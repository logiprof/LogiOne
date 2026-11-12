<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LOGIEVAL_Calculator {

    public static function round_half( float $v ): float { return round($v*2)/2; }
    public static function clamp( float $v ): float { return max(1.0, min(6.0,$v)); }

    /**
     * Formula: note = (pts_obtenus / total_max) * 5 + 1, rounded to 0.5
     */
    /**
     * LogiOne 1.28 : grille (objectifs actifs, sous-critères, maximum) d'une évaluation.
     * Évaluation clôturée : la grille est figée à la clôture (option logieval_grid_{id}) ; une modification
     * ultérieure des objectifs ou des points ne change plus ses notes. Les évaluations déjà clôturées
     * sans grille figée sont figées à la première lecture (valeurs actuelles).
     *
     * @return array{objectifs:array, sous_by_obj:array, total_max:float, frozen:bool}
     */
    public static function grid( int $eval_id = 0 ): array {
        if ( $eval_id ) {
            $eval = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('evaluations'), ['id' => $eval_id] );
            if ( $eval && $eval['statut'] === 'cloturee' ) {
                $saved = get_option( 'logieval_grid_' . $eval_id );
                if ( is_array( $saved ) && isset( $saved['objectifs'], $saved['sous_by_obj'] ) ) {
                    $saved['total_max'] = (float) ( $saved['total_max'] ?? 0 );
                    $saved['frozen']    = true;
                    $saved['rule']      = (int) ( $saved['rule'] ?? 1 );
                    return $saved;
                }
                // Évaluation clôturée avant LogiOne 1.28 : figée telle quelle, avec l'ancienne règle de calcul,
                // pour que ses notes restent exactement celles déjà distribuées.
                return self::freeze_grid( $eval_id, 1 );
            }
        }
        return self::live_grid() + ['frozen' => false, 'rule' => 2];
    }

    private static function live_grid(): array {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        $objectifs = LOGIEVAL_Database::get_rows( $t['objectifs'], ['actif' => 1], 'ordre' );
        $all_sc    = $wpdb->get_results( "SELECT * FROM {$t['sous_criteres']} ORDER BY objectif_id,ordre", ARRAY_A ) ?: [];
        $sous_by_obj = [];
        foreach ( $all_sc as $sc ) $sous_by_obj[ $sc['objectif_id'] ][] = $sc;
        return ['objectifs' => $objectifs, 'sous_by_obj' => $sous_by_obj, 'total_max' => LOGIEVAL_Database::get_total_max_points()];
    }

    /** Fige la grille d'une évaluation (appelé à chaque clôture). */
    public static function freeze_grid( int $eval_id, int $rule = 2 ): array {
        $grid = self::live_grid() + ['rule' => $rule];
        update_option( 'logieval_grid_' . $eval_id, $grid, false );
        return $grid + ['frozen' => true];
    }

    /** Réouverture : la grille redevient celle du moment. */
    public static function unfreeze_grid( int $eval_id ): void {
        delete_option( 'logieval_grid_' . $eval_id );
    }

    /** Maximum d'un objectif (choix unique : meilleur choix ; Oui/Non : somme des sous-critères). */
    public static function objective_max( array $obj, array $scs ): float {
        if ( $obj['type'] === 'radio' ) {
            return $scs ? (float) max( array_map( 'floatval', array_column( $scs, 'points' ) ) ) : 0.0;
        }
        return (float) array_sum( array_map( 'floatval', array_column( $scs, 'points' ) ) );
    }

    public static function points_to_note( float $pts, float $total_max ): float {
        if ($total_max <= 0) return 1.0;
        return self::clamp(self::round_half(($pts / $total_max) * 5 + 1));
    }

    /**
     * Load all votes indexed [eleve_id][enseignant_id][sous_critere_id] => 'oui'|'non'|'abstention'
     */
    public static function get_votes( int $eval_id ): array {
        global $wpdb;
        $t    = LOGIEVAL_Database::t('reponses');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, enseignant_id, sous_critere_id, vote
             FROM {$t} WHERE evaluation_id=%d AND vote_set=1", $eval_id
        ), ARRAY_A) ?: [];
        $idx = [];
        foreach ($rows as $r) {
            $idx[$r['eleve_id']][$r['enseignant_id']][$r['sous_critere_id']] = $r['vote'];
        }
        return $idx;
    }

    /**
     * Load non-evaluable objectives indexed [eleve_id][enseignant_id][objectif_id] => true.
     */
    public static function get_non_evaluables( int $eval_id ): array {
        global $wpdb;
        $t = LOGIEVAL_Database::t('non_evaluables');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, enseignant_id, objectif_id FROM {$t} WHERE evaluation_id=%d",
            $eval_id
        ), ARRAY_A) ?: [];
        $idx = [];
        foreach ($rows as $r) {
            $idx[$r['eleve_id']][$r['enseignant_id']][$r['objectif_id']] = true;
        }
        return $idx;
    }

    /**
     * Load legacy arbitration decisions [eleve_id][sous_critere_id] => 1|0.
     * Legacy arbitration decisions are kept for compatibility with old data.
     */
    public static function get_arbitrages( int $eval_id ): array {
        global $wpdb;
        $t    = LOGIEVAL_Database::t('arbitrages');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, sous_critere_id, decision FROM {$t} WHERE evaluation_id=%d", $eval_id
        ), ARRAY_A) ?: [];
        $idx = [];
        foreach ($rows as $r) {
            $idx[$r['eleve_id']][$r['sous_critere_id']] = (int)$r['decision'];
        }
        return $idx;
    }

    /**
     * Determine the outcome for one (eleve, sous_critere) across all teachers.
     *
     * Returns:
     *   ['result' => 'oui'|'non'|'egalite'|'abstention',
     *    'oui' => int, 'non' => int, 'total_votants' => int,
     *    'arbitrage' => 1|0|null, 'ratio_oui' => float]
     */
    public static function vote_result(
        int $eleve_id, int $sc_id,
        array $votes,          // [eleve][ens][sc] => vote
        array $arbitrages,     // [eleve][sc] => decision
        array $enseignant_ids, // all teachers assigned
        array $excluded_enseignant_ids = []
    ): array {
        $nb_oui = 0;
        $nb_non = 0;
        foreach ($enseignant_ids as $eid) {
            if (in_array((int)$eid, $excluded_enseignant_ids, true)) continue;
            $v = $votes[$eleve_id][$eid][$sc_id] ?? 'abstention';
            if ($v === 'oui') $nb_oui++;
            elseif ($v === 'non') $nb_non++;
        }
        $total = $nb_oui + $nb_non;
        $ratio = $total > 0 ? $nb_oui / $total : 0.0;
        if ($total === 0) return ['result'=>'abstention','oui'=>0,'non'=>0,'total_votants'=>0,'arbitrage'=>null,'ratio_oui'=>0.0];
        if ($nb_oui > $nb_non) return ['result'=>'oui', 'oui'=>$nb_oui,'non'=>$nb_non,'total_votants'=>$total,'arbitrage'=>null,'ratio_oui'=>$ratio];
        if ($nb_non > $nb_oui) return ['result'=>'non', 'oui'=>$nb_oui,'non'=>$nb_non,'total_votants'=>$total,'arbitrage'=>null,'ratio_oui'=>$ratio];
        // A tie corresponds to a 0.5 ratio for proportional criteria.
        return ['result'=>'egalite','oui'=>$nb_oui,'non'=>$nb_non,'total_votants'=>$total,'arbitrage'=>null,'ratio_oui'=>$ratio,'is_tie'=>true];
    }

    /**
     * Compute final points for one student.
     * Non-master objectives award points proportionally: points * oui / (oui + non).
     * Abstentions and teachers who marked the objective non-evaluable are excluded.
     * Evaluations created before proportional scoring keep the legacy majority mode.
     * Master-only objectives keep their original full-point / half-point logic.
     *
     * Returns ['pts'=>float, 'details'=>[sc_id=>vote_result], 'ties'=>[sc_id,...]]
     */
    public static function compute_student_points(
        int $eleve_id,
        array $objectifs,
        array $sous_by_obj,
        array $votes,
        array $arbitrages,
        array $enseignant_ids,
        array $non_evaluables = [],
        string $scoring_mode = 'majoritaire'
    ): array {
        $pts     = 0.0;
        $details = [];
        $ties    = [];
        // LogiOne 1.28 : ce qui n'a été évalué par personne (tous « non évaluable », abstention ou pas de réponse)
        // ne compte ni dans les points ni dans le maximum de l'élève ; sans aucune réponse, pas de note.
        $max       = 0.0;
        $excluded  = 0.0;
        $evaluated = false;

        foreach ($objectifs as $obj) {
            $max += self::objective_max( $obj, $sous_by_obj[(int) $obj['id']] ?? [] );
            $oid = (int)$obj['id'];
            $scs = $sous_by_obj[$oid] ?? [];
            $eligible_teacher_ids = array_values(array_filter(
                array_map('intval', $enseignant_ids),
                fn($teacher_id) => empty($non_evaluables[$eleve_id][$teacher_id][$oid])
            ));
            $is_master_only = !empty($obj['maitre_seulement']);
            $use_proportional = !$is_master_only && $scoring_mode === 'proportionnel';

            if ($obj['type'] === 'radio') {
                if (empty($scs)) {
                    $details['radio_'.$oid] = ['pts'=>0,'tally'=>[]];
                    continue;
                }
                $radio_max = self::objective_max( $obj, $scs );
                // For radio: count how many teachers picked each SC
                $tally = [];
                foreach ($scs as $sc) $tally[$sc['id']] = 0;
                foreach ($eligible_teacher_ids as $eid) {
                    $selected_sc_id = 0;
                    foreach ($scs as $sc) {
                        $v = $votes[$eleve_id][$eid][$sc['id']] ?? 'abstention';
                        if ($v === 'oui') {
                            $selected_sc_id = (int)$sc['id'];
                            break;
                        }
                    }
                    if ($selected_sc_id) $tally[$selected_sc_id]++;
                }
                if (array_sum($tally) === 0) {
                    $excluded += $radio_max;
                } else {
                    $evaluated = true;
                }
                // Non-master radio objectives use the average value selected by eligible teachers.
                if ($use_proportional) {
                    $voters = array_sum($tally);
                    $weighted_points = 0.0;
                    foreach ($scs as $sc) {
                        $weighted_points += ((float)$sc['points']) * (int)($tally[$sc['id']] ?? 0);
                    }
                    $winner_pts = $voters > 0 ? $weighted_points / $voters : 0.0;
                    $pts += $winner_pts;
                    $details['radio_'.$oid] = ['pts'=>$winner_pts,'tally'=>$tally,'total_votants'=>$voters];
                    continue;
                }

                // Master-only objectives and legacy evaluations keep the winner logic.
                arsort($tally);
                $sc_ids = array_keys($tally);
                $winner_id = $sc_ids[0];
                $winner_pts = 0;
                // Check if there's a tie at the top
                $top_count = reset($tally);
                $tied_scs  = array_keys(array_filter($tally, fn($c) => $c === $top_count));
                if ($top_count > 0 && count($tied_scs) === 1) {
                    foreach ($scs as $sc) {
                        if ((int)$sc['id'] === (int)$winner_id) { $winner_pts = (float)$sc['points']; break; }
                    }
                } elseif (count($tied_scs) > 1 && $top_count > 0) {
                    $highest_tied_pts = 0.0;
                    foreach ($scs as $sc) {
                        if (in_array((int)$sc['id'], array_map('intval', $tied_scs), true)) {
                            $highest_tied_pts = max($highest_tied_pts, (float)$sc['points']);
                        }
                    }
                    $winner_pts = $highest_tied_pts / 2;
                }
                $pts += $winner_pts;
                $details['radio_'.$oid] = ['pts'=>$winner_pts,'tally'=>$tally];

            } else {
                // Checkbox: proportional score per SC only for new non-master evaluations.
                foreach ($scs as $sc) {
                    $sc_id = (int)$sc['id'];
                    $vr    = self::vote_result($eleve_id, $sc_id, $votes, $arbitrages, $eligible_teacher_ids);
                    $awarded = $use_proportional
                        ? ((float)$sc['points']) * (float)($vr['ratio_oui'] ?? 0.0)
                        : ($vr['result'] === 'oui' ? (float)$sc['points'] : ($vr['result'] === 'egalite' ? ((float)$sc['points']) / 2 : 0.0));
                    $vr['awarded_points'] = $awarded;
                    $details[$sc_id] = $vr;
                    $pts += $awarded;
                    if ((int) ($vr['total_votants'] ?? 0) === 0) {
                        $excluded += (float) $sc['points'];
                    } else {
                        $evaluated = true;
                    }
                }
            }
        }
        return [
            'pts'       => $evaluated ? $pts : null,
            'raw_pts'   => $pts,
            'max'       => max( 0.0, $max - $excluded ),
            'evaluated' => $evaluated,
            'details'   => $details,
            'ties'      => $ties,
        ];
    }

    /**
     * Full results for all students in an evaluation.
     */
    public static function full_results( int $eval_id ): array {
        global $wpdb;
        $t    = LOGIEVAL_Database::tables();
        $eval = LOGIEVAL_Database::get_row($t['evaluations'], ['id'=>$eval_id]);
        if (!$eval) return [];

        $eleves    = LOGIEVAL_Database::get_rows($t['eleves'], ['classe_id'=>$eval['classe_id']], 'nom,prenom');
        $grid        = self::grid( $eval_id );
        $objectifs   = $grid['objectifs'];
        $sous_by_obj = $grid['sous_by_obj'];

        $status_filter = $eval['statut'] === 'cloturee' ? " AND statut='termine'" : '';
        $ens_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d{$status_filter}", $eval_id
        ), ARRAY_A) ?: [];
        $ens_ids  = array_column($ens_rows, 'enseignant_id');

        $votes      = self::get_votes($eval_id);
        $arbitrages = self::get_arbitrages($eval_id);
        $non_evaluables = self::get_non_evaluables($eval_id);

        $results = [];
        foreach ($eleves as $el) {
            $eid2 = (int)$el['id'];
            $computed = self::compute_student_points(
                $eid2,
                $objectifs,
                $sous_by_obj,
                $votes,
                $arbitrages,
                $ens_ids,
                $non_evaluables,
                $eval['mode_calcul'] ?? 'majoritaire'
            );
            if ( (int) $grid['rule'] < 2 ) {
                // Ancienne règle (évaluations clôturées avant 1.28) : maximum complet, note toujours calculée.
                $computed['pts'] = $computed['raw_pts'];
                $computed['max'] = (float) $grid['total_max'];
            }
            $note = $computed['pts'] !== null ? self::points_to_note($computed['pts'], $computed['max']) : null;
            $results[$eid2] = [
                'eleve'   => $el,
                'max'     => $computed['max'],
                'pts'     => $computed['pts'],
                'avg_pts' => $computed['pts'],
                'note'    => $note,
                'details' => $computed['details'],
                'ties'    => $computed['ties'],
            ];
        }
        return $results;
    }

    /**
     * Completion stats per teacher.
     */
    public static function completion_stats( int $eval_id ): array {
        global $wpdb;
        $t = LOGIEVAL_Database::tables();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.prenom, e.nom, ee.statut, ee.date_fin,
                    COUNT(DISTINCT CASE WHEN r.vote_set=1 THEN CONCAT(r.eleve_id,'_',r.sous_critere_id) END) AS reponses_saisies
             FROM {$t['eval_enseignant']} ee
             INNER JOIN {$t['enseignants']} e ON e.id=ee.enseignant_id
             LEFT JOIN {$t['reponses']} r ON r.evaluation_id=ee.evaluation_id AND r.enseignant_id=ee.enseignant_id
             WHERE ee.evaluation_id=%d
             GROUP BY e.id, ee.statut, ee.date_fin", $eval_id
        ), ARRAY_A) ?: [];
    }

    // Legacy: kept for backward compat calls
    public static function get_reponses( int $eval_id ): array {
        return self::get_votes($eval_id);
    }
}
