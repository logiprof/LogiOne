<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LOGIEVAL_Exporter
 *
 * Generates a .docx file using native PHP (ZipArchive + XML).
 * No Composer, no PhpWord dependency required.
 * DOCX format = ZIP archive containing XML files per OOXML spec.
 */
class LOGIEVAL_Exporter {

    private static function clean_filename_part( string $value ): string {
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value, " .-_\t\n\r\0\x0B");
        return $value !== '' ? $value : 'evaluation';
    }

    private static function content_disposition_filename( string $filename ): string {
        $fallback = remove_accents($filename);
        $fallback = preg_replace('/[^\x20-\x7E]/', '', $fallback);
        $fallback = str_replace('"', '', $fallback);
        if ($fallback === '') $fallback = 'LogiEval.docx';
        return 'attachment; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($filename);
    }

    public static function export_word( int $evaluation_id, int $only_eleve_id = 0 ): void {
        $ens_id = LOGIEVAL_Auth::current_enseignant_id();
        $eval   = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('evaluations'), ['id' => $evaluation_id] );

        if ( ! $eval ) wp_die('Évaluation introuvable.');
        if ( $eval['statut'] !== 'cloturee' && ! current_user_can( 'lp_eval_admin' ) )
            wp_die('Évaluation pas encore clôturée.');
        if ( ! current_user_can( 'lp_eval_admin' ) && ( ! $ens_id || ! LOGIEVAL_Auth::is_maitre($ens_id,(int)$eval['classe_id']) ) )
            wp_die('Accès réservé au maître de classe.');
        if ( ! class_exists('ZipArchive') )
            wp_die('L\'extension PHP ZipArchive est requise pour l\'export Word. Contactez votre hébergeur.');

        global $wpdb;
        $t          = LOGIEVAL_Database::tables();
        $classe     = LOGIEVAL_Database::get_row($t['classes'],   ['id' => $eval['classe_id']]);
        $eleves     = LOGIEVAL_Database::eval_students($eval); // 1.53.1
        $grid        = LOGIEVAL_Calculator::grid($evaluation_id); // figée si l'évaluation est clôturée
        $objectifs   = $grid['objectifs'];
        $sous_by_obj = $grid['sous_by_obj'];
        // Objectifs « maître de classe » (absences, retards) : détaillés pour le maître de classe seulement.
        $viewer_ens  = LOGIEVAL_Auth::current_enseignant_id();
        $hide_master_only = ! ( $viewer_ens && LOGIEVAL_Auth::is_maitre( $viewer_ens, (int) $eval['classe_id'] ) );

        $ens_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.prenom, e.nom, ee.statut, ee.date_fin, COALESCE(ce.est_maitre,0) AS est_maitre
             FROM {$t['enseignants']} e
             INNER JOIN {$t['eval_enseignant']} ee ON ee.enseignant_id=e.id
             LEFT JOIN {$t['classe_enseignant']} ce ON ce.classe_id=%d AND ce.enseignant_id=e.id
             WHERE ee.evaluation_id=%d
             ORDER BY ce.est_maitre DESC, e.nom,e.prenom",
            $eval['classe_id'],
            $evaluation_id
        ), ARRAY_A) ?: [];
        $participants = array_values(array_filter($ens_rows, fn($e) => $e['statut'] === 'termine'));
        $pending_rows = array_values(array_filter($ens_rows, fn($e) => $e['statut'] !== 'termine'));
        $participant_names = array_map(fn($e) => $e['prenom'].' '.$e['nom'], $participants ?: $ens_rows);
        $pending_names = array_map(fn($e) => $e['prenom'].' '.$e['nom'], $pending_rows);

        $maitre_row = $wpdb->get_row($wpdb->prepare(
            "SELECT e.prenom, e.nom FROM {$t['enseignants']} e
             INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id=e.id
             WHERE ce.classe_id=%d AND ce.est_maitre=1 LIMIT 1", $eval['classe_id']
        ), ARRAY_A);

        $votes      = LOGIEVAL_Calculator::get_votes($evaluation_id);
        $arbitrages = LOGIEVAL_Calculator::get_arbitrages($evaluation_id);
        $total_max  = (float) $grid['total_max'];
        $results    = LOGIEVAL_Calculator::full_results($evaluation_id);
        if ($only_eleve_id > 0) {
            $results = isset($results[$only_eleve_id]) ? [$only_eleve_id => $results[$only_eleve_id]] : [];
        }

        $refs_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, note_calculee FROM {$t['notes_ref']} WHERE evaluation_id_dest=%d", $evaluation_id
        ), ARRAY_A) ?: [];
        $refs = array_column($refs_raw, 'note_calculee', 'eleve_id');

        // ── Build document XML ────────────────────────────────────
        $etablissement = get_option('logieval_etablissement', 'Centre professionnel du Nord vaudois');
        $doc = new LOGIEVAL_DocxBuilder();

        // Cover
        $doc->addLogoTitle('Evaluation de votre posture professionnelle', '');
        $doc->addSubtitle($classe['nom'] . ' — ' . $eval['semestre']);
        $doc->addInfo('Établissement', $etablissement);
        $doc->addInfo('Date d\'échéance', date_i18n('d.m.Y', strtotime($eval['date_echeance'])));
        $doc->addInfo('Maître de classe', $maitre_row ? $maitre_row['prenom'].' '.$maitre_row['nom'] : '—');
        $doc->addInfo('Enseignants participants', $participant_names ? implode(', ', $participant_names) : '—');
        if ($eval['statut'] === 'cloturee' && $pending_names) {
            $doc->addInfo('Clôture partielle', 'Le maître de classe a finalisé cette évaluation sans attendre : '.implode(', ', $pending_names).'. Ces enseignants ne sont pas pris en compte dans les résultats.');
        }
        $doc->addInfo('Total points max', number_format($total_max, 2) . ' pts');
        $doc->pageBreak();

        // Results per student
        if ($only_eleve_id === 0) {
            $doc->addHeading('Enseignants ayant participé à l\'évaluation');
            $doc->openTable();
            $doc->addTableHeaderRow(['Enseignant', 'Rôle', 'Clôture de l\'évaluation']);
            foreach ($ens_rows as $ens_row) {
                $doc->addTableRow([
                    $ens_row['prenom'].' '.$ens_row['nom'],
                    !empty($ens_row['est_maitre']) ? 'Maître de classe' : 'Enseignant',
                    !empty($ens_row['date_fin'])
                        ? date_i18n('d.m.Y H:i', strtotime($ens_row['date_fin']))
                        : 'Non clôturée',
                ]);
            }
            $doc->closeTable();
            $doc->addSpace();
        }

        $doc->addHeading('Résultats par élève');

        // Table header
        $headers = ['Élève', 'Points moy.', 'Note /6'];
        if (!empty($refs)) $headers[] = 'S. préc.';
        $doc->openTable();
        $doc->addTableHeaderRow($headers);

        $notes_valid = [];
        foreach ($results as $eleve_id => $data) {
            $note    = $data['note'];
            $avg_pts = $data['avg_pts'] ?? $data['pts'] ?? null;
            $row     = [
                $data['eleve']['nom'].' '.$data['eleve']['prenom'],
                $avg_pts !== null ? number_format($avg_pts,2).'/'.number_format((float)($data['max'] ?? $total_max),2) : '—',
                $note    !== null ? number_format($note,1) : '—',
            ];
            if (!empty($refs)) $row[] = isset($refs[$eleve_id]) ? number_format((float)$refs[$eleve_id],1) : '—';
            $doc->addTableRow($row, $note);
            if ($note !== null) $notes_valid[] = $note;
        }
        $doc->closeTable();

        if ($notes_valid) {
            $moy = LOGIEVAL_Calculator::round_half(array_sum($notes_valid) / count($notes_valid));
            $doc->addSpace();
            $doc->addBold('Moyenne de classe : ' . number_format($moy, 1) . ' / 6');
        }

        $doc->addSpace();

        // Detail per student (vote-based results)
        $doc->addHeading('Résultats détaillés par objectif');
        $ens_ids_arr = array_column($ens_rows, 'id');

        foreach ($results as $eleve_id => $data) {
            $doc->addStudentTitle(
                $data['eleve']['nom'].' '.$data['eleve']['prenom'],
                $data['note'] !== null ? number_format($data['note'],1).'/6 ('.number_format((float)$data['pts'],2).'/'.number_format((float)($data['max'] ?? $total_max),2).' pts)' : 'pas encore évalué'
            );

            $hidden_master_pts = 0.0;
            foreach ($objectifs as $obj) {
                if ($hide_master_only && !empty($obj['maitre_seulement'])) {
                    $hidden_master_pts += (float)($data['details']['radio_'.(int)$obj['id']]['pts'] ?? 0.0);
                    continue;
                }
                $oid     = (int)$obj['id'];
                $scs     = $sous_by_obj[$oid] ?? [];
                $rows    = [];

                if ($obj['type'] === 'radio') {
                    $radio_details = $data['details']['radio_'.$oid] ?? ['tally'=>[]];
                    $tally = $radio_details['tally'] ?? [];
                    $top = $tally ? reset($tally) : 0;
                    $top_ids = $top > 0 ? array_keys(array_filter($tally, fn($count) => $count === $top)) : [];
                    $radio_voters = array_sum($tally);
                    $has_radio_tie = $top > 0 && count($top_ids) > 1;
                    $highest_tied_pts = 0.0;
                    $half_awarded_id = 0;
                    if ($has_radio_tie) {
                        foreach ($scs as $tie_sc) {
                            if (in_array((int)$tie_sc['id'], array_map('intval', $top_ids), true)) {
                                if ((float)$tie_sc['points'] > $highest_tied_pts) {
                                    $highest_tied_pts = (float)$tie_sc['points'];
                                    $half_awarded_id = (int)$tie_sc['id'];
                                }
                            }
                        }
                    }
                    foreach ($scs as $sc) {
                        if (empty($obj['maitre_seulement']) && ($eval['mode_calcul'] ?? 'majoritaire') === 'proportionnel') {
                            $obtained = $radio_voters > 0
                                ? ((float)$sc['points']) * ((int)($tally[$sc['id']] ?? 0) / $radio_voters)
                                : 0.0;
                            $row_result = $obtained > 0 ? 'oui' : 'non';
                        } elseif (count($top_ids) === 1 && (int)$top_ids[0] === (int)$sc['id']) {
                            $obtained = (float)$sc['points'];
                            $row_result = 'oui';
                        } elseif ($has_radio_tie && (int)$sc['id'] === $half_awarded_id) {
                            $obtained = $highest_tied_pts / 2;
                            $row_result = 'egalite';
                        } else {
                            $obtained = 0.0;
                            $row_result = 'non';
                        }
                        $rows[] = [
                            $sc['lettre'].' - '.$sc['libelle'].' ('.number_format((float)$sc['points'],1).' pt)',
                            number_format($obtained,2).' / '.number_format((float)$sc['points'],2),
                            $row_result,
                        ];
                    }
                } else {
                    foreach ($scs as $sc) {
                        $vr  = $data['details'][$sc['id']] ?? ['result'=>'abstention'];
                        $res = $vr['result'];
                        $obtained = (float)($vr['awarded_points'] ?? 0.0);
                        $rows[] = [
                            $sc['lettre'].' - '.$sc['libelle'].' ('.number_format((float)$sc['points'],1).' pt)',
                            number_format($obtained,2).' / '.number_format((float)$sc['points'],2),
                            $res,
                        ];
                    }
                }
                $label = ($obj['maitre_seulement']?'[Maître] ':'') . $obj['libelle'];
                $doc->addObjectiveResultTable($label, $rows);
            }
            if ($hide_master_only && $hidden_master_pts > 0) {
                $doc->addInfo('Objectifs du maître de classe', number_format($hidden_master_pts, 2).' pts (détail visible par le maître de classe)');
            }
            $doc->addSpace();
        }

        if ($only_eleve_id > 0) {
            $doc->addSpace();
            $doc->addSpace();
            $doc->addHeading('Signature du maître de classe');
            $doc->addInfo('Maître de classe', $maitre_row ? $maitre_row['prenom'].' '.$maitre_row['nom'] : '—');
            $doc->addSignatureLine();
        }

        // ── Stream DOCX ───────────────────────────────────────────
        $filename_base = 'LogiEval_' . ($classe['nom'] ?? 'classe');
        if ($only_eleve_id > 0 && !empty($results[$only_eleve_id]['eleve'])) {
            $eleve = $results[$only_eleve_id]['eleve'];
            $filename_base .= '_' . ($eval['semestre'] ?? 'semestre');
            $filename_base .= '_' . trim(($eleve['prenom'] ?? '') . ' ' . ($eleve['nom'] ?? ''));
        }
        $filename = self::clean_filename_part($filename_base).'.docx';
        $docx_data = $doc->build();

        header('Content-Description: File Transfer');
        header('Content-Disposition: '.self::content_disposition_filename($filename));
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Length: '.strlen($docx_data));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        echo $docx_data;
        exit;
    }
}

/* ══════════════════════════════════════════════════════════════
 * LOGIEVAL_DocxBuilder
 * Builds a minimal but complete DOCX file using ZipArchive + XML
 * Colors: green #1B6B3A, dark #1A1A2E, orange #D97706
 * ══════════════════════════════════════════════════════════════ */
class LOGIEVAL_DocxBuilder {

    private array $body_parts = [];
    private bool  $in_table   = false;
    private ?array $image      = null;

    /* ── Helpers ────────────────────────────────────────────── */
    private static function x( string $text ): string {
        return htmlspecialchars($text, ENT_XML1, 'UTF-8');
    }

    private static function rgb_to_hex( float $note ): string {
        if ($note >= 4.5) return '1B6B3A';
        if ($note >= 3.0) return 'D97706';
        return 'C0392B';
    }

    private function para( string $xml ): void {
        $this->body_parts[] = '<w:p>'.$xml.'</w:p>';
    }

    private function run( string $text, array $opts = [] ): string {
        $rpr = '<w:rPr>';
        if (!empty($opts['bold']))   $rpr .= '<w:b/>';
        if (!empty($opts['italic'])) $rpr .= '<w:i/>';
        if (!empty($opts['size']))   $rpr .= '<w:sz w:val="'.(int)($opts['size']*2).'"/><w:szCs w:val="'.(int)($opts['size']*2).'"/>';
        if (!empty($opts['color']))  $rpr .= '<w:color w:val="'.$opts['color'].'"/>';
        if (!empty($opts['font']))   $rpr .= '<w:rFonts w:ascii="'.$opts['font'].'" w:hAnsi="'.$opts['font'].'"/>';
        $rpr .= '</w:rPr>';
        return '<w:r>'.$rpr.'<w:t xml:space="preserve">'.self::x($text).'</w:t></w:r>';
    }

    private function ppr( array $opts = [] ): string {
        $ppr = '<w:pPr>';
        if (!empty($opts['spacing_before'])) $ppr .= '<w:spacing w:before="'.$opts['spacing_before'].'" w:after="'.($opts['spacing_after']??80).'"/>';
        if (!empty($opts['shading']))        $ppr .= '<w:shd w:val="clear" w:color="auto" w:fill="'.$opts['shading'].'"/>';
        if (!empty($opts['indent']))         $ppr .= '<w:ind w:left="'.$opts['indent'].'"/>';
        if (!empty($opts['align']))          $ppr .= '<w:jc w:val="'.$opts['align'].'"/>';
        $ppr .= '</w:pPr>';
        return $ppr;
    }

    private function imageRun( string $rid, int $cx, int $cy ): string {
        return '<w:r><w:drawing>'
            .'<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:docPr id="1" name="CPNV"/>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr><pic:cNvPr id="0" name="CPNV"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="'.$rid.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    /* ── Content methods ────────────────────────────────────── */
    public function addLogoTitle( string $text, string $image_path ): void {
        $has_image = is_readable($image_path);
        if ($has_image) {
            $this->image = [
                'path' => $image_path,
                'name' => 'cpnv-logo.png',
                'rid'  => 'rId3',
            ];
        }

        $this->body_parts[] = '<w:tbl>'
            .'<w:tblPr><w:tblW w:w="9026" w:type="dxa"/>'
            .'<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
            .'</w:tblPr>'
            .'<w:tr>'
            .'<w:tc><w:tcPr><w:tcW w:w="5850" w:type="dxa"/></w:tcPr>'
            .'<w:p>'.$this->ppr(['spacing_before'=>200,'spacing_after'=>120])
            .$this->run($text, ['bold'=>true,'size'=>20,'color'=>'1A1A2E','font'=>'Arial'])
            .'</w:p></w:tc>'
            .'<w:tc><w:tcPr><w:tcW w:w="3176" w:type="dxa"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="right"/></w:pPr>'
            .($has_image ? $this->imageRun('rId3', 1778000, 624000) : '')
            .'</w:p></w:tc>'
            .'</w:tr>'
            .'</w:tbl>';
    }

    public function addTitle( string $text ): void {
        $this->para(
            $this->ppr(['spacing_before'=>240,'shading'=>'1A1A2E','align'=>'center'])
            .$this->run($text, ['bold'=>true,'size'=>18,'color'=>'FFFFFF','font'=>'Arial'])
        );
    }

    public function addSubtitle( string $text ): void {
        $this->para(
            $this->ppr(['spacing_before'=>40,'spacing_after'=>120])
            .$this->run($text, ['size'=>10,'color'=>'334155','font'=>'Arial'])
        );
    }

    public function addInfo( string $label, string $value ): void {
        $this->para(
            $this->ppr(['spacing_before'=>60,'spacing_after'=>60,'indent'=>200])
            .$this->run($label.': ', ['bold'=>true,'size'=>11,'color'=>'1A1A2E','font'=>'Arial'])
            .$this->run($value, ['size'=>11,'font'=>'Arial'])
        );
    }

    public function addHeading( string $text ): void {
        $this->para(
            $this->ppr(['spacing_before'=>220,'spacing_after'=>100])
            .$this->run($text, ['bold'=>false,'size'=>13,'color'=>'006B35','font'=>'Arial'])
        );
    }

    public function addBullet( string $text ): void {
        $this->para(
            $this->ppr(['spacing_before'=>40,'spacing_after'=>40,'indent'=>400])
            .$this->run('• ', ['color'=>'1B6B3A','font'=>'Arial','size'=>11])
            .$this->run($text, ['size'=>11,'font'=>'Arial'])
        );
    }

    public function addBold( string $text ): void {
        $this->para(
            $this->ppr(['spacing_before'=>80,'spacing_after'=>80,'indent'=>200])
            .$this->run($text, ['bold'=>true,'size'=>11,'font'=>'Arial'])
        );
    }

    public function addSpace(): void {
        $this->para('<w:pPr><w:spacing w:before="80" w:after="80"/></w:pPr>');
    }

    public function pageBreak(): void {
        $this->para('<w:r><w:br w:type="page"/></w:r>');
    }

    public function addStudentTitle( string $name, string $note ): void {
        $this->para(
            $this->ppr(['spacing_before'=>160,'spacing_after'=>80])
            .$this->run($name, ['bold'=>true,'size'=>12,'color'=>'006B35','font'=>'Arial'])
            .$this->run('   '.$note, ['bold'=>true,'size'=>12,'color'=>'006B35','font'=>'Arial'])
        );
    }

    public function addObjectifLine( string $label, string $score, string $result='' ): void {
        $is_maitre   = strpos($label, '[Maître]') === 0;
        $color       = $is_maitre ? '92400E' : '334155';
        $bg          = $is_maitre ? 'FFFBEB' : 'F8FAFC';
        $score_color = $result==='oui'?'1B6B3A':($result==='non'?'C0392B':($result==='egalite'?'D97706':'888888'));
        $this->para(
            $this->ppr(['spacing_before'=>40,'spacing_after'=>40,'shading'=>$bg,'indent'=>300])
            .$this->run($label, ['size'=>10,'color'=>$color,'font'=>'Arial'])
            .$this->run('  →  '.$score, ['bold'=>true,'size'=>10,'color'=>$score_color,'font'=>'Arial'])
        );
    }

    public function addObjectiveResultTable( string $title, array $rows ): void {
        $is_maitre = strpos($title, '[Maître]') === 0;
        $title_col = $is_maitre ? '92400E' : '166534';
        $this->para(
            $this->ppr(['spacing_before'=>120,'spacing_after'=>30])
            .$this->run($title, ['bold'=>false,'size'=>11,'color'=>$title_col,'font'=>'Arial'])
        );

        $this->body_parts[] = '<w:tbl>'
            .'<w:tblPr>'
            .'<w:tblStyle w:val="TableGrid"/>'
            .'<w:tblW w:w="9026" w:type="dxa"/>'
            .'<w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:left w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:right w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:insideV w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'</w:tblBorders>'
            .'</w:tblPr>';

        $this->addObjectiveTableRow(['Sous-critère', 'Point obtenu'], true);
        foreach ($rows as $row) {
            $this->addObjectiveTableRow([$row[0], $row[1]], false, $row[2] ?? '');
        }
        $this->body_parts[] = '</w:tbl>';
    }

    private function addObjectiveTableRow( array $cols, bool $header = false, string $result = '' ): void {
        $bg = 'FFFFFF';
        $score_color = $result==='oui'?'1B6B3A':($result==='non'?'C0392B':($result==='egalite'?'D97706':'555555'));
        $widths = [7200, 1826];
        $xml = '<w:tr>';
        foreach ($cols as $i => $val) {
            $color = $i === 1 && !$header ? $score_color : '555555';
            $bold = $header || $i === 1;
            $xml .= '<w:tc>'
                .'<w:tcPr>'
                .'<w:tcW w:w="'.$widths[$i].'" w:type="dxa"/>'
                .'<w:shd w:val="clear" w:color="auto" w:fill="'.$bg.'"/>'
                .'</w:tcPr>'
                .'<w:p>'.($i === 1 ? '<w:pPr><w:jc w:val="center"/></w:pPr>' : '')
                .$this->run((string)$val, ['size'=>$header ? 9 : 8.5,'color'=>$color,'bold'=>$bold,'font'=>'Arial'])
                .'</w:p>'
                .'</w:tc>';
        }
        $xml .= '</w:tr>';
        $this->body_parts[] = $xml;
    }

    /* ── Table ──────────────────────────────────────────────── */
    public function openTable(): void {
        $this->in_table = true;
        $this->body_parts[] = '<w:tbl>'
            .'<w:tblPr>'
            .'<w:tblStyle w:val="TableGrid"/>'
            .'<w:tblW w:w="9026" w:type="dxa"/>'
            .'<w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:left w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:right w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'<w:insideV w:val="single" w:sz="4" w:color="D0D8E8"/>'
            .'</w:tblBorders>'
            .'</w:tblPr>';
    }

    public function addTableHeaderRow( array $cols ): void {
        $n    = count($cols);
        $wide = 9026;
        $col_w = (int)($wide / $n);
        $xml = '<w:tr>';
        foreach ($cols as $col) {
            $xml .= '<w:tc>'
                .'<w:tcPr>'
                .'<w:tcW w:w="'.$col_w.'" w:type="dxa"/>'
                .'<w:shd w:val="clear" w:color="auto" w:fill="FFFFFF"/>'
                .'</w:tcPr>'
                .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
                .$this->run($col, ['bold'=>true,'size'=>10,'color'=>'555555','font'=>'Arial'])
                .'</w:p>'
                .'</w:tc>';
        }
        $xml .= '</w:tr>';
        $this->body_parts[] = $xml;
    }

    public function addTableRow( array $cols, ?float $note = null ): void {
        static $row_idx = 0;
        $row_idx++;
        $bg   = 'FFFFFF';
        $n    = count($cols);
        $col_w = (int)(9026 / $n);
        $xml  = '<w:tr>';
        foreach ($cols as $i => $val) {
            // Color the note column
            $color = '333333';
            $bold  = false;
            if ($i === 2 && $note !== null) {
                $color = self::rgb_to_hex($note);
                $bold  = true;
            }
            $xml .= '<w:tc>'
                .'<w:tcPr>'
                .'<w:tcW w:w="'.$col_w.'" w:type="dxa"/>'
                .'<w:shd w:val="clear" w:color="auto" w:fill="'.$bg.'"/>'
                .'</w:tcPr>'
                .'<w:p>'
                .$this->run((string)$val, ['size'=>10,'color'=>$color,'bold'=>$bold,'font'=>'Arial'])
                .'</w:p>'
                .'</w:tc>';
        }
        $xml .= '</w:tr>';
        $this->body_parts[] = $xml;
    }

    public function closeTable(): void {
        $this->body_parts[] = '</w:tbl>';
        $this->in_table = false;
    }

    public function addSignatureLine(): void {
        // Signature box: date line + signature line
        $this->para(
            $this->ppr(['spacing_before'=>200,'spacing_after'=>60,'indent'=>200])
            .$this->run('Date : ___________________', ['size'=>11,'font'=>'Arial'])
        );
        $this->para(
            $this->ppr(['spacing_before'=>400,'spacing_after'=>60,'indent'=>200])
            .$this->run('Signature : ', ['size'=>11,'font'=>'Arial'])
            .$this->run('_________________________________________', ['size'=>11,'color'=>'CCCCCC','font'=>'Arial'])
        );
    }

    /* ── Build DOCX ─────────────────────────────────────────── */
    public function build(): string {
        $body_xml = implode("\n", $this->body_parts);

        // ── [Content_Types].xml
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
            .'</Types>';

        // ── _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';

        // ── word/_rels/document.xml.rels
        $doc_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>'
            .($this->image ? '<Relationship Id="'.$this->image['rid'].'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$this->image['name'].'"/>' : '')
            .'</Relationships>';

        // ── word/settings.xml
        $settings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:defaultTabStop w:val="720"/>'
            .'</w:settings>';

        // ── word/styles.xml (minimal)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'
            .'<w:rFonts w:ascii="Arial" w:hAnsi="Arial"/>'
            .'<w:sz w:val="22"/><w:szCs w:val="22"/>'
            .'</w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="table" w:styleId="TableGrid">'
            .'<w:name w:val="Table Grid"/>'
            .'<w:tblPr><w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="auto"/>'
            .'<w:left w:val="single" w:sz="4" w:color="auto"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="auto"/>'
            .'<w:right w:val="single" w:sz="4" w:color="auto"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="auto"/>'
            .'<w:insideV w:val="single" w:sz="4" w:color="auto"/>'
            .'</w:tblBorders></w:tblPr>'
            .'</w:style>'
            .'</w:styles>';

        // ── word/document.xml
        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document '.$ns.'>'
            .'<w:body>'
            .$body_xml
            .'<w:sectPr>'
            .'<w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="709" w:footer="709"/>'
            .'</w:sectPr>'
            .'</w:body>'
            .'</w:document>';

        // ── Build ZIP in memory
        $tmp = tempnam(sys_get_temp_dir(), 'logieval_docx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',           $ct);
        $zip->addFromString('_rels/.rels',                   $rels);
        $zip->addFromString('word/document.xml',             $document);
        $zip->addFromString('word/styles.xml',               $styles);
        $zip->addFromString('word/settings.xml',             $settings);
        $zip->addFromString('word/_rels/document.xml.rels',  $doc_rels);
        if ($this->image) {
            $zip->addFile($this->image['path'], 'word/media/'.$this->image['name']);
        }
        $zip->close();

        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data;
    }
}
