<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LOGIEVAL_Public {

    public function __construct() {
        // Registered on init: block themes render the shortcode before wp_enqueue_scripts.
        add_action( 'init',                           [ __CLASS__, 'register_assets' ] );
        add_action( 'wp_enqueue_scripts',             [ $this, 'enqueue_assets' ] );
        add_action( 'template_redirect',              [ $this, 'maybe_handle_direct_document' ], 0 );
        // Connexion par identifiant traitée AVANT l'affichage de la page : avec un thème classique (Divi…),
        // le cookie ne pouvait pas être posé pendant l'affichage, et l'enseignant sans compte devait
        // ressaisir son identifiant à chaque clic.
        add_action( 'template_redirect',              [ $this, 'maybe_handle_auth' ], 1 );
        add_shortcode( 'logieval_login',              [ $this, 'shortcode_login' ] );
        add_action( 'wp_ajax_nopriv_logieval_public', [ $this, 'handle_ajax' ] );
        add_action( 'wp_ajax_logieval_public',        [ $this, 'handle_ajax' ] );
        $export_handler = function() {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
            if (!self::verify_nonce($nonce)) {
                wp_die('Lien expiré. Rechargez la page et réessayez.');
            }
            $id = intval($_POST['eval_id'] ?? 0);
            $eleve_id = intval($_POST['eleve_id'] ?? 0);
            if (!current_user_can( 'lp_eval_admin' ) && (!LOGIEVAL_Auth::is_logged_in() || !$this->current_teacher_can_see_results($id))) {
                wp_die('Accès refusé.');
            }
            if ($id) LOGIEVAL_Exporter::export_word($id, $eleve_id);
        };
        add_action( 'wp_ajax_logieval_export_front',        $export_handler );
        add_action( 'wp_ajax_nopriv_logieval_export_front', $export_handler ); // enseignants sans compte sur le site
        add_action( 'wp_ajax_logieval_print_front', [ $this, 'handle_print' ] );
        add_action( 'wp_ajax_nopriv_logieval_print_front', [ $this, 'handle_print' ] );
    }

    /** Résultat de la connexion / déconnexion traitée avant l'affichage : 'ok', 'fail', 'rate', 'logout'. */
    private static $auth_state = null;

    public function maybe_handle_auth(): void {
        if ( is_admin() ) return;
        if ( isset( $_POST['logieval_login_submit'] ) ) {
            $identifier = sanitize_text_field( wp_unslash( $_POST['logieval_identifier'] ?? '' ) );
            $shared     = ! empty( $_POST['logieval_shared'] );
            if ( LOGIEVAL_Auth::login( $identifier, $shared ) ) {
                self::$auth_state = 'ok';
                // Redirection après connexion : actualiser la page ne renvoie pas le formulaire.
                if ( ! headers_sent() ) {
                    nocache_headers();
                    wp_safe_redirect( remove_query_arg( 'logieval_logout', add_query_arg( [] ) ), 303 );
                    exit;
                }
            } else {
                self::$auth_state = LOGIEVAL_Auth::is_rate_limited() ? 'rate' : 'fail';
            }
            nocache_headers();
        } elseif ( isset( $_GET['logieval_logout'] ) ) {
            LOGIEVAL_Auth::logout();
            self::$auth_state = 'logout';
            nocache_headers();
        }
    }

    /* Nonce bound to the LogiEval teacher (WordPress nonces of logged-out visitors are
       identical for everyone, so they gave no CSRF protection on their own). */
    private static function nonce_action(): string {
        return 'logieval_public_' . LOGIEVAL_Auth::current_enseignant_id();
    }

    public static function create_nonce(): string {
        return wp_create_nonce( self::nonce_action() );
    }

    private static function verify_nonce( string $nonce ): bool {
        return (bool) wp_verify_nonce( $nonce, self::nonce_action() );
    }

    /** « dans 4 jours », « aujourd'hui », « en retard de 2 jours ». */
    private static function days_label( string $date ): string {
        $today = new DateTime( current_time( 'Y-m-d' ) );
        $due   = DateTime::createFromFormat( '!Y-m-d', $date );
        if ( ! $due ) return '';
        $d = (int) $today->diff( $due )->format( '%r%a' );
        if ( $d === 0 ) return "aujourd'hui";
        if ( $d === 1 ) return 'demain';
        if ( $d > 1 ) return "dans {$d} jours";
        return $d === -1 ? 'en retard d’un jour' : 'en retard de ' . abs( $d ) . ' jours';
    }

    private static function days_left( string $date ): int {
        $today = new DateTime( current_time( 'Y-m-d' ) );
        $due   = DateTime::createFromFormat( '!Y-m-d', $date );
        return $due ? (int) $today->diff( $due )->format( '%r%a' ) : 0;
    }

    private static function valid_date( string $date ): bool {
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    /** Vrai quand LogiEval est ouvert dans le portail LogiOne. */
    private static function in_logione(): bool {
        return class_exists('LogiPilot_Portal') && method_exists('LogiPilot_Portal', 'current_tool') && LogiPilot_Portal::current_tool() !== '';
    }

    private static function portal_url(): string {
        if ( self::in_logione() ) {
            return get_permalink() ?: home_url('/'); // adresse du portail, outil LogiEval ouvert
        }
        $page_id = (int) get_option('logieval_login_page', 0);
        $url = $page_id ? get_permalink($page_id) : get_permalink();
        return $url ?: home_url('/');
    }

    /** Lien vers une vue de LogiEval sur la page courante (page LogiEval, ou portail LogiOne avec l'outil ouvert). */
    private static function page_link( array $args ): string {
        return add_query_arg( $args, self::portal_url() );
    }

    private static function logout_url(): string {
        return add_query_arg('logieval_logout', 1, self::portal_url());
    }

    /* ── Bandeau LogiOne : anthracite, pleine largeur ──────────────── */
    private static function hero_art(): string {
        return '<svg class="lge-iso" viewBox="24 -60 252 290" aria-hidden="true" focusable="false"><g class="c" style="--i:0"><polygon class="t" points="150.0,86.0 189.84,109.0 150.0,132.0 110.16,109.0"/><polygon class="l" points="110.16,109.0 150.0,132.0 150.0,178.0 110.16,155.0"/><polygon class="r" points="189.84,109.0 150.0,132.0 150.0,178.0 189.84,155.0"/></g><g class="c" style="--i:6"><polygon class="t" points="150.0,40.0 189.84,63.0 150.0,86.0 110.16,63.0"/><polygon class="l" points="110.16,63.0 150.0,86.0 150.0,132.0 110.16,109.0"/><polygon class="r" points="189.84,63.0 150.0,86.0 150.0,132.0 189.84,109.0"/></g><g class="c mark" style="--i:9"><polygon class="t" points="150.0,-6.0 189.84,17.0 150.0,40.0 110.16,17.0"/><polygon class="l" points="110.16,17.0 150.0,40.0 150.0,86.0 110.16,63.0"/><polygon class="r" points="189.84,17.0 150.0,40.0 150.0,86.0 189.84,63.0"/></g><g class="c" style="--i:1"><polygon class="t" points="189.84,109.0 229.67,132.0 189.84,155.0 150.0,132.0"/><polygon class="l" points="150.0,132.0 189.84,155.0 189.84,201.0 150.0,178.0"/><polygon class="r" points="229.67,132.0 189.84,155.0 189.84,201.0 229.67,178.0"/></g><g class="c" style="--i:2"><polygon class="t" points="110.16,109.0 150.0,132.0 110.16,155.0 70.33,132.0"/><polygon class="l" points="70.33,132.0 110.16,155.0 110.16,201.0 70.33,178.0"/><polygon class="r" points="150.0,132.0 110.16,155.0 110.16,201.0 150.0,178.0"/></g><g class="c" style="--i:7"><polygon class="t" points="189.84,63.0 229.67,86.0 189.84,109.0 150.0,86.0"/><polygon class="l" points="150.0,86.0 189.84,109.0 189.84,155.0 150.0,132.0"/><polygon class="r" points="229.67,86.0 189.84,109.0 189.84,155.0 229.67,132.0"/></g><g class="c" style="--i:8"><polygon class="t" points="110.16,63.0 150.0,86.0 110.16,109.0 70.33,86.0"/><polygon class="l" points="70.33,86.0 110.16,109.0 110.16,155.0 70.33,132.0"/><polygon class="r" points="150.0,86.0 110.16,109.0 110.16,155.0 150.0,132.0"/></g><g class="c" style="--i:3"><polygon class="t" points="229.67,132.0 269.51,155.0 229.67,178.0 189.84,155.0"/><polygon class="l" points="189.84,155.0 229.67,178.0 229.67,224.0 189.84,201.0"/><polygon class="r" points="269.51,155.0 229.67,178.0 229.67,224.0 269.51,201.0"/></g><g class="c" style="--i:4"><polygon class="t" points="150.0,132.0 189.84,155.0 150.0,178.0 110.16,155.0"/><polygon class="l" points="110.16,155.0 150.0,178.0 150.0,224.0 110.16,201.0"/><polygon class="r" points="189.84,155.0 150.0,178.0 150.0,224.0 189.84,201.0"/></g><g class="c" style="--i:5"><polygon class="t" points="70.33,132.0 110.16,155.0 70.33,178.0 30.49,155.0"/><polygon class="l" points="30.49,155.0 70.33,178.0 70.33,224.0 30.49,201.0"/><polygon class="r" points="110.16,155.0 70.33,178.0 70.33,224.0 110.16,201.0"/></g><g class="c star" style="--i:10"><polygon points="150.00,-54.00 155.00,-40.88 169.02,-40.18 158.08,-31.37 161.76,-17.82 150.00,-25.50 138.24,-17.82 141.92,-31.37 130.98,-40.18 145.00,-40.88" fill="#f5c400"/></g></svg>';
    }

    /**
     * $o : title, kicker, lead (texte), meta (HTML déjà échappé), back (bool),
     *      actions (HTML déjà échappé), user (tableau enseignant ou null), art (bool)
     */
    private static function hero( array $o ): string {
        $user = $o['user'] ?? null;
        $h  = '<header class="lge-hero">';
        $h .= '<div class="lge-hero-top">';
        $h .= '<a class="lge-brand" href="' . esc_url(self::portal_url()) . '"><img class="lge-logo" src="' . esc_url(LOGIEVAL_URL . 'assets/logieval-logo-blanc.svg') . '" alt="LogiEval by LogiProf" width="300" height="80" decoding="async"></a>';
        if ( $user && LOGIEVAL_Auth::is_linked() ) {
            $h .= '<div class="lge-user"><span>' . esc_html(trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))) . ' · reconnu par votre compte</span></div>';
        } elseif ( $user ) {
            $h .= '<div class="lge-user"><span>Identifiant <code>' . esc_html($user['identifiant_unique']) . '</code></span>'
                . '<a href="' . esc_url(self::logout_url()) . '">Se déconnecter</a></div>';
        }
        $h .= '</div>';
        if ( ! empty($o['back']) || ! empty($o['actions']) ) {
            $h .= '<div class="lge-toolbar">';
            if ( ! empty($o['back']) ) {
                $h .= '<a class="lge-back" href="' . esc_url(self::portal_url()) . '"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>Retour au portail</a>';
            }
            $h .= ! empty($o['actions']) ? '<div class="lge-actions">' . $o['actions'] . '</div>' : '';
            $h .= '</div>';
        }
        $h .= '<div class="lge-hero-main' . ( ! empty($o['art']) ? ' has-art' : '' ) . '"><div class="lge-hero-text">';
        $h .= ! empty($o['kicker']) ? '<p class="lge-kicker">' . esc_html($o['kicker']) . '</p>' : '';
        $h .= '<h2 class="lge-hello">' . esc_html($o['title'] ?? '') . '</h2>';
        $h .= ! empty($o['lead']) ? '<p class="lge-lead">' . esc_html($o['lead']) . '</p>' : '';
        $h .= $o['meta'] ?? '';
        $h .= '</div>';
        $h .= ! empty($o['art']) ? '<div class="lge-art">' . self::hero_art() . '</div>' : '';
        $h .= '</div></header>';
        return $h;
    }

    public function maybe_handle_direct_document(): void {
        if (is_admin()) return;
        if (isset($_GET['logieval_print'])) {
            $this->handle_print();
        }
        if (isset($_GET['logieval_export'])) {
            $this->handle_front_export();
        }
    }

    /* Assets are only loaded on pages that use LogiEval (they used to be added to every page of the site). */
    public static function register_assets(): void {
        wp_register_style( 'logieval-public', LOGIEVAL_URL.'public/css/public.css', [], LOGIEVAL_VERSION );
        wp_register_script( 'logieval-public', LOGIEVAL_URL.'public/js/public.js', ['jquery'], LOGIEVAL_VERSION, true );
    }

    public function enqueue_assets(): void {
        if ( self::page_uses_logieval() ) {
            self::load_assets();
        }
    }

    private static function page_uses_logieval(): bool {
        if ( isset( $_GET['logieval_eval'] ) || isset( $_GET['logieval_new_eval'] ) ) return true;
        $post = get_post();
        if ( $post && has_shortcode( (string) $post->post_content, 'logieval_login' ) ) return true;
        $page_id = (int) get_option( 'logieval_login_page', 0 );
        return $page_id && is_page( $page_id );
    }

    private static function load_assets(): void {
        static $done = false;
        if ( $done ) return;
        $done = true;
        wp_enqueue_style( 'logieval-public' );
        wp_enqueue_script( 'logieval-public' );
        wp_localize_script( 'logieval-public', 'logievalPublic', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => self::create_nonce(),
        ]);
    }

    public function handle_print(): void {
        $eval_id  = intval($_GET['eval_id'] ?? 0);
        $eleve_id = intval($_GET['eleve_id'] ?? 0);
        $print_mode = sanitize_key(wp_unslash($_GET['print_mode'] ?? ''));
        if (!in_array($print_mode, ['', 'students'], true)) $print_mode = '';
        if (!$eval_id) wp_die('Évaluation manquante.');
        $has_report_key = $this->valid_report_key($eval_id);
        if ($has_report_key) {
            $eleve_id = 0;
            $print_mode = '';
        }
        if (!$has_report_key) {
            $nonce = sanitize_text_field(wp_unslash($_GET['nonce'] ?? ''));
            if (!self::verify_nonce($nonce)) {
                wp_die('Lien expiré. Rechargez la page et réessayez.');
            }
            if (!LOGIEVAL_Auth::is_logged_in() && !current_user_can( 'lp_eval_admin' )) {
                wp_die('Session expirée. Veuillez vous reconnecter.');
            }
        } elseif (!LOGIEVAL_Auth::is_logged_in() && !current_user_can( 'lp_eval_admin' )) {
            wp_die('Connectez-vous avec votre identifiant enseignant pour ouvrir ce rapport.');
        }
        // Lien du rapport envoyé par e-mail : enseignants de l'évaluation ; sinon : maître de classe seulement.
        $allowed = $has_report_key ? $this->current_teacher_can_access_eval($eval_id) : $this->current_teacher_can_see_results($eval_id);
        if (!$allowed) {
            wp_die('Accès refusé : l’impression des résultats est réservée au maître de classe.');
        }
        $this->render_print_document($eval_id, $eleve_id, $print_mode);
    }

    public function handle_front_export(): void {
        $nonce = sanitize_text_field(wp_unslash($_GET['nonce'] ?? ''));
        if (!self::verify_nonce($nonce)) {
            wp_die('Lien expiré. Rechargez la page et réessayez.');
        }
        if (!LOGIEVAL_Auth::is_logged_in() && !current_user_can( 'lp_eval_admin' )) {
            wp_die('Session expirée. Veuillez vous reconnecter.');
        }
        $eval_id  = intval($_GET['eval_id'] ?? 0);
        $eleve_id = intval($_GET['eleve_id'] ?? 0);
        if (!$eval_id || !$this->current_teacher_can_see_results($eval_id)) {
            wp_die('Accès refusé : l’export des résultats est réservé au maître de classe.');
        }
        LOGIEVAL_Exporter::export_word($eval_id, $eleve_id);
    }

    /**
     * Notes, absences et export de toute la classe : réservés au maître de classe (et à l'administrateur).
     * Les autres enseignants de l'évaluation ne voient que leurs propres réponses.
     */
    private function current_teacher_can_see_results( int $eval_id, int $ens_id = 0 ): bool {
        if ( current_user_can( 'lp_eval_admin' ) && ! LOGIEVAL_Auth::current_enseignant_id() ) return true;
        if ( ! $ens_id ) $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        if ( ! $eval_id || ! $ens_id ) return current_user_can( 'lp_eval_admin' );
        $eval = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('evaluations'), ['id' => $eval_id] );
        return $eval && ( LOGIEVAL_Auth::is_maitre( $ens_id, (int) $eval['classe_id'] ) || current_user_can( 'lp_eval_admin' ) );
    }

    private function current_teacher_can_access_eval( int $eval_id, int $ens_id = 0 ): bool {
        if ( current_user_can( 'lp_eval_admin' ) ) return true;
        if ( ! $ens_id ) $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        if ( ! $eval_id || ! $ens_id ) return false;
        $t = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id' => $eval_id]);
        if ( ! $eval ) return false;
        if ( LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id']) ) return true;
        return (bool) LOGIEVAL_Database::get_row($t['eval_enseignant'], [
            'evaluation_id' => $eval_id,
            'enseignant_id' => $ens_id,
        ]);
    }

    /**
     * Votes qui comptent dans les décomptes affichés : enseignants encore attribués à l'évaluation,
     * hors objectifs qu'ils ont marqués « non évaluable » (leurs réponses y sont gardées mais mises de côté).
     */
    private static function counted_votes_sql( string $alias = 'r' ): string {
        $t = LOGIEVAL_Database::tables();
        return " AND {$alias}.enseignant_id IN (SELECT ee_c.enseignant_id FROM {$t['eval_enseignant']} ee_c WHERE ee_c.evaluation_id={$alias}.evaluation_id)"
             . " AND NOT EXISTS (SELECT 1 FROM {$t['non_evaluables']} ne_c INNER JOIN {$t['sous_criteres']} sc_c ON sc_c.objectif_id=ne_c.objectif_id"
             . " WHERE ne_c.evaluation_id={$alias}.evaluation_id AND ne_c.enseignant_id={$alias}.enseignant_id AND ne_c.eleve_id={$alias}.eleve_id AND sc_c.id={$alias}.sous_critere_id)";
    }

    private function filter_active_class_enseignants( array $enseignant_ids, int $classe_id ): array {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', $enseignant_ids))));
        if (empty($ids) || !$classe_id) return [];

        $t = LOGIEVAL_Database::tables();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge([$classe_id], $ids);
        $active = $wpdb->get_col($wpdb->prepare(
            "SELECT e.id
             FROM {$t['enseignants']} e
             INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id=e.id
             WHERE ce.classe_id=%d AND e.actif=1 AND e.id IN ($placeholders)",
            ...$params
        )) ?: [];

        return array_values(array_map('intval', $active));
    }

    private function validate_teacher_write_context( int $eval_id, int $ens_id, int $eleve_id = 0, int $sc_id = 0 ): array {
        $t = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id' => $eval_id]);
        if ( ! $eval || $eval['statut'] === 'cloturee' ) {
            return ['ok' => false, 'message' => "Évaluation clôturée ou introuvable."];
        }
        $assignment = LOGIEVAL_Database::get_row($t['eval_enseignant'], ['evaluation_id' => $eval_id, 'enseignant_id' => $ens_id]);
        if ( ! $assignment ) {
            return ['ok' => false, 'message' => "Vous n'êtes pas attribué à cette évaluation."];
        }
        if ( ($assignment['statut'] ?? '') === 'termine' ) {
            return ['ok' => false, 'message' => "Votre évaluation est déjà marquée comme terminée. Réouvrez-la avant de modifier vos réponses."];
        }
        if ( $eleve_id ) {
            $eleve = LOGIEVAL_Database::get_row($t['eleves'], ['id' => $eleve_id]);
            if ( ! $eleve || (int)$eleve['classe_id'] !== (int)$eval['classe_id'] ) {
                return ['ok' => false, 'message' => "Élève non lié à cette évaluation."];
            }
        }
        $sc = null;
        $obj = null;
        if ( $sc_id ) {
            $sc = LOGIEVAL_Database::get_row($t['sous_criteres'], ['id' => $sc_id]);
            $obj = $sc ? LOGIEVAL_Database::get_row($t['objectifs'], ['id' => $sc['objectif_id']]) : null;
            if ( ! $sc || ! $obj || empty($obj['actif']) ) {
                return ['ok' => false, 'message' => "Critère introuvable."];
            }
            if ( ! empty($obj['maitre_seulement']) && ! LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id']) ) {
                return ['ok' => false, 'message' => "Réservé au maître de classe."];
            }
        }
        return ['ok' => true, 'eval' => $eval, 'sc' => $sc, 'obj' => $obj];
    }

    private function print_url( int $eval_id, int $eleve_id = 0, string $print_mode = '' ): string {
        $page_id = (int) get_option('logieval_login_page', 0);
        $base = $page_id ? get_permalink($page_id) : home_url('/');
        $args = [
            'logieval_print' => 1,
            'eval_id' => $eval_id,
            'nonce' => self::create_nonce(),
        ];
        if ($eleve_id > 0) $args['eleve_id'] = $eleve_id;
        if ($print_mode !== '') $args['print_mode'] = $print_mode;
        return add_query_arg($args, $base);
    }

    private function class_report_email_url( int $eval_id ): string {
        $page_id = (int) get_option('logieval_login_page', 0);
        $base = $page_id ? get_permalink($page_id) : home_url('/');
        $key = wp_generate_password(32, false, false);
        set_transient('logieval_class_report_key_'.$eval_id, $key, 7 * DAY_IN_SECONDS);
        return add_query_arg([
            'logieval_print' => 1,
            'eval_id' => $eval_id,
            'report_key' => $key,
        ], $base);
    }

    private function valid_report_key( int $eval_id ): bool {
        $key = sanitize_text_field(wp_unslash($_GET['report_key'] ?? ''));
        if (!$eval_id || $key === '') return false;
        $saved = (string) get_transient('logieval_class_report_key_'.$eval_id);
        if ($saved === '' || !hash_equals($saved, $key)) return false;
        $eval = LOGIEVAL_Database::get_row(LOGIEVAL_Database::t('evaluations'), ['id'=>$eval_id]);
        return $eval && $eval['statut'] === 'cloturee';
    }

    private function export_url( int $eval_id, int $eleve_id = 0 ): string {
        $page_id = (int) get_option('logieval_login_page', 0);
        $base = $page_id ? get_permalink($page_id) : home_url('/');
        $args = [
            'logieval_export' => 1,
            'eval_id' => $eval_id,
            'nonce' => self::create_nonce(),
        ];
        if ($eleve_id > 0) $args['eleve_id'] = $eleve_id;
        return add_query_arg($args, $base);
    }

    public function shortcode_login( $atts = [] ): string {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        self::load_assets(); // shortcode placed outside the page content (widget, page builder…)
        nocache_headers();
        if (isset($_GET['logieval_print'])) {
            $this->handle_print();
            return '';
        }
        if (isset($_GET['logieval_export'])) {
            $this->handle_front_export();
            return '';
        }
        if ( isset($_POST['logieval_login_submit']) ) {
            $identifier = sanitize_text_field( wp_unslash( $_POST['logieval_identifier'] ?? '' ) );
            // Normalement déjà traité avant l'affichage (maybe_handle_auth) ; sinon (page affichée autrement), ici.
            if ( null === self::$auth_state ) {
                self::$auth_state = LOGIEVAL_Auth::login( $identifier, ! empty( $_POST['logieval_shared'] ) ) ? 'ok' : ( LOGIEVAL_Auth::is_rate_limited() ? 'rate' : 'fail' );
            }
            if ( 'ok' === self::$auth_state ) {
                // Cookie is set — render portal directly without redirect
                // (redirect can lose cookies on some mobile browsers)
                if ( isset($_GET['logieval_eval']) ) {
                    return $this->render_evaluation( intval($_GET['logieval_eval']) );
                }
                return $this->render_portal();
            }
            // Login failed
            if ( 'rate' === self::$auth_state ) {
                $error = '<p class="logieval-error">Trop de tentatives depuis cet appareil. Réessayez dans 15 minutes.</p>';
            } else {
                $identifier_display = esc_html( strtoupper($identifier) );
                $error = '<p class="logieval-error">Identifiant invalide : <code>' . $identifier_display . '</code><br>Vérifiez le code reçu par e-mail (majuscules, tiret inclus).</p>';
            }
        }
        if (isset($_GET['logieval_logout'])) {
            if ( 'logout' !== self::$auth_state ) LOGIEVAL_Auth::logout();
            return $this->render_login_form('<p class="logieval-logout-msg">Vous avez été déconnecté.</p>');
        }
        if ( (LOGIEVAL_Auth::is_logged_in() || current_user_can( 'lp_eval_admin' )) && isset($_GET['logieval_eval']) )
            return $this->render_evaluation( intval($_GET['logieval_eval']) );
        if ( LOGIEVAL_Auth::is_logged_in() && isset($_GET['logieval_new_eval']) )
            return $this->render_new_evaluation_form();
        if ( LOGIEVAL_Auth::is_logged_in() ) return $this->render_portal();
        return $this->render_login_form($error ?? '');
    }

    private function render_login_form( string $message = '' ): string {
        $action_url = esc_url(remove_query_arg(['logieval_logout'], wp_unslash($_SERVER['REQUEST_URI'] ?? '')));
        ob_start(); ?>
        <div class="logieval-app logieval-login-wrap">
            <?= self::hero(['title'=>'Accès enseignant', 'kicker'=>'Évaluation de la posture professionnelle', 'lead'=>(is_user_logged_in() && current_user_can('lp_eval_use')) ? 'Votre compte n’est pas encore relié à LogiEval. Saisissez une seule fois votre identifiant reçu par e-mail : la prochaine fois, vous serez reconnu automatiquement.' : 'Saisissez votre identifiant unique reçu par e-mail.', 'art'=>true]) ?>
            <div class="lge-body">
            <div class="logieval-login-card">
                <h3>Se connecter</h3>
                <?= $message ?>
                <form method="post" action="<?=$action_url?>" class="logieval-login-form">
                    <label class="lge-sr" for="logieval-identifier">Identifiant</label>
                    <input type="text" id="logieval-identifier" name="logieval_identifier" value="" placeholder="ex. ENS-A3F7K2C9" class="logieval-input-code" autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus>
                    <button type="submit" name="logieval_login_submit" class="logieval-btn logieval-btn-primary">Se connecter →</button>
                    <label class="logieval-shared"><input type="checkbox" name="logieval_shared" value="1"> Ordinateur partagé (salle des maîtres…) : me déconnecter en fermant le navigateur</label>
                    <p class="logieval-login-help">Pas besoin de compte sur le site : votre identifiant personnel, reçu par e-mail, suffit.</p>
                </form>
            </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private function render_portal(): string {
        if (isset($_POST['logieval_reopen_eval_id'])) {
            $reopen_eval_id = intval($_POST['logieval_reopen_eval_id']);
            $ens_id_reopen  = LOGIEVAL_Auth::current_enseignant_id();
            if ($reopen_eval_id && $ens_id_reopen
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'logieval_reopen_eval_' . $reopen_eval_id)
            ) {
                $t_reopen  = LOGIEVAL_Database::tables();
                $eval_reopen = LOGIEVAL_Database::get_row($t_reopen['evaluations'], ['id' => $reopen_eval_id]);
                if ($eval_reopen && LOGIEVAL_Auth::is_maitre($ens_id_reopen, (int)$eval_reopen['classe_id'])) {
                    LOGIEVAL_Database::update($t_reopen['evaluations'], ['statut' => 'active', 'last_teacher_reminder_sent' => 0], ['id' => $reopen_eval_id]);
                    LOGIEVAL_Calculator::unfreeze_grid($reopen_eval_id);
                    delete_transient('logieval_class_report_key_'.$reopen_eval_id);
                    $reopen_success = true;
                } else {
                    $reopen_error = "Réouverture refusée : vous n'êtes pas maître de cette classe.";
                }
            } else {
                $reopen_error = "Erreur de sécurité. Rechargez la page et réessayez.";
            }
        }

        // Handle evaluation deletion (POST form, no JS required)
        if (isset($_POST['logieval_delete_eval_id'])) {
            $del_eval_id = intval($_POST['logieval_delete_eval_id']);
            $ens_id_del  = LOGIEVAL_Auth::current_enseignant_id();
            if ($del_eval_id && $ens_id_del
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'logieval_delete_eval_' . $del_eval_id)
            ) {
                $t_del  = LOGIEVAL_Database::tables();
                $eval_del = LOGIEVAL_Database::get_row($t_del['evaluations'], ['id' => $del_eval_id]);
                if ($eval_del && LOGIEVAL_Auth::is_maitre($ens_id_del, (int)$eval_del['classe_id'])) {
                    global $wpdb;
                    $wpdb->delete($t_del['notes_ref'],       ['evaluation_id_dest'   => $del_eval_id]);
                    $wpdb->delete($t_del['notes_ref'],       ['evaluation_id_source' => $del_eval_id]);
                    $wpdb->delete($t_del['non_evaluables'],  ['evaluation_id'        => $del_eval_id]);
                    $wpdb->delete($t_del['arbitrages'],      ['evaluation_id'        => $del_eval_id]);
                    $wpdb->delete($t_del['reponses'],        ['evaluation_id'        => $del_eval_id]);
                    $wpdb->delete($t_del['eval_enseignant'], ['evaluation_id'        => $del_eval_id]);
                    $wpdb->delete($t_del['evaluations'],     ['id'                   => $del_eval_id]);
                    LOGIEVAL_Calculator::unfreeze_grid($del_eval_id);
                    delete_transient('logieval_class_report_key_'.$del_eval_id);
                    $delete_success = true;
                } else {
                    $delete_error = "Suppression refusée : vous n'êtes pas maître de cette classe.";
                }
            } else {
                $delete_error = "Erreur de sécurité. Rechargez la page et réessayez.";
            }
        }

        $ens = LOGIEVAL_Auth::current_enseignant();
        $eid = (int)$ens['id'];
        global $wpdb; $t = LOGIEVAL_Database::tables();
        $evals = $wpdb->get_results($wpdb->prepare(
            "SELECT ev.*,cl.nom AS classe_nom,ee.statut AS mon_statut
             FROM {$t['evaluations']} ev
             INNER JOIN {$t['eval_enseignant']} ee ON ee.evaluation_id=ev.id AND ee.enseignant_id=%d
             INNER JOIN {$t['classes']} cl ON cl.id=ev.classe_id
             WHERE ev.statut='active' AND ee.statut!='termine' ORDER BY ev.date_echeance ASC",$eid),ARRAY_A)?:[];
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT ev.*, cl.nom AS classe_nom, ee.statut AS mon_statut,
                    (SELECT MAX(ee2.date_fin)
                     FROM {$t['eval_enseignant']} ee2
                     WHERE ee2.evaluation_id=ev.id AND ee2.statut='termine') AS date_cloture
             FROM {$t['evaluations']} ev
             INNER JOIN {$t['eval_enseignant']} ee ON ee.evaluation_id=ev.id AND ee.enseignant_id=%d
             INNER JOIN {$t['classes']} cl ON cl.id=ev.classe_id
             WHERE ev.statut='cloturee' OR ee.statut='termine'
             ORDER BY ev.date_echeance DESC, ev.date_creation DESC
             LIMIT 20", $eid
        ), ARRAY_A) ?: [];
        ob_start(); ?>
        <div class="logieval-app logieval-portal-wrap">
            <?= self::hero(['title'=>'Bonjour '.$ens['prenom'], 'lead'=>'Vos évaluations de la posture professionnelle des apprenti·es, au même endroit.', 'user'=>$ens, 'art'=>true]) ?>
            <div class="lge-body">
            <?php if (!empty($delete_success) || !empty($reopen_success)): ?>
            <script>if (window.history && history.replaceState) { history.replaceState(null, '', location.href); }</script>
            <?php endif ?>
            <?php if (!empty($delete_success)): ?>
            <div class="logieval-notice-success">✅ Évaluation supprimée avec succès.</div>
            <?php elseif (!empty($reopen_success)): ?>
            <div class="logieval-notice-success">✅ Évaluation réouverte avec succès.</div>
            <?php elseif (!empty($delete_error)): ?>
            <div class="logieval-error-banner"><span>⚠ </span><span><?=esc_html($delete_error)?></span></div>
            <?php elseif (!empty($reopen_error)): ?>
            <div class="logieval-error-banner"><span>⚠ </span><span><?=esc_html($reopen_error)?></span></div>
            <?php endif ?>

            <?php
            // Classes dont je suis maître de classe, avec toutes leurs évaluations
            $classes_as_maitre = $wpdb->get_results($wpdb->prepare(
                "SELECT cl.id, cl.nom FROM {$t['classes']} cl
                 INNER JOIN {$t['classe_enseignant']} ce ON ce.classe_id=cl.id
                 WHERE ce.enseignant_id=%d AND ce.est_maitre=1
                 ORDER BY cl.nom", $eid
            ), ARRAY_A) ?: [];
            $classes_as_maitre = LOGIEVAL_Rollover::without_archived($classes_as_maitre); // 1.53.0 : classes archivées masquées
            $maitre_class_ids = array_map('intval', array_column($classes_as_maitre, 'id'));
            $all_maitre_evals = [];
            if ($maitre_class_ids) {
                $placeholders = implode(',', array_fill(0, count($maitre_class_ids), '%d'));
                $all_maitre_evals = $wpdb->get_results($wpdb->prepare(
                    "SELECT ev.*, cl.nom AS classe_nom,
                            COUNT(DISTINCT ee.id)       AS nb_ens,
                            SUM(ee.statut='termine')    AS nb_done,
                            MAX(CASE WHEN ee.statut='termine' THEN ee.date_fin ELSE NULL END) AS date_cloture
                     FROM {$t['evaluations']} ev
                     INNER JOIN {$t['classes']} cl ON cl.id=ev.classe_id
                     LEFT JOIN  {$t['eval_enseignant']} ee ON ee.evaluation_id=ev.id
                     WHERE ev.classe_id IN ($placeholders)
                     GROUP BY ev.id
                     ORDER BY ev.statut ASC, ev.date_echeance DESC",
                    ...$maitre_class_ids
                ), ARRAY_A) ?: [];
            }
            // L'historique ne répète pas ce qui figure déjà dans « Mes classes »
            $history = array_values(array_filter($history, fn($h) => !in_array((int)$h['classe_id'], $maitre_class_ids, true)));
            ?>

            <h3>À faire</h3>
            <?php if (empty($evals)): ?>
            <div class="lge-all-done">✓ Rien à faire pour le moment : vous avez répondu à toutes vos évaluations.</div>
            <?php else: ?>
            <p class="lge-section-help">Les évaluations où vous devez encore donner vos réponses, la plus urgente en premier.</p>
            <div class="logieval-eval-grid">
            <?php foreach ($evals as $ev):
                $days = self::days_left($ev['date_echeance']);
                $urg  = $days < 0 || $days <= 3 ? 'logieval-card-urgent' : ($days <= 7 ? 'logieval-card-warning' : '');
                $due  = $days < 0 ? 'is-late' : ($days <= 7 ? 'is-soon' : '');
            ?><div class="logieval-eval-card logieval-clickable-card <?=esc_attr($urg)?>" data-href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$ev['id']]))?>">
                <h4><?=esc_html($ev['classe_nom'])?></h4>
                <p class="logieval-semestre"><?=esc_html($ev['semestre'])?></p>
                <p class="lge-due <?=esc_attr($due)?>">À rendre le <?=esc_html(date_i18n('d.m.Y', strtotime($ev['date_echeance'])))?> · <?=esc_html(self::days_label($ev['date_echeance']))?></p>
                <a href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$ev['id']]))?>" class="logieval-btn logieval-btn-primary">Évaluer →</a>
            </div><?php endforeach ?></div>
            <?php endif ?>

            <?php if (!empty($classes_as_maitre)): ?>
            <div class="logieval-maitre-section lge-classes">
                <div class="logieval-maitre-header">
                    <h3>Mes classes</h3>
                    <a href="<?=esc_url(self::page_link(['logieval_new_eval'=>1]))?>" class="logieval-btn logieval-btn-create">+ Nouvelle évaluation</a>
                </div>
                <p class="lge-section-help">Vous êtes maître de classe de <?=esc_html(implode(', ', array_column($classes_as_maitre, 'nom')))?>. Suivez ici l'avancement des collègues, imprimez et gérez les évaluations.</p>
                <?php if (empty($all_maitre_evals)): ?>
                <p class="logieval-empty">Aucune évaluation pour vos classes. Commencez par « Nouvelle évaluation ».</p>
                <?php else: ?>
                <div class="logieval-maitre-evals">
                    <?php foreach ($all_maitre_evals as $mev):
                        $is_active = $mev['statut'] === 'active';
                        $nb_ens    = (int)$mev['nb_ens'];
                        $nb_done   = (int)$mev['nb_done'];
                        $pct       = $nb_ens > 0 ? round(($nb_done/$nb_ens)*100) : 0;
                        $days      = self::days_left($mev['date_echeance']);
                        $urg_cls   = $is_active && $days <= 3 ? 'logieval-meval-urgent' : ($is_active && $days <= 7 ? 'logieval-meval-warning' : '');
                    ?>
                    <div class="logieval-meval-row <?=esc_attr($urg_cls)?>">
                        <div class="logieval-meval-info">
                            <span class="logieval-meval-classe"><?=esc_html($mev['classe_nom'])?></span>
                            <span class="logieval-meval-sem"><?=esc_html($mev['semestre'])?></span>
                            <?php if ($is_active): ?>
                            <span class="logieval-meval-date">À rendre le <?=esc_html(date_i18n('d.m.Y', strtotime($mev['date_echeance'])))?> · <?=esc_html(self::days_label($mev['date_echeance']))?></span>
                            <?php elseif (!empty($mev['date_cloture'])): ?>
                            <span class="logieval-meval-date">Clôturée le <?=esc_html(date_i18n('d.m.Y', strtotime($mev['date_cloture'])))?></span>
                            <?php endif ?>
                        </div>
                        <div class="logieval-meval-status">
                            <?php if ($is_active): ?>
                                <div class="logieval-meval-progress">
                                    <span><?=$nb_done?> sur <?=$nb_ens?> enseignants ont terminé</span>
                                    <div class="logieval-progress-bar" style="width:160px"><div class="logieval-progress-fill" style="width:<?=(int)$pct?>%"></div></div>
                                </div>
                            <?php else: ?>
                                <span class="logieval-badge logieval-badge-green">✓ Clôturée</span>
                            <?php endif ?>
                        </div>
                        <div class="logieval-meval-actions">
                            <a href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$mev['id']]))?>" class="logieval-btn-meval logieval-btn-meval-view"><?=$is_active ? 'Ouvrir' : 'Voir les résultats'?></a>
                            <?php if (!$is_active): ?>
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Réouvrir cette évaluation ? Les enseignants pourront corriger leurs réponses.')">
                                <?php wp_nonce_field('logieval_reopen_eval_'.$mev['id']) ?>
                                <input type="hidden" name="logieval_reopen_eval_id" value="<?=(int)$mev['id']?>">
                                <button type="submit" class="logieval-btn-meval logieval-btn-meval-reopen">Réouvrir</button>
                            </form>
                            <?php endif ?>
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Supprimer définitivement l’évaluation <?=esc_attr(esc_js($mev['classe_nom'].' – '.$mev['semestre']))?> ?\n\nToutes les réponses de TOUS les enseignants et les notes seront effacées. Cela ne peut pas être annulé.') ? confirm('Dernière confirmation : supprimer définitivement ?') : false">
                                <?php wp_nonce_field('logieval_delete_eval_'.$mev['id']) ?>
                                <input type="hidden" name="logieval_delete_eval_id" value="<?=(int)$mev['id']?>">
                                <button type="submit" class="logieval-btn-meval logieval-btn-meval-del" title="Supprimer cette évaluation">Supprimer</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
                <?php endif ?>
            </div>
            <?php endif ?>

            <?php if (!empty($history)): ?>
            <details class="lge-history">
                <summary>Mes évaluations terminées (<?=count($history)?>)</summary>
                <div class="logieval-eval-grid">
                <?php foreach ($history as $hev): ?><div class="logieval-eval-card logieval-card-closed logieval-clickable-card" data-href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$hev['id']]))?>">
                    <h4><?=esc_html($hev['classe_nom'])?></h4>
                    <p class="logieval-semestre"><?=esc_html($hev['semestre'])?></p>
                    <p><?= $hev['statut']==='cloturee'
                        ? '<span class="logieval-badge logieval-badge-green">✓ Clôturée</span>'
                        : '<span class="logieval-badge logieval-badge-orange">Vous avez terminé · en attente des collègues</span>' ?></p>
                    <a href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$hev['id']]))?>" class="logieval-btn logieval-btn-secondary">Voir</a>
                </div><?php endforeach ?></div>
            </details>
            <?php endif ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private function render_evaluation( int $eval_id ): string {
        // A LogiEval teacher session takes priority over the WordPress admin role.
        // This lets an administrator who is also an assigned teacher submit votes.
        $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        $is_site_admin = current_user_can( 'lp_eval_admin' ) && !$ens_id;
        if (!$ens_id && !$is_site_admin) return $this->shortcode_login([]);
        global $wpdb; $t = LOGIEVAL_Database::tables();
        $ee     = $ens_id ? LOGIEVAL_Database::get_row($t['eval_enseignant'],['evaluation_id'=>$eval_id,'enseignant_id'=>$ens_id]) : null;
        $eval   = LOGIEVAL_Database::get_row($t['evaluations'],['id'=>$eval_id]);
        $classe = $eval?LOGIEVAL_Database::get_row($t['classes'],['id'=>$eval['classe_id']]):null;
        // Maître de classe pas attribué à l'évaluation (créée depuis l'administration) : il voit tout
        // en lecture, avec une explication, au lieu d'un message « accès refusé ».
        $not_assigned = false;
        if (!$is_site_admin && !$ee && $eval && $classe && LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
            $not_assigned = true;
            $ee = ['statut' => 'termine'];
        }
        if ((!$is_site_admin && !$ee)||!$eval||!$classe) return '<p class="logieval-error">Évaluation introuvable, ou vous n’êtes pas attribué à cette évaluation. Demandez au maître de classe de vous ajouter.</p>';
        $is_closed = $eval['statut']==='cloturee';
        $is_test_mode = !empty($_GET['logieval_test']);
        $is_maitre = !$is_site_admin && $ens_id && LOGIEVAL_Auth::is_maitre($ens_id,(int)$eval['classe_id']);
        $can_view_master_reserved = $is_maitre;
        $can_view_peer_details = $can_view_master_reserved || $is_site_admin;
        $eleves    = LOGIEVAL_Database::eval_students($eval); // 1.53.1
        $grid      = LOGIEVAL_Calculator::grid($eval_id); // figée si l'évaluation est clôturée
        $objectifs = $grid['objectifs'];
        $sous_by_obj = $grid['sous_by_obj'];
        // Load MY explicit votes: [eleve_id][sc_id] => 'oui'|'non'|'abstention'
        $my_votes_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, sous_critere_id, vote, vote_set, commentaire
             FROM {$t['reponses']} WHERE evaluation_id=%d AND enseignant_id=%d",
            $eval_id, $ens_id
        ), ARRAY_A) ?: [];
        $my_votes = [];
        $my_comments = [];
        foreach ($my_votes_raw as $r) {
            if (!empty($r['vote_set'])) {
                $my_votes[$r['eleve_id']][$r['sous_critere_id']] = $r['vote'];
            }
            $my_comments[$r['eleve_id']][$r['sous_critere_id']] = $r['commentaire'] ?? '';
        }

        // Load ALL votes for visibility: [eleve_id][sc_id] => [ens_id => vote]
        $all_votes_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT r.eleve_id, r.sous_critere_id, r.enseignant_id, r.vote
             FROM {$t['reponses']} r WHERE r.evaluation_id=%d AND r.vote_set=1" . self::counted_votes_sql('r'),
            $eval_id
        ), ARRAY_A) ?: [];
        $all_votes = [];
        foreach ($all_votes_raw as $r) {
            $all_votes[$r['eleve_id']][$r['sous_critere_id']][$r['enseignant_id']] = $r['vote'];
        }

        // Load arbitrages (maître decisions on ties): [eleve_id][sc_id] => 0|1
        $arbitrages_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, sous_critere_id, decision
             FROM {$t['arbitrages']} WHERE evaluation_id=%d",
            $eval_id
        ), ARRAY_A) ?: [];
        $arbitrages = [];
        foreach ($arbitrages_raw as $a) {
            $arbitrages[$a['eleve_id']][$a['sous_critere_id']] = (int)$a['decision'];
        }

        // Load assigned teacher IDs for vote counting
        $closed_teacher_filter = $eval['statut'] === 'cloturee' ? " AND statut='termine'" : '';
        $closed_teacher_join_filter = $eval['statut'] === 'cloturee' ? " AND ee.statut='termine'" : '';
        $ens_ids_eval = $wpdb->get_col($wpdb->prepare(
            "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d{$closed_teacher_filter}", $eval_id
        )) ?: [];
        $class_teachers = [];
        $assigned_teacher_ids = [];
        $stats = [];
        if ($can_view_master_reserved) {
            $class_teachers = $wpdb->get_results($wpdb->prepare(
                "SELECT e.id, e.prenom, e.nom
                 FROM {$t['enseignants']} e
                 INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id=e.id
                 WHERE ce.classe_id=%d AND e.actif=1
                 ORDER BY e.nom, e.prenom",
                $eval['classe_id']
            ), ARRAY_A) ?: [];
            $assigned_teacher_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d",
                $eval_id
            )) ?: []);
            $stats = LOGIEVAL_Calculator::completion_stats($eval_id);
        }
        $total_max=(float)$grid['total_max'];
        $refs_raw=$wpdb->get_results($wpdb->prepare("SELECT eleve_id,note_calculee FROM {$t['notes_ref']} WHERE evaluation_id_dest=%d",$eval_id),ARRAY_A)?:[];
        $refs=array_column($refs_raw,'note_calculee','eleve_id');
        $votes_by_teacher = LOGIEVAL_Calculator::get_votes($eval_id);
        $non_evaluables_by_teacher = LOGIEVAL_Calculator::get_non_evaluables($eval_id);

        $my_non_eval_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, objectif_id FROM {$t['non_evaluables']}
             WHERE evaluation_id=%d AND enseignant_id=%d",
            $eval_id, $ens_id
        ), ARRAY_A) ?: [];
        $my_non_eval = [];
        foreach ($my_non_eval_raw as $ne) {
            $my_non_eval[$ne['eleve_id']][$ne['objectif_id']] = true;
        }

        $others_by_eleve_sc = [];
        $comments_by_eleve_sc = [];
        if ($can_view_peer_details) {
            // Visible only for the maître de classe/admin. Other teachers must not see peer votes/comments.
            $others_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT r.eleve_id, r.sous_critere_id, r.vote, CONCAT(e.prenom,' ',e.nom) AS ens_nom
                 FROM {$t['reponses']} r
                 INNER JOIN {$t['enseignants']} e ON e.id = r.enseignant_id
                 WHERE r.evaluation_id = %d AND r.enseignant_id != %d AND r.vote_set=1" . self::counted_votes_sql('r') . "
                 ORDER BY e.nom, e.prenom",
                $eval_id, $is_site_admin ? 0 : $ens_id
            ), ARRAY_A) ?: [];
            foreach ($others_raw as $or) {
                $others_by_eleve_sc[$or['eleve_id']][$or['sous_critere_id']][$or['vote']][] = $or['ens_nom'];
            }
            $comments_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT r.eleve_id, r.sous_critere_id, r.commentaire, CONCAT(e.prenom,' ',e.nom) AS ens_nom
                 FROM {$t['reponses']} r
                 INNER JOIN {$t['enseignants']} e ON e.id = r.enseignant_id
                 WHERE r.evaluation_id = %d AND r.enseignant_id != %d
                   AND r.commentaire IS NOT NULL AND r.commentaire <> ''
                 ORDER BY e.nom, e.prenom",
                $eval_id, $is_site_admin ? 0 : $ens_id
            ), ARRAY_A) ?: [];
            foreach ($comments_raw as $cr) {
                $comments_by_eleve_sc[$cr['eleve_id']][$cr['sous_critere_id']][] = $cr;
            }
        }

        // Actions du maître de classe : deux boutons au plus, selon l'état de l'évaluation.
        ob_start(); ?>
                    <?php if ($is_maitre && !$is_closed): ?>
                    <button class="logieval-btn logieval-btn-create" id="btn-send-pending-reminder" data-eval="<?=(int)$eval_id?>">Relancer les retardataires</button>
                    <?php endif ?>
                    <?php if ($is_maitre): ?>
                    <details class="lge-menu">
                        <summary class="logieval-btn logieval-btn-secondary"><?=$is_closed ? 'Imprimer / exporter' : 'Imprimer'?></summary>
                        <div class="lge-menu-list">
                            <a target="_blank" rel="noopener" href="<?=esc_url($this->print_url($eval_id))?>"><strong>Imprimer la classe</strong><small>Récapitulatif et détail de chaque élève<?=$is_closed ? '' : ' (état actuel)'?></small></a>
                            <?php if ($is_closed): ?>
                            <a target="_blank" rel="noopener" href="<?=esc_url($this->print_url($eval_id, 0, 'students'))?>"><strong>Fiches élèves (PDF)</strong><small>Une page par élève, avec signature</small></a>
                            <a href="<?=esc_url($this->export_url($eval_id))?>"><strong>Export Word</strong><small>Document .docx modifiable</small></a>
                            <button type="button" id="btn-send-class-report" data-eval="<?=(int)$eval_id?>"><strong>Envoyer le rapport par e-mail</strong><small>Aux enseignants de l'évaluation</small></button>
                            <?php endif ?>
                        </div>
                    </details>
                    <?php endif ?>
                    <?php if ($is_maitre && $is_closed): ?>
                    <button class="logieval-btn logieval-btn-secondary" id="btn-reopen-evaluation" data-eval="<?=(int)$eval_id?>">Réouvrir</button>
                    <?php endif ?>
        <?php
        $hero_actions = ob_get_clean();
        if ($is_test_mode) {
            // Entraînement : aucune action réelle (rappels, impression, réouverture…) n'est proposée.
            $hero_actions = '';
        }
        $hero_meta = '';
        if ($is_closed) {
            $hero_meta .= '<p class="lge-meta"><span class="logieval-badge logieval-badge-green">Clôturée</span></p>';
        } else {
            $hero_meta .= '<p class="lge-meta">À rendre le <strong>' . esc_html(date_i18n('d.m.Y', strtotime($eval['date_echeance']))) . '</strong> · ' . esc_html(self::days_label($eval['date_echeance'])) . '</p>';
        }
        ob_start(); ?>
        <div class="logieval-app logieval-eval-wrap" data-can-peer="<?=$can_view_peer_details ? 1 : 0?>">
            <?= self::hero(['title'=>$classe['nom'], 'kicker'=>$eval['semestre'], 'meta'=>$hero_meta, 'back'=>true, 'actions'=>$hero_actions, 'user'=>$ens_id ? LOGIEVAL_Auth::current_enseignant() : null]) ?>
            <div class="lge-body">
            <?php if ($is_test_mode): ?>
            <div class="logieval-test-banner">
                <strong>Entraînement</strong>
                <span>Vous pouvez tout essayer : rien n'est enregistré.</span>
                <span class="lge-overall" id="lge-overall"></span>
                <span id="logieval-save-status">Prêt</span>
                <a class="logieval-btn logieval-btn-secondary" href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$eval_id]))?>">Quitter l'entraînement</a>
            </div>
            <?php endif ?>
            <?php if ($can_view_master_reserved): ?>
            <?php $nb_done = count(array_filter($stats, fn($x) => $x['statut']==='termine')); ?>
            <div class="logieval-ens-status-bar">
                <span class="lge-status-title">Enseignants : <?=$nb_done?> sur <?=count($stats)?> ont terminé</span>
                <?php foreach ($stats as $s): $done = $s['statut']==='termine'; ?>
                <div class="logieval-ens-chip <?=$done?'done':'pending'?>">
                    <span><?=$done ? '✓' : '○'?> <?=esc_html($s['prenom'].' '.$s['nom'])?></span>
                    <small><?=$done ? 'terminé'.(!empty($s['date_fin']) ? ' le '.esc_html(date_i18n('d.m', strtotime($s['date_fin']))) : '') : 'en cours'?></small>
                    <?php if ($is_maitre && !$is_closed && !$is_test_mode && $s['statut']==='termine' && (int)$s['id'] !== (int)$ens_id): ?>
                    <button type="button"
                            class="logieval-chip-action logieval-master-mark-undone"
                            data-eval="<?=$eval_id?>"
                            data-teacher="<?=(int)$s['id']?>"
                            title="Permettre à cet enseignant de modifier son évaluation">
                        Réouvrir
                    </button>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
            <?php if (!$is_closed && $can_view_master_reserved && !$is_test_mode): ?>
            <div class="logieval-manage-eval-card">
                <div class="logieval-manage-eval-head">
                    <h3>Modifier l'évaluation</h3>
                    <span>Maître de classe</span>
                </div>
                <div class="logieval-manage-eval-grid">
                    <label>
                        <strong>Date de remise requise</strong>
                        <input type="date" id="front-eval-echeance" value="<?=esc_attr($eval['date_echeance'])?>">
                    </label>
                    <div>
                        <strong>Enseignants attribués</strong>
                        <div class="logieval-front-ens-checks">
                        <?php foreach ($class_teachers as $teacher): ?>
                            <label>
                                <input type="checkbox" class="front-eval-ens-check"
                                       value="<?=(int)$teacher['id']?>"
                                       <?=in_array((int)$teacher['id'], $assigned_teacher_ids, true) ? 'checked' : ''?>>
                                <?=esc_html($teacher['nom'].' '.$teacher['prenom'])?>
                            </label>
                        <?php endforeach ?>
                        </div>
                    </div>
                </div>
                <button class="logieval-btn logieval-btn-secondary" id="btn-update-eval-settings" data-eval="<?=$eval_id?>">
                    Enregistrer les modifications
                </button>
            </div>
            <?php endif ?>
            <?php if ($not_assigned): ?>
            <div class="logieval-notice-info">Vous n’êtes pas attribué à cette évaluation : vous voyez l’avancement et les résultats, sans donner de réponses. <?php if (!$is_closed): ?>Pour évaluer aussi, cochez votre nom dans « Modifier l’évaluation » ci-dessus.<?php endif ?></div>
            <?php endif ?>
            <?php if (!$is_closed && !$is_site_admin && !$is_test_mode && !$not_assigned): ?>
            <div class="logieval-autosave-bar">
                <?php if ($ee['statut']!=='termine'): ?>
                <div class="lge-overall-wrap">
                    <span class="lge-overall" id="lge-overall"></span>
                    <span class="lge-bar"><span class="lge-bar-fill" id="lge-overall-fill"></span></span>
                </div>
                <span id="logieval-save-status">Les réponses sont enregistrées automatiquement</span>
                <button class="logieval-btn logieval-btn-secondary" id="lge-goto-missing">Aller au premier critère manquant</button>
                <button class="logieval-btn logieval-btn-success" id="btn-mark-done" data-eval="<?=(int)$eval_id?>">Marquer terminé</button>
                <?php else: ?>
                <span class="logieval-badge logieval-badge-green">✓ Vous avez terminé</span>
                <span id="logieval-save-status">Vos réponses sont verrouillées.</span>
                <button class="logieval-btn logieval-btn-secondary" id="btn-mark-undone" data-eval="<?=(int)$eval_id?>">Corriger mes réponses</button>
                <?php if ($is_maitre): ?>
                <span class="lge-close-help">L'évaluation se clôture seule quand tous les enseignants ont terminé. Vous pouvez aussi la clôturer maintenant : les enseignants encore en cours ne seront pas comptés.</span>
                <button class="logieval-btn logieval-btn-export" id="btn-force-close-finished" data-eval="<?=(int)$eval_id?>">Clôturer maintenant</button>
                <?php endif ?>
                <?php endif ?>
            </div>
            <?php if ($ee['statut']!=='termine'): ?>
            <p class="lge-practice"><a href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$eval_id, 'logieval_test'=>1]))?>">S'entraîner sans enregistrer</a></p>
            <?php endif ?>
            <?php endif ?>

            <?php if (empty($eleves)): ?>
            <div class="logieval-notice-info">Aucun élève dans cette classe pour le moment : l’administrateur doit les ajouter (LogiEval › Élèves ou Import). Vous pourrez ensuite répondre.</div>
            <?php elseif (empty($objectifs)): ?>
            <div class="logieval-notice-info">Aucun objectif actif : l’administrateur doit en activer (LogiEval › Objectifs).</div>
            <?php endif ?>
            <div class="logieval-students-list">
            <?php $student_index = 0; foreach ($eleves as $el):
                $student_index++;
                $eid2=(int)$el['id'];
                // Compute current voted result for this student
                $computed = LOGIEVAL_Calculator::compute_student_points(
                    $eid2, $objectifs, $sous_by_obj,
                    $votes_by_teacher,
                    $arbitrages, $ens_ids_eval,
                    $non_evaluables_by_teacher,
                    $eval['mode_calcul'] ?? 'majoritaire'
                );
                if ((int)($grid['rule'] ?? 2) < 2) { $computed['pts'] = $computed['raw_pts']; $computed['max'] = $total_max; }
                $my_pts  = $computed['pts'];
                $my_max  = $computed['max'];
                $my_ties = $computed['ties'];
                // Pas encore de réponse pour cet élève : pas de note (auparavant 1.0 s'affichait en rouge).
                $my_note = $my_pts !== null ? LOGIEVAL_Calculator::points_to_note($my_pts, $my_max) : null;
                $nc      = $my_note === null ? 'logieval-note-none' : ($my_note < 3 ? 'logieval-note-red' : ($my_note < 4.5 ? 'logieval-note-orange' : 'logieval-note-green'));
                $ref_note=$refs[$eid2]??null;
            ?>
            <div class="logieval-student-card" data-eleve="<?=$eid2?>">
                <div class="logieval-student-header" onclick="toggleStudent(<?=$eid2?>)">
                    <span class="logieval-student-name"><?=esc_html($el['nom'].' '.$el['prenom'])?></span>
                    <span class="logieval-student-score">
                        <span class="lge-progress" id="prog-<?=$eid2?>" hidden></span>
                        <?php if ($can_view_master_reserved): ?>
                        <?php if ($ref_note!==null): ?><span class="logieval-ref-note" title="Note de l’évaluation précédente">Semestre précédent : <?=number_format((float)$ref_note,1)?></span><?php endif ?>
                        <span class="logieval-my-score <?=$nc?>" id="score-<?=$eid2?>">
                            <?php if ($my_note === null): ?>
                            <strong>—</strong> <small>pas encore évalué</small>
                            <?php else: ?>
                            <strong><?=number_format($my_note,1)?>/6</strong>
                            <small>(<?=number_format($my_pts,2)?>/<?=number_format($my_max,2)?> pts)</small>
                            <?php endif ?>
                        </span>
                        <?php endif ?>
                        <span class="logieval-chevron" id="chev-<?=$eid2?>"><?=$student_index === 1 ? '▲' : '▼'?></span>
                        <?php if ($is_maitre && !$is_test_mode): ?>
                            <a class="logieval-print-student"
                               target="_blank"
                               onclick="event.stopPropagation()"
                               href="<?=esc_url($this->print_url($eval_id, $eid2))?>">
                                Imprimer
                            </a>
                        <?php endif ?>
                    </span>
                </div>
                <div class="logieval-student-body <?=$student_index === 1 ? '' : 'logieval-hidden'?>" id="body-<?=$eid2?>">
                <?php if (!$is_site_admin && (!$is_closed || $is_test_mode) && (($ee['statut'] ?? '') !== 'termine' || $is_test_mode)): ?>
                <div class="lge-student-tools">
                    <button type="button" class="lge-fill" data-eleve="<?=$eid2?>" data-obj="">✓ Oui à tous les critères restants</button>
                    <span>Remplit seulement les critères Oui / Non pas encore renseignés. Vous corrigez ensuite les exceptions.</span>
                </div>
                <?php endif ?>
                <?php foreach ($objectifs as $obj):
                    $oid=(int)$obj['id']; $scs=$sous_by_obj[$oid]??[];
                    $maitre_only=(bool)$obj['maitre_seulement'];
                    $can_view_maitre = $is_maitre;
                    // Absences/retards and other master-only objectives remain exclusively visible to the class master.
                    if ($maitre_only && $is_site_admin) continue;
                    if ($maitre_only && !$can_view_maitre) continue;
                    $can_edit=!$maitre_only||$can_view_maitre;
                    $locked=(!$is_test_mode && ($is_site_admin||$is_closed||($ee['statut'] ?? '')==='termine'))||!$can_edit;
                    $pts_obj=0.0;
                    // Points currently obtained for this objective.
                    if ($obj['type']==='radio') {
                        $pts_obj = $computed['details']['radio_'.$oid]['pts'] ?? 0.0;
                    } else {
                        foreach ($scs as $sc) {
                            $pts_obj += (float)($computed['details'][(int)$sc['id']]['awarded_points'] ?? 0.0);
                        }
                    }
                    $pts_max_obj=$obj['type']==='radio'
                        ? ($scs ? max(array_column($scs,'points')) : 0)
                        : array_sum(array_column($scs,'points'));
                ?>
                <div class="logieval-objectif <?=$maitre_only?'logieval-obj-maitre':''?>">
                    <?php
                        $is_ne     = isset($my_non_eval[$eid2][$oid]);
                        $ne_class  = $is_ne ? 'logieval-obj-ne' : '';
                        $can_ne    = !$is_site_admin && !$maitre_only && (!$is_closed || $is_test_mode) && (($ee['statut'] ?? '') !== 'termine' || $is_test_mode); // teacher can toggle NE
                    ?>
                    <div class="logieval-obj-header <?=$ne_class?>">
                        <div class="logieval-obj-title">
                            <?php if ($maitre_only): ?><span class="logieval-maitre-badge">👑 Maître de classe</span><?php endif ?>
                            <?=esc_html($obj['libelle'])?>
                        </div>
                        <div class="logieval-obj-right">
                            <?php if ($can_ne && $obj['type']==='checkbox' && !$is_ne): ?>
                            <button type="button" class="lge-fill lge-fill-obj" data-eleve="<?=$eid2?>" data-obj="<?=$oid?>">✓ Tout Oui</button>
                            <?php endif ?>
                            <?php if ($can_ne): ?>
                            <button class="logieval-btn-ne <?=$is_ne?'logieval-btn-ne-active':''?>"
                                    data-eleve="<?=$eid2?>" data-obj="<?=$oid?>"
                                    data-ne="<?=$is_ne?1:0?>"
                                    title="<?=$is_ne ? 'Rétablir' : 'Non évaluable'?>"
                                    onclick="logievalToggleNE(this)">
                                <?=$is_ne ? '↩ Rétablir' : '⊘ Non évaluable'?>
                            </button>
                            <?php endif ?>
                            <?php if ($can_view_master_reserved): ?>
                            <div class="logieval-obj-score <?=$is_ne?'logieval-obj-score-ne':''?>" id="obj-score-<?=$eid2?>-<?=$oid?>">
                                <?php if ($is_ne): ?>
                                <span class="logieval-ne-label">Non évaluable</span>
                                <?php else: ?>
                                <span><?=number_format($pts_obj,2)?></span> / <?=number_format($pts_max_obj,2)?> pt<?=$pts_max_obj>1?'s':''?>
                                <?php endif ?>
                            </div>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="logieval-sc-list <?=$obj['type']==='radio'?'logieval-sc-radio':'logieval-sc-checkbox'?> <?=$is_ne?'logieval-sc-list-ne':''?>"
                         id="sc-list-<?=$eid2?>-<?=$oid?>">
                    <?php foreach ($scs as $sc):
                    ?>
                    <?php
                        $others_for_sc = $others_by_eleve_sc[$eid2][$sc['id']] ?? ['oui'=>[], 'non'=>[], 'abstention'=>[]];
                        $others_oui = $others_for_sc['oui'] ?? [];
                        $others_non = $others_for_sc['non'] ?? [];
                        $others_abs = $others_for_sc['abstention'] ?? [];
                        $others_comments = $comments_by_eleve_sc[$eid2][$sc['id']] ?? [];
                    ?>
                    <?php
                        $my_vote    = $my_votes[$eid2][$sc['id']] ?? '';
                        $sc_votes   = $all_votes[$eid2][$sc['id']] ?? [];
                        $my_comment = $my_comments[$eid2][$sc['id']] ?? '';
                        // Tally for display
                        $nb_oui_sc = count(array_filter($sc_votes, fn($v)=>$v==='oui'));
                        $nb_non_sc = count(array_filter($sc_votes, fn($v)=>$v==='non'));
                        $nb_abs_sc = count(array_filter($sc_votes, fn($v)=>$v==='abstention'));
                        $is_tie_sc = $nb_oui_sc === $nb_non_sc && $nb_oui_sc > 0;
                    ?>
                    <div class="logieval-sc-wrapper <?=$is_tie_sc && !$is_closed?'logieval-sc-tie':''?>">
                    <div class="logieval-sc-row">
                        <span class="logieval-sc-lettre"><?=esc_html($sc['lettre'])?></span>
                        <span class="logieval-sc-text"><?=esc_html($sc['libelle'])?></span>
                        <span class="logieval-sc-pts"><?=number_format((float)$sc['points'],1)?> pt<?=$sc['points']>1?'s':''?></span>
                    </div>
                    <!-- Vote buttons for this SC -->
                    <div class="logieval-vote-row">
                        <?php if (!$is_site_admin && !$locked): ?>
                        <div class="logieval-vote-btns" data-eleve="<?=$eid2?>" data-sc="<?=$sc['id']?>" data-obj="<?=$oid?>" data-type="<?=$obj['type']?>" data-pts="<?=$sc['points']?>">
                            <?php if ($obj['type']==='radio'): ?>
                            <button type="button"
                                class="logieval-vote-btn logieval-vote-oui <?=$my_vote==='oui'?'active':''?>"
                                onclick="logievalVote(this,'oui')"><?=$my_vote==='oui'?'✓ Choisi':'Choisir'?></button>
                            <?php else: ?>
                            <button type="button"
                                class="logieval-vote-btn logieval-vote-oui <?=$my_vote==='oui'?'active':''?>"
                                onclick="logievalVote(this,'oui')">✓ Oui</button>
                            <button type="button"
                                class="logieval-vote-btn logieval-vote-non <?=$my_vote==='non'?'active':''?>"
                                onclick="logievalVote(this,'non')">✗ Non</button>
                            <button type="button"
                                class="logieval-vote-btn logieval-vote-abs <?=$my_vote==='abstention'?'active':''?>"
                                onclick="logievalVote(this,'abstention')" title="Je ne peux pas me prononcer (ne compte pas dans le calcul)">⊘ Sans avis</button>
                            <?php endif ?>
                        </div>
                        <?php elseif (!$is_site_admin): ?>
                        <div class="logieval-vote-readonly">
                            <?php if ($my_vote==='oui'): ?><span class="logieval-vote-ro-oui"><?=$obj['type']==='radio'?'✓ Choisi':'✓ Oui'?></span>
                            <?php elseif ($my_vote==='non'): ?><span class="logieval-vote-ro-non">✗ Non</span>
                            <?php elseif ($my_vote==='abstention'): ?><span class="logieval-vote-ro-abs">⊘ Sans avis</span>
                            <?php else: ?><span class="logieval-vote-ro-missing">Non renseigné</span><?php endif ?>
                        </div>
                        <?php endif ?>

                        <!-- Tally of all teachers -->
                        <?php if ($obj['type']!=='radio' && $can_view_peer_details): ?>
                        <div class="logieval-tally">
                            <?php if ($nb_oui_sc > 0 || $nb_non_sc > 0 || $nb_abs_sc > 0): ?>
                            <span class="logieval-tally-oui" title="<?=$nb_oui_sc?> vote<?=$nb_oui_sc>1?'s':''?> Oui">
                                ✓ <?=$nb_oui_sc?>
                            </span>
                            <span class="logieval-tally-non" title="<?=$nb_non_sc?> vote<?=$nb_non_sc>1?'s':''?> Non">
                                ✗ <?=$nb_non_sc?>
                            </span>
                            <?php if ($nb_abs_sc > 0): ?>
                            <span class="logieval-tally-abs" title="<?=$nb_abs_sc?> abstention<?=$nb_abs_sc>1?'s':''?>">
                                ⊘ <?=$nb_abs_sc?>
                            </span>
                            <?php endif ?>
                            <?php if ($is_tie_sc): ?>
                                <span class="logieval-tally-tie">Égalité : 50 % des points</span>
                            <?php endif ?>
                            <?php endif ?>
                        </div>
                        <?php endif ?>
                    </div>

                    <?php if ($can_view_peer_details): ?>
                    <div class="logieval-others" data-eleve="<?=$eid2?>" data-sc="<?=$sc['id']?>">
                        <?php if ($others_oui || $others_non || $others_abs): ?>
                            <?php if ($others_oui): ?>
                            <div class="logieval-others-line logieval-others-oui">
                                <strong><?=$obj['type']==='radio'?'Choisi par':'Oui'?> :</strong>
                                <?=esc_html(implode(', ', $others_oui))?>
                            </div>
                            <?php endif ?>
                            <?php if ($others_non): ?>
                            <div class="logieval-others-line logieval-others-non">
                                <strong>Non :</strong> <?=esc_html(implode(', ', $others_non))?>
                            </div>
                            <?php endif ?>
                            <?php if ($others_abs): ?>
                            <div class="logieval-others-line logieval-others-abs">
                                <strong>Sans avis :</strong> <?=esc_html(implode(', ', $others_abs))?>
                            </div>
                            <?php endif ?>
                        <?php endif ?>
                    </div>
                    <?php endif ?>

                    <!-- Comment field -->
                    <?php if (!$locked): ?>
                    <div class="logieval-comment-wrap">
                        <?php if ($my_comment === '' || $my_comment === null): ?>
                        <button type="button" class="lge-add-comment">+ Commentaire</button>
                        <?php endif ?>
                        <textarea class="logieval-comment-input<?=($my_comment === '' || $my_comment === null) ? ' logieval-hidden' : ''?>"
                                  data-eleve="<?=$eid2?>" data-sc="<?=(int)$sc['id']?>"
                                  aria-label="Commentaire"
                                  placeholder="Commentaire (optionnel, visible seulement par les enseignants)…"
                                  rows="1"><?=esc_textarea($my_comment)?></textarea>
                    </div>
                    <?php elseif ($my_comment): ?>
                    <div class="logieval-comment-ro"><strong>Mon commentaire :</strong> <?=esc_html($my_comment)?></div>
                    <?php endif ?>
                    <?php if ($can_view_peer_details && $others_comments): ?>
                    <div class="logieval-others-comments">
                        <?php foreach ($others_comments as $oc): ?>
                        <div class="logieval-comment-ro">
                            <strong><?=esc_html($oc['ens_nom'])?> :</strong> <?=esc_html($oc['commentaire'])?>
                        </div>
                        <?php endforeach ?>
                    </div>
                    <?php endif ?>

                    </div><!-- /sc-wrapper -->
                    <?php endforeach ?>
                    </div>
                    <?php if (!$can_edit&&!$is_closed): ?><p class="logieval-locked-msg">🔒 Réservé au maître de classe</p><?php endif ?>
                </div>
                <?php endforeach ?>
                <?php $next = $eleves[$student_index] ?? null; ?>
                <div class="lge-student-next">
                    <?php if ($next): ?>
                    <button type="button" class="logieval-btn logieval-btn-primary lge-next" data-next="<?=(int)$next['id']?>">Élève suivant : <?=esc_html($next['nom'].' '.$next['prenom'])?> →</button>
                    <?php else: ?>
                    <span>C'était le dernier élève.</span>
                    <?php endif ?>
                </div>
                </div>
            </div>
            <?php endforeach ?>
            </div>

            <?php if ($can_view_master_reserved): ?>
            <div class="logieval-legend">
                <span class="logieval-note-green">■</span> ≥ 4.5 Très satisfaisant &nbsp;
                <span class="logieval-note-orange">■</span> 3 – 4.5 Satisfaisant &nbsp;
                <span class="logieval-note-red">■</span> &lt; 3 Insuffisant
            </div>
            <?php endif ?>
            </div>
        </div>
        <script>
        window.logievalEvalId=<?=(int)$eval_id?>;
        window.logievalNonce=<?=wp_json_encode(self::create_nonce())?>;
        window.logievalTotalMax=<?=(float)$total_max?>;
        window.logievalTestMode=<?=$is_test_mode ? 1 : 0?>;
        function toggleStudent(id, noScroll){
            var targetBody = null;
            document.querySelectorAll('.logieval-student-body').forEach(function(body){
                var isTarget = body.id === 'body-'+id;
                body.classList.toggle('logieval-hidden', !isTarget);
                if (isTarget) targetBody = body;
            });
            document.querySelectorAll('.logieval-chevron').forEach(function(chev){
                chev.textContent = chev.id === 'chev-'+id ? '▲' : '▼';
            });
            if (targetBody ? !noScroll : false) {
                window.requestAnimationFrame(function(){
                    var card = targetBody.closest('.logieval-student-card');
                    var bar = document.querySelector('.logieval-autosave-bar');
                    var offset = (bar ? bar.offsetHeight : 0) + 24;
                    window.scrollTo({top: card.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth'});
                });
            }
        }
        </script>
        <?php return ob_get_clean();
    }

    /* ── New evaluation form (maître de classe only) ───────────── */
    private function render_new_evaluation_form(): string {
        $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        $ens    = LOGIEVAL_Auth::current_enseignant();
        if (!$ens_id) return $this->shortcode_login([]);

        global $wpdb;
        $t = LOGIEVAL_Database::tables();

        // Get classes where this teacher is maître
        $classes_as_maitre = $wpdb->get_results($wpdb->prepare(
            "SELECT cl.id, cl.nom FROM {$t['classes']} cl
             INNER JOIN {$t['classe_enseignant']} ce ON ce.classe_id=cl.id
             WHERE ce.enseignant_id=%d AND ce.est_maitre=1
             ORDER BY cl.nom", $ens_id
        ), ARRAY_A) ?: [];
        $classes_as_maitre = LOGIEVAL_Rollover::without_archived($classes_as_maitre); // 1.53.0

        if (empty($classes_as_maitre)) {
            return '<div class="logieval-app logieval-eval-wrap"><div class="lge-body"><p class="logieval-error">Vous n\'êtes maître d\'aucune classe.</p>
                    <a href="'.esc_url(self::portal_url()).'">← Retour</a></div></div>';
        }

        // Get all enseignants per class for the multi-select
        $all_ens_by_class = [];
        foreach ($classes_as_maitre as $cl) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT e.id, e.prenom, e.nom FROM {$t['enseignants']} e
                 INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id=e.id
                 WHERE ce.classe_id=%d AND e.actif=1 ORDER BY e.nom, e.prenom", $cl['id']
            ), ARRAY_A) ?: [];
            $all_ens_by_class[$cl['id']] = $rows;
        }

        // Previous evaluations per class (for "reprise" option)
        $prev_by_class = [];
        foreach ($classes_as_maitre as $cl) {
            $prev = $wpdb->get_row($wpdb->prepare(
                "SELECT id, semestre FROM {$t['evaluations']} WHERE classe_id=%d ORDER BY date_creation DESC LIMIT 1",
                $cl['id']
            ), ARRAY_A);
            if ($prev) $prev_by_class[$cl['id']] = $prev;
        }

        // Handle form submission
        $success_msg = '';
        $error_msg   = '';
        if (isset($_POST['logieval_create_eval']) && check_admin_referer('logieval_create_eval')) {
            $result = $this->do_create_evaluation($ens_id);
            if ($result['success']) {
                if (!headers_sent()) {
                    wp_safe_redirect(self::portal_url());
                    exit;
                }
                // Thème qui a déjà commencé la page : retour au portail par le navigateur.
                $back = esc_url(self::portal_url());
                return '<div class="logieval-app"><p class="logieval-success">Évaluation créée. <a href="' . $back . '">Retour au portail</a></p></div><script>window.location.replace(' . wp_json_encode(self::portal_url()) . ');</script>';
            }
            $error_msg = $result['message'];
        }

        ob_start(); ?>
        <div class="logieval-app logieval-eval-wrap">
            <?= self::hero(['title'=>'Nouvelle évaluation', 'kicker'=>'Espace maître de classe', 'lead'=>'Maître de classe : '.$ens['prenom'].' '.$ens['nom'], 'back'=>true, 'user'=>$ens]) ?>
            <div class="lge-body">

            <?php if ($error_msg): ?>
            <div class="logieval-error-banner" style="margin:16px 0">
                <span>⚠ </span><span><?=esc_html($error_msg)?></span>
            </div>
            <?php endif ?>

            <div class="logieval-create-form-card">
                <form method="post" id="logieval-create-form" onsubmit="var b=this.querySelector('button[type=submit]');if(b){if(b.disabled)return false;b.disabled=true;b.textContent='Création en cours…';}">
                    <?php wp_nonce_field('logieval_create_eval') ?>
                    <input type="hidden" name="logieval_create_eval" value="1">

                    <div class="logieval-form-group">
                        <label class="logieval-form-label">Classe <span class="logieval-required">*</span></label>
                        <select name="classe_id" id="create-classe-id" class="logieval-form-select" required onchange="updateEnseignants(this.value)">
                            <option value="">— Sélectionner une classe —</option>
                            <?php foreach ($classes_as_maitre as $cl): ?>
                            <option value="<?=$cl['id']?>"><?=esc_html($cl['nom'])?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="logieval-form-group">
                        <label class="logieval-form-label">Semestre <span class="logieval-required">*</span></label>
                        <input type="text" name="semestre" class="logieval-form-input"
                               placeholder="ex. Semestre 1 2024-2025" required
                               value="<?=esc_attr(wp_unslash($_POST['semestre'] ?? ''))?>">
                    </div>

                    <div class="logieval-form-group">
                        <label class="logieval-form-label">Date d'échéance <span class="logieval-required">*</span></label>
                        <input type="date" name="date_echeance" class="logieval-form-input"
                               required min="<?=date('Y-m-d')?>"
                               value="<?=esc_attr(wp_unslash($_POST['date_echeance'] ?? ''))?>">
                    </div>

                    <!-- Enseignants per class (shown dynamically) -->
                    <?php foreach ($classes_as_maitre as $cl):
                        $ens_list = $all_ens_by_class[$cl['id']] ?? [];
                    ?>
                    <div class="logieval-ens-select logieval-hidden" id="ens-group-<?=$cl['id']?>">
                        <label class="logieval-form-label">Enseignants participants</label>
                        <?php if (empty($ens_list)): ?>
                            <p class="logieval-empty">Aucun enseignant affecté à cette classe.</p>
                        <?php else: ?>
                        <div class="logieval-ens-checkboxes">
                            <?php foreach ($ens_list as $e): ?>
                            <label class="logieval-ens-check-label">
                                <input type="checkbox" name="enseignant_ids[]"
                                       value="<?=$e['id']?>" checked>
                                <?=esc_html($e['nom'].' '.$e['prenom'])?>
                            </label>
                            <?php endforeach ?>
                        </div>
                        <?php endif ?>
                    </div>
                    <?php endforeach ?>

                    <!-- Previous semester option -->
                    <?php foreach ($classes_as_maitre as $cl): ?>
                    <div class="logieval-prev-select logieval-hidden" id="prev-group-<?=$cl['id']?>">
                        <?php if (isset($prev_by_class[$cl['id']])): $prev=$prev_by_class[$cl['id']]; ?>
                        <label class="logieval-form-label" style="display:flex;align-items:center;gap:10px">
                            <input type="checkbox" name="import_previous" value="1">
                            <span>Reprendre les notes du semestre précédent
                                <small style="color:#666;display:block"><?=esc_html($prev['semestre'])?></small>
                            </span>
                        </label>
                        <?php endif ?>
                    </div>
                    <?php endforeach ?>

                    <div class="logieval-form-group">
                        <label class="logieval-form-label">Notifications optionnelles</label>
                        <div class="logieval-ens-checkboxes">
                            <label class="logieval-ens-check-label">
                                <input type="checkbox" name="mail_teacher_completed" value="1" <?=!empty($_POST['mail_teacher_completed'])?'checked':''?>>
                                Informer les enseignants en attente lorsqu’un collègue termine
                            </label>
                            <label class="logieval-ens-check-label">
                                <input type="checkbox" name="mail_all_completed" value="1" <?=!empty($_POST['mail_all_completed'])?'checked':''?>>
                                Informer le maître de classe lorsque tous les enseignants ont terminé
                            </label>
                            <label class="logieval-ens-check-label">
                                <input type="checkbox" name="mail_closure_maitre" value="1" <?=!empty($_POST['mail_closure_maitre'])?'checked':''?>>
                                Informer le maître de classe lorsque l’évaluation est clôturée
                            </label>
                        </div>
                        <small class="logieval-form-help">
                            L’attribution initiale, le rappel avant échéance et la relance du dernier enseignant en attente sont toujours envoyés.
                        </small>
                    </div>

                    <div class="logieval-form-actions">
                        <button type="submit" class="logieval-btn logieval-btn-primary logieval-btn-large">
                            ✚ Créer & notifier les enseignants
                        </button>
                        <a href="<?=esc_url(self::portal_url())?>" class="logieval-btn logieval-btn-secondary">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
            </div>
        </div>

        <script>
        var ensGroups = <?=json_encode(array_column($classes_as_maitre, 'id'))?>;
        var prevGroups = <?=json_encode(array_keys($prev_by_class))?>;

        function updateEnseignants(classeId) {
            ensGroups.forEach(function(id) {
                var el = document.getElementById('ens-group-' + id);
                if (el) el.classList.toggle('logieval-hidden', String(id) !== String(classeId));
                // Uncheck all checkboxes in hidden groups so they don't submit
                if (el ? String(id) !== String(classeId) : false) {
                    el.querySelectorAll('input[type="checkbox"]').forEach(function(cb){ cb.name = ''; });
                } else if (el) {
                    el.querySelectorAll('input[type="checkbox"]').forEach(function(cb){ cb.name = 'enseignant_ids[]'; });
                }
            });
            prevGroups.forEach(function(id) {
                var el = document.getElementById('prev-group-' + id);
                if (el) el.classList.toggle('logieval-hidden', String(id) !== String(classeId));
            });
        }
        </script>
        <?php return ob_get_clean();
    }

    /* ── Process evaluation creation ────────────────────────────── */
    private function do_create_evaluation( int $ens_id ): array {
        global $wpdb;
        $classe_id     = intval($_POST['classe_id'] ?? 0);
        $semestre      = sanitize_text_field(wp_unslash($_POST['semestre'] ?? ''));
        $date_echeance = sanitize_text_field(wp_unslash($_POST['date_echeance'] ?? ''));
        $import_prev   = !empty($_POST['import_previous']);

        if (!$classe_id)     return ['success'=>false, 'message'=>'Veuillez sélectionner une classe.'];
        if (!$semestre)      return ['success'=>false, 'message'=>'Le semestre est obligatoire.'];
        if (!$date_echeance) return ['success'=>false, 'message'=>'La date d\'échéance est obligatoire.'];
        if (!self::valid_date($date_echeance)) return ['success'=>false, 'message'=>'Date d\'échéance invalide.'];
        if (strtotime($date_echeance) <= time()) return ['success'=>false, 'message'=>'La date d\'échéance doit être dans le futur.'];

        // Verify this teacher is maître of this class
        if (!LOGIEVAL_Auth::is_maitre($ens_id, $classe_id)) {
            return ['success'=>false, 'message'=>'Vous n\'êtes pas maître de cette classe.'];
        }

        $t = LOGIEVAL_Database::tables();
        // Anti double-clic : même classe + même semestre créée il y a moins de 60 s
        $dup = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['evaluations']} WHERE classe_id=%d AND semestre=%s AND date_creation >= (NOW() - INTERVAL 60 SECOND) LIMIT 1",
            $classe_id, $semestre
        ));
        if ($dup) return ['success'=>true, 'message'=>'Évaluation déjà créée.', 'eval_id'=>$dup];
        $ens_ids = $this->filter_active_class_enseignants(
            array_map('intval', (array)($_POST['enseignant_ids'] ?? [])),
            $classe_id
        );

        // Create evaluation
        $eval_id = LOGIEVAL_Database::insert($t['evaluations'], [
            'classe_id'     => $classe_id,
            'semestre'      => $semestre,
            'date_echeance' => $date_echeance,
            'created_by'    => 0, // front-end creation
            'statut'        => 'active',
            'mode_calcul'   => 'proportionnel',
            'mail_teacher_completed' => !empty($_POST['mail_teacher_completed']) ? 1 : 0,
            'mail_all_completed'     => !empty($_POST['mail_all_completed']) ? 1 : 0,
            'mail_closure_maitre'    => !empty($_POST['mail_closure_maitre']) ? 1 : 0,
        ]);

        if (!$eval_id) return ['success'=>false, 'message'=>'Erreur lors de la création en base de données.'];

        // Assign teachers (fall back to all class teachers if none selected)
        if (empty($ens_ids)) {
            $ens_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ce.enseignant_id
                 FROM {$t['classe_enseignant']} ce
                 INNER JOIN {$t['enseignants']} e ON e.id = ce.enseignant_id
                 WHERE ce.classe_id=%d AND e.actif=1",
                $classe_id
            )) ?: [];
        }
        if (empty($ens_ids)) {
            LOGIEVAL_Database::delete($t['evaluations'], ['id' => $eval_id]);
            return ['success'=>false, 'message'=>'Aucun enseignant actif n’est affecté à cette classe. Activez au moins un profil enseignant avant de créer l’évaluation.'];
        }
        foreach ($ens_ids as $eid) {
            if ($eid > 0) {
                LOGIEVAL_Database::insert($t['eval_enseignant'], [
                    'evaluation_id' => $eval_id,
                    'enseignant_id' => $eid,
                ]);
            }
        }

        // Import previous semester notes if requested
        if ($import_prev) {
            $prev = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$t['evaluations']} WHERE classe_id=%d AND id!=%d ORDER BY date_creation DESC LIMIT 1",
                $classe_id, $eval_id
            ), ARRAY_A);
            if ($prev) {
                $results = LOGIEVAL_Calculator::full_results((int)$prev['id']);
                foreach ($results as $eleve_id => $data) {
                    if ($data['note'] !== null) {
                        LOGIEVAL_Database::insert($t['notes_ref'], [
                            'evaluation_id_source' => $prev['id'],
                            'evaluation_id_dest'   => $eval_id,
                            'eleve_id'             => $eleve_id,
                            'note_calculee'        => $data['note'],
                            'points_obtenus'       => $data['avg_pts'] ?? $data['pts'] ?? 0,
                        ]);
                    }
                }
            }
        }

        // Send notifications
        LOGIEVAL_Mailer::later('notify_assignment', [$eval_id]);

        return ['success'=>true, 'eval_id'=>$eval_id];
    }

    public function handle_ajax(): void {
        // Use wp_verify_nonce instead of check_ajax_referer so we return JSON on failure
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!self::verify_nonce($nonce)) {
            // Page restée ouverte longtemps : si l'enseignant est toujours connecté, on lui donne un nouveau
            // jeton et la page refait la demande toute seule (rien n'est perdu).
            if (LOGIEVAL_Auth::is_logged_in() || current_user_can( 'lp_eval_admin' )) {
                wp_send_json_error(['message' => "La page a expiré. Nouvel essai…", 'code' => 'nonce_expired', 'nonce' => self::create_nonce()]);
            }
            wp_send_json_error(['message' => "Votre session a expiré : rechargez la page et saisissez à nouveau votre identifiant.", 'code' => 'session_expired']);
            return;
        }
        if (!LOGIEVAL_Auth::is_logged_in() && !current_user_can( 'lp_eval_admin' )) {
            wp_send_json_error(['message' => "Votre session a expiré : rechargez la page et saisissez à nouveau votre identifiant.", 'code' => 'session_expired']);
            return;
        }
        $a = sanitize_key(wp_unslash($_POST['sub_action'] ?? ''));
        switch ($a) {

            case 'mark_done':             $this->ajax_mark_done();             break;
            case 'update_eval_settings':  $this->ajax_update_eval_settings();  break;
            case 'force_close_finished':  $this->ajax_force_close_finished();  break;
            case 'reopen_evaluation':     $this->ajax_reopen_evaluation();     break;
            case 'send_class_report':      $this->ajax_send_class_report();     break;
            case 'send_pending_reminder':  $this->ajax_send_pending_reminder(); break;
            case 'mark_undone':           $this->ajax_mark_undone();           break;
            case 'master_mark_teacher_undone': $this->ajax_master_mark_teacher_undone(); break;
            case 'get_others_responses':  $this->ajax_get_others_responses();  break;
            case 'delete_evaluation':     $this->ajax_delete_evaluation();     break;
            case 'toggle_ne':             $this->ajax_toggle_ne();             break;
            case 'save_vote':             $this->ajax_save_vote();             break;
            case 'fill_yes':              $this->ajax_fill_yes();              break;
            case 'save_comment':          $this->ajax_save_comment();          break;
            case 'save_arbitrage':        $this->ajax_save_arbitrage();        break;
            default:
                wp_send_json_error(['message' => "Action inconnue."]);
        }
    }



    private function ajax_mark_done(): void {
        try {
            $ens_id  = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id = intval($_POST['eval_id'] ?? 0);
            if (!$ens_id) { wp_send_json_error(['message'=>"Session expirée. Veuillez vous reconnecter."]); return; }
            if (!$eval_id) { wp_send_json_error(['message'=>"Identifiant d'évaluation manquant."]); return; }

            $t = LOGIEVAL_Database::tables();
            $ee = LOGIEVAL_Database::get_row($t['eval_enseignant'],['evaluation_id'=>$eval_id,'enseignant_id'=>$ens_id]);
            if (!$ee) { wp_send_json_error(['message'=>"Vous n'êtes pas attribué à cette évaluation."]); return; }
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id' => $eval_id]);
            if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
            if ($eval['statut'] !== 'active') { wp_send_json_error(['message'=>"Cette évaluation est clôturée."]); return; }
            $missing = $this->missing_required_votes($eval_id, $ens_id);
            if (!empty($missing)) {
                $preview = implode(', ', array_slice($missing, 0, 5));
                $suffix  = count($missing) > 5 ? '…' : '';
                wp_send_json_error(['message'=>"Impossible de terminer : certains critères ne sont pas renseignés. À compléter : {$preview}{$suffix}"]);
                return;
            }

            global $wpdb;
            $changed_to_done = (bool) $wpdb->update($t['eval_enseignant'],
                ['statut'=>'termine','date_fin'=>current_time('mysql')],
                ['evaluation_id'=>$eval_id,'enseignant_id'=>$ens_id,'statut'=>'en_cours']
            );
            $pending = LOGIEVAL_Database::pending_teacher_count($eval_id);
            if ($changed_to_done && $pending === 1) {
                LOGIEVAL_Mailer::later('notify_last_teacher_pending', [$eval_id]);
            } elseif ($changed_to_done) {
                LOGIEVAL_Mailer::later('notify_teacher_completed', [$eval_id, $ens_id]);
            }
            if ($pending === 0) {
                if ($changed_to_done) {
                    LOGIEVAL_Mailer::later('notify_all_teachers_completed', [$eval_id, false]);
                }
                $closed = $wpdb->update($t['evaluations'], ['statut'=>'cloturee'], ['id'=>$eval_id, 'statut'=>'active']);
                if ($closed) {
                    LOGIEVAL_Calculator::freeze_grid($eval_id);
                    LOGIEVAL_Mailer::later('notify_closure', [$eval_id]);
                }
            }
            wp_send_json_success([
                'auto_closed' => $pending===0,
                'pending' => $pending,
            ]);
        } catch (Exception $e) {
            error_log('LogiEval mark done exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_update_eval_settings(): void {
        try {
            $ens_id  = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id = intval($_POST['eval_id'] ?? 0);
            $date_echeance = sanitize_text_field(wp_unslash($_POST['date_echeance'] ?? ''));
            $enseignant_ids = array_values(array_unique(array_map('intval', (array)($_POST['enseignant_ids'] ?? []))));
            if (!$ens_id || !$eval_id || !$date_echeance || empty($enseignant_ids)) {
                wp_send_json_error(['message'=>"Paramètres manquants."]);
                return;
            }
            if (!self::valid_date($date_echeance)) {
                wp_send_json_error(['message'=>"Date de remise invalide."]);
                return;
            }

            global $wpdb;
            $t = LOGIEVAL_Database::tables();
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
            if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
            if ($eval['statut'] === 'cloturee') { wp_send_json_error(['message'=>"Une évaluation clôturée doit d'abord être réouverte."]); return; }
            if (!LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
                wp_send_json_error(['message'=>"Action réservée au maître de classe."]);
                return;
            }
            if (LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id']) && !in_array($ens_id, $enseignant_ids, true)) {
                $enseignant_ids[] = $ens_id;
            }
            $enseignant_ids = $this->filter_active_class_enseignants($enseignant_ids, (int)$eval['classe_id']);
            if (empty($enseignant_ids) || !in_array($ens_id, $enseignant_ids, true)) {
                wp_send_json_error(['message'=>"Sélectionnez au moins un enseignant actif de cette classe, dont le maître de classe."]);
                return;
            }

            LOGIEVAL_Database::update($t['evaluations'], [
                'date_echeance'=>$date_echeance,
                'last_teacher_reminder_sent'=>0,
            ], ['id'=>$eval_id]);

            $existing_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d",
                $eval_id
            )) ?: []);

            $added_teacher_ids = array_values(array_diff($enseignant_ids, $existing_ids));
            foreach ($added_teacher_ids as $add_id) {
                LOGIEVAL_Database::insert($t['eval_enseignant'], [
                    'evaluation_id' => $eval_id,
                    'enseignant_id' => (int)$add_id,
                ]);
            }
            foreach (array_diff($existing_ids, $enseignant_ids) as $remove_id) {
                $wpdb->delete($t['eval_enseignant'], [
                    'evaluation_id' => $eval_id,
                    'enseignant_id' => (int)$remove_id,
                ]);
            }
            if (!empty($added_teacher_ids)) {
                LOGIEVAL_Mailer::later('notify_assignment', [$eval_id, $added_teacher_ids]);
            }

            wp_send_json_success(['updated'=>true]);
        } catch (Exception $e) {
            error_log('LogiEval update eval settings exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_force_close_finished(): void {
        try {
            global $wpdb;
            $ens_id  = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id = intval($_POST['eval_id'] ?? 0);
            if (!$ens_id || !$eval_id) { wp_send_json_error(['message'=>"Paramètres manquants."]); return; }
            $t = LOGIEVAL_Database::tables();
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
            if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
            if ($eval['statut'] !== 'active') { wp_send_json_error(['message'=>"Cette évaluation est déjà clôturée."]); return; }
            if (!LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
                wp_send_json_error(['message'=>"Action réservée au maître de classe."]); return;
            }
            $missing = $this->missing_required_votes($eval_id, $ens_id);
            if (!empty($missing)) {
                wp_send_json_error(['message'=>"Impossible de clôturer : votre propre évaluation n'est pas complète."]);
                return;
            }
            $changed_to_done = (bool) $wpdb->update($t['eval_enseignant'],
                ['statut'=>'termine','date_fin'=>current_time('mysql')],
                ['evaluation_id'=>$eval_id,'enseignant_id'=>$ens_id,'statut'=>'en_cours']
            );
            if ($changed_to_done) {
                LOGIEVAL_Mailer::later('notify_teacher_completed', [$eval_id, $ens_id]);
            }
            $closed = $wpdb->update($t['evaluations'], ['statut'=>'cloturee'], ['id'=>$eval_id, 'statut'=>'active']);
            if ($closed) {
                LOGIEVAL_Calculator::freeze_grid($eval_id);
                LOGIEVAL_Mailer::later('notify_closure', [$eval_id]);
            }
            wp_send_json_success(['forced'=>true]);
        } catch (Exception $e) {
            error_log('LogiEval force close exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_reopen_evaluation(): void {
        try {
            $ens_id  = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id = intval($_POST['eval_id'] ?? 0);
            if (!$ens_id || !$eval_id) { wp_send_json_error(['message'=>"Paramètres manquants."]); return; }

            $t = LOGIEVAL_Database::tables();
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
            if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
            if (!LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
                wp_send_json_error(['message'=>"Action réservée au maître de classe."]); return;
            }
            if ($eval['statut'] !== 'cloturee') {
                wp_send_json_success(['reopened'=>false]);
                return;
            }

            LOGIEVAL_Database::update($t['evaluations'], [
                'statut'=>'active',
                'last_teacher_reminder_sent'=>0,
            ], ['id'=>$eval_id]);
            delete_transient('logieval_class_report_key_'.$eval_id);
            LOGIEVAL_Calculator::unfreeze_grid($eval_id);
            wp_send_json_success(['reopened'=>true]);
        } catch (Exception $e) {
            error_log('LogiEval reopen evaluation exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_send_class_report(): void {
        try {
            $ens_id  = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id = intval($_POST['eval_id'] ?? 0);
            if (!$ens_id || !$eval_id) { wp_send_json_error(['message'=>"Paramètres manquants."]); return; }

            $t = LOGIEVAL_Database::tables();
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
            if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
            if ($eval['statut'] !== 'cloturee') {
                wp_send_json_error(['message'=>"Le rapport ne peut être envoyé que lorsque l'évaluation est clôturée."]); return;
            }
            if (!LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
                wp_send_json_error(['message'=>"Action réservée au maître de classe."]); return;
            }

            $report_url = $this->class_report_email_url($eval_id);
            $sent = LOGIEVAL_Mailer::send_class_report($eval_id, $report_url);
            if ($sent <= 0) {
                wp_send_json_error(['message'=>"Aucun enseignant attribué avec une adresse e-mail valide."]); return;
            }
            wp_send_json_success(['sent'=>$sent]);
        } catch (Exception $e) {
            error_log('LogiEval send class report exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_send_pending_reminder(): void {
        try {
            $ens_id  = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id = intval($_POST['eval_id'] ?? 0);
            if (!$ens_id || !$eval_id) { wp_send_json_error(['message'=>"Paramètres manquants."]); return; }

            $t = LOGIEVAL_Database::tables();
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
            if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
            if ($eval['statut'] !== 'active') {
                wp_send_json_error(['message'=>"Aucun rappel ne peut être envoyé pour une évaluation clôturée."]); return;
            }
            if (!LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
                wp_send_json_error(['message'=>"Action réservée au maître de classe."]); return;
            }

            $sent = LOGIEVAL_Mailer::notify_pending_completion($eval_id);
            if ($sent <= 0) {
                wp_send_json_error(['message'=>"Aucun autre enseignant n'est actuellement en attente."]); return;
            }
            wp_send_json_success(['sent'=>$sent]);
        } catch (Exception $e) {
            error_log('LogiEval send pending reminder exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function unresolved_arbitrages( int $eval_id, bool $completed_only = false ): array {
        // Since v2.0.47 equal oui/non votes automatically award half-points.
        // No arbitration can block completion or closure anymore.
        return [];
    }

    private function missing_required_votes( int $eval_id, int $ens_id ): array {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
        if (!$eval) return ['évaluation introuvable'];

        $is_maitre = LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id']);
        $eleves    = LOGIEVAL_Database::eval_students($eval); // 1.53.1
        $objectifs = LOGIEVAL_Database::get_rows($t['objectifs'], ['actif'=>1], 'ordre');
        $all_sc    = $wpdb->get_results("SELECT * FROM {$t['sous_criteres']} ORDER BY objectif_id,ordre", ARRAY_A) ?: [];
        $sous_by_obj = [];
        foreach ($all_sc as $sc) $sous_by_obj[$sc['objectif_id']][] = $sc;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, sous_critere_id, vote
             FROM {$t['reponses']}
             WHERE evaluation_id=%d AND enseignant_id=%d AND vote_set=1",
            $eval_id, $ens_id
        ), ARRAY_A) ?: [];
        $votes = [];
        foreach ($rows as $row) $votes[$row['eleve_id']][$row['sous_critere_id']] = $row['vote'];

        $ne_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, objectif_id FROM {$t['non_evaluables']}
             WHERE evaluation_id=%d AND enseignant_id=%d",
            $eval_id, $ens_id
        ), ARRAY_A) ?: [];
        $non_eval = [];
        foreach ($ne_rows as $row) $non_eval[$row['eleve_id']][$row['objectif_id']] = true;

        $missing = [];
        foreach ($eleves as $eleve) {
            $eleve_id = (int)$eleve['id'];
            $eleve_label = $eleve['nom'].' '.$eleve['prenom'];
            foreach ($objectifs as $obj) {
                $obj_id = (int)$obj['id'];
                if (!empty($obj['maitre_seulement']) && !$is_maitre) continue;
                if (!empty($non_eval[$eleve_id][$obj_id])) continue;
                $scs = $sous_by_obj[$obj_id] ?? [];
                if ($obj['type'] === 'radio') {
                    $has_choice = false;
                    foreach ($scs as $sc) {
                        if (($votes[$eleve_id][$sc['id']] ?? '') === 'oui') { $has_choice = true; break; }
                    }
                    if (!$has_choice) $missing[] = $eleve_label.' / '.$obj['libelle'];
                } else {
                    foreach ($scs as $sc) {
                        if (!isset($votes[$eleve_id][$sc['id']])) {
                            $missing[] = $eleve_label.' / '.$sc['lettre'];
                        }
                    }
                }
            }
        }
        return $missing;
    }

    private function clean_print_filename_part( string $value ): string {
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value, " .-_\t\n\r\0\x0B");
        return $value !== '' ? $value : 'evaluation';
    }

    private function print_student_label( array $eleve ): string {
        $nom = trim((string)($eleve['nom'] ?? ''));
        $prenom = trim((string)($eleve['prenom'] ?? ''));
        $label = trim($nom . ' ' . $prenom);
        if ($label === '') {
            $label = trim($prenom . ' ' . $nom);
        }
        return $label !== '' ? $label : 'Élève';
    }

    private function print_document_basename( array $classe, array $eval, array $eleves, int $only_eleve_id, string $print_mode = '' ): string {
        $base = 'LogiEval_' . ($classe['nom'] ?? 'classe');
        if ($only_eleve_id > 0 && !empty($eleves[0])) {
            $base .= '_' . ($eval['semestre'] ?? 'semestre');
            $base .= '_' . $this->print_student_label($eleves[0]);
        } elseif ($print_mode === 'students') {
            $base .= '_' . ($eval['semestre'] ?? 'semestre');
            $base .= '_tous_les_eleves';
        }
        return $this->clean_print_filename_part($base);
    }

    private function render_print_document( int $eval_id, int $only_eleve_id = 0, string $print_mode = '' ): void {
        global $wpdb;
        nocache_headers();
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Referrer-Policy: no-referrer');
            header('X-Frame-Options: SAMEORIGIN');
        }
        $t = LOGIEVAL_Database::tables();
        $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
        if (!$eval) wp_die('Évaluation introuvable.');
        $assigned = LOGIEVAL_Database::get_row($t['eval_enseignant'], ['evaluation_id'=>$eval_id, 'enseignant_id'=>$ens_id]);
        $is_maitre = LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id']);
        $report_key_allows_assigned_teacher = $this->valid_report_key($eval_id) && $ens_id && $assigned;
        if (!$is_maitre && !current_user_can( 'lp_eval_admin' ) && !$report_key_allows_assigned_teacher) wp_die('Accès refusé.');
        if ($print_mode === 'students' && $eval['statut'] !== 'cloturee') {
            wp_die('L’impression groupée des fiches élèves est disponible uniquement lorsque l’évaluation est clôturée.');
        }

        $classe = LOGIEVAL_Database::get_row($t['classes'], ['id'=>$eval['classe_id']]);
        $maitre = $wpdb->get_row($wpdb->prepare(
            "SELECT e.prenom, e.nom FROM {$t['enseignants']} e
             INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id=e.id
             WHERE ce.classe_id=%d AND ce.est_maitre=1
             ORDER BY e.nom, e.prenom LIMIT 1",
            $eval['classe_id']
        ), ARRAY_A);
        $maitre_name = $maitre ? $maitre['prenom'].' '.$maitre['nom'] : 'Maître de classe';
        $eleves = LOGIEVAL_Database::eval_students($eval); // 1.53.1
        if ($only_eleve_id) {
            $eleves = array_values(array_filter($eleves, fn($e) => (int)$e['id'] === $only_eleve_id));
        }
        $print_basename = $this->print_document_basename($classe, $eval, $eleves, $only_eleve_id, $print_mode);
        $grid = LOGIEVAL_Calculator::grid($eval_id); // figée si l'évaluation est clôturée
        $objectifs = $grid['objectifs'];
        $sous_by_obj = $grid['sous_by_obj'];
        $hide_master_only = !$is_maitre;

        $results = LOGIEVAL_Calculator::full_results($eval_id);
        $votes = LOGIEVAL_Calculator::get_votes($eval_id);
        $arbitrages = LOGIEVAL_Calculator::get_arbitrages($eval_id);
        $non_evaluables_by_teacher = LOGIEVAL_Calculator::get_non_evaluables($eval_id);
        $closed_teacher_filter = $eval['statut'] === 'cloturee' ? " AND statut='termine'" : '';
        $closed_teacher_join_filter = $eval['statut'] === 'cloturee' ? " AND ee.statut='termine'" : '';
        $ens_ids_eval = $wpdb->get_col($wpdb->prepare(
            "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d{$closed_teacher_filter}", $eval_id
        )) ?: [];
        $total_max = (float)$grid['total_max'];
        $pending_teachers = $wpdb->get_col($wpdb->prepare(
            "SELECT CONCAT(e.prenom,' ',e.nom)
             FROM {$t['eval_enseignant']} ee
             INNER JOIN {$t['enseignants']} e ON e.id=ee.enseignant_id
             WHERE ee.evaluation_id=%d AND ee.statut!='termine'
             ORDER BY e.nom, e.prenom",
            $eval_id
        )) ?: [];
        $is_partial_closure = $eval['statut'] === 'cloturee' && !empty($pending_teachers);
        $is_class_print = $only_eleve_id === 0 && $print_mode !== 'students';
        $is_students_batch_print = $only_eleve_id === 0 && $print_mode === 'students';
        $teacher_summary_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.prenom, e.nom, ee.statut, ee.date_fin, COALESCE(ce.est_maitre,0) AS est_maitre
             FROM {$t['eval_enseignant']} ee
             INNER JOIN {$t['enseignants']} e ON e.id=ee.enseignant_id
             LEFT JOIN {$t['classe_enseignant']} ce ON ce.classe_id=%d AND ce.enseignant_id=e.id
             WHERE ee.evaluation_id=%d
             ORDER BY ce.est_maitre DESC, e.nom, e.prenom",
            $eval['classe_id'],
            $eval_id
        ), ARRAY_A) ?: [];
        $teacher_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.eleve_id, r.sous_critere_id, r.vote, r.vote_set, r.commentaire,
                    CONCAT(e.prenom,' ',e.nom) AS ens_nom
             FROM {$t['reponses']} r
             INNER JOIN {$t['enseignants']} e ON e.id = r.enseignant_id
             INNER JOIN {$t['eval_enseignant']} ee ON ee.evaluation_id=r.evaluation_id AND ee.enseignant_id=r.enseignant_id
             WHERE r.evaluation_id=%d {$closed_teacher_join_filter}
             ORDER BY e.nom, e.prenom",
            $eval_id
        ), ARRAY_A) ?: [];
        $teacher_details = [];
        foreach ($teacher_rows as $tr) {
            $teacher_details[(int)$tr['eleve_id']][(int)$tr['sous_critere_id']][] = $tr;
        }
        $document_title = 'Evaluation de votre posture professionnelle';
        $title = $print_basename;
        ?><!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?=esc_html($title)?></title>
            <style>
                @page{size:A4;margin:10mm}
                body{font-family:Arial,sans-serif;color:#1f2937;margin:24px;background:#fff}
                .toolbar{position:sticky;top:0;background:#fff;border:1px solid #ddd;padding:10px;margin-bottom:18px}
                .toolbar button{padding:8px 14px;border:0;border-radius:4px;background:#067030;color:#fff;font-weight:700;cursor:pointer}
                .print-hint{display:inline-block;margin-left:12px;color:#475569;font-size:13px}
                .sheet{background:#fff;padding:0;margin:0 auto 24px;max-width:980px;display:flex;flex-direction:column}
                .logieval-summary-sheet{break-after:page;page-break-after:always}
                .logieval-student-print-sheet{break-after:page;page-break-after:always}
                .logieval-student-print-sheet{min-height:1040px}
                .sheet:last-child{page-break-after:auto}
                .doc-header{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:14px;border-bottom:2px solid #e2e8f0;padding-bottom:12px}
                h1{font-size:22px;font-weight:400;margin:0 0 14px;color:#1A1A2E}
                h2{font-size:15px;font-weight:400;margin:18px 0 10px;color:#067030}
                .student-title{font-size:18px;margin:2px 0 4px;color:#067030}
                .meta{font-size:12px;color:#334155;margin-bottom:12px}.score{font-size:18px;font-weight:800;color:#067030;margin-bottom:4px}
                .objective-block{break-inside:avoid;page-break-inside:avoid}
                table{width:100%;border-collapse:collapse;margin:8px 0 18px} th,td{border:1px solid #d8dee9;padding:8px 9px;vertical-align:top;font-size:12px;line-height:1.25} th{background:#fff;text-align:left;color:#555;font-weight:700}
                .teacher-detail{font-size:11px;margin:0 0 4px}.teacher-comment{color:#475569;font-style:italic}
                .partial-warning{border:1px solid #f59e0b;background:#fffbeb;color:#92400e;padding:10px;margin:10px 0 14px;font-weight:700}
                .pts{font-weight:800}.ok{color:#067030}.ko{color:#b00000}.empty{color:#b45309;font-weight:700}
                .signature{margin-top:auto;border:2px solid #cbd5e1;padding:10px;min-height:66px;display:flex;justify-content:space-between;gap:18px;align-items:flex-start}
                .signature strong{display:block;margin-bottom:5px}.signature-box{border:1px dashed #94a3b8;height:44px;flex:1}
                body.logieval-class-print{margin:14px}
                body.logieval-class-print .sheet{max-width:1020px;margin-bottom:10px}
                body.logieval-class-print .doc-header{margin-bottom:7px;padding-bottom:6px}
                body.logieval-class-print h1{font-size:19px;margin-bottom:6px}
                body.logieval-class-print h2{font-size:12px;margin:8px 0 4px}
                body.logieval-class-print .student-title{font-size:14px;margin:1px 0 2px}
                body.logieval-class-print .score{font-size:14px;margin-bottom:2px}
                body.logieval-class-print .meta{font-size:9px;margin-bottom:4px}
                body.logieval-class-print table{margin:3px 0 7px}
                body.logieval-class-print th,body.logieval-class-print td{font-size:8.2px;line-height:1.08;padding:2px 3px}
                body.logieval-class-print .teacher-detail{font-size:7.6px;line-height:1.05;margin:0 0 2px}
                body.logieval-class-print .teacher-comment{display:inline;font-size:7.4px}
                @media print{html,body{background:#fff;margin:0;padding:0}.toolbar,.print-hint{display:none}.sheet{border:0;margin:0;max-width:none;min-height:auto;padding:0;font-size:10px}.logieval-summary-sheet{break-after:page;page-break-after:always}.logieval-student-print-sheet{min-height:calc(297mm - 20mm);break-after:page;page-break-after:always}.sheet:last-child{break-after:auto;page-break-after:auto}.doc-header{margin-bottom:8px;padding-bottom:8px}h1{font-size:18px;margin-bottom:9px}h2{font-size:12px;margin:9px 0 5px}.student-title{font-size:14px}.meta{font-size:9px;margin-bottom:5px}.score{font-size:14px}.objective-block{break-inside:avoid;page-break-inside:avoid}table{margin:4px 0 9px;break-inside:avoid;page-break-inside:avoid}th,td{font-size:9px;line-height:1.13;padding:4px 5px}tr{break-inside:avoid;page-break-inside:avoid}.signature{min-height:42px;padding:6px;font-size:9px;break-inside:avoid;page-break-inside:avoid}.signature-box{height:28px}body.logieval-class-print .doc-header{margin-bottom:4px;padding-bottom:4px}body.logieval-class-print h1{font-size:15px;margin-bottom:4px}body.logieval-class-print h2{font-size:9.2px;margin:5px 0 2px}body.logieval-class-print .student-title{font-size:11px;margin:0 0 1px}body.logieval-class-print .score{font-size:11px;margin-bottom:1px}body.logieval-class-print .meta{font-size:7.5px;margin-bottom:2px}body.logieval-class-print table{margin:2px 0 4px}body.logieval-class-print th,body.logieval-class-print td{font-size:7.3px;line-height:1.03;padding:1.5px 2px}body.logieval-class-print .teacher-detail{font-size:6.8px;line-height:1.02;margin:0 0 1px}body.logieval-class-print .teacher-comment{display:inline;font-size:6.7px}body.logieval-class-print .objective-block{break-inside:avoid;page-break-inside:avoid}}
            </style>
        </head>
        <body class="<?=$is_class_print ? 'logieval-class-print' : ($is_students_batch_print ? 'logieval-students-print' : 'logieval-single-print')?>">
        <div class="toolbar"><a href="<?=esc_url(self::page_link(['logieval_eval'=>(int)$eval_id]))?>" onclick="if (window.opener) { window.close(); return false; } if (history.length > 1) { history.back(); return false; }" style="margin-right:10px">← Retour à l’évaluation</a><button onclick="window.print()">Imprimer</button><span class="print-hint">Si une URL apparaît en bas de page, désactivez « En-têtes et pieds de page » dans les options d'impression du navigateur.</span></div>
        <?php if ($is_class_print): ?>
        <section class="sheet logieval-summary-sheet">
            <div class="doc-header">
                <div>
                    <h1><?=esc_html($document_title)?></h1>
                    <div class="meta">Classe <?=esc_html($classe['nom'])?> | <?=esc_html($eval['semestre'])?> | Récapitulatif</div>
                </div>
            </div>
            <?php if ($is_partial_closure): ?>
            <div class="partial-warning">
                Clôture partielle : le maître de classe a finalisé cette évaluation sans attendre
                <?=esc_html(implode(', ', $pending_teachers))?>.
                Ces enseignants ne sont pas pris en compte dans les résultats.
            </div>
            <?php endif ?>
            <h2>Enseignants ayant participé à l'évaluation</h2>
            <table>
                <thead><tr><th>Enseignant</th><th style="width:150px">Rôle</th><th style="width:190px">Clôture de l'évaluation</th></tr></thead>
                <tbody>
                <?php foreach ($teacher_summary_rows as $teacher_row): ?>
                <tr>
                    <td><?=esc_html($teacher_row['prenom'].' '.$teacher_row['nom'])?></td>
                    <td><?=!empty($teacher_row['est_maitre']) ? 'Maître de classe' : 'Enseignant'?></td>
                    <td>
                        <?=!empty($teacher_row['date_fin'])
                            ? esc_html(date_i18n('d.m.Y H:i', strtotime($teacher_row['date_fin'])))
                            : '<span class="empty">Non clôturée</span>'?>
                    </td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <h2>Récapitulatif des élèves</h2>
            <table>
                <thead><tr><th>Élève</th><th style="width:160px">Points</th><th style="width:120px">Note /6</th></tr></thead>
                <tbody>
                <?php foreach ($eleves as $summary_eleve):
                    $sid = (int)$summary_eleve['id'];
                    $sres = $results[$sid] ?? null;
                ?>
                <tr>
                    <td><?=esc_html($this->print_student_label($summary_eleve))?></td>
                    <td class="pts"><?= ($sres && $sres['pts'] !== null) ? number_format((float)$sres['pts'],2).' / '.number_format((float)($sres['max'] ?? $total_max),2) : '—' ?></td>
                    <td class="pts"><?= ($sres && $sres['note'] !== null) ? number_format((float)$sres['note'],1) : 'pas encore évalué' ?></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </section>
        <?php endif ?>
        <?php foreach ($eleves as $eleve):
            $eid = (int)$eleve['id'];
            $res = $results[$eid] ?? null;
        ?>
        <section class="sheet logieval-student-print-sheet">
            <div class="doc-header">
                <div>
                    <h1><?=esc_html($document_title)?></h1>
                    <div class="meta">Classe <?=esc_html($classe['nom'])?> | <?=esc_html($eval['semestre'])?></div>
                </div>
            </div>
            <div class="score"><?= ($res && $res['note'] !== null) ? number_format((float)$res['note'],1).' / 6' : '—' ?></div>
            <h2 class="student-title"><?=esc_html($this->print_student_label($eleve))?></h2>
            <div class="meta">
                Statut : <?=esc_html($eval['statut']==='cloturee'?'clôturée':'en cours')?> |
                Points : <?= ($res && $res['pts'] !== null) ? number_format((float)$res['pts'],2).' / '.number_format((float)($res['max'] ?? $total_max),2) : 'pas encore évalué' ?>
            </div>
            <?php foreach ($objectifs as $obj):
                if ($hide_master_only && !empty($obj['maitre_seulement'])) continue;
                $oid = (int)$obj['id'];
                $scs = $sous_by_obj[$oid] ?? [];
            ?>
            <div class="objective-block">
            <h2><?=esc_html($obj['libelle'])?></h2>
            <table>
                <thead><tr><th>Sous-critère</th><th style="width:150px">Point obtenu</th><?php if ($is_class_print): ?><th>Détail enseignants</th><?php endif ?></tr></thead>
                <tbody>
                <?php foreach ($scs as $sc):
                    $obtained = 0.0;
                    if ($obj['type'] === 'radio') {
                        $computed = LOGIEVAL_Calculator::compute_student_points(
                            $eid,
                            [$obj],
                            [$oid => $scs],
                            $votes,
                            $arbitrages,
                            $ens_ids_eval,
                            $non_evaluables_by_teacher,
                            $eval['mode_calcul'] ?? 'majoritaire'
                        );
                        $radio_details = $computed['details']['radio_'.$oid] ?? ['tally'=>[]];
                        $tally = $radio_details['tally'] ?? [];
                        $top = $tally ? reset($tally) : 0;
                        $top_ids = $top > 0 ? array_keys(array_filter($tally, fn($count) => $count === $top)) : [];
                        $radio_voters = array_sum($tally);
                        if (empty($obj['maitre_seulement']) && ($eval['mode_calcul'] ?? 'majoritaire') === 'proportionnel') {
                            $obtained = $radio_voters > 0
                                ? ((float)$sc['points']) * ((int)($tally[$sc['id']] ?? 0) / $radio_voters)
                                : 0.0;
                        } elseif (count($top_ids) === 1 && (int)$top_ids[0] === (int)$sc['id']) {
                            $obtained = (float)$sc['points'];
                        } elseif (count($top_ids) > 1 && in_array((int)$sc['id'], array_map('intval', $top_ids), true)) {
                            $highest_tied_pts = 0.0;
                            $half_awarded_id = 0;
                            foreach ($scs as $tie_sc) {
                                if (in_array((int)$tie_sc['id'], array_map('intval', $top_ids), true)) {
                                    if ((float)$tie_sc['points'] > $highest_tied_pts) {
                                        $highest_tied_pts = (float)$tie_sc['points'];
                                        $half_awarded_id = (int)$tie_sc['id'];
                                    }
                                }
                            }
                            if ((int)$sc['id'] === $half_awarded_id) {
                                $obtained = $highest_tied_pts / 2;
                            }
                        }
                    } else {
                        $obtained = (float)($res['details'][(int)$sc['id']]['awarded_points'] ?? 0.0);
                    }
                ?>
                <tr>
                    <td><strong><?=esc_html($sc['lettre'])?></strong> - <?=esc_html($sc['libelle'])?> <small>(<?=number_format((float)$sc['points'],1)?> pt)</small></td>
                    <td class="pts <?=$obtained > 0 ? 'ok' : 'ko'?>"><?=number_format($obtained,2)?> / <?=number_format((float)$sc['points'],2)?></td>
                    <?php if ($is_class_print): ?>
                    <td>
                        <?php foreach (($teacher_details[$eid][(int)$sc['id']] ?? []) as $td):
                            $vote_label = ((int)$td['vote_set'] === 1)
                                ? ($td['vote'] === 'oui' ? 'Oui' : ($td['vote'] === 'non' ? 'Non' : 'Abstention'))
                                : 'Non renseigné';
                        ?>
                        <div class="teacher-detail">
                            <strong><?=esc_html($td['ens_nom'])?> :</strong> <?=esc_html($vote_label)?>
                            <?php if (!empty($td['commentaire'])): ?>
                                <div class="teacher-comment"><?=esc_html($td['commentaire'])?></div>
                            <?php endif ?>
                        </div>
                        <?php endforeach ?>
                    </td>
                    <?php endif ?>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            </div>
            <?php endforeach ?>
            <?php if (!$is_class_print): ?>
            <div class="signature">
                <div>
                    <strong>Maître de classe</strong>
                    <?=esc_html($maitre_name)?>
                </div>
                <div class="signature-box"></div>
            </div>
            <?php endif ?>
        </section>
        <?php endforeach ?>
        </body>
        </html><?php
        exit;
    }

    private function ajax_mark_undone(): void {
        $ens_id=LOGIEVAL_Auth::current_enseignant_id(); $eval_id=intval($_POST['eval_id'] ?? 0);
        if (!$ens_id || !$eval_id) { wp_send_json_error(['message'=>"Paramètres manquants."]); return; }
        $eval = LOGIEVAL_Database::get_row(LOGIEVAL_Database::t('evaluations'), ['id'=>$eval_id]);
        if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
        if ($eval['statut'] !== 'active') { wp_send_json_error(['message'=>"Une évaluation clôturée doit d'abord être réouverte."]); return; }
        if (!$this->current_teacher_can_access_eval($eval_id, $ens_id)) {
            wp_send_json_error(['message'=>"Vous n'êtes pas attribué à cette évaluation."]); return;
        }
        LOGIEVAL_Database::update(LOGIEVAL_Database::t('eval_enseignant'),['statut'=>'en_cours','date_fin'=>null],['evaluation_id'=>$eval_id,'enseignant_id'=>$ens_id]);
        LOGIEVAL_Database::update(LOGIEVAL_Database::t('evaluations'), ['last_teacher_reminder_sent'=>0], ['id'=>$eval_id]);
        wp_send_json_success();
    }

    private function ajax_master_mark_teacher_undone(): void {
        $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        $eval_id = intval($_POST['eval_id'] ?? 0);
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        if (!$ens_id || !$eval_id || !$teacher_id) {
            wp_send_json_error(['message'=>"Paramètres manquants."]);
            return;
        }

        $t = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
        if (!$eval) {
            wp_send_json_error(['message'=>"Évaluation introuvable."]);
            return;
        }
        if ($eval['statut'] !== 'active') {
            wp_send_json_error(['message'=>"L'évaluation doit d'abord être réouverte."]);
            return;
        }
        if (!LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
            wp_send_json_error(['message'=>"Action réservée au maître de classe."]);
            return;
        }

        $assignment = LOGIEVAL_Database::get_row($t['eval_enseignant'], [
            'evaluation_id' => $eval_id,
            'enseignant_id' => $teacher_id,
        ]);
        if (!$assignment) {
            wp_send_json_error(['message'=>"Cet enseignant n'est pas attribué à cette évaluation."]);
            return;
        }
        if (($assignment['statut'] ?? '') !== 'termine') {
            wp_send_json_success(['updated'=>false]);
            return;
        }

        LOGIEVAL_Database::update($t['eval_enseignant'], [
            'statut' => 'en_cours',
            'date_fin' => null,
        ], [
            'evaluation_id' => $eval_id,
            'enseignant_id' => $teacher_id,
        ]);
        LOGIEVAL_Database::update($t['evaluations'], ['last_teacher_reminder_sent'=>0], ['id'=>$eval_id]);
        wp_send_json_success(['updated'=>true]);
    }

    private function ajax_save_vote(): void {
        try {
            $ens_id   = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id  = intval($_POST['eval_id']  ?? 0);
            $eleve_id = intval($_POST['eleve_id'] ?? 0);
            $sc_id    = intval($_POST['sc_id']    ?? 0);
            $vote     = sanitize_key(wp_unslash($_POST['vote'] ?? 'abstention'));
            if (!in_array($vote,['oui','non','abstention','clear'],true)) $vote = 'abstention';
            if (!$ens_id||!$eval_id||!$eleve_id||!$sc_id) {
                wp_send_json_error(['message'=>"Paramètres manquants."]); return;
            }
            $t = LOGIEVAL_Database::tables();
            $ctx = $this->validate_teacher_write_context($eval_id, $ens_id, $eleve_id, $sc_id);
            if (empty($ctx['ok'])) { wp_send_json_error(['message'=>$ctx['message']]); return; }
            $obj = $ctx['obj'];
            global $wpdb;
            $tr = LOGIEVAL_Database::t('reponses');
            // « Effacer ma réponse » : le critère redevient non renseigné (le commentaire est gardé).
            if ($vote === 'clear') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$tr} SET vote='abstention', vote_set=0, updated_at=NOW()
                     WHERE evaluation_id=%d AND enseignant_id=%d AND eleve_id=%d AND sous_critere_id=%d",
                    $eval_id,$ens_id,$eleve_id,$sc_id
                ));
                wp_send_json_success(['saved_at'=>current_time('H:i'), 'cleared'=>true]);
                return;
            }
            // Choix unique : deux clics rapides ne doivent jamais laisser deux choix enregistrés.
            $lock = 'logieval_vote_'.$eval_id.'_'.$ens_id.'_'.$eleve_id.'_'.(int)($obj['id'] ?? 0);
            $locked_ok = (bool) $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock));
            if ($obj && $obj['type'] === 'radio') {
                $vote = 'oui';
                $sibling_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$t['sous_criteres']} WHERE objectif_id=%d AND id!=%d",
                    (int)$obj['id'], $sc_id
                ));
                foreach ($sibling_ids as $sibling_id) {
                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO {$tr} (evaluation_id,enseignant_id,eleve_id,sous_critere_id,vote,vote_set,updated_at)
                         VALUES (%d,%d,%d,%d,'abstention',0,NOW())
                         ON DUPLICATE KEY UPDATE vote='abstention', vote_set=0, updated_at=NOW()",
                        $eval_id,$ens_id,$eleve_id,(int)$sibling_id
                    ));
                }
            }
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$tr} (evaluation_id,enseignant_id,eleve_id,sous_critere_id,vote,vote_set,updated_at)
                 VALUES (%d,%d,%d,%d,%s,1,NOW())
                 ON DUPLICATE KEY UPDATE vote=VALUES(vote), vote_set=1, updated_at=NOW()",
                $eval_id,$ens_id,$eleve_id,$sc_id,$vote
            ));
            if ($obj && $obj['type'] === 'radio') {
                // Garde-fou : un seul choix enregistré pour cet objectif (le dernier).
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$tr} r INNER JOIN {$t['sous_criteres']} sc ON sc.id=r.sous_critere_id
                     SET r.vote='abstention', r.vote_set=0
                     WHERE r.evaluation_id=%d AND r.enseignant_id=%d AND r.eleve_id=%d AND sc.objectif_id=%d AND r.sous_critere_id!=%d",
                    $eval_id,$ens_id,$eleve_id,(int)$obj['id'],$sc_id
                ));
            }
            if ($locked_ok) $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock));
            if ($wpdb->last_error) {
                error_log('LogiEval save vote DB error: ' . $wpdb->last_error);
                wp_send_json_error(['message'=>'Erreur base de données. Réessayez ou contactez l’administrateur.']); return;
            }
            wp_send_json_success(['saved_at'=>current_time('H:i')]);
        } catch (Exception $e) {
            error_log('LogiEval save vote exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    /**
     * « Tout Oui » : met Oui aux sous-critères Oui/Non qui ne sont pas encore renseignés,
     * pour un élève (tous les objectifs ou un seul). Ne remplace jamais une réponse déjà donnée,
     * ignore les objectifs « Non évaluable » et ceux réservés au maître de classe.
     */
    private function ajax_fill_yes(): void {
        try {
            global $wpdb;
            $ens_id   = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id  = intval($_POST['eval_id']  ?? 0);
            $eleve_id = intval($_POST['eleve_id'] ?? 0);
            $obj_id   = intval($_POST['obj_id']   ?? 0);
            if (!$ens_id || !$eval_id || !$eleve_id) { wp_send_json_error(['message'=>"Paramètres manquants."]); return; }
            $ctx = $this->validate_teacher_write_context($eval_id, $ens_id, $eleve_id);
            if (empty($ctx['ok'])) { wp_send_json_error(['message'=>$ctx['message']]); return; }
            $t = LOGIEVAL_Database::tables();
            $sql = "SELECT sc.id FROM {$t['sous_criteres']} sc
                    INNER JOIN {$t['objectifs']} o ON o.id = sc.objectif_id
                    WHERE o.actif = 1 AND o.type = 'checkbox' AND o.maitre_seulement = 0
                      AND o.id NOT IN (SELECT objectif_id FROM {$t['non_evaluables']} WHERE evaluation_id=%d AND enseignant_id=%d AND eleve_id=%d)
                      AND sc.id NOT IN (SELECT sous_critere_id FROM {$t['reponses']} WHERE evaluation_id=%d AND enseignant_id=%d AND eleve_id=%d AND vote_set=1)";
            $args = [$eval_id, $ens_id, $eleve_id, $eval_id, $ens_id, $eleve_id];
            if ($obj_id) { $sql .= " AND o.id=%d"; $args[] = $obj_id; }
            $sc_ids = array_map('intval', $wpdb->get_col($wpdb->prepare($sql, ...$args)) ?: []);
            foreach ($sc_ids as $sc_id) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$t['reponses']} (evaluation_id,enseignant_id,eleve_id,sous_critere_id,vote,vote_set,updated_at)
                     VALUES (%d,%d,%d,%d,'oui',1,NOW())
                     ON DUPLICATE KEY UPDATE vote=IF(vote_set=1, vote, 'oui'), vote_set=1, updated_at=NOW()",
                    $eval_id, $ens_id, $eleve_id, $sc_id
                ));
            }
            if ($wpdb->last_error) {
                error_log('LogiEval fill yes DB error: ' . $wpdb->last_error);
                wp_send_json_error(['message'=>'Erreur base de données. Réessayez ou contactez l’administrateur.']); return;
            }
            wp_send_json_success(['sc_ids'=>$sc_ids, 'saved_at'=>current_time('H:i')]);
        } catch (Exception $e) {
            error_log('LogiEval fill yes exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_save_comment(): void {
        try {
            $ens_id     = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id    = intval($_POST['eval_id']  ?? 0);
            $eleve_id   = intval($_POST['eleve_id'] ?? 0);
            $sc_id      = intval($_POST['sc_id']    ?? 0);
            $commentaire = mb_substr(sanitize_textarea_field(wp_unslash($_POST['commentaire'] ?? '')), 0, 2000);
            if (!$ens_id||!$eval_id||!$eleve_id||!$sc_id) {
                wp_send_json_error(['message'=>"Paramètres manquants."]); return;
            }
            $t = LOGIEVAL_Database::tables();
            $ctx = $this->validate_teacher_write_context($eval_id, $ens_id, $eleve_id, $sc_id);
            if (empty($ctx['ok'])) { wp_send_json_error(['message'=>$ctx['message']]); return; }
            global $wpdb;
            $tr = $t['reponses'];
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$tr} (evaluation_id,enseignant_id,eleve_id,sous_critere_id,vote,vote_set,commentaire,updated_at)
                 VALUES (%d,%d,%d,%d,'abstention',0,%s,NOW())
                 ON DUPLICATE KEY UPDATE commentaire=VALUES(commentaire), updated_at=NOW()",
                $eval_id,$ens_id,$eleve_id,$sc_id,$commentaire
            ));
            if ($wpdb->last_error) {
                error_log('LogiEval save comment DB error: ' . $wpdb->last_error);
                wp_send_json_error(['message'=>'Erreur base de données. Réessayez ou contactez l’administrateur.']); return;
            }
            wp_send_json_success();
        } catch (Exception $e) {
            error_log('LogiEval save comment exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_save_arbitrage(): void {
        try {
            $ens_id   = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id  = intval($_POST['eval_id']  ?? 0);
            $eleve_id = intval($_POST['eleve_id'] ?? 0);
            $sc_id    = intval($_POST['sc_id']    ?? 0);
            $decision = intval($_POST['decision'] ?? 0) ? 1 : 0;
            if (!$ens_id||!$eval_id||!$eleve_id||!$sc_id) {
                wp_send_json_error(['message'=>"Paramètres manquants."]); return;
            }
            $eval = LOGIEVAL_Database::get_row(LOGIEVAL_Database::t('evaluations'),['id'=>$eval_id]);
            if (!$eval) { wp_send_json_error(['message'=>"Évaluation introuvable."]); return; }
            if ($eval['statut'] !== 'active') { wp_send_json_error(['message'=>"Une évaluation clôturée doit d'abord être réouverte."]); return; }
            if (!LOGIEVAL_Auth::is_maitre($ens_id,(int)$eval['classe_id'])) {
                wp_send_json_error(['message'=>"Réservé au maître de classe."]); return;
            }
            $t = LOGIEVAL_Database::tables();
            $eleve = LOGIEVAL_Database::get_row($t['eleves'], ['id'=>$eleve_id]);
            $sc = LOGIEVAL_Database::get_row($t['sous_criteres'], ['id'=>$sc_id]);
            if (!$eleve || (int)$eleve['classe_id'] !== (int)$eval['classe_id'] || !$sc) {
                wp_send_json_error(['message'=>"Données incompatibles avec cette évaluation."]); return;
            }
            global $wpdb;
            $ta = LOGIEVAL_Database::t('arbitrages');
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$ta} (evaluation_id,eleve_id,sous_critere_id,decision,updated_at)
                 VALUES (%d,%d,%d,%d,NOW())
                 ON DUPLICATE KEY UPDATE decision=VALUES(decision),updated_at=NOW()",
                $eval_id,$eleve_id,$sc_id,$decision
            ));
            if ($wpdb->last_error) {
                error_log('LogiEval save arbitrage DB error: ' . $wpdb->last_error);
                wp_send_json_error(['message'=>'Erreur base de données. Réessayez ou contactez l’administrateur.']); return;
            }
            wp_send_json_success(['decision'=>$decision]);
        } catch (Exception $e) {
            error_log('LogiEval save arbitrage exception: ' . $e->getMessage());
            wp_send_json_error(['message'=>'Erreur inattendue. Réessayez ou contactez l’administrateur.']);
        }
    }

    private function ajax_toggle_ne(): void {
        try {
            $ens_id   = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id  = intval($_POST['eval_id']  ?? 0);
            $eleve_id = intval($_POST['eleve_id'] ?? 0);
            $obj_id   = intval($_POST['obj_id']   ?? 0);
            $active   = intval($_POST['active']   ?? 1); // 1=mark NE, 0=remove NE

            if (!$ens_id || !$eval_id || !$eleve_id || !$obj_id) {
                wp_send_json_error(['message' => "Paramètres manquants."]); return;
            }

            // Cannot mark NE on maitre-only objectives
            $t   = LOGIEVAL_Database::tables();
            $obj = LOGIEVAL_Database::get_row($t['objectifs'], ['id' => $obj_id]);
            if (!$obj) { wp_send_json_error(['message' => "Objectif introuvable."]); return; }
            if ($obj['maitre_seulement']) {
                wp_send_json_error(['message' => "Cet objectif est réservé au maître de classe."]); return;
            }

            // Verify teacher is assigned to this evaluation
            $ee = LOGIEVAL_Database::get_row($t['eval_enseignant'],
                ['evaluation_id' => $eval_id, 'enseignant_id' => $ens_id]);
            if (!$ee) { wp_send_json_error(['message' => "Vous n'êtes pas attribué à cette évaluation."]); return; }
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id' => $eval_id]);
            if (!$eval || $eval['statut'] !== 'active') {
                wp_send_json_error(['message' => "Une évaluation clôturée doit d'abord être réouverte."]); return;
            }
            if (($ee['statut'] ?? '') === 'termine') {
                wp_send_json_error(['message' => "Votre évaluation est déjà marquée comme terminée. Réouvrez-la avant de modifier vos réponses."]); return;
            }
            $eleve = LOGIEVAL_Database::get_row($t['eleves'], ['id' => $eleve_id]);
            if (!$eval || !$eleve || (int)$eleve['classe_id'] !== (int)$eval['classe_id']) {
                wp_send_json_error(['message' => "Élève non lié à cette évaluation."]); return;
            }

            $tne = LOGIEVAL_Database::t('non_evaluables');
            global $wpdb;

            if ($active) {
                // Mark as non-evaluable — upsert
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$tne} (evaluation_id, enseignant_id, eleve_id, objectif_id)
                     VALUES (%d,%d,%d,%d)
                     ON DUPLICATE KEY UPDATE created_at=NOW()",
                    $eval_id, $ens_id, $eleve_id, $obj_id
                ));
                // LogiOne 1.28 : les réponses déjà données sont GARDÉES (mises de côté, elles ne comptent pas) ;
                // « Rétablir » les fait revenir. Auparavant elles étaient effacées sans retour possible.
            } else {
                // Remove non-evaluable flag
                $wpdb->delete($tne, [
                    'evaluation_id' => $eval_id,
                    'enseignant_id' => $ens_id,
                    'eleve_id'      => $eleve_id,
                    'objectif_id'   => $obj_id,
                ]);
            }

            if ($wpdb->last_error) {
                error_log('LogiEval toggle NE DB error: ' . $wpdb->last_error);
                wp_send_json_error(['message' => "Erreur base de données. Réessayez ou contactez l’administrateur."]); return;
            }
            wp_send_json_success(['active' => $active]);
        } catch (Exception $e) {
            error_log('LogiEval toggle NE exception: ' . $e->getMessage());
            wp_send_json_error(['message' => "Erreur inattendue. Réessayez ou contactez l’administrateur."]);
        }
    }

    private function ajax_delete_evaluation(): void {
        try {
            $ens_id  = LOGIEVAL_Auth::current_enseignant_id();
            $eval_id = intval($_POST['eval_id'] ?? 0);

            if (!$ens_id) {
                wp_send_json_error(['message' => "Session expirée."]); return;
            }
            if (!$eval_id) {
                wp_send_json_error(['message' => "Identifiant manquant."]); return;
            }

            $t    = LOGIEVAL_Database::tables();
            $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id' => $eval_id]);
            if (!$eval) {
                wp_send_json_error(['message' => "Évaluation introuvable."]); return;
            }

            // Only the maître de classe can delete
            if (!LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id'])) {
                wp_send_json_error(['message' => "Seul le maître de classe peut supprimer une évaluation."]); return;
            }

            global $wpdb;

            // Delete all related data in correct order (FK respect)
            $wpdb->delete($t['notes_ref'],       ['evaluation_id_dest'   => $eval_id]);
            $wpdb->delete($t['notes_ref'],       ['evaluation_id_source' => $eval_id]);
            $wpdb->delete($t['non_evaluables'],  ['evaluation_id'        => $eval_id]);
            $wpdb->delete($t['arbitrages'],      ['evaluation_id'        => $eval_id]);
            $wpdb->delete($t['reponses'],        ['evaluation_id'        => $eval_id]);
            $wpdb->delete($t['eval_enseignant'], ['evaluation_id'        => $eval_id]);
            $wpdb->delete($t['evaluations'],     ['id'                   => $eval_id]);
            delete_transient('logieval_class_report_key_'.$eval_id);
            LOGIEVAL_Calculator::unfreeze_grid($eval_id);

            if ($wpdb->last_error) {
                error_log('LogiEval delete evaluation DB error: ' . $wpdb->last_error);
                wp_send_json_error(['message' => "Erreur base de données. Réessayez ou contactez l’administrateur."]); return;
            }

            wp_send_json_success(['deleted' => $eval_id]);

        } catch (Exception $e) {
            error_log('LogiEval delete evaluation exception: ' . $e->getMessage());
            wp_send_json_error(['message' => "Erreur inattendue. Réessayez ou contactez l’administrateur."]);
        }
    }

    private function ajax_get_others_responses(): void {
        $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        $is_site_admin = current_user_can( 'lp_eval_admin' ) && !$ens_id;
        $eval_id = intval($_POST['eval_id'] ?? 0);
        if ((!$ens_id && !$is_site_admin) || !$eval_id) { wp_send_json_error(['message'=>"Paramètres manquants."]); return; }
        if (!$this->current_teacher_can_access_eval($eval_id, $ens_id)) {
            wp_send_json_error(['message'=>"Vous n'êtes pas attribué à cette évaluation."]); return;
        }

        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
        if (!$eval || (!$is_site_admin && !LOGIEVAL_Auth::is_maitre($ens_id, (int)$eval['classe_id']))) {
            wp_send_json_error(['message'=>"Accès réservé au maître de classe."]); return;
        }

        // Get vote tallies and colleague names grouped by (eleve_id, sc_id)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.eleve_id, r.sous_critere_id AS sc_id, r.enseignant_id, r.vote,
                    CONCAT(e.prenom,' ',e.nom) AS ens_nom
             FROM {$t['reponses']} r
             INNER JOIN {$t['enseignants']} e ON e.id=r.enseignant_id
             WHERE r.evaluation_id=%d AND r.vote_set=1" . self::counted_votes_sql('r') . "
             ORDER BY e.nom, e.prenom",
            $eval_id
        ), ARRAY_A) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $key = (int)$row['eleve_id'] . '_' . (int)$row['sc_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'eleve_id'      => (int)$row['eleve_id'],
                    'sc_id'         => (int)$row['sc_id'],
                    'nb_oui'        => 0,
                    'nb_non'        => 0,
                    'nb_abs'        => 0,
                    'oui_teachers'  => [],
                    'non_teachers'  => [],
                    'abs_teachers'  => [],
                ];
            }
            if ($row['vote'] === 'oui') {
                $grouped[$key]['nb_oui']++;
                if ((int)$row['enseignant_id'] !== $ens_id) $grouped[$key]['oui_teachers'][] = $row['ens_nom'];
            } elseif ($row['vote'] === 'non') {
                $grouped[$key]['nb_non']++;
                if ((int)$row['enseignant_id'] !== $ens_id) $grouped[$key]['non_teachers'][] = $row['ens_nom'];
            } elseif ($row['vote'] === 'abstention') {
                $grouped[$key]['nb_abs']++;
                if ((int)$row['enseignant_id'] !== $ens_id) $grouped[$key]['abs_teachers'][] = $row['ens_nom'];
            }
        }
        wp_send_json_success(array_values($grouped));
    }
}
