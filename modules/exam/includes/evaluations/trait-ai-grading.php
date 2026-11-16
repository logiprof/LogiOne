<?php
/**
 * LogiEval_Secure_Evaluations — partie « ai-grading ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Ai_Grading {
	private static function render_ai_settings_panel(): void {
		$user_id   = get_current_user_id();
		$ai        = LogiEval_Secure_AI::get_user_settings( $user_id );
		$providers = LogiEval_Secure_AI::providers();
		$has_key   = LogiEval_Secure_AI::has_api_key( $user_id );
		$test      = get_transient( 'logieval_ai_test_' . $user_id );
		if ( $test ) {
			delete_transient( 'logieval_ai_test_' . $user_id );
		}
		?>
		<section class="les-panel" data-les-ai-settings data-les-settings-section>
			<h2><?php esc_html_e( 'Ma pré-correction IA', 'logieval-secure' ); ?></h2>
			<p><?php esc_html_e( 'L’IA propose des points et un commentaire, mais vous gardez toujours la validation finale. Vous utilisez votre propre clé API : les frais sont facturés directement sur votre compte chez le fournisseur choisi. Votre clé est chiffrée et n’est visible par personne, pas même l’administrateur.', 'logieval-secure' ); ?></p>
			<?php if ( ! self::ai_grading_allowed() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'La pré-correction IA est actuellement désactivée sur ce site par l’administrateur. Vous pouvez préparer vos réglages : ils seront utilisés dès qu’elle sera autorisée.', 'logieval-secure' ); ?></p></div>
			<?php endif; ?>
			<?php if ( is_array( $test ) ) : ?>
				<div class="notice <?php echo ! empty( $test['ok'] ) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( (string) $test['message'] ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="logieval_secure_save_ai_settings">
				<?php self::frontend_redirect_field( array( 'logiexam_view' => 'settings' ) ); ?>
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<div class="les-settings-pane" data-les-settings-pane="ia">
				<label class="les-checkbox-line">
					<input type="checkbox" name="ai[enabled]" value="1" <?php checked( (int) $ai['enabled'], 1 ); ?>>
					<?php esc_html_e( 'Activer les boutons de pré-correction IA dans mes corrections', 'logieval-secure' ); ?>
				</label>
				<?php $configured = LogiEval_Secure_AI::configured_providers( $user_id ); ?>
				<fieldset class="les-ai-providers">
					<legend><?php esc_html_e( 'Mes fournisseurs d’IA', 'logieval-secure' ); ?></legend>
					<p class="description"><?php esc_html_e( 'Vous pouvez connecter plusieurs fournisseurs (une clé chacun). Au moment de corriger ou de créer des questions, vous choisissez lequel utiliser : par exemple un modèle bon marché pour les questions simples, un modèle plus précis pour les réponses longues.', 'logieval-secure' ); ?></p>
					<?php foreach ( $providers as $key => $provider ) : $profile = $ai['profiles'][ $key ] ?? array( 'model' => '', 'base_url' => '', 'api_key' => '' ); $is_on = in_array( $key, $configured, true ); ?>
						<details class="les-ai-provider-row<?php echo $is_on ? ' is-on' : ''; ?>" data-les-ai-provider-row="<?php echo esc_attr( $key ); ?>" <?php echo $is_on ? 'open' : ''; ?>>
							<summary>
								<strong><?php echo esc_html( $provider['label'] ); ?></strong>
								<?php if ( $is_on ) : ?>
									<span class="les-ai-provider-badge"><?php echo esc_html( sprintf( __( 'Connecté · %s', 'logieval-secure' ), LogiEval_Secure_AI::model_for( $user_id, $key ) ) ); ?></span>
								<?php else : ?>
									<span class="les-ai-provider-badge is-off"><?php esc_html_e( 'Non connecté', 'logieval-secure' ); ?></span>
								<?php endif; ?>
							</summary>
							<div class="les-form-row">
								<label><?php esc_html_e( 'Modèle', 'logieval-secure' ); ?>
									<input type="text" name="ai[profiles][<?php echo esc_attr( $key ); ?>][model]" value="<?php echo esc_attr( $profile['model'] ); ?>" placeholder="<?php echo esc_attr( $provider['default_model'] ?: 'nom-du-modele' ); ?>">
								</label>
								<label><?php esc_html_e( 'Clé API', 'logieval-secure' ); ?>
									<input type="password" name="ai[profiles][<?php echo esc_attr( $key ); ?>][api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== (string) $profile['api_key'] ? __( 'Clé enregistrée - laisser vide pour la conserver', 'logieval-secure' ) : __( 'Coller votre clé API', 'logieval-secure' ) ); ?>">
								</label>
							</div>
							<?php if ( 'compatible' === $key ) : ?>
								<label><?php esc_html_e( 'Adresse de l’API (URL de base compatible OpenAI)', 'logieval-secure' ); ?>
									<input type="url" name="ai[profiles][compatible][base_url]" value="<?php echo esc_attr( $profile['base_url'] ); ?>" placeholder="https://exemple.ch/v1">
									<span class="description"><?php esc_html_e( 'Par exemple un service hébergé en Suisse ou un serveur de votre école. L’adresse doit accepter les requêtes /chat/completions au format OpenAI.', 'logieval-secure' ); ?></span>
								</label>
							<?php endif; ?>
							<p class="description">
								<?php esc_html_e( 'Modèle vide = modèle proposé par défaut.', 'logieval-secure' ); ?>
								<?php if ( $provider['key_url'] ) : ?> <a href="<?php echo esc_url( $provider['key_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Obtenir une clé', 'logieval-secure' ); ?></a><?php endif; ?>
								<?php if ( $provider['models_url'] ) : ?> · <a href="<?php echo esc_url( $provider['models_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Modèles et prix', 'logieval-secure' ); ?></a><?php endif; ?>
							</p>
							<?php if ( '' !== (string) $profile['api_key'] ) : ?>
								<label class="les-checkbox-line"><input type="checkbox" name="ai[profiles][<?php echo esc_attr( $key ); ?>][clear_api_key]" value="1"> <?php esc_html_e( 'Supprimer cette clé', 'logieval-secure' ); ?></label>
							<?php endif; ?>
						</details>
					<?php endforeach; ?>
				</fieldset>
				<fieldset class="les-ai-learning les-ai-roles">
				<legend><?php esc_html_e( 'Quelle IA pour quoi ?', 'logieval-secure' ); ?></legend>
				<p class="description"><?php esc_html_e( 'L’IA proposée par défaut pour chaque travail. Vous pouvez toujours en choisir une autre au moment de corriger (menu « IA » à côté de « Pré-corriger avec l’IA ») ou de créer, et comparer deux IA avec « ⚖️ Comparer deux IA ».', 'logieval-secure' ); ?></p>
				<div class="les-form-row">
					<label><?php esc_html_e( 'Par défaut pour corriger', 'logieval-secure' ); ?>
						<select name="ai[provider]" data-les-ai-provider>
							<?php foreach ( $providers as $key => $provider ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ai['provider'], $key ); ?>><?php echo esc_html( $provider['label'] . ( in_array( $key, $configured, true ) ? '' : ' ' . __( '(non connecté)', 'logieval-secure' ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><?php esc_html_e( 'Par défaut pour créer des questions', 'logieval-secure' ); ?>
						<select name="ai[gen_provider]">
							<option value=""><?php esc_html_e( 'Le même que pour corriger', 'logieval-secure' ); ?></option>
							<?php foreach ( $providers as $key => $provider ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ai['gen_provider'], $key ); ?>><?php echo esc_html( $provider['label'] . ( in_array( $key, $configured, true ) ? '' : ' ' . __( '(non connecté)', 'logieval-secure' ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<?php $cmp = self::ai_compare_stats( $user_id ); if ( $cmp ) : ?>
					<div class="les-ai-compare-stats" data-les-ai-compare-stats>
						<strong><?php esc_html_e( 'Vos comparaisons :', 'logieval-secure' ); ?></strong>
						<?php foreach ( array( 'grading' => __( 'correction', 'logieval-secure' ), 'generation' => __( 'création', 'logieval-secure' ) ) as $purpose => $plabel ) : if ( empty( $cmp[ $purpose ] ) ) { continue; } arsort( $cmp[ $purpose ] ); ?>
							<p><?php echo esc_html( ucfirst( $plabel ) . ' : ' . implode( ' · ', array_map( static fn( $label, $n ) => sprintf( _n( '%1$s gardée %2$d fois', '%1$s gardée %2$d fois', $n, 'logieval-secure' ), $label, $n ), array_keys( $cmp[ $purpose ] ), $cmp[ $purpose ] ) ) ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				</fieldset>
				<?php $usage = LogiEval_Secure_AI::usage_for_month( $user_id ); ?>
				<fieldset class="les-ai-learning les-ai-usage">
					<legend><?php esc_html_e( 'Ma consommation ce mois-ci', 'logieval-secure' ); ?></legend>
					<?php if ( ! $usage ) : ?>
						<p class="description"><?php esc_html_e( 'Aucune demande à l’IA ce mois-ci.', 'logieval-secure' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Fournisseur et modèle', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Demandes', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Jetons envoyés', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Jetons reçus', 'logieval-secure' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $usage as $row ) : ?>
									<tr><td><?php echo esc_html( ( $providers[ $row['provider'] ]['label'] ?? $row['provider'] ) . ' - ' . $row['model'] ); ?></td><td><?php echo esc_html( number_format_i18n( $row['calls'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['in'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['out'] ) ); ?></td></tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description"><?php esc_html_e( 'Les fournisseurs facturent au nombre de jetons (environ 4 caractères par jeton). Multipliez par le prix du modèle indiqué chez le fournisseur pour comparer les coûts.', 'logieval-secure' ); ?></p>
					<?php endif; ?>
				</fieldset>
				</div>
				<div class="les-settings-pane" data-les-settings-pane="correction">
				<fieldset class="les-ai-learning">
					<legend><?php esc_html_e( 'Fiabilité de la pré-correction', 'logieval-secure' ); ?></legend>
					<label class="les-checkbox-line"><input type="checkbox" name="ai_double_check" value="1" <?php checked( self::ai_double_check_enabled( get_current_user_id() ) ); ?>> <?php esc_html_e( 'Double vérification : un second passage de l’IA contrôle chaque point accordé et retire ceux qui ne correspondent pas à la réponse attendue (plus fiable, environ deux fois le coût).', 'logieval-secure' ); ?></label>
				</fieldset>
				<?php self::render_ai_prompts_panel( 'grading' ); ?>
				<?php $address = self::ai_student_address( get_current_user_id() ); ?>
				<fieldset class="les-ai-learning">
					<legend><?php esc_html_e( 'Commentaires pour les élèves', 'logieval-secure' ); ?></legend>
					<p class="description"><?php esc_html_e( 'L’IA rédige des commentaires simples et bienveillants, adressés directement à l’élève : ce qui est réussi, ce qui manque, un conseil.', 'logieval-secure' ); ?></p>
					<label class="les-checkbox-line"><input type="radio" name="ai_address" value="tu" <?php checked( $address, 'tu' ); ?>> <?php esc_html_e( 'Tutoyer les élèves (« Tu as bien vu… »)', 'logieval-secure' ); ?></label>
					<label class="les-checkbox-line"><input type="radio" name="ai_address" value="vous" <?php checked( $address, 'vous' ); ?>> <?php esc_html_e( 'Vouvoyer les élèves (« Vous avez bien vu… »)', 'logieval-secure' ); ?></label>
				</fieldset>
				<?php $learning = self::ai_learning_stats( get_current_user_id() ); ?>
				<fieldset class="les-ai-learning">
					<legend><?php esc_html_e( 'Apprentissage à partir de mes corrections', 'logieval-secure' ); ?></legend>
					<label class="les-checkbox-line">
						<input type="checkbox" name="ai_learning" value="1" <?php checked( self::ai_learning_enabled( get_current_user_id() ) ); ?>>
						<?php esc_html_e( 'L’IA apprend de mes corrections : quand j’enregistre des points différents (ou identiques) de sa proposition, elle s’en sert pour ses prochaines propositions', 'logieval-secure' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Pour une question déjà corrigée, l’IA reçoit quelques réponses que vous avez notées (sans nom d’élève) avec vos points, pour noter comme vous. Elle tient aussi compte de votre tendance générale (plus ou moins sévère que ses propositions).', 'logieval-secure' ); ?></p>
					<p class="les-ai-learning-stats" data-les-ai-learning-stats>
						<?php
						if ( $learning['count'] ) {
							echo esc_html( sprintf( __( '%1$d correction(s) mémorisée(s) sur %2$d question(s). Vos points s’écartent en moyenne de %3$s des propositions de l’IA (%4$s).', 'logieval-secure' ), $learning['count'], $learning['questions'], self::format_points( $learning['avg_abs'] ) . ' pt', $learning['bias_label'] ) );
						} else {
							esc_html_e( 'Aucune correction mémorisée pour le moment : enregistrez une correction pré-corrigée par l’IA pour commencer.', 'logieval-secure' );
						}
						?>
					</p>
					<?php if ( $learning['count'] ) : ?>
						<label class="les-checkbox-line"><input type="checkbox" name="ai_learning_reset" value="1"> <?php esc_html_e( 'Effacer tout ce que l’IA a appris de mes corrections (recommencer à zéro)', 'logieval-secure' ); ?></label>
					<?php endif; ?>
				</fieldset>
				</div>
				<div class="les-settings-pane" data-les-settings-pane="creation">
					<?php self::render_ai_prompts_panel( 'generation' ); ?>
				</div>
				<p class="description"><?php esc_html_e( 'La pré-correction envoie au fournisseur uniquement la question, le contexte, la réponse attendue, la théorie du cours Sensei liée, les points possibles et la réponse de l’élève (sans son nom). Si l’apprentissage est activé, elle ajoute quelques réponses à la même question déjà notées par vous, avec vos points et vos commentaires (toujours sans nom).', 'logieval-secure' ); ?></p>
				<p class="les-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer mes réglages IA', 'logieval-secure' ); ?></button>
					<button type="submit" class="button les-button-secondary" name="test_ai" value="1"><?php esc_html_e( 'Enregistrer et tester la connexion', 'logieval-secure' ); ?></button>
				</p>
			</form>
		</section>
		<?php
	}

	/** Choix du fournisseur d'IA au moment de corriger ou de créer des questions (fournisseurs connectés). */
	private static function render_ai_provider_choice( string $purpose, bool $compact = false ): void {
		$user_id    = get_current_user_id();
		$configured = LogiEval_Secure_AI::configured_providers( $user_id );
		if ( ! $configured || ( $compact && count( $configured ) < 2 ) ) {
			return;
		}
		$default = LogiEval_Secure_AI::resolve_provider( $user_id, '', $purpose );
		?>
		<label class="les-ai-provider-choice<?php echo $compact ? ' is-compact' : ''; ?>">
			<span><?php echo $compact ? esc_html__( 'IA :', 'logieval-secure' ) : esc_html__( 'IA utilisée :', 'logieval-secure' ); ?></span>
			<select data-les-ai-provider-choice="<?php echo esc_attr( $purpose ); ?>" data-default="<?php echo esc_attr( $default ); ?>">
				<?php foreach ( $configured as $key ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $default, $key ); ?>><?php echo esc_html( LogiEval_Secure_AI::model_label( $user_id, $key ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! $compact && count( $configured ) < 2 ) : ?>
				<a class="les-ai-provider-add" href="<?php echo esc_url( self::app_url( 'settings' ) ); ?>"><?php esc_html_e( '+ connecter une autre IA', 'logieval-secure' ); ?></a>
			<?php endif; ?>
		</label>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Sévérité de la pré-correction IA (1 = très bienveillante … 5 = très exigeante)
	 * ------------------------------------------------------------------- */

	public static function ai_strictness_levels(): array {
		return array(
			1 => array(
				'short'  => __( 'Valorise toute idée juste, points partiels généreux, imprécisions ignorées.', 'logieval-secure' ),
				'label'  => __( 'Très bienveillante', 'logieval-secure' ),
				'prompt' => "Niveau d'exigence 1/5 (très bienveillant) : valorise toute idée juste, même incomplète ou mal formulée. Accorde généreusement des points partiels dès qu'un élément pertinent apparaît. Ignore les imprécisions de vocabulaire. En cas de doute, choisis la note la plus favorable.",
			),
			2 => array(
				'short'  => __( 'Sens globalement correct suffisant, indulgente sur la formulation.', 'logieval-secure' ),
				'label'  => __( 'Bienveillante', 'logieval-secure' ),
				'prompt' => "Niveau d'exigence 2/5 (bienveillant) : accorde les points quand le sens est globalement correct, sois indulgent sur la formulation et le vocabulaire, et accorde des points partiels pour les réponses incomplètes mais pertinentes. En cas de doute, penche vers la note favorable.",
			),
			3 => array(
				'short'  => __( 'Applique le barème de façon juste et standard (réglage par défaut).', 'logieval-secure' ),
				'label'  => __( 'Équilibrée', 'logieval-secure' ),
				'prompt' => "Niveau d'exigence 3/5 (équilibré) : applique le barème de façon juste et standard, comme un enseignant expérimenté.",
			),
			4 => array(
				'short'  => __( 'Réponse complète et vocabulaire technique attendus, imprécisions pénalisées.', 'logieval-secure' ),
				'label'  => __( 'Exigeante', 'logieval-secure' ),
				'prompt' => "Niveau d'exigence 4/5 (exigeant) : attends une réponse complète, précise et utilisant le vocabulaire technique du cours. N'accorde des points partiels que si un élément essentiel est clairement présent. Les imprécisions et les oublis coûtent des points. En cas de doute, ne donne pas les points et mets needs_review à true.",
			),
			5 => array(
				'short'  => __( 'Niveau examen final : tous les éléments exacts, aucune approximation.', 'logieval-secure' ),
				'label'  => __( 'Très exigeante', 'logieval-secure' ),
				'prompt' => "Niveau d'exigence 5/5 (très exigeant, niveau examen final) : tous les éléments attendus doivent être présents, exacts et formulés avec le vocabulaire du cours. Aucune approximation n'est acceptée ; un élément manquant ou flou ne rapporte pas ses points. Les points partiels sont rares. En cas de doute, n'accorde pas les points et mets needs_review à true.",
			),
		);
	}

	public static function evaluation_ai_strictness( ?array $evaluation ): int {
		$level = (int) ( $evaluation['ai_strictness'] ?? 3 );
		return $level >= 1 && $level <= 5 ? $level : 3;
	}

	/** Nombre de réponses déjà pré-corrigées par l'IA dans cette évaluation (copies non annulées). */
	private static function evaluation_ai_graded_count( int $evaluation_id ): int {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT an.attempt_id) FROM {$t['answers']} an INNER JOIN {$t['attempts']} a ON a.id=an.attempt_id WHERE an.evaluation_id=%d AND an.ai_graded_at IS NOT NULL AND a.status <> 'security_terminated'", $evaluation_id ) );
	}

	private static function render_ai_strictness_badge( array $evaluation ): void {
		$level = self::evaluation_ai_strictness( $evaluation );
		?>
		<span class="les-ai-level-badge les-ai-level-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( sprintf( __( 'Sévérité IA : %1$d/5 – %2$s (identique pour tous les élèves)', 'logieval-secure' ), $level, self::ai_strictness_levels()[ $level ]['label'] ) ); ?></span>
		<?php
	}

	private static function render_ai_strictness_slider( array $evaluation ): void {
		$level   = self::evaluation_ai_strictness( $evaluation );
		$levels  = self::ai_strictness_levels();
		$graded  = self::evaluation_ai_graded_count( (int) $evaluation['id'] );
		$locked  = $graded > 0;
		?>
		<fieldset class="les-ai-strictness<?php echo $locked ? ' is-locked' : ''; ?>" data-les-ai-strictness data-evaluation-id="<?php echo esc_attr( $evaluation['id'] ); ?>" data-locked="<?php echo $locked ? '1' : '0'; ?>">
			<legend><?php esc_html_e( 'Sévérité de la pré-correction IA', 'logieval-secure' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Un seul niveau par évaluation : il s’applique à tous les élèves pour qu’ils soient évalués de manière équitable.', 'logieval-secure' ); ?></p>
			<div class="les-ai-levels" role="radiogroup">
				<?php foreach ( $levels as $value => $info ) : ?>
					<label class="les-ai-level les-ai-level-<?php echo esc_attr( $value ); ?>">
						<input type="radio" name="les_ai_strictness_<?php echo esc_attr( $evaluation['id'] ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $level, $value ); ?> <?php disabled( $locked ); ?>>
						<span class="les-ai-level-num"><?php echo esc_html( $value ); ?>/5</span>
						<strong><?php echo esc_html( $info['label'] ); ?></strong>
						<small><?php echo esc_html( $info['short'] ); ?></small>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="les-ai-lock-note" data-les-ai-lock-note <?php echo $locked ? '' : 'hidden'; ?>>
				🔒 <span data-les-ai-lock-text><?php echo esc_html( sprintf( _n( 'Niveau verrouillé : %1$d copie a déjà été pré-corrigée au niveau « %2$s ».', 'Niveau verrouillé : %1$d copies ont déjà été pré-corrigées au niveau « %2$s ».', $graded, 'logieval-secure' ), $graded, $levels[ $level ]['label'] ) ); ?></span>
				<button type="button" class="button les-button-secondary" data-les-ai-strictness-change><?php esc_html_e( 'Changer de niveau…', 'logieval-secure' ); ?></button>
			</p>
			<span class="les-ai-strictness-status" data-les-ai-strictness-status aria-live="polite"></span>
		</fieldset>
		<?php
	}

	public static function ajax_set_ai_strictness(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$evaluation = self::get_evaluation( absint( $_POST['evaluation_id'] ?? 0 ) );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_ai' ) || ! self::can_manage_evaluation( $evaluation ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
		}
		$level = max( 1, min( 5, absint( $_POST['level'] ?? 3 ) ) );
		global $wpdb;
		$t       = LogiEval_Secure_Database::tables();
		$graded  = self::evaluation_ai_graded_count( (int) $evaluation['id'] );
		$cleared = 0;
		if ( $graded > 0 && $level !== self::evaluation_ai_strictness( $evaluation ) ) {
			if ( empty( $_POST['reset'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Le niveau est verrouillé car des copies ont déjà été pré-corrigées. Utilisez « Changer de niveau… ».', 'logieval-secure' ) ), 409 );
			}
			// Équité : les propositions IA faites à l'ancien niveau sont effacées (copies non terminées).
			// Les points saisis par l'enseignant ne sont jamais modifiés.
			$cleared = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$t['answers']}
					SET ai_points=NULL, ai_comment=NULL, ai_analysis=NULL, ai_confidence=NULL, ai_needs_review=0, ai_model=NULL, ai_graded_at=NULL
					WHERE evaluation_id=%d AND ai_graded_at IS NOT NULL AND attempt_id IN (SELECT id FROM {$t['attempts']} WHERE evaluation_id=%d AND status NOT IN ('graded', 'security_terminated'))",
					(int) $evaluation['id'],
					(int) $evaluation['id']
				)
			);
		}
		$wpdb->update( $t['evaluations'], array( 'ai_strictness' => $level ), array( 'id' => (int) $evaluation['id'] ) );
		wp_send_json_success(
			array(
				'level'   => $level,
				'label'   => self::ai_strictness_levels()[ $level ]['label'],
				'cleared' => $cleared,
				'graded'  => self::evaluation_ai_graded_count( (int) $evaluation['id'] ),
			)
		);
	}

	private static function render_ai_analysis_box( array $answer ): void {
		$analysis = trim( (string) ( $answer['ai_analysis'] ?? '' ) );
		$comment  = trim( (string) ( $answer['ai_comment'] ?? '' ) );
		if ( '' === $analysis && '' === $comment && ! is_numeric( $answer['ai_points'] ?? null ) ) {
			echo '<div class="les-ai-analysis" data-les-ai-analysis hidden></div>';
			return;
		}

		$confidence = is_numeric( $answer['ai_confidence'] ?? null ) ? round( (float) $answer['ai_confidence'] * 100 ) : null;
		?>
		<div class="les-ai-analysis" data-les-ai-analysis>
			<strong><?php esc_html_e( 'Analyse IA enregistrée', 'logieval-secure' ); ?></strong>
			<p>
				<?php echo esc_html( sprintf( __( 'Proposition : %s point(s)', 'logieval-secure' ), is_numeric( $answer['ai_points'] ?? null ) ? self::format_points( (float) $answer['ai_points'] ) : '-' ) ); ?>
				<?php if ( null !== $confidence ) : ?>
					<?php echo esc_html( sprintf( __( ' - confiance %d%%', 'logieval-secure' ), $confidence ) ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $answer['ai_needs_review'] ) ) : ?>
					<?php echo esc_html__( ' - à vérifier', 'logieval-secure' ); ?>
				<?php endif; ?>
			</p>
			<?php if ( '' !== $analysis ) : ?>
				<?php self::render_ai_analysis_groups( $analysis ); ?>
			<?php endif; ?>
			<?php if ( '' !== $comment ) : ?>
				<p><em><?php echo esc_html( $comment ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Analyse IA découpée en points, les uns sous les autres : une ligne par retour à la ligne, sinon une par phrase
	 * (« Critère 1 … . Critère 2 … » devient deux points).
	 */
	public static function ai_analysis_items( string $analysis ): array {
		$items = array();
		foreach ( preg_split( '/\R+/u', $analysis ) as $line ) {
			$line = trim( preg_replace( '/^\s*(?:[-–•*]|\d+[.)])\s+/u', '', $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^[✓✔◐✗✘→🧠]/u', $line ) ) {
				$items[] = $line; // Nouveau format : une ligne = un point (déjà marqué réussi / en partie / pas réussi).
				continue;
			}
			foreach ( preg_split( '/(?<=[.!?…»])\s+(?=(?:Crit[èe]re\s*\d|[A-ZÀÂÉÈÊÎÔÛÇ«]))/u', $line ) as $sentence ) {
				$sentence = trim( $sentence );
				if ( '' !== $sentence ) {
					$items[] = $sentence;
				}
			}
		}
		return $items;
	}

	/** Double vérification des points accordés (activée par défaut). */
	public static function ai_double_check_enabled( int $user_id ): bool {
		return '0' !== (string) get_user_meta( $user_id, 'logieval_secure_ai_double_check', true );
	}

	/** Forme d'adresse aux élèves dans les commentaires : « tu » (par défaut) ou « vous ». */
	public static function ai_student_address( int $user_id ): string {
		return 'vous' === (string) get_user_meta( $user_id, 'logieval_secure_ai_address', true ) ? 'vous' : 'tu';
	}

	/** Consignes de rédaction du commentaire pour l'élève : simple, concret, humain. */
	private static function ai_comment_instructions( int $user_id ): string {
		$tu = 'tu' === self::ai_student_address( $user_id );
		// Consigne modifiable dans Réglages › Mes consignes pour l'IA.
		return self::ai_prompt(
			'comment_style',
			array(
				'{adresse}' => $tu ? 'en le tutoyant' : 'en le vouvoyant',
				'{exemple}' => $tu ? 'Tu as juste pour les deux pays sans accès à la mer. Par contre, tu n’expliques pas pourquoi le navire porte ce pavillon. C’est ça qui te coûte le deuxième point.' : 'Vous avez juste pour les deux pays sans accès à la mer. Par contre, vous n’expliquez pas pourquoi le navire porte ce pavillon. C’est ce qui vous coûte le deuxième point.',
			),
			$user_id
		) . "\n";
	}

	/** Réponse vide : commentaire « Non répondu. », tout simplement. */
	private static function ai_empty_answer_comment( array $answer = array() ): string {
		return self::empty_answer_comment_text();
	}

	/** Statut de chaque critère d'après l'analyse IA (« Critère 2 partiellement rempli : … ») : numéro => ok | partial | miss. */
	public static function ai_criteria_statuses( string $analysis ): array {
		$out = array();
		foreach ( self::ai_analysis_items( $analysis ) as $item ) {
			$symbol = self::ai_analysis_item_status( $item );
			if ( ! preg_match( '/^crit[èe]re\s*(\d+)\s*([^:]*)/iu', self::ai_analysis_item_text( $item ), $m ) ) {
				continue;
			}
			if ( preg_match( '/^[✓✔◐✗✘]/u', trim( $item ) ) ) {
				$out[ (int) $m[1] ] = $symbol; // Le symbole donné par l'IA fait foi.
				continue;
			}
			$head = mb_strtolower( $m[2] );
			if ( preg_match( '/\b(non|pas|absent|manquant)\b/u', $head ) ) {
				$out[ (int) $m[1] ] = 'miss';
			} elseif ( false !== mb_strpos( $head, 'partiel' ) ) {
				$out[ (int) $m[1] ] = 'partial';
			} elseif ( preg_match( '/(rempli|respecté|présent|validé|atteint|correct)/u', $head ) ) {
				$out[ (int) $m[1] ] = 'ok';
			}
		}
		return $out;
	}

	/** Un point de l'analyse : ok (réussi), partial (en partie), miss (pas réussi / manquant), note (remarque) ou end (conclusion). */
	public static function ai_analysis_item_status( string $item ): string {
		$item = trim( $item );
		if ( preg_match( '/^[✓✔]/u', $item ) ) {
			return 'ok';
		}
		if ( preg_match( '/^◐/u', $item ) ) {
			return 'partial';
		}
		if ( preg_match( '/^[✗✘]/u', $item ) ) {
			return 'miss';
		}
		if ( preg_match( '/^→/u', $item ) ) {
			return 'end';
		}
		// Anciennes analyses (sans symbole) : d'après les mots employés.
		$lower = mb_strtolower( $item );
		if ( preg_match( '/^conclusion\b/u', $lower ) ) {
			return 'end';
		}
		if ( preg_match( "/(non rempli|pas rempli|n['’]est pas (rempli|présent|correct|juste|respecté)|non respecté|manquant|il manque|absent|incorrect|erron|ne (mentionne|cite|donne|dit|explique|précise|justifie|indique|identifie) (pas|rien)|0 point)/u", $lower ) ) {
			return 'miss';
		}
		if ( false !== mb_strpos( $lower, 'partiel' ) ) {
			return 'partial';
		}
		if ( preg_match( '/(rempli|respecté|présent|correct|juste|cohérent|validé|réussi)/u', $lower ) ) {
			return 'ok';
		}
		return 'note';
	}

	public static function ai_analysis_item_text( string $item ): string {
		return trim( preg_replace( '/^[✓✔◐✗✘→]\s*/u', '', trim( $item ) ) );
	}

	public static function ai_analysis_item_class( string $item ): string {
		$status = self::ai_analysis_item_status( $item );
		return in_array( $status, array( 'ok', 'partial', 'miss' ), true ) ? 'is-' . $status : '';
	}

	/** Analyse IA séparée : ce qui est réussi d'un côté, ce qui ne l'est pas (ou en partie) de l'autre, puis les remarques. */
	private static function render_ai_analysis_groups( string $analysis ): void {
		$groups = array( 'ok' => array(), 'miss' => array(), 'note' => array() );
		foreach ( self::ai_analysis_items( $analysis ) as $item ) {
			// 1.35 : « 🧠 Compris par l'IA » en tête, pour vérifier d'un coup d'œil qu'elle a bien lu l'élève.
			if ( 0 === mb_strpos( trim( $item ), '🧠' ) ) {
				echo '<p class="les-ai-understood">' . esc_html( trim( $item ) ) . '</p>';
				continue;
			}
			$status = self::ai_analysis_item_status( $item );
			$text   = self::ai_analysis_item_text( $item );
			if ( '' === $text ) {
				continue;
			}
			if ( 'ok' === $status ) {
				$groups['ok'][] = array( 'is-ok', $text );
			} elseif ( 'miss' === $status || 'partial' === $status ) {
				$groups['miss'][] = array( 'is-' . $status, $text );
			} else {
				$groups['note'][] = array( 'end' === $status ? 'is-end' : '', $text );
			}
		}
		if ( $groups['ok'] || $groups['miss'] ) {
			echo '<div class="les-ai-split">';
			echo '<div class="les-ai-col is-ok"><strong class="les-ai-col-title">✓ ' . esc_html__( 'Réussi', 'logieval-secure' ) . '</strong>';
			echo $groups['ok'] ? self::ai_list_html( $groups['ok'] ) : '<p class="les-ai-col-empty">' . esc_html__( 'Rien de réussi selon l’IA.', 'logieval-secure' ) . '</p>';
			echo '</div><div class="les-ai-col is-miss"><strong class="les-ai-col-title">✗ ' . esc_html__( 'Pas réussi / à revoir', 'logieval-secure' ) . '</strong>';
			echo $groups['miss'] ? self::ai_list_html( $groups['miss'] ) : '<p class="les-ai-col-empty">' . esc_html__( 'Rien à revoir selon l’IA.', 'logieval-secure' ) . '</p>';
			echo '</div></div>';
		}
		if ( $groups['note'] ) {
			echo self::ai_list_html( $groups['note'], 'les-ai-notes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans ai_list_html.
		}
	}

	private static function ai_list_html( array $rows, string $class = '' ): string {
		$html = '<ul class="les-ai-analysis-list' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '">';
		foreach ( $rows as $row ) {
			$html .= '<li class="' . esc_attr( $row[0] ) . '">' . esc_html( $row[1] ) . '</li>';
		}
		return $html . '</ul>';
	}

	public static function handle_save_ai_settings(): void {
		self::require_teacher_post();
		$user_id = get_current_user_id();
		$input   = (array) wp_unslash( $_POST['ai'] ?? array() );
		LogiEval_Secure_AI::save_user_settings( $user_id, $input );
		update_user_meta( $user_id, 'logieval_secure_ai_learning', empty( $_POST['ai_learning'] ) ? '0' : '1' );
		self::save_ai_prompts_from_post( $user_id );
		update_user_meta( $user_id, 'logieval_secure_ai_double_check', empty( $_POST['ai_double_check'] ) ? '0' : '1' );
		update_user_meta( $user_id, 'logieval_secure_ai_address', 'vous' === sanitize_key( wp_unslash( $_POST['ai_address'] ?? '' ) ) ? 'vous' : 'tu' );
		if ( ! empty( $_POST['ai_learning_reset'] ) ) {
			self::ai_learning_reset( $user_id );
		}

		if ( ! empty( $_POST['test_ai'] ) ) {
			// Test de chaque fournisseur connecté.
			$lines = array();
			$all_ok = true;
			foreach ( LogiEval_Secure_AI::configured_providers( $user_id ) as $provider ) {
				$result = LogiEval_Secure_AI::complete( 'Réponds uniquement avec ce JSON exact : {"ok": true}', $user_id, array( 'provider' => $provider, 'max_tokens' => 200 ) );
				if ( is_wp_error( $result ) ) {
					$all_ok  = false;
					$lines[] = sprintf( __( '✗ %1$s : %2$s', 'logieval-secure' ), LogiEval_Secure_AI::model_label( $user_id, $provider ), $result->get_error_message() );
				} else {
					$lines[] = sprintf( __( '✓ %s : connexion réussie.', 'logieval-secure' ), $result['model'] );
				}
			}
			$test = $lines ? array( 'ok' => $all_ok, 'message' => implode( ' — ', $lines ) ) : array( 'ok' => false, 'message' => __( 'Aucun fournisseur connecté : enregistrez au moins une clé API.', 'logieval-secure' ) );
			set_transient( 'logieval_ai_test_' . $user_id, $test, 5 * MINUTE_IN_SECONDS );
		}

		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-settings&settings_saved=1' ), array( 'logiexam_view' => 'settings', 'settings_saved' => 1 ) ) );
		exit;
	}

		public static function ajax_ai_grade_answer(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_ai' ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
			}
			if ( ! self::ai_grading_is_enabled() ) {
				wp_send_json_error( array( 'message' => __( 'La pré-correction IA n’est pas activée dans les réglages.', 'logieval-secure' ) ), 400 );
			}

			$attempt_id  = absint( $_POST['attempt_id'] ?? 0 );
			$question_id = absint( $_POST['question_id'] ?? 0 );
			$attempt     = self::get_attempt( $attempt_id );
			if ( ! self::current_user_can_manage_attempt( $attempt ) || ! self::question_belongs_to_evaluation( $question_id, (int) $attempt['evaluation_id'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Copie ou question introuvable.', 'logieval-secure' ) ), 404 );
			}
			if ( self::attempt_is_cheating_cancelled( $attempt ) ) {
				wp_send_json_error( array( 'message' => __( 'Copie annulée pour tricherie : pas de correction.', 'logieval-secure' ) ), 400 );
			}

			$answer = self::answer_row_by_question( self::get_answers_for_attempt( $attempt_id ), $question_id );
			if ( ! $answer ) {
				wp_send_json_error( array( 'message' => __( 'Réponse introuvable.', 'logieval-secure' ) ), 404 );
			}

			$max_points = max( 0, (float) ( $answer['points'] ?? 0 ) );
			if ( ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
				$result = array(
					'points'       => self::format_points( 0 ),
					'comment'      => self::ai_empty_answer_comment( $answer ),
					'analysis'     => __( 'Aucun élément de réponse n’a été fourni par l’élève. La proposition IA est donc 0 point.', 'logieval-secure' ),
					'confidence'   => 1,
					'needs_review' => false,
				);
				self::save_ai_grade_result( $attempt, $question_id, $result );
				wp_send_json_success( $result );
			}

			$result = self::request_ai_grade_answer( $answer, $max_points );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
			}
			self::save_ai_grade_result( $attempt, $question_id, $result );
			wp_send_json_success( $result );
		}

		/**
		 * LogiOne 1.31.2 : nouveau commentaire pour l'élève d'après les points et les critères choisis par l'enseignant.
		 * Les points ne sont ni recalculés ni enregistrés : seul le texte du commentaire est proposé.
		 */
		public static function ajax_ai_regenerate_comment(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_ai' ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
			}
			if ( ! self::ai_grading_is_enabled() ) {
				wp_send_json_error( array( 'message' => __( 'La pré-correction IA n’est pas activée dans les réglages.', 'logieval-secure' ) ), 400 );
			}
			$attempt_id  = absint( $_POST['attempt_id'] ?? 0 );
			$question_id = absint( $_POST['question_id'] ?? 0 );
			$attempt     = self::get_attempt( $attempt_id );
			if ( ! self::current_user_can_manage_attempt( $attempt ) || ! self::question_belongs_to_evaluation( $question_id, (int) $attempt['evaluation_id'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Copie ou question introuvable.', 'logieval-secure' ) ), 404 );
			}
			$answer = self::answer_row_by_question( self::get_answers_for_attempt( $attempt_id ), $question_id );
			if ( ! $answer ) {
				wp_send_json_error( array( 'message' => __( 'Réponse introuvable.', 'logieval-secure' ) ), 404 );
			}
			if ( ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
				wp_send_json_success( array( 'comment' => self::empty_answer_comment_text() ) );
			}
			$max_points = max( 0, (float) ( $answer['points'] ?? 0 ) );
			$raw_points = trim( (string) wp_unslash( $_POST['points'] ?? '' ) );
			if ( '' === $raw_points || ! is_numeric( str_replace( ',', '.', $raw_points ) ) ) {
				wp_send_json_error( array( 'message' => __( 'Indiquez d’abord vos points pour cette question.', 'logieval-secure' ) ), 400 );
			}
			$points   = max( 0, min( $max_points, (float) str_replace( ',', '.', $raw_points ) ) );
			$criteria = self::scored_criteria( $answer['grading_criteria'] ?? '' )['criteria'];
			$checked  = array_map( 'intval', (array) json_decode( (string) wp_unslash( $_POST['checked'] ?? '[]' ), true ) );
			$verdicts = array();
			if ( $max_points > 0 && $points >= $max_points - 0.001 ) {
				$checked = array_keys( $criteria ); // tous les points : l'enseignant accepte la réponse, tous les critères sont remplis
			} elseif ( ! $checked && $points > 0 ) {
				$criteria = array(); // critères non cochés mais des points : on ne sait pas lesquels sont remplis, l'IA s'appuie sur les points
			}
			foreach ( $criteria as $i => $c ) {
				$ok         = in_array( $i, $checked, true );
				$verdicts[] = array( 'label' => $c['label'], 'status' => $ok ? 'ok' : 'miss', 'hint' => '', 'points' => (float) $c['points'] );
			}
			$draft   = trim( sanitize_textarea_field( wp_unslash( $_POST['current'] ?? '' ) ) );
			$comment = self::ai_comment_for_points( $answer, $points, $max_points, $verdicts, $draft, true );
			if ( is_wp_error( $comment ) ) {
				wp_send_json_error( array( 'message' => $comment->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'comment' => sanitize_textarea_field( $comment ), 'points' => self::format_points( $points ) ) );
		}

		/* ── LogiOne 1.32 : comparer deux IA ─────────────────────────────────────────────── */

		/** Préférences relevées lors des comparaisons (quelle IA l'enseignant a gardée). */
		public static function ai_compare_stats( int $user_id ): array {
			$s = get_user_meta( $user_id, 'logieval_secure_ai_compare_stats', true );
			return is_array( $s ) ? $s : array();
		}

		private static function ai_compare_record( string $purpose, string $kept, string $other = '' ): void {
			$user_id = get_current_user_id();
			$stats   = self::ai_compare_stats( $user_id );
			$label   = LogiEval_Secure_AI::model_label( $user_id, $kept );
			$stats[ $purpose ][ $label ] = (int) ( $stats[ $purpose ][ $label ] ?? 0 ) + 1;
			if ( '' !== $other ) {
				$other_label = LogiEval_Secure_AI::model_label( $user_id, $other );
				$stats[ $purpose ][ $other_label ] = (int) ( $stats[ $purpose ][ $other_label ] ?? 0 );
			}
			update_user_meta( $user_id, 'logieval_secure_ai_compare_stats', $stats );
		}

		/** Même réponse corrigée par deux IA, sans rien enregistrer : l'enseignant garde ensuite l'une des deux. */
		public static function ajax_ai_grade_compare(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_ai' ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
			}
			if ( ! self::ai_grading_is_enabled() ) {
				wp_send_json_error( array( 'message' => __( 'La pré-correction IA n’est pas activée dans les réglages.', 'logieval-secure' ) ), 400 );
			}
			$attempt_id  = absint( $_POST['attempt_id'] ?? 0 );
			$question_id = absint( $_POST['question_id'] ?? 0 );
			$attempt     = self::get_attempt( $attempt_id );
			if ( ! self::current_user_can_manage_attempt( $attempt ) || ! self::question_belongs_to_evaluation( $question_id, (int) $attempt['evaluation_id'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Copie ou question introuvable.', 'logieval-secure' ) ), 404 );
			}
			$answer = self::answer_row_by_question( self::get_answers_for_attempt( $attempt_id ), $question_id );
			if ( ! $answer || ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
				wp_send_json_error( array( 'message' => __( 'Réponse vide : rien à comparer.', 'logieval-secure' ) ), 400 );
			}
			$user_id = get_current_user_id();
			$pair    = array( 'a' => sanitize_key( wp_unslash( $_POST['provider_a'] ?? '' ) ), 'b' => sanitize_key( wp_unslash( $_POST['provider_b'] ?? '' ) ) );
			foreach ( $pair as $p ) {
				if ( ! LogiEval_Secure_AI::provider_is_configured( $user_id, $p ) ) {
					wp_send_json_error( array( 'message' => __( 'Choisissez deux IA connectées dans vos réglages.', 'logieval-secure' ) ), 400 );
				}
			}
			if ( $pair['a'] === $pair['b'] ) {
				wp_send_json_error( array( 'message' => __( 'Choisissez deux IA différentes.', 'logieval-secure' ) ), 400 );
			}
			$max_points = max( 0, (float) ( $answer['points'] ?? 0 ) );
			$out        = array();
			foreach ( $pair as $slot => $provider ) {
				$_POST['provider'] = $provider; // utilisé par toutes les étapes (correction, vérification, commentaire)
				$start  = microtime( true );
				$result = self::request_ai_grade_answer( $answer, $max_points );
				$secs   = round( microtime( true ) - $start, 1 );
				if ( is_wp_error( $result ) ) {
					$out[ $slot ] = array( 'provider' => $provider, 'label' => LogiEval_Secure_AI::model_label( $user_id, $provider ), 'error' => $result->get_error_message(), 'seconds' => $secs );
					continue;
				}
				$result['provider'] = $provider;
				$result['label']    = LogiEval_Secure_AI::model_label( $user_id, $provider );
				$result['seconds']  = $secs;
				$out[ $slot ]       = $result;
			}
			set_transient( 'les_cmp_' . $user_id . '_' . $attempt_id . '_' . $question_id, $out, HOUR_IN_SECONDS );
			wp_send_json_success( $out );
		}

		/** L'enseignant garde la correction A ou B : elle devient la proposition IA de la question. */
		public static function ajax_ai_grade_keep(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_ai' ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
			}
			$attempt_id  = absint( $_POST['attempt_id'] ?? 0 );
			$question_id = absint( $_POST['question_id'] ?? 0 );
			$attempt     = self::get_attempt( $attempt_id );
			if ( ! self::current_user_can_manage_attempt( $attempt ) ) {
				wp_send_json_error( array( 'message' => __( 'Copie introuvable.', 'logieval-secure' ) ), 404 );
			}
			$slot  = 'b' === sanitize_key( wp_unslash( $_POST['choice'] ?? '' ) ) ? 'b' : 'a';
			$saved = get_transient( 'les_cmp_' . get_current_user_id() . '_' . $attempt_id . '_' . $question_id );
			if ( ! is_array( $saved ) || empty( $saved[ $slot ] ) || ! empty( $saved[ $slot ]['error'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Comparaison expirée : relancez-la.', 'logieval-secure' ) ), 410 );
			}
			$result = $saved[ $slot ];
			self::save_ai_grade_result( $attempt, $question_id, $result );
			self::ai_compare_record( 'grading', (string) $result['provider'], (string) ( $saved[ 'a' === $slot ? 'b' : 'a' ]['provider'] ?? '' ) );
			wp_send_json_success( $result );
		}

		/** Création : l'enseignant a gardé les questions de l'une des deux IA. */
		public static function ajax_ai_compare_vote(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
				wp_send_json_error( array(), 403 );
			}
			$kept  = sanitize_key( wp_unslash( $_POST['kept'] ?? '' ) );
			$other = sanitize_key( wp_unslash( $_POST['other'] ?? '' ) );
			if ( LogiEval_Secure_AI::provider_is_configured( get_current_user_id(), $kept ) ) {
				self::ai_compare_record( 'generation', $kept, $other );
			}
			wp_send_json_success();
		}

		public static function ajax_ai_grade_bulk(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_ai' ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
			}
			if ( ! self::ai_grading_is_enabled() ) {
				wp_send_json_error( array( 'message' => __( 'La pré-correction IA n’est pas activée dans les réglages.', 'logieval-secure' ) ), 400 );
			}

			$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
			$question_id   = absint( $_POST['question_id'] ?? 0 );
			$only_attempt  = absint( $_POST['attempt_id'] ?? 0 ); // Pré-correction d'un seul élève.
			$evaluation    = self::get_evaluation( $evaluation_id );
			if ( ! self::can_manage_evaluation( $evaluation ) ) {
				wp_send_json_error( array( 'message' => __( 'Évaluation introuvable.', 'logieval-secure' ) ), 404 );
			}
			if ( $question_id && ! self::question_belongs_to_evaluation( $question_id, $evaluation_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Question introuvable dans cette évaluation.', 'logieval-secure' ) ), 404 );
			}

			// Pré-correction découpée (LogiOne 1.24.3) : le navigateur demande d'abord la liste des copies (« plan »),
			// puis envoie une copie à la fois. Chaque demande s'arrête après ~20 s (« remaining ») pour ne jamais
			// dépasser la durée maximale autorisée par l'hébergement ; le navigateur la relance aussitôt.
			$plan         = ! empty( $_POST['plan'] );
			$only_missing = ! empty( $_POST['only_missing'] );
			$since        = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) wp_unslash( $_POST['since'] ?? '' ) ) ? (string) wp_unslash( $_POST['since'] ) : '';
			$skip_qids    = array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_POST['skip_questions'] ?? '' ) ) ) ); // Réponses en erreur dans cette série : pas de nouvel essai.
			$deadline     = microtime( true ) + (float) apply_filters( 'logieval_secure_ai_bulk_budget', 20 );
			$remaining    = false;
			$skipped      = 0;
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$results = array();
			$failed  = array();
			$copies  = array();
			foreach ( self::get_attempts( $evaluation_id ) as $attempt ) {
				if ( $only_attempt && (int) $attempt['id'] !== $only_attempt ) {
					continue;
				}
				if ( self::attempt_correction_is_finished( $attempt, self::get_result_by_attempt( (int) $attempt['id'] ) ) ) {
					continue; // Ne jamais toucher une correction terminée.
				}
				if ( in_array( (string) ( $attempt['status'] ?? '' ), array( 'not_started', 'security_paused', 'security_terminated' ), true ) ) {
					continue;
				}
				if ( $plan ) {
					$todo = 0;
					$done = 0;
					foreach ( self::get_answers_for_attempt( (int) $attempt['id'] ) as $answer ) {
						if ( $question_id && (int) $answer['question_id'] !== $question_id ) {
							continue;
						}
						if ( ! empty( $answer['ai_graded_at'] ) ) {
							$done++;
						} else {
							$todo++;
						}
					}
					$copies[] = array(
						'attempt_id' => (int) $attempt['id'],
						'name'       => self::student_full_name( (int) $attempt['user_id'] ),
						'todo'       => $todo,
						'done'       => $done,
					);
					continue;
				}
				foreach ( self::get_answers_for_attempt( (int) $attempt['id'] ) as $answer ) {
					if ( $question_id && (int) $answer['question_id'] !== $question_id ) {
						continue;
					}
					if ( in_array( (int) $answer['question_id'], $skip_qids, true ) ) {
						continue;
					}
					$graded_at = (string) ( $answer['ai_graded_at'] ?? '' );
					if ( '' !== $graded_at && ( $only_missing || ( '' !== $since && $graded_at >= $since ) ) ) {
						$skipped++; // Déjà pré-corrigée (ou déjà refaite pendant cette série).
						continue;
					}
					if ( microtime( true ) > $deadline ) {
						$remaining = true; // Le navigateur relance la même copie pour continuer.
						break 2;
					}
					$grade = self::ai_grade_answer_for_attempt( $attempt, $answer );
					if ( is_wp_error( $grade ) ) {
						$failed[] = array(
							'attempt_id'  => (int) $attempt['id'],
							'question_id' => (int) $answer['question_id'],
							'message'     => $grade->get_error_message(),
						);
						continue;
					}
					$results[] = array_merge(
						array(
							'attempt_id'  => (int) $attempt['id'],
							'question_id' => (int) $answer['question_id'],
						),
						$grade
					);
				}
			}

			if ( $plan ) {
				wp_send_json_success(
					array(
						'copies'     => $copies,
						'started_at' => current_time( 'mysql' ),
					)
				);
			}

			wp_send_json_success(
				array(
					'processed' => count( $results ),
					'failed'    => $failed,
					'results'   => $results,
					'skipped'   => $skipped,
					'remaining' => $remaining,
				)
			);
		}

		/* ---------------------------------------------------------------------
		 * Apprentissage : l'IA s'aligne sur les points que l'enseignant attribue (LogiOne 1.24.5)
		 * ------------------------------------------------------------------- */

		private static function ai_feedback_table(): string {
			global $wpdb;
			$table = class_exists( 'LogiPilot_Install' ) ? LogiPilot_Install::table( 'ai_feedback' ) : $wpdb->prefix . 'logipilot_ai_feedback';
			static $exists = array();
			if ( ! isset( $exists[ $table ] ) ) {
				$exists[ $table ] = ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
			}
			return $exists[ $table ] ? $table : '';
		}

		public static function ai_learning_enabled( int $user_id ): bool {
			return '0' !== (string) get_user_meta( $user_id, 'logieval_secure_ai_learning', true );
		}

		/** Mémorise la proposition IA et les points enregistrés par l'enseignant pour une réponse (appelé à chaque enregistrement). */
		public static function record_ai_feedback( int $attempt_id, int $question_id, $teacher_points, float $max_points, string $comment ): void {
			global $wpdb;
			$table = self::ai_feedback_table();
			if ( '' === $table || null === $teacher_points || ! is_numeric( $teacher_points ) ) {
				return;
			}
			$t   = LogiEval_Secure_Database::tables();
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT evaluation_id, answer, ai_points FROM {$t['answers']} WHERE attempt_id=%d AND question_id=%d", $attempt_id, $question_id ), ARRAY_A );
			if ( ! $row || ! is_numeric( $row['ai_points'] ) || ! self::answer_is_complete( $row['answer'] ?? '' ) ) {
				return; // Pas de proposition IA (ou réponse vide) : rien à apprendre.
			}
			$text = wp_strip_all_tags( self::readable_answer( (string) $row['answer'] ) );
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (teacher_id, evaluation_id, question_id, attempt_id, answer_text, ai_points, teacher_points, max_points, teacher_comment, updated_at)
					VALUES (%d, %d, %d, %d, %s, %f, %f, %f, %s, %s)
					ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id), answer_text=VALUES(answer_text), ai_points=VALUES(ai_points), teacher_points=VALUES(teacher_points), max_points=VALUES(max_points), teacher_comment=VALUES(teacher_comment), updated_at=VALUES(updated_at)",
					get_current_user_id(),
					(int) $row['evaluation_id'],
					$question_id,
					$attempt_id,
					mb_substr( $text, 0, 2000 ),
					(float) $row['ai_points'],
					(float) $teacher_points,
					$max_points,
					mb_substr( $comment, 0, 1000 ),
					current_time( 'mysql' )
				)
			);
		}

		/** Réponses à la même question déjà notées par l'enseignant (les plus récentes, sans nom d'élève). */
		public static function ai_learning_examples( int $question_id, int $exclude_attempt, int $user_id, int $limit = 6 ): array {
			global $wpdb;
			$table = self::ai_feedback_table();
			if ( '' === $table || ! $question_id ) {
				return array();
			}
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT answer_text, ai_points, teacher_points, max_points, teacher_comment FROM {$table} WHERE teacher_id=%d AND question_id=%d AND attempt_id<>%d ORDER BY updated_at DESC, id DESC LIMIT %d", $user_id, $question_id, $exclude_attempt, $limit ), ARRAY_A );
			return array_map(
				static fn( $r ) => array_filter(
					array(
						'reponse_eleve'          => mb_substr( (string) $r['answer_text'], 0, 600 ),
						'points_enseignant'      => (float) $r['teacher_points'],
						'points_proposes_ia'     => (float) $r['ai_points'],
						'points_max'             => (float) $r['max_points'],
						'commentaire_enseignant' => (string) $r['teacher_comment'],
					),
					static fn( $v ) => '' !== $v
				),
				(array) $rows
			);
		}

		/** Tendance générale : écart moyen (points enseignant - points IA) / points max sur les 100 dernières corrections. */
		public static function ai_learning_bias( int $user_id ): array {
			global $wpdb;
			$table = self::ai_feedback_table();
			if ( '' === $table ) {
				return array( 'count' => 0, 'ratio' => 0.0 );
			}
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT ai_points, teacher_points, max_points FROM {$table} WHERE teacher_id=%d AND max_points>0 ORDER BY updated_at DESC, id DESC LIMIT 100", $user_id ), ARRAY_A );
			$sum   = 0.0;
			foreach ( (array) $rows as $r ) {
				$sum += ( (float) $r['teacher_points'] - (float) $r['ai_points'] ) / (float) $r['max_points'];
			}
			$count = count( (array) $rows );
			return array( 'count' => $count, 'ratio' => $count ? $sum / $count : 0.0 );
		}

		public static function ai_learning_stats( int $user_id ): array {
			global $wpdb;
			$table = self::ai_feedback_table();
			$stats = array( 'count' => 0, 'questions' => 0, 'avg_abs' => 0.0, 'bias_label' => '' );
			if ( '' === $table ) {
				return $stats;
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COUNT(DISTINCT question_id) AS q, AVG(ABS(teacher_points-ai_points)) AS a FROM {$table} WHERE teacher_id=%d", $user_id ), ARRAY_A );
			$bias = self::ai_learning_bias( $user_id );
			$stats['count']     = (int) ( $row['n'] ?? 0 );
			$stats['questions'] = (int) ( $row['q'] ?? 0 );
			$stats['avg_abs']   = (float) ( $row['a'] ?? 0 );
			$pct                = (int) round( abs( $bias['ratio'] ) * 100 );
			$stats['bias_label'] = $pct < 5 ? __( 'vous êtes en moyenne d’accord avec l’IA', 'logieval-secure' ) : ( $bias['ratio'] > 0 ? sprintf( __( 'vous donnez en moyenne %d %% de points en plus', 'logieval-secure' ), $pct ) : sprintf( __( 'vous donnez en moyenne %d %% de points en moins', 'logieval-secure' ), $pct ) );
			return $stats;
		}

		public static function ai_learning_reset( int $user_id ): void {
			global $wpdb;
			$table = self::ai_feedback_table();
			if ( '' !== $table ) {
				$wpdb->delete( $table, array( 'teacher_id' => $user_id ) );
			}
		}

		/** L'administrateur autorise la pré-correction IA sur le site (chaque enseignant utilise sa propre clé). */
		public static function ai_grading_allowed(): bool {
			return 1 === (int) self::get_setting( 'ai_grading_enabled', 0 );
		}

		private static function ai_grading_is_enabled(): bool {
			return self::ai_grading_allowed() && LogiEval_Secure_AI::user_is_ready( get_current_user_id() );
		}

		private static function ai_model_label(): string {
			return LogiEval_Secure_AI::model_label( get_current_user_id() );
		}

		private static function ai_grade_answer_for_attempt( array $attempt, array $answer ) {
			$question_id = absint( $answer['question_id'] ?? 0 );
			$max_points  = max( 0, (float) ( $answer['points'] ?? 0 ) );
			if ( ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
				$result = array(
					'points'       => self::format_points( 0 ),
					'comment'      => self::ai_empty_answer_comment( $answer ),
					'analysis'     => __( 'Aucun élément de réponse n’a été fourni par l’élève. La proposition IA est donc 0 point.', 'logieval-secure' ),
					'confidence'   => 1,
					'needs_review' => false,
					'model'        => self::ai_model_label(),
				);
				self::save_ai_grade_result( $attempt, $question_id, $result );
				return $result;
			}
			$result = self::request_ai_grade_answer( $answer, $max_points );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			self::save_ai_grade_result( $attempt, $question_id, $result );
			return $result;
		}

		private static function save_ai_grade_result( array $attempt, int $question_id, array $result ): void {
			global $wpdb;
			$t      = LogiEval_Secure_Database::tables();
			$points = self::parse_decimal( $result['points'] ?? 0 );
			$data   = array(
				'ai_points'       => $points,
				'ai_comment'      => sanitize_textarea_field( (string) ( $result['comment'] ?? '' ) ),
				'ai_analysis'     => sanitize_textarea_field( (string) ( $result['analysis'] ?? '' ) ),
				'ai_confidence'   => max( 0, min( 1, (float) ( $result['confidence'] ?? 0 ) ) ),
				'ai_needs_review' => ! empty( $result['needs_review'] ) ? 1 : 0,
				'ai_model'        => sanitize_text_field( (string) ( $result['model'] ?? self::ai_model_label() ) . ' · ' . sprintf( 'Sévérité %d/5', self::evaluation_ai_strictness( self::get_evaluation( (int) $attempt['evaluation_id'] ) ) ) ),
				'ai_graded_at'    => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			);

			$updated = $wpdb->update(
				$t['answers'],
				$data,
				array(
					'attempt_id'  => (int) $attempt['id'],
					'question_id' => $question_id,
				)
			);
			if ( 0 === $updated && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['answers']} WHERE attempt_id=%d AND question_id=%d", (int) $attempt['id'], $question_id ) ) ) {
				$data['attempt_id']    = (int) $attempt['id'];
				$data['evaluation_id'] = (int) $attempt['evaluation_id'];
				$data['question_id']   = $question_id;
				$data['answer']        = '';
				$data['auto_points']   = null;
				$wpdb->insert( $t['answers'], $data );
			}
		}

		private static function request_ai_grade_answer( array $answer, float $max_points ) {
			$evaluation       = self::get_evaluation( absint( $answer['evaluation_id'] ?? 0 ) );
			$sensei_reference = $evaluation ? LogiEval_Secure_Sensei::get_course_reference_content( absint( $evaluation['sensei_course_id'] ?? 0 ) ) : '';
			$rubric_payload   = array(
				'question'         => wp_strip_all_tags( (string) ( $answer['title'] ?? '' ) ),
				'consigne'         => wp_strip_all_tags( (string) ( $answer['prompt'] ?? '' ) ),
				'contexte'         => wp_strip_all_tags( (string) ( $answer['context'] ?? '' ) ),
				'reponse_attendue' => wp_strip_all_tags( self::readable_answer( (string) ( $answer['expected_answer'] ?? '' ) ) ),
				'reference_theorique_sensei' => $sensei_reference,
				'reponse_eleve'    => wp_strip_all_tags( self::readable_answer( (string) ( $answer['answer'] ?? '' ) ) ),
				'points_max'       => $max_points,
				'criteres_evaluation' => self::scored_criteria( $answer['grading_criteria'] ?? '' )['criteria'],
				'type_question'    => sanitize_key( (string) ( $answer['type'] ?? '' ) ),
				// 1.35 : abréviations et mots d'atelier (modifiable dans Réglages › Mes consignes pour l'IA).
				'vocabulaire_metier' => self::ai_prompt( 'grading_vocabulary' ),
			);
			$learning_note = '';
			if ( self::ai_learning_enabled( get_current_user_id() ) ) {
				$examples = self::ai_learning_examples( absint( $answer['question_id'] ?? 0 ), absint( $answer['attempt_id'] ?? 0 ), get_current_user_id() );
				if ( $examples ) {
					$rubric_payload['corrections_precedentes_enseignant'] = $examples;
					$learning_note .= "Si corrections_precedentes_enseignant est fourni, ce sont des réponses d'autres élèves à CETTE MÊME question, déjà notées par l'enseignant (points_enseignant), avec ta proposition d'alors (points_proposes_ia). Elles montrent sa façon de noter : aligne-toi sur ses exigences et sa sévérité pour des réponses comparables, et inspire-toi du fond de ses commentaires (mais adresse-toi toujours directement à l'élève, même si l'enseignant a écrit « l'élève… »). Ses points priment sur tes anciennes propositions.\n";
				}
				$bias = self::ai_learning_bias( get_current_user_id() );
				if ( $bias['count'] >= 5 && abs( $bias['ratio'] ) >= 0.05 ) {
					$rubric_payload['tendance_enseignant'] = sprintf( 'Sur ses %1$d dernières corrections, l’enseignant a attribué en moyenne %2$s %3$d %% des points maximum par rapport à tes propositions.', $bias['count'], $bias['ratio'] > 0 ? 'plus' : 'moins', (int) round( abs( $bias['ratio'] ) * 100 ) );
					$learning_note .= "Si tendance_enseignant est fourni, corrige ta tendance en conséquence (sois plus généreux si l'enseignant donne en moyenne plus, plus exigeant s'il donne moins), sans jamais dépasser points_max.\n";
				}
			}
			if ( LogiEval_Secure_Question_Types::TFJ === ( $answer['type'] ?? '' ) ) {
				$tfj = LogiEval_Secure_Question_Types::tfj_expected( $answer['expected_answer'] ?? '' );
				$rubric_payload['bareme_vrai_faux'] = sprintf(
					'Question Vrai/Faux argumentée : le bon choix (%1$s) vaut %2$s pt, l’argumentation vaut le reste (%3$s pt). Un mauvais choix ne reçoit pas les points du choix, mais une argumentation pertinente peut recevoir une partie des points d’argumentation.',
					$tfj['choice'],
					null === $tfj['choice_points'] ? $max_points / 2 : min( $max_points, $tfj['choice_points'] ),
					max( 0, $max_points - ( null === $tfj['choice_points'] ? $max_points / 2 : min( $max_points, $tfj['choice_points'] ) ) )
				);
			}
			// LogiOne 1.31 : correction critère par critère. L'IA rend un verdict par critère avec une citation de
			// la réponse ; c'est LogiExam qui calcule le total. Le texte et les points ne peuvent plus se contredire.
			$scored           = self::scored_criteria( $answer['grading_criteria'] ?? '' );
			$teacher_criteria = $scored['criteria'];
			$criteria         = $teacher_criteria;
			if ( $scored['notes'] ) {
				// 1.36.5 : consignes de l'enseignant (« Accepter … pour le premier critère ») : appliquées, pas notées.
				$rubric_payload['consignes_enseignant'] = $scored['notes'];
				// Si la consigne portait des points, le barème restant est ramené au total prévu (sans dépasser la question).
				$sum    = array_sum( array_map( static fn( $c ) => (float) $c['points'], $teacher_criteria ) );
				$target = min( $max_points, $sum + (float) ( $scored['dropped_points'] ?? 0 ) );
				if ( $sum > 0 && $sum < $target - 0.001 ) {
					foreach ( $teacher_criteria as $i => $c ) {
						$teacher_criteria[ $i ]['points'] = round( (float) $c['points'] * $target / $sum, 2 );
					}
					$criteria = $teacher_criteria;
				}
			}
			$tfj_choice       = null; // Vrai / Faux argumenté : le choix est vérifié par le programme, pas par l'IA.
			if ( ! $criteria && LogiEval_Secure_Question_Types::TFJ === ( $answer['type'] ?? '' ) ) {
				$tfj    = LogiEval_Secure_Question_Types::tfj_expected( $answer['expected_answer'] ?? '' );
				$cp     = null === $tfj['choice_points'] ? $max_points / 2 : min( $max_points, $tfj['choice_points'] );
				$given  = json_decode( (string) ( $answer['answer'] ?? '' ), true );
				$choice = is_array( $given ) ? (string) ( $given['choice'] ?? '' ) : '';
				if ( '' !== $tfj['choice'] ) {
					$tfj_choice = array( 'ok' => 0 === strcasecmp( $choice, $tfj['choice'] ), 'given' => $choice, 'expected' => $tfj['choice'] );
					$criteria   = array(
						array( 'label' => sprintf( 'Bon choix Vrai/Faux (%s)', $tfj['choice'] ), 'points' => $cp ),
						array( 'label' => 'Justification : ' . ( '' !== $tfj['justification'] ? $tfj['justification'] : 'argument juste et pertinent' ), 'points' => max( 0, $max_points - $cp ) ),
					);
				}
			}
			$own_ideas = ! $criteria; // Pas de barème : l'IA découpe d'abord la réponse attendue en idées.
			if ( $criteria ) {
				$numbered = array();
				foreach ( $criteria as $i => $c ) {
					$numbered[] = array( 'n' => $i + 1, 'critere' => $c['label'], 'points' => (float) $c['points'] );
				}
				$rubric_payload['criteres_evaluation'] = $numbered;
			} else {
				unset( $rubric_payload['criteres_evaluation'] );
			}
			$criteria_rule = $own_ideas
				? "Il n'y a pas de barème détaillé : découpe d'abord reponse_attendue en 1 à 4 idées essentielles (\"label\" court), réparties sur points_max (\"points\" de chaque idée, total = points_max), puis juge chaque idée ; pour une idée \"partial\", indique les points obtenus dans \"awarded\".\n"
				: "criteres_evaluation est le barème officiel de l'enseignant : juge CHAQUE critère séparément, dans l'ordre, avec son numéro n. N'en ajoute pas, n'en oublie pas.\n"
					. ( ! empty( $rubric_payload['consignes_enseignant'] ) ? "consignes_enseignant : précisions de l'enseignant sur la façon d'appliquer les critères (réponses à accepter aussi, tolérances). Elles PRIMENT : une réponse qu'elles acceptent remplit le critère concerné. Ce ne sont pas des critères notés.\n" : '' );

			$prompt = "Tu aides un enseignant d'école professionnelle à pré-corriger UNE réponse d'élève. Tu ne remplaces pas l'enseignant : il relira ta proposition.\n"
				. $criteria_rule
				// 1.35 : comprendre d'abord ce que l'élève a voulu écrire, puis juger le SENS, pas les mots.
				. "ÉTAPE 0 — LIRE L'ÉLÈVE : avant de juger, écris dans \"lecture\" ce que dit reponse_eleve, reformulé en français correct et clair, idée par idée. Déchiffre les fautes, l'écriture phonétique, les abréviations, le langage SMS, les mots d'atelier et les phrases incomplètes (aide-toi de vocabulaire_metier). Garde EXACTEMENT ses idées : n'en ajoute aucune, ne corrige pas une erreur de fond, ne complète pas une idée inachevée. Une idée vraiment illisible : écris « (illisible) ».\n"
				. "COMPRENDRE LE SENS : un critère est rempli si l'élève exprime la MÊME IDÉE que l'élément attendu, quelle que soit la forme : autres mots, synonyme ou terme du métier, ordre différent, idée répartie sur plusieurs phrases, explication par la cause ou la conséquence, forme négative équivalente (« ne pas laisser sortir les vieux produits en dernier » = FIFO), exemple concret qui montre clairement qu'il a compris ce principe-là. L'élève est « dans le tir » s'il vise la bonne notion : juste mais incomplet ou imprécis → \"partial\" (pas \"miss\") ; notion différente, fausse, contradictoire ou trop vague pour savoir ce qu'il veut dire → \"miss\". Un mot-clé du cours placé dans une phrase fausse ne suffit pas : c'est le sens qui compte.\n"
				// 1.36.5 : corrections trop sévères — l'élève n'a pas à recopier la réponse attendue.
				. "ÉQUITÉ : l'élève n'a pas à reprendre les mots de reponse_attendue ni tous ses détails. S'il donne avec ses mots l'idée principale qu'un critère décrit, même de façon simple, générale ou maladroite, le critère est rempli (\"ok\") — par ex. « c'est pour révéler la pollution » remplit « veut vérifier la pollution réelle ». Garde \"partial\" pour une idée vraiment amorcée mais incomplète, et \"miss\" pour une idée absente ou fausse. Une même phrase peut remplir plusieurs critères. Demande-toi pour chaque critère refusé : « un enseignant raisonnable, relisant cette copie, accepterait-il ce passage ? » — si oui, accepte.\n"
				. "Méthode pour chaque critère :\n"
				. "1. Cherche dans reponse_eleve le passage qui répond à ce critère. Recopie-le MOT POUR MOT dans \"evidence\" (citation courte, 25 mots maximum). S'il n'y a aucun passage, \"evidence\" = \"\".\n"
				. "2. Décide \"status\" : \"ok\" (le critère est rempli : l'idée est exprimée, même avec d'autres mots, un exemple concret correct ou une formulation maladroite), \"partial\" (l'idée est seulement en partie présente ou imprécise), \"miss\" (absente, fausse, contradictoire avec le cours ou hors sujet). Si evidence est vide, status est obligatoirement \"miss\".\n"
				. "3. Pour \"partial\" seulement, donne \"points\" (entre 0 et les points du critère). Pour ok et miss, le programme fixe les points lui-même.\n"
				. "4. \"explanation\" : une phrase qui dit pourquoi, en citant au besoin la réponse.\n"
				. "5. \"hint\" (pour partial et miss seulement) : l'idée qui était attendue, en mots simples, comme on l'expliquerait à l'élève (par ex. « c'est l'expéditeur qui remplit et déclare le contenu »). Vide pour ok.\n"
				. ( 'calculation' === ( $answer['type'] ?? '' ) ? self::ai_prompt( 'grading_calc' ) . "\n" : '' )
				// Règles de correction : modifiables dans Réglages › Mes consignes pour l'IA.
				. self::ai_prompt( 'grading_rules' ) . "\n"
				. "Ne donne PAS de total : le programme additionne les critères.\n"
				. self::ai_strictness_levels()[ self::evaluation_ai_strictness( $evaluation ) ]['prompt'] . " Ce niveau d'exigence s'applique au choix entre ok, partial et miss.\n"
				. $learning_note
				. self::ai_comment_instructions( get_current_user_id() )
				. "Le commentaire doit être cohérent avec tes verdicts : ne félicite pas pour un critère \"miss\", ne reproche pas un critère \"ok\". Pour chaque critère partial ou miss, le commentaire dit à l'élève CE QUI ÉTAIT ATTENDU (l'idée, en mots simples : « Il fallait dire que… », « Il te manquait… »), pour qu'il comprenne sa faute. Jamais un simple « Réponse incorrecte. ».\n"
				. "needs_review = true si tu hésites vraiment sur un critère (réponse ambiguë, illisible, ou idée juste mais hors du cours).\n"
				. "Réponds uniquement avec ce JSON valide :\n"
				. '{"lecture":"ce que dit l\'élève, reformulé en français correct, sans rien ajouter","criteria":[{"n":1,' . ( $own_ideas ? '"label":"idée attendue","points":1,' : '' ) . '"expected":"élément visé de reponse_attendue","evidence":"citation exacte ou vide","matches_expected":true,"status":"ok|partial|miss",' . ( $own_ideas ? '"awarded":0,' : '"points":0,' ) . '"explanation":"…","hint":"idée attendue si partial ou miss"}],' . ( 'calculation' === ( $answer['type'] ?? '' ) ? '"method_coherent":true,' : '' ) . '"summary":"une phrase de conclusion pour l\'enseignant, COHÉRENTE avec tes verdicts : ne présente comme réussi que ce que tu as noté ok ou partial (ici seulement on peut parler de « l\'élève »)","comment":"' . ( 'vous' === self::ai_student_address( get_current_user_id() ) ? 'message adressé DIRECTEMENT à l\'élève en le vouvoyant : « Vous avez… », « Il vous manque… »' : 'message adressé DIRECTEMENT à l\'élève en le tutoyant : « Tu as… », « Il te manque… »' ) . ', jamais « l\'élève », « il », « elle » ni « la réponse est… »","confidence":0.0,"needs_review":false}' . "\n\n"
				. wp_json_encode( $rubric_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			$completion = LogiEval_Secure_AI::complete( $prompt, get_current_user_id(), array( 'provider' => sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ), 'purpose' => 'grading', 'json' => true, 'max_tokens' => 2000 + 250 * max( 1, count( $criteria ) ) ) );
			if ( is_wp_error( $completion ) ) {
				return $completion;
			}
			$json = self::decode_ai_grade_json( (string) $completion['text'] );
			if ( is_wp_error( $json ) ) {
				return $json;
			}

			$student_text = wp_strip_all_tags( self::readable_answer( (string) ( $answer['answer'] ?? '' ) ) );
			$lecture = trim( wp_strip_all_tags( (string) ( $json['lecture'] ?? '' ) ) );
			$rubric_payload['lecture_eleve'] = $lecture;
			$rubric_payload['evaluation_id'] = absint( $answer['evaluation_id'] ?? 0 ); // pour la relecture (niveau d'exigence)
			if ( is_array( $json['criteria'] ?? null ) && $json['criteria'] && self::ai_double_check_enabled( get_current_user_id() ) ) {
				$json['criteria'] = self::ai_verify_criteria( $json['criteria'], $criteria, $own_ideas, $rubric_payload, null !== $tfj_choice );
			}
			// 1.36.5 : la double vérification ne contrôlait que les critères ACCORDÉS (elle ne pouvait que retirer des points).
			// Relecture symétrique : les critères refusés ou partiels sont relus « du côté de l'élève ».
			if ( is_array( $json['criteria'] ?? null ) && $json['criteria'] && '' !== trim( $student_text ) ) {
				$json['criteria'] = self::ai_reread_refused_criteria( $json['criteria'], $criteria, $own_ideas, $rubric_payload, null !== $tfj_choice, $student_text );
				$reread = array();
				foreach ( array_values( $json['criteria'] ) as $k => $row ) {
					if ( is_array( $row ) && ! empty( $row['_reread'] ) ) {
						$reread[] = isset( $row['n'] ) ? (int) $row['n'] : $k + 1;
					}
				}
				if ( $reread ) {
					// La conclusion du premier passage parlait des critères refusés : elle n'est plus juste.
					$json['summary'] = sprintf( _n( 'Critère %s accordé à la relecture (idée exprimée avec les mots de l’élève).', 'Critères %s accordés à la relecture (idées exprimées avec les mots de l’élève).', count( $reread ), 'logieval-secure' ), implode( ', ', $reread ) );
				}
			}
			if ( is_array( $json['criteria'] ?? null ) && $json['criteria'] ) {
				$calc = null;
				if ( 'calculation' === ( $answer['type'] ?? '' ) ) {
					$calc = array(
						'result_ok'       => LogiEval_Secure_Question_Types::calculation_result_ok( $answer['expected_answer'] ?? '', json_decode( (string) ( $answer['answer'] ?? '' ), true ) ?: (string) ( $answer['answer'] ?? '' ) ),
						'method_coherent' => array_key_exists( 'method_coherent', $json ) ? (bool) $json['method_coherent'] : null,
					);
				}
				$graded = self::ai_grade_from_criteria( $json, $criteria, $own_ideas, $max_points, $student_text, $tfj_choice, $calc );
			} else {
				// Ancien format (l'IA n'a pas suivi le schéma) : total proposé par l'IA.
				$graded = array(
					'points'       => max( 0, min( $max_points, round( (float) ( $json['points'] ?? 0 ), 2 ) ) ),
					'analysis'     => (string) ( $json['analysis'] ?? '' ),
					'needs_review' => ! empty( $json['needs_review'] ),
				);
			}
			$points = (float) $graded['points'];
			// Garde-fou : calcul sans développement écrit → les critères « développement / démarche » ne sont pas accordés.
			list( $points, $analysis_note ) = self::cap_points_without_development( $answer, $points, $max_points );
			$needs_review = ! empty( $graded['needs_review'] ) || ! empty( $json['needs_review'] );
			$confidence   = max( 0, min( 1, (float) ( $json['confidence'] ?? 0 ) ) );
			if ( $needs_review ) {
				$confidence = min( $confidence, 0.6 );
			}

			// Commentaire écrit dans un passage à part, d'après les points définitifs (comme « Régénérer le commentaire »,
			// jugé bien meilleur) ; si ce passage échoue, on garde celui de la correction (s'il est encore cohérent).
			$comment = '';
			if ( isset( $graded['verdicts'] ) ) {
				$written = self::ai_comment_for_points( $answer, $points, $max_points, $graded['verdicts'] );
				$comment = is_wp_error( $written ) ? '' : (string) $written;
			}
			if ( '' === $comment ) {
				$first   = empty( $graded['changed'] ) ? trim( (string) ( $json['comment'] ?? '' ) ) : '';
				$comment = self::ai_direct_comment( $first, $graded['verdicts'] ?? array(), $points, $max_points );
			} else {
				$comment = self::ai_comment_with_expected( $comment, $graded['verdicts'], self::ai_student_address( get_current_user_id() ) );
			}

			// 1.35 : l'enseignant voit ce que l'IA a compris de la réponse, pour juger si elle l'a bien lue.
			$understood = '' !== $lecture && '' !== trim( $student_text ) ? '🧠 Compris par l’IA : « ' . self::shorten( $lecture, 500 ) . ' »' . "\n" : '';
			return array(
				'points'       => self::format_points( $points ),
				'comment'      => sanitize_textarea_field( $comment ),
				'analysis'     => sanitize_textarea_field( $understood . $graded['analysis'] . $analysis_note ),
				'confidence'   => $confidence,
				'needs_review' => $needs_review,
				'model'        => (string) $completion['model'],
			);
		}

		/** Consigne selon le niveau de points : tous les points = réponse acceptée, aucun reproche. */
		private static function ai_comment_score_rule( float $points, float $max_points, bool $by_teacher ): string {
			if ( $max_points > 0 && $points >= $max_points - 0.001 ) {
				return ( $by_teacher ? "L'enseignant donne TOUS les points : il accepte la réponse de l'élève telle qu'elle est." : "L'élève a TOUS les points." )
					. " Le commentaire confirme que c'est juste et dit ce qui est bien dans SA réponse. Ne dis JAMAIS qu'un élément est faux, manquant, imprécis ou « pas ce qui était attendu », même si sa réponse diffère de reponse_attendue. Pas de « il fallait », pas de « par contre ».\n";
			}
			if ( $points <= 0.001 ) {
				return "L'élève n'a aucun point : explique simplement et sans le décourager ce qui était attendu. N'écris PAS « bonne idée », « bien », « bravo » ni « c'est juste » : rien dans sa réponse ne rapporte de point. S'il a écrit quelque chose de vrai mais hors sujet, dis-le ainsi : « c'est vrai, mais ça ne répond pas à la question », puis donne l'idée attendue.\n";
			}
			return 'Les points sont partiels (' . self::short_points( $points ) . ' sur ' . self::short_points( $max_points ) . ") : le commentaire doit refléter cette proportion — ce qui est juste d'abord, puis ce qui manque pour avoir tous les points.\n";
		}

		/** 1.38.3 : le commentaire félicite-t-il l'élève ? (incohérent quand il n'a aucun point) */
		public static function ai_comment_has_praise( string $comment ): bool {
			return (bool) preg_match( "/(bonne id[ée]e|bonne piste|bonne r[ée]ponse|bien vu|tr[èe]s bien|bravo|c['’]est (bien|juste|correct|exact)\b(?!.{0,20}mais)|tu as (bien|raison)|vous avez (bien|raison)|bien (compris|identifi|expliqu)|pertinent)/u", mb_strtolower( $comment ) );
		}

		/** Le commentaire signale-t-il une erreur ou un manque ? (interdit quand l'élève a tous les points) */
		public static function ai_comment_has_reproach( string $comment ): bool {
			$c = mb_strtolower( $comment );
			$c = preg_replace( '/«\s*(vrai|faux)\s*»/u', '', $c ); // le choix « faux » d'un Vrai/Faux n'est pas un reproche
			return (bool) preg_match( "/(il (te|vous) manqu|il manqu|il fallait|il aurait fallu|pas ce qui (é|e)tait attendu|n['’]est pas (juste|correct|exact|ce qui)|pas tout à fait|incompl|impr[ée]cis|erron|\\bfaux\\b|par contre|en revanche|attention)/u", $c );
		}

		/**
		 * Rédaction du commentaire pour l'élève, dans un passage à part (LogiOne 1.31.3) : les points sont déjà fixés
		 * (par l'enseignant ou par la correction critère par critère). L'IA ne fait qu'écrire : ce qui est juste, et
		 * pour ce qui manque, l'idée attendue. Utilisé par « Régénérer le commentaire » ET par la pré-correction.
		 * @return string|WP_Error
		 */
		public static function ai_comment_for_points( array $answer, float $points, float $max_points, array $verdicts, string $draft = '', bool $by_teacher = false ) {
			$payload = array(
				'consigne'         => wp_strip_all_tags( (string) ( $answer['prompt'] ?? '' ) ),
				'contexte'         => wp_strip_all_tags( (string) ( $answer['context'] ?? '' ) ),
				'reponse_attendue' => wp_strip_all_tags( self::readable_answer( (string) ( $answer['expected_answer'] ?? '' ) ) ),
				'reponse_eleve'    => wp_strip_all_tags( self::readable_answer( (string) ( $answer['answer'] ?? '' ) ) ),
				'points'           => $points,
				'points_max'       => $max_points,
			);
			$rows = array();
			foreach ( $verdicts as $v ) {
				$row = array( 'critere' => (string) $v['label'], 'rempli' => 'ok' === $v['status'] ? 'oui' : ( 'partial' === $v['status'] ? 'en partie' : 'non' ) );
				if ( '' !== trim( (string) ( $v['hint'] ?? '' ) ) ) {
					$row['idee_attendue'] = (string) $v['hint'];
				}
				$rows[] = $row;
			}
			if ( $rows ) {
				$payload['criteres'] = $rows;
			}
			if ( '' !== $draft && ! self::is_legacy_empty_comment( $draft ) ) {
				$payload['commentaire_actuel'] = $draft;
			}
			$prompt = "Tu aides un enseignant d'école professionnelle. La réponse est DÉJÀ corrigée : " . ( $by_teacher ? "l'enseignant" : 'la correction' ) . " donne points sur points_max"
				. ( $rows ? ' et indique pour chaque critère s\'il est rempli' : '' ) . ". Ces points sont définitifs : ne les discute pas, ne les recalcule pas, ne propose pas d'autre note.\n"
				. "Écris uniquement le commentaire pour l'élève, qui explique CES points : ce qui est juste dans sa réponse, et pour ce qui manque, l'idée qui était attendue (en mots simples, d'après reponse_attendue). Si l'élève a écrit quelque chose de vrai mais qui n'est pas ce qui était attendu, dis-le-lui simplement.\n"
				. ( isset( $payload['commentaire_actuel'] ) ? "commentaire_actuel est le brouillon de l'enseignant : garde ses idées et son intention, reformule-le simplement.\n" : '' )
				. self::ai_comment_score_rule( $points, $max_points, $by_teacher )
				. self::ai_comment_instructions( get_current_user_id() )
				. 'Réponds uniquement avec ce JSON : {"comment":"…"}' . "\n\n"
				. wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$full    = $max_points > 0 && $points >= $max_points - 0.001;
			$address = self::ai_student_address( get_current_user_id() );
			$comment = '';
			for ( $try = 0; $try < 2; $try++ ) {
				$retry      = '';
				if ( $try ) {
					$retry = $full
						? "\n\nATTENTION : ton commentaire précédent signalait une erreur ou un manque alors que l'élève a TOUS les points. Réécris-le : uniquement ce qui est juste dans sa réponse."
						: "\n\nATTENTION : ton commentaire précédent félicitait l'élève (« bonne idée », « bien »…) alors qu'il n'a AUCUN point. Réécris-le : ce qui était attendu, sans compliment sur ce qui ne rapporte rien.";
				}
				$completion = LogiEval_Secure_AI::complete( $prompt . $retry, get_current_user_id(), array( 'provider' => sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ), 'purpose' => 'grading', 'json' => true, 'max_tokens' => 700 ) );
				if ( is_wp_error( $completion ) ) {
					return $try ? self::ai_comment_from_verdicts( $verdicts, $address, $points, $max_points ) : $completion;
				}
				$json    = self::decode_ai_grade_json( (string) $completion['text'] );
				$comment = is_array( $json ) ? trim( (string) ( $json['comment'] ?? '' ) ) : '';
				$zero = $points <= 0.001 && $max_points > 0;
				if ( ( ! $full || ! self::ai_comment_has_reproach( $comment ) ) && ( ! $zero || ! self::ai_comment_has_praise( $comment ) ) ) {
					break;
				}
				$comment = '';
			}
			if ( ( $full || ( $points <= 0.001 && $max_points > 0 ) ) && '' === $comment ) {
				return self::ai_comment_from_verdicts( $verdicts, $address, $points, $max_points );
			}
			return self::ai_direct_comment_raw( $comment, $verdicts, $points, $max_points, $address );
		}

		/**
		 * Double vérification (LogiOne 1.31.3) : un second passage, en contrôleur exigeant, reprend UNIQUEMENT les
		 * critères accordés (ok / partial) et retire ceux qui ne correspondent pas vraiment à la réponse attendue.
		 * Il ne peut qu'enlever des points, jamais en ajouter.
		 */
		private static function ai_verify_criteria( array $rows, array $criteria, bool $own_ideas, array $payload, bool $skip_first ): array {
			$to_check = array();
			foreach ( array_values( $rows ) as $k => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$n      = isset( $row['n'] ) ? (int) $row['n'] : $k + 1;
				$status = strtolower( (string) ( $row['status'] ?? '' ) );
				// Déjà écarté (ne correspond pas à l'attendu) : inutile de le faire vérifier.
				if ( ! in_array( $status, array( 'ok', 'partial' ), true ) || ( $skip_first && 1 === $n ) || ( array_key_exists( 'matches_expected', $row ) && false === $row['matches_expected'] ) ) {
					continue;
				}
				$label      = $own_ideas ? (string) ( $row['label'] ?? '' ) : (string) ( $criteria[ $n - 1 ]['label'] ?? '' );
				$to_check[] = array( 'n' => $n, 'critere' => $label, 'element_attendu' => (string) ( $row['expected'] ?? '' ), 'citation_eleve' => (string) ( $row['evidence'] ?? '' ), 'verdict_propose' => $status );
			}
			if ( ! $to_check ) {
				return $rows;
			}
			$prompt = "Tu es un contrôleur exigeant mais juste. Un premier correcteur a accordé des points à une réponse d'élève. Vérifie UNIQUEMENT ces critères accordés.\n"
				. "Pour chacun : la citation de l'élève exprime-t-elle VRAIMENT l'élément attendu (d'après reponse_attendue et la consigne) ? Juge le SENS, pas les mots : la même idée avec d'autres mots, un synonyme, un terme du métier (voir vocabulaire_metier), des fautes d'orthographe ou un exemple qui illustre correctement ce principe → keep = true. Une information différente de l'attendu (autre pays, autre exemple, autre notion), fausse, ou trop vague pour savoir ce que l'élève veut dire, ou qui ne répond pas à la question → keep = false. lecture_eleve est la réponse de l'élève remise en français correct : sers-t'en pour comprendre, mais ne te base que sur ce que l'élève a écrit.\n"
				. 'Réponds uniquement avec ce JSON : {"checks":[{"n":1,"keep":true,"reason":"une phrase"}]}' . "\n\n"
				. wp_json_encode(
					array(
						'consigne'          => $payload['consigne'] ?? '',
						'contexte'          => $payload['contexte'] ?? '',
						'reponse_attendue'  => $payload['reponse_attendue'] ?? '',
						'reponse_eleve'     => $payload['reponse_eleve'] ?? '',
						'lecture_eleve'     => $payload['lecture_eleve'] ?? '',
						'vocabulaire_metier' => $payload['vocabulaire_metier'] ?? '',
						'criteres_accordes' => $to_check,
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				);
			$completion = LogiEval_Secure_AI::complete( $prompt, get_current_user_id(), array( 'provider' => sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ), 'purpose' => 'grading', 'json' => true, 'max_tokens' => 400 + 150 * count( $to_check ) ) );
			if ( is_wp_error( $completion ) ) {
				return $rows; // vérification impossible : le premier verdict reste (avec ses garde-fous)
			}
			$json = self::decode_ai_grade_json( (string) $completion['text'] );
			if ( ! is_array( $json ) || ! is_array( $json['checks'] ?? null ) ) {
				return $rows;
			}
			$reject  = array();
			$checked = array_flip( array_map( static fn( $c ) => (int) $c['n'], $to_check ) );
			foreach ( $json['checks'] as $check ) {
				// Seuls les critères réellement soumis au contrôle peuvent être retirés.
				if ( is_array( $check ) && isset( $check['n'], $checked[ (int) $check['n'] ] ) && false === ( $check['keep'] ?? true ) ) {
					$reject[ (int) $check['n'] ] = trim( wp_strip_all_tags( (string) ( $check['reason'] ?? '' ) ) );
				}
			}
			foreach ( array_values( $rows ) as $k => $row ) {
				$n = is_array( $row ) && isset( $row['n'] ) ? (int) $row['n'] : $k + 1;
				if ( isset( $reject[ $n ] ) ) {
					$rows[ $k ]['status']      = 'miss';
					$rows[ $k ]['explanation'] = 'Retiré à la double vérification' . ( '' !== $reject[ $n ] ? ' : ' . $reject[ $n ] : '.' );
					$rows[ $k ]['_changed']    = true;
					if ( '' === trim( (string) ( $rows[ $k ]['hint'] ?? '' ) ) ) {
						$rows[ $k ]['hint'] = (string) ( $rows[ $k ]['expected'] ?? '' );
					}
				}
			}
			return $rows;
		}

		/**
		 * 1.36.5 : relecture des critères refusés (« miss ») ou partiels. Un second regard, chargé de chercher si l'élève
		 * a exprimé l'idée attendue avec ses propres mots, peut les accorder — seulement avec une citation exacte de la
		 * réponse (vérifiée par le programme). Le niveau d'exigence de l'évaluation s'applique.
		 */
		private static function ai_reread_refused_criteria( array $rows, array $criteria, bool $own_ideas, array $payload, bool $skip_first, string $student_text ): array {
			$to_check = array();
			foreach ( array_values( $rows ) as $k => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$n      = isset( $row['n'] ) ? (int) $row['n'] : $k + 1;
				$status = strtolower( (string) ( $row['status'] ?? '' ) );
				if ( ! in_array( $status, array( 'miss', 'partial' ), true ) || ( $skip_first && 1 === $n ) || ! empty( $row['_changed'] ) ) {
					continue; // déjà accordé, choix Vrai/Faux vérifié par le programme, ou retiré par le contrôle
				}
				$label      = $own_ideas ? (string) ( $row['label'] ?? '' ) : (string) ( $criteria[ $n - 1 ]['label'] ?? '' );
				$to_check[] = array( 'n' => $n, 'critere' => $label, 'points' => $own_ideas ? (float) ( $row['points'] ?? 0 ) : (float) ( $criteria[ $n - 1 ]['points'] ?? 0 ), 'element_attendu' => (string) ( $row['expected'] ?? '' ), 'verdict_propose' => $status, 'raison_du_refus' => (string) ( $row['explanation'] ?? '' ) );
			}
			if ( ! $to_check ) {
				return $rows;
			}
			$evaluation = self::get_evaluation( absint( $payload['evaluation_id'] ?? 0 ) );
			$prompt = "Tu es un second correcteur, enseignant d'école professionnelle, juste et bienveillant. Un premier correcteur a REFUSÉ (miss) ou accordé en partie (partial) ces critères. Il est souvent trop sévère : il exige les mots exacts de la réponse attendue.\n"
				. "Pour chaque critère : relis TOUTE la reponse_eleve (aide-toi de lecture_eleve, qui la remet en français correct). L'élève exprime-t-il l'idée attendue, même avec ses propres mots, de façon simple, générale, maladroite, par un exemple ou par la conséquence ? Juge le SENS : l'élève n'a pas à recopier reponse_attendue ni à en donner tous les détails.\n"
				. "- \"ok\" : l'idée du critère est bien là (un enseignant raisonnable l'accepterait).\n"
				. "- \"partial\" : l'idée est amorcée mais il manque une partie essentielle.\n"
				. "- \"miss\" : l'idée est absente, fausse, ou il faut deviner ce que l'élève veut dire. Ne lui prête jamais une idée qu'il n'a pas écrite.\n"
				. "Si tu accordes (ok ou partial), \"evidence\" = le passage de reponse_eleve recopié MOT POUR MOT (25 mots max). Pour partial, \"points\" entre 0 et les points du critère.\n"
				. ( ! empty( $payload['consignes_enseignant'] ) ? "consignes_enseignant : réponses que l'enseignant accepte aussi ; elles priment.\n" : '' )
				. ( $evaluation ? self::ai_strictness_levels()[ self::evaluation_ai_strictness( $evaluation ) ]['prompt'] . "\n" : '' )
				. 'Réponds uniquement avec ce JSON : {"rereads":[{"n":1,"status":"ok|partial|miss","evidence":"citation exacte","points":0,"reason":"une phrase"}]}' . "\n\n"
				. wp_json_encode(
					array(
						'consigne'             => $payload['consigne'] ?? '',
						'contexte'             => $payload['contexte'] ?? '',
						'reponse_attendue'     => $payload['reponse_attendue'] ?? '',
						'consignes_enseignant' => $payload['consignes_enseignant'] ?? array(),
						'reponse_eleve'        => $payload['reponse_eleve'] ?? '',
						'lecture_eleve'        => $payload['lecture_eleve'] ?? '',
						'vocabulaire_metier'   => $payload['vocabulaire_metier'] ?? '',
						'criteres_a_relire'    => $to_check,
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				);
			$completion = LogiEval_Secure_AI::complete( $prompt, get_current_user_id(), array( 'provider' => sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ), 'purpose' => 'grading', 'json' => true, 'max_tokens' => 500 + 180 * count( $to_check ) ) );
			if ( is_wp_error( $completion ) ) {
				return $rows; // relecture impossible : le premier verdict reste
			}
			$json = self::decode_ai_grade_json( (string) $completion['text'] );
			if ( ! is_array( $json ) || ! is_array( $json['rereads'] ?? null ) ) {
				return $rows;
			}
			$asked = array();
			foreach ( $to_check as $c ) {
				$asked[ $c['n'] ] = $c;
			}
			$better = array();
			foreach ( $json['rereads'] as $r ) {
				if ( ! is_array( $r ) || ! isset( $r['n'], $asked[ (int) $r['n'] ] ) ) {
					continue;
				}
				$n      = (int) $r['n'];
				$status = strtolower( trim( (string) ( $r['status'] ?? '' ) ) );
				$ev     = trim( wp_strip_all_tags( (string) ( $r['evidence'] ?? '' ) ), " \t\n\r\0\x0B\"«»“”" );
				$rank   = array( 'miss' => 0, 'partial' => 1, 'ok' => 2 );
				// Seulement vers le haut, et seulement avec une citation qui se trouve vraiment dans la réponse.
				if ( ! isset( $rank[ $status ] ) || $rank[ $status ] <= $rank[ $asked[ $n ]['verdict_propose'] ] || '' === $ev || ! self::ai_evidence_found( $ev, $student_text ) ) {
					continue;
				}
				$better[ $n ] = array( 'status' => $status, 'evidence' => $ev, 'points' => (float) ( $r['points'] ?? 0 ), 'reason' => trim( wp_strip_all_tags( (string) ( $r['reason'] ?? '' ) ) ) );
			}
			foreach ( array_values( $rows ) as $k => $row ) {
				$n = is_array( $row ) && isset( $row['n'] ) ? (int) $row['n'] : $k + 1;
				if ( ! isset( $better[ $n ] ) ) {
					continue;
				}
				$b = $better[ $n ];
				$rows[ $k ]['status']           = $b['status'];
				$rows[ $k ]['evidence']         = $b['evidence'];
				$rows[ $k ]['matches_expected'] = true;
				if ( 'partial' === $b['status'] ) {
					$rows[ $k ]['points'] = max( 0, min( (float) $asked[ $n ]['points'], $b['points'] ) );
				}
				$rows[ $k ]['explanation'] = 'Accordé à la relecture' . ( '' !== $b['reason'] ? ' : ' . $b['reason'] : '.' );
				if ( 'ok' === $b['status'] ) {
					$rows[ $k ]['hint'] = '';
				}
				$rows[ $k ]['_changed'] = true;
				$rows[ $k ]['_reread']  = true;
			}
			return $rows;
		}

		/**
		 * Verdicts de l'IA → points et analyse, calculés par le programme :
		 * ok = tous les points du critère, miss = 0, partial = entre les deux (arrondi au quart de point) ;
		 * sans citation de la réponse, un critère n'est pas accordé ; citation introuvable → « à vérifier ».
		 */
		public static function ai_grade_from_criteria( array $json, array $criteria, bool $own_ideas, float $max_points, string $student_text, ?array $tfj_choice = null, ?array $calc = null ): array {
			$rows = array();
			foreach ( array_values( (array) $json['criteria'] ) as $k => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$n          = isset( $row['n'] ) ? (int) $row['n'] : $k + 1;
				$rows[ $n ] = $row;
			}
			if ( $own_ideas ) {
				// Idées proposées par l'IA : points ramenés exactement au total de la question.
				$criteria = array();
				$sum      = 0.0;
				foreach ( $rows as $n => $row ) {
					$label = trim( wp_strip_all_tags( (string) ( $row['label'] ?? '' ) ) );
					$pts   = max( 0, (float) ( $row['points'] ?? 0 ) );
					$criteria[] = array( 'label' => '' !== $label ? $label : 'Idée ' . $n, 'points' => $pts );
					$sum      += $pts;
				}
				$count = count( $criteria );
				foreach ( $criteria as $i => $c ) {
					$criteria[ $i ]['points'] = $sum > 0 ? $c['points'] * $max_points / $sum : ( $count ? $max_points / $count : 0 );
				}
				$rows = array_values( $rows );
				$rows = array_combine( range( 1, max( 1, count( $rows ) ) ), $rows ?: array( array() ) );
			}

			$lines        = array();
			$verdicts     = array();
			$changed      = false;
			$total        = 0.0;
			$needs_review = false;
			$missing      = 0;
			foreach ( array_values( $criteria ) as $i => $c ) {
				$n        = $i + 1;
				$cmax     = max( 0, (float) $c['points'] );
				$row      = $rows[ $n ] ?? null;
				$status   = is_array( $row ) ? strtolower( trim( (string) ( $row['status'] ?? '' ) ) ) : '';
				$status   = in_array( $status, array( 'ok', 'partial', 'miss' ), true ) ? $status : ( null === $row ? '' : 'miss' );
				$evidence = is_array( $row ) ? trim( wp_strip_all_tags( (string) ( $row['evidence'] ?? '' ) ), " \t\n\r\0\x0B\"«»“”" ) : '';
				$why      = is_array( $row ) ? trim( wp_strip_all_tags( (string) ( $row['explanation'] ?? '' ) ) ) : '';
				$flag     = '';
				$before   = $status;
				if ( is_array( $row ) && ! empty( $row['_changed'] ) ) {
					$changed = true;
				}
				if ( '' === $status ) {
					$missing++;
					$needs_review = true;
					$status       = 'miss';
					$why          = 'Critère non évalué par l’IA : à vérifier.';
				}
				if ( null !== $tfj_choice && 0 === $i ) {
					// Choix Vrai / Faux : comparé directement à la réponse attendue.
					$status   = $tfj_choice['ok'] ? 'ok' : 'miss';
					$evidence = $tfj_choice['given'];
					$why      = $tfj_choice['ok'] ? 'Bon choix.' : sprintf( 'Choix attendu : « %s ».', $tfj_choice['expected'] );
				} elseif ( 'miss' !== $status && is_array( $row ) && array_key_exists( 'matches_expected', $row ) && false === $row['matches_expected'] ) {
					$status = 'miss';
					$exp    = trim( wp_strip_all_tags( (string) ( $row['expected'] ?? '' ) ) );
					$why    = 'Ne correspond pas à ce qui était attendu' . ( '' !== $exp ? ' (« ' . self::shorten( $exp, 120 ) . ' »)' : '' ) . '.';
				} elseif ( 'miss' !== $status && '' === $evidence ) {
					$status       = 'miss';
					$needs_review = true;
					$flag         = ' (aucune citation de la réponse : non accordé, à vérifier)';
				} elseif ( 'miss' !== $status && ! self::ai_evidence_found( $evidence, $student_text ) ) {
					$needs_review = true;
					$flag         = ' (citation introuvable telle quelle dans la réponse : à vérifier)';
				}
				// Questions de calcul : un calcul sans rapport avec ce qui est demandé ne rapporte rien pour la démarche,
				// et un résultat faux (vérifié par le programme) ne rapporte pas les points du résultat.
				if ( null !== $calc ) {
					$label_l = mb_strtolower( (string) $c['label'] );
					$is_dev  = (bool) preg_match( '/d[ée]velopp|d[ée]marche|calcul|raisonnement|formule|[ée]tape|m[ée]thode|op[ée]ration|justifi/u', $label_l );
					$is_res  = (bool) preg_match( '/r[ée]sultat|r[ée]ponse finale|solution|unit[ée]/u', $label_l ) && ! $is_dev;
					if ( false === $calc['method_coherent'] && ( $is_dev || ( $own_ideas && ! $is_res ) ) && 'miss' !== $status ) {
						$status = 'miss';
						$why    = 'Le calcul ne correspond pas à ce qui est demandé : pas de points pour la démarche.';
					}
					if ( false === $calc['result_ok'] && $is_res && 'miss' !== $status ) {
						$status = 'miss';
						$why    = 'Le résultat final est faux.';
					}
				}
				if ( 'ok' === $status ) {
					$got = $cmax;
				} elseif ( 'partial' === $status ) {
					// Sans barème détaillé, « points » est la valeur de l'idée : les points obtenus sont dans « awarded ».
					$given = isset( $row['awarded'] ) && is_numeric( $row['awarded'] ) ? $row['awarded'] : ( $own_ideas ? null : ( $row['points'] ?? null ) );
					if ( null !== $given && is_numeric( $given ) ) {
						// Points partiels donnés par l'IA : respectés (0 reste 0, plus de « moitié » d'office).
						$got = self::quarter( max( 0, min( $cmax, (float) $given ) ) );
						if ( $got >= $cmax ) {
							$got = self::quarter( max( 0, $cmax - 0.25 ) );
						}
						if ( $got <= 0 ) {
							$status = 'miss';
						}
					} else {
						$got = self::quarter( $cmax / 2 );
					}
				} else {
					$got = 0.0;
				}
				$total     += $got;
				if ( $before !== $status && 'miss' === $status ) {
					$changed = true; // verdict de l'IA corrigé par le programme : son commentaire n'est plus fiable
				}
				$hint       = is_array( $row ) && 'ok' !== $status ? trim( wp_strip_all_tags( (string) ( $row['hint'] ?? '' ) ) ) : '';
				if ( '' === $hint && is_array( $row ) && 'ok' !== $status ) {
					$hint = trim( wp_strip_all_tags( (string) ( $row['expected'] ?? '' ) ) );
				}
				$verdicts[] = array( 'label' => (string) $c['label'], 'status' => $status, 'hint' => $hint );
				$symbol = 'ok' === $status ? '✓' : ( 'partial' === $status ? '◐' : '✗' );
				$state  = 'ok' === $status ? 'rempli' : ( 'partial' === $status ? 'partiellement rempli' : 'non rempli' );
				$label  = false !== mb_strpos( (string) $c['label'], '«' ) ? (string) $c['label'] : '« ' . $c['label'] . ' »';
				$name   = $own_ideas ? 'Idée ' . $n . ' ' . $label : 'Critère ' . $n . ' ' . $state . ' — ' . $label;
				$line   = $symbol . ' ' . $name . ' (' . self::short_points( $got ) . ' / ' . self::short_points( $cmax ) . ' pt)';
				if ( '' !== $evidence && 'miss' !== $status ) {
					$line .= ' : « ' . self::shorten( $evidence, 160 ) . ' »';
				}
				if ( '' !== $why ) {
					$line .= ( '' !== $evidence && 'miss' !== $status ? ' — ' : ' : ' ) . self::shorten( $why, 260 );
				}
				$lines[] = $line . $flag;
			}
			if ( null !== $calc && false === $calc['method_coherent'] && false === $calc['result_ok'] && $total > 0 ) {
				$total   = 0.0;
				$lines[] = '✗ Calcul sans rapport avec la question et résultat faux : aucun point.';
			}
			$total   = max( 0, min( $max_points, self::quarter( $total ) ) );
			$summary = trim( wp_strip_all_tags( (string) ( $json['summary'] ?? '' ) ) );
			// 1.38.3 : la conclusion doit dire la même chose que les critères. « L'élève a identifié un élément pertinent »
			// avec 0 point (ou « il manque… » avec tous les points) est remplacé par une conclusion tirée des verdicts.
			if ( '' !== $summary && $max_points > 0 ) {
				$missing = array();
				foreach ( $verdicts as $v ) {
					if ( is_array( $v ) && 'ok' !== ( $v['status'] ?? '' ) ) {
						$missing[] = self::shorten( (string) ( ( $v['hint'] ?? '' ) !== '' ? $v['hint'] : ( $v['label'] ?? '' ) ), 90 );
					}
				}
				$missing = array_values( array_filter( $missing ) );
				if ( $total <= 0.001 && preg_match( '/(a (bien |correctement )?(identifi|compris|relev|mentionn|not)|pertinent|bonne id[ée]e|bonne piste|juste|correct|bien vu|partiellement)/u', mb_strtolower( $summary ) ) ) {
					$summary = 'Aucun critère rempli' . ( $missing ? ' : il manque ' . implode( ' ; ', array_slice( $missing, 0, 3 ) ) . '.' : '.' );
				} elseif ( $total >= $max_points - 0.001 && self::ai_comment_has_reproach( $summary ) ) {
					$summary = 'Tous les critères sont remplis.';
				}
			}
			$lines[] = '→ Total : ' . self::short_points( $total ) . ' / ' . self::short_points( $max_points ) . ' pt' . ( '' !== $summary ? '. ' . self::shorten( $summary, 300 ) : '' );
			if ( $needs_review ) {
				$lines[] = '→ À vérifier par l’enseignant (voir les remarques ci-dessus).';
			}
			return array( 'points' => $total, 'analysis' => implode( "\n", $lines ), 'needs_review' => $needs_review, 'verdicts' => $verdicts, 'changed' => $changed );
		}

		/**
		 * Le commentaire pour l'élève doit lui parler directement (« Tu as… » / « Vous avez… »), simplement.
		 * S'il parle de « l'élève » à la 3e personne, l'IA le réécrit ; sinon, phrase simple construite d'après les critères.
		 */
		public static function ai_comment_is_direct( string $comment, string $address ): bool {
			$c = trim( $comment );
			if ( '' === $c ) {
				return false;
			}
			// Parle « de l'élève » : toujours refusé.
			$about = "/(\\bl['’](élève|eleve|apprenti|apprentie|étudiant|étudiante)\\b|\\bcet(te)? (élève|apprenti)|\\b(la|sa|cette) réponse (de l['’]élève|est|reste|montre)|^réponse\\b)/iu";
			if ( preg_match( $about, $c ) ) {
				return false;
			}
			// Un commentaire neutre (« Bonne idée sur le transport. ») est accepté : seule la 3e personne est corrigée.
			$tu_words = "/(\\b(tu|te|ton|ta|tes|toi)\\b|\\bt['’])/iu";
			// Vouvoiement choisi mais l'IA tutoie (ou l'inverse) : à réécrire aussi.
			if ( 'vous' === $address && preg_match( $tu_words, $c ) && ! preg_match( '/\\b(vous|votre|vos)\\b/iu', $c ) ) {
				return false;
			}
			// « Il ne cite pas… » au milieu d'un message qui tutoie : il parle encore de l'élève à la 3e personne.
			$third = "/^(il|elle) (?!ne faut|n['’]y a|faut|manque|te |vous )(ne |n['’]|a |donne|cite|explique|mentionne|montre|identifie|indique|répond|parle|évoque|devrait|doit)/iu";
			foreach ( preg_split( '/(?<=[.!?])\\s+/u', $c ) as $sentence ) {
				if ( preg_match( $third, trim( $sentence ) ) ) {
					return false;
				}
			}
			return true;
		}

		private static function ai_direct_comment( string $comment, array $verdicts, float $points, float $max_points ): string {
			$address = self::ai_student_address( get_current_user_id() );
			return self::ai_comment_with_expected( self::ai_direct_comment_raw( $comment, $verdicts, $points, $max_points, $address ), $verdicts, $address );
		}

		/**
		 * Le commentaire doit dire à l'élève ce qui était attendu pour chaque critère manqué : si l'idée attendue
		 * (« hint » donné par l'IA) n'y figure pas, elle est ajoutée en une phrase simple.
		 */
		public static function ai_comment_with_expected( string $comment, array $verdicts, string $address ): string {
			$words = static function ( string $s ): array {
				$s = preg_replace( '/[^a-z0-9]+/', ' ', remove_accents( mb_strtolower( $s ) ) );
				$stop = array_flip( array( 'les', 'des', 'une', 'est', 'que', 'qui', 'pour', 'dans', 'par', 'pas', 'sur', 'avec', 'son', 'ses', 'aux', 'sont', 'faut', 'fallait', 'tu', 'te', 'vous', 'il', 'elle', 'le', 'la', 'de', 'du', 'et', 'en', 'un', 'au', 'ce', 'ou' ) );
				return array_values( array_filter( explode( ' ', $s ), static fn( $w ) => strlen( $w ) >= 3 && ! isset( $stop[ $w ] ) ) );
			};
			$have    = array_flip( $words( $comment ) );
			$missing = array();
			foreach ( $verdicts as $v ) {
				$hint = trim( (string) ( $v['hint'] ?? '' ), " \t.;" );
				if ( 'ok' === ( $v['status'] ?? '' ) || '' === $hint ) {
					continue;
				}
				$hw  = $words( $hint );
				$hit = 0;
				foreach ( $hw as $w ) {
					if ( isset( $have[ $w ] ) ) {
						$hit++;
					}
				}
				if ( ! $hw || $hit / count( $hw ) < 0.5 ) {
					$missing[] = $hint;
				}
			}
			if ( ! $missing ) {
				return $comment;
			}
			$lead = 'vous' === $address ? 'Ce qui était attendu : ' : 'Ce qu’il fallait dire : ';
			$text = $lead . implode( ' ; ', array_map( static fn( $h ) => '' === $h ? $h : mb_strtolower( mb_substr( $h, 0, 1 ) ) . mb_substr( $h, 1 ), $missing ) ) . '.';
			return trim( $comment . ( '' !== trim( $comment ) ? ' ' : '' ) . $text );
		}

		private static function ai_direct_comment_raw( string $comment, array $verdicts, float $points, float $max_points, string $address ): string {
			if ( self::ai_comment_is_direct( $comment, $address ) ) {
				return $comment;
			}
			if ( '' !== $comment ) {
				$tu     = 'tu' === $address;
				$prompt = "Réécris ce commentaire de correction pour qu'il s'adresse DIRECTEMENT à l'élève (un apprenti de 15 à 20 ans en Suisse romande), " . ( $tu ? 'en le tutoyant (« Tu as… », « Il te manque… »)' : 'en le vouvoyant (« Vous avez… », « Il vous manque… »)' )
					. ". Garde exactement le même sens et les mêmes constats, ne change rien au jugement. 2 à 4 phrases courtes, mots simples, ton naturel d'enseignant, sans « l'élève », sans « il » ni « elle » pour parler de lui, sans formule toute faite ni emoji. Points obtenus : " . self::short_points( $points ) . ' sur ' . self::short_points( $max_points ) . ".
"
					. 'Réponds uniquement avec ce JSON : {"comment":"…"}' . "

Commentaire à réécrire :
" . $comment;
				$completion = LogiEval_Secure_AI::complete( $prompt, get_current_user_id(), array( 'provider' => sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ), 'purpose' => 'grading', 'json' => true, 'max_tokens' => 500 ) );
				if ( ! is_wp_error( $completion ) ) {
					$json      = self::decode_ai_grade_json( (string) $completion['text'] );
					$rewritten = is_array( $json ) ? trim( (string) ( $json['comment'] ?? '' ) ) : '';
					if ( self::ai_comment_is_direct( $rewritten, $address ) ) {
						return $rewritten;
					}
				}
			}
			return self::ai_comment_from_verdicts( $verdicts, $address, $points, $max_points );
		}

		/** Commentaire simple construit d'après les verdicts, quand l'IA n'a pas su s'adresser à l'élève. */
		public static function ai_comment_from_verdicts( array $verdicts, string $address, float $points, float $max_points ): string {
			$tu    = 'vous' !== $address;
			// Points complets : jamais « il te manque » (les critères non cochés ne veulent alors rien dire).
			if ( $max_points > 0 && $points >= $max_points - 0.001 ) {
				return ( $tu ? 'C’est juste, tu as tous les points (' : 'C’est juste, vous avez tous les points (' ) . self::short_points( $points ) . ' sur ' . self::short_points( $max_points ) . ').';
			}
			$clean = static function ( string $label ): string {
				$label = preg_replace( '/^\\s*\\d+([.,]\\d+)?\\s*pts?\\.?\\s*/iu', '', $label );
				$label = preg_replace( '/^(pour|sur)\\s+(le|la|les|l[\'’])\\s*/iu', '', $label );
				return trim( $label, " \t«»\"" );
			};
			$ok = $part = $miss = array();
			foreach ( $verdicts as $v ) {
				$l = $clean( (string) $v['label'] );
				if ( '' === $l ) {
					continue;
				}
				if ( 'ok' === $v['status'] ) {
					$ok[] = '« ' . $l . ' »';
				} elseif ( 'partial' === $v['status'] ) {
					$part[] = '« ' . $l . ' »';
				} elseif ( '' !== trim( (string) ( $v['hint'] ?? '' ) ) ) {
					$miss[] = trim( (string) $v['hint'], " \t." ); // l'idée attendue, en mots simples
				} else {
					$miss[] = '« ' . $l . ' »';
				}
			}
			$out = array();
			if ( $ok ) {
				$out[] = 'C’est juste pour ' . implode( ' et ', $ok ) . '.';
			}
			if ( $part ) {
				$out[] = ( $tu ? 'Tu es sur la bonne piste pour ' : 'Vous êtes sur la bonne piste pour ' ) . implode( ' et ', $part ) . ( $tu ? ', mais il faut être plus précis.' : ', mais il faut être plus précis.' );
			}
			if ( $miss ) {
				$last  = array_pop( $miss );
				$out[] = ( $tu ? 'Il te manque ' : 'Il vous manque ' ) . ( $miss ? implode( ', ', $miss ) . ' et ' : '' ) . $last . '.';
			}
			if ( ! $out ) {
				$out[] = $tu ? 'Relis la question et la réponse attendue avec ton enseignant.' : 'Relisez la question et la réponse attendue avec votre enseignant.';
			}
			$out[] = ( $tu ? 'Tu as ' : 'Vous avez ' ) . self::short_points( $points ) . ' point' . ( $points >= 2 ? 's' : '' ) . ' sur ' . self::short_points( $max_points ) . '.';
			return implode( ' ', $out );
		}

		/** La citation donnée par l'IA se retrouve-t-elle dans la réponse (mots communs, sans accents ni ponctuation) ? */
		public static function ai_evidence_found( string $evidence, string $answer ): bool {
			$norm  = static function ( string $s ): array {
				$s = remove_accents( mb_strtolower( $s ) );
				$s = preg_replace( '/[^a-z0-9]+/', ' ', $s );
				return array_values( array_filter( explode( ' ', $s ), static fn( $w ) => strlen( $w ) >= 3 ) );
			};
			$ev = $norm( $evidence );
			if ( ! $ev ) {
				return '' !== trim( $evidence ) && false !== mb_stripos( $answer, trim( $evidence ) );
			}
			$have = array_flip( $norm( $answer ) );
			$hit  = 0;
			foreach ( $ev as $w ) {
				if ( isset( $have[ $w ] ) ) {
					$hit++;
				}
			}
			return $hit / count( $ev ) >= 0.6;
		}

		private static function quarter( float $v ): float {
			return round( $v * 4 ) / 4;
		}

		private static function short_points( float $v ): string {
			$s = rtrim( rtrim( number_format( $v, 2, ',', '' ), '0' ), ',' );
			return '' === $s ? '0' : $s;
		}

		private static function shorten( string $s, int $max ): string {
			$s = trim( preg_replace( '/\s+/u', ' ', $s ) );
			return mb_strlen( $s ) > $max ? rtrim( mb_substr( $s, 0, $max - 1 ) ) . '…' : $s;
		}

		/**
		 * Calcul sans développement écrit : les points des critères « développement / démarche / raisonnement /
		 * justification / étapes » ne peuvent pas être accordés, quoi que propose l'IA.
		 * @return array{0: float, 1: string} points (éventuellement réduits) et note ajoutée à l'analyse
		 */
		public static function cap_points_without_development( array $answer, float $points, float $max_points ): array {
			if ( 'calculation' !== ( $answer['type'] ?? '' ) ) {
				return array( $points, '' );
			}
			$given = json_decode( (string) ( $answer['answer'] ?? '' ), true );
			$dev   = is_array( $given ) ? trim( (string) ( $given['development'] ?? '' ) ) : '';
			if ( '' !== $dev ) {
				return array( $points, '' );
			}
			$dev_points = 0.0;
			foreach ( self::scored_criteria( $answer['grading_criteria'] ?? '' )['criteria'] as $criterion ) {
				if ( preg_match( '/d[ée]velopp|d[ée]marche|raisonnement|justifi|[ée]tapes/iu', (string) $criterion['label'] ) ) {
					$dev_points += (float) $criterion['points'];
				}
			}
			if ( $dev_points <= 0 || $points <= $max_points - $dev_points ) {
				return array( $points, '' );
			}
			return array( max( 0, round( $max_points - $dev_points, 2 ) ), ' ' . sprintf( 'Aucun développement écrit : les critères de développement (%s pt) ne sont pas accordés.', self::format_points( $dev_points ) ) );
		}

		private static function decode_ai_grade_json( string $text ) {
			$text = trim( preg_replace( '/^```(?:json)?|```$/m', '', $text ) );
			$data = json_decode( $text, true );
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'logieval_ai_invalid_json', __( 'La réponse de l’IA n’a pas pu être interprétée de manière fiable.', 'logieval-secure' ) );
			}
			return $data;
		}
}
