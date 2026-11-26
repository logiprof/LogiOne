<?php
/**
 * Module LogiInfo de LogiOne (code issu de l'extension LogiInfo 2.8.1).
 * Formations Word, Excel, PowerPoint et « Réussir ses présentations » dans Sensei : remises, contrôle automatique,
 * progression, correction et retours.
 *
 * Base de données indépendante, reprise telle quelle : tables {préfixe}logiinfo_remises, logiinfo_progression,
 * logiinfo_eleves, logiinfo_journal, options fwr_settings / logiinfo_* / lpfi_*, fichiers des élèves dans
 * wp-content/uploads/logiinfo/. Les tables sont seulement complétées (dbDelta), jamais vidées ; rien n'est effacé.
 * Même menu « LogiInfo » dans l'administration, mêmes codes courts. Si l'extension autonome LogiInfo est encore
 * active, ce module n'est pas chargé.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'LI_VERSION', '2.8.1' );
define( 'LI_DIR', __DIR__ );
define( 'LI_FILE', __FILE__ );
define( 'LIR_VERSION', LI_VERSION );
/** Mot de passe qui verrouille le suivi des modifications des fichiers de départ Word (données brutes). */
define( 'LIR_MDP_EXERCICES', 'Logipro-info' );
define( 'LIR_FILE', __FILE__ );
define( 'LIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIR_URL', plugin_dir_url( __FILE__ ) );
define( 'LIR_CAP', 'fwr_corriger' );
define( 'LI_ADMIN_CAP', 'logiinfo_admin' );

require_once LIR_DIR . 'includes/class-fwr-docx.php';
require_once LIR_DIR . 'includes/class-fwr-xlsx.php';
require_once LIR_DIR . 'includes/class-li-xlsx-model.php';
require_once LIR_DIR . 'includes/class-li-xlsx-check.php';
require_once LIR_DIR . 'includes/class-li-pptx.php';
require_once LIR_DIR . 'includes/class-fwr-pptx.php';
require_once LIR_DIR . 'includes/class-fwr-rules.php';
require_once LIR_DIR . 'includes/class-fwr-core.php';
require_once LIR_DIR . 'includes/class-fwr-front.php';
require_once LIR_DIR . 'includes/class-li-formations.php';
require_once LIR_DIR . 'includes/class-li-formateurs.php';
require_once LIR_DIR . 'includes/class-li-classes.php';
require_once LIR_DIR . 'includes/class-li-exercices.php';
if ( is_admin() ) {
	require_once LIR_DIR . 'includes/class-fwr-admin.php';
	require_once LIR_DIR . 'includes/class-fwr-export.php';
	require_once LIR_DIR . 'includes/class-li-donnees.php';
}

/** Anciennes extensions remplacées par LogiInfo (désactivées, jamais effacées). */
const LI_OLD_PLUGINS = array( 'logiprof-formations-import/logiprof-formations-import.php', 'fw-remises/fw-remises.php' );

/*
 * Aucune donnée n'est jamais effacée : pas de désinstallation, rien à la désactivation.
 * maybe_upgrade() crée les tables manquantes, reprend les anciennes données et règle les droits ; il ne s'exécute
 * qu'au changement de version (même version que l'extension 2.8.1 : rien n'est refait sur un site déjà équipé).
 */
add_action( 'plugins_loaded', function () {
	LIR_Core::maybe_upgrade();
	LIR_Front::init();
	if ( is_admin() ) {
		LIR_Admin::init();
		LIR_Export::init();
	}
} );

add_action( 'admin_init', function () {
	if ( ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
	foreach ( LI_OLD_PLUGINS as $p ) { if ( is_plugin_active( $p ) ) { deactivate_plugins( $p, true ); } }
} );

/** LogiOne : repère de chargement et accès au portail. */
final class LogiOne_LogiInfo {

	/** Formateurs qui corrigent dans LogiInfo, administrateurs LogiInfo, ou enseignants qui suivent la progression Sensei. */
	public static function can_view(): bool {
		return current_user_can( LIR_CAP ) || current_user_can( LI_ADMIN_CAP ) || current_user_can( 'manage_sensei_grades' );
	}

	/** Remises en attente de correction (même calcul que la pastille du menu). */
	private static function pending(): int {
		global $wpdb;
		if ( ! current_user_can( LIR_CAP ) ) { return 0; }
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . LIR_Core::table() . " r WHERE r.statut = 'depose'" . LI_Formateurs::scope_sql( 'r' ) ); // phpcs:ignore
	}

	/** [logiinfo_portail] : tableau de bord LogiInfo dans le portail LogiOne. */
	public static function portal(): string {
		if ( ! self::can_view() ) { return ''; }
		$n     = self::pending();
		$cards = array();
		if ( current_user_can( LIR_CAP ) ) {
			$cards[] = array( 'Remises des exercices', $n ? $n . ' remise' . ( $n > 1 ? 's' : '' ) . ' à corriger' : 'Aucune remise en attente', admin_url( 'admin.php?page=fw-remises' ), true );
			$cards[] = array( 'Correction par l’IA', 'Export des copies et import de la correction', admin_url( 'admin.php?page=fw-remises-ia' ), false );
		}
		$cards[] = array( 'Progression des élèves', 'Étapes terminées, leçon par leçon', admin_url( 'admin.php?page=lpfi-progress' ), false );
		if ( current_user_can( LI_ADMIN_CAP ) ) {
			$cards[] = array( 'Cours et fichiers', 'Cours Sensei Word, Excel, PowerPoint, Présentations', admin_url( 'admin.php?page=lpfi' ), false );
			$cards[] = array( 'Formateurs et classes', 'Qui reçoit les exercices de quelle classe', admin_url( 'admin.php?page=logiinfo-formateurs' ), false );
			$cards[] = array( 'Données et sauvegarde', 'État des tables, sauvegarde ZIP', admin_url( 'admin.php?page=logiinfo-donnees' ), false );
		}
		$out = '<div class="logiinfo-portail"><p style="margin:0 0 16px;color:#5b6672">Les formations informatiques (Word, Excel, PowerPoint, Présentations) : exercices rendus, contrôle automatique et progression des élèves.</p>'
			. '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px">';
		foreach ( $cards as $c ) {
			$out .= '<a href="' . esc_url( $c[2] ) . '" style="display:flex;flex-direction:column;gap:6px;border:1px solid ' . ( $c[3] && $n ? '#f5c400' : '#d9ddd6' ) . ';border-radius:12px;padding:16px;background:' . ( $c[3] && $n ? '#fff8d6' : '#fff' ) . ';text-decoration:none;color:#313a45">'
				. '<strong style="font-size:15px">' . esc_html( $c[0] ) . '</strong><span style="font-size:13px;color:#5b6672">' . esc_html( $c[1] ) . '</span>'
				. '<span style="margin-top:auto;font-weight:600;color:#067030">Ouvrir →</span></a>';
		}
		$out .= '</div>';
		$links = array();
		foreach ( array( 'word' => 'Word', 'excel' => 'Excel', 'powerpoint' => 'PowerPoint', 'presentation' => 'Réussir ses présentations' ) as $k => $label ) {
			$id = LI_Formations::sensei_ready() ? (int) LI_Formations::course_id( $k ) : 0;
			if ( $id ) { $links[] = '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $label ) . '</a>'; }
		}
		if ( $links ) {
			$out .= '<p style="margin:18px 0 0;color:#5b6672">Voir les cours comme les élèves : ' . implode( ' · ', $links ) . '</p>';
		}
		return $out . '</div>';
	}
}
add_action( 'init', function () { add_shortcode( 'logiinfo_portail', array( 'LogiOne_LogiInfo', 'portal' ) ); } );
