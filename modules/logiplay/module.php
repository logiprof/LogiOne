<?php
/**
 * Module LogiPlay de LogiOne (code issu de l'extension LogiPlay 2.29.0, « jeux-revisions-logistique »).
 * Jeux de révision gamifiés pour logisticien·ne·s CFC : 70 jeux (M101 à M809, mathématiques), en français, allemand et italien.
 *
 * Données reprises telles quelles, rien n'est supprimé ni recréé : tables wp_jdr_progression, wp_jdr_historique,
 * wp_jdr_sessions (progressions, historique, sessions des élèves), options jrl_* et jdr_* (réglages, regroupements,
 * jeux masqués, statistiques). Mêmes codes courts ([jeux_logistique], [jeu_m101]…), même menu « LogiPlay ».
 * Si l'extension autonome LogiPlay est encore active, ce module n'est pas chargé (avis dans l'administration).
 * Les mises à jour passent par LogiOne (le système de mise à jour GitHub de LogiPlay n'est pas repris).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'JRL_VERSION', '2.30.0' );
define( 'JRL_FILE', __FILE__ );
define( 'JRL_DIR', __DIR__ );
define( 'JRL_REPO', 'logiprof/jeux-revisions-logistique' );
define( 'JRL_ASSET', 'jeux-revisions-logistique.zip' );

/** Repère de chargement pour LogiOne. */
final class LogiOne_LogiPlay {
	/** Portail LogiOne : les enseignants (droit de modifier des contenus, ou de voir les statistiques des jeux). */
	public static function can_view(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'jrl_voir_stats' ) || current_user_can( 'manage_options' );
	}

	/** [logiplay_portail] : LogiPlay dans le portail LogiOne (raccourcis enseignant + catalogue des jeux). */
	public static function portail(): string {
		if ( ! self::can_view() ) { return ''; }
		$liens = array();
		if ( current_user_can( 'jrl_voir_stats' ) || current_user_can( 'manage_options' ) ) {
			$liens[] = array( 'Statistiques des jeux', admin_url( 'admin.php?page=jeux-logistique-stats' ), 'Élèves actifs, temps de jeu, scores par classe' );
		}
		if ( function_exists( 'jdr_cap' ) && current_user_can( jdr_cap() ) ) {
			$liens[] = array( 'Suivi des élèves', admin_url( 'tools.php?page=jeux-revision' ), 'Progression de chaque élève, jeu par jeu' );
		}
		if ( current_user_can( 'edit_posts' ) ) {
			$liens[] = array( 'Codes courts et réglages', admin_url( 'admin.php?page=jeux-logistique' ), 'Un jeu ou un module dans vos pages et leçons' );
			$liens[] = array( 'Gestion des jeux', admin_url( 'admin.php?page=jeux-logistique-gestion' ), 'Jeux visibles ou masqués pour les élèves' );
		}
		$h = '<div class="lpl-lo-bar"><div class="lpl-lo-intro"><strong>' . count( jrl_jeux() ) . ' jeux de révision</strong> pour vos apprentis, avec entraînement par objectif, examen blanc noté sur 6 et révision des erreurs. Testez-les ci-dessous comme vos élèves les voient.</div>';
		if ( $liens ) {
			$h .= '<div class="lpl-lo-links">';
			foreach ( $liens as $l ) {
				$h .= '<a href="' . esc_url( $l[1] ) . '"><b>' . esc_html( $l[0] ) . '</b><span>' . esc_html( $l[2] ) . '</span></a>';
			}
			$h .= '</div>';
		}
		$h .= '</div><style>.lpl-lo-bar{display:flex;flex-wrap:wrap;gap:14px 22px;align-items:center;justify-content:space-between;background:#fff;border:1px solid #dfe3e0;border-radius:12px;padding:14px 18px;margin:0 0 18px}.lpl-lo-intro{flex:1 1 320px;font-size:15px;line-height:1.5}.lpl-lo-links{display:flex;flex-wrap:wrap;gap:8px}.lpl-lo-links a{display:flex;flex-direction:column;gap:1px;padding:8px 12px;border:1.5px solid #dfe3e0;border-radius:10px;text-decoration:none;color:#252c35;background:#f6f7f4;min-width:180px}.lpl-lo-links a:hover{border-color:#067030}.lpl-lo-links b{font-size:14px;font-weight:700}.lpl-lo-intro strong{font-weight:700}.lpl-lo-links span{font-size:12px;color:#5b6672}</style>';
		return $h . do_shortcode( '[jeux_logistique largeur="contenu"]' );
	}
}
add_action( 'init', function () { add_shortcode( 'logiplay_portail', array( 'LogiOne_LogiPlay', 'portail' ) ); }, 21 );

/* Moteur commun des jeux de révision (cartes, page du jeu, progression en base, suivi) */
require_once __DIR__ . '/includes/catalogue.php';
/* Visiteurs sans compte : ils jouent, la progression reste dans leur navigateur (ni base, ni bilan, ni classement). Réglage dans le menu LogiPlay. */
add_filter(
	'jdr_visiteurs',
	function ( $ok = false, $id = '' ) {
		if ( '0' === get_option( 'jrl_visiteurs', '1' ) ) {
			return false;
		}
		// LogiOne 1.56.1 : jeux réservés aux comptes (option « jrl_visiteurs_bloques » = liste des jeux fermés aux visiteurs). Par défaut, tous sont ouverts.
		return ! ( '' !== $id && in_array( $id, (array) get_option( 'jrl_visiteurs_bloques', array() ), true ) );
	},
	10,
	2
);
/* Menu Jeux logistique (shortcodes, affichage, regroupements, mises à jour) */
require_once __DIR__ . '/includes/admin.php';
/* Regroupements de modules (un shortcode par regroupement) */
require_once __DIR__ . '/includes/groupes.php';
/* Gestion des jeux : activer / masquer, cartes, statistiques, remise à zéro */
require_once __DIR__ . '/includes/gestion.php';
/* Classement par groupe Sensei (podium du catalogue, bouton Classement dans les jeux) */
require_once __DIR__ . '/includes/classement.php';
/* Statistiques : élèves actifs, temps de jeu, scores par classe, classes favorites par enseignant·e */
require_once __DIR__ . '/includes/statistiques.php';
require_once __DIR__ . '/includes/rapport.php';
/* Interface LogiPlay des shortcodes (bandeau, tuiles, cartes) */
require_once __DIR__ . '/includes/interface.php';

/**
 * Jeux livrés par l'extension : un dossier par jeu dans jeux/<identifiant>/ avec index.html, data.js, jeu.json et logo.svg.
 * Pour ajouter un jeu, il suffit d'ajouter son dossier : il apparaît automatiquement dans le catalogue.
 */
function jrl_jeux() {
	static $jeux = null;
	if ( null !== $jeux ) { return $jeux; }
	$jeux = array();
	foreach ( (array) glob( JRL_DIR . '/jeux/*/jeu.json' ) as $f ) {
		$dir = dirname( $f );
		if ( ! is_readable( $dir . '/index.html' ) ) { continue; }
		$d = json_decode( (string) file_get_contents( $f ), true );
		if ( ! is_array( $d ) || empty( $d['titre'] ) ) { continue; }
		$slug = sanitize_key( basename( $dir ) );
		$d['slug'] = $slug;
		if ( empty( $d['module'] ) ) { $d['module'] = strtoupper( strtok( $slug, '-' ) ); }
		if ( empty( $d['shortcode'] ) ) { $d['shortcode'] = 'jeu_' . strtolower( $d['module'] ); }
		$jeux[ $slug ] = $d;
	}
	uksort( $jeux, 'strnatcasecmp' );
	return $jeux;
}

/* Inscription des jeux dans le moteur commun (priorité 20 : remplace un ancien jeu installé séparément sous le même identifiant) */
add_filter( 'jdr_jeux', function ( $jeux ) {
	foreach ( jrl_jeux() as $slug => $d ) {
		$base = 'jeux/' . $slug . '/';
		$pdf  = function ( $nom ) use ( $slug, $base ) { return file_exists( JRL_DIR . '/' . $base . $nom ) ? plugins_url( $base . $nom, JRL_FILE ) : ''; };
		$jeux[ $slug ] = array(
			'titre'       => (string) $d['titre'],
			'sous_titre'  => (string) ( $d['sous_titre'] ?? '' ),
			'duree'       => (string) ( $d['duree'] ?? '' ),
			'description' => (string) ( $d['description'] ?? '' ),
			'contenu'     => (array) ( $d['contenu'] ?? array() ),
			'logo'        => plugins_url( $base . 'logo.svg', JRL_FILE ),
			'url'         => plugins_url( $base . 'index.html', JRL_FILE ),
			'couleur'     => (string) ( $d['couleur'] ?? '#067030' ),
			'bouton'      => '▶ Commencer',
			'version'     => (string) ( $d['version'] ?? JRL_VERSION ),
			/* dossier de formation : jeux/<identifiant>/dossier.pdf et corrige.pdf, affichés sous le bouton s'ils sont présents */
			'dossier'     => $pdf( 'dossier.pdf' ),
			'corrige'     => $pdf( 'corrige.pdf' ),
			/* versions allemande et italienne : textes de la carte (jeu.json, clés « de » et « it ») ; le jeu lui-même est index.de.html / index.it.html */
			'i18n'        => array_filter( array(
				'de' => ( ! empty( $d['de'] ) && is_array( $d['de'] ) && file_exists( JRL_DIR . '/' . $base . 'index.de.html' ) ) ? array_merge( array( 'bouton' => '▶ Starten', 'bouton_suite' => '▶ Fortsetzen', 'bouton_connexion' => 'Anmelden, um zu spielen' ), $d['de'] ) : array(),
				'it' => ( ! empty( $d['it'] ) && is_array( $d['it'] ) && file_exists( JRL_DIR . '/' . $base . 'index.it.html' ) ) ? array_merge( array( 'bouton' => '▶ Inizia', 'bouton_suite' => '▶ Continua', 'bouton_connexion' => 'Accedere per giocare' ), $d['it'] ) : array(),
			) ),
		);
	}
	return $jeux;
}, 20 );

/* ---------- Shortcodes ---------- */
add_action( 'init', function () {
	add_shortcode( 'jeux_logistique', 'jrl_shortcode_catalogue' );
	foreach ( jrl_jeux() as $slug => $d ) {
		$tag = sanitize_key( $d['shortcode'] );
		if ( '' === $tag || shortcode_exists( $tag ) ) { continue; }
		add_shortcode( $tag, function ( $atts = array() ) use ( $slug ) {
			$langue = is_array( $atts ) && ! empty( $atts['langue'] ) ? (string) $atts['langue'] : '';
			if ( function_exists( 'jrl_est_masque' ) && jrl_est_masque( $slug ) ) { return current_user_can( 'edit_posts' ) ? '<p><em>Ce jeu est masqué (menu LogiPlay › Gestion des jeux) : les élèves ne le voient pas.</em></p>' : ''; }
			if ( ! function_exists( 'jdr_shortcode_jeu' ) ) { return ''; }
			jrl_lp_enqueue();
			return '<div class="lpl-app lpl-solo ' . esc_attr( jrl_aff_classes() ) . '">' . jdr_shortcode_jeu( array( 'id' => $slug, 'langue' => $langue ) ) . '</div>';
		} );
	}
}, 20 );

/**
 * [jeux_logistique] : tout le catalogue de l'extension.
 * [jeux_logistique modules="M1"] : seulement les modules qui commencent par M1 (M101 à M110).
 * [jeux_logistique modules="M101,M203"] : seulement ces modules.
 * [jeux_logistique groupe="modules-1re-annee"] ou [jeux_modules_1re_annee] : un regroupement (menu LogiPlay).
 * [jeux_logistique podium="non"] : sans le podium « Classement de la semaine » (affiché aux élèves d'un groupe Sensei).
 * [jeux_logistique colonnes="3"] : 3 jeux côte à côte (1 à 4 ; sinon le réglage « Affichage du catalogue »).
 * [jeux_logistique entete="non"] : sans le bandeau LogiPlay ; [jeux_logistique largeur="contenu"] : dans la colonne du thème au lieu de toute la largeur.
 */
function jrl_shortcode_catalogue( $atts ) {
	if ( ! function_exists( 'jdr_card' ) ) { return ''; }
	$a = shortcode_atts( array( 'modules' => '', 'langue' => '', 'colonnes' => '', 'groupe' => '', 'podium' => '', 'entete' => '', 'largeur' => '' ), $atts, 'jeux_logistique' );
	$filtres = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) $a['modules'] ) ) ) );
	$groupe = null;
	if ( '' !== (string) $a['groupe'] ) {
		$groupe = function_exists( 'jrl_groupe' ) ? jrl_groupe( $a['groupe'] ) : null;
		if ( ! $groupe ) { return current_user_can( 'edit_posts' ) ? '<p><em>Regroupement introuvable : vérifiez le shortcode dans le menu LogiPlay.</em></p>' : ''; }
		if ( '' === (string) $a['colonnes'] && ! empty( $groupe['colonnes'] ) ) { $a['colonnes'] = $groupe['colonnes']; }
	}
	$tous = jdr_jeux();
	$cartes = array();
	foreach ( jrl_jeux() as $slug => $d ) {
		if ( ! isset( $tous[ $slug ] ) ) { continue; }
		if ( $groupe && ! in_array( $slug, (array) $groupe['jeux'], true ) ) { continue; }
		if ( $filtres ) {
			$garde = false;
			foreach ( $filtres as $f ) { if ( 0 === strpos( strtoupper( $d['module'] ), $f ) ) { $garde = true; break; } }
			if ( ! $garde ) { continue; }
		}
		$cartes[] = array( $slug, $tous[ $slug ] );
	}
	if ( ! $cartes ) { return current_user_can( 'edit_posts' ) ? '<p><em>Aucun jeu ne correspond à ce shortcode.</em></p>' : ''; }
	jdr_enqueue(); jrl_lp_enqueue();
	$prev = function_exists( 'jdr_lang' ) ? jdr_lang() : 'fr';
	if ( '' !== $a['langue'] && function_exists( 'jdr_lang' ) ) { jdr_lang( $a['langue'] ); }
	$col = jrl_colonnes( $a['colonnes'] );
	$podium = ( 'non' !== strtolower( (string) $a['podium'] ) && function_exists( 'jrl_cl_podium_html' ) ) ? jrl_cl_podium_html() : '';
	$entete = 'non' !== strtolower( (string) $a['entete'] );
	$plein = 'contenu' !== strtolower( (string) $a['largeur'] );
	$slugs = array(); $infos = array(); foreach ( $cartes as $c ) { $slugs[] = $c[0]; $infos[ $c[0] ] = $c[1]; }
	$out = '<section class="lpl-app ' . ( $plein ? 'lpl-full' : 'lpl-contenu' ) . ' ' . esc_attr( jrl_aff_classes() ) . '">'
		. ( $entete ? jrl_lp_hero( count( $cartes ) ) : '' )
		. '<div class="lpl-body">' . jrl_lp_tuiles( $slugs, $infos ) . $podium
		. '<h3 class="lpl-h">' . esc_html( jrl_lp_t( 'Vos jeux' ) ) . ' <span>' . esc_html( 1 === count( $cartes ) ? jrl_lp_t( '1 jeu' ) : sprintf( jrl_lp_t( '%d jeux' ), count( $cartes ) ) ) . '</span></h3>'
		. '<div class="jdr-grid' . ( 'auto' !== $col ? ' jdr-cols-' . $col : '' ) . '">';
	foreach ( $cartes as $c ) { $out .= jdr_card( $c[0], $c[1] ); }
	if ( function_exists( 'jdr_lang' ) ) { jdr_lang( $prev ); }
	return $out . '</div></div></section>';
}

/* ---------- Affichage (menu LogiPlay > Affichage du catalogue) ---------- */
/** Nombre de jeux côte à côte : « auto » ou 1 à 4 (attribut colonnes="…" du shortcode, sinon le réglage). */
function jrl_colonnes( $attr = '' ) {
	$v = '' !== (string) $attr ? (string) $attr : (string) get_option( 'jrl_colonnes', 'auto' );
	return in_array( $v, array( '1', '2', '3', '4' ), true ) ? $v : 'auto';
}
/** Classes de l'enveloppe : éléments de la carte masqués selon les réglages. */
function jrl_aff_classes() {
	$c = 'jrl-cat';
	if ( '0' === get_option( 'jrl_aff_duree', '1' ) ) { $c .= ' jrl-no-duree'; }
	if ( '0' === get_option( 'jrl_aff_progression', '1' ) ) { $c .= ' jrl-no-prog'; }
	if ( '0' === get_option( 'jrl_aff_objectifs', '1' ) ) { $c .= ' jrl-no-obj'; }
	return $c;
}
