<?php
/**
 * LogiEval_Secure_Evaluations — partie « student-session ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Student_Session {
		public static function ajax_start(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => __( 'Connexion obligatoire.', 'logieval-secure' ) ), 403 );
			}

		if ( self::code_entry_is_rate_limited() ) {
			wp_send_json_error( array( 'message' => __( 'Trop de tentatives. Veuillez patienter quelques minutes avant de réessayer.', 'logieval-secure' ) ), 429 );
		}

		$code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
		$out  = self::start_attempt( get_current_user_id(), $code );
		if ( $out['success'] ) {
			self::clear_code_entry_rate_limit();
			wp_send_json_success( $out );
		}

			if ( empty( $out['seb_required'] ) ) {
			self::register_failed_code_entry();
		}
			wp_send_json_error( $out, 403 );
		}

		/* ── LogiOne 1.30 : délai pour demander un déblocage ─────────────────────────── */

		/**
		 * Numéro de la sortie qui clôture l'évaluation. Le réglage « Blocages autorisés » (N) donne N blocages avec
		 * compte à rebours et demande de déblocage ; la sortie N+1 clôture (LogiOne 1.33.3). Avant, la valeur était
		 * le numéro de la sortie qui clôture : avec 1, la première sortie clôturait sans aucun blocage.
		 */
		public static function max_exits(): int {
			return max( 0, min( 10, (int) self::get_setting( 'max_exit_attempts', 3 ) ) ) + 1;
		}

		public static function unlock_request_seconds(): int {
			return max( 0, min( 300, (int) self::get_setting( 'unlock_request_seconds', 30 ) ) );
		}

		public static function unlock_deadline_message(): string {
			return __( 'Aucune demande de déblocage dans le délai : l’évaluation est clôturée définitivement (note 1).', 'logieval-secure' );
		}

		/**
		 * Secondes restantes pour demander le déblocage, ou null si le délai ne s'applique pas
		 * (réglage à 0, élève pas bloqué, demande déjà envoyée).
		 */
		public static function unlock_deadline_left( array $attempt, ?array $logs = null ): ?int {
			$seconds = self::unlock_request_seconds();
			if ( ! $seconds || ! in_array( (string) ( $attempt['status'] ?? '' ), array( 'security_paused', 'locked_exit_attempt' ), true ) ) {
				return null;
			}
			$logs     = null === $logs ? LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] ) : $logs;
			$block_id = 0;
			$block_at = '';
			foreach ( $logs as $log ) {
				if ( 'exam_blocked' === (string) ( $log['event_type'] ?? '' ) && (int) $log['id'] > $block_id ) {
					$block_id = (int) $log['id'];
					$block_at = (string) $log['created_at'];
				}
			}
			if ( ! $block_id ) {
				return null;
			}
			foreach ( $logs as $log ) {
				if ( in_array( (string) ( $log['event_type'] ?? '' ), array( 'unlock_requested', 'teacher_unlock' ), true ) && (int) $log['id'] > $block_id ) {
					return null;
				}
			}
			// 1.33.2 : heure du site lue avec son fuseau, comparée à l'heure réelle. Avant, si une autre extension
			// changeait le fuseau de PHP, le délai paraissait dépassé d'une heure et l'élève était clôturé sans compteur.
			$elapsed = time() - self::site_time_to_ts( $block_at );
			if ( $elapsed < 0 || $elapsed > DAY_IN_SECONDS ) {
				$elapsed = max( 0, min( $elapsed, DAY_IN_SECONDS ) );
			}
			return $seconds - $elapsed;
		}

		/** Date « heure du site » (current_time('mysql')) → horodatage réel, quel que soit le fuseau de PHP. */
		public static function site_time_to_ts( string $mysql ): int {
			if ( '' === $mysql ) {
				return 0;
			}
			try {
				return ( new DateTimeImmutable( $mysql, wp_timezone() ) )->getTimestamp();
			} catch ( Exception $e ) {
				return (int) strtotime( $mysql );
			}
		}

		/** Le compte à rebours de l'élève est arrivé à zéro : le serveur vérifie, puis clôture (note 1). */
		public static function ajax_unlock_deadline(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			$attempt = self::current_attempt_from_request( array( 'security_paused', 'locked_exit_attempt' ) );
			if ( ! $attempt ) {
				wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
			}
			$left = self::unlock_deadline_left( $attempt );
			if ( null === $left ) {
				wp_send_json_success( array( 'action' => 'none' ) );
			}
			if ( $left > 2 ) {
				wp_send_json_success( array( 'action' => 'wait', 'left' => $left ) );
			}
			$message = self::unlock_deadline_message();
			self::terminate_attempt_for_security( $attempt, $message );
			LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'unlock_deadline_expired', $message, (int) $attempt['id'], 'critical' );
			wp_send_json_success( array( 'action' => 'terminated', 'message' => $message ) );
		}

		public static function ajax_begin_questions(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request();
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}

		$evaluation = self::get_evaluation( (int) $attempt['evaluation_id'] );
		if ( ! $evaluation ) {
			wp_send_json_error( array( 'message' => __( 'Évaluation introuvable.', 'logieval-secure' ) ), 404 );
		}

		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		// 1.34 : durée de cet élève (temps supplémentaire de ses aménagements compris).
		$duration   = self::attempt_duration_seconds( $evaluation, (int) $attempt['user_id'] );
		$expires_at = ! empty( $attempt['expires_at'] ) ? (string) $attempt['expires_at'] : ( $duration > 0 ? gmdate( 'Y-m-d H:i:s', time() + $duration ) : null );
		$started_at = ! empty( $attempt['started_at'] ) ? (string) $attempt['started_at'] : current_time( 'mysql' );
		$wpdb->update(
			$t['attempts'],
			array(
				'status'       => 'in_progress',
				'started_at'   => $started_at,
				'last_seen_at' => current_time( 'mysql' ),
				'expires_at'   => $expires_at,
			),
			array( 'id' => (int) $attempt['id'] )
		);

		wp_send_json_success(
			array(
				'expires_at' => $expires_at,
			)
		);
	}

	public static function ajax_save_answer(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request();
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}

		$attempt = self::ensure_attempt_started( $attempt );
		self::save_answer( $attempt, absint( $_POST['question_id'] ?? 0 ), self::decode_submitted_answer( $_POST['answer'] ?? '' ) );
		// Échéance à jour : l'enseignant a pu ajouter du temps.
		wp_send_json_success( array( 'expires_at' => $attempt['expires_at'] ?? null ) );
	}

		public static function ajax_submit(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			$attempt = self::current_attempt_from_request();
			if ( ! $attempt ) {
				wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}
		self::submit_attempt( $attempt );
		$updated_attempt = self::get_attempt( (int) $attempt['id'] );
			wp_send_json_success(
				array(
					'message'    => __( 'Votre évaluation a bien été envoyée. Restez sur cette interface pendant 5 minutes. Si vous quittez maintenant, l’évaluation sera clôturée définitivement et vous ne pourrez plus la reprendre.', 'logieval-secure' ),
					'lock_until' => $updated_attempt['lock_until'] ?? '',
				)
				);
		}

		public static function ajax_expire(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			$attempt = self::current_attempt_from_request( array( 'in_progress' ), true );
			if ( ! $attempt ) {
				wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
			}

			$answers = json_decode( wp_unslash( $_POST['answers'] ?? '{}' ), true );
			if ( is_array( $answers ) ) {
				foreach ( $answers as $question_id => $answer ) {
					self::save_answer( $attempt, absint( $question_id ), self::normalize_answer_payload( $answer ) );
				}
			}

			// L'enseignant a ajouté du temps entre-temps : on ne clôture pas.
			if ( ! empty( $attempt['expires_at'] ) && strtotime( $attempt['expires_at'] . ' UTC' ) > time() + 5 ) {
				wp_send_json_success(
					array(
						'action'     => 'extended',
						'expires_at' => $attempt['expires_at'],
						'message'    => __( 'Votre enseignant vous a donné du temps en plus.', 'logieval-secure' ),
					)
				);
			}

			self::expire_attempt( $attempt );
			wp_send_json_success(
				array(
					'action'  => 'submitted',
					'message' => __( 'Le temps est écoulé. Votre évaluation a été clôturée automatiquement et transmise à votre enseignant. Vous ne pouvez plus la modifier ni la reprendre.', 'logieval-secure' ),
				)
			);
		}

	public static function ajax_reopen(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request( array( 'submitted_locked' ) );
		if ( ! $attempt || ! self::attempt_lock_is_active( $attempt ) ) {
			wp_send_json_error( array( 'message' => __( 'Le délai de réouverture est terminé.', 'logieval-secure' ) ), 403 );
		}

		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$wpdb->update( $t['attempts'], array( 'status' => 'in_progress', 'last_seen_at' => current_time( 'mysql' ) ), array( 'id' => (int) $attempt['id'] ) );
		LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'post_submit_reopen', __( "L'élève a rouvert son évaluation pendant le délai de 5 minutes.", 'logieval-secure' ), (int) $attempt['id'], 'info' );

		wp_send_json_success(
			array(
				'evaluation' => self::get_student_evaluation( (int) $attempt['evaluation_id'] ),
				'questions'  => self::get_student_questions_for_evaluation( (int) $attempt['evaluation_id'] ),
				'answers'    => self::get_answers_map_for_attempt( (int) $attempt['id'] ),
				'expires_at' => $attempt['expires_at'] ?? '',
			)
		);
	}

	public static function ajax_force_security_block(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request( array( 'in_progress', 'security_paused', 'locked_exit_attempt', 'submitted_locked' ), true );
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}

		$event_type = sanitize_key( $_POST['event_type'] ?? 'fullscreen_exit' );
		$reason     = sanitize_text_field( wp_unslash( $_POST['reason'] ?? __( 'Sortie du mode sécurisé détectée.', 'logieval-secure' ) ) );
		$details    = wp_unslash( $_POST['event_data'] ?? '' );
		$status     = (string) ( $attempt['status'] ?? '' );

		if ( 'unlock_requested' === $event_type ) {
			// L'enseignant a déjà débloqué : on ne rebloque pas l'élève.
			// Uniquement si ce déblocage n'a pas encore été utilisé : après une reprise, une nouvelle sortie doit rebloquer.
			if ( 'in_progress' === $status && self::attempt_has_unconsumed_teacher_unlock( LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] ) ) ) {
				wp_send_json_success(
					array(
						'action'  => 'unlocked',
						'message' => __( 'Votre enseignant a autorisé la reprise. Cliquez sur « Reprendre l’évaluation ».', 'logieval-secure' ),
					)
				);
			}
			// Élève déjà bloqué : on enregistre seulement la demande (elle ne compte PAS comme une sortie supplémentaire).
			if ( in_array( $status, array( 'security_paused', 'locked_exit_attempt' ), true ) ) {
				LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'unlock_requested', $details ?: $reason, (int) $attempt['id'], 'warning' );
				wp_send_json_success(
					array(
						'action'    => 'blocked',
						'requested' => true,
						'message'   => __( 'Votre demande de déblocage a été transmise à votre enseignant.', 'logieval-secure' ),
					)
				);
			}
			// Sinon (le signalement de la sortie n'est jamais arrivé au serveur) : on bloque normalement ci-dessous.
		}

		if ( 'submitted_locked' !== $status ) {
			LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], $event_type, $details ?: $reason, (int) $attempt['id'], 'warning' );
		}

		// Copie déjà rendue : quitter le plein écran clôture la copie, sans aucun signalement (le travail est terminé).
		if ( 'submitted_locked' === (string) ( $attempt['status'] ?? '' ) ) {
			self::finalize_submitted_lock( $attempt );
			$message = __( 'Votre évaluation est terminée définitivement. Vous ne pourrez pas la reprendre.', 'logieval-secure' );
			wp_send_json_success(
				array(
					'action'  => 'submitted',
					'message' => $message,
				)
			);
		}

		if ( 'security_paused' === (string) ( $attempt['status'] ?? '' ) ) {
			wp_send_json_success(
				array(
					'action'  => 'blocked',
					'message' => __( 'Votre évaluation est bloquée. Attendez l’autorisation de votre enseignant pour reprendre.', 'logieval-secure' ),
				)
			);
		}

		$block_count  = self::security_block_count_from_logs( LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] ) );
		$max_attempts = self::max_exits();
		if ( $block_count >= $max_attempts - 1 ) {
			$message = sprintf(
				__( "%d tentatives de sortie ont été détectées. L’évaluation est terminée automatiquement et signalée à l’enseignant.", 'logieval-secure' ),
				$max_attempts
			);
			self::terminate_attempt_for_security( $attempt, $message );
			wp_send_json_success(
				array(
					'action'  => 'terminated',
					'message' => $message,
				)
			);
		}

		$next_count = $block_count + 1;
		$message    = sprintf(
			__( 'Tentative de sortie détectée (%1$d/%2$d). L’évaluation est bloquée jusqu’à validation de l’enseignant.', 'logieval-secure' ),
			$next_count,
			$max_attempts
		);
		self::pause_attempt_for_security( $attempt, $message );
		wp_send_json_success(
			array(
				'action'        => 'blocked',
				'warning_count' => $next_count,
				'message'       => $message,
			)
		);
	}

	public static function ajax_unlock_status(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request( array( 'in_progress', 'security_paused', 'locked_exit_attempt' ), true );
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}

		$unlocked = 'in_progress' === (string) ( $attempt['status'] ?? '' ) && self::attempt_has_unconsumed_teacher_unlock( LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] ) );
		wp_send_json_success(
			array(
				'unlocked' => $unlocked,
				'expires_at' => $attempt['expires_at'] ?? '',
				'message'  => $unlocked
					? __( 'Votre enseignant a autorisé la reprise. Cliquez sur “Reprendre l’évaluation” pour relancer le plein écran et continuer.', 'logieval-secure' )
					: __( 'Votre demande est encore en attente de validation enseignant.', 'logieval-secure' ),
			)
		);
	}

	public static function ajax_resume_after_unlock(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request( array( 'in_progress', 'security_paused' ) );
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}
		if ( 'security_paused' === ( $attempt['status'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'Votre évaluation est encore bloquée. Attendez l’autorisation de votre enseignant.', 'logieval-secure' ) ), 423 );
		}
		if ( ! empty( $_POST['requires_teacher_unlock'] ) && ! self::attempt_has_unconsumed_teacher_unlock( LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Votre demande est en attente. Votre enseignant doit autoriser la reprise depuis le monitoring ou le registre sécurité.', 'logieval-secure' ) ), 423 );
		}
		$attempt = self::ensure_attempt_started( $attempt );
		LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'student_resume_after_unlock', __( "L'élève a repris l'évaluation après autorisation de l'enseignant.", 'logieval-secure' ), (int) $attempt['id'], 'info' );
		wp_send_json_success(
			array(
				'evaluation' => self::get_student_evaluation( (int) $attempt['evaluation_id'] ),
				'questions'  => self::get_student_questions_for_evaluation( (int) $attempt['evaluation_id'] ),
				'answers'    => self::get_answers_map_for_attempt( (int) $attempt['id'] ),
				'expires_at' => $attempt['expires_at'] ?? '',
			)
		);
	}

	public static function ajax_finalize_lock(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request( array( 'submitted_locked' ) );
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}

		self::finalize_submitted_lock( $attempt );
		wp_send_json_success();
	}

	/**
	 * Détermine comment reprendre une tentative existante quand l'élève ressaisit son code
	 * (rechargement de page, onglet fermé...).
	 *  - new        : tentative pas encore commencée, démarrage normal ;
	 *  - blocked    : élève bloqué, il voit l'écran de blocage et peut demander un déblocage ;
	 *  - unlocked   : l'enseignant a débloqué, l'élève voit « Reprendre l'évaluation » ;
	 *  - terminated : nombre maximal de sorties atteint, évaluation terminée.
	 */
	private static function resume_state_for_reentry( array $attempt ): array {
		$status     = (string) ( $attempt['status'] ?? '' );
		$attempt_id = (int) $attempt['id'];
		$logs       = LogiEval_Secure_Security::get_logs_for_attempt( $attempt_id );

		if ( in_array( $status, array( 'security_paused', 'locked_exit_attempt' ), true ) ) {
			// Délai pour demander le déblocage dépassé (page fermée pour échapper au compte à rebours) : clôture.
			$left = self::unlock_deadline_left( $attempt, $logs );
			if ( null !== $left && $left <= 0 ) {
				$message = self::unlock_deadline_message();
				self::terminate_attempt_for_security( $attempt, $message );
				LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'unlock_deadline_expired', $message, $attempt_id, 'critical' );
				return array( 'state' => 'terminated', 'message' => $message );
			}
			return array(
				'state'               => 'blocked',
				'unlock_requested'    => '' !== self::pending_unlock_request_at( $logs ),
				'unlock_deadline_left' => $left,
				'message'             => __( 'Votre évaluation est toujours bloquée. Demandez le déblocage à votre enseignant.', 'logieval-secure' ),
			);
		}

		if ( 'in_progress' !== $status || empty( $attempt['started_at'] ) ) {
			return array( 'state' => 'new' );
		}

		// L'enseignant a débloqué mais l'élève n'avait pas encore repris : pas de nouvelle sanction.
		if ( self::attempt_has_unconsumed_teacher_unlock( $logs ) ) {
			return array(
				'state'   => 'unlocked',
				'message' => __( 'Votre enseignant a autorisé la reprise. Cliquez sur « Reprendre l’évaluation ».', 'logieval-secure' ),
			);
		}

		// Évaluation commencée puis page rechargée / quittée : c'est une sortie du mode sécurisé.
		$reason = __( 'Rechargement ou réouverture de la page pendant l’évaluation.', 'logieval-secure' );
		LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'evaluation_reentry', $reason, $attempt_id, 'warning' );
		$block_count  = self::security_block_count_from_logs( $logs );
		$max_attempts = self::max_exits();
		if ( $block_count >= $max_attempts - 1 ) {
			$message = sprintf(
				__( "%d tentatives de sortie ont été détectées. L’évaluation est terminée automatiquement et signalée à l’enseignant.", 'logieval-secure' ),
				$max_attempts
			);
			self::terminate_attempt_for_security( $attempt, $message );
			return array( 'state' => 'terminated', 'message' => $message );
		}
		$message = sprintf(
			__( 'Tentative de sortie détectée (%1$d/%2$d) : la page de l’évaluation a été rechargée ou fermée. L’évaluation est bloquée jusqu’à validation de l’enseignant.', 'logieval-secure' ),
			$block_count + 1,
			$max_attempts
		);
		self::pause_attempt_for_security( $attempt, $message );
		return array( 'state' => 'blocked', 'unlock_requested' => false, 'message' => $message );
	}

	private static function attempt_has_unconsumed_teacher_unlock( array $logs ): bool {
		$last_unlock_id  = 0;
		$last_request_id = 0;
		$last_resume_id  = 0;
		foreach ( $logs as $log ) {
			$type = (string) ( $log['event_type'] ?? '' );
			$id   = (int) ( $log['id'] ?? 0 );
			if ( 'teacher_unlock' === $type ) {
				$last_unlock_id = max( $last_unlock_id, $id );
			} elseif ( in_array( $type, array( 'exam_blocked', 'unlock_requested' ), true ) ) {
				$last_request_id = max( $last_request_id, $id );
			} elseif ( 'student_resume_after_unlock' === $type ) {
				$last_resume_id = max( $last_resume_id, $id );
			}
		}
		return $last_unlock_id > 0 && $last_unlock_id > $last_request_id && $last_unlock_id > $last_resume_id;
	}

	public static function start_attempt( int $user_id, string $plain_code ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$plain_code = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $plain_code ) ) );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['codes']} WHERE user_id=%d AND status='active'", $user_id ), ARRAY_A );
		foreach ( $rows as $code ) {
			if ( ! wp_check_password( $plain_code, $code['code_hash'] ) ) {
				continue;
			}

			$evaluation = self::get_evaluation( (int) $code['evaluation_id'] );
			if ( ! $evaluation || 'deleted' === (string) ( $evaluation['status'] ?? '' ) || ! self::evaluation_is_open( $evaluation ) || ! self::user_can_access_evaluation( $user_id, $evaluation ) ) {
				return array( 'success' => false, 'message' => __( 'Évaluation indisponible ou non autorisée.', 'logieval-secure' ) );
			}

			// Safe Exam Browser exigé : refus hors de SEB (ou avec une autre configuration), avant toute session.
			$seb_refusal = LogiEval_Secure_SEB::check( (int) $evaluation['id'] );
			if ( $seb_refusal ) {
				LogiEval_Secure_Security::log_event( (int) $evaluation['id'], $user_id, 'seb_refused', wp_json_encode( array( 'reason' => $seb_refusal['message'], 'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ), null, 'warning' );
				return array( 'success' => false ) + $seb_refusal;
			}

			// Le code est personnel et lié au compte connecté : une nouvelle saisie (rechargement de page,
			// fermeture accidentelle de l'onglet) reprend la session. L'ancien onglet perd son jeton.
			$token = wp_generate_password( 48, false, false );
			$wpdb->update( $t['codes'], array( 'session_token' => $token, 'session_started_at' => current_time( 'mysql' ) ), array( 'id' => $code['id'] ) );

			$attempt_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['attempts']} WHERE evaluation_id=%d AND user_id=%d", $evaluation['id'], $user_id ) );
			$existing   = $attempt_id ? self::get_attempt( $attempt_id ) : null;
			$resume     = $existing ? self::resume_state_for_reentry( $existing ) : array( 'state' => 'new' );

			if ( 'terminated' === $resume['state'] ) {
				return array( 'success' => false, 'message' => $resume['message'] );
			}

			if ( 'new' === $resume['state'] ) {
				$data = array( 'code_id' => (int) $code['id'], 'status' => 'in_progress', 'started_at' => null, 'last_seen_at' => current_time( 'mysql' ), 'expires_at' => null, 'ip_address' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ), 'user_agent' => sanitize_textarea_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
				if ( $attempt_id ) {
					$wpdb->update( $t['attempts'], $data, array( 'id' => $attempt_id ) );
				} else {
					$data['evaluation_id'] = (int) $evaluation['id'];
					$data['user_id']       = $user_id;
					$wpdb->insert( $t['attempts'], $data );
					$attempt_id = (int) $wpdb->insert_id;
				}
			} else {
				// Tentative déjà commencée : on conserve statut, heure de début et échéance.
				$wpdb->update( $t['attempts'], array( 'code_id' => (int) $code['id'], 'last_seen_at' => current_time( 'mysql' ) ), array( 'id' => $attempt_id ) );
			}

			$attempt = self::get_attempt( $attempt_id );
			return array(
				'success'           => true,
				'attempt_id'        => $attempt_id,
				'session_token'     => $token,
				'expires_at'        => 'new' === $resume['state'] ? null : ( $attempt['expires_at'] ?? null ),
				'resume_state'      => $resume['state'],
				'resume_message'    => $resume['message'] ?? '',
				'unlock_requested'  => ! empty( $resume['unlock_requested'] ),
				'unlock_deadline_left' => $resume['unlock_deadline_left'] ?? null,
				'evaluation'        => self::get_student_evaluation( (int) $evaluation['id'] ),
				'questions'         => self::get_student_questions_for_evaluation( (int) $evaluation['id'] ),
				'answers'           => self::get_answers_map_for_attempt( $attempt_id ),
				'seb'               => LogiEval_Secure_SEB::is_required( (int) $evaluation['id'] ),
				// Filigrane affiché sur les questions : une capture ou une photo qui circule reste identifiable.
				'watermark'         => trim( wp_get_current_user()->display_name . ' · ' . wp_date( 'd.m.Y H:i' ) ),
				'seb_quit_url'      => LogiEval_Secure_SEB::is_required( (int) $evaluation['id'] ) ? LogiEval_Secure_SEB::quit_url() : '',
			);
		}

		$other_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['codes']} WHERE user_id<>%d AND status='active'", $user_id ), ARRAY_A );
		foreach ( $other_rows as $code ) {
			if ( wp_check_password( $plain_code, $code['code_hash'] ) ) {
				return array( 'success' => false, 'message' => __( 'Ce code est valide, mais il appartient à un autre compte élève. Connectez-vous avec le bon compte utilisateur.', 'logieval-secure' ) );
			}
		}

		return array( 'success' => false, 'message' => __( 'Code invalide.', 'logieval-secure' ) );
	}

	public static function save_answer( array $attempt, int $question_id, $answer ): void {
		global $wpdb;
		$t        = LogiEval_Secure_Database::tables();
		$question = self::get_question( $question_id );
		if ( ! $question || ! self::question_belongs_to_evaluation( $question_id, (int) $attempt['evaluation_id'] ) ) {
			return;
		}

		$special_value = LogiEval_Secure_Question_Types::answer_to_store( $question, $answer );
		if ( null !== $special_value ) {
			$answer_value = $special_value;
		} elseif ( is_array( $answer ) && ( array_key_exists( 'development', $answer ) || array_key_exists( 'final_result', $answer ) ) ) {
			$answer_value = wp_json_encode(
				array(
					'development'  => sanitize_textarea_field( (string) ( $answer['development'] ?? '' ) ),
					'final_result' => sanitize_text_field( (string) ( $answer['final_result'] ?? '' ) ),
				),
				JSON_UNESCAPED_UNICODE
			);
		} else {
			$answer_value = is_array( $answer ) ? wp_json_encode( array_map( 'sanitize_text_field', $answer ), JSON_UNESCAPED_UNICODE ) : sanitize_textarea_field( (string) $answer );
		}
		$auto_points  = LogiEval_Secure_Grading::auto_points( $question, $answer );
		$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT id, answer FROM {$t['answers']} WHERE attempt_id=%d AND question_id=%d", $attempt['id'], $question_id ), ARRAY_A );
		$exists       = $existing_row ? (int) $existing_row['id'] : 0;
		$data         = array( 'evaluation_id' => (int) $attempt['evaluation_id'], 'answer' => $answer_value, 'auto_points' => $auto_points, 'updated_at' => current_time( 'mysql' ) );
		if ( $exists && (string) ( $existing_row['answer'] ?? '' ) !== $answer_value ) {
			$data['ai_points']       = null;
			$data['ai_comment']      = null;
			$data['ai_analysis']     = null;
			$data['ai_confidence']   = null;
			$data['ai_needs_review'] = 0;
			$data['ai_model']        = '';
			$data['ai_graded_at']    = null;
		}

		if ( $exists ) {
			$wpdb->update( $t['answers'], $data, array( 'id' => $exists ) );
		} else {
			$data['attempt_id']  = (int) $attempt['id'];
			$data['question_id'] = $question_id;
			$wpdb->insert( $t['answers'], $data );
		}
		$wpdb->update( $t['attempts'], array( 'last_seen_at' => current_time( 'mysql' ) ), array( 'id' => $attempt['id'] ) );
	}

		private static function decode_submitted_answer( $answer ) {
			$answer = wp_unslash( $answer );
			if ( is_string( $answer ) ) {
				$decoded = json_decode( $answer, true );
				if ( is_array( $decoded ) ) {
					return self::normalize_answer_payload( $decoded );
				}
			}
			return self::normalize_answer_payload( $answer );
		}

		private static function normalize_answer_payload( $answer ) {
			if ( is_array( $answer ) ) {
				if ( array_key_exists( 'choice', $answer ) || array_key_exists( 'justification', $answer ) ) {
					return array(
						'choice'        => sanitize_text_field( (string) ( $answer['choice'] ?? '' ) ),
						'justification' => sanitize_textarea_field( (string) ( $answer['justification'] ?? '' ) ),
					);
				}
				if ( array_key_exists( 'development', $answer ) || array_key_exists( 'final_result', $answer ) ) {
					return array(
						'development'  => sanitize_textarea_field( (string) ( $answer['development'] ?? '' ) ),
						'final_result' => sanitize_text_field( (string) ( $answer['final_result'] ?? '' ) ),
					);
				}
				// 1.36 : Relier {pairs: {gauche: droite}} et texte à trous {blanks: [...]} (positions des trous conservées).
				if ( isset( $answer['pairs'] ) && is_array( $answer['pairs'] ) ) {
					$pairs = array();
					foreach ( $answer['pairs'] as $l => $r ) {
						if ( is_scalar( $r ) ) {
							$pairs[ sanitize_key( (string) $l ) ] = sanitize_key( (string) $r );
						}
					}
					return array( 'pairs' => $pairs );
				}
				if ( isset( $answer['blanks'] ) && is_array( $answer['blanks'] ) ) {
					return array( 'blanks' => array_map( static fn( $v ) => is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '', array_values( $answer['blanks'] ) ) );
				}
				return array_values( array_filter( array_map( 'sanitize_text_field', $answer ), static fn( $value ) => '' !== $value ) );
			}
			return sanitize_textarea_field( (string) $answer );
		}

		public static function submit_attempt( array $attempt ): void {
			global $wpdb;
			$t = LogiEval_Secure_Database::tables();
			$lock_minutes = max( 0, (int) self::get_setting( 'post_submit_lock_minutes', 5 ) );
		$lock_until = gmdate( 'Y-m-d H:i:s', time() + ( $lock_minutes * MINUTE_IN_SECONDS ) );
		$wpdb->update( $t['attempts'], array( 'status' => 'submitted_locked', 'submitted_at' => current_time( 'mysql' ), 'last_seen_at' => current_time( 'mysql' ), 'lock_until' => $lock_until ), array( 'id' => $attempt['id'] ) );
		$wpdb->update( $t['codes'], array( 'status' => 'submitted_locked', 'used_at' => current_time( 'mysql' ) ), array( 'id' => $attempt['code_id'] ) );

		$points = self::sum_attempt_points( (int) $attempt['id'] );
		$eval   = self::get_evaluation( (int) $attempt['evaluation_id'] );
		list( $bonus, $malus ) = self::logipilot_bonus_malus( $attempt );
		$total  = LogiEval_Secure_Grading::final_total( $points, $bonus, $malus );
		$grade  = LogiEval_Secure_Grading::grade( $total, $eval ? $eval['total_points'] : 0 );
		$data   = array( 'points_obtained' => $points, 'bonus_points' => $bonus, 'malus_points' => $malus, 'final_total' => $total, 'final_grade' => $grade, 'status' => 'to_grade', 'updated_at' => current_time( 'mysql' ) );
		if ( self::get_result_by_attempt( (int) $attempt['id'] ) ) {
			$wpdb->update( $t['results'], $data, array( 'attempt_id' => (int) $attempt['id'] ) );
		} else {
			$wpdb->insert( $t['results'], $data + array( 'attempt_id' => (int) $attempt['id'], 'evaluation_id' => (int) $attempt['evaluation_id'], 'user_id' => (int) $attempt['user_id'], 'created_at' => current_time( 'mysql' ) ) );
		}
		}

		/**
		 * LogiOne : bonus et malus à la remise. Priorité à la décision de progression validée,
		 * sinon les valeurs déjà saisies sont conservées (auparavant remises à zéro).
		 */
		private static function logipilot_bonus_malus( array $attempt ): array {
			if ( class_exists( 'LogiPilot_Snapshots' ) ) {
				$decision = LogiPilot_Snapshots::validated_decision( 'exam', (int) $attempt['evaluation_id'], (int) $attempt['user_id'] );
				if ( $decision && null !== $decision->adjustment ) {
					return LogiPilot_Snapshots::bonus_malus( (float) $decision->adjustment );
				}
			}
			$result = self::get_result_by_attempt( (int) $attempt['id'] );
			return array( (float) ( $result['bonus_points'] ?? 0 ), (float) ( $result['malus_points'] ?? 0 ) );
		}

		public static function expire_attempt( array $attempt ): void {
			global $wpdb;
			$t = LogiEval_Secure_Database::tables();
			$wpdb->update(
				$t['attempts'],
				array(
					'status'       => 'submitted',
					'submitted_at' => current_time( 'mysql' ),
					'last_seen_at' => current_time( 'mysql' ),
					'lock_until'   => null,
				),
				array( 'id' => (int) $attempt['id'] )
			);
			$wpdb->update( $t['codes'], array( 'status' => 'used', 'session_token' => null, 'used_at' => current_time( 'mysql' ) ), array( 'id' => (int) $attempt['code_id'] ) );

			$points = self::sum_attempt_points( (int) $attempt['id'] );
			$eval   = self::get_evaluation( (int) $attempt['evaluation_id'] );
			list( $bonus, $malus ) = self::logipilot_bonus_malus( $attempt );
			$total  = LogiEval_Secure_Grading::final_total( $points, $bonus, $malus );
			$grade  = LogiEval_Secure_Grading::grade( $total, $eval ? $eval['total_points'] : 0 );
			$result = self::get_result_by_attempt( (int) $attempt['id'] );
			$data   = array(
				'points_obtained' => $points,
				'bonus_points'    => $bonus,
				'malus_points'    => $malus,
				'final_total'     => $total,
				'final_grade'     => $grade,
				'status'          => 'to_grade',
				'updated_at'      => current_time( 'mysql' ),
			);
			if ( $result ) {
				$wpdb->update( $t['results'], $data, array( 'attempt_id' => (int) $attempt['id'] ) );
			} else {
				$data['attempt_id']    = (int) $attempt['id'];
				$data['evaluation_id'] = (int) $attempt['evaluation_id'];
				$data['user_id']       = (int) $attempt['user_id'];
				$data['created_at']    = current_time( 'mysql' );
				$wpdb->insert( $t['results'], $data );
			}
			LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'time_expired_auto_submit', __( "Temps écoulé : l'évaluation a été clôturée automatiquement.", 'logieval-secure' ), (int) $attempt['id'], 'info' );
		}

		public static function current_attempt_from_request( array $allowed_statuses = array( 'in_progress' ), bool $allow_expired = false ): ?array {
			if ( ! is_user_logged_in() ) {
				return null;
			}
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$attempt_id = absint( $_POST['attempt_id'] ?? 0 );
		$token      = sanitize_text_field( wp_unslash( $_POST['session_token'] ?? '' ) );
		if ( ! $attempt_id || ! $token ) {
			return null;
		}
		$attempt = self::get_attempt( $attempt_id );
		if ( ! $attempt || (int) $attempt['user_id'] !== get_current_user_id() || ! in_array( $attempt['status'], $allowed_statuses, true ) ) {
			return null;
		}
			if ( ! $allow_expired && 'in_progress' === $attempt['status'] && self::attempt_is_expired( $attempt ) ) {
				return null;
			}
		$valid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['codes']} WHERE id=%d AND session_token=%s", $attempt['code_id'], $token ) );
		if ( $valid && wp_doing_ajax() ) {
			// Safe Exam Browser exigé : chaque demande (réponse, envoi…) doit venir de SEB, pas seulement le démarrage.
			$seb_refusal = LogiEval_Secure_SEB::check( (int) $attempt['evaluation_id'] );
			if ( $seb_refusal ) {
				wp_send_json_error( $seb_refusal, 403 );
			}
		}
		return $valid ? $attempt : null;
	}

	/**
	 * Heure de début et échéance enregistrées dès que l'élève travaille (réponse, blocage, reprise), même si la
	 * demande « Commencer » n'est jamais arrivée au serveur (ex. blocage pendant le passage en plein écran).
	 */
	public static function ensure_attempt_started( array $attempt ): array {
		if ( ! empty( $attempt['started_at'] ) ) {
			return $attempt;
		}
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$evaluation = self::get_evaluation( (int) $attempt['evaluation_id'] );
		$data       = array( 'started_at' => current_time( 'mysql' ) );
		if ( empty( $attempt['expires_at'] ) && $evaluation && ! empty( $evaluation['duration_minutes'] ) ) {
			$data['expires_at'] = gmdate( 'Y-m-d H:i:s', time() + self::attempt_duration_seconds( $evaluation, (int) $attempt['user_id'] ) );
		}
		$wpdb->update( $t['attempts'], $data, array( 'id' => (int) $attempt['id'] ) );
		return array_merge( $attempt, $data );
	}

	public static function pause_attempt_for_security( array $attempt, string $reason ): void {
		$attempt = self::ensure_attempt_started( $attempt );
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t['attempts']} SET status=%s, last_seen_at=%s WHERE id=%d AND status<>%s",
				'security_paused',
				current_time( 'mysql' ),
				(int) $attempt['id'],
				'security_paused'
			)
		);
		if ( $updated ) {
			LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'exam_blocked', $reason, (int) $attempt['id'], 'critical' );
		}
	}

	public static function terminate_attempt_for_security( array $attempt, string $reason ): void {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$wpdb->update(
			$t['attempts'],
			array(
				'status'       => 'security_terminated',
				'submitted_at' => current_time( 'mysql' ),
				'last_seen_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $attempt['id'] )
		);
		$wpdb->update( $t['codes'], array( 'status' => 'used', 'session_token' => null, 'used_at' => current_time( 'mysql' ) ), array( 'id' => (int) $attempt['code_id'] ) );

		// Évaluation annulée pour tricherie : pas de correction, note 1.
		$data   = array(
			'points_obtained' => 0,
			'bonus_points'    => 0,
			'malus_points'    => 0,
			'final_total'     => 0,
			'final_grade'     => self::CHEATING_GRADE,
			'status'          => 'suspected_cheating',
			'teacher_comment' => sanitize_textarea_field( $reason ),
			'updated_at'      => current_time( 'mysql' ),
		);
		if ( self::get_result_by_attempt( (int) $attempt['id'] ) ) {
			$wpdb->update( $t['results'], $data, array( 'attempt_id' => (int) $attempt['id'] ) );
		} else {
			$data['attempt_id']    = (int) $attempt['id'];
			$data['evaluation_id'] = (int) $attempt['evaluation_id'];
			$data['user_id']       = (int) $attempt['user_id'];
			$data['created_at']    = current_time( 'mysql' );
			$wpdb->insert( $t['results'], $data );
		}
		LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'exam_auto_submitted', $reason, (int) $attempt['id'], 'critical' );
	}

	public static function attempt_lock_is_active( array $attempt ): bool {
		return ! empty( $attempt['lock_until'] ) && strtotime( $attempt['lock_until'] . ' UTC' ) > time();
	}

	public static function attempt_is_expired( array $attempt ): bool {
		return ! empty( $attempt['expires_at'] ) && strtotime( $attempt['expires_at'] . ' UTC' ) < time();
	}

	public static function finalize_submitted_lock( array $attempt ): void {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$wpdb->update( $t['attempts'], array( 'status' => 'submitted', 'last_seen_at' => current_time( 'mysql' ) ), array( 'id' => (int) $attempt['id'] ) );
		$wpdb->update( $t['codes'], array( 'status' => 'used', 'session_token' => null ), array( 'id' => (int) $attempt['code_id'] ) );
	}

	public static function flag_attempt_cheating( array $attempt, string $reason ): void {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$wpdb->update( $t['attempts'], array( 'status' => 'locked_exit_attempt', 'last_seen_at' => current_time( 'mysql' ) ), array( 'id' => (int) $attempt['id'] ) );
		$result = self::get_result_by_attempt( (int) $attempt['id'] );
		$data   = array( 'status' => 'suspected_cheating', 'teacher_comment' => sanitize_textarea_field( $reason ), 'updated_at' => current_time( 'mysql' ) );
		if ( $result ) {
			$wpdb->update( $t['results'], $data, array( 'attempt_id' => (int) $attempt['id'] ) );
		}
	}

	public static function get_student_evaluation( int $id ): ?array {
		$evaluation = self::get_evaluation( $id );
		if ( ! $evaluation || 'deleted' === (string) ( $evaluation['status'] ?? '' ) ) {
			return null;
		}
		$type = self::normalize_evaluation_type( $evaluation['evaluation_type'] ?? 'mes_company' );

		return array(
			'id'               => (int) $evaluation['id'],
			'title'            => (string) $evaluation['title'],
			'description'      => wp_kses_post( (string) $evaluation['description'] ),
			'instructions'     => wp_kses_post( (string) $evaluation['instructions'] ),
			// 1.34 : durée de l'élève connecté (aménagements compris) et ses aménagements (audio, zoom).
			'duration_minutes' => (int) round( self::attempt_duration_seconds( $evaluation, get_current_user_id() ) / MINUTE_IN_SECONDS ),
			'base_duration_minutes' => (int) $evaluation['duration_minutes'],
			'accommodations'   => self::accommodations_for( get_current_user_id() ),
			'total_points'     => (float) $evaluation['total_points'],
			'evaluation_type'  => $type,
			'story_intro'      => wp_kses_post( (string) ( $evaluation['story_intro'] ?? '' ) ),
			'security_notice'  => wp_kses_post( (string) ( ! empty( $evaluation['security_notice'] ) ? $evaluation['security_notice'] : self::default_security_notice() ) ),
			'secure_mode'      => (int) $evaluation['secure_mode'],
			'company_name'     => 'mes_company' === $type ? sanitize_text_field( $evaluation['company_name'] ?? '' ) : '',
			'company_context'  => 'mes_company' === $type ? wp_kses_post( (string) ( $evaluation['company_context'] ?? '' ) ) : '',
			'apprentice_role'  => 'mes_company' === $type ? wp_kses_post( (string) ( $evaluation['apprentice_role'] ?? '' ) ) : '',
			'company_logo_url' => 'mes_company' === $type && ! empty( $evaluation['company_logo_id'] ) ? self::private_image_url( (int) $evaluation['company_logo_id'], 'medium' ) : '',
		);
	}

	public static function get_student_questions_for_evaluation( int $evaluation_id ): array {
		$questions = array();
		foreach ( self::get_questions_for_evaluation( $evaluation_id ) as $question ) {
			$points      = (float) ( $question['evaluation_points'] ?: $question['points'] );
			$is_order    = LogiEval_Secure_Question_Types::ORDERING === $question['type'];
			$is_match    = LogiEval_Secure_Question_Types::MATCHING === $question['type'];
			$is_blanks   = LogiEval_Secure_Question_Types::BLANKS === $question['type'];
			$questions[] = array(
				'id'                => (int) $question['id'],
				'title'             => (string) $question['title'],
				'context'           => wp_kses_post( (string) ( $question['context'] ?? '' ) ),
				'prompt'            => wp_kses_post( (string) $question['prompt'] ),
				'type'              => sanitize_key( (string) $question['type'] ),
				// Remettre dans l'ordre : les options sont dans le bon ordre, l'élève ne reçoit que les éléments mélangés.
				'options'           => $is_order || $is_match || $is_blanks ? '[]' : (string) $question['options'],
				// 1.36 : Relier (droite mélangée, identifiants différents de la gauche) et texte à trous (jamais les réponses).
				'match'             => $is_match ? LogiEval_Secure_Question_Types::student_match( $question, get_current_user_id() ) : null,
				'blanks'            => $is_blanks ? LogiEval_Secure_Question_Types::student_blanks( $question, get_current_user_id() ) : null,
				'calculator'        => LogiEval_Secure_Question_Types::has_calculator( $question ),
				'items'             => $is_order ? LogiEval_Secure_Question_Types::student_items( $question, get_current_user_id() ) : null,
				'order_mode'        => $is_order ? LogiEval_Secure_Question_Types::order_settings( $question['expected_answer'] ?? '' )['mode'] : '',
				// Calcul : seulement la consigne de précision (jamais le résultat attendu).
				'precision_label'   => 'calculation' === $question['type'] ? LogiEval_Secure_Question_Types::precision_label( LogiEval_Secure_Question_Types::calculation_precision( $question['expected_answer'] ?? '' ) ) : '',
				'points'            => $points,
				'evaluation_points' => $points,
				'media_id'          => absint( $question['media_id'] ?? 0 ),
				'image'             => self::question_image_data( absint( $question['media_id'] ?? 0 ) ),
			);
		}
		$evaluation = self::get_evaluation( $evaluation_id );
		if ( $evaluation && self::exam_options( $evaluation_id )['shuffle_questions'] ) {
			$questions = self::shuffle_questions_for_student( $questions, $evaluation_id, get_current_user_id() );
		}
		return $questions;
	}

	/**
	 * Ordre aléatoire des questions : chaque élève a son propre ordre, toujours le même pour lui
	 * (rechargement, reprise après blocage, réouverture). Les réponses restent liées à chaque question.
	 */
	public static function shuffle_questions_for_student( array $questions, int $evaluation_id, int $user_id ): array {
		$n = count( $questions );
		if ( $n < 2 ) {
			return $questions;
		}
		// 1.38.5 : les sous-questions d'un même problème (même énoncé, à la suite) restent ensemble et dans l'ordre :
		// on mélange des blocs (une question seule = un bloc).
		$questions = array_values( $questions );
		$blocks    = array();
		foreach ( $questions as $i => $q ) {
			$ctx  = trim( (string) ( $q['context'] ?? '' ) );
			$last = count( $blocks ) - 1;
			if ( '' !== $ctx && $last >= 0 && $blocks[ $last ]['ctx'] === $ctx ) {
				$blocks[ $last ]['items'][] = $q;
			} else {
				$blocks[] = array( 'ctx' => $ctx, 'items' => array( $q ) );
			}
		}
		if ( count( $blocks ) < 2 ) {
			return $questions;
		}
		$grouped = count( $blocks ) < count( $questions );
		if ( $grouped ) {
			$keys = array();
			foreach ( $blocks as $i => $b ) {
				$keys[ $i ] = sprintf( '%u', crc32( 'logiexam-question-order|' . $evaluation_id . '|' . $user_id . '|' . (int) $b['items'][0]['id'] ) );
			}
			$order = array_keys( $blocks );
			usort( $order, static fn( $a, $b ) => strnatcmp( $keys[ $a ], $keys[ $b ] ) ?: $a <=> $b );
			if ( $order === array_keys( $blocks ) ) {
				$order = array_merge( array_slice( $order, 1 ), array( $order[0] ) );
			}
			$out = array();
			foreach ( $order as $i ) {
				foreach ( $blocks[ $i ]['items'] as $q ) {
					$out[] = $q;
				}
			}
			return $out;
		}
		$keys = array();
		foreach ( $questions as $i => $q ) {
			$keys[ $i ] = sprintf( '%u', crc32( 'logiexam-question-order|' . $evaluation_id . '|' . $user_id . '|' . (int) $q['id'] ) );
		}
		$order = array_keys( $questions );
		usort(
			$order,
			static function ( $a, $b ) use ( $keys ) {
				return strnatcmp( $keys[ $a ], $keys[ $b ] ) ?: $a <=> $b;
			}
		);
		if ( $order === array_keys( $questions ) ) {
			// Jamais exactement l'ordre de l'évaluation : on décale d'un cran.
			$order = array_merge( array_slice( $order, 1 ), array( $order[0] ) );
		}
		$out = array();
		foreach ( $order as $i ) {
			$out[] = $questions[ $i ];
		}
		return $out;
	}

	public static function evaluation_is_open( array $evaluation ): bool {
		$now = time();
		if ( ! empty( $evaluation['opens_at'] ) && self::site_time_to_ts( (string) $evaluation['opens_at'] ) > $now ) {
			return false;
		}
		if ( ! empty( $evaluation['closes_at'] ) && self::site_time_to_ts( (string) $evaluation['closes_at'] ) < $now ) {
			return false;
		}
		return 'published' === $evaluation['status'];
	}

	public static function user_can_access_evaluation( int $user_id, array $evaluation ): bool {
		$specific_students = self::decode_ids( $evaluation['student_user_ids'] );
		if ( ! empty( $specific_students ) && ! in_array( $user_id, $specific_students, true ) ) {
			return false;
		}
		return LogiEval_Secure_Sensei::user_in_any_group( $user_id, self::decode_ids( $evaluation['sensei_group_ids'] ) );
	}

	public static function resolve_student_ids( array $evaluation ): array {
		$ids = self::decode_ids( $evaluation['student_user_ids'] );
		foreach ( self::decode_ids( $evaluation['sensei_group_ids'] ) as $group_id ) {
			foreach ( LogiEval_Secure_Sensei::get_group_students( $group_id ) as $student ) {
				$ids[] = (int) $student->ID;
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private static function code_entry_rate_limit_key(): string {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		return 'logieval_secure_code_attempts_' . md5( get_current_user_id() . '|' . $ip );
	}

	private static function code_entry_is_rate_limited(): bool {
		return (int) get_transient( self::code_entry_rate_limit_key() ) >= 8;
	}

	private static function register_failed_code_entry(): void {
		$key      = self::code_entry_rate_limit_key();
		$attempts = (int) get_transient( $key );
		set_transient( $key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
	}

	private static function clear_code_entry_rate_limit(): void {
		delete_transient( self::code_entry_rate_limit_key() );
	}
}
