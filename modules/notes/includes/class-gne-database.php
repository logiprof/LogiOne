<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNE_Database
{
    private const TABLE_NAMES = array('classes', 'students', 'semesters', 'branches', 'evaluations', 'grades', 'settings');

    public static function table(string $name): string
    {
        global $wpdb;

        if (!in_array($name, self::TABLE_NAMES, true)) {
            throw new InvalidArgumentException('Unknown GNE table.');
        }

        // Espace de l'enseignant : '' (données d'origine) ou « t{ID}_ ».
        return $wpdb->prefix . 'gne_' . (class_exists('LogiPilot_Spaces') ? LogiPilot_Spaces::infix() : '') . $name;
    }

    public static function activate(): void
    {
        self::create_tables();
        self::add_capabilities();
        self::add_default_settings();
        if (!get_option('gne_db_version')) {
            // Première installation uniquement : une réactivation ne recalcule jamais les notes existantes.
            self::normalize_grade_statuses();
            self::recalculate_existing_notes();
        }
        update_option('gne_db_version', defined('GNE_VERSION') ? GNE_VERSION : '1', false);
    }

    public static function deactivate(): void
    {
        // Data is intentionally preserved on deactivation.
        wp_clear_scheduled_hook('gne_missing_grade_reminder');
    }

    public static function maybe_upgrade(): void
    {
        $target = defined('GNE_VERSION') ? GNE_VERSION : '1';

        if (get_option('gne_db_version') === $target) {
            return;
        }

        self::create_tables();
        self::add_capabilities();
        self::add_default_settings();
        // 0.1.21 : les notes enregistrées ne sont plus recalculées lors d'une mise à jour (conservation de l'historique).
        update_option('gne_db_version', $target, false);
    }

    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $classes = self::table('classes');
        $students = self::table('students');
        $semesters = self::table('semesters');
        $branches = self::table('branches');
        $evaluations = self::table('evaluations');
        $grades = self::table('grades');
        $settings = self::table('settings');

        $sql = "
CREATE TABLE {$classes} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL DEFAULT '',
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
    class_id bigint(20) unsigned NOT NULL DEFAULT 0,
    last_name varchar(120) NOT NULL DEFAULT '',
    first_name varchar(120) NOT NULL DEFAULT '',
    identifier varchar(80) NOT NULL DEFAULT '',
    email varchar(190) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY class_id (class_id),
    KEY status (status),
    KEY last_name (last_name),
    KEY identifier (identifier)
) {$charset};

CREATE TABLE {$semesters} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL DEFAULT '',
    school_year varchar(20) NOT NULL DEFAULT '',
    start_date date DEFAULT NULL,
    end_date date DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    sort_order int(10) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY school_year (school_year),
    KEY status (status),
    KEY sort_order (sort_order)
) {$charset};

CREATE TABLE {$branches} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(120) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY name (name),
    KEY status (status)
) {$charset};

CREATE TABLE {$evaluations} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    class_id bigint(20) unsigned NOT NULL DEFAULT 0,
    semester_id bigint(20) unsigned NOT NULL DEFAULT 0,
    branch_id bigint(20) unsigned NOT NULL DEFAULT 0,
    module_name varchar(190) NOT NULL DEFAULT '',
    evaluation_date date DEFAULT NULL,
    is_makeup tinyint(1) unsigned NOT NULL DEFAULT 0,
    total_points decimal(10,2) NOT NULL DEFAULT 0,
    progress_pdf_path varchar(255) NOT NULL DEFAULT '',
    progress_pdf_name varchar(190) NOT NULL DEFAULT '',
    progress_pdf_uploaded_at datetime DEFAULT NULL,
    agapepe_reported tinyint(1) unsigned NOT NULL DEFAULT 0,
    agapepe_reported_at datetime DEFAULT NULL,
    agapepe_reported_by bigint(20) unsigned NOT NULL DEFAULT 0,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY class_id (class_id),
    KEY semester_id (semester_id),
    KEY branch_id (branch_id),
    KEY evaluation_date (evaluation_date),
    KEY is_makeup (is_makeup),
    KEY agapepe_reported (agapepe_reported)
) {$charset};

CREATE TABLE {$grades} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    evaluation_id bigint(20) unsigned NOT NULL DEFAULT 0,
    student_id bigint(20) unsigned NOT NULL DEFAULT 0,
    points_obtained decimal(10,2) DEFAULT NULL,
    points_adjustment decimal(5,2) NOT NULL DEFAULT 0,
    note decimal(4,1) DEFAULT NULL,
    is_cheating tinyint(1) unsigned NOT NULL DEFAULT 0,
    is_makeup tinyint(1) unsigned NOT NULL DEFAULT 0,
    makeup_date date DEFAULT NULL,
    status varchar(30) NOT NULL DEFAULT 'graded',
    status_note text,
    reminded_at datetime DEFAULT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY evaluation_student (evaluation_id, student_id),
    KEY student_id (student_id),
    KEY note (note),
    KEY is_cheating (is_cheating),
    KEY is_makeup (is_makeup),
    KEY makeup_date (makeup_date),
    KEY status (status),
    KEY reminded_at (reminded_at)
) {$charset};

CREATE TABLE {$settings} (
    setting_key varchar(100) NOT NULL,
    setting_value longtext,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (setting_key)
) {$charset};
";

        dbDelta($sql);
    }

    public static function default_settings(): array
    {
        return array(
            'missing_grade_reminder_enabled' => '1',
            'missing_grade_reminder_email' => '',
            'missing_grade_reminder_days' => '15',
        );
    }

    public static function add_default_settings(): void
    {
        global $wpdb;

        $table = self::table('settings');
        $now = self::now();

        foreach (self::default_settings() as $key => $value) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT setting_key FROM {$table} WHERE setting_key = %s", $key));

            if (!$exists) {
                $wpdb->insert(
                    $table,
                    array(
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'updated_at' => $now,
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }
    }

    public static function get_settings(): array
    {
        global $wpdb;

        $defaults = self::default_settings();
        $table = self::table('settings');
        $rows = $wpdb->get_results("SELECT setting_key, setting_value FROM {$table}");
        $settings = $defaults;

        foreach ($rows as $row) {
            $key = (string) $row->setting_key;
            if (array_key_exists($key, $defaults)) {
                $settings[$key] = (string) $row->setting_value;
            }
        }

        return $settings;
    }

    public static function update_settings(array $settings): void
    {
        global $wpdb;

        $defaults = self::default_settings();
        $table = self::table('settings');
        $now = self::now();

        foreach ($defaults as $key => $default_value) {
            $value = array_key_exists($key, $settings) ? (string) $settings[$key] : $default_value;
            $wpdb->replace(
                $table,
                array(
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'updated_at' => $now,
                ),
                array('%s', '%s', '%s')
            );
        }
    }

    private static function normalize_grade_statuses(): void
    {
        global $wpdb;

        $grades = self::table('grades');
        $wpdb->query("UPDATE {$grades} SET status = 'graded' WHERE status = '' OR status IS NULL");
    }

    private static function recalculate_existing_notes(): void
    {
        global $wpdb;

        $grades = self::table('grades');
        $evaluations = self::table('evaluations');
        $now = self::now();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$grades} g
                 INNER JOIN {$evaluations} e ON e.id = g.evaluation_id
                 SET g.note = ROUND(((((LEAST(GREATEST(g.points_obtained + COALESCE(g.points_adjustment, 0), 0), e.total_points) / e.total_points) * 5) + 1) * 2)) / 2,
                     g.updated_at = %s
                 WHERE g.status = 'graded'
                   AND COALESCE(g.is_cheating, 0) = 0
                   AND g.points_obtained IS NOT NULL
                   AND e.total_points > 0",
                $now
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$grades}
                 SET note = 1,
                     points_obtained = NULL,
                     points_adjustment = 0,
                     is_makeup = 0,
                     makeup_date = NULL,
                     status = 'graded',
                     status_note = 'Triche : note fixée à 1.',
                     updated_at = %s
                 WHERE COALESCE(is_cheating, 0) = 1",
                $now
            )
        );
    }

    /** LogiOne : les droits viennent de la matrice centrale ; gne_manage n'est plus ajouté (ACL-06, ACL-08). */
    public static function add_capabilities(): void
    {
        return;
        $roles = wp_roles();

        foreach (array_keys($roles->roles) as $role_name) {
            $role = get_role($role_name);

            if ($role && ($role->has_cap('manage_options') || $role->has_cap('gca_manage'))) {
                $role->add_cap('gne_manage');
            }
        }
    }

    public static function now(): string
    {
        return current_time('mysql');
    }

    public static function default_school_year(): string
    {
        $year = (int) wp_date('Y');
        $month = (int) wp_date('n');

        if ($month >= 8) {
            return $year . '-' . ($year + 1);
        }

        return ($year - 1) . '-' . $year;
    }
}
