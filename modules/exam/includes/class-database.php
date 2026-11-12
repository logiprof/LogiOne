<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Database {
	const OPTION_DB_VERSION = 'logieval_secure_db_version';

	public static function tables(): array {
		global $wpdb;
		$prefix = $wpdb->prefix . 'logieval_secure_';

		return array(
			'evaluations'   => $prefix . 'evaluations',
			'questions'     => $prefix . 'questions',
			'eval_questions'=> $prefix . 'evaluation_questions',
			'answers'       => $prefix . 'answers',
			'attempts'      => $prefix . 'attempts',
			'codes'         => $prefix . 'codes',
			'results'       => $prefix . 'results',
			'security_logs' => $prefix . 'security_logs',
			'exports'       => $prefix . 'exports',
		);
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta(
			"CREATE TABLE {$t['evaluations']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(190) NOT NULL,
				description LONGTEXT NULL,
				instructions LONGTEXT NULL,
				duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
				total_points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				opens_at DATETIME NULL,
				closes_at DATETIME NULL,
				sensei_course_id BIGINT UNSIGNED NULL,
				sensei_group_ids LONGTEXT NULL,
				student_user_ids LONGTEXT NULL,
				evaluation_type VARCHAR(40) NOT NULL DEFAULT 'mes_company',
				story_intro LONGTEXT NULL,
				security_notice LONGTEXT NULL,
				company_name VARCHAR(190) NOT NULL DEFAULT '',
				company_context LONGTEXT NULL,
				apprentice_role LONGTEXT NULL,
				company_logo_id BIGINT UNSIGNED NULL,
				secure_mode TINYINT(1) NOT NULL DEFAULT 1,
				is_shared TINYINT(1) NOT NULL DEFAULT 0,
				ai_strictness TINYINT(1) NOT NULL DEFAULT 3,
				shared_at DATETIME NULL,
				anti_cheat_rules LONGTEXT NULL,
				results_visibility VARCHAR(40) NOT NULL DEFAULT 'teacher_only',
				status VARCHAR(40) NOT NULL DEFAULT 'draft',
				archived_status VARCHAR(40) NULL,
				deleted_at DATETIME NULL,
				deleted_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				KEY status (status),
				KEY created_by (created_by)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['questions']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				origin VARCHAR(40) NOT NULL DEFAULT 'logieval',
				origin_id BIGINT UNSIGNED NULL,
				sensei_course_id BIGINT UNSIGNED NULL,
				title VARCHAR(190) NOT NULL,
				context LONGTEXT NULL,
				prompt LONGTEXT NOT NULL,
				type VARCHAR(40) NOT NULL DEFAULT 'long_answer',
				expected_answer LONGTEXT NULL,
				grading_criteria LONGTEXT NULL,
				options LONGTEXT NULL,
				points DECIMAL(10,2) NOT NULL DEFAULT 1.00,
				difficulty VARCHAR(30) NOT NULL DEFAULT '',
				tags LONGTEXT NULL,
				media_id BIGINT UNSIGNED NULL,
				recommended_time INT UNSIGNED NULL,
				category VARCHAR(120) NOT NULL DEFAULT '',
				usage_count INT UNSIGNED NOT NULL DEFAULT 0,
				created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				KEY origin (origin, origin_id),
				KEY type (type),
				KEY created_by (created_by)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['eval_questions']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				evaluation_id BIGINT UNSIGNED NOT NULL,
				question_id BIGINT UNSIGNED NOT NULL,
				position INT UNSIGNED NOT NULL DEFAULT 0,
				points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				PRIMARY KEY (id),
				UNIQUE KEY eval_question (evaluation_id, question_id),
				KEY evaluation_id (evaluation_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['codes']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				evaluation_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				code_hash VARCHAR(255) NOT NULL,
				code_preview VARCHAR(20) NOT NULL DEFAULT '',
				code_encrypted TEXT NULL,
				status VARCHAR(30) NOT NULL DEFAULT 'active',
				session_token VARCHAR(120) NULL,
				session_started_at DATETIME NULL,
				used_at DATETIME NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY eval_user (evaluation_id, user_id),
				KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['attempts']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				evaluation_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				code_id BIGINT UNSIGNED NOT NULL,
				status VARCHAR(40) NOT NULL DEFAULT 'not_started',
				started_at DATETIME NULL,
				submitted_at DATETIME NULL,
				last_seen_at DATETIME NULL,
				expires_at DATETIME NULL,
				lock_until DATETIME NULL,
				ip_address VARCHAR(80) NOT NULL DEFAULT '',
				user_agent TEXT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY eval_user (evaluation_id, user_id),
				KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['answers']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				attempt_id BIGINT UNSIGNED NOT NULL,
				evaluation_id BIGINT UNSIGNED NOT NULL,
				question_id BIGINT UNSIGNED NOT NULL,
				answer LONGTEXT NULL,
				auto_points DECIMAL(10,2) NULL,
				manual_points DECIMAL(10,2) NULL,
				teacher_comment LONGTEXT NULL,
				ai_points DECIMAL(10,2) NULL,
				ai_comment LONGTEXT NULL,
				ai_analysis LONGTEXT NULL,
				ai_confidence DECIMAL(4,3) NULL,
				ai_needs_review TINYINT(1) NOT NULL DEFAULT 0,
				ai_model VARCHAR(120) NOT NULL DEFAULT '',
				ai_graded_at DATETIME NULL,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY attempt_question (attempt_id, question_id),
				KEY evaluation_id (evaluation_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['results']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				attempt_id BIGINT UNSIGNED NOT NULL,
				evaluation_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				points_obtained DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				bonus_points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				malus_points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				final_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				final_grade DECIMAL(4,2) NULL,
				status VARCHAR(40) NOT NULL DEFAULT 'to_grade',
				teacher_comment LONGTEXT NULL,
				corrected_by BIGINT UNSIGNED NULL,
				corrected_at DATETIME NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY attempt_id (attempt_id),
				KEY evaluation_id (evaluation_id),
				KEY user_id (user_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['security_logs']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				attempt_id BIGINT UNSIGNED NULL,
				evaluation_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				event_type VARCHAR(80) NOT NULL,
				event_data LONGTEXT NULL,
				severity VARCHAR(20) NOT NULL DEFAULT 'info',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY attempt_id (attempt_id),
				KEY evaluation_id (evaluation_id),
				KEY event_type (event_type)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['exports']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				evaluation_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NULL,
				format VARCHAR(20) NOT NULL DEFAULT 'csv',
				file_url TEXT NULL,
				created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY evaluation_id (evaluation_id)
			) $charset;"
		);

		self::migrate_multi_teacher();
		update_option( self::OPTION_DB_VERSION, LOGIEVAL_SECURE_VERSION );
	}

	/**
	 * Passage au mode multi-enseignants (une seule fois) :
	 * - les évaluations et questions sans auteur sont attribuées au premier administrateur ;
	 * - l'ancienne clé OpenAI globale devient la clé personnelle de ce même administrateur.
	 */
	private static function migrate_multi_teacher(): void {
		if ( get_option( 'logieval_secure_multi_teacher_migrated' ) ) {
			return;
		}
		global $wpdb;
		$t      = self::tables();
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
				'fields'  => 'ID',
			)
		);
		$owner  = (int) ( $admins[0] ?? 0 );
		if ( $owner ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$t['evaluations']} SET created_by=%d WHERE created_by=0", $owner ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$t['questions']} SET created_by=%d WHERE created_by=0", $owner ) );

			$settings = get_option( 'logieval_secure_settings', array() );
			if ( is_array( $settings ) && ! empty( $settings['openai_api_key'] ) && class_exists( 'LogiEval_Secure_AI' ) ) {
				$existing = LogiEval_Secure_AI::get_user_settings( $owner );
				if ( '' === (string) $existing['api_key'] ) {
					LogiEval_Secure_AI::save_user_settings(
						$owner,
						array(
							'enabled'  => ! empty( $settings['ai_grading_enabled'] ) ? 1 : 0,
							'provider' => 'openai',
							'model'    => (string) ( $settings['openai_model'] ?? '' ),
							'api_key'  => (string) $settings['openai_api_key'],
						)
					);
				}
				unset( $settings['openai_api_key'], $settings['openai_model'] );
				update_option( 'logieval_secure_settings', $settings, false );
			}
		}
		update_option( 'logieval_secure_multi_teacher_migrated', 1, false );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION_DB_VERSION ) !== LOGIEVAL_SECURE_VERSION ) {
			self::install();
		}
	}

	public static function deactivate(): void {
		// Les données sont archivées durablement. Aucune suppression à la désactivation.
	}
}
