<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LOGIEVAL_Database {

    public static function tables(): array {
        global $wpdb;
        $p = $wpdb->prefix . 'logieval_';
        return [
            'classes'           => $p . 'classes',
            'eleves'            => $p . 'eleves',
            'enseignants'       => $p . 'enseignants',
            'branches'          => $p . 'branches',
            'objectifs'         => $p . 'objectifs',
            'sous_criteres'     => $p . 'sous_criteres',
            'classe_enseignant' => $p . 'classe_enseignant',
            'evaluations'       => $p . 'evaluations',
            'eval_enseignant'   => $p . 'eval_enseignant',
            'reponses'          => $p . 'reponses',
            'non_evaluables'    => $p . 'non_evaluables',
            'arbitrages'        => $p . 'arbitrages',
            'notes_ref'         => $p . 'notes_ref',
        ];
    }

    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $t = self::tables();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $enseignants_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t['enseignants'])) === $t['enseignants'];
        $had_enseignants_actif = $enseignants_table_exists && self::column_exists($t['enseignants'], 'actif');

        dbDelta( "CREATE TABLE {$t['classes']} (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom            VARCHAR(120) NOT NULL,
            niveau         VARCHAR(60)  NOT NULL DEFAULT '',
            annee_scolaire VARCHAR(20)  NOT NULL DEFAULT '',
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['eleves']} (
            id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
            classe_id INT UNSIGNED NOT NULL,
            prenom    VARCHAR(80)  NOT NULL,
            nom       VARCHAR(80)  NOT NULL,
            num_eleve VARCHAR(30)  NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY classe_id (classe_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['enseignants']} (
            id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
            prenom             VARCHAR(80)  NOT NULL,
            nom                VARCHAR(80)  NOT NULL,
            email              VARCHAR(160) NOT NULL,
            identifiant_unique VARCHAR(20)  NOT NULL,
            actif              TINYINT(1)   NOT NULL DEFAULT 0,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifiant_unique (identifiant_unique),
            UNIQUE KEY email (email)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['branches']} (
            id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
            libelle VARCHAR(120) NOT NULL,
            actif   TINYINT(1)   NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) $charset;" );

        // type=radio  → l'enseignant coche UNE seule option (A, B, C…)
        // type=checkbox → chaque sous-critère est coché/décoché indépendamment
        dbDelta( "CREATE TABLE {$t['objectifs']} (
            id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            libelle          TEXT         NOT NULL,
            type             ENUM('radio','checkbox') NOT NULL DEFAULT 'checkbox',
            maitre_seulement TINYINT(1)   NOT NULL DEFAULT 0,
            actif            TINYINT(1)   NOT NULL DEFAULT 1,
            ordre            INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['sous_criteres']} (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            objectif_id INT UNSIGNED NOT NULL,
            lettre      VARCHAR(5)   NOT NULL DEFAULT 'A',
            libelle     TEXT         NOT NULL,
            points      DECIMAL(5,2) NOT NULL DEFAULT 1.00,
            ordre       INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY objectif_id (objectif_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['classe_enseignant']} (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            classe_id     INT UNSIGNED NOT NULL,
            enseignant_id INT UNSIGNED NOT NULL,
            branche_id    INT UNSIGNED DEFAULT NULL,
            est_maitre    TINYINT(1)   NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_ce (classe_id, enseignant_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['evaluations']} (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            classe_id     INT UNSIGNED NOT NULL,
            semestre      VARCHAR(40)  NOT NULL,
            date_creation DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_echeance DATE         NOT NULL,
            statut        ENUM('active','cloturee') NOT NULL DEFAULT 'active',
            mode_calcul   ENUM('majoritaire','proportionnel') NOT NULL DEFAULT 'majoritaire',
            mail_teacher_completed TINYINT(1) NOT NULL DEFAULT 0,
            mail_all_completed     TINYINT(1) NOT NULL DEFAULT 0,
            mail_closure_maitre    TINYINT(1) NOT NULL DEFAULT 0,
            last_teacher_reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
            created_by    INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY classe_id (classe_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['eval_enseignant']} (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            evaluation_id INT UNSIGNED NOT NULL,
            enseignant_id INT UNSIGNED NOT NULL,
            statut        ENUM('en_cours','termine') NOT NULL DEFAULT 'en_cours',
            date_fin      DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_ee (evaluation_id, enseignant_id)
        ) $charset;" );

        // Une ligne = un sous_critere sélectionné/coché pour un élève par un enseignant
        dbDelta( "CREATE TABLE {$t['reponses']} (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            evaluation_id   INT UNSIGNED NOT NULL,
            enseignant_id   INT UNSIGNED NOT NULL,
            eleve_id        INT UNSIGNED NOT NULL,
            sous_critere_id INT UNSIGNED NOT NULL,
            vote            ENUM('oui','non','abstention') NOT NULL DEFAULT 'abstention',
            vote_set        TINYINT(1)   NOT NULL DEFAULT 0,
            commentaire     TEXT         DEFAULT NULL,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_rep (evaluation_id, enseignant_id, eleve_id, sous_critere_id),
            KEY eleve_id (eleve_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$t['notes_ref']} (
            id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
            evaluation_id_source INT UNSIGNED NOT NULL,
            evaluation_id_dest   INT UNSIGNED NOT NULL,
            eleve_id             INT UNSIGNED NOT NULL,
            note_calculee        DECIMAL(3,1) DEFAULT NULL,
            points_obtenus       DECIMAL(5,2) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_ref (evaluation_id_dest, eleve_id)
        ) $charset;" );

        // One row = one (enseignant, eval, eleve, objectif) marked as non-evaluable
        dbDelta( "CREATE TABLE {$t['non_evaluables']} (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            evaluation_id   INT UNSIGNED NOT NULL,
            enseignant_id   INT UNSIGNED NOT NULL,
            eleve_id        INT UNSIGNED NOT NULL,
            objectif_id     INT UNSIGNED NOT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_ne (evaluation_id, enseignant_id, eleve_id, objectif_id)
        ) $charset;" );

        // Maître de classe arbitration on tied sub-criteria
        dbDelta( "CREATE TABLE {$t['arbitrages']} (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            evaluation_id   INT UNSIGNED NOT NULL,
            eleve_id        INT UNSIGNED NOT NULL,
            sous_critere_id INT UNSIGNED NOT NULL,
            decision        TINYINT(1)   NOT NULL DEFAULT 0,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_arb (evaluation_id, eleve_id, sous_critere_id)
        ) $charset;" );

        self::migrate_reponses_table();
        if (!$had_enseignants_actif) {
            $wpdb->query("UPDATE {$t['enseignants']} SET actif=1");
        }
        self::ensure_retards_default_criterion();

        update_option( 'logieval_db_version', LOGIEVAL_VERSION );
        self::maybe_insert_defaults();
        self::repair_slashed_texts();
    }

    /**
     * 2.1.0 — Avant cette version, les textes saisis étaient enregistrés avec les barres obliques
     * ajoutées par WordPress (« l\'élève »). Réparation unique des textes déjà enregistrés.
     */
    private static function repair_slashed_texts(): void {
        if ( get_option( 'logieval_unslash_repaired' ) ) return;
        global $wpdb;
        $t = self::tables();
        $columns = [
            'reponses'      => ['commentaire'],
            'evaluations'   => ['semestre'],
            'classes'       => ['nom', 'niveau', 'annee_scolaire'],
            'eleves'        => ['prenom', 'nom', 'num_eleve'],
            'enseignants'   => ['prenom', 'nom'],
            'branches'      => ['libelle'],
            'objectifs'     => ['libelle'],
            'sous_criteres' => ['lettre', 'libelle'],
        ];
        foreach ( $columns as $key => $cols ) {
            foreach ( $cols as $col ) {
                $rows = $wpdb->get_results( "SELECT id, `{$col}` AS v FROM {$t[$key]} WHERE LOCATE(CHAR(92), `{$col}`) > 0", ARRAY_A ) ?: [];
                foreach ( $rows as $row ) {
                    $v = (string) $row['v'];
                    $guard = 0;
                    while ( preg_match( '/\\\\[\'"\\\\]/', $v ) && $guard++ < 5 ) {
                        $v = stripslashes( $v );
                    }
                    if ( $v !== $row['v'] ) {
                        $wpdb->update( $t[$key], [ $col => $v ], [ 'id' => (int) $row['id'] ] );
                    }
                }
            }
        }
        update_option( 'logieval_unslash_repaired', 1, false );
    }

    public static function maybe_upgrade(): void {
        // LogiOne 1.53.0 : la vérification détaillée des tables (une quinzaine de requêtes) ne se fait plus à chaque
        // page du site, mais après une mise à jour et au plus une fois par jour.
        if (get_option('logieval_db_version') !== LOGIEVAL_VERSION) {
            self::install();
            set_transient('logieval_schema_checked', 1, DAY_IN_SECONDS);
            return;
        }
        if (get_transient('logieval_schema_checked')) {
            return;
        }
        if (self::schema_needs_upgrade()) {
            self::install();
        }
        set_transient('logieval_schema_checked', 1, DAY_IN_SECONDS);
    }

    private static function schema_needs_upgrade(): bool {
        global $wpdb;
        $t = self::tables();

        foreach (['reponses', 'non_evaluables', 'arbitrages'] as $key) {
            $table = $t[$key];
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) return true;
        }

        return !self::column_exists($t['reponses'], 'vote')
            || !self::column_exists($t['reponses'], 'vote_set')
            || !self::column_exists($t['reponses'], 'commentaire')
            || !self::column_exists($t['evaluations'], 'mode_calcul')
            || !self::column_exists($t['evaluations'], 'mail_teacher_completed')
            || !self::column_exists($t['evaluations'], 'mail_all_completed')
            || !self::column_exists($t['evaluations'], 'mail_closure_maitre')
            || !self::column_exists($t['evaluations'], 'last_teacher_reminder_sent')
            || !self::column_exists($t['enseignants'], 'actif')
            || self::retards_criterion_needs_upgrade();
    }

    private static function column_exists( string $table, string $column ): bool {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        return !empty($found);
    }

    private static function migrate_reponses_table(): void {
        global $wpdb;
        $t = self::tables();

        $has_coche = self::column_exists($t['reponses'], 'coche');
        $has_vote  = self::column_exists($t['reponses'], 'vote');

        if (!$has_vote) {
            $wpdb->query("ALTER TABLE {$t['reponses']} ADD COLUMN vote ENUM('oui','non','abstention') NOT NULL DEFAULT 'abstention' AFTER sous_critere_id");
            $has_vote = true;
        }

        if ($has_coche && $has_vote) {
            $wpdb->query("UPDATE {$t['reponses']} SET vote = CASE WHEN coche=1 THEN 'oui' ELSE 'non' END WHERE vote='abstention'");
            $wpdb->query("ALTER TABLE {$t['reponses']} DROP COLUMN coche");
        }

        if (!self::column_exists($t['reponses'], 'vote_set')) {
            $wpdb->query("ALTER TABLE {$t['reponses']} ADD COLUMN vote_set TINYINT(1) NOT NULL DEFAULT 0 AFTER vote");
            $wpdb->query("UPDATE {$t['reponses']} SET vote_set=1 WHERE vote IN ('oui','non','abstention')");
        }

        if (!self::column_exists($t['reponses'], 'commentaire')) {
            $wpdb->query("ALTER TABLE {$t['reponses']} ADD COLUMN commentaire TEXT DEFAULT NULL AFTER vote_set");
        }
    }

    private static function retards_criterion_needs_upgrade(): bool {
        // LogiOne 1.28 : cette mise à niveau ne se fait qu'une fois ; ensuite, les modifications de
        // l'administrateur (texte, lettres, points) ne sont plus jamais remplacées.
        if ( get_option( 'logieval_retards_upgraded' ) ) return false;
        global $wpdb;
        $t = self::tables();
        $objectif_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['objectifs']} WHERE libelle=%s LIMIT 1",
            "L'apprenti·e arrive à l'heure aux cours."
        ));
        if (!$objectif_id) return false;

        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t['sous_criteres']} WHERE objectif_id=%d AND libelle=%s",
            $objectif_id,
            "Aucune arrivée tardive."
        ));

        return $exists === 0;
    }

    private static function ensure_retards_default_criterion(): void {
        if ( get_option( 'logieval_retards_upgraded' ) ) return;
        global $wpdb;
        $t = self::tables();
        $objectif_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['objectifs']} WHERE libelle=%s LIMIT 1",
            "L'apprenti·e arrive à l'heure aux cours."
        ));
        if (!$objectif_id) return;

        $new_label = "Aucune arrivée tardive.";
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t['sous_criteres']} WHERE objectif_id=%d AND libelle=%s",
            $objectif_id,
            $new_label
        ));

        // Déjà 4 choix ou plus (texte modifié par l'administrateur) : rien à ajouter.
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['sous_criteres']} WHERE objectif_id=%d", $objectif_id));
        if ($count >= 4) $exists = 1;
        if (!$exists) {
            $wpdb->insert($t['sous_criteres'], [
                'objectif_id' => $objectif_id,
                'lettre'      => 'A',
                'libelle'     => $new_label,
                'points'      => 3,
                'ordre'       => 0,
            ]);
        }

        update_option( 'logieval_retards_upgraded', 1, false );
        if ( $exists ) return; // déjà en place : on ne touche plus aux lettres ni aux points

        $updates = [
            ["Max 2 arrivées tardives.", 'B', 2, 1],
            ["Max 3 arrivées tardives.", 'C', 1, 2],
            ["Dès 4 arrivées tardives.", 'D', 0, 3],
        ];

        foreach ($updates as [$libelle, $lettre, $points, $ordre]) {
            $wpdb->update(
                $t['sous_criteres'],
                ['lettre' => $lettre, 'points' => $points, 'ordre' => $ordre],
                ['objectif_id' => $objectif_id, 'libelle' => $libelle]
            );
        }
    }

    /* ── Default CPNV objectives ────────────────────────────────── */
    private static function maybe_insert_defaults(): void {
        global $wpdb;
        $t = self::tables();
        if ( (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['objectifs']}") > 0 ) return;

        $defs = [
            [ "L'apprenti·e est présent·e aux cours.",                                'radio',    1, 1 ],
            [ "L'apprenti·e arrive à l'heure aux cours.",                              'radio',    1, 2 ],
            [ "L'apprenti·e communique de manière respectueuse et polie.",             'checkbox', 0, 3 ],
            [ "L'apprenti·e a un comportement propice au travail en classe.",          'checkbox', 0, 4 ],
            [ "L'apprenti·e a ses affaires et travaille de manière autonome.",         'checkbox', 0, 5 ],
            [ "L'apprenti·e est autonome dans l'utilisation des moyens numériques.",   'checkbox', 0, 6 ],
            [ "L'apprenti·e est un soutien (leader) positif pour la classe.",          'checkbox', 0, 7 ],
        ];

        $sous = [
            1 => [ ['A',"Pas d'absence ou max 25% d'absences justifiées.",3], ['B',"Plus de 25% d'absences justifiées.",2], ['C',"Comporte des absences non justifiées.",0] ],
            2 => [ ['A',"Aucune arrivée tardive.",3], ['B',"Max 2 arrivées tardives.",2], ['C',"Max 3 arrivées tardives.",1], ['D',"Dès 4 arrivées tardives.",0] ],
            3 => [
                ['A',"L'apprenti·e écoute attentivement les opinions des autres sans les interrompre, même s'il/elle n'est pas d'accord, et répond de manière respectueuse.",1],
                ['B',"L'apprenti·e utilise régulièrement des mots et expressions de politesse (comme « s'il vous plaît », « merci », « excusez-moi ») lorsqu'il/elle parle avec les enseignants et les collègues.",1],
                ['C',"L'apprenti·e utilise un vocabulaire correct tant avec ses collègues qu'avec les enseignant·es.",1],
                ['D',"Il·elle parle sans arrogance et adopte un langage corporel ouvert et positif (par exemple, sourire, contact visuel, posture ouverte).",1],
            ],
            4 => [
                ['A',"L'apprenti·e reste concentré·e pendant les cours, écoute activement les explications et ne se laisse pas distraire par des éléments extérieurs.",1],
                ['B',"L'apprenti·e participe activement aux discussions de classe, pose des questions pertinentes et contribue de manière constructive aux activités de groupe.",1],
                ['C',"L'apprenti·e suit les consignes données par l'enseignant·e et respecte les règles de la classe.",1],
                ['D',"L'apprenti·e est capable de gérer son temps de manière autonome pendant les cours, en réalisant les tâches assignées dans les délais impartis.",1],
            ],
            5 => [
                ['A',"L'apprenti·e vient en classe avec tout le matériel nécessaire pour les activités prévues.",1],
                ['B',"L'apprenti·e apporte les devoirs complétés et tout autre document demandé par l'enseignant·e.",1],
            ],
            6 => [
                ['A',"L'apprenti·e est responsable de la gestion de ses identifiants et se connecte de manière autonome aux plateformes numériques officielles.",1],
                ['B',"L'apprenti·e utilise de manière autonome les outils numériques officiels (Teams, messagerie CPNV, etc.) pour accomplir les tâches en classe.",1],
                ['C',"L'apprenti·e consulte régulièrement sa messagerie et répond aux messages envoyés par les enseignant·es.",1],
            ],
            7 => [
                ['A',"L'apprenti·e prend un rôle de leader lors de travaux de groupe en guidant efficacement le groupe et en valorisant les contributions de chacun.",1],
            ],
        ];

        $ids = [];
        foreach ( $defs as $i => [$libelle, $type, $maitre, $ordre] ) {
            $wpdb->insert( $t['objectifs'], [
                'libelle'          => $libelle,
                'type'             => $type,
                'maitre_seulement' => (int)$maitre,
                'actif'            => 1,
                'ordre'            => (int)$ordre,
            ]);
            $ids[$i+1] = (int)$wpdb->insert_id;
        }
        foreach ( $sous as $idx => $items ) {
            foreach ( $items as $ord => [$lettre,$lib,$pts] ) {
                $wpdb->insert( $t['sous_criteres'], ['objectif_id'=>$ids[$idx],'lettre'=>$lettre,'libelle'=>$lib,'points'=>$pts,'ordre'=>$ord] );
            }
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'logieval_daily_cron' );
    }

    public static function t( string $key ): string { return self::tables()[$key] ?? ''; }

    /**
     * LogiOne 1.53.1 : élèves d'une évaluation. Une évaluation clôturée garde les élèves qui y ont des réponses,
     * même s'ils ont changé de classe depuis (redoublement) ; un élève arrivé après la clôture n'y apparaît pas.
     * Une évaluation en cours montre la classe actuelle, plus un élève parti en cours de route qui a déjà des réponses.
     */
    public static function eval_students( array $eval ): array {
        global $wpdb;
        $t   = self::tables();
        $eid = (int) $eval['id'];
        $with_data = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT eleve_id FROM {$t['reponses']} WHERE evaluation_id=%d
             UNION SELECT eleve_id FROM {$t['non_evaluables']} WHERE evaluation_id=%d
             UNION SELECT eleve_id FROM {$t['arbitrages']} WHERE evaluation_id=%d",
            $eid, $eid, $eid
        ) ) ?: [] );
        $current = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['eleves']} WHERE classe_id=%d", (int) $eval['classe_id'] ) ) ?: [] );
        $ids = ( ( $eval['statut'] ?? '' ) === 'cloturee' && $with_data ) ? $with_data : array_values( array_unique( array_merge( $current, $with_data ) ) );
        if ( ! $ids ) return [];
        return $wpdb->get_results( "SELECT * FROM {$t['eleves']} WHERE id IN (" . implode( ',', $ids ) . ") ORDER BY nom, prenom", ARRAY_A ) ?: [];
    }

    /** Autre élève portant le même nom et prénom (ou le même n° d'élève) : chaque élève n'est que dans une classe. */
    public static function find_duplicate_student( string $prenom, string $nom, string $num, int $except_id = 0 ): ?array {
        global $wpdb;
        $t   = self::tables();
        $sql = "SELECT * FROM {$t['eleves']} WHERE id<>%d AND ((LOWER(TRIM(nom))=LOWER(TRIM(%s)) AND LOWER(TRIM(prenom))=LOWER(TRIM(%s)))";
        $args = [ $except_id, $nom, $prenom ];
        if ( trim( $num ) !== '' ) { $sql .= " OR TRIM(num_eleve)=TRIM(%s)"; $args[] = $num; }
        $row = $wpdb->get_row( $wpdb->prepare( $sql . ") LIMIT 1", ...$args ), ARRAY_A );
        return $row ?: null;
    }

    public static function log_student_move( int $eleve_id, int $from, int $to ): void {
        if ( $from === $to ) return;
        $all = get_option( 'logieval_student_moves', [] );
        $all = is_array( $all ) ? $all : [];
        $all[ $eleve_id ][] = [ 'from' => $from, 'to' => $to, 'at' => current_time( 'mysql' ), 'by' => get_current_user_id() ];
        update_option( 'logieval_student_moves', $all, false );
    }

    public static function get_rows( string $table, array $where = [], string $order = '' ): array {
        global $wpdb;
        $sql = "SELECT * FROM {$table}";
        if ( $where ) {
            $c=[]; $v=[];
            foreach ($where as $col=>$val) { $c[]="`$col`=%s"; $v[]=$val; }
            $sql .= ' WHERE '.implode(' AND ',$c);
            $sql = $wpdb->prepare($sql,...$v);
        }
        if ($order) {
            $parts = array_filter(array_map('trim', explode(',', $order)));
            $safe_parts = [];
            foreach ($parts as $part) {
                if (preg_match('/^([A-Za-z0-9_]+)(?:\s+(ASC|DESC))?$/i', $part, $m)) {
                    $safe_parts[] = '`' . esc_sql($m[1]) . '`' . (!empty($m[2]) ? ' ' . strtoupper($m[2]) : '');
                }
            }
            if ($safe_parts) {
                $sql .= ' ORDER BY ' . implode(', ', $safe_parts);
            }
        }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function get_row( string $table, array $where ): ?array {
        $r = self::get_rows($table,$where); return $r[0] ?? null;
    }

    public static function insert( string $table, array $data ): int {
        global $wpdb; $wpdb->insert($table,$data); return (int)$wpdb->insert_id;
    }

    public static function update( string $table, array $data, array $where ): void {
        global $wpdb; $wpdb->update($table,$data,$where);
    }

    public static function delete( string $table, array $where ): void {
        global $wpdb; $wpdb->delete($table,$where);
    }

    /**
     * Enseignants qui doivent encore terminer : seulement ceux qui existent encore et sont actifs
     * (un enseignant supprimé ou désactivé ne bloque plus la clôture).
     */
    public static function pending_teacher_count( int $eval_id ): int {
        global $wpdb;
        $t = self::tables();
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t['eval_enseignant']} ee INNER JOIN {$t['enseignants']} e ON e.id=ee.enseignant_id
             WHERE ee.evaluation_id=%d AND ee.statut='en_cours' AND e.actif=1",
            $eval_id
        ) );
    }

    /* ── Dynamic total max points ───────────────────────────────── */
    public static function get_total_max_points(): float {
        $t    = self::tables();
        $objs = self::get_rows( $t['objectifs'], ['actif'=>1] );
        $total = 0.0;
        foreach ( $objs as $obj ) {
            $scs = self::get_rows( $t['sous_criteres'], ['objectif_id'=>$obj['id']] );
            if ( $obj['type'] === 'radio' ) {
                $max = 0.0;
                foreach ( $scs as $sc ) $max = max($max,(float)$sc['points']);
                $total += $max;
            } else {
                foreach ( $scs as $sc ) $total += (float)$sc['points'];
            }
        }
        return $total;
    }
}
