<?php
/**
 * Module LogiProjet de LogiOne (code issu de LogiProjet 1.10.2).
 * Travaux notés en duo (présentations PowerPoint) : dépôt par les apprentis, correction assistée par IA,
 * validation par l'enseignant et retour aux apprentis.
 *
 * - Mêmes tables (wp_logiprojet_travaux, wp_logiprojet_depots), mêmes réglages (logiprojet_settings) et même
 *   dossier de dépôts (uploads/logiprojet-…) que l'extension autonome : les travaux existants sont repris tels quels.
 * - Même menu « LogiProjet » dans le menu latéral de WordPress, même code court [logiprojet], même API REST.
 * - Si l'extension autonome LogiProjet est encore active, ce module n'est pas chargé (pas de doublon).
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'LOGIPROJET_VERSION' ) ) {
	define( 'LOGIPROJET_VERSION', '1.10.3' );
	define( 'LOGIPROJET_DB_VERSION', '5' );
	define( 'LOGIPROJET_FILE', __FILE__ );
	define( 'LOGIPROJET_DIR', plugin_dir_path( __FILE__ ) );
	define( 'LOGIPROJET_URL', plugin_dir_url( __FILE__ ) );
}

require_once LOGIPROJET_DIR . 'includes/class-install.php';
require_once LOGIPROJET_DIR . 'includes/class-access.php';
require_once LOGIPROJET_DIR . 'includes/class-sensei.php';
require_once LOGIPROJET_DIR . 'includes/class-store.php';
require_once LOGIPROJET_DIR . 'includes/class-pptx.php';
require_once LOGIPROJET_DIR . 'includes/class-formats.php';
require_once LOGIPROJET_DIR . 'includes/class-ai.php';
require_once LOGIPROJET_DIR . 'includes/class-grading.php';
require_once LOGIPROJET_DIR . 'includes/class-consigne.php';
require_once LOGIPROJET_DIR . 'includes/class-transfer.php';
require_once LOGIPROJET_DIR . 'includes/class-rest.php';
require_once LOGIPROJET_DIR . 'includes/class-settings.php';
require_once LOGIPROJET_DIR . 'includes/class-app.php';
require_once LOGIPROJET_DIR . 'includes/class-print.php';
require_once LOGIPROJET_DIR . 'includes/class-skill.php';
require_once LOGIPROJET_DIR . 'includes/class-claude.php';

// Pas de crochet d'activation dans un module : à la première visite, les tables manquantes sont créées
// (version de base enregistrée différente) ; si elles existent déjà, rien n'est modifié.
add_action( 'plugins_loaded', array( 'LogiProjet_Install', 'maybe_upgrade' ) );
add_action( 'rest_api_init', array( 'LogiProjet_Rest', 'register_routes' ) );
add_action( 'init', array( 'LogiProjet_App', 'init' ) );
add_action( 'admin_menu', array( 'LogiProjet_Settings', 'menu' ) );
add_action( 'admin_init', array( 'LogiProjet_Settings', 'register' ) );
add_action( 'admin_post_logiprojet_imprimer', array( 'LogiProjet_Print', 'render' ) );
add_action( 'admin_post_logiprojet_competence', array( 'LogiProjet_Skill', 'download' ) );
add_action( 'admin_post_logiprojet_export_correction', array( 'LogiProjet_Claude', 'export' ) );
