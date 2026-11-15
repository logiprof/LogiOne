<?php
/**
 * LogiOne 1.38.0 : mises à jour depuis GitHub (dépôt logiprof/LogiOne).
 *
 * WordPress affiche « Une nouvelle version de LogiOne est disponible » comme pour une extension du répertoire officiel :
 * la dernière « release » publiée sur GitHub est comparée à la version installée. La mise à jour se fait avec le bouton
 * habituel « Mettre à jour » (Extensions) et ne remplace QUE les fichiers de l'extension : les tables et réglages de
 * LogiNotes, LogiExam, LogiProgress, LogiClass ne sont pas touchés.
 *
 * Le dépôt est privé : chaque site a besoin d'une clé GitHub en lecture seule (écran LogiOne › Mises à jour, ou
 * constante LOGIONE_GITHUB_TOKEN dans wp-config.php). Si le dépôt devient public, aucune clé n'est nécessaire.
 *
 * Chaque release doit contenir le fichier logione-X.Y.Z.zip (dossier « logipilot » à l'intérieur), produit
 * automatiquement par .github/workflows/release.yml quand on pousse une étiquette vX.Y.Z.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class LogiPilot_Updater
{
    public const REPO = 'logiprof/LogiOne';
    public const SLUG = 'logipilot';
    private const CACHE = 'logione_update_release';
    private const TOKEN_OPTION = 'logione_github_token';
    private const ERROR_OPTION = 'logione_update_error';
    private const PAGE = 'logione-updates';

    public static function boot(): void
    {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        add_filter('upgrader_pre_download', array(__CLASS__, 'download_package'), 10, 4);
        add_filter('upgrader_source_selection', array(__CLASS__, 'fix_folder_name'), 10, 4);
        add_action('upgrader_process_complete', array(__CLASS__, 'after_upgrade'), 10, 2);
        add_filter('plugin_row_meta', array(__CLASS__, 'row_meta'), 10, 2);
        add_action('admin_menu', array(__CLASS__, 'menu'), 40);
        add_action('admin_post_logione_updates_save', array(__CLASS__, 'handle_save'));
        add_action('admin_post_logione_updates_check', array(__CLASS__, 'handle_check'));
    }

    public static function basename(): string
    {
        return plugin_basename(LOGIPILOT_FILE);
    }

    private static function token(): string
    {
        if (defined('LOGIONE_GITHUB_TOKEN') && LOGIONE_GITHUB_TOKEN) {
            return (string) LOGIONE_GITHUB_TOKEN;
        }

        return (string) get_option(self::TOKEN_OPTION, '');
    }

    private static function headers(string $accept = 'application/vnd.github+json'): array
    {
        $headers = array(
            'Accept' => $accept,
            'User-Agent' => 'LogiOne-Updater/' . LOGIPILOT_VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        );
        $token = self::token();
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Dernière release publiée : array(version, tag, notes, published, asset_api, asset_name, html_url) ou null.
     * Mise en cache 6 h (1 h en cas d'erreur, pour ne pas interroger GitHub en boucle).
     */
    public static function latest_release(bool $force = false): ?array
    {
        if (!$force) {
            $cached = get_site_transient(self::CACHE);
            if (is_array($cached)) {
                return $cached['release'] ?? null;
            }
        }
        $release = null;
        $error = '';
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', array('timeout' => 15, 'headers' => self::headers()));
        if (is_wp_error($response)) {
            $error = 'GitHub injoignable : ' . $response->get_error_message();
        } else {
            $code = (int) wp_remote_retrieve_response_code($response);
            $data = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($code === 404) {
                $error = self::token() === ''
                    ? 'Dépôt privé : ajoutez une clé GitHub en lecture seule pour recevoir les mises à jour.'
                    : 'Aucune version publiée sur GitHub, ou la clé GitHub n’a pas accès au dépôt ' . self::REPO . '.';
            } elseif ($code === 401 || $code === 403) {
                $error = 'Clé GitHub refusée (expirée, révoquée ou sans accès en lecture au dépôt) — code ' . $code . '.';
            } elseif ($code !== 200 || !is_array($data)) {
                $error = 'Réponse inattendue de GitHub (code ' . $code . ').';
            } else {
                $asset = null;
                foreach ((array) ($data['assets'] ?? array()) as $a) {
                    if (is_array($a) && preg_match('/\.zip$/i', (string) ($a['name'] ?? '')) && (!$asset || preg_match('/^logione/i', (string) $a['name']))) {
                        $asset = $a;
                    }
                }
                $version = ltrim((string) ($data['tag_name'] ?? ''), 'vV');
                if ($version === '' || !$asset) {
                    $error = 'La dernière version publiée sur GitHub n’a pas de fichier .zip à installer.';
                } else {
                    $release = array(
                        'version' => $version,
                        'tag' => (string) $data['tag_name'],
                        'notes' => (string) ($data['body'] ?? ''),
                        'published' => (string) ($data['published_at'] ?? ''),
                        'asset_api' => (string) ($asset['url'] ?? ''),
                        'asset_name' => (string) ($asset['name'] ?? ''),
                        'html_url' => (string) ($data['html_url'] ?? ''),
                    );
                }
            }
        }
        update_site_option(self::ERROR_OPTION, $error === '' ? '' : array('message' => $error, 'time' => time()));
        set_site_transient(self::CACHE, array('release' => $release), $error === '' ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);

        return $release;
    }

    /** Ajoute LogiOne à la liste des mises à jour de WordPress quand GitHub a une version plus récente. */
    public static function inject_update($transient)
    {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }
        $release = self::latest_release();
        if (!$release) {
            // Pas encore de version lisible sur GitHub : LogiOne se déclare quand même « à jour »,
            // pour que WordPress affiche le lien « Activer les mises à jour auto ».
            $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();
            $transient->no_update[self::basename()] = (object) array(
                'id' => 'github.com/' . self::REPO,
                'slug' => self::SLUG,
                'plugin' => self::basename(),
                'new_version' => LOGIPILOT_VERSION,
                'url' => 'https://github.com/' . self::REPO,
                'package' => '',
                'icons' => array(),
                'banners' => array(),
            );
            return $transient;
        }
        $item = (object) array(
            'id' => 'github.com/' . self::REPO,
            'slug' => self::SLUG,
            'plugin' => self::basename(),
            'new_version' => $release['version'],
            'url' => 'https://github.com/' . self::REPO,
            'package' => $release['asset_api'],
            'requires' => '6.0',
            'requires_php' => '8.0',
            'tested' => get_bloginfo('version'),
            'icons' => array(),
            'banners' => array(),
        );
        if (version_compare($release['version'], LOGIPILOT_VERSION, '>')) {
            $transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
            $transient->response[self::basename()] = $item;
            if (isset($transient->no_update[self::basename()])) {
                unset($transient->no_update[self::basename()]);
            }
        } else {
            $item->new_version = LOGIPILOT_VERSION;
            $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();
            $transient->no_update[self::basename()] = $item; // permet les mises à jour automatiques
            if (isset($transient->response[self::basename()])) {
                unset($transient->response[self::basename()]);
            }
        }

        return $transient;
    }

    /** Fenêtre « Afficher les détails de la version » : notes de version GitHub. */
    public static function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }
        $release = self::latest_release();
        $notes = $release ? self::markdown_to_html($release['notes']) : '';

        return (object) array(
            'name' => 'LogiOne',
            'slug' => self::SLUG,
            'version' => $release ? $release['version'] : LOGIPILOT_VERSION,
            'author' => 'LogiProf',
            'homepage' => 'https://github.com/' . self::REPO,
            'requires' => '6.0',
            'requires_php' => '8.0',
            'tested' => get_bloginfo('version'),
            'last_updated' => $release ? $release['published'] : '',
            'download_link' => $release ? $release['asset_api'] : '',
            'sections' => array(
                'description' => '<p>La suite pédagogique de LogiProf : LogiNotes, LogiProgress, LogiExam et LogiClass réunis.</p><p>La mise à jour remplace uniquement les fichiers de l’extension : vos données (classes, notes, évaluations, progression) ne sont pas modifiées.</p>',
                'changelog' => $notes !== '' ? $notes : '<p>Voir les notes de version sur GitHub.</p>',
            ),
        );
    }

    private static function markdown_to_html(string $md): string
    {
        $html = '';
        $in_list = false;
        foreach (preg_split('/\r?\n/', trim($md)) as $line) {
            $t = trim($line);
            if (preg_match('/^[-*]\s+(.*)$/', $t, $m)) {
                if (!$in_list) {
                    $html .= '<ul>';
                    $in_list = true;
                }
                $html .= '<li>' . self::inline_md($m[1]) . '</li>';
                continue;
            }
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            if ($t === '') {
                continue;
            }
            if (preg_match('/^#{1,6}\s+(.*)$/', $t, $m)) {
                $html .= '<h4>' . self::inline_md($m[1]) . '</h4>';
            } else {
                $html .= '<p>' . self::inline_md($t) . '</p>';
            }
        }

        return $html . ($in_list ? '</ul>' : '');
    }

    private static function inline_md(string $text): string
    {
        $text = esc_html($text);

        return preg_replace(array('/\*\*(.+?)\*\*/', '/`(.+?)`/'), array('<strong>$1</strong>', '<code>$1</code>'), $text);
    }

    /**
     * Téléchargement du .zip d'une release (dépôt privé) : GitHub demande la clé, puis redirige vers une adresse de
     * téléchargement signée qui, elle, ne doit PAS recevoir la clé. WordPress ne sait pas le faire seul.
     */
    public static function download_package($reply, $package, $upgrader = null, $hook_extra = array())
    {
        $prefix = 'https://api.github.com/repos/' . self::REPO . '/releases/assets/';
        if ($reply !== false || !is_string($package) || stripos($package, $prefix) !== 0) {
            return $reply;
        }
        if (is_object($upgrader) && isset($upgrader->skin) && method_exists($upgrader->skin, 'feedback')) {
            $upgrader->skin->feedback('Téléchargement de LogiOne depuis GitHub…');
        }
        $response = wp_remote_get($package, array('timeout' => 30, 'redirection' => 0, 'headers' => self::headers('application/octet-stream')));
        if (is_wp_error($response)) {
            return new WP_Error('logione_download', 'Téléchargement impossible depuis GitHub : ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $location = (string) wp_remote_retrieve_header($response, 'location');
        if (in_array($code, array(301, 302, 303, 307, 308), true) && $location !== '') {
            $file = download_url($location, 300);
            return is_wp_error($file) ? new WP_Error('logione_download', 'Téléchargement impossible : ' . $file->get_error_message()) : $file;
        }
        if ($code === 200 && wp_remote_retrieve_body($response) !== '') {
            $file = wp_tempnam('logione.zip');
            if (!$file || file_put_contents($file, wp_remote_retrieve_body($response)) === false) {
                return new WP_Error('logione_download', 'Impossible d’écrire le fichier téléchargé.');
            }
            return $file;
        }

        return new WP_Error('logione_download', 'GitHub a refusé le téléchargement (code ' . $code . '). Vérifiez la clé GitHub dans LogiOne › Mises à jour.');
    }

    /** Le dossier installé doit rester « logipilot » (sinon WordPress verrait une autre extension, désactivée). */
    public static function fix_folder_name($source, $remote_source, $upgrader = null, $hook_extra = array())
    {
        global $wp_filesystem;

        if (!is_array($hook_extra) || ($hook_extra['plugin'] ?? '') !== self::basename() || is_wp_error($source)) {
            return $source;
        }
        $wanted = trailingslashit($remote_source) . dirname(self::basename()) . '/';
        if (untrailingslashit($source) === untrailingslashit($wanted)) {
            return $source;
        }
        if ($wp_filesystem && $wp_filesystem->move(untrailingslashit($source), untrailingslashit($wanted), true)) {
            return $wanted;
        }

        return new WP_Error('logione_folder', 'Le dossier de la mise à jour n’a pas pu être renommé en « ' . dirname(self::basename()) . ' ».');
    }

    public static function after_upgrade($upgrader, $options): void
    {
        if (($options['type'] ?? '') === 'plugin' && in_array(self::basename(), (array) ($options['plugins'] ?? array()), true)) {
            delete_site_transient(self::CACHE);
        }
    }

    public static function row_meta(array $links, string $file): array
    {
        if ($file === self::basename() && current_user_can('update_plugins')) {
            $links[] = '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE)) . '">Mises à jour (GitHub)</a>';
        }

        return $links;
    }

    public static function menu(): void
    {
        add_submenu_page('logione', 'Mises à jour', 'Mises à jour', 'update_plugins', self::PAGE, array(__CLASS__, 'render_page'));
    }

    public static function handle_save(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logione_updates_save');
        if (!empty($_POST['remove_token'])) {
            delete_option(self::TOKEN_OPTION);
        } else {
            $token = trim(sanitize_text_field(wp_unslash($_POST['github_token'] ?? '')));
            if ($token !== '') {
                update_option(self::TOKEN_OPTION, $token, false);
            }
        }
        self::refresh();
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&saved=1'));
        exit;
    }

    public static function handle_check(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('logione_updates_check');
        self::refresh();
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&checked=1'));
        exit;
    }

    private static function refresh(): void
    {
        delete_site_transient(self::CACHE);
        self::latest_release(true);
        delete_site_transient('update_plugins');
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
    }

    public static function render_page(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die('Accès refusé.');
        }
        $release = self::latest_release();
        $error = get_site_option(self::ERROR_OPTION, '');
        $token = self::token();
        $from_constant = defined('LOGIONE_GITHUB_TOKEN') && LOGIONE_GITHUB_TOKEN;
        $newer = $release && version_compare($release['version'], LOGIPILOT_VERSION, '>');
        echo '<div class="wrap"><h1>LogiOne · Mises à jour</h1>';
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Réglage enregistré et vérification faite.</p></div>';
        } elseif (!empty($_GET['checked'])) {
            echo '<div class="notice notice-info is-dismissible"><p>Vérification faite.</p></div>';
        }
        echo '<table class="widefat striped" style="max-width:760px"><tbody>';
        echo '<tr><th style="width:220px">Version installée</th><td><strong>' . esc_html(LOGIPILOT_VERSION) . '</strong></td></tr>';
        echo '<tr><th>Dernière version sur GitHub</th><td>' . ($release ? '<strong>' . esc_html($release['version']) . '</strong>' . ($release['published'] ? ' <span style="color:#646970">(' . esc_html(wp_date('d.m.Y H:i', strtotime($release['published']))) . ')</span>' : '') : '—') . '</td></tr>';
        echo '<tr><th>État</th><td>';
        if (is_array($error) && !empty($error['message'])) {
            echo '<span style="color:#b32d2e">⚠ ' . esc_html($error['message']) . '</span>';
        } elseif ($newer) {
            echo '<span style="color:#996800">Une mise à jour est disponible.</span> <a class="button button-primary" href="' . esc_url(self_admin_url('plugins.php?plugin_status=upgrade')) . '">Aller aux extensions pour mettre à jour</a>';
        } elseif ($release) {
            echo '<span style="color:#008a20">✓ LogiOne est à jour.</span>';
        } else {
            echo '—';
        }
        echo '</td></tr>';
        echo '<tr><th>Dépôt</th><td><a href="https://github.com/' . esc_attr(self::REPO) . '/releases" target="_blank" rel="noopener">github.com/' . esc_html(self::REPO) . '</a></td></tr>';
        echo '</tbody></table>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0">';
        wp_nonce_field('logione_updates_check');
        echo '<input type="hidden" name="action" value="logione_updates_check"><button class="button">Vérifier maintenant</button> <span style="color:#646970">WordPress vérifie aussi tout seul, environ deux fois par jour.</span></form>';

        echo '<h2>Clé GitHub (dépôt privé)</h2>';
        echo '<p style="max-width:760px">Le dépôt est privé : ce site a besoin d’une clé GitHub <strong>en lecture seule</strong> pour voir et télécharger les nouvelles versions. Sur GitHub : <em>Settings › Developer settings › Personal access tokens › Fine-grained tokens › Generate new token</em> ; « Repository access » : <em>Only select repositories › ' . esc_html(self::REPO) . '</em> ; « Permissions » : <em>Contents : Read-only</em>. Collez la clé ci-dessous.</p>';
        if ($from_constant) {
            echo '<p>✓ Clé définie dans <code>wp-config.php</code> (constante <code>LOGIONE_GITHUB_TOKEN</code>).</p>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('logione_updates_save');
            echo '<input type="hidden" name="action" value="logione_updates_save">';
            echo '<p><input type="password" name="github_token" class="regular-text" autocomplete="off" placeholder="' . esc_attr($token !== '' ? 'Clé enregistrée (••••' . substr($token, -4) . ') — collez une nouvelle clé pour la remplacer' : 'github_pat_…') . '" style="width:520px"> ';
            echo '<button class="button button-primary">Enregistrer et vérifier</button>';
            if ($token !== '') {
                echo ' <button class="button" name="remove_token" value="1">Retirer la clé</button>';
            }
            echo '</p></form>';
        }
        echo '<p style="max-width:760px;color:#646970">La mise à jour remplace uniquement les fichiers de l’extension : les données de LogiNotes, LogiExam, LogiProgress et LogiClass ne sont pas modifiées. Conseil : faites une sauvegarde du site avant une mise à jour importante.</p>';
        if ($release && $release['notes'] !== '') {
            echo '<h2>Nouveautés de la version ' . esc_html($release['version']) . '</h2><div style="max-width:760px">' . wp_kses_post(self::markdown_to_html($release['notes'])) . '</div>';
        }
        echo '</div>';
    }
}
