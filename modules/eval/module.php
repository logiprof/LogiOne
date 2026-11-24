<?php
/**
 * Module LogiEval de LogiOne (code issu de LogiEval 2.2.0).
 * Évaluation collaborative de la posture professionnelle des élèves par les enseignants d'une classe.
 *
 * - Mêmes tables et réglages que LogiEval (wp_logieval_*, options logieval_*) : rien n'est recréé ni effacé,
 *   les classes, enseignants, élèves, évaluations et réponses existants sont repris tels quels.
 * - La page avec [logieval_login] continue de fonctionner comme avant (identifiant reçu par e-mail).
 * - Dans LogiOne, l'enseignant est reconnu par son compte (même adresse e-mail, ou après une seule
 *   connexion avec son identifiant) : il n'a plus besoin de le saisir.
 * - Administration : permission LogiOne « lp_eval_admin » ; accès depuis LogiOne : « lp_eval_use ».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOGIEVAL_VERSION', '2.3.0' ); // inchangée volontairement : pas de réinstallation des tables
define( 'LOGIEVAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOGIEVAL_URL', plugin_dir_url( __FILE__ ) );

require_once LOGIEVAL_PATH . 'includes/class-database.php';
require_once LOGIEVAL_PATH . 'includes/class-auth.php';
require_once LOGIEVAL_PATH . 'includes/class-mailer.php';
require_once LOGIEVAL_PATH . 'includes/class-calculator.php';
require_once LOGIEVAL_PATH . 'includes/class-exporter.php';
require_once LOGIEVAL_PATH . 'includes/class-importer.php';
require_once LOGIEVAL_PATH . 'includes/class-rollover.php';
LOGIEVAL_Rollover::boot();
require_once LOGIEVAL_PATH . 'admin/class-admin.php';
require_once LOGIEVAL_PATH . 'public/class-public.php';

/* Démarrage : mise à niveau des tables (ajouts seulement), écrans, shortcode, rappels quotidiens. */
function logieval_module_boot() {
	LOGIEVAL_Database::maybe_upgrade();
	new LOGIEVAL_Admin();
	new LOGIEVAL_Public();
}
if ( did_action( 'plugins_loaded' ) ) {
	logieval_module_boot();
} else {
	add_action( 'plugins_loaded', 'logieval_module_boot' );
}

add_action( 'logieval_daily_cron', array( 'LOGIEVAL_Mailer', 'send_reminders' ) );
add_action( 'logieval_deferred_mail', array( 'LOGIEVAL_Mailer', 'run_deferred' ), 10, 2 ); // 1.53.0
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'logieval_daily_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'logieval_daily_cron' );
		}
	}
);
