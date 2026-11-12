<?php
/**
 * Tuiles de contenu de LogiOne : chaque menu devient une tuile du portail.
 * Description: Les ressources des élèves en tuiles : rubriques, liens, pages et outils intégrés, gérés par l'administration. Présentation LogiOne by LogiProf.
 * Version: 0.10.0
 * Author: LogiProf
 * Text Domain: logiprof-espace-enseignants
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiPilot_Tiles {
	const VERSION         = '0.10.0';
	const OPTION_VERSION  = 'logipilot_tiles_version';
	const EDITOR_SHORTCODE = 'logipilot_tuiles_editeur';
	const SLUG            = 'logione-tuiles';
	const SHORTCODE       = 'logipilot_tuile_contenu';
	const LEGACY_SHORTCODE = 'logipilot_tuile_contenu_menu';
	const DEFAULT_MENU_ID = 'principal';
	const OPTION_MENUS    = 'logipilot_tiles_menus';
	const OPTION_ITEMS    = 'logipilot_tiles_items';
	const OPTION_SETTINGS = 'logipilot_tiles_settings';
	const OPTION_SEEDED   = 'logipilot_tiles_seeded';
	const NOTICE_TRANSIENT_PREFIX = 'logipilot_tiles_notice_';
	const ACTION_SAVE     = 'logipilot_tiles_save';
	const NONCE_ACTION    = 'logipilot_tiles_save_settings';
	const NONCE_NAME      = 'logipilot_tiles_nonce';
	/** Présentation en liste : un lien « automatique » s'ouvre dans un nouvel onglet (comportement historique). */
	const LIST_AUTO_NEW_TAB = false;
	/** Présentation par défaut (tuiles, depuis 0.3.0) et paramètres d'adresse de la navigation en tuiles. */
	const DEFAULT_THEME       = 'logione';
	const DEFAULT_THEME_SINCE = '0.3.0';
	const DEFAULT_HERO        = '#313a45';
	const PARAM_ITEM          = 'lh';
	const PARAM_MENU          = 'lhm';

	private static $instance_count = 0;
	/** Vrai pendant l'affichage d'une page dans tuiles de contenu (évite tuiles de contenu dans tuiles de contenu). */
	private static $reading = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_public_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( self::LEGACY_SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( self::EDITOR_SHORTCODE, array( __CLASS__, 'render_editor_shortcode' ) );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ) );
		// Icônes importées : SVG accepté pour les personnes qui gèrent tuiles de contenu, nettoyé à l'envoi.
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'check_svg_filetype' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'sanitize_svg_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( __CLASS__, 'sanitize_svg_upload' ) );
	}

	public static function activate() {
		if ( false === get_option( self::OPTION_SEEDED, false ) ) {
			add_option( self::OPTION_ITEMS, self::default_items(), '', false );
			add_option( self::OPTION_SETTINGS, self::default_settings(), '', false );
			add_option( self::OPTION_MENUS, array( self::default_menu() ), '', false );
			add_option( self::OPTION_SEEDED, self::VERSION, '', false );
			update_option( self::OPTION_VERSION, self::VERSION, false );
			return;
		}

		if ( false === get_option( self::OPTION_SETTINGS, false ) ) {
			add_option( self::OPTION_SETTINGS, self::default_settings(), '', false );
		}

		if ( false === get_option( self::OPTION_MENUS, false ) ) {
			add_option( self::OPTION_MENUS, self::legacy_menu_collection(), '', false );
		}
	}

	/**
	 * Mise à jour vers 0.3.0 : les menus existants (contenu, réglages, rôles) sont conservés ;
	 * seule la présentation passe aux tuiles. Des couleurs personnalisées choisies en 0.2.x restent
	 * telles quelles. Rien n'est supprimé.
	 */
	public static function maybe_upgrade() {
		$stored = (string) get_option( self::OPTION_VERSION, '' );

		if ( '' !== $stored && version_compare( $stored, self::DEFAULT_THEME_SINCE, '>=' ) ) {
			return;
		}

		// Avant 0.2.0, il n'existait aucun choix de présentation : tout passe aux tuiles.
		$keep_custom = '' !== $stored && version_compare( $stored, '0.2.0', '>=' );
		$upgrade     = static function ( $settings ) use ( $keep_custom ) {
			$settings = is_array( $settings ) ? $settings : array();
			if ( ! ( $keep_custom && isset( $settings['theme'] ) && 'custom' === $settings['theme'] ) ) {
				$settings['theme'] = self::DEFAULT_THEME;
			}
			return $settings;
		};

		$menus = get_option( self::OPTION_MENUS, false );

		if ( is_array( $menus ) ) {
			foreach ( $menus as $index => $menu ) {
				if ( is_array( $menu ) ) {
					$menus[ $index ]['settings'] = $upgrade( isset( $menu['settings'] ) ? $menu['settings'] : array() );
				}
			}
			update_option( self::OPTION_MENUS, $menus, false );
		}

		$settings = get_option( self::OPTION_SETTINGS, false );

		if ( is_array( $settings ) ) {
			update_option( self::OPTION_SETTINGS, $upgrade( $settings ), false );
		}

		update_option( self::OPTION_VERSION, self::VERSION, false );
	}

	/** Droit nécessaire pour créer et modifier les menus : l'administration (LogiOne peut le relier à son droit « tuiles de contenu »). */
	public static function capability() {
		return (string) apply_filters( 'logipilot_tiles_capability', 'manage_options' );
	}

	public static function current_user_can_edit() {
		return is_user_logged_in() && current_user_can( self::capability() );
	}

	/** Menus proposés dans l'éditeur : tous (tuiles de contenu est géré par l'administration, identique pour tous). */
	private static function editor_menus() {
		return self::get_menus();
	}

	public static function register_public_assets() {
		wp_register_style(
			self::SLUG,
			plugins_url( 'assets/public.css', __FILE__ ),
			array( 'dashicons' ),
			self::VERSION
		);

		wp_register_script(
			self::SLUG,
			plugins_url( 'assets/public.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);
	}

	public static function register_admin_menu() {
		add_menu_page(
			__( 'tuiles de contenu', 'logiprof-espace-enseignants' ),
			__( 'tuiles de contenu', 'logiprof-espace-enseignants' ),
			self::capability(),
			self::SLUG,
			array( __CLASS__, 'render_admin_page' ),
			self::menu_icon(),
			58
		);
	}

	private static function menu_icon() {
		$file = plugin_dir_path( __FILE__ ) . 'assets/menu.svg';
		$svg  = is_readable( $file ) ? file_get_contents( $file ) : '';

		return $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-welcome-learn-more';
	}

	public static function logo_url( $on_dark = false ) {
		return LOGIPILOT_URL . 'assets/' . ( $on_dark ? 'logione-logo-blanc.svg' : 'logione-logo.svg' );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( substr( (string) $hook, -strlen( '_page_' . self::SLUG ) ) !== '_page_' . self::SLUG ) {
			return;
		}

		self::enqueue_editor_assets();
	}

	/** Éditeur des menus : utilisable dans l'administration et dans une page (LogiOne, [logipilot_tuiles_editeur]). */
	/** Adresses des icônes déjà importées, pour l'aperçu de l'éditeur. */
	private static function icon_images_for_editor( $menus ) {
		$out = array();
		foreach ( (array) $menus as $menu ) {
			foreach ( (array) ( $menu['items'] ?? array() ) as $item ) {
				$url = self::icon_image_url( $item );
				if ( '' !== $url ) {
					$out[ (string) absint( $item['icon_image'] ) ] = $url;
				}
			}
		}

		foreach ( (array) $menus as $menu ) {
			$tile_icon = absint( $menu['settings']['tile_icon'] ?? 0 );
			$url       = $tile_icon ? self::icon_image_url( array( 'icon_image' => $tile_icon ) ) : '';
			if ( '' !== $url ) {
				$out[ (string) $tile_icon ] = $url;
			}
		}

		return (object) $out;
	}

	public static function enqueue_editor_assets() {
		if ( current_user_can( 'upload_files' ) ) {
			wp_enqueue_media();
		}

		$menus          = self::editor_menus();
		$active_menu_id = self::get_active_menu_id( $menus );
		$active_menu    = self::get_menu_by_id( $menus, $active_menu_id );

		if ( ! $active_menu ) {
			$active_menu    = reset( $menus );
			$active_menu_id = $active_menu['id'];
		}

		self::register_public_assets();

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( self::SLUG );
		wp_enqueue_style(
			self::SLUG . '-admin',
			plugins_url( 'assets/admin.css', __FILE__ ),
			array( self::SLUG, 'dashicons' ),
			self::VERSION
		);

		wp_enqueue_script( self::SLUG );
		wp_enqueue_script(
			self::SLUG . '-admin',
			plugins_url( 'assets/admin.js', __FILE__ ),
			array( 'jquery', 'jquery-ui-sortable', self::SLUG ),
			self::VERSION,
			true
		);

		wp_localize_script(
			self::SLUG . '-admin',
			'logiprofEspaceEnseignantsAdmin',
			array(
				'menus'              => $menus,
				'activeMenuId'       => $active_menu_id,
				'items'              => $active_menu['items'],
				'settings'           => $active_menu['settings'],
				'defaultSettings'    => self::default_settings(),
				'defaultAppearance'  => self::default_appearance_settings(),
				'pages'              => self::get_pages_for_admin(),
				'roles'              => self::get_roles(),
				'adminUrl'           => admin_url(),
				'logoUrl'            => self::logo_url( true ),
				'logoDarkUrl'        => self::logo_url( false ),
				'shortcode'          => self::SHORTCODE,
				'legacyShortcode'    => self::LEGACY_SHORTCODE,
				'icons'              => self::icon_library(),
				'iconImages'         => self::icon_images_for_editor( $menus ),
				'tilePresets'        => self::tile_presets(),
				'tileSwatches'       => self::tile_swatches(),
				'strings'            => self::admin_strings(),
			)
		);
	}

	public static function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
			esc_html__( 'Configurer', 'logiprof-espace-enseignants' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	public static function render_admin_page() {
		if ( ! self::current_user_can_edit() ) {
			wp_die( esc_html__( 'Vous n’avez pas les droits nécessaires.', 'logiprof-espace-enseignants' ), '', array( 'response' => 403 ) );
		}

		echo self::render_editor( false ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** [logipilot_tuiles_editeur] : l'éditeur des menus dans une page du site (enseignants autorisés). */
	public static function render_editor_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p class="lpee-editor-denied">' . esc_html__( 'Connectez-vous pour modifier les tuiles.', 'logiprof-espace-enseignants' ) . '</p>';
		}

		if ( ! self::current_user_can_edit() ) {
			return '<p class="lpee-editor-denied">' . esc_html__( 'Vous n’avez pas le droit de modifier les tuiles de contenu.', 'logiprof-espace-enseignants' ) . '</p>';
		}

		self::enqueue_editor_assets();

		return self::render_editor( true );
	}

	public static function render_editor( $in_page = false ) {
		$menus         = self::editor_menus();
		$active_menu_id = self::get_active_menu_id( $menus );
		$active_menu   = self::get_menu_by_id( $menus, $active_menu_id );

		if ( ! $active_menu ) {
			$active_menu    = reset( $menus );
			$active_menu_id = $active_menu['id'];
		}

		$settings      = $active_menu['settings'];
		$roles         = self::get_roles();
		$admin_notice  = self::consume_admin_notice();
		$shortcode     = self::menu_shortcode( $active_menu_id );
		$return_url    = $in_page ? remove_query_arg( array( 'updated', 'lpee_menu' ), home_url( add_query_arg( array() ) ) ) : '';
		ob_start();
		?>
		<div class="<?php echo $in_page ? 'lpee-admin lpee-admin-in-page' : 'wrap lpee-admin'; ?>">
			<header class="lpee-admin-head">
				<img class="lpee-admin-logo" src="<?php echo esc_url( self::logo_url( true ) ); ?>" alt="LogiOne by LogiProf" width="220" height="58">
				<p><?php esc_html_e( 'Tuiles de contenu : rubriques, liens vers vos pages et outils intégrés, affichés dans LogiOne.', 'logiprof-espace-enseignants' ); ?></p>
			</header>
			<?php if ( ! $in_page ) : ?><h1 class="screen-reader-text"><?php esc_html_e( 'Tuiles de contenu', 'logiprof-espace-enseignants' ); ?></h1><?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Les réglages ont été enregistrés.', 'logiprof-espace-enseignants' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $admin_notice['messages'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $admin_notice['type'] ); ?> is-dismissible">
					<?php foreach ( $admin_notice['messages'] as $message ) : ?>
						<p><?php echo esc_html( $message ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="lpee-admin-intro">
				<p>
					<?php esc_html_e( 'Chaque tuile créée ici apparaît dans LogiOne, à la suite des outils. Pour réserver une tuile à un groupe d’enseignants, choisissez ses rôles dans l’onglet Accès et comportement ; chaque élément peut aussi être limité par rôle.', 'logiprof-espace-enseignants' ); ?>
				</p>
			</div>

			<form id="lpee-admin-form" class="lpee-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>">
				<input type="hidden" id="lpee-menus-payload" name="lpee_menus_payload" value="">
				<input type="hidden" id="lpee-active-menu-id" name="lpee_active_menu_id" value="<?php echo esc_attr( $active_menu_id ); ?>">
				<input type="hidden" id="lpee-items-payload" name="lpee_items_payload" value="">
				<?php if ( $in_page ) : ?>
					<input type="hidden" name="lpee_return" value="<?php echo esc_url( $return_url ); ?>">
				<?php endif; ?>

				<section class="lpee-menu-manager" aria-label="<?php esc_attr_e( 'Tuiles disponibles', 'logiprof-espace-enseignants' ); ?>">
					<div class="lpee-menu-fields">
						<label class="lpee-field">
							<span><?php esc_html_e( 'Tuile à modifier', 'logiprof-espace-enseignants' ); ?></span>
							<select id="lpee-menu-select"></select>
						</label>

						<label class="lpee-field">
							<span><?php esc_html_e( 'Nom de la tuile', 'logiprof-espace-enseignants' ); ?></span>
							<input type="text" id="lpee-menu-name" value="">
						</label>

						<label class="lpee-field">
							<span><?php esc_html_e( 'Identifiant', 'logiprof-espace-enseignants' ); ?></span>
							<input type="text" id="lpee-menu-id" value="" readonly>
						</label>

						<label class="lpee-field lpee-field-wide">
							<span><?php esc_html_e( 'Texte de la tuile', 'logiprof-espace-enseignants' ); ?></span>
							<input type="text" name="lpee_settings[tile_text]" value="<?php echo esc_attr( $settings['tile_text'] ); ?>" maxlength="140" placeholder="<?php esc_attr_e( 'Une phrase sous le titre de la tuile', 'logiprof-espace-enseignants' ); ?>">
						</label>

						<div class="lpee-field lpee-field-wide lpee-color-field">
							<span><?php esc_html_e( 'Couleur de la tuile', 'logiprof-espace-enseignants' ); ?></span>
							<input type="hidden" id="lpee-menu-tile-color" name="lpee_settings[tile_color]" value="<?php echo esc_attr( $settings['tile_color'] ); ?>">
							<div class="lpee-swatches" data-lpee-swatches="setting" data-input="#lpee-menu-tile-color" data-picker="#lpee-menu-tile-picker" role="radiogroup" aria-label="<?php esc_attr_e( 'Couleur de la tuile', 'logiprof-espace-enseignants' ); ?>"></div>
							<label class="lpee-color-other"><input type="color" id="lpee-menu-tile-picker" data-lpee-color-picker value="#dcefe3"> <span><?php esc_html_e( 'Autre couleur (toutes les teintes)', 'logiprof-espace-enseignants' ); ?></span></label>
						</div>

						<div class="lpee-field lpee-field-wide lpee-icon-image">
							<span><?php esc_html_e( 'Icône de la tuile', 'logiprof-espace-enseignants' ); ?></span>
							<div class="lpee-icon-image-row">
								<span class="lpee-icon-image-preview is-empty" id="lpee-menu-tile-icon-preview" aria-hidden="true"></span>
								<input type="hidden" id="lpee-menu-tile-icon" name="lpee_settings[tile_icon]" value="<?php echo (int) $settings['tile_icon']; ?>">
								<button type="button" class="button" data-lpee-media-pick="#lpee-menu-tile-icon" data-preview="#lpee-menu-tile-icon-preview"><span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e( 'Importer ou choisir une image…', 'logiprof-espace-enseignants' ); ?></button>
								<button type="button" class="button-link" data-lpee-media-clear="#lpee-menu-tile-icon" data-preview="#lpee-menu-tile-icon-preview"><?php esc_html_e( 'Retirer', 'logiprof-espace-enseignants' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'Facultatif : remplace les cubes sur la tuile. PNG, JPG, WebP ou SVG.', 'logiprof-espace-enseignants' ); ?></p>
						</div>
					</div>

					<div class="lpee-menu-actions">
						<button type="button" class="button button-primary" id="lpee-menu-create">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php esc_html_e( 'Créer une tuile', 'logiprof-espace-enseignants' ); ?>
						</button>
						<button type="button" class="button" id="lpee-menu-duplicate">
							<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							<?php esc_html_e( 'Dupliquer', 'logiprof-espace-enseignants' ); ?>
						</button>
						<button type="button" class="button" id="lpee-menu-delete">
							<span class="dashicons dashicons-trash" aria-hidden="true"></span>
							<?php esc_html_e( 'Supprimer', 'logiprof-espace-enseignants' ); ?>
						</button>
					</div>
				</section>

				<nav class="lpee-admin-tabs" aria-label="<?php esc_attr_e( 'Sections de réglage', 'logiprof-espace-enseignants' ); ?>">
					<button type="button" class="lpee-admin-tab is-active" data-lpee-tab-button="content"><?php esc_html_e( 'Contenu', 'logiprof-espace-enseignants' ); ?></button>
					<button type="button" class="lpee-admin-tab" data-lpee-tab-button="appearance"><?php esc_html_e( 'Apparence', 'logiprof-espace-enseignants' ); ?></button>
					<button type="button" class="lpee-admin-tab" data-lpee-tab-button="access"><?php esc_html_e( 'Accès et comportement', 'logiprof-espace-enseignants' ); ?></button>
				</nav>

				<div class="lpee-admin-layout">
					<div class="lpee-admin-main">
						<section class="lpee-admin-panel is-active" data-lpee-tab-panel="content">
							<div class="lpee-toolbar">
								<button type="button" class="button button-primary" data-lpee-add="folder" data-parent-id="">
									<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
									<?php esc_html_e( 'Ajouter un accordéon principal', 'logiprof-espace-enseignants' ); ?>
								</button>
								<button type="button" class="button" data-lpee-add="link" data-parent-id="">
									<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
									<?php esc_html_e( 'Ajouter un lien au premier niveau', 'logiprof-espace-enseignants' ); ?>
								</button>
								<button type="button" class="button" data-lpee-add="text" data-parent-id="">
									<span class="dashicons dashicons-editor-paragraph" aria-hidden="true"></span>
									<?php esc_html_e( 'Ajouter un texte', 'logiprof-espace-enseignants' ); ?>
								</button>
							</div>

							<p class="description">
								<?php esc_html_e( 'Déplacez les éléments par glisser-déposer, ou utilisez les boutons Monter, Descendre et le champ Parent dans le formulaire d’édition.', 'logiprof-espace-enseignants' ); ?>
							</p>

							<div id="lpee-tree" class="lpee-admin-tree" aria-live="polite"></div>
							<noscript>
								<p><?php esc_html_e( 'JavaScript est nécessaire pour gérer l’arborescence.', 'logiprof-espace-enseignants' ); ?></p>
							</noscript>
						</section>

						<section class="lpee-admin-panel" data-lpee-tab-panel="appearance" hidden>
							<div class="lpee-settings-grid">
								<label class="lpee-field lpee-field-wide">
									<span><?php esc_html_e( 'Présentation', 'logiprof-espace-enseignants' ); ?></span>
									<select name="lpee_settings[theme]">
										<option value="logione" <?php selected( $settings['theme'], 'logione' ); ?>><?php esc_html_e( 'LogiOne (recommandé) : couleurs LogiProf', 'logiprof-espace-enseignants' ); ?></option>
										<option value="custom" <?php selected( $settings['theme'], 'custom' ); ?>><?php esc_html_e( 'Couleurs personnalisées ci-dessous', 'logiprof-espace-enseignants' ); ?></option>
									</select>
								</label>

								<label class="lpee-field lpee-field-wide">
									<span><?php esc_html_e( 'Titre affiché dans la tuile ouverte', 'logiprof-espace-enseignants' ); ?></span>
									<input type="text" name="lpee_settings[block_title]" value="<?php echo esc_attr( $settings['block_title'] ); ?>">
								</label>

								<div class="lpee-field lpee-field-wide lpee-intro-field lpee-rich-field">
									<span><?php esc_html_e( 'Texte en haut de la tuile ouverte (facultatif)', 'logiprof-espace-enseignants' ); ?></span>
									<?php echo self::format_bar( __( 'Mise en forme du texte d’accueil', 'logiprof-espace-enseignants' ) ); // phpcs:ignore ?>
									<textarea name="lpee_settings[intro_text]" rows="4" data-lpee-rich><?php echo esc_textarea( $settings['intro_text'] ); ?></textarea>
									<span class="description"><?php esc_html_e( 'Écrivez directement ici. Sélectionnez un passage puis cliquez sur un bouton pour le mettre en forme ; l’aperçu à droite montre le résultat.', 'logiprof-espace-enseignants' ); ?></span>
								</div>

								<fieldset class="lpee-field-wide lpee-tiles-options" hidden>
									<legend><?php esc_html_e( 'Présentation en tuiles', 'logiprof-espace-enseignants' ); ?></legend>
									<label class="lpee-check"><input type="checkbox" name="lpee_settings[show_search]" value="1" <?php checked( $settings['show_search'], '1' ); ?>><span><?php esc_html_e( 'Recherche dans le bandeau', 'logiprof-espace-enseignants' ); ?></span></label>
									<label class="lpee-check"><input type="checkbox" name="lpee_settings[show_greeting]" value="1" <?php checked( $settings['show_greeting'], '1' ); ?>><span><?php esc_html_e( 'Saluer l’élève par son prénom', 'logiprof-espace-enseignants' ); ?></span></label>
									<label class="lpee-check"><input type="checkbox" name="lpee_settings[show_art]" value="1" <?php checked( $settings['show_art'], '1' ); ?>><span><?php esc_html_e( 'Illustration (Noki, la mascotte LogiProf)', 'logiprof-espace-enseignants' ); ?></span></label>
									<div class="lpee-field lpee-field-wide lpee-color-field lpee-hero-color-field">
										<span><?php esc_html_e( 'Couleur de l’en-tête', 'logiprof-espace-enseignants' ); ?></span>
										<input type="hidden" id="lpee-hero-color" name="lpee_settings[hero_color]" value="<?php echo esc_attr( $settings['hero_color'] ); ?>">
										<div class="lpee-swatches" data-lpee-swatches="setting" data-input="#lpee-hero-color" data-picker="#lpee-hero-picker" role="radiogroup" aria-label="<?php esc_attr_e( 'Couleur de l’en-tête', 'logiprof-espace-enseignants' ); ?>"></div>
										<label class="lpee-color-other"><input type="color" id="lpee-hero-picker" data-lpee-color-picker value="<?php echo esc_attr( $settings['hero_color'] ); ?>"> <span><?php esc_html_e( 'Autre couleur (toutes les teintes)', 'logiprof-espace-enseignants' ); ?></span></label>
										<p class="description"><?php esc_html_e( 'Le texte, la salutation et le logo passent automatiquement en clair ou en foncé pour rester lisibles.', 'logiprof-espace-enseignants' ); ?></p>
									</div>
									<div class="lpee-palette">
										<span><?php esc_html_e( 'Couleurs automatiques des tuiles, dans l’ordre', 'logiprof-espace-enseignants' ); ?></span>
										<div class="lpee-palette-swatches">
											<?php foreach ( array_pad( array_slice( (array) $settings['tile_palette'], 0, 5 ), 5, '#ffffff' ) as $i => $swatch ) : ?>
												<input type="color" name="lpee_settings[tile_palette][]" value="<?php echo esc_attr( $swatch ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Couleur %d', 'logiprof-espace-enseignants' ), $i + 1 ) ); ?>">
											<?php endforeach; ?>
											<button type="button" class="button-link" id="lpee-palette-reset"><?php esc_html_e( 'Couleurs LogiProf', 'logiprof-espace-enseignants' ); ?></button>
										</div>
										<p class="description"><?php esc_html_e( 'Utilisées par les tuiles en couleur « Automatique ». Le texte passe en blanc ou en anthracite selon le fond, pour rester lisible.', 'logiprof-espace-enseignants' ); ?></p>
									</div>
								</fieldset>

								<label class="lpee-check lpee-field-wide">
									<input type="checkbox" name="lpee_settings[show_title]" value="1" <?php checked( $settings['show_title'], '1' ); ?>>
									<span><?php esc_html_e( 'Afficher le titre général', 'logiprof-espace-enseignants' ); ?></span>
								</label>

								<label class="lpee-field">
									<span><?php esc_html_e( 'Couleur principale', 'logiprof-espace-enseignants' ); ?></span>
									<input type="color" name="lpee_settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>">
								</label>

								<label class="lpee-field">
									<span><?php esc_html_e( 'Fond général', 'logiprof-espace-enseignants' ); ?></span>
									<input type="color" name="lpee_settings[container_bg]" value="<?php echo esc_attr( $settings['container_bg'] ); ?>">
								</label>

								<label class="lpee-field">
									<span><?php esc_html_e( 'Fond des éléments', 'logiprof-espace-enseignants' ); ?></span>
									<input type="color" name="lpee_settings[item_bg]" value="<?php echo esc_attr( $settings['item_bg'] ); ?>">
								</label>

								<label class="lpee-field">
									<span><?php esc_html_e( 'Couleur des textes', 'logiprof-espace-enseignants' ); ?></span>
									<input type="color" name="lpee_settings[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>">
								</label>

								<label class="lpee-field">
									<span><?php esc_html_e( 'Largeur maximale', 'logiprof-espace-enseignants' ); ?></span>
									<input type="number" min="320" max="1600" step="10" name="lpee_settings[max_width]" value="<?php echo esc_attr( $settings['max_width'] ); ?>">
								</label>

								<label class="lpee-field">
									<span><?php esc_html_e( 'Arrondis', 'logiprof-espace-enseignants' ); ?></span>
									<input type="number" min="0" max="32" step="1" name="lpee_settings[radius]" value="<?php echo esc_attr( $settings['radius'] ); ?>">
								</label>

								<label class="lpee-field">
									<span><?php esc_html_e( 'Espacement', 'logiprof-espace-enseignants' ); ?></span>
									<select name="lpee_settings[spacing]">
										<option value="comfortable" <?php selected( $settings['spacing'], 'comfortable' ); ?>><?php esc_html_e( 'Confortable', 'logiprof-espace-enseignants' ); ?></option>
										<option value="compact" <?php selected( $settings['spacing'], 'compact' ); ?>><?php esc_html_e( 'Compact', 'logiprof-espace-enseignants' ); ?></option>
									</select>
								</label>
							</div>

							<p>
								<button type="button" class="button" id="lpee-reset-appearance">
									<?php esc_html_e( 'Rétablir les réglages d’apparence', 'logiprof-espace-enseignants' ); ?>
								</button>
							</p>
						</section>

						<section class="lpee-admin-panel" data-lpee-tab-panel="access" hidden>
							<div class="lpee-settings-grid">
								<div class="lpee-field lpee-field-wide">
									<label class="lpee-check">
										<input type="checkbox" id="lpee-field-restrict-menu-roles" name="lpee_settings[restrict_menu_roles]" value="1" <?php checked( $settings['restrict_menu_roles'], '1' ); ?>>
										<span><?php esc_html_e( 'Réserver cette tuile à certains rôles (groupes d’enseignants)', 'logiprof-espace-enseignants' ); ?></span>
									</label>
									<p class="description"><?php esc_html_e( 'Laissez cette option désactivée pour afficher la tuile à tous les enseignants. Les restrictions créées dans un élément restent locales à cet élément.', 'logiprof-espace-enseignants' ); ?></p>

									<span><?php esc_html_e( 'Rôles autorisés si la restriction globale est activée', 'logiprof-espace-enseignants' ); ?></span>
									<div id="lpee-menu-role-list" class="lpee-role-list <?php echo '1' === $settings['restrict_menu_roles'] ? '' : 'is-disabled'; ?>">
										<?php foreach ( $roles as $role_slug => $role_label ) : ?>
											<label class="lpee-check">
												<input type="checkbox" name="lpee_settings[allowed_roles][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $settings['allowed_roles'], true ) ); ?>>
												<span><?php echo esc_html( $role_label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>

								<label class="lpee-field lpee-field-wide">
									<span><?php esc_html_e( 'Message des rubriques vides', 'logiprof-espace-enseignants' ); ?></span>
									<input type="text" name="lpee_settings[empty_message]" value="<?php echo esc_attr( $settings['empty_message'] ); ?>">
								</label>

								<label class="lpee-check lpee-field-wide">
									<input type="checkbox" name="lpee_settings[single_open_siblings]" value="1" <?php checked( $settings['single_open_siblings'], '1' ); ?>>
									<span><?php esc_html_e( 'Limiter l’ouverture à une seule rubrique parmi les rubriques de même niveau', 'logiprof-espace-enseignants' ); ?></span>
								</label>
							</div>
						</section>
					</div>

					<aside class="lpee-admin-preview">
						<div class="lpee-preview-head">
							<h2><?php esc_html_e( 'Prévisualisation', 'logiprof-espace-enseignants' ); ?></h2>
							<span><?php esc_html_e( 'Aperçu instantané', 'logiprof-espace-enseignants' ); ?></span>
						</div>
						<div id="lpee-preview"></div>
					</aside>
				</div>

				<div class="lpee-save-bar">
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Enregistrer les modifications', 'logiprof-espace-enseignants' ); ?>
					</button>
				</div>
			</form>

			<?php self::render_editor_modal(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Barre de mise en forme d'un texte (texte d'accueil, bloc de texte) : type de texte, gras, italique, souligné, surligné, lien, liste. */
	private static function format_bar( $label ) {
		return '<span class="lpee-format-bar" role="toolbar" aria-label="' . esc_attr( $label ) . '"><select class="lpee-format-block" data-lpee-block aria-label="' . esc_attr__( 'Type de texte', 'logiprof-espace-enseignants' ) . '" title="' . esc_attr__( 'Type de texte', 'logiprof-espace-enseignants' ) . '"><option value="p">' . esc_html__( 'Paragraphe', 'logiprof-espace-enseignants' ) . '</option><option value="h1">' . esc_html__( 'Titre 1', 'logiprof-espace-enseignants' ) . '</option><option value="h2">' . esc_html__( 'Titre 2', 'logiprof-espace-enseignants' ) . '</option><option value="h3">' . esc_html__( 'Titre 3', 'logiprof-espace-enseignants' ) . '</option><option value="h4">' . esc_html__( 'Titre 4', 'logiprof-espace-enseignants' ) . '</option></select><button type="button" class="button" data-lpee-format="strong" title="' . esc_attr__( 'Gras', 'logiprof-espace-enseignants' ) . '"><strong>G</strong></button><button type="button" class="button" data-lpee-format="em" title="' . esc_attr__( 'Italique', 'logiprof-espace-enseignants' ) . '"><em>I</em></button><button type="button" class="button" data-lpee-format="u" title="' . esc_attr__( 'Souligné', 'logiprof-espace-enseignants' ) . '"><u>S</u></button><button type="button" class="button" data-lpee-format="mark" title="' . esc_attr__( 'Surligné', 'logiprof-espace-enseignants' ) . '"><mark>Surligner</mark></button><button type="button" class="button" data-lpee-format="a" title="' . esc_attr__( 'Lien', 'logiprof-espace-enseignants' ) . '"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> ' . esc_html__( 'Lien', 'logiprof-espace-enseignants' ) . '</button><button type="button" class="button" data-lpee-format="ul" title="' . esc_attr__( 'Liste à puces', 'logiprof-espace-enseignants' ) . '"><span class="dashicons dashicons-editor-ul" aria-hidden="true"></span> ' . esc_html__( 'Liste', 'logiprof-espace-enseignants' ) . '</button></span>';
	}

	private static function render_editor_modal() {
		?>
		<div id="lpee-editor-modal" class="lpee-modal" hidden>
			<div class="lpee-modal-backdrop" data-lpee-close-editor></div>
			<div class="lpee-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="lpee-editor-title">
				<div class="lpee-modal-head">
					<h2 id="lpee-editor-title"><?php esc_html_e( 'Modifier un élément', 'logiprof-espace-enseignants' ); ?></h2>
					<button type="button" class="button-link lpee-modal-close" data-lpee-close-editor>
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Fermer', 'logiprof-espace-enseignants' ); ?></span>
					</button>
				</div>

				<div class="lpee-modal-body">
					<input type="hidden" id="lpee-field-id" value="">

					<label class="lpee-field">
						<span><?php esc_html_e( 'Type', 'logiprof-espace-enseignants' ); ?></span>
						<select id="lpee-field-type">
							<option value="folder"><?php esc_html_e( 'Rubrique déroulante', 'logiprof-espace-enseignants' ); ?></option>
							<option value="link"><?php esc_html_e( 'Lien de destination', 'logiprof-espace-enseignants' ); ?></option>
							<option value="text"><?php esc_html_e( 'Bloc de texte', 'logiprof-espace-enseignants' ); ?></option>
						</select>
					</label>

					<label class="lpee-field">
						<span><?php esc_html_e( 'Titre', 'logiprof-espace-enseignants' ); ?></span>
						<input type="text" id="lpee-field-title" value="" required>
					</label>

					<details class="lpee-field lpee-direct-shortcode-field lpee-advanced">
						<summary><?php esc_html_e( 'Avancé : afficher cet élément seul sur une autre page', 'logiprof-espace-enseignants' ); ?></summary>
						<p class="description"><?php esc_html_e( 'Facultatif. Ce code ne fait rien par lui-même : il sert uniquement si vous voulez montrer cet élément seul, ailleurs sur le site, en le collant dans une page.', 'logiprof-espace-enseignants' ); ?></p>
						<input type="text" id="lpee-field-direct-shortcode" value="" readonly aria-label="<?php esc_attr_e( 'Shortcode de cet élément', 'logiprof-espace-enseignants' ); ?>">
					</details>

					<label class="lpee-field lpee-description-field">
						<span><?php esc_html_e( 'Description', 'logiprof-espace-enseignants' ); ?></span>
						<textarea id="lpee-field-description" rows="4"></textarea>
					</label>

					<label class="lpee-field">
						<span><?php esc_html_e( 'Icône', 'logiprof-espace-enseignants' ); ?></span>
						<select id="lpee-field-icon"></select>
					</label>

					<label class="lpee-field">
						<span><?php esc_html_e( 'Élément parent', 'logiprof-espace-enseignants' ); ?></span>
						<select id="lpee-field-parent"></select>
					</label>

					<label class="lpee-field">
						<span><?php esc_html_e( 'Position', 'logiprof-espace-enseignants' ); ?></span>
						<input type="number" min="1" step="1" id="lpee-field-position" value="1">
					</label>

					<label class="lpee-check">
						<input type="checkbox" id="lpee-field-visible" checked>
						<span><?php esc_html_e( 'Affiché', 'logiprof-espace-enseignants' ); ?></span>
					</label>

					<fieldset class="lpee-tile-fields lpee-tile-fields-logione">
						<legend><?php esc_html_e( 'Icône de l’élément', 'logiprof-espace-enseignants' ); ?></legend>
						<div class="lpee-field lpee-field-wide lpee-icon-image">
							<span><?php esc_html_e( 'Icône importée', 'logiprof-espace-enseignants' ); ?></span>
							<div class="lpee-icon-image-row">
								<span class="lpee-icon-image-preview" id="lpee-icon-image-preview" aria-hidden="true"></span>
								<input type="hidden" id="lpee-field-icon-image" value="0">
								<button type="button" class="button" id="lpee-icon-image-pick"><span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e( 'Importer ou choisir une image…', 'logiprof-espace-enseignants' ); ?></button>
								<button type="button" class="button-link" id="lpee-icon-image-remove"><?php esc_html_e( 'Retirer', 'logiprof-espace-enseignants' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'PNG, JPG, WebP ou SVG, de préférence carré. Remplace le pictogramme sur la tuile, dans les onglets et dans les listes.', 'logiprof-espace-enseignants' ); ?></p>
						</div>
						<div class="lpee-field lpee-field-wide lpee-color-field">
							<span><?php esc_html_e( 'Couleur de la tuile', 'logiprof-espace-enseignants' ); ?></span>
							<select id="lpee-field-tile-color" hidden>
								<?php foreach ( self::tile_color_choices() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<div class="lpee-swatches" data-lpee-swatches="item" role="radiogroup" aria-label="<?php esc_attr_e( 'Couleur de la tuile', 'logiprof-espace-enseignants' ); ?>"></div>
							<label class="lpee-color-other"><input type="color" id="lpee-field-tile-custom" value="#067030"> <span><?php esc_html_e( 'Autre couleur (toutes les teintes)', 'logiprof-espace-enseignants' ); ?></span></label>
						</div>
						<label class="lpee-field">
							<span><?php esc_html_e( 'Taille', 'logiprof-espace-enseignants' ); ?></span>
							<select id="lpee-field-tile-size">
								<?php foreach ( self::tile_size_choices() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</fieldset>

					<div class="lpee-field lpee-field-wide lpee-text-fields lpee-rich-field">
						<span><?php esc_html_e( 'Texte', 'logiprof-espace-enseignants' ); ?></span>
						<?php echo self::format_bar( __( 'Mise en forme du bloc de texte', 'logiprof-espace-enseignants' ) ); // phpcs:ignore ?>
						<textarea id="lpee-field-content" rows="8" data-lpee-rich></textarea>
						<span class="description"><?php esc_html_e( 'Écrivez directement ici, puis mettez en forme : type de texte (paragraphe, titres), gras, italique, souligné, surligné, liens, listes.', 'logiprof-espace-enseignants' ); ?></span>
					</div>

					<div class="lpee-folder-fields">
						<div class="lpee-include-fields">
							<label class="lpee-field">
								<span><?php esc_html_e( 'Reprendre le contenu d’un autre menu', 'logiprof-espace-enseignants' ); ?></span>
								<select id="lpee-field-include-menu"></select>
							</label>
							<label class="lpee-check">
								<input type="checkbox" id="lpee-field-include-inline">
								<span><?php esc_html_e( 'Afficher ses éléments directement à cet endroit (sans rubrique)', 'logiprof-espace-enseignants' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'Les éléments communs se gèrent une seule fois, dans leur menu : toute modification apparaît partout où ce menu est repris.', 'logiprof-espace-enseignants' ); ?></p>
						</div>
						<label class="lpee-check">
							<input type="checkbox" id="lpee-field-open">
							<span><?php esc_html_e( 'Ouvert par défaut', 'logiprof-espace-enseignants' ); ?></span>
						</label>
					</div>

					<div class="lpee-link-fields">
						<label class="lpee-field lpee-field-wide lpee-link-target-field">
							<span><?php esc_html_e( 'Ouverture du lien', 'logiprof-espace-enseignants' ); ?></span>
							<select id="lpee-field-link-target">
								<?php foreach ( self::link_target_choices() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="lpee-field">
							<span><?php esc_html_e( 'Type de destination', 'logiprof-espace-enseignants' ); ?></span>
							<select id="lpee-field-destination-type">
								<option value="page"><?php esc_html_e( 'Page WordPress', 'logiprof-espace-enseignants' ); ?></option>
								<option value="url"><?php esc_html_e( 'URL saisie manuellement', 'logiprof-espace-enseignants' ); ?></option>
								<option value="admin"><?php esc_html_e( 'Backoffice WordPress', 'logiprof-espace-enseignants' ); ?></option>
								<option value="shortcode"><?php esc_html_e( 'Shortcode intégré', 'logiprof-espace-enseignants' ); ?></option>
							</select>
						</label>

						<div class="lpee-page-destination">
							<label class="lpee-field">
								<span><?php esc_html_e( 'Rechercher une page', 'logiprof-espace-enseignants' ); ?></span>
								<input type="search" id="lpee-field-page-search" value="" placeholder="<?php esc_attr_e( 'Filtrer les pages…', 'logiprof-espace-enseignants' ); ?>">
							</label>
							<label class="lpee-field">
								<span><?php esc_html_e( 'Page sélectionnée', 'logiprof-espace-enseignants' ); ?></span>
								<select id="lpee-field-page-id"></select>
							</label>
						</div>

						<label class="lpee-field lpee-url-destination">
							<span><?php esc_html_e( 'URL', 'logiprof-espace-enseignants' ); ?></span>
							<input type="text" id="lpee-field-url" value="" placeholder="https://example.com ou /page-interne/">
						</label>

						<label class="lpee-field lpee-shortcode-destination">
							<span><?php esc_html_e( 'Shortcode de l’outil', 'logiprof-espace-enseignants' ); ?></span>
							<textarea id="lpee-field-shortcode" rows="3" placeholder='[logiprof_sensei_group_progress show_filters="yes"]'></textarea>
						</label>
					</div>

					<div class="lpee-field lpee-field-wide lpee-role-restriction-field">
						<label class="lpee-check lpee-role-restriction-toggle">
							<input type="checkbox" id="lpee-field-limit-roles">
							<span><?php esc_html_e( 'Limiter uniquement cet élément à certains rôles', 'logiprof-espace-enseignants' ); ?></span>
						</label>
						<div id="lpee-field-roles" class="lpee-role-list"></div>
						<p class="description"><?php esc_html_e( 'Cette restriction ne se propage pas aux autres éléments du menu. Si l’option est désactivée, l’élément reste visible pour tous les rôles autorisés à charger le menu complet.', 'logiprof-espace-enseignants' ); ?></p>
					</div>
				</div>

				<div class="lpee-modal-actions">
					<button type="button" class="button" data-lpee-close-editor><?php esc_html_e( 'Annuler', 'logiprof-espace-enseignants' ); ?></button>
					<button type="button" class="button button-primary" id="lpee-save-editor"><?php esc_html_e( 'Appliquer', 'logiprof-espace-enseignants' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! self::current_user_can_edit() ) {
			wp_die( esc_html__( 'Vous n’avez pas les droits nécessaires.', 'logiprof-espace-enseignants' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$menus_payload = isset( $_POST['lpee_menus_payload'] ) ? wp_unslash( $_POST['lpee_menus_payload'] ) : '';
		$active_menu_id = isset( $_POST['lpee_active_menu_id'] ) ? sanitize_key( wp_unslash( $_POST['lpee_active_menu_id'] ) ) : self::DEFAULT_MENU_ID;
		$payload  = isset( $_POST['lpee_items_payload'] ) ? wp_unslash( $_POST['lpee_items_payload'] ) : '[]';
		$posted_settings = isset( $_POST['lpee_settings'] ) && is_array( $_POST['lpee_settings'] ) ? wp_unslash( $_POST['lpee_settings'] ) : array();

		$return = isset( $_POST['lpee_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['lpee_return'] ) ), '' ) : '';

		if ( '' !== trim( (string) $menus_payload ) ) {
			$result   = self::sanitize_menus_payload( $menus_payload );
			$menus    = $result['menus'];
			$active_menu = self::get_menu_by_id( $menus, $active_menu_id );

			if ( ! $active_menu ) {
				$active_menu    = reset( $menus );
				$active_menu_id = $active_menu['id'];
			}

			update_option( self::OPTION_MENUS, $menus, false );
			$principal = self::get_menu_by_id( $menus, self::DEFAULT_MENU_ID ) ?: reset( $menus );
			update_option( self::OPTION_ITEMS, $principal['items'], false );
			update_option( self::OPTION_SETTINGS, $principal['settings'], false );

			if ( ! empty( $result['warnings'] ) ) {
				self::set_admin_notice( 'warning', $result['warnings'] );
			}

			$target = '' !== $return
				? add_query_arg( array( 'lpee_menu' => $active_menu_id, 'updated' => '1' ), $return )
				: add_query_arg( array( 'page' => self::SLUG, 'menu' => $active_menu_id, 'updated' => '1' ), admin_url( 'admin.php' ) );
			wp_safe_redirect( $target );
			exit;
		}


		$result   = self::sanitize_items_payload( $payload );
		$settings = self::sanitize_settings( $posted_settings );

		update_option( self::OPTION_ITEMS, $result['items'], false );
		update_option( self::OPTION_SETTINGS, $settings, false );
		update_option(
			self::OPTION_MENUS,
			array(
				array(
					'id'       => self::DEFAULT_MENU_ID,
					'name'     => __( 'Ressources', 'logiprof-espace-enseignants' ),
					'items'    => $result['items'],
					'settings' => $settings,
					'order'    => 1,
				),
			),
			false
		);

		if ( ! empty( $result['warnings'] ) ) {
			self::set_admin_notice( 'warning', $result['warnings'] );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'menu'    => '',
				'item'    => '',
				'id'      => '',
				'outil'   => '',
				'element' => '',
				'vue'     => '',
			),
			(array) $atts,
			self::SHORTCODE
		);

		$menu = self::get_menu_for_shortcode( $atts['menu'] );

		if ( ! $menu ) {
			return '';
		}

		$settings = $menu['settings'];

		if ( ! self::current_user_can_view_menu( $settings ) ) {
			return '';
		}

		$items = self::expand_includes( $menu['items'], array( (string) $menu['id'] => true ) );

		if ( empty( $items ) ) {
			return '';
		}

		// Un élément seul ([logipilot_tuile_contenu item="…"]) garde la présentation en liste.
		if ( self::$reading ) {
			return '';
		}

		if ( 'tiles' === $settings['theme'] && '' !== self::get_shortcode_item_identifier( $atts ) ) {
			$settings['theme'] = 'logione';
		}

		self::register_public_assets();

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( self::SLUG );
		wp_enqueue_script( self::SLUG );

		self::$instance_count++;
		$instance_id = 'lpee-' . self::$instance_count . '-' . wp_rand( 1000, 9999 );
		$item_identifier = self::get_shortcode_item_identifier( $atts );

		if ( '' !== $item_identifier ) {
			$output = self::render_single_item_shortcode( $items, $settings, $instance_id, $item_identifier );
		} elseif ( 'panneau' === $atts['vue'] ) {
			$output = self::render_embed( (string) $menu['id'], $items, $settings, $instance_id );
		} elseif ( 'tiles' === $settings['theme'] ) {
			$output = self::render_tiles( (string) $menu['id'], $items, $settings, $instance_id );
		} else {
			$output = self::render_menu( $items, $settings, $instance_id );
		}

		return '' === trim( $output ) ? '' : $output;
	}

	private static function get_shortcode_item_identifier( $atts ) {
		foreach ( array( 'item', 'id', 'outil', 'element' ) as $key ) {
			if ( isset( $atts[ $key ] ) && '' !== trim( (string) $atts[ $key ] ) ) {
				return trim( (string) $atts[ $key ] );
			}
		}

		return '';
	}

	private static function render_single_item_shortcode( $items, $settings, $instance_id, $identifier ) {
		$item = self::find_item_by_identifier( $items, $identifier );

		if ( ! $item ) {
			return '';
		}

		$items_by_id = array();

		foreach ( $items as $candidate ) {
			$items_by_id[ $candidate['id'] ] = $candidate;
		}

		if ( ! self::item_is_renderable_in_context( $item, $items_by_id ) ) {
			return '';
		}

		$tree = self::build_children_map( $items );
		$item_html = self::render_item( $item, $tree, $settings, $instance_id, 0 );

		if ( '' === $item_html ) {
			return '';
		}

		$style = self::build_style_attribute( $settings );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="lpee-root lpee-single lpee-theme-<?php echo esc_attr( $settings['theme'] ); ?> lpee-spacing-<?php echo esc_attr( $settings['spacing'] ); ?>" data-lpee-root data-single-open="<?php echo esc_attr( $settings['single_open_siblings'] ); ?>" style="<?php echo esc_attr( $style ); ?>">
			<ul class="lpee-list lpee-level-0" data-lpee-level="0">
				<?php echo $item_html; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function find_item_by_identifier( $items, $identifier ) {
		$identifier = trim( (string) $identifier );

		if ( '' === $identifier ) {
			return null;
		}

		$key  = sanitize_key( $identifier );
		$slug = sanitize_title( $identifier );

		foreach ( $items as $item ) {
			if ( $identifier === $item['id'] || $key === sanitize_key( $item['id'] ) || $slug === sanitize_title( $item['title'] ) ) {
				return $item;
			}
		}

		return null;
	}

	private static function item_is_renderable_in_context( $item, $items_by_id ) {
		$current = $item;

		while ( $current ) {
			if ( ! empty( $current['hidden'] ) || ! self::current_user_can_view_item( $current ) ) {
				return false;
			}

			if ( empty( $current['parent_id'] ) || ! isset( $items_by_id[ $current['parent_id'] ] ) ) {
				break;
			}

			$current = $items_by_id[ $current['parent_id'] ];
		}

		return true;
	}

	private static function render_menu( $items, $settings, $instance_id ) {
		$tree       = self::build_children_map( $items );
		$style      = self::build_style_attribute( $settings );
		$show_title = '1' === $settings['show_title'] && '' !== trim( $settings['block_title'] );
		$list_html  = self::render_item_list( '', $tree, $settings, $instance_id, 0 );

		if ( '' === $list_html ) {
			return '';
		}

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="lpee-root lpee-theme-<?php echo esc_attr( $settings['theme'] ); ?> lpee-spacing-<?php echo esc_attr( $settings['spacing'] ); ?>" data-lpee-root data-single-open="<?php echo esc_attr( $settings['single_open_siblings'] ); ?>" style="<?php echo esc_attr( $style ); ?>">
			<?php if ( 'logione' === $settings['theme'] ) : ?>
				<div class="lpee-head">
					<?php if ( $show_title ) : ?>
						<h2 class="lpee-block-title"><?php echo esc_html( $settings['block_title'] ); ?></h2>
					<?php endif; ?>
				</div>
			<?php elseif ( $show_title ) : ?>
				<h2 class="lpee-block-title"><?php echo esc_html( $settings['block_title'] ); ?></h2>
			<?php endif; ?>

			<?php echo $list_html; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -------------------------------------------------------------------
	 * Présentation en tuiles (0.4.0) : tout se passe dans la même page.
	 * - Une tuile de rubrique déplie son contenu juste en dessous (panneau), sans rechargement.
	 * - Dans le panneau, les sous-rubriques sont des onglets (ex. CFC / AFP), les liens des lignes compactes.
	 * - Un outil intégré se déplie sur place ; un lien ouvre sa destination.
	 * - L'adresse (?lhm=menu&lh=élément) garde l'état : sans JavaScript, le serveur ouvre le bon panneau.
	 * ----------------------------------------------------------------- */

	/** Couleurs LogiProf proposées pour les tuiles. */
	public static function tile_presets() {
		return array(
			'ink'    => '#313a45',
			'green'  => '#067030',
			'white'  => '#ffffff',
			'yellow' => '#f5c400',
			'mint'   => '#dcefe3',
		);
	}

	/** Couleurs automatiques, dans l'ordre (modifiables dans Apparence). */
	private static function tile_palette( $settings = array() ) {
		$palette = isset( $settings['tile_palette'] ) && is_array( $settings['tile_palette'] ) ? array_values( array_filter( array_map( 'sanitize_hex_color', $settings['tile_palette'] ) ) ) : array();

		return $palette ? $palette : array_values( self::tile_presets() );
	}

	/** Pastilles de couleur de l'éditeur : couleurs LogiProf, puis une gamme complète ; « Autre couleur » pour le reste. */
	public static function tile_swatches() {
		return array(
			array( 'key' => 'ink', 'hex' => '#313a45', 'label' => __( 'Anthracite', 'logiprof-espace-enseignants' ) ),
			array( 'key' => 'green', 'hex' => '#067030', 'label' => __( 'Vert LogiProf', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#2fae5f', 'label' => __( 'Vert vif', 'logiprof-espace-enseignants' ) ),
			array( 'key' => 'mint', 'hex' => '#dcefe3', 'label' => __( 'Vert clair', 'logiprof-espace-enseignants' ) ),
			array( 'key' => 'yellow', 'hex' => '#f5c400', 'label' => __( 'Jaune', 'logiprof-espace-enseignants' ) ),
			array( 'key' => 'white', 'hex' => '#ffffff', 'label' => __( 'Blanc', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#0e6e8c', 'label' => __( 'Bleu pétrole', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#2563eb', 'label' => __( 'Bleu', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#cfe3ff', 'label' => __( 'Bleu clair', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#7c3aed', 'label' => __( 'Violet', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#db2777', 'label' => __( 'Rose', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#c0392b', 'label' => __( 'Rouge', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#ea580c', 'label' => __( 'Orange', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#f3e3c3', 'label' => __( 'Sable', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#6b7280', 'label' => __( 'Gris', 'logiprof-espace-enseignants' ) ),
			array( 'hex' => '#111827', 'label' => __( 'Noir', 'logiprof-espace-enseignants' ) ),
		);
	}

	/** Couleurs proposées dans l'éditeur, pour chaque élément. */
	public static function tile_color_choices() {
		return array(
			'auto'   => __( 'Automatique', 'logiprof-espace-enseignants' ),
			'ink'    => __( 'Anthracite', 'logiprof-espace-enseignants' ),
			'green'  => __( 'Vert', 'logiprof-espace-enseignants' ),
			'white'  => __( 'Blanc', 'logiprof-espace-enseignants' ),
			'yellow' => __( 'Jaune', 'logiprof-espace-enseignants' ),
			'mint'   => __( 'Vert clair', 'logiprof-espace-enseignants' ),
			'custom' => __( 'Personnalisée…', 'logiprof-espace-enseignants' ),
		);
	}

	/** Couleur de fond d'une tuile : choisie, personnalisée, ou la suivante de la palette automatique. */
	private static function tile_hex( $item, $settings, &$auto_index ) {
		$choice  = isset( $item['tile_color'] ) ? $item['tile_color'] : 'auto';
		$presets = self::tile_presets();

		if ( 'custom' === $choice && ! empty( $item['tile_custom'] ) && sanitize_hex_color( $item['tile_custom'] ) ) {
			return $item['tile_custom'];
		}
		if ( isset( $presets[ $choice ] ) ) {
			return $presets[ $choice ];
		}
		$palette = self::tile_palette( $settings );

		return $palette[ ( $auto_index++ ) % count( $palette ) ];
	}

	private static function hex_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	private static function luminance( $hex ) {
		$channels = array_map(
			static function ( $c ) {
				$c /= 255;
				return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			},
			self::hex_rgb( $hex )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	private static function contrast( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );

		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/** Début de l'en-tête (bandeau) : couleur choisie, textes et logo lisibles sur ce fond. */
	private static function hero_open( $settings ) {
		$hex  = sanitize_hex_color( (string) ( $settings['hero_color'] ?? '' ) );
		$hex  = $hex ? $hex : self::DEFAULT_HERO;
		$dark = self::contrast( $hex, '#ffffff' ) >= self::contrast( $hex, '#313a45' );

		return '<header class="lph-hero' . ( $dark ? '' : ' is-light' ) . '" style="' . esc_attr( self::hero_vars( $hex ) ) . '"><div class="lph-hero-top"><img class="lph-logo" src="' . esc_url( self::logo_url( $dark ) ) . '" alt="tuiles de contenu by LogiProf" width="200" height="53"></div>';
	}

	/** Variables CSS de l'en-tête : fond, texte, texte secondaire, accent (salutation, liens). */
	public static function hero_vars( $hex ) {
		$ink = '#313a45';
		if ( self::contrast( $hex, '#ffffff' ) >= self::contrast( $hex, $ink ) ) {
			$accent = self::contrast( $hex, '#2fae5f' ) >= 3 ? '#2fae5f' : ( self::contrast( $hex, '#f5c400' ) >= 3 ? '#f5c400' : '#ffffff' );
			$vars   = array( '--hero-bg' => $hex, '--hero-fg' => '#ffffff', '--hero-sub' => 'rgba(255,255,255,.82)', '--hero-accent' => $accent );
		} else {
			$accent = self::contrast( $hex, '#067030' ) >= 3 ? '#067030' : $ink;
			$vars   = array( '--hero-bg' => $hex, '--hero-fg' => $ink, '--hero-sub' => 'rgba(49,58,69,.82)', '--hero-accent' => $accent );
		}
		$out = '';
		foreach ( $vars as $k => $v ) {
			$out .= $k . ':' . $v . ';';
		}

		return $out;
	}

	/** Variables CSS d'une tuile : textes et accents choisis pour rester lisibles sur n'importe quel fond. */
	private static function tile_vars( $hex ) {
		$ink   = '#313a45';
		$green = '#067030';

		if ( self::contrast( $hex, '#ffffff' ) >= self::contrast( $hex, $ink ) ) {
			$vars = array( '--bg' => $hex, '--fg' => '#ffffff', '--sub' => 'rgba(255,255,255,.8)', '--icon-bg' => 'rgba(255,255,255,.14)', '--icon-fg' => '#ffffff', '--cta' => self::contrast( $hex, '#f5c400' ) >= 3 ? '#f5c400' : '#ffffff' );
		} else {
			$accent = self::contrast( $hex, $green ) >= 3 ? $green : $ink;
			$vars   = array( '--bg' => $hex, '--fg' => $ink, '--sub' => 'rgba(49,58,69,.78)', '--icon-bg' => self::contrast( $hex, '#ffffff' ) < 1.1 ? '#dcefe3' : 'rgba(255,255,255,.6)', '--icon-fg' => $accent, '--cta' => $accent );
		}
		$vars['--accent'] = self::contrast( $hex, '#ffffff' ) < 1.25 ? '#2fae5f' : $hex;

		$out = '';
		foreach ( $vars as $name => $value ) {
			$out .= $name . ':' . $value . ';';
		}

		return $out;
	}

	/** Icône d'un élément : image importée (médiathèque) si elle existe, sinon pictogramme. */
	private static function render_icon( $item, $class ) {
		$url = self::icon_image_url( $item );

		if ( '' !== $url ) {
			return '<span class="' . esc_attr( $class ) . ' is-image" aria-hidden="true"><img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async"></span>';
		}

		return self::render_dashicon( $item['icon'], $class );
	}

	private static function icon_image_url( $item ) {
		$id = isset( $item['icon_image'] ) ? absint( $item['icon_image'] ) : 0;
		if ( ! $id || ! wp_attachment_is_image( $id ) && 'image/svg+xml' !== get_post_mime_type( $id ) ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'thumbnail' );

		return $url ? $url : (string) wp_get_attachment_url( $id );
	}

	/* -------------------------------------------------------------------
	 * Import d'icônes SVG : réservé aux personnes qui gèrent tuiles de contenu ; le fichier est nettoyé
	 * (aucun script, aucun lien externe, aucun gestionnaire d'événement) avant d'être enregistré.
	 * ----------------------------------------------------------------- */

	public static function allow_svg_upload( $mimes ) {
		if ( self::current_user_can_edit() ) {
			$mimes['svg'] = 'image/svg+xml';
		}

		return $mimes;
	}

	public static function check_svg_filetype( $data, $file, $filename, $mimes ) {
		if ( self::current_user_can_edit() && 'svg' === strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) ) {
			return array( 'ext' => 'svg', 'type' => 'image/svg+xml', 'proper_filename' => $data['proper_filename'] ?? false );
		}

		return $data;
	}

	public static function sanitize_svg_upload( $file ) {
		if ( 'svg' !== strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) ) {
			return $file;
		}
		if ( ! self::current_user_can_edit() ) {
			$file['error'] = __( 'Les fichiers SVG sont réservés aux personnes qui gèrent tuiles de contenu.', 'logiprof-espace-enseignants' );
			return $file;
		}
		$clean = self::sanitize_svg( (string) file_get_contents( $file['tmp_name'] ) );
		if ( '' === $clean ) {
			$file['error'] = __( 'Ce fichier SVG n’a pas pu être lu ou contient des éléments non autorisés.', 'logiprof-espace-enseignants' );
			return $file;
		}
		file_put_contents( $file['tmp_name'], $clean );
		$file['size'] = strlen( $clean );

		return $file;
	}

	/** Garde uniquement le dessin : formes, dégradés, textes. Retourne '' si le fichier est refusé. */
	public static function sanitize_svg( $svg ) {
		if ( '' === trim( $svg ) || preg_match( '/<!DOCTYPE|<!ENTITY/i', $svg ) || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}
		$allowed = array( 'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect', 'defs', 'lineargradient', 'radialgradient', 'stop', 'title', 'desc', 'use', 'symbol', 'clippath', 'mask', 'text', 'tspan' );
		$dom     = new DOMDocument();
		$prev    = libxml_use_internal_errors( true );
		$ok      = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return '';
		}
		$nodes = array();
		foreach ( $dom->getElementsByTagName( '*' ) as $node ) {
			$nodes[] = $node;
		}
		foreach ( $nodes as $node ) {
			if ( ! in_array( strtolower( $node->localName ), $allowed, true ) ) {
				$node->parentNode->removeChild( $node );
				continue;
			}
			$attrs = array();
			foreach ( $node->attributes as $attr ) {
				$attrs[] = $attr;
			}
			foreach ( $attrs as $attr ) {
				$name  = strtolower( $attr->nodeName );
				$value = (string) $attr->nodeValue;
				$bad   = 0 === strpos( $name, 'on' )
					|| ( in_array( $name, array( 'href', 'xlink:href' ), true ) && 0 !== strpos( trim( $value ), '#' ) )
					|| preg_match( '/javascript:|expression\s*\(|url\s*\(\s*[\'"]?\s*(?!#)/i', $value );
				if ( $bad ) {
					$node->removeAttributeNode( $attr );
				}
			}
		}

		return (string) $dom->saveXML( $dom->documentElement );
	}

	public static function tile_size_choices() {
		return array(
			'normal' => __( 'Normale', 'logiprof-espace-enseignants' ),
			'wide'   => __( 'Large (deux colonnes)', 'logiprof-espace-enseignants' ),
		);
	}

	private static function render_tiles( $menu_id, $items, $settings, $instance_id ) {
		$tree    = self::build_children_map( $items );
		$by_id   = array();
		$menu_id = sanitize_key( $menu_id );

		foreach ( $items as $item ) {
			$by_id[ $item['id'] ] = $item;
		}

		$top = self::get_renderable_children( '', $tree );

		if ( ! $top ) {
			return '';
		}

		// État demandé par l'adresse : l'élément et ses rubriques parentes sont ouverts.
		$open = array();
		if ( isset( $_GET[ self::PARAM_ITEM ], $_GET[ self::PARAM_MENU ] ) && sanitize_key( wp_unslash( $_GET[ self::PARAM_MENU ] ) ) === $menu_id ) {
			$requested = sanitize_text_field( wp_unslash( $_GET[ self::PARAM_ITEM ] ) );
			if ( isset( $by_id[ $requested ] ) && self::item_is_renderable_in_context( $by_id[ $requested ], $by_id ) ) {
				for ( $node = $by_id[ $requested ]; $node; $node = ( '' !== $node['parent_id'] && isset( $by_id[ $node['parent_id'] ] ) ) ? $by_id[ $node['parent_id'] ] : null ) {
					$open[ $node['id'] ] = true;
				}
			}
		}

		$base   = remove_query_arg( array( self::PARAM_ITEM, self::PARAM_MENU ) );
		$reader = isset( $requested ) && isset( $open[ $requested ] ) && 'reader' === self::tile_state( $by_id[ $requested ], $tree ) ? $by_id[ $requested ] : null;
		$ctx    = array(
			'tree'     => $tree,
			'settings' => $settings,
			'open'     => $open,
			'uid'      => $instance_id,
			'url'      => static function ( $item_id ) use ( $base, $menu_id ) {
				return add_query_arg( array( self::PARAM_MENU => $menu_id, self::PARAM_ITEM => $item_id ), $base );
			},
		);
		$title = '' !== trim( $settings['block_title'] ) ? $settings['block_title'] : __( 'Mes ressources', 'logiprof-espace-enseignants' );

		if ( $reader ) {
			return self::render_reader( $reader, $by_id, $title, $menu_id, $instance_id, $ctx );
		}

		$user  = wp_get_current_user();
		$first = trim( (string) $user->first_name ) !== '' ? (string) $user->first_name : (string) $user->display_name;

		$html  = sprintf( '<div id="%s" class="lpee-root lph lpee-theme-tiles" data-lph data-lph-menu="%s">', esc_attr( $instance_id ), esc_attr( $menu_id ) );
		$html .= self::hero_open( $settings );
		$html .= '<div class="lph-hero-main' . ( '1' === $settings['show_art'] ? '' : ' no-art' ) . '"><div class="lph-hero-text">';
		$html .= '1' === $settings['show_greeting'] && '' !== $first ? '<p class="lph-hello">' . esc_html( sprintf( __( 'Bonjour %s', 'logiprof-espace-enseignants' ), $first ) ) . '</p>' : '';
		$html .= '<h2 class="lph-title">' . esc_html( $title ) . '</h2>';
		$html .= '' !== trim( wp_strip_all_tags( $settings['intro_text'] ) ) ? '<div class="lph-lead">' . wpautop( self::clean_intro( (string) $settings['intro_text'] ) ) . '</div>' : '';
		if ( '1' === $settings['show_search'] ) {
			$html .= '<div class="lph-search" role="search"><span class="dashicons dashicons-search" aria-hidden="true"></span>'
				. '<input type="search" class="lph-search-input" data-lph-search placeholder="' . esc_attr__( 'Rechercher une ressource…', 'logiprof-espace-enseignants' ) . '" aria-label="' . esc_attr__( 'Rechercher une ressource', 'logiprof-espace-enseignants' ) . '" autocomplete="off" hidden></div>';
		}
		$html .= '</div>' . ( '1' === $settings['show_art'] ? '<div class="lph-art">' . self::hub_art() . '</div>' : '' ) . '</div></header>';

		$html .= '<div class="lph-body"><div class="lph-results" data-lph-results hidden aria-live="polite"></div><div class="lph-grid" data-lph-grid>';
		$index = 0;

		foreach ( $top as $item ) {
			$state = self::tile_state( $item, $tree );
			// Les tuiles indisponibles ne prennent pas de couleur : l'alternance reste régulière.
			$color = 'unavailable' === $state ? '' : self::tile_hex( $item, $settings, $index );
			$html .= self::render_tile( $item, $state, $color, $ctx );

			if ( 'panel' === $state ) {
				$html .= self::render_panel( $item, $color, $ctx );
			}
		}

		return $html . '</div></div></div>';
	}

	/**
	 * Page du site affichée dans tuiles de contenu : même onglet, bandeau compact avec « Retour » (vers la rubrique,
	 * qui se rouvre telle que l'élève l'avait laissée) et le chemin parcouru.
	 */
	private static function render_reader( $item, $by_id, $title, $menu_id, $instance_id, $ctx ) {
		$parent = ( '' !== $item['parent_id'] && isset( $by_id[ $item['parent_id'] ] ) ) ? $item['parent_id'] : '';
		$home   = remove_query_arg( array( self::PARAM_ITEM, self::PARAM_MENU ) );
		$back   = '' !== $parent ? $ctx['url']( $parent ) : $home;
		$trail  = array();
		for ( $node = $item; $node; $node = ( '' !== $node['parent_id'] && isset( $by_id[ $node['parent_id'] ] ) ) ? $by_id[ $node['parent_id'] ] : null ) {
			array_unshift( $trail, $node );
		}

		$html  = sprintf( '<div id="%s" class="lpee-root lph lpee-theme-tiles is-reading" data-lph data-lph-menu="%s">', esc_attr( $instance_id ), esc_attr( $menu_id ) );
		$html .= self::hero_open( $ctx['settings'] );
		$html .= '<div class="lph-bar"><a class="lph-back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>' . esc_html__( 'Retour', 'logiprof-espace-enseignants' ) . '</a>';
		$html .= '<nav class="lph-crumbs" aria-label="' . esc_attr__( 'Vous êtes ici', 'logiprof-espace-enseignants' ) . '"><a href="' . esc_url( $home ) . '">' . esc_html( $title ) . '</a>';
		foreach ( $trail as $i => $node ) {
			$html .= '<span class="lph-sep" aria-hidden="true">›</span>';
			$html .= $i === count( $trail ) - 1 ? '<span aria-current="page">' . esc_html( $node['title'] ) . '</span>' : '<a href="' . esc_url( $ctx['url']( $node['id'] ) ) . '">' . esc_html( $node['title'] ) . '</a>';
		}
		$html .= '</nav></div><div class="lph-reader-head">' . self::render_icon( $item, 'lph-reader-icon' ) . '<h2 class="lph-title">' . esc_html( $item['title'] ) . '</h2></div></header>';

		$page = get_post( (int) $item['page_id'] );
		if ( ! $page || ( 'publish' !== $page->post_status && ! current_user_can( 'read_post', $page->ID ) ) ) {
			$body = '<p class="lph-empty">' . esc_html__( 'Cette page n’est pas disponible.', 'logiprof-espace-enseignants' ) . '</p>';
		} elseif ( post_password_required( $page ) ) {
			$body = get_the_password_form( $page );
		} else {
			// Contenu de la page, avec ses shortcodes ; tuiles de contenu ne s'affiche pas lui-même à l'intérieur.
			global $post;
			$previous = $post;
			$post     = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
			self::$reading = true;
			$body          = apply_filters( 'the_content', $page->post_content );
			self::$reading = false;
			$post          = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $previous ) {
				setup_postdata( $previous );
			}
		}
		$full  = get_permalink( (int) $item['page_id'] );
		$html .= '<div class="lph-body"><article class="lph-reader">' . $body . '</article>';
		$html .= $full ? '<p class="lph-reader-full"><a href="' . esc_url( $full ) . '">' . esc_html__( 'Ouvrir la page seule', 'logiprof-espace-enseignants' ) . '</a></p>' : '';

		return $html . '</div></div>';
	}

	/**
	 * Contenu d'un menu affiché dans un panneau (sans bandeau), attribut vue="panneau".
	 * Même présentation que l'intérieur d'une tuile : texte d'introduction mis en forme, liens en lignes
	 * compactes, sous-rubriques en onglets, outils dépliables sur place. Utilisé aussi par LogiOne.
	 */
	private static function render_embed( $menu_id, $items, $settings, $instance_id ) {
		$tree    = self::build_children_map( $items );
		$menu_id = sanitize_key( $menu_id );
		$open    = array();
		if ( isset( $_GET[ self::PARAM_ITEM ], $_GET[ self::PARAM_MENU ] ) && sanitize_key( wp_unslash( $_GET[ self::PARAM_MENU ] ) ) === $menu_id ) {
			$by_id = array();
			foreach ( $items as $item ) {
				$by_id[ $item['id'] ] = $item;
			}
			$requested = sanitize_text_field( wp_unslash( $_GET[ self::PARAM_ITEM ] ) );
			for ( $node = $by_id[ $requested ] ?? null; $node; $node = ( '' !== $node['parent_id'] && isset( $by_id[ $node['parent_id'] ] ) ) ? $by_id[ $node['parent_id'] ] : null ) {
				$open[ $node['id'] ] = true;
			}
		}
		$base = remove_query_arg( array( self::PARAM_ITEM, self::PARAM_MENU ) );
		$ctx  = array(
			'embed'    => true,
			'tree'     => $tree,
			'settings' => $settings,
			'open'     => $open,
			'uid'      => $instance_id,
			'url'      => static function ( $item_id ) use ( $base, $menu_id ) {
				return add_query_arg( array( self::PARAM_MENU => $menu_id, self::PARAM_ITEM => $item_id ), $base );
			},
		);
		$intro = isset( $settings['intro_text'] ) ? (string) $settings['intro_text'] : '';
		$html  = sprintf( '<div id="%s" class="lpee-root lph lph-embed" data-lph data-lph-menu="%s">', esc_attr( $instance_id ), esc_attr( $menu_id ) );
		$html .= '' !== trim( wp_strip_all_tags( $intro ) ) ? '<div class="lph-embed-intro">' . wpautop( self::clean_intro( $intro ) ) . '</div>' : '';
		$html .= self::render_folder_body( array( 'id' => '', 'title' => '' ), array(), $ctx );

		return $html . '</div>';
	}

	/** panel : se déplie dans la page (rubrique, outil) ; link : ouvre une destination ; unavailable : rien à ouvrir. */
	private static function tile_state( $item, $tree ) {
		if ( 'folder' === $item['type'] ) {
			return 'panel';
		}
		if ( 'text' === $item['type'] ) {
			return '' !== trim( wp_strip_all_tags( (string) ( $item['content'] ?? '' ) ) ) ? 'panel' : 'unavailable';
		}
		if ( isset( $item['destination_type'] ) && 'shortcode' === $item['destination_type'] ) {
			return '' !== trim( (string) $item['shortcode'] ) ? 'panel' : 'unavailable';
		}

		if ( '' === self::get_item_url( $item ) ) {
			return 'unavailable';
		}

		return self::link_mode( $item );
	}

	/**
	 * Ouverture d'un lien (présentation en tuiles) :
	 * reader : page du site affichée dans tuiles de contenu (même onglet, bouton Retour) — par défaut pour une page ;
	 * same : même onglet ; new : nouvel onglet (uniquement si choisi dans l'éditeur).
	 */
	private static function link_mode( $item ) {
		$choice = isset( $item['link_target'] ) ? $item['link_target'] : 'auto';
		$page   = isset( $item['destination_type'] ) && 'page' === $item['destination_type'] && ! empty( $item['page_id'] );

		if ( 'new' === $choice ) {
			return 'new';
		}

		return 'same';
	}

	public static function link_target_choices() {
		return array(
			'auto' => __( 'Dans le même onglet (recommandé)', 'logiprof-espace-enseignants' ),
			'new'  => __( 'Dans un nouvel onglet', 'logiprof-espace-enseignants' ),
		);
	}

	/** Lien d'un élément selon son mode : adresse tuiles de contenu (lecture), destination, avec ou sans nouvel onglet. */
	private static function link_attrs( $item, $state, $ctx ) {
		if ( 'reader' === $state ) {
			return ' href="' . esc_url( $ctx['url']( $item['id'] ) ) . '" data-lph-reader';
		}
		$attrs = ' href="' . esc_url( self::get_item_url( $item ) ) . '"';

		return 'new' === $state ? $attrs . ' target="_blank" rel="noopener noreferrer" data-lpee-newtab' : $attrs;
	}

	private static function link_go_icon( $state ) {
		if ( 'new' === $state ) {
			return '<span class="dashicons dashicons-external" aria-hidden="true"></span><span class="screen-reader-text"> ' . esc_html__( '(nouvel onglet)', 'logiprof-espace-enseignants' ) . '</span>';
		}

		return '<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>';
	}

	private static function render_tile( $item, $state, $color, $ctx ) {
		$description = trim( (string) $item['description'] );
		$size        = isset( $item['tile_size'] ) && 'wide' === $item['tile_size'] ? ' is-wide' : '';
		$classes     = 'lph-tile' . $size . ( 'folder' === $item['type'] ? ' is-folder' : '' );
		$style       = '' !== $color ? ' style="' . esc_attr( self::tile_vars( $color ) ) . '"' : '';
		$body        = self::render_icon( $item, 'lph-tile-icon' );
		$body       .= '<span class="lph-tile-body"><strong class="lph-tile-title">' . esc_html( $item['title'] ) . '</strong>';
		$body       .= '' !== $description ? '<span class="lph-tile-text">' . nl2br( esc_html( $description ) ) . '</span>' : '';
		$node        = ' data-lph-node="' . esc_attr( $item['id'] ) . '"' . $style;

		if ( 'unavailable' === $state ) {
			$body .= '<span class="lph-tile-status">' . esc_html__( 'Indisponible', 'logiprof-espace-enseignants' ) . '</span></span>';

			return '<div class="' . esc_attr( $classes . ' is-unavailable' ) . '"' . $node . ' aria-disabled="true">' . $body . '</div>';
		}

		if ( in_array( $state, array( 'reader', 'same', 'new' ), true ) ) {
			$body .= '<span class="lph-tile-cta">' . esc_html__( 'Ouvrir', 'logiprof-espace-enseignants' ) . self::link_go_icon( $state ) . '</span></span>';

			return '<a class="' . esc_attr( $classes ) . '"' . $node . self::link_attrs( $item, $state, $ctx ) . '>' . $body . '</a>';
		}

		$is_open = isset( $ctx['open'][ $item['id'] ] );
		$count   = 'folder' === $item['type'] ? count( self::get_renderable_children( $item['id'], $ctx['tree'] ) ) : 0;
		$meta    = $count ? sprintf( _n( '%d élément', '%d éléments', $count, 'logiprof-espace-enseignants' ), $count ) : ( 'text' === $item['type'] ? __( 'Texte', 'logiprof-espace-enseignants' ) : __( 'Outil', 'logiprof-espace-enseignants' ) );
		$body   .= '<span class="lph-tile-meta">' . esc_html( $meta ) . '</span>';
		$body   .= '<span class="lph-tile-cta"><span class="lph-cta-open">' . esc_html__( 'Afficher', 'logiprof-espace-enseignants' ) . '</span><span class="lph-cta-close">' . esc_html__( 'Masquer', 'logiprof-espace-enseignants' ) . '</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></span></span>';

		return sprintf(
			'<a class="%s" href="%s" role="button" data-lph-toggle="%s" aria-expanded="%s" aria-controls="%s"%s>%s</a>',
			esc_attr( $classes . ( $is_open ? ' is-active' : '' ) ),
			esc_url( $is_open ? remove_query_arg( array( self::PARAM_ITEM, self::PARAM_MENU ) ) : $ctx['url']( $item['id'] ) ),
			esc_attr( $item['id'] ),
			$is_open ? 'true' : 'false',
			esc_attr( $ctx['uid'] . '-p-' . sanitize_html_class( $item['id'] ) ),
			$node,
			$body
		);
	}

	/** Panneau déplié sous la tuile : titre, puis contenu de la rubrique ou de l'outil. */
	private static function render_panel( $item, $color, $ctx ) {
		$id    = $ctx['uid'] . '-p-' . sanitize_html_class( $item['id'] );
		$html  = sprintf( '<section id="%s" class="lph-panel" style="%s" data-lph-panel="%s" aria-labelledby="%s-h"%s>', esc_attr( $id ), esc_attr( self::tile_vars( $color ) ), esc_attr( $item['id'] ), esc_attr( $id ), isset( $ctx['open'][ $item['id'] ] ) ? '' : ' hidden' );
		$html .= '<div class="lph-panel-head"><h3 id="' . esc_attr( $id ) . '-h" class="lph-panel-title" tabindex="-1">' . esc_html( $item['title'] ) . '</h3>';
		$html .= '<a class="lph-close" href="' . esc_url( remove_query_arg( array( self::PARAM_ITEM, self::PARAM_MENU ) ) ) . '" data-lph-close role="button"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Fermer', 'logiprof-espace-enseignants' ) . '</span></a></div>';

		if ( 'folder' === $item['type'] ) {
			$html .= self::render_folder_body( $item, array( $item['title'] ), $ctx );
		} elseif ( 'text' === $item['type'] ) {
			$html .= self::render_text_block( $item, array( $item['title'] ), true, $item['id'] . '--texte' );
		} else {
			$html .= self::render_tool_output( $item );
		}

		return $html . '</section>';
	}

	/**
	 * Contenu d'une rubrique : d'abord ses liens et outils (lignes compactes), puis ses sous-rubriques en
	 * onglets. Un onglet affiche son contenu sur place (même règle, à tous les niveaux).
	 */
	private static function render_folder_body( $folder, $path, $ctx ) {
		$children = self::get_renderable_children( $folder['id'], $ctx['tree'] );

		if ( ! $children ) {
			return '<p class="lph-empty">' . esc_html( $ctx['settings']['empty_message'] ) . '</p>';
		}

		$folders = array_values( array_filter( $children, static function ( $c ) { return 'folder' === $c['type']; } ) );
		$entries = array_values( array_filter( $children, static function ( $c ) { return 'folder' !== $c['type']; } ) );
		$html    = '';

		// Liens et outils en lignes compactes ; un bloc de texte s'affiche tel quel, à sa place.
		$in_list = false;
		foreach ( $entries as $entry ) {
			if ( 'text' === $entry['type'] ) {
				$html   .= ( $in_list ? '</ul>' : '' ) . self::render_text_block( $entry, $path, true );
				$in_list = false;
				continue;
			}
			$html   .= ( $in_list ? '' : '<ul class="lph-links">' ) . '<li>' . self::render_entry( $entry, $path, $ctx ) . '</li>';
			$in_list = true;
		}
		$html .= $in_list ? '</ul>' : '';

		if ( $folders ) {
			// Onglet sélectionné : celui de l'adresse, sinon le premier (le navigateur peut restaurer le dernier choix).
			$selected = $folders[0]['id'];
			foreach ( $folders as $sub ) {
				if ( isset( $ctx['open'][ $sub['id'] ] ) ) {
					$selected = $sub['id'];
				}
			}

			$group = $ctx['uid'] . '-t-' . sanitize_html_class( $folder['id'] );
			$html .= '<div class="lph-tabs" role="tablist" data-lph-tabs="' . esc_attr( $folder['id'] ) . '" aria-label="' . esc_attr( $folder['title'] ) . '">';
			foreach ( $folders as $sub ) {
				$on    = $sub['id'] === $selected;
				$html .= sprintf(
					'<a class="lph-tab%s" href="%s" role="tab" id="%s" data-lph-tab="%s" data-lph-node="%s" aria-selected="%s" aria-controls="%s" tabindex="%s">%s<span>%s</span></a>',
					$on ? ' is-selected' : '',
					esc_url( $ctx['url']( $sub['id'] ) ),
					esc_attr( $group . '-' . sanitize_html_class( $sub['id'] ) ),
					esc_attr( $sub['id'] ),
					esc_attr( $sub['id'] ),
					$on ? 'true' : 'false',
					esc_attr( $group . '-' . sanitize_html_class( $sub['id'] ) . '-c' ),
					$on ? '0' : '-1',
					self::render_icon( $sub, 'lph-tab-icon' ),
					esc_html( $sub['title'] )
				);
			}
			$html .= '</div>';

			foreach ( $folders as $sub ) {
				$on          = $sub['id'] === $selected;
				$description = trim( (string) $sub['description'] );
				$html       .= sprintf( '<div class="lph-tabpanel" role="tabpanel" id="%s" aria-labelledby="%s" data-lph-tabpanel="%s"%s>', esc_attr( $group . '-' . sanitize_html_class( $sub['id'] ) . '-c' ), esc_attr( $group . '-' . sanitize_html_class( $sub['id'] ) ), esc_attr( $sub['id'] ), $on ? '' : ' hidden' );
				$html       .= '' !== $description ? '<p class="lph-tab-description">' . nl2br( esc_html( $description ) ) . '</p>' : '';
				$html       .= self::render_folder_body( $sub, array_merge( $path, array( $sub['title'] ) ), $ctx ) . '</div>';
			}
		}

		return $html;
	}

	/** Lien ou outil dans un panneau : une ligne compacte ; un outil se déplie sur place. */
	private static function render_entry( $item, $path, $ctx ) {
		$description = trim( (string) $item['description'] );
		$copy        = '<span class="lph-link-copy"><strong>' . esc_html( $item['title'] ) . '</strong>' . ( '' !== $description ? '<small>' . esc_html( $description ) . '</small>' : '' ) . '</span>';
		$attrs       = ' data-lph-node="' . esc_attr( $item['id'] ) . '" data-lph-path="' . esc_attr( implode( ' › ', $path ) ) . '"';
		$state       = self::tile_state( $item, $ctx['tree'] );
		if ( 'reader' === $state && ! empty( $ctx['embed'] ) ) {
			$state = 'same'; // panneau intégré ailleurs (LogiOne) : la page s'ouvre dans le même onglet
		}

		if ( 'panel' === $state ) {
			return '<details class="lph-toolbox"' . $attrs . ( isset( $ctx['open'][ $item['id'] ] ) ? ' open' : '' ) . '><summary class="lph-link">' . self::render_icon( $item, 'lph-link-icon' ) . $copy . '<span class="lph-link-go dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>' . self::render_tool_output( $item ) . '</details>';
		}

		if ( in_array( $state, array( 'reader', 'same', 'new' ), true ) ) {
			return '<a class="lph-link"' . $attrs . self::link_attrs( $item, $state, $ctx ) . '>' . self::render_icon( $item, 'lph-link-icon' ) . $copy . str_replace( 'class="dashicons', 'class="lph-link-go dashicons', self::link_go_icon( $state ) ) . '</a>';
		}

		return '<div class="lph-link is-unavailable"' . $attrs . ' aria-disabled="true">' . self::render_icon( $item, 'lph-link-icon' ) . $copy . '<span class="lph-link-status">' . esc_html__( 'Indisponible', 'logiprof-espace-enseignants' ) . '</span></div>';
	}

	/** Bloc de texte mis en forme (dans une rubrique, ou contenu d'une tuile « texte »). */
	private static function render_text_block( $item, $path, $searchable, $node_id = '' ) {
		$content = self::clean_intro( (string) ( $item['content'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return '';
		}
		$attrs = $searchable ? ' data-lph-node="' . esc_attr( '' !== $node_id ? $node_id : $item['id'] ) . '" data-lph-path="' . esc_attr( implode( ' › ', $path ) ) . '" data-lph-title="' . esc_attr( $item['title'] ) . '" tabindex="-1"' : '';

		return '<div class="lph-text"' . $attrs . '>' . wpautop( $content ) . '</div>';
	}

	private static function render_tool_output( $item ) {
		$tool = do_shortcode( shortcode_unautop( trim( (string) $item['shortcode'] ) ) );

		return '<div class="lph-tool">' . ( '' !== trim( $tool ) ? $tool : '<p class="lph-empty">' . esc_html__( 'L’outil n’a retourné aucun contenu.', 'logiprof-espace-enseignants' ) . '</p>' ) . '</div>';
	}

	/** Illustration du bandeau : Noki, la mascotte LogiProf, sur sa palette (comme dans LogiOne). */
	private static function hub_art() {
		$base = plugins_url( 'assets/noki-logione', __FILE__ );

		return '<picture><source srcset="' . esc_url( $base . '.webp' ) . '" type="image/webp"><img class="lph-noki" src="' . esc_url( $base . '.png' ) . '" alt="" width="408" height="555" decoding="async"></picture>';
	}

	private static function render_item_list( $parent_id, $tree, $settings, $instance_id, $level ) {
		$children = self::get_renderable_children( $parent_id, $tree );

		if ( empty( $children ) ) {
			return '';
		}

		$output = sprintf(
			'<ul class="lpee-list lpee-level-%d" data-lpee-level="%d">',
			(int) $level,
			(int) $level
		);

		foreach ( $children as $item ) {
			$output .= self::render_item( $item, $tree, $settings, $instance_id, $level );
		}

		$output .= '</ul>';

		return $output;
	}

	private static function render_item( $item, $tree, $settings, $instance_id, $level ) {
		if ( 'folder' === $item['type'] ) {
			return self::render_folder_item( $item, $tree, $settings, $instance_id, $level );
		}
		if ( 'text' === $item['type'] ) {
			$content = self::clean_intro( (string) ( $item['content'] ?? '' ) );

			return '' === trim( wp_strip_all_tags( $content ) ) ? '' : '<li class="lpee-node lpee-node-text" data-lpee-node><div class="lpee-text">' . wpautop( $content ) . '</div></li>';
		}

		if ( isset( $item['destination_type'] ) && 'shortcode' === $item['destination_type'] ) {
			return self::render_shortcode_tool_item( $item, $settings, $instance_id );
		}

		return self::render_link_item( $item, $settings, $level );
	}

	private static function render_folder_item( $item, $tree, $settings, $instance_id, $level ) {
		$children       = self::get_renderable_children( $item['id'], $tree );
		$is_open        = ! empty( $item['open'] ) || self::has_open_descendant( $item['id'], $tree );
		$item_id        = sanitize_html_class( $item['id'] );
		$button_id      = $instance_id . '-toggle-' . $item_id;
		$panel_id       = $instance_id . '-panel-' . $item_id;
		$node_classes   = 'lpee-node lpee-node-folder' . ( $is_open ? ' is-open' : '' );
		$panel_attrs    = $is_open ? '' : ' hidden';
		$description    = trim( (string) $item['description'] );

		$output  = sprintf( '<li class="%s" data-lpee-node>', esc_attr( $node_classes ) );
		$output .= sprintf(
			'<button id="%s" type="button" class="lpee-row lpee-toggle" data-lpee-toggle aria-expanded="%s" aria-controls="%s">',
			esc_attr( $button_id ),
			$is_open ? 'true' : 'false',
			esc_attr( $panel_id )
		);
		$output .= self::render_dashicon( $item['icon'], 'lpee-row-icon' );
		$output .= '<span class="lpee-row-copy">';
		$output .= sprintf( '<span class="lpee-row-title">%s</span>', esc_html( $item['title'] ) );

		if ( '' !== $description ) {
			$output .= sprintf( '<span class="lpee-row-description">%s</span>', nl2br( esc_html( $description ) ) );
		}

		$output .= '</span>';
		$output .= '<span class="lpee-chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
		$output .= '</button>';
		$output .= sprintf(
			'<div id="%s" class="lpee-panel" role="region" aria-labelledby="%s"%s>',
			esc_attr( $panel_id ),
			esc_attr( $button_id ),
			$panel_attrs
		);

		if ( empty( $children ) ) {
			$output .= sprintf( '<p class="lpee-empty">%s</p>', esc_html( $settings['empty_message'] ) );
		} else {
			$output .= self::render_item_list( $item['id'], $tree, $settings, $instance_id, $level + 1 );
		}

		$output .= '</div>';
		$output .= '</li>';

		return $output;
	}

	private static function render_shortcode_tool_item( $item, $settings, $instance_id ) {
		$shortcode   = isset( $item['shortcode'] ) ? trim( (string) $item['shortcode'] ) : '';
		$description = trim( (string) $item['description'] );

		if ( '' === $shortcode ) {
			$output  = '<li class="lpee-node lpee-node-link is-disabled" data-lpee-node>';
			$output .= '<div class="lpee-row lpee-link is-unavailable" role="button" aria-disabled="true">';
			$output .= self::render_dashicon( $item['icon'], 'lpee-row-icon' );
			$output .= '<span class="lpee-row-copy">';
			$output .= sprintf( '<span class="lpee-row-title">%s</span>', esc_html( $item['title'] ) );

			if ( '' !== $description ) {
				$output .= sprintf( '<span class="lpee-row-description">%s</span>', nl2br( esc_html( $description ) ) );
			}

			$output .= '</span>';
			$output .= '<span class="lpee-status">' . esc_html__( 'Shortcode à renseigner', 'logiprof-espace-enseignants' ) . '</span>';
			$output .= '</div></li>';

			return $output;
		}

		$item_id               = sanitize_html_class( $item['id'] );
		$button_id             = $instance_id . '-toggle-shortcode-' . $item_id;
		$panel_id              = $instance_id . '-panel-shortcode-' . $item_id;
		$shortcode_html        = do_shortcode( shortcode_unautop( $shortcode ) );
		$shortcode_has_content = '' !== trim( wp_strip_all_tags( $shortcode_html ) ) || false !== strpos( $shortcode_html, '<' );

		$output  = '<li class="lpee-node lpee-node-shortcode" data-lpee-node>';
		$output .= sprintf(
			'<button id="%s" type="button" class="lpee-row lpee-toggle lpee-shortcode-toggle" data-lpee-toggle aria-expanded="false" aria-controls="%s">',
			esc_attr( $button_id ),
			esc_attr( $panel_id )
		);
		$output .= self::render_dashicon( $item['icon'], 'lpee-row-icon' );
		$output .= '<span class="lpee-row-copy">';
		$output .= sprintf( '<span class="lpee-row-title">%s</span>', esc_html( $item['title'] ) );

		if ( '' !== $description ) {
			$output .= sprintf( '<span class="lpee-row-description">%s</span>', nl2br( esc_html( $description ) ) );
		}

		$output .= '</span>';
		$output .= '<span class="lpee-chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
		$output .= '</button>';
		$output .= sprintf(
			'<div id="%s" class="lpee-panel lpee-shortcode-panel" role="region" aria-labelledby="%s" hidden>',
			esc_attr( $panel_id ),
			esc_attr( $button_id )
		);
		$output .= '<div class="lpee-shortcode-content">';
		$output .= $shortcode_has_content ? $shortcode_html : '<p class="lpee-empty">' . esc_html__( 'L’outil n’a retourné aucun contenu.', 'logiprof-espace-enseignants' ) . '</p>';
		$output .= '</div></div></li>';

		return $output;
	}

	private static function render_link_item( $item, $settings, $level ) {
		$url         = self::get_item_url( $item );
		$description = trim( (string) $item['description'] );
		$classes     = 'lpee-node lpee-node-link' . ( $url ? '' : ' is-disabled' );
		$output      = sprintf( '<li class="%s" data-lpee-node>', esc_attr( $classes ) );

		$choice  = isset( $item['link_target'] ) ? $item['link_target'] : 'auto';
		$new_tab = 'new' === $choice || ( 'auto' === $choice && self::LIST_AUTO_NEW_TAB );

		if ( $url && $new_tab ) {
			$output .= sprintf(
				'<a class="lpee-row lpee-link" href="%s" target="_blank" rel="noopener noreferrer" data-lpee-newtab aria-label="%s">',
				esc_url( $url ),
				esc_attr( sprintf( __( '%s, ouvre dans un nouvel onglet', 'logiprof-espace-enseignants' ), $item['title'] ) )
			);
		} elseif ( $url ) {
			$output .= sprintf( '<a class="lpee-row lpee-link" href="%s">', esc_url( $url ) );
		} else {
			$output .= '<div class="lpee-row lpee-link is-unavailable" role="link" aria-disabled="true">';
		}

		$output .= self::render_dashicon( $item['icon'], 'lpee-row-icon' );
		$output .= '<span class="lpee-row-copy">';
		$output .= sprintf( '<span class="lpee-row-title">%s</span>', esc_html( $item['title'] ) );

		if ( '' !== $description ) {
			$output .= sprintf( '<span class="lpee-row-description">%s</span>', nl2br( esc_html( $description ) ) );
		}

		$output .= '</span>';

		if ( $url && $new_tab ) {
			$output .= '<span class="lpee-external dashicons dashicons-external" aria-hidden="true"></span>';
			$output .= '<span class="screen-reader-text">' . esc_html__( 'Ouvre dans un nouvel onglet', 'logiprof-espace-enseignants' ) . '</span>';
			$output .= '</a>';
		} elseif ( $url ) {
			$output .= '<span class="lpee-external dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>';
			$output .= '</a>';
		} else {
			$output .= '<span class="lpee-status">' . esc_html__( 'Indisponible', 'logiprof-espace-enseignants' ) . '</span>';
			$output .= '</div>';
		}

		$output .= '</li>';

		return $output;
	}

	private static function get_item_url( $item ) {
		if ( 'link' !== $item['type'] ) {
			return '';
		}

		$destination_type = isset( $item['destination_type'] ) ? $item['destination_type'] : 'page';

		if ( 'page' === $destination_type && ! empty( $item['page_id'] ) ) {
			$url = get_permalink( (int) $item['page_id'] );
			return $url ? $url : '';
		}

		if ( 'admin' === $destination_type ) {
			return admin_url();
		}

		if ( 'url' === $destination_type && ! empty( $item['url'] ) ) {
			return $item['url'];
		}

		return '';
	}

	private static function build_children_map( $items ) {
		$tree = array( '' => array() );

		foreach ( $items as $item ) {
			if ( ! isset( $tree[ $item['parent_id'] ] ) ) {
				$tree[ $item['parent_id'] ] = array();
			}

			$tree[ $item['parent_id'] ][] = $item;
		}

		foreach ( $tree as $parent_id => $children ) {
			usort(
				$children,
				function ( $a, $b ) {
					$order_a = isset( $a['order'] ) ? (float) $a['order'] : 0;
					$order_b = isset( $b['order'] ) ? (float) $b['order'] : 0;

					if ( $order_a == $order_b ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
						return strcasecmp( $a['title'], $b['title'] );
					}

					return $order_a <=> $order_b;
				}
			);

			$tree[ $parent_id ] = $children;
		}

		return $tree;
	}

	private static function get_renderable_children( $parent_id, $tree ) {
		if ( empty( $tree[ $parent_id ] ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$tree[ $parent_id ],
				function ( $item ) {
					return empty( $item['hidden'] ) && self::current_user_can_view_item( $item );
				}
			)
		);
	}

	private static function has_open_descendant( $item_id, $tree ) {
		$children = self::get_renderable_children( $item_id, $tree );

		if ( empty( $children ) ) {
			return false;
		}

		foreach ( $children as $child ) {
			if ( 'folder' !== $child['type'] ) {
				continue;
			}

			if ( ! empty( $child['open'] ) || self::has_open_descendant( $child['id'], $tree ) ) {
				return true;
			}
		}

		return false;
	}

	private static function current_user_can_view_menu( $settings ) {
		$user_roles = self::current_user_role_slugs();

		if ( empty( $user_roles ) ) {
			return false;
		}

		if ( '1' !== $settings['restrict_menu_roles'] ) {
			return true;
		}

		return (bool) array_intersect( $user_roles, $settings['allowed_roles'] );
	}

	private static function current_user_can_view_item( $item ) {
		$allowed_roles = isset( $item['allowed_roles'] ) && is_array( $item['allowed_roles'] ) ? array_map( 'sanitize_key', $item['allowed_roles'] ) : array();

		if ( empty( $allowed_roles ) ) {
			return true;
		}

		return (bool) array_intersect( self::current_user_role_slugs(), $allowed_roles );
	}

	private static function current_user_role_slugs() {
		if ( ! is_user_logged_in() ) {
			return array();
		}

		$user = wp_get_current_user();

		if ( ! $user || empty( $user->roles ) ) {
			return array();
		}

		return array_map( 'sanitize_key', (array) $user->roles );
	}

	private static function render_dashicon( $icon, $class ) {
		$icons = self::icon_library();
		$icon  = isset( $icons[ $icon ] ) ? $icon : 'book';

		return sprintf(
			'<span class="%s dashicons %s" aria-hidden="true"></span>',
			esc_attr( $class ),
			esc_attr( $icons[ $icon ]['class'] )
		);
	}

	private static function build_style_attribute( $settings ) {
		if ( 'custom' !== $settings['theme'] ) {
			return '--lpee-max-width:' . (int) $settings['max_width'] . 'px';
		}

		$style = array(
			'--lpee-primary:' . $settings['primary_color'],
			'--lpee-bg:' . $settings['container_bg'],
			'--lpee-item-bg:' . $settings['item_bg'],
			'--lpee-text:' . $settings['text_color'],
			'--lpee-max-width:' . (int) $settings['max_width'] . 'px',
			'--lpee-radius:' . (int) $settings['radius'] . 'px',
		);

		return implode( ';', $style );
	}

	private static function sanitize_menus_payload( $payload ) {
		$decoded = json_decode( (string) $payload, true );
		$warnings = array();

		if ( ! is_array( $decoded ) ) {
			return array(
				'menus'    => self::get_menus(),
				'warnings' => array( __( 'Les menus envoyés n’ont pas pu être lus. Les menus précédents ont été conservés.', 'logiprof-espace-enseignants' ) ),
			);
		}

		$menus = self::sanitize_menus_array( $decoded, self::get_menus(), $warnings );

		return array(
			'menus'    => $menus,
			'warnings' => $warnings,
		);
	}

	private static function sanitize_menus_array( $raw_menus, $existing_menus = array(), &$warnings = array() ) {
		$existing_by_id = array();

		foreach ( (array) $existing_menus as $existing_menu ) {
			if ( isset( $existing_menu['id'] ) ) {
				$existing_by_id[ $existing_menu['id'] ] = $existing_menu;
			}
		}

		$menus    = array();
		$seen_ids = array();

		foreach ( (array) $raw_menus as $index => $raw_menu ) {
			if ( ! is_array( $raw_menu ) ) {
				continue;
			}

			$name = isset( $raw_menu['name'] ) ? sanitize_text_field( $raw_menu['name'] ) : '';

			if ( '' === trim( $name ) ) {
				$name = sprintf( __( 'Menu %d', 'logiprof-espace-enseignants' ), count( $menus ) + 1 );
			}

			$id = isset( $raw_menu['id'] ) ? sanitize_key( $raw_menu['id'] ) : '';

			if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
				$id = self::generate_menu_id( $name, $index, $seen_ids );
			}

			$seen_ids[ $id ] = true;

			$existing_items = isset( $existing_by_id[ $id ]['items'] ) && is_array( $existing_by_id[ $id ]['items'] ) ? $existing_by_id[ $id ]['items'] : array();
			$item_result    = self::sanitize_items_array( isset( $raw_menu['items'] ) ? $raw_menu['items'] : array(), $existing_items );

			if ( ! empty( $item_result['warnings'] ) ) {
				$warnings = array_merge( $warnings, $item_result['warnings'] );
			}

			$menus[] = array(
				'id'       => $id,
				'name'     => $name,
				'items'    => $item_result['items'],
				'settings' => self::sanitize_settings( isset( $raw_menu['settings'] ) && is_array( $raw_menu['settings'] ) ? $raw_menu['settings'] : array() ),
				'order'    => isset( $raw_menu['order'] ) ? max( 1, absint( $raw_menu['order'] ) ) : count( $menus ) + 1,
				'_index'   => (int) $index,
			);
		}

		if ( empty( $menus ) ) {
			if ( ! empty( $existing_menus ) ) {
				$warnings[] = __( 'Aucun menu valide n’a été envoyé. Les menus précédents ont été conservés.', 'logiprof-espace-enseignants' );
				return $existing_menus;
			}

			$warnings[] = __( 'Aucun menu valide n’a été envoyé. Un menu principal a été recréé.', 'logiprof-espace-enseignants' );
			return array( self::default_menu() );
		}

		usort(
			$menus,
			function ( $a, $b ) {
				$order_a = isset( $a['order'] ) ? (int) $a['order'] : 0;
				$order_b = isset( $b['order'] ) ? (int) $b['order'] : 0;

				if ( $order_a === $order_b ) {
					$index_a = isset( $a['_index'] ) ? (int) $a['_index'] : 0;
					$index_b = isset( $b['_index'] ) ? (int) $b['_index'] : 0;
					return $index_a <=> $index_b;
				}

				return $order_a <=> $order_b;
			}
		);

		foreach ( $menus as $index => $menu ) {
			unset( $menus[ $index ]['_index'] );
			$menus[ $index ]['order'] = $index + 1;
		}

		return $menus;
	}

	private static function sanitize_items_payload( $payload, $existing_items = null ) {
		$decoded = json_decode( (string) $payload, true );

		if ( ! is_array( $decoded ) ) {
			return array(
				'items'    => null === $existing_items ? self::get_items() : (array) $existing_items,
				'warnings' => array( __( 'Le contenu envoyé n’a pas pu être lu. Les éléments précédents ont été conservés.', 'logiprof-espace-enseignants' ) ),
			);
		}

		return self::sanitize_items_array( $decoded, null === $existing_items ? self::get_items() : (array) $existing_items );
	}

	private static function sanitize_items_array( $decoded, $existing = array() ) {
		$warnings       = array();
		$existing_by_id = array();

		if ( ! is_array( $decoded ) ) {
			return array(
				'items'    => (array) $existing,
				'warnings' => array( __( 'Une arborescence de menu n’a pas pu être lue. Son contenu précédent a été conservé.', 'logiprof-espace-enseignants' ) ),
			);
		}

		foreach ( (array) $existing as $existing_item ) {
			if ( isset( $existing_item['id'] ) ) {
				$existing_by_id[ $existing_item['id'] ] = $existing_item;
			}
		}

		$icons        = self::icon_library();
		$allowed_icon = array_keys( $icons );
		$role_keys    = array_keys( self::get_roles() );
		$items        = array();
		$seen_ids     = array();

		foreach ( $decoded as $index => $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$title = isset( $raw_item['title'] ) ? sanitize_text_field( $raw_item['title'] ) : '';

			if ( '' === trim( $title ) ) {
				continue;
			}

			$id = isset( $raw_item['id'] ) ? sanitize_key( $raw_item['id'] ) : '';

			if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
				$id = self::generate_item_id( $index );
			}

			$seen_ids[ $id ] = true;

			$type = isset( $raw_item['type'] ) && in_array( $raw_item['type'], array( 'link', 'text' ), true ) ? $raw_item['type'] : 'folder';
			$icon = isset( $raw_item['icon'] ) && in_array( $raw_item['icon'], $allowed_icon, true ) ? $raw_item['icon'] : 'book';

			$destination_type = isset( $raw_item['destination_type'] ) && in_array( $raw_item['destination_type'], array( 'page', 'url', 'admin', 'shortcode' ), true ) ? $raw_item['destination_type'] : 'page';
			$item_roles       = array();

			if ( isset( $raw_item['allowed_roles'] ) && is_array( $raw_item['allowed_roles'] ) ) {
				$item_roles = array_values( array_intersect( array_map( 'sanitize_key', $raw_item['allowed_roles'] ), $role_keys ) );
			}

			$items[] = array(
				'id'               => $id,
				'type'             => $type,
				'title'            => $title,
				'description'      => isset( $raw_item['description'] ) ? sanitize_textarea_field( $raw_item['description'] ) : '',
				'icon'             => $icon,
				'parent_id'        => isset( $raw_item['parent_id'] ) ? sanitize_key( $raw_item['parent_id'] ) : '',
				'allowed_roles'    => $item_roles,
				'hidden'           => ! empty( $raw_item['hidden'] ) ? '1' : '0',
				'open'             => ! empty( $raw_item['open'] ) && 'folder' === $type ? '1' : '0',
				'include_menu'     => 'folder' === $type && ! empty( $raw_item['include_menu'] ) ? sanitize_key( $raw_item['include_menu'] ) : '',
				'include_inline'   => 'folder' === $type && ! empty( $raw_item['include_menu'] ) && ! empty( $raw_item['include_inline'] ) ? '1' : '0',
				'tile_color'       => isset( $raw_item['tile_color'] ) && isset( self::tile_color_choices()[ $raw_item['tile_color'] ] ) ? $raw_item['tile_color'] : 'auto',
				'tile_size'        => isset( $raw_item['tile_size'] ) && 'wide' === $raw_item['tile_size'] ? 'wide' : 'normal',
				'tile_custom'      => isset( $raw_item['tile_custom'] ) && sanitize_hex_color( $raw_item['tile_custom'] ) ? sanitize_hex_color( $raw_item['tile_custom'] ) : '',
				'icon_image'       => isset( $raw_item['icon_image'] ) ? absint( $raw_item['icon_image'] ) : 0,
				'link_target'      => isset( $raw_item['link_target'] ) && in_array( $raw_item['link_target'], array( 'same', 'new' ), true ) ? $raw_item['link_target'] : 'auto',
				'destination_type' => 'link' === $type ? $destination_type : '',
				'page_id'          => 'link' === $type && ! empty( $raw_item['page_id'] ) ? absint( $raw_item['page_id'] ) : 0,
				'url'              => 'link' === $type && 'url' === $destination_type && ! empty( $raw_item['url'] ) ? esc_url_raw( trim( $raw_item['url'] ) ) : '',
				'shortcode'        => 'link' === $type && 'shortcode' === $destination_type && ! empty( $raw_item['shortcode'] ) ? sanitize_textarea_field( $raw_item['shortcode'] ) : '',
				'content'          => 'text' === $type && isset( $raw_item['content'] ) ? self::clean_intro( (string) $raw_item['content'] ) : '',
				'order'            => isset( $raw_item['order'] ) ? absint( $raw_item['order'] ) : ( $index + 1 ),
				'_index'           => (int) $index,
			);
		}

		$items = self::validate_item_relationships( $items, $existing_by_id, $warnings );
		$items = self::normalize_item_order( $items );

		return array(
			'items'    => $items,
			'warnings' => $warnings,
		);
	}

	private static function validate_item_relationships( $items, $existing_by_id, &$warnings ) {
		$map = array();

		foreach ( $items as $index => $item ) {
			$map[ $item['id'] ] = $index;
		}

		foreach ( $items as $index => $item ) {
			if ( '' === $item['parent_id'] ) {
				continue;
			}

			if ( $item['parent_id'] === $item['id'] || ! isset( $map[ $item['parent_id'] ] ) ) {
				$items[ $index ]['parent_id'] = '';
				$warnings[] = sprintf( __( 'Le parent de “%s” était invalide. L’élément a été replacé au premier niveau.', 'logiprof-espace-enseignants' ), $item['title'] );
				continue;
			}

			$parent = $items[ $map[ $item['parent_id'] ] ];

			if ( 'folder' !== $parent['type'] ) {
				$items[ $index ]['parent_id'] = '';
				$warnings[] = sprintf( __( '“%s” ne pouvait pas être placé sous un lien. L’élément a été replacé au premier niveau.', 'logiprof-espace-enseignants' ), $item['title'] );
			}
		}

		$children_by_parent = array();

		foreach ( $items as $item ) {
			if ( '' === $item['parent_id'] ) {
				continue;
			}

			if ( ! isset( $children_by_parent[ $item['parent_id'] ] ) ) {
				$children_by_parent[ $item['parent_id'] ] = array();
			}

			$children_by_parent[ $item['parent_id'] ][] = $item['id'];
		}

		foreach ( $items as $index => $item ) {
			if ( 'folder' === $item['type'] || empty( $children_by_parent[ $item['id'] ] ) ) {
				continue;
			}

			if ( isset( $existing_by_id[ $item['id'] ] ) && 'folder' === $existing_by_id[ $item['id'] ]['type'] ) {
				$items[ $index ]['type']             = 'folder';
				$items[ $index ]['destination_type'] = '';
				$items[ $index ]['page_id']          = 0;
				$items[ $index ]['url']              = '';
				$items[ $index ]['shortcode']        = '';
				$warnings[] = sprintf( __( '“%s” contient des éléments. Son type rubrique a été conservé pour éviter une perte de structure.', 'logiprof-espace-enseignants' ), $item['title'] );
			} else {
				foreach ( $children_by_parent[ $item['id'] ] as $child_id ) {
					if ( isset( $map[ $child_id ] ) ) {
						$items[ $map[ $child_id ] ]['parent_id'] = $item['parent_id'];
					}
				}

				$warnings[] = sprintf( __( 'Les sous-éléments de “%s” ont été replacés au même niveau, car un lien ne peut pas contenir d’éléments.', 'logiprof-espace-enseignants' ), $item['title'] );
			}
		}

		$map = array();
		foreach ( $items as $index => $item ) {
			$map[ $item['id'] ] = $index;
		}

		foreach ( $items as $index => $item ) {
			$visited = array( $item['id'] => true );
			$parent_id = $item['parent_id'];

			while ( '' !== $parent_id ) {
				if ( isset( $visited[ $parent_id ] ) ) {
					$items[ $index ]['parent_id'] = '';
					$warnings[] = sprintf( __( 'Le déplacement de “%s” créait une boucle. L’élément a été replacé au premier niveau.', 'logiprof-espace-enseignants' ), $item['title'] );
					break;
				}

				$visited[ $parent_id ] = true;

				if ( ! isset( $map[ $parent_id ] ) ) {
					break;
				}

				$parent_id = $items[ $map[ $parent_id ] ]['parent_id'];
			}
		}

		return $items;
	}

	private static function normalize_item_order( $items ) {
		$groups = array();

		foreach ( $items as $item ) {
			$parent_id = $item['parent_id'];

			if ( ! isset( $groups[ $parent_id ] ) ) {
				$groups[ $parent_id ] = array();
			}

			$groups[ $parent_id ][] = $item;
		}

		$normalized = array();

		foreach ( $groups as $children ) {
			usort(
				$children,
				function ( $a, $b ) {
					$order_a = isset( $a['order'] ) ? (int) $a['order'] : 0;
					$order_b = isset( $b['order'] ) ? (int) $b['order'] : 0;

					if ( $order_a === $order_b ) {
						$index_a = isset( $a['_index'] ) ? (int) $a['_index'] : 0;
						$index_b = isset( $b['_index'] ) ? (int) $b['_index'] : 0;
						return $index_a <=> $index_b;
					}

					return $order_a <=> $order_b;
				}
			);

			foreach ( $children as $position => $child ) {
				unset( $child['_index'] );
				$child['order'] = $position + 1;
				$normalized[] = $child;
			}
		}

		return $normalized;
	}

	private static function generate_item_id( $seed ) {
		return 'lpee_' . substr( md5( wp_rand() . ':' . microtime( true ) . ':' . (string) $seed ), 0, 12 );
	}

	private static function sanitize_settings( $raw ) {
		$defaults = self::default_settings();
		$roles    = self::get_roles();

		$settings = array(
			'theme'                => self::sanitize_theme( isset( $raw['theme'] ) ? $raw['theme'] : '' ),
			'tile_text'            => isset( $raw['tile_text'] ) ? sanitize_text_field( $raw['tile_text'] ) : $defaults['tile_text'],
			'tile_icon'            => isset( $raw['tile_icon'] ) ? absint( $raw['tile_icon'] ) : 0,
			'tile_color'           => isset( $raw['tile_color'] ) && ( isset( self::tile_colors()[ $raw['tile_color'] ] ) || sanitize_hex_color( $raw['tile_color'] ) ) ? (string) $raw['tile_color'] : $defaults['tile_color'],
			'intro_text'           => isset( $raw['intro_text'] ) ? self::clean_intro( (string) $raw['intro_text'] ) : $defaults['intro_text'],
			'show_search'          => isset( $raw['show_search'] ) ? ( ! empty( $raw['show_search'] ) && '0' !== (string) $raw['show_search'] ? '1' : '0' ) : $defaults['show_search'],
			'show_greeting'        => isset( $raw['show_greeting'] ) ? ( ! empty( $raw['show_greeting'] ) && '0' !== (string) $raw['show_greeting'] ? '1' : '0' ) : $defaults['show_greeting'],
			'tile_palette'         => self::sanitize_palette( isset( $raw['tile_palette'] ) ? $raw['tile_palette'] : null ),
			'hero_color'           => isset( $raw['hero_color'] ) && sanitize_hex_color( (string) $raw['hero_color'] ) ? sanitize_hex_color( (string) $raw['hero_color'] ) : $defaults['hero_color'],
			'show_art'             => isset( $raw['show_art'] ) ? ( ! empty( $raw['show_art'] ) && '0' !== (string) $raw['show_art'] ? '1' : '0' ) : $defaults['show_art'],
			'block_title'          => isset( $raw['block_title'] ) ? sanitize_text_field( $raw['block_title'] ) : $defaults['block_title'],
			'show_title'           => ! empty( $raw['show_title'] ) ? '1' : '0',
			'primary_color'        => self::sanitize_color( isset( $raw['primary_color'] ) ? $raw['primary_color'] : '', $defaults['primary_color'] ),
			'container_bg'         => self::sanitize_color( isset( $raw['container_bg'] ) ? $raw['container_bg'] : '', $defaults['container_bg'] ),
			'item_bg'              => self::sanitize_color( isset( $raw['item_bg'] ) ? $raw['item_bg'] : '', $defaults['item_bg'] ),
			'text_color'           => self::sanitize_color( isset( $raw['text_color'] ) ? $raw['text_color'] : '', $defaults['text_color'] ),
			'max_width'            => isset( $raw['max_width'] ) ? min( 1600, max( 320, absint( $raw['max_width'] ) ) ) : $defaults['max_width'],
			'radius'               => isset( $raw['radius'] ) ? min( 32, max( 0, absint( $raw['radius'] ) ) ) : $defaults['radius'],
			'spacing'              => isset( $raw['spacing'] ) && in_array( $raw['spacing'], array( 'compact', 'comfortable' ), true ) ? $raw['spacing'] : $defaults['spacing'],
			'empty_message'        => isset( $raw['empty_message'] ) ? sanitize_text_field( $raw['empty_message'] ) : $defaults['empty_message'],
			'single_open_siblings' => ! empty( $raw['single_open_siblings'] ) ? '1' : '0',
			'restrict_menu_roles'  => ! empty( $raw['restrict_menu_roles'] ) ? '1' : '0',
			'allowed_roles'        => array(),
		);

		if ( isset( $raw['allowed_roles'] ) && is_array( $raw['allowed_roles'] ) ) {
			$posted_roles = array_map( 'sanitize_key', $raw['allowed_roles'] );
			$settings['allowed_roles'] = array_values( array_intersect( $posted_roles, array_keys( $roles ) ) );
		}

		return $settings;
	}

	private static function sanitize_color( $value, $fallback ) {
		$value = sanitize_hex_color( $value );
		return $value ? $value : $fallback;
	}

	private static function get_menus() {
		$menus = get_option( self::OPTION_MENUS, false );

		if ( is_array( $menus ) && ! empty( $menus ) ) {
			$warnings = array();
			return self::sanitize_menus_array( $menus, array(), $warnings );
		}

		return self::legacy_menu_collection();
	}

	private static function legacy_menu_collection() {
		$items    = get_option( self::OPTION_ITEMS, false );
		$settings = get_option( self::OPTION_SETTINGS, false );

		if ( ! is_array( $items ) ) {
			$items = self::default_items();
		}

		if ( ! is_array( $settings ) ) {
			$settings = self::default_settings();
		}

		$item_result = self::sanitize_items_array( $items, array() );

		return array(
			array(
				'id'       => self::DEFAULT_MENU_ID,
				'name'     => __( 'Ressources', 'logiprof-espace-enseignants' ),
				'items'    => $item_result['items'],
				'settings' => self::sanitize_settings( $settings ),
				'order'    => 1,
			),
		);
	}

	private static function default_menu() {
		return array(
			'id'       => self::DEFAULT_MENU_ID,
			'name'     => __( 'Ressources', 'logiprof-espace-enseignants' ),
			'items'    => self::default_items(),
			'settings' => self::default_settings(),
			'order'    => 1,
		);
	}

	private static function get_active_menu_id( $menus ) {
		$requested_id = isset( $_GET['lpee_menu'] ) ? sanitize_key( wp_unslash( $_GET['lpee_menu'] ) ) : ( isset( $_GET['menu'] ) ? sanitize_key( wp_unslash( $_GET['menu'] ) ) : '' );

		if ( '' !== $requested_id && self::get_menu_by_id( $menus, $requested_id ) ) {
			return $requested_id;
		}

		$default_menu = self::get_menu_by_id( $menus, self::DEFAULT_MENU_ID );

		if ( $default_menu ) {
			return self::DEFAULT_MENU_ID;
		}

		$first_menu = reset( $menus );
		return isset( $first_menu['id'] ) ? $first_menu['id'] : self::DEFAULT_MENU_ID;
	}

	private static function get_menu_by_id( $menus, $menu_id ) {
		$menu_id = sanitize_key( $menu_id );

		foreach ( (array) $menus as $menu ) {
			if ( isset( $menu['id'] ) && $menu_id === $menu['id'] ) {
				return $menu;
			}
		}

		return null;
	}

	private static function get_menu_for_shortcode( $identifier ) {
		$menus      = self::get_menus();
		$identifier = trim( (string) $identifier );

		if ( '' === $identifier ) {
			return self::get_menu_by_id( $menus, self::DEFAULT_MENU_ID ) ?: reset( $menus );
		}

		$key  = sanitize_key( $identifier );
		$slug = sanitize_title( $identifier );

		foreach ( $menus as $menu ) {
			$menu_name = isset( $menu['name'] ) ? $menu['name'] : '';

			if ( $key === $menu['id'] || $slug === sanitize_title( $menu_name ) ) {
				return $menu;
			}
		}

		return null;
	}

	private static function menu_shortcode( $menu_id ) {
		return '[' . self::SHORTCODE . ' menu="' . sanitize_key( $menu_id ) . '"]';
	}

	private static function generate_menu_id( $name, $seed, $seen_ids = array() ) {
		$base = sanitize_key( sanitize_title( $name ) );

		if ( '' === $base ) {
			$base = 'menu';
		}

		$id     = $base;
		$suffix = 2;

		while ( isset( $seen_ids[ $id ] ) ) {
			$id = $base . '_' . $suffix;
			$suffix++;
		}

		if ( '' === $id ) {
			$id = 'menu_' . substr( md5( wp_rand() . ':' . microtime( true ) . ':' . (string) $seed ), 0, 8 );
		}

		return $id;
	}

	private static function get_items() {
		$menus = self::get_menus();
		$menu  = reset( $menus );
		$items = isset( $menu['items'] ) ? $menu['items'] : array();

		return is_array( $items ) ? $items : array();
	}

	private static function get_settings() {
		$menus    = self::get_menus();
		$menu     = reset( $menus );
		$settings = isset( $menu['settings'] ) ? $menu['settings'] : array();

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, self::default_settings() );
		$settings['theme'] = self::sanitize_theme( $settings['theme'] );
		$settings['tile_palette'] = self::sanitize_palette( $settings['tile_palette'] );
		$settings['hero_color']   = sanitize_hex_color( (string) $settings['hero_color'] ) ? sanitize_hex_color( (string) $settings['hero_color'] ) : self::DEFAULT_HERO;
		foreach ( array( 'show_search', 'show_greeting', 'show_art' ) as $flag ) {
			$settings[ $flag ] = ! empty( $settings[ $flag ] ) ? '1' : '0';
		}
		$settings['allowed_roles'] = is_array( $settings['allowed_roles'] ) ? array_map( 'sanitize_key', $settings['allowed_roles'] ) : array( 'administrator' );
		$settings['show_title'] = ! empty( $settings['show_title'] ) ? '1' : '0';
		$settings['single_open_siblings'] = ! empty( $settings['single_open_siblings'] ) ? '1' : '0';
		$settings['restrict_menu_roles'] = ! empty( $settings['restrict_menu_roles'] ) ? '1' : '0';
		$settings['primary_color'] = self::sanitize_color( $settings['primary_color'], '#155e75' );
		$settings['container_bg'] = self::sanitize_color( $settings['container_bg'], '#eef6f5' );
		$settings['item_bg'] = self::sanitize_color( $settings['item_bg'], '#ffffff' );
		$settings['text_color'] = self::sanitize_color( $settings['text_color'], '#183039' );
		$settings['max_width'] = min( 1600, max( 320, absint( $settings['max_width'] ) ) );
		$settings['radius'] = min( 32, max( 0, absint( $settings['radius'] ) ) );
		$settings['spacing'] = in_array( $settings['spacing'], array( 'compact', 'comfortable' ), true ) ? $settings['spacing'] : 'comfortable';
		$settings['block_title'] = sanitize_text_field( $settings['block_title'] );
		$settings['empty_message'] = sanitize_text_field( $settings['empty_message'] );

		return $settings;
	}

	private static function default_settings() {
		return array_merge( self::default_appearance_settings(), self::default_behavior_settings() );
	}

	/**
	 * Rubriques qui reprennent un autre menu : le contenu du menu repris est ajouté dans la rubrique
	 * (ou à sa place, si « directement » est coché). Les éléments communs ne sont gérés qu'une fois.
	 * Les identifiants sont préfixés (rubrique--élément) ; un menu ne peut pas se reprendre lui-même.
	 */
	private static function expand_includes( $items, $visited, $depth = 0 ) {
		$out = array();
		foreach ( $items as $item ) {
			$include = 'folder' === $item['type'] && ! empty( $item['include_menu'] ) ? (string) $item['include_menu'] : '';
			if ( '' === $include || isset( $visited[ $include ] ) || $depth > 4 ) {
				$out[] = $item;
				continue;
			}
			$source = self::get_menu_by_id( self::get_menus(), $include );
			if ( ! $source || ! self::current_user_can_view_menu( $source['settings'] ) ) {
				$out[] = $item;
				continue;
			}
			$inline   = ! empty( $item['include_inline'] ) && '1' === (string) $item['include_inline'];
			$children = self::expand_includes( $source['items'], $visited + array( $include => true ), $depth + 1 );
			$prefix   = $item['id'] . '--';
			if ( ! $inline ) {
				$out[] = $item;
			}
			foreach ( $children as $child ) {
				$top                = '' === (string) $child['parent_id'];
				$child['id']        = $prefix . $child['id'];
				$child['parent_id'] = $top ? ( $inline ? (string) $item['parent_id'] : $item['id'] ) : $prefix . $child['parent_id'];
				if ( $top ) {
					// Rubrique propre d'abord, puis le menu repris ; « directement » : à la place de la rubrique.
					$child['order'] = $inline ? (float) $item['order'] + ( (float) $child['order'] ) / 1000 : 10000 + (float) $child['order'];
				}
				$out[] = $child;
			}
		}

		return $out;
	}

	/** Texte d'accueil nettoyé : scripts et styles retirés avec leur contenu, puis seulement la mise en forme permise. */
	public static function clean_intro( $text ) {
		return wp_kses( (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $text ), self::intro_allowed_html() );
	}

	/** Mise en forme permise dans le texte d'accueil : gras, italique, souligné, surligné, liens, listes, paragraphes. */
	public static function intro_allowed_html() {
		return array(
			'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(), 'mark' => array(),
			'br' => array(), 'p' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
			'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(),
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
		);
	}

	/** Palette automatique : 1 à 8 couleurs valides, sinon les couleurs LogiProf. */
	private static function sanitize_palette( $raw ) {
		$palette = is_array( $raw ) ? array_values( array_filter( array_map( 'sanitize_hex_color', array_map( 'strval', $raw ) ) ) ) : array();

		return $palette ? array_slice( $palette, 0, 8 ) : array_values( self::tile_presets() );
	}

	/** Présentations : tuiles (défaut), liste LogiOne, ou couleurs personnalisées. */
	private static function sanitize_theme( $theme ) {
		return in_array( $theme, array( 'logione', 'custom' ), true ) ? $theme : self::DEFAULT_THEME;
	}

	/** Couleurs proposées pour les tuiles (gamme LogiProf). */
	public static function tile_colors() {
		return array(
			'menthe'     => __( 'Vert clair', 'logiprof-espace-enseignants' ),
			'vert'       => __( 'Vert', 'logiprof-espace-enseignants' ),
			'anthracite' => __( 'Anthracite', 'logiprof-espace-enseignants' ),
			'jaune'      => __( 'Jaune', 'logiprof-espace-enseignants' ),
			'blanc'      => __( 'Blanc', 'logiprof-espace-enseignants' ),
		);
	}

	private static function default_appearance_settings() {
		return array(
			'theme'         => self::DEFAULT_THEME,
			'tile_text'     => '',
			'tile_color'    => 'menthe',
			'tile_icon'     => 0,
			'intro_text'    => '',
			'show_search'   => '1',
			'show_greeting' => '1',
			'show_art'      => '1',
			'hero_color'    => self::DEFAULT_HERO,
			'tile_palette'  => array_values( self::tile_presets() ),
			'block_title'   => 'Ressources',
			'show_title'    => '1',
			'primary_color' => '#155e75',
			'container_bg'  => '#eef6f5',
			'item_bg'       => '#ffffff',
			'text_color'    => '#183039',
			'max_width'     => 980,
			'radius'        => 14,
			'spacing'       => 'comfortable',
		);
	}

	private static function default_behavior_settings() {
		return array(
			'empty_message'        => 'Contenu bientôt disponible.',
			'single_open_siblings' => '0',
			'restrict_menu_roles'  => '0',
			'allowed_roles'        => array( 'administrator' ),
		);
	}

	private static function default_items() {
		return array(
			array(
				'id'               => 'programme_formation',
				'type'             => 'folder',
				'title'            => 'Programme de formation',
				'description'      => 'Choisissez le niveau, puis le programme de votre classe.',
				'icon'             => 'graduation',
				'parent_id'        => '',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => '',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 1,
			),
			array(
				'id'               => 'cfc',
				'type'             => 'folder',
				'title'            => 'CFC',
				'description'      => '',
				'icon'             => 'book',
				'parent_id'        => 'programme_formation',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => '',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 1,
			),
			array(
				'id'               => 'cfc_1_2p',
				'type'             => 'link',
				'title'            => '1ère année - 2P',
				'description'      => '',
				'icon'             => 'document',
				'parent_id'        => 'cfc',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 1,
			),
			array(
				'id'               => 'cfc_1_3p',
				'type'             => 'link',
				'title'            => '1ère année - 3P',
				'description'      => '',
				'icon'             => 'document',
				'parent_id'        => 'cfc',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 2,
			),
			array(
				'id'               => 'cfc_1_complet',
				'type'             => 'link',
				'title'            => '1ère année - Complet',
				'description'      => '',
				'icon'             => 'document',
				'parent_id'        => 'cfc',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 3,
			),
			array(
				'id'               => 'cfc_2',
				'type'             => 'link',
				'title'            => '2ème année',
				'description'      => '',
				'icon'             => 'document',
				'parent_id'        => 'cfc',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 4,
			),
			array(
				'id'               => 'cfc_3',
				'type'             => 'link',
				'title'            => '3ème année',
				'description'      => '',
				'icon'             => 'document',
				'parent_id'        => 'cfc',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 5,
			),
			array(
				'id'               => 'art32_1',
				'type'             => 'link',
				'title'            => 'Art32 - 1ère année',
				'description'      => '',
				'icon'             => 'document',
				'parent_id'        => 'cfc',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 6,
			),
			array(
				'id'               => 'art32_2',
				'type'             => 'link',
				'title'            => 'Art32 - 2ème année',
				'description'      => '',
				'icon'             => 'document',
				'parent_id'        => 'cfc',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 7,
			),
			array(
				'id'               => 'afp',
				'type'             => 'folder',
				'title'            => 'AFP',
				'description'      => '',
				'icon'             => 'layers',
				'parent_id'        => 'programme_formation',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => '',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 2,
			),
			array(
				'id'               => 'progression_modules',
				'type'             => 'link',
				'title'            => 'Visualiser la progression dans les modules',
				'description'      => 'Choisissez une classe et un cours, puis contrôlez la progression élève par élève. Le tableau met en avant les validations, les leçons en cours, les reprises et les élèves qui demandent une attention rapide.',
				'icon'             => 'chart',
				'parent_id'        => '',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 2,
			),
			array(
				'id'               => 'activite_classe',
				'type'             => 'link',
				'title'            => 'Vérifier l’activité de la classe',
				'description'      => 'Consultez l’activité des élèves sur la plateforme.',
				'icon'             => 'activity',
				'parent_id'        => '',
				'hidden'           => '0',
				'open'             => '0',
				'destination_type' => 'page',
				'page_id'          => 0,
				'url'              => '',
				'order'            => 3,
			),
		);
	}

	private static function icon_library() {
		return array(
			'graduation' => array(
				'label' => __( 'Formation', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-welcome-learn-more',
			),
			'book'       => array(
				'label' => __( 'Livre', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-book-alt',
			),
			'layers'     => array(
				'label' => __( 'Niveaux', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-networking',
			),
			'document'   => array(
				'label' => __( 'Document', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-media-document',
			),
			'chart'      => array(
				'label' => __( 'Progression', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-chart-bar',
			),
			'activity'   => array(
				'label' => __( 'Activité', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-visibility',
			),
			'users'      => array(
				'label' => __( 'Classe', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-groups',
			),
			'tools'      => array(
				'label' => __( 'Outils', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-admin-tools',
			),
			'calendar'   => array(
				'label' => __( 'Calendrier', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-calendar-alt',
			),
			'check'      => array(
				'label' => __( 'Validation', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-yes-alt',
			),
			'link'       => array(
				'label' => __( 'Lien', 'logiprof-espace-enseignants' ),
				'class' => 'dashicons-admin-links',
			),
		);
	}

	private static function get_pages_for_admin() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$formatted = array();

		foreach ( $pages as $page_id ) {
			$title = get_the_title( $page_id );

			$formatted[] = array(
				'id'     => (int) $page_id,
				'title'  => $title ? $title : __( '(Sans titre)', 'logiprof-espace-enseignants' ),
				'status' => get_post_status( $page_id ),
				'url'    => get_permalink( $page_id ),
			);
		}

		return $formatted;
	}

	private static function get_roles() {
		$wp_roles = wp_roles();
		$roles    = array();

		foreach ( $wp_roles->roles as $slug => $role ) {
			$roles[ sanitize_key( $slug ) ] = translate_user_role( $role['name'] );
		}

		return $roles;
	}

	private static function admin_strings() {
		return array(
			'addFolder'          => __( 'Ajouter une rubrique', 'logiprof-espace-enseignants' ),
			'addLink'            => __( 'Ajouter un lien', 'logiprof-espace-enseignants' ),
			'edit'               => __( 'Modifier', 'logiprof-espace-enseignants' ),
			'delete'             => __( 'Supprimer', 'logiprof-espace-enseignants' ),
			'hide'               => __( 'Masquer', 'logiprof-espace-enseignants' ),
			'show'               => __( 'Réafficher', 'logiprof-espace-enseignants' ),
			'moveUp'             => __( 'Monter', 'logiprof-espace-enseignants' ),
			'moveDown'           => __( 'Descendre', 'logiprof-espace-enseignants' ),
			'folder'             => __( 'Rubrique', 'logiprof-espace-enseignants' ),
			'link'               => __( 'Lien', 'logiprof-espace-enseignants' ),
			'hidden'             => __( 'Masqué', 'logiprof-espace-enseignants' ),
			'root'               => __( 'Aucun - premier niveau', 'logiprof-espace-enseignants' ),
			'emptyTitle'         => __( 'Le titre est obligatoire.', 'logiprof-espace-enseignants' ),
			'chooseRole'         => __( 'Choisissez au moins un rôle, ou désactivez la restriction de cet élément.', 'logiprof-espace-enseignants' ),
			'invalidParent'      => __( 'Ce parent n’est pas autorisé pour cet élément.', 'logiprof-espace-enseignants' ),
			'typeHasChildren'    => __( 'Cette rubrique contient déjà des éléments. Déplacez ou supprimez ses descendants avant de la transformer en lien.', 'logiprof-espace-enseignants' ),
			'deleteWithChildren' => __( 'Cette rubrique contient des éléments. Ses descendants seront aussi supprimés. Continuer ?', 'logiprof-espace-enseignants' ),
			'deleteSimple'       => __( 'Supprimer cet élément ?', 'logiprof-espace-enseignants' ),
			'unavailable'        => __( 'Indisponible', 'logiprof-espace-enseignants' ),
			'newFolderTitle'     => __( 'Nouvelle rubrique', 'logiprof-espace-enseignants' ),
			'newLinkTitle'       => __( 'Nouveau lien', 'logiprof-espace-enseignants' ),
			'newMenuName'        => __( 'Nouvelle tuile', 'logiprof-espace-enseignants' ),
			'menuNamePrompt'     => __( 'Nom de la tuile', 'logiprof-espace-enseignants' ),
			'duplicateMenuName'  => __( 'Copie de %s', 'logiprof-espace-enseignants' ),
			'deleteMenu'         => __( 'Supprimer cette tuile ?', 'logiprof-espace-enseignants' ),
			'lastMenu'           => __( 'Il faut conserver au moins une tuile.', 'logiprof-espace-enseignants' ),
			'directShortcodePending' => __( 'Disponible après avoir appliqué l’élément.', 'logiprof-espace-enseignants' ),
			'unsaved'            => __( 'Des modifications ne sont pas enregistrées. Quitter cette page ?', 'logiprof-espace-enseignants' ),
			'noPage'             => __( 'Aucune page sélectionnée', 'logiprof-espace-enseignants' ),
			'opensNewTab'        => __( 'ouvre dans un nouvel onglet', 'logiprof-espace-enseignants' ),
		);
	}

	private static function set_admin_notice( $type, $messages ) {
		set_transient(
			self::NOTICE_TRANSIENT_PREFIX . get_current_user_id(),
			array(
				'type'     => in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'info',
				'messages' => array_values( array_filter( array_map( 'sanitize_text_field', (array) $messages ) ) ),
			),
			60
		);
	}

	private static function consume_admin_notice() {
		$key    = self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );

		return is_array( $notice ) ? $notice : array();
	}
}

LogiPilot_Tiles::init();
