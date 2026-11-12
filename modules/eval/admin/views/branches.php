<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Branches de cours</h1>
<?php $branches = LOGIEVAL_Database::get_rows(LOGIEVAL_Database::t('branches'), [], 'libelle'); ?>
<button class="button button-primary" id="logieval-add-branche">+ Nouvelle branche</button>
<div id="logieval-branche-form" class="logieval-card logieval-hidden" style="margin-top:20px;max-width:400px">
    <h3 id="logieval-branche-form-title">Nouvelle branche</h3>
    <input type="hidden" id="branche-id" value="0">
    <table class="form-table">
        <tr><th>Libellé</th><td><input type="text" id="branche-libelle" class="regular-text"></td></tr>
        <tr><th>Active</th><td><input type="checkbox" id="branche-actif" checked></td></tr>
    </table>
    <p><button class="button button-primary" id="logieval-save-branche">Enregistrer</button>
       <button class="button" id="logieval-cancel-branche">Annuler</button></p>
</div>
<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:20px">
<thead><tr><th>Libellé</th><th>Statut</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($branches as $b): ?>
<tr>
    <td><?= esc_html($b['libelle']) ?></td>
    <td><?= $b['actif'] ? '<span class="logieval-badge logieval-badge-green">Active</span>' : '<span class="logieval-badge logieval-badge-grey">Inactive</span>' ?></td>
    <td>
        <button class="button button-small logieval-edit-branche" data-id="<?=$b['id']?>" data-libelle="<?=esc_attr($b['libelle'])?>" data-actif="<?=$b['actif']?>">Modifier</button>
        <button class="button button-small logieval-delete-branche" data-id="<?=$b['id']?>">Supprimer</button>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<script>
jQuery(function($){
    $('#logieval-add-branche').on('click', function(){
        $('#branche-id').val(0); $('#branche-libelle').val(''); $('#branche-actif').prop('checked',true);
        $('#logieval-branche-form').removeClass('logieval-hidden');
    });
    $('#logieval-cancel-branche').on('click', function(){ $('#logieval-branche-form').addClass('logieval-hidden'); });
    $(document).on('click','.logieval-edit-branche', function(){
        var d=$(this).data();
        $('#branche-id').val(d.id); $('#branche-libelle').val(d.libelle); $('#branche-actif').prop('checked', d.actif==1);
        $('#logieval-branche-form').removeClass('logieval-hidden');
    });
    $('#logieval-save-branche').on('click', function(){
        $.post(logievalAdmin.ajax_url, {
            action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'save_branche',
            id:$('#branche-id').val(), libelle:$('#branche-libelle').val(), actif:$('#branche-actif').is(':checked')?1:0
        }, logievalAfter);
    });
    $(document).on('click','.logieval-delete-branche', function(){
        if(!confirm('Supprimer ?')) return;
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'delete_branche',id:$(this).data('id')}, logievalAfter);
    });
});
</script>
