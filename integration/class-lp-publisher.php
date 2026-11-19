<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Publication des résultats LogiExam vers LogiNotes (lot 4, EXG-08, NUM-01 à NUM-03, SYN-01 à SYN-05).
 *
 * - Le résultat est d'abord sauvegardé dans LogiExam ; la publication est une demande durable
 *   (table logipilot_publications) traitée immédiatement ou reprise plus tard.
 * - Une clé d'opération unique par copie, révision et destination rend la publication rejouable
 *   sans doublon. Une ancienne révision ne remplace jamais une plus récente.
 * - LogiNotes reçoit le score brut et le détail des ajustements, et la note calculée par LogiExam :
 *   aucun bonus ni malus n'est appliqué une seconde fois.
 * - Une modification locale incompatible dans LogiNotes produit un conflit, jamais un écrasement.
 */
final class LogiPilot_Publisher
{
    public const MAX_ATTEMPTS = 5;

    public static function init(): void
    {
        add_action('admin_post_logipilot_publish', array(__CLASS__, 'handle_publish'));
        add_action('admin_post_logipilot_match_students', array(__CLASS__, 'handle_match_students'));
        add_action('admin_post_logipilot_retry', array(__CLASS__, 'handle_retry'));
        add_action('admin_post_logipilot_resolve', array(__CLASS__, 'handle_resolve'));
        add_action('admin_post_logipilot_progress_switch', array(__CLASS__, 'handle_progress_switch'));
        add_action('logipilot_sync_retry', array(__CLASS__, 'retry_pending'));
        add_action('logipilot_exam_correction_reopened', array(__CLASS__, 'on_reopened'), 10, 2);
        add_filter('logipilot_notes_locked_students', array(__CLASS__, 'locked_students'), 10, 2);
        add_action('logipilot_notes_evaluation_detail', array(__CLASS__, 'render_notes_origin_panel'));
        add_filter('logipilot_attempt_publication_label', array(__CLASS__, 'publication_label'), 10, 2);
    }

    public static function table(): string
    {
        return LogiPilot_Install::table('publications');
    }

    public static function state_label(string $state): string
    {
        return array(
            'pending' => 'En attente',
            'pending_data' => 'En attente de complément',
            'processing' => 'En cours',
            'done' => 'Réussi',
            'ready' => 'Prête',
            'published' => 'Résultats publiés',
            'revision' => 'En cours de révision',
            'error' => 'En erreur',
            'conflict' => 'En conflit',
            'superseded' => 'Remplacée par une révision plus récente',
            'unmatched' => 'Non rapprochée',
        )[$state] ?? $state;
    }

    /* -------------------------------------------------------------------
     * Préparation : quelles copies sont prêtes ?
     * ----------------------------------------------------------------- */

    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function overview(int $exam_evaluation_id): array
    {
        return LogiPilot_Spaces::run_for_exam($exam_evaluation_id, static fn() => self::overview_here($exam_evaluation_id));
    }

    private static function overview_here(int $exam_evaluation_id): array
    {
        global $wpdb;

        $evaluation = LogiEval_Secure_Evaluations::get_evaluation($exam_evaluation_id);
        $link = LogiPilot_Links::exam_link($exam_evaluation_id);
        $notes_class_id = $link && $link->notes_evaluation_id ? (int) $wpdb->get_var($wpdb->prepare("SELECT class_id FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE id = %d", $link->notes_evaluation_id)) : 0;
        $rows = array();

        foreach (LogiEval_Secure_Evaluations::get_attempts($exam_evaluation_id) as $attempt) {
            $result = LogiEval_Secure_Evaluations::get_result_by_attempt((int) $attempt['id']);
            $user = get_userdata((int) $attempt['user_id']);
            $row = array(
                'attempt' => $attempt,
                'result' => $result,
                'label' => $user ? $user->display_name : ('Compte ' . $attempt['user_id']),
                'notes_student_id' => LogiPilot_Links::notes_student_for_user((int) $attempt['user_id'], $notes_class_id),
                'status' => 'pending',
                'message' => '',
                'last' => self::last_done((int) $attempt['id']),
            );
            if ($attempt['status'] === 'security_terminated') {
                $row['status'] = 'ready';
                $row['flag'] = 'cheating';
            } elseif ($result && $result['status'] === 'graded') {
                $row['status'] = 'ready';
                $row['flag'] = 'graded';
            } else {
                $row['message'] = $row['last'] ? 'Correction rouverte : en cours de révision' : 'Correction non finalisée';
                $row['status'] = $row['last'] ? 'revision' : 'pending';
            }
            if ($row['status'] === 'ready' && !$row['notes_student_id']) {
                $row['status'] = 'unmatched';
                $row['message'] = 'aucune fiche LogiNotes choisie';
            }
            if ($row['status'] === 'ready' && $row['last'] && self::same_components($row['last'], self::components($evaluation, $attempt, $result, $row['flag'], $exam_evaluation_id))) {
                $row['status'] = 'published';
                $row['message'] = 'Déjà publié (révision ' . (int) $row['last']->revision . ')';
            }
            $rows[] = $row;
        }

        return array('evaluation' => $evaluation, 'link' => $link, 'rows' => $rows, 'notes_class_id' => $notes_class_id);
    }

    /**
     * 1.36.1 : proposition de rapprochement pour un compte élève (WordPress) dans la classe LogiNotes :
     * même nom (prénom/nom dans un ordre ou l'autre, sans accents) ou même e-mail. Rien n'est enregistré ici :
     * l'enseignant valide le choix dans le panneau de publication (DAT-02).
     */
    private static function suggest_student(int $user_id, array $students): int
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return 0;
        }
        $names = array_filter(array_unique(array(
            LogiPilot_Links::normalize_name($user->last_name . ' ' . $user->first_name),
            LogiPilot_Links::normalize_name($user->first_name . ' ' . $user->last_name),
            LogiPilot_Links::normalize_name($user->display_name),
            LogiPilot_Links::normalize_name($user->user_login),
        )));
        $email = strtolower(trim((string) $user->user_email));
        $found = array();
        foreach ($students as $student) {
            if (LogiPilot_Links::wp_user_for_notes_student((int) $student->id)) {
                continue; // déjà relié à un autre compte
            }
            $a = LogiPilot_Links::normalize_name($student->last_name . ' ' . $student->first_name);
            $b = LogiPilot_Links::normalize_name($student->first_name . ' ' . $student->last_name);
            if (($email !== '' && strtolower(trim((string) ($student->email ?? ''))) === $email) || in_array($a, $names, true) || in_array($b, $names, true)) {
                $found[] = (int) $student->id;
            }
        }

        return count($found) === 1 ? $found[0] : 0;
    }

    private static function class_students(int $class_id): array
    {
        global $wpdb;

        if ($class_id <= 0) {
            return array();
        }

        return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . LogiPilot_Spaces::notes_table('students') . " WHERE class_id = %d AND status = 'active' ORDER BY last_name, first_name", $class_id));
    }

    /** Rapprochement validé depuis le panneau de publication (back office ou page web), puis publication. */
    public static function handle_match_students(): void
    {
        $exam_id = absint($_POST['exam_evaluation_id'] ?? 0);
        check_admin_referer('logipilot_match_' . $exam_id);
        $evaluation = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation($exam_id) : null;
        if (!$evaluation || !current_user_can('lp_exam_publish') || !LogiEval_Secure_Evaluations::can_manage_evaluation($evaluation)) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        $return = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return'] ?? '')), admin_url());
        $choices = array_map('sanitize_text_field', (array) wp_unslash($_POST['lp_match'] ?? array()));
        $publish = !empty($_POST['lp_then_publish']);
        $report = LogiPilot_Spaces::run_for_exam($exam_id, static fn() => self::match_students_here($exam_id, $choices));
        if ($publish) {
            $pub = self::publish($exam_id);
            $pub['errors'] = array_merge($report['errors'], $pub['errors']);
            $pub['linked'] = $report['linked'];
            $report = $pub;
        } else {
            $report += array('done' => 0, 'unchanged' => 0, 'waiting' => 0);
        }
        set_transient('logipilot_publish_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('lp_published', 1, $return) . '#logipilot-publish');
        exit;
    }

    private static function match_students_here(int $exam_id, array $choices): array
    {
        global $wpdb;

        $report = array('linked' => 0, 'errors' => array());
        $overview = self::overview_here($exam_id);
        $class_id = (int) $overview['notes_class_id'];
        if ($class_id <= 0) {
            $report['errors'][] = 'La fiche LogiNotes n’est liée à aucune classe : impossible de rapprocher les élèves.';
            return $report;
        }
        $allowed = array();
        foreach ($overview['rows'] as $row) {
            if (!$row['notes_student_id']) {
                $allowed[(int) $row['attempt']['user_id']] = $row['label'];
            }
        }
        $students = array();
        foreach (self::class_students($class_id) as $s) {
            $students[(int) $s->id] = $s;
        }
        $now = current_time('mysql');
        $used = array();
        foreach ($choices as $user_id => $choice) {
            $user_id = absint($user_id);
            if (!isset($allowed[$user_id]) || $choice === '' || $choice === '0') {
                continue;
            }
            if ($choice === 'new') {
                $user = get_userdata($user_id);
                if (!$user) {
                    continue;
                }
                // Nouvelle fiche élève dans la classe LogiNotes (ajout seulement : rien n'est modifié ni supprimé).
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
                if ($student_id <= 0) {
                    $report['errors'][] = $allowed[$user_id] . ' : la fiche LogiNotes n’a pas pu être créée.';
                    continue;
                }
                LogiPilot_Log::add('student_created_from_exam', 'notes_student', $student_id, array('user' => $user_id, 'exam' => $exam_id));
            } else {
                $student_id = absint($choice);
                if (!isset($students[$student_id]) || isset($used[$student_id])) {
                    continue;
                }
                $other = LogiPilot_Links::wp_user_for_notes_student($student_id);
                if ($other && $other !== $user_id) {
                    $report['errors'][] = $allowed[$user_id] . ' : cette fiche LogiNotes est déjà reliée à un autre compte.';
                    continue;
                }
                if ($other === $user_id) {
                    continue;
                }
            }
            $used[$student_id] = true;
            $wpdb->insert(LogiPilot_Install::table('student_links'), array('notes_student_id' => $student_id, 'wp_user_id' => $user_id, 'validated_by' => get_current_user_id(), 'validated_at' => $now));
            LogiPilot_Log::add('student_linked', 'notes_student', $student_id, array('from' => 0, 'to' => $user_id, 'via' => 'publish_panel'));
            $report['linked']++;
        }

        return $report;
    }

    private static function last_done(int $attempt_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE attempt_id = %d AND state = 'done' ORDER BY revision DESC LIMIT 1", $attempt_id)) ?: null;
    }

    private static function components(array $evaluation, array $attempt, ?array $result, string $flag, int $exam_evaluation_id): array
    {
        $total_points = (float) $evaluation['total_points'];
        $decision = LogiPilot_Snapshots::validated_decision('exam', $exam_evaluation_id, (int) $attempt['user_id']);
        if ($flag === 'cheating') {
            return array(
                'raw_points' => null, 'progress_adjustment' => 0.0, 'other_adjustment' => 0.0,
                'total_adjusted' => null, 'total_retained' => null, 'total_points' => $total_points,
                'grade' => 1.0, 'result_flag' => 'cheating', 'snapshot_id' => 0,
            );
        }
        $raw = round((float) $result['points_obtained'], 2);
        $net = round((float) $result['bonus_points'] - (float) $result['malus_points'], 2);
        $progress = $decision && $decision->adjustment !== null ? (float) $decision->adjustment : 0.0;
        $adjusted = max(0, round($raw + $net, 2));

        return array(
            'raw_points' => $raw,
            'progress_adjustment' => $progress,
            'other_adjustment' => round($net - $progress, 2),
            'total_adjusted' => $adjusted,
            'total_retained' => min($adjusted, $total_points),
            'total_points' => $total_points,
            'grade' => $result['final_grade'] !== null ? (float) $result['final_grade'] : null,
            'result_flag' => 'graded',
            'snapshot_id' => $decision ? (int) $decision->snapshot_id : 0,
        );
    }

    private static function same_components(object $last, array $c): bool
    {
        $num = static fn($a, $b) => ($a === null && $b === null) || ($a !== null && $b !== null && abs((float) $a - (float) $b) < 0.0001);

        return $num($last->raw_points, $c['raw_points'])
            && $num($last->progress_adjustment, $c['progress_adjustment'])
            && $num($last->other_adjustment, $c['other_adjustment'])
            && $num($last->grade, $c['grade'])
            && $last->result_flag === $c['result_flag'];
    }

    /* -------------------------------------------------------------------
     * Publication
     * ----------------------------------------------------------------- */

    public static function handle_publish(): void
    {
        $exam_id = absint($_POST['exam_evaluation_id'] ?? 0);
        check_admin_referer('logipilot_publish_' . $exam_id);
        LogiPilot_Spaces::push(LogiPilot_Spaces::exam_space($exam_id));
        $evaluation = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation($exam_id) : null;
        if (!$evaluation || !current_user_can('lp_exam_publish') || !LogiEval_Secure_Evaluations::can_manage_evaluation($evaluation)) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        $return = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return'] ?? '')), admin_url());
        $report = self::publish($exam_id);
        set_transient('logipilot_publish_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('lp_published', 1, $return) . '#logipilot-publish');
        exit;
    }

    /** Publie toutes les copies prêtes (publication partielle autorisée). */
    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function publish(int $exam_evaluation_id, bool $only_already_published = false): array
    {
        return LogiPilot_Spaces::run_for_exam($exam_evaluation_id, static fn() => self::publish_here($exam_evaluation_id, $only_already_published));
    }

    private static function publish_here(int $exam_evaluation_id, bool $only_already_published = false): array
    {
        global $wpdb;

        $report = array('done' => 0, 'unchanged' => 0, 'waiting' => 0, 'errors' => array());
        if (!LogiPilot_Plugin::service_enabled('exam_to_notes')) {
            $report['errors'][] = 'La liaison LogiExam → LogiNotes est désactivée par l’administrateur.';
            return $report;
        }
        $link = LogiPilot_Links::ensure_notes_fiche($exam_evaluation_id);
        if (!$link || (int) $link->notes_evaluation_id <= 0) {
            $report['errors'][] = 'La fiche LogiNotes n’existe pas encore : ' . ($link ? (string) $link->message : 'liaison absente');
            return $report;
        }

        LogiPilot_Snapshots::freeze_if_needed('exam', $exam_evaluation_id, 'Figé automatiquement à la première publication.');
        $overview = self::overview($exam_evaluation_id);
        $now = current_time('mysql');

        foreach ($overview['rows'] as $row) {
            if ($row['status'] === 'published') {
                $report['unchanged']++;
                continue;
            }
            if ($only_already_published && !$row['last']) {
                continue; // 1.43.0 : mise à jour des seules notes déjà envoyées à LogiNotes.
            }
            if ($row['status'] !== 'ready') {
                if ($row['status'] === 'unmatched') {
                    $report['errors'][] = $row['label'] . ' : ' . $row['message'] . '.';
                } else {
                    $report['waiting']++;
                }
                continue;
            }

            $c = self::components($overview['evaluation'], $row['attempt'], $row['result'], $row['flag'], $exam_evaluation_id);
            $revision = $row['last'] ? (int) $row['last']->revision + 1 : 1;
            $key = sha1('publish|' . (int) $row['attempt']['id'] . '|' . $revision . '|' . (int) $link->notes_evaluation_id . '|' . (int) $row['notes_student_id']);
            $existing_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE op_key = %s', $key));
            if (!$existing_id) {
                // La clé unique empêche tout doublon, même si deux publications partent en même temps.
                $wpdb->suppress_errors(true);
                $wpdb->insert(self::table(), $c + array(
                    'op_key' => $key,
                    'exam_evaluation_id' => $exam_evaluation_id,
                    'attempt_id' => (int) $row['attempt']['id'],
                    'wp_user_id' => (int) $row['attempt']['user_id'],
                    'notes_evaluation_id' => (int) $link->notes_evaluation_id,
                    'notes_student_id' => (int) $row['notes_student_id'],
                    'revision' => $revision,
                    'validated_by' => get_current_user_id(),
                    'validated_at' => $now,
                    'state' => 'pending',
                    'message' => '',
                    'attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ));
                $wpdb->suppress_errors(false);
            }
            $id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE op_key = %s', $key));
            $state = self::process($id);
            if ($state === 'done') {
                $report['done']++;
            } else {
                $pub = self::get($id);
                $report['errors'][] = $row['label'] . ' : ' . self::state_label($state) . ($pub && $pub->message ? ' — ' . $pub->message : '');
            }
        }

        LogiPilot_Log::add('results_published', 'exam_evaluation', $exam_evaluation_id, $report);

        return $report;
    }

    private static function exam_of_attempt(int $attempt_id): int
    {
        global $wpdb;

        if (!class_exists('LogiEval_Secure_Database')) {
            return 0;
        }
        $t = LogiEval_Secure_Database::tables();

        return (int) $wpdb->get_var($wpdb->prepare("SELECT evaluation_id FROM {$t['attempts']} WHERE id = %d", $attempt_id));
    }

    public static function get(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id)) ?: null;
    }

    /** Traite une demande. Un seul processus peut la réserver (doubles clics, traitements concurrents). */
    public static function process(int $id, bool $force = false): string
    {
        global $wpdb;

        $table = self::table();
        $now = current_time('mysql');
        $reserved = $wpdb->query($wpdb->prepare("UPDATE {$table} SET state = 'processing', attempts = attempts + 1, updated_at = %s WHERE id = %d AND state IN ('pending', 'error')", $now, $id));
        $pub = self::get($id);
        if (!$pub) {
            return 'error';
        }
        if ((int) $reserved !== 1) {
            return (string) $pub->state;
        }

        $finish = static function (string $state, string $message = '') use ($wpdb, $table, $id): string {
            $wpdb->update($table, array('state' => $state, 'message' => $message, 'updated_at' => current_time('mysql')), array('id' => $id));
            return $state;
        };

        // Une ancienne révision ne remplace jamais une plus récente (SYN-02, REC-18).
        $newer = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE attempt_id = %d AND revision > %d AND state = 'done'", $pub->attempt_id, $pub->revision));
        if ($newer > 0) {
            return $finish('superseded', 'Une révision plus récente est déjà publiée.');
        }

        $grades = LogiPilot_Spaces::notes_table('grades');
        if (!LogiPilot_Plugin::is_loaded('notes') || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $grades)) !== $grades) {
            return $finish('pending', 'LogiNotes indisponible : la demande sera reprise automatiquement.');
        }
        $notes_evaluation = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE id = %d", $pub->notes_evaluation_id));
        if (!$notes_evaluation) {
            return $finish('error', 'Fiche LogiNotes introuvable.');
        }

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$grades} WHERE evaluation_id = %d AND student_id = %d", $pub->notes_evaluation_id, $pub->notes_student_id));
        $previous = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE attempt_id = %d AND notes_evaluation_id = %d AND notes_student_id = %d AND state IN ('done', 'superseded') AND id <> %d ORDER BY revision DESC LIMIT 1", $pub->attempt_id, $pub->notes_evaluation_id, $pub->notes_student_id, $id));
        if (!$force && $existing && self::row_has_local_data($existing, $previous)) {
            return $finish('conflict', 'La note a été saisie ou modifiée directement dans LogiNotes : résolution manuelle nécessaire.');
        }

        $is_cheating = $pub->result_flag === 'cheating';
        $data = array(
            'points_obtained' => $is_cheating ? null : (float) $pub->raw_points,
            'points_adjustment' => $is_cheating ? 0 : round((float) $pub->progress_adjustment + (float) $pub->other_adjustment, 2),
            'note' => $pub->grade !== null ? (float) $pub->grade : null,
            'is_cheating' => $is_cheating ? 1 : 0,
            'is_makeup' => 0,
            'makeup_date' => null,
            'status' => 'graded',
            'status_note' => ($is_cheating ? 'Triche (copie arrêtée par LogiExam) : note fixée à 1. ' : '') . 'Publié depuis LogiExam, révision ' . (int) $pub->revision . '.',
            'updated_at' => $now,
        );
        if ($existing) {
            $ok = $wpdb->update($grades, $data, array('id' => (int) $existing->id));
        } else {
            $ok = $wpdb->insert($grades, $data + array('evaluation_id' => (int) $pub->notes_evaluation_id, 'student_id' => (int) $pub->notes_student_id, 'reminded_at' => null, 'created_at' => $now));
        }
        if ($ok === false) {
            return $finish('error', 'Écriture LogiNotes impossible : ' . $wpdb->last_error);
        }

        if ((float) $notes_evaluation->total_points !== (float) $pub->total_points && (float) $pub->total_points > 0) {
            $wpdb->update(LogiPilot_Spaces::notes_table('evaluations'), array('total_points' => (float) $pub->total_points, 'updated_at' => $now), array('id' => (int) $notes_evaluation->id));
        }
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET state = 'superseded', updated_at = %s WHERE attempt_id = %d AND state = 'done' AND id <> %d", $now, $pub->attempt_id, $id));
        LogiPilot_Links::set_link_state((int) $pub->exam_evaluation_id, 'published', 'Dernière publication le ' . wp_date('d.m.Y H:i') . '.');
        LogiPilot_Log::add('result_published', 'attempt', (int) $pub->attempt_id, array('revision' => (int) $pub->revision, 'notes_evaluation' => (int) $pub->notes_evaluation_id, 'grade' => $pub->grade));

        return $finish('done');
    }

    /** Vrai si la ligne LogiNotes contient une saisie locale différente de la dernière publication. */
    private static function row_has_local_data(object $row, ?object $previous): bool
    {
        $num = static fn($a, $b) => ($a === null && $b === null) || ($a !== null && $b !== null && abs((float) $a - (float) $b) < 0.0001);
        if (!$previous) {
            return $row->points_obtained !== null || $row->note !== null || (int) $row->is_cheating === 1;
        }
        $expected_points = $previous->result_flag === 'cheating' ? null : $previous->raw_points;

        return !$num($row->points_obtained, $expected_points) || !$num($row->note, $previous->grade);
    }

    public static function retry_pending(): void
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::table() . " WHERE state IN ('pending', 'error') AND attempts < %d ORDER BY id ASC LIMIT 200", self::MAX_ATTEMPTS));
        foreach ($ids as $id) {
            self::process((int) $id);
        }
        foreach ($wpdb->get_col('SELECT exam_evaluation_id FROM ' . LogiPilot_Install::table('exam_links') . " WHERE notes_evaluation_id = 0 AND state IN ('pending_data', 'error') LIMIT 100") as $exam_id) {
            LogiPilot_Links::ensure_notes_fiche((int) $exam_id);
        }
    }

    public static function handle_retry(): void
    {
        if (!current_user_can('lp_integration_sync')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        $id = absint($_POST['publication_id'] ?? 0);
        check_admin_referer('logipilot_retry_' . $id);
        global $wpdb;
        // Relance manuelle : même opération (même clé), compteur remis à zéro.
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table() . " SET attempts = 0, state = 'pending' WHERE id = %d AND state IN ('error', 'pending')", $id));
        $state = self::process($id);
        LogiPilot_Log::add('publication_retried', 'publication', $id, array('state' => $state));
        wp_safe_redirect(admin_url('admin.php?page=logione-sync&retried=' . $id));
        exit;
    }

    /** Résolution d'un conflit par une personne autorisée : version LogiExam ou note LogiNotes conservée. */
    public static function handle_resolve(): void
    {
        $id = absint($_POST['publication_id'] ?? 0);
        check_admin_referer('logipilot_resolve_' . $id);
        if (!current_user_can('lp_integration_sync') || !current_user_can('lp_notes_edit')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        global $wpdb;
        $choice = sanitize_key(wp_unslash($_POST['choice'] ?? ''));
        $pub = self::get($id);
        if ($pub && $pub->state === 'conflict') {
            if ($choice === 'exam') {
                $wpdb->update(self::table(), array('state' => 'pending', 'message' => 'Conflit résolu : version LogiExam appliquée.'), array('id' => $id));
                $state = self::process($id, true);
            } else {
                $wpdb->update(self::table(), array('state' => 'superseded', 'message' => 'Conflit résolu : note saisie dans LogiNotes conservée.', 'updated_at' => current_time('mysql')), array('id' => $id));
                $state = 'superseded';
            }
            LogiPilot_Log::add('conflict_resolved', 'publication', $id, array('choice' => $choice, 'state' => $state));
        }
        wp_safe_redirect(admin_url('admin.php?page=logione-sync&resolved=' . $id));
        exit;
    }

    /** Libellé imprimé sur le PDF de l'élève : la révision publiée et sa date (EXP-01). */
    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function publication_label(string $label, int $attempt_id): string
    {
        return LogiPilot_Spaces::run_for_exam(self::exam_of_attempt($attempt_id), static fn() => self::publication_label_here($label, $attempt_id));
    }

    private static function publication_label_here(string $label, int $attempt_id): string
    {
        $last = self::last_done($attempt_id);
        if (!$last) {
            return 'Non publiée';
        }
        $result = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_result_by_attempt($attempt_id) : null;
        $text = 'Révision ' . (int) $last->revision . ' du ' . mysql2date('d.m.Y H:i', (string) $last->validated_at) . ', note ' . (string) $last->grade;
        if ($result && $result['status'] !== 'graded' && $last->result_flag !== 'cheating') {
            $text .= ' (correction rouverte : version provisoire)';
        }

        return $text;
    }

    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function on_reopened(int $exam_evaluation_id, int $attempt_id): void
    {
        LogiPilot_Spaces::run_for_exam($exam_evaluation_id, static fn() => self::on_reopened_here($exam_evaluation_id, $attempt_id));
    }

    private static function on_reopened_here(int $exam_evaluation_id, int $attempt_id): void
    {
        if (self::last_done($attempt_id)) {
            LogiPilot_Links::set_link_state($exam_evaluation_id, 'revision', 'Une correction publiée a été rouverte : la dernière publication reste valable jusqu’à la nouvelle publication.');
            LogiPilot_Log::add('published_correction_reopened', 'attempt', $attempt_id);
        }
    }

    /** Élèves dont la note vient de LogiExam : lecture seule dans LogiNotes (SYN-05). */
    public static function locked_students(array $locked, int $notes_evaluation_id): array
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT notes_student_id FROM ' . self::table() . " WHERE notes_evaluation_id = %d AND state = 'done'", $notes_evaluation_id));

        return array_values(array_unique(array_merge($locked, array_map('intval', $ids))));
    }

    /* -------------------------------------------------------------------
     * Interfaces
     * ----------------------------------------------------------------- */

    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function render_publish_box(array $evaluation, string $return_url): void
    {
        LogiPilot_Spaces::run_for_exam((int) $evaluation['id'], static fn() => self::render_publish_box_here($evaluation, $return_url));
    }

    private static function render_publish_box_here(array $evaluation, string $return_url): void
    {
        $exam_id = (int) $evaluation['id'];
        $overview = self::overview($exam_id);
        $counts = array('ready' => 0, 'published' => 0, 'pending' => 0, 'revision' => 0, 'unmatched' => 0);
        foreach ($overview['rows'] as $row) {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
        }
        echo '<section class="logipilot-panel" id="logipilot-publish"><h3>Publier les résultats dans LogiNotes</h3>';
        echo '<div id="logipilot-progress-switch"></div>';
        self::render_progress_switch($exam_id, $return_url);
        if (!empty($_GET['lp_published'])) {
            $report = get_transient('logipilot_publish_report_' . get_current_user_id());
            if (is_array($report)) {
                echo '<div class="notice notice-info inline"><p>' . esc_html((!empty($report['linked']) ? sprintf('%d élève(s) rapproché(s) de LogiNotes. ', $report['linked']) : '') . sprintf('%d résultat(s) publié(s), %d déjà à jour, %d copie(s) en attente de correction.', $report['done'], $report['unchanged'], $report['waiting'])) . '</p>';
                foreach ((array) $report['errors'] as $error) {
                    echo '<p class="logipilot-warning">' . esc_html((string) $error) . '</p>';
                }
                echo '</div>';
            }
        }
        printf('<p>%d prête(s) à publier · %d déjà publiée(s) · %d en attente de correction · %d en cours de révision · %d non rapprochée(s)</p>', $counts['ready'], $counts['published'], $counts['pending'], $counts['revision'], $counts['unmatched']);
        $can = current_user_can('lp_exam_publish') && LogiEval_Secure_Evaluations::can_manage_evaluation($evaluation);
        $need_match = $can && (int) $overview['notes_class_id'] > 0 && (bool) array_filter($overview['rows'], static fn($r) => !$r['notes_student_id']);
        $students = $need_match ? self::class_students((int) $overview['notes_class_id']) : array();
        $total = (float) ($overview['evaluation']['total_points'] ?? 0);
        if ($need_match) {
            echo '<div class="notice notice-warning inline"><p><strong>Rapprochement nécessaire.</strong> LogiNotes doit savoir quelle fiche élève correspond à chaque compte élève. Choisissez la fiche dans la colonne « Fiche LogiNotes » (une proposition est faite quand le nom ou l’e-mail correspond), ou « Créer sa fiche », puis cliquez sur « Rapprocher et publier » (seules les copies corrigées sont publiées). Ce choix est enregistré une fois pour toutes : les prochaines évaluations de cette classe seront publiées directement.</p></div>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="logipilot-match-form">';
            wp_nonce_field('logipilot_match_' . $exam_id);
            printf('<input type="hidden" name="action" value="logipilot_match_students"><input type="hidden" name="exam_evaluation_id" value="%d"><input type="hidden" name="return" value="%s"><input type="hidden" name="lp_then_publish" value="1">', $exam_id, esc_attr($return_url));
        }
        echo '<table class="widefat striped"><thead><tr><th>Élève</th><th>Points obtenus</th><th>Bonus / malus</th><th>Total retenu</th><th>Note</th><th>État</th>' . ($need_match ? '<th>Fiche LogiNotes</th>' : '') . '</tr></thead><tbody>';
        foreach ($overview['rows'] as $row) {
            $result = $row['result'];
            $retained = '—';
            if ($result && ($row['flag'] ?? '') !== 'cheating') {
                $adjusted = max(0, round((float) $result['points_obtained'] + (float) $result['bonus_points'] - (float) $result['malus_points'], 2));
                $retained = rtrim(rtrim(number_format($total > 0 ? min($adjusted, $total) : $adjusted, 2, '.', ''), '0'), '.') . ($total > 0 ? ' / ' . rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') : '');
            }
            $cell = '';
            if ($need_match) {
                if ($row['status'] === 'unmatched' || (!$row['notes_student_id'] && in_array($row['status'], array('pending', 'revision'), true))) {
                    $uid = (int) $row['attempt']['user_id'];
                    $suggest = self::suggest_student($uid, $students);
                    $cell = '<select name="lp_match[' . $uid . ']" aria-label="' . esc_attr('Fiche LogiNotes de ' . $row['label']) . '"><option value="">— Choisir —</option>';
                    foreach ($students as $st) {
                        $taken = LogiPilot_Links::wp_user_for_notes_student((int) $st->id);
                        if ($taken && $taken !== $uid) {
                            continue;
                        }
                        $cell .= '<option value="' . (int) $st->id . '"' . selected($suggest, (int) $st->id, false) . '>' . esc_html(trim($st->last_name . ' ' . $st->first_name)) . '</option>';
                    }
                    $cell .= '<option value="new"' . (!$suggest && !$students ? ' selected' : '') . '>+ Créer sa fiche dans LogiNotes</option></select>';
                    if ($suggest) {
                        $cell .= ' <small class="logipilot-muted">proposé (même nom)</small>';
                    }
                } else {
                    $cell = $row['notes_student_id'] ? '✓ reliée' : '';
                }
                $cell = '<td>' . $cell . '</td>';
            }
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>%s</tr>',
                esc_html($row['label']),
                esc_html($result ? (string) $result['points_obtained'] : '—'),
                esc_html($result ? ('+' . $result['bonus_points'] . ' / −' . $result['malus_points']) : '—'),
                esc_html($retained),
                esc_html(($row['flag'] ?? '') === 'cheating' ? '1' : ($result && $result['final_grade'] !== null ? (string) $result['final_grade'] : '—')),
                esc_html(($row['flag'] ?? '') === 'cheating' && $row['status'] === 'ready' ? 'Prête (copie arrêtée : note 1)' : (self::state_label($row['status']) . ($row['message'] ? ' — ' . $row['message'] : ''))),
                $cell // déjà échappé
            );
        }
        echo '</tbody></table>';
        echo '<p class="logipilot-muted">« Points obtenus » = points des questions ; le bonus / malus s’y ajoute ; « Total retenu » = ce qui est envoyé à LogiNotes (plafonné au maximum de l’évaluation).</p>';
        if ($need_match) {
            echo '<p><button class="button button-primary">Rapprocher et publier</button></p></form>';
        }
        if ($can) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('logipilot_publish_' . $exam_id);
            printf('<input type="hidden" name="action" value="logipilot_publish"><input type="hidden" name="exam_evaluation_id" value="%d"><input type="hidden" name="return" value="%s">', $exam_id, esc_attr($return_url));
            echo '<p><button class="button' . ($counts['unmatched'] ? '' : ' button-primary') . '"' . ($counts['ready'] ? '' : ' disabled') . '>Publier les résultats dans LogiNotes</button> <span class="logipilot-muted">Seules les copies finalisées et rapprochées sont publiées. Les copies en attente le seront à une prochaine publication.</span></p></form>';
        }
        echo '</section>';
    }

    /* -------------------------------------------------------------------
     * 1.43.0 : « Aucun bonus ni malus de progression pour cette évaluation »,
     * réglable à tout moment, même après la finalisation et la publication.
     * ----------------------------------------------------------------- */

    public static function render_progress_switch(int $exam_id, string $return_url): void
    {
        $evaluation = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation($exam_id) : null;
        if (!$evaluation) {
            return;
        }
        $off = LogiPilot_Snapshots::progress_off($exam_id);
        $can = current_user_can('lp_exam_publish') && LogiEval_Secure_Evaluations::can_manage_evaluation($evaluation);
        if (!empty($_GET['lp_progress_switch'])) {
            $msg = get_transient('logipilot_progress_switch_' . get_current_user_id());
            if (is_string($msg) && $msg !== '') {
                echo '<div class="notice notice-success inline"><p>' . esc_html($msg) . '</p></div>';
            }
        }
        echo '<div class="logipilot-progress-switch" style="margin:8px 0 12px;padding:10px 14px;border:1px solid ' . ($off ? '#f5c400' : '#cbd5e1') . ';border-radius:8px;background:' . ($off ? '#fff8e1' : '#f8fafc') . '">';
        echo '<p style="margin:0 0 6px"><strong>Bonus / malus de progression pour cette évaluation : ' . ($off ? 'NON appliqués' : 'appliqués') . '</strong></p>';
        if ($can) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return window.confirm(' . esc_attr(wp_json_encode($off
                ? 'Appliquer de nouveau les bonus / malus de progression validés à toutes les copies de cette évaluation ? Les notes déjà envoyées à LogiNotes sont mises à jour.'
                : 'Retirer les bonus / malus de progression de toutes les copies de cette évaluation, même celles déjà finalisées ? Les notes déjà envoyées à LogiNotes sont mises à jour. Les bonus / malus saisis à la main dans une copie sont conservés.')) . ');">';
            wp_nonce_field('logipilot_progress_switch_' . $exam_id);
            printf('<input type="hidden" name="action" value="logipilot_progress_switch"><input type="hidden" name="exam_evaluation_id" value="%d"><input type="hidden" name="return" value="%s"><input type="hidden" name="off" value="%d">', $exam_id, esc_attr($return_url), $off ? 0 : 1);
            echo '<button class="button' . ($off ? '' : ' button-primary') . '">' . esc_html($off ? 'Appliquer de nouveau les bonus / malus' : 'Ne pas appliquer de bonus / malus à cette évaluation') . '</button> ';
            echo '<small class="logipilot-muted">' . esc_html('Modifiable à tout moment, même après la publication : les copies sont recalculées et les notes déjà dans LogiNotes sont corrigées.') . '</small>';
            echo '</form>';
        }
        echo '</div>';
    }

    public static function handle_progress_switch(): void
    {
        global $wpdb;

        $exam_id = absint($_POST['exam_evaluation_id'] ?? 0);
        check_admin_referer('logipilot_progress_switch_' . $exam_id);
        LogiPilot_Spaces::push(LogiPilot_Spaces::exam_space($exam_id));
        $evaluation = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation($exam_id) : null;
        if (!$evaluation || !current_user_can('lp_exam_publish') || !LogiEval_Secure_Evaluations::can_manage_evaluation($evaluation)) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        $return = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return'] ?? '')), admin_url());
        $turn_off = !empty($_POST['off']);

        if ($turn_off === LogiPilot_Snapshots::progress_off($exam_id)) {
            wp_safe_redirect($return); // déjà dans cet état (double clic)
            exit;
        }
        // Décisions validées lues AVANT de couper (pour retirer) ou APRÈS avoir rallumé (pour rajouter).
        if (!$turn_off) {
            LogiPilot_Snapshots::set_progress_off($exam_id, false);
        }
        $t = LogiEval_Secure_Database::tables();
        $changed = 0;
        foreach (LogiEval_Secure_Evaluations::get_attempts($exam_id) as $attempt) {
            if ((string) $attempt['status'] === 'security_terminated') {
                continue;
            }
            $result = LogiEval_Secure_Evaluations::get_result_by_attempt((int) $attempt['id']);
            if (!$result) {
                continue;
            }
            $decision = LogiPilot_Snapshots::validated_decision('exam', $exam_id, (int) $attempt['user_id']);
            if (!$decision || $decision->adjustment === null || abs((float) $decision->adjustment) < 0.00001) {
                continue;
            }
            $d = (float) $decision->adjustment;
            $current = round((float) $result['bonus_points'] - (float) $result['malus_points'], 2);
            // En retirant : seulement si la copie contient bien cet ajustement (pas déjà retiré à la main).
            if ($turn_off && !(($d > 0 && $current >= $d - 0.00001) || ($d < 0 && $current <= $d + 0.00001))) {
                continue;
            }
            $net = round($current + ($turn_off ? -$d : $d), 2);
            [$bonus, $malus] = LogiPilot_Snapshots::bonus_malus($net);
            $total = LogiEval_Secure_Grading::final_total((float) $result['points_obtained'], $bonus, $malus);
            $wpdb->update($t['results'], array(
                'bonus_points' => $bonus,
                'malus_points' => $malus,
                'final_total' => $total,
                'final_grade' => LogiEval_Secure_Grading::grade($total, $evaluation['total_points'] ?? 0),
                'updated_at' => current_time('mysql'),
            ), array('attempt_id' => (int) $attempt['id']));
            $changed++;
        }
        if ($turn_off) {
            LogiPilot_Snapshots::set_progress_off($exam_id, true);
        }
        LogiPilot_Log::add($turn_off ? 'progress_disabled' : 'progress_enabled', 'exam_evaluation', $exam_id, array('copies' => $changed));

        $report = self::publish($exam_id, true);
        $msg = ($turn_off ? 'Bonus / malus de progression retirés' : 'Bonus / malus de progression appliqués de nouveau') . sprintf(' : %d copie(s) recalculée(s), %d note(s) mise(s) à jour dans LogiNotes.', $changed, (int) $report['done']);
        if (!empty($report['errors'])) {
            $msg .= ' Attention : ' . implode(' ', array_map('strval', (array) $report['errors']));
        }
        set_transient('logipilot_progress_switch_' . get_current_user_id(), $msg, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('lp_progress_switch', 1, $return) . '#logipilot-progress-switch');
        exit;
    }

    /** Dans LogiNotes : origine des résultats publiés, révision et état de révision. */
    public static function render_notes_origin_panel($evaluation): void
    {
        global $wpdb;

        if (!is_object($evaluation)) {
            return;
        }
        $links = LogiPilot_Links::exam_links_for_notes((int) $evaluation->id);
        if (!$links) {
            return;
        }
        echo '<section class="gne-panel logipilot-panel"><h3>Résultats numériques (LogiExam)</h3>';
        foreach ($links as $link) {
            $exam = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation((int) $link->exam_evaluation_id) : null;
            echo '<p>' . esc_html(($link->link_role === 'makeup' ? 'Rattrapage : ' : 'Évaluation : ') . ($exam['title'] ?? ('#' . $link->exam_evaluation_id)) . ' · ' . self::state_label((string) $link->state)) . '</p>';
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT p.*, s.last_name, s.first_name FROM ' . self::table() . " p LEFT JOIN " . LogiPilot_Spaces::notes_table('students') . " s ON s.id = p.notes_student_id WHERE p.notes_evaluation_id = %d AND p.state IN ('done', 'conflict', 'error', 'pending') ORDER BY s.last_name, s.first_name, p.revision DESC",
            $evaluation->id
        ));
        if ($rows) {
            echo '<table class="gne-table"><thead><tr><th>Élève</th><th>Points bruts</th><th>Progression</th><th>Autres ajustements</th><th>Total retenu</th><th>Note</th><th>Révision</th><th>État</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $result = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_result_by_attempt((int) $row->attempt_id) : null;
                $state = self::state_label((string) $row->state);
                if ($row->state === 'done' && $result && $result['status'] !== 'graded' && $row->result_flag !== 'cheating') {
                    $state = 'En cours de révision dans LogiExam (publication affichée conservée)';
                }
                printf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                    esc_html(trim($row->last_name . ' ' . $row->first_name)),
                    esc_html($row->raw_points === null ? '—' : (string) $row->raw_points),
                    esc_html((string) $row->progress_adjustment),
                    esc_html((string) $row->other_adjustment),
                    esc_html($row->total_retained === null ? '—' : $row->total_retained . ' / ' . $row->total_points),
                    esc_html($row->grade === null ? '—' : (string) $row->grade),
                    (int) $row->revision,
                    esc_html($state . ($row->message ? ' — ' . $row->message : ''))
                );
            }
            echo '</tbody></table>';
        }
        echo '<p class="gne-muted">Les notes publiées depuis LogiExam sont en lecture seule ici : toute correction se fait dans LogiExam puis est republiée.</p>';
        foreach ($links as $link) {
            if (class_exists('LogiEval_Secure_Evaluations')) {
                $url = LogiEval_Secure_Evaluations::evaluation_url((int) $link->exam_evaluation_id, 'results');
                echo '<p><a class="button" href="' . esc_url($url) . '#logipilot-publish">Corriger dans LogiExam (bonus / malus, points)</a> <span class="gne-muted">' . esc_html((LogiPilot_Snapshots::progress_off((int) $link->exam_evaluation_id) ? 'Bonus / malus de progression : non appliqués.' : 'Bonus / malus de progression : appliqués.')) . '</span></p>';
            }
        }
        echo '</section>';
    }
}
