<?php
/**
 * Version élève à imprimer (plan B en cas de panne) : l'évaluation sans aucune réponse ni critère,
 * avec la place pour écrire, en PDF ou en Word. Disponible avant la passation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Evaluation_Paper {

	/** Contenu de la version élève (aucune bonne réponse, aucun critère, éléments à ordonner mélangés). */
	private static function evaluation_paper_data( array $evaluation ): array {
		$questions = self::get_questions_for_evaluation( (int) $evaluation['id'] );
		$total     = 0.0;
		foreach ( $questions as $q ) {
			$total += (float) ( (float) $q['evaluation_points'] > 0 ? $q['evaluation_points'] : $q['points'] );
		}
		$info = array_values(
			array_filter(
				array(
					array( 'Évaluation', (string) $evaluation['title'] ),
					self::evaluation_course_name( $evaluation ) ? array( 'Cours', self::evaluation_course_name( $evaluation ) ) : null,
					array( 'Durée', (int) $evaluation['duration_minutes'] . ' min' ),
					array( 'Questions', (string) count( $questions ) . ' · ' . self::format_points( $total ) . ' pts' ),
				)
			)
		);
		$intro = array_filter(
			array(
				'Consignes'            => self::pdf_plain_text( (string) ( $evaluation['instructions'] ?? '' ) ),
				'Entreprise'           => self::pdf_plain_text( (string) ( $evaluation['company_name'] ?? '' ) ),
				'Rôle de l’apprenti·e' => self::pdf_plain_text( (string) ( $evaluation['apprentice_role'] ?? '' ) ),
				'Contexte'             => self::pdf_plain_text( (string) ( $evaluation['company_context'] ?? '' ) ),
				'Introduction'         => self::pdf_plain_text( (string) ( $evaluation['story_intro'] ?? '' ) ),
			),
			static fn( $v ) => '' !== $v
		);
		$items = array();
		foreach ( $questions as $q ) {
			$type    = (string) $q['type'];
			$choices = array();
			if ( in_array( $type, array( 'qcm', 'multiple_choice' ), true ) ) {
				foreach ( (array) json_decode( (string) $q['options'], true ) as $opt ) {
					if ( is_scalar( $opt ) && '' !== trim( (string) $opt ) ) {
						$choices[] = (string) $opt;
					}
				}
			}
			$order = array();
			$mode  = 'items';
			if ( LogiEval_Secure_Question_Types::ORDERING === $type ) {
				$mode    = LogiEval_Secure_Question_Types::order_settings( $q['expected_answer'] ?? '' )['mode'];
				$by_id   = array();
				foreach ( LogiEval_Secure_Question_Types::order_items( $q['options'] ?? '' ) as $item ) {
					$by_id[ $item['id'] ] = $item;
				}
				// Même mélange que pour un élève en ligne (jamais le bon ordre), lettres A, B, C… pour répondre.
				foreach ( LogiEval_Secure_Question_Types::student_items( $q, 0 ) as $i => $pub ) {
					$item    = $by_id[ $pub['id'] ] ?? array( 'text' => (string) $pub['text'], 'media_id' => 0 );
					$order[] = array( 'letter' => chr( 65 + ( $i % 26 ) ), 'text' => (string) $item['text'], 'media_id' => (int) $item['media_id'] );
				}
			}
			// 1.36 : Relier (colonne de droite mélangée, lettres) et texte à trous (trous en lignes).
			$match  = array();
			$blanks = array();
			if ( LogiEval_Secure_Question_Types::MATCHING === $type ) {
				$m     = LogiEval_Secure_Question_Types::student_match( $q, 0 );
				$match = array(
					'left'  => wp_list_pluck( $m['left'], 'text' ),
					'right' => wp_list_pluck( $m['right'], 'text' ),
				);
			} elseif ( LogiEval_Secure_Question_Types::BLANKS === $type ) {
				$b      = LogiEval_Secure_Question_Types::student_blanks( $q, 0 );
				$plain  = '';
				$k      = 0;
				foreach ( $b['parts'] as $part ) {
					$plain .= null === $part ? ' (' . ( ++$k ) . ') ____________ ' : (string) $part;
				}
				$blanks = array( 'text' => trim( preg_replace( '/[ \t]+/', ' ', $plain ) ), 'bank' => $b['bank'] );
			}
			$items[] = array(
				'match'     => $match,
				'blanks'    => $blanks,
				'context'   => self::pdf_plain_text( (string) ( $q['context'] ?? '' ) ),
				'prompt'    => self::pdf_plain_text( (string) $q['prompt'] ),
				'type'      => $type,
				'points'    => (float) ( (float) $q['evaluation_points'] > 0 ? $q['evaluation_points'] : $q['points'] ),
				'precision' => 'calculation' === $type ? LogiEval_Secure_Question_Types::precision_label( LogiEval_Secure_Question_Types::calculation_precision( $q['expected_answer'] ?? '' ) ) : '',
				'choices'   => $choices,
				'order'     => $order,
				'mode'      => $mode,
				'media_id'  => absint( $q['media_id'] ?? 0 ),
			);
		}
		return array( 'info' => $info, 'intro' => $intro, 'total' => $total, 'questions' => $items );
	}

	/** Nombre de lignes d'écriture selon le type et les points. */
	private static function paper_write_lines( array $q ): int {
		switch ( $q['type'] ) {
			case 'short_answer':
				return 3;
			case 'long_answer':
			case 'manual':
			case 'mes':
				return (int) max( 6, min( 16, ceil( $q['points'] * 2 ) + 2 ) );
			default:
				return 5;
		}
	}

	/* ---------------------------------------------------------------------
	 * PDF
	 * ------------------------------------------------------------------- */

	private static function build_evaluation_paper_pdf( array $evaluation, array $data ): string {
		self::$pdf_images = array();
		$doc = array( 'pages' => array(), 'content' => '', 'y' => 0, 'header' => 'Évaluation – version papier' );
		self::pdf_report_new_page( $doc );

		// Identité de l'élève et cadre de correction.
		$top = $doc['y'];
		$h   = 104;
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $h, 511, $h, 248, 250, 252 );
		$doc['content'] .= self::pdf_rect( 42, $top - $h, 511, $h );
		foreach ( array( array( 'Nom', 58, $top - 30 ), array( 'Prénom', 300, $top - 30 ), array( 'Classe', 58, $top - 62 ), array( 'Date', 300, $top - 62 ) ) as $f ) {
			$doc['content'] .= self::pdf_text_styled( $f[1], $f[2], 10, $f[0] . ' :', 'F2', array( 49, 58, 69 ) );
			$doc['content'] .= self::pdf_fill_rect( $f[1] + 52, $f[2] - 3, 175, 0.8, 120, 130, 140 );
		}
		$doc['content'] .= self::pdf_text_styled( 58, $top - 92, 10, 'Points : ______ / ' . self::format_points( $data['total'] ), 'F2', array( 49, 58, 69 ) );
		$doc['content'] .= self::pdf_text_styled( 300, $top - 92, 10, 'Note : ______', 'F2', array( 49, 58, 69 ) );
		$doc['y'] -= $h + 34;

		self::pdf_report_section_title( $doc, (string) $evaluation['title'] );
		self::pdf_report_info_grid( $doc, $data['info'] );
		foreach ( $data['intro'] as $label => $text ) {
			self::pdf_report_note_box( $doc, $label, $text );
		}

		// Les questions commencent sur une nouvelle page : la page 1 peut être distribuée à part.
		self::pdf_report_new_page( $doc );
		self::pdf_report_section_title( $doc, 'Questions' );
		$current_context = null;
		foreach ( $data['questions'] as $i => $q ) {
			$card = self::pdf_paper_card_blocks( $i + 1, $q );
			if ( '' !== $q['context'] && $q['context'] !== $current_context ) {
				$ch = max( 48, 34 + ( count( self::pdf_wrap_lines( $q['context'], 92 ) ) * 14 ) + 8 ) + 28;
				self::pdf_report_ensure_space( $doc, $ch + min( $card['height'], 300 ) + 8 );
				self::pdf_report_context_box( $doc, $q['context'] );
				$current_context = $q['context'];
			}
			self::pdf_draw_key_card( $doc, $card );
		}
		if ( '' !== $doc['content'] ) {
			$doc['pages'][] = $doc['content'];
		}
		return self::build_pdf_document( $doc['pages'] );
	}

	/** Carte d'une question pour l'élève : énoncé, points, image, puis la zone de réponse selon le type. */
	private static function pdf_paper_card_blocks( int $number, array $q ): array {
		$ink    = array( 49, 58, 69 );
		$blocks = array();
		$blocks[] = array( 'lines', self::pdf_wrap_lines( $q['prompt'], 64 ), 11, 'F2', array( 17, 24, 39 ), 14 );
		// 1.49.2 : points de la question bien visibles, avec une case pour noter les points obtenus à la main.
		$meta     = 'Points : ______ / ' . self::format_points( $q['points'] ) . ' pts' . ( '' !== $q['precision'] ? '   ·   ' . $q['precision'] : '' );
		$blocks[] = array( 'lines', array( $meta ), 10, 'F2', array( 6, 112, 48 ), 14 );
		if ( $q['media_id'] ) {
			$img = self::pdf_register_image( $q['media_id'] );
			if ( '' !== $img ) {
				$blocks[] = array( 'image', $img, 220, 120 );
			}
		}
		switch ( $q['type'] ) {
			case 'qcm':
			case 'multiple_choice':
				$blocks[] = array( 'lines', array( 'multiple_choice' === $q['type'] ? 'Cochez toutes les bonnes réponses.' : 'Cochez la bonne réponse.' ), 9, 'F1', array( 100, 110, 120 ), 12 );
				foreach ( $q['choices'] as $c ) {
					$blocks[] = array( 'check', self::pdf_wrap_lines( $c, 80 ) );
				}
				break;
			case 'true_false':
				$blocks[] = array( 'checkrow', array( 'Vrai', 'Faux' ) );
				break;
			case LogiEval_Secure_Question_Types::TFJ:
				$blocks[] = array( 'checkrow', array( 'Vrai', 'Faux' ) );
				$blocks[] = array( 'lines', array( 'Justification :' ), 10, 'F2', $ink, 13 );
				$blocks[] = array( 'write', 4 );
				break;
			case LogiEval_Secure_Question_Types::ORDERING:
				if ( 'letters' === $q['mode'] ) {
					$blocks[] = array( 'lines', array( 'Lettres mélangées : ' . implode( '  ', wp_list_pluck( $q['order'], 'text' ) ) ), 12, 'F2', $ink, 16 );
					$blocks[] = array( 'lines', array( 'Écrivez le mot, une lettre par case :' ), 9, 'F1', array( 100, 110, 120 ), 12 );
				} else {
					if ( array_filter( wp_list_pluck( $q['order'], 'media_id' ) ) ) {
						$cells = array();
						foreach ( $q['order'] as $o ) {
							$cells[] = array( 'img' => $o['media_id'] ? self::pdf_register_image( $o['media_id'] ) : '', 'caption' => self::pdf_wrap_lines( $o['letter'] . '. ' . $o['text'], 30 ) );
						}
						$blocks[] = array( 'grid', $cells );
					} else {
						foreach ( $q['order'] as $o ) {
							$blocks[] = array( 'lines', self::pdf_wrap_lines( $o['letter'] . '. ' . $o['text'], 84 ), 10, 'F1', array( 15, 23, 42 ), 13 );
						}
					}
					$blocks[] = array( 'lines', array( 'Remettez dans l’ordre : écrivez les lettres dans les cases 1, 2, 3…' ), 9, 'F1', array( 100, 110, 120 ), 12 );
				}
				$blocks[] = array( 'slots', count( $q['order'] ) );
				break;
			case LogiEval_Secure_Question_Types::MATCHING:
				foreach ( $q['match']['left'] as $i => $t ) {
					$blocks[] = array( 'lines', self::pdf_wrap_lines( ( $i + 1 ) . '. ' . $t, 84 ), 10, 'F2', array( 15, 23, 42 ), 13 );
				}
				$blocks[] = array( 'lines', array( '' ), 6, 'F1', $ink, 6 );
				foreach ( $q['match']['right'] as $i => $t ) {
					$blocks[] = array( 'lines', self::pdf_wrap_lines( chr( 65 + ( $i % 26 ) ) . '. ' . $t, 84 ), 10, 'F1', array( 15, 23, 42 ), 13 );
				}
				$blocks[] = array( 'lines', array( 'Reliez : écrivez dans chaque case la lettre qui va avec le numéro 1, 2, 3…' ), 9, 'F1', array( 100, 110, 120 ), 12 );
				$blocks[] = array( 'slots', count( $q['match']['left'] ) );
				break;
			case LogiEval_Secure_Question_Types::BLANKS:
				if ( ! empty( $q['blanks']['bank'] ) ) {
					$blocks[] = array( 'lines', self::pdf_wrap_lines( 'Mots à placer : ' . implode( ' · ', $q['blanks']['bank'] ), 84 ), 10, 'F2', $ink, 14 );
				}
				$blocks[] = array( 'lines', self::pdf_wrap_lines( (string) ( $q['blanks']['text'] ?? '' ), 78 ), 11, 'F1', array( 15, 23, 42 ), 22 );
				break;
			case 'calculation':
				$blocks[] = array( 'lines', array( 'Développement :' ), 10, 'F2', $ink, 13 );
				$blocks[] = array( 'write', 7 );
				$blocks[] = array( 'lines', array( 'Résultat : ' . str_repeat( '_', 30 ) ), 11, 'F2', $ink, 18 );
				break;
			default:
				$blocks[] = array( 'write', self::paper_write_lines( $q ) );
		}
		return array( 'number' => $number, 'points' => $q['points'], 'blocks' => $blocks, 'height' => self::pdf_card_height( $blocks ) );
	}

	/** Hauteur d'une carte (corrigé ou version papier). */
	private static function pdf_card_height( array $blocks ): int {
		$height = 26;
		foreach ( $blocks as $b ) {
			switch ( $b[0] ) {
				case 'lines':
					$height += count( $b[1] ) * $b[5] + 4;
					break;
				case 'image':
					$height += $b[3] + 8;
					break;
				case 'write':
					$height += $b[1] * 22 + 6;
					break;
				case 'check':
					$height += count( $b[1] ) * 13 + 6;
					break;
				case 'checkrow':
					$height += 22;
					break;
				case 'slots':
					$height += (int) ceil( $b[1] / 12 ) * 34 + 4;
					break;
				default:
					foreach ( array_chunk( $b[1], 3 ) as $row ) {
						$height += 92 + 8 + max( array_map( static fn( $c ) => count( $c['caption'] ), $row ) ) * 11 + 10;
					}
			}
		}
		return $height + 8;
	}

	/** Dessin des zones de réponse (appelé par pdf_draw_key_card). Retourne la nouvelle position verticale. */
	private static function pdf_draw_answer_block( array &$doc, array $b, float $y ): float {
		switch ( $b[0] ) {
			case 'write':
				for ( $i = 0; $i < $b[1]; $i++ ) {
					$y -= 22;
					$doc['content'] .= self::pdf_fill_rect( 82, $y + 4, 455, 0.6, 175, 182, 190 );
				}
				return $y - 6;
			case 'check':
				foreach ( $b[1] as $k => $line ) {
					if ( 0 === $k ) {
						$doc['content'] .= self::pdf_rect( 82, $y - 2, 9, 9 );
					}
					$doc['content'] .= self::pdf_text_styled( 98, $y, 10, $line, 'F1', array( 15, 23, 42 ) );
					$y -= 13;
				}
				return $y - 6;
			case 'checkrow':
				foreach ( $b[1] as $k => $label ) {
					$x = 82 + $k * 90;
					$doc['content'] .= self::pdf_rect( $x, $y - 2, 9, 9 );
					$doc['content'] .= self::pdf_text_styled( $x + 15, $y, 10, $label, 'F1', array( 15, 23, 42 ) );
				}
				return $y - 22;
			case 'slots':
				for ( $i = 0; $i < $b[1]; $i++ ) {
					$x  = 82 + ( $i % 12 ) * 37;
					$yy = $y - (int) floor( $i / 12 ) * 34;
					$doc['content'] .= self::pdf_rect( $x, $yy - 22, 30, 24 );
					$doc['content'] .= self::pdf_text_styled( $x + 2, $yy - 6, 6, (string) ( $i + 1 ), 'F1', array( 120, 130, 140 ) );
				}
				return $y - (int) ceil( $b[1] / 12 ) * 34 - 4;
		}
		return $y;
	}

	/* ---------------------------------------------------------------------
	 * Word (.docx)
	 * ------------------------------------------------------------------- */

	/** Lignes d'écriture : tableau sans bordures verticales, une ligne par rangée. */
	private static function docx_write_lines( int $n, int $indent = 280 ): string {
		$rows = '';
		for ( $i = 0; $i < $n; $i++ ) {
			$rows .= '<w:tr><w:trPr><w:trHeight w:val="440" w:hRule="exact"/></w:trPr><w:tc><w:tcPr><w:tcW w:w="9300" w:type="dxa"/></w:tcPr><w:p><w:pPr><w:spacing w:after="0"/></w:pPr></w:p></w:tc></w:tr>';
		}
		return '<w:tbl><w:tblPr><w:tblW w:w="9300" w:type="dxa"/><w:tblInd w:w="' . $indent . '" w:type="dxa"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:right w:val="nil"/><w:bottom w:val="single" w:sz="4" w:color="AFB6BE"/><w:insideH w:val="single" w:sz="4" w:color="AFB6BE"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="9300"/></w:tblGrid>' . $rows . '</w:tbl>' . self::docx_p( '', array( 'after' => 60 ) );
	}

	/** Cases numérotées pour écrire l'ordre ou les lettres d'un mot. */
	private static function docx_slots( int $n ): string {
		$cells = '';
		$grid  = '';
		for ( $i = 0; $i < $n; $i++ ) {
			$grid  .= '<w:gridCol w:w="620"/>';
			$cells .= '<w:tc><w:tcPr><w:tcW w:w="620" w:type="dxa"/></w:tcPr>' . self::docx_p( (string) ( $i + 1 ), array( 'size' => 6, 'color' => '8A939C', 'after' => 0 ) ) . '</w:tc>';
		}
		return '<w:tbl><w:tblPr><w:tblW w:w="' . ( 620 * $n ) . '" w:type="dxa"/><w:tblInd w:w="280" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="6" w:color="8A939C"/><w:left w:val="single" w:sz="6" w:color="8A939C"/><w:bottom w:val="single" w:sz="6" w:color="8A939C"/><w:right w:val="single" w:sz="6" w:color="8A939C"/><w:insideV w:val="single" w:sz="6" w:color="8A939C"/></w:tblBorders></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid><w:tr><w:trPr><w:trHeight w:val="520" w:hRule="exact"/></w:trPr>' . $cells . '</w:tr></w:tbl>' . self::docx_p( '', array( 'after' => 60 ) );
	}

	private static function build_evaluation_paper_docx( array $evaluation, array $data ): string {
		$media = array();
		$body  = '';
		$logo  = self::docx_image_run( $media, 0, LOGIEVAL_SECURE_PATH . 'assets/pdf-logo.png', 1900000, 500000 );
		$body .= '<w:p><w:pPr><w:shd w:val="clear" w:color="auto" w:fill="313A45"/><w:spacing w:after="0"/></w:pPr>' . $logo . '</w:p>';
		$body .= self::docx_p( 'Évaluation – version papier', array( 'bold' => true, 'color' => 'FFFFFF', 'size' => 12, 'shade' => '313A45', 'border' => true, 'after' => 240 ) );

		// Identité de l'élève et cadre de correction.
		$cell = static fn( $label ) => array( 'xml' => self::docx_p( $label . ' :', array( 'bold' => true, 'size' => 10, 'after' => 220 ) ), 'fill' => 'F6F7F3' );
		$body .= self::docx_table(
			array(
				array( $cell( 'Nom' ), $cell( 'Prénom' ) ),
				array( $cell( 'Classe' ), $cell( 'Date' ) ),
				array( array( 'xml' => self::docx_p( 'Points : ______ / ' . self::format_points( $data['total'] ), array( 'bold' => true, 'size' => 10, 'after' => 220 ) ), 'fill' => 'F6F7F3' ), $cell( 'Note' ) ),
			),
			array( 4800, 4800 )
		);
		$body .= self::docx_p( (string) $evaluation['title'], array( 'bold' => true, 'size' => 18, 'before' => 120, 'after' => 80 ) );
		$body .= self::docx_p( implode( ' · ', array_map( static fn( $i ) => $i[0] . ' : ' . $i[1], array_slice( $data['info'], 1 ) ) ), array( 'size' => 10, 'color' => '5B6672', 'after' => 160 ) );
		foreach ( $data['intro'] as $label => $text ) {
			$body .= self::docx_p( $label, array( 'bold' => true, 'size' => 10, 'color' => '067030', 'after' => 20, 'keep' => true ) ) . self::docx_p( $text, array( 'size' => 10 ) );
		}
		$body .= self::docx_p( 'Questions', array( 'bold' => true, 'size' => 13, 'border' => true, 'before' => 160, 'after' => 160, 'page_break' => true ) );

		$hint            = array( 'size' => 9, 'color' => '64707C', 'indent' => 280, 'after' => 40, 'keep' => true );
		$current_context = null;
		foreach ( $data['questions'] as $i => $q ) {
			if ( '' !== $q['context'] && $q['context'] !== $current_context ) {
				$body .= self::docx_p( 'CONTEXTE / SECTION', array( 'bold' => true, 'size' => 8, 'color' => '3B8235', 'shade' => 'DCF7D2', 'after' => 0, 'keep' => true ) );
				$body .= self::docx_p( $q['context'], array( 'size' => 10, 'shade' => 'DCF7D2', 'after' => 160, 'keep' => true ) );
				$current_context = $q['context'];
			}
			$body .= self::docx_p( ( $i + 1 ) . '. ' . $q['prompt'], array( 'bold' => true, 'size' => 11, 'after' => 40, 'keep' => true, 'before' => 160 ) );
			$body .= self::docx_p( 'Points : ______ / ' . self::format_points( $q['points'] ) . ' pts' . ( '' !== $q['precision'] ? '   ·   ' . $q['precision'] : '' ), array( 'bold' => true, 'size' => 10, 'color' => '067030', 'after' => 60, 'keep' => true, 'indent' => 280 ) );
			if ( $q['media_id'] ) {
				$run = self::docx_image_run( $media, $q['media_id'], '', 3200000, 1900000 );
				if ( '' !== $run ) {
					$body .= '<w:p><w:pPr><w:ind w:left="280"/><w:keepNext/></w:pPr>' . $run . '</w:p>';
				}
			}
			switch ( $q['type'] ) {
				case 'qcm':
				case 'multiple_choice':
					$body .= self::docx_p( 'multiple_choice' === $q['type'] ? 'Cochez toutes les bonnes réponses.' : 'Cochez la bonne réponse.', $hint );
					foreach ( $q['choices'] as $c ) {
						$body .= self::docx_p( "\u{2610}  " . $c, array( 'size' => 11, 'indent' => 280, 'after' => 60 ) );
					}
					break;
				case 'true_false':
					$body .= self::docx_p( "\u{2610}  Vrai          \u{2610}  Faux", array( 'size' => 11, 'indent' => 280, 'after' => 120 ) );
					break;
				case LogiEval_Secure_Question_Types::TFJ:
					$body .= self::docx_p( "\u{2610}  Vrai          \u{2610}  Faux", array( 'size' => 11, 'indent' => 280, 'after' => 80 ) );
					$body .= self::docx_p( 'Justification :', array( 'bold' => true, 'size' => 10, 'indent' => 280, 'after' => 0, 'keep' => true ) );
					$body .= self::docx_write_lines( 4 );
					break;
				case LogiEval_Secure_Question_Types::ORDERING:
					if ( 'letters' === $q['mode'] ) {
						$body .= self::docx_p( 'Lettres mélangées : ' . implode( '  ', wp_list_pluck( $q['order'], 'text' ) ), array( 'bold' => true, 'size' => 12, 'indent' => 280, 'after' => 60, 'keep' => true ) );
						$body .= self::docx_p( 'Écrivez le mot, une lettre par case :', $hint );
					} else {
						if ( array_filter( wp_list_pluck( $q['order'], 'media_id' ) ) ) {
							$rows = array();
							foreach ( array_chunk( $q['order'], 3 ) as $chunk ) {
								$row = array();
								foreach ( $chunk as $o ) {
									$img   = $o['media_id'] ? self::docx_image_run( $media, $o['media_id'], '', 1700000, 1050000 ) : '';
									$row[] = array( 'xml' => ( $img ? '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>' . $img . '</w:p>' : '' ) . self::docx_p( $o['letter'] . '. ' . $o['text'], array( 'size' => 10, 'bold' => true ) ) );
								}
								while ( count( $row ) < 3 ) {
									$row[] = array( 'xml' => self::docx_p( '' ) );
								}
								$rows[] = $row;
							}
							$body .= self::docx_table( $rows, array( 3150, 3150, 3150 ) );
						} else {
							foreach ( $q['order'] as $o ) {
								$body .= self::docx_p( $o['letter'] . '. ' . $o['text'], array( 'size' => 10, 'indent' => 280, 'after' => 40, 'keep' => true ) );
							}
						}
						$body .= self::docx_p( 'Remettez dans l’ordre : écrivez les lettres dans les cases 1, 2, 3…', $hint );
					}
					$body .= self::docx_slots( count( $q['order'] ) );
					break;
				case LogiEval_Secure_Question_Types::MATCHING:
					$rows = array();
					$n    = max( count( $q['match']['left'] ), count( $q['match']['right'] ) );
					for ( $i = 0; $i < $n; $i++ ) {
						$rows[] = array(
							array( 'xml' => self::docx_p( isset( $q['match']['left'][ $i ] ) ? ( $i + 1 ) . '. ' . $q['match']['left'][ $i ] : '', array( 'size' => 10, 'bold' => true ) ) ),
							array( 'xml' => self::docx_p( isset( $q['match']['right'][ $i ] ) ? chr( 65 + ( $i % 26 ) ) . '. ' . $q['match']['right'][ $i ] : '', array( 'size' => 10 ) ) ),
						);
					}
					$body .= self::docx_table( $rows, array( 4725, 4725 ) );
					$body .= self::docx_p( 'Reliez : écrivez dans chaque case la lettre qui va avec le numéro 1, 2, 3…', $hint );
					$body .= self::docx_slots( count( $q['match']['left'] ) );
					break;
				case LogiEval_Secure_Question_Types::BLANKS:
					if ( ! empty( $q['blanks']['bank'] ) ) {
						$body .= self::docx_p( 'Mots à placer : ' . implode( ' · ', $q['blanks']['bank'] ), array( 'bold' => true, 'size' => 10, 'indent' => 280, 'after' => 80, 'keep' => true ) );
					}
					$body .= self::docx_p( (string) ( $q['blanks']['text'] ?? '' ), array( 'size' => 11, 'indent' => 280, 'after' => 120, 'line' => 480 ) );
					break;
				case 'calculation':
					$body .= self::docx_p( 'Développement :', array( 'bold' => true, 'size' => 10, 'indent' => 280, 'after' => 0, 'keep' => true ) );
					$body .= self::docx_write_lines( 7 );
					$body .= self::docx_p( 'Résultat : ' . str_repeat( '_', 30 ), array( 'bold' => true, 'size' => 11, 'indent' => 280, 'after' => 120 ) );
					break;
				default:
					$body .= self::docx_write_lines( self::paper_write_lines( $q ) );
			}
		}
		return self::docx_package( $body, $media );
	}
}
