<?php

if (!defined('ABSPATH')) {
    exit;
}

class GCA_Importer
{
    public function allowed_import_types(): array
    {
        return array('lagap', 'students', 'tracking', 'grades');
    }

    public function normalize_import_type(string $type): string
    {
        return in_array($type, $this->allowed_import_types(), true) ? $type : 'students';
    }

    public function field_definitions(string $type): array
    {
        $shared = array(
            'class_name' => array(
                'label' => 'Classe',
                'aliases' => array('classe', 'classe(s)', 'classes'),
                'required' => true,
            ),
            'last_name' => array(
                'label' => 'Nom',
                'aliases' => array('nom', 'nom de famille'),
                'required' => true,
            ),
            'first_name' => array(
                'label' => 'Prénom',
                'aliases' => array('prenom'),
                'required' => true,
            ),
            'lateness_count' => array(
                'label' => 'Nombre de retards',
                'aliases' => array('nb de retards annuel', 'nombre de retards', 'retards', 'retard'),
                'required' => false,
            ),
            'absence_count' => array(
                'label' => "Nombre de périodes d’absence",
                'aliases' => array("nb d'absences annuel", "nombre d'absences", 'absences', "nombre de periodes d'absence", "nombre de périodes d'absence", "periodes d'absence", "périodes d'absence"),
                'required' => false,
            ),
            'absence_rate' => array(
                'label' => "Taux d'absence (%)",
                'aliases' => array("taux d'absence annuel", "taux d'absence", 'pourcentage absence', '% absence'),
                'required' => false,
            ),
            'removal_count' => array(
                'label' => 'Nombre de mises à la porte',
                'aliases' => array('nb de mises a la porte annuel', 'nombre de mises a la porte', 'mises a la porte'),
                'required' => false,
            ),
            'comment_count' => array(
                'label' => 'Nombre de commentaires',
                'aliases' => array('nb de commentaires annuel', 'nombre de commentaires', 'commentaires', 'commentaire'),
                'required' => false,
            ),
        );

        if ($type === 'grades') {
            return array(
                'class_name' => array(
                    'label' => 'Classe',
                    'aliases' => array('classe', 'classe(s)', 'classes', 'numero de classe', 'numéro de classe', 'classe eleve', 'classe élève'),
                    'required' => true,
                ),
                'last_name' => array(
                    'label' => 'Nom',
                    'aliases' => array('nom', 'nom de famille', 'nom eleve', 'nom élève'),
                    'required' => true,
                ),
                'first_name' => array(
                    'label' => 'Prénom',
                    'aliases' => array('prenom', 'prénom', 'prenom eleve', 'prénom élève'),
                    'required' => true,
                ),
                'assessment' => array(
                    'label' => 'Évaluation',
                    'aliases' => array('evaluation', 'évaluation', 'epreuve', 'épreuve', 'test', 'travail', 'titre', 'intitule', 'intitulé'),
                    'required' => true,
                ),
                'grade_value' => array(
                    'label' => 'Note',
                    'aliases' => array('note', 'notes', 'resultat', 'résultat', 'moyenne', 'valeur'),
                    'required' => true,
                ),
            );
        }

        if ($type === 'tracking') {
            return $shared;
        }

        return array_merge(
            $shared,
            array(
                'date_of_birth' => array(
                    'label' => 'Date de naissance',
                    'aliases' => array('date de naissance', 'naissance'),
                    'required' => false,
                ),
                'gender' => array(
                    'label' => 'Genre',
                    'aliases' => array('genre', 'sexe', 'sexe officiel', 'civilite', 'civilité'),
                    'required' => false,
                ),
                'phone' => array(
                    'label' => 'Téléphone apprenti',
                    'aliases' => array('telephone apprenti', 'telephone', 'n telephone apprenti'),
                    'required' => false,
                ),
                'email' => array(
                    'label' => 'E-mail principal de l’élève',
                    'aliases' => array('email apprenti', 'e-mail apprenti', 'courriel apprenti', 'e-mail principal de l eleve', 'email principal de l eleve'),
                    'required' => false,
                ),
                'trainer_name' => array(
                    'label' => 'Formateur responsable',
                    'aliases' => array('formateur responsable', 'nom formateur responsable', 'responsable formation', 'responsable de formation'),
                    'required' => false,
                ),
                'trainer_company' => array(
                    'label' => 'Entreprise formateur',
                    'aliases' => array('entreprise formateur', 'entreprise formatrice'),
                    'required' => false,
                ),
                'trainer_company_address' => array(
                    'label' => 'Entreprise formatrice - adresse',
                    'aliases' => array('entreprise formatrice adresse', 'adresse entreprise formatrice', 'adresse entreprise'),
                    'required' => false,
                ),
                'trainer_company_zip' => array(
                    'label' => 'Entreprise formatrice - NPA',
                    'aliases' => array('entreprise formatrice npa', 'npa entreprise formatrice', 'npa entreprise'),
                    'required' => false,
                ),
                'trainer_company_city' => array(
                    'label' => 'Entreprise formatrice - ville',
                    'aliases' => array('entreprise formatrice ville', 'ville entreprise formatrice', 'localite entreprise formatrice', 'localité entreprise formatrice'),
                    'required' => false,
                ),
                'trainer_email' => array(
                    'label' => 'E-mail du responsable',
                    'aliases' => array('email formateur responsable', 'e-mail formateur responsable', 'courriel formateur responsable', 'entreprise formatrice email responsable', 'entreprise formatrice email'),
                    'required' => false,
                ),
                'trainer_phone' => array(
                    'label' => 'Téléphone formateur responsable',
                    'aliases' => array('n de telephone du formateur responsable', 'telephone formateur'),
                    'required' => false,
                ),
                'legal_rep1_name' => array(
                    'label' => 'Représentant légal 1 - nom',
                    'aliases' => array('nom representant legal', 'representant legal'),
                    'required' => false,
                ),
                'legal_rep1_email' => array(
                    'label' => 'Représentant légal 1 - e-mail',
                    'aliases' => array('email representant legal'),
                    'required' => false,
                ),
                'legal_rep1_phone' => array(
                    'label' => 'Représentant légal 1 - téléphone',
                    'aliases' => array('telephone representant legal'),
                    'required' => false,
                ),
                'legal_rep2_name' => array(
                    'label' => 'Représentant légal 2 - nom',
                    'aliases' => array('nom representant legal 2'),
                    'required' => false,
                ),
                'legal_rep2_email' => array(
                    'label' => 'Représentant légal 2 - e-mail',
                    'aliases' => array('email representant legal 2'),
                    'required' => false,
                ),
                'legal_rep2_phone' => array(
                    'label' => 'Représentant légal 2 - téléphone',
                    'aliases' => array('telephone representant legal 2'),
                    'required' => false,
                ),
            )
        );
    }

    public function infer_mapping(array $headers, string $type): array
    {
        $mapping = array();
        $fields = $this->field_definitions($type);
        $normalized_headers = array();

        foreach ($headers as $header) {
            $normalized_headers[$header] = $this->normalize_label((string) $header);
        }

        // Une colonne reconnue exactement pour un champ n'est pas reprise « à peu près » pour un autre
        // (ex. « Entreprise formatrice » ne doit pas remplir l'adresse, le NPA ou la ville de l'entreprise).
        $exact_owner = array();
        foreach ($fields as $field => $definition) {
            foreach ($normalized_headers as $header => $normalized_header) {
                foreach ($definition['aliases'] as $alias) {
                    if ($normalized_header === $this->normalize_label($alias) && !isset($exact_owner[$header])) {
                        $exact_owner[$header] = $field;
                    }
                }
            }
        }

        foreach ($fields as $field => $definition) {
            $best_header = '';
            $best_score = 0;

            foreach ($normalized_headers as $header => $normalized_header) {
                foreach ($definition['aliases'] as $alias) {
                    $normalized_alias = $this->normalize_label($alias);
                    $score = 0;

                    if ($normalized_header === $normalized_alias) {
                        $score = 100;
                    } elseif (str_contains($normalized_header, $normalized_alias)) {
                        $score = 70 + strlen($normalized_alias);
                    } elseif (str_contains($normalized_alias, $normalized_header) && strlen($normalized_header) > 3) {
                        $score = 40 + strlen($normalized_header);
                    }

                    if ($score < 100 && isset($exact_owner[$header]) && $exact_owner[$header] !== $field) {
                        $score = 0;
                    }

                    if ($score > $best_score) {
                        $best_score = $score;
                        $best_header = (string) $header;
                    }
                }
            }

            $mapping[$field] = $best_header;
        }

        return $mapping;
    }

    public function lagap_mapping(array $headers): array
    {
        $mapping = $this->infer_mapping($headers, 'lagap');
        $exact = array(
            'last_name' => array('Nom'),
            'first_name' => array('Prenom'),
            'absence_count' => array("Nb d'absences annuel", "Nombre de périodes d'absence"),
            'removal_count' => array('Nb de mises a la porte annuel'),
            'lateness_count' => array('Nb de retards annuel'),
            'comment_count' => array('Nb de commentaires annuel'),
            'absence_rate' => array("Taux d'absence annuel"),
            'class_name' => array('Classe(s)'),
            'email' => array('E-mail principal de l eleve', 'Email principal de l eleve'),
            'trainer_email' => array('Entreprise formatrice Email'),
            'trainer_company' => array('Entreprise formatrice'),
            'trainer_company_address' => array('Entreprise formatrice Adresse'),
            'trainer_company_zip' => array('Entreprise formatrice NPA'),
            'trainer_company_city' => array('Entreprise formatrice Ville'),
            'trainer_phone' => array('N de telephone du formateur responsable'),
            'trainer_name' => array('Formateur responsable'),
            'gender' => array('Sexe officiel', 'Sexe', 'Genre'),
        );

        foreach ($exact as $field => $candidates) {
            $header = $this->find_header($headers, $candidates);
            if ($header !== '') {
                $mapping[$field] = $header;
            }
        }

        if (
            !empty($mapping['trainer_email'])
            && !empty($mapping['trainer_name'])
            && $mapping['trainer_email'] === $mapping['trainer_name']
        ) {
            $mapping['trainer_email'] = '';
        }

        return $mapping;
    }

    public function is_lagap_export(array $headers): bool
    {
        $required = array('Nom', 'Prenom', 'Classe(s)');
        $signals = array('Nb de retards annuel', "Taux d'absence annuel", 'Entreprise formatrice Email');
        $required_count = 0;
        $signal_count = 0;

        foreach ($required as $candidate) {
            if ($this->find_header($headers, array($candidate)) !== '') {
                $required_count++;
            }
        }

        foreach ($signals as $candidate) {
            if ($this->find_header($headers, array($candidate)) !== '') {
                $signal_count++;
            }
        }

        return $required_count >= 3 && $signal_count >= 2;
    }

    public function read_file(string $path, int $limit = 0): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            return $this->read_xlsx($path, $limit);
        }

        return $this->read_csv($path, $limit);
    }

    public function process(string $path, string $type, string $mode, array $mapping, string $duplicate_mode, string $source_filename): array
    {
        global $wpdb;

        $rows = $this->read_file($path);
        if ($type === 'grades') {
            return $this->process_grades($path, $mode, $mapping, $duplicate_mode, $source_filename, $rows);
        }

        $settings = GCA_Database::get_settings();
        $classes_table = GCA_Database::table('classes');
        $students_table = GCA_Database::table('students');
        $imports_table = GCA_Database::table('imports');

        $processed = 0;
        $skipped = 0;
        $errors = array();
        $source_sha256 = is_readable($path) ? (string) hash_file('sha256', $path) : '';
        $source_size = is_readable($path) ? (int) filesize($path) : 0;
        $source_path = GCA_Database::private_relative_path($path);
        $teacher_id = GCA_Database::current_teacher_id();
        $taken_classes = array();

        foreach ($rows as $index => $row) {
            $line_number = $index + 2;
            $data = $this->mapped_row($row, $mapping);

            if (empty($data['class_name']) || empty($data['last_name']) || empty($data['first_name'])) {
                $errors[] = 'Ligne ' . $line_number . ': classe, nom ou prénom manquant.';
                continue;
            }

            $class_id = $this->ensure_class((string) $data['class_name'], (string) $settings['school_year']);
            if ($class_id <= 0) {
                // Classe déjà suivie par un autre maître de classe : ses lignes ne sont pas importées ici.
                $taken_key = (string) $data['class_name'];
                if (!isset($taken_classes[$taken_key])) {
                    $taken_classes[$taken_key] = true;
                    $errors[] = 'Classe « ' . sanitize_text_field($taken_key) . ' » : elle a déjà un maître de classe (un autre enseignant). Ses élèves ne sont pas importés chez vous.';
                }
                $skipped++;
                continue;
            }
            $student = $this->find_student($class_id, (string) $data['last_name'], (string) $data['first_name'], $data);

            if ($student && $duplicate_mode === 'ignore') {
                $skipped++;
                continue;
            }

            $student_data = $this->student_payload($data, $type, $mode, $student);
            $student_id = 0;

            if ($student) {
                $student_id = (int) $student->id;
                $student_data['updated_at'] = GCA_Database::now();
                $formats = $this->formats_for_payload($student_data);
                $wpdb->update($students_table, $student_data, array('id' => $student_id), $formats, array('%d'));
            } else {
                $student_data['class_id'] = $class_id;
                $student_data['last_name'] = sanitize_text_field((string) $data['last_name']);
                $student_data['first_name'] = sanitize_text_field((string) $data['first_name']);
                $student_data['created_at'] = GCA_Database::now();
                $student_data['updated_at'] = GCA_Database::now();
                $formats = $this->formats_for_payload($student_data);
                $wpdb->insert($students_table, $student_data, $formats);
                $student_id = (int) $wpdb->insert_id;
            }

            if ($student_id > 0) {
                $this->recalculate_tasks($student_id);
                $processed++;
            } else {
                $errors[] = 'Ligne ' . $line_number . ': impossible de sauvegarder la fiche.';
            }
        }

        $wpdb->insert(
            $imports_table,
            array(
                'import_type' => $type,
                'import_mode' => $mode,
                'source_filename' => sanitize_file_name($source_filename),
                'mapping_json' => wp_json_encode($mapping),
                'row_count' => count($rows),
                'processed_count' => $processed,
                'skipped_count' => $skipped,
                'error_count' => count($errors),
                'errors_json' => wp_json_encode(array_slice($errors, 0, 200)),
                'created_by' => get_current_user_id(),
                'teacher_id' => $teacher_id,
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => $source_path,
                'created_at' => GCA_Database::now(),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s')
        );
        $import_id = (int) $wpdb->insert_id;

        GCA_Database::log(
            'import_processed',
            'import',
            $import_id,
            array(
                'type' => $type,
                'mode' => $mode,
                'rows' => count($rows),
                'processed' => $processed,
                'skipped' => $skipped,
                'errors' => count($errors),
                'source_filename' => sanitize_file_name($source_filename),
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => $source_path,
                'mapping' => $mapping,
            )
        );
        GCA_Database::snapshot(
            'import_backup',
            'import',
            $import_id,
            array(
                'source_filename' => sanitize_file_name($source_filename),
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => $source_path,
                'mapping' => $mapping,
                'row_count' => count($rows),
                'processed_count' => $processed,
                'skipped_count' => $skipped,
                'error_count' => count($errors),
                'errors' => array_slice($errors, 0, 200),
            ),
            $teacher_id,
            get_current_user_id()
        );

        return array(
            'rows' => count($rows),
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
        );
    }

    private function process_grades(string $path, string $mode, array $mapping, string $duplicate_mode, string $source_filename, array $rows): array
    {
        global $wpdb;

        $settings = GCA_Database::get_settings();
        $grades_table = GCA_Database::table('grades');
        $imports_table = GCA_Database::table('imports');
        $processed = 0;
        $skipped = 0;
        $errors = array();
        $source_sha256 = is_readable($path) ? (string) hash_file('sha256', $path) : '';
        $source_size = is_readable($path) ? (int) filesize($path) : 0;
        $source_path = GCA_Database::private_relative_path($path);
        $teacher_id = GCA_Database::current_teacher_id();
        $source_filename = sanitize_file_name($source_filename);

        foreach ($rows as $index => $row) {
            $line_number = $index + 2;
            $data = $this->mapped_row($row, $mapping);

            $required_grade_values = array(
                (string) ($data['class_name'] ?? ''),
                (string) ($data['last_name'] ?? ''),
                (string) ($data['first_name'] ?? ''),
                (string) ($data['assessment'] ?? ''),
                (string) ($data['grade_value'] ?? ''),
            );
            if (array_filter($required_grade_values, array($this, 'has_import_value')) !== $required_grade_values) {
                $errors[] = 'Ligne ' . $line_number . ': classe, nom, prénom, évaluation ou note manquant.';
                continue;
            }

            $class = $this->find_existing_class((string) $data['class_name'], (string) $settings['school_year']);
            if (!$class) {
                $errors[] = 'Ligne ' . $line_number . ': classe introuvable dans le plugin (' . sanitize_text_field((string) $data['class_name']) . ').';
                continue;
            }

            $student = $this->find_student((int) $class->id, (string) $data['last_name'], (string) $data['first_name'], array());
            if (!$student) {
                $errors[] = 'Ligne ' . $line_number . ': aucun apprenti correspondant dans ' . (string) $class->name . ' pour ' . sanitize_text_field((string) $data['first_name']) . ' ' . sanitize_text_field((string) $data['last_name']) . '.';
                continue;
            }

            $assessment = sanitize_text_field((string) ($data['assessment'] ?? ''));
            $grade_value = sanitize_text_field((string) $data['grade_value']);
            $source_key = $this->grade_source_key((int) $student->id, (int) $class->id, $assessment);

            $existing_grade_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$grades_table} WHERE student_id = %d AND source_key = %s LIMIT 1",
                    (int) $student->id,
                    $source_key
                )
            );
            if (!$existing_grade_id) {
                $existing_grade_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$grades_table} WHERE student_id = %d AND LOWER(assessment) = LOWER(%s) LIMIT 1",
                        (int) $student->id,
                        $assessment
                    )
                );
            }

            if ($existing_grade_id && $duplicate_mode === 'ignore') {
                $skipped++;
                continue;
            }

            $payload = array(
                'student_id' => (int) $student->id,
                'class_id' => (int) $class->id,
                'school_year' => sanitize_text_field((string) $class->school_year),
                'subject' => '',
                'assessment' => $assessment,
                'grade_value' => $grade_value,
                'coefficient' => 1.0,
                'grade_date' => null,
                'comment' => '',
                'source_key' => $source_key,
                'source_filename' => $source_filename,
                'created_by' => get_current_user_id(),
                'teacher_id' => $teacher_id,
                'updated_at' => GCA_Database::now(),
            );

            if ($existing_grade_id) {
                $saved = $wpdb->update(
                    $grades_table,
                    $payload,
                    array('id' => (int) $existing_grade_id),
                    array('%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s'),
                    array('%d')
                );
            } else {
                $payload['created_at'] = GCA_Database::now();
                $saved = $wpdb->insert(
                    $grades_table,
                    $payload,
                    array('%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
                );
            }

            if ($saved === false) {
                $errors[] = 'Ligne ' . $line_number . ': impossible de sauvegarder la note.';
                continue;
            }

            $processed++;
        }

        $wpdb->insert(
            $imports_table,
            array(
                'import_type' => 'grades',
                'import_mode' => $mode,
                'source_filename' => $source_filename,
                'mapping_json' => wp_json_encode($mapping),
                'row_count' => count($rows),
                'processed_count' => $processed,
                'skipped_count' => $skipped,
                'error_count' => count($errors),
                'errors_json' => wp_json_encode(array_slice($errors, 0, 200)),
                'created_by' => get_current_user_id(),
                'teacher_id' => $teacher_id,
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => $source_path,
                'created_at' => GCA_Database::now(),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s')
        );
        $import_id = (int) $wpdb->insert_id;

        GCA_Database::log(
            'import_processed',
            'import',
            $import_id,
            array(
                'type' => 'grades',
                'mode' => $mode,
                'rows' => count($rows),
                'processed' => $processed,
                'skipped' => $skipped,
                'errors' => count($errors),
                'source_filename' => $source_filename,
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => $source_path,
                'mapping' => $mapping,
            )
        );
        GCA_Database::snapshot(
            'import_backup',
            'import',
            $import_id,
            array(
                'source_filename' => $source_filename,
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => $source_path,
                'mapping' => $mapping,
                'row_count' => count($rows),
                'processed_count' => $processed,
                'skipped_count' => $skipped,
                'error_count' => count($errors),
                'errors' => array_slice($errors, 0, 200),
            ),
            $teacher_id,
            get_current_user_id()
        );

        return array(
            'rows' => count($rows),
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
        );
    }

    private function find_existing_class(string $class_name, string $school_year): ?object
    {
        global $wpdb;

        $class_name = $this->clean_class_name($class_name);
        if ($class_name === '') {
            return null;
        }

        $table = GCA_Database::table('classes');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'active' AND school_year = %s ORDER BY name ASC",
                sanitize_text_field($school_year)
            )
        );

        if (!$rows) {
            $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'active' ORDER BY school_year DESC, name ASC");
        }

        $matches = array();
        foreach ($rows as $row) {
            if ($this->class_values_match((string) $row->name, $class_name)) {
                $matches[] = $row;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function class_values_match(string $stored, string $incoming): bool
    {
        $stored_clean = $this->clean_class_name($stored);
        $incoming_clean = $this->clean_class_name($incoming);

        if ($this->normalize_label($stored_clean) === $this->normalize_label($incoming_clean)) {
            return true;
        }

        $stored_code = $this->short_class_code($stored_clean);
        $incoming_code = $this->short_class_code($incoming_clean);

        return $stored_code !== '' && $incoming_code !== '' && $stored_code === $incoming_code;
    }

    private function clean_class_name(string $class_name): string
    {
        $class_name = sanitize_text_field(trim($class_name));
        $class_name = preg_replace('/\s*[-–]\s*\d{4}\s*[-–]\s*\d{4}\s*$/', '', $class_name);
        return trim((string) preg_replace('/\s+/', ' ', (string) $class_name));
    }

    private function short_class_code(string $class_name): string
    {
        if (preg_match('/C\d+[A-Z]*/i', $class_name, $matches)) {
            return strtolower((string) $matches[0]);
        }

        return '';
    }

    private function has_import_value($value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '-';
    }

    private function grade_source_key(int $student_id, int $class_id, string $assessment): string
    {
        $assessment_key = $this->normalize_label($assessment);

        return sha1(implode('|', array($student_id, $class_id, $assessment_key)));
    }

    public function recalculate_tasks(int $student_id): void
    {
        global $wpdb;

        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');

        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, c.name AS class_name FROM {$students} s INNER JOIN {$classes} c ON c.id = s.class_id WHERE s.id = %d",
                $student_id
            )
        );

        if (!$student || $student->archived_at) {
            return;
        }

        $settings = GCA_Database::get_settings();
        $t1 = max(1, (int) $settings['lateness_threshold_1']);
        $t2 = max($t1 + 1, (int) $settings['lateness_threshold_2']);
        $td = max($t2 + 1, (int) $settings['lateness_threshold_dean']);
        $lateness = (int) $student->lateness_count;

        if ($lateness >= $t1 && !$this->communication_exists($student_id, 'lateness_3')) {
            $this->create_task($student_id, 'lateness_3', 'lateness_3', (string) $t1, 'Préparer la 1ère convocation', 'Seuil de ' . $t1 . ' retards atteint.');
        }

        if ($lateness >= $t2 && $this->communication_exists($student_id, 'lateness_3') && !$this->communication_exists($student_id, 'lateness_6')) {
            $this->create_task($student_id, 'lateness_6', 'lateness_6', (string) $t2, 'Préparer la 2e convocation', 'Seuil de ' . $t2 . ' retards atteint.');
        }

        if (
            $lateness >= $td
            && $this->communication_exists($student_id, 'lateness_3')
            && $this->communication_exists($student_id, 'lateness_6')
            && !$this->communication_exists($student_id, 'dean_warning')
        ) {
            $this->create_task($student_id, 'dean_warning_' . $td, 'dean_warning', (string) $td, 'Notifier le Doyen', 'Retards répétés : notification au Doyen à préparer.');
        }

        if (
            $student->lateness_status === 'warning_confirmed'
            && (int) $student->warning_lateness_count > 0
            && $lateness > (int) $student->warning_lateness_count
        ) {
            $this->create_task(
                $student_id,
                'dean_exclusion_after_warning_' . $lateness,
                'dean_exclusion',
                (string) $lateness,
                'Notifier le Doyen',
                'Nouveau retard après avertissement confirmé.'
            );
        }

        if (
            $student->lateness_status === 'exclusion_confirmed'
            && (int) $student->exclusion_lateness_count > 0
            && $lateness > (int) $student->exclusion_lateness_count
        ) {
            $this->create_task(
                $student_id,
                'dean_exclusion_recurrence_' . $lateness,
                'dean_exclusion',
                (string) $lateness,
                'Notifier le Doyen',
                'Nouveau retard après exclusion temporaire.'
            );
        }

        $this->sync_absence_tasks($student_id, $student, $settings);

        $this->sync_contract_break_task($student_id, $student);

        $removal_count = (int) $student->removal_count;
        for ($removal_index = 1; $removal_index <= $removal_count; $removal_index++) {
            if ($this->communication_exists($student_id, 'removal_' . $removal_index)) {
                continue;
            }

            $this->create_task(
                $student_id,
                'removal_' . $removal_index,
                'removal',
                (string) $removal_index,
                'Rédiger un e-mail libre',
                'Mise à la porte n°' . $removal_index . ' à traiter.'
            );
        }
    }

    public function resolve_task(int $task_id): void
    {
        global $wpdb;

        $wpdb->update(
            GCA_Database::table('tasks'),
            array(
                'status' => 'resolved',
                'resolved_at' => GCA_Database::now(),
            ),
            array('id' => $task_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    public function recalculate_all_active_tasks(): void
    {
        global $wpdb;

        $students = GCA_Database::table('students');
        $ids = $wpdb->get_col("SELECT id FROM {$students} WHERE archived_at IS NULL ORDER BY id ASC");

        foreach ($ids as $id) {
            $this->recalculate_tasks((int) $id);
        }
    }

    private function sync_contract_break_task(int $student_id, object $student): void
    {
        $valid_keys = array();
        $break_date = $this->local_date((string) ($student->contract_break_date ?? ''));

        if ((string) ($student->contract_status ?? 'active') === 'broken' && $break_date) {
            $due_date = $this->contract_break_review_date($break_date);
            $today = new DateTimeImmutable(wp_date('Y-m-d'), wp_timezone());

            if ($today >= $due_date) {
                $task_key = 'contract_break_review_' . $break_date->format('Ymd');
                $valid_keys[] = $task_key;
                $this->create_task(
                    $student_id,
                    $task_key,
                    'contract_break_review',
                    $due_date->format('Y-m-d'),
                    'Faire le point rupture',
                    'Rupture de contrat du ' . $break_date->format('d.m.Y') . '. Le délai de suivi de 3 mois + 1 semaine est arrivé à échéance le ' . $due_date->format('d.m.Y') . '. Vérifier si une nouvelle entreprise formatrice est trouvée ou si l’apprenti doit arrêter de se présenter aux cours.'
                );
            }
        }

        $this->prune_pending_tasks($student_id, 'contract_break_review', $valid_keys);
    }

    private function sync_absence_tasks(int $student_id, object $student, array $settings): void
    {
        $this->prune_pending_tasks($student_id, 'absence', array());

        $count_threshold = max(1, (int) ($settings['absence_unjustified_days_threshold'] ?? 21));
        $rate_threshold = min(100, max(1, (float) str_replace(',', '.', (string) ($settings['absence_critical_rate_threshold'] ?? 30))));
        $absence_values = $this->absence_followup_values($student);
        $absence_count = $absence_values['effective_count'];
        $absence_rate = $absence_values['effective_rate'];
        $alert_allowed = $this->absence_alert_allowed($student, $settings);

        $valid_count_keys = array();
        if ($alert_allowed && $absence_count > $count_threshold) {
            $task_key = 'absence_unjustified_over_' . $count_threshold . '_count_' . $this->threshold_key($absence_count);
            $valid_count_keys[] = $task_key;
            $this->create_task(
                $student_id,
                $task_key,
                'absence_unjustified_21',
                (string) $count_threshold,
                'Préparer un message',
                'Absences non justifiées supérieures à ' . $count_threshold . ' jours/périodes. Nombre à traiter : ' . $this->format_decimal($absence_count) . '. Importé : ' . $this->format_decimal($absence_values['raw_count']) . '. Justifié dans le plugin : ' . $this->format_decimal($absence_values['justified_count']) . '.'
            );
        }
        $this->prune_pending_tasks($student_id, 'absence_unjustified_21', $valid_count_keys);

        $valid_rate_keys = array();
        if ($alert_allowed && $absence_rate >= $rate_threshold) {
            $task_key = 'absence_rate_' . $this->threshold_key($rate_threshold) . '_current_' . $this->threshold_key($absence_rate);
            $valid_rate_keys[] = $task_key;
            $this->create_task(
                $student_id,
                $task_key,
                'absence_rate_30',
                (string) $rate_threshold,
                'Préparer un message',
                "Taux d'absences non justifiées supérieur ou égal à " . $this->format_decimal($rate_threshold) . ' %. Taux à traiter : ' . $this->format_decimal($absence_rate) . ' %. Importé : ' . $this->format_decimal($absence_values['raw_rate']) . ' %. Justifié dans le plugin : ' . $this->format_decimal($absence_values['justified_count']) . ' période(s).'
            );
        }
        $this->prune_pending_tasks($student_id, 'absence_rate_30', $valid_rate_keys);
    }

    private function absence_followup_values(object $student): array
    {
        $raw_count = max(0.0, (float) ($student->absence_count ?? 0));
        $raw_rate = max(0.0, (float) ($student->absence_rate ?? 0));
        $justified_count = min($raw_count, max(0.0, (float) ($student->absence_justified_count ?? 0)));
        $effective_count = max(0.0, $raw_count - $justified_count);
        $effective_rate = $raw_count > 0 && $raw_rate > 0
            ? max(0.0, $raw_rate * ($effective_count / $raw_count))
            : 0.0;

        return array(
            'raw_count' => $raw_count,
            'raw_rate' => $raw_rate,
            'justified_count' => $justified_count,
            'effective_count' => $effective_count,
            'effective_rate' => $effective_rate,
        );
    }

    private function contract_break_review_date(DateTimeImmutable $break_date): DateTimeImmutable
    {
        return $break_date->modify('+3 months')->modify('+7 days');
    }

    private function absence_alert_allowed(object $student, array $settings): bool
    {
        $absence_count = $this->absence_followup_values($student)['effective_count'];
        $minimum_count = max(0, (float) ($settings['absence_min_count_before_alert'] ?? 2));
        if ($absence_count < $minimum_count) {
            return false;
        }

        $grace_days = min(120, max(0, (int) ($settings['absence_grace_days'] ?? 21)));
        $start_date = $this->local_date((string) ($settings['school_start_date'] ?? ''));
        if ($start_date && $grace_days > 0) {
            $first_alert_date = $start_date->modify('+' . $grace_days . ' days');
            $today = new DateTimeImmutable(wp_date('Y-m-d'), wp_timezone());
            if ($today < $first_alert_date) {
                return false;
            }
        }

        return true;
    }

    private function prune_pending_tasks(int $student_id, string $task_type, array $valid_task_keys): void
    {
        global $wpdb;

        $tasks = GCA_Database::table('tasks');
        $valid_task_keys = array_values(array_unique(array_map('strval', $valid_task_keys)));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, task_key
                 FROM {$tasks}
                 WHERE student_id = %d
                   AND task_type = %s
                   AND status = 'pending'",
                $student_id,
                $task_type
            )
        );

        foreach ($rows as $row) {
            if (in_array((string) $row->task_key, $valid_task_keys, true)) {
                continue;
            }

            $wpdb->delete($tasks, array('id' => (int) $row->id), array('%d'));
        }
    }

    public function create_task(int $student_id, string $task_key, string $task_type, string $threshold, string $label, string $message): void
    {
        global $wpdb;

        $table = GCA_Database::table('tasks');
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE student_id = %d AND task_key = %s",
                $student_id,
                $task_key
            )
        );

        if ($existing) {
            return;
        }

        $wpdb->insert(
            $table,
            array(
                'student_id' => $student_id,
                'task_key' => $task_key,
                'task_type' => $task_type,
                'threshold_value' => $threshold,
                'status' => 'pending',
                'action_label' => $label,
                'message' => $message,
                'created_at' => GCA_Database::now(),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    public function communication_exists(int $student_id, string $type): bool
    {
        global $wpdb;

        $table = GCA_Database::table('communications');

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE student_id = %d AND communication_type = %s AND status = 'sent' LIMIT 1",
                $student_id,
                $type
            )
        );
    }

    public function absence_thresholds(): array
    {
        $settings = GCA_Database::get_settings();
        $raw = (string) $settings['absence_thresholds'];

        if (trim($raw) === '') {
            return array();
        }

        $parts = preg_split('/[,;\s]+/', $raw);
        $thresholds = array();

        foreach ($parts as $part) {
            $value = (float) str_replace(',', '.', trim($part));
            if ($value > 0) {
                $thresholds[] = $value;
            }
        }

        $thresholds = array_values(array_unique($thresholds));
        sort($thresholds);

        return $thresholds;
    }

    private function local_date(string $value): ?DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (array('Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y') as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value, wp_timezone());
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }

    public function threshold_key(float $threshold): string
    {
        return str_replace('.', '_', rtrim(rtrim((string) $threshold, '0'), '.'));
    }

    private function read_csv(string $path, int $limit = 0): array
    {
        $delimiter = $this->detect_delimiter($path);
        $handle = fopen($path, 'rb');

        if (!$handle) {
            return array();
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return array();
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $rows = array();

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = array();

            foreach ($headers as $index => $header) {
                $row[(string) $header] = $values[$index] ?? '';
            }

            $rows[] = $row;

            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }

        fclose($handle);
        return $rows;
    }

    private function detect_delimiter(string $path): string
    {
        $line = '';
        $handle = fopen($path, 'rb');

        if ($handle) {
            $line = (string) fgets($handle);
            fclose($handle);
        }

        $best = ',';
        $best_count = 0;

        foreach (array(',', ';', "\t") as $delimiter) {
            $count = count(str_getcsv($line, $delimiter));
            if ($count > $best_count) {
                $best = $delimiter;
                $best_count = $count;
            }
        }

        return $best;
    }

    private function read_xlsx(string $path, int $limit = 0): array
    {
        if (!class_exists('ZipArchive')) {
            return array();
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return array();
        }

        $shared_strings = $this->xlsx_shared_strings($zip);
        $sheet_path = $this->xlsx_first_sheet_path($zip);

        if ($sheet_path === '') {
            $zip->close();
            return array();
        }

        $sheet_xml = $zip->getFromName($sheet_path);
        $zip->close();

        if (!$sheet_xml) {
            return array();
        }

        $xml = simplexml_load_string($sheet_xml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return array();
        }

        $raw_rows = array();

        foreach ($xml->sheetData->row as $row) {
            $cells = array();

            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $index = $this->column_index_from_ref($ref);
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 's') {
                    $shared_index = (int) $cell->v;
                    $value = $shared_strings[$shared_index] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                } elseif (isset($cell->v)) {
                    $value = (string) $cell->v;
                }

                $cells[$index] = $value;
            }

            if ($cells) {
                ksort($cells);
                $raw_rows[] = $cells;
            }
        }

        if (!$raw_rows) {
            return array();
        }

        $headers = array_values($raw_rows[0]);
        $rows = array();

        foreach (array_slice($raw_rows, 1) as $raw) {
            $row = array();
            foreach ($headers as $index => $header) {
                $row[(string) $header] = $raw[$index] ?? '';
            }
            $rows[] = $row;

            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function xlsx_shared_strings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (!$xml) {
            return array();
        }

        $strings = array();
        $tree = simplexml_load_string($xml);

        if (!$tree) {
            return array();
        }

        foreach ($tree->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }

            $text = '';
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    if (isset($run->t)) {
                        $text .= (string) $run->t;
                    }
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function xlsx_first_sheet_path(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (!$workbook || !$rels) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook_xml = simplexml_load_string($workbook);
        $rels_xml = simplexml_load_string($rels);

        if (!$workbook_xml || !$rels_xml || !isset($workbook_xml->sheets->sheet[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheet = $workbook_xml->sheets->sheet[0];
        $attrs = $sheet->attributes('r', true);
        $relationship_id = isset($attrs['id']) ? (string) $attrs['id'] : '';

        if ($relationship_id === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        foreach ($rels_xml->Relationship as $rel) {
            if ((string) $rel['Id'] === $relationship_id) {
                $target = ltrim((string) $rel['Target'], '/');
                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function column_index_from_ref(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private function find_header(array $headers, array $candidates): string
    {
        $normalized_headers = array();

        foreach ($headers as $header) {
            $normalized_headers[$this->normalize_label((string) $header)] = (string) $header;
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize_label((string) $candidate);
            if (isset($normalized_headers[$normalized])) {
                return $normalized_headers[$normalized];
            }
        }

        return '';
    }

    private function mapped_row(array $row, array $mapping): array
    {
        $data = array();

        foreach ($mapping as $field => $header) {
            if ($header === '' || $header === '__ignore') {
                continue;
            }

            $data[$field] = isset($row[$header]) ? trim((string) $row[$header]) : '';
        }

        foreach (array('trainer_name' => 'trainer_phone') as $name_field => $phone_field) {
            if (empty($data[$name_field])) {
                continue;
            }

            $parsed = $this->parse_trainer((string) $data[$name_field]);
            $data[$name_field] = $parsed['name'];

            if (empty($data[$phone_field]) && $parsed['phone'] !== '') {
                $data[$phone_field] = $parsed['phone'];
            }
        }

        return $data;
    }

    private function student_payload(array $data, string $type, string $mode, ?object $existing): array
    {
        $payload = array();
        $locked_fields = $this->manual_fields_lookup($existing);
        $text_fields = array(
            'date_of_birth',
            'gender',
            'phone',
            'email',
            'trainer_name',
            'trainer_company',
            'trainer_company_address',
            'trainer_company_zip',
            'trainer_company_city',
            'trainer_email',
            'trainer_phone',
            'legal_rep1_name',
            'legal_rep1_email',
            'legal_rep1_phone',
            'legal_rep2_name',
            'legal_rep2_email',
            'legal_rep2_phone',
        );

        foreach ($text_fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($existing && isset($locked_fields[$field])) {
                continue;
            }

            $value = trim((string) $data[$field]);

            if ($field === 'date_of_birth') {
                $date = $this->parse_date($value);
                if ($date !== '') {
                    $payload[$field] = $date;
                }
                continue;
            }

            if ($field === 'gender') {
                $value = $this->normalize_gender($value);
            }

            if ($field === 'email' || $field === 'trainer_email' || str_ends_with($field, '_email')) {
                $value = sanitize_email($value);
            } else {
                $value = sanitize_text_field($value);
            }

            if ($existing && ($value === '' || $value === '-')) {
                continue;
            }

            $payload[$field] = $value === '-' ? '' : $value;
        }

        if ($existing && empty($locked_fields['trainer_id'])) {
            foreach (array('trainer_name', 'trainer_company', 'trainer_company_address', 'trainer_company_zip', 'trainer_company_city', 'trainer_email', 'trainer_phone') as $trainer_field) {
                if (array_key_exists($trainer_field, $payload) && trim((string) ($existing->{$trainer_field} ?? '')) !== trim((string) $payload[$trainer_field])) {
                    $payload['trainer_id'] = 0;
                    break;
                }
            }
        }

        $count_fields = array('absence_count', 'absence_rate', 'lateness_count', 'removal_count', 'comment_count');
        foreach ($count_fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'absence_rate' || $field === 'absence_count') {
                $incoming = $this->clean_decimal($data[$field]);
            } else {
                $incoming = $this->clean_integer($data[$field]);
            }

            if ($mode === 'add' && $existing) {
                $payload[$field] = (float) ($existing->{$field} ?? 0) + $incoming;
            } else {
                $payload[$field] = $incoming;
            }
        }

        if (!$existing) {
            foreach ($count_fields as $field) {
                if (!isset($payload[$field])) {
                    $payload[$field] = 0;
                }
            }
            if (empty($payload['gender'])) {
                $payload['gender'] = $this->infer_gender_from_first_name((string) ($data['first_name'] ?? ''));
            }
            $payload['lateness_status'] = 'none';
        } elseif (empty($existing->gender) && empty($payload['gender']) && empty($locked_fields['gender'])) {
            $payload['gender'] = $this->infer_gender_from_first_name((string) ($data['first_name'] ?? $existing->first_name ?? ''));
        }

        return $payload;
    }

    private function manual_fields_lookup(?object $existing): array
    {
        if (!$existing) {
            return array();
        }

        return array_fill_keys(GCA_Database::manual_fields_from_json($existing->manual_fields_json ?? ''), true);
    }

    private function formats_for_payload(array $payload): array
    {
        $formats = array();

        foreach ($payload as $field => $_value) {
            if ($field === 'absence_rate' || $field === 'absence_count') {
                $formats[] = '%f';
            } elseif (str_ends_with($field, '_id') || str_ends_with($field, '_count') || $field === 'lateness_count' || $field === 'removal_count' || $field === 'comment_count') {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    private function ensure_class(string $name, string $school_year): int
    {
        global $wpdb;

        $table = GCA_Database::table('classes');
        $name = sanitize_text_field($name);
        $school_year = sanitize_text_field($school_year);

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE name = %s AND school_year = %s LIMIT 1",
                $name,
                $school_year
            )
        );

        if ($id) {
            return (int) $id;
        }

        // Une classe n'a qu'un maître de classe : pas de création si un autre enseignant l'a déjà.
        if (class_exists('LogiPilot_Spaces') && LogiPilot_Spaces::classe_master_elsewhere($name, $school_year) > 0) {
            return 0;
        }

        $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'school_year' => $school_year,
                'status' => 'active',
                'created_at' => GCA_Database::now(),
                'updated_at' => GCA_Database::now(),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    private function find_student(int $class_id, string $last_name, string $first_name, array $data = array()): ?object
    {
        global $wpdb;

        $table = GCA_Database::table('students');

        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE archived_at IS NULL AND class_id = %d AND LOWER(last_name) = LOWER(%s) AND LOWER(first_name) = LOWER(%s) LIMIT 1",
                $class_id,
                sanitize_text_field($last_name),
                sanitize_text_field($first_name)
            )
        );

        if ($student) {
            return $student;
        }

        $email = sanitize_email((string) ($data['email'] ?? ''));
        if (!is_email($email)) {
            return null;
        }

        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE archived_at IS NULL AND LOWER(email) = LOWER(%s) ORDER BY id ASC LIMIT 2",
                $email
            )
        );

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function parse_date(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return '';
        }

        if (preg_match('/^\d{5}(?:[.,]\d+)?$/', $value)) {
            $serial = (int) floor((float) str_replace(',', '.', $value));
            if ($serial > 20000 && $serial < 80000) {
                $base = DateTimeImmutable::createFromFormat('!Y-m-d', '1899-12-30');
                if ($base instanceof DateTimeImmutable) {
                    return $base->modify('+' . $serial . ' days')->format('Y-m-d');
                }
            }
        }

        $formats = array('Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y', 'm/d/Y');
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        return '';
    }

    private function clean_integer($value): int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return 0;
        }

        return max(0, (int) preg_replace('/[^\d-]/', '', $value));
    }

    private function clean_decimal($value): float
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $value = str_replace(array('%', ' '), '', $value);
        $value = str_replace(',', '.', $value);

        return max(0.0, (float) $value);
    }

    private function format_decimal($value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function parse_trainer(string $value): array
    {
        $value = trim($value);
        $phone = '';

        if (preg_match('/\(([^)]+)\)\s*$/', $value, $matches)) {
            $phone = sanitize_text_field($matches[1]);
            $value = trim(preg_replace('/\([^)]+\)\s*$/', '', $value));
        }

        if ($value === '-') {
            $value = '';
        }

        return array(
            'name' => sanitize_text_field($value),
            'phone' => $phone,
        );
    }

    private function normalize_gender(string $value): string
    {
        $normalized = strtolower(trim(remove_accents($value)));

        if (in_array($normalized, array('m', 'masculin', 'homme', 'male', 'garcon', 'garçon'), true)) {
            return 'male';
        }

        if (in_array($normalized, array('f', 'feminin', 'féminin', 'femme', 'female', 'fille'), true)) {
            return 'female';
        }

        return '';
    }

    private function infer_gender_from_first_name(string $first_name): string
    {
        $parts = preg_split('/\s+/', trim($first_name));
        $first = strtolower(trim(remove_accents((string) ($parts[0] ?? ''))));
        $female = array('adriana', 'amel', 'ebrar', 'ema', 'emilia', 'elodie', 'gilda', 'jasmin', 'mila', 'narges', 'svetlana');
        $male = array('adam', 'adrian', 'adriano', 'ahmad', 'albion', 'benny', 'brayan', 'burak', 'carlos', 'cristiano', 'damien', 'david', 'devrim', 'diogo', 'dorio', 'drilon', 'edi', 'eliott', 'elvis', 'evan', 'fabio', 'francisco', 'gabin', 'jonathan', 'kelvy', 'kingston', 'kris', 'lenny', 'louca', 'loukas', 'lucas', 'mateo', 'max', 'nathan', 'nicolas', 'noah', 'nolan', 'ossama', 'pedro', 'sacha', 'santiago', 'sofiane', 'sulayman', 'terry', 'theo', 'tharmisan', 'tiago');

        if (in_array($first, $female, true)) {
            return 'female';
        }

        if (in_array($first, $male, true)) {
            return 'male';
        }

        if ($first !== '' && str_ends_with($first, 'a')) {
            return 'female';
        }

        return '';
    }

    private function normalize_label(string $label): string
    {
        $label = trim(wp_strip_all_tags($label));
        if (function_exists('remove_accents')) {
            $label = remove_accents($label);
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
            if ($converted !== false) {
                $label = $converted;
            }
        }

        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);
        return trim(preg_replace('/\s+/', ' ', $label));
    }
}
