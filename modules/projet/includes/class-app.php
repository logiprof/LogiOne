<?php
/**
 * Code court [logiprojet], page d'administration et chargement des styles et scripts.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_App {

	public static function init() {
		add_shortcode( 'logiprojet', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	public static function admin_menu() {
		if ( ! LogiProjet_Access::is_teacher() ) {
			return;
		}
		add_menu_page( 'LogiProjet', 'LogiProjet', 'read', 'logiprojet-app', array( __CLASS__, 'admin_page' ), 'dashicons-portfolio', 31 );
	}

	public static function admin_page() {
		if ( ! LogiProjet_Access::is_teacher() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiprojet' ) );
		}
		echo '<div class="wrap lpj-admin-app">' . self::render( array( 'embedded' => true ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function enqueue( $with_app = true ) {
		wp_enqueue_style( 'logiprojet', LOGIPROJET_URL . 'assets/logiprojet.css', array(), LOGIPROJET_VERSION );
		if ( ! $with_app ) {
			return;
		}
		wp_enqueue_script( 'logiprojet', LOGIPROJET_URL . 'assets/logiprojet.js', array(), LOGIPROJET_VERSION, true );
		wp_localize_script(
			'logiprojet',
			'LOGIPROJET',
			array(
				'root'  => esc_url_raw( rest_url( LogiProjet_Rest::NS . '/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'logo'  => LOGIPROJET_URL . 'assets/logiprojet-logo-white.svg',
			)
		);
	}

	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'pleine_largeur' => 'oui' ), $atts, 'logiprojet' );
		return self::render( array( 'embedded' => 'non' === $atts['pleine_largeur'] ) );
	}

	public static function render( $args ) {
		if ( ! is_user_logged_in() ) {
			self::enqueue( false );
			return '<div class="lpj lpj-embedded"><div class="lpj-login"><p>Connectez-vous pour accéder à vos travaux.</p><a class="lpj-btn lpj-btn-yellow" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Se connecter</a></div></div>';
		}
		self::enqueue();
		$class = 'lpj' . ( ! empty( $args['embedded'] ) ? ' lpj-embedded' : '' );
		return '<div class="' . esc_attr( $class ) . '" data-lpj-app><div class="lpj-boot"><span class="lpj-spinner" aria-hidden="true"></span> Chargement de LogiProjet…</div><noscript>LogiProjet a besoin de JavaScript.</noscript></div>';
	}
}
