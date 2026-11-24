<?php
/**
 * Plugin Name: LogiOne
 * Description: La suite pédagogique de LogiProf : LogiNotes, LogiProgress, LogiExam et LogiClass réunis, avec permissions par rôle, relevés de progression figés et publication des résultats LogiExam vers LogiNotes.
 * Version: 1.53.7
 * Update URI: https://github.com/logiprof/LogiOne
 * Author: LogiProf
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: logipilot
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LOGIPILOT_VERSION', '1.53.7');
define('LOGIPILOT_FILE', __FILE__);
define('LOGIPILOT_DIR', plugin_dir_path(__FILE__));
define('LOGIPILOT_URL', plugin_dir_url(__FILE__));

require_once LOGIPILOT_DIR . 'core/class-lp-plugin.php';
require_once LOGIPILOT_DIR . 'core/class-lp-permissions.php';
require_once LOGIPILOT_DIR . 'core/class-lp-log.php';
require_once LOGIPILOT_DIR . 'core/class-lp-spaces.php';
require_once LOGIPILOT_DIR . 'core/class-lp-updater.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-install.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-progress-service.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-snapshots.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-links.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-publisher.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-admin.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-portal.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-layout.php';
require_once LOGIPILOT_DIR . 'integration/class-lp-diagnostic.php';

register_activation_hook(__FILE__, array('LogiPilot_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('LogiPilot_Plugin', 'deactivate'));

LogiPilot_Plugin::boot();
LogiPilot_Updater::boot(); // 1.38.0 : mises à jour depuis GitHub (logiprof/LogiOne)
