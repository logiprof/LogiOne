<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permissions centrales de LogiOne (lot 1).
 *
 * - Les autorisations sont enregistrées par identifiant de rôle, jamais déduites du libellé.
 * - Les capacités lp_* ne sont pas écrites dans les rôles : elles sont calculées à chaque
 *   vérification depuis la matrice, une révocation prend donc effet immédiatement.
 * - Le rôle administrator garde toujours toutes les permissions (accès de secours, ACL-02).
 * - Des droits supplémentaires peuvent être accordés à un utilisateur précis, en plus de son rôle.
 * - Le périmètre des données ne dépend plus des droits : chaque enseignant travaille dans son
 *   propre espace LogiNotes / LogiClass (voir LogiPilot_Spaces).
 */
final class LogiPilot_Permissions
{
    public const OPTION_MATRIX = 'logipilot_permissions';
    public const OPTION_SCOPES = 'logipilot_scopes';
    public const OPTION_ASSIGNMENTS = 'logipilot_class_assignments';
    public const OPTION_MIGRATION = 'logipilot_permissions_migration';
    public const OPTION_USERS = 'logipilot_user_permissions';

    public const GROUPS = array(
        'suite' => 'Suite',
        'notes' => 'LogiNotes',
        'progress' => 'LogiProgress',
        'exam' => 'LogiExam',
        'classe' => 'LogiClass',
        'live' => 'LogiLive',
        'eval' => 'LogiEval',
        'planning' => 'LogiPlanning',
        'integration' => 'Intégration',
        'tiles' => 'Tuiles de contenu',
    );

    public const PERMS = array(
        'lp_access' => array('suite', 'Accéder à l’espace LogiOne'),
        'lp_admin_modules' => array('suite', 'Administrer les modules et services'),
        'lp_admin_permissions' => array('suite', 'Administrer les permissions'),
        'lp_notes_view' => array('notes', 'Consulter'),
        'lp_notes_edit' => array('notes', 'Créer et modifier (classes, élèves, évaluations)'),
        'lp_notes_grade' => array('notes', 'Saisir les points'),
        'lp_notes_adjust' => array('notes', 'Valider un ajustement de progression'),
        'lp_notes_export' => array('notes', 'Exporter'),
        'lp_notes_delete' => array('notes', 'Supprimer'),
        'lp_progress_view' => array('progress', 'Consulter'),
        'lp_progress_export' => array('progress', 'Exporter'),
        'lp_progress_use' => array('progress', 'Utiliser la progression pour une évaluation'),
        'lp_progress_settings' => array('progress', 'Gérer les réglages'),
        'lp_progress_reset' => array('progress', 'Réinitialiser l’avancement d’un élève (cours ou leçon Sensei)'),
        'lp_exam_teach' => array('exam', 'Préparer, surveiller, débloquer, corriger, finaliser'),
        'lp_exam_ai' => array('exam', 'Précorriger avec l’IA'),
        'lp_exam_publish' => array('exam', 'Publier les résultats dans LogiNotes'),
        'lp_exam_export' => array('exam', 'Exporter'),
        'lp_classe_view' => array('classe', 'Consulter'),
        'lp_classe_edit' => array('classe', 'Modifier les fiches et traiter les situations'),
        'lp_classe_import' => array('classe', 'Importer'),
        'lp_classe_templates' => array('classe', 'Gérer les modèles de messages'),
        'lp_classe_send' => array('classe', 'Envoyer des e-mails'),
        'lp_classe_export' => array('classe', 'Exporter'),
        'lp_classe_delete' => array('classe', 'Supprimer ou anonymiser'),
        'lp_classe_settings' => array('classe', 'Administrer les réglages'),
        'lp_live_view' => array('live', 'Voir l’activité de la classe en direct (élèves connectés, leçon ouverte)'),
        'lp_eval_use' => array('eval', 'Remplir les évaluations de posture depuis LogiOne (reconnu par son compte, sans identifiant)'),
        'lp_eval_admin' => array('eval', 'Administrer LogiEval : classes, élèves, enseignants, objectifs, évaluations, réglages'),
        'lp_planning_view' => array('planning', 'Consulter les plans de formation (vue des élèves)'),
        'lp_planning_edit' => array('planning', 'Créer et modifier les plans : import, journées, branches, messages'),
        'lp_integration_link' => array('integration', 'Gérer les correspondances classes et élèves'),
        'lp_integration_sync' => array('integration', 'Relancer une synchronisation'),
        'lp_tiles_edit' => array('tiles', 'Créer et modifier les tuiles de contenu (réservé à l’administration par défaut)'),
    );

    private static ?array $matrix_cache = null;
    private static ?array $users_cache = null;

    public static function init(): void
    {
        add_filter('user_has_cap', array(__CLASS__, 'filter_user_has_cap'), 10, 4);
        add_action('update_option_' . self::OPTION_MATRIX, array(__CLASS__, 'flush_cache'));
        add_action('admin_post_logipilot_save_permissions', array(__CLASS__, 'handle_save'));
        add_action('admin_post_logipilot_save_user_permissions', array(__CLASS__, 'handle_save_user'));
        add_action('init', array(__CLASS__, 'maybe_grant_live'), 1);
        add_action('update_option_' . self::OPTION_USERS, array(__CLASS__, 'flush_cache'));
    }

    public static function flush_cache(): void
    {
        self::$matrix_cache = null;
        self::$users_cache = null;
    }

    /** Droits accordés individuellement : [user_id => [perm => 1]]. */
    public static function user_grants(): array
    {
        if (self::$users_cache === null) {
            $grants = get_option(self::OPTION_USERS, array());
            self::$users_cache = is_array($grants) ? $grants : array();
        }

        return self::$users_cache;
    }

    public static function user_granted(int $user_id, string $perm): bool
    {
        $grants = self::user_grants();

        return !empty($grants[$user_id][$perm]);
    }

    public static function matrix(): array
    {
        if (self::$matrix_cache === null) {
            $matrix = get_option(self::OPTION_MATRIX, array());
            self::$matrix_cache = is_array($matrix) ? $matrix : array();
        }

        return self::$matrix_cache;
    }

    public static function role_has(string $role, string $perm): bool
    {
        if ($role === 'administrator') {
            return true;
        }
        $matrix = self::matrix();

        return !empty($matrix[$role][$perm]);
    }

    public static function user_has(WP_User $user, string $perm): bool
    {
        foreach ((array) $user->roles as $role) {
            if (self::role_has((string) $role, $perm)) {
                return true;
            }
        }
        if (self::user_granted((int) $user->ID, $perm)) {
            return true;
        }

        if ($perm === 'lp_access') {
            foreach (array_keys(self::PERMS) as $other) {
                if ($other === 'lp_access') {
                    continue;
                }
                if (self::user_granted((int) $user->ID, $other)) {
                    return true;
                }
                foreach ((array) $user->roles as $role) {
                    if (self::role_has((string) $role, $other)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** Droit obtenu par le rôle seul (affiché dans l'écran des droits individuels). */
    public static function role_grants(WP_User $user, string $perm): bool
    {
        foreach ((array) $user->roles as $role) {
            if (self::role_has((string) $role, $perm)) {
                return true;
            }
        }

        return false;
    }

    /** Calcule les capacités lp_* à la volée : rien n'est stocké dans les rôles. */
    public static function filter_user_has_cap(array $allcaps, array $caps, array $args, $user): array
    {
        if (!$user instanceof WP_User) {
            return $allcaps;
        }

        foreach ($caps as $cap) {
            if (is_string($cap) && isset(self::PERMS[$cap])) {
                $allcaps[$cap] = self::user_has($user, $cap);
            }
        }

        return $allcaps;
    }

    public static function can(string $perm): bool
    {
        return is_user_logged_in() && current_user_can($perm);
    }

    public static function require(string $perm, string $message = 'Accès refusé.'): void
    {
        if (!self::can($perm)) {
            wp_die(esc_html($message), 'Accès refusé', array('response' => 403));
        }
    }

    /* -------------------------------------------------------------------
     * Périmètre de données LogiNotes (ACL-03)
     * ----------------------------------------------------------------- */

    /**
     * Toutes les classes de l'espace courant sont accessibles : l'isolement entre enseignants est
     * assuré par les espaces (chaque enseignant ne voit que ses propres tables). null = toutes.
     */
    public static function notes_allowed_class_ids(?int $user_id = null): ?array
    {
        return null;
    }

    public static function notes_class_allowed(int $class_id): bool
    {
        $allowed = self::notes_allowed_class_ids();

        return $allowed === null || in_array($class_id, $allowed, true);
    }

    /** Fragment SQL « AND colonne IN (...) » pour filtrer une requête LogiNotes. */
    public static function notes_class_sql(string $column): string
    {
        $allowed = self::notes_allowed_class_ids();
        if ($allowed === null) {
            return '';
        }
        if (!$allowed) {
            return ' AND 1 = 0';
        }

        return ' AND ' . $column . ' IN (' . implode(',', array_map('absint', $allowed)) . ')';
    }

    /* -------------------------------------------------------------------
     * Reprise des droits existants (ACL-06)
     * ----------------------------------------------------------------- */

    public static function initialize_from_legacy(bool $force = false): void
    {
        if (!$force && get_option(self::OPTION_MATRIX, null) !== null) {
            return;
        }

        $roles = wp_roles();
        $matrix = array();
        $report = array();

        foreach ($roles->roles as $key => $data) {
            if ($key === 'administrator') {
                continue;
            }
            $caps = (array) ($data['caps'] ?? $data['capabilities'] ?? array());
            $name = (string) ($data['name'] ?? $key);
            $perms = array();

            $has_gne = !empty($caps['gne_manage']);
            $has_gca = !empty($caps['gca_manage']);
            if ($has_gne && !$has_gca) {
                foreach (array('lp_notes_view', 'lp_notes_edit', 'lp_notes_grade', 'lp_notes_adjust', 'lp_notes_export', 'lp_notes_delete', 'lp_progress_use', 'lp_integration_link') as $perm) {
                    $perms[$perm] = 1;
                }
                $report[] = $name . ' : LogiNotes repris (droit gne_manage).';
            } elseif ($has_gne && $has_gca) {
                $report[] = $name . ' : LogiNotes NON repris automatiquement, car gne_manage a pu être ajouté par gca_manage (ACL-08). À confirmer dans la matrice.';
            }

            if (!empty($caps['psgpt_access']) || self::legacy_progress_role($key, $name)) {
                foreach (array('lp_progress_view', 'lp_progress_export') as $perm) {
                    $perms[$perm] = 1;
                }
                $report[] = $name . ' : LogiProgress repris.';
            }

            if (self::legacy_exam_role($key, $name)) {
                foreach (array('lp_exam_teach', 'lp_exam_ai', 'lp_exam_publish', 'lp_exam_export', 'lp_progress_use', 'lp_integration_sync') as $perm) {
                    $perms[$perm] = 1;
                }
                $report[] = $name . ' : LogiExam enseignant repris (ancienne reconnaissance par le nom du rôle).';
            }

            if (self::legacy_live_role($key, $name, $caps)) {
                $perms['lp_live_view'] = 1;
                $report[] = $name . ' : LogiLive repris.';
            }

            if ($has_gca) {
                foreach (array('lp_classe_view', 'lp_classe_edit', 'lp_classe_import', 'lp_classe_templates', 'lp_classe_send', 'lp_classe_export', 'lp_classe_delete', 'lp_classe_settings') as $perm) {
                    $perms[$perm] = 1;
                }
                $report[] = $name . ' : LogiClass repris (droit gca_manage).';
            }

            if ($perms) {
                $perms['lp_access'] = 1;
                $matrix[$key] = $perms;
            }
        }

        update_option(self::OPTION_MATRIX, $matrix, false);
        update_option(self::OPTION_MIGRATION, array('at' => current_time('mysql'), 'report' => $report), false);
        self::flush_cache();
        LogiPilot_Log::add('permissions_initialized', 'permissions', 0, array('report' => $report));
    }

    /** Reproduit la règle de LogiProgress 3.8.9, utilisée UNIQUEMENT pour la reprise initiale. */
    /** Rôles qui avaient accès à LogiLive (ou à l'ancien suivi en direct) : droit lslca_access ou rôle « enseignant logistique ». */
    private static function legacy_live_role(string $key, string $name, array $caps): bool
    {
        $norm = static fn($value) => trim((string) preg_replace('/[^a-z0-9]+/', '_', remove_accents(strtolower((string) $value))), '_');

        return !empty($caps['lslca_access']) || strpos($norm($key), 'enseignant_logistique') !== false || strpos($norm($name), 'enseignant_logistique') !== false;
    }

    /** Mise à jour vers LogiOne 1.15 : les rôles qui utilisaient LogiLive gardent leur accès (une seule fois). */
    public static function maybe_grant_live(): void
    {
        self::maybe_grant_teaching_modules();
        if (get_option('logipilot_live_granted') || get_option(self::OPTION_MATRIX, null) === null) {
            return;
        }
        $matrix = self::matrix();
        $report = array();
        foreach (wp_roles()->roles as $key => $data) {
            if ($key === 'administrator') {
                continue;
            }
            $caps = (array) ($data['capabilities'] ?? array());
            if (self::legacy_live_role($key, (string) ($data['name'] ?? $key), $caps)) {
                $matrix[$key]['lp_live_view'] = 1;
                $matrix[$key]['lp_access'] = 1;
                $report[] = (string) ($data['name'] ?? $key);
            }
        }
        update_option(self::OPTION_MATRIX, $matrix, false);
        update_option('logipilot_live_granted', current_time('mysql'), false);
        self::flush_cache();
        LogiPilot_Log::add('permissions_live_granted', 'permissions', 0, array('roles' => $report));
    }

    /**
     * Mise à jour vers LogiOne 1.16 : LogiEval et LogiPlanning (une seule fois).
     * - Les rôles qui avaient déjà accès à LogiOne, ou le rôle « enseignant logistique », peuvent ouvrir LogiEval
     *   (ils y sont reconnus s'ils sont enseignants LogiEval, sinon l'identifiant leur est demandé, comme avant)
     *   et consulter les plans de formation (comme dans LogiPlanning, où les enseignants voyaient tous les plans).
     * - Administrer LogiEval et gérer les plans restent réservés à l'administration, comme avant.
     */
    public static function maybe_grant_teaching_modules(): void
    {
        if (get_option('logipilot_teaching_granted') || get_option(self::OPTION_MATRIX, null) === null) {
            return;
        }
        $matrix = self::matrix();
        $report = array();
        foreach (wp_roles()->roles as $key => $data) {
            if ($key === 'administrator') {
                continue;
            }
            $caps = (array) ($data['capabilities'] ?? array());
            // Rôles d'enseignement : déjà un droit sur LogiNotes, LogiProgress ou LogiExam (pas le secrétariat
            // qui n'utilise que LogiClass), ou le rôle « enseignant logistique ».
            $teaching = !empty($matrix[$key]['lp_access']) && (bool) array_filter(
                (array) ($matrix[$key] ?? array()),
                static function ($on, $perm) { return $on && preg_match('/^lp_(notes|progress|exam)/', (string) $perm); },
                ARRAY_FILTER_USE_BOTH
            );
            if ($teaching || self::legacy_live_role($key, (string) ($data['name'] ?? $key), $caps)) {
                $matrix[$key]['lp_access'] = 1;
                $matrix[$key]['lp_eval_use'] = 1;
                $matrix[$key]['lp_planning_view'] = 1;
                $report[] = (string) ($data['name'] ?? $key);
            }
        }
        update_option(self::OPTION_MATRIX, $matrix, false);
        update_option('logipilot_teaching_granted', current_time('mysql'), false);
        self::flush_cache();
        LogiPilot_Log::add('permissions_teaching_granted', 'permissions', 0, array('roles' => $report));
    }

    private static function legacy_progress_role(string $key, string $name): bool
    {
        $norm = static function ($value) {
            $value = remove_accents(strtolower((string) $value));
            return trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
        };

        return strpos($norm($key), 'enseignant_logistique') !== false || strpos($norm($name), 'enseignant_logistique') !== false;
    }

    /** Reproduit la règle de LogiExam 3.5.0, utilisée UNIQUEMENT pour la reprise initiale. */
    private static function legacy_exam_role(string $key, string $name): bool
    {
        $normalized = remove_accents(strtolower($key . ' ' . $name));
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', $normalized), '_');
        foreach (array('enseignant_logistique', 'logistique_enseignant', 'logistics_teacher', 'teacher_logistics') as $needle) {
            if (strpos($normalized, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /* -------------------------------------------------------------------
     * Écran d'administration des permissions
     * ----------------------------------------------------------------- */

    /** Présentation de chaque module dans l'écran Permissions : description et droit indispensable pour l'ouvrir. */
    public const MODULE_INFO = array(
        'suite' => array('Accès à LogiOne', 'L’entrée dans LogiOne et son administration.', 'lp_access'),
        'notes' => array('LogiNotes', 'Le carnet de notes : classes, élèves, évaluations, relevés.', 'lp_notes_view'),
        'progress' => array('LogiProgress', 'La progression des groupes dans les cours en ligne.', 'lp_progress_view'),
        'exam' => array('LogiExam', 'Les évaluations numériques sécurisées.', 'lp_exam_teach'),
        'classe' => array('LogiClass', 'Le suivi des apprentis : fiches, absences, e-mails.', 'lp_classe_view'),
        'live' => array('LogiLive', 'Le suivi en direct de la classe : qui est connecté, sur quelle leçon.', 'lp_live_view'),
        'eval' => array('LogiEval', 'L’évaluation collaborative de la posture professionnelle des élèves. La page LogiEval avec identifiant continue de fonctionner.', 'lp_eval_use'),
        'planning' => array('LogiPlanning', 'Les plans de formation par classe et le calendrier des élèves.', 'lp_planning_view'),
        'integration' => array('Liaisons entre modules', 'Les correspondances de classes et d’élèves entre les modules.', ''),
        'tiles' => array('Tuiles de contenu', 'Les tuiles de ressources du portail LogiOne.', ''),
    );

    /** Rôles affichés par défaut : ceux qui ont déjà au moins un droit LogiOne (sinon tous). */
    private static function default_visible_roles(array $roles, array $matrix): array
    {
        $with = array_values(array_filter(array_keys($roles), static fn($key) => $key !== 'administrator' && !empty(array_filter((array) ($matrix[$key] ?? array())))));

        return $with ?: array_values(array_diff(array_keys($roles), array('administrator')));
    }

    public static function render_page(): void
    {
        if (!current_user_can('lp_admin_permissions')) {
            wp_die('Accès refusé.', 403);
        }

        $roles = wp_roles()->roles;
        unset($roles['administrator']);
        $matrix = self::matrix();
        $migration = get_option(self::OPTION_MIGRATION, array());
        $visible = self::default_visible_roles(wp_roles()->roles, $matrix);
        wp_enqueue_script('logipilot-permissions', LOGIPILOT_URL . 'assets/permissions.js', array(), LOGIPILOT_VERSION, true);
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Permissions enregistrées. Elles s’appliquent immédiatement.</p></div>';
        }
        ?>
        <div class="wrap logipilot-wrap lpperm">
            <h1>LogiOne · Permissions</h1>
            <p class="lpperm-intro">Choisissez un module, puis cochez ce que chaque rôle peut y faire. Les modifications s’appliquent dès l’enregistrement. L’administrateur a toujours tous les droits (accès de secours). Pour une seule personne, utilisez <a href="#droits-utilisateur">les droits individuels</a> plus bas.</p>
            <p class="logipilot-muted">Les permissions disent <em>ce qu’un enseignant peut faire</em>. Elles ne lui donnent jamais accès aux données d’un collègue : dans LogiNotes et LogiClass, chacun travaille dans son propre espace.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="lpperm-form" data-lpperm data-access-perm="<?php echo esc_attr('lp_access'); ?>">
                <input type="hidden" name="action" value="logipilot_save_permissions">
                <?php wp_nonce_field('logipilot_save_permissions'); ?>

                <fieldset class="lpperm-roles" data-lpperm-roles>
                    <legend>Rôles affichés</legend>
                    <?php foreach ($roles as $key => $role) : ?>
                        <label class="lpperm-chip"><input type="checkbox" value="<?php echo esc_attr($key); ?>" data-lpperm-role-toggle <?php checked(in_array($key, $visible, true)); ?>> <?php echo esc_html(translate_user_role($role['name'])); ?></label>
                    <?php endforeach; ?>
                    <p class="description">Affichez seulement les rôles de vos enseignants : les droits des rôles masqués sont conservés.</p>
                </fieldset>

                <nav class="lpperm-tabs" role="tablist" aria-label="Modules" data-lpperm-tabs hidden>
                    <?php foreach (self::GROUPS as $group => $label) : ?>
                        <button type="button" class="lpperm-tab" role="tab" id="lpperm-tab-<?php echo esc_attr($group); ?>" aria-controls="lpperm-panel-<?php echo esc_attr($group); ?>" data-lpperm-tab="<?php echo esc_attr($group); ?>">
                            <strong><?php echo esc_html(self::MODULE_INFO[$group][0] ?? $label); ?></strong>
                            <span class="lpperm-tab-count" data-lpperm-count="<?php echo esc_attr($group); ?>"></span>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <?php foreach (self::GROUPS as $group => $label) :
                    [$title, $text, $key_perm] = self::MODULE_INFO[$group] ?? array($label, '', '');
                    $perms = array_filter(self::PERMS, static fn($def) => $def[0] === $group);
                    ?>
                    <section class="lpperm-panel" id="lpperm-panel-<?php echo esc_attr($group); ?>" role="tabpanel" aria-labelledby="lpperm-tab-<?php echo esc_attr($group); ?>" data-lpperm-panel="<?php echo esc_attr($group); ?>" data-key-perm="<?php echo esc_attr($key_perm); ?>">
                        <header class="lpperm-panel-head">
                            <h2><?php echo esc_html($title); ?></h2>
                            <p><?php echo esc_html($text); ?><?php if ($key_perm !== '') : ?> <strong>Pour ouvrir ce module, il faut « <?php echo esc_html(self::PERMS[$key_perm][1]); ?> »</strong><?php echo $group === 'suite' ? '' : ' (et « Accéder à l’espace LogiOne »)'; ?>.<?php endif; ?></p>
                        </header>
                        <div class="lpperm-table-wrap">
                            <table class="lpperm-table logipilot-matrix">
                                <thead>
                                    <tr>
                                        <th scope="col" class="lpperm-perm-col">Ce que le rôle peut faire</th>
                                        <?php foreach ($roles as $key => $role) : ?>
                                            <th scope="col" data-lpperm-role="<?php echo esc_attr($key); ?>" title="<?php echo esc_attr($key); ?>"><?php echo esc_html(translate_user_role($role['name'])); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="lpperm-all" data-lpperm-js-only hidden>
                                        <th scope="row">Tout le module</th>
                                        <?php foreach ($roles as $key => $role) : ?>
                                            <td data-lpperm-role="<?php echo esc_attr($key); ?>"><input type="checkbox" data-lpperm-all="<?php echo esc_attr($key); ?>" aria-label="<?php echo esc_attr('Tout ' . $title . ' pour ' . translate_user_role($role['name'])); ?>"></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php foreach ($perms as $perm => $def) : ?>
                                        <tr<?php echo $perm === $key_perm ? ' class="is-key"' : ''; ?>>
                                            <th scope="row" title="<?php echo esc_attr($perm); ?>"><?php echo esc_html($def[1]); ?><?php echo $perm === $key_perm ? ' <span class="lpperm-key">indispensable</span>' : ''; ?></th>
                                            <?php foreach ($roles as $key => $role) : ?>
                                                <td data-lpperm-role="<?php echo esc_attr($key); ?>"><input type="checkbox" name="perm[<?php echo esc_attr($key); ?>][<?php echo esc_attr($perm); ?>]" value="1" data-perm="<?php echo esc_attr($perm); ?>" aria-label="<?php echo esc_attr($def[1] . ' — ' . translate_user_role($role['name'])); ?>" <?php checked(!empty($matrix[$key][$perm])); ?>></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>

                <p class="lpperm-save"><button class="button button-primary button-hero">Enregistrer les permissions</button> <span class="lpperm-dirty" data-lpperm-dirty hidden>Modifications non enregistrées</span></p>
            </form>
            <?php self::render_user_section(); ?>
            <?php if (!empty($migration['report'])) : ?>
                <details class="lpperm-migration"><summary>Reprise des droits du <?php echo esc_html((string) $migration['at']); ?></summary>
                <ul class="ul-disc">
                    <?php foreach ((array) $migration['report'] as $line) : ?>
                        <li><?php echo esc_html((string) $line); ?></li>
                    <?php endforeach; ?>
                </ul></details>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_save(): void
    {
        if (!current_user_can('lp_admin_permissions')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_save_permissions');

        $roles = array_keys(wp_roles()->roles);
        $posted = isset($_POST['perm']) && is_array($_POST['perm']) ? wp_unslash($_POST['perm']) : array();
        $before = self::matrix();
        $matrix = array();
        foreach ($roles as $role) {
            if ($role === 'administrator') {
                continue;
            }
            foreach (array_keys(self::PERMS) as $perm) {
                if (!empty($posted[$role][$perm])) {
                    $matrix[$role][$perm] = 1;
                }
            }
        }

        update_option(self::OPTION_MATRIX, $matrix, false);
        self::flush_cache();
        LogiPilot_Log::add('permissions_saved', 'permissions', 0, array('before' => $before, 'after' => $matrix));

        wp_safe_redirect(admin_url('admin.php?page=logione-permissions&saved=1'));
        exit;
    }

    /* -------------------------------------------------------------------
     * Droits accordés à un utilisateur précis
     * ----------------------------------------------------------------- */

    private static function render_user_section(): void
    {
        $grants = self::user_grants();
        $selected_id = absint($_GET['lp_user'] ?? 0);
        $selected = $selected_id ? get_userdata($selected_id) : null;
        $users = get_users(array('orderby' => 'display_name', 'fields' => array('ID', 'display_name', 'user_login')));
        echo '<h2 id="droits-utilisateur">Droits pour un utilisateur précis</h2>';
        echo '<p>Utile pour donner un droit à une seule personne sans changer tout son rôle (par exemple : un collègue qui doit utiliser LogiExam). Ces droits s’ajoutent à ceux de son rôle ; pour en retirer, décochez-les ici ou modifiez le rôle.</p>';
        if (!empty($_GET['user_saved'])) {
            echo '<div class="notice notice-success inline"><p>Droits de l’utilisateur enregistrés. Ils s’appliquent immédiatement.</p></div>';
        }

        $with = array_filter($grants, static fn($perms) => is_array($perms) && array_filter($perms));
        if ($with) {
            echo '<table class="widefat striped"><thead><tr><th>Utilisateur</th><th>Droits individuels</th><th></th></tr></thead><tbody>';
            foreach ($with as $user_id => $perms) {
                $user = get_userdata((int) $user_id);
                if (!$user) {
                    continue;
                }
                $labels = array();
                foreach (array_keys(array_filter($perms)) as $perm) {
                    if (isset(self::PERMS[$perm])) {
                        $labels[] = self::GROUPS[self::PERMS[$perm][0]] . ' : ' . self::PERMS[$perm][1];
                    }
                }
                printf('<tr><td>%s <code>%s</code></td><td>%s</td><td><a href="%s">Modifier</a></td></tr>', esc_html($user->display_name), esc_html($user->user_login), esc_html(implode(' · ', $labels)), esc_url(admin_url('admin.php?page=logione-permissions&lp_user=' . (int) $user->ID . '#droits-utilisateur')));
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="logipilot-muted">Aucun droit individuel pour le moment.</p>';
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="logione-permissions"><p><label>Choisir un utilisateur <select name="lp_user" onchange="this.form.submit()"><option value="0">—</option>';
        foreach ($users as $user) {
            printf('<option value="%d" %s>%s (%s)</option>', (int) $user->ID, selected($selected_id, (int) $user->ID, false), esc_html($user->display_name), esc_html($user->user_login));
        }
        echo '</select></label> <button class="button">Afficher ses droits</button></p></form>';

        if (!$selected) {
            return;
        }
        $is_admin = in_array('administrator', (array) $selected->roles, true);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="logipilot_save_user_permissions"><input type="hidden" name="user_id" value="' . (int) $selected->ID . '">';
        wp_nonce_field('logipilot_save_user_permissions');
        echo '<h3>' . esc_html($selected->display_name) . ' — rôle : ' . esc_html(implode(', ', array_map(static fn($role) => translate_user_role(wp_roles()->roles[$role]['name'] ?? $role), (array) $selected->roles)) ?: 'aucun') . '</h3>';
        if ($is_admin) {
            echo '<p>Administrateur : tous les droits, toujours.</p></form>';
            return;
        }
        echo '<p class="description">« Par son rôle » : déjà autorisé par son rôle. Cochez « En plus » pour accorder un droit à cette personne seulement.</p><div class="lpperm-user-grid">';
        foreach (self::GROUPS as $group => $group_label) {
            echo '<fieldset class="lpperm-user-card"><legend>' . esc_html(self::MODULE_INFO[$group][0] ?? $group_label) . '</legend><table class="lpperm-user-table"><thead><tr><th scope="col">Ce qu’il peut faire</th><th scope="col">Par son rôle</th><th scope="col">En plus</th></tr></thead><tbody>';
            foreach (self::PERMS as $perm => $def) {
                if ($def[0] !== $group) {
                    continue;
                }
                $by_role = self::role_grants($selected, $perm);
                printf(
                    '<tr><th scope="row" title="%s">%s</th><td>%s</td><td><input type="checkbox" name="perm[%s]" value="1" aria-label="%s" %s></td></tr>',
                    esc_attr($perm),
                    esc_html($def[1]),
                    $by_role ? '<span class="lpperm-yes">✓</span>' : '<span class="lpperm-no">—</span>',
                    esc_attr($perm),
                    esc_attr($def[1] . ' (en plus de son rôle)'),
                    checked(self::user_granted((int) $selected->ID, $perm), true, false)
                );
            }
            echo '</tbody></table></fieldset>';
        }
        echo '</div><p><button class="button button-primary">Enregistrer les droits de ' . esc_html($selected->display_name) . '</button></p></form>';
    }

    public static function handle_save_user(): void
    {
        if (!current_user_can('lp_admin_permissions')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_save_user_permissions');
        $user_id = absint($_POST['user_id'] ?? 0);
        if (!$user_id || !get_userdata($user_id)) {
            wp_die('Utilisateur introuvable.', 'Erreur', array('response' => 400));
        }
        $posted = isset($_POST['perm']) && is_array($_POST['perm']) ? wp_unslash($_POST['perm']) : array();
        $perms = array();
        foreach (array_keys(self::PERMS) as $perm) {
            if (!empty($posted[$perm])) {
                $perms[$perm] = 1;
            }
        }
        $grants = self::user_grants();
        $before = $grants[$user_id] ?? array();
        if ($perms) {
            $grants[$user_id] = $perms;
        } else {
            unset($grants[$user_id]);
        }
        update_option(self::OPTION_USERS, $grants, false);
        self::flush_cache();
        LogiPilot_Log::add('user_permissions_saved', 'user', $user_id, array('before' => $before, 'after' => $perms));

        wp_safe_redirect(admin_url('admin.php?page=logione-permissions&lp_user=' . $user_id . '&user_saved=1#droits-utilisateur'));
        exit;
    }

    /** Retire de la matrice les droits qui n'existent plus (ex. « Passer une évaluation » de la 1.4.0). */
    public static function remove_unknown_permissions(): void
    {
        $matrix = get_option(self::OPTION_MATRIX, null);
        if (!is_array($matrix)) {
            return;
        }
        $clean = array();
        foreach ($matrix as $role => $perms) {
            $perms = array_intersect_key((array) $perms, self::PERMS);
            if ($perms) {
                $clean[$role] = $perms;
            }
        }
        if ($clean !== $matrix) {
            update_option(self::OPTION_MATRIX, $clean, false);
            self::flush_cache();
        }
    }
}
