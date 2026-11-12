<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tables propres à LogiOne. Elles complètent les tables historiques sans jamais les modifier
 * (MIG-01 : aucun DROP, TRUNCATE ni renommage).
 *
 * Correspondances, fiches liées, relevés et publications sont rangés dans l'espace de
 * l'enseignant concerné (wp_logipilot_t{ID}_*), comme ses données LogiNotes. Seul le journal
 * est commun.
 */
final class LogiPilot_Install
{
    public const DB_VERSION = '1.3.2';
    public const OPTION = 'logipilot_db_version';

    public static function table(string $name): string
    {
        global $wpdb;

        $infix = (in_array($name, array('log', 'exam_options', 'ai_feedback'), true) || !class_exists('LogiPilot_Spaces')) ? '' : LogiPilot_Spaces::infix();

        return $wpdb->prefix . 'logipilot_' . $infix . $name;
    }

    public static function maybe_upgrade(): void
    {
        if (get_option(self::OPTION) !== self::DB_VERSION) {
            self::install();
        }
    }

    public static function install(): void
    {
        LogiPilot_Spaces::ensure_principal(get_current_user_id());
        LogiPilot_Spaces::run(LogiPilot_Spaces::principal(), array(__CLASS__, 'create_space_tables'));
        self::create_exam_options_table();
        self::create_ai_feedback_table();

        update_option(self::OPTION, self::DB_VERSION, false);
        LogiPilot_Permissions::remove_unknown_permissions();

        if (!wp_next_scheduled('logipilot_sync_retry')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'logipilot_sync_retry');
        }
    }

    /**
     * Options LogiExam propres à LogiOne (ordre aléatoire des questions, « Ma bibliothèque »).
     * Table à part : les tables historiques de LogiExam ne sont jamais modifiées (pas de colonne ajoutée).
     */
    public static function create_exam_options_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table('exam_options');
        dbDelta("CREATE TABLE {$table} (
    evaluation_id bigint(20) unsigned NOT NULL,
    owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
    shuffle_questions tinyint(1) NOT NULL DEFAULT 0,
    in_library tinyint(1) NOT NULL DEFAULT 0,
    library_at datetime DEFAULT NULL,
    progress_deadline date DEFAULT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (evaluation_id),
    KEY owner_library (owner_id, in_library)
) {$charset};");
    }

    /**
     * Apprentissage de la pré-correction IA (LogiOne 1.24.5) : pour chaque réponse pré-corrigée, la proposition de l'IA
     * et les points finalement attribués par l'enseignant. Table à part : les tables de LogiExam ne sont pas modifiées.
     */
    public static function create_ai_feedback_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table('ai_feedback');
        dbDelta("CREATE TABLE {$table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    evaluation_id bigint(20) unsigned NOT NULL DEFAULT 0,
    question_id bigint(20) unsigned NOT NULL DEFAULT 0,
    attempt_id bigint(20) unsigned NOT NULL DEFAULT 0,
    answer_text text NULL,
    ai_points decimal(8,2) NOT NULL DEFAULT 0,
    teacher_points decimal(8,2) NOT NULL DEFAULT 0,
    max_points decimal(8,2) NOT NULL DEFAULT 0,
    teacher_comment text NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY attempt_question (attempt_id, question_id),
    KEY teacher_question (teacher_id, question_id)
) {$charset};");
    }

    /** Tables de l'espace courant (dbDelta : création ou ajout de colonnes, jamais de suppression). */
    public static function create_space_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = static fn(string $name): string => self::table($name);

        dbDelta("CREATE TABLE {$t('class_links')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    notes_class_id bigint(20) unsigned NOT NULL,
    sensei_group_id bigint(20) unsigned NOT NULL,
    validated_by bigint(20) unsigned NOT NULL DEFAULT 0,
    validated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY class_group (notes_class_id, sensei_group_id),
    KEY sensei_group_id (sensei_group_id)
) {$charset};");

        dbDelta("CREATE TABLE {$t('student_links')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    notes_student_id bigint(20) unsigned NOT NULL,
    wp_user_id bigint(20) unsigned NOT NULL,
    validated_by bigint(20) unsigned NOT NULL DEFAULT 0,
    validated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY notes_student (notes_student_id),
    KEY wp_user_id (wp_user_id)
) {$charset};");

        dbDelta("CREATE TABLE {$t('notes_meta')} (
    notes_evaluation_id bigint(20) unsigned NOT NULL,
    origin varchar(20) NOT NULL DEFAULT 'paper',
    course_id bigint(20) unsigned NOT NULL DEFAULT 0,
    group_id bigint(20) unsigned NOT NULL DEFAULT 0,
    progress_deadline date DEFAULT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (notes_evaluation_id)
) {$charset};");

        dbDelta("CREATE TABLE {$t('exam_links')} (
    exam_evaluation_id bigint(20) unsigned NOT NULL,
    notes_evaluation_id bigint(20) unsigned NOT NULL DEFAULT 0,
    link_role varchar(20) NOT NULL DEFAULT 'main',
    state varchar(30) NOT NULL DEFAULT 'pending_data',
    message text,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (exam_evaluation_id),
    KEY notes_evaluation_id (notes_evaluation_id),
    KEY state (state)
) {$charset};");

        dbDelta("CREATE TABLE {$t('snapshots')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    context varchar(10) NOT NULL,
    context_id bigint(20) unsigned NOT NULL,
    revision int(10) unsigned NOT NULL DEFAULT 1,
    status varchar(20) NOT NULL DEFAULT 'validated',
    course_id bigint(20) unsigned NOT NULL DEFAULT 0,
    group_id bigint(20) unsigned NOT NULL DEFAULT 0,
    rule_version varchar(40) NOT NULL DEFAULT '',
    deadline date DEFAULT NULL,
    read_at datetime NOT NULL,
    validated_at datetime NOT NULL,
    validated_by bigint(20) unsigned NOT NULL DEFAULT 0,
    reason text,
    payload longtext,
    PRIMARY KEY  (id),
    UNIQUE KEY context_revision (context, context_id, revision),
    KEY context_status (context, context_id, status)
) {$charset};");

        dbDelta("CREATE TABLE {$t('decisions')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    snapshot_id bigint(20) unsigned NOT NULL,
    wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    notes_student_id bigint(20) unsigned NOT NULL DEFAULT 0,
    required_count int(10) unsigned NOT NULL DEFAULT 0,
    completed_count int(10) unsigned NOT NULL DEFAULT 0,
    proposal varchar(20) NOT NULL DEFAULT 'verify',
    adjustment decimal(5,2) DEFAULT NULL,
    reason text,
    exception_reason text,
    PRIMARY KEY  (id),
    KEY snapshot_id (snapshot_id),
    KEY wp_user_id (wp_user_id),
    KEY notes_student_id (notes_student_id)
) {$charset};");

        dbDelta("CREATE TABLE {$t('publications')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    op_key char(40) NOT NULL,
    exam_evaluation_id bigint(20) unsigned NOT NULL,
    attempt_id bigint(20) unsigned NOT NULL,
    wp_user_id bigint(20) unsigned NOT NULL,
    notes_evaluation_id bigint(20) unsigned NOT NULL,
    notes_student_id bigint(20) unsigned NOT NULL DEFAULT 0,
    revision int(10) unsigned NOT NULL DEFAULT 1,
    raw_points decimal(10,2) DEFAULT NULL,
    progress_adjustment decimal(10,2) NOT NULL DEFAULT 0,
    other_adjustment decimal(10,2) NOT NULL DEFAULT 0,
    total_adjusted decimal(10,2) DEFAULT NULL,
    total_retained decimal(10,2) DEFAULT NULL,
    total_points decimal(10,2) NOT NULL DEFAULT 0,
    grade decimal(4,2) DEFAULT NULL,
    result_flag varchar(20) NOT NULL DEFAULT 'graded',
    snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
    validated_by bigint(20) unsigned NOT NULL DEFAULT 0,
    validated_at datetime DEFAULT NULL,
    state varchar(20) NOT NULL DEFAULT 'pending',
    message text,
    attempts int(10) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY op_key (op_key),
    KEY attempt_state (attempt_id, state),
    KEY exam_evaluation_id (exam_evaluation_id),
    KEY notes_target (notes_evaluation_id, notes_student_id)
) {$charset};");

        dbDelta("CREATE TABLE {$t('log')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    action varchar(80) NOT NULL,
    object_type varchar(40) NOT NULL DEFAULT '',
    object_id bigint(20) unsigned NOT NULL DEFAULT 0,
    data longtext,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY action (action),
    KEY object (object_type, object_id)
) {$charset};");

    }
}
