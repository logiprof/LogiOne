<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Les méthodes de la classe sont réparties par thème dans includes/evaluations/.
foreach ( array( 'access', 'data', 'editor', 'monitoring', 'student-session', 'corrections', 'ai-grading', 'pdf-exports', 'evaluation-export', 'evaluation-paper', 'workspace', 'batch', 'teacher-control', 'generator', 'ai-prompts', 'accommodations' ) as $logieval_part ) {
	require_once __DIR__ . '/evaluations/trait-' . $logieval_part . '.php';
}
unset( $logieval_part );

	final class LogiEval_Secure_Evaluations {
		const NONCE_ACTION = 'logieval_secure_action';
		const ADMIN_PAGE   = 'logiexam';
		const SETTINGS_OPTION = 'logieval_secure_settings';
		/** Note attribuée à une évaluation annulée pour tricherie. */
		public const CHEATING_GRADE = 1.0;

	use LogiEval_Secure_Evaluations_Student_Session;
	use LogiEval_Secure_Evaluations_Pdf_Exports;
	use LogiEval_Secure_Evaluations_Ai_Grading;
	use LogiEval_Secure_Evaluations_Monitoring;
	use LogiEval_Secure_Evaluations_Access;
	use LogiEval_Secure_Evaluations_Corrections;
	use LogiEval_Secure_Evaluations_Editor;
	use LogiEval_Secure_Evaluations_Data;
	use LogiEval_Secure_Evaluations_Workspace;
	use LogiEval_Secure_Evaluations_Batch;
	use LogiEval_Secure_Evaluations_Evaluation_Export;
	use LogiEval_Secure_Evaluations_Evaluation_Paper;
	use LogiEval_Secure_Evaluations_Teacher_Control;
	use LogiEval_Secure_Evaluations_Generator;
	use LogiEval_Secure_Evaluations_Ai_Prompts;
	use LogiEval_Secure_Evaluations_Accommodations;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'init', array( __CLASS__, 'serve_private_image' ), 1 );
		add_filter( 'plugin_action_links_' . LOGIEVAL_SECURE_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
		add_action( 'admin_post_logieval_secure_save_evaluation', array( __CLASS__, 'handle_save_evaluation' ) );
		add_action( 'admin_post_logieval_secure_save_question', array( __CLASS__, 'handle_save_question' ) );
		add_action( 'admin_post_logieval_secure_delete_questions', array( __CLASS__, 'handle_delete_questions' ) );
		add_action( 'admin_post_logieval_secure_import_sensei_question', array( __CLASS__, 'handle_import_sensei_question' ) );
		add_action( 'admin_post_logieval_secure_duplicate_evaluation', array( __CLASS__, 'handle_duplicate_evaluation' ) );
		add_action( 'admin_post_logieval_secure_delete_evaluation', array( __CLASS__, 'handle_delete_evaluation' ) );
		add_action( 'admin_post_logieval_secure_restore_evaluation', array( __CLASS__, 'handle_restore_evaluation' ) );
		add_action( 'admin_post_logieval_secure_generate_codes', array( __CLASS__, 'handle_generate_codes' ) );
		add_action( 'admin_post_logieval_secure_export_codes_pdf', array( __CLASS__, 'handle_export_codes_pdf' ) );
		add_action( 'admin_post_logieval_secure_export_attempt_pdf', array( __CLASS__, 'handle_export_attempt_pdf' ) );
		add_action( 'admin_post_logieval_secure_export_evaluation', array( __CLASS__, 'handle_export_evaluation' ) );
		add_action( 'admin_post_logieval_secure_grade_attempt', array( __CLASS__, 'handle_grade_attempt' ) );
		add_action( 'admin_post_logieval_secure_save_question_corrections', array( __CLASS__, 'handle_save_question_corrections' ) );
		add_action( 'admin_post_logieval_secure_unlock_attempt', array( __CLASS__, 'handle_unlock_attempt' ) );
		add_action( 'admin_post_logieval_secure_attempt_control', array( __CLASS__, 'handle_attempt_control' ) );
		add_action( 'admin_post_logieval_secure_gen_save', array( __CLASS__, 'handle_gen_save' ) );
		add_action( 'wp_ajax_logieval_secure_gen_questions', array( __CLASS__, 'ajax_gen_questions' ) );
		add_action( 'wp_ajax_logieval_secure_gen_lessons', array( __CLASS__, 'ajax_gen_lessons' ) );
		add_action( 'wp_ajax_logieval_secure_gen_source', array( __CLASS__, 'ajax_gen_source' ) );
		add_action( 'wp_ajax_logieval_secure_time_status', array( __CLASS__, 'ajax_time_status' ) );
		add_action( 'admin_post_logieval_secure_reopen_correction', array( __CLASS__, 'handle_reopen_correction' ) );
		add_action( 'admin_post_logieval_secure_finalize_all', array( __CLASS__, 'handle_finalize_all' ) );
		add_action( 'admin_post_logieval_secure_export_pdf_zip', array( __CLASS__, 'handle_export_pdf_zip' ) );
		add_action( 'wp_ajax_logieval_secure_set_ai_strictness', array( __CLASS__, 'ajax_set_ai_strictness' ) );
		add_action( 'wp_ajax_logieval_secure_set_expected_answer', array( __CLASS__, 'ajax_set_expected_answer' ) );
		add_action( 'admin_post_logieval_secure_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_logieval_secure_save_accommodations', array( __CLASS__, 'handle_save_accommodations' ) );
		add_action( 'admin_post_logieval_secure_save_ai_settings', array( __CLASS__, 'handle_save_ai_settings' ) );
		add_action( 'admin_post_logieval_secure_toggle_share', array( __CLASS__, 'handle_toggle_share' ) );
		add_action( 'admin_post_logieval_secure_toggle_library', array( __CLASS__, 'handle_toggle_library' ) );
		add_action( 'wp_ajax_logieval_secure_toggle_favorite_group', array( __CLASS__, 'ajax_toggle_favorite_group' ) );
		add_action( 'admin_post_logieval_secure_copy_shared', array( __CLASS__, 'handle_duplicate_evaluation' ) );
		add_action( 'wp_ajax_logieval_secure_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_logieval_secure_begin_questions', array( __CLASS__, 'ajax_begin_questions' ) );
		add_action( 'wp_ajax_logieval_secure_unlock_deadline', array( __CLASS__, 'ajax_unlock_deadline' ) );
		add_action( 'wp_ajax_logieval_secure_save_answer', array( __CLASS__, 'ajax_save_answer' ) );
			add_action( 'wp_ajax_logieval_secure_submit', array( __CLASS__, 'ajax_submit' ) );
			add_action( 'wp_ajax_logieval_secure_expire', array( __CLASS__, 'ajax_expire' ) );
			add_action( 'wp_ajax_logieval_secure_reopen', array( __CLASS__, 'ajax_reopen' ) );
			add_action( 'wp_ajax_logieval_secure_force_security_block', array( __CLASS__, 'ajax_force_security_block' ) );
			add_action( 'wp_ajax_logieval_secure_unlock_status', array( __CLASS__, 'ajax_unlock_status' ) );
			add_action( 'wp_ajax_logieval_secure_resume_after_unlock', array( __CLASS__, 'ajax_resume_after_unlock' ) );
			add_action( 'wp_ajax_logieval_secure_finalize_lock', array( __CLASS__, 'ajax_finalize_lock' ) );
			add_action( 'wp_ajax_logieval_secure_monitoring_snapshot', array( __CLASS__, 'ajax_monitoring_snapshot' ) );
			add_action( 'wp_ajax_logieval_secure_teacher_unlock', array( __CLASS__, 'ajax_teacher_unlock' ) );
			add_action( 'wp_ajax_logieval_secure_ai_grade_answer', array( __CLASS__, 'ajax_ai_grade_answer' ) );
			add_action( 'wp_ajax_logieval_secure_ai_grade_comment', array( __CLASS__, 'ajax_ai_regenerate_comment' ) );
			add_action( 'wp_ajax_logieval_secure_ai_grade_compare', array( __CLASS__, 'ajax_ai_grade_compare' ) );
			add_action( 'wp_ajax_logieval_secure_ai_grade_keep', array( __CLASS__, 'ajax_ai_grade_keep' ) );
			add_action( 'wp_ajax_logieval_secure_ai_compare_vote', array( __CLASS__, 'ajax_ai_compare_vote' ) );
			add_action( 'wp_ajax_logieval_secure_ai_grade_bulk', array( __CLASS__, 'ajax_ai_grade_bulk' ) );
		}

	public static function register_admin_page(): void {
		add_menu_page(
			__( 'LogiExam', 'logieval-secure' ),
			__( 'LogiExam', 'logieval-secure' ),
			LogiEval_Secure_Ultimate_Member::ACCESS_CAP,
			self::ADMIN_PAGE,
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-welcome-learn-more',
			6
		);

		// Menu visible : 4 entrées seulement.
		$visible = array(
			self::ADMIN_PAGE    => __( 'Mes évaluations', 'logieval-secure' ),
			'logiexam-library'   => __( 'Bibliothèque', 'logieval-secure' ),
			'logiexam-questions' => __( 'Banque de questions', 'logieval-secure' ),
			'logiexam-generator' => __( 'Créer avec l’IA', 'logieval-secure' ),
			'logiexam-accommodations' => __( 'Aménagements', 'logieval-secure' ),
			'logiexam-settings'  => __( 'Réglages', 'logieval-secure' ),
		);
		foreach ( $visible as $slug => $title ) {
			add_submenu_page( self::ADMIN_PAGE, $title, $title, LogiEval_Secure_Ultimate_Member::ACCESS_CAP, $slug, array( __CLASS__, 'render_admin_page' ) );
		}

		// Pages cachées : page d'une évaluation, création, et anciennes adresses (redirigées vers la bonne étape).
		foreach ( array( 'logiexam-evaluation', 'logiexam-create', 'logiexam-evaluations', 'logiexam-codes', 'logiexam-corrections', 'logiexam-monitoring', 'logiexam-security', 'logiexam-exports', 'logieval-secure' ) as $slug ) {
			$hook = add_submenu_page( '', __( 'LogiExam', 'logieval-secure' ), __( 'LogiExam', 'logieval-secure' ), LogiEval_Secure_Ultimate_Member::ACCESS_CAP, $slug, array( __CLASS__, 'render_admin_page' ) );
			if ( $hook ) {
				// Les pages cachées n'ont pas de titre : on en donne un pour l'onglet du navigateur.
				add_action( 'load-' . $hook, static function () {
					$GLOBALS['title'] = __( 'LogiExam', 'logieval-secure' );
				} );
			}
		}
	}

	public static function plugin_action_links( array $links ): array {
		if ( LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::ADMIN_PAGE ) ) . '">' . esc_html__( 'Ouvrir LogiExam', 'logieval-secure' ) . '</a>' );
		}
		$links[] = '<span>' . esc_html__( 'Shortcodes : [logiexam_teacher] / [logiexam_student]', 'logieval-secure' ) . '</span>';
		return $links;
	}

	public static function render_admin_page(): void {
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ) );
		}
		wp_enqueue_style( 'logieval-secure', LOGIEVAL_SECURE_URL . 'assets/logieval-secure.css', array(), LOGIEVAL_SECURE_ASSETS_VERSION );
		wp_enqueue_script( 'logieval-secure', LOGIEVAL_SECURE_URL . 'assets/logieval-secure.js', array(), LOGIEVAL_SECURE_ASSETS_VERSION, true );
		if ( current_user_can( 'upload_files' ) ) {
			wp_enqueue_media(); // « 📁 Choisir dans la médiathèque » pour les images des questions
		}
		wp_localize_script(
			'logieval-secure',
			'LogiEvalSecure',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'saved'     => __( 'Réponse enregistrée.', 'logieval-secure' ),
					'submitted' => __( 'Votre évaluation a bien été envoyée et sera corrigée par votre enseignant.', 'logieval-secure' ),
					'error'     => __( 'Une erreur est survenue.', 'logieval-secure' ),
				),
			)
		);
		?>
		<div class="wrap logieval-secure" data-les-teacher>
			<h1><?php esc_html_e( 'LogiExam', 'logieval-secure' ); ?></h1>
			<?php self::render_teacher_app(); ?>
		</div>
		<?php
	}

	public static function render_frontend_teacher_app(): string {
		ob_start();
		?>
		<div class="logieval-secure les-teacher les-app-shell" data-les-teacher>
			<header class="les-hero">
				<div>
					<span class="les-kicker"><?php esc_html_e( 'LogiExam', 'logieval-secure' ); ?></span>
					<h2><?php esc_html_e( 'Espace enseignant', 'logieval-secure' ); ?></h2>
					<p><?php esc_html_e( 'Préparez, surveillez, corrigez et exportez vos évaluations depuis une seule page par évaluation.', 'logieval-secure' ); ?></p>
				</div>
			</header>
			<?php self::render_teacher_app(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function frontend_url( array $args = array() ): string {
		$url = remove_query_arg( array( 'saved', 'question_saved', 'imported', 'graded', 'duplicated', 'deleted', 'restored', 'codes', 'correction_incomplete', 'incomplete_attempt', 'reopened', 'open_attempt', 'new_codes', 'share_updated', 'library_updated', 'copied', 'settings_saved', 'unlocked', 'finalized', 'not_finalized' ) );
		foreach ( $args as $key => $value ) {
			if ( null === $value || '' === $value ) {
				$url = remove_query_arg( $key, $url );
			} else {
				$url = add_query_arg( $key, $value, $url );
			}
		}
		return $url;
	}

	private static function frontend_redirect_url( array $args = array() ): string {
		return esc_url_raw( self::frontend_url( $args ) );
	}

	/** Dans wp-admin : mémorise l'écran courant pour y revenir après l'envoi du formulaire. */
	private static function return_field(): void {
		if ( ! is_admin() ) {
			return;
		}
		?>
		<input type="hidden" name="logiexam_return" value="<?php echo esc_attr( esc_url_raw( self::frontend_url() ) ); ?>">
		<?php
	}

	private static function frontend_redirect_field( array $args = array() ): void {
		if ( is_admin() ) {
			return;
		}
		?>
		<input type="hidden" name="logiexam_frontend_redirect" value="<?php echo esc_attr( self::frontend_redirect_url( $args ) ); ?>">
		<?php
	}

	private static function expected_answer_values( $expected ): array {
		if ( is_array( $expected ) ) {
			return array_values( array_filter( array_map( 'strval', $expected ), 'strlen' ) );
		}

		$expected = trim( (string) $expected );
		if ( '' === $expected ) {
			return array();
		}

		if ( '[' === substr( $expected, 0, 1 ) ) {
			$decoded = json_decode( $expected, true );
			if ( is_array( $decoded ) ) {
				return array_values( array_filter( array_map( 'strval', $decoded ), 'strlen' ) );
			}
		}

		return array_values( array_filter( array_map( 'trim', preg_split( '/\R|;/', $expected ) ), 'strlen' ) );
	}

		private static function group_name_by_id( int $group_id ): string {
		foreach ( LogiEval_Secure_Sensei::get_groups() as $group ) {
			if ( (int) ( $group->term_id ?? 0 ) === $group_id ) {
				return (string) $group->name;
			}
		}
		$title = get_the_title( $group_id );
		return $title ?: ( $group_id ? sprintf( __( 'Groupe %d', 'logieval-secure' ), $group_id ) : '' );
	}

	private static function attempt_status_label( string $attempt_status, string $result_status = '' ): string {
		$labels = array(
			'in_progress'         => __( 'En cours', 'logieval-secure' ),
			'submitted_locked'    => __( 'Soumis - verrouillage 5 min', 'logieval-secure' ),
			'submitted'           => __( 'Soumis', 'logieval-secure' ),
			'graded'              => __( 'Corrigé', 'logieval-secure' ),
			'locked_exit_attempt' => __( 'Sortie suspecte', 'logieval-secure' ),
			'security_paused'     => __( 'Bloqué - validation enseignant requise', 'logieval-secure' ),
			'security_terminated' => __( 'Annulée – tricherie (ne pas corriger)', 'logieval-secure' ),
		);
		if ( 'security_terminated' === $attempt_status ) {
			return $labels['security_terminated'];
		}
		if ( 'suspected_cheating' === $result_status ) {
			return __( 'Triche suspectée', 'logieval-secure' );
		}
		if ( 'correction_in_progress' === $result_status ) {
			return __( 'Correction en cours', 'logieval-secure' );
		}
		return $labels[ $attempt_status ] ?? $attempt_status;
	}

	private static function format_points( $points ): string {
		return number_format( (float) $points, 2, '.', '' );
	}

	private static function parse_decimal( $value ): float {
		return (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( (string) $value ) ) );
	}

	private static function uncorrected_answer_count( int $attempt_id ): int {
		$count = 0;
		foreach ( self::get_answers_for_attempt( $attempt_id ) as $answer ) {
			if ( self::answer_is_complete( $answer['answer'] ?? '' ) && ! self::answer_has_correction_points( $answer ) ) {
				$count++;
			}
		}
		return $count;
	}

	private static function ensure_zero_for_empty_answers( int $attempt_id ): void {
		$attempt = self::get_attempt( $attempt_id );
		if ( ! $attempt ) {
			return;
		}
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		foreach ( self::get_answers_for_attempt( $attempt_id ) as $answer ) {
			if ( self::answer_is_complete( $answer['answer'] ?? '' ) || is_numeric( $answer['manual_points'] ?? null ) ) {
				continue;
			}
			$question_id = (int) $answer['question_id'];
			$answer_data = array(
				'manual_points' => 0,
				'updated_at'    => current_time( 'mysql' ),
			);
			if ( '' === trim( (string) ( $answer['teacher_comment'] ?? '' ) ) ) {
				$answer_data['teacher_comment'] = self::empty_answer_comment_text();
			}
			if ( ! empty( $answer['id'] ) ) {
				$wpdb->update( $t['answers'], $answer_data, array( 'id' => (int) $answer['id'] ) );
			} else {
				$answer_data['evaluation_id'] = (int) $attempt['evaluation_id'];
				$answer_data['attempt_id']    = $attempt_id;
				$answer_data['question_id']   = $question_id;
				$answer_data['answer']        = '';
				$answer_data['auto_points']   = null;
				// 1.36.4 : la table des réponses n'a pas de colonne created_at (l'insertion échouait).
				$wpdb->insert( $t['answers'], $answer_data );
			}
		}
	}

	private static function attempt_effective_duration( array $attempt ): string {
		if ( empty( $attempt['started_at'] ) ) {
			return __( 'Non démarré', 'logieval-secure' );
		}
		$start = self::local_datetime_timestamp( (string) $attempt['started_at'] );
		$end   = ! empty( $attempt['submitted_at'] ) ? self::local_datetime_timestamp( (string) $attempt['submitted_at'] ) : self::local_datetime_timestamp( (string) ( $attempt['last_seen_at'] ?? '' ) );
		if ( ! $start || ! $end || $end < $start ) {
			return __( 'Non renseigné', 'logieval-secure' );
		}
		$seconds = max( 0, ( $end - $start ) - self::attempt_security_pause_seconds( (int) $attempt['id'], $end ) );
		return self::format_duration_seconds( $seconds );
	}

	private static function attempt_remaining_duration( array $attempt ): string {
		if ( empty( $attempt['submitted_at'] ) ) {
			return __( 'Non remis', 'logieval-secure' );
		}
		$submitted = self::local_datetime_timestamp( (string) $attempt['submitted_at'] );
		$expires   = ! empty( $attempt['expires_at'] ) ? self::utc_datetime_timestamp( (string) $attempt['expires_at'] ) : 0;
		if ( ! $submitted || ! $expires || $expires <= $submitted ) {
			return '00:00:00';
		}
		return self::format_duration_seconds( $expires - $submitted );
	}

	private static function attempt_date_label( array $attempt ): string {
		$date = ! empty( $attempt['started_at'] ) ? (string) $attempt['started_at'] : (string) ( $attempt['submitted_at'] ?? '' );
		if ( '' === trim( $date ) ) {
			return __( 'Non renseignée', 'logieval-secure' );
		}
		$timestamp = self::local_datetime_timestamp( $date );
		return $timestamp ? wp_date( 'd.m.Y', $timestamp, self::pdf_timezone() ) : __( 'Non renseignée', 'logieval-secure' );
	}

	private static function format_duration_seconds( int $seconds ): string {
		$seconds = max( 0, $seconds );
		return sprintf( '%02d:%02d:%02d', floor( $seconds / HOUR_IN_SECONDS ), floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ), $seconds % MINUTE_IN_SECONDS );
	}

	private static function readable_answer( $value ): string {
		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return __( 'Non répondu', 'logieval-secure' );
		}
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			$special = LogiEval_Secure_Question_Types::readable( $decoded );
			if ( null !== $special ) {
				return $special;
			}
			if ( array_key_exists( 'development', $decoded ) || array_key_exists( 'final_result', $decoded ) ) {
				$development = trim( (string) ( $decoded['development'] ?? '' ) );
				$result      = trim( (string) ( $decoded['final_result'] ?? '' ) );
				$parts       = array();
				if ( '' !== $development ) {
					$parts[] = __( 'Développement', 'logieval-secure' ) . " :\n" . $development;
				} elseif ( '' !== $result ) {
					// Dit explicitement qu'il n'y a pas de développement (correction et pré-correction IA).
					$parts[] = __( 'Développement : (aucun développement écrit)', 'logieval-secure' );
				}
				if ( '' !== $result ) {
					$parts[] = __( 'Résultat final', 'logieval-secure' ) . ' : ' . $result;
				}
				$precision_label = LogiEval_Secure_Question_Types::precision_label( LogiEval_Secure_Question_Types::calculation_precision( $decoded ) );
				if ( '' !== $precision_label ) {
					$parts[] = __( 'Précision demandée', 'logieval-secure' ) . ' : ' . $precision_label;
				}
				return empty( $parts ) ? __( 'Non répondu', 'logieval-secure' ) : implode( "\n\n", $parts );
			}
			return implode( ', ', array_map( 'strval', $decoded ) );
		}
		return $value;
	}

	private static function evaluation_module_name( array $evaluation ): string {
		$course_id = absint( $evaluation['sensei_course_id'] ?? 0 );
		if ( $course_id ) {
			$title = get_the_title( $course_id );
			if ( $title ) {
				return $title;
			}
		}
		return ! empty( $evaluation['title'] ) ? (string) $evaluation['title'] : __( 'Non renseigné', 'logieval-secure' );
	}

	private static function evaluation_course_name( array $evaluation ): string {
		$course_id = absint( $evaluation['sensei_course_id'] ?? 0 );
		if ( ! $course_id ) {
			return '';
		}

		$title = get_the_title( $course_id );
		return $title ? (string) $title : '';
	}

	private static function evaluation_group_names( array $evaluation ): string {
		$group_ids = self::decode_ids( $evaluation['sensei_group_ids'] ?? '' );
		if ( empty( $group_ids ) ) {
			return __( 'Élèves spécifiques / aucune classe', 'logieval-secure' );
		}

		$known_groups = LogiEval_Secure_Sensei::get_groups();
		$names = array();
		foreach ( $group_ids as $group_id ) {
			$name = '';
			foreach ( $known_groups as $group ) {
				if ( (int) ( $group->term_id ?? 0 ) === (int) $group_id ) {
					$name = (string) $group->name;
					break;
				}
			}
			if ( '' === $name ) {
				$title = get_the_title( $group_id );
				$name = $title ?: sprintf( __( 'Groupe %d', 'logieval-secure' ), $group_id );
			}
			$names[] = $name;
		}

		return implode( ', ', $names );
	}

	private static function student_full_name( int $user_id ): string {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '#' . $user_id;
		}

		$first_name = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
		$last_name  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
		$full_name  = trim( $first_name . ' ' . $last_name );
		return $full_name ?: (string) $user->display_name;
	}

	private static function group_label_for_user( array $evaluation, int $user_id ): string {
		$group_ids = self::decode_ids( $evaluation['sensei_group_ids'] ?? '' );
		if ( empty( $group_ids ) ) {
			return __( 'Non renseigné', 'logieval-secure' );
		}

		$known_groups = LogiEval_Secure_Sensei::get_groups();
		foreach ( $group_ids as $group_id ) {
			foreach ( LogiEval_Secure_Sensei::get_group_students( $group_id ) as $student ) {
				if ( (int) $student->ID !== $user_id ) {
					continue;
				}
				foreach ( $known_groups as $group ) {
					if ( (int) ( $group->term_id ?? 0 ) === (int) $group_id ) {
						return (string) $group->name;
					}
				}
				$title = get_the_title( $group_id );
				return $title ?: sprintf( __( 'Groupe %d', 'logieval-secure' ), $group_id );
			}
		}

		return __( 'Non renseigné', 'logieval-secure' );
	}

	private static function local_datetime_timestamp( string $mysql_datetime ): int {
		$mysql_datetime = trim( $mysql_datetime );
		if ( '' === $mysql_datetime ) {
			return 0;
		}
		// Heures « locales » enregistrées avec current_time() : fuseau du site WordPress (affichage ensuite en heure de Zurich).
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : self::pdf_timezone();
		$date     = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql_datetime, $timezone );
		if ( false === $date ) {
			$date = date_create_immutable( $mysql_datetime, $timezone );
		}
		return $date ? $date->getTimestamp() : 0;
	}

	private static function utc_datetime_timestamp( string $mysql_datetime ): int {
		$mysql_datetime = trim( $mysql_datetime );
		if ( '' === $mysql_datetime ) {
			return 0;
		}
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql_datetime, new DateTimeZone( 'UTC' ) );
		if ( false === $date ) {
			$date = date_create_immutable( $mysql_datetime, new DateTimeZone( 'UTC' ) );
		}
		return $date ? $date->getTimestamp() : 0;
	}

	private static function require_teacher_post(): void {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ) );
		}
	}

	private static function post_redirect_url( string $fallback, array $args = array() ): string {
		$front = isset( $_POST['logiexam_frontend_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['logiexam_frontend_redirect'] ) ) : '';
		if ( ! $front && ! empty( $_POST['logiexam_return'] ) ) {
			// Retour à l'écran d'origine dans wp-admin (ex. page d'une évaluation, élève affiché).
			$front = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['logiexam_return'] ) ), '' );
		}
		if ( $front ) {
			foreach ( $args as $key => $value ) {
				if ( null === $value || '' === $value ) {
					$front = remove_query_arg( $key, $front );
				} else {
					$front = add_query_arg( $key, $value, $front );
				}
			}
			return $front;
		}
		return $fallback;
	}

	private static function mysql_datetime_from_local( $value ): ?string {
		$value = sanitize_text_field( wp_unslash( $value ) );
		if ( empty( $value ) ) {
			return null;
		}
		return date( 'Y-m-d H:i:s', strtotime( $value ) );
	}

	private static function datetime_local_value( $mysql ): string {
		return $mysql ? date( 'Y-m-d\TH:i', strtotime( $mysql ) ) : '';
	}
}

