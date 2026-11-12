<?php
/**
 * LogiOne 1.29 : Safe Exam Browser (SEB).
 *
 * Par évaluation, l'enseignant peut exiger SEB. LogiExam fournit alors :
 * - un fichier de configuration .seb (ouverture directe par un lien sebs://) qui démarre sur la page élève,
 *   n'autorise que ce site, coupe correcteur et dictée, et quitte SEB par une adresse dédiée ;
 * - un contrôle côté serveur à chaque demande de l'élève (démarrage, réponses, envoi) : hors de SEB, refus ;
 * - en option, la vérification de la « Config Key » (clé de configuration, copiée depuis l'outil de
 *   configuration SEB) : un navigateur ordinaire qui se ferait passer pour SEB est refusé.
 *
 * Aucune table n'est modifiée : les réglages sont stockés dans l'option « logiexam_seb ».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_SEB {
	const OPTION       = 'logiexam_seb';
	const DOWNLOAD_URL = 'https://safeexambrowser.org/download_en.html';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'handle_endpoints' ), 5 );
	}

	/* ── Réglages ──────────────────────────────────────────────────────────── */

	public static function modes(): array {
		return array(
			'off'      => __( 'Non : navigateur habituel (plein écran surveillé)', 'logieval-secure' ),
			'required' => __( 'Obligatoire : l’évaluation ne s’ouvre que dans Safe Exam Browser', 'logieval-secure' ),
			'key'      => __( 'Obligatoire + clé de configuration vérifiée (le plus sûr)', 'logieval-secure' ),
		);
	}

	public static function get( int $evaluation_id ): array {
		$all = get_option( self::OPTION, array() );
		$row = is_array( $all ) && isset( $all[ $evaluation_id ] ) && is_array( $all[ $evaluation_id ] ) ? $all[ $evaluation_id ] : array();
		$row = wp_parse_args( $row, array( 'mode' => 'off', 'keys' => array(), 'quit_hash' => '' ) );
		if ( ! isset( self::modes()[ $row['mode'] ] ) ) {
			$row['mode'] = 'off';
		}
		$row['keys'] = array_values( array_filter( array_map( 'strval', (array) $row['keys'] ) ) );
		return $row;
	}

	public static function is_required( int $evaluation_id ): bool {
		return 'off' !== self::get( $evaluation_id )['mode'];
	}

	/** Enregistre les réglages envoyés par le formulaire de l'évaluation. */
	public static function save_from_post( int $evaluation_id ): void {
		if ( ! $evaluation_id || ! isset( $_POST['seb_mode'] ) ) {
			return;
		}
		$current = self::get( $evaluation_id );
		$mode    = sanitize_key( wp_unslash( $_POST['seb_mode'] ) );
		$mode    = isset( self::modes()[ $mode ] ) ? $mode : 'off';
		$keys    = array();
		foreach ( preg_split( '/[\s,;]+/', strtolower( (string) wp_unslash( $_POST['seb_keys'] ?? '' ) ) ) as $k ) {
			if ( preg_match( '/^[0-9a-f]{64}$/', $k ) ) {
				$keys[] = $k;
			}
		}
		$quit_hash = $current['quit_hash'];
		$quit_pw   = (string) wp_unslash( $_POST['seb_quit_password'] ?? '' );
		if ( ! empty( $_POST['seb_quit_clear'] ) ) {
			$quit_hash = '';
		} elseif ( '' !== $quit_pw ) {
			$quit_hash = hash( 'sha256', $quit_pw );
		}
		if ( 'key' === $mode && ! $keys ) {
			// Sans clé collée, la vérification de clé est impossible : on reste sur « obligatoire ».
			$mode = 'required';
		}
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$row = array( 'mode' => $mode, 'keys' => array_values( array_unique( $keys ) ), 'quit_hash' => $quit_hash );
		if ( 'off' === $mode && ! $keys && '' === $quit_hash ) {
			unset( $all[ $evaluation_id ] );
		} else {
			$all[ $evaluation_id ] = $row;
		}
		update_option( self::OPTION, $all, false );
	}

	/* ── Adresses ──────────────────────────────────────────────────────────── */

	public static function token( int $evaluation_id ): string {
		return substr( wp_hash( 'logiexam_seb_' . $evaluation_id ), 0, 16 );
	}

	public static function student_page_url(): string {
		$url = (string) get_option( 'logiexam_student_page_url', '' );
		if ( '' === $url ) {
			global $wpdb;
			$id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post') AND ( post_content LIKE '%[logiexam_student%' OR post_content LIKE '%[logieval_student%' ) ORDER BY ID LIMIT 1" );
			$url = $id ? (string) get_permalink( $id ) : home_url( '/' );
		}
		return $url;
	}

	public static function start_url( int $evaluation_id ): string {
		return add_query_arg( 'logiexam_seb', $evaluation_id, self::student_page_url() );
	}

	public static function config_url( int $evaluation_id ): string {
		return add_query_arg( array( 'logiexam_seb_config' => $evaluation_id, 't' => self::token( $evaluation_id ) ), home_url( '/' ) );
	}

	/** Lien qui ouvre directement Safe Exam Browser avec la configuration (sebs:// = https, seb:// = http). */
	public static function seb_link( int $evaluation_id ): string {
		return preg_replace( '#^http(s?)://#i', 'seb$1://', self::config_url( $evaluation_id ) );
	}

	public static function quit_url(): string {
		return add_query_arg( 'logiexam_seb_quit', 1, home_url( '/' ) );
	}

	/* ── Fichier de configuration .seb ─────────────────────────────────────── */

	public static function config_settings( int $evaluation_id ): array {
		$s    = self::get( $evaluation_id );
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$out  = array(
			'originatorVersion'             => 'LogiExam ' . ( defined( 'LOGIPILOT_VERSION' ) ? LOGIPILOT_VERSION : '' ),
			'sebConfigPurpose'              => 0,
			'startURL'                      => self::start_url( $evaluation_id ),
			'sendBrowserExamKey'            => true,
			'quitURL'                       => self::quit_url(),
			'quitURLConfirm'                => false,
			'allowQuit'                     => true,
			'browserViewMode'               => 1,
			'enableBrowserWindowToolbar'    => false,
			'browserWindowAllowReload'      => true,
			'newBrowserWindowByLinkPolicy'  => 0,
			'newBrowserWindowByScriptPolicy' => 0,
			'allowSpellCheck'               => false,
			'allowDictation'                => false,
			'allowDictionaryLookup'         => false,
			'allowPreferencesWindow'        => false,
			'allowScreenSharing'            => false,
			'enablePrintScreen'             => false,
			'enablePrivateClipboard'        => true,
			'examSessionClearCookiesOnStart' => true,
			'URLFilterEnable'               => true,
			'URLFilterEnableContentFilter'  => false,
			'URLFilterRules'                => array(
				array( 'active' => true, 'regex' => false, 'expression' => $host, 'action' => 1 ),
				array( 'active' => true, 'regex' => false, 'expression' => $host . '/*', 'action' => 1 ),
			),
		);
		if ( '' !== $s['quit_hash'] ) {
			$out['hashedQuitPassword'] = $s['quit_hash'];
		}
		return $out;
	}

	public static function config_xml( int $evaluation_id ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' . "\n";
		$xml .= '<plist version="1.0">' . "\n" . self::plist_value( self::config_settings( $evaluation_id ) ) . "\n</plist>\n";
		return $xml;
	}

	private static function plist_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '<true/>' : '<false/>';
		}
		if ( is_int( $value ) ) {
			return '<integer>' . $value . '</integer>';
		}
		if ( is_array( $value ) ) {
			if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
				return '<array>' . implode( '', array_map( array( __CLASS__, 'plist_value' ), $value ) ) . '</array>';
			}
			$out = '<dict>';
			foreach ( $value as $k => $v ) {
				$out .= '<key>' . esc_html( (string) $k ) . '</key>' . self::plist_value( $v );
			}
			return $out . '</dict>';
		}
		return '<string>' . esc_html( (string) $value ) . '</string>';
	}

	/* ── Détection et vérification ─────────────────────────────────────────── */

	public static function request_is_seb(): bool {
		$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		return ! empty( $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] )
			|| ! empty( $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] )
			|| (bool) preg_match( '#\bSEB/\d#', $ua )
			|| '' !== (string) ( $_POST['seb_ck'] ?? '' );
	}

	/** Adresse complète de la demande en cours (http et https : derrière un proxy, le schéma vu par PHP peut différer). */
	private static function request_urls(): array {
		$host = (string) ( $_SERVER['HTTP_HOST'] ?? '' );
		$uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		return array( 'https://' . $host . $uri, 'http://' . $host . $uri );
	}

	/** La clé de configuration envoyée par SEB correspond-elle à l'une des clés enregistrées ? */
	public static function config_key_matches( array $keys ): bool {
		if ( ! $keys ) {
			return false;
		}
		$header = strtolower( (string) ( $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] ?? '' ) );
		if ( '' !== $header ) {
			foreach ( self::request_urls() as $url ) {
				foreach ( $keys as $key ) {
					if ( hash_equals( hash( 'sha256', $url . $key ), $header ) ) {
						return true;
					}
				}
			}
		}
		// Requêtes envoyées par la page (AJAX) : valeur donnée par l'API JavaScript de SEB pour l'adresse de la page.
		$ck      = strtolower( sanitize_text_field( wp_unslash( $_POST['seb_ck'] ?? '' ) ) );
		$page    = esc_url_raw( wp_unslash( $_POST['seb_url'] ?? '' ) );
		$site    = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$page_ok = '' !== $page && strtolower( (string) wp_parse_url( $page, PHP_URL_HOST ) ) === strtolower( $site );
		if ( preg_match( '/^[0-9a-f]{64}$/', $ck ) && $page_ok ) {
			$raw = (string) wp_unslash( $_POST['seb_url'] );
			foreach ( $keys as $key ) {
				if ( hash_equals( hash( 'sha256', $raw . $key ), $ck ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Contrôle d'accès d'une évaluation. Renvoie null si l'accès est autorisé, sinon les informations
	 * à afficher à l'élève (message + lien d'ouverture dans SEB).
	 */
	public static function check( int $evaluation_id ): ?array {
		$s = self::get( $evaluation_id );
		if ( 'off' === $s['mode'] ) {
			return null;
		}
		$base = array(
			'seb_required' => true,
			'seb_link'     => self::seb_link( $evaluation_id ),
			'seb_download' => self::DOWNLOAD_URL,
		);
		if ( ! self::request_is_seb() ) {
			return $base + array(
				'reason'  => 'not_seb',
				'message' => __( 'Cette évaluation se fait uniquement dans Safe Exam Browser. Cliquez sur « Ouvrir dans Safe Exam Browser », connectez-vous puis entrez à nouveau votre code.', 'logieval-secure' ),
			);
		}
		if ( 'key' === $s['mode'] && ! self::config_key_matches( $s['keys'] ) ) {
			return $base + array(
				'reason'  => 'bad_key',
				'message' => __( 'Safe Exam Browser est bien ouvert, mais pas avec la configuration de cette évaluation. Fermez Safe Exam Browser, puis utilisez le lien « Ouvrir dans Safe Exam Browser » donné par votre enseignant.', 'logieval-secure' ),
			);
		}
		return null;
	}

	/* ── Adresses publiques : fichier .seb, sortie, test ───────────────────── */

	public static function handle_endpoints(): void {
		if ( isset( $_GET['logiexam_seb_config'] ) ) {
			$id = absint( $_GET['logiexam_seb_config'] );
			$t  = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );
			if ( ! $id || ! hash_equals( self::token( $id ), $t ) || ! self::is_required( $id ) ) {
				status_header( 404 );
				nocache_headers();
				echo esc_html__( 'Configuration Safe Exam Browser introuvable pour cette évaluation.', 'logieval-secure' );
				exit;
			}
			nocache_headers();
			header( 'Content-Type: application/seb' );
			header( 'Content-Disposition: attachment; filename="logiexam-' . $id . '.seb"' );
			echo self::config_xml( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- plist XML échappé élément par élément.
			exit;
		}
		if ( isset( $_GET['logiexam_seb_quit'] ) ) {
			nocache_headers();
			echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>LogiExam</title><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>'
				. esc_html__( 'Évaluation terminée', 'logieval-secure' ) . '</h1><p>' . esc_html__( 'Safe Exam Browser va se fermer.', 'logieval-secure' ) . '</p></body></html>';
			exit;
		}
	}

	/** Bandeau affiché sur la page élève ouverte depuis la configuration SEB (aussi utile à l'enseignant pour tester). */
	public static function student_banner(): string {
		$id = absint( $_GET['logiexam_seb'] ?? 0 );
		if ( ! $id ) {
			return '';
		}
		$s     = self::get( $id );
		$is    = self::request_is_seb();
		$lines = array();
		$lines[] = $is ? '✓ ' . __( 'Safe Exam Browser détecté.', 'logieval-secure' ) : '✗ ' . __( 'Safe Exam Browser non détecté : vous êtes dans un navigateur ordinaire.', 'logieval-secure' );
		if ( $is && 'key' === $s['mode'] ) {
			$lines[] = self::config_key_matches( $s['keys'] )
				? '✓ ' . __( 'Configuration de l’évaluation vérifiée.', 'logieval-secure' )
				: '… ' . __( 'Configuration : vérification au moment d’entrer le code.', 'logieval-secure' );
		}
		return '<div class="les-seb-banner' . ( $is ? ' is-ok' : ' is-ko' ) . '" data-les-seb-banner>' . implode( '<br>', array_map( 'esc_html', $lines ) ) . '</div>';
	}
}
