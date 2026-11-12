<?php
/**
 * Module LogiPlanning de LogiOne (code issu de LogiPlanning LMS 1.2.5).
 * Plans de formation par classe Sensei : import, journées, branches, messages, calendrier des élèves.
 *
 * - Mêmes tables que LogiPlanning LMS (wp_logiplanning_*) : les plans existants sont repris tels quels.
 * - Gestion : permission LogiOne « lp_planning_edit » ; consultation enseignant : « lp_planning_view ».
 * - Les élèves gardent leur page avec [logiplanning_lms classe="…"] (même shortcode).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiOne_Planning {
	const VERSION   = '1.2.5';
	const PAGE      = 'logiplanning-lms';
	const SHORTCODE = 'logiplanning_lms';
	const NONCE     = 'logiplanning_lms_nonce';

	private static $assets_loaded = false;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_post_lplms_preview_import', array( __CLASS__, 'handle_preview_import' ) );
		add_action( 'admin_post_lplms_commit_import', array( __CLASS__, 'handle_commit_import' ) );
		add_action( 'admin_post_lplms_save_plan', array( __CLASS__, 'handle_save_plan' ) );
		add_action( 'admin_post_lplms_duplicate_plan', array( __CLASS__, 'handle_duplicate_plan' ) );
		add_action( 'admin_post_lplms_delete_plan', array( __CLASS__, 'handle_delete_plan' ) );
		add_action( 'admin_post_lplms_save_day', array( __CLASS__, 'handle_save_day' ) );
		add_action( 'admin_post_lplms_bulk_save_days', array( __CLASS__, 'handle_bulk_save_days' ) );
		add_action( 'admin_post_lplms_delete_day', array( __CLASS__, 'handle_delete_day' ) );
		add_action( 'admin_post_lplms_save_branch', array( __CLASS__, 'handle_save_branch' ) );
		add_action( 'admin_post_lplms_save_message', array( __CLASS__, 'handle_save_message' ) );
		add_action( 'admin_post_lplms_delete_message', array( __CLASS__, 'handle_delete_message' ) );
		add_action( 'admin_post_lplms_export_ics', array( __CLASS__, 'handle_export_ics' ) );
		add_action( 'admin_post_lplms_export_excel', array( __CLASS__, 'handle_export_excel' ) );
		add_action( 'admin_post_lplms_download_ics', array( __CLASS__, 'handle_public_export_ics' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		// Portail LogiOne : choix du plan par l'enseignant.
		add_shortcode( 'logiplanning_enseignant', array( __CLASS__, 'render_teacher_portal' ) );
		// Plan favori de l'enseignant (LogiOne 1.24.9) : affiché d'office à l'ouverture de LogiPlanning.
		add_action( 'init', array( __CLASS__, 'handle_favorite_plan' ), 20 );
	}

	const FAVORITE_META = 'logipilot_planning_favorite';

	/** Plan favori de l'enseignant (0 = aucun). */
	public static function favorite_plan_id( $user_id ) {
		return absint( get_user_meta( (int) $user_id, self::FAVORITE_META, true ) );
	}

	/** « ☆ Mon plan favori » : enregistre (ou retire) le favori, puis revient à la même page sans les paramètres. */
	public static function handle_favorite_plan() {
		if ( ! isset( $_GET['lpl_fav'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_lplnonce'] ?? '' ) ), 'lpl_favorite_plan' ) ) {
			return;
		}
		if ( ! current_user_can( 'lp_planning_view' ) && ! self::current_user_can_manage_plugin() ) {
			return;
		}
		$plan_id = absint( $_GET['lpl_fav'] );
		if ( $plan_id && self::get_plan( $plan_id ) ) {
			update_user_meta( get_current_user_id(), self::FAVORITE_META, $plan_id );
		} else {
			delete_user_meta( get_current_user_id(), self::FAVORITE_META );
		}
		$back = remove_query_arg( $plan_id ? array( 'lpl_fav', '_lplnonce', 'lpl_plan' ) : array( 'lpl_fav', '_lplnonce' ) ); // Favori choisi : il s'affiche d'office.
		wp_safe_redirect( add_query_arg( 'lpl_fav_saved', $plan_id ? '1' : '0', $back ) );
		exit;
	}

	public static function activate() {
		self::create_tables();
		self::maybe_migrate_plan_groups();
		self::maybe_migrate_branch_schedules();
		self::maybe_migrate_day_group_teachers();
		self::maybe_migrate_day_rooms();
		self::maybe_migrate_day_group_rooms();
		self::seed_branches();
	}

	/** Tables et colonnes créées ou complétées si besoin (jamais supprimées) : les données existantes sont conservées. */
	public static function maybe_install() {
		if ( get_option( 'logiplanning_module_version' ) === self::VERSION ) {
			return;
		}
		self::activate();
		update_option( 'logiplanning_module_version', self::VERSION, false );
	}

	private static function tables() {
		global $wpdb;
		return array(
			'plans'    => $wpdb->prefix . 'logiplanning_plans',
			'days'     => $wpdb->prefix . 'logiplanning_days',
			'branches' => $wpdb->prefix . 'logiplanning_branches',
			'messages' => $wpdb->prefix . 'logiplanning_messages',
		);
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables  = self::tables();
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$tables['plans']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id bigint(20) unsigned NOT NULL DEFAULT 0,
			group_ids text NULL,
			slug varchar(120) NOT NULL DEFAULT '',
			title varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY group_id (group_id)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$tables['branches']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			color varchar(20) NOT NULL DEFAULT '#2f7d7b',
			default_schedule varchar(80) NOT NULL DEFAULT '',
			description text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$tables['messages']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL DEFAULT '',
			content text NULL,
			internal_category varchar(120) NOT NULL DEFAULT '',
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY active (active)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$tables['days']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
			training_date date NOT NULL,
			branch_id bigint(20) unsigned NOT NULL DEFAULT 0,
			modality varchar(40) NOT NULL DEFAULT 'presentiel',
			teacher varchar(190) NOT NULL DEFAULT '',
			teacher_by_group text NULL,
			schedule varchar(80) NOT NULL DEFAULT '',
			classroom varchar(120) NOT NULL DEFAULT '',
			classroom_by_group text NULL,
			day_type varchar(80) NOT NULL DEFAULT '',
			course_category_ids text NULL,
			message_id bigint(20) unsigned NOT NULL DEFAULT 0,
			custom_message text NULL,
			is_vacation tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY plan_date (plan_id, training_date),
			KEY branch_id (branch_id),
			KEY is_vacation (is_vacation)
		) {$charset};" );
	}

	private static function maybe_migrate_plan_groups() {
		global $wpdb;
		$table = self::tables()['plans'];
		$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'group_ids' ) );
		if ( ! $column ) {
			$wpdb->query( "ALTER TABLE {$table} ADD group_ids text NULL AFTER group_id" );
		}
		$wpdb->query( "UPDATE {$table} SET group_ids = CONCAT('[', group_id, ']') WHERE (group_ids IS NULL OR group_ids = '') AND group_id > 0" );
	}

	private static function maybe_migrate_branch_schedules() {
		global $wpdb;
		$table = self::tables()['branches'];
		$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'default_schedule' ) );
		if ( ! $column ) {
			$wpdb->query( "ALTER TABLE {$table} ADD default_schedule varchar(80) NOT NULL DEFAULT '' AFTER color" );
		}
	}

	private static function maybe_migrate_day_group_teachers() {
		global $wpdb;
		$table = self::tables()['days'];
		$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'teacher_by_group' ) );
		if ( ! $column ) {
			$wpdb->query( "ALTER TABLE {$table} ADD teacher_by_group text NULL AFTER teacher" );
		}
	}

	private static function maybe_migrate_day_rooms() {
		global $wpdb;
		$table = self::tables()['days'];
		$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'classroom' ) );
		if ( ! $column ) {
			$wpdb->query( "ALTER TABLE {$table} ADD classroom varchar(120) NOT NULL DEFAULT '' AFTER schedule" );
		}
	}

	private static function maybe_migrate_day_group_rooms() {
		global $wpdb;
		$table = self::tables()['days'];
		$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'classroom_by_group' ) );
		if ( ! $column ) {
			$wpdb->query( "ALTER TABLE {$table} ADD classroom_by_group text NULL AFTER classroom" );
		}
	}

	private static function seed_branches() {
		global $wpdb;
		$tables = self::tables();
		$now    = current_time( 'mysql' );
		$items  = array(
			array( 'ECG', '#7a5cff', 'Enseignement de culture générale.' ),
			array( 'Log tronc commun', '#1976d2', 'Modules logistiques du tronc commun.' ),
			array( 'Log orientation', '#00866e', 'Modules liés à l’orientation.' ),
			array( 'Vacances', '#b8c0cc', 'Jours de vacances ou pauses planifiées.' ),
		);

		foreach ( $items as $item ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['branches']} WHERE name = %s", $item[0] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$tables['branches'],
				array( 'name' => $item[0], 'color' => $item[1], 'default_schedule' => '', 'description' => $item[2], 'created_at' => $now ),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	public static function register_admin_menu() {
		$capability = 'lp_planning_edit';
		add_menu_page( 'LogiPlanning', 'LogiPlanning', $capability, self::PAGE, array( __CLASS__, 'render_admin_page' ), 'dashicons-calendar-alt', 4 );
		add_submenu_page( self::PAGE, __( 'Plans de formation', 'logiplanning-lms' ), __( 'Plans de formation', 'logiplanning-lms' ), $capability, self::PAGE, array( __CLASS__, 'render_admin_page' ) );
		foreach ( self::admin_tabs() as $key => $label ) {
			if ( 'plans' === $key ) {
				continue;
			}
			add_submenu_page( self::PAGE, $label, $label, $capability, self::PAGE . '-' . $key, array( __CLASS__, 'render_admin_page' ) );
		}
	}

	private static function admin_tabs() {
		return array(
			'plans'    => __( 'Plans de formation', 'logiplanning-lms' ),
			'import'   => __( 'Importer un plan', 'logiplanning-lms' ),
			'days'     => __( 'Journées de formation', 'logiplanning-lms' ),
			'branches' => __( 'Branches', 'logiplanning-lms' ),
			'messages' => __( 'Messages', 'logiplanning-lms' ),
			'settings' => __( 'Réglages', 'logiplanning-lms' ),
			'export'   => __( 'Export / Agenda', 'logiplanning-lms' ),
		);
	}

	public static function register_assets() {
		wp_register_style( 'logiplanning-lms', plugins_url( 'assets/admin.css', __FILE__ ), array(), self::VERSION );
		wp_register_script( 'logiplanning-lms', plugins_url( 'assets/admin.js', __FILE__ ), array(), self::VERSION, true );
		wp_register_style( 'logiplanning-front', plugins_url( 'assets/front.css', __FILE__ ), array(), self::VERSION );
		wp_register_script( 'logiplanning-front', plugins_url( 'assets/front.js', __FILE__ ), array(), self::VERSION, true );
	}

	/** Administration (plans, import, journées…). */
	private static function enqueue_assets() {
		if ( self::$assets_loaded ) {
			return;
		}
		if ( ! wp_style_is( 'logiplanning-lms', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'logiplanning-lms' );
		wp_enqueue_script( 'logiplanning-lms' );
		self::$assets_loaded = true;
	}

	/** Planning des élèves (aux couleurs LogiOne). */
	private static function enqueue_front_assets() {
		if ( ! wp_style_is( 'logiplanning-front', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'logiplanning-front' );
		wp_enqueue_script( 'logiplanning-front' );
	}

	/** Logo LogiPlanning by LogiProf (texte en Poppins, inséré dans la page). */
	public static function logo() {
		static $svg = null;
		if ( null === $svg ) {
			$svg = (string) include __DIR__ . '/assets/logo.svg.php';
		}
		return $svg;
	}

	public static function render_admin_page() {
		if ( ! self::current_user_can_manage_plugin() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiplanning-lms' ) );
		}

		self::maybe_migrate_plan_groups();
		self::maybe_migrate_branch_schedules();
		self::maybe_migrate_day_group_teachers();
		self::maybe_migrate_day_rooms();
		self::maybe_migrate_day_group_rooms();
		self::enqueue_assets();
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$tab    = $screen && false !== strpos( (string) $screen->id, '_page_' . self::PAGE . '-' ) ? str_replace( self::PAGE . '-', '', substr( $screen->id, strrpos( $screen->id, '_' ) + 1 ) ) : 'plans';
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $tab;
		if ( ! isset( self::admin_tabs()[ $tab ] ) ) {
			$tab = 'plans';
		}
		?>
		<div class="wrap lplms-admin">
			<div class="lplms-admin-hero"><?php echo self::logo(); // phpcs:ignore -- SVG statique du module. ?><p><?php esc_html_e( 'Plans de formation par classe : importez le planning, complétez les journées, puis les élèves le consultent sur leur page.', 'logiplanning-lms' ); ?></p></div>
			<h1 class="screen-reader-text"><?php esc_html_e( 'LogiPlanning', 'logiplanning-lms' ); ?></h1>
			<?php self::render_notices(); ?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( self::admin_tabs() as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::admin_url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>
			<?php
			switch ( $tab ) {
				case 'import':
					self::render_import_page();
					break;
				case 'days':
					self::render_days_page();
					break;
				case 'branches':
					self::render_branches_page();
					break;
				case 'messages':
					self::render_messages_page();
					break;
				case 'settings':
					self::render_settings_page();
					break;
				case 'export':
					self::render_export_page();
					break;
				default:
					self::render_plans_page();
			}
			?>
		</div>
		<?php
	}

	private static function admin_url( $tab = 'plans', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	private static function render_notices() {
		if ( empty( $_GET['lplms_notice'] ) ) {
			return;
		}
		$notice  = sanitize_key( wp_unslash( $_GET['lplms_notice'] ) );
		$count   = absint( $_GET['lplms_count'] ?? 0 );
		$skipped = absint( $_GET['lplms_skipped'] ?? 0 );
		$slug    = sanitize_title( wp_unslash( $_GET['lplms_slug'] ?? '' ) );
		$messages = array(
			'saved'            => __( 'Enregistré.', 'logiplanning-lms' ),
			'imported'         => sprintf( _n( 'Import terminé : %d journée ajoutée ou mise à jour.', 'Import terminé : %d journées ajoutées ou mises à jour.', $count, 'logiplanning-lms' ), $count ) . ( $skipped ? ' ' . sprintf( _n( '%d ligne ignorée (date illisible).', '%d lignes ignorées (date illisible).', $skipped, 'logiplanning-lms' ), $skipped ) : '' ),
			'deleted'          => __( 'Supprimé.', 'logiplanning-lms' ),
			'slug_changed'     => sprintf( __( 'Plan enregistré. Ce nom court était déjà utilisé : le plan a reçu « %s ». Utilisez-le dans le shortcode.', 'logiplanning-lms' ), $slug ),
			'title_missing'    => __( 'Indiquez le nom du plan.', 'logiplanning-lms' ),
			'import_plan'      => __( 'Choisissez le plan dans lequel importer les journées.', 'logiplanning-lms' ),
			'import_file'      => __( 'Choisissez le fichier à importer.', 'logiplanning-lms' ),
			'import_unreadable'=> __( 'Le fichier n’a pas pu être lu. Enregistrez-le depuis Excel en .xls ou en texte tabulé, avec une ligne de titres (Date, Branche, Modalité, Enseignant, Horaire).', 'logiplanning-lms' ),
			'import_expired'   => __( 'L’aperçu a expiré (plus d’une heure) : importez de nouveau le fichier.', 'logiplanning-lms' ),
			'error'            => __( 'Action impossible. Vérifiez les données.', 'logiplanning-lms' ),
		);
		$class = in_array( $notice, array( 'error', 'title_missing', 'import_plan', 'import_file', 'import_unreadable', 'import_expired' ), true ) ? 'notice-error' : ( 'slug_changed' === $notice ? 'notice-warning' : 'notice-success' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( isset( $messages[ $notice ] ) ? $messages[ $notice ] : $messages['saved'] ) . '</p></div>';
	}

	private static function render_plans_page() {
		$plans  = self::get_plans();
		$groups = self::get_groups();
		?>
		<div class="lplms-grid">
			<section>
				<h2><?php esc_html_e( 'Plans existants', 'logiplanning-lms' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Plan', 'logiplanning-lms' ); ?></th><th><?php esc_html_e( 'Classes Sensei', 'logiplanning-lms' ); ?></th><th><?php esc_html_e( 'Shortcode', 'logiplanning-lms' ); ?></th><th><?php esc_html_e( 'Actions', 'logiplanning-lms' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $plans as $plan ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $plan->title ); ?></strong><br><code><?php echo esc_html( $plan->slug ); ?></code></td>
								<td><?php echo esc_html( self::get_group_labels( self::get_plan_group_ids( $plan ) ) ); ?></td>
								<td><div class="lplms-shortcode"><input class="code" readonly value='[logiplanning_lms classe="<?php echo esc_attr( $plan->slug ); ?>"]' aria-label="<?php esc_attr_e( 'Shortcode de la page des élèves', 'logiplanning-lms' ); ?>"><button type="button" class="button" data-lplms-copy><?php esc_html_e( 'Copier', 'logiplanning-lms' ); ?></button></div><p class="description"><?php esc_html_e( 'À coller dans la page de la classe.', 'logiplanning-lms' ); ?></p></td>
								<td>
									<a class="button button-primary" href="<?php echo esc_url( self::admin_url( 'days', array( 'plan_id' => $plan->id ) ) ); ?>"><?php esc_html_e( 'Modifier les journées', 'logiplanning-lms' ); ?></a>
									<a class="button" href="<?php echo esc_url( self::admin_url( 'import', array( 'plan_id' => $plan->id ) ) ); ?>"><?php esc_html_e( 'Importer un fichier', 'logiplanning-lms' ); ?></a>
									<details class="lplms-plan-edit">
										<summary><?php esc_html_e( 'Modifier le plan', 'logiplanning-lms' ); ?></summary>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="lplms_save_plan">
											<input type="hidden" name="id" value="<?php echo esc_attr( $plan->id ); ?>">
											<?php wp_nonce_field( 'lplms_save_plan', self::NONCE ); ?>
											<label><?php esc_html_e( 'Nom du plan', 'logiplanning-lms' ); ?><input name="title" value="<?php echo esc_attr( $plan->title ); ?>" required></label>
											<label><?php esc_html_e( 'Nom court (pour le shortcode)', 'logiplanning-lms' ); ?><input name="slug" value="<?php echo esc_attr( $plan->slug ); ?>" required></label>
											<div class="lplms-editor-field"><strong><?php esc_html_e( 'Groupes/classes Sensei', 'logiplanning-lms' ); ?></strong><?php self::render_group_multiselect( 'group_ids', self::get_plan_group_ids( $plan ), $groups ); ?></div>
											<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer le plan', 'logiplanning-lms' ); ?></button>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-delete-plan-form" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer ce plan et toutes ses journées ? Cette action est définitive.', 'logiplanning-lms' ) ); ?>');">
											<input type="hidden" name="action" value="lplms_delete_plan">
											<input type="hidden" name="id" value="<?php echo esc_attr( $plan->id ); ?>">
											<?php wp_nonce_field( 'lplms_delete_plan_' . absint( $plan->id ), self::NONCE ); ?>
											<button type="submit" class="button lplms-danger-link"><?php esc_html_e( 'Supprimer', 'logiplanning-lms' ); ?></button>
										</form>
									</details>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-inline-duplicate">
										<input type="hidden" name="action" value="lplms_duplicate_plan">
										<input type="hidden" name="source_plan_id" value="<?php echo esc_attr( $plan->id ); ?>">
										<?php wp_nonce_field( 'lplms_duplicate_plan', self::NONCE ); ?>
										<input name="title" placeholder="<?php esc_attr_e( 'Nom de la copie (ex. Logisticien 1B)', 'logiplanning-lms' ); ?>" required aria-label="<?php esc_attr_e( 'Nom de la copie', 'logiplanning-lms' ); ?>">
										<button type="submit" class="button"><?php esc_html_e( 'Dupliquer', 'logiplanning-lms' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if ( empty( $plans ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'Aucun plan pour le moment.', 'logiplanning-lms' ); ?></td></tr><?php endif; ?>
					</tbody>
				</table>
			</section>
			<section>
				<h2><?php esc_html_e( 'Créer un plan', 'logiplanning-lms' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-panel">
					<input type="hidden" name="action" value="lplms_save_plan">
					<?php wp_nonce_field( 'lplms_save_plan', self::NONCE ); ?>
					<label><?php esc_html_e( 'Titre', 'logiplanning-lms' ); ?><input name="title" class="regular-text" required></label>
					<label><?php esc_html_e( 'Nom court (pour le shortcode)', 'logiplanning-lms' ); ?><input name="slug" class="regular-text" placeholder="<?php esc_attr_e( 'facultatif : créé à partir du titre', 'logiplanning-lms' ); ?>"></label>
					<div class="lplms-editor-field"><strong><?php esc_html_e( 'Groupes/classes Sensei', 'logiplanning-lms' ); ?></strong><?php self::render_group_multiselect( 'group_ids', array(), $groups ); ?></div>
					<?php submit_button( __( 'Enregistrer le plan', 'logiplanning-lms' ) ); ?>
				</form>

			</section>
		</div>
		<?php
	}

	private static function render_import_page() {
		$preview = self::get_import_preview();
		if ( $preview ) {
			self::render_import_preview( $preview );
			return;
		}
		?>
		<h2><?php esc_html_e( 'Importer un fichier XLS', 'logiplanning-lms' ); ?></h2>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-panel">
			<input type="hidden" name="action" value="lplms_preview_import">
			<?php wp_nonce_field( 'lplms_preview_import', self::NONCE ); ?>
			<p><?php esc_html_e( 'Colonnes attendues : Date, Branche, Modalité, Enseignant, Horaire. Les fichiers .xls Excel HTML/XML et les exports tabulés sont pris en charge.', 'logiplanning-lms' ); ?></p>
			<label><?php esc_html_e( 'Plan cible', 'logiplanning-lms' ); ?><?php self::render_plan_select( 'plan_id', self::default_plan_id() ); ?></label>
			<input type="file" name="planning_file" accept=".xls,.html,.xml,.csv,.tsv,.txt" required>
			<?php submit_button( __( 'Prévisualiser', 'logiplanning-lms' ) ); ?>
		</form>
		<?php
	}

	private static function render_import_preview( $preview ) {
		$headers = $preview['headers'];
		$rows    = $preview['rows'];
		$fields  = array( 'date' => 'Date', 'branch' => 'Branche', 'modality' => 'Modalité', 'teacher' => 'Enseignant', 'schedule' => 'Horaire' );
		?>
		<h2><?php esc_html_e( 'Aperçu avant import', 'logiplanning-lms' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lplms_commit_import">
			<input type="hidden" name="preview_key" value="<?php echo esc_attr( $preview['key'] ); ?>">
			<input type="hidden" name="plan_id" value="<?php echo esc_attr( $preview['plan_id'] ); ?>">
			<?php wp_nonce_field( 'lplms_commit_import', self::NONCE ); ?>
			<table class="form-table"><tbody>
				<?php foreach ( $fields as $field => $label ) : ?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><select name="map[<?php echo esc_attr( $field ); ?>]"><?php foreach ( $headers as $i => $header ) : ?><option value="<?php echo esc_attr( $i ); ?>" <?php selected( self::guess_column( $headers, $field ), $i ); ?>><?php echo esc_html( $header ? $header : sprintf( __( 'Colonne %d', 'logiplanning-lms' ), $i + 1 ) ); ?></option><?php endforeach; ?></select></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<div class="lplms-table-scroll">
				<table class="widefat striped">
					<thead><tr><?php foreach ( $headers as $header ) : ?><th><?php echo esc_html( $header ); ?></th><?php endforeach; ?></tr></thead>
					<tbody><?php foreach ( array_slice( $rows, 0, 30 ) as $row ) : ?><tr><?php foreach ( $headers as $i => $header ) : ?><td><?php echo esc_html( isset( $row[ $i ] ) ? $row[ $i ] : '' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
				</table>
			</div>
			<?php submit_button( __( 'Valider l’import', 'logiplanning-lms' ), 'primary', 'submit', false ); ?>
			<a class="button" href="<?php echo esc_url( self::admin_url( 'import' ) ); ?>"><?php esc_html_e( 'Annuler', 'logiplanning-lms' ); ?></a>
		</form>
		<?php
	}

	/**
	 * Journées d'un plan : une ligne par journée avec l'essentiel (date, branche, modalité, horaire,
	 * enseignant, salle), le reste (types, catégories, messages, valeurs par classe) dans « Plus ».
	 * Le formulaire d'ajout est replié en haut. Mêmes champs qu'avant : l'enregistrement ne change pas.
	 */
	private static function render_days_page() {
		$plan_id = isset( $_GET['plan_id'] ) ? absint( $_GET['plan_id'] ) : self::get_first_plan_id();
		$edit_id = isset( $_GET['day_id'] ) ? absint( $_GET['day_id'] ) : 0;
		$day     = $edit_id ? self::get_day( $edit_id ) : null;
		$days    = $plan_id ? self::get_days( $plan_id ) : array();
		$plan    = $plan_id ? self::get_plan( $plan_id ) : null;
		?>
		<div class="lplms-days-bar">
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lplms-filter">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>"><input type="hidden" name="tab" value="days">
				<label><?php esc_html_e( 'Plan', 'logiplanning-lms' ); ?><?php self::render_plan_select( 'plan_id', $plan_id ); ?></label>
				<noscript><button class="button"><?php esc_html_e( 'Afficher', 'logiplanning-lms' ); ?></button></noscript>
			</form>
			<?php if ( $plan ) : ?>
				<p class="lplms-days-summary"><?php echo esc_html( sprintf( _n( '%d journée', '%d journées', count( $days ), 'logiplanning-lms' ), count( $days ) ) . ' · ' . self::get_group_labels( self::get_plan_group_ids( $plan ) ) ); ?></p>
				<a class="button" href="<?php echo esc_url( self::admin_url( 'import', array( 'plan_id' => $plan_id ) ) ); ?>"><?php esc_html_e( 'Importer un fichier', 'logiplanning-lms' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( $plan ) : ?>
		<details class="lplms-add-day" id="lplms-day-form" <?php echo ( $day || ! $days ) ? 'open' : ''; ?>>
			<summary class="button button-primary"><?php echo $day ? esc_html( sprintf( __( 'Modifier la journée du %s', 'logiplanning-lms' ), self::format_date( $day->training_date ) ) ) : esc_html__( '+ Ajouter une journée', 'logiplanning-lms' ); ?></summary>
			<div class="lplms-add-day-body">
				<?php self::render_day_form( $plan_id, $day ); ?>
				<?php if ( $day ) : ?><p><a href="<?php echo esc_url( self::admin_url( 'days', array( 'plan_id' => $plan_id ) ) ); ?>"><?php esc_html_e( 'Annuler', 'logiplanning-lms' ); ?></a></p><?php endif; ?>
			</div>
		</details>
		<?php endif; ?>

		<section class="lplms-days-section">
			<?php self::render_days_bulk_form( $plan_id, $days ); ?>
		</section>
		<?php
	}

	private static function render_days_bulk_form( $plan_id, $days ) {
		if ( empty( $days ) ) {
			echo '<p class="lplms-empty-days">' . esc_html__( 'Aucune journée pour ce plan : importez un fichier ou ajoutez une première journée.', 'logiplanning-lms' ) . '</p>';
			return;
		}
		$plan      = self::get_plan( $plan_id );
		$group_ids = self::get_plan_group_ids( $plan );
		$today     = current_time( 'Y-m-d' );
		$month     = '';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-days-form">
			<input type="hidden" name="action" value="lplms_bulk_save_days">
			<input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan_id ); ?>">
			<?php wp_nonce_field( 'lplms_bulk_save_days', self::NONCE ); ?>
			<div class="lplms-days-head" aria-hidden="true"><span><?php esc_html_e( 'Date', 'logiplanning-lms' ); ?></span><span><?php esc_html_e( 'Branche', 'logiplanning-lms' ); ?></span><span><?php esc_html_e( 'Modalité', 'logiplanning-lms' ); ?></span><span><?php esc_html_e( 'Horaire', 'logiplanning-lms' ); ?></span><span><?php esc_html_e( 'Enseignant', 'logiplanning-lms' ); ?></span><span><?php esc_html_e( 'Salle', 'logiplanning-lms' ); ?></span><span></span></div>
			<?php foreach ( $days as $item ) : ?>
				<?php
				$id           = absint( $item->id );
				$category_ids = self::decode_ids( $item->course_category_ids );
				$key          = substr( (string) $item->training_date, 0, 7 );
				if ( $key !== $month ) {
					$month = $key;
					echo '<h3 class="lplms-days-month">' . esc_html( date_i18n( 'F Y', strtotime( $item->training_date . ' 12:00:00' ) ) ) . '</h3>';
				}
				$branch  = self::get_branch( $item->branch_id );
				$color   = $branch && ! empty( $branch->color ) ? $branch->color : '#b8c0cc';
				$state   = $item->training_date < $today ? ' is-past' : ( $item->training_date === $today ? ' is-today' : '' );
				$extras  = array_filter( array( $item->day_type, $category_ids ? sprintf( _n( '%d catégorie', '%d catégories', count( $category_ids ), 'logiplanning-lms' ), count( $category_ids ) ) : '', $item->message_id ? __( 'message', 'logiplanning-lms' ) : '', trim( wp_strip_all_tags( (string) $item->custom_message ) ) !== '' ? __( 'message personnalisé', 'logiplanning-lms' ) : '' ) );
				?>
				<div class="lplms-day-row<?php echo esc_attr( $state . ( $item->is_vacation ? ' is-vacation' : '' ) ); ?>" data-lplms-day-row style="--branch: <?php echo esc_attr( $color ); ?>">
					<div class="lplms-day-main">
						<label class="lplms-day-date"><span class="screen-reader-text"><?php esc_html_e( 'Date', 'logiplanning-lms' ); ?></span><input type="date" name="days[<?php echo esc_attr( $id ); ?>][training_date]" value="<?php echo esc_attr( $item->training_date ); ?>" required>
							<span class="lplms-day-vac"><input type="checkbox" name="days[<?php echo esc_attr( $id ); ?>][is_vacation]" value="1" data-lplms-vacation-toggle <?php checked( $item->is_vacation ); ?>> <?php esc_html_e( 'Vacances', 'logiplanning-lms' ); ?></span></label>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Branche', 'logiplanning-lms' ); ?></span><?php self::render_branch_select( 'days[' . $id . '][branch_id]', $item->branch_id ); ?></label>
						<label data-lplms-vacation-field><span class="screen-reader-text"><?php esc_html_e( 'Modalité', 'logiplanning-lms' ); ?></span><select name="days[<?php echo esc_attr( $id ); ?>][modality]" data-lplms-modality-select><option value="presentiel" <?php selected( $item->modality, 'presentiel' ); ?>><?php esc_html_e( 'Présentiel', 'logiplanning-lms' ); ?></option><option value="distance" <?php selected( $item->modality, 'distance' ); ?>><?php esc_html_e( 'À distance', 'logiplanning-lms' ); ?></option></select></label>
						<label data-lplms-vacation-field><span class="screen-reader-text"><?php esc_html_e( 'Horaire', 'logiplanning-lms' ); ?></span><input name="days[<?php echo esc_attr( $id ); ?>][schedule]" data-lplms-schedule-input value="<?php echo esc_attr( $item->schedule ); ?>" placeholder="08h00-11h30"></label>
						<div data-lplms-vacation-field class="lplms-day-cell"><?php self::render_teacher_fields( 'days[' . $id . ']', $item->teacher, self::decode_teacher_by_group( $item ), $group_ids ); ?></div>
						<div data-lplms-vacation-field data-lplms-classroom-field class="lplms-day-cell"><?php self::render_classroom_fields( 'days[' . $id . ']', isset( $item->classroom ) ? $item->classroom : '', self::decode_classroom_by_group( $item ), $group_ids ); ?></div>
						<div class="lplms-day-actions">
							<a class="button-link" href="<?php echo esc_url( self::admin_url( 'days', array( 'plan_id' => $plan_id, 'day_id' => $id ) ) . '#lplms-day-form' ); ?>"><?php esc_html_e( 'Modifier', 'logiplanning-lms' ); ?></a>
							<a class="button-link lplms-delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'lplms_delete_day', 'id' => $id, 'plan_id' => $plan_id ), admin_url( 'admin-post.php' ) ), 'lplms_delete_day_' . $id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Supprimer cette journée ?', 'logiplanning-lms' ) ); ?>');"><?php esc_html_e( 'Supprimer', 'logiplanning-lms' ); ?></a>
						</div>
					</div>
					<details class="lplms-day-more">
						<summary><?php esc_html_e( 'Plus', 'logiplanning-lms' ); ?><?php echo $extras ? ' <span>· ' . esc_html( implode( ' · ', $extras ) ) . '</span>' : ''; ?></summary>
						<div class="lplms-day-more-grid">
							<label data-lplms-vacation-field><?php esc_html_e( 'Type de journée', 'logiplanning-lms' ); ?><input name="days[<?php echo esc_attr( $id ); ?>][day_type]" value="<?php echo esc_attr( $item->day_type ); ?>"></label>
							<label><?php esc_html_e( 'Message réutilisable', 'logiplanning-lms' ); ?><?php self::render_message_select( 'days[' . $id . '][message_id]', $item->message_id ); ?></label>
							<label class="lplms-wide"><?php esc_html_e( 'Message personnalisé', 'logiplanning-lms' ); ?><textarea name="days[<?php echo esc_attr( $id ); ?>][custom_message]" rows="2"><?php echo esc_textarea( $item->custom_message ); ?></textarea></label>
							<div class="lplms-wide" data-lplms-vacation-field><strong><?php esc_html_e( 'Catégories de cours Sensei (max 3)', 'logiplanning-lms' ); ?></strong><?php self::render_category_multiselect( $category_ids, 'days[' . $id . '][course_category_ids]' ); ?><?php self::render_course_code_preview( $category_ids ); ?></div>
							<div class="lplms-wide lplms-propagate" data-lplms-vacation-field>
								<span><?php esc_html_e( 'Recopier sur les autres journées de la même branche et de la même modalité :', 'logiplanning-lms' ); ?></span>
								<button type="submit" class="button lplms-propagate-button" name="apply_teacher_from_day" value="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'cet enseignant', 'logiplanning-lms' ); ?></button>
								<button type="submit" class="button lplms-propagate-button" name="apply_classroom_from_day" value="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'cette salle', 'logiplanning-lms' ); ?></button>
							</div>
						</div>
					</details>
				</div>
			<?php endforeach; ?>
			<div class="lplms-days-save"><?php submit_button( __( 'Enregistrer toutes les journées', 'logiplanning-lms' ), 'primary large', 'submit', false ); ?><span class="lplms-dirty" data-lplms-dirty hidden><?php esc_html_e( 'Modifications non enregistrées', 'logiplanning-lms' ); ?></span></div>
		</form>
		<?php
	}

	private static function render_day_quick_form( $plan_id, $day ) {
		$category_ids = self::decode_ids( $day->course_category_ids );
		$plan = self::get_plan( $plan_id );
		$group_ids = self::get_plan_group_ids( $plan );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-quick-form">
			<input type="hidden" name="action" value="lplms_save_day">
			<input type="hidden" name="id" value="<?php echo esc_attr( $day->id ); ?>">
			<input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan_id ); ?>">
			<?php wp_nonce_field( 'lplms_save_day', self::NONCE ); ?>
			<label><?php esc_html_e( 'Date', 'logiplanning-lms' ); ?><input type="date" name="training_date" required value="<?php echo esc_attr( $day->training_date ); ?>"></label>
			<label class="lplms-checkline"><input type="checkbox" name="is_vacation" value="1" data-lplms-vacation-toggle <?php checked( $day->is_vacation ); ?>> <?php esc_html_e( 'Vacances', 'logiplanning-lms' ); ?></label>
			<label><?php esc_html_e( 'Branche', 'logiplanning-lms' ); ?><?php self::render_branch_select( 'branch_id', $day->branch_id ); ?></label>
			<label data-lplms-vacation-field><?php esc_html_e( 'Modalité', 'logiplanning-lms' ); ?><select name="modality" data-lplms-modality-select><option value="presentiel" <?php selected( $day->modality, 'presentiel' ); ?>><?php esc_html_e( 'Présentiel', 'logiplanning-lms' ); ?></option><option value="distance" <?php selected( $day->modality, 'distance' ); ?>><?php esc_html_e( 'À distance', 'logiplanning-lms' ); ?></option></select></label>
			<div data-lplms-vacation-field><?php self::render_teacher_fields( '', $day->teacher, self::decode_teacher_by_group( $day ), $group_ids ); ?></div>
			<label data-lplms-vacation-field><?php esc_html_e( 'Horaire', 'logiplanning-lms' ); ?><input name="schedule" data-lplms-schedule-input value="<?php echo esc_attr( $day->schedule ); ?>"></label>
			<div data-lplms-vacation-field data-lplms-classroom-field><?php self::render_classroom_fields( '', isset( $day->classroom ) ? $day->classroom : '', self::decode_classroom_by_group( $day ), $group_ids ); ?></div>
			<label data-lplms-vacation-field><?php esc_html_e( 'Type de journée', 'logiplanning-lms' ); ?><input name="day_type" value="<?php echo esc_attr( $day->day_type ); ?>"></label>
			<label><?php esc_html_e( 'Message réutilisable', 'logiplanning-lms' ); ?><?php self::render_message_select( 'message_id', $day->message_id ); ?></label>
			<div class="lplms-quick-wide" data-lplms-vacation-field>
				<strong><?php esc_html_e( 'Catégories de cours Sensei', 'logiplanning-lms' ); ?></strong>
				<?php self::render_category_multiselect( $category_ids ); ?>
			</div>
			<label class="lplms-quick-wide"><?php esc_html_e( 'Message personnalisé', 'logiplanning-lms' ); ?><textarea name="custom_message" rows="4"><?php echo esc_textarea( $day->custom_message ); ?></textarea></label>
			<label class="lplms-checkline lplms-quick-wide"><input type="checkbox" name="apply_to_same_branch" value="1"> <?php esc_html_e( 'Reporter ces informations sur les autres journées avec la même branche dans ce plan', 'logiplanning-lms' ); ?></label>
			<div class="lplms-quick-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer cette journée', 'logiplanning-lms' ); ?></button>
			</div>
		</form>
		<?php
	}

	private static function render_day_form( $plan_id, $day = null ) {
		$category_ids = $day ? self::decode_ids( $day->course_category_ids ) : array();
		$plan = self::get_plan( $plan_id );
		$group_ids = self::get_plan_group_ids( $plan );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-panel">
			<input type="hidden" name="action" value="lplms_save_day">
			<input type="hidden" name="id" value="<?php echo esc_attr( $day ? $day->id : 0 ); ?>">
			<input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan_id ); ?>">
			<?php wp_nonce_field( 'lplms_save_day', self::NONCE ); ?>
			<label><?php esc_html_e( 'Date', 'logiplanning-lms' ); ?><input type="date" name="training_date" required value="<?php echo esc_attr( $day ? $day->training_date : '' ); ?>"></label>
			<label><input type="checkbox" name="is_vacation" value="1" data-lplms-vacation-toggle <?php checked( $day && $day->is_vacation ); ?>> <?php esc_html_e( 'Vacances', 'logiplanning-lms' ); ?></label>
			<label><?php esc_html_e( 'Branche', 'logiplanning-lms' ); ?><?php self::render_branch_select( 'branch_id', $day ? $day->branch_id : 0 ); ?></label>
			<label data-lplms-vacation-field><?php esc_html_e( 'Modalité', 'logiplanning-lms' ); ?><select name="modality" data-lplms-modality-select><option value="presentiel" <?php selected( $day ? $day->modality : '', 'presentiel' ); ?>><?php esc_html_e( 'Présentiel', 'logiplanning-lms' ); ?></option><option value="distance" <?php selected( $day ? $day->modality : '', 'distance' ); ?>><?php esc_html_e( 'À distance', 'logiplanning-lms' ); ?></option></select></label>
			<div data-lplms-vacation-field><?php self::render_teacher_fields( '', $day ? $day->teacher : '', $day ? self::decode_teacher_by_group( $day ) : array(), $group_ids ); ?></div>
			<label data-lplms-vacation-field><?php esc_html_e( 'Horaire', 'logiplanning-lms' ); ?><input name="schedule" class="regular-text" data-lplms-schedule-input value="<?php echo esc_attr( $day ? $day->schedule : '' ); ?>"></label>
			<div data-lplms-vacation-field data-lplms-classroom-field><?php self::render_classroom_fields( '', $day && isset( $day->classroom ) ? $day->classroom : '', $day ? self::decode_classroom_by_group( $day ) : array(), $group_ids ); ?></div>
			<label data-lplms-vacation-field><?php esc_html_e( 'Type de journée', 'logiplanning-lms' ); ?><input name="day_type" class="regular-text" value="<?php echo esc_attr( $day ? $day->day_type : '' ); ?>"></label>
			<div class="lplms-editor-field" data-lplms-vacation-field><strong><?php esc_html_e( 'Catégories de cours Sensei (max 3)', 'logiplanning-lms' ); ?></strong><?php self::render_category_multiselect( $category_ids ); ?></div>
			<label><?php esc_html_e( 'Message réutilisable', 'logiplanning-lms' ); ?><?php self::render_message_select( 'message_id', $day ? $day->message_id : 0 ); ?></label>
			<div class="lplms-editor-field">
				<strong><?php esc_html_e( 'Message personnalisé', 'logiplanning-lms' ); ?></strong>
				<?php
				wp_editor(
					$day ? $day->custom_message : '',
					'lplms_custom_message',
					array(
						'textarea_name' => 'custom_message',
						'textarea_rows' => 8,
						'media_buttons' => true,
						'teeny'         => false,
						'quicktags'     => true,
					)
				);
				?>
			</div>
			<label class="lplms-checkline"><input type="checkbox" name="apply_to_same_branch" value="1"> <?php esc_html_e( 'Reporter ces informations sur les autres journées avec la même branche dans ce plan', 'logiplanning-lms' ); ?></label>
			<?php submit_button( __( 'Enregistrer la journée', 'logiplanning-lms' ) ); ?>
			<?php if ( $day ) : ?><a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'lplms_delete_day', 'id' => $day->id, 'plan_id' => $plan_id ), admin_url( 'admin-post.php' ) ), 'lplms_delete_day_' . $day->id ) ); ?>"><?php esc_html_e( 'Supprimer', 'logiplanning-lms' ); ?></a><?php endif; ?>
		</form>
		<?php
	}

	private static function render_branches_page() {
		$branches = self::get_branches();
		?>
		<h2><?php esc_html_e( 'Branches', 'logiplanning-lms' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Couleur', 'logiplanning-lms' ); ?></th><th><?php esc_html_e( 'Nom', 'logiplanning-lms' ); ?></th><th><?php esc_html_e( 'Horaire par défaut', 'logiplanning-lms' ); ?></th><th><?php esc_html_e( 'Description', 'logiplanning-lms' ); ?></th><th><?php esc_html_e( 'Action', 'logiplanning-lms' ); ?></th></tr></thead><tbody>
			<?php foreach ( $branches as $branch ) : ?>
				<?php $form_id = 'lplms-branch-' . absint( $branch->id ); ?>
				<tr>
					<td><input form="<?php echo esc_attr( $form_id ); ?>" type="color" name="color" value="<?php echo esc_attr( $branch->color ); ?>"></td>
					<td><input form="<?php echo esc_attr( $form_id ); ?>" name="name" class="regular-text" value="<?php echo esc_attr( $branch->name ); ?>" required></td>
					<td><input form="<?php echo esc_attr( $form_id ); ?>" name="default_schedule" class="regular-text" value="<?php echo esc_attr( isset( $branch->default_schedule ) ? $branch->default_schedule : '' ); ?>" placeholder="08h00-11h30"></td>
					<td><textarea form="<?php echo esc_attr( $form_id ); ?>" name="description" rows="2" class="large-text"><?php echo esc_textarea( $branch->description ); ?></textarea></td>
					<td>
						<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="lplms_save_branch">
							<input type="hidden" name="id" value="<?php echo esc_attr( $branch->id ); ?>">
							<?php wp_nonce_field( 'lplms_save_branch', self::NONCE ); ?>
							<button type="submit" class="button"><?php esc_html_e( 'Enregistrer', 'logiplanning-lms' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>
		<h2><?php esc_html_e( 'Ajouter une branche', 'logiplanning-lms' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-panel">
			<input type="hidden" name="action" value="lplms_save_branch"><?php wp_nonce_field( 'lplms_save_branch', self::NONCE ); ?>
			<label><?php esc_html_e( 'Nom', 'logiplanning-lms' ); ?><input name="name" class="regular-text" required></label>
			<label><?php esc_html_e( 'Couleur', 'logiplanning-lms' ); ?><input type="color" name="color" value="#2f7d7b"></label>
			<label><?php esc_html_e( 'Horaire par défaut', 'logiplanning-lms' ); ?><input name="default_schedule" class="regular-text" placeholder="08h00-11h30"></label>
			<label><?php esc_html_e( 'Description', 'logiplanning-lms' ); ?><textarea name="description" rows="3" class="large-text"></textarea></label>
			<?php submit_button( __( 'Ajouter', 'logiplanning-lms' ) ); ?>
		</form>
		<?php
	}

	private static function render_messages_page() {
		$messages = self::get_messages( false );
		?>
		<h2><?php esc_html_e( 'Messages globaux réutilisables', 'logiplanning-lms' ); ?></h2>
		<div class="lplms-message-admin-list">
			<?php foreach ( $messages as $message ) : ?>
				<div class="lplms-panel">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="lplms_save_message">
						<input type="hidden" name="id" value="<?php echo esc_attr( $message->id ); ?>">
						<?php wp_nonce_field( 'lplms_save_message', self::NONCE ); ?>
						<label><?php esc_html_e( 'Titre', 'logiplanning-lms' ); ?><input name="title" class="regular-text" value="<?php echo esc_attr( $message->title ); ?>" required></label>
						<label><?php esc_html_e( 'Catégorie interne', 'logiplanning-lms' ); ?><input name="internal_category" class="regular-text" value="<?php echo esc_attr( $message->internal_category ); ?>"></label>
						<label><input type="checkbox" name="active" value="1" <?php checked( $message->active ); ?>> <?php esc_html_e( 'Actif', 'logiplanning-lms' ); ?></label>
						<div class="lplms-editor-field">
							<strong><?php esc_html_e( 'Contenu', 'logiplanning-lms' ); ?></strong>
							<?php
							wp_editor(
								$message->content,
								'lplms_message_content_' . absint( $message->id ),
								array(
									'textarea_name' => 'content',
									'textarea_rows' => 6,
									'media_buttons' => true,
									'teeny'         => false,
									'quicktags'     => true,
								)
							);
							?>
						</div>
						<?php submit_button( __( 'Enregistrer le message', 'logiplanning-lms' ), 'primary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer ce message ? Les journées qui l’utilisent repasseront sur aucun message réutilisable.', 'logiplanning-lms' ) ); ?>');">
						<input type="hidden" name="action" value="lplms_delete_message">
						<input type="hidden" name="id" value="<?php echo esc_attr( $message->id ); ?>">
						<?php wp_nonce_field( 'lplms_delete_message_' . absint( $message->id ), self::NONCE ); ?>
						<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Supprimer', 'logiplanning-lms' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
			<?php if ( empty( $messages ) ) : ?><p><?php esc_html_e( 'Aucun message pour le moment.', 'logiplanning-lms' ); ?></p><?php endif; ?>
		</div>
		<h2><?php esc_html_e( 'Ajouter un message', 'logiplanning-lms' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-panel">
			<input type="hidden" name="action" value="lplms_save_message"><?php wp_nonce_field( 'lplms_save_message', self::NONCE ); ?>
			<label><?php esc_html_e( 'Titre', 'logiplanning-lms' ); ?><input name="title" class="regular-text" required></label>
			<label><?php esc_html_e( 'Catégorie interne', 'logiplanning-lms' ); ?><input name="internal_category" class="regular-text"></label>
			<label><input type="checkbox" name="active" value="1" checked> <?php esc_html_e( 'Actif', 'logiplanning-lms' ); ?></label>
			<div class="lplms-editor-field">
				<strong><?php esc_html_e( 'Contenu', 'logiplanning-lms' ); ?></strong>
				<?php
				wp_editor(
					'',
					'lplms_message_content',
					array(
						'textarea_name' => 'content',
						'textarea_rows' => 8,
						'media_buttons' => true,
						'teeny'         => false,
						'quicktags'     => true,
					)
				);
				?>
			</div>
			<?php submit_button( __( 'Ajouter le message', 'logiplanning-lms' ) ); ?>
		</form>
		<?php
	}

	private static function render_settings_page() {
		?>
		<h2><?php esc_html_e( 'Réglages', 'logiplanning-lms' ); ?></h2>
		<div class="lplms-panel">
			<p><strong><?php esc_html_e( 'Shortcode', 'logiplanning-lms' ); ?></strong> <code>[logiplanning_lms classe="logisticien-1a"]</code></p>
			<p><?php esc_html_e( 'Le plugin utilise les groupes Sensei de type post "group" et les catégories Sensei "course-category". La progression est lue depuis Sensei LMS quand disponible, avec fallback sur les statuts de leçons.', 'logiplanning-lms' ); ?></p>
			<p><?php esc_html_e( 'Export PDF : cette version fournit une page imprimable via le calendrier et prépare les exports agenda ICS. Une génération PDF serveur pourra être ajoutée ensuite.', 'logiplanning-lms' ); ?></p>
		</div>
		<?php
	}

	private static function render_export_page() {
		?>
		<h2><?php esc_html_e( 'Export / Agenda', 'logiplanning-lms' ); ?></h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-panel">
			<input type="hidden" name="action" value="lplms_export_excel">
			<?php wp_nonce_field( 'lplms_export_excel' ); ?>
			<h3><?php esc_html_e( 'Export Excel', 'logiplanning-lms' ); ?></h3>
			<label><?php esc_html_e( 'Plan', 'logiplanning-lms' ); ?><?php self::render_plan_select( 'plan_id', 0 ); ?></label>
			<label><?php esc_html_e( 'Début', 'logiplanning-lms' ); ?><input type="date" name="start"></label>
			<label><?php esc_html_e( 'Fin', 'logiplanning-lms' ); ?><input type="date" name="end"></label>
			<?php submit_button( __( 'Exporter le tableau Excel', 'logiplanning-lms' ) ); ?>
		</form>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lplms-panel">
			<input type="hidden" name="action" value="lplms_export_ics">
			<?php wp_nonce_field( 'lplms_export_ics' ); ?>
			<h3><?php esc_html_e( 'Export agenda', 'logiplanning-lms' ); ?></h3>
			<label><?php esc_html_e( 'Plan', 'logiplanning-lms' ); ?><?php self::render_plan_select( 'plan_id', 0 ); ?></label>
			<label><?php esc_html_e( 'Début', 'logiplanning-lms' ); ?><input type="date" name="start"></label>
			<label><?php esc_html_e( 'Fin', 'logiplanning-lms' ); ?><input type="date" name="end"></label>
			<?php submit_button( __( 'Exporter en .ics', 'logiplanning-lms' ) ); ?>
		</form>
		<?php
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'classe' => '', 'plan_id' => 0 ), $atts, self::SHORTCODE );
		if ( ! is_user_logged_in() ) {
			return self::front_notice( __( 'Connecte-toi pour consulter ton planning.', 'logiplanning-lms' ) );
		}

		$plan = ! empty( $atts['plan_id'] ) ? self::get_plan( absint( $atts['plan_id'] ) ) : self::get_plan_by_slug( sanitize_title( $atts['classe'] ) );
		if ( ! $plan ) {
			return self::front_notice( __( 'Planning introuvable.', 'logiplanning-lms' ) );
		}
		if ( ! self::current_user_can_view_plan( $plan ) ) {
			return self::front_notice( __( 'Ce planning n’est pas disponible pour ton compte.', 'logiplanning-lms' ) );
		}

		return self::render_plan_app( $plan );
	}

	private static function front_notice( $text ) {
		self::enqueue_front_assets();
		return '<div class="lpl-notice">' . esc_html( $text ) . '</div>';
	}

	/**
	 * Planning d'un plan, aux couleurs LogiOne : prochain cours mis en avant, liste ou calendrier,
	 * « À reprendre » et modules en autonomie à côté. Les données sont dessinées par assets/front.js.
	 */
	private static function render_plan_app( $plan, $teacher_view = false, $toolbar_html = '' ) {
		self::enqueue_front_assets();
		$is_member     = self::get_user_plan_group_id( $plan, get_current_user_id() ) > 0;
		$show_progress = $is_member || ! $teacher_view;
		$groups        = self::get_group_labels( self::get_plan_group_ids( $plan ) );
		$data          = array(
			'planTitle'       => $plan->title,
			'today'           => current_time( 'Y-m-d' ),
			'days'            => self::build_frontend_days( $plan->id ),
			'autonomyCourses' => self::build_autonomy_courses(),
			'showProgress'    => $show_progress,
		);
		ob_start();
		?>
		<section class="lpl-app<?php echo $teacher_view ? ' is-teacher' : ''; ?>" data-lpl-app data-logiplanning="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
			<header class="lpl-hero">
				<div class="lpl-hero-top">
					<span class="lpl-brand"><?php echo self::logo(); // phpcs:ignore -- SVG statique du module. ?></span>
					<a class="lpl-btn lpl-btn-yellow" href="<?php echo esc_url( self::get_public_ics_url( $plan, 0, 'future' ) ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3.5 10h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><?php esc_html_e( 'Ajouter tout l’agenda à venir', 'logiplanning-lms' ); ?></a>
				</div>
				<?php echo $toolbar_html; // phpcs:ignore -- construit et échappé par l'appelant. ?>
				<div class="lpl-hero-main">
					<div class="lpl-hero-text">
						<p class="lpl-kicker"><?php echo esc_html( sprintf( __( 'Plan de formation · %s', 'logiplanning-lms' ), $groups ) ); ?></p>
						<h2 class="lpl-title"><?php echo esc_html( $plan->title ); ?></h2>
						<div class="lpl-next" data-lpl-next aria-live="polite"></div>
					</div>
				</div>
			</header>
			<div class="lpl-body">
				<div class="lpl-toolbar">
					<div class="lpl-switch" role="group" aria-label="<?php esc_attr_e( 'Affichage', 'logiplanning-lms' ); ?>">
						<button type="button" data-lpl-view="list" aria-pressed="true"><?php esc_html_e( 'Liste', 'logiplanning-lms' ); ?></button>
						<button type="button" data-lpl-view="calendar" aria-pressed="false"><?php esc_html_e( 'Calendrier', 'logiplanning-lms' ); ?></button>
					</div>
					<div class="lpl-monthnav">
						<button type="button" class="lpl-icon-btn" data-lpl-prev aria-label="<?php esc_attr_e( 'Période précédente', 'logiplanning-lms' ); ?>">‹</button>
						<strong class="lpl-period" data-lpl-title></strong>
						<button type="button" class="lpl-icon-btn" data-lpl-next-month aria-label="<?php esc_attr_e( 'Période suivante', 'logiplanning-lms' ); ?>">›</button>
						<button type="button" class="lpl-btn lpl-btn-ghost" data-lpl-today><?php esc_html_e( 'Aujourd’hui', 'logiplanning-lms' ); ?></button>
					</div>
					<label class="lpl-search">
						<span class="lpl-sr"><?php esc_html_e( 'Trouver un module', 'logiplanning-lms' ); ?></span>
						<select data-lpl-module-search><option value=""><?php esc_html_e( 'Trouver un module…', 'logiplanning-lms' ); ?></option></select>
					</label>
				</div>
				<div class="lpl-module-result" data-lpl-module-result hidden></div>
				<div class="lpl-layout">
					<div class="lpl-main">
						<div class="lpl-list" data-lpl-list></div>
						<div class="lpl-month" data-lpl-grid hidden></div>
					</div>
					<aside class="lpl-side">
						<?php if ( $show_progress ) : ?>
							<section class="lpl-card lpl-resume">
								<h3><?php esc_html_e( 'À reprendre', 'logiplanning-lms' ); ?></h3>
								<p class="lpl-muted"><?php esc_html_e( 'Cours pas encore terminés, sur des journées déjà passées.', 'logiplanning-lms' ); ?></p>
								<div data-lpl-overdue></div>
							</section>
						<?php endif; ?>
						<section class="lpl-card lpl-autonomy">
							<h3><?php esc_html_e( 'Modules en autonomie', 'logiplanning-lms' ); ?></h3>
							<p class="lpl-muted"><?php esc_html_e( 'À suivre à ton rythme pendant les deux années de formation.', 'logiplanning-lms' ); ?></p>
							<div data-lpl-autonomy></div>
						</section>
					</aside>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Dans LogiOne : l'enseignant choisit un plan et le voit comme ses élèves.
	 * Ceux qui peuvent gérer les plans ont un accès direct aux journées et à l'import.
	 */
	public static function render_teacher_portal() {
		if ( ! current_user_can( 'lp_planning_view' ) && ! self::current_user_can_manage_plugin() ) {
			return self::front_notice( __( 'LogiPlanning est réservé aux enseignants.', 'logiplanning-lms' ) );
		}
		$plans = self::get_plans();
		$param = 'lpl_plan';
		$id    = isset( $_GET[ $param ] ) ? absint( $_GET[ $param ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple choix d'affichage.
		$fav   = self::favorite_plan_id( get_current_user_id() );
		$favorite_plan = $fav ? self::get_plan( $fav ) : null;
		if ( ! $favorite_plan ) {
			$fav = 0;
		}
		// Sans choix dans l'adresse : le plan favori, sinon le premier.
		$plan  = $id ? self::get_plan( $id ) : ( $favorite_plan ? $favorite_plan : ( $plans ? $plans[0] : null ) );
		if ( ! $plan ) {
			$manage = self::current_user_can_manage_plugin() ? ' <a href="' . esc_url( self::admin_url( 'plans' ) ) . '">' . esc_html__( 'Créer un plan', 'logiplanning-lms' ) . '</a>' : '';
			self::enqueue_front_assets();
			return '<div class="lpl-notice">' . esc_html__( 'Aucun plan de formation pour le moment.', 'logiplanning-lms' ) . $manage . '</div>';
		}
		$bar = '<div class="lpl-teacherbar"><form method="get" class="lpl-plan-pick">';
		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $key === $param || is_array( $value ) ) {
				continue;
			}
			$bar .= '<input type="hidden" name="' . esc_attr( sanitize_key( $key ) ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $value ) ) ) . '">';
		}
		$bar .= '<label><span>' . esc_html__( 'Plan de formation', 'logiplanning-lms' ) . '</span><select name="' . esc_attr( $param ) . '" onchange="this.form.submit()">';
		usort( $plans, static function ( $a, $b ) use ( $fav ) {
			return ( (int) $b->id === $fav ) <=> ( (int) $a->id === $fav ); // Le favori en tête de liste.
		} );
		foreach ( $plans as $item ) {
			$bar .= '<option value="' . esc_attr( $item->id ) . '"' . selected( (int) $item->id, (int) $plan->id, false ) . '>' . esc_html( ( (int) $item->id === $fav ? '★ ' : '' ) . $item->title . ' — ' . self::get_group_labels( self::get_plan_group_ids( $item ) ) ) . '</option>';
		}
		$bar .= '</select></label><noscript><button class="lpl-btn">' . esc_html__( 'Afficher', 'logiplanning-lms' ) . '</button></noscript></form>';
		$is_fav  = (int) $plan->id === $fav;
		$fav_url = add_query_arg(
			array(
				'lpl_fav'   => $is_fav ? 0 : (int) $plan->id,
				'_lplnonce' => wp_create_nonce( 'lpl_favorite_plan' ),
			),
			remove_query_arg( array( 'lpl_fav_saved' ) )
		);
		$bar .= '<a class="lpl-btn lpl-fav' . ( $is_fav ? ' is-fav' : '' ) . '" href="' . esc_url( $fav_url ) . '" data-lpl-fav title="' . esc_attr( $is_fav ? __( 'Ce plan s’affiche d’office quand vous ouvrez LogiPlanning. Cliquer pour retirer le favori.', 'logiplanning-lms' ) : __( 'Afficher ce plan d’office quand vous ouvrez LogiPlanning.', 'logiplanning-lms' ) ) . '">'
			. ( $is_fav ? '★ ' . esc_html__( 'Mon plan favori', 'logiplanning-lms' ) : '☆ ' . esc_html__( 'Définir comme plan favori', 'logiplanning-lms' ) ) . '</a>';
		if ( self::current_user_can_manage_plugin() ) {
			$bar .= '<a class="lpl-btn lpl-btn-ghost" href="' . esc_url( self::admin_url( 'days', array( 'plan_id' => $plan->id ) ) ) . '">' . esc_html__( 'Modifier les journées', 'logiplanning-lms' ) . '</a>';
			$bar .= '<a class="lpl-btn lpl-btn-ghost" href="' . esc_url( self::admin_url( 'import', array( 'plan_id' => $plan->id ) ) ) . '">' . esc_html__( 'Importer un fichier', 'logiplanning-lms' ) . '</a>';
		}
		if ( isset( $_GET['lpl_fav_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$bar .= '<p class="lpl-fav-notice" role="status">' . ( '1' === $_GET['lpl_fav_saved'] ? esc_html__( 'Plan favori enregistré : il s’affichera d’office à votre prochaine visite.', 'logiplanning-lms' ) : esc_html__( 'Plan favori retiré.', 'logiplanning-lms' ) ) . '</p>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$bar .= '<p class="lpl-teacher-hint">' . esc_html__( 'Vous voyez ce planning comme vos élèves. Leur page : ', 'logiplanning-lms' ) . '<code>[logiplanning_lms classe="' . esc_html( $plan->slug ) . '"]</code></p></div>';

		return self::render_plan_app( $plan, true, $bar );
	}

	private static function build_frontend_days( $plan_id ) {
		$items = array();
		$plan  = self::get_plan( $plan_id );
		$user_group_id = $plan ? self::get_user_plan_group_id( $plan, get_current_user_id() ) : 0;
		$group_ids = $plan ? self::get_plan_group_ids( $plan ) : array();
		foreach ( self::get_days( $plan_id ) as $day ) {
			$branch = self::get_branch( $day->branch_id );
			$categories = self::decode_ids( $day->course_category_ids );
			$courses = $day->is_vacation ? array() : self::get_courses_for_categories( $categories );
			$course_items = array();
			foreach ( $courses as $course ) {
				$course_items[] = array(
					'id'       => (int) $course->ID,
					'title'    => get_the_title( $course ),
					'url'      => get_permalink( $course ),
					'progress' => self::get_course_progress( $course->ID, get_current_user_id() ),
					'image'    => self::get_course_image_url( $course->ID ),
					'summary'  => self::get_course_summary( $course ),
				);
			}
			$message = self::get_day_message( $day, $branch );
			$state = self::get_day_progress_state( $course_items );
			$items[] = array(
				'id'          => (int) $day->id,
				'date'        => $day->training_date,
				'dateLabel'   => self::format_date( $day->training_date ),
				'branch'      => $day->is_vacation ? __( 'Vacances', 'logiplanning-lms' ) : ( $branch ? $branch->name : '' ),
				'color'       => $day->is_vacation ? '#b8c0cc' : ( $branch ? $branch->color : '#2f7d7b' ),
				'modality'    => $day->is_vacation ? '' : self::format_modality( $day->modality ),
				'teacher'     => $day->is_vacation ? '' : self::get_day_teacher_for_group( $day, $user_group_id ),
				'schedule'    => $day->is_vacation ? '' : $day->schedule,
				'classroom'   => $day->is_vacation ? '' : self::get_day_classroom_for_group( $day, $user_group_id ),
				'groupDetails'=> $day->is_vacation ? array() : self::get_day_group_details( $day, $group_ids ),
				'message'     => $message,
				'courses'     => $day->is_vacation ? array() : $course_items,
				'state'       => $day->is_vacation ? array( 'key' => 'none', 'icon' => '', 'label' => '' ) : $state,
				'isVacation'  => (bool) $day->is_vacation,
				'icsUrl'      => $plan ? self::get_public_ics_url( $plan, (int) $day->id, 'single' ) : '',
			);
		}
		return $items;
	}

	private static function get_course_image_url( $course_id ) {
		$image = get_the_post_thumbnail_url( $course_id, 'thumbnail' );
		return $image ? esc_url_raw( $image ) : '';
	}

	private static function get_course_summary( $course ) {
		if ( ! $course instanceof WP_Post ) {
			return '';
		}
		$text = has_excerpt( $course ) ? $course->post_excerpt : $course->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		return wp_trim_words( $text, 34, '...' );
	}

	private static function build_autonomy_courses() {
		$courses = self::get_courses_for_category_slug( 'art32-modules-a-traiter-en-autonomie' );
		$items   = array();

		foreach ( $courses as $course ) {
			$items[] = array(
				'id'       => (int) $course->ID,
				'title'    => get_the_title( $course ),
				'url'      => get_permalink( $course ),
				'progress' => self::get_course_progress( $course->ID, get_current_user_id() ),
				'image'    => self::get_course_image_url( $course->ID ),
				'summary'  => self::get_course_summary( $course ),
			);
		}

		return $items;
	}

	private static function get_day_message( $day, $branch ) {
		if ( $day->is_vacation ) {
			return __( 'Vacances - aucun cours prévu.', 'logiplanning-lms' );
		}
		if ( trim( (string) $day->custom_message ) ) {
			return wp_kses_post( $day->custom_message );
		}
		if ( $day->message_id ) {
			$message = self::get_message( $day->message_id );
			if ( $message && $message->active ) {
				return wp_kses_post( $message->content );
			}
		}
		if ( $branch && 'ECG' === $branch->name ) {
			return __( 'Aujourd’hui, vous avez ECG. Le contenu sera transmis par l’enseignant concerné.', 'logiplanning-lms' );
		}
		return '';
	}

	private static function get_day_progress_state( $courses ) {
		if ( empty( $courses ) ) {
			return array( 'key' => 'none', 'icon' => '', 'label' => '' );
		}
		$done = 0;
		$started = 0;
		foreach ( $courses as $course ) {
			if ( $course['progress'] >= 100 ) {
				$done++;
			}
			if ( $course['progress'] > 0 ) {
				$started++;
			}
		}
		if ( $done === count( $courses ) ) {
			return array( 'key' => 'done', 'icon' => '✓', 'label' => __( 'Terminé', 'logiplanning-lms' ) );
		}
		if ( $started > 0 ) {
			return array( 'key' => 'progress', 'icon' => '●', 'label' => __( 'En cours', 'logiplanning-lms' ) );
		}
		return array( 'key' => 'todo', 'icon' => '✕', 'label' => __( 'Non commencé', 'logiplanning-lms' ) );
	}

	public static function handle_save_plan() {
		self::require_admin_post( 'lplms_save_plan' );
		global $wpdb;
		$tables = self::tables();
		$id     = absint( $_POST['id'] ?? 0 );
		$title  = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$slug   = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		$group_ids = isset( $_POST['group_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['group_ids'] ) ) ) ) : array();
		$group     = ! empty( $group_ids ) ? absint( $group_ids[0] ) : 0;
		if ( ! $title ) {
			self::redirect( 'plans', 'title_missing' );
		}
		$slug   = $slug ? $slug : sanitize_title( $title );
		$wanted = $slug;
		$data = array( 'title' => $title, 'slug' => $slug, 'group_id' => $group, 'group_ids' => wp_json_encode( $group_ids ), 'updated_at' => current_time( 'mysql' ) );
		if ( $id ) {
			$duplicate = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['plans']} WHERE slug = %s AND id <> %d", $slug, $id ) );
			if ( $duplicate ) {
				$data['slug'] = self::make_unique_plan_slug( $slug );
			}
			$wpdb->update( $tables['plans'], $data, array( 'id' => $id ) );
		} else {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['plans']} WHERE slug = %s", $slug ) );
			if ( $existing ) {
				$data['slug'] = self::make_unique_plan_slug( $slug );
			}
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $tables['plans'], $data );
		}
		if ( $data['slug'] !== $wanted ) {
			self::redirect( 'plans', 'slug_changed', array( 'lplms_slug' => $data['slug'] ) );
		}
		self::redirect( 'plans', 'saved' );
	}

	public static function handle_duplicate_plan() {
		self::require_admin_post( 'lplms_duplicate_plan' );
		global $wpdb;
		$tables = self::tables();

		$source_id = absint( $_POST['source_plan_id'] ?? 0 );
		$title     = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$slug      = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		$source    = self::get_plan( $source_id );

		if ( ! $source || ! $title ) {
			self::redirect( 'plans', 'error' );
		}

		if ( ! $slug ) {
			$slug = sanitize_title( $title );
		}
		$slug = self::make_unique_plan_slug( $slug );
		$now  = current_time( 'mysql' );

		$wpdb->insert(
			$tables['plans'],
			array(
				'title'      => $title,
				'slug'       => $slug,
				'group_id'   => absint( $source->group_id ),
				'group_ids'  => isset( $source->group_ids ) ? $source->group_ids : '',
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		$new_plan_id = absint( $wpdb->insert_id );
		if ( ! $new_plan_id ) {
			self::redirect( 'plans', 'error' );
		}

		foreach ( self::get_days( $source_id ) as $day ) {
			$wpdb->insert(
				$tables['days'],
				array(
					'plan_id'             => $new_plan_id,
					'training_date'       => $day->training_date,
					'branch_id'           => absint( $day->branch_id ),
					'modality'            => $day->modality,
					'teacher'             => $day->teacher,
					'teacher_by_group'    => isset( $day->teacher_by_group ) ? $day->teacher_by_group : '',
					'schedule'            => $day->schedule,
					'classroom'           => isset( $day->classroom ) ? $day->classroom : '',
					'classroom_by_group'  => isset( $day->classroom_by_group ) ? $day->classroom_by_group : '',
					'day_type'            => $day->day_type,
					'course_category_ids' => $day->course_category_ids,
					'message_id'          => absint( $day->message_id ),
					'custom_message'      => $day->custom_message,
					'is_vacation'         => absint( $day->is_vacation ),
					'created_at'          => $now,
					'updated_at'          => $now,
				)
			);
		}

		self::redirect( 'days', 'saved', array( 'plan_id' => $new_plan_id ) );
	}

	public static function handle_delete_plan() {
		$id = absint( $_POST['id'] ?? 0 );
		self::require_admin_post( 'lplms_delete_plan_' . $id );
		if ( ! $id ) {
			self::redirect( 'plans', 'error' );
		}

		global $wpdb;
		$tables = self::tables();
		$wpdb->delete( $tables['days'], array( 'plan_id' => $id ), array( '%d' ) );
		$wpdb->delete( $tables['plans'], array( 'id' => $id ), array( '%d' ) );
		self::redirect( 'plans', 'deleted' );
	}

	public static function handle_preview_import() {
		self::require_admin_post( 'lplms_preview_import' );
		$plan_id = absint( $_POST['plan_id'] ?? 0 );
		if ( ! $plan_id || ! self::get_plan( $plan_id ) ) {
			self::redirect( 'import', 'import_plan' );
		}
		if ( empty( $_FILES['planning_file']['tmp_name'] ) ) {
			self::redirect( 'import', 'import_file', array( 'plan_id' => $plan_id ) );
		}
		$parsed = self::parse_import_file( $_FILES['planning_file']['tmp_name'] );
		if ( empty( $parsed['rows'] ) ) {
			self::redirect( 'import', 'import_unreadable', array( 'plan_id' => $plan_id ) );
		}
		$key = 'lplms_import_' . get_current_user_id() . '_' . wp_generate_password( 8, false );
		$payload = array( 'key' => $key, 'plan_id' => $plan_id, 'headers' => $parsed['headers'], 'rows' => $parsed['rows'] );
		set_transient( $key, $payload, HOUR_IN_SECONDS );
		wp_safe_redirect( self::admin_url( 'import', array( 'preview' => $key ) ) );
		exit;
	}

	public static function handle_commit_import() {
		self::require_admin_post( 'lplms_commit_import' );
		$preview = self::get_import_preview( sanitize_text_field( wp_unslash( $_POST['preview_key'] ?? '' ) ) );
		if ( ! $preview ) {
			self::redirect( 'import', 'import_expired' );
		}
		$map     = isset( $_POST['map'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['map'] ) ) : array();
		$count   = 0;
		$skipped = 0;
		foreach ( $preview['rows'] as $row ) {
			$date = self::parse_date( $row[ $map['date'] ?? 0 ] ?? '' );
			if ( ! $date ) {
				$skipped += implode( '', array_map( 'trim', (array) $row ) ) !== '' ? 1 : 0;
				continue;
			}
			$count++;
			self::upsert_import_day(
				absint( $preview['plan_id'] ),
				array(
					'training_date' => $date,
					'branch_name'   => sanitize_text_field( $row[ $map['branch'] ?? 1 ] ?? '' ),
					'modality'      => sanitize_text_field( $row[ $map['modality'] ?? 2 ] ?? '' ),
					'teacher'       => sanitize_text_field( $row[ $map['teacher'] ?? 3 ] ?? '' ),
					'schedule'      => sanitize_text_field( $row[ $map['schedule'] ?? 4 ] ?? '' ),
				)
			);
		}
		delete_transient( $preview['key'] );
		self::redirect( 'days', 'imported', array( 'plan_id' => absint( $preview['plan_id'] ), 'lplms_count' => $count, 'lplms_skipped' => $skipped ) );
	}

	public static function handle_save_day() {
		self::require_admin_post( 'lplms_save_day' );
		global $wpdb;
		$tables = self::tables();
		$id = absint( $_POST['id'] ?? 0 );
		$plan_id = absint( $_POST['plan_id'] ?? 0 );
		$cats = isset( $_POST['course_category_ids'] ) ? array_slice( array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['course_category_ids'] ) ) ) ), 0, 3 ) : array();
		$teacher_by_group = isset( $_POST['teacher_by_group'] ) ? self::sanitize_teacher_by_group( wp_unslash( $_POST['teacher_by_group'] ) ) : array();
		$classroom_by_group = isset( $_POST['classroom_by_group'] ) ? self::sanitize_group_text_map( wp_unslash( $_POST['classroom_by_group'] ) ) : array();
		$data = array(
			'plan_id'             => $plan_id,
			'training_date'       => sanitize_text_field( wp_unslash( $_POST['training_date'] ?? '' ) ),
			'branch_id'           => absint( $_POST['branch_id'] ?? 0 ),
			'modality'            => self::sanitize_modality( wp_unslash( $_POST['modality'] ?? '' ) ),
			'teacher'             => sanitize_text_field( wp_unslash( $_POST['teacher'] ?? '' ) ),
			'teacher_by_group'    => wp_json_encode( $teacher_by_group ),
			'schedule'            => sanitize_text_field( wp_unslash( $_POST['schedule'] ?? '' ) ),
			'classroom'           => sanitize_text_field( wp_unslash( $_POST['classroom'] ?? '' ) ),
			'classroom_by_group'  => wp_json_encode( $classroom_by_group ),
			'day_type'            => sanitize_text_field( wp_unslash( $_POST['day_type'] ?? '' ) ),
			'course_category_ids' => wp_json_encode( $cats ),
			'message_id'          => absint( $_POST['message_id'] ?? 0 ),
			'custom_message'      => wp_kses_post( wp_unslash( $_POST['custom_message'] ?? '' ) ),
			'is_vacation'         => ! empty( $_POST['is_vacation'] ) ? 1 : 0,
			'updated_at'          => current_time( 'mysql' ),
		);
		if ( 'distance' === $data['modality'] ) {
			$data['classroom']          = '';
			$data['classroom_by_group'] = wp_json_encode( array() );
		}
		if ( $data['is_vacation'] ) {
			$data['teacher']             = '';
			$data['teacher_by_group']    = wp_json_encode( array() );
			$data['schedule']            = '';
			$data['classroom']           = '';
			$data['classroom_by_group']  = wp_json_encode( array() );
			$data['day_type']            = '';
			$data['course_category_ids'] = wp_json_encode( array() );
		}
		if ( $id ) {
			$wpdb->update( $tables['days'], $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $tables['days'], $data );
			$id = absint( $wpdb->insert_id );
		}
		if ( $id && ! empty( $_POST['apply_to_same_branch'] ) ) {
			self::apply_day_data_to_same_branch( $id, $plan_id, $data );
		}
		self::redirect( 'days', 'saved', array( 'plan_id' => $plan_id ) );
	}

	private static function apply_day_data_to_same_branch( $source_id, $plan_id, $data ) {
		global $wpdb;
		$source_id = absint( $source_id );
		$plan_id   = absint( $plan_id );
		$branch_id = absint( $data['branch_id'] ?? 0 );
		if ( ! $source_id || ! $plan_id || ! $branch_id ) {
			return;
		}

		$copy = array_intersect_key(
			$data,
			array_flip(
				array(
					'branch_id',
					'modality',
					'teacher',
					'teacher_by_group',
					'schedule',
					'classroom',
					'classroom_by_group',
					'day_type',
					'course_category_ids',
					'message_id',
					'custom_message',
					'is_vacation',
					'updated_at',
				)
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::tables()['days'] . ' SET branch_id = %d, modality = %s, teacher = %s, teacher_by_group = %s, schedule = %s, classroom = %s, classroom_by_group = %s, day_type = %s, course_category_ids = %s, message_id = %d, custom_message = %s, is_vacation = %d, updated_at = %s WHERE plan_id = %d AND branch_id = %d AND id <> %d',
				absint( $copy['branch_id'] ),
				(string) $copy['modality'],
				(string) $copy['teacher'],
				(string) $copy['teacher_by_group'],
				(string) $copy['schedule'],
				(string) $copy['classroom'],
				(string) $copy['classroom_by_group'],
				(string) $copy['day_type'],
				(string) $copy['course_category_ids'],
				absint( $copy['message_id'] ),
				(string) $copy['custom_message'],
				absint( $copy['is_vacation'] ),
				(string) $copy['updated_at'],
				$plan_id,
				$branch_id,
				$source_id
			)
		);
	}

	public static function handle_bulk_save_days() {
		self::require_admin_post( 'lplms_bulk_save_days' );
		$plan_id = absint( $_POST['plan_id'] ?? 0 );
		$days    = isset( $_POST['days'] ) ? (array) wp_unslash( $_POST['days'] ) : array();

		foreach ( $days as $day_id => $raw_day ) {
			$day_id = absint( $day_id );
			$existing = self::get_day( $day_id );
			if ( ! $existing || absint( $existing->plan_id ) !== $plan_id || ! is_array( $raw_day ) ) {
				continue;
			}
			self::save_day_data( $day_id, $plan_id, $raw_day );
		}

		$teacher_source = absint( $_POST['apply_teacher_from_day'] ?? 0 );
		if ( $teacher_source ) {
			self::apply_day_people_field_to_same_branch_modality( $teacher_source, $plan_id, 'teacher' );
		}

		$classroom_source = absint( $_POST['apply_classroom_from_day'] ?? 0 );
		if ( $classroom_source ) {
			self::apply_day_people_field_to_same_branch_modality( $classroom_source, $plan_id, 'classroom' );
		}

		self::redirect( 'days', 'saved', array( 'plan_id' => $plan_id ) );
	}

	private static function apply_day_people_field_to_same_branch_modality( $source_id, $plan_id, $field ) {
		global $wpdb;
		$source = self::get_day( $source_id );
		if ( ! $source || absint( $source->plan_id ) !== absint( $plan_id ) || $source->is_vacation ) {
			return;
		}
		if ( 'classroom' === $field && 'distance' === $source->modality ) {
			return;
		}

		if ( 'teacher' === $field ) {
			$set = array(
				'teacher'          => $source->teacher,
				'teacher_by_group' => isset( $source->teacher_by_group ) ? $source->teacher_by_group : wp_json_encode( array() ),
				'updated_at'       => current_time( 'mysql' ),
			);
		} elseif ( 'classroom' === $field ) {
			$set = array(
				'classroom'          => isset( $source->classroom ) ? $source->classroom : '',
				'classroom_by_group' => isset( $source->classroom_by_group ) ? $source->classroom_by_group : wp_json_encode( array() ),
				'updated_at'         => current_time( 'mysql' ),
			);
		} else {
			return;
		}

		$wpdb->update(
			self::tables()['days'],
			$set,
			array(
				'plan_id'     => absint( $plan_id ),
				'branch_id'   => absint( $source->branch_id ),
				'modality'    => $source->modality,
				'is_vacation' => 0,
			)
		);
	}

	private static function save_day_data( $id, $plan_id, $raw_day ) {
		global $wpdb;
		$cats = isset( $raw_day['course_category_ids'] ) ? array_slice( array_values( array_filter( array_map( 'absint', (array) $raw_day['course_category_ids'] ) ) ), 0, 3 ) : array();
		$teacher_by_group = isset( $raw_day['teacher_by_group'] ) ? self::sanitize_teacher_by_group( $raw_day['teacher_by_group'] ) : array();
		$classroom_by_group = isset( $raw_day['classroom_by_group'] ) ? self::sanitize_group_text_map( $raw_day['classroom_by_group'] ) : array();
		$data = array(
			'plan_id'             => absint( $plan_id ),
			'training_date'       => sanitize_text_field( $raw_day['training_date'] ?? '' ),
			'branch_id'           => absint( $raw_day['branch_id'] ?? 0 ),
			'modality'            => self::sanitize_modality( $raw_day['modality'] ?? '' ),
			'teacher'             => sanitize_text_field( $raw_day['teacher'] ?? '' ),
			'teacher_by_group'    => wp_json_encode( $teacher_by_group ),
			'schedule'            => sanitize_text_field( $raw_day['schedule'] ?? '' ),
			'classroom'           => sanitize_text_field( $raw_day['classroom'] ?? '' ),
			'classroom_by_group'  => wp_json_encode( $classroom_by_group ),
			'day_type'            => sanitize_text_field( $raw_day['day_type'] ?? '' ),
			'course_category_ids' => wp_json_encode( $cats ),
			'message_id'          => absint( $raw_day['message_id'] ?? 0 ),
			'custom_message'      => wp_kses_post( $raw_day['custom_message'] ?? '' ),
			'is_vacation'         => ! empty( $raw_day['is_vacation'] ) ? 1 : 0,
			'updated_at'          => current_time( 'mysql' ),
		);
		if ( 'distance' === $data['modality'] ) {
			$data['classroom']          = '';
			$data['classroom_by_group'] = wp_json_encode( array() );
		}
		if ( $data['is_vacation'] ) {
			$data['teacher']             = '';
			$data['teacher_by_group']    = wp_json_encode( array() );
			$data['schedule']            = '';
			$data['classroom']           = '';
			$data['classroom_by_group']  = wp_json_encode( array() );
			$data['day_type']            = '';
			$data['course_category_ids'] = wp_json_encode( array() );
		}

		$wpdb->update( self::tables()['days'], $data, array( 'id' => absint( $id ) ) );
	}

	public static function handle_delete_day() {
		global $wpdb;
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! self::current_user_can_manage_plugin() || ! $id || ! check_admin_referer( 'lplms_delete_day_' . $id ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiplanning-lms' ) );
		}
		$wpdb->delete( self::tables()['days'], array( 'id' => $id ) );
		self::redirect( 'days', 'deleted', array( 'plan_id' => absint( $_GET['plan_id'] ?? 0 ) ) );
	}

	public static function handle_save_branch() {
		self::require_admin_post( 'lplms_save_branch' );
		global $wpdb;
		$id = absint( $_POST['id'] ?? 0 );
		$data = array(
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'color' => sanitize_hex_color( wp_unslash( $_POST['color'] ?? '#2f7d7b' ) ),
			'default_schedule' => sanitize_text_field( wp_unslash( $_POST['default_schedule'] ?? '' ) ),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
		);
		if ( $id ) {
			$wpdb->update( self::tables()['branches'], $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( self::tables()['branches'], $data );
		}
		self::redirect( 'branches', 'saved' );
	}

	public static function handle_save_message() {
		self::require_admin_post( 'lplms_save_message' );
		global $wpdb;
		$id = absint( $_POST['id'] ?? 0 );
		$data = array(
			'title' => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'content' => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ),
			'internal_category' => sanitize_text_field( wp_unslash( $_POST['internal_category'] ?? '' ) ),
			'active' => ! empty( $_POST['active'] ) ? 1 : 0,
		);
		if ( $id ) {
			$wpdb->update( self::tables()['messages'], $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( self::tables()['messages'], $data );
		}
		self::redirect( 'messages', 'saved' );
	}

	public static function handle_delete_message() {
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! self::current_user_can_manage_plugin() || ! $id || empty( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'lplms_delete_message_' . $id ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiplanning-lms' ) );
		}
		global $wpdb;
		$wpdb->update( self::tables()['days'], array( 'message_id' => 0 ), array( 'message_id' => $id ) );
		$wpdb->delete( self::tables()['messages'], array( 'id' => $id ) );
		self::redirect( 'messages', 'deleted' );
	}

	public static function handle_export_ics() {
		if ( ! self::current_user_can_manage_plugin() || ! check_admin_referer( 'lplms_export_ics' ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiplanning-lms' ) );
		}
		$plan_id = absint( $_GET['plan_id'] ?? 0 );
		$plan = self::get_plan( $plan_id );
		if ( ! $plan ) {
			self::redirect( 'export', 'error' );
		}
		$days = self::get_days( $plan_id, sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['end'] ?? '' ) ) );
		self::output_ics( $plan, $days );
	}

	public static function handle_export_excel() {
		if ( ! self::current_user_can_manage_plugin() || ! check_admin_referer( 'lplms_export_excel' ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiplanning-lms' ) );
		}
		$plan_id = absint( $_GET['plan_id'] ?? 0 );
		$plan = self::get_plan( $plan_id );
		if ( ! $plan ) {
			self::redirect( 'export', 'error' );
		}
		$days = self::get_days( $plan_id, sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['end'] ?? '' ) ) );
		self::output_excel( $plan, $days );
	}

	public static function handle_public_export_ics() {
		$plan_id = absint( $_GET['plan_id'] ?? 0 );
		$day_id  = absint( $_GET['day_id'] ?? 0 );
		$scope   = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'future';
		$plan = self::get_plan( $plan_id );
		if ( ! $plan || ! is_user_logged_in() || ! self::current_user_can_view_plan( $plan ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiplanning-lms' ) );
		}
		if ( ! check_admin_referer( 'lplms_download_ics_' . $plan_id ) ) {
			wp_die( esc_html__( 'Lien agenda invalide.', 'logiplanning-lms' ) );
		}
		if ( 'single' === $scope && $day_id ) {
			$day = self::get_day( $day_id );
			if ( ! $day || absint( $day->plan_id ) !== $plan_id ) {
				wp_die( esc_html__( 'Journée introuvable.', 'logiplanning-lms' ) );
			}
			self::output_ics( $plan, array( $day ) );
		}

		self::output_ics( $plan, self::get_days( $plan_id, current_time( 'Y-m-d' ) ) );
	}

	private static function output_ics( $plan, $days ) {
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $plan->slug . '.ics' ) . '"' );
		$user_group_id = self::get_user_plan_group_id( $plan, get_current_user_id() );
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Logiprof//LogiPlanning LMS//FR\r\n";
		foreach ( $days as $day ) {
			$branch = $day->is_vacation ? __( 'Vacances', 'logiplanning-lms' ) : self::get_branch_name( $day->branch_id );
			echo "BEGIN:VEVENT\r\n";
			echo 'UID:lplms-' . absint( $day->id ) . '@' . wp_parse_url( home_url(), PHP_URL_HOST ) . "\r\n";
			echo 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\r\n";
			echo self::get_ics_date_lines( $day->training_date, $day->schedule );
			echo 'SUMMARY:' . self::ics_escape( $branch ) . "\r\n";
			echo 'DESCRIPTION:' . self::ics_escape( self::get_ics_description( $day, $user_group_id ) ) . "\r\n";
			echo "END:VEVENT\r\n";
		}
		echo "END:VCALENDAR\r\n";
		exit;
	}

	private static function output_excel( $plan, $days ) {
		nocache_headers();
		header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $plan->slug . '-planning.xls' ) . '"' );
		echo "\xEF\xBB\xBF";
		?>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				table { border-collapse: collapse; }
				th, td { border: 1px solid #999; padding: 6px; vertical-align: top; }
				th { background: #e8eef7; font-weight: bold; }
			</style>
		</head>
		<body>
			<table>
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Plan', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Classes Sensei', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Date', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Branche', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Modalité', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Enseignant', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Enseignants par groupe', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Horaire', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Salle', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Salles par groupe', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Vacances', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Catégories Sensei', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Modules liés', 'logiplanning-lms' ); ?></th>
						<th><?php echo esc_html__( 'Message', 'logiplanning-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $days as $day ) : ?>
						<?php
						$category_ids = self::decode_ids( $day->course_category_ids );
						$is_distance = 'distance' === $day->modality;
						?>
						<tr>
							<td><?php echo esc_html( $plan->title ); ?></td>
							<td><?php echo esc_html( self::get_group_labels( self::get_plan_group_ids( $plan ) ) ); ?></td>
							<td><?php echo esc_html( $day->training_date ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? __( 'Vacances', 'logiplanning-lms' ) : self::get_branch_name( $day->branch_id ) ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? '' : self::format_modality( $day->modality ) ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? '' : $day->teacher ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? '' : self::format_group_text_map( self::decode_teacher_by_group( $day ) ) ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? '' : $day->schedule ); ?></td>
							<td><?php echo esc_html( ( $day->is_vacation || $is_distance ) ? '' : $day->classroom ); ?></td>
							<td><?php echo esc_html( ( $day->is_vacation || $is_distance ) ? '' : self::format_group_text_map( self::decode_classroom_by_group( $day ) ) ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? '' : $day->day_type ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? __( 'Oui', 'logiplanning-lms' ) : __( 'Non', 'logiplanning-lms' ) ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? '' : self::get_category_labels( $category_ids ) ); ?></td>
							<td><?php echo esc_html( $day->is_vacation ? '' : implode( ', ', self::get_course_codes_for_categories( $category_ids ) ) ); ?></td>
							<td><?php echo esc_html( wp_strip_all_tags( self::get_day_message( $day, self::get_branch( $day->branch_id ) ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</body>
		</html>
		<?php
		exit;
	}

	private static function get_public_ics_url( $plan, $day_id = 0, $scope = 'future' ) {
		$plan_id = absint( $plan->id );
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'lplms_download_ics',
					'plan_id' => $plan_id,
					'day_id'  => absint( $day_id ),
					'scope'   => sanitize_key( $scope ),
				),
				admin_url( 'admin-post.php' )
			),
			'lplms_download_ics_' . $plan_id
		);
	}

	private static function get_ics_description( $day, $group_id = 0 ) {
		$teacher = self::get_day_teacher_for_group( $day, $group_id );
		$parts = array_filter(
			array(
				$teacher ? sprintf( __( 'Enseignant : %s', 'logiplanning-lms' ), $teacher ) : '',
				$day->schedule ? sprintf( __( 'Horaire : %s', 'logiplanning-lms' ), $day->schedule ) : '',
				self::get_day_classroom_for_group( $day, $group_id ) ? sprintf( __( 'Salle : %s', 'logiplanning-lms' ), self::get_day_classroom_for_group( $day, $group_id ) ) : '',
				$day->is_vacation ? '' : sprintf( __( 'Modalité : %s', 'logiplanning-lms' ), self::format_modality( $day->modality ) ),
			)
		);

		if ( $day->is_vacation ) {
			$parts[] = __( 'Vacances - aucun cours prévu.', 'logiplanning-lms' );
			return implode( "\n", $parts );
		}

		$courses = self::get_courses_for_categories( self::decode_ids( $day->course_category_ids ) );
		if ( empty( $courses ) ) {
			$parts[] = __( 'Cours prévus : aucun cours prévu.', 'logiplanning-lms' );
			return implode( "\n", $parts );
		}

		$parts[] = __( 'Cours prévus :', 'logiplanning-lms' );
		foreach ( $courses as $course ) {
			$parts[] = sprintf(
				'- %s (%d%%) : %s',
				get_the_title( $course ),
				self::get_course_progress( $course->ID, get_current_user_id() ),
				get_permalink( $course )
			);
		}

		return implode( "\n", $parts );
	}

	private static function get_ics_date_lines( $date, $schedule ) {
		$clean = remove_accents( strtolower( (string) $schedule ) );
		if ( preg_match( '/(\d{1,2})\s*(?:h|:)?\s*(\d{0,2})\s*[-–]\s*(\d{1,2})\s*(?:h|:)?\s*(\d{0,2})/', $clean, $m ) ) {
			$start_hour = min( 23, absint( $m[1] ) );
			$start_min  = '' === $m[2] ? 0 : min( 59, absint( $m[2] ) );
			$end_hour   = min( 23, absint( $m[3] ) );
			$end_min    = '' === $m[4] ? 0 : min( 59, absint( $m[4] ) );

			return sprintf(
				"DTSTART:%sT%02d%02d00\r\nDTEND:%sT%02d%02d00\r\n",
				gmdate( 'Ymd', strtotime( $date ) ),
				$start_hour,
				$start_min,
				gmdate( 'Ymd', strtotime( $date ) ),
				$end_hour,
				$end_min
			);
		}

		return 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $date ) ) . "\r\n";
	}

	private static function parse_import_file( $path ) {
		$content = file_get_contents( $path );
		$content = str_replace( "\xEF\xBB\xBF", '', (string) $content );
		$rows = array();
		if ( preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/is', $content, $matches ) ) {
			foreach ( $matches[1] as $tr ) {
				preg_match_all( '/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr, $cells );
				$rows[] = array_map( array( __CLASS__, 'clean_cell' ), $cells[1] );
			}
		} elseif ( false !== strpos( $content, "\t" ) ) {
			foreach ( preg_split( '/\r\n|\r|\n/', $content ) as $line ) {
				if ( trim( $line ) !== '' ) {
					$rows[] = array_map( 'trim', str_getcsv( $line, "\t" ) );
				}
			}
		} else {
			foreach ( preg_split( '/\r\n|\r|\n/', $content ) as $line ) {
				if ( trim( $line ) !== '' ) {
					$rows[] = array_map( 'trim', str_getcsv( $line, ';' ) );
				}
			}
		}
		$rows = array_values( array_filter( $rows ) );
		$headers = array_shift( $rows );
		return array( 'headers' => array_values( (array) $headers ), 'rows' => array_values( $rows ) );
	}

	private static function clean_cell( $cell ) {
		return trim( html_entity_decode( wp_strip_all_tags( preg_replace( '/<br\s*\/?>/i', ' ', $cell ) ), ENT_QUOTES, get_option( 'blog_charset' ) ) );
	}

	private static function upsert_import_day( $plan_id, $data ) {
		global $wpdb;
		$tables = self::tables();
		$branch_id = self::ensure_branch( $data['branch_name'] );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['days']} WHERE plan_id = %d AND training_date = %s", $plan_id, $data['training_date'] ) );
		$row = array(
			'plan_id' => $plan_id,
			'training_date' => $data['training_date'],
			'branch_id' => $branch_id,
			'modality' => self::sanitize_modality( $data['modality'] ),
			'teacher' => $data['teacher'],
			'schedule' => $data['schedule'],
			'is_vacation' => false !== stripos( remove_accents( $data['branch_name'] ), 'vacance' ) ? 1 : 0,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( $existing ) {
			$wpdb->update( $tables['days'], $row, array( 'id' => absint( $existing ) ) );
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $tables['days'], $row );
		}
	}

	private static function ensure_branch( $name ) {
		global $wpdb;
		$name = trim( $name ) ? trim( $name ) : __( 'Log tronc commun', 'logiplanning-lms' );
		$table = self::tables()['branches'];
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s", $name ) );
		if ( $id ) {
			return absint( $id );
		}
		$wpdb->insert( $table, array( 'name' => $name, 'color' => '#2f7d7b', 'description' => '', 'created_at' => current_time( 'mysql' ) ) );
		return absint( $wpdb->insert_id );
	}

	private static function get_import_preview( $key = '' ) {
		$key = $key ? $key : sanitize_text_field( wp_unslash( $_GET['preview'] ?? '' ) );
		if ( ! $key ) {
			return null;
		}
		$preview = get_transient( $key );
		return is_array( $preview ) ? $preview : null;
	}

	private static function guess_column( $headers, $field ) {
		$needles = array(
			'date' => array( 'date' ),
			'branch' => array( 'branche', 'cours' ),
			'modality' => array( 'modalite', 'modalité' ),
			'teacher' => array( 'enseignant', 'prof' ),
			'schedule' => array( 'horaire', 'heure' ),
		);
		foreach ( $headers as $i => $header ) {
			$clean = remove_accents( strtolower( $header ) );
			foreach ( $needles[ $field ] as $needle ) {
				if ( false !== strpos( $clean, remove_accents( $needle ) ) ) {
					return $i;
				}
			}
		}
		return 0;
	}

	private static function parse_date( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $value, $m ) ) {
			$year = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
			return sprintf( '%04d-%02d-%02d', $year, $m[2], $m[1] );
		}
		$time = strtotime( $value );
		return $time ? gmdate( 'Y-m-d', $time ) : '';
	}

	private static function sanitize_modality( $value ) {
		$value = remove_accents( strtolower( (string) $value ) );
		return false !== strpos( $value, 'distance' ) ? 'distance' : 'presentiel';
	}

	private static function current_user_can_manage_plugin() {
		return current_user_can( 'lp_planning_edit' );
	}

	private static function current_user_can_view_plan( $plan ) {
		if ( current_user_can( 'lp_planning_view' ) || current_user_can( 'edit_posts' ) || self::current_user_can_manage_plugin() || self::current_user_is_logistics_teacher() ) {
			return true;
		}
		return self::current_user_can_access_plan( $plan );
	}

	private static function current_user_is_logistics_teacher() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$user = wp_get_current_user();
		foreach ( (array) $user->roles as $role ) {
			if ( self::is_logistics_teacher_key( $role ) ) {
				return true;
			}
		}

		$wp_roles = wp_roles();
		foreach ( (array) $user->roles as $role ) {
			if ( isset( $wp_roles->roles[ $role ]['name'] ) && self::is_logistics_teacher_key( $wp_roles->roles[ $role ]['name'] ) ) {
				return true;
			}
		}

		foreach ( get_user_meta( $user_id ) as $meta_key => $values ) {
			if ( self::is_logistics_teacher_key( $meta_key ) ) {
				return true;
			}
			foreach ( (array) $values as $value ) {
				if ( self::meta_value_contains_logistics_teacher( maybe_unserialize( $value ) ) ) {
					return true;
				}
			}
		}

		foreach ( self::get_groups() as $group ) {
			if ( ! self::is_logistics_teacher_key( $group->post_name ) && ! self::is_logistics_teacher_key( $group->post_title ) ) {
				continue;
			}
			if ( in_array( $user_id, self::get_group_user_ids( $group->ID ), true ) ) {
				return true;
			}
		}

		return false;
	}

	private static function meta_value_contains_logistics_teacher( $value ) {
		if ( is_scalar( $value ) ) {
			return self::is_logistics_teacher_key( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( self::is_logistics_teacher_key( $key ) || self::meta_value_contains_logistics_teacher( $item ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function is_logistics_teacher_key( $value ) {
		$key = sanitize_title( str_replace( '_', '-', remove_accents( (string) $value ) ) );
		if ( in_array( $key, array( 'enseignant-logistique', 'enseignants-logistique', 'enseignant-logistiques', 'enseignants-logistiques' ), true ) ) {
			return true;
		}
		return ( false !== strpos( $key, 'enseignant' ) || false !== strpos( $key, 'teacher' ) || false !== strpos( $key, 'prof' ) ) && ( false !== strpos( $key, 'logistique' ) || false !== strpos( $key, 'logistics' ) );
	}

	private static function current_user_can_access_plan( $plan ) {
		foreach ( self::get_plan_group_ids( $plan ) as $group_id ) {
			if ( in_array( get_current_user_id(), self::get_group_student_ids( $group_id ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function get_user_plan_group_id( $plan, $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $plan || ! $user_id ) {
			return 0;
		}
		foreach ( self::get_plan_group_ids( $plan ) as $group_id ) {
			if ( in_array( $user_id, self::get_group_student_ids( $group_id ), true ) ) {
				return absint( $group_id );
			}
		}
		return 0;
	}

	private static function get_day_teacher_for_group( $day, $group_id = 0 ) {
		$teachers = self::decode_teacher_by_group( $day );
		$group_id = absint( $group_id );
		if ( $group_id && ! empty( $teachers[ $group_id ] ) ) {
			return $teachers[ $group_id ];
		}
		if ( ! empty( $day->teacher ) ) {
			return $day->teacher;
		}
		foreach ( $teachers as $teacher ) {
			if ( $teacher ) {
				return $teacher;
			}
		}
		return '';
	}

	private static function get_day_classroom_for_group( $day, $group_id = 0 ) {
		if ( isset( $day->modality ) && 'distance' === $day->modality ) {
			return '';
		}
		$classrooms = self::decode_classroom_by_group( $day );
		$group_id = absint( $group_id );
		if ( $group_id && ! empty( $classrooms[ $group_id ] ) ) {
			return $classrooms[ $group_id ];
		}
		if ( ! empty( $day->classroom ) ) {
			return $day->classroom;
		}
		foreach ( $classrooms as $classroom ) {
			if ( $classroom ) {
				return $classroom;
			}
		}
		return '';
	}

	private static function get_day_group_details( $day, $group_ids ) {
		$details = array();
		foreach ( array_map( 'absint', (array) $group_ids ) as $group_id ) {
			$teacher = self::get_day_teacher_for_group( $day, $group_id );
			$classroom = self::get_day_classroom_for_group( $day, $group_id );
			if ( $teacher || $classroom ) {
				$details[] = array(
					'group'     => self::get_group_label( $group_id ),
					'teacher'   => $teacher,
					'classroom' => $classroom,
				);
			}
		}
		return $details;
	}

	private static function get_group_student_ids( $group_id ) {
		return self::get_group_user_ids( $group_id );
	}

	private static function get_group_user_ids( $group_id ) {
		global $wpdb;
		$group_id = absint( $group_id );
		$user_ids = array();
		$tables = array(
			$wpdb->prefix . 'sensei_pro_groups_students',
			$wpdb->prefix . 'sensei_groups_students',
			$wpdb->prefix . 'sensei_group_students',
			$wpdb->prefix . 'sensei_pro_groups_users',
			$wpdb->prefix . 'sensei_groups_users',
			$wpdb->prefix . 'sensei_group_users',
			$wpdb->prefix . 'sensei_pro_groups_members',
			$wpdb->prefix . 'sensei_groups_members',
			$wpdb->prefix . 'sensei_group_members',
			$wpdb->prefix . 'sensei_pro_groups_teachers',
			$wpdb->prefix . 'sensei_groups_teachers',
			$wpdb->prefix . 'sensei_group_teachers',
		);
		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				continue;
			}
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
			if ( ! in_array( 'group_id', $columns, true ) ) {
				continue;
			}
			foreach ( array( 'student_id', 'user_id', 'member_id', 'teacher_id' ) as $user_column ) {
				if ( in_array( $user_column, $columns, true ) ) {
					$user_ids = array_merge( $user_ids, array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT {$user_column} FROM {$table} WHERE group_id = %d", $group_id ) ) ) );
				}
			}
		}
		foreach ( get_post_meta( $group_id ) as $values ) {
			foreach ( (array) $values as $value ) {
				$user_ids = array_merge( $user_ids, self::extract_user_ids_from_meta_value( maybe_unserialize( $value ) ) );
			}
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) );
	}

	private static function extract_user_ids_from_meta_value( $value ) {
		$ids = array();
		if ( is_numeric( $value ) ) {
			$ids[] = absint( $value );
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$ids = array_merge( $ids, self::extract_user_ids_from_meta_value( $item ) );
			}
		}
		return $ids;
	}

	private static function get_course_progress( $course_id, $user_id ) {
		if ( function_exists( 'Sensei' ) && isset( Sensei()->course ) && method_exists( Sensei()->course, 'get_completion_percentage' ) ) {
			return max( 0, min( 100, absint( Sensei()->course->get_completion_percentage( $course_id, $user_id ) ) ) );
		}
		$lesson_ids = self::get_course_lesson_ids( $course_id );
		if ( empty( $lesson_ids ) ) {
			return 0;
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $lesson_ids ), '%d' ) );
		$params = array_merge( array( absint( $user_id ) ), $lesson_ids );
		$done = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT comment_post_ID) FROM {$wpdb->comments} WHERE user_id = %d AND comment_type = 'sensei_lesson_status' AND comment_approved IN ('complete','passed','graded') AND comment_post_ID IN ($placeholders)", $params ) );
		return (int) round( ( absint( $done ) / count( $lesson_ids ) ) * 100 );
	}

	private static function get_course_lesson_ids( $course_id ) {
		if ( function_exists( 'Sensei' ) && isset( Sensei()->course ) && method_exists( Sensei()->course, 'course_lessons' ) ) {
			return array_map( 'absint', (array) Sensei()->course->course_lessons( $course_id, 'publish', 'ids' ) );
		}
		return get_posts( array( 'post_type' => 'lesson', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_lesson_course', 'meta_value' => absint( $course_id ), 'fields' => 'ids' ) );
	}

	private static function get_courses_for_categories( $category_ids ) {
		$category_ids = array_values( array_filter( array_map( 'absint', $category_ids ) ) );
		if ( empty( $category_ids ) ) {
			return array();
		}
		return get_posts( array( 'post_type' => 'course', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'tax_query' => array( array( 'taxonomy' => 'course-category', 'field' => 'term_id', 'terms' => $category_ids ) ) ) );
	}

	private static function get_course_codes_for_categories( $category_ids ) {
		$codes = array();
		foreach ( self::get_courses_for_categories( $category_ids ) as $course ) {
			$code = self::get_course_code( get_the_title( $course ) );
			if ( $code ) {
				$codes[] = $code;
			}
		}
		return array_values( array_unique( $codes ) );
	}

	private static function get_course_code( $title ) {
		if ( preg_match( '/\bM\s*([0-9]{3}[A-Za-z]?)\b/u', (string) $title, $matches ) ) {
			return 'M' . strtoupper( $matches[1] );
		}
		return '';
	}

	private static function get_courses_for_category_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => 'course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'course-category',
						'field'    => 'slug',
						'terms'    => array( $slug ),
					),
				),
			)
		);
	}

	private static function get_groups() {
		return get_posts( array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	}

	private static function get_plans() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM " . self::tables()['plans'] . ' ORDER BY title ASC' );
	}

	private static function get_plan( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['plans'] . ' WHERE id = %d', absint( $id ) ) );
	}

	private static function get_plan_by_slug( $slug ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['plans'] . ' WHERE slug = %s', sanitize_title( $slug ) ) );
	}

	private static function make_unique_plan_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			$slug = 'plan';
		}
		$base = $slug;
		$i    = 2;
		while ( self::get_plan_by_slug( $slug ) ) {
			$slug = $base . '-' . $i;
			$i++;
		}
		return $slug;
	}

	private static function get_first_plan_id() {
		global $wpdb;
		return absint( $wpdb->get_var( 'SELECT id FROM ' . self::tables()['plans'] . ' ORDER BY title ASC LIMIT 1' ) );
	}

	private static function get_days( $plan_id, $start = '', $end = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::tables()['days'] . ' WHERE plan_id = %d';
		$params = array( absint( $plan_id ) );
		if ( $start ) {
			$sql .= ' AND training_date >= %s';
			$params[] = $start;
		}
		if ( $end ) {
			$sql .= ' AND training_date <= %s';
			$params[] = $end;
		}
		$sql .= ' ORDER BY training_date ASC';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	private static function get_day( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['days'] . ' WHERE id = %d', absint( $id ) ) );
	}

	private static function get_branches() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::tables()['branches'] . ' ORDER BY name ASC' );
	}

	private static function get_branch( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['branches'] . ' WHERE id = %d', absint( $id ) ) );
	}

	private static function get_branch_name( $id ) {
		$branch = self::get_branch( $id );
		return $branch ? $branch->name : '';
	}

	private static function get_messages( $active_only = true ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::tables()['messages'];
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		return $wpdb->get_results( $sql . ' ORDER BY title ASC' );
	}

	private static function get_message( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['messages'] . ' WHERE id = %d', absint( $id ) ) );
	}

	private static function decode_ids( $json ) {
		$ids = json_decode( (string) $json, true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	private static function get_category_labels( $category_ids ) {
		$labels = array();
		foreach ( array_map( 'absint', (array) $category_ids ) as $category_id ) {
			$term = get_term( $category_id, 'course-category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$labels[] = $term->name;
			}
		}
		return implode( ', ', $labels );
	}

	private static function decode_teacher_by_group( $day ) {
		return self::decode_group_text_map( $day, 'teacher_by_group' );
	}

	private static function sanitize_teacher_by_group( $raw ) {
		return self::sanitize_group_text_map( $raw );
	}

	private static function decode_classroom_by_group( $day ) {
		return self::decode_group_text_map( $day, 'classroom_by_group' );
	}

	private static function decode_group_text_map( $day, $property ) {
		if ( ! $day || empty( $day->{$property} ) ) {
			return array();
		}
		$decoded = json_decode( (string) $day->{$property}, true );
		return is_array( $decoded ) ? self::sanitize_group_text_map( $decoded ) : array();
	}

	private static function sanitize_group_text_map( $raw ) {
		$values = array();
		foreach ( (array) $raw as $group_id => $value ) {
			$group_id = absint( $group_id );
			$value = sanitize_text_field( (string) $value );
			if ( $group_id && $value ) {
				$values[ $group_id ] = $value;
			}
		}
		return $values;
	}

	private static function format_group_text_map( $values ) {
		$items = array();
		foreach ( (array) $values as $group_id => $value ) {
			$value = trim( (string) $value );
			if ( $value ) {
				$items[] = self::get_group_label( $group_id ) . ' : ' . $value;
			}
		}
		return implode( "\n", $items );
	}

	private static function format_category_names( $ids ) {
		$names = array();
		foreach ( $ids as $id ) {
			$term = get_term( $id, 'course-category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return implode( ', ', $names );
	}

	/** Plan proposé par défaut : celui de l'adresse, sinon le seul plan, sinon le dernier modifié. */
	private static function default_plan_id() {
		$id = absint( $_GET['plan_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id && self::get_plan( $id ) ) {
			return $id;
		}
		global $wpdb;
		return absint( $wpdb->get_var( 'SELECT id FROM ' . self::tables()['plans'] . ' ORDER BY updated_at DESC, id DESC LIMIT 1' ) );
	}

	private static function render_plan_select( $name, $selected ) {
		echo '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Choisir un plan', 'logiplanning-lms' ) . '</option>';
		foreach ( self::get_plans() as $plan ) {
			echo '<option value="' . esc_attr( $plan->id ) . '" ' . selected( $selected, $plan->id, false ) . '>' . esc_html( $plan->title ) . '</option>';
		}
		echo '</select>';
	}

	private static function render_group_select( $name, $selected, $groups = null ) {
		$groups = null === $groups ? self::get_groups() : $groups;
		echo '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Choisir un groupe Sensei', 'logiplanning-lms' ) . '</option>';
		foreach ( $groups as $group ) {
			echo '<option value="' . esc_attr( $group->ID ) . '" ' . selected( $selected, $group->ID, false ) . '>' . esc_html( $group->post_title ) . '</option>';
		}
		echo '</select>';
	}

	private static function render_group_multiselect( $name, $selected, $groups = null ) {
		$groups   = null === $groups ? self::get_groups() : $groups;
		$selected = array_map( 'absint', (array) $selected );
		echo '<div class="lplms-group-picker">';
		foreach ( $groups as $group ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $group->ID ) . '" ' . checked( in_array( absint( $group->ID ), $selected, true ), true, false ) . '> ' . esc_html( $group->post_title ) . '</label>';
		}
		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'Aucun groupe Sensei trouvé.', 'logiplanning-lms' ) . '</p>';
		}
		echo '</div><span class="description">' . esc_html__( 'Coche ou décoche les classes liées à ce plan.', 'logiplanning-lms' ) . '</span>';
	}

	private static function render_branch_select( $name, $selected ) {
		echo '<select name="' . esc_attr( $name ) . '" data-lplms-branch-select>';
		foreach ( self::get_branches() as $branch ) {
			echo '<option value="' . esc_attr( $branch->id ) . '" data-schedule="' . esc_attr( isset( $branch->default_schedule ) ? $branch->default_schedule : '' ) . '" ' . selected( $selected, $branch->id, false ) . '>' . esc_html( $branch->name ) . '</option>';
		}
		echo '</select>';
	}

	private static function render_message_select( $name, $selected ) {
		echo '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Aucun message', 'logiplanning-lms' ) . '</option>';
		foreach ( self::get_messages() as $message ) {
			echo '<option value="' . esc_attr( $message->id ) . '" ' . selected( $selected, $message->id, false ) . '>' . esc_html( $message->title ) . '</option>';
		}
		echo '</select>';
	}

	private static function render_teacher_fields( $prefix, $teacher, $teacher_by_group, $group_ids ) {
		$teacher_name = $prefix ? $prefix . '[teacher]' : 'teacher';
		$map_name = $prefix ? $prefix . '[teacher_by_group]' : 'teacher_by_group';
		$group_ids = array_values( array_filter( array_map( 'absint', (array) $group_ids ) ) );
		echo '<div class="lplms-group-specific-fields">';
		echo '<label>' . esc_html__( 'Enseignant par défaut', 'logiplanning-lms' ) . '<input name="' . esc_attr( $teacher_name ) . '" value="' . esc_attr( $teacher ) . '"></label>';
		if ( count( $group_ids ) > 1 ) {
			echo '<details class="lplms-fields-by-group" open><summary>' . esc_html__( 'Enseignants par groupe classe', 'logiplanning-lms' ) . '</summary>';
			foreach ( $group_ids as $group_id ) {
				echo '<label><span>' . esc_html( self::get_group_label( $group_id ) ) . '</span><input name="' . esc_attr( $map_name . '[' . $group_id . ']' ) . '" value="' . esc_attr( $teacher_by_group[ $group_id ] ?? '' ) . '" placeholder="' . esc_attr__( 'Utilise l’enseignant par défaut si vide', 'logiplanning-lms' ) . '"></label>';
			}
			echo '</details>';
		}
		echo '</div>';
	}

	private static function render_classroom_fields( $prefix, $classroom, $classroom_by_group, $group_ids ) {
		$classroom_name = $prefix ? $prefix . '[classroom]' : 'classroom';
		$map_name = $prefix ? $prefix . '[classroom_by_group]' : 'classroom_by_group';
		$group_ids = array_values( array_filter( array_map( 'absint', (array) $group_ids ) ) );
		echo '<div class="lplms-group-specific-fields">';
		echo '<label>' . esc_html__( 'Salle par défaut', 'logiplanning-lms' ) . '<input name="' . esc_attr( $classroom_name ) . '" value="' . esc_attr( $classroom ) . '"></label>';
		if ( count( $group_ids ) > 1 ) {
			echo '<details class="lplms-fields-by-group" open><summary>' . esc_html__( 'Salles par groupe classe', 'logiplanning-lms' ) . '</summary>';
			foreach ( $group_ids as $group_id ) {
				echo '<label><span>' . esc_html( self::get_group_label( $group_id ) ) . '</span><input name="' . esc_attr( $map_name . '[' . $group_id . ']' ) . '" value="' . esc_attr( $classroom_by_group[ $group_id ] ?? '' ) . '" placeholder="' . esc_attr__( 'Utilise la salle par défaut si vide', 'logiplanning-lms' ) . '"></label>';
			}
			echo '</details>';
		}
		echo '</div>';
	}

	private static function render_category_multiselect( $selected, $field_name = 'course_category_ids' ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'course-category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		echo '<div class="lplms-category-picker" data-lplms-category-picker data-lplms-max="3">';
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$codes = self::get_course_codes_for_categories( array( $term->term_id ) );
				echo '<label><input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $term->term_id ) . '" data-lplms-course-codes="' . esc_attr( implode( ',', $codes ) ) . '" ' . checked( in_array( $term->term_id, $selected, true ), true, false ) . '> ' . esc_html( $term->name ) . '</label>';
			}
		}
		echo '</div><button type="button" class="button lplms-clear-select" data-lplms-clear-categories>' . esc_html__( 'Aucune catégorie', 'logiplanning-lms' ) . '</button><span class="description">' . esc_html__( 'Coche jusqu’à 3 catégories. Clique sur "Aucune catégorie" pour vider le choix.', 'logiplanning-lms' ) . '</span>';
	}

	private static function render_course_code_preview( $category_ids ) {
		$codes = self::get_course_codes_for_categories( $category_ids );
		echo '<div class="lplms-course-code-preview" data-lplms-course-preview>';
		echo $codes ? esc_html( implode( ', ', $codes ) ) : '<span>' . esc_html__( 'Aucun module lié', 'logiplanning-lms' ) . '</span>';
		echo '</div>';
	}

	private static function get_group_label( $group_id ) {
		$post = get_post( $group_id );
		return $post ? $post->post_title : __( 'Aucun groupe', 'logiplanning-lms' );
	}

	private static function get_group_labels( $group_ids ) {
		$labels = array();
		foreach ( array_map( 'absint', (array) $group_ids ) as $group_id ) {
			if ( $group_id ) {
				$labels[] = self::get_group_label( $group_id );
			}
		}
		return ! empty( $labels ) ? implode( ', ', $labels ) : __( 'Aucun groupe', 'logiplanning-lms' );
	}

	private static function get_plan_group_ids( $plan ) {
		if ( ! $plan ) {
			return array();
		}
		$ids = array();
		if ( isset( $plan->group_ids ) && trim( (string) $plan->group_ids ) ) {
			$decoded = json_decode( (string) $plan->group_ids, true );
			if ( is_array( $decoded ) ) {
				$ids = array_map( 'absint', $decoded );
			}
		}
		if ( empty( $ids ) && ! empty( $plan->group_id ) ) {
			$ids[] = absint( $plan->group_id );
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private static function format_date( $date ) {
		$time = strtotime( $date );
		return $time ? date_i18n( get_option( 'date_format' ), $time ) : '';
	}

	private static function format_modality( $value ) {
		return 'distance' === $value ? __( 'À distance', 'logiplanning-lms' ) : __( 'Présentiel', 'logiplanning-lms' );
	}

	private static function ics_escape( $text ) {
		return str_replace( array( "\\", "\n", "\r", ',', ';' ), array( '\\\\', '\\n', '', '\,', '\;' ), wp_strip_all_tags( (string) $text ) );
	}

	private static function require_admin_post( $action ) {
		if ( ! self::current_user_can_manage_plugin() || empty( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), $action ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logiplanning-lms' ) );
		}
	}

	private static function redirect( $tab, $notice, $args = array() ) {
		wp_safe_redirect( self::admin_url( $tab, array_merge( $args, array( 'lplms_notice' => $notice ) ) ) );
		exit;
	}
}

LogiOne_Planning::init();
