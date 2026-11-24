<?php
/**
 * LogiEval_Secure_Evaluations — actions groupées :
 * finaliser toutes les copies pré-corrigées, télécharger tous les PDF élèves d'une classe (ZIP).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Batch {

	/**
	 * Copies qui peuvent être finalisées d'un coup : rendues, non annulées, pas encore finalisées,
	 * et dont chaque réponse a des points (enseignant, automatiques ou proposition IA).
	 *
	 * @return array{ready: array<int,array>, missing: array<int,array>}
	 */
	public static function finalizable_attempts( array $evaluation ): array {
		$ready   = array();
		$missing = array();
		foreach ( self::get_attempts( (int) $evaluation['id'] ) as $attempt ) {
			$result = self::get_result_by_attempt( (int) $attempt['id'] );
			if ( ! self::attempt_needs_correction( $attempt, $result ) ) {
				continue;
			}
			$complete = true;
			foreach ( self::get_answers_for_attempt( (int) $attempt['id'] ) as $answer ) {
				if ( self::answer_is_complete( $answer['answer'] ?? '' ) && ! self::answer_has_correction_points( $answer ) && ! is_numeric( $answer['ai_points'] ?? null ) ) {
					$complete = false;
					break;
				}
			}
			if ( $complete ) {
				$ready[] = $attempt;
			} else {
				$missing[] = $attempt;
			}
		}
		return array( 'ready' => $ready, 'missing' => $missing );
	}

	public static function handle_finalize_all(): void {
		self::require_teacher_post();
		$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
		$evaluation    = self::require_managed_evaluation( $evaluation_id );
		$counts        = self::finalize_ready_attempts( $evaluation );
		$done          = $counts['done'];
		$left          = $counts['left'];
		$args = array( 'logiexam_view' => 'corrections', 'evaluation_id' => $evaluation_id, 'finalized' => $done, 'not_finalized' => $left ?: null );
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-evaluation&step=grade&evaluation_id=' . $evaluation_id . '&finalized=' . $done . ( $left ? '&not_finalized=' . $left : '' ) ), $args ) );
		exit;
	}

	/**
	 * 1.48.0 : finalise toutes les copies prêtes (utilisé aussi par « Enregistrer et finaliser toutes les corrections »
	 * de la correction par question).
	 *
	 * @return array{done:int,left:int}
	 */
	public static function finalize_ready_attempts( array $evaluation ): array {
		global $wpdb;
		$t             = LogiEval_Secure_Database::tables();
		$evaluation_id = (int) $evaluation['id'];
		self::backfill_auto_points( $evaluation_id ); // 1.52.2
		$sets          = self::finalizable_attempts( $evaluation );
		$now           = current_time( 'mysql' );
		$done          = 0;

		foreach ( $sets['ready'] as $attempt ) {
			$attempt_id = (int) $attempt['id'];
			// Les propositions IA non encore reprises deviennent les points de l'enseignant (avec leur commentaire).
			foreach ( self::get_answers_for_attempt( $attempt_id ) as $answer ) {
				if ( is_numeric( $answer['manual_points'] ?? null ) || ! is_numeric( $answer['ai_points'] ?? null ) ) {
					continue;
				}
				$data = array(
					'manual_points' => min( max( 0, (float) $answer['ai_points'] ), max( 0, (float) $answer['points'] ) ),
					'updated_at'    => $now,
				);
				if ( '' === trim( (string) ( $answer['teacher_comment'] ?? '' ) ) && '' !== trim( (string) ( $answer['ai_comment'] ?? '' ) ) ) {
					$data['teacher_comment'] = (string) $answer['ai_comment'];
				}
				$wpdb->update( $t['answers'], $data, array( 'attempt_id' => $attempt_id, 'question_id' => (int) $answer['question_id'] ) );
			}
			self::ensure_zero_for_empty_answers( $attempt_id );
			if ( self::uncorrected_answer_count( $attempt_id ) > 0 ) {
				continue;
			}
			$result = self::get_result_by_attempt( $attempt_id );
			$points = self::sum_attempt_points( $attempt_id );
			$bonus  = (float) ( $result['bonus_points'] ?? 0 );
			$malus  = (float) ( $result['malus_points'] ?? 0 );
			$total  = LogiEval_Secure_Grading::final_total( $points, $bonus, $malus );
			$data   = array(
				'points_obtained' => $points,
				'bonus_points'    => $bonus,
				'malus_points'    => $malus,
				'final_total'     => $total,
				'final_grade'     => LogiEval_Secure_Grading::grade( $total, $evaluation['total_points'] ?? 0 ),
				'status'          => 'graded',
				'teacher_comment' => (string) ( $result['teacher_comment'] ?? '' ),
				'corrected_by'    => get_current_user_id(),
				'corrected_at'    => $now,
				'updated_at'      => $now,
			);
			if ( $result ) {
				$wpdb->update( $t['results'], $data, array( 'attempt_id' => $attempt_id ) );
			} else {
				$wpdb->insert(
					$t['results'],
					$data + array(
						'attempt_id'    => $attempt_id,
						'evaluation_id' => $evaluation_id,
						'user_id'       => (int) $attempt['user_id'],
						'created_at'    => $now,
					)
				);
			}
			$wpdb->update( $t['attempts'], array( 'status' => 'graded' ), array( 'id' => $attempt_id ) );
			do_action( 'logipilot_exam_attempt_finalized', $evaluation_id, $attempt_id );
			$done++;
		}

		return array( 'done' => $done, 'left' => count( $sets['missing'] ) + ( count( $sets['ready'] ) - $done ) );
	}

	private static function render_finalize_all_button( array $evaluation ): void {
		$sets  = self::finalizable_attempts( $evaluation );
		$ready = count( $sets['ready'] );
		if ( ! $ready ) {
			return;
		}
		$confirm = sprintf(
			/* translators: %d: nombre de copies */
			_n( 'Finaliser %d copie ? Les propositions IA non modifiées seront reprises telles quelles comme points définitifs. Vous pourrez rouvrir une correction ensuite.', 'Finaliser %d copies ? Les propositions IA non modifiées seront reprises telles quelles comme points définitifs. Vous pourrez rouvrir chaque correction ensuite.', $ready, 'logieval-secure' ),
			$ready
		);
		?>
		<form class="les-inline les-finalize-all" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( $confirm ); ?>');">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<?php self::frontend_redirect_field( array( 'logiexam_view' => 'corrections', 'evaluation_id' => absint( $evaluation['id'] ) ) ); ?>
			<?php self::return_field(); ?>
			<input type="hidden" name="action" value="logieval_secure_finalize_all">
			<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation['id'] ); ?>">
			<button type="submit" class="button button-primary"><?php echo esc_html( sprintf( _n( 'Finaliser la copie pré-corrigée (%d)', 'Finaliser toutes les copies pré-corrigées (%d)', $ready, 'logieval-secure' ), $ready ) ); ?></button>
			<?php if ( $sets['missing'] ) : ?>
				<span class="description"><?php echo esc_html( sprintf( _n( '%d copie a encore des questions sans points.', '%d copies ont encore des questions sans points.', count( $sets['missing'] ), 'logieval-secure' ), count( $sets['missing'] ) ) ); ?></span>
			<?php endif; ?>
		</form>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * ZIP de tous les PDF élèves (une classe ou toute l'évaluation)
	 * ------------------------------------------------------------------- */

	public static function pdf_zip_url( int $evaluation_id, string $class = '' ): string {
		$args = array(
			'action'        => 'logieval_secure_export_pdf_zip',
			'evaluation_id' => $evaluation_id,
		);
		if ( '' !== $class ) {
			$args['class'] = rawurlencode( $class );
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::NONCE_ACTION );
	}

	public static function handle_export_pdf_zip(): void {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_export' ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'L’extension PHP « zip » n’est pas installée sur le serveur : téléchargez les PDF un par un, ou demandez à votre hébergeur d’activer ZipArchive.', 'logieval-secure' ) );
		}
		$evaluation_id = absint( $_GET['evaluation_id'] ?? 0 );
		$evaluation    = self::require_managed_evaluation( $evaluation_id );
		$class         = isset( $_GET['class'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['class'] ) ) ) : '';

		$attempts = array_values(
			array_filter(
				self::get_attempts( $evaluation_id ),
				static fn( $a ) => '' === $class || self::group_label_for_user( $evaluation, (int) $a['user_id'] ) === $class
			)
		);
		if ( empty( $attempts ) ) {
			wp_die( esc_html__( 'Aucune copie à exporter pour cette sélection.', 'logieval-secure' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$tmp = wp_tempnam( 'logiexam-zip' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Impossible de créer l’archive ZIP.', 'logieval-secure' ) );
		}
		$used = array();
		foreach ( $attempts as $attempt ) {
			$name = self::zip_safe_name( self::student_full_name( (int) $attempt['user_id'] ) ?: self::user_display_name( (int) $attempt['user_id'] ) );
			if ( '' === $class ) {
				$name = self::zip_safe_name( self::group_label_for_user( $evaluation, (int) $attempt['user_id'] ) ) . '/' . $name;
			}
			$file = $name . '.pdf';
			$n    = 2;
			while ( isset( $used[ $file ] ) ) {
				$file = $name . ' (' . $n++ . ').pdf';
			}
			$used[ $file ] = true;
			$zip->addFromString( $file, self::build_attempt_pdf( $evaluation, $attempt ) );
		}
		$zip->close();

		$filename = self::zip_safe_name( 'LogiExam - ' . $evaluation['title'] . ( '' !== $class ? ' - ' . $class : '' ) ) . '.zip';
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		exit;
	}

	/** Nom de fichier lisible (accents conservés) mais sans caractères interdits. */
	private static function zip_safe_name( string $name ): string {
		$name = trim( preg_replace( '/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', wp_strip_all_tags( $name ) ) );
		return '' === $name ? 'copie' : mb_substr( $name, 0, 120 );
	}
}
