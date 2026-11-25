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
