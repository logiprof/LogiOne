<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Espaces personnels des enseignants (LogiNotes, LogiClass et liaisons LogiOne).
 *
 * Chaque enseignant travaille dans son propre jeu de tables : ses classes, élèves, semestres,
 * évaluations, notes, réglages, modèles de messages et fichiers privés ne sont jamais visibles
 * ni modifiables par un autre enseignant. Une même classe suivie par deux enseignants existe
 * donc deux fois, séparément.
 *
 * - L'espace « principal » appartient à l'administrateur qui a installé LogiOne : il garde
 *   les tables historiques (wp_gne_*, wp_gca_*), donc toutes les données existantes, sans copie.
 * - Les autres enseignants reçoivent des tables wp_gne_t{ID}_*, wp_gca_t{ID}_*, créées
 *   automatiquement à leur première utilisation.
 * - Seuls les administrateurs peuvent afficher l'espace d'un autre enseignant (sélecteur).
 * - LogiExam reste organisé par auteur (chaque évaluation appartient à son créateur) ; ses
 *   résultats sont publiés dans l'espace LogiNotes de l'auteur de l'évaluation.
 */
final class LogiPilot_Spaces
{
    public const OPTION_PRINCIPAL = 'logipilot_space_principal';
    public const OPTION_REGISTRY = 'logipilot_spaces';
    public const META_VIEW = 'logipilot_space_view';
    public const TRANSIENT_REMINDERS = 'logipilot_space_reminders';

    /** Réglages LogiClass communs à tous les espaces (version du schéma). */
    private const SHARED_OPTIONS = array('gca_db_version');

    /** Tâches planifiées exécutées une fois par espace. */
    private const PER_SPACE_HOOKS = array('gne_missing_grade_reminder', 'gca_stale_task_reminder', 'gca_import_freshness_reminder', 'gca_weekly_followup_reminder', 'logipilot_sync_retry');

    private static array $stack = array();
    private static ?int $resolved = null;
    private static array $ensuring = array();

    public static function init(): void
    {
        add_action('init', array(__CLASS__, 'wrap_scheduled_hooks'), 0);
        add_action('admin_post_logipilot_set_space', array(__CLASS__, 'handle_set_space'));
        add_action('admin_post_logipilot_spaces', array(__CLASS__, 'handle_spaces_action'));
        add_action('admin_notices', array(__CLASS__, 'render_admin_bar'));
        add_filter('do_shortcode_tag', array(__CLASS__, 'prefix_shortcode'), 10, 2);
        foreach (array('admin_post_gne_save_settings', 'admin_post_gca_save_settings') as $hook) {
            add_action($hook, static function (): void {
                delete_transient(LogiPilot_Spaces::TRANSIENT_REMINDERS);
            }, 1);
        }
        add_action('set_current_user', static function (): void {
            self::$resolved = null;
        });
    }

    /* -------------------------------------------------------------------
     * Espace courant
     * ----------------------------------------------------------------- */

    public static function principal(): int
    {
        return (int) get_option(self::OPTION_PRINCIPAL, 0);
    }

    /** Fixe le propriétaire de l'espace principal s'il ne l'est pas encore (activation, mise à jour). */
    public static function ensure_principal(int $user_id = 0): void
    {
        if (self::principal() > 0) {
            return;
        }
        if ($user_id <= 0 && defined('LOGIPILOT_TEST_MODE') && class_exists('LogiPilot_Sandbox')) {
            // Version test : la personne qui a créé le bac à sable.
            $state = get_option(LogiPilot_Sandbox::STATE, array());
            $user_id = is_array($state) ? (int) ($state['created_by'] ?? 0) : 0;
        }
        if ($user_id <= 0) {
            // Bascule réelle : l'administrateur qui a activé LogiOne.
            $previous = get_option('logipilot_legacy_deactivated', array());
            $user_id = is_array($previous) ? (int) ($previous['by'] ?? 0) : 0;
        }
        if ($user_id <= 0 || !user_can($user_id, 'manage_options')) {
            $admins = get_users(array('role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID'));
            $user_id = $admins ? (int) $admins[0] : $user_id;
        }
        if ($user_id > 0) {
            update_option(self::OPTION_PRINCIPAL, $user_id, false);
            self::register($user_id);
        }
    }

    public static function is_admin_user(?int $user_id = null): bool
    {
        $user_id = $user_id ?? get_current_user_id();

        return $user_id > 0 && user_can($user_id, 'manage_options');
    }

    /** Identifiant (compte WordPress) du propriétaire de l'espace utilisé maintenant. */
    public static function current(): int
    {
        if (self::$stack) {
            return (int) end(self::$stack);
        }
        if (!did_action('init')) {
            return self::principal();
        }
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return self::principal();
        }
        $space = $user_id;
        if (self::is_admin_user($user_id)) {
            $view = (int) get_user_meta($user_id, self::META_VIEW, true);
            if ($view > 0 && self::exists($view)) {
                $space = $view;
            }
        }
        self::$resolved = $space;
        if (self::may_use_modules($space)) {
            self::ensure($space);
        }

        return $space;
    }

    /** Fragment ajouté au nom des tables : '' pour l'espace principal, « t12_ » pour l'enseignant n° 12. */
    public static function infix(?int $space = null): string
    {
        $space = $space ?? self::current();
        $principal = self::principal();

        return ($space <= 0 || $space === $principal || $principal <= 0) ? '' : 't' . $space . '_';
    }

    /** Suffixe des dossiers privés : '' pour l'espace principal, « -t12 » sinon. */
    public static function dir_suffix(?int $space = null): string
    {
        $infix = self::infix($space);

        return $infix === '' ? '' : '-' . rtrim($infix, '_');
    }

    /** Nom de table LogiNotes de l'espace courant. */
    public static function notes_table(string $name, ?int $space = null): string
    {
        global $wpdb;

        return $wpdb->prefix . 'gne_' . self::infix($space) . $name;
    }

    /** Nom d'un réglage LogiClass (gca_settings, modèles…) dans l'espace courant. */
    public static function option(string $name): string
    {
        foreach (self::SHARED_OPTIONS as $shared) {
            if (substr($name, -strlen($shared)) === $shared) {
                return $name;
            }
        }
        $infix = self::infix();
        if ($infix === '') {
            return $name;
        }
        $marker = 'gca' . '_'; // écrit en deux morceaux : le marqueur doit rester identique dans la version test
        $pos = strpos($name, $marker);

        return $pos === false ? $name : substr($name, 0, $pos + 4) . $infix . substr($name, $pos + 4);
    }

    public static function push(int $space): void
    {
        self::$stack[] = $space;
        self::ensure($space);
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    /** Exécute $callback dans l'espace $space puis revient à l'espace précédent. */
    public static function run(int $space, callable $callback)
    {
        self::push($space);
        try {
            return $callback();
        } finally {
            self::pop();
        }
    }

    /** Espace de l'auteur d'une évaluation LogiExam (là où vivent sa fiche LogiNotes et ses relevés). */
    public static function exam_space(int $exam_evaluation_id): int
    {
        global $wpdb;

        static $cache = array();
        if (!isset($cache[$exam_evaluation_id])) {
            $owner = 0;
            if (class_exists('LogiEval_Secure_Database')) {
                $t = LogiEval_Secure_Database::tables();
                $owner = (int) $wpdb->get_var($wpdb->prepare("SELECT created_by FROM {$t['evaluations']} WHERE id = %d", $exam_evaluation_id));
            }
            $cache[$exam_evaluation_id] = $owner > 0 ? $owner : self::principal();
        }

        return $cache[$exam_evaluation_id];
    }

    public static function run_for_exam(int $exam_evaluation_id, callable $callback)
    {
        return self::run(self::exam_space($exam_evaluation_id), $callback);
    }

    /* -------------------------------------------------------------------
     * Création des tables d'un espace
     * ----------------------------------------------------------------- */

    public static function registry(): array
    {
        $registry = get_option(self::OPTION_REGISTRY, array());

        return is_array($registry) ? $registry : array();
    }

    public static function exists(int $space): bool
    {
        return $space > 0 && ($space === self::principal() || isset(self::registry()[$space]));
    }

    private static function register(int $space): void
    {
        $registry = self::registry();
        if (!isset($registry[$space])) {
            $registry[$space] = array('created_at' => current_time('mysql'));
            update_option(self::OPTION_REGISTRY, $registry, false);
        }
    }

    private static function may_use_modules(int $user_id): bool
    {
        if ($user_id === self::principal()) {
            return true;
        }
        $user = get_userdata($user_id);

        return $user && class_exists('LogiPilot_Permissions')
            && (LogiPilot_Permissions::user_has($user, 'lp_notes_view') || LogiPilot_Permissions::user_has($user, 'lp_classe_view') || LogiPilot_Permissions::user_has($user, 'lp_exam_teach'));
    }

    /** Versions de schéma attendues : un changement relance la création (dbDelta) dans chaque espace. */
    private static function schema_signature(): string
    {
        return implode('|', array(
            defined('GNE_VERSION') ? GNE_VERSION : '-',
            defined('GCA_VERSION') ? GCA_VERSION : '-',
            class_exists('LogiPilot_Install') ? LogiPilot_Install::DB_VERSION : '-',
        ));
    }

    /** Crée (une seule fois) les tables et réglages par défaut de l'espace. Ne touche jamais l'espace principal. */
    public static function ensure(int $space): void
    {
        if ($space <= 0 || self::infix($space) === '' || isset(self::$ensuring[$space])) {
            return;
        }
        $registry = self::registry();
        $signature = self::schema_signature();
        if (($registry[$space]['schema'] ?? '') === $signature) {
            return;
        }

        self::$ensuring[$space] = true;
        self::$stack[] = $space;
        try {
            if (class_exists('GNE_Database')) {
                GNE_Database::create_tables();
                GNE_Database::add_default_settings();
            }
            if (class_exists('GCA_Database')) {
                GCA_Database::create_tables();
                GCA_Database::add_default_options();
                GCA_Database::ensure_private_dirs();
            }
            if (class_exists('LogiPilot_Install')) {
                LogiPilot_Install::create_space_tables();
            }
        } finally {
            array_pop(self::$stack);
            unset(self::$ensuring[$space]);
        }

        $registry = self::registry();
        $registry[$space] = array('created_at' => $registry[$space]['created_at'] ?? current_time('mysql'), 'schema' => $signature);
        update_option(self::OPTION_REGISTRY, $registry, false);
    }

    /** @return int[] tous les espaces, l'espace principal en premier */
    public static function all(): array
    {
        $spaces = array();
        if (self::principal() > 0) {
            $spaces[] = self::principal();
        }
        foreach (array_keys(self::registry()) as $space) {
            $space = (int) $space;
            if ($space > 0 && !in_array($space, $spaces, true)) {
                $spaces[] = $space;
            }
        }

        return $spaces;
    }

    public static function label(int $space): string
    {
        $user = get_userdata($space);
        $name = $user ? $user->display_name : ('Compte n° ' . $space);

        return $space === self::principal() ? $name . ' (données d’origine)' : $name;
    }

    /* -------------------------------------------------------------------
     * Tâches planifiées : une exécution par espace
     * ----------------------------------------------------------------- */

    public static function wrap_scheduled_hooks(): void
    {
        global $wp_filter;

        foreach (self::PER_SPACE_HOOKS as $base) {
            foreach (array($base, 'lpt_' . $base) as $hook) {
                if (empty($wp_filter[$hook]) || !($wp_filter[$hook] instanceof WP_Hook) || !$wp_filter[$hook]->callbacks) {
                    continue;
                }
                $callbacks = $wp_filter[$hook]->callbacks;
                remove_all_actions($hook);
                add_action($hook, static function (...$args) use ($callbacks): void {
                    foreach (LogiPilot_Spaces::all() as $space) {
                        LogiPilot_Spaces::run($space, static function () use ($callbacks, $args): void {
                            ksort($callbacks);
                            foreach ($callbacks as $priority_callbacks) {
                                foreach ($priority_callbacks as $callback) {
                                    call_user_func_array($callback['function'], array_slice($args, 0, (int) $callback['accepted_args']));
                                }
                            }
                        });
                    }
                }, 10, 10);
            }
        }
    }

    /**
     * Réglages de rappel « fusionnés » : un rappel est planifié si au moins un espace l'a activé.
     * Sans cela, un enseignant qui désactive ses rappels supprimerait ceux de ses collègues.
     */
    public static function reminder_settings(string $module, callable $reader, array $flags): array
    {
        $cache = get_transient(self::TRANSIENT_REMINDERS);
        $cache = is_array($cache) ? $cache : array();
        if (!isset($cache[$module])) {
            $merged = null;
            foreach (self::all() ?: array(0) as $space) {
                $settings = self::run($space, $reader);
                $settings = is_array($settings) ? $settings : array();
                if ($merged === null) {
                    $merged = $settings;
                }
                foreach ($flags as $flag) {
                    if (!empty($settings[$flag]) && empty($merged[$flag])) {
                        $merged[$flag] = $settings[$flag];
                    }
                }
            }
            $cache[$module] = $merged ?? array();
            set_transient(self::TRANSIENT_REMINDERS, $cache, 5 * MINUTE_IN_SECONDS);
        }

        return $cache[$module];
    }

    /* -------------------------------------------------------------------
     * Sélecteur d'espace (administrateurs seulement)
     * ----------------------------------------------------------------- */

    private static function on_space_screen(): bool
    {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));

        return (bool) preg_match('/(^|_)(gne|gca)_|logione-(links|sync)$/', $page);
    }

    public static function render_admin_bar(): void
    {
        if (!is_admin() || !self::on_space_screen()) {
            return;
        }
        echo self::bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    public static function prefix_shortcode($output, $tag)
    {
        if (!is_string($output) || !preg_match('/(loginotes|gestion_notes|gestion_apprentis)$/', (string) $tag)) {
            return $output;
        }

        return self::bar_html() . $output;
    }

    public static function bar_html(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }
        $current = self::current();
        $me = get_current_user_id();
        if (!self::is_admin_user()) {
            return '';
        }
        $html = '<div class="logipilot-space-bar' . ($current !== $me ? ' is-other' : '') . '"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="logipilot_set_space">' . wp_nonce_field('logipilot_set_space', '_wpnonce', true, false);
        $html .= '<label><strong>Données affichées :</strong> <select name="space" onchange="this.form.submit()">';
        $choices = array_unique(array_merge(array($me), self::all()));
        foreach ($choices as $space) {
            $label = $space === $me ? 'Mes données' . ($space === self::principal() ? ' (données d’origine)' : '') : 'Espace de ' . self::label($space);
            $html .= '<option value="' . (int) $space . '"' . selected($current, $space, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select></label> <button class="button">Afficher</button>';
        if ($current !== $me) {
            $html .= ' <span class="logipilot-warning">Vous consultez l’espace d’un autre enseignant : vos modifications s’y appliquent.</span>';
        }
        $html .= '</form></div>';

        return $html;
    }

    public static function handle_set_space(): void
    {
        check_admin_referer('logipilot_set_space');
        $me = get_current_user_id();
        $space = absint($_POST['space'] ?? 0);
        if (!self::is_admin_user()) {
            wp_die('Seuls les administrateurs peuvent afficher l’espace d’un autre enseignant.', 'Accès refusé', array('response' => 403));
        }
        if ($space === $me || !self::exists($space)) {
            delete_user_meta($me, self::META_VIEW);
        } else {
            update_user_meta($me, self::META_VIEW, $space);
        }
        LogiPilot_Log::add('space_viewed', 'space', $space);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /* -------------------------------------------------------------------
     * Écran « Espaces enseignants » : vue d'ensemble, copie, transfert
     * ----------------------------------------------------------------- */

    private static function count(string $table): int
    {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    public static function stats(int $space): array
    {
        global $wpdb;

        $infix = self::infix($space);

        return array(
            'notes_classes' => self::count($wpdb->prefix . 'gne_' . $infix . 'classes'),
            'notes_students' => self::count($wpdb->prefix . 'gne_' . $infix . 'students'),
            'notes_evaluations' => self::count($wpdb->prefix . 'gne_' . $infix . 'evaluations'),
            'classe_classes' => self::count($wpdb->prefix . 'gca_' . $infix . 'classes'),
            'classe_students' => self::count($wpdb->prefix . 'gca_' . $infix . 'students'),
        );
    }

    public static function render_page(): void
    {
        if (!self::is_admin_user()) {
            wp_die('Accès réservé aux administrateurs.', 403);
        }
        global $wpdb;

        $notice = sanitize_key(wp_unslash($_GET['done'] ?? ''));
        $messages = array(
            'copied' => 'Classe copiée dans l’espace de l’enseignant. Ses notes et évaluations ne sont pas copiées.',
            'transferred' => 'Les données d’origine appartiennent maintenant à l’enseignant choisi.',
            'refused' => 'Opération refusée : l’enseignant choisi a déjà des données dans son propre espace.',
            'missing' => 'Choisissez une classe et un enseignant.',
            'master_only' => 'Une classe LogiClass n’a qu’un maître de classe : elle ne peut pas être copiée chez un autre enseignant.',
        );
        echo '<div class="wrap logipilot-wrap"><h1>LogiOne · Espaces enseignants</h1>';
        if (isset($messages[$notice])) {
            echo '<div class="notice notice-' . (in_array($notice, array('refused', 'missing', 'master_only'), true) ? 'error' : 'success') . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
        echo '<p>Dans LogiNotes et LogiClass, <strong>chaque enseignant a ses propres données</strong> : ses classes, ses élèves, ses évaluations, ses notes, ses réglages et ses fichiers. Un enseignant ne voit jamais les données d’un collègue et ne peut pas les modifier. Dans LogiNotes, si deux enseignants suivent la même classe, chacun a sa propre copie. <strong>Dans LogiClass, une classe n’a qu’un maître de classe</strong> : lui seul la voit, la modifie et lance les actions ; un autre enseignant ne peut ni la créer ni l’importer chez lui.</p>';
        echo '<p>Dans LogiExam, chaque évaluation appartient à celui qui l’a créée ; ses résultats sont publiés dans le LogiNotes de cet enseignant. Seuls les administrateurs peuvent afficher l’espace d’un autre enseignant, avec le sélecteur « Données affichées » en haut de LogiNotes et de LogiClass.</p>';

        echo '<h2>Espaces existants</h2><table class="widefat striped"><thead><tr><th>Enseignant</th><th>LogiNotes : classes · élèves · évaluations</th><th>LogiClass : classes · élèves</th></tr></thead><tbody>';
        foreach (self::all() as $space) {
            $s = self::stats($space);
            printf('<tr><td>%s</td><td>%d · %d · %d</td><td>%d · %d</td></tr>', esc_html(self::label($space)), $s['notes_classes'], $s['notes_students'], $s['notes_evaluations'], $s['classe_classes'], $s['classe_students']);
        }
        echo '</tbody></table><p class="description">L’espace d’un enseignant est créé automatiquement, vide, la première fois qu’il ouvre LogiNotes, LogiClass ou LogiExam.</p>';

        $teachers = array_filter(get_users(array('orderby' => 'display_name')), static fn($user) => self::may_use_modules((int) $user->ID) || self::is_admin_user((int) $user->ID));
        $source = self::current();
        $notes_classes = $wpdb->get_results('SELECT id, name, school_year FROM ' . self::notes_table('classes', $source) . ' ORDER BY school_year DESC, name ASC') ?: array();
        $gca_table = $wpdb->prefix . 'gca_' . self::infix($source) . 'classes';
        $gca_classes = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gca_table)) === $gca_table ? ($wpdb->get_results("SELECT * FROM `{$gca_table}` ORDER BY id DESC") ?: array()) : array();
        $options = static function (array $users, int $exclude) {
            $html = '<option value="0">— Choisir —</option>';
            foreach ($users as $user) {
                if ((int) $user->ID !== $exclude) {
                    $html .= '<option value="' . (int) $user->ID . '">' . esc_html($user->display_name . ' (' . $user->user_login . ')') . '</option>';
                }
            }
            return $html;
        };

        echo '<h2 id="maitres">Maîtres de classe (LogiClass)</h2>';
        $registry = self::classe_registry();
        $conflicts = array_filter($registry, static fn($c) => count($c['spaces']) > 1);
        if ($conflicts) {
            echo '<div class="notice notice-warning inline"><p><strong>' . count($conflicts) . ' classe(s) existent chez plusieurs enseignants</strong> (créées avant la règle du maître de classe unique). Chacun ne voit que la sienne. Décidez qui en est le maître de classe ; l’autre enseignant peut supprimer sa classe dans LogiClass (ou demandez-moi un transfert).</p></div>';
        }
        if ($registry) {
            echo '<table class="widefat striped"><thead><tr><th>Classe</th><th>Année scolaire</th><th>Maître de classe</th></tr></thead><tbody>';
            foreach ($registry as $c) {
                $who = array_map(static fn($sp) => self::label($sp), $c['spaces']);
                printf('<tr%s><td>%s</td><td>%s</td><td>%s</td></tr>', count($c['spaces']) > 1 ? ' class="logipilot-error"' : '', esc_html($c['name']), esc_html($c['school_year']), esc_html(implode(' · ', $who)) . (count($c['spaces']) > 1 ? ' <strong>— en double</strong>' : ''));
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="description">Aucune classe LogiClass pour l’instant.</p>';
        }

        echo '<h2>Donner une classe LogiNotes à un collègue</h2><p>Copie une classe LogiNotes de l’espace affiché (' . esc_html(self::label($source)) . ') et ses élèves actifs dans l’espace d’un autre enseignant. L’original ne change pas. Les évaluations et les notes ne sont jamais copiées : chaque enseignant saisit les siennes.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="logipilot_spaces"><input type="hidden" name="op" value="copy_class">' . wp_nonce_field('logipilot_spaces', '_wpnonce', true, false);
        echo '<p><label>Classe <select name="class_ref"><option value="">— Choisir —</option>';
        foreach ($notes_classes as $class) {
            echo '<option value="notes:' . (int) $class->id . '">LogiNotes · ' . esc_html($class->name . ' · ' . $class->school_year) . '</option>';
        }
        echo '</select></label> <label>vers <select name="target">' . $options($teachers, $source) . '</select></label> <button class="button button-primary">Copier la classe</button></p></form>';

        $principal = self::principal();
        echo '<h2>Données d’origine</h2><p>Les données qui existaient avant LogiOne appartiennent à <strong>' . esc_html(self::label($principal)) . '</strong>. Vous pouvez les confier à un autre enseignant, à condition que son propre espace soit encore vide. Rien n’est copié ni supprimé : seul le propriétaire change.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="logipilot_spaces"><input type="hidden" name="op" value="transfer_principal">' . wp_nonce_field('logipilot_spaces', '_wpnonce', true, false);
        echo '<p><label>Nouveau propriétaire <select name="target">' . $options($teachers, $principal) . '</select></label> <button class="button">Transférer les données d’origine</button></p></form></div>';
    }

    public static function handle_spaces_action(): void
    {
        if (!self::is_admin_user()) {
            wp_die('Accès réservé aux administrateurs.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_spaces');
        $op = sanitize_key(wp_unslash($_POST['op'] ?? ''));
        $target = absint($_POST['target'] ?? 0);
        $done = 'missing';

        if ($op === 'copy_class' && $target > 0 && get_userdata($target)) {
            [$module, $class_id] = array_pad(explode(':', sanitize_text_field(wp_unslash($_POST['class_ref'] ?? ''))), 2, 0);
            $source = self::current();
            if ($module === 'classe') {
                // LogiClass : une classe n'a qu'un maître de classe ; la copier en créerait un second.
                $done = 'master_only';
            } elseif ((int) $class_id > 0 && $target !== $source) {
                $copied = self::copy_notes_class($source, $target, (int) $class_id);
                $done = $copied ? 'copied' : 'missing';
                LogiPilot_Log::add('space_class_copied', $module === 'classe' ? 'gca_class' : 'notes_class', (int) $class_id, array('from' => $source, 'to' => $target, 'new' => $copied));
            }
        } elseif ($op === 'transfer_principal' && $target > 0 && get_userdata($target)) {
            $s = self::exists($target) ? self::stats($target) : array();
            if (array_sum($s) > 0) {
                $done = 'refused';
            } else {
                $previous = self::principal();
                // Les tables vides éventuelles du nouveau propriétaire restent en place, inutilisées.
                $registry = self::registry();
                unset($registry[$target]);
                update_option(self::OPTION_REGISTRY, $registry, false);
                update_option(self::OPTION_PRINCIPAL, $target, false);
                self::$resolved = null;
                LogiPilot_Log::add('space_principal_transferred', 'space', $target, array('from' => $previous));
                $done = 'transferred';
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=logione-spaces&done=' . $done));
        exit;
    }

    /* -------------------------------------------------------------------
     * LogiClass : un seul maître de classe par classe (nom + année scolaire)
     * ----------------------------------------------------------------- */

    /** Nom de classe comparable : sans accents, minuscules, espaces simples. */
    public static function class_key(string $name, string $school_year): string
    {
        $norm = static fn(string $v): string => trim((string) preg_replace('/\s+/', ' ', strtolower(remove_accents($v))));

        return $norm($name) . '|' . $norm($school_year);
    }

    /** @return array<string, array{name:string, school_year:string, status:string, spaces:int[]}> toutes les classes LogiClass, par classe */
    public static function classe_registry(): array
    {
        global $wpdb;

        $out = array();
        foreach (self::all() as $space) {
            $table = $wpdb->prefix . 'gca_' . self::infix($space) . 'classes';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                continue;
            }
            foreach ((array) $wpdb->get_results("SELECT name, school_year, status FROM `{$table}`") as $row) {
                $key = self::class_key((string) $row->name, (string) $row->school_year);
                if (!isset($out[$key])) {
                    $out[$key] = array('name' => (string) $row->name, 'school_year' => (string) $row->school_year, 'status' => (string) $row->status, 'spaces' => array());
                }
                if (!in_array($space, $out[$key]['spaces'], true)) {
                    $out[$key]['spaces'][] = $space;
                }
            }
        }
        uasort($out, static fn($a, $b) => array($b['school_year'], $a['name']) <=> array($a['school_year'], $b['name']));

        return $out;
    }

    /**
     * Maître de classe d'une autre classe : l'enseignant (espace) qui a déjà cette classe dans LogiClass,
     * autre que $space ; 0 si personne d'autre. Une classe n'a qu'un maître de classe.
     */
    public static function classe_master_elsewhere(string $name, string $school_year, ?int $space = null): int
    {
        global $wpdb;

        $space = $space ?? self::current();
        $key = self::class_key($name, $school_year);
        foreach (self::all() as $other) {
            if ($other === $space) {
                continue;
            }
            $table = $wpdb->prefix . 'gca_' . self::infix($other) . 'classes';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                continue;
            }
            foreach ((array) $wpdb->get_results("SELECT name, school_year FROM `{$table}`") as $row) {
                if (self::class_key((string) $row->name, (string) $row->school_year) === $key) {
                    return $other;
                }
            }
        }

        return 0;
    }

    private static function copy_notes_class(int $source, int $target, int $class_id): int
    {
        global $wpdb;

        $class = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::notes_table('classes', $source) . ' WHERE id = %d', $class_id), ARRAY_A);
        if (!$class) {
            return 0;
        }
        $students = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::notes_table('students', $source) . " WHERE class_id = %d AND status = 'active'", $class_id), ARRAY_A);
        self::ensure($target);
        $now = current_time('mysql');
        $classes_table = self::notes_table('classes', $target);
        $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$classes_table} WHERE name = %s AND school_year = %s", $class['name'], $class['school_year']));
        if ($existing) {
            $new_id = $existing;
        } else {
            unset($class['id']);
            $wpdb->insert($classes_table, array_merge($class, array('created_at' => $now, 'updated_at' => $now)));
            $new_id = (int) $wpdb->insert_id;
        }
        foreach ($students as $student) {
            unset($student['id']);
            $wpdb->insert(self::notes_table('students', $target), array_merge($student, array('class_id' => $new_id, 'created_at' => $now, 'updated_at' => $now)));
        }

        return $new_id;
    }

    private static function copy_gca_class(int $source, int $target, int $class_id): int
    {
        global $wpdb;

        $from = $wpdb->prefix . 'gca_' . self::infix($source);
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$from}classes WHERE id = %d", $class_id), ARRAY_A);
        if (!$class) {
            return 0;
        }
        $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$from}students WHERE class_id = %d", $class_id), ARRAY_A);
        self::ensure($target);
        $to = $wpdb->prefix . 'gca_' . self::infix($target);
        $now = current_time('mysql');
        $new_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$to}classes WHERE name = %s AND school_year = %s", $class['name'], $class['school_year']));
        if (!$new_id) {
            unset($class['id']);
            $wpdb->insert($to . 'classes', array_merge($class, array('created_at' => $now, 'updated_at' => $now)));
            $new_id = (int) $wpdb->insert_id;
        }
        if ($new_id <= 0) {
            return 0;
        }
        foreach ($students as $student) {
            if (!empty($student['archived_at'])) {
                continue;
            }
            unset($student['id']);
            $student['class_id'] = $new_id;
            // Les fichiers privés (photos, PDF) restent dans l'espace d'origine.
            if (array_key_exists('photo_path', $student)) {
                $student['photo_path'] = null;
            }
            $wpdb->suppress_errors(true);
            $wpdb->insert($to . 'students', $student);
            $wpdb->suppress_errors(false);
        }

        return $new_id;
    }
}
