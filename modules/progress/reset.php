<?php
/**
 * LogiProgress – réinitialiser l'avancement d'un élève (LogiOne 1.26.0).
 *
 * Depuis le détail d'un élève : « Réinitialiser le cours » (toutes les leçons du cours) ou « Réinitialiser » une leçon.
 * Les fonctions de Sensei sont utilisées quand elles existent (Sensei_Utils) : Sensei efface lui-même l'avancement
 * et les réponses aux quiz, comme depuis ses propres réglages. Sinon, les statuts de leçon Sensei de l'élève sont
 * supprimés directement. Une copie de ce qui est effacé est gardée dans le journal LogiOne.
 * Réservé à la permission « Réinitialiser l'avancement d'un élève » (lp_progress_reset).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiProgress_Reset {

	public static function can_reset_progress() {
		return is_user_logged_in() && current_user_can( 'lp_progress_view' ) && current_user_can( 'lp_progress_reset' );
	}

	/** Formulaire d'un bouton de réinitialisation : une leçon, un module Sensei ($module), ou le cours entier. */
	private static function render_reset_button( $student, $course, $lesson, $group_id, $module = null ) {
		if ( ! self::can_reset_progress() ) {
			return;
		}
		$name    = $student->display_name;
		$confirm = $lesson
			/* translators: 1: leçon, 2: élève */
			? sprintf( __( 'Réinitialiser la leçon « %1$s » pour %2$s ?\n\nSa progression et ses réponses au quiz de cette leçon seront effacées dans Sensei. L’élève en sera informé par e-mail. Cette action ne peut pas être annulée.', 'pickles-sensei-group-progress-tracker' ), $lesson->post_title, $name )
			/* translators: 1: cours, 2: élève */
			: ( $module
				/* translators: 1: module, 2: cours, 3: élève */
				? sprintf( __( 'Réinitialiser le module « %1$s » du cours « %2$s » pour %3$s ?\n\nLa progression et les réponses aux quiz de toutes les leçons de ce module seront effacées dans Sensei. Le reste du cours n’est pas touché. L’élève en sera informé par e-mail. Cette action ne peut pas être annulée.', 'pickles-sensei-group-progress-tracker' ), $module->name, $course->post_title, $name )
				: sprintf( __( 'Réinitialiser tout le cours « %1$s » pour %2$s ?\n\nLa progression et les réponses aux quiz de toutes les leçons de ce cours seront effacées dans Sensei. L’élève reste inscrit au cours et en sera informé par e-mail. Cette action ne peut pas être annulée.', 'pickles-sensei-group-progress-tracker' ), $course->post_title, $name ) );
		$back = remove_query_arg( array( 'psgpt_reset', 'psgpt_reset_user', 'psgpt_reset_item', 'psgpt_reset_mail', 'psgpt_reset_module' ), self::ui_current_url() );
		?>
		<form class="psgpt-reset-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-psgpt-confirm="<?php echo esc_attr( $confirm ); ?>">
			<input type="hidden" name="action" value="psgpt_reset_progress">
			<?php wp_nonce_field( 'psgpt_reset_progress', 'psgpt_reset_nonce' ); ?>
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $student->ID ); ?>">
			<input type="hidden" name="course_id" value="<?php echo esc_attr( $course->ID ); ?>">
			<input type="hidden" name="lesson_id" value="<?php echo esc_attr( $lesson ? $lesson->ID : 0 ); ?>">
			<input type="hidden" name="module_id" value="<?php echo esc_attr( $module ? $module->term_id : 0 ); ?>">
			<input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>">
			<input type="hidden" name="back" value="<?php echo esc_attr( $back ); ?>">
			<?php if ( $lesson ) : ?>
				<button type="submit" class="psgpt-reset-btn is-lesson" title="<?php echo esc_attr( sprintf( __( 'Réinitialiser la leçon « %s » pour cet élève', 'pickles-sensei-group-progress-tracker' ), $lesson->post_title ) ); ?>">↺ <span><?php esc_html_e( 'Réinitialiser', 'pickles-sensei-group-progress-tracker' ); ?></span></button>
			<?php elseif ( $module ) : ?>
				<button type="submit" class="psgpt-reset-btn is-module">↺ <?php esc_html_e( 'Réinitialiser le module', 'pickles-sensei-group-progress-tracker' ); ?></button>
			<?php else : ?>
				<button type="submit" class="psgpt-reset-btn is-course">↺ <?php esc_html_e( 'Réinitialiser le cours', 'pickles-sensei-group-progress-tracker' ); ?></button>
			<?php endif; ?>
		</form>
		<?php
	}

	/** Message après une réinitialisation. */
	private static function render_reset_notice() {
		if ( empty( $_GET['psgpt_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$kind = sanitize_key( wp_unslash( $_GET['psgpt_reset'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user = get_userdata( absint( $_GET['psgpt_reset_user'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$item = get_post( absint( $_GET['psgpt_reset_item'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'error' === $kind || ! $user || ! $item ) {
			echo '<div class="psgpt-notice is-error" role="alert">' . esc_html__( 'La réinitialisation n’a pas pu être faite (élève, cours ou leçon introuvable, ou droit manquant).', 'pickles-sensei-group-progress-tracker' ) . '</div>';
			return;
		}
		$module = 'module' === $kind ? get_term( absint( $_GET['psgpt_reset_module'] ?? 0 ), 'module' ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'lesson' === $kind ) {
			/* translators: 1: leçon, 2: élève */
			$text = sprintf( __( 'Leçon « %1$s » réinitialisée pour %2$s.', 'pickles-sensei-group-progress-tracker' ), $item->post_title, $user->display_name );
		} elseif ( $module && ! is_wp_error( $module ) ) {
			/* translators: 1: module, 2: cours, 3: élève */
			$text = sprintf( __( 'Module « %1$s » du cours « %2$s » réinitialisé pour %3$s.', 'pickles-sensei-group-progress-tracker' ), $module->name, $item->post_title, $user->display_name );
		} else {
			/* translators: 1: cours, 2: élève */
			$text = sprintf( __( 'Cours « %1$s » réinitialisé pour %2$s.', 'pickles-sensei-group-progress-tracker' ), $item->post_title, $user->display_name );
		}
		$mail = sanitize_key( wp_unslash( $_GET['psgpt_reset_mail'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sent' === $mail ) {
			/* translators: %s: adresse e-mail */
			$text .= ' ' . sprintf( __( 'L’élève en a été informé par e-mail (%s).', 'pickles-sensei-group-progress-tracker' ), $user->user_email );
		} elseif ( 'noaddress' === $mail ) {
			$text .= ' ' . __( 'Aucune adresse e-mail dans son profil : l’élève n’a pas été informé.', 'pickles-sensei-group-progress-tracker' );
		} elseif ( 'failed' === $mail ) {
			$text .= ' ' . __( 'L’e-mail à l’élève n’a pas pu être envoyé.', 'pickles-sensei-group-progress-tracker' );
		}
		echo '<div class="psgpt-notice is-ok" role="status">' . esc_html( $text ) . '</div>';
	}

	public static function handle_reset_progress() {
		$back = isset( $_POST['back'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ) ), admin_url( 'admin.php?page=logiprogress' ) ) : admin_url( 'admin.php?page=logiprogress' );
		if ( ! isset( $_POST['psgpt_reset_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['psgpt_reset_nonce'] ) ), 'psgpt_reset_progress' ) || ! self::can_reset_progress() ) {
			wp_die( esc_html__( 'Action non autorisée.', 'pickles-sensei-group-progress-tracker' ), 403 );
		}
		$user_id   = absint( $_POST['user_id'] ?? 0 );
		$course_id = absint( $_POST['course_id'] ?? 0 );
		$lesson_id = absint( $_POST['lesson_id'] ?? 0 );
		$module_id = $lesson_id ? 0 : absint( $_POST['module_id'] ?? 0 );
		$group_id  = absint( $_POST['group_id'] ?? 0 );
		$course    = get_post( $course_id );
		$lessons   = $course && 'course' === $course->post_type ? self::reset_all_course_lesson_ids( $course_id ) : array();
		$valid     = $user_id && get_userdata( $user_id ) && $course && $group_id && in_array( $user_id, self::get_group_student_ids( $group_id ), true )
			&& ( ! $lesson_id || in_array( $lesson_id, array_map( 'absint', $lessons ), true ) );
		$module      = null;
		$module_less = array();
		if ( $valid && $module_id ) {
			$module      = taxonomy_exists( 'module' ) ? get_term( $module_id, 'module' ) : null;
			$module_less = $module && ! is_wp_error( $module ) ? array_keys( array_filter( self::lesson_module_map( $lessons ), static fn( $t ) => (int) $t->term_id === $module_id ) ) : array();
			$valid       = ! empty( $module_less );
		}
		$anchor = '#psgpt-student-' . $user_id;
		if ( ! $valid ) {
			wp_safe_redirect( add_query_arg( array( 'psgpt_reset' => 'error' ), $back ) . $anchor );
			exit;
		}

		$targets = $lesson_id ? array( $lesson_id ) : ( $module_id ? array_map( 'absint', $module_less ) : array_map( 'absint', $lessons ) );
		$scope   = $lesson_id ? 'lesson' : ( $module_id ? 'module' : 'course' );
		$backup  = array();
		foreach ( $targets as $lid ) {
			$backup[ $lid ] = self::reset_backup_rows( $user_id, $lid, 'sensei_lesson_status' );
		}
		$backup['course'] = self::reset_backup_rows( $user_id, $course_id, 'sensei_course_status' );

		$method = 'direct';
		if ( 'course' !== $scope ) {
			// Une leçon, ou toutes les leçons d'un module : leçon par leçon, comme dans Sensei.
			foreach ( $targets as $lid ) {
				if ( class_exists( 'Sensei_Utils' ) && method_exists( 'Sensei_Utils', 'sensei_remove_user_from_lesson' ) ) {
					Sensei_Utils::sensei_remove_user_from_lesson( $lid, $user_id );
					$method = 'sensei';
				}
				self::reset_delete_lesson_status( $user_id, $lid ); // Sans Sensei, ou s'il en reste.
			}
			self::reset_reopen_course_status( $user_id, $course_id );
		} else {
			if ( class_exists( 'Sensei_Utils' ) && method_exists( 'Sensei_Utils', 'reset_course_for_user' ) ) {
				Sensei_Utils::reset_course_for_user( $course_id, $user_id );
				$method = 'sensei';
			}
			foreach ( $targets as $lid ) {
				self::reset_delete_lesson_status( $user_id, $lid );
			}
			self::reset_reopen_course_status( $user_id, $course_id, true );
		}
		do_action( 'logiprogress_progress_reset', $user_id, $course_id, $lesson_id );
		$mailed = self::reset_notify_student( $user_id, $course, $lesson_id ? get_post( $lesson_id ) : null, $module );

		if ( class_exists( 'LogiPilot_Log' ) ) {
			LogiPilot_Log::add(
				'progress_reset_' . $scope,
				$scope,
				$lesson_id ? $lesson_id : ( $module_id ? $module_id : $course_id ),
				array(
					'student'  => $user_id,
					'course'   => $course_id,
					'group'    => $group_id,
					'lessons'  => count( $targets ),
					'method'   => $method,
					'mail'     => $mailed,
					'backup'   => $backup, // Ce qui a été effacé (statuts et métadonnées), pour pouvoir le retrouver au besoin.
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'psgpt_reset'      => $scope,
					'psgpt_reset_module' => $module_id ? $module_id : null,
					'psgpt_reset_user' => $user_id,
					'psgpt_reset_item' => $lesson_id ? $lesson_id : $course_id,
					'psgpt_reset_mail' => $mailed,
				),
				$back
			) . $anchor
		);
		exit;
	}

	/** Module Sensei de chaque leçon (leçon => terme « module »), pour les cours organisés en modules. */
	private static function lesson_module_map( $lesson_ids ) {
		$lesson_ids = array_values( array_filter( array_map( 'absint', (array) $lesson_ids ) ) );
		if ( ! $lesson_ids || ! taxonomy_exists( 'module' ) ) {
			return array();
		}
		$map   = array();
		$terms = wp_get_object_terms( $lesson_ids, 'module', array( 'fields' => 'all_with_object_id' ) );
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			if ( ! isset( $map[ (int) $term->object_id ] ) ) {
				$map[ (int) $term->object_id ] = $term;
			}
		}
		return $map;
	}

	/** Toutes les leçons du cours, y compris celles que le tableau masque (objectifs, documents téléchargeables…). */
	private static function reset_all_course_lesson_ids( $course_id ) {
		$ids = array_map( 'absint', (array) self::get_lesson_ids_from_order( $course_id ) );
		if ( function_exists( 'Sensei' ) && isset( Sensei()->course ) && method_exists( Sensei()->course, 'course_lessons' ) ) {
			$ids = array_merge( $ids, array_map( 'absint', (array) Sensei()->course->course_lessons( $course_id, 'any', 'ids' ) ) );
		}
		$ids = array_merge(
			$ids,
			array_map(
				'absint',
				get_posts(
					array(
						'post_type'      => 'lesson',
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'meta_key'       => '_lesson_course',
						'meta_value'     => absint( $course_id ),
						'fields'         => 'ids',
					)
				)
			)
		);
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * E-mail à l'élève (adresse de son profil WordPress) : quelle leçon ou quel cours a été réinitialisé, par qui,
	 * et le lien pour le refaire. Réponse possible directement à l'enseignant. Renvoie sent | noaddress | failed.
	 */
	private static function reset_notify_student( $user_id, $course, $lesson, $module = null ) {
		$student = get_userdata( $user_id );
		if ( ! $student || ! is_email( $student->user_email ) ) {
			return 'noaddress';
		}
		$teacher = wp_get_current_user();
		$first   = trim( (string) $student->first_name ) ? trim( (string) $student->first_name ) : $student->display_name;
		$what    = $lesson
			/* translators: 1: leçon, 2: cours */
			? sprintf( __( 'la leçon « %1$s » du cours « %2$s »', 'pickles-sensei-group-progress-tracker' ), $lesson->post_title, $course->post_title )
			: ( $module
				/* translators: 1: module, 2: cours */
				? sprintf( __( 'le module « %1$s » du cours « %2$s » (toutes ses leçons)', 'pickles-sensei-group-progress-tracker' ), $module->name, $course->post_title )
				/* translators: %s: cours */
				: sprintf( __( 'le cours « %s » (toutes ses leçons)', 'pickles-sensei-group-progress-tracker' ), $course->post_title ) );
		$link    = get_permalink( $lesson ? $lesson : $course );
		$subject = $lesson
			/* translators: %s: leçon */
			? sprintf( __( 'Ta leçon « %s » a été réinitialisée', 'pickles-sensei-group-progress-tracker' ), $lesson->post_title )
			: ( $module
				/* translators: %s: module */
				? sprintf( __( 'Ton module « %s » a été réinitialisé', 'pickles-sensei-group-progress-tracker' ), $module->name )
				/* translators: %s: cours */
				: sprintf( __( 'Ton cours « %s » a été réinitialisé', 'pickles-sensei-group-progress-tracker' ), $course->post_title ) );
		$lines   = array(
			/* translators: %s: prénom de l'élève */
			sprintf( __( 'Bonjour %s,', 'pickles-sensei-group-progress-tracker' ), $first ),
			'',
			/* translators: 1: enseignant, 2: leçon ou cours */
			sprintf( __( '%1$s a réinitialisé ta progression dans %2$s.', 'pickles-sensei-group-progress-tracker' ), $teacher->display_name, $what ),
			$lesson ? __( 'Cette leçon est de nouveau à faire : tu peux la reprendre depuis le début, quiz compris.', 'pickles-sensei-group-progress-tracker' ) : ( $module ? __( 'Les leçons de ce module sont de nouveau à faire : tu peux reprendre le module depuis le début, quiz compris.', 'pickles-sensei-group-progress-tracker' ) : __( 'Ces leçons sont de nouveau à faire : tu peux reprendre le cours depuis le début, quiz compris.', 'pickles-sensei-group-progress-tracker' ) ),
			'',
		);
		if ( $link ) {
			/* translators: %s: adresse du cours ou de la leçon */
			$lines[] = sprintf( __( 'Pour la reprendre : %s', 'pickles-sensei-group-progress-tracker' ), $link );
			$lines[] = '';
		}
		$lines[] = __( 'Si tu as une question, réponds simplement à cet e-mail.', 'pickles-sensei-group-progress-tracker' );
		$lines[] = '';
		$lines[] = $teacher->display_name;
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( is_email( $teacher->user_email ) ) {
			$headers[] = 'Reply-To: ' . $teacher->display_name . ' <' . $teacher->user_email . '>';
		}
		$ok = wp_mail( $student->user_email, $subject, implode( "\n", $lines ), $headers );
		return $ok ? 'sent' : 'failed';
	}

	/** Statuts Sensei (commentaires) et leurs métadonnées, avant effacement. */
	private static function reset_backup_rows( $user_id, $post_id, $type ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT comment_ID, comment_post_ID, comment_approved, comment_date, comment_content FROM {$wpdb->comments} WHERE user_id = %d AND comment_post_ID = %d AND comment_type = %s", $user_id, $post_id, $type ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['meta'] = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->commentmeta} WHERE comment_id = %d", $row['comment_ID'] ), ARRAY_A );
		}
		return $rows;
	}

	private static function reset_delete_lesson_status( $user_id, $lesson_id ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d AND comment_post_ID = %d AND comment_type = 'sensei_lesson_status'", $user_id, $lesson_id ) );
		foreach ( $ids as $id ) {
			wp_delete_comment( (int) $id, true );
		}
		// Anciennes réponses de quiz enregistrées à part (anciennes versions de Sensei).
		$quiz_id = (int) get_post_meta( $lesson_id, '_lesson_quiz', true );
		if ( $quiz_id ) {
			$old = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d AND comment_post_ID = %d AND comment_type IN ('sensei_quiz_answers', 'sensei_user_answer', 'sensei_user_grade')", $user_id, $quiz_id ) );
			foreach ( $old as $id ) {
				wp_delete_comment( (int) $id, true );
			}
		}
	}

	/** Le cours n'est plus « terminé » : il repasse « en cours » (cours entier : 0 % et aucune leçon terminée). */
	private static function reset_reopen_course_status( $user_id, $course_id, $whole = false ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d AND comment_post_ID = %d AND comment_type = 'sensei_course_status'", $user_id, $course_id ) );
		foreach ( $ids as $id ) {
			$comment = get_comment( (int) $id );
			if ( $comment && ( $whole || 'complete' === $comment->comment_approved ) ) {
				$wpdb->update( $wpdb->comments, array( 'comment_approved' => 'in-progress' ), array( 'comment_ID' => (int) $id ) );
				clean_comment_cache( (int) $id );
				if ( $whole ) {
					update_comment_meta( (int) $id, 'percent', 0 );
					update_comment_meta( (int) $id, 'complete', 0 );
				}
			}
		}
	}
}
