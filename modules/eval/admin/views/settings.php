<?php
if (!defined('ABSPATH')) exit;
$mail_templates = LOGIEVAL_Mailer::templates();
$email_login_url = LOGIEVAL_Mailer::login_url();
?>
<div class="wrap logieval-wrap">
<h1>Réglages</h1>
<?php $pages = get_pages(); ?>

<div class="logieval-card" style="max-width:1100px">
<h2>Général</h2>
<table class="form-table">
    <tr><th>Nom de l'établissement</th>
        <td><input type="text" id="cfg-etablissement" class="regular-text" value="<?= esc_attr(get_option('logieval_etablissement','')) ?>"></td>
    </tr>
    <tr><th>Page de connexion enseignant</th>
        <td>
            <select id="cfg-login-page">
                <option value="">— Aucune (URL automatique) —</option>
                <?php foreach ($pages as $pg): ?>
                <option value="<?=$pg->ID?>" <?= get_option('logieval_login_page')==$pg->ID?'selected':'' ?>><?= esc_html($pg->post_title) ?></option>
                <?php endforeach ?>
            </select>
            <p class="description">Placez le shortcode <code>[logieval_login]</code> sur cette page.</p>
            <p class="description"><strong>Lien actuellement envoyé dans les e-mails :</strong><br>
                <code><?= esc_html($email_login_url) ?></code>
            </p>
        </td>
    </tr>
    <tr><th>Guide de l’enseignant (PDF)</th>
        <td>
            <p style="margin-top:0"><a class="button" href="<?= esc_url(LOGIEVAL_Mailer::guide_url()) ?>" target="_blank" rel="noopener">📘 Ouvrir le guide actuel</a></p>
            <input type="url" id="cfg-guide-url" class="regular-text" placeholder="Laisser vide : guide fourni avec LogiEval" value="<?= esc_attr(get_option('logieval_guide_url','')) ?>">
            <p class="description">Ce guide est envoyé avec les identifiants (variable <code>{guide}</code>) et s’ouvre avec le bouton « Aide » des enseignants. Pour utiliser votre propre version, téléversez le PDF dans la Médiathèque et collez son adresse ici.</p>
        </td>
    </tr>
</table>

<h2 id="demo">Mode démonstration</h2>
<?php $demo_on = LOGIEVAL_Demo::enabled(); $demo_n = LOGIEVAL_Demo::count(); ?>
<table class="form-table">
    <tr><th>Présentation aux collègues</th>
        <td>
            <label><input type="checkbox" id="cfg-demo-on" <?= $demo_on ? 'checked' : '' ?>> <strong>Proposer « Essayer LogiEval » sur la page de connexion</strong></label>
            <p class="description">Chaque collègue choisit son profil (<b>maître de classe</b> ou <b>enseignant·e</b>) et reçoit sa propre classe fictive de 5 élèves avec une évaluation courte (3 objectifs, 4 questions), sans identifiant.
            Les essais n’envoient aucun e-mail, n’apparaissent pas dans vos listes (classes, élèves, enseignants, évaluations) et n’utilisent pas vos vrais objectifs. Ils sont effacés quand le collègue quitte la démonstration, ou au bout de 3 jours.</p>
            <p class="description" id="cfg-demo-state"><?= $demo_n ? esc_html( $demo_n . ' essai' . ( $demo_n > 1 ? 's' : '' ) . ' en cours.' ) : 'Aucun essai en cours.' ?></p>
            <p><button type="button" class="button" id="cfg-demo-purge">Effacer toutes les données de démonstration</button></p>
            <p class="description">Pensez à décocher la case après la présentation.</p>
        </td>
    </tr>
</table>

<h2>Identifiants enseignants</h2>
<table class="form-table">
    <tr><th>Préfixe</th>
        <td><input type="text" id="cfg-id-prefix" class="small-text" value="<?= esc_attr(get_option('logieval_id_prefix','ENS')) ?>" maxlength="6"> <small>ex. ENS → ENS-A3F7K2</small></td>
    </tr>
    <tr><th>Longueur (partie aléatoire)</th>
        <td><input type="number" id="cfg-id-length" class="small-text" value="<?= esc_attr(get_option('logieval_id_length','8')) ?>" min="8" max="16"></td>
    </tr>
</table>

<h2>Messages automatiques</h2>
<p class="description">
    Personnalisez ici tous les e-mails envoyés par LogiEval. Les variables entre accolades sont remplacées au moment de l'envoi.
</p>

<?php foreach ($mail_templates as $template_key => $tpl):
    $subject_value = get_option($tpl['subject'], '');
    $body_value    = get_option($tpl['body'], '');
    if ($subject_value === '') $subject_value = $tpl['default_subject'];
    if ($body_value === '') $body_value = $tpl['default_body'];
    $subject_value = stripslashes($subject_value);
    $body_value    = stripslashes($body_value);
?>
<div class="logieval-mail-template" style="border:1px solid #dcdcde;border-radius:6px;padding:14px 16px;margin:16px 0;background:#fff">
    <h3 style="margin-top:0"><?= esc_html($tpl['title']) ?></h3>
    <table class="form-table" role="presentation">
        <tr>
            <th>Sujet</th>
            <td>
                <input type="text"
                       class="large-text logieval-mail-field"
                       data-option="<?= esc_attr($tpl['subject']) ?>"
                       value="<?= esc_attr($subject_value) ?>">
            </td>
        </tr>
        <tr>
            <th>Corps</th>
            <td>
                <textarea class="large-text logieval-mail-field"
                          rows="9"
                          data-option="<?= esc_attr($tpl['body']) ?>"><?= esc_textarea($body_value) ?></textarea>
                <?php if (!empty($tpl['to'])): ?><p class="description" style="color:#1d2327"><strong>Destinataires :</strong> <?= esc_html($tpl['to']) ?></p><?php endif ?>
                <p class="description"><strong>Variables disponibles :</strong> <?= esc_html($tpl['variables']) ?></p>
            </td>
        </tr>
    </table>
</div>
<?php endforeach ?>

<p><button class="button button-primary" id="logieval-save-settings">💾 Enregistrer les réglages</button>
   <span id="logieval-settings-saved" style="display:none;color:green;margin-left:12px">✓ Enregistré</span>
</p>
</div>
</div>

<script>
jQuery(function($){
    $('#cfg-demo-on').on('change', function(){
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'demo_toggle', on: this.checked ? 1 : 0}, function(r){
            if (r.success) { $('#cfg-demo-state').text(r.data.on ? 'Mode démonstration activé : la carte « Essayer LogiEval » est visible sur la page de connexion.' : 'Mode démonstration désactivé.'); }
        });
    });
    $('#cfg-demo-purge').on('click', function(){
        if (!confirm('Effacer toutes les données de démonstration (classes, élèves, collègues et évaluations fictifs) ? Vos vraies données ne sont pas touchées.')) return;
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'demo_purge'}, function(r){
            if (r.success) { $('#cfg-demo-state').text(r.data.n + ' essai(s) effacé(s). Données de démonstration supprimées.'); }
        });
    });
    $('#logieval-save-settings').on('click', function(){
        var data = {
            action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'save_settings',
            logieval_etablissement: $('#cfg-etablissement').val(),
            logieval_login_page: $('#cfg-login-page').val(),
            logieval_guide_url: $('#cfg-guide-url').val(),
            logieval_id_prefix: $('#cfg-id-prefix').val(),
            logieval_id_length: $('#cfg-id-length').val()
        };
        $('.logieval-mail-field').each(function(){
            data[$(this).data('option')] = $(this).val();
        });
        $.post(logievalAdmin.ajax_url, data, function(r){
            if(r.success){ $('#logieval-settings-saved').fadeIn().delay(2000).fadeOut(); }
        });
    });
});
</script>
