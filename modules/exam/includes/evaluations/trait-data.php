<?php
/**
 * LogiEval_Secure_Evaluations — partie « data ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Data {
	/** 1.35.1 : sous-menus des réglages (sans JavaScript, tout reste affiché). */
	private static function settings_tabs( bool $is_admin ): array {
		$tabs = array(
			'ia'         => __( '🔌 IA : connexion et modèles', 'logieval-secure' ),
			'correction' => __( '✅ Correction IA', 'logieval-secure' ),
			'creation'   => __( '✨ Création IA', 'logieval-secure' ),
		);
		if ( $is_admin ) {
			$tabs['securite'] = __( '🔒 Sécurité et sorties', 'logieval-secure' );
			$tabs['site']     = __( '⚙ Site et élèves', 'logieval-secure' );
		}
		return $tabs;
	}

	private static function render_settings_tabs( bool $is_admin ): void {
		$tabs    = self::settings_tabs( $is_admin );
		$current = sanitize_key( wp_unslash( $_GET['settings_tab'] ?? '' ) );
		$current = isset( $tabs[ $current ] ) ? $current : '';
		?>
		<nav class="les-settings-tabs" data-les-settings-tabs data-current="<?php echo esc_attr( $current ); ?>" aria-label="<?php esc_attr_e( 'Parties des réglages', 'logieval-secure' ); ?>" hidden>
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'settings_tab', $key ) ); ?>" data-les-settings-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private static function render_settings_workspace(): void {
		$settings = self::get_settings();
		$is_admin = self::current_user_is_admin();
		?>
		<?php if ( isset( $_GET['settings_saved'] ) ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Réglages enregistrés.', 'logieval-secure' ); ?></p></div>
		<?php endif; ?>
		<?php self::render_settings_tabs( $is_admin ); ?>
		<?php self::render_ai_settings_panel(); ?>
		<?php if ( $is_admin ) : ?>
		<section class="les-panel" data-les-settings-section>
			<h2><?php esc_html_e( 'Réglages du site (administrateur)', 'logieval-secure' ); ?></h2>
			<p><?php esc_html_e( 'Paramètres globaux appliqués à tous les enseignants, aux nouvelles évaluations et aux contrôles de sécurité.', 'logieval-secure' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="logieval_secure_save_settings">
				<?php self::frontend_redirect_field( array( 'logiexam_view' => 'settings' ) ); ?>
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<div class="les-settings-pane" data-les-settings-pane="securite">
				<h3><?php esc_html_e( 'Sécurité et sorties', 'logieval-secure' ); ?></h3>
				<div class="les-form-row">
					<label><?php esc_html_e( 'Verrouillage après soumission (minutes)', 'logieval-secure' ); ?>
						<input type="number" min="0" max="60" name="post_submit_lock_minutes" value="<?php echo esc_attr( $settings['post_submit_lock_minutes'] ); ?>">
					</label>
					<label><?php esc_html_e( 'Blocages autorisés (avec demande de déblocage)', 'logieval-secure' ); ?>
						<input type="number" min="0" max="10" name="max_exit_attempts" value="<?php echo esc_attr( $settings['max_exit_attempts'] ); ?>">
						<small class="description"><?php esc_html_e( 'Ex. 1 : à la 1re sortie, l’élève est bloqué et peut demander le déblocage (compte à rebours) ; à la 2e sortie, l’évaluation est clôturée (note 1). 0 = la 1re sortie clôture directement.', 'logieval-secure' ); ?></small>
					</label>
					<label><?php esc_html_e( 'Délai pour revenir en plein écran (secondes)', 'logieval-secure' ); ?>
						<input type="number" min="0" max="60" name="fullscreen_grace_seconds" value="<?php echo esc_attr( $settings['fullscreen_grace_seconds'] ); ?>">
						<small class="description"><?php esc_html_e( 'L’élève qui quitte le plein écran voit un compte à rebours : s’il revient à temps, pas de blocage. 0 = blocage immédiat.', 'logieval-secure' ); ?></small>
					</label>
					<label><?php esc_html_e( 'Délai pour demander un déblocage (secondes)', 'logieval-secure' ); ?>
						<input type="number" min="0" max="300" name="unlock_request_seconds" value="<?php echo esc_attr( $settings['unlock_request_seconds'] ?? 30 ); ?>">
						<small class="description"><?php esc_html_e( 'Après un blocage, l’élève voit un grand compte à rebours rouge : s’il ne clique pas sur « Demander un déblocage » à temps, l’évaluation est clôturée définitivement avec la note 1. 0 = pas de délai (attente illimitée).', 'logieval-secure' ); ?></small>
					</label>
				</div>
				<div class="les-form-row">
					<label><?php esc_html_e( 'Mode sécurisé par défaut', 'logieval-secure' ); ?>
						<select name="secure_mode_default">
							<option value="1" <?php selected( (int) $settings['secure_mode_default'], 1 ); ?>><?php esc_html_e( 'Activé', 'logieval-secure' ); ?></option>
							<option value="0" <?php selected( (int) $settings['secure_mode_default'], 0 ); ?>><?php esc_html_e( 'Désactivé', 'logieval-secure' ); ?></option>
						</select>
					</label>
				</div>
				<label><?php esc_html_e( 'Message de surveillance affiché par défaut', 'logieval-secure' ); ?>
					<textarea class="widefat" rows="5" name="default_security_notice"><?php echo esc_textarea( $settings['default_security_notice'] ); ?></textarea>
				</label>
				<p class="description"><?php esc_html_e( 'Chaque évaluation peut ensuite personnaliser ce message si nécessaire.', 'logieval-secure' ); ?></p>
				</div>
				<div class="les-settings-pane" data-les-settings-pane="site">
				<h3><?php esc_html_e( 'Site et élèves', 'logieval-secure' ); ?></h3>
				<div class="les-form-row">
					<label><?php esc_html_e( 'Visibilité des résultats élèves', 'logieval-secure' ); ?>
						<select name="results_visibility">
							<option value="teacher_only" <?php selected( $settings['results_visibility'], 'teacher_only' ); ?>><?php esc_html_e( 'Enseignant uniquement', 'logieval-secure' ); ?></option>
							<option value="after_correction" <?php selected( $settings['results_visibility'], 'after_correction' ); ?>><?php esc_html_e( 'Après correction', 'logieval-secure' ); ?></option>
						</select>
					</label>
				</div>
				<label class="les-checkbox-line">
					<input type="checkbox" name="ai_grading_enabled" value="1" <?php checked( (int) ( $settings['ai_grading_enabled'] ?? 0 ), 1 ); ?>>
					<?php esc_html_e( 'Autoriser les enseignants à utiliser la pré-correction IA (chacun avec sa propre clé API et à ses frais)', 'logieval-secure' ); ?>
				</label>
				</div>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer les réglages du site', 'logieval-secure' ); ?></button></p>
			</form>
			<div class="les-settings-pane" data-les-settings-pane="site">
				<hr>
				<?php self::render_shortcodes_panel(); ?>
			</div>
		</section>
		<?php endif; ?>
		<?php
	}

	public static function handle_save_settings(): void {
		self::require_teacher_post();
		if ( ! self::current_user_is_admin() ) {
			wp_die( esc_html__( 'Seul l’administrateur peut modifier les réglages du site.', 'logieval-secure' ) );
		}
		$settings = array_merge(
			self::get_settings(),
			array(
				'post_submit_lock_minutes' => max( 0, min( 60, absint( $_POST['post_submit_lock_minutes'] ?? 5 ) ) ),
				'max_exit_attempts'        => max( 0, min( 10, absint( $_POST['max_exit_attempts'] ?? 3 ) ) ),
				'fullscreen_grace_seconds' => max( 0, min( 60, absint( $_POST['fullscreen_grace_seconds'] ?? 5 ) ) ),
				'unlock_request_seconds'   => max( 0, min( 300, absint( $_POST['unlock_request_seconds'] ?? 30 ) ) ),
				'results_visibility'       => in_array( sanitize_key( $_POST['results_visibility'] ?? 'teacher_only' ), array( 'teacher_only', 'after_correction' ), true ) ? sanitize_key( $_POST['results_visibility'] ) : 'teacher_only',
				'secure_mode_default'      => isset( $_POST['secure_mode_default'] ) ? absint( $_POST['secure_mode_default'] ) : 1,
				'default_security_notice'  => wp_kses_post( wp_unslash( $_POST['default_security_notice'] ?? self::hardcoded_default_security_notice() ) ),
				'ai_grading_enabled'       => isset( $_POST['ai_grading_enabled'] ) ? 1 : 0,
			)
		);
		unset( $settings['openai_api_key'], $settings['openai_model'] );
		update_option( self::SETTINGS_OPTION, $settings, false );
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-settings&settings_saved=1' ), array( 'logiexam_view' => 'settings', 'settings_saved' => 1 ) ) );
		exit;
	}

	/** Évaluations visibles par l'enseignant connecté (les siennes ; toutes pour l'administrateur). */
	public static function get_evaluations(): array {
		global $wpdb;
		$t   = LogiEval_Secure_Database::tables();
		$sql = "SELECT * FROM {$t['evaluations']} WHERE status <> 'deleted'";
		if ( ! self::admin_sees_all() ) {
			$sql .= $wpdb->prepare( ' AND created_by=%d', get_current_user_id() );
		}
		return $wpdb->get_results( $sql . ' ORDER BY created_at DESC', ARRAY_A );
	}

	public static function get_evaluation( int $id ): ?array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['evaluations']} WHERE id=%d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public static function get_attempt( int $attempt_id ): ?array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['attempts']} WHERE id=%d", $attempt_id ), ARRAY_A );
		return $row ?: null;
	}

	public static function get_attempts( int $evaluation_id ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['attempts']} WHERE evaluation_id=%d ORDER BY started_at DESC", $evaluation_id ), ARRAY_A );
	}

	public static function get_result_by_attempt( int $attempt_id ): ?array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['results']} WHERE attempt_id=%d", $attempt_id ), ARRAY_A );
		return $row ?: null;
	}

	public static function get_answers_for_attempt( int $attempt_id ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$attempt = self::get_attempt( $attempt_id );
		if ( ! $attempt ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
					"SELECT COALESCE(a.id, 0) AS id, %d AS attempt_id, eq.evaluation_id, q.id AS question_id, COALESCE(a.answer, '') AS answer, a.auto_points, a.manual_points, a.teacher_comment, a.ai_points, a.ai_comment, a.ai_analysis, a.ai_confidence, a.ai_needs_review, a.ai_model, a.ai_graded_at, q.title, q.context, q.prompt, q.type, q.expected_answer, q.options, q.grading_criteria, q.media_id, eq.points
				FROM {$t['eval_questions']} eq
				INNER JOIN {$t['questions']} q ON q.id=eq.question_id
				LEFT JOIN {$t['answers']} a ON a.attempt_id=%d AND a.question_id=eq.question_id
				WHERE eq.evaluation_id=%d
				ORDER BY eq.position ASC",
				$attempt_id,
				$attempt_id,
				(int) $attempt['evaluation_id']
			),
			ARRAY_A
		) ?: array();
		// Réponse vide : les anciens commentaires automatiques s'affichent simplement « Non répondu. » (sans rien réécrire en base).
		foreach ( $rows as $i => $row ) {
			foreach ( array( 'teacher_comment', 'ai_comment' ) as $k ) {
				if ( isset( $row[ $k ] ) && self::is_legacy_empty_comment( (string) $row[ $k ] ) ) {
					$rows[ $i ][ $k ] = self::empty_answer_comment_text();
				}
			}
		}
		return $rows;
	}

	public static function empty_answer_comment_text(): string {
		return __( 'Non répondu.', 'logieval-secure' );
	}

	/** Anciens textes automatiques pour une réponse vide (versions précédentes de LogiExam). */
	public static function is_legacy_empty_comment( string $text ): bool {
		$t = trim( $text );
		return '' !== $t && (bool) preg_match( "/^(R[ée]ponse vide\s*:\s*0 point propos[ée] automatiquement\.?|Tu n['’]as pas r[ée]pondu [àa] cette question\..*|Vous n['’]avez pas r[ée]pondu [àa] cette question\..*)$/us", $t );
	}

	public static function get_answers_map_for_attempt( int $attempt_id ): array {
		$map = array();
		foreach ( self::get_answers_for_attempt( $attempt_id ) as $answer ) {
			$map[ (int) $answer['question_id'] ] = (string) $answer['answer'];
		}
		return $map;
	}

	public static function sum_attempt_points( int $attempt_id ): float {
		$answers = self::get_answers_for_attempt( $attempt_id );
		$total   = 0.0;
		foreach ( $answers as $answer ) {
			$total += is_numeric( $answer['manual_points'] ) ? (float) $answer['manual_points'] : (float) $answer['auto_points'];
		}
		return round( $total, 2 );
	}

	private static function completed_answer_count( array $answers ): int {
		$count = 0;
		foreach ( $answers as $answer ) {
			if ( self::answer_is_complete( $answer['answer'] ?? '' ) ) {
				$count++;
			}
		}
		return $count;
	}

	private static function answer_is_complete( $value ): bool {
		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return false;
		}
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) && ( array_key_exists( 'development', $decoded ) || array_key_exists( 'final_result', $decoded ) ) ) {
			return '' !== trim( (string) ( $decoded['development'] ?? '' ) ) || '' !== trim( (string) ( $decoded['final_result'] ?? '' ) );
		}
		if ( is_array( $decoded ) ) {
			// 1.36.4 : « Relier » / « Texte à trous » enregistrent une structure même sans rien remplir.
			$filled = false;
			array_walk_recursive( $decoded, static function ( $v ) use ( &$filled ) {
				if ( ! $filled && ( is_bool( $v ) ? $v : ( null !== $v && '' !== trim( (string) $v ) ) ) ) {
					$filled = true;
				}
			} );
			return $filled;
		}
		return true;
	}

	public static function decode_ids( $json ): array {
		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? array_values( array_filter( array_map( 'absint', $decoded ) ) ) : array();
	}

		public static function default_settings(): array {
			return array(
				'post_submit_lock_minutes' => 5,
				'max_exit_attempts'        => 3,
				'fullscreen_grace_seconds' => 5,
				'unlock_request_seconds'   => 30,
				'results_visibility'       => 'teacher_only',
				'secure_mode_default'      => 1,
				'default_security_notice'  => self::hardcoded_default_security_notice(),
				'ai_grading_enabled'       => 0,
			);
		}

	/**
	 * 1.52.0 : test formatif (réglage par évaluation, enregistré dans une option WordPress : aucune table modifiée).
	 * Corrigé et noté dans LogiExam, jamais envoyé dans LogiNotes, sans bonus / malus de progression ;
	 * l'élève peut télécharger sa copie corrigée dès que la correction est finalisée.
	 */
	public static function is_formative( $evaluation ): bool {
		$id  = is_array( $evaluation ) ? (int) ( $evaluation['id'] ?? 0 ) : (int) $evaluation;
		$all = get_option( 'logiexam_formative', array() );

		return $id > 0 && is_array( $all ) && ! empty( $all[ $id ] );
	}

	public static function set_formative( int $evaluation_id, bool $formative ): void {
		$all = get_option( 'logiexam_formative', array() );
		$all = is_array( $all ) ? $all : array();
		if ( $formative ) {
			$all[ $evaluation_id ] = 1;
		} else {
			unset( $all[ $evaluation_id ] );
		}
		update_option( 'logiexam_formative', $all, true );
	}

	public static function render_formative_badge( $evaluation ): void {
		if ( self::is_formative( $evaluation ) ) {
			echo '<span class="les-formative-badge" title="' . esc_attr__( 'Test formatif : pas de correction, rien dans LogiNotes ; l’élève s’autocorrige avec le corrigé', 'logieval-secure' ) . '">' . esc_html__( 'Formatif', 'logieval-secure' ) . '</span>';
		}
	}

	public static function get_settings(): array {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
	}

		public static function get_setting( string $key, $default = null ) {
			$settings = self::get_settings();
			return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
		}
}
