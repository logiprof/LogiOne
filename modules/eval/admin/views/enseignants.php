<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Enseignants</h1>
<?php $ens_list = LOGIEVAL_Database::get_rows(LOGIEVAL_Database::t('enseignants'), [], 'nom, prenom'); ?>

<button class="button button-primary" id="logieval-add-ens">+ Nouvel enseignant</button>
<p class="description" style="margin-top:8px">
    Les enseignants importés ou créés ici restent inactifs tant que vous ne cliquez pas sur
    <strong>Activer et envoyer l’e-mail</strong>. L’identifiant existe déjà, mais il ne permet pas la connexion avant activation.
</p>

<div id="logieval-ens-form" class="logieval-card logieval-hidden" style="margin-top:20px;max-width:500px">
    <h3 id="logieval-ens-form-title">Nouvel enseignant</h3>
    <input type="hidden" id="ens-id" value="0">
    <table class="form-table">
        <tr><th>Prénom</th><td><input type="text" id="ens-prenom" class="regular-text"></td></tr>
        <tr><th>Nom</th><td><input type="text" id="ens-nom" class="regular-text"></td></tr>
        <tr><th>E-mail</th><td><input type="email" id="ens-email" class="regular-text"></td></tr>
    </table>
    <p><button class="button button-primary" id="logieval-save-ens">Enregistrer</button>
       <button class="button" id="logieval-cancel-ens">Annuler</button></p>
</div>

<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:20px">
<thead>
    <tr><th>Nom</th><th>E-mail</th><th>Statut</th><th>Identifiant unique</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($ens_list as $e): ?>
<?php $is_active = !empty($e['actif']); ?>
<tr>
    <td><strong><?= esc_html($e['nom'].' '.$e['prenom']) ?></strong></td>
    <td><?= esc_html($e['email']) ?></td>
    <td>
        <?php if ($is_active): ?>
            <span style="display:inline-block;background:#DCFCE7;color:#166534;border-radius:999px;padding:3px 10px;font-weight:600">Actif</span>
        <?php else: ?>
            <span style="display:inline-block;background:#F1F5F9;color:#475569;border-radius:999px;padding:3px 10px;font-weight:600">Inactif</span>
        <?php endif ?>
    </td>
    <td>
        <code id="ident-<?= $e['id'] ?>"><?= esc_html($e['identifiant_unique']) ?></code>
        <button class="button button-small logieval-regen" data-id="<?= $e['id'] ?>" title="Régénérer">↺</button>
    </td>
    <td>
        <button class="button button-small logieval-edit-ens"
            data-id="<?= $e['id'] ?>"
            data-prenom="<?= esc_attr($e['prenom']) ?>"
            data-nom="<?= esc_attr($e['nom']) ?>"
            data-email="<?= esc_attr($e['email']) ?>">Modifier</button>
        <?php if ($is_active): ?>
            <button class="button button-small logieval-deactivate-ens" data-id="<?= $e['id'] ?>">Désactiver</button>
        <?php else: ?>
            <button class="button button-primary button-small logieval-activate-ens" data-id="<?= $e['id'] ?>">Activer et envoyer l’e-mail</button>
        <?php endif ?>
        <button class="button button-small logieval-delete-ens" data-id="<?= $e['id'] ?>">Supprimer</button>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>

<script>
jQuery(function($){
    $('#logieval-add-ens').on('click', function(){
        $('#logieval-ens-form-title').text('Nouvel enseignant');
        $('#ens-id').val(0); $('#ens-prenom,#ens-nom,#ens-email').val('');
        $('#logieval-ens-form').removeClass('logieval-hidden');
    });
    $('#logieval-cancel-ens').on('click', function(){ $('#logieval-ens-form').addClass('logieval-hidden'); });

    $(document).on('click','.logieval-edit-ens', function(){
        var d=$(this).data();
        $('#logieval-ens-form-title').text('Modifier enseignant');
        $('#ens-id').val(d.id); $('#ens-prenom').val(d.prenom); $('#ens-nom').val(d.nom); $('#ens-email').val(d.email);
        $('#logieval-ens-form').removeClass('logieval-hidden'); $('html,body').animate({scrollTop:$('#logieval-ens-form').offset().top-60});
    });

    $('#logieval-save-ens').on('click', function(){
        $.post(logievalAdmin.ajax_url, {
            action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'save_enseignant',
            id:$('#ens-id').val(), prenom:$('#ens-prenom').val(), nom:$('#ens-nom').val(), email:$('#ens-email').val()
        }, logievalAfter);
    });

    $(document).on('click','.logieval-delete-ens', function(){
        if(!confirm('Supprimer cet enseignant ?')) return;
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'delete_enseignant',id:$(this).data('id')}, logievalAfter);
    });

    $(document).on('click','.logieval-regen', function(){
        if(!confirm('Régénérer l\'identifiant de cet enseignant ? L\'ancien ne fonctionnera plus.')) return;
        var id=$(this).data('id');
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'regen_ident',id:id}, function(r){
            if(r.success) $('#ident-'+id).text(r.data.identifiant);
        });
    });

    $(document).on('click','.logieval-activate-ens', function(){
        if(!confirm('Activer cet enseignant et lui envoyer son e-mail d’accès LogiEval ?')) return;
        var btn=$(this), id=btn.data('id');
        btn.prop('disabled',true).text('Activation…');
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'activate_enseignant', id:id}, function(r){
            if(r.success) {
                if (r.data && r.data.email_sent === 0) alert('Enseignant activé, mais l’e-mail d’accès n’a PAS pu être envoyé. Communiquez-lui son identifiant et le lien de connexion manuellement, ou vérifiez la configuration e-mail du site.');
                location.reload();
            }
            else { alert((r.data ? r.data.message : '') ? r.data.message : 'Erreur lors de l’activation.'); btn.prop('disabled',false).text('Activer et envoyer l’e-mail'); }
        });
    });

    $(document).on('click','.logieval-deactivate-ens', function(){
        if(!confirm('Désactiver cet enseignant ? Il ne pourra plus se connecter tant que son profil n’est pas réactivé.')) return;
        var btn=$(this), id=btn.data('id');
        btn.prop('disabled',true).text('Désactivation…');
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'deactivate_enseignant', id:id}, function(r){
            if(r.success) location.reload();
            else { alert((r.data ? r.data.message : '') ? r.data.message : 'Erreur lors de la désactivation.'); btn.prop('disabled',false).text('Désactiver'); }
        });
    });
});
</script>
