<?php
/**
 * LogiEval_Secure_Evaluations — partie « corrections ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Corrections {		public static function attempt_class_label( array $evaluation, int $user_id ): string {
		return self::group_label_for_user( $evaluation, $user_id );
	}

		private static function render_grade_form( array $attempt, ?array $evaluation, ?array $result, ?array $logs = null, bool $standalone = false, int $next_attempt_id = 0 ): void {
		$answers = self::get_answers_for_attempt( (int) $attempt['id'] );
		$logs    = null === $logs ? LogiEval_Secure_Security::get_logs_for_attempt( (int) $attempt['id'] ) : $logs;
		$locked  = self::attempt_correction_is_finished( $attempt, $result );
		$open    = $standalone || absint( $_GET['open_attempt'] ?? 0 ) === (int) $attempt['id'];
		?>
		<?php if ( $locked ) : ?>
			<div class="les-correction-done">
				<span>✓ <?php echo esc_html( sprintf( __( 'Correction terminée%1$s — note %2$s', 'logieval-secure' ), ! empty( $result['corrected_at'] ) ? ' ' . sprintf( __( 'le %s', 'logieval-secure' ), mysql2date( 'd.m.Y à H:i', (string) $result['corrected_at'] ) ) : '', self::pdf_optional_number( $result['final_grade'] ?? null ) ) ); ?></span>
				<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="logieval_secure_reopen_correction">
					<input type="hidden" name="attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">
					<?php self::frontend_redirect_field( array( 'logiexam_view' => 'corrections', 'evaluation_id' => absint( $attempt['evaluation_id'] ) ) ); ?>
					<?php self::return_field(); ?>
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Rouvrir la correction', 'logieval-secure' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
		<?php if ( $standalone ) : ?>
		<div class="les-grade-standalone">
		<?php else : ?>
		<details <?php echo $open ? 'open' : ''; ?>><summary><?php echo esc_html( $locked ? __( 'Voir la correction (verrouillée)', 'logieval-secure' ) : __( 'Corriger', 'logieval-secure' ) ); ?></summary>
		<?php endif; ?>
			<?php if ( $open && isset( $_GET['reopened'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Correction rouverte : vous pouvez modifier les points, commentaires, bonus et malus, puis finaliser à nouveau.', 'logieval-secure' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-les-dirty-guard>
				<fieldset class="les-locked-fieldset" <?php disabled( $locked ); ?>>
				<input type="hidden" name="action" value="logieval_secure_grade_attempt">
				<input type="hidden" name="attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">
				<?php self::frontend_redirect_field( array( 'logiexam_view' => 'corrections', 'evaluation_id' => absint( $attempt['evaluation_id'] ) ) ); ?>
				<?php self::return_field(); ?>
				<input type="hidden" name="next_attempt_id" value="<?php echo esc_attr( $next_attempt_id ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<details class="les-security-collapsed">
					<summary><span><?php esc_html_e( 'Sécurité de la copie', 'logieval-secure' ); ?></span> <?php self::render_security_alert_badge( $logs, $attempt ); ?></summary>
					<?php self::render_security_logs_details( $logs, $attempt ); ?>
				</details>
				<?php if ( self::ai_grading_is_enabled() && ! empty( $answers ) ) : ?>
					<div class="les-ai-bulk-toolbar is-student">
						<?php if ( $evaluation ) { self::render_ai_strictness_badge( $evaluation ); } ?>
						<button type="button" class="button les-button-secondary" data-les-ai-grade-bulk data-evaluation-id="<?php echo esc_attr( $attempt['evaluation_id'] ); ?>" data-attempt-id="<?php echo esc_attr( $attempt['id'] ); ?>"><?php esc_html_e( 'Pré-corriger toutes les questions de cet élève', 'logieval-secure' ); ?></button>
						<span class="les-ai-grade-status" aria-live="polite"></span>
						<p class="description"><?php esc_html_e( 'Les propositions remplissent les points et commentaires ci-dessous. Vérifiez-les, puis enregistrez ou finalisez la correction.', 'logieval-secure' ); ?></p>
					</div>
				<?php endif; ?>
				<?php self::render_ai_accept_all_button( $answers, __( 'Attribuer les points proposés par l’IA à toutes les questions', 'logieval-secure' ) ); ?>
				<label class="les-checkbox-line les-auto-next"><input type="checkbox" data-les-auto-next checked> <?php esc_html_e( 'Après avoir attribué les points, passer directement à la question suivante', 'logieval-secure' ); ?></label>
				<?php self::render_bonus_malus_card( $evaluation, $result, $answers ); ?>
				<?php foreach ( array_values( $answers ) as $answer_index => $answer ) : $own_title = self::correction_own_title( $answer ); ?>
						<?php
						$is_blank = ! self::answer_is_complete( $answer['answer'] ?? '' );
						if ( ! isset( $reviewed_ids ) ) {
							$reviewed_ids = self::reviewed_questions( (int) $attempt['id'] );
						}
						$is_reviewed = in_array( (int) $answer['question_id'], $reviewed_ids, true );
						?>
						<div class="les-answer-grade<?php echo $is_blank ? ' is-blank' : ''; ?>" data-les-ai-grade-target data-attempt-id="<?php echo esc_attr( $attempt['id'] ); ?>" data-question-id="<?php echo esc_attr( $answer['question_id'] ); ?>" data-les-max-points="<?php echo esc_attr( (float) $answer['points'] ); ?>"<?php echo $is_blank ? ' data-les-blank-answer="1"' : ''; ?><?php echo $is_reviewed ? ' data-les-reviewed="1"' : ''; ?>>
						<div class="les-correction-qhead"><span class="les-correction-qnum"><?php echo esc_html( sprintf( __( 'Question %d', 'logieval-secure' ), $answer_index + 1 ) ); ?></span><?php if ( '' !== $own_title ) : ?> <strong><?php echo esc_html( $own_title ); ?></strong><?php endif; ?> <span class="les-correction-qpts"><?php echo esc_html( self::format_points( (float) $answer['points'] ) ); ?> pts</span><?php if ( $is_blank ) : ?> <span class="les-blank-badge"><?php esc_html_e( 'Non répondu : 0 pt', 'logieval-secure' ); ?></span><?php endif; ?><span class="les-reviewed-badge"<?php echo $is_reviewed ? '' : ' hidden'; ?>><?php esc_html_e( '✓ Corrigée', 'logieval-secure' ); ?></span></div>
						<?php if ( ! empty( $answer['context'] ) ) : ?>
							<div class="les-question-context"><?php echo wp_kses_post( wpautop( $answer['context'] ) ); ?></div>
						<?php endif; ?>
						<div class="les-correction-prompt"><?php echo wp_kses_post( wpautop( $answer['prompt'] ) ); ?></div>
						<?php self::render_question_image( absint( $answer['media_id'] ?? 0 ), 'is-small' ); ?>
						<?php $student_user = get_userdata( (int) $attempt['user_id'] ); ?>
						<div class="les-answer-compare">
							<div class="les-student-answer">
								<strong class="les-student-answer-label"><span aria-hidden="true">✎</span> <?php echo esc_html( $student_user ? sprintf( __( 'Réponse de l’élève · %s', 'logieval-secure' ), $student_user->display_name ) : __( 'Réponse de l’élève', 'logieval-secure' ) ); ?></strong>
								<?php if ( ! LogiEval_Secure_Question_Types::render_order_answer( $answer, (string) ( $answer['answer'] ?? '' ) ) ) : ?>
									<?php $student_text = self::readable_answer( $answer['answer'] ?? '' ); ?>
									<div class="les-student-answer-text"><?php echo '' !== trim( (string) $student_text ) ? nl2br( esc_html( $student_text ) ) : '<em>' . esc_html__( 'Aucune réponse', 'logieval-secure' ) . '</em>'; ?></div>
								<?php endif; ?>
							</div>
							<?php self::render_expected_answer_box( (int) $answer['question_id'], (string) ( $answer['expected_answer'] ?? '' ), (string) ( $answer['type'] ?? '' ) ); ?>
						</div>
							<div class="les-crit-ai">
								<?php self::render_criteria_checklist( $answer ); ?>
								<?php self::render_ai_analysis_box( $answer ); ?>
							</div>
						<?php $max_points = max( 0, (float) $answer['points'] ); $current_points = self::correction_points_value( $answer, $max_points ); ?>
						<div class="les-points-tools">
							<strong><?php echo esc_html( sprintf( __( 'Points attribués / %s pts possibles', 'logieval-secure' ), self::format_points( $max_points ) ) ); ?></strong>
							<div class="les-points-buttons">
								<button type="button" class="button les-button-secondary" onclick="var i=this.closest('.les-points-tools').querySelector('input[name^=manual_points]');if(i){i.value=this.dataset.lesSetPoints;}" data-les-set-points="<?php echo esc_attr( self::format_points( $max_points ) ); ?>"><?php esc_html_e( 'Tous les points', 'logieval-secure' ); ?></button>
								<button type="button" class="button les-button-secondary" onclick="var i=this.closest('.les-points-tools').querySelector('input[name^=manual_points]');if(i){i.value=this.dataset.lesSetPoints;}" data-les-set-points="<?php echo esc_attr( self::format_points( $max_points / 2 ) ); ?>"><?php esc_html_e( 'Moitié', 'logieval-secure' ); ?></button>
								<button type="button" class="button les-button-secondary" onclick="var i=this.closest('.les-points-tools').querySelector('input[name^=manual_points]');if(i){i.value=this.dataset.lesSetPoints;}" data-les-set-points="0"><?php esc_html_e( 'Aucun point', 'logieval-secure' ); ?></button>
								<?php self::render_ai_accept_button( $answer ); ?>
								<label class="les-manual-points" data-les-manual-points><span><?php esc_html_e( 'Points manuels', 'logieval-secure' ); ?></span><input type="text" inputmode="decimal" name="manual_points[<?php echo esc_attr( $answer['question_id'] ); ?>]" value="<?php echo esc_attr( $current_points ); ?>" data-auto-points="<?php echo esc_attr( is_numeric( $answer['auto_points'] ?? null ) ? (float) $answer['auto_points'] : '' ); ?>" data-saved-manual="<?php echo esc_attr( is_numeric( $answer['manual_points'] ?? null ) ? (float) $answer['manual_points'] : '' ); ?>"><em class="les-manual-tag" aria-hidden="true"><?php esc_html_e( '✎ saisi à la main', 'logieval-secure' ); ?></em></label>
							</div>
							<?php if ( self::ai_grading_is_enabled() ) : ?>
								<div class="les-ai-grade-tools">
									<button type="button" class="button les-button-secondary" data-les-ai-grade data-attempt-id="<?php echo esc_attr( $attempt['id'] ); ?>" data-question-id="<?php echo esc_attr( $answer['question_id'] ); ?>"><?php esc_html_e( 'Pré-corriger avec l’IA', 'logieval-secure' ); ?></button>
									<?php self::render_ai_provider_choice( 'grading', true ); ?>
									<?php if ( count( LogiEval_Secure_AI::configured_providers( get_current_user_id() ) ) >= 2 ) : ?>
										<button type="button" class="button les-button-secondary" data-les-ai-compare-open data-attempt-id="<?php echo esc_attr( $attempt['id'] ); ?>" data-question-id="<?php echo esc_attr( $answer['question_id'] ); ?>"><?php esc_html_e( '⚖️ Comparer deux IA', 'logieval-secure' ); ?></button>
									<?php endif; ?>
									<span class="les-ai-grade-status" aria-live="polite"></span>
								</div>
								<div class="les-ai-compare" data-les-ai-compare hidden></div>
							<?php endif; ?>
							<?php if ( is_numeric( $answer['auto_points'] ?? null ) ) : ?>
								<small><?php echo esc_html( sprintf( __( 'Proposition automatique : %s pt(s).', 'logieval-secure' ), self::format_points( (float) $answer['auto_points'] ) ) ); ?></small>
							<?php endif; ?>
						</div>
						<?php list( $comment_text, $comment_auto ) = self::correction_comment_value( $answer ); ?>
						<details class="les-comment-box<?php echo '' !== $comment_text ? ' is-filled' : ''; ?>"<?php echo '' !== $comment_text ? ' open' : ''; ?>>
							<summary><span class="les-comment-add"><?php esc_html_e( '+ Ajouter un commentaire', 'logieval-secure' ); ?></span><span class="les-comment-has">💬 <?php esc_html_e( 'Commentaire :', 'logieval-secure' ); ?> <span class="les-comment-preview"><?php echo esc_html( $comment_text ); ?></span> <span class="les-comment-edit"><?php esc_html_e( 'modifier', 'logieval-secure' ); ?></span></span><span class="les-comment-title"><?php esc_html_e( 'Commentaire pour l’élève', 'logieval-secure' ); ?> <span class="les-comment-close"><?php esc_html_e( '(replier)', 'logieval-secure' ); ?></span></span></summary>
							<?php if ( $comment_auto ) : ?><p class="les-comment-auto-note"><?php esc_html_e( 'Commentaire proposé par l’IA : relisez-le et adaptez-le si besoin ; il est enregistré avec la correction.', 'logieval-secure' ); ?></p><?php endif; ?>
							<textarea name="teacher_comment[<?php echo esc_attr( $answer['question_id'] ); ?>]" aria-label="<?php esc_attr_e( 'Commentaire', 'logieval-secure' ); ?>"<?php echo $comment_auto ? ' data-auto-comment="' . esc_attr( $comment_text ) . '"' : ''; ?>><?php echo esc_textarea( $comment_text ); ?></textarea>
							<?php self::render_regen_comment_button( (int) $attempt['id'], (int) $answer['question_id'] ); ?>
						</details>
					</div>
				<?php endforeach; ?>
				<label><?php esc_html_e( 'Commentaire général', 'logieval-secure' ); ?><textarea name="teacher_comment_global"><?php echo esc_textarea( $result['teacher_comment'] ?? '' ); ?></textarea></label>
				<div class="les-actions <?php echo $standalone ? 'les-sticky-grade-bar' : ''; ?>">
					<?php if ( $standalone ) : ?>
						<span class="les-sticky-summary" data-les-sticky-summary></span>
					<?php endif; ?>
					<button type="submit" class="button les-button-secondary" name="grade_status" value="in_progress"><?php esc_html_e( 'Enregistrer la progression', 'logieval-secure' ); ?></button>
					<button type="submit" class="button" name="grade_status" value="graded"><?php esc_html_e( 'Finaliser la correction', 'logieval-secure' ); ?></button>
					<?php if ( $standalone && $next_attempt_id ) : ?>
						<button type="submit" class="button button-primary" name="grade_status" value="graded_next"><?php esc_html_e( 'Finaliser et élève suivant →', 'logieval-secure' ); ?></button>
					<?php endif; ?>
				</div>
				</fieldset>
			</form>
		<?php if ( $standalone ) : ?>
		</div>
		<?php else : ?>
		</details>
		<?php endif; ?>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Critères d'évaluation par question (comment les points sont attribués)
	 * ------------------------------------------------------------------- */

	/** @return array<int, array{label:string, points:float}> */
	public static function decode_criteria( $json ): array {
		$data = is_array( $json ) ? $json : json_decode( (string) $json, true );
		$out  = array();
		foreach ( (array) $data as $row ) {
			$label = trim( (string) ( $row['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$out[] = array(
				'label'  => $label,
				'points' => max( 0, (float) ( $row['points'] ?? 0 ) ),
			);
		}
		return $out;
	}

	/**
	 * 1.36.5 : une ligne du barème qui est une CONSIGNE de correction (« Accepter … pour le premier critère »,
	 * « Tolérer … », « Ne pas pénaliser … ») n'est pas un critère noté : l'IA la notait comme un critère de plus
	 * (presque toujours « non rempli ») et retirait des points à tort. Elle devient une précision donnée à l'IA.
	 */
	public static function criterion_is_note( string $label ): bool {
		$raw = trim( preg_replace( '/^[\s\-•*]+/u', '', mb_strtolower( $label ) ) );
		// « Accepter… », « Accepté : … », « On accepte… », « Aussi accepté… » (mais pas « Accepte la livraison », un geste noté).
		if ( preg_match( '/^(?:accepter|acceptée?s?\s*[:(]|on\s+accepte|(?:aussi|également)\s+accepté|accepter\s+aussi|tolérer|toléré|admettre|admis\s*:|valider\s+aussi|valable\s+aussi|compter\s+(?:aussi\s+)?juste|ne\s+pas\s+(?:pénaliser|exiger|compter|enlever|retirer|accepter)|pas\s+besoin|inutile\s+de|refuser|refusé\s*:)/u', $raw ) ) {
			return true;
		}
		if ( preg_match( '/^(?:remarque|note|nb|n\.b\.|attention|précision|consigne)\s*[:.]/u', $raw ) ) {
			return true;
		}
		$l = remove_accents( $raw );
		return (bool) preg_match( '/\b(?:pour|dans|au|du)\s+(?:le\s+)?(?:premier|1er|deuxieme|2e|second|troisieme|3e|quatrieme|4e|dernier)\s+critere\b|\bpour\s+le\s+critere\s+\d/u', $l );
	}

	/** Barème noté (sans les consignes) et consignes de correction, pour la correction et la pré-correction IA. */
	public static function scored_criteria( $json ): array {
		$all      = self::decode_criteria( $json );
		$criteria = array();
		$notes    = array();
		$dropped  = 0.0;
		foreach ( $all as $c ) {
			if ( self::criterion_is_note( $c['label'] ) ) {
				$notes[]  = $c['label'];
				$dropped += (float) $c['points'];
			} else {
				$criteria[] = $c;
			}
		}
		if ( ! $criteria ) {
			return array( 'criteria' => $all, 'notes' => array() ); // que des « consignes » : on garde le barème tel quel
		}
		return array( 'criteria' => $criteria, 'notes' => $notes, 'dropped_points' => $dropped );
	}

	private static function sanitize_criteria( $rows ): string {
		$clean = array();
		foreach ( (array) wp_unslash( $rows ) as $row ) {
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$clean[] = array(
				'label'  => $label,
				'points' => max( 0, self::parse_decimal( (string) ( $row['points'] ?? 0 ) ) ),
			);
		}
		return wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
	}

	private static function render_criteria_editor( string $name_prefix, array $question ): void {
		$prefix   = '' === $name_prefix ? 'criteria' : $name_prefix . '[criteria]';
		$criteria = self::decode_criteria( $question['grading_criteria'] ?? '' );
		?>
		<div class="les-criteria-editor" data-les-criteria-editor data-les-criteria-prefix="<?php echo esc_attr( $prefix ); ?>">
			<strong><?php esc_html_e( 'Critères d’évaluation (barème)', 'logieval-secure' ); ?></strong>
			<p class="description"><?php esc_html_e( 'Décrivez comment les points sont attribués, un critère par ligne. Exemple : « Définit correctement “recyclable” » = 0.5 pt. Les critères ne sont jamais montrés aux élèves pendant l’évaluation ; ils servent à la correction et à la pré-correction IA.', 'logieval-secure' ); ?></p>
			<p class="description"><?php esc_html_e( 'Une ligne qui commence par « Accepter… », « Tolérer… », « Ne pas pénaliser… » ou qui parle « du premier critère » est une consigne de correction : elle n’est pas notée (mettez-la à 0 pt), l’IA l’applique au critère concerné.', 'logieval-secure' ); ?></p>
			<div class="les-criteria-rows" data-les-criteria-rows>
				<?php foreach ( $criteria as $i => $criterion ) : ?>
					<div class="les-criteria-row">
						<input type="text" name="<?php echo esc_attr( $prefix . '[' . $i . '][label]' ); ?>" value="<?php echo esc_attr( $criterion['label'] ); ?>" placeholder="<?php esc_attr_e( 'Critère', 'logieval-secure' ); ?>">
						<input type="number" step="0.25" min="0" name="<?php echo esc_attr( $prefix . '[' . $i . '][points]' ); ?>" value="<?php echo esc_attr( self::format_points( $criterion['points'] ) ); ?>" aria-label="<?php esc_attr_e( 'Points', 'logieval-secure' ); ?>">
						<button type="button" class="button les-button-secondary" data-les-remove-criterion aria-label="<?php esc_attr_e( 'Supprimer ce critère', 'logieval-secure' ); ?>">✕</button>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="les-criteria-footer">
				<button type="button" class="button les-button-secondary" data-les-add-criterion><?php esc_html_e( '+ Ajouter un critère', 'logieval-secure' ); ?></button>
				<span class="les-criteria-total" data-les-criteria-total></span>
			</p>
		</div>
		<?php
	}

	/** Liste à cocher des critères dans la correction : cocher additionne les points dans le champ de la question. */
	private static function render_criteria_checklist( array $answer, bool $compact = false ): void {
		$scored   = self::scored_criteria( $answer['grading_criteria'] ?? '' );
		$criteria = $scored['criteria'];
		if ( empty( $criteria ) ) {
			return;
		}
		?>
		<div class="les-criteria-check<?php echo $compact ? ' is-compact' : ''; ?>" data-les-criteria-check>
			<?php if ( ! $compact ) : ?><strong><?php esc_html_e( 'Critères d’évaluation', 'logieval-secure' ); ?></strong><?php endif; ?>
			<?php $ai_status = self::ai_criteria_statuses( (string) ( $answer['ai_analysis'] ?? '' ) ); $ai_badges = array( 'ok' => array( '✓', __( 'IA : rempli', 'logieval-secure' ) ), 'partial' => array( '◐', __( 'IA : partiellement rempli', 'logieval-secure' ) ), 'miss' => array( '✗', __( 'IA : non rempli', 'logieval-secure' ) ) ); ?>
			<?php foreach ( $criteria as $n => $criterion ) : $st = $ai_status[ $n + 1 ] ?? ''; ?>
				<label class="les-check"><input type="checkbox" value="<?php echo esc_attr( $criterion['points'] ); ?>" data-les-criterion data-label="<?php echo esc_attr( $criterion['label'] ); ?>" data-ai-status="<?php echo esc_attr( $st ); ?>"> <span><?php echo esc_html( $criterion['label'] ); ?> <em>(<?php echo esc_html( self::format_points( $criterion['points'] ) ); ?> pt)</em></span> <span class="les-ai-crit is-<?php echo esc_attr( $st ); ?>" title="<?php echo esc_attr( $st ? $ai_badges[ $st ][1] : '' ); ?>"<?php echo $st ? '' : ' hidden'; ?>><?php echo esc_html( $st ? $ai_badges[ $st ][0] . ' IA' : '' ); ?></span></label>
			<?php endforeach; ?>
			<?php foreach ( $scored['notes'] as $note ) : ?>
				<p class="les-criteria-note">ℹ️ <?php echo esc_html( $note ); ?> <em><?php esc_html_e( '(consigne de correction, pas un critère noté)', 'logieval-secure' ); ?></em></p>
			<?php endforeach; ?>
			<?php if ( ! $compact ) : ?><p class="description"><?php esc_html_e( 'Cochez les critères remplis : les points se calculent automatiquement (vous pouvez toujours les ajuster).', 'logieval-secure' ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	private static function render_criteria_summary( array $question ): void {
		$criteria = self::decode_criteria( $question['grading_criteria'] ?? '' );
		if ( empty( $criteria ) ) {
			return;
		}
		?>
		<div class="les-criteria-summary"><strong><?php esc_html_e( 'Critères d’évaluation', 'logieval-secure' ); ?></strong>
			<ul>
				<?php foreach ( $criteria as $criterion ) : ?>
					<li><?php echo esc_html( $criterion['label'] ); ?> — <strong><?php echo esc_html( self::format_points( $criterion['points'] ) ); ?> pt</strong></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function attempt_correction_is_finished( array $attempt, ?array $result ): bool {
		return 'graded' === (string) ( $result['status'] ?? '' ) || 'graded' === (string) ( $attempt['status'] ?? '' );
	}

	public static function handle_reopen_correction(): void {
		self::require_teacher_post();
		global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$attempt_id = absint( $_POST['attempt_id'] ?? 0 );
		$attempt    = self::require_managed_attempt( $attempt_id );
		if ( self::attempt_is_cheating_cancelled( $attempt ) ) {
			wp_die( esc_html__( 'Cette copie a été annulée pour tricherie : elle ne peut pas être corrigée.', 'logieval-secure' ) );
		}
		$wpdb->update(
			$t['results'],
			array(
				'status'       => 'correction_in_progress',
				'corrected_at' => null,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'attempt_id' => $attempt_id )
		);
		if ( 'graded' === (string) $attempt['status'] ) {
			$wpdb->update( $t['attempts'], array( 'status' => 'submitted' ), array( 'id' => $attempt_id ) );
		}
		do_action( 'logipilot_exam_correction_reopened', (int) $attempt['evaluation_id'], $attempt_id );
		$args     = array( 'logiexam_view' => 'corrections', 'evaluation_id' => absint( $attempt['evaluation_id'] ), 'reopened' => 1, 'open_attempt' => $attempt_id, 'attempt' => $attempt_id );
		$fallback = admin_url( 'admin.php?page=logiexam-corrections&evaluation_id=' . absint( $attempt['evaluation_id'] ) . '&reopened=1&open_attempt=' . $attempt_id );
		wp_safe_redirect( self::post_redirect_url( $fallback, $args ) . '#les-attempt-' . $attempt_id );
		exit;
	}

	/** Encadré Bonus / Malus de la correction d'un élève, avec boutons rapides et récapitulatif en direct. */
	private static function render_bonus_malus_card( ?array $evaluation, ?array $result, array $answers ): void {
		$bonus     = (float) ( $result['bonus_points'] ?? 0 );
		$malus     = (float) ( $result['malus_points'] ?? 0 );
		$max       = array_sum( array_map( static fn( $a ) => (float) ( $a['points'] ?? 0 ), $answers ) );
		$total_pts = (float) ( $evaluation['total_points'] ?? $max );
		?>
		<div class="les-bonus-malus" data-les-bonus-malus data-total-points="<?php echo esc_attr( $total_pts ); ?>">
			<strong class="les-bonus-malus-title"><?php esc_html_e( 'Bonus / Malus', 'logieval-secure' ); ?></strong>
			<div class="les-bonus-malus-grid">
				<div class="les-bonus-field">
					<label><?php esc_html_e( 'Points bonus (ajoutés)', 'logieval-secure' ); ?>
						<input type="number" min="0" step="0.25" inputmode="decimal" name="bonus_points" value="<?php echo esc_attr( self::format_points( $bonus ) ); ?>" data-les-bonus>
					</label>
					<div class="les-points-buttons">
						<button type="button" class="button les-button-secondary" data-les-adjust="bonus" data-step="0.5">+0.5</button>
						<button type="button" class="button les-button-secondary" data-les-adjust="bonus" data-step="1">+1</button>
						<button type="button" class="button les-button-secondary" data-les-adjust="bonus" data-step="-0.5">−0.5</button>
						<button type="button" class="button les-button-secondary" data-les-adjust="bonus" data-step="reset"><?php esc_html_e( 'Aucun bonus', 'logieval-secure' ); ?></button>
					</div>
				</div>
				<div class="les-malus-field">
					<label><?php esc_html_e( 'Points malus (retirés)', 'logieval-secure' ); ?>
						<input type="number" min="0" step="0.25" inputmode="decimal" name="malus_points" value="<?php echo esc_attr( self::format_points( $malus ) ); ?>" data-les-malus>
					</label>
					<div class="les-points-buttons">
						<button type="button" class="button les-button-secondary" data-les-adjust="malus" data-step="0.5">+0.5</button>
						<button type="button" class="button les-button-secondary" data-les-adjust="malus" data-step="1">+1</button>
						<button type="button" class="button les-button-secondary" data-les-adjust="malus" data-step="-0.5">−0.5</button>
						<button type="button" class="button les-button-secondary" data-les-adjust="malus" data-step="reset"><?php esc_html_e( 'Aucun malus', 'logieval-secure' ); ?></button>
					</div>
				</div>
			</div>
			<p class="les-bonus-malus-summary" data-les-bonus-summary></p>
		</div>
		<?php
	}

	/**
	 * Commentaire de la question : celui de l'enseignant, sinon d'office celui proposé par l'IA (LogiOne 1.25.0),
	 * qui explique simplement pourquoi les points sont donnés ou non. Renvoie [texte, proposé par l'IA ?].
	 */
	private static function correction_comment_value( array $answer ): array {
		$teacher = trim( (string) ( $answer['teacher_comment'] ?? '' ) );
		if ( '' !== $teacher ) {
			return array( $teacher, false );
		}
		if ( ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
			return array( self::empty_answer_comment_text(), false ); // 1.36.4 : « Non répondu. » d'office
		}
		$ai = trim( (string) ( $answer['ai_comment'] ?? '' ) );
		if ( '' !== $ai && is_numeric( $answer['ai_points'] ?? null ) ) {
			return array( $ai, true );
		}
		return array( '', false );
	}

	/** Bouton « Régénérer le commentaire avec l'IA » : nouveau commentaire d'après VOS points et critères (rien d'autre ne change). */
	private static function render_regen_comment_button( int $attempt_id, int $question_id, bool $compact = false ): void {
		if ( ! self::ai_grading_is_enabled() ) {
			return;
		}
		printf(
			'<div class="les-regen-comment%1$s"><button type="button" class="button button-small les-button-secondary" data-les-regen-comment data-attempt-id="%2$d" data-question-id="%3$d" title="%4$s">%5$s</button><span class="les-regen-status" aria-live="polite"></span></div>',
			$compact ? ' is-compact' : '',
			$attempt_id,
			$question_id,
			esc_attr__( 'L’IA réécrit le commentaire pour l’élève d’après vos points et les critères cochés. Vos points ne sont pas modifiés.', 'logieval-secure' ),
			$compact ? esc_html__( '✨ Régénérer', 'logieval-secure' ) : esc_html__( '✨ Régénérer le commentaire avec l’IA', 'logieval-secure' )
		);
	}

	/** Bouton « Points de l'IA (x) » : reprend la proposition IA (points, et commentaire si le champ est vide). Caché tant qu'il n'y a pas de proposition. */
	private static function render_ai_accept_button( array $answer, bool $compact = false ): void {
		$has = is_numeric( $answer['ai_points'] ?? null );
		if ( ! $has && ! self::ai_grading_is_enabled() ) {
			return;
		}
		$points = $has ? self::format_points( (float) $answer['ai_points'] ) : '';
		$label  = $compact ? __( 'IA : %s', 'logieval-secure' ) : __( 'Points de l’IA (%s)', 'logieval-secure' );
		printf(
			'<button type="button" class="button les-ai-accept" data-les-ai-accept data-label="%1$s" data-points="%2$s" data-comment="%3$s" title="%4$s"%5$s>%6$s</button>',
			esc_attr( $label ),
			esc_attr( $points ),
			esc_attr( (string) ( $answer['ai_comment'] ?? '' ) ),
			esc_attr__( 'Attribuer les points proposés par l’IA (le commentaire de l’IA est repris si le vôtre est vide)', 'logieval-secure' ),
			$has ? '' : ' hidden',
			esc_html( sprintf( $label, $points ) )
		);
	}

	/** Bouton « Attribuer les points proposés par l'IA » pour plusieurs réponses à la fois. */
	private static function render_ai_accept_all_button( array $answers, string $label ): void {
		$count = count( array_filter( $answers, static fn( $a ) => is_array( $a ) && is_numeric( $a['ai_points'] ?? null ) ) );
		if ( ! $count && ! self::ai_grading_is_enabled() ) {
			return;
		}
		printf(
			'<div class="les-ai-accept-all-bar"><button type="button" class="button les-ai-accept-all" data-les-ai-accept-all%1$s>%2$s</button><span class="les-ai-accept-status" aria-live="polite"></span></div>',
			$count ? '' : ' hidden',
			esc_html( $label )
		);
	}

	/**
	 * Titre propre à afficher dans la correction, ou « » quand le titre n'est que le début de la consigne
	 * (titre créé automatiquement, coupé après quelques mots) : la consigne complète est alors affichée en entier juste dessous.
	 */
	private static function correction_own_title( array $question ): string {
		$norm   = static fn( $v ) => trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $v ), ENT_QUOTES, 'UTF-8' ) ) );
		$title  = $norm( $question['title'] ?? '' );
		$prompt = $norm( $question['prompt'] ?? '' );
		if ( '' === $title || ( '' !== $prompt && 0 === mb_stripos( $prompt, rtrim( $title, ' .…:' ) ) ) ) {
			return '';
		}
		return $title;
	}

	private static function render_question_by_question_correction( array $evaluation, array $attempts ): void {
		$questions = self::get_answers_for_attempt( ! empty( $attempts[0]['id'] ) ? (int) $attempts[0]['id'] : 0 );
		if ( empty( $questions ) || empty( $attempts ) ) {
			return;
		}
			?>
				<details class="les-question-correction-mode" <?php echo self::$embedded_in_evaluation ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Correction question par question', 'logieval-secure' ); ?></summary>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="logieval_secure_save_question_corrections">
						<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation['id'] ); ?>">
						<?php self::frontend_redirect_field( array( 'logiexam_view' => 'corrections', 'evaluation_id' => absint( $evaluation['id'] ) ) ); ?>
						<?php self::return_field(); ?>
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<p><?php esc_html_e( 'Corrigez les questions pour les élèves, puis enregistrez toute la progression en un seul bloc sans clôturer la correction.', 'logieval-secure' ); ?></p>
						<p class="les-actions les-correction-savebar">
							<?php if ( self::ai_grading_is_enabled() ) : ?>
								<button type="button" class="button les-button-secondary" data-les-ai-grade-bulk data-evaluation-id="<?php echo esc_attr( $evaluation['id'] ); ?>"><?php esc_html_e( 'Pré-corriger toutes les questions', 'logieval-secure' ); ?></button>
								<span class="les-ai-grade-status" aria-live="polite"></span>
							<?php endif; ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer toute la progression', 'logieval-secure' ); ?></button>
						</p>
					<label class="les-checkbox-line les-auto-next"><input type="checkbox" data-les-auto-next checked> <?php esc_html_e( 'Après avoir attribué les points, passer directement à l’élève suivant', 'logieval-secure' ); ?></label>
					<input type="hidden" name="les_resume_card" value="" data-les-resume-field>
					<?php
					// Où en est la correction ? Une carte est « faite » quand des points ont été enregistrés (ou réponse vide).
					$done_map  = array();
					$first_todo = null;
					$done_count = 0;
					$all_count  = 0;
					foreach ( $questions as $qi => $q ) {
						foreach ( $attempts as $att ) {
							$row = self::answer_row_by_question( self::get_answers_for_attempt( (int) $att['id'] ), (int) $q['question_id'] );
							if ( ! $row ) {
								continue;
							}
							$is_done = is_numeric( $row['manual_points'] ?? null ) || ! self::answer_is_complete( $row['answer'] ?? '' );
							$done_map[ (int) $q['question_id'] ][ (int) $att['id'] ] = $is_done;
							$all_count++;
							$done_count += $is_done ? 1 : 0;
							if ( ! $is_done && null === $first_todo ) {
								$u          = get_userdata( (int) $att['user_id'] );
								$first_todo = array( 'id' => 'les-qcard-' . (int) $q['question_id'] . '-' . (int) $att['id'], 'label' => sprintf( __( 'Question %1$d · %2$s', 'logieval-secure' ), $qi + 1, $u ? $u->display_name : '#' . $att['user_id'] ) );
							}
						}
					}
					?>
					<div class="les-resume-banner<?php echo $first_todo ? '' : ' is-done'; ?>" data-les-resume-banner<?php echo $first_todo ? ' data-target="' . esc_attr( $first_todo['id'] ) . '"' : ''; ?>>
						<span class="les-resume-count"><?php echo esc_html( sprintf( __( '%1$d / %2$d réponses corrigées', 'logieval-secure' ), $done_count, $all_count ) ); ?></span>
						<?php if ( $first_todo && $done_count > 0 ) : ?>
							<a class="button button-primary" href="#<?php echo esc_attr( $first_todo['id'] ); ?>" data-les-resume-go>▶ <?php echo esc_html( sprintf( __( 'Reprendre : %s', 'logieval-secure' ), $first_todo['label'] ) ); ?></a>
						<?php elseif ( ! $first_todo ) : ?>
							<strong><?php esc_html_e( '✓ Toutes les réponses ont des points enregistrés.', 'logieval-secure' ); ?></strong>
						<?php endif; ?>
					</div>
					<?php foreach ( $questions as $index => $question ) : ?>
						<section class="les-question-correction-section">
							<input type="hidden" name="question_ids[]" value="<?php echo esc_attr( $question['question_id'] ); ?>">
							<h4><?php echo esc_html( sprintf( __( 'Question %d', 'logieval-secure' ), $index + 1 ) ); ?><?php $own_title = self::correction_own_title( $question ); if ( '' !== $own_title ) { echo ' - ' . esc_html( $own_title ); } ?> <span><?php echo esc_html( self::format_points( (float) $question['points'] ) ); ?> pts</span><?php $qd = $done_map[ (int) $question['question_id'] ] ?? array(); $qdone = count( array_filter( $qd ) ); ?> <em class="les-qprogress<?php echo $qd && $qdone === count( $qd ) ? ' is-complete' : ''; ?>"><?php echo esc_html( sprintf( __( '%1$d / %2$d corrigées', 'logieval-secure' ), $qdone, count( $qd ) ) ); ?></em></h4>
							<?php if ( self::ai_grading_is_enabled() ) : ?>
								<div class="les-ai-bulk-toolbar is-question">
									<button type="button" class="button les-button-secondary" data-les-ai-grade-bulk data-evaluation-id="<?php echo esc_attr( $evaluation['id'] ); ?>" data-question-id="<?php echo esc_attr( $question['question_id'] ); ?>"><?php esc_html_e( 'Pré-corriger cette question pour tous les élèves', 'logieval-secure' ); ?></button>
									<span class="les-ai-grade-status" aria-live="polite"></span>
								</div>
							<?php endif; ?>
						<?php self::render_ai_accept_all_button( array_filter( array_map( static fn( $a ) => self::answer_row_by_question( self::get_answers_for_attempt( (int) $a['id'] ), (int) $question['question_id'] ), $attempts ) ), __( 'Attribuer les points proposés par l’IA à tous les élèves pour cette question', 'logieval-secure' ) ); ?>
						<?php if ( ! empty( $question['context'] ) ) : ?><div class="les-question-context"><?php echo wp_kses_post( wpautop( $question['context'] ) ); ?></div><?php endif; ?>
						<div class="les-prompt les-correction-prompt"><?php echo wp_kses_post( wpautop( $question['prompt'] ) ); ?></div>
						<?php self::render_question_image( absint( $question['media_id'] ?? 0 ), 'is-small' ); ?>
					<?php self::render_criteria_summary( $question ); ?>
						<div class="les-qcards">
							<?php foreach ( $attempts as $attempt ) : $user = get_userdata( (int) $attempt['user_id'] ); $answers = self::get_answers_for_attempt( (int) $attempt['id'] ); $answer = self::answer_row_by_question( $answers, (int) $question['question_id'] ); if ( ! $answer ) { continue; } $max_points = max( 0, (float) $answer['points'] ); $is_blank = ! self::answer_is_complete( $answer['answer'] ?? '' ); $student_name = $user ? $user->display_name : '#' . $attempt['user_id']; $qname = 'question_manual_points[' . (int) $question['question_id'] . '][' . (int) $attempt['id'] . ']'; ?>
								<?php $card_done = ! empty( $done_map[ (int) $question['question_id'] ][ (int) $attempt['id'] ] ); ?>
								<article id="les-qcard-<?php echo esc_attr( (int) $question['question_id'] . '-' . (int) $attempt['id'] ); ?>" class="les-qcard les-question-grade-form<?php echo $is_blank ? ' is-blank' : ''; ?><?php echo $card_done ? ' is-saved' : ''; ?>"<?php echo $card_done ? ' data-les-saved="1"' : ''; ?> data-les-ai-grade-target data-attempt-id="<?php echo esc_attr( $attempt['id'] ); ?>" data-question-id="<?php echo esc_attr( $answer['question_id'] ); ?>" data-les-max-points="<?php echo esc_attr( $max_points ); ?>">
									<input type="hidden" name="attempt_ids[<?php echo esc_attr( $question['question_id'] ); ?>][]" value="<?php echo esc_attr( $attempt['id'] ); ?>">
									<header class="les-qcard-head">
										<strong class="les-qcard-name"><?php echo esc_html( $student_name ); ?></strong>
										<?php if ( $is_blank ) : ?><span class="les-blank-badge"><?php esc_html_e( 'Non répondu : 0 pt', 'logieval-secure' ); ?></span><?php endif; ?>
										<?php echo is_numeric( $answer['manual_points'] ?? null ) ? '<span class="les-save-state les-alert-badge is-ok">' . esc_html__( 'Sauvegardé', 'logieval-secure' ) . '</span>' : '<span class="les-save-state les-alert-badge is-warning">' . esc_html__( 'À enregistrer', 'logieval-secure' ) . '</span>'; ?>
									</header>
									<div class="les-qcard-question">
										<span class="les-correction-qnum"><?php echo esc_html( sprintf( __( 'Question %d', 'logieval-secure' ), $index + 1 ) ); ?></span><?php $qtitle = self::correction_own_title( $question ); if ( '' !== $qtitle ) : ?> <strong><?php echo esc_html( $qtitle ); ?></strong><?php endif; ?> <span class="les-correction-qpts"><?php echo esc_html( self::format_points( (float) $question['points'] ) ); ?> pts</span>
										<?php if ( '' !== trim( wp_strip_all_tags( (string) ( $question['context'] ?? '' ) ) ) ) : ?>
											<details class="les-qcard-context" open>
												<summary><?php esc_html_e( 'Contexte', 'logieval-secure' ); ?></summary>
												<div class="les-question-context"><?php echo wp_kses_post( wpautop( $question['context'] ) ); ?></div>
											</details>
										<?php endif; ?>
										<div class="les-qcard-prompt"><?php echo wp_kses_post( wpautop( $question['prompt'] ) ); ?></div>
										<?php self::render_question_image( absint( $question['media_id'] ?? 0 ), 'is-small' ); ?>
									</div>
									<div class="les-answer-compare">
										<div class="les-student-answer">
											<strong class="les-student-answer-label"><span aria-hidden="true">✎</span> <?php echo esc_html( sprintf( __( 'Réponse de l’élève · %s', 'logieval-secure' ), $student_name ) ); ?></strong>
											<?php if ( ! LogiEval_Secure_Question_Types::render_order_answer( $answer, (string) ( $answer['answer'] ?? '' ) ) ) : ?>
												<?php $student_text = self::readable_answer( $answer['answer'] ?? '' ); ?>
												<div class="les-student-answer-text"><?php echo '' !== trim( (string) $student_text ) ? nl2br( esc_html( $student_text ) ) : '<em>' . esc_html__( 'Aucune réponse', 'logieval-secure' ) . '</em>'; ?></div>
											<?php endif; ?>
										</div>
										<?php self::render_expected_answer_box( (int) $question['question_id'], (string) ( $question['expected_answer'] ?? '' ), (string) ( $question['type'] ?? '' ) ); ?>
									</div>
									<div class="les-crit-ai">
										<?php self::render_criteria_checklist( $answer ); ?>
										<?php self::render_ai_analysis_box( $answer ); ?>
									</div>
									<div class="les-points-tools">
										<strong><?php echo esc_html( sprintf( __( 'Points attribués / %s pts possibles', 'logieval-secure' ), self::format_points( $max_points ) ) ); ?></strong>
										<div class="les-points-buttons">
											<?php foreach ( array( array( __( 'Tous les points', 'logieval-secure' ), $max_points ), array( __( 'Moitié', 'logieval-secure' ), $max_points / 2 ), array( __( 'Aucun point', 'logieval-secure' ), 0 ) ) as $pbtn ) : ?>
												<button type="button" class="button les-button-secondary" onclick="var i=this.closest('.les-question-grade-form').querySelector('input[name^=question_manual_points]');if(i){i.value=this.dataset.lesSetPoints;i.dispatchEvent(new Event('input',{bubbles:true}));}" data-les-set-points="<?php echo esc_attr( self::format_points( (float) $pbtn[1] ) ); ?>"><?php echo esc_html( $pbtn[0] ); ?></button>
											<?php endforeach; ?>
											<?php self::render_ai_accept_button( $answer ); ?>
											<label class="les-manual-points" data-les-manual-points><span><?php esc_html_e( 'Points manuels', 'logieval-secure' ); ?></span><input type="text" inputmode="decimal" name="<?php echo esc_attr( $qname ); ?>" value="<?php echo esc_attr( self::correction_points_value( $answer, $max_points ) ); ?>" data-auto-points="<?php echo esc_attr( is_numeric( $answer['auto_points'] ?? null ) ? (float) $answer['auto_points'] : '' ); ?>" data-saved-manual="<?php echo esc_attr( is_numeric( $answer['manual_points'] ?? null ) ? (float) $answer['manual_points'] : '' ); ?>"><em class="les-manual-tag" aria-hidden="true"><?php esc_html_e( '✎ saisi à la main', 'logieval-secure' ); ?></em></label>
										</div>
										<?php if ( self::ai_grading_is_enabled() ) : ?>
											<div class="les-ai-grade-tools">
												<button type="button" class="button les-button-secondary" data-les-ai-grade data-attempt-id="<?php echo esc_attr( $attempt['id'] ); ?>" data-question-id="<?php echo esc_attr( $answer['question_id'] ); ?>"><?php esc_html_e( 'Pré-corriger avec l’IA', 'logieval-secure' ); ?></button>
												<span class="les-ai-grade-status" aria-live="polite"></span>
											</div>
										<?php endif; ?>
									</div>
									<?php list( $comment_text, $comment_auto ) = self::correction_comment_value( $answer ); ?>
									<details class="les-comment-box<?php echo '' !== $comment_text ? ' is-filled' : ''; ?>"<?php echo '' !== $comment_text ? ' open' : ''; ?>>
										<summary><span class="les-comment-add"><?php esc_html_e( '+ Ajouter un commentaire', 'logieval-secure' ); ?></span><span class="les-comment-has">💬 <?php esc_html_e( 'Commentaire :', 'logieval-secure' ); ?> <span class="les-comment-preview"><?php echo esc_html( $comment_text ); ?></span> <span class="les-comment-edit"><?php esc_html_e( 'modifier', 'logieval-secure' ); ?></span></span><span class="les-comment-title"><?php esc_html_e( 'Commentaire pour l’élève', 'logieval-secure' ); ?> <span class="les-comment-close"><?php esc_html_e( '(replier)', 'logieval-secure' ); ?></span></span></summary>
										<?php if ( $comment_auto ) : ?><p class="les-comment-auto-note"><?php esc_html_e( 'Commentaire proposé par l’IA : relisez-le et adaptez-le si besoin ; il est enregistré avec la correction.', 'logieval-secure' ); ?></p><?php endif; ?>
										<textarea name="question_teacher_comment[<?php echo esc_attr( $question['question_id'] ); ?>][<?php echo esc_attr( $attempt['id'] ); ?>]" aria-label="<?php esc_attr_e( 'Commentaire', 'logieval-secure' ); ?>"<?php echo $comment_auto ? ' data-auto-comment="' . esc_attr( $comment_text ) . '"' : ''; ?>><?php echo esc_textarea( $comment_text ); ?></textarea>
										<?php self::render_regen_comment_button( (int) $attempt['id'], (int) $question['question_id'] ); ?>
									</details>
								</article>
							<?php endforeach; ?>
						</div>
							</section>
						<?php endforeach; ?>
						<section class="les-question-correction-section les-bonus-malus-section">
							<h4><?php esc_html_e( 'Bonus / Malus par élève', 'logieval-secure' ); ?></h4>
							<p class="description"><?php esc_html_e( 'Points ajoutés (bonus) ou retirés (malus) au total de chaque élève. Enregistrés avec toute la progression.', 'logieval-secure' ); ?></p>
							<div class="les-table-scroll">
								<table class="widefat striped les-bonus-malus-table">
									<thead><tr><th><?php esc_html_e( 'Élève', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Points bonus', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Points malus', 'logieval-secure' ); ?></th></tr></thead>
									<tbody>
										<?php foreach ( $attempts as $attempt ) : $user = get_userdata( (int) $attempt['user_id'] ); $bm_result = self::get_result_by_attempt( (int) $attempt['id'] ); ?>
											<tr>
												<td><?php echo esc_html( $user ? $user->display_name : '#' . $attempt['user_id'] ); ?></td>
												<td><input type="number" min="0" step="0.25" inputmode="decimal" name="bonus_malus[<?php echo esc_attr( $attempt['id'] ); ?>][bonus]" value="<?php echo esc_attr( self::format_points( (float) ( $bm_result['bonus_points'] ?? 0 ) ) ); ?>"></td>
												<td><input type="number" min="0" step="0.25" inputmode="decimal" name="bonus_malus[<?php echo esc_attr( $attempt['id'] ); ?>][malus]" value="<?php echo esc_attr( self::format_points( (float) ( $bm_result['malus_points'] ?? 0 ) ) ); ?>"></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</section>
						<p class="les-actions les-correction-savebar">
							<?php if ( self::ai_grading_is_enabled() ) : ?>
								<button type="button" class="button les-button-secondary" data-les-ai-grade-bulk data-evaluation-id="<?php echo esc_attr( $evaluation['id'] ); ?>"><?php esc_html_e( 'Pré-corriger toutes les questions', 'logieval-secure' ); ?></button>
								<span class="les-ai-grade-status" aria-live="polite"></span>
							<?php endif; ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer toute la progression', 'logieval-secure' ); ?></button>
						</p>
					</form>
			</details>
		<?php
	}

		/**
	 * Copie annulée pour tricherie : nombre maximal de sorties du mode sécurisé atteint.
	 * Elle ne doit jamais être corrigée.
	 */
	/**
	 * Réponse attendue dans la correction. Si la question n'en a pas, on le dit clairement et l'enseignant
	 * peut l'ajouter tout de suite (questions à réponse écrite), sans quitter la correction.
	 */
	private static function render_expected_answer_box( int $question_id, string $expected, string $type ): void {
		if ( '' !== trim( $expected ) ) {
			?>
			<div class="les-expected-answer"><strong><?php esc_html_e( 'Réponse attendue', 'logieval-secure' ); ?></strong><div><?php echo wp_kses_post( wpautop( self::readable_answer( $expected ) ) ); ?></div></div>
			<?php
			return;
		}
		$question = self::get_question( $question_id );
		$editable = in_array( $type, array( 'short_answer', 'long_answer' ), true ) && self::can_manage_question( $question );
		?>
		<div class="les-expected-answer is-missing" data-les-expected-box data-question-id="<?php echo esc_attr( $question_id ); ?>">
			<strong><?php esc_html_e( 'Réponse attendue', 'logieval-secure' ); ?></strong>
			<p class="les-expected-missing"><?php esc_html_e( 'Aucune réponse attendue n’est enregistrée pour cette question.', 'logieval-secure' ); ?></p>
			<?php if ( $editable ) : ?>
				<details class="les-expected-add">
					<summary><?php esc_html_e( '✎ Ajouter la réponse attendue', 'logieval-secure' ); ?></summary>
					<textarea rows="3" data-les-expected-text placeholder="<?php esc_attr_e( 'Ex. : la Bolivie et la Suisse ; le pavillon s’achète ou se choisit, il ne dépend pas du pays du propriétaire.', 'logieval-secure' ); ?>"></textarea>
					<button type="button" class="button button-primary" data-les-expected-save><?php esc_html_e( 'Enregistrer dans la question', 'logieval-secure' ); ?></button>
					<small><?php esc_html_e( 'Elle est enregistrée dans la question (banque de questions) : elle servira pour tous les élèves et pour la pré-correction de l’IA.', 'logieval-secure' ); ?></small>
					<span class="les-expected-status" aria-live="polite"></span>
				</details>
			<?php else : ?>
				<small><?php esc_html_e( 'À compléter dans la question (Banque de questions).', 'logieval-secure' ); ?></small>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Ajoute la réponse attendue d'une question qui n'en a pas (depuis la correction). */
	public static function ajax_set_expected_answer(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
		}
		$question = self::get_question( absint( $_POST['question_id'] ?? 0 ) );
		if ( ! self::can_manage_question( $question ) ) {
			wp_send_json_error( array( 'message' => __( 'Cette question appartient à un autre enseignant.', 'logieval-secure' ) ), 403 );
		}
		if ( ! in_array( (string) $question['type'], array( 'short_answer', 'long_answer' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Pour ce type de question, complétez la réponse dans la banque de questions.', 'logieval-secure' ) ), 400 );
		}
		if ( '' !== trim( (string) $question['expected_answer'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Cette question a déjà une réponse attendue. Rechargez la page.', 'logieval-secure' ) ), 409 );
		}
		$text = sanitize_textarea_field( wp_unslash( $_POST['expected_answer'] ?? '' ) );
		if ( '' === trim( $text ) ) {
			wp_send_json_error( array( 'message' => __( 'Écrivez la réponse attendue.', 'logieval-secure' ) ), 400 );
		}
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$wpdb->update( $t['questions'], array( 'expected_answer' => $text, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $question['id'] ) );
		wp_send_json_success( array( 'html' => wpautop( esc_html( $text ) ), 'message' => __( 'Réponse attendue enregistrée.', 'logieval-secure' ) ) );
	}

	public static function attempt_is_cheating_cancelled( array $attempt ): bool {
		return 'security_terminated' === (string) ( $attempt['status'] ?? '' );
	}

	private static function render_cheating_cancelled_notice( array $attempt, array $logs ): void {
		$exits = array_values(
			array_filter(
				array_reverse( $logs ),
				static fn( $log ) => in_array( (string) ( $log['event_type'] ?? '' ), self::security_exit_event_types(), true )
			)
		);
		$unlocks = count( array_filter( $logs, static fn( $log ) => 'teacher_unlock' === (string) ( $log['event_type'] ?? '' ) ) );
		?>
		<div class="les-cheating-notice">
			<div class="les-cheating-notice-head">
				<span class="les-cheating-icon" aria-hidden="true">⛔</span>
				<div>
					<strong><?php esc_html_e( 'Évaluation annulée pour tricherie — ne pas corriger', 'logieval-secure' ); ?></strong>
					<p><?php echo esc_html( sprintf( __( 'L’élève a quitté le mode sécurisé %1$d fois (%2$d déblocage(s) accordé(s)). L’évaluation a été arrêtée automatiquement le %3$s. Cette copie est exclue de la correction : note 1. Le PDF élève peut être exporté normalement.', 'logieval-secure' ), max( count( $exits ), self::security_block_count_from_logs( $logs ) + 1 ), $unlocks, mysql2date( 'd.m.Y à H:i', (string) ( $attempt['submitted_at'] ?? '' ) ) ) ); ?></p>
				</div>
			</div>
			<?php if ( ! empty( $exits ) ) : ?>
				<ol class="les-cheating-exits">
					<?php foreach ( $exits as $exit ) : ?>
						<li><strong><?php echo esc_html( mysql2date( 'H:i:s', (string) $exit['created_at'] ) ); ?></strong> — <?php echo esc_html( self::security_event_label( (string) $exit['event_type'] ) ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
			<details class="les-cheating-details">
				<summary><?php esc_html_e( 'Voir le journal de sécurité complet', 'logieval-secure' ); ?></summary>
				<?php self::render_security_logs_details( $logs, $attempt ); ?>
			</details>
		</div>
		<?php
	}

	/**
	 * 1.38.2 : questions déjà corrigées par l'enseignant pour une copie (correction enregistrée en partie).
	 * Les points proposés (IA, calcul automatique) sont enregistrés pour toutes les questions : ils ne disent pas ce
	 * que l'enseignant a vraiment regardé. On retient donc les questions qu'il a traitées (option, sans toucher aux tables).
	 */
	public static function reviewed_questions( int $attempt_id ): array {
		$ids = get_option( 'logiexam_reviewed_' . $attempt_id, array() );
		return is_array( $ids ) ? array_values( array_unique( array_map( 'absint', $ids ) ) ) : array();
	}

	private static function save_reviewed_questions( int $attempt_id, array $ids ): void {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( $ids ) {
			update_option( 'logiexam_reviewed_' . $attempt_id, $ids, false );
		}
	}

	private static function attempt_needs_correction( array $attempt, ?array $result ): bool {
		if ( self::attempt_is_cheating_cancelled( $attempt ) ) {
			return false;
		}
		$attempt_status = (string) ( $attempt['status'] ?? '' );
		$result_status  = (string) ( $result['status'] ?? '' );
		if ( in_array( $result_status, array( 'graded', 'archived' ), true ) || 'graded' === $attempt_status ) {
			return false;
		}
		if ( ! empty( $attempt['submitted_at'] ) ) {
			return true;
		}
		return in_array( $attempt_status, array( 'submitted', 'submitted_locked', 'locked_exit_attempt', 'security_terminated' ), true )
			|| in_array( $result_status, array( 'to_grade', 'submitted', 'suspected_cheating' ), true );
	}

		private static function correction_points_value( array $answer, float $max_points ): string {
		if ( is_numeric( $answer['manual_points'] ?? null ) ) {
			return self::format_points( (float) $answer['manual_points'] );
		}
		if ( ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
			return self::format_points( 0 ); // 1.36.4 : question restée vide → 0 pt d'office
		}
		if ( is_numeric( $answer['auto_points'] ?? null ) ) {
			return self::format_points( (float) $answer['auto_points'] );
		}
		return '';
	}

		public static function handle_grade_attempt(): void {
			self::require_teacher_post();
			global $wpdb;
		$t          = LogiEval_Secure_Database::tables();
		$attempt_id = absint( $_POST['attempt_id'] ?? 0 );
		$attempt    = self::require_managed_attempt( $attempt_id );
		if ( self::attempt_is_cheating_cancelled( $attempt ) ) {
			wp_die( esc_html__( 'Cette copie a été annulée pour tricherie (sorties répétées du mode sécurisé). Elle ne peut pas être corrigée.', 'logieval-secure' ) );
		}
		if ( self::attempt_correction_is_finished( $attempt, self::get_result_by_attempt( $attempt_id ) ) ) {
			wp_die( esc_html__( 'Cette correction est terminée. Cliquez sur « Rouvrir la correction » pour la modifier.', 'logieval-secure' ) );
		}

		$manual_points = (array) ( $_POST['manual_points'] ?? array() );
		$comments      = (array) ( $_POST['teacher_comment'] ?? array() );
		if ( isset( $_POST['reviewed_questions'] ) ) {
			$posted = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['reviewed_questions'] ) ) ) );
			$posted = array_intersect( $posted, array_map( 'absint', array_keys( $manual_points ) ) );
			self::save_reviewed_questions( $attempt_id, array_merge( self::reviewed_questions( $attempt_id ), $posted ) );
		}
		foreach ( $manual_points as $question_id => $points ) {
			$question_id = absint( $question_id );
			$max_points  = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT points FROM {$t['eval_questions']} WHERE evaluation_id=%d AND question_id=%d",
					(int) $attempt['evaluation_id'],
					$question_id
				)
				);
				$points_raw    = is_scalar( $points ) ? trim( (string) wp_unslash( $points ) ) : '';
				$current_answer = (string) $wpdb->get_var( $wpdb->prepare( "SELECT answer FROM {$t['answers']} WHERE attempt_id=%d AND question_id=%d", $attempt_id, $question_id ) );
				$parsed_points = '' === $points_raw
					? ( self::answer_is_complete( $current_answer ) ? null : 0 )
					: max( 0, min( $max_points, self::parse_decimal( $points_raw ) ) );
				$answer_data = array(
					'manual_points'   => $parsed_points,
					'teacher_comment' => sanitize_textarea_field( wp_unslash( $comments[ $question_id ] ?? '' ) ),
				'updated_at'      => current_time( 'mysql' ),
			);
			$updated     = $wpdb->update(
				$t['answers'],
				$answer_data,
				array( 'attempt_id' => $attempt_id, 'question_id' => $question_id )
			);
			self::record_ai_feedback( $attempt_id, $question_id, $parsed_points, $max_points, (string) $answer_data['teacher_comment'] );
			if ( 0 === $updated && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['answers']} WHERE attempt_id=%d AND question_id=%d", $attempt_id, $question_id ) ) ) {
				$answer_data['evaluation_id'] = (int) $attempt['evaluation_id'];
				$answer_data['attempt_id']    = $attempt_id;
				$answer_data['question_id']   = $question_id;
				$answer_data['answer']        = '';
				$answer_data['auto_points']   = null;
				// 1.36.4 : la table des réponses n'a pas de colonne created_at (l'insertion échouait).
				$wpdb->insert( $t['answers'], $answer_data );
				}
			}

			self::ensure_zero_for_empty_answers( $attempt_id );
			$grade_status       = sanitize_key( $_POST['grade_status'] ?? 'graded' );
			$requested_finalize = in_array( $grade_status, array( 'graded', 'graded_next' ), true );
			$missing_corrections = self::uncorrected_answer_count( $attempt_id );
			$finalize = $requested_finalize && 0 === $missing_corrections;
			$result = self::get_result_by_attempt( $attempt_id );
			$points = self::sum_attempt_points( $attempt_id );
			$bonus  = max( 0, array_key_exists( 'bonus_points', $_POST ) ? self::parse_decimal( $_POST['bonus_points'] ) : (float) ( $result['bonus_points'] ?? 0 ) );
			$malus  = max( 0, array_key_exists( 'malus_points', $_POST ) ? self::parse_decimal( $_POST['malus_points'] ) : (float) ( $result['malus_points'] ?? 0 ) );
			$total  = LogiEval_Secure_Grading::final_total( $points, $bonus, $malus );
			$eval   = self::get_evaluation( (int) $attempt['evaluation_id'] );
			$grade  = LogiEval_Secure_Grading::grade( $total, $eval ? $eval['total_points'] : 0 );
			$data   = array(
				'points_obtained' => $points,
			'bonus_points'    => $bonus,
			'malus_points'    => $malus,
			'final_total'     => $total,
			'final_grade'     => $grade,
			'status'          => $finalize ? 'graded' : 'correction_in_progress',
			'teacher_comment' => array_key_exists( 'teacher_comment_global', $_POST ) ? sanitize_textarea_field( wp_unslash( $_POST['teacher_comment_global'] ) ) : (string) ( $result['teacher_comment'] ?? '' ),
			'corrected_by'    => get_current_user_id(),
			'corrected_at'    => $finalize ? current_time( 'mysql' ) : null,
			'updated_at'      => current_time( 'mysql' ),
		);

		if ( $result ) {
			$wpdb->update( $t['results'], $data, array( 'attempt_id' => $attempt_id ) );
		} else {
			$data['attempt_id']     = $attempt_id;
			$data['evaluation_id']  = (int) $attempt['evaluation_id'];
			$data['user_id']        = (int) $attempt['user_id'];
			$data['created_at']     = current_time( 'mysql' );
			$wpdb->insert( $t['results'], $data );
		}

			if ( $finalize ) {
				$wpdb->update( $t['attempts'], array( 'status' => 'graded' ), array( 'id' => $attempt_id ) );
				do_action( 'logipilot_exam_attempt_finalized', (int) $attempt['evaluation_id'], $attempt_id );
			}
				$redirect_args = array( 'logiexam_view' => 'corrections', 'evaluation_id' => absint( $attempt['evaluation_id'] ), 'attempt' => $attempt_id );
				$next_attempt  = absint( $_POST['next_attempt_id'] ?? 0 );
				if ( 'graded_next' === $grade_status && $finalize && $next_attempt ) {
					$redirect_args['attempt'] = $next_attempt;
				}
				if ( $requested_finalize && $missing_corrections > 0 ) {
					$redirect_args['correction_incomplete'] = $missing_corrections;
					$redirect_args['incomplete_attempt'] = $attempt_id;
					$redirect_args['question_saved'] = 1;
				} else {
					$redirect_args['graded'] = 1;
				}
				$fallback = admin_url( 'admin.php?page=logiexam-corrections&evaluation_id=' . absint( $attempt['evaluation_id'] ) . ( isset( $redirect_args['correction_incomplete'] ) ? '&correction_incomplete=' . absint( $missing_corrections ) . '&incomplete_attempt=' . absint( $attempt_id ) . '&question_saved=1' : '&graded=1' ) );
				wp_safe_redirect( self::post_redirect_url( $fallback, $redirect_args ) );
				exit;
			}

		public static function handle_save_question_corrections(): void {
			self::require_teacher_post();
			global $wpdb;
			$t             = LogiEval_Secure_Database::tables();
			$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
			$evaluation    = self::require_managed_evaluation( $evaluation_id );
			$question_ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['question_ids'] ?? array() ) ) ) ) );
			if ( empty( $question_ids ) && ! empty( $_POST['question_id'] ) ) {
				$question_ids[] = absint( $_POST['question_id'] );
			}
			if ( ! $evaluation || empty( $question_ids ) ) {
				wp_die( esc_html__( 'Évaluation ou questions introuvables.', 'logieval-secure' ) );
			}

			$posted_attempt_ids = (array) ( $_POST['attempt_ids'] ?? array() );
			$manual_points      = (array) ( $_POST['question_manual_points'] ?? array() );
			$comments           = (array) ( $_POST['question_teacher_comment'] ?? array() );
			$has_attempts       = false;
			foreach ( $question_ids as $posted_question_id ) {
				if ( ! empty( $posted_attempt_ids[ $posted_question_id ] ) ) {
					$has_attempts = true;
					break;
				}
			}
			$is_ajax = ! empty( $_POST['les_ajax'] ); // 1.44.0 : sauvegarde automatique (sans quitter la page)
			if ( ! $has_attempts ) {
				if ( $is_ajax ) {
					wp_send_json_success( array( 'saved' => 0, 'time' => current_time( 'H:i:s' ) ) );
				}
				wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-corrections&evaluation_id=' . $evaluation_id . '&question_saved=1' ), array( 'logiexam_view' => 'corrections', 'evaluation_id' => $evaluation_id, 'question_saved' => 1 ) ) );
				exit;
			}

			$now    = current_time( 'mysql' );
			$failed = false;
			$saved  = 0;
			$wpdb->query( 'START TRANSACTION' );

			foreach ( $question_ids as $question_id ) {
				if ( ! self::question_belongs_to_evaluation( $question_id, $evaluation_id ) ) {
					$failed = true;
					break;
				}

				$max_points = (float) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT points FROM {$t['eval_questions']} WHERE evaluation_id=%d AND question_id=%d",
						$evaluation_id,
						$question_id
					)
				);
				$attempt_ids = array_values( array_unique( array_map( 'absint', (array) ( $posted_attempt_ids[ $question_id ] ?? array() ) ) ) );
				$question_points = (array) ( $manual_points[ $question_id ] ?? array() );
				$question_comments = (array) ( $comments[ $question_id ] ?? array() );
				foreach ( $attempt_ids as $attempt_id ) {
					$attempt = self::get_attempt( $attempt_id );
					if ( ! $attempt || (int) $attempt['evaluation_id'] !== $evaluation_id ) {
						$failed = true;
						break 2;
					}
					if ( self::attempt_is_cheating_cancelled( $attempt ) || self::attempt_correction_is_finished( $attempt, self::get_result_by_attempt( $attempt_id ) ) ) {
						continue; // Copie annulée ou correction terminée : non modifiée ici.
					}

						$points_raw    = is_scalar( $question_points[ $attempt_id ] ?? null ) ? trim( (string) wp_unslash( $question_points[ $attempt_id ] ) ) : '';
						$current_answer = self::answer_row_by_question( self::get_answers_for_attempt( $attempt_id ), $question_id );
						$parsed_points = '' === $points_raw
							? ( self::answer_is_complete( $current_answer['answer'] ?? '' ) ? null : 0 )
							: max( 0, min( $max_points, self::parse_decimal( $points_raw ) ) );
						$answer_data   = array(
							'manual_points'   => $parsed_points,
							'teacher_comment' => sanitize_textarea_field( wp_unslash( $question_comments[ $attempt_id ] ?? '' ) ),
						'updated_at'      => $now,
					);

					$updated = $wpdb->update(
						$t['answers'],
						$answer_data,
						array(
							'attempt_id'  => $attempt_id,
							'question_id' => $question_id,
						)
					);
					if ( false === $updated ) {
						$failed = true;
						break 2;
					}
					$saved++;
					self::record_ai_feedback( $attempt_id, $question_id, $parsed_points, (float) $max_points, (string) $answer_data['teacher_comment'] );
					if ( 0 === $updated && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['answers']} WHERE attempt_id=%d AND question_id=%d", $attempt_id, $question_id ) ) ) {
						$answer_data['evaluation_id'] = $evaluation_id;
						$answer_data['attempt_id']    = $attempt_id;
						$answer_data['question_id']   = $question_id;
						$answer_data['answer']        = '';
						$answer_data['auto_points']   = null;
						if ( false === $wpdb->insert( $t['answers'], $answer_data ) ) {
							$failed = true;
							break 2;
							}
						}

						self::ensure_zero_for_empty_answers( $attempt_id );
						$result = self::get_result_by_attempt( $attempt_id );
						$points = self::sum_attempt_points( $attempt_id );
					$posted_bm = (array) ( $_POST['bonus_malus'][ $attempt_id ] ?? array() );
					$bonus     = max( 0, isset( $posted_bm['bonus'] ) ? self::parse_decimal( $posted_bm['bonus'] ) : (float) ( $result['bonus_points'] ?? 0 ) );
					$malus     = max( 0, isset( $posted_bm['malus'] ) ? self::parse_decimal( $posted_bm['malus'] ) : (float) ( $result['malus_points'] ?? 0 ) );
					$total  = LogiEval_Secure_Grading::final_total( $points, $bonus, $malus );
					$grade  = LogiEval_Secure_Grading::grade( $total, $evaluation['total_points'] ?? 0 );
					$status = ( $result && 'graded' === ( $result['status'] ?? '' ) ) ? 'graded' : 'correction_in_progress';
					$data   = array(
						'points_obtained' => $points,
						'bonus_points'    => $bonus,
						'malus_points'    => $malus,
						'final_total'     => $total,
						'final_grade'     => $grade,
						'status'          => $status,
						'teacher_comment' => (string) ( $result['teacher_comment'] ?? '' ),
						'corrected_by'    => get_current_user_id(),
						'corrected_at'    => 'graded' === $status ? ( $result['corrected_at'] ?? $now ) : null,
						'updated_at'      => $now,
					);

					if ( $result ) {
						if ( false === $wpdb->update( $t['results'], $data, array( 'attempt_id' => $attempt_id ) ) ) {
							$failed = true;
							break 2;
						}
					} else {
						$data['attempt_id']    = $attempt_id;
						$data['evaluation_id'] = $evaluation_id;
						$data['user_id']       = (int) $attempt['user_id'];
						$data['created_at']    = $now;
						if ( false === $wpdb->insert( $t['results'], $data ) ) {
							$failed = true;
							break 2;
						}
					}
				}
			}

			if ( $failed ) {
				$wpdb->query( 'ROLLBACK' );
				if ( $is_ajax ) {
					wp_send_json_error( array( 'message' => __( 'Sauvegarde automatique impossible : cliquez sur « Enregistrer toute la progression ».', 'logieval-secure' ) ) );
				}
				wp_die( esc_html__( 'Impossible d’enregistrer le bloc de corrections. Aucune correction partielle n’a été appliquée.', 'logieval-secure' ) );
			}

			$wpdb->query( 'COMMIT' );
			if ( $is_ajax ) {
				wp_send_json_success( array( 'saved' => $saved, 'time' => current_time( 'H:i:s' ) ) );
			}
			$resume = sanitize_html_class( (string) wp_unslash( $_POST['les_resume_card'] ?? '' ) );
			$args   = array( 'logiexam_view' => 'evaluation', 'evaluation_id' => $evaluation_id, 'step' => 'grade', 'correction_mode' => 'question', 'question_saved' => 1, 'les_resume' => 1 );
			wp_safe_redirect( self::post_redirect_url( self::evaluation_url( $evaluation_id, 'grade', $args ), $args ) . ( 0 === strpos( $resume, 'les-qcard-' ) ? '#' . $resume : '' ) );
			exit;
		}

	private static function answer_has_correction_points( array $answer ): bool {
		return is_numeric( $answer['manual_points'] ?? null ) || is_numeric( $answer['auto_points'] ?? null );
	}
}

