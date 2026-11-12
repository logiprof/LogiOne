<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNE_PDF
{
    private float $page_width = 595.28;
    private float $page_height = 841.89;
    private float $margin = 42.0;
    private float $y = 800.0;
    private string $content = '';
    private array $pages = array();
    private array $page_annotations = array();
    private array $current_annotations = array();

    public function __construct(string $orientation = 'portrait')
    {
        if ($orientation === 'landscape') {
            $this->page_width = 841.89;
            $this->page_height = 595.28;
            $this->margin = 36.0;
        }

        $this->start_page();
    }

    public static function evaluation_report(object $evaluation, array $rows, array $stats, ?string $progress_pdf_url = null): string
    {
        $pdf = new self();
        $class_label = trim((string) $evaluation->class_name);
        $semester_label = trim((string) $evaluation->semester_name . ' ' . (string) ($evaluation->semester_school_year ?? ''));
        $generated_at = date_i18n(get_option('date_format') . ' H:i');
        $progress_pdf_name = trim((string) ($evaluation->progress_pdf_name ?? ''));
        $progress_pdf_value = $progress_pdf_name !== '' && $progress_pdf_url
            ? array('text' => 'Ouvrir le justificatif', 'url' => $progress_pdf_url)
            : '-';

        $pdf->header('Évaluation - ' . (string) $evaluation->module_name, 'Export généré le ' . $generated_at);
        $pdf->metadata(array(
            'Groupe classe' => $class_label,
            'Branche' => trim((string) ($evaluation->branch_name ?? '')) !== '' ? trim((string) $evaluation->branch_name) : '-',
            'Semestre' => $semester_label,
            'Date' => self::display_date((string) $evaluation->evaluation_date),
            'Points totaux' => self::format_decimal($evaluation->total_points),
            'Méthode de calcul' => '((points obtenus + bonus/malus) x 5 / points totaux) + 1, arrondi au 0.5',
            'Nombre de notes' => (string) (int) ($stats['grade_count'] ?? 0),
            'En attente' => (string) (int) ($stats['missing_count'] ?? 0),
            'Triches' => (string) (int) ($stats['cheating_count'] ?? 0),
            'Rattrapages élèves' => (string) (int) ($stats['makeup_count'] ?? 0),
            'Non faites' => (string) (int) ($stats['not_applicable_count'] ?? 0),
            'Justificatif bonus/malus' => $progress_pdf_value,
            'Moyenne (hors triche)' => self::format_average($stats['avg_note'] ?? null),
            'Note la plus basse' => self::format_note($stats['min_note'] ?? null),
            'Note la plus haute' => self::format_note($stats['max_note'] ?? null),
        ));

        $pdf->section_title('Notes par élève');
        $pdf->table_header(array(
            array('label' => 'Élève', 'width' => 112),
            array('label' => 'Statut', 'width' => 55),
            array('label' => 'Points', 'width' => 56),
            array('label' => '+/-', 'width' => 42),
            array('label' => 'Total pts', 'width' => 58),
            array('label' => 'Rattrapage', 'width' => 70),
            array('label' => 'Note sans +/-', 'width' => 75),
            array('label' => 'Note validée', 'width' => 43),
        ));

        foreach ($rows as $row) {
            $student_name = trim((string) $row->last_name . ' ' . (string) $row->first_name);
            $is_cheating = (int) ($row->is_cheating ?? 0) === 1;
            $status = $is_cheating ? 'Triche' : self::grade_status_label((string) ($row->grade_status ?? 'missing'));
            $makeup_date = ((int) ($row->is_makeup ?? 0) === 1) ? self::display_date((string) ($row->makeup_date ?? '')) : '-';
            $pdf->table_row(array(
                self::truncate($student_name, 28),
                $status,
                $is_cheating || $row->points_obtained === null ? '-' : self::format_decimal($row->points_obtained) . '/' . self::format_decimal($evaluation->total_points),
                $is_cheating ? '-' : self::format_adjustment($row->points_adjustment ?? 0),
                $is_cheating ? '-' : self::format_adjusted_points($row->points_obtained ?? null, $row->points_adjustment ?? 0),
                $makeup_date,
                $is_cheating ? '1' : self::format_note(self::calculate_note($row->points_obtained ?? null, $evaluation->total_points ?? null, 0.0)),
                self::format_note($row->note),
            ));
        }

        return $pdf->output();
    }

    public static function semester_report(object $class, object $semester, array $evaluations, array $rows, array $stats): string
    {
        $pdf = new self('landscape');
        $generated_at = date_i18n(get_option('date_format') . ' H:i');
        $class_label = trim((string) $class->name);
        $semester_label = trim((string) $semester->name . ' ' . (string) $semester->school_year);

        $pdf->header('Relevé de semestre - ' . (string) $class->name, 'Export généré le ' . $generated_at);
        $pdf->metadata(array(
            'Groupe classe' => $class_label,
            'Semestre' => $semester_label,
            'Évaluations' => (string) count($evaluations),
            'Élèves' => (string) count($rows),
            'Moyenne du groupe (hors triche)' => self::format_average($stats['class_average'] ?? null),
            'Moyenne' => 'calculée au dixième sur les notes évaluables, hors triche',
        ));

        if (!$evaluations) {
            $pdf->section_title('Notes');
            $pdf->text($pdf->margin, $pdf->y, 'Aucune évaluation saisie pour ce groupe classe et ce semestre.', 10, 'F1', 0.35);
            return $pdf->output();
        }

        if (!$rows) {
            $pdf->section_title('Notes');
            $pdf->text($pdf->margin, $pdf->y, 'Aucun élève dans ce groupe classe.', 10, 'F1', 0.35);
            return $pdf->output();
        }

        $pdf->section_title('Tests du semestre');
        foreach ($evaluations as $index => $evaluation) {
            $branch_name = trim((string) ($evaluation->branch_name ?? ''));
            $label = sprintf(
                'T%d - %s%s - %s - %s pts',
                $index + 1,
                (string) $evaluation->module_name,
                $branch_name !== '' ? ' - ' . $branch_name : '',
                self::display_date((string) $evaluation->evaluation_date),
                self::format_decimal($evaluation->total_points)
            );
            $pdf->ensure_space(15);
            $pdf->text($pdf->margin, $pdf->y, self::truncate($label, 120), 8.5, 'F1', 0.1);
            $pdf->y -= 14;
        }

        $pdf->y -= 8;
        $chunks = array_chunk($evaluations, 8, true);
        $chunk_count = count($chunks);

        foreach ($chunks as $chunk_index => $chunk) {
            $pdf->section_title($chunk_count > 1 ? 'Notes par élève (' . ($chunk_index + 1) . '/' . $chunk_count . ')' : 'Notes par élève');
            $available_width = $pdf->page_width - ($pdf->margin * 2);
            $student_width = 170;
            $average_width = 62;
            $test_width = floor(($available_width - $student_width - $average_width) / max(1, count($chunk)));
            $columns = array(array('label' => 'Élève', 'width' => $student_width));

            foreach ($chunk as $evaluation_index => $evaluation) {
                $columns[] = array('label' => 'T' . ($evaluation_index + 1), 'width' => $test_width);
            }

            $columns[] = array('label' => 'Moyenne', 'width' => $average_width);
            $widths = array_map(static fn(array $column): float => (float) $column['width'], $columns);
            $pdf->table_header($columns);

            foreach ($rows as $row) {
                $student = $row['student'];
                $cells = array(self::truncate(trim((string) $student->last_name . ' ' . (string) $student->first_name), 32));

                foreach ($chunk as $evaluation) {
                    $grade = $row['grades'][(int) $evaluation->id] ?? null;
                    $cells[] = self::semester_grade_cell($grade);
                }

                $cells[] = self::format_average($row['average'] ?? null);
                $pdf->table_row_with_columns($cells, $widths, 22, array(count($cells) - 1));
            }

            $pdf->y -= 8;
        }

        $pdf->section_title('Légende');
        $pdf->text($pdf->margin, $pdf->y, 'NF = évaluation marquée comme ne sera pas faite. R = rattrapage. T = triche, note fixée à 1. +/- = bonus ou malus appliqué aux points. Tiret = note en attente.', 8.5, 'F1', 0.35);

        return $pdf->output();
    }

    private function start_page(): void
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
            $this->page_annotations[] = $this->current_annotations;
        }

        $this->content = '';
        $this->current_annotations = array();
        $this->y = $this->page_height - $this->margin;
    }

    private function header(string $title, string $subtitle): void
    {
        $this->rect($this->margin, $this->page_height - 92, $this->page_width - ($this->margin * 2), 50, 0.94, 0.96, 0.98);
        $this->text($this->margin + 14, $this->page_height - 66, $title, 17, 'F2');
        $this->text($this->margin + 14, $this->page_height - 84, $subtitle, 9, 'F1', 0.35);
        $this->y = $this->page_height - 120;
    }

    private function metadata(array $items): void
    {
        $left_x = $this->margin;
        $right_x = $this->margin + 270;
        $line_height = 19;
        $index = 0;

        foreach ($items as $label => $value) {
            $this->ensure_space($line_height);
            $x = ($index % 2 === 0) ? $left_x : $right_x;
            $y = $this->y;
            $value_x = $x + 118;
            $value_limit = ($index % 2 === 0) ? 26 : 24;

            $this->text($x, $y, (string) $label, 8, 'F2', 0.35);
            if (is_array($value) && isset($value['text'], $value['url'])) {
                $this->link_text($value_x, $y, self::truncate((string) $value['text'], $value_limit), (string) $value['url'], 9);
            } else {
                $this->text($value_x, $y, self::truncate((string) $value, $value_limit), 9, 'F1');
            }

            if ($index % 2 === 1) {
                $this->y -= $line_height;
            }

            $index++;
        }

        if ($index % 2 === 1) {
            $this->y -= $line_height;
        }

        $this->y -= 8;
    }

    private function section_title(string $title): void
    {
        $this->ensure_space(28);
        $this->text($this->margin, $this->y, $title, 13, 'F2');
        $this->line($this->margin, $this->y - 8, $this->page_width - $this->margin, $this->y - 8, 0.82, 0.86, 0.9);
        $this->y -= 26;
    }

    private function table_header(array $columns): void
    {
        $this->ensure_space(32);
        $x = $this->margin;
        $row_height = 22;
        $this->rect($x, $this->y - $row_height + 6, array_sum(array_column($columns, 'width')), $row_height, 0.94, 0.96, 0.98);

        foreach ($columns as $column) {
            $this->text($x + 5, $this->y - 8, (string) $column['label'], 8.5, 'F2', 0.18);
            $x += (float) $column['width'];
        }

        $this->y -= $row_height;
    }

    private function table_row(array $cells): void
    {
        $columns = array(112, 55, 56, 42, 58, 70, 75, 43);
        $this->table_row_with_columns($cells, $columns, 22, array(2, 4, 6, 7));
    }

    private function table_row_with_columns(array $cells, array $columns, float $row_height = 22, array $bold_indexes = array()): void
    {
        $this->ensure_space($row_height + 4);
        $x = $this->margin;
        $line_width = min($this->page_width - $this->margin, $this->margin + array_sum($columns));
        $this->line($this->margin, $this->y - $row_height + 4, $line_width, $this->y - $row_height + 4, 0.87, 0.9, 0.93);

        foreach ($cells as $index => $cell) {
            if (!isset($columns[$index])) {
                continue;
            }

            $width = (float) $columns[$index];
            $limit = max(3, (int) floor($width / 4.6));
            $this->text($x + 5, $this->y - 9, self::truncate((string) $cell, $limit), 8.5, in_array($index, $bold_indexes, true) ? 'F2' : 'F1');
            $x += $width;
        }

        $this->y -= $row_height;
    }

    private function ensure_space(float $height): void
    {
        if ($this->y - $height < $this->margin) {
            $this->start_page();
        }
    }

    private function text(float $x, float $y, string $text, float $size, string $font = 'F1', float $gray = 0.0): void
    {
        $this->content .= sprintf(
            "%.3F g BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $gray,
            $font,
            $size,
            $x,
            $y,
            self::pdf_string($text)
        );
    }

    private function link_text(float $x, float $y, string $text, string $url, float $size): void
    {
        $this->text($x, $y, $text, $size, 'F1');
        $width = min(
            max(44.0, strlen($text) * $size * 0.5),
            max(44.0, $this->page_width - $this->margin - $x)
        );
        $this->line($x, $y - 2, $x + $width, $y - 2, 0.2, 0.2, 0.2);
        $this->current_annotations[] = array(
            'x1' => $x,
            'y1' => $y - 3,
            'x2' => $x + $width,
            'y2' => $y + $size + 3,
            'url' => $url,
        );
    }

    private function rect(float $x, float $y, float $width, float $height, float $r, float $g, float $b): void
    {
        $this->content .= sprintf(
            "q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q\n",
            $r,
            $g,
            $b,
            $x,
            $y,
            $width,
            $height
        );
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $r, float $g, float $b): void
    {
        $this->content .= sprintf(
            "q %.3F %.3F %.3F RG 0.6 w %.2F %.2F m %.2F %.2F l S Q\n",
            $r,
            $g,
            $b,
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    private function output(): string
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
            $this->page_annotations[] = $this->current_annotations;
            $this->content = '';
            $this->current_annotations = array();
        }

        $page_count = count($this->pages);
        $objects = array();
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $page_object_start = 5;
        $content_object_start = $page_object_start + $page_count;
        $annotation_object_start = $content_object_start + $page_count;
        $kids = array();
        $annotation_ids_by_page = array();
        $next_annotation_id = $annotation_object_start;

        for ($i = 0; $i < $page_count; $i++) {
            $kids[] = ($page_object_start + $i) . ' 0 R';
            $annotation_ids_by_page[$i] = array();

            foreach (($this->page_annotations[$i] ?? array()) as $annotation) {
                $annotation_ids_by_page[$i][] = $next_annotation_id;
                $objects[$next_annotation_id] = sprintf(
                    '<< /Type /Annot /Subtype /Link /Rect [%.2F %.2F %.2F %.2F] /Border [0 0 0] /A << /S /URI /URI (%s) >> >>',
                    (float) $annotation['x1'],
                    (float) $annotation['y1'],
                    (float) $annotation['x2'],
                    (float) $annotation['y2'],
                    self::pdf_string((string) $annotation['url'])
                );
                $next_annotation_id++;
            }
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $page_count . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        for ($i = 0; $i < $page_count; $i++) {
            $annotation_refs = array_map(static fn(int $id): string => $id . ' 0 R', $annotation_ids_by_page[$i] ?? array());
            $annotations = $annotation_refs ? ' /Annots [' . implode(' ', $annotation_refs) . ']' : '';
            $objects[$page_object_start + $i] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R%s >>',
                $this->page_width,
                $this->page_height,
                $content_object_start + $i,
                $annotations
            );
            $stream = $this->pages[$i];
            $objects[$content_object_start + $i] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array(0 => 0);

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $start_xref = strlen($pdf);
        $max_id = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max_id + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $max_id; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($max_id + 1) . " /Root 1 0 R >>\nstartxref\n" . $start_xref . "\n%%EOF";

        return $pdf;
    }

    private static function pdf_string(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $encoded = false;

        if (function_exists('iconv')) {
            $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        }

        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E\n]/', '?', remove_accents($text));
        }

        $result = '';
        $length = strlen((string) $encoded);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($encoded[$i]);
            if ($byte === 40 || $byte === 41 || $byte === 92) {
                $result .= '\\' . chr($byte);
            } elseif ($byte === 10) {
                $result .= '\\n';
            } elseif ($byte < 32 || $byte > 126) {
                $result .= sprintf('\\%03o', $byte);
            } else {
                $result .= chr($byte);
            }
        }

        return $result;
    }

    private static function truncate(string $text, int $limit): string
    {
        $text = trim($text);

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text) > $limit ? mb_substr($text, 0, max(0, $limit - 3)) . '...' : $text;
        }

        return strlen($text) > $limit ? substr($text, 0, max(0, $limit - 3)) . '...' : $text;
    }

    private static function display_date(string $value): string
    {
        if ($value === '' || $value === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : '-';
    }

    private static function format_decimal($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $formatted = number_format((float) $value, 2, ',', '');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    private static function format_note($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $float = (float) $value;
        if (abs($float - round($float)) < 0.00001) {
            return number_format($float, 0, ',', '');
        }

        return number_format($float, 1, ',', '');
    }

    private static function calculate_note($points, $total_points, float $adjustment = 0.0): ?float
    {
        if ($points === null || $points === '' || $total_points === null || $total_points === '') {
            return null;
        }

        $total = (float) $total_points;
        if ($total <= 0) {
            return null;
        }

        $adjusted_points = min(max((float) $points + $adjustment, 0.0), $total);
        $raw_note = (($adjusted_points / $total) * 5) + 1;

        return round($raw_note * 2) / 2;
    }

    private static function format_average($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 1, ',', '');
    }

    private static function semester_grade_cell($grade): string
    {
        if (!$grade) {
            return '-';
        }

        $status = (string) ($grade->status ?? 'missing');
        if ($status === 'not_applicable') {
            return 'NF';
        }

        if ($grade->note === null) {
            return '-';
        }

        $cell = self::format_note($grade->note);
        if ((int) ($grade->is_cheating ?? 0) === 1) {
            return $cell . ' T';
        }

        $adjustment = self::format_adjustment($grade->points_adjustment ?? 0);
        if ($adjustment !== '-') {
            $cell .= ' ' . $adjustment;
        }

        if ((int) ($grade->is_makeup ?? 0) === 1) {
            $cell .= ' R';
        }

        return $cell;
    }

    private static function format_adjustment($value): string
    {
        $adjustment = (float) $value;
        if (abs($adjustment) < 0.00001) {
            return '-';
        }

        return $adjustment > 0
            ? '+' . self::format_decimal($adjustment)
            : self::format_decimal($adjustment);
    }

    private static function format_adjusted_points($points, $adjustment): string
    {
        if ($points === null || $points === '') {
            return '-';
        }

        return self::format_decimal((float) $points + (float) $adjustment);
    }

    private static function grade_status_label(string $status): string
    {
        if ($status === 'not_applicable') {
            return 'Non faite';
        }

        if ($status === 'graded') {
            return 'Notée';
        }

        return 'En attente';
    }
}
