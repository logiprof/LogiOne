<?php
/**
 * LogiEval_Secure_Evaluations — partie « student-search » (LogiOne 1.41.0).
 * Onglet « Par élève » : choisir un élève, quelle que soit sa classe, et voir toutes ses évaluations
 * (passées, en cours, à corriger, pas encore passées) avec points, note et détail par question.
 * Lecture seule : aucune donnée n'est modifiée, aucune table n'est changée.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Student_Search {

	/**
	 * Tous les élèves des groupes classes, plus les élèves ayant une copie dans une évaluation visible.
	 *
	 * @return array<int,array{name:string,groups:string[]}>
	 */
	private static function student_search_pool( array $evaluations ): array {
		$pool = array();
		foreach ( self::accommodation_groups() as $group ) {
			foreach ( array_keys( $group['students'] ) as $uid ) {
				$uid = (int) $uid;
				if ( ! isset( $pool[ $uid ] ) ) {
					$pool[ $uid ] = array( 'name' => self::student_full_name( $uid ), 'groups' => array() );
				}
				$pool[ $uid ]['groups'][] = (string) $group['name'];
			}
		}
		$ids = array_map( static fn( $e ) => (int) $e['id'], $evaluations );
		if ( $ids ) {
			global $wpdb;
			$t = LogiEval_Secure_Database::tables();
			$users = $wpdb->get_col( 'SELECT DISTINCT user_id FROM ' . $t['attempts'] . ' WHERE evaluation_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiants entiers.
			foreach ( (array) $users as $uid ) {
				$uid = (int) $uid;
				if ( $uid && ! isset( $pool[ $uid ] ) && get_userdata( $uid ) ) {
					$pool[ $uid ] = array( 'name' => self::student_full_name( $uid ), 'groups' => array() );
				}
			}
		}
		foreach ( $pool as $uid => $row ) {
			$pool[ $uid ]['groups'] = array_values( array_unique( $row['groups'] ) );
			natcasesort( $pool[ $uid ]['groups'] );
		}
		uasort( $pool, static fn( $a, $b ) => strnatcasecmp( remove_accents( $a['name'] ), remove_accents( $b['name'] ) ) );
		return $pool;
	}

	/**
	 * Évaluations de l'élève : celles où il a une copie, et celles qui lui sont attribuées mais pas encore passées.
	 *
	 * @return array<int,array{evaluation:array,attempt:?array,result:?array}>
	 */
	private static function student_search_rows( int $user_id, array $evaluations ): array {
		global $wpdb;
		$t        = LogiEval_Secure_Database::tables();
		$by_id    = array();
		foreach ( $evaluations as $evaluation ) {
			$by_id[ (int) $evaluation['id'] ] = $evaluation;
		}
		$rows = array();
		if ( $by_id ) {
			$attempts = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $t['attempts'] . ' WHERE user_id=%d AND evaluation_id IN (' . implode( ',', array_keys( $by_id ) ) . ')', $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiants entiers.
			foreach ( (array) $attempts as $attempt ) {
				$eid          = (int) $attempt['evaluation_id'];
				$rows[ $eid ] = array( 'evaluation' => $by_id[ $eid ], 'attempt' => $attempt, 'result' => self::get_result_by_attempt( (int) $attempt['id'] ) );
			}
		}
		// Attribuées mais pas encore passées (brouillons exclus).
		$members = array();
		foreach ( $evaluations as $evaluation ) {
			$eid = (int) $evaluation['id'];
			if ( isset( $rows[ $eid ] ) || 'draft' === (string) $evaluation['status'] ) {
				continue;
			}
			$assigned = in_array( $user_id, self::decode_ids( $evaluation['student_user_ids'] ?? '' ), true );
			foreach ( self::decode_ids( $evaluation['sensei_group_ids'] ?? '' ) as $gid ) {
				if ( $assigned ) {
					break;
				}
				if ( ! isset( $members[ $gid ] ) ) {
					$members[ $gid ] = array_map( static fn( $u ) => (int) $u->ID, LogiEval_Secure_Sensei::get_group_students( $gid ) );
				}
				$assigned = in_array( $user_id, $members[ $gid ], true );
			}
			if ( $assigned ) {
				$rows[ $eid ] = array( 'evaluation' => $evaluation, 'attempt' => null, 'result' => null );
			}
		}
		// Les plus récentes en haut : date de passage, sinon date de planification, sinon date de création.
		$date = static function ( array $row ): string {
			$a = (array) $row['attempt'];
			$e = $row['evaluation'];
			foreach ( array( $a['submitted_at'] ?? '', $a['started_at'] ?? '', $e['opens_at'] ?? '', $e['created_at'] ?? '' ) as $d ) {
				if ( '' !== (string) $d && '0000-00-00 00:00:00' !== (string) $d ) {
					return (string) $d;
				}
			}
			return '';
		};
		// Les évaluations pas encore passées viennent après celles que l'élève a faites.
		uasort( $rows, static fn( $a, $b ) => ( null === $a['attempt'] ) <=> ( null === $b['attempt'] ) ?: strcmp( $date( $b ), $date( $a ) ) );
		return array_values( $rows );
	}

	private static function render_student_search_workspace(): void {
		$evaluations = self::get_evaluations();
		$pool        = self::student_search_pool( $evaluations );
		$selected    = absint( $_GET['les_student'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $selected && ! isset( $pool[ $selected ] ) ) {
			$selected = 0;
		}
		self::render_attempt_control_notice();
		$groups = array();
		foreach ( $pool as $row ) {
			foreach ( $row['groups'] as $g ) {
				$groups[ $g ] = true;
			}
		}
		$groups = array_keys( $groups );
		natcasesort( $groups );
		?>
		<section class="les-panel les-student-search" data-les-student-search>
			<div class="les-section-title">
				<div>
					<h2><?php esc_html_e( 'Recherche par élève', 'logieval-secure' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Tapez le nom d’un élève, quelle que soit sa classe, pour voir toutes ses évaluations : copies rendues, points, notes et détail par question.', 'logieval-secure' ); ?></p>
				</div>
			</div>
			<?php if ( ! $pool ) : ?>
				<p class="les-empty-state"><?php esc_html_e( 'Aucun élève trouvé dans les groupes classes.', 'logieval-secure' ); ?></p>
			<?php else : ?>
				<div class="les-ss-picker<?php echo $selected ? ' has-selection' : ''; ?>">
					<div class="les-ss-filters">
						<label class="les-ss-search">
							<span class="screen-reader-text"><?php esc_html_e( 'Rechercher un élève', 'logieval-secure' ); ?></span>
							<span aria-hidden="true">🔎</span>
							<input type="search" data-les-ss-input autocomplete="off" placeholder="<?php echo esc_attr( sprintf( _n( 'Rechercher parmi %d élève…', 'Rechercher parmi %d élèves…', count( $pool ), 'logieval-secure' ), count( $pool ) ) ); ?>"<?php echo $selected ? '' : ' autofocus'; ?>>
						</label>
						<?php if ( count( $groups ) > 1 ) : ?>
							<label class="les-ss-group">
								<span class="screen-reader-text"><?php esc_html_e( 'Classe', 'logieval-secure' ); ?></span>
								<select data-les-ss-group>
									<option value=""><?php esc_html_e( 'Toutes les classes', 'logieval-secure' ); ?></option>
									<?php foreach ( $groups as $g ) : ?>
										<option value="<?php echo esc_attr( $g ); ?>"><?php echo esc_html( $g ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>
					</div>
					<ul class="les-ss-list" data-les-ss-list>
						<?php foreach ( $pool as $uid => $row ) : ?>
							<li data-name="<?php echo esc_attr( strtolower( remove_accents( $row['name'] . ' ' . implode( ' ', $row['groups'] ) ) ) ); ?>" data-groups="<?php echo esc_attr( wp_json_encode( $row['groups'] ) ); ?>">
								<a class="<?php echo $uid === $selected ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::app_url( 'students', array( 'les_student' => $uid ) ) . '#les-ss-result' ); ?>">
									<strong><?php echo esc_html( $row['name'] ); ?></strong>
									<span><?php echo esc_html( $row['groups'] ? implode( ' · ', $row['groups'] ) : __( 'Sans classe', 'logieval-secure' ) ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="description les-ss-empty" data-les-ss-empty hidden><?php esc_html_e( 'Aucun élève ne correspond à cette recherche.', 'logieval-secure' ); ?></p>
				</div>
				<?php if ( $selected ) : ?>
					<?php self::render_student_search_result( $selected, $pool[ $selected ], $evaluations ); ?>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_student_search_result( int $user_id, array $student, array $evaluations ): void {
		$rows   = self::student_search_rows( $user_id, $evaluations );
		$grades = array();
		$counts = array( 'done' => 0, 'to_grade' => 0, 'pending' => 0 );
		foreach ( $rows as $i => $row ) {
			$attempt = $row['attempt'];
			$result  = $row['result'];
			$grade   = null;
			$state   = 'pending';
			if ( $attempt ) {
				$cancelled = self::attempt_is_cheating_cancelled( $attempt );
				$finished  = self::attempt_correction_is_finished( $attempt, $result );
				$submitted = $cancelled || ! empty( $attempt['submitted_at'] ) || in_array( (string) $attempt['status'], array( 'submitted', 'submitted_locked', 'graded' ), true );
				$grade     = $cancelled ? self::CHEATING_GRADE : ( $finished && $result ? (float) $result['final_grade'] : null );
				$state     = $cancelled ? 'cancelled' : ( $finished ? 'done' : ( $submitted ? 'to_grade' : 'running' ) );
			}
			$rows[ $i ]['grade']     = $grade;
			$rows[ $i ]['state']     = $state;
			$rows[ $i ]['submitted'] = $attempt && ( 'pending' !== $state && 'running' !== $state );
			if ( null !== $grade ) {
				$grades[] = $grade;
			}
			if ( 'done' === $state || 'cancelled' === $state ) {
				$counts['done']++;
			} elseif ( 'to_grade' === $state ) {
				$counts['to_grade']++;
			} elseif ( 'pending' === $state ) {
				$counts['pending']++;
			}
		}
		$state_labels = array(
			'done'      => __( 'Corrigée', 'logieval-secure' ),
			'cancelled' => __( 'Annulée (fraude)', 'logieval-secure' ),
			'to_grade'  => __( 'À corriger', 'logieval-secure' ),
			'running'   => __( 'En cours', 'logieval-secure' ),
			'pending'   => __( 'Pas encore passée', 'logieval-secure' ),
		);
		$group_names = array();
		foreach ( LogiEval_Secure_Sensei::get_groups() as $group ) {
			$group_names[ (int) $group->term_id ] = (string) $group->name;
		}
		?>
		<div class="les-ss-result" id="les-ss-result">
			<div class="les-ss-head">
				<div>
					<h3><?php echo esc_html( $student['name'] ); ?></h3>
					<p class="les-ss-classes"><?php echo esc_html( $student['groups'] ? implode( ' · ', $student['groups'] ) : __( 'Sans classe', 'logieval-secure' ) ); ?></p>
				</div>
				<div class="les-ss-stats">
					<span><strong><?php echo esc_html( (string) $counts['done'] ); ?></strong> <?php esc_html_e( 'corrigée(s)', 'logieval-secure' ); ?></span>
					<span class="<?php echo $counts['to_grade'] ? 'is-alert' : ''; ?>"><strong><?php echo esc_html( (string) $counts['to_grade'] ); ?></strong> <?php esc_html_e( 'à corriger', 'logieval-secure' ); ?></span>
					<span><strong><?php echo esc_html( (string) $counts['pending'] ); ?></strong> <?php esc_html_e( 'pas encore passée(s)', 'logieval-secure' ); ?></span>
					<span class="is-average"><?php esc_html_e( 'Moyenne', 'logieval-secure' ); ?> <strong><?php echo esc_html( $grades ? number_format_i18n( array_sum( $grades ) / count( $grades ), 2 ) : '—' ); ?></strong></span>
				</div>
			</div>
			<?php if ( ! $rows ) : ?>
				<p class="les-message"><?php esc_html_e( 'Aucune évaluation pour cet élève.', 'logieval-secure' ); ?></p>
			<?php else : ?>
				<div class="les-ss-cards">
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$evaluation = $row['evaluation'];
						$attempt    = $row['attempt'];
						$result     = $row['result'];
						$eid        = (int) $evaluation['id'];
						$classes    = array();
						foreach ( self::decode_ids( $evaluation['sensei_group_ids'] ?? '' ) as $gid ) {
							$classes[] = $group_names[ (int) $gid ] ?? ( get_the_title( (int) $gid ) ?: sprintf( __( 'Groupe %d', 'logieval-secure' ), $gid ) );
						}
						$when = $attempt ? (string) ( $attempt['submitted_at'] ?: $attempt['started_at'] ) : (string) ( $evaluation['opens_at'] ?? '' );
						$when = '0000-00-00 00:00:00' === $when ? '' : $when;
						?>
						<article class="les-ss-card is-<?php echo esc_attr( $row['state'] ); ?>">
							<div class="les-ss-card-main">
								<div class="les-ss-card-title">
									<h4><?php echo esc_html( (string) $evaluation['title'] ); ?></h4>
									<p>
										<?php echo esc_html( $classes ? implode( ' · ', $classes ) : __( 'Non affectée', 'logieval-secure' ) ); ?>
										<?php if ( $when ) : ?>
											· <?php echo esc_html( ( $attempt ? __( 'passée le', 'logieval-secure' ) : __( 'prévue le', 'logieval-secure' ) ) . ' ' . mysql2date( 'd.m.Y H:i', $when ) ); ?>
										<?php endif; ?>
									</p>
								</div>
								<span class="les-ss-state"><?php echo esc_html( $state_labels[ $row['state'] ] ); ?></span>
								<div class="les-ss-score">
									<span class="les-ss-points"><?php echo esc_html( 'cancelled' !== $row['state'] && $result ? self::format_points( (float) $result['final_total'] ) . ' / ' . self::format_points( (float) $evaluation['total_points'] ) : '— / ' . self::format_points( (float) $evaluation['total_points'] ) ); ?> <?php esc_html_e( 'pts', 'logieval-secure' ); ?></span>
									<strong class="les-ss-grade"><?php echo esc_html( null !== $row['grade'] ? self::pdf_optional_number( $row['grade'] ) : '—' ); ?></strong>
								</div>
							</div>
							<div class="les-ss-actions">
								<?php if ( $row['submitted'] ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( self::evaluation_url( $eid, 'grade', array( 'attempt' => (int) $attempt['id'] ) ) ); ?>"><?php echo esc_html( 'to_grade' === $row['state'] ? __( 'Corriger la copie', 'logieval-secure' ) : __( 'Voir la copie corrigée', 'logieval-secure' ) ); ?></a>
									<a class="button les-button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=logieval_secure_export_attempt_pdf&attempt_id=' . absint( $attempt['id'] ) ), self::NONCE_ACTION ) ); ?>"><?php esc_html_e( 'PDF élève', 'logieval-secure' ); ?></a>
								<?php elseif ( 'running' === $row['state'] ) : ?>
									<a class="button les-button-secondary" href="<?php echo esc_url( self::evaluation_url( $eid, 'run' ) ); ?>"><?php esc_html_e( 'Suivre la passation', 'logieval-secure' ); ?></a>
								<?php endif; ?>
								<a class="button les-button-secondary" href="<?php echo esc_url( self::evaluation_url( $eid ) ); ?>"><?php esc_html_e( 'Ouvrir l’évaluation', 'logieval-secure' ); ?></a>
								<?php if ( 'running' === $row['state'] && $attempt ) : ?>
									<?php self::render_attempt_control_menu( $attempt ); ?>
								<?php endif; ?>
							</div>
							<?php if ( 'running' === $row['state'] && $attempt ) : $saved = self::completed_answer_count( self::get_answers_for_attempt( (int) $attempt['id'] ) ); $nbq = count( self::get_questions_for_evaluation( $eid ) ); ?>
								<p class="les-ss-running-note">
									<?php echo esc_html( sprintf( __( 'Copie jamais envoyée : %1$d réponse(s) enregistrée(s) sur %2$d question(s). Elle n’apparaît pas dans les copies à corriger tant qu’elle n’est pas terminée. Si l’élève a fini en classe, ouvrez « ⚙ Gérer » › « Terminer la copie et l’envoyer en correction ».', 'logieval-secure' ), $saved, $nbq ) ); ?>
								</p>
							<?php endif; ?>
							<?php if ( $row['submitted'] && 'cancelled' !== $row['state'] ) : ?>
								<?php self::render_student_search_detail( (int) $attempt['id'], (string) $row['state'] ); ?>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Détail par question : points obtenus / points de la question, commentaire de l'enseignant. */
	private static function render_student_search_detail( int $attempt_id, string $state ): void {
		$answers = self::get_answers_for_attempt( $attempt_id );
		if ( ! $answers ) {
			return;
		}
		?>
		<details class="les-ss-detail">
			<summary><?php echo esc_html( sprintf( _n( 'Détail par question (%d question)', 'Détail par question (%d questions)', count( $answers ), 'logieval-secure' ), count( $answers ) ) ); ?></summary>
			<table class="les-ss-detail-table">
				<thead><tr><th>#</th><th><?php esc_html_e( 'Question', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Points', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Commentaire', 'logieval-secure' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $answers as $n => $a ) : ?>
						<?php
						$max    = (float) $a['points'];
						$points = is_numeric( $a['manual_points'] ) ? (float) $a['manual_points'] : ( is_numeric( $a['auto_points'] ) ? (float) $a['auto_points'] : null );
						$empty  = '' === trim( wp_strip_all_tags( (string) $a['answer'] ) );
						$class  = null === $points ? 'is-open' : ( $points >= $max - 0.001 ? 'is-full' : ( $points > 0 ? 'is-part' : 'is-zero' ) );
						$title  = trim( (string) $a['title'] ) !== '' ? (string) $a['title'] : wp_trim_words( wp_strip_all_tags( (string) $a['prompt'] ), 14 );
						?>
						<tr class="<?php echo esc_attr( $class ); ?>">
							<td><?php echo esc_html( (string) ( $n + 1 ) ); ?></td>
							<td><?php echo esc_html( $title ); ?><?php echo $empty ? ' <em>' . esc_html__( '(non répondu)', 'logieval-secure' ) . '</em>' : ''; ?></td>
							<td class="les-ss-pts"><?php echo esc_html( ( null === $points ? ( 'to_grade' === $state ? __( 'à corriger', 'logieval-secure' ) : '—' ) : self::format_points( $points ) ) . ' / ' . self::format_points( $max ) ); ?></td>
							<td><?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $a['teacher_comment'] ), 30 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
		<?php
	}
}
