<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Security {
	public static function init(): void {
		add_action( 'wp_ajax_logieval_secure_security_event', array( __CLASS__, 'ajax_log_event' ) );
	}

	public static function ajax_log_event(): void {
		check_ajax_referer( LogiEval_Secure_Evaluations::NONCE_ACTION, 'nonce' );
		$attempt = LogiEval_Secure_Evaluations::current_attempt_from_request( array( 'in_progress', 'submitted_locked', 'locked_exit_attempt', 'security_paused' ) );
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}

		$event_type = sanitize_key( $_POST['event_type'] ?? 'unknown' );
		$event_data = wp_unslash( $_POST['event_data'] ?? '' );
		$severity   = sanitize_key( $_POST['severity'] ?? 'warning' );

		// Copie déjà rendue : le travail est terminé, plus rien n'est signalé (sortie du plein écran, onglet, raccourcis…).
		if ( ! self::attempt_is_submitted( $attempt ) && ! self::is_recent_duplicate( (int) $attempt['id'], $event_type ) ) {
			self::log_event(
				(int) $attempt['evaluation_id'],
				(int) $attempt['user_id'],
				$event_type,
				$event_data,
				(int) $attempt['id'],
				$severity
			);
		}

		$fresh_attempt   = LogiEval_Secure_Evaluations::get_attempt( (int) $attempt['id'] );
		$security_action = self::maybe_apply_attempt_threshold( $fresh_attempt ?: $attempt, $event_type );
		wp_send_json_success( $security_action );
	}

	public static function log_event( int $evaluation_id, int $user_id, string $event_type, $event_data = '', ?int $attempt_id = null, string $severity = 'info' ): void {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$wpdb->insert(
			$t['security_logs'],
			array(
				'attempt_id'    => $attempt_id,
				'evaluation_id' => $evaluation_id,
				'user_id'       => $user_id,
				'event_type'    => sanitize_key( $event_type ),
				'event_data'    => is_string( $event_data ) ? sanitize_textarea_field( $event_data ) : wp_json_encode( $event_data ),
				'severity'      => sanitize_key( $severity ),
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	/** Copie rendue (en attente de clôture ou clôturée) : plus aucun incident n'est enregistré. */
	public static function attempt_is_submitted( array $attempt ): bool {
		return ! in_array( (string) ( $attempt['status'] ?? '' ), array( 'in_progress', 'security_paused', 'locked_exit_attempt' ), true );
	}

	/** Évènements enregistrés après la remise par d'anciennes versions : ils ne sont plus montrés ni comptés. */
	private static function after_submit_filter(): string {
		// Utilisé dans $wpdb->prepare() : les % sont doublés.
		return " AND NOT ( (event_type LIKE 'post\\\\_submit\\\\_%%' AND event_type <> 'post_submit_reopen') OR event_data LIKE '%%après soumission%%' )";
	}

	public static function get_logs_for_evaluation( int $evaluation_id ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['security_logs']} WHERE evaluation_id=%d" . self::after_submit_filter() . " ORDER BY created_at DESC LIMIT 200", $evaluation_id ), ARRAY_A );
	}

	public static function get_logs_for_attempt( int $attempt_id ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['security_logs']} WHERE attempt_id=%d" . self::after_submit_filter() . " ORDER BY created_at DESC LIMIT 100", $attempt_id ), ARRAY_A );
	}

	private static function is_recent_duplicate( int $attempt_id, string $event_type ): bool {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t['security_logs']} WHERE attempt_id=%d AND event_type=%s AND created_at >= DATE_SUB(%s, INTERVAL 2 SECOND)",
				$attempt_id,
				$event_type,
				current_time( 'mysql' )
			)
		);
	}

	private static function maybe_apply_attempt_threshold( array $attempt, string $event_type ): array {
		$block_count = self::count_security_blocks( (int) $attempt['id'] );
		if ( ! self::is_exit_event( $event_type ) ) {
			return array( 'action' => 'warning', 'warning_count' => $block_count, 'message' => '' );
		}
		if ( 'submitted_locked' === ( $attempt['status'] ?? '' ) ) {
			LogiEval_Secure_Evaluations::finalize_submitted_lock( $attempt );
			$message = __( 'Votre évaluation est terminée définitivement. Vous ne pourrez pas la reprendre.', 'logieval-secure' );
			return array( 'action' => 'submitted', 'warning_count' => $block_count, 'message' => $message );
		}
		if ( 'security_paused' === ( $attempt['status'] ?? '' ) ) {
			return array(
				'action'        => 'blocked',
				'warning_count' => max( 1, $block_count ),
				'message'       => __( 'Votre évaluation est bloquée. Attendez l’autorisation de votre enseignant pour reprendre.', 'logieval-secure' ),
			);
		}
		$max_attempts = LogiEval_Secure_Evaluations::max_exits();
		if ( $block_count >= $max_attempts - 1 ) {
			$message = sprintf(
				/* translators: %d: maximum exit attempts. */
				__( "%d tentatives de sortie ont été détectées. L’évaluation est terminée automatiquement et signalée à l’enseignant.", 'logieval-secure' ),
				$max_attempts
			);
			LogiEval_Secure_Evaluations::terminate_attempt_for_security( $attempt, $message );
			return array( 'action' => 'terminated', 'warning_count' => $max_attempts, 'message' => $message );
		}
		$next_count = $block_count + 1;
		$message    = sprintf(
			/* translators: 1: warning count, 2: maximum warning count. */
			__( 'Tentative de sortie détectée (%1$d/%2$d). L’évaluation est bloquée jusqu’à validation de l’enseignant.', 'logieval-secure' ),
			$next_count,
			$max_attempts
		);
		LogiEval_Secure_Evaluations::pause_attempt_for_security( $attempt, $message );
		return array( 'action' => 'blocked', 'warning_count' => $next_count, 'message' => $message );
	}

	private static function is_exit_event( string $event_type ): bool {
		$events = array(
			'tab_hidden',
			'focus_lost',
			'fullscreen_exit',
			'navigation_blocked',
			'form_navigation_blocked',
			'evaluation_exit_attempt',
			'evaluation_exit_confirmed',
			'post_submit_exit_attempt',
			'post_submit_exit_confirmed',
			'ai_tool_detected',
			'side_panel',
		);
		return in_array( $event_type, $events, true );
	}

	private static function count_security_blocks( int $attempt_id ): int {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t['security_logs']} WHERE attempt_id=%d AND event_type=%s AND id > (SELECT COALESCE(MAX(r.id), 0) FROM {$t['security_logs']} r WHERE r.attempt_id=%d AND r.event_type=%s)",
				$attempt_id,
				'exam_blocked',
				$attempt_id,
				'teacher_reset_exits'
			)
		);
	}
}
