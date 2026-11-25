<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LOGIEVAL_Mailer {

    /* LogiOne 1.53.0 : envoi différé des e-mails de notification. La réponse part d'abord vers l'enseignant
       (« Terminé » s'affiche tout de suite), les e-mails sont envoyés juste après, même si le serveur d'envoi est lent. */
    private static $queue = [];

    public static function later( string $method, array $args = [] ): void {
        if ( ! method_exists( __CLASS__, $method ) ) return;
        if ( ! self::$queue ) {
            register_shutdown_function( [ __CLASS__, 'flush_queue' ] );
        }
        self::$queue[] = [ $method, $args ];
    }

    public static function flush_queue(): void {
        $queue = self::$queue;
        self::$queue = [];
        if ( ! $queue ) return;
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            @fastcgi_finish_request(); // la réponse est envoyée, la suite tourne en arrière-plan
            @set_time_limit( 120 );
            foreach ( $queue as $job ) {
                try { call_user_func_array( [ __CLASS__, $job[0] ], $job[1] ); } catch ( \Throwable $e ) { error_log( 'LogiEval e-mail : ' . $e->getMessage() ); }
            }
            return;
        }
        // Sans PHP-FPM : tâche planifiée immédiate (lancée par WP-Cron dans une requête séparée).
        foreach ( $queue as $i => $job ) {
            wp_schedule_single_event( time() + $i, 'logieval_deferred_mail', [ $job[0], $job[1] ] );
        }
        if ( function_exists( 'spawn_cron' ) ) spawn_cron();
    }

    public static function run_deferred( $method, $args = [] ): void {
        if ( is_string( $method ) && method_exists( __CLASS__, $method ) ) {
            call_user_func_array( [ __CLASS__, $method ], (array) $args );
        }
    }


    public static function templates(): array {
        return [
            'assign' => [
                'title'     => 'Attribution d’une nouvelle évaluation',
                'subject'   => 'logieval_mail_assign_subject',
                'body'      => 'logieval_mail_assign_body',
                'variables' => '{nom_enseignant}, {classe}, {semestre}, {echeance}, {identifiant}, {lien}, {jours_restants}',
                'default_subject' => '[Évaluation] Nouvelle évaluation – {classe} – {semestre}',
                'default_body'    => "Bonjour {nom_enseignant},

Une nouvelle évaluation comportementale vous a été attribuée.

Classe    : {classe}
Semestre  : {semestre}
Échéance  : {echeance}

Votre identifiant d'accès : {identifiant}
Lien d'accès              : {lien}

Merci de compléter votre évaluation avant la date d'échéance.

Cordialement,
L'équipe de direction",
            ],
            'teacher_access' => [
                'title'     => 'Activation du profil enseignant',
                'subject'   => 'logieval_mail_teacher_access_subject',
                'body'      => 'logieval_mail_teacher_access_body',
                'variables' => '{nom_enseignant}, {identifiant}, {lien}, {guide}',
                'default_subject' => '[LogiEval] Votre accès enseignant est activé',
                'default_body'    => "Bonjour {nom_enseignant},

Votre profil enseignant LogiEval est maintenant activé.

Votre identifiant personnel : {identifiant}
Lien d'accès               : {lien}

Guide de l'enseignant (PDF, 12 pages) : {guide}

Conservez cet identifiant : il vous permettra d'accéder aux évaluations qui vous seront attribuées. Une fois connecté, le bouton « Aide » vous ramène au guide à tout moment.

Cordialement,
L'équipe de direction",
            ],
            'reminder' => [
                'title'     => 'Relance automatique avant échéance',
                'subject'   => 'logieval_mail_reminder_subject',
                'body'      => 'logieval_mail_reminder_body',
                'variables' => '{nom_enseignant}, {classe}, {semestre}, {echeance}, {identifiant}, {lien}, {jours_restants}',
                'default_subject' => '[RAPPEL] Évaluation {classe} – {semestre} – J-{jours_restants}',
                'default_body'    => "Bonjour {nom_enseignant},

Rappel : il vous reste {jours_restants} jour(s) pour compléter l'évaluation suivante.

Classe    : {classe}
Semestre  : {semestre}
Échéance  : {echeance}

Votre identifiant : {identifiant}
Lien               : {lien}

Merci de ne pas tarder.

Cordialement,
L'équipe de direction",
            ],
            'closure' => [
                'title'     => 'Évaluation clôturée - message au maître de classe',
                'subject'   => 'logieval_mail_closure_subject',
                'body'      => 'logieval_mail_closure_body',
                'variables' => '{nom_enseignant}, {classe}, {semestre}, {echeance}, {identifiant}, {lien}, {jours_restants}',
                'default_subject' => '[Évaluation] Clôturée – {classe} – {semestre}',
                'default_body'    => "Bonjour {nom_enseignant},

L'évaluation de la classe {classe} pour le semestre {semestre} est maintenant complète.

Vous pouvez accéder à l'export Word depuis votre interface.

Lien : {lien}

Cordialement,
L'équipe de direction",
            ],
            'teacher_completed' => [
                'title'     => 'Un enseignant a terminé son évaluation',
                'subject'   => 'logieval_mail_teacher_completed_subject',
                'body'      => 'logieval_mail_teacher_completed_body',
                'variables' => '{nom_enseignant}, {enseignant_termine}, {classe}, {semestre}, {echeance}, {identifiant}, {lien}',
                'default_subject' => '[LogiEval] {enseignant_termine} a terminé – {classe} – {semestre}',
                'default_body'    => "Bonjour {nom_enseignant},

{enseignant_termine} vient de terminer complètement son évaluation.

Classe   : {classe}
Semestre : {semestre}
Échéance : {echeance}

Si ce n'est pas encore fait, vous pouvez maintenant compléter la vôtre.

Votre identifiant : {identifiant}
Lien               : {lien}

Cordialement,
L'équipe de direction",
            ],
            'all_completed' => [
                'title'     => 'Tous les enseignants ont terminé - message au maître de classe',
                'subject'   => 'logieval_mail_all_completed_subject',
                'body'      => 'logieval_mail_all_completed_body',
                'variables' => '{nom_enseignant}, {classe}, {semestre}, {echeance}, {identifiant}, {lien}, {action}',
                'default_subject' => '[LogiEval] Tous les enseignants ont terminé – {classe} – {semestre}',
                'default_body'    => "Bonjour {nom_enseignant},

Tous les enseignants attribués à l'évaluation ont terminé leur saisie.

Classe   : {classe}
Semestre : {semestre}
Échéance : {echeance}

{action}

Votre identifiant : {identifiant}
Lien               : {lien}

Cordialement,
L'équipe de direction",
            ],
            'pending_completion' => [
                'title'     => 'Relance manuelle aux enseignants en attente',
                'subject'   => 'logieval_mail_pending_completion_subject',
                'body'      => 'logieval_mail_pending_completion_body',
                'variables' => '{nom_enseignant}, {classe}, {semestre}, {echeance}, {identifiant}, {lien}',
                'default_subject' => '[RAPPEL] Évaluation à terminer – {classe} – {semestre}',
                'default_body'    => "Bonjour {nom_enseignant},

Le maître de classe vous rappelle que votre évaluation n'est pas encore terminée.

Classe    : {classe}
Semestre  : {semestre}
Échéance  : {echeance}

Votre identifiant : {identifiant}
Lien               : {lien}

Merci de terminer votre évaluation afin que la clôture puisse être effectuée.

Cordialement,
L'équipe de direction",
            ],
            'last_teacher_pending' => [
                'title'     => 'Relance automatique au dernier enseignant en attente',
                'subject'   => 'logieval_mail_last_teacher_pending_subject',
                'body'      => 'logieval_mail_last_teacher_pending_body',
                'variables' => '{nom_enseignant}, {classe}, {semestre}, {echeance}, {identifiant}, {lien}',
                'default_subject' => '[RAPPEL] Vous êtes le dernier enseignant en attente – {classe} – {semestre}',
                'default_body'    => "Bonjour {nom_enseignant},

Les autres enseignants ont terminé leur évaluation. Vous êtes le dernier enseignant dont la validation est encore attendue.

Classe    : {classe}
Semestre  : {semestre}
Échéance  : {echeance}

Votre identifiant : {identifiant}
Lien               : {lien}

Merci de terminer votre évaluation afin que la clôture puisse être effectuée.

Cordialement,
L'équipe de direction",
            ],
            'class_report' => [
                'title'     => 'Envoi du rapport détaillé de classe',
                'subject'   => 'logieval_mail_class_report_subject',
                'body'      => 'logieval_mail_class_report_body',
                'variables' => '{nom_enseignant}, {classe}, {semestre}, {echeance}, {identifiant}, {lien_rapport}, {lien}',
                'default_subject' => '[LogiEval] Rapport détaillé – {classe} – {semestre}',
                'default_body'    => "Bonjour {nom_enseignant},

Le maître de classe vous transmet le rapport détaillé de l'évaluation clôturée.

Classe   : {classe}
Semestre : {semestre}
Échéance : {echeance}

Rapport détaillé : {lien_rapport}

Votre identifiant : {identifiant}
Lien d'accès      : {lien}

Cordialement,
L'équipe de direction",
            ],
        ];
    }

    /* ── Send assignment notification ───────────────────────────── */
    public static function notify_assignment( int $evaluation_id, array $enseignant_ids = [] ): int {
        global $wpdb;
        $t   = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row( $t['evaluations'], ['id' => $evaluation_id] );
        if ( ! $eval ) return 0;

        $classe = LOGIEVAL_Database::get_row( $t['classes'], ['id' => $eval['classe_id']] );
        if ( ! $classe ) return 0;

        $enseignant_ids = array_values( array_unique( array_filter( array_map( 'intval', $enseignant_ids ) ) ) );
        $where_ids = '';
        $params = [ $evaluation_id ];
        if ( ! empty( $enseignant_ids ) ) {
            $where_ids = ' AND ee.enseignant_id IN (' . implode( ',', array_fill( 0, count( $enseignant_ids ), '%d' ) ) . ')';
            $params = array_merge( $params, $enseignant_ids );
        }

        $enseignants = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT e.* FROM {$t['enseignants']} e
             INNER JOIN {$t['eval_enseignant']} ee ON ee.enseignant_id = e.id
             WHERE ee.evaluation_id = %d
               AND e.email <> ''
               AND e.actif = 1
               {$where_ids}
             ORDER BY e.nom, e.prenom",
            $params
        ), ARRAY_A ) ?: [];

        $login_url = self::login_url();
        $sent = 0;

        foreach ( $enseignants as $ens ) {
            $vars = [
                '{nom_enseignant}'  => $ens['prenom'] . ' ' . $ens['nom'],
                '{classe}'          => $classe['nom'] . ( $classe['niveau'] ? ' (' . $classe['niveau'] . ')' : '' ),
                '{semestre}'        => $eval['semestre'],
                '{echeance}'        => date_i18n( 'd.m.Y', strtotime( $eval['date_echeance'] ) ),
                '{identifiant}'     => $ens['identifiant_unique'],
                '{lien}'            => $login_url,
                '{jours_restants}'  => (string) max( 0, (int) floor( ( strtotime( $eval['date_echeance'] ) - time() ) / 86400 ) ),
            ];
            $subject = self::parse( self::template_subject('assign'), $vars );
            $body    = self::parse( self::default_assign_body(), $vars );
            self::send( $ens['email'], $subject, $body );
            $sent++;
        }

        return $sent;
    }

    /* ── Send reminders (called daily by cron) ──────────────────── */
    public static function send_reminders(): void {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        // Safety net for evaluations that already have exactly one teacher pending.
        $active_ids = $wpdb->get_col("SELECT id FROM {$t['evaluations']} WHERE statut='active'") ?: [];
        foreach ($active_ids as $active_id) {
            self::notify_last_teacher_pending((int)$active_id);
        }

        // Échéance dans 7 jours ou moins (avant : uniquement « exactement J-7 » — un cron
        // manqué ce jour-là et le rappel ne partait jamais). Un drapeau évite les doublons.
        $today       = current_time( 'Y-m-d' );
        $target_date = date( 'Y-m-d', strtotime( $today . ' +7 days' ) );
        $evals = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t['evaluations']} WHERE statut = 'active' AND date_echeance >= %s AND date_echeance <= %s",
            $today, $target_date
        ), ARRAY_A ) ?: [];

        foreach ( $evals as $eval ) {
            if ( get_option( 'logieval_j7_sent_' . $eval['id'] ) ) continue;
            update_option( 'logieval_j7_sent_' . $eval['id'], current_time( 'mysql' ), false );
            $days_left = max( 0, (int) round( ( strtotime( $eval['date_echeance'] ) - strtotime( $today ) ) / DAY_IN_SECONDS ) );
            $classe = LOGIEVAL_Database::get_row( $t['classes'], ['id' => $eval['classe_id']] );
            // Teachers who haven't finished
            $pending = $wpdb->get_results( $wpdb->prepare(
                "SELECT e.* FROM {$t['enseignants']} e
                 INNER JOIN {$t['eval_enseignant']} ee ON ee.enseignant_id = e.id
                 WHERE ee.evaluation_id = %d AND ee.statut = 'en_cours'
                   AND e.actif = 1",
                $eval['id']
            ), ARRAY_A );

            foreach ( $pending as $ens ) {
                $vars = [
                    '{nom_enseignant}'  => $ens['prenom'] . ' ' . $ens['nom'],
                    '{classe}'          => $classe['nom'],
                    '{semestre}'        => $eval['semestre'],
                    '{echeance}'        => date_i18n( 'd.m.Y', strtotime( $eval['date_echeance'] ) ),
                    '{identifiant}'     => $ens['identifiant_unique'],
                    '{lien}'            => self::login_url(),
                    '{jours_restants}'  => (string) $days_left,
                ];
                $subject = self::parse( self::template_subject('reminder'), $vars );
                $body    = self::parse( self::default_reminder_body(), $vars );
                self::send( $ens['email'], $subject, $body );
            }
        }
    }

    /* ── Notify closure to maître de classe ─────────────────────── */
    public static function notify_closure( int $evaluation_id ): void {
        global $wpdb;
        $t    = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row( $t['evaluations'], ['id' => $evaluation_id] );
        if ( ! $eval ) return;
        $classe = LOGIEVAL_Database::get_row( $t['classes'], ['id' => $eval['classe_id']] );

        // Find maître de classe
        $maitre_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.* FROM {$t['enseignants']} e
             INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id = e.id
             WHERE ce.classe_id = %d AND ce.est_maitre = 1 AND e.actif = 1 LIMIT 1",
            $eval['classe_id']
        ), ARRAY_A );

        if ( $maitre_row && ! empty( $eval['mail_closure_maitre'] ) ) {
            $vars = [
                '{nom_enseignant}' => $maitre_row['prenom'] . ' ' . $maitre_row['nom'],
                '{classe}'         => $classe['nom'],
                '{semestre}'       => $eval['semestre'],
                '{echeance}'       => date_i18n( 'd.m.Y', strtotime( $eval['date_echeance'] ) ),
                '{identifiant}'    => $maitre_row['identifiant_unique'],
                '{lien}'           => self::login_url(),
                '{jours_restants}' => '0',
            ];
            $subject = self::parse( self::template_subject('closure'), $vars );
            $body    = self::parse( self::default_closure_body(), $vars );
            self::send( $maitre_row['email'], $subject, $body );
        }

    }

    public static function notify_teacher_completed( int $evaluation_id, int $completed_enseignant_id ): void {
        global $wpdb;
        $t    = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row( $t['evaluations'], ['id' => $evaluation_id] );
        if ( ! $eval || empty( $eval['mail_teacher_completed'] ) ) return;

        $classe    = LOGIEVAL_Database::get_row( $t['classes'], ['id' => $eval['classe_id']] );
        $completed = LOGIEVAL_Database::get_row( $t['enseignants'], ['id' => $completed_enseignant_id] );
        if ( ! $classe || ! $completed ) return;

        $enseignants = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.* FROM {$t['enseignants']} e
             INNER JOIN {$t['eval_enseignant']} ee ON ee.enseignant_id = e.id
             WHERE ee.evaluation_id = %d
               AND ee.statut = 'en_cours'
               AND ee.enseignant_id <> %d
               AND e.email <> ''
               AND e.actif = 1
             ORDER BY e.nom, e.prenom",
            $evaluation_id,
            $completed_enseignant_id
        ), ARRAY_A ) ?: [];

        $completed_name = trim( $completed['prenom'] . ' ' . $completed['nom'] );
        foreach ( $enseignants as $ens ) {
            $vars = [
                '{nom_enseignant}'     => $ens['prenom'] . ' ' . $ens['nom'],
                '{enseignant_termine}' => $completed_name,
                '{classe}'             => $classe['nom'],
                '{semestre}'           => $eval['semestre'],
                '{echeance}'           => date_i18n( 'd.m.Y', strtotime( $eval['date_echeance'] ) ),
                '{identifiant}'        => $ens['identifiant_unique'],
                '{lien}'               => self::login_url(),
            ];
            $subject = self::parse( self::template_subject('teacher_completed'), $vars );
            $body = self::parse( self::template_body('teacher_completed'), $vars );
            self::send( $ens['email'], $subject, $body );
        }
    }

    public static function notify_all_teachers_completed( int $evaluation_id, bool $has_arbitrages = false ): void {
        global $wpdb;
        $t    = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row( $t['evaluations'], ['id' => $evaluation_id] );
        if ( ! $eval || empty( $eval['mail_all_completed'] ) ) return;

        $classe = LOGIEVAL_Database::get_row( $t['classes'], ['id' => $eval['classe_id']] );
        if ( ! $classe ) return;

        $maitre = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.* FROM {$t['enseignants']} e
             INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id = e.id
             WHERE ce.classe_id = %d AND ce.est_maitre = 1 AND e.actif = 1 LIMIT 1",
            $eval['classe_id']
        ), ARRAY_A );
        if ( ! $maitre || empty( $maitre['email'] ) ) return;

        $vars = [
            '{nom_enseignant}' => $maitre['prenom'] . ' ' . $maitre['nom'],
            '{classe}'         => $classe['nom'],
            '{semestre}'       => $eval['semestre'],
            '{echeance}'       => date_i18n( 'd.m.Y', strtotime( $eval['date_echeance'] ) ),
            '{identifiant}'    => $maitre['identifiant_unique'],
            '{lien}'           => self::login_url(),
            '{action}'         => $has_arbitrages
                ? "Des égalités ont été détectées et seront automatiquement comptées en demi-points."
                : "Vous pouvez vérifier les résultats, clôturer si nécessaire et imprimer les documents.",
        ];
        $subject = self::parse( self::template_subject('all_completed'), $vars );
        $body = self::parse( self::template_body('all_completed'), $vars );
        self::send( $maitre['email'], $subject, $body );
    }

    public static function notify_pending_completion( int $evaluation_id ): int {
        global $wpdb;
        $t    = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row( $t['evaluations'], ['id' => $evaluation_id] );
        if ( ! $eval ) return 0;
        $classe = LOGIEVAL_Database::get_row( $t['classes'], ['id' => $eval['classe_id']] );
        if ( ! $classe ) return 0;
        $pending = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.* FROM {$t['enseignants']} e
             INNER JOIN {$t['eval_enseignant']} ee ON ee.enseignant_id = e.id
             LEFT JOIN {$t['classe_enseignant']} ce
               ON ce.classe_id = %d AND ce.enseignant_id = e.id
             WHERE ee.evaluation_id = %d
               AND ee.statut = 'en_cours'
               AND COALESCE(ce.est_maitre,0) = 0
               AND e.email <> ''
               AND e.actif = 1
             ORDER BY e.nom, e.prenom",
            $eval['classe_id'],
            $evaluation_id
        ), ARRAY_A ) ?: [];

        foreach ( $pending as $ens ) {
            $vars = [
                '{nom_enseignant}' => $ens['prenom'] . ' ' . $ens['nom'],
                '{classe}'         => $classe['nom'],
                '{semestre}'       => $eval['semestre'],
                '{echeance}'       => date_i18n( 'd.m.Y', strtotime( $eval['date_echeance'] ) ),
                '{identifiant}'    => $ens['identifiant_unique'],
                '{lien}'           => self::login_url(),
            ];
            $subject = self::parse( self::template_subject('pending_completion'), $vars );
            $body = self::parse( self::template_body('pending_completion'), $vars );
            self::send( $ens['email'], $subject, $body );
        }

        return count($pending);
    }

    public static function notify_last_teacher_pending( int $evaluation_id ): int {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$evaluation_id]);
        if (!$eval || $eval['statut'] !== 'active' || !empty($eval['last_teacher_reminder_sent'])) return 0;

        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT e.* FROM {$t['enseignants']} e
             INNER JOIN {$t['eval_enseignant']} ee ON ee.enseignant_id=e.id
             WHERE ee.evaluation_id=%d AND ee.statut='en_cours'
               AND e.actif = 1
             ORDER BY e.nom,e.prenom",
            $evaluation_id
        ), ARRAY_A) ?: [];
        if (count($pending) !== 1) return 0;
        $completed_count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t['eval_enseignant']} WHERE evaluation_id=%d AND statut='termine'",
            $evaluation_id
        ));
        if ($completed_count < 1) return 0;

        $ens = $pending[0];
        if (empty($ens['email'])) return 0;
        $classe = LOGIEVAL_Database::get_row($t['classes'], ['id'=>$eval['classe_id']]);
        if (!$classe) return 0;

        $claimed = $wpdb->update(
            $t['evaluations'],
            ['last_teacher_reminder_sent'=>1],
            ['id'=>$evaluation_id, 'last_teacher_reminder_sent'=>0]
        );
        if (!$claimed) return 0;

        $vars = [
            '{nom_enseignant}' => $ens['prenom'].' '.$ens['nom'],
            '{classe}'         => $classe['nom'],
            '{semestre}'       => $eval['semestre'],
            '{echeance}'       => date_i18n('d.m.Y', strtotime($eval['date_echeance'])),
            '{identifiant}'    => $ens['identifiant_unique'],
            '{lien}'           => self::login_url(),
        ];
        self::send(
            $ens['email'],
            self::parse(self::template_subject('last_teacher_pending'), $vars),
            self::parse(self::template_body('last_teacher_pending'), $vars)
        );
        return 1;
    }

    public static function send_class_report( int $evaluation_id, string $report_url ): int {
        global $wpdb;
        $t    = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row( $t['evaluations'], ['id' => $evaluation_id] );
        if ( ! $eval || $eval['statut'] !== 'cloturee' ) return 0;
        $classe = LOGIEVAL_Database::get_row( $t['classes'], ['id' => $eval['classe_id']] );
        if ( ! $classe ) return 0;

        $enseignants = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT e.* FROM {$t['enseignants']} e
             INNER JOIN {$t['eval_enseignant']} ee ON ee.enseignant_id = e.id
             WHERE ee.evaluation_id = %d AND e.email <> '' AND e.actif = 1
             ORDER BY e.nom, e.prenom",
            $evaluation_id
        ), ARRAY_A ) ?: [];

        $sent = 0;
        foreach ( $enseignants as $ens ) {
            $vars = [
                '{nom_enseignant}' => $ens['prenom'] . ' ' . $ens['nom'],
                '{classe}'         => $classe['nom'],
                '{semestre}'       => $eval['semestre'],
                '{echeance}'       => date_i18n( 'd.m.Y', strtotime( $eval['date_echeance'] ) ),
                '{identifiant}'    => $ens['identifiant_unique'],
                '{lien_rapport}'   => $report_url,
                '{lien}'           => self::login_url(),
            ];
            $subject = self::parse( self::template_subject('class_report'), $vars );
            $body    = self::parse( self::template_body('class_report'), $vars );
            self::send( $ens['email'], $subject, $body );
            $sent++;
        }
        return $sent;
    }

    public static function notify_teacher_access( int $enseignant_id ): bool {
        $ens = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['id' => $enseignant_id] );
        if ( ! $ens || empty( $ens['email'] ) || empty( $ens['actif'] ) ) {
            return false;
        }

        $vars = [
            '{nom_enseignant}' => $ens['prenom'] . ' ' . $ens['nom'],
            '{identifiant}'    => $ens['identifiant_unique'],
            '{lien}'           => self::login_url(),
            '{guide}'          => self::guide_url(),
        ];
        $body = self::template_body('teacher_access');
        // 1.53.12 : un modèle personnalisé sans {guide} reçoit quand même le lien vers le guide.
        if ( strpos( $body, '{guide}' ) === false ) $body .= "\n\nGuide de l'enseignant (PDF) : {guide}";

        $ok = self::send(
            $ens['email'],
            self::parse( self::template_subject('teacher_access'), $vars ),
            self::parse( $body, $vars )
        );
        if ( $ok ) { // 1.53.11 : date d'envoi des accès, pour savoir à qui il reste à les envoyer
            $sent = get_option( 'logieval_access_sent', [] );
            $sent = is_array( $sent ) ? $sent : [];
            $sent[ $enseignant_id ] = current_time( 'mysql' );
            update_option( 'logieval_access_sent', $sent, false );
        }
        return $ok;
    }

    /* ── Core send ──────────────────────────────────────────────── */
    private static function send( string $to, string $subject, string $body ): bool {
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        return (bool) wp_mail( $to, $subject, nl2br( esc_html( $body ) ), $headers );
    }

    private static function parse( string $template, array $vars ): string {
        return str_replace( array_keys( $vars ), array_values( $vars ), $template );
    }

    /** 1.53.12 : guide PDF de l'enseignant (fourni avec LogiEval, remplaçable dans les Réglages). */
    public static function guide_url(): string {
        $custom = trim( (string) get_option( 'logieval_guide_url', '' ) );
        if ( $custom !== '' ) return $custom;
        $file = LOGIEVAL_PATH . 'assets/guide-logieval.pdf';
        return LOGIEVAL_URL . 'assets/guide-logieval.pdf' . ( is_file( $file ) ? '?v=' . filemtime( $file ) : '' );
    }

    public static function login_url(): string {
        $page_id = absint( get_option( 'logieval_login_page', 0 ) );
        $url = '';
        if ( $page_id && get_post_status( $page_id ) ) {
            $url = get_permalink( $page_id );
            if ( ! $url ) {
                $url = home_url( '/?page_id=' . $page_id );
            }
        }
        if ( ! $url ) {
            $url = home_url( '/?logieval=login' );
        }
        return remove_query_arg( 'logieval_logout', $url );
    }

    private static function template_subject( string $template_key ): string {
        $templates = self::templates();
        if ( empty( $templates[$template_key] ) ) return '';
        $value = get_option( $templates[$template_key]['subject'], '' );
        return stripslashes($value !== '' ? $value : $templates[$template_key]['default_subject']);
    }

    private static function template_body( string $template_key ): string {
        $templates = self::templates();
        if ( empty( $templates[$template_key] ) ) return '';
        $value = get_option( $templates[$template_key]['body'], '' );
        return stripslashes($value !== '' ? $value : $templates[$template_key]['default_body']);
    }

    /* ── Default templates ──────────────────────────────────────── */
    private static function default_assign_body(): string {
        return self::template_body('assign');
    }

    private static function default_reminder_body(): string {
        return self::template_body('reminder');
    }

    private static function default_closure_body(): string {
        return self::template_body('closure');
    }
}
