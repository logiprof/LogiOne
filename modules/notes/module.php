<?php
/**
 * Module LogiNotes de LogiOne (code issu de LogiNotes 0.1.20, avec correctifs LogiOne).
 * La constante de version reste 0.1.20 : l'option gne_db_version n'est pas modifiée,
 * ce qui garantit qu'un retour à l'ancien plugin ne déclenche aucune routine de mise à jour.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GNE_VERSION', '0.1.20');
define('GNE_FILE', __FILE__);
define('GNE_DIR', plugin_dir_path(__FILE__));
define('GNE_URL', plugin_dir_url(__FILE__));

require_once GNE_DIR . 'includes/class-gne-database.php';
require_once GNE_DIR . 'includes/class-gne-pdf.php';
require_once GNE_DIR . 'includes/class-gne-app.php';

add_action('plugins_loaded', static function () {
    GNE_Database::maybe_upgrade();
    GNE_App::instance();
}, 20);
