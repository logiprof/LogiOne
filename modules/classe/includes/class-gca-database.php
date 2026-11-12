<?php

if (!defined('ABSPATH')) {
    exit;
}

class GCA_Database
{
    public static function table(string $name): string
    {
        global $wpdb;
        // Espace de l'enseignant : '' (données d'origine) ou « t{ID}_ ».
        return $wpdb->prefix . 'gca_' . (class_exists('LogiPilot_Spaces') ? LogiPilot_Spaces::infix() : '') . $name;
    }

    public static function activate(): void
    {
        self::create_tables();
        self::migrate_legacy_exclusions();
        self::backfill_verified_manual_fields();
        self::unprotect_import_counter_fields();
        self::add_capabilities();
        self::add_default_options();
        self::ensure_private_dirs();
        update_option(LogiPilot_Spaces::option('gca_db_version'), defined('GCA_VERSION') ? GCA_VERSION : '1', false);
    }

    public static function deactivate(): void
    {
        // Data is intentionally preserved on deactivation.
        wp_clear_scheduled_hook('gca_weekly_followup_reminder');
        wp_clear_scheduled_hook('gca_stale_task_reminder');
        wp_clear_scheduled_hook('gca_import_freshness_reminder');
    }

    public static function maybe_upgrade(): void
    {
        $target = defined('GCA_VERSION') ? GCA_VERSION : '1';

        if (get_option(LogiPilot_Spaces::option('gca_db_version')) === $target) {
            return;
        }

        self::create_tables();
        self::migrate_legacy_exclusions();
        self::backfill_verified_manual_fields();
        self::unprotect_import_counter_fields();
        self::add_capabilities();
        self::add_default_options();
        self::normalize_email_reminder_settings();
        self::ensure_private_dirs();
        update_option(LogiPilot_Spaces::option('gca_db_version'), $target, false);
        update_option(LogiPilot_Spaces::option('gca_recalculate_tasks_once'), 1, false);
    }

    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $teachers = self::table('teachers');
        $trainers = self::table('trainers');
        $classes = self::table('classes');
        $students = self::table('students');
        $grades = self::table('grades');
        $absence_justifications = self::table('absence_justifications');
        $disciplinary_exclusions = self::table('disciplinary_exclusions');
        $imports = self::table('imports');
        $tasks = self::table('tasks');
        $communications = self::table('communications');
        $events = self::table('events');
        $logs = self::table('logs');
        $snapshots = self::table('snapshots');

        $sql = "
CREATE TABLE {$teachers} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    user_login varchar(190) NOT NULL DEFAULT '',
    display_name varchar(190) NOT NULL DEFAULT '',
    user_email varchar(190) NOT NULL DEFAULT '',
    roles_json text,
    first_seen_at datetime NOT NULL,
    last_seen_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY wp_user_id (wp_user_id),
    KEY user_email (user_email)
) {$charset};

CREATE TABLE {$trainers} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    trainer_key char(40) NOT NULL,
    name varchar(190) NOT NULL DEFAULT '',
    company varchar(190) NOT NULL DEFAULT '',
    company_address varchar(255) NOT NULL DEFAULT '',
    company_zip varchar(20) NOT NULL DEFAULT '',
    company_city varchar(120) NOT NULL DEFAULT '',
    email varchar(190) NOT NULL DEFAULT '',
    phone varchar(80) NOT NULL DEFAULT '',
    salutation varchar(190) NOT NULL DEFAULT '',
    verified_at datetime DEFAULT NULL,
    verified_by bigint(20) unsigned NOT NULL DEFAULT 0,
    manual_fields_json longtext,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY trainer_key (trainer_key),
    KEY email (email),
    KEY name (name),
    KEY verified_at (verified_at)
) {$charset};

CREATE TABLE {$classes} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    school_year varchar(20) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY class_year (name, school_year),
    KEY status (status)
) {$charset};

CREATE TABLE {$students} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    class_id bigint(20) unsigned NOT NULL,
    last_name varchar(120) NOT NULL,
    first_name varchar(120) NOT NULL,
    date_of_birth date DEFAULT NULL,
    gender varchar(20) NOT NULL DEFAULT '',
    photo_path text,
    phone varchar(80) NOT NULL DEFAULT '',
    email varchar(190) NOT NULL DEFAULT '',
    trainer_id bigint(20) unsigned NOT NULL DEFAULT 0,
    trainer_name varchar(190) NOT NULL DEFAULT '',
    trainer_company varchar(190) NOT NULL DEFAULT '',
    trainer_company_address varchar(255) NOT NULL DEFAULT '',
    trainer_company_zip varchar(20) NOT NULL DEFAULT '',
    trainer_company_city varchar(120) NOT NULL DEFAULT '',
    trainer_email varchar(190) NOT NULL DEFAULT '',
    trainer_phone varchar(80) NOT NULL DEFAULT '',
    field_trainer_name varchar(190) NOT NULL DEFAULT '',
    field_trainer_email varchar(190) NOT NULL DEFAULT '',
    field_trainer_phone varchar(80) NOT NULL DEFAULT '',
    legal_rep1_name varchar(190) NOT NULL DEFAULT '',
    legal_rep1_email varchar(190) NOT NULL DEFAULT '',
    legal_rep1_phone varchar(80) NOT NULL DEFAULT '',
    legal_rep2_name varchar(190) NOT NULL DEFAULT '',
    legal_rep2_email varchar(190) NOT NULL DEFAULT '',
    legal_rep2_phone varchar(80) NOT NULL DEFAULT '',
    absence_count decimal(10,2) NOT NULL DEFAULT 0,
    absence_rate decimal(7,2) NOT NULL DEFAULT 0,
    absence_justified_count decimal(10,2) NOT NULL DEFAULT 0,
    lateness_count int(10) unsigned NOT NULL DEFAULT 0,
    removal_count int(10) unsigned NOT NULL DEFAULT 0,
    comment_count int(10) unsigned NOT NULL DEFAULT 0,
    contract_status varchar(30) NOT NULL DEFAULT 'active',
    contract_break_date date DEFAULT NULL,
    lateness_status varchar(60) NOT NULL DEFAULT 'none',
    warning_confirmed_at datetime DEFAULT NULL,
    warning_lateness_count int(10) unsigned NOT NULL DEFAULT 0,
    exclusion_start date DEFAULT NULL,
    exclusion_end date DEFAULT NULL,
    exclusion_lateness_count int(10) unsigned NOT NULL DEFAULT 0,
    disciplinary_warning_received tinyint(1) unsigned NOT NULL DEFAULT 0,
    disciplinary_warning_date date DEFAULT NULL,
    expulsion_received tinyint(1) unsigned NOT NULL DEFAULT 0,
    expulsion_date date DEFAULT NULL,
    wellbeing_council_convoked tinyint(1) unsigned NOT NULL DEFAULT 0,
    wellbeing_council_date date DEFAULT NULL,
    verified_at datetime DEFAULT NULL,
    verified_by bigint(20) unsigned NOT NULL DEFAULT 0,
    manual_fields_json longtext,
    admin_notes longtext,
    archived_at datetime DEFAULT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY class_student (class_id, last_name(80), first_name(80)),
    KEY class_id (class_id),
    KEY trainer_id (trainer_id),
    KEY field_trainer_email (field_trainer_email),
    KEY contract_status (contract_status),
    KEY contract_break_date (contract_break_date),
    KEY lateness_status (lateness_status),
    KEY disciplinary_warning_received (disciplinary_warning_received),
    KEY expulsion_received (expulsion_received),
    KEY wellbeing_council_convoked (wellbeing_council_convoked),
    KEY verified_at (verified_at),
    KEY archived_at (archived_at)
) {$charset};

CREATE TABLE {$grades} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    student_id bigint(20) unsigned NOT NULL,
    class_id bigint(20) unsigned NOT NULL,
    school_year varchar(20) NOT NULL DEFAULT '',
    subject varchar(190) NOT NULL DEFAULT '',
    assessment varchar(190) NOT NULL DEFAULT '',
    grade_value varchar(40) NOT NULL DEFAULT '',
    coefficient decimal(10,2) NOT NULL DEFAULT 1,
    grade_date date DEFAULT NULL,
    comment text,
    source_key char(40) NOT NULL,
    source_filename varchar(255) NOT NULL DEFAULT '',
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY student_grade_source (student_id, source_key),
    KEY student_id (student_id),
    KEY class_id (class_id),
    KEY school_year (school_year),
    KEY subject (subject),
    KEY grade_date (grade_date),
    KEY teacher_id (teacher_id)
) {$charset};

CREATE TABLE {$absence_justifications} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    student_id bigint(20) unsigned NOT NULL,
    absence_date date NOT NULL,
    period_count decimal(10,2) NOT NULL DEFAULT 0,
    source varchar(40) NOT NULL DEFAULT 'manual',
    task_id bigint(20) unsigned NOT NULL DEFAULT 0,
    notes text,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY student_id (student_id),
    KEY absence_date (absence_date),
    KEY task_id (task_id),
    KEY teacher_id (teacher_id)
) {$charset};

CREATE TABLE {$disciplinary_exclusions} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    student_id bigint(20) unsigned NOT NULL,
    start_date date DEFAULT NULL,
    end_date date DEFAULT NULL,
    notes text,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY student_id (student_id),
    KEY start_date (start_date),
    KEY end_date (end_date),
    KEY teacher_id (teacher_id)
) {$charset};

CREATE TABLE {$imports} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    import_type varchar(40) NOT NULL,
    import_mode varchar(20) NOT NULL DEFAULT 'replace',
    source_filename varchar(255) NOT NULL DEFAULT '',
    mapping_json longtext,
    row_count int(10) unsigned NOT NULL DEFAULT 0,
    processed_count int(10) unsigned NOT NULL DEFAULT 0,
    skipped_count int(10) unsigned NOT NULL DEFAULT 0,
    error_count int(10) unsigned NOT NULL DEFAULT 0,
    errors_json longtext,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    source_sha256 char(64) NOT NULL DEFAULT '',
    source_size bigint(20) unsigned NOT NULL DEFAULT 0,
    source_path text,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY import_type (import_type),
    KEY teacher_id (teacher_id),
    KEY created_at (created_at)
) {$charset};

CREATE TABLE {$tasks} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    student_id bigint(20) unsigned NOT NULL,
    task_key varchar(191) NOT NULL,
    task_type varchar(60) NOT NULL,
    threshold_value varchar(40) NOT NULL DEFAULT '',
    status varchar(30) NOT NULL DEFAULT 'pending',
    action_label varchar(190) NOT NULL DEFAULT '',
    message text,
    created_at datetime NOT NULL,
    resolved_at datetime DEFAULT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY student_task (student_id, task_key),
    KEY status (status),
    KEY task_type (task_type)
) {$charset};

CREATE TABLE {$communications} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    student_id bigint(20) unsigned NOT NULL,
    communication_type varchar(60) NOT NULL,
    subject varchar(255) NOT NULL DEFAULT '',
    body longtext,
    recipients_to text,
    recipients_cc text,
    recipients_bcc text,
    attachment_path text,
    sent_at datetime NOT NULL,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    status varchar(30) NOT NULL DEFAULT 'sent',
    PRIMARY KEY  (id),
    KEY student_id (student_id),
    KEY communication_type (communication_type),
    KEY teacher_id (teacher_id),
    KEY sent_at (sent_at)
) {$charset};

CREATE TABLE {$events} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    student_id bigint(20) unsigned NOT NULL,
    event_type varchar(60) NOT NULL,
    status varchar(60) NOT NULL DEFAULT '',
    payload_json longtext,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY student_id (student_id),
    KEY event_type (event_type),
    KEY teacher_id (teacher_id)
) {$charset};

CREATE TABLE {$logs} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    actor_name varchar(190) NOT NULL DEFAULT '',
    actor_email varchar(190) NOT NULL DEFAULT '',
    action varchar(80) NOT NULL,
    object_type varchar(60) NOT NULL DEFAULT '',
    object_id bigint(20) unsigned NOT NULL DEFAULT 0,
    data_json longtext,
    data_hash char(64) NOT NULL DEFAULT '',
    request_id varchar(64) NOT NULL DEFAULT '',
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY action (action),
    KEY teacher_id (teacher_id),
    KEY object_ref (object_type, object_id),
    KEY created_at (created_at)
) {$charset};

CREATE TABLE {$snapshots} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    action varchar(80) NOT NULL,
    object_type varchar(60) NOT NULL DEFAULT '',
    object_id bigint(20) unsigned NOT NULL DEFAULT 0,
    payload_json longtext,
    payload_hash char(64) NOT NULL DEFAULT '',
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY teacher_id (teacher_id),
    KEY action (action),
    KEY object_ref (object_type, object_id),
    KEY created_at (created_at)
) {$charset};
";

        dbDelta($sql);
    }

    private static function migrate_legacy_exclusions(): void
    {
        global $wpdb;

        $students = self::table('students');
        $disciplinary_exclusions = self::table('disciplinary_exclusions');
        $now = self::now();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$disciplinary_exclusions}
                    (student_id, start_date, end_date, notes, created_by, teacher_id, created_at, updated_at)
                 SELECT s.id, s.exclusion_start, s.exclusion_end, %s, 0, 0, %s, %s
                 FROM {$students} s
                 WHERE s.lateness_status = 'exclusion_confirmed'
                   AND NOT EXISTS (
                       SELECT 1 FROM {$disciplinary_exclusions} x WHERE x.student_id = s.id
                   )",
                'Exclusion importée depuis l’ancien suivi.',
                $now,
                $now
            )
        );
    }

    private static function backfill_verified_manual_fields(): void
    {
        global $wpdb;

        $trainers = self::table('trainers');
        $students = self::table('students');
        $trainer_fields = self::manual_fields_json(array(
            'name',
            'company',
            'company_address',
            'company_zip',
            'company_city',
            'email',
            'phone',
            'salutation',
        ));
        $student_fields = self::manual_fields_json(array(
            'class_id',
            'last_name',
            'first_name',
            'date_of_birth',
            'gender',
            'phone',
            'email',
            'trainer_id',
            'trainer_name',
            'trainer_company',
            'trainer_company_address',
            'trainer_company_zip',
            'trainer_company_city',
            'trainer_email',
            'trainer_phone',
            'legal_rep1_name',
            'legal_rep1_email',
            'legal_rep1_phone',
            'legal_rep2_name',
            'legal_rep2_email',
            'legal_rep2_phone',
            'contract_status',
            'contract_break_date',
            'disciplinary_warning_received',
            'disciplinary_warning_date',
            'expulsion_received',
            'expulsion_date',
            'wellbeing_council_convoked',
            'wellbeing_council_date',
            'admin_notes',
        ));

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$trainers}
                 SET manual_fields_json = %s
                 WHERE verified_at IS NOT NULL
                   AND verified_at <> '0000-00-00 00:00:00'
                   AND TRIM(COALESCE(manual_fields_json, '')) = ''",
                $trainer_fields
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$students}
                 SET manual_fields_json = %s
                 WHERE verified_at IS NOT NULL
                   AND verified_at <> '0000-00-00 00:00:00'
                   AND TRIM(COALESCE(manual_fields_json, '')) = ''",
                $student_fields
            )
        );
    }

    private static function unprotect_import_counter_fields(): void
    {
        global $wpdb;

        $students = self::table('students');
        $counter_fields = array('absence_count', 'absence_rate', 'lateness_count', 'removal_count', 'comment_count');
        $rows = $wpdb->get_results(
            "SELECT id, manual_fields_json
             FROM {$students}
             WHERE manual_fields_json IS NOT NULL
               AND TRIM(manual_fields_json) <> ''"
        );

        foreach ($rows as $row) {
            $current = self::manual_fields_from_json($row->manual_fields_json ?? '');
            $cleaned = array_values(array_diff($current, $counter_fields));

            if ($cleaned === $current) {
                continue;
            }

            $wpdb->update(
                $students,
                array('manual_fields_json' => self::manual_fields_json($cleaned)),
                array('id' => (int) $row->id),
                array('%s'),
                array('%d')
            );
        }
    }

    public static function add_capabilities(): void
    {
        // LogiOne : les droits viennent de la matrice centrale ; l'ancien droit gca_manage n'est plus utilisé.
        return;
    }

    public static function add_default_options(): void
    {
        if (!get_option(LogiPilot_Spaces::option('gca_settings'))) {
            add_option(LogiPilot_Spaces::option('gca_settings'), self::default_settings(), '', false);
        }

        if (!get_option(LogiPilot_Spaces::option('gca_email_templates'))) {
            add_option(LogiPilot_Spaces::option('gca_email_templates'), self::default_email_templates(), '', false);
        } else {
            self::upgrade_email_templates();
        }

        if (!get_option(LogiPilot_Spaces::option('gca_trainer_message_templates'))) {
            add_option(LogiPilot_Spaces::option('gca_trainer_message_templates'), self::default_trainer_message_templates(), '', false);
        }

        if (!get_option(LogiPilot_Spaces::option('gca_free_message_templates'))) {
            add_option(LogiPilot_Spaces::option('gca_free_message_templates'), self::default_free_message_templates(), '', false);
        }

        if (false === get_option(LogiPilot_Spaces::option('gca_trainer_salutations'), false)) {
            add_option(LogiPilot_Spaces::option('gca_trainer_salutations'), array(), '', false);
        }

        if (false === get_option(LogiPilot_Spaces::option('gca_import_mapping_profiles'), false)) {
            add_option(LogiPilot_Spaces::option('gca_import_mapping_profiles'), array(), '', false);
        }
    }

    private static function normalize_email_reminder_settings(): void
    {
        $settings = get_option(LogiPilot_Spaces::option('gca_settings'), array());
        $settings = wp_parse_args(is_array($settings) ? $settings : array(), self::default_settings());
        $sender_name = trim((string) ($settings['email_sender_name'] ?? ''));
        $normalized_sender_name = strtolower(remove_accents($sender_name));
        if ($sender_name === '' || $normalized_sender_name === 'logiprof') {
            $settings['email_sender_name'] = 'CPNV';
        }

        $settings['reminder_emails'] = sanitize_text_field((string) ($settings['reminder_emails'] ?? ''));
        $settings['weekly_reminder_enabled'] = 0;
        $settings['weekly_reminder_time'] = '08:00';
        $settings['stale_task_reminder_enabled'] = 1;
        $settings['stale_task_days'] = min(30, max(1, (int) ($settings['stale_task_days'] ?? 3)));
        $settings['stale_task_reminder_time'] = self::sanitize_time_setting((string) ($settings['stale_task_reminder_time'] ?? '08:15'), '08:15');
        $settings['import_reminder_enabled'] = 1;
        $settings['import_reminder_days'] = min(365, max(1, (int) ($settings['import_reminder_days'] ?? 8)));
        $settings['school_start_date'] = self::sanitize_date_setting((string) ($settings['school_start_date'] ?? '')) ?: self::default_school_start_date();
        $settings['absence_grace_days'] = min(120, max(0, (int) ($settings['absence_grace_days'] ?? 21)));
        $settings['absence_min_count_before_alert'] = max(0, (float) str_replace(',', '.', (string) ($settings['absence_min_count_before_alert'] ?? 2)));
        $settings['absence_unjustified_days_threshold'] = max(1, (int) ($settings['absence_unjustified_days_threshold'] ?? 21));
        $settings['absence_critical_rate_threshold'] = min(100, max(1, (float) str_replace(',', '.', (string) ($settings['absence_critical_rate_threshold'] ?? 30))));
        $settings['manual_approval'] = 1;
        $settings['automatic_send'] = 0;

        update_option(LogiPilot_Spaces::option('gca_settings'), $settings, false);
    }

    private static function sanitize_time_setting(string $time, string $fallback): string
    {
        $time = trim($time);

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return $time;
        }

        return $fallback;
    }

    private static function sanitize_date_setting(string $date): string
    {
        $date = trim($date);

        if ($date === '') {
            return '';
        }

        foreach (array('Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y') as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $date);
            if ($parsed instanceof DateTime && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }

        return '';
    }

    public static function default_settings(): array
    {
        return array(
            'school_year' => self::default_school_year(),
            'lateness_threshold_1' => 3,
            'lateness_threshold_2' => 6,
            'lateness_threshold_dean' => 7,
            'absence_thresholds' => '',
            'school_start_date' => self::default_school_start_date(),
            'absence_grace_days' => 21,
            'absence_min_count_before_alert' => 2,
            'absence_unjustified_days_threshold' => 21,
            'absence_critical_rate_threshold' => 30,
            'dean_emails' => '',
            'email_sender_name' => 'CPNV',
            'email_sender_email' => '',
            'email_reply_to_email' => '',
            'message_signature_text' => '',
            'message_signature_image_url' => '',
            'message_signature_image_path' => '',
            'reminder_emails' => '',
            'weekly_reminder_enabled' => 0,
            'weekly_reminder_time' => '08:00',
            'stale_task_reminder_enabled' => 1,
            'stale_task_days' => 3,
            'stale_task_reminder_time' => '08:15',
            'import_reminder_enabled' => 1,
            'import_reminder_days' => 8,
            'manual_approval' => 1,
            'automatic_send' => 0,
        );
    }

    public static function default_school_start_date(): string
    {
        $school_year = self::default_school_year();
        $start_year = (int) substr($school_year, 0, 4);

        return $start_year > 0 ? $start_year . '-08-17' : gmdate('Y') . '-08-17';
    }

    public static function default_school_year(): string
    {
        $year = (int) gmdate('Y');
        $month = (int) gmdate('n');

        if ($month >= 8) {
            return $year . '-' . ($year + 1);
        }

        return ($year - 1) . '-' . $year;
    }

    public static function default_email_templates(): array
    {
        return array(
            'lateness_3_subject' => 'Convocation aux retenues {class_code} - {full_name}',
            'lateness_3_body' => "Bonjour {first_name},\n\nNous vous informons que vous avez atteint {lateness_count} arrivées tardives. Vous êtes {gender_participle} aux retenues le {convocation_date}.\n\n{detention_no_show_note}\n\nLe formulaire de convocation est joint à ce message.\n\nAvec nos salutations.",
            'lateness_6_subject' => 'Deuxième convocation aux retenues {class_code} - {full_name}',
            'lateness_6_body' => "Bonjour {first_name},\n\nUne première convocation a déjà été enregistrée pour {lateness_previous_threshold} arrivées tardives. Date de cette première convocation : {first_convocation_date}.\n\nDepuis cette convocation, {lateness_additional_count} arrivées tardives supplémentaires ont été enregistrées. Votre compteur atteint maintenant {lateness_total} retards.\n\n{detention_no_show_note}\n\nVous êtes {gender_participle} aux retenues le {convocation_date}. Le formulaire de convocation est joint à ce message.\n\nAvec nos salutations.",
            'absence_subject' => 'Convocation {class_code} - absences non justifiées - {full_name}',
            'absence_body' => "Bonjour {first_name},\n\nVotre taux d'absences non justifiées atteint {absence_rate} %. Nombre de périodes d’absence non justifiées à traiter : {absence_count}.\n\nUne convocation est nécessaire afin de faire le point sur votre situation.\n\nDate proposée : {convocation_date}.\n\nAvec nos salutations.",
            'absence_unjustified_21_subject' => 'Absences non justifiées {class_code} - {full_name}',
            'absence_unjustified_21_body' => "Bonjour,\n\nJe vous informe que {full_name}, classe {class_name}, présente actuellement {absence_count} période(s) d’absences non justifiées à traiter.\n\nCe volume dépasse le seuil de {threshold} jours/périodes et nécessite un suivi de la situation.\n\nAvec nos salutations.",
            'absence_rate_30_subject' => 'Taux d’absences non justifiées {class_code} - {full_name}',
            'absence_rate_30_body' => "Bonjour,\n\nJe vous informe que {full_name}, classe {class_name}, présente actuellement un taux d’absences non justifiées de {absence_rate} %, pour {absence_count} période(s) à traiter.\n\nCe taux atteint ou dépasse le seuil de {threshold} % et nécessite un suivi de la situation.\n\nAvec nos salutations.",
            'dean_warning_subject' => "Retards répétés {class_code} - demande de courrier d'avertissement - {full_name}",
            'dean_warning_body' => "Bonjour,\n\n{gender_article_cap} {full_name}, classe {class_name}, atteint {lateness_count} retards. Les convocations aux seuils précédents ont été traitées.\n\nMerci d'établir le courrier d'avertissement officiel si la procédure le confirme.\n\nAvec nos salutations.",
            'dean_exclusion_subject' => 'Récidive après avertissement {class_code} - {full_name}',
            'dean_exclusion_body' => "Bonjour,\n\n{gender_article_cap} {full_name}, classe {class_name}, a enregistré un nouveau retard après confirmation de l'avertissement.\n\nCompteur actuel : {lateness_count} retards.\n\nMerci d'évaluer la procédure d'exclusion temporaire.\n\nAvec nos salutations.",
            'contract_break_review_subject' => 'Rupture de contrat {class_code} - point de situation - {full_name}',
            'contract_break_review_body' => "Bonjour,\n\nLa rupture de contrat de {gender_article} {full_name}, classe {class_name}, a été enregistrée le {contract_break_date}.\n\n{gender_article_cap} peut suivre les cours jusqu’au {contract_follow_until}. L’alerte de contrôle prévue le {contract_review_date} est arrivée à échéance.\n\nMerci de vérifier si une nouvelle entreprise formatrice a été trouvée ou si {gender_pronoun} doit arrêter de se présenter aux cours.\n\nAvec nos salutations.",
            'student_report_subject' => 'Rapport de suivi {class_code} - {full_name}',
            'student_report_body' => "Bonjour,\n\nVous trouverez en pièce jointe le rapport de suivi de {gender_article} {full_name}, classe {class_name}.\n\nCe rapport reprend les compteurs actuels, les actions déjà entreprises et les situations encore à traiter.\n\nAvec nos salutations.",
            'trainer_bulk_subject' => 'Information de suivi - apprentis {classes_list}',
            'trainer_bulk_body' => "Bonjour,\n\nInformation concernant :\n\n{apprentices_list}\n\nAvec nos salutations.",
        );
    }

    public static function upgrade_email_templates(): void
    {
        $saved = get_option(LogiPilot_Spaces::option('gca_email_templates'), array());
        $defaults = self::default_email_templates();

        if (!is_array($saved)) {
            update_option(LogiPilot_Spaces::option('gca_email_templates'), $defaults, false);
            return;
        }

        $legacy = self::legacy_email_templates();
        $changed = false;

        foreach ($defaults as $key => $default) {
            if (!isset($saved[$key]) || $saved[$key] === '') {
                $saved[$key] = $default;
                $changed = true;
                continue;
            }

            foreach ($legacy[$key] ?? array() as $legacy_value) {
                if ((string) $saved[$key] === $legacy_value) {
                    $saved[$key] = $default;
                    $changed = true;
                    break;
                }
            }
        }

        if ($changed) {
            update_option(LogiPilot_Spaces::option('gca_email_templates'), wp_parse_args($saved, $defaults), false);
        }
    }

    private static function legacy_email_templates(): array
    {
        return array(
            'lateness_3_subject' => array(
                'Convocation aux retenues - {full_name}',
                'Convocation aux retenues {class_code} - {full_name}',
            ),
            'lateness_3_body' => array(
                "Bonjour {first_name},\n\nNous vous informons que vous avez atteint {lateness_count} arrivées tardives. Vous êtes convoqué(e) aux retenues le {convocation_date}.\n\n{detention_no_show_note}\n\nLe formulaire de convocation est joint à ce message.\n\nAvec nos salutations.",
                "Bonjour {first_name},\n\nNous vous informons que vous avez atteint {lateness_count} arrivées tardives. Vous êtes convoqué(e) aux retenues le {convocation_date}.\n\nLe formulaire de convocation est joint à ce message.\n\nAvec nos salutations.",
                "Bonjour {first_name},\n\nNous vous informons que vous avez atteint {lateness_count} retards. Vous etes convoque aux retenues le {convocation_date}.\n\nLe formulaire de convocation est joint a ce message.\n\nAvec nos salutations.",
            ),
            'lateness_6_subject' => array(
                'Deuxieme convocation aux retenues - {full_name}',
                'Deuxieme convocation aux retenues {class_code} - {full_name}',
            ),
            'lateness_6_body' => array(
                "Bonjour {first_name},\n\nUne première convocation a déjà été enregistrée pour 3 arrivées tardives. Date de cette première convocation : {first_convocation_date}.\n\nDepuis cette convocation, 3 arrivées tardives supplémentaires ont été enregistrées. Votre compteur atteint maintenant {lateness_count} retards.\n\n{detention_no_show_note}\n\nVous êtes convoqué(e) aux retenues le {convocation_date}. Le formulaire de convocation est joint à ce message.\n\nAvec nos salutations.",
                "Bonjour {first_name},\n\nUne première convocation a déjà été enregistrée pour 3 arrivées tardives. Date de cette première convocation : {first_convocation_date}.\n\nDepuis cette convocation, 3 arrivées tardives supplémentaires ont été enregistrées. Votre compteur atteint maintenant {lateness_count} retards.\n\nVous êtes convoqué(e) aux retenues le {convocation_date}. Le formulaire de convocation est joint à ce message.\n\nAvec nos salutations.",
                "Bonjour {first_name},\n\nUne première convocation a déjà été enregistrée pour 3 arrivées tardives. Date de cette première convocation : {first_convocation_date}.\n\nDepuis cette convocation, 3 arrivées tardives supplémentaires ont été enregistrées. Votre compteur atteint maintenant {lateness_count} retards.\n\n{detention_no_show_note}\n\nVous êtes {gender_participle} aux retenues le {convocation_date}. Le formulaire de convocation est joint à ce message.\n\nAvec nos salutations.",
                "Bonjour {first_name},\n\nMalgre une premiere convocation, trois retards supplementaires ont ete enregistres. Votre compteur atteint maintenant {lateness_count} retards.\n\nVous etes convoque aux retenues le {convocation_date}. Le formulaire de convocation est joint a ce message.\n\nAvec nos salutations.",
            ),
            'absence_subject' => array(
                'Convocation - absences injustifiees - {full_name}',
                'Convocation {class_code} - absences injustifiees - {full_name}',
                'Convocation {class_code} - absences injustifiées - {full_name}',
            ),
            'absence_body' => array(
                "Bonjour {first_name},\n\nVotre taux d'absences injustifiées atteint {absence_rate} %. Une convocation est nécessaire afin de faire le point sur votre situation.\n\nDate proposée : {convocation_date}.\n\nAvec nos salutations.",
                "Bonjour {first_name},\n\nVotre taux d'absence injustifiee atteint {absence_rate}%. Une convocation est necessaire afin de faire le point sur votre situation.\n\nDate proposee : {convocation_date}.\n\nAvec nos salutations.",
            ),
            'absence_unjustified_21_subject' => array(
                'Absences injustifiées {class_code} - {full_name}',
            ),
            'absence_rate_30_subject' => array(
                'Taux d’absences injustifiées {class_code} - {full_name}',
            ),
            'dean_warning_subject' => array(
                'Retards repetes - demande de courrier d avertissement - {full_name}',
                'Retards repetes {class_code} - demande de courrier d avertissement - {full_name}',
            ),
            'dean_warning_body' => array(
                "Bonjour,\n\nL'apprenti(e) {full_name}, classe {class_name}, atteint {lateness_count} retards. Les convocations aux seuils précédents ont été traitées.\n\nMerci d'établir le courrier d'avertissement officiel si la procédure le confirme.\n\nAvec nos salutations.",
                "Bonjour,\n\nL'apprenti {full_name}, classe {class_name}, atteint {lateness_count} retards. Les convocations aux seuils precedents ont ete traitees.\n\nMerci d'etablir le courrier d'avertissement officiel si la procedure le confirme.\n\nAvec nos salutations.",
            ),
            'dean_exclusion_subject' => array(
                'Recidive apres avertissement - {full_name}',
                'Recidive apres avertissement {class_code} - {full_name}',
            ),
            'dean_exclusion_body' => array(
                "Bonjour,\n\nL'apprenti(e) {full_name}, classe {class_name}, a enregistré un nouveau retard après confirmation de l'avertissement.\n\nCompteur actuel : {lateness_count} retards.\n\nMerci d'évaluer la procédure d'exclusion temporaire.\n\nAvec nos salutations.",
                "Bonjour,\n\nL'apprenti {full_name}, classe {class_name}, a enregistre un nouveau retard apres confirmation de l'avertissement.\n\nCompteur actuel : {lateness_count} retards.\n\nMerci d'evaluer la procedure d'exclusion temporaire.\n\nAvec nos salutations.",
            ),
            'student_report_body' => array(
                "Bonjour {trainer_salutation},\n\nVous trouverez en pièce jointe le rapport de suivi de {gender_article} {full_name}, classe {class_name}.\n\nCe rapport reprend les compteurs actuels, les actions déjà entreprises et les situations encore à traiter.\n\nAvec nos salutations.",
                "Bonjour {trainer_name},\n\nVous trouverez en pièce jointe le rapport de suivi de {gender_article} {full_name}, classe {class_name}.\n\nCe rapport reprend les compteurs actuels, les actions déjà entreprises et les situations encore à traiter.\n\nAvec nos salutations.",
            ),
            'trainer_bulk_body' => array(
                "Bonjour {trainer_salutation},\n\nInformation concernant :\n\n{apprentices_list}\n\nAvec nos salutations.",
                "Bonjour {recipient_name},\n\nInformation concernant :\n\n{apprentices_list}\n\nAvec nos salutations.",
                "Bonjour {trainer_name},\n\nCe message concerne votre/vos apprenti(s) suivant(s) :\n\n{apprentices_list}\n\nAvec nos salutations.",
                "Bonjour {recipient_name},\n\nCe message concerne votre/vos apprenti(s) suivant(s) :\n\n{apprentices_list}\n\nAvec nos salutations.",
            ),
        );
    }

    public static function get_settings(): array
    {
        $saved = get_option(LogiPilot_Spaces::option('gca_settings'), array());
        return wp_parse_args(is_array($saved) ? $saved : array(), self::default_settings());
    }

    public static function update_settings(array $settings): void
    {
        update_option(LogiPilot_Spaces::option('gca_settings'), wp_parse_args($settings, self::default_settings()), false);
    }

    public static function get_email_templates(): array
    {
        $saved = get_option(LogiPilot_Spaces::option('gca_email_templates'), array());
        return wp_parse_args(is_array($saved) ? $saved : array(), self::default_email_templates());
    }

    public static function update_email_templates(array $templates): void
    {
        update_option(LogiPilot_Spaces::option('gca_email_templates'), wp_parse_args($templates, self::default_email_templates()), false);
    }

    public static function sanitize_message_body($body): string
    {
        $allowed = wp_kses_allowed_html('post');
        foreach (array('p', 'div', 'span') as $tag) {
            if (!isset($allowed[$tag]) || !is_array($allowed[$tag])) {
                $allowed[$tag] = array();
            }
            $allowed[$tag]['style'] = true;
        }

        $allowed['u'] = array();
        $allowed['br'] = array();
        $allowed['hr'] = array('style' => true);

        return wp_kses((string) $body, $allowed);
    }

    public static function default_trainer_message_templates(): array
    {
        $defaults = self::default_email_templates();

        return array(
            'general' => array(
                'id' => 'general',
                'label' => 'Information générale',
                'subject' => (string) $defaults['trainer_bulk_subject'],
                'body' => (string) $defaults['trainer_bulk_body'],
            ),
            'class_info' => array(
                'id' => 'class_info',
                'label' => 'Information de classe',
                'subject' => 'Information de classe - {classes_list}',
                'body' => "Bonjour,\n\nNous vous transmettons une information concernant la classe {classes_list}.\n\n{apprentices_list}\n\nAvec nos salutations.",
            ),
        );
    }

    public static function get_trainer_message_templates(): array
    {
        $saved = get_option(LogiPilot_Spaces::option('gca_trainer_message_templates'), array());
        if (!is_array($saved) || !$saved) {
            $saved = self::default_trainer_message_templates();
        }

        $defaults = self::default_trainer_message_templates();
        $clean = array();
        foreach ($saved as $key => $template) {
            if (!is_array($template)) {
                continue;
            }

            $id = sanitize_key((string) ($template['id'] ?? $key));
            if ($id === '') {
                continue;
            }

            $body = self::sanitize_message_body((string) ($template['body'] ?? ''));
            if ($id === 'general' && in_array(
                $body,
                array(
                    "Bonjour {trainer_salutation},\n\nInformation concernant :\n\n{apprentices_list}\n\nAvec nos salutations.",
                    "Bonjour {recipient_name},\n\nInformation concernant :\n\n{apprentices_list}\n\nAvec nos salutations.",
                    "Bonjour {trainer_name},\n\nCe message concerne votre/vos apprenti(s) suivant(s) :\n\n{apprentices_list}\n\nAvec nos salutations.",
                    "Bonjour {recipient_name},\n\nCe message concerne votre/vos apprenti(s) suivant(s) :\n\n{apprentices_list}\n\nAvec nos salutations.",
                ),
                true
            )) {
                $body = (string) $defaults['general']['body'];
            }
            if ($id === 'class_info' && in_array(
                $body,
                array(
                    "Bonjour {recipient_name},\n\nNous vous transmettons une information concernant la classe {classes_list}.\n\n{apprentices_list}\n\nAvec nos salutations.",
                    "Bonjour {trainer_salutation},\n\nNous vous transmettons une information concernant la classe {classes_list}.\n\n{apprentices_list}\n\nAvec nos salutations.",
                ),
                true
            )) {
                $body = (string) $defaults['class_info']['body'];
            }

            $clean[$id] = array(
                'id' => $id,
                'label' => sanitize_text_field((string) ($template['label'] ?? 'Message préformaté')),
                'subject' => sanitize_text_field((string) ($template['subject'] ?? '')),
                'body' => $body,
            );
        }

        foreach ($defaults as $id => $template) {
            if (!isset($clean[$id])) {
                $clean[$id] = $template;
            }
        }

        return $clean ?: $defaults;
    }

    public static function update_trainer_message_templates(array $templates): void
    {
        update_option(LogiPilot_Spaces::option('gca_trainer_message_templates'), $templates ?: self::default_trainer_message_templates(), false);
    }

    public static function default_free_message_templates(): array
    {
        return array(
            'general' => array(
                'id' => 'general',
                'label' => 'Information individuelle',
                'subject' => 'Suivi {class_code} - {full_name}',
                'body' => "Bonjour {first_name},\n\nJe vous contacte concernant votre suivi de classe.\n\nAvec nos salutations.",
            ),
            'removal_followup' => array(
                'id' => 'removal_followup',
                'label' => 'Mise à la porte',
                'subject' => 'Mise à la porte {threshold} {class_code} - {full_name}',
                'body' => "Bonjour {first_name},\n\nUne mise à la porte a été enregistrée dans votre suivi.\n\nClasse : {class_name}\nNombre de mises à la porte enregistrées : {removal_count}\n\nMerci de prendre contact avec votre enseignant afin de clarifier la situation.\n\nAvec nos salutations.",
            ),
        );
    }

    public static function get_free_message_templates(): array
    {
        $saved = get_option(LogiPilot_Spaces::option('gca_free_message_templates'), array());
        if (!is_array($saved) || !$saved) {
            $saved = self::default_free_message_templates();
        }

        $defaults = self::default_free_message_templates();
        $clean = array();
        foreach ($saved as $key => $template) {
            if (!is_array($template)) {
                continue;
            }

            $id = sanitize_key((string) ($template['id'] ?? $key));
            if ($id === '') {
                continue;
            }

            $clean[$id] = array(
                'id' => $id,
                'label' => sanitize_text_field((string) ($template['label'] ?? 'Message libre')),
                'subject' => sanitize_text_field((string) ($template['subject'] ?? '')),
                'body' => self::sanitize_message_body((string) ($template['body'] ?? '')),
            );
        }

        foreach ($defaults as $id => $template) {
            if (!isset($clean[$id])) {
                $clean[$id] = $template;
            }
        }

        return $clean ?: $defaults;
    }

    public static function update_free_message_templates(array $templates): void
    {
        update_option(LogiPilot_Spaces::option('gca_free_message_templates'), $templates ?: self::default_free_message_templates(), false);
    }

    public static function get_import_mapping_profile(string $type, int $teacher_id = 0): array
    {
        $type = sanitize_key($type);
        $teacher_id = $teacher_id > 0 ? $teacher_id : self::current_teacher_id();

        if ($type === '' || $teacher_id <= 0) {
            return array();
        }

        $saved = get_option(LogiPilot_Spaces::option('gca_import_mapping_profiles'), array());
        if (!is_array($saved) || !$saved) {
            return array();
        }

        $teacher_key = (string) $teacher_id;
        $teacher_profiles = $saved[$teacher_key] ?? ($saved[$teacher_id] ?? array());
        if (!is_array($teacher_profiles) || empty($teacher_profiles[$type]) || !is_array($teacher_profiles[$type])) {
            return array();
        }

        $profile = $teacher_profiles[$type];
        $raw_mapping = isset($profile['mapping']) && is_array($profile['mapping']) ? $profile['mapping'] : array();
        $mapping = array();

        foreach ($raw_mapping as $field => $header) {
            $field = sanitize_key((string) $field);
            if ($field === '') {
                continue;
            }

            $mapping[$field] = sanitize_text_field((string) $header);
        }

        if (!$mapping) {
            return array();
        }

        return array(
            'label' => sanitize_text_field((string) ($profile['label'] ?? 'Mapping sauvegardé')),
            'mapping' => $mapping,
            'updated_at' => sanitize_text_field((string) ($profile['updated_at'] ?? '')),
        );
    }

    public static function save_import_mapping_profile(string $type, int $teacher_id, string $label, array $mapping): void
    {
        $type = sanitize_key($type);
        $teacher_id = $teacher_id > 0 ? $teacher_id : self::current_teacher_id();

        if ($type === '' || $teacher_id <= 0) {
            return;
        }

        $clean_mapping = array();
        foreach ($mapping as $field => $header) {
            $field = sanitize_key((string) $field);
            if ($field === '') {
                continue;
            }

            $clean_mapping[$field] = sanitize_text_field((string) $header);
        }

        if (!$clean_mapping) {
            return;
        }

        $label = sanitize_text_field($label);
        if ($label === '') {
            $label = 'Mapping ' . $type;
        }

        $saved = get_option(LogiPilot_Spaces::option('gca_import_mapping_profiles'), array());
        if (!is_array($saved)) {
            $saved = array();
        }

        $teacher_key = (string) $teacher_id;
        if (empty($saved[$teacher_key]) || !is_array($saved[$teacher_key])) {
            $saved[$teacher_key] = array();
        }

        $saved[$teacher_key][$type] = array(
            'label' => $label,
            'mapping' => $clean_mapping,
            'updated_at' => self::now(),
        );

        update_option(LogiPilot_Spaces::option('gca_import_mapping_profiles'), $saved, false);
    }

    public static function ensure_private_dirs(): string
    {
        $base = self::private_dir();

        foreach (array($base, $base . '/imports', $base . '/pdfs', $base . '/reports', $base . '/photos', $base . '/signatures', $base . '/backups') as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }

            if (!file_exists($dir . '/index.php')) {
                file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
            }

            if (!file_exists($dir . '/.htaccess')) {
                file_put_contents($dir . '/.htaccess', "Deny from all\n");
            }
        }

        return $base;
    }

    public static function private_dir(string $child = ''): string
    {
        $uploads = wp_upload_dir(null, false);
        $dir = trailingslashit($uploads['basedir']) . 'gestion-apprentis/private' . (class_exists('LogiPilot_Spaces') ? LogiPilot_Spaces::dir_suffix() : '');

        if ($child !== '') {
            $dir .= '/' . ltrim($child, '/');
        }

        return $dir;
    }

    public static function private_relative_path(string $path): string
    {
        $base = trailingslashit(self::private_dir());

        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/');
        }

        return '';
    }

    public static function now(): string
    {
        return current_datetime()->format('Y-m-d H:i:s');
    }

    public static function manual_fields_from_json($value): array
    {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return array();
        }

        $fields = array();
        foreach ($decoded as $field) {
            $field = sanitize_key((string) $field);
            if ($field !== '') {
                $fields[$field] = true;
            }
        }

        return array_keys($fields);
    }

    public static function manual_fields_json(array $fields): string
    {
        $clean = array();
        foreach ($fields as $field) {
            $field = sanitize_key((string) $field);
            if ($field !== '') {
                $clean[$field] = true;
            }
        }

        $fields = array_keys($clean);
        sort($fields);

        return wp_json_encode($fields);
    }

    public static function merge_manual_fields($current, array $fields): string
    {
        return self::manual_fields_json(array_merge(self::manual_fields_from_json($current), $fields));
    }

    public static function log(string $action, string $object_type = '', int $object_id = 0, array $data = array()): void
    {
        global $wpdb;

        $teacher_id = self::current_teacher_id();
        $actor = self::current_actor_snapshot();
        $data_json = wp_json_encode($data);
        $data_hash = hash('sha256', (string) $data_json);

        $wpdb->insert(
            self::table('logs'),
            array(
                'user_id' => $actor['user_id'],
                'teacher_id' => $teacher_id,
                'actor_name' => $actor['display_name'],
                'actor_email' => $actor['user_email'],
                'action' => $action,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'data_json' => $data_json,
                'data_hash' => $data_hash,
                'request_id' => self::request_id(),
                'created_at' => self::now(),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        self::snapshot($action, $object_type, $object_id, $data, $teacher_id, $actor['user_id']);
    }

    public static function add_event(int $student_id, string $event_type, string $status = '', array $payload = array()): void
    {
        global $wpdb;

        $teacher_id = self::current_teacher_id();
        $wpdb->insert(
            self::table('events'),
            array(
                'student_id' => $student_id,
                'event_type' => $event_type,
                'status' => $status,
                'payload_json' => wp_json_encode($payload),
                'created_by' => get_current_user_id(),
                'teacher_id' => $teacher_id,
                'created_at' => self::now(),
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        self::snapshot(
            'event_' . $event_type,
            'student',
            $student_id,
            array(
                'status' => $status,
                'payload' => $payload,
            ),
            $teacher_id,
            get_current_user_id()
        );
    }

    public static function snapshot(string $action, string $object_type, int $object_id, array $payload = array(), int $teacher_id = 0, int $user_id = 0): void
    {
        global $wpdb;

        $payload_json = wp_json_encode($payload);

        $wpdb->insert(
            self::table('snapshots'),
            array(
                'teacher_id' => $teacher_id > 0 ? $teacher_id : self::current_teacher_id(),
                'user_id' => $user_id > 0 ? $user_id : get_current_user_id(),
                'action' => $action,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'payload_json' => $payload_json,
                'payload_hash' => hash('sha256', (string) $payload_json),
                'created_at' => self::now(),
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }

    public static function current_teacher_id(): int
    {
        global $wpdb;

        $user = wp_get_current_user();
        $user_id = isset($user->ID) ? (int) $user->ID : 0;

        if ($user_id <= 0) {
            return 0;
        }

        $table = self::table('teachers');
        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE wp_user_id = %d LIMIT 1", $user_id));
        $data = array(
            'wp_user_id' => $user_id,
            'user_login' => sanitize_user((string) $user->user_login, true),
            'display_name' => sanitize_text_field((string) ($user->display_name ?: $user->user_login)),
            'user_email' => sanitize_email((string) $user->user_email),
            'roles_json' => wp_json_encode(is_array($user->roles) ? array_values($user->roles) : array()),
            'last_seen_at' => self::now(),
        );

        if ($existing_id > 0) {
            $wpdb->update(
                $table,
                $data,
                array('id' => $existing_id),
                array('%d', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            return $existing_id;
        }

        $data['first_seen_at'] = self::now();
        $wpdb->insert(
            $table,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    private static function current_actor_snapshot(): array
    {
        $user = wp_get_current_user();
        $user_id = isset($user->ID) ? (int) $user->ID : 0;

        return array(
            'user_id' => $user_id,
            'display_name' => $user_id > 0 ? sanitize_text_field((string) ($user->display_name ?: $user->user_login)) : '',
            'user_email' => $user_id > 0 ? sanitize_email((string) $user->user_email) : '',
        );
    }

    private static function request_id(): string
    {
        static $request_id = '';

        if ($request_id === '') {
            $request_id = wp_generate_uuid4();
        }

        return $request_id;
    }
}
