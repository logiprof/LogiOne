<?php
/**
 * Module LogiLive de LogiOne (code issu de LogiLive 1.0.0).
 * Suivi en direct de la classe : élèves connectés, leçon Sensei ouverte et dernière activité, par groupe classe.
 *
 * - Accès : permission LogiOne « lp_live_view » (écran Permissions) ; rien n'est écrit dans les rôles WordPress.
 * - Mêmes données que LogiLive et que l'ancien « Logiprof Sensei Live Class Activity » (métadonnées _lslca_*).
 * - Version test : lecture seule (aucun enregistrement d'activité).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiOne_LogiLive {
	const VERSION        = '1.2.0'; // 1.38.1 : nouveau script (moins de requêtes) — force le rechargement
	const ADMIN_PAGE     = 'logilive';
	const SHORTCODE      = 'logilive';
	const OLD_SHORTCODE  = 'logiprof_sensei_live_activity';
	const ACCESS_CAP     = 'lp_live_view';
	const AJAX_ACTION    = 'logilive_refresh';
	const PING_ACTION    = 'logilive_ping';
	const NONCE_ACTION   = 'logilive_refresh';
	const PING_NONCE     = 'logilive_ping';
	const HANDLE         = 'logilive';
	const PING_HANDLE    = 'logilive-ping';

	/* Mêmes clés que l'ancien plugin : l'historique est conservé. */
	const META_LAST_SEEN       = '_lslca_last_seen';
	const META_LAST_URL        = '_lslca_last_url';
	const META_LAST_TITLE      = '_lslca_last_title';
	const META_CURRENT_LESSON  = '_lslca_current_lesson_id';
	const META_CURRENT_COURSE  = '_lslca_current_course_id';
	const META_CURRENT_CONTEXT = '_lslca_current_context';
	const META_TRACKED_AT      = '_lslca_tracked_at';
	const META_SENSEI_LESSON   = '_lslca_last_sensei_lesson_id';
	const META_SENSEI_COURSE   = '_lslca_last_sensei_course_id';
	const META_SENSEI_AT       = '_lslca_last_sensei_at';
	const META_LAST_LOGIN      = '_lslca_last_login';
	/* Suivi plus fin (LogiOne 1.17) : page ouverte, fermeture de la page, dernières actions (type seulement). */
	const META_PAGE_TOKEN      = '_lslca_page_token';
	const META_LEFT_AT         = '_lslca_left_at';
	const META_ACTIONS         = '_lslca_actions';
	const META_NOTICE_SEEN     = '_lslca_notice_seen';
	const OPTION               = 'logilive_settings';
	const PURGE_HOOK           = 'logilive_purge_actions';
	const LIVE_SECONDS         = 90;       // Signal reçu il y a moins de 90 s : l'élève est là (signal toutes les 30 s).
	const PING_MIN_GAP         = 12;       // 1.38.1 : deux signaux de présence du même élève à moins de 12 s → le 2e ne réécrit rien.
	const RETENTION            = DAY_IN_SECONDS; // Les actions sont effacées après 24 h.
	const MAX_ACTIONS          = 8;

	/** Actions signalées par le navigateur. Seul le type est gardé : jamais le texte copié ni ce qui est tapé. */
	const ACTIONS = array(
		'scroll'      => 'Fait défiler la page',
		'click'       => 'A cliqué dans la page',
		'copy'        => 'A copié du texte',
		'video_play'  => 'Regarde une vidéo',
		'video_pause' => 'A mis la vidéo en pause',
		'video_end'   => 'A fini la vidéo',
		'embed'       => 'Utilise un contenu intégré (vidéo, exercice…)',
		'hidden'      => 'A quitté l’onglet',
		'visible'     => 'Est revenu sur l’onglet',
		'idle'        => 'Inactif (aucun geste)',
		'active'      => 'De nouveau actif',
		'leave'       => 'A fermé ou quitté la page',
	);

	private static $payload = null;

	private static $assets_loaded   = false;
	private static $revision_source = null;
	private static $old_plugin      = false;

	public static function init() {
		if ( did_action( 'plugins_loaded' ) ) {
			self::boot();
		} else {
			add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 20 );
		}
	}

	public static function boot() {
		// Si l'ancien plugin est encore actif, il continue d'enregistrer l'activité (mêmes données) :
		// LogiLive n'enregistre rien en double et affiche un rappel pour le désactiver.
		self::$old_plugin = class_exists( 'Logiprof_Sensei_Live_Class_Activity' );

		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_refresh_activity' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );

		if ( defined( 'LOGIPILOT_TEST_MODE' ) ) {
			return; // Version test : lecture seule, aucune activité n'est enregistrée.
		}

		if ( self::$old_plugin ) {
			add_action( 'admin_notices', array( __CLASS__, 'old_plugin_notice' ) );
			return;
		}

		add_action( 'admin_post_logilive_settings', array( __CLASS__, 'handle_settings' ) );
		// L'ancien shortcode continue de fonctionner : les pages existantes n'ont pas à être modifiées.
		add_shortcode( self::OLD_SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_login', array( __CLASS__, 'remember_user_login' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'track_current_user_activity' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracking_ping' ), 30 );
		add_action( 'wp_ajax_' . self::PING_ACTION, array( __CLASS__, 'ajax_track_ping' ) );
		add_action( self::PURGE_HOOK, array( __CLASS__, 'purge_old_actions' ) );
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::PURGE_HOOK );
		}
	}

	/* ── Réglages : suivi des actions, avis aux élèves ──────────── */

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		return array(
			'actions' => isset( $s['actions'] ) ? (int) $s['actions'] : 1,
			'notice'  => isset( $s['notice'] ) ? (int) $s['notice'] : 1,
		);
	}

	public static function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logilive' ), 403 );
		}
		check_admin_referer( 'logilive_settings' );
		$new = array(
			'actions' => empty( $_POST['logilive_actions'] ) ? 0 : 1,
			'notice'  => empty( $_POST['logilive_notice'] ) ? 0 : 1,
		);
		update_option( self::OPTION, $new, false );
		if ( ! $new['actions'] ) {
			self::purge_old_actions( true ); // Suivi coupé : les actions déjà notées sont effacées tout de suite.
		}
		wp_safe_redirect( add_query_arg( 'llv_saved', 1, admin_url( 'admin.php?page=' . self::ADMIN_PAGE ) ) );
		exit;
	}

	/** Efface les actions de plus de 24 h (ou toutes). Ne touche pas aux autres données de LogiLive. */
	public static function purge_old_actions( $all = false ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", self::META_ACTIONS ) );
		foreach ( (array) $rows as $row ) {
			$kept = $all ? array() : self::fresh_actions( json_decode( (string) $row->meta_value, true ) );
			if ( $kept ) {
				update_user_meta( (int) $row->user_id, self::META_ACTIONS, wp_json_encode( $kept ) );
			} else {
				delete_user_meta( (int) $row->user_id, self::META_ACTIONS );
			}
		}
	}

	private static function fresh_actions( $list ) {
		$out = array();
		foreach ( is_array( $list ) ? $list : array() as $a ) {
			if ( is_array( $a ) && isset( $a['t'], $a['at'], self::ACTIONS[ $a['t'] ] ) && ( time() - (int) $a['at'] ) < self::RETENTION ) {
				$out[] = array( 't' => (string) $a['t'], 'v' => (int) ( $a['v'] ?? 0 ), 'at' => (int) $a['at'] );
			}
		}
		return array_slice( $out, -self::MAX_ACTIONS );
	}

	private static function get_actions( $user_id ) {
		return self::fresh_actions( json_decode( (string) get_user_meta( $user_id, self::META_ACTIONS, true ), true ) );
	}

	private static function record_action( $user_id, $type, $value ) {
		$list = self::get_actions( $user_id );
		$last = end( $list );
		// Défilement continu : une seule ligne, mise à jour (pas une ligne par mouvement).
		if ( $last && $last['t'] === $type && in_array( $type, array( 'scroll', 'click' ), true ) ) {
			array_pop( $list );
		}
		$list[] = array( 't' => $type, 'v' => max( 0, min( 100, (int) $value ) ), 'at' => time() );
		update_user_meta( $user_id, self::META_ACTIONS, wp_json_encode( array_slice( $list, -self::MAX_ACTIONS ) ) );
	}

	public static function action_label( $action ) {
		$label = self::ACTIONS[ $action['t'] ] ?? '';
		if ( 'scroll' === $action['t'] && $action['v'] ) {
			/* translators: %d: pourcentage de la page */
			$label .= ' · ' . sprintf( __( '%d %% de la page', 'logilive' ), $action['v'] );
		}
		return $label;
	}

	private static function short_ago( $timestamp ) {
		$diff = max( 0, time() - (int) $timestamp );
		if ( $diff < 10 ) {
			return __( 'à l’instant', 'logilive' );
		}
		if ( $diff < MINUTE_IN_SECONDS ) {
			/* translators: %d: secondes */
			return sprintf( __( 'il y a %d s', 'logilive' ), $diff );
		}
		if ( $diff < HOUR_IN_SECONDS ) {
			/* translators: %d: minutes */
			return sprintf( __( 'il y a %d min', 'logilive' ), floor( $diff / MINUTE_IN_SECONDS ) );
		}
		return wp_date( 'H:i', (int) $timestamp, wp_timezone() );
	}

	public static function old_plugin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>LogiLive</strong> : '
			. esc_html__( "l'ancien plugin « Logiprof Sensei Live Class Activity » est encore actif. Désactivez-le : LogiLive reprend ses données et son shortcode.", 'logilive' )
			. '</p></div>';
	}

	/* ── Droits d'accès ─────────────────────────────────────────── */

	/** Accès : permission LogiOne « Voir l'activité en direct ». Le droit demandé ne vient jamais du navigateur. */
	private static function current_user_can_access() {
		return is_user_logged_in() && current_user_can( self::ACCESS_CAP );
	}

	/* ── Administration ─────────────────────────────────────────── */

	public static function register_admin_page() {
		add_menu_page(
			'LogiLive',
			'LogiLive',
			self::ACCESS_CAP,
			self::ADMIN_PAGE,
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-visibility',
			4
		);
	}

	public static function render_admin_page() {
		if ( ! self::current_user_can_access() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logilive' ) );
		}

		self::enqueue_assets();
		?>
		<div class="wrap llv-admin">
			<img class="llv-admin-logo" src="<?php echo esc_url( plugins_url( 'assets/logilive-logo.svg', __FILE__ ) ); ?>" alt="LogiLive by LogiProf" width="261" height="80">
			<h1><?php esc_html_e( 'Activité de la classe en direct', 'logilive' ); ?></h1>
			<p class="llv-admin-lead"><?php esc_html_e( 'LogiLive affiche, pour un groupe classe Sensei, les élèves connectés, la leçon ou la page ouverte et leur dernière activité connue.', 'logilive' ); ?></p>

			<div class="llv-admin-cards">
				<div class="llv-admin-card">
					<h2><?php esc_html_e( 'Shortcodes', 'logilive' ); ?></h2>
					<p><code>[logilive]</code> — <?php esc_html_e( 'avec le choix du groupe classe.', 'logilive' ); ?></p>
					<p><code>[logilive group_id="123" show_filters="no"]</code> — <?php esc_html_e( 'fixé sur un groupe précis.', 'logilive' ); ?></p>
					<p class="description"><?php esc_html_e( "L'ancien shortcode [logiprof_sensei_live_activity] fonctionne toujours.", 'logilive' ); ?></p>
				</div>
				<div class="llv-admin-card">
					<h2><?php esc_html_e( 'Réglages par défaut', 'logilive' ); ?></h2>
					<ul>
						<li><?php esc_html_e( 'Actualisation : toutes les 15 secondes (refresh_seconds, minimum 10).', 'logilive' ); ?></li>
						<li><?php esc_html_e( 'Connecté : activité dans les 5 dernières minutes (online_minutes).', 'logilive' ); ?></li>
						<li><?php esc_html_e( 'Heures au format 24 h, selon le fuseau horaire de WordPress.', 'logilive' ); ?></li>
					</ul>
				</div>
				<?php if ( current_user_can( 'manage_options' ) && ! defined( 'LOGIPILOT_TEST_MODE' ) ) : $settings = self::settings(); ?>
				<div class="llv-admin-card">
					<h2><?php esc_html_e( 'Suivi des actions', 'logilive' ); ?></h2>
					<?php if ( ! empty( $_GET['llv_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<p class="llv-saved"><?php esc_html_e( 'Réglages enregistrés.', 'logilive' ); ?></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'logilive_settings' ); ?>
						<input type="hidden" name="action" value="logilive_settings">
						<p><label><input type="checkbox" name="logilive_actions" value="1" <?php checked( $settings['actions'] ); ?>> <?php esc_html_e( 'Afficher la dernière action de chaque élève (défilement, clic, vidéo, copie, onglet quitté, inactivité)', 'logilive' ); ?></label></p>
						<p><label><input type="checkbox" name="logilive_notice" value="1" <?php checked( $settings['notice'] ); ?>> <?php esc_html_e( 'Informer les élèves une fois, par un petit message, de ce que voit l’enseignant', 'logilive' ); ?></label></p>
						<p class="description"><?php esc_html_e( 'Seul le type d’action est noté : jamais le texte copié, ni ce que l’élève tape. Les actions sont effacées après 24 heures, et toutes tout de suite si vous coupez ce suivi.', 'logilive' ); ?></p>
						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'logilive' ); ?></button></p>
					</form>
				</div>
				<?php endif; ?>
			</div>

			<h2 class="llv-admin-preview-title"><?php esc_html_e( 'Aperçu', 'logilive' ); ?></h2>
			<?php
			echo self::render_app( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans render_app.
				array(
					'group_id'        => 0,
					'show_filters'    => 'yes',
					'filter_id'       => 'admin',
					'refresh_seconds' => 15,
					'online_minutes'  => 5,
					'empty_text'      => __( 'Choisissez un groupe classe pour afficher les élèves.', 'logilive' ),
					'class'           => '',
				)
			);
			?>
		</div>
		<?php
	}

	/* ── Styles et scripts ──────────────────────────────────────── */

	public static function register_assets() {
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		wp_register_style( self::HANDLE, plugins_url( 'assets/logilive.css', __FILE__ ), array(), self::VERSION );
		wp_register_script( self::HANDLE, plugins_url( 'assets/logilive.js', __FILE__ ), array(), self::VERSION, true );
		wp_register_script( self::PING_HANDLE, plugins_url( 'assets/logilive-ping.js', __FILE__ ), array(), self::VERSION . '.' . (int) @filemtime( __DIR__ . '/assets/logilive-ping.js' ), true );
	}

	/** Chargés uniquement là où le tableau est affiché. */
	private static function enqueue_assets() {
		if ( self::$assets_loaded ) {
			return;
		}
		self::register_assets();
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
		wp_localize_script(
			self::HANDLE,
			'LogiLiveConfig',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'action'  => self::AJAX_ACTION,
				'i18n'    => array(
					'live'     => __( 'En direct', 'logilive' ),
					'updated'  => __( 'Actualisé à %s', 'logilive' ),
					'paused'   => __( 'En pause (onglet masqué)', 'logilive' ),
					'offline'  => __( 'Connexion perdue, nouvel essai…', 'logilive' ),
					'loading'  => __( 'Actualisation…', 'logilive' ),
					'noMatch'  => __( 'Aucun élève ne correspond à ce filtre.', 'logilive' ),
				),
			)
		);
		self::$assets_loaded = true;
	}

	/** Petit script d'activité, pour tout utilisateur connecté (élève compris). Ne contient aucun accès au tableau. */
	public static function enqueue_tracking_ping() {
		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}
		// 1.38.1 : les enseignants et administrateurs ne sont pas suivis : pas de signal (moins de requêtes).
		if ( self::current_user_can_access() || current_user_can( 'manage_options' ) ) {
			return;
		}
		self::register_assets();
		wp_enqueue_script( self::PING_HANDLE );
		$settings = self::settings();
		$notice   = '';
		if ( $settings['notice'] && ! self::current_user_can_access() && ! get_user_meta( get_current_user_id(), self::META_NOTICE_SEEN, true ) ) {
			$notice = $settings['actions']
				? __( 'Pendant les cours, ton enseignant voit la page que tu as ouverte et ta dernière action (défilement, vidéo, copie, onglet quitté). Jamais ce que tu écris ni ce que tu copies.', 'logilive' )
				: __( 'Pendant les cours, ton enseignant voit la page que tu as ouverte. Jamais ce que tu écris.', 'logilive' );
		}
		wp_localize_script(
			self::PING_HANDLE,
			'LogiLivePing',
			array_merge(
				self::get_current_tracking_payload(),
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::PING_NONCE ),
					'action'  => self::PING_ACTION,
					'actions' => $settings['actions'] ? 1 : 0,
					'notice'  => $notice,
					'noticeOk' => __( 'OK, compris', 'logilive' ),
				)
			)
		);
	}

	/* ── Enregistrement de l'activité ───────────────────────────── */

	public static function remember_user_login( $user_login, $user ) {
		if ( $user && ! empty( $user->ID ) ) {
			update_user_meta( absint( $user->ID ), self::META_LAST_LOGIN, time() );
		}
	}

	public static function track_current_user_activity() {
		if ( ! is_user_logged_in() || is_admin() || wp_doing_ajax() || is_feed() || is_robots() || is_trackback() || is_404() ) {
			return;
		}

		$payload = self::get_current_tracking_payload();
		self::store_activity( get_current_user_id(), $payload['context'], $payload['lessonId'], $payload['courseId'], $payload['title'], $payload['url'], $payload['token'] );
	}

	public static function ajax_track_ping() {
		check_ajax_referer( self::PING_NONCE, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Utilisateur non connecté.', 'logilive' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- vérifié ci-dessus.
		$context   = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( wp_unslash( $_POST['lesson_id'] ) ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0;
		$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$url       = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$token     = isset( $_POST['token'] ) ? substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_POST['token'] ) ), 0, 32 ) : '';
		$event     = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
		$value     = isset( $_POST['value'] ) ? (int) wp_unslash( $_POST['value'] ) : 0;
		// phpcs:enable
		$user_id = get_current_user_id();

		if ( 'notice_ok' === $event ) {
			update_user_meta( $user_id, self::META_NOTICE_SEEN, time() );
			wp_send_json_success( array( 'ok' => 1 ) );
		}
		// Page fermée ou quittée : l'élève passe hors ligne tout de suite, sauf s'il a déjà ouvert une autre page.
		if ( 'leave' === $event ) {
			if ( $token && get_user_meta( $user_id, self::META_PAGE_TOKEN, true ) === $token ) {
				update_user_meta( $user_id, self::META_LEFT_AT, time() );
				if ( self::settings()['actions'] ) {
					self::record_action( $user_id, 'leave', 0 );
				}
			}
			wp_send_json_success( array( 'ok' => 1 ) );
		}

		// Le navigateur ne peut signaler que des leçons / cours réels et des pages de ce site.
		if ( ! in_array( $context, array( '', 'lesson', 'course' ), true ) ) {
			$context = '';
		}
		if ( $lesson_id && 'lesson' !== get_post_type( $lesson_id ) ) {
			$lesson_id = 0;
		}
		if ( $course_id && 'course' !== get_post_type( $course_id ) ) {
			$course_id = 0;
		}
		if ( 'lesson' === $context && ! $lesson_id ) {
			$context = '';
		}
		if ( 'course' === $context && ! $course_id ) {
			$context = '';
		}
		$url   = self::is_local_url( $url ) ? $url : '';
		$title = mb_substr( $title, 0, 200 );

		// 1.38.1 : simple signal de présence reçu juste après un autre (plusieurs onglets ouverts, page rechargée) :
		// rien n'a changé pour le tableau du professeur → réponse immédiate, sans écriture en base.
		$last_tracked = absint( get_user_meta( $user_id, self::META_TRACKED_AT, true ) );
		if ( ( '' === $event || 'heartbeat' === $event ) && $last_tracked && ( time() - $last_tracked ) < self::PING_MIN_GAP
			&& ( ! $token || get_user_meta( $user_id, self::META_PAGE_TOKEN, true ) === $token ) ) {
			wp_send_json_success( array( 'tracked_at' => $last_tracked, 'skipped' => 1 ) );
		}

		self::store_activity( $user_id, $context, $lesson_id, $course_id, $title, $url, $token );
		if ( $event && 'heartbeat' !== $event && isset( self::ACTIONS[ $event ] ) && self::settings()['actions'] ) {
			self::record_action( $user_id, $event, $value );
		}
		wp_send_json_success( array( 'tracked_at' => time() ) );
	}

	private static function store_activity( $user_id, $context, $lesson_id, $course_id, $title, $url, $token = '' ) {
		$now = time();

		if ( '' !== (string) get_user_meta( $user_id, self::META_LEFT_AT, true ) ) {
			delete_user_meta( $user_id, self::META_LEFT_AT );
		}
		// Nouvelle page du site ouverte juste après : ce n'était pas une fermeture, on retire « a quitté la page ».
		if ( $token && get_user_meta( $user_id, self::META_PAGE_TOKEN, true ) !== $token ) {
			$list = self::get_actions( $user_id );
			$last = end( $list );
			if ( $last && 'leave' === $last['t'] && ( $now - $last['at'] ) <= 15 ) {
				array_pop( $list );
				update_user_meta( $user_id, self::META_ACTIONS, wp_json_encode( $list ) );
			}
		}
		if ( $token ) {
			update_user_meta( $user_id, self::META_PAGE_TOKEN, $token );
		}

		if ( ! $course_id && $lesson_id ) {
			$course_id = self::get_lesson_course_id( $lesson_id );
		}

		update_user_meta( $user_id, self::META_LAST_SEEN, $now );
		update_user_meta( $user_id, self::META_TRACKED_AT, $now );
		update_user_meta( $user_id, self::META_CURRENT_CONTEXT, sanitize_key( $context ) );
		if ( $title ) {
			update_user_meta( $user_id, self::META_LAST_TITLE, sanitize_text_field( $title ) );
		}
		if ( $url ) {
			update_user_meta( $user_id, self::META_LAST_URL, esc_url_raw( $url ) );
		}

		if ( $lesson_id ) {
			update_user_meta( $user_id, self::META_CURRENT_LESSON, $lesson_id );
			update_user_meta( $user_id, self::META_SENSEI_LESSON, $lesson_id );
			update_user_meta( $user_id, self::META_SENSEI_AT, $now );
		} else {
			if ( '' !== (string) get_user_meta( $user_id, self::META_CURRENT_LESSON, true ) ) {
				delete_user_meta( $user_id, self::META_CURRENT_LESSON );
			}
		}

		if ( $course_id ) {
			update_user_meta( $user_id, self::META_CURRENT_COURSE, $course_id );
			update_user_meta( $user_id, self::META_SENSEI_COURSE, $course_id );
		} else {
			if ( '' !== (string) get_user_meta( $user_id, self::META_CURRENT_COURSE, true ) ) {
				delete_user_meta( $user_id, self::META_CURRENT_COURSE );
			}
		}
	}

	private static function get_current_tracking_payload() {
		if ( null !== self::$payload ) {
			return self::$payload;
		}
		$post_id   = get_queried_object_id();
		$context   = '';
		$lesson_id = 0;
		$course_id = 0;

		if ( $post_id && is_singular( 'lesson' ) ) {
			$context   = 'lesson';
			$lesson_id = absint( $post_id );
			$course_id = self::get_lesson_course_id( $lesson_id );
		} elseif ( $post_id && is_singular( 'course' ) ) {
			$context   = 'course';
			$course_id = absint( $post_id );
		}

		self::$payload = array(
			'context'    => $context,
			'lessonId'   => $lesson_id,
			'courseId'   => $course_id,
			'title'      => $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : wp_get_document_title(),
			'url'        => self::get_current_url(),
			'token'      => wp_generate_password( 16, false, false ), // Identifie cette page ouverte (fermeture).
			'intervalMs' => 30000,
		);
		return self::$payload;
	}

	private static function get_current_url() {
		// Domaine pris dans les réglages du site (jamais dans l'en-tête Host envoyé par le navigateur).
		$home = wp_parse_url( home_url( '/' ) );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passé dans esc_url_raw.
		$base = ( isset( $home['scheme'] ) ? $home['scheme'] : 'https' ) . '://' . ( isset( $home['host'] ) ? $home['host'] : '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		return esc_url_raw( $base . '/' . ltrim( (string) $uri, '/' ) );
	}

	private static function is_local_url( $url ) {
		if ( ! $url ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $home && strtolower( $host ) === strtolower( $home );
	}

	/* ── Affichage ──────────────────────────────────────────────── */

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'group_id'        => 0,
				'show_filters'    => 'yes',
				'filter_id'       => 'default',
				'refresh_seconds' => 15,
				'online_minutes'  => 5,
				'empty_text'      => __( 'Choisissez un groupe classe pour afficher les élèves.', 'logilive' ),
				'class'           => '',
				'capability'      => '', // Ancien attribut, ignoré : l'accès est toujours réservé aux enseignants.
			),
			$atts,
			self::SHORTCODE
		);

		if ( ! self::current_user_can_access() ) {
			return '<div class="llv-denied">' . esc_html__( 'Accès réservé aux enseignants.', 'logilive' ) . '</div>';
		}

		self::enqueue_assets();
		return self::render_app( $atts );
	}

	private static function render_app( $atts ) {
		$filter_id       = sanitize_key( $atts['filter_id'] ? $atts['filter_id'] : 'default' );
		$group_param     = 'lslca_group_' . $filter_id;
		$group_id        = absint( $atts['group_id'] );
		$show_filters    = 'yes' === strtolower( (string) $atts['show_filters'] );
		$refresh_seconds = max( 10, absint( $atts['refresh_seconds'] ) );
		$online_minutes  = max( 1, absint( $atts['online_minutes'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple choix d'affichage.
		if ( $show_filters && isset( $_GET[ $group_param ] ) ) {
			$group_id = absint( $_GET[ $group_param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( $group_id && 'group' !== get_post_type( $group_id ) ) {
			$group_id = 0;
		}

		$classes = array( 'llv-app' );
		if ( is_admin() ) {
			$classes[] = 'is-admin';
		}
		foreach ( preg_split( '/\s+/', (string) $atts['class'] ) as $extra ) {
			$extra = sanitize_html_class( $extra );
			if ( $extra ) {
				$classes[] = $extra;
			}
		}

		$art = '';
		$art_file = plugin_dir_path( __FILE__ ) . 'assets/logilive-classe.svg';
		if ( is_readable( $art_file ) ) {
			$art = (string) file_get_contents( $art_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier du plugin.
		}

		ob_start();
		?>
		<section
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-llv-app
			data-group-id="<?php echo esc_attr( $group_id ); ?>"
			data-group-param="<?php echo esc_attr( $group_param ); ?>"
			data-online-minutes="<?php echo esc_attr( $online_minutes ); ?>"
			data-refresh-ms="<?php echo esc_attr( $refresh_seconds * 1000 ); ?>"
		>
			<header class="llv-hero">
				<div class="llv-hero-top">
					<span class="llv-brand"><img class="llv-logo" src="<?php echo esc_url( plugins_url( 'assets/logilive-logo-blanc.svg', __FILE__ ) ); ?>" alt="LogiLive by LogiProf" width="261" height="80" decoding="async"></span>
					<div class="llv-live" data-llv-live>
						<span class="llv-live-dot" aria-hidden="true"></span>
						<span class="llv-live-label"><?php esc_html_e( 'En direct', 'logilive' ); ?></span>
						<span class="llv-live-time" data-llv-live-time><?php echo esc_html( sprintf( __( 'Actualisé à %s', 'logilive' ), wp_date( 'H:i', time(), wp_timezone() ) ) ); ?></span>
					</div>
				</div>
				<div class="llv-hero-main">
					<div class="llv-hero-text">
						<p class="llv-kicker"><?php esc_html_e( 'Suivi de la classe', 'logilive' ); ?></p>
						<h2 class="llv-hello"><?php esc_html_e( 'Qui travaille, et sur quoi ?', 'logilive' ); ?></h2>
						<p class="llv-lead">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: secondes, 2: minutes */
									__( 'Le tableau se met à jour toutes les %1$s secondes. Un élève est « connecté » s’il a été actif dans les %2$s dernières minutes.', 'logilive' ),
									number_format_i18n( $refresh_seconds ),
									number_format_i18n( $online_minutes )
								)
							);
							?>
						</p>
						<?php if ( $show_filters ) : ?>
							<?php self::render_group_form( $group_param, $group_id ); ?>
						<?php endif; ?>
					</div>
					<?php if ( $art ) : ?>
						<div class="llv-art"><?php echo $art; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique du plugin. ?></div>
					<?php endif; ?>
				</div>
			</header>

			<div class="llv-body">
				<div class="llv-results" data-llv-results aria-live="polite">
					<?php
					if ( ! $group_id ) {
						echo self::render_empty( $atts['empty_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo self::render_activity_results( $group_id, $online_minutes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_empty( $text, $kind = 'pick' ) {
		return '<div class="llv-empty llv-empty-' . esc_attr( $kind ) . '"><span class="llv-empty-icon" aria-hidden="true"></span><p>' . esc_html( $text ) . '</p></div>';
	}

	private static function render_group_form( $group_param, $selected_group ) {
		$groups = self::get_groups();
		?>
		<form class="llv-group-form" method="get" data-llv-group-form>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- on conserve les autres paramètres de la page.
			foreach ( $_GET as $key => $value ) :
				if ( $key === $group_param || is_array( $value ) ) {
					continue;
				}
				?>
				<input type="hidden" name="<?php echo esc_attr( sanitize_key( $key ) ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $value ) ) ); ?>">
			<?php endforeach; ?>
			<label class="llv-field">
				<span><?php esc_html_e( 'Groupe classe', 'logilive' ); ?></span>
				<select name="<?php echo esc_attr( $group_param ); ?>" data-llv-group-select>
					<option value="0"><?php esc_html_e( 'Choisir un groupe…', 'logilive' ); ?></option>
					<?php foreach ( $groups as $group ) : ?>
						<option value="<?php echo esc_attr( $group->ID ); ?>" <?php selected( $selected_group, $group->ID ); ?>><?php echo esc_html( $group->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button class="llv-btn llv-btn-yellow" type="submit"><?php esc_html_e( 'Afficher', 'logilive' ); ?></button>
		</form>
		<?php
	}

	public static function ajax_refresh_activity() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! self::current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logilive' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- vérifié ci-dessus.
		$group_id       = isset( $_POST['group_id'] ) ? absint( wp_unslash( $_POST['group_id'] ) ) : 0;
		$online_minutes = isset( $_POST['online_minutes'] ) ? min( 120, max( 1, absint( wp_unslash( $_POST['online_minutes'] ) ) ) ) : 5;
		// phpcs:enable

		if ( ! $group_id || 'group' !== get_post_type( $group_id ) ) {
			wp_send_json_success(
				array(
					'html' => self::render_empty( __( 'Choisissez un groupe classe pour afficher les élèves.', 'logilive' ) ),
					'time' => wp_date( 'H:i', time(), wp_timezone() ),
				)
			);
		}

		wp_send_json_success(
			array(
				'html' => self::render_activity_results( $group_id, $online_minutes ),
				'time' => wp_date( 'H:i', time(), wp_timezone() ),
			)
		);
	}

	private static function render_activity_results( $group_id, $online_minutes ) {
		$group  = get_post( $group_id );
		$rows   = self::get_group_activity_rows( $group_id, $online_minutes );
		$online = 0;
		$lesson = 0;
		foreach ( $rows as $row ) {
			if ( $row['is_online'] ) {
				$online++;
				if ( $row['in_lesson'] ) {
					$lesson++;
				}
			}
		}
		$offline = count( $rows ) - $online;
		$show_actions = (bool) self::settings()['actions'];

		ob_start();
		?>
		<div class="llv-summary">
			<div class="llv-tile llv-tile-group">
				<span class="llv-tile-label"><?php esc_html_e( 'Groupe classe', 'logilive' ); ?></span>
				<strong class="llv-tile-title"><?php echo esc_html( $group ? $group->post_title : __( 'Groupe sélectionné', 'logilive' ) ); ?></strong>
			</div>
			<div class="llv-tile llv-tile-online">
				<span class="llv-tile-num"><?php echo esc_html( number_format_i18n( $online ) ); ?></span>
				<span class="llv-tile-label"><?php echo esc_html( _n( 'connecté', 'connectés', $online, 'logilive' ) ); ?></span>
			</div>
			<div class="llv-tile llv-tile-lesson">
				<span class="llv-tile-num"><?php echo esc_html( number_format_i18n( $lesson ) ); ?></span>
				<span class="llv-tile-label"><?php esc_html_e( 'dans une leçon', 'logilive' ); ?></span>
			</div>
			<div class="llv-tile llv-tile-offline">
				<span class="llv-tile-num"><?php echo esc_html( number_format_i18n( $offline ) ); ?></span>
				<span class="llv-tile-label"><?php esc_html_e( 'hors ligne', 'logilive' ); ?></span>
			</div>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<?php echo self::render_empty( __( 'Aucun élève dans ce groupe.', 'logilive' ), 'none' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<div class="llv-tools">
				<div class="llv-chips" role="group" aria-label="<?php esc_attr_e( 'Filtrer les élèves', 'logilive' ); ?>">
					<button type="button" class="llv-chip" data-llv-filter="all" aria-pressed="true"><?php esc_html_e( 'Tous', 'logilive' ); ?> <span><?php echo esc_html( count( $rows ) ); ?></span></button>
					<button type="button" class="llv-chip" data-llv-filter="online" aria-pressed="false"><?php esc_html_e( 'Connectés', 'logilive' ); ?> <span><?php echo esc_html( $online ); ?></span></button>
					<button type="button" class="llv-chip" data-llv-filter="offline" aria-pressed="false"><?php esc_html_e( 'Hors ligne', 'logilive' ); ?> <span><?php echo esc_html( $offline ); ?></span></button>
				</div>
				<label class="llv-search">
					<span class="llv-sr"><?php esc_html_e( 'Rechercher un élève', 'logilive' ); ?></span>
					<input type="search" placeholder="<?php esc_attr_e( 'Rechercher un élève…', 'logilive' ); ?>" data-llv-search autocomplete="off">
				</label>
			</div>

			<div class="llv-table-wrap">
				<table class="llv-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Élève', 'logilive' ); ?></th>
							<th scope="col"><?php esc_html_e( 'État', 'logilive' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Page ou leçon', 'logilive' ); ?></th>
							<?php if ( $show_actions ) : ?><th scope="col"><?php esc_html_e( 'Dernière action', 'logilive' ); ?></th><?php endif; ?>
							<th scope="col"><?php esc_html_e( 'Cours', 'logilive' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Dernière activité', 'logilive' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$state = $row['is_online'] ? ( $row['in_lesson'] ? 'lesson' : 'online' ) : 'offline';
							$name  = $row['student']->display_name;
							?>
							<tr class="is-<?php echo esc_attr( $state ); ?>" data-llv-row data-state="<?php echo $row['is_online'] ? 'online' : 'offline'; ?>" data-name="<?php echo esc_attr( remove_accents( mb_strtolower( $name . ' ' . $row['student']->user_email ) ) ); ?>">
								<th scope="row" data-label="<?php esc_attr_e( 'Élève', 'logilive' ); ?>">
									<span class="llv-student">
										<span class="llv-avatar" aria-hidden="true"><?php echo esc_html( self::initials( $name ) ); ?></span>
										<span>
											<span class="llv-student-name"><?php echo esc_html( $name ); ?></span>
											<small><?php echo esc_html( $row['student']->user_email ); ?></small>
										</span>
									</span>
								</th>
								<td data-label="<?php esc_attr_e( 'État', 'logilive' ); ?>">
									<span class="llv-status is-<?php echo esc_attr( $row['presence'] ? $row['presence'] : $state ); ?>">
										<?php
										if ( 'away' === $row['presence'] ) {
											esc_html_e( 'Onglet quitté', 'logilive' );
										} elseif ( 'idle' === $row['presence'] ) {
											esc_html_e( 'Inactif', 'logilive' );
										} elseif ( 'silent' === $row['presence'] ) {
											esc_html_e( 'Sans signal', 'logilive' );
										} elseif ( 'lesson' === $state ) {
											esc_html_e( 'En leçon', 'logilive' );
										} elseif ( 'online' === $state ) {
											esc_html_e( 'Connecté', 'logilive' );
										} elseif ( $row['closed'] ) {
											esc_html_e( 'Page fermée', 'logilive' );
										} else {
											esc_html_e( 'Hors ligne', 'logilive' );
										}
										?>
									</span>
									<?php if ( 'silent' === $row['presence'] ) : ?>
										<small><?php echo esc_html( sprintf( __( 'Dernier signal %s', 'logilive' ), self::short_ago( $row['last_seen_ts'] ) ) ); ?></small>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Page ou leçon', 'logilive' ); ?>">
									<?php if ( ! empty( $row['activity_url'] ) ) : ?>
										<a class="llv-activity" href="<?php echo esc_url( $row['activity_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['activity_title'] ); ?></a>
									<?php else : ?>
										<span class="llv-activity"><?php echo esc_html( $row['activity_title'] ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $row['activity_note'] ) ) : ?>
										<small><?php echo esc_html( $row['activity_note'] ); ?></small>
									<?php endif; ?>
								</td>
								<?php if ( $show_actions ) : ?>
								<td data-label="<?php esc_attr_e( 'Dernière action', 'logilive' ); ?>" class="llv-action-cell">
									<?php if ( $row['last_action'] ) : ?>
										<span class="llv-action is-<?php echo esc_attr( $row['last_action']['t'] ); ?>"><?php echo esc_html( self::action_label( $row['last_action'] ) ); ?></span>
										<small><?php echo esc_html( self::short_ago( $row['last_action']['at'] ) ); ?></small>
										<?php if ( count( $row['actions'] ) > 1 ) : ?>
											<details class="llv-actions-more" data-llv-more="<?php echo esc_attr( $row['student']->ID ); ?>"><summary><?php esc_html_e( 'Avant', 'logilive' ); ?></summary><ol>
												<?php foreach ( array_reverse( array_slice( $row['actions'], 0, -1 ) ) as $a ) : ?>
													<li><?php echo esc_html( self::action_label( $a ) ); ?> <small><?php echo esc_html( self::short_ago( $a['at'] ) ); ?></small></li>
												<?php endforeach; ?>
											</ol></details>
										<?php endif; ?>
									<?php else : ?>
										<span class="llv-action is-none">—</span>
									<?php endif; ?>
								</td>
								<?php endif; ?>
								<td data-label="<?php esc_attr_e( 'Cours', 'logilive' ); ?>"><span class="llv-course"><?php echo esc_html( $row['course_title'] ); ?></span></td>
								<td data-label="<?php esc_attr_e( 'Dernière activité', 'logilive' ); ?>">
									<span class="llv-when"><?php echo esc_html( $row['last_seen_relative'] ); ?></span>
									<?php if ( $row['last_seen_label'] ) : ?>
										<small><?php echo esc_html( $row['last_seen_label'] ); ?></small>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="llv-nomatch" data-llv-nomatch hidden><?php esc_html_e( 'Aucun élève ne correspond à ce filtre.', 'logilive' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	private static function initials( $name ) {
		$parts = preg_split( '/[\s\-]+/u', trim( (string) $name ) );
		$out   = '';
		foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $part ) {
			$out .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
		}
		return $out ? $out : '?';
	}

	/* ── Données ────────────────────────────────────────────────── */

	private static function get_group_activity_rows( $group_id, $online_minutes ) {
		$student_ids = self::get_group_student_ids( $group_id );
		if ( empty( $student_ids ) ) {
			return array();
		}

		// Charge tous les élèves et leurs métadonnées en une fois (au lieu d'une requête par élève).
		cache_users( $student_ids );

		$rows = array();
		foreach ( $student_ids as $student_id ) {
			$student = get_userdata( $student_id );
			if ( $student ) {
				$rows[] = self::get_student_activity_row( $student, $online_minutes );
			}
		}

		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a['is_online'] !== $b['is_online'] ) {
					return $a['is_online'] ? -1 : 1;
				}
				if ( $a['sort_ts'] !== $b['sort_ts'] ) {
					return $a['sort_ts'] > $b['sort_ts'] ? -1 : 1;
				}
				return strnatcasecmp( $a['student']->display_name, $b['student']->display_name );
			}
		);

		return $rows;
	}

	private static function get_student_activity_row( $student, $online_minutes ) {
		$user_id               = absint( $student->ID );
		$window                = max( 1, absint( $online_minutes ) ) * MINUTE_IN_SECONDS;
		$last_seen_ts          = absint( get_user_meta( $user_id, self::META_LAST_SEEN, true ) );
		$last_login_ts         = self::get_last_login_timestamp( $user_id );
		$context               = sanitize_key( get_user_meta( $user_id, self::META_CURRENT_CONTEXT, true ) );
		$lesson_id             = absint( get_user_meta( $user_id, self::META_CURRENT_LESSON, true ) );
		$course_id             = absint( get_user_meta( $user_id, self::META_CURRENT_COURSE, true ) );
		$last_sensei_lesson_id = absint( get_user_meta( $user_id, self::META_SENSEI_LESSON, true ) );
		$last_sensei_course_id = absint( get_user_meta( $user_id, self::META_SENSEI_COURSE, true ) );
		$last_sensei_ts        = absint( get_user_meta( $user_id, self::META_SENSEI_AT, true ) );
		$last_title            = sanitize_text_field( get_user_meta( $user_id, self::META_LAST_TITLE, true ) );
		$last_url              = esc_url_raw( get_user_meta( $user_id, self::META_LAST_URL, true ) );
		$activity_title        = $last_title ? $last_title : __( 'Aucune activité récente', 'logilive' );
		$activity_url          = self::is_local_url( $last_url ) ? $last_url : '';
		$activity_note         = '';
		$course_title_override = '';
		$display_seen_ts       = max( $last_seen_ts, $last_login_ts );
		$in_lesson             = false;
		$latest_activity       = array();

		$is_online = $last_seen_ts && ( time() - $last_seen_ts ) <= $window;
		if ( ! $last_seen_ts && $last_login_ts && ( time() - $last_login_ts ) <= $window ) {
			$is_online = true;
		}
		// Page fermée (ou quittée) sans en ouvrir d'autre : hors ligne tout de suite.
		$left_at = absint( get_user_meta( $user_id, self::META_LEFT_AT, true ) );
		$closed  = $left_at && $left_at >= $last_seen_ts && ( time() - $left_at ) <= $window;
		if ( $closed ) {
			$is_online = false;
		}
		$actions     = self::settings()['actions'] ? self::get_actions( $user_id ) : array();
		$last_action = $actions ? end( $actions ) : null;
		$presence    = '';
		if ( $is_online ) {
			if ( $last_action && 'hidden' === $last_action['t'] && $last_action['at'] >= $last_seen_ts - 2 ) {
				$presence = 'away';
			} elseif ( $last_action && 'idle' === $last_action['t'] ) {
				$presence = 'idle';
			} elseif ( $last_seen_ts && ( time() - $last_seen_ts ) > self::LIVE_SECONDS ) {
				$presence = 'silent';
			}
		}

		if ( $is_online && $last_seen_ts && $lesson_id && 'lesson' === $context ) {
			$lesson = get_post( $lesson_id );
			if ( $lesson ) {
				$activity_title = $lesson->post_title;
				$activity_url   = get_permalink( $lesson );
				$activity_note  = __( 'Leçon ouverte en ce moment', 'logilive' );
				$in_lesson      = true;
			}
		} elseif ( $is_online && $last_seen_ts && $course_id && 'course' === $context ) {
			$course = get_post( $course_id );
			if ( $course ) {
				$activity_title = $course->post_title;
				$activity_url   = get_permalink( $course );
				$activity_note  = __( 'Page du cours ouverte en ce moment', 'logilive' );
			}
		} elseif ( $is_online ) {
			$lesson_id             = 0;
			$course_id             = 0;
			$activity_title        = $last_seen_ts && $last_title ? $last_title : __( 'Connexion au site', 'logilive' );
			$activity_url          = $last_seen_ts ? $activity_url : '';
			$activity_note         = $last_seen_ts ? __( 'Sur le site, hors cours Sensei', 'logilive' ) : __( 'Vient de se connecter', 'logilive' );
			$course_title_override = __( 'Hors cours Sensei', 'logilive' );
		} elseif ( $last_sensei_lesson_id && ( ! $last_sensei_ts || $last_sensei_ts >= $display_seen_ts ) ) {
			$lesson = get_post( $last_sensei_lesson_id );
			if ( $lesson ) {
				$lesson_id      = $last_sensei_lesson_id;
				$course_id      = $last_sensei_course_id;
				$activity_title = $lesson->post_title;
				$activity_url   = get_permalink( $lesson );
				$activity_note  = __( 'Dernière leçon Sensei consultée', 'logilive' );
				if ( $last_sensei_ts ) {
					$display_seen_ts = max( $display_seen_ts, $last_sensei_ts );
				}
			}
		}

		if ( ! $is_online ) {
			$latest_activity = self::pick_latest_external_activity(
				self::get_latest_sensei_lesson_activity( $user_id ),
				self::get_latest_revision_activity( $user_id )
			);
			if ( ! empty( $latest_activity ) && $latest_activity['timestamp'] >= $display_seen_ts ) {
				$lesson_id             = absint( $latest_activity['lesson_id'] );
				$course_id             = absint( $latest_activity['course_id'] );
				$activity_title        = $latest_activity['title'];
				$activity_url          = $latest_activity['url'];
				$activity_note         = $latest_activity['note'];
				$course_title_override = isset( $latest_activity['course_title'] ) ? $latest_activity['course_title'] : '';
				$display_seen_ts       = $latest_activity['timestamp'];
			}
		}

		if ( $last_login_ts && $display_seen_ts === $last_login_ts && ( empty( $latest_activity['timestamp'] ) || $latest_activity['timestamp'] < $last_login_ts ) ) {
			$lesson_id             = 0;
			$course_id             = 0;
			$activity_title        = __( 'Connexion au site', 'logilive' );
			$activity_url          = '';
			$activity_note         = __( 'Dernière connexion connue, sans activité de cours', 'logilive' );
			$course_title_override = __( 'Hors cours Sensei', 'logilive' );
			$in_lesson             = false;
		}

		if ( ! $course_id && $lesson_id ) {
			$course_id = self::get_lesson_course_id( $lesson_id );
		}
		$course = $course_id ? get_post( $course_id ) : null;

		return array(
			'student'            => $student,
			'is_online'          => (bool) $is_online,
			'in_lesson'          => $in_lesson,
			'sort_ts'            => max( $last_seen_ts, $display_seen_ts ),
			'last_seen_label'    => $display_seen_ts ? self::format_observed_datetime( $display_seen_ts ) : '',
			'last_seen_relative' => $display_seen_ts ? self::format_relative_time( $display_seen_ts ) : __( 'Jamais vu', 'logilive' ),
			'activity_title'     => $activity_title,
			'activity_url'       => $activity_url,
			'activity_note'      => $activity_note,
			'course_title'       => $course ? $course->post_title : ( $course_title_override ? $course_title_override : __( 'Hors cours Sensei', 'logilive' ) ),
			'presence'           => $presence,
			'closed'             => $closed,
			'last_seen_ts'       => $last_seen_ts,
			'last_action'        => $last_action,
			'actions'            => $actions,
		);
	}

	private static function get_last_login_timestamp( $user_id ) {
		$meta_keys = array(
			self::META_LAST_LOGIN,
			'last_login',
			'_last_login',
			'last_login_time',
			'_last_login_time',
			'wp_last_login',
			'um_last_login',
			'_um_last_login',
			'um_last_login_time',
			'_um_last_login_time',
		);

		$timestamps = array();
		foreach ( $meta_keys as $meta_key ) {
			$timestamp = self::parse_timestamp_value( get_user_meta( $user_id, $meta_key, true ), $meta_key );
			if ( $timestamp ) {
				$timestamps[] = $timestamp;
			}
		}

		return empty( $timestamps ) ? 0 : max( $timestamps );
	}

	private static function parse_timestamp_value( $value, $source_key = '' ) {
		if ( is_array( $value ) ) {
			$timestamps = array();
			foreach ( $value as $item ) {
				$timestamp = self::parse_timestamp_value( $item, $source_key );
				if ( $timestamp ) {
					$timestamps[] = $timestamp;
				}
			}
			return empty( $timestamps ) ? 0 : max( $timestamps );
		}

		if ( is_numeric( $value ) ) {
			$timestamp = (int) $value;
			if ( $timestamp > 1000000000000 ) {
				$timestamp = (int) floor( $timestamp / 1000 );
			}
			if ( $timestamp <= 1000000000 ) {
				return 0;
			}
			return self::META_LAST_LOGIN === $source_key ? $timestamp : self::normalize_external_numeric_timestamp( $timestamp );
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}

		$mysql_timestamp = self::mysql_local_to_timestamp( $value );
		if ( $mysql_timestamp ) {
			return $mysql_timestamp;
		}

		$timestamp = strtotime( $value );
		return $timestamp ? $timestamp : 0;
	}

	private static function get_latest_sensei_lesson_activity( $user_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT comment_post_ID, comment_approved, comment_date, comment_date_gmt
				FROM {$wpdb->comments}
				WHERE user_id = %d AND comment_type = 'sensei_lesson_status'
				ORDER BY comment_date_gmt DESC, comment_ID DESC
				LIMIT 1",
				absint( $user_id )
			)
		);
		if ( ! $row ) {
			return array();
		}

		$lesson_id = absint( $row->comment_post_ID );
		$lesson    = get_post( $lesson_id );
		if ( ! $lesson ) {
			return array();
		}

		$local_timestamp = self::mysql_local_to_timestamp( $row->comment_date );

		return array(
			'lesson_id' => $lesson_id,
			'course_id' => self::get_lesson_course_id( $lesson_id ),
			'title'     => $lesson->post_title,
			'url'       => get_permalink( $lesson ),
			'note'      => self::format_status( $row->comment_approved ),
			'timestamp' => $local_timestamp ? $local_timestamp : self::mysql_gmt_to_timestamp( $row->comment_date_gmt ),
		);
	}

	private static function get_latest_revision_activity( $user_id ) {
		global $wpdb;

		$source = self::get_revision_results_source();
		if ( empty( $source['table'] ) ) {
			return array();
		}

		$table           = $source['table'];
		$columns         = $source['columns'];
		$user_column     = self::quote_identifier( $columns['user_id'] );
		$finished_column = self::quote_identifier( $columns['finished_at'] );
		$modules_column  = ! empty( $columns['modules'] ) ? self::quote_identifier( $columns['modules'] ) : "''";
		$mode_column     = ! empty( $columns['mode'] ) ? self::quote_identifier( $columns['mode'] ) : "''";
		$status_column   = ! empty( $columns['status'] ) ? self::quote_identifier( $columns['status'] ) : "''";
		$percent_column  = ! empty( $columns['percent'] ) ? self::quote_identifier( $columns['percent'] ) : '0';

		// Noms de table et de colonnes : lus dans la base, filtrés par sanitize_key puis entre accents graves.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT {$modules_column} AS modules, {$mode_column} AS mode, {$status_column} AS status,
					{$percent_column} AS percent, {$finished_column} AS finished_at
				FROM {$table}
				WHERE {$user_column} = %d
				ORDER BY {$finished_column} DESC
				LIMIT 1",
				absint( $user_id )
			)
		);
		if ( ! $row || empty( $row->finished_at ) ) {
			return array();
		}

		$timestamp = self::mysql_local_to_timestamp( $row->finished_at );
		if ( ! $timestamp ) {
			return array();
		}

		return array(
			'lesson_id'    => 0,
			'course_id'    => 0,
			'title'        => __( 'Révision LogiProf', 'logilive' ),
			'url'          => '',
			'note'         => self::format_revision_activity_note( $row ),
			'course_title' => __( 'Révision LogiProf', 'logilive' ),
			'timestamp'    => $timestamp,
		);
	}

	private static function pick_latest_external_activity( $sensei_activity, $revision_activity ) {
		$best = array();
		foreach ( array( $sensei_activity, $revision_activity ) as $activity ) {
			if ( ! empty( $activity['timestamp'] ) && ( empty( $best ) || $activity['timestamp'] > $best['timestamp'] ) ) {
				$best = $activity;
			}
		}
		return $best;
	}

	/** Recherche la table des résultats de révision une seule fois par requête (et non une fois par élève). */
	private static function get_revision_results_source() {
		global $wpdb;

		if ( null !== self::$revision_source ) {
			return self::$revision_source;
		}
		self::$revision_source = array();

		$candidates = array(
			$wpdb->prefix . 'logiprof_revision_results',
			$wpdb->prefix . 'logiprof_revisions_results',
			$wpdb->prefix . 'logiprof_revision_multimodules_results',
			$wpdb->prefix . 'logiprof_revisions_multimodules_results',
		);
		$like_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%revision%result%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing    = array_map( 'strval', (array) $like_tables );

		foreach ( array_unique( array_merge( $candidates, $existing ) ) as $table ) {
			if ( ! in_array( $table, $existing, true ) ) {
				continue;
			}
			$columns = self::get_revision_results_columns( $table );
			if ( ! empty( $columns['user_id'] ) && ! empty( $columns['finished_at'] ) ) {
				self::$revision_source = array(
					'table'   => self::quote_identifier( $table ),
					'columns' => $columns,
				);
				break;
			}
		}

		return self::$revision_source;
	}

	private static function get_revision_results_columns( $table ) {
		global $wpdb;

		$raw_columns = $wpdb->get_col( 'DESCRIBE ' . self::quote_identifier( $table ), 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$raw_columns = array_values( array_filter( array_map( 'sanitize_key', (array) $raw_columns ) ) );
		if ( empty( $raw_columns ) ) {
			return array();
		}

		return array(
			'user_id'     => self::find_first_column( $raw_columns, array( 'user_id', 'student_id', 'learner_id', 'eleve_id' ) ),
			'modules'     => self::find_first_column( $raw_columns, array( 'modules', 'selected_modules', 'module', 'module_code', 'module_codes', 'revision_modules' ) ),
			'mode'        => self::find_first_column( $raw_columns, array( 'mode', 'modality', 'modalite', 'type' ) ),
			'status'      => self::find_first_column( $raw_columns, array( 'status', 'session_status' ) ),
			'percent'     => self::find_first_column( $raw_columns, array( 'percent', 'score_percent', 'success_percent', 'percentage', 'score' ) ),
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

	private static function format_revision_activity_note( $row ) {
		$parts   = array();
		$modules = self::format_revision_modules( isset( $row->modules ) ? $row->modules : '' );
		$mode    = self::format_revision_mode( isset( $row->mode ) ? $row->mode : '' );
		$status  = self::format_revision_status( isset( $row->status ) ? $row->status : '' );
		$percent = isset( $row->percent ) ? absint( $row->percent ) : 0;

		foreach ( array( $modules, $mode, $status ) as $part ) {
			if ( $part ) {
				$parts[] = $part;
			}
		}
		if ( $percent ) {
			/* translators: %s: pourcentage */
			$parts[] = sprintf( __( '%s %% de réussite', 'logilive' ), $percent );
		}

		return ! empty( $parts ) ? implode( ' · ', $parts ) : __( 'Activité de révision', 'logilive' );
	}

	private static function format_revision_modules( $encoded_modules ) {
		$modules = json_decode( (string) $encoded_modules, true );
		if ( ! is_array( $modules ) || empty( $modules ) ) {
			return '';
		}
		$modules = array_values( array_filter( array_map( 'sanitize_text_field', array_filter( $modules, 'is_scalar' ) ) ) );
		return empty( $modules ) ? '' : implode( ', ', array_slice( $modules, 0, 6 ) );
	}

	private static function format_revision_mode( $mode ) {
		switch ( (string) $mode ) {
			case 'mix':
				return __( 'Parcours mixte', 'logilive' );
			case 'quiz':
				return __( 'QCM intelligent', 'logilive' );
			case 'truefalse':
				return __( 'Vrai / faux', 'logilive' );
			case 'mistakes':
				return __( 'Focus erreurs', 'logilive' );
		}
		return sanitize_text_field( $mode );
	}

	private static function format_revision_status( $status ) {
		if ( 'in_progress' === (string) $status ) {
			return __( 'En cours', 'logilive' );
		}
		if ( in_array( (string) $status, array( 'completed', 'complete' ), true ) ) {
			return __( 'Terminée', 'logilive' );
		}
		return sanitize_text_field( $status );
	}

	private static function get_groups() {
		return get_posts(
			array(
				'post_type'        => 'group',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
	}

	private static function get_group_student_ids( $group_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'sensei_pro_groups_students';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $exists !== $table ) {
			return array();
		}

		return array_values(
			array_unique(
				array_map(
					'absint',
					$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare( "SELECT student_id FROM {$table} WHERE group_id = %d ORDER BY student_id ASC", absint( $group_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					)
				)
			)
		);
	}

	private static function get_lesson_course_id( $lesson_id ) {
		static $cache = array();

		$lesson_id = absint( $lesson_id );
		if ( ! $lesson_id ) {
			return 0;
		}
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
		if ( ! $course_id ) {
			$course_id = self::get_lesson_course_id_from_order( $lesson_id );
		}
		$cache[ $lesson_id ] = $course_id;
		return $course_id;
	}

	private static function get_lesson_course_id_from_order( $lesson_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_lesson_order' AND meta_value LIKE %s",
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

	private static function format_status( $status ) {
		if ( in_array( (string) $status, array( 'complete', 'passed', 'graded' ), true ) ) {
			return __( 'Leçon terminée', 'logilive' );
		}
		if ( 'in-progress' === (string) $status ) {
			return __( 'Leçon commencée', 'logilive' );
		}
		if ( 'failed' === (string) $status ) {
			return __( 'Leçon à reprendre', 'logilive' );
		}
		return __( 'Dernière activité Sensei', 'logilive' );
	}

	private static function format_relative_time( $timestamp ) {
		$diff = max( 0, time() - absint( $timestamp ) );
		if ( $diff < MINUTE_IN_SECONDS ) {
			return __( "À l'instant", 'logilive' );
		}
		/* translators: %s: durée, ex. « 5 min » */
		return sprintf( __( 'Il y a %s', 'logilive' ), human_time_diff( $timestamp, time() ) );
	}

	private static function format_observed_datetime( $timestamp ) {
		return wp_date( 'j.m.Y · H:i', absint( $timestamp ), wp_timezone() );
	}

	private static function mysql_gmt_to_timestamp( $mysql_gmt_date ) {
		$timestamp = strtotime( (string) $mysql_gmt_date . ' GMT' );
		return $timestamp ? $timestamp : 0;
	}

	private static function normalize_external_numeric_timestamp( $timestamp ) {
		$timestamp = absint( $timestamp );
		if ( ! $timestamp ) {
			return 0;
		}
		$offset          = (int) wp_timezone()->getOffset( new DateTimeImmutable( '@' . $timestamp ) );
		$local_timestamp = $timestamp - $offset;
		return $local_timestamp > 1000000000 ? $local_timestamp : $timestamp;
	}

	private static function mysql_local_to_timestamp( $mysql_date ) {
		if ( ! $mysql_date ) {
			return 0;
		}
		try {
			$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', (string) $mysql_date, wp_timezone() );
			return $datetime ? $datetime->getTimestamp() : 0;
		} catch ( Exception $exception ) {
			return 0;
		}
	}
}

LogiOne_LogiLive::init();
