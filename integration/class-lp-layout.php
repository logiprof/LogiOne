<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disposition de la page d'accueil LogiOne (mosaïque des tuiles) : ordre, taille de chaque tuile,
 * nombre de colonnes et largeur. Réglée par l'administration dans « LogiOne › Disposition du portail ».
 * Tant que rien n'est enregistré, l'accueil garde sa présentation d'origine.
 */
final class LogiPilot_Layout
{
    public const OPTION = 'logipilot_portal_layout';
    public const SLUG = 'logione-disposition';
    public const CAP = 'lp_tiles_edit';

    /** Tailles proposées : libellé et description courte. */
    public const SIZES = array(
        'petite' => 'Petite',
        'normale' => 'Normale',
        'haute' => 'Haute (2 rangées)',
        'large' => 'Large (2 colonnes)',
        'pleine' => 'Pleine largeur',
    );

    public const WIDTHS = array('standard' => 'Standard (centrée)', 'large' => 'Toute la largeur de l’écran');

    public static function init(): void
    {
        add_action('admin_post_logipilot_save_layout', array(__CLASS__, 'handle_save'));
    }

    /** Disposition enregistrée, ou null (présentation d'origine). */
    public static function get(): ?array
    {
        $raw = get_option(self::OPTION, null);
        if (!is_array($raw) || empty($raw['order'])) {
            return null;
        }

        return self::sanitize($raw);
    }

    /** Disposition proposée par défaut dans l'éditeur : celle d'origine, traduite en tailles. */
    public static function defaults(array $tools): array
    {
        $sizes = array();
        foreach (array_keys($tools) as $key) {
            $sizes[$key] = in_array($key, array('classe', 'exam'), true) ? 'haute' : 'normale';
        }

        return array('columns' => 3, 'width' => 'standard', 'order' => array_keys($tools), 'sizes' => $sizes);
    }

    public static function sanitize(array $raw): array
    {
        $order = array();
        foreach ((array) ($raw['order'] ?? array()) as $key) {
            $key = sanitize_key((string) $key);
            if ($key !== '' && !in_array($key, $order, true)) {
                $order[] = $key;
            }
        }
        $sizes = array();
        foreach ((array) ($raw['sizes'] ?? array()) as $key => $size) {
            $key = sanitize_key((string) $key);
            $size = sanitize_key((string) $size);
            if ($key !== '' && isset(self::SIZES[$size])) {
                $sizes[$key] = $size;
            }
        }
        $columns = (int) ($raw['columns'] ?? 3);
        $width = sanitize_key((string) ($raw['width'] ?? 'standard'));

        return array(
            'columns' => in_array($columns, array(2, 3, 4), true) ? $columns : 3,
            'width' => isset(self::WIDTHS[$width]) ? $width : 'standard',
            'order' => array_slice($order, 0, 200),
            'sizes' => $sizes,
        );
    }

    /** Outils et tuiles dans l'ordre choisi ; une tuile créée depuis s'ajoute à la fin, en taille normale. */
    public static function arrange(array $tools, array $layout): array
    {
        $out = array();
        foreach ($layout['order'] as $key) {
            if (isset($tools[$key])) {
                $out[$key] = $tools[$key];
            }
        }

        return $out + $tools;
    }

    public static function size(array $layout, string $key): string
    {
        return $layout['sizes'][$key] ?? 'normale';
    }

    public static function edit_url(): string
    {
        return admin_url('admin.php?page=' . self::SLUG);
    }

    /* ------------------------------------------------------------------ */

    public static function handle_save(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Accès refusé.', 403);
        }
        check_admin_referer('logipilot_save_layout');

        if (isset($_POST['lp_layout_reset'])) {
            delete_option(self::OPTION);
            $done = 'reset';
        } else {
            $order = array_filter(array_map('sanitize_key', explode(',', (string) wp_unslash($_POST['lp_layout_order'] ?? ''))));
            $layout = self::sanitize(array(
                'order' => $order,
                'sizes' => (array) wp_unslash($_POST['lp_layout_size'] ?? array()),
                'columns' => (int) ($_POST['lp_layout_columns'] ?? 3),
                'width' => (string) wp_unslash($_POST['lp_layout_width'] ?? 'standard'),
            ));
            update_option(self::OPTION, $layout, false);
            $done = 'saved';
        }
        if (class_exists('LogiPilot_Log')) {
            LogiPilot_Log::add('layout_' . $done, 'portal', 0, array());
        }

        wp_safe_redirect(add_query_arg('lp_layout', $done, self::edit_url()));
        exit;
    }

    public static function render_page(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Accès refusé.', 403);
        }
        $tools = LogiPilot_Portal::tools();
        $saved = self::get();
        $layout = $saved ?? self::defaults($tools);
        $tools = self::arrange($tools, $layout);
        $portal = LogiPilot_Portal::find_page_url();

        wp_enqueue_script('logipilot-layout', LOGIPILOT_URL . 'assets/layout.js', array(), LOGIPILOT_VERSION, true);

        echo '<div class="wrap logipilot-wrap lpl-wrap"><h1>LogiOne · Disposition du portail</h1>';
        $notice = sanitize_key((string) ($_GET['lp_layout'] ?? ''));
        if ($notice === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Disposition enregistrée : la page d’accueil LogiOne l’utilise dès maintenant.</p></div>';
        } elseif ($notice === 'reset') {
            echo '<div class="notice notice-success is-dismissible"><p>Présentation d’origine rétablie.</p></div>';
        }
        echo '<p class="lpl-intro">Choisissez l’ordre des tuiles de la page d’accueil LogiOne et la taille de chacune. Glissez une tuile pour la déplacer (ou utilisez les flèches). Chaque enseignant ne voit que les tuiles auxquelles il a droit, dans cet ordre.'
            . ($saved === null ? ' <strong>La page utilise encore la présentation d’origine</strong> : elle changera quand vous enregistrerez.' : '') . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lpl-form" data-lpl-form>';
        wp_nonce_field('logipilot_save_layout');
        echo '<input type="hidden" name="action" value="logipilot_save_layout">';
        echo '<input type="hidden" name="lp_layout_order" value="' . esc_attr(implode(',', array_keys($tools))) . '" data-lpl-order>';

        echo '<div class="lpl-options"><label>Colonnes <select name="lp_layout_columns" data-lpl-columns>';
        foreach (array(2, 3, 4) as $n) {
            echo '<option value="' . $n . '"' . selected($layout['columns'], $n, false) . '>' . $n . ' colonnes</option>';
        }
        echo '</select></label><label>Largeur de la mosaïque <select name="lp_layout_width" data-lpl-width>';
        foreach (self::WIDTHS as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($layout['width'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></div>';

        echo '<div class="lpl-columns"><div class="lpl-list-wrap"><h2>Tuiles</h2><ol class="lpl-list" data-lpl-list>';
        $n = count($tools);
        $i = 0;
        foreach ($tools as $key => $tool) {
            $i++;
            $size = self::size($layout, $key);
            $kind = (string) ($tool['kind'] ?? 'tool');
            $type = $kind === 'content' ? 'Tuile de contenu' : ($kind === 'manage' ? 'Gestion (administration)' : 'Outil');
            echo '<li class="lpl-item" draggable="true" data-key="' . esc_attr($key) . '" data-label="' . esc_attr($tool['label']) . '" data-kind="' . esc_attr($kind) . '" data-color="' . esc_attr(self::preview_color($key, $tool)) . '">'
                . '<span class="lpl-grip" aria-hidden="true">⋮⋮</span>'
                . '<span class="lpl-name"><strong>' . esc_html($tool['label']) . '</strong><small>' . esc_html($type) . '</small></span>'
                . '<label class="lpl-size"><span class="screen-reader-text">Taille de « ' . esc_html($tool['label']) . ' »</span><select name="lp_layout_size[' . esc_attr($key) . ']" data-lpl-size>';
            foreach (self::SIZES as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($size, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label>'
                . '<span class="lpl-move"><button type="button" class="button" data-lpl-up aria-label="Monter « ' . esc_attr($tool['label']) . ' »"' . ($i === 1 ? ' disabled' : '') . '>↑</button>'
                . '<button type="button" class="button" data-lpl-down aria-label="Descendre « ' . esc_attr($tool['label']) . ' »"' . ($i === $n ? ' disabled' : '') . '>↓</button></span></li>';
        }
        echo '</ol></div>';

        echo '<div class="lpl-preview-wrap"><h2>Aperçu</h2><p class="description">Sur un ordinateur. Sur tablette, la mosaïque passe à 2 colonnes, et sur téléphone à 1 colonne.</p>'
            . '<div class="lpl-preview lpl-cols-' . (int) $layout['columns'] . '" data-lpl-preview></div></div></div>';

        echo '<p class="lpl-actions"><button type="submit" class="button button-primary button-hero">Enregistrer la disposition</button> ';
        if ($portal !== '') {
            echo '<a class="button" href="' . esc_url($portal) . '" target="_blank" rel="noopener">Voir la page LogiOne</a> ';
        }
        if ($saved !== null) {
            echo '<button type="submit" name="lp_layout_reset" value="1" class="button-link lpl-reset">Rétablir la présentation d’origine</button>';
        }
        echo '</p></form></div>';
    }

    /** Couleur de fond de la tuile, pour l'aperçu. */
    private static function preview_color(string $key, array $tool): string
    {
        $builtin = array('classe' => '#313a45', 'progress' => '#067030', 'notes' => '#ffffff', 'exam' => '#f5c400', 'eval' => '#0e7490', 'planning' => '#ffffff', 'live' => '#dcefe3', 'projet' => '#fde9d9', 'logi3d' => '#e0e7ff');
        if (isset($builtin[$key])) {
            return $builtin[$key];
        }
        if (($tool['kind'] ?? '') === 'manage') {
            return '#eef0eb';
        }
        if (preg_match('/--tile-bg:\s*(#[0-9a-f]{6})/i', (string) ($tool['style'] ?? ''), $m)) {
            return $m[1];
        }

        return '#dcefe3';
    }
}
