<?php
/**
 * Installation : tables, droits, dossier protégé des fichiers déposés.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Install {

	const CAP = 'logiprojet_enseigner';

	public static function activate() {
		self::create_tables();
		self::upload_dir();
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAP ) ) {
			$admin->add_cap( self::CAP );
		}
		if ( false === get_option( 'logiprojet_settings', false ) ) {
			add_option( 'logiprojet_settings', LogiProjet_Settings::defaults(), '', 'no' );
		}
		update_option( 'logiprojet_db_version', LOGIPROJET_DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'logiprojet_db_version' ) !== LOGIPROJET_DB_VERSION ) {
			self::activate();
		}
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'logiprojet_' . $name;
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$travaux = self::table( 'travaux' );
		$depots  = self::table( 'depots' );

		dbDelta(
			"CREATE TABLE {$travaux} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL DEFAULT '',
				module varchar(40) NOT NULL DEFAULT '',
				course_id bigint(20) unsigned NOT NULL DEFAULT 0,
				lessons longtext NULL,
				grille longtext NULL,
				consignes longtext NULL,
				consigne longtext NULL,
				date_debut datetime NULL,
				consigne_url varchar(500) NOT NULL DEFAULT '',
				group_ids longtext NULL,
				due_at datetime NULL,
				oral_bonus tinyint(1) NOT NULL DEFAULT 1,
				allow_solo tinyint(1) NOT NULL DEFAULT 0,
				group_size tinyint(2) NOT NULL DEFAULT 2,
				graded tinyint(1) NOT NULL DEFAULT 1,
				formats longtext NULL,
				status varchar(20) NOT NULL DEFAULT 'brouillon',
				author_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY author_id (author_id),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$depots} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				travail_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user1 bigint(20) unsigned NOT NULL DEFAULT 0,
				user2 bigint(20) unsigned NOT NULL DEFAULT 0,
				members longtext NULL,
				file_path varchar(255) NOT NULL DEFAULT '',
				file_name varchar(255) NOT NULL DEFAULT '',
				file_size bigint(20) unsigned NOT NULL DEFAULT 0,
				submitted_by bigint(20) unsigned NOT NULL DEFAULT 0,
				submitted_at datetime NULL,
				draft_path varchar(255) NOT NULL DEFAULT '',
				draft_name varchar(255) NOT NULL DEFAULT '',
				draft_size bigint(20) unsigned NOT NULL DEFAULT 0,
				draft_by bigint(20) unsigned NOT NULL DEFAULT 0,
				draft_at datetime NULL,
				late_excused tinyint(1) NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'depose',
				extract longtext NULL,
				ai longtext NULL,
				ai_error text NULL,
				ai_model varchar(120) NOT NULL DEFAULT '',
				scores longtext NULL,
				oral longtext NULL,
				feedback longtext NULL,
				points decimal(6,2) NULL,
				note decimal(3,1) NULL,
				note_finale decimal(3,1) NULL,
				validated_at datetime NULL,
				validated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY travail_id (travail_id),
				KEY user1 (user1),
				KEY user2 (user2)
			) {$charset};"
		);
	}

	/**
	 * Dossier des PowerPoint déposés : nom aléatoire, accès direct bloqué.
	 * Les fichiers ne sont servis que par l'API, après contrôle des droits.
	 */
	public static function upload_dir() {
		$token = get_option( 'logiprojet_dir_token' );
		if ( ! $token ) {
			$token = wp_generate_password( 24, false, false );
			update_option( 'logiprojet_dir_token', $token, false );
		}
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . 'logiprojet-' . $token;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}
}
