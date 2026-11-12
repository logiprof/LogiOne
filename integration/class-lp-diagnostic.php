<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lot 0 (inventaire en lecture seule) et lot 5 (contrôle de conservation avant / après).
 *
 * La page de diagnostic ne modifie rien. Seules deux actions explicites écrivent :
 * la photographie de conservation (une option) et le test de protection des fichiers
 * (un fichier de test créé puis supprimé aussitôt).
 */
final class LogiPilot_Diagnostic
{
    public const OPTION_PHOTOS = 'logipilot_conservation';

    public static function init(): void
    {
        add_action('admin_post_logipilot_conservation', array(__CLASS__, 'handle_photo'));
        add_action('admin_post_logipilot_file_test', array(__CLASS__, 'handle_file_test'));
        add_action('admin_post_logipilot_diagnostic_export', array(__CLASS__, 'handle_export'));
    }

    /* -------------------------------------------------------------------
     * Photographie de conservation (MIG-02, MIG-03, REC-01)
     * ----------------------------------------------------------------- */

    public static function tracked_tables(): array
    {
        global $wpdb;

        $tables = array();
        foreach (array('gne_', 'logieval_secure_', 'gca_') as $prefix) {
            foreach ((array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . $prefix) . '%')) as $table) {
                $tables[] = (string) $table;
            }
        }
        sort($tables);

        return $tables;
    }

    public static function photograph(): array
    {
        global $wpdb;

        $photo = array('at' => current_time('mysql'), 'tables' => array(), 'files' => array(), 'sensei' => array(), 'options' => array());
        foreach (self::tracked_tables() as $table) {
            $checksum = $wpdb->get_row("CHECKSUM TABLE `{$table}`", ARRAY_N);
            $has_id = (bool) $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE %s', 'id'));
            $photo['tables'][$table] = array(
                'rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"),
                'max_id' => $has_id ? (int) $wpdb->get_var("SELECT MAX(id) FROM `{$table}`") : null,
                'checksum' => (string) ($checksum[1] ?? ''),
            );
        }
        $photo['sensei']['lesson_status_comments'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'sensei_lesson_status'");
        $photo['sensei']['progress_dates_meta'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key LIKE '\\_psgpt\\_%' OR meta_key LIKE '\\_lsgpt\\_%'");
        foreach (array('gne_db_version', 'gca_db_version', 'logieval_secure_db_version', 'gca_settings', 'gca_email_templates', 'logieval_secure_settings', 'psgpt_inactivity_days', 'psgpt_interface_colors') as $option) {
            $photo['options'][$option] = md5((string) maybe_serialize(get_option($option, '')));
        }
        $uploads = wp_upload_dir(null, false);
        foreach (array('loginotes-documents', 'gestion-apprentis/private') as $dir) {
            $photo['files'][$dir] = self::directory_fingerprint(trailingslashit((string) $uploads['basedir']) . $dir);
        }

        return $photo;
    }

    private static function directory_fingerprint(string $path): array
    {
        $count = 0;
        $size = 0;
        $hash = hash_init('sha1');
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            $files = array();
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = substr($file->getPathname(), strlen($path)) . '|' . $file->getSize() . '|' . hash_file('sha1', $file->getPathname());
                }
            }
            sort($files);
            foreach ($files as $line) {
                $count++;
                $size += (int) explode('|', $line)[1];
                hash_update($hash, $line . "\n");
            }
        }

        return array('count' => $count, 'bytes' => $size, 'fingerprint' => hash_final($hash));
    }

    public static function record_conservation(string $phase): void
    {
        $photos = get_option(self::OPTION_PHOTOS, array());
        $photos = is_array($photos) ? $photos : array();
        $photos[$phase . '_' . gmdate('Ymd_His')] = self::photograph();
        if (count($photos) > 20) {
            $photos = array_slice($photos, -20, null, true);
        }
        update_option(self::OPTION_PHOTOS, $photos, false);
    }

    public static function compare(array $before, array $after): array
    {
        $lines = array();
        foreach ($before['tables'] as $table => $data) {
            $now = $after['tables'][$table] ?? null;
            if (!$now) {
                $lines[] = array($table, 'TABLE ABSENTE', 'error');
                continue;
            }
            if ($now['rows'] < $data['rows']) {
                $lines[] = array($table, sprintf('%d → %d lignes : des lignes ont disparu', $data['rows'], $now['rows']), 'error');
            } elseif ($now['checksum'] !== $data['checksum']) {
                $lines[] = array($table, sprintf('%d → %d lignes, contenu modifié (utilisation normale ou écriture à vérifier)', $data['rows'], $now['rows']), 'warning');
            } else {
                $lines[] = array($table, sprintf('%d lignes, identique', $now['rows']), 'ok');
            }
        }
        foreach ($before['files'] as $dir => $data) {
            $now = $after['files'][$dir] ?? array('count' => 0, 'fingerprint' => '');
            if ($now['count'] < $data['count']) {
                $lines[] = array('fichiers ' . $dir, sprintf('%d → %d fichiers : des fichiers ont disparu', $data['count'], $now['count']), 'error');
            } else {
                $lines[] = array('fichiers ' . $dir, sprintf('%d → %d fichiers', $data['count'], $now['count']) . ($now['fingerprint'] === $data['fingerprint'] ? ', identiques' : ', ajouts ou modifications'), $now['fingerprint'] === $data['fingerprint'] ? 'ok' : 'warning');
            }
        }
        foreach ($before['sensei'] as $key => $value) {
            $now = (int) ($after['sensei'][$key] ?? 0);
            $lines[] = array('Sensei ' . $key, $value . ' → ' . $now, $now < $value ? 'error' : 'ok');
        }
        foreach ($before['options'] as $key => $hash) {
            $lines[] = array('option ' . $key, ($after['options'][$key] ?? '') === $hash ? 'identique' : 'modifiée', ($after['options'][$key] ?? '') === $hash ? 'ok' : 'warning');
        }

        return $lines;
    }

    public static function handle_photo(): void
    {
        if (!current_user_can('lp_admin_modules')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_conservation');
        self::record_conservation('manuelle');
        wp_safe_redirect(admin_url('admin.php?page=logione-diagnostic&photo=1#conservation'));
        exit;
    }

    /* -------------------------------------------------------------------
     * Inventaire (lot 0)
     * ----------------------------------------------------------------- */

    public static function inventory(): array
    {
        global $wpdb;

        $p = $wpdb->prefix;
        $exists = static fn(string $table): bool => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        $count = static function (string $sql) use ($wpdb) {
            $value = $wpdb->get_var($sql);
            return $value === null ? null : (int) $value;
        };

        $inv = array();
        $inv['environnement'] = array(
            'WordPress' => get_bloginfo('version'),
            'PHP' => PHP_VERSION,
            'Base de données' => $wpdb->db_server_info(),
            'Serveur web' => sanitize_text_field((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'inconnu')),
            'Préfixe des tables' => $p,
            'Multisite' => is_multisite() ? 'oui' : 'non',
            'Sels de sécurité définis' => (defined('AUTH_SALT') && AUTH_SALT !== '' && AUTH_SALT !== 'put your unique phrase here') ? 'oui (nécessaires aux clés IA chiffrées : à conserver à l’identique)' : 'NON',
            'LogiOne' => LOGIPILOT_VERSION,
        );
        $inv['versions des modules'] = array(
            'gne_db_version (LogiNotes)' => (string) get_option('gne_db_version', '—'),
            'logieval_secure_db_version (LogiExam)' => (string) get_option('logieval_secure_db_version', '—'),
            'gca_db_version (LogiClass)' => (string) get_option('gca_db_version', '—'),
            'gca_recalculate_tasks_once' => (string) get_option('gca_recalculate_tasks_once', '0'),
            'logipilot_db_version' => (string) get_option('logipilot_db_version', '—'),
            'Anciens plugins encore actifs' => implode(', ', LogiPilot_Plugin::legacy_active_plugins()) ?: 'aucun',
        );
        $inv['Sensei'] = array(
            'Types cours / leçons / groupes' => implode(' / ', array_map(static fn($t) => post_type_exists($t) ? 'oui' : 'non', array('course', 'lesson', 'group'))),
            'Table sensei_pro_groups_students' => $exists($p . 'sensei_pro_groups_students') ? 'présente' : 'ABSENTE (groupes indisponibles pour LogiProgress)',
            'Table sensei_lms_progress' => $exists($p . 'sensei_lms_progress') ? 'présente' : 'absente',
            'Source de progression compatible' => LogiPilot_Progress_Service::source_compatible() ? 'oui' : 'NON : progression stockée hors des commentaires',
            'Statuts de leçons (commentaires)' => (string) $count("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'sensei_lesson_status'"),
            'Quiz en attente de correction (ungraded)' => (string) $count("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'sensei_lesson_status' AND comment_approved = 'ungraded'"),
        );

        $checks = array();
        if ($exists($p . 'gne_grades')) {
            $checks['Notes sans évaluation'] = $count("SELECT COUNT(*) FROM {$p}gne_grades g LEFT JOIN {$p}gne_evaluations e ON e.id = g.evaluation_id WHERE e.id IS NULL");
            $checks['Notes sans élève'] = $count("SELECT COUNT(*) FROM {$p}gne_grades g LEFT JOIN {$p}gne_students s ON s.id = g.student_id WHERE s.id IS NULL");
            $checks['Élèves notés dans une évaluation d’une autre classe (changement de classe)'] = $count("SELECT COUNT(DISTINCT g.student_id) FROM {$p}gne_grades g INNER JOIN {$p}gne_evaluations e ON e.id = g.evaluation_id INNER JOIN {$p}gne_students s ON s.id = g.student_id WHERE s.class_id <> e.class_id");
            $checks['Lignes de triche'] = $count("SELECT COUNT(*) FROM {$p}gne_grades WHERE is_cheating = 1");
        }
        if ($exists($p . 'logieval_secure_results')) {
            $checks['Résultats LogiExam sans copie'] = $count("SELECT COUNT(*) FROM {$p}logieval_secure_results r LEFT JOIN {$p}logieval_secure_attempts a ON a.id = r.attempt_id WHERE a.id IS NULL");
            $checks['Copies arrêtées pour sécurité'] = $count("SELECT COUNT(*) FROM {$p}logieval_secure_attempts WHERE status = 'security_terminated'");
            $checks['Évaluations LogiExam en cours (ouvertes maintenant)'] = $count($wpdb->prepare("SELECT COUNT(*) FROM {$p}logieval_secure_evaluations WHERE status = 'published' AND (opens_at IS NULL OR opens_at <= %s) AND (closes_at IS NULL OR closes_at >= %s)", current_time('mysql'), current_time('mysql')));
        }
        if ($exists($p . 'gca_students')) {
            $checks['Apprentis sans classe existante'] = $count("SELECT COUNT(*) FROM {$p}gca_students s LEFT JOIN {$p}gca_classes c ON c.id = s.class_id WHERE c.id IS NULL");
        }
        $inv['contrôles'] = array_map(static fn($v) => $v === null ? '—' : (string) $v, $checks);

        $inv['leçons dont l’exclusion change avec la nouvelle règle'] = self::rule_changes();

        $crons = array();
        foreach (array('gne_missing_grade_reminder', 'gca_stale_task_reminder', 'gca_import_freshness_reminder', 'gca_weekly_followup_reminder', 'logipilot_sync_retry') as $hook) {
            $next = wp_next_scheduled($hook);
            $crons[$hook] = $next ? wp_date('d.m.Y H:i', $next) : 'non planifié';
        }
        $inv['tâches planifiées'] = $crons;

        $roles = array();
        foreach (wp_roles()->roles as $key => $role) {
            $legacy = array_intersect(array('gne_manage', 'gca_manage', 'psgpt_access', 'logieval_secure_teacher'), array_keys(array_filter((array) $role['capabilities'])));
            $users = count(get_users(array('role' => $key, 'fields' => 'ID')));
            $roles[$key . ' (' . $role['name'] . ')'] = $users . ' utilisateur(s)' . ($legacy ? ' · anciens droits : ' . implode(', ', $legacy) : '');
        }
        $inv['rôles'] = $roles;

        return $inv;
    }

    /** Compare la règle d'exclusion 3.8.9 et la règle corrigée sur les titres réels. */
    public static function rule_changes(): array
    {
        if (!class_exists('LogiProgress_Sensei_Group_Progress_Tracker')) {
            return array('—' => 'LogiProgress non chargé');
        }
        $old = static function (string $title): bool {
            $title = remove_accents(strtolower(wp_strip_all_tags($title)));
            $title = str_replace('&', ' et ', $title);
            $title = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $title)));
            foreach (array('objectif', 'objectifs', 'mathematiques solutions', 'mathematique solution', 'podcast', 'evaluation formative mathematiques telechargeables', 'evaluation formative mathematique telechargeable', 'evaluation formative et mathematiques telechargeables', 'evaluation formative et mathematique telechargeable') as $term) {
                if (strpos($title, $term) !== false) {
                    return true;
                }
            }
            return false;
        };
        $changes = array();
        foreach (get_posts(array('post_type' => 'lesson', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'all')) as $lesson) {
            $before = $old((string) $lesson->post_title);
            $after = LogiProgress_Sensei_Group_Progress_Tracker::api_lesson_excluded_title((string) $lesson->post_title);
            if ($before !== $after) {
                $changes['#' . $lesson->ID . ' ' . $lesson->post_title] = $after ? 'désormais exclue' : 'désormais incluse';
            }
        }

        return $changes ?: array('—' => 'aucune leçon concernée');
    }

    public static function render_page(): void
    {
        if (!current_user_can('lp_admin_modules')) {
            wp_die('Accès refusé.', 403);
        }
        echo '<div class="wrap logipilot-wrap"><h1>LogiOne · Diagnostic</h1><p>Inventaire en lecture seule (lot 0). À exporter avant toute bascule et à conserver avec la sauvegarde.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
        wp_nonce_field('logipilot_diagnostic_export');
        echo '<input type="hidden" name="action" value="logipilot_diagnostic_export"><button class="button">Exporter le diagnostic (JSON)</button></form>';

        foreach (self::inventory() as $section => $items) {
            echo '<h2>' . esc_html(ucfirst($section)) . '</h2><table class="widefat striped"><tbody>';
            foreach ($items as $label => $value) {
                echo '<tr><th>' . esc_html((string) $label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h2 id="files">Protection des fichiers privés</h2>';
        $file_test = get_transient('logipilot_file_test');
        if (is_array($file_test)) {
            echo '<table class="widefat striped"><tbody>';
            foreach ($file_test as $dir => $result) {
                echo '<tr><th>' . esc_html($dir) . '</th><td>' . esc_html($result) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('logipilot_file_test');
        echo '<input type="hidden" name="action" value="logipilot_file_test"><p><button class="button">Tester l’accès direct aux dossiers privés</button> <span class="description">Crée un fichier de test, tente de l’ouvrir sans connexion, puis le supprime.</span></p></form>';

        echo '<h2 id="conservation">Contrôle de conservation (avant / après)</h2>';
        $photos = get_option(self::OPTION_PHOTOS, array());
        $photos = is_array($photos) ? $photos : array();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('logipilot_conservation');
        echo '<input type="hidden" name="action" value="logipilot_conservation"><p><button class="button">Prendre une photographie maintenant</button></p></form>';
        if ($photos) {
            $keys = array_keys($photos);
            $reference_key = null;
            foreach ($keys as $key) {
                if (strpos($key, 'avant_activation') === 0) {
                    $reference_key = $key;
                }
            }
            $reference_key = $reference_key ?: $keys[0];
            echo '<p>Référence : <strong>' . esc_html($reference_key) . '</strong>, comparée à l’état actuel.</p><table class="widefat striped"><tbody>';
            foreach (self::compare($photos[$reference_key], self::photograph()) as [$label, $text, $level]) {
                echo '<tr class="logipilot-' . esc_attr($level) . '"><th>' . esc_html($label) . '</th><td>' . esc_html($text) . '</td></tr>';
            }
            echo '</tbody></table><p class="description">Photographies disponibles : ' . esc_html(implode(', ', $keys)) . '</p>';
        }
        echo '</div>';
    }

    public static function handle_file_test(): void
    {
        if (!current_user_can('lp_admin_modules')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_file_test');
        $uploads = wp_upload_dir(null, false);
        $results = array();
        // Dossiers d'origine et dossiers personnels de chaque enseignant (…-t12).
        $dirs = array('loginotes-documents', 'gestion-apprentis/private');
        foreach (array('loginotes-documents-t*', 'gestion-apprentis/private-t*') as $pattern) {
            foreach ((array) glob(trailingslashit((string) $uploads['basedir']) . $pattern, GLOB_ONLYDIR) as $found) {
                $dirs[] = ltrim(substr((string) $found, strlen(trailingslashit((string) $uploads['basedir']))), '/');
            }
        }
        foreach ($dirs as $dir) {
            $path = trailingslashit((string) $uploads['basedir']) . $dir;
            if (!is_dir($path)) {
                $results[$dir] = 'dossier absent';
                continue;
            }
            $name = 'logipilot-test-' . wp_generate_password(12, false) . '.txt';
            if (@file_put_contents($path . '/' . $name, 'test') === false) {
                $results[$dir] = 'écriture impossible : test non réalisé';
                continue;
            }
            $response = wp_remote_get(trailingslashit((string) $uploads['baseurl']) . $dir . '/' . $name, array('timeout' => 10, 'sslverify' => false));
            @unlink($path . '/' . $name);
            if (is_wp_error($response)) {
                $results[$dir] = 'test impossible : ' . $response->get_error_message();
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                $results[$dir] = $code === 200 ? 'NON PROTÉGÉ : le fichier est lisible sans connexion (HTTP 200)' : 'protégé (HTTP ' . $code . ')';
            }
        }
        set_transient('logipilot_file_test', $results, HOUR_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=logione-diagnostic#files'));
        exit;
    }

    public static function handle_export(): void
    {
        if (!current_user_can('lp_admin_modules')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_diagnostic_export');
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="logipilot-diagnostic-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode(array('inventaire' => self::inventory(), 'photographie' => self::photograph()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
