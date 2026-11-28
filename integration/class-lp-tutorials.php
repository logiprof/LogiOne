<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LogiOne 1.55.1 : tutoriels vidéo des enseignants.
 *
 * - Un bouton « ▶ Tutoriel vidéo » dans le bandeau du portail quand un outil est ouvert, et en haut des écrans
 *   d'administration de l'outil (LogiNotes, LogiInfo, LogiClass…).
 * - La vidéo s'ouvre dans une fenêtre sur le site (lecteur YouTube sans cookies) avec « Ouvrir sur YouTube ↗ ».
 * - Liens réglables dans LogiOne › Tutoriels vidéo (option « logione_tutoriels ») ; un champ vide reprend le lien par défaut,
 *   « - » masque le bouton. LogiEval et LogiProjet gardent leur propre bouton (réglages de leur module).
 */
final class LogiPilot_Tutorials
{
    public const OPTION = 'logione_tutoriels';
    public const SLUG = 'logione-tutoriels';

    /** Liens par défaut (vidéos publiées par LogiProf). */
    public const DEFAULTS = array(
        'classe' => 'https://youtu.be/z0yjiku0lTo',
        'progress' => 'https://youtu.be/8dilj8ZyYbs',
        'notes' => 'https://youtu.be/nEI_Aiz_VCs',
        'exam' => 'https://youtu.be/CnTOheLHPM0',
        'live' => 'https://youtu.be/CPbkm-uD0wo',
        'logi3d' => 'https://youtu.be/INDj-5urXXI',
        'logiinfo' => 'https://youtu.be/1pCvk4L72Lk',
        'planning' => '',
        'logiplay' => '',
    );
    public const EVAL_DEFAULT = 'https://youtu.be/2VRtiNXhq64';

    /** Écrans d'administration de chaque outil (début du paramètre « page »). */
    private const ADMIN_PAGES = array(
        'classe' => array('gca_app'),
        'progress' => array('logiprogress'),
        'notes' => array('gne_app'),
        'exam' => array('logiexam'),
        'live' => array('logilive'),
        'logi3d' => array('logi3d'),
        'logiinfo' => array('fw-remises', 'logiinfo'),
        'planning' => array('logiplanning'),
        'logiplay' => array('jeux-logistique'),
    );

    private static bool $assets = false;

    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'menu'), 40);
        add_action('admin_post_logione_tutoriels', array(__CLASS__, 'save'));
        add_action('admin_notices', array(__CLASS__, 'admin_button'));
        add_action('init', array(__CLASS__, 'seed_eval'));
    }

    /** LogiEval a son propre réglage : il reçoit le lien par défaut s'il est encore vide (jamais écrasé). */
    public static function seed_eval(): void
    {
        if (get_option('logione_tutoriels_eval_seeded')) {
            return;
        }
        if ((string) get_option('logieval_tutorial_url', '') === '') {
            update_option('logieval_tutorial_url', self::EVAL_DEFAULT);
        }
        update_option('logione_tutoriels_eval_seeded', 1, false);
    }

    public static function url(string $tool): string
    {
        $saved = get_option(self::OPTION, array());
        $saved = is_array($saved) ? $saved : array();
        $v = isset($saved[$tool]) ? trim((string) $saved[$tool]) : '';
        if ($v === '-') {
            return '';
        }

        return $v !== '' ? $v : (string) (self::DEFAULTS[$tool] ?? '');
    }

    /** Lien YouTube (watch, youtu.be, shorts, embed, live) ou fichier vidéo → données du lecteur ; null si non reconnu. */
    public static function video(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('~\.(mp4|webm|m4v)(\?.*)?$~i', $url)) {
            return array('type' => 'file', 'src' => esc_url_raw($url), 'watch' => esc_url_raw($url));
        }
        if (!preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/))([A-Za-z0-9_-]{11})~', $url, $m)) {
            return null;
        }
        $start = preg_match('~[?&](?:t|start)=(\d+)~', $url, $s) ? (int) $s[1] : 0;

        return array(
            'type' => 'youtube',
            'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1&playsinline=1&autoplay=1' . ($start ? '&start=' . $start : ''),
            'watch' => 'https://www.youtube.com/watch?v=' . $m[1] . ($start ? '&t=' . $start . 's' : ''),
        );
    }

    /** Bouton « ▶ Tutoriel vidéo » d'un outil ('' s'il n'y a pas de vidéo). */
    public static function button(string $tool, string $label, string $class = 'lp-tuto-btn'): string
    {
        $v = self::video(self::url($tool));
        if (!$v) {
            return '';
        }

        return '<button type="button" class="' . esc_attr($class) . '" data-lp-tuto="' . esc_attr((string) wp_json_encode($v)) . '" data-lp-tuto-title="' . esc_attr('Tutoriel ' . $label) . '">▶ Tutoriel vidéo</button>' . self::assets();
    }

    /** Fenêtre vidéo commune (portail et administration), écrite une seule fois par page. */
    private static function assets(): string
    {
        if (self::$assets) {
            return '';
        }
        self::$assets = true;

        return '<style>.lp-tuto-bg{position:fixed;inset:0;z-index:100000;background:rgba(10,14,20,.74);display:flex;align-items:center;justify-content:center;padding:16px}.lp-tuto-box{width:min(980px,96vw);background:#111;border-radius:12px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.5)}.lp-tuto-frame{position:relative;padding-top:56.25%}.lp-tuto-frame iframe,.lp-tuto-frame video{position:absolute;inset:0;width:100%;height:100%;border:0;background:#000}.lp-tuto-bar{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 12px;background:#313a45;color:#fff;font:600 14px/1.3 system-ui,sans-serif}.lp-tuto-bar a,.lp-tuto-bar button{border:0;border-radius:8px;padding:8px 14px;font:600 14px/1.2 system-ui,sans-serif;background:#fff;color:#252c35!important;text-decoration:none;cursor:pointer}.lp-tuto-bar button{background:#f5c400}.lp-tuto-bar span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            . '.lp-tuto-btn{display:inline-flex;align-items:center;gap:6px;font:600 13px/1.2 inherit;padding:7px 13px;border-radius:20px;border:1.5px solid currentColor;background:transparent;color:inherit;cursor:pointer;white-space:nowrap}.lp-tuto-btn:hover{color:#f5c400}'
            . '.lp-tuto-notice{display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-left-color:#067030!important}.lp-tuto-notice .lp-tuto-btn{color:#067030;border-color:#067030;margin:6px 0}.lp-tuto-notice .lp-tuto-btn:hover{background:#067030;color:#fff}</style>'
            . '<script>(function(){if(window.lpTuto){return;}window.lpTuto=1;document.addEventListener("click",function(e){var b=e.target.closest("[data-lp-tuto]");if(!b){return;}e.preventDefault();var v;try{v=JSON.parse(b.getAttribute("data-lp-tuto"));}catch(x){return;}'
            . 'var d=document.createElement("div");d.className="lp-tuto-bg";var t=b.getAttribute("data-lp-tuto-title")||"Tutoriel vidéo";'
            . 'var m=v.type==="youtube"?"<iframe src=\""+v.src+"\" title=\"Tutoriel vidéo\" allow=\"autoplay; encrypted-media; picture-in-picture; fullscreen\" allowfullscreen></iframe>":"<video src=\""+v.src+"\" controls autoplay playsinline></video>";'
            . 'd.innerHTML="<div class=\"lp-tuto-box\" role=\"dialog\" aria-modal=\"true\"><div class=\"lp-tuto-frame\">"+m+"</div><div class=\"lp-tuto-bar\"><span></span><a target=\"_blank\" rel=\"noopener\">"+(v.type==="youtube"?"Ouvrir sur YouTube ↗":"Ouvrir la vidéo ↗")+"</a><button type=\"button\" data-c>Fermer ✕</button></div></div>";'
            . 'd.querySelector("span").textContent=t;d.querySelector("a").href=v.watch;document.body.appendChild(d);var k=function(ev){if(ev.key==="Escape"){c();}};var c=function(){if(d.parentNode){d.parentNode.removeChild(d);}document.removeEventListener("keydown",k);};document.addEventListener("keydown",k);'
            . 'd.addEventListener("click",function(ev){if(ev.target===d||ev.target.closest("[data-c]")){c();}});d.querySelector("[data-c]").focus();});})();</script>';
    }

    /** En haut des écrans d'administration d'un outil. */
    public static function admin_button(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ($page === '') {
            return;
        }
        foreach (self::ADMIN_PAGES as $tool => $prefixes) {
            foreach ($prefixes as $p) {
                if (strpos($page, $p) === 0) {
                    $label = LogiPilot_Plugin::MODULES[$tool]['label'] ?? $tool;
                    $btn = self::button($tool, $label);
                    if ($btn !== '') {
                        echo '<div class="notice notice-info lp-tuto-notice"><p style="margin:0">Besoin d’aide pour <strong>' . esc_html($label) . '</strong> ? Regardez le tutoriel vidéo.</p>' . $btn . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
                    }
                    return;
                }
            }
        }
    }

    /* ---------- Réglages : LogiOne › Tutoriels vidéo ---------- */

    public static function menu(): void
    {
        add_submenu_page('logione', 'Tutoriels vidéo', 'Tutoriels vidéo', 'manage_options', self::SLUG, array(__CLASS__, 'render_page'));
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Accès refusé.'));
        }
        $saved = get_option(self::OPTION, array());
        $saved = is_array($saved) ? $saved : array();
        echo '<div class="wrap"><h1>Tutoriels vidéo des enseignants</h1>';
        if (isset($_GET['ok'])) { // phpcs:ignore WordPress.Security.NonceVerification
            echo '<div class="notice notice-success is-dismissible"><p>Liens enregistrés.</p></div>';
        }
        echo '<p>Le bouton <strong>« ▶ Tutoriel vidéo »</strong> s’affiche dans le bandeau du portail LogiOne quand un outil est ouvert, et en haut de ses écrans d’administration. La vidéo s’ouvre dans une fenêtre du site (lecteur YouTube sans cookies) avec un bouton « Ouvrir sur YouTube ». Collez un lien YouTube ou l’adresse d’un fichier MP4 de la Médiathèque (lu sans publicité). Laissez vide pour le lien par défaut ; tapez <code>-</code> pour masquer le bouton.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('logione_tutoriels');
        echo '<input type="hidden" name="action" value="logione_tutoriels"><table class="form-table" role="presentation">';
        foreach (self::DEFAULTS as $tool => $def) {
            if (!isset(LogiPilot_Plugin::MODULES[$tool])) {
                continue;
            }
            $label = LogiPilot_Plugin::MODULES[$tool]['label'];
            $cur = self::url($tool);
            $ok = self::video($cur);
            echo '<tr><th scope="row"><label for="lp-tuto-' . esc_attr($tool) . '">' . esc_html($label) . '</label></th><td>'
                . '<input type="text" class="large-text" id="lp-tuto-' . esc_attr($tool) . '" name="tuto[' . esc_attr($tool) . ']" value="' . esc_attr((string) ($saved[$tool] ?? '')) . '" placeholder="' . esc_attr($def !== '' ? $def : 'https://youtu.be/…') . '">'
                . '<p class="description">' . ($ok ? '✓ Bouton affiché' . (($saved[$tool] ?? '') === '' ? ' (lien par défaut)' : '') . ' · ' . self::button($tool, $label) : ($cur === '' ? 'Aucune vidéo : pas de bouton.' : '<span style="color:#b32d2e">Lien non reconnu</span>')) . '</p></td></tr>';
        }
        echo '<tr><th scope="row">LogiEval</th><td><p class="description">Réglé dans <a href="' . esc_url(admin_url('admin.php?page=logieval-settings')) . '">LogiEval › Réglages › Tutoriel vidéo</a> (bouton à côté de « Aide » et sur la page de connexion).</p></td></tr>';
        echo '<tr><th scope="row">LogiProjet</th><td><p class="description">Réglé dans LogiProjet › Réglages › Aide (tutoriels et manuels des apprentis et des enseignants).</p></td></tr>';
        echo '</table>';
        submit_button('Enregistrer');
        echo '</form></div>';
    }

    public static function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Accès refusé.'));
        }
        check_admin_referer('logione_tutoriels');
        $in = isset($_POST['tuto']) && is_array($_POST['tuto']) ? wp_unslash($_POST['tuto']) : array();
        $out = array();
        foreach (array_keys(self::DEFAULTS) as $tool) {
            $v = trim((string) ($in[$tool] ?? ''));
            $out[$tool] = $v === '-' ? '-' : esc_url_raw($v, array('http', 'https'));
        }
        update_option(self::OPTION, $out, false);
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&ok=1'));
        exit;
    }
}
