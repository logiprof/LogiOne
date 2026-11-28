<?php
/**
 * Module LogiExam de LogiOne (code issu de LogiExam 3.5.0).
 * La version reste 3.5.0 : aucune réinstallation n'est déclenchée par la bascule.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOGIEVAL_SECURE_VERSION', '3.6.0' );
// Version des fichiers CSS / JS : suit LogiOne, pour que les navigateurs prennent les nouveautés.
define( 'LOGIEVAL_SECURE_ASSETS_VERSION', LOGIEVAL_SECURE_VERSION . '-' . LOGIPILOT_VERSION );
define( 'LOGIEVAL_SECURE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOGIEVAL_SECURE_URL', plugin_dir_url( __FILE__ ) );
define( 'LOGIEVAL_SECURE_BASENAME', plugin_basename( LOGIPILOT_FILE ) );

require_once LOGIEVAL_SECURE_PATH . 'includes/class-database.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-ultimate-member.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-sensei.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-grading.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-question-types.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-ai.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-security.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-evaluations.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-export.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-shortcodes.php';
require_once LOGIEVAL_SECURE_PATH . 'includes/class-seb.php';

add_action(
	'plugins_loaded',
	static function() {
		load_plugin_textdomain( 'logieval-secure', false, dirname( LOGIEVAL_SECURE_BASENAME ) . '/languages' );
		LogiEval_Secure_Database::maybe_upgrade();
		LogiEval_Secure_Ultimate_Member::init();
		LogiEval_Secure_Sensei::init();
		LogiEval_Secure_Evaluations::init();
		LogiEval_Secure_Security::init();
		LogiEval_Secure_Export::init();
		LogiEval_Secure_Shortcodes::init();
		LogiEval_Secure_SEB::init();
	},
	20
);
