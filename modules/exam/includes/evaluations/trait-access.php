<?php
/**
 * LogiEval_Secure_Evaluations — partie « access ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Access {
	/* ---------------------------------------------------------------------
	 * Multi-enseignants : propriété et partage
	 * ------------------------------------------------------------------- */

	public static function current_user_is_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	/** L'administrateur voit tout, sauf s'il a choisi « Mes évaluations uniquement ». */
	public static function admin_sees_all(): bool {
		if ( ! self::current_user_is_admin() ) {
			return false;
		}
		static $scope = null;
		if ( null === $scope ) {
			$user_id = get_current_user_id();
			if ( isset( $_GET['logiexam_scope'] ) ) {
				$scope = 'mine' === sanitize_key( wp_unslash( $_GET['logiexam_scope'] ) ) ? 'mine' : 'all';
				update_user_meta( $user_id, 'logieval_secure_scope', $scope );
			} else {
				$scope = 'mine' === get_user_meta( $user_id, 'logieval_secure_scope', true ) ? 'mine' : 'all';
			}
		}
		return 'all' === $scope;
	}

	public static function can_manage_evaluation( ?array $evaluation ): bool {
		if ( ! $evaluation ) {
			return false;
		}
		return self::current_user_is_admin() || (int) ( $evaluation['created_by'] ?? 0 ) === get_current_user_id();
	}

	/** Lecture seule : ses propres évaluations, ou une évaluation partagée par un collègue. */
	public static function can_view_evaluation( ?array $evaluation ): bool {
		if ( self::can_manage_evaluation( $evaluation ) ) {
			return true;
		}
		return $evaluation && ! empty( $evaluation['is_shared'] ) && 'deleted' !== (string) ( $evaluation['status'] ?? '' );
	}

	public static function can_manage_question( ?array $question ): bool {
		if ( ! $question ) {
			return false;
		}
		return self::current_user_is_admin() || (int) ( $question['created_by'] ?? 0 ) === get_current_user_id();
	}

	private static function require_managed_evaluation( int $evaluation_id ): array {
		$evaluation = self::get_evaluation( $evaluation_id );
		if ( ! self::can_manage_evaluation( $evaluation ) ) {
			wp_die( esc_html__( 'Évaluation introuvable ou appartenant à un autre enseignant.', 'logieval-secure' ), '', array( 'response' => 403 ) );
		}
		return $evaluation;
	}

	private static function current_user_can_manage_attempt( ?array $attempt ): bool {
		return $attempt && self::can_manage_evaluation( self::get_evaluation( (int) $attempt['evaluation_id'] ) );
	}

	private static function require_managed_attempt( int $attempt_id ): array {
		$attempt = self::get_attempt( $attempt_id );
		if ( ! self::current_user_can_manage_attempt( $attempt ) ) {
			wp_die( esc_html__( 'Copie introuvable ou appartenant à une évaluation d’un autre enseignant.', 'logieval-secure' ), '', array( 'response' => 403 ) );
		}
		return $attempt;
	}

	public static function user_display_name( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : null;
		return $user ? (string) $user->display_name : __( 'Enseignant inconnu', 'logieval-secure' );
	}

	private static function render_scope_switch(): void {
		if ( ! self::current_user_is_admin() ) {
			return;
		}
		$all = self::admin_sees_all();
		?>
		<div class="les-scope-switch">
			<span><?php esc_html_e( 'Affichage administrateur :', 'logieval-secure' ); ?></span>
			<a class="<?php echo $all ? '' : 'is-active'; ?>" href="<?php echo esc_url( add_query_arg( 'logiexam_scope', 'mine' ) ); ?>"><?php esc_html_e( 'Mes évaluations', 'logieval-secure' ); ?></a>
			<a class="<?php echo $all ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'logiexam_scope', 'all' ) ); ?>"><?php esc_html_e( 'Tous les enseignants', 'logieval-secure' ); ?></a>
		</div>
		<?php
	}

	public static function get_shared_evaluations( string $search = '' ): array {
		global $wpdb;
		$t   = LogiEval_Secure_Database::tables();
		$sql = "SELECT e.*, (SELECT COUNT(*) FROM {$t['eval_questions']} eq WHERE eq.evaluation_id=e.id) AS question_count FROM {$t['evaluations']} e WHERE e.is_shared=1 AND e.status <> 'deleted'";
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$sql .= $wpdb->prepare( ' AND (e.title LIKE %s OR e.description LIKE %s)', $like, $like );
		}
		return $wpdb->get_results( $sql . ' ORDER BY e.shared_at DESC, e.id DESC', ARRAY_A );
	}

	/* ---------------------------------------------------------------------
	 * Options LogiOne d'une évaluation (ordre aléatoire, « Ma bibliothèque »).
	 * Rangées dans une table propre à LogiOne : la table historique des évaluations n'est jamais modifiée.
	 * ------------------------------------------------------------------- */

	private static $exam_options_cache = null;

	public static function exam_options_table(): string {
		global $wpdb;
		return class_exists( 'LogiPilot_Install' ) ? LogiPilot_Install::table( 'exam_options' ) : $wpdb->prefix . 'logipilot_exam_options';
	}

	public static function exam_options( int $evaluation_id ): array {
		global $wpdb;
		if ( null === self::$exam_options_cache ) {
			self::$exam_options_cache = array();
			$table = self::exam_options_table();
			if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				foreach ( (array) $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ) as $row ) {
					self::$exam_options_cache[ (int) $row['evaluation_id'] ] = $row;
				}
			}
		}
		$row = self::$exam_options_cache[ $evaluation_id ] ?? array();
		return array(
			'shuffle_questions' => (int) ( $row['shuffle_questions'] ?? 0 ),
			'in_library'        => (int) ( $row['in_library'] ?? 0 ),
			'library_at'        => $row['library_at'] ?? null,
			'progress_deadline' => $row['progress_deadline'] ?? null,
		);
	}

	public static function set_exam_options( int $evaluation_id, array $values ): void {
		global $wpdb;
		if ( ! $evaluation_id ) {
			return;
		}
		$table = self::exam_options_table();
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) && class_exists( 'LogiPilot_Install' ) ) {
			LogiPilot_Install::create_exam_options_table();
		}
		$current = self::exam_options( $evaluation_id );
		$row     = array_merge( $current, array_intersect_key( $values, $current ) );
		if ( ! empty( $row['in_library'] ) && empty( $current['in_library'] ) && ! array_key_exists( 'library_at', $values ) ) {
			$row['library_at'] = current_time( 'mysql' );
		}
		if ( empty( $row['in_library'] ) ) {
			$row['library_at'] = null;
		}
		$evaluation = self::get_evaluation( $evaluation_id );
		$wpdb->replace(
			$table,
			array(
				'evaluation_id'     => $evaluation_id,
				'owner_id'          => (int) ( $evaluation['created_by'] ?? 0 ),
				'shuffle_questions' => (int) $row['shuffle_questions'],
				'in_library'        => (int) $row['in_library'],
				'library_at'        => $row['library_at'],
				'progress_deadline' => $row['progress_deadline'],
				'updated_at'        => current_time( 'mysql' ),
			)
		);
		self::$exam_options_cache = null;
	}

	/** Évaluations de ma bibliothèque personnelle (non partagée), y compris celles retirées de « Mes évaluations ». */
	public static function get_library_evaluations( int $user_id ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, o.shuffle_questions, o.library_at, (SELECT COUNT(*) FROM {$t['eval_questions']} eq WHERE eq.evaluation_id=e.id) AS question_count FROM {$t['evaluations']} e INNER JOIN " . self::exam_options_table() . " o ON o.evaluation_id=e.id WHERE o.in_library=1 AND e.created_by=%d ORDER BY e.title ASC, e.id DESC",
				$user_id
			),
			ARRAY_A
		);
	}

	/** Classes favorites de l'enseignant : la même liste que dans LogiProgress (étoiles). */
	public static function favorite_group_ids( int $user_id = 0 ): array {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$ids     = $user_id ? get_user_meta( $user_id, 'psgpt_favorite_groups', true ) : array();
		return array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ) );
	}

	public static function ajax_toggle_favorite_group(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, '_wpnonce', false ) || ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
		}
		$group_id = absint( $_POST['group_id'] ?? 0 );
		$known    = array_map( static fn( $g ) => (int) $g->term_id, LogiEval_Secure_Sensei::get_groups() );
		if ( ! $group_id || ! in_array( $group_id, $known, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Classe introuvable.', 'logieval-secure' ) ), 400 );
		}
		$ids = self::favorite_group_ids();
		if ( in_array( $group_id, $ids, true ) ) {
			$ids = array_values( array_diff( $ids, array( $group_id ) ) );
			$fav = false;
		} else {
			$ids[] = $group_id;
			$fav   = true;
		}
		update_user_meta( get_current_user_id(), 'psgpt_favorite_groups', $ids );
		wp_send_json_success( array( 'fav' => $fav, 'count' => count( $ids ) ) );
	}

	public static function handle_toggle_library(): void {
		self::require_teacher_post();
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$evaluation = self::require_managed_evaluation( absint( $_POST['evaluation_id'] ?? 0 ) );
		$on         = empty( self::exam_options( (int) $evaluation['id'] )['in_library'] ) ? 1 : 0;
		self::set_exam_options( (int) $evaluation['id'], array( 'in_library' => $on ) );
		$back = sanitize_key( wp_unslash( $_POST['library_return'] ?? '' ) );
		if ( 'library' === $back ) {
			wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-library&library_updated=' . $on ), array( 'logiexam_view' => 'library', 'evaluation_id' => null, 'library_updated' => $on ) ) );
			exit;
		}
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-evaluations&library_updated=' . $on ), array( 'logiexam_view' => 'evaluations', 'evaluation_id' => null, 'library_updated' => $on ) ) );
		exit;
	}

	private static function library_course_label( int $course_id ): string {
		if ( ! $course_id ) {
			return __( 'Sans cours Sensei', 'logieval-secure' );
		}
		$title = get_the_title( $course_id );
		return $title ? (string) $title : sprintf( __( 'Cours n° %d', 'logieval-secure' ), $course_id );
	}

	private static function render_library_tabs( string $tab ): void {
		?>
		<nav class="les-library-tabs" aria-label="<?php esc_attr_e( 'Bibliothèques', 'logieval-secure' ); ?>">
			<a class="<?php echo 'mine' === $tab ? 'is-active' : ''; ?>" <?php echo 'mine' === $tab ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( self::app_url( 'library', array( 'library_tab' => 'mine' ) ) ); ?>"><?php esc_html_e( 'Ma bibliothèque', 'logieval-secure' ); ?> <small><?php esc_html_e( '(non partagée)', 'logieval-secure' ); ?></small></a>
			<a class="<?php echo 'shared' === $tab ? 'is-active' : ''; ?>" <?php echo 'shared' === $tab ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( self::app_url( 'library', array( 'library_tab' => 'shared' ) ) ); ?>"><?php esc_html_e( 'Bibliothèque partagée', 'logieval-secure' ); ?></a>
		</nav>
		<?php
	}

	private static function render_library_workspace(): void {
		$tab = sanitize_key( wp_unslash( $_GET['library_tab'] ?? 'mine' ) );
		$tab = 'shared' === $tab ? 'shared' : 'mine';
		self::render_library_tabs( $tab );
		if ( 'mine' === $tab ) {
			self::render_my_library();
			return;
		}
		self::render_shared_library();
	}

	/** Ma bibliothèque : uniquement mes évaluations, classées par cours Sensei, avec un champ pour choisir le cours. */
	private static function render_my_library(): void {
		$me     = get_current_user_id();
		$items  = self::get_library_evaluations( $me );
		$course = isset( $_GET['library_course'] ) ? sanitize_text_field( wp_unslash( $_GET['library_course'] ) ) : 'all';
		$groups = array();
		foreach ( $items as $item ) {
			$cid = absint( $item['sensei_course_id'] ?? 0 );
			if ( ! isset( $groups[ $cid ] ) ) {
				$groups[ $cid ] = array( 'label' => self::library_course_label( $cid ), 'items' => array() );
			}
			$groups[ $cid ]['items'][] = $item;
		}
		uasort(
			$groups,
			static function ( $a, $b ) {
				return strnatcasecmp( remove_accents( $a['label'] ), remove_accents( $b['label'] ) );
			}
		);
		if ( isset( $groups[0] ) ) { // « Sans cours » à la fin.
			$none = $groups[0];
			unset( $groups[0] );
			$groups[0] = $none;
		}
		$shown = 'all' === $course ? $groups : array_intersect_key( $groups, array( absint( $course ) => true ) );
		?>
		<section class="les-panel les-my-library">
			<h2><?php esc_html_e( 'Ma bibliothèque', 'logieval-secure' ); ?></h2>
			<p><?php esc_html_e( 'Vos évaluations mises de côté pour les réutiliser, classées par cours Sensei. Personne d’autre ne les voit. « Réutiliser » crée une nouvelle copie (questions comprises) prête à être préparée pour une nouvelle classe ; l’évaluation de la bibliothèque reste intacte.', 'logieval-secure' ); ?></p>
			<?php if ( isset( $_GET['library_updated'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html( '1' === (string) $_GET['library_updated'] ? __( 'Évaluation ajoutée à votre bibliothèque.', 'logieval-secure' ) : __( 'Évaluation retirée de votre bibliothèque.', 'logieval-secure' ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( empty( $items ) ) : ?>
				<div class="les-empty-state">
					<p><?php esc_html_e( 'Votre bibliothèque est vide. Pour y ajouter une évaluation : dans « Mes évaluations », ouvrez « Autres actions » puis « Ajouter à ma bibliothèque », ou cochez « Ajouter à ma bibliothèque (non partagée) » en modifiant l’évaluation.', 'logieval-secure' ); ?></p>
				</div>
			<?php else : ?>
				<form method="get" class="les-library-search les-library-course">
					<?php if ( is_admin() ) : ?>
						<input type="hidden" name="page" value="logiexam-library">
					<?php else : ?>
						<?php foreach ( $_GET as $k => $v ) : if ( is_array( $v ) || in_array( $k, array( 'library_course', 'library_tab', 'library_updated' ), true ) ) { continue; } ?>
							<input type="hidden" name="<?php echo esc_attr( sanitize_key( $k ) ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $v ) ) ); ?>">
						<?php endforeach; ?>
						<input type="hidden" name="logiexam_view" value="library">
					<?php endif; ?>
					<input type="hidden" name="library_tab" value="mine">
					<label for="les-library-course"><?php esc_html_e( 'Cours Sensei', 'logieval-secure' ); ?></label>
					<select id="les-library-course" name="library_course" onchange="this.form.submit()">
						<option value="all" <?php selected( $course, 'all' ); ?>><?php echo esc_html( sprintf( __( 'Tous les cours (%d évaluations)', 'logieval-secure' ), count( $items ) ) ); ?></option>
						<?php foreach ( $groups as $cid => $g ) : ?>
							<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( (string) $course, (string) $cid ); ?>><?php echo esc_html( $g['label'] . ' (' . count( $g['items'] ) . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<noscript><button type="submit" class="button les-button-secondary"><?php esc_html_e( 'Afficher', 'logieval-secure' ); ?></button></noscript>
				</form>
				<?php foreach ( $shown as $cid => $g ) : ?>
					<h3 class="les-library-course-title"><?php echo esc_html( $g['label'] ); ?> <span><?php echo esc_html( sprintf( _n( '%d évaluation', '%d évaluations', count( $g['items'] ), 'logieval-secure' ), count( $g['items'] ) ) ); ?></span></h3>
					<div class="les-table-scroll">
						<table class="widefat striped les-library-table">
							<thead><tr><th><?php esc_html_e( 'Évaluation', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Questions', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Points', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Durée', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Ajoutée le', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Actions', 'logieval-secure' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $g['items'] as $item ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $item['title'] ); ?></strong>
											<?php if ( 'deleted' === (string) $item['status'] ) : ?> <span class="les-alert-badge"><?php esc_html_e( 'retirée de Mes évaluations', 'logieval-secure' ); ?></span><?php endif; ?>
											<?php if ( ! empty( $item['shuffle_questions'] ) ) : ?> <span class="les-shuffle-badge"><?php esc_html_e( 'Ordre aléatoire', 'logieval-secure' ); ?></span><?php endif; ?>
											<?php if ( ! empty( $item['description'] ) ) : ?><br><small><?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $item['description'] ), 20 ) ); ?></small><?php endif; ?></td>
										<td><?php echo esc_html( (int) $item['question_count'] ); ?></td>
										<td><?php echo esc_html( self::format_points( (float) $item['total_points'] ) ); ?></td>
										<td><?php echo esc_html( (int) $item['duration_minutes'] ? (int) $item['duration_minutes'] . ' min' : '-' ); ?></td>
										<td><?php echo esc_html( ! empty( $item['library_at'] ) ? mysql2date( 'd.m.Y', (string) $item['library_at'] ) : '-' ); ?></td>
										<td>
											<div class="les-row-actions">
												<a class="button les-button-secondary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=logiexam&logiexam_view=preview&evaluation_id=' . absint( $item['id'] ) ) : self::frontend_url( array( 'logiexam_view' => 'preview', 'evaluation_id' => absint( $item['id'] ) ) ) ); ?>"><?php esc_html_e( 'Aperçu', 'logieval-secure' ); ?></a>
												<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( self::NONCE_ACTION ); ?><?php self::frontend_redirect_field( array( 'logiexam_view' => 'create' ) ); ?><input type="hidden" name="action" value="logieval_secure_copy_shared"><input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $item['id'] ); ?>"><button type="submit" class="button button-primary"><?php esc_html_e( 'Réutiliser', 'logieval-secure' ); ?></button></form>
												<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Retirer cette évaluation de votre bibliothèque ? Elle n’est pas supprimée.', 'logieval-secure' ) ); ?>');"><?php wp_nonce_field( self::NONCE_ACTION ); ?><?php self::frontend_redirect_field( array( 'logiexam_view' => 'library' ) ); ?><input type="hidden" name="action" value="logieval_secure_toggle_library"><input type="hidden" name="library_return" value="library"><input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $item['id'] ); ?>"><button type="submit" class="button les-button-secondary"><?php esc_html_e( 'Retirer', 'logieval-secure' ); ?></button></form>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_shared_library(): void {
		$search = sanitize_text_field( wp_unslash( $_GET['library_q'] ?? '' ) );
		$items  = self::get_shared_evaluations( $search );
		$me     = get_current_user_id();
		?>
		<section class="les-panel">
			<h2><?php esc_html_e( 'Bibliothèque partagée', 'logieval-secure' ); ?></h2>
			<p><?php esc_html_e( 'Évaluations mises à disposition par les enseignants. Prévisualisez-les puis dupliquez-les dans votre espace : vous obtenez votre propre copie (questions comprises), que vous pouvez modifier librement sans toucher à l’original.', 'logieval-secure' ); ?></p>
			<?php if ( isset( $_GET['copied'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Évaluation copiée dans vos évaluations.', 'logieval-secure' ); ?></p></div>
			<?php endif; ?>
			<form method="get" class="les-library-search">
				<?php if ( is_admin() ) : ?>
					<input type="hidden" name="page" value="logiexam-library">
				<?php else : ?>
					<input type="hidden" name="logiexam_view" value="library">
				<?php endif; ?>
				<input type="hidden" name="library_tab" value="shared">
				<input type="search" name="library_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Rechercher par titre ou module…', 'logieval-secure' ); ?>">
				<button type="submit" class="button les-button-secondary"><?php esc_html_e( 'Rechercher', 'logieval-secure' ); ?></button>
			</form>
			<div class="les-table-scroll">
				<table class="widefat striped les-library-table">
					<thead><tr><th><?php esc_html_e( 'Évaluation', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Partagée par', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Questions', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Points', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Durée', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Partagée le', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Actions', 'logieval-secure' ); ?></th></tr></thead>
					<tbody>
						<?php if ( empty( $items ) ) : ?>
							<tr><td colspan="7"><?php echo esc_html( '' !== $search ? __( 'Aucune évaluation partagée ne correspond à votre recherche.', 'logieval-secure' ) : __( 'Aucune évaluation partagée pour le moment. Pour partager une des vôtres, cochez « Partager dans la bibliothèque » dans l’évaluation ou utilisez le bouton « Partager » de la liste.', 'logieval-secure' ) ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $items as $item ) : $is_mine = (int) $item['created_by'] === $me; ?>
							<tr>
								<td><strong><?php echo esc_html( $item['title'] ); ?></strong><?php if ( ! empty( $item['description'] ) ) : ?><br><small><?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $item['description'] ), 20 ) ); ?></small><?php endif; ?></td>
								<td><?php echo esc_html( self::user_display_name( (int) $item['created_by'] ) ); ?><?php if ( $is_mine ) : ?> <span class="les-alert-badge is-ok"><?php esc_html_e( 'vous', 'logieval-secure' ); ?></span><?php endif; ?></td>
								<td><?php echo esc_html( (int) $item['question_count'] ); ?></td>
								<td><?php echo esc_html( self::format_points( (float) $item['total_points'] ) ); ?></td>
								<td><?php echo esc_html( (int) $item['duration_minutes'] ? (int) $item['duration_minutes'] . ' min' : '-' ); ?></td>
								<td><?php echo esc_html( ! empty( $item['shared_at'] ) ? mysql2date( 'd.m.Y', (string) $item['shared_at'] ) : '-' ); ?></td>
								<td>
									<div class="les-row-actions">
										<a class="button les-button-secondary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=logiexam&logiexam_view=preview&evaluation_id=' . absint( $item['id'] ) ) : self::frontend_url( array( 'logiexam_view' => 'preview', 'evaluation_id' => absint( $item['id'] ) ) ) ); ?>"><?php esc_html_e( 'Aperçu', 'logieval-secure' ); ?></a>
										<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( self::NONCE_ACTION ); ?><?php self::frontend_redirect_field( array( 'logiexam_view' => 'create' ) ); ?><input type="hidden" name="action" value="logieval_secure_copy_shared"><input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $item['id'] ); ?>"><button type="submit" class="button"><?php esc_html_e( 'Dupliquer dans mes évaluations', 'logieval-secure' ); ?></button></form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	}

	public static function handle_toggle_share(): void {
		self::require_teacher_post();
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$evaluation = self::require_managed_evaluation( absint( $_POST['evaluation_id'] ?? 0 ) );
		$share      = empty( $evaluation['is_shared'] ) ? 1 : 0;
		$wpdb->update(
			$t['evaluations'],
			array(
				'is_shared' => $share,
				'shared_at' => $share ? current_time( 'mysql' ) : null,
			),
			array( 'id' => (int) $evaluation['id'] )
		);
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-evaluations&share_updated=' . $share ), array( 'logiexam_view' => 'evaluations', 'evaluation_id' => null, 'share_updated' => $share ) ) );
		exit;
	}

	/** Copie une question dans la banque de l'enseignant connecté. */
	private static function copy_question_for_current_user( array $question ): int {
		global $wpdb;
		$t    = LogiEval_Secure_Database::tables();
		$data = $question;
		foreach ( array( 'id', 'position', 'evaluation_points' ) as $key ) {
			unset( $data[ $key ] );
		}
		$data['created_by']  = get_current_user_id();
		$data['usage_count'] = 0;
		$data['created_at']  = current_time( 'mysql' );
		$data['updated_at']  = current_time( 'mysql' );
		$wpdb->insert( $t['questions'], $data );
		return (int) $wpdb->insert_id;
	}
}
