<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** 1.53.6 : suivi d'un élève — toutes ses évaluations, toutes classes et années confondues. */
$t  = LOGIEVAL_Database::tables();
$el = LOGIEVAL_Database::get_row( $t['eleves'], ['id' => $suivi] );
$back = admin_url( 'admin.php?page=logieval-eleves' );
if ( ! $el ) { echo '<p>Élève introuvable.</p><p><a class="button" href="' . esc_url( $back ) . '">← Élèves</a></p>'; return; }
$cur   = LOGIEVAL_Database::get_row( $t['classes'], ['id' => (int) $el['classe_id']] );
$evals = LOGIEVAL_Database::student_evaluations( (int) $el['id'] );
$moves = get_option( 'logieval_student_moves', [] );
$moves = is_array( $moves ) ? ( $moves[ (int) $el['id'] ] ?? [] ) : [];
?>
<p><a class="button" href="<?= esc_url( $back ) ?>">← Élèves</a></p>
<h2 style="margin-top:8px">📈 Suivi de <?= esc_html( $el['prenom'] . ' ' . $el['nom'] ) ?></h2>
<p>Classe actuelle : <strong><?= esc_html( $cur ? LOGIEVAL_Database::class_label( $cur ) : '—' ) ?></strong><?= $el['num_eleve'] !== '' ? ' · n° ' . esc_html( $el['num_eleve'] ) : '' ?></p>
<p class="description" style="max-width:820px">Toutes les évaluations de cet élève, y compris celles faites dans une autre classe ou une année précédente. La classe indiquée est celle qu’il avait au moment de l’évaluation.</p>
<?php if ( ! $evals ) : ?>
<p><em>Aucune évaluation pour l’instant.</em></p>
<?php else : ?>
<table class="wp-list-table widefat striped" style="max-width:1100px">
<thead><tr><th>Date</th><th>Semestre</th><th>Classe à ce moment</th><th>Statut</th><th>Points</th><th>Note</th><th></th></tr></thead>
<tbody>
<?php foreach ( $evals as $ev ) :
    $cl   = LOGIEVAL_Database::get_row( $t['classes'], ['id' => (int) $ev['classe_id']] );
    $lbl  = $cl ? LOGIEVAL_Rollover::label_at( $cl, (string) $ev['date_creation'] ) : '—';
    $res  = LOGIEVAL_Calculator::full_results( (int) $ev['id'] );
    $r    = $res[ (int) $el['id'] ] ?? null;
    $closed = $ev['statut'] === 'cloturee';
?>
<tr>
    <td><?= esc_html( mysql2date( 'd.m.Y', $ev['date_creation'] ) ) ?></td>
    <td><?= esc_html( $ev['semestre'] ) ?></td>
    <td><?= esc_html( $lbl ) ?></td>
    <td><?= $closed ? '✅ Clôturée' : '⏳ En cours' ?></td>
    <td><?= $r && $r['pts'] !== null ? esc_html( rtrim( rtrim( number_format( (float) $r['pts'], 1, '.', '' ), '0' ), '.' ) . ' / ' . rtrim( rtrim( number_format( (float) $r['max'], 1, '.', '' ), '0' ), '.' ) ) : '—' ?></td>
    <td><strong><?= $r && $r['note'] !== null ? esc_html( number_format( (float) $r['note'], 1, '.', '' ) ) : '—' ?></strong><?= $closed ? '' : ' <small>(provisoire)</small>' ?></td>
    <td>
        <a class="button button-small" href="<?= esc_url( add_query_arg( ['page' => 'logieval-evaluations', 'eval_id' => (int) $ev['id']], admin_url( 'admin.php' ) ) ) ?>">Détail</a>
        <a class="button button-small" href="<?= esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=logieval_export&eval_id=' . (int) $ev['id'] . '&eleve_id=' . (int) $el['id'] ), 'logieval_export_' . (int) $ev['id'] ) ) ?>">⬇ Word</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<?php if ( $moves ) : ?>
<h3 style="margin-top:20px">Changements de classe</h3>
<ul style="list-style:disc;margin-left:18px">
<?php foreach ( $moves as $m ) :
    $f = LOGIEVAL_Database::get_row( $t['classes'], ['id' => (int) $m['from']] );
    $to = LOGIEVAL_Database::get_row( $t['classes'], ['id' => (int) $m['to']] ); ?>
    <li><?= esc_html( mysql2date( 'd.m.Y', $m['at'] ) ) ?> : <?= esc_html( $f ? LOGIEVAL_Database::class_label( $f ) : '?' ) ?> → <?= esc_html( $to ? LOGIEVAL_Database::class_label( $to ) : '?' ) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
