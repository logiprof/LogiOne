<?php
/**
 * LogiEval_Secure_Evaluations — partie « formative » (LogiOne 1.56.0).
 *
 * Tests formatifs en libre accès : l'élève les voit directement sur sa page LogiExam, rangés par série de
 * modules (M100, M200…), sans code, autant de fois qu'il le souhaite. Aucune table n'est modifiée : la copie
 * réutilise la ligne existante (évaluation, élève) des tables codes / attempts, remise à zéro à chaque nouvel essai.
 * Le nombre d'essais est gardé dans la méta utilisateur « logiexam_formative_runs ».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Formative {

	/** Tests formatifs ouverts à l'élève (publiés, dans leurs dates, pour sa classe). */
	public static function formative_evaluations_for_student( int $user_id ): array {
		$ids = array_map( 'intval', array_keys( array_filter( (array) get_option( 'logiexam_formative', array() ) ) ) );
		$out = array();
		foreach ( $ids as $id ) {
			$evaluation = self::get_evaluation( $id );
			if ( ! $evaluation || 'deleted' === (string) ( $evaluation['status'] ?? '' ) || ! self::evaluation_is_open( $evaluation ) || ! self::user_can_access_evaluation( $user_id, $evaluation ) ) {
				continue;
			}
			$out[] = $evaluation;
		}
		return $out;
	}

	/**
	 * Numéros de module d'un test (M101, M102…) : d'après le cours choisi pour le test, puis les cours de ses
	 * questions, puis le titre du test. Rend [] si aucun numéro n'est trouvé.
	 */
	public static function formative_modules( array $evaluation ): array {
		$codes   = array();
		$courses = array( absint( $evaluation['sensei_course_id'] ?? 0 ) );
		foreach ( self::get_questions_for_evaluation( (int) $evaluation['id'] ) as $q ) {
			$courses[] = absint( $q['sensei_course_id'] ?? 0 );
		}
		foreach ( array_unique( array_filter( $courses ) ) as $course_id ) {
			if ( preg_match( '/\bM\s?(\d{3})\b/i', (string) get_the_title( $course_id ), $m ) ) {
				$codes[] = 'M' . $m[1];
			}
		}
		if ( ! $codes && preg_match_all( '/\bM\s?(\d{3})\b/i', (string) $evaluation['title'], $all ) ) {
			foreach ( $all[1] as $n ) {
				$codes[] = 'M' . $n;
			}
		}
		$codes = array_values( array_unique( $codes ) );
		sort( $codes, SORT_NATURAL );
		return $codes;
	}

	private static function formative_runs( int $user_id ): array {
		$runs = get_user_meta( $user_id, 'logiexam_formative_runs', true );
		return is_array( $runs ) ? $runs : array();
	}

	private static function formative_attempt_for( int $evaluation_id, int $user_id ): ?array {
		global $wpdb;
		$t  = LogiEval_Secure_Database::tables();
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['attempts']} WHERE evaluation_id=%d AND user_id=%d", $evaluation_id, $user_id ) );
		return $id ? self::get_attempt( $id ) : null;
	}

	private static function attempt_is_submitted( array $attempt ): bool {
		return ! empty( $attempt['submitted_at'] ) || in_array( (string) $attempt['status'], array( 'submitted', 'submitted_locked', 'graded' ), true );
	}

	/** Espace « Entraînement » de la page élève : tuiles M100, M200… avec les modules, puis les tests de chaque module. */
	public static function render_student_formative_area(): string {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		$evaluations = self::formative_evaluations_for_student( $user_id );
		$here        = get_permalink() ?: home_url( '/' );
		$runs        = self::formative_runs( $user_id );
		$series      = array(); // 'M100' => [ 'M101' => [ items ] ]
		foreach ( $evaluations as $evaluation ) {
			$id      = (int) $evaluation['id'];
			$attempt = self::formative_attempt_for( $id, $user_id );
			$done    = $attempt && self::attempt_is_submitted( $attempt ) && ! self::attempt_is_cheating_cancelled( $attempt );
			$item    = array(
				'id'        => $id,
				'title'     => (string) $evaluation['title'],
				'questions' => count( self::get_evaluation_question_ids( $id ) ),
				'runs'      => (int) ( $runs[ $id ] ?? ( $done ? 1 : 0 ) ),
				'done'      => $done,
				'started'   => $attempt && ! $done && 'in_progress' === (string) $attempt['status'] && ! empty( $attempt['started_at'] ),
				'when'      => $done && ! empty( $attempt['submitted_at'] ) ? mysql2date( 'd.m.Y H:i', (string) $attempt['submitted_at'] ) : '',
				'key_url'   => $done ? add_query_arg( array( 'les_corrige' => (int) $attempt['id'], '_lesnonce' => wp_create_nonce( 'logiexam_corrige_' . (int) $attempt['id'] ) ), $here ) : '',
				'pdf_url'   => $done ? wp_nonce_url( admin_url( 'admin-post.php?action=logiexam_formative_export&attempt_id=' . (int) $attempt['id'] ), 'logiexam_formative_export_' . (int) $attempt['id'] ) : '',
			);
			$modules = self::formative_modules( $evaluation );
			foreach ( $modules ? $modules : array( '' ) as $code ) {
				$serie                             = '' === $code ? __( 'Autres', 'logieval-secure' ) : 'M' . substr( $code, 1, 1 ) . '00';
				$series[ $serie ][ $code ][ $id ] = $item;
			}
		}
		uksort( $series, static fn( $a, $b ) => ( 'M' !== $a[0] ) <=> ( 'M' !== $b[0] ) ?: strnatcasecmp( $a, $b ) );

		ob_start();
		?>
		<section class="les-panel les-formative-area" id="les-formatif" data-les-formative-area>
			<header class="les-formative-head">
				<div>
					<span class="les-kicker"><?php esc_html_e( 'Entraînement libre', 'logieval-secure' ); ?></span>
					<h2><?php esc_html_e( 'Tests formatifs', 'logieval-secure' ); ?></h2>
					<p><?php esc_html_e( 'Pour vous entraîner : pas de code à saisir, pas de note. Faites-les quand vous voulez, autant de fois que vous voulez. Après l’envoi, vous voyez le corrigé et vous pouvez exporter votre copie en PDF.', 'logieval-secure' ); ?></p>
				</div>
				<span class="les-formative-tag"><?php esc_html_e( 'Non évalué', 'logieval-secure' ); ?></span>
			</header>
			<?php if ( ! $series ) : ?>
				<p class="les-formative-empty"><?php esc_html_e( 'Aucun test formatif n’est ouvert pour votre classe pour le moment.', 'logieval-secure' ); ?></p>
			<?php else : ?>
				<p class="les-message" data-les-formative-message></p>
				<div class="les-formative-tiles">
					<?php foreach ( $series as $serie => $modules ) : ksort( $modules, SORT_NATURAL ); ?>
						<article class="les-formative-tile">
							<h3><?php echo esc_html( $serie ); ?></h3>
							<div class="les-formative-chips">
								<?php foreach ( $modules as $code => $items ) : $key = sanitize_title( $serie . '-' . ( '' === $code ? 'autres' : $code ) ); ?>
									<button type="button" class="les-formative-chip" data-les-formative-chip="<?php echo esc_attr( $key ); ?>" aria-expanded="false">
										<?php echo esc_html( '' === $code ? __( 'Divers', 'logieval-secure' ) : $code ); ?>
										<span><?php echo (int) count( $items ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<?php foreach ( $series as $serie => $modules ) : ?>
					<?php foreach ( $modules as $code => $items ) : $key = sanitize_title( $serie . '-' . ( '' === $code ? 'autres' : $code ) ); ?>
						<div class="les-formative-list" data-les-formative-list="<?php echo esc_attr( $key ); ?>" hidden>
							<h3><?php echo esc_html( '' === $code ? __( 'Autres tests formatifs', 'logieval-secure' ) : sprintf( __( 'Tests formatifs du module %s', 'logieval-secure' ), $code ) ); ?></h3>
							<ul>
								<?php foreach ( $items as $item ) : ?>
									<li>
										<span class="les-formative-copy-title"><strong><?php echo esc_html( $item['title'] ); ?></strong>
											<small><?php echo esc_html( sprintf( _n( '%d question', '%d questions', $item['questions'], 'logieval-secure' ), $item['questions'] ) ); ?>
												<?php if ( $item['runs'] ) : ?> · <?php echo esc_html( sprintf( _n( 'fait %d fois', 'fait %d fois', $item['runs'], 'logieval-secure' ), $item['runs'] ) ); ?><?php endif; ?>
												<?php if ( $item['when'] ) : ?> · <?php echo esc_html( sprintf( __( 'dernier envoi le %s', 'logieval-secure' ), $item['when'] ) ); ?><?php endif; ?>
											</small>
										</span>
										<span class="les-formative-actions">
											<button type="button" class="button" data-les-formative-start="<?php echo (int) $item['id']; ?>"><?php echo esc_html( $item['started'] ? __( 'Reprendre', 'logieval-secure' ) : ( $item['done'] ? __( 'Recommencer', 'logieval-secure' ) : __( 'Commencer', 'logieval-secure' ) ) ); ?></button>
											<?php if ( $item['done'] ) : ?>
												<a class="button les-button-secondary" href="<?php echo esc_url( $item['key_url'] ); ?>"><?php esc_html_e( 'Voir mon corrigé', 'logieval-secure' ); ?></a>
												<a class="button les-button-secondary" href="<?php echo esc_url( $item['pdf_url'] ); ?>"><?php esc_html_e( 'Exporter en PDF', 'logieval-secure' ); ?></a>
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Démarre (ou reprend) un test formatif sans code. Une copie déjà envoyée est remise à zéro : l'élève
	 * recommence autant de fois qu'il veut. Même réponse que la saisie d'un code, avec « formative ».
	 */
	public static function ajax_start_formative(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Connexion obligatoire.', 'logieval-secure' ) ), 403 );
		}
		global $wpdb;
		$t             = LogiEval_Secure_Database::tables();
		$user_id       = get_current_user_id();
		$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
		$evaluation    = self::get_evaluation( $evaluation_id );
		if ( ! $evaluation || ! self::is_formative( $evaluation ) || 'deleted' === (string) ( $evaluation['status'] ?? '' ) || ! self::evaluation_is_open( $evaluation ) || ! self::user_can_access_evaluation( $user_id, $evaluation ) ) {
			wp_send_json_error( array( 'message' => __( 'Ce test formatif n’est pas ouvert pour vous.', 'logieval-secure' ) ), 403 );
		}

		// Ligne « code » (évaluation, élève) : créée au besoin avec un code aléatoire jamais montré ; elle porte le jeton de session.
		$code_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['codes']} WHERE evaluation_id=%d AND user_id=%d", $evaluation_id, $user_id ) );
		$token   = wp_generate_password( 48, false, false );
		if ( $code_id ) {
			$wpdb->update( $t['codes'], array( 'status' => 'active', 'session_token' => $token, 'session_started_at' => current_time( 'mysql' ), 'used_at' => null ), array( 'id' => $code_id ) );
		} else {
			$wpdb->insert(
				$t['codes'],
				array(
					'evaluation_id'      => $evaluation_id,
					'user_id'            => $user_id,
					'code_hash'          => wp_hash_password( wp_generate_password( 24, false, false ) ),
					'code_preview'       => 'FORMATIF',
					'status'             => 'active',
					'session_token'      => $token,
					'session_started_at' => current_time( 'mysql' ),
					'created_at'         => current_time( 'mysql' ),
				)
			);
			$code_id = (int) $wpdb->insert_id;
		}
		if ( ! $code_id ) {
			wp_send_json_error( array( 'message' => __( 'Impossible de démarrer le test. Réessayez.', 'logieval-secure' ) ), 500 );
		}

		$attempt = self::formative_attempt_for( $evaluation_id, $user_id );
		$fresh   = ! $attempt || self::attempt_is_submitted( $attempt ) || 'in_progress' !== (string) $attempt['status'];
		$base    = array(
			'code_id'      => $code_id,
			'status'       => 'in_progress',
			'last_seen_at' => current_time( 'mysql' ),
			'ip_address'   => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'user_agent'   => sanitize_textarea_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
		);
		if ( ! $attempt ) {
			$wpdb->insert( $t['attempts'], $base + array( 'evaluation_id' => $evaluation_id, 'user_id' => $user_id, 'started_at' => null, 'expires_at' => null ) );
			$attempt_id = (int) $wpdb->insert_id;
		} else {
			$attempt_id = (int) $attempt['id'];
			if ( $fresh ) {
				// Nouvel essai : on efface les réponses et le résultat de l'essai précédent (test formatif uniquement).
				$wpdb->delete( $t['answers'], array( 'attempt_id' => $attempt_id ) );
				$wpdb->delete( $t['results'], array( 'attempt_id' => $attempt_id ) );
				$base += array( 'started_at' => null, 'submitted_at' => null, 'expires_at' => null, 'lock_until' => null );
			}
			$wpdb->update( $t['attempts'], $base, array( 'id' => $attempt_id ) );
		}
		if ( $fresh ) {
			$runs                   = self::formative_runs( $user_id );
			$runs[ $evaluation_id ] = (int) ( $runs[ $evaluation_id ] ?? ( $attempt && self::attempt_is_submitted( $attempt ) ? 1 : 0 ) );
			update_user_meta( $user_id, 'logiexam_formative_runs', $runs );
		}

		wp_send_json_success(
			array(
				'formative'     => true,
				'attempt_id'    => $attempt_id,
				'session_token' => $token,
				'expires_at'    => null,
				'resume_state'  => 'new',
				'evaluation'    => self::get_student_evaluation( $evaluation_id ),
				'questions'     => self::get_student_questions_for_evaluation( $evaluation_id ),
				'answers'       => $fresh ? new stdClass() : self::get_answers_map_for_attempt( $attempt_id ),
				'seb'           => false,
				'watermark'     => '',
				'seb_quit_url'  => '',
			)
		);
	}

	/** Après l'envoi d'un test formatif : essai compté, session libérée, adresse du corrigé. */
	public static function formative_after_submit( array $attempt ): array {
		global $wpdb;
		$t       = LogiEval_Secure_Database::tables();
		$user_id = (int) $attempt['user_id'];
		$eid     = (int) $attempt['evaluation_id'];
		$wpdb->update( $t['attempts'], array( 'status' => 'submitted', 'lock_until' => null ), array( 'id' => (int) $attempt['id'] ) );
		$wpdb->update( $t['codes'], array( 'status' => 'used', 'session_token' => null ), array( 'id' => (int) $attempt['code_id'] ) );
		$runs         = self::formative_runs( $user_id );
		$runs[ $eid ] = (int) ( $runs[ $eid ] ?? 0 ) + 1;
		update_user_meta( $user_id, 'logiexam_formative_runs', $runs );
		$page = (string) get_option( 'logiexam_student_page_url', '' );
		$page = $page ? $page : ( wp_get_referer() ? remove_query_arg( array( 'les_corrige', '_lesnonce' ), wp_get_referer() ) : home_url( '/' ) );
		return array(
			'formative'   => true,
			'message'     => __( 'Copie envoyée. Voici le corrigé : comparez vos réponses avec les réponses attendues.', 'logieval-secure' ),
			'corrige_url' => add_query_arg( array( 'les_corrige' => (int) $attempt['id'], '_lesnonce' => wp_create_nonce( 'logiexam_corrige_' . (int) $attempt['id'] ) ), $page ),
		);
	}

	/** Export PDF d'un test formatif par l'élève : ses réponses à côté des réponses attendues, dès l'envoi. */
	public static function handle_formative_export(): void {
		$attempt_id = absint( $_GET['attempt_id'] ?? 0 );
		check_admin_referer( 'logiexam_formative_export_' . $attempt_id );
		$attempt    = self::get_attempt( $attempt_id );
		$evaluation = self::formative_attempt_open_for_student( $attempt );
		if ( ! is_user_logged_in() || ! $evaluation ) {
			wp_die( esc_html__( 'Cette copie n’est pas disponible.', 'logieval-secure' ), '', array( 'response' => 403 ) );
		}
		$pdf      = self::build_formative_pdf( $evaluation, $attempt );
		$filename = sanitize_file_name( 'test-formatif-' . sanitize_title( (string) $evaluation['title'] ) . '.pdf' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function build_formative_pdf( array $evaluation, array $attempt ): string {
		$data    = self::evaluation_export_data( $evaluation );
		$raw     = self::get_questions_for_evaluation( (int) $evaluation['id'] );
		$answers = self::get_answers_for_attempt( (int) $attempt['id'] );
		$modules = self::formative_modules( $evaluation );

		self::$pdf_images = array();
		$doc = array(
			'pages'    => array(),
			'content'  => '',
			'y'        => 0,
			'title'    => (string) $evaluation['title'],
			'subtitle' => self::student_full_name( (int) $attempt['user_id'] ),
			'header'   => 'Test formatif - ma copie et le corrigé',
		);
		self::pdf_report_new_page( $doc );
		self::pdf_report_section_title( $doc, 'Test formatif' );
		self::pdf_report_info_grid(
			$doc,
			array_values(
				array_filter(
					array(
						array( 'Test', (string) $evaluation['title'] ),
						$modules ? array( 'Module(s)', implode( ', ', $modules ) ) : null,
						array( 'Élève', self::student_full_name( (int) $attempt['user_id'] ), 'highlight' ),
						array( 'Envoyé le', self::pdf_local_datetime( $attempt['submitted_at'] ?? '' ) ),
						array( 'Questions', (string) count( $data['questions'] ) ),
						array( 'Statut', 'Entraînement - non évalué' ),
					)
				)
			)
		);
		self::pdf_report_note_box( $doc, 'À savoir', 'Ce test ne compte pas dans vos notes. Pour chaque question, comparez votre réponse avec la réponse attendue.' );

		self::pdf_report_section_title( $doc, 'Mes réponses et le corrigé' );
		$current_context = null;
		foreach ( $data['questions'] as $i => $q ) {
			$row     = self::answer_row_by_question( $answers, (int) ( $raw[ $i ]['id'] ?? 0 ) );
			$context = trim( self::pdf_plain_text( (string) ( $q['context'] ?? '' ) ) );
			if ( '' !== $context && $context !== $current_context ) {
				self::pdf_report_context_box( $doc, $context );
				$current_context = $context;
			}
			$expected = array();
			foreach ( (array) $q['options'] as $opt ) {
				if ( 0 === strpos( (string) $opt, '[x] ' ) ) {
					$expected[] = substr( (string) $opt, 4 );
				}
			}
			if ( '' !== trim( (string) $q['expected'] ) ) {
				$expected[] = self::pdf_plain_text( (string) $q['expected'] );
			}
			foreach ( (array) $q['criteria'] as $c ) {
				$expected[] = '- ' . $c['label'];
			}
			$auto = isset( $row['auto_points'] ) && is_numeric( $row['auto_points'] ) ? (float) $row['auto_points'] : null;
			self::pdf_report_question_card(
				$doc,
				$i + 1,
				self::pdf_plain_text( (string) $q['prompt'] ),
				self::readable_answer( $row['answer'] ?? '' ),
				(float) ( $auto ?? 0 ),
				(float) $q['points'],
				'Réponse attendue : ' . ( $expected ? implode( ' ', $expected ) : '-' ),
				null === $auto ? self::format_points( (float) $q['points'] ) . ' pts' : null
			);
		}
		if ( '' !== $doc['content'] ) {
			$doc['pages'][] = $doc['content'];
		}
		return self::build_pdf_document( $doc['pages'] );
	}

	/** Copie d'une évaluation vers l'autre partie (formative ↔ sommative) : type de la copie demandé, ou celui de l'original. */
	public static function duplicate_kind_for( int $source_id ): bool {
		$kind = sanitize_key( (string) ( $_POST['as_kind'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- vérifié par l'action.
		if ( 'formatif' === $kind ) {
			return true;
		}
		if ( 'sommatif' === $kind ) {
			return false;
		}
		return self::is_formative( $source_id );
	}
}
