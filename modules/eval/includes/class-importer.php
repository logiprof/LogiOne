<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LOGIEVAL_Importer {

    /**
     * Main entry point.
     * @param string $type     'eleves' or 'enseignants'
     * @param string $tmp_path Temp file path
     * @param string $filename Original filename (to detect extension)
     * @return array Results array
     */
    public static function process( string $type, string $tmp_path, string $filename ): array {
        $ext = strtolower( pathinfo($filename, PATHINFO_EXTENSION) );
        $valid = self::validate_uploaded_file($tmp_path, $filename);
        if (isset($valid['error'])) return $valid;

        if ( $ext === 'csv' ) {
            $rows = self::parse_csv( $tmp_path );
        } elseif ( in_array($ext, ['xlsx','xls']) ) {
            $rows = self::parse_excel( $tmp_path, $ext );
        } else {
            return ['error' => 'Format non supporté. Utilisez .xlsx, .xls ou .csv'];
        }

        if ( isset($rows['error']) ) return $rows;
        if ( empty($rows) ) return ['error' => 'Le fichier est vide ou ne contient pas de données.'];

        // 1.53.8 : mauvais fichier ou mauvais type d'import → un message clair, rien n'est importé.
        $keys = array_keys((array) reset($rows));
        $looks_ce = isset($keys[0]) && $keys[0] === 'classe' && (bool) preg_grep('/^(maitre|enseignant)/', $keys);
        if ( $type === 'eleves' && ( ! in_array('nom', $keys, true) || ! in_array('prenom', $keys, true) ) ) {
            return ['error' => $looks_ce
                ? 'Ce fichier est un fichier « Classes ↔ enseignants » (classe, maître de classe, enseignants). Choisissez le type d’import « 🏫 Classes ↔ enseignants » en haut, puis relancez. Rien n’a été importé.'
                : 'Ce fichier n’a pas les colonnes « nom » et « prenom » sur sa première ligne (colonnes trouvées : ' . implode(', ', array_slice($keys, 0, 6)) . '). Vérifiez que c’est bien la liste des élèves et que la feuille des élèves est la première du classeur. Rien n’a été importé.'];
        }
        if ( $type === 'enseignants' && ( ! in_array('nom', $keys, true) || ! in_array('email', $keys, true) ) ) {
            return ['error' => $looks_ce
                ? 'Ce fichier est un fichier « Classes ↔ enseignants ». Choisissez le type d’import « 🏫 Classes ↔ enseignants » en haut, puis relancez. Rien n’a été importé.'
                : 'Ce fichier n’a pas les colonnes « nom », « prenom » et « email » sur sa première ligne. Rien n’a été importé.'];
        }
        if ( $type === 'classes_enseignants' && in_array('nom', $keys, true) && in_array('prenom', $keys, true) ) {
            return ['error' => 'Ce fichier ressemble à une liste d’élèves (colonnes nom, prénom). Pour l’import « Classes ↔ enseignants », la colonne 1 est la classe et la colonne 2 le maître de classe. Rien n’a été importé.'];
        }

        $skip_errors     = ! empty($_POST['skip_errors']);
        $update_existing = ! empty($_POST['update_existing']);

        if ( $type === 'eleves' ) {
            $create_classes = ! empty($_POST['create_classes']);
            $class_year     = sanitize_text_field( wp_unslash( $_POST['class_year'] ?? '' ) );
            return self::import_eleves( $rows, $skip_errors, $update_existing, $create_classes, $class_year );
        } elseif ( $type === 'enseignants' ) {
            return self::import_enseignants( $rows, $skip_errors, $update_existing );
        } elseif ( $type === 'classes_enseignants' ) {
            return self::import_classes_enseignants( $rows );
        }

        return ['error' => 'Type d\'import inconnu.'];
    }

    /* ── CSV parser ─────────────────────────────────────────────── */
    private static function parse_csv( string $path ): array {
        $content = file_get_contents($path);
        if ($content === false) return ['error' => 'Impossible d\'ouvrir le fichier CSV.'];
        // BOM UTF-8
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) $content = substr($content, 3);
        // Excel sous Windows enregistre souvent en Windows-1252 : on convertit en UTF-8
        // (avant : « Zoé » devenait illisible et les en-têtes accentués n'étaient pas reconnus).
        if (!preg_match('//u', $content)) {
            $conv = function_exists('mb_convert_encoding') ? @mb_convert_encoding($content, 'UTF-8', 'Windows-1252') : false;
            if ($conv === false || $conv === '') $conv = function_exists('iconv') ? @iconv('Windows-1252', 'UTF-8//TRANSLIT', $content) : false;
            if ($conv !== false && $conv !== '') $content = $conv;
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Séparateur : celui qui apparaît le plus dans la ligne d'en-têtes (hors guillemets)
        $first_line = strtok($content, "\n");
        $unquoted   = preg_replace('/"[^"]*"/', '', (string)$first_line);
        $delimiters = [';', ',', "\t", '|'];
        $counts     = array_map(fn($d) => substr_count($unquoted, $d), $delimiters);
        $delimiter  = $delimiters[array_search(max($counts), $counts)];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows    = [];
        $headers = null;
        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line = array_map(fn($v) => trim((string)$v), $line);
            if (empty(array_filter($line, fn($v) => $v !== ''))) continue; // skip empty lines

            if ($headers === null) {
                $headers = self::normalize_headers($line);
                continue;
            }
            $row = [];
            foreach ($headers as $i => $h) {
                if ($h === '') continue;
                $row[$h] = $line[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /** En-têtes tolérants : « Prénom », « E-mail », « N° élève »… → clés internes. */
    private static function normalize_headers( array $line ): array {
        $aliases = [
            'prenom' => 'prenom', 'first name' => 'prenom', 'firstname' => 'prenom',
            'nom' => 'nom', 'nom de famille' => 'nom', 'last name' => 'nom', 'lastname' => 'nom', 'name' => 'nom',
            'classe' => 'classe', 'class' => 'classe',
            'email' => 'email', 'e mail' => 'email', 'mail' => 'email', 'courriel' => 'email', 'adresse e mail' => 'email', 'adresse email' => 'email',
            'num eleve' => 'num_eleve', 'numero eleve' => 'num_eleve', 'no eleve' => 'num_eleve', 'n eleve' => 'num_eleve',
            'n° eleve' => 'num_eleve', 'numero' => 'num_eleve', 'num' => 'num_eleve', 'matricule' => 'num_eleve',
        ];
        $out = [];
        foreach ($line as $h) {
            $n = self::normalize_str((string)$h);
            $n = trim(str_replace(['°', 'º'], ' ', $n));
            $n = preg_replace('/\s+/', ' ', $n);
            $key = $aliases[$n] ?? str_replace(' ', '_', $n);
            if ($key === '') $key = 'col_' . (count($out) + 1);
            // 1.53.5 : deux colonnes au même titre (« Enseignant », « Enseignant »…) ne s'écrasent plus.
            $base = $key; $k = 2;
            while (in_array($key, $out, true)) { $key = $base . '_' . $k++; }
            $out[] = $key;
        }
        return $out;
    }

    /* ── Excel parser (uses PhpSpreadsheet if available, else basic XML) ── */
    private static function parse_excel( string $path, string $ext ): array {
        // Try PhpSpreadsheet first (available if composer installed)
        $autoload = LOGIEVAL_PATH . 'vendor/autoload.php';
        if ( file_exists($autoload) ) {
            require_once $autoload;
            try {
                $reader = $ext === 'xlsx'
                    ? new \PhpOffice\PhpSpreadsheet\Reader\Xlsx()
                    : new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($path);
                $sheet       = $spreadsheet->getActiveSheet();
                $data        = $sheet->toArray(null, true, true, false);

                if (empty($data)) return ['error' => 'Feuille Excel vide.'];

                // First non-empty row = headers
                $headers = null;
                $rows    = [];
                foreach ($data as $line) {
                    $line = array_map(fn($v) => trim((string)$v), $line);
                    if (empty(array_filter($line))) continue;
                    if ($headers === null) {
                        $headers = self::normalize_headers($line);
                        continue;
                    }
                    $row = [];
                    foreach ($headers as $i => $h) {
                        $row[$h] = $line[$i] ?? '';
                    }
                    $rows[] = $row;
                }
                return $rows;
            } catch (\Exception $e) {
                error_log('LogiEval Excel import error: ' . $e->getMessage());
                return ['error' => 'Erreur de lecture Excel. Vérifiez le fichier et réessayez.'];
            }
        }

        // Fallback: parse xlsx as ZIP/XML (no dependency)
        if ($ext === 'xlsx') {
            return self::parse_xlsx_native($path);
        }

        return ['error' => 'Les fichiers .xls (ancien format Excel) ne sont pas pris en charge. Dans Excel : Fichier › Enregistrer sous › « Classeur Excel (.xlsx) » ou « CSV », puis importez ce fichier.'];
    }

    /* ── Native XLSX parser (ZIP + XML, no dependency) ────────────── */
    private static function parse_xlsx_native( string $path ): array {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return ['error' => 'Impossible d\'ouvrir le fichier XLSX.'];

        // Read shared strings
        $shared = [];
        $ss_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss_xml) {
            $ss = self::safe_simplexml_load($ss_xml);
            if (!$ss) { $zip->close(); return ['error' => 'Fichier XLSX invalide.']; }
            foreach ($ss->si as $si) {
                $text = '';
                foreach ($si->r ?? [$si] as $r) {
                    $text .= (string)($r->t ?? $r);
                }
                $shared[] = $text;
            }
        }

        // Read first sheet (sheet1.xml, sinon la première feuille trouvée)
        $sheet_index = $zip->locateName('xl/worksheets/sheet1.xml');
        if ($sheet_index === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (preg_match('#^xl/worksheets/[^/]+\.xml$#', (string)$zip->getNameIndex($i))) { $sheet_index = $i; break; }
            }
        }
        if ($sheet_index === false) { $zip->close(); return ['error' => 'Feuille introuvable dans le fichier XLSX.']; }

        $sheet_xml = $zip->getFromIndex($sheet_index);
        $zip->close();

        $sheet = self::safe_simplexml_load($sheet_xml);
        if (!$sheet) return ['error' => 'Fichier XLSX invalide.'];
        $data  = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $line = [];
            $pos  = 0;
            foreach ($row->c as $cell) {
                // Position réelle de la cellule (« C5 » → colonne 2) : une cellule vide
                // au milieu ne décale plus les colonnes suivantes.
                $ref = (string)$cell['r'];
                if ($ref !== '' && preg_match('/^([A-Z]+)/', $ref, $m)) {
                    $col = 0;
                    foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
                    $pos = $col - 1;
                }
                $type  = (string)$cell['t'];
                $value = (string)($cell->v ?? '');
                if ($type === 's') $value = $shared[(int)$value] ?? '';
                elseif ($type === 'inlineStr') {
                    $value = '';
                    foreach ($cell->is->r ?? [] as $r) $value .= (string)$r->t;
                    if ($value === '') $value = (string)($cell->is->t ?? '');
                }
                elseif ($type === 'b') $value = $value ? 'TRUE' : 'FALSE';
                $line[$pos] = trim($value);
                $pos++;
            }
            if ($line) {
                $max = max(array_keys($line));
                $full = [];
                for ($i = 0; $i <= $max; $i++) $full[] = $line[$i] ?? '';
                $line = $full;
            }
            if (!empty(array_filter($line, fn($v) => $v !== ''))) $data[] = $line;
        }

        if (empty($data)) return ['error' => 'Aucune donnée trouvée dans la feuille Excel.'];

        $headers = self::normalize_headers(array_shift($data));
        $rows    = [];
        foreach ($data as $line) {
            $row = [];
            foreach ($headers as $i => $h) { $row[$h] = $line[$i] ?? ''; }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function validate_uploaded_file( string $tmp_path, string $filename ): array {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed_ext = ['csv', 'xlsx', 'xls'];
        if (!in_array($ext, $allowed_ext, true)) {
            return ['error' => 'Format non supporté. Utilisez .xlsx, .xls ou .csv'];
        }

        if (!is_readable($tmp_path) || filesize($tmp_path) <= 0) {
            return ['error' => 'Fichier vide ou illisible.'];
        }

        $allowed_mimes = [
            'csv'  => ['text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        ];

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $tmp_path);
                finfo_close($finfo);
            }
        }
        if ($mime && !in_array($mime, $allowed_mimes[$ext], true)) {
            return ['error' => 'Le contenu du fichier ne correspond pas à son extension. Réenregistrez-le depuis Excel au format .xlsx ou .csv.'];
        }

        if ($ext === 'xlsx') {
            $zip = new ZipArchive();
            if ($zip->open($tmp_path) !== true) {
                return ['error' => 'Fichier XLSX invalide.'];
            }
            $has_workbook = $zip->locateName('xl/workbook.xml') !== false;
            $has_sheet = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (strpos((string)$zip->getNameIndex($i), 'xl/worksheets/') === 0) { $has_sheet = true; break; }
            }
            $zip->close();
            if (!$has_workbook || !$has_sheet) {
                return ['error' => 'Structure XLSX invalide.'];
            }
        }

        return ['ok' => true];
    }

    private static function safe_simplexml_load( string $xml ) {
        $previous = null;
        if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            $previous = libxml_disable_entity_loader(true);
        }
        $old_use_errors = libxml_use_internal_errors(true);
        $loaded = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($old_use_errors);
        if ($previous !== null && function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader($previous);
        }
        return $loaded;
    }

    /* ── String normalizer ─────────────────────────────────────────── */
    private static function normalize_str( string $s ): string {
        $s = mb_strtolower(trim($s));
        // Replace accented chars
        $from = ['à','â','ä','é','è','ê','ë','î','ï','ô','ù','û','ü','œ','æ','ç','À','Â','Ä','É','È','Ê','Ë','Î','Ï','Ô','Ù','Û','Ü','Œ','Æ','Ç'];
        $to   = ['a','a','a','e','e','e','e','i','i','o','u','u','u','oe','ae','c','a','a','a','e','e','e','e','i','i','o','u','u','u','oe','ae','c'];
        $s = str_replace($from, $to, $s);
        // Collapse multiple spaces/dashes to single space
        $s = preg_replace('/[\s\-_\.]+/', ' ', $s);
        return trim($s);
    }

    /* ── Import élèves ───────────────────────────────────────────── */
    /** Année scolaire en cours (« 2026-2027 » dès août). */
    public static function current_school_year(): string {
        $y = (int) current_time('Y'); $m = (int) current_time('n');
        $start = $m >= 8 ? $y : $y - 1;
        return $start . '-' . ($start + 1);
    }

    private static function guess_niveau( string $classe ): string {
        if ( preg_match('/(?<![a-z])(?:cr|c|a)(\d)/i', $classe, $m) ) {
            $n = (int) $m[1];
            return $n . ( 1 === $n ? 'ère' : 'ème' ) . ' année';
        }
        return '';
    }

    /**
     * Classe correspondant à un nom, pour une année scolaire donnée.
     * Priorité : même nom + même année, puis même nom sans année. Une classe du même nom mais d'une AUTRE année
     * n'est jamais utilisée ($other_year reçoit alors cette année, pour le message).
     */
    private static function resolve_class( array $classes, string $name, string $year, string &$other_year = '' ): int {
        $key = preg_replace('/[^a-z0-9]/', '', self::normalize_str($name));
        $no_year = 0; $other_year = '';
        foreach ($classes as $cl) {
            if (preg_replace('/[^a-z0-9]/', '', self::normalize_str((string) $cl['nom'])) !== $key) continue;
            $y = trim((string) ($cl['annee_scolaire'] ?? ''));
            if ($y === $year) return (int) $cl['id'];
            if ($y === '') { $no_year = $no_year ?: (int) $cl['id']; }
            else { $other_year = $y; }
        }
        if ($no_year) { $other_year = ''; }
        return $no_year;
    }

    private static function import_eleves( array $rows, bool $skip, bool $update, bool $create_classes = false, string $class_year = '' ): array {
        $t         = LOGIEVAL_Database::tables();
        $classes   = LOGIEVAL_Database::get_rows($t['classes'], [], 'nom');
        // 1.53.3 : les classes archivées (passage d'année) ne reçoivent jamais d'élèves importés.
        if ( class_exists('LOGIEVAL_Rollover') ) $classes = LOGIEVAL_Rollover::without_archived($classes);
        if ( $class_year === '' || ! preg_match('/^\d{4}-\d{4}$/', $class_year) ) $class_year = self::current_school_year();
        $required = ['nom','prenom','classe'];
        $result   = ['total'=>0,'imported'=>0,'updated'=>0,'skipped'=>0,'errors'=>[],'preview'=>[]];

        foreach ($rows as $line_num => $row) {
            $result['total']++;
            $line_label = $line_num + 2; // +2 because header is row 1

            // Normalize keys (handle accents / spaces)
            // (clés déjà normalisées par normalize_headers)

            // Check required
            $missing = [];
            foreach ($required as $req) {
                if (!isset($row[$req]) || trim($row[$req]) === '') $missing[] = $req;
            }
            if ($missing) {
                if (!$skip) return ['error' => "Ligne {$line_label} : champs manquants : ".implode(', ',$missing)];
                $result['errors'][] = ['line'=>$line_label,'data'=>array_values($row),'reason'=>'Champs manquants : '.implode(', ',$missing)];
                continue;
            }

            $nom       = sanitize_text_field(trim($row['nom']));
            $prenom    = sanitize_text_field(trim($row['prenom']));
            $num_eleve = sanitize_text_field(trim($row['num_eleve'] ?? ''));
            $classe_raw = trim($row['classe']);
            $classe_nm  = self::normalize_str($classe_raw);

            // 1.53.6 : même nom ET même année scolaire. Une « C1e 2025-2026 » n'est jamais remplie avec les élèves de 2026-2027.
            $other_year = '';
            $classe_id  = self::resolve_class($classes, $classe_raw, $class_year, $other_year) ?: null;

            if (!$classe_id && $create_classes) {
                $classe_id = (int) LOGIEVAL_Database::insert($t['classes'], [
                    'nom'            => sanitize_text_field($classe_raw),
                    'niveau'         => self::guess_niveau($classe_raw),
                    'annee_scolaire' => $class_year,
                ]);
                if ($classe_id) {
                    $classes[] = ['id'=>$classe_id,'nom'=>sanitize_text_field($classe_raw),'annee_scolaire'=>$class_year];
                    $result['classes_created'][] = $classe_raw;
                }
            }

            if (!$classe_id) {
                // Build helpful suggestion
                $available = implode(', ', array_map(function($cl) { return '"'.$cl['nom'].'"'; }, $classes));
                $reason = $other_year !== ''
                    ? "La classe \"$classe_raw\" n'existe que pour l'année $other_year (pas pour $class_year) : cochez « créer automatiquement les classes manquantes », ou faites d'abord le passage d'année."
                    : "Classe introuvable : \"$classe_raw\". Classes disponibles : $available";
                if (!$skip) return ['error' => "Ligne {$line_label} : $reason"];
                $result['errors'][] = ['line'=>$line_label,'data'=>array_values($row),'reason'=>$reason];
                continue;
            }

            // Check duplicate
            global $wpdb;
            $same = LOGIEVAL_Database::find_duplicate_student($prenom, $nom, $num_eleve, 0, (int) $classe_id);
            $existing_id = $same ? (int) $same['id'] : null;
            // 1.53.1 : déjà dans une AUTRE classe → jamais de doublon ; avec « mettre à jour », l'élève change de classe.
            if (!$existing_id) {
                $other = LOGIEVAL_Database::find_duplicate_student($prenom, $nom, $num_eleve);
                if ($other) {
                    if ($update) {
                        LOGIEVAL_Database::log_student_move((int) $other['id'], (int) $other['classe_id'], (int) $classe_id);
                        LOGIEVAL_Database::update($t['eleves'], ['classe_id' => $classe_id, 'num_eleve' => $num_eleve !== '' ? $num_eleve : $other['num_eleve']], ['id' => (int) $other['id']]);
                        $result['updated']++;
                        $result['preview'][] = ['nom'=>$nom,'prenom'=>$prenom,'num_eleve'=>$num_eleve,'classe'=>$row['classe'],'_status'=>'updated'];
                    } else {
                        $result['skipped']++;
                        $result['errors'][] = ['line'=>$line_label,'data'=>array_values($row),'reason'=>'Élève déjà dans une autre classe (cochez « mettre à jour » pour le changer de classe).'];
                    }
                    continue;
                }
            }

            if ($existing_id) {
                if ($update) {
                    if ($num_eleve !== '') LOGIEVAL_Database::update($t['eleves'], ['num_eleve'=>$num_eleve], ['id'=>$existing_id]);
                    $result['updated']++;
                    $result['preview'][] = ['nom'=>$nom,'prenom'=>$prenom,'num_eleve'=>$num_eleve,'classe'=>$row['classe'],'_status'=>'updated'];
                } else {
                    $result['skipped']++;
                }
                continue;
            }

            LOGIEVAL_Database::insert($t['eleves'], [
                'classe_id' => $classe_id,
                'nom'       => $nom,
                'prenom'    => $prenom,
                'num_eleve' => $num_eleve,
            ]);
            $result['imported']++;
            $result['preview'][] = ['nom'=>$nom,'prenom'=>$prenom,'num_eleve'=>$num_eleve,'classe'=>$row['classe'],'_status'=>'new'];
        }
        return $result;
    }

    /* ── Import enseignants ──────────────────────────────────────── */
    private static function import_enseignants( array $rows, bool $skip, bool $update ): array {
        $t      = LOGIEVAL_Database::tables();
        $result = ['total'=>0,'imported'=>0,'updated'=>0,'skipped'=>0,'errors'=>[],'preview'=>[]];

        foreach ($rows as $line_num => $row ) {
            $result['total']++;
            $line_label = $line_num + 2;

            // (clés déjà normalisées par normalize_headers)

            // Required fields
            $nom    = sanitize_text_field(trim($row['nom']    ?? ''));
            $prenom = sanitize_text_field(trim($row['prenom'] ?? ''));
            $email  = sanitize_email(trim($row['email']       ?? ''));

            if (!$nom || !$prenom) {
                if (!$skip) return ['error' => "Ligne {$line_label} : nom et prénom obligatoires."];
                $result['errors'][] = ['line'=>$line_label,'data'=>array_values($row),'reason'=>'Nom ou prénom manquant'];
                continue;
            }
            if (!$email || !is_email($email)) {
                if (!$skip) return ['error' => "Ligne {$line_label} : e-mail invalide : \"{$row['email']}\""];
                $result['errors'][] = ['line'=>$line_label,'data'=>array_values($row),'reason'=>"E-mail invalide : \"{$row['email']}\""];
                continue;
            }

            // Check duplicate by email
            global $wpdb;
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id, actif FROM {$t['enseignants']} WHERE email=%s", $email
            ), ARRAY_A);

            if ($existing) {
                if ($update) {
                    LOGIEVAL_Database::update($t['enseignants'],
                        ['nom'=>$nom,'prenom'=>$prenom],
                        ['id'=>$existing['id']]
                    );
                    $result['updated']++;
                    $result['preview'][] = ['nom'=>$nom,'prenom'=>$prenom,'email'=>$email,'_status'=>'updated'];
                } else {
                    $result['skipped']++;
                }
                continue;
            }

            $ident = LOGIEVAL_Auth::generate_identifier();
            LOGIEVAL_Database::insert($t['enseignants'], [
                'nom'                => $nom,
                'prenom'             => $prenom,
                'email'              => $email,
                'identifiant_unique' => $ident,
                'actif'              => 0,
            ]);
            $result['imported']++;
            $result['preview'][] = ['nom'=>$nom,'prenom'=>$prenom,'email'=>$email,'identifiant'=>$ident,'_status'=>'new_inactive'];
        }
        return $result;
    }

    /* ── Import classes ↔ enseignants (1.53.5) ─────────────────────
     * Colonne 1 = classe, colonne 2 = maître de classe, colonnes suivantes = autres enseignants.
     * Lecture par position : les titres des colonnes n'ont pas d'importance.
     * Enseignant connu (nom+prénom dans n'importe quel ordre, ou e-mail) → simplement lié à la classe.
     * Enseignant inconnu → compte créé INACTIF, avec un e-mail provisoire à compléter avant activation.
     * Rien n'est jamais retiré : les liens déjà présents sont gardés. */
    private static function name_key( string $s ): string {
        $tok = preg_split('/[\s,]+/', self::normalize_str(str_replace('-', ' ', $s)), -1, PREG_SPLIT_NO_EMPTY);
        sort($tok);
        return implode(' ', $tok);
    }

    /** « NOM Prénom », « Prénom NOM », « Nom, Prénom » ou « Prénom Nom ». */
    public static function split_name( string $full ): array {
        $full = trim(preg_replace('/\s+/u', ' ', $full));
        if (strpos($full, ',') !== false) {
            [$nom, $prenom] = array_map('trim', explode(',', $full, 2));
            return [$prenom, $nom];
        }
        $tok = explode(' ', $full);
        if (count($tok) < 2) return ['', $full];
        $upper = []; $other = [];
        foreach ($tok as $w) {
            $letters = preg_replace('/[^\p{L}]/u', '', $w);
            if (mb_strlen($letters) >= 2 && $letters === mb_strtoupper($letters)) $upper[] = $w; else $other[] = $w;
        }
        if ($upper && $other) {
            $nom = implode(' ', array_map(static fn($w) => mb_convert_case(mb_strtolower($w), MB_CASE_TITLE), $upper));
            return [implode(' ', $other), $nom];
        }
        return [array_shift($tok), implode(' ', $tok)];
    }

    private static function import_classes_enseignants( array $rows ): array {
        global $wpdb;
        $t       = LOGIEVAL_Database::tables();
        $result  = ['total'=>0,'imported'=>0,'updated'=>0,'skipped'=>0,'errors'=>[],'preview'=>[],'teachers_created'=>[]];
        $classes = LOGIEVAL_Database::get_rows($t['classes'], [], 'nom');
        if (class_exists('LOGIEVAL_Rollover')) $classes = LOGIEVAL_Rollover::without_archived($classes);
        $year = self::current_school_year();

        $by_name = []; $by_mail = [];
        foreach (LOGIEVAL_Database::get_rows($t['enseignants']) as $e) {
            $by_name[self::name_key($e['prenom'].' '.$e['nom'])][] = (int) $e['id'];
            $by_mail[strtolower(trim($e['email']))] = (int) $e['id'];
        }

        foreach ($rows as $line_num => $row) {
            $vals  = array_values(array_map(static fn($v) => trim((string) $v), $row));
            $label = $line_num + 2;
            if (!array_filter($vals, static fn($v) => $v !== '')) continue;
            $result['total']++;
            $classe = $vals[0] ?? '';
            $oy     = '';
            $cid    = self::resolve_class($classes, $classe, $year, $oy);
            if (!$cid) {
                $result['errors'][] = ['line'=>$label,'data'=>$vals,'reason'=> $oy !== ''
                    ? "La classe \"$classe\" n'existe que pour l'année $oy, pas pour $year : importez d'abord les élèves de $year (ou faites le passage d'année)."
                    : "Classe introuvable : \"$classe\" (importez d'abord les élèves pour créer les classes)."];
                continue;
            }
            $links = [];
            foreach (array_slice($vals, 1) as $pos => $cell) {
                if ($cell === '') continue;
                $is_maitre = ($pos === 0);
                $eid = 0;
                if (strpos($cell, '@') !== false && is_email($cell)) {
                    $eid = $by_mail[strtolower($cell)] ?? 0;
                    if (!$eid) { $result['errors'][] = ['line'=>$label,'data'=>$vals,'reason'=>"Aucun enseignant avec l'e-mail $cell : indiquez plutôt son nom et prénom pour le créer."]; continue; }
                } else {
                    $ids = $by_name[self::name_key($cell)] ?? [];
                    if (count($ids) > 1) { $result['errors'][] = ['line'=>$label,'data'=>$vals,'reason'=>"Plusieurs enseignants s'appellent « $cell » : indiquez son e-mail à la place."]; continue; }
                    if ($ids) {
                        $eid = $ids[0];
                    } else {
                        [$prenom, $nom] = self::split_name($cell);
                        $ident = LOGIEVAL_Auth::generate_identifier();
                        $eid = LOGIEVAL_Database::insert($t['enseignants'], [
                            'prenom' => sanitize_text_field($prenom), 'nom' => sanitize_text_field($nom),
                            'email'  => LOGIEVAL_Database::placeholder_email($ident),
                            'identifiant_unique' => $ident, 'actif' => 0,
                        ]);
                        if (!$eid) { $result['errors'][] = ['line'=>$label,'data'=>$vals,'reason'=>"Création impossible pour « $cell »."]; continue; }
                        $by_name[self::name_key($cell)] = [$eid];
                        $result['teachers_created'][] = trim("$prenom $nom");
                    }
                }
                $links[] = [$eid, $is_maitre, $cell];
            }
            foreach ($links as [$eid, $is_maitre, $cell]) {
                $ex = LOGIEVAL_Database::get_row($t['classe_enseignant'], ['classe_id'=>$cid,'enseignant_id'=>$eid]);
                if ($is_maitre) $wpdb->query($wpdb->prepare("UPDATE {$t['classe_enseignant']} SET est_maitre=0 WHERE classe_id=%d AND enseignant_id<>%d", $cid, $eid));
                if ($ex) {
                    if ($is_maitre && !(int) $ex['est_maitre']) {
                        LOGIEVAL_Database::update($t['classe_enseignant'], ['est_maitre'=>1], ['id'=>(int) $ex['id']]);
                        $result['updated']++; $st = 'maitre';
                    } else { $result['skipped']++; $st = 'deja'; }
                } else {
                    LOGIEVAL_Database::insert($t['classe_enseignant'], ['classe_id'=>$cid,'enseignant_id'=>$eid,'branche_id'=>null,'est_maitre'=>$is_maitre ? 1 : 0]);
                    $result['imported']++; $st = 'new';
                }
                $result['preview'][] = ['classe'=>$classe,'enseignant'=>$cell,'rôle'=>$is_maitre ? 'Maître de classe' : 'Enseignant','_status'=>$st];
            }
        }
        $result['teachers_created'] = array_values(array_unique($result['teachers_created']));
        return $result;
    }
}
