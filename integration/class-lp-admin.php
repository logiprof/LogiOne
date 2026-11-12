<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Espace commun LogiOne (UI-01) : tableau de bord, modules, permissions, correspondances,
 * synchronisation, diagnostic et journal. Seuls les modules et actions autorisés sont affichés.
 */
final class LogiPilot_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 5);
        add_action('admin_post_logipilot_save_modules', array(__CLASS__, 'handle_save_modules'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
    }

    public static function enqueue(): void
    {
        wp_enqueue_style('logipilot', LOGIPILOT_URL . 'assets/logipilot.css', array(), LOGIPILOT_VERSION);
    }

    public static function register_menu(): void
    {
        add_menu_page('LogiOne', 'LogiOne', 'lp_access', 'logione', array(__CLASS__, 'render_dashboard'), self::menu_icon(), 2);
        add_submenu_page('logione', 'Tableau de bord', 'Tableau de bord', 'lp_access', 'logione', array(__CLASS__, 'render_dashboard'));
        add_submenu_page('logione', 'Permissions', 'Permissions', 'lp_admin_permissions', 'logione-permissions', array('LogiPilot_Permissions', 'render_page'));
        add_submenu_page('logione', 'Espaces enseignants', 'Espaces enseignants', 'manage_options', 'logione-spaces', array('LogiPilot_Spaces', 'render_page'));
        add_submenu_page('logione', 'Correspondances', 'Correspondances', 'lp_integration_link', 'logione-links', array('LogiPilot_Links', 'render_links_page'));
        add_submenu_page('logione', 'Synchronisation', 'Synchronisation', 'lp_integration_sync', 'logione-sync', array(__CLASS__, 'render_sync'));
        add_submenu_page('logione', 'Modules', 'Modules', 'lp_admin_modules', 'logione-modules', array(__CLASS__, 'render_modules'));
        add_submenu_page('logione', 'Diagnostic', 'Diagnostic', 'lp_admin_modules', 'logione-diagnostic', array('LogiPilot_Diagnostic', 'render_page'));
        if (class_exists('LogiPilot_Tiles')) {
            add_submenu_page('logione', 'Tuiles de contenu', 'Tuiles de contenu', 'lp_tiles_edit', 'logione-tuiles', array('LogiPilot_Tiles', 'render_admin_page'));
        }
        if (class_exists('LogiPilot_Layout')) {
            add_submenu_page('logione', 'Disposition du portail', 'Disposition du portail', LogiPilot_Layout::CAP, LogiPilot_Layout::SLUG, array('LogiPilot_Layout', 'render_page'));
        }
        add_submenu_page('logione', 'Journal', 'Journal', 'lp_admin_permissions', 'logione-log', array(__CLASS__, 'render_log'));
        if (defined('LOGIPILOT_TEST_MODE') && class_exists('LogiPilot_Sandbox')) {
            add_submenu_page('logione', 'Bac à sable', 'Bac à sable', 'lp_admin_modules', 'logione-sandbox', array('LogiPilot_Sandbox', 'render_page'));
        }
    }

    /** Icône du menu : le symbole LogiOne en une couleur, recoloré par WordPress selon le thème de l'administration. */
    private static function menu_icon(): string
    {
        $svg = @file_get_contents(LOGIPILOT_DIR . 'assets/logione-menu.svg');

        return $svg ? 'data:image/svg+xml;base64,' . base64_encode($svg) : 'dashicons-welcome-learn-more';
    }

    private static function module_entries(): array
    {
        return array(
            'classe' => array('LogiClass', 'Fiches apprentis, imports, suivi et communications', 'lp_classe_view', 'admin.php?page=gca_app'),
            'progress' => array('LogiProgress', 'Progression Sensei par groupe et par cours', 'lp_progress_view', 'admin.php?page=logiprogress'),
            'notes' => array('LogiNotes', 'Carnet de notes, évaluations papier, relevés', 'lp_notes_view', 'admin.php?page=gne_app'),
            'exam' => array('LogiExam', 'Évaluations numériques, passation, correction', 'lp_exam_teach', 'admin.php?page=logiexam'),
            'eval' => array('LogiEval', 'Évaluation collaborative de la posture professionnelle', 'lp_eval_admin', 'admin.php?page=logieval'),
            'planning' => array('LogiPlanning', 'Plans de formation par classe et calendrier des élèves', 'lp_planning_edit', 'admin.php?page=logiplanning-lms'),
            'live' => array('LogiLive', 'Suivi en direct de la classe : élèves connectés et leçon ouverte', 'lp_live_view', 'admin.php?page=logilive'),
            'tiles' => array('Tuiles de contenu', 'Tuiles de ressources pour les enseignants, affichées dans LogiOne à la suite des outils', 'lp_tiles_edit', 'admin.php?page=logione-tuiles'),
        );
    }

    public static function render_dashboard(): void
    {
        echo '<div class="wrap logipilot-wrap"><h1 class="logipilot-brand-title">' . LogiPilot_Portal::logo('logipilot-admin-logo') . '<span class="screen-reader-text">LogiOne</span></h1><div class="logipilot-cards">';
        foreach (self::module_entries() as $key => [$label, $text, $cap, $url]) {
            if (!current_user_can($cap)) {
                continue;
            }
            $loaded = LogiPilot_Plugin::is_loaded($key);
            echo '<div class="logipilot-card"><h2>' . esc_html($label) . '</h2><p>' . esc_html($text) . '</p>';
            echo $loaded ? '<a class="button button-primary" href="' . esc_url(admin_url($url)) . '">Ouvrir</a>' : '<p class="logipilot-warning">Module désactivé ou en conflit avec l’ancien plugin.</p>';
            echo '</div>';
        }
        echo '</div>';

        if (current_user_can('lp_admin_modules')) {
            echo '<div class="logipilot-panel"><h2>Accès des enseignants depuis le site</h2><p>Placez le shortcode <code>[logione]</code> dans une page de votre site (par exemple « Mes outils »). Chaque enseignant connecté y voit seulement les outils auxquels il a droit et les utilise directement dans la page. LogiOne est réservé aux enseignants.</p><p><strong>Élèves :</strong> ils passent leurs évaluations, comme avant, sur la page qui contient le shortcode LogiExam <code>[logiexam_student]</code>. Il n’a pas changé et ne demande aucun droit LogiOne.</p><p class="logipilot-muted">Variantes : <code>[logione module="notes"]</code> ouvre directement un outil (classe, progress, notes, exam) ; <code>[logione modules="notes,exam"]</code> limite les outils proposés. Les droits se règlent ici, dans Permissions.</p></div>';
        }

        if (current_user_can('lp_integration_sync')) {
            global $wpdb;
            $problems = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . LogiPilot_Install::table('publications') . " WHERE state IN ('error', 'conflict', 'pending')");
            $pending = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . LogiPilot_Install::table('exam_links') . " WHERE notes_evaluation_id = 0");
            if ($problems || $pending) {
                echo '<p class="logipilot-warning">' . esc_html(sprintf('%d publication(s) à traiter et %d fiche(s) LogiNotes en attente de complément.', $problems, $pending)) . ' <a href="' . esc_url(admin_url('admin.php?page=logione-sync')) . '">Voir la synchronisation</a></p>';
            }
        }
        echo '</div>';
    }

    public static function render_modules(): void
    {
        if (!current_user_can('lp_admin_modules')) {
            wp_die('Accès refusé.', 403);
        }
        $enabled = LogiPilot_Plugin::enabled_modules();
        $conflicts = LogiPilot_Plugin::conflicts();
        echo '<div class="wrap logipilot-wrap"><h1>LogiOne · Modules et services</h1>';
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Réglages enregistrés. Ils s’appliquent au prochain chargement de page.</p></div>';
        }
        echo '<p>Activer un module, ouvrir son écran et utiliser un service d’intégration sont trois réglages distincts (ARC-04). Désactiver un module ne supprime aucune donnée.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="logipilot_save_modules">';
        wp_nonce_field('logipilot_save_modules');
        echo '<h2>Modules</h2><table class="widefat striped"><tbody>';
        foreach (LogiPilot_Plugin::MODULES as $key => $module) {
            $state = isset($conflicts[$key]) ? 'Non chargé : ancien plugin actif (' . $conflicts[$key] . ')' : (LogiPilot_Plugin::is_loaded($key) ? 'Chargé' : 'Désactivé');
            printf('<tr><td><label><input type="checkbox" name="modules[%s]" value="1" %s> %s</label></td><td>%s</td></tr>', esc_attr($key), checked(!empty($enabled[$key]), true, false), esc_html($module['label']), esc_html($state));
        }
        echo '</tbody></table><p class="description">LogiProgress désactivé : ses écrans sont masqués, mais les dates de validation Sensei continuent d’être mémorisées.</p>';
        echo '<h2>Services d’intégration</h2><table class="widefat striped"><tbody>';
        foreach (LogiPilot_Plugin::SERVICES as $key => $label) {
            printf('<tr><td><label><input type="checkbox" name="services[%s]" value="1" %s> %s</label></td></tr>', esc_attr($key), checked(LogiPilot_Plugin::service_enabled($key), true, false), esc_html($label));
        }
        echo '</tbody></table><p class="description">LogiClass n’échange aucune donnée avec les autres modules dans cette version (EXG-09).</p>';
        echo '<p><button class="button button-primary">Enregistrer</button></p></form></div>';
    }

    public static function handle_save_modules(): void
    {
        if (!current_user_can('lp_admin_modules')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logipilot_save_modules');
        $modules = array();
        foreach (array_keys(LogiPilot_Plugin::MODULES) as $key) {
            $modules[$key] = !empty($_POST['modules'][$key]) ? 1 : 0;
        }
        $services = array();
        foreach (array_keys(LogiPilot_Plugin::SERVICES) as $key) {
            $services[$key] = !empty($_POST['services'][$key]) ? 1 : 0;
        }
        update_option(LogiPilot_Plugin::OPTION_MODULES, $modules, true);
        update_option(LogiPilot_Plugin::OPTION_SERVICES, $services, true);
        LogiPilot_Log::add('modules_saved', 'suite', 0, array('modules' => $modules, 'services' => $services));
        wp_safe_redirect(admin_url('admin.php?page=logione-modules&saved=1'));
        exit;
    }

    public static function render_sync(): void
    {
        if (!current_user_can('lp_integration_sync')) {
            wp_die('Accès refusé.', 403);
        }
        global $wpdb;

        $rows = $wpdb->get_results('SELECT * FROM ' . LogiPilot_Install::table('publications') . " WHERE state <> 'superseded' ORDER BY updated_at DESC LIMIT 200");
        $links = $wpdb->get_results('SELECT * FROM ' . LogiPilot_Install::table('exam_links') . ' ORDER BY updated_at DESC LIMIT 100');
        $count = static fn(array $items, array $states): int => count(array_filter($items, static fn($item) => in_array((string) $item->state, $states, true)));
        $problems = $count($rows, array('error', 'conflict'));
        $waiting = $count($rows, array('pending', 'processing')) + count(array_filter($links, static fn($link) => (int) $link->notes_evaluation_id === 0));

        echo '<div class="wrap logipilot-wrap"><h1>LogiOne · Synchronisation</h1>';
        echo '<div class="logipilot-panel"><h2>À quoi sert cette page ?</h2>';
        echo '<p>Elle sert uniquement au passage <strong>LogiExam → LogiNotes</strong>. Quand une évaluation est créée dans LogiExam, une fiche est préparée dans votre LogiNotes. Quand vous cliquez sur « Publier les résultats dans LogiNotes », les notes corrigées sont recopiées dans cette fiche. Cette page vous montre ce qui est passé, ce qui attend et ce qui a échoué.</p>';
        echo '<p><strong>En temps normal, vous n’avez rien à faire ici.</strong> Vous intervenez seulement si une ligne est :</p><ul class="ul-disc">';
        echo '<li><strong>En erreur</strong> (LogiNotes était momentanément indisponible) : cliquez sur « Relancer ». Une relance ne crée jamais de note en double. Sinon, la reprise est automatique toutes les heures.</li>';
        echo '<li><strong>En conflit</strong> (la note a été modifiée à la main dans LogiNotes après la publication) : choisissez la note à garder, celle de LogiExam ou celle saisie dans LogiNotes.</li>';
        echo '<li><strong>En attente de complément</strong> (fiche LogiNotes sans classe ou sans semestre) : ouvrez l’évaluation dans LogiExam et complétez l’encadré « Fiche LogiNotes », ou reliez le groupe Sensei à votre classe dans Correspondances.</li></ul>';
        echo '<p class="logipilot-muted">LogiClass et les évaluations papier de LogiNotes ne passent pas par cette page. Vous ne voyez ici que vos propres évaluations.</p></div>';
        if ($problems || $waiting) {
            echo '<p class="logipilot-warning">' . esc_html(sprintf('%d ligne(s) demandent votre attention, %d en attente.', $problems, $waiting)) . '</p>';
        } else {
            echo '<p class="notice notice-success inline" style="padding:8px 12px">Tout est à jour : aucune action nécessaire.</p>';
        }

        echo '<h2>Évaluations LogiExam et leur fiche LogiNotes</h2><table class="widefat striped"><thead><tr><th>Évaluation LogiExam</th><th>Fiche LogiNotes</th><th>État</th><th>Détail</th></tr></thead><tbody>';
        foreach ($links as $link) {
            $exam = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation((int) $link->exam_evaluation_id) : null;
            $fiche = (int) $link->notes_evaluation_id > 0 ? $wpdb->get_var($wpdb->prepare('SELECT module_name FROM ' . LogiPilot_Spaces::notes_table('evaluations') . ' WHERE id = %d', $link->notes_evaluation_id)) : null;
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($exam['title'] ?? ('#' . $link->exam_evaluation_id)), esc_html($link->notes_evaluation_id ? ($fiche ?: '#' . $link->notes_evaluation_id) : 'pas encore créée'), esc_html(LogiPilot_Publisher::state_label((string) $link->state)), esc_html((string) $link->message));
        }
        if (!$links) {
            echo '<tr><td colspan="4">Aucune évaluation LogiExam liée pour le moment.</td></tr>';
        }
        echo '</tbody></table><h2>Notes envoyées dans LogiNotes</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Évaluation</th><th>Élève</th><th>Version</th><th>Note</th><th>État</th><th>Détail</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $user = get_userdata((int) $row->wp_user_id);
            $exam = class_exists('LogiEval_Secure_Evaluations') ? LogiEval_Secure_Evaluations::get_evaluation((int) $row->exam_evaluation_id) : null;
            echo '<tr><td>' . esc_html(mysql2date('d.m.Y H:i', (string) $row->updated_at)) . '</td><td>' . esc_html($exam['title'] ?? ('#' . $row->exam_evaluation_id)) . '</td><td>' . esc_html($user ? $user->display_name : '—') . '</td><td>' . (int) $row->revision . '</td><td>' . esc_html((string) $row->grade) . '</td><td>' . esc_html(LogiPilot_Publisher::state_label((string) $row->state)) . '</td><td>' . esc_html((string) $row->message) . '</td><td>';
            if (in_array($row->state, array('error', 'pending'), true)) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('logipilot_retry_' . (int) $row->id);
                echo '<input type="hidden" name="action" value="logipilot_retry"><input type="hidden" name="publication_id" value="' . (int) $row->id . '"><button class="button">Relancer</button></form>';
            }
            if ($row->state === 'conflict' && current_user_can('lp_notes_edit')) {
                foreach (array('exam' => 'Garder la note LogiExam', 'notes' => 'Garder la note saisie dans LogiNotes') as $choice => $label) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    wp_nonce_field('logipilot_resolve_' . (int) $row->id);
                    echo '<input type="hidden" name="action" value="logipilot_resolve"><input type="hidden" name="choice" value="' . esc_attr($choice) . '"><input type="hidden" name="publication_id" value="' . (int) $row->id . '"><button class="button">' . esc_html($label) . '</button></form>';
                }
            }
            echo '</td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="8">Aucune note publiée depuis LogiExam pour le moment.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function render_log(): void
    {
        if (!current_user_can('lp_admin_permissions')) {
            wp_die('Accès refusé.', 403);
        }
        global $wpdb;

        $rows = $wpdb->get_results('SELECT * FROM ' . LogiPilot_Install::table('log') . ' ORDER BY id DESC LIMIT 300');
        echo '<div class="wrap logipilot-wrap"><h1>LogiOne · Journal</h1><table class="widefat striped"><thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Détail</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $user = get_userdata((int) $row->user_id);
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td></tr>', esc_html((string) $row->created_at), esc_html($user ? $user->display_name : '—'), esc_html((string) $row->action), esc_html($row->object_type . ' #' . $row->object_id), esc_html(mb_substr((string) $row->data, 0, 300)));
        }
        echo '</tbody></table></div>';
    }
}
