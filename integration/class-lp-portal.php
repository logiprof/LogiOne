<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Portail LogiOne pour le site public : shortcode [logione].
 *
 * - Chaque utilisateur connecté voit les outils auxquels il a droit (LogiNotes, LogiProgress,
 *   LogiExam, LogiClass) et les ouvre sur la même page, sans passer par l'administration.
 * - LogiOne est réservé aux enseignants : les élèves passent leurs évaluations avec le shortcode
 *   LogiExam habituel [logiexam_student], qui reste inchangé.
 * - Ordre d'affichage : LogiClass, LogiProgress, LogiNotes, LogiExam, LogiEval, LogiPlanning, LogiLive, puis les tuiles de contenu créées par l'administration.
 * - Les responsables voient un lien vers le back-office LogiOne (droits, modules, espaces…).
 *
 * Attributs : [logione module="notes"] ouvre directement un outil ;
 * [logione modules="notes,exam"] limite les outils proposés sur cette page.
 */
final class LogiPilot_Portal
{
    public const PARAM = 'lp_module';
    public const META_LAST = 'logipilot_portal_module';
    /** Tuile de contenu dépliée sur l'accueil (sans JavaScript, l'adresse suffit). */
    public const PARAM_TILE = 'lp_tuile';

    private static int $page_id = 0;
    private static string $module = '';

    public static function init(): void
    {
        add_shortcode('logione', array(__CLASS__, 'render'));
        // Ancien nom, conservé pour les pages déjà créées.
        add_shortcode('logipilot', array(__CLASS__, 'render'));
    }

    /** Outils du portail : libellé, présentation, droit requis, module LogiOne et shortcode affiché. */
    public static function tools(): array
    {
        $labels = array();
        foreach (LogiPilot_Plugin::MODULES as $key => $module) {
            $labels[$key] = $module['label'];
        }

        $tools = array(
            'classe' => array(
                'label' => $labels['classe'], 'tag' => 'gestion_apprentis', 'cap' => 'lp_classe_view', 'module' => 'classe', 'color' => '#ea580c',
                'text' => 'Le suivi de vos apprentis.',
                'points' => array('Fiches apprentis et formateurs', 'Absences, retards et situations à traiter', 'E-mails et rapports PDF'),
            ),
            'progress' => array(
                'label' => $labels['progress'], 'tag' => 'logiprogress', 'cap' => 'lp_progress_view', 'module' => 'progress', 'color' => '#059669',
                'text' => 'La progression de vos groupes dans les cours en ligne.',
                'points' => array('Avancement par élève et par leçon', 'Élèves inactifs repérés', 'Export du suivi'),
            ),
            'notes' => array(
                'label' => $labels['notes'], 'tag' => 'loginotes', 'cap' => 'lp_notes_view', 'module' => 'notes', 'color' => '#2563eb',
                'text' => 'Le carnet de notes de vos classes.',
                'points' => array('Classes, élèves et semestres', 'Saisie des points, note calculée automatiquement', 'Relevés et exports PDF'),
            ),
            'exam' => array(
                'label' => $labels['exam'], 'tag' => 'logiexam_teacher', 'cap' => 'lp_exam_teach', 'module' => 'exam', 'color' => '#7c3aed',
                'text' => 'Vos évaluations numériques sécurisées.',
                'points' => array('Préparation avec la banque de questions', 'Surveillance de la passation en direct', 'Correction et publication dans LogiNotes'),
            ),
            'eval' => array(
                'label' => $labels['eval'], 'tag' => 'logieval_login', 'cap' => 'lp_eval_use', 'module' => 'eval', 'color' => '#0e7490',
                'text' => 'La posture professionnelle de vos élèves, évaluée en équipe.',
                'points' => array('Vos évaluations à remplir', 'Suivi des collègues (maître de classe)', 'Résultats et impressions'),
            ),
            'planning' => array(
                'label' => $labels['planning'], 'tag' => 'logiplanning_enseignant', 'cap' => 'lp_planning_view', 'module' => 'planning', 'color' => '#1d4ed8',
                'text' => 'Les plans de formation de vos classes.',
                'points' => array('Prochains cours et journées', 'Le planning tel que le voient les élèves', 'Import et modification des journées'),
            ),
            'live' => array(
                'label' => $labels['live'], 'tag' => 'logilive', 'cap' => 'lp_live_view', 'module' => 'live', 'color' => '#e11d48',
                'text' => 'Votre classe en direct.',
                'points' => array('Élèves connectés en ce moment', 'Leçon ou page ouverte', 'Dernière activité de chacun'),
            ),
            // LogiOne 1.51.1 : LogiProjet (droit d'enseignant propre à LogiProjet : rôles choisis dans ses réglages).
            'projet' => array(
                'label' => $labels['projet'], 'tag' => 'logiprojet pleine_largeur="non"', 'cap' => 'logiprojet_enseigner', 'can' => array('LogiProjet_Access', 'is_teacher'), 'module' => 'projet', 'color' => '#c2410c',
                'text' => 'Les travaux notés en groupe de vos apprentis.',
                'points' => array('Dépôt des présentations par les apprentis', 'Correction assistée par l’IA', 'Notes et retours publiés aux apprentis'),
            ),
            // LogiOne 1.53.16 : Logi3D, catalogue des animations 3D.
            'logi3d' => array(
                'label' => $labels['logi3d'], 'tag' => 'logi3d_catalogue', 'cap' => 'edit_posts', 'can' => array('LogiOne_Logi3D', 'can_view'), 'module' => 'logi3d', 'color' => '#4338ca',
                'text' => 'Les animations 3D à projeter en classe.',
                'points' => array('Surfaces et volumes de l’entrepôt', 'Aperçu et plein écran', 'Code court pour vos pages et leçons'),
            ),
            // LogiOne 1.53.17 : LogiInfo, formations Word, Excel, PowerPoint et Présentations.
            'logiinfo' => array(
                'label' => $labels['logiinfo'], 'tag' => 'logiinfo_portail', 'cap' => 'fwr_corriger', 'can' => array('LogiOne_LogiInfo', 'can_view'), 'module' => 'logiinfo', 'color' => '#0369a1',
                'text' => 'Les formations informatiques de vos apprentis.',
                'points' => array('Exercices rendus et contrôle automatique', 'Progression des élèves', 'Correction et retours publiés'),
            ),
        );

        // Tuiles de contenu créées par l'administration, à la suite des outils.
        if (class_exists('LogiPilot_Tiles_Portal') && LogiPilot_Plugin::is_loaded('tiles')) {
            $tools += LogiPilot_Tiles_Portal::tools();
        }

        return $tools;
    }

    /** Pictogramme d'un outil ou d'une tuile de contenu. */
    private static function art(string $key, array $tool): string
    {
        if (!empty($tool['image'])) {
            return '<img class="lpp-tile-img" src="' . esc_url($tool['image']) . '" alt="" loading="lazy" decoding="async">';
        }

        return self::iso(isset($tool['kind']) ? 'tile' : $key);
    }

    /** Noki, la mascotte LogiProf, sur sa palette (bandeau du portail). */
    public static function noki(): string
    {
        $base = LOGIPILOT_URL . 'assets/noki-logione';

        return '<picture><source srcset="' . esc_url($base . '.webp') . '" type="image/webp"><img class="lpp-noki" src="' . esc_url($base . '.png') . '" alt="" width="408" height="555" decoding="async"></picture>';
    }

    /** Logo « LogiOne by LogiProf » (fichier SVG du plugin). */
    public static function logo(string $class = 'lpp-logo', bool $on_dark = false): string
    {
        $file = $on_dark ? 'logione-logo-blanc.svg' : 'logione-logo.svg';

        return '<img class="' . esc_attr($class) . '" src="' . esc_url(LOGIPILOT_URL . 'assets/' . $file) . '" alt="LogiOne by LogiProf" width="248" height="65" decoding="async">';
    }

    /** Pictogramme isométrique (cubes du logo LogiProf) : « one » pour l'accueil, ou la clé d'un outil. */
    private static function iso(string $key): string
    {
        static $icons = null;
        if ($icons === null) {
            $icons = (array) include LOGIPILOT_DIR . 'integration/portal-iso.php';
        }

        return (string) ($icons[$key] ?? '');
    }

    /** Pictogrammes en ligne (aucun fichier externe). */
    private static function icon(string $key): string
    {
        $paths = array(
            'notes' => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/><path d="M9 8h6"/>',
            'progress' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
            'exam' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 3v2h6V3"/><path d="m9 13 2 2 4-4"/>',
            'classe' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.2A5 5 0 0 1 21 19"/>',
            'pilot' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
            'back' => '<path d="M15 6l-6 6 6 6"/>',
            'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        );

        return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$key] ?? '') . '</svg>';
    }

    /** Outils que l'utilisateur courant peut ouvrir. */
    public static function available(array $only = array()): array
    {
        $out = array();
        foreach (self::tools() as $key => $tool) {
            if ($only && !in_array($key, $only, true)) {
                continue;
            }
            if (!LogiPilot_Plugin::is_loaded($tool['module']) || !shortcode_exists(strtok($tool['tag'], ' '))) {
                continue;
            }
            // Un outil auquel l'utilisateur n'a pas droit n'apparaît jamais.
            $allowed = isset($tool['can']) && is_callable($tool['can']) ? (bool) call_user_func($tool['can']) : current_user_can($tool['cap']);
            if (!$allowed) {
                continue;
            }
            $out[$key] = $tool;
        }

        return $out;
    }

    public static function render($atts = array()): string
    {
        $atts = shortcode_atts(array('module' => '', 'modules' => ''), (array) $atts, 'logione');
        $only = array_filter(array_map('sanitize_key', explode(',', (string) $atts['modules'])));
        $fixed = sanitize_key((string) $atts['module']);
        if ($fixed !== '') {
            $only = array($fixed);
        }

        if (!is_user_logged_in()) {
            $login = wp_login_url(get_permalink() ?: home_url('/'));
            return '<div class="logipilot-portal"><header class="lpp-hero"><div class="lpp-hero-top"><span class="lpp-brand">' . self::logo('lpp-logo', true) . '</span></div>'
                . '<div class="lpp-hero-main"><div class="lpp-hero-text"><h2 class="lpp-hello">Espace enseignants</h2><p>Connectez-vous pour retrouver vos outils LogiOne.</p><p><a class="lpp-button" href="' . esc_url($login) . '">Se connecter</a></p></div><div class="lpp-one">' . self::noki() . '</div></div></header></div>';
        }

        $tools = self::available($only);
        $current = self::current_module($tools, $fixed);
        self::$page_id = (int) get_queried_object_id();
        $base = self::$page_id ? get_permalink(self::$page_id) : home_url(add_query_arg(array()));

        $user = wp_get_current_user();
        $first = trim((string) $user->first_name) !== '' ? (string) $user->first_name : (string) $user->display_name;
        $home = remove_query_arg(self::PARAM, $base);
        // Largeur de la mosaïque d'accueil : le bandeau s'aligne dessus.
        $home_layout = ($current === '' && class_exists('LogiPilot_Layout')) ? LogiPilot_Layout::get() : null;
        $width_class = $home_layout !== null ? ' lpp-home-width-' . sanitize_html_class((string) $home_layout['width']) : '';
        $html = '<div class="logipilot-portal' . ($current !== '' ? ' is-tool' : '') . $width_class . '">';

        // Bandeau : logo, compte, administration
        $html .= '<header class="lpp-hero"><div class="lpp-hero-top"><a class="lpp-brand" href="' . esc_url($home) . '">' . self::logo('lpp-logo', true) . '</a>';
        $html .= '</div>';

        if (!$tools) {
            return $html . '<div class="lpp-hero-main"><div class="lpp-hero-text"><h2 class="lpp-hello">LogiOne est réservé aux enseignants.</h2><p>Si vous êtes enseignant, demandez à l’administrateur de LogiOne de vous donner les droits nécessaires. Pour passer une évaluation, utilisez la page indiquée par votre enseignant.</p></div></div></header></div>';
        }

        if ($current === '') {
            $html .= '<div class="lpp-hero-main"><div class="lpp-hero-text"><h2 class="lpp-hello">Bonjour ' . esc_html($first) . '</h2><p class="lpp-about">LogiOne réunit vos outils d’enseignant en un seul endroit : le suivi de vos apprentis, la progression de vos groupes dans les cours en ligne, votre classe en direct, vos notes, vos évaluations numériques, la posture professionnelle de vos élèves et les plans de formation. Vos données restent les vôtres : aucun collègue ne les voit.</p><p class="lpp-hint">Choisissez un outil ci-dessous : il s’ouvre dans cette page.</p></div><div class="lpp-one">' . self::noki() . '</div></div></header>';
            $layout = class_exists('LogiPilot_Layout') ? LogiPilot_Layout::get() : null;
            if ($layout !== null) {
                // Disposition choisie par l'administration : une seule mosaïque, dans l'ordre et aux tailles choisis.
                $open = sanitize_key(wp_unslash($_GET[self::PARAM_TILE] ?? ''));
                $html .= '<div class="lpp-grid lpp-layout lpp-cols-' . (int) $layout['columns'] . ' lpp-width-' . esc_attr($layout['width']) . '" style="--cols:' . (int) $layout['columns'] . '" data-lpp-tiles data-lpp-param="' . esc_attr(self::PARAM_TILE) . '">';
                foreach (LogiPilot_Layout::arrange($tools, $layout) as $key => $tool) {
                    $html .= self::tile($key, $tool, $base, $open === $key, LogiPilot_Layout::size($layout, $key));
                    if (($tool['kind'] ?? '') === 'content' && empty($tool['direct'])) {
                        $html .= self::panel($key, $tool, $base, $open === $key);
                    }
                }

                return $html . '</div>' . self::panel_script() . self::layout_link() . '</div>';
            }
            $builtin = array_filter($tools, static function ($t) { return !isset($t['kind']); });
            $extra = array_diff_key($tools, $builtin);
            if ($builtin) {
                $html .= '<div class="lpp-grid lpp-n' . count($builtin) . '">';
                foreach ($builtin as $key => $tool) {
                    $html .= self::tile($key, $tool, $base);
                }
                $html .= '</div>';
            }
            if ($extra) {
                $open = sanitize_key(wp_unslash($_GET[self::PARAM_TILE] ?? ''));
                $html .= '<div class="lpp-grid lpp-more" data-lpp-tiles data-lpp-param="' . esc_attr(self::PARAM_TILE) . '">';
                foreach ($extra as $key => $tool) {
                    $html .= self::tile($key, $tool, $base, $open === $key);
                    // Contenu de la tuile : déplié juste en dessous, sur la page des tuiles.
                    if (($tool['kind'] ?? '') === 'content' && empty($tool['direct'])) {
                        $html .= self::panel($key, $tool, $base, $open === $key);
                    }
                }
                $html .= '</div>' . self::panel_script();
            }

            return $html . self::layout_link() . '</div>';
        }

        // Outil ouvert : retour à l'accueil LogiOne et accès direct aux autres outils, dans le bandeau
        $tool = $tools[$current];
        $html .= '<div class="lpp-toolbar">';
        if ($fixed === '' && count($tools) > 1) {
            $html .= '<a class="lpp-back" href="' . esc_url($home) . '">' . self::icon('back') . 'Retour à LogiOne</a>';
        }
        $html .= '<span class="lpp-current lpp-tile-' . esc_attr($current) . '"><span class="lpp-current-art">' . self::art($current, $tool) . '</span>' . esc_html($tool['label']) . '</span>';
        if ($fixed === '' && count($tools) > 1) {
            $html .= '<nav class="lpp-switch logipilot-portal-nav" aria-label="Autres outils LogiOne">';
            $menu = '';
            foreach ($tools as $key => $other) {
                if ($key !== $current) {
                    $html .= '<a href="' . esc_url(add_query_arg(self::PARAM, $key, $base)) . '">' . esc_html($other['label']) . '</a>';
                    $menu .= '<a href="' . esc_url(add_query_arg(self::PARAM, $key, $base)) . '"><span class="lpp-menu-art">' . self::art($key, $other) . '</span>' . esc_html($other['label']) . '</a>';
                }
            }
            $html .= '</nav>';
            // 1.39.0 : sur téléphone, les autres outils sont rangés dans un menu « Outils » (une seule ligne de bandeau).
            $html .= '<details class="lpp-switch-menu"><summary aria-label="Autres outils LogiOne"><span class="lpp-menu-icon" aria-hidden="true"></span>Outils</summary>'
                . '<div class="lpp-switch-menu-list"><a class="lpp-menu-home" href="' . esc_url($home) . '">' . self::icon('back') . 'Accueil LogiOne</a>' . $menu . '</div></details>';
        }
        $html .= '</div></header>';

        // L'outil s'affiche dans la page : ses liens internes gardent l'outil ouvert.
        self::$module = $current;
        add_filter('page_link', array(__CLASS__, 'keep_module_in_link'), 10, 2);
        add_filter('post_link', array(__CLASS__, 'keep_module_in_link'), 10, 2);
        $content = do_shortcode('[' . $tool['tag'] . ']');
        remove_filter('page_link', array(__CLASS__, 'keep_module_in_link'), 10);
        remove_filter('post_link', array(__CLASS__, 'keep_module_in_link'), 10);
        self::$module = '';

        // Tout s'ouvre dans l'onglet courant.
        $content = preg_replace_callback('/<a\b[^>]*>/i', static function (array $m): string {
            // Les téléchargements (PDF, exports) peuvent garder leur nouvel onglet ; la navigation reste dans la page.
            if (stripos($m[0], 'admin-post.php') !== false || stripos($m[0], 'admin-ajax.php') !== false || stripos($m[0], 'data-lpee-newtab') !== false || preg_match('/\.(pdf|zip|csv|xlsx?)\b/i', $m[0])) {
                return $m[0];
            }
            return (string) preg_replace('/\s+target=(["\'])_blank\1/i', '', $m[0]);
        }, (string) $content);

        return $html . '<div class="lpp-tool logipilot-portal-tool logipilot-portal-tool-' . esc_attr($current) . '">' . $content . '</div></div>';
    }

    /** Outil ouvert en ce moment dans le portail ('' hors du portail) : les modules adaptent leurs liens. */
    public static function current_tool(): string
    {
        return self::$module;
    }

    /** Une tuile de l'accueil : outil, tuile de contenu ou « Gérer les tuiles ». */
    private static function tile(string $key, array $tool, string $base, bool $open = false, string $size = ''): string
    {
        $sized = $size !== '' ? ' lpp-size-' . sanitize_html_class($size) : '';
        $url = esc_url(add_query_arg(self::PARAM, $key, $base));
        $kind = (string) ($tool['kind'] ?? '');
        $attrs = '';
        $cta = 'Ouvrir';
        if ($kind === 'content') {
            if (!empty($tool['direct'])) {
                // Tuile à un seul lien : le lien directement, dans l'onglet choisi.
                $url = esc_url($tool['direct']['url']);
                $attrs = !empty($tool['direct']['new']) ? ' target="_blank" rel="noopener noreferrer"' : '';
            } else {
                $url = esc_url(add_query_arg(self::PARAM_TILE, $open ? false : $key, remove_query_arg(self::PARAM_TILE, $base)));
                $attrs = ' role="button" data-lpp-toggle="' . esc_attr($key) . '" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="lpp-panel-' . esc_attr($key) . '"';
                $cta = '<span class="lpp-cta-open">Afficher</span><span class="lpp-cta-close">Masquer</span>';
            }
            $attrs .= isset($tool['style']) ? ' style="' . esc_attr($tool['style']) . '"' : '';
        }
        if ($kind === 'manage') {
            return '<a class="lpp-tile logipilot-portal-card lpp-ctile lpp-manage' . $sized . '" href="' . $url . '"><span class="lpp-plus" aria-hidden="true">+</span>'
                . '<span class="lpp-tile-body"><strong>' . esc_html($tool['label']) . '</strong><span class="lpp-tile-text">' . esc_html($tool['text']) . '</span>'
                . '<span class="lpp-tile-cta">Créer ou modifier</span></span></a>';
        }
        $class = $kind === 'content' ? 'lpp-ctile' . (isset($tool['style']) ? '' : ' lpp-c-' . sanitize_html_class((string) $tool['color'])) . ($open ? ' is-active' : '') : 'lpp-tile-' . $key;
        $points = '';
        foreach ($tool['points'] as $point) {
            $points .= '<li>' . esc_html($point) . '</li>';
        }

        return '<a class="lpp-tile logipilot-portal-card ' . esc_attr($class . $sized) . '" href="' . $url . '"' . $attrs . '>'
            . '<span class="lpp-tile-art">' . self::art($key, $tool) . '</span>'
            . '<span class="lpp-tile-body"><strong>' . esc_html($tool['label']) . '</strong>'
            . ($tool['text'] !== '' ? '<span class="lpp-tile-text">' . esc_html($tool['text']) . '</span>' : '')
            . ($points !== '' ? '<ul>' . $points . '</ul>' : '')
            . '<span class="lpp-tile-cta">' . ($kind === 'content' ? $cta : 'Ouvrir ' . esc_html($tool['label'])) . '</span></span></a>';
    }

    /** Lien « Disposition de cette page », pour l'administration seulement. */
    private static function layout_link(): string
    {
        if (!class_exists('LogiPilot_Layout') || !current_user_can(LogiPilot_Layout::CAP)) {
            return '';
        }

        return '<p class="lpp-layout-edit"><a href="' . esc_url(LogiPilot_Layout::edit_url()) . '">' . self::icon('pilot') . 'Modifier la disposition de cette page</a><span>Réservé à l’administration.</span></p>';
    }

    /** Première page publiée qui contient le portail LogiOne (pour le lien « Voir la page »). */
    public static function find_page_url(): string
    {
        global $wpdb;
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page', 'post') AND post_content LIKE %s ORDER BY post_type = 'page' DESC, ID ASC LIMIT 1",
            '%' . $wpdb->esc_like('[logione') . '%'
        ));

        return $id ? (string) get_permalink($id) : '';
    }

    /** Panneau d'une tuile de contenu, sous la tuile (pleine largeur de la grille). */
    private static function panel(string $key, array $tool, string $base, bool $open): string
    {
        $close = esc_url(remove_query_arg(self::PARAM_TILE, $base));

        return '<section class="lpp-panel" id="lpp-panel-' . esc_attr($key) . '" data-lpp-panel="' . esc_attr($key) . '"' . (isset($tool['accent']) ? ' style="--accent:' . esc_attr($tool['accent']) . '"' : '') . ($open ? '' : ' hidden') . '>'
            . '<div class="lpp-panel-head"><h3 class="lpp-panel-title" tabindex="-1">' . esc_html($tool['label']) . '</h3>'
            . '<a class="lpp-panel-close" href="' . $close . '" data-lpp-close role="button"><span aria-hidden="true">×</span><span class="screen-reader-text">Fermer</span></a></div>'
            . do_shortcode('[' . $tool['tag'] . ']') . '</section>';
    }

    /** Ouvrir / fermer les panneaux sans recharger la page : script du plugin (assets/portal.js), chargé en pied de page. */
    private static function panel_script(): string
    {
        wp_enqueue_script('logipilot-portal', LOGIPILOT_URL . 'assets/portal.js', array(), LOGIPILOT_VERSION, true);

        return '';
    }

    /**
     * Outil affiché : paramètre lp_module, sinon le dernier outil ouvert quand l'adresse contient
     * des paramètres de cet outil (formulaires de recherche, filtres), sinon l'accueil.
     */
    private static function current_module(array $tools, string $fixed): string
    {
        if ($fixed !== '') {
            return isset($tools[$fixed]) ? $fixed : '';
        }
        if (count($tools) === 1) {
            return (string) array_key_first($tools);
        }
        $user_id = get_current_user_id();
        $requested = sanitize_key(wp_unslash($_GET[self::PARAM] ?? ''));
        if ($requested !== '' && isset($tools[$requested])) {
            update_user_meta($user_id, self::META_LAST, $requested);
            return $requested;
        }
        $ignored = array('page_id', 'p', 'pagename', 'preview', 'lang', self::PARAM);
        $has_tool_params = (bool) array_diff(array_keys($_GET), $ignored);
        if ($has_tool_params) {
            $last = (string) get_user_meta($user_id, self::META_LAST, true);
            if (isset($tools[$last])) {
                return $last;
            }
        }

        return '';
    }

    public static function keep_module_in_link($link, $post)
    {
        $id = is_object($post) ? (int) $post->ID : (int) $post;
        if (self::$module === '' || $id !== self::$page_id || !is_string($link)) {
            return $link;
        }

        return add_query_arg(self::PARAM, self::$module, $link);
    }
}
