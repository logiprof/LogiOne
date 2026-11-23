<?php
/**
 * LogiExam (LogiOne 1.20) : barème des notes (à la demi) et export d'une évaluation complète avec son corrigé,
 * en PDF ou en Word (.docx) : informations, barème, questions, réponses attendues, critères, images.
 * La version élève à imprimer (sans réponses) est dans trait-evaluation-paper.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Evaluation_Export {

	/* ---------------------------------------------------------------------
	 * Barème
	 * ------------------------------------------------------------------- */

	/**
	 * Barème suisse (note = points / total × 5 + 1, arrondie à la demi) : pour chaque note, les points
	 * minimum et maximum (au quart de point).
	 * @return array<int, array{grade:float, min:float, max:float}>
	 */
	public static function grade_scale( float $total ): array {
		$rows = array();
		if ( $total <= 0 ) {
			return $rows;
		}
		$steps = (int) round( $total * 4 );
		$by    = array();
		for ( $i = 0; $i <= $steps; $i++ ) {
			$p = $i / 4;
			$g = LogiEval_Secure_Grading::grade( $p, $total );
			if ( null === $g ) {
				continue;
			}
			$key = (string) $g;
			if ( ! isset( $by[ $key ] ) ) {
				$by[ $key ] = array( 'grade' => (float) $g, 'min' => $p, 'max' => $p );
			}
			$by[ $key ]['max'] = $p;
		}
		$rows = array_values( $by );
		usort( $rows, static fn( $a, $b ) => $b['grade'] <=> $a['grade'] );
		return $rows;
	}

	private static function grade_label( float $grade ): string {
		return rtrim( rtrim( number_format( $grade, 1, ',', '' ), '0' ), ',' );
	}

	/** Encadré « Barème » de l'éditeur (recalculé en direct quand les points des questions changent). */
	public static function render_bareme_panel( float $total ): void {
		?>
		<section class="les-panel les-bareme" data-les-bareme>
			<div class="les-bareme-head">
				<h3><?php esc_html_e( 'Barème', 'logieval-secure' ); ?></h3>
				<p><?php esc_html_e( 'Notes à la demi : note = points ÷ total × 5 + 1. Total actuel :', 'logieval-secure' ); ?> <strong data-les-bareme-total><?php echo esc_html( self::format_points( $total ) ); ?></strong> pts</p>
			</div>
			<div class="les-bareme-grid" data-les-bareme-grid>
				<?php foreach ( self::grade_scale( $total ) as $row ) : ?>
					<div class="les-bareme-cell"><strong><?php echo esc_html( self::grade_label( $row['grade'] ) ); ?></strong><span><?php echo esc_html( self::format_points( $row['min'] ) . ' – ' . self::format_points( $row['max'] ) ); ?> pts</span></div>
				<?php endforeach; ?>
			</div>
			<?php if ( $total <= 0 ) : ?>
				<p class="description" data-les-bareme-empty><?php esc_html_e( 'Ajoutez des points aux questions : le barème apparaît ici.', 'logieval-secure' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Contenu commun aux deux formats
	 * ------------------------------------------------------------------- */

	private static function evaluation_export_data( array $evaluation ): array {
		$questions = self::get_questions_for_evaluation( (int) $evaluation['id'] );
		$total     = 0.0;
		foreach ( $questions as $q ) {
			$total += (float) ( $q['evaluation_points'] ?: $q['points'] );
		}
		$types = array( 'mes_company' => 'MES Entreprise fictive', 'story' => 'Mode histoire', 'simple_quiz' => 'Test simple questions / réponses' );
		$info  = array_filter(
			array(
				array( 'Évaluation', (string) $evaluation['title'] ),
				self::evaluation_course_name( $evaluation ) ? array( 'Cours Sensei', self::evaluation_course_name( $evaluation ) ) : null,
				array( 'Classe(s)', self::evaluation_group_names( $evaluation ) ),
				array( 'Type', $types[ (string) ( $evaluation['evaluation_type'] ?? '' ) ] ?? (string) ( $evaluation['evaluation_type'] ?? '' ) ),
				array( 'Durée', (int) $evaluation['duration_minutes'] . ' min' ),
				array( 'Questions', (string) count( $questions ) ),
				array( 'Total des points', self::format_points( $total ) . ' pts' ),
				array( 'Ouverture', self::pdf_datetime( $evaluation['opens_at'] ?? '' ) ),
				array( 'Fermeture', self::pdf_datetime( $evaluation['closes_at'] ?? '' ) ),
				array( 'Mode sécurisé', ! empty( $evaluation['secure_mode'] ) ? 'Oui' : 'Non' ),
			)
		);
		$intro = array_filter(
			array(
				'Description'          => self::pdf_plain_text( (string) ( $evaluation['description'] ?? '' ) ),
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
			$type     = (string) $q['type'];
			$options  = array();
			$expected = '';
			$order    = array();
			if ( in_array( $type, array( 'qcm', 'multiple_choice' ), true ) ) {
				$right = self::expected_answer_values( $q['expected_answer'] ?? '' );
				foreach ( (array) json_decode( (string) $q['options'], true ) as $opt ) {
					if ( is_scalar( $opt ) && '' !== (string) $opt ) {
						$options[] = ( in_array( (string) $opt, $right, true ) ? '[x] ' : '[ ] ' ) . $opt . ( in_array( (string) $opt, $right, true ) ? '  (bonne réponse)' : '' );
					}
				}
			} elseif ( LogiEval_Secure_Question_Types::ORDERING === $type ) {
				foreach ( LogiEval_Secure_Question_Types::order_items( $q['options'] ?? '' ) as $i => $item ) {
					$order[] = array( 'pos' => $i + 1, 'label' => LogiEval_Secure_Question_Types::item_label( $item, $i + 1 ), 'text' => (string) $item['text'], 'media_id' => (int) $item['media_id'] );
				}
				$set      = LogiEval_Secure_Question_Types::order_settings( $q['expected_answer'] ?? '' );
				$expected = 'Bon ordre : ' . implode( ' · ', array_map( static fn( $o ) => $o['pos'] . '. ' . $o['label'], $order ) ) . ( 'letters' === $set['mode'] ? ' (mot : ' . implode( '', wp_list_pluck( $order, 'text' ) ) . ')' : '' ) . ' — ' . ( 'all' === $set['scoring'] ? 'tout juste ou rien' : 'points au prorata des éléments bien placés' );
			}
			if ( '' === $expected ) {
				$expected = self::readable_answer( (string) ( $q['expected_answer'] ?? '' ) );
				$expected = __( 'Non répondu', 'logieval-secure' ) === $expected ? '' : $expected;
			}
			$precision = 'calculation' === $type ? LogiEval_Secure_Question_Types::precision_label( LogiEval_Secure_Question_Types::calculation_precision( $q['expected_answer'] ?? '' ) ) : '';
			$items[]   = array(
				'context'   => self::pdf_plain_text( (string) ( $q['context'] ?? '' ) ),
				'prompt'    => self::pdf_plain_text( (string) $q['prompt'] ),
				'type'      => self::question_type_label( $type ),
				'points'    => (float) ( $q['evaluation_points'] ?: $q['points'] ),
				'precision' => $precision,
				'options'   => $options,
				'expected'  => $expected,
				'criteria'  => self::decode_criteria( $q['grading_criteria'] ?? '' ),
				'media_id'  => absint( $q['media_id'] ?? 0 ),
				'order'     => $order,
			);
		}
		return array( 'info' => array_values( $info ), 'intro' => $intro, 'total' => $total, 'scale' => self::grade_scale( $total ), 'questions' => $items );
	}

	public static function handle_export_evaluation(): void {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ) );
		}
		$evaluation = self::require_managed_evaluation( absint( $_REQUEST['evaluation_id'] ?? 0 ) );
		$format     = 'docx' === sanitize_key( wp_unslash( $_REQUEST['format'] ?? 'pdf' ) ) ? 'docx' : 'pdf';
		$paper      = 'eleve' === sanitize_key( wp_unslash( $_REQUEST['version'] ?? '' ) );
		if ( $paper ) {
			$data = self::evaluation_paper_data( $evaluation );
			$body = 'docx' === $format ? self::build_evaluation_paper_docx( $evaluation, $data ) : self::build_evaluation_paper_pdf( $evaluation, $data );
		} else {
			$data = self::evaluation_export_data( $evaluation );
			$body = 'docx' === $format ? self::build_evaluation_docx( $evaluation, $data ) : self::build_evaluation_pdf( $evaluation, $data );
		}
		$filename   = sanitize_file_name( 'logiexam-evaluation-' . sanitize_title( (string) $evaluation['title'] ) . ( $paper ? '-version-eleve.' : '-corrige.' ) . $format );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . ( 'docx' === $format ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** Boutons « Exporter l'évaluation avec le corrigé ». */
	public static function render_evaluation_export_buttons( array $evaluation, string $only = '' ): void {
		?>
		<?php
		$versions = array(
			'eleve'   => array( __( 'Version élève à imprimer', 'logieval-secure' ), __( 'Sans aucune réponse, avec la place pour écrire : en cas de panne, vos élèves font l’évaluation sur papier.', 'logieval-secure' ) ),
			'corrige' => array( __( 'Évaluation avec le corrigé', 'logieval-secure' ), __( 'Pour vous : bonnes réponses, réponses attendues, critères et barème.', 'logieval-secure' ) ),
		);
		if ( '' !== $only ) {
			$versions = array_intersect_key( $versions, array( $only => true ) );
		}
		foreach ( $versions as $version => $texts ) :
			?>
			<div class="les-export-evaluation" data-les-export-version="<?php echo esc_attr( $version ); ?>">
				<span class="les-export-label"><strong><?php echo esc_html( $texts[0] ); ?></strong><small><?php echo esc_html( $texts[1] ); ?></small></span>
				<?php foreach ( array( 'pdf' => 'PDF', 'docx' => 'Word (.docx)' ) as $format => $label ) : ?>
					<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<input type="hidden" name="action" value="logieval_secure_export_evaluation">
						<input type="hidden" name="version" value="<?php echo esc_attr( $version ); ?>">
						<input type="hidden" name="format" value="<?php echo esc_attr( $format ); ?>">
						<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( (int) $evaluation['id'] ); ?>">
						<button type="submit" class="button les-button-secondary"><?php echo esc_html( $label ); ?></button>
					</form>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * PDF
	 * ------------------------------------------------------------------- */

	private static function build_evaluation_pdf( array $evaluation, array $data ): string {
		self::$pdf_images = array();
		$doc = array( 'pages' => array(), 'content' => '', 'y' => 0, 'header' => 'Évaluation et corrigé' );
		self::pdf_report_new_page( $doc );
		self::pdf_report_section_title( $doc, 'Informations de l’évaluation' );
		self::pdf_report_info_grid( $doc, $data['info'] );
		foreach ( $data['intro'] as $label => $text ) {
			self::pdf_report_note_box( $doc, $label, $text );
		}
		if ( $data['scale'] ) {
			self::pdf_report_section_title( $doc, 'Barème (notes à la demi)' );
			$rows   = array_chunk( $data['scale'], 4 );
			$height = 16 + count( $rows ) * 30;
			self::pdf_report_ensure_space( $doc, $height + 10 );
			$top = $doc['y'];
			$doc['content'] .= self::pdf_fill_rect( 42, $top - $height, 511, $height, 238, 240, 235 );
			$doc['content'] .= self::pdf_rect( 42, $top - $height, 511, $height );
			foreach ( $rows as $r => $chunk ) {
				foreach ( $chunk as $c => $row ) {
					$x = 58 + $c * 124;
					$y = $top - 22 - $r * 30;
					$doc['content'] .= self::pdf_text_styled( $x, $y, 13, self::grade_label( $row['grade'] ), 'F2', array( 49, 58, 69 ) );
					$doc['content'] .= self::pdf_text_styled( $x + 30, $y, 9, self::format_points( $row['min'] ) . ' – ' . self::format_points( $row['max'] ) . ' pts', 'F1', array( 71, 85, 105 ) );
				}
			}
			$doc['y'] -= $height + 20;
		}

		self::pdf_report_new_page( $doc );
		self::pdf_report_section_title( $doc, 'Questions et corrigé' );
		$current_context = null;
		foreach ( $data['questions'] as $i => $q ) {
			$card = self::pdf_key_card_blocks( $i + 1, $q );
			$h    = $card['height'];
			if ( '' !== $q['context'] && $q['context'] !== $current_context ) {
				$ch = max( 48, 34 + ( count( self::pdf_wrap_lines( $q['context'], 92 ) ) * 14 ) + 8 ) + 28;
				self::pdf_report_ensure_space( $doc, $ch + $h + 8 );
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

	/** Prépare une carte « question + corrigé » (lignes, images, hauteur). */
	private static function pdf_key_card_blocks( int $number, array $q ): array {
		$blocks   = array();
		$blocks[] = array( 'lines', self::pdf_wrap_lines( $q['prompt'], 64 ), 11, 'F2', array( 17, 24, 39 ), 14 );
		$meta     = $q['type'] . ' · ' . self::format_points( $q['points'] ) . ' pts' . ( '' !== $q['precision'] ? ' · ' . $q['precision'] : '' );
		$blocks[] = array( 'lines', self::pdf_wrap_lines( $meta, 88 ), 9, 'F2', array( 6, 112, 48 ), 12 );
		if ( $q['media_id'] ) {
			$img = self::pdf_register_image( $q['media_id'] );
			if ( '' !== $img ) {
				$blocks[] = array( 'image', $img, 220, 120 );
			}
		}
		foreach ( $q['options'] as $opt ) {
			$blocks[] = array( 'lines', self::pdf_wrap_lines( $opt, 86 ), 10, 'F1', array( 49, 58, 69 ), 13 );
		}
		$with_images = array_filter( wp_list_pluck( $q['order'], 'media_id' ) );
		if ( $with_images ) {
			$cells = array();
			foreach ( $q['order'] as $o ) {
				$cells[] = array( 'img' => $o['media_id'] ? self::pdf_register_image( $o['media_id'] ) : '', 'caption' => self::pdf_wrap_lines( $o['pos'] . '. ' . $o['label'], 30 ) );
			}
			$blocks[] = array( 'grid', $cells );
		}
		if ( '' !== trim( $q['expected'] ) ) {
			$blocks[] = array( 'lines', self::pdf_wrap_lines( 'Corrigé : ' . $q['expected'], 86 ), 10, 'F1', array( 30, 64, 175 ), 13 );
		}
		foreach ( $q['criteria'] as $c ) {
			$blocks[] = array( 'lines', self::pdf_wrap_lines( '- ' . $c['label'] . ' (' . self::format_points( $c['points'] ) . ' pt)', 86 ), 9, 'F1', array( 71, 85, 105 ), 12 );
		}
		return array( 'number' => $number, 'points' => $q['points'], 'blocks' => $blocks, 'height' => self::pdf_card_height( $blocks ) );
	}

	private static function pdf_draw_key_card( array &$doc, array $card ): void {
		self::pdf_report_ensure_space( $doc, $card['height'] + 8 );
		$top = $doc['y'];
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $card['height'], 511, $card['height'], 255, 255, 255 );
		$doc['content'] .= self::pdf_rect( 42, $top - $card['height'], 511, $card['height'] );
		$doc['content'] .= self::pdf_fill_rect( 42, $top - $card['height'], 4, $card['height'], 245, 196, 0 );
		$doc['content'] .= self::pdf_text_styled( 58, $top - 20, 12, $card['number'] . '.', 'F2', array( 17, 24, 39 ) );
		if ( ! empty( $card['right'] ) ) { // 1.49.3 : points à droite, sur la ligne de la question
			$doc['content'] .= self::pdf_text_styled( 539 - strlen( $card['right'] ) * 10 * 0.56, $top - 20, 10, $card['right'], 'F2', array( 6, 112, 48 ) );
		}
		$y = $top - 20;
		foreach ( $card['blocks'] as $b ) {
			if ( 'lines' === $b[0] ) {
				foreach ( $b[1] as $line ) {
					$doc['content'] .= self::pdf_text_styled( 82, $y, $b[2], $line, $b[3], $b[4] );
					$y -= $b[5];
				}
				$y -= 4;
			} elseif ( 'image' === $b[0] ) {
				$doc['content'] .= self::pdf_draw_image( $b[1], 82, $y + 6, $b[2], $b[3] );
				$y -= $b[3] + 8;
			} elseif ( in_array( $b[0], array( 'write', 'check', 'checkrow', 'slots' ), true ) ) {
				$y = self::pdf_draw_answer_block( $doc, $b, $y );
			} else {
				foreach ( array_chunk( $b[1], 3 ) as $row ) {
					foreach ( $row as $c => $cell ) {
						$x = 82 + $c * 158;
						$doc['content'] .= self::pdf_fill_rect( $x, $y - 92, 150, 92, 248, 250, 252 );
						if ( '' !== $cell['img'] ) {
							$doc['content'] .= self::pdf_draw_image( $cell['img'], $x + 3, $y - 3, 144, 86 );
						}
						$doc['content'] .= self::pdf_rect( $x, $y - 92, 150, 92 );
						$ty = $y - 104;
						foreach ( $cell['caption'] as $line ) {
							$doc['content'] .= self::pdf_text_styled( $x, $ty, 9, $line, 'F1', array( 15, 23, 42 ) );
							$ty -= 11;
						}
					}
					$y -= 92 + 8 + max( array_map( static fn( $c ) => count( $c['caption'] ), $row ) ) * 11 + 10;
				}
			}
		}
		$doc['y'] -= $card['height'] + 12;
	}

	/* ---------------------------------------------------------------------
	 * Word (.docx)
	 * ------------------------------------------------------------------- */

	private static function docx_esc( string $text ): string {
		return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private static function docx_p( string $text, array $o = array() ): string {
		$rpr = '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>' . ( ! empty( $o['bold'] ) ? '<w:b/>' : '' ) . ( ! empty( $o['italic'] ) ? '<w:i/>' : '' ) . '<w:color w:val="' . ( $o['color'] ?? '313A45' ) . '"/><w:sz w:val="' . (int) ( ( $o['size'] ?? 11 ) * 2 ) . '"/>';
		$ppr = '<w:spacing w:before="' . (int) ( $o['before'] ?? 0 ) . '" w:after="' . (int) ( $o['after'] ?? 80 ) . '"/>' . ( ! empty( $o['indent'] ) ? '<w:ind w:left="' . (int) $o['indent'] . '"/>' : '' ) . ( ! empty( $o['keep'] ) ? '<w:keepNext/>' : '' ) . ( ! empty( $o['shade'] ) ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $o['shade'] . '"/>' : '' ) . ( ! empty( $o['border'] ) ? '<w:pBdr><w:bottom w:val="single" w:sz="12" w:space="2" w:color="F5C400"/></w:pBdr>' : '' ) . ( ! empty( $o['page_break'] ) ? '<w:pageBreakBefore/>' : '' );
		$runs = '';
		foreach ( explode( "\n", $text ) as $i => $line ) {
			$runs .= ( $i ? '<w:r><w:br/></w:r>' : '' ) . '<w:r><w:rPr>' . $rpr . '</w:rPr><w:t xml:space="preserve">' . self::docx_esc( $line ) . '</w:t></w:r>';
		}
		return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . $runs . '</w:p>';
	}

	private static function docx_image_run( array &$media, int $media_id, string $path, int $max_w_emu, int $max_h_emu ): string {
		$file = $path ?: ( $media_id ? (string) get_attached_file( $media_id ) : '' );
		if ( ! $file || ! is_readable( $file ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return '';
		}
		$src = @imagecreatefromstring( (string) file_get_contents( $file ) );
		if ( ! $src ) {
			return '';
		}
		$w = imagesx( $src );
		$h = imagesy( $src );
		$scale = min( 1, 900 / max( $w, $h ) );
		$nw = max( 1, (int) round( $w * $scale ) );
		$nh = max( 1, (int) round( $h * $scale ) );
		$dst = imagecreatetruecolor( $nw, $nh );
		imagefill( $dst, 0, 0, imagecolorallocate( $dst, $path ? 49 : 255, $path ? 58 : 255, $path ? 69 : 255 ) );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
		ob_start();
		imagejpeg( $dst, null, 85 );
		$data = (string) ob_get_clean();
		imagedestroy( $src );
		imagedestroy( $dst );
		$n       = count( $media ) + 1;
		$media[] = array( 'name' => 'image' . $n . '.jpeg', 'data' => $data, 'rid' => 'rIdImg' . $n );
		$ratio   = min( $max_w_emu / $nw, $max_h_emu / $nh );
		$cx      = (int) ( $nw * $ratio );
		$cy      = (int) ( $nh * $ratio );
		return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="' . $n . '" name="Image ' . $n . '"/>'
			. '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
			. '<pic:nvPicPr><pic:cNvPr id="' . $n . '" name="image' . $n . '.jpeg"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rIdImg' . $n . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
			. '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
	}

	private static function docx_table( array $rows, array $widths, array $o = array() ): string {
		$grid = '';
		foreach ( $widths as $w ) {
			$grid .= '<w:gridCol w:w="' . (int) $w . '"/>';
		}
		$xml = '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum( $widths ) . '" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="D2D6CD"/><w:left w:val="single" w:sz="4" w:color="D2D6CD"/><w:bottom w:val="single" w:sz="4" w:color="D2D6CD"/><w:right w:val="single" w:sz="4" w:color="D2D6CD"/><w:insideH w:val="single" w:sz="4" w:color="D2D6CD"/><w:insideV w:val="single" w:sz="4" w:color="D2D6CD"/></w:tblBorders><w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>';
		foreach ( $rows as $cells ) {
			$xml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
			foreach ( $cells as $c => $cell ) {
				$fill = $cell['fill'] ?? ( $o['fill'] ?? '' );
				$xml .= '<w:tc><w:tcPr><w:tcW w:w="' . (int) $widths[ $c ] . '" w:type="dxa"/>' . ( $fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '' ) . '</w:tcPr>' . $cell['xml'] . '</w:tc>';
			}
			$xml .= '</w:tr>';
		}
		return $xml . '</w:tbl>' . self::docx_p( '', array( 'after' => 60 ) );
	}

	private static function build_evaluation_docx( array $evaluation, array $data ): string {
		$media = array();
		$body  = '';
		// En-tête LogiExam : logo sur bandeau anthracite, filet jaune.
		$logo  = self::docx_image_run( $media, 0, LOGIEVAL_SECURE_PATH . 'assets/pdf-logo.png', 1900000, 500000 );
		$body .= '<w:p><w:pPr><w:shd w:val="clear" w:color="auto" w:fill="313A45"/><w:spacing w:after="0"/></w:pPr>' . $logo . '</w:p>';
		$body .= self::docx_p( 'Évaluation et corrigé · ' . wp_date( 'd.m.Y H:i' ), array( 'bold' => true, 'color' => 'FFFFFF', 'size' => 12, 'shade' => '313A45', 'border' => true, 'after' => 240 ) );
		$body .= self::docx_p( (string) $evaluation['title'], array( 'bold' => true, 'size' => 18, 'after' => 160 ) );
		$body .= self::docx_p( 'Informations de l’évaluation', array( 'bold' => true, 'size' => 13, 'border' => true, 'before' => 120, 'after' => 120, 'keep' => true ) );
		$rows = array();
		foreach ( array_chunk( $data['info'], 2 ) as $pair ) {
			$row = array();
			foreach ( $pair as $item ) {
				$row[] = array( 'xml' => self::docx_p( mb_strtoupper( (string) $item[0] ), array( 'bold' => true, 'size' => 8, 'color' => '5B6672', 'after' => 20 ) ) . self::docx_p( (string) $item[1], array( 'size' => 10, 'after' => 20 ) ), 'fill' => 'F6F7F3' );
			}
			while ( count( $row ) < 2 ) {
				$row[] = array( 'xml' => self::docx_p( '' ), 'fill' => 'F6F7F3' );
			}
			$rows[] = $row;
		}
		$body .= self::docx_table( $rows, array( 4800, 4800 ) );
		foreach ( $data['intro'] as $label => $text ) {
			$body .= self::docx_p( $label, array( 'bold' => true, 'size' => 10, 'color' => '067030', 'after' => 20, 'keep' => true ) ) . self::docx_p( $text, array( 'size' => 10 ) );
		}
		if ( $data['scale'] ) {
			$body .= self::docx_p( 'Barème (notes à la demi)', array( 'bold' => true, 'size' => 13, 'border' => true, 'before' => 160, 'after' => 120, 'keep' => true ) );
			$rows = array( array( array( 'xml' => self::docx_p( 'Note', array( 'bold' => true, 'color' => 'FFFFFF' ) ), 'fill' => '313A45' ), array( 'xml' => self::docx_p( 'Points', array( 'bold' => true, 'color' => 'FFFFFF' ) ), 'fill' => '313A45' ) ) );
			foreach ( $data['scale'] as $row ) {
				$rows[] = array( array( 'xml' => self::docx_p( self::grade_label( $row['grade'] ), array( 'bold' => true ) ) ), array( 'xml' => self::docx_p( self::format_points( $row['min'] ) . ' – ' . self::format_points( $row['max'] ) . ' pts' ) ) );
			}
			$body .= self::docx_table( $rows, array( 1600, 3200 ) );
		}
		$body .= self::docx_p( 'Questions et corrigé', array( 'bold' => true, 'size' => 13, 'border' => true, 'after' => 160, 'page_break' => true ) );
		$current_context = null;
		foreach ( $data['questions'] as $i => $q ) {
			if ( '' !== $q['context'] && $q['context'] !== $current_context ) {
				$body .= self::docx_p( 'CONTEXTE / SECTION', array( 'bold' => true, 'size' => 8, 'color' => '3B8235', 'shade' => 'DCF7D2', 'after' => 0, 'keep' => true ) );
				$body .= self::docx_p( $q['context'], array( 'size' => 10, 'shade' => 'DCF7D2', 'after' => 160, 'keep' => true ) );
				$current_context = $q['context'];
			}
			$body .= self::docx_p( ( $i + 1 ) . '. ' . $q['prompt'], array( 'bold' => true, 'size' => 11, 'after' => 40, 'keep' => true, 'before' => 120 ) );
			$body .= self::docx_p( $q['type'] . ' · ' . self::format_points( $q['points'] ) . ' pts' . ( '' !== $q['precision'] ? ' · ' . $q['precision'] : '' ), array( 'bold' => true, 'size' => 9, 'color' => '067030', 'after' => 60, 'keep' => true, 'indent' => 280 ) );
			if ( $q['media_id'] ) {
				$run = self::docx_image_run( $media, $q['media_id'], '', 3200000, 1900000 );
				if ( '' !== $run ) {
					$body .= '<w:p><w:pPr><w:ind w:left="280"/><w:keepNext/></w:pPr>' . $run . '</w:p>';
				}
			}
			foreach ( $q['options'] as $opt ) {
				$body .= self::docx_p( $opt, array( 'size' => 10, 'indent' => 280, 'after' => 20, 'bold' => false !== strpos( $opt, 'bonne réponse' ) ) );
			}
			if ( array_filter( wp_list_pluck( $q['order'], 'media_id' ) ) ) {
				$rows = array();
				foreach ( array_chunk( $q['order'], 3 ) as $chunk ) {
					$row = array();
					foreach ( $chunk as $o ) {
						$img   = $o['media_id'] ? self::docx_image_run( $media, $o['media_id'], '', 1700000, 1050000 ) : '';
						$row[] = array( 'xml' => ( $img ? '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>' . $img . '</w:p>' : '' ) . self::docx_p( $o['pos'] . '. ' . $o['label'], array( 'size' => 9 ) ) );
					}
					while ( count( $row ) < 3 ) {
						$row[] = array( 'xml' => self::docx_p( '' ) );
					}
					$rows[] = $row;
				}
				$body .= self::docx_table( $rows, array( 3150, 3150, 3150 ) );
			}
			if ( '' !== trim( $q['expected'] ) ) {
				$body .= self::docx_p( 'Corrigé : ' . $q['expected'], array( 'size' => 10, 'color' => '1E40AF', 'indent' => 280, 'shade' => 'EEF0EB', 'after' => 60 ) );
			}
			foreach ( $q['criteria'] as $c ) {
				$body .= self::docx_p( '• ' . $c['label'] . ' (' . self::format_points( $c['points'] ) . ' pt)', array( 'size' => 9, 'color' => '475569', 'indent' => 280, 'after' => 20 ) );
			}
		}

		return self::docx_package( $body, $media );
	}

	/** Assemble le fichier .docx (A4, numéros de page en pied de page). */
	private static function docx_package( string $body, array $media ): string {
		$footer   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:color w:val="7A848E"/><w:sz w:val="16"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:rPr><w:color w:val="7A848E"/><w:sz w:val="16"/></w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r><w:r><w:rPr><w:color w:val="7A848E"/><w:sz w:val="16"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:rPr><w:color w:val="7A848E"/><w:sz w:val="16"/></w:rPr><w:t>1</w:t></w:r><w:r><w:rPr><w:color w:val="7A848E"/><w:sz w:val="16"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r></w:p></w:ftr>';
		$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
			. '<w:body>' . $body . '<w:sectPr><w:footerReference w:type="default" r:id="rIdFtr"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1000" w:right="1000" w:bottom="1000" w:left="1000" w:header="500" w:footer="500" w:gutter="0"/></w:sectPr></w:body></w:document>';
		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		foreach ( $media as $m ) {
			$rels .= '<Relationship Id="' . $m['rid'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $m['name'] . '"/>';
		}
		$rels .= '<Relationship Id="rIdFtr" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>';

		$tmp = (string) tempnam( get_temp_dir(), 'lexdocx' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE );
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="jpeg" ContentType="image/jpeg"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>' );
		$zip->addFromString( 'word/document.xml', $document );
		$zip->addFromString( 'word/_rels/document.xml.rels', $rels );
		$zip->addFromString( 'word/footer1.xml', $footer );
		foreach ( $media as $m ) {
			$zip->addFromString( 'word/media/' . $m['name'], $m['data'] );
		}
		$zip->close();
		$out = (string) file_get_contents( $tmp );
		@unlink( $tmp );
		return $out;
	}
}
