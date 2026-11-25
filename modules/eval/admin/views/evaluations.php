<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Évaluations</h1>
<?php
global $wpdb;
$t = LOGIEVAL_Database::tables();
$classes = LOGIEVAL_Rollover::without_archived(LOGIEVAL_Database::get_rows($t['classes'], [], 'nom')); // 1.53.0
$all_ens = LOGIEVAL_Database::get_rows($t['enseignants'], [], 'nom, prenom');

/* ══ DETAIL VIEW ══════════════════════════════════════════════ */
$detail_id = intval($_GET['eval_id'] ?? 0);
if ($detail_id) {
    $eval    = LOGIEVAL_Database::get_row($t['evaluations'], ['id' => $detail_id]);
    $classe  = $eval ? LOGIEVAL_Database::get_row($t['classes'], ['id' => $eval['classe_id']]) : null;

    if (!$eval || !$classe) {
        echo '<p class="notice notice-error">Évaluation introuvable.</p>';
    } else {
        $stats     = LOGIEVAL_Calculator::completion_stats($detail_id);
        $results   = LOGIEVAL_Calculator::full_results($detail_id);
        $grid      = LOGIEVAL_Calculator::grid($detail_id); // figée si l'évaluation est clôturée
        $objectifs = $grid['objectifs'];
        $total_max = (float) $grid['total_max'];
	        $classe_teachers = $wpdb->get_results($wpdb->prepare(
	            "SELECT e.id, e.prenom, e.nom
	             FROM {$t['enseignants']} e
	             INNER JOIN {$t['classe_enseignant']} ce ON ce.enseignant_id=e.id
	             WHERE ce.classe_id=%d AND e.actif=1
	             ORDER BY e.nom, e.prenom",
	            $eval['classe_id']
	        ), ARRAY_A) ?: [];
        $assigned_teacher_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT enseignant_id FROM {$t['eval_enseignant']} WHERE evaluation_id=%d",
            $detail_id
        )) ?: []);

        // Previous semester ref notes (use correct column: note_calculee)
        $refs_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT eleve_id, note_calculee FROM {$t['notes_ref']} WHERE evaluation_id_dest=%d",
            $detail_id
        ), ARRAY_A) ?: [];
        $ref_map = array_column($refs_raw, 'note_calculee', 'eleve_id');
?>

<p><a href="<?= admin_url('admin.php?page=logieval-evaluations') ?>">&larr; Retour à la liste</a></p>
<h2><?= esc_html($classe['nom']) ?> — <?= esc_html($eval['semestre']) ?></h2>
<p>
    Statut : <strong><?= $eval['statut'] === 'cloturee' ? '✅ Clôturée' : '🔄 Active' ?></strong>
    | Échéance : <?= date_i18n('d.m.Y', strtotime($eval['date_echeance'])) ?>
    | Total points max : <strong><?= number_format($total_max, 1) ?> pts</strong>
    <?php if ($eval['statut'] === 'cloturee'): ?>
    | <a class="button button-small" href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=logieval_export&eval_id='.$detail_id), 'logieval_export_'.$detail_id)) ?>">⬇ Export Word</a>
    <?php else: ?>
    | <button class="button button-small logieval-btn-close-eval" data-id="<?= $detail_id ?>">Clôturer</button>
    <?php endif ?>
</p>

<?php if ($eval['statut'] === 'active'): ?>
<div class="logieval-card" style="max-width:760px;margin:18px 0">
    <h3>Modifier l'évaluation</h3>
    <table class="form-table">
        <tr>
            <th>Date de remise requise</th>
            <td><input type="date" id="detail-eval-echeance" value="<?=esc_attr($eval['date_echeance'])?>"></td>
        </tr>
        <tr>
            <th>Enseignants attribués</th>
            <td>
                <?php foreach ($classe_teachers as $ens_row): ?>
                <label style="display:block;margin:4px 0">
                    <input type="checkbox" class="detail-eval-ens-check"
                           value="<?=(int)$ens_row['id']?>"
                           <?=in_array((int)$ens_row['id'], $assigned_teacher_ids, true) ? 'checked' : ''?>>
                    <?=esc_html($ens_row['nom'].' '.$ens_row['prenom'])?>
                </label>
                <?php endforeach ?>
                <?php if (empty($classe_teachers)): ?><em>Aucun enseignant attribué à cette classe.</em><?php endif ?>
                <p class="description">Les enseignants ajoutés recevront l'accès à cette évaluation. Un enseignant retiré ne sera plus pris en compte dans les résultats.</p>
            </td>
        </tr>
    </table>
    <p>
        <button class="button button-primary" id="logieval-update-detail-eval"
                data-id="<?=$detail_id?>"
                data-classe="<?=(int)$eval['classe_id']?>"
                data-semestre="<?=esc_attr($eval['semestre'])?>">
            Enregistrer les modifications
        </button>
        <span id="logieval-detail-eval-saved" style="display:none;color:green;margin-left:10px">✓ Modifié</span>
    </p>
</div>
<?php endif ?>

<!-- Teacher progress -->
<h3>Progression des enseignants</h3>
<table class="wp-list-table widefat fixed striped logieval-table">
<thead>
    <tr>
        <th>Enseignant</th>
        <th>Statut</th>
        <th>Réponses saisies</th>
        <th>Date de fin</th>
    </tr>
</thead>
<tbody>
<?php foreach ($stats as $s): ?>
<tr>
    <td><strong><?= esc_html($s['prenom'].' '.$s['nom']) ?></strong></td>
    <td>
        <?= $s['statut'] === 'termine'
            ? '<span class="logieval-badge logieval-badge-green">✓ Terminé</span>'
            : '<span class="logieval-badge logieval-badge-orange">En cours</span>' ?>
    </td>
    <td><?= (int)($s['reponses_saisies'] ?? 0) ?></td>
    <td><?= $s['date_fin'] ? date_i18n('d.m.Y H:i', strtotime($s['date_fin'])) : '—' ?></td>
</tr>
<?php endforeach ?>
<?php if (empty($stats)): ?>
<tr><td colspan="4"><em>Aucun enseignant assigné.</em></td></tr>
<?php endif ?>
</tbody>
</table>

<?php
// Pre-load all sub-criteria once (performance)
$all_scs_raw = $wpdb->get_results("SELECT * FROM {$t['sous_criteres']} ORDER BY objectif_id, ordre", ARRAY_A) ?: [];
$scs_by_obj = [];
foreach ($all_scs_raw as $sc) $scs_by_obj[$sc['objectif_id']][] = $sc;
?>
<!-- Results per student -->
<h3 style="margin-top:28px">Résultats par élève</h3>
<?php if (empty($results)): ?>
    <p><em>Aucun élève dans cette classe ou aucune réponse saisie.</em></p>
<?php else: ?>
<div style="overflow-x:auto">
<table class="wp-list-table widefat fixed striped logieval-table logieval-results-table">
<thead>
    <tr>
        <th style="min-width:160px">Élève</th>
        <?php foreach ($objectifs as $obj):
            $scs_h = $scs_by_obj[$obj['id']] ?? [];
            $pts_max = $obj['type'] === 'radio'
                ? ($scs_h ? max(array_column($scs_h,'points')) : 0)
                : array_sum(array_column($scs_h,'points'));
        ?>
        <th title="Max: <?= number_format($pts_max,1) ?> pts | <?= $obj['maitre_seulement'] ? 'Maître' : 'Tous' ?>">
            <?= esc_html(mb_substr($obj['libelle'], 0, 30)) ?><?= mb_strlen($obj['libelle']) > 30 ? '…' : '' ?>
        </th>
        <?php endforeach ?>
        <?php if (!empty($ref_map)): ?><th>S. préc.</th><?php endif ?>
        <th style="min-width:70px">Points</th>
        <th style="min-width:70px">Note /6</th>
        <th style="min-width:120px">Export</th>
    </tr>
</thead>
<tbody>
<?php foreach ($results as $eleve_id => $data):
    $note     = $data['note'];
    $avg_pts  = $data['avg_pts'] ?? $data['pts'] ?? null;
    $note_cls = $note === null ? '' : ($note < 3 ? 'logieval-note-red' : ($note < 4.5 ? 'logieval-note-orange' : 'logieval-note-green'));
?>
<tr>
    <td><strong><?= esc_html($data['eleve']['nom'].' '.$data['eleve']['prenom']) ?></strong></td>
    <?php foreach ($objectifs as $obj):
        $oid = (int)$obj['id'];
        $scs = $scs_by_obj[$oid] ?? [];
        if ($obj['type'] === 'radio') {
            $obj_avg = $data['details']['radio_'.$oid]['pts'] ?? 0;
        } else {
            $obj_avg = 0.0;
            foreach ($scs as $sc) {
                $vr = $data['details'][$sc['id']] ?? ['result' => 'abstention'];
                $obj_avg += (float)($vr['awarded_points'] ?? 0.0);
            }
        }
    ?>
    <td style="text-align:center">
        <?= $obj_avg !== null ? number_format($obj_avg, 2) : '—' ?>
    </td>
    <?php endforeach ?>
    <?php if (!empty($ref_map)): ?>
    <td class="logieval-note-prev"><?= isset($ref_map[$eleve_id]) ? number_format((float)$ref_map[$eleve_id], 1) : '—' ?></td>
    <?php endif ?>
    <td style="text-align:center">
        <?= $avg_pts !== null ? '<strong>'.number_format($avg_pts,2).'</strong>/'.number_format((float)($data['max'] ?? $total_max),2) : '—' ?>
    </td>
    <td class="<?= $note_cls ?>" style="text-align:center">
        <strong><?= $note !== null ? number_format($note, 1) : '—' ?></strong>
    </td>
    <td style="text-align:center">
        <a class="button button-small"
           href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=logieval_export&eval_id='.$detail_id.'&eleve_id='.(int)$eleve_id), 'logieval_export_'.$detail_id)) ?>">
            Export Word
        </a>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<?php endif ?>

<?php
    } // end if eval && classe
} else {
/* ══ LIST VIEW ════════════════════════════════════════════════ */
?>

<button class="button button-primary" id="logieval-add-eval" style="margin-bottom:20px">+ Nouvelle évaluation</button>

<div id="logieval-eval-form" class="logieval-card logieval-hidden" style="margin-bottom:20px;max-width:620px">
    <h3>Nouvelle évaluation</h3>
    <table class="form-table">
        <tr><th>Classe</th>
            <td><select id="eval-classe" onchange="loadClasseTeachers(this.value)">
                <option value="">— Choisir —</option>
                <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl['id'] ?>"><?= esc_html(LOGIEVAL_Database::class_label($cl)) ?></option>
                <?php endforeach ?>
            </select></td>
        </tr>
        <tr><th>Semestre</th><td><input type="text" id="eval-semestre" class="regular-text" placeholder="ex. Semestre 1 2024-2025"></td></tr>
        <tr><th>Date d'échéance</th><td><input type="date" id="eval-echeance"></td></tr>
        <tr><th>Reprendre notes préc.</th>
            <td><input type="checkbox" id="eval-import-prev">
            <small>Importer les notes du semestre précédent</small></td>
        </tr>
        <tr><th>Enseignants</th>
            <td>
                <div id="eval-ens-list"><em>Sélectionnez une classe d'abord</em></div>
                <small>Tous les enseignants de la classe sont sélectionnés par défaut.</small>
            </td>
        </tr>
        <tr><th>Notifications optionnelles</th>
            <td>
                <label style="display:block;margin:4px 0"><input type="checkbox" id="eval-mail-teacher-completed"> Informer les enseignants en attente lorsqu’un collègue termine</label>
                <label style="display:block;margin:4px 0"><input type="checkbox" id="eval-mail-all-completed"> Informer le maître de classe lorsque tous les enseignants ont terminé</label>
                <label style="display:block;margin:4px 0"><input type="checkbox" id="eval-mail-closure-maitre"> Informer le maître de classe lorsque l’évaluation est clôturée</label>
                <small>L’attribution initiale, le rappel avant échéance et la relance du dernier enseignant en attente sont toujours envoyés.</small>
            </td>
        </tr>
    </table>
    <p>
        <button class="button button-primary" id="logieval-save-eval">Créer & notifier</button>
        <button class="button" id="logieval-cancel-eval">Annuler</button>
    </p>
</div>

<?php
$evals = $wpdb->get_results(
    "SELECT ev.*, cl.nom AS classe_nom,
            COUNT(DISTINCT ee.id)        AS nb_ens,
            SUM(ee.statut='termine')     AS nb_done
     FROM {$t['evaluations']} ev
     INNER JOIN {$t['classes']} cl ON cl.id = ev.classe_id
     LEFT JOIN  {$t['eval_enseignant']} ee ON ee.evaluation_id = ev.id
     GROUP BY ev.id
     ORDER BY ev.date_creation DESC",
    ARRAY_A
) ?: [];
?>
<table class="wp-list-table widefat fixed striped logieval-table">
<thead>
    <tr>
        <th>Classe</th><th>Semestre</th><th>Créée le</th>
        <th>Échéance</th><th>Progression</th><th>Statut</th><th>Actions</th>
    </tr>
</thead>
<tbody>
<?php if (empty($evals)): ?>
<tr><td colspan="7"><em>Aucune évaluation créée.</em></td></tr>
<?php endif ?>
<?php foreach ($evals as $ev):
    $pct = $ev['nb_ens'] > 0 ? round(($ev['nb_done'] / $ev['nb_ens']) * 100) : 0;
?>
<tr>
    <td><?= esc_html($ev['classe_nom']) ?></td>
    <td><?= esc_html($ev['semestre']) ?></td>
    <td><?= date_i18n('d.m.Y', strtotime($ev['date_creation'])) ?></td>
    <td><?= date_i18n('d.m.Y', strtotime($ev['date_echeance'])) ?></td>
    <td>
        <div class="logieval-progress-bar">
            <div class="logieval-progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
        <small><?= (int)$ev['nb_done'] ?>/<?= (int)$ev['nb_ens'] ?></small>
    </td>
    <td>
        <?= $ev['statut'] === 'cloturee'
            ? '<span class="logieval-badge logieval-badge-green">Clôturée</span>'
            : '<span class="logieval-badge logieval-badge-blue">Active</span>' ?>
    </td>
    <td>
        <a class="button button-small" href="?page=logieval-evaluations&eval_id=<?= $ev['id'] ?>">Détail</a>
        <?php if ($ev['statut'] === 'active'): ?>
        <button class="button button-small logieval-btn-close-eval" data-id="<?= $ev['id'] ?>">Clôturer</button>
        <?php endif ?>
        <?php if ($ev['statut'] === 'cloturee'): ?>
        <a class="button button-small" href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=logieval_export&eval_id='.$ev['id']), 'logieval_export_'.(int)$ev['id'])) ?>">Export Word</a>
        <?php endif ?>
        <button class="button button-small logieval-delete-eval" data-id="<?= $ev['id'] ?>">Supprimer</button>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?php } // end list view ?>
</div><!-- /wrap -->

<script>
jQuery(function($){
    $('#logieval-add-eval').on('click', function(){
        $('#logieval-eval-form').removeClass('logieval-hidden');
    });
    $('#logieval-cancel-eval').on('click', function(){
        $('#logieval-eval-form').addClass('logieval-hidden');
    });
    $('#logieval-save-eval').on('click', function(){
        var btn = $(this);
        if (btn.prop('disabled')) return; // évite la double création sur double-clic
        btn.prop('disabled', true);
        var ens_ids = [];
        $('input.eval-ens-check:checked').each(function(){ ens_ids.push($(this).val()); });
        $.post(logievalAdmin.ajax_url, {
            action: 'logieval_admin', nonce: logievalAdmin.nonce, sub_action: 'save_evaluation',
            classe_id:       $('#eval-classe').val(),
            semestre:        $('#eval-semestre').val(),
            date_echeance:   $('#eval-echeance').val(),
            enseignant_ids:  ens_ids,
            import_previous: $('#eval-import-prev').is(':checked') ? 1 : 0,
            mail_teacher_completed: $('#eval-mail-teacher-completed').is(':checked') ? 1 : 0,
            mail_all_completed:     $('#eval-mail-all-completed').is(':checked') ? 1 : 0,
            mail_closure_maitre:    $('#eval-mail-closure-maitre').is(':checked') ? 1 : 0
        }, function(r){
            if (r.success) location.reload();
            else { btn.prop('disabled', false); alert('Erreur : ' + ((r.data ? r.data.message : '') ? r.data.message : 'inconnue')); }
        }).fail(function(){ btn.prop('disabled', false); });
    });
    $(document).on('click', '.logieval-btn-close-eval', function(){
        if (!confirm('Clôturer définitivement cette évaluation ?')) return;
        $.post(logievalAdmin.ajax_url, {
            action: 'logieval_admin', nonce: logievalAdmin.nonce,
            sub_action: 'close_evaluation', id: $(this).data('id')
        }, logievalAfter);
    });
    $('#logieval-update-detail-eval').on('click', function(){
        var ens_ids = [];
        $('.detail-eval-ens-check:checked').each(function(){ ens_ids.push($(this).val()); });
        if (!ens_ids.length) {
            alert('Sélectionnez au moins un enseignant.');
            return;
        }
        $.post(logievalAdmin.ajax_url, {
            action: 'logieval_admin',
            nonce: logievalAdmin.nonce,
            sub_action: 'save_evaluation',
            id: $(this).data('id'),
            classe_id: $(this).data('classe'),
            semestre: $(this).data('semestre'),
            date_echeance: $('#detail-eval-echeance').val(),
            enseignant_ids: ens_ids
        }, function(r){
            if (r.success) {
                $('#logieval-detail-eval-saved').fadeIn().delay(1600).fadeOut();
                setTimeout(function(){ location.reload(); }, 450);
            } else {
                alert('Erreur : ' + ((r.data ? r.data.message : '') ? r.data.message : 'inconnue'));
            }
        });
    });
    $(document).on('click', '.logieval-delete-eval', function(){
        if (!confirm('Supprimer cette évaluation et toutes ses réponses ?')) return;
        $.post(logievalAdmin.ajax_url, {
            action: 'logieval_admin', nonce: logievalAdmin.nonce,
            sub_action: 'delete_evaluation', id: $(this).data('id')
        }, logievalAfter);
    });
});

function loadClasseTeachers(classe_id) {
    if (!classe_id) {
        jQuery('#eval-ens-list').html('<em>Sélectionnez une classe d\'abord</em>');
        return;
    }
    jQuery.post(logievalAdmin.ajax_url, {
        action: 'logieval_admin', nonce: logievalAdmin.nonce,
        sub_action: 'get_classe_teachers', classe_id: classe_id
    }, function(r){
        if (!r.success) return;
        var html = '', off = 0;
        r.data.forEach(function(e){
            var on = parseInt(e.actif, 10) === 1; if (!on) off++;
            html += '<label style="display:block;margin:4px 0'+(on?'':';opacity:.55')+'"><input type="checkbox" class="eval-ens-check" value="'+parseInt(e.id, 10)+'" '+(on?'checked':'disabled')+'> '+escHtml(e.nom)+' '+escHtml(e.prenom)+(on?'':' <small>(compte inactif)</small>')+'</label>';
        });
        if (off) html += '<p style="color:#92400E;margin:6px 0 0">⚠ '+off+' enseignant(s) de cette classe ne sont pas encore activés : complétez leur e-mail puis activez-les dans <a href="admin.php?page=logieval-enseignants">LogiEval › Enseignants</a>. Ils pourront ensuite être ajoutés à l’évaluation.</p>';
        jQuery('#eval-ens-list').html(html || '<em>Aucun enseignant dans cette classe</em>');
    });
}

function escHtml(str) {
    return jQuery('<div>').text(str || '').html();
}
</script>
