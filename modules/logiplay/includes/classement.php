<?php
/*
 * Classement des élèves par groupe Sensei Pro (table {prefix}sensei_pro_groups_students).
 * Points = XP des jeux de révision de l'extension (jeux masqués exclus) :
 *   « Depuis le début » : XP actuels ; « Cette semaine » : XP gagnés depuis lundi 00:00 (historique quotidien).
 * Noms affichés : prénom + initiale du nom. Les comptes enseignants (capacité edit_posts) ne sont pas classés.
 * Affichage : podium de la semaine au-dessus du catalogue, bouton « Classement » dans chaque jeu (REST jdr/v1/classement/<jeu>).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function jrl_cl_on( $ou ) { return '0' !== get_option( 'podium' === $ou ? 'jrl_cl_podium' : 'jrl_cl_jeu', '1' ); }

function jrl_t( $fr ) {
	static $de = array(
		'Classement de la semaine' => 'Rangliste der Woche',
		'Votre rang cette semaine : %s · %s XP' => 'Ihr Rang diese Woche: %s · %s XP',
		'Vous n’avez pas encore de points cette semaine : à vous de jouer !' => 'Sie haben diese Woche noch keine Punkte: Jetzt spielen!',
		'Personne n’a encore de points cette semaine : soyez la première ou le premier !' => 'Diese Woche hat noch niemand Punkte: Seien Sie die oder der Erste!',
		'Voir tout le classement' => 'Ganze Rangliste ansehen',
		'(vous)' => '(Sie)',
		'tous les jeux' => 'alle Spiele',
	);
	static $it = array(
		'Classement de la semaine' => 'Classifica della settimana',
		'Votre rang cette semaine : %s · %s XP' => 'La sua posizione questa settimana: %s · %s XP',
		'Vous n’avez pas encore de points cette semaine : à vous de jouer !' => 'Questa settimana non ha ancora punti: tocca a lei!',
		'Personne n’a encore de points cette semaine : soyez la première ou le premier !' => 'Questa settimana nessuno ha ancora punti: sia la prima o il primo!',
		'Voir tout le classement' => 'Vedi tutta la classifica',
		'(vous)' => '(Lei)',
		'tous les jeux' => 'tutti i giochi',
	);
	$lg = function_exists( 'jdr_lang' ) ? jdr_lang() : 'fr';
	if ( 'it' === $lg ) { return $it[ $fr ] ?? $fr; }
	return ( 'de' === $lg && isset( $de[ $fr ] ) ) ? $de[ $fr ] : $fr;
}
function jrl_cl_rang( $n ) { $lg = function_exists( 'jdr_lang' ) ? jdr_lang() : 'fr'; return 'de' === $lg ? $n . '.' : ( 'it' === $lg ? $n . '°' : ( 1 === (int) $n ? '1er' : $n . 'e' ) ); }

function jrl_cl_table() {
	global $wpdb; static $ok = null;
	$t = $wpdb->prefix . 'sensei_pro_groups_students';
	if ( null === $ok ) { $ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t; }
	return $ok ? $t : '';
}
/** Groupes d'un élève : id => nom. Un enseignant (edit_posts) voit tous les groupes. Filtre « jrl_groupes_eleve » pour une autre source. */
function jrl_cl_groupes( $uid ) {
	global $wpdb; $g = array(); $t = jrl_cl_table();
	if ( $t ) {
		$ids = user_can( $uid, 'edit_posts' )
			? $wpdb->get_col( "SELECT DISTINCT group_id FROM $t" ) // phpcs:ignore
			: $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT group_id FROM $t WHERE student_id = %d", $uid ) ); // phpcs:ignore
		foreach ( array_map( 'absint', (array) $ids ) as $id ) { if ( $id && 'trash' !== get_post_status( $id ) ) { $n = get_the_title( $id ); $g[ $id ] = $n ? $n : 'Groupe ' . $id; } }
		natcasesort( $g );
	}
	return (array) apply_filters( 'jrl_groupes_eleve', $g, $uid );
}
function jrl_cl_membres( $gid ) {
	global $wpdb; $t = jrl_cl_table();
	$ids = $t ? array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT student_id FROM $t WHERE group_id = %d", $gid ) ) ) : array(); // phpcs:ignore
	return array_values( array_filter( (array) apply_filters( 'jrl_membres_groupe', $ids, $gid ) ) );
}
function jrl_cl_nom( $uid ) {
	$p = trim( (string) get_user_meta( $uid, 'first_name', true ) ); $n = trim( (string) get_user_meta( $uid, 'last_name', true ) );
	if ( '' === $p ) { $u = get_userdata( $uid ); return $u ? $u->display_name : '#' . $uid; }
	return $p . ( '' !== $n ? ' ' . mb_strtoupper( mb_substr( $n, 0, 1 ) ) . '.' : '' );
}
/** Jeux comptés : ceux de l'extension, sauf les masqués. */
function jrl_cl_jeux() { return array_values( array_diff( array_keys( jrl_jeux() ), function_exists( 'jrl_masques' ) ? jrl_masques() : array() ) ); }
function jrl_cl_lundi() { $ts = current_time( 'timestamp' ); return gmdate( 'Y-m-d', $ts - ( (int) gmdate( 'N', $ts ) - 1 ) * DAY_IN_SECONDS ); }

/** Points par élève : uid => XP (période « semaine » ou « total »). */
function jrl_cl_points( array $uids, $periode, array $jeux ) {
	global $wpdb;
	$pts = array_fill_keys( $uids, 0 );
	if ( ! $uids || ! $jeux || ! function_exists( 'jdr_table' ) ) { return $pts; }
	$u = implode( ',', array_map( 'intval', $uids ) );
	$j = implode( ',', array_map( function ( $s ) use ( $wpdb ) { return $wpdb->prepare( '%s', $s ); }, $jeux ) );
	$p = jdr_table(); $h = jdr_table_hist();
	if ( 'total' === $periode ) {
		foreach ( (array) $wpdb->get_results( "SELECT user_id, SUM(xp) AS x FROM $p WHERE user_id IN ($u) AND jeu IN ($j) GROUP BY user_id" ) as $r ) { $pts[ (int) $r->user_id ] = max( 0, (int) $r->x ); } // phpcs:ignore
		return $pts;
	}
	$lundi = jrl_cl_lundi();
	$base = array(); $semaine = array();
	/* XP au dernier jour d'activité avant lundi */
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT h.user_id, h.jeu, h.xp FROM $h h WHERE h.user_id IN ($u) AND h.jeu IN ($j) AND h.jour = (SELECT MAX(h2.jour) FROM $h h2 WHERE h2.user_id = h.user_id AND h2.jeu = h.jeu AND h2.jour < %s)", $lundi ) ) as $r ) { $base[ $r->user_id . '|' . $r->jeu ] = (int) $r->xp; } // phpcs:ignore
	/* sans trace avant lundi : la plus petite valeur de la semaine (anciennes progressions sans historique) */
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, jeu, MIN(xp) AS x FROM $h WHERE user_id IN ($u) AND jeu IN ($j) AND jour >= %s GROUP BY user_id, jeu", $lundi ) ) as $r ) { $semaine[ $r->user_id . '|' . $r->jeu ] = (int) $r->x; } // phpcs:ignore
	foreach ( (array) $wpdb->get_results( "SELECT user_id, jeu, xp, cree_le FROM $p WHERE user_id IN ($u) AND jeu IN ($j)" ) as $r ) { // phpcs:ignore
		$k = $r->user_id . '|' . $r->jeu;
		if ( isset( $base[ $k ] ) ) { $b = $base[ $k ]; }
		elseif ( get_date_from_gmt( $r->cree_le, 'Y-m-d' ) >= $lundi ) { $b = 0; }
		else { $b = $semaine[ $k ] ?? (int) $r->xp; }
		$pts[ (int) $r->user_id ] += max( 0, (int) $r->xp - $b );
	}
	return $pts;
}

/** Classement d'un groupe : liste de array( r => rang, n => nom, p => points, m => moi ). */
function jrl_cl_liste( $gid, $periode, array $jeux, $moi ) {
	$uids = array_values( array_filter( jrl_cl_membres( $gid ), function ( $id ) use ( $moi ) { return (int) $id === (int) $moi || ! user_can( $id, 'edit_posts' ); } ) );
	$pts = jrl_cl_points( $uids, $periode, $jeux );
	$pts = array_filter( $pts, function ( $p, $id ) use ( $moi ) { return $p > 0 || (int) $id === (int) $moi; }, ARRAY_FILTER_USE_BOTH );
	$noms = array(); foreach ( array_keys( $pts ) as $id ) { $noms[ $id ] = jrl_cl_nom( $id ); }
	uksort( $pts, function ( $a, $b ) use ( $pts, $noms ) { return $pts[ $b ] <=> $pts[ $a ] ?: strnatcasecmp( $noms[ $a ], $noms[ $b ] ); } );
	$out = array(); $rang = 0; $prec = null; $i = 0;
	foreach ( $pts as $id => $p ) { $i++; if ( $p !== $prec ) { $rang = $i; $prec = $p; } $out[] = array( 'r' => $p > 0 ? $rang : 0, 'n' => $noms[ $id ], 'p' => (int) $p, 'm' => (int) $id === (int) $moi ); }
	return $out;
}

/* ---------- REST : classement pour le jeu ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'jdr/v1', '/classement/(?P<jeu>[a-z0-9_-]+)', array(
		'methods' => 'GET', 'permission_callback' => 'is_user_logged_in',
		'callback' => function ( WP_REST_Request $r ) {
			$jeu = sanitize_key( $r['jeu'] );
			if ( ! jrl_cl_on( 'jeu' ) || ! isset( jrl_jeux()[ $jeu ] ) ) { return rest_ensure_response( array( 'off' => true ) ); }
			$uid = get_current_user_id(); $groupes = jrl_cl_groupes( $uid );
			$res = array( 'groupes' => array() );
			foreach ( $groupes as $id => $n ) { $res['groupes'][] = array( 'id' => (int) $id, 'nom' => html_entity_decode( $n, ENT_QUOTES ) ); }
			if ( $groupes ) {
				$g = (int) $r->get_param( 'groupe' ); if ( ! isset( $groupes[ $g ] ) ) { $g = (int) array_key_first( $groupes ); }
				$tous = jrl_cl_jeux(); $res['groupe'] = $g;
				foreach ( array( 'semaine', 'total' ) as $per ) {
					$res[ $per ] = array( 'jeu' => jrl_cl_liste( $g, $per, array( $jeu ), $uid ), 'tous' => jrl_cl_liste( $g, $per, $tous, $uid ) );
				}
			}
			$resp = rest_ensure_response( $res ); $resp->header( 'Cache-Control', 'no-store' ); return $resp;
		},
	) );
} );

/** Podium de la semaine (tous les jeux) au-dessus du catalogue, pour l'élève connecté qui fait partie d'un groupe. */
function jrl_cl_podium_html() {
	if ( ! is_user_logged_in() || ! jrl_cl_on( 'podium' ) ) { return ''; }
	$uid = get_current_user_id();
	$groupes = jrl_cl_groupes( $uid );
	if ( ! $groupes ) { return ''; }
	if ( user_can( $uid, 'edit_posts' ) ) { /* enseignant : seulement ses propres groupes s'il en a, sinon rien */
		global $wpdb; $t = jrl_cl_table();
		$siens = $t ? array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT group_id FROM $t WHERE student_id = %d", $uid ) ) ) : array(); // phpcs:ignore
		$groupes = array_intersect_key( $groupes, array_flip( $siens ) );
		if ( ! $groupes ) { return ''; }
	}
	$h = '';
	foreach ( $groupes as $gid => $nom ) {
		$l = jrl_cl_liste( $gid, 'semaine', jrl_cl_jeux(), $uid );
		$top = array_values( array_filter( $l, function ( $x ) { return $x['p'] > 0; } ) );
		$moi = null; foreach ( $l as $x ) { if ( $x['m'] ) { $moi = $x; } }
		$h .= '<section class="jrl-podium"><div class="jrl-podium__head"><span class="jrl-podium__ic" aria-hidden="true">🏆</span><div><h3>' . esc_html( jrl_t( 'Classement de la semaine' ) ) . '</h3><p>' . esc_html( $nom . ' · ' . jrl_t( 'tous les jeux' ) ) . '</p></div></div>';
		if ( ! $top ) {
			$h .= '<p class="jrl-podium__vide">' . esc_html( jrl_t( 'Personne n’a encore de points cette semaine : soyez la première ou le premier !' ) ) . '</p>';
		} else {
			$h .= '<ol class="jrl-podium__top">';
			foreach ( array_slice( $top, 0, 3 ) as $x ) {
				$h .= '<li class="jrl-p' . (int) min( 3, $x['r'] ) . ( $x['m'] ? ' is-moi' : '' ) . '"><span class="jrl-podium__r">' . esc_html( jrl_cl_rang( $x['r'] ) ) . '</span><b>' . esc_html( $x['n'] ) . ( $x['m'] ? ' ' . esc_html( jrl_t( '(vous)' ) ) : '' ) . '</b><span>' . esc_html( number_format_i18n( $x['p'] ) ) . ' XP</span></li>';
			}
			$h .= '</ol>';
			$h .= '<p class="jrl-podium__moi">' . esc_html( $moi && $moi['p'] > 0 ? sprintf( jrl_t( 'Votre rang cette semaine : %s · %s XP' ), jrl_cl_rang( $moi['r'] ), number_format_i18n( $moi['p'] ) ) : jrl_t( 'Vous n’avez pas encore de points cette semaine : à vous de jouer !' ) ) . '</p>';
			if ( count( $top ) > 3 ) {
				$h .= '<details class="jrl-podium__tout"><summary>' . esc_html( jrl_t( 'Voir tout le classement' ) ) . '</summary><ol>';
				foreach ( $top as $x ) { $h .= '<li' . ( $x['m'] ? ' class="is-moi"' : '' ) . '><span>' . esc_html( jrl_cl_rang( $x['r'] ) ) . '</span><b>' . esc_html( $x['n'] ) . '</b><span>' . esc_html( number_format_i18n( $x['p'] ) ) . ' XP</span></li>'; }
				$h .= '</ol></details>';
			}
		}
		$h .= '</section>';
	}
	return '<div class="jrl-podiums">' . $h . '</div>';
}
