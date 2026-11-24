<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Relevés de progression datés et figés (lot 2, PRG-05, PRG-06, EXG-12).
 *
 * États d'un relevé :
 * - frozen     : lecture figée automatiquement à l'enregistrement d'une note, propositions non appliquées ;
 * - validated  : décisions validées par l'enseignant et appliquées aux notes ;
 * - superseded : remplacé par une révision explicite, toujours consultable.
 *
 * Un relevé enregistré n'est jamais modifié par une évolution de Sensei, d'un titre, d'un groupe
 * ou d'une classe. Seule une révision explicite crée une nouvelle version.
 */
final class LogiPilot_Snapshots
{
    public static function init(): void
    {
        add_action('admin_post_logipilot_snapshot', array(__CLASS__, 'handle_action'));
        add_action('logipilot_notes_grades_saved', array(__CLASS__, 'on_notes_grades_saved'));
        add_action('logipilot_exam_attempt_finalized', array(__CLASS__, 'on_exam_attempt_finalized'), 10, 2);
    }

    /* -------------------------------------------------------------------
     * 1.43.0 : bonus / malus de progression désactivés pour une évaluation LogiExam
     * (réglage enregistré dans une option WordPress : aucune table modifiée).
     * ----------------------------------------------------------------- */

    public const OPTION_PROGRESS_OFF = 'logipilot_exam_progress_off';

    public static function progress_off(int $exam_evaluation_id): bool
    {
        if (method_exists('LogiEval_Secure_Evaluations', 'is_formative') && LogiEval_Secure_Evaluations::is_formative($exam_evaluation_id)) {
            return true; // 1.52.0 : test formatif, jamais de bonus / malus de progression
        }
        $off = get_option(self::OPTION_PROGRESS_OFF, array());

        return is_array($off) && isset($off[$exam_evaluation_id]);
    }

    public static function set_progress_off(int $exam_evaluation_id, bool $off): void
    {
        $all = get_option(self::OPTION_PROGRESS_OFF, array());
        $all = is_array($all) ? $all : array();
        if ($off) {
            $all[$exam_evaluation_id] = array('by' => get_current_user_id(), 'at' => current_time('mysql'));
        } else {
            unset($all[$exam_evaluation_id]);
        }
        update_option(self::OPTION_PROGRESS_OFF, $all, false);
    }

    /* -------------------------------------------------------------------
     * Lecture
     * ----------------------------------------------------------------- */

    public static function latest(string $context, int $context_id, array $statuses = array('frozen', 'validated')): ?object
    {
        global $wpdb;

        $table = LogiPilot_Install::table('snapshots');
        $in = implode(',', array_map(static fn($s) => "'" . esc_sql($s) . "'", $statuses));

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE context = %s AND context_id = %d AND status IN ({$in}) ORDER BY revision DESC LIMIT 1", $context, $context_id)) ?: null;
    }

    public static function history(string $context, int $context_id): array
    {
        global $wpdb;

        $table = LogiPilot_Install::table('snapshots');

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE context = %s AND context_id = %d ORDER BY revision DESC", $context, $context_id));
    }

    public static function get(int $snapshot_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . LogiPilot_Install::table('snapshots') . ' WHERE id = %d', $snapshot_id)) ?: null;
    }

    public static function decisions(int $snapshot_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . LogiPilot_Install::table('decisions') . ' WHERE snapshot_id = %d ORDER BY id ASC', $snapshot_id));
    }

    /** Décision validée pour un élève (compte WordPress ou fiche LogiNotes). */
    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function validated_decision(string $context, int $context_id, int $wp_user_id = 0, int $notes_student_id = 0): ?object
    {
        if ($context === 'exam' && self::progress_off($context_id)) {
            return null; // 1.43.0 : l'enseignant a choisi « aucun bonus ni malus » pour cette évaluation.
        }
        if ($context === 'exam') {
            return LogiPilot_Spaces::run_for_exam($context_id, static fn() => self::validated_decision_here($context, $context_id, $wp_user_id, $notes_student_id));
        }
        return self::validated_decision_here($context, $context_id, $wp_user_id, $notes_student_id);
    }

    private static function validated_decision_here(string $context, int $context_id, int $wp_user_id = 0, int $notes_student_id = 0): ?object
    {
        global $wpdb;

        $snapshot = self::latest($context, $context_id, array('validated'));
        if (!$snapshot) {
            return null;
        }

        $table = LogiPilot_Install::table('decisions');
        if ($notes_student_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE snapshot_id = %d AND notes_student_id = %d LIMIT 1", $snapshot->id, $notes_student_id));
            if ($row) {
                return $row;
            }
        }
        if ($wp_user_id > 0) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE snapshot_id = %d AND wp_user_id = %d LIMIT 1", $snapshot->id, $wp_user_id)) ?: null;
        }

        return null;
    }

    /* -------------------------------------------------------------------
     * Contextes : évaluation LogiNotes (papier) ou LogiExam (numérique)
     * ----------------------------------------------------------------- */

    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function context_info(string $context, int $context_id): ?array
    {
        if ($context === 'exam') {
            return LogiPilot_Spaces::run_for_exam($context_id, static fn() => self::context_info_here($context, $context_id));
        }
        return self::context_info_here($context, $context_id);
    }

    private static function context_info_here(string $context, int $context_id): ?array
    {
        global $wpdb;

        if ($context === 'notes') {
            $evaluation = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE id = %d", $context_id));
            if (!$evaluation) {
                return null;
            }
            $meta = LogiPilot_Links::notes_meta($context_id);
            $people = array();
            foreach (LogiPilot_Links::notes_participants((int) $evaluation->class_id, $context_id) as $student) {
                $people[] = array(
                    'key' => 'n' . (int) $student->id,
                    'notes_student_id' => (int) $student->id,
                    'wp_user_id' => LogiPilot_Links::wp_user_for_notes_student((int) $student->id),
                    'label' => trim($student->last_name . ' ' . $student->first_name),
                );
            }

            return array(
                'context' => 'notes',
                'id' => $context_id,
                'title' => (string) $evaluation->module_name,
                'course_id' => (int) ($meta->course_id ?? 0),
                'group_id' => (int) ($meta->group_id ?? 0),
                'deadline' => (string) ($meta->progress_deadline ?? ''),
                'origin' => (string) ($meta->origin ?? 'paper'),
                'people' => $people,
                'can_validate' => current_user_can('lp_notes_adjust') && current_user_can('lp_progress_use') && LogiPilot_Permissions::notes_class_allowed((int) $evaluation->class_id),
            );
        }

        if ($context === 'exam' && class_exists('LogiEval_Secure_Evaluations')) {
            $evaluation = LogiEval_Secure_Evaluations::get_evaluation($context_id);
            if (!$evaluation) {
                return null;
            }
            $user_ids = LogiEval_Secure_Evaluations::resolve_student_ids($evaluation);
            foreach (LogiEval_Secure_Evaluations::get_attempts($context_id) as $attempt) {
                $user_ids[] = (int) $attempt['user_id'];
            }
            $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
            $link = LogiPilot_Links::exam_link($context_id);
            $notes_class_id = 0;
            if ($link && (int) $link->notes_evaluation_id > 0) {
                $notes_class_id = (int) $wpdb->get_var($wpdb->prepare("SELECT class_id FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE id = %d", $link->notes_evaluation_id));
            }
            $people = array();
            foreach ($user_ids as $user_id) {
                $user = get_userdata($user_id);
                $people[] = array(
                    'key' => 'u' . $user_id,
                    'wp_user_id' => $user_id,
                    'notes_student_id' => LogiPilot_Links::notes_student_for_user($user_id, $notes_class_id),
                    'label' => $user ? $user->display_name : ('Compte ' . $user_id),
                );
            }
            usort($people, static fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
            $groups = LogiEval_Secure_Evaluations::decode_ids($evaluation['sensei_group_ids'] ?? '[]');

            return array(
                'context' => 'exam',
                'id' => $context_id,
                'title' => (string) $evaluation['title'],
                'course_id' => (int) ($evaluation['sensei_course_id'] ?? 0),
                'group_id' => (int) ($groups[0] ?? 0),
                'deadline' => (string) (LogiEval_Secure_Evaluations::exam_options($context_id)['progress_deadline'] ?? ''),
                'origin' => 'exam',
                'people' => $people,
                'can_validate' => current_user_can('lp_exam_teach') && current_user_can('lp_progress_use') && LogiEval_Secure_Evaluations::can_manage_evaluation($evaluation),
            );
        }

        return null;
    }

    /* -------------------------------------------------------------------
     * Écriture : figer, valider, réviser
     * ----------------------------------------------------------------- */

    private static function store(array $info, array $reading, string $status, string $reason, string $deadline, array $overrides): int
    {
        global $wpdb;

        $snapshots = LogiPilot_Install::table('snapshots');
        $decisions = LogiPilot_Install::table('decisions');
        $now = current_time('mysql');

        $wpdb->query('START TRANSACTION');
        $revision = 1 + (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(revision) FROM {$snapshots} WHERE context = %s AND context_id = %d FOR UPDATE", $info['context'], $info['id']));
        $wpdb->query($wpdb->prepare("UPDATE {$snapshots} SET status = 'superseded' WHERE context = %s AND context_id = %d AND status IN ('frozen', 'validated')", $info['context'], $info['id']));
        $ok = $wpdb->insert($snapshots, array(
            'context' => $info['context'],
            'context_id' => $info['id'],
            'revision' => $revision,
            'status' => $status,
            'course_id' => (int) $reading['course_id'],
            'group_id' => (int) $reading['group_id'],
            'rule_version' => (string) $reading['rule_version'],
            'deadline' => $deadline !== '' ? $deadline : null,
            'read_at' => $reading['read_at'],
            'validated_at' => $now,
            'validated_by' => get_current_user_id(),
            'reason' => $reason,
            'payload' => wp_json_encode($reading),
        ));
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return 0;
        }
        $snapshot_id = (int) $wpdb->insert_id;

        foreach ($reading['people'] as $key => $person) {
            [$final, $exception] = self::final_decision($person, $overrides[$key] ?? array(), $status);
            $wpdb->insert($decisions, array(
                'snapshot_id' => $snapshot_id,
                'wp_user_id' => (int) $person['wp_user_id'],
                'notes_student_id' => (int) $person['notes_student_id'],
                'required_count' => (int) $person['required'],
                'completed_count' => (int) $person['completed'],
                'proposal' => $final['proposal'],
                'adjustment' => $final['adjustment'],
                'reason' => (string) $person['reason'],
                'exception_reason' => $exception,
            ));
        }
        $wpdb->query('COMMIT');

        LogiPilot_Log::add('snapshot_' . $status, $info['context'], (int) $info['id'], array('snapshot' => $snapshot_id, 'revision' => $revision, 'reason' => $reason));

        return $snapshot_id;
    }

    /** @return array{0: array{proposal:string, adjustment:?float}, 1: string} */
    private static function final_decision(array $person, array $override, string $status): array
    {
        $choice = (string) ($override['choice'] ?? '');
        $why = sanitize_text_field((string) ($override['reason'] ?? ''));
        $proposal = (string) $person['proposal'];
        $adjustment = $person['adjustment'];

        if ($status === 'frozen') {
            // Lecture figée : la proposition est conservée, rien n'est appliqué.
            return array(array('proposal' => $proposal, 'adjustment' => null), '');
        }

        if ($choice !== '' && $choice !== $proposal) {
            $map = array('bonus' => LogiPilot_Progress_Service::BONUS, 'malus' => LogiPilot_Progress_Service::MALUS, 'exempt' => 0.0);
            if (isset($map[$choice])) {
                return array(array('proposal' => $choice, 'adjustment' => $map[$choice]), $why !== '' ? $why : 'Décision de l’enseignant');
            }
        }

        return array(array('proposal' => $proposal, 'adjustment' => $proposal === 'verify' ? null : $adjustment), '');
    }

    /** Fige la lecture actuelle si aucun relevé n'existe encore (EXG-12). */
    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function freeze_if_needed(string $context, int $context_id, string $reason): int
    {
        if ($context === 'exam') {
            return LogiPilot_Spaces::run_for_exam($context_id, static fn() => self::freeze_if_needed_here($context, $context_id, $reason));
        }
        return self::freeze_if_needed_here($context, $context_id, $reason);
    }

    private static function freeze_if_needed_here(string $context, int $context_id, string $reason): int
    {
        if (self::latest($context, $context_id)) {
            return 0;
        }
        $info = self::context_info($context, $context_id);
        if (!$info || $info['course_id'] <= 0 || !LogiPilot_Plugin::service_enabled('progress_for_evaluations')) {
            return 0;
        }

        $reading = LogiPilot_Progress_Service::read($info['course_id'], $info['group_id'], $info['people'], (string) $info['deadline']);

        return self::store($info, $reading, 'frozen', $reason, (string) $info['deadline'], array());
    }

    public static function on_notes_grades_saved(int $evaluation_id): void
    {
        $meta = LogiPilot_Links::notes_meta($evaluation_id);
        if ($meta && $meta->origin === 'exam') {
            return; // Le relevé d'une évaluation numérique est géré dans LogiExam.
        }
        global $wpdb;
        $has_note = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . LogiPilot_Spaces::notes_table('grades') . " WHERE evaluation_id = %d AND (points_obtained IS NOT NULL OR note IS NOT NULL)", $evaluation_id));
        if ($has_note > 0) {
            self::freeze_if_needed('notes', $evaluation_id, 'Figé automatiquement à l’enregistrement de la première note.');
        }
    }

    public static function on_exam_attempt_finalized(int $evaluation_id, int $attempt_id): void
    {
        self::freeze_if_needed('exam', $evaluation_id, 'Figé automatiquement à la finalisation de la première copie.');
    }

    /* -------------------------------------------------------------------
     * Application des décisions validées
     * ----------------------------------------------------------------- */

    private static function apply(string $context, int $context_id, int $snapshot_id, ?int $previous_id): array
    {
        $warnings = array();
        $previous = array();
        if ($previous_id) {
            foreach (self::decisions($previous_id) as $row) {
                $previous[$row->notes_student_id . '|' . $row->wp_user_id] = $row->adjustment;
            }
        }

        foreach (self::decisions($snapshot_id) as $decision) {
            if ($decision->adjustment === null) {
                continue;
            }
            $adjustment = (float) $decision->adjustment;
            $before = $previous[$decision->notes_student_id . '|' . $decision->wp_user_id] ?? null;
            if ($context === 'notes' && (int) $decision->notes_student_id > 0) {
                $warning = self::apply_to_notes($context_id, (int) $decision->notes_student_id, $adjustment, $before);
            } elseif ($context === 'exam' && (int) $decision->wp_user_id > 0) {
                $warning = self::apply_to_exam($context_id, (int) $decision->wp_user_id, $adjustment);
            } else {
                $warning = '';
            }
            if ($warning !== '') {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    /** Ajustement papier : appliqué une seule fois au score brut, conservé même sans points (NOT-02). */
    private static function apply_to_notes(int $evaluation_id, int $student_id, float $adjustment, $previous): string
    {
        global $wpdb;

        $grades = LogiPilot_Spaces::notes_table('grades');
        $evaluation = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . LogiPilot_Spaces::notes_table('evaluations') . " WHERE id = %d", $evaluation_id));
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$grades} WHERE evaluation_id = %d AND student_id = %d", $evaluation_id, $student_id));
        $now = current_time('mysql');
        $name = (string) $wpdb->get_var($wpdb->prepare("SELECT CONCAT(last_name, ' ', first_name) FROM " . LogiPilot_Spaces::notes_table('students') . " WHERE id = %d", $student_id));

        if (!$row) {
            $wpdb->insert($grades, array(
                'evaluation_id' => $evaluation_id, 'student_id' => $student_id, 'points_obtained' => null,
                'points_adjustment' => $adjustment, 'note' => null, 'is_cheating' => 0, 'is_makeup' => 0,
                'makeup_date' => null, 'status' => 'missing', 'status_note' => '', 'reminded_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ));
            return '';
        }

        if ((int) $row->is_cheating === 1 || $row->status === 'not_applicable') {
            return $name . ' : statut « triche » ou « non concerné », ajustement non appliqué.';
        }

        $current = (float) $row->points_adjustment;
        if (abs($current) > 0.00001 && ($previous === null || abs($current - (float) $previous) > 0.00001)) {
            if (abs($current - $adjustment) > 0.00001) {
                return $name . ' : un autre ajustement (' . $current . ') est déjà saisi ; il n’a pas été remplacé.';
            }
        }

        $note = $row->note;
        if ($row->points_obtained !== null && (float) $evaluation->total_points > 0) {
            $note = LogiPilot_Links::calculate_note((float) $row->points_obtained, (float) $evaluation->total_points, $adjustment);
        }
        $wpdb->update($grades, array('points_adjustment' => $adjustment, 'note' => $note, 'updated_at' => $now), array('id' => (int) $row->id));

        return '';
    }

    /** Ajustement numérique : appliqué au résultat tant que la copie n'est pas finalisée. */
    private static function apply_to_exam(int $evaluation_id, int $user_id, float $adjustment): string
    {
        global $wpdb;

        if (self::progress_off($evaluation_id)) {
            return ''; // 1.43.0 : bonus / malus désactivés pour cette évaluation.
        }

        $t = LogiEval_Secure_Database::tables();
        $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['attempts']} WHERE evaluation_id = %d AND user_id = %d", $evaluation_id, $user_id), ARRAY_A);
        if (!$attempt) {
            return ''; // Sera appliqué à la remise de la copie.
        }
        $result = LogiEval_Secure_Evaluations::get_result_by_attempt((int) $attempt['id']);
        if (!$result) {
            return '';
        }
        $user = get_userdata($user_id);
        $label = $user ? $user->display_name : ('Compte ' . $user_id);
        if ($attempt['status'] === 'security_terminated') {
            return $label . ' : copie arrêtée pour sécurité, ajustement non appliqué.';
        }
        if ($result['status'] === 'graded') {
            return $label . ' : correction déjà finalisée. Rouvrez la correction pour appliquer le nouvel ajustement.';
        }

        [$bonus, $malus] = self::bonus_malus($adjustment);
        $evaluation = LogiEval_Secure_Evaluations::get_evaluation($evaluation_id);
        $total = LogiEval_Secure_Grading::final_total((float) $result['points_obtained'], $bonus, $malus);
        $wpdb->update($t['results'], array(
            'bonus_points' => $bonus,
            'malus_points' => $malus,
            'final_total' => $total,
            'final_grade' => LogiEval_Secure_Grading::grade($total, $evaluation['total_points'] ?? 0),
            'updated_at' => current_time('mysql'),
        ), array('attempt_id' => (int) $attempt['id']));

        return '';
    }

    /** @return array{0: float, 1: float} bonus et malus positifs au format LogiExam */
    public static function bonus_malus(float $adjustment): array
    {
        return $adjustment >= 0 ? array($adjustment, 0.0) : array(0.0, -$adjustment);
    }

    /* -------------------------------------------------------------------
     * Interface : panneau commun aux deux parcours
     * ----------------------------------------------------------------- */

    /** Exécuté dans l'espace de l'auteur de l'évaluation LogiExam. */
    public static function render_panel(string $context, int $context_id, string $return_url): void
    {
        if ($context === 'exam') {
            LogiPilot_Spaces::run_for_exam($context_id, static fn() => self::render_panel_here($context, $context_id, $return_url));
            return;
        }
        self::render_panel_here($context, $context_id, $return_url);
    }

    private static function render_panel_here(string $context, int $context_id, string $return_url): void
    {
        $info = self::context_info($context, $context_id);
        if (!$info) {
            return;
        }

        $latest = self::latest($context, $context_id);
        $history = self::history($context, $context_id);
        echo '<section class="logipilot-panel" id="logipilot-progress">';
        echo '<h3>Progression LogiProgress (bonus +1 / malus −2)</h3>';
        if ($context === 'exam' && class_exists('LogiPilot_Publisher')) {
            LogiPilot_Publisher::render_progress_switch($context_id, $return_url);
        }

        if ($context === 'notes' && $info['origin'] === 'exam') {
            echo '<p>Évaluation numérique : le relevé de progression est validé dans LogiExam et transmis avec les résultats publiés.</p></section>';
            return;
        }

        if ($info['course_id'] <= 0) {
            echo '<p class="logipilot-muted">Aucun cours Sensei n’est associé à cette évaluation : aucun bonus ni malus de progression n’est proposé.</p></section>';
            return;
        }

        if (!LogiPilot_Plugin::service_enabled('progress_for_evaluations')) {
            echo '<p class="logipilot-warning">Le service de progression est désactivé par l’administrateur. Les notes restent utilisables.</p></section>';
            return;
        }

        if (!empty($_GET['lp_snapshot_warnings'])) {
            $warnings = get_transient('logipilot_snapshot_warnings_' . get_current_user_id());
            if (is_array($warnings) && $warnings) {
                echo '<div class="notice notice-warning inline"><p>' . implode('<br>', array_map('esc_html', $warnings)) . '</p></div>';
            }
        }

        if ($latest) {
            $payload = json_decode((string) $latest->payload, true) ?: array();
            $decisions = self::decisions((int) $latest->id);
            $state = $latest->status === 'validated' ? 'Relevé validé et appliqué' : 'Relevé figé, propositions non encore validées';
            printf(
                '<p><strong>%s</strong> · révision %d · lecture du %s · %s · règle %s</p>',
                esc_html($state),
                (int) $latest->revision,
                esc_html(mysql2date('d.m.Y H:i', (string) $latest->read_at)),
                esc_html(trim(($payload['course_title'] ?? '') . ($payload['group_title'] ? ' · ' . $payload['group_title'] : ''))),
                esc_html((string) $latest->rule_version)
            );
            if ($latest->deadline) {
                echo '<p>Date limite pédagogique : ' . esc_html(mysql2date('d.m.Y', (string) $latest->deadline)) . '</p>';
            }
            if ($latest->reason) {
                echo '<p class="logipilot-muted">' . esc_html((string) $latest->reason) . '</p>';
            }
            self::render_lessons_summary($payload);
            self::render_decision_form($info, $payload, $decisions, $latest, $return_url);
        } else {
            $reading = LogiPilot_Progress_Service::read($info['course_id'], $info['group_id'], $info['people'], (string) $info['deadline']);
            echo '<p><strong>Lecture provisoire</strong> du ' . esc_html(mysql2date('d.m.Y H:i', $reading['read_at'])) . ' — rien n’est encore enregistré. Le relevé sera figé au plus tard à l’enregistrement de la première note.</p>';
            self::render_lessons_summary($reading);
            self::render_decision_form($info, $reading, array(), null, $return_url);
        }

        if (count($history) > 1) {
            echo '<details><summary>Historique des relevés (' . count($history) . ')</summary><ul>';
            foreach ($history as $snapshot) {
                printf('<li>Révision %d · %s · lecture du %s · %s</li>', (int) $snapshot->revision, esc_html(self::status_label((string) $snapshot->status)), esc_html(mysql2date('d.m.Y H:i', (string) $snapshot->read_at)), esc_html((string) $snapshot->reason));
            }
            echo '</ul></details>';
        }
        echo '</section>';
    }

    public static function status_label(string $status): string
    {
        return array('frozen' => 'Figé', 'validated' => 'Validé', 'superseded' => 'Remplacé')[$status] ?? $status;
    }

    private static function render_lessons_summary(array $reading): void
    {
        if (!empty($reading['error'])) {
            echo '<p class="logipilot-warning">' . esc_html(LogiPilot_Progress_Service::reason_label((string) $reading['error'])) . ' : aucun malus automatique.</p>';
        }
        $eligible = (array) ($reading['lessons']['eligible'] ?? array());
        $excluded = (array) ($reading['lessons']['excluded'] ?? array());
        echo '<details><summary>' . esc_html(count($eligible) . ' leçon(s) exigée(s), ' . count($excluded) . ' exclue(s)') . '</summary>';
        echo '<p><strong>Exigées :</strong> ' . esc_html(implode(' · ', $eligible) ?: '—') . '</p>';
        echo '<p><strong>Exclues :</strong> ' . esc_html(implode(' · ', $excluded) ?: '—') . '</p></details>';
    }

    private static function render_decision_form(array $info, array $reading, array $decisions, ?object $snapshot, string $return_url): void
    {
        $by_key = array();
        foreach ($decisions as $decision) {
            $by_key[$decision->notes_student_id > 0 && $info['context'] === 'notes' ? 'n' . $decision->notes_student_id : 'u' . $decision->wp_user_id] = $decision;
        }
        $validated = $snapshot && $snapshot->status === 'validated';
        $can = !empty($info['can_validate']);
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="logipilot_snapshot">
            <input type="hidden" name="context" value="<?php echo esc_attr($info['context']); ?>">
            <input type="hidden" name="context_id" value="<?php echo esc_attr((string) $info['id']); ?>">
            <input type="hidden" name="return" value="<?php echo esc_attr($return_url); ?>">
            <?php wp_nonce_field('logipilot_snapshot_' . $info['context'] . '_' . $info['id']); ?>
            <table class="widefat striped logipilot-progress-table">
                <thead><tr><th>Élève</th><th>Leçons terminées / exigées</th><th>Proposition</th><th>Décision retenue</th><th>Motif d’exception</th></tr></thead>
                <tbody>
                <?php foreach ((array) ($reading['people'] ?? array()) as $key => $person) :
                    $decision = $by_key[$key] ?? null;
                    $retained = $decision ? ($decision->adjustment === null ? ($snapshot && $snapshot->status === 'frozen' ? 'Non appliqué' : 'À vérifier') : LogiPilot_Progress_Service::proposal_label((string) $decision->proposal)) : '';
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $person['label']); ?></td>
                        <td><?php echo esc_html($person['wp_user_id'] ? ((int) $person['completed'] . ' / ' . (int) $person['required'] . ((int) ($person['ungraded'] ?? 0) ? ' (' . (int) $person['ungraded'] . ' en attente de correction)' : '') . ((int) ($person['late'] ?? 0) ? ' · ' . (int) $person['late'] . ' terminée(s) après la date limite' : '')) : '—'); ?></td>
                        <td><?php echo esc_html(LogiPilot_Progress_Service::proposal_label((string) $person['proposal'])); ?><br><small><?php echo esc_html(LogiPilot_Progress_Service::reason_label((string) $person['reason'])); ?></small></td>
                        <td>
                            <?php if ($validated) : ?>
                                <?php echo esc_html($retained); ?>
                            <?php elseif ($can) : ?>
                                <select name="override[<?php echo esc_attr($key); ?>][choice]">
                                    <option value="">Proposition</option>
                                    <option value="bonus">Bonus +1</option>
                                    <option value="malus">Malus −2</option>
                                    <option value="exempt">Exemption (0)</option>
                                </select>
                            <?php else : ?>
                                <?php echo esc_html($retained ?: '—'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($validated) : ?>
                                <?php echo esc_html((string) ($decision->exception_reason ?? '')); ?>
                            <?php elseif ($can) : ?>
                                <input type="text" name="override[<?php echo esc_attr($key); ?>][reason]" placeholder="Obligatoire si la décision change">
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($can) : ?>
                <p>
                    <label>Date limite pédagogique <input type="date" name="deadline" value="<?php echo esc_attr($snapshot && $snapshot->deadline ? (string) $snapshot->deadline : (string) $info['deadline']); ?>"></label>
                    <?php if (!$validated) : ?>
                        <button class="button" name="op" value="set_deadline">Appliquer la date et recalculer</button>
                    <?php endif; ?>
                    <br><small class="logipilot-muted">Seules les leçons terminées au plus tard ce jour-là comptent pour le bonus ; une leçon terminée après compte comme non faite. Sans date, c’est la progression au moment de la validation qui compte.</small>
                    <?php if ($snapshot) : ?>
                        <label>Motif de la révision <input type="text" name="reason" placeholder="Obligatoire pour une révision"></label>
                    <?php endif; ?>
                </p>
                <p>
                    <?php if ($snapshot && $snapshot->status === 'frozen') : ?>
                        <button class="button button-primary" name="op" value="validate_frozen">Valider les décisions de ce relevé figé</button>
                    <?php endif; ?>
                    <?php if (!$snapshot) : ?>
                        <button class="button button-primary" name="op" value="validate_live">Actualiser et valider la progression</button>
                    <?php else : ?>
                        <button class="button" name="op" value="revise">Réviser : relire Sensei et créer une nouvelle version</button>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </form>
        <?php
    }

    public static function handle_action(): void
    {
        $context = sanitize_key(wp_unslash($_POST['context'] ?? ''));
        $context_id = absint($_POST['context_id'] ?? 0);
        check_admin_referer('logipilot_snapshot_' . $context . '_' . $context_id);
        if ($context === 'exam') {
            LogiPilot_Spaces::push(LogiPilot_Spaces::exam_space($context_id));
        }
        $info = self::context_info($context, $context_id);
        if (!$info || empty($info['can_validate'])) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }

        $op = sanitize_key(wp_unslash($_POST['op'] ?? ''));
        $overrides = array();
        foreach ((array) wp_unslash($_POST['override'] ?? array()) as $key => $value) {
            $overrides[sanitize_key((string) $key)] = array(
                'choice' => sanitize_key((string) ($value['choice'] ?? '')),
                'reason' => sanitize_text_field((string) ($value['reason'] ?? '')),
            );
        }
        $deadline = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash($_POST['deadline'] ?? '')) ? (string) wp_unslash($_POST['deadline']) : '';
        $reason = sanitize_text_field(wp_unslash($_POST['reason'] ?? ''));
        $return = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return'] ?? '')), admin_url());

        foreach ($overrides as $override) {
            if ($override['choice'] !== '' && $override['reason'] === '') {
                self::redirect_error($return, 'Un motif est obligatoire pour chaque décision différente de la proposition.');
            }
        }

        $latest = self::latest($context, $context_id);
        $previous_validated = self::latest($context, $context_id, array('validated'));
        if ($op === 'revise' && $latest && $reason === '') {
            self::redirect_error($return, 'Indiquez le motif de la révision.');
        }

        if ($op === 'set_deadline') {
            // Enregistre la date limite et recalcule la lecture provisoire, sans rien valider.
            self::save_deadline($context, $context_id, $deadline);
            wp_safe_redirect($return . '#logipilot-progress');
            exit;
        }

        if ($op === 'validate_frozen' && $latest && $latest->status === 'frozen') {
            $reading = json_decode((string) $latest->payload, true) ?: array();
            if ($deadline !== (string) ($reading['deadline'] ?? '')) {
                // Date limite changée : relecture avec la nouvelle date (seules les leçons terminées avant comptent).
                $reading = LogiPilot_Progress_Service::read($info['course_id'], $info['group_id'], $info['people'], $deadline);
            }
            $snapshot_id = self::store($info, $reading, 'validated', 'Validation du relevé figé (révision ' . (int) $latest->revision . ').' . ($reason ? ' ' . $reason : ''), $deadline, $overrides);
        } elseif ($op === 'validate_live' || $op === 'revise') {
            $reading = LogiPilot_Progress_Service::read($info['course_id'], $info['group_id'], $info['people'], $deadline);
            $snapshot_id = self::store($info, $reading, 'validated', $op === 'revise' ? 'Révision : ' . $reason : 'Validation par l’enseignant.', $deadline, $overrides);
        } else {
            wp_safe_redirect($return);
            exit;
        }

        self::save_deadline($context, $context_id, $deadline);

        $warnings = $snapshot_id ? self::apply($context, $context_id, $snapshot_id, $previous_validated ? (int) $previous_validated->id : null) : array('Le relevé n’a pas pu être enregistré.');
        set_transient('logipilot_snapshot_warnings_' . get_current_user_id(), $warnings, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('lp_snapshot_warnings', $warnings ? 1 : 0, $return) . '#logipilot-progress');
        exit;
    }

    private static function save_deadline(string $context, int $context_id, string $deadline): void
    {
        if ($context === 'notes') {
            LogiPilot_Links::save_notes_meta($context_id, array('progress_deadline' => $deadline ?: null));
        } elseif ($context === 'exam' && class_exists('LogiEval_Secure_Evaluations')) {
            LogiEval_Secure_Evaluations::set_exam_options($context_id, array('progress_deadline' => $deadline ?: null));
        }
    }

    private static function redirect_error(string $return, string $message): void
    {
        set_transient('logipilot_snapshot_warnings_' . get_current_user_id(), array($message), 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('lp_snapshot_warnings', 1, $return) . '#logipilot-progress');
        exit;
    }
}
