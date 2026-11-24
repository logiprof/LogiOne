<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Shortcodes {
	private static $assets_loaded = false;

	public static function init(): void {
		add_shortcode( 'logiexam_student', array( __CLASS__, 'student' ) );
		add_shortcode( 'logiexam_teacher', array( __CLASS__, 'teacher' ) );
		add_shortcode( 'logieval_student', array( __CLASS__, 'student' ) );
		add_shortcode( 'logieval_teacher', array( __CLASS__, 'teacher' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_style( 'logieval-secure', LOGIEVAL_SECURE_URL . 'assets/logieval-secure.css', array(), LOGIEVAL_SECURE_ASSETS_VERSION );
		wp_register_script( 'logieval-secure', LOGIEVAL_SECURE_URL . 'assets/logieval-secure.js', array(), LOGIEVAL_SECURE_ASSETS_VERSION, true );
	}

	private static function enqueue_assets(): void {
		if ( self::$assets_loaded ) {
			return;
		}
		// Thèmes en blocs : le contenu est rendu avant le hook wp_enqueue_scripts, on enregistre donc les fichiers ici.
		if ( ! wp_script_is( 'logieval-secure', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'logieval-secure' );
		wp_enqueue_script( 'logieval-secure' );
		wp_localize_script(
			'logieval-secure',
			'LogiEvalSecure',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( LogiEval_Secure_Evaluations::NONCE_ACTION ),
				// Nombre de sorties qui termine l'évaluation (réglage) : affiché tel quel aux élèves.
				'maxExits' => LogiEval_Secure_Evaluations::max_exits(),
				// Délai de grâce (secondes) pour revenir en plein écran sans blocage.
				'sebDownload'     => LogiEval_Secure_SEB::DOWNLOAD_URL,
				'unlockRequestSeconds' => LogiEval_Secure_Evaluations::unlock_request_seconds(),
				'fullscreenGrace' => max( 0, min( 60, (int) LogiEval_Secure_Evaluations::get_setting( 'fullscreen_grace_seconds', 5 ) ) ),
				'i18n'    => array(
					'saved'     => __( 'Réponse enregistrée.', 'logieval-secure' ),
					'submitted' => __( 'Votre évaluation a bien été envoyée et sera corrigée par votre enseignant.', 'logieval-secure' ),
					'error'     => __( 'Une erreur est survenue.', 'logieval-secure' ),
				),
			)
		);
		self::$assets_loaded = true;
	}

	/**
	 * LogiOne 1.30 : écran à part, avant les règles, qui explique clairement ce qui se passe en cas de sortie
	 * (compte à rebours, blocage, délai pour demander le déblocage, clôture avec la note 1).
	 */
	private static function exit_rules_screen(): string {
		$grace  = max( 0, min( 60, (int) LogiEval_Secure_Evaluations::get_setting( 'fullscreen_grace_seconds', 5 ) ) );
		$unlock = LogiEval_Secure_Evaluations::unlock_request_seconds();
		$max    = LogiEval_Secure_Evaluations::max_exits();
		$steps  = array();
		$steps[] = $grace > 0
			? sprintf( __( 'Si vous quittez le plein écran ou la fenêtre de l’évaluation, un grand compte à rebours rouge apparaît : vous avez %d secondes pour revenir. Si vous revenez à temps, rien ne se passe (3 fois au maximum).', 'logieval-secure' ), $grace )
			: __( 'Si vous quittez le plein écran ou la fenêtre de l’évaluation, elle est bloquée immédiatement.', 'logieval-secure' );
		$steps[] = __( 'Si vous ne revenez pas à temps, votre évaluation est BLOQUÉE : vous ne pouvez plus répondre.', 'logieval-secure' );
		if ( $unlock > 0 ) {
			$steps[] = sprintf( __( 'Un deuxième compte à rebours rouge apparaît alors : vous avez %d secondes pour cliquer sur « Demander un déblocage ». Votre enseignant décide ensuite s’il vous laisse reprendre.', 'logieval-secure' ), $unlock );
			$steps[] = __( 'Si vous ne demandez pas le déblocage à temps, votre évaluation est clôturée DÉFINITIVEMENT avec la note de 1.', 'logieval-secure' );
		} else {
			$steps[] = __( 'Cliquez alors sur « Demander un déblocage » : votre enseignant décide s’il vous laisse reprendre.', 'logieval-secure' );
		}
		$steps[] = sprintf( __( 'À la %de sortie, l’évaluation est clôturée définitivement avec la note de 1.', 'logieval-secure' ), $max );
		$steps[] = __( 'Fermer ou recharger la page compte aussi comme une sortie.', 'logieval-secure' );
		$html  = '<div class="les-exit-rules" data-les-exit-rules>';
		$html .= '<div class="les-exit-rules-title">⚠ ' . esc_html__( 'À lire avant de commencer : sortir de l’évaluation', 'logieval-secure' ) . '</div>';
		$html .= '<ol class="les-exit-rules-steps">';
		foreach ( $steps as $step ) {
			$html .= '<li>' . esc_html( $step ) . '</li>';
		}
		$html .= '</ol>';
		$html .= '<label class="les-check les-exit-rules-confirm"><input type="checkbox" data-les-exit-rules-ack> ' . esc_html__( 'J’ai compris : si je sors et que je ne demande pas le déblocage à temps, j’aurai la note de 1.', 'logieval-secure' ) . '</label>';
		$html .= '<div class="les-actions"><button type="button" data-les-exit-rules-ok disabled>' . esc_html__( 'J’ai compris, continuer', 'logieval-secure' ) . '</button></div>';
		$html .= '</div>';
		return $html;
	}

	private static function frontend_asset_fallback_markup(): string {
		if ( is_admin() ) {
			return '';
		}
		return '<link rel="stylesheet" id="logieval-secure-shortcode-css" href="' . esc_url( LOGIEVAL_SECURE_URL . 'assets/logieval-secure.css?ver=' . rawurlencode( LOGIEVAL_SECURE_ASSETS_VERSION ) ) . '" media="all">';
	}

	public static function student(): string {
		self::enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() ?: home_url( add_query_arg( array() ) ) );
			return self::frontend_asset_fallback_markup() . '<div class="logieval-secure"><div class="les-empty-state les-warning"><h2>' . esc_html__( 'Connexion requise', 'logieval-secure' ) . '</h2><p>' . esc_html__( 'Vous devez d’abord vous connecter avec votre compte utilisateur. Le code personnel de l’évaluation pourra être saisi uniquement après connexion.', 'logieval-secure' ) . '</p><div class="les-actions"><a class="button" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Se connecter', 'logieval-secure' ) . '</a></div></div></div>';
		}

		// Adresse de la page élève : sert de page de départ dans la configuration Safe Exam Browser.
		$here = get_permalink();
		if ( $here && get_option( 'logiexam_student_page_url' ) !== $here ) {
			update_option( 'logiexam_student_page_url', $here, false );
		}
		// 1.52.1 : corrigé d'un test formatif (autocorrection), à la place de l'écran d'accès.
		if ( isset( $_GET['les_corrige'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- vérifié dans la méthode.
			$key = LogiEval_Secure_Evaluations::render_student_formative_copies();
			if ( false !== strpos( $key, 'les-answer-key' ) ) {
				return self::frontend_asset_fallback_markup() . '<div class="logieval-secure les-student">' . $key . '</div>';
			}
		}
		ob_start();
		?>
		<?php echo self::frontend_asset_fallback_markup(); ?>
		<div class="logieval-secure les-student" data-les-student>
			<?php echo LogiEval_Secure_SEB::student_banner(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans la méthode. ?>
			<div class="les-access les-screen">
				<div class="les-access-intro">
					<span class="les-kicker"><?php esc_html_e( 'LogiExam', 'logieval-secure' ); ?></span>
					<h2><?php esc_html_e( 'Évaluation sécurisée', 'logieval-secure' ); ?></h2>
					<p><?php esc_html_e( "Connectez-vous avec votre compte WordPress, puis saisissez le code personnel remis par votre enseignant. Ce code est unique, lié à votre compte et à votre groupe classe.", 'logieval-secure' ); ?></p>
					<ul class="les-checklist">
						<li><?php esc_html_e( 'Compte WordPress connecté', 'logieval-secure' ); ?></li>
						<li><?php esc_html_e( 'Groupe classe Sensei autorisé', 'logieval-secure' ); ?></li>
						<li><?php esc_html_e( 'Code unique personnel généré par l’enseignant', 'logieval-secure' ); ?></li>
						<li><?php esc_html_e( 'Session sécurisée et journalisée', 'logieval-secure' ); ?></li>
					</ul>
				</div>
				<form class="les-access-card" data-les-start-form>
					<label><?php esc_html_e( 'Code personnel', 'logieval-secure' ); ?><input name="code" autocomplete="one-time-code" inputmode="text" required placeholder="EXAM-1234"></label>
					<button type="submit"><?php esc_html_e( "Entrer dans l'évaluation", 'logieval-secure' ); ?></button>
					<p class="les-message" data-les-message></p>
				</form>
					<?php echo LogiEval_Secure_Evaluations::render_student_formative_copies(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans la méthode. ?>
					<div class="les-keys-warning is-compact les-keys-wide" role="note">
						<p class="les-keys-title"><span class="les-keys-icon" aria-hidden="true">⚠</span><?php esc_html_e( 'Attention aux touches du clavier !', 'logieval-secure' ); ?></p>
						<p class="les-keys-lead"><?php echo wp_kses( __( '<strong>Ne touchez jamais la touche Windows</strong> pendant l’évaluation, ni <kbd>Échap</kbd> ou <kbd>Alt</kbd> + <kbd>Tab</kbd> : une seule mauvaise touche vous fait sortir de l’écran et bloque l’évaluation. Regardez bien sur quelle touche vous appuyez.', 'logieval-secure' ), array( 'strong' => array(), 'kbd' => array() ) ); ?></p>
					</div>
			</div>
			<div class="les-exam les-screen" data-les-exam hidden>
				<header class="les-exam-header">
					<div>
						<span class="les-kicker"><?php esc_html_e( 'Mode sécurisé actif', 'logieval-secure' ); ?></span>
						<h2 data-les-title></h2>
						<p data-les-instructions></p>
					</div>
					<div class="les-exam-meta">
						<div class="les-clock" data-les-clock title="<?php esc_attr_e( 'Heure actuelle', 'logieval-secure' ); ?>"><span class="les-clock-label"><?php esc_html_e( 'Heure', 'logieval-secure' ); ?></span> <strong data-les-clock-time>--:--</strong></div>
						<div class="les-timer" data-les-timer>--:--</div>
						<button type="button" class="les-timer-toggle" data-les-timer-toggle aria-pressed="false" hidden><?php esc_html_e( 'Masquer le minuteur', 'logieval-secure' ); ?></button>
						<span data-les-question-count></span>
					</div>
				</header>
				<div class="les-timer-toast" data-les-timer-toast role="status" aria-live="polite" hidden><span data-les-timer-toast-text></span><button type="button" data-les-timer-toast-close><?php esc_html_e( 'OK', 'logieval-secure' ); ?></button></div>
				<div class="les-company-context" data-les-company hidden></div>
				<div class="les-exam-intro" data-les-exam-intro>
					<div class="les-secure-start" data-les-security-start>
						<?php echo self::exit_rules_screen(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans la méthode. ?>
						<div class="les-security-ack-step" data-les-security-ack-step hidden>
						<div class="les-security-notice" data-les-security-notice hidden></div>
						<div class="les-empty-state">
							<p class="les-privacy-note"><?php esc_html_e( 'Pendant l’évaluation, sont enregistrés et signalés à l’enseignant : les sorties de la fenêtre ou du plein écran, l’ouverture d’un panneau ou d’un outil d’aide (assistant IA, extension) le texte qui apparaît dans une réponse sans être tapé au clavier, et les tentatives de capture d’écran (interdites). Le contenu de vos frappes n’est pas enregistré.', 'logieval-secure' ); ?></p>
							<label class="les-check les-security-confirm">
								<input type="checkbox" data-les-security-ack>
								<?php esc_html_e( 'J’ai lu les règles ci-dessus et j’accepte le mode sécurisé.', 'logieval-secure' ); ?>
							</label>
							<div class="les-actions">
								<button type="button" data-les-ack-security disabled><?php esc_html_e( 'Accéder à l’évaluation', 'logieval-secure' ); ?></button>
							</div>
							<p class="les-message" data-les-security-message></p>
						</div>
											</div>
					</div>
					<div class="les-evaluation-start" data-les-evaluation-start hidden>
						<div class="les-story-intro" data-les-story-intro hidden></div>
						<div class="les-empty-state">
							<span class="les-kicker"><?php esc_html_e( 'Introduction', 'logieval-secure' ); ?></span>
							<h2><?php esc_html_e( 'Lisez le contexte, puis commencez', 'logieval-secure' ); ?></h2>
							<p><?php esc_html_e( 'Le chronomètre démarre et le plein écran s’active quand vous cliquez sur le bouton.', 'logieval-secure' ); ?></p>
							<div class="les-actions">
								<button type="button" data-les-begin-questions><?php esc_html_e( 'Commencer l’évaluation', 'logieval-secure' ); ?></button>
							</div>
							<p class="les-message" data-les-begin-message></p>
						</div>
					</div>
				</div>
				<div data-les-questions-area hidden>
					<div class="les-progress-wrap" aria-label="<?php esc_attr_e( 'Progression des réponses', 'logieval-secure' ); ?>">
						<div class="les-progress-meta"><strong><?php esc_html_e( 'Progression', 'logieval-secure' ); ?></strong><span data-les-progress-text>0/0 répondues</span></div>
						<div class="les-progress-track"><span data-les-progress></span></div>
						<div class="les-question-nav" data-les-question-nav></div>
					</div>
					<form data-les-answer-form></form>
					<div class="les-actions">
						<button type="button" data-les-submit><?php esc_html_e( 'Soumettre', 'logieval-secure' ); ?></button>
					</div>
				</div>
				<div class="les-fullscreen-guard" data-les-fullscreen-guard hidden>
					<div class="les-empty-state les-warning">
						<h2><?php esc_html_e( 'Plein écran requis', 'logieval-secure' ); ?></h2>
						<p><?php esc_html_e( 'L’évaluation doit se dérouler en plein écran. Cliquez sur le bouton ci-dessous pour activer le plein écran et afficher les questions.', 'logieval-secure' ); ?></p>
						<div class="les-actions">
							<button type="button" data-les-return-fullscreen><?php esc_html_e( 'Réactiver le plein écran', 'logieval-secure' ); ?></button>
						</div>
						<p class="les-message" data-les-fullscreen-message></p>
					</div>
				</div>
			</div>
			<div class="les-post-submit-lock" data-les-lock data-les-lock-mode="" hidden>
					<div class="les-empty-state les-warning">
						<h2><?php esc_html_e( 'Évaluation envoyée', 'logieval-secure' ); ?></h2>
						<p><?php esc_html_e( 'Votre copie est envoyée. Restez sur cette interface pendant 5 minutes. Si vous quittez l’écran maintenant, l’évaluation sera clôturée définitivement sans blocage, mais vous ne pourrez plus la reprendre.', 'logieval-secure' ); ?></p>
					<p><strong data-les-lock-timer>05:00</strong></p>
					<div class="les-actions">
						<button type="button" data-les-reopen><?php esc_html_e( 'Rouvrir mon évaluation', 'logieval-secure' ); ?></button>
					</div>
					<p class="les-message" data-les-lock-message></p>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function teacher(): string {
		self::enqueue_assets();
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() ) {
			return self::frontend_asset_fallback_markup() . '<div class="logieval-secure"><div class="les-empty-state les-warning"><h2>' . esc_html__( 'Accès réservé', 'logieval-secure' ) . '</h2><p>' . esc_html__( 'Cette interface est réservée aux administrateurs et au rôle Enseignant Logistique.', 'logieval-secure' ) . '</p></div></div>';
		}

		if ( current_user_can( 'upload_files' ) && function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media(); // médiathèque aussi dans l'espace enseignant du site
		}
		return self::frontend_asset_fallback_markup() . LogiEval_Secure_Evaluations::render_frontend_teacher_app();
	}
}
