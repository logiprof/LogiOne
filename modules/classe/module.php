<?php
/**
 * Module LogiClass de LogiOne (code issu de Gestion des classes d'apprentis 0.2.70).
 * La version reste 0.2.70 : aucune migration ni recalcul des situations n'est relancé par la bascule.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GCA_VERSION', '0.2.70');
define('GCA_FILE', __FILE__);
define('GCA_DIR', plugin_dir_path(__FILE__));
define('GCA_URL', plugin_dir_url(__FILE__));

require_once GCA_DIR . 'includes/class-gca-database.php';
require_once GCA_DIR . 'includes/class-gca-pdf.php';
require_once GCA_DIR . 'includes/class-gca-importer.php';
require_once GCA_DIR . 'includes/class-gca-app.php';

add_action('plugins_loaded', static function () {
    GCA_Database::maybe_upgrade();
    GCA_App::instance();
}, 20);
