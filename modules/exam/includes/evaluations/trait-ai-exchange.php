<?php
/**
 * LogiOne 1.40 — Correction par une IA externe (sans clé API).
 *
 * 1. L'enseignant télécharge un fichier : consignes de correction + barème + réponses des élèves, ANONYMES (E01, E02…).
 * 2. Il le confie à l'IA de son choix (Claude, ChatGPT…), qui rend un compte rendu au format JSON demandé.
 * 3. Il colle ce compte rendu dans LogiExam : vérification, aperçu, puis import dans la colonne « IA »
 *    (ai_points, ai_comment, ai_analysis…). Les points de l'enseignant ne sont jamais touchés.
 *
 * La correspondance codes ↔ copies reste sur le site (option logiexam_ai_exchange_{évaluation}).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Ai_Exchange {

	private static function ai_exchange_option( int $evaluation_id ): string {
		return 'logiexam_ai_exchange_' . $evaluation_id;
	}

	private static function ai_exchange_transient( int $evaluation_id ): string {
		return 'logiexam_ai_import_' . get_current_user_id() . '_' . $evaluation_id;
	}

	/** Questions notées automatiquement (QCM, Vrai/Faux, Relier, Ordonner, Trous) : pas besoin de l'IA. */
	private static function ai_exchange_is_auto_type( string $type ): bool {
		return in_array( $type, array_merge( LogiEval_Secure_Question_Types::AUTO_TYPES, array( 'multiple_choice', 'true_false' ) ), true );
	}

	/** Copies concernées : rendues, non annulées, correction pas encore terminée. */
	private static function ai_exchange_attempts( array $evaluation ): array {
		$out = array();
		foreach ( self::get_attempts( (int) $evaluation['id'] ) as $attempt ) {
			if ( self::attempt_is_cheating_cancelled( $attempt ) ) {
				continue;
			}
			$submitted = ! empty( $attempt['submitted_at'] ) || in_array( (string) $attempt['status'], array( 'submitted', 'submitted_locked', 'graded' ), true );
			if ( ! $submitted || self::attempt_correction_is_finished( $attempt, self::get_result_by_attempt( (int) $attempt['id'] ) ) ) {
				continue;
			}
			$out[] = $attempt;
		}
		usort( $out, static fn( $a, $b ) => (int) $a['id'] <=> (int) $b['id'] );
		return $out;
	}

	/** Barème d'une réponse, construit exactement comme pour la pré-correction intégrée. */
	private static function ai_exchange_criteria( array $answer, float $max_points ): array {
		$scored   = self::scored_criteria( $answer['grading_criteria'] ?? '' );
		$criteria = $scored['criteria'];
		if ( $scored['notes'] ) {
			$sum    = array_sum( array_map( static fn( $c ) => (float) $c['points'], $criteria ) );
			$target = min( $max_points, $sum + (float) ( $scored['dropped_points'] ?? 0 ) );
			if ( $sum > 0 && $sum < $target - 0.001 ) {
				foreach ( $criteria as $i => $c ) {
					$criteria[ $i ]['points'] = round( (float) $c['points'] * $target / $sum, 2 );
				}
			}
		}
		$tfj_choice = null;
		$tfj_rule   = '';
		if ( LogiEval_Secure_Question_Types::TFJ === ( $answer['type'] ?? '' ) ) {
			$tfj = LogiEval_Secure_Question_Types::tfj_expected( $answer['expected_answer'] ?? '' );
			$cp  = null === $tfj['choice_points'] ? $max_points / 2 : min( $max_points, $tfj['choice_points'] );
			$tfj_rule = sprintf( 'Vrai/Faux argumenté : le bon choix (%1$s) vaut %2$s pt, l’argumentation vaut %3$s pt.', $tfj['choice'], $cp, max( 0, $max_points - $cp ) );
			if ( ! $criteria && '' !== $tfj['choice'] ) {
				$given      = json_decode( (string) ( $answer['answer'] ?? '' ), true );
				$choice     = is_array( $given ) ? (string) ( $given['choice'] ?? '' ) : '';
				$tfj_choice = array( 'ok' => 0 === strcasecmp( $choice, $tfj['choice'] ), 'given' => $choice, 'expected' => $tfj['choice'] );
				$criteria   = array(
					array( 'label' => sprintf( 'Bon choix Vrai/Faux (%s)', $tfj['choice'] ), 'points' => $cp ),
					array( 'label' => 'Justification : ' . ( '' !== $tfj['justification'] ? $tfj['justification'] : 'argument juste et pertinent' ), 'points' => max( 0, $max_points - $cp ) ),
				);
			}
		}
		return array(
			'criteria'   => $criteria,
			'own_ideas'  => ! $criteria,
			'notes'      => $scored['notes'],
			'tfj_choice' => $tfj_choice,
			'tfj_rule'   => $tfj_rule,
		);
	}

	/* ---------------------------------------------------------------------
	 * 1. Export
	 * ------------------------------------------------------------------- */

	public static function handle_ai_export(): void {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ) );
		}
		$evaluation   = self::require_managed_evaluation( absint( $_GET['evaluation_id'] ?? 0 ) );
		$id           = (int) $evaluation['id'];
		$only_missing = ! empty( $_GET['only_missing'] );
		$attempts     = self::ai_exchange_attempts( $evaluation );

		// Questions de l'évaluation (numéro = ordre dans l'évaluation, comme « Question n » à l'écran).
		$questions = array();
		$qcodes    = array();
		$first     = $attempts ? self::get_answers_for_attempt( (int) $attempts[0]['id'] ) : array();
		foreach ( array_values( $first ) as $i => $answer ) {
			if ( self::ai_exchange_is_auto_type( (string) $answer['type'] ) ) {
				continue;
			}
			$code       = 'Q' . ( $i + 1 );
			$max        = max( 0, (float) $answer['points'] );
			$built      = self::ai_exchange_criteria( $answer, $max );
			$q          = array(
				'question'         => $code,
				'type'             => sanitize_key( (string) $answer['type'] ),
				'points_max'       => $max,
				'titre'            => wp_strip_all_tags( (string) ( $answer['title'] ?? '' ) ),
				'consigne'         => wp_strip_all_tags( (string) ( $answer['prompt'] ?? '' ) ),
				'contexte'         => wp_strip_all_tags( (string) ( $answer['context'] ?? '' ) ),
				'reponse_attendue' => wp_strip_all_tags( self::readable_answer( (string) ( $answer['expected_answer'] ?? '' ) ) ),
			);
			if ( $built['criteria'] && null === $built['tfj_choice'] ) {
				$q['criteres'] = array();
				foreach ( $built['criteria'] as $k => $c ) {
					$q['criteres'][] = array( 'n' => $k + 1, 'critere' => (string) $c['label'], 'points' => (float) $c['points'] );
				}
			} elseif ( null !== $built['tfj_choice'] || '' !== $built['tfj_rule'] ) {
				$q['criteres'] = array(
					array( 'n' => 1, 'critere' => 'Bon choix Vrai/Faux (vérifié par LogiExam, mets status ok si le choix est juste)', 'points' => (float) ( $built['criteria'][0]['points'] ?? $max / 2 ) ),
					array( 'n' => 2, 'critere' => (string) ( $built['criteria'][1]['label'] ?? 'Justification' ), 'points' => (float) ( $built['criteria'][1]['points'] ?? $max / 2 ) ),
				);
				$q['bareme_vrai_faux'] = $built['tfj_rule'];
			} else {
				$q['criteres'] = 'Pas de barème détaillé : découpe reponse_attendue en 1 à 4 idées (label + points, total = points_max).';
			}
			if ( $built['notes'] ) {
				$q['consignes_enseignant'] = $built['notes'];
			}
			$questions[]                      = array_filter( $q, static fn( $v ) => '' !== $v && array() !== $v );
			$qcodes[ $code ]                  = (int) $answer['question_id'];
		}

		// Copies anonymes.
		$students = array();
		$scodes   = array();
		$n        = 0;
		foreach ( $attempts as $attempt ) {
			$n++;
			$code     = sprintf( 'E%02d', $n );
			$answers  = array();
			$by_qid   = array();
			foreach ( self::get_answers_for_attempt( (int) $attempt['id'] ) as $answer ) {
				$by_qid[ (int) $answer['question_id'] ] = $answer;
			}
			foreach ( $qcodes as $qcode => $qid ) {
				$answer = $by_qid[ $qid ] ?? null;
				if ( ! $answer || ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
					continue; // Réponse vide : 0 point « Non répondu. », mis par LogiExam à l'import.
				}
				if ( $only_missing && ( is_numeric( $answer['manual_points'] ?? null ) || is_numeric( $answer['ai_points'] ?? null ) ) ) {
					continue;
				}
				$answers[] = array(
					'question' => $qcode,
					'reponse'  => wp_strip_all_tags( self::readable_answer( (string) $answer['answer'] ) ),
				);
			}
			$scodes[ $code ] = (int) $attempt['id'];
			if ( $answers ) {
				$students[] = array( 'eleve' => $code, 'reponses' => $answers );
			}
		}

		$token = strtoupper( substr( wp_hash( $id . '|' . microtime( true ) . '|' . wp_rand() ), 0, 8 ) );
		update_option(
			self::ai_exchange_option( $id ),
			array(
				'token'     => $token,
				'students'  => $scodes,
				'questions' => $qcodes,
				'created'   => current_time( 'mysql' ),
			),
			false
		);

		$text     = self::ai_exchange_document( $evaluation, $token, $questions, $students );
		$filename = sanitize_file_name( 'logiexam-correction-ia-' . sanitize_title( (string) $evaluation['title'] ) . '-' . wp_date( 'Y-m-d' ) . '.txt' );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fichier texte téléchargé.
		exit;
	}

	/** Le fichier remis à l'IA : consignes, format de réponse, barème, copies. */
	private static function ai_exchange_document( array $evaluation, string $token, array $questions, array $students ): string {
		$uid     = get_current_user_id();
		$tu      = 'tu' === self::ai_student_address( $uid );
		$level   = self::ai_strictness_levels()[ self::evaluation_ai_strictness( $evaluation ) ] ?? array( 'label' => '', 'prompt' => '' );
		$json    = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
		$vocab   = trim( self::ai_prompt( 'grading_vocabulary' ) );
		$count_a = array_sum( array_map( static fn( $s ) => count( $s['reponses'] ), $students ) );

		$doc  = "# LogiExam — correction par une IA\n\n";
		$doc .= 'Évaluation : ' . wp_strip_all_tags( (string) $evaluation['title'] ) . "\n";
		$doc .= 'Code d’export : ' . $token . "\n";
		$doc .= sprintf( "Contenu : %d questions à corriger, %d copies anonymes (E01, E02…), %d réponses.\n\n", count( $questions ), count( $students ), $count_a );

		$doc .= "## Ta mission\n\n";
		$doc .= "Tu aides un enseignant d'école professionnelle à pré-corriger les copies ci-dessous. Tu ne remplaces pas l'enseignant : il relira chacune de tes propositions dans LogiExam. Corrige CHAQUE réponse de CHAQUE élève, question par question, en suivant le barème.\n\n";
		$doc .= "Pour chaque réponse :\n";
		$doc .= "1. LIRE L'ÉLÈVE : écris dans \"lecture\" ce que dit la réponse, reformulé en français correct, idée par idée. Déchiffre fautes, écriture phonétique, abréviations, mots d'atelier. N'ajoute aucune idée, ne corrige pas une erreur de fond.\n";
		$doc .= "2. JUGER CHAQUE CRITÈRE du barème (\"criteres\"), dans l'ordre, avec son numéro n. N'en ajoute pas, n'en oublie pas. S'il n'y a pas de barème détaillé, découpe d'abord reponse_attendue en 1 à 4 idées (\"label\" + \"points\", total = points_max).\n";
		$doc .= "   - \"expected\" : l'élément précis de reponse_attendue visé par ce critère.\n";
		$doc .= "   - \"evidence\" : le passage de la réponse qui remplit le critère, recopié MOT POUR MOT (25 mots maximum). Aucun passage → \"\".\n";
		$doc .= "   - \"matches_expected\" : true seulement si la citation exprime bien CET élément attendu (une information vraie mais différente → false).\n";
		$doc .= "   - \"status\" : \"ok\" (l'idée est exprimée, même avec d'autres mots, un exemple concret correct ou une formulation maladroite), \"partial\" (idée amorcée mais incomplète ou imprécise), \"miss\" (absente, fausse, contradictoire ou hors sujet). evidence vide → obligatoirement \"miss\".\n";
		$doc .= "   - \"points\" : seulement pour \"partial\" (entre 0 et les points du critère). Pour ok et miss, LogiExam fixe les points.\n";
		$doc .= "   - \"explanation\" : une phrase qui dit pourquoi. \"hint\" (partial et miss) : l'idée qui était attendue, en mots simples.\n";
		$doc .= "3. COMMENTAIRE pour l'élève (\"comment\") : court, adressé directement à l'élève " . ( $tu ? 'en le TUTOYANT (« Tu as… », « Il te manque… »)' : 'en le VOUVOYANT (« Vous avez… », « Il vous manque… »)' ) . ", jamais « l'élève », « il » ou « elle ». Cohérent avec tes verdicts : ne félicite pas un critère miss, ne reproche pas un critère ok. Pour chaque critère partial ou miss, dis ce qui était attendu (« Il fallait dire que… »).\n";
		$doc .= "4. \"needs_review\": true si tu hésites vraiment (réponse ambiguë, illisible, ou idée juste mais hors du cours).\n\n";
		$doc .= "Règles d'équité :\n";
		$doc .= "- Juge le SENS, pas les mots : synonymes, termes du métier, ordre différent, idée répartie sur plusieurs phrases, exemple concret qui montre la compréhension → le critère est rempli.\n";
		$doc .= "- L'élève n'a pas à recopier la réponse attendue. Une même phrase peut remplir plusieurs critères. Pour chaque critère refusé, demande-toi : « un enseignant raisonnable accepterait-il ce passage ? » — si oui, accepte.\n";
		$doc .= "- Un mot-clé du cours placé dans une phrase fausse ne suffit pas.\n";
		$doc .= '- ' . trim( wp_strip_all_tags( self::ai_prompt( 'grading_rules' ) ) ) . "\n";
		if ( '' !== trim( (string) ( $level['prompt'] ?? '' ) ) ) {
			$doc .= '- Niveau d’exigence choisi par l’enseignant (' . wp_strip_all_tags( (string) $level['label'] ) . ') : ' . trim( wp_strip_all_tags( (string) $level['prompt'] ) ) . "\n";
		}
		if ( array_filter( $questions, static fn( $q ) => 'calculation' === ( $q['type'] ?? '' ) ) ) {
			$doc .= '- Questions de calcul : ' . trim( wp_strip_all_tags( self::ai_prompt( 'grading_calc' ) ) ) . " Pour ces questions, ajoute \"method_coherent\": true ou false (démarche cohérente).\n";
		}
		if ( '' !== $vocab ) {
			$doc .= '- Vocabulaire du métier : ' . $vocab . "\n";
		}
		$doc .= "\n## Format de réponse OBLIGATOIRE\n\n";
		$doc .= "Réponds UNIQUEMENT par un bloc JSON valide, sans texte avant ni après, exactement de cette forme :\n\n";
		$doc .= "```json\n" . wp_json_encode(
			array(
				'logiexam'    => $token,
				'corrections' => array(
					array(
						'eleve'        => 'E01',
						'question'     => 'Q1',
						'lecture'      => 'ce que dit l’élève, reformulé',
						'criteria'     => array(
							array( 'n' => 1, 'expected' => 'élément attendu', 'evidence' => 'citation exacte', 'matches_expected' => true, 'status' => 'ok', 'explanation' => '…', 'hint' => '' ),
							array( 'n' => 2, 'expected' => 'élément attendu', 'evidence' => '', 'matches_expected' => false, 'status' => 'miss', 'explanation' => '…', 'hint' => 'idée attendue' ),
							array( 'n' => 3, 'expected' => 'élément attendu', 'evidence' => 'citation', 'matches_expected' => true, 'status' => 'partial', 'points' => 0.5, 'explanation' => '…', 'hint' => 'ce qui manquait' ),
						),
						'comment'      => $tu ? 'Tu as… Il te manquait…' : 'Vous avez… Il vous manquait…',
						'needs_review' => false,
					),
				),
			),
			$json
		) . "\n```\n\n";
		$doc .= "- Une entrée par élève ET par question présente dans ses réponses (les réponses vides ne sont pas listées : ne les corrige pas).\n";
		$doc .= "- Pour une question sans barème détaillé, chaque critère porte aussi \"label\" et \"points\".\n";
		$doc .= "- Ne calcule PAS de total : LogiExam additionne les critères.\n";
		$doc .= "- Si ta réponse devient trop longue, arrête-toi après un élève COMPLET, termine le JSON proprement et ajoute \"suite\": \"E07\" (le prochain élève). L'enseignant te demandera de continuer et importera chaque partie.\n\n";

		$reference = trim( LogiEval_Secure_Sensei::get_course_reference_content( absint( $evaluation['sensei_course_id'] ?? 0 ) ) );
		if ( '' !== $reference ) {
			$doc .= "## Référence du cours (reference_theorique_sensei)\n\n" . $reference . "\n\n";
		}
		$doc .= "## Questions et barème\n\n```json\n" . wp_json_encode( $questions, $json ) . "\n```\n\n";
		$doc .= "## Copies des élèves (anonymes)\n\n```json\n" . wp_json_encode( $students, $json ) . "\n```\n";
		return $doc;
	}

	/* ---------------------------------------------------------------------
	 * 2. Import : vérification (aperçu) puis enregistrement
	 * ------------------------------------------------------------------- */

	/** Extrait le JSON du compte rendu (bloc ```json, texte autour, etc.). */
	private static function ai_exchange_parse( string $text ): ?array {
		$text = trim( $text );
		if ( preg_match( '/```(?:json)?\s*(\{.*\})\s*```/su', $text, $m ) ) {
			$text = $m[1];
		}
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) ) {
			$start = strpos( $text, '{' );
			$end   = strrpos( $text, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			}
		}
		if ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['eleve'] ) ) {
			$data = array( 'corrections' => $data ); // L'IA n'a rendu que la liste.
		}
		return is_array( $data ) && is_array( $data['corrections'] ?? null ) ? $data : null;
	}

	public static function handle_ai_import(): void {
		self::require_teacher_post();
		$evaluation = self::require_managed_evaluation( absint( $_POST['evaluation_id'] ?? 0 ) );
		$id         = (int) $evaluation['id'];
		$step       = sanitize_key( wp_unslash( $_POST['ai_import_step'] ?? 'check' ) );
		$back       = static function ( array $args ) use ( $id ): void {
			$args = array_merge( array( 'logiexam_view' => 'evaluation', 'evaluation_id' => $id, 'step' => 'grade' ), $args );
			wp_safe_redirect( self::post_redirect_url( self::evaluation_url( $id, 'grade', $args ), $args ) . '#les-ai-exchange' );
			exit;
		};

		if ( 'cancel' === $step ) {
			delete_transient( self::ai_exchange_transient( $id ) );
			$back( array( 'ai_import' => null ) );
		}

		if ( 'apply' === $step ) {
			$pending = get_transient( self::ai_exchange_transient( $id ) );
			if ( ! is_array( $pending ) ) {
				$back( array( 'ai_import' => 'expired' ) );
			}
			$done = self::ai_exchange_apply( $evaluation, $pending );
			delete_transient( self::ai_exchange_transient( $id ) );
			$back( array( 'ai_import' => 'done', 'ai_imported' => $done ) );
		}

		// Vérification.
		$text = (string) wp_unslash( $_POST['ai_report'] ?? '' );
		if ( ! empty( $_FILES['ai_report_file']['tmp_name'] ) && is_uploaded_file( $_FILES['ai_report_file']['tmp_name'] ) && (int) $_FILES['ai_report_file']['size'] < 5 * MB_IN_BYTES ) {
			$text = (string) file_get_contents( $_FILES['ai_report_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$map  = get_option( self::ai_exchange_option( $id ) );
		$data = self::ai_exchange_parse( $text );
		if ( ! is_array( $map ) ) {
			$back( array( 'ai_import' => 'noexport' ) );
		}
		if ( null === $data ) {
			$back( array( 'ai_import' => 'invalid' ) );
		}
		$token = strtoupper( trim( (string) ( $data['logiexam'] ?? '' ) ) );
		if ( '' !== $token && $token !== $map['token'] ) {
			$back( array( 'ai_import' => 'token' ) );
		}

		$rows     = array();
		$warnings = array();
		$seen     = array();
		foreach ( $data['corrections'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$scode = strtoupper( trim( (string) ( $row['eleve'] ?? '' ) ) );
			$qcode = strtoupper( trim( (string) ( $row['question'] ?? '' ) ) );
			if ( preg_match( '/^E(\d+)$/', $scode, $m ) ) {
				$scode = sprintf( 'E%02d', (int) $m[1] );
			}
			if ( ! isset( $map['students'][ $scode ] ) || ! isset( $map['questions'][ $qcode ] ) ) {
				$warnings[] = sprintf( 'Ligne ignorée : %s / %s n’existe pas dans cet export.', $scode ?: '?', $qcode ?: '?' );
				continue;
			}
			$key          = $scode . '|' . $qcode;
			$seen[ $key ] = true;
			$rows[ $key ] = array(
				'eleve'        => $scode,
				'question'     => $qcode,
				'attempt_id'   => (int) $map['students'][ $scode ],
				'question_id'  => (int) $map['questions'][ $qcode ],
				'lecture'      => sanitize_textarea_field( (string) ( $row['lecture'] ?? '' ) ),
				'criteria'     => is_array( $row['criteria'] ?? null ) ? array_values( array_filter( $row['criteria'], 'is_array' ) ) : array(),
				'points'       => isset( $row['points'] ) && is_numeric( $row['points'] ) ? (float) $row['points'] : null,
				'comment'      => sanitize_textarea_field( (string) ( $row['comment'] ?? $row['commentaire'] ?? '' ) ),
				'analysis'     => sanitize_textarea_field( (string) ( $row['analysis'] ?? $row['analyse'] ?? '' ) ),
				'needs_review' => ! empty( $row['needs_review'] ),
				'method'       => array_key_exists( 'method_coherent', $row ) ? (bool) $row['method_coherent'] : null,
			);
		}
		if ( ! $rows ) {
			$back( array( 'ai_import' => 'empty' ) );
		}
		set_transient(
			self::ai_exchange_transient( $id ),
			array(
				'rows'     => $rows,
				'warnings' => array_slice( $warnings, 0, 20 ),
				'suite'    => sanitize_text_field( (string) ( $data['suite'] ?? '' ) ),
				'model'    => sanitize_text_field( (string) ( $data['modele'] ?? $data['model'] ?? '' ) ),
			),
			2 * HOUR_IN_SECONDS
		);
		$back( array( 'ai_import' => 'preview' ) );
	}

	/** Calcule la proposition d'une ligne (mêmes garde-fous que la pré-correction intégrée). */
	private static function ai_exchange_result( array $answer, array $row, string $model ): array {
		$max   = max( 0, (float) ( $answer['points'] ?? 0 ) );
		$built = self::ai_exchange_criteria( $answer, $max );
		$text  = wp_strip_all_tags( self::readable_answer( (string) ( $answer['answer'] ?? '' ) ) );
		$verdicts = array();
		if ( $row['criteria'] ) {
			$calc = null;
			if ( 'calculation' === ( $answer['type'] ?? '' ) ) {
				$calc = array(
					'result_ok'       => LogiEval_Secure_Question_Types::calculation_result_ok( $answer['expected_answer'] ?? '', json_decode( (string) ( $answer['answer'] ?? '' ), true ) ?: (string) ( $answer['answer'] ?? '' ) ),
					'method_coherent' => $row['method'],
				);
			}
			$graded   = self::ai_grade_from_criteria( array( 'criteria' => $row['criteria'], 'summary' => '' ), $built['criteria'], $built['own_ideas'], $max, $text, $built['tfj_choice'], $calc );
			$points   = (float) $graded['points'];
			$analysis = (string) $graded['analysis'];
			$verdicts = (array) ( $graded['verdicts'] ?? array() );
			$review   = ! empty( $graded['needs_review'] ) || $row['needs_review'];
		} else {
			$points   = max( 0, min( $max, (float) $row['points'] ) );
			$analysis = $row['analysis'];
			$review   = $row['needs_review'];
		}
		list( $points, $note ) = self::cap_points_without_development( $answer, $points, $max );
		$comment = $row['comment'];
		if ( $verdicts ) {
			$comment = self::ai_direct_comment( $comment, $verdicts, $points, $max );
			$comment = self::ai_comment_with_expected( $comment, $verdicts, self::ai_student_address( get_current_user_id() ) );
		}
		$lecture = '' !== $row['lecture'] ? '🧠 Compris par l’IA : « ' . self::shorten( $row['lecture'], 500 ) . ' »' . "\n" : '';
		return array(
			'points'       => self::format_points( $points ),
			'comment'      => sanitize_textarea_field( $comment ),
			'analysis'     => sanitize_textarea_field( $lecture . $analysis . $note ),
			'confidence'   => $review ? 0.5 : 0.8,
			'needs_review' => $review,
			'model'        => 'IA externe (import)' . ( '' !== $model ? ' · ' . $model : '' ),
		);
	}

	private static function ai_exchange_apply( array $evaluation, array $pending ): int {
		$done     = 0;
		$open     = array();
		foreach ( self::ai_exchange_attempts( $evaluation ) as $attempt ) {
			$open[ (int) $attempt['id'] ] = $attempt;
		}
		$answers = array();
		foreach ( (array) $pending['rows'] as $row ) {
			$attempt = $open[ (int) $row['attempt_id'] ] ?? null;
			if ( ! $attempt ) {
				continue; // Copie finalisée entre-temps : on n'y touche pas.
			}
			if ( ! isset( $answers[ $attempt['id'] ] ) ) {
				$answers[ $attempt['id'] ] = array();
				foreach ( self::get_answers_for_attempt( (int) $attempt['id'] ) as $a ) {
					$answers[ $attempt['id'] ][ (int) $a['question_id'] ] = $a;
				}
			}
			$answer = $answers[ $attempt['id'] ][ (int) $row['question_id'] ] ?? null;
			if ( ! $answer || ! self::answer_is_complete( $answer['answer'] ?? '' ) ) {
				continue;
			}
			self::save_ai_grade_result( $attempt, (int) $row['question_id'], self::ai_exchange_result( $answer, $row, (string) ( $pending['model'] ?? '' ) ) );
			$done++;
		}
		// Réponses vides des copies importées : 0 point « Non répondu. », comme la pré-correction intégrée.
		foreach ( array_keys( $answers ) as $attempt_id ) {
			foreach ( $answers[ $attempt_id ] as $qid => $answer ) {
				if ( self::ai_exchange_is_auto_type( (string) $answer['type'] ) || self::answer_is_complete( $answer['answer'] ?? '' ) || is_numeric( $answer['ai_points'] ?? null ) || is_numeric( $answer['manual_points'] ?? null ) ) {
					continue;
				}
				self::save_ai_grade_result(
					$open[ $attempt_id ],
					(int) $qid,
					array(
						'points'       => self::format_points( 0 ),
						'comment'      => self::ai_empty_answer_comment( $answer ),
						'analysis'     => __( 'Aucun élément de réponse n’a été fourni par l’élève. La proposition IA est donc 0 point.', 'logieval-secure' ),
						'confidence'   => 1,
						'needs_review' => false,
						'model'        => 'IA externe (import)',
					)
				);
			}
		}
		return $done;
	}

	/* ---------------------------------------------------------------------
	 * 3. Panneau dans l'étape « Correction »
	 * ------------------------------------------------------------------- */

	private static function render_ai_exchange_panel( array $evaluation ): void {
		$id      = (int) $evaluation['id'];
		$state   = sanitize_key( wp_unslash( $_GET['ai_import'] ?? '' ) );
		$pending = 'preview' === $state ? get_transient( self::ai_exchange_transient( $id ) ) : false;
		$map     = get_option( self::ai_exchange_option( $id ) );
		$export  = wp_nonce_url( add_query_arg( array( 'action' => 'logieval_secure_ai_export', 'evaluation_id' => $id ), admin_url( 'admin-post.php' ) ), self::NONCE_ACTION );
		$open    = '' !== $state;
		$notices = array(
			'invalid'  => array( 'error', __( 'Le compte rendu n’a pas pu être lu : collez la réponse complète de l’IA (le bloc JSON).', 'logieval-secure' ) ),
			'token'    => array( 'error', __( 'Ce compte rendu correspond à un autre fichier d’export. Téléchargez un nouveau fichier et faites-le corriger.', 'logieval-secure' ) ),
			'noexport' => array( 'error', __( 'Téléchargez d’abord le fichier pour l’IA (étape 1).', 'logieval-secure' ) ),
			'empty'    => array( 'error', __( 'Aucune correction reconnue dans ce compte rendu (codes élève ou question introuvables).', 'logieval-secure' ) ),
			'expired'  => array( 'error', __( 'L’aperçu a expiré : vérifiez à nouveau le compte rendu.', 'logieval-secure' ) ),
		);
		?>
		<details class="les-ai-exchange les-ai-panel" id="les-ai-exchange"<?php echo $open ? ' open' : ''; ?>>
			<summary class="les-ai-panel-summary">
				<span class="les-ai-panel-title">🔁 <?php esc_html_e( 'Correction avec une IA externe (Claude, ChatGPT…)', 'logieval-secure' ); ?></span>
				<?php if ( is_array( $map ) ) : ?><span class="les-ai-panel-count"><?php echo esc_html( sprintf( __( 'Dernier export : %s', 'logieval-secure' ), mysql2date( 'j.m.Y H:i', $map['created'] ) ) ); ?></span><?php endif; ?>
				<span class="les-ai-panel-toggle"><span class="les-ai-panel-open"><?php esc_html_e( 'Afficher', 'logieval-secure' ); ?></span><span class="les-ai-panel-close"><?php esc_html_e( 'Réduire', 'logieval-secure' ); ?></span></span>
			</summary>
			<div class="les-ai-panel-body les-ai-exchange-body">
				<?php if ( isset( $notices[ $state ] ) ) : ?>
					<div class="notice notice-<?php echo esc_attr( $notices[ $state ][0] ); ?> inline"><p><?php echo esc_html( $notices[ $state ][1] ); ?></p></div>
				<?php elseif ( 'done' === $state ) : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html( sprintf( _n( '%d correction importée dans la colonne IA. Relisez-les copie par copie : vos points ne sont jamais remplacés.', '%d corrections importées dans la colonne IA. Relisez-les copie par copie : vos points ne sont jamais remplacés.', absint( $_GET['ai_imported'] ?? 0 ), 'logieval-secure' ), absint( $_GET['ai_imported'] ?? 0 ) ) ); ?></p></div>
				<?php endif; ?>

				<?php if ( is_array( $pending ) ) : ?>
					<?php self::render_ai_exchange_preview( $evaluation, $pending ); ?>
				<?php else : ?>
					<ol class="les-ai-exchange-steps">
						<li>
							<strong><?php esc_html_e( 'Téléchargez le fichier pour l’IA', 'logieval-secure' ); ?></strong>
							<p class="description"><?php esc_html_e( 'Il contient les consignes de correction, le barème et les réponses. Les élèves y sont anonymes (E01, E02…) : aucun nom ne quitte votre site.', 'logieval-secure' ); ?></p>
							<p>
								<a class="button les-button-secondary" href="<?php echo esc_url( $export ); ?>">📤 <?php esc_html_e( 'Toutes les réponses', 'logieval-secure' ); ?></a>
								<a class="button les-button-secondary" href="<?php echo esc_url( add_query_arg( 'only_missing', 1, $export ) ); ?>"><?php esc_html_e( 'Seulement les réponses pas encore corrigées', 'logieval-secure' ); ?></a>
							</p>
						</li>
						<li>
							<strong><?php esc_html_e( 'Confiez-le à l’IA', 'logieval-secure' ); ?></strong>
							<p class="description"><?php esc_html_e( 'Joignez le fichier dans Claude, ChatGPT ou une autre IA, avec ce message :', 'logieval-secure' ); ?></p>
							<p><code class="les-ai-exchange-msg"><?php esc_html_e( 'Corrige ces copies en suivant exactement les instructions du fichier, et réponds uniquement avec le bloc JSON demandé.', 'logieval-secure' ); ?></code></p>
						</li>
						<li>
							<strong><?php esc_html_e( 'Importez le compte rendu', 'logieval-secure' ); ?></strong>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="les-ai-exchange-form">
								<?php wp_nonce_field( self::NONCE_ACTION ); ?>
								<?php self::frontend_redirect_field( array( 'logiexam_view' => 'evaluation', 'evaluation_id' => $id, 'step' => 'grade' ) ); ?>
								<?php self::return_field(); ?>
								<input type="hidden" name="action" value="logieval_secure_ai_import">
								<input type="hidden" name="ai_import_step" value="check">
								<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $id ); ?>">
								<textarea name="ai_report" rows="6" placeholder="<?php esc_attr_e( 'Collez ici la réponse de l’IA (le bloc JSON)…', 'logieval-secure' ); ?>"></textarea>
								<p class="les-ai-exchange-file"><label><?php esc_html_e( 'ou un fichier :', 'logieval-secure' ); ?> <input type="file" name="ai_report_file" accept=".json,.txt,.md,text/plain,application/json"></label></p>
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Vérifier le compte rendu', 'logieval-secure' ); ?></button>
							</form>
						</li>
					</ol>
				<?php endif; ?>
			</div>
		</details>
		<?php
	}

	private static function render_ai_exchange_preview( array $evaluation, array $pending ): void {
		$id   = (int) $evaluation['id'];
		$open = array();
		foreach ( self::ai_exchange_attempts( $evaluation ) as $attempt ) {
			$open[ (int) $attempt['id'] ] = $attempt;
		}
		$by = array();
		foreach ( (array) $pending['rows'] as $row ) {
			$by[ $row['eleve'] ][] = $row;
		}
		ksort( $by );
		$map       = get_option( self::ai_exchange_option( $id ) );
		$expected  = is_array( $map ) ? count( (array) $map['questions'] ) : 0;
		$skipped   = 0;
		$total_ok  = 0;
		$empty     = 0;
		?>
		<h4><?php esc_html_e( 'Aperçu avant import', 'logieval-secure' ); ?></h4>
		<table class="widefat striped les-ai-exchange-table">
			<thead><tr><th><?php esc_html_e( 'Élève', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Questions corrigées', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Points proposés', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'À vérifier', 'logieval-secure' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $by as $code => $rows ) : ?>
				<?php
				$attempt = $open[ (int) $rows[0]['attempt_id'] ] ?? null;
				$points  = 0.0;
				$max     = 0.0;
				$review  = 0;
				$usable  = 0;
				if ( $attempt ) {
					$answers = array();
					foreach ( self::get_answers_for_attempt( (int) $attempt['id'] ) as $a ) {
						$answers[ (int) $a['question_id'] ] = $a;
					}
					foreach ( $rows as $row ) {
						$a = $answers[ (int) $row['question_id'] ] ?? null;
						if ( ! $a || ! self::answer_is_complete( $a['answer'] ?? '' ) ) {
							$empty++;
							continue; // Réponse vide : 0 point « Non répondu. » de toute façon.
						}
						$res     = self::ai_exchange_result( $a, $row, '' );
						$points += (float) self::parse_decimal( $res['points'] );
						$max    += (float) $a['points'];
						$review += $res['needs_review'] ? 1 : 0;
						$total_ok++;
						$usable++;
					}
				} else {
					$skipped += count( $rows );
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $code ); ?></strong> · <?php echo $attempt ? esc_html( self::student_full_name( (int) $attempt['user_id'] ) ) : '<em>' . esc_html__( 'copie déjà finalisée : ignorée', 'logieval-secure' ) . '</em>'; ?></td>
					<td><?php echo esc_html( (string) $usable ); ?></td>
					<td><?php echo $attempt ? esc_html( self::format_points( $points ) . ' / ' . self::format_points( $max ) ) : '—'; ?></td>
					<td><?php echo $review ? esc_html( '⚠ ' . $review ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $empty ) : ?>
			<p class="description"><?php echo esc_html( sprintf( _n( '%d ligne concerne une réponse vide : elle est ignorée (0 point « Non répondu. »).', '%d lignes concernent des réponses vides : elles sont ignorées (0 point « Non répondu. »).', $empty, 'logieval-secure' ), $empty ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $pending['warnings'] ) ) : ?>
			<ul class="les-ai-exchange-warnings"><?php foreach ( $pending['warnings'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<?php if ( '' !== ( $pending['suite'] ?? '' ) ) : ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Compte rendu partiel : l’IA s’est arrêtée avant %s. Importez cette partie, puis demandez-lui « Continue » et importez la suite de la même façon.', 'logieval-secure' ), $pending['suite'] ) ); ?></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Les propositions arrivent dans la colonne IA, comme une pré-correction : vos points et vos commentaires ne sont jamais remplacés. Les réponses vides reçoivent 0 point « Non répondu. ».', 'logieval-secure' ); ?></p>
		<div class="les-actions">
			<?php foreach ( array( 'apply' => __( 'Importer %d corrections', 'logieval-secure' ), 'cancel' => __( 'Annuler', 'logieval-secure' ) ) as $step => $label ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="les-inline">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<?php self::frontend_redirect_field( array( 'logiexam_view' => 'evaluation', 'evaluation_id' => $id, 'step' => 'grade' ) ); ?>
					<?php self::return_field(); ?>
					<input type="hidden" name="action" value="logieval_secure_ai_import">
					<input type="hidden" name="ai_import_step" value="<?php echo esc_attr( $step ); ?>">
					<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $id ); ?>">
					<button type="submit" class="button <?php echo 'apply' === $step ? 'button-primary' : 'les-button-secondary'; ?>"<?php disabled( 'apply' === $step && ! $total_ok ); ?>><?php echo esc_html( 'apply' === $step ? sprintf( $label, $total_ok ) : $label ); ?></button>
				</form>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
