<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Correspondances (DAT-01 à DAT-03) et liaison LogiExam → LogiNotes (EXG-07).
 *
 * Les identifiants historiques ne sont jamais modifiés : les liens sont stockés à part.
 * Un rapprochement n'est définitif qu'après validation par une personne autorisée.
 */
final class LogiPilot_Links
{
    public static function init(): void
    {
        add_action('admin_post_logipilot_links', array(__CLASS__, 'handle_links'));
        add_action('admin_post_logipilot_complete_exam_link', array(__CLASS__, 'handle_complete_exam_link'));
        add_action('logipilot_exam_evaluation_saved', array(__CLASS__, 'on_exam_evaluation_saved'), 10, 2);
        add_action('logipilot_notes_evaluation_saved', array(__CLASS__, 'on_notes_evaluation_saved'), 10, 1);
        add_action('logipilot_notes_evaluation_form_fields', array(__CLASS__, 'render_notes_form_fields'));
    }

    /* -------------------------------------------------------------------
     * Méta des évaluations LogiNotes (cours, groupe, origine)
     * ----------------------------------------------------------------- */

    public static function notes_meta(int $evaluation_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . LogiPilot_Install::table('notes_meta') . ' WHERE notes_evaluation_id = %d', $evaluation_id)) ?: null;
    }

    public static function save_notes_meta(int $evaluation_id, array $fields): void
    {
        global $wpdb;

        $table = LogiPilot_Install::table('notes_meta');
        $current = self::notes_meta($evaluation_id);
        $data = array_merge(
            array('origin' => 'paper', 'course_id' => 0, 'group_id' => 0, 'progress_deadline' => null),
            $current ? (array) $current : array(),
            $fields,
            array('notes_evaluation_id' => $evaluation_id, 'updated_at' => current_time('mysql'))
        );
        $wpdb->replace($table, $data);
    }

    /** Élèves actifs de la classe, plus tout élève déjà noté dans l'évaluation (changement de classe). */
    public static function notes_participants(int $class_id, int $evaluation_id): array
    {
        global $wpdb;

        $students = LogiPilot_Spaces::notes_table('students');
        $grades = LogiPilot_Spaces::notes_table('grades');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.* FROM {$students} s
             WHERE (s.class_id = %d AND s.status = 'active')
                OR s.id IN (SELECT g.student_id FROM {$grades} g WHERE g.evaluation_id = %d)
             ORDER BY s.last_name ASC, s.first_name ASC",
            $class_id,
            $evaluation_id
        ));
    }

    /** Même barème que LogiNotes : (points retenus / maximum) × 5 + 1, borné, arrondi au demi-point. */
    public static function calculate_note(float $points, float $total_points, float $adjustment): ?float
    {
        if ($total_points <= 0) {
            return null;
        }
        $adjusted = min(max($points + $adjustment, 0.0), $total_points);

        return round(((($adjusted / $total_points) * 5) + 1) * 2) / 2;
    }

    /* -------------------------------------------------------------------
     * Correspondances classes et élèves
     * ----------------------------------------------------------------- */

    public static function wp_user_for_notes_student(int $student_id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare('SELECT wp_user_id FROM ' . LogiPilot_Install::table('student_links') . ' WHERE notes_student_id = %d', $student_id));
    }

    /** Fiche LogiNotes liée à un compte, de préférence dans la classe donnée. */
    public static function notes_student_for_user(int $user_id, int $class_id = 0): int
    {
        global $wpdb;

        $links = LogiPilot_Install::table('student_links');
        $students = LogiPilot_Spaces::notes_table('students');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT s.id, s.class_id FROM {$links} l INNER JOIN {$students} s ON s.id = l.notes_student_id WHERE l.wp_user_id = %d ORDER BY s.id DESC", $user_id));
        foreach ($rows as $row) {
            if ($class_id > 0 && (int) $row->class_id === $class_id) {
                return (int) $row->id;
            }
        }
        if ($class_id > 0 && $rows) {
            // L'élève a pu changer de classe : on garde sa fiche, l'évaluation la conservera (EXG-11).
            return (int) $rows[0]->id;
        }

        return $rows ? (int) $rows[0]->id : 0;
    }

    public static function groups_for_notes_class(int $class_id): array
    {
        global $wpdb;

        return array_map('intval', $wpdb->get_col($wpdb->prepare('SELECT sensei_group_id FROM ' . LogiPilot_Install::table('class_links') . ' WHERE notes_class_id = %d', $class_id)));
    }

    public static function notes_class_for_group(int $group_id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare('SELECT notes_class_id FROM ' . LogiPilot_Install::table('class_links') . ' WHERE sensei_group_id = %d ORDER BY id DESC LIMIT 1', $group_id));
    }

    public static function normalize_name(string $value): string
    {
        $value = remove_accents(mb_strtolower(wp_strip_all_tags($value), 'UTF-8'));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /** Suggestions de rapprochement : jamais appliquées sans validation (DAT-02). */
    public static function suggestions_for_class(int $class_id): array
    {
        global $wpdb;

        $candidates = array();
        foreach (self::groups_for_notes_class($class_id) as $group_id) {
            $members = class_exists('LogiProgress_Sensei_Group_Progress_Tracker') ? LogiProgress_Sensei_Group_Progress_Tracker::api_group_students($group_id) : null;
            if ($members === null && class_exists('LogiEval_Secure_Sensei')) {
                $members = array_map(static fn($user) => (int) $user->ID, LogiEval_Secure_Sensei::get_group_students($group_id));
            }
            foreach ((array) $members as $user_id) {
                $candidates[(int) $user_id] = true;
            }
        }

        $by_name = array();
        foreach (array_keys($candidates) as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) {
                continue;
            }
            $names = array_filter(array(
                self::normalize_name($user->last_name . ' ' . $user->first_name),
                self::normalize_name($user->first_name . ' ' . $user->last_name),
                self::normalize_name($user->display_name),
            ));
            foreach (array_unique($names) as $name) {
                $by_name[$name][] = $user_id;
            }
        }

        $suggestions = array();
        $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . LogiPilot_Spaces::notes_table('students') . " WHERE class_id = %d AND status = 'active' ORDER BY last_name, first_name", $class_id));
        foreach ($students as $student) {
            $key = self::normalize_name($student->last_name . ' ' . $student->first_name);
            $matches = array_values(array_unique($by_name[$key] ?? array()));
            $suggestions[(int) $student->id] = array(
                'student' => $student,
                'linked' => self::wp_user_for_notes_student((int) $student->id),
                'matches' => $matches,
                'candidates' => array_keys($candidates),
            );
        }

        return $suggestions;
    }

    public static function handle_links(): void
    {
        if (!current_user_can('lp_integration_link')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_links');
        global $wpdb;

        $class_id = absint($_POST['notes_class_id'] ?? 0);
        if (!LogiPilot_Permissions::notes_class_allowed($class_id)) {
            wp_die('Classe hors de votre périmètre.', 'Accès refusé', array('response' => 403));
        }
        $op = sanitize_key(wp_unslash($_POST['op'] ?? ''));
        $now = current_time('mysql');

        if ($op === 'link_group') {
            $group_id = absint($_POST['sensei_group_id'] ?? 0);
            if ($class_id && $group_id) {
                $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . LogiPilot_Install::table('class_links') . ' (notes_class_id, sensei_group_id, validated_by, validated_at) VALUES (%d, %d, %d, %s)', $class_id, $group_id, get_current_user_id(), $now));
                LogiPilot_Log::add('class_linked', 'notes_class', $class_id, array('group' => $group_id));
            }
        } elseif ($op === 'unlink_group') {
            $group_id = absint($_POST['sensei_group_id'] ?? 0);
            $wpdb->delete(LogiPilot_Install::table('class_links'), array('notes_class_id' => $class_id, 'sensei_group_id' => $group_id));
            LogiPilot_Log::add('class_unlinked', 'notes_class', $class_id, array('group' => $group_id));
        } elseif ($op === 'save_students') {
            foreach ((array) wp_unslash($_POST['student_user'] ?? array()) as $student_id => $user_id) {
                $student_id = absint($student_id);
                $user_id = absint($user_id);
                $owner = (int) $wpdb->get_var($wpdb->prepare("SELECT class_id FROM " . LogiPilot_Spaces::notes_table('students') . " WHERE id = %d", $student_id));
                if ($owner !== $class_id) {
                    continue;
                }
                $current = self::wp_user_for_notes_student($student_id);
                if ($user_id === $current) {
                    continue;
                }
                $wpdb->delete(LogiPilot_Install::table('student_links'), array('notes_student_id' => $student_id));
                if ($user_id > 0) {
                    $wpdb->insert(LogiPilot_Install::table('student_links'), array('notes_student_id' => $student_id, 'wp_user_id' => $user_id, 'validated_by' => get_current_user_id(), 'validated_at' => $now));
                }
                LogiPilot_Log::add('student_linked', 'notes_student', $student_id, array('from' => $current, 'to' => $user_id));
            }
        } elseif ($op === 'create_students') {
            // Crée dans LogiNotes les membres du groupe sans fiche, puis les relie (sur demande explicite).
            foreach (array_map('absint', (array) ($_POST['create_user'] ?? array())) as $user_id) {
                $user = get_userdata($user_id);
                if (!$user || self::notes_student_for_user($user_id, $class_id)) {
                    continue;
                }
                $wpdb->insert(LogiPilot_Spaces::notes_table('students'), array(
                    'class_id' => $class_id,
                    'last_name' => $user->last_name ?: $user->display_name,
                    'first_name' => $user->first_name ?: '',
                    'identifier' => '',
                    'email' => '',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ));
                $student_id = (int) $wpdb->insert_id;
                $wpdb->insert(LogiPilot_Install::table('student_links'), array('notes_student_id' => $student_id, 'wp_user_id' => $user_id, 'validated_by' => get_current_user_id(), 'validated_at' => $now));
                LogiPilot_Log::add('student_created_from_group', 'notes_student', $student_id, array('user' => $user_id));
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=logione-links&notes_class_id=' . $class_id . '&saved=1'));
        exit;
    }

    public static function render_links_page(): void
    {
        if (!current_user_can('lp_integration_link')) {
            wp_die('Accès refusé.', 403);
        }
        global $wpdb;

        $classes = $wpdb->get_results("SELECT * FROM " . LogiPilot_Spaces::notes_table('classes') . " WHERE 1=1" . LogiPilot_Permissions::notes_class_sql('id') . ' ORDER BY school_year DESC, name ASC');
        $class_id = absint($_GET['notes_class_id'] ?? ($classes[0]->id ?? 0));
        $groups = class_exists('LogiProgress_Sensei_Group_Progress_Tracker') ? LogiProgress_Sensei_Group_Progress_Tracker::api_groups() : array();
        echo '<div class="wrap logipilot-wrap"><h1>LogiOne · Correspondances</h1>';
        echo '<p>Reliez chaque classe LogiNotes à son groupe Sensei, puis chaque élève à son compte WordPress. Rien n’est relié automatiquement : les suggestions par nom doivent être validées. Les identifiants historiques ne changent jamais.</p>';
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Correspondances enregistrées.</p></div>';
        }
        if (!$classes) {
            echo '<p>Aucune classe LogiNotes accessible.</p></div>';
            return;
        }
        echo '<form method="get"><input type="hidden" name="page" value="logione-links"><label>Classe LogiNotes <select name="notes_class_id" onchange="this.form.submit()">';
        foreach ($classes as $class) {
            printf('<option value="%d" %s>%s</option>', (int) $class->id, selected($class_id, (int) $class->id, false), esc_html($class->name . ' · ' . $class->school_year));
        }
        echo '</select></label> <button class="button">Afficher</button></form>';

        $linked = self::groups_for_notes_class($class_id);
        echo '<h2>Groupes Sensei reliés</h2><ul>';
        foreach ($linked as $group_id) {
            echo '<li>' . esc_html(get_the_title($group_id) ?: ('Groupe ' . $group_id)) . ' ';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            wp_nonce_field('logipilot_links');
            printf('<input type="hidden" name="action" value="logipilot_links"><input type="hidden" name="op" value="unlink_group"><input type="hidden" name="notes_class_id" value="%d"><input type="hidden" name="sensei_group_id" value="%d"><button class="button-link">retirer</button></form></li>', $class_id, $group_id);
        }
        if (!$linked) {
            echo '<li>Aucun groupe relié.</li>';
        }
        echo '</ul><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('logipilot_links');
        printf('<input type="hidden" name="action" value="logipilot_links"><input type="hidden" name="op" value="link_group"><input type="hidden" name="notes_class_id" value="%d">', $class_id);
        echo '<select name="sensei_group_id"><option value="0">Choisir un groupe Sensei</option>';
        foreach ($groups as $group) {
            printf('<option value="%d">%s</option>', (int) $group->ID, esc_html($group->post_title));
        }
        echo '</select> <button class="button">Relier ce groupe</button></form>';

        $suggestions = self::suggestions_for_class($class_id);
        $all_candidates = array();
        foreach ($suggestions as $item) {
            foreach ($item['candidates'] as $user_id) {
                $all_candidates[$user_id] = true;
            }
        }
        echo '<h2>Élèves</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('logipilot_links');
        printf('<input type="hidden" name="action" value="logipilot_links"><input type="hidden" name="op" value="save_students"><input type="hidden" name="notes_class_id" value="%d">', $class_id);
        echo '<table class="widefat striped"><thead><tr><th>Élève LogiNotes</th><th>Compte WordPress</th><th>État</th></tr></thead><tbody>';
        foreach ($suggestions as $student_id => $item) {
            $student = $item['student'];
            $linked_user = (int) $item['linked'];
            $matches = $item['matches'];
            $selected = $linked_user ?: (count($matches) === 1 ? (int) $matches[0] : 0);
            $state = $linked_user ? 'Relié' : (count($matches) === 1 ? 'Suggestion par nom : à valider' : (count($matches) > 1 ? 'Homonymes : choisir' : 'Aucun compte trouvé'));
            echo '<tr><td>' . esc_html($student->last_name . ' ' . $student->first_name) . '</td><td><select name="student_user[' . (int) $student_id . ']"><option value="0">— Aucun —</option>';
            $options = array_unique(array_merge(array_keys($all_candidates), $linked_user ? array($linked_user) : array()));
            foreach ($options as $user_id) {
                $user = get_userdata((int) $user_id);
                if ($user) {
                    printf('<option value="%d" %s>%s (%s)</option>', (int) $user_id, selected($selected, (int) $user_id, false), esc_html($user->display_name), esc_html($user->user_login));
                }
            }
            echo '</select></td><td>' . esc_html($state) . '</td></tr>';
        }
        if (!$suggestions) {
            echo '<tr><td colspan="3">Aucun élève actif dans cette classe.</td></tr>';
        }
        echo '</tbody></table><p><button class="button button-primary">Valider les correspondances affichées</button></p></form>';

        $unlinked_members = array();
        foreach (array_keys($all_candidates) as $user_id) {
            if (!self::notes_student_for_user((int) $user_id, $class_id)) {
                $unlinked_members[] = (int) $user_id;
            }
        }
        if ($unlinked_members) {
            echo '<h2>Membres du groupe sans fiche LogiNotes</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('logipilot_links');
            printf('<input type="hidden" name="action" value="logipilot_links"><input type="hidden" name="op" value="create_students"><input type="hidden" name="notes_class_id" value="%d"><ul>', $class_id);
            foreach ($unlinked_members as $user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    printf('<li><label><input type="checkbox" name="create_user[]" value="%d"> %s (%s)</label></li>', $user_id, esc_html($user->display_name), esc_html($user->user_login));
                }
            }
            echo '</ul><p><button class="button">Créer les fiches cochées dans LogiNotes et les relier</button></p></form>';
        }
        echo '</div>';
    }

    /* -------------------------------------------------------------------
     * LogiNotes : cours et groupe Sensei d'une évaluation papier
     * ----------------------------------------------------------------- */

    public static function render_notes_form_fields($evaluation): void
    {
        $evaluation_id = is_object($evaluation) ? (int) $evaluation->id : 0;
        $meta = $evaluation_id ? self::notes_meta($evaluation_id) : null;
        if ($meta && $meta->origin === 'exam') {
            echo '<p class="gne-muted">Évaluation créée depuis LogiExam : les résultats sont publiés depuis LogiExam.</p>';
            return;
        }
        if (!class_exists('LogiProgress_Sensei_Group_Progress_Tracker')) {
            return;
        }
        $courses = LogiProgress_Sensei_Group_Progress_Tracker::api_courses();
        $groups = LogiProgress_Sensei_Group_Progress_Tracker::api_groups();
        ?>
        <label class="gne-field">
            <span>Cours Sensei (bonus / malus de progression)</span>
            <select name="logipilot_course_id">
                <option value="0">Aucun</option>
                <?php foreach ($courses as $course) : ?>
                    <option value="<?php echo esc_attr((string) $course->ID); ?>" <?php selected((int) ($meta->course_id ?? 0), (int) $course->ID); ?>><?php echo esc_html($course->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gne-field">
            <span>Groupe Sensei</span>
            <select name="logipilot_group_id">
                <option value="0">Selon la classe</option>
                <?php foreach ($groups as $group) : ?>
                    <option value="<?php echo esc_attr((string) $group->ID); ?>" <?php selected((int) ($meta->group_id ?? 0), (int) $group->ID); ?>><?php echo esc_html($group->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }

    public static function on_notes_evaluation_saved(int $evaluation_id): void
    {
        if (!isset($_POST['logipilot_course_id'])) {
            return;
        }
        $meta = self::notes_meta($evaluation_id);
        if ($meta && $meta->origin === 'exam') {
            return;
        }
        global $wpdb;
        $group_id = absint($_POST['logipilot_group_id'] ?? 0);
        if ($group_id === 0) {
            $class_id = (int) $wpdb->get_var($wpdb->prepare("SELECT class_id FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE id = %d", $evaluation_id));
            $group_id = (int) (self::groups_for_notes_class($class_id)[0] ?? 0);
        }
        self::save_notes_meta($evaluation_id, array('course_id' => absint($_POST['logipilot_course_id']), 'group_id' => $group_id));
    }

    /* -------------------------------------------------------------------
     * LogiExam → LogiNotes : fiche créée automatiquement (EXG-07, DAT-05)
     * ----------------------------------------------------------------- */

    public static function exam_link(int $exam_evaluation_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . LogiPilot_Install::table('exam_links') . ' WHERE exam_evaluation_id = %d', $exam_evaluation_id)) ?: null;
    }

    public static function exam_links_for_notes(int $notes_evaluation_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . LogiPilot_Install::table('exam_links') . ' WHERE notes_evaluation_id = %d', $notes_evaluation_id));
    }

    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function on_exam_evaluation_saved(int $exam_evaluation_id, bool $is_new): void
    {
        LogiPilot_Spaces::run_for_exam($exam_evaluation_id, static fn() => self::on_exam_evaluation_saved_here($exam_evaluation_id, $is_new));
    }

    private static function on_exam_evaluation_saved_here(int $exam_evaluation_id, bool $is_new): void
    {
        if (!LogiPilot_Plugin::service_enabled('exam_to_notes')) {
            return;
        }
        self::ensure_notes_fiche($exam_evaluation_id, true);
    }

    /** Une seule fiche par évaluation : relancer la création ne crée jamais de doublon (REC-15). */
    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function ensure_notes_fiche(int $exam_evaluation_id, bool $sync_fields = false): ?object
    {
        return LogiPilot_Spaces::run_for_exam($exam_evaluation_id, static fn() => self::ensure_notes_fiche_here($exam_evaluation_id, $sync_fields));
    }

    private static function ensure_notes_fiche_here(int $exam_evaluation_id, bool $sync_fields = false): ?object
    {
        global $wpdb;

        if (!class_exists('LogiEval_Secure_Evaluations')) {
            return null;
        }
        $evaluation = LogiEval_Secure_Evaluations::get_evaluation($exam_evaluation_id);
        if (!$evaluation || ($evaluation['status'] ?? '') === 'deleted') {
            return null;
        }

        $table = LogiPilot_Install::table('exam_links');
        $now = current_time('mysql');
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (exam_evaluation_id, notes_evaluation_id, link_role, state, message, created_by, created_at, updated_at) VALUES (%d, 0, 'main', 'pending_data', '', %d, %s, %s)", $exam_evaluation_id, get_current_user_id(), $now, $now));
        $link = self::exam_link($exam_evaluation_id);
        if (!$link || (int) $link->notes_evaluation_id > 0) {
            if ($sync_fields && $link && (int) $link->notes_evaluation_id > 0) {
                self::sync_fiche_fields($link, $evaluation);
            }
            return $link;
        }

        if (!self::table_ready('evaluations')) {
            self::set_link_state($exam_evaluation_id, 'pending_data', 'LogiNotes est indisponible : la fiche sera créée à la prochaine reprise.');
            return self::exam_link($exam_evaluation_id);
        }

        $groups = LogiEval_Secure_Evaluations::decode_ids($evaluation['sensei_group_ids'] ?? '[]');
        $class_id = 0;
        foreach ($groups as $group_id) {
            $class_id = self::notes_class_for_group((int) $group_id);
            if ($class_id) {
                break;
            }
        }
        $date = !empty($evaluation['opens_at']) ? substr((string) $evaluation['opens_at'], 0, 10) : wp_date('Y-m-d');
        $semester_id = self::semester_for_date($date);

        $missing = array();
        if (!$class_id) {
            $missing[] = 'classe LogiNotes (reliez le groupe Sensei à une classe dans Correspondances)';
        }
        if (!$semester_id) {
            $missing[] = 'semestre';
        }
        if ((float) $evaluation['total_points'] <= 0) {
            $missing[] = 'maximum de points';
        }
        if ($missing) {
            self::set_link_state($exam_evaluation_id, 'pending_data', 'Fiche LogiNotes en attente de complément : ' . implode(', ', $missing) . '.');
            return self::exam_link($exam_evaluation_id);
        }

        return self::create_fiche($exam_evaluation_id, $evaluation, $class_id, $semester_id, $date);
    }

    private static function create_fiche(int $exam_evaluation_id, array $evaluation, int $class_id, int $semester_id, string $date): ?object
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert(LogiPilot_Spaces::notes_table('evaluations'), array(
            'class_id' => $class_id,
            'semester_id' => $semester_id,
            'branch_id' => 0,
            'module_name' => mb_substr((string) $evaluation['title'], 0, 190),
            'evaluation_date' => $date,
            'is_makeup' => 0,
            'total_points' => (float) $evaluation['total_points'],
            'created_by' => (int) ($evaluation['created_by'] ?? get_current_user_id()),
            'created_at' => $now,
            'updated_at' => $now,
        ));
        $notes_id = (int) $wpdb->insert_id;
        if ($notes_id <= 0) {
            self::set_link_state($exam_evaluation_id, 'error', 'Création de la fiche LogiNotes impossible : ' . $wpdb->last_error);
            return self::exam_link($exam_evaluation_id);
        }

        $wpdb->update(LogiPilot_Install::table('exam_links'), array('notes_evaluation_id' => $notes_id, 'state' => 'ready', 'message' => 'Fiche créée, en attente des résultats publiés. Semestre et date modifiables dans LogiNotes.', 'updated_at' => $now), array('exam_evaluation_id' => $exam_evaluation_id, 'notes_evaluation_id' => 0));
        self::save_notes_meta($notes_id, array('origin' => 'exam', 'course_id' => (int) ($evaluation['sensei_course_id'] ?? 0)));
        LogiPilot_Log::add('notes_fiche_created', 'exam_evaluation', $exam_evaluation_id, array('notes_evaluation' => $notes_id));

        return self::exam_link($exam_evaluation_id);
    }

    /** Le titre et le maximum suivent LogiExam tant qu'aucun résultat n'est publié. */
    private static function sync_fiche_fields(object $link, array $evaluation): void
    {
        global $wpdb;

        $published = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . LogiPilot_Install::table('publications') . " WHERE notes_evaluation_id = %d AND state = 'done'", $link->notes_evaluation_id));
        if ($published > 0 || $link->link_role !== 'main') {
            return;
        }
        $wpdb->update(LogiPilot_Spaces::notes_table('evaluations'), array('module_name' => mb_substr((string) $evaluation['title'], 0, 190), 'total_points' => (float) $evaluation['total_points'], 'updated_at' => current_time('mysql')), array('id' => (int) $link->notes_evaluation_id));
    }

    public static function set_link_state(int $exam_evaluation_id, string $state, string $message): void
    {
        global $wpdb;

        $wpdb->update(LogiPilot_Install::table('exam_links'), array('state' => $state, 'message' => $message, 'updated_at' => current_time('mysql')), array('exam_evaluation_id' => $exam_evaluation_id));
    }

    private static function table_ready(string $name): bool
    {
        global $wpdb;

        $table = LogiPilot_Spaces::notes_table($name);

        return LogiPilot_Plugin::is_loaded('notes') && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function semester_for_date(string $date): int
    {
        global $wpdb;

        $table = LogiPilot_Spaces::notes_table('semesters');
        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE status = 'active' AND start_date <= %s AND end_date >= %s ORDER BY sort_order ASC, id DESC LIMIT 1", $date, $date));

        return $id;
    }

    /** Complément manuel d'une fiche en attente, ou rattachement à une fiche existante (rattrapage). */
    public static function handle_complete_exam_link(): void
    {
        $exam_id = absint($_POST['exam_evaluation_id'] ?? 0);
        check_admin_referer('logipilot_complete_exam_link_' . $exam_id);
        LogiPilot_Spaces::push(LogiPilot_Spaces::exam_space($exam_id));
        $evaluation = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation($exam_id) : null;
        if (!$evaluation || !current_user_can('lp_exam_teach') || !LogiEval_Secure_Evaluations::can_manage_evaluation($evaluation)) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        $return = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return'] ?? '')), admin_url());
        $link = self::ensure_notes_fiche($exam_id);
        $existing = absint($_POST['attach_notes_evaluation_id'] ?? 0);
        global $wpdb;

        if ($existing > 0 && $link && (int) $link->notes_evaluation_id === 0) {
            $class_id = (int) $wpdb->get_var($wpdb->prepare("SELECT class_id FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE id = %d", $existing));
            if ($class_id && LogiPilot_Permissions::notes_class_allowed($class_id)) {
                $wpdb->update(LogiPilot_Install::table('exam_links'), array('notes_evaluation_id' => $existing, 'link_role' => 'makeup', 'state' => 'ready', 'message' => 'Rattachée à une fiche LogiNotes existante (rattrapage).', 'updated_at' => current_time('mysql')), array('exam_evaluation_id' => $exam_id));
                LogiPilot_Log::add('exam_attached_to_notes', 'exam_evaluation', $exam_id, array('notes_evaluation' => $existing));
            }
        } elseif ($link && (int) $link->notes_evaluation_id === 0) {
            $class_id = absint($_POST['notes_class_id'] ?? 0);
            $semester_id = absint($_POST['semester_id'] ?? 0);
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash($_POST['evaluation_date'] ?? '')) ? (string) wp_unslash($_POST['evaluation_date']) : wp_date('Y-m-d');
            if ($class_id && $semester_id && LogiPilot_Permissions::notes_class_allowed($class_id) && (float) $evaluation['total_points'] > 0) {
                self::create_fiche($exam_id, $evaluation, $class_id, $semester_id, $date);
            }
        }
        wp_safe_redirect($return . '#logipilot-exam-link');
        exit;
    }

    /** Encadré affiché sur la page d'une évaluation LogiExam. */
    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function render_exam_link_box(array $evaluation, string $return_url): void
    {
        LogiPilot_Spaces::run_for_exam((int) $evaluation['id'], static fn() => self::render_exam_link_box_here($evaluation, $return_url));
    }

    private static function render_exam_link_box_here(array $evaluation, string $return_url): void
    {
        global $wpdb;

        $exam_id = (int) $evaluation['id'];
        $link = self::exam_link($exam_id);
        if (LogiPilot_Plugin::service_enabled('exam_to_notes') && (!$link || (int) $link->notes_evaluation_id === 0)) {
            $link = self::ensure_notes_fiche($exam_id); // reprise d'une création en attente, sans doublon
        }
        echo '<section class="logipilot-panel" id="logipilot-exam-link"><h3>Fiche LogiNotes</h3>';
        if (!$link) {
            echo '<p>Liaison avec LogiNotes désactivée.</p></section>';
            return;
        }
        if ((int) $link->notes_evaluation_id > 0) {
            $notes = $wpdb->get_row($wpdb->prepare("SELECT e.*, c.name AS class_name FROM " . LogiPilot_Spaces::notes_table('evaluations') . " e LEFT JOIN " . LogiPilot_Spaces::notes_table('classes') . " c ON c.id = e.class_id WHERE e.id = %d", $link->notes_evaluation_id));
            $url = admin_url('admin.php?page=gne_app&gne_tab=evaluations&evaluation_id=' . (int) $link->notes_evaluation_id);
            echo '<p>' . esc_html(($link->link_role === 'makeup' ? 'Rattrapage rattaché à ' : 'Fiche liée : ') . ($notes ? $notes->module_name . ' · ' . $notes->class_name : '#' . $link->notes_evaluation_id)) . ' — ';
            echo current_user_can('lp_notes_view') ? '<a href="' . esc_url($url) . '">ouvrir dans LogiNotes</a>' : 'consultation réservée aux utilisateurs de LogiNotes';
            echo '</p><p class="logipilot-muted">' . esc_html(LogiPilot_Publisher::state_label((string) $link->state) . ' · ' . (string) $link->message) . '</p>';
        } else {
            echo '<p class="logipilot-warning">' . esc_html((string) $link->message) . '</p>';
            if (current_user_can('lp_exam_teach') && self::table_ready('evaluations')) {
                $classes = $wpdb->get_results("SELECT id, name, school_year FROM " . LogiPilot_Spaces::notes_table('classes') . " WHERE status = 'active'" . LogiPilot_Permissions::notes_class_sql('id') . ' ORDER BY name');
                $semesters = $wpdb->get_results("SELECT id, name, school_year FROM " . LogiPilot_Spaces::notes_table('semesters') . " WHERE status = 'active' ORDER BY school_year DESC, sort_order ASC");
                $existing = $wpdb->get_results("SELECT id, module_name, evaluation_date FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE 1=1" . LogiPilot_Permissions::notes_class_sql('class_id') . ' ORDER BY evaluation_date DESC LIMIT 50');
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('logipilot_complete_exam_link_' . $exam_id);
                printf('<input type="hidden" name="action" value="logipilot_complete_exam_link"><input type="hidden" name="exam_evaluation_id" value="%d"><input type="hidden" name="return" value="%s">', $exam_id, esc_attr($return_url));
                echo '<p><label>Classe <select name="notes_class_id"><option value="0">—</option>';
                foreach ($classes as $class) {
                    printf('<option value="%d">%s</option>', (int) $class->id, esc_html($class->name . ' · ' . $class->school_year));
                }
                echo '</select></label> <label>Semestre <select name="semester_id"><option value="0">—</option>';
                foreach ($semesters as $semester) {
                    printf('<option value="%d">%s</option>', (int) $semester->id, esc_html($semester->name . ' · ' . $semester->school_year));
                }
                echo '</select></label> <label>Date <input type="date" name="evaluation_date" value="' . esc_attr(!empty($evaluation['opens_at']) ? substr((string) $evaluation['opens_at'], 0, 10) : wp_date('Y-m-d')) . '"></label></p>';
                echo '<p>ou rattacher comme rattrapage à <select name="attach_notes_evaluation_id"><option value="0">—</option>';
                foreach ($existing as $item) {
                    printf('<option value="%d">%s</option>', (int) $item->id, esc_html($item->module_name . ' · ' . $item->evaluation_date));
                }
                echo '</select></p><p><button class="button button-primary">Compléter la fiche LogiNotes</button></p></form>';
            }
        }
        echo '</section>';
    }
}
