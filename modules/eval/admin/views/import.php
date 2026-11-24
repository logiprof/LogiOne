<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<h1>📥 Import — Élèves & Enseignants</h1>

<?php
$t       = LOGIEVAL_Database::tables();
$classes = LOGIEVAL_Database::get_rows($t['classes'], [], 'nom');
$assets  = LOGIEVAL_URL . 'assets/';

// Handle import submission
$import_results = null;
if (isset($_POST['logieval_import_submit']) && check_admin_referer('logieval_import')) {
    $type   = sanitize_text_field($_POST['import_type'] ?? '');
    $file   = $_FILES['import_file'] ?? null;
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $allowed_mimes = [
            'csv'  => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
        ];
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($checked['ext']) || empty($checked['type'])) {
            $import_results = ['error' => 'Type de fichier non autorisé. Utilisez .xlsx, .xls ou .csv.'];
        } else {
            $import_results = LOGIEVAL_Importer::process($type, $file['tmp_name'], $file['name']);
        }
    } else {
        $import_results = ['error' => 'Aucun fichier reçu ou erreur d\'upload.'];
    }
}
?>

<!-- ══ Download templates ══════════════════════════════════════ -->
<div class="logieval-import-section">
    <h2>1. Télécharger les modèles de fichiers</h2>
    <p>Utilisez ces modèles pour préparer vos fichiers. Ne modifiez pas les en-têtes.</p>

    <div class="logieval-template-grid">
        <div class="logieval-template-card">
            <div class="logieval-template-icon">🎓</div>
            <h3>Modèle Élèves</h3>
            <p>Colonnes : <code>nom</code>, <code>prenom</code>, <code>num_eleve</code>, <code>classe</code></p>
            <div class="logieval-template-btns">
                <a href="<?= $assets ?>template-eleves.xlsx" class="button button-primary" download>⬇ Excel (.xlsx)</a>
                <a href="<?= $assets ?>template-eleves.csv"  class="button" download>⬇ CSV</a>
            </div>
        </div>
        <div class="logieval-template-card">
            <div class="logieval-template-icon">👨‍🏫</div>
            <h3>Modèle Enseignants</h3>
            <p>Colonnes : <code>nom</code>, <code>prenom</code>, <code>email</code></p>
            <p class="description">
                Les enseignants importés seront créés inactifs : aucun e-mail ne sera envoyé pendant l’import.
                L’e-mail d’accès partira uniquement lors de l’activation du profil.
            </p>
            <div class="logieval-template-btns">
                <a href="<?= $assets ?>template-enseignants.xlsx" class="button button-primary" download>⬇ Excel (.xlsx)</a>
                <a href="<?= $assets ?>template-enseignants.csv"  class="button" download>⬇ CSV</a>
            </div>
        </div>
        <div class="logieval-template-card">
            <div class="logieval-template-icon">🏫</div>
            <h3>Modèle Classes ↔ enseignants</h3>
            <p>Colonne 1 : classe · colonne 2 : maître de classe · colonnes 3 à 5 : autres enseignants</p>
            <p class="description">Enseignants connus : simplement liés à la classe. Inconnus : compte créé inactif, e-mail à compléter avant activation.</p>
            <div class="logieval-template-btns">
                <a href="<?= $assets ?>template-classes-enseignants.xlsx" class="button button-primary" download>⬇ Excel (.xlsx)</a>
            </div>
        </div>
    </div>
</div>

<!-- ══ Upload form ═════════════════════════════════════════════ -->
<div class="logieval-import-section">
    <h2>2. Importer un fichier</h2>

    <form method="post" enctype="multipart/form-data" id="logieval-import-form">
        <?php wp_nonce_field('logieval_import') ?>
        <input type="hidden" name="logieval_import_submit" value="1">

        <table class="form-table" style="max-width:600px">
            <tr>
                <th style="width:160px">Type d'import</th>
                <td>
                    <label style="margin-right:24px">
                        <input type="radio" name="import_type" value="eleves" checked onchange="updateTypeHint(this.value)">
                        🎓 Élèves
                    </label>
                    <label>
                        <input type="radio" name="import_type" value="enseignants" onchange="updateTypeHint(this.value)">
                        👨‍🏫 Enseignants
                    </label>
                    <label style="margin-left:24px">
                        <input type="radio" name="import_type" value="classes_enseignants" onchange="updateTypeHint(this.value)">
                        🏫 Classes ↔ enseignants
                    </label>
                </td>
            </tr>
            <tr>
                <th>Fichier</th>
                <td>
                    <div class="logieval-dropzone" id="logieval-dropzone">
                        <input type="file" name="import_file" id="import-file-input"
                               accept=".xlsx,.xls,.csv" style="display:none">
                        <div id="dropzone-content">
                            <div style="font-size:2.5rem;margin-bottom:8px">📂</div>
                            <div><strong>Glissez votre fichier ici</strong></div>
                            <div style="color:#777;font-size:.85rem;margin:4px 0">ou</div>
                            <button type="button" class="button" onclick="document.getElementById('import-file-input').click()">
                                Choisir un fichier
                            </button>
                            <div style="color:#999;font-size:.8rem;margin-top:8px">Excel (.xlsx, .xls) ou CSV</div>
                        </div>
                        <div id="dropzone-file" style="display:none;padding:12px">
                            <span id="file-icon">📄</span>
                            <span id="file-name" style="font-weight:600;margin:0 8px"></span>
                            <span id="file-size" style="color:#777;font-size:.85rem"></span>
                            <button type="button" class="button button-small" id="btn-clear-file" style="margin-left:12px">✕ Changer</button>
                        </div>
                    </div>
                    <p id="type-hint" class="description" style="margin-top:8px">
                        Colonnes requises : <code>nom</code>, <code>prenom</code>, <code>num_eleve</code> (optionnel), <code>classe</code>
                    </p>
                    <p id="teacher-import-hint" class="description" style="display:none;margin-top:8px;color:#475569">
                        Les enseignants importés resteront inactifs et ne recevront pas d’e-mail tant que vous ne les activez pas depuis la page Enseignants.
                    </p>
                    <div id="classes-hint" style="margin-top:10px;padding:10px 14px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;font-size:.85rem;">
                        <strong style="color:#166534">Classes disponibles dans LogiEval :</strong><br>
                        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
                        <?php
                        $available_classes = LOGIEVAL_Database::get_rows(LOGIEVAL_Database::t('classes'), [], 'nom');
                        if (empty($available_classes)): ?>
                            <em style="color:#999">Aucune classe créée. Créez d'abord vos classes dans le menu "Classes".</em>
                        <?php else: foreach ($available_classes as $ac): ?>
                            <code style="background:#DCFCE7;padding:2px 8px;border-radius:4px;color:#166534;font-size:.9rem"><?= esc_html($ac['nom']) ?></code>
                        <?php endforeach; endif; ?>
                        </div>
                        <p class="description" style="margin:6px 0 0;color:#555">Le nom dans la colonne <code>classe</code> doit correspondre exactement à l'un des noms ci-dessus.</p>
                    </div>
                </td>
            </tr>
            <tr>
                <th>Options</th>
                <td>
                    <label>
                        <input type="checkbox" name="skip_errors" value="1" checked>
                        Ignorer les lignes invalides et importer le reste
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="update_existing" value="1">
                        Mettre à jour les entrées existantes (même e-mail / même n° d'élève / même nom+prénom) — un élève déjà dans une autre classe y est déplacé
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="create_classes" value="1">
                        Élèves : créer automatiquement les classes manquantes, année scolaire
                    </label>
                    <input type="text" name="class_year" value="<?= esc_attr( LOGIEVAL_Importer::current_school_year() ) ?>" pattern="\d{4}-\d{4}" style="width:110px">
                </td>
            </tr>
        </table>

        <p>
            <button type="submit" class="button button-primary button-large" id="btn-import">
                📥 Lancer l'import
            </button>
        </p>
    </form>
</div>

<!-- ══ Results ═════════════════════════════════════════════════ -->
<?php if ($import_results): ?>
<div class="logieval-import-section">
    <h2>3. Résultats de l'import</h2>

    <?php if (isset($import_results['error'])): ?>
        <div class="logieval-import-error">
            ❌ <?= esc_html($import_results['error']) ?>
        </div>

    <?php else:
        $total    = $import_results['total'];
        $imported = $import_results['imported'];
        $updated  = $import_results['updated'];
        $skipped  = $import_results['skipped'];
        $errors   = $import_results['errors'];
    ?>
        <!-- Summary cards -->
        <div class="logieval-import-summary">
            <div class="logieval-import-stat logieval-import-stat-total">
                <span class="logieval-import-stat-num"><?= $total ?></span>
                <span>Lignes lues</span>
            </div>
            <div class="logieval-import-stat logieval-import-stat-ok">
                <span class="logieval-import-stat-num"><?= $imported ?></span>
                <span><?= ($type ?? '') === 'classes_enseignants' ? 'Liens créés' : 'Importés' ?></span>
            </div>
            <?php if ($updated > 0): ?>
            <div class="logieval-import-stat logieval-import-stat-updated">
                <span class="logieval-import-stat-num"><?= $updated ?></span>
                <span>Mis à jour</span>
            </div>
            <?php endif ?>
            <?php if ($skipped > 0): ?>
            <div class="logieval-import-stat logieval-import-stat-skip">
                <span class="logieval-import-stat-num"><?= $skipped ?></span>
                <span><?= ($type ?? '') === 'classes_enseignants' ? 'Déjà liés' : 'Ignorés (doublons)' ?></span>
            </div>
            <?php endif ?>
            <?php if (count($errors) > 0): ?>
            <div class="logieval-import-stat logieval-import-stat-err">
                <span class="logieval-import-stat-num"><?= count($errors) ?></span>
                <span>Erreurs</span>
            </div>
            <?php endif ?>
        </div>

        <?php if ($imported > 0 || $updated > 0): ?>
        <div class="logieval-import-success">
            ✅ Import terminé : <?= $imported ?> entrée<?= $imported > 1 ? 's' : '' ?> importée<?= $imported > 1 ? 's' : '' ?>
            <?= $updated > 0 ? ', '.$updated.' mise'.(($updated>1)?'s':'').' à jour' : '' ?>.
        </div>
        <?php endif ?>
        <?php if (!empty($import_results['classes_created'])): ?>
        <div class="logieval-import-success">
            🏫 <?= count($import_results['classes_created']) ?> classe(s) créée(s) : <?= esc_html(implode(', ', $import_results['classes_created'])) ?>
        </div>
        <?php endif ?>

        <?php if (!empty($import_results['teachers_created'])): ?>
        <div class="logieval-import-success" style="background:#FEF3C7;color:#92400E">
            👤 <?= count($import_results['teachers_created']) ?> compte(s) enseignant créé(s), inactif(s) : <?= esc_html(implode(', ', $import_results['teachers_created'])) ?>.
            Complétez leur e-mail dans LogiEval › Enseignants, puis activez-les.
        </div>
        <?php endif ?>

        <?php if (!empty($errors)): ?>
        <h3 style="color:#B91C1C;margin-top:20px">⚠ Lignes en erreur (<?= count($errors) ?>)</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="60">Ligne</th>
                    <th width="200">Données</th>
                    <th>Raison de l'erreur</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errors as $err): ?>
                <tr>
                    <td><?= (int)$err['line'] ?></td>
                    <td><small><?= esc_html(implode(', ', array_slice($err['data'], 0, 3))) ?></small></td>
                    <td><span style="color:#B91C1C">❌ <?= esc_html($err['reason']) ?></span></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>

        <?php if (!empty($import_results['preview'])): ?>
        <h3 style="margin-top:20px">✅ Entrées importées</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php foreach (array_keys($import_results['preview'][0]) as $col): ?>
                    <th><?= esc_html($col) ?></th>
                    <?php endforeach ?>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($import_results['preview'] as $row): ?>
                <tr>
                    <?php foreach ($row as $k => $v): ?>
                        <?php if ($k !== '_status'): ?>
                        <td><?= esc_html($v) ?></td>
                        <?php endif ?>
                    <?php endforeach ?>
                    <td>
                        <?php if (($row['_status'] ?? '') === 'updated'): ?>
                            <span style="color:#D97706">↺ Mis à jour</span>
                        <?php elseif (($row['_status'] ?? '') === 'deja'): ?>
                            <span style="color:#64748B">= Déjà lié</span>
                        <?php elseif (($row['_status'] ?? '') === 'maitre'): ?>
                            <span style="color:#D97706">👑 Devenu maître de classe</span>
                        <?php elseif (($row['_status'] ?? '') === 'new_inactive'): ?>
                            <span style="color:#475569">✓ Importé inactif</span>
                        <?php else: ?>
                            <span style="color:#166534">✓ Importé</span>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>

    <?php endif ?>
</div>
<?php endif ?>

</div><!-- /wrap -->

<style>
.logieval-import-section { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:24px 28px; margin-bottom:24px; }
.logieval-import-section h2 { margin-top:0; color:#1A1A2E; font-size:1.1rem; border-left:4px solid #1B6B3A; padding-left:12px; }

.logieval-template-grid { display:flex; gap:20px; flex-wrap:wrap; margin-top:16px; }
.logieval-template-card { background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:20px 24px; flex:1; min-width:260px; }
.logieval-template-card h3 { margin:8px 0 6px; color:#1A1A2E; }
.logieval-template-card p { color:#555; font-size:.88rem; margin:0 0 14px; }
.logieval-template-icon { font-size:2rem; }
.logieval-template-btns { display:flex; gap:8px; flex-wrap:wrap; }

.logieval-dropzone { border:2px dashed #CBD5E1; border-radius:8px; text-align:center; padding:32px 20px; cursor:pointer; transition:all .2s; background:#F8FAFC; }
.logieval-dropzone.dragover { border-color:#1B6B3A; background:#F0FDF4; }
.logieval-dropzone.has-file { border-style:solid; border-color:#1B6B3A; background:#F0FDF4; cursor:default; }

.logieval-import-summary { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
.logieval-import-stat { text-align:center; padding:16px 20px; border-radius:8px; min-width:100px; }
.logieval-import-stat span:first-child { display:block; }
.logieval-import-stat-num { font-size:2rem; font-weight:700; }
.logieval-import-stat-total   { background:#EEF2FF; color:#3730A3; }
.logieval-import-stat-ok      { background:#DCFCE7; color:#166534; }
.logieval-import-stat-updated { background:#FEF9C3; color:#854D0E; }
.logieval-import-stat-skip    { background:#F1F5F9; color:#475569; }
.logieval-import-stat-err     { background:#FEE2E2; color:#B91C1C; }
.logieval-import-success { background:#DCFCE7; border:1px solid #BBF7D0; color:#166534; padding:12px 16px; border-radius:6px; font-weight:600; }
.logieval-import-error   { background:#FEE2E2; border:1px solid #FECACA; color:#B91C1C; padding:12px 16px; border-radius:6px; }
</style>

<script>
function updateTypeHint(type) {
    var hints = {
        eleves:       'Colonnes requises : <code>nom</code>, <code>prenom</code>, <code>num_eleve</code> (optionnel), <code>classe</code>',
        enseignants:  'Colonnes requises : <code>nom</code>, <code>prenom</code>, <code>email</code>',
        classes_enseignants: 'Colonne 1 : <code>classe</code> · colonne 2 : <code>maître de classe</code> · colonnes 3 et suivantes : autres enseignants (« Prénom Nom », « NOM Prénom » ou e-mail). Les enseignants inconnus sont créés <strong>inactifs</strong>, avec un e-mail à compléter avant activation. Aucun lien existant n’est retiré.'
    };
    document.getElementById('type-hint').innerHTML = hints[type] || '';
    var ch = document.getElementById('classes-hint');
    if (ch) ch.style.display = (type === 'eleves') ? 'block' : 'none';
    var th = document.getElementById('teacher-import-hint');
    if (th) th.style.display = (type === 'enseignants') ? 'block' : 'none';
}

jQuery(function($){
    var $dz    = $('#logieval-dropzone');
    var $input = $('#import-file-input');

    // File input change
    $input.on('change', function(){
        showFile(this.files[0]);
    });

    // Drag & drop
    $dz.on('dragover', function(e){ e.preventDefault(); $(this).addClass('dragover'); });
    $dz.on('dragleave', function(){ $(this).removeClass('dragover'); });
    $dz.on('drop', function(e){
        e.preventDefault(); $(this).removeClass('dragover');
        var f = e.originalEvent.dataTransfer.files[0];
        if (f) {
            // Transfer to input
            var dt = new DataTransfer();
            dt.items.add(f);
            $input[0].files = dt.files;
            showFile(f);
        }
    });

    // Click on dropzone (not button)
    $dz.on('click', function(e){
        if (!$(e.target).is('button')) $input.click();
    });

    // Clear
    $('#btn-clear-file').on('click', function(e){
        e.stopPropagation();
        $input.val('');
        $dz.removeClass('has-file');
        $('#dropzone-content').show();
        $('#dropzone-file').hide();
    });

    function showFile(f) {
        if (!f) return;
        var ext = f.name.split('.').pop().toLowerCase();
        var icon = ext === 'csv' ? '📋' : '📊';
        var size = f.size > 1024*1024 ? (f.size/1024/1024).toFixed(1)+' MB' : Math.round(f.size/1024)+' KB';
        $('#file-icon').text(icon);
        $('#file-name').text(f.name);
        $('#file-size').text(size);
        $dz.addClass('has-file');
        $('#dropzone-content').hide();
        $('#dropzone-file').show();
    }

    // Submit feedback
    $('#logieval-import-form').on('submit', function(){
        if (!$input[0].files.length) {
            alert('Veuillez sélectionner un fichier.');
            return false;
        }
        $('#btn-import').prop('disabled',true).text('⏳ Import en cours…');
    });
});
</script>
