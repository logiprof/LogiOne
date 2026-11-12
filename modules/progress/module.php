<?php
/**
 * Module LogiProgress de LogiOne (code issu de LogiProgress 3.8.9).
 * Quand le module est désactivé, seuls les enregistrements de dates Sensei restent actifs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/reset.php';
require_once __DIR__ . '/suspicion.php';

final class LogiProgress_Sensei_Group_Progress_Tracker {
	use LogiProgress_UI;
	use LogiProgress_Reset;
	use LogiProgress_Suspicion;

	const VERSION              = '4.0.0';
	const PAGE                 = 'logiprogress';
	const SHORTCODE            = 'pickles_sensei_group_progress';
	const INACTIVITY_SHORTCODE = 'pickles_sensei_inactive_students';
	const LOGIPROGRESS_SHORTCODE = 'logiprogress';
	const LOGIPROGRESS_INACTIVITY_SHORTCODE = 'logiprogress_inactive_students';
	const OPTION_INACTIVITY_DAYS = 'psgpt_inactivity_days';
	const OPTION_COLORS        = 'psgpt_interface_colors';
	const ACCESS_CAP           = 'lp_progress_view';
	const RULE_VERSION         = 'logipilot-2026.10b';

	const META_FIRST_COMPLETED_AT = '_psgpt_first_completed_at';
	const META_REOPENED_AT        = '_psgpt_reopened_at';
	const LEGACY_META_FIRST_COMPLETED_AT = '_lsgpt_first_completed_at';
	const LEGACY_META_REOPENED_AT        = '_lsgpt_reopened_at';

	private static $assets_loaded = false;

	/** Mode réduit : seules les dates de validation Sensei sont mémorisées (module désactivé). */
	public static function init_hooks_only() {
		if ( defined( 'LOGIPILOT_TEST_MODE' ) ) {
			return; // Mode test : aucune écriture dans les données Sensei.
		}
		add_action( 'transition_comment_status', array( __CLASS__, 'remember_lesson_status_dates' ), 10, 3 );
		add_action( 'comment_post', array( __CLASS__, 'remember_new_lesson_status_dates' ), 10, 3 );
		add_action( 'edit_comment', array( __CLASS__, 'remember_edited_lesson_status_dates' ), 10, 2 );
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_post_psgpt_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_psgpt_export_pdf', array( __CLASS__, 'export_pdf_page' ) );
		add_action( 'wp_ajax_psgpt_toggle_favorite', array( __CLASS__, 'ajax_toggle_favorite' ) );
		add_action( 'admin_post_psgpt_toggle_favorite', array( __CLASS__, 'post_toggle_favorite' ) );
		add_action( 'admin_post_psgpt_reset_progress', array( __CLASS__, 'handle_reset_progress' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( self::INACTIVITY_SHORTCODE, array( __CLASS__, 'render_inactivity_shortcode' ) );
		add_shortcode( self::LOGIPROGRESS_SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( self::LOGIPROGRESS_INACTIVITY_SHORTCODE, array( __CLASS__, 'render_inactivity_shortcode' ) );

		if ( ! defined( 'LOGIPILOT_TEST_MODE' ) ) {
			add_action( 'transition_comment_status', array( __CLASS__, 'remember_lesson_status_dates' ), 10, 3 );
			add_action( 'comment_post', array( __CLASS__, 'remember_new_lesson_status_dates' ), 10, 3 );
			add_action( 'edit_comment', array( __CLASS__, 'remember_edited_lesson_status_dates' ), 10, 2 );
		}
	}

	public static function register_admin_page() {
		add_menu_page(
			__( 'LogiProgress', 'pickles-sensei-group-progress-tracker' ),
			__( 'LogiProgress', 'pickles-sensei-group-progress-tracker' ),
			self::ACCESS_CAP,
			self::PAGE,
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-welcome-learn-more',
			3
		);
	}

	/** LogiOne : les droits viennent de la matrice centrale ; plus aucun droit n'est ajouté aux rôles. */
	public static function sync_access_capability() {
		return;
		$roles = wp_roles();
		if ( ! $roles ) {
			return;
		}

		foreach ( $roles->roles as $role_key => $role_data ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				continue;
			}

			$role_name = isset( $role_data['name'] ) ? (string) $role_data['name'] : '';
			if ( 'administrator' === $role_key || self::is_logistics_teacher_role( $role_key, $role_name ) ) {
				$role->add_cap( self::ACCESS_CAP );
			}
		}
	}

	private static function is_logistics_teacher_role( $role_key, $role_name ) {
		$normalized_key = self::normalize_role_text( $role_key );
		$normalized_name = self::normalize_role_text( $role_name );

		return in_array( $normalized_key, array( 'enseignant_logistique', 'um_enseignant_logistique' ), true )
			|| false !== strpos( $normalized_key, 'enseignant_logistique' )
			|| false !== strpos( $normalized_name, 'enseignant_logistique' );
	}

	private static function normalize_role_text( $value ) {
		$value = remove_accents( strtolower( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );
		return trim( (string) $value, '_' );
	}

	/** Le paramètre capability des shortcodes et des URL est ignoré : il ne peut plus abaisser l'autorisation. */
	private static function current_user_can_access_plugin( $capability = self::ACCESS_CAP ) {
		return is_user_logged_in() && current_user_can( 'lp_progress_view' );
	}

	private static function current_user_has_logistics_teacher_role() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		$wp_roles = wp_roles();
		foreach ( (array) $user->roles as $role_key ) {
			$role_name = isset( $wp_roles->roles[ $role_key ]['name'] ) ? $wp_roles->roles[ $role_key ]['name'] : '';
			if ( self::is_logistics_teacher_role( $role_key, $role_name ) ) {
				return true;
			}
		}

		return false;
	}

	public static function register_assets() {
		wp_register_style(
			'pickles-sensei-group-progress',
			plugins_url( 'assets/group-progress.css', __FILE__ ),
			array(),
			self::VERSION . ( defined( 'LOGIPILOT_VERSION' ) ? '-' . LOGIPILOT_VERSION : '' )
		);
		wp_register_script(
			'pickles-sensei-group-progress-ui',
			plugins_url( 'assets/progress-ui.js', __FILE__ ),
			array(),
			self::VERSION . ( defined( 'LOGIPILOT_VERSION' ) ? '-' . LOGIPILOT_VERSION : '' ),
			true
		);
	}

	private static function enqueue_assets() {
		if ( self::$assets_loaded ) {
			return;
		}

		if ( ! wp_style_is( 'pickles-sensei-group-progress', 'registered' ) ) {
			self::register_assets();
		}

		wp_enqueue_style( 'pickles-sensei-group-progress' );
		wp_enqueue_script( 'pickles-sensei-group-progress-ui' );
		wp_add_inline_style( 'pickles-sensei-group-progress', self::get_custom_color_css() );
		if ( ! is_admin() && did_action( 'wp_head' ) && ! wp_style_is( 'pickles-sensei-group-progress', 'done' ) ) {
			wp_print_styles( 'pickles-sensei-group-progress' );
		}
		self::$assets_loaded = true;
	}

	private static function get_color_fields() {
		return array(
			'primary'    => array(
				'label'   => __( 'Couleur principale', 'pickles-sensei-group-progress-tracker' ),
				'default' => '#047857',
			),
			'primary_dark' => array(
				'label'   => __( 'Couleur principale foncee', 'pickles-sensei-group-progress-tracker' ),
				'default' => '#065f46',
			),
			'background' => array(
				'label'   => __( 'Fond de l interface', 'pickles-sensei-group-progress-tracker' ),
				'default' => '#fbfaf6',
			),
			'text'       => array(
				'label'   => __( 'Texte principal', 'pickles-sensei-group-progress-tracker' ),
				'default' => '#142033',
			),
			'muted'      => array(
				'label'   => __( 'Texte secondaire', 'pickles-sensei-group-progress-tracker' ),
				'default' => '#687586',
			),
			'warning'    => array(
				'label'   => __( 'En cours', 'pickles-sensei-group-progress-tracker' ),
				'default' => '#aa7418',
			),
			'danger'     => array(
				'label'   => __( 'À reprendre', 'pickles-sensei-group-progress-tracker' ),
				'default' => '#b83232',
			),
		);
	}

	private static function get_interface_colors() {
		$defaults = wp_list_pluck( self::get_color_fields(), 'default' );
		$saved = get_option( self::OPTION_COLORS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return self::sanitize_interface_colors( array_merge( $defaults, $saved ) );
	}

	private static function sanitize_interface_colors( $colors ) {
		$sanitized = array();
		foreach ( self::get_color_fields() as $key => $field ) {
			$value = isset( $colors[ $key ] ) ? sanitize_hex_color( $colors[ $key ] ) : '';
			$sanitized[ $key ] = $value ? $value : $field['default'];
		}

		return $sanitized;
	}

	private static function get_custom_color_css() {
		$colors = self::get_interface_colors();
		$primary_rgb = self::hex_to_rgb_string( $colors['primary'] );
		$text_rgb = self::hex_to_rgb_string( $colors['text'] );
		$warning_rgb = self::hex_to_rgb_string( $colors['warning'] );
		$danger_rgb = self::hex_to_rgb_string( $colors['danger'] );

		return sprintf(
			'.psgpt{--psgpt-ink:%1$s;--psgpt-muted:%2$s;--psgpt-green:%4$s;--psgpt-green-dark:%5$s;--psgpt-green-mid:%4$s;--psgpt-green-soft:rgba(%6$s,.10);--psgpt-gold:%7$s;--psgpt-red:%8$s;}',
			esc_attr( $colors['text'] ),
			esc_attr( $colors['muted'] ),
			esc_attr( $colors['background'] ),
			esc_attr( $colors['primary'] ),
			esc_attr( $colors['primary_dark'] ),
			esc_attr( $primary_rgb ),
			esc_attr( $colors['warning'] ),
			esc_attr( $colors['danger'] ),
			esc_attr( $text_rgb ),
			esc_attr( $warning_rgb ),
			esc_attr( $danger_rgb )
		);
	}

	private static function hex_to_rgb_string( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '29,127,116';
		}

		return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
	}

	public static function render_admin_page() {
		if ( ! self::current_user_can_access_plugin() ) {
			wp_die( esc_html__( 'Acces non autorise.', 'pickles-sensei-group-progress-tracker' ) );
		}

		self::enqueue_assets();

		$group_id  = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LogiProgress', 'pickles-sensei-group-progress-tracker' ); ?></h1>
			<p><?php esc_html_e( 'Selectionne un groupe et un cours pour consulter les validations de chaque lecon, avec date et heure.', 'pickles-sensei-group-progress-tracker' ); ?></p>
			<?php self::render_settings_panel(); ?>
			<?php echo self::render_tracker( array( 'group_id' => $group_id, 'course_id' => $course_id, 'show_filters' => 'yes', 'context' => 'admin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h2><?php esc_html_e( 'Shortcode', 'pickles-sensei-group-progress-tracker' ); ?></h2>
			<p><code>[logiprogress show_filters="yes"]</code></p>
			<p><code>[logiprogress group_id="123" course_id="456"]</code></p>
			<p><code>[logiprogress group_id="123" course_ids="456,789"]</code></p>
			<p><code>[logiprogress_inactive_students show_filters="yes"]</code></p>
		</div>
		<?php
	}

	private static function render_settings_panel() {
		$days = self::get_default_inactivity_days();
		$colors = self::get_interface_colors();
		$settings_saved = isset( $_GET['psgpt_settings_saved'] ) && '1' === (string) $_GET['psgpt_settings_saved'];
		?>
		<div class="psgpt-admin-settings">
			<h2><?php esc_html_e( 'Parametres LogiProgress', 'pickles-sensei-group-progress-tracker' ); ?></h2>
			<?php if ( $settings_saved ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Parametres enregistres.', 'pickles-sensei-group-progress-tracker' ); ?></p></div>
			<?php endif; ?>
			<?php if ( current_user_can( 'lp_progress_settings' ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="psgpt_save_settings">
					<?php wp_nonce_field( 'psgpt_save_settings' ); ?>
					<div class="psgpt-settings-grid">
						<div class="psgpt-settings-section">
							<h3><?php esc_html_e( 'Inactivite', 'pickles-sensei-group-progress-tracker' ); ?></h3>
							<label for="psgpt-inactivity-days"><?php esc_html_e( 'Signaler un module apres', 'pickles-sensei-group-progress-tracker' ); ?></label>
							<input id="psgpt-inactivity-days" name="inactivity_days" type="number" min="1" max="365" value="<?php echo esc_attr( $days ); ?>">
							<span><?php esc_html_e( 'jours sans activite', 'pickles-sensei-group-progress-tracker' ); ?></span>
						</div>
						<div class="psgpt-settings-section">
							<h3><?php esc_html_e( 'Couleurs de l interface', 'pickles-sensei-group-progress-tracker' ); ?></h3>
							<?php foreach ( self::get_color_fields() as $key => $field ) : ?>
								<label class="psgpt-color-field" for="psgpt-color-<?php echo esc_attr( $key ); ?>">
									<span><?php echo esc_html( $field['label'] ); ?></span>
									<input id="psgpt-color-<?php echo esc_attr( $key ); ?>" name="colors[<?php echo esc_attr( $key ); ?>]" type="color" value="<?php echo esc_attr( isset( $colors[ $key ] ) ? $colors[ $key ] : $field['default'] ); ?>">
								</label>
							<?php endforeach; ?>
						</div>
					</div>
					<?php submit_button( __( 'Enregistrer', 'pickles-sensei-group-progress-tracker' ), 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><?php echo esc_html( sprintf( __( 'Seuil actuel : %s jours sans activite.', 'pickles-sensei-group-progress-tracker' ), number_format_i18n( $days ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'lp_progress_settings' ) ) {
			wp_die( esc_html__( 'Acces non autorise.', 'pickles-sensei-group-progress-tracker' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'psgpt_save_settings' );

		$days = isset( $_POST['inactivity_days'] ) ? absint( wp_unslash( $_POST['inactivity_days'] ) ) : 7;
		$days = min( 365, max( 1, $days ) );
		update_option( self::OPTION_INACTIVITY_DAYS, $days );

		$colors = self::sanitize_interface_colors( isset( $_POST['colors'] ) ? wp_unslash( $_POST['colors'] ) : array() );
		update_option( self::OPTION_COLORS, $colors );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::PAGE,
					'psgpt_settings_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'group_id'     => 0,
				'course_id'    => 0,
				'course_ids'   => '',
				'show_filters' => 'yes',
				'filter_id'    => 'default',
				'capability'   => self::ACCESS_CAP,
				'show_export'  => 'yes',
				'empty_text'   => __( 'Aucune donnee a afficher pour le moment.', 'pickles-sensei-group-progress-tracker' ),
				'class'        => '',
			),
			$atts,
			self::SHORTCODE
		);

		if ( ! self::current_user_can_access_plugin() ) {
			return '<div class="psgpt-empty">' . esc_html__( 'Acces non autorise.', 'pickles-sensei-group-progress-tracker' ) . '</div>';
		}

		self::enqueue_assets();

		return self::render_tracker( $atts );
	}

	public static function render_inactivity_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'group_id'     => 0,
				'days'         => self::get_default_inactivity_days(),
				'show_filters' => 'yes',
				'filter_id'    => 'inactive',
				'capability'   => self::ACCESS_CAP,
				'empty_text'   => __( 'Aucun eleve inactif a signaler pour ce groupe.', 'pickles-sensei-group-progress-tracker' ),
				'class'        => '',
			),
			$atts,
			self::INACTIVITY_SHORTCODE
		);

		if ( ! self::current_user_can_access_plugin() ) {
			return '<div class="psgpt-empty">' . esc_html__( 'Acces non autorise.', 'pickles-sensei-group-progress-tracker' ) . '</div>';
		}

		self::enqueue_assets();

		return self::render_inactivity_tracker( $atts );
	}







	private static function get_report_course_id( $report ) {
		return isset( $report['course']->ID ) ? absint( $report['course']->ID ) : 0;
	}



	private static function summary_initials( $name ) {
		$parts = preg_split( '/[\s\-]+/u', trim( (string) $name ) );
		$out   = '';
		foreach ( array_slice( array_filter( (array) $parts ), 0, 2 ) as $part ) {
			$out .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
		}

		return '' !== $out ? $out : '?';
	}


	private static function render_lesson_cell( $detail ) {
		$status = isset( $detail['status'] ) ? $detail['status'] : '';
		$is_done = self::is_complete_status( $status );
		$badge_class = $is_done ? 'is-complete' : ( 'in-progress' === $status ? 'is-progress' : ( 'failed' === $status ? 'is-failed' : 'is-empty' ) );
		?>
		<div class="psgpt-status <?php echo esc_attr( $badge_class ); ?>">
			<span><?php echo esc_html( self::format_status( $status ) ); ?></span>
			<?php if ( ! empty( $detail['first_completed_at'] ) ) : ?>
				<small><?php echo esc_html( sprintf( __( 'Validée le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['first_completed_at'] ) ) ); ?></small>
			<?php elseif ( ! empty( $detail['updated_at'] ) ) : ?>
				<small><?php echo esc_html( sprintf( __( 'Mise à jour le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['updated_at'] ) ) ); ?></small>
			<?php endif; ?>
			<?php if ( ! empty( $detail['reopened_at'] ) ) : ?>
				<small class="psgpt-retry"><?php echo esc_html( sprintf( __( 'Reprise le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['reopened_at'] ) ) ); ?></small>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_quiz_score_for_lesson( $user_id, $lesson_id ) {
		$score = self::get_lesson_quiz_score_percent( $user_id, $lesson_id );
		if ( '' === $score ) {
			return;
		}

		echo '<small class="psgpt-quiz-score">' . esc_html( sprintf( __( 'Quiz : %s%%', 'pickles-sensei-group-progress-tracker' ), $score ) ) . '</small>';
	}

	private static function get_summary_lesson_label( $detail, $user_id = 0, $lesson_id = 0 ) {
		$status = isset( $detail['status'] ) ? $detail['status'] : '';
		$label = self::format_status( $status );

		if ( ! empty( $detail['first_completed_at'] ) ) {
			$label .= ' - ' . self::format_datetime( $detail['first_completed_at'] );
		} elseif ( ! empty( $detail['updated_at'] ) ) {
			$label .= ' - ' . self::format_datetime( $detail['updated_at'] );
		}

		if ( $user_id && $lesson_id ) {
			$score = self::get_lesson_quiz_score_percent( $user_id, $lesson_id );
			if ( '' !== $score ) {
				$label .= ' - ' . sprintf( __( 'Quiz : %s%%', 'pickles-sensei-group-progress-tracker' ), $score );
			}
		}

		return $label;
	}

	private static function get_export_url( $group_id, $course_ids, $capability ) {
		$course_ids = self::parse_course_ids( $course_ids );
		$args = array(
			'action'     => 'psgpt_export_pdf',
			'group_id'   => absint( $group_id ),
			'course_ids' => implode( ',', $course_ids ),
			'capability' => sanitize_key( $capability ),
		);

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'psgpt_export_pdf_' . absint( $group_id ) . '_' . md5( implode( ',', $course_ids ) ) );
	}

	public static function export_pdf_page() {
		$group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
		$course_ids = isset( $_GET['course_ids'] ) ? self::parse_course_ids( wp_unslash( $_GET['course_ids'] ) ) : array();
		if ( empty( $course_ids ) && isset( $_GET['course_id'] ) ) {
			$course_ids = array( absint( $_GET['course_id'] ) );
		}
		$capability = isset( $_GET['capability'] ) ? sanitize_key( wp_unslash( $_GET['capability'] ) ) : self::ACCESS_CAP;

		if ( ! $group_id || empty( $course_ids ) || ! check_admin_referer( 'psgpt_export_pdf_' . $group_id . '_' . md5( implode( ',', $course_ids ) ) ) ) {
			wp_die( esc_html__( 'Lien d export invalide.', 'pickles-sensei-group-progress-tracker' ) );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'lp_progress_export' ) ) {
			wp_die( esc_html__( 'Acces non autorise.', 'pickles-sensei-group-progress-tracker' ), '', array( 'response' => 403 ) );
		}

		$reports = self::get_reports_data( $group_id, $course_ids );
		$group_title = ! empty( $reports ) ? $reports[0]['group_title'] : __( 'Groupe selectionne', 'pickles-sensei-group-progress-tracker' );
		$title = count( $reports ) > 1 ? __( 'LogiProgress multi-cours', 'pickles-sensei-group-progress-tracker' ) : ( ! empty( $reports[0]['course_title'] ) ? $reports[0]['course_title'] : __( 'LogiProgress', 'pickles-sensei-group-progress-tracker' ) );
		$filename = sanitize_title( $title . '-' . $group_title . '-logiprogress' );
		$colors = self::get_interface_colors();

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $title . ' - ' . $group_title ); ?></title>
			<style>
				@page { size: A4 landscape; margin: 10mm; }
				body { margin: 0; color: <?php echo esc_html( $colors['text'] ); ?>; font: 11px/1.35 Arial, sans-serif; }
				header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 14px; }
				h1 { margin: 0 0 4px; font-size: 18px; line-height: 1.2; }
				h2 { margin: 0 0 8px; font-size: 15px; line-height: 1.2; }
				p { margin: 0; color: <?php echo esc_html( $colors['muted'] ); ?>; }
				.course-page { page-break-before: always; break-before: page; }
				.course-page:first-of-type { page-break-before: auto; break-before: auto; }
				table { width: 100%; border-collapse: collapse; table-layout: fixed; }
				th, td { padding: 6px; border: 1px solid #d9e0e7; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
				thead { display: table-header-group; }
				tbody { display: table-row-group; }
				tr { page-break-inside: avoid; break-inside: avoid; }
				thead th { background: <?php echo esc_html( $colors['primary'] ); ?>; color: #fff; font-weight: 700; }
				.student { width: 140px; }
				.progress { width: 72px; }
				.status { display: block; font-weight: 700; }
				.meta { display: block; margin-top: 2px; color: <?php echo esc_html( $colors['muted'] ); ?>; font-size: 9px; }
				.done { color: <?php echo esc_html( $colors['primary'] ); ?>; }
				.progressing { color: <?php echo esc_html( $colors['warning'] ); ?>; }
				.failed { color: <?php echo esc_html( $colors['danger'] ); ?>; }
				.quiz-score { color: <?php echo esc_html( $colors['primary_dark'] ); ?>; font-weight: 700; }
				.actions { margin: 0 0 12px; }
				.actions button { padding: 8px 12px; border: 0; border-radius: 4px; background: <?php echo esc_html( $colors['primary'] ); ?>; color: #fff; font-weight: 700; cursor: pointer; }
				@media print { .actions { display: none; } thead { display: table-header-group; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
			</style>
		</head>
		<body data-filename="<?php echo esc_attr( $filename ); ?>">
			<div class="actions">
				<button type="button" onclick="window.print()"><?php esc_html_e( 'Enregistrer en PDF', 'pickles-sensei-group-progress-tracker' ); ?></button>
			</div>
			<header>
				<div>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p><?php echo esc_html( $group_title ); ?></p>
				</div>
				<p><?php echo esc_html( sprintf( __( 'Export genere le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( current_time( 'mysql' ) ) ) ); ?></p>
			</header>
			<?php foreach ( $reports as $report ) : ?>
				<section class="course-page">
					<h2><?php echo esc_html( $report['course_title'] ); ?></h2>
					<?php self::render_pdf_table( $report ); ?>
				</section>
			<?php endforeach; ?>
			<script>
				document.title = document.body.getAttribute('data-filename') || document.title;
				window.addEventListener('load', function() {
					window.setTimeout(function() { window.print(); }, 300);
				});
			</script>
		</body>
		</html>
		<?php
		exit;
	}

	private static function get_report_data( $group_id, $course_id ) {
		$student_ids = self::get_group_student_ids( $group_id );
		$lessons = self::get_course_lessons( $course_id );
		$lesson_ids = wp_list_pluck( $lessons, 'ID' );
		$group = get_post( $group_id );
		$course = get_post( $course_id );
		$students = array();

		foreach ( $student_ids as $student_id ) {
			$user = get_userdata( $student_id );
			if ( ! $user ) {
				continue;
			}

			$statuses = self::get_user_lesson_status_details( $student_id, $lesson_ids );
			$students[] = array(
				'id'       => $student_id,
				'name'     => $user->display_name,
				'email'    => $user->user_email,
				'statuses' => $statuses,
				'progress' => self::calculate_progress( $statuses, $lesson_ids ),
			);
		}

		return array(
			'group_title'  => $group ? $group->post_title : __( 'Groupe selectionne', 'pickles-sensei-group-progress-tracker' ),
			'course_title' => $course ? $course->post_title : __( 'Cours selectionne', 'pickles-sensei-group-progress-tracker' ),
			'lessons'      => $lessons,
			'students'     => $students,
		);
	}

	private static function get_reports_data( $group_id, $course_ids ) {
		$reports = array();
		foreach ( self::parse_course_ids( $course_ids ) as $course_id ) {
			$report = self::get_report_data( $group_id, $course_id );
			if ( ! empty( $report['lessons'] ) && ! empty( $report['students'] ) ) {
				$reports[] = $report;
			}
		}

		return $reports;
	}

	private static function render_pdf_table( $report ) {
		if ( empty( $report['lessons'] ) || empty( $report['students'] ) ) {
			echo '<p>' . esc_html__( 'Aucune donnee a exporter.', 'pickles-sensei-group-progress-tracker' ) . '</p>';
			return;
		}
		?>
		<table>
			<thead>
				<tr>
					<th class="student"><?php esc_html_e( 'Élève', 'pickles-sensei-group-progress-tracker' ); ?></th>
					<?php foreach ( $report['lessons'] as $lesson ) : ?>
						<th><?php echo esc_html( $lesson->post_title ); ?></th>
					<?php endforeach; ?>
					<th class="progress"><?php esc_html_e( 'Progression', 'pickles-sensei-group-progress-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['students'] as $student ) : ?>
					<tr>
						<th class="student">
							<?php echo esc_html( $student['name'] ); ?>
							<span class="meta"><?php echo esc_html( $student['email'] ); ?></span>
						</th>
						<?php foreach ( $report['lessons'] as $lesson ) : ?>
							<?php $detail = isset( $student['statuses'][ $lesson->ID ] ) ? $student['statuses'][ $lesson->ID ] : array(); ?>
							<td>
								<?php
								self::render_pdf_lesson_cell( $detail );
								self::render_pdf_quiz_score_for_lesson( isset( $student['id'] ) ? $student['id'] : 0, $lesson->ID );
								?>
							</td>
						<?php endforeach; ?>
						<td><?php echo esc_html( $student['progress'] ); ?>%</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_pdf_lesson_cell( $detail ) {
		$status = isset( $detail['status'] ) ? $detail['status'] : '';
		$class = self::is_complete_status( $status ) ? 'done' : ( 'in-progress' === $status ? 'progressing' : ( 'failed' === $status ? 'failed' : '' ) );

		echo '<span class="status ' . esc_attr( $class ) . '">' . esc_html( self::format_status( $status ) ) . '</span>';

		if ( ! empty( $detail['first_completed_at'] ) ) {
			echo '<span class="meta">' . esc_html( sprintf( __( 'Validée le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['first_completed_at'] ) ) ) . '</span>';
		} elseif ( ! empty( $detail['updated_at'] ) ) {
			echo '<span class="meta">' . esc_html( sprintf( __( 'Mise à jour le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['updated_at'] ) ) ) . '</span>';
		}

		if ( ! empty( $detail['reopened_at'] ) ) {
			echo '<span class="meta failed">' . esc_html( sprintf( __( 'Reprise le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['reopened_at'] ) ) ) . '</span>';
		}
	}

	private static function render_pdf_quiz_score_for_lesson( $user_id, $lesson_id ) {
		$score = self::get_lesson_quiz_score_percent( $user_id, $lesson_id );
		if ( '' === $score ) {
			return;
		}

		echo '<span class="meta quiz-score">' . esc_html( sprintf( __( 'Quiz : %s%%', 'pickles-sensei-group-progress-tracker' ), $score ) ) . '</span>';
	}

	private static function get_lesson_quiz_score_percent( $user_id, $lesson_id ) {
		static $scores = array();

		$user_id = absint( $user_id );
		$lesson_id = absint( $lesson_id );
		if ( ! $user_id || ! $lesson_id ) {
			return '';
		}

		$cache_key = $user_id . ':' . $lesson_id;
		if ( array_key_exists( $cache_key, $scores ) ) {
			return $scores[ $cache_key ];
		}

		$scores[ $cache_key ] = '';
		$quiz_id = self::get_lesson_quiz_id( $lesson_id );
		if ( ! $quiz_id ) {
			return $scores[ $cache_key ];
		}

		$comments = get_comments(
			array(
				'post_id'           => $quiz_id,
				'user_id'           => $user_id,
				'type'              => 'sensei_quiz_status',
				'number'            => 1,
				'status'            => 'all',
				'orderby'           => 'comment_date_gmt',
				'order'             => 'DESC',
				'no_found_rows'     => true,
				'update_meta_cache' => true,
			)
		);

		if ( empty( $comments ) ) {
			return $scores[ $cache_key ];
		}

		$comment = $comments[0];
		foreach ( array( 'percent', 'complete', 'grade', 'score', '_quiz_grade', '_sensei_score' ) as $meta_key ) {
			$value = get_comment_meta( $comment->comment_ID, $meta_key, true );
			if ( '' !== $value && is_numeric( $value ) ) {
				$scores[ $cache_key ] = (string) max( 0, min( 100, round( (float) $value ) ) );
				return $scores[ $cache_key ];
			}
		}

		if ( self::is_complete_status( $comment->comment_approved ) ) {
			$scores[ $cache_key ] = '100';
		}

		return $scores[ $cache_key ];
	}

	private static function get_lesson_quiz_id( $lesson_id ) {
		static $quiz_ids = array();

		$lesson_id = absint( $lesson_id );
		if ( ! $lesson_id ) {
			return 0;
		}

		if ( array_key_exists( $lesson_id, $quiz_ids ) ) {
			return $quiz_ids[ $lesson_id ];
		}

		$quiz_ids[ $lesson_id ] = 0;
		$ids = get_posts(
			array(
				'post_type'      => 'quiz',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_quiz_lesson',
				'meta_value'     => $lesson_id,
			)
		);

		if ( empty( $ids ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'quiz',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'post_parent'    => $lesson_id,
				)
			);
		}

		if ( ! empty( $ids ) ) {
			$quiz_ids[ $lesson_id ] = absint( $ids[0] );
			return $quiz_ids[ $lesson_id ];
		}

		if ( function_exists( 'Sensei' ) && isset( Sensei()->lesson ) && method_exists( Sensei()->lesson, 'lesson_quizzes' ) ) {
			$sensei_quizzes = Sensei()->lesson->lesson_quizzes( $lesson_id );
			if ( ! empty( $sensei_quizzes ) ) {
				$first_quiz = is_array( $sensei_quizzes ) ? reset( $sensei_quizzes ) : $sensei_quizzes;
				$quiz_ids[ $lesson_id ] = isset( $first_quiz->ID ) ? absint( $first_quiz->ID ) : absint( $first_quiz );
			}
		}

		return $quiz_ids[ $lesson_id ];
	}

	private static function get_user_lesson_status_details( $user_id, $lesson_ids ) {
		global $wpdb;

		$lesson_ids = array_values( array_filter( array_map( 'absint', $lesson_ids ) ) );
		if ( empty( $lesson_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $lesson_ids ), '%d' ) );
		$params = array_merge( array( absint( $user_id ) ), $lesson_ids );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_post_ID, comment_approved, comment_date, comment_date_gmt
				FROM {$wpdb->comments}
				WHERE user_id = %d
					AND comment_type = 'sensei_lesson_status'
					AND comment_post_ID IN ($placeholders)
				ORDER BY comment_date_gmt ASC",
				$params
			)
		);

		$details = array();
		foreach ( $rows as $row ) {
			$lesson_id = absint( $row->comment_post_ID );
			$first_completed = self::get_comment_meta_with_legacy_fallback( $row->comment_ID, self::META_FIRST_COMPLETED_AT, self::LEGACY_META_FIRST_COMPLETED_AT );
			$reopened_at = self::get_comment_meta_with_legacy_fallback( $row->comment_ID, self::META_REOPENED_AT, self::LEGACY_META_REOPENED_AT );

			if ( ! $first_completed && self::is_complete_status( $row->comment_approved ) ) {
				$first_completed = $row->comment_date;
			}

			if ( empty( $details[ $lesson_id ] ) ) {
				$details[ $lesson_id ] = array(
					'started_at'         => (string) get_comment_meta( $row->comment_ID, 'start', true ), // Début de la leçon noté par Sensei.
					'status'             => $row->comment_approved,
					'updated_at'         => $row->comment_date,
					'first_completed_at' => $first_completed,
					'reopened_at'        => $reopened_at,
				);
				continue;
			}

			if ( $first_completed && ( empty( $details[ $lesson_id ]['first_completed_at'] ) || strtotime( $first_completed ) < strtotime( $details[ $lesson_id ]['first_completed_at'] ) ) ) {
				$details[ $lesson_id ]['first_completed_at'] = $first_completed;
			}

			if ( $reopened_at && ( empty( $details[ $lesson_id ]['reopened_at'] ) || strtotime( $reopened_at ) > strtotime( $details[ $lesson_id ]['reopened_at'] ) ) ) {
				$details[ $lesson_id ]['reopened_at'] = $reopened_at;
			}

			$details[ $lesson_id ]['status'] = $row->comment_approved;
			$details[ $lesson_id ]['updated_at'] = $row->comment_date;
		}

		return $details;
	}

	private static function get_comment_meta_with_legacy_fallback( $comment_id, $meta_key, $legacy_meta_key ) {
		$value = get_comment_meta( $comment_id, $meta_key, true );
		if ( '' !== $value && null !== $value ) {
			return $value;
		}

		return get_comment_meta( $comment_id, $legacy_meta_key, true );
	}

	public static function remember_lesson_status_dates( $new_status, $old_status, $comment ) {
		if ( ! $comment || 'sensei_lesson_status' !== $comment->comment_type ) {
			return;
		}

		self::maybe_store_status_dates( $comment->comment_ID, $new_status, $old_status );
	}

	public static function remember_new_lesson_status_dates( $comment_id, $approved, $commentdata ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment || 'sensei_lesson_status' !== $comment->comment_type ) {
			return;
		}

		self::maybe_store_status_dates( $comment_id, $comment->comment_approved, '' );
	}

	public static function remember_edited_lesson_status_dates( $comment_id, $commentdata = null ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment || 'sensei_lesson_status' !== $comment->comment_type ) {
			return;
		}

		self::maybe_store_status_dates( $comment_id, $comment->comment_approved, '' );
	}

	private static function maybe_store_status_dates( $comment_id, $new_status, $old_status ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		$now = current_time( 'mysql' );

		if ( self::is_complete_status( $new_status ) && ! get_comment_meta( $comment_id, self::META_FIRST_COMPLETED_AT, true ) ) {
			update_comment_meta( $comment_id, self::META_FIRST_COMPLETED_AT, $comment->comment_date ? $comment->comment_date : $now );
		}

		if ( self::is_complete_status( $old_status ) && ! self::is_complete_status( $new_status ) ) {
			update_comment_meta( $comment_id, self::META_REOPENED_AT, $now );
		}
	}

	private static function calculate_progress( $statuses, $lesson_ids ) {
		$total = count( $lesson_ids );
		if ( ! $total ) {
			return 0;
		}

		$complete = 0;
		foreach ( $lesson_ids as $lesson_id ) {
			if ( ! empty( $statuses[ $lesson_id ]['status'] ) && self::is_complete_status( $statuses[ $lesson_id ]['status'] ) ) {
				$complete++;
			}
		}

		return (int) round( ( $complete / $total ) * 100 );
	}

	private static function get_inactive_students_for_group( $group_id, $days ) {
		$student_ids = self::get_group_student_ids( $group_id );
		$cutoff = current_time( 'timestamp' ) - ( max( 1, absint( $days ) ) * DAY_IN_SECONDS );
		$alerts = array();

		foreach ( $student_ids as $student_id ) {
			$student = get_userdata( $student_id );
			if ( ! $student ) {
				continue;
			}

			$course_activity = self::get_user_course_activity( $student_id );
			$modules = array();

			foreach ( $course_activity as $course_id => $activity ) {
				$course = get_post( $course_id );
				if ( ! $course || 'publish' !== $course->post_status ) {
					continue;
				}

				$last_timestamp = strtotime( $activity['last_date'] );
				if ( ! $last_timestamp || $last_timestamp >= $cutoff ) {
					continue;
				}

				$lessons = self::get_course_lessons( $course_id );
				if ( empty( $lessons ) ) {
					continue;
				}

				$lesson_ids = wp_list_pluck( $lessons, 'ID' );
				$statuses = self::get_user_lesson_status_details( $student_id, $lesson_ids );
				$progress = self::calculate_progress( $statuses, $lesson_ids );
				if ( $progress >= 100 ) {
					continue;
				}

				$modules[] = array(
					'course_id'     => $course_id,
					'course_title'  => $course->post_title,
					'last_date'     => $activity['last_date'],
					'last_ts'       => $last_timestamp,
					'last_label'    => self::format_inactivity_label( $activity['last_date'] ),
					'progress'      => $progress,
				);
			}

			if ( empty( $modules ) ) {
				continue;
			}

			usort(
				$modules,
				function ( $a, $b ) {
					if ( $a['last_ts'] === $b['last_ts'] ) {
						return strnatcasecmp( $a['course_title'], $b['course_title'] );
					}

					return $a['last_ts'] < $b['last_ts'] ? -1 : 1;
				}
			);

			$alerts[] = array(
				'student' => $student,
				'modules' => $modules,
			);
		}

		usort(
			$alerts,
			function ( $a, $b ) {
				return strnatcasecmp( $a['student']->display_name, $b['student']->display_name );
			}
		);

		return $alerts;
	}

	private static function get_user_course_activity( $user_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_post_ID, comment_date
				FROM {$wpdb->comments}
				WHERE user_id = %d
					AND comment_type = 'sensei_lesson_status'
				ORDER BY comment_date_gmt DESC, comment_ID DESC",
				absint( $user_id )
			)
		);

		$activity = array();
		foreach ( $rows as $row ) {
			$course_id = self::get_lesson_course_id( (int) $row->comment_post_ID );
			if ( ! $course_id || isset( $activity[ $course_id ] ) ) {
				continue;
			}

			$activity[ $course_id ] = array(
				'last_date' => $row->comment_date,
			);
		}

		return $activity;
	}

	private static function get_lesson_course_id( $lesson_id ) {
		static $cache = array();

		$lesson_id = absint( $lesson_id );
		if ( isset( $cache[ $lesson_id ] ) ) {
			return $cache[ $lesson_id ];
		}

		if ( function_exists( 'Sensei' ) && isset( Sensei()->lesson ) && method_exists( Sensei()->lesson, 'get_course_id' ) ) {
			$course_id = absint( Sensei()->lesson->get_course_id( $lesson_id ) );
			if ( $course_id ) {
				$cache[ $lesson_id ] = $course_id;
				return $course_id;
			}
		}

		$course_id = absint( get_post_meta( $lesson_id, '_lesson_course', true ) );
		if ( $course_id ) {
			$cache[ $lesson_id ] = $course_id;
			return $course_id;
		}

		$cache[ $lesson_id ] = self::get_lesson_course_id_from_order( $lesson_id );
		return $cache[ $lesson_id ];
	}

	private static function get_lesson_course_id_from_order( $lesson_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value
				FROM {$wpdb->postmeta}
				WHERE meta_key = '_lesson_order' AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( (string) absint( $lesson_id ) ) . '%'
			)
		);

		foreach ( $rows as $row ) {
			$lesson_ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $row->meta_value ) ) ) );
			if ( in_array( absint( $lesson_id ), $lesson_ids, true ) ) {
				return absint( $row->post_id );
			}
		}

		return 0;
	}

	private static function format_inactivity_label( $last_date ) {
		$timestamp = strtotime( $last_date );
		if ( ! $timestamp ) {
			return __( 'Aucune activite detectee', 'pickles-sensei-group-progress-tracker' );
		}

		$days = max( 1, (int) floor( ( current_time( 'timestamp' ) - $timestamp ) / DAY_IN_SECONDS ) );

		return sprintf(
			__( '%1$s jours sans activite - derniere activite le %2$s', 'pickles-sensei-group-progress-tracker' ),
			number_format_i18n( $days ),
			self::format_datetime( $last_date )
		);
	}

	private static function get_revision_history_for_students( $student_ids ) {
		global $wpdb;

		$source = self::get_revision_results_source();
		if ( empty( $source['table'] ) || empty( $source['columns']['user_id'] ) || empty( $source['columns']['modules'] ) || empty( $source['columns']['finished_at'] ) ) {
			return array();
		}

		$student_ids = array_values( array_filter( array_map( 'absint', (array) $student_ids ) ) );
		if ( empty( $student_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
		$table = $source['table'];
		$columns = $source['columns'];
		$user_column = self::quote_identifier( $columns['user_id'] );
		$modules_column = self::quote_identifier( $columns['modules'] );
		$finished_column = self::quote_identifier( $columns['finished_at'] );
		$progress_select = ! empty( $columns['progress'] ) ? self::quote_identifier( $columns['progress'] ) : "''";
		$status_select = ! empty( $columns['status'] ) ? self::quote_identifier( $columns['status'] ) : "'completed'";
		$percent_select = ! empty( $columns['percent'] ) ? self::quote_identifier( $columns['percent'] ) : '0';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$user_column} AS user_id,
					{$modules_column} AS modules,
					{$percent_select} AS percent,
					{$status_select} AS status,
					{$progress_select} AS progress,
					{$finished_column} AS finished_at
				FROM {$table}
				WHERE {$user_column} IN ($placeholders)
				ORDER BY {$finished_column} DESC
				LIMIT 120",
				$student_ids
			)
		);

		$history = array();
		foreach ( $rows as $row ) {
			$user_id = absint( $row->user_id );
			if ( ! isset( $history[ $user_id ] ) ) {
				$history[ $user_id ] = array();
			}

			if ( count( $history[ $user_id ] ) >= 5 ) {
				continue;
			}

			$history[ $user_id ][] = $row;
		}

		return $history;
	}

	private static function get_revision_results_source() {
		global $wpdb;

		$candidates = array(
			$wpdb->prefix . 'logiprof_revision_results',
			$wpdb->prefix . 'logiprof_revisions_results',
			$wpdb->prefix . 'logiprof_revision_multimodules_results',
			$wpdb->prefix . 'logiprof_revisions_multimodules_results',
		);

		$like_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%revision%result%' ) );
		$candidates = array_values( array_unique( array_merge( $candidates, (array) $like_tables ) ) );

		foreach ( $candidates as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				continue;
			}

			$columns = self::get_revision_results_columns( $table );
			if ( ! empty( $columns['user_id'] ) && ! empty( $columns['modules'] ) && ! empty( $columns['finished_at'] ) ) {
				return array(
					'table'   => self::quote_identifier( $table ),
					'columns' => $columns,
				);
			}
		}

		return array();
	}

	private static function get_revision_results_columns( $table ) {
		global $wpdb;

		$raw_columns = $wpdb->get_col( 'DESCRIBE ' . self::quote_identifier( $table ), 0 );
		$raw_columns = array_values( array_filter( array_map( 'sanitize_key', (array) $raw_columns ) ) );
		if ( empty( $raw_columns ) ) {
			return array();
		}

		return array(
			'user_id'     => self::find_first_column( $raw_columns, array( 'user_id', 'student_id', 'learner_id', 'eleve_id' ) ),
			'modules'     => self::find_first_column( $raw_columns, array( 'modules', 'selected_modules', 'module', 'module_code', 'module_codes', 'revision_modules' ) ),
			'progress'    => self::find_first_column( $raw_columns, array( 'progress', 'session_progress' ) ),
			'percent'     => self::find_first_column( $raw_columns, array( 'percent', 'score_percent', 'success_percent', 'percentage', 'score' ) ),
			'status'      => self::find_first_column( $raw_columns, array( 'status', 'session_status' ) ),
			'finished_at' => self::find_first_column( $raw_columns, array( 'finished_at', 'completed_at', 'ended_at', 'created_at', 'updated_at', 'date', 'session_date' ) ),
		);
	}

	private static function find_first_column( $columns, $candidates ) {
		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $columns, true ) ) {
				return $candidate;
			}
		}

		return '';
	}

	private static function quote_identifier( $identifier ) {
		return '`' . str_replace( '`', '``', (string) $identifier ) . '`';
	}

	private static function format_revision_modules( $encoded_modules, $encoded_progress = '' ) {
		$modules = json_decode( (string) $encoded_modules, true );
		if ( ( ! is_array( $modules ) || empty( $modules ) ) && $encoded_progress ) {
			$progress = json_decode( (string) $encoded_progress, true );
			if ( is_array( $progress ) && ! empty( $progress['modules'] ) && is_array( $progress['modules'] ) ) {
				$modules = $progress['modules'];
			}
		}

		if ( ! is_array( $modules ) || empty( $modules ) ) {
			return __( 'Modules non renseignes', 'pickles-sensei-group-progress-tracker' );
		}

		$modules = array_values( array_filter( array_map( 'sanitize_text_field', $modules ) ) );
		return implode( ', ', array_slice( $modules, 0, 4 ) );
	}

	private static function format_revision_status( $status ) {
		return 'in_progress' === (string) $status ? __( 'En cours', 'pickles-sensei-group-progress-tracker' ) : __( 'Terminée', 'pickles-sensei-group-progress-tracker' );
	}

	private static function get_default_inactivity_days() {
		return min( 365, max( 1, absint( get_option( self::OPTION_INACTIVITY_DAYS, 7 ) ) ) );
	}

	private static function parse_course_ids( $value ) {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$parts = preg_split( '/[\s,;]+/', (string) $value );
		}

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $parts ) ) ) );
	}

	private static function get_groups() {
		return get_posts(
			array(
				'post_type'      => 'group',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	private static function get_courses() {
		return get_posts(
			array(
				'post_type'      => 'course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	private static function get_group_student_ids( $group_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sensei_pro_groups_students';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array();
		}

		return array_map(
			'absint',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT student_id FROM {$table} WHERE group_id = %d ORDER BY student_id ASC",
					absint( $group_id )
				)
			)
		);
	}

	private static function get_course_lessons( $course_id ) {
		$lesson_ids = self::get_lesson_ids_from_order( $course_id );

		if ( empty( $lesson_ids ) && function_exists( 'Sensei' ) && isset( Sensei()->course ) && method_exists( Sensei()->course, 'course_lessons' ) ) {
			$lesson_ids = array_map( 'absint', (array) Sensei()->course->course_lessons( $course_id, 'publish', 'ids' ) );
		}

		if ( empty( $lesson_ids ) ) {
			$lesson_ids = get_posts(
				array(
					'post_type'      => 'lesson',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_key'       => '_lesson_course',
					'meta_value'     => absint( $course_id ),
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);
		}

		if ( empty( $lesson_ids ) ) {
			return array();
		}

		$lessons = get_posts(
			array(
				'post_type'      => 'lesson',
				'post_status'    => 'publish',
				'post__in'       => $lesson_ids,
				'posts_per_page' => -1,
				'orderby'        => 'post__in',
			)
		);

		return self::sort_lessons_for_report( $lessons, $lesson_ids );
	}

	private static function sort_lessons_for_report( $lessons, $original_order ) {
		$lessons = array_values( array_filter( $lessons, array( __CLASS__, 'should_show_lesson_in_report' ) ) );
		$positions = array_flip( array_map( 'absint', $original_order ) );

		usort(
			$lessons,
			function ( $a, $b ) use ( $positions ) {
				$a_key = self::get_lesson_sort_key( $a, $positions );
				$b_key = self::get_lesson_sort_key( $b, $positions );

				foreach ( $a_key as $index => $a_value ) {
					$b_value = $b_key[ $index ];
					if ( $a_value === $b_value ) {
						continue;
					}

					if ( is_numeric( $a_value ) && is_numeric( $b_value ) ) {
						return $a_value < $b_value ? -1 : 1;
					}

					return strnatcasecmp( (string) $a_value, (string) $b_value );
				}

				return 0;
			}
		);

		return $lessons;
	}

	private static function get_lesson_sort_key( $lesson, $positions ) {
		$title = trim( wp_strip_all_tags( $lesson->post_title ) );
		$normalized = remove_accents( function_exists( 'mb_strtolower' ) ? mb_strtolower( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'UTF-8' ) : strtolower( $title ) );
		$position = isset( $positions[ $lesson->ID ] ) ? (int) $positions[ $lesson->ID ] : 999999;

		if ( preg_match( '/([a-z]+)?\s*([0-9]+)\s*\.\s*([0-9]+)/i', $title, $matches ) ) {
			$prefix = isset( $matches[1] ) ? strtolower( $matches[1] ) : '';
			return array( 10, $prefix, (int) $matches[2], (int) $matches[3], $position, $normalized );
		}

		// Leçon de chapitre (« M103 – 3 Tâches et objectifs… ») : placée juste avant ses sous-leçons 3.1, 3.2…
		$without_code = preg_replace( '/^\s*(?:module\s+)?(?:m\s?\d+[a-z0-9]*)\s*[^a-z0-9]*\s*/i', '', $normalized );
		if ( $without_code !== $normalized && preg_match( '/^([0-9]+)(?![0-9])(?!\s*[.,]\s*[0-9])/', (string) $without_code, $chapter ) ) {
			return array( 10, '', (int) $chapter[1], 0, $position, $normalized );
		}

		if ( false !== strpos( $normalized, 'evaluation globale' ) || false !== strpos( $normalized, 'eval globale' ) ) {
			return array( 20, '', 0, 0, $position, $normalized );
		}

		if ( false !== strpos( $normalized, 'mathematique' ) || false !== strpos( $normalized, 'math' ) ) {
			return array( 30, '', 0, 0, $position, $normalized );
		}

		return array( 40, '', 0, 0, $position, $normalized );
	}

	private static function should_show_lesson_in_report( $lesson ) {
		$normalized = self::normalize_lesson_title_for_filter( isset( $lesson->post_title ) ? $lesson->post_title : '' );
		// Leçon « Objectifs » : seulement quand le titre COMMENCE par « Objectif(s) » (après le code du module,
		// ex. « M103 – Objectifs », « Objectifs du module M101 »). Une leçon de contenu qui parle d'objectifs
		// (« 3 Tâches et objectifs de la logistique », « 3.2 … des objectifs … ») est affichée et comptée.
		$without_code = preg_replace( '/^(?:module\s+)?(?:m\s?\d+[a-z0-9]*\s+)+/', '', $normalized );
		if ( preg_match( '/^objectifs?(?:\s|$)/', (string) $without_code ) ) {
			return false;
		}
		$hidden_terms = array(
			'mathematiques solutions',
			'mathematique solution',
			'podcast',
			'evaluation formative mathematiques telechargeables',
			'evaluation formative mathematique telechargeable',
			'evaluation formative et mathematiques telechargeables',
			'evaluation formative et mathematique telechargeable',
		);

		foreach ( $hidden_terms as $term ) {
			if ( false !== strpos( $normalized, $term ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Règle LogiOne (version RULE_VERSION) : identique à la 3.8.9, avec deux corrections validées
	 * le 10 octobre 2026 : les entités HTML sont décodées et les majuscules accentuées sont
	 * mises en minuscules avant le retrait des accents (« Évaluation » = « évaluation »).
	 */
	private static function normalize_lesson_title_for_filter( $title ) {
		$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = remove_accents( function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title ) );
		$title = str_replace( '&', ' et ', $title );
		$title = preg_replace( '/[^a-z0-9]+/', ' ', $title );
		return trim( preg_replace( '/\\s+/', ' ', (string) $title ) );
	}

	private static function get_lesson_ids_from_order( $course_id ) {
		$lesson_order = (string) get_post_meta( absint( $course_id ), '_lesson_order', true );
		if ( '' === trim( $lesson_order ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', explode( ',', $lesson_order ) ) ) );
	}

	private static function is_complete_status( $status ) {
		return in_array( (string) $status, array( 'complete', 'passed', 'graded' ), true );
	}

	private static function format_status( $status ) {
		if ( self::is_complete_status( $status ) ) {
			return __( 'Terminée', 'pickles-sensei-group-progress-tracker' );
		}

		if ( 'in-progress' === $status ) {
			return __( 'En cours', 'pickles-sensei-group-progress-tracker' );
		}

		if ( 'failed' === $status ) {
			return __( 'À reprendre', 'pickles-sensei-group-progress-tracker' );
		}

		if ( 'ungraded' === $status ) {
			return __( 'Rendue, en attente de correction', 'pickles-sensei-group-progress-tracker' );
		}

		return __( 'Non commencée', 'pickles-sensei-group-progress-tracker' );
	}

	/* ---------------------------------------------------------------------
	 * API de lecture pour LogiOne : même règle de sélection que les écrans.
	 * ------------------------------------------------------------------- */

	/** Leçons du cours partagées entre éligibles et exclues, dans l'ordre d'affichage. */
	public static function api_course_lessons( $course_id ) {
		$lesson_ids = self::get_lesson_ids_from_order( $course_id );
		if ( empty( $lesson_ids ) && function_exists( 'Sensei' ) && isset( Sensei()->course ) && method_exists( Sensei()->course, 'course_lessons' ) ) {
			$lesson_ids = array_map( 'absint', (array) Sensei()->course->course_lessons( $course_id, 'publish', 'ids' ) );
		}
		if ( empty( $lesson_ids ) ) {
			$lesson_ids = get_posts( array( 'post_type' => 'lesson', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_lesson_course', 'meta_value' => absint( $course_id ), 'orderby' => 'menu_order', 'order' => 'ASC', 'fields' => 'ids' ) );
		}
		$eligible = array();
		$excluded = array();
		$unpublished = array();
		if ( $lesson_ids ) {
			$all = get_posts( array( 'post_type' => 'lesson', 'post_status' => 'any', 'post__in' => $lesson_ids, 'posts_per_page' => -1, 'orderby' => 'post__in' ) );
			$published = array();
			foreach ( $all as $lesson ) {
				if ( 'publish' !== $lesson->post_status ) {
					$unpublished[ (int) $lesson->ID ] = $lesson->post_title;
					continue;
				}
				$published[] = $lesson;
				if ( ! self::should_show_lesson_in_report( $lesson ) ) {
					$excluded[ (int) $lesson->ID ] = $lesson->post_title;
				}
			}
			foreach ( self::sort_lessons_for_report( $published, $lesson_ids ) as $lesson ) {
				$eligible[ (int) $lesson->ID ] = $lesson->post_title;
			}
		}
		return array( 'eligible' => $eligible, 'excluded' => $excluded, 'unpublished' => $unpublished );
	}

	/** null si la source des groupes Sensei est indisponible (distinct d'un groupe vide). */
	public static function api_group_students( $group_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sensei_pro_groups_students';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return null;
		}
		return self::get_group_student_ids( $group_id );
	}

	public static function api_statuses( $user_id, $lesson_ids ) {
		return self::get_user_lesson_status_details( $user_id, $lesson_ids );
	}

	public static function api_is_complete_status( $status ) {
		return self::is_complete_status( $status );
	}

	public static function api_groups() {
		return self::get_groups();
	}

	public static function api_courses() {
		return self::get_courses();
	}

	public static function api_lesson_excluded_title( $title ) {
		return ! self::should_show_lesson_in_report( (object) array( 'post_title' => $title ) );
	}

	private static function format_datetime( $mysql_date ) {
		$mysql_date = trim( (string) $mysql_date );
		if ( '' === $mysql_date || '0000-00-00 00:00:00' === $mysql_date ) {
			return '';
		}

		$formatted = mysql2date( get_option( 'date_format' ) . ' H:i', $mysql_date, true );
		if ( $formatted ) {
			return $formatted;
		}

		$timestamp = strtotime( $mysql_date );
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' H:i', $timestamp ) : '';
	}
}

if ( defined( 'LOGIPILOT_PROGRESS_HOOKS_ONLY' ) && LOGIPILOT_PROGRESS_HOOKS_ONLY ) {
	LogiProgress_Sensei_Group_Progress_Tracker::init_hooks_only();
} else {
	LogiProgress_Sensei_Group_Progress_Tracker::init();
}
