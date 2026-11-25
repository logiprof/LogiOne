<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LOGIEVAL_Admin {

    public function __construct() {
        add_action( 'admin_menu',             [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_logieval_admin',    [ $this, 'handle_ajax' ] );
        add_action( 'admin_post_logieval_export', [ $this, 'handle_export' ] );
        // 1.53.5 : écrans LogiEval affichés dans LogiOne (cadre intégré), sans le menu WordPress.
        add_filter( 'admin_body_class', [ $this, 'embed_body_class' ] );
        add_action( 'admin_head',   [ $this, 'embed_head' ] );
        add_action( 'admin_footer', [ $this, 'embed_footer' ] );
        add_filter( 'wp_redirect',  [ $this, 'embed_keep_on_redirect' ] );
    }

    private static function is_embed(): bool {
        return ! empty( $_GET['lp_embed'] ) && strpos( (string) ( $_GET['page'] ?? '' ), 'logieval' ) === 0;
    }
    public function embed_body_class( $c ) { return self::is_embed() ? $c . ' lge-embed' : $c; }
    public function embed_head(): void {
        if ( ! self::is_embed() ) return;
        echo '<style>html.wp-toolbar{padding-top:0!important}#wpadminbar,#adminmenumain,#adminmenuback,#adminmenuwrap,#wpfooter,#screen-meta-links,.update-nag,.notice:not(.logieval-notice):not(.notice-success):not(.notice-error){display:none!important}'
           . '#wpcontent,#wpfooter{margin-left:0!important}#wpcontent{padding-left:16px!important}#wpbody-content{padding-bottom:20px!important}body.lge-embed{min-width:0}</style>';
    }
    public function embed_footer(): void {
        if ( ! self::is_embed() ) return; ?>
<script>(function(){
  // Les liens et formulaires vers les écrans LogiEval restent dans le cadre ; le reste s'ouvre en pleine page.
  function keep(u){ try{ var x=new URL(u,location.href); if(x.origin!==location.origin) return null;
    if(/\/wp-admin\/admin\.php$/.test(x.pathname) && /^logieval/.test(x.searchParams.get('page')||'')){ x.searchParams.set('lp_embed','1'); return x.toString(); }
    return null; }catch(e){ return null; } }
  document.querySelectorAll('a[href]').forEach(function(a){ var h=a.getAttribute('href'); if(!h||h.charAt(0)==='#'||/^javascript:/i.test(h)) return;
    var k=keep(a.href); if(k){ a.href=k; } else if(!a.target && !/admin-post\.php|admin-ajax\.php/.test(a.href)) { a.target='_top'; } });
  document.querySelectorAll('form').forEach(function(f){ if((f.method||'').toLowerCase()==='get'){ if(!f.querySelector('[name=lp_embed]')){ var i=document.createElement('input'); i.type='hidden'; i.name='lp_embed'; i.value='1'; f.appendChild(i);} }
    else { var k=keep(f.action||location.href); if(k) f.action=k; } });
  function post(){ if(parent!==window) parent.postMessage({lgeHeight:document.documentElement.scrollHeight},location.origin); }
  if(window.ResizeObserver) new ResizeObserver(post).observe(document.body); window.addEventListener('load',post); post();
})();</script>
<?php }
    /** Après une action (admin-post), revenir dans le cadre si on en venait. */
    public function embed_keep_on_redirect( $location ) {
        $ref = (string) wp_get_referer();
        if ( $ref && strpos( $ref, 'lp_embed=1' ) !== false && is_string( $location )
            && strpos( $location, 'admin.php' ) !== false && preg_match( '/[?&]page=logieval/', $location ) && strpos( $location, 'lp_embed=' ) === false ) {
            $location = add_query_arg( 'lp_embed', 1, $location );
        }
        return $location;
    }

    /* ── Menus ──────────────────────────────────────────────────── */
    public function register_menus(): void {
        add_menu_page(
            'LogiEval',
            'LogiEval',
            'lp_eval_admin',
            'logieval',
            [ $this, 'page_dashboard' ],
            'dashicons-clipboard',
            3
        );
        add_submenu_page( 'logieval', 'Évaluations LogiEval', 'Évaluations', 'lp_eval_admin', 'logieval-evaluations', [ $this, 'page_evaluations' ] );
        add_submenu_page( 'logieval', 'Classes LogiEval', 'Classes', 'lp_eval_admin', 'logieval-classes', [ $this, 'page_classes' ] );
        add_submenu_page( 'logieval', 'Élèves LogiEval', 'Élèves', 'lp_eval_admin', 'logieval-eleves', [ $this, 'page_eleves' ] );
        add_submenu_page( 'logieval', 'Enseignants LogiEval', 'Enseignants', 'lp_eval_admin', 'logieval-enseignants', [ $this, 'page_enseignants' ] );
        add_submenu_page( 'logieval', 'Objectifs LogiEval', 'Objectifs', 'lp_eval_admin', 'logieval-objectifs', [ $this, 'page_objectifs' ] );
        // « Branches » n'est plus dans le menu : les branches n'étaient utilisées nulle part.
        // La page reste accessible par son adresse pour ne rien perdre des données existantes.
        add_submenu_page( 'logieval', 'Branches LogiEval', 'Branches', 'lp_eval_admin', 'logieval-branches', [ $this, 'page_branches' ] );
        add_action( 'admin_head', function() { remove_submenu_page( 'logieval', 'logieval-branches' ); } );
        add_submenu_page( 'logieval', 'Réglages LogiEval', 'Réglages', 'lp_eval_admin', 'logieval-settings', [ $this, 'page_settings' ] );
        add_submenu_page( 'logieval', 'Import LogiEval', 'Import', 'lp_eval_admin', 'logieval-import', [ $this, 'page_import' ] );
    }

    /* ── Assets ─────────────────────────────────────────────────── */
    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'logieval' ) === false ) return;
        wp_enqueue_style( 'logieval-admin', LOGIEVAL_URL . 'admin/css/admin.css', [], LOGIEVAL_VERSION );
        wp_enqueue_script( 'logieval-admin', LOGIEVAL_URL . 'admin/js/admin.js', ['jquery'], LOGIEVAL_VERSION, true );
        wp_localize_script( 'logieval-admin', 'logievalAdmin', [
            'max_upload' => wp_max_upload_size(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('logieval_admin_nonce'),
        ]);
    }

    public function handle_export(): void {
        if ( ! current_user_can( 'lp_eval_admin' ) ) {
            wp_die( 'Accès refusé.' );
        }
        $eval_id  = isset( $_GET['eval_id'] ) ? absint( $_GET['eval_id'] ) : 0;
        $eleve_id = isset( $_GET['eleve_id'] ) ? absint( $_GET['eleve_id'] ) : 0;
        check_admin_referer( 'logieval_export_' . $eval_id );
        if ( ! $eval_id ) {
            wp_die( 'Évaluation manquante.' );
        }
        LOGIEVAL_Exporter::export_word( $eval_id, $eleve_id );
    }

    /* ── AJAX handler ───────────────────────────────────────────── */
    public function handle_ajax(): void {
        check_ajax_referer( 'logieval_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'lp_eval_admin' ) ) wp_send_json_error(['message' => 'Accès refusé : droits LogiEval insuffisants.']);
        $action = sanitize_key( wp_unslash( $_POST['sub_action'] ?? '' ) );

        switch ( $action ) {
            case 'save_classe':         $this->ajax_save_classe(); break;
            case 'delete_classe':       $this->ajax_delete_classe(); break;
            case 'save_eleve':          $this->ajax_save_eleve(); break;
            case 'delete_eleve':        $this->ajax_delete_eleve(); break;
            case 'bulk_eleves':         $this->ajax_bulk_eleves(); break;
            case 'bulk_enseignants':    $this->ajax_bulk_enseignants(); break;
            case 'save_enseignant':     $this->ajax_save_enseignant(); break;
            case 'delete_enseignant':   $this->ajax_delete_enseignant(); break;
            case 'activate_enseignant': $this->ajax_activate_enseignant(); break;
            case 'deactivate_enseignant': $this->ajax_deactivate_enseignant(); break;
            case 'regen_ident':         $this->ajax_regen_ident(); break;
            case 'save_branche':        $this->ajax_save_branche(); break;
            case 'delete_branche':      $this->ajax_delete('branches', 'id'); break;
            case 'save_evaluation':     $this->ajax_save_evaluation(); break;
            case 'close_evaluation':    $this->ajax_close_evaluation(); break;
            case 'delete_evaluation':   $this->ajax_delete_evaluation(); break;
            case 'assign_enseignant_classe':       $this->ajax_assign_enseignant_classe(); break;
            case 'remove_enseignant_classe':       $this->ajax_remove_enseignant_classe(); break;
            case 'set_maitre_classe':              $this->ajax_set_maitre_classe(); break;
            case 'get_classe_teachers_detail':     $this->ajax_get_classe_teachers_detail(); break;
            case 'save_settings':                  $this->ajax_save_settings(); break;
            case 'get_classe_teachers':            $this->ajax_get_classe_teachers(); break;
            case 'save_objectif':          $this->ajax_save_objectif(); break;
            case 'delete_objectif':        $this->ajax_delete_objectif(); break;
            case 'save_sous_critere':      $this->ajax_save_sous_critere(); break;
            case 'delete_sous_critere':    $this->ajax_delete_sous_critere(); break;
            default: wp_send_json_error(['message' => 'Action inconnue.']);
        }
    }

    /* ── AJAX sub-handlers ──────────────────────────────────────── */
    private function ajax_save_classe(): void {
        $id    = intval( $_POST['id'] ?? 0 );
        $data  = [
            'nom'           => sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) ),
            'niveau'        => sanitize_text_field( wp_unslash( $_POST['niveau'] ?? '' ) ),
            'annee_scolaire'=> sanitize_text_field( wp_unslash( $_POST['annee_scolaire'] ?? '' ) ),
        ];
        if ( $data['nom'] === '' ) { wp_send_json_error(['message' => 'Le nom de la classe est obligatoire.']); return; }
        if ( $id ) {
            LOGIEVAL_Database::update( LOGIEVAL_Database::t('classes'), $data, ['id' => $id] );
        } else {
            $id = LOGIEVAL_Database::insert( LOGIEVAL_Database::t('classes'), $data );
        }
        // Handle maitre designation
        $maitre_id = intval( $_POST['maitre_id'] ?? 0 );
        if ( $maitre_id && $id ) {
            global $wpdb;
            $t = LOGIEVAL_Database::t('classe_enseignant');
            $wpdb->query( $wpdb->prepare("UPDATE {$t} SET est_maitre=0 WHERE classe_id=%d", $id) );
            $wpdb->query( $wpdb->prepare("UPDATE {$t} SET est_maitre=1 WHERE classe_id=%d AND enseignant_id=%d", $id, $maitre_id) );
        }
        wp_send_json_success( ['id' => $id] );
    }

    private function ajax_save_eleve(): void {
        $id   = intval( $_POST['id'] ?? 0 );
        $data = [
            'classe_id' => intval( $_POST['classe_id'] ?? 0 ),
            'prenom'    => sanitize_text_field( wp_unslash( $_POST['prenom'] ?? '' ) ),
            'nom'       => sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) ),
            'num_eleve' => sanitize_text_field( wp_unslash( $_POST['num_eleve'] ?? '' ) ),
        ];
        if ( ! $data['classe_id'] || ! LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('classes'), ['id' => $data['classe_id']] ) ) {
            wp_send_json_error(['message' => 'Choisissez une classe existante.']); return;
        }
        if ( $data['prenom'] === '' || $data['nom'] === '' ) {
            wp_send_json_error(['message' => 'Le prénom et le nom de l’élève sont obligatoires.']); return;
        }
        // 1.53.1 : un élève n'est que dans une seule classe.
        $dup = LOGIEVAL_Database::find_duplicate_student( $data['prenom'], $data['nom'], $data['num_eleve'], $id );
        if ( $dup ) {
            $dup_classe = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('classes'), ['id' => (int) $dup['classe_id']] );
            $new_classe = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('classes'), ['id' => $data['classe_id']] );
            if ( (int) $dup['classe_id'] === $data['classe_id'] ) {
                wp_send_json_error(['message' => sprintf( '%s %s est déjà dans la classe %s.', $dup['prenom'], $dup['nom'], $dup_classe['nom'] ?? '?' )]); return;
            }
            // 1.53.4 : nouvel élève déjà inscrit ailleurs → l'enseignant confirme, et l'élève CHANGE de classe (même fiche, données gardées).
            if ( ! $id ) {
                $confirm = intval( $_POST['confirm_move'] ?? 0 );
                if ( $confirm !== (int) $dup['id'] ) {
                    wp_send_json_error([
                        'code'      => 'already_in_class',
                        'dup_id'    => (int) $dup['id'],
                        'eleve'     => trim( $dup['prenom'] . ' ' . $dup['nom'] ),
                        'from'      => $dup_classe['nom'] ?? '?',
                        'to'        => $new_classe['nom'] ?? '?',
                        'message'   => sprintf( 'Cet élève est déjà dans la classe %s.', $dup_classe['nom'] ?? '?' ),
                    ]); return;
                }
                $move = [ 'classe_id' => $data['classe_id'] ];
                if ( $data['num_eleve'] !== '' && trim( (string) $dup['num_eleve'] ) === '' ) $move['num_eleve'] = $data['num_eleve'];
                LOGIEVAL_Database::log_student_move( (int) $dup['id'], (int) $dup['classe_id'], $data['classe_id'] );
                LOGIEVAL_Database::update( LOGIEVAL_Database::t('eleves'), $move, ['id' => (int) $dup['id']] );
                wp_send_json_success( ['id' => (int) $dup['id'], 'moved' => true] ); return;
            }
            wp_send_json_error(['message' => sprintf( 'Cet élève existe déjà (%s %s, classe %s). Un élève ne peut être que dans une classe : modifiez sa fiche existante et changez-y sa classe.', $dup['prenom'], $dup['nom'], $dup_classe['nom'] ?? '?' )]); return;
        }
        if ( $id ) {
            $before = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('eleves'), ['id' => $id] );
            if ( $before ) LOGIEVAL_Database::log_student_move( $id, (int) $before['classe_id'], $data['classe_id'] );
        }
        if ( $id ) LOGIEVAL_Database::update( LOGIEVAL_Database::t('eleves'), $data, ['id' => $id] );
        else $id = LOGIEVAL_Database::insert( LOGIEVAL_Database::t('eleves'), $data );
        if ( ! $id ) { wp_send_json_error(['message' => 'Enregistrement impossible (erreur base de données).']); return; }
        wp_send_json_success( ['id' => $id] );
    }

    private function ajax_save_enseignant(): void {
        $id   = intval( $_POST['id'] ?? 0 );
        $data = [
            'prenom' => sanitize_text_field( wp_unslash( $_POST['prenom'] ?? '' ) ),
            'nom'    => sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) ),
            'email'  => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
        ];
        if ( $data['prenom'] === '' || $data['nom'] === '' ) {
            wp_send_json_error(['message' => 'Le prénom et le nom sont obligatoires.']); return;
        }
        if ( ! is_email( $data['email'] ) ) {
            wp_send_json_error(['message' => 'Adresse e-mail invalide.']); return;
        }
        global $wpdb;
        $te = LOGIEVAL_Database::t('enseignants');
        $other = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$te} WHERE email=%s AND id<>%d LIMIT 1", $data['email'], $id ) );
        if ( $other ) {
            wp_send_json_error(['message' => 'Un autre enseignant utilise déjà cette adresse e-mail.']); return;
        }
        if ( $id ) {
            LOGIEVAL_Database::update( $te, $data, ['id' => $id] );
        } else {
            $data['identifiant_unique'] = LOGIEVAL_Auth::generate_identifier();
            $data['actif'] = 0;
            $id = LOGIEVAL_Database::insert( $te, $data );
            if ( ! $id ) { wp_send_json_error(['message' => 'Enregistrement impossible (erreur base de données).']); return; }
        }
        wp_send_json_success( ['id' => $id] );
    }

    private function ajax_activate_enseignant(): void {
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error(['message' => 'Identifiant enseignant manquant.']);
            return;
        }

        $ens = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['id' => $id] );
        if ( $ens && LOGIEVAL_Database::is_placeholder_email( (string) $ens['email'] ) ) {
            wp_send_json_error(['message' => 'Complétez d’abord l’e-mail de cet enseignant (bouton « Modifier ») : il a été créé par import, sans adresse.']); return;
        }
        LOGIEVAL_Database::update( LOGIEVAL_Database::t('enseignants'), ['actif' => 1], ['id' => $id] );
        // 1.53.11 : « Activer sans e-mail » → les accès seront envoyés plus tard.
        if ( isset( $_POST['send'] ) && ! intval( $_POST['send'] ) ) { wp_send_json_success(['email_sent' => -1]); return; }
        $sent = LOGIEVAL_Mailer::notify_teacher_access( $id );
        wp_send_json_success(['email_sent' => $sent ? 1 : 0]);
    }

    /** 1.53.11 : actions groupées sur les enseignants cochés. */
    private function ajax_bulk_enseignants(): void {
        $t   = LOGIEVAL_Database::t('enseignants');
        $ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) ) ) ) );
        $op  = sanitize_key( $_POST['op'] ?? '' );
        if ( ! $ids ) { wp_send_json_error(['message' => 'Aucun enseignant coché.']); return; }
        $done = 0; $skipped = []; $failed = [];
        foreach ( $ids as $id ) {
            $e = LOGIEVAL_Database::get_row( $t, ['id' => $id] );
            if ( ! $e ) continue;
            $name = trim( $e['prenom'] . ' ' . $e['nom'] );
            $todo = LOGIEVAL_Database::is_placeholder_email( (string) $e['email'] );
            if ( $op === 'activate' || $op === 'activate_send' ) {
                if ( $todo ) { $skipped[] = $name; continue; }
                if ( empty( $e['actif'] ) ) LOGIEVAL_Database::update( $t, ['actif' => 1], ['id' => $id] );
                if ( $op === 'activate_send' && ! LOGIEVAL_Mailer::notify_teacher_access( $id ) ) { $failed[] = $name; continue; }
                $done++;
            } elseif ( $op === 'send' ) {
                if ( empty( $e['actif'] ) || $todo ) { $skipped[] = $name; continue; }
                if ( LOGIEVAL_Mailer::notify_teacher_access( $id ) ) $done++; else $failed[] = $name;
            } elseif ( $op === 'deactivate' ) {
                LOGIEVAL_Database::update( $t, ['actif' => 0], ['id' => $id] ); $done++;
            } else { wp_send_json_error(['message' => 'Action inconnue.']); return; }
        }
        $msg = [ 'activate' => '%d enseignant(s) activé(s), sans e-mail.', 'activate_send' => '%d enseignant(s) activé(s) et prévenu(s) par e-mail.',
                 'send' => '%d e-mail(s) d’accès envoyé(s).', 'deactivate' => '%d enseignant(s) désactivé(s).' ][ $op ];
        $out = sprintf( $msg, $done );
        if ( $skipped ) $out .= "\n\nIgnorés (" . count( $skipped ) . ") — e-mail à compléter" . ( $op === 'send' ? ' ou compte inactif' : '' ) . " : " . implode( ', ', $skipped );
        if ( $failed )  $out .= "\n\nE-mail non parti (" . count( $failed ) . ") : " . implode( ', ', $failed ) . ". Vérifiez la configuration e-mail du site.";
        wp_send_json_success(['message' => $out]);
    }

    private function ajax_deactivate_enseignant(): void {
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error(['message' => 'Identifiant enseignant manquant.']);
            return;
        }

        LOGIEVAL_Database::update( LOGIEVAL_Database::t('enseignants'), ['actif' => 0], ['id' => $id] );
        wp_send_json_success();
    }

    private function ajax_regen_ident(): void {
        $id  = intval( $_POST['id'] ?? 0 );
        if ( ! $id || ! LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['id' => $id] ) ) {
            wp_send_json_error(['message' => 'Enseignant introuvable.']); return;
        }
        $new = LOGIEVAL_Auth::regenerate( $id );
        wp_send_json_success( ['identifiant' => $new] );
    }

    private function ajax_save_branche(): void {
        $id   = intval( $_POST['id'] ?? 0 );
        $data = [ 'libelle' => sanitize_text_field( wp_unslash( $_POST['libelle'] ?? '' ) ),
                  'actif'   => intval( $_POST['actif'] ?? 1 ) ];
        if ( $id ) LOGIEVAL_Database::update( LOGIEVAL_Database::t('branches'), $data, ['id' => $id] );
        else $id = LOGIEVAL_Database::insert( LOGIEVAL_Database::t('branches'), $data );
        wp_send_json_success( ['id' => $id] );
    }

    private function ajax_save_evaluation(): void {
        global $wpdb;
        $t    = LOGIEVAL_Database::tables();
        $id   = intval( $_POST['id'] ?? 0 );
        $data = [
            'classe_id'     => intval( $_POST['classe_id'] ?? 0 ),
            'semestre'      => sanitize_text_field( wp_unslash( $_POST['semestre'] ?? '' ) ),
            'date_echeance' => sanitize_text_field( wp_unslash( $_POST['date_echeance'] ?? '' ) ),
            'created_by'    => get_current_user_id(),
        ];
        $d = DateTime::createFromFormat( 'Y-m-d', $data['date_echeance'] );
        if ( ! $d || $d->format( 'Y-m-d' ) !== $data['date_echeance'] || ! $data['classe_id'] ) {
            wp_send_json_error(['message' => 'Classe ou date d’échéance invalide.']);
            return;
        }
        if ( $data['semestre'] === '' ) {
            wp_send_json_error(['message' => 'Le semestre est obligatoire.']);
            return;
        }

        if ( $id ) {
            $current = LOGIEVAL_Database::get_row( $t['evaluations'], ['id' => $id] );
            if ( ! $current ) { wp_send_json_error(['message' => 'Évaluation introuvable.']); return; }
            unset( $data['created_by'] ); // on garde l'auteur d'origine
            // Validation AVANT toute écriture (avant : la date était déjà modifiée quand l'erreur s'affichait).
            $enseignant_ids = array_values(array_unique(array_map('intval', (array)($_POST['enseignant_ids'] ?? []))));
            $enseignant_ids = $this->filter_active_class_enseignants($enseignant_ids, (int)$data['classe_id']);
            if ( empty($enseignant_ids) ) {
                wp_send_json_error(['message' => 'Sélectionnez au moins un enseignant actif affecté à cette classe.']);
                return;
            }
            $existing_before = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d", $id
            )) ?: []);
            $same_set = ! array_diff($existing_before, $enseignant_ids) && ! array_diff($enseignant_ids, $existing_before);
            // Rappel « dernier enseignant » remis à zéro seulement si l'équipe ou l'échéance change
            if ( ! $same_set || $current['date_echeance'] !== $data['date_echeance'] ) {
                $data['last_teacher_reminder_sent'] = 0;
                delete_option( 'logieval_j7_sent_' . $id );
            }
            LOGIEVAL_Database::update( $t['evaluations'], $data, ['id' => $id] );

            $existing_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d",
                $id
            )) ?: [];
            $existing_ids = array_map('intval', $existing_ids);

            $added_teacher_ids = array_values(array_diff($enseignant_ids, $existing_ids));
            foreach ($added_teacher_ids as $eid) {
                LOGIEVAL_Database::insert($t['eval_enseignant'], [
                    'evaluation_id' => $id,
                    'enseignant_id' => (int)$eid,
                ]);
            }
            foreach (array_diff($existing_ids, $enseignant_ids) as $eid) {
                $wpdb->delete($t['eval_enseignant'], [
                    'evaluation_id' => $id,
                    'enseignant_id' => (int)$eid,
                ]);
            }
            if ( ! empty($added_teacher_ids) ) {
                LOGIEVAL_Mailer::later('notify_assignment', [$id, $added_teacher_ids]);
            }
        } else {
            // Anti double-clic : même classe et même semestre créés il y a moins de 60 s
            $dup = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$t['evaluations']} WHERE classe_id=%d AND semestre=%s AND date_creation >= (NOW() - INTERVAL 60 SECOND) LIMIT 1",
                $data['classe_id'], $data['semestre']
            ) );
            if ( $dup ) { wp_send_json_success( ['id' => $dup, 'duplicate' => 1] ); return; }
            $data['mode_calcul'] = 'proportionnel';
            $data['mail_teacher_completed'] = ! empty( $_POST['mail_teacher_completed'] ) ? 1 : 0;
            $data['mail_all_completed']     = ! empty( $_POST['mail_all_completed'] ) ? 1 : 0;
            $data['mail_closure_maitre']    = ! empty( $_POST['mail_closure_maitre'] ) ? 1 : 0;
            $id = LOGIEVAL_Database::insert( $t['evaluations'], $data );
            if ( ! $id ) { wp_send_json_error(['message' => 'Création impossible (erreur base de données).']); return; }

            // Assign teachers
            $enseignant_ids = array_map('intval', (array)($_POST['enseignant_ids'] ?? []));
            $enseignant_ids = $this->filter_active_class_enseignants($enseignant_ids, (int)$data['classe_id']);
            if ( empty($enseignant_ids) ) {
                // Auto: all teachers of this class
                $enseignant_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT ce.enseignant_id
                     FROM {$t['classe_enseignant']} ce
                     INNER JOIN {$t['enseignants']} e ON e.id = ce.enseignant_id
                     WHERE ce.classe_id = %d AND e.actif = 1",
                    $data['classe_id']
                )) ?: [];
            }
            if ( empty($enseignant_ids) ) {
                LOGIEVAL_Database::delete( $t['evaluations'], ['id' => $id] );
                wp_send_json_error(['message' => 'Aucun enseignant actif n’est affecté à cette classe. Activez au moins un profil enseignant avant de créer l’évaluation.']);
                return;
            }
            foreach ( $enseignant_ids as $eid ) {
                LOGIEVAL_Database::insert( $t['eval_enseignant'], ['evaluation_id' => $id, 'enseignant_id' => $eid] );
            }

            // Import previous semester notes if requested
            if ( ! empty($_POST['import_previous']) ) {
                self::import_previous_notes( $id, $data['classe_id'] );
            }

            // Send notifications
            LOGIEVAL_Mailer::later('notify_assignment', [$id]);
        }
        wp_send_json_success( ['id' => $id] );
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

    private static function import_previous_notes( int $new_eval_id, int $classe_id ): void {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        // Find most recent previous evaluation for this class
        $prev = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$t['evaluations']} WHERE classe_id=%d AND id != %d ORDER BY date_creation DESC LIMIT 1",
            $classe_id, $new_eval_id
        ), ARRAY_A );
        if ( ! $prev ) return;

        $results = LOGIEVAL_Calculator::full_results( (int)$prev['id'] );
        foreach ( $results as $eleve_id => $data ) {
            if ( $data['note'] !== null ) {
                LOGIEVAL_Database::insert( $t['notes_ref'], [
                    'evaluation_id_source' => $prev['id'],
                    'evaluation_id_dest'   => $new_eval_id,
                    'eleve_id'             => $eleve_id,
                    'note_calculee'        => $data['note'],
                    'points_obtenus'       => $data['avg_pts'] ?? $data['pts'] ?? 0,
                ]);
            }
        }
    }

    private function ajax_close_evaluation(): void {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        $id = intval( $_POST['id'] ?? 0 );
        if (!$id) {
            wp_send_json_error(['message' => 'Identifiant manquant.']);
            return;
        }
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id' => $id]);
        if (!$eval) {
            wp_send_json_error(['message' => 'Évaluation introuvable.']);
            return;
        }
        if ($eval['statut'] !== 'active') {
            wp_send_json_success();
            return;
        }
        $pending = LOGIEVAL_Database::pending_teacher_count( $id );
        if ($pending > 0) {
            wp_send_json_error(['message' => "Impossible de clôturer depuis le back-office : {$pending} enseignant(s) n'ont pas terminé. Utilisez l'interface maître de classe pour clôturer avec les évaluations déjà faites."]);
            return;
        }
        // (Avant : le résultat de la mise à jour n'était jamais lu, l'e-mail de clôture ne partait pas.)
        $closed = $wpdb->update( $t['evaluations'], ['statut' => 'cloturee'], ['id' => $id, 'statut' => 'active'] );
        if ($closed) {
            LOGIEVAL_Calculator::freeze_grid( $id );
            LOGIEVAL_Mailer::later('notify_closure', [$id]);
        }
        wp_send_json_success();
    }

    private function ajax_delete( string $table_key, string $pk ): void {
        $id = intval( $_POST['id'] ?? 0 );
        LOGIEVAL_Database::delete( LOGIEVAL_Database::t( $table_key ), [ $pk => $id ] );
        wp_send_json_success();
    }

    private function count_rows( string $table_key, string $where_sql, ...$args ): int {
        global $wpdb;
        $t = LOGIEVAL_Database::t( $table_key );
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$where_sql}", ...$args ) );
    }

    /** Classe : refusée tant qu'elle contient des élèves ou des évaluations (évite les données orphelines). */
    private function ajax_delete_classe(): void {
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error(['message' => 'Identifiant manquant.']); return; }
        $n_el = $this->count_rows( 'eleves', 'classe_id=%d', $id );
        $n_ev = $this->count_rows( 'evaluations', 'classe_id=%d', $id );
        if ( $n_el || $n_ev ) {
            wp_send_json_error(['message' => "Suppression refusée : cette classe contient {$n_el} élève(s) et {$n_ev} évaluation(s). Supprimez ou déplacez-les d’abord."]);
            return;
        }
        global $wpdb;
        $wpdb->delete( LOGIEVAL_Database::t('classe_enseignant'), ['classe_id' => $id] );
        $wpdb->delete( LOGIEVAL_Database::t('classes'), ['id' => $id] );
        wp_send_json_success();
    }

    /** Élève : supprime aussi ses réponses, avis « non évaluable », arbitrages et notes reprises. */
    private function ajax_delete_eleve(): void {
        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error(['message' => 'Identifiant manquant.']); return; }
        $t = LOGIEVAL_Database::tables();
        foreach ( ['reponses', 'non_evaluables', 'arbitrages', 'notes_ref'] as $k ) {
            $wpdb->delete( $t[$k], ['eleve_id' => $id] );
        }
        $wpdb->delete( $t['eleves'], ['id' => $id] );
        wp_send_json_success();
    }

    /** 1.53.9 : actions groupées sur les élèves cochés (supprimer, changer de classe). */
    private function ajax_bulk_eleves(): void {
        global $wpdb;
        $t   = LOGIEVAL_Database::tables();
        $ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) ) ) ) );
        $op  = sanitize_key( $_POST['op'] ?? '' );
        if ( ! $ids ) { wp_send_json_error(['message' => 'Aucun élève coché.']); return; }
        if ( $op === 'delete' ) {
            foreach ( $ids as $id ) {
                foreach ( ['reponses', 'non_evaluables', 'arbitrages', 'notes_ref'] as $k ) $wpdb->delete( $t[$k], ['eleve_id' => $id] );
                $wpdb->delete( $t['eleves'], ['id' => $id] );
            }
            wp_send_json_success(['message' => count( $ids ) . ' élève(s) supprimé(s).']); return;
        }
        if ( $op === 'move' ) {
            $to = intval( $_POST['classe_id'] ?? 0 );
            if ( ! $to || ! LOGIEVAL_Database::get_row( $t['classes'], ['id' => $to] ) ) { wp_send_json_error(['message' => 'Choisissez la classe de destination.']); return; }
            $n = 0;
            foreach ( $ids as $id ) {
                $el = LOGIEVAL_Database::get_row( $t['eleves'], ['id' => $id] );
                if ( ! $el || (int) $el['classe_id'] === $to ) continue;
                LOGIEVAL_Database::log_student_move( $id, (int) $el['classe_id'], $to );
                LOGIEVAL_Database::update( $t['eleves'], ['classe_id' => $to], ['id' => $id] );
                $n++;
            }
            wp_send_json_success(['message' => $n . ' élève(s) déplacé(s).']); return;
        }
        wp_send_json_error(['message' => 'Action inconnue.']);
    }

    /** Enseignant : refusé s'il a déjà donné des réponses ; on propose de le désactiver. */
    private function ajax_delete_enseignant(): void {
        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error(['message' => 'Identifiant manquant.']); return; }
        $n = $this->count_rows( 'reponses', 'enseignant_id=%d', $id );
        if ( $n ) {
            wp_send_json_error(['message' => "Suppression refusée : cet enseignant a déjà saisi {$n} réponse(s). Désactivez-le plutôt : ses réponses restent conservées et il ne peut plus se connecter."]);
            return;
        }
        $t = LOGIEVAL_Database::tables();
        $wpdb->delete( $t['classe_enseignant'], ['enseignant_id' => $id] );
        $wpdb->delete( $t['eval_enseignant'], ['enseignant_id' => $id] );
        $wpdb->delete( $t['non_evaluables'], ['enseignant_id' => $id] );
        $wpdb->delete( $t['enseignants'], ['id' => $id] );
        wp_send_json_success();
    }

    /** Objectif : refusé s'il a déjà servi ; sinon supprime aussi ses sous-critères. */
    private function ajax_delete_objectif(): void {
        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error(['message' => 'Identifiant manquant.']); return; }
        $t = LOGIEVAL_Database::tables();
        $used = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t['reponses']} r INNER JOIN {$t['sous_criteres']} sc ON sc.id=r.sous_critere_id WHERE sc.objectif_id=%d", $id
        ) );
        if ( $used ) {
            wp_send_json_error(['message' => "Suppression refusée : cet objectif a déjà été utilisé dans {$used} réponse(s). Décochez « Actif » pour le masquer des prochaines évaluations."]);
            return;
        }
        $wpdb->delete( $t['sous_criteres'], ['objectif_id' => $id] );
        $wpdb->delete( $t['objectifs'], ['id' => $id] );
        wp_send_json_success();
    }

    private function ajax_delete_sous_critere(): void {
        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error(['message' => 'Identifiant manquant.']); return; }
        $n = $this->count_rows( 'reponses', 'sous_critere_id=%d', $id );
        if ( $n ) {
            wp_send_json_error(['message' => "Suppression refusée : ce critère a déjà été coché dans {$n} réponse(s)."]);
            return;
        }
        $wpdb->delete( LOGIEVAL_Database::t('sous_criteres'), ['id' => $id] );
        wp_send_json_success();
    }

    private function ajax_delete_evaluation(): void {
        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if (!$id) {
            wp_send_json_error(['message' => 'Identifiant manquant.']);
            return;
        }

        $t = LOGIEVAL_Database::tables();
        $wpdb->delete($t['notes_ref'],       ['evaluation_id_dest'   => $id]);
        $wpdb->delete($t['notes_ref'],       ['evaluation_id_source' => $id]);
        $wpdb->delete($t['non_evaluables'],  ['evaluation_id'        => $id]);
        $wpdb->delete($t['arbitrages'],      ['evaluation_id'        => $id]);
        $wpdb->delete($t['reponses'],        ['evaluation_id'        => $id]);
        $wpdb->delete($t['eval_enseignant'], ['evaluation_id'        => $id]);
        $wpdb->delete($t['evaluations'],     ['id'                   => $id]);
        delete_transient('logieval_class_report_key_'.$id);

        if ($wpdb->last_error) {
            error_log('LogiEval delete evaluation DB error: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Erreur base de données. Réessayez ou contactez l’administrateur.']);
            return;
        }
        wp_send_json_success();
    }

    private function ajax_assign_enseignant_classe(): void {
        $t          = LOGIEVAL_Database::t('classe_enseignant');
        $classe_id  = intval($_POST['classe_id']   ?? 0);
        $ens_id     = intval($_POST['enseignant_id']?? 0);
        $est_maitre = intval($_POST['est_maitre']  ?? 0);

        if ( !$classe_id || !$ens_id ) {
            wp_send_json_error(["message"=>"Données manquantes."]); return;
        }
        $enseignant = LOGIEVAL_Database::get_row(LOGIEVAL_Database::t('enseignants'), ['id' => $ens_id]);
        if (!$enseignant || empty($enseignant['actif'])) {
            wp_send_json_error(["message"=>"Cet enseignant doit être activé avant d’être affecté à une classe."]); return;
        }

        $existing = LOGIEVAL_Database::get_row($t, ['classe_id'=>$classe_id,'enseignant_id'=>$ens_id]);
        if ($existing) {
            wp_send_json_error(["message"=>"Cet enseignant est déjà affecté à cette classe."]); return;
        }

        // If setting as maître, unset all other maîtres in this class
        if ($est_maitre) {
            global $wpdb;
            $wpdb->query($wpdb->prepare("UPDATE {$t} SET est_maitre=0 WHERE classe_id=%d", $classe_id));
        }

        LOGIEVAL_Database::insert($t, [
            'classe_id'     => $classe_id,
            'enseignant_id' => $ens_id,
            'branche_id'    => null,
            'est_maitre'    => $est_maitre,
        ]);
        wp_send_json_success();
    }

    private function ajax_remove_enseignant_classe(): void {
        $ce_id = intval($_POST['ce_id'] ?? 0);
        if (!$ce_id) { wp_send_json_error(['message'=>'ID manquant.']); return; }
        LOGIEVAL_Database::delete(LOGIEVAL_Database::t('classe_enseignant'), ['id'=>$ce_id]);
        wp_send_json_success();
    }

    private function ajax_set_maitre_classe(): void {
        global $wpdb;
        $ce_id     = intval($_POST['ce_id']    ?? 0);
        $classe_id = intval($_POST['classe_id']?? 0);
        if (!$ce_id || !$classe_id) { wp_send_json_error(["message"=>"Paramètres manquants."]); return; }
        $t = LOGIEVAL_Database::t('classe_enseignant');
        $row = LOGIEVAL_Database::get_row( $t, ['id' => $ce_id] );
        if ( ! $row || (int) $row['classe_id'] !== $classe_id ) {
            wp_send_json_error(['message' => 'Cette affectation n’appartient pas à cette classe.']); return;
        }
        // Unset all maîtres for this class
        $wpdb->query($wpdb->prepare("UPDATE {$t} SET est_maitre=0 WHERE classe_id=%d", $classe_id));
        // Set this one
        LOGIEVAL_Database::update($t, ['est_maitre'=>1], ['id'=>$ce_id]);
        wp_send_json_success();
    }

    private function ajax_get_classe_teachers_detail(): void {
        global $wpdb;
        $t         = LOGIEVAL_Database::tables();
        $classe_id = intval($_POST['classe_id'] ?? 0);
        $rows      = $wpdb->get_results($wpdb->prepare(
            "SELECT ce.id AS ce_id, ce.enseignant_id, ce.est_maitre, e.prenom, e.nom
             FROM {$t['classe_enseignant']} ce
             INNER JOIN {$t['enseignants']} e ON e.id=ce.enseignant_id
             WHERE ce.classe_id=%d ORDER BY e.nom, e.prenom", $classe_id
        ), ARRAY_A) ?: [];
        wp_send_json_success($rows);
    }

    private function ajax_get_classe_teachers(): void {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        $classe_id = intval($_POST['classe_id'] ?? 0);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.prenom, e.nom, e.actif FROM {$t['enseignants']} e
             INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id=e.id
             WHERE ce.classe_id=%d ORDER BY e.actif DESC, e.nom, e.prenom", $classe_id
        ), ARRAY_A);
        wp_send_json_success($rows);
    }

    private function ajax_save_settings(): void {
        $keys = [
            'logieval_etablissement', 'logieval_id_prefix', 'logieval_id_length',
            'logieval_login_page',
        ];
        foreach (LOGIEVAL_Mailer::templates() as $tpl) {
            $keys[] = $tpl['subject'];
            $keys[] = $tpl['body'];
        }
        foreach ( $keys as $k ) {
            if ( ! isset($_POST[$k]) ) continue;
            $value = wp_unslash($_POST[$k]);
            if ( $k === 'logieval_login_page' ) {
                update_option( $k, absint($value) );
            } elseif ( $k === 'logieval_id_length' ) {
                update_option( $k, min( 16, max( LOGIEVAL_Auth::MIN_ID_LENGTH, absint($value) ) ) );
            } elseif ( $k === 'logieval_id_prefix' ) {
                $prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $value ) );
                update_option( $k, substr( $prefix !== '' ? $prefix : 'ENS', 0, 6 ) );
            } else {
                update_option( $k, sanitize_textarea_field($value) );
            }
        }
        wp_send_json_success();
    }


    private function ajax_save_objectif(): void {
        $id   = intval($_POST['id'] ?? 0);
        $data = [
            'libelle'          => sanitize_textarea_field( wp_unslash( $_POST['libelle'] ?? '' ) ),
            'type'             => in_array($_POST['type']??'', ['radio','checkbox'], true) ? $_POST['type'] : 'checkbox',
            'maitre_seulement' => intval($_POST['maitre_seulement'] ?? 0),
            'ordre'            => intval($_POST['ordre'] ?? 0),
            'actif'            => intval($_POST['actif'] ?? 1),
        ];
        if ( trim( $data['libelle'] ) === '' ) { wp_send_json_error(['message' => 'Le libellé de l’objectif est obligatoire.']); return; }
        if ($id) LOGIEVAL_Database::update(LOGIEVAL_Database::t('objectifs'), $data, ['id'=>$id]);
        else $id = LOGIEVAL_Database::insert(LOGIEVAL_Database::t('objectifs'), $data);
        wp_send_json_success(['id'=>$id]);
    }

    private function ajax_save_sous_critere(): void {
        $id   = intval($_POST['id'] ?? 0);
        $data = [
            'objectif_id' => intval($_POST['objectif_id'] ?? 0),
            'lettre'      => sanitize_text_field( wp_unslash( $_POST['lettre'] ?? 'A' ) ),
            'libelle'     => sanitize_textarea_field( wp_unslash( $_POST['libelle'] ?? '' ) ),
            'points'      => floatval($_POST['points'] ?? 1),
            'ordre'       => intval($_POST['ordre'] ?? 0),
        ];
        if ( ! $data['objectif_id'] || ! LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('objectifs'), ['id' => $data['objectif_id']] ) ) {
            wp_send_json_error(['message' => 'Objectif introuvable.']); return;
        }
        if ( trim( $data['libelle'] ) === '' ) { wp_send_json_error(['message' => 'Le libellé du critère est obligatoire.']); return; }
        if ( $data['points'] < 0 ) { wp_send_json_error(['message' => 'Les points ne peuvent pas être négatifs.']); return; }
        if ($id) LOGIEVAL_Database::update(LOGIEVAL_Database::t('sous_criteres'), $data, ['id'=>$id]);
        else $id = LOGIEVAL_Database::insert(LOGIEVAL_Database::t('sous_criteres'), $data);
        wp_send_json_success(['id'=>$id]);
    }

    /* ── Page renderers ─────────────────────────────────────────── */
    public function page_dashboard(): void    { include LOGIEVAL_PATH . 'admin/views/dashboard.php'; }
    public function page_classes(): void      { include LOGIEVAL_PATH . 'admin/views/classes.php'; }
    public function page_eleves(): void       { include LOGIEVAL_PATH . 'admin/views/eleves.php'; }
    public function page_enseignants(): void  { include LOGIEVAL_PATH . 'admin/views/enseignants.php'; }
    public function page_branches(): void     { include LOGIEVAL_PATH . 'admin/views/branches.php'; }
    public function page_objectifs(): void { include LOGIEVAL_PATH . 'admin/views/objectifs.php'; }
    public function page_import(): void    { include LOGIEVAL_PATH . 'admin/views/import.php'; }
    public function page_evaluations(): void  { include LOGIEVAL_PATH . 'admin/views/evaluations.php'; }
    public function page_settings(): void     { include LOGIEVAL_PATH . 'admin/views/settings.php'; }
}
