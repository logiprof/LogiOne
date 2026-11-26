<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Enseignants</h1>
<?php $ens_list = LOGIEVAL_Demo::hide(LOGIEVAL_Database::get_rows(LOGIEVAL_Database::t('enseignants'), [], 'nom, prenom'), 'enseignants'); // 1.53.19 : sans les essais de démonstration
$access_sent = get_option('logieval_access_sent', null);
if ($access_sent === null) { // 1.53.11 : les comptes déjà actifs avant cette version ont déjà reçu leur accès
    $access_sent = [];
    foreach ($ens_list as $e0) if (!empty($e0['actif'])) $access_sent[(int)$e0['id']] = 'avant';
    update_option('logieval_access_sent', $access_sent, false);
}
$access_sent = is_array($access_sent) ? $access_sent : [];
$class_counts = LOGIEVAL_Database::teacher_class_counts();
$pending_all = array_filter($ens_list, static fn($e) => !empty($e['actif']) && empty($access_sent[(int)$e['id']]) && !LOGIEVAL_Database::is_placeholder_email((string)$e['email']));
// 1.53.14 : seuls les enseignants affectés à au moins une classe reçoivent leurs accès
$pending = array_filter($pending_all, static fn($e) => !empty($class_counts[(int)$e['id']]));
$pending_noclass = count($pending_all) - count($pending); ?>

<button class="button button-primary" id="logieval-add-ens">+ Nouvel enseignant</button>
<p class="description" style="margin-top:8px;max-width:900px">
    Seuls les enseignants affectés à au moins une classe (colonne « Classes ») reçoivent leurs accès. Les enseignants importés ou créés ici restent inactifs tant que vous ne les activez pas. Pour préparer tranquillement :
    <strong>Activer sans e-mail</strong> (ils peuvent être ajoutés aux évaluations, rien ne leur est envoyé), puis, quand tout est prêt,
    <strong>Envoyer les accès</strong>. La colonne « Accès envoyé » indique qui les a déjà reçus.
</p>
<?php if ($pending): ?>
<div class="notice notice-info inline" style="margin:12px 0;padding:10px 12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span><strong><?= count($pending) ?> enseignant(s) actif(s)</strong> n’ont pas encore reçu leur e-mail d’accès.</span>
    <?php if ($pending_noclass): ?><span class="description"><?= (int)$pending_noclass ?> autre(s) sans classe : rien ne leur sera envoyé.</span><?php endif ?>
    <button type="button" class="button button-primary" id="lge-send-pending" data-ids="<?= esc_attr(implode(',', array_map(static fn($e) => (int)$e['id'], $pending))) ?>">Envoyer les accès maintenant (<?= count($pending) ?>)</button>
</div>
<?php endif ?>

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

<div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 12px;background:#fff;border:1px solid #c3c4c7;border-radius:6px">
    <strong id="lge-ens-count">0 enseignant coché</strong>
    <label class="screen-reader-text" for="lge-ens-op">Action groupée</label>
    <select id="lge-ens-op">
        <option value="">Actions groupées…</option>
        <option value="activate">Activer sans e-mail</option>
        <option value="send">Envoyer l’e-mail d’accès</option>
        <option value="activate_send">Activer et envoyer l’e-mail</option>
        <option value="deactivate">Désactiver</option>
    </select>
    <button type="button" class="button" id="lge-ens-apply" disabled>Appliquer</button>
</div>
<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:10px">
<thead>
    <tr><td class="manage-column check-column" style="width:2.2em"><label class="screen-reader-text" for="lge-ens-all">Tout cocher</label><input type="checkbox" id="lge-ens-all"></td><th>Nom</th><th>E-mail</th><th style="width:70px">Classes</th><th>Statut</th><th>Accès envoyé</th><th>Identifiant unique</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($ens_list as $e): ?>
<?php $is_active = !empty($e['actif']); ?>
<tr>
    <th scope="row" class="check-column"><label class="screen-reader-text" for="lge-ens-<?= (int)$e['id'] ?>">Cocher <?= esc_html($e['prenom'].' '.$e['nom']) ?></label><input type="checkbox" class="lge-ens-ck" id="lge-ens-<?= (int)$e['id'] ?>" value="<?= (int)$e['id'] ?>"></th>
    <td><strong><?= esc_html($e['nom'].' '.$e['prenom']) ?></strong></td>
    <?php $todo_mail = LOGIEVAL_Database::is_placeholder_email((string) $e['email']); ?>
    <td><?= $todo_mail ? '<span style="color:#B45309;font-weight:600">⚠ e-mail à compléter</span>' : esc_html($e['email']) ?></td>
    <?php $nc = (int)($class_counts[(int)$e['id']] ?? 0); ?>
    <td><?= $nc ? $nc : '<span style="color:#B45309" title="Aucune classe : ne recevra pas d’accès">0</span>' ?></td>
    <td>
        <?php if ($is_active): ?>
            <span style="display:inline-block;background:#DCFCE7;color:#166534;border-radius:999px;padding:3px 10px;font-weight:600">Actif</span>
        <?php else: ?>
            <span style="display:inline-block;background:#F1F5F9;color:#475569;border-radius:999px;padding:3px 10px;font-weight:600">Inactif</span>
        <?php endif ?>
    </td>
    <td><?php $sent_at = $access_sent[(int)$e['id']] ?? ''; ?>
        <?= $sent_at === 'avant' ? '✓ <small>(actif avant)</small>' : ($sent_at ? '✓ ' . esc_html(mysql2date('d.m.Y H:i', $sent_at)) : ($is_active ? '<span style="color:#B45309">Pas encore</span>' : '—')) ?>
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
            data-email="<?= $todo_mail ? '' : esc_attr($e['email']) ?>">Modifier</button>
        <?php if ($is_active): ?>
            <?php if (empty($access_sent[(int)$e['id']]) && $nc): ?>
            <button class="button button-primary button-small lge-send-one" data-id="<?= $e['id'] ?>">Envoyer l’accès</button>
            <?php endif ?>
            <button class="button button-small logieval-deactivate-ens" data-id="<?= $e['id'] ?>">Désactiver</button>
        <?php else: ?>
            <button class="button button-small lge-activate-silent" data-id="<?= $e['id'] ?>">Activer sans e-mail</button>
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

    // 1.53.11 : activer sans e-mail, envoyer les accès plus tard, actions groupées
    function bulk(op, ids, ask){
        if(!ids.length) return;
        if(ask && !confirm(ask)) return;
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'bulk_enseignants', op:op, ids:ids}, function(r){
            alert(r && r.data && r.data.message ? r.data.message : 'L’opération a échoué.');
            if(r && r.success) location.reload();
        });
    }
    $(document).on('click','.lge-activate-silent', function(){ bulk('activate',[$(this).data('id')]); });
    $(document).on('click','.lge-send-one', function(){ bulk('send',[$(this).data('id')],'Envoyer maintenant l’e-mail d’accès (identifiant + lien) à cet enseignant ?'); });
    $('#lge-send-pending').on('click', function(){ var ids=String($(this).data('ids')).split(','); bulk('send', ids, 'Envoyer maintenant l’e-mail d’accès à '+ids.length+' enseignant(s) actif(s) qui ne l’ont pas encore reçu ?'); });
    function refreshEns(){
        var n=$('.lge-ens-ck:checked').length;
        $('#lge-ens-count').text(n+(n>1?' enseignants cochés':' enseignant coché'));
        $('#lge-ens-apply').prop('disabled', !n || !$('#lge-ens-op').val());
        $('#lge-ens-all').prop('checked', n>0 && n===$('.lge-ens-ck').length).prop('indeterminate', n>0 && n<$('.lge-ens-ck').length);
    }
    $('#lge-ens-all').on('change', function(){ $('.lge-ens-ck').prop('checked', this.checked); refreshEns(); });
    $(document).on('change','.lge-ens-ck', refreshEns); $('#lge-ens-op').on('change', refreshEns);
    $('#lge-ens-apply').on('click', function(){
        var op=$('#lge-ens-op').val(), ids=$('.lge-ens-ck:checked').map(function(){return this.value;}).get();
        var lbl={activate:'Activer sans e-mail', send:'Envoyer l’e-mail d’accès à', activate_send:'Activer et envoyer l’e-mail à', deactivate:'Désactiver'}[op];
        bulk(op, ids, lbl+' '+ids.length+' enseignant(s) ?');
    });
    refreshEns();

    $(document).on('click','.logieval-activate-ens', function(){
        if(!confirm('Activer cet enseignant et lui envoyer son e-mail d’accès LogiEval ?')) return;
        var btn=$(this), id=btn.data('id');
        btn.prop('disabled',true).text('Activation…');
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'activate_enseignant', id:id}, function(r){
            if(r.success) {
                if (r.data && r.data.email_sent === -2) alert('Enseignant activé. Aucun e-mail envoyé : il n’est affecté à aucune classe.');
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
