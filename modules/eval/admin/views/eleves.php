<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Élèves</h1>
<?php
$t = LOGIEVAL_Database::tables();
$classes = LOGIEVAL_Database::get_rows($t['classes'], [], 'nom');
$filter_classe = intval($_GET['classe_id'] ?? 0);
$eleves = $filter_classe
    ? LOGIEVAL_Database::get_rows($t['eleves'], ['classe_id' => $filter_classe], 'nom, prenom')
    : LOGIEVAL_Database::get_rows($t['eleves'], [], 'nom, prenom');
global $wpdb;
?>

<div style="margin-bottom:16px">
    <label>Filtrer par classe :
    <select id="filter-classe" onchange="location.href='?page=logieval-eleves&classe_id='+this.value">
        <option value="0">Toutes</option>
        <?php foreach ($classes as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= $filter_classe==$cl['id']?'selected':'' ?>><?= esc_html($cl['nom']) ?></option>
        <?php endforeach ?>
    </select></label>
</div>

<button class="button button-primary" id="logieval-add-eleve">+ Nouvel élève</button>

<div id="logieval-eleve-form" class="logieval-card logieval-hidden" style="margin-top:20px;max-width:500px">
    <h3 id="logieval-eleve-form-title">Nouvel élève</h3>
    <input type="hidden" id="eleve-id" value="0">
    <table class="form-table">
        <tr><th>Classe</th>
            <td><select id="eleve-classe">
                <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $filter_classe==$cl['id']?'selected':'' ?>><?= esc_html($cl['nom']) ?></option>
                <?php endforeach ?>
            </select></td>
        </tr>
        <tr><th>Prénom</th><td><input type="text" id="eleve-prenom" class="regular-text"></td></tr>
        <tr><th>Nom</th><td><input type="text" id="eleve-nom" class="regular-text"></td></tr>
        <tr><th>N° élève</th><td><input type="text" id="eleve-num" class="regular-text"></td></tr>
    </table>
    <p><button class="button button-primary" id="logieval-save-eleve">Enregistrer</button>
       <button class="button" id="logieval-cancel-eleve">Annuler</button></p>
</div>

<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:20px">
<thead><tr><th>Nom</th><th>Prénom</th><th>N° élève</th><th>Classe</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($eleves as $el):
    $classe_nom = $wpdb->get_var($wpdb->prepare("SELECT nom FROM {$t['classes']} WHERE id=%d", $el['classe_id']));
?>
<tr>
    <td><?= esc_html($el['nom']) ?></td>
    <td><?= esc_html($el['prenom']) ?></td>
    <td><?= esc_html($el['num_eleve']) ?></td>
    <td><?= esc_html($classe_nom) ?></td>
    <td>
        <button class="button button-small logieval-edit-eleve"
            data-id="<?= $el['id'] ?>" data-classe="<?= $el['classe_id'] ?>"
            data-prenom="<?= esc_attr($el['prenom']) ?>" data-nom="<?= esc_attr($el['nom']) ?>"
            data-num="<?= esc_attr($el['num_eleve']) ?>">Modifier</button>
        <button class="button button-small logieval-delete-eleve" data-id="<?= $el['id'] ?>">Supprimer</button>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>

<script>
jQuery(function($){
    $('#logieval-add-eleve').on('click', function(){
        $('#logieval-eleve-form-title').text('Nouvel élève');
        $('#eleve-id').val(0); $('#eleve-prenom,#eleve-nom,#eleve-num').val('');
        $('#logieval-eleve-form').removeClass('logieval-hidden');
    });
    $('#logieval-cancel-eleve').on('click', function(){ $('#logieval-eleve-form').addClass('logieval-hidden'); });
    $(document).on('click','.logieval-edit-eleve', function(){
        var d=$(this).data();
        $('#logieval-eleve-form-title').text('Modifier élève');
        $('#eleve-id').val(d.id); $('#eleve-classe').val(d.classe);
        $('#eleve-prenom').val(d.prenom); $('#eleve-nom').val(d.nom); $('#eleve-num').val(d.num);
        $('#logieval-eleve-form').removeClass('logieval-hidden');
    });
    $('#logieval-save-eleve').on('click', function(){
        $.post(logievalAdmin.ajax_url, {
            action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'save_eleve',
            id:$('#eleve-id').val(), classe_id:$('#eleve-classe').val(),
            prenom:$('#eleve-prenom').val(), nom:$('#eleve-nom').val(), num_eleve:$('#eleve-num').val()
        }, logievalAfter);
    });
    $(document).on('click','.logieval-delete-eleve', function(){
        if(!confirm('Supprimer cet élève ? Toutes ses réponses d’évaluation seront aussi supprimées définitivement.')) return;
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'delete_eleve',id:$(this).data('id')}, logievalAfter);
    });
});
</script>
