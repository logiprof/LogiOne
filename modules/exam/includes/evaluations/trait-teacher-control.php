<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LogiOne : main totale de l'enseignant sur les blocages d'un élève pendant une évaluation.
 *  - débloquer, effacer les blocages (compteur de sorties remis à zéro) ;
 *  - ajouter du temps ;
 *  - annuler une annulation pour tricherie et rouvrir la copie ;
 *  - rouvrir une copie envoyée (pas encore corrigée).
 * Les tables LogiExam ne changent pas de structure : chaque action est inscrite au journal de sécurité
 * (avec une sauvegarde de ce qui est remplacé).
 */
trait LogiEval_Secure_Evaluations_Teacher_Control {

	/** Types d'événements du journal ajoutés par ces actions. */
	private static function teacher_control_event_types(): array {
		return array( 'teacher_reset_exits', 'teacher_add_time', 'teacher_restore', 'teacher_reopen' );
	}

	/** Identifiant du dernier « compteur remis à zéro » (0 si aucun). */
	public static function last_exit_reset_id( array $logs ): int {
		$id = 0;
		foreach ( $logs as $log ) {
			if ( 'teacher_reset_exits' === (string) ( $log['event_type'] ?? '' ) ) {
				$id = max( $id, (int) ( $log['id'] ?? 0 ) );
			}
		}
		return $id;
	}

	/** Journaux postérieurs à la dernière remise à zéro du compteur. */
	private static function logs_since_exit_reset( array $logs ): array {
		$reset = self::last_exit_reset_id( $logs );
		if ( ! $reset ) {
			return $logs;
		}
		return array_values( array_filter( $logs, static fn( $log ) => (int) ( $log['id'] ?? 0 ) > $reset ) );
	}

	private static function control_ts_from_local( string $mysql ): int {
		if ( '' === $mysql ) {
			return 0;
		}
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql, wp_timezone() );
		return $dt ? $dt->getTimestamp() : 0;
	}

	private static function control_ts_from_gmt( string $mysql ): int {
		if ( '' === $mysql ) {
			return 0;
		}
		$ts = strtotime( $mysql . ' UTC' );
		return $ts ? (int) $ts : 0;
	}

	/** Ce que l'enseignant peut faire sur cette copie, selon son état. */
	private static function attempt_control_options( array $attempt, ?array $logs = null ): array {
		$logs    = is_array( $logs ) ? $logs : LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] );
		$status  = (string) ( $attempt['status'] ?? '' );
		$result  = self::get_result_by_attempt( (int) $attempt['id'] );
		$rstatus = (string) ( $result['status'] ?? '' );
		$blocked = self::attempt_needs_teacher_unlock( $attempt, $logs );
		$running = empty( $attempt['submitted_at'] ) && in_array( $status, array( 'in_progress', 'security_paused', 'locked_exit_attempt' ), true ) && ! empty( $attempt['started_at'] );
		$finished_not_graded = ! in_array( $rstatus, array( 'graded', 'archived', 'in_progress' ), true ) && 'graded' !== $status;
		return array(
			'blocked'     => $blocked,
			'unlock'      => $blocked,
			'reset_exits' => $running && ( $blocked || self::security_block_count_from_logs( $logs ) > 0 || self::security_exit_count_from_logs( $logs ) > 0 ),
			'add_time'    => $running && ! empty( $attempt['expires_at'] ),
			'restore'     => 'security_terminated' === $status && $finished_not_graded,
			'reopen'      => in_array( $status, array( 'submitted', 'submitted_locked' ), true ) && $finished_not_graded,
			'graded_lock' => in_array( $status, array( 'submitted', 'submitted_locked', 'security_terminated' ), true ) && ! $finished_not_graded,
			'exits'       => self::security_exit_count_from_logs( $logs ),
			'blocks'      => self::security_block_count_from_logs( $logs ),
			'reset_at'    => self::last_exit_reset_at( $logs ),
		);
	}

	private static function last_exit_reset_at( array $logs ): string {
		$id = self::last_exit_reset_id( $logs );
		foreach ( $logs as $log ) {
			if ( (int) ( $log['id'] ?? 0 ) === $id ) {
				return (string) ( $log['created_at'] ?? '' );
			}
		}
		return '';
	}

	private static function control_return_url(): string {
		if ( wp_doing_ajax() ) {
			$url = (string) wp_get_referer();
		} else {
			$url = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		}
		return remove_query_arg( array( 'les_ctl', 'les_ctl_error', 'unlocked' ), $url );
	}

	/** Menu « ⚙ Gérer » : toutes les actions possibles sur la copie d'un élève. */
	private static function render_attempt_control_menu( array $attempt, ?array $logs = null ): void {
		$o = self::attempt_control_options( $attempt, $logs );
		if ( ! $o['unlock'] && ! $o['reset_exits'] && ! $o['add_time'] && ! $o['restore'] && ! $o['reopen'] && ! $o['graded_lock'] ) {
			return;
		}
		$max    = self::max_exits();
		$return = self::control_return_url();
		$name   = self::user_display_name( (int) $attempt['user_id'] );
		$form   = static function ( string $op, string $label, string $class = 'button', string $confirm = '', bool $minutes = false ) use ( $attempt, $return ) {
			?>
			<form class="les-ctl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo $confirm ? ' onsubmit="return window.confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"' : ''; ?>>
				<input type="hidden" name="action" value="logieval_secure_attempt_control">
				<input type="hidden" name="op" value="<?php echo esc_attr( $op ); ?>">
				<input type="hidden" name="ctl_attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">
				<input type="hidden" name="les_ctl_return" value="<?php echo esc_attr( $return ); ?>">
				<input type="hidden" name="ctl_request" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php if ( $minutes ) : ?>
					<label class="les-ctl-minutes"><?php esc_html_e( 'Temps en plus', 'logieval-secure' ); ?>
						<select name="minutes">
							<option value="0"><?php esc_html_e( 'aucun', 'logieval-secure' ); ?></option>
							<option value="5">+5 min</option>
							<option value="10">+10 min</option>
							<option value="15">+15 min</option>
							<option value="30">+30 min</option>
						</select>
					</label>
				<?php endif; ?>
				<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
			</form>
			<?php
		};
		?>
		<details class="les-ctl" data-les-ctl="<?php echo esc_attr( $attempt['id'] ); ?>">
			<summary><?php esc_html_e( '⚙ Gérer', 'logieval-secure' ); ?><?php if ( $o['blocked'] ) : ?> <span class="les-ctl-dot" aria-hidden="true"></span><?php endif; ?></summary>
			<div class="les-ctl-body">
				<p class="les-ctl-state">
					<strong><?php echo esc_html( $name ); ?></strong> ·
					<?php echo esc_html( sprintf( __( 'Sorties : %1$d / %2$d', 'logieval-secure' ), $o['exits'], $max ) ); ?> ·
					<?php echo esc_html( sprintf( $o['blocks'] > 1 ? __( '%d blocages', 'logieval-secure' ) : __( '%d blocage', 'logieval-secure' ), $o['blocks'] ) ); ?>
					<?php if ( $o['reset_at'] ) : ?><br><small><?php echo esc_html( sprintf( __( 'Compteur remis à zéro le %s', 'logieval-secure' ), mysql2date( 'd.m.Y H:i', $o['reset_at'] ) ) ); ?></small><?php endif; ?>
				</p>
				<?php if ( $o['unlock'] ) : ?>
					<div class="les-ctl-row">
						<?php $form( 'unlock', __( 'Débloquer l’élève', 'logieval-secure' ), 'button button-primary' ); ?>
						<small><?php esc_html_e( 'Il reprend là où il en était. Le compteur de sorties est conservé. Le temps passé bloqué n’est pas rendu : ajoutez du temps ci-dessous si besoin.', 'logieval-secure' ); ?><?php
						$since = self::unresolved_security_block_started_at( (int) $attempt['id'] );
						$since_ts = $since ? self::site_time_to_ts( $since ) : 0;
						if ( $since_ts && time() > $since_ts ) {
							$mins = (int) floor( ( time() - $since_ts ) / MINUTE_IN_SECONDS );
							echo ' <strong>' . esc_html( $mins < 1 ? __( 'Bloqué depuis moins d’une minute.', 'logieval-secure' ) : sprintf( _n( 'Bloqué depuis %d min.', 'Bloqué depuis %d min.', $mins, 'logieval-secure' ), $mins ) ) . '</strong>';
						}
						?></small>
					</div>
				<?php endif; ?>
				<?php if ( $o['reset_exits'] ) : ?>
					<div class="les-ctl-row">
						<?php $form( 'reset_exits', $o['blocked'] ? __( 'Débloquer et effacer les blocages', 'logieval-secure' ) : __( 'Effacer les blocages (compteur à 0)', 'logieval-secure' ), $o['blocked'] ? 'button' : 'button' ); ?>
						<small><?php echo esc_html( sprintf( __( 'Le compteur repart à 0 / %d : l’élève a de nouveau droit à toutes ses sorties avant l’annulation.', 'logieval-secure' ), $max ) ); ?></small>
					</div>
				<?php endif; ?>
				<?php if ( $o['add_time'] ) : ?>
					<div class="les-ctl-row les-ctl-time">
						<span><?php esc_html_e( 'Ajouter du temps :', 'logieval-secure' ); ?></span>
						<?php foreach ( array( 5, 10, 15 ) as $m ) : ?>
							<form class="les-ctl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="logieval_secure_attempt_control">
								<input type="hidden" name="op" value="add_time">
								<input type="hidden" name="minutes" value="<?php echo esc_attr( $m ); ?>">
								<input type="hidden" name="ctl_attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">
								<input type="hidden" name="les_ctl_return" value="<?php echo esc_attr( $return ); ?>">
								<input type="hidden" name="ctl_request" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
								<?php wp_nonce_field( self::NONCE_ACTION ); ?>
								<button type="submit" class="button button-small">+<?php echo esc_html( $m ); ?> min</button>
							</form>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $o['restore'] ) : ?>
					<div class="les-ctl-row">
						<?php $form( 'restore', __( 'Annuler la sanction et rouvrir la copie', 'logieval-secure' ), 'button button-primary', sprintf( __( 'Annuler l’annulation pour tricherie de %s ? La note 1 est retirée, le compteur de sorties repart à zéro et l’élève pourra reprendre son évaluation avec son code, là où il en était.', 'logieval-secure' ), $name ), true ); ?>
						<small><?php esc_html_e( 'Ses réponses sont conservées. Il retrouve le temps qu’il lui restait (au moins 5 min).', 'logieval-secure' ); ?></small>
					</div>
				<?php endif; ?>
				<?php if ( $o['reopen'] ) : ?>
					<div class="les-ctl-row">
						<?php $form( 'reopen', __( 'Rouvrir la copie', 'logieval-secure' ), 'button', sprintf( __( 'Rouvrir la copie de %s ? Elle n’est plus envoyée : l’élève peut reprendre avec son code et devra la renvoyer.', 'logieval-secure' ), $name ), true ); ?>
						<small><?php esc_html_e( 'Ses réponses sont conservées. Il retrouve le temps qu’il lui restait (au moins 5 min).', 'logieval-secure' ); ?></small>
					</div>
				<?php endif; ?>
				<?php if ( $o['graded_lock'] ) : ?>
					<p class="les-ctl-note"><?php esc_html_e( 'La correction de cette copie a commencé ou est terminée : rouvrez d’abord la correction pour pouvoir rouvrir la copie.', 'logieval-secure' ); ?></p>
				<?php endif; ?>
			</div>
		</details>
		<?php
	}

	private static function render_attempt_control_notice(): void {
		$op = sanitize_key( wp_unslash( $_GET['les_ctl'] ?? '' ) );
		$er = sanitize_key( wp_unslash( $_GET['les_ctl_error'] ?? '' ) );
		$labels = array(
			'unlock'      => __( 'Élève débloqué : il peut reprendre son évaluation.', 'logieval-secure' ),
			'reset_exits' => __( 'Blocages effacés : le compteur de sorties de l’élève est remis à zéro.', 'logieval-secure' ),
			'add_time'    => __( 'Temps ajouté. Le chrono de l’élève se met à jour tout seul.', 'logieval-secure' ),
			'restore'     => __( 'Sanction annulée : l’élève peut reprendre son évaluation en entrant à nouveau son code.', 'logieval-secure' ),
			'reopen'      => __( 'Copie rouverte : l’élève peut reprendre son évaluation en entrant à nouveau son code.', 'logieval-secure' ),
		);
		$errors = array(
			'state'   => __( 'Action impossible : l’état de la copie a changé entre-temps. Rechargez la page.', 'logieval-secure' ),
			'graded'  => __( 'La correction de cette copie a commencé : rouvrez d’abord la correction.', 'logieval-secure' ),
			'notime'  => __( 'Cette évaluation n’a pas de durée limitée : rien à ajouter.', 'logieval-secure' ),
		);
		if ( isset( $labels[ $op ] ) ) {
			echo '<div class="notice notice-success les-message les-ctl-notice"><p>' . esc_html( $labels[ $op ] ) . '</p></div>';
		} elseif ( isset( $errors[ $er ] ) ) {
			echo '<div class="notice notice-error les-message is-error les-ctl-notice"><p>' . esc_html( $errors[ $er ] ) . '</p></div>';
		}
	}

	public static function handle_attempt_control(): void {
		self::require_teacher_post();
		$attempt = self::require_managed_attempt( absint( $_POST['ctl_attempt_id'] ?? 0 ) );
		$op      = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
		$minutes = min( 120, max( 0, absint( $_POST['minutes'] ?? 0 ) ) );
		// 1.38.1 : chaque clic n'est appliqué qu'UNE fois. Un double clic, ou le navigateur qui renvoie le formulaire
		// quand le serveur est lent, ajoutait les minutes deux fois (+5 min → 10 min).
		$request = preg_replace( '/[^a-f0-9-]/', '', strtolower( (string) wp_unslash( $_POST['ctl_request'] ?? '' ) ) );
		$seen    = $request ? 'les_ctl_' . md5( $request . '|' . (int) $attempt['id'] ) : '';
		if ( $seen && get_transient( $seen ) ) {
			$error = (string) get_transient( $seen ) === 'ok' ? '' : (string) get_transient( $seen );
		} else {
			if ( $seen ) {
				set_transient( $seen, 'busy', 10 * MINUTE_IN_SECONDS ); // posé AVANT d'agir : un 2e envoi simultané est ignoré
			}
			$error = self::apply_attempt_control( $attempt, $op, $minutes );
			if ( $seen ) {
				set_transient( $seen, '' === $error ? 'ok' : $error, 10 * MINUTE_IN_SECONDS );
			}
		}
		if ( 'busy' === $error ) {
			$error = '';
		}

		$fallback = admin_url( 'admin.php?page=logiexam-monitoring&evaluation_id=' . absint( $attempt['evaluation_id'] ) );
		$return   = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['les_ctl_return'] ?? '' ) ), $fallback );
		$return   = remove_query_arg( array( 'les_ctl', 'les_ctl_error', 'unlocked' ), $return );
		$return   = '' === $error ? add_query_arg( 'les_ctl', $op, $return ) : add_query_arg( 'les_ctl_error', $error, $return );
		wp_safe_redirect( $return );
		exit;
	}

	/** Applique l'action ; renvoie '' si tout s'est bien passé, sinon un code d'erreur. */
	public static function apply_attempt_control( array $attempt, string $op, int $minutes = 0 ): string {
		$o = self::attempt_control_options( $attempt );
		if ( ! isset( $o[ $op ] ) || ! in_array( $op, array( 'unlock', 'reset_exits', 'add_time', 'restore', 'reopen' ), true ) ) {
			return 'state';
		}
		if ( ! $o[ $op ] ) {
			if ( in_array( $op, array( 'restore', 'reopen' ), true ) && $o['graded_lock'] ) {
				return 'graded';
			}
			if ( 'add_time' === $op && empty( $attempt['expires_at'] ) ) {
				return 'notime';
			}
			return 'state';
		}
		switch ( $op ) {
			case 'unlock':
				self::unlock_attempt_by_teacher( $attempt );
				break;
			case 'reset_exits':
				self::control_log( $attempt, 'teacher_reset_exits', __( 'L’enseignant a effacé les blocages : compteur de sorties remis à zéro.', 'logieval-secure' ) );
				if ( $o['blocked'] ) {
					self::unlock_attempt_by_teacher( self::get_attempt( (int) $attempt['id'] ) );
				}
				break;
			case 'add_time':
				self::control_add_time( $attempt, max( 1, $minutes ) );
				break;
			case 'restore':
			case 'reopen':
				self::control_reopen_attempt( $attempt, $op, $minutes );
				break;
		}
		return '';
	}

	private static function control_log( array $attempt, string $type, string $message, array $details = array(), string $severity = 'info' ): void {
		$text = $message;
		if ( $details ) {
			$text .= ' ' . wp_json_encode( $details, JSON_UNESCAPED_UNICODE );
		}
		LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], $type, $text, (int) $attempt['id'], $severity );
	}

	private static function control_add_time( array $attempt, int $minutes ): void {
		global $wpdb;
		$t    = LogiEval_Secure_Database::tables();
		$base = max( self::control_ts_from_gmt( (string) $attempt['expires_at'] ), time() );
		$new  = gmdate( 'Y-m-d H:i:s', $base + $minutes * MINUTE_IN_SECONDS );
		$wpdb->update( $t['attempts'], array( 'expires_at' => $new ), array( 'id' => (int) $attempt['id'] ) );
		self::control_log(
			$attempt,
			'teacher_add_time',
			sprintf( __( 'L’enseignant a ajouté %d min.', 'logieval-secure' ), $minutes ),
			array( 'avant' => (string) $attempt['expires_at'], 'apres' => $new )
		);
	}

	/** Rouvre une copie terminée (annulée pour tricherie ou envoyée) : l'élève reprend avec son code. */
	private static function control_reopen_attempt( array $attempt, string $op, int $extra_minutes ): void {
		global $wpdb;
		$t      = LogiEval_Secure_Database::tables();
		$id     = (int) $attempt['id'];
		$result = self::get_result_by_attempt( $id );
		$code   = $attempt['code_id'] ? $wpdb->get_row( $wpdb->prepare( "SELECT id, status, used_at FROM {$t['codes']} WHERE id=%d", (int) $attempt['code_id'] ), ARRAY_A ) : null;

		$expires = null;
		if ( ! empty( $attempt['expires_at'] ) ) {
			$end       = self::control_ts_from_gmt( (string) $attempt['expires_at'] );
			$stop      = self::control_ts_from_local( (string) ( $attempt['submitted_at'] ?? '' ) );
			$remaining = ( $end && $stop ) ? $end - $stop : 0;
			$remaining = max( 5 * MINUTE_IN_SECONDS, $remaining ) + $extra_minutes * MINUTE_IN_SECONDS;
			$expires   = gmdate( 'Y-m-d H:i:s', time() + $remaining );
		}

		// Sauvegarde de ce qui est remplacé (dans le journal de sécurité).
		$backup = array(
			'statut'      => (string) $attempt['status'],
			'envoyee_le'  => (string) ( $attempt['submitted_at'] ?? '' ),
			'fin'         => (string) ( $attempt['expires_at'] ?? '' ),
			'code'        => $code ? (string) $code['status'] : '',
			'resultat'    => $result ? array_intersect_key( $result, array_flip( array( 'points_obtained', 'bonus_points', 'malus_points', 'final_total', 'final_grade', 'status', 'teacher_comment' ) ) ) : null,
		);

		$wpdb->update(
			$t['attempts'],
			array(
				'status'       => 'in_progress',
				'submitted_at' => null,
				'lock_until'   => null,
				'expires_at'   => $expires,
				'last_seen_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
		if ( $code ) {
			$wpdb->update( $t['codes'], array( 'status' => 'active', 'session_token' => null, 'used_at' => null ), array( 'id' => (int) $code['id'] ) );
		}
		if ( $result ) {
			$wpdb->delete( $t['results'], array( 'attempt_id' => $id ) );
		}

		$restore = 'restore' === $op;
		self::control_log(
			$attempt,
			$restore ? 'teacher_restore' : 'teacher_reopen',
			$restore ? __( 'L’enseignant a annulé la sanction pour tricherie et rouvert la copie.', 'logieval-secure' ) : __( 'L’enseignant a rouvert la copie envoyée.', 'logieval-secure' ),
			$backup,
			'warning'
		);
		if ( $restore ) {
			self::control_log( $attempt, 'teacher_reset_exits', __( 'L’enseignant a effacé les blocages : compteur de sorties remis à zéro.', 'logieval-secure' ) );
		}
		// La reprise est autorisée : en entrant son code, l'élève voit « Reprendre l'évaluation », sans nouvelle sanction.
		LogiEval_Secure_Security::log_event( (int) $attempt['evaluation_id'], (int) $attempt['user_id'], 'teacher_unlock', __( "L'enseignant a autorisé la reprise de l'évaluation.", 'logieval-secure' ), $id, 'info' );
	}

	/** Chrono de l'élève : l'échéance à jour (temps ajouté par l'enseignant). */
	public static function ajax_time_status(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$attempt = self::current_attempt_from_request( array( 'in_progress', 'security_paused', 'locked_exit_attempt' ), true );
		if ( ! $attempt ) {
			wp_send_json_error( array( 'message' => __( 'Session invalide.', 'logieval-secure' ) ), 403 );
		}
		wp_send_json_success(
			array(
				'expires_at' => $attempt['expires_at'] ?? null,
				'status'     => (string) $attempt['status'],
				'expired'    => self::attempt_is_expired( $attempt ),
			)
		);
	}
}
