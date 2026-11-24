<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Classes</h1>

<?php
$t = LOGIEVAL_Database::tables();
$classes      = LOGIEVAL_Rollover::without_archived(LOGIEVAL_Database::get_rows($t['classes'], [], 'annee_scolaire DESC, nom')); // 1.53.0 : archivées dans « Passage d'année »
$all_ens      = LOGIEVAL_Database::get_rows($t['enseignants'], ['actif' => 1], 'nom, prenom');
global $wpdb;
?>

<button class="button button-primary" id="logieval-add-classe">+ Nouvelle classe</button>
<?php LOGIEVAL_Rollover::render_panel(); ?>

<div id="logieval-classe-form" class="logieval-card logieval-hidden" style="margin-top:20px;max-width:600px">
    <h3 id="logieval-classe-form-title">Nouvelle classe</h3>
    <input type="hidden" id="classe-id" value="0">
    <table class="form-table">
        <tr><th>Nom</th><td><input type="text" id="classe-nom" class="regular-text"></td></tr>
        <tr><th>Niveau</th><td><input type="text" id="classe-niveau" class="regular-text" placeholder="ex. A1b"></td></tr>
        <tr><th>Année scolaire</th><td><input type="text" id="classe-annee" class="regular-text" placeholder="ex. 2024-2025"></td></tr>
        <tr><th>Maître de classe</th>
            <td><p class="description" style="margin:0">Après avoir enregistré la classe, affectez-lui ses enseignants avec « + Affecter », puis cliquez « Nommer maître » à côté du maître de classe.</p></td>
        </tr>
    </table>
    <p>
        <button class="button button-primary" id="logieval-save-classe">Enregistrer</button>
        <button class="button" id="logieval-cancel-classe">Annuler</button>
    </p>
</div>

<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:20px">
<thead>
    <tr>
        <th width="150">Nom</th>
        <th width="80">Niveau</th>
        <th width="100">Année</th>
        <th width="60">Élèves</th>
        <th>Enseignants affectés</th>
        <th width="130">Maître de classe</th>
        <th width="160">Actions</th>
    </tr>
</thead>
<tbody>
<?php foreach ($classes as $cl):
    $nb_el = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['eleves']} WHERE classe_id=%d", $cl['id']));
    $ens_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.prenom, e.nom, e.actif, ce.est_maitre, ce.id AS ce_id
         FROM {$t['classe_enseignant']} ce
         INNER JOIN {$t['enseignants']} e ON e.id = ce.enseignant_id
         WHERE ce.classe_id = %d ORDER BY e.nom, e.prenom", $cl['id']), ARRAY_A) ?: [];
    $maitre = null;
    foreach ($ens_rows as $er) { if ($er['est_maitre']) { $maitre = $er; break; } }
?>
<tr data-id="<?= $cl['id'] ?>">
    <td><strong><?= esc_html($cl['nom']) ?></strong></td>
    <td><?= esc_html($cl['niveau']) ?></td>
    <td><?= esc_html($cl['annee_scolaire']) ?></td>
    <td><?= $nb_el ?></td>
    <td>
        <?php if (empty($ens_rows)): ?>
            <em style="color:#999">Aucun enseignant</em>
        <?php else: ?>
            <?php foreach ($ens_rows as $er): ?>
            <span class="logieval-tag">
                <?= $er['est_maitre'] ? '👑 ' : '' ?>
                <?= esc_html($er['nom'].' '.$er['prenom']) ?><?= empty($er['actif']) ? ' <small style="color:#B45309">(inactif)</small>' : '' ?>
            </span>
            <?php endforeach ?>
        <?php endif ?>
        <br>
        <button class="button button-small logieval-manage-ens" data-classe="<?= $cl['id'] ?>"
                data-classenom="<?= esc_attr($cl['nom']) ?>" style="margin-top:6px">
            👥 Gérer les enseignants (<?= count($ens_rows) ?>)
        </button>
    </td>
    <td><?= $maitre ? esc_html($maitre['nom'].' '.$maitre['prenom']) : '<em style="color:#999">—</em>' ?></td>
    <td>
        <button class="button button-small logieval-edit-classe"
            data-id="<?= $cl['id'] ?>"
            data-nom="<?= esc_attr($cl['nom']) ?>"
            data-niveau="<?= esc_attr($cl['niveau']) ?>"
            data-annee="<?= esc_attr($cl['annee_scolaire']) ?>"
            data-maitre="<?= $maitre ? (int)$maitre['id'] : 0 ?>">Modifier</button>
        <button class="button button-small logieval-delete-classe" data-id="<?= $cl['id'] ?>">Supprimer</button>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<!-- ══ Modal: gérer les enseignants d'une classe ══════════════════════ -->
<div id="logieval-ens-modal" class="logieval-modal logieval-hidden">
    <div class="logieval-modal-inner" style="max-width:620px;width:100%">
        <h3 style="margin-top:0">👥 Enseignants — <span id="modal-classe-nom"></span></h3>
        <input type="hidden" id="modal-classe-id">

        <!-- Already assigned -->
        <div style="margin-bottom:20px">
            <h4 style="margin:0 0 8px;color:#1A1A2E">Enseignants actuellement affectés</h4>
            <div id="assigned-list" style="min-height:40px">
                <em style="color:#999">Chargement…</em>
            </div>
        </div>

        <hr style="border-color:#e0e0e0;margin:16px 0">

        <!-- Add new -->
        <h4 style="margin:0 0 10px;color:#1A1A2E">Affecter un nouvel enseignant</h4>
        <table class="form-table" style="margin:0">
            <tr>
                <th style="width:120px">Enseignant</th>
                <td>
                    <select id="new-ens-id" style="min-width:220px">
                        <option value="">— Choisir —</option>
                        <?php foreach ($all_ens as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= esc_html($e['nom'].' '.$e['prenom']) ?></option>
                        <?php endforeach ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Maître de classe</th>
                <td><input type="checkbox" id="new-est-maitre"> <small>Désigner comme maître de classe</small></td>
            </tr>
        </table>
        <p style="margin-top:12px">
            <button class="button button-primary" id="btn-add-ens">+ Affecter</button>
        </p>

        <hr style="border-color:#e0e0e0;margin:16px 0">
        <p style="text-align:right;margin:0">
            <button class="button" id="logieval-close-ens-modal">Fermer (les changements sont sauvegardés automatiquement)</button>
        </p>
    </div>
</div>

</div><!-- /wrap -->

<style>
.logieval-modal { position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center; }
.logieval-modal-inner { background:#fff;border-radius:10px;padding:28px 32px;box-shadow:0 8px 40px rgba(0,0,0,.2);overflow-y:auto;max-height:90vh; }
.logieval-hidden { display:none !important; }
.logieval-tag { display:inline-block;background:#EEF4FB;border-radius:4px;padding:2px 8px;font-size:.82rem;margin:2px; }
.assigned-row { display:flex;align-items:center;gap:10px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;margin-bottom:6px;background:#F8FAFC; }
.assigned-row .ens-name { flex:1;font-weight:600;font-size:.95rem; }
.assigned-row .ens-maitre { background:#FEF9C3;color:#854D0E;border-radius:4px;padding:1px 7px;font-size:.78rem;font-weight:700; }
.btn-remove-ens { background:#FEE2E2;border:1px solid #FECACA;color:#B91C1C;border-radius:4px;padding:3px 10px;cursor:pointer;font-size:.82rem; }
.btn-remove-ens:hover { background:#FECACA; }
.btn-set-maitre { background:#DCFCE7;border:1px solid #BBF7D0;color:#166534;border-radius:4px;padding:3px 10px;cursor:pointer;font-size:.78rem; }
</style>

<script>
jQuery(function($){

    /* ── Classe form ──────────────────────────────────────────── */
    $('#logieval-add-classe').on('click', function(){
        $('#logieval-classe-form-title').text('Nouvelle classe');
        $('#classe-id').val(0); $('#classe-nom,#classe-niveau,#classe-annee').val(''); $('#classe-maitre').val('');
        $('#logieval-classe-form').removeClass('logieval-hidden');
    });
    $('#logieval-cancel-classe').on('click', function(){ $('#logieval-classe-form').addClass('logieval-hidden'); });

    $(document).on('click','.logieval-edit-classe', function(){
        var d=$(this).data();
        $('#logieval-classe-form-title').text('Modifier la classe');
        $('#classe-id').val(d.id); $('#classe-nom').val(d.nom); $('#classe-niveau').val(d.niveau);
        $('#classe-annee').val(d.annee); $('#classe-maitre').val(d.maitre);
        $('#logieval-classe-form').removeClass('logieval-hidden');
        $('html,body').animate({scrollTop:$('#logieval-classe-form').offset().top-60});
    });

    $('#logieval-save-classe').on('click', function(){
        $.post(logievalAdmin.ajax_url,{
            action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'save_classe',
            id:$('#classe-id').val(), nom:$('#classe-nom').val(),
            niveau:$('#classe-niveau').val(), annee_scolaire:$('#classe-annee').val(),
            maitre_id:$('#classe-maitre').val()
        },logievalAfter);
    });

    $(document).on('click','.logieval-delete-classe', function(){
        if (!confirm('Supprimer cette classe ?')) return;
        $.post(logievalAdmin.ajax_url,{action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'delete_classe',id:$(this).data('id')},logievalAfter);
    });

    /* ── Teacher management modal ─────────────────────────────── */
    var currentClasseId = 0;

    // Open modal
    $(document).on('click','.logieval-manage-ens', function(){
        currentClasseId = $(this).data('classe');
        $('#modal-classe-nom').text($(this).data('classenom'));
        $('#modal-classe-id').val(currentClasseId);
        $('#new-ens-id').val('');
        $('#new-est-maitre').prop('checked',false);
        $('#logieval-ens-modal').removeClass('logieval-hidden');
        loadAssigned(currentClasseId);
    });

    $('#logieval-close-ens-modal').on('click', function(){
        $('#logieval-ens-modal').addClass('logieval-hidden');
        location.reload(); // Refresh page to show updated teacher list
    });

    // Close on backdrop click
    $('#logieval-ens-modal').on('click', function(e){
        if ($(e.target).is('#logieval-ens-modal')) {
            $('#logieval-ens-modal').addClass('logieval-hidden');
            location.reload();
        }
    });

    /* Load assigned teachers via AJAX */
    function loadAssigned(classeId) {
        $('#assigned-list').html('<em style="color:#999">Chargement…</em>');
        $.post(logievalAdmin.ajax_url,{
            action:'logieval_admin', nonce:logievalAdmin.nonce,
            sub_action:'get_classe_teachers_detail', classe_id:classeId
        },function(r){
            if (!r.success) { $('#assigned-list').html('<em style="color:red">Erreur de chargement.</em>'); return; }
            if (r.data.length === 0) {
                $('#assigned-list').html('<em style="color:#999">Aucun enseignant affecté pour l\'instant.</em>');
                return;
            }
            var html = '';
            r.data.forEach(function(e){
                html += '<div class="assigned-row" id="ens-row-'+e.ce_id+'">';
                html += '<span class="ens-name">' + escHtml(e.nom + ' ' + e.prenom) + '</span>';
                if (e.est_maitre == 1) html += '<span class="ens-maitre">👑 Maître</span>';
                else html += '<button class="btn-set-maitre" data-ceid="'+e.ce_id+'" title="Désigner comme maître de classe">Nommer maître</button>';
                html += '<button class="btn-remove-ens" data-ceid="'+e.ce_id+'" data-eid="'+e.enseignant_id+'" title="Retirer de la classe">✕ Retirer</button>';
                html += '</div>';
            });
            $('#assigned-list').html(html);
        });
    }

    /* Add a teacher */
    $('#btn-add-ens').on('click', function(){
        var eid = $('#new-ens-id').val();
        if (!eid) { alert('Veuillez sélectionner un enseignant.'); return; }
        var btn = $(this);
        btn.prop('disabled',true).text('Ajout…');
        $.post(logievalAdmin.ajax_url,{
            action:'logieval_admin', nonce:logievalAdmin.nonce,
            sub_action:'assign_enseignant_classe',
            classe_id:currentClasseId,
            enseignant_id:eid,
            est_maitre:$('#new-est-maitre').is(':checked')?1:0
        },function(r){
            btn.prop('disabled',false).text('+ Affecter');
            if (r.success) {
                // Reset form and reload list — NO page reload
                $('#new-ens-id').val('');
                $('#new-est-maitre').prop('checked',false);
                loadAssigned(currentClasseId);
            } else {
                alert((r.data ? r.data.message : '') ? r.data.message : 'Cet enseignant est peut-être déjà affecté à cette classe.');
            }
        });
    });

    /* Remove a teacher */
    $(document).on('click','.btn-remove-ens', function(){
        if (!confirm('Retirer cet enseignant de la classe ?')) return;
        var ceid = $(this).data('ceid');
        $(this).prop('disabled',true).text('…');
        $.post(logievalAdmin.ajax_url,{
            action:'logieval_admin', nonce:logievalAdmin.nonce,
            sub_action:'remove_enseignant_classe', ce_id:ceid
        },function(r){
            if (r.success) loadAssigned(currentClasseId);
            else alert((r.data && r.data.message) ? r.data.message : 'Erreur lors du retrait.');
        });
    });

    /* Set as maître */
    $(document).on('click','.btn-set-maitre', function(){
        var ceid = $(this).data('ceid');
        $(this).prop('disabled',true).text('…');
        $.post(logievalAdmin.ajax_url,{
            action:'logieval_admin', nonce:logievalAdmin.nonce,
            sub_action:'set_maitre_classe', ce_id:ceid, classe_id:currentClasseId
        },function(r){
            if (r.success) loadAssigned(currentClasseId);
            else alert((r.data && r.data.message) ? r.data.message : 'Erreur.');
        });
    });

    function escHtml(str){ return $('<div>').text(str).html(); }
});
</script>
