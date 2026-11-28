<?php
/*
 * Moteur commun des jeux de révision (identique dans toutes les extensions).
 * Chaque extension embarque ce fichier ; au chargement de WordPress, la version la plus récente est utilisée.
 * Ne pas renommer les fonctions. Toute modification : incrémenter la version ci-dessous (3 endroits).
 *
 * 1.3.0 : jeu réservé aux utilisateurs connectés et servi par le site (?jdr_jeu=<slug>),
 *         progression dans une table dédiée ({prefix}jdr_progression) + historique quotidien,
 *         protection contre l'écrasement par un appareil en retard, suivi des participants et export CSV.
 * 1.4.0 : progression conservée telle quelle (les objets JSON vides ne deviennent plus des listes : détail des réussites
 *         et épreuves à revoir préservés), bilan de chaque session enregistré ({prefix}jdr_sessions) et envoyé par
 *         e-mail à l'adresse du compte (bonnes et mauvaises réponses, bonne réponse mise en évidence), réglage on/off.
 * 1.4.1 : carte sans la puce « public » ; bouton « Objectifs » qui déplie la liste (titre de chaque objectif en gras,
 *         objectif(s) en puces dessous). 'contenu' accepte array( 'titre' => …, 'objectifs' => array( … ) ) ou du texte.
 * 1.5.0 : points d'accroche pour l'extension de gestion : filtre jdr_acces (carte verrouillée, page du jeu et REST
 *         refusés), filtre jdr_carte_extra (bandeau sur la carte), action jdr_session_terminee.
 * 1.6.0 : prénom de la fiche du compte (first_name, renseigné aussi par Ultimate Member ; à défaut le nom public)
 *         transmis au jeu (JDR_CFG.user.prenom) pour saluer la personne avant de jouer, et utilisé dans l'e-mail de bilan.
 * 1.7.0 : données durables. Tables vérifiées et recréées si elles manquent ; copie complète quotidienne de chaque
 *         progression dans l'historique (30 derniers jours d'activité restaurables, jdr_restaurer_jour) ;
 *         supprimer un jeu ne supprime plus ses données, sauf réglage explicite (option jdr_effacer_donnees, fonction
 *         jdr_effacer_donnees_ok() appelée par la désinstallation de chaque jeu) ; sauvegarde complète en un fichier
 *         (export JSON) et restauration (import) dans Outils > LogiPlay.
 *         Dossier de formation : clés 'dossier' et 'corrige' (URL des PDF livrés avec le jeu), affichés sous le bouton de la carte ;
 *         filtre jdr_carte_pied ( $html, $jeu, $uid, $peut_jouer ) pour le remplacer ou le compléter (extension de gestion).
 * 1.7.2 : fusion avec la lignée 1.6.x des jeux enfants : prénom transmis aussi dans JDR_CFG.user.first ;
 *         'dossier' en texte = nom du dossier de l'école affiché sous le titre (1.6.2), 'dossier' en URL = PDF sous le bouton ;
 *         carte en deux temps (1.6.1) : logo + sur-titre, titre et durée côte à côte, puis explication, progression,
 *         objectifs et bouton sur toute la largeur de la carte, marge intérieure de 15 px ; titre lisible sur tous les thèmes.
 * 1.8.0 : jeux bilingues français / allemand. Shortcodes avec langue="de" ; textes de la carte en allemand (clé 'i18n'
 *         => array( 'de' => array( 'titre', 'sous_titre', 'duree', 'description', 'contenu', 'bouton'… ) ) du jeu) ;
 *         page du jeu ?lang=de servie avec index.de.html s'il existe (même identifiant, même progression).
 * 1.8.1 : carte du jeu : le bouton « Objectifs » déplie une liste à puces, un objectif par ligne (code et titre seulement).
 * 1.8.2 : carte du jeu sans le paragraphe de description.
 * 1.8.3 : carte du jeu : titre et durée sur toute la largeur, sous l'icône.
 * 1.8.4 : remise à zéro d'une progression par l'administration (marque « raz » : les appareils qui ne l'ont pas vue sont refusés).
 * 1.8.5 : styles du podium « Classement de la semaine ».
 * 1.9.0 : italien (langue="it", ?lang=it, index.it.html, clé i18n 'it') à côté du français et de l’allemand.
 * 1.10.0 : mode visiteur (filtre jdr_visiteurs) : sans compte, le jeu s'ouvre quand même (JDR_CFG.guest), la progression
 *          reste dans le navigateur ; ni enregistrement en base, ni bilan, ni classement.
 * 1.7.3 : aucun e-mail. Le bilan de chaque session est seulement enregistré dans la base (espace de l'élève, suivi du
 *         formateur) ; l'envoi du bilan par e-mail et son réglage sont supprimés. Seule l'extension de gestion écrit aux élèves.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$GLOBALS['jdr_engines']['1.10.0'] = dirname( __DIR__ );
if ( ! function_exists( 'jdr_engine_pick' ) ) {
	function jdr_engine_pick() {
		if ( function_exists( 'jdr_boot' ) ) { return; } /* un moteur 1.1.0 ou plus ancien est déjà chargé */
		$e = isset( $GLOBALS['jdr_engines'] ) && is_array( $GLOBALS['jdr_engines'] ) ? $GLOBALS['jdr_engines'] : array();
		if ( ! $e ) { return; }
		uksort( $e, 'version_compare' ); end( $e ); $v = key( $e );
		$fn = 'jdr_engine_' . str_replace( '.', '_', $v );
		if ( function_exists( $fn ) ) { $fn( $e[ $v ] ); }
	}
	add_action( 'plugins_loaded', 'jdr_engine_pick', 1 );
}
if ( ! function_exists( 'jdr_engine_1_8_0' ) && ! function_exists( 'jdr_engine_1_8_1' ) && ! function_exists( 'jdr_engine_1_8_2' ) && ! function_exists( 'jdr_engine_1_8_3' ) && ! function_exists( 'jdr_engine_1_8_4' ) && ! function_exists( 'jdr_engine_1_8_5' ) && ! function_exists( 'jdr_engine_1_9_0' ) && ! function_exists( 'jdr_engine_1_10_0' ) ) {
	function jdr_engine_1_10_0( $dir ) {
		define( 'JDR_ENGINE_VERSION', '1.10.0' );
		define( 'JDR_ENGINE_URL', trailingslashit( plugin_dir_url( $dir . '/catalogue' ) ) );
		define( 'JDR_DB_VERSION', '3' );

		/* visiteurs non connectés autorisés à jouer (sans enregistrement) : réglage de l'extension, filtre jdr_visiteurs */
		function jdr_visiteurs( $id = '' ) { return (bool) apply_filters( 'jdr_visiteurs', false, (string) $id ); } /* LogiOne 1.56.1 : réglable jeu par jeu */
		function jdr_jeux() { $j = apply_filters( 'jdr_jeux', array() ); return is_array( $j ) ? $j : array(); }
		/* langue des cartes et de la page du jeu : 'fr' (défaut), 'de' ou 'it' */
		function jdr_lang( $l = null ) { static $cur = 'fr'; if ( null !== $l ) { $l = strtolower( (string) $l ); $cur = in_array( $l, array( 'de', 'it' ), true ) ? $l : 'fr'; } return $cur; }
		function jdr_t( $s ) {
			if ( 'fr' === jdr_lang() ) { return $s; }
			static $it = array(
				'▶ Jouer' => '▶ Gioca', '▶ Commencer' => '▶ Inizia', '▶ Reprendre' => '▶ Continua', 'Se connecter pour jouer' => 'Accedere per giocare',
				'Ce jeu n’est pas disponible pour votre groupe.' => 'Questo gioco non è disponibile per il suo gruppo.',
				'Ce jeu est réservé aux membres du site. Connectez-vous : votre progression et votre score sont enregistrés sur votre compte.' => 'Questo gioco è riservato ai membri del sito. Effettui l’accesso: progressi e punteggio sono salvati nel suo account.',
				'Votre progression' => 'I suoi progressi', 'Pas encore commencé · %d objectifs à découvrir' => 'Non ancora iniziato · %d obiettivi da scoprire',
				'Points d’expérience' => 'Punti esperienza', 'Niveau' => 'Livello', 'niv.' => 'liv.', 'Objectifs maîtrisés (80 % ou plus)' => 'Obiettivi padroneggiati (80 % o più)',
				'objectifs maîtrisés' => 'obiettivi padroneggiati', 'Meilleure note à l’examen blanc' => 'Nota migliore all’esame di prova', 'à l’examen' => 'all’esame',
				'Maîtrise globale' => 'Padronanza globale', 'Maîtrise par objectif' => 'Padronanza per obiettivo',
				'Dossier de formation' => 'Dossier di formazione', 'Notions clés et exercices sur papier · PDF' => 'Nozioni chiave ed esercizi su carta · PDF',
				'Télécharger' => 'Scarica', 'Corrigé' => 'Soluzioni', 'Dossier : ' => 'Dossier: ', 'Objectifs (%d)' => 'Obiettivi (%d)',
				'Jouez sans compte : votre progression reste dans ce navigateur. Connectez-vous pour l’enregistrer sur votre compte et participer au classement.' => 'Giochi senza account: i progressi restano in questo browser. Acceda per salvarli nel suo account e partecipare alla classifica.', 'Se connecter' => 'Accedi',
				'Jeu de révision' => 'Gioco di ripasso', 'Jeu non disponible' => 'Gioco non disponibile', 'Retour' => 'Indietro',
			);
			if ( 'it' === jdr_lang() ) { return isset( $it[ $s ] ) ? $it[ $s ] : $s; }
			static $de = array(
				'▶ Jouer' => '▶ Spielen', '▶ Commencer' => '▶ Starten', '▶ Reprendre' => '▶ Fortsetzen', 'Se connecter pour jouer' => 'Anmelden, um zu spielen',
				'Ce jeu n’est pas disponible pour votre groupe.' => 'Dieses Spiel ist für Ihre Gruppe nicht verfügbar.',
				'Ce jeu est réservé aux membres du site. Connectez-vous : votre progression et votre score sont enregistrés sur votre compte.' => 'Dieses Spiel ist den Mitgliedern der Website vorbehalten. Melden Sie sich an: Fortschritt und Punkte werden in Ihrem Konto gespeichert.',
				'Votre progression' => 'Ihr Fortschritt', 'Pas encore commencé · %d objectifs à découvrir' => 'Noch nicht begonnen · %d Lernziele zu entdecken',
				'Points d’expérience' => 'Erfahrungspunkte', 'Niveau' => 'Stufe', 'niv.' => 'Stufe', 'Objectifs maîtrisés (80 % ou plus)' => 'Beherrschte Lernziele (80 % oder mehr)',
				'objectifs maîtrisés' => 'Lernziele beherrscht', 'Meilleure note à l’examen blanc' => 'Beste Note in der Probeprüfung', 'à l’examen' => 'in der Prüfung',
				'Maîtrise globale' => 'Lernstand gesamt', 'Maîtrise par objectif' => 'Lernstand pro Lernziel',
				'Dossier de formation' => 'Ausbildungsdossier', 'Notions clés et exercices sur papier · PDF' => 'Grundbegriffe und Übungen auf Papier · PDF',
				'Télécharger' => 'Herunterladen', 'Corrigé' => 'Lösungen', 'Dossier : ' => 'Dossier: ', 'Objectifs (%d)' => 'Lernziele (%d)',
				'Jouez sans compte : votre progression reste dans ce navigateur. Connectez-vous pour l’enregistrer sur votre compte et participer au classement.' => 'Spielen Sie ohne Konto: Ihr Fortschritt bleibt in diesem Browser. Melden Sie sich an, um ihn in Ihrem Konto zu speichern und an der Rangliste teilzunehmen.', 'Se connecter' => 'Anmelden',
				'Jeu de révision' => 'Lernspiel', 'Jeu non disponible' => 'Spiel nicht verfügbar', 'Retour' => 'Zurück',
			);
			return isset( $de[ $s ] ) ? $de[ $s ] : $s;
		}
		/* textes de la carte dans la langue courante : clé 'i18n' => array( 'de' => array( 'titre' => …, … ), 'it' => … ) */
		function jdr_loc( $jeu ) {
			$lg = jdr_lang(); if ( 'fr' === $lg || ! is_array( $jeu ) ) { return $jeu; }
			$d = ( isset( $jeu['i18n'][ $lg ] ) && is_array( $jeu['i18n'][ $lg ] ) ) ? $jeu['i18n'][ $lg ] : array();
			foreach ( array( 'titre', 'sous_titre', 'duree', 'description', 'contenu', 'bouton', 'bouton_suite', 'bouton_connexion' ) as $k ) {
				if ( isset( $d[ $k ] ) && '' !== $d[ $k ] && array() !== $d[ $k ] ) { $jeu[ $k ] = $d[ $k ]; }
			}
			foreach ( array( 'bouton', 'bouton_suite', 'bouton_connexion' ) as $k ) { if ( isset( $jeu[ $k ] ) ) { $jeu[ $k ] = jdr_t( $jeu[ $k ] ); } }
			return $jeu;
		}
		/* true si l'utilisateur peut jouer, sinon le message à afficher (filtre jdr_acces, utilisé par l'extension de gestion) */
		function jdr_acces( $id, $uid = 0 ) {
			$uid = $uid ? (int) $uid : get_current_user_id();
			$r = apply_filters( 'jdr_acces', true, $id, $uid );
			if ( true === $r ) { return true; }
			return ( is_string( $r ) && '' !== $r ) ? $r : jdr_t( 'Ce jeu n’est pas disponible pour votre groupe.' );
		}
		function jdr_rest_acces( $j ) { $a = jdr_acces( $j ); return true === $a ? true : new WP_Error( 'jdr_acces', $a, array( 'status' => 403 ) ); }
		function jdr_boot() {
			add_action( 'init', 'jdr_register_assets' ); add_action( 'init', 'jdr_register_shortcodes' ); add_action( 'init', 'jdr_maybe_install_db', 5 );
			add_action( 'rest_api_init', 'jdr_register_rest' ); add_action( 'admin_menu', 'jdr_admin_menu' );
			add_action( 'template_redirect', 'jdr_serve_game', 0 ); add_action( 'admin_post_jdr_export', 'jdr_export_csv' ); add_action( 'admin_post_jdr_reglages', 'jdr_save_settings' );
			add_action( 'admin_post_jdr_sauvegarde', 'jdr_backup_download' ); add_action( 'admin_post_jdr_restauration', 'jdr_backup_restore' );
			add_action( 'deleted_user', 'jdr_delete_user_rows' );
		}

		/* ---------- base de données ---------- */
		function jdr_table() { global $wpdb; return $wpdb->prefix . 'jdr_progression'; }
		function jdr_table_hist() { global $wpdb; return $wpdb->prefix . 'jdr_historique'; }
		function jdr_table_sess() { global $wpdb; return $wpdb->prefix . 'jdr_sessions'; }
		/* les trois tables existent-elles ? (vérifié au plus toutes les 12 h côté site, à chaque page côté administration) */
		function jdr_tables_ok() {
			if ( ! is_admin() && get_transient( 'jdr_tables_ok' ) ) { return true; }
			global $wpdb;
			foreach ( array( jdr_table(), jdr_table_hist(), jdr_table_sess() ) as $t ) { if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { delete_transient( 'jdr_tables_ok' ); return false; } }
			set_transient( 'jdr_tables_ok', 1, 12 * HOUR_IN_SECONDS ); return true;
		}
		/* la désinstallation d'un jeu efface-t-elle ses données ? Non, sauf réglage explicite dans Outils > LogiPlay */
		function jdr_effacer_donnees_ok() { return '1' === get_option( 'jdr_effacer_donnees', '0' ); }
		function jdr_maybe_install_db() {
			if ( get_option( 'jdr_db_version' ) === JDR_DB_VERSION && jdr_tables_ok() ) { return; }
			global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$cs = $wpdb->get_charset_collate(); $t = jdr_table(); $h = jdr_table_hist();
			dbDelta( "CREATE TABLE $t (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  jeu varchar(64) NOT NULL,
  data longtext NOT NULL,
  xp int(10) unsigned NOT NULL DEFAULT 0,
  maitrise tinyint(3) unsigned NOT NULL DEFAULT 0,
  objectifs tinyint(3) unsigned NOT NULL DEFAULT 0,
  meilleure_note decimal(3,1) NOT NULL DEFAULT 0,
  sauvegardes int(10) unsigned NOT NULL DEFAULT 0,
  cree_le datetime NOT NULL,
  maj_le datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_jeu (user_id,jeu),
  KEY jeu (jeu)
) $cs;" );
			dbDelta( "CREATE TABLE $h (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  jeu varchar(64) NOT NULL,
  jour date NOT NULL,
  xp int(10) unsigned NOT NULL DEFAULT 0,
  maitrise tinyint(3) unsigned NOT NULL DEFAULT 0,
  objectifs tinyint(3) unsigned NOT NULL DEFAULT 0,
  meilleure_note decimal(3,1) NOT NULL DEFAULT 0,
  data longtext NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_jeu_jour (user_id,jeu,jour)
) $cs;" );
			$se = jdr_table_sess();
			dbDelta( "CREATE TABLE $se (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  jeu varchar(64) NOT NULL,
  sid varchar(40) NOT NULL,
  mode varchar(16) NOT NULL DEFAULT '',
  titre varchar(190) NOT NULL DEFAULT '',
  bonnes smallint(5) unsigned NOT NULL DEFAULT 0,
  total smallint(5) unsigned NOT NULL DEFAULT 0,
  note decimal(3,1) NOT NULL DEFAULT 0,
  xp int(10) unsigned NOT NULL DEFAULT 0,
  duree int(10) unsigned NOT NULL DEFAULT 0,
  detail longtext NOT NULL,
  envoye tinyint(1) unsigned NOT NULL DEFAULT 0,
  cree_le datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_jeu_sid (user_id,jeu,sid),
  KEY jeu_date (jeu,cree_le)
) $cs;" );
			/* reprise des progressions enregistrées par les versions précédentes (méta utilisateur) */
			$rows = $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE 'jdr\\_progression\\_%'" );
			foreach ( (array) $rows as $r ) {
				$jeu = substr( $r->meta_key, strlen( 'jdr_progression_' ) ); $d = json_decode( $r->meta_value, true );
				if ( $jeu && is_array( $d ) && ! jdr_row( (int) $r->user_id, $jeu ) ) { jdr_write( (int) $r->user_id, $jeu, $d, $r->meta_value ); }
			}
			update_option( 'jdr_db_version', JDR_DB_VERSION, false ); delete_transient( 'jdr_tables_ok' );
		}
		function jdr_row( $uid, $jeu ) {
			global $wpdb; if ( ! $uid ) { return null; }
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jdr_table() . ' WHERE user_id = %d AND jeu = %s', $uid, $jeu ) );
		}
		function jdr_cols( $d ) {
			$s = jdr_summary( $d ); $xp = $s ? $s['xp'] : (int) ( $d['state']['xp'] ?? 0 );
			return array( 'xp' => max( 0, $xp ), 'maitrise' => $s ? $s['global'] : 0, 'objectifs' => $s ? $s['done'] : 0, 'meilleure_note' => $s ? $s['best'] : (float) ( $d['state']['best'] ?? 0 ) );
		}
		/* $d : copie en tableaux PHP (colonnes de suivi) ; $json : progression telle qu'envoyée par le jeu, stockée sans transformation
		   (un aller-retour en tableaux PHP change les objets vides {} en listes [] et le jeu perd alors son détail). */
		function jdr_write( $uid, $jeu, $d, $json = null ) {
			global $wpdb; $now = current_time( 'mysql', true ); $cols = jdr_cols( $d ); if ( null === $json ) { $json = wp_json_encode( $d ); }
			$row = jdr_row( $uid, $jeu );
			if ( $row ) {
				$ok = $wpdb->update( jdr_table(), array_merge( $cols, array( 'data' => $json, 'sauvegardes' => (int) $row->sauvegardes + 1, 'maj_le' => $now ) ), array( 'id' => $row->id ) );
			} else {
				$ok = $wpdb->insert( jdr_table(), array_merge( $cols, array( 'user_id' => $uid, 'jeu' => $jeu, 'data' => $json, 'sauvegardes' => 1, 'cree_le' => $now, 'maj_le' => $now ) ) );
				if ( false === $ok ) { $row = jdr_row( $uid, $jeu ); if ( $row ) { $ok = $wpdb->update( jdr_table(), array_merge( $cols, array( 'data' => $json, 'maj_le' => $now ) ), array( 'id' => $row->id ) ); } }
			}
			if ( false === $ok ) { return false; }
			/* historique : une ligne par participant, par jeu et par jour */
			$jour = current_time( 'Y-m-d' ); $h = jdr_table_hist();
			$hid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $h WHERE user_id = %d AND jeu = %s AND jour = %s", $uid, $jeu, $jour ) );
			$snap = array_merge( $cols, array( 'data' => $json ) ); /* copie complète du jour : restaurable */
			if ( $hid ) { $wpdb->update( $h, $snap, array( 'id' => $hid ) ); } else { $wpdb->insert( $h, array_merge( $snap, array( 'user_id' => $uid, 'jeu' => $jeu, 'jour' => $jour ) ) ); }
			/* copies complètes gardées pour les N derniers jours d'activité (30 par défaut) ; les lignes plus anciennes gardent leurs chiffres */
			$garde = max( 1, (int) apply_filters( 'jdr_jours_copies', 30 ) );
			$limite = $wpdb->get_var( $wpdb->prepare( "SELECT jour FROM $h WHERE user_id = %d AND jeu = %s AND data IS NOT NULL ORDER BY jour DESC LIMIT %d, 1", $uid, $jeu, $garde ) );
			if ( $limite ) { $wpdb->query( $wpdb->prepare( "UPDATE $h SET data = NULL WHERE user_id = %d AND jeu = %s AND jour <= %s", $uid, $jeu, $limite ) ); }
			return true;
		}
		function jdr_delete_user_rows( $uid ) { global $wpdb; foreach ( array( jdr_table(), jdr_table_hist(), jdr_table_sess() ) as $t ) { $wpdb->delete( $t, array( 'user_id' => (int) $uid ) ); } }

		function jdr_register_assets() {
			wp_register_style( 'jdr-launcher', JDR_ENGINE_URL . 'assets/launcher.css', array(), JDR_ENGINE_VERSION );
			wp_register_script( 'jdr-launcher', JDR_ENGINE_URL . 'assets/launcher.js', array(), JDR_ENGINE_VERSION, true );
		}
		/* prénom de la fiche du compte (champ « Prénom » de WordPress ou d'Ultimate Member), sinon le nom public */
		function jdr_prenom( $u ) {
			$p = trim( (string) get_user_meta( $u->ID, 'first_name', true ) );
			if ( '' === $p ) { $p = $u->display_name ? $u->display_name : $u->user_login; }
			return $p;
		}
		function jdr_cfg( $id = '' ) {
			$u = wp_get_current_user(); $in = is_user_logged_in();
			return array( 'rest' => esc_url_raw( rest_url( 'jdr/v1/' ) ), 'nonce' => $in ? wp_create_nonce( 'wp_rest' ) : '', 'loggedIn' => $in, 'jeu' => $id,
				'user' => $in ? array( 'id' => (int) $u->ID, 'name' => $u->display_name ? $u->display_name : $u->user_login, 'prenom' => jdr_prenom( $u ), 'first' => trim( (string) get_user_meta( $u->ID, 'first_name', true ) ) ) : null,
				'ajax' => admin_url( 'admin-ajax.php' ), 'login' => jdr_login_url(), 'site' => home_url( '/' ), 'guest' => ! $in && jdr_visiteurs( $id ) );
		}
		function jdr_enqueue() {
			static $done = false; wp_enqueue_style( 'jdr-launcher' ); wp_enqueue_script( 'jdr-launcher' );
			if ( $done ) { return; } $done = true;
			$cfg = jdr_cfg(); unset( $cfg['user'] );
			wp_add_inline_script( 'jdr-launcher', 'window.JDR=window.JDR||{};window.JDR.cfg=' . wp_json_encode( $cfg ) . ';', 'before' );
		}

		/* ---------- page du jeu servie par le site (réservée aux connectés) ---------- */
		function jdr_game_url( $id, $lang = '' ) { $q = array( 'jdr_jeu' => $id, 'wp' => '1', 'jeu' => $id ); if ( in_array( $lang, array( 'de', 'it' ), true ) ) { $q['lang'] = $lang; } return add_query_arg( $q, home_url( '/' ) ); } /* wp=1 & jeu : compatibilité avec les moteurs de jeu plus anciens */
		function jdr_serve_game() {
			if ( empty( $_GET['jdr_jeu'] ) ) { return; }
			$id = sanitize_key( wp_unslash( $_GET['jdr_jeu'] ) ); $jeux = jdr_jeux();
			if ( ! isset( $jeux[ $id ] ) ) { status_header( 404 ); exit( 'Jeu introuvable.' ); }
			$lg = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : ''; jdr_lang( $lg );
			if ( ! is_user_logged_in() && ! jdr_visiteurs( $id ) ) { wp_safe_redirect( jdr_login_url( jdr_game_url( $id, $lg ) ) ); exit; }
			$acc = is_user_logged_in() ? jdr_acces( $id ) : true; /* visiteur : jeu ouvert, rien n'est enregistré */
			if ( true !== $acc ) {
				status_header( 403 ); nocache_headers(); header( 'Content-Type: text/html; charset=utf-8' ); $ref = wp_get_referer();
				echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( jdr_t( 'Jeu non disponible' ) ) . '</title></head><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#eef3f8;font-family:system-ui,sans-serif;color:#1f2328"><div style="max-width:420px;background:#fff;border-radius:16px;padding:28px;text-align:center;box-shadow:0 6px 24px rgba(15,33,55,.08)"><div style="font-size:2rem">🔒</div><h1 style="font-size:1.25rem">' . esc_html( jdr_t( 'Jeu non disponible' ) ) . '</h1><p>' . esc_html( $acc ) . '</p><p><a href="' . esc_url( $ref ? $ref : home_url( '/' ) ) . '" onclick="if(window.parent!==window){parent.postMessage({jdr:\'close\'},location.origin);return false}">' . esc_html( jdr_t( 'Retour' ) ) . '</a></p></div></body></html>';
				exit;
			}
			$url = strtok( jdr_defaults( $jeux[ $id ] )['url'], '?' );
			$base = trailingslashit( dirname( $url ) );
			$file = wp_normalize_path( WP_PLUGIN_DIR . substr( $url, strlen( plugins_url() ) ) );
			if ( in_array( $lg, array( 'de', 'it' ), true ) ) { $fde = preg_replace( '#index\.html$#', 'index.' . $lg . '.html', $file ); if ( $fde !== $file && is_readable( $fde ) ) { $file = $fde; } } /* version allemande ou italienne du jeu */
			$html = ( 0 === strpos( $file, wp_normalize_path( WP_PLUGIN_DIR ) ) && is_readable( $file ) ) ? file_get_contents( $file ) : false;
			if ( ! $html ) { status_header( 500 ); exit( 'Fichiers du jeu introuvables.' ); }
			$cfg = jdr_cfg( $id ); $ref = wp_get_referer(); $cfg['back'] = $ref ? $ref : home_url( '/' ); if ( ! empty( $cfg['guest'] ) ) { $cfg['login'] = jdr_login_url( $cfg['back'] ); } /* visiteur : après connexion, retour à la page des jeux */
			$inject = '<base href="' . esc_url( $base ) . '"><script>window.JDR_CFG=' . wp_json_encode( $cfg ) . ';</script>';
			$html = preg_replace( '/<head>/i', '<head>' . $inject, $html, 1 );
			nocache_headers(); header( 'Content-Type: text/html; charset=utf-8' ); header( 'X-Robots-Tag: noindex, nofollow' ); header( 'X-Frame-Options: SAMEORIGIN' );
			echo $html; exit; // phpcs:ignore WordPress.Security.EscapeOutput -- page du jeu fournie par l'extension
		}

		function jdr_register_shortcodes() { add_shortcode( 'jeu_revision', 'jdr_shortcode_jeu' ); add_shortcode( 'jeux_revision', 'jdr_shortcode_catalogue' ); }
		function jdr_shortcode_jeu( $atts ) {
			$atts = shortcode_atts( array( 'id' => '', 'langue' => '' ), $atts, 'jeu_revision' ); $jeux = jdr_jeux(); $id = sanitize_key( $atts['id'] );
			if ( '' === $id && count( $jeux ) === 1 ) { $id = array_key_first( $jeux ); }
			if ( ! isset( $jeux[ $id ] ) ) { return current_user_can( 'edit_posts' ) ? '<p><em>Jeu de révision introuvable : vérifiez l’identifiant du shortcode.</em></p>' : ''; }
			jdr_enqueue(); $prev = jdr_lang(); if ( '' !== $atts['langue'] ) { jdr_lang( $atts['langue'] ); }
			$h = '<div class="jdr-grid jdr-grid--one">' . jdr_card( $id, $jeux[ $id ] ) . '</div>'; jdr_lang( $prev ); return $h;
		}
		function jdr_shortcode_catalogue( $atts = array() ) {
			$atts = shortcode_atts( array( 'langue' => '' ), $atts, 'jeux_revision' );
			$jeux = jdr_jeux(); if ( ! $jeux ) { return ''; } jdr_enqueue(); $prev = jdr_lang(); if ( '' !== $atts['langue'] ) { jdr_lang( $atts['langue'] ); }
			$out = '<div class="jdr-grid">'; foreach ( $jeux as $id => $jeu ) { $out .= jdr_card( $id, $jeu ); } jdr_lang( $prev ); return $out . '</div>';
		}
		function jdr_defaults( $jeu ) {
			return wp_parse_args( $jeu, array( 'titre' => '', 'sous_titre' => '', 'public' => '', 'duree' => '', 'description' => '', 'contenu' => array(), 'logo' => '', 'url' => '', 'couleur' => '#1f4e8c', 'dossier' => '', 'corrige' => '', 'bouton' => '▶ Jouer', 'bouton_suite' => '▶ Reprendre', 'bouton_connexion' => 'Se connecter pour jouer' ) );
		}

		/* ---------- progression de l'utilisateur connecté ---------- */
		function jdr_user_data( $id, $uid = 0 ) {
			$uid = $uid ? $uid : get_current_user_id(); if ( ! $uid ) { return null; }
			$row = jdr_row( $uid, $id ); $d = $row ? json_decode( $row->data, true ) : null;
			return is_array( $d ) ? $d : null;
		}
		/* progression renvoyée au jeu : décodée en objets, pour que {} reste {} */
		function jdr_user_data_obj( $id, $uid = 0 ) {
			$uid = $uid ? $uid : get_current_user_id(); if ( ! $uid ) { return null; }
			$row = jdr_row( $uid, $id ); $d = $row ? json_decode( $row->data ) : null;
			return is_object( $d ) ? $d : null;
		}
		function jdr_summary( $d ) {
			if ( ! is_array( $d ) || empty( $d['sum'] ) || ! is_array( $d['sum'] ) ) { return null; }
			$s = $d['sum']; $obj = array();
			foreach ( ( isset( $s['obj'] ) && is_array( $s['obj'] ) ? array_slice( $s['obj'], 0, 12 ) : array() ) as $o ) {
				if ( ! is_array( $o ) ) { continue; }
				$obj[] = array( 'code' => isset( $o['code'] ) ? (string) $o['code'] : '', 't' => isset( $o['t'] ) ? (string) $o['t'] : '', 'm' => max( 0, min( 100, (int) ( $o['m'] ?? 0 ) ) ), 'col' => isset( $o['col'] ) ? sanitize_key( $o['col'] ) : '' );
			}
			return array( 'xp' => max( 0, (int) ( $s['xp'] ?? 0 ) ), 'lv' => max( 1, (int) ( $s['lv'] ?? 1 ) ), 'lvs' => max( 1, (int) ( $s['lvs'] ?? 1 ) ), 'level' => (string) ( $s['level'] ?? '' ),
				'global' => max( 0, min( 100, (int) ( $s['global'] ?? 0 ) ) ), 'done' => max( 0, (int) ( $s['done'] ?? 0 ) ), 'best' => max( 0, min( 6, (float) ( $s['best'] ?? 0 ) ) ), 'obj' => $obj );
		}
		function jdr_started( $id ) {
			if ( ! is_user_logged_in() ) { return false; }
			$d = jdr_user_data( $id ); if ( ! $d ) { return false; }
			$s = jdr_summary( $d ); if ( $s ) { return $s['xp'] > 0 || $s['global'] > 0; }
			return ! empty( $d['state']['xp'] );
		}
		function jdr_cap_hex( $c ) {
			$m = array( 'teal' => '#0e9aa7', 'indigo' => '#4152d6', 'violet' => '#8a4fd8', 'coral' => '#e2574c', 'amber' => '#e39b16', 'emerald' => '#18a06a', 'sky' => '#0284c7', 'rose' => '#d63b7e', 'lime' => '#5f9e12', 'orange' => '#e06a1b', 'plum' => '#9c3f8f', 'slate' => '#4b6584' );
			return isset( $m[ $c ] ) ? $m[ $c ] : '';
		}
		function jdr_login_url( $back = '' ) {
			if ( ! $back ) { $back = home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); }
			if ( function_exists( 'um_get_core_page' ) ) { $u = um_get_core_page( 'login' ); if ( $u ) { return add_query_arg( 'redirect_to', rawurlencode( $back ), $u ); } } /* Ultimate Member */
			return wp_login_url( $back );
		}
		function jdr_progress_html( $id, $jeu ) {
			$jeu = jdr_loc( jdr_defaults( $jeu ) ); $n = count( (array) $jeu['contenu'] );
			if ( ! is_user_logged_in() ) {
				if ( jdr_visiteurs( $id ) ) { return '<div class="jdr-prog jdr-prog--anon"><p>' . esc_html( jdr_t( 'Jouez sans compte : votre progression reste dans ce navigateur. Connectez-vous pour l’enregistrer sur votre compte et participer au classement.' ) ) . ' <a href="' . esc_url( jdr_login_url() ) . '">' . esc_html( jdr_t( 'Se connecter' ) ) . '</a></p></div>'; }
				return '<div class="jdr-prog jdr-prog--anon"><p>' . esc_html( jdr_t( 'Ce jeu est réservé aux membres du site. Connectez-vous : votre progression et votre score sont enregistrés sur votre compte.' ) ) . '</p></div>';
			}
			$d = jdr_user_data( $id ); $s = jdr_summary( $d );
			if ( ! $s && $d && isset( $d['state']['xp'] ) ) { /* jeu sans résumé (ancien moteur) : score seul */
				return '<div class="jdr-prog"><div class="jdr-prog__stats"><span><b>' . esc_html( number_format_i18n( (int) $d['state']['xp'] ) ) . '</b> XP</span></div></div>';
			}
			if ( ! $s ) {
				return '<div class="jdr-prog jdr-prog--new"><div class="jdr-prog__head"><span>' . esc_html( jdr_t( 'Votre progression' ) ) . '</span><b>0 %</b></div><div class="jdr-prog__bar"><i style="width:0%"></i></div><p class="jdr-prog__hint">' . esc_html( sprintf( jdr_t( 'Pas encore commencé · %d objectifs à découvrir' ), $n ) ) . '</p></div>';
			}
			$segs = '';
			foreach ( $s['obj'] as $o ) {
				$hex = jdr_cap_hex( $o['col'] ); $label = trim( $o['code'] . ' ' . $o['t'] ) . ' : ' . $o['m'] . ' %';
				$segs .= '<span class="jdr-seg' . ( $o['m'] >= 80 ? ' is-done' : '' ) . '" title="' . esc_attr( $label ) . '"' . ( $hex ? ' style="--jdr-seg:' . esc_attr( $hex ) . '"' : '' ) . '><i style="height:' . (int) $o['m'] . '%"></i><span class="screen-reader-text">' . esc_html( $label ) . '</span></span>';
			}
			$tot = $s['obj'] ? count( $s['obj'] ) : $n;
			$stats = '<span title="' . esc_attr( jdr_t( 'Points d’expérience' ) ) . '"><b>' . esc_html( number_format_i18n( $s['xp'] ) ) . '</b> XP</span>'
				. '<span title="' . esc_attr( jdr_t( 'Niveau' ) ) . '"><b>' . esc_html( $s['level'] ) . '</b> · ' . esc_html( jdr_t( 'niv.' ) ) . ' ' . (int) $s['lv'] . '/' . (int) $s['lvs'] . '</span>'
				. '<span title="' . esc_attr( jdr_t( 'Objectifs maîtrisés (80 % ou plus)' ) ) . '"><b>' . (int) $s['done'] . '/' . (int) $tot . '</b> ' . esc_html( jdr_t( 'objectifs maîtrisés' ) ) . '</span>'
				. ( $s['best'] > 0 ? '<span title="' . esc_attr( jdr_t( 'Meilleure note à l’examen blanc' ) ) . '"><b>' . esc_html( number_format_i18n( $s['best'], 1 ) ) . '</b>/6 ' . esc_html( jdr_t( 'à l’examen' ) ) . '</span>' : '' );
			return '<div class="jdr-prog"><div class="jdr-prog__head"><span>' . esc_html( jdr_t( 'Votre progression' ) ) . '</span><b>' . (int) $s['global'] . ' %</b></div>'
				. '<div class="jdr-prog__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) $s['global'] . '" aria-label="' . esc_attr( jdr_t( 'Maîtrise globale' ) ) . '"><i style="width:' . (int) $s['global'] . '%"></i></div>'
				. ( $segs ? '<div class="jdr-prog__segs" aria-label="' . esc_attr( jdr_t( 'Maîtrise par objectif' ) ) . '">' . $segs . '</div>' : '' )
				. '<div class="jdr-prog__stats">' . $stats . '</div></div>';
		}

		/* 'dossier' : une URL = PDF du dossier de formation (sous le bouton) ; un simple texte = nom du dossier de l'école (sous le titre, comme 1.6.2) */
		function jdr_est_url( $v ) { return is_string( $v ) && (bool) preg_match( '#^(https?:)?//|^/#i', trim( $v ) ); }
		/* dossier de formation livré avec le jeu (clés 'dossier' et 'corrige' : URL des PDF), affiché sous le bouton */
		function jdr_dossier_html( $id, $jeu, $liens = null ) {
			$jeu = jdr_defaults( $jeu );
			if ( null === $liens ) { $liens = array( 'dossier' => jdr_est_url( $jeu['dossier'] ) ? $jeu['dossier'] : '', 'corrige' => $jeu['corrige'] ); }
			if ( empty( $liens['dossier'] ) && empty( $liens['corrige'] ) ) { return ''; }
			$h = '<div class="jdr-doc"><span class="jdr-doc__ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>'
				. '<span class="jdr-doc__t"><b>' . esc_html( jdr_t( 'Dossier de formation' ) ) . '</b><small>' . esc_html( jdr_t( 'Notions clés et exercices sur papier · PDF' ) ) . '</small></span><span class="jdr-doc__a">';
			if ( ! empty( $liens['dossier'] ) ) { $h .= '<a class="jdr-doc__btn" href="' . esc_url( $liens['dossier'] ) . '" download>' . esc_html( jdr_t( 'Télécharger' ) ) . '</a>'; }
			if ( ! empty( $liens['corrige'] ) ) { $h .= '<a class="jdr-doc__btn jdr-doc__btn--alt" href="' . esc_url( $liens['corrige'] ) . '" download>' . esc_html( jdr_t( 'Corrigé' ) ) . '</a>'; }
			return $h . '</span></div>';
		}
		function jdr_card( $id, $jeu ) {
			$jeu = jdr_loc( jdr_defaults( $jeu ) ); $in = is_user_logged_in();
			$c = sanitize_hex_color( $jeu['couleur'] ); $c = $c ? $c : '#1f4e8c';
			$btn = jdr_started( $id ) ? $jeu['bouton_suite'] : $jeu['bouton']; $btn = $btn ? $btn : jdr_t( '▶ Jouer' );
			$acc = $in ? jdr_acces( $id ) : true;
			$etat = ''; if ( $in ) { $su = jdr_summary( jdr_user_data( $id ) ); if ( $su && ( $su['xp'] > 0 || $su['global'] > 0 ) ) { $etat = $su['global'] >= 80 ? ' is-mastered' : ' is-started'; } }
			$extra = $in ? (string) apply_filters( 'jdr_carte_extra', '', $id, get_current_user_id() ) : '';
			ob_start(); ?>
			<article class="jdr-card<?php echo esc_attr( $etat ); echo true !== $acc ? ' jdr-card--locked' : ''; ?>" style="--jdr-accent:<?php echo esc_attr( $c ); ?>" data-jdr-id="<?php echo esc_attr( $id ); ?>">
				<div class="jdr-card__logo"><img src="<?php echo esc_url( $jeu['logo'] ); ?>" alt="" width="96" height="96" loading="lazy"></div>
				<div class="jdr-card__body">
					<?php if ( $jeu['sous_titre'] ) : ?><p class="jdr-card__kicker"><?php echo esc_html( $jeu['sous_titre'] ); ?></p><?php endif; ?>
					<h3 class="jdr-card__title"><?php echo esc_html( $jeu['titre'] ); ?><?php if ( $jeu['dossier'] && ! jdr_est_url( $jeu['dossier'] ) ) : ?><span class="jdr-card__dossier"><?php echo esc_html( jdr_t( 'Dossier : ' ) . $jeu['dossier'] ); ?></span><?php endif; ?></h3>
					<?php if ( $jeu['duree'] ) : ?><p class="jdr-card__meta"><span><?php echo esc_html( $jeu['duree'] ); ?></span></p><?php endif; ?>
					<?php echo $extra; /* HTML fourni par l'extension de gestion, déjà échappé */ ?>
					<div class="jdr-card__progress" data-jdr-prog="<?php echo esc_attr( $id ); ?>"><?php echo jdr_progress_html( $id, $jeu ); /* déjà échappé */ ?></div>
					<?php if ( $jeu['contenu'] ) : ?><details class="jdr-card__content"><summary class="jdr-obj-btn"><?php echo esc_html( sprintf( jdr_t( 'Objectifs (%d)' ), count( $jeu['contenu'] ) ) ); ?></summary><ul class="jdr-obj"><?php foreach ( (array) $jeu['contenu'] as $l ) : $t = is_array( $l ) ? (string) ( $l['titre'] ?? '' ) : (string) $l; $sub = is_array( $l ) ? array_filter( (array) ( $l['objectifs'] ?? array() ) ) : array(); ?><li><?php echo esc_html( $t ); ?></li><?php endforeach; ?></ul></details><?php endif; ?>
					<?php if ( $in && true !== $acc ) : ?>
					<p class="jdr-locked" role="note"><span aria-hidden="true">🔒</span> <?php echo esc_html( $acc ); ?></p>
					<?php elseif ( $in || jdr_visiteurs( $id ) ) : ?>
					<button type="button" class="jdr-play" data-jdr-id="<?php echo esc_attr( $id ); ?>" data-jdr-src="<?php echo esc_url( jdr_game_url( $id, jdr_lang() ) ); ?>" data-jdr-lang="<?php echo esc_attr( jdr_lang() ); ?>" data-jdr-title="<?php echo esc_attr( $jeu['titre'] ); ?>"><?php echo esc_html( $btn ); ?></button>
					<?php else : ?>
					<a class="jdr-play jdr-play--login" href="<?php echo esc_url( jdr_login_url() ); ?>"><?php echo esc_html( $jeu['bouton_connexion'] ); ?></a>
					<?php endif; ?>
					<?php echo $in ? (string) apply_filters( 'jdr_carte_pied', true === $acc ? jdr_dossier_html( $id, $jeu ) : '', $id, get_current_user_id(), true === $acc ) : ''; /* sous le bouton : dossier de formation (et ressources de l'extension de gestion), déjà échappé */ ?>
				</div>
			</article>
			<?php return ob_get_clean();
		}

		/* ---------- REST ---------- */
		function jdr_register_rest() {
			register_rest_route( 'jdr/v1', '/progression/(?P<jeu>[a-z0-9_-]+)', array(
				array( 'methods' => 'GET', 'callback' => 'jdr_rest_get', 'permission_callback' => 'is_user_logged_in' ),
				array( 'methods' => 'POST', 'callback' => 'jdr_rest_set', 'permission_callback' => 'is_user_logged_in' ),
			) );
			register_rest_route( 'jdr/v1', '/bilan/(?P<jeu>[a-z0-9_-]+)', array(
				array( 'methods' => 'POST', 'callback' => 'jdr_rest_bilan', 'permission_callback' => 'is_user_logged_in' ),
			) );
			register_rest_route( 'jdr/v1', '/carte/(?P<jeu>[a-z0-9_-]+)', array(
				array( 'methods' => 'GET', 'callback' => 'jdr_rest_card', 'permission_callback' => 'is_user_logged_in' ),
			) );
		}
		function jdr_rest_jeu( $r ) { $j = sanitize_key( $r['jeu'] ); if ( ! isset( jdr_jeux()[ $j ] ) ) { return new WP_Error( 'jdr_jeu', 'Jeu inconnu.', array( 'status' => 404 ) ); } return $j; }
		function jdr_rest_get( WP_REST_Request $r ) {
			$j = jdr_rest_jeu( $r ); if ( is_wp_error( $j ) ) { return $j; } $a = jdr_rest_acces( $j ); if ( is_wp_error( $a ) ) { return $a; }
			$res = rest_ensure_response( array( 'data' => jdr_user_data_obj( $j ) ) ); $res->header( 'Cache-Control', 'no-store' ); return $res;
		}
		function jdr_rest_set( WP_REST_Request $r ) {
			$j = jdr_rest_jeu( $r ); if ( is_wp_error( $j ) ) { return $j; } $a = jdr_rest_acces( $j ); if ( is_wp_error( $a ) ) { return $a; }
			$raw = json_decode( (string) $r->get_body() ); /* objets conservés */
			if ( ! is_object( $raw ) || ! isset( $raw->data ) || ! is_object( $raw->data ) || ! isset( $raw->data->state ) || ! is_object( $raw->data->state ) ) { return new WP_Error( 'jdr_data', 'Données invalides.', array( 'status' => 400 ) ); }
			$json = wp_json_encode( $raw->data ); if ( ! $json || strlen( $json ) > 262144 ) { return new WP_Error( 'jdr_size', 'Progression trop volumineuse.', array( 'status' => 413 ) ); }
			$d = json_decode( $json, true ); $uid = get_current_user_id(); $cur = jdr_user_data( $j, $uid );
			/* un appareil en retard (XP inférieure) n'écrase jamais une progression plus avancée : le jeu reprend celle du site.
			   Après une remise à zéro par l'administration (marque « raz »), un appareil qui ne l'a pas encore vue est aussi refusé. */
			$raz = $cur ? (string) ( $cur['state']['raz'] ?? '' ) : '';
			if ( $cur && ( (int) ( $cur['state']['xp'] ?? 0 ) > (int) ( $d['state']['xp'] ?? 0 ) || ( '' !== $raz && (string) ( $d['state']['raz'] ?? '' ) !== $raz ) ) ) {
				return new WP_REST_Response( array( 'code' => 'jdr_conflict', 'data' => jdr_user_data_obj( $j, $uid ) ), 409 );
			}
			if ( ! jdr_write( $uid, $j, $d, $json ) ) { return new WP_Error( 'jdr_db', 'Enregistrement impossible.', array( 'status' => 500 ) ); }
			return rest_ensure_response( array( 'ok' => true ) );
		}
		/* ---------- bilan de session : enregistré en base, jamais envoyé par e-mail ---------- */
		function jdr_mail_on() { return false; }
		function jdr_txt( $v, $max = 600 ) { $v = is_scalar( $v ) ? wp_strip_all_tags( (string) $v ) : ''; return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max ); }
		function jdr_rest_bilan( WP_REST_Request $r ) {
			$j = jdr_rest_jeu( $r ); if ( is_wp_error( $j ) ) { return $j; } $a = jdr_rest_acces( $j ); if ( is_wp_error( $a ) ) { return $a; }
			$b = json_decode( (string) $r->get_body(), true );
			if ( ! is_array( $b ) || empty( $b['items'] ) || ! is_array( $b['items'] ) || strlen( (string) $r->get_body() ) > 400000 ) { return new WP_Error( 'jdr_data', 'Bilan invalide.', array( 'status' => 400 ) ); }
			$sid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $b['id'] ?? '' ) ) ); $sid = substr( $sid, 0, 40 );
			if ( '' === $sid ) { return new WP_Error( 'jdr_data', 'Bilan invalide.', array( 'status' => 400 ) ); }
			$items = array();
			foreach ( array_slice( $b['items'], 0, 200 ) as $it ) {
				if ( ! is_array( $it ) ) { continue; }
				$items[] = array( 'obj' => jdr_txt( $it['obj'] ?? '', 190 ), 'k' => jdr_txt( $it['k'] ?? '', 60 ), 's' => jdr_txt( $it['s'] ?? '', 1200 ), 'q' => jdr_txt( $it['q'] ?? '', 800 ),
					'good' => ! empty( $it['good'] ), 'given' => jdr_txt( $it['given'] ?? '', 800 ), 'answer' => jdr_txt( $it['answer'] ?? '', 1200 ), 'ex' => jdr_txt( $it['ex'] ?? '', 1200 ), 'ref' => jdr_txt( $it['ref'] ?? '', 160 ) );
			}
			if ( ! $items ) { return new WP_Error( 'jdr_data', 'Bilan vide.', array( 'status' => 400 ) ); }
			$mode = in_array( $b['mode'] ?? '', array( 'train', 'review', 'exam' ), true ) ? $b['mode'] : 'train';
			$ok = 0; foreach ( $items as $it ) { $ok += $it['good'] ? 1 : 0; }
			$s = array( 'mode' => $mode, 'titre' => jdr_txt( $b['title'] ?? '', 190 ), 'bonnes' => $ok, 'total' => count( $items ), 'note' => isset( $b['note'] ) && is_numeric( $b['note'] ) ? max( 0, min( 6, (float) $b['note'] ) ) : 0,
				'xp' => max( 0, (int) ( $b['xp'] ?? 0 ) ), 'duree' => max( 0, min( 86400, (int) ( $b['secs'] ?? 0 ) ) ), 'niveau' => jdr_txt( $b['level'] ?? '', 60 ), 'global' => max( 0, min( 100, (int) ( $b['global'] ?? 0 ) ) ) );
			global $wpdb; $uid = get_current_user_id(); $t = jdr_table_sess();
			$dup = $wpdb->get_row( $wpdb->prepare( "SELECT id, envoye FROM $t WHERE user_id = %d AND jeu = %s AND sid = %s", $uid, $j, $sid ) );
						if ( $dup ) { return rest_ensure_response( array( 'ok' => true, 'saved' => true, 'doublon' => true ) ); }
			$wpdb->insert( $t, array( 'user_id' => $uid, 'jeu' => $j, 'sid' => $sid, 'mode' => $mode, 'titre' => $s['titre'], 'bonnes' => $ok, 'total' => $s['total'], 'note' => $s['note'], 'xp' => $s['xp'], 'duree' => $s['duree'],
				'detail' => wp_json_encode( $items ), 'envoye' => 0, 'cree_le' => current_time( 'mysql', true ) ) );
			$id = (int) $wpdb->insert_id;
			do_action( 'jdr_session_terminee', $uid, $j, $s, $items );
			return rest_ensure_response( array( 'ok' => true, 'saved' => true ) );
		}
		function jdr_bilan_html( $u, $j, $jeu, $s, $items ) {
			$c = sanitize_hex_color( $jeu['couleur'] ); $c = $c ? $c : '#1f4e8c'; $e = 'esc_html';
			$name = jdr_prenom( $u ); $bad = array(); $good = array();
			foreach ( $items as $it ) { if ( $it['good'] ) { $good[] = $it; } else { $bad[] = $it; } }
			$modes = array( 'train' => 'Entraînement', 'review' => 'Révision ciblée', 'exam' => 'Examen blanc' );
			$dur = $s['duree'] >= 60 ? floor( $s['duree'] / 60 ) . ' min ' . str_pad( (string) ( $s['duree'] % 60 ), 2, '0', STR_PAD_LEFT ) . ' s' : $s['duree'] . ' s';
			$q = function ( $it, $n, $ok ) use ( $e ) {
				$h = '<tr><td style="padding:14px 16px;border-top:1px solid #e5e7eb">';
				$h .= '<div style="font-size:12px;color:#6b7280;margin-bottom:4px">' . (int) $n . ' · ' . $e( $it['obj'] ) . ( $it['k'] ? ' · ' . $e( $it['k'] ) : '' ) . '</div>';
				if ( $it['s'] ) { $h .= '<div style="font-size:14px;color:#374151;background:#f3f4f6;border-radius:6px;padding:8px 10px;margin:4px 0 6px">' . $e( $it['s'] ) . '</div>'; }
				$h .= '<div style="font-size:15px;font-weight:600;color:#111827;margin-bottom:8px">' . $e( str_replace( '___', '…', $it['q'] ) ) . '</div>';
				if ( $ok ) {
					$h .= '<div style="font-size:14px;color:#065f46">✓ Votre réponse : <strong>' . $e( $it['given'] ? $it['given'] : $it['answer'] ) . '</strong></div>';
				} else {
					$h .= '<div style="font-size:14px;color:#991b1b;margin-bottom:6px">✘ Votre réponse : <span style="text-decoration:line-through">' . $e( $it['given'] ? $it['given'] : '(aucune)' ) . '</span></div>';
					$h .= '<div style="font-size:15px;color:#065f46;background:#d1fae5;border-left:4px solid #059669;border-radius:4px;padding:8px 10px">✓ Bonne réponse : <strong>' . $e( $it['answer'] ) . '</strong></div>';
					if ( $it['ex'] ) { $h .= '<div style="font-size:13px;color:#4b5563;margin-top:6px">' . $e( $it['ex'] ) . ( $it['ref'] ? ' <em>(' . $e( $it['ref'] ) . ')</em>' : '' ) . '</div>'; }
				}
				return $h . '</td></tr>';
			};
			$sec = function ( $title, $col, $bg, $list, $ok ) use ( $q, $e ) {
				if ( ! $list ) { return ''; } $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 0;border:1px solid #e5e7eb;border-radius:8px;border-collapse:separate;overflow:hidden">';
				$h .= '<tr><td style="background:' . $bg . ';color:' . $col . ';font-weight:700;font-size:16px;padding:12px 16px">' . $e( $title ) . ' (' . count( $list ) . ')</td></tr>';
				$n = 0; foreach ( $list as $it ) { $h .= $q( $it, ++$n, $ok ); } return $h . '</table>';
			};
			$kpi = '<strong style="font-size:22px">' . (int) $s['bonnes'] . ' / ' . (int) $s['total'] . '</strong> bonnes réponses';
			if ( 'exam' === $s['mode'] ) { $kpi .= ' · note <strong style="font-size:22px">' . $e( number_format_i18n( $s['note'], 1 ) ) . '</strong> / 6'; }
			$h = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head><body style="margin:0;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;color:#111827">';
			$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:20px 10px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#fff;border-radius:10px;overflow:hidden">';
			$h .= '<tr><td style="background:' . $c . ';color:#fff;padding:20px 24px"><div style="font-size:13px;opacity:.9">' . $e( $jeu['titre'] ) . ' · ' . $e( $modes[ $s['mode'] ] ) . '</div><div style="font-size:22px;font-weight:700;margin-top:4px">' . $e( $s['titre'] ? $s['titre'] : 'Bilan de session' ) . '</div></td></tr>';
			$h .= '<tr><td style="padding:20px 24px"><p style="margin:0 0 12px">Bonjour ' . $e( $name ) . ',</p><p style="margin:0 0 14px">Voici le bilan de la session que vous venez de terminer, le ' . $e( wp_date( 'j F Y' ) . ' à ' . wp_date( 'H:i' ) ) . '.</p>';
			$h .= '<div style="background:#f3f4f6;border-radius:8px;padding:14px 16px">' . $kpi . '<div style="font-size:13px;color:#4b5563;margin-top:6px">Durée : ' . $e( $dur ) . ' · +' . (int) $s['xp'] . ' XP' . ( $s['niveau'] ? ' · niveau ' . $e( $s['niveau'] ) : '' ) . ' · maîtrise globale ' . (int) $s['global'] . ' %</div></div>';
			$h .= $sec( 'À retravailler', '#991b1b', '#fee2e2', $bad, false ) . $sec( 'Réussies', '#065f46', '#d1fae5', $good, true );
			if ( $bad ) { $h .= '<p style="margin:20px 0 0;font-size:14px">Les épreuves ratées reviennent dans la <strong>Révision ciblée</strong> du jeu jusqu’à ce qu’elles soient réussies deux fois de suite.</p>'; }
			$h .= '<p style="margin:20px 0 0"><a href="' . esc_url( jdr_game_url( $j ) ) . '" style="display:inline-block;background:' . $c . ';color:#fff;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:6px">Reprendre le jeu</a></p>';
			$h .= '</td></tr><tr><td style="padding:14px 24px;background:#f9fafb;font-size:12px;color:#6b7280">E-mail envoyé automatiquement par ' . $e( get_bloginfo( 'name' ) ) . ' à la fin de chaque session de jeu.</td></tr></table></td></tr></table></body></html>';
			return apply_filters( 'jdr_bilan_html', $h, $u, $j, $s, $items );
		}
		function jdr_save_settings() {
			if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.', 403 ); }
			check_admin_referer( 'jdr_reglages' );
			update_option( 'jdr_effacer_donnees', empty( $_POST['jdr_effacer_donnees'] ) ? '0' : '1', false );
			wp_safe_redirect( admin_url( 'tools.php?page=jeux-revision&reglages=1' ) ); exit;
		}
		function jdr_rest_card( WP_REST_Request $r ) {
			$j = jdr_rest_jeu( $r ); if ( is_wp_error( $j ) ) { return $j; }
			if ( ! empty( $r['lang'] ) ) { jdr_lang( sanitize_key( $r['lang'] ) ); }
			$jeu = jdr_loc( jdr_defaults( jdr_jeux()[ $j ] ) );
			return rest_ensure_response( array( 'html' => jdr_progress_html( $j, $jeu ), 'bouton' => jdr_started( $j ) ? $jeu['bouton_suite'] : $jeu['bouton'] ) );
		}

		/* ---------- administration : jeux installés et suivi des participants ---------- */
		function jdr_cap() { return apply_filters( 'jdr_capacite_suivi', 'edit_others_posts' ); }
		function jdr_admin_menu() { add_management_page( 'LogiPlay – Suivi des élèves', 'LogiPlay', 'edit_posts', 'jeux-revision', 'jdr_admin_page' ); }
		function jdr_rows_for( $jeu ) {
			global $wpdb;
			return $wpdb->get_results( $wpdb->prepare( 'SELECT p.user_id, p.xp, p.maitrise, p.objectifs, p.meilleure_note, p.sauvegardes, p.cree_le, p.maj_le, u.display_name, u.user_login, u.user_email, (SELECT COUNT(*) FROM ' . jdr_table_sess() . ' s WHERE s.user_id = p.user_id AND s.jeu = p.jeu) AS sessions FROM ' . jdr_table() . " p LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id WHERE p.jeu = %s ORDER BY p.maj_le DESC LIMIT 2000", $jeu ) );
		}
		function jdr_admin_page() {
			$jeux = jdr_jeux();
			echo '<div class="wrap"><h1>Jeux de révision</h1><p>Collez un shortcode dans une page (bloc « Code court »). Le jeu est réservé aux utilisateurs connectés et s’ouvre en plein écran ; la progression est enregistrée dans la base de données du site, sur le compte de chaque participant.</p><p><code>[jeux_revision]</code> affiche tous les jeux installés.</p><table class="widefat striped"><thead><tr><th>Jeu</th><th>Shortcode</th><th>Participants</th><th>Version</th></tr></thead><tbody>';
			global $wpdb;
			foreach ( $jeux as $id => $j ) {
				$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jdr_table() . ' WHERE jeu = %s', $id ) );
				printf( '<tr><td><strong>%1$s</strong><br>%2$s</td><td><code>[jeu_revision id="%3$s"]</code></td><td>%5$d</td><td>%4$s</td></tr>', esc_html( $j['titre'] ?? $id ), esc_html( $j['sous_titre'] ?? '' ), esc_attr( $id ), esc_html( $j['version'] ?? '' ), $n );
			}
			echo '</tbody></table><p>Moteur commun : ' . esc_html( JDR_ENGINE_VERSION ) . '</p>';
			if ( current_user_can( 'manage_options' ) ) {
				if ( isset( $_GET['reglages'] ) ) { echo '<div class="notice notice-success"><p>Réglage enregistré.</p></div>'; }
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="jdr_reglages">' . wp_nonce_field( 'jdr_reglages', '_wpnonce', true, false )
					. ''
					. '<br><label><input type="checkbox" name="jdr_effacer_donnees" value="1"' . checked( jdr_effacer_donnees_ok(), true, false ) . '> Effacer les progressions d’un jeu quand son extension est <strong>supprimée</strong> (déconseillé : laissé décoché, les données restent dans la base, même après une suppression, une mise à jour ou une réinstallation).</label>'
					. '<br><button class="button">Enregistrer</button></form>';
				echo jdr_backup_box(); // phpcs:ignore
			}
			if ( current_user_can( jdr_cap() ) && $jeux ) {
				$sel = isset( $_GET['jeu'] ) ? sanitize_key( wp_unslash( $_GET['jeu'] ) ) : array_key_first( $jeux ); if ( ! isset( $jeux[ $sel ] ) ) { $sel = array_key_first( $jeux ); }
				echo '<h2 id="suivi">Suivi des participants</h2><form method="get"><input type="hidden" name="page" value="jeux-revision"><label for="jdr-jeu">Jeu : </label><select id="jdr-jeu" name="jeu">';
				foreach ( $jeux as $id => $j ) { printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $id ), esc_html( $j['titre'] ?? $id ), selected( $sel, $id, false ) ); }
				echo '</select> <button class="button">Afficher</button> <a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jdr_export&jeu=' . $sel ), 'jdr_export' ) ) . '">Exporter en CSV</a></form>';
				$rows = jdr_rows_for( $sel );
				echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>Participant</th><th>Identifiant</th><th>XP</th><th>Maîtrise</th><th>Objectifs maîtrisés</th><th>Meilleure note</th><th>Sessions</th><th>Dernière activité</th></tr></thead><tbody>';
				if ( ! $rows ) { echo '<tr><td colspan="8">Aucune progression enregistrée pour ce jeu.</td></tr>'; }
				$tot = count( (array) ( $jeux[ $sel ]['contenu'] ?? array() ) );
				foreach ( (array) $rows as $p ) {
					printf( '<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$d %%</td><td>%5$d / %6$d</td><td>%7$s</td><td>%9$d</td><td>%8$s</td></tr>', esc_html( $p->display_name ? $p->display_name : '#' . $p->user_id ), esc_html( (string) $p->user_login ), esc_html( number_format_i18n( (int) $p->xp ) ), (int) $p->maitrise, (int) $p->objectifs, $tot, $p->meilleure_note > 0 ? esc_html( number_format_i18n( (float) $p->meilleure_note, 1 ) ) : '–', esc_html( get_date_from_gmt( $p->maj_le, 'd.m.Y H:i' ) ), (int) $p->sessions );
				}
				echo '</tbody></table>';
			}
			echo '</div>';
		}
		/* ---------- sauvegarde complète et restauration ---------- */
		function jdr_backup_box() {
			global $wpdb; $n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . jdr_table() ); $msg = '';
			if ( isset( $_GET['restaure'] ) ) { $msg = '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Restauration terminée : %1$d progression(s) restaurée(s), %2$d gardée(s) parce que plus avancée(s) sur le site, %3$d ignorée(s) (compte introuvable).', (int) $_GET['restaure'], (int) ( $_GET['garde'] ?? 0 ), (int) ( $_GET['sans'] ?? 0 ) ) ) . '</p></div>'; }
			if ( isset( $_GET['restauration_erreur'] ) ) { $msg = '<div class="notice notice-error"><p>Fichier de sauvegarde illisible : choisissez un fichier .json téléchargé depuis cette page.</p></div>'; }
			return $msg . '<h2 id="sauvegarde">Sauvegarde des données</h2><p>' . esc_html( sprintf( '%d progression(s) enregistrée(s).', $n ) ) . ' Les progressions restent dans la base du site quand vous désactivez, mettez à jour, supprimez ou réinstallez un jeu. Chaque jour d’activité, une copie complète de chaque progression est aussi gardée (30 derniers jours). Téléchargez régulièrement une sauvegarde complète (progressions, historique, bilans de session) : elle permet de tout restaurer sur ce site ou sur un nouveau site.</p>'
				. '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jdr_sauvegarde' ), 'jdr_sauvegarde' ) ) . '">Télécharger la sauvegarde complète</a></p>'
				. '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="jdr_restauration">' . wp_nonce_field( 'jdr_restauration', '_wpnonce', true, false )
				. '<label for="jdr-fichier">Restaurer une sauvegarde : </label><input id="jdr-fichier" type="file" name="fichier" accept=".json,application/json" required> '
				. '<label><input type="checkbox" name="forcer" value="1"> Remplacer aussi les progressions plus avancées sur le site</label> <button class="button">Restaurer</button></form>'
				. '<p class="description">Les participants sont retrouvés par leur identifiant de connexion, à défaut par leur adresse e-mail : sur un nouveau site, créez leurs comptes avant de restaurer.</p>';
		}
		function jdr_backup_data() {
			global $wpdb; $out = array( 'format' => 'jeux-revision-sauvegarde', 'version' => 1, 'moteur' => JDR_ENGINE_VERSION, 'site' => home_url( '/' ), 'date' => gmdate( 'c' ), 'utilisateurs' => array(), 'progression' => array(), 'historique' => array(), 'sessions' => array() );
			$ids = array();
			foreach ( array( 'progression' => jdr_table(), 'historique' => jdr_table_hist(), 'sessions' => jdr_table_sess() ) as $k => $t ) {
				$off = 0;
				do {
					$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id LIMIT %d, 500", $off ), ARRAY_A );
					foreach ( $rows as $r ) { unset( $r['id'] ); $out[ $k ][] = $r; $ids[ (int) $r['user_id'] ] = 1; }
					$off += 500;
				} while ( 500 === count( $rows ) );
			}
			foreach ( array_keys( $ids ) as $uid ) { $u = get_userdata( $uid ); $out['utilisateurs'][ (string) $uid ] = $u ? array( 'login' => $u->user_login, 'email' => $u->user_email, 'nom' => $u->display_name ) : null; }
			return $out;
		}
		function jdr_backup_download() {
			if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.', 403 ); }
			check_admin_referer( 'jdr_sauvegarde' );
			nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="jeux-revision-sauvegarde-' . gmdate( 'Y-m-d' ) . '.json"' );
			echo wp_json_encode( jdr_backup_data() ); exit;
		}
		/* restaure un tableau de sauvegarde ; renvoie array( restaurées, gardées, sans compte ) */
		function jdr_backup_apply( $b, $forcer = false ) {
			global $wpdb; jdr_maybe_install_db(); $map = array(); $ok = 0; $garde = 0; $sans = 0;
			foreach ( (array) ( $b['utilisateurs'] ?? array() ) as $old => $u ) {
				if ( ! is_array( $u ) ) { continue; }
				$cur = get_user_by( 'login', (string) ( $u['login'] ?? '' ) );
				if ( ! $cur && ! empty( $u['email'] ) ) { $cur = get_user_by( 'email', (string) $u['email'] ); }
				if ( $cur ) { $map[ (int) $old ] = (int) $cur->ID; }
			}
			foreach ( (array) ( $b['progression'] ?? array() ) as $r ) {
				$uid = $map[ (int) ( $r['user_id'] ?? 0 ) ] ?? 0; $jeu = sanitize_key( $r['jeu'] ?? '' );
				if ( ! $uid ) { $sans++; continue; }
				$d = json_decode( (string) ( $r['data'] ?? '' ), true ); if ( ! $jeu || ! is_array( $d ) ) { continue; }
				$row = jdr_row( $uid, $jeu );
				if ( $row && ! $forcer && (int) $row->xp > (int) ( $r['xp'] ?? 0 ) ) { $garde++; continue; }
				if ( jdr_write( $uid, $jeu, $d, (string) $r['data'] ) ) { $ok++; }
			}
			$h = jdr_table_hist(); $se = jdr_table_sess();
			foreach ( (array) ( $b['historique'] ?? array() ) as $r ) {
				$uid = $map[ (int) ( $r['user_id'] ?? 0 ) ] ?? 0; $jeu = sanitize_key( $r['jeu'] ?? '' ); $jour = (string) ( $r['jour'] ?? '' );
				if ( ! $uid || ! $jeu || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $jour ) ) { continue; }
				if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $h WHERE user_id = %d AND jeu = %s AND jour = %s", $uid, $jeu, $jour ) ) ) { continue; }
				$wpdb->insert( $h, array( 'user_id' => $uid, 'jeu' => $jeu, 'jour' => $jour, 'xp' => (int) ( $r['xp'] ?? 0 ), 'maitrise' => (int) ( $r['maitrise'] ?? 0 ), 'objectifs' => (int) ( $r['objectifs'] ?? 0 ), 'meilleure_note' => (float) ( $r['meilleure_note'] ?? 0 ), 'data' => isset( $r['data'] ) ? (string) $r['data'] : null ) );
			}
			foreach ( (array) ( $b['sessions'] ?? array() ) as $r ) {
				$uid = $map[ (int) ( $r['user_id'] ?? 0 ) ] ?? 0; $jeu = sanitize_key( $r['jeu'] ?? '' ); $sid = substr( (string) ( $r['sid'] ?? '' ), 0, 40 );
				if ( ! $uid || ! $jeu || '' === $sid ) { continue; }
				if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $se WHERE user_id = %d AND jeu = %s AND sid = %s", $uid, $jeu, $sid ) ) ) { continue; }
				$wpdb->insert( $se, array( 'user_id' => $uid, 'jeu' => $jeu, 'sid' => $sid, 'mode' => (string) ( $r['mode'] ?? '' ), 'titre' => (string) ( $r['titre'] ?? '' ), 'bonnes' => (int) ( $r['bonnes'] ?? 0 ), 'total' => (int) ( $r['total'] ?? 0 ), 'note' => (float) ( $r['note'] ?? 0 ), 'xp' => (int) ( $r['xp'] ?? 0 ), 'duree' => (int) ( $r['duree'] ?? 0 ), 'detail' => (string) ( $r['detail'] ?? '' ), 'envoye' => 1, 'cree_le' => (string) ( $r['cree_le'] ?? current_time( 'mysql', true ) ) ) );
			}
			return array( $ok, $garde, $sans );
		}
		function jdr_backup_restore() {
			if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.', 403 ); }
			check_admin_referer( 'jdr_restauration' );
			$back = admin_url( 'tools.php?page=jeux-revision' );
			$f = isset( $_FILES['fichier']['tmp_name'] ) ? (string) $_FILES['fichier']['tmp_name'] : ''; // phpcs:ignore
			$b = ( '' !== $f && is_uploaded_file( $f ) ) ? json_decode( (string) file_get_contents( $f ), true ) : null; // phpcs:ignore
			if ( ! is_array( $b ) || ( $b['format'] ?? '' ) !== 'jeux-revision-sauvegarde' ) { wp_safe_redirect( add_query_arg( 'restauration_erreur', 1, $back ) . '#sauvegarde' ); exit; }
			list( $ok, $garde, $sans ) = jdr_backup_apply( $b, ! empty( $_POST['forcer'] ) );
			wp_safe_redirect( add_query_arg( array( 'restaure' => $ok, 'garde' => $garde, 'sans' => $sans ), $back ) . '#sauvegarde' ); exit;
		}
		/* copies complètes quotidiennes d'un participant pour un jeu (jours disponibles, du plus récent au plus ancien) */
		function jdr_jours_copies( $uid, $jeu ) {
			global $wpdb; $h = jdr_table_hist();
			return (array) $wpdb->get_col( $wpdb->prepare( "SELECT jour FROM $h WHERE user_id = %d AND jeu = %s AND data IS NOT NULL ORDER BY jour DESC", (int) $uid, $jeu ) );
		}
		/* remet la progression d'un participant dans l'état enregistré à la fin d'un jour donné */
		function jdr_restaurer_jour( $uid, $jeu, $jour ) {
			global $wpdb; $h = jdr_table_hist();
			$data = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM $h WHERE user_id = %d AND jeu = %s AND jour = %s AND data IS NOT NULL", (int) $uid, $jeu, $jour ) );
			$d = $data ? json_decode( $data, true ) : null; if ( ! is_array( $d ) ) { return false; }
			$row = jdr_row( (int) $uid, $jeu ); /* l'état actuel est gardé pour pouvoir annuler */
			if ( $row ) { update_user_meta( (int) $uid, 'jdr_avant_restauration_' . $jeu, array( 'le' => current_time( 'mysql', true ), 'data' => $row->data ) ); }
			return jdr_write( (int) $uid, $jeu, $d, $data );
		}
		function jdr_annuler_restauration( $uid, $jeu ) {
			$m = get_user_meta( (int) $uid, 'jdr_avant_restauration_' . $jeu, true );
			$d = is_array( $m ) && ! empty( $m['data'] ) ? json_decode( $m['data'], true ) : null; if ( ! is_array( $d ) ) { return false; }
			delete_user_meta( (int) $uid, 'jdr_avant_restauration_' . $jeu );
			return jdr_write( (int) $uid, $jeu, $d, $m['data'] );
		}
		function jdr_export_csv() {
			if ( ! current_user_can( jdr_cap() ) ) { wp_die( 'Accès refusé.', 403 ); }
			check_admin_referer( 'jdr_export' );
			$jeu = isset( $_GET['jeu'] ) ? sanitize_key( wp_unslash( $_GET['jeu'] ) ) : ''; $jeux = jdr_jeux();
			if ( ! isset( $jeux[ $jeu ] ) ) { wp_die( 'Jeu inconnu.', 404 ); }
			nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="progression-' . $jeu . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
			$out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" );
			fputcsv( $out, array( 'Participant', 'Identifiant', 'E-mail', 'XP', 'Maîtrise (%)', 'Objectifs maîtrisés', 'Meilleure note', 'Sauvegardes', 'Sessions terminées', 'Premier jeu', 'Dernière activité' ), ';' );
			foreach ( (array) jdr_rows_for( $jeu ) as $p ) {
				fputcsv( $out, array( $p->display_name, $p->user_login, $p->user_email, (int) $p->xp, (int) $p->maitrise, (int) $p->objectifs, $p->meilleure_note > 0 ? str_replace( '.', ',', (string) $p->meilleure_note ) : '', (int) $p->sauvegardes, (int) $p->sessions, get_date_from_gmt( $p->cree_le, 'd.m.Y H:i' ), get_date_from_gmt( $p->maj_le, 'd.m.Y H:i' ) ), ';' );
			}
			fclose( $out ); exit;
		}
		jdr_boot();
	}
}
