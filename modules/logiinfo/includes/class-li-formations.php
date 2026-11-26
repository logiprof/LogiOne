<?php
/** LogiInfo – cours Sensei Word, Excel et PowerPoint : création, mise à jour, fichiers, image de mise en avant et progression des étapes. */
defined( 'ABSPATH' ) || exit;

define( 'LIF_VERSION', LI_VERSION );
define( 'LIF_CONTENT_VERSION', 4 ); // version du contenu des leçons livré avec l'extension
define( 'LIF_DIR', LI_DIR );

final class LI_Formations {

	const COURSES = array( 'word', 'excel', 'powerpoint', 'presentation' );
	const EXT     = array( 'png', 'jpg', 'jpeg', 'gif', 'svg', 'pdf', 'zip', 'docx', 'xlsx', 'pptx', 'mp4', 'woff2', 'txt' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_lpfi_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_lpfi_files', array( __CLASS__, 'handle_files' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_update_content' ) );
		add_action( 'admin_post_lpfi_update', array( __CLASS__, 'handle_update' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
	}

	public static function on_activate() {
		$done = array();
		if ( ! self::sensei_ready() || ! current_user_can( LI_ADMIN_CAP ) ) { return; }
		foreach ( self::COURSES as $k ) {
			if ( self::course_id( $k ) ) { continue; }
			try {
				$r = self::import( $k );
				$done[ $k ] = is_wp_error( $r ) ? $r->get_error_message() : $r;
			} catch ( Throwable $e ) {
				$done[ $k ] = $e->getMessage();
			}
		}
		update_option( 'lpfi_activation_report', $done, false );
	}

	/** Les leçons Word se terminent par [fw_remise] : sans l'extension de remise, le code court ne s'affiche pas en clair. */
	public static function fallback_shortcode() {
		if ( ! shortcode_exists( 'fw_remise' ) ) { add_shortcode( 'fw_remise', '__return_empty_string' ); }
	}

	public static function links( $l ) {
		array_unshift( $l, '<a href="' . esc_url( admin_url( 'admin.php?page=lpfi' ) ) . '">Ouvrir</a>' );
		return $l;
	}

	public static function menu() {
		add_submenu_page( 'fw-remises', 'Progression des exercices', 'Progression des élèves', self::progress_cap(), 'lpfi-progress', array( __CLASS__, 'progress_page' ) );
		add_submenu_page( 'fw-remises', 'Cours informatiques', 'Cours et fichiers', LI_ADMIN_CAP, 'lpfi', array( __CLASS__, 'page' ) );
	}

	private static function progress_cap() {
		return current_user_can( LI_ADMIN_CAP ) ? LI_ADMIN_CAP : 'manage_sensei_grades';
	}

	/* ------------------------------------------------------------------ progression des étapes (côté apprenti) */
	public static function front_assets() {
		if ( ! is_singular( array( 'lesson', 'course' ) ) ) { return; }
		$post = get_post();
		if ( ! $post ) { return; }
		// agrandissement des images par-dessus la page (toutes les leçons et tous les cours)
		if ( false !== strpos( $post->post_content, 'wp-block-image' ) ) {
			wp_enqueue_style( 'li-zoom', LIR_URL . 'assets/lightbox.css', array(), LIF_VERSION );
			wp_enqueue_script( 'li-zoom', LIR_URL . 'assets/lightbox.js', array(), LIF_VERSION, true );
		}
		if ( ! is_singular( 'lesson' ) || false === strpos( $post->post_content, 'fw-progress' ) ) { return; }
		wp_enqueue_style( 'lpex', LIR_URL . 'assets/lpex.css', array(), LIF_VERSION );
		wp_enqueue_script( 'lpex', LIR_URL . 'assets/lpex.js', array(), LIF_VERSION, true );
		$uid = get_current_user_id();
		wp_localize_script( 'lpex', 'LPEX', array(
			'logged' => (bool) $uid,
			'state'  => $uid ? (object) array_map( function ( $v ) { return $v['steps']; }, LIR_Core::get_progress( $uid ) ) : new stdClass(),
			'rest'   => esc_url_raw( rest_url( 'lpex/v1/steps' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
		) );
	}

	public static function rest() {
		register_rest_route( 'lpex/v1', '/steps', array(
			'methods'             => 'POST',
			'permission_callback' => function () { return is_user_logged_in(); },
			'callback'            => array( __CLASS__, 'rest_save' ),
			'args'                => array(
				'key'   => array( 'required' => true, 'type' => 'string' ),
				'steps' => array( 'required' => true, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		) );
	}

	public static function rest_save( WP_REST_Request $r ) {
		$key = (string) $r['key'];
		if ( ! preg_match( '/^(word|excel|powerpoint|presentation)-([1-9]|1[0-5])$/', $key ) ) { return new WP_Error( 'lpex_key', 'Exercice inconnu.', array( 'status' => 400 ) ); }
		$steps = array_values( array_unique( array_filter( array_map( 'intval', (array) $r['steps'] ), function ( $n ) { return $n >= 1 && $n <= 40; } ) ) );
		sort( $steps );
		LIR_Core::save_progress( get_current_user_id(), $key, $steps );
		return array( 'ok' => true, 'steps' => $steps );
	}

	/* ------------------------------------------------------------------ progression (côté formateur) */
	public static function progress_page() {
		if ( ! current_user_can( self::progress_cap() ) ) { return; }
		$k = isset( $_GET['course'] ) && in_array( $_GET['course'], self::COURSES, true ) ? sanitize_key( $_GET['course'] ) : 'word'; // phpcs:ignore WordPress.Security.NonceVerification
		$d = self::data( $k );
		$nums = array();
		foreach ( $d['modules'] as $m ) { foreach ( $m['lessons'] as $l ) { $nums[ (int) $l['num'] ] = (int) $l['steps']; } }
		ksort( $nums );
		echo '<div class="wrap"><h1>Progression des exercices</h1><nav class="nav-tab-wrapper">';
		foreach ( self::COURSES as $c ) {
			echo '<a class="nav-tab' . ( $c === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=lpfi-progress&course=' . $c ) ) . '">' . esc_html( array( 'word' => 'Word 365', 'excel' => 'Excel 365', 'powerpoint' => 'PowerPoint 365', 'presentation' => 'Présentations' )[ $c ] ) . '</a>';
		}
		echo '</nav><p class="description">Étapes cochées « terminée » par chaque apprenti, exercice par exercice. Vert : exercice terminé ; jaune : en cours.</p>';
		echo '<style>.lpex-t td,.lpex-t th{text-align:center;padding:6px 4px}.lpex-t td:first-child,.lpex-t th:first-child{text-align:left;white-space:nowrap}.lpex-t .ok{background:#dcefe3;color:#0b4d22;font-weight:600}.lpex-t .go{background:#fff4c2;color:#5c4800}.lpex-t .no{color:#b0b6bc}</style>';
		$prog = LIR_Core::get_progress();
		$users = $prog ? get_users( array( 'include' => array_keys( $prog ), 'orderby' => 'display_name' ) ) : array();
		$rows = '';
		foreach ( $users as $u ) {
			$all = $prog[ $u->ID ];
			$cells = ''; $any = false; $last = 0;
			foreach ( $nums as $n => $tot ) {
				$key = $k . '-' . $n;
				$c   = isset( $all[ $key ] ) ? count( $all[ $key ]['steps'] ) : 0;
				if ( isset( $all[ $key ] ) ) { $any = true; $last = max( $last, (int) $all[ $key ]['ts'] ); }
				$cls = ! $c ? 'no' : ( $c >= $tot ? 'ok' : 'go' );
				$cells .= '<td class="' . $cls . '">' . ( $c ? (int) $c . '/' . (int) $tot : '–' ) . '</td>';
			}
			if ( ! $any ) { continue; }
			$rows .= '<tr><td>' . esc_html( $u->display_name ) . '</td>' . $cells . '<td>' . esc_html( $last ? wp_date( 'd.m.Y H:i', $last ) : '' ) . '</td></tr>';
		}
		echo '<table class="widefat striped lpex-t"><thead><tr><th>Apprenti</th>';
		foreach ( $nums as $n => $tot ) { echo '<th>L' . (int) $n . '</th>'; }
		echo '<th>Dernière activité</th></tr></thead><tbody>' . ( $rows ? $rows : '<tr><td colspan="' . ( count( $nums ) + 2 ) . '">Aucune étape cochée pour l\'instant.</td></tr>' ) . '</tbody></table></div>'; // phpcs:ignore
	}

	/* ------------------------------------------------------------------ mise à jour du contenu des leçons */
	/** Après une mise à jour de l'extension : nouveau contenu pour les leçons que vous n'avez pas modifiées. */
	public static function maybe_update_content() {
		if ( ! current_user_can( LI_ADMIN_CAP ) || (int) get_option( 'lpfi_content_version', 1 ) >= LIF_CONTENT_VERSION ) { return; }
		update_option( 'lpfi_content_version', LIF_CONTENT_VERSION, false );
		$rep = array();
		foreach ( self::COURSES as $k ) {
			if ( self::course_id( $k ) ) { $rep[ $k ] = self::update_content( $k, false ); }
		}
		update_option( 'lpfi_update_report', $rep, false );
	}

	public static function handle_update() {
		$k = self::req_course( 'lpfi_update' );
		if ( ! self::course_id( $k ) ) { self::back( false, 'Ce cours n\'a pas été créé par l\'extension.' ); }
		$r = self::update_content( $k, true );
		self::back( true, sprintf( 'Contenu mis à jour : %d leçons.', $r['updated'] ) );
	}

	/** Leçons du cours, indexées par titre. */
	private static function lessons_by_title( $course_id ) {
		$out = array();
		$ls  = get_posts( array( 'post_type' => 'lesson', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_lesson_course', 'meta_value' => $course_id ) ); // phpcs:ignore
		foreach ( $ls as $l ) { $out[ html_entity_decode( $l->post_title, ENT_QUOTES, 'UTF-8' ) ] = $l; }
		return $out;
	}

	/** Empreinte du texte d'une leçon, insensible à l'encodage (émojis en entités, etc.). */
	private static function fp( $html ) {
		return md5( preg_replace( '/[^A-Za-z0-9]/', '', html_entity_decode( preg_replace( '/\srel="[^"]*"/', '', (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}

	/** Leçon retouchée à la main ? (contenu différent de tout contenu livré par l'extension) */
	private static function is_edited( $p ) {
		$h = self::fp( $p->post_content );
		$saved = get_post_meta( $p->ID, '_lpfi_hash', true );
		if ( $saved ) { return $h !== $saved; }
		static $prev = null;
		if ( null === $prev ) { $prev = (array) json_decode( (string) @file_get_contents( LIF_DIR . '/data/previous.json' ), true ); }
		if ( in_array( $h, $prev, true ) ) { return false; }
		return strtotime( $p->post_modified ) - strtotime( $p->post_date ) > 600;
	}

	/** Image de mise en avant du cours (si le cours n'en a pas encore). */
	public static function set_cover( $k, $course_id ) {
		$d = self::data( $k );
		if ( ! $course_id || has_post_thumbnail( $course_id ) || empty( $d['cover'] ) ) { return; }
		$src = LIF_DIR . '/' . $d['cover'];
		if ( ! file_exists( $src ) ) { return; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( basename( $src ) );
		copy( $src, $tmp );
		$file = array( 'name' => 'logiinfo-' . basename( $src ), 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, $course_id, $d['title'] );
		if ( is_wp_error( $id ) ) { @unlink( $tmp ); return; }
		update_post_meta( $id, '_wp_attachment_image_alt', $d['title'] );
		set_post_thumbnail( $course_id, $id );
	}

	public static function update_content( $k, $force ) {
		$d   = self::data( $k );
		$ls  = self::lessons_by_title( self::course_id( $k ) );
		$res = array( 'updated' => 0, 'skipped' => array() );
		kses_remove_filters();
		foreach ( $d['modules'] as $m ) {
			foreach ( $m['lessons'] as $l ) {
				if ( ! isset( $ls[ $l['title'] ] ) ) { continue; }
				$p = $ls[ $l['title'] ];
				$edited = self::is_edited( $p );
				if ( $edited && ! $force ) { $res['skipped'][] = $l['title']; continue; }
				wp_update_post( wp_slash( array( 'ID' => $p->ID, 'post_content' => $l['content'] ) ) );
				clean_post_cache( $p->ID );
				update_post_meta( $p->ID, '_lpfi_hash', self::fp( get_post_field( 'post_content', $p->ID, 'raw' ) ) );
				$res['updated']++;
			}
		}
		kses_init_filters();
		self::build_zips( $k );
		self::set_cover( $k, self::course_id( $k ) );
		return $res;
	}

	/** ZIP des exercices à plusieurs fichiers : uniquement les fichiers de départ (plus de PDF en double). */
	public static function build_zips( $k ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return; }
		$d  = self::data( $k );
		$up = wp_upload_dir();
		$base = trailingslashit( $up['basedir'] ) . $d['folder'];
		foreach ( $d['modules'] as $m ) {
			foreach ( $m['lessons'] as $l ) {
				if ( empty( $l['zip'] ) ) { continue; }
				$ok = true;
				foreach ( $l['zip'] as $f ) { if ( ! file_exists( $base . '/' . $f[0] ) ) { $ok = false; } }
				if ( ! $ok ) { continue; }
				$target = sprintf( '%s/L%02d/L%02d_fichiers_exercice.zip', $base, $l['num'], $l['num'] );
				$z = new ZipArchive();
				if ( true !== $z->open( $target, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { continue; }
				foreach ( $l['zip'] as $f ) { $z->addFile( $base . '/' . $f[0], $f[1] ); }
				$z->close();
			}
		}
	}

	/** Données d'un cours (page du cours, modules, leçons) exportées par la chaîne de fabrication. */
	public static function data( $key ) {
		static $cache = array();
		if ( ! isset( $cache[ $key ] ) ) {
			$cache[ $key ] = json_decode( (string) file_get_contents( LIF_DIR . '/data/' . $key . '.json' ), true );
		}
		return $cache[ $key ];
	}

	public static function sensei_ready() {
		return post_type_exists( 'course' ) && post_type_exists( 'lesson' ) && taxonomy_exists( 'module' );
	}

	/** Cours déjà créé par l'extension (et toujours présent) ou 0. */
	public static function course_id( $key ) {
		$id = (int) get_option( 'lpfi_course_' . $key );
		$p  = $id ? get_post( $id ) : null;
		return ( $p && 'course' === $p->post_type && 'trash' !== $p->post_status ) ? $id : 0;
	}

	public static function files_status( $folder, $expected = 15 ) {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . $folder;
		$n   = is_dir( $dir . '/captures' ) ? count( (array) glob( $dir . '/captures/*.png' ) ) : 0;
		$l   = 0;
		for ( $i = 1; $i <= $expected; $i++ ) { if ( file_exists( sprintf( '%s/L%02d/L%02d_theorie.pdf', $dir, $i, $i ) ) ) { $l++; } }
		return array( 'dir' => $dir, 'url' => trailingslashit( $up['baseurl'] ) . $folder, 'captures' => $n, 'lecons' => $l, 'ok' => $n > 0 && $expected === $l );
	}

	/* ------------------------------------------------------------------ page d'administration */
	public static function page() {
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { return; }
		echo '<div class="wrap lpfi"><h1>LogiInfo – Cours Word, Excel, PowerPoint et guide des présentations</h1>';
		self::notice();
		$up = get_option( 'lpfi_update_report' );
		if ( $up ) {
			delete_option( 'lpfi_update_report' );
			foreach ( $up as $k => $r ) {
				echo '<div class="notice notice-success"><p><strong>' . esc_html( self::data( $k )['title'] ) . '</strong> : ' . (int) $r['updated'] . ' leçons mises à jour (nouvelle présentation des exercices).'
					. ( $r['skipped'] ? ' Non modifiées car vous les aviez retouchées : ' . esc_html( implode( ', ', $r['skipped'] ) ) . '. Bouton « Remplacer le contenu des leçons » pour les mettre à jour quand même.' : '' ) . '</p></div>';
			}
		}
		echo '<style>.lpfi-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:18px;margin-top:14px}.lpfi-card{background:#fff;border:1px solid #d9ddd6;border-left:6px solid #067030;border-radius:6px;padding:16px 20px}'
			. '.lpfi-card h2{margin:0 0 8px;color:#313a45}.lpfi-st{margin:4px 0}.lpfi-ok{color:#067030;font-weight:600}.lpfi-ko{color:#b42318;font-weight:600}.lpfi .button-primary{background:#067030;border-color:#067030}.lpfi .button-primary:hover{background:#0b4d22;border-color:#0b4d22}'
			. '.lpfi-step{font-weight:600;margin:14px 0 4px;color:#313a45}.lpfi-mods{margin:6px 0 0 18px;list-style:disc;color:#5b6672}</style>';
		if ( ! self::sensei_ready() ) {
			echo '<div class="notice notice-error"><p><strong>Sensei LMS n\'est pas actif.</strong> Activez Sensei LMS, puis revenez sur cette page.</p></div>';
		}
		$max = size_format( wp_max_upload_size() );
		echo '<div class="lpfi-cards">';
		foreach ( self::COURSES as $k ) {
			$d  = self::data( $k );
			$nl = 0; foreach ( $d['modules'] as $mm ) { $nl += count( $mm['lessons'] ); }
			$fs = self::files_status( $d['folder'], $nl );
			$id = self::course_id( $k );
			$nl = 0;
			foreach ( $d['modules'] as $m ) { $nl += count( $m['lessons'] ); }
			echo '<div class="lpfi-card"><h2>' . esc_html( $d['title'] ) . '</h2>';
			echo '<p class="lpfi-st">' . count( $d['modules'] ) . ' modules · ' . (int) $nl . ' leçons</p><ul class="lpfi-mods">';
			foreach ( $d['modules'] as $m ) { echo '<li>' . esc_html( $m['title'] ) . ' (' . count( $m['lessons'] ) . ')</li>'; }
			echo '</ul>';

			echo '<p class="lpfi-step">1. Les fichiers de la formation</p>';
			if ( $fs['ok'] ) {
				echo '<p class="lpfi-st lpfi-ok">✓ Installés dans uploads/' . esc_html( $d['folder'] ) . ' (' . (int) $fs['captures'] . ' captures, ' . $nl . ' leçons).</p>';
			} else {
				echo '<p class="lpfi-st lpfi-ko">✗ Dossier uploads/' . esc_html( $d['folder'] ) . ' incomplet (' . (int) $fs['captures'] . ' captures, ' . (int) $fs['lecons'] . '/' . $nl . ' leçons).</p>';
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
			wp_nonce_field( 'lpfi_files_' . $k );
			echo '<input type="hidden" name="action" value="lpfi_files"><input type="hidden" name="course" value="' . esc_attr( $k ) . '">';
			echo '<p><input type="file" name="lpfi_zip" accept=".zip" required> <button class="button">' . ( $fs['ok'] ? 'Remplacer les fichiers' : 'Installer les fichiers' ) . '</button></p>';
			echo '<p class="description">Choisissez <strong>' . esc_html( $d['folder'] ) . '.zip</strong> (ou le ZIP complet de la formation). Taille maximale acceptée par votre site : <strong>' . esc_html( $max ) . '</strong>. Si le fichier est plus gros, copiez le dossier ' . esc_html( $d['folder'] ) . ' par FTP dans wp-content/uploads/.</p></form>';

			echo '<p class="lpfi-step">2. Le cours dans Sensei</p>';
			if ( $id ) {
				echo '<p class="lpfi-st lpfi-ok">✓ Cours créé (' . esc_html( get_post_status( $id ) === 'publish' ? 'publié' : 'brouillon' ) . ').</p>';
				echo '<p><a class="button button-primary" href="' . esc_url( get_edit_post_link( $id, 'raw' ) ) . '">Modifier le cours</a> <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=lesson&course_id=' . $id ) ) . '">Voir ses leçons</a> <a class="button" href="' . esc_url( get_preview_post_link( $id ) ) . '" target="_blank">Aperçu</a></p>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:8px">';
				wp_nonce_field( 'lpfi_update_' . $k );
				echo '<input type="hidden" name="action" value="lpfi_update"><input type="hidden" name="course" value="' . esc_attr( $k ) . '">';
				echo '<button class="button">Remplacer le contenu des leçons</button> <span class="description">Remet le contenu livré avec l\'extension dans les ' . $nl . ' leçons (vos retouches dans ces leçons seraient perdues). Titres, modules et réglages ne changent pas.</span></form>';
				echo '<p class="description">Pour recréer le cours depuis zéro, mettez d\'abord l\'ancien cours à la corbeille.</p>';
			} else {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'lpfi_import_' . $k );
				echo '<input type="hidden" name="action" value="lpfi_import"><input type="hidden" name="course" value="' . esc_attr( $k ) . '">';
				echo '<p><button class="button button-primary"' . disabled( ! self::sensei_ready(), true, false ) . '>Créer le cours, ses modules et ses leçons</button></p>';
				echo '<p class="description">Tout est créé en <strong>brouillon</strong> : relisez, puis publiez le cours et ses leçons quand vous êtes prêt.</p></form>';
			}
			echo '</div>';
		}
		echo '</div><p style="margin-top:18px"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=lpfi-progress' ) ) . '">Voir la progression des apprentis</a></p></div>';
	}

	private static function notice() {
		$msg = isset( $_GET['lpfi_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['lpfi_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $msg ) { return; }
		$ok = isset( $_GET['lpfi_ok'] ) && '1' === $_GET['lpfi_ok']; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	private static function back( $ok, $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'lpfi', 'lpfi_ok' => $ok ? '1' : '0', 'lpfi_msg' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function req_course( $action ) {
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { wp_die( 'Accès refusé.' ); }
		$k = isset( $_POST['course'] ) ? sanitize_key( wp_unslash( $_POST['course'] ) ) : '';
		if ( ! in_array( $k, self::COURSES, true ) ) { wp_die( 'Cours inconnu.' ); }
		check_admin_referer( $action . '_' . $k );
		return $k;
	}

	/* ------------------------------------------------------------------ installation des fichiers */
	public static function handle_files() {
		$k = self::req_course( 'lpfi_files' );
		$d = self::data( $k );
		if ( empty( $_FILES['lpfi_zip']['tmp_name'] ) || ! empty( $_FILES['lpfi_zip']['error'] ) ) {
			self::back( false, 'Le fichier n\'a pas pu être téléversé (il dépasse peut-être la taille maximale du site). Utilisez le FTP.' );
		}
		$r = self::extract( $_FILES['lpfi_zip']['tmp_name'], $d['folder'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_wp_error( $r ) ) { self::back( false, $r->get_error_message() ); }
		self::back( true, sprintf( '%d fichiers installés dans uploads/%s.', $r, $d['folder'] ) );
	}

	/** Extrait le dossier « {folder}/ » d'un ZIP (seul, ou à l'intérieur du ZIP complet de la formation). */
	public static function extract( $zip_path, $folder ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'zip', 'L\'extension PHP ZipArchive manque sur le serveur : utilisez le FTP.' ); }
		$z = new ZipArchive();
		if ( true !== $z->open( $zip_path ) ) { return new WP_Error( 'zip', 'Ce fichier n\'est pas un ZIP valide.' ); }
		$up   = wp_upload_dir();
		$dest = trailingslashit( $up['basedir'] ) . $folder;
		$n    = 0;
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$name = str_replace( '\\', '/', (string) $z->getNameIndex( $i ) );
			$pos  = strpos( '/' . $name, '/' . $folder . '/' );
			if ( false === $pos || '/' === substr( $name, -1 ) ) { continue; }
			$rel = substr( $name, $pos + strlen( $folder ) + 1 );
			if ( '' === $rel || false !== strpos( $rel, '..' ) || 0 === strpos( $rel, '/' ) ) { continue; }
			$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, self::EXT, true ) && 'OFL.txt' !== basename( $rel ) ) { continue; }
			$target = $dest . '/' . $rel;
			wp_mkdir_p( dirname( $target ) );
			$data = $z->getFromIndex( $i );
			if ( false === $data || false === file_put_contents( $target, $data ) ) { continue; }
			$n++;
		}
		$z->close();
		if ( ! $n ) { return new WP_Error( 'zip', 'Aucun fichier du dossier ' . $folder . ' trouvé dans ce ZIP.' ); }
		return $n;
	}

	/* ------------------------------------------------------------------ création du cours */
	public static function handle_import() {
		$k = self::req_course( 'lpfi_import' );
		if ( self::course_id( $k ) ) { self::back( false, 'Ce cours existe déjà.' ); }
		$r = self::import( $k );
		if ( is_wp_error( $r ) ) { self::back( false, $r->get_error_message() ); }
		self::back( true, sprintf( 'Cours créé en brouillon : %d modules et %d leçons. Relisez-le, puis publiez-le.', $r['modules'], $r['lessons'] ) );
	}

	public static function import( $k ) {
		if ( ! self::sensei_ready() ) { return new WP_Error( 'sensei', 'Sensei LMS n\'est pas actif.' ); }
		$d = self::data( $k );
		if ( ! $d ) { return new WP_Error( 'data', 'Données du cours introuvables.' ); }
		@set_time_limit( 300 ); // phpcs:ignore

		// Le contenu contient une feuille de style et des boutons : on l'enregistre tel quel (comme un collage par un administrateur).
		kses_remove_filters();

		$course_id = wp_insert_post( wp_slash( array( 'post_type' => 'course', 'post_status' => 'draft', 'post_title' => $d['title'], 'post_excerpt' => $d['excerpt'], 'post_content' => '' ) ), true );
		if ( is_wp_error( $course_id ) ) { kses_init_filters(); return $course_id; }

		$ids = self::structure_sensei( $course_id, $d );
		if ( ! $ids ) { $ids = self::structure_manual( $course_id, $d ); }

		// Contenu des leçons.
		foreach ( $d['modules'] as $mi => $m ) {
			foreach ( $m['lessons'] as $li => $l ) {
				$lid = $ids['lessons'][ $mi ][ $li ];
				wp_update_post( wp_slash( array( 'ID' => $lid, 'post_title' => $l['title'], 'post_content' => $l['content'] ) ) );
				update_post_meta( $lid, '_lesson_length', 45 );
				update_post_meta( $lid, '_lesson_complexity', 'easy' );
				clean_post_cache( $lid );
				update_post_meta( $lid, '_lpfi_hash', self::fp( get_post_field( 'post_content', $lid, 'raw' ) ) );
			}
		}

		// Page du cours : présentation, bouton « Suivre le cours », progression et plan du cours (modules et leçons).
		wp_update_post( wp_slash( array( 'ID' => $course_id, 'post_content' => $d['description'] . self::course_blocks( $d, $ids ) ) ) );
		update_option( 'lpfi_course_' . $k, $course_id, false );
		update_option( 'lpfi_content_version', LIF_CONTENT_VERSION, false );
		kses_init_filters();
		self::build_zips( $k );
		self::set_cover( $k, $course_id );

		$nl = 0;
		foreach ( $ids['lessons'] as $ls ) { $nl += count( $ls ); }
		return array( 'course' => $course_id, 'modules' => count( $ids['modules'] ), 'lessons' => $nl );
	}

	/** Méthode officielle de Sensei (celle du bloc « Plan du cours »). Renvoie null si elle n'est pas disponible. */
	private static function structure_sensei( $course_id, $d ) {
		if ( ! class_exists( 'Sensei_Course_Structure' ) ) { return null; }
		$structure = array();
		foreach ( $d['modules'] as $m ) {
			$ls = array();
			foreach ( $m['lessons'] as $l ) { $ls[] = array( 'type' => 'lesson', 'title' => $l['title'] ); }
			$structure[] = array( 'type' => 'module', 'title' => $m['title'], 'description' => $m['description'], 'lessons' => $ls );
		}
		try {
			$cs = Sensei_Course_Structure::instance( $course_id );
			$r  = $cs->save( $structure );
			if ( is_wp_error( $r ) || false === $r ) { self::cleanup( $course_id ); return null; }
			$got = $cs->get( 'edit' );
		} catch ( Throwable $e ) {
			self::cleanup( $course_id );
			return null;
		}
		$ids = array( 'modules' => array(), 'lessons' => array() );
		$got = array_values( array_filter( (array) $got, function ( $i ) { return isset( $i['type'] ) && 'module' === $i['type']; } ) );
		foreach ( $d['modules'] as $mi => $m ) {
			if ( ! isset( $got[ $mi ]['id'], $got[ $mi ]['lessons'] ) || count( $got[ $mi ]['lessons'] ) !== count( $m['lessons'] ) ) { self::cleanup( $course_id ); return null; }
			$ids['modules'][ $mi ] = (int) $got[ $mi ]['id'];
			foreach ( $got[ $mi ]['lessons'] as $li => $l ) { $ids['lessons'][ $mi ][ $li ] = (int) $l['id']; }
		}
		return $ids;
	}

	/** Retire ce qu'une tentative partielle aurait créé, avant la méthode de secours. */
	private static function cleanup( $course_id ) {
		$lessons = get_posts( array( 'post_type' => 'lesson', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_lesson_course', 'meta_value' => $course_id ) ); // phpcs:ignore
		foreach ( $lessons as $l ) { wp_delete_post( $l, true ); }
		wp_set_object_terms( $course_id, array(), 'module' );
		delete_post_meta( $course_id, '_module_order' );
		delete_post_meta( $course_id, '_lesson_order' );
	}

	/** Méthode de secours : même modèle de données que Sensei (taxonomie « module », métadonnées d'ordre). */
	private static function structure_manual( $course_id, $d ) {
		$ids = array( 'modules' => array(), 'lessons' => array() );
		$all = array();
		foreach ( $d['modules'] as $mi => $m ) {
			$t = term_exists( $m['title'], 'module' );
			if ( ! $t ) { $t = wp_insert_term( $m['title'], 'module', array( 'description' => $m['description'] ) ); }
			$tid = is_array( $t ) ? (int) $t['term_id'] : (int) $t;
			$ids['modules'][ $mi ] = $tid;
			foreach ( $m['lessons'] as $li => $l ) {
				$lid = wp_insert_post( wp_slash( array( 'post_type' => 'lesson', 'post_status' => 'draft', 'post_title' => $l['title'], 'post_content' => '' ) ) );
				update_post_meta( $lid, '_lesson_course', $course_id );
				wp_set_object_terms( $lid, $tid, 'module' );
				update_post_meta( $lid, '_order_module_' . $tid, $li );
				$ids['lessons'][ $mi ][ $li ] = $lid;
				$all[] = $lid;
			}
		}
		wp_set_object_terms( $course_id, $ids['modules'], 'module' );
		update_post_meta( $course_id, '_module_order', array_map( 'strval', $ids['modules'] ) );
		update_post_meta( $course_id, '_lesson_order', implode( ',', $all ) );
		foreach ( $all as $i => $lid ) { update_post_meta( $lid, '_order_' . $course_id, $i ); }
		return $ids;
	}

	private static function course_blocks( $d, $ids ) {
		$h  = "\n\n<!-- wp:sensei-lms/button-take-course {\"className\":\"wp-block-sensei-lms-button-take-course\"} -->\n";
		$h .= '<div class="wp-block-sensei-lms-button-take-course is-style-default wp-block-sensei-button wp-block-button has-text-align-left"><button class="wp-block-button__link">Suivre le cours</button></div>' . "\n";
		$h .= "<!-- /wp:sensei-lms/button-take-course -->\n\n<!-- wp:sensei-lms/course-progress /-->\n\n<!-- wp:sensei-lms/course-outline -->\n";
		foreach ( $d['modules'] as $mi => $m ) {
			$h .= '<!-- wp:sensei-lms/course-outline-module ' . wp_json_encode( array( 'id' => $ids['modules'][ $mi ], 'title' => $m['title'], 'description' => $m['description'] ) ) . " -->\n";
			foreach ( $m['lessons'] as $li => $l ) {
				$h .= '<!-- wp:sensei-lms/course-outline-lesson ' . wp_json_encode( array( 'id' => $ids['lessons'][ $mi ][ $li ], 'title' => $l['title'] ) ) . " /-->\n";
			}
			$h .= "<!-- /wp:sensei-lms/course-outline-module -->\n";
		}
		return $h . "<!-- /wp:sensei-lms/course-outline -->\n";
	}
}
LI_Formations::init();
