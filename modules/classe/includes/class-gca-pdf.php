<?php

if (!defined('ABSPATH')) {
    exit;
}

class GCA_PDF
{
    public static function create_convocation_pdf(object $student, object $class, string $type, array $meta = array()): string
    {
        GCA_Database::ensure_private_dirs();

        $student_dir = GCA_Database::private_dir('pdfs/student-' . (int) $student->id);

        if (!is_dir($student_dir)) {
            wp_mkdir_p($student_dir);
        }

        $safe_name = self::convocation_pdf_filename($student, $class, $type, $meta);
        $path = self::unique_file_path($student_dir, $safe_name);

        if ($type === 'lateness') {
            self::write_retention_form_pdf($path, $student, $class, $meta);
            return $path;
        }

        $title = $type === 'absence' ? 'Convocation - absences injustifiées' : 'Convocation aux retenues';
        $lines = array(
            'Apprenti: ' . trim($student->first_name . ' ' . $student->last_name),
            'Classe: ' . $class->name,
            'Date: ' . wp_date('d.m.Y'),
            'Motif: ' . ($type === 'absence' ? 'Absences injustifiées' : 'Retards répétés'),
            'Nombre de retards: ' . (int) $student->lateness_count,
            "Nombre de périodes d'absence: " . self::format_decimal($student->absence_count ?? 0),
            "Taux d'absence: " . self::format_decimal($student->absence_rate) . '%',
        );

        if (!empty($meta['convocation_date'])) {
            $lines[] = 'Date de convocation: ' . sanitize_text_field($meta['convocation_date']);
        }

        $lines[] = '';
        $lines[] = 'Ce document est généré automatiquement par le plugin de gestion des classes.';
        $lines[] = 'Le modèle graphique officiel pourra être intégré ultérieurement sans modifier les données.';

        self::write_simple_pdf($path, $title, $lines);

        return $path;
    }

    public static function create_student_report_pdf(object $student, object $class, array $history = array(), array $pending_tasks = array(), array $meta = array()): string
    {
        GCA_Database::ensure_private_dirs();

        $student_dir = GCA_Database::private_dir('reports/student-' . (int) $student->id);

        if (!is_dir($student_dir)) {
            wp_mkdir_p($student_dir);
        }

        $safe_name = sanitize_file_name($student->last_name . '-' . $student->first_name . '-rapport-' . wp_date('Ymd-His') . '.pdf');
        $path = $student_dir . '/' . $safe_name;
        $full_name = self::clean_text(trim((string) $student->first_name . ' ' . (string) $student->last_name));
        $class_name = self::clean_text((string) $class->name);
        $photo_path = (string) ($meta['photo_path'] ?? '');
        $gender_label = (string) ($meta['gender_label'] ?? 'Non renseigné');
        $minor_label = (string) ($meta['minor_label'] ?? 'Non renseigné');
        $status_label = (string) ($meta['status_label'] ?? '');
        $contract_status_label = (string) ($meta['contract_status_label'] ?? 'Contrat actif');
        $pages = array();
        $stream = "q\n";
        $images = array();
        $y = 790;

        $photo = self::jpeg_image('StudentPhoto', $photo_path);
        if ($photo) {
            $images[] = $photo;
            $stream .= self::image('StudentPhoto', 455, 700, 72, 72);
        }

        $line = static function (string $text, int $size = 10, bool $bold = false, array $rgb = array(0, 0, 0)) use (&$stream, &$pages, &$images, &$y): void {
            $wrapped = self::wrap_line($text, $size >= 13 ? 70 : 92);
            foreach ($wrapped as $part) {
                if ($y < 62) {
                    $stream .= "Q\n";
                    $pages[] = array('stream' => $stream, 'images' => $images);
                    $stream = "q\n";
                    $images = array();
                    $y = 790;
                }
                $stream .= self::text_line($part, 50, $y, $size, $bold, $rgb);
                $y -= $size + 6;
            }
        };

        $gap = static function (int $height = 10) use (&$y): void {
            $y -= $height;
        };

        $section = static function (string $title) use (&$stream, &$y, $line, $gap): void {
            $gap(8);
            $line($title, 13, true, array(0.08, 0.16, 0.28));
            $stream .= "0.2 w 50 " . self::number($y + 8) . " m 545 " . self::number($y + 8) . " l S\n";
            $gap(4);
        };

        $row = static function (string $label, $value) use ($line): void {
            $text = $label . ' : ' . trim((string) $value);
            $line($text, 10, false);
        };

        $line('Rapport de suivi', 19, true, array(0.08, 0.16, 0.28));
        $line($full_name !== '' ? $full_name : 'Apprenti', 15, true);
        $row('Classe', $class_name);
        $row('Date du rapport', wp_date('d.m.Y'));
        $row('Genre', $gender_label);
        $row('Majorité', $minor_label);
        $row('Statut disciplinaire', $status_label);
        $row('Contrat de formation', $contract_status_label);
        if ((string) ($student->contract_status ?? 'active') === 'broken') {
            $row('Date de rupture', $meta['contract_break_date'] ?? '');
            $row('Suivi école jusqu’au', $meta['contract_follow_until'] ?? '');
            $row('Alerte de contrôle', $meta['contract_review_date'] ?? '');
        }

        $disciplinary_rows = array();
        if ((int) ($student->disciplinary_warning_received ?? 0) === 1) {
            $disciplinary_rows[] = array('Avertissement reçu', self::format_date($student->disciplinary_warning_date ?? ''));
        }
        $disciplinary_exclusions = isset($meta['disciplinary_exclusions']) && is_array($meta['disciplinary_exclusions'])
            ? $meta['disciplinary_exclusions']
            : array();
        if (!$disciplinary_exclusions && (int) ($student->expulsion_received ?? 0) === 1) {
            $disciplinary_rows[] = array('Expulsion reçue', self::format_date($student->expulsion_date ?? ''));
        }
        if ((int) ($student->wellbeing_council_convoked ?? 0) === 1) {
            $disciplinary_rows[] = array('Conseil de savoir-être', self::format_date($student->wellbeing_council_date ?? ''));
        }

        if ($disciplinary_rows || $disciplinary_exclusions) {
            $section('Décisions disciplinaires');
            foreach ($disciplinary_rows as $disciplinary_row) {
                $row($disciplinary_row[0], $disciplinary_row[1] !== '' ? $disciplinary_row[1] : 'Oui');
            }
            foreach ($disciplinary_exclusions as $exclusion) {
                $period = trim((string) ($exclusion['period'] ?? ''));
                $duration = trim((string) ($exclusion['duration'] ?? ''));
                $notes = trim((string) ($exclusion['notes'] ?? ''));
                $value = $period !== '' ? $period : 'Oui';
                if ($duration !== '' && $duration !== '-') {
                    $value .= ' (' . $duration . ')';
                }
                if ($notes !== '') {
                    $value .= ' - ' . $notes;
                }
                $row('Exclusion', $value);
            }
        }

        $section('Coordonnées');
        $row('E-mail principal de l’élève', $student->email ?? '');
        $row('Téléphone apprenti', $student->phone ?? '');
        $without_employer = in_array((string) ($student->contract_status ?? 'active'), array('broken', 'free_candidate'), true);
        if ($without_employer) {
            $row('Employeur actif', 'Non');
        } else {
            $row('Formateur responsable', $student->trainer_name ?? '');
            $row('Entreprise formatrice', $student->trainer_company ?? '');
            $company_address = trim((string) ($student->trainer_company_address ?? ''));
            $company_locality = trim((string) ($student->trainer_company_zip ?? '') . ' ' . (string) ($student->trainer_company_city ?? ''));
            $row('Adresse entreprise', trim(implode(', ', array_filter(array($company_address, $company_locality)))));
            $row('E-mail responsable', $student->trainer_email ?? '');
            $row('Téléphone responsable', $student->trainer_phone ?? '');
        }
        $row('Représentant légal 1', trim((string) ($student->legal_rep1_name ?? '') . ' ' . (string) ($student->legal_rep1_email ?? '')));
        $row('Représentant légal 2', trim((string) ($student->legal_rep2_name ?? '') . ' ' . (string) ($student->legal_rep2_email ?? '')));

        $section('Indicateurs actuels');
        $row('Retards', (int) ($student->lateness_count ?? 0));
        $row("Périodes d'absence", self::format_decimal($student->absence_count ?? 0));
        $row("Taux d'absence", self::format_decimal($student->absence_rate ?? 0) . ' %');
        $row('Mises à la porte', (int) ($student->removal_count ?? 0));

        $grades = isset($meta['grades']) && is_array($meta['grades']) ? $meta['grades'] : array();
        if ($grades) {
            $section('Notes récupérées');
            foreach ($grades as $grade) {
                $assessment = trim((string) ($grade['assessment'] ?? ''));
                $value = trim((string) ($grade['grade_value'] ?? ''));
                $line('- ' . trim(($assessment !== '' ? $assessment . ' : ' : '') . $value), 10);
            }
        }

        $section('Situations à traiter');
        if (!$pending_tasks) {
            $line('Aucune situation en attente.', 10);
        } else {
            foreach ($pending_tasks as $task) {
                $line('- ' . (string) ($task['label'] ?? '') . ' : ' . (string) ($task['message'] ?? ''), 10);
            }
        }

        $section('Actions déjà entreprises');
        if (!$history) {
            $line('Aucune action enregistrée.', 10);
        } else {
            foreach ($history as $item) {
                $date = trim((string) ($item['date'] ?? ''));
                $label = trim((string) ($item['label'] ?? 'Action'));
                $summary = trim((string) ($item['summary'] ?? ''));
                $meta_line = trim((string) ($item['meta'] ?? ''));
                $line(($date !== '' ? $date . ' - ' : '') . $label, 10, true);
                if ($summary !== '') {
                    $line($summary, 10);
                }
                if ($meta_line !== '') {
                    $line($meta_line, 9, false, array(0.35, 0.4, 0.48));
                }
                $gap(4);
            }
        }

        if (!empty($student->admin_notes)) {
            $section('Notes internes');
            $line((string) $student->admin_notes, 10);
        }

        $stream .= "Q\n";
        $pages[] = array('stream' => $stream, 'images' => $images);

        self::write_pdf_pages($path, $pages);

        return $path;
    }

    private static function write_retention_form_pdf(string $path, object $student, object $class, array $meta): void
    {
        $full_name = self::clean_text((string) $student->last_name . ' ' . (string) $student->first_name);
        $class_name = self::clean_text((string) $class->name);
        $convocation_date = self::clean_text((string) ($meta['convocation_date'] ?? ''));
        $threshold = isset($student->threshold_value) ? (int) $student->threshold_value : (int) $student->lateness_count;
        $motive_count = $threshold > 0 ? $threshold : (int) ($student->lateness_count ?? 0);
        $motive = $motive_count > 0 ? 'Convocation pour ' . $motive_count . ' arrivées tardives' : 'Convocation pour arrivées tardives répétées';
        $remark = '';

        if ($threshold >= 6) {
            $first_convocation_date = self::clean_text((string) ($meta['first_convocation_date'] ?? ''));
            $remark = 'Une première convocation a déjà été enregistrée';
            if ($first_convocation_date !== '' && $first_convocation_date !== 'non renseignée') {
                $remark .= ' en date du ' . $first_convocation_date;
            }
            $remark .= ' pour 3 arrivées tardives.';
        }

        $stream = "q\n";
        $logo = self::jpeg_image('Im1', GCA_DIR . 'assets/templates/cpnv-logo.jpeg');

        if ($logo) {
            $stream .= self::image('Im1', 71, 757, 104, 52);
        } else {
            $stream .= self::text_line('cpnv', 71, 786, 26, true);
        }

        $stream .= self::text_line('Retenue', 245, 784, 32, true, array(0.07, 0.09, 0.12));
        $stream .= self::text_fit('Rue Dr César-Roux 2 - 1005 Lausanne', 384, 786, 165, 9, false);
        $stream .= self::text_fit('Tél. 021 316 11 11 ou 021 316 01 28', 384, 771, 165, 9, false);
        $stream .= self::text_fit('www.cpnv.ch', 384, 756, 165, 9, false);
        $stream .= self::text_line('Lundi, mardi, mercredi et jeudi - CR12', 199, 733, 13, false);
        $stream .= self::text_line('Filière de logistique', 71, 735, 10, false, array(0, 0.55, 0.22));
        $stream .= "0 0.55 0.22 RG 1 w 71 718 m 524 718 l S\n";

        $stream .= self::text_line('À remplir par l’enseignant-e : CFS', 71, 690, 12, true);
        $stream .= self::text_line('Nom, prénom :', 71, 660, 12, false);
        $stream .= self::text_fit($full_name, 158, 660, 185, 12, false);
        $stream .= self::text_line('Classe :', 363, 660, 12, false);
        $stream .= self::text_fit($class_name, 415, 660, 112, 12, false);

        $stream .= self::text_line('Convoqué le :', 71, 610, 12, false);
        $stream .= self::text_line($convocation_date, 158, 610, 12, false);
        $stream .= self::text_line('Horaire : 16h45-17h35', 71, 581, 12, false);
        $stream .= self::text_line('Motifs :', 71, 552, 12, false);
        $stream .= self::text_fit($motive, 124, 552, 300, 12, false);

        $stream .= self::text_line('Remarque :', 71, 500, 12, true);
        if ($remark !== '') {
            $stream .= self::text_block($remark, 155, 500, 360, 10, false, 14);
        }

        $stream .= self::text_line('À remplir par l’enseignant-e des appuis', 71, 420, 12, true);
        $stream .= self::checkbox_line('Élève présent', 71, 390);
        $stream .= self::checkbox_line('Élève absent', 71, 361);
        $stream .= self::text_line('Remarque éventuelle :', 71, 331, 12, false);

        $stream .= self::text_line('Merci de bien vouloir retourner ce formulaire complété au doyen', 71, 218, 12, true);
        $stream .= self::text_line('Meilleures salutations', 71, 155, 12, false);
        $stream .= "Q\n";

        self::write_pdf($path, $stream, $logo ? array($logo) : array());
    }

    private static function convocation_pdf_filename(object $student, object $class, string $type, array $meta): string
    {
        $last_name = self::filename_part((string) ($student->last_name ?? ''));
        $first_name = self::filename_part((string) ($student->first_name ?? ''));
        $full_name = trim($last_name . ' ' . $first_name);
        $class_code = self::class_code_for_filename((string) ($class->name ?? ''));

        if ($type === 'lateness') {
            $convocation_date = self::filename_part((string) ($meta['convocation_date'] ?? ''));
            if ($convocation_date === '') {
                $convocation_date = wp_date('d.m.Y');
            }
            $convocation_date = str_replace('.', '-', $convocation_date);

            $base = implode(
                ' - ',
                array_filter(array($class_code, $full_name, 'Retenue du ' . $convocation_date))
            );
        } else {
            $base = implode(
                ' - ',
                array_filter(array($class_code, $full_name, 'Convocation ' . $type . ' ' . wp_date('Ymd-His')))
            );
        }

        $filename = sanitize_file_name($base . '.pdf');

        return $filename !== '' ? $filename : sanitize_file_name('convocation-' . wp_date('Ymd-His') . '.pdf');
    }

    private static function filename_part(string $value): string
    {
        $value = self::clean_text($value);
        $value = str_replace(array('/', '\\'), '-', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value, " \t\n\r\0\x0B.-");
    }

    private static function class_code_for_filename(string $class_name): string
    {
        $class_name = self::filename_part($class_name);

        if (preg_match('/^(.+?)\s+-\s+\d{4}-\d{4}$/', $class_name, $matches)) {
            return trim((string) $matches[1]);
        }

        return $class_name;
    }

    private static function unique_file_path(string $directory, string $filename): string
    {
        $path = $directory . '/' . $filename;
        if (!file_exists($path)) {
            return $path;
        }

        $info = pathinfo($filename);
        $base = (string) ($info['filename'] ?? 'document');
        $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';

        for ($index = 2; $index < 1000; $index++) {
            $candidate = $directory . '/' . $base . '-' . $index . $extension;
            if (!file_exists($candidate)) {
                return $candidate;
            }
        }

        return $directory . '/' . $base . '-' . wp_date('Ymd-His') . $extension;
    }

    private static function checkbox_line(string $label, int $x, int $baseline): string
    {
        return self::rectangle($x, $baseline - 1, 10, 10)
            . self::text_line($label, $x + 17, $baseline, 12, false);
    }

    private static function format_decimal($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private static function format_date($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $timestamp = strtotime($value);
            return $timestamp ? wp_date('d.m.Y', $timestamp) : '';
        }

        return self::clean_text($value);
    }

    private static function write_simple_pdf(string $path, string $title, array $lines): void
    {
        $stream = "q\n";
        $stream .= self::text_line($title, 50, 790, 18, true);
        $stream .= self::text_line('Formulaire de convocation', 50, 760, 12, false);
        $stream .= "0.2 w 50 748 m 545 748 l S\n";

        $y = 715;
        foreach ($lines as $line) {
            if ($line === '') {
                $y -= 16;
                continue;
            }

            $wrapped = self::wrap_line((string) $line, 92);
            foreach ($wrapped as $wrapped_line) {
                $stream .= self::text_line($wrapped_line, 50, $y, 11, false);
                $y -= 17;
            }
        }

        $stream .= "Q\n";

        self::write_pdf($path, $stream);
    }

    private static function write_pdf(string $path, string $stream, array $images = array()): void
    {
        self::write_pdf_pages($path, array(array('stream' => $stream, 'images' => $images)));
    }

    private static function write_pdf_pages(string $path, array $pages): void
    {
        $objects = array(
            '',
            '',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        );
        $image_refs = array();

        foreach ($pages as $page) {
            foreach (($page['images'] ?? array()) as $image) {
                if (empty($image['name']) || isset($image_refs[$image['name']])) {
                    continue;
                }

                $object_number = count($objects) + 1;
                $image_refs[$image['name']] = $object_number;
                $objects[] = $image['object'];
            }
        }

        $page_refs = array();

        foreach ($pages as $page) {
            $stream = (string) ($page['stream'] ?? '');
            $page_images = array();
            foreach (($page['images'] ?? array()) as $image) {
                if (!empty($image['name']) && isset($image_refs[$image['name']])) {
                    $page_images[] = '/' . $image['name'] . ' ' . $image_refs[$image['name']] . ' 0 R';
                }
            }

            $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>';
            if ($page_images) {
                $resources .= ' /XObject << ' . implode(' ', $page_images) . ' >>';
            }
            $resources .= ' >>';

            $object_number = count($objects) + 1;
            $content_object_number = $object_number + 1;
            $page_refs[] = $object_number . ' 0 R';
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources ' . $resources . ' /Contents ' . $content_object_number . ' 0 R >>';
            $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $objects[0] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $page_refs) . '] /Count ' . count($page_refs) . ' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = array();

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF";

        file_put_contents($path, $pdf);
    }

    private static function jpeg_image(string $name, string $path): array
    {
        if (!is_readable($path)) {
            return array();
        }

        $size = @getimagesize($path);
        if (!$size || (int) $size[2] !== IMAGETYPE_JPEG) {
            return array();
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return array();
        }

        return array(
            'name' => $name,
            'object' => '<< /Type /XObject /Subtype /Image /Width ' . (int) $size[0] . ' /Height ' . (int) $size[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($bytes) . " >>\nstream\n" . $bytes . "\nendstream",
        );
    }

    private static function image(string $name, float $x, float $y, float $width, float $height): string
    {
        return 'q ' . self::number($width) . ' 0 0 ' . self::number($height) . ' ' . self::number($x) . ' ' . self::number($y) . ' cm /' . $name . " Do Q\n";
    }

    private static function rectangle(float $x, float $y, float $width, float $height): string
    {
        return '0 0 0 RG 0.7 w ' . self::number($x) . ' ' . self::number($y) . ' ' . self::number($width) . ' ' . self::number($height) . " re S\n";
    }

    private static function text_line(string $text, int $x, int $y, int $size, bool $bold, array $rgb = array(0, 0, 0)): string
    {
        $font = $bold ? 'F2' : 'F1';
        return self::color($rgb) . "BT /{$font} {$size} Tf {$x} {$y} Td (" . self::escape_pdf_text($text) . ") Tj ET\n";
    }

    private static function text_fit(string $text, int $x, int $y, int $max_width, int $size, bool $bold, array $rgb = array(0, 0, 0)): string
    {
        $estimated_width = self::estimated_text_width($text, $size);

        if ($estimated_width > $max_width && $estimated_width > 0) {
            $size = max(8, (int) floor($size * ($max_width / $estimated_width)));
        }

        return self::text_line($text, $x, $y, $size, $bold, $rgb);
    }

    private static function text_block(string $text, int $x, int $y, int $max_width, int $size, bool $bold, int $line_height, array $rgb = array(0, 0, 0)): string
    {
        $stream = '';

        foreach (self::wrap_text_by_width($text, $max_width, $size) as $line) {
            $stream .= self::text_line($line, $x, $y, $size, $bold, $rgb);
            $y -= $line_height;
        }

        return $stream;
    }

    private static function wrap_text_by_width(string $text, int $max_width, int $size): array
    {
        $words = preg_split('/\s+/', self::clean_text($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return array();
        }

        $lines = array();
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && self::estimated_text_width($candidate, $size) > $max_width) {
                $lines[] = $current;
                $current = $word;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private static function estimated_text_width(string $text, int $size): float
    {
        return strlen(self::encode_pdf_text($text)) * $size * 0.52;
    }

    private static function clean_text(string $text): string
    {
        return trim(wp_strip_all_tags($text));
    }

    private static function escape_pdf_text(string $text): string
    {
        $text = self::encode_pdf_text($text);

        return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
    }

    private static function encode_pdf_text(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        return $text;
    }

    private static function color(array $rgb): string
    {
        $red = isset($rgb[0]) ? (float) $rgb[0] : 0;
        $green = isset($rgb[1]) ? (float) $rgb[1] : 0;
        $blue = isset($rgb[2]) ? (float) $rgb[2] : 0;

        return self::number($red) . ' ' . self::number($green) . ' ' . self::number($blue) . " rg\n";
    }

    private static function number(float $value): string
    {
        $number = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return $number === '' ? '0' : $number;
    }

    private static function wrap_line(string $line, int $width): array
    {
        $plain = trim(preg_replace('/\s+/', ' ', $line));

        if ($plain === '') {
            return array('');
        }

        return explode("\n", wordwrap($plain, $width, "\n", false));
    }
}
