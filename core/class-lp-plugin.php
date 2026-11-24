<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Socle LogiOne : registre des modules, chargement conditionnel, activation et désactivation.
 *
 * Garanties : aucune table n'est supprimée ni vidée, aucune note n'est recalculée et
 * aucune option de version des anciens plugins n'est modifiée (retour arrière possible).
 */
final class LogiPilot_Plugin
{
    public const OPTION_MODULES = 'logipilot_modules';
    public const OPTION_SERVICES = 'logipilot_services';

    /** Modules de la suite. `legacy` = fichier principal de l'ancien plugin autonome. */
    public const MODULES = array(
        'notes' => array(
            'label' => 'LogiNotes',
            'file' => 'modules/notes/module.php',
            'legacy' => 'loginotes.php',
            'class' => 'GNE_App',
        ),
        'progress' => array(
            'label' => 'LogiProgress',
            'file' => 'modules/progress/module.php',
            'legacy' => 'pickles-sensei-group-progress-tracker.php',
            'class' => 'LogiProgress_Sensei_Group_Progress_Tracker',
        ),
        'exam' => array(
            'label' => 'LogiExam',
            'file' => 'modules/exam/module.php',
            'legacy' => 'logieval-secure.php',
            'class' => 'LogiEval_Secure_Evaluations',
        ),
        'classe' => array(
            'label' => 'LogiClass',
            'file' => 'modules/classe/module.php',
            'legacy' => 'gestion-classes-apprentis.php',
            'class' => 'GCA_App',
        ),
        'live' => array(
            'label' => 'LogiLive',
            'file' => 'modules/live/module.php',
            'legacy' => 'logilive.php',
            'class' => 'LogiOne_LogiLive',
        ),
        'eval' => array(
            'label' => 'LogiEval',
            'file' => 'modules/eval/module.php',
            'legacy' => 'logieval.php',
            'class' => 'LOGIEVAL_Database',
        ),
        'planning' => array(
            'label' => 'LogiPlanning',
            'file' => 'modules/planning/module.php',
            'legacy' => 'logiplanning-lms.php',
            'class' => 'LogiOne_Planning',
        ),
        'projet' => array(
            'label' => 'LogiProjet',
            'file' => 'modules/projet/module.php',
            'legacy' => 'logiprojet.php',
            'class' => 'LogiProjet_App',
        ),
        'tiles' => array(
            'label' => 'Tuiles de contenu',
            'file' => 'modules/tiles/module.php',
            'legacy' => '',
            'class' => 'LogiPilot_Tiles',
        ),
    );

    /** Services d'intégration : chacun peut être coupé indépendamment de l'écran du module. */
    public const SERVICES = array(
        'progress_for_evaluations' => 'Relevés de progression pour les évaluations (Notes et Exam)',
        'exam_to_notes' => 'Création des fiches LogiNotes et publication des résultats LogiExam',
    );

    private static array $loaded = array();
    private static array $conflicts = array();

    public static function boot(): void
    {
        // En mode test, les données d'origine sont copiées AVANT que les modules ne créent leurs tables
        // (sinon, à l'activation, LogiEval créerait des copies vides).
        if (defined('LOGIPILOT_TEST_MODE') && class_exists('LogiPilot_Sandbox')) {
            LogiPilot_Sandbox::prepare();
        }
        // En mode test, les modules sont renommés : ils cohabitent avec les plugins actuels.
        $legacy = defined('LOGIPILOT_TEST_MODE') ? array() : self::legacy_active_plugins();
        $enabled = self::enabled_modules();

        foreach (self::MODULES as $key => $module) {
            if (isset($legacy[$key])) {
                self::$conflicts[$key] = $legacy[$key];
                continue;
            }

            if (class_exists($module['class'])) {
                self::$conflicts[$key] = 'classe déjà chargée';
                continue;
            }

            if (empty($enabled[$key])) {
                if ($key === 'progress') {
                    // Les dates de première validation Sensei continuent d'être mémorisées (ARC-03).
                    define('LOGIPILOT_PROGRESS_HOOKS_ONLY', true);
                    require_once LOGIPILOT_DIR . $module['file'];
                    self::$loaded['progress_hooks'] = true;
                }
                continue;
            }

            require_once LOGIPILOT_DIR . $module['file'];
            self::$loaded[$key] = true;
        }

        LogiPilot_Permissions::init();
        LogiPilot_Spaces::init();
        LogiPilot_Log::init();
        LogiPilot_Admin::init();
        LogiPilot_Portal::init();
        LogiPilot_Layout::init();
        LogiPilot_Links::init();
        LogiPilot_Snapshots::init();
        LogiPilot_Publisher::init();
        LogiPilot_Diagnostic::init();
        if (defined('LOGIPILOT_TEST_MODE') && class_exists('LogiPilot_Sandbox')) {
            LogiPilot_Sandbox::init();
        }

        add_action('plugins_loaded', array('LogiPilot_Install', 'maybe_upgrade'), 5);
        add_action('pre_uninstall_plugin', array(__CLASS__, 'protect_legacy_data_on_uninstall'), 1, 1);

        if (self::$conflicts) {
            add_action('admin_notices', array(__CLASS__, 'render_conflict_notice'));
        }
    }

    public static function is_loaded(string $module): bool
    {
        return !empty(self::$loaded[$module]);
    }

    /** Le service Progress est utilisable même si l'écran LogiProgress est désactivé. */
    public static function progress_service_available(): bool
    {
        return (self::is_loaded('progress') || !empty(self::$loaded['progress_hooks'])) && self::service_enabled('progress_for_evaluations');
    }

    public static function service_enabled(string $service): bool
    {
        $services = get_option(self::OPTION_SERVICES, array());
        if (!is_array($services) || !array_key_exists($service, $services)) {
            return true;
        }

        return !empty($services[$service]);
    }

    public static function enabled_modules(): array
    {
        $stored = get_option(self::OPTION_MODULES, array());
        $stored = is_array($stored) ? $stored : array();
        $enabled = array();
        foreach (array_keys(self::MODULES) as $key) {
            $enabled[$key] = array_key_exists($key, $stored) ? !empty($stored[$key]) : true;
        }

        return $enabled;
    }

    public static function conflicts(): array
    {
        return self::$conflicts;
    }

    /** Anciens plugins autonomes encore actifs, repérés par le nom de leur fichier principal. */
    public static function legacy_active_plugins(): array
    {
        $active = (array) get_option('active_plugins', array());
        if (is_multisite()) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', array())));
        }

        $found = array();
        foreach ($active as $plugin) {
            foreach (self::MODULES as $key => $module) {
                if (basename((string) $plugin) === $module['legacy'] && strpos((string) $plugin, 'logipilot/') !== 0) {
                    $found[$key] = (string) $plugin;
                }
            }
        }

        return $found;
    }

    /**
     * LogiOne 1.51.0 : supprimer un ancien plugin autonome (LogiProjet, LogiNotes…) depuis la page Extensions
     * lancerait son uninstall.php, qui efface ses tables et ses fichiers — c'est-à-dire les données que LogiOne
     * utilise maintenant. Tant que LogiOne est actif, ce nettoyage est neutralisé : seul le dossier de l'ancien
     * plugin est supprimé, les données restent.
     */
    public static function protect_legacy_data_on_uninstall($plugin): void
    {
        $plugin = (string) $plugin;
        if (strpos($plugin, 'logipilot/') === 0) {
            return;
        }
        $is_legacy = false;
        foreach (self::MODULES as $module) {
            if ($module['legacy'] !== '' && basename($plugin) === $module['legacy']) {
                $is_legacy = true;
                break;
            }
        }
        if (!$is_legacy) {
            return;
        }
        // 1. uninstall.php de l'ancien plugin : renommé juste avant que WordPress ne l'exécute.
        $file = WP_PLUGIN_DIR . '/' . dirname($plugin) . '/uninstall.php';
        if (dirname($plugin) !== '.' && file_exists($file)) {
            @rename($file, $file . '.desactive-par-logione'); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            if (file_exists($file)) {
                wp_die(esc_html('LogiOne : suppression interrompue pour protéger les données de ' . $plugin . '. Désactivez simplement l’ancien plugin (ne le supprimez pas), ou supprimez son dossier par FTP.'));
            }
        }
        // 2. Crochet de désinstallation éventuellement enregistré (register_uninstall_hook) : retiré.
        $hooks = get_option('uninstall_plugins', array());
        if (is_array($hooks) && isset($hooks[$plugin])) {
            unset($hooks[$plugin]);
            update_option('uninstall_plugins', $hooks);
        }
        LogiPilot_Log::add('legacy_uninstall_protected', 'suite', 0, array('plugin' => $plugin));
    }

    public static function activate(): void
    {
        if (defined('LOGIPILOT_TEST_MODE') && class_exists('LogiPilot_Sandbox')) {
            // Mode test : les plugins actuels restent actifs ; leurs données sont seulement copiées.
            LogiPilot_Sandbox::create();
            // Les données d'origine (copiées) appartiennent à l'administrateur qui active la version test.
            LogiPilot_Spaces::ensure_principal(get_current_user_id());
            LogiPilot_Install::install();
            self::ensure_module_tables();
            LogiPilot_Permissions::initialize_from_legacy();
            LogiPilot_Log::add('logipilot_test_activated', 'suite', 0);
            return;
        }

        // 1. Désactiver les anciens plugins autonomes : leurs données restent intactes.
        $legacy = self::legacy_active_plugins();
        if ($legacy) {
            if (!function_exists('deactivate_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            deactivate_plugins(array_values($legacy), false);
            update_option('logipilot_legacy_deactivated', array(
                'plugins' => array_values($legacy),
                'at' => current_time('mysql'),
                'by' => get_current_user_id(),
            ), false);
        }

        // 2. Photographie de conservation AVANT toute écriture de LogiOne.
        LogiPilot_Diagnostic::record_conservation('avant_activation');

        // 3. Les données existantes appartiennent à l'administrateur qui active LogiOne (espace principal).
        LogiPilot_Spaces::ensure_principal(get_current_user_id());

        // 4. Tables propres à LogiOne (ajouts uniquement).
        LogiPilot_Install::install();

        // 5. Tables des modules : créées seulement si elles n'existent pas encore (nouvelle installation).
        self::ensure_module_tables();

        // 6. Matrice de permissions initiale reprise des droits existants.
        LogiPilot_Permissions::initialize_from_legacy();

        LogiPilot_Log::add('logipilot_activated', 'suite', 0, array('legacy_deactivated' => array_values($legacy)));
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function ensure_module_tables(): void
    {
        LogiPilot_Spaces::run(LogiPilot_Spaces::principal(), array(__CLASS__, 'ensure_module_tables_here'));
    }

    public static function ensure_module_tables_here(): void
    {
        global $wpdb;

        if (class_exists('GNE_Database') && !self::table_exists($wpdb->prefix . 'gne_evaluations')) {
            GNE_Database::activate();
        }

        if (class_exists('LogiEval_Secure_Database')) {
            LogiEval_Secure_Database::maybe_upgrade();
        }

        if (class_exists('LogiPilot_Tiles')) {
            // Première installation uniquement : les tuiles existantes ne sont jamais remplacées.
            LogiPilot_Tiles::activate();
        }

        if (class_exists('GCA_Database')) {
            if (!self::table_exists($wpdb->prefix . 'gca_students')) {
                GCA_Database::activate();
            } else {
                GCA_Database::ensure_private_dirs();
            }
        }
    }

    public static function deactivate(): void
    {
        // Données conservées. Seules les tâches planifiées sont retirées (comme les anciens plugins).
        foreach (array('gne_missing_grade_reminder', 'gca_weekly_followup_reminder', 'gca_stale_task_reminder', 'gca_import_freshness_reminder', 'logipilot_sync_retry') as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    public static function render_conflict_notice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $labels = array();
        foreach (self::$conflicts as $key => $plugin) {
            $labels[] = self::MODULES[$key]['label'] . ' (' . $plugin . ')';
        }

        echo '<div class="notice notice-warning"><p><strong>LogiOne :</strong> ';
        echo esc_html('les modules suivants ne sont pas chargés car l’ancien plugin autonome est encore actif : ' . implode(', ', $labels) . '. Désactivez l’ancien plugin pour utiliser la version intégrée. Aucune donnée n’est perdue.');
        echo '</p></div>';
    }
}
