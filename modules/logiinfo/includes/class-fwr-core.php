<?php
/** Fonctions communes : base de données, stockage protégé, réglages, analyse, téléchargement. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LIR_Core {
	const DB_VERSION = '7';
	const STATUTS = array( 'depose' => 'À corriger', 'corrige' => 'Corrigé (non publié)', 'publie' => 'Retour publié', 'corbeille' => 'Corbeille' );

	/** Tables propres à LogiInfo. Elles ne sont jamais supprimées : ni à la désactivation, ni à la mise à jour, ni à la suppression de l'extension. */
	public static function table() { global $wpdb; return $wpdb->prefix . 'logiinfo_remises'; }
	public static function progress_table() { global $wpdb; return $wpdb->prefix . 'logiinfo_progression'; }
	public static function eleves_table() { global $wpdb; return $wpdb->prefix . 'logiinfo_eleves'; }
	public static function journal_table() { global $wpdb; return $wpdb->prefix . 'logiinfo_journal'; }
	/** Trace les opérations importantes (dépôt, correction, publication, corbeille, restauration, sauvegarde). */
	public static function log( $action, $remise_id = 0, $detail = '' ) {
		global $wpdb;
		$wpdb->insert( self::journal_table(), array( 'quand' => current_time( 'mysql' ), 'user_id' => get_current_user_id(), 'action' => substr( $action, 0, 40 ), 'remise_id' => (int) $remise_id, 'detail' => (string) $detail ) );
	}

	private static $lecons = null;
	public static function reset_lecons() { self::$lecons = null; }
	public static function lecons() {
		$l = &self::$lecons;
		if ( null === $l ) {
			$l = array();
			foreach ( (array) include LIR_DIR . 'includes/lecons.php' as $n => $x ) { $l[ $n ] = $x + array( 'type' => 'docx', 'formation' => 'word', 'num' => $n ); }
			foreach ( (array) include LIR_DIR . 'includes/lecons-excel.php' as $n => $x ) { $l[ $n ] = $x + array( 'types' => array( 'xlsx' ) ); }
			foreach ( (array) include LIR_DIR . 'includes/lecons-powerpoint.php' as $n => $x ) { $l[ $n ] = $x + array( 'types' => array( 'pptx' ) ); }
			foreach ( (array) include LIR_DIR . 'includes/lecons-presentation.php' as $n => $x ) { $l[ $n ] = $x + array( 'types' => array( 'pptx' ) ); }
			foreach ( $l as $n => $x ) { if ( empty( $x['types'] ) ) { $l[ $n ]['types'] = array( $x['type'] ); } }
			$l += LI_Exercices::as_lecons();
		}
		return $l;
	}
	/** Clé interne d'un exercice : leçons Word 1 à 15, Excel 101 à 115, PowerPoint 201 à 215, guide « Réussir ses présentations » 301 et suivantes. */
	public static function key( $num, $formation = 'word' ) { return ( array( 'excel' => 100, 'powerpoint' => 200, 'presentation' => 300 )[ $formation ] ?? 0 ) + (int) $num; }
	public static function lecon( $n ) { $l = self::lecons(); return isset( $l[ (int) $n ] ) ? $l[ (int) $n ] : null; }
	public static function is_xlsx( $n ) { $l = self::lecon( $n ); return $l && 'xlsx' === $l['type']; }
	public static function is_pptx( $n ) { $l = self::lecon( $n ); return $l && 'pptx' === $l['type']; }
	/** Exercice Excel ou PowerPoint : contrôle par critères (data/*-checks.json), sans comparaison Word. */
	public static function is_checked( $n ) { return self::is_xlsx( $n ) || self::is_pptx( $n ); }
	/** Lecteur de fichier selon l'extension (traçabilité et résumé de la structure). */
	public static function reader( $path ) {
		$x = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'xlsx' === $x ) { return new LIR_Xlsx( $path ); }
		if ( 'pptx' === $x ) { return new LIR_Pptx( $path ); }
		return new LIR_Docx( $path );
	}
	/** Nom du dossier des fichiers de la formation (uploads/…). */
	public static function folder( $n ) {
		$l = self::lecon( $n );
		return array( 'excel' => 'Excel2026', 'powerpoint' => 'PowerPoint2026', 'presentation' => 'Presentation2026' )[ $l['formation'] ?? 'word' ] ?? 'Word2026';
	}
	public static function is_libre( $n ) { $l = self::lecon( $n ); return $l && 'libre' === $l['type']; }
	/** « L03 » (Word), « XL03 » (Excel) ou « EX001 » (exercice ajouté dans un autre cours). */
	public static function code( $n ) {
		$l = self::lecon( $n );
		if ( $l && 'libre' === $l['type'] ) { return 'EX' . sprintf( '%03d', $l['num'] ); }
		return ( $l ? ( array( 'excel' => 'XL', 'powerpoint' => 'PP', 'presentation' => 'PR' )[ $l['formation'] ] ?? 'L' ) : 'L' ) . sprintf( '%02d', $l ? $l['num'] : $n );
	}
	public static function lecon_label( $n ) {
		$l = self::lecon( $n );
		if ( $l && 'libre' === $l['type'] ) { return self::code( $n ) . ' – ' . ( $l['titre'] !== $l['exercice'] ? $l['titre'] . ' · ' : '' ) . $l['exercice']; }
		return $l ? sprintf( '%s %s – %s', array( 'excel' => 'Excel', 'powerpoint' => 'PowerPoint', 'presentation' => 'Présentations' )[ $l['formation'] ] ?? 'Word', sprintf( 'L%02d', $l['num'] ), $l['titre'] ) : 'L' . (int) $n;
	}

	public static function settings() {
		$d = array( 'max_mo' => 15, 'echelle' => 'suisse', 'detail_defaut' => 1, 'message' => 'Merci ! Votre exercice a bien été déposé. Votre formateur vous fera un retour prochainement.' );
		return wp_parse_args( (array) get_option( 'fwr_settings', array() ), $d );
	}

	/* ---------------------------------------------------------------- installation */
	public static function activate() {
		self::install_db();
		self::storage_dir();
		self::protect_reference();
		self::caps();
	}
	/** À chaque nouvelle version de l'extension : tables complétées (jamais vidées), protections réécrites, données reprises. */
	public static function maybe_upgrade() {
		if ( get_option( 'logiinfo_db_version' ) === self::DB_VERSION && get_option( 'logiinfo_version' ) === LI_VERSION ) { return; }
		self::install_db();
		self::migrate();
		self::storage_dir();
		self::protect_reference();
		self::caps();
		update_option( 'logiinfo_version', LI_VERSION, false );
	}

	/** Seuls les administrateurs corrigent dans LogiInfo (les autres formateurs reçoivent leurs copies par e-mail). */
	public static function caps() {
		$admin = get_role( 'administrator' );
		if ( $admin ) { $admin->add_cap( LIR_CAP ); $admin->add_cap( LI_ADMIN_CAP ); }
		foreach ( wp_roles()->roles as $slug => $r ) {
			if ( 'administrator' === $slug ) { continue; }
			$role = get_role( $slug );
			if ( $role ) { $role->remove_cap( LIR_CAP ); $role->remove_cap( LI_ADMIN_CAP ); }
		}
		// droit de correction individuel : seulement les formateurs à qui l'administrateur l'a donné
		$allowed = array();
		foreach ( LI_Formateurs::all() as $f ) { if ( ! empty( $f['acces'] ) && ! empty( $f['actif'] ) && $f['user_id'] ) { $allowed[ (int) $f['user_id'] ] = true; } }
		foreach ( get_users( array( 'fields' => 'all' ) ) as $u ) {
			if ( in_array( 'administrator', (array) $u->roles, true ) ) { continue; }
			if ( isset( $allowed[ $u->ID ] ) ) { $u->add_cap( LIR_CAP ); }
			elseif ( isset( $u->caps[ LIR_CAP ] ) ) { $u->remove_cap( LIR_CAP ); }
			if ( isset( $u->caps[ LI_ADMIN_CAP ] ) ) { $u->remove_cap( LI_ADMIN_CAP ); }
		}
	}

	/** Reprise des données des anciennes extensions (copie : les anciennes données ne sont pas effacées). */
	private static function migrate() {
		global $wpdb;
		$old = $wpdb->prefix . 'fwr_remises';
		if ( ! get_option( 'logiinfo_migre_remises' ) && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) ) === $old ) {
			if ( ! (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ) ) {
				$cols = array_intersect( $wpdb->get_col( "SHOW COLUMNS FROM $old" ), $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::table() ) );
				$cl = '`' . implode( '`,`', $cols ) . '`';
				$wpdb->query( 'INSERT INTO ' . self::table() . " ($cl) SELECT $cl FROM $old" ); // phpcs:ignore
			}
			update_option( 'logiinfo_migre_remises', current_time( 'mysql' ), false );
		}
		if ( ! get_option( 'logiinfo_migre_progression' ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", 'lpex_steps' ) );
			foreach ( $rows as $r ) {
				$time = (array) get_user_meta( $r->user_id, 'lpex_time', true );
				foreach ( (array) maybe_unserialize( $r->meta_value ) as $key => $steps ) {
					if ( ! preg_match( '/^(word|excel|powerpoint|presentation)-\d+$/', (string) $key ) ) { continue; }
					self::save_progress( (int) $r->user_id, $key, (array) $steps, isset( $time[ $key ] ) ? (int) $time[ $key ] : time() );
				}
			}
			update_option( 'logiinfo_migre_progression', current_time( 'mysql' ), false );
		}
	}

	/** Progression d'un élève dans un exercice (« word-3 », « excel-12 »…). */
	public static function save_progress( $user_id, $key, $steps, $ts = null ) {
		global $wpdb;
		$steps = array_values( array_unique( array_map( 'intval', $steps ) ) ); sort( $steps );
		$wpdb->replace( self::progress_table(), array( 'user_id' => (int) $user_id, 'cle' => $key, 'etapes' => implode( ',', $steps ), 'maj' => gmdate( 'Y-m-d H:i:s', $ts ? $ts : time() ) ) );
	}
	/** [ cle => [ 'steps' => int[], 'ts' => timestamp ] ] pour un élève, ou pour tous si $user_id = 0 ([ user_id => [...] ]). */
	public static function get_progress( $user_id = 0 ) {
		global $wpdb;
		$t = self::progress_table();
		$rows = $user_id ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d", $user_id ) ) : $wpdb->get_results( "SELECT * FROM $t" ); // phpcs:ignore
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! preg_match( '/^[a-z]+-\d+$/', $r->cle ) ) { continue; }
			$v = array( 'steps' => '' === $r->etapes ? array() : array_map( 'intval', explode( ',', $r->etapes ) ), 'ts' => strtotime( $r->maj . ' UTC' ) );
			if ( $user_id ) { $out[ $r->cle ] = $v; } else { $out[ (int) $r->user_id ][ $r->cle ] = $v; }
		}
		return $out;
	}

	private static function install_db() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		$c = $wpdb->get_charset_collate();
		$p = self::progress_table();
		dbDelta( "CREATE TABLE $p (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			cle varchar(20) NOT NULL,
			etapes varchar(255) NOT NULL DEFAULT '',
			maj datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_cle (user_id,cle)
		) $c;" );
		LI_Exercices::install( $c );
		$e = self::eleves_table();
		dbDelta( "CREATE TABLE $e (
			user_id bigint(20) unsigned NOT NULL,
			formateur_id int(10) unsigned NOT NULL DEFAULT 0,
			maj datetime NOT NULL,
			PRIMARY KEY  (user_id),
			KEY formateur (formateur_id)
		) $c;" );
		$j = self::journal_table();
		dbDelta( "CREATE TABLE $j (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			quand datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL,
			remise_id bigint(20) unsigned NOT NULL DEFAULT 0,
			detail text NULL,
			PRIMARY KEY  (id),
			KEY remise (remise_id)
		) $c;" );
		dbDelta( "CREATE TABLE $t (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			lecon int(10) unsigned NOT NULL,
			version int(10) unsigned NOT NULL DEFAULT 1,
			files longtext NOT NULL,
			created_at datetime NOT NULL,
			auto_score decimal(6,2) NULL,
			auto_max decimal(6,2) NULL,
			auto_report longtext NULL,
			note varchar(20) NOT NULL DEFAULT '',
			commentaire longtext NULL,
			detail tinyint(1) NOT NULL DEFAULT 1,
			statut varchar(20) NOT NULL DEFAULT 'depose',
			corrige_par bigint(20) unsigned NULL,
			corrige_at datetime NULL,
			publie_at datetime NULL,
			ia_note varchar(20) NULL,
			ia_commentaire longtext NULL,
			ia_details longtext NULL,
			ia_at datetime NULL,
			ia_export_at datetime NULL,
			validation varchar(10) NOT NULL DEFAULT '',
			motif text NULL,
			formateur_id int(10) unsigned NOT NULL DEFAULT 0,
			groupe_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY groupe (groupe_id),
			KEY formateur (formateur_id),
			KEY user_lecon (user_id, lecon),
			KEY statut (statut)
		) $c;" );
		update_option( 'logiinfo_db_version', self::DB_VERSION, false );
	}

	/** Dossier de stockage protégé contre l'accès direct. */
	public static function storage_dir() {
		$up = wp_upload_dir();
		$base = trailingslashit( $up['basedir'] );
		$dir = $base . 'logiinfo/copies-eleves';
		// reprise de l'ancien dossier de l'extension « Formation Word – Remise des exercices » (déplacé, jamais effacé)
		if ( ! is_dir( $dir ) && is_dir( $base . 'fw-remises' ) ) { wp_mkdir_p( $base . 'logiinfo' ); @rename( $base . 'fw-remises', $dir ); }
		if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
		foreach ( array( $base . 'logiinfo', $dir ) as $d ) {
			if ( ! file_exists( $d . '/index.php' ) ) { @file_put_contents( $d . '/index.php', "<?php // Silence.\n" ); }
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "# Accès direct interdit\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) { file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); }
		if ( ! file_exists( $dir . '/web.config' ) ) {
			file_put_contents( $dir . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' );
		}
		return $dir;
	}
	/** Les corrigés livrés avec l'extension ne doivent pas être accessibles aux apprentis. */
	public static function protect_reference() {
		$d = LIR_DIR . 'reference';
		if ( is_dir( $d ) && is_writable( $d ) ) {
			@file_put_contents( $d . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
			@file_put_contents( $d . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' );
			if ( ! file_exists( $d . '/index.php' ) ) { @file_put_contents( $d . '/index.php', "<?php // Silence.\n" ); }
		}
	}

	/** Résultats attendus d'une leçon : liste de [libellé, docx, pdf]. */
	public static function references( $lecon ) {
		$n = sprintf( 'L%02d', $lecon );
		$out = array( array( 'label' => 14 === (int) $lecon ? 'Lettre type attendue' : 'Résultat attendu', 'base' => $n . '_resultat_attendu' ) );
		if ( 14 === (int) $lecon ) { $out[] = array( 'label' => 'Lettres fusionnées attendues', 'base' => 'L14_lettres_fusionnees' ); }
		foreach ( $out as $k => $r ) {
			$out[ $k ]['docx'] = file_exists( LIR_DIR . 'reference/' . $r['base'] . '.docx' ) ? LIR_DIR . 'reference/' . $r['base'] . '.docx' : '';
			$out[ $k ]['pdf']  = file_exists( LIR_DIR . 'reference/' . $r['base'] . '.pdf' ) ? LIR_DIR . 'reference/' . $r['base'] . '.pdf' : '';
		}
		return $out;
	}
	public static function ref_url( $lecon, $k, $type ) {
		return add_query_arg( array( 'action' => 'fwr_ref', 'lecon' => (int) $lecon, 'k' => (int) $k, 'type' => sanitize_key( $type ), '_wpnonce' => wp_create_nonce( 'fwr_ref' ) ), admin_url( 'admin-post.php' ) );
	}
	public static function handle_ref() {
		check_admin_referer( 'fwr_ref' );
		if ( ! current_user_can( LIR_CAP ) ) { wp_die( 'Accès refusé.' ); }
		$refs = self::references( (int) ( $_GET['lecon'] ?? 0 ) );
		$k = (int) ( $_GET['k'] ?? 0 );
		$type = 'pdf' === ( $_GET['type'] ?? '' ) ? 'pdf' : 'docx';
		$path = isset( $refs[ $k ] ) ? $refs[ $k ][ $type ] : '';
		if ( ! $path ) { wp_die( 'Résultat attendu introuvable.' ); }
		nocache_headers();
		header( 'Content-Type: ' . ( 'pdf' === $type ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) );
		header( 'Content-Disposition: ' . ( 'pdf' === $type ? 'inline' : 'attachment' ) . '; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore
		exit;
	}

	/** Objets que l'aperçu dans le navigateur ne dessine pas (graphiques, SmartArt, zones de texte). */
	public static function objets_non_affiches( $path ) {
		$d = new LIR_Docx( $path );
		if ( ! $d->ok ) { return array(); }
		$out = array();
		$types = array( 'barChart' => 'Histogramme', 'bar3DChart' => 'Histogramme 3D', 'pieChart' => 'Secteurs', 'pie3DChart' => 'Secteurs 3D', 'doughnutChart' => 'Anneau', 'lineChart' => 'Courbes', 'areaChart' => 'Aires' );
		foreach ( $d->d['charts'] as $c ) {
			$t = isset( $types[ $c['type'] ] ) ? $types[ $c['type'] ] : 'Graphique';
			$out[] = '📊 ' . $t . ( $c['title'] ? ' « ' . $c['title'] . ' »' : ' (sans titre)' ) . ' – ' . $c['series'] . ' série(s), ' . $c['points'] . ' valeur(s)' . ( $c['labels'] ? ', étiquettes de données' : '' );
		}
		foreach ( $d->d['smartart'] as $s ) { $out[] = '🔷 SmartArt : ' . rtrim( trim( $s['text'] ), ' |' ); }
		foreach ( $d->d['textboxes'] as $t ) { $out[] = '🔲 Zone de texte : « ' . mb_substr( $t['text'], 0, 80 ) . ' »' . ( $t['fill'] ? ' (remplissage coloré)' : '' ); }
		return $out;
	}

	public static function abs_path( $stored ) { return self::storage_dir() . '/' . ltrim( $stored, '/' ); }

	/* ---------------------------------------------------------------- données */
	public static function get( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
		return $r ? self::hydrate( $r ) : null;
	}
	public static function hydrate( $r ) {
		$r['files'] = json_decode( $r['files'], true ) ?: array();
		$r['auto_report'] = $r['auto_report'] ? json_decode( $r['auto_report'], true ) : null;
		$r['ia_details'] = ! empty( $r['ia_details'] ) ? json_decode( $r['ia_details'], true ) : array();
		foreach ( array( 'ia_note', 'ia_commentaire', 'ia_at', 'ia_export_at', 'motif' ) as $k ) { if ( ! isset( $r[ $k ] ) ) { $r[ $k ] = null; } }
		if ( ! isset( $r['validation'] ) ) { $r['validation'] = ''; }
		return $r;
	}
	public static function for_user( $user_id, $lecon = null ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table() . " WHERE user_id = %d AND statut <> 'corbeille'";
		$args = array( $user_id );
		if ( $lecon ) { $sql .= ' AND lecon = %d'; $args[] = $lecon; }
		$sql .= ' ORDER BY lecon ASC, version DESC';
		return array_map( array( __CLASS__, 'hydrate' ), $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) );
	}
	public static function next_version( $user_id, $lecon ) {
		global $wpdb;
		return 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . self::table() . ' WHERE user_id = %d AND lecon = %d', $user_id, $lecon ) );
	}

	/** Points à vérifier d'un exercice Excel (déduits du fichier de départ et du résultat attendu). */
	public static function excel_checks( $num ) {
		static $all = null;
		if ( null === $all ) { $all = (array) json_decode( (string) file_get_contents( LI_DIR . '/data/excel-checks.json' ), true ); }
		return $all[ (int) $num ] ?? array();
	}
	/** Points à vérifier d'un exercice .pptx (cours PowerPoint ou guide « Réussir ses présentations »). */
	public static function pptx_checks( $lecon ) {
		static $all = array();
		$L = self::lecon( $lecon );
		if ( ! $L ) { return array(); }
		$f = 'presentation' === $L['formation'] ? 'presentation' : 'powerpoint';
		if ( ! isset( $all[ $f ] ) ) { $all[ $f ] = (array) json_decode( (string) file_get_contents( LI_DIR . '/data/' . $f . '-checks.json' ), true ); }
		return $all[ $f ][ (int) $L['num'] ] ?? array();
	}
	/** Critères du contrôle automatique d'une leçon : [ [libellé, points], … ]. */
	public static function criteria( $lecon ) {
		if ( self::is_checked( $lecon ) ) {
			$num = self::lecon( $lecon )['num'];
			$c = self::is_pptx( $lecon ) ? self::pptx_checks( $lecon ) : self::excel_checks( $num );
			return array_map( function ( $x ) { return array( $x['label'] ?? '', $x['pts'] ?? 0 ); }, $c );
		}
		return array_map( function ( $r ) { return array( $r[0], $r[1] ); }, LIR_Rules::get( $lecon ) );
	}

	/** Analyse les fichiers d'une remise et renvoie [rapport, structures]. */
	public static function analyse( $lecon, $files ) {
		$docs = array(); $errors = array(); $trace = array();
		foreach ( $files as $f ) {
			$x = strtolower( pathinfo( $f['stored'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $x, array( 'docx', 'xlsx', 'pptx' ), true ) ) { continue; } // PDF, images… : pas d'analyse
			$d = self::reader( self::abs_path( $f['stored'] ) );
			if ( $d->ok ) { $docs[] = $d->d; $trace[] = array( 'file' => $f['name'] ) + $d->d['trace']; } else { $errors[] = $f['name'] . ' : ' . $d->error; }
		}
		if ( self::is_xlsx( $lecon ) ) {
			$rep = array( 'items' => array(), 'score' => 0, 'max' => 0, 'pct' => 0 );
			$checks = self::excel_checks( self::lecon( $lecon )['num'] );
			foreach ( $files as $f ) {
				$m = new LI_Xlsx_Model( self::abs_path( $f['stored'] ) );
				if ( $m->ok && $checks ) { $rep = LI_Xlsx_Check::evaluate( $checks, $m ); break; }
			}
		} elseif ( self::is_pptx( $lecon ) ) {
			$rep = array( 'items' => array(), 'score' => 0, 'max' => 0, 'pct' => 0 );
			$checks = self::pptx_checks( $lecon );
			foreach ( $files as $f ) {
				if ( 'pptx' !== strtolower( pathinfo( $f['stored'], PATHINFO_EXTENSION ) ) ) { continue; }
				$m = new LI_Pptx_Model( self::abs_path( $f['stored'] ) );
				if ( $m->ok && $checks ) { $rep = LI_Pptx_Check::evaluate( $checks, $m ); break; }
			}
		} elseif ( self::is_libre( $lecon ) ) {
			$rep = array( 'items' => array(), 'score' => 0, 'max' => 0, 'pct' => 0 ); // exercice libre : correction par le formateur
		} else {
			$rep = LIR_Rules::evaluate( $lecon, $docs );
		}
		$rep['trace'] = $trace;
		$rep['errors'] = $errors;
		$rep['date'] = current_time( 'mysql' );
		return $rep;
	}
	/** Alertes de traçabilité d'une remise (suivi des modifications du fichier de départ). */
	public static function trace_flags( $r ) {
		$rep = $r['auto_report'];
		if ( ! is_array( $rep ) || ! isset( $rep['trace'] ) ) { return null; } // remise analysée avant la version 1.2 : relancer le contrôle
		$flags = array();
		foreach ( $rep['trace'] as $t ) {
			$pre = count( $rep['trace'] ) > 1 ? $t['file'] . ' : ' : '';
			if ( isset( $t['kind'] ) && in_array( $t['kind'], array( 'xlsx', 'pptx' ), true ) ) {
				$obj = 'pptx' === $t['kind'] ? 'présentation' : 'classeur';
				if ( $t['creator'] && ! $t['depart'] ) { $flags[] = $pre . $obj . ' créé(e) par « ' . $t['creator'] . ' » : il ne provient pas du fichier de départ'; }
				if ( ! $t['last_by'] ) { $flags[] = $pre . 'aucun nom d\'auteur enregistré dans le ' . ( 'pptx' === $t['kind'] ? 'fichier' : 'classeur' ); }
				continue;
			}
			if ( 'verrouillee' !== $t['protection'] ) { $flags[] = $pre . ( 'absente' === $t['protection'] ? 'protection du suivi retirée' : 'protection modifiée (autre mot de passe)' ); }
			if ( ! $t['tracking'] ) { $flags[] = $pre . 'suivi des modifications désactivé'; }
			if ( ! $t['n'] ) { $flags[] = $pre . 'aucune modification enregistrée (fichier de départ non utilisé ?)'; }
			if ( count( $t['authors'] ) > 1 ) { $flags[] = $pre . 'plusieurs auteurs : ' . implode( ', ', array_keys( $t['authors'] ) ); }
		}
		return $flags;
	}
	public static function authors( $r ) {
		$a = array();
		foreach ( (array) ( $r['auto_report']['trace'] ?? array() ) as $t ) { foreach ( $t['authors'] as $k => $n ) { $a[ $k ] = ( $a[ $k ] ?? 0 ) + $n; } }
		return $a;
	}
	/** Auteurs de cette remise que l'on retrouve dans les remises d'autres apprentis pour la même leçon. */
	public static function shared_authors( $r ) {
		global $wpdb;
		$mine = array_keys( self::authors( $r ) );
		if ( ! $mine ) { return array(); }
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE lecon = %d AND user_id <> %d', $r['lecon'], $r['user_id'] ), ARRAY_A );
		$out = array();
		foreach ( $rows as $o ) {
			$o = self::hydrate( $o );
			foreach ( array_intersect( $mine, array_keys( self::authors( $o ) ) ) as $who ) { $out[ $who ][ (int) $o['user_id'] ] = self::user_label( $o['user_id'] ); }
		}
		return $out;
	}
	/* ---------------------------------------------------------------- décision et vérification de l'auteur */
	const DECISIONS = array( 'valide' => 'Validé', 'invalide' => 'Invalidé' );
	/** Motifs proposés pour invalider (le formateur peut toujours écrire le sien). */
	public static function motifs() {
		return array(
			'Le fichier n\'a pas été réalisé sur votre compte : le nom de l\'auteur ne correspond pas.',
			'Ce fichier est identique à celui d\'un autre élève.',
			'Le fichier ne provient pas du fichier de départ fourni.',
			'Le suivi des modifications a été retiré ou désactivé.',
			'Ce n\'est pas le bon fichier ou l\'exercice est incomplet.',
		);
	}
	private static function name_tokens( $s ) {
		$s = strtolower( remove_accents( (string) $s ) );
		return array_values( array_filter( preg_split( '/[^a-z0-9]+/', $s ), function ( $t ) { return strlen( $t ) >= 2; } ) );
	}
	/** Noms Office sans valeur pour identifier quelqu'un (poste partagé, réglage par défaut). */
	private static function generic_name( $name ) {
		static $g = array( 'utilisateur', 'user', 'users', 'eleve', 'eleves', 'apprenti', 'apprentie', 'apprentis', 'etudiant', 'etudiante', 'student', 'admin', 'administrateur', 'administrator',
			'windows', 'microsoft', 'office', 'pc', 'poste', 'ecole', 'classe', 'salle', 'owner', 'proprietaire', 'invite', 'guest', 'compte', 'auteur', 'author', 'unknown', 'inconnu', 'logiprof', 'win', 'lenovo', 'dell', 'hp', 'asus', 'acer', 'mac', 'macbook', 'ipad', 'local' );
		$t = self::name_tokens( $name );
		if ( ! $t ) { return true; }
		foreach ( $t as $x ) { if ( ! in_array( $x, $g, true ) && ! ctype_digit( $x ) && strlen( $x ) >= 2 ) { return false; } }
		return true;
	}
	/** Noms enregistrés dans les fichiers déposés (auteurs des modifications, dernier enregistrement, créateur). */
	public static function file_names( $r ) {
		$n = array();
		foreach ( (array) ( $r['auto_report']['trace'] ?? array() ) as $t ) {
			foreach ( array_keys( (array) ( $t['authors'] ?? array() ) ) as $k ) { $n[ $k ] = 1; }
			if ( ! empty( $t['last_by'] ) ) { $n[ $t['last_by'] ] = 1; }
			if ( ! empty( $t['creator'] ) && empty( $t['depart'] ) ) { $n[ $t['creator'] ] = 1; }
		}
		unset( $n['LogiProf'] );
		return array_keys( $n );
	}
	/** Forme comparable d'un mot : minuscules, sans accents, « ue/oe/ae » ramenés à « u/o/a » (Müller = Mueller = Muller). */
	private static function norm_tok( $t ) { return strtr( str_replace( array( 'ae', 'oe', 'ue' ), array( 'a', 'o', 'u' ), $t ), array( 'y' => 'i' ) ); }
	/** Morceaux du nom du compte : prénom, nom, nom affiché (« name »), identifiant et e-mail (« login »). */
	public static function account_parts( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) { return array( 'name' => array(), 'login' => array(), 'label' => '' ); }
		$name = array();
		foreach ( array( get_user_meta( $user_id, 'first_name', true ), get_user_meta( $user_id, 'last_name', true ), $u->display_name, $u->nickname ) as $v ) {
			foreach ( self::name_tokens( $v ) as $t ) { if ( ! self::generic_name( $t ) || strlen( $t ) < 3 ) { $name[ self::norm_tok( $t ) ] = $t; } }
		}
		$login = array();
		foreach ( array( $u->user_login, strstr( (string) $u->user_email, '@', true ) ) as $v ) {
			$login[] = self::norm_tok( preg_replace( '/[0-9]+/', '', implode( '', self::name_tokens( $v ) ) ) );
			foreach ( self::name_tokens( $v ) as $t ) { if ( strlen( $t ) >= 3 && ! ctype_digit( $t ) ) { $name[ self::norm_tok( $t ) ] = $name[ self::norm_tok( $t ) ] ?? $t; } }
		}
		$has = (bool) array_filter( array( get_user_meta( $user_id, 'first_name', true ), get_user_meta( $user_id, 'last_name', true ) ), 'strlen' );
		return array( 'name' => $name, 'login' => array_filter( $login ), 'has_name' => $has, 'label' => trim( get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'last_name', true ) ) ?: $u->display_name );
	}
	/**
	 * Le nom d'auteur correspond-il au compte ? Tolère un compte incomplet (prénom seul ou nom seul), les initiales (« L. Martin »),
	 * les noms composés, les accents, ü/ue et une faute de frappe sur les noms longs.
	 * Retourne 'oui', 'partiel' (seule une initiale concorde) ou '' (aucun rapport).
	 */
	public static function name_match( $name, $user_id ) {
		$acc = self::account_parts( $user_id );
		if ( ! $acc['name'] && ! $acc['login'] ) { return ''; }
		$toks = array_map( array( __CLASS__, 'norm_tok' ), self::name_tokens( $name ) );
		$raw = strtolower( remove_accents( (string) $name ) );
		foreach ( $toks as $t ) {
			foreach ( array_keys( $acc['name'] ) as $a ) {
				if ( $t === $a ) { return 'oui'; }
				if ( strlen( $t ) >= 5 && strlen( $a ) >= 5 && levenshtein( $t, $a ) <= 1 ) { return 'oui'; } // Martins / Martin, faute de frappe
			}
		}
		$compact = self::norm_tok( implode( '', self::name_tokens( $name ) ) );
		foreach ( array_keys( $acc['name'] ) as $a ) { if ( strlen( $a ) >= 4 && false !== strpos( $compact, $a ) ) { return 'oui'; } } // « lmartin », « leamartin »
		foreach ( $acc['login'] as $l ) { if ( strlen( $compact ) >= 4 && ( false !== strpos( $l, $compact ) || ( strlen( $l ) >= 4 && false !== strpos( $compact, $l ) ) ) ) { return 'oui'; } }
		if ( ! $acc['has_name'] ) { // compte sans prénom ni nom : l'identifiant (« lmartin3 », « lea.martin ») est le seul indice
			foreach ( $toks as $t ) { foreach ( $acc['login'] as $l ) { if ( strlen( $t ) >= 4 && false !== strpos( $l, $t ) ) { return 'oui'; } } }
		}
		// initiales : « L. M. », « LM » ou « L. Dupont » face à un compte « Léa » : indice seulement
		preg_match_all( '/\b([a-z])\b/', $raw, $ini );
		$letters = $ini[1];
		if ( 1 === count( $toks ) && strlen( $toks[0] ) <= 3 ) { $letters = array_merge( $letters, str_split( $toks[0] ) ); }
		foreach ( $letters as $l ) { foreach ( array_keys( $acc['name'] ) as $a ) { if ( $a[0] === $l ) { return 'partiel'; } } }
		return '';
	}
	public static function name_matches( $name, $user_id ) { return 'oui' === self::name_match( $name, $user_id ); }
	/** Empreintes SHA-1 des fichiers (calculées une fois, puis gardées avec la remise). */
	public static function hashes( &$r ) {
		$changed = false; $out = array();
		foreach ( $r['files'] as $k => $f ) {
			if ( empty( $f['sha1'] ) ) {
				$p = self::abs_path( $f['stored'] );
				if ( ! file_exists( $p ) ) { continue; }
				$r['files'][ $k ]['sha1'] = sha1_file( $p ); $changed = true;
			}
			$out[] = $r['files'][ $k ]['sha1'];
		}
		if ( $changed ) { global $wpdb; $wpdb->update( self::table(), array( 'files' => wp_json_encode( $r['files'] ) ), array( 'id' => $r['id'] ) ); }
		return $out;
	}
	/**
	 * Qui a fait l'exercice ? Compare les noms enregistrés dans les fichiers au compte qui a déposé,
	 * et cherche un fichier identique (même empreinte) déposé par un autre compte.
	 * level : ok (cohérent) · info (impossible à vérifier : nom générique ou absent) · alerte (incohérence à vérifier).
	 */
	public static function identity( $r ) {
		static $cache = array();
		if ( isset( $cache[ $r['id'] ] ) ) { return $cache[ $r['id'] ]; }
		global $wpdb;
		$res = array( 'level' => 'ok', 'msgs' => array(), 'motif' => '' );
		$who = self::user_label( $r['user_id'] );
		$names = self::file_names( $r );
		$acc = self::account_parts( $r['user_id'] );
		$incomplet = count( array_filter( array( get_user_meta( $r['user_id'], 'first_name', true ), get_user_meta( $r['user_id'], 'last_name', true ) ), 'strlen' ) ) < 2;
		$sans_nom = ! array_filter( array( get_user_meta( $r['user_id'], 'first_name', true ), get_user_meta( $r['user_id'], 'last_name', true ) ), 'strlen' );
		$bad = array(); $generic = array(); $good = array(); $partial = array(); $unk = array();
		foreach ( $names as $n ) {
			if ( self::generic_name( $n ) ) { $generic[] = $n; continue; }
			$m = self::name_match( $n, $r['user_id'] );
			if ( 'oui' === $m ) { $good[] = $n; continue; }
			if ( $sans_nom ) { $unk[] = $n; continue; } // compte sans prénom ni nom : rien à comparer
			// compte incomplet (prénom seul ou nom seul) et un seul mot dans le fichier : c'est peut-être la partie qui manque au compte
			if ( 'partiel' === $m || ( $incomplet && 1 === count( self::name_tokens( $n ) ) ) ) { $partial[] = $n; } else { $bad[] = $n; }
		}
		if ( $bad ) {
			$res['level'] = 'alerte';
			$res['msgs'][] = sprintf( 'Le fichier a été modifié ou enregistré par « %s », un nom qui ne correspond pas au compte de %s.', implode( ' », « ', $bad ), $who );
			$res['motif'] = self::motifs()[0];
		}
		foreach ( self::shared_authors( $r ) as $n => $users ) {
			if ( self::generic_name( $n ) ) { continue; }
			$res['level'] = 'alerte';
			$res['msgs'][] = sprintf( 'Le nom « %s » apparaît aussi dans les fichiers de : %s.', $n, implode( ', ', $users ) );
		}
		$mine = self::hashes( $r );
		if ( $mine ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE lecon = %d AND user_id <> %d AND statut <> 'corbeille'", $r['lecon'], $r['user_id'] ), ARRAY_A );
			$twins = array();
			foreach ( $rows as $o ) { $o = self::hydrate( $o ); if ( array_intersect( $mine, self::hashes( $o ) ) ) { $twins[ (int) $o['user_id'] ] = self::user_label( $o['user_id'] ); } }
			if ( $twins ) {
				$res['level'] = 'alerte';
				$res['msgs'][] = 'Fichier strictement identique (au bit près) à celui déposé par : ' . implode( ', ', $twins ) . '.';
				$res['motif'] = self::motifs()[1];
			}
		}
		if ( 'ok' === $res['level'] ) {
			if ( ! $names ) {
				$res['level'] = 'info'; $res['msgs'][] = 'Aucun nom d\'auteur enregistré dans le fichier : impossible de vérifier qui l\'a réalisé.';
			} elseif ( $unk && ! $good ) {
				$res['level'] = 'info';
				$res['msgs'][] = sprintf( 'Le compte « %s » n\'a ni prénom ni nom : impossible de le comparer au nom enregistré dans le fichier (« %s »). Complétez le profil de l\'apprenti·e.', $acc['label'], implode( ' », « ', $unk ) );
			} elseif ( ! $good && ! $partial ) {
				$res['level'] = 'info'; $res['msgs'][] = sprintf( 'Nom d\'auteur générique (« %s ») : ordinateur partagé ou Office non personnalisé, impossible de vérifier.', implode( ' », « ', $generic ) );
			} elseif ( ! $good ) {
				$res['level'] = 'info';
				$res['msgs'][] = sprintf( 'Correspondance incertaine : « %s » dans le fichier, compte « %s » (initiales compatibles ou nom du compte incomplet). Vérifiez si besoin.', implode( ' », « ', $partial ), $acc['label'] );
			} else {
				$res['msgs'][] = sprintf( 'Le nom enregistré dans le fichier (« %s ») correspond au compte de %s.', implode( ' », « ', $good ), $who );
			}
			if ( $incomplet && $good ) {
				$res['msgs'][] = sprintf( 'Le compte n\'indique qu\'une partie du nom (« %s ») : la vérification s\'appuie sur ce seul nom. Complétez le prénom et le nom du compte pour une vérification plus sûre.', $acc['label'] );
			}
		}
		return $cache[ $r['id'] ] = $res;
	}
	public static function reanalyse( $id ) {
		global $wpdb;
		$r = self::get( $id );
		if ( ! $r ) { return; }
		$rep = self::analyse( $r['lecon'], $r['files'] );
		$wpdb->update( self::table(), array( 'auto_score' => $rep['score'], 'auto_max' => $rep['max'], 'auto_report' => wp_json_encode( $rep ) ), array( 'id' => $id ) );
	}

	/** Note proposée à partir du contrôle automatique selon l'échelle choisie. */
	public static function note_proposee( $score, $max ) {
		if ( ! $max ) { return ''; }
		$s = self::settings();
		$r = $score / $max;
		if ( 'vingt' === $s['echelle'] ) { return number_format_i18n( round( $r * 20 * 2 ) / 2, 1 ) . ' / 20'; }
		if ( 'pourcent' === $s['echelle'] ) { return round( $r * 100 ) . ' %'; }
		return number_format( round( ( 1 + 5 * $r ) * 2 ) / 2, 1, '.', '' );
	}
	public static function echelle_label() {
		$s = self::settings();
		return array( 'suisse' => 'note de 1 à 6 (demi-points, 4 = suffisant)', 'vingt' => 'note sur 20', 'pourcent' => 'pourcentage' )[ $s['echelle'] ];
	}

	public static function user_label( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) { return '#' . $user_id; }
		$n = trim( $u->first_name . ' ' . $u->last_name );
		return $n ? $n : $u->display_name;
	}

	/* ---------------------------------------------------------------- téléchargement sécurisé */
	public static function download_url( $id, $k ) {
		// Adresse brute (sans &amp;) : elle sert aussi au fetch de l'aperçu côte à côte ; les liens l'échappent à l'affichage.
		return add_query_arg( array( 'action' => 'fwr_dl', 'id' => (int) $id, 'f' => (int) $k, '_wpnonce' => wp_create_nonce( 'fwr_dl_' . (int) $id ) ), admin_url( 'admin-post.php' ) );
	}
	public static function handle_download() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$k  = isset( $_GET['f'] ) ? (int) $_GET['f'] : 0;
		check_admin_referer( 'fwr_dl_' . $id );
		$r = self::get( $id );
		if ( ! $r || ! isset( $r['files'][ $k ] ) ) { wp_die( 'Fichier introuvable.' ); }
		$own = (int) $r['user_id'] === get_current_user_id();
		if ( ! $own && ! ( current_user_can( LIR_CAP ) && LI_Formateurs::can_see( $r ) ) ) { wp_die( 'Accès refusé.' ); }
		$f = $r['files'][ $k ];
		$path = self::abs_path( $f['stored'] );
		if ( ! file_exists( $path ) ) { wp_die( 'Fichier introuvable sur le serveur.' ); }
		$name = sanitize_file_name( sprintf( '%s_%s_v%d_%s', self::code( $r['lecon'] ), self::user_label( $r['user_id'] ), $r['version'], $f['name'] ) );
		$ft = wp_check_filetype( $path );
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . ( $ft['type'] ? $ft['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore
		exit;
	}
}
add_action( 'admin_post_fwr_dl', array( 'LIR_Core', 'handle_download' ) );
add_action( 'admin_post_fwr_ref', array( 'LIR_Core', 'handle_ref' ) );
