<?php
/**
 * LogiProgress – rythme suspect (LogiOne 1.26.3).
 *
 * Signale l'élève dont plusieurs leçons ont été validées en quelques minutes seulement (contenu théorique, quiz),
 * pour que l'enseignant aille vérifier. Durée d'une leçon : de son début noté par Sensei (« start ») à sa validation ;
 * sans début connu, temps écoulé depuis la leçon validée juste avant dans le même cours.
 * Rien n'est bloqué ni modifié : c'est une simple alerte visuelle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiProgress_Suspicion {

	/** Une leçon validée en moins de ce temps est « trop rapide » (3 minutes par défaut). */
	private static function suspect_seconds() {
		return max( 30, (int) apply_filters( 'logiprogress_suspect_seconds', 180 ) );
	}

	/**
	 * Leçons validées trop vite pour un élève, sur les cours affichés.
	 * Renvoie [ 'flag' => bool, 'rapid' => [ lesson_id => [ 'seconds', 'quiz', 'title', 'course' ] ], 'quiz' => n ].
	 */
	private static function student_suspicion( $sid, $reports ) {
		$limit = self::suspect_seconds();
		$rapid = array();
		foreach ( $reports as $r ) {
			$statuses = isset( $r['rows'][ $sid ]['statuses'] ) ? $r['rows'][ $sid ]['statuses'] : array();
			$done     = array();
			foreach ( $r['lessons'] as $lesson ) {
				$d = isset( $statuses[ $lesson->ID ] ) ? $statuses[ $lesson->ID ] : array();
				if ( empty( $d['status'] ) || ! self::is_complete_status( $d['status'] ) ) {
					continue;
				}
				$end = strtotime( (string) ( ! empty( $d['first_completed_at'] ) ? $d['first_completed_at'] : ( $d['updated_at'] ?? '' ) ) );
				if ( ! $end ) {
					continue;
				}
				$start = ! empty( $d['started_at'] ) ? strtotime( (string) $d['started_at'] ) : 0;
				// 1.33.2 : un « début » noté au moment même de la validation (leçon sans quiz terminée d'un clic, Sensei
				// note alors début = fin) ne dit rien du temps passé : on l'ignore et on prend la leçon précédente.
				if ( $start && ( $start > $end || $end - $start < 10 ) ) {
					$start = 0;
				}
				$done[] = array( 'lesson' => $lesson, 'end' => $end, 'start' => $start );
			}
			usort( $done, static fn( $a, $b ) => $a['end'] <=> $b['end'] );
			$prev = 0;
			foreach ( $done as $item ) {
				$from = $item['start'] ? $item['start'] : $prev;
				if ( $from ) {
					$seconds = $item['end'] - $from;
					if ( $seconds >= 0 && $seconds < $limit ) {
						$rapid[ $item['lesson']->ID ] = array(
							'seconds' => $seconds,
							'approx'  => ! $item['start'],
							'quiz'    => self::lesson_has_real_quiz( $item['lesson']->ID ),
							'title'   => $item['lesson']->post_title,
							'course'  => $r['course']->post_title,
						);
					}
				}
				$prev = $item['end'];
			}
		}
		$quiz = count( array_filter( $rapid, static fn( $x ) => $x['quiz'] ) );
		// Alerte : au moins 3 leçons trop rapides, ou 2 dont au moins une avec quiz.
		$flag = count( $rapid ) >= 3 || ( count( $rapid ) >= 2 && $quiz >= 1 );
		return array( 'flag' => $flag, 'rapid' => $rapid, 'quiz' => $quiz );
	}

	/**
	 * Vrai quiz avec des questions. Sensei crée un quiz vide pour presque chaque leçon : avant, toute leçon
	 * était donc affichée « avec quiz » (LogiOne 1.33.2).
	 */
	private static function lesson_has_real_quiz( $lesson_id ) {
		static $cache = array();
		$lesson_id = absint( $lesson_id );
		if ( isset( $cache[ $lesson_id ] ) ) {
			return $cache[ $lesson_id ];
		}
		$has     = false;
		$quiz_id = self::get_lesson_quiz_id( $lesson_id );
		if ( $quiz_id ) {
			$flag = get_post_meta( $lesson_id, '_quiz_has_questions', true );
			if ( '' !== $flag && null !== $flag ) {
				$has = (bool) $flag;
			} elseif ( function_exists( 'Sensei' ) && isset( Sensei()->lesson ) && method_exists( Sensei()->lesson, 'lesson_quiz_questions' ) ) {
				$has = count( (array) Sensei()->lesson->lesson_quiz_questions( $quiz_id ) ) > 0;
			} else {
				global $wpdb;
				$has = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_quiz_id' AND pm.meta_value = %d AND p.post_type IN ('question','multiple_question') AND p.post_status <> 'trash'", $quiz_id ) ) > 0;
			}
		}
		$cache[ $lesson_id ] = $has;
		return $has;
	}

	/**
	 * 1.37.0 : nombre de questions du quiz d'une leçon : array( 'count' => questions du quiz, 'shown' => posées à
	 * l'élève si le quiz n'en montre qu'une partie, sinon 0 ). count = 0 : pas de quiz (ou quiz vide).
	 * Une « question multiple » de Sensei (tirage dans une catégorie) compte pour le nombre de questions tirées.
	 */
	public static function lesson_quiz_info( $lesson_id ) {
		static $cache = array();
		$lesson_id = absint( $lesson_id );
		if ( isset( $cache[ $lesson_id ] ) ) {
			return $cache[ $lesson_id ];
		}
		$info    = array( 'count' => 0, 'shown' => 0 );
		$quiz_id = self::get_lesson_quiz_id( $lesson_id );
		if ( $quiz_id ) {
			$questions = array();
			if ( function_exists( 'Sensei' ) && isset( Sensei()->lesson ) && method_exists( Sensei()->lesson, 'lesson_quiz_questions' ) ) {
				$questions = (array) Sensei()->lesson->lesson_quiz_questions( $quiz_id, 'publish' );
			}
			if ( ! $questions ) {
				global $wpdb;
				$questions = (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_type FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_quiz_id' AND pm.meta_value = %d AND p.post_type IN ('question','multiple_question') AND p.post_status NOT IN ('trash','auto-draft')", $quiz_id ) );
			}
			foreach ( $questions as $q ) {
				$id   = is_object( $q ) ? (int) $q->ID : (int) $q;
				$type = is_object( $q ) && isset( $q->post_type ) ? $q->post_type : get_post_type( $id );
				$info['count'] += 'multiple_question' === $type ? max( 1, (int) get_post_meta( $id, 'number', true ) ) : 1;
			}
			$show = (int) get_post_meta( $quiz_id, '_show_questions', true );
			if ( $show > 0 && $show < $info['count'] ) {
				$info['shown'] = $show;
			}
		}
		$cache[ $lesson_id ] = $info;
		return $info;
	}

	/** Petit badge « 📝 Quiz · 8 questions » ou « Sans quiz ». */
	public static function quiz_badge( $lesson_id ) {
		$info = self::lesson_quiz_info( $lesson_id );
		if ( ! $info['count'] ) {
			return '<span class="psgpt-quiz-badge is-none" title="' . esc_attr__( 'Cette leçon n’a pas de quiz (ou un quiz sans question).', 'pickles-sensei-group-progress-tracker' ) . '">' . esc_html__( 'Sans quiz', 'pickles-sensei-group-progress-tracker' ) . '</span>';
		}
		/* translators: %d: nombre de questions */
		$text  = sprintf( _n( '%d question', '%d questions', $info['count'], 'pickles-sensei-group-progress-tracker' ), $info['count'] );
		$title = $text;
		if ( $info['shown'] ) {
			/* translators: 1: posées, 2: total */
			$text  = sprintf( __( '%1$d / %2$d questions', 'pickles-sensei-group-progress-tracker' ), $info['shown'], $info['count'] );
			$title = sprintf( __( 'Quiz de %2$d questions, %1$d posées à chaque élève', 'pickles-sensei-group-progress-tracker' ), $info['shown'], $info['count'] );
		}
		return '<span class="psgpt-quiz-badge is-quiz" title="' . esc_attr( sprintf( __( 'Quiz : %s', 'pickles-sensei-group-progress-tracker' ), $title ) ) . '">📝 ' . esc_html( sprintf( __( 'Quiz · %s', 'pickles-sensei-group-progress-tracker' ), $text ) ) . '</span>';
	}

	private static function suspect_duration( $seconds ) {
		return $seconds < 60
			/* translators: %d: secondes */
			? sprintf( __( '%d s', 'pickles-sensei-group-progress-tracker' ), max( 1, (int) $seconds ) )
			/* translators: %d: minutes */
			: sprintf( __( '%d min', 'pickles-sensei-group-progress-tracker' ), (int) round( $seconds / 60 ) );
	}

	/** Texte de l'alerte : « 4 leçons validées en moins de 3 min chacune (dont 2 avec quiz) ». */
	private static function suspect_summary( $s ) {
		$n    = count( $s['rapid'] );
		/* translators: 1: nombre de leçons, 2: durée */
		$text = sprintf( _n( '%1$d leçon validée en moins de %2$s', '%1$d leçons validées en moins de %2$s chacune', $n, 'pickles-sensei-group-progress-tracker' ), $n, self::suspect_duration( self::suspect_seconds() ) );
		if ( $s['quiz'] ) {
			/* translators: %d: nombre de leçons avec quiz */
			$text .= ' ' . sprintf( _n( '(dont %d avec quiz)', '(dont %d avec quiz)', $s['quiz'], 'pickles-sensei-group-progress-tracker' ), $s['quiz'] );
		}
		return $text;
	}

	/** Pastille à côté du nom de l'élève, dans la liste. */
	private static function render_suspect_badge( $s ) {
		if ( empty( $s['flag'] ) ) {
			return;
		}
		$title = __( 'Rythme suspect : ', 'pickles-sensei-group-progress-tracker' ) . self::suspect_summary( $s ) . '. ' . __( 'À vérifier.', 'pickles-sensei-group-progress-tracker' );
		echo '<span class="psgpt-suspect" title="' . esc_attr( $title ) . '" data-psgpt-suspect>⚠<span class="psgpt-sr">' . esc_html( $title ) . '</span></span>';
	}

	/** Encadré dans le détail de l'élève : la liste des leçons validées trop vite. */
	private static function render_suspect_panel( $s ) {
		if ( empty( $s['flag'] ) ) {
			return;
		}
		?>
		<div class="psgpt-card psgpt-suspect-panel" role="note">
			<strong>⚠ <?php esc_html_e( 'Rythme suspect : à vérifier', 'pickles-sensei-group-progress-tracker' ); ?></strong>
			<p><?php echo esc_html( self::suspect_summary( $s ) . '.' ); ?> <?php esc_html_e( 'Regardez ses réponses dans Sensei ; au besoin, réinitialisez la leçon, le module ou le cours.', 'pickles-sensei-group-progress-tracker' ); ?></p>
			<ul>
				<?php foreach ( $s['rapid'] as $item ) : ?>
					<li><?php echo esc_html( $item['title'] ); ?> — <strong><?php echo esc_html( ( ! empty( $item['approx'] ) ? '≈ ' : '' ) . self::suspect_duration( $item['seconds'] ) ); ?></strong><?php echo ! empty( $item['approx'] ) ? ' <small class="psgpt-muted">' . esc_html__( '(depuis la leçon précédente)', 'pickles-sensei-group-progress-tracker' ) . '</small>' : ''; ?><?php echo $item['quiz'] ? ' · ' . esc_html__( 'avec quiz', 'pickles-sensei-group-progress-tracker' ) : ''; ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
