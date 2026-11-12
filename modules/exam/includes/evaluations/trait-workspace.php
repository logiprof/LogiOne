<?php
/**
 * LogiEval_Secure_Evaluations — partie « workspace ».
 * Navigation simplifiée : Mes évaluations · Bibliothèque · Banque de questions · Réglages,
 * et une page unique par évaluation en 4 étapes : Préparer → Passation → Corriger → Résultats.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Workspace {

	/** Vrai pendant l'affichage d'une page d'évaluation : les anciens sélecteurs d'évaluation sont masqués. */
	private static $embedded_in_evaluation = false;

	/* ---------------------------------------------------------------------
	 * Routage et URLs
	 * ------------------------------------------------------------------- */

	public static function evaluation_steps(): array {
		return array(
			'prepare' => __( 'Préparer', 'logieval-secure' ),
			'run'     => __( 'Passation', 'logieval-secure' ),
			'grade'   => __( 'Corriger', 'logieval-secure' ),
			'results' => __( 'Résultats', 'logieval-secure' ),
		);
	}

	/**
	 * Détermine l'écran à afficher à partir de l'URL (admin ou shortcode), y compris les anciennes
	 * adresses (codes, monitoring, sécurité, corrections, exports) qui mènent à l'étape correspondante.
	 */
	private static function current_route(): array {
		$legacy_steps = array(
			'codes'       => 'run',
			'monitoring'  => 'run',
			'security'    => 'run',
			'corrections' => 'grade',
			'exports'     => 'results',
		);
		$view = sanitize_key( wp_unslash( $_GET['logiexam_view'] ?? '' ) );
		if ( is_admin() ) {
			$page = sanitize_key( wp_unslash( $_GET['page'] ?? self::ADMIN_PAGE ) );
			if ( 'preview' !== $view ) {
				$view = str_replace( 'logiexam-', '', $page );
				if ( in_array( $page, array( self::ADMIN_PAGE, 'logieval-secure' ), true ) ) {
					$view = 'home';
				}
			}
		}
		if ( in_array( $view, array( '', 'dashboard', 'evaluations' ), true ) ) {
			$view = 'home';
		}

		$evaluation_id = absint( $_GET['evaluation_id'] ?? 0 );
		$raw           = $evaluation_id ? self::get_evaluation( $evaluation_id ) : null;
		$evaluation    = self::can_manage_evaluation( $raw ) && 'deleted' !== (string) ( $raw['status'] ?? '' ) ? $raw : null;

		if ( isset( $legacy_steps[ $view ] ) ) {
			return $evaluation ? array( 'screen' => 'evaluation', 'evaluation' => $evaluation, 'step' => $legacy_steps[ $view ], 'raw' => $raw ) : array( 'screen' => 'home', 'evaluation' => null, 'step' => '', 'raw' => $raw );
		}
		if ( 'create' === $view && $evaluation ) {
			return array( 'screen' => 'evaluation', 'evaluation' => $evaluation, 'step' => 'prepare', 'raw' => $raw );
		}
		if ( 'evaluation' === $view ) {
			if ( ! $evaluation ) {
				return array( 'screen' => 'home', 'evaluation' => null, 'step' => '', 'raw' => $raw );
			}
			$step = sanitize_key( wp_unslash( $_GET['step'] ?? '' ) );
			if ( ! isset( self::evaluation_steps()[ $step ] ) ) {
				$step = self::evaluation_workflow( $evaluation )['step'];
			}
			return array( 'screen' => 'evaluation', 'evaluation' => $evaluation, 'step' => $step, 'raw' => $raw );
		}
		if ( ! in_array( $view, array( 'home', 'create', 'library', 'questions', 'settings', 'preview', 'generator', 'accommodations' ), true ) ) {
			$view = 'home';
		}
		return array( 'screen' => $view, 'evaluation' => $evaluation, 'step' => '', 'raw' => $raw );
	}

	/** URL d'un écran de l'application (wp-admin ou page du shortcode enseignant). */
	public static function app_url( string $screen, array $args = array() ): string {
		if ( is_admin() || wp_doing_ajax() && ! empty( $_SERVER['HTTP_REFERER'] ) && false !== strpos( (string) $_SERVER['HTTP_REFERER'], '/wp-admin/' ) ) {
			$pages = array(
				'home'       => self::ADMIN_PAGE,
				'evaluation' => 'logiexam-evaluation',
				'create'     => 'logiexam-create',
				'library'    => 'logiexam-library',
				'questions'  => 'logiexam-questions',
				'settings'   => 'logiexam-settings',
				'generator'  => 'logiexam-generator',
				'accommodations' => 'logiexam-accommodations',
				'preview'    => self::ADMIN_PAGE,
			);
			$query = array( 'page' => $pages[ $screen ] ?? self::ADMIN_PAGE );
			if ( 'preview' === $screen ) {
				$query['logiexam_view'] = 'preview';
			}
			return add_query_arg( array_merge( $query, array_filter( $args, static fn( $v ) => null !== $v && '' !== $v ) ), admin_url( 'admin.php' ) );
		}
		$base = self::teacher_base_url();
		return add_query_arg( array_merge( array( 'logiexam_view' => $screen ), array_filter( $args, static fn( $v ) => null !== $v && '' !== $v ) ), $base );
	}

	/** Adresse de la page qui contient le shortcode enseignant, sans paramètres LogiExam. */
	private static function teacher_base_url(): string {
		$keys = array( 'logiexam_view', 'evaluation_id', 'step', 'attempt', 'mode', 'question_id', 'saved', 'question_saved', 'imported', 'graded', 'duplicated', 'deleted', 'restored', 'codes', 'correction_incomplete', 'incomplete_attempt', 'reopened', 'open_attempt', 'new_codes', 'share_updated', 'copied', 'settings_saved', 'unlocked', 'finalized', 'correction_status', 'correction_group', 'library_q', 'logiexam_scope', 'question_deleted', 'questions_blocked', 'sensei_course_id', 'question_module', 'question_category', 'les_trash', 'gen_saved', 'gen_created', 'gen_error', 'acc_group', 'acc_saved', 'lp_published', 'lp_snapshot_warnings' );
		if ( wp_doing_ajax() && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			return remove_query_arg( $keys, esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
		}
		return remove_query_arg( $keys );
	}

	public static function evaluation_url( int $evaluation_id, string $step = '', array $args = array() ): string {
		return self::app_url( 'evaluation', array_merge( array( 'evaluation_id' => $evaluation_id, 'step' => $step ), $args ) );
	}

	/** Point d'entrée commun (wp-admin et shortcode) : navigation + écran demandé. */
	private static function render_teacher_app(): void {
		$route = self::current_route();
		$nav   = in_array( $route['screen'], array( 'evaluation', 'create', 'preview' ), true ) ? 'home' : $route['screen'];
		self::render_main_nav( $nav );
		switch ( $route['screen'] ) {
			case 'evaluation':
				self::render_evaluation_page( $route['evaluation'], $route['step'] );
				break;
			case 'create':
				self::render_create_workspace( null, self::get_questions(), LogiEval_Secure_Sensei::get_courses(), LogiEval_Secure_Sensei::get_groups() );
				break;
			case 'preview':
				self::render_teacher_preview_workspace( self::can_view_evaluation( $route['raw'] ) ? $route['raw'] : null );
				break;
			case 'library':
				self::render_library_workspace();
				break;
			case 'questions':
				self::render_questions_workspace( self::get_questions(), LogiEval_Secure_Sensei::get_courses() );
				break;
			case 'settings':
				self::render_settings_workspace();
				break;
			case 'generator':
				self::render_generator_workspace();
				break;
			case 'accommodations':
				self::render_accommodations_workspace();
				break;
			default:
				self::render_home_workspace();
				break;
		}
	}

	private static function render_main_nav( string $current ): void {
		$items = array(
			'home'      => __( 'Mes évaluations', 'logieval-secure' ),
			'library'   => __( 'Bibliothèque', 'logieval-secure' ),
			'questions' => __( 'Banque de questions', 'logieval-secure' ),
			'generator' => __( '✨ Créer avec l’IA', 'logieval-secure' ),
			'accommodations' => __( 'Aménagements', 'logieval-secure' ),
			'settings'  => __( 'Réglages', 'logieval-secure' ),
		);
		if ( ! current_user_can( 'lp_exam_ai' ) ) {
			unset( $items['generator'] );
		}
		?>
		<nav class="les-main-nav" aria-label="<?php esc_attr_e( 'Navigation LogiExam', 'logieval-secure' ); ?>">
			<?php foreach ( $items as $screen => $label ) : ?>
				<a class="<?php echo $current === $screen ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::app_url( $screen ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
			<a class="les-main-nav-create" href="<?php echo esc_url( self::app_url( 'create' ) ); ?>">+ <?php esc_html_e( 'Créer une évaluation', 'logieval-secure' ); ?></a>
		</nav>
		<?php self::render_scope_switch(); ?>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Statut et prochaine action d'une évaluation
	 * ------------------------------------------------------------------- */

	/**
	 * @return array{key:string,label:string,step:string,next_label:string,counts:array}
	 */
	public static function evaluation_workflow( array $evaluation ): array {
		$counts = array(
			'students'    => count( self::resolve_student_ids( $evaluation ) ),
			'codes'       => self::codes_count_for_evaluation( (int) $evaluation['id'] ),
			'in_progress' => 0,
			'blocked'     => 0,
			'submitted'   => 0,
			'to_grade'    => 0,
			'graded'      => 0,
			'cancelled'   => 0,
			'questions'   => count( self::get_questions_for_evaluation( (int) $evaluation['id'] ) ),
		);
		foreach ( self::get_attempts( (int) $evaluation['id'] ) as $attempt ) {
			$result = self::get_result_by_attempt( (int) $attempt['id'] );
			$status = (string) $attempt['status'];
			if ( self::attempt_is_cheating_cancelled( $attempt ) ) {
				$counts['cancelled']++;
				$counts['submitted']++;
				continue;
			}
			if ( self::attempt_needs_teacher_unlock( $attempt ) ) {
				$counts['blocked']++;
			}
			if ( in_array( $status, array( 'in_progress', 'security_paused', 'locked_exit_attempt' ), true ) && ! empty( $attempt['started_at'] ) && empty( $attempt['submitted_at'] ) ) {
				$counts['in_progress']++;
			}
			if ( ! empty( $attempt['submitted_at'] ) || in_array( $status, array( 'submitted', 'submitted_locked', 'graded' ), true ) ) {
				$counts['submitted']++;
				if ( self::attempt_correction_is_finished( $attempt, $result ) ) {
					$counts['graded']++;
				} elseif ( self::attempt_needs_correction( $attempt, $result ) ) {
					$counts['to_grade']++;
				}
			}
		}

		if ( 'draft' === (string) $evaluation['status'] || 0 === $counts['questions'] ) {
			$state = array( 'key' => 'draft', 'label' => __( 'Brouillon', 'logieval-secure' ), 'step' => 'prepare', 'next_label' => __( 'Terminer la préparation', 'logieval-secure' ) );
		} elseif ( $counts['in_progress'] > 0 || $counts['blocked'] > 0 ) {
			$state = array( 'key' => 'running', 'label' => __( 'En cours', 'logieval-secure' ), 'step' => 'run', 'next_label' => sprintf( _n( 'Suivre la passation (%d élève)', 'Suivre la passation (%d élèves)', $counts['in_progress'], 'logieval-secure' ), $counts['in_progress'] ) );
		} elseif ( $counts['to_grade'] > 0 ) {
			$state = array( 'key' => 'to_grade', 'label' => __( 'À corriger', 'logieval-secure' ), 'step' => 'grade', 'next_label' => sprintf( _n( 'Corriger (%d copie)', 'Corriger (%d copies)', $counts['to_grade'], 'logieval-secure' ), $counts['to_grade'] ) );
		} elseif ( $counts['submitted'] > 0 ) {
			$state = array( 'key' => 'graded', 'label' => __( 'Corrigée', 'logieval-secure' ), 'step' => 'results', 'next_label' => __( 'Voir les résultats', 'logieval-secure' ) );
		} else {
			$state = array( 'key' => 'ready', 'label' => __( 'Prête', 'logieval-secure' ), 'step' => 'run', 'next_label' => __( 'Imprimer les codes', 'logieval-secure' ) );
		}
		$state['counts'] = $counts;
		return $state;
	}

	private static function render_status_badge( array $workflow ): void {
		?>
		<span class="les-status-badge is-<?php echo esc_attr( $workflow['key'] ); ?>"><?php echo esc_html( $workflow['label'] ); ?></span>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Mes évaluations
	 * ------------------------------------------------------------------- */

	private static function render_home_workspace(): void {
		$evaluations = self::get_evaluations();
		$deleted     = self::get_deleted_evaluations();
		?>
		<section class="les-panel les-home" data-les-monitoring data-evaluation-id="0" data-refresh-interval="15000">
			<div class="les-home-head">
				<div>
					<h2><?php echo esc_html( self::admin_sees_all() ? __( 'Toutes les évaluations (tous les enseignants)', 'logieval-secure' ) : __( 'Mes évaluations', 'logieval-secure' ) ); ?></h2>
					<p class="description"><?php esc_html_e( 'Chaque évaluation affiche son statut et la prochaine action à faire.', 'logieval-secure' ); ?></p>
				</div>
				<div class="les-home-head-actions">
					<a class="button les-button-secondary les-trash-link" href="<?php echo esc_url( add_query_arg( 'les_trash', 1, self::app_url( 'home' ) ) . '#les-trash' ); ?>" data-les-open-trash><span aria-hidden="true">🗑</span> <?php echo esc_html( sprintf( __( 'Corbeille (%d)', 'logieval-secure' ), count( $deleted ) ) ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( self::app_url( 'create' ) ); ?>">+ <?php esc_html_e( 'Créer une évaluation', 'logieval-secure' ); ?></a>
				</div>
			</div>
			<?php self::render_home_notices(); ?>
			<p class="description" data-les-monitoring-status hidden></p>
			<div data-les-monitoring-body><?php self::render_monitoring_global_body(); ?></div>
			<?php if ( empty( $evaluations ) ) : ?>
				<div class="les-empty-state">
					<h3><?php esc_html_e( 'Aucune évaluation pour le moment', 'logieval-secure' ); ?></h3>
					<p><?php esc_html_e( 'Créez une évaluation, ou dupliquez un modèle depuis la bibliothèque partagée.', 'logieval-secure' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( self::app_url( 'create' ) ); ?>"><?php esc_html_e( 'Créer une évaluation', 'logieval-secure' ); ?></a> <a class="button les-button-secondary" href="<?php echo esc_url( self::app_url( 'library' ) ); ?>"><?php esc_html_e( 'Ouvrir la bibliothèque', 'logieval-secure' ); ?></a></p>
				</div>
			<?php else : ?>
				<?php self::render_evaluation_board( $evaluations ); ?>
			<?php endif; ?>
			<details class="les-deleted-history les-trash" id="les-trash"<?php echo ( ! empty( $_GET['les_trash'] ) || ! empty( $_GET['deleted'] ) ) ? ' open' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>>
				<summary><span aria-hidden="true">🗑</span> <?php echo esc_html( sprintf( __( 'Corbeille : évaluations supprimées (%d)', 'logieval-secure' ), count( $deleted ) ) ); ?></summary>
				<p class="description"><?php esc_html_e( 'Une évaluation supprimée n’est jamais effacée : ses questions, codes, copies et corrections sont conservés. « Restaurer » la remet dans Mes évaluations, avec le statut qu’elle avait avant.', 'logieval-secure' ); ?></p>
				<?php self::render_deleted_evaluations_table( $deleted ); ?>
			</details>
		</section>
		<?php
	}

	private static function render_home_notices(): void {
		$messages = array(
			'deleted'  => __( 'Évaluation placée dans la corbeille (en bas de cette page). Vous pouvez la restaurer à tout moment.', 'logieval-secure' ),
			'restored' => __( 'Évaluation restaurée.', 'logieval-secure' ),
		);
		foreach ( $messages as $key => $text ) {
			if ( isset( $_GET[ $key ] ) ) {
				echo '<div class="notice notice-success inline"><p>' . esc_html( $text ) . '</p></div>';
			}
		}
		if ( isset( $_GET['share_updated'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html( absint( $_GET['share_updated'] ) ? __( 'Évaluation partagée : elle apparaît maintenant dans la bibliothèque des enseignants.', 'logieval-secure' ) : __( 'Évaluation retirée de la bibliothèque partagée. Les copies déjà faites par vos collègues sont conservées.', 'logieval-secure' ) ) . '</p></div>';
		}
	}

	/**
	 * « Mes évaluations » en colonnes, une par classe (comme LogiNotes) : d'abord « Non affectées »,
	 * puis chaque classe. Dans une colonne : les évaluations sans date d'abord, puis par date de planification.
	 */
	private static function render_evaluation_board( array $evaluations ): void {
		$names = array();
		foreach ( LogiEval_Secure_Sensei::get_groups() as $group ) {
			$names[ (int) $group->term_id ] = (string) $group->name;
		}
		$columns = array();
		$none    = array();
		foreach ( $evaluations as $evaluation ) {
			$group_ids = self::decode_ids( $evaluation['sensei_group_ids'] ?? '' );
			if ( empty( $group_ids ) ) {
				$none[] = $evaluation;
				continue;
			}
			foreach ( $group_ids as $gid ) {
				$gid = (int) $gid;
				if ( ! isset( $columns[ $gid ] ) ) {
					$label = $names[ $gid ] ?? ( get_the_title( $gid ) ?: sprintf( __( 'Groupe %d', 'logieval-secure' ), $gid ) );
					$columns[ $gid ] = array( 'label' => $label, 'items' => array() );
				}
				$columns[ $gid ]['items'][] = $evaluation;
			}
		}
		uasort( $columns, static fn( $a, $b ) => strnatcasecmp( remove_accents( $a['label'] ), remove_accents( $b['label'] ) ) );
		$sort = static function ( array $items ): array {
			usort(
				$items,
				static function ( $a, $b ) {
					$da = (string) ( $a['opens_at'] ?? '' );
					$db = (string) ( $b['opens_at'] ?? '' );
					if ( '' === $da || '' === $db ) {
						if ( $da !== $db ) {
							return '' === $da ? -1 : 1; // Sans date : en haut.
						}
						return (int) $b['id'] <=> (int) $a['id']; // Les plus récentes d'abord.
					}
					return strcmp( $da, $db ) ?: ( (int) $a['id'] <=> (int) $b['id'] );
				}
			);
			return $items;
		};
		$board = array();
		if ( $none ) {
			$board[] = array( 'key' => 'none', 'label' => __( 'Non affectées', 'logieval-secure' ), 'hint' => __( 'Aucune classe choisie', 'logieval-secure' ), 'items' => $sort( $none ) );
		}
		foreach ( $columns as $gid => $col ) {
			$board[] = array( 'key' => (string) $gid, 'label' => $col['label'], 'hint' => '', 'items' => $sort( $col['items'] ) );
		}
		?>
		<p class="description les-board-intro"><?php esc_html_e( 'Une colonne par classe. Dans chaque classe : les évaluations sans date en premier, puis dans l’ordre de leur date de planification.', 'logieval-secure' ); ?></p>
		<div class="les-eval-board">
			<?php foreach ( $board as $col ) : ?>
				<section class="les-board-col<?php echo 'none' === $col['key'] ? ' is-unassigned' : ''; ?>" aria-label="<?php echo esc_attr( $col['label'] ); ?>">
					<header class="les-board-col-head">
						<h3><?php echo esc_html( $col['label'] ); ?></h3>
						<span class="les-board-count"><?php echo (int) count( $col['items'] ); ?></span>
					</header>
					<?php if ( $col['hint'] ) : ?><p class="les-board-hint"><?php echo esc_html( $col['hint'] ); ?></p><?php endif; ?>
					<div class="les-board-cards">
						<?php foreach ( $col['items'] as $evaluation ) : self::render_evaluation_card( $evaluation, true ); endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_evaluation_card( array $evaluation, bool $in_board = false ): void {
		$id       = (int) $evaluation['id'];
		$workflow = self::evaluation_workflow( $evaluation );
		$c        = $workflow['counts'];
		$classes  = self::evaluation_group_names( $evaluation );
		$options  = self::exam_options( $id );
		?>
		<article class="les-eval-card is-<?php echo esc_attr( $workflow['key'] ); ?><?php echo $in_board ? ' is-in-board' : ''; ?>">
			<div class="les-eval-card-main">
				<?php if ( $in_board ) : ?>
					<span class="les-eval-card-when<?php echo empty( $evaluation['opens_at'] ) ? ' is-none' : ''; ?>"><?php echo esc_html( ! empty( $evaluation['opens_at'] ) ? date_i18n( 'l j F Y · H:i', strtotime( (string) $evaluation['opens_at'] ) ) : __( 'Date non planifiée', 'logieval-secure' ) ); ?></span>
				<?php endif; ?>
				<h3><a href="<?php echo esc_url( self::evaluation_url( $id ) ); ?>"><?php echo esc_html( $evaluation['title'] ); ?></a></h3>
				<div class="les-eval-card-meta">
					<?php self::render_status_badge( $workflow ); ?>
					<?php if ( ! empty( $evaluation['is_shared'] ) ) : ?><span class="les-shared-badge"><?php esc_html_e( 'Partagée', 'logieval-secure' ); ?></span><?php endif; ?>
					<?php if ( ! empty( $options['in_library'] ) ) : ?><span class="les-library-badge"><?php esc_html_e( 'Dans ma bibliothèque', 'logieval-secure' ); ?></span><?php endif; ?>
					<?php if ( ! empty( $options['shuffle_questions'] ) ) : ?><span class="les-shuffle-badge"><?php esc_html_e( 'Ordre aléatoire', 'logieval-secure' ); ?></span><?php endif; ?>
					<?php if ( ! $in_board ) : ?><span><?php echo esc_html( $classes ?: __( 'Aucune classe', 'logieval-secure' ) ); ?></span><?php endif; ?>
					<?php if ( ! $in_board && ! empty( $evaluation['opens_at'] ) ) : ?><span><?php echo esc_html( mysql2date( 'd.m.Y H:i', (string) $evaluation['opens_at'] ) ); ?></span><?php endif; ?>
					<?php if ( (int) $evaluation['created_by'] !== get_current_user_id() ) : ?><span><?php echo esc_html( sprintf( __( 'Enseignant : %s', 'logieval-secure' ), self::user_display_name( (int) $evaluation['created_by'] ) ) ); ?></span><?php endif; ?>
				</div>
				<p class="les-eval-card-progress">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: copies rendues, 2: élèves, 3: à corriger, 4: corrigées */
							__( '%1$d / %2$d copies rendues · %3$d à corriger · %4$d corrigée(s)', 'logieval-secure' ),
							$c['submitted'],
							max( $c['students'], $c['submitted'] ),
							$c['to_grade'],
							$c['graded']
						)
					);
					?>
					<?php if ( $c['blocked'] ) : ?><strong class="les-alert-badge is-critical"><?php echo esc_html( sprintf( _n( '%d élève bloqué', '%d élèves bloqués', $c['blocked'], 'logieval-secure' ), $c['blocked'] ) ); ?></strong><?php endif; ?>
				</p>
			</div>
			<div class="les-eval-card-actions">
				<a class="button button-primary" href="<?php echo esc_url( self::evaluation_url( $id, $workflow['step'] ) ); ?>"><?php echo esc_html( $workflow['next_label'] ); ?> →</a>
				<details class="les-more-actions">
					<summary><?php esc_html_e( 'Autres actions', 'logieval-secure' ); ?></summary>
					<div class="les-more-actions-menu">
						<a href="<?php echo esc_url( self::app_url( 'preview', array( 'evaluation_id' => $id ) ) ); ?>"><?php esc_html_e( 'Aperçu élève', 'logieval-secure' ); ?></a>
						<?php self::render_post_button( 'logieval_secure_duplicate_evaluation', $id, __( 'Dupliquer', 'logieval-secure' ) ); ?>
						<?php self::render_post_button( 'logieval_secure_toggle_library', $id, ! empty( $options['in_library'] ) ? __( 'Retirer de ma bibliothèque', 'logieval-secure' ) : __( 'Ajouter à ma bibliothèque', 'logieval-secure' ) ); ?>
						<?php self::render_post_button( 'logieval_secure_toggle_share', $id, ! empty( $evaluation['is_shared'] ) ? __( 'Retirer du partage', 'logieval-secure' ) : __( 'Partager avec les enseignants', 'logieval-secure' ) ); ?>
						<?php self::render_post_button( 'logieval_secure_delete_evaluation', $id, __( 'Supprimer', 'logieval-secure' ), __( 'Placer cette évaluation dans la corbeille ? Rien n’est effacé : vous pourrez la restaurer ensuite.', 'logieval-secure' ), 'is-danger' ); ?>
					</div>
				</details>
			</div>
		</article>
		<?php
	}

	private static function render_post_button( string $action, int $evaluation_id, string $label, string $confirm = '', string $class = '' ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" <?php echo $confirm ? 'onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : ''; ?>>
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<?php self::frontend_redirect_field( array() ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation_id ); ?>">
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Page d'une évaluation
	 * ------------------------------------------------------------------- */

	private static function render_evaluation_page( array $evaluation, string $step ): void {
		$id       = (int) $evaluation['id'];
		$workflow = self::evaluation_workflow( $evaluation );
		$c        = $workflow['counts'];
		$steps    = self::evaluation_steps();
		$badges   = array(
			'prepare' => 'draft' === $workflow['key'] ? '!' : '✓',
			'run'     => $c['blocked'] ? sprintf( '%d 🔒', $c['blocked'] ) : ( $c['in_progress'] ? (string) $c['in_progress'] : '' ),
			'grade'   => $c['to_grade'] ? (string) $c['to_grade'] : '',
			'results' => $c['graded'] ? (string) $c['graded'] : '',
		);
		self::$embedded_in_evaluation = true;
		?>
		<div class="les-eval-page">
			<p class="les-breadcrumb"><a href="<?php echo esc_url( self::app_url( 'home' ) ); ?>">← <?php esc_html_e( 'Mes évaluations', 'logieval-secure' ); ?></a></p>
			<header class="les-eval-header">
				<div>
					<h2><?php echo esc_html( $evaluation['title'] ); ?></h2>
					<div class="les-eval-card-meta">
						<?php self::render_status_badge( $workflow ); ?>
						<span><?php echo esc_html( self::evaluation_group_names( $evaluation ) ?: __( 'Aucune classe', 'logieval-secure' ) ); ?></span>
						<span><?php echo esc_html( sprintf( _n( '%d question', '%d questions', $c['questions'], 'logieval-secure' ), $c['questions'] ) ); ?> · <?php echo esc_html( self::format_points( (float) $evaluation['total_points'] ) ); ?> pts</span>
						<?php if ( ! empty( $evaluation['opens_at'] ) || ! empty( $evaluation['closes_at'] ) ) : ?>
							<span><?php echo esc_html( trim( ( $evaluation['opens_at'] ? mysql2date( 'd.m.Y H:i', (string) $evaluation['opens_at'] ) : '…' ) . ' → ' . ( $evaluation['closes_at'] ? mysql2date( 'd.m.Y H:i', (string) $evaluation['closes_at'] ) : '…' ) ) ); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="les-eval-header-actions">
					<a class="button les-button-secondary" href="<?php echo esc_url( self::app_url( 'preview', array( 'evaluation_id' => $id ) ) ); ?>"><?php esc_html_e( 'Aperçu élève', 'logieval-secure' ); ?></a>
				</div>
			</header>
			<nav class="les-steps" aria-label="<?php esc_attr_e( 'Étapes de l’évaluation', 'logieval-secure' ); ?>">
				<?php $n = 0; foreach ( $steps as $key => $label ) : $n++; ?>
					<a class="les-step <?php echo $key === $step ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::evaluation_url( $id, $key ) ); ?>">
						<span class="les-step-num"><?php echo esc_html( $n ); ?></span>
						<span class="les-step-label"><?php echo esc_html( $label ); ?></span>
						<?php if ( '' !== $badges[ $key ] ) : ?><span class="les-step-badge is-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $badges[ $key ] ); ?></span><?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php if ( $workflow['step'] !== $step ) : ?>
				<div class="les-next-action">
					<span><?php esc_html_e( 'Prochaine étape :', 'logieval-secure' ); ?></span>
					<a class="button button-primary" href="<?php echo esc_url( self::evaluation_url( $id, $workflow['step'] ) ); ?>"><?php echo esc_html( $workflow['next_label'] ); ?> →</a>
				</div>
			<?php endif; ?>
			<div class="les-step-content">
				<?php
				switch ( $step ) {
					case 'run':
						self::render_step_run( $evaluation, $workflow );
						break;
					case 'grade':
						self::render_step_grade( $evaluation );
						break;
					case 'results':
						self::render_step_results( $evaluation );
						break;
					default:
						self::render_step_prepare( $evaluation, $workflow );
						break;
				}
				// LogiOne : fiche LogiNotes liée, relevé de progression et publication des résultats.
				if ( class_exists( 'LogiPilot_Links' ) ) {
					$lp_return = self::evaluation_url( (int) $evaluation['id'], $step );
					if ( in_array( $step, array( 'prepare', 'results' ), true ) ) {
						LogiPilot_Links::render_exam_link_box( $evaluation, $lp_return );
					}
					if ( in_array( $step, array( 'prepare', 'grade' ), true ) ) {
						LogiPilot_Snapshots::render_panel( 'exam', (int) $evaluation['id'], $lp_return );
					}
					if ( 'results' === $step ) {
						LogiPilot_Publisher::render_publish_box( $evaluation, $lp_return );
					}
				}
				?>
			</div>
		</div>
		<?php
		self::$embedded_in_evaluation = false;
	}

	private static function render_step_prepare( array $evaluation, array $workflow ): void {
		if ( isset( $_GET['saved'] ) && 'draft' === sanitize_key( wp_unslash( $_GET['saved'] ) ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Brouillon enregistré. Aucun code élève n’a été créé et les élèves n’y ont pas accès. Quand l’évaluation est prête, cliquez sur « Publier la version définitive ».', 'logieval-secure' ) . '</p></div>';
		} elseif ( isset( $_GET['saved'] ) ) {
			$new_codes = absint( $_GET['new_codes'] ?? 0 );
			echo '<div class="notice notice-success inline"><p>' . esc_html( __( 'Évaluation enregistrée.', 'logieval-secure' ) . ( $new_codes ? ' ' . sprintf( _n( '%d code élève a été créé automatiquement.', '%d codes élèves ont été créés automatiquement.', $new_codes, 'logieval-secure' ), $new_codes ) : '' ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['gen_created'] ) ) {
			$n = absint( $_GET['gen_created'] );
			echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( _n( '%d question créée avec l’IA a été ajoutée à la banque et à cette évaluation.', '%d questions créées avec l’IA ont été ajoutées à la banque et à cette évaluation.', $n, 'logieval-secure' ), $n ) ) . '</p></div>';
		}
		if ( 'draft' === (string) $evaluation['status'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Brouillon : les élèves n’y ont pas accès. Choisissez la classe et les dates, puis cliquez sur « Publier la version définitive » : les codes élèves seront alors créés automatiquement.', 'logieval-secure' ) . '</p></div>';
		}
		if ( ! empty( $evaluation['id'] ) ) {
			self::render_evaluation_export_buttons( $evaluation );
		}
		self::render_create_workspace( $evaluation, self::get_questions(), LogiEval_Secure_Sensei::get_courses(), LogiEval_Secure_Sensei::get_groups() );
	}

	private static function render_step_run( array $evaluation, array $workflow ): void {
		$id      = (int) $evaluation['id'];
		$created = 'published' === (string) $evaluation['status'] ? self::ensure_codes_for_evaluation( $id ) : 0;
		$codes   = self::codes_count_for_evaluation( $id );
		$c       = $workflow['counts'];
		// Plan B en cas de panne : la version papier reste à portée de main pendant la passation.
		self::render_evaluation_export_buttons( $evaluation, 'eleve' );
		?>
		<section class="les-panel les-codes-panel">
			<h3><?php esc_html_e( 'Codes élèves', 'logieval-secure' ); ?></h3>
			<?php if ( isset( $_GET['codes'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Nouveaux codes générés pour toute la classe. Imprimez le nouveau PDF : les anciens codes ne fonctionnent plus.', 'logieval-secure' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'published' !== (string) $evaluation['status'] ) : ?>
				<p class="les-message is-error"><?php esc_html_e( 'L’évaluation est encore en brouillon : enregistrez-la à l’étape « Préparer » pour créer les codes.', 'logieval-secure' ); ?></p>
			<?php elseif ( ! $codes ) : ?>
				<p class="les-message is-error"><?php esc_html_e( 'Aucun élève trouvé : choisissez une classe ou des élèves à l’étape « Préparer ».', 'logieval-secure' ); ?></p>
			<?php else : ?>
				<p>
					<?php echo esc_html( sprintf( _n( '%d code unique a été créé automatiquement (un par élève).', '%d codes uniques ont été créés automatiquement (un par élève).', $codes, 'logieval-secure' ), $codes ) ); ?>
					<?php if ( $created ) : ?><strong><?php echo esc_html( sprintf( _n( ' %d nouvel élève vient d’être ajouté.', ' %d nouveaux élèves viennent d’être ajoutés.', $created, 'logieval-secure' ), $created ) ); ?></strong><?php endif; ?>
				</p>
				<div class="les-actions">
					<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<input type="hidden" name="action" value="logieval_secure_export_codes_pdf">
						<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $id ); ?>">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Imprimer les codes (PDF)', 'logieval-secure' ); ?></button>
					</form>
					<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Générer de nouveaux codes pour toute la classe ? Les codes déjà distribués ne fonctionneront plus.', 'logieval-secure' ) ); ?>');">
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<?php self::frontend_redirect_field( array() ); ?>
						<input type="hidden" name="action" value="logieval_secure_generate_codes">
						<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $id ); ?>">
						<button type="submit" class="button les-button-secondary"><?php esc_html_e( 'Régénérer tous les codes', 'logieval-secure' ); ?></button>
					</form>
				</div>
				<p class="description"><?php esc_html_e( 'Le PDF est toujours à jour : il reprend les dates actuelles et les élèves ajoutés à la classe depuis.', 'logieval-secure' ); ?></p>
			<?php endif; ?>
		</section>
		<section class="les-panel" data-les-monitoring data-evaluation-id="<?php echo esc_attr( $id ); ?>" data-refresh-interval="10000">
			<h3><?php esc_html_e( 'Suivi en direct', 'logieval-secure' ); ?></h3>
			<?php if ( isset( $_GET['unlocked'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Élève débloqué.', 'logieval-secure' ); ?></p></div>
			<?php endif; ?>
			<p class="description" data-les-monitoring-status><?php esc_html_e( 'Actualisation automatique toutes les 10 secondes lorsque cette page est visible.', 'logieval-secure' ); ?></p>
			<div data-les-monitoring-body><?php self::render_monitoring_body( $evaluation ); ?></div>
		</section>
		<?php self::render_security_log_panel( $evaluation ); ?>
		<?php
	}

	private static function render_security_log_panel( array $evaluation ): void {
		$logs = LogiEval_Secure_Security::get_logs_for_evaluation( (int) $evaluation['id'] );
		?>
		<details class="les-panel les-security-log-panel">
			<summary><?php echo esc_html( sprintf( __( 'Registre de sécurité (%d événements)', 'logieval-secure' ), count( $logs ) ) ); ?></summary>
			<?php if ( empty( $logs ) ) : ?>
				<p class="les-message"><?php esc_html_e( 'Aucun événement de sécurité pour cette évaluation.', 'logieval-secure' ); ?></p>
			<?php else : ?>
				<div class="les-table-scroll">
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Élève', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Événement', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Niveau', 'logieval-secure' ); ?></th></tr></thead><tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr><td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', (string) $log['created_at'] ) ); ?></td><td><?php echo esc_html( self::user_display_name( (int) $log['user_id'] ) ); ?></td><td><?php echo esc_html( self::security_event_label( (string) $log['event_type'] ) ); ?></td><td><?php echo esc_html( self::security_severity_label( (string) $log['severity'] ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>
		</details>
		<?php
	}

	private static function render_step_grade( array $evaluation ): void {
		$id       = (int) $evaluation['id'];
		$mode     = 'question' === sanitize_key( wp_unslash( $_GET['correction_mode'] ?? '' ) ) ? 'question' : 'student';
		$attempts = array_values( array_filter( self::get_attempts( $id ), static fn( $a ) => self::attempt_is_cheating_cancelled( $a ) || ! empty( $a['submitted_at'] ) || in_array( (string) $a['status'], array( 'submitted', 'submitted_locked', 'graded' ), true ) ) );
		usort(
			$attempts,
			static function ( $a, $b ) use ( $evaluation ) {
				$ca = self::group_label_for_user( $evaluation, (int) $a['user_id'] );
				$cb = self::group_label_for_user( $evaluation, (int) $b['user_id'] );
				return strnatcasecmp( $ca, $cb ) ?: strnatcasecmp( self::user_display_name( (int) $a['user_id'] ), self::user_display_name( (int) $b['user_id'] ) );
			}
		);
		$in_progress = count( array_filter( self::get_attempts( $id ), static fn( $a ) => ! self::attempt_is_cheating_cancelled( $a ) && empty( $a['submitted_at'] ) && ! in_array( (string) $a['status'], array( 'submitted', 'submitted_locked', 'graded' ), true ) ) );
		$mode_url    = static fn( string $m ) => self::evaluation_url( $id, 'grade', array( 'correction_mode' => 'question' === $m ? 'question' : null ) );
		?>
		<section class="les-panel les-grade-step">
			<div class="les-grade-toolbar">
				<div class="les-segmented" role="tablist" aria-label="<?php esc_attr_e( 'Mode de correction', 'logieval-secure' ); ?>">
					<a role="tab" class="<?php echo 'student' === $mode ? 'is-active' : ''; ?>" href="<?php echo esc_url( $mode_url( 'student' ) ); ?>"><?php esc_html_e( 'Par élève', 'logieval-secure' ); ?></a>
					<a role="tab" class="<?php echo 'question' === $mode ? 'is-active' : ''; ?>" href="<?php echo esc_url( $mode_url( 'question' ) ); ?>"><?php esc_html_e( 'Par question', 'logieval-secure' ); ?></a>
				</div>
				<?php if ( self::ai_grading_is_enabled() ) : ?>
					<?php
					// Une fois la pré-correction faite, le panneau IA se réduit à une ligne (niveau d'exigence) ; un clic le rouvre.
					$ai_done   = self::evaluation_ai_graded_count( $id );
					$ai_levels = self::ai_strictness_levels();
					$ai_level  = self::evaluation_ai_strictness( $evaluation );
					?>
					<details class="les-ai-bulk-toolbar les-ai-panel"<?php echo $ai_done ? '' : ' open'; ?> data-les-ai-panel>
						<summary class="les-ai-panel-summary">
							<span class="les-ai-panel-title">🤖 <?php esc_html_e( 'Pré-correction IA', 'logieval-secure' ); ?></span>
							<span class="les-ai-panel-level"><?php esc_html_e( 'Niveau d’exigence :', 'logieval-secure' ); ?> <strong data-les-ai-panel-level><?php echo esc_html( $ai_levels[ $ai_level ]['label'] ?? '' ); ?></strong></span>
							<?php if ( $ai_done ) : ?><span class="les-ai-panel-count"><?php echo esc_html( sprintf( _n( '%d copie pré-corrigée', '%d copies pré-corrigées', $ai_done, 'logieval-secure' ), $ai_done ) ); ?></span><?php endif; ?>
							<span class="les-ai-panel-toggle"><span class="les-ai-panel-open"><?php esc_html_e( 'Afficher', 'logieval-secure' ); ?></span><span class="les-ai-panel-close"><?php esc_html_e( 'Réduire', 'logieval-secure' ); ?></span></span>
						</summary>
						<div class="les-ai-panel-body">
							<?php self::render_ai_strictness_slider( $evaluation ); ?>
							<?php self::render_ai_provider_choice( 'grading' ); ?>
							<button type="button" class="button les-button-secondary" data-les-ai-grade-bulk data-evaluation-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Pré-corriger toutes les questions et toutes les copies', 'logieval-secure' ); ?></button>
							<span class="les-ai-grade-status" aria-live="polite"></span>
							<p class="description"><?php esc_html_e( 'Les propositions IA sont enregistrées avec leur analyse. Elles ne remplacent jamais la décision de l’enseignant et n’apparaissent pas dans le PDF élève.', 'logieval-secure' ); ?></p>
						</div>
					</details>
				<?php elseif ( self::ai_grading_allowed() ) : ?>
					<p class="description les-ai-hint"><?php esc_html_e( 'Pré-correction IA disponible : ajoutez votre propre clé API dans Réglages › Ma pré-correction IA pour l’activer.', 'logieval-secure' ); ?></p>
				<?php endif; ?>
				<?php self::render_finalize_all_button( $evaluation ); ?>
			</div>
			<?php self::render_grade_notices( $evaluation ); ?>
			<?php if ( $in_progress ) : ?>
				<p class="description"><?php echo esc_html( sprintf( _n( '%d élève n’a pas encore rendu sa copie : elle apparaîtra ici après l’envoi.', '%d élèves n’ont pas encore rendu leur copie : elles apparaîtront ici après l’envoi.', $in_progress, 'logieval-secure' ), $in_progress ) ); ?></p>
			<?php endif; ?>
			<?php if ( empty( $attempts ) ) : ?>
				<p class="les-message"><?php esc_html_e( 'Aucune copie rendue pour le moment.', 'logieval-secure' ); ?></p>
			<?php elseif ( 'question' === $mode ) : ?>
				<?php
				$open_attempts = array_values( array_filter( $attempts, static fn( $a ) => ! self::attempt_is_cheating_cancelled( $a ) && ! self::attempt_correction_is_finished( $a, self::get_result_by_attempt( (int) $a['id'] ) ) ) );
				$done          = count( $attempts ) - count( $open_attempts );
				if ( $done ) :
					?>
					<p class="description les-finished-note"><?php echo esc_html( sprintf( _n( '%d copie terminée ou annulée n’apparaît pas ici. Pour la modifier, passez en mode « Par élève » et cliquez sur « Rouvrir la correction ».', '%d copies terminées ou annulées n’apparaissent pas ici. Pour les modifier, passez en mode « Par élève » et cliquez sur « Rouvrir la correction ».', $done, 'logieval-secure' ), $done ) ); ?></p>
				<?php endif; ?>
				<?php if ( empty( $open_attempts ) ) : ?>
					<p class="les-message"><?php esc_html_e( 'Toutes les copies sont corrigées.', 'logieval-secure' ); ?></p>
				<?php else : ?>
					<?php self::render_question_by_question_correction( $evaluation, $open_attempts ); ?>
				<?php endif; ?>
			<?php else : ?>
				<?php self::render_grade_by_student( $evaluation, $attempts ); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_grade_notices( array $evaluation ): void {
		if ( isset( $_GET['correction_incomplete'] ) ) {
			self::render_incomplete_correction_alert( max( 1, absint( $_GET['correction_incomplete'] ) ), absint( $_GET['incomplete_attempt'] ?? 0 ), $evaluation );
		} elseif ( isset( $_GET['graded'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Correction enregistrée.', 'logieval-secure' ) . '</p></div>';
		} elseif ( isset( $_GET['question_saved'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Progression enregistrée : les corrections affichées ont été sauvegardées.', 'logieval-secure' ) . '</p></div>';
		}
		if ( isset( $_GET['finalized'] ) ) {
			$done = absint( $_GET['finalized'] );
			$left = absint( $_GET['not_finalized'] ?? 0 );
			echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( _n( '%d copie finalisée.', '%d copies finalisées.', $done, 'logieval-secure' ), $done ) . ( $left ? ' ' . sprintf( _n( '%d copie n’a pas pu l’être (points manquants) : corrigez-la manuellement.', '%d copies n’ont pas pu l’être (points manquants) : corrigez-les manuellement.', $left, 'logieval-secure' ), $left ) : '' ) ) . '</p></div>';
		}
	}

	/** Correction « Par élève » : liste des copies à gauche, une seule copie affichée, élève précédent / suivant. */
	private static function render_grade_by_student( array $evaluation, array $attempts ): void {
		$id        = (int) $evaluation['id'];
		$ids       = array_map( static fn( $a ) => (int) $a['id'], $attempts );
		$requested = absint( $_GET['attempt'] ?? 0 ) ?: absint( $_GET['open_attempt'] ?? 0 ) ?: absint( $_GET['incomplete_attempt'] ?? 0 );
		$index     = $requested ? array_search( $requested, $ids, true ) : false;
		if ( false === $index ) {
			$index = 0;
			foreach ( $attempts as $i => $a ) {
				if ( self::attempt_needs_correction( $a, self::get_result_by_attempt( (int) $a['id'] ) ) ) {
					$index = $i;
					break;
				}
			}
		}
		$current = $attempts[ $index ];
		$prev    = $attempts[ $index - 1 ] ?? null;
		$next    = $attempts[ $index + 1 ] ?? null;
		// « Finaliser et suivant » mène à la prochaine copie encore à corriger (après celle-ci, sinon depuis le début).
		$next_todo = 0;
		$count     = count( $attempts );
		for ( $k = 1; $k < $count; $k++ ) {
			$a = $attempts[ ( $index + $k ) % $count ];
			if ( self::attempt_needs_correction( $a, self::get_result_by_attempt( (int) $a['id'] ) ) ) {
				$next_todo = (int) $a['id'];
				break;
			}
		}
		$url    = static fn( array $a ) => self::evaluation_url( $id, 'grade', array( 'attempt' => (int) $a['id'] ) ) . '#les-grade-copy';
		$result = self::get_result_by_attempt( (int) $current['id'] );
		$logs   = LogiEval_Secure_Security::get_logs_for_attempt( (int) $current['id'] );
		$class  = '';
		?>
		<div class="les-grade-layout">
			<nav class="les-grade-roster" aria-label="<?php esc_attr_e( 'Copies', 'logieval-secure' ); ?>">
				<?php foreach ( $attempts as $i => $a ) : ?>
					<?php
					$r     = self::get_result_by_attempt( (int) $a['id'] );
					$label = self::group_label_for_user( $evaluation, (int) $a['user_id'] );
					if ( $label !== $class ) {
						$class = $label;
						echo '<span class="les-roster-class">' . esc_html( $class ) . '</span>';
					}
					if ( self::attempt_is_cheating_cancelled( $a ) ) {
						$state = array( 'cancelled', '⛔', self::pdf_optional_number( self::CHEATING_GRADE ) );
					} elseif ( self::attempt_correction_is_finished( $a, $r ) ) {
						$state = array( 'done', '✓', self::pdf_optional_number( $r['final_grade'] ?? null ) );
					} elseif ( $r && 'correction_in_progress' === (string) $r['status'] ) {
						$state = array( 'progress', '◐', '' );
					} else {
						$state = array( 'todo', '●', '' );
					}
					?>
					<a class="les-roster-item is-<?php echo esc_attr( $state[0] ); ?> <?php echo $i === $index ? 'is-current' : ''; ?>" href="<?php echo esc_url( $url( $a ) ); ?>">
						<span class="les-roster-icon" aria-hidden="true"><?php echo esc_html( $state[1] ); ?></span>
						<span class="les-roster-name"><?php echo esc_html( self::user_display_name( (int) $a['user_id'] ) ); ?></span>
						<?php if ( '' !== $state[2] ) : ?><span class="les-roster-grade"><?php echo esc_html( $state[2] ); ?></span><?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="les-grade-copy" id="les-grade-copy">
				<div class="les-grade-copy-head">
					<div>
						<h3><?php echo esc_html( self::user_display_name( (int) $current['user_id'] ) ); ?></h3>
						<span class="description"><?php echo esc_html( self::group_label_for_user( $evaluation, (int) $current['user_id'] ) ); ?> · <?php echo esc_html( sprintf( __( 'Copie %1$d sur %2$d', 'logieval-secure' ), $index + 1, $count ) ); ?> · <?php echo esc_html( self::export_status_label( $current, $result ) ); ?></span>
					</div>
					<div class="les-grade-nav">
						<?php if ( $prev ) : ?><a class="button les-button-secondary" data-les-guarded-link href="<?php echo esc_url( $url( $prev ) ); ?>">← <?php esc_html_e( 'Élève précédent', 'logieval-secure' ); ?></a><?php endif; ?>
						<?php if ( $next ) : ?><a class="button les-button-secondary" data-les-guarded-link href="<?php echo esc_url( $url( $next ) ); ?>"><?php esc_html_e( 'Élève suivant', 'logieval-secure' ); ?> →</a><?php endif; ?>
						<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=logieval_secure_export_attempt_pdf&attempt_id=' . absint( $current['id'] ) ), self::NONCE_ACTION ) ); ?>"><?php esc_html_e( 'PDF élève', 'logieval-secure' ); ?></a>
					</div>
				</div>
				<?php self::render_attempt_control_notice(); ?>
				<?php if ( self::attempt_is_cheating_cancelled( $current ) ) : ?>
					<?php self::render_cheating_cancelled_notice( $current, $logs ); ?>
					<?php self::render_attempt_control_menu( $current, $logs ); ?>
				<?php else : ?>
					<?php self::render_attempt_control_menu( $current, $logs ); ?>
					<?php self::render_attempt_unlock_action( $current ); ?>
					<?php self::render_grade_form( $current, $evaluation, $result, $logs, true, $next_todo ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_step_results( array $evaluation ): void {
		$id      = (int) $evaluation['id'];
		$by_class = array();
		foreach ( self::get_attempts( $id ) as $attempt ) {
			$by_class[ self::group_label_for_user( $evaluation, (int) $attempt['user_id'] ) ][] = $attempt;
		}
		uksort( $by_class, 'strnatcasecmp' );
		?>
		<section class="les-panel">
			<h3><?php esc_html_e( 'Résultats', 'logieval-secure' ); ?></h3>
			<div class="les-actions les-results-actions">
				<a class="button button-primary" href="<?php echo esc_url( self::export_csv_url( $id ) ); ?>"><?php esc_html_e( 'Exporter tout (CSV)', 'logieval-secure' ); ?></a>
				<a class="button les-button-secondary" href="<?php echo esc_url( self::pdf_zip_url( $id ) ); ?>"><?php esc_html_e( 'Tous les PDF élèves (ZIP)', 'logieval-secure' ); ?></a>
			</div>
			<?php if ( empty( $by_class ) ) : ?>
				<p class="les-message"><?php esc_html_e( 'Aucune copie pour le moment.', 'logieval-secure' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $by_class as $class => $attempts ) : ?>
				<?php
				usort( $attempts, static fn( $a, $b ) => strnatcasecmp( self::user_display_name( (int) $a['user_id'] ), self::user_display_name( (int) $b['user_id'] ) ) );
				$grades = array();
				?>
				<div class="les-results-class">
					<div class="les-results-class-head">
						<h4><?php echo esc_html( $class ); ?> <span><?php echo esc_html( sprintf( _n( '%d copie', '%d copies', count( $attempts ), 'logieval-secure' ), count( $attempts ) ) ); ?></span></h4>
						<div class="les-actions">
							<a class="button les-button-secondary" href="<?php echo esc_url( self::export_csv_url( $id, $class ) ); ?>"><?php esc_html_e( 'CSV de la classe', 'logieval-secure' ); ?></a>
							<a class="button les-button-secondary" href="<?php echo esc_url( self::pdf_zip_url( $id, (string) $class ) ); ?>"><?php esc_html_e( 'PDF de la classe (ZIP)', 'logieval-secure' ); ?></a>
						</div>
					</div>
					<div class="les-table-scroll">
						<table class="widefat striped les-results-table">
							<thead><tr><th><?php esc_html_e( 'Élève', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Statut', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Points', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Note', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Copie', 'logieval-secure' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $attempts as $attempt ) : $result = self::get_result_by_attempt( (int) $attempt['id'] ); $cancelled = self::attempt_is_cheating_cancelled( $attempt ); $finished = self::attempt_correction_is_finished( $attempt, $result ); $grade = $cancelled ? self::CHEATING_GRADE : ( $finished && $result ? (float) $result['final_grade'] : null ); if ( null !== $grade ) { $grades[] = $grade; } ?>
									<tr class="<?php echo $cancelled ? 'les-row-cancelled' : ''; ?>">
										<td><?php echo esc_html( self::user_display_name( (int) $attempt['user_id'] ) ); ?></td>
										<td><?php echo esc_html( self::export_status_label( $attempt, $result ) ); ?></td>
										<td><?php echo esc_html( $cancelled ? '—' : ( $result ? self::format_points( (float) $result['final_total'] ) . ' / ' . self::format_points( (float) $evaluation['total_points'] ) : '-' ) ); ?></td>
										<td><strong><?php echo esc_html( null !== $grade ? self::pdf_optional_number( $grade ) : '-' ); ?></strong></td>
										<td><a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=logieval_secure_export_attempt_pdf&attempt_id=' . absint( $attempt['id'] ) ), self::NONCE_ACTION ) ); ?>"><?php esc_html_e( 'PDF élève', 'logieval-secure' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if ( $grades ) : ?>
						<p class="les-results-summary"><?php echo esc_html( sprintf( __( 'Moyenne des notes finalisées : %1$s (%2$d copie(s))', 'logieval-secure' ), number_format_i18n( array_sum( $grades ) / count( $grades ), 2 ), count( $grades ) ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}
}
