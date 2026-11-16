<?php
/**
 * LogiEval_Secure_Evaluations — partie « ai-prompts » (LogiOne 1.32).
 * Consignes données à l'IA, modifiables par chaque enseignant : correction (règles, calculs, commentaire pour l'élève)
 * et création (règles, QCM). Le texte d'origine est celui que LogiExam utilisait jusqu'ici ; la partie technique
 * (format de réponse JSON, méthode critère par critère) reste gérée par LogiExam pour que rien ne casse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Ai_Prompts {

	/** Blocs de consignes modifiables. Variables possibles entre accolades (remplacées au moment de l'envoi). */
	public static function ai_prompt_blocks(): array {
		return array(
			'grading_rules'    => array(
				'group'   => 'grading',
				'title'   => __( 'Correction — règles pour juger les réponses', 'logieval-secure' ),
				'help'    => __( 'Comment l’IA décide si un critère est rempli. Appliqué à chaque pré-correction.', 'logieval-secure' ),
				'vars'    => array(),
				'default' => "Sources, dans cet ordre : reference_theorique_sensei (si fournie), reponse_attendue, la consigne, le contexte. N'utilise pas ta culture générale pour contredire le cours.\n"
					. "ANCRAGE SUR LA RÉPONSE ATTENDUE : chaque critère vise un élément PRÉCIS de reponse_attendue. Recopie cet élément dans \"expected\". Le critère est rempli si la citation de l'élève exprime CET élément — avec ses propres mots, sans devoir reprendre ceux de reponse_attendue ni tous ses détails (mêmes idées, ou exemples que reponse_attendue ou le cours donnent pour la même raison). Exemple : critère « pays cité » avec reponse_attendue « Panama, Libéria, îles Marshall » → un autre pays (États-Unis, France…) ne remplit PAS le critère. Une information vraie mais différente de l'attendu, ou qui ne répond pas à la question, vaut \"miss\". Indique \"matches_expected\": true seulement si c'est bien le cas.\n"
					. "Règles : un critère n'est rempli que si l'élève l'a ÉCRIT. Comprends ce qu'il a écrit même s'il l'a mal écrit (fautes, écriture phonétique, abréviations, mots du métier, phrases incomplètes), mais n'ajoute jamais une idée qu'il n'a pas écrite. Une réponse hors sujet ne remplit aucun critère, même si elle est longue. Ne pénalise pas l'orthographe ni le style si le sens reste clair. Si la réponse indique « (aucun développement écrit) », tout critère sur le développement, le calcul ou la démarche est \"miss\".",
			),
			'grading_vocabulary' => array(
				'group'   => 'grading',
				'title'   => __( 'Correction — vocabulaire du métier et équivalences', 'logieval-secure' ),
				'help'    => __( 'Abréviations, mots d’atelier et expressions que vos élèves emploient, avec leur sens. L’IA les comprend comme le terme du cours. Une ligne par équivalence ; complétez librement.', 'logieval-secure' ),
				'vars'    => array(),
				'default' => "BL, bon de livraison = bulletin de livraison
"
					. "transpal, transpalette manuelle = transpalette
"
					. "chariot, élévateur, clark = chariot élévateur
"
					. "FIFO = premier entré, premier sorti ; LIFO = dernier entré, premier sorti ; FEFO = premier périmé, premier sorti
"
					. "palette euro, EPAL, EUR = palette 1200 × 800 mm
"
					. "réceptionner, recevoir la marchandise = réception des marchandises
"
					. "contrôle quantitatif = compter, vérifier le nombre de colis ; contrôle qualitatif = vérifier l'état, les dégâts
"
					. "stock min, stock de sécurité = stock minimal
"
					. "EPI = équipements de protection individuelle (souliers de sécurité, gants, gilet…)
"
					. "SUVA = assurance accidents ; CFST = commission fédérale de coordination pour la sécurité au travail",
			),
			'grading_calc'     => array(
				'group'   => 'grading',
				'title'   => __( 'Correction — questions de calcul', 'logieval-secure' ),
				'help'    => __( 'Ajouté seulement pour les questions de calcul.', 'logieval-secure' ),
				'vars'    => array(),
				'default' => "QUESTION DE CALCUL : \"method_coherent\" = true seulement si le développement de l'élève applique la bonne méthode (bonne opération ou formule, avec les bonnes données de l'énoncé) pour trouver CE QUI EST DEMANDÉ, même avec une erreur de calcul en cours de route. Un calcul qui n'a rien à voir avec la question, qui utilise de mauvaises données ou une opération absurde → method_coherent = false, et les critères de développement / démarche sont \"miss\" (0 point). Ne donne JAMAIS de points partiels juste parce qu'un calcul a été écrit : écrire des chiffres ne suffit pas.\nORDRE DE LECTURE : 1) regarde d'abord le RÉSULTAT FINAL (compare les nombres : virgule ou point, unité, phrase autour ; un arrondi correct est juste). 2) S'il est juste, relis le développement avec bienveillance pour comprendre comment l'élève y est arrivé : une démarche ÉQUIVALENTE est acceptée (autre ordre des opérations, étapes regroupées ou découpées, conversion d'unités faite autrement — ex. diviser par 3 puis par 100 au lieu de diviser par 300 —, signes = enchaînés, notation maladroite). Ne refuse la démarche que si elle est vraiment fausse ou absente. 3) S'il est faux, cherche OÙ est l'erreur : bonne méthode avec une erreur de calcul → démarche acceptée, résultat refusé ; mauvaise méthode → les deux refusés.",
			),
			'comment_style'    => array(
				'group'   => 'grading',
				'title'   => __( 'Correction — commentaire pour l’élève', 'logieval-secure' ),
				'help'    => __( 'Le ton et la forme du commentaire (pré-correction et « Régénérer le commentaire »). {adresse} = « en le tutoyant » ou « en le vouvoyant » selon votre réglage ; {exemple} = l’exemple de ton correspondant.', 'logieval-secure' ),
				'vars'    => array( '{adresse}', '{exemple}' ),
				'default' => "Consignes pour comment (lu par l'élève, un apprenti de 15 à 20 ans en Suisse romande) : explique-lui simplement, à partir de ce qu'il a répondu, pourquoi il a les points ou pourquoi il ne les a pas. "
					. "Écris comme un enseignant d’école professionnelle de Suisse romande qui parle à son apprenti, {adresse}, avec le français usuel de Suisse romande (par exemple « c’est juste » pour une bonne réponse, septante / huitante / nonante). "
					. "2 à 4 phrases courtes, mots de tous les jours, ton naturel et direct. Pas de liste, pas de symboles, pas d'emoji, pas de jargon. Cite au besoin un mot de sa réponse. Tu peux dire quel point il a eu ou lequel il lui manque. "
					. "Ça ne doit pas sonner comme une machine : pas de formules toutes faites (« Excellent travail », « Bravo pour », « N'hésite pas à », « Continue ainsi », « Bonne réponse globale », « Il est important de »), pas de parler de « l'élève » ni de « la réponse de l'élève ». "
					. 'Exemple du ton attendu : « {exemple} ».',
			),
			'generation_rules' => array(
				'group'   => 'generation',
				'title'   => __( 'Création — règles pour rédiger les questions', 'logieval-secure' ),
				'help'    => __( 'Appliqué à chaque génération de questions. {forme} = tutoiement ou vouvoiement choisi au moment de générer.', 'logieval-secure' ),
				'vars'    => array( '{forme}' ),
				'default' => "- Chaque question porte sur une notion différente et importante de la théorie ; varie les parties de la théorie couvertes.\n"
					. "- Français simple, clair et usuel en Suisse romande ; phrases courtes ; {forme}.\n"
					. "- La consigne (\"prompt\") dit exactement ce qui est attendu (« Cite deux… », « Explique pourquoi… »), pour qu'une correction soit simple et sans discussion.\n"
					. "- \"context\" : une mise en situation courte seulement si elle aide (sinon vide) : un contexte concret de l'entreprise (ex. « Le camion arrive au quai 2 pour livrer trois palettes… »).\n"
					. "- \"points\" : entre 1 et 4, par pas de 0.5, selon la difficulté.\n"
					. "- \"criteria\" : 1 à 4 critères observables, chacun avec ses points ; la somme des critères = \"points\". Chaque critère reprend l'élément précis attendu (ex. « 1 pt pays cité : Panama, Libéria ou îles Marshall » plutôt que « 1 pt pays cité »), pour que la correction soit fiable.\n"
					. "- \"expected_answer\" : la réponse attendue complète, telle qu'un enseignant l'écrirait dans son corrigé.\n"
					. "- \"source\" : le passage ou le titre de la théorie qui justifie la réponse (court).\n"
					. "- N'invente rien qui ne soit pas dans la théorie.",
			),
			'generation_qcm'   => array(
				'group'   => 'generation',
				'title'   => __( 'Création — QCM : les mauvaises réponses (distracteurs)', 'logieval-secure' ),
				'help'    => __( 'Repris de votre méthode « étude de cas → tableau QCM ». Ajouté quand des QCM sont demandés.', 'logieval-secure' ),
				'vars'    => array(),
				'default' => "QCM — les fausses propositions sont de vrais distracteurs crédibles : on ne doit pas pouvoir trouver la bonne réponse par sa seule forme.\n"
					. "1. Même longueur que la bonne réponse (±10 % en nombre de mots, même nombre de phrases).\n"
					. "2. Même structure de phrase : si la bonne réponse commence par « Il faut… », les distracteurs aussi ; si elle contient une explication (« car… »), les distracteurs aussi.\n"
					. "3. Même registre : si la bonne réponse cite des termes techniques précis (twistlocks, RPLP, HACCP, TEU…), les distracteurs citent aussi des termes plausibles du même domaine.\n"
					. "4. Une seule variation factuelle par distracteur (un nombre, une valeur, une notion, un sigle) : ne pas changer plusieurs choses à la fois.\n"
					. "5. Utiliser de préférence les valeurs concurrentes citées ailleurs dans la théorie (ex. 1,0 g et 3,0 g si la bonne réponse est 1,5 g).\n"
					. "6. Calcul : même raisonnement, seules les valeurs ou un opérateur changent.\n"
					. "7. Question fermée (oui/non) : un distracteur avec la réponse inverse et une justification inverse plausible, un autre avec la même réponse mais une justification fausse plausible.\n"
					. "Jamais : distracteur absurde ou caricatural, beaucoup plus court ou plus long, terme ou sigle inventé, deux distracteurs identiques ou trop proches, « toutes les réponses » ou « aucune ».",
			),
		);
	}

	/** Textes personnalisés de l'enseignant (seulement ceux qui diffèrent de l'origine). */
	public static function ai_prompt_custom( int $user_id ): array {
		$saved = get_user_meta( $user_id, 'logieval_secure_ai_prompts', true );
		return is_array( $saved ) ? $saved : array();
	}

	/** Texte d'une consigne (personnalisé sinon d'origine), variables remplacées. */
	public static function ai_prompt( string $key, array $vars = array(), int $user_id = 0 ): string {
		$blocks  = self::ai_prompt_blocks();
		$user_id = $user_id ?: get_current_user_id();
		$custom  = self::ai_prompt_custom( $user_id );
		$text    = isset( $custom[ $key ] ) && '' !== trim( (string) $custom[ $key ] ) ? (string) $custom[ $key ] : (string) ( $blocks[ $key ]['default'] ?? '' );
		return strtr( trim( $text ), $vars );
	}

	private static function ai_prompt_norm( string $s ): string {
		return trim( preg_replace( '/\s+/u', ' ', str_replace( "\r", '', $s ) ) );
	}

	/** Enregistrement depuis le formulaire « Ma pré-correction IA ». */
	public static function save_ai_prompts_from_post( int $user_id ): void {
		if ( ! isset( $_POST['ai_prompts'] ) || ! is_array( $_POST['ai_prompts'] ) ) {
			return;
		}
		$posted = (array) wp_unslash( $_POST['ai_prompts'] );
		$custom = self::ai_prompt_custom( $user_id );
		foreach ( self::ai_prompt_blocks() as $key => $block ) {
			if ( ! array_key_exists( $key, $posted ) ) {
				continue;
			}
			$text = sanitize_textarea_field( (string) $posted[ $key ] );
			// Identique au texte d'origine (ou vide) : on ne garde rien, les améliorations futures s'appliqueront.
			if ( '' === trim( $text ) || self::ai_prompt_norm( $text ) === self::ai_prompt_norm( (string) $block['default'] ) ) {
				unset( $custom[ $key ] );
			} else {
				$custom[ $key ] = $text;
			}
		}
		if ( $custom ) {
			update_user_meta( $user_id, 'logieval_secure_ai_prompts', $custom );
		} else {
			delete_user_meta( $user_id, 'logieval_secure_ai_prompts' );
		}
	}

	/** Zone « Mes consignes pour l'IA » dans les réglages. */
	private static function render_ai_prompts_panel( string $only = '' ): void {
		$user_id = get_current_user_id();
		$custom  = self::ai_prompt_custom( $user_id );
		$groups  = array(
			'grading'    => __( '✅ Correction', 'logieval-secure' ),
			'generation' => __( '✨ Création d’évaluations', 'logieval-secure' ),
		);
		if ( '' !== $only && isset( $groups[ $only ] ) ) {
			$groups = array( $only => $groups[ $only ] ); // 1.35.1 : un groupe par sous-menu des réglages
		}
		?>
		<fieldset class="les-ai-learning les-ai-prompts" data-les-ai-prompts>
			<legend><?php echo esc_html( '' === $only ? __( 'Mes consignes pour l’IA', 'logieval-secure' ) : ( 'grading' === $only ? __( 'Mes consignes pour l’IA — correction', 'logieval-secure' ) : __( 'Mes consignes pour l’IA — création', 'logieval-secure' ) ) ); ?></legend>
			<p class="description"><?php esc_html_e( 'Ce sont les consignes que LogiExam donne à l’IA. Vous pouvez les modifier, les compléter ou les raccourcir à tout moment ; « Rétablir le texte d’origine » revient à la version de LogiExam. Le format technique de la réponse (JSON, critère par critère) reste géré par LogiExam pour que la correction et la création fonctionnent toujours.', 'logieval-secure' ); ?></p>
			<?php foreach ( $groups as $group => $group_label ) : ?>
				<details class="les-ai-prompt-group" <?php echo 'grading' === $group || '' !== $only ? 'open' : ''; ?>>
					<summary><strong><?php echo esc_html( $group_label ); ?></strong></summary>
					<?php foreach ( self::ai_prompt_blocks() as $key => $block ) : if ( $block['group'] !== $group ) { continue; } $is_custom = isset( $custom[ $key ] ); $value = $is_custom ? (string) $custom[ $key ] : (string) $block['default']; ?>
						<div class="les-ai-prompt" data-les-ai-prompt="<?php echo esc_attr( $key ); ?>">
							<label for="les-ai-prompt-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $block['title'] ); ?></strong>
								<span class="les-ai-prompt-state<?php echo $is_custom ? ' is-custom' : ''; ?>"><?php echo $is_custom ? esc_html__( 'personnalisé', 'logieval-secure' ) : esc_html__( 'texte d’origine', 'logieval-secure' ); ?></span></label>
							<p class="description"><?php echo esc_html( $block['help'] ); ?></p>
							<textarea id="les-ai-prompt-<?php echo esc_attr( $key ); ?>" name="ai_prompts[<?php echo esc_attr( $key ); ?>]" rows="<?php echo esc_attr( (string) min( 14, max( 5, (int) ceil( mb_strlen( $value ) / 110 ) + substr_count( $value, "\n" ) ) ) ); ?>" data-default="<?php echo esc_attr( (string) $block['default'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
							<button type="button" class="button button-small les-button-secondary" data-les-ai-prompt-reset><?php esc_html_e( 'Rétablir le texte d’origine', 'logieval-secure' ); ?></button>
						</div>
					<?php endforeach; ?>
				</details>
			<?php endforeach; ?>
			<script>
			(function () {
				var box = document.currentScript.closest('[data-les-ai-prompts]');
				box.addEventListener('click', function (e) {
					var b = e.target.closest('[data-les-ai-prompt-reset]');
					if (!b) { return; }
					var wrap = b.closest('[data-les-ai-prompt]');
					var area = wrap.querySelector('textarea');
					if (area.value !== area.dataset.default && !window.confirm('Remplacer votre texte par le texte d’origine de LogiExam ?')) { return; }
					area.value = area.dataset.default;
					var st = wrap.querySelector('.les-ai-prompt-state'); st.textContent = 'texte d’origine (à enregistrer)'; st.classList.remove('is-custom');
				});
			})();
			</script>
		</fieldset>
		<?php
	}
}
