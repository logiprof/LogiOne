<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Objectifs évaluateurs</h1>
<?php
$t = LOGIEVAL_Database::tables();
$objectifs = array_values(array_filter(LOGIEVAL_Database::get_rows($t['objectifs'], [], 'ordre'), static fn($o) => ! in_array((int)$o['id'], LOGIEVAL_Demo::objective_ids(), true))); // 1.53.19 : sans la grille de démonstration
$total_max = LOGIEVAL_Database::get_total_max_points();
global $wpdb;

// Detail: show sub-criteria of one objective
$detail_obj = isset($_GET['obj_id']) ? intval($_GET['obj_id']) : 0;
if ($detail_obj) {
    $obj = LOGIEVAL_Database::get_row($t['objectifs'], ['id' => $detail_obj]);
    $scs = LOGIEVAL_Database::get_rows($t['sous_criteres'], ['objectif_id' => $detail_obj], 'ordre');
?>
<p><a href="<?= admin_url('admin.php?page=logieval-objectifs') ?>">&larr; Retour aux objectifs</a></p>
<div class="logieval-card" style="margin-bottom:20px;max-width:700px">
    <h3 style="margin-top:0"><?= esc_html($obj['libelle']) ?></h3>
    <p>
        Type : <strong><?= $obj['type']==='radio' ? '🔘 Un seul choix possible' : '☑ Plusieurs choix possibles' ?></strong>
        | Maître de classe seulement : <strong><?= $obj['maitre_seulement'] ? 'Oui' : 'Non' ?></strong>
    </p>
</div>

<button class="button button-primary" id="logieval-add-sc">+ Nouveau sous-critère</button>
<div id="logieval-sc-form" class="logieval-card logieval-hidden" style="margin-top:16px;max-width:700px">
    <input type="hidden" id="sc-id" value="0">
    <input type="hidden" id="sc-obj-id" value="<?= $detail_obj ?>">
    <table class="form-table">
        <tr><th>Lettre</th><td><input type="text" id="sc-lettre" class="small-text" maxlength="5" placeholder="A"></td></tr>
        <tr><th>Libellé</th><td><textarea id="sc-libelle" class="large-text" rows="3"></textarea></td></tr>
        <tr><th>Points</th><td><input type="number" id="sc-points" value="1" min="0" step="0.5" style="width:80px"></td></tr>
        <tr><th>Ordre</th><td><input type="number" id="sc-ordre" value="0" min="0" style="width:80px"></td></tr>
    </table>
    <p>
        <button class="button button-primary" id="logieval-save-sc">Enregistrer</button>
        <button class="button" id="logieval-cancel-sc">Annuler</button>
    </p>
</div>

<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:20px">
<thead><tr><th width="50">Lettre</th><th>Libellé</th><th width="80">Points</th><th width="120">Actions</th></tr></thead>
<tbody>
<?php foreach ($scs as $sc): ?>
<tr>
    <td><strong><?= esc_html($sc['lettre']) ?></strong></td>
    <td><?= esc_html($sc['libelle']) ?></td>
    <td><span class="logieval-badge logieval-badge-green"><?= number_format($sc['points'],1) ?> pt<?= $sc['points']>1?'s':'' ?></span></td>
    <td>
        <button class="button button-small logieval-edit-sc"
            data-id="<?= $sc['id'] ?>" data-lettre="<?= esc_attr($sc['lettre']) ?>"
            data-libelle="<?= esc_attr($sc['libelle']) ?>" data-points="<?= $sc['points'] ?>"
            data-ordre="<?= $sc['ordre'] ?>">Modifier</button>
        <button class="button button-small logieval-delete-sc" data-id="<?= $sc['id'] ?>">Supprimer</button>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?php } else { // LIST VIEW ?>

<p>Total de points maximum actuel : <strong style="color:#16A34A;font-size:1.2em"><?= number_format($total_max,1) ?> pts</strong></p>

<button class="button button-primary" id="logieval-add-obj">+ Nouvel objectif</button>
<div id="logieval-obj-form" class="logieval-card logieval-hidden" style="margin-top:16px;max-width:700px">
    <h3 id="logieval-obj-form-title" style="margin-top:0">Nouvel objectif</h3>
    <input type="hidden" id="obj-id" value="0">
    <table class="form-table">
        <tr><th>Libellé</th><td><textarea id="obj-libelle" class="large-text" rows="2"></textarea></td></tr>
        <tr><th>Type de saisie</th>
            <td>
                <label><input type="radio" name="obj-type" value="radio"> 🔘 Choix exclusif (A ou B ou C…)</label><br>
                <label><input type="radio" name="obj-type" value="checkbox" checked> ☑ Cases indépendantes (plusieurs cochables)</label>
            </td>
        </tr>
        <tr><th>Réservé au maître</th><td><input type="checkbox" id="obj-maitre"> <small>Seul le maître de classe peut évaluer cet objectif</small></td></tr>
        <tr><th>Ordre</th><td><input type="number" id="obj-ordre" value="0" min="0" style="width:80px"></td></tr>
        <tr><th>Actif</th><td><input type="checkbox" id="obj-actif" checked></td></tr>
    </table>
    <p>
        <button class="button button-primary" id="logieval-save-obj">Enregistrer</button>
        <button class="button" id="logieval-cancel-obj">Annuler</button>
    </p>
</div>

<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:20px">
<thead>
    <tr>
        <th width="40">#</th>
        <th>Libellé</th>
        <th width="130">Type</th>
        <th width="80">Pts max</th>
        <th width="90">Réservé</th>
        <th width="70">Statut</th>
        <th width="160">Actions</th>
    </tr>
</thead>
<tbody>
<?php foreach ($objectifs as $obj):
    $scs = LOGIEVAL_Database::get_rows($t['sous_criteres'], ['objectif_id' => $obj['id']]);
    $pts_max = 0;
    if ($obj['type'] === 'radio') {
        foreach ($scs as $sc) $pts_max = max($pts_max, (float)$sc['points']);
    } else {
        foreach ($scs as $sc) $pts_max += (float)$sc['points'];
    }
?>
<tr>
    <td><?= $obj['ordre'] ?></td>
    <td>
        <strong><?= esc_html(mb_substr($obj['libelle'],0,80)) ?><?= mb_strlen($obj['libelle'])>80?'…':'' ?></strong>
        <br><small><?= count($scs) ?> sous-critère<?= count($scs)>1?'s':'' ?></small>
    </td>
    <td><?= $obj['type']==='radio' ? '<span class="logieval-badge logieval-badge-blue">🔘 Un seul choix</span>' : '<span class="logieval-badge logieval-badge-orange">☑ Plusieurs choix</span>' ?></td>
    <td><strong><?= number_format($pts_max,1) ?></strong></td>
    <td><?= $obj['maitre_seulement'] ? '<span class="logieval-badge logieval-badge-blue">Maître</span>' : '<span class="logieval-badge logieval-badge-grey">Tous</span>' ?></td>
    <td><?= $obj['actif'] ? '<span class="logieval-badge logieval-badge-green">Actif</span>' : '<span class="logieval-badge logieval-badge-grey">Inactif</span>' ?></td>
    <td>
        <a href="?page=logieval-objectifs&obj_id=<?= $obj['id'] ?>" class="button button-small">Sous-critères</a>
        <button class="button button-small logieval-edit-obj"
            data-id="<?= $obj['id'] ?>"
            data-libelle="<?= esc_attr($obj['libelle']) ?>"
            data-type="<?= $obj['type'] ?>"
            data-maitre="<?= $obj['maitre_seulement'] ?>"
            data-ordre="<?= $obj['ordre'] ?>"
            data-actif="<?= $obj['actif'] ?>">Modifier</button>
        <button class="button button-small logieval-delete-obj" data-id="<?= $obj['id'] ?>">Supprimer</button>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<?php } ?>
</div>

<script>
jQuery(function($){
    // Objectives
    $('#logieval-add-obj').on('click',function(){
        $('#logieval-obj-form-title').text('Nouvel objectif');
        $('#obj-id').val(0);$('#obj-libelle').val('');$('#obj-ordre').val(0);
        $('input[name="obj-type"][value="checkbox"]').prop('checked',true);
        $('#obj-maitre,#obj-actif').prop('checked',false);$('#obj-actif').prop('checked',true);
        $('#logieval-obj-form').removeClass('logieval-hidden');
    });
    $('#logieval-cancel-obj').on('click',function(){$('#logieval-obj-form').addClass('logieval-hidden');});
    $(document).on('click','.logieval-edit-obj',function(){
        var d=$(this).data();
        $('#logieval-obj-form-title').text('Modifier objectif');
        $('#obj-id').val(d.id);$('#obj-libelle').val(d.libelle);$('#obj-ordre').val(d.ordre);
        $('input[name="obj-type"][value="'+d.type+'"]').prop('checked',true);
        $('#obj-maitre').prop('checked',d.maitre==1);$('#obj-actif').prop('checked',d.actif==1);
        $('#logieval-obj-form').removeClass('logieval-hidden');
        $('html,body').animate({scrollTop:$('#logieval-obj-form').offset().top-60});
    });
    $('#logieval-save-obj').on('click',function(){
        $.post(logievalAdmin.ajax_url,{
            action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'save_objectif',
            id:$('#obj-id').val(),libelle:$('#obj-libelle').val(),
            type:$('input[name="obj-type"]:checked').val(),
            maitre_seulement:$('#obj-maitre').is(':checked')?1:0,
            ordre:$('#obj-ordre').val(),actif:$('#obj-actif').is(':checked')?1:0
        },logievalAfter);
    });
    $(document).on('click','.logieval-delete-obj',function(){
        if(!confirm('Supprimer cet objectif et tous ses sous-critères ?'))return;
        $.post(logievalAdmin.ajax_url,{action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'delete_objectif',id:$(this).data('id')},logievalAfter);
    });

    // Sub-criteria
    $('#logieval-add-sc').on('click',function(){
        $('#sc-id').val(0);$('#sc-lettre,#sc-libelle').val('');$('#sc-points').val(1);$('#sc-ordre').val(0);
        $('#logieval-sc-form').removeClass('logieval-hidden');
    });
    $('#logieval-cancel-sc').on('click',function(){$('#logieval-sc-form').addClass('logieval-hidden');});
    $(document).on('click','.logieval-edit-sc',function(){
        var d=$(this).data();
        $('#sc-id').val(d.id);$('#sc-lettre').val(d.lettre);$('#sc-libelle').val(d.libelle);
        $('#sc-points').val(d.points);$('#sc-ordre').val(d.ordre);
        $('#logieval-sc-form').removeClass('logieval-hidden');
    });
    $('#logieval-save-sc').on('click',function(){
        $.post(logievalAdmin.ajax_url,{
            action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'save_sous_critere',
            id:$('#sc-id').val(),objectif_id:$('#sc-obj-id').val(),
            lettre:$('#sc-lettre').val(),libelle:$('#sc-libelle').val(),
            points:$('#sc-points').val(),ordre:$('#sc-ordre').val()
        },logievalAfter);
    });
    $(document).on('click','.logieval-delete-sc',function(){
        if(!confirm('Supprimer ce sous-critère ?'))return;
        $.post(logievalAdmin.ajax_url,{action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'delete_sous_critere',id:$(this).data('id')},logievalAfter);
    });
});
</script>
