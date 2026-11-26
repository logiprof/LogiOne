<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>Élèves</h1>
<?php
// 1.53.6 : suivi d'un élève sur toutes ses évaluations, quelle que soit sa classe à l'époque.
$suivi = intval($_GET['suivi'] ?? 0);
if ($suivi) { include __DIR__ . '/eleve-suivi.php'; echo '</div>'; return; }
$t = LOGIEVAL_Database::tables();
$classes = LOGIEVAL_Demo::hide(LOGIEVAL_Database::get_rows($t['classes'], [], 'nom'), 'classes');
$filter_classe = intval($_GET['classe_id'] ?? 0);
$eleves = $filter_classe
    ? LOGIEVAL_Database::get_rows($t['eleves'], ['classe_id' => $filter_classe], 'nom, prenom')
    : LOGIEVAL_Database::get_rows($t['eleves'], [], 'nom, prenom');
$eleves = LOGIEVAL_Demo::hide($eleves, 'eleves'); // 1.53.19
global $wpdb;
// 1.53.9 : élèves qui ont déjà des réponses (avertissement avant une suppression groupée).
$with_data = array_flip( array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT eleve_id FROM {$t['reponses']}" ) ?: [] ) );
?>

<div style="margin-bottom:16px">
    <label>Filtrer par classe :
    <select id="filter-classe" onchange="location.href='?page=logieval-eleves&classe_id='+this.value">
        <option value="0">Toutes</option>
        <?php foreach ($classes as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= $filter_classe==$cl['id']?'selected':'' ?>><?= esc_html(LOGIEVAL_Database::class_label($cl)) ?></option>
        <?php endforeach ?>
    </select></label>
</div>

<?php
// 1.53.1 : un élève ne doit être que dans une seule classe. Doublons existants (même nom et prénom) à corriger.
$dups = $wpdb->get_results(
    "SELECT LOWER(TRIM(e.nom)) n, LOWER(TRIM(e.prenom)) p, GROUP_CONCAT(CONCAT(e.id, ':', IFNULL(c.nom,'?')) ORDER BY e.id SEPARATOR '|') lst, COUNT(*) k
     FROM {$t['eleves']} e LEFT JOIN {$t['classes']} c ON c.id=e.classe_id
     GROUP BY LOWER(TRIM(e.nom)), LOWER(TRIM(e.prenom)) HAVING k>1", ARRAY_A ) ?: [];
if ( $dups ) : ?>
<div class="notice notice-warning" style="margin:0 0 16px"><p><strong>Élèves présents dans plusieurs fiches</strong> — un élève ne doit être que dans une seule classe. Gardez la fiche de sa classe actuelle et supprimez l’autre (seulement si elle n’a pas d’évaluations), ou demandez-moi de les fusionner.</p><ul style="margin-left:18px;list-style:disc">
<?php foreach ( $dups as $d ) : ?><li><?= esc_html( ucwords( $d['p'] . ' ' . $d['n'] ) ) ?> : <?= esc_html( implode( ', ', array_map( static fn( $x ) => explode( ':', $x, 2 )[1] ?? '', explode( '|', $d['lst'] ) ) ) ) ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>
<p class="description" style="max-width:820px">Changement de classe (redoublement, réorientation) : cliquez « Modifier » sur l’élève et choisissez sa nouvelle classe. Ses évaluations passées restent liées à lui (bouton « 📈 Suivi ») et dans les résultats de son ancienne classe ; les nouvelles évaluations le comptent dans sa nouvelle classe.</p>

<button class="button button-primary" id="logieval-add-eleve">+ Nouvel élève</button>

<div id="logieval-eleve-form" class="logieval-card logieval-hidden" style="margin-top:20px;max-width:500px">
    <h3 id="logieval-eleve-form-title">Nouvel élève</h3>
    <input type="hidden" id="eleve-id" value="0">
    <table class="form-table">
        <tr><th>Classe</th>
            <td><select id="eleve-classe">
                <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $filter_classe==$cl['id']?'selected':'' ?>><?= esc_html(LOGIEVAL_Database::class_label($cl)) ?></option>
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

<div id="lge-bulk" style="margin-top:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 12px;background:#fff;border:1px solid #c3c4c7;border-radius:6px">
    <strong id="lge-bulk-count">0 élève coché</strong>
    <label class="screen-reader-text" for="lge-bulk-op">Action groupée</label>
    <select id="lge-bulk-op">
        <option value="">Actions groupées…</option>
        <option value="move">Changer de classe</option>
        <option value="delete">Supprimer</option>
    </select>
    <label class="screen-reader-text" for="lge-bulk-classe">Classe de destination</label>
    <select id="lge-bulk-classe" style="display:none">
        <option value="0">— classe de destination —</option>
        <?php foreach ($classes as $cl): ?>
        <option value="<?= $cl['id'] ?>"><?= esc_html(LOGIEVAL_Database::class_label($cl)) ?></option>
        <?php endforeach ?>
    </select>
    <button type="button" class="button" id="lge-bulk-apply" disabled>Appliquer</button>
    <span class="description">Cochez des élèves (ou la case du titre pour tous ceux affichés).</span>
</div>

<table class="wp-list-table widefat fixed striped logieval-table" style="margin-top:10px">
<thead><tr><td class="manage-column check-column" style="width:2.2em"><label class="screen-reader-text" for="lge-check-all">Tout cocher</label><input type="checkbox" id="lge-check-all"></td><th>Nom</th><th>Prénom</th><th>N° élève</th><th>Classe</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($eleves as $el):
    $cl_row = LOGIEVAL_Database::get_row($t['classes'], ['id' => (int) $el['classe_id']]);
    $classe_nom = $cl_row ? LOGIEVAL_Database::class_label($cl_row) : '';
?>
<tr>
    <th scope="row" class="check-column"><label class="screen-reader-text" for="lge-ck-<?= (int)$el['id'] ?>">Cocher <?= esc_html($el['prenom'].' '.$el['nom']) ?></label><input type="checkbox" class="lge-ck" id="lge-ck-<?= (int)$el['id'] ?>" value="<?= (int)$el['id'] ?>" data-has-data="<?= isset($with_data[(int)$el['id']]) ? 1 : 0 ?>"></th>
    <td><?= esc_html($el['nom']) ?></td>
    <td><?= esc_html($el['prenom']) ?></td>
    <td><?= esc_html($el['num_eleve']) ?></td>
    <td><?= esc_html($classe_nom) ?></td>
    <td>
        <button class="button button-small logieval-edit-eleve"
            data-id="<?= $el['id'] ?>" data-classe="<?= $el['classe_id'] ?>"
            data-prenom="<?= esc_attr($el['prenom']) ?>" data-nom="<?= esc_attr($el['nom']) ?>"
            data-num="<?= esc_attr($el['num_eleve']) ?>">Modifier</button>
        <a class="button button-small" href="<?= esc_url(add_query_arg(['page'=>'logieval-eleves','suivi'=>(int)$el['id']], admin_url('admin.php'))) ?>">📈 Suivi</a>
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
    function saveEleve(confirmMove){
        $.post(logievalAdmin.ajax_url, {
            action:'logieval_admin', nonce:logievalAdmin.nonce, sub_action:'save_eleve',
            id:$('#eleve-id').val(), classe_id:$('#eleve-classe').val(),
            prenom:$('#eleve-prenom').val(), nom:$('#eleve-nom').val(), num_eleve:$('#eleve-num').val(),
            confirm_move: confirmMove || 0
        }, function(r){
            if (r && !r.success && r.data && r.data.code === 'already_in_class') { askMove(r.data); return; }
            logievalAfter(r);
        });
    }
    // 1.53.4 : bulle de confirmation quand l'élève est déjà dans une autre classe.
    function askMove(d){
        var esc=function(t){return $('<div>').text(t).html();};
        var $o=$('<div class="logieval-move-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;display:flex;align-items:center;justify-content:center"></div>');
        var $b=$('<div role="dialog" aria-modal="true" style="background:#fff;max-width:460px;width:92%;border-radius:10px;padding:22px 24px;box-shadow:0 10px 40px rgba(0,0,0,.25);font-size:14px;line-height:1.5"></div>');
        $b.html('<h3 style="margin:0 0 10px">⚠️ Élève déjà dans une autre classe</h3>'
          +'<p><strong>'+esc(d.eleve)+'</strong> est déjà présent·e dans la classe <strong>'+esc(d.from)+'</strong>.</p>'
          +'<p>Veux-tu vraiment l’intégrer dans la classe <strong>'+esc(d.to)+'</strong> ?<br><span style="color:#555">Il ou elle quittera la classe '+esc(d.from)+'. Ses évaluations et ses résultats sont conservés.</span></p>'
          +'<p style="text-align:right;margin:18px 0 0"><button type="button" class="button lg-no">Annuler</button> <button type="button" class="button button-primary lg-yes">Oui, le déplacer dans '+esc(d.to)+'</button></p>');
        $o.append($b).appendTo('body');
        var close=function(){ $o.remove(); $(document).off('keydown.lgmove'); };
        $b.find('.lg-no').on('click', close).trigger('focus');
        $o.on('click', function(e){ if(e.target===this) close(); });
        $(document).on('keydown.lgmove', function(e){ if(e.key==='Escape') close(); });
        $b.find('.lg-yes').on('click', function(){ $(this).prop('disabled',true); close(); saveEleve(d.dup_id); });
    }
    $('#logieval-save-eleve').on('click', function(){ saveEleve(0); });
    // 1.53.9 : sélection multiple
    function checked(){ return $('.lge-ck:checked'); }
    function refreshBulk(){
        var n=checked().length, op=$('#lge-bulk-op').val();
        $('#lge-bulk-count').text(n+(n>1?' élèves cochés':' élève coché'));
        $('#lge-bulk-classe').toggle(op==='move');
        $('#lge-bulk-apply').prop('disabled', !n || !op || (op==='move' && $('#lge-bulk-classe').val()==='0'));
        $('#lge-check-all').prop('checked', n>0 && n===$('.lge-ck').length).prop('indeterminate', n>0 && n<$('.lge-ck').length);
    }
    $('#lge-check-all').on('change', function(){ $('.lge-ck').prop('checked', this.checked); refreshBulk(); });
    $(document).on('change','.lge-ck', refreshBulk);
    $('#lge-bulk-op, #lge-bulk-classe').on('change', refreshBulk);
    $('#lge-bulk-apply').on('click', function(){
        var $c=checked(), op=$('#lge-bulk-op').val(), ids=$c.map(function(){return this.value;}).get();
        if(op==='delete'){
            var withData=$c.filter('[data-has-data=1]').length;
            var msg='Supprimer définitivement '+ids.length+' élève(s) ?';
            if(withData) msg+='\n\n⚠ '+withData+' d’entre eux ont déjà des réponses d’évaluation : elles seront supprimées aussi. Pour un élève qui change de classe, utilisez plutôt « Changer de classe ».';
            if(!confirm(msg)) return;
        } else if(op==='move'){
            if(!confirm('Déplacer '+ids.length+' élève(s) dans la classe « '+$('#lge-bulk-classe option:selected').text()+' » ? Leurs évaluations restent liées à eux.')) return;
        }
        $(this).prop('disabled',true).text('…');
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'bulk_eleves',op:op,ids:ids,classe_id:$('#lge-bulk-classe').val()}, logievalAfter);
    });
    refreshBulk();

    $(document).on('click','.logieval-delete-eleve', function(){
        if(!confirm('Supprimer cet élève ? Toutes ses réponses d’évaluation seront aussi supprimées définitivement.')) return;
        $.post(logievalAdmin.ajax_url, {action:'logieval_admin',nonce:logievalAdmin.nonce,sub_action:'delete_eleve',id:$(this).data('id')}, logievalAfter);
    });
});
</script>
