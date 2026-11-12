<?php
/**
 * LogiProgress – interface enseignant (LogiOne 1.22).
 *
 * Choix du groupe (favoris propres à chaque enseignant) et des cours avec des boutons ronds et une recherche en direct,
 * tableau de progression par onglets de cours, détail par élève et onglet « Élèves inactifs ».
 * Lecture seule, sauf la réinitialisation de l'avancement d'un élève (reset.php, permission dédiée). Les groupes favoris sont enregistrés
 * dans les réglages personnels de l'enseignant (métadonnée utilisateur).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiProgress_UI {

	private static $ui_quiz_cache = array();

	/* ---------------------------------------------------------------------
	 * Groupes favoris (par enseignant)
	 * ------------------------------------------------------------------- */

	private static function favorite_meta_key() {
		return 'psgpt_favorite_groups';
	}

	private static function get_favorite_group_ids( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$ids = get_user_meta( $user_id, self::favorite_meta_key(), true );
		return array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ) );
	}

	/** Ajoute ou retire un groupe des favoris de l'enseignant connecté ; renvoie l'état final. */
	private static function toggle_favorite_group( $group_id ) {
		$group_id = absint( $group_id );
		$group    = $group_id ? get_post( $group_id ) : null;
		if ( ! $group || 'group' !== $group->post_type ) {
			return null;
		}
		$ids = self::get_favorite_group_ids();
		if ( in_array( $group_id, $ids, true ) ) {
			$ids = array_values( array_diff( $ids, array( $group_id ) ) );
			$fav = false;
		} else {
			$ids[] = $group_id;
			$fav   = true;
		}
		update_user_meta( get_current_user_id(), self::favorite_meta_key(), $ids );
		return array( 'fav' => $fav, 'count' => count( $ids ) );
	}

	/** Appel AJAX (étoile cliquée) : réponse JSON. */
	public static function ajax_toggle_favorite() {
		if ( ! self::current_user_can_access_plugin() || ! check_ajax_referer( 'psgpt_favorite', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Action non autorisée.', 'pickles-sensei-group-progress-tracker' ) ), 403 );
		}
		$state = self::toggle_favorite_group( isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0 );
		if ( null === $state ) {
			wp_send_json_error( array( 'message' => __( 'Groupe introuvable.', 'pickles-sensei-group-progress-tracker' ) ), 400 );
		}
		wp_send_json_success( $state );
	}

	/** Sans JavaScript : le lien de l'étoile change le favori puis revient à la page. */
	public static function post_toggle_favorite() {
		if ( ! self::current_user_can_access_plugin() ) {
			wp_die( esc_html__( 'Acces non autorise.', 'pickles-sensei-group-progress-tracker' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'psgpt_favorite', 'nonce' );
		self::toggle_favorite_group( isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0 );
		$back = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : '';
		wp_safe_redirect( $back ? $back : ( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	private static function favorite_toggle_url( $group_id ) {
		return add_query_arg(
			array(
				'action'   => 'psgpt_toggle_favorite',
				'group_id' => absint( $group_id ),
				'nonce'    => wp_create_nonce( 'psgpt_favorite' ),
				'back'     => rawurlencode( self::ui_current_url() ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Outils
	 * ------------------------------------------------------------------- */

	private static function ui_current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST ) ) . $uri;
	}

	private static function ui_keys( $atts ) {
		$fid = sanitize_key( isset( $atts['filter_id'] ) && '' !== $atts['filter_id'] ? $atts['filter_id'] : 'default' );
		return array(
			'group'  => 'psgpt_group_' . $fid,
			'course' => 'psgpt_course_' . $fid,
			'view'   => 'psgpt_view_' . $fid,
			'edit'   => 'psgpt_edit_' . $fid,
		);
	}

	/** Lien vers la même page avec d'autres paramètres (les autres paramètres, ex. lp_module, sont gardés). */
	private static function ui_url( $keys, $set, $remove = array() ) {
		$url = remove_query_arg( array_merge( array( $keys['view'], $keys['edit'] ), (array) $remove ), self::ui_current_url() );
		foreach ( $set as $k => $v ) {
			if ( is_array( $v ) ) {
				$url = remove_query_arg( $k, $url );
				$url = add_query_arg( $k, implode( ',', array_map( 'absint', $v ) ), $url );
			} else {
				$url = add_query_arg( $k, rawurlencode( (string) $v ), $url );
			}
		}
		return $url;
	}

	private static function ui_norm( $text ) {
		$text = remove_accents( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	private static function ui_icon( $name ) {
		$icons = array(
			'star'   => '<svg class="psgpt-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z"/></svg>',
			'search' => '<svg class="psgpt-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
			'pdf'    => '<svg class="psgpt-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/></svg>',
		);
		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
	}

	private static function ui_status_kind( $status ) {
		if ( self::is_complete_status( $status ) ) {
			return 'done';
		}
		if ( 'in-progress' === $status ) {
			return 'progress';
		}
		if ( 'ungraded' === $status ) {
			return 'wait';
		}
		if ( 'failed' === $status ) {
			return 'failed';
		}
		return 'empty';
	}

	private static function ui_quiz( $user_id, $lesson_id ) {
		$k = $user_id . ':' . $lesson_id;
		if ( ! isset( self::$ui_quiz_cache[ $k ] ) ) {
			self::$ui_quiz_cache[ $k ] = (string) self::get_lesson_quiz_score_percent( $user_id, $lesson_id );
		}
		return self::$ui_quiz_cache[ $k ];
	}

	/** Date courte d'une leçon (jj.mm) et texte complet pour l'infobulle. */
	private static function ui_lesson_dates( $detail ) {
		$date   = ! empty( $detail['first_completed_at'] ) ? $detail['first_completed_at'] : ( ! empty( $detail['updated_at'] ) ? $detail['updated_at'] : '' );
		$prefix = ! empty( $detail['first_completed_at'] ) ? __( 'Validée le %s', 'pickles-sensei-group-progress-tracker' ) : __( 'Mise à jour le %s', 'pickles-sensei-group-progress-tracker' );
		$short  = $date ? mysql2date( 'd.m', $date ) : '';
		$full   = $date ? sprintf( $prefix, self::format_datetime( $date ) ) : '';
		return array( $short, $full );
	}

	private static function ui_days_since( $mysql_date ) {
		$ts = $mysql_date ? strtotime( $mysql_date ) : 0;
		if ( ! $ts ) {
			return null;
		}
		return max( 0, (int) floor( ( current_time( 'timestamp' ) - $ts ) / DAY_IN_SECONDS ) );
	}

	private static function ui_days_label( $days ) {
		if ( null === $days ) {
			return __( 'Aucune', 'pickles-sensei-group-progress-tracker' );
		}
		if ( 0 === $days ) {
			return __( 'Aujourd’hui', 'pickles-sensei-group-progress-tracker' );
		}
		if ( 1 === $days ) {
			return __( 'Hier', 'pickles-sensei-group-progress-tracker' );
		}
		/* translators: %s: nombre de jours */
		return sprintf( __( 'Il y a %s jours', 'pickles-sensei-group-progress-tracker' ), number_format_i18n( $days ) );
	}

	private static function ui_hidden_inputs( $exclude ) {
		foreach ( $_GET as $key => $value ) {
			if ( in_array( $key, $exclude, true ) || is_array( $value ) ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( sanitize_key( $key ) ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $value ) ) ) . '">';
		}
	}

	/* ---------------------------------------------------------------------
	 * Page principale
	 * ------------------------------------------------------------------- */

	private static function render_tracker( $atts ) {
		$keys         = self::ui_keys( $atts );
		$show_filters = 'yes' === strtolower( (string) $atts['show_filters'] );

		$group_id   = absint( $atts['group_id'] );
		$course_ids = self::parse_course_ids( isset( $atts['course_ids'] ) ? $atts['course_ids'] : '' );
		if ( empty( $course_ids ) && ! empty( $atts['course_id'] ) ) {
			$course_ids = array( absint( $atts['course_id'] ) );
		}
		$view = 'progress';
		$edit = false;
		if ( $show_filters ) {
			$group_id   = isset( $_GET[ $keys['group'] ] ) ? absint( $_GET[ $keys['group'] ] ) : $group_id;
			$course_ids = isset( $_GET[ $keys['course'] ] ) ? self::parse_course_ids( wp_unslash( $_GET[ $keys['course'] ] ) ) : $course_ids;
			$view       = ( isset( $_GET[ $keys['view'] ] ) && 'inactive' === $_GET[ $keys['view'] ] ) ? 'inactive' : 'progress';
			$edit       = ! empty( $_GET[ $keys['edit'] ] );
		}

		$classes = array( 'psgpt', 'psgpt-ui' );
		if ( ! empty( $atts['class'] ) ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}

		ob_start();
		echo '<section class="' . esc_attr( implode( ' ', $classes ) ) . '" data-psgpt-ui data-fav-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-fav-action="psgpt_toggle_favorite" data-fav-nonce="' . esc_attr( wp_create_nonce( 'psgpt_favorite' ) ) . '">';
		if ( $show_filters ) {
			self::render_ui_hero( $keys, $view );
		}
		if ( 'inactive' === $view ) {
			self::render_inactivity_view( $keys, $group_id, self::get_default_inactivity_days(), true );
		} elseif ( $show_filters && ( $edit || ! $group_id || empty( $course_ids ) ) ) {
			if ( ! $edit && ( $group_id || ! empty( $course_ids ) ) && ( ! $group_id || empty( $course_ids ) ) && isset( $_GET[ $keys['group'] ] ) ) {
				echo '<p class="psgpt-alert" role="alert">' . esc_html__( 'Choisis un groupe et au moins un cours pour afficher la progression.', 'pickles-sensei-group-progress-tracker' ) . '</p>';
			}
			self::render_selection_form( $keys, $group_id, $course_ids );
		} else {
			self::render_progress_results( $keys, $group_id, $course_ids, $atts, $show_filters );
		}
		echo '</section>';
		return ob_get_clean();
	}

	private static function render_ui_hero( $keys, $view ) {
		$progress_url = self::ui_url( $keys, array() );
		$inactive_url = self::ui_url( $keys, array( $keys['view'] => 'inactive' ) );
		?>
		<header class="psgpt-card psgpt-hero">
			<div class="psgpt-hero-text">
				<span class="psgpt-kicker">LogiProgress</span>
				<h2 class="psgpt-title"><?php echo 'inactive' === $view ? esc_html__( 'Élèves inactifs', 'pickles-sensei-group-progress-tracker' ) : esc_html__( 'Progression des groupes', 'pickles-sensei-group-progress-tracker' ); ?></h2>
				<p class="psgpt-lead">
					<?php
					if ( 'inactive' === $view ) {
						/* translators: %d: nombre de jours */
						echo esc_html( sprintf( __( 'Élèves qui ont un cours commencé mais pas terminé, sans activité depuis au moins %d jours.', 'pickles-sensei-group-progress-tracker' ), self::get_default_inactivity_days() ) );
					} else {
						esc_html_e( 'Suivez, leçon par leçon, où en sont vos apprentis dans les cours en ligne.', 'pickles-sensei-group-progress-tracker' );
					}
					?>
				</p>
			</div>
			<nav class="psgpt-views" aria-label="<?php esc_attr_e( 'Vues LogiProgress', 'pickles-sensei-group-progress-tracker' ); ?>">
				<a class="<?php echo 'progress' === $view ? 'is-active' : ''; ?>" <?php echo 'progress' === $view ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( $progress_url ); ?>"><?php esc_html_e( 'Progression', 'pickles-sensei-group-progress-tracker' ); ?></a>
				<a class="<?php echo 'inactive' === $view ? 'is-active' : ''; ?>" <?php echo 'inactive' === $view ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( $inactive_url ); ?>"><?php esc_html_e( 'Élèves inactifs', 'pickles-sensei-group-progress-tracker' ); ?></a>
			</nav>
		</header>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Choix du groupe : boutons ronds, favoris, recherche en direct
	 * ------------------------------------------------------------------- */

	private static function render_group_picker( $keys, $selected_group, $step_number = 0, $auto_submit = false ) {
		$groups   = self::get_groups();
		$favs     = self::get_favorite_group_ids();
		$fav_here = 0;
		foreach ( $groups as $g ) {
			$fav_here += in_array( (int) $g->ID, $favs, true ) ? 1 : 0;
		}
		$uid = 'psgpt-g-' . wp_unique_id();
		?>
		<section class="psgpt-card psgpt-step" data-psgpt-groups data-psgpt-autosubmit="<?php echo $auto_submit ? '1' : '0'; ?>">
			<div class="psgpt-step-head">
				<?php if ( $step_number ) : ?>
					<span class="psgpt-step-num" aria-hidden="true"><?php echo (int) $step_number; ?></span>
				<?php endif; ?>
				<h3 id="<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Groupe classe', 'pickles-sensei-group-progress-tracker' ); ?></h3>
				<span class="psgpt-step-hint"><?php esc_html_e( 'Un seul groupe à la fois', 'pickles-sensei-group-progress-tracker' ); ?></span>
				<div class="psgpt-seg" role="group" aria-label="<?php esc_attr_e( 'Groupes affichés', 'pickles-sensei-group-progress-tracker' ); ?>" data-psgpt-modes hidden>
					<button type="button" data-mode="fav" aria-pressed="false"><?php echo self::ui_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Mes favoris', 'pickles-sensei-group-progress-tracker' ); ?> · <span data-psgpt-favcount><?php echo (int) $fav_here; ?></span></button>
					<button type="button" data-mode="all" aria-pressed="true"><?php esc_html_e( 'Tous les groupes', 'pickles-sensei-group-progress-tracker' ); ?> · <?php echo (int) count( $groups ); ?></button>
				</div>
			</div>
			<?php if ( empty( $groups ) ) : ?>
				<p class="psgpt-muted"><?php esc_html_e( 'Aucun groupe Sensei publié.', 'pickles-sensei-group-progress-tracker' ); ?></p>
			<?php else : ?>
				<div class="psgpt-tools" data-psgpt-allonly>
					<label class="psgpt-search">
						<?php echo self::ui_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="psgpt-sr"><?php esc_html_e( 'Rechercher un groupe', 'pickles-sensei-group-progress-tracker' ); ?></span>
						<input type="search" data-psgpt-filter autocomplete="off" placeholder="<?php esc_attr_e( 'Tapez pour filtrer, ex. C2…', 'pickles-sensei-group-progress-tracker' ); ?>">
					</label>
					<span class="psgpt-muted psgpt-small"><?php esc_html_e( 'Cliquez sur l’étoile d’un groupe pour l’ajouter à vos favoris ou l’en retirer.', 'pickles-sensei-group-progress-tracker' ); ?></span>
				</div>
				<div class="psgpt-choices psgpt-choices-groups" role="radiogroup" aria-labelledby="<?php echo esc_attr( $uid ); ?>" data-psgpt-list data-mode="all">
					<?php foreach ( $groups as $group ) : ?>
						<?php
						$gid     = (int) $group->ID;
						$is_fav  = in_array( $gid, $favs, true );
						$checked = $gid === (int) $selected_group;
						$count   = count( self::get_group_student_ids( $gid ) );
						$label   = $is_fav
							/* translators: %s: groupe */
							? sprintf( __( 'Retirer %s des favoris', 'pickles-sensei-group-progress-tracker' ), $group->post_title )
							/* translators: %s: groupe */
							: sprintf( __( 'Ajouter %s aux favoris', 'pickles-sensei-group-progress-tracker' ), $group->post_title );
						?>
						<div class="psgpt-choice-wrap<?php echo $checked ? ' is-checked' : ''; ?>" data-psgpt-item data-fav="<?php echo $is_fav ? '1' : '0'; ?>" data-search="<?php echo esc_attr( self::ui_norm( $group->post_title ) ); ?>">
							<label class="psgpt-choice">
								<input type="radio" name="<?php echo esc_attr( $keys['group'] ); ?>" value="<?php echo esc_attr( $gid ); ?>" <?php checked( $checked ); ?>>
								<span class="psgpt-mark psgpt-mark-radio" aria-hidden="true"></span>
								<span class="psgpt-choice-text">
									<strong><?php echo esc_html( $group->post_title ); ?></strong>
									<?php /* translators: %d: nombre d'élèves */ ?>
									<small><?php echo esc_html( sprintf( _n( '%d élève', '%d élèves', $count, 'pickles-sensei-group-progress-tracker' ), $count ) ); ?></small>
								</span>
							</label>
							<a class="psgpt-star<?php echo $is_fav ? ' is-fav' : ''; ?>" role="button" href="<?php echo esc_url( self::favorite_toggle_url( $gid ) ); ?>" data-group="<?php echo esc_attr( $gid ); ?>" data-name="<?php echo esc_attr( $group->post_title ); ?>" aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>"><?php echo self::ui_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="psgpt-nomatch" data-psgpt-nomatch hidden><?php esc_html_e( 'Aucun groupe ne correspond à cette recherche.', 'pickles-sensei-group-progress-tracker' ); ?></p>
				<p class="psgpt-nomatch" data-psgpt-nofav hidden><?php esc_html_e( 'Vous n’avez pas encore de groupe favori : cliquez sur l’étoile d’un groupe dans « Tous les groupes ».', 'pickles-sensei-group-progress-tracker' ); ?></p>
				<button type="button" class="psgpt-linkbtn" data-psgpt-showall hidden>
					<?php /* translators: %d: nombre de groupes */ ?>
					<?php echo esc_html( sprintf( __( 'Un autre groupe ? Voir tous les groupes (%d) →', 'pickles-sensei-group-progress-tracker' ), count( $groups ) ) ); ?>
				</button>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_selection_form( $keys, $group_id, $course_ids ) {
		$courses  = self::get_courses();
		$selected = array_map( 'absint', (array) $course_ids );
		$uid      = 'psgpt-c-' . wp_unique_id();
		$group    = $group_id ? get_post( $group_id ) : null;
		?>
		<form class="psgpt-select" method="get" data-psgpt-select>
			<?php self::ui_hidden_inputs( array( $keys['group'], $keys['course'], $keys['view'], $keys['edit'] ) ); ?>
			<?php self::render_group_picker( $keys, $group_id, 1 ); ?>

			<section class="psgpt-card psgpt-step" data-psgpt-courses>
				<div class="psgpt-step-head">
					<span class="psgpt-step-num" aria-hidden="true">2</span>
					<h3 id="<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Cours à afficher', 'pickles-sensei-group-progress-tracker' ); ?></h3>
					<span class="psgpt-step-hint"><?php esc_html_e( 'Plusieurs choix possibles', 'pickles-sensei-group-progress-tracker' ); ?></span>
					<div class="psgpt-step-tools">
						<label class="psgpt-search">
							<?php echo self::ui_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="psgpt-sr"><?php esc_html_e( 'Rechercher un cours', 'pickles-sensei-group-progress-tracker' ); ?></span>
							<input type="search" data-psgpt-filter autocomplete="off" placeholder="<?php esc_attr_e( 'Tapez pour filtrer, ex. m106…', 'pickles-sensei-group-progress-tracker' ); ?>">
						</label>
						<button type="button" class="psgpt-btn" data-psgpt-all hidden><?php esc_html_e( 'Tout sélectionner', 'pickles-sensei-group-progress-tracker' ); ?></button>
						<button type="button" class="psgpt-btn" data-psgpt-none hidden><?php esc_html_e( 'Tout effacer', 'pickles-sensei-group-progress-tracker' ); ?></button>
					</div>
				</div>
				<?php if ( empty( $courses ) ) : ?>
					<p class="psgpt-muted"><?php esc_html_e( 'Aucun cours Sensei publié.', 'pickles-sensei-group-progress-tracker' ); ?></p>
				<?php else : ?>
					<div class="psgpt-choices psgpt-choices-courses" role="group" aria-labelledby="<?php echo esc_attr( $uid ); ?>" data-psgpt-list>
						<?php foreach ( $courses as $course ) : ?>
							<?php
							$cid     = (int) $course->ID;
							$checked = in_array( $cid, $selected, true );
							$n       = count( self::get_course_lessons( $cid ) );
							?>
							<div class="psgpt-choice-wrap<?php echo $checked ? ' is-checked' : ''; ?>" data-psgpt-item data-lessons="<?php echo esc_attr( $n ); ?>" data-search="<?php echo esc_attr( self::ui_norm( $course->post_title ) ); ?>">
								<label class="psgpt-choice">
									<input type="checkbox" name="<?php echo esc_attr( $keys['course'] ); ?>[]" value="<?php echo esc_attr( $cid ); ?>" <?php checked( $checked ); ?>>
									<span class="psgpt-mark psgpt-mark-radio" aria-hidden="true"></span>
									<span class="psgpt-choice-text">
										<strong><?php echo esc_html( $course->post_title ); ?></strong>
										<?php /* translators: %d: nombre de leçons */ ?>
										<small><?php echo esc_html( sprintf( _n( '%d leçon', '%d leçons', $n, 'pickles-sensei-group-progress-tracker' ), $n ) ); ?></small>
									</span>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="psgpt-nomatch" data-psgpt-nomatch hidden><?php esc_html_e( 'Aucun cours ne correspond à cette recherche.', 'pickles-sensei-group-progress-tracker' ); ?></p>
					<p class="psgpt-small psgpt-muted" data-psgpt-hiddensel hidden><span data-psgpt-hiddentext></span> <button type="button" class="psgpt-linkbtn" data-psgpt-clear><?php esc_html_e( 'Effacer la recherche', 'pickles-sensei-group-progress-tracker' ); ?></button></p>
				<?php endif; ?>
			</section>

			<div class="psgpt-bar">
				<div class="psgpt-bar-text" aria-live="polite">
					<strong data-psgpt-sumtitle><?php echo esc_html( $group ? $group->post_title : __( 'Aucun groupe choisi', 'pickles-sensei-group-progress-tracker' ) ); ?></strong>
					<small data-psgpt-sumtext><?php echo esc_html( sprintf( _n( '%d cours sélectionné', '%d cours sélectionnés', count( $selected ), 'pickles-sensei-group-progress-tracker' ), count( $selected ) ) ); ?></small>
				</div>
				<button type="submit" class="psgpt-primary" data-psgpt-submit><?php esc_html_e( 'Afficher la progression', 'pickles-sensei-group-progress-tracker' ); ?> →</button>
			</div>
		</form>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Résultats
	 * ------------------------------------------------------------------- */

	private static function render_progress_results( $keys, $group_id, $course_ids, $atts, $show_filters ) {
		$group       = get_post( $group_id );
		$student_ids = self::get_group_student_ids( $group_id );
		$students    = array();
		foreach ( $student_ids as $sid ) {
			$u = get_userdata( $sid );
			if ( $u ) {
				$students[ $sid ] = $u;
			}
		}
		uasort(
			$students,
			static function ( $a, $b ) {
				return strnatcasecmp( remove_accents( $a->display_name ), remove_accents( $b->display_name ) );
			}
		);

		$reports = array();
		foreach ( array_values( array_unique( array_map( 'absint', (array) $course_ids ) ) ) as $course_id ) {
			$course  = get_post( $course_id );
			$lessons = $course ? self::get_course_lessons( $course_id ) : array();
			if ( ! $course || empty( $lessons ) ) {
				continue;
			}
			$lesson_ids = wp_list_pluck( $lessons, 'ID' );
			$rows       = array();
			foreach ( $students as $sid => $u ) {
				$st   = self::get_user_lesson_status_details( $sid, $lesson_ids );
				$done = 0;
				$wait = 0;
				$last = '';
				foreach ( $lesson_ids as $lid ) {
					$s     = isset( $st[ $lid ]['status'] ) ? $st[ $lid ]['status'] : '';
					$done += self::is_complete_status( $s ) ? 1 : 0;
					$wait += 'ungraded' === $s ? 1 : 0;
					if ( ! empty( $st[ $lid ]['updated_at'] ) && $st[ $lid ]['updated_at'] > $last ) {
						$last = $st[ $lid ]['updated_at'];
					}
				}
				$rows[ $sid ] = array(
					'statuses' => $st,
					'done'     => $done,
					'wait'     => $wait,
					'pct'      => self::calculate_progress( $st, $lesson_ids ),
					'last'     => $last,
				);
			}
			$reports[] = array( 'course' => $course, 'lessons' => $lessons, 'rows' => $rows );
		}

		$back_url = $show_filters ? self::ui_url( $keys, array( $keys['edit'] => 1 ) ) : '';
		if ( $show_filters ) {
			self::render_quick_selector( $keys, $group_id, $course_ids );
		}
		?>
		<section class="psgpt-card psgpt-resultbar">
			<div class="psgpt-resultbar-text">
				<span class="psgpt-kicker"><?php esc_html_e( 'Groupe classe', 'pickles-sensei-group-progress-tracker' ); ?></span>
				<h3 class="psgpt-group-title"><?php echo esc_html( $group ? $group->post_title : __( 'Groupe selectionne', 'pickles-sensei-group-progress-tracker' ) ); ?></h3>
				<div class="psgpt-chips">
					<?php foreach ( $reports as $report ) : ?>
						<span class="psgpt-chip"><a class="psgpt-course-link" href="<?php echo esc_url( get_permalink( $report['course'] ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Ouvrir le cours dans un nouvel onglet', 'pickles-sensei-group-progress-tracker' ); ?>"><?php echo esc_html( $report['course']->post_title ); ?> <span class="psgpt-ext" aria-hidden="true">↗</span></a></span>
					<?php endforeach; ?>
					<?php if ( $back_url ) : ?>
						<a class="psgpt-edit-link" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Modifier la sélection', 'pickles-sensei-group-progress-tracker' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( ! empty( $reports ) && ! empty( $students ) && 'yes' === strtolower( isset( $atts['show_export'] ) ? (string) $atts['show_export'] : 'yes' ) && current_user_can( 'lp_progress_export' ) ) : ?>
				<a class="psgpt-export-button" href="<?php echo esc_url( self::get_export_url( $group_id, array_map( array( __CLASS__, 'get_report_course_id' ), $reports ), isset( $atts['capability'] ) ? $atts['capability'] : self::ACCESS_CAP ) ); ?>" target="_blank" rel="noopener"><?php echo self::ui_icon( 'pdf' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Exporter en PDF', 'pickles-sensei-group-progress-tracker' ); ?></a>
			<?php endif; ?>
		</section>
		<?php
		if ( empty( $students ) || empty( $reports ) ) {
			echo '<div class="psgpt-empty">' . esc_html( ! empty( $atts['empty_text'] ) ? $atts['empty_text'] : __( 'Aucune donnée à afficher pour le moment.', 'pickles-sensei-group-progress-tracker' ) ) . '</div>';
			return;
		}

		self::render_kpis( $students, $reports );
		self::render_course_tabs( $students, $reports );
		self::render_student_detail( $students, $reports, $group_id );
	}

	/**
	 * Barre compacte au-dessus des résultats : changer de classe et de modules sans quitter la progression (LogiOne 1.33.1).
	 */
	private static function render_quick_selector( $keys, $group_id, $course_ids ) {
		$groups   = self::get_groups();
		$courses  = self::get_courses();
		$favs     = self::get_favorite_group_ids();
		$selected = array_map( 'absint', (array) $course_ids );
		$fav_groups = array();
		$other      = array();
		foreach ( $groups as $g ) {
			if ( in_array( (int) $g->ID, $favs, true ) ) {
				$fav_groups[] = $g;
			} else {
				$other[] = $g;
			}
		}
		// Modules déjà choisis en tête de liste.
		usort(
			$courses,
			static function ( $a, $b ) use ( $selected ) {
				$sa = in_array( (int) $a->ID, $selected, true ) ? 0 : 1;
				$sb = in_array( (int) $b->ID, $selected, true ) ? 0 : 1;
				return $sa - $sb;
			}
		);
		$n   = count( array_intersect( $selected, array_map( 'intval', wp_list_pluck( $courses, 'ID' ) ) ) );
		$uid = 'psgpt-qb-' . wp_unique_id();
		?>
		<form class="psgpt-card psgpt-quickbar" method="get" data-psgpt-quick>
			<?php self::ui_hidden_inputs( array( $keys['group'], $keys['course'], $keys['view'], $keys['edit'] ) ); ?>
			<label class="psgpt-qb-field psgpt-qb-group">
				<span class="psgpt-qb-label"><?php esc_html_e( 'Classe', 'pickles-sensei-group-progress-tracker' ); ?></span>
				<select name="<?php echo esc_attr( $keys['group'] ); ?>" data-psgpt-qb-group>
					<?php if ( $fav_groups ) : ?>
						<optgroup label="<?php esc_attr_e( '★ Mes favoris', 'pickles-sensei-group-progress-tracker' ); ?>">
							<?php foreach ( $fav_groups as $g ) : ?>
								<option value="<?php echo esc_attr( $g->ID ); ?>" <?php selected( (int) $g->ID, (int) $group_id ); ?>><?php echo esc_html( $g->post_title ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Tous les groupes', 'pickles-sensei-group-progress-tracker' ); ?>">
					<?php endif; ?>
					<?php foreach ( $other as $g ) : ?>
						<option value="<?php echo esc_attr( $g->ID ); ?>" <?php selected( (int) $g->ID, (int) $group_id ); ?>><?php echo esc_html( $g->post_title ); ?></option>
					<?php endforeach; ?>
					<?php if ( $fav_groups ) : ?>
						</optgroup>
					<?php endif; ?>
				</select>
			</label>
			<div class="psgpt-qb-field psgpt-qb-courses">
				<span class="psgpt-qb-label" id="<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Modules', 'pickles-sensei-group-progress-tracker' ); ?></span>
				<details class="psgpt-qb-dd" data-psgpt-qb-dd>
					<summary data-psgpt-qb-summary aria-describedby="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( sprintf( _n( '%d module choisi', '%d modules choisis', $n, 'pickles-sensei-group-progress-tracker' ), $n ) ); ?></summary>
					<div class="psgpt-qb-panel">
						<input type="search" data-psgpt-qb-filter autocomplete="off" placeholder="<?php esc_attr_e( 'Filtrer, ex. m106…', 'pickles-sensei-group-progress-tracker' ); ?>" aria-label="<?php esc_attr_e( 'Filtrer les modules', 'pickles-sensei-group-progress-tracker' ); ?>">
						<div class="psgpt-qb-list" role="group" aria-labelledby="<?php echo esc_attr( $uid ); ?>">
							<?php foreach ( $courses as $course ) : ?>
								<label class="psgpt-qb-item" data-search="<?php echo esc_attr( self::ui_norm( $course->post_title ) ); ?>">
									<input type="checkbox" name="<?php echo esc_attr( $keys['course'] ); ?>[]" value="<?php echo esc_attr( $course->ID ); ?>" <?php checked( in_array( (int) $course->ID, $selected, true ) ); ?>>
									<span><?php echo esc_html( $course->post_title ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="psgpt-qb-actions">
							<button type="button" class="psgpt-linkbtn" data-psgpt-qb-none><?php esc_html_e( 'Tout décocher', 'pickles-sensei-group-progress-tracker' ); ?></button>
							<button type="button" class="psgpt-linkbtn" data-psgpt-qb-close><?php esc_html_e( 'Fermer', 'pickles-sensei-group-progress-tracker' ); ?></button>
						</div>
					</div>
				</details>
			</div>
			<button type="submit" class="psgpt-primary psgpt-qb-submit" data-psgpt-qb-submit><?php esc_html_e( 'Afficher', 'pickles-sensei-group-progress-tracker' ); ?> →</button>
			<p class="psgpt-qb-msg" data-psgpt-qb-msg role="alert" hidden></p>
		</form>
		<?php
	}

	private static function render_kpis( $students, $reports ) {
		$n      = count( $students );
		$sum    = 0;
		$done   = 0;
		$late   = 0;
		$wait   = 0;
		$total  = 0;
		foreach ( $reports as $r ) {
			$total += count( $r['lessons'] );
		}
		foreach ( $students as $sid => $u ) {
			$d = 0;
			foreach ( $reports as $r ) {
				$d    += $r['rows'][ $sid ]['done'];
				$wait += $r['rows'][ $sid ]['wait'];
			}
			$p    = $total ? (int) round( 100 * $d / $total ) : 0;
			$sum += $p;
			$done += 100 === $p ? 1 : 0;
			$late += $p < 50 ? 1 : 0;
		}
		$avg = $n ? (int) round( $sum / $n ) : 0;
		?>
		<section class="psgpt-kpis" aria-label="<?php esc_attr_e( 'Chiffres clés', 'pickles-sensei-group-progress-tracker' ); ?>">
			<div class="psgpt-kpi"><strong><?php echo (int) $n; ?></strong><span><?php echo esc_html( _n( 'élève dans le groupe', 'élèves dans le groupe', $n, 'pickles-sensei-group-progress-tracker' ) ); ?></span></div>
			<div class="psgpt-kpi is-good"><strong><?php echo (int) $avg; ?> %</strong><span><?php esc_html_e( 'progression moyenne', 'pickles-sensei-group-progress-tracker' ); ?></span></div>
			<div class="psgpt-kpi"><strong><?php echo (int) $done; ?> / <?php echo (int) $n; ?></strong><span><?php esc_html_e( 'ont tout terminé', 'pickles-sensei-group-progress-tracker' ); ?></span></div>
			<div class="psgpt-kpi is-warn"><strong><?php echo (int) $late; ?></strong><span><?php esc_html_e( 'sous 50 % : à relancer', 'pickles-sensei-group-progress-tracker' ); ?></span></div>
			<div class="psgpt-kpi is-info"><strong><?php echo (int) $wait; ?></strong><span><?php esc_html_e( 'rendus en attente de correction', 'pickles-sensei-group-progress-tracker' ); ?></span></div>
		</section>
		<?php
	}

	private static function render_course_tabs( $students, $reports ) {
		$base = 'psgpt-course-' . wp_unique_id();
		$days = self::get_default_inactivity_days();
		?>
		<section class="psgpt-card psgpt-board" data-psgpt-board>
			<?php if ( count( $reports ) > 1 ) : ?>
				<div class="psgpt-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Cours', 'pickles-sensei-group-progress-tracker' ); ?>" data-psgpt-tabs hidden>
					<?php foreach ( $reports as $i => $report ) : ?>
						<?php
						$avg = 0;
						foreach ( $report['rows'] as $row ) {
							$avg += $row['pct'];
						}
						$avg = count( $report['rows'] ) ? (int) round( $avg / count( $report['rows'] ) ) : 0;
						?>
						<button type="button" role="tab" id="<?php echo esc_attr( $base . '-tab-' . $i ); ?>" aria-controls="<?php echo esc_attr( $base . '-' . $i ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $i ? '0' : '-1'; ?>"><?php echo esc_html( $report['course']->post_title ); ?> <span class="psgpt-badge"><?php echo (int) $avg; ?> %</span></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $reports as $i => $report ) : ?>
				<?php
				$counts = array( 'all' => 0, 'late' => 0, 'progress' => 0, 'done' => 0 );
				foreach ( $report['rows'] as $row ) {
					$counts['all']++;
					$counts[ $row['pct'] >= 100 ? 'done' : ( $row['pct'] < 50 ? 'late' : 'progress' ) ]++;
				}
				?>
				<section class="psgpt-course-section" id="<?php echo esc_attr( $base . '-' . $i ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $base . '-tab-' . $i ); ?>" data-psgpt-panel>
					<h3 class="psgpt-course-title"><a class="psgpt-course-link" href="<?php echo esc_url( get_permalink( $report['course'] ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Ouvrir le cours dans un nouvel onglet', 'pickles-sensei-group-progress-tracker' ); ?>"><?php echo esc_html( $report['course']->post_title ); ?> <span class="psgpt-ext" aria-hidden="true">↗</span></a></h3>
					<div class="psgpt-board-tools">
						<div class="psgpt-filters-chips" role="group" aria-label="<?php esc_attr_e( 'Filtrer les élèves', 'pickles-sensei-group-progress-tracker' ); ?>" data-psgpt-rowfilter hidden>
							<button type="button" data-filter="all" aria-pressed="true"><?php esc_html_e( 'Tous', 'pickles-sensei-group-progress-tracker' ); ?> · <?php echo (int) $counts['all']; ?></button>
							<button type="button" data-filter="late" aria-pressed="false"><?php esc_html_e( 'À relancer (< 50 %)', 'pickles-sensei-group-progress-tracker' ); ?> · <?php echo (int) $counts['late']; ?></button>
							<button type="button" data-filter="progress" aria-pressed="false"><?php esc_html_e( 'En cours', 'pickles-sensei-group-progress-tracker' ); ?> · <?php echo (int) $counts['progress']; ?></button>
							<button type="button" data-filter="done" aria-pressed="false"><?php esc_html_e( 'Terminé', 'pickles-sensei-group-progress-tracker' ); ?> · <?php echo (int) $counts['done']; ?></button>
						</div>
						<?php self::render_legend(); ?>
					</div>
					<div class="psgpt-table-wrap">
						<table class="psgpt-table psgpt-grid">
							<thead>
								<tr>
									<th scope="col" class="psgpt-student-col"><?php esc_html_e( 'Élève', 'pickles-sensei-group-progress-tracker' ); ?></th>
									<th scope="col" class="psgpt-pct-col"><?php esc_html_e( 'Progression', 'pickles-sensei-group-progress-tracker' ); ?></th>
									<?php foreach ( $report['lessons'] as $lesson ) : ?>
										<th scope="col" class="psgpt-lesson-col"><a class="psgpt-lesson-link" href="<?php echo esc_url( get_permalink( $lesson ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $lesson->post_title ); ?></a><?php echo self::quiz_badge( $lesson->ID ); // phpcs:ignore -- échappé dans quiz_badge ?></th>
									<?php endforeach; ?>
									<th scope="col" class="psgpt-last-col"><?php esc_html_e( 'Dernière activité', 'pickles-sensei-group-progress-tracker' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $students as $sid => $student ) : ?>
									<?php
									$row   = $report['rows'][ $sid ];
									$band  = $row['pct'] >= 100 ? 'done' : ( $row['pct'] < 50 ? 'late' : 'progress' );
									$ago   = self::ui_days_since( $row['last'] );
									$stale = null === $ago || $ago >= $days;
									?>
									<tr data-band="<?php echo esc_attr( $band ); ?>">
										<th scope="row" class="psgpt-student-col">
											<a class="psgpt-name-link" href="#psgpt-student-<?php echo esc_attr( $sid ); ?>" data-psgpt-student="<?php echo esc_attr( $sid ); ?>">
												<span class="psgpt-avatar" aria-hidden="true"><?php echo esc_html( self::summary_initials( $student->display_name ) ); ?></span>
												<span class="psgpt-student-name"><?php echo esc_html( $student->display_name ); ?></span>
											</a>
										</th>
										<td class="psgpt-progress-cell is-<?php echo esc_attr( $band ); ?>">
											<strong><?php echo esc_html( $row['pct'] ); ?>%</strong>
											<span class="psgpt-progress"><span style="width: <?php echo esc_attr( $row['pct'] ); ?>%"></span></span>
										</td>
										<?php foreach ( $report['lessons'] as $lesson ) : ?>
											<td class="psgpt-cell-td"><?php self::render_compact_cell( isset( $row['statuses'][ $lesson->ID ] ) ? $row['statuses'][ $lesson->ID ] : array(), $sid, $lesson ); ?></td>
										<?php endforeach; ?>
										<td class="psgpt-last-cell"><span class="psgpt-last<?php echo $stale ? ' is-stale' : ''; ?>"<?php echo $row['last'] ? ' title="' . esc_attr( self::format_datetime( $row['last'] ) ) . '"' : ''; ?>><?php echo esc_html( self::ui_days_label( $ago ) ); ?></span></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="psgpt-nomatch" data-psgpt-norows hidden><?php esc_html_e( 'Aucun élève dans ce filtre.', 'pickles-sensei-group-progress-tracker' ); ?></p>
				</section>
			<?php endforeach; ?>
			<p class="psgpt-board-foot"><?php esc_html_e( 'Survolez une leçon pour voir la date et l’heure exactes. Cliquez sur un élève pour ouvrir son détail.', 'pickles-sensei-group-progress-tracker' ); ?></p>
		</section>
		<?php
	}

	private static function render_legend() {
		?>
		<ul class="psgpt-legend" aria-label="<?php esc_attr_e( 'Légende', 'pickles-sensei-group-progress-tracker' ); ?>">
			<li><span class="psgpt-dot is-done" aria-hidden="true"></span><?php esc_html_e( 'Terminée', 'pickles-sensei-group-progress-tracker' ); ?></li>
			<li><span class="psgpt-dot is-progress" aria-hidden="true"></span><?php esc_html_e( 'En cours', 'pickles-sensei-group-progress-tracker' ); ?></li>
			<li><span class="psgpt-dot is-wait" aria-hidden="true"></span><?php esc_html_e( 'À corriger', 'pickles-sensei-group-progress-tracker' ); ?></li>
			<li><span class="psgpt-dot is-failed" aria-hidden="true"></span><?php esc_html_e( 'À reprendre', 'pickles-sensei-group-progress-tracker' ); ?></li>
			<li><span class="psgpt-dot is-empty" aria-hidden="true"></span><?php esc_html_e( 'Pas commencée', 'pickles-sensei-group-progress-tracker' ); ?></li>
		</ul>
		<?php
	}

	private static function render_compact_cell( $detail, $user_id, $lesson ) {
		$status = isset( $detail['status'] ) ? $detail['status'] : '';
		$kind   = self::ui_status_kind( $status );
		$label  = self::format_status( $status );
		list( $short, $full ) = self::ui_lesson_dates( $detail );
		$quiz  = self::ui_quiz( $user_id, $lesson->ID );
		$title = $lesson->post_title . ' — ' . $label . ( $full ? ' · ' . $full : '' );
		if ( ! empty( $detail['reopened_at'] ) ) {
			/* translators: %s: date */
			$title .= ' · ' . sprintf( __( 'Reprise le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['reopened_at'] ) );
		}
		if ( '' !== $quiz ) {
			/* translators: %s: pourcentage */
			$title .= ' · ' . sprintf( __( 'Quiz : %s%%', 'pickles-sensei-group-progress-tracker' ), $quiz );
		}
		?>
		<span class="psgpt-cell" title="<?php echo esc_attr( $title ); ?>">
			<span class="psgpt-dot is-<?php echo esc_attr( $kind ); ?>" aria-hidden="true"></span>
			<span class="psgpt-sr"><?php echo esc_html( $label . ( $full ? ', ' . $full : '' ) ); ?></span>
			<small aria-hidden="true"><?php echo esc_html( $short ? $short : '—' ); ?></small>
			<?php if ( '' !== $quiz ) : ?>
				<small class="psgpt-quiz-score"><?php echo esc_html( sprintf( __( 'Quiz %s %%', 'pickles-sensei-group-progress-tracker' ), $quiz ) ); ?></small>
			<?php endif; ?>
		</span>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Détail par élève
	 * ------------------------------------------------------------------- */

	private static function render_student_detail( $students, $reports, $group_id = 0 ) {
		$history = self::get_revision_history_for_students( array_keys( $students ) );
		$total   = 0;
		foreach ( $reports as $r ) {
			$total += count( $r['lessons'] );
		}
		$first = (int) key( $students );
		$susp  = array();
		foreach ( $students as $sid => $student ) {
			$susp[ $sid ] = self::student_suspicion( $sid, $reports );
		}
		?>
		<section class="psgpt-student-summary" data-psgpt-detail>
			<h3 class="psgpt-section-title"><?php esc_html_e( 'Détail par élève', 'pickles-sensei-group-progress-tracker' ); ?></h3>
			<?php self::render_reset_notice(); ?>
			<div class="psgpt-detail">
				<nav class="psgpt-card psgpt-student-list" aria-label="<?php esc_attr_e( 'Élèves', 'pickles-sensei-group-progress-tracker' ); ?>">
					<span class="psgpt-kicker psgpt-kicker-muted"><?php echo esc_html( sprintf( __( 'Élèves (%d)', 'pickles-sensei-group-progress-tracker' ), count( $students ) ) ); ?></span>
					<?php foreach ( $students as $sid => $student ) : ?>
						<?php
						$d = 0;
						foreach ( $reports as $r ) {
							$d += $r['rows'][ $sid ]['done'];
						}
						$p = $total ? (int) round( 100 * $d / $total ) : 0;
						?>
						<a class="psgpt-st<?php echo $sid === $first ? ' is-active' : ''; ?>" href="#psgpt-student-<?php echo esc_attr( $sid ); ?>" data-psgpt-student="<?php echo esc_attr( $sid ); ?>"<?php echo $sid === $first ? ' aria-current="true"' : ''; ?>>
							<span class="psgpt-avatar" aria-hidden="true"><?php echo esc_html( self::summary_initials( $student->display_name ) ); ?></span>
							<span class="psgpt-st-main">
								<span class="psgpt-st-name"><?php echo esc_html( $student->display_name ); ?><?php self::render_suspect_badge( $susp[ $sid ] ); ?></span>
								<span class="psgpt-progress"><span class="<?php echo $p < 50 ? 'is-late' : ''; ?>" style="width: <?php echo esc_attr( $p ); ?>%"></span></span>
							</span>
							<span class="psgpt-st-pct<?php echo $p < 50 ? ' is-late' : ''; ?>"><?php echo (int) $p; ?> %</span>
						</a>
					<?php endforeach; ?>
				</nav>
				<div class="psgpt-panels">
					<?php foreach ( $students as $sid => $student ) : ?>
						<?php
						$d    = 0;
						$last = '';
						foreach ( $reports as $r ) {
							$d += $r['rows'][ $sid ]['done'];
							if ( $r['rows'][ $sid ]['last'] > $last ) {
								$last = $r['rows'][ $sid ]['last'];
							}
						}
						$p = $total ? (int) round( 100 * $d / $total ) : 0;
						?>
						<article class="psgpt-student-summary-card psgpt-student-panel<?php echo $sid === $first ? ' is-active' : ''; ?>" id="psgpt-student-<?php echo esc_attr( $sid ); ?>" data-psgpt-panel-student="<?php echo esc_attr( $sid ); ?>" tabindex="-1">
							<header class="psgpt-card psgpt-panel-head">
								<span class="psgpt-avatar psgpt-avatar-lg" aria-hidden="true"><?php echo esc_html( self::summary_initials( $student->display_name ) ); ?></span>
								<div class="psgpt-panel-who">
									<h4><?php echo esc_html( $student->display_name ); ?></h4>
									<small><?php echo esc_html( sprintf( __( 'Dernière activité : %s', 'pickles-sensei-group-progress-tracker' ), $last ? self::format_datetime( $last ) : __( 'aucune', 'pickles-sensei-group-progress-tracker' ) ) ); ?></small>
								</div>
								<div class="psgpt-panel-stats">
									<span><strong class="is-good"><?php echo (int) $p; ?> %</strong><small><?php esc_html_e( 'progression globale', 'pickles-sensei-group-progress-tracker' ); ?></small></span>
									<span><strong><?php echo (int) $d; ?> / <?php echo (int) $total; ?></strong><small><?php esc_html_e( 'leçons terminées', 'pickles-sensei-group-progress-tracker' ); ?></small></span>
								</div>
							</header>
							<?php self::render_suspect_panel( $susp[ $sid ] ); ?>
							<?php foreach ( $reports as $r ) : ?>
								<?php $row = $r['rows'][ $sid ]; ?>
								<div class="psgpt-card psgpt-student-summary-course">
									<div class="psgpt-summary-course-head">
										<strong class="psgpt-summary-course-title"><a class="psgpt-course-link" href="<?php echo esc_url( get_permalink( $r['course'] ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Ouvrir le cours dans un nouvel onglet', 'pickles-sensei-group-progress-tracker' ); ?>"><?php echo esc_html( $r['course']->post_title ); ?> <span class="psgpt-ext" aria-hidden="true">↗</span></a></strong>
										<span class="psgpt-summary-bar">
											<span class="psgpt-progress" role="img" aria-label="<?php echo esc_attr( sprintf( '%d %%', $row['pct'] ) ); ?>"><span style="width: <?php echo esc_attr( $row['pct'] ); ?>%"></span></span>
											<span class="psgpt-summary-course-pct"><?php echo (int) $row['pct']; ?>%</span>
										</span>
										<?php if ( $row['done'] > 0 || ! empty( $row['statuses'] ) ) { self::render_reset_button( $student, $r['course'], null, $group_id ); } ?>
									</div>
									<small class="psgpt-summary-course-count"><?php echo esc_html( sprintf( _n( '%1$d leçon terminée sur %2$d', '%1$d leçons terminées sur %2$d', $row['done'], 'pickles-sensei-group-progress-tracker' ), $row['done'], count( $r['lessons'] ) ) ); ?></small>
									<?php
									// Cours organisé en modules Sensei : un en-tête par module, avec « Réinitialiser le module ».
									$modules_map = self::lesson_module_map( wp_list_pluck( $r['lessons'], 'ID' ) );
									$started_mod = array();
									foreach ( $modules_map as $mlid => $mterm ) {
										if ( ! empty( $row['statuses'][ $mlid ]['status'] ) ) {
											$started_mod[ (int) $mterm->term_id ] = true;
										}
									}
									$current_mod = -1;
									?>
									<ol class="psgpt-summary-lessons<?php echo $modules_map ? ' has-modules' : ''; ?>">
										<?php foreach ( $r['lessons'] as $lesson ) : ?>
											<?php
											$lesson_mod = isset( $modules_map[ $lesson->ID ] ) ? $modules_map[ $lesson->ID ] : null;
											$mod_id     = $lesson_mod ? (int) $lesson_mod->term_id : 0;
											if ( $modules_map && $mod_id !== $current_mod ) :
												$current_mod = $mod_id;
												?>
												<li class="psgpt-module-head">
													<strong><?php echo esc_html( $lesson_mod ? ( 0 === stripos( $lesson_mod->name, 'module' ) ? $lesson_mod->name : sprintf( __( 'Module : %s', 'pickles-sensei-group-progress-tracker' ), $lesson_mod->name ) ) : __( 'Hors module', 'pickles-sensei-group-progress-tracker' ) ); ?></strong>
													<?php if ( $lesson_mod && ! empty( $started_mod[ $mod_id ] ) ) { self::render_reset_button( $student, $r['course'], null, $group_id, $lesson_mod ); } ?>
												</li>
											<?php endif; ?>
											<?php
											$detail = isset( $row['statuses'][ $lesson->ID ] ) ? $row['statuses'][ $lesson->ID ] : array();
											$status = isset( $detail['status'] ) ? $detail['status'] : '';
											$kind   = self::ui_status_kind( $status );
											list( , $full ) = self::ui_lesson_dates( $detail );
											$quiz = self::ui_quiz( $sid, $lesson->ID );
											?>
											<li>
												<span class="psgpt-dot is-<?php echo esc_attr( $kind ); ?>" aria-hidden="true"></span>
												<span class="psgpt-summary-lesson"><?php echo esc_html( $lesson->post_title ); ?> <?php echo self::quiz_badge( $lesson->ID ); // phpcs:ignore -- échappé ?><?php if ( ! empty( $susp[ $sid ]['flag'] ) && isset( $susp[ $sid ]['rapid'][ $lesson->ID ] ) ) : ?> <span class="psgpt-fast" title="<?php esc_attr_e( 'Validée très vite', 'pickles-sensei-group-progress-tracker' ); ?>">⚡ <?php echo esc_html( self::suspect_duration( $susp[ $sid ]['rapid'][ $lesson->ID ]['seconds'] ) ); ?></span><?php endif; ?></span>
												<span class="psgpt-pill is-<?php echo esc_attr( $kind ); ?>"><?php echo esc_html( self::format_status( $status ) ); ?></span>
												<span class="psgpt-summary-date">
													<?php echo esc_html( $full ? $full : '—' ); ?>
													<?php if ( '' !== $quiz ) : ?>
														<span class="psgpt-quiz-score"> · <?php echo esc_html( sprintf( __( 'Quiz : %s%%', 'pickles-sensei-group-progress-tracker' ), $quiz ) ); ?></span>
													<?php endif; ?>
													<?php if ( ! empty( $detail['reopened_at'] ) ) : ?>
														<span class="psgpt-retry"> · <?php echo esc_html( sprintf( __( 'Reprise le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $detail['reopened_at'] ) ) ); ?></span>
													<?php endif; ?>
												</span>
												<?php if ( '' !== $status ) { self::render_reset_button( $student, $r['course'], $lesson, $group_id ); } ?>
											</li>
										<?php endforeach; ?>
									</ol>
								</div>
							<?php endforeach; ?>
							<?php if ( ! empty( $history[ $sid ] ) ) : ?>
								<div class="psgpt-card psgpt-revision-history">
									<h5><?php esc_html_e( 'Révisions récentes', 'pickles-sensei-group-progress-tracker' ); ?></h5>
									<p class="psgpt-muted psgpt-small"><?php esc_html_e( 'Tirées du module de révisions LogiProf, indépendamment des cours choisis.', 'pickles-sensei-group-progress-tracker' ); ?></p>
									<ul class="psgpt-revision-list">
										<?php foreach ( $history[ $sid ] as $hrow ) : ?>
											<li><strong><?php echo esc_html( self::format_revision_modules( $hrow->modules, $hrow->progress ) ); ?></strong> · <?php echo esc_html( self::format_datetime( $hrow->finished_at ) ); ?> · <?php echo esc_html( self::format_revision_status( $hrow->status ) ); ?> · <?php echo (int) $hrow->percent; ?> %</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Élèves inactifs
	 * ------------------------------------------------------------------- */

	private static function render_inactivity_tracker( $atts ) {
		$keys     = self::ui_keys( $atts );
		$keys['group'] = 'psgpt_inactive_group_' . sanitize_key( isset( $atts['filter_id'] ) ? $atts['filter_id'] : 'inactive' );
		$group_id = absint( $atts['group_id'] );
		if ( 'yes' === strtolower( (string) $atts['show_filters'] ) && isset( $_GET[ $keys['group'] ] ) ) {
			$group_id = absint( $_GET[ $keys['group'] ] );
		}
		$classes = array( 'psgpt', 'psgpt-ui', 'psgpt-inactivity' );
		if ( ! empty( $atts['class'] ) ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}
		ob_start();
		echo '<section class="' . esc_attr( implode( ' ', $classes ) ) . '" data-psgpt-ui data-fav-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-fav-action="psgpt_toggle_favorite" data-fav-nonce="' . esc_attr( wp_create_nonce( 'psgpt_favorite' ) ) . '">';
		self::render_inactivity_view( $keys, $group_id, max( 1, absint( $atts['days'] ) ), 'yes' === strtolower( (string) $atts['show_filters'] ), isset( $atts['empty_text'] ) ? $atts['empty_text'] : '', false );
		echo '</section>';
		return ob_get_clean();
	}

	private static function render_inactivity_view( $keys, $group_id, $days, $show_filters, $empty_text = '', $link_to_progress = true ) {
		if ( $show_filters ) {
			?>
			<form class="psgpt-select psgpt-inactivity-filters" method="get" data-psgpt-select>
				<?php self::ui_hidden_inputs( array( $keys['group'] ) ); ?>
				<?php self::render_group_picker( $keys, $group_id, 0, true ); ?>
				<noscript><button type="submit" class="psgpt-primary"><?php esc_html_e( 'Vérifier', 'pickles-sensei-group-progress-tracker' ); ?></button></noscript>
			</form>
			<?php
		}
		if ( ! $group_id ) {
			echo '<div class="psgpt-empty">' . esc_html__( 'Choisissez un groupe pour afficher les élèves inactifs.', 'pickles-sensei-group-progress-tracker' ) . '</div>';
			return;
		}
		$group  = get_post( $group_id );
		$alerts = self::get_inactive_students_for_group( $group_id, $days );
		?>
		<div class="psgpt-inactive-head">
			<h3 class="psgpt-section-title"><?php echo esc_html( $group ? $group->post_title : '' ); ?></h3>
			<?php /* translators: 1: nombre d'élèves, 2: jours */ ?>
			<span class="psgpt-muted"><?php echo esc_html( sprintf( _n( '%1$d élève inactif · seuil : %2$d jours', '%1$d élèves inactifs · seuil : %2$d jours', count( $alerts ), 'pickles-sensei-group-progress-tracker' ), count( $alerts ), $days ) ); ?></span>
		</div>
		<?php
		if ( empty( $alerts ) ) {
			echo '<div class="psgpt-empty">' . esc_html( $empty_text ? $empty_text : __( 'Aucun élève inactif à signaler pour ce groupe.', 'pickles-sensei-group-progress-tracker' ) ) . '</div>';
			return;
		}
		?>
		<div class="psgpt-inactive-list">
			<?php foreach ( $alerts as $alert ) : ?>
				<?php
				$oldest = null;
				$cids   = array();
				foreach ( $alert['modules'] as $m ) {
					$ago    = self::ui_days_since( $m['last_date'] );
					$oldest = null === $oldest ? $ago : max( $oldest, (int) $ago );
					$cids[] = (int) $m['course_id'];
				}
				$detail_url = $link_to_progress ? self::ui_url( $keys, array( $keys['group'] => $group_id, $keys['course'] => $cids ) ) . '#psgpt-student-' . (int) $alert['student']->ID : '';
				?>
				<article class="psgpt-card psgpt-inactive-card">
					<span class="psgpt-avatar psgpt-avatar-warn" aria-hidden="true"><?php echo esc_html( self::summary_initials( $alert['student']->display_name ) ); ?></span>
					<div class="psgpt-inactive-student">
						<strong><?php echo esc_html( $alert['student']->display_name ); ?></strong>
						<?php foreach ( $alert['modules'] as $m ) : ?>
							<span class="psgpt-inactive-module"><a href="<?php echo esc_url( get_permalink( $m['course_id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $m['course_title'] ); ?></a> · <?php echo (int) $m['progress']; ?> % · <?php echo esc_html( sprintf( __( 'dernière activité le %s', 'pickles-sensei-group-progress-tracker' ), self::format_datetime( $m['last_date'] ) ) ); ?></span>
						<?php endforeach; ?>
					</div>
					<?php /* translators: %d: jours */ ?>
					<span class="psgpt-pill is-progress psgpt-pill-lg"><?php echo esc_html( sprintf( _n( 'Inactif depuis %d jour', 'Inactif depuis %d jours', (int) $oldest, 'pickles-sensei-group-progress-tracker' ), (int) $oldest ) ); ?></span>
					<?php if ( $detail_url ) : ?>
						<a class="psgpt-btn" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Voir le détail', 'pickles-sensei-group-progress-tracker' ); ?></a>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
			<p class="psgpt-muted psgpt-small"><?php esc_html_e( 'Les cours terminés à 100 % ne sont jamais signalés.', 'pickles-sensei-group-progress-tracker' ); ?></p>
		</div>
		<?php
	}
}
