<?php
/**
 * LogiInfo – page « Données et sauvegarde » : état des tables et des fichiers, test de protection,
 * sauvegarde complète téléchargeable et restauration sans écrasement, journal des opérations.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LI_Donnees {

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page( 'fw-remises', 'Données et sauvegarde', 'Données et sauvegarde', LI_ADMIN_CAP, 'logiinfo-donnees', array( __CLASS__, 'page' ) );
		}, 25 );
		add_action( 'admin_post_logiinfo_sauvegarde', array( __CLASS__, 'backup' ) );
		add_action( 'admin_post_logiinfo_restaurer', array( __CLASS__, 'restore' ) );
	}

	private static function tables() {
		return array( LIR_Core::table() => 'Remises des exercices', LIR_Core::progress_table() => 'Progression des élèves (étapes cochées)', LIR_Core::eleves_table() => 'Formateur de chaque élève', LI_Exercices::table() => 'Exercices ajoutés dans d\'autres cours', LIR_Core::journal_table() => 'Journal des opérations' );
	}

	/** Fichiers référencés par les remises : [présents, manquants[]] */
	private static function check_files() {
		global $wpdb;
		$ok = 0; $missing = array(); $known = array();
		foreach ( $wpdb->get_results( 'SELECT id, files FROM ' . LIR_Core::table(), ARRAY_A ) as $r ) {
			foreach ( (array) json_decode( $r['files'], true ) as $f ) {
				$known[ $f['stored'] ] = true;
				if ( file_exists( LIR_Core::abs_path( $f['stored'] ) ) ) { $ok++; } else { $missing[] = 'R' . $r['id'] . ' : ' . $f['name']; }
			}
		}
		return array( $ok, $missing, $known );
	}
	private static function disk() {
		$dir = LIR_Core::storage_dir(); $n = 0; $size = 0; $files = array();
		if ( is_dir( $dir ) ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( ! $f->isFile() || in_array( $f->getFilename(), array( 'index.php', '.htaccess', 'web.config', '_test-protection.txt' ), true ) ) { continue; }
				$n++; $size += $f->getSize();
				$files[] = ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( $dir ) ) ), '/' );
			}
		}
		return array( $n, $size, $files );
	}

	/** Les copies sont-elles accessibles directement depuis Internet ? (cas des serveurs Nginx qui ignorent .htaccess) */
	private static function probe() {
		$dir = LIR_Core::storage_dir();
		$tok = wp_generate_password( 20, false );
		@file_put_contents( $dir . '/_test-protection.txt', $tok );
		$up = wp_upload_dir();
		$url = trailingslashit( $up['baseurl'] ) . 'logiinfo/copies-eleves/_test-protection.txt';
		$r = wp_remote_get( $url, array( 'timeout' => 8, 'sslverify' => false, 'redirection' => 0 ) );
		@unlink( $dir . '/_test-protection.txt' );
		if ( is_wp_error( $r ) ) { return array( 'inconnu', $r->get_error_message() ); }
		return false !== strpos( (string) wp_remote_retrieve_body( $r ), $tok ) ? array( 'ouvert', $url ) : array( 'ferme', (int) wp_remote_retrieve_response_code( $r ) );
	}

	public static function page() {
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { return; }
		global $wpdb;
		echo '<div class="wrap fwr-wrap"><h1>LogiInfo – Données et sauvegarde</h1>';
		if ( isset( $_GET['li_msg'] ) ) { echo '<div class="notice notice-' . ( ! empty( $_GET['li_ok'] ) ? 'success' : 'error' ) . '"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['li_msg'] ) ) ) ) . '</p></div>'; } // phpcs:ignore
		echo '<style>.li-c{background:#fff;border:1px solid #d9ddd6;border-left:6px solid #067030;border-radius:6px;padding:14px 20px;margin:16px 0;max-width:1100px}.li-c h2{margin:0 0 10px;color:#313a45}.li-ok{color:#067030;font-weight:600}.li-ko{color:#b42318;font-weight:600}.li-c .button-primary{background:#067030;border-color:#067030}.li-c td,.li-c th{padding:6px 10px}</style>';

		echo '<div class="li-c"><h2>🛡️ Ce qui est garanti</h2><ul style="list-style:disc;margin-left:20px">'
			. '<li><b>Mise à jour de l\'extension</b> : les tables sont seulement complétées (jamais vidées ni recréées) et les fichiers des élèves sont rangés hors du dossier de l\'extension, dans <code>wp-content/uploads/logiinfo/copies-eleves/</code>. Une mise à jour ne touche à rien.</li>'
			. '<li><b>Désactivation ou suppression de l\'extension</b> : aucune donnée n\'est effacée (pas de nettoyage à la désinstallation). En la réinstallant, tout réapparaît.</li>'
			. '<li><b>Corbeille</b> : une remise « supprimée » va à la corbeille ; ses fichiers restent sur le serveur et elle peut être restaurée. Rien n\'est effacé définitivement depuis LogiInfo.</li>'
			. '<li><b>Leçons</b> : à la mise à jour, seules les leçons que vous n\'avez pas retouchées reçoivent le nouveau contenu.</li>'
			. '<li><b>Sécurité</b> : dépôt réservé aux élèves connectés, contrôle du type et du contenu réel des fichiers, correction réservée aux administrateurs, noms de fichiers aléatoires, téléchargement seulement par l\'élève propriétaire ou un formateur, dossier protégé contre l\'accès direct.</li></ul></div>';

		echo '<div class="li-c"><h2>🗄️ Base de données LogiInfo</h2><table class="widefat striped" style="max-width:760px"><thead><tr><th>Table</th><th>Contenu</th><th>Lignes</th></tr></thead><tbody>';
		foreach ( self::tables() as $t => $lab ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
			echo '<tr><td><code>' . esc_html( $t ) . '</code></td><td>' . esc_html( $lab ) . '</td><td>' . ( $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ) : '<span class="li-ko">absente</span>' ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table><p class="description">Version de la base : ' . esc_html( (string) get_option( 'logiinfo_db_version' ) ) . ' · extension ' . esc_html( LI_VERSION ) . '. Ces tables sont aussi incluses dans les sauvegardes de votre site (UpdraftPlus, hébergeur).</p></div>';

		list( $ok, $missing, $known ) = self::check_files();
		list( $n, $size, $files ) = self::disk();
		$orph = count( array_diff( $files, array_keys( $known ) ) );
		echo '<div class="li-c"><h2>📁 Fichiers des élèves</h2><p>Dossier : <code>' . esc_html( LIR_Core::storage_dir() ) . '</code><br>' . (int) $n . ' fichiers · ' . esc_html( size_format( $size ) ) . '</p>';
		echo $missing ? '<p class="li-ko">⚠️ ' . count( $missing ) . ' fichier(s) enregistré(s) en base introuvable(s) sur le disque : ' . esc_html( implode( ' ; ', array_slice( $missing, 0, 15 ) ) ) . ( count( $missing ) > 15 ? '…' : '' ) . '. Restaurez une sauvegarde ci-dessous.</p>'
			: '<p class="li-ok">✅ Les ' . (int) $ok . ' fichiers des remises sont tous présents.</p>';
		if ( $orph ) { echo '<p class="description">' . (int) $orph . ' fichier(s) sans remise associée (conservés, rien n\'est supprimé).</p>'; }
		list( $state, $info ) = self::probe();
		if ( 'ferme' === $state ) { echo '<p class="li-ok">🔒 Accès direct depuis Internet bloqué (réponse ' . (int) $info . ').</p>'; }
		elseif ( 'ouvert' === $state ) { echo '<p class="li-ko">⚠️ Le dossier des copies est lisible directement depuis Internet : votre serveur ignore le fichier .htaccess (serveur Nginx). Les noms de fichiers sont aléatoires et la liste du dossier est masquée, mais demandez à votre hébergeur d\'ajouter la règle : <code>location ^~ /wp-content/uploads/logiinfo/ { deny all; }</code></p>'; }
		else { echo '<p class="description">Test d\'accès direct impossible depuis le serveur (' . esc_html( $info ) . ').</p>'; }
		echo '</div>';

		echo '<div class="li-c"><h2>💾 Sauvegarde</h2><p>Un seul fichier ZIP avec toutes les remises (fichiers des élèves, notes, commentaires, observations de l\'IA), la progression des élèves, le journal et les réglages de LogiInfo. Conservez-le en lieu sûr, par exemple avant une grosse modification du site.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'logiinfo_sauvegarde' );
		echo '<input type="hidden" name="action" value="logiinfo_sauvegarde"><button class="button button-primary">⬇ Télécharger une sauvegarde complète</button>';
		$last = get_option( 'logiinfo_derniere_sauvegarde' );
		echo $last ? ' <span class="description">Dernière sauvegarde téléchargée le ' . esc_html( mysql2date( 'd.m.Y à H:i', $last ) ) . '.</span>' : ' <span class="description">Aucune sauvegarde téléchargée pour l\'instant.</span>';
		echo '</form>';
		echo '<h3 style="margin-top:20px">Restaurer une sauvegarde</h3><p class="description">Remet ce qui manque (remises, fichiers, progression) sans jamais écraser ni supprimer ce qui existe déjà.</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'logiinfo_restaurer' );
		echo '<input type="hidden" name="action" value="logiinfo_restaurer"><input type="file" name="sauvegarde" accept=".zip" required> <button class="button">Restaurer ce qui manque</button></form></div>';

		echo '<div class="li-c"><h2>📜 Journal (30 dernières opérations)</h2>';
		$rows = $wpdb->get_results( 'SELECT * FROM ' . LIR_Core::journal_table() . ' ORDER BY id DESC LIMIT 30', ARRAY_A );
		if ( ! $rows ) { echo '<p class="description">Aucune opération enregistrée.</p>'; }
		else {
			$lab = array( 'depot' => 'Dépôt d\'un élève', 'correction' => 'Correction enregistrée', 'publication' => 'Retour publié', 'corbeille' => 'Mise à la corbeille', 'restauration' => 'Restaurée', 'import_ia' => 'Correction IA importée', 'sauvegarde' => 'Sauvegarde téléchargée', 'restauration_sauvegarde' => 'Sauvegarde restaurée', 'email_formateur' => 'E-mail envoyé au formateur', 'email_echec' => 'Échec de l\'e-mail au formateur', 'reattribution' => 'Transmise à un autre formateur' );
			echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Par</th><th>Opération</th><th>Remise</th><th>Détail</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( mysql2date( 'd.m.Y H:i', $r['quand'] ) ) . '</td><td>' . esc_html( $r['user_id'] ? LIR_Core::user_label( $r['user_id'] ) : '–' ) . '</td><td>' . esc_html( $lab[ $r['action'] ] ?? $r['action'] ) . '</td><td>'
					. ( $r['remise_id'] ? '<a href="' . esc_url( admin_url( 'admin.php?page=fw-remises&vue=fiche&id=' . (int) $r['remise_id'] ) ) . '">R' . (int) $r['remise_id'] . '</a>' : '' ) . '</td><td>' . esc_html( (string) $r['detail'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
	}

	private static function back( $ok, $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'logiinfo-donnees', 'li_ok' => $ok ? 1 : 0, 'li_msg' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function backup() {
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { wp_die( 'Accès refusé.' ); }
		check_admin_referer( 'logiinfo_sauvegarde' );
		if ( ! class_exists( 'ZipArchive' ) ) { self::back( false, 'ZipArchive n\'est pas disponible sur ce serveur.' ); }
		global $wpdb;
		@set_time_limit( 600 );
		$path = wp_tempnam( 'logiinfo' ) . '.zip';
		$z = new ZipArchive();
		$z->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$dump = function ( $t ) use ( $wpdb ) { return wp_json_encode( $wpdb->get_results( "SELECT * FROM $t", ARRAY_A ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); }; // phpcs:ignore
		$z->addFromString( 'base/remises.json', $dump( LIR_Core::table() ) );
		$z->addFromString( 'base/progression.json', $dump( LIR_Core::progress_table() ) );
		$z->addFromString( 'base/journal.json', $dump( LIR_Core::journal_table() ) );
		$z->addFromString( 'base/eleves.json', $dump( LIR_Core::eleves_table() ) );
		$z->addFromString( 'base/exercices.json', $dump( LI_Exercices::table() ) );
		$opts = array();
		foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'logiinfo\\_%' OR option_name LIKE 'lpfi\\_%' OR option_name = 'fwr_settings'" ) as $o ) { $opts[ $o ] = get_option( $o ); }
		$z->addFromString( 'base/reglages.json', wp_json_encode( $opts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
		list( , , $files ) = self::disk();
		$dir = LIR_Core::storage_dir();
		foreach ( $files as $f ) { $z->addFile( $dir . '/' . $f, 'copies-eleves/' . $f ); }
		$z->addFromString( 'LISEZ-MOI.txt', "Sauvegarde LogiInfo du " . current_time( 'd.m.Y H:i' ) . " – site " . home_url() . "\nRestauration : LogiInfo › Données et sauvegarde › Restaurer une sauvegarde (rien n'est écrasé).\n" );
		$z->close();
		update_option( 'logiinfo_derniere_sauvegarde', current_time( 'mysql' ), false );
		LIR_Core::log( 'sauvegarde', 0, count( $files ) . ' fichiers' );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="logiinfo-sauvegarde_' . current_time( 'Y-m-d_Hi' ) . '.zip"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore
		wp_delete_file( $path );
		exit;
	}

	public static function restore() {
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { wp_die( 'Accès refusé.' ); }
		check_admin_referer( 'logiinfo_restaurer' );
		if ( empty( $_FILES['sauvegarde']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['sauvegarde']['error'] ) { self::back( false, 'Fichier non reçu (taille trop grande ?).' ); } // phpcs:ignore
		global $wpdb;
		$z = new ZipArchive();
		if ( true !== $z->open( $_FILES['sauvegarde']['tmp_name'] ) || false === $z->locateName( 'base/remises.json' ) ) { self::back( false, 'Ce fichier n\'est pas une sauvegarde LogiInfo.' ); } // phpcs:ignore
		$dir = LIR_Core::storage_dir();
		$nr = 0; $nf = 0; $np = 0;
		$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . LIR_Core::table() );
		foreach ( (array) json_decode( (string) $z->getFromName( 'base/remises.json' ), true ) as $row ) {
			if ( empty( $row['id'] ) || $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . LIR_Core::table() . ' WHERE id = %d', $row['id'] ) ) ) { continue; }
			$wpdb->insert( LIR_Core::table(), array_intersect_key( $row, array_flip( $cols ) ) ) && $nr++;
		}
		foreach ( (array) json_decode( (string) $z->getFromName( 'base/progression.json' ), true ) as $row ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . LIR_Core::progress_table() . ' WHERE user_id = %d AND cle = %s', $row['user_id'], $row['cle'] ) ) ) { continue; }
			unset( $row['id'] );
			$wpdb->insert( LIR_Core::progress_table(), $row ) && $np++;
		}
		foreach ( (array) json_decode( (string) $z->getFromName( 'base/exercices.json' ), true ) as $row ) {
			if ( empty( $row['id'] ) || $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . LI_Exercices::table() . ' WHERE id = %d', $row['id'] ) ) ) { continue; }
			$wpdb->insert( LI_Exercices::table(), $row );
		}
		foreach ( (array) json_decode( (string) $z->getFromName( 'base/eleves.json' ), true ) as $row ) {
			if ( empty( $row['user_id'] ) || $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . LIR_Core::eleves_table() . ' WHERE user_id = %d', $row['user_id'] ) ) ) { continue; }
			$wpdb->insert( LIR_Core::eleves_table(), $row );
		}
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$name = $z->getNameIndex( $i );
			if ( 0 !== strpos( $name, 'copies-eleves/' ) || '/' === substr( $name, -1 ) ) { continue; }
			$rel = substr( $name, strlen( 'copies-eleves/' ) );
			if ( false !== strpos( $rel, '..' ) || ! preg_match( '#^[0-9]+/[A-Z]{1,3}[0-9]{2,5}/[A-Za-z0-9_.-]+\.(docx|xlsx|pptx|pdf|jpe?g|png|zip|txt|odt|ods|odp)$#', $rel ) ) { continue; } // seulement des copies d'élèves
			$dest = $dir . '/' . $rel;
			if ( file_exists( $dest ) ) { continue; }
			wp_mkdir_p( dirname( $dest ) );
			file_put_contents( $dest, $z->getFromIndex( $i ) ) && $nf++;
		}
		$z->close();
		LIR_Core::log( 'restauration_sauvegarde', 0, "$nr remises, $nf fichiers, $np progressions" );
		self::back( true, "Restauration terminée : $nr remise(s), $nf fichier(s) et $np progression(s) remis en place. Rien n'a été écrasé." );
	}
}
LI_Donnees::init();
