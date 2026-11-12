<?php
/**
 * LogiEval_Secure_Evaluations — partie « monitoring ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Monitoring {
	private static function render_incomplete_correction_alert( int $missing_count, int $attempt_id, ?array $evaluation ): void {
		$attempt = $attempt_id ? self::get_attempt( $attempt_id ) : null;
		$user    = $attempt ? get_userdata( (int) $attempt['user_id'] ) : null;
		$name    = $user ? $user->display_name : __( 'la copie sélectionnée', 'logieval-secure' );
		$url     = self::frontend_url(
			array(
				'logiexam_view' => 'corrections',
				'evaluation_id' => $evaluation ? absint( $evaluation['id'] ) : null,
			)
		);
		?>
		<div class="les-correction-alert-screen" role="alert" aria-live="assertive">
			<div class="les-correction-alert-icon">!</div>
			<div>
				<span class="les-kicker"><?php esc_html_e( 'Finalisation bloquée', 'logieval-secure' ); ?></span>
				<h3><?php esc_html_e( 'La correction n’est pas complète', 'logieval-secure' ); ?></h3>
				<p>
					<?php
					echo esc_html(
						sprintf(
							_n(
								'La finalisation de %2$s est impossible : %1$d question corrigible n’a pas encore de points attribués.',
								'La finalisation de %2$s est impossible : %1$d questions corrigibles n’ont pas encore de points attribués.',
								$missing_count,
								'logieval-secure'
							),
							$missing_count,
							$name
						)
					);
					?>
				</p>
				<p><?php esc_html_e( 'Votre progression a bien été sauvegardée. Complétez les points manquants, puis relancez la finalisation.', 'logieval-secure' ); ?></p>
				<p class="les-actions">
					<a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Retourner à la correction', 'logieval-secure' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

						private static function render_monitoring_global_body(): void {
				self::render_blocked_attempts_alert( self::get_blocked_attempts(), true );
				?>
				<p class="les-message"><?php esc_html_e( 'Ouvrez une évaluation (étape « Passation ») pour suivre chaque élève en détail.', 'logieval-secure' ); ?></p>
				<?php
			}

			/**
			 * Encadré bien visible listant les élèves bloqués, avec l'état de leur demande de déblocage.
			 */
			private static function render_blocked_attempts_alert( array $blocked_attempts, bool $show_evaluation ): void {
				if ( empty( $blocked_attempts ) ) {
					?>
					<p class="les-message"><?php esc_html_e( 'Aucun élève bloqué pour le moment.', 'logieval-secure' ); ?></p>
					<?php
					return;
				}
				$requests = count( array_filter( $blocked_attempts, static fn( $row ) => ! empty( $row['pending_request_at'] ) ) );
				?>
				<div class="les-blocked-alert" data-les-blocked-count="<?php echo esc_attr( count( $blocked_attempts ) ); ?>" data-les-request-count="<?php echo esc_attr( $requests ); ?>">
					<h3>
						<?php
						echo esc_html( sprintf( _n( '%d élève bloqué', '%d élèves bloqués', count( $blocked_attempts ), 'logieval-secure' ), count( $blocked_attempts ) ) );
						if ( $requests ) {
							echo ' — ' . esc_html( sprintf( _n( '%d demande de déblocage en attente', '%d demandes de déblocage en attente', $requests, 'logieval-secure' ), $requests ) );
						}
						?>
					</h3>
					<div class="les-table-scroll">
						<table class="widefat striped">
							<thead><tr>
								<?php if ( $show_evaluation ) : ?><th><?php esc_html_e( 'Évaluation', 'logieval-secure' ); ?></th><?php endif; ?>
								<th><?php esc_html_e( 'Élève', 'logieval-secure' ); ?></th>
								<th><?php esc_html_e( 'Groupe classe', 'logieval-secure' ); ?></th>
								<th><?php esc_html_e( 'Bloqué depuis', 'logieval-secure' ); ?></th>
								<th><?php esc_html_e( 'Demande de déblocage', 'logieval-secure' ); ?></th>
								<th><?php esc_html_e( 'Action', 'logieval-secure' ); ?></th>
							</tr></thead>
							<tbody>
								<?php foreach ( $blocked_attempts as $blocked ) : ?>
									<tr class="<?php echo ! empty( $blocked['pending_request_at'] ) ? 'les-row-request' : ''; ?>">
										<?php if ( $show_evaluation ) : ?><td><strong><?php echo esc_html( $blocked['evaluation_title'] ?? '-' ); ?></strong></td><?php endif; ?>
										<td><strong><?php echo esc_html( $blocked['student_name'] ?? '-' ); ?></strong></td>
										<td><?php echo esc_html( ! empty( $blocked['group_label'] ) ? $blocked['group_label'] : '-' ); ?></td>
										<td><?php echo esc_html( ! empty( $blocked['blocked_since'] ) ? mysql2date( 'H:i:s', $blocked['blocked_since'] ) : '-' ); ?><?php if ( ! empty( $blocked['block_reason'] ) ) : ?><br><small><?php echo esc_html( $blocked['block_reason'] ); ?></small><?php endif; ?></td>
										<td>
											<?php if ( ! empty( $blocked['pending_request_at'] ) ) : ?>
												<span class="les-alert-badge is-critical"><?php echo esc_html( sprintf( __( 'Demandée à %s', 'logieval-secure' ), mysql2date( 'H:i:s', $blocked['pending_request_at'] ) ) ); ?></span>
											<?php else : ?>
												<span class="les-alert-badge is-warning"><?php esc_html_e( 'Pas encore demandée', 'logieval-secure' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php self::render_attempt_unlock_action( $blocked, array( 'page' => 'logiexam-monitoring', 'evaluation_id' => absint( $blocked['evaluation_id'] ?? 0 ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php
			}

		private static function render_monitoring_body( array $evaluation ): void {
			$attempts = self::get_attempts( (int) $evaluation['id'] );
			$total_questions = count( self::get_questions_for_evaluation( (int) $evaluation['id'] ) );
			$blocked_attempts = self::get_blocked_attempts( (int) $evaluation['id'] );
			$finished_count = 0;
			$in_progress_count = 0;
			$to_grade_count = 0;
			foreach ( $attempts as $monitor_attempt ) {
				$monitor_result = self::get_result_by_attempt( (int) $monitor_attempt['id'] );
				if ( self::monitoring_attempt_is_finished( $monitor_attempt ) ) {
					$finished_count++;
				} elseif ( 'in_progress' === (string) ( $monitor_attempt['status'] ?? '' ) ) {
					$in_progress_count++;
				}
				if ( self::attempt_needs_correction( $monitor_attempt, $monitor_result ) ) {
					$to_grade_count++;
				}
			}
			?>
			<h3><?php echo esc_html( $evaluation['title'] ); ?></h3>
			<?php self::render_attempt_control_notice(); ?>
			<?php if ( ! empty( $blocked_attempts ) ) { self::render_blocked_attempts_alert( $blocked_attempts, false ); } ?>
			<div class="les-stat-grid">
				<div class="les-stat"><strong><?php echo esc_html( count( $attempts ) ); ?></strong><span><?php esc_html_e( 'Élèves suivis', 'logieval-secure' ); ?></span></div>
				<div class="les-stat"><strong><?php echo esc_html( $finished_count ); ?></strong><span><?php esc_html_e( 'Terminés', 'logieval-secure' ); ?></span></div>
				<div class="les-stat"><strong><?php echo esc_html( $in_progress_count ); ?></strong><span><?php esc_html_e( 'En cours', 'logieval-secure' ); ?></span></div>
				<div class="les-stat"><strong><?php echo esc_html( $to_grade_count ); ?></strong><span><?php esc_html_e( 'À corriger', 'logieval-secure' ); ?></span></div>
				<div class="les-stat"><strong><?php echo esc_html( count( $blocked_attempts ) ); ?></strong><span><?php esc_html_e( 'Bloqués', 'logieval-secure' ); ?></span></div>
			</div>
			<div class="les-table-scroll">
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Élève', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Groupe classe', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Statut', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Temps restant', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Questions complétées', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Questions restantes', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Avancement', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Blocage', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Dernière activité', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Action', 'logieval-secure' ); ?></th></tr></thead>
					<tbody>
						<?php if ( empty( $attempts ) ) : ?>
							<tr><td colspan="10"><?php esc_html_e( 'Aucune tentative élève pour cette évaluation pour le moment.', 'logieval-secure' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $attempts as $attempt ) : $user = get_userdata( (int) $attempt['user_id'] ); $answers = self::get_answers_for_attempt( (int) $attempt['id'] ); $is_finished = self::monitoring_attempt_is_finished( $attempt ); $completed = $is_finished ? $total_questions : self::completed_answer_count( $answers ); $remaining = $is_finished ? 0 : max( 0, $total_questions - $completed ); $percent = $total_questions ? round( $completed / $total_questions * 100 ) : 0; $result = self::get_result_by_attempt( (int) $attempt['id'] ); $logs = LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] ); $is_blocked = self::attempt_needs_teacher_unlock( $attempt, $logs ); ?>
							<tr>
								<td><?php echo esc_html( $user ? $user->display_name : '#' . $attempt['user_id'] ); ?><?php $acc_sum = self::accommodation_summary( (int) $attempt['user_id'] ); if ( '' !== $acc_sum ) : ?> <span class="les-acc-badge" title="<?php esc_attr_e( 'Aménagements', 'logieval-secure' ); ?>"><?php echo esc_html( $acc_sum ); ?></span><?php endif; ?></td>
								<td><?php echo esc_html( self::group_label_for_user( $evaluation, (int) $attempt['user_id'] ) ?: '-' ); ?></td>
								<td><?php echo esc_html( $is_finished ? self::monitoring_finished_status_label( $attempt, $result ) : self::attempt_status_label( (string) $attempt['status'], $result['status'] ?? '' ) ); ?></td>
								<td class="les-time-cell"><?php self::render_time_left_cell( $attempt, $evaluation, $is_finished, $is_blocked ); ?></td>
								<td><?php echo esc_html( $completed . ' / ' . $total_questions ); ?></td>
								<td><?php echo esc_html( $remaining ); ?></td>
								<td><div class="les-progress-inline"><span style="width:<?php echo esc_attr( $percent ); ?>%"></span></div><strong><?php echo esc_html( $percent ); ?>%</strong></td>
								<td><?php $pending_request_at = $is_blocked ? self::pending_unlock_request_at( $logs ) : ''; ?><?php if ( ! $is_blocked ) : ?><span class="les-alert-badge is-ok"><?php esc_html_e( 'OK', 'logieval-secure' ); ?></span><?php elseif ( $pending_request_at ) : ?><span class="les-alert-badge is-critical"><?php esc_html_e( 'Bloqué - déblocage demandé', 'logieval-secure' ); ?></span><br><small><?php echo esc_html( sprintf( __( 'Demande envoyée à %s', 'logieval-secure' ), mysql2date( 'H:i:s', $pending_request_at ) ) ); ?></small><?php else : ?><span class="les-alert-badge is-critical"><?php esc_html_e( 'Bloqué', 'logieval-secure' ); ?></span><br><small><?php esc_html_e( 'Demande de déblocage pas encore envoyée', 'logieval-secure' ); ?></small><?php endif; ?></td>
								<td><?php echo esc_html( $attempt['last_seen_at'] ?: '-' ); ?></td>
								<td>
									<?php if ( $is_blocked ) : ?>
										<?php self::render_attempt_unlock_action( $attempt, array( 'page' => 'logiexam-monitoring', 'evaluation_id' => absint( $evaluation['id'] ) ) ); ?>
									<?php endif; ?>
									<?php self::render_attempt_control_menu( $attempt, $logs ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}

	/** Temps restant d'un élève (décompte en direct dans le navigateur). */
	private static function render_time_left_cell( array $attempt, array $evaluation, bool $is_finished, bool $is_blocked ): void {
		if ( $is_finished ) {
			echo '<span class="description">-</span>';
			return;
		}
		$duration = (int) ( $evaluation['duration_minutes'] ?? 0 );
		if ( empty( $attempt['started_at'] ) ) {
			echo '<span class="les-time-left is-waiting">' . esc_html__( 'Pas commencé', 'logieval-secure' ) . '</span>';
			if ( $duration ) {
				echo '<br><small>' . esc_html( sprintf( __( '%d min prévues', 'logieval-secure' ), $duration ) ) . '</small>';
			}
			return;
		}
		if ( empty( $attempt['expires_at'] ) ) {
			echo '<span class="les-time-left is-waiting">' . esc_html__( 'Sans limite', 'logieval-secure' ) . '</span>';
			return;
		}
		$end  = (int) strtotime( (string) $attempt['expires_at'] . ' UTC' );
		$left = $end ? $end - time() : 0;
		if ( $is_blocked ) {
			// Pendant un blocage, le temps est mis de côté : il est rendu au déblocage.
			$since = self::unresolved_security_block_started_at( (int) $attempt['id'] );
			$dt    = $since ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $since, wp_timezone() ) : false;
			if ( $dt && $end ) {
				$left = $end - $dt->getTimestamp();
			}
			echo '<span class="les-time-left is-paused" title="' . esc_attr__( 'Le temps est arrêté pendant le blocage : il est rendu au déblocage.', 'logieval-secure' ) . '">⏸ ' . esc_html( self::format_time_left( max( 0, $left ) ) ) . '</span><br><small>' . esc_html__( 'arrêté (bloqué)', 'logieval-secure' ) . '</small>';
			return;
		}
		$class = $left <= 0 ? 'is-over' : ( $left <= 5 * MINUTE_IN_SECONDS ? 'is-low' : '' );
		echo '<span class="les-time-left ' . esc_attr( $class ) . '" data-les-time-left="' . esc_attr( max( 0, $left ) ) . '">' . esc_html( $left > 0 ? self::format_time_left( $left ) : __( 'Temps écoulé', 'logieval-secure' ) ) . '</span>';
		echo '<br><small>' . esc_html( sprintf( __( 'fin à %s', 'logieval-secure' ), wp_date( 'H:i', $end ) ) ) . '</small>';
	}

	private static function format_time_left( int $seconds ): string {
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;
		return $h ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%02d:%02d', $m, $s );
	}

	private static function get_blocked_attempts( int $evaluation_id = 0 ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$where = "a.submitted_at IS NULL
			AND a.status NOT IN ('submitted', 'submitted_locked', 'graded', 'security_terminated')
			AND e.status <> 'deleted'
			AND (a.status IN ('security_paused', 'locked_exit_attempt') OR l.event_type IN ('exam_blocked', 'unlock_requested'))";
		$params = array();
		if ( $evaluation_id ) {
			$where .= ' AND a.evaluation_id=%d';
			$params[] = $evaluation_id;
		}
		if ( ! self::admin_sees_all() ) {
			$where .= ' AND e.created_by=%d';
			$params[] = get_current_user_id();
		}
		$sql = "SELECT DISTINCT a.*, e.title AS evaluation_title
			FROM {$t['attempts']} a
			INNER JOIN {$t['evaluations']} e ON e.id=a.evaluation_id
			LEFT JOIN {$t['security_logs']} l ON l.attempt_id=a.id
			WHERE {$where}
			ORDER BY a.last_seen_at DESC, a.id DESC";
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$rows = array_values(
			array_filter(
				(array) $rows,
				static function ( array $row ): bool {
					return self::attempt_needs_teacher_unlock( $row );
				}
			)
		);
		foreach ( $rows as &$row ) {
			$user = get_userdata( (int) $row['user_id'] );
			$evaluation = self::get_evaluation( (int) $row['evaluation_id'] );
			$logs = LogiEval_Secure_Security::get_logs_for_attempt( (int) $row['id'] );
			$row['student_name'] = $user ? $user->display_name : '#' . (int) $row['user_id'];
			$row['group_label'] = $evaluation ? self::group_label_for_user( $evaluation, (int) $row['user_id'] ) : '';
			$row['block_count'] = self::security_block_count_from_logs( $logs );
			$row['last_alert'] = ! empty( $logs ) ? self::security_event_label( (string) $logs[0]['event_type'] ) . ' - ' . (string) $logs[0]['created_at'] : '';
			$row['pending_request_at'] = self::pending_unlock_request_at( $logs );
			$row['blocked_since'] = self::unresolved_security_block_started_at( (int) $row['id'] );
			$row['block_reason'] = self::last_exit_reason_label( $logs );
		}
		unset( $row );
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				// Les demandes de déblocage en attente apparaissent en premier.
				return (int) empty( $a['pending_request_at'] ) <=> (int) empty( $b['pending_request_at'] );
			}
		);
		return $rows;
	}

	private static function pending_unlock_request_at( array $logs ): string {
		$last_unlock_id = 0;
		foreach ( $logs as $log ) {
			if ( 'teacher_unlock' === (string) ( $log['event_type'] ?? '' ) ) {
				$last_unlock_id = max( $last_unlock_id, (int) ( $log['id'] ?? 0 ) );
			}
		}
		$latest_id = 0;
		$latest_at = '';
		foreach ( $logs as $log ) {
			$id = (int) ( $log['id'] ?? 0 );
			if ( 'unlock_requested' === (string) ( $log['event_type'] ?? '' ) && $id > $last_unlock_id && $id > $latest_id ) {
				$latest_id = $id;
				$latest_at = (string) ( $log['created_at'] ?? '' );
			}
		}
		return $latest_at;
	}

	private static function last_exit_reason_label( array $logs ): string {
		foreach ( $logs as $log ) {
			$type = (string) ( $log['event_type'] ?? '' );
			if ( in_array( $type, self::security_exit_event_types(), true ) ) {
				return self::security_event_label( $type );
			}
		}
		return '';
	}

	private static function render_security_logs_details( array $logs, array $attempt ): void {
		$exit_count = self::security_exit_count_from_logs( $logs );
		?>
		<div class="les-security-summary <?php echo esc_attr( empty( $logs ) ? 'is-ok' : 'is-warning' ); ?>">
			<div>
				<strong><?php esc_html_e( 'Blocages', 'logieval-secure' ); ?></strong>
				<span><?php echo esc_html( sprintf( _n( '%d blocage', '%d blocages', self::security_block_count_from_logs( $logs ), 'logieval-secure' ), self::security_block_count_from_logs( $logs ) ) ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Alertes sécurité', 'logieval-secure' ); ?></strong>
				<span><?php $merged_count = count( self::merged_security_logs( $logs ) ); echo esc_html( sprintf( _n( '%d événement enregistré', '%d événements enregistrés', $merged_count, 'logieval-secure' ), $merged_count ) ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Tentatives de sortie', 'logieval-secure' ); ?></strong>
				<span><?php echo esc_html( sprintf( __( '%1$d / %2$d', 'logieval-secure' ), $exit_count, self::max_exits() ) ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Statut sécurité', 'logieval-secure' ); ?></strong>
				<span><?php echo esc_html( self::security_status_label( $logs, $attempt ) ); ?></span>
			</div>
		</div>
		<?php if ( empty( $logs ) ) : ?>
			<p class="les-message"><?php esc_html_e( 'Aucune alerte de sécurité détectée pour cette copie.', 'logieval-secure' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat striped les-security-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'logieval-secure' ); ?></th>
					<th><?php esc_html_e( 'Alerte détectée', 'logieval-secure' ); ?></th>
					<th><?php esc_html_e( 'Niveau', 'logieval-secure' ); ?></th>
					<th><?php esc_html_e( 'Détails', 'logieval-secure' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( self::merged_security_logs( $logs ) as $group ) : ?>
					<tr>
						<td><?php echo esc_html( $group['created_at'] ); ?></td>
						<td><?php echo esc_html( implode( ' + ', $group['labels'] ) ); ?><br><code><?php echo esc_html( implode( ', ', array_unique( $group['types'] ) ) ); ?></code></td>
						<td><?php echo esc_html( self::security_severity_label( $group['severity'] ) ); ?></td>
						<td><?php echo wp_kses_post( implode( '<br>', array_map( array( __CLASS__, 'format_security_event_details' ), $group['details'] ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function monitoring_attempt_is_finished( array $attempt ): bool {
		return ! empty( $attempt['submitted_at'] )
			|| in_array( (string) ( $attempt['status'] ?? '' ), array( 'submitted', 'submitted_locked', 'graded', 'security_terminated' ), true );
	}

	private static function monitoring_finished_status_label( array $attempt, ?array $result ): string {
		if ( self::attempt_is_cheating_cancelled( $attempt ) ) {
			return sprintf( __( 'Annulée – tricherie (%d sorties)', 'logieval-secure' ), self::max_exits() );
		}
		if ( 'graded' === (string) ( $result['status'] ?? '' ) || 'graded' === (string) ( $attempt['status'] ?? '' ) ) {
			return __( 'Terminée - corrigée', 'logieval-secure' );
		}
		if ( self::attempt_needs_correction( $attempt, $result ) ) {
			return __( 'Terminée - à corriger', 'logieval-secure' );
		}
		return __( 'Terminée', 'logieval-secure' );
	}

	private static function security_exit_count_from_logs( array $logs ): int {
		$logs  = self::logs_since_exit_reset( $logs );
		$count = 0;
		foreach ( $logs as $log ) {
			if ( in_array( (string) ( $log['event_type'] ?? '' ), self::security_exit_event_types(), true ) ) {
				$count++;
			}
		}
		return $count;
	}

	private static function security_exit_event_types(): array {
		return array(
			'tab_hidden',
			'focus_lost',
			'fullscreen_exit',
			'navigation_blocked',
			'form_navigation_blocked',
			'evaluation_exit_attempt',
			'evaluation_exit_confirmed',
			'post_submit_exit_attempt',
			'post_submit_exit_confirmed',
			'evaluation_reentry',
			'ai_tool_detected',
			'side_panel',
		);
	}

	private static function security_block_count_from_logs( array $logs ): int {
		$logs  = self::logs_since_exit_reset( $logs );
		$count = 0;
		foreach ( $logs as $log ) {
			if ( 'exam_blocked' === (string) ( $log['event_type'] ?? '' ) ) {
				$count++;
			}
		}
		return $count;
	}

	private static function attempt_has_teacher_unlock_after_block( int $attempt_id ): bool {
		$logs = LogiEval_Secure_Security::get_logs_for_attempt( $attempt_id );
		$last_block_id = 0;
		$last_unlock_id = 0;
		foreach ( $logs as $log ) {
			$type = (string) ( $log['event_type'] ?? '' );
			$id   = (int) ( $log['id'] ?? 0 );
			if ( 'exam_blocked' === $type && $id > $last_block_id ) {
				$last_block_id = $id;
			}
			if ( 'teacher_unlock' === $type && $id > $last_unlock_id ) {
				$last_unlock_id = $id;
			}
		}
		return $last_block_id > 0 && $last_unlock_id > $last_block_id;
	}

	private static function attempt_has_valid_teacher_unlock( int $attempt_id ): bool {
		$logs            = LogiEval_Secure_Security::get_logs_for_attempt( $attempt_id );
		$last_request_id = 0;
		$last_unlock_id  = 0;
		foreach ( $logs as $log ) {
			$type = (string) ( $log['event_type'] ?? '' );
			$id   = (int) ( $log['id'] ?? 0 );
			if ( in_array( $type, array( 'exam_blocked', 'unlock_requested' ), true ) && $id > $last_request_id ) {
				$last_request_id = $id;
			}
			if ( 'teacher_unlock' === $type && $id > $last_unlock_id ) {
				$last_unlock_id = $id;
			}
		}

		return $last_unlock_id > 0 && $last_unlock_id > $last_request_id;
	}

	private static function unresolved_security_block_started_at( int $attempt_id ): string {
		$logs           = LogiEval_Secure_Security::get_logs_for_attempt( $attempt_id );
		$last_unlock_id = 0;
		foreach ( $logs as $log ) {
			if ( 'teacher_unlock' === (string) ( $log['event_type'] ?? '' ) ) {
				$last_unlock_id = max( $last_unlock_id, (int) ( $log['id'] ?? 0 ) );
			}
		}

		$started_at = '';
		foreach ( array_reverse( $logs ) as $log ) {
			$id   = (int) ( $log['id'] ?? 0 );
			$type = (string) ( $log['event_type'] ?? '' );
			if ( $id <= $last_unlock_id || 'exam_blocked' !== $type ) {
				continue;
			}
			$started_at = (string) ( $log['created_at'] ?? '' );
			break;
		}

		return $started_at;
	}

	private static function extend_attempt_deadline_for_security_pause( array $attempt ): string {
		if ( empty( $attempt['expires_at'] ) ) {
			return '';
		}

		$blocked_at = self::unresolved_security_block_started_at( (int) $attempt['id'] );
		// Les journaux sont enregistrés avec current_time() : on les relit dans le fuseau horaire WordPress.
		$blocked_dt = $blocked_at ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $blocked_at, wp_timezone() ) : false;
		$blocked_ts = $blocked_dt ? $blocked_dt->getTimestamp() : 0;
		if ( ! $blocked_ts ) {
			return (string) $attempt['expires_at'];
		}

		$pause_seconds = max( 0, time() - $blocked_ts );
		if ( $pause_seconds < 1 ) {
			return (string) $attempt['expires_at'];
		}

		$expires_ts = strtotime( (string) $attempt['expires_at'] . ' UTC' );
		if ( ! $expires_ts ) {
			return (string) $attempt['expires_at'];
		}

		return gmdate( 'Y-m-d H:i:s', $expires_ts + $pause_seconds );
	}

	private static function attempt_needs_teacher_unlock( array $attempt, ?array $logs = null ): bool {
		$status = (string) ( $attempt['status'] ?? '' );
		if ( in_array( $status, array( 'security_paused', 'locked_exit_attempt' ), true ) ) {
			return true;
		}
		if ( ! empty( $attempt['submitted_at'] ) || in_array( $status, array( 'submitted', 'submitted_locked', 'graded', 'security_terminated' ), true ) ) {
			return false;
		}
		$logs = is_array( $logs ) ? $logs : LogiEval_Secure_Security::get_logs_for_attempt( (int) ( $attempt['id'] ?? 0 ) );
		$last_request_id = 0;
		$last_unlock_id  = 0;
		foreach ( $logs as $log ) {
			$type = (string) ( $log['event_type'] ?? '' );
			$id   = (int) ( $log['id'] ?? 0 );
			if ( in_array( $type, array( 'exam_blocked', 'unlock_requested' ), true ) && $id > $last_request_id ) {
				$last_request_id = $id;
			}
			if ( 'teacher_unlock' === $type && $id > $last_unlock_id ) {
				$last_unlock_id = $id;
			}
		}
		return $last_request_id > 0 && $last_request_id > $last_unlock_id;
	}

	private static function security_status_label( array $logs, array $attempt ): string {
		if ( 'security_terminated' === ( $attempt['status'] ?? '' ) ) {
			return __( 'Évaluation terminée automatiquement', 'logieval-secure' );
		}
		if ( 'security_paused' === ( $attempt['status'] ?? '' ) ) {
			return __( 'Reprise bloquée - validation enseignant requise', 'logieval-secure' );
		}
		if ( in_array( 'critical', array_column( $logs, 'severity' ), true ) ) {
			return __( 'Critique', 'logieval-secure' );
		}
		return empty( $logs ) ? __( 'Aucune alerte', 'logieval-secure' ) : __( 'À contrôler', 'logieval-secure' );
	}

	private static function security_event_label( string $event_type ): string {
		$labels = array(
			'tab_hidden'                  => __( 'Changement d’onglet ou page masquée', 'logieval-secure' ),
			'tab_visible'                 => __( 'Retour sur la page', 'logieval-secure' ),
			'window_blur'                 => __( 'Fenêtre quittée', 'logieval-secure' ),
			'window_focus'                => __( 'Retour dans la fenêtre', 'logieval-secure' ),
			'focus_lost'                  => __( 'Perte de focus', 'logieval-secure' ),
			'fullscreen_exit'             => __( 'Sortie du plein écran', 'logieval-secure' ),
			'fullscreen_enter'            => __( 'Entrée en plein écran', 'logieval-secure' ),
			'right_click'                 => __( 'Clic droit bloqué', 'logieval-secure' ),
			'copy_attempt'                => __( 'Tentative de copie', 'logieval-secure' ),
			'cut_attempt'                 => __( 'Tentative de couper', 'logieval-secure' ),
			'paste_attempt'               => __( 'Tentative de coller', 'logieval-secure' ),
			'print_attempt'               => __( 'Tentative d’impression', 'logieval-secure' ),
			'keyboard_shortcut'           => __( 'Raccourci clavier sensible', 'logieval-secure' ),
			'window_resize'               => __( 'Redimensionnement de fenêtre', 'logieval-secure' ),
			'navigation_blocked'          => __( 'Navigation bloquée', 'logieval-secure' ),
			'form_navigation_blocked'     => __( 'Navigation par formulaire bloquée', 'logieval-secure' ),
			'evaluation_exit_attempt'     => __( 'Tentative de sortie pendant l’évaluation', 'logieval-secure' ),
			'evaluation_exit_confirmed'   => __( 'Sortie confirmée pendant l’évaluation', 'logieval-secure' ),
			'post_submit_exit_attempt'    => __( 'Tentative de sortie pendant le verrouillage', 'logieval-secure' ),
			'post_submit_exit_confirmed'  => __( 'Sortie confirmée pendant le verrouillage', 'logieval-secure' ),
			'exam_auto_submitted'         => __( 'Évaluation terminée automatiquement', 'logieval-secure' ),
			'time_expired_auto_submit'    => __( 'Temps écoulé - clôture automatique', 'logieval-secure' ),
			'unlock_requested'            => __( 'Demande de déblocage', 'logieval-secure' ),
			'teacher_unlock'              => __( 'Reprise autorisée par l’enseignant', 'logieval-secure' ),
			'fullscreen_exit_recovered'   => __( 'Plein écran quitté puis rétabli à temps (pas de blocage)', 'logieval-secure' ),
			'ai_tool_detected'            => __( 'Outil d’IA ou extension détecté dans la page (blocage)', 'logieval-secure' ),
			'screenshot_attempt'          => __( 'Tentative de capture d’écran (touche Impr. écran ou raccourci de capture)', 'logieval-secure' ),
			'unlock_deadline_expired'     => __( 'Pas de demande de déblocage dans le délai : évaluation clôturée (note 1)', 'logieval-secure' ),
			'seb_refused'                 => __( 'Accès refusé : Safe Exam Browser exigé (navigateur ordinaire ou autre configuration)', 'logieval-secure' ),
			'seb_focus_info'              => __( 'Safe Exam Browser : barre du programme utilisée (non sanctionné)', 'logieval-secure' ),
			'side_panel'                  => __( 'Panneau latéral ouvert à côté de l’évaluation (assistant IA du navigateur possible)', 'logieval-secure' ),
			'text_inserted'               => __( 'Texte inséré sans frappe au clavier (à vérifier)', 'logieval-secure' ),
			'drop_attempt'                => __( 'Glisser-déposer de texte bloqué', 'logieval-secure' ),
			'ai_tool_detected_start'      => __( 'Outil d’IA ou extension détecté avant le début (accès refusé)', 'logieval-secure' ),
			'navigation_prevented'        => __( 'Clic sur un lien hors de l’évaluation (désactivé, pas de sortie)', 'logieval-secure' ),
			'devtools_shortcut'           => __( 'Tentative d’afficher le code de la page (bloquée)', 'logieval-secure' ),
			'device_sleep'                => __( 'Ordinateur en veille (pas compté comme une sortie)', 'logieval-secure' ),
			'teacher_reset_exits'         => __( 'Blocages effacés par l’enseignant (compteur à zéro)', 'logieval-secure' ),
			'teacher_add_time'            => __( 'Temps ajouté par l’enseignant', 'logieval-secure' ),
			'teacher_restore'             => __( 'Sanction annulée, copie rouverte par l’enseignant', 'logieval-secure' ),
			'teacher_reopen'              => __( 'Copie rouverte par l’enseignant', 'logieval-secure' ),
			'evaluation_reentry'          => __( 'Page rechargée / rouverte pendant l’évaluation', 'logieval-secure' ),
			'student_resume_after_unlock' => __( 'Reprise par l’élève après déblocage', 'logieval-secure' ),
			'exam_blocked'                => __( 'Évaluation bloquée', 'logieval-secure' ),
		);
		return $labels[ $event_type ] ?? $event_type;
	}

	/**
	 * Alertes regroupées : les évènements enregistrés à la même seconde (ex. « Perte de focus » puis
	 * « Évaluation bloquée ») ne forment qu'une alerte, au niveau le plus élevé.
	 * @return array<int, array{created_at:string, severity:string, labels:string[], types:string[], details:string[]}>
	 */
	public static function merged_security_logs( array $logs ): array {
		$rank   = array( 'info' => 0, 'warning' => 1, 'critical' => 2 );
		$groups = array();
		foreach ( $logs as $log ) {
			$at   = (string) ( $log['created_at'] ?? '' );
			$last = count( $groups ) - 1;
			if ( $last < 0 || $groups[ $last ]['created_at'] !== $at ) {
				$groups[] = array( 'created_at' => $at, 'severity' => 'info', 'labels' => array(), 'types' => array(), 'details' => array() );
				$last++;
			}
			$sev = sanitize_key( (string) ( $log['severity'] ?? 'info' ) );
			if ( ( $rank[ $sev ] ?? 0 ) >= ( $rank[ $groups[ $last ]['severity'] ] ?? 0 ) ) {
				$groups[ $last ]['severity'] = $sev;
			}
			$label = self::security_event_label( (string) $log['event_type'] );
			if ( ! in_array( $label, $groups[ $last ]['labels'], true ) ) {
				$groups[ $last ]['labels'][] = $label;
			}
			$groups[ $last ]['types'][] = (string) $log['event_type'];
			$detail = (string) ( $log['event_data'] ?? '' );
			if ( '' !== trim( $detail ) && ! in_array( $detail, $groups[ $last ]['details'], true ) ) {
				$groups[ $last ]['details'][] = $detail;
			}
		}
		// L'évènement le plus grave d'abord (ex. « Évaluation bloquée + Perte de focus »).
		foreach ( $groups as &$g ) {
			$g['labels'] = array_reverse( $g['labels'] );
			$g['details'] = array_reverse( $g['details'] );
		}
		unset( $g );
		return $groups;
	}

	private static function security_severity_label( string $severity ): string {
		$labels = array(
			'info'     => __( 'Information', 'logieval-secure' ),
			'warning'  => __( 'Attention', 'logieval-secure' ),
			'critical' => __( 'Critique', 'logieval-secure' ),
		);
		return $labels[ $severity ] ?? $severity;
	}

	private static function format_security_event_details( string $event_data ): string {
		$event_data = trim( $event_data );
		if ( '' === $event_data ) {
			return esc_html__( 'Aucun détail supplémentaire.', 'logieval-secure' );
		}
		$decoded = json_decode( $event_data, true );
		if ( ! is_array( $decoded ) ) {
			return esc_html( $event_data );
		}
		$parts = array();
		$labels = array(
			'reason'     => __( 'Raison', 'logieval-secure' ),
			'url'        => __( 'URL', 'logieval-secure' ),
			'width'      => __( 'Largeur fenêtre', 'logieval-secure' ),
			'height'     => __( 'Hauteur fenêtre', 'logieval-secure' ),
			'user_agent' => __( 'Navigateur', 'logieval-secure' ),
			'key'        => __( 'Touche', 'logieval-secure' ),
		);
		foreach ( $labels as $key => $label ) {
			if ( isset( $decoded[ $key ] ) && '' !== (string) $decoded[ $key ] ) {
				$parts[] = '<strong>' . esc_html( $label ) . ' :</strong> ' . esc_html( (string) $decoded[ $key ] );
			}
		}
		if ( empty( $parts ) ) {
			return esc_html( wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE ) ?: $event_data );
		}
		return implode( '<br>', $parts );
	}

	private static function render_security_alert_badge( array $logs, array $attempt ): void {
		$count = count( $logs );
		if ( ! $count ) {
			echo '<span class="les-alert-badge is-ok">' . esc_html__( 'Aucune', 'logieval-secure' ) . '</span>';
			return;
		}
		$has_critical = in_array( 'critical', array_column( $logs, 'severity' ), true ) || 'locked_exit_attempt' === ( $attempt['status'] ?? '' );
		$class = $has_critical ? 'is-critical' : 'is-warning';
		$label = $has_critical ? __( 'Critique', 'logieval-secure' ) : __( 'Attention', 'logieval-secure' );
		echo '<span class="les-alert-badge ' . esc_attr( $class ) . '">' . esc_html( sprintf( '%s - %d', $label, $count ) ) . '</span>';
	}

		private static function render_attempt_unlock_action( array $attempt, array $redirect_args = array() ): void {
			if ( ! self::attempt_needs_teacher_unlock( $attempt ) ) {
				return;
			}
			if ( empty( $redirect_args ) ) {
				$redirect_args = array( 'logiexam_view' => 'corrections', 'evaluation_id' => absint( $attempt['evaluation_id'] ) );
			}
			$redirect_page = ! empty( $redirect_args['page'] ) ? sanitize_key( (string) $redirect_args['page'] ) : '';
			$frontend_args = $redirect_args;
			unset( $frontend_args['page'] );
			?>
				<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="logieval_secure_unlock_attempt">
					<input type="hidden" name="attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">
					<?php if ( $redirect_page ) : ?>
						<input type="hidden" name="redirect_page" value="<?php echo esc_attr( $redirect_page ); ?>">
					<?php endif; ?>
					<?php self::frontend_redirect_field( $frontend_args ); ?>
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Débloquer l’élève', 'logieval-secure' ); ?></button>
			</form>
			<?php
		}

		public static function handle_unlock_attempt(): void {
		self::require_teacher_post();
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$attempt_id = absint( $_POST['attempt_id'] ?? 0 );
		$attempt    = self::require_managed_attempt( $attempt_id );
		if ( ! self::attempt_needs_teacher_unlock( $attempt ) ) {
			wp_die( esc_html__( 'Copie non bloquée ou introuvable.', 'logieval-secure' ) );
		}

			self::unlock_attempt_by_teacher( $attempt );
			$redirect_page = sanitize_key( wp_unslash( $_POST['redirect_page'] ?? 'logiexam-corrections' ) );
			$fallback = 'logiexam-monitoring' === $redirect_page ? admin_url( 'admin.php?page=logiexam-monitoring&evaluation_id=' . absint( $attempt['evaluation_id'] ) . '&unlocked=1' ) : admin_url( 'admin.php?page=logiexam-corrections&evaluation_id=' . absint( $attempt['evaluation_id'] ) . '&unlocked=1' );
			$frontend_view = 'logiexam-monitoring' === $redirect_page ? 'monitoring' : 'corrections';
			wp_safe_redirect( self::post_redirect_url( $fallback, array( 'logiexam_view' => $frontend_view, 'evaluation_id' => absint( $attempt['evaluation_id'] ), 'unlocked' => 1 ) ) );
			exit;
		}

	private static function unlock_attempt_by_teacher( array $attempt ): void {
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$attempt_id = (int) $attempt['id'];
		// 1.33.7 : plus de temps rajouté en cachette au déblocage (durée du blocage). Le temps de l'élève ne change
		// que par « Ajouter du temps » : exactement les minutes choisies par l'enseignant.
		$update     = array(
			'status'       => 'in_progress',
			'last_seen_at' => current_time( 'mysql' ),
		);
		$wpdb->update( $t['attempts'], $update, array( 'id' => $attempt_id ) );
		LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'teacher_unlock', __( "L'enseignant a autorisé la reprise de l'évaluation.", 'logieval-secure' ), $attempt_id, 'info' );
	}

	public static function ajax_teacher_unlock(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
		}
		$attempt = self::get_attempt( absint( $_POST['attempt_id'] ?? 0 ) );
		if ( ! self::current_user_can_manage_attempt( $attempt ) ) {
			wp_send_json_error( array( 'message' => __( 'Copie introuvable.', 'logieval-secure' ) ), 404 );
		}
		if ( ! self::attempt_needs_teacher_unlock( $attempt ) ) {
			wp_send_json_success( array( 'already' => true, 'message' => __( 'Cet élève est déjà débloqué.', 'logieval-secure' ) ) );
		}
		self::unlock_attempt_by_teacher( $attempt );
		wp_send_json_success( array( 'message' => __( 'Élève débloqué. Son évaluation peut reprendre.', 'logieval-secure' ) ) );
	}

		public static function ajax_monitoring_snapshot(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
				wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
			}

				$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
				$evaluation = $evaluation_id ? self::get_evaluation( $evaluation_id ) : null;
				if ( $evaluation_id && ! self::can_manage_evaluation( $evaluation ) ) {
					wp_send_json_error( array( 'message' => __( 'Évaluation introuvable.', 'logieval-secure' ) ), 404 );
				}

				ob_start();
				if ( $evaluation ) {
					self::render_monitoring_body( $evaluation );
				} else {
					self::render_monitoring_global_body();
				}
			wp_send_json_success(
				array(
					'html'      => ob_get_clean(),
					'refreshed' => current_time( 'mysql' ),
				)
			);
		}

	private static function default_security_notice(): string {
		return (string) self::get_setting( 'default_security_notice', self::hardcoded_default_security_notice() );
	}

	private static function hardcoded_default_security_notice(): string {
		return __( "Pendant l’évaluation, vous ne devez pas quitter l’écran, changer d’onglet ou effectuer des recherches. Toute tentative de sortie est enregistrée et pourra être considérée comme une tricherie.", 'logieval-secure' );
	}

	private static function attempt_security_pause_seconds( int $attempt_id, int $until_timestamp ): int {
		$logs         = array_reverse( LogiEval_Secure_Security::get_logs_for_attempt( $attempt_id ) );
		$pause_start  = 0;
		$pause_seconds = 0;

		foreach ( $logs as $log ) {
			$type = (string) ( $log['event_type'] ?? '' );
			if ( ! in_array( $type, array( 'exam_blocked', 'teacher_unlock' ), true ) ) {
				continue;
			}

			$created_at = self::local_datetime_timestamp( (string) ( $log['created_at'] ?? '' ) );
			if ( ! $created_at ) {
				continue;
			}

			if ( 'exam_blocked' === $type && ! $pause_start ) {
				$pause_start = $created_at;
				continue;
			}

			if ( 'teacher_unlock' === $type && $pause_start ) {
				$pause_seconds += max( 0, min( $created_at, $until_timestamp ) - $pause_start );
				$pause_start = 0;
			}
		}

		if ( $pause_start ) {
			$pause_seconds += max( 0, $until_timestamp - $pause_start );
		}

		return $pause_seconds;
	}
}

