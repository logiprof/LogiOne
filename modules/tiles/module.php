<?php
/**
 * Tuiles de contenu de LogiOne : l'administration crée des tuiles (rubriques, liens, pages, outils
 * intégrés) qui s'affichent dans le portail à la suite des outils. Une tuile peut être réservée à des
 * rôles (groupes d'enseignants).
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/tiles.php';

// Modifier les tuiles : droit LogiOne « Tuiles de contenu » (administration par défaut).
add_filter('logipilot_tiles_capability', static function () {
    return 'lp_tiles_edit';
});

final class LogiPilot_Tiles_Portal
{
    /** Préfixe des tuiles dans le portail (paramètre lp_module). */
    public const PREFIX = 't-';
    public const MANAGE = 'tuiles';

    public static function init(): void
    {
        add_shortcode('logipilot_tuile', array(__CLASS__, 'render_tile'));
    }

    /** Tuiles enregistrées, dans l'ordre de l'éditeur. */
    private static function menus(): array
    {
        $menus = array_values(array_filter((array) get_option(LogiPilot_Tiles::OPTION_MENUS, array()), static function ($m) {
            return is_array($m) && !empty($m['id']);
        }));
        usort($menus, static function ($a, $b) {
            return (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0);
        });

        return $menus;
    }

    /**
     * Tuiles du portail pour la personne connectée : celles qu'elle peut voir (contenu non vide et rôle
     * autorisé), puis « Gérer les tuiles » pour l'administration.
     */
    public static function tools(): array
    {
        $out = array();
        if (!is_user_logged_in()) {
            return $out;
        }
        $can_edit = LogiPilot_Tiles::current_user_can_edit();
        foreach (self::menus() as $menu) {
            $id = sanitize_key((string) $menu['id']);
            // Un enseignant voit les tuiles autorisées pour son rôle et qui ont du contenu ; l'administration les voit toutes.
            if ($id === '' || (!$can_edit && trim(LogiPilot_Tiles::render_shortcode(array('menu' => $id))) === '')) {
                continue;
            }
            $settings = (array) ($menu['settings'] ?? array());
            $hex = self::hex((string) ($settings['tile_color'] ?? 'menthe'));
            $out[self::PREFIX . $id] = array(
                'label' => (string) ($menu['name'] ?? $id), 'tag' => 'logipilot_tuile menu="' . $id . '"', 'cap' => 'lp_access', 'module' => 'tiles',
                'text' => (string) ($settings['tile_text'] ?? ''), 'points' => array(), 'kind' => 'content',
                'color' => 'custom', 'style' => self::vars($hex), 'accent' => self::contrast($hex, '#ffffff') < 1.25 ? '#2fae5f' : $hex,
                'menu' => $id, 'direct' => self::direct_link($menu),
                'image' => self::image_url((int) ($settings['tile_icon'] ?? 0)),
            );
        }
        if ($can_edit) {
            $out[self::MANAGE] = array(
                'label' => 'Gérer les tuiles', 'tag' => LogiPilot_Tiles::EDITOR_SHORTCODE, 'cap' => 'lp_tiles_edit', 'module' => 'tiles',
                'text' => 'Créez des tuiles pour les enseignants : rubriques, liens vers vos pages, outils intégrés.', 'points' => array(), 'kind' => 'manage',
            );
        }

        return $out;
    }

    /** Icône importée de la tuile (médiathèque), ou chaîne vide. */
    private static function image_url(int $id): string
    {
        if ($id <= 0 || (!wp_attachment_is_image($id) && get_post_mime_type($id) !== 'image/svg+xml')) {
            return '';
        }
        $url = wp_get_attachment_image_url($id, 'medium');

        return $url ? (string) $url : (string) wp_get_attachment_url($id);
    }

    /** Couleur d'une tuile : couleur libre #rrggbb, ou ancienne couleur nommée. */
    private static function hex(string $color): string
    {
        $named = array('menthe' => '#dcefe3', 'vert' => '#067030', 'anthracite' => '#313a45', 'jaune' => '#f5c400', 'blanc' => '#ffffff');
        if (isset($named[$color])) {
            return $named[$color];
        }

        return sanitize_hex_color($color) ?: '#dcefe3';
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $sum = 0.0;
        foreach (array(0 => 0.2126, 2 => 0.7152, 4 => 0.0722) as $i => $w) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $sum += $w * ($c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4));
        }

        return $sum;
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Variables de la tuile : texte blanc ou anthracite selon le fond, pour rester lisible. */
    private static function vars(string $hex): string
    {
        if (self::contrast($hex, '#ffffff') >= self::contrast($hex, '#313a45')) {
            $v = array('--tile-bg' => $hex, '--tile-fg' => '#ffffff', '--tile-muted' => 'rgba(255,255,255,.82)', '--cta' => self::contrast($hex, '#f5c400') >= 3 ? '#f5c400' : '#ffffff');
        } else {
            $v = array('--tile-bg' => $hex, '--tile-fg' => '#313a45', '--tile-muted' => 'rgba(49,58,69,.78)', '--cta' => self::contrast($hex, '#067030') >= 3 ? '#067030' : '#313a45');
        }
        $v['--gap'] = $hex;
        $out = '';
        foreach ($v as $k => $val) {
            $out .= $k . ':' . $val . ';';
        }

        return $out;
    }

    /**
     * Tuile qui ne contient qu'un seul lien (visible pour cette personne) : la tuile mène directement
     * à ce lien, dans l'onglet choisi dans l'éditeur. Sinon : null (la tuile se déplie).
     */
    private static function direct_link(array $menu): ?array
    {
        $roles = is_user_logged_in() ? (array) wp_get_current_user()->roles : array();
        $top = array_values(array_filter((array) ($menu['items'] ?? array()), static function ($i) use ($roles) {
            $allowed = (array) ($i['allowed_roles'] ?? array());
            return ($i['parent_id'] ?? '') === '' && empty($i['hidden']) && (!$allowed || array_intersect($roles, $allowed));
        }));
        if (count($top) !== 1 || ($top[0]['type'] ?? '') !== 'link' || ($top[0]['destination_type'] ?? '') === 'shortcode') {
            return null;
        }
        $item = $top[0];
        $type = $item['destination_type'] ?? 'page';
        $url = $type === 'page' && !empty($item['page_id']) ? (string) get_permalink((int) $item['page_id']) : ($type === 'url' ? (string) ($item['url'] ?? '') : ($type === 'admin' ? admin_url() : ''));

        return $url !== '' ? array('url' => $url, 'new' => ($item['link_target'] ?? 'auto') === 'new') : null;
    }

    /** Contenu d'une tuile ouverte ; l'administration peut la modifier directement. */
    public static function render_tile($atts = array()): string
    {
        $atts = shortcode_atts(array('menu' => ''), (array) $atts, 'logipilot_tuile');
        $id = sanitize_key((string) $atts['menu']);
        $html = '<div class="lptile">';
        if (LogiPilot_Tiles::current_user_can_edit() && class_exists('LogiPilot_Portal')) {
            $edit = add_query_arg(array(LogiPilot_Portal::PARAM => self::MANAGE, 'lpee_menu' => $id), remove_query_arg(array(LogiPilot_Portal::PARAM, 'lpee_menu', 'updated')));
            $html .= '<div class="lptile-bar"><a class="lptile-switch is-edit" href="' . esc_url($edit) . '">Modifier cette tuile</a>'
                . '<span class="lptile-note">Réservé à l’administration.</span></div>';
        }

        $content = LogiPilot_Tiles::render_shortcode(array('menu' => $id, 'vue' => 'panneau'));
        if (trim($content) === '') {
            $content = '<p class="lptile-empty">Cette tuile est vide ou réservée à d’autres rôles : les enseignants ne la voient pas encore.</p>';
        }

        return $html . $content . '</div>';
    }
}

LogiPilot_Tiles_Portal::init();
