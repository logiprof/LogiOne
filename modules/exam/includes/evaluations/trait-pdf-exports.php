<?php
/**
 * LogiEval_Secure_Evaluations — partie « pdf-exports ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Pdf_Exports {	private static function codes_count_for_evaluation( int $evaluation_id ): int {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$evaluation = self::get_evaluation( $evaluation_id );
		$targeted   = $evaluation ? array_map( 'intval', self::resolve_student_ids( $evaluation ) ) : array();
		$users      = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$t['codes']} WHERE evaluation_id=%d AND status<>'revoked'", $evaluation_id ) ) );
		return count( array_intersect( $users, $targeted ) ); // Seulement la classe choisie.
	}

	/**
	 * Aligne les codes sur la cible actuelle de l'évaluation (classes et élèves choisis).
	 * Un élève qui n'est plus visé perd son code (statut « revoked » : il ne peut plus entrer),
	 * sauf s'il a déjà commencé l'évaluation : sa copie et son accès sont conservés.
	 */
	private static function sync_codes_with_target( array $evaluation ): int {
		global $wpdb;
		$t        = LogiEval_Secure_Database::tables();
		$id       = (int) $evaluation['id'];
		$targeted = array_map( 'intval', self::resolve_student_ids( $evaluation ) );
		$started  = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$t['attempts']} WHERE evaluation_id=%d", $id ) ) );
		$revoked  = 0;
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, status FROM {$t['codes']} WHERE evaluation_id=%d", $id ), ARRAY_A ) as $row ) {
			$uid = (int) $row['user_id'];
			if ( 'revoked' === (string) $row['status'] || in_array( $uid, $targeted, true ) || in_array( $uid, $started, true ) ) {
				continue;
			}
			$wpdb->update( $t['codes'], array( 'status' => 'revoked', 'session_token' => null ), array( 'id' => (int) $row['id'] ) );
			$revoked++;
		}
		return $revoked;
	}

	private static function latest_codes_pdf_export( int $evaluation_id ): ?array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t['exports']} WHERE evaluation_id=%d AND format=%s AND file_url<>'' ORDER BY created_at DESC, id DESC LIMIT 1",
				$evaluation_id,
				'codes_pdf'
			),
			ARRAY_A
		);
		if ( ! $row || empty( $row['file_url'] ) || ! self::codes_pdf_export_path( (string) $row['file_url'] ) ) {
			return null;
		}
		return $row;
	}

	public static function export_status_label( array $attempt, ?array $result ): string {
		$result_status = (string) ( $result['status'] ?? '' );
		if ( 'graded' === $result_status || 'graded' === (string) $attempt['status'] ) {
			return __( 'Corrigé', 'logieval-secure' );
		}
		if ( 'to_grade' === $result_status ) {
			return __( 'À corriger', 'logieval-secure' );
		}
		return self::attempt_status_label( (string) $attempt['status'], $result_status );
	}

	private static function export_csv_url( int $evaluation_id, string $class = '' ): string {
		$url = admin_url( 'admin-post.php?action=logieval_secure_export_csv&evaluation_id=' . $evaluation_id );
		if ( '' !== $class ) {
			$url = add_query_arg( 'class', rawurlencode( $class ), $url );
		}
		return wp_nonce_url( $url, 'logieval_secure_export' );
	}

		private static function render_shortcodes_panel(): void {
		?>
		<h2><?php esc_html_e( 'Shortcodes', 'logieval-secure' ); ?></h2>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Usage', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Shortcode', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Accès', 'logieval-secure' ); ?></th></tr></thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Interface élève', 'logieval-secure' ); ?></td>
					<td><code>[logiexam_student]</code></td>
					<td><?php esc_html_e( 'Élève connecté, groupe Sensei autorisé et code unique valide.', 'logieval-secure' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Tableau de bord enseignant', 'logieval-secure' ); ?></td>
					<td><code>[logiexam_teacher]</code></td>
					<td><?php esc_html_e( 'Administrateur ou rôle Enseignant Logistique uniquement.', 'logieval-secure' ); ?></td>
				</tr>
			</tbody>
		</table>
		<p><small><?php esc_html_e( 'Alias conservés pour compatibilité : [logieval_student] et [logieval_teacher].', 'logieval-secure' ); ?></small></p>
		<?php
	}

	public static function handle_generate_codes(): void {
		self::require_teacher_post();
		$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
		self::require_managed_evaluation( $evaluation_id );
		self::generate_codes( $evaluation_id );
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-codes&evaluation_id=' . $evaluation_id . '&codes=1' ), array( 'logiexam_view' => 'codes', 'evaluation_id' => $evaluation_id, 'codes' => 1 ) ) );
		exit;
	}

	public static function handle_export_codes_pdf(): void {
		self::require_teacher_post();

		$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
		$evaluation    = self::require_managed_evaluation( $evaluation_id );

		self::ensure_codes_for_evaluation( $evaluation_id );
		// PDF toujours à jour (dates, classe, nouveaux élèves) à partir des codes chiffrés.
		$codes = self::decrypted_codes_for_evaluation( $evaluation_id );
		if ( empty( $codes ) ) {
			$codes = get_transient( 'logieval_secure_codes_' . get_current_user_id() . '_' . $evaluation_id );
		}
		if ( empty( $codes ) || ! is_array( $codes ) ) {
			$export = self::latest_codes_pdf_export( $evaluation_id );
			$path   = $export ? self::codes_pdf_export_path( (string) $export['file_url'] ) : '';
			if ( ! $path ) {
				wp_die( esc_html__( 'Aucun PDF de codes n’est disponible pour cette évaluation. Générez de nouveaux accès pour créer un nouveau document récupérable.', 'logieval-secure' ) );
			}
			$pdf = (string) file_get_contents( $path );
		} else {
			$pdf = self::build_codes_pdf( $evaluation, $codes );
			self::save_codes_pdf_export( $evaluation, $pdf );
		}

		$filename = sanitize_file_name( 'logiexam-codes-' . $evaluation_id . '.pdf' );

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

	/**
	 * 1.52.0 : test formatif — l'élève télécharge sa propre copie corrigée (PDF élève), une fois la correction finalisée.
	 */
	public static function handle_student_formative_pdf(): void {
		$attempt_id = absint( $_GET['attempt_id'] ?? 0 );
		check_admin_referer( 'logiexam_formative_copy_' . $attempt_id );
		$attempt    = self::get_attempt( $attempt_id );
		$evaluation = $attempt ? self::get_evaluation( (int) $attempt['evaluation_id'] ) : null;
		if ( ! is_user_logged_in() || ! $attempt || ! $evaluation || (int) $attempt['user_id'] !== get_current_user_id()
			|| ! self::is_formative( $evaluation ) || self::attempt_is_cheating_cancelled( $attempt )
			|| ! self::attempt_correction_is_finished( $attempt, self::get_result_by_attempt( $attempt_id ) ) ) {
			wp_die( esc_html__( 'Cette copie corrigée n’est pas disponible.', 'logieval-secure' ), '', array( 'response' => 403 ) );
		}
		$pdf      = self::build_attempt_pdf( $evaluation, $attempt );
		$filename = sanitize_file_name( 'ma-copie-corrigee-' . sanitize_title( (string) $evaluation['title'] ) . '.pdf' );
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

	/** 1.52.1 : une copie de test formatif dont l'élève peut voir le corrigé (copie envoyée, non annulée). */
	private static function formative_attempt_open_for_student( ?array $attempt ): ?array {
		if ( ! $attempt || (int) $attempt['user_id'] !== get_current_user_id() || self::attempt_is_cheating_cancelled( $attempt ) ) {
			return null;
		}
		$evaluation = self::get_evaluation( (int) $attempt['evaluation_id'] );
		$submitted  = ! empty( $attempt['submitted_at'] ) || in_array( (string) $attempt['status'], array( 'submitted', 'submitted_locked', 'graded' ), true );
		if ( ! $evaluation || 'deleted' === (string) ( $evaluation['status'] ?? '' ) || ! self::is_formative( $evaluation ) || ! $submitted ) {
			return null;
		}

		return $evaluation;
	}

	/** Liste des tests formatifs de l'élève connecté (page élève LogiExam) : corrigé pour s'autocorriger. */
	public static function render_student_formative_copies(): string {
		global $wpdb;
		$user_id = get_current_user_id();
		$ids     = array_map( 'intval', array_keys( array_filter( (array) get_option( 'logiexam_formative', array() ) ) ) );
		if ( ! $user_id || ! $ids ) {
			return '';
		}
		$attempt_id = absint( $_GET['les_corrige'] ?? 0 );
		if ( $attempt_id && wp_verify_nonce( (string) wp_unslash( $_GET['_lesnonce'] ?? '' ), 'logiexam_corrige_' . $attempt_id ) ) {
			$attempt    = self::get_attempt( $attempt_id );
			$evaluation = self::formative_attempt_open_for_student( $attempt );
			if ( $evaluation ) {
				return self::render_formative_answer_key( $evaluation, $attempt );
			}
		}
		$t    = LogiEval_Secure_Database::tables();
		$in   = implode( ',', $ids );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['attempts']} WHERE user_id=%d AND evaluation_id IN ({$in}) ORDER BY id DESC", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = array();
		foreach ( (array) $rows as $attempt ) {
			$evaluation = self::formative_attempt_open_for_student( $attempt );
			if ( ! $evaluation ) {
				continue;
			}
			$url     = add_query_arg( array( 'les_corrige' => (int) $attempt['id'], '_lesnonce' => wp_create_nonce( 'logiexam_corrige_' . (int) $attempt['id'] ) ), get_permalink() ?: home_url( '/' ) );
			$extra   = '';
			if ( self::attempt_correction_is_finished( $attempt, self::get_result_by_attempt( (int) $attempt['id'] ) ) ) {
				$pdf   = wp_nonce_url( admin_url( 'admin-post.php?action=logiexam_formative_copy&attempt_id=' . (int) $attempt['id'] ), 'logiexam_formative_copy_' . (int) $attempt['id'] );
				$extra = ' <a class="button les-button-secondary" href="' . esc_url( $pdf ) . '">' . esc_html__( 'Copie annotée par l’enseignant (PDF)', 'logieval-secure' ) . '</a>';
			}
			$when    = ! empty( $attempt['submitted_at'] ) ? mysql2date( 'd.m.Y', (string) $attempt['submitted_at'] ) : '';
			$items[] = '<li><span class="les-formative-copy-title"><strong>' . esc_html( (string) $evaluation['title'] ) . '</strong>' . ( $when ? ' <span class="les-formative-grade">' . esc_html( sprintf( __( 'envoyé le %s', 'logieval-secure' ), $when ) ) . '</span>' : '' ) . '</span>'
				. '<span><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Voir le corrigé et m’autocorriger', 'logieval-secure' ) . '</a>' . $extra . '</span></li>';
		}
		if ( ! $items ) {
			return '';
		}

		return '<section class="les-formative-copies"><h3>' . esc_html__( 'Mes tests formatifs', 'logieval-secure' ) . '</h3><p>' . esc_html__( 'Ces tests ne comptent pas dans vos notes. Comparez vos réponses avec le corrigé pour voir ce qui est acquis et ce qui reste à travailler.', 'logieval-secure' ) . '</p><ul>' . implode( '', $items ) . '</ul></section>';
	}

	/** Corrigé d'un test formatif : pour chaque question, la réponse de l'élève à côté de la réponse attendue. */
	private static function render_formative_answer_key( array $evaluation, array $attempt ): string {
		$answers = self::get_answers_for_attempt( (int) $attempt['id'] );
		$back    = remove_query_arg( array( 'les_corrige', '_lesnonce' ) );
		$data    = self::evaluation_export_data( $evaluation );
		$raw     = self::get_questions_for_evaluation( (int) $evaluation['id'] );
		ob_start();
		?>
		<section class="les-answer-key">
			<p class="les-answer-key-actions"><a class="button les-button-secondary" href="<?php echo esc_url( $back ); ?>">← <?php esc_html_e( 'Retour', 'logieval-secure' ); ?></a> <button type="button" class="button" onclick="window.print()"><?php esc_html_e( 'Imprimer', 'logieval-secure' ); ?></button></p>
			<span class="les-kicker"><?php esc_html_e( 'Test formatif · corrigé', 'logieval-secure' ); ?></span>
			<h2><?php echo esc_html( (string) $evaluation['title'] ); ?></h2>
			<p class="les-answer-key-lead"><?php esc_html_e( 'Pour chaque question, comparez votre réponse avec la réponse attendue et cochez « Juste », « À moitié » ou « À revoir ». Ce test ne compte pas dans vos notes.', 'logieval-secure' ); ?></p>
			<?php foreach ( $data['questions'] as $i => $q ) : $row = self::answer_row_by_question( $answers, (int) ( $raw[ $i ]['id'] ?? 0 ) ); $mine = self::readable_answer( $row['answer'] ?? '' ); ?>
				<article class="les-answer-key-item">
					<header><strong><?php echo esc_html( sprintf( __( 'Question %d', 'logieval-secure' ), $i + 1 ) ); ?></strong><span><?php echo esc_html( self::format_points( (float) $q['points'] ) ); ?> pts</span></header>
					<?php if ( '' !== $q['context'] ) : ?><p class="les-answer-key-context"><?php echo nl2br( esc_html( $q['context'] ) ); ?></p><?php endif; ?>
					<?php if ( $q['media_id'] ) : ?><div class="les-answer-key-media"><?php echo wp_get_attachment_image( $q['media_id'], 'large' ); ?></div><?php endif; ?>
					<p class="les-answer-key-prompt"><?php echo nl2br( esc_html( $q['prompt'] ) ); ?></p>
					<div class="les-answer-key-grid">
						<div class="les-answer-key-mine"><strong><?php esc_html_e( 'Ma réponse', 'logieval-secure' ); ?></strong><div><?php echo wp_kses_post( wpautop( esc_html( $mine ) ) ); ?></div></div>
						<div class="les-answer-key-expected"><strong><?php esc_html_e( 'Réponse attendue', 'logieval-secure' ); ?></strong>
							<?php if ( $q['options'] ) : ?><ul><?php foreach ( $q['options'] as $opt ) : ?><li><?php echo esc_html( str_replace( array( '[x] ', '[ ] ' ), array( '✓ ', '· ' ), $opt ) ); ?></li><?php endforeach; ?></ul><?php endif; ?>
							<?php if ( '' !== $q['expected'] ) : ?><div><?php echo wp_kses_post( wpautop( esc_html( $q['expected'] ) ) ); ?></div><?php endif; ?>
							<?php if ( $q['criteria'] ) : ?><p class="les-answer-key-criteria-title"><?php esc_html_e( 'Ce qu’il fallait dire :', 'logieval-secure' ); ?></p><ul class="les-answer-key-criteria"><?php foreach ( $q['criteria'] as $c ) : ?><li><?php echo esc_html( $c['label'] ); ?> <em>(<?php echo esc_html( self::format_points( (float) $c['points'] ) ); ?> pt)</em></li><?php endforeach; ?></ul><?php endif; ?>
						</div>
					</div>
					<p class="les-answer-key-self"><span><?php esc_html_e( 'Mon autocorrection :', 'logieval-secure' ); ?></span>
						<?php foreach ( array( 'ok' => __( 'Juste', 'logieval-secure' ), 'half' => __( 'À moitié', 'logieval-secure' ), 'ko' => __( 'À revoir', 'logieval-secure' ) ) as $k => $label ) : ?>
							<label class="is-<?php echo esc_attr( $k ); ?>"><input type="radio" name="les_self_<?php echo (int) $i; ?>" value="<?php echo esc_attr( $k ); ?>"> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</p>
				</article>
			<?php endforeach; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_export_attempt_pdf(): void {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_export' ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ) );
		}

		$attempt_id = absint( $_GET['attempt_id'] ?? 0 );
		$attempt    = self::require_managed_attempt( $attempt_id );

		$evaluation = self::get_evaluation( (int) $attempt['evaluation_id'] );
		if ( ! $evaluation ) {
			wp_die( esc_html__( 'Évaluation introuvable.', 'logieval-secure' ) );
		}

		$pdf      = self::build_attempt_pdf( $evaluation, $attempt );
		$filename = sanitize_file_name( 'logiexam-copie-' . $evaluation['id'] . '-' . $attempt['user_id'] . '.pdf' );

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

	/**
	 * Codes automatiques : crée un code unique pour chaque élève de l'évaluation qui n'en a pas encore.
	 * Les codes déjà distribués ne changent jamais. Retourne le nombre de codes créés.
	 */
	public static function ensure_codes_for_evaluation( int $evaluation_id ): int {
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$evaluation = self::get_evaluation( $evaluation_id );
		if ( ! $evaluation || 'published' !== (string) ( $evaluation['status'] ?? '' ) ) {
			return 0;
		}
		self::sync_codes_with_target( $evaluation );
		$existing = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, status FROM {$t['codes']} WHERE evaluation_id=%d", $evaluation_id ), ARRAY_A ) as $row ) {
			$existing[ (int) $row['user_id'] ] = $row;
		}
		$created  = 0;
		foreach ( self::resolve_student_ids( $evaluation ) as $user_id ) {
			$row = $existing[ (int) $user_id ] ?? null;
			if ( $row && 'revoked' === (string) $row['status'] ) {
				// Élève de nouveau visé (classe remise) : nouveau code actif.
				$plain = strtoupper( wp_generate_password( 10, false, false ) );
				$wpdb->update( $t['codes'], array( 'code_hash' => wp_hash_password( $plain ), 'code_preview' => substr( $plain, -4 ), 'code_encrypted' => LogiEval_Secure_AI::encrypt( $plain ), 'status' => 'active', 'session_token' => null ), array( 'id' => (int) $row['id'] ) );
				$created++;
				continue;
			}
			if ( $row ) {
				continue;
			}
			$plain = strtoupper( wp_generate_password( 10, false, false ) );
			$wpdb->insert(
				$t['codes'],
				array(
					'evaluation_id'  => $evaluation_id,
					'user_id'        => (int) $user_id,
					'code_hash'      => wp_hash_password( $plain ),
					'code_preview'   => substr( $plain, -4 ),
					'code_encrypted' => LogiEval_Secure_AI::encrypt( $plain ),
					'status'         => 'active',
					'created_at'     => current_time( 'mysql' ),
				)
			);
			$created++;
		}
		return $created;
	}

	/** Codes en clair (déchiffrés) de l'évaluation, pour imprimer les fiches à tout moment. */
	private static function decrypted_codes_for_evaluation( int $evaluation_id ): array {
		global $wpdb;
		$t     = LogiEval_Secure_Database::tables();
		$codes = array();
		$evaluation = self::get_evaluation( $evaluation_id );
		$targeted   = $evaluation ? array_map( 'intval', self::resolve_student_ids( $evaluation ) ) : array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, code_encrypted FROM {$t['codes']} WHERE evaluation_id=%d AND status<>'revoked' AND code_encrypted IS NOT NULL AND code_encrypted<>''", $evaluation_id ), ARRAY_A ) as $row ) {
			if ( ! in_array( (int) $row['user_id'], $targeted, true ) ) {
				continue; // Plus dans la classe choisie : pas sur les fiches.
			}
			$plain = LogiEval_Secure_AI::decrypt( (string) $row['code_encrypted'] );
			if ( '' !== $plain ) {
				$codes[ (int) $row['user_id'] ] = $plain;
			}
		}
		return $codes;
	}

	public static function generate_codes( int $evaluation_id ): array {
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$evaluation = self::get_evaluation( $evaluation_id );
		if ( ! $evaluation || 'deleted' === (string) ( $evaluation['status'] ?? '' ) ) {
			return array();
		}

		self::sync_codes_with_target( $evaluation );
		$user_ids = self::resolve_student_ids( $evaluation );
		$codes    = array();
		foreach ( $user_ids as $user_id ) {
			$plain = strtoupper( wp_generate_password( 10, false, false ) );
			$hash  = wp_hash_password( $plain );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['codes']} WHERE evaluation_id=%d AND user_id=%d", $evaluation_id, $user_id ) );
			if ( $exists ) {
				$wpdb->update( $t['codes'], array( 'code_hash' => $hash, 'code_preview' => substr( $plain, -4 ), 'code_encrypted' => LogiEval_Secure_AI::encrypt( $plain ), 'status' => 'active', 'session_token' => null ), array( 'id' => $exists ) );
			} else {
				$wpdb->insert( $t['codes'], array( 'evaluation_id' => $evaluation_id, 'user_id' => $user_id, 'code_hash' => $hash, 'code_preview' => substr( $plain, -4 ), 'code_encrypted' => LogiEval_Secure_AI::encrypt( $plain ), 'status' => 'active', 'created_at' => current_time( 'mysql' ) ) );
			}
			$codes[ $user_id ] = $plain;
		}

		set_transient( 'logieval_secure_codes_' . get_current_user_id() . '_' . $evaluation_id, $codes, HOUR_IN_SECONDS );
		$evaluation = self::get_evaluation( $evaluation_id );
		if ( $evaluation && ! empty( $codes ) ) {
			self::save_codes_pdf_export( $evaluation, self::build_codes_pdf( $evaluation, $codes ) );
		}
		return $codes;
	}

	private static function csv_ids( $value ): array {
		return array_values( array_filter( array_map( 'absint', preg_split( '/[,\s]+/', (string) wp_unslash( $value ) ) ) ) );
	}

	private static function build_attempt_pdf( array $evaluation, array $attempt ): string {
		$user          = get_userdata( (int) $attempt['user_id'] );
		$result        = self::get_result_by_attempt( (int) $attempt['id'] );
		$logs          = LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] );
		$attempt       = self::attempt_with_time_fallbacks( $attempt, $evaluation, $logs );
		$block_logs    = self::pdf_block_logs( $logs );
		$questions     = self::get_attempt_pdf_question_rows( (int) $attempt['id'], (int) $evaluation['id'] );
		$points        = $result ? (float) $result['points_obtained'] : self::sum_attempt_points( (int) $attempt['id'] );
		$bonus         = $result ? (float) $result['bonus_points'] : 0.0;
		$malus         = $result ? (float) $result['malus_points'] : 0.0;
		$final_total   = $result ? (float) $result['final_total'] : LogiEval_Secure_Grading::final_total( $points, $bonus, $malus );
		$base_grade    = LogiEval_Secure_Grading::grade( $points, $evaluation['total_points'] ?? 0 );
		$final_grade   = LogiEval_Secure_Grading::grade( $final_total, $evaluation['total_points'] ?? 0 );
		$effective     = self::attempt_effective_duration( $attempt );
		$remaining     = self::attempt_remaining_duration( $attempt );
		$attempt_date  = self::attempt_date_label( $attempt );
		$started_at    = self::pdf_local_datetime( $attempt['started_at'] ?? '' );
		$finished_at   = self::pdf_local_datetime( $attempt['submitted_at'] ?? '' );
		$expires_at    = self::pdf_utc_datetime( $attempt['expires_at'] ?? '' );
		$course_name   = self::evaluation_course_name( $evaluation );
		$security_text = self::security_status_label( $logs, $attempt );
		$alert_count   = count( self::merged_security_logs( $block_logs ) );
		$block_count   = self::security_block_count_from_logs( $logs );
		$is_cancelled  = self::attempt_is_cheating_cancelled( $attempt );
		if ( $is_cancelled ) {
			$points      = 0.0;
			$bonus       = 0.0;
			$malus       = 0.0;
			$final_total = 0.0;
			$base_grade  = self::CHEATING_GRADE;
			$final_grade = self::CHEATING_GRADE;
		}

		self::$pdf_images = array();
		$doc = array(
			'pages'    => array(),
			'content'  => '',
			'y'        => 0,
			'title'    => (string) $evaluation['title'],
			'subtitle' => self::student_full_name( (int) $attempt['user_id'] ),
			'header'   => self::attempt_is_cheating_cancelled( $attempt ) ? 'Copie annulée - tricherie' : 'Copie corrigée',
		);
		self::pdf_report_new_page( $doc );

		self::pdf_report_section_title( $doc, 'Informations de l’évaluation' );
		self::pdf_report_info_grid(
			$doc,
			array_filter(
				array(
					array( 'Évaluation', (string) $evaluation['title'] ),
					$course_name ? array( 'Cours Sensei', $course_name ) : null,
					$course_name ? null : array( 'Module', self::evaluation_module_name( $evaluation ) ),
					array( 'Élève', self::student_full_name( (int) $attempt['user_id'] ), 'highlight' ),
					array( 'Compte', $user ? $user->user_email : '#' . (int) $attempt['user_id'] ),
					array( 'Groupe classe', self::group_label_for_user( $evaluation, (int) $attempt['user_id'] ) ),
					array( 'Date de l’évaluation', $attempt_date ),
					array( 'Heure de début (Zurich)', $started_at ),
					array( 'Heure de fin (Zurich)', $finished_at ),
					array( 'Heure limite (Zurich)', $expires_at ),
					array( 'Temps à disposition', (int) $evaluation['duration_minutes'] . ' min' ),
					array( 'Temps effectif', $effective ),
					array( 'Temps restant à la remise', $remaining ),
					array( 'Sécurité', $alert_count ? $security_text . ' - ' . sprintf( _n( '%d alerte', '%d alertes', $alert_count, 'logieval-secure' ), $alert_count ) : __( 'Aucune alerte', 'logieval-secure' ) ),
					array( 'Blocages enseignant', sprintf( _n( '%d blocage', '%d blocages', $block_count, 'logieval-secure' ), $block_count ) ),
				),
				static function( $item ) {
					return is_array( $item );
				}
			)
		);

		if ( $is_cancelled ) {
			$exit_total = max( self::security_exit_count_from_logs( $logs ), $block_count + 1 );
			self::pdf_report_alert_box(
				$doc,
				'Évaluation annulée pour tricherie - Note : 1',
				sprintf(
					'Le mode sécurisé a été quitté %1$d fois pendant l’évaluation (maximum autorisé : %2$d déblocages). L’évaluation a été arrêtée automatiquement le %3$s et n’a pas été corrigée. La note de 1 est attribuée.',
					$exit_total,
					self::max_exits() - 1,
					$finished_at
				)
			);
		}

		self::pdf_report_section_title( $doc, 'Synthèse de la note' );
		self::pdf_report_score_box(
				$doc,
				array(
					'Points obtenus'          => $is_cancelled ? 'Non corrigée' : self::format_points( $points ) . ' / ' . self::format_points( (float) $evaluation['total_points'] ),
					'Bonus (points)'          => '+' . self::format_points( $bonus ),
					'Malus (points)'          => '-' . self::format_points( $malus ),
					'Total des points'        => $is_cancelled ? 'Annulée (tricherie)' : self::format_points( $final_total ) . ' / ' . self::format_points( (float) $evaluation['total_points'] ),
					'Note finale'             => self::pdf_optional_number( $final_grade ),
				)
			);
		if ( $result && ! empty( $result['teacher_comment'] ) ) {
			self::pdf_report_note_box( $doc, 'Commentaire général', (string) $result['teacher_comment'] );
		}

		// Les questions, réponses et corrections commencent toujours en page 2.
		self::pdf_report_new_page( $doc );
		self::pdf_report_section_title( $doc, 'Questions et réponses' );
		$current_context = null;
		foreach ( $questions as $index => $row ) {
			$answer_points = is_numeric( $row['manual_points'] ) ? (float) $row['manual_points'] : ( is_numeric( $row['auto_points'] ) ? (float) $row['auto_points'] : 0.0 );
			if ( $is_cancelled ) {
				$answer_points        = 0.0;
				$row['teacher_comment'] = 'Non corrigée : évaluation annulée pour tricherie.';
			}
			$context       = trim( self::pdf_plain_text( (string) ( $row['context'] ?? '' ) ) );
			if ( '' !== $context && $context !== $current_context ) {
				// Le contexte et sa première question restent sur la même page.
				$context_h = max( 48, 34 + ( count( self::pdf_wrap_lines( $context, 92 ) ) * 14 ) + 8 ) + 28;
				self::pdf_report_ensure_space( $doc, $context_h + self::pdf_question_height_estimate( $row ) + 8 );
				self::pdf_report_context_box( $doc, $context );
				$current_context = $context;
			}
			if ( self::pdf_report_order_card( $doc, $index + 1, $row, $answer_points, (float) $row['points'], (string) ( $row['teacher_comment'] ?? '' ) ) ) {
				continue;
			}
			$precision_label = 'calculation' === ( $row['type'] ?? '' ) ? LogiEval_Secure_Question_Types::precision_label( LogiEval_Secure_Question_Types::calculation_precision( $row['expected_answer'] ?? '' ) ) : '';
			self::pdf_report_question_card(
				$doc,
				$index + 1,
				self::pdf_plain_text( (string) $row['prompt'] ) . ( '' !== $precision_label ? ' [' . $precision_label . ']' : '' ),
				self::readable_answer( $row['answer'] ?? '' ),
				$answer_points,
				(float) $row['points'],
				(string) ( $row['teacher_comment'] ?? '' )
			);
		}

		$doc['y'] -= 40;
		self::pdf_report_section_title( $doc, 'Sécurité - Blocages et alertes' );
		if ( empty( $block_logs ) ) {
			self::pdf_report_note_box( $doc, 'Registre', 'Aucun blocage ni alerte de sécurité.' );
		} else {
			foreach ( self::merged_security_logs( $block_logs ) as $group ) {
				$details = array_map( static fn( $d ) => self::pdf_plain_text( str_replace( '<br>', ' - ', self::format_security_event_details( (string) $d ) ) ), $group['details'] );
				self::pdf_report_security_event(
					$doc,
					$group['created_at'],
					self::security_severity_label( $group['severity'] ),
					implode( ' + ', $group['labels'] ),
					implode( ' | ', $details )
				);
			}
		}

		if ( '' !== $doc['content'] ) {
			$doc['pages'][] = $doc['content'];
		}
		return self::build_pdf_document( $doc['pages'] );
	}

	/**
	 * Heures manquantes d'une copie (anciennes versions : début jamais reçu par le serveur) : début retrouvé
	 * d'après la saisie du code et le premier évènement enregistré ; échéance = début + durée.
	 */
	private static function attempt_with_time_fallbacks( array $attempt, array $evaluation, array $logs ): array {
		if ( empty( $attempt['started_at'] ) ) {
			global $wpdb;
			$t          = LogiEval_Secure_Database::tables();
			$candidates = array();
			if ( ! empty( $attempt['code_id'] ) ) {
				$candidates[] = (string) $wpdb->get_var( $wpdb->prepare( "SELECT session_started_at FROM {$t['codes']} WHERE id=%d", (int) $attempt['code_id'] ) );
			}
			foreach ( $logs as $log ) {
				$candidates[] = (string) ( $log['created_at'] ?? '' );
			}
			$candidates = array_filter( $candidates, static fn( $v ) => '' !== trim( $v ) && ( empty( $attempt['submitted_at'] ) || $v <= $attempt['submitted_at'] ) );
			if ( $candidates ) {
				$attempt['started_at'] = min( $candidates );
			}
		}
		if ( empty( $attempt['expires_at'] ) && ! empty( $attempt['started_at'] ) && ! empty( $evaluation['duration_minutes'] ) ) {
			$start = self::local_datetime_timestamp( (string) $attempt['started_at'] );
			if ( $start ) {
				$attempt['expires_at'] = gmdate( 'Y-m-d H:i:s', $start + self::attempt_duration_seconds( $evaluation, (int) ( $attempt['user_id'] ?? 0 ) ) );
			}
		}
		return $attempt;
	}

	/** Pour l'export : uniquement les blocages et alertes (ni déblocage par l'enseignant, ni reprise, ni simples informations). */
	private static function pdf_block_logs( array $logs ): array {
		$skip = array( 'teacher_unlock', 'student_resume_after_unlock', 'post_submit_reopen', 'unlock_requested', 'tab_visible', 'window_focus', 'fullscreen_enter', 'time_expired_auto_submit' );
		return array_values( array_filter( $logs, static fn( $log ) => ! in_array( (string) $log['event_type'], $skip, true ) && in_array( (string) $log['severity'], array( 'warning', 'critical' ), true ) ) );
	}

	/** Hauteur approximative d'une carte de question (pour garder un contexte avec sa question). */
	private static function pdf_question_height_estimate( array $row ): int {
		if ( LogiEval_Secure_Question_Types::ORDERING === ( $row['type'] ?? '' ) ) {
			$items = LogiEval_Secure_Question_Types::order_items( $row['options'] ?? '' );
			if ( array_filter( wp_list_pluck( $items, 'media_id' ) ) ) {
				return 90 + (int) ceil( count( $items ) / 3 ) * 150;
			}
		}
		$q = count( self::pdf_wrap_lines( (string) $row['prompt'], 64 ) );
		$a = count( self::pdf_wrap_lines( 'Réponse élève : ' . self::readable_answer( $row['answer'] ?? '' ), 88 ) );
		$c = '' !== trim( (string) ( $row['teacher_comment'] ?? '' ) ) ? count( self::pdf_wrap_lines( 'Commentaire : ' . $row['teacher_comment'], 92 ) ) : 0;
		return 34 + $q * 14 + 18 + $a * 13 + $c * 13 + ( $c ? 12 : 0 ) + 14;
	}

	private static function get_attempt_pdf_question_rows( int $attempt_id, int $evaluation_id ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return $wpdb->get_results(
			$wpdb->prepare(
					"SELECT q.id AS question_id, q.title, q.context, q.prompt, q.type, q.options, q.expected_answer, eq.points, a.answer, a.auto_points, a.manual_points, a.teacher_comment
				FROM {$t['eval_questions']} eq
				INNER JOIN {$t['questions']} q ON q.id=eq.question_id
				LEFT JOIN {$t['answers']} a ON a.question_id=q.id AND a.attempt_id=%d
				WHERE eq.evaluation_id=%d
				ORDER BY eq.position ASC",
				$attempt_id,
				$evaluation_id
			),
			ARRAY_A
		);
	}

	private static function pdf_optional_number( $value ): string {
		return is_numeric( $value ) ? number_format_i18n( (float) $value, 2 ) : __( 'Non calculée', 'logieval-secure' );
	}

	private static function pdf_upper( string $text ): string {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
	}

	private static function pdf_report_new_page( array &$doc ): void {
		if ( '' !== $doc['content'] ) {
			$doc['pages'][] = $doc['content'];
		}
		$doc['content'] = '';
		$doc['y']       = 748;
		// Bandeau LogiExam (couleurs LogiOne) : logo à gauche, type de document et date à droite, filet jaune.
		$doc['content'] .= self::pdf_fill_rect( 0, 778, 595, 64, 49, 58, 69 );
		$doc['content'] .= self::pdf_fill_rect( 0, 774, 595, 4, 245, 196, 0 );
		$logo = self::pdf_register_file_image( LOGIEVAL_SECURE_PATH . 'assets/pdf-logo.png', 'ImLogo' );
		if ( '' !== $logo ) {
			$doc['content'] .= self::pdf_draw_image( $logo, 30, 834, 150, 48 );
		} else {
			$doc['content'] .= self::pdf_text_styled( 42, 802, 18, 'LogiExam', 'F2', array( 255, 255, 255 ) );
		}
		$title = (string) ( $doc['header'] ?? 'Copie corrigée' );
		$doc['content'] .= self::pdf_text_styled( 553 - self::pdf_text_width( $title, 15 ), 808, 15, $title, 'F2', array( 255, 255, 255 ) );
		$date = wp_date( 'd.m.Y H:i' );
		$doc['content'] .= self::pdf_text_styled( 553 - self::pdf_text_width( $date, 9 ), 792, 9, $date, 'F1', array( 201, 209, 217 ) );
	}

	/** Largeur approximative d'un texte en Helvetica (alignement à droite). */
	private static function pdf_text_width( string $text, int $size ): float {
		return strlen( self::pdf_plain_text( $text ) ) * $size * 0.5;
	}

	/** Image d'un fichier du plugin (logo) pour le PDF. */
	private static function pdf_register_file_image( string $path, string $name, int $max_px = 600 ): string {
		if ( isset( self::$pdf_images[ $name ] ) ) {
			return $name;
		}
		if ( ! is_readable( $path ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return '';
		}
		$src = @imagecreatefromstring( (string) file_get_contents( $path ) );
		if ( ! $src ) {
			return '';
		}
		$w = imagesx( $src );
		$h = imagesy( $src );
		$scale = min( 1, $max_px / max( $w, $h ) );
		$nw = max( 1, (int) round( $w * $scale ) );
		$nh = max( 1, (int) round( $h * $scale ) );
		$dst = imagecreatetruecolor( $nw, $nh );
		imagefill( $dst, 0, 0, imagecolorallocate( $dst, 49, 58, 69 ) );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
		ob_start();
		imagejpeg( $dst, null, 90 );
		self::$pdf_images[ $name ] = array( 'data' => (string) ob_get_clean(), 'w' => $nw, 'h' => $nh );
		imagedestroy( $src );
		imagedestroy( $dst );
		return $name;
	}

	private static function pdf_report_ensure_space( array &$doc, int $height ): void {
		if ( $doc['y'] - $height < 54 ) {
			self::pdf_report_new_page( $doc );
		}
	}

	private static function pdf_report_section_title( array &$doc, string $title ): void {
		// Un titre long (ex. nom d'évaluation) passe à la ligne au lieu de déborder de la page.
		$lines = self::pdf_wrap_lines( $title, 74 );
		self::pdf_report_ensure_space( $doc, 34 + ( count( $lines ) - 1 ) * 18 );
		foreach ( $lines as $i => $line ) {
			$doc['content'] .= self::pdf_text_styled( 42, $doc['y'] - $i * 18, 14, $line, 'F2', array( 49, 58, 69 ) );
		}
		$doc['y']       -= ( count( $lines ) - 1 ) * 18;
		$doc['content'] .= self::pdf_fill_rect( 42, $doc['y'] - 8, 60, 3, 245, 196, 0 );
		$doc['content'] .= self::pdf_fill_rect( 102, $doc['y'] - 7, 451, 1, 210, 214, 205 );
		$doc['y']       -= 26;
	}

	private static function pdf_report_info_grid( array &$doc, array $items ): void {
		// Deux colonnes ; chaque rangée prend la hauteur de son texte le plus long (plus de chevauchement).
		$items     = array_values( $items );
		$rows      = array_chunk( $items, 2 );
		$wrap      = 42;
		$heights   = array();
		foreach ( $rows as $pair ) {
			$h = 0;
			foreach ( $pair as $item ) {
				$is_highlight = ! empty( $item[2] ) && 'highlight' === $item[2];
				$lines        = count( self::pdf_wrap_lines( (string) $item[1], $is_highlight ? 30 : $wrap ) );
				$h            = max( $h, 13 + ( $lines * ( $is_highlight ? 13 : 11 ) ) + ( $is_highlight ? 12 : 4 ) );
			}
			$heights[] = $h + 14;
		}
		$height = 22 + array_sum( $heights );
		self::pdf_report_ensure_space( $doc, $height + 10 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 248, 250, 252 );
		$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
		$y_row = $top - 25;
		foreach ( $rows as $r => $pair ) {
			foreach ( $pair as $col => $item ) {
				$x            = 58 + ( $col * 250 );
				$y            = $y_row;
				$is_highlight = ! empty( $item[2] ) && 'highlight' === $item[2];
				$lines        = self::pdf_wrap_lines( (string) $item[1], $is_highlight ? 30 : $wrap );
				if ( $is_highlight ) {
					$box = 13 + count( $lines ) * 13 + 10;
					$doc['content'] .= self::pdf_fill_rect( $x - 6, $y - $box + 10, 220, $box, 255, 255, 255 );
					$doc['content'] .= self::pdf_rect( $x - 6, $y - $box + 10, 220, $box );
				}
				$doc['content'] .= self::pdf_text_styled( $x, $y, 8, self::pdf_upper( (string) $item[0] ), 'F2', array( 100, 116, 139 ) );
				foreach ( $lines as $i => $line ) {
					$doc['content'] .= self::pdf_text_styled( $x, $y - 13 - ( $i * ( $is_highlight ? 13 : 11 ) ), $is_highlight ? 12 : 9, $line, $is_highlight ? 'F2' : 'F1', $is_highlight ? array( 6, 95, 70 ) : array( 15, 23, 42 ) );
				}
			}
			$y_row -= $heights[ $r ];
		}
		$doc['y'] -= $height + 20;
	}

	private static function pdf_report_score_box( array &$doc, array $items ): void {
		// À gauche : points, bonus, malus, total ; à droite : la note finale, bien visible, dans le cadre.
		$height = 150;
		self::pdf_report_ensure_space( $doc, $height + 12 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 238, 240, 235 );
		$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
		$row = 0;
		foreach ( array( 'Points obtenus', 'Bonus (points)', 'Malus (points)', 'Total des points' ) as $label ) {
			if ( ! array_key_exists( $label, $items ) ) {
				continue;
			}
			$y = $top - 24 - ( $row * 31 );
			$doc['content'] .= self::pdf_text_styled( 60, $y, 8, self::pdf_upper( $label ), 'F2', array( 91, 102, 114 ) );
			$doc['content'] .= self::pdf_text_styled( 60, $y - 14, 12, (string) $items[ $label ], 'F2', array( 49, 58, 69 ) );
			$row++;
		}
		if ( array_key_exists( 'Note finale', $items ) ) {
			$bx = 330;
			$bw = 205;
			$by = $top - $height + 18;
			$bh = $height - 36;
			$doc['content'] .= self::pdf_fill_rect( $bx, $by, $bw, $bh, 49, 58, 69 );
			$doc['content'] .= self::pdf_fill_rect( $bx, $by + $bh - 5, $bw, 5, 245, 196, 0 );
			$doc['content'] .= self::pdf_text_styled( $bx + 18, $by + $bh - 26, 9, 'NOTE FINALE', 'F2', array( 245, 196, 0 ) );
			$grade = (string) $items['Note finale'];
			$size  = strlen( $grade ) > 6 ? 22 : 40;
			$doc['content'] .= self::pdf_text_styled( $bx + 18, $by + 22, $size, $grade, 'F2', array( 255, 255, 255 ) );
		}
		$doc['y'] -= $height + 20;
	}

	private static function pdf_report_context_box( array &$doc, string $context ): void {
		$lines = self::pdf_wrap_lines( $context, 92 );
		// Titre (34 pt) + lignes de 14 pt + marge basse de 14 pt : le texte reste toujours dans le cadre vert.
		$height = max( 48, 34 + ( count( $lines ) * 14 ) + 8 );
		self::pdf_report_ensure_space( $doc, $height + 10 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 220, 247, 210 );
		$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
		$doc['content'] .= self::pdf_text_styled( 58, $top - 18, 9, 'CONTEXTE / SECTION', 'F2', array( 59, 130, 53 ) );
		$y = $top - 34;
		foreach ( $lines as $line ) {
			$doc['content'] .= self::pdf_text_styled( 58, $y, 10, $line, 'F1', array( 15, 23, 42 ) );
			$y -= 14;
		}
		$doc['y'] -= $height + 28;
	}

	private static function pdf_report_question_card( array &$doc, int $number, string $question, string $answer, float $points, float $max_points, string $comment ): void {
		$question_lines = self::pdf_wrap_lines( $question, 64 ); // Laisse la place aux points à droite.
		$answer_lines   = self::pdf_wrap_lines( 'Réponse élève : ' . $answer, 88 );
		$comment_lines  = '' !== trim( $comment ) ? self::pdf_wrap_lines( 'Commentaire : ' . $comment, 92 ) : array();
		$height = 34 + ( count( $question_lines ) * 14 ) + 18 + ( count( $answer_lines ) * 13 ) + ( count( $comment_lines ) * 13 ) + ( $comment_lines ? 12 : 0 ) + 14;
		self::pdf_report_ensure_space( $doc, $height + 8 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 255, 255, 255 );
		$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
		$doc['content'] .= self::pdf_text_styled( 58, $top - 20, 12, $number . '.', 'F2', array( 17, 24, 39 ) );
		$doc['content'] .= self::pdf_text_styled( 488, $top - 20, 11, self::format_points( $points ) . ' / ' . self::format_points( $max_points ), 'F2', array( 6, 95, 70 ) );
		$y = $top - 20;
		foreach ( $question_lines as $index => $line ) {
			$doc['content'] .= self::pdf_text_styled( 82, $y - ( $index * 14 ), 11, $line, 'F2', array( 17, 24, 39 ) );
		}
		$y = $top - 32 - ( count( $question_lines ) * 14 );
		foreach ( $answer_lines as $line ) {
			$doc['content'] .= self::pdf_text_styled( 82, $y, 10, $line, 'F1', array( 30, 64, 175 ) );
			$y -= 13;
		}
		// Commentaire : espacé de la réponse, en italique, aligné sur le numéro de la question.
		if ( $comment_lines ) {
			$y -= 12;
		}
		foreach ( $comment_lines as $line ) {
			$doc['content'] .= self::pdf_text_styled( 58, $y, 10, $line, 'F3', array( 71, 85, 105 ) );
			$y -= 13;
		}
		$doc['y'] -= $height + 12;
	}

	/** Images du document en cours (nom => JPEG, largeur, hauteur en pixels). */
	private static array $pdf_images = array();

	/** Prépare une image de la médiathèque pour le PDF (réduite, fond blanc, JPEG). Retourne son nom ou ''. */
	private static function pdf_register_image( int $media_id, int $max_px = 360 ): string {
		$name = 'Im' . $media_id;
		if ( isset( self::$pdf_images[ $name ] ) ) {
			return $name;
		}
		$file = $media_id ? get_attached_file( $media_id ) : '';
		if ( ! $file || ! is_readable( $file ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return '';
		}
		$src = @imagecreatefromstring( (string) file_get_contents( $file ) );
		if ( ! $src ) {
			return '';
		}
		$w     = imagesx( $src );
		$h     = imagesy( $src );
		$scale = min( 1, $max_px / max( $w, $h ) );
		$nw    = max( 1, (int) round( $w * $scale ) );
		$nh    = max( 1, (int) round( $h * $scale ) );
		$dst   = imagecreatetruecolor( $nw, $nh );
		imagefill( $dst, 0, 0, imagecolorallocate( $dst, 255, 255, 255 ) );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
		ob_start();
		imagejpeg( $dst, null, 85 );
		$data = (string) ob_get_clean();
		imagedestroy( $src );
		imagedestroy( $dst );
		self::$pdf_images[ $name ] = array( 'data' => $data, 'w' => $nw, 'h' => $nh );
		return $name;
	}

	/** Dessine une image enregistrée, ajustée dans un cadre (proportions gardées, centrée). */
	private static function pdf_draw_image( string $name, float $x, float $y_top, float $box_w, float $box_h ): string {
		$img = self::$pdf_images[ $name ] ?? null;
		if ( ! $img ) {
			return '';
		}
		$scale = min( $box_w / $img['w'], $box_h / $img['h'] );
		$w     = $img['w'] * $scale;
		$h     = $img['h'] * $scale;
		$ix    = $x + ( $box_w - $w ) / 2;
		$iy    = $y_top - $h - ( $box_h - $h ) / 2;
		return sprintf( "q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $w, $h, $ix, $iy, $name );
	}

	/**
	 * Question « remettre dans l'ordre » avec images : les images dans l'ordre choisi par l'élève,
	 * trois par ligne, le texte de chaque image juste dessous, et si elle est bien placée.
	 */
	private static function pdf_report_order_card( array &$doc, int $number, array $row, float $points, float $max_points, string $comment ): bool {
		if ( LogiEval_Secure_Question_Types::ORDERING !== ( $row['type'] ?? '' ) ) {
			return false;
		}
		$items = LogiEval_Secure_Question_Types::order_items( $row['options'] ?? '' );
		if ( ! array_filter( wp_list_pluck( $items, 'media_id' ) ) ) {
			return false; // sans image : la liste texte habituelle suffit
		}
		$by_id = array();
		foreach ( $items as $i => $item ) {
			$by_id[ $item['id'] ] = array( $item, $i + 1 );
		}
		$decoded = json_decode( (string) ( $row['answer'] ?? '' ), true );
		$order   = is_array( $decoded ) && isset( $decoded['order'] ) ? array_values( array_filter( (array) $decoded['order'], static fn( $id ) => isset( $by_id[ $id ] ) ) ) : array();
		$marks   = LogiEval_Secure_Question_Types::order_marks( $row, (string) ( $row['answer'] ?? '' ) );

		$question_lines = self::pdf_wrap_lines( (string) $row['prompt'], 64 );
		$comment_lines  = '' !== trim( $comment ) ? self::pdf_wrap_lines( 'Commentaire : ' . $comment, 92 ) : array();
		$cell_w = 150;
		$img_h  = 92;
		$cells  = array();
		foreach ( $order as $pos => $id ) {
			list( $item, $right ) = $by_id[ $id ];
			$caption = trim( $item['text'] ) !== '' ? $item['text'] : 'Image ' . $right;
			$cells[] = array(
				'img'     => $item['media_id'] ? self::pdf_register_image( (int) $item['media_id'] ) : '',
				'caption' => self::pdf_wrap_lines( ( $pos + 1 ) . '. ' . $caption, 30 ),
				'ok'      => ! empty( $marks[ $pos ] ),
				'right'   => $right,
			);
		}
		$rows_h = array();
		foreach ( array_chunk( $cells, 3 ) as $chunk ) {
			$max_lines = max( array_map( static fn( $c ) => count( $c['caption'] ), $chunk ) );
			$rows_h[]  = $img_h + 8 + ( $max_lines * 11 ) + 12 + 14;
		}
		$answer_block = $cells ? array_sum( $rows_h ) : 16;
		$height = 34 + ( count( $question_lines ) * 14 ) + 16 + $answer_block + ( count( $comment_lines ) * 13 ) + ( $comment_lines ? 12 : 0 ) + 14;
		self::pdf_report_ensure_space( $doc, $height + 8 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 255, 255, 255 );
		$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
		$doc['content'] .= self::pdf_text_styled( 58, $top - 20, 12, $number . '.', 'F2', array( 17, 24, 39 ) );
		$doc['content'] .= self::pdf_text_styled( 488, $top - 20, 11, self::format_points( $points ) . ' / ' . self::format_points( $max_points ), 'F2', array( 6, 95, 70 ) );
		foreach ( $question_lines as $i => $line ) {
			$doc['content'] .= self::pdf_text_styled( 82, $top - 20 - ( $i * 14 ), 11, $line, 'F2', array( 17, 24, 39 ) );
		}
		$y = $top - 32 - ( count( $question_lines ) * 14 );
		$doc['content'] .= self::pdf_text_styled( 82, $y, 10, $cells ? 'Réponse élève (ordre choisi) :' : 'Réponse élève : Non répondu', 'F1', array( 30, 64, 175 ) );
		$y -= 12;
		foreach ( array_chunk( $cells, 3 ) as $r => $chunk ) {
			foreach ( $chunk as $c => $cell ) {
				$x = 82 + $c * ( $cell_w + 8 );
				$doc['content'] .= self::pdf_fill_rect( $x, $y - $img_h, $cell_w, $img_h, 248, 250, 252 );
				if ( '' !== $cell['img'] ) {
					$doc['content'] .= self::pdf_draw_image( $cell['img'], $x + 3, $y - 3, $cell_w - 6, $img_h - 6 );
				}
				$doc['content'] .= self::pdf_rect( $x, $y - $img_h, $cell_w, $img_h );
				$ty = $y - $img_h - 12;
				foreach ( $cell['caption'] as $line ) {
					$doc['content'] .= self::pdf_text_styled( $x, $ty, 9, $line, 'F1', array( 15, 23, 42 ) );
					$ty -= 11;
				}
				$doc['content'] .= self::pdf_text_styled( $x, $ty - 1, 8, $cell['ok'] ? 'Bien placée' : 'Mal placée (place correcte : ' . $cell['right'] . ')', 'F2', $cell['ok'] ? array( 6, 95, 70 ) : array( 180, 35, 24 ) );
			}
			$y -= $rows_h[ $r ];
		}
		// Commentaire : espacé de la réponse, en italique, aligné sur le numéro de la question.
		if ( $comment_lines ) {
			$y -= 12;
		}
		foreach ( $comment_lines as $line ) {
			$doc['content'] .= self::pdf_text_styled( 58, $y, 10, $line, 'F3', array( 71, 85, 105 ) );
			$y -= 13;
		}
		$doc['y'] -= $height + 12;
		return true;
	}

	private static function pdf_report_alert_box( array &$doc, string $title, string $text ): void {
		$lines  = self::pdf_wrap_lines( $text, 90 );
		$height = max( 60, 40 + count( $lines ) * 13 + 8 );
		self::pdf_report_ensure_space( $doc, $height + 10 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 254, 228, 226 );
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 8, $height, 180, 35, 24 );
		$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
		$doc['content'] .= self::pdf_text_styled( 62, $top - 22, 13, self::pdf_upper( $title ), 'F2', array( 180, 35, 24 ) );
		$y = $top - 40;
		foreach ( $lines as $line ) {
			$doc['content'] .= self::pdf_text_styled( 62, $y, 10, $line, 'F1', array( 122, 39, 26 ) );
			$y -= 13;
		}
		$doc['y'] -= $height + 16;
	}

	private static function pdf_report_note_box( array &$doc, string $title, string $text ): void {
		// Un paragraphe par ligne du texte ; « 1. », « 2. », « • » : retrait des lignes suivantes ; « ATTENTION… » en gras.
		$lines = array();
		foreach ( preg_split( '/\R/u', $text ) as $para ) {
			$para = trim( $para );
			if ( '' === $para ) {
				continue;
			}
			$hang = (bool) preg_match( '/^(\d+\.|•)\s/u', $para );
			$bold = 0 === strpos( $para, 'ATTENTION' );
			foreach ( self::pdf_wrap_lines( $para, $hang ? 88 : 92 ) as $k => $piece ) {
				$lines[] = array( $piece, $hang && $k > 0 ? 14 : 0, $bold ? 'F2' : 'F1' );
			}
		}
		$height = max( 46, 34 + count( $lines ) * 13 + 8 );
		self::pdf_report_ensure_space( $doc, $height + 8 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 248, 250, 252 );
		$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
		$doc['content'] .= self::pdf_text_styled( 58, $top - 18, 9, self::pdf_upper( $title ), 'F2', array( 71, 85, 105 ) );
		$y = $top - 34;
		foreach ( $lines as $line ) {
			$doc['content'] .= self::pdf_text_styled( 58 + $line[1], $y, 10, $line[0], $line[2], 'F2' === $line[2] ? array( 180, 35, 24 ) : array( 15, 23, 42 ) );
			$y -= 13;
		}
		$doc['y'] -= $height + 14;
	}

	private static function pdf_report_security_event( array &$doc, string $date, string $severity, string $event, string $detail ): void {
		$text = $date . ' - ' . $severity . ' - ' . $event . ( '' !== $detail ? ' - ' . $detail : '' );
		self::pdf_report_note_box( $doc, 'Alerte sécurité', $text );
	}

	private static function pdf_add_line( array &$lines, string $text, int $size = 10, int $line_height = 14, int $indent = 0 ): void {
		$lines[] = array(
			'text'        => $text,
			'size'        => $size,
			'line_height' => $line_height,
			'indent'      => $indent,
		);
	}

	private static function pdf_add_wrapped_lines( array &$lines, string $text, int $size = 10, int $line_height = 14, int $indent = 0, int $max_chars = 92 ): void {
		$wrapped = explode( "\n", wordwrap( self::pdf_plain_text( $text ), $max_chars, "\n", false ) );
		foreach ( $wrapped as $line ) {
			self::pdf_add_line( $lines, $line, $size, $line_height, $indent );
		}
	}

	private static function build_pdf_from_lines( array $lines ): string {
		$pages   = array();
		$content = '';
		$y       = 790;
		foreach ( $lines as $line ) {
			$line_height = (int) $line['line_height'];
			if ( $y < 52 + $line_height ) {
				$pages[] = $content;
				$content = '';
				$y       = 790;
			}
			$content .= self::pdf_text( 42 + (int) $line['indent'], $y, (int) $line['size'], (string) $line['text'] );
			$y       -= $line_height;
		}
		$pages[] = $content;
		return self::build_pdf_document( $pages );
	}

	private static function build_pdf_document( array $page_contents ): string {
		$objects        = array();
		$objects[1]     = '';
		$objects[2]     = '';
		$objects[3]     = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4]     = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[5]     = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
		$next_object_id = 6;
		$page_refs      = array();
		// Images (JPEG) dessinées dans les pages : une XObject chacune, partagées par toutes les pages.
		$xobjects = '';
		foreach ( self::$pdf_images as $name => $img ) {
			$id = $next_object_id++;
			$objects[ $id ] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $img['w'] . ' /Height ' . (int) $img['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $img['data'] ) . " >>\nstream\n" . $img['data'] . "\nendstream";
			$xobjects .= '/' . $name . ' ' . $id . ' 0 R ';
		}
		$xobject_res = '' !== $xobjects ? ' /XObject << ' . $xobjects . '>>' : '';

		foreach ( $page_contents as $page_content ) {
			$content_id = $next_object_id++;
			$page_id    = $next_object_id++;
			$objects[ $content_id ] = '<< /Length ' . strlen( $page_content ) . " >>\nstream\n" . $page_content . "endstream";
			$objects[ $page_id ]    = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >>' . $xobject_res . ' >> /Contents ' . $content_id . ' 0 R >>';
			$page_refs[]            = $page_id . ' 0 R';
		}

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $page_refs ) . '] /Count ' . count( $page_refs ) . ' >>';

		$pdf     = "%PDF-1.4\n";
		$offsets = array( 0 );
		for ( $i = 1; $i < $next_object_id; $i++ ) {
			$offsets[ $i ] = strlen( $pdf );
			$pdf         .= $i . " 0 obj\n" . $objects[ $i ] . "\nendobj\n";
		}

		$xref_offset = strlen( $pdf );
		$pdf        .= "xref\n0 " . $next_object_id . "\n";
		$pdf        .= "0000000000 65535 f \n";
		for ( $i = 1; $i < $next_object_id; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size " . $next_object_id . " /Root 1 0 R >>\nstartxref\n" . $xref_offset . "\n%%EOF";
		self::$pdf_images = array();
		return $pdf;
	}

	/**
	 * Fiches « Code personnel d'évaluation », une page par élève, au visuel LogiExam (comme la version élève
	 * à imprimer) : bandeau avec logo, élève, évaluation, informations, grand cadre du code et rappels.
	 */
	private static function build_codes_pdf( array $evaluation, array $codes ): string {
		$question_count = count( self::get_questions_for_evaluation( (int) $evaluation['id'] ) );
		$course         = self::evaluation_course_name( $evaluation );
		$opens_at       = self::pdf_datetime( $evaluation['opens_at'] ?? '' );
		$closes_at      = self::pdf_datetime( $evaluation['closes_at'] ?? '' );

		$student_rows = array();
		foreach ( $codes as $user_id => $code ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				continue;
			}
			$student_rows[] = array(
				'name'  => self::student_full_name( $user_id ),
				'group' => self::group_label_for_user( $evaluation, $user_id ),
				'code'  => strtoupper( preg_replace( '/\s+/', '', (string) $code ) ),
			);
		}
		usort( $student_rows, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );

		self::$pdf_images = array();
		$doc = array( 'pages' => array(), 'content' => '', 'y' => 0, 'header' => 'Code personnel d’évaluation' );
		foreach ( $student_rows as $row ) {
			self::pdf_report_new_page( $doc );
			self::pdf_report_info_grid( $doc, array( array( 'Élève', $row['name'], 'highlight' ), array( 'Groupe classe', $row['group'] ) ) );
			self::pdf_report_section_title( $doc, (string) $evaluation['title'] );
			$info = array(
				array( 'Questions', (string) $question_count ),
				array( 'Durée', ! empty( $evaluation['duration_minutes'] ) ? (int) $evaluation['duration_minutes'] . ' min' : 'Non renseignée' ),
				array( 'Ouverture', $opens_at ),
				array( 'Fin de session', $closes_at ),
			);
			if ( '' !== $course ) {
				$info[] = array( 'Cours / module', $course );
			}
			self::pdf_report_info_grid( $doc, $info );

			// Grand cadre du code (anthracite, filet jaune), toujours sur la même page.
			$top = min( $doc['y'] - 4, 470 );
			$h   = 118;
			$doc['content'] .= self::pdf_fill_rect( 42, $top - $h, 511, $h, 49, 58, 69 );
			$doc['content'] .= self::pdf_fill_rect( 42, $top - 5, 511, 5, 245, 196, 0 );
			$doc['content'] .= self::pdf_text_styled( 64, $top - 30, 10, 'CODE À SAISIR', 'F2', array( 245, 196, 0 ) );
			$size = strlen( $row['code'] ) > 14 ? 30 : 44;
			$doc['content'] .= self::pdf_text_styled( 297.5 - self::pdf_text_width( $row['code'], $size ) * 1.12 / 2, $top - 88, $size, $row['code'], 'F2', array( 255, 255, 255 ) );
			$doc['y'] = $top - $h - 18;

			self::pdf_report_note_box(
				$doc,
				'Comment entrer dans l’évaluation',
				"1. Connectez-vous au site avec votre propre compte.\n"
				. "2. Ouvrez la page des évaluations LogiExam.\n"
				. "3. Saisissez ce code, lisez les règles, puis entrez dans l’évaluation en plein écran.\n"
				. "• Ce code est personnel : il fonctionne uniquement avec le compte de l’élève indiqué ci-dessus, et plus du tout une fois l’évaluation rendue.\n"
				. "• Quitter le plein écran ou changer d’onglet bloque l’évaluation.\n"
				. 'ATTENTION AUX TOUCHES : ne touchez jamais la touche Windows, ni Échap ou Alt + Tab : elles font sortir de l’écran.'
			);
		}
		if ( '' !== $doc['content'] ) {
			$doc['pages'][] = $doc['content'];
		}
		return self::build_pdf_document( $doc['pages'] );
	}

	private static function save_codes_pdf_export( array $evaluation, string $pdf ): ?string {
		if ( '' === $pdf ) {
			return null;
		}
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return null;
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'logiexam/codes';
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		$filename = sanitize_file_name( 'logiexam-codes-' . absint( $evaluation['id'] ) . '-' . gmdate( 'Ymd-His' ) . '.pdf' );
		$path     = trailingslashit( $dir ) . $filename;
		if ( false === file_put_contents( $path, $pdf ) ) {
			return null;
		}

		$url = trailingslashit( $upload['baseurl'] ) . 'logiexam/codes/' . $filename;
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$wpdb->insert(
			$t['exports'],
			array(
				'evaluation_id' => absint( $evaluation['id'] ),
				'user_id'       => null,
				'format'        => 'codes_pdf',
				'file_url'      => esc_url_raw( $url ),
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
			)
		);
		return $url;
	}

	private static function codes_pdf_export_path( string $url ): string {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['baseurl'] ) || empty( $upload['basedir'] ) ) {
			return '';
		}
		$baseurl = trailingslashit( $upload['baseurl'] );
		if ( 0 !== strpos( $url, $baseurl ) ) {
			return '';
		}
		$relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
		$path     = trailingslashit( $upload['basedir'] ) . $relative;
		$real     = realpath( $path );
		$base     = realpath( $upload['basedir'] );
		if ( ! $real || ! $base || 0 !== strpos( $real, $base ) || ! is_readable( $real ) ) {
			return '';
		}
		return $real;
	}

	private static function pdf_datetime( $mysql_datetime ): string {
		if ( empty( $mysql_datetime ) ) {
			return __( 'Non renseigné', 'logieval-secure' );
		}
		return self::pdf_local_datetime( $mysql_datetime );
	}

	private static function pdf_timezone(): DateTimeZone {
		return new DateTimeZone( 'Europe/Zurich' );
	}

	private static function pdf_local_datetime( $mysql_datetime ): string {
		$timestamp = self::local_datetime_timestamp( (string) $mysql_datetime );
		return $timestamp ? wp_date( 'd.m.Y H:i', $timestamp, self::pdf_timezone() ) : __( 'Non renseigné', 'logieval-secure' );
	}

	private static function pdf_utc_datetime( $mysql_datetime ): string {
		$timestamp = self::utc_datetime_timestamp( (string) $mysql_datetime );
		return $timestamp ? wp_date( 'd.m.Y H:i', $timestamp, self::pdf_timezone() ) : __( 'Non renseigné', 'logieval-secure' );
	}

	private static function pdf_text( float $x, float $y, int $size, string $text ): string {
		return sprintf( "BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n", $size, $x, $y, self::pdf_escape( $text ) );
	}

	private static function pdf_text_styled( float $x, float $y, int $size, string $text, string $font = 'F1', array $rgb = array( 0, 0, 0 ) ): string {
		$font = in_array( $font, array( 'F1', 'F2', 'F3' ), true ) ? $font : 'F1';
		return self::pdf_color( $rgb ) . sprintf( "BT /%s %d Tf %.2f %.2f Td (%s) Tj ET\n", $font, $size, $x, $y, self::pdf_escape( $text ) );
	}

	private static function pdf_wrapped_text( float $x, float $y, int $size, int $line_height, string $text, int $max_chars ): string {
		$lines   = explode( "\n", wordwrap( self::pdf_plain_text( $text ), $max_chars, "\n", false ) );
		$content = '';
		foreach ( $lines as $index => $line ) {
			$content .= self::pdf_text( $x, $y - ( $index * $line_height ), $size, $line );
		}
		return $content;
	}

	private static function pdf_wrapped_text_styled( float $x, float $y, int $size, int $line_height, string $text, int $max_chars, string $font = 'F1', array $rgb = array( 0, 0, 0 ) ): string {
		$content = '';
		foreach ( self::pdf_wrap_lines( $text, $max_chars ) as $index => $line ) {
			$content .= self::pdf_text_styled( $x, $y - ( $index * $line_height ), $size, $line, $font, $rgb );
		}
		return $content;
	}

	private static function pdf_rect( float $x, float $y, float $width, float $height ): string {
		return sprintf( "0.741 0.792 0.859 RG %.2f %.2f %.2f %.2f re S\n", $x, $y, $width, $height );
	}

	private static function pdf_fill_rect( float $x, float $y, float $width, float $height, int $r, int $g, int $b ): string {
		return self::pdf_color( array( $r, $g, $b ) ) . sprintf( "%.2f %.2f %.2f %.2f re f\n", $x, $y, $width, $height );
	}

	private static function pdf_color( array $rgb ): string {
		$r = max( 0, min( 255, (int) ( $rgb[0] ?? 0 ) ) ) / 255;
		$g = max( 0, min( 255, (int) ( $rgb[1] ?? 0 ) ) ) / 255;
		$b = max( 0, min( 255, (int) ( $rgb[2] ?? 0 ) ) ) / 255;
		return sprintf( "%.3f %.3f %.3f rg %.3f %.3f %.3f RG\n", $r, $g, $b, $r, $g, $b );
	}

	private static function pdf_wrap_lines( string $text, int $max_chars ): array {
		$text = self::pdf_plain_text( $text );
		if ( '' === $text ) {
			return array( '' );
		}
		return explode( "\n", wordwrap( $text, $max_chars, "\n", false ) );
	}

	private static function pdf_escape( string $text ): string {
		$text = self::pdf_plain_text( $text );
		if ( function_exists( 'iconv' ) ) {
			$converted = iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );
			if ( false !== $converted ) {
				$text = $converted;
			}
		}
		$text = str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', ' ', ' ' ), $text );
		return $text;
	}

	private static function pdf_plain_text( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}
}

