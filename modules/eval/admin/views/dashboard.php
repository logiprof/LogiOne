<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap logieval-wrap">
<img class="logieval-admin-logo" src="<?= esc_url(LOGIEVAL_URL . 'assets/logieval-logo.svg') ?>" alt="LogiEval by LogiProf" width="300" height="80">
<h1>Tableau de bord</h1>

<?php
global $wpdb;
$t = LOGIEVAL_Database::tables();
// ── Mise en route : chaque étape se coche toute seule quand elle est faite
$n_cls      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['classes']}");
$n_ens_act  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['enseignants']} WHERE actif=1");
$n_ens_off  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['enseignants']} WHERE actif=0");
$n_cls_ens  = (int) $wpdb->get_var("SELECT COUNT(DISTINCT classe_id) FROM {$t['classe_enseignant']}");
$n_cls_mtr  = (int) $wpdb->get_var("SELECT COUNT(DISTINCT classe_id) FROM {$t['classe_enseignant']} WHERE est_maitre=1");
$n_cls_elv  = (int) $wpdb->get_var("SELECT COUNT(DISTINCT classe_id) FROM {$t['eleves']}");
$n_evals    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['evaluations']}");
$login_page = (int) get_option('logieval_login_page', 0);
$page_ok    = $login_page && get_post_status($login_page) === 'publish' && has_shortcode((string) get_post_field('post_content', $login_page), 'logieval_login');
$u = fn($slug) => esc_url(admin_url('admin.php?page=' . $slug));
$steps = [
    ['Page de connexion des enseignants', $page_ok, $page_ok ? 'Page « ' . get_the_title($login_page) . ' » choisie.' : 'Créez une page avec [logieval_login], puis choisissez-la dans Réglages.', $u('logieval-settings'), 'Réglages'],
    ['Classes', $n_cls > 0, $n_cls ? "$n_cls classe(s)." : 'Créez vos classes.', $u('logieval-classes'), 'Classes'],
    ['Enseignants activés', $n_ens_act > 0, $n_ens_act ? "$n_ens_act actif(s)" . ($n_ens_off ? ", $n_ens_off pas encore activé(s) (ils ne reçoivent leur accès qu'à l'activation)." : '.') : 'Importez ou ajoutez les enseignants, puis activez-les : ils reçoivent alors leur identifiant par e-mail.', $u('logieval-enseignants'), 'Enseignants'],
    ['Enseignants affectés aux classes', $n_cls > 0 && $n_cls_ens >= $n_cls, "$n_cls_ens classe(s) sur $n_cls ont des enseignants.", $u('logieval-classes'), 'Classes'],
    ['Un maître de classe par classe', $n_cls > 0 && $n_cls_mtr >= $n_cls, "$n_cls_mtr classe(s) sur $n_cls ont un maître. Dans Classes, cliquez « Nommer maître ».", $u('logieval-classes'), 'Classes'],
    ['Élèves', $n_cls > 0 && $n_cls_elv >= $n_cls, "$n_cls_elv classe(s) sur $n_cls ont des élèves.", $u('logieval-import'), 'Importer'],
    ['Première évaluation', $n_evals > 0, $n_evals ? "$n_evals évaluation(s) créée(s)." : 'Le maître de classe la crée depuis son espace, ou vous la créez ici.', $u('logieval-evaluations'), 'Évaluations'],
];
$steps_done = count(array_filter($steps, fn($s) => $s[1]));
?>
<?php if ($steps_done < count($steps)): ?>
<div class="logieval-card logieval-setup" style="margin:16px 0 22px">
    <h2 style="margin-top:0">Mise en route · <?= (int) $steps_done ?> étape<?= $steps_done > 1 ? 's' : '' ?> sur <?= count($steps) ?></h2>
    <ol class="logieval-steps">
    <?php foreach ($steps as $i => [$label, $ok, $help, $url, $btn]): ?>
        <li class="<?= $ok ? 'is-done' : '' ?>">
            <span class="logieval-step-mark"><?= $ok ? '✓' : (int) ($i + 1) ?></span>
            <span class="logieval-step-text"><strong><?= esc_html($label) ?></strong><small><?= esc_html($help) ?></small></span>
            <?php if (!$ok): ?><a class="button" href="<?= $url ?>"><?= esc_html($btn) ?></a><?php endif ?>
        </li>
    <?php endforeach ?>
    </ol>
</div>
<?php endif ?>

<div class="logieval-card" style="margin:16px 0 22px;padding:16px 18px">
    <h2 style="margin-top:0">Navigation LogiEval</h2>
    <p>
        <a class="button button-primary" href="<?= esc_url(admin_url('admin.php?page=logieval-evaluations')) ?>">Évaluations</a>
        <a class="button" href="<?= esc_url(admin_url('admin.php?page=logieval-classes')) ?>">Classes</a>
        <a class="button" href="<?= esc_url(admin_url('admin.php?page=logieval-eleves')) ?>">Élèves</a>
        <a class="button" href="<?= esc_url(admin_url('admin.php?page=logieval-enseignants')) ?>">Enseignants</a>
        <a class="button" href="<?= esc_url(admin_url('admin.php?page=logieval-objectifs')) ?>">Objectifs</a>
        <a class="button" href="<?= esc_url(admin_url('admin.php?page=logieval-settings')) ?>">Réglages</a>
        <a class="button" href="<?= esc_url(admin_url('admin.php?page=logieval-import')) ?>">Import</a>
    </p>
</div>

<?php
global $wpdb;
$t = LOGIEVAL_Database::tables();
$nb_classes    = $wpdb->get_var("SELECT COUNT(*) FROM {$t['classes']}");
$nb_eleves     = $wpdb->get_var("SELECT COUNT(*) FROM {$t['eleves']}");
$nb_ens        = $wpdb->get_var("SELECT COUNT(*) FROM {$t['enseignants']}");
$nb_eval_act   = $wpdb->get_var("SELECT COUNT(*) FROM {$t['evaluations']} WHERE statut='active'");
$nb_eval_clos  = $wpdb->get_var("SELECT COUNT(*) FROM {$t['evaluations']} WHERE statut='cloturee'");
$evals_actives = $wpdb->get_results(
    "SELECT ev.*, cl.nom AS classe_nom,
            COUNT(ee.id) AS nb_ens,
            SUM(ee.statut='termine') AS nb_termine
     FROM {$t['evaluations']} ev
     INNER JOIN {$t['classes']} cl ON cl.id = ev.classe_id
     LEFT JOIN {$t['eval_enseignant']} ee ON ee.evaluation_id = ev.id
     WHERE ev.statut = 'active'
     GROUP BY ev.id
     ORDER BY ev.date_echeance ASC", ARRAY_A
);
?>

<div class="logieval-stats-grid">
    <div class="logieval-stat-card">
        <span class="logieval-stat-num"><?= esc_html($nb_classes) ?></span>
        <span class="logieval-stat-label">Classes</span>
    </div>
    <div class="logieval-stat-card">
        <span class="logieval-stat-num"><?= esc_html($nb_eleves) ?></span>
        <span class="logieval-stat-label">Élèves</span>
    </div>
    <div class="logieval-stat-card">
        <span class="logieval-stat-num"><?= esc_html($nb_ens) ?></span>
        <span class="logieval-stat-label">Enseignants</span>
    </div>
    <div class="logieval-stat-card logieval-stat-accent">
        <span class="logieval-stat-num"><?= esc_html($nb_eval_act) ?></span>
        <span class="logieval-stat-label">Évaluations actives</span>
    </div>
    <div class="logieval-stat-card">
        <span class="logieval-stat-num"><?= esc_html($nb_eval_clos) ?></span>
        <span class="logieval-stat-label">Clôturées</span>
    </div>
</div>

<h2>Évaluations en cours</h2>
<?php if (empty($evals_actives)): ?>
    <p class="logieval-empty">Aucune évaluation active.</p>
<?php else: ?>
<table class="wp-list-table widefat fixed striped logieval-table">
<thead>
    <tr>
        <th>Classe</th><th>Semestre</th><th>Échéance</th>
        <th>Progression</th><th>Actions</th>
    </tr>
</thead>
<tbody>
<?php foreach ($evals_actives as $ev):
    $pct = $ev['nb_ens'] > 0 ? round(($ev['nb_termine'] / $ev['nb_ens']) * 100) : 0;
    $days_left = (int) floor((strtotime($ev['date_echeance']) - time()) / 86400);
    $urgency   = $days_left <= 3 ? 'logieval-urgent' : ($days_left <= 7 ? 'logieval-warning' : '');
?>
<tr>
    <td><strong><?= esc_html($ev['classe_nom']) ?></strong></td>
    <td><?= esc_html($ev['semestre']) ?></td>
    <td class="<?= $urgency ?>">
        <?= esc_html(date_i18n('d.m.Y', strtotime($ev['date_echeance']))) ?>
        <?php if ($days_left >= 0): ?>
            <small>(J-<?= $days_left ?>)</small>
        <?php else: ?>
            <small class="logieval-urgent">En retard</small>
        <?php endif ?>
    </td>
    <td>
        <div class="logieval-progress-bar">
            <div class="logieval-progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
        <small><?= (int)$ev['nb_termine'] ?>/<?= (int)$ev['nb_ens'] ?> enseignants</small>
    </td>
    <td>
        <button class="button button-small logieval-btn-close-eval" data-id="<?= $ev['id'] ?>">Clôturer</button>
        <a class="button button-small" href="<?= admin_url('admin.php?page=logieval-evaluations&eval_id='.$ev['id']) ?>">Détail</a>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<?php endif ?>
</div>

<script>
jQuery(function($){
    $('.logieval-btn-close-eval').on('click', function(){
        if (!confirm('Forcer la clôture de cette évaluation ?')) return;
        var id = $(this).data('id');
        $.post(logievalAdmin.ajax_url, {
            action:'logieval_admin', nonce:logievalAdmin.nonce,
            sub_action:'close_evaluation', id:id
        }, logievalAfter);
    });
});
</script>
