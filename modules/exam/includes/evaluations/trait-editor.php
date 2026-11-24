<?php
/**
 * LogiEval_Secure_Evaluations — partie « editor ».
 * Méthodes regroupées par thème ; elles sont utilisées par la classe principale (trait).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Editor {		private static function render_create_workspace( ?array $evaluation, array $questions, array $courses, array $groups ): void {
		?>
		<section class="les-panel">
			<h2><?php echo esc_html( $evaluation ? __( 'Modifier une évaluation', 'logieval-secure' ) : __( 'Créer une évaluation', 'logieval-secure' ) ); ?></h2>
			<p><?php esc_html_e( 'Définissez le cadre pédagogique, les dates, les groupes Sensei, les questions et les règles de sécurité.', 'logieval-secure' ); ?></p>
			<?php if ( $evaluation && isset( $_GET['duplicated'] ) ) : ?>
				<div class="notice notice-warning inline les-duplicate-notice"><p><strong><?php esc_html_e( 'Copie créée (brouillon).', 'logieval-secure' ); ?></strong> <?php esc_html_e( 'Le groupe classe, les élèves spécifiques et les dates d’ouverture et de fermeture n’ont pas été repris : choisissez-les ci-dessous, puis enregistrez.', 'logieval-secure' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $evaluation && ! self::$embedded_in_evaluation ) : ?>
				<?php self::render_evaluation_summary( $evaluation ); ?>
			<?php endif; ?>
			<?php self::render_evaluation_form( $evaluation, $questions, $courses, $groups ); ?>
		</section>
		<?php
	}

	/** Réglage « Safe Exam Browser » de l'évaluation (LogiOne 1.29). */
	private static function render_seb_settings( int $evaluation_id ): void {
		$seb = LogiEval_Secure_SEB::get( $evaluation_id );
		?>
		<fieldset class="les-panel les-seb-settings" data-les-seb-settings style="box-shadow:none;margin:14px 0;background:#f8fafc">
			<legend><strong><?php esc_html_e( '🔒 Safe Exam Browser (verrouillage complet de l’ordinateur)', 'logieval-secure' ); ?></strong></legend>
			<p class="description"><?php esc_html_e( 'Safe Exam Browser (gratuit) bloque les autres applications, les extensions, les assistants IA et les raccourcis du système pendant l’évaluation. Il doit être installé sur les ordinateurs des élèves.', 'logieval-secure' ); ?>
				<a href="<?php echo esc_url( LogiEval_Secure_SEB::DOWNLOAD_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Télécharger Safe Exam Browser', 'logieval-secure' ); ?></a></p>
			<label><?php esc_html_e( 'Exiger Safe Exam Browser', 'logieval-secure' ); ?>
				<select name="seb_mode" data-les-seb-mode>
					<?php foreach ( LogiEval_Secure_SEB::modes() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $seb['mode'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div data-les-seb-details <?php echo 'off' === $seb['mode'] ? 'hidden' : ''; ?>>
				<label><?php esc_html_e( 'Mot de passe pour quitter Safe Exam Browser (recommandé)', 'logieval-secure' ); ?>
					<input type="text" name="seb_quit_password" value="" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $seb['quit_hash'] ? __( 'Mot de passe enregistré - laisser vide pour le garder', 'logieval-secure' ) : __( 'Ex. : un mot que vous seul connaissez', 'logieval-secure' ) ); ?>">
					<span class="description"><?php esc_html_e( 'Sans mot de passe, un élève peut fermer Safe Exam Browser (sa sortie est alors notée comme d’habitude). À la fin de l’évaluation, un bouton « Quitter Safe Exam Browser » ferme le programme sans mot de passe.', 'logieval-secure' ); ?></span>
				</label>
				<?php if ( '' !== $seb['quit_hash'] ) : ?>
					<label class="les-checkbox-line"><input type="checkbox" name="seb_quit_clear" value="1"> <?php esc_html_e( 'Supprimer le mot de passe', 'logieval-secure' ); ?></label>
				<?php endif; ?>
				<label data-les-seb-keys><?php esc_html_e( 'Clé(s) de configuration (Config Key) - seulement pour « clé vérifiée »', 'logieval-secure' ); ?>
					<textarea class="widefat" name="seb_keys" rows="2" placeholder="<?php esc_attr_e( '64 caractères (0-9, a-f), une clé par ligne', 'logieval-secure' ); ?>"><?php echo esc_textarea( implode( "\n", $seb['keys'] ) ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Ouvrez le fichier .seb ci-dessous dans l’outil de configuration de Safe Exam Browser (« SEBConfigTool »), onglet « Exam » : copiez la « Config Key » et collez-la ici. Si vous modifiez ce réglage plus tard, la clé change : téléchargez à nouveau le fichier et recopiez la clé.', 'logieval-secure' ); ?></span>
				</label>
				<?php if ( $evaluation_id && 'off' !== $seb['mode'] ) : ?>
					<div class="les-seb-links">
						<p><strong><?php esc_html_e( 'Lien à donner aux élèves :', 'logieval-secure' ); ?></strong>
							<code data-les-seb-link><?php echo esc_html( LogiEval_Secure_SEB::seb_link( $evaluation_id ) ); ?></code>
							<button type="button" class="button button-small" data-les-copy="<?php echo esc_attr( LogiEval_Secure_SEB::seb_link( $evaluation_id ) ); ?>"><?php esc_html_e( 'Copier', 'logieval-secure' ); ?></button></p>
						<p class="description"><?php esc_html_e( 'Ce lien ouvre Safe Exam Browser directement sur la page de l’évaluation. Les élèves peuvent aussi simplement entrer leur code dans leur navigateur : LogiExam leur affichera alors un bouton « Ouvrir dans Safe Exam Browser ».', 'logieval-secure' ); ?></p>
						<p><a class="button" href="<?php echo esc_url( LogiEval_Secure_SEB::config_url( $evaluation_id ) ); ?>"><?php esc_html_e( 'Télécharger le fichier de configuration (.seb)', 'logieval-secure' ); ?></a>
							<a class="button" href="<?php echo esc_attr( LogiEval_Secure_SEB::seb_link( $evaluation_id ) ); ?>"><?php esc_html_e( 'Tester sur cet ordinateur (Safe Exam Browser installé)', 'logieval-secure' ); ?></a></p>
						<p class="description"><?php esc_html_e( 'Test : Safe Exam Browser s’ouvre sur la page élève et affiche en haut « ✓ Safe Exam Browser détecté » (et « ✓ Configuration vérifiée » en mode clé). Pour en sortir, utilisez le mot de passe de sortie.', 'logieval-secure' ); ?></p>
					</div>
				<?php elseif ( 'off' !== $seb['mode'] || ! $evaluation_id ) : ?>
					<p class="description"><?php esc_html_e( 'Enregistrez l’évaluation pour obtenir le lien et le fichier Safe Exam Browser.', 'logieval-secure' ); ?></p>
				<?php endif; ?>
			</div>
			<script>
			(function () {
				var box = document.currentScript.closest('[data-les-seb-settings]');
				var sel = box.querySelector('[data-les-seb-mode]');
				var det = box.querySelector('[data-les-seb-details]');
				var keys = box.querySelector('[data-les-seb-keys]');
				var upd = function () { det.hidden = sel.value === 'off'; keys.style.display = sel.value === 'key' ? '' : 'none'; };
				sel.addEventListener('change', upd); upd();
				box.querySelectorAll('[data-les-copy]').forEach(function (b) {
					b.addEventListener('click', function () {
						var t = b.getAttribute('data-les-copy');
						(navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { b.textContent = '✓ Copié'; }).catch(function () { window.prompt('Copiez ce lien :', t); });
					});
				});
			})();
			</script>
		</fieldset>
		<?php
	}

	private static function render_evaluation_summary( array $evaluation ): void {
		$question_count = count( self::get_questions_for_evaluation( (int) $evaluation['id'] ) );
		?>
		<div class="les-panel" style="box-shadow:none;margin:14px 0;background:#f8fafc">
			<h3><?php esc_html_e( 'Aperçu de l’évaluation', 'logieval-secure' ); ?></h3>
			<table class="widefat striped"><tbody>
				<tr><th><?php esc_html_e( 'Titre', 'logieval-secure' ); ?></th><td><?php echo esc_html( $evaluation['title'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Statut', 'logieval-secure' ); ?></th><td><?php echo esc_html( $evaluation['status'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Dates', 'logieval-secure' ); ?></th><td><?php echo esc_html( trim( (string) $evaluation['opens_at'] . ' - ' . (string) $evaluation['closes_at'], ' -' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Durée', 'logieval-secure' ); ?></th><td><?php echo esc_html( (int) $evaluation['duration_minutes'] ); ?> min</td></tr>
				<tr><th><?php esc_html_e( 'Points', 'logieval-secure' ); ?></th><td><?php echo esc_html( $evaluation['total_points'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Questions', 'logieval-secure' ); ?></th><td><?php echo esc_html( $question_count ); ?></td></tr>
				<?php if ( ! empty( $evaluation['company_name'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Entreprise fictive', 'logieval-secure' ); ?></th><td><?php echo esc_html( $evaluation['company_name'] ); ?></td></tr>
				<?php endif; ?>
				<?php if ( ! empty( $evaluation['apprentice_role'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Rôle / mission', 'logieval-secure' ); ?></th><td><?php echo wp_kses_post( wpautop( $evaluation['apprentice_role'] ) ); ?></td></tr>
				<?php endif; ?>
			</tbody></table>
		</div>
		<?php
	}

	private static function render_questions_workspace( array $questions, array $courses ): void {
		$course_id       = absint( $_GET['sensei_course_id'] ?? 0 );
		$edit_question_id = absint( $_GET['question_id'] ?? 0 );
		$edit_question    = $edit_question_id ? self::get_question( $edit_question_id ) : null;
		$edit_question    = self::can_manage_question( $edit_question ) ? $edit_question : null;
		$module_key      = sanitize_key( wp_unslash( $_GET['question_module'] ?? '' ) );
		$category_slug   = sanitize_title( wp_unslash( $_GET['question_category'] ?? '' ) );
		$modules         = LogiEval_Secure_Sensei::get_question_modules_for_course( $course_id );
		$categories      = LogiEval_Secure_Sensei::get_question_categories( $course_id, $module_key );
		$sensei_questions = LogiEval_Secure_Sensei::get_quiz_questions( $course_id, $category_slug, $module_key );
		$bank_questions  = self::filter_question_bank_by_course( $questions, $course_id );
		?>
		<div class="les-grid">
			<section class="les-panel">
				<h2><?php echo esc_html( $edit_question ? __( 'Modifier une question', 'logieval-secure' ) : __( 'Créer une question', 'logieval-secure' ) ); ?></h2>
				<?php if ( isset( $_GET['question_saved'] ) ) : ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'Question enregistrée.', 'logieval-secure' ); ?></p></div>
				<?php endif; ?>
				<?php if ( ! empty( $_GET['gen_saved'] ) ) : $gen_n = absint( $_GET['gen_saved'] ); ?>
					<div class="notice notice-success inline les-gen-saved"><p><?php echo esc_html( sprintf( _n( '%d question créée avec l’IA a été ajoutée à la banque de questions.', '%d questions créées avec l’IA ont été ajoutées à la banque de questions.', $gen_n, 'logieval-secure' ), $gen_n ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( isset( $_GET['question_deleted'] ) && empty( $_GET['questions_blocked'] ) ) : ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'Question supprimée.', 'logieval-secure' ); ?></p></div>
				<?php endif; ?>
				<?php if ( ! empty( $_GET['questions_blocked'] ) ) : ?>
					<div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( '%d question(s) non supprimée(s) : des élèves y ont déjà répondu. Leurs copies sont conservées.', absint( $_GET['questions_blocked'] ) ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( $edit_question ) : ?>
					<p class="description"><?php esc_html_e( 'Vous modifiez la question enregistrée dans la banque LogiExam. Les prochaines évaluations qui utilisent cette question reprendront cette version.', 'logieval-secure' ); ?></p>
				<?php endif; ?>
				<?php self::render_question_form( $courses, $edit_question ); ?>
			</section>
			<section class="les-panel">
				<h2><?php esc_html_e( 'Banque de questions', 'logieval-secure' ); ?></h2>
				<?php self::render_sensei_question_filters( $courses, $modules, $categories, $course_id, $module_key, $category_slug ); ?>
				<?php self::render_question_bank( $bank_questions, $course_id ); ?>
				<?php self::render_sensei_question_import_table( $sensei_questions ); ?>
			</section>
		</div>
		<?php
	}

		private static function render_evaluation_form( ?array $evaluation, array $questions, array $courses, array $groups ): void {
		$selected_groups    = $evaluation ? self::decode_ids( $evaluation['sensei_group_ids'] ) : array();
		$selected_course    = isset( $_GET['sensei_course_id'] ) ? absint( $_GET['sensei_course_id'] ) : ( isset( $evaluation['sensei_course_id'] ) ? (int) $evaluation['sensei_course_id'] : 0 );
			$settings           = self::get_settings();
			$evaluation_type    = self::normalize_evaluation_type( $evaluation['evaluation_type'] ?? 'mes_company' );
			$security_notice    = ! empty( $evaluation['security_notice'] ) ? (string) $evaluation['security_notice'] : self::default_security_notice();
		$inline_questions   = $evaluation ? self::get_questions_for_evaluation( (int) $evaluation['id'] ) : array();
		if ( empty( $inline_questions ) ) {
			$inline_questions[] = array(
				'id'              => 0,
				'title'           => '',
				'sensei_course_id'=> $selected_course,
				'context'         => '',
				'prompt'          => '',
				'type'            => 'short_answer',
				'points'          => 1,
				'expected_answer' => '',
				'options'         => '[]',
			);
		}
		?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="logieval_secure_save_evaluation">
			<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation['id'] ?? 0 ); ?>">
			<?php self::frontend_redirect_field( array( 'logiexam_view' => 'create', 'evaluation_id' => $evaluation['id'] ?? 0 ) ); ?>
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<label><?php esc_html_e( 'Titre', 'logieval-secure' ); ?><input class="widefat" name="title" required value="<?php echo esc_attr( $evaluation['title'] ?? '' ); ?>"></label>
			<label><?php esc_html_e( 'Description', 'logieval-secure' ); ?><textarea class="widefat" name="description" rows="3"><?php echo esc_textarea( $evaluation['description'] ?? '' ); ?></textarea></label>
			<label><?php esc_html_e( 'Consignes', 'logieval-secure' ); ?><textarea class="widefat" name="instructions" rows="4"><?php echo esc_textarea( $evaluation['instructions'] ?? '' ); ?></textarea></label>
			<div class="les-panel" style="box-shadow:none;margin:14px 0;background:#f8fafc">
				<h3><?php esc_html_e( 'Type d’évaluation', 'logieval-secure' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Choisissez la structure dès le départ. Le mode histoire se construit par sections narratives liées aux questions.', 'logieval-secure' ); ?></p>
				<div class="les-form-row">
					<label><?php esc_html_e( 'Structure', 'logieval-secure' ); ?>
						<select class="widefat" name="evaluation_type" data-logiexam-evaluation-type>
							<option value="mes_company" <?php selected( $evaluation_type, 'mes_company' ); ?>><?php esc_html_e( 'MES Entreprise fictive', 'logieval-secure' ); ?></option>
							<option value="story" <?php selected( $evaluation_type, 'story' ); ?>><?php esc_html_e( 'Mode histoire', 'logieval-secure' ); ?></option>
							<option value="simple_quiz" <?php selected( $evaluation_type, 'simple_quiz' ); ?>><?php esc_html_e( 'Test simple questions / réponses', 'logieval-secure' ); ?></option>
						</select>
					</label>
				</div>
				<label class="les-checkbox-line les-formative-choice">
					<input type="checkbox" name="logiexam_formative" value="1" <?php checked( ! empty( $evaluation['id'] ) && self::is_formative( (int) $evaluation['id'] ) ); ?>>
					<span><strong><?php esc_html_e( 'Test formatif', 'logieval-secure' ); ?></strong> — <?php esc_html_e( 'pour s’entraîner : pas de correction à faire (ni par l’IA, ni par vous), rien dans LogiNotes, pas de bonus / malus. Dès qu’il a envoyé sa copie, l’élève voit sur sa page LogiExam le corrigé (sa réponse à côté de la réponse attendue) pour s’autocorriger. La passation reste celle d’un examen (code, sécurité).', 'logieval-secure' ); ?></span>
				</label>
				<div data-logiexam-story-settings>
					<label><?php esc_html_e( 'Introduction générale de l’histoire', 'logieval-secure' ); ?><textarea class="widefat" name="story_intro" rows="5"><?php echo esc_textarea( $evaluation['story_intro'] ?? '' ); ?></textarea></label>
					<p class="description"><?php esc_html_e( 'En mode histoire, ce texte est affiché seul avant les questions. Les sections suivantes se définissent ensuite dans les questions.', 'logieval-secure' ); ?></p>
				</div>
				<label><?php esc_html_e( 'Message affiché avant le démarrage', 'logieval-secure' ); ?><textarea class="widefat" name="security_notice" rows="4"><?php echo esc_textarea( $security_notice ); ?></textarea></label>
			</div>
			<?php self::render_seb_settings( (int) ( $evaluation['id'] ?? 0 ) ); ?>
			<div class="les-form-row">
				<label><?php esc_html_e( 'Durée en minutes', 'logieval-secure' ); ?><input type="number" min="0" name="duration_minutes" value="<?php echo esc_attr( $evaluation['duration_minutes'] ?? 45 ); ?>"></label>
				<label><?php esc_html_e( 'Ouverture', 'logieval-secure' ); ?><input type="datetime-local" name="opens_at" value="<?php echo esc_attr( self::datetime_local_value( $evaluation['opens_at'] ?? '' ) ); ?>"></label>
				<label><?php esc_html_e( 'Fermeture', 'logieval-secure' ); ?><input type="datetime-local" name="closes_at" value="<?php echo esc_attr( self::datetime_local_value( $evaluation['closes_at'] ?? '' ) ); ?>"></label>
			</div>
			<label><?php esc_html_e( 'Cours Sensei recommandé', 'logieval-secure' ); ?>
				<select class="widefat" name="sensei_course_id">
					<option value="0"><?php esc_html_e( 'Aucun cours sélectionné', 'logieval-secure' ); ?></option>
					<?php foreach ( $courses as $course ) : ?>
						<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( (int) $course->ID, $selected_course ); ?>><?php echo esc_html( get_the_title( $course ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="description"><?php esc_html_e( 'Sélectionner un cours Sensei est recommandé pour organiser les examens. Son contenu et ses leçons liées serviront aussi de référence théorique pour la pré-correction IA.', 'logieval-secure' ); ?></span>
			</label>
			<div class="les-panel" data-logiexam-company-panel style="box-shadow:none;margin:14px 0;background:#f8fafc">
				<h3><?php esc_html_e( 'Entreprise fictive', 'logieval-secure' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Ces informations sont facultatives. Si elles sont complétées, elles apparaîtront avant les questions dans l’interface élève.', 'logieval-secure' ); ?></p>
				<div class="les-form-row">
					<label><?php esc_html_e( "Nom de l'entreprise", 'logieval-secure' ); ?><input class="widefat" name="company_name" value="<?php echo esc_attr( $evaluation['company_name'] ?? '' ); ?>"></label>
				</div>
				<label><?php esc_html_e( "Rôle / mission de l'apprenti", 'logieval-secure' ); ?><textarea class="widefat" name="apprentice_role" rows="4"><?php echo esc_textarea( $evaluation['apprentice_role'] ?? '' ); ?></textarea></label>
				<label><?php esc_html_e( "Contexte de l'entreprise", 'logieval-secure' ); ?><textarea class="widefat" name="company_context" rows="3"><?php echo esc_textarea( $evaluation['company_context'] ?? '' ); ?></textarea></label>
				<div class="les-company-logo-field" data-les-media-field>
				<label><?php esc_html_e( "Logo de l'entreprise", 'logieval-secure' ); ?><input type="file" name="company_logo" accept="image/*"></label>
				<input type="hidden" name="company_logo_media_id" value="0" data-les-media-input>
				<?php self::render_media_pick_button(); ?>
				<?php if ( ! empty( $evaluation['company_logo_id'] ) ) : $logo_url = wp_get_attachment_image_url( (int) $evaluation['company_logo_id'], 'medium' ); ?>
					<?php if ( $logo_url ) : ?>
						<div class="les-company-logo-preview"><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo actuel', 'logieval-secure' ); ?>"></div>
					<?php endif; ?>
				<?php endif; ?>
				</div>
			</div>
			<?php $selected_group = (int) ( $selected_groups[0] ?? 0 ); ?>
			<?php $favorite_groups = self::favorite_group_ids(); ?>
			<fieldset class="les-group-radios" data-les-groups data-fav-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-fav-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">
				<legend><?php esc_html_e( 'Groupe classe Sensei', 'logieval-secure' ); ?></legend>
				<div class="les-groups-tools" data-les-groups-tools hidden>
					<div class="les-groups-seg" role="group" aria-label="<?php esc_attr_e( 'Groupes affichés', 'logieval-secure' ); ?>">
						<button type="button" data-mode="fav" aria-pressed="false"><span class="les-star-icon" aria-hidden="true">★</span> <?php esc_html_e( 'Mes classes favorites', 'logieval-secure' ); ?> · <span data-les-favcount>0</span></button>
						<button type="button" data-mode="all" aria-pressed="true"><?php esc_html_e( 'Toutes les classes', 'logieval-secure' ); ?> · <?php echo (int) count( $groups ); ?></button>
					</div>
					<input type="search" class="les-groups-search" data-les-groups-search placeholder="<?php esc_attr_e( 'Rechercher une classe…', 'logieval-secure' ); ?>" aria-label="<?php esc_attr_e( 'Rechercher une classe', 'logieval-secure' ); ?>">
				</div>
				<p class="description les-groups-hint" data-les-groups-hint hidden><?php esc_html_e( 'Cliquez sur l’étoile d’une classe pour l’ajouter à vos favorites ou l’en retirer (mêmes favorites que dans LogiProgress).', 'logieval-secure' ); ?></p>
				<div class="les-checklist-panel" data-les-groups-list data-mode="all">
					<label class="les-check is-none"><input type="radio" name="sensei_group_ids[]" value="0" <?php checked( $selected_group, 0 ); ?>> <span><?php esc_html_e( 'Aucun groupe (uniquement les élèves spécifiques ci-dessous)', 'logieval-secure' ); ?></span></label>
					<?php foreach ( $groups as $group ) : ?>
						<?php
						$gid    = (int) $group->term_id;
						$is_fav = in_array( $gid, $favorite_groups, true );
						$label  = $is_fav ? sprintf( __( 'Retirer %s des favorites', 'logieval-secure' ), $group->name ) : sprintf( __( 'Ajouter %s aux favorites', 'logieval-secure' ), $group->name );
						?>
						<div class="les-group-row" data-les-group data-fav="<?php echo $is_fav ? '1' : '0'; ?>" data-search="<?php echo esc_attr( strtolower( remove_accents( (string) $group->name ) ) ); ?>">
							<label class="les-check"><input type="radio" name="sensei_group_ids[]" value="<?php echo esc_attr( $gid ); ?>" <?php checked( $selected_group, $gid ); ?>> <span><?php echo esc_html( $group->name ); ?></span></label>
							<button type="button" class="les-group-star<?php echo $is_fav ? ' is-fav' : ''; ?>" data-les-star="<?php echo esc_attr( $gid ); ?>" data-name="<?php echo esc_attr( $group->name ); ?>" aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>" hidden>★</button>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="description" data-les-groups-nofav hidden><?php esc_html_e( 'Aucune classe favorite pour l’instant : ouvrez « Toutes les classes » et cliquez sur l’étoile de vos classes.', 'logieval-secure' ); ?></p>
				<button type="button" class="button-link les-groups-showall" data-les-groups-showall hidden><?php echo esc_html( sprintf( __( 'Une autre classe ? Voir toutes les classes (%d) →', 'logieval-secure' ), count( $groups ) ) ); ?></button>
				<?php if ( count( $selected_groups ) > 1 ) : ?>
					<p class="description"><?php esc_html_e( 'Cette évaluation était liée à plusieurs groupes. Un seul groupe peut maintenant être choisi : vérifiez la sélection avant d’enregistrer.', 'logieval-secure' ); ?></p>
				<?php endif; ?>
			</fieldset>
			<label><?php esc_html_e( 'Élèves spécifiques (ID utilisateurs séparés par des virgules)', 'logieval-secure' ); ?><input class="widefat" name="student_user_ids" value="<?php echo esc_attr( implode( ',', self::decode_ids( $evaluation['student_user_ids'] ?? '' ) ) ); ?>"></label>
				<label><input type="checkbox" name="secure_mode" value="1" <?php checked( (int) ( $evaluation['secure_mode'] ?? $settings['secure_mode_default'] ), 1 ); ?>> <?php esc_html_e( 'Mode sécurisé actif', 'logieval-secure' ); ?></label>
				<label class="les-shuffle-option"><input type="checkbox" name="shuffle_questions" value="1" <?php checked( $evaluation ? self::exam_options( (int) $evaluation['id'] )['shuffle_questions'] : 0, 1 ); ?>> <?php esc_html_e( 'Ordre aléatoire des questions', 'logieval-secure' ); ?> <small><?php esc_html_e( '(chaque élève reçoit les questions dans un ordre différent, toujours le même pour lui ; la correction et les exports gardent l’ordre de l’évaluation. Déconseillé pour une évaluation « histoire » dont les questions se suivent.)', 'logieval-secure' ); ?></small></label>
				<label class="les-library-option"><input type="checkbox" name="in_library" value="1" <?php checked( $evaluation ? self::exam_options( (int) $evaluation['id'] )['in_library'] : 0, 1 ); ?>> <?php esc_html_e( 'Ajouter à ma bibliothèque (non partagée)', 'logieval-secure' ); ?> <small><?php esc_html_e( '(pour la retrouver facilement par cours Sensei et la réutiliser plus tard ; vous seul la voyez)', 'logieval-secure' ); ?></small></label>
				<label class="les-share-option"><input type="checkbox" name="is_shared" value="1" <?php checked( (int) ( $evaluation['is_shared'] ?? 0 ), 1 ); ?>> <?php esc_html_e( 'Partager dans la bibliothèque des enseignants', 'logieval-secure' ); ?> <small><?php esc_html_e( '(vos collègues pourront la consulter et en faire une copie ; ils ne voient ni vos élèves ni vos résultats)', 'logieval-secure' ); ?></small></label>
			<?php self::render_inline_question_editor( $inline_questions, $courses, $selected_course, $evaluation_type ); ?>
			<?php self::render_bareme_panel( (float) array_sum( array_map( static fn( $q ) => (float) ( $q['evaluation_points'] ?? $q['points'] ?? 0 ), $inline_questions ) ) ); ?>
				<?php
				$is_draft     = ! $evaluation || 'draft' === (string) ( $evaluation['status'] ?? '' );
				$has_attempts = $evaluation && ! empty( self::get_attempts( (int) $evaluation['id'] ) );
				$final_label  = ! $evaluation ? __( 'Enregistrer la version définitive', 'logieval-secure' ) : ( $is_draft ? __( 'Publier la version définitive', 'logieval-secure' ) : __( 'Mettre à jour', 'logieval-secure' ) );
				?>
				<?php if ( ! $has_attempts ) : ?>
					<button type="submit" name="save_mode" value="draft" class="button les-fab-draft" title="<?php esc_attr_e( 'Enregistre sans créer les codes élèves : les élèves n’y ont pas accès.', 'logieval-secure' ); ?>"><span aria-hidden="true">✎</span> <?php esc_html_e( 'Enregistrer comme brouillon', 'logieval-secure' ); ?></button>
				<?php endif; ?>
				<button type="submit" name="save_mode" value="final" class="button button-primary les-fab-save" title="<?php esc_attr_e( 'Version définitive : les codes élèves sont créés et l’évaluation s’ouvre aux élèves aux dates prévues.', 'logieval-secure' ); ?>"><span aria-hidden="true">✓</span> <?php echo esc_html( $final_label ); ?></button>
		</form>
		<script>
			/* Boutons flottants (Ajouter une question, Mettre à jour) : masqués seulement une fois le formulaire dépassé
			   (ex. panneau de progression plus bas), pour ne pas les confondre avec d'autres boutons. */
			(function () {
				var save = document.querySelector('.les-fab-save');
				var form = save ? save.closest('form') : null;
				if (!form) return;
				var update = function () {
					var r = form.getBoundingClientRect();
					document.body.classList.toggle('les-fabs-off', r.bottom < 180);
				};
				window.addEventListener('scroll', update, { passive: true });
				window.addEventListener('resize', update);
				update();
			}());
			/* Classes favorites : seules les classes de l'enseignant s'affichent ; « Toutes les classes » pour les autres. */
			(function () {
				var box = document.querySelector('[data-les-groups]');
				if (!box) return;
				var list = box.querySelector('[data-les-groups-list]');
				var tools = box.querySelector('[data-les-groups-tools]');
				var search = box.querySelector('[data-les-groups-search]');
				var hint = box.querySelector('[data-les-groups-hint]');
				var nofav = box.querySelector('[data-les-groups-nofav]');
				var showAll = box.querySelector('[data-les-groups-showall]');
				var rows = Array.prototype.slice.call(box.querySelectorAll('[data-les-group]'));
				var norm = function (t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim(); };
				function favCount() { return rows.filter(function (r) { return r.getAttribute('data-fav') === '1'; }).length; }
				function render() {
					var mode = list.getAttribute('data-mode');
					var q = mode === 'all' ? norm(search.value) : '';
					var shown = 0;
					rows.forEach(function (r) {
						var checked = r.querySelector('input').checked;
						var ok = mode === 'all' ? (!q || r.getAttribute('data-search').indexOf(q) !== -1 || norm(r.textContent).replace(/\s/g, '').indexOf(q.replace(/\s/g, '')) !== -1 || checked) : (r.getAttribute('data-fav') === '1' || checked);
						r.hidden = !ok;
						if (ok) shown++;
					});
					tools.querySelectorAll('[data-mode]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-mode') === mode ? 'true' : 'false'); });
					box.querySelector('[data-les-favcount]').textContent = String(favCount());
					search.hidden = mode !== 'all';
					hint.hidden = mode !== 'all';
					showAll.hidden = mode !== 'fav';
					nofav.hidden = !(mode === 'fav' ? favCount() === 0 : false);
				}
				function setMode(m) { list.setAttribute('data-mode', m); render(); }
				tools.hidden = false;
				rows.forEach(function (r) { r.querySelector('[data-les-star]').hidden = false; });
				tools.querySelectorAll('[data-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); }); });
				showAll.addEventListener('click', function () { setMode('all'); search.focus(); });
				search.addEventListener('input', render);
				search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
				list.addEventListener('change', render);
				box.addEventListener('click', function (e) {
					var star = e.target.closest('[data-les-star]');
					if (!star) return;
					e.preventDefault();
					var row = star.closest('[data-les-group]');
					var body = new FormData();
					body.append('action', 'logieval_secure_toggle_favorite_group');
					body.append('_wpnonce', box.getAttribute('data-fav-nonce'));
					body.append('group_id', star.getAttribute('data-les-star'));
					star.disabled = true;
					fetch(box.getAttribute('data-fav-url'), { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); }).then(function (res) {
						star.disabled = false;
						if (!res || !res.success) return;
						var fav = !!res.data.fav, name = star.getAttribute('data-name');
						var label = fav ? 'Retirer ' + name + ' des favorites' : 'Ajouter ' + name + ' aux favorites';
						row.setAttribute('data-fav', fav ? '1' : '0');
						star.classList.toggle('is-fav', fav);
						star.setAttribute('aria-pressed', fav ? 'true' : 'false');
						star.setAttribute('aria-label', label);
						star.setAttribute('title', label);
						render();
					}).catch(function () { star.disabled = false; });
				});
				setMode(favCount() > 0 ? 'fav' : 'all');
			}());
			document.addEventListener('click', function(event) {
				var button = event.target.closest('[data-logiexam-add-question]');
				if (!button) return;
				event.preventDefault();
				var list = document.querySelector('[data-logiexam-inline-questions]');
				var template = document.querySelector('[data-logiexam-question-template]');
				if (!list || !template) return;
				var index = parseInt(list.dataset.nextIndex || '0', 10);
				var lastCourse = '';
				var lastSelect = list.querySelector('.les-inline-question:last-of-type select[data-logiexam-question-course]');
				if (lastSelect) lastCourse = lastSelect.value;
				var html = template.innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__NUMBER__', String(index + 1));
				list.insertAdjacentHTML('beforeend', html);
				var newSelect = list.querySelector('.les-inline-question:last-of-type select[data-logiexam-question-course]');
				if (newSelect ? lastCourse : '') newSelect.value = lastCourse;
				list.dataset.nextIndex = String(index + 1);
				logiexamRefreshAllQuestionTypes();
				// La nouvelle question est amenée à l'écran, prête à être remplie (bouton flottant toujours accessible).
				var all = list.querySelectorAll('.les-inline-question');
				var added = all.length ? all[all.length - 1] : null;
				if (added) {
					added.scrollIntoView({ behavior: 'smooth', block: 'center' });
					var first = added.querySelector('textarea, input[type="text"], input:not([type]), select');
					if (first) setTimeout(function () { try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); } }, 350);
				}
			});
			/* 1.38.5 : « Problème » = un énoncé commun + des sous-questions (questions normales qui partagent l'énoncé). */
			(function () {
				function list() { return document.querySelector('[data-logiexam-inline-questions]'); }
				function newQuestion(container, type) {
					var l = list();
					var template = document.querySelector('[data-logiexam-question-template]');
					if (!l || !template) return null;
					var index = parseInt(l.dataset.nextIndex || '0', 10);
					container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__NUMBER__', String(index + 1)));
					l.dataset.nextIndex = String(index + 1);
					var q = container.lastElementChild;
					var sel = q ? q.querySelector('select[name$="[type]"]') : null;
					if (sel && type && sel.querySelector('option[value="' + type + '"]')) { sel.value = type; sel.dispatchEvent(new Event('change', { bubbles: true })); }
					var lastCourse = l.querySelectorAll('select[data-logiexam-question-course]');
					if (lastCourse.length > 1) { var c = q.querySelector('select[data-logiexam-question-course]'); if (c) c.value = lastCourse[lastCourse.length - 2].value; }
					if (typeof logiexamRefreshAllQuestionTypes === 'function') logiexamRefreshAllQuestionTypes();
					return q;
				}
				function sync(block) {
					var ctx = block.querySelector('[data-les-problem-context]');
					var items = block.querySelectorAll('[data-les-problem-items] > .les-inline-question');
					items.forEach(function (q, k) {
						var t = q.querySelector('textarea[name$="[context]"]');
						if (t && ctx) t.value = ctx.value;
						var h = q.querySelector('.les-section-title h4');
						if (h) {
							if (!h.dataset.base) h.dataset.base = h.textContent;
							h.textContent = 'Sous-question ' + String.fromCharCode(97 + (k % 26)) + ')';
						}
					});
					var range = block.querySelector('[data-les-problem-range]');
					if (range) range.textContent = items.length + ' sous-question' + (items.length > 1 ? 's' : '');
				}
				function syncAll() { document.querySelectorAll('[data-les-problem]').forEach(sync); }
				document.addEventListener('click', function (event) {
					var add = event.target.closest('[data-les-add-problem]');
					var sub = event.target.closest('[data-les-add-subquestion]');
					var rm = event.target.closest('[data-les-remove-problem]');
					if (add) {
						event.preventDefault();
						var tpl = document.querySelector('[data-les-problem-template]');
						if (!tpl || !list()) return;
						list().insertAdjacentHTML('beforeend', tpl.innerHTML);
						var block = list().lastElementChild;
						var items = block.querySelector('[data-les-problem-items]');
						newQuestion(items, 'calculation');
						newQuestion(items, 'calculation');
						sync(block);
						block.scrollIntoView({ behavior: 'smooth', block: 'start' });
						var ta = block.querySelector('[data-les-problem-context]');
						if (ta) setTimeout(function () { try { ta.focus({ preventScroll: true }); } catch (e) { ta.focus(); } }, 350);
					} else if (sub) {
						event.preventDefault();
						var b = sub.closest('[data-les-problem]');
						var q = newQuestion(b.querySelector('[data-les-problem-items]'), 'calculation');
						sync(b);
						if (q) { q.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
					} else if (rm) {
						event.preventDefault();
						var bl = rm.closest('[data-les-problem]');
						if (bl && window.confirm('Supprimer ce problème et toutes ses sous-questions ?')) bl.remove();
					}
				});
				document.addEventListener('input', function (event) {
					var ctx = event.target.closest('[data-les-problem-context]');
					if (ctx) sync(ctx.closest('[data-les-problem]'));
				});
				/* 1.39.4 : regrouper des questions existantes en problème, ou sortir une sous-question. */
				function renumber() {
					var l = list();
					if (!l) return;
					l.querySelectorAll('.les-inline-question').forEach(function (q, k) {
						if (q.closest('[data-les-problem]')) return;
						var h = q.querySelector('.les-section-title h4');
						if (h) { h.textContent = 'Question ' + (k + 1); h.dataset.base = h.textContent; }
					});
				}
				function picks() {
					var l = list();
					if (!l) return [];
					return Array.prototype.filter.call(l.querySelectorAll('[data-les-group-pick]:checked'), function (c) { return !c.closest('[data-les-problem]'); })
						.map(function (c) { return c.closest('.les-inline-question'); });
				}
				function refreshBar() {
					var btn = document.querySelector('[data-les-group-selected]');
					if (!btn) return;
					var n = picks().length;
					btn.disabled = n < 2;
					var c = btn.querySelector('[data-les-group-count]');
					if (c) c.textContent = n ? '(' + n + ')' : '';
				}
				document.addEventListener('change', function (event) { if (event.target.matches('[data-les-group-pick]')) refreshBar(); });
				document.addEventListener('click', function (event) {
					var grp = event.target.closest('[data-les-group-selected]');
					var out = event.target.closest('[data-les-unproblem]');
					if (grp) {
						event.preventDefault();
						var items = picks();
						if (items.length < 2) { window.alert('Sélectionnez au moins deux questions.'); return; }
						var tpl = document.querySelector('[data-les-problem-template]');
						if (!tpl) return;
						var holder = document.createElement('div');
						holder.innerHTML = tpl.innerHTML;
						var block = holder.firstElementChild;
						while (block && !(block.matches && block.matches('[data-les-problem]'))) block = block.nextElementSibling;
						if (!block) return;
						items[0].parentNode.insertBefore(block, items[0]);
						var box = block.querySelector('[data-les-problem-items]');
						var texts = [];
						items.forEach(function (q) {
							var t = q.querySelector('textarea[name$="[context]"]');
							var v = t ? t.value.trim() : '';
							if (v && texts.indexOf(v) === -1) texts.push(v);
							var pick = q.querySelector('[data-les-group-pick]');
							if (pick) pick.checked = false;
							box.appendChild(q);
						});
						var ctx = block.querySelector('[data-les-problem-context]');
						if (ctx) ctx.value = texts.join('\n\n');
						sync(block);
						renumber();
						refreshBar();
						block.scrollIntoView({ behavior: 'smooth', block: 'start' });
						if (ctx && !ctx.value) setTimeout(function () { try { ctx.focus({ preventScroll: true }); } catch (e) { ctx.focus(); } }, 350);
					} else if (out) {
						event.preventDefault();
						var q = out.closest('.les-inline-question');
						var b = q ? q.closest('[data-les-problem]') : null;
						if (!b) return;
						var t = q.querySelector('textarea[name$="[context]"]');
						if (t) t.value = '';
						b.parentNode.insertBefore(q, b.nextSibling);
						if (!b.querySelector('[data-les-problem-items] > .les-inline-question')) b.remove(); else sync(b);
						renumber();
						q.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}
				});
				// Un problème sans énoncé ne serait pas reconnu à la réouverture : on le signale avant d'enregistrer.
				document.addEventListener('submit', function (event) {
					var empty = null;
					document.querySelectorAll('[data-les-problem]').forEach(function (b) {
						var c = b.querySelector('[data-les-problem-context]');
						if (!empty && b.querySelector('[data-les-problem-items] > .les-inline-question') && c && !c.value.trim()) empty = c;
					});
					if (empty && event.target.contains(empty)) {
						event.preventDefault();
						event.stopImmediatePropagation();
						window.alert('Écrivez l’énoncé du problème avant d’enregistrer : c’est lui qui relie les sous-questions entre elles.');
						empty.scrollIntoView({ behavior: 'smooth', block: 'center' });
						try { empty.focus({ preventScroll: true }); } catch (e) { empty.focus(); }
					}
				}, true);
				document.addEventListener('submit', function (event) {
					if (event.target.querySelector && event.target.querySelector('[data-les-problem]')) syncAll();
				}, true);
				// Une sous-question supprimée : on renumérote ; un problème vidé disparaît.
				document.addEventListener('click', function (event) {
					if (!event.target.closest('[data-logiexam-remove-question]')) return;
					setTimeout(function () {
						document.querySelectorAll('[data-les-problem]').forEach(function (b) {
							if (!b.querySelector('[data-les-problem-items] > .les-inline-question')) b.remove(); else sync(b);
						});
					}, 0);
				});
				if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', syncAll); else syncAll();
			}());
			/* 1.39.3 : dupliquer une question (copie juste en dessous, enregistrée comme une nouvelle question). */
			document.addEventListener('click', function (event) {
				var btn = event.target.closest('[data-logiexam-duplicate-question]');
				if (!btn) return;
				event.preventDefault();
				var item = btn.closest('.les-inline-question');
				if (!item) return;
				var idInput = null;
				item.querySelectorAll('input[type="hidden"]').forEach(function (i) { if (!idInput && /^(inline_questions\[[^\]]+\]|story_sections\[[^\]]+\]\[questions\]\[[^\]]+\])\[id\]$/.test(i.name)) idInput = i; });
				if (!idInput) return;
				var oldPrefix = idInput.name.slice(0, -4), newPrefix;
				var storyList = item.closest('[data-logiexam-story-questions]');
				if (storyList) {
					var qi = parseInt(storyList.dataset.nextQuestion || '0', 10);
					newPrefix = oldPrefix.replace(/\[questions\]\[[^\]]+\]$/, '[questions][' + qi + ']');
					storyList.dataset.nextQuestion = String(qi + 1);
				} else {
					var l = document.querySelector('[data-logiexam-inline-questions]');
					var ni = parseInt((l && l.dataset.nextIndex) || '0', 10);
					newPrefix = 'inline_questions[' + ni + ']';
					if (l) l.dataset.nextIndex = String(ni + 1);
				}
				var clone = item.cloneNode(true);
				var src = item.querySelectorAll('input, select, textarea');
				var dst = clone.querySelectorAll('input, select, textarea');
				dst.forEach(function (d, k) {
					var s = src[k];
					if (!s) return;
					if (d.type === 'file') { d.value = ''; return; }
					if (d.type === 'checkbox' || d.type === 'radio') { d.checked = s.checked; return; }
					if (d.tagName === 'SELECT') { for (var o = 0; o < d.options.length; o++) d.options[o].selected = !!(s.options[o] && s.options[o].selected); return; }
					d.value = s.value;
				});
				clone.querySelectorAll('*').forEach(function (el) {
					Array.prototype.slice.call(el.attributes).forEach(function (a) {
						if (a.value.indexOf(oldPrefix) !== -1) el.setAttribute(a.name, a.value.split(oldPrefix).join(newPrefix));
					});
				});
				clone.querySelectorAll('input[type="hidden"]').forEach(function (i) { if (i.name === newPrefix + '[id]') i.value = '0'; });
				item.parentNode.insertBefore(clone, item.nextSibling);
				// Numérotation « Question n » remise dans l'ordre.
				var reg = document.querySelector('[data-logiexam-inline-questions]');
				if (reg) {
					reg.querySelectorAll('.les-inline-question').forEach(function (q, k) {
						if (q.closest('[data-les-problem]')) return;
						var h = q.querySelector('.les-section-title h4');
						if (h) h.textContent = 'Question ' + (k + 1);
					});
				}
				document.querySelectorAll('[data-logiexam-story-questions]').forEach(function (sl) {
					sl.querySelectorAll('.les-inline-question').forEach(function (q, k) {
						var h = q.querySelector('.les-section-title h5');
						if (h) h.textContent = 'Question ' + (k + 1);
					});
				});
				var ctx = clone.closest('[data-les-problem]') ? clone.closest('[data-les-problem]').querySelector('[data-les-problem-context]') : null;
				if (ctx) ctx.dispatchEvent(new Event('input', { bubbles: true }));
				if (typeof logiexamRefreshAllQuestionTypes === 'function') logiexamRefreshAllQuestionTypes();
				clone.querySelectorAll('input, textarea').forEach(function (f) { if (f.type !== 'file' && f.type !== 'hidden') f.dispatchEvent(new Event('input', { bubbles: true })); });
				clone.classList.add('les-just-duplicated');
				setTimeout(function () { clone.classList.remove('les-just-duplicated'); }, 2500);
				clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
			document.addEventListener('click', function(event) {
				var button = event.target.closest('[data-logiexam-remove-question]');
				if (!button) return;
				event.preventDefault();
				var item = button.closest('.les-inline-question');
				if (item) item.remove();
			});
			document.addEventListener('click', function(event) {
				var button = event.target.closest('[data-logiexam-add-story-section]');
				if (!button) return;
				event.preventDefault();
				var list = document.querySelector('[data-logiexam-story-sections]');
				var template = document.querySelector('[data-logiexam-story-section-template]');
				if (!list || !template) return;
				var index = parseInt(list.dataset.nextSection || '0', 10);
				var html = template.innerHTML
					.replaceAll('__SECTION__', String(index))
					.replaceAll('__SECTION_NUMBER__', String(index + 1))
					.replaceAll('__QUESTION__', '0')
					.replaceAll('__QUESTION_NUMBER__', '1');
				list.insertAdjacentHTML('beforeend', html);
				list.dataset.nextSection = String(index + 1);
				logiexamRefreshAllQuestionTypes();
			});
			document.addEventListener('click', function(event) {
				var button = event.target.closest('[data-logiexam-remove-story-section]');
				if (!button) return;
				event.preventDefault();
				var section = button.closest('[data-logiexam-story-section]');
				if (section) section.remove();
			});
			document.addEventListener('click', function(event) {
				var button = event.target.closest('[data-logiexam-add-story-question]');
				if (!button) return;
				event.preventDefault();
				var section = button.closest('[data-logiexam-story-section]');
				var list = section ? section.querySelector('[data-logiexam-story-questions]') : null;
				var template = document.querySelector('[data-logiexam-story-question-template]');
				if (!section || !list || !template) return;
				var sectionMatch = (section.querySelector('textarea[name^="story_sections["]') || {}).name || '';
				var sectionIndex = (sectionMatch.match(/story_sections\[([^\]]+)\]/) || [])[1] || '0';
				var questionIndex = parseInt(list.dataset.nextQuestion || '0', 10);
				var lastCourse = '';
				var lastSelect = list.querySelector('.les-inline-question:last-of-type select[data-logiexam-question-course]');
				if (lastSelect) lastCourse = lastSelect.value;
				var html = template.innerHTML
					.replaceAll('__SECTION__', String(sectionIndex))
					.replaceAll('__QUESTION__', String(questionIndex))
					.replaceAll('__QUESTION_NUMBER__', String(questionIndex + 1));
				list.insertAdjacentHTML('beforeend', html);
				var newSelect = list.querySelector('.les-inline-question:last-of-type select[data-logiexam-question-course]');
				if (newSelect ? lastCourse : '') newSelect.value = lastCourse;
				list.dataset.nextQuestion = String(questionIndex + 1);
				logiexamRefreshAllQuestionTypes();
			});
			function logiexamToggleEvaluationMode() {
				var type = document.querySelector('[data-logiexam-evaluation-type]');
				var company = document.querySelector('[data-logiexam-company-panel]');
				var story = document.querySelector('[data-logiexam-story-settings]');
				var regularEditor = document.querySelector('[data-logiexam-regular-question-editor]');
				var storyEditor = document.querySelector('[data-logiexam-story-question-editor]');
				var value = type ? type.value : 'mes_company';
				if (company) company.hidden = value !== 'mes_company';
				if (story) story.hidden = value !== 'story';
				if (regularEditor) regularEditor.hidden = value === 'story';
				if (storyEditor) storyEditor.hidden = value !== 'story';
			}
			document.addEventListener('change', function(event) {
				if (event.target.matches('[data-logiexam-evaluation-type]')) logiexamToggleEvaluationMode();
			});
			function logiexamRefreshQuestionType(question) {
				var type = question.querySelector('[data-logiexam-question-type]');
				var value = type ? type.value : 'short_answer';
				question.querySelectorAll('[data-logiexam-field-types]').forEach(function(block) {
					var types = (block.dataset.logiexamFieldTypes || '').split(/\s+/);
					block.hidden = types.indexOf(value) === -1;
				});
			}
			function logiexamRefreshAllQuestionTypes() {
				document.querySelectorAll('.les-inline-question').forEach(logiexamRefreshQuestionType);
			}
			document.addEventListener('change', function(event) {
				if (!event.target.matches('[data-logiexam-question-type]')) return;
				var question = event.target.closest('.les-inline-question');
				if (question) logiexamRefreshQuestionType(question);
			});
			logiexamToggleEvaluationMode();
			logiexamRefreshAllQuestionTypes();
		</script>
		<?php
	}

	private static function render_inline_question_editor( array $questions, array $courses, int $selected_course, string $evaluation_type = 'mes_company' ): void {
		$is_story = 'story' === self::normalize_evaluation_type( $evaluation_type );
		?>
		<div class="les-panel" data-logiexam-regular-question-editor style="box-shadow:none;margin:14px 0;background:#f8fafc" <?php echo $is_story ? 'hidden' : ''; ?>>
			<div class="les-section-title">
				<h3><?php esc_html_e( 'Questions de l’évaluation', 'logieval-secure' ); ?></h3>
				<button type="button" class="button les-button-secondary" data-logiexam-add-question><?php esc_html_e( 'Ajouter une question', 'logieval-secure' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Créez directement les questions de cette évaluation. En mode histoire, le champ section permet de regrouper plusieurs questions sous le même bout d’histoire.', 'logieval-secure' ); ?></p>
			<button type="button" class="les-fab" data-logiexam-add-question aria-label="<?php esc_attr_e( 'Ajouter une question', 'logieval-secure' ); ?>"><span aria-hidden="true">+</span> <?php esc_html_e( 'Ajouter une question', 'logieval-secure' ); ?></button>
			<p class="les-problem-actions"><button type="button" class="button les-button-secondary" data-les-add-problem><?php esc_html_e( '🧮 Ajouter un problème (énoncé + sous-questions)', 'logieval-secure' ); ?></button>
				<span class="description"><?php esc_html_e( 'Un énoncé commun (les données), puis plusieurs sous-questions : chacune a son espace de développement, sa réponse et sa calculatrice.', 'logieval-secure' ); ?></span></p>
			<p class="les-group-bar" data-les-group-bar><button type="button" class="button les-button-secondary" data-les-group-selected disabled><?php esc_html_e( '🧮 Regrouper les questions sélectionnées en problème', 'logieval-secure' ); ?> <span data-les-group-count></span></button>
				<span class="description"><?php esc_html_e( 'Cochez « Sélectionner » sur au moins deux questions existantes : elles deviennent les sous-questions d’un problème, dans leur ordre actuel. Vous écrivez ensuite l’énoncé commun.', 'logieval-secure' ); ?></span></p>
			<div data-logiexam-inline-questions data-next-index="<?php echo esc_attr( count( $questions ) ); ?>">
				<?php
				// 1.38.5 : questions qui se suivent avec le même énoncé = un « problème » à sous-questions.
				$groups = self::group_problem_questions( $questions );
				foreach ( $groups as $group ) {
					if ( count( $group['items'] ) > 1 ) {
						self::render_problem_block_open( $group['context'] );
					}
					foreach ( $group['items'] as $index => $question ) {
						self::render_inline_question_fields( (int) $index, $question, $courses, $selected_course );
					}
					if ( count( $group['items'] ) > 1 ) {
						self::render_problem_block_close();
					}
				}
				?>
			</div>
		</div>
		<template data-les-problem-template><?php self::render_problem_block_open( '' ); self::render_problem_block_close(); ?></template>
		<template data-logiexam-question-template>
			<?php self::render_inline_question_fields( '__INDEX__', array( 'id' => 0, 'title' => '', 'sensei_course_id' => $selected_course, 'context' => '', 'prompt' => '', 'type' => 'short_answer', 'points' => 1, 'expected_answer' => '', 'options' => '[]' ), $courses, $selected_course, '__NUMBER__' ); ?>
		</template>
		<div data-logiexam-story-question-editor <?php echo $is_story ? '' : 'hidden'; ?>>
			<?php self::render_story_section_editor( $questions, $courses, $selected_course ); ?>
		</div>
		<?php
	}

	private static function render_story_section_editor( array $questions, array $courses, int $selected_course ): void {
		$sections = self::group_questions_by_story_section( $questions, $selected_course );
		?>
		<div class="les-panel les-story-builder" style="box-shadow:none;margin:14px 0;background:#f8fafc">
			<div class="les-section-title">
				<h3><?php esc_html_e( 'Sections d’histoire et questions', 'logieval-secure' ); ?></h3>
				<button type="button" class="button les-button-secondary" data-logiexam-add-story-section><?php esc_html_e( 'Ajouter une section d’histoire', 'logieval-secure' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Définissez d’abord les parties de l’histoire, puis ajoutez les questions dans chaque partie. Chaque section sera affichée comme une partie distincte de l’évaluation.', 'logieval-secure' ); ?></p>
			<div data-logiexam-story-sections data-next-section="<?php echo esc_attr( count( $sections ) ); ?>">
				<?php foreach ( $sections as $section_index => $section ) : ?>
					<?php self::render_story_section_fields( (int) $section_index, $section, $courses, $selected_course ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<template data-logiexam-story-section-template>
			<?php self::render_story_section_fields( '__SECTION__', array( 'context' => '', 'questions' => array( self::blank_question( $selected_course ) ) ), $courses, $selected_course, '__SECTION_NUMBER__' ); ?>
		</template>
		<template data-logiexam-story-question-template>
			<?php self::render_story_question_fields( '__SECTION__', '__QUESTION__', self::blank_question( $selected_course ), $courses, $selected_course, '__QUESTION_NUMBER__' ); ?>
		</template>
		<?php
	}

	private static function group_questions_by_story_section( array $questions, int $selected_course ): array {
		$sections = array();
		$last_context = null;
		foreach ( $questions as $question ) {
			$context = (string) ( $question['context'] ?? '' );
			if ( null === $last_context || $context !== $last_context ) {
				$sections[] = array( 'context' => $context, 'questions' => array() );
				$last_context = $context;
			}
			$sections[ count( $sections ) - 1 ]['questions'][] = $question;
		}
		if ( empty( $sections ) ) {
			$sections[] = array( 'context' => '', 'questions' => array( self::blank_question( $selected_course ) ) );
		}
		return array_values( $sections );
	}

	private static function blank_question( int $selected_course ): array {
		return array( 'id' => 0, 'title' => '', 'sensei_course_id' => $selected_course, 'context' => '', 'prompt' => '', 'type' => 'short_answer', 'points' => 1, 'expected_answer' => '', 'options' => '[]' );
	}

	private static function render_story_section_fields( $section_index, array $section, array $courses, int $selected_course, $display_number = null ): void {
		$number = null === $display_number ? ( (int) $section_index + 1 ) : $display_number;
		$questions = ! empty( $section['questions'] ) ? $section['questions'] : array( self::blank_question( $selected_course ) );
		?>
		<div class="les-story-section" data-logiexam-story-section>
			<div class="les-section-title">
				<h4><?php echo esc_html( sprintf( __( 'Section %s', 'logieval-secure' ), $number ) ); ?></h4>
				<div class="les-actions">
					<button type="button" class="button les-button-secondary" data-logiexam-add-story-question><?php esc_html_e( 'Ajouter une question dans cette section', 'logieval-secure' ); ?></button>
					<button type="button" class="button les-button-danger" data-logiexam-remove-story-section><?php esc_html_e( 'Supprimer la section', 'logieval-secure' ); ?></button>
				</div>
			</div>
			<label><?php esc_html_e( 'Bout d’histoire / contexte de la section', 'logieval-secure' ); ?><textarea class="widefat" name="story_sections[<?php echo esc_attr( $section_index ); ?>][context]" rows="5"><?php echo esc_textarea( $section['context'] ?? '' ); ?></textarea></label>
			<div data-logiexam-story-questions data-next-question="<?php echo esc_attr( count( $questions ) ); ?>">
				<?php foreach ( $questions as $question_index => $question ) : ?>
					<?php self::render_story_question_fields( $section_index, (int) $question_index, $question, $courses, $selected_course ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_story_question_fields( $section_index, $question_index, array $question, array $courses, int $selected_course, $display_number = null ): void {
		$name_prefix = 'story_sections[' . $section_index . '][questions][' . $question_index . ']';
		$number      = null === $display_number ? ( (int) $question_index + 1 ) : $display_number;
		$options     = self::textarea_options_value( $question['options'] ?? '' );
		$course_id   = ! empty( $question['sensei_course_id'] ) ? (int) $question['sensei_course_id'] : $selected_course;
		?>
		<div class="les-inline-question les-story-question">
			<div class="les-section-title">
				<h5><?php echo esc_html( sprintf( __( 'Question %s', 'logieval-secure' ), $number ) ); ?></h5>
				<span class="les-actions les-question-head-actions"><button type="button" class="button les-button-secondary" data-logiexam-duplicate-question title="<?php esc_attr_e( 'Ajoute une copie juste en dessous, avec les mêmes réglages', 'logieval-secure' ); ?>"><?php esc_html_e( 'Dupliquer', 'logieval-secure' ); ?></button>
				<button type="button" class="button les-button-danger" data-logiexam-remove-question><?php esc_html_e( 'Supprimer la question', 'logieval-secure' ); ?></button></span>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[id]" value="<?php echo esc_attr( $question['id'] ?? 0 ); ?>">
			<div class="les-form-row">
				<label><?php esc_html_e( 'Cours Sensei concerné', 'logieval-secure' ); ?>
					<select class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[sensei_course_id]" data-logiexam-question-course>
						<option value="0"><?php esc_html_e( 'Aucun cours', 'logieval-secure' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( (int) $course->ID, $course_id ); ?>><?php echo esc_html( get_the_title( $course ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Points', 'logieval-secure' ); ?><input type="number" step="0.25" min="0" name="<?php echo esc_attr( $name_prefix ); ?>[points]" value="<?php echo esc_attr( $question['evaluation_points'] ?? $question['points'] ?? 1 ); ?>"></label>
				<label><?php esc_html_e( 'Type', 'logieval-secure' ); ?>
					<?php self::render_question_type_select( $name_prefix . '[type]', (string) ( $question['type'] ?? 'short_answer' ) ); ?>
				</label>
			</div>
			<label><?php esc_html_e( 'Question posée', 'logieval-secure' ); ?><textarea class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[prompt]" rows="3"><?php echo esc_textarea( $question['prompt'] ?? '' ); ?></textarea></label>
			<?php self::render_adaptive_answer_fields( $name_prefix, $question, $options ); ?>
			<?php self::render_question_image_field( $name_prefix, $question ); ?>
			<?php self::render_criteria_editor( $name_prefix, $question ); ?>
		</div>
		<?php
	}

	private static function render_adaptive_answer_fields( string $name_prefix, array $question, string $options_text ): void {
		$type     = sanitize_key( $question['type'] ?? 'short_answer' );
		$expected = (string) ( $question['expected_answer'] ?? '' );
		$calculation_expected = self::calculation_answer_parts( $expected );
		$options  = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $options_text ) ) ) );
		while ( count( $options ) < 4 ) {
			$options[] = '';
		}
		?>
		<div class="les-type-fields" data-logiexam-type-fields>
			<div data-logiexam-field-types="short_answer long_answer">
				<label><?php esc_html_e( 'Réponse attendue', 'logieval-secure' ); ?><textarea class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[expected_answer]" rows="2"><?php echo esc_textarea( $expected ); ?></textarea></label>
			</div>
			<div data-logiexam-field-types="calculation">
				<label><?php esc_html_e( 'Développement attendu', 'logieval-secure' ); ?><textarea class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[calculation_development]" rows="3"><?php echo esc_textarea( $calculation_expected['development'] ); ?></textarea></label>
				<label><?php esc_html_e( 'Résultat final attendu', 'logieval-secure' ); ?><input type="text" class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[calculation_result]" value="<?php echo esc_attr( $calculation_expected['final_result'] ); ?>"></label>
				<label><?php esc_html_e( 'Résultat accepté', 'logieval-secure' ); ?>
					<select name="<?php echo esc_attr( $name_prefix ); ?>[calculation_precision]">
						<option value="" <?php selected( $calculation_expected['precision'], '' ); ?>><?php esc_html_e( 'Non précisé (le résultat tel que je l’ai écrit)', 'logieval-secure' ); ?></option>
						<?php foreach ( LogiEval_Secure_Question_Types::PRECISIONS as $precision ) : ?>
							<option value="<?php echo esc_attr( $precision ); ?>" <?php selected( $calculation_expected['precision'], $precision ); ?>><?php echo esc_html( LogiEval_Secure_Question_Types::precision_label( $precision ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<p class="description"><?php esc_html_e( 'Affiché bien visiblement à l’élève sur la question, et repris dans la correction et l’export. Le résultat est corrigé automatiquement selon ce choix (ex. arrondi à 2 décimales : 233,33 est juste, 233,3 ne l’est pas).', 'logieval-secure' ); ?></p>
				<p class="description"><?php esc_html_e( 'Une calculatrice sera affichée automatiquement à l’élève pour ce type de question.', 'logieval-secure' ); ?></p>
			</div>
			<div data-logiexam-field-types="true_false">
				<label><?php esc_html_e( 'Réponse correcte', 'logieval-secure' ); ?>
					<select class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[true_false_answer]">
						<option value=""><?php esc_html_e( 'Sélectionner', 'logieval-secure' ); ?></option>
						<option value="Vrai" <?php selected( $expected, 'Vrai' ); ?>><?php esc_html_e( 'Vrai', 'logieval-secure' ); ?></option>
						<option value="Faux" <?php selected( $expected, 'Faux' ); ?>><?php esc_html_e( 'Faux', 'logieval-secure' ); ?></option>
					</select>
				</label>
			</div>
			<div data-logiexam-field-types="qcm">
				<strong><?php esc_html_e( 'Propositions QCM', 'logieval-secure' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Saisissez les propositions puis cochez celle qui est correcte.', 'logieval-secure' ); ?></p>
				<div class="les-qcm-builder">
					<?php foreach ( $options as $option_index => $option ) : ?>
						<label class="les-qcm-option">
							<input type="radio" name="<?php echo esc_attr( $name_prefix ); ?>[qcm_correct_index]" value="<?php echo esc_attr( $option_index ); ?>" <?php checked( $expected !== '' && $option !== '' && $expected === $option ); ?>>
							<input type="text" class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[qcm_options][]" value="<?php echo esc_attr( $option ); ?>" placeholder="<?php echo esc_attr( sprintf( __( 'Proposition %d', 'logieval-secure' ), $option_index + 1 ) ); ?>">
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php LogiEval_Secure_Question_Types::render_editor_fields( $name_prefix, $question ); ?>
			<?php $calc_types = implode( ' ', array_diff( array_keys( self::question_types() ), array( 'calculation' ) ) ) . ' multiple_choice manual mes'; ?>
			<div data-logiexam-field-types="<?php echo esc_attr( $calc_types ); ?>" class="les-calc-option">
				<label class="les-check"><input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[show_calculator]" value="1" <?php checked( LogiEval_Secure_Question_Types::has_calculator( $question ) && 'calculation' !== $type ); ?>> <?php esc_html_e( '🧮 Afficher la calculatrice à l’élève pour cette question', 'logieval-secure' ); ?></label>
			</div>
		</div>
		<?php
	}

	private static function calculation_answer_parts( $value ): array {
		$parts = array(
			'development'  => '',
			'final_result' => '',
			'precision'    => '',
		);
		$decoded = json_decode( (string) $value, true );
		if ( is_array( $decoded ) ) {
			$parts['development']  = (string) ( $decoded['development'] ?? '' );
			$parts['final_result'] = (string) ( $decoded['final_result'] ?? '' );
			$parts['precision']    = LogiEval_Secure_Question_Types::calculation_precision( $decoded );
			return $parts;
		}
		$parts['final_result'] = (string) $value;
		return $parts;
	}

	/**
	 * 1.38.5 : regroupe les questions qui se suivent et partagent le même énoncé (champ « Détails nécessaires »).
	 * @return array<int, array{context: string, items: array<int, array>}>
	 */
	public static function group_problem_questions( array $questions ): array {
		$groups = array();
		foreach ( $questions as $index => $question ) {
			$ctx  = trim( (string) ( $question['context'] ?? '' ) );
			$last = count( $groups ) - 1;
			if ( '' !== $ctx && $last >= 0 && $groups[ $last ]['context'] === $ctx ) {
				$groups[ $last ]['items'][ $index ] = $question;
			} else {
				$groups[] = array( 'context' => $ctx, 'items' => array( $index => $question ) );
			}
		}
		return $groups;
	}

	private static function render_problem_block_open( string $context ): void {
		?>
		<section class="les-problem-block" data-les-problem>
			<div class="les-section-title">
				<h4><?php esc_html_e( '🧮 Problème', 'logieval-secure' ); ?> <span class="les-problem-range" data-les-problem-range></span></h4>
				<button type="button" class="button les-button-danger" data-les-remove-problem><?php esc_html_e( 'Supprimer le problème', 'logieval-secure' ); ?></button>
			</div>
			<label class="les-problem-context"><?php esc_html_e( 'Énoncé du problème (données communes à toutes les sous-questions)', 'logieval-secure' ); ?>
				<textarea class="widefat" rows="5" data-les-problem-context placeholder="<?php esc_attr_e( 'Ex. Une palette mesure 1,20 m × 0,80 m. On y empile des cartons de 40 × 30 × 25 cm sur 1,50 m de hauteur maximum…', 'logieval-secure' ); ?>"><?php echo esc_textarea( $context ); ?></textarea>
			</label>
			<p class="description"><?php esc_html_e( 'L’énoncé reste affiché au-dessus des sous-questions pour l’élève. Chaque sous-question de type « Calcul » a son espace de développement, sa réponse et sa calculatrice.', 'logieval-secure' ); ?></p>
			<div class="les-problem-items" data-les-problem-items>
		<?php
	}

	private static function render_problem_block_close(): void {
		?>
			</div>
			<p><button type="button" class="button les-button-secondary" data-les-add-subquestion><?php esc_html_e( '+ Ajouter une sous-question', 'logieval-secure' ); ?></button></p>
		</section>
		<?php
	}

	private static function render_inline_question_fields( $index, array $question, array $courses, int $selected_course, $display_number = null ): void {
		$name_prefix = 'inline_questions[' . $index . ']';
		$number      = null === $display_number ? ( (int) $index + 1 ) : $display_number;
		$options     = self::textarea_options_value( $question['options'] ?? '' );
		$course_id   = ! empty( $question['sensei_course_id'] ) ? (int) $question['sensei_course_id'] : $selected_course;
		?>
		<div class="les-inline-question">
			<div class="les-section-title">
				<h4><?php echo esc_html( sprintf( __( 'Question %s', 'logieval-secure' ), $number ) ); ?></h4>
				<span class="les-actions les-question-head-actions"><label class="les-group-pick"><input type="checkbox" data-les-group-pick> <?php esc_html_e( 'Sélectionner', 'logieval-secure' ); ?></label>
				<button type="button" class="button les-button-secondary les-unproblem" data-les-unproblem title="<?php esc_attr_e( 'Remet cette sous-question comme question indépendante, juste après le problème', 'logieval-secure' ); ?>"><?php esc_html_e( 'Sortir du problème', 'logieval-secure' ); ?></button><button type="button" class="button les-button-secondary" data-logiexam-duplicate-question title="<?php esc_attr_e( 'Ajoute une copie juste en dessous, avec les mêmes réglages', 'logieval-secure' ); ?>"><?php esc_html_e( 'Dupliquer', 'logieval-secure' ); ?></button>
				<button type="button" class="button les-button-danger" data-logiexam-remove-question><?php esc_html_e( 'Supprimer la question', 'logieval-secure' ); ?></button></span>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[id]" value="<?php echo esc_attr( $question['id'] ?? 0 ); ?>">
			<div class="les-form-row">
				<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[title]" value="<?php echo esc_attr( $question['title'] ?? '' ); ?>">
				<label><?php esc_html_e( 'Cours Sensei concerné', 'logieval-secure' ); ?>
					<select class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[sensei_course_id]" data-logiexam-question-course>
						<option value="0"><?php esc_html_e( 'Aucun cours', 'logieval-secure' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( (int) $course->ID, $course_id ); ?>><?php echo esc_html( get_the_title( $course ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Points', 'logieval-secure' ); ?><input type="number" step="0.25" min="0" name="<?php echo esc_attr( $name_prefix ); ?>[points]" value="<?php echo esc_attr( $question['evaluation_points'] ?? $question['points'] ?? 1 ); ?>"></label>
				<label><?php esc_html_e( 'Type', 'logieval-secure' ); ?>
					<?php self::render_question_type_select( $name_prefix . '[type]', (string) ( $question['type'] ?? 'short_answer' ) ); ?>
				</label>
			</div>
			<label data-les-context-field><?php esc_html_e( 'Détails nécessaires / section d’histoire', 'logieval-secure' ); ?><textarea class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[context]" rows="3"><?php echo esc_textarea( $question['context'] ?? '' ); ?></textarea></label>
			<label><?php esc_html_e( 'Question posée', 'logieval-secure' ); ?><textarea class="widefat" name="<?php echo esc_attr( $name_prefix ); ?>[prompt]" rows="3"><?php echo esc_textarea( $question['prompt'] ?? '' ); ?></textarea></label>
			<?php self::render_adaptive_answer_fields( $name_prefix, $question, $options ); ?>
			<?php self::render_question_image_field( $name_prefix, $question ); ?>
			<?php self::render_criteria_editor( $name_prefix, $question ); ?>
		</div>
		<?php
	}

	private static function render_question_form( array $courses, ?array $question = null ): void {
		$is_edit   = ! empty( $question['id'] );
		$question  = (array) $question;
		$options   = self::textarea_options_value( $question['options'] ?? '' );
		$course_id = absint( $question['sensei_course_id'] ?? 0 );
		$prefix    = 'bank';
		?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="logieval_secure_save_question">
			<input type="hidden" name="question_id" value="<?php echo esc_attr( $question['id'] ?? 0 ); ?>">
			<?php self::frontend_redirect_field( array( 'logiexam_view' => 'questions', 'question_id' => null ) ); ?>
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<div class="les-inline-question les-bank-question">
				<div class="les-form-row">
					<label><?php esc_html_e( 'Cours Sensei concerné', 'logieval-secure' ); ?>
						<select class="widefat" name="bank[sensei_course_id]">
							<option value="0"><?php esc_html_e( 'Aucun cours', 'logieval-secure' ); ?></option>
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( (int) $course->ID, $course_id ); ?>><?php echo esc_html( get_the_title( $course ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><?php esc_html_e( 'Points', 'logieval-secure' ); ?><input type="number" step="0.25" min="0" name="bank[points]" value="<?php echo esc_attr( $question['points'] ?? 1 ); ?>"></label>
					<label><?php esc_html_e( 'Type', 'logieval-secure' ); ?>
						<?php self::render_question_type_select( 'bank[type]', (string) ( $question['type'] ?? 'short_answer' ) ); ?>
					</label>
				</div>
				<label><?php esc_html_e( 'Détails nécessaires / section d’histoire', 'logieval-secure' ); ?><textarea class="widefat" name="bank[context]" rows="3"><?php echo esc_textarea( $question['context'] ?? '' ); ?></textarea></label>
				<label><?php esc_html_e( 'Question posée', 'logieval-secure' ); ?><textarea class="widefat" name="bank[prompt]" rows="3" required><?php echo esc_textarea( $question['prompt'] ?? '' ); ?></textarea></label>
				<?php self::render_adaptive_answer_fields( $prefix, $question, $options ); ?>
				<?php self::render_question_image_field( $prefix, $question ); ?>
				<?php self::render_criteria_editor( $prefix, $question ); ?>
				<div class="les-form-row">
					<label><?php esc_html_e( 'Difficulté', 'logieval-secure' ); ?>
						<select name="bank[difficulty]">
							<option value="" <?php selected( $question['difficulty'] ?? '', '' ); ?>>-</option>
							<option value="facile" <?php selected( $question['difficulty'] ?? '', 'facile' ); ?>><?php esc_html_e( 'Facile', 'logieval-secure' ); ?></option>
							<option value="moyen" <?php selected( $question['difficulty'] ?? '', 'moyen' ); ?>><?php esc_html_e( 'Moyen', 'logieval-secure' ); ?></option>
							<option value="difficile" <?php selected( $question['difficulty'] ?? '', 'difficile' ); ?>><?php esc_html_e( 'Difficile', 'logieval-secure' ); ?></option>
						</select>
					</label>
					<label><?php esc_html_e( 'Catégorie / tags', 'logieval-secure' ); ?><input class="widefat" name="bank[tags]" value="<?php echo esc_attr( $question['tags'] ?? '' ); ?>"></label>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Les questions créées ici sont sauvegardées dans votre banque LogiExam et pourront être réutilisées dans de futurs examens.', 'logieval-secure' ); ?></p>
			<?php if ( $is_edit ) : ?>
				<p><a class="button les-button-secondary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=logiexam-questions' ) : self::frontend_url( array( 'logiexam_view' => 'questions', 'question_id' => null ) ) ); ?>"><?php esc_html_e( 'Annuler la modification', 'logieval-secure' ); ?></a></p>
			<?php endif; ?>
			<button type="submit" class="button <?php echo $is_edit ? 'button-primary' : 'les-button-secondary'; ?>"><?php echo esc_html( $is_edit ? __( 'Enregistrer les modifications', 'logieval-secure' ) : __( 'Ajouter à la banque', 'logieval-secure' ) ); ?></button>
		</form>
		<?php
	}
	private static function render_question_bank( array $questions, int $course_id = 0 ): void {
		?>
		<h3><?php esc_html_e( 'Questions LogiExam', 'logieval-secure' ); ?></h3>
		<?php if ( $course_id ) : ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Filtre actif : %s', 'logieval-secure' ), get_the_title( $course_id ) ?: __( 'Cours sélectionné', 'logieval-secure' ) ) ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Toutes les questions de la banque sont affichées. Sélectionnez un cours Sensei pour limiter la liste.', 'logieval-secure' ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer les questions sélectionnées ? Cette action retirera aussi ces questions des évaluations qui les utilisent.', 'logieval-secure' ) ); ?>');">
			<input type="hidden" name="action" value="logieval_secure_delete_questions">
			<?php self::frontend_redirect_field( array( 'logiexam_view' => 'questions', 'question_id' => null ) ); ?>
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<table class="widefat striped"><thead><tr><th><span class="screen-reader-text"><?php esc_html_e( 'Sélection', 'logieval-secure' ); ?></span></th><th>Titre</th><th>Cours Sensei</th><th>Type</th><th>Origine</th><th>Points</th><th>Utilisations</th><th>Actions</th></tr></thead><tbody>
			<?php foreach ( $questions as $question ) : ?>
				<tr>
					<td><input type="checkbox" name="question_ids[]" value="<?php echo esc_attr( $question['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Sélectionner la question %s', 'logieval-secure' ), $question['title'] ) ); ?>"></td>
					<td><?php echo esc_html( $question['title'] ); ?></td>
					<td><?php echo esc_html( ! empty( $question['sensei_course_id'] ) ? get_the_title( (int) $question['sensei_course_id'] ) : '-' ); ?></td>
					<td><?php echo esc_html( $question['type'] ); ?></td>
					<td><?php echo esc_html( $question['origin'] ); ?></td>
					<td><?php echo esc_html( $question['points'] ); ?></td>
					<td><?php echo esc_html( $question['usage_count'] ); ?></td>
					<td>
						<a class="button les-button-secondary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=logiexam-questions&question_id=' . absint( $question['id'] ) ) : self::frontend_url( array( 'logiexam_view' => 'questions', 'question_id' => absint( $question['id'] ) ) ) ); ?>"><?php esc_html_e( 'Modifier', 'logieval-secure' ); ?></a>
						<button type="submit" class="button les-button-danger" name="single_question_id" value="<?php echo esc_attr( $question['id'] ); ?>"><?php esc_html_e( 'Supprimer', 'logieval-secure' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<p class="les-actions" style="justify-content:flex-start">
				<button type="submit" class="button les-button-danger"><?php esc_html_e( 'Supprimer les questions sélectionnées', 'logieval-secure' ); ?></button>
			</p>
		</form>
		<?php
	}

	private static function filter_question_bank_by_course( array $questions, int $course_id ): array {
		if ( ! $course_id ) {
			return $questions;
		}

		return array_values(
			array_filter(
				$questions,
				static function( $question ) use ( $course_id ) {
					return (int) ( $question['sensei_course_id'] ?? 0 ) === $course_id;
				}
			)
		);
	}

	private static function render_sensei_question_import_table( array $sensei_questions ): void {
		?>
		<h3><?php esc_html_e( 'Questions Sensei disponibles', 'logieval-secure' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Copiez les questions Sensei utiles dans la banque LogiExam, puis sélectionnez-les dans l’évaluation.', 'logieval-secure' ); ?></p>
		<table class="widefat striped"><thead><tr><th>Question</th><th>Type</th><th>Action</th></tr></thead><tbody>
		<?php if ( empty( $sensei_questions ) ) : ?>
			<tr><td colspan="3"><?php esc_html_e( 'Aucune question Sensei trouvée pour ce filtre.', 'logieval-secure' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $sensei_questions as $question ) : ?>
			<tr>
				<td><?php echo esc_html( $question['title'] ); ?></td><td><?php echo esc_html( $question['type'] ); ?></td>
					<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( self::NONCE_ACTION ); ?><?php self::frontend_redirect_field( array( 'logiexam_view' => 'questions' ) ); ?><input type="hidden" name="action" value="logieval_secure_import_sensei_question"><input type="hidden" name="sensei_question_id" value="<?php echo esc_attr( $question['id'] ); ?>"><button type="submit" class="button les-button-secondary"><?php esc_html_e( 'Copier', 'logieval-secure' ); ?></button></form></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}

	private static function render_sensei_question_selection( array $sensei_questions ): void {
		?>
		<div class="les-panel" style="box-shadow:none;margin:12px 0;background:#f8fafc">
			<strong><?php esc_html_e( 'Questions Sensei liées au cours', 'logieval-secure' ); ?></strong>
			<p class="description"><?php esc_html_e( 'Cochez les questions Sensei à ajouter à cette évaluation. Elles seront copiées dans la banque LogiExam sans modifier les questions originales.', 'logieval-secure' ); ?></p>
			<?php if ( empty( $sensei_questions ) ) : ?>
				<p class="les-message"><?php esc_html_e( 'Aucune question Sensei disponible avec le cours et la catégorie sélectionnés.', 'logieval-secure' ); ?></p>
			<?php else : ?>
				<div class="les-checklist-panel">
					<?php foreach ( $sensei_questions as $question ) : ?>
						<label class="les-check">
							<input type="checkbox" name="sensei_question_ids[]" value="<?php echo esc_attr( $question['id'] ); ?>">
							<span><?php echo esc_html( $question['title'] . ' - ' . $question['type'] . ' - ' . max( 0.25, (float) $question['points'] ) . ' pts' ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_sensei_question_filters( array $courses, array $modules, array $categories, int $course_id, string $module_key, string $category_slug ): void {
		?>
		<form method="get" class="les-panel" style="box-shadow:none;margin:0 0 16px">
			<?php if ( is_admin() ) : ?>
				<input type="hidden" name="page" value="logiexam-questions">
			<?php else : ?>
				<input type="hidden" name="logiexam_view" value="questions">
			<?php endif; ?>
			<div class="les-form-row">
				<label><?php esc_html_e( 'Cours Sensei', 'logieval-secure' ); ?>
					<select name="sensei_course_id">
						<option value="0"><?php esc_html_e( 'Tous les cours', 'logieval-secure' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, (int) $course->ID ); ?>><?php echo esc_html( get_the_title( $course ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Module de questions', 'logieval-secure' ); ?>
					<select name="question_module">
						<option value=""><?php esc_html_e( 'Tous les modules', 'logieval-secure' ); ?></option>
						<?php foreach ( $modules as $module ) : ?>
							<option value="<?php echo esc_attr( $module['key'] ); ?>" <?php selected( $module_key, (string) $module['key'] ); ?>><?php echo esc_html( $module['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Catégorie de questions', 'logieval-secure' ); ?>
					<select name="question_category">
						<option value=""><?php esc_html_e( 'Toutes les catégories', 'logieval-secure' ); ?></option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $category_slug, (string) $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
				<button type="submit" class="button les-button-secondary"><?php esc_html_e( 'Filtrer les questions', 'logieval-secure' ); ?></button>
		</form>
		<?php
	}

	private static function render_question_category_hint( array $categories, int $course_id, string $category_slug ): void {
		if ( ! $course_id ) {
			echo '<p class="description">' . esc_html__( 'Sélectionnez un cours Sensei pour voir les catégories de questions liées à ce cours.', 'logieval-secure' ) . '</p>';
			return;
		}
		$module_key = sanitize_key( wp_unslash( $_GET['question_module'] ?? '' ) );
		$modules    = LogiEval_Secure_Sensei::get_question_modules_for_course( $course_id );
		$base_args  = is_admin() ? array( 'page' => 'logiexam-create' ) : array( 'logiexam_view' => 'create' );
		?>
		<div class="les-panel" style="box-shadow:none;margin:8px 0;background:#f8fafc">
			<strong><?php esc_html_e( 'Modules et catégories de questions du cours', 'logieval-secure' ); ?></strong>
			<p class="description"><?php esc_html_e( 'Les modules proviennent du tableau de paramétrage Logiprof Révision Modalités. Choisissez un module ou une catégorie, puis cochez les questions Sensei à ajouter.', 'logieval-secure' ); ?></p>
			<?php if ( ! empty( $modules ) ) : ?>
				<p>
					<a class="button <?php echo '' === $module_key ? '' : 'les-button-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'sensei_course_id' => $course_id, 'question_module' => '', 'question_category' => '' ) ) ) ); ?>"><?php esc_html_e( 'Tous les modules', 'logieval-secure' ); ?></a>
					<?php foreach ( $modules as $module ) : ?>
						<a class="button <?php echo $module_key === $module['key'] ? '' : 'les-button-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'sensei_course_id' => $course_id, 'question_module' => $module['key'], 'question_category' => '' ) ) ) ); ?>"><?php echo esc_html( $module['label'] ); ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
			<p>
				<a class="button les-button-secondary" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'sensei_course_id' => $course_id, 'question_module' => $module_key, 'question_category' => '' ) ) ) ); ?>"><?php esc_html_e( 'Toutes les catégories', 'logieval-secure' ); ?></a>
				<?php foreach ( $categories as $category ) : ?>
					<a class="button <?php echo $category_slug === $category->slug ? '' : 'les-button-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'sensei_course_id' => $course_id, 'question_module' => $module_key, 'question_category' => $category->slug ) ) ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}

		private static function render_deleted_evaluations_table( array $evaluations ): void {
		$statuses = array( 'draft' => __( 'Brouillon', 'logieval-secure' ), 'published' => __( 'Publiée', 'logieval-secure' ), 'closed' => __( 'Fermée', 'logieval-secure' ), 'archived' => __( 'Archivée', 'logieval-secure' ) );
		$names    = array();
		foreach ( LogiEval_Secure_Sensei::get_groups() as $group ) {
			$names[ (int) $group->term_id ] = (string) $group->name;
		}
		?>
		<div class="les-table-scroll">
		<table class="widefat striped les-evaluations-table les-trash-table"><thead><tr><th><?php esc_html_e( 'Évaluation', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Classe(s)', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Copies', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Supprimée le', 'logieval-secure' ); ?></th><th><?php esc_html_e( 'Actions', 'logieval-secure' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $evaluations ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'La corbeille est vide.', 'logieval-secure' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $evaluations as $evaluation ) :
			$deleted_by = ! empty( $evaluation['deleted_by'] ) ? get_userdata( (int) $evaluation['deleted_by'] ) : null;
			$classes    = array();
			foreach ( self::decode_ids( $evaluation['sensei_group_ids'] ?? '' ) as $gid ) {
				$classes[] = $names[ (int) $gid ] ?? ( get_the_title( (int) $gid ) ?: '#' . (int) $gid );
			}
			$copies = count( self::get_attempts( (int) $evaluation['id'] ) );
			$old    = (string) ( $evaluation['archived_status'] ?? '' );
			?>
			<tr>
				<td><strong><?php echo esc_html( $evaluation['title'] ); ?></strong><br><small><?php echo esc_html( sprintf( __( 'Statut avant suppression : %s', 'logieval-secure' ), $statuses[ $old ] ?? ( '' !== $old ? $old : __( 'non renseigné', 'logieval-secure' ) ) ) ); ?></small></td>
				<td><?php echo esc_html( $classes ? implode( ', ', $classes ) : __( 'Non affectée', 'logieval-secure' ) ); ?></td>
				<td><?php echo esc_html( (string) $copies ); ?></td>
				<td><?php echo esc_html( ! empty( $evaluation['deleted_at'] ) ? date_i18n( 'j F Y · H:i', strtotime( (string) $evaluation['deleted_at'] ) ) : __( 'Non renseigné', 'logieval-secure' ) ); ?><?php if ( $deleted_by ) : ?><br><small><?php echo esc_html( sprintf( __( 'par %s', 'logieval-secure' ), $deleted_by->display_name ) ); ?></small><?php endif; ?></td>
				<td>
					<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( self::NONCE_ACTION ); ?><?php self::frontend_redirect_field( array( 'logiexam_view' => 'evaluations' ) ); ?><input type="hidden" name="action" value="logieval_secure_restore_evaluation"><input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation['id'] ); ?>"><button type="submit" class="button button-primary"><?php esc_html_e( 'Restaurer', 'logieval-secure' ); ?></button></form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		</div>
		<?php
	}

	private static function render_teacher_preview_workspace( ?array $evaluation ): void {
		if ( ! $evaluation ) {
			?>
			<section class="les-panel">
				<h2><?php esc_html_e( 'Aperçu de l’évaluation', 'logieval-secure' ); ?></h2>
				<div class="les-empty-state les-warning">
					<h2><?php esc_html_e( 'Évaluation introuvable', 'logieval-secure' ); ?></h2>
					<p><?php esc_html_e( 'Sélectionnez une évaluation existante pour la visualiser ou la tester.', 'logieval-secure' ); ?></p>
				</div>
			</section>
			<?php
			return;
		}

		$questions = self::get_questions_for_evaluation( (int) $evaluation['id'] );
		$course    = self::evaluation_module_name( $evaluation );
		$back_url  = is_admin() ? admin_url( 'admin.php?page=logiexam-evaluations' ) : self::frontend_url( array( 'logiexam_view' => 'evaluations', 'evaluation_id' => null ) );
		$edit_url  = is_admin() ? admin_url( 'admin.php?page=logiexam-create&evaluation_id=' . absint( $evaluation['id'] ) ) : self::frontend_url( array( 'logiexam_view' => 'create', 'evaluation_id' => absint( $evaluation['id'] ) ) );
		?>
		<section class="les-panel les-preview" data-les-preview>
			<div class="les-preview-toolbar">
				<div>
					<span class="les-kicker"><?php esc_html_e( 'Aperçu en mode étudiant', 'logieval-secure' ); ?></span>
					<h2><?php echo esc_html( $evaluation['title'] ); ?></h2>
					<p><?php esc_html_e( 'Vous pouvez tester les champs comme un élève. Aucune réponse n’est enregistrée, aucun code n’est consommé et aucune copie n’est créée.', 'logieval-secure' ); ?></p>
				</div>
				<div class="les-actions">
					<a class="button les-button-secondary" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Retour aux évaluations', 'logieval-secure' ); ?></a>
					<?php if ( self::can_manage_evaluation( $evaluation ) ) : ?>
						<a class="button" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier cette évaluation', 'logieval-secure' ); ?></a>
					<?php endif; ?>
					<?php if ( ! empty( $evaluation['is_shared'] ) && (int) $evaluation['created_by'] !== get_current_user_id() ) : ?>
						<form class="les-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( self::NONCE_ACTION ); ?><?php self::frontend_redirect_field( array( 'logiexam_view' => 'create' ) ); ?><input type="hidden" name="action" value="logieval_secure_copy_shared"><input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation['id'] ); ?>"><button type="submit" class="button"><?php esc_html_e( 'Dupliquer dans mes évaluations', 'logieval-secure' ); ?></button></form>
					<?php endif; ?>
				</div>
			</div>
			<div class="les-preview-meta">
				<div><strong><?php esc_html_e( 'Module', 'logieval-secure' ); ?></strong><span><?php echo esc_html( $course ); ?></span></div>
				<div><strong><?php esc_html_e( 'Questions', 'logieval-secure' ); ?></strong><span><?php echo esc_html( count( $questions ) ); ?></span></div>
				<div><strong><?php esc_html_e( 'Durée', 'logieval-secure' ); ?></strong><span><?php echo esc_html( (int) $evaluation['duration_minutes'] ); ?> min</span></div>
				<div><strong><?php esc_html_e( 'Points', 'logieval-secure' ); ?></strong><span><?php echo esc_html( $evaluation['total_points'] ); ?></span></div>
			</div>
			<div class="les-exam les-preview-exam">
				<header class="les-exam-header">
					<div>
						<span class="les-kicker"><?php esc_html_e( 'Aperçu élève', 'logieval-secure' ); ?></span>
						<h2><?php echo esc_html( $evaluation['title'] ); ?></h2>
						<?php if ( ! empty( $evaluation['instructions'] ) ) : ?>
							<div class="les-prompt"><?php echo wp_kses_post( wpautop( $evaluation['instructions'] ) ); ?></div>
						<?php elseif ( ! empty( $evaluation['description'] ) ) : ?>
							<div class="les-prompt"><?php echo wp_kses_post( wpautop( $evaluation['description'] ) ); ?></div>
						<?php endif; ?>
					</div>
					<div class="les-exam-meta">
						<div class="les-timer"><?php echo esc_html( self::preview_duration_label( (int) $evaluation['duration_minutes'] ) ); ?></div>
						<span><?php printf( esc_html( _n( '%d question', '%d questions', count( $questions ), 'logieval-secure' ) ), count( $questions ) ); ?></span>
					</div>
				</header>
			<?php self::render_preview_company_context( $evaluation ); ?>
			<div class="les-exam-intro" data-les-preview-intro>
				<?php self::render_preview_intro_blocks( $evaluation ); ?>
				<div class="les-empty-state">
					<h2><?php esc_html_e( 'Prêt à afficher les questions ?', 'logieval-secure' ); ?></h2>
					<p><?php esc_html_e( 'Dans le parcours élève, les questions ne sont affichées qu’après validation de cet écran d’introduction.', 'logieval-secure' ); ?></p>
					<div class="les-actions">
							<button type="button" data-les-preview-begin><?php echo 'story' === self::normalize_evaluation_type( $evaluation['evaluation_type'] ?? 'mes_company' ) ? esc_html__( 'Continuer vers les questions', 'logieval-secure' ) : esc_html__( 'Commencer l’évaluation', 'logieval-secure' ); ?></button>
					</div>
				</div>
				</div>
				<form class="les-preview-form" data-les-preview-questions hidden>
					<?php if ( empty( $questions ) ) : ?>
						<div class="les-empty-state les-warning">
							<h2><?php esc_html_e( 'Aucune question', 'logieval-secure' ); ?></h2>
							<p><?php esc_html_e( 'Ajoutez des questions à cette évaluation pour pouvoir la tester.', 'logieval-secure' ); ?></p>
						</div>
					<?php else : ?>
						<?php if ( 'story' === self::normalize_evaluation_type( $evaluation['evaluation_type'] ?? 'mes_company' ) ) : ?>
							<?php self::render_preview_story_sections( $questions ); ?>
						<?php else : ?>
							<?php foreach ( $questions as $index => $question ) : ?>
								<?php self::render_preview_question( $question, $index, $questions[ $index - 1 ]['context'] ?? '' ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endif; ?>
					<div class="les-actions">
						<button type="reset" class="les-button-secondary"><?php esc_html_e( 'Réinitialiser le test', 'logieval-secure' ); ?></button>
						<button type="button" onclick="alert('<?php echo esc_js( __( 'Aperçu enseignant : aucune soumission réelle n’est envoyée.', 'logieval-secure' ) ); ?>')"><?php esc_html_e( 'Simuler la soumission', 'logieval-secure' ); ?></button>
					</div>
				</form>
			</div>
		</section>
		<script>
			document.querySelectorAll('[data-les-preview]').forEach(function (preview) {
				var button = preview.querySelector('[data-les-preview-begin]');
				if (!button) return;
				button.addEventListener('click', function () {
					var intro = preview.querySelector('[data-les-preview-intro]');
					var questions = preview.querySelector('[data-les-preview-questions]');
					if (intro) intro.hidden = true;
					if (questions) questions.hidden = false;
					var firstStory = preview.querySelector('[data-preview-story-section]');
					if (firstStory) firstStory.hidden = false;
				});
				preview.addEventListener('click', function (event) {
					var start = event.target.closest('[data-preview-story-start]');
					if (start) {
						event.preventDefault();
						var section = start.closest('[data-preview-story-section]');
						if (!section) return;
						var intro = section.querySelector('[data-preview-story-intro]');
						var questions = section.querySelector('[data-preview-story-questions]');
						if (intro) intro.hidden = true;
						if (questions) questions.hidden = false;
						return;
					}
					var next = event.target.closest('[data-preview-story-next]');
					if (!next) return;
					event.preventDefault();
					var section = next.closest('[data-preview-story-section]');
					var following = section ? section.nextElementSibling : null;
					if (section) section.hidden = true;
					if (following ? following.matches('[data-preview-story-section]') : false) {
						following.hidden = false;
					} else {
						alert('<?php echo esc_js( __( 'Toutes les sections sont terminées. Vous pouvez simuler la soumission.', 'logieval-secure' ) ); ?>');
					}
				});
			});
		</script>
		<?php
	}

	private static function render_preview_company_context( array $evaluation ): void {
		if ( 'mes_company' !== self::normalize_evaluation_type( $evaluation['evaluation_type'] ?? 'mes_company' ) ) {
			return;
		}
		if ( empty( $evaluation['company_name'] ) && empty( $evaluation['company_context'] ) && empty( $evaluation['apprentice_role'] ) && empty( $evaluation['company_logo_id'] ) ) {
			return;
		}
		$logo_url = ! empty( $evaluation['company_logo_id'] ) ? self::private_image_url( (int) $evaluation['company_logo_id'], 'medium' ) : '';
		?>
		<div class="les-company-context">
			<?php if ( $logo_url ) : ?>
				<div class="les-company-logo"><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $evaluation['company_name'] ?? '' ); ?>"></div>
			<?php endif; ?>
			<div class="les-company-body">
				<?php if ( ! empty( $evaluation['company_name'] ) ) : ?>
					<h3><?php echo esc_html( $evaluation['company_name'] ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $evaluation['company_context'] ) ) : ?>
					<?php echo wp_kses_post( wpautop( $evaluation['company_context'] ) ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $evaluation['apprentice_role'] ) ) : ?>
					<div class="les-apprentice-role">
						<strong><?php esc_html_e( 'Votre rôle / mission', 'logieval-secure' ); ?></strong>
						<?php echo wp_kses_post( wpautop( $evaluation['apprentice_role'] ) ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_preview_intro_blocks( array $evaluation ): void {
		$notice = ! empty( $evaluation['security_notice'] ) ? (string) $evaluation['security_notice'] : self::default_security_notice();
		$type   = self::normalize_evaluation_type( $evaluation['evaluation_type'] ?? 'mes_company' );
		?>
		<div class="les-security-notice">
			<strong><?php esc_html_e( 'Rappel important', 'logieval-secure' ); ?></strong>
			<?php echo wp_kses_post( wpautop( $notice ) ); ?>
		</div>
		<?php if ( 'story' === $type && ! empty( $evaluation['story_intro'] ) ) : ?>
			<div class="les-story-intro">
				<span class="les-kicker"><?php esc_html_e( 'Introduction de l’histoire', 'logieval-secure' ); ?></span>
				<?php echo wp_kses_post( wpautop( $evaluation['story_intro'] ) ); ?>
			</div>
		<?php endif; ?>
		<?php
	}

	private static function render_preview_story_sections( array $questions ): void {
		$sections = self::group_questions_by_story_section( $questions, 0 );
		$global_index = 0;
		foreach ( $sections as $section_index => $section ) :
			?>
			<section class="les-story-step" data-preview-story-section hidden>
				<div class="les-story-section-intro" data-preview-story-intro>
					<span class="les-kicker"><?php echo esc_html( sprintf( __( 'Partie %1$d/%2$d', 'logieval-secure' ), $section_index + 1, count( $sections ) ) ); ?></span>
					<div class="les-story-intro-text"><?php echo wp_kses_post( wpautop( $section['context'] ?? '' ) ); ?></div>
					<div class="les-actions"><button type="button" data-preview-story-start><?php esc_html_e( 'Accéder aux questions', 'logieval-secure' ); ?></button></div>
				</div>
				<div data-preview-story-questions hidden>
					<?php foreach ( (array) ( $section['questions'] ?? array() ) as $question ) : ?>
						<?php self::render_preview_question( $question, $global_index, (string) ( $question['context'] ?? '' ) ); ?>
						<?php $global_index++; ?>
					<?php endforeach; ?>
					<div class="les-actions">
						<button type="button" data-preview-story-next><?php echo $section_index + 1 >= count( $sections ) ? esc_html__( 'Terminer les questions', 'logieval-secure' ) : esc_html__( 'Passer à la section suivante', 'logieval-secure' ); ?></button>
					</div>
				</div>
			</section>
			<?php
		endforeach;
	}

	private static function render_preview_question( array $question, int $index, string $previous_context = '' ): void {
		$points = (float) ( $question['evaluation_points'] ?: $question['points'] );
		$context = trim( (string) ( $question['context'] ?? '' ) );
		?>
			<fieldset class="les-question">
				<legend>
					<span class="les-question-title"><?php echo esc_html( ( $index + 1 ) . '. ' . wp_strip_all_tags( (string) $question['prompt'] ) ); ?></span>
					<span class="les-question-points"><?php echo esc_html( $points ); ?> pts</span>
				</legend>
				<?php if ( '' !== $context && $context !== trim( $previous_context ) ) : ?>
					<div class="les-question-context"><?php echo wp_kses_post( wpautop( $context ) ); ?></div>
				<?php endif; ?>
				<?php self::render_question_image( absint( $question['media_id'] ?? 0 ) ); ?>
				<?php self::render_preview_answer_input( $question ); ?>
			<details class="les-answer-grade">
				<summary><?php esc_html_e( 'Référence enseignant', 'logieval-secure' ); ?></summary>
				<p><strong><?php esc_html_e( 'Type', 'logieval-secure' ); ?> :</strong> <?php echo esc_html( self::question_type_label( (string) $question['type'] ) ); ?></p>
				<p><strong><?php esc_html_e( 'Réponse attendue', 'logieval-secure' ); ?> :</strong></p>
				<pre><?php echo esc_html( (string) ( $question['expected_answer'] ?? '' ) ); ?></pre>
			</details>
		</fieldset>
		<?php
	}

	private static function render_preview_answer_input( array $question ): void {
		$type    = sanitize_key( (string) $question['type'] );
		$name    = 'preview_q_' . absint( $question['id'] );
		$options = json_decode( (string) ( $question['options'] ?? '' ), true );
		$options = is_array( $options ) ? array_values( array_filter( array_map( 'strval', $options ) ) ) : array();

		if ( 'true_false' === $type ) {
			$options = array( __( 'Vrai', 'logieval-secure' ), __( 'Faux', 'logieval-secure' ) );
		}

		if ( in_array( $type, array( 'qcm', 'multiple_choice', 'true_false' ), true ) ) {
			$is_multiple = 'multiple_choice' === $type;
			$expected_values = self::expected_answer_values( $question['expected_answer'] ?? '' );
			echo '<div class="les-options">';
			foreach ( $options as $option_index => $option ) {
				$field_id = $name . '_' . $option_index;
				$is_correct = in_array( (string) $option, $expected_values, true );
				printf(
					'<label for="%1$s" class="%7$s"><input id="%1$s" type="%2$s" name="%3$s%4$s" value="%5$s"> %6$s%8$s</label>',
					esc_attr( $field_id ),
					esc_attr( $is_multiple ? 'checkbox' : 'radio' ),
					esc_attr( $name ),
					$is_multiple ? '[]' : '',
					esc_attr( $option ),
					esc_html( $option ),
					$is_correct ? 'is-correct-answer' : 'is-wrong-answer',
					$is_correct ? ' <strong>' . esc_html__( 'Correct', 'logieval-secure' ) . '</strong>' : ''
				);
			}
			echo '</div>';
			return;
		}

		if ( LogiEval_Secure_Question_Types::TFJ === $type ) {
			$expected = LogiEval_Secure_Question_Types::tfj_expected( $question['expected_answer'] ?? '' );
			echo '<div class="les-options">';
			foreach ( array( 'Vrai', 'Faux' ) as $option ) {
				printf( '<label class="%3$s"><input type="radio" name="%1$s_choice" value="%2$s"> %2$s%4$s</label>', esc_attr( $name ), esc_attr( $option ), $option === $expected['choice'] ? 'is-correct-answer' : 'is-wrong-answer', $option === $expected['choice'] ? ' <strong>' . esc_html__( 'Correct', 'logieval-secure' ) . '</strong>' : '' );
			}
			echo '</div>';
			printf( '<label class="les-tfj-justify">%s<textarea name="%s_justification" rows="4"></textarea></label>', esc_html__( 'Justifie ta réponse', 'logieval-secure' ), esc_attr( $name ) );
			return;
		}
		if ( LogiEval_Secure_Question_Types::ORDERING === $type ) {
			$items   = LogiEval_Secure_Question_Types::student_items( $question, get_current_user_id() );
			$letters = 'letters' === LogiEval_Secure_Question_Types::order_settings( $question['expected_answer'] ?? '' )['mode'];
			echo '<ol class="les-order' . ( $letters ? ' is-letters' : '' ) . '">';
			foreach ( $items as $item ) {
				echo '<li class="les-order-item">' . ( $item['image'] ? '<img src="' . esc_url( $item['image']['url'] ) . '" alt="">' : '' ) . '<span>' . esc_html( $item['text'] ) . '</span></li>';
			}
			echo '</ol><p class="description">' . esc_html__( 'Aperçu : l’élève reçoit les éléments mélangés et les déplace (glisser ou flèches).', 'logieval-secure' ) . '</p>';
			return;
		}

		if ( LogiEval_Secure_Question_Types::MATCHING === $type ) {
			$m = LogiEval_Secure_Question_Types::student_match( $question, get_current_user_id() );
			echo '<div class="les-match les-match-preview"><ol class="les-match-col is-left">';
			foreach ( $m['left'] as $i => $l ) {
				echo '<li><span class="les-match-num">' . (int) ( $i + 1 ) . '</span> ' . esc_html( $l['text'] ) . '</li>';
			}
			echo '</ol><ol class="les-match-col is-right">';
			foreach ( $m['right'] as $i => $r ) {
				echo '<li><span class="les-match-num">' . esc_html( chr( 65 + ( $i % 26 ) ) ) . '</span> ' . esc_html( $r['text'] ) . '</li>';
			}
			echo '</ol></div><p class="description">' . esc_html__( 'Aperçu : l’élève relie chaque élément de gauche à un élément de droite (colonne de droite mélangée).', 'logieval-secure' ) . '</p>';
			return;
		}
		if ( LogiEval_Secure_Question_Types::BLANKS === $type ) {
			$b = LogiEval_Secure_Question_Types::student_blanks( $question, get_current_user_id() );
			if ( $b['bank'] ) {
				echo '<p class="les-blanks-bank">' . implode( ' ', array_map( static fn( $w ) => '<span>' . esc_html( $w ) . '</span>', $b['bank'] ) ) . '</p>';
			}
			echo '<p class="les-blanks-text">';
			foreach ( $b['parts'] as $part ) {
				echo null === $part ? '<input type="text" class="les-blank" size="10" aria-label="' . esc_attr__( 'Mot manquant', 'logieval-secure' ) . '">' : nl2br( esc_html( $part ) );
			}
			echo '</p>';
			return;
		}
		if ( 'calculation' === $type ) {
			$precision_label = LogiEval_Secure_Question_Types::precision_label( LogiEval_Secure_Question_Types::calculation_precision( $question['expected_answer'] ?? '' ) );
			if ( '' !== $precision_label ) {
				echo '<p class="les-precision-badge">' . esc_html( $precision_label ) . '</p>';
			}
			printf(
				'<div class="les-calculation-answer"><div class="les-calculation-fields"><label>%1$s<textarea name="%2$s_development" rows="4" placeholder="%3$s"></textarea></label><label>%4$s<input type="text" name="%2$s_result" placeholder="%5$s"></label></div></div>',
				esc_html__( 'Développement / raisonnement', 'logieval-secure' ),
				esc_attr( $name ),
				esc_attr__( 'Votre développement', 'logieval-secure' ),
				esc_html__( 'Résultat final', 'logieval-secure' ),
				esc_attr__( 'Votre résultat final', 'logieval-secure' )
			);
			return;
		}

		if ( 'short_answer' === $type ) {
			printf( '<input type="text" name="%s" placeholder="%s">', esc_attr( $name ), esc_attr__( 'Votre réponse', 'logieval-secure' ) );
			return;
		}

		printf( '<textarea name="%s" rows="6" placeholder="%s"></textarea>', esc_attr( $name ), esc_attr__( 'Votre réponse', 'logieval-secure' ) );
	}

	private static function question_type_label( string $type ): string {
		$labels = array(
			'short_answer'    => __( 'Réponse courte', 'logieval-secure' ),
			'long_answer'     => __( 'Réponse longue', 'logieval-secure' ),
			'qcm'             => __( 'QCM', 'logieval-secure' ),
			'multiple_choice' => __( 'QCM multi-réponses', 'logieval-secure' ),
			'true_false'      => __( 'Vrai / faux', 'logieval-secure' ),
			'true_false_justify' => __( 'Vrai / Faux argumenté', 'logieval-secure' ),
			'ordering'        => __( 'Remettre dans l’ordre', 'logieval-secure' ),
			'matching'        => __( 'Relier (gauche ↔ droite)', 'logieval-secure' ),
			'fill_blanks'     => __( 'Texte à trous', 'logieval-secure' ),
			'calculation'     => __( 'Mathématiques / calcul', 'logieval-secure' ),
			'manual'          => __( 'Correction manuelle', 'logieval-secure' ),
			'semi_auto'       => __( 'Correction semi-automatique', 'logieval-secure' ),
			'mes'             => __( 'Mise en situation', 'logieval-secure' ),
		);
		return $labels[ $type ] ?? $type;
	}

	private static function preview_duration_label( int $minutes ): string {
		if ( $minutes <= 0 ) {
			return '--:--';
		}
		return sprintf( '%02d:%02d', floor( $minutes / 60 ), $minutes % 60 );
	}

	/* ---------------------------------------------------------------------
	 * Image par question
	 * ------------------------------------------------------------------- */

	/** Récupère un fichier envoyé dans un champ imbriqué (ex. inline_questions[3][image]). */
	private static function uploaded_question_file( array $path ): ?array {
		if ( empty( $path ) ) {
			return null;
		}
		$root = array_shift( $path );
		if ( empty( $_FILES[ $root ] ) ) {
			return null;
		}
		if ( 'question_image' === $root ) {
			$file = $_FILES[ $root ];
		} else {
			$file = array();
			foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $key ) {
				$value = $_FILES[ $root ][ $key ] ?? null;
				foreach ( array_merge( $path, array( 'image' ) ) as $segment ) {
					$value = is_array( $value ) ? ( $value[ $segment ] ?? null ) : null;
				}
				$file[ $key ] = $value;
			}
		}
		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return null;
		}
		return $file;
	}

	private static function resolve_question_image( int $current_id, bool $remove, ?array $file ): int {
		if ( $file ) {
			$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'] );
			if ( empty( $check['type'] ) || 0 !== strpos( (string) $check['type'], 'image/' ) ) {
				return $remove ? 0 : $current_id;
			}
			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			$attachment_id = media_handle_sideload( $file, 0 );
			if ( ! is_wp_error( $attachment_id ) ) {
				return (int) $attachment_id;
			}
		}
		if ( $remove ) {
			return 0;
		}
		// Image choisie dans la médiathèque (ou image actuelle) : uniquement une vraie image du site.
		return $current_id && wp_attachment_is_image( $current_id ) ? $current_id : 0;
	}

	public static function question_image_data( int $media_id ): array {
		if ( ! $media_id || ! wp_attachment_is_image( $media_id ) ) {
			return array();
		}
		// 1.32.1 : adresses anonymes — le nom du fichier (qui peut contenir la réponse) n'apparaît jamais dans la page.
		return array(
			'url'  => self::private_image_url( $media_id, 'large' ),
			'full' => self::private_image_url( $media_id, 'full' ),
			'alt'  => '',
		);
	}

	private static function private_image_sig( int $media_id, string $size ): string {
		return substr( hash_hmac( 'sha256', $media_id . '|' . $size, wp_salt( 'auth' ) ), 0, 24 );
	}

	public static function private_image_url( int $media_id, string $size = 'large' ): string {
		$size = 'full' === $size ? 'full' : ( 'medium' === $size ? 'medium' : 'large' );
		if ( ! self::private_image_path( $media_id, $size ) ) {
			// Fichier non local (CDN…) : adresse habituelle.
			return (string) ( wp_get_attachment_image_url( $media_id, $size ) ?: wp_get_attachment_image_url( $media_id, 'full' ) );
		}
		return add_query_arg( 'les_img', $media_id . '-' . $size . '-' . self::private_image_sig( $media_id, $size ), home_url( '/' ) );
	}

	private static function private_image_path( int $media_id, string $size ): string {
		$file = (string) get_attached_file( $media_id );
		if ( 'full' !== $size ) {
			$meta = image_get_intermediate_size( $media_id, $size );
			if ( ! empty( $meta['path'] ) ) {
				$up   = wp_get_upload_dir();
				$cand = trailingslashit( $up['basedir'] ) . $meta['path'];
				if ( is_readable( $cand ) ) {
					return $cand;
				}
			}
		}
		return $file && is_readable( $file ) ? $file : '';
	}

	/** Sert une image de question sous une adresse sans nom de fichier. */
	public static function serve_private_image(): void {
		if ( empty( $_GET['les_img'] ) || ! is_string( $_GET['les_img'] ) ) {
			return;
		}
		if ( ! preg_match( '/^(\d+)-(large|full|medium)-([a-f0-9]{24})$/', wp_unslash( $_GET['les_img'] ), $m ) ) {
			status_header( 404 );
			exit;
		}
		$id = (int) $m[1];
		if ( ! hash_equals( self::private_image_sig( $id, $m[2] ), $m[3] ) || ! wp_attachment_is_image( $id ) ) {
			status_header( 404 );
			exit;
		}
		$path = self::private_image_path( $id, $m[2] );
		if ( ! $path ) {
			status_header( 404 );
			exit;
		}
		$type = wp_check_filetype( $path );
		$mime = $type['type'] ?: 'image/jpeg';
		$ext  = $type['ext'] ?: 'jpg';
		nocache_headers();
		header( 'Cache-Control: private, max-age=3600' );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="image.' . $ext . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path );
		exit;
	}

	private static function render_question_image( int $media_id, string $class = '' ): void {
		$image = self::question_image_data( $media_id );
		if ( empty( $image ) ) {
			return;
		}
		?>
		<figure class="les-question-image <?php echo esc_attr( $class ); ?>">
			<button type="button" class="les-zoomable" data-les-zoom-image="<?php echo esc_url( $image['full'] ); ?>" aria-label="<?php esc_attr_e( 'Agrandir l’image', 'logieval-secure' ); ?>">
				<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy">
				<span class="les-zoom-hint">🔍 <?php esc_html_e( 'Agrandir', 'logieval-secure' ); ?></span>
			</button>
		</figure>
		<?php
	}

	/** Bouton « 📁 Médiathèque » : choisir une image déjà présente sur le site ou en téléverser une (LogiOne 1.32.1). */
	public static function render_media_pick_button( string $label = '' ): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		printf(
			'<button type="button" class="button les-button-secondary les-media-pick" data-les-media-pick>%s</button><span class="les-media-picked" data-les-media-picked hidden></span>',
			esc_html( '' !== $label ? $label : __( '📁 Choisir dans la médiathèque', 'logieval-secure' ) )
		);
	}

	private static function render_question_image_field( string $name_prefix, array $question ): void {
		$media_id   = absint( $question['media_id'] ?? 0 );
		$image      = self::question_image_data( $media_id );
		$file_name  = '' === $name_prefix ? 'question_image' : $name_prefix . '[image]';
		$media_name = '' === $name_prefix ? 'media_id' : $name_prefix . '[media_id]';
		$rm_name    = '' === $name_prefix ? 'remove_image' : $name_prefix . '[remove_image]';
		?>
		<div class="les-question-image-field" data-les-media-field>
			<strong><?php esc_html_e( 'Image de la question (facultatif)', 'logieval-secure' ); ?></strong>
			<input type="hidden" name="<?php echo esc_attr( $media_name ); ?>" value="<?php echo esc_attr( $media_id ); ?>" data-les-media-input>
			<?php if ( ! empty( $image ) ) : ?>
				<div class="les-question-image-current">
					<img src="<?php echo esc_url( $image['url'] ); ?>" alt="">
					<label class="les-check"><input type="checkbox" name="<?php echo esc_attr( $rm_name ); ?>" value="1"> <span><?php esc_html_e( 'Retirer cette image', 'logieval-secure' ); ?></span></label>
				</div>
			<?php endif; ?>
			<label><?php echo esc_html( ! empty( $image ) ? __( 'Remplacer par une autre image', 'logieval-secure' ) : __( 'Ajouter une image (JPG, PNG, GIF, WebP)', 'logieval-secure' ) ); ?>
				<input type="file" name="<?php echo esc_attr( $file_name ); ?>" accept="image/*">
			</label>
			<?php self::render_media_pick_button(); ?>
			<p class="description"><?php esc_html_e( 'L’élève voit l’image sous la question et peut l’agrandir sans quitter le plein écran.', 'logieval-secure' ); ?></p>
		</div>
		<?php
	}

	public static function handle_save_evaluation(): void {
		self::require_teacher_post();
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();

		$id              = isset( $_POST['evaluation_id'] ) ? absint( $_POST['evaluation_id'] ) : 0;
		$existing        = $id ? self::require_managed_evaluation( $id ) : null;
		$evaluation_type = self::normalize_evaluation_type( $_POST['evaluation_type'] ?? 'mes_company' );
		if ( 'story' === $evaluation_type ) {
			$question_rows = self::flatten_story_sections( (array) ( $_POST['story_sections'] ?? array() ) );
		} else {
			$question_rows = array();
			foreach ( (array) ( $_POST['inline_questions'] ?? array() ) as $row_key => $row ) {
				$row               = (array) $row;
				$row['_file_path'] = array( 'inline_questions', $row_key );
				$question_rows[]   = $row;
			}
		}
		$inline_questions = self::save_inline_questions( $question_rows, absint( $_POST['sensei_course_id'] ?? 0 ) );
		$total_points = array_sum( array_map( static fn( $question ) => (float) $question['points'], $inline_questions ) );
		// Brouillon : enregistré sans codes élèves ni fiche LogiNotes ; jamais pour une évaluation déjà commencée par des élèves.
		$save_as_draft = 'draft' === sanitize_key( wp_unslash( $_POST['save_mode'] ?? '' ) ) && ( ! $existing || empty( self::get_attempts( (int) $existing['id'] ) ) );
		$data         = array(
			'title'              => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'        => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'instructions'       => wp_kses_post( wp_unslash( $_POST['instructions'] ?? '' ) ),
			'duration_minutes'   => absint( $_POST['duration_minutes'] ?? 0 ),
			'total_points'       => $total_points,
			'opens_at'           => self::mysql_datetime_from_local( $_POST['opens_at'] ?? '' ),
			'closes_at'          => self::mysql_datetime_from_local( $_POST['closes_at'] ?? '' ),
			'sensei_course_id'   => absint( $_POST['sensei_course_id'] ?? 0 ),
			'sensei_group_ids'   => wp_json_encode( array_slice( array_values( array_filter( array_map( 'absint', (array) ( $_POST['sensei_group_ids'] ?? array() ) ) ) ), 0, 1 ) ),
			'student_user_ids'   => wp_json_encode( self::csv_ids( $_POST['student_user_ids'] ?? '' ) ),
			'evaluation_type'    => $evaluation_type,
			'story_intro'        => wp_kses_post( wp_unslash( $_POST['story_intro'] ?? '' ) ),
			'security_notice'    => wp_kses_post( wp_unslash( $_POST['security_notice'] ?? self::default_security_notice() ) ),
			'company_name'       => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
			'company_context'    => wp_kses_post( wp_unslash( $_POST['company_context'] ?? '' ) ),
			'apprentice_role'    => wp_kses_post( wp_unslash( $_POST['apprentice_role'] ?? '' ) ),
			'secure_mode'        => isset( $_POST['secure_mode'] ) ? 1 : 0,
			'anti_cheat_rules'   => wp_json_encode( array( 'fullscreen', 'visibilitychange', 'blur', 'no_contextmenu', 'no_copy_paste', 'keyboard_guard' ) ),
			'status'             => $save_as_draft ? 'draft' : 'published',
			'created_by'         => get_current_user_id(),
			'is_shared'          => isset( $_POST['is_shared'] ) ? 1 : 0,
			'updated_at'         => current_time( 'mysql' ),
		);
		if ( $data['is_shared'] && ( ! $existing || empty( $existing['is_shared'] ) ) ) {
			$data['shared_at'] = current_time( 'mysql' );
		}
		if ( $existing ) {
			unset( $data['created_by'] ); // L'auteur reste le propriétaire, même si l'administrateur modifie.
		}
		$logo_id = self::handle_company_logo_upload();
		if ( ! $logo_id ) {
			// Logo choisi dans la médiathèque.
			$picked  = absint( $_POST['company_logo_media_id'] ?? 0 );
			$logo_id = $picked && wp_attachment_is_image( $picked ) ? $picked : 0;
		}
		if ( $logo_id ) {
			$data['company_logo_id'] = $logo_id;
		}

		if ( $id ) {
			$wpdb->update( $t['evaluations'], $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $t['evaluations'], $data );
			$id = (int) $wpdb->insert_id;
		}
		if ( $id ) {
			self::set_formative( $id, ! empty( $_POST['logiexam_formative'] ) ); // 1.52.0
		}

		self::set_exam_options(
			$id,
			array(
				'shuffle_questions' => isset( $_POST['shuffle_questions'] ) ? 1 : 0,
				'in_library'        => isset( $_POST['in_library'] ) ? 1 : 0,
			)
		);

		$wpdb->delete( $t['eval_questions'], array( 'evaluation_id' => $id ) );
		foreach ( $inline_questions as $position => $question ) {
			$wpdb->insert(
				$t['eval_questions'],
				array(
					'evaluation_id' => $id,
					'question_id'   => (int) $question['id'],
					'position'      => $position + 1,
					'points'        => (float) $question['points'],
				)
			);
		}

		// Classes ou élèves changés : les élèves qui ne sont plus visés perdent leur code tout de suite.
		$saved_evaluation = self::get_evaluation( (int) $id );
		if ( $saved_evaluation ) {
			self::sync_codes_with_target( $saved_evaluation );
		}

		$new_codes = 0;
		if ( ! $save_as_draft ) {
			// Codes uniques générés automatiquement pour les élèves qui n'en ont pas encore.
			$new_codes = self::ensure_codes_for_evaluation( $id );
			// LogiOne : création (ou reprise) de la fiche LogiNotes liée, sans doublon (EXG-07).
			do_action( 'logipilot_exam_evaluation_saved', $id, false );
		}
		LogiEval_Secure_SEB::save_from_post( (int) $id );
		$saved_flag = $save_as_draft ? 'draft' : 1;
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-create&evaluation_id=' . $id . '&saved=' . $saved_flag . '&new_codes=' . $new_codes ), array( 'evaluation_id' => $id, 'saved' => $saved_flag, 'new_codes' => $new_codes ) ) );
		exit;
	}

	/** Types proposés partout (éditeur d'évaluation, sections d'histoire, banque de questions). */
	public static function question_types(): array {
		return array(
			'short_answer' => __( 'Réponse courte', 'logieval-secure' ),
			'long_answer'  => __( 'Réponse longue', 'logieval-secure' ),
			'true_false'   => __( 'Vrai / Faux', 'logieval-secure' ),
			'true_false_justify' => __( 'Vrai / Faux argumenté', 'logieval-secure' ),
			'qcm'          => __( 'QCM', 'logieval-secure' ),
			'ordering'     => __( 'Remettre dans l’ordre', 'logieval-secure' ),
			'matching'     => __( 'Relier (gauche ↔ droite)', 'logieval-secure' ),
			'fill_blanks'  => __( 'Texte à trous', 'logieval-secure' ),
			'calculation'  => __( 'Mathématiques / calcul', 'logieval-secure' ),
		);
	}

	/** Anciens types encore acceptés pour les questions existantes (plus proposés à la création). */
	private static function legacy_question_types(): array {
		return array( 'multiple_choice', 'manual', 'mes' );
	}

	private static function render_question_type_select( string $name, string $current ): void {
		$types = self::question_types();
		?>
		<select name="<?php echo esc_attr( $name ); ?>" data-logiexam-question-type>
			<?php foreach ( $types as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
			<?php if ( '' !== $current && ! isset( $types[ $current ] ) ) : ?>
				<option value="<?php echo esc_attr( $current ); ?>" selected><?php echo esc_html( sprintf( __( '%s (ancien type)', 'logieval-secure' ), self::question_type_label( $current ) ) ); ?></option>
			<?php endif; ?>
		</select>
		<?php
	}

	/**
	 * Construit les données d'une question à partir d'une ligne de formulaire (même logique partout).
	 * Retourne null si la question posée est vide.
	 */
	private static function build_question_data( array $row, int $default_course_id ): ?array {
		$prompt = wp_kses_post( wp_unslash( $row['prompt'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $prompt ) ) ) {
			return null;
		}
		$type = sanitize_key( $row['type'] ?? 'short_answer' );
		if ( ! isset( self::question_types()[ $type ] ) && ! in_array( $type, self::legacy_question_types(), true ) ) {
			$type = 'short_answer';
		}

		if ( 'qcm' === $type ) {
			$raw_options     = array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) ( $row['qcm_options'] ?? array() ) ) );
			$correct_index   = absint( $row['qcm_correct_index'] ?? 0 );
			$expected_answer = $raw_options[ $correct_index ] ?? '';
			$options         = array_values( array_filter( $raw_options ) );
		} else {
			$options         = array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', wp_unslash( (string) ( $row['options'] ?? '' ) ) ) ) );
			$expected_answer = wp_kses_post( wp_unslash( $row['expected_answer'] ?? '' ) );
		}
		if ( 'true_false' === $type ) {
			$expected_answer = sanitize_text_field( wp_unslash( $row['true_false_answer'] ?? '' ) );
		}
		if ( 'calculation' === $type ) {
			$expected_answer = wp_json_encode(
				array(
					'development'  => wp_kses_post( wp_unslash( $row['calculation_development'] ?? '' ) ),
					'final_result' => sanitize_text_field( wp_unslash( $row['calculation_result'] ?? '' ) ),
					'precision'    => in_array( (string) ( $row['calculation_precision'] ?? '' ), LogiEval_Secure_Question_Types::PRECISIONS, true ) ? (string) $row['calculation_precision'] : '',
				),
				JSON_UNESCAPED_UNICODE
			);
		}
		$file_path = (array) ( $row['_file_path'] ?? array() );
		$special   = LogiEval_Secure_Question_Types::build_from_form(
			$type,
			$row,
			static function ( int $current, bool $remove, array $suffix ) use ( $file_path ): int {
				return self::resolve_question_image( $current, $remove, $file_path ? self::uploaded_question_file( array_merge( $file_path, $suffix ) ) : null );
			}
		);
		if ( $special ) {
			$expected_answer = $special['expected_answer'];
			$options         = json_decode( $special['options'], true );
		}
		return array(
			'origin'           => 'logiexam',
			'sensei_course_id' => absint( $row['sensei_course_id'] ?? $default_course_id ),
			'title'            => wp_trim_words( wp_strip_all_tags( $prompt ), 12, '' ),
			'context'          => wp_kses_post( wp_unslash( $row['context'] ?? '' ) ),
			'prompt'           => $prompt,
			'type'             => $type,
			'expected_answer'  => $expected_answer,
			'options'          => wp_json_encode( array_values( (array) $options ), JSON_UNESCAPED_UNICODE ),
			'points'           => max( 0, (float) ( $row['points'] ?? 1 ) ),
			'grading_criteria' => self::sanitize_criteria( $row['criteria'] ?? array() ),
			'media_id'         => self::resolve_question_image( absint( $row['media_id'] ?? 0 ), ! empty( $row['remove_image'] ), self::uploaded_question_file( (array) ( $row['_file_path'] ?? array() ) ) ),
			// 1.36 : calculatrice à côté de la question (colonne recommended_time réutilisée comme drapeau).
			'recommended_time' => 'calculation' !== $type && ! empty( $row['show_calculator'] ) ? 1 : 0,
			'updated_at'       => current_time( 'mysql' ),
		);
	}

	private static function save_inline_questions( array $rows, int $default_course_id ): array {
		global $wpdb;
		$t     = LogiEval_Secure_Database::tables();
		$saved = array();

		foreach ( $rows as $row ) {
			$data = self::build_question_data( (array) $row, $default_course_id );
			if ( ! $data ) {
				continue;
			}
			$question_id = absint( $row['id'] ?? 0 );
			if ( $question_id && self::can_manage_question( self::get_question( $question_id ) ) ) {
				$wpdb->update( $t['questions'], $data, array( 'id' => $question_id ) );
			} else {
				$data['created_by'] = get_current_user_id();
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $t['questions'], $data );
				$question_id = (int) $wpdb->insert_id;
			}
			if ( $question_id ) {
				$saved[] = array( 'id' => $question_id, 'points' => (float) $data['points'] );
			}
		}

		return $saved;
	}

	private static function flatten_story_sections( array $sections ): array {
		$rows = array();
		foreach ( $sections as $section_key => $section ) {
			$section = (array) $section;
			$context = wp_kses_post( wp_unslash( $section['context'] ?? '' ) );
			foreach ( (array) ( $section['questions'] ?? array() ) as $question_key => $question ) {
				$question = (array) $question;
				$question['context'] = $context;
				$question['_file_path'] = array( 'story_sections', $section_key, 'questions', $question_key );
				$rows[] = $question;
			}
		}
		return $rows;
	}

	public static function handle_save_question(): void {
		self::require_teacher_post();
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();

		$question_id = absint( $_POST['question_id'] ?? 0 );
		$question    = $question_id ? self::get_question( $question_id ) : null;
		if ( $question && ! self::can_manage_question( $question ) ) {
			wp_die( esc_html__( 'Cette question appartient à un autre enseignant.', 'logieval-secure' ), '', array( 'response' => 403 ) );
		}
		$row               = (array) ( $_POST['bank'] ?? array() );
		$row['_file_path'] = array( 'bank' );
		$data              = self::build_question_data( $row, 0 );
		if ( ! $data ) {
			wp_die( esc_html__( 'La question posée est obligatoire.', 'logieval-secure' ) );
		}
		$data['origin']     = $question['origin'] ?? 'logiexam';
		$data['difficulty'] = sanitize_key( $row['difficulty'] ?? '' );
		$data['tags']       = sanitize_text_field( wp_unslash( $row['tags'] ?? '' ) );
		$data['category']   = $data['tags'];

		if ( $question ) {
			$wpdb->update( $t['questions'], $data, array( 'id' => $question_id ) );
		} else {
			$data['origin']     = 'logiexam';
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $t['questions'], $data );
		}

		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-questions&question_saved=1' ), array( 'logiexam_view' => 'questions', 'question_id' => null, 'question_saved' => 1 ) ) );
		exit;
	}

	public static function handle_delete_questions(): void {
		self::require_teacher_post();
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();

		$question_ids = array_map( 'absint', (array) ( $_POST['question_ids'] ?? array() ) );
		$single_id    = absint( $_POST['single_question_id'] ?? 0 );
		if ( $single_id ) {
			$question_ids[] = $single_id;
		}
		$question_ids = array_values( array_unique( array_filter( $question_ids ) ) );
		$affected_evaluations = array();

		$blocked = 0;
		foreach ( $question_ids as $question_id ) {
			if ( ! self::can_manage_question( self::get_question( $question_id ) ) ) {
				continue; // Jamais de suppression dans la banque d'un collègue.
			}
			// LogiOne : une question à laquelle des élèves ont répondu n'est jamais supprimée (copies conservées).
			if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['answers']} WHERE question_id=%d", $question_id ) ) > 0 ) {
				$blocked++;
				continue;
			}
			$affected_evaluations = array_merge(
				$affected_evaluations,
				array_map(
					'absint',
					(array) $wpdb->get_col( $wpdb->prepare( "SELECT evaluation_id FROM {$t['eval_questions']} WHERE question_id=%d", $question_id ) )
				)
			);
			$wpdb->delete( $t['answers'], array( 'question_id' => $question_id ) );
			$wpdb->delete( $t['eval_questions'], array( 'question_id' => $question_id ) );
			$wpdb->delete( $t['questions'], array( 'id' => $question_id ) );
		}

		foreach ( array_values( array_unique( array_filter( $affected_evaluations ) ) ) as $evaluation_id ) {
			$total_points = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points), 0) FROM {$t['eval_questions']} WHERE evaluation_id=%d", $evaluation_id ) );
			$wpdb->update( $t['evaluations'], array( 'total_points' => $total_points, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $evaluation_id ) );
		}

		if ( $blocked ) {
			set_transient( 'logipilot_questions_blocked_' . get_current_user_id(), $blocked, 10 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-questions&question_deleted=1' . ( $blocked ? '&questions_blocked=' . $blocked : '' ) ), array( 'logiexam_view' => 'questions', 'question_id' => null, 'question_deleted' => 1, 'questions_blocked' => $blocked ?: null ) ) );
		exit;
	}

	public static function handle_import_sensei_question(): void {
		self::require_teacher_post();
		$sensei_id = absint( $_POST['sensei_question_id'] ?? 0 );
		self::import_sensei_question_to_bank( $sensei_id );
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-questions&imported=1' ), array( 'imported' => 1 ) ) );
		exit;
	}

	private static function import_sensei_question_to_bank( int $sensei_id ): int {
		if ( ! $sensei_id ) {
			return 0;
		}

		global $wpdb;
		$t        = LogiEval_Secure_Database::tables();
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$t['questions']} WHERE origin=%s AND origin_id=%d AND created_by=%d ORDER BY id DESC LIMIT 1",
				'sensei_copy',
				$sensei_id,
				get_current_user_id()
			)
		);
		if ( $existing ) {
			return $existing;
		}

		foreach ( LogiEval_Secure_Sensei::get_quiz_questions() as $question ) {
			if ( (int) $question['id'] === $sensei_id ) {
				$wpdb->insert(
					$t['questions'],
					array(
						'origin'          => 'sensei_copy',
						'origin_id'       => $sensei_id,
						'sensei_course_id'=> absint( $question['course_id'] ?? 0 ),
						'title'           => sanitize_text_field( $question['title'] ),
						'context'         => '',
						'prompt'          => wp_kses_post( $question['prompt'] ),
						'type'            => sanitize_key( $question['type'] ),
						'expected_answer' => wp_kses_post( $question['expected_answer'] ),
						'options'         => is_string( $question['options'] ) ? $question['options'] : wp_json_encode( $question['options'] ),
						'points'          => max( 0.25, (float) $question['points'] ),
						'tags'            => sanitize_text_field( $question['categories'] ?? '' ),
						'category'        => sanitize_text_field( $question['categories'] ?? '' ),
						'created_by'      => get_current_user_id(),
						'created_at'      => current_time( 'mysql' ),
					)
				);
				return (int) $wpdb->insert_id;
			}
		}

		return 0;
	}

	public static function handle_duplicate_evaluation(): void {
		self::require_teacher_post();
		global $wpdb;
		$t             = LogiEval_Secure_Database::tables();
		$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
		$evaluation    = self::get_evaluation( $evaluation_id );

		if ( ! self::can_view_evaluation( $evaluation ) ) {
			wp_die( esc_html__( 'Évaluation introuvable ou non partagée.', 'logieval-secure' ), '', array( 'response' => 403 ) );
		}

		unset( $evaluation['id'] );
		$evaluation['title']           = sprintf( __( 'Copie - %s', 'logieval-secure' ), $evaluation['title'] );
		$evaluation['status']          = 'draft';
		$evaluation['archived_status'] = null;
		$evaluation['deleted_at']      = null;
		$evaluation['deleted_by']      = 0;
		$evaluation['is_shared']       = 0;
		$evaluation['shared_at']       = null;
		$evaluation['created_by']      = get_current_user_id();
		$evaluation['created_at']      = current_time( 'mysql' );
		$evaluation['updated_at']      = current_time( 'mysql' );
		// Une copie ne reprend jamais le groupe classe, les élèves spécifiques ni les dates d'ouverture / fermeture :
		// l'enseignant les choisit pour la nouvelle session.
		$evaluation['sensei_group_ids'] = wp_json_encode( array() );
		$evaluation['student_user_ids'] = wp_json_encode( array() );
		$evaluation['opens_at']         = null;
		$evaluation['closes_at']        = null;

		$wpdb->insert( $t['evaluations'], $evaluation );
		$new_id = (int) $wpdb->insert_id;

		if ( $new_id && self::exam_options( $evaluation_id )['shuffle_questions'] ) {
			// La copie garde l'ordre aléatoire ; elle n'entre pas dans la bibliothèque.
			self::set_exam_options( $new_id, array( 'shuffle_questions' => 1 ) );
		}

		if ( $new_id ) {
			// Chaque copie possède ses propres questions : modifier la copie ne modifie jamais l'original.
			foreach ( self::get_questions_for_evaluation( $evaluation_id ) as $question ) {
				$new_question_id = self::copy_question_for_current_user( $question );
				if ( ! $new_question_id ) {
					continue;
				}
				$wpdb->insert(
					$t['eval_questions'],
					array(
						'evaluation_id' => $new_id,
						'question_id'   => $new_question_id,
						'position'      => (int) $question['position'],
						'points'        => (float) $question['evaluation_points'],
					)
				);
			}
		}

		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-create&evaluation_id=' . $new_id . '&duplicated=1' ), array( 'logiexam_view' => 'create', 'evaluation_id' => $new_id, 'duplicated' => 1 ) ) );
		exit;
	}

	public static function handle_delete_evaluation(): void {
		self::require_teacher_post();
		global $wpdb;
		$t             = LogiEval_Secure_Database::tables();
		$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
		$evaluation    = self::require_managed_evaluation( $evaluation_id );

		if ( ! $evaluation ) {
			wp_die( esc_html__( 'Évaluation introuvable.', 'logieval-secure' ) );
		}

		$wpdb->update(
			$t['evaluations'],
			array(
				'archived_status' => (string) ( $evaluation['status'] ?? 'draft' ),
				'status'          => 'deleted',
				'deleted_at'      => current_time( 'mysql' ),
				'deleted_by'      => get_current_user_id(),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $evaluation_id )
		);

		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-evaluations&deleted=1' ), array( 'logiexam_view' => 'evaluations', 'evaluation_id' => null, 'deleted' => 1 ) ) );
		exit;
	}

	public static function handle_restore_evaluation(): void {
		self::require_teacher_post();
		global $wpdb;
		$t             = LogiEval_Secure_Database::tables();
		$evaluation_id = absint( $_POST['evaluation_id'] ?? 0 );
		$evaluation    = self::require_managed_evaluation( $evaluation_id );

		if ( ! $evaluation || 'deleted' !== (string) ( $evaluation['status'] ?? '' ) ) {
			wp_die( esc_html__( 'Évaluation supprimée introuvable.', 'logieval-secure' ) );
		}

		$restore_status = ! empty( $evaluation['archived_status'] ) && 'deleted' !== (string) $evaluation['archived_status'] ? (string) $evaluation['archived_status'] : 'draft';
		$wpdb->update(
			$t['evaluations'],
			array(
				'status'          => $restore_status,
				'archived_status' => null,
				'deleted_at'      => null,
				'deleted_by'      => 0,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $evaluation_id )
		);

		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-evaluations&restored=1' ), array( 'logiexam_view' => 'evaluations', 'evaluation_id' => null, 'restored' => 1 ) ) );
		exit;
	}

	public static function get_deleted_evaluations(): array {
		global $wpdb;
		$t   = LogiEval_Secure_Database::tables();
		$sql = "SELECT * FROM {$t['evaluations']} WHERE status = 'deleted'";
		if ( ! self::admin_sees_all() ) {
			$sql .= $wpdb->prepare( ' AND created_by=%d', get_current_user_id() );
		}
		return $wpdb->get_results( $sql . ' ORDER BY deleted_at DESC, updated_at DESC', ARRAY_A );
	}

	/** Banque de questions de l'enseignant connecté (toutes pour l'administrateur en vue globale). */
	public static function get_questions(): array {
		global $wpdb;
		$t   = LogiEval_Secure_Database::tables();
		$sql = "SELECT * FROM {$t['questions']}";
		if ( ! self::admin_sees_all() ) {
			$sql .= $wpdb->prepare( ' WHERE created_by=%d', get_current_user_id() );
		}
		return $wpdb->get_results( $sql . ' ORDER BY created_at DESC', ARRAY_A );
	}

	public static function get_question( int $id ): ?array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['questions']} WHERE id=%d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public static function get_questions_for_evaluation( int $evaluation_id ): array {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return $wpdb->get_results( $wpdb->prepare( "SELECT q.*, eq.position, eq.points AS evaluation_points FROM {$t['eval_questions']} eq INNER JOIN {$t['questions']} q ON q.id=eq.question_id WHERE eq.evaluation_id=%d ORDER BY eq.position ASC", $evaluation_id ), ARRAY_A );
	}

	public static function question_belongs_to_evaluation( int $question_id, int $evaluation_id ): bool {
		global $wpdb;
		$t = LogiEval_Secure_Database::tables();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['eval_questions']} WHERE evaluation_id=%d AND question_id=%d", $evaluation_id, $question_id ) );
	}

	public static function get_evaluation_question_ids( int $evaluation_id ): array {
		return array_map( static fn( $q ) => (int) $q['id'], self::get_questions_for_evaluation( $evaluation_id ) );
	}

	private static function answer_row_by_question( array $answers, int $question_id ): ?array {
		foreach ( $answers as $answer ) {
			if ( (int) ( $answer['question_id'] ?? 0 ) === $question_id ) {
				return $answer;
			}
		}
		return null;
	}

	private static function sum_question_points( array $question_ids ): float {
		$total = 0.0;
		foreach ( $question_ids as $question_id ) {
			$question = self::get_question( (int) $question_id );
			if ( $question ) {
				$total += (float) $question['points'];
			}
		}
		return round( $total, 2 );
	}

	private static function textarea_options_value( $options ): string {
		$decoded = json_decode( (string) $options, true );
		if ( is_array( $decoded ) ) {
			// Éléments « remettre dans l'ordre » (objets) : seulement leur texte.
			return implode( "\n", array_map( static fn( $v ) => is_array( $v ) ? (string) ( $v['text'] ?? '' ) : (string) $v, $decoded ) );
		}
		return (string) $options;
	}

	private static function normalize_evaluation_type( $type ): string {
		$type = sanitize_key( (string) $type );
		if ( 'classic' === $type ) {
			return 'mes_company';
		}
		return in_array( $type, array( 'mes_company', 'story', 'simple_quiz' ), true ) ? $type : 'mes_company';
	}

		private static function handle_company_logo_upload(): int {
		if ( empty( $_FILES['company_logo']['name'] ) || ! is_uploaded_file( $_FILES['company_logo']['tmp_name'] ?? '' ) ) {
			return 0;
		}

		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$attachment_id = media_handle_upload( 'company_logo', 0 );
		return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
	}
}

