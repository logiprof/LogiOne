<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service de lecture de la progression (lot 2, PRO-01 à PRO-03, PRG-01 à PRG-04).
 *
 * Il utilise exclusivement la règle de sélection du module LogiProgress (aucune copie des
 * exclusions) et compare des nombres exacts de leçons, jamais le pourcentage arrondi.
 * Une erreur de source, un cours sans leçon ou un élève non rapproché donnent « À vérifier »,
 * jamais un malus automatique.
 */
final class LogiPilot_Progress_Service
{
    public const BONUS = 1.0;
    public const MALUS = -2.0;

    public const REASONS = array(
        'service_disabled' => 'Service de progression désactivé',
        'no_course' => 'Aucun cours Sensei associé à l’évaluation',
        'source_incompatible' => 'Source de progression Sensei non prise en charge',
        'no_lessons' => 'Le cours ne contient aucune leçon éligible',
        'no_account' => 'Élève non rapproché d’un compte WordPress',
        'ungraded' => 'Quiz rendu en attente de correction',
        'complete' => 'Toutes les leçons exigées sont terminées',
        'incomplete' => 'Des leçons exigées ne sont pas terminées',
        'late' => 'Des leçons exigées ont été terminées après la date limite',
    );

    public static function rule_version(): string
    {
        return class_exists('LogiProgress_Sensei_Group_Progress_Tracker')
            ? (string) LogiProgress_Sensei_Group_Progress_Tracker::RULE_VERSION
            : 'indisponible';
    }

    /**
     * @param array $people liste de array('key' => string, 'wp_user_id' => int, 'notes_student_id' => int, 'label' => string)
     */
    public static function read(int $course_id, int $group_id, array $people, string $deadline = ''): array
    {
        // Date limite pédagogique : seules les leçons terminées au plus tard ce jour-là (23:59:59) comptent.
        $deadline = preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) ? $deadline : '';
        $limit = $deadline !== '' ? $deadline . ' 23:59:59' : '';
        $result = array(
            'ok' => true,
            'error' => '',
            'rule_version' => self::rule_version(),
            'read_at' => current_time('mysql'),
            'deadline' => $deadline,
            'course_id' => $course_id,
            'course_title' => $course_id ? get_the_title($course_id) : '',
            'group_id' => $group_id,
            'group_title' => $group_id ? get_the_title($group_id) : '',
            'group_members' => null,
            'lessons' => array('eligible' => array(), 'excluded' => array(), 'unpublished' => array()),
            'people' => array(),
        );

        $global_error = '';
        if (!LogiPilot_Plugin::progress_service_available() || !class_exists('LogiProgress_Sensei_Group_Progress_Tracker')) {
            $global_error = 'service_disabled';
        } elseif ($course_id <= 0 || !get_post($course_id)) {
            $global_error = 'no_course';
        } elseif (!self::source_compatible()) {
            $global_error = 'source_incompatible';
        } else {
            $result['lessons'] = LogiProgress_Sensei_Group_Progress_Tracker::api_course_lessons($course_id);
            if (!$result['lessons']['eligible']) {
                $global_error = 'no_lessons';
            }
            if ($group_id > 0) {
                $result['group_members'] = LogiProgress_Sensei_Group_Progress_Tracker::api_group_students($group_id);
            }
        }

        if ($global_error) {
            $result['ok'] = false;
            $result['error'] = $global_error;
        }

        $eligible_ids = array_map('intval', array_keys($result['lessons']['eligible']));
        foreach ($people as $person) {
            $key = (string) $person['key'];
            $row = array(
                'key' => $key,
                'wp_user_id' => (int) ($person['wp_user_id'] ?? 0),
                'notes_student_id' => (int) ($person['notes_student_id'] ?? 0),
                'label' => (string) ($person['label'] ?? ''),
                'required' => count($eligible_ids),
                'completed' => 0,
                'ungraded' => 0,
                'late' => 0,
                'statuses' => array(),
                'proposal' => 'verify',
                'adjustment' => null,
                'reason' => $global_error,
                'in_group' => null,
            );

            if (!$global_error) {
                if ($row['wp_user_id'] <= 0) {
                    $row['reason'] = 'no_account';
                } else {
                    $details = LogiProgress_Sensei_Group_Progress_Tracker::api_statuses($row['wp_user_id'], $eligible_ids);
                    foreach ($eligible_ids as $lesson_id) {
                        $status = (string) ($details[$lesson_id]['status'] ?? '');
                        $row['statuses'][$lesson_id] = $status;
                        if (LogiProgress_Sensei_Group_Progress_Tracker::api_is_complete_status($status)) {
                            $done_at = (string) (($details[$lesson_id]['first_completed_at'] ?? '') ?: ($details[$lesson_id]['updated_at'] ?? ''));
                            if ($limit !== '' && $done_at !== '' && $done_at > $limit) {
                                $row['late']++;
                            } else {
                                $row['completed']++;
                            }
                        } elseif ($status === 'ungraded') {
                            $sent_at = (string) ($details[$lesson_id]['updated_at'] ?? '');
                            if ($limit !== '' && $sent_at !== '' && $sent_at > $limit) {
                                $row['late']++;
                            } else {
                                $row['ungraded']++;
                            }
                        }
                    }
                    $missing = $row['required'] - $row['completed'] - $row['ungraded'];
                    if ($row['completed'] === $row['required']) {
                        $row['proposal'] = 'bonus';
                        $row['adjustment'] = self::BONUS;
                        $row['reason'] = 'complete';
                    } elseif ($missing > 0) {
                        $row['proposal'] = 'malus';
                        $row['adjustment'] = self::MALUS;
                        $row['reason'] = $row['late'] > 0 ? 'late' : 'incomplete';
                    } else {
                        $row['proposal'] = 'verify';
                        $row['reason'] = 'ungraded';
                    }
                    if (is_array($result['group_members'])) {
                        $row['in_group'] = in_array($row['wp_user_id'], array_map('intval', $result['group_members']), true);
                    }
                }
            }

            $result['people'][$key] = $row;
        }

        return $result;
    }

    /**
     * Sensei peut stocker la progression dans ses propres tables (stockage « HPOS »).
     * Si aucune trace n'existe dans les commentaires mais que ces tables sont remplies,
     * la lecture serait fausse : on refuse plutôt que d'annoncer 0 %.
     */
    public static function source_compatible(): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sensei_lms_progress';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return true;
        }

        $comments = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'sensei_lesson_status' LIMIT 1");
        $custom = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE type = 'lesson' LIMIT 1");

        return !($comments === 0 && $custom > 0);
    }

    public static function reason_label(string $reason): string
    {
        return self::REASONS[$reason] ?? $reason;
    }

    public static function proposal_label(string $proposal, $adjustment = null): string
    {
        if ($proposal === 'bonus') {
            return 'Bonus +1';
        }
        if ($proposal === 'malus') {
            return 'Malus −2';
        }
        if ($proposal === 'exempt') {
            return 'Exemption (0)';
        }

        return 'À vérifier';
    }
}
