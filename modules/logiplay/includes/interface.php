<?php
/*
 * Interface LogiPlay des shortcodes, dans l'esprit des outils LogiOne (LogiLive, LogiEval…) :
 * bandeau anthracite avec le logo, salutation et cubes, puis tuiles blanches à liseré de couleur sur fond papier.
 * Attributs du catalogue : entete="non" (sans bandeau), largeur="contenu" (dans la colonne du thème au lieu de toute la largeur).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', function () {
	wp_register_style( 'logiplay', plugins_url( 'assets/logiplay.css', JRL_FILE ), array( 'jdr-launcher' ), JRL_VERSION );
	wp_register_script( 'logiplay', plugins_url( 'assets/logiplay.js', JRL_FILE ), array(), JRL_VERSION, true );
}, 30 );

function jrl_lp_enqueue() { wp_enqueue_style( 'logiplay' ); wp_enqueue_script( 'logiplay' ); }

/** Textes de l'interface dans la langue des cartes (fr, de, it). */
function jrl_lp_t( $fr ) {
	$lg = function_exists( 'jdr_lang' ) ? jdr_lang() : 'fr';
	if ( 'fr' === $lg ) { return $fr; }
	static $tr = array(
		'de' => array(
			'Jeux de révision · Logisticien·ne CFC' => 'Lernspiele · Logistiker/in EFZ',
			'Bonjour %s !' => 'Hallo %s!',
			'Révisez en jouant' => 'Lernen mit Spielen',
			'Choisissez un module : entraînement par objectif, examen blanc noté sur 6 et révision ciblée de vos erreurs.' => 'Wählen Sie ein Modul: Training pro Lernziel, Probeprüfung mit Note bis 6 und gezielte Wiederholung Ihrer Fehler.',
			'Connectez-vous pour jouer : votre progression et vos points sont enregistrés sur votre compte.' => 'Melden Sie sich an, um zu spielen: Fortschritt und Punkte werden in Ihrem Konto gespeichert.',
			'%d jeux' => '%d Spiele', '1 jeu' => '1 Spiel',
			'Jeux commencés' => 'Begonnene Spiele', 'Points d’expérience' => 'Erfahrungspunkte', 'Objectifs maîtrisés' => 'Beherrschte Lernziele', 'Meilleure note' => 'Beste Note',
			'à l’examen blanc' => 'in der Probeprüfung', 'pas encore d’examen' => 'noch keine Prüfung',
			'Vos jeux' => 'Ihre Spiele', 'Se connecter' => 'Anmelden',
			'Jouez librement, sans compte : votre progression reste dans ce navigateur. Connectez-vous pour l’enregistrer sur votre compte et participer au classement.' => 'Spielen Sie frei, ohne Konto: Ihr Fortschritt bleibt in diesem Browser. Melden Sie sich an, um ihn in Ihrem Konto zu speichern und an der Rangliste teilzunehmen.',
		),
		'it' => array(
			'Jeux de révision · Logisticien·ne CFC' => 'Giochi di ripasso · Impiegato/a in logistica AFC',
			'Bonjour %s !' => 'Buongiorno %s!',
			'Révisez en jouant' => 'Ripassi giocando',
			'Choisissez un module : entraînement par objectif, examen blanc noté sur 6 et révision ciblée de vos erreurs.' => 'Scelga un modulo: allenamento per obiettivo, esame di prova con nota su 6 e ripasso mirato dei suoi errori.',
			'Connectez-vous pour jouer : votre progression et vos points sont enregistrés sur votre compte.' => 'Effettui l’accesso per giocare: progressi e punti sono salvati nel suo account.',
			'%d jeux' => '%d giochi', '1 jeu' => '1 gioco',
			'Jeux commencés' => 'Giochi iniziati', 'Points d’expérience' => 'Punti esperienza', 'Objectifs maîtrisés' => 'Obiettivi padroneggiati', 'Meilleure note' => 'Nota migliore',
			'à l’examen blanc' => 'all’esame di prova', 'pas encore d’examen' => 'nessun esame ancora',
			'Vos jeux' => 'I suoi giochi', 'Se connecter' => 'Accedi',
			'Jouez librement, sans compte : votre progression reste dans ce navigateur. Connectez-vous pour l’enregistrer sur votre compte et participer au classement.' => 'Giochi liberamente, senza account: i progressi restano in questo browser. Acceda per salvarli nel suo account e partecipare alla classifica.',
		),
	);
	return isset( $tr[ $lg ][ $fr ] ) ? $tr[ $lg ][ $fr ] : $fr;
}

/** Cubes LogiPlay (pyramide 3-2-1, cube du sommet jaune avec le triangle « lecture »). */
function jrl_lp_art() {
	$w = 34.64; $h = 20; $e = 40;
	$cube = function ( $cx, $cy, $cls, $i, $play = false ) use ( $w, $h, $e ) {
		$f = function ( $p ) { return implode( ' ', array_map( function ( $q ) { return round( $q[0], 2 ) . ',' . round( $q[1], 2 ); }, $p ) ); };
		$t = $f( array( array( $cx, $cy - $h ), array( $cx + $w, $cy ), array( $cx, $cy + $h ), array( $cx - $w, $cy ) ) );
		$l = $f( array( array( $cx - $w, $cy ), array( $cx, $cy + $h ), array( $cx, $cy + $h + $e ), array( $cx - $w, $cy + $e ) ) );
		$r = $f( array( array( $cx + $w, $cy ), array( $cx, $cy + $h ), array( $cx, $cy + $h + $e ), array( $cx + $w, $cy + $e ) ) );
		$s = '<g class="c ' . $cls . '" style="--i:' . (int) $i . '"><polygon class="t" points="' . $t . '"/><polygon class="l" points="' . $l . '"/><polygon class="r" points="' . $r . '"/>';
		if ( $play ) { $s .= '<path class="p" d="M' . ( $cx - 7 ) . ',' . ( $cy - 9 ) . ' L' . ( $cx + 10 ) . ',' . $cy . ' L' . ( $cx - 7 ) . ',' . ( $cy + 9 ) . ' Z"/>'; }
		return $s . '</g>';
	};
	$svg = '';
	$i = 0;
	foreach ( array( -2 * $w, 0, 2 * $w ) as $dx ) { $svg .= $cube( 120 + $dx, 160, 'on', $i++ ); }
	foreach ( array( -$w, $w ) as $dx ) { $svg .= $cube( 120 + $dx, 120, 'on', $i++ ); }
	$svg .= $cube( 120, 80, 'mark', $i++, true );
	return '<svg class="lpl-iso" viewBox="40 52 160 172" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

/** Bandeau anthracite : logo, nombre de jeux, salutation, texte et cubes. */
function jrl_lp_hero( $nb ) {
	$in = is_user_logged_in();
	$hello = jrl_lp_t( 'Révisez en jouant' );
	if ( $in && function_exists( 'jdr_prenom' ) ) { $hello = sprintf( jrl_lp_t( 'Bonjour %s !' ), jdr_prenom( wp_get_current_user() ) ); }
	$lead = $in ? jrl_lp_t( 'Choisissez un module : entraînement par objectif, examen blanc noté sur 6 et révision ciblée de vos erreurs.' )
		: ( function_exists( 'jdr_visiteurs' ) && jdr_visiteurs() ? jrl_lp_t( 'Jouez librement, sans compte : votre progression reste dans ce navigateur. Connectez-vous pour l’enregistrer sur votre compte et participer au classement.' ) : jrl_lp_t( 'Connectez-vous pour jouer : votre progression et vos points sont enregistrés sur votre compte.' ) );
	$h = '<header class="lpl-hero"><div class="lpl-hero-top">'
		. '<span class="lpl-brand"><img class="lpl-logo" src="' . esc_url( plugins_url( 'assets/logiplay-logo-blanc.svg', JRL_FILE ) ) . '" alt="LogiPlay by LogiProf" width="270" height="80" decoding="async"></span>'
		. '<span class="lpl-pill"><span class="lpl-dot" aria-hidden="true"></span>' . esc_html( 1 === (int) $nb ? jrl_lp_t( '1 jeu' ) : sprintf( jrl_lp_t( '%d jeux' ), (int) $nb ) ) . ' · FR · DE · IT</span>'
		. '</div><div class="lpl-hero-main"><div class="lpl-hero-text">'
		. '<p class="lpl-kicker">' . esc_html( jrl_lp_t( 'Jeux de révision · Logisticien·ne CFC' ) ) . '</p>'
		. '<h2 class="lpl-hello">' . esc_html( $hello ) . '</h2>'
		. '<p class="lpl-lead">' . esc_html( $lead ) . '</p>';
	if ( ! $in && function_exists( 'jdr_login_url' ) ) { $h .= '<p class="lpl-cta"><a class="lpl-btn lpl-btn-yellow" href="' . esc_url( jdr_login_url() ) . '">' . esc_html( jrl_lp_t( 'Se connecter' ) ) . '</a></p>'; }
	return $h . '</div><div class="lpl-art">' . jrl_lp_art() . '</div></div></header>';
}

/** Tuiles de synthèse de l'élève connecté, calculées sur les jeux affichés. */
function jrl_lp_tuiles( $slugs, $jeux ) {
	if ( ! is_user_logged_in() || ! function_exists( 'jdr_table' ) || ! function_exists( 'jdr_summary' ) ) { return ''; }
	global $wpdb;
	$uid = get_current_user_id();
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT jeu, data FROM ' . jdr_table() . ' WHERE user_id = %d', $uid ) ); // phpcs:ignore
	$par = array(); foreach ( (array) $rows as $r ) { $par[ $r->jeu ] = $r->data; }
	$commences = 0; $xp = 0; $done = 0; $tot = 0; $best = 0;
	foreach ( $slugs as $slug ) {
		$n = isset( $jeux[ $slug ]['contenu'] ) ? count( (array) $jeux[ $slug ]['contenu'] ) : 0;
		$s = isset( $par[ $slug ] ) ? jdr_summary( json_decode( $par[ $slug ], true ) ) : null;
		$tot += $s && $s['obj'] ? count( $s['obj'] ) : $n;
		if ( ! $s ) { continue; }
		if ( $s['xp'] > 0 || $s['global'] > 0 ) { $commences++; }
		$xp += $s['xp']; $done += $s['done']; $best = max( $best, (float) $s['best'] );
	}
	$t = function ( $cls, $label, $num, $sub ) {
		return '<div class="lpl-tile ' . $cls . '"><span class="lpl-tile-label">' . esc_html( $label ) . '</span><span class="lpl-tile-num">' . $num . '</span>' . ( $sub ? '<span class="lpl-tile-sub">' . esc_html( $sub ) . '</span>' : '' ) . '</div>';
	};
	return '<div class="lpl-summary">'
		. $t( 'lpl-tile-green', jrl_lp_t( 'Jeux commencés' ), (int) $commences . '<small>/' . count( $slugs ) . '</small>', '' )
		. $t( 'lpl-tile-yellow', jrl_lp_t( 'Points d’expérience' ), esc_html( number_format_i18n( $xp ) ) . '<small> XP</small>', '' )
		. $t( 'lpl-tile-ink', jrl_lp_t( 'Objectifs maîtrisés' ), (int) $done . '<small>/' . (int) $tot . '</small>', '' )
		. $t( 'lpl-tile-grey', jrl_lp_t( 'Meilleure note' ), $best > 0 ? esc_html( number_format_i18n( $best, 1 ) ) . '<small>/6</small>' : '–', $best > 0 ? jrl_lp_t( 'à l’examen blanc' ) : jrl_lp_t( 'pas encore d’examen' ) )
		. '</div>';
}
