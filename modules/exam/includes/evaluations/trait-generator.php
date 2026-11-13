<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LogiOne : « Créer des questions avec l'IA ».
 * L'enseignant fournit la théorie (texte collé, fichier PDF / Word / texte, leçons Sensei) ; l'IA propose des
 * questions avec réponse attendue, points et critères d'évaluation ; l'enseignant relit, corrige, puis les
 * ajoute à la banque de questions, et peut créer directement une évaluation (brouillon) avec elles.
 * Rien n'est enregistré avant « Ajouter » : les questions passent par le même chemin que l'éditeur.
 */
trait LogiEval_Secure_Evaluations_Generator {

	/** Types proposés au générateur (tous corrigeables facilement). */
	private static function generator_types(): array {
		return array(
			'qcm'                => __( 'QCM (une bonne réponse)', 'logieval-secure' ),
			'true_false'         => __( 'Vrai / Faux', 'logieval-secure' ),
			'true_false_justify' => __( 'Vrai / Faux argumenté', 'logieval-secure' ),
			'short_answer'       => __( 'Réponse courte', 'logieval-secure' ),
			'long_answer'        => __( 'Réponse longue (explication)', 'logieval-secure' ),
			'calculation'        => __( 'Calcul', 'logieval-secure' ),
			'ordering'           => __( 'Remettre dans l’ordre', 'logieval-secure' ),
			'matching'           => __( 'Relier (gauche ↔ droite)', 'logieval-secure' ),
			'fill_blanks'        => __( 'Texte à trous', 'logieval-secure' ),
		);
	}

	private static function generator_levels(): array {
		return array(
			'cfc1' => __( 'CFC logisticien·ne – 1re année', 'logieval-secure' ),
			'cfc2' => __( 'CFC logisticien·ne – 2e année', 'logieval-secure' ),
			'cfc3' => __( 'CFC logisticien·ne – 3e année', 'logieval-secure' ),
			'afp'  => __( 'AFP logisticien·ne', 'logieval-secure' ),
			'adult' => __( 'Formation d’adultes / cariste', 'logieval-secure' ),
		);
	}

	private static function generator_allowed(): bool {
		return LogiEval_Secure_Ultimate_Member::current_user_can_teach() && current_user_can( 'lp_exam_ai' );
	}

	/* ---------------------------------------------------------------------
	 * Écran
	 * ------------------------------------------------------------------- */

	private static function render_generator_workspace(): void {
		if ( ! self::generator_allowed() ) {
			echo '<div class="les-panel"><p>' . esc_html__( 'Vous n’avez pas l’autorisation d’utiliser l’IA dans LogiExam.', 'logieval-secure' ) . '</p></div>';
			return;
		}
		$ready   = LogiEval_Secure_AI::user_is_ready( get_current_user_id() );
		$courses = LogiEval_Secure_Sensei::get_courses();
		?>
		<section class="les-panel les-gen" data-les-gen>
			<div class="les-gen-head">
				<h2><?php esc_html_e( 'Créer des questions avec l’IA', 'logieval-secure' ); ?></h2>
				<p><?php esc_html_e( 'Donnez la théorie : l’IA propose des questions avec la réponse attendue, les points et les critères de correction. Vous relisez, vous corrigez, puis vous les ajoutez à la banque de questions ou vous créez directement une évaluation.', 'logieval-secure' ); ?></p>
			</div>
			<?php if ( ! empty( $_GET['gen_error'] ) ) : ?>
				<div class="les-message is-error"><?php esc_html_e( 'Aucune question n’a pu être enregistrée : vérifiez qu’au moins une question est cochée et complète.', 'logieval-secure' ); ?></div>
			<?php endif; ?>
			<?php if ( ! $ready ) : ?>
				<div class="les-message is-error"><?php echo wp_kses_post( sprintf( __( 'L’IA n’est pas encore configurée pour votre compte. Enregistrez votre clé dans <a href="%s">Réglages → Pré-correction IA</a>.', 'logieval-secure' ), esc_url( self::app_url( 'settings' ) ) ) ); ?></div>
			<?php endif; ?>

			<div class="les-gen-step">
				<h3><span class="les-gen-num">1</span> <?php esc_html_e( 'La théorie', 'logieval-secure' ); ?></h3>
				<div class="les-gen-sources">
					<label class="les-gen-field"><?php esc_html_e( 'Coller la théorie (texte du cours, résumé, fiche…)', 'logieval-secure' ); ?>
						<textarea rows="8" data-les-gen-text placeholder="<?php esc_attr_e( 'Collez ici le texte de la théorie…', 'logieval-secure' ); ?>"></textarea>
					</label>
					<div class="les-gen-row">
						<label class="les-gen-field"><?php esc_html_e( 'Ajouter un fichier (PDF, Word .docx, texte .txt)', 'logieval-secure' ); ?>
							<input type="file" accept=".pdf,.docx,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain" multiple data-les-gen-file>
						</label>
						<label class="les-gen-field"><?php esc_html_e( 'Ou reprendre des leçons Sensei', 'logieval-secure' ); ?>
							<select data-les-gen-course>
								<option value=""><?php esc_html_e( 'Choisir un cours…', 'logieval-secure' ); ?></option>
								<?php foreach ( $courses as $course ) : ?>
									<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<div class="les-gen-lessons" data-les-gen-lessons hidden></div>
					<ul class="les-gen-sourcelist" data-les-gen-sourcelist></ul>
					<p class="description" data-les-gen-size></p>
				</div>
				<div class="les-gen-objectives" data-les-gen-objectives-box>
					<h4>🎯 <?php esc_html_e( 'Les objectifs que les élèves doivent atteindre (facultatif)', 'logieval-secure' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Si vous les donnez, chaque question vérifie l’un de ces objectifs et les questions sont réparties sur tous les objectifs. La théorie sert toujours pour les réponses attendues.', 'logieval-secure' ); ?></p>
					<textarea rows="5" data-les-gen-objectives placeholder="<?php esc_attr_e( "Un objectif par ligne, par exemple :\n1. Je décris les étapes de la mise en service d’une machine CNC.\n2. J’explique le rôle de l’origine pièce et de l’origine machine.", 'logieval-secure' ); ?>"></textarea>
					<div class="les-gen-row">
						<label class="les-gen-field"><?php esc_html_e( 'Déposer un fichier d’objectifs (PDF, Word .docx, texte)', 'logieval-secure' ); ?>
							<input type="file" accept=".pdf,.docx,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain" data-les-gen-obj-file>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Les objectifs sont dans une leçon Sensei ? Choisissez le cours ci-dessus et cliquez sur « 🎯 Objectifs » à côté de la leçon.', 'logieval-secure' ); ?></p>
					<p class="description" data-les-gen-obj-info></p>
				</div>
			</div>

			<div class="les-gen-step">
				<h3><span class="les-gen-num">2</span> <?php esc_html_e( 'Les questions voulues', 'logieval-secure' ); ?></h3>
				<div class="les-gen-row">
					<label class="les-gen-field is-small"><?php esc_html_e( 'Nombre de questions', 'logieval-secure' ); ?>
						<input type="number" min="1" max="40" value="10" data-les-gen-count>
					</label>
					<label class="les-gen-field"><?php esc_html_e( 'Niveau', 'logieval-secure' ); ?>
						<select data-les-gen-level>
							<?php foreach ( self::generator_levels() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="les-gen-field"><?php esc_html_e( 'Adresse aux élèves', 'logieval-secure' ); ?>
						<select data-les-gen-address>
							<option value="tu"><?php esc_html_e( 'Tutoiement (« Explique… »)', 'logieval-secure' ); ?></option>
							<option value="vous"><?php esc_html_e( 'Vouvoiement (« Expliquez… »)', 'logieval-secure' ); ?></option>
						</select>
					</label>
					<label class="les-gen-field"><?php esc_html_e( 'Cours Sensei des questions (classement)', 'logieval-secure' ); ?>
						<select data-les-gen-target-course>
							<option value="0"><?php esc_html_e( 'Aucun', 'logieval-secure' ); ?></option>
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<?php self::render_ai_provider_choice( 'generation' ); ?>
				<fieldset class="les-gen-types">
					<legend><?php esc_html_e( 'Types de questions (mélangés)', 'logieval-secure' ); ?></legend>
					<?php foreach ( self::generator_types() as $key => $label ) : ?>
						<label class="les-check"><input type="checkbox" value="<?php echo esc_attr( $key ); ?>" data-les-gen-type <?php checked( in_array( $key, array( 'qcm', 'true_false_justify', 'short_answer', 'long_answer' ), true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<label class="les-gen-field"><?php esc_html_e( 'Consignes pour l’IA (facultatif)', 'logieval-secure' ); ?>
					<textarea rows="2" data-les-gen-notes placeholder="<?php esc_attr_e( 'Ex. : insister sur la sécurité au quai ; pas de question sur les dates ; questions sur le film « Cargos » seulement.', 'logieval-secure' ); ?>"></textarea>
				</label>
				<div class="les-actions">
					<button type="button" class="button button-primary" data-les-gen-run <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Générer les questions', 'logieval-secure' ); ?></button>
					<?php if ( $ready && count( LogiEval_Secure_AI::configured_providers( get_current_user_id() ) ) >= 2 ) : ?>
						<button type="button" class="button les-button-secondary" data-les-gen-compare><?php esc_html_e( '⚖️ Comparer deux IA', 'logieval-secure' ); ?></button>
					<?php endif; ?>
					<span class="les-gen-status" data-les-gen-status aria-live="polite"></span>
				</div>
				<div class="les-ai-compare les-gen-compare" data-les-gen-compare-panel hidden></div>
			</div>

			<div class="les-gen-step" data-les-gen-review hidden>
				<h3><span class="les-gen-num">3</span> <?php esc_html_e( 'Relire et corriger', 'logieval-secure' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Tout est modifiable. Décochez une question pour ne pas la garder. Les critères servent à la correction et à la pré-correction IA ; leur total donne les points de la question.', 'logieval-secure' ); ?></p>
				<div class="les-gen-list" data-les-gen-list></div>
				<form class="les-gen-save" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-les-gen-form>
					<input type="hidden" name="action" value="logieval_secure_gen_save">
					<input type="hidden" name="questions" value="" data-les-gen-payload>
					<input type="hidden" name="sensei_course_id" value="0" data-les-gen-payload-course>
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<?php self::frontend_redirect_field( array( 'logiexam_view' => 'questions' ) ); ?>
					<div class="les-gen-total" data-les-gen-total></div>
					<div class="les-gen-row">
						<label class="les-gen-field"><?php esc_html_e( 'Titre de l’évaluation (si vous la créez)', 'logieval-secure' ); ?>
							<input type="text" name="evaluation_title" data-les-gen-title placeholder="<?php esc_attr_e( 'Ex. : M103 – Tâches et objectifs de la logistique', 'logieval-secure' ); ?>">
						</label>
						<label class="les-gen-field is-small"><?php esc_html_e( 'Durée (minutes)', 'logieval-secure' ); ?>
							<input type="number" min="5" max="300" name="duration_minutes" value="45">
						</label>
					</div>
					<div class="les-actions">
						<button type="submit" name="mode" value="bank" class="button"><?php esc_html_e( 'Ajouter à la banque de questions', 'logieval-secure' ); ?></button>
						<button type="submit" name="mode" value="evaluation" class="button button-primary"><?php esc_html_e( 'Ajouter et créer une évaluation (brouillon)', 'logieval-secure' ); ?></button>
					</div>
				</form>
			</div>
		</section>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Sources de théorie
	 * ------------------------------------------------------------------- */

	/** Leçons d'un cours Sensei (pour cocher celles à reprendre). */
	public static function ajax_gen_lessons(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! self::generator_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
		}
		$course_id = absint( $_POST['course_id'] ?? 0 );
		$lessons   = array();
		foreach ( self::generator_course_lessons( $course_id ) as $lesson ) {
			$text      = self::generator_clean_text( (string) $lesson->post_content );
			$lessons[] = array( 'id' => (int) $lesson->ID, 'title' => get_the_title( $lesson ), 'chars' => function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) );
		}
		wp_send_json_success( array( 'lessons' => $lessons ) );
	}

	/** Texte des leçons cochées, ou d'un fichier Word / texte envoyé. */
	public static function ajax_gen_source(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! self::generator_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
		}
		if ( ! empty( $_FILES['file']['tmp_name'] ) && is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
			$name = sanitize_file_name( (string) ( $_FILES['file']['name'] ?? '' ) );
			$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( (int) ( $_FILES['file']['size'] ?? 0 ) > 15 * MB_IN_BYTES ) {
				wp_send_json_error( array( 'message' => __( 'Fichier trop lourd (15 Mo au maximum).', 'logieval-secure' ) ), 400 );
			}
			if ( 'docx' === $ext ) {
				$text = self::generator_docx_text( (string) $_FILES['file']['tmp_name'] );
			} elseif ( in_array( $ext, array( 'txt', 'md' ), true ) ) {
				$text = (string) file_get_contents( (string) $_FILES['file']['tmp_name'] );
			} else {
				wp_send_json_error( array( 'message' => __( 'Format non pris en charge ici (PDF : lu directement dans le navigateur).', 'logieval-secure' ) ), 400 );
			}
			$text = trim( preg_replace( "/[ \t]+/u", ' ', preg_replace( "/\n{3,}/", "\n\n", str_replace( "\r", '', (string) $text ) ) ) );
			if ( '' === $text ) {
				wp_send_json_error( array( 'message' => __( 'Aucun texte trouvé dans ce fichier.', 'logieval-secure' ) ), 400 );
			}
			wp_send_json_success( array( 'title' => $name, 'text' => $text ) );
		}
		$ids   = array_filter( array_map( 'absint', (array) ( $_POST['lesson_ids'] ?? array() ) ) );
		$parts = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || ! in_array( $post->post_type, array( 'lesson', 'sensei_lesson' ), true ) ) {
				continue;
			}
			$text = self::generator_clean_text( (string) $post->post_content );
			if ( '' !== $text ) {
				$parts[] = get_the_title( $post ) . "\n" . $text;
			}
		}
		if ( ! $parts ) {
			wp_send_json_error( array( 'message' => __( 'Ces leçons ne contiennent pas de texte exploitable (vidéo, PDF ou contenu H5P seulement ?). Ajoutez la théorie en texte ou en fichier.', 'logieval-secure' ) ), 400 );
		}
		wp_send_json_success( array( 'title' => sprintf( _n( '%d leçon Sensei', '%d leçons Sensei', count( $parts ), 'logieval-secure' ), count( $parts ) ), 'text' => implode( "\n\n", $parts ) ) );
	}

	private static function generator_course_lessons( int $course_id ): array {
		if ( ! $course_id ) {
			return array();
		}
		$types = array_values( array_filter( array( 'lesson', 'sensei_lesson' ), 'post_type_exists' ) );
		if ( ! $types ) {
			return array();
		}
		// Ordre du cours d'abord, puis les leçons du cours qui n'y figurent pas encore.
		$order = array_values( array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $course_id, '_lesson_order', true ) ) ) ) );
		$base  = array( 'post_type' => $types, 'post_status' => array( 'publish', 'private' ), 'posts_per_page' => 200 );
		$found = $order ? get_posts( array_merge( $base, array( 'post__in' => $order, 'orderby' => 'post__in' ) ) ) : array();
		$more  = get_posts( array_merge( $base, array( 'meta_key' => '_lesson_course', 'meta_value' => $course_id, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) );
		$out   = array();
		foreach ( array_merge( $found, $more ) as $post ) {
			$out[ (int) $post->ID ] = $post;
		}
		return array_values( $out );
	}

	/** Texte lisible d'un contenu WordPress (garde le texte des modules Divi, enlève balises et codes courts). */
	private static function generator_clean_text( string $html ): string {
		$html = preg_replace( '/\[\/?[a-zA-Z0-9_\-]+(?:\s[^\]]*)?\]/', ' ', $html );
		$html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', (string) $html );
		$html = preg_replace( '#</(p|div|li|h[1-6]|tr|br)\s*>|<br\s*/?>#i', "\n", (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( "/[ \t\x{00A0}]+/u", ' ', $text );
		$text = preg_replace( "/\s*\n\s*/u", "\n", (string) $text );
		return trim( (string) $text );
	}

	private static function generator_docx_text( string $path ): string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return '';
		}
		$xml = (string) $zip->getFromName( 'word/document.xml' );
		$zip->close();
		$xml = preg_replace( '#</w:p>#', "\n", $xml );
		$xml = preg_replace( '#<w:tab/>#', ' ', (string) $xml );
		$xml = preg_replace( '#<w:br[^>]*/>#', "\n", (string) $xml );
		return html_entity_decode( wp_strip_all_tags( (string) $xml ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/* ---------------------------------------------------------------------
	 * Génération (par petits paquets, demandés un à un par le navigateur)
	 * ------------------------------------------------------------------- */

	public static function ajax_gen_questions(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! self::generator_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Accès non autorisé.', 'logieval-secure' ) ), 403 );
		}
		if ( ! LogiEval_Secure_AI::user_is_ready( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'L’IA n’est pas configurée pour votre compte (Réglages → Pré-correction IA).', 'logieval-secure' ) ), 400 );
		}
		$theory = trim( (string) wp_unslash( $_POST['theory'] ?? '' ) );
		$theory = wp_check_invalid_utf8( $theory );
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $theory ) : strlen( $theory ) ) < 80 ) {
			wp_send_json_error( array( 'message' => __( 'Ajoutez d’abord la théorie (au moins quelques phrases).', 'logieval-secure' ) ), 400 );
		}
		$max_chars = (int) apply_filters( 'logieval_secure_generator_max_chars', 60000 );
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $theory ) : strlen( $theory ) ) > $max_chars ) {
			$theory = ( function_exists( 'mb_substr' ) ? mb_substr( $theory, 0, $max_chars ) : substr( $theory, 0, $max_chars ) ) . "\n[…]";
		}
		$allowed = array_keys( self::generator_types() );
		$types   = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['types'] ?? array() ) ), $allowed ) );
		if ( ! $types ) {
			wp_send_json_error( array( 'message' => __( 'Choisissez au moins un type de question.', 'logieval-secure' ) ), 400 );
		}
		$types   = array_slice( $types, 0, 6 );
		$level   = sanitize_key( wp_unslash( $_POST['level'] ?? 'cfc1' ) );
		$levels  = self::generator_levels();
		$level   = $levels[ $level ] ?? reset( $levels );
		$address = 'vous' === sanitize_key( wp_unslash( $_POST['address'] ?? 'tu' ) ) ? 'vous' : 'tu';
		$notes   = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		$avoid   = array_slice( array_filter( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['avoid'] ?? array() ) ) ), 0, 60 );
		// 1.33.0 : objectifs à atteindre (collés, fichier ou leçon Sensei) et objectifs visés par ce lot.
		$objectives = trim( sanitize_textarea_field( wp_unslash( $_POST['objectives'] ?? '' ) ) );
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $objectives ) : strlen( $objectives ) ) > 15000 ) {
			$objectives = ( function_exists( 'mb_substr' ) ? mb_substr( $objectives, 0, 15000 ) : substr( $objectives, 0, 15000 ) ) . "\n[…]";
		}
		$focus = array_slice( array_filter( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['focus'] ?? array() ) ) ), 0, 6 );

		$prompt     = self::generator_prompt( $theory, $types, $level, $address, $notes, $avoid, $objectives, $focus );
		$options = array(
			'provider'   => sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ),
			'purpose'    => 'generation',
			'json'       => true,
			'max_tokens' => 1500 + 1200 * count( $types ),
			'timeout'    => (int) apply_filters( 'logieval_secure_generator_timeout', 110 ),
		);
		$questions  = array();
		$completion = null;
		$truncated  = false;
		// Deux essais : si la réponse est coupée ou illisible, on redemande avec deux fois plus de place.
		for ( $try = 0; $try < 2 && ! $questions; $try++ ) {
			if ( $try ) {
				$options['max_tokens'] *= 2;
			}
			$completion = LogiEval_Secure_AI::complete( $prompt, get_current_user_id(), $options );
			if ( is_wp_error( $completion ) ) {
				if ( 'logieval_ai_truncated' === $completion->get_error_code() && ! $try ) {
					$truncated = true;
					continue;
				}
				wp_send_json_error( array( 'message' => $completion->get_error_message() ), 502 );
			}
			$truncated = ! empty( $completion['truncated'] );
			foreach ( self::generator_parse( (string) $completion['text'] ) as $q ) {
				$clean = self::generator_normalize( $q, $types );
				if ( $clean ) {
					$questions[] = $clean;
				}
			}
		}
		if ( ! $questions ) {
			$message = $truncated
				? __( 'La réponse de l’IA était trop longue et a été coupée. Réessayez avec moins de questions à la fois ou une théorie plus courte.', 'logieval-secure' )
				: __( 'La réponse de l’IA n’a pas pu être lue. Réessayez.', 'logieval-secure' );
			wp_send_json_error( array( 'message' => $message ), 502 );
		}
		wp_send_json_success( array( 'questions' => $questions, 'model' => $completion['model'] ?? '' ) );
	}

	/**
	 * Lit le JSON renvoyé par l'IA. Si la réponse est coupée en cours de route, on garde
	 * les questions complètes déjà écrites au lieu de tout perdre.
	 */
	private static function generator_parse( string $text ): array {
		$text = trim( preg_replace( '/^```(?:json)?|```$/m', '', $text ) );
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) && preg_match( '/\{.*\}/s', $text, $m ) ) {
			$data = json_decode( $m[0], true );
		}
		if ( is_array( $data ) ) {
			$raw = $data['questions'] ?? ( isset( $data[0] ) ? $data : ( isset( $data['type'] ) ? array( $data ) : array() ) );
			return array_values( array_filter( (array) $raw, 'is_array' ) );
		}
		// Récupération : objets complets du tableau « questions ».
		$start = strpos( $text, '"questions"' );
		$start = false === $start ? strpos( $text, '[' ) : strpos( $text, '[', $start );
		if ( false === $start ) {
			return array();
		}
		$out   = array();
		$depth = 0;
		$in    = false;
		$esc   = false;
		$from  = -1;
		$len   = strlen( $text );
		for ( $i = $start + 1; $i < $len; $i++ ) {
			$c = $text[ $i ];
			if ( $in ) {
				if ( $esc ) {
					$esc = false;
				} elseif ( '\\' === $c ) {
					$esc = true;
				} elseif ( '"' === $c ) {
					$in = false;
				}
				continue;
			}
			if ( '"' === $c ) {
				$in = true;
			} elseif ( '{' === $c ) {
				if ( 0 === $depth ) {
					$from = $i;
				}
				$depth++;
			} elseif ( '}' === $c && $depth > 0 ) {
				$depth--;
				if ( 0 === $depth && $from >= 0 ) {
					$q = json_decode( substr( $text, $from, $i - $from + 1 ), true );
					if ( is_array( $q ) ) {
						$out[] = $q;
					}
					$from = -1;
				}
			} elseif ( ']' === $c && 0 === $depth ) {
				break;
			}
		}
		return $out;
	}

	private static function generator_prompt( string $theory, array $types, string $level, string $address, string $notes, array $avoid, string $objectives = '', array $focus = array() ): string {
		$descriptions = array(
			'qcm'                => 'qcm : une question et 4 propositions ("options"), une seule juste ("correct_index", à partir de 0). Les fausses propositions sont plausibles, de même longueur et de même style que la bonne. Pas de « toutes les réponses » ni de « aucune ».',
			'true_false'         => 'true_false : une affirmation à juger ("prompt"), réponse "Vrai" ou "Faux" dans "true_false". Évite les affirmations à piège sur un détail.',
			'true_false_justify' => 'true_false_justify : une affirmation, réponse "Vrai" ou "Faux" dans "true_false", justification attendue courte dans "justification", points du choix Vrai/Faux dans "choice_points" (en général 1) ; le reste des points pour la justification.',
			'short_answer'       => 'short_answer : réponse en quelques mots ou une phrase ("expected_answer").',
			'long_answer'        => 'long_answer : explication en 2 à 4 phrases ; "expected_answer" donne les idées attendues (pas une phrase modèle unique).',
			'calculation'        => 'calculation : petit problème chiffré tiré de la théorie ; "calculation" = {"development": étapes du calcul, "result": résultat final avec unité, "precision": "exact", "0", "1" ou "2" décimales}.',
			'ordering'           => 'ordering : remettre dans l’ordre 4 à 6 étapes d’un processus ; "items" = les étapes DANS LE BON ORDRE.',
			'matching'           => 'matching : relier 4 à 6 paires (notion ↔ définition, symbole ↔ signification, document ↔ usage…) ; "pairs" = [{"left": …, "right": …}] avec la BONNE paire sur chaque ligne ; textes courts, droite sans indice qui trahit la paire. "distractors" = 1 ou 2 éléments de droite plausibles qui ne vont avec aucun élément de gauche (intrus).',
			'fill_blanks'        => 'fill_blanks : un court texte de 2 à 4 phrases tiré de la théorie, avec 3 à 6 mots-clés à trouver écrits entre crochets dans "blanks_text" (ex. « Le principe [FIFO] signifie premier entré, premier [sorti|sortie]. ») ; variantes acceptées séparées par |. "bank" = true pour montrer la liste des mots à placer (false seulement si la question doit être plus difficile).',
		);
		$lines = array();
		foreach ( $types as $type ) {
			$lines[] = '- ' . $descriptions[ $type ];
		}
		$schema = '{"questions":[{"type":"…","context":"","prompt":"…","points":2,"expected_answer":"…","calculator":false,"options":[],"correct_index":0,"true_false":"","justification":"","choice_points":1,"calculation":{"development":"","result":"","precision":"exact"},"items":[],"pairs":[{"left":"","right":""}],"distractors":[],"blanks_text":"","bank":true,"criteria":[{"label":"…","points":1}],"source":"…","objective":""}]}';
		$verb   = 'tu' === $address ? 'tutoiement (« Explique… », « Cite… »)' : 'vouvoiement (« Expliquez… », « Citez… »)';
		$prompt = "Tu es enseignant·e de logistique en Suisse romande. Public : {$level}.\n"
			. 'Crée exactement ' . count( $types ) . " questions d'évaluation à partir UNIQUEMENT de la théorie ci-dessous, une question pour chaque type de cette liste, dans cet ordre :\n"
			. implode( "\n", $lines ) . "\n"
			// 1.38.4 : mêmes options que les questions créées à la main.
			. "- \"calculator\" : true si l'élève doit faire un calcul pour répondre (toujours pour calculation ; aussi pour une réponse courte ou un QCM qui demande de calculer), sinon false.\n\n"
			. "Règles :\n"
			// Règles modifiables dans Réglages › Mes consignes pour l'IA.
			. self::ai_prompt( 'generation_rules', array( '{forme}' => $verb ) ) . "\n"
			. ( in_array( 'qcm', $types, true ) ? "\n" . self::ai_prompt( 'generation_qcm' ) . "\n" : '' );
		if ( '' !== $notes ) {
			$prompt .= "- Consignes de l'enseignant : {$notes}\n";
		}
		if ( '' !== $objectives ) {
			$prompt .= "\nOBJECTIFS QUE LES ÉLÈVES DOIVENT ATTEINDRE (prioritaires) :\n\"\"\"\n{$objectives}\n\"\"\"\n"
				. "- Chaque question vérifie directement UN de ces objectifs (savoir ou savoir-faire visé), au niveau d'exigence que l'objectif décrit (citer, décrire, expliquer, calculer, appliquer…).\n"
				. "- Pas de question sur un point de la théorie qui ne sert aucun objectif.\n"
				. "- La réponse attendue et les critères restent tirés de la théorie.\n"
				. "- Remplis \"objective\" avec l'objectif visé (son numéro s'il en a un, puis ses premiers mots).\n";
			if ( $focus ) {
				$prompt .= "- Pour ce lot, vise en priorité ces objectifs (un par question, dans l'ordre) :\n  • " . implode( "\n  • ", $focus ) . "\n";
			}
		}
		if ( $avoid ) {
			$prompt .= "\nQuestions déjà créées (ne pas refaire les mêmes, ni des questions très proches) :\n- " . implode( "\n- ", $avoid ) . "\n";
		}
		$prompt .= "\nRéponds uniquement avec ce JSON, sans texte autour, champs inutiles laissés vides :\n{$schema}\n\nTHÉORIE :\n\"\"\"\n{$theory}\n\"\"\"";
		return $prompt;
	}

	/** 1.38.4 : la question demande-t-elle un calcul ? (si l'IA ne l'a pas dit) */
	private static function generator_needs_calculator( string $text ): bool {
		$t = mb_strtolower( $text );
		if ( ! preg_match( '/\d/', $t ) ) {
			return false;
		}
		return (bool) preg_match( '/\b(calcule[rz]?|calculez|combien|quel(le)? (est|sera) (le|la) (total|volume|poids|masse|prix|co[ûu]t|montant|surface|nombre|pourcentage|taux|dur[ée]e)|pourcentage|\d+\s*[x×*\/+]\s*\d+|m3|m³|m2|m²|chf|kg\b|tonnes?)/u', $t );
	}

	/** Remet une question proposée par l'IA dans un format sûr et complet ; null si inutilisable. */
	private static function generator_normalize( array $q, array $types ): ?array {
		$type = sanitize_key( (string) ( $q['type'] ?? '' ) );
		if ( 'multiple_choice' === $type ) {
			$type = 'qcm';
		}
		if ( ! isset( self::generator_types()[ $type ] ) ) {
			return null;
		}
		$text   = static fn( $v ) => trim( sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' ) );
		$prompt = $text( $q['prompt'] ?? '' );
		if ( '' === $prompt ) {
			return null;
		}
		$half   = static fn( $v ) => max( 0, round( (float) str_replace( ',', '.', (string) $v ) * 2 ) / 2 );
		$points = $half( $q['points'] ?? 1 );
		$criteria = array();
		foreach ( (array) ( $q['criteria'] ?? array() ) as $c ) {
			$label = is_array( $c ) ? $text( $c['label'] ?? '' ) : $text( $c );
			if ( '' !== $label ) {
				$criteria[] = array( 'label' => $label, 'points' => is_array( $c ) ? $half( $c['points'] ?? 0 ) : 0 );
			}
		}
		$sum = array_sum( array_column( $criteria, 'points' ) );
		if ( $sum > 0 ) {
			$points = $sum;
		}
		$points = $points > 0 ? min( 20, $points ) : 1;
		$out = array(
			// 1.38.4 : calculatrice comme dans l'éditeur (case « Afficher la calculatrice ») ; d'office pour un calcul.
			'calculator'      => 'calculation' === $type || ! empty( $q['calculator'] ) || self::generator_needs_calculator( $prompt . ' ' . $text( $q['expected_answer'] ?? '' ) ),
			'type'            => $type,
			'context'         => $text( $q['context'] ?? '' ),
			'prompt'          => $prompt,
			'points'          => $points,
			'expected_answer' => $text( $q['expected_answer'] ?? '' ),
			'criteria'        => $criteria,
			'source'          => $text( $q['source'] ?? '' ),
			'objective'       => $text( $q['objective'] ?? '' ),
		);
		switch ( $type ) {
			case 'qcm':
				$options = array_values( array_filter( array_map( $text, (array) ( $q['options'] ?? array() ) ), 'strlen' ) );
				if ( count( $options ) < 2 ) {
					return null;
				}
				$index = (int) ( $q['correct_index'] ?? 0 );
				$out['options']         = array_slice( $options, 0, 6 );
				$out['correct_index']   = $index >= 0 && $index < count( $out['options'] ) ? $index : 0;
				$out['expected_answer'] = $out['options'][ $out['correct_index'] ];
				break;
			case 'true_false':
			case 'true_false_justify':
				$tf = ucfirst( strtolower( remove_accents( $text( $q['true_false'] ?? $q['expected_answer'] ?? '' ) ) ) );
				$out['true_false'] = in_array( $tf, array( 'Vrai', 'Faux' ), true ) ? $tf : 'Vrai';
				if ( 'true_false_justify' === $type ) {
					$out['justification'] = $text( $q['justification'] ?? $q['expected_answer'] ?? '' );
					$out['choice_points'] = min( $points, $half( $q['choice_points'] ?? 1 ) );
				}
				$out['expected_answer'] = $out['true_false'];
				break;
			case 'calculation':
				$calc = (array) ( $q['calculation'] ?? array() );
				$precision = (string) ( $calc['precision'] ?? 'exact' );
				$out['calculation'] = array(
					'development' => $text( $calc['development'] ?? '' ),
					'result'      => $text( $calc['result'] ?? $q['expected_answer'] ?? '' ),
					'precision'   => in_array( $precision, LogiEval_Secure_Question_Types::PRECISIONS, true ) ? $precision : 'exact',
				);
				if ( '' === $out['calculation']['result'] ) {
					return null;
				}
				break;
			case 'ordering':
				$items = array_values( array_filter( array_map( $text, (array) ( $q['items'] ?? array() ) ), 'strlen' ) );
				if ( count( $items ) < 3 ) {
					return null;
				}
				$out['items']         = array_slice( $items, 0, 10 );
				$out['order_scoring'] = 'all' === ( $q['order_scoring'] ?? '' ) ? 'all' : 'partial';
				break;
			case 'matching':
				$pairs = array();
				$decoy = array();
				foreach ( (array) ( $q['pairs'] ?? array() ) as $p ) {
					$l = $text( is_array( $p ) ? ( $p['left'] ?? '' ) : '' );
					$r = $text( is_array( $p ) ? ( $p['right'] ?? '' ) : '' );
					if ( '' !== $l && '' !== $r ) {
						$pairs[] = array( 'left' => $l, 'right' => $r );
					} elseif ( '' === $l && '' !== $r ) {
						$decoy[] = array( 'left' => '', 'right' => $r, 'decoy' => true ); // intrus (élément de droite seul, comme dans l'éditeur)
					}
				}
				foreach ( (array) ( $q['distractors'] ?? array() ) as $d ) {
					$r = $text( is_array( $d ) ? ( $d['right'] ?? '' ) : $d );
					if ( '' !== $r ) {
						$decoy[] = array( 'left' => '', 'right' => $r, 'decoy' => true );
					}
				}
				if ( count( $pairs ) < 2 ) {
					return null;
				}
				// Comme dans l'éditeur : paires justes puis intrus (élément de droite seul).
				$out['pairs']           = array_merge( array_slice( $pairs, 0, 10 ), array_slice( $decoy, 0, 3 ) );
				$out['match_scoring']   = 'all' === ( $q['match_scoring'] ?? '' ) ? 'all' : 'partial';
				$out['expected_answer'] = implode( ' ; ', array_map( static fn( $p ) => $p['left'] . ' → ' . $p['right'], array_slice( $pairs, 0, 10 ) ) );
				break;
			case 'fill_blanks':
				$bt = $text( $q['blanks_text'] ?? '' );
				if ( count( LogiEval_Secure_Question_Types::parse_blanks_text( $bt )['blanks'] ) < 1 ) {
					return null;
				}
				$out['blanks_text']     = $bt;
				$out['expected_answer'] = $bt;
				$out['blanks_bank']     = ! array_key_exists( 'bank', $q ) && ! array_key_exists( 'blanks_bank', $q ) ? true : ! empty( $q['blanks_bank'] ?? $q['bank'] );
				$out['blanks_tolerant'] = ! array_key_exists( 'blanks_tolerant', $q ) || ! empty( $q['blanks_tolerant'] );
				$out['blanks_scoring']  = 'all' === ( $q['blanks_scoring'] ?? '' ) ? 'all' : 'partial';
				break;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Enregistrement : banque de questions (+ évaluation brouillon)
	 * ------------------------------------------------------------------- */

	public static function handle_gen_save(): void {
		self::require_teacher_post();
		if ( ! self::generator_allowed() ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$t         = LogiEval_Secure_Database::tables();
		$course_id = absint( $_POST['sensei_course_id'] ?? 0 );
		$list      = json_decode( (string) wp_unslash( $_POST['questions'] ?? '' ), true );
		$saved     = array();
		foreach ( is_array( $list ) ? array_slice( $list, 0, 60 ) : array() as $q ) {
			$q = self::generator_normalize( is_array( $q ) ? $q : array(), array_keys( self::generator_types() ) );
			if ( ! $q ) {
				continue;
			}
			$row = array(
				'show_calculator'  => ! empty( $q['calculator'] ) ? 1 : 0,
				'prompt'           => $q['prompt'],
				'context'          => $q['context'],
				'type'             => $q['type'],
				'points'           => $q['points'],
				'criteria'         => $q['criteria'],
				'expected_answer'  => $q['expected_answer'],
				'sensei_course_id' => $course_id,
			);
			if ( 'qcm' === $q['type'] ) {
				$row['qcm_options']       = $q['options'];
				$row['qcm_correct_index'] = $q['correct_index'];
			} elseif ( 'true_false' === $q['type'] ) {
				$row['true_false_answer'] = $q['true_false'];
			} elseif ( 'true_false_justify' === $q['type'] ) {
				$row['tfj_choice']        = $q['true_false'];
				$row['tfj_justification'] = $q['justification'];
				$row['tfj_choice_points'] = (string) $q['choice_points'];
			} elseif ( 'calculation' === $q['type'] ) {
				$row['calculation_development'] = $q['calculation']['development'];
				$row['calculation_result']      = $q['calculation']['result'];
				$row['calculation_precision']   = $q['calculation']['precision'];
			} elseif ( 'ordering' === $q['type'] ) {
				$row['order_mode']    = 'items';
				$row['order_scoring'] = $q['order_scoring'];
				$row['order_items']   = array_map( static fn( $item ) => array( 'text' => $item ), $q['items'] );
			} elseif ( 'matching' === $q['type'] ) {
				$row['match_scoring'] = $q['match_scoring'];
				$row['match_pairs']   = $q['pairs'];
			} elseif ( 'fill_blanks' === $q['type'] ) {
				$row['blanks_text']     = $q['blanks_text'];
				$row['blanks_scoring']  = $q['blanks_scoring'];
				$row['blanks_bank']     = $q['blanks_bank'] ? 1 : 0;
				$row['blanks_tolerant'] = $q['blanks_tolerant'] ? 1 : 0;
			}
			// Même chemin que l'éditeur de questions (les valeurs sont attendues « slashées » comme un formulaire).
			$data = self::build_question_data( wp_slash( $row ), $course_id );
			if ( ! $data ) {
				continue;
			}
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $t['questions'], $data );
			if ( $wpdb->insert_id ) {
				$saved[] = array( 'id' => (int) $wpdb->insert_id, 'points' => (float) $data['points'] );
			}
		}
		if ( ! $saved ) {
			wp_safe_redirect( self::post_redirect_url( self::app_url( 'generator', array( 'gen_error' => 1 ) ), array( 'logiexam_view' => 'generator', 'gen_error' => 1 ) ) );
			exit;
		}

		if ( 'evaluation' === sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['evaluation_title'] ?? '' ) );
			$wpdb->insert(
				$t['evaluations'],
				array(
					'title'            => '' !== $title ? $title : __( 'Évaluation créée avec l’IA', 'logieval-secure' ),
					'description'      => '',
					'instructions'     => '',
					'duration_minutes' => max( 5, min( 300, absint( $_POST['duration_minutes'] ?? 45 ) ) ),
					'total_points'     => array_sum( array_column( $saved, 'points' ) ),
					'sensei_course_id' => $course_id,
					'sensei_group_ids' => '[]',
					'student_user_ids' => '[]',
					'evaluation_type'  => 'simple_quiz',
					'security_notice'  => self::default_security_notice(),
					'secure_mode'      => 1,
					'anti_cheat_rules' => wp_json_encode( array( 'fullscreen', 'visibilitychange', 'blur', 'no_contextmenu', 'no_copy_paste', 'keyboard_guard' ) ),
					'status'           => 'draft',
					'created_by'       => get_current_user_id(),
					'created_at'       => current_time( 'mysql' ),
					'updated_at'       => current_time( 'mysql' ),
				)
			);
			$evaluation_id = (int) $wpdb->insert_id;
			if ( $evaluation_id ) {
				foreach ( $saved as $position => $question ) {
					$wpdb->insert( $t['eval_questions'], array( 'evaluation_id' => $evaluation_id, 'question_id' => $question['id'], 'position' => $position + 1, 'points' => $question['points'] ) );
				}
				$args = array( 'evaluation_id' => $evaluation_id, 'gen_created' => count( $saved ) );
				wp_safe_redirect( self::post_redirect_url( self::app_url( 'create', $args ), array_merge( array( 'logiexam_view' => 'create' ), $args ) ) );
				exit;
			}
		}
		wp_safe_redirect( self::post_redirect_url( self::app_url( 'questions', array( 'gen_saved' => count( $saved ) ) ), array( 'logiexam_view' => 'questions', 'gen_saved' => count( $saved ) ) ) );
		exit;
	}
}
