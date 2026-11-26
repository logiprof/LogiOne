<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNE_App
{
    private static ?GNE_App $instance = null;
    private const MISSING_GRADE_REMINDER_HOOK = 'gne_missing_grade_reminder';

    public static function instance(): GNE_App
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_shortcode('gestion_notes', array($this, 'render_shortcode'));
        add_shortcode('loginotes', array($this, 'render_shortcode'));
        add_action('init', array($this, 'register_assets'));
        add_action('init', array($this, 'ensure_missing_grade_reminder_schedule'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action(self::MISSING_GRADE_REMINDER_HOOK, array($this, 'send_missing_grade_reminders'));

        $actions = array(
            'save_class',
            'import_students',
            'delete_class',
            'save_student',
            'delete_student',
            'save_branch',
            'delete_branch',
            'save_semester',
            'delete_semester',
            'save_settings',
            'save_evaluation',
            'save_agapepe_status',
            'delete_evaluation',
            'export_evaluation_pdf',
            'export_semester_pdf',
            'download_progress_pdf',
        );

        foreach ($actions as $action) {
            add_action('admin_post_gne_' . $action, array($this, 'handle_' . $action));
        }

        add_action('admin_post_nopriv_gne_download_progress_pdf', array($this, 'handle_download_progress_pdf_login_redirect'));
    }

    public function register_assets(): void
    {
        // LogiOne 1.52.4 : la version suit la date des fichiers, pour qu'un cache (navigateur, FlyingPress…) ne serve
        // jamais une ancienne copie après une mise à jour.
        $dir = plugin_dir_path(dirname(__FILE__)) . 'assets/';
        wp_register_style('gne-app', GNE_URL . 'assets/app.css', array(), GNE_VERSION . '.' . (int) @filemtime($dir . 'app.css'));
        wp_register_script('gne-app', GNE_URL . 'assets/app.js', array(), GNE_VERSION . '.' . (int) @filemtime($dir . 'app.js'), true);
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            'LogiNotes',
            'LogiNotes',
            'lp_notes_view',
            'gne_app',
            array($this, 'render_admin_page'),
            'dashicons-welcome-write-blog',
            27
        );

        add_submenu_page(
            'gne_app',
            'Évaluations',
            'Évaluations',
            'lp_notes_view',
            'gne_app',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gne_app',
            'Groupes classes',
            'Groupes classes',
            'lp_notes_view',
            'gne_classes',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gne_app',
            'Branches',
            'Branches',
            'lp_notes_view',
            'gne_branches',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gne_app',
            'Semestres',
            'Semestres',
            'lp_notes_view',
            'gne_semesters',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gne_app',
            'Relevé',
            'Relevé',
            'lp_notes_view',
            'gne_report',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gne_app',
            'AGAPEPE',
            'AGAPEPE',
            'lp_notes_view',
            'gne_agapepe',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gne_app',
            'Paramètres',
            'Paramètres',
            'lp_notes_view',
            'gne_settings',
            array($this, 'render_admin_page')
        );
    }

    public function ensure_missing_grade_reminder_schedule(): void
    {
        // Un seul rappel planifié pour tous les enseignants : actif si au moins un espace l'a activé.
        $settings = class_exists('LogiPilot_Spaces') ? LogiPilot_Spaces::reminder_settings('notes', array('GNE_Database', 'get_settings'), array('missing_grade_reminder_enabled')) : GNE_Database::get_settings();

        if (empty($settings['missing_grade_reminder_enabled'])) {
            wp_clear_scheduled_hook(self::MISSING_GRADE_REMINDER_HOOK);
            return;
        }

        if (!wp_next_scheduled(self::MISSING_GRADE_REMINDER_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::MISSING_GRADE_REMINDER_HOOK);
        }
    }

    private function reschedule_missing_grade_reminder(): void
    {
        wp_clear_scheduled_hook(self::MISSING_GRADE_REMINDER_HOOK);
        $this->ensure_missing_grade_reminder_schedule();
    }

    public function render_admin_page(): void
    {
        echo '<div class="wrap gne-admin-wrap">';
        echo $this->render_app();
        echo '</div>';
    }

    public function render_shortcode(): string
    {
        return $this->render_app();
    }

    private function render_app(): string
    {
        if (!is_user_logged_in()) {
            return '<div class="gne-access">Veuillez vous connecter pour accéder à la gestion des notes.</div>';
        }

        if (!$this->can_manage()) {
            return '<div class="gne-access">Accès refusé.</div>';
        }

        wp_enqueue_style('gne-app');
        wp_enqueue_script('gne-app');

        $tab = isset($_GET['gne_tab']) ? sanitize_key(wp_unslash($_GET['gne_tab'])) : $this->tab_from_admin_page();
        $allowed_tabs = array('evaluations', 'classes', 'branches', 'semesters', 'report', 'agapepe', 'settings');
        if (!in_array($tab, $allowed_tabs, true)) {
            $tab = 'evaluations';
        }

        ob_start();
        ?>
        <div class="gne-app">
            <header class="gne-header">
                <div>
                    <p class="gne-kicker">Interface autonome</p>
                    <h1>LogiNotes</h1>
                </div>
            </header>

            <?php $this->render_notice(); ?>

            <nav class="gne-tabs" aria-label="Navigation gestion des notes">
                <?php
                $tabs = array(
                    'evaluations' => 'Évaluations',
                    'classes' => 'Groupes classes',
                    'branches' => 'Branches',
                    'semesters' => 'Semestres',
                    'report' => 'Relevé',
                    'agapepe' => 'AGAPEPE',
                    'settings' => 'Paramètres',
                );
                foreach ($tabs as $key => $label) :
                    ?>
                    <a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->page_url(array('gne_tab' => $key))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <main class="gne-main">
                <?php
                if ($tab === 'evaluations' && isset($_GET['evaluation_id'])) {
                    $this->render_evaluation_detail((int) $_GET['evaluation_id']);
                } elseif ($tab === 'classes' && isset($_GET['class_id'])) {
                    $this->render_class_detail((int) $_GET['class_id']);
                } else {
                    switch ($tab) {
                        case 'classes':
                            $this->render_classes();
                            break;
                        case 'branches':
                            $this->render_branches();
                            break;
                        case 'semesters':
                            $this->render_semesters();
                            break;
                        case 'report':
                            $this->render_report();
                            break;
                        case 'agapepe':
                            $this->render_agapepe();
                            break;
                        case 'settings':
                            $this->render_settings();
                            break;
                        case 'evaluations':
                        default:
                            $this->render_evaluations();
                            break;
                    }
                }
                ?>
            </main>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_notice(): void
    {
        if (empty($_GET['gne_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['gne_notice']));
        $messages = array(
            'class_saved' => array('type' => 'success', 'text' => 'Groupe classe enregistré.'),
            'class_deleted' => array('type' => 'success', 'text' => 'Groupe classe supprimé avec ses élèves, évaluations et notes.'),
            'class_exists' => array('type' => 'error', 'text' => 'Un groupe classe porte déjà ce numéro.'),
            'students_imported' => array('type' => 'success', 'text' => 'Liste d’élèves importée.'),
            'import_empty' => array('type' => 'error', 'text' => 'Aucune ligne valide à importer.'),
            'import_failed' => array('type' => 'error', 'text' => 'La liste n’a pas pu être importée.'),
            'student_saved' => array('type' => 'success', 'text' => 'Élève enregistré.'),
            'student_deleted' => array('type' => 'success', 'text' => 'Élève supprimé avec ses notes.'),
            'branch_saved' => array('type' => 'success', 'text' => 'Branche enregistrée.'),
            'branch_deleted' => array('type' => 'success', 'text' => 'Branche supprimée.'),
            'branch_exists' => array('type' => 'error', 'text' => 'Une branche porte déjà ce nom.'),
            'branch_in_use' => array('type' => 'error', 'text' => 'Cette branche contient déjà des évaluations et ne peut pas être supprimée.'),
            'semester_saved' => array('type' => 'success', 'text' => 'Semestre enregistré.'),
            'semester_deleted' => array('type' => 'success', 'text' => 'Semestre supprimé.'),
            'semester_in_use' => array('type' => 'error', 'text' => 'Ce semestre contient déjà des évaluations et ne peut pas être supprimé.'),
            'evaluation_saved' => array('type' => 'success', 'text' => 'Évaluation et notes enregistrées.'),
            'evaluation_deleted' => array('type' => 'success', 'text' => 'Évaluation supprimée.'),
            'agapepe_saved' => array('type' => 'success', 'text' => 'Statut AGAPEPE enregistré.'),
            'pdf_failed' => array('type' => 'error', 'text' => 'Le justificatif PDF n’a pas pu être enregistré.'),
            'settings_saved' => array('type' => 'success', 'text' => 'Paramètres enregistrés.'),
            'missing_data' => array('type' => 'error', 'text' => 'Certaines informations obligatoires sont manquantes ou invalides.'),
            'missing_email' => array('type' => 'error', 'text' => 'Indiquez au moins une adresse e-mail valide pour recevoir les alertes.'),
            'not_found' => array('type' => 'error', 'text' => 'Élément introuvable.'),
        );

        if (!isset($messages[$notice])) {
            return;
        }

        printf(
            '<div class="gne-notice gne-notice-%s">%s</div>',
            esc_attr($messages[$notice]['type']),
            esc_html($messages[$notice]['text'])
        );
    }

    private function render_evaluations(): void
    {
        global $wpdb;

        $classes = $this->classes_for_form();
        $semesters = $this->semesters_for_form();
        $branches = $this->branches_for_form();
        $students_by_class = $this->students_grouped_by_class();
        $comparison_options = $this->evaluation_comparison_options();
        $selected_comparison_id = isset($_GET['compare_evaluation_id']) ? absint($_GET['compare_evaluation_id']) : 0;
        $stats = array(
            'classes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . GNE_Database::table('classes') . " WHERE status = 'active'"),
            'students' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . GNE_Database::table('students') . " WHERE status = 'active'"),
            'branches' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . GNE_Database::table('branches') . " WHERE status = 'active'"),
            'semesters' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . GNE_Database::table('semesters') . " WHERE status = 'active'"),
            'evaluations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . GNE_Database::table('evaluations')),
        );
        ?>
        <section class="gne-stats">
            <a class="gne-stat-card" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'classes'))); ?>"><strong><?php echo esc_html((string) $stats['classes']); ?></strong><span>Groupes classes</span></a>
            <a class="gne-stat-card" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'classes'))); ?>"><strong><?php echo esc_html((string) $stats['students']); ?></strong><span>Élèves</span></a>
            <a class="gne-stat-card" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'branches'))); ?>"><strong><?php echo esc_html((string) $stats['branches']); ?></strong><span>Branches</span></a>
            <a class="gne-stat-card" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'semesters'))); ?>"><strong><?php echo esc_html((string) $stats['semesters']); ?></strong><span>Semestres actifs</span></a>
            <a class="gne-stat-card" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations'))); ?>"><strong><?php echo esc_html((string) $stats['evaluations']); ?></strong><span>Évaluations saisies</span></a>
        </section>

        <?php
        // Tableau des évaluations : une colonne par groupe classe, les plus récentes en haut.
        $all_evaluations = $this->recent_evaluations(5000);
        $columns = array();
        foreach ($classes as $class) {
            $columns[(int) $class->id] = array('name' => (string) $class->name, 'items' => array());
        }
        foreach ($all_evaluations as $evaluation) {
            $class_id = (int) $evaluation->class_id;
            if (!isset($columns[$class_id])) {
                $columns[$class_id] = array('name' => trim((string) $evaluation->class_name) !== '' ? (string) $evaluation->class_name : 'Sans groupe classe', 'items' => array());
            }
            $columns[$class_id]['items'][] = $evaluation;
        }
        // Groupes classes par ordre de nom ; ceux qui n'ont encore aucune évaluation viennent à la fin.
        uasort($columns, static fn(array $a, array $b): int => (int) !$a['items'] <=> (int) !$b['items'] ?: strnatcasecmp($a['name'], $b['name']));
        $board_names = array();
        foreach ($all_evaluations as $evaluation) {
            $name = trim((string) $evaluation->module_name);
            if ($name !== '') {
                $board_names[strtolower(remove_accents($name))] = $name;
            }
        }
        natcasesort($board_names);
        $open_new = !empty($_GET['gne_new']);
        $visible_per_column = 6;
        ?>
        <section class="gne-board-head">
            <div>
                <h2>Évaluations</h2>
                <p class="gne-muted">Une colonne par groupe classe, de la plus récente à la plus ancienne. Cliquez sur une évaluation pour saisir ou modifier ses notes.</p>
            </div>
            <a class="gne-button gne-button-primary gne-button-hero" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations', 'gne_new' => 1)) . '#gne-new'); ?>" data-gne-new-toggle aria-controls="gne-new" aria-expanded="<?php echo $open_new ? 'true' : 'false'; ?>">
                <span class="gne-plus" aria-hidden="true">+</span> Nouvelle évaluation
            </a>
        </section>

        <section class="gne-panel gne-new-panel" id="gne-new" data-gne-new-panel<?php echo $open_new ? '' : ' hidden'; ?>>
            <div class="gne-panel-title">
                <div>
                    <h2>Nouvelle évaluation</h2>
                    <p class="gne-muted">La note est calculée avec la formule points ajustés x 5 / points totaux + 1, puis arrondie au 0.5.</p>
                </div>
                <a class="gne-button" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations'))); ?>" data-gne-new-close>Fermer</a>
            </div>
            <?php
            ob_start();
            $this->render_evaluation_form(null, $classes, $semesters, $branches, $students_by_class, array());
            $new_form = (string) ob_get_clean();
            $preset_class = absint($_GET['gne_class'] ?? 0);
            if ($preset_class > 0) {
                $new_form = (string) preg_replace('/(<select name="class_id"[^>]*>.*?<option value="' . $preset_class . '")/s', '$1 selected', $new_form, 1);
            }
            echo $new_form; // phpcs:ignore -- formulaire déjà échappé
            ?>
        </section>

        <section class="gne-panel gne-board-panel" data-gne-board>
            <?php if (!$all_evaluations && !$columns) : ?>
                <p class="gne-empty">Aucune évaluation pour le moment. Cliquez sur « Nouvelle évaluation » pour commencer.</p>
            <?php else : ?>
                <div class="gne-filter gne-board-filter">
                    <label>Groupe classe
                        <select data-gne-board-class>
                            <option value="">Tous les groupes classes</option>
                            <?php foreach ($columns as $class_id => $column) : ?>
                                <option value="<?php echo esc_attr((string) $class_id); ?>"><?php echo esc_html($column['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Évaluation
                        <select data-gne-board-evaluation>
                            <option value="">Toutes les évaluations</option>
                            <?php foreach ($board_names as $board_name) : ?>
                                <option value="<?php echo esc_attr($board_name); ?>"><?php echo esc_html($board_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="gne-empty" hidden data-gne-board-empty>Aucune évaluation ne correspond aux filtres.</p>
                </div>
                <div class="gne-board" data-gne-board-columns>
                    <?php foreach ($columns as $class_id => $column) : ?>
                        <section class="gne-board-column" data-gne-board-column data-gne-class-id="<?php echo esc_attr((string) $class_id); ?>" aria-label="<?php echo esc_attr($column['name']); ?>">
                            <header class="gne-board-column-head">
                                <h3><?php echo esc_html($column['name']); ?></h3>
                                <span class="gne-badge gne-badge-neutral" title="Évaluations"><?php echo esc_html((string) count($column['items'])); ?></span>
                                <?php if ($class_id > 0) : ?>
                                    <a class="gne-board-add" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations', 'gne_new' => 1, 'gne_class' => (int) $class_id)) . '#gne-new'); ?>" data-gne-new-toggle data-gne-new-class="<?php echo esc_attr((string) $class_id); ?>" title="<?php echo esc_attr('Nouvelle évaluation pour ' . $column['name']); ?>"><span aria-hidden="true">+</span><span class="screen-reader-text"><?php echo esc_html('Nouvelle évaluation pour ' . $column['name']); ?></span></a>
                                <?php endif; ?>
                            </header>
                            <?php if (!$column['items']) : ?>
                                <p class="gne-board-none">Aucune évaluation.</p>
                            <?php endif; ?>
                            <?php foreach (array_chunk($column['items'], $visible_per_column) as $chunk_index => $chunk) : ?>
                                <?php if ($chunk_index === 1) : ?>
                                    <details class="gne-board-more" data-gne-board-more><summary>Afficher les <?php echo esc_html((string) (count($column['items']) - $visible_per_column)); ?> plus anciennes</summary>
                                <?php endif; ?>
                                <?php foreach ($chunk as $evaluation) : ?>
                                    <?php $progress_status = $this->evaluation_progress_status($evaluation); ?>
                                    <a class="gne-board-card" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => (int) $evaluation->id))); ?>" data-gne-board-item data-gne-class-id="<?php echo esc_attr((string) $class_id); ?>" data-gne-evaluation-name="<?php echo esc_attr((string) $evaluation->module_name); ?>">
                                        <span class="gne-board-date"><?php echo esc_html($this->board_date((string) $evaluation->evaluation_date)); ?></span>
                                        <strong><?php echo esc_html($evaluation->module_name); ?></strong>
                                        <small><?php echo esc_html(implode(' · ', array_filter(array(trim((string) ($evaluation->branch_name ?? '')), trim((string) ($evaluation->semester_name ?? ''))), static fn($part): bool => $part !== '' && $part !== '-'))); ?></small>
                                        <span class="gne-row-metrics">
                                            <span class="<?php echo esc_attr('gne-progress-pill ' . $progress_status['class']); ?>"><?php echo esc_html($progress_status['label']); ?></span>
                                            <span class="gne-badge"><?php echo esc_html((string) (int) $evaluation->grade_count); ?> note<?php echo (int) $evaluation->grade_count > 1 ? 's' : ''; ?></span>
                                            <span class="gne-badge gne-badge-neutral">Moy. <?php echo esc_html($this->format_average($evaluation->avg_note)); ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if (count($column['items']) > $visible_per_column) : ?>
                                </details>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php $this->render_evaluation_comparison($comparison_options, $selected_comparison_id); ?>
        <?php
    }

    private function render_evaluation_comparison(array $options, int $selected_evaluation_id): void
    {
        $comparison = $selected_evaluation_id > 0 ? $this->evaluation_comparison($selected_evaluation_id) : null;
        ?>
        <section class="gne-panel gne-panel-spaced">
            <div class="gne-panel-title">
                <div>
                    <h2>Comparaison par classe</h2>
                    <p class="gne-muted">Sélectionnez une évaluation pour comparer la moyenne obtenue dans chaque groupe classe.</p>
                </div>
            </div>

            <?php if (!$options) : ?>
                <p class="gne-empty">Aucune évaluation n'est encore disponible pour la comparaison.</p>
            <?php else : ?>
                <form class="gne-filter gne-comparison-filter" method="get">
                    <?php $this->hidden_page_fields(array('gne_tab' => 'evaluations')); ?>
                    <label>Évaluation
                        <select name="compare_evaluation_id" required>
                            <option value="">Choisir une évaluation</option>
                            <?php foreach ($options as $option) : ?>
                                <option value="<?php echo esc_attr((string) (int) $option->evaluation_id); ?>" <?php selected($selected_evaluation_id, (int) $option->evaluation_id); ?>>
                                    <?php echo esc_html($this->comparison_option_label($option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="gne-button gne-button-primary" type="submit">Comparer</button>
                </form>

                <?php if ($selected_evaluation_id <= 0) : ?>
                    <p class="gne-empty gne-panel-spaced">Choisissez une évaluation pour afficher les moyennes par classe.</p>
                <?php elseif (!$comparison) : ?>
                    <p class="gne-empty gne-panel-spaced">Évaluation introuvable pour la comparaison.</p>
                <?php else : ?>
                    <?php $rows = $comparison['rows']; ?>
                    <?php if (count($rows) < 2) : ?>
                        <p class="gne-empty gne-panel-spaced">Cette évaluation n'a pas encore été saisie dans plusieurs groupes classes.</p>
                    <?php endif; ?>

                    <div class="gne-table-wrap gne-panel-spaced">
                        <table class="gne-table gne-comparison-table">
                            <thead>
                                <tr>
                                    <th>Groupe classe</th>
                                    <th>Date</th>
                                    <th>Notes</th>
                                    <th>En attente</th>
                                    <th>Non faites</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row) : ?>
                                    <?php
                                    $average = $row->avg_note !== null ? (float) $row->avg_note : null;
                                    $bar_width = $average !== null ? min(100, max(0, ($average / 6) * 100)) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => (int) $row->id))); ?>">
                                                <strong><?php echo esc_html((string) $row->class_name); ?></strong>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($this->display_date((string) $row->evaluation_date)); ?></td>
                                        <td><?php echo esc_html((string) (int) $row->grade_count); ?></td>
                                        <td><?php echo esc_html((string) (int) $row->missing_count); ?></td>
                                        <td><?php echo esc_html((string) (int) $row->not_applicable_count); ?></td>
                                        <td>
                                            <strong><?php echo esc_html($this->format_average($row->avg_note)); ?></strong>
                                            <?php if ($average !== null) : ?>
                                                <span class="gne-comparison-meter" aria-hidden="true"><span style="width: <?php echo esc_attr(number_format($bar_width, 1, '.', '')); ?>%;"></span></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_evaluation_detail(int $evaluation_id): void
    {
        $evaluation = $this->evaluation($evaluation_id);

        if (!$evaluation) {
            echo '<p class="gne-empty">Évaluation introuvable.</p>';
            return;
        }

        $classes = $this->classes_for_form((int) $evaluation->class_id);
        $semesters = $this->semesters_for_form((int) $evaluation->semester_id);
        $branches = $this->branches_for_form((int) ($evaluation->branch_id ?? 0));
        $students_by_class = $this->students_grouped_by_class();
        $students_by_class[(int) $evaluation->class_id] = $this->participant_students((int) $evaluation->class_id, array($evaluation_id));
        $grade_map = $this->grade_map($evaluation_id);
        $stats = $this->evaluation_stats($evaluation_id);
        $progress_status = $this->evaluation_progress_status((object) array_merge((array) $evaluation, $stats));
        ?>
        <section class="gne-panel">
            <div class="gne-panel-title">
                <div>
                    <a class="gne-back" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations'))); ?>">&larr; Évaluations</a>
                    <h2><?php echo esc_html($evaluation->module_name); ?></h2>
                    <p class="gne-muted">
                        <?php echo esc_html($this->evaluation_context_label($evaluation)); ?>
                    </p>
                </div>
                <div class="gne-panel-actions">
                    <a class="gne-button gne-button-primary" href="<?php echo esc_url($this->export_evaluation_pdf_url($evaluation_id)); ?>">Exporter PDF</a>
                    <span class="<?php echo esc_attr('gne-progress-pill ' . $progress_status['class']); ?>"><?php echo esc_html($progress_status['label']); ?></span>
                    <span class="gne-badge"><?php echo esc_html((string) $stats['grade_count']); ?> note<?php echo $stats['grade_count'] > 1 ? 's' : ''; ?></span>
                    <span class="gne-badge gne-badge-neutral"><?php echo esc_html((string) $stats['missing_count']); ?> en attente</span>
                    <span class="gne-badge gne-badge-warning"><?php echo esc_html((string) $stats['cheating_count']); ?> triche<?php echo $stats['cheating_count'] > 1 ? 's' : ''; ?></span>
                    <span class="gne-badge gne-badge-neutral"><?php echo esc_html((string) $stats['makeup_count']); ?> rattrapage<?php echo $stats['makeup_count'] > 1 ? 's' : ''; ?></span>
                    <span class="gne-badge gne-badge-warning"><?php echo esc_html((string) $stats['not_applicable_count']); ?> non faite<?php echo $stats['not_applicable_count'] > 1 ? 's' : ''; ?></span>
                    <span class="gne-badge gne-badge-neutral">Moyenne <?php echo esc_html($this->format_average($stats['avg_note'])); ?></span>
                </div>
            </div>

            <?php $this->render_evaluation_form($evaluation, $classes, $semesters, $branches, $students_by_class, $grade_map); ?>
            <?php
            do_action('logipilot_notes_evaluation_detail', $evaluation);
            if (class_exists('LogiPilot_Snapshots')) {
                LogiPilot_Snapshots::render_panel('notes', $evaluation_id, $this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => $evaluation_id)));
            }
            ?>
            <?php $this->render_agapepe_status_form($evaluation, 'evaluation'); ?>

            <form class="gne-inline-form gne-danger-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gne-confirm="Supprimer cette évaluation et toutes les notes associées ?">
                <?php wp_nonce_field('gne_delete_evaluation_' . (int) $evaluation_id); ?>
                <input type="hidden" name="action" value="gne_delete_evaluation">
                <input type="hidden" name="evaluation_id" value="<?php echo esc_attr((string) $evaluation_id); ?>">
                <button class="gne-button gne-button-danger" type="submit">Supprimer l'évaluation</button>
            </form>
        </section>
        <?php
    }

    private function render_evaluation_form(?object $evaluation, array $classes, array $semesters, array $branches, array $students_by_class, array $grade_map): void
    {
        $evaluation_id = $evaluation ? (int) $evaluation->id : 0;
        $selected_class_id = $evaluation ? (int) $evaluation->class_id : 0;
        $selected_semester_id = $evaluation ? (int) $evaluation->semester_id : ($semesters ? (int) $semesters[0]->id : 0);
        $selected_branch_id = $evaluation ? (int) ($evaluation->branch_id ?? 0) : 0;
        $total_points = $evaluation ? $this->number_input_value($evaluation->total_points) : '';
        $module_name = $evaluation ? (string) $evaluation->module_name : '';
        $evaluation_date = $evaluation ? (string) $evaluation->evaluation_date : wp_date('Y-m-d');
        $locked_ids = $evaluation_id ? array_map('intval', (array) apply_filters('logipilot_notes_locked_students', array(), $evaluation_id)) : array();
        ?>
        <form class="gne-form gne-evaluation-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gne-evaluation-form>
            <?php wp_nonce_field('gne_save_evaluation'); ?>
            <input type="hidden" name="action" value="gne_save_evaluation">
            <input type="hidden" name="evaluation_id" value="<?php echo esc_attr((string) $evaluation_id); ?>">

            <div class="gne-form-grid">
                <label>Module
                    <input type="text" name="module_name" value="<?php echo esc_attr($module_name); ?>" placeholder="Module 122" required>
                </label>
                <label>Date de l'évaluation
                    <input type="date" name="evaluation_date" value="<?php echo esc_attr($this->date_input_value($evaluation_date)); ?>" required>
                </label>
                <label>Points totaux
                    <input type="number" name="total_points" value="<?php echo esc_attr($total_points); ?>" min="0.01" step="0.01" required data-gne-total-points>
                </label>
                <label>Semestre
                    <select name="semester_id" required>
                        <option value="">Choisir un semestre</option>
                        <?php foreach ($semesters as $semester) : ?>
                            <option value="<?php echo esc_attr((string) $semester->id); ?>" <?php selected($selected_semester_id, (int) $semester->id); ?>>
                                <?php echo esc_html($this->semester_name($semester)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Branche
                    <select name="branch_id">
                        <option value="">Aucune branche</option>
                        <?php foreach ($branches as $branch) : ?>
                            <option value="<?php echo esc_attr((string) $branch->id); ?>" <?php selected($selected_branch_id, (int) $branch->id); ?>>
                                <?php echo esc_html($branch->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="gne-wide-field">Justificatif bonus/malus PDF
                    <input type="file" name="progress_pdf" accept=".pdf,application/pdf">
                    <?php if ($evaluation && (string) ($evaluation->progress_pdf_path ?? '') !== '') : ?>
                        <small><a href="<?php echo esc_url($this->progress_pdf_download_url((int) $evaluation->id)); ?>">Télécharger le justificatif actuel</a></small>
                    <?php endif; ?>
                </label>
                <label class="gne-class-picker">Groupe classe
                    <select name="class_id" required data-gne-class-select>
                        <option value="">Sélectionner le groupe classe pour saisir les points</option>
                        <?php foreach ($classes as $class) : ?>
                            <option value="<?php echo esc_attr((string) $class->id); ?>" <?php selected($selected_class_id, (int) $class->id); ?>><?php echo esc_html($class->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php do_action('logipilot_notes_evaluation_form_fields', $evaluation); ?>
            </div>

            <?php if (!$classes) : ?>
                <p class="gne-empty">Créez d'abord un groupe classe.</p>
            <?php elseif (!$semesters) : ?>
                <p class="gne-empty">Créez d'abord un semestre dans l'onglet Semestres.</p>
            <?php else : ?>
                <div class="gne-grade-entry" data-gne-grade-entry>
                    <div class="gne-grade-entry-head">
                        <h3>Points par élève</h3>
                        <span class="gne-muted">Les champs vides restent en attente.</span>
                    </div>
                    <p class="gne-empty" data-gne-no-class <?php echo $selected_class_id > 0 ? 'hidden' : ''; ?>>Choisissez un groupe classe pour afficher ses élèves.</p>
                    <?php foreach ($classes as $class) : ?>
                        <?php $students = $students_by_class[(int) $class->id] ?? array(); ?>
                        <div class="gne-class-students" data-gne-class-students="<?php echo esc_attr((string) $class->id); ?>" <?php echo (int) $class->id === $selected_class_id ? '' : 'hidden'; ?>>
                            <?php if (!$students) : ?>
                                <p class="gne-empty">Aucun élève actif dans <?php echo esc_html($class->name); ?>.</p>
                            <?php else : ?>
                                <div class="gne-table-wrap">
                                    <table class="gne-table gne-grade-table">
                                        <colgroup>
                                            <col class="gne-grade-col-student">
                                            <col class="gne-grade-col-points">
                                            <col class="gne-grade-col-adjustment">
                                            <col class="gne-grade-col-makeup">
                                            <col class="gne-grade-col-status">
                                            <col class="gne-grade-col-base-note">
                                            <col class="gne-grade-col-note">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Élève</th>
                                                <th>Points obtenus</th>
                                                <th>+/-</th>
                                                <th>Rattrapage</th>
                                                <th>Évaluation</th>
                                                <th>Note sans +/-</th>
                                                <th>Note validée</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student) : ?>
                                                <?php
                                                $grade = $grade_map[(int) $student->id] ?? null;
                                                $grade_status = $this->grade_status_value($grade ? (string) ($grade->status ?? 'graded') : 'missing');
                                                $is_not_applicable = $grade_status === 'not_applicable';
                                                $is_cheating = $grade && !$is_not_applicable && (int) ($grade->is_cheating ?? 0) === 1;
                                                $points_value = $grade && !$is_not_applicable && $grade->points_obtained !== null ? $this->number_input_value($grade->points_obtained) : '';
                                                $adjustment_value = $grade && !$is_not_applicable ? $this->number_input_value($grade->points_adjustment ?? 0) : '0';
                                                $note_value = $grade && !$is_not_applicable && $grade->note !== null ? $this->format_note($grade->note) : '';
                                                $base_note_value = $is_cheating
                                                    ? '1'
                                                    : ($grade && !$is_not_applicable && $grade->points_obtained !== null && $evaluation
                                                    ? $this->format_note($this->calculate_note((float) $grade->points_obtained, (float) $evaluation->total_points, 0.0))
                                                    : '');
                                                $is_student_makeup = $grade && !$is_not_applicable && (int) ($grade->is_makeup ?? 0) === 1;
                                                $makeup_date_value = $is_student_makeup ? $this->date_input_value((string) ($grade->makeup_date ?? '')) : '';
                                                if (in_array((int) $student->id, $locked_ids, true)) :
                                                    ?>
                                                    <tr class="gne-grade-locked-row">
                                                        <td><strong><?php echo esc_html($this->student_name($student)); ?></strong><br><span class="gne-grade-locked">Publié depuis LogiExam : lecture seule</span></td>
                                                        <td><?php echo esc_html($grade && $grade->points_obtained !== null ? $this->format_decimal($grade->points_obtained) : '—'); ?></td>
                                                        <td><?php echo esc_html($grade ? $this->format_decimal($grade->points_adjustment) : '0'); ?></td>
                                                        <td colspan="2"><?php echo esc_html($is_cheating ? 'Triche' : ''); ?></td>
                                                        <td>-</td>
                                                        <td><span class="gne-note-output"><?php echo esc_html($note_value !== '' ? $note_value : '-'); ?></span></td>
                                                    </tr>
                                                    <?php
                                                    continue;
                                                endif;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo esc_html($this->student_name($student)); ?></strong>
                                                    </td>
                                                    <td>
                                                        <input type="number" name="points[<?php echo esc_attr((string) $student->id); ?>]" value="<?php echo esc_attr($points_value); ?>" min="0" step="0.01" <?php disabled($is_not_applicable || $is_cheating); ?> data-gne-points-input>
                                                    </td>
                                                    <td>
                                                        <select name="adjustments[<?php echo esc_attr((string) $student->id); ?>]" <?php disabled($is_not_applicable || $is_cheating); ?> data-gne-adjustment-input>
                                                            <option value="0" <?php selected($adjustment_value, '0'); ?>>Aucun</option>
                                                            <option value="0.5" <?php selected($adjustment_value, '0.5'); ?>>+0,5 bonus</option>
                                                            <option value="1" <?php selected($adjustment_value, '1'); ?>>+1 bonus</option>
                                                            <option value="1.5" <?php selected($adjustment_value, '1.5'); ?>>+1,5 bonus</option>
                                                            <option value="-0.5" <?php selected($adjustment_value, '-0.5'); ?>>-0,5 malus</option>
                                                            <option value="-1" <?php selected($adjustment_value, '-1'); ?>>-1 malus</option>
                                                            <option value="-1.5" <?php selected($adjustment_value, '-1.5'); ?>>-1,5 malus</option>
                                                            <option value="-2" <?php selected($adjustment_value, '-2'); ?>>-2 malus</option>
                                                            <option value="-2.5" <?php selected($adjustment_value, '-2.5'); ?>>-2,5 malus</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <label class="gne-inline-check">
                                                            <input type="checkbox" name="student_makeup[<?php echo esc_attr((string) $student->id); ?>]" value="1" <?php checked($is_student_makeup); ?> <?php disabled($is_not_applicable || $is_cheating); ?> data-gne-makeup-toggle>
                                                            Rattrapage
                                                        </label>
                                                        <div class="gne-makeup-date-field" data-gne-makeup-date-wrap <?php echo $is_student_makeup ? '' : 'hidden'; ?>>
                                                            <input type="date" name="makeup_date[<?php echo esc_attr((string) $student->id); ?>]" value="<?php echo esc_attr($makeup_date_value); ?>" <?php disabled(!$is_student_makeup || $is_not_applicable); ?> <?php echo $is_student_makeup ? 'required' : ''; ?> data-gne-makeup-date>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="gne-inline-check">
                                                            <input type="checkbox" name="student_cheating[<?php echo esc_attr((string) $student->id); ?>]" value="1" <?php checked($is_cheating); ?> <?php disabled($is_not_applicable); ?> data-gne-cheating-toggle>
                                                            Triche
                                                        </label>
                                                        <label class="gne-inline-check">
                                                            <input type="checkbox" name="not_applicable[<?php echo esc_attr((string) $student->id); ?>]" value="1" <?php checked($is_not_applicable); ?> data-gne-not-applicable>
                                                            Ne sera pas faite
                                                        </label>
                                                    </td>
                                                    <td>
                                                        <span class="gne-note-output gne-note-output-secondary" data-gne-base-note-output><?php echo esc_html($is_not_applicable ? 'Non faite' : ($base_note_value !== '' ? $base_note_value : '-')); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="gne-note-output" data-gne-note-output><?php echo esc_html($is_not_applicable ? 'Non faite' : ($note_value !== '' ? $note_value : '-')); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button class="gne-button gne-button-primary" type="submit"><?php echo $evaluation_id > 0 ? 'Mettre à jour l’évaluation' : 'Enregistrer l’évaluation'; ?></button>
        </form>
        <?php
    }

    private function render_classes(): void
    {
        global $wpdb;

        $classes_table = GNE_Database::table('classes');
        $students_table = GNE_Database::table('students');
        $classes = $wpdb->get_results(
            "SELECT c.*, COALESCE(stats.student_count, 0) AS student_count
             FROM {$classes_table} c
             LEFT JOIN (
                SELECT class_id, COUNT(*) AS student_count
                FROM {$students_table}
                WHERE status = 'active'
                GROUP BY class_id
             ) stats ON stats.class_id = c.id
             WHERE c.status = 'active'" . LogiPilot_Permissions::notes_class_sql('c.id') . "
             ORDER BY c.name ASC"
        );
        ?>
        <section class="gne-grid gne-grid-2">
            <div class="gne-panel">
                <div class="gne-panel-title"><h2>Groupes classes</h2></div>
                <?php if (!$classes) : ?>
                    <p class="gne-empty">Aucun groupe classe pour le moment.</p>
                <?php else : ?>
                    <div class="gne-compact-list">
                        <?php foreach ($classes as $class) : ?>
                            <a class="gne-compact-row" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'classes', 'class_id' => (int) $class->id))); ?>">
                                <span>
                                    <strong><?php echo esc_html($class->name); ?></strong>
                                </span>
                                <span class="gne-badge"><?php echo esc_html((string) (int) $class->student_count); ?> élève<?php echo (int) $class->student_count > 1 ? 's' : ''; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="gne-grid">
                <div class="gne-panel">
                    <div class="gne-panel-title"><h2>Ajouter un groupe classe</h2></div>
                    <?php $this->render_class_form(null); ?>
                </div>

                <div class="gne-panel">
                    <div class="gne-panel-title"><h2>Importer des élèves</h2></div>
                    <?php $this->render_student_import_form(); ?>
                </div>
            </div>
        </section>
        <?php
    }

    private function render_class_detail(int $class_id): void
    {
        global $wpdb;

        $classes = GNE_Database::table('classes');
        $students = GNE_Database::table('students');
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$classes} WHERE id = %d", $class_id));

        if (!$class || !LogiPilot_Permissions::notes_class_allowed((int) $class->id)) {
            echo '<p class="gne-empty">Groupe classe introuvable.</p>';
            return;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$students} WHERE class_id = %d AND status = 'active' ORDER BY last_name ASC, first_name ASC",
                $class_id
            )
        );
        ?>
        <section class="gne-grid gne-grid-detail">
            <div class="gne-panel">
                <div class="gne-panel-title">
                    <div>
                        <a class="gne-back" href="<?php echo esc_url($this->page_url(array('gne_tab' => 'classes'))); ?>">&larr; Groupes classes</a>
                        <h2><?php echo esc_html($class->name); ?></h2>
                    </div>
                </div>

                <?php $this->render_class_form($class); ?>

                <form class="gne-inline-form gne-danger-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gne-confirm="Supprimer ce groupe classe, ses élèves, ses évaluations et les notes associées ?">
                    <?php wp_nonce_field('gne_delete_class_' . (int) $class_id); ?>
                    <input type="hidden" name="action" value="gne_delete_class">
                    <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
                    <button class="gne-button gne-button-danger" type="submit">Supprimer le groupe</button>
                </form>
            </div>

            <div class="gne-grid">
                <div class="gne-panel">
                    <div class="gne-panel-title"><h2>Ajouter un élève</h2></div>
                    <?php $this->render_student_form($class_id, null); ?>
                </div>

                <div class="gne-panel">
                    <div class="gne-panel-title"><h2>Importer des élèves</h2></div>
                    <?php $this->render_student_import_form($class_id); ?>
                </div>
            </div>
        </section>

        <section class="gne-panel gne-panel-spaced">
            <div class="gne-panel-title"><h2>Élèves du groupe</h2></div>
            <?php if (!$rows) : ?>
                <p class="gne-empty">Aucun élève dans ce groupe classe.</p>
            <?php else : ?>
                <div class="gne-student-cards">
                    <?php foreach ($rows as $student) : ?>
                        <div class="gne-student-card">
                            <?php $this->render_student_form($class_id, $student); ?>
                            <form class="gne-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gne-confirm="Supprimer cet élève et ses notes ?">
                                <?php wp_nonce_field('gne_delete_student_' . (int) $student->id); ?>
                                <input type="hidden" name="action" value="gne_delete_student">
                                <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $student->id); ?>">
                                <button class="gne-button gne-button-danger" type="submit">Supprimer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_class_form(?object $class): void
    {
        $class_id = $class ? (int) $class->id : 0;
        ?>
        <form class="gne-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('gne_save_class'); ?>
            <input type="hidden" name="action" value="gne_save_class">
            <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
            <label>Numéro de classe
                <input type="text" name="name" value="<?php echo esc_attr($class ? (string) $class->name : ''); ?>" placeholder="CLLOG-C2b" required>
            </label>
            <button class="gne-button gne-button-primary" type="submit"><?php echo $class_id > 0 ? 'Mettre à jour' : 'Enregistrer'; ?></button>
        </form>
        <?php
    }

    private function render_student_import_form(int $class_id = 0): void
    {
        $has_target_class = $class_id > 0;
        ?>
        <form class="gne-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('gne_import_students'); ?>
            <input type="hidden" name="action" value="gne_import_students">
            <?php if ($has_target_class) : ?>
                <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
            <?php endif; ?>
            <label>Fichier CSV, TXT ou Excel
                <input type="file" name="student_file" accept=".csv,.txt,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
            </label>
            <label>Coller des élèves
                <textarea name="student_paste" rows="6" placeholder="<?php echo esc_attr($has_target_class ? "Nom;Prénom\nDupont;Marie\nMartin;Lucas" : "Classe;Nom;Prénom\nCLLOG-C2b;Dupont;Marie\nCLLOG-C2b;Martin;Lucas"); ?>"></textarea>
            </label>
            <p class="gne-muted"><?php echo esc_html($has_target_class ? 'Colonnes attendues dans ce groupe : nom ; prénom. Les colonnes classe ; nom ; prénom sont aussi acceptées.' : 'Colonnes attendues : classe ; nom ; prénom. Une ligne d’en-tête est acceptée.'); ?></p>
            <button class="gne-button gne-button-primary" type="submit">Importer la liste</button>
        </form>
        <?php
    }

    private function render_student_form(int $class_id, ?object $student): void
    {
        $student_id = $student ? (int) $student->id : 0;
        ?>
        <form class="gne-form gne-student-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('gne_save_student'); ?>
            <input type="hidden" name="action" value="gne_save_student">
            <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $student_id); ?>">
            <div class="gne-form-grid">
                <label>Nom
                    <input type="text" name="last_name" value="<?php echo esc_attr($student ? (string) $student->last_name : ''); ?>" required>
                </label>
                <label>Prénom
                    <input type="text" name="first_name" value="<?php echo esc_attr($student ? (string) $student->first_name : ''); ?>" required>
                </label>
            </div>
            <button class="gne-button gne-button-primary" type="submit"><?php echo $student_id > 0 ? 'Mettre à jour' : 'Ajouter l’élève'; ?></button>
        </form>
        <?php
    }

    private function render_branches(): void
    {
        global $wpdb;

        $branches_table = GNE_Database::table('branches');
        $evaluations_table = GNE_Database::table('evaluations');
        $branches = $wpdb->get_results(
            "SELECT b.*, COALESCE(stats.evaluation_count, 0) AS evaluation_count
             FROM {$branches_table} b
             LEFT JOIN (
                SELECT branch_id, COUNT(*) AS evaluation_count
                FROM {$evaluations_table}
                WHERE branch_id > 0
                GROUP BY branch_id
             ) stats ON stats.branch_id = b.id
             ORDER BY b.name ASC"
        );
        ?>
        <section class="gne-grid gne-grid-2">
            <div class="gne-panel">
                <div class="gne-panel-title"><h2>Branches</h2></div>
                <?php if (!$branches) : ?>
                    <p class="gne-empty">Aucune branche pour le moment.</p>
                <?php else : ?>
                    <div class="gne-student-cards">
                        <?php foreach ($branches as $branch) : ?>
                            <div class="gne-student-card">
                                <?php $this->render_branch_form($branch); ?>
                                <div class="gne-row-footer">
                                    <span class="gne-badge"><?php echo esc_html((string) (int) $branch->evaluation_count); ?> évaluation<?php echo (int) $branch->evaluation_count > 1 ? 's' : ''; ?></span>
                                    <form class="gne-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gne-confirm="Supprimer cette branche ?">
                                        <?php wp_nonce_field('gne_delete_branch_' . (int) $branch->id); ?>
                                        <input type="hidden" name="action" value="gne_delete_branch">
                                        <input type="hidden" name="branch_id" value="<?php echo esc_attr((string) $branch->id); ?>">
                                        <button class="gne-button gne-button-danger" type="submit">Supprimer</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="gne-panel">
                <div class="gne-panel-title"><h2>Ajouter une branche</h2></div>
                <?php $this->render_branch_form(null); ?>
            </div>
        </section>
        <?php
    }

    private function render_branch_form(?object $branch): void
    {
        $branch_id = $branch ? (int) $branch->id : 0;
        ?>
        <form class="gne-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('gne_save_branch'); ?>
            <input type="hidden" name="action" value="gne_save_branch">
            <input type="hidden" name="branch_id" value="<?php echo esc_attr((string) $branch_id); ?>">
            <label>Nom de la branche
                <input type="text" name="name" value="<?php echo esc_attr($branch ? (string) $branch->name : ''); ?>" placeholder="Branche informatique" required>
            </label>
            <button class="gne-button gne-button-primary" type="submit"><?php echo $branch_id > 0 ? 'Mettre à jour la branche' : 'Enregistrer la branche'; ?></button>
        </form>
        <?php
    }

    private function render_semesters(): void
    {
        global $wpdb;

        $semesters_table = GNE_Database::table('semesters');
        $evaluations_table = GNE_Database::table('evaluations');
        $semesters = $wpdb->get_results(
            "SELECT sem.*, COALESCE(stats.evaluation_count, 0) AS evaluation_count
             FROM {$semesters_table} sem
             LEFT JOIN (
                SELECT semester_id, COUNT(*) AS evaluation_count
                FROM {$evaluations_table}
                GROUP BY semester_id
             ) stats ON stats.semester_id = sem.id
             ORDER BY sem.school_year DESC, sem.sort_order ASC, sem.start_date ASC, sem.name ASC"
        );
        ?>
        <section class="gne-grid gne-grid-2">
            <div class="gne-panel">
                <div class="gne-panel-title"><h2>Semestres</h2></div>
                <?php if (!$semesters) : ?>
                    <p class="gne-empty">Aucun semestre pour le moment.</p>
                <?php else : ?>
                    <div class="gne-student-cards">
                        <?php foreach ($semesters as $semester) : ?>
                            <div class="gne-student-card">
                                <?php $this->render_semester_form($semester); ?>
                                <div class="gne-row-footer">
                                    <span class="gne-badge"><?php echo esc_html((string) (int) $semester->evaluation_count); ?> évaluation<?php echo (int) $semester->evaluation_count > 1 ? 's' : ''; ?></span>
                                    <form class="gne-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gne-confirm="Supprimer ce semestre ?">
                                        <?php wp_nonce_field('gne_delete_semester_' . (int) $semester->id); ?>
                                        <input type="hidden" name="action" value="gne_delete_semester">
                                        <input type="hidden" name="semester_id" value="<?php echo esc_attr((string) $semester->id); ?>">
                                        <button class="gne-button gne-button-danger" type="submit">Supprimer</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="gne-panel">
                <div class="gne-panel-title"><h2>Ajouter un semestre</h2></div>
                <?php $this->render_semester_form(null); ?>
            </div>
        </section>
        <?php
    }

    private function render_semester_form(?object $semester): void
    {
        $semester_id = $semester ? (int) $semester->id : 0;
        ?>
        <form class="gne-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('gne_save_semester'); ?>
            <input type="hidden" name="action" value="gne_save_semester">
            <input type="hidden" name="semester_id" value="<?php echo esc_attr((string) $semester_id); ?>">
            <div class="gne-form-grid">
                <label>Nom du semestre
                    <input type="text" name="name" value="<?php echo esc_attr($semester ? (string) $semester->name : 'Semestre 1'); ?>" required>
                </label>
                <label>Année scolaire
                    <input type="text" name="school_year" value="<?php echo esc_attr($semester ? (string) $semester->school_year : GNE_Database::default_school_year()); ?>" required>
                </label>
                <label>Date de début
                    <input type="date" name="start_date" value="<?php echo esc_attr($semester ? $this->date_input_value((string) $semester->start_date) : ''); ?>">
                </label>
                <label>Date de fin
                    <input type="date" name="end_date" value="<?php echo esc_attr($semester ? $this->date_input_value((string) $semester->end_date) : ''); ?>">
                </label>
                <label>Ordre
                    <input type="number" name="sort_order" value="<?php echo esc_attr($semester ? (string) (int) $semester->sort_order : '1'); ?>" min="0" step="1">
                </label>
                <label>Statut
                    <select name="status">
                        <option value="active" <?php selected($semester ? (string) $semester->status : 'active', 'active'); ?>>Actif</option>
                        <option value="archived" <?php selected($semester ? (string) $semester->status : 'active', 'archived'); ?>>Archivé</option>
                    </select>
                </label>
            </div>
            <button class="gne-button gne-button-primary" type="submit"><?php echo $semester_id > 0 ? 'Mettre à jour le semestre' : 'Enregistrer le semestre'; ?></button>
        </form>
        <?php
    }

    private function render_report(): void
    {
        $classes = $this->classes_for_form();
        $semesters = $this->semesters_for_form();
        $selected_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : ($classes ? (int) $classes[0]->id : 0);
        $selected_semester_id = isset($_GET['semester_id']) ? (int) $_GET['semester_id'] : ($semesters ? (int) $semesters[0]->id : 0);
        ?>
        <section class="gne-panel">
            <div class="gne-panel-title">
                <div>
                    <h2>Relevé de notes</h2>
                    <p class="gne-muted">Vue synthétique par groupe classe et semestre.</p>
                </div>
            </div>

            <form class="gne-filter" method="get">
                <?php $this->hidden_page_fields(array('gne_tab' => 'report')); ?>
                <select name="class_id">
                    <?php foreach ($classes as $class) : ?>
                        <option value="<?php echo esc_attr((string) $class->id); ?>" <?php selected($selected_class_id, (int) $class->id); ?>><?php echo esc_html($class->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="semester_id">
                    <?php foreach ($semesters as $semester) : ?>
                        <option value="<?php echo esc_attr((string) $semester->id); ?>" <?php selected($selected_semester_id, (int) $semester->id); ?>><?php echo esc_html($this->semester_name($semester)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="gne-button" type="submit">Afficher</button>
                <?php if ($selected_class_id > 0 && $selected_semester_id > 0) : ?>
                    <a class="gne-button gne-button-primary" href="<?php echo esc_url($this->export_semester_pdf_url($selected_class_id, $selected_semester_id)); ?>">Exporter le semestre PDF</a>
                <?php endif; ?>
            </form>
        </section>

        <?php
        if ($selected_class_id <= 0 || $selected_semester_id <= 0) {
            echo '<p class="gne-empty gne-panel-spaced">Créez au moins un groupe classe et un semestre pour afficher le relevé.</p>';
            return;
        }

        $this->render_report_table($selected_class_id, $selected_semester_id);
    }

    private function render_agapepe(): void
    {
        $evaluations = $this->agapepe_evaluations();
        $pending = array();
        $reported = array();

        foreach ($evaluations as $evaluation) {
            if ((int) ($evaluation->agapepe_reported ?? 0) === 1) {
                $reported[] = $evaluation;
            } else {
                $pending[] = $evaluation;
            }
        }
        ?>
        <section class="gne-panel">
            <div class="gne-panel-title">
                <div>
                    <h2>AGAPEPE</h2>
                    <p class="gne-muted">Suivi des évaluations à reporter dans le système de gestion des notes.</p>
                </div>
            </div>

            <?php $this->render_agapepe_table($pending, 'Notes à saisir dans AGAPEPE', 'Aucune évaluation en attente de report AGAPEPE.'); ?>
            <?php $this->render_agapepe_table($reported, 'Déjà saisies dans AGAPEPE', 'Aucune évaluation marquée comme saisie dans AGAPEPE.'); ?>
        </section>
        <?php
    }

    private function render_agapepe_table(array $evaluations, string $title, string $empty_message): void
    {
        ?>
        <div class="gne-agapepe-section">
            <h3><?php echo esc_html($title); ?></h3>
            <?php if (!$evaluations) : ?>
                <p class="gne-empty"><?php echo esc_html($empty_message); ?></p>
            <?php else : ?>
                <div class="gne-table-wrap">
                    <table class="gne-table gne-agapepe-table">
                        <thead>
                            <tr>
                                <th>Évaluation</th>
                                <th>Groupe classe</th>
                                <th>Date</th>
                                <th>Notes</th>
                                <th>En attente</th>
                                <th>Moyenne</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evaluations as $evaluation) : ?>
                                <?php $progress_status = $this->evaluation_progress_status($evaluation); ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => (int) $evaluation->id))); ?>">
                                            <strong><?php echo esc_html((string) $evaluation->module_name); ?></strong>
                                        </a>
                                        <?php if (trim((string) ($evaluation->branch_name ?? '')) !== '') : ?>
                                            <small><?php echo esc_html((string) $evaluation->branch_name); ?></small>
                                        <?php endif; ?>
                                        <small><?php echo esc_html(trim((string) ($evaluation->semester_name ?? '') . ' ' . (string) ($evaluation->semester_school_year ?? ''))); ?></small>
                                    </td>
                                    <td><?php echo esc_html((string) $evaluation->class_name); ?></td>
                                    <td><?php echo esc_html($this->display_date((string) $evaluation->evaluation_date)); ?></td>
                                    <td><?php echo esc_html((string) (int) $evaluation->grade_count); ?></td>
                                    <td><?php echo esc_html((string) (int) $evaluation->missing_count); ?></td>
                                    <td><?php echo esc_html($this->format_average($evaluation->avg_note)); ?></td>
                                    <td>
                                        <span class="<?php echo esc_attr('gne-progress-pill ' . $progress_status['class']); ?>"><?php echo esc_html($progress_status['label']); ?></span>
                                        <?php if ((int) ($evaluation->agapepe_reported ?? 0) === 1 && (string) ($evaluation->agapepe_reported_at ?? '') !== '') : ?>
                                            <small><?php echo esc_html($this->display_datetime((string) $evaluation->agapepe_reported_at)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php $this->render_agapepe_status_form($evaluation, 'table'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_agapepe_status_form(object $evaluation, string $context): void
    {
        $evaluation_id = (int) ($evaluation->id ?? 0);
        if ($evaluation_id <= 0) {
            return;
        }

        $reported = (int) ($evaluation->agapepe_reported ?? 0) === 1;
        $next_value = $reported ? 0 : 1;
        $button_label = $reported ? 'À reporter' : 'Marquer saisi';
        $redirect_to = $context === 'evaluation'
            ? $this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => $evaluation_id))
            : $this->page_url(array('gne_tab' => 'agapepe'));

        if ($context === 'evaluation') :
            ?>
            <div class="gne-agapepe-control">
                <div>
                    <strong>Suivi AGAPEPE</strong>
                    <p class="gne-muted">
                        <?php echo esc_html($reported ? 'Cette évaluation est marquée comme saisie dans AGAPEPE.' : 'Cette évaluation reste à reporter dans AGAPEPE.'); ?>
                        <?php if ($reported && (string) ($evaluation->agapepe_reported_at ?? '') !== '') : ?>
                            <?php echo esc_html('Dernière mise à jour : ' . $this->display_datetime((string) $evaluation->agapepe_reported_at) . '.'); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <form class="gne-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gne_save_agapepe_status_' . $evaluation_id); ?>
                    <input type="hidden" name="action" value="gne_save_agapepe_status">
                    <input type="hidden" name="evaluation_id" value="<?php echo esc_attr((string) $evaluation_id); ?>">
                    <input type="hidden" name="agapepe_reported" value="<?php echo esc_attr((string) $next_value); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
                    <button class="gne-button <?php echo $reported ? '' : 'gne-button-primary'; ?>" type="submit"><?php echo esc_html($reported ? 'Marquer comme à reporter' : 'Marquer comme saisi dans AGAPEPE'); ?></button>
                </form>
            </div>
            <?php
            return;
        endif;
        ?>
        <form class="gne-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('gne_save_agapepe_status_' . $evaluation_id); ?>
            <input type="hidden" name="action" value="gne_save_agapepe_status">
            <input type="hidden" name="evaluation_id" value="<?php echo esc_attr((string) $evaluation_id); ?>">
            <input type="hidden" name="agapepe_reported" value="<?php echo esc_attr((string) $next_value); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
            <button class="gne-button <?php echo $reported ? '' : 'gne-button-primary'; ?>" type="submit"><?php echo esc_html($button_label); ?></button>
        </form>
        <?php
    }

    private function render_settings(): void
    {
        $settings = GNE_Database::get_settings();
        $enabled = !empty($settings['missing_grade_reminder_enabled']);
        $email = (string) ($settings['missing_grade_reminder_email'] ?? '');
        $days = max(1, (int) ($settings['missing_grade_reminder_days'] ?? 15));
        ?>
        <section class="gne-panel">
            <div class="gne-panel-title">
                <div>
                    <h2>Paramètres des alertes</h2>
                    <p class="gne-muted">Les rappels concernent les élèves sans note alors qu'au moins un camarade a déjà une note.</p>
                </div>
            </div>

            <form class="gne-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gne_save_settings'); ?>
                <input type="hidden" name="action" value="gne_save_settings">
                <div class="gne-form-grid">
                    <label class="gne-check">
                        <input type="checkbox" name="missing_grade_reminder_enabled" value="1" <?php checked($enabled); ?>>
                        Activer les alertes de notes manquantes
                    </label>
                    <label>Délai avant alerte
                        <input type="number" name="missing_grade_reminder_days" value="<?php echo esc_attr((string) $days); ?>" min="1" max="90" step="1">
                    </label>
                    <label class="gne-wide-field">E-mail de rappel
                        <input type="text" name="missing_grade_reminder_email" value="<?php echo esc_attr($email); ?>" placeholder="prenom.nom@example.ch">
                    </label>
                </div>
                <button class="gne-button gne-button-primary" type="submit">Enregistrer les paramètres</button>
            </form>
        </section>
        <?php
    }

    private function render_report_table(int $class_id, int $semester_id): void
    {
        global $wpdb;

        if (!LogiPilot_Permissions::notes_class_allowed($class_id)) {
            echo '<p class="gne-empty gne-panel-spaced">Groupe classe hors de votre périmètre.</p>';
            return;
        }

        $evaluations_table = GNE_Database::table('evaluations');
        $branches_table = GNE_Database::table('branches');
        $evaluations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, b.name AS branch_name
                 FROM {$evaluations_table} e
                 LEFT JOIN {$branches_table} b ON b.id = e.branch_id
                 WHERE e.class_id = %d AND e.semester_id = %d
                 ORDER BY e.evaluation_date ASC, e.id ASC",
                $class_id,
                $semester_id
            )
        );
        $students = $this->participant_students($class_id, array_map(static fn($evaluation): int => (int) $evaluation->id, $evaluations));

        if (!$students) {
            echo '<p class="gne-empty gne-panel-spaced">Aucun élève dans ce groupe classe.</p>';
            return;
        }

        if (!$evaluations) {
            echo '<p class="gne-empty gne-panel-spaced">Aucune évaluation saisie pour ce groupe classe et ce semestre.</p>';
            return;
        }

        $evaluation_ids = array_map(static fn($evaluation): int => (int) $evaluation->id, $evaluations);
        $grades_by_student = $this->grades_by_student_for_evaluations($evaluation_ids);
        ?>
        <section class="gne-panel gne-panel-spaced">
            <div class="gne-panel-title"><h2>Notes</h2></div>
            <div class="gne-table-wrap">
                <table class="gne-table gne-report-table">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <?php foreach ($evaluations as $evaluation) : ?>
                                <th>
                                    <a href="<?php echo esc_url($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => (int) $evaluation->id))); ?>"><?php echo esc_html($evaluation->module_name); ?></a>
                                    <?php if (trim((string) ($evaluation->branch_name ?? '')) !== '') : ?>
                                        <small><?php echo esc_html((string) $evaluation->branch_name); ?></small>
                                    <?php endif; ?>
                                    <small><?php echo esc_html($this->display_date((string) $evaluation->evaluation_date)); ?></small>
                                </th>
                            <?php endforeach; ?>
                            <th>Moyenne</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student) : ?>
                            <?php
                            $notes = array();
                            foreach ($evaluations as $evaluation) {
                                $grade = $grades_by_student[(int) $student->id][(int) $evaluation->id] ?? null;
                                $grade_status = $this->grade_status_value($grade ? (string) ($grade->status ?? 'graded') : 'missing');
                                if ($grade_status === 'graded' && $grade && $grade->note !== null) {
                                    $notes[] = (float) $grade->note;
                                }
                            }
                            $average = $notes ? array_sum($notes) / count($notes) : null;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($this->student_name($student)); ?></strong>
                                </td>
                                <?php foreach ($evaluations as $evaluation) : ?>
                                    <?php
                                    $grade = $grades_by_student[(int) $student->id][(int) $evaluation->id] ?? null;
                                    $grade_status = $this->grade_status_value($grade ? (string) ($grade->status ?? 'graded') : 'missing');
                                    ?>
                                    <td>
                                        <?php if ($grade_status === 'not_applicable') : ?>
                                            <span class="gne-badge gne-badge-warning">Non faite</span>
                                        <?php elseif ($grade && $grade->note !== null && (int) ($grade->is_cheating ?? 0) === 1) : ?>
                                            <strong><?php echo esc_html($this->format_note($grade->note)); ?></strong>
                                            <small>Triche : note fixée à 1</small>
                                        <?php elseif ($grade && $grade->note !== null) : ?>
                                            <strong><?php echo esc_html($this->format_note($grade->note)); ?></strong>
                                            <small><?php echo esc_html($this->format_decimal($grade->points_obtained)); ?> / <?php echo esc_html($this->format_decimal($evaluation->total_points)); ?> pts</small>
                                            <?php $grade_details = $this->grade_detail_summary($grade); ?>
                                            <?php if ($grade_details !== '') : ?>
                                                <small><?php echo esc_html($grade_details); ?></small>
                                            <?php endif; ?>
                                            <?php if (abs((float) ($grade->points_adjustment ?? 0)) > 0.00001 && $grade->points_obtained !== null) : ?>
                                                <small>Note sans +/- : <?php echo esc_html($this->format_note($this->calculate_note((float) $grade->points_obtained, (float) $evaluation->total_points, 0.0))); ?></small>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="gne-muted">En attente</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><strong><?php echo esc_html($this->format_average($average)); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
    }

    public function handle_save_class(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gne_save_class');

        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $school_year = $this->stored_class_school_year($class_id);
        $status = 'active';

        if ($name === '') {
            $this->redirect_notice('classes', 'missing_data');
        }

        $table = GNE_Database::table('classes');
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE name = %s AND id <> %d LIMIT 1",
                $name,
                $class_id
            )
        );

        if ($existing_id && (int) $existing_id !== $class_id) {
            $args = array('gne_tab' => 'classes', 'gne_notice' => 'class_exists');
            if ($class_id > 0) {
                $args['class_id'] = $class_id;
            }
            wp_safe_redirect($this->page_url($args));
            exit;
        }

        $now = GNE_Database::now();
        if ($class_id > 0) {
            $wpdb->update(
                $table,
                array('name' => $name, 'school_year' => $school_year, 'status' => $status, 'updated_at' => $now),
                array('id' => $class_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table,
                array('name' => $name, 'school_year' => $school_year, 'status' => $status, 'created_at' => $now, 'updated_at' => $now),
                array('%s', '%s', '%s', '%s', '%s')
            );
            $class_id = (int) $wpdb->insert_id;
        }

        wp_safe_redirect($this->page_url(array('gne_tab' => 'classes', 'class_id' => $class_id, 'gne_notice' => 'class_saved')));
        exit;
    }

    public function handle_import_students(): void
    {
        $this->require_manage();
        check_admin_referer('gne_import_students');

        $target_class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        if ($target_class_id > 0 && !$this->class_exists($target_class_id)) {
            $this->redirect_notice('classes', 'not_found');
        }

        $rows = array();
        $file_error = UPLOAD_ERR_NO_FILE;

        if (!empty($_FILES['student_file']) && is_array($_FILES['student_file'])) {
            $file = $_FILES['student_file'];
            if (is_array($file['error'] ?? null) || is_array($file['tmp_name'] ?? null) || is_array($file['name'] ?? null) || is_array($file['size'] ?? null)) {
                $this->redirect_import_notice($target_class_id, 'import_failed');
            }

            $file_error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
            if ($file_error !== UPLOAD_ERR_NO_FILE) {
                $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
                $file_name = isset($file['name']) ? (string) $file['name'] : '';
                $size = isset($file['size']) ? (int) $file['size'] : 0;
                $max_size = defined('MB_IN_BYTES') ? 4 * MB_IN_BYTES : 4 * 1024 * 1024;

                if ($file_error !== UPLOAD_ERR_OK || $tmp_name === '' || !is_uploaded_file($tmp_name) || $size <= 0 || $size > $max_size) {
                    $this->redirect_import_notice($target_class_id, 'import_failed');
                }

                $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($extension, array('csv', 'txt', 'xlsx', 'xls'), true)) {
                    $this->redirect_import_notice($target_class_id, 'import_failed');
                }

                $file_rows = $this->read_student_import_file($tmp_name, $file_name, $target_class_id);
                if (!$file_rows) {
                    $this->redirect_import_notice($target_class_id, 'import_empty');
                }

                $rows = array_merge($rows, $file_rows);
            }
        }

        $pasted_data = isset($_POST['student_paste']) ? (string) wp_unslash($_POST['student_paste']) : '';
        if (trim($pasted_data) !== '') {
            $rows = array_merge($rows, $this->read_student_import_text($pasted_data, $target_class_id));
        }

        if (!$rows) {
            $this->redirect_import_notice($target_class_id, $file_error === UPLOAD_ERR_NO_FILE ? 'import_empty' : 'import_failed');
        }

        $result = $this->save_student_import_rows($rows);
        if ($result['students'] <= 0) {
            $this->redirect_import_notice($target_class_id, 'import_empty');
        }

        $args = array('gne_tab' => 'classes', 'gne_notice' => 'students_imported');
        if ($target_class_id > 0) {
            $args['class_id'] = $target_class_id;
        }

        wp_safe_redirect($this->page_url($args));
        exit;
    }

    public function handle_delete_class(): void
    {
        global $wpdb;

        $this->require_manage();
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        check_admin_referer('gne_delete_class_' . $class_id);

        if ($class_id <= 0) {
            $this->redirect_notice('classes', 'not_found');
        }

        $evaluations = GNE_Database::table('evaluations');
        $grades = GNE_Database::table('grades');
        $students = GNE_Database::table('students');
        $classes = GNE_Database::table('classes');
        $evaluation_ids = array_map('absint', $wpdb->get_col($wpdb->prepare("SELECT id FROM {$evaluations} WHERE class_id = %d", $class_id)));

        if ($evaluation_ids) {
            $wpdb->query("DELETE FROM {$grades} WHERE evaluation_id IN (" . implode(',', $evaluation_ids) . ")");
        }

        $wpdb->delete($evaluations, array('class_id' => $class_id), array('%d'));
        $wpdb->delete($students, array('class_id' => $class_id), array('%d'));
        $wpdb->delete($classes, array('id' => $class_id), array('%d'));

        wp_safe_redirect($this->page_url(array('gne_tab' => 'classes', 'gne_notice' => 'class_deleted')));
        exit;
    }

    public function handle_save_student(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gne_save_student');

        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $last_name = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $identifier = '';
        $email = '';
        $status = 'active';

        if ($class_id <= 0 || $last_name === '' || $first_name === '' || !$this->class_exists($class_id)) {
            $this->redirect_notice('classes', 'missing_data');
        }

        $table = GNE_Database::table('students');
        $now = GNE_Database::now();
        $data = array(
            'class_id' => $class_id,
            'last_name' => $last_name,
            'first_name' => $first_name,
            'identifier' => $identifier,
            'email' => $email,
            'status' => $status,
            'updated_at' => $now,
        );
        $formats = array('%d', '%s', '%s', '%s', '%s', '%s', '%s');

        if ($student_id > 0) {
            $wpdb->update($table, $data, array('id' => $student_id), $formats, array('%d'));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, array_merge($formats, array('%s')));
        }

        wp_safe_redirect($this->page_url(array('gne_tab' => 'classes', 'class_id' => $class_id, 'gne_notice' => 'student_saved')));
        exit;
    }

    public function handle_delete_student(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gne_delete_student_' . $student_id);

        $students = GNE_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$students} WHERE id = %d", $student_id));
        if (!$student) {
            $this->redirect_notice('classes', 'not_found');
        }

        $wpdb->delete(GNE_Database::table('grades'), array('student_id' => $student_id), array('%d'));
        $wpdb->delete($students, array('id' => $student_id), array('%d'));

        wp_safe_redirect($this->page_url(array('gne_tab' => 'classes', 'class_id' => (int) $student->class_id, 'gne_notice' => 'student_deleted')));
        exit;
    }

    public function handle_save_branch(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gne_save_branch');

        $branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));

        if ($name === '') {
            $this->redirect_notice('branches', 'missing_data');
        }

        $table = GNE_Database::table('branches');
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE name = %s AND id <> %d LIMIT 1",
                $name,
                $branch_id
            )
        );

        if ($existing_id && (int) $existing_id !== $branch_id) {
            $this->redirect_notice('branches', 'branch_exists');
        }

        $now = GNE_Database::now();
        $data = array(
            'name' => $name,
            'status' => 'active',
            'updated_at' => $now,
        );
        $formats = array('%s', '%s', '%s');

        if ($branch_id > 0) {
            $wpdb->update($table, $data, array('id' => $branch_id), $formats, array('%d'));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, array_merge($formats, array('%s')));
        }

        wp_safe_redirect($this->page_url(array('gne_tab' => 'branches', 'gne_notice' => 'branch_saved')));
        exit;
    }

    public function handle_delete_branch(): void
    {
        global $wpdb;

        $this->require_manage();
        $branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
        check_admin_referer('gne_delete_branch_' . $branch_id);

        if ($branch_id <= 0) {
            $this->redirect_notice('branches', 'not_found');
        }

        $evaluation_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . GNE_Database::table('evaluations') . " WHERE branch_id = %d",
                $branch_id
            )
        );

        if ($evaluation_count > 0) {
            $this->redirect_notice('branches', 'branch_in_use');
        }

        $wpdb->delete(GNE_Database::table('branches'), array('id' => $branch_id), array('%d'));
        wp_safe_redirect($this->page_url(array('gne_tab' => 'branches', 'gne_notice' => 'branch_deleted')));
        exit;
    }

    public function handle_save_semester(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gne_save_semester');

        $semester_id = isset($_POST['semester_id']) ? (int) $_POST['semester_id'] : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $school_year = sanitize_text_field(wp_unslash($_POST['school_year'] ?? ''));
        $start_date = $this->normalize_date_for_storage(wp_unslash($_POST['start_date'] ?? ''));
        $end_date = $this->normalize_date_for_storage(wp_unslash($_POST['end_date'] ?? ''));
        $sort_order = max(0, (int) ($_POST['sort_order'] ?? 0));
        $status = isset($_POST['status']) && $_POST['status'] === 'archived' ? 'archived' : 'active';

        if ($name === '' || $school_year === '') {
            $this->redirect_notice('semesters', 'missing_data');
        }

        $table = GNE_Database::table('semesters');
        $now = GNE_Database::now();
        $data = array(
            'name' => $name,
            'school_year' => $school_year,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => $status,
            'sort_order' => $sort_order,
            'updated_at' => $now,
        );
        $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%s');

        if ($semester_id > 0) {
            $wpdb->update($table, $data, array('id' => $semester_id), $formats, array('%d'));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, array_merge($formats, array('%s')));
            $semester_id = (int) $wpdb->insert_id;
        }

        wp_safe_redirect($this->page_url(array('gne_tab' => 'semesters', 'gne_notice' => 'semester_saved')));
        exit;
    }

    public function handle_delete_semester(): void
    {
        global $wpdb;

        $this->require_manage();
        $semester_id = isset($_POST['semester_id']) ? (int) $_POST['semester_id'] : 0;
        check_admin_referer('gne_delete_semester_' . $semester_id);

        if ($semester_id <= 0) {
            $this->redirect_notice('semesters', 'not_found');
        }

        $evaluation_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . GNE_Database::table('evaluations') . " WHERE semester_id = %d",
                $semester_id
            )
        );

        if ($evaluation_count > 0) {
            $this->redirect_notice('semesters', 'semester_in_use');
        }

        $wpdb->delete(GNE_Database::table('semesters'), array('id' => $semester_id), array('%d'));
        wp_safe_redirect($this->page_url(array('gne_tab' => 'semesters', 'gne_notice' => 'semester_deleted')));
        exit;
    }

    public function handle_save_settings(): void
    {
        $this->require_manage();
        check_admin_referer('gne_save_settings');

        $enabled = isset($_POST['missing_grade_reminder_enabled']) ? '1' : '0';
        $raw_email = sanitize_text_field(wp_unslash($_POST['missing_grade_reminder_email'] ?? ''));
        $recipients = $this->parse_email_list($raw_email);
        $days = min(90, max(1, (int) ($_POST['missing_grade_reminder_days'] ?? 15)));

        if ($enabled === '1' && !$recipients) {
            $this->redirect_notice('settings', 'missing_email');
        }

        GNE_Database::update_settings(array(
            'missing_grade_reminder_enabled' => $enabled,
            'missing_grade_reminder_email' => implode(', ', $recipients),
            'missing_grade_reminder_days' => (string) $days,
        ));

        $this->reschedule_missing_grade_reminder();
        wp_safe_redirect($this->page_url(array('gne_tab' => 'settings', 'gne_notice' => 'settings_saved')));
        exit;
    }

    public function handle_save_evaluation(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gne_save_evaluation');

        $evaluation_id = isset($_POST['evaluation_id']) ? (int) $_POST['evaluation_id'] : 0;
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $semester_id = isset($_POST['semester_id']) ? (int) $_POST['semester_id'] : 0;
        $branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
        $module_name = sanitize_text_field(wp_unslash($_POST['module_name'] ?? ''));
        $evaluation_date = $this->normalize_date_for_storage(wp_unslash($_POST['evaluation_date'] ?? ''));
        $total_points = $this->sanitize_decimal(wp_unslash($_POST['total_points'] ?? ''));

        if ($class_id <= 0 || $semester_id <= 0 || $module_name === '' || !$evaluation_date || $total_points === null || $total_points <= 0 || !$this->class_exists($class_id) || !$this->semester_exists($semester_id) || ($branch_id > 0 && !$this->branch_exists($branch_id))) {
            $this->redirect_notice('evaluations', 'missing_data');
        }

        $now = GNE_Database::now();
        $evaluations = GNE_Database::table('evaluations');
        $data = array(
            'class_id' => $class_id,
            'semester_id' => $semester_id,
            'branch_id' => $branch_id,
            'module_name' => $module_name,
            'evaluation_date' => $evaluation_date,
            'is_makeup' => 0,
            'total_points' => $total_points,
            'updated_at' => $now,
        );
        $formats = array('%d', '%d', '%d', '%s', '%s', '%d', '%f', '%s');

        if ($evaluation_id > 0) {
            $wpdb->update($evaluations, $data, array('id' => $evaluation_id), $formats, array('%d'));
        } else {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = $now;
            $wpdb->insert($evaluations, $data, array_merge($formats, array('%d', '%s')));
            $evaluation_id = (int) $wpdb->insert_id;
        }

        if ($evaluation_id <= 0) {
            $this->redirect_notice('evaluations', 'missing_data');
        }

        if (!$this->save_progress_pdf_upload($evaluation_id)) {
            wp_safe_redirect($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => $evaluation_id, 'gne_notice' => 'pdf_failed')));
            exit;
        }

        $this->replace_evaluation_grades($evaluation_id, $class_id, (float) $total_points);
        do_action('logipilot_notes_evaluation_saved', $evaluation_id);
        do_action('logipilot_notes_grades_saved', $evaluation_id);

        wp_safe_redirect($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => $evaluation_id, 'gne_notice' => 'evaluation_saved')));
        exit;
    }

    public function handle_save_agapepe_status(): void
    {
        global $wpdb;

        $this->require_manage();

        $evaluation_id = isset($_POST['evaluation_id']) ? (int) $_POST['evaluation_id'] : 0;
        check_admin_referer('gne_save_agapepe_status_' . $evaluation_id);

        if ($evaluation_id <= 0 || !$this->evaluation($evaluation_id)) {
            $this->redirect_notice('agapepe', 'not_found');
        }

        $reported = isset($_POST['agapepe_reported']) && (int) $_POST['agapepe_reported'] === 1;
        $now = GNE_Database::now();
        $wpdb->update(
            GNE_Database::table('evaluations'),
            array(
                'agapepe_reported' => $reported ? 1 : 0,
                'agapepe_reported_at' => $reported ? $now : null,
                'agapepe_reported_by' => $reported ? get_current_user_id() : 0,
                'updated_at' => $now,
            ),
            array('id' => $evaluation_id),
            array('%d', '%s', '%d', '%s'),
            array('%d')
        );

        $fallback = $this->page_url(array('gne_tab' => 'agapepe'));
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        $redirect_to = $redirect_to !== '' ? wp_validate_redirect($redirect_to, $fallback) : $fallback;

        wp_safe_redirect(add_query_arg('gne_notice', 'agapepe_saved', remove_query_arg('gne_notice', $redirect_to)));
        exit;
    }

    public function handle_delete_evaluation(): void
    {
        global $wpdb;

        $this->require_manage();
        $evaluation_id = isset($_POST['evaluation_id']) ? (int) $_POST['evaluation_id'] : 0;
        check_admin_referer('gne_delete_evaluation_' . $evaluation_id);

        if ($evaluation_id <= 0) {
            $this->redirect_notice('evaluations', 'not_found');
        }

        $evaluation = $this->evaluation($evaluation_id);
        if (!$evaluation) {
            $this->redirect_notice('evaluations', 'not_found');
        }

        $progress_pdf_path = $this->progress_pdf_absolute_path((string) ($evaluation->progress_pdf_path ?? ''));
        if ($progress_pdf_path !== '' && is_readable($progress_pdf_path)) {
            @unlink($progress_pdf_path);
        }

        $wpdb->delete(GNE_Database::table('grades'), array('evaluation_id' => $evaluation_id), array('%d'));
        $wpdb->delete(GNE_Database::table('evaluations'), array('id' => $evaluation_id), array('%d'));

        wp_safe_redirect($this->page_url(array('gne_tab' => 'evaluations', 'gne_notice' => 'evaluation_deleted')));
        exit;
    }

    public function send_missing_grade_reminders(): void
    {
        $settings = GNE_Database::get_settings();
        if (empty($settings['missing_grade_reminder_enabled'])) {
            return;
        }

        $recipients = $this->parse_email_list((string) ($settings['missing_grade_reminder_email'] ?? ''));
        if (!$recipients) {
            return;
        }

        $days = min(90, max(1, (int) ($settings['missing_grade_reminder_days'] ?? 15)));
        $alerts = $this->missing_grade_alert_rows($days);
        if (!$alerts) {
            return;
        }

        $subject = sprintf('Notes manquantes depuis plus de %d jours', $days);
        $body = $this->missing_grade_email_body($alerts, $days);

        if (wp_mail($recipients, $subject, $body)) {
            $this->mark_missing_grade_reminders_sent($alerts);
        }
    }

    public function handle_export_evaluation_pdf(): void
    {
        $this->require_manage();

        $evaluation_id = isset($_GET['evaluation_id']) ? (int) $_GET['evaluation_id'] : 0;
        check_admin_referer('gne_export_evaluation_pdf_' . $evaluation_id);

        if ($evaluation_id <= 0) {
            wp_die('Évaluation introuvable.', 'Évaluation introuvable', array('response' => 404));
        }

        $payload = $this->evaluation_export_payload($evaluation_id);
        if (!$payload) {
            wp_die('Évaluation introuvable.', 'Évaluation introuvable', array('response' => 404));
        }

        $progress_pdf_url = (string) ($payload['evaluation']->progress_pdf_path ?? '') !== ''
            ? $this->progress_pdf_protected_download_url((int) $payload['evaluation']->id)
            : null;
        $pdf = GNE_PDF::evaluation_report($payload['evaluation'], $payload['rows'], $payload['stats'], $progress_pdf_url);
        $filename = sanitize_file_name(
            'evaluation-' .
            (string) $payload['evaluation']->class_name . '-' .
            (string) $payload['evaluation']->module_name . '-' .
            (string) $payload['evaluation']->evaluation_date .
            '.pdf'
        );

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public function handle_download_progress_pdf(): void
    {
        $this->require_manage();

        $evaluation_id = isset($_GET['evaluation_id']) ? (int) $_GET['evaluation_id'] : 0;
        if (isset($_GET['_wpnonce'])) {
            check_admin_referer('gne_download_progress_pdf_' . $evaluation_id);
        }

        if ($evaluation_id <= 0) {
            wp_die('Justificatif introuvable.', 'Justificatif introuvable', array('response' => 404));
        }

        $evaluation = $this->evaluation($evaluation_id);
        if (!$evaluation || (string) ($evaluation->progress_pdf_path ?? '') === '') {
            wp_die('Justificatif introuvable.', 'Justificatif introuvable', array('response' => 404));
        }

        $path = $this->progress_pdf_absolute_path((string) $evaluation->progress_pdf_path);
        if ($path === '' || !is_readable($path)) {
            wp_die('Justificatif introuvable.', 'Justificatif introuvable', array('response' => 404));
        }

        $filename = sanitize_file_name((string) ($evaluation->progress_pdf_name ?: 'justificatif-bonus-malus.pdf'));

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function handle_download_progress_pdf_login_redirect(): void
    {
        $evaluation_id = isset($_GET['evaluation_id']) ? absint($_GET['evaluation_id']) : 0;
        $target = admin_url('admin-post.php?action=gne_download_progress_pdf&evaluation_id=' . $evaluation_id);

        wp_safe_redirect(wp_login_url($target));
        exit;
    }

    public function handle_export_semester_pdf(): void
    {
        $this->require_manage();

        $class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
        $semester_id = isset($_GET['semester_id']) ? (int) $_GET['semester_id'] : 0;
        check_admin_referer('gne_export_semester_pdf_' . $class_id . '_' . $semester_id);

        if ($class_id <= 0 || $semester_id <= 0) {
            wp_die('Relevé introuvable.', 'Relevé introuvable', array('response' => 404));
        }

        $payload = $this->semester_export_payload($class_id, $semester_id);
        if (!$payload) {
            wp_die('Relevé introuvable.', 'Relevé introuvable', array('response' => 404));
        }

        $pdf = GNE_PDF::semester_report($payload['class'], $payload['semester'], $payload['evaluations'], $payload['rows'], $payload['stats']);
        $filename = sanitize_file_name(
            'semestre-' .
            (string) $payload['class']->name . '-' .
            (string) $payload['semester']->name .
            '.pdf'
        );

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private function missing_grade_alert_rows(int $days): array
    {
        global $wpdb;

        $classes = GNE_Database::table('classes');
        $students = GNE_Database::table('students');
        $semesters = GNE_Database::table('semesters');
        $branches = GNE_Database::table('branches');
        $evaluations = GNE_Database::table('evaluations');
        $grades = GNE_Database::table('grades');
        $cutoff = wp_date('Y-m-d', strtotime('-' . $days . ' days'));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    e.id AS evaluation_id,
                    e.module_name,
                    e.evaluation_date,
                    e.total_points,
                    c.name AS class_name,
                    sem.name AS semester_name,
                    sem.school_year AS semester_school_year,
                    b.name AS branch_name,
                    s.id AS student_id,
                    s.last_name,
                    s.first_name,
                    g.id AS grade_id,
                    g.status AS grade_status,
                    g.is_makeup,
                    g.makeup_date,
                    g.reminded_at
                 FROM {$evaluations} e
                 INNER JOIN {$classes} c ON c.id = e.class_id
                 INNER JOIN {$students} s ON s.status = 'active' AND (s.class_id = e.class_id OR EXISTS (SELECT 1 FROM {$grades} gx WHERE gx.evaluation_id = e.id AND gx.student_id = s.id))
                 LEFT JOIN {$semesters} sem ON sem.id = e.semester_id
                 LEFT JOIN {$branches} b ON b.id = e.branch_id
                 LEFT JOIN {$grades} g ON g.evaluation_id = e.id AND g.student_id = s.id
                 WHERE c.status = 'active'
                   AND e.evaluation_date IS NOT NULL
                   AND (
                        (
                            g.id IS NOT NULL
                            AND g.is_makeup = 1
                            AND g.makeup_date IS NOT NULL
                            AND g.makeup_date <> '0000-00-00'
                            AND g.makeup_date <= %s
                        )
                        OR (
                            (g.id IS NULL OR g.is_makeup = 0 OR g.makeup_date IS NULL OR g.makeup_date = '0000-00-00')
                            AND e.evaluation_date <= %s
                        )
                   )
                   AND EXISTS (
                        SELECT 1
                        FROM {$grades} gg
                        WHERE gg.evaluation_id = e.id
                          AND gg.status = 'graded'
                          AND gg.note IS NOT NULL
                   )
                   AND (
                        g.id IS NULL
                        OR (
                            g.status IN ('missing', 'graded')
                            AND g.note IS NULL
                            AND (g.reminded_at IS NULL OR g.reminded_at = '0000-00-00 00:00:00')
                        )
                 )
                 ORDER BY c.name ASC, e.evaluation_date ASC, e.module_name ASC, s.last_name ASC, s.first_name ASC",
                $cutoff,
                $cutoff
            )
        );
    }

    private function missing_grade_email_body(array $alerts, int $days): string
    {
        $groups = array();

        foreach ($alerts as $alert) {
            $evaluation_id = (int) $alert->evaluation_id;
            if (!isset($groups[$evaluation_id])) {
                $groups[$evaluation_id] = array(
                    'evaluation' => $alert,
                    'students' => array(),
                );
            }

            $name = trim((string) $alert->last_name . ' ' . (string) $alert->first_name);
            if ((int) ($alert->is_makeup ?? 0) === 1 && (string) ($alert->makeup_date ?? '') !== '') {
                $name .= ' (rattrapage le ' . $this->display_date((string) $alert->makeup_date) . ')';
            }
            $groups[$evaluation_id]['students'][] = $name;
        }

        $body = "Bonjour,\n\n";
        $body .= sprintf("Les évaluations suivantes ont des notes manquantes depuis plus de %d jours.\n\n", $days);

        foreach ($groups as $evaluation_id => $group) {
            $evaluation = $group['evaluation'];
            $body .= sprintf(
                "- %s · %s · %s · %s · %s\n",
                (string) $evaluation->class_name,
                (string) ($evaluation->branch_name ?: 'Sans branche'),
                (string) $evaluation->module_name,
                (string) $evaluation->semester_name,
                $this->display_date((string) $evaluation->evaluation_date)
            );
            $body .= '  Élèves concernés : ' . implode(', ', $group['students']) . "\n";
            $body .= '  Ouvrir l’évaluation : ' . admin_url('admin.php?page=gne_app&evaluation_id=' . (int) $evaluation_id) . "\n\n";
        }

        $body .= "Pour arrêter le rappel pour un élève, ouvrez l’évaluation et cochez « Ne sera pas faite » sur sa ligne.\n";

        return $body;
    }

    private function mark_missing_grade_reminders_sent(array $alerts): void
    {
        global $wpdb;

        $grades = GNE_Database::table('grades');
        $now = GNE_Database::now();

        foreach ($alerts as $alert) {
            $grade_id = (int) ($alert->grade_id ?? 0);

            if ($grade_id > 0) {
                $wpdb->update(
                    $grades,
                    array(
                        'status' => 'missing',
                        'reminded_at' => $now,
                        'updated_at' => $now,
                    ),
                    array('id' => $grade_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                continue;
            }

            $wpdb->insert(
                $grades,
                array(
                    'evaluation_id' => (int) $alert->evaluation_id,
                    'student_id' => (int) $alert->student_id,
                    'points_obtained' => null,
                    'points_adjustment' => 0,
                    'note' => null,
                    'is_makeup' => 0,
                    'makeup_date' => null,
                    'status' => 'missing',
                    'status_note' => 'Alerte de note manquante envoyée.',
                    'reminded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%d', '%d', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    private function replace_evaluation_grades(int $evaluation_id, int $class_id, float $total_points): void
    {
        global $wpdb;

        $grades = GNE_Database::table('grades');
        $student_ids = array_map(static fn($student): int => (int) $student->id, $this->participant_students($class_id, array($evaluation_id)));
        $locked_ids = array_map('intval', (array) apply_filters('logipilot_notes_locked_students', array(), $evaluation_id));
        $student_ids = array_values(array_diff($student_ids, $locked_ids));
        $submitted_points = isset($_POST['points']) && is_array($_POST['points']) ? wp_unslash($_POST['points']) : array();
        $submitted_adjustments = isset($_POST['adjustments']) && is_array($_POST['adjustments']) ? wp_unslash($_POST['adjustments']) : array();
        $submitted_cheatings = isset($_POST['student_cheating']) && is_array($_POST['student_cheating']) ? wp_unslash($_POST['student_cheating']) : array();
        $submitted_makeups = isset($_POST['student_makeup']) && is_array($_POST['student_makeup']) ? wp_unslash($_POST['student_makeup']) : array();
        $submitted_makeup_dates = isset($_POST['makeup_date']) && is_array($_POST['makeup_date']) ? wp_unslash($_POST['makeup_date']) : array();
        $not_applicable = isset($_POST['not_applicable']) && is_array($_POST['not_applicable']) ? wp_unslash($_POST['not_applicable']) : array();
        $now = GNE_Database::now();

        foreach ($student_ids as $student_id) {
            if (!isset($not_applicable[$student_id]) && !isset($submitted_cheatings[$student_id]) && isset($submitted_makeups[$student_id]) && !$this->normalize_date_for_storage($submitted_makeup_dates[$student_id] ?? '')) {
                wp_safe_redirect($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => $evaluation_id, 'gne_notice' => 'missing_data')));
                exit;
            }
        }

        $existing_rows = array();
        foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM {$grades} WHERE evaluation_id = %d", $evaluation_id)) as $existing_row) {
            $existing_rows[(int) $existing_row->student_id] = $existing_row;
        }

        foreach ($student_ids as $student_id) {
            $raw_points = $submitted_points[$student_id] ?? '';
            $is_not_applicable = isset($not_applicable[$student_id]);
            $is_cheating = !$is_not_applicable && isset($submitted_cheatings[$student_id]);
            $is_student_makeup = isset($submitted_makeups[$student_id]);
            $makeup_date = $is_student_makeup ? $this->normalize_date_for_storage($submitted_makeup_dates[$student_id] ?? '') : null;

            if (!$is_not_applicable && !$is_cheating && $is_student_makeup && !$makeup_date) {
                wp_safe_redirect($this->page_url(array('gne_tab' => 'evaluations', 'evaluation_id' => $evaluation_id, 'gne_notice' => 'missing_data')));
                exit;
            }

            if ($is_not_applicable) {
                $this->upsert_grade_row(
                    $existing_rows[$student_id] ?? null,
                    array(
                        'evaluation_id' => $evaluation_id,
                        'student_id' => $student_id,
                        'points_obtained' => null,
                        'points_adjustment' => 0,
                        'note' => null,
                        'is_cheating' => 0,
                        'is_makeup' => 0,
                        'makeup_date' => null,
                        'status' => 'not_applicable',
                        'status_note' => 'Cette évaluation ne sera pas faite.',
                        'reminded_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ),
                    array('%d', '%d', '%f', '%f', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                continue;
            }

            if ($is_cheating) {
                $this->upsert_grade_row(
                    $existing_rows[$student_id] ?? null,
                    array(
                        'evaluation_id' => $evaluation_id,
                        'student_id' => $student_id,
                        'points_obtained' => null,
                        'points_adjustment' => 0,
                        'note' => 1,
                        'is_cheating' => 1,
                        'is_makeup' => 0,
                        'makeup_date' => null,
                        'status' => 'graded',
                        'status_note' => 'Triche : note fixée à 1.',
                        'reminded_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ),
                    array('%d', '%d', '%f', '%f', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                continue;
            }

            $points = $this->sanitize_decimal($raw_points);
            $adjustment = $this->sanitize_points_adjustment($submitted_adjustments[$student_id] ?? '0');
            $status = 'missing';
            $note = null;
            $status_note = '';

            if ($points !== null) {
                $points = min(max(0.0, (float) $points), $total_points);
                $note = $this->calculate_note($points, $total_points, $adjustment);
                $status = 'graded';
            } else {
                // NOT-02 : un ajustement validé reste enregistré tant que les points ne sont pas saisis (aucune note fictive).
                $points = null;
            }

            if ($is_student_makeup) {
                $status_note = 'Rattrapage le ' . $this->display_date((string) $makeup_date) . '.';
            } else {
                $makeup_date = null;
            }

            $this->upsert_grade_row(
                $existing_rows[$student_id] ?? null,
                array(
                    'evaluation_id' => $evaluation_id,
                    'student_id' => $student_id,
                    'points_obtained' => $points,
                    'points_adjustment' => $adjustment,
                    'note' => $note,
                    'is_cheating' => 0,
                    'is_makeup' => $is_student_makeup ? 1 : 0,
                    'makeup_date' => $makeup_date,
                    'status' => $status,
                    'status_note' => $status_note,
                    'reminded_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%d', '%d', '%f', '%f', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Écrit une ligne de note sans la supprimer : mise à jour de la ligne existante (identifiant,
     * date de création et suivi des rappels conservés) ou insertion si l'élève n'a pas encore de ligne.
     * Une ligne dont les valeurs pédagogiques sont identiques n'est pas réécrite.
     */
    private function upsert_grade_row(?object $existing, array $data, array $formats): void
    {
        global $wpdb;

        $grades = GNE_Database::table('grades');
        if (!$existing) {
            $wpdb->insert($grades, $data, $formats);
            return;
        }

        $compare = array('points_obtained', 'points_adjustment', 'note', 'is_cheating', 'is_makeup', 'makeup_date', 'status');
        $same = true;
        foreach ($compare as $field) {
            $old = $existing->$field ?? null;
            $new = $data[$field] ?? null;
            if ($old === null || $new === null || $old === '' || $new === '') {
                $same = $same && (($old === null || $old === '') === ($new === null || $new === ''));
            } elseif (is_numeric($old) && is_numeric($new)) {
                $same = $same && abs((float) $old - (float) $new) < 0.00001;
            } else {
                $same = $same && (string) $old === (string) $new;
            }
        }
        if ($same) {
            return;
        }

        $update = $data;
        $update_formats = array();
        foreach (array_keys($data) as $index => $field) {
            if (in_array($field, array('evaluation_id', 'student_id', 'created_at', 'reminded_at'), true)) {
                unset($update[$field]);
                continue;
            }
            $update_formats[] = $formats[$index];
        }
        $wpdb->update($grades, $update, array('id' => (int) $existing->id), $update_formats, array('%d'));
    }

    private function read_student_import_file(string $file_path, string $file_name, int $target_class_id = 0): array
    {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($extension, array('csv', 'txt'), true)) {
            return $this->read_delimited_student_import_file($file_path, $target_class_id);
        }

        if ($extension === 'xlsx') {
            return $this->read_xlsx_student_import_rows($file_path, $target_class_id);
        }

        if ($extension === 'xls') {
            return $this->read_delimited_student_import_file($file_path, $target_class_id);
        }

        return array();
    }

    private function read_delimited_student_import_file(string $file_path, int $target_class_id = 0): array
    {
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return array();
        }

        $first_line = '';
        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $first_line = $line;
                break;
            }
        }

        if ($first_line === '') {
            fclose($handle);
            return array();
        }

        $delimiter = $this->detect_import_delimiter($first_line);
        rewind($handle);

        $table_rows = array();
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $table_rows[] = $data;
        }

        fclose($handle);

        return $this->student_import_rows_from_table($table_rows, $target_class_id);
    }

    private function read_student_import_text(string $text, int $target_class_id = 0): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: array();
        $first_line = '';

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $first_line = $line;
                break;
            }
        }

        if ($first_line === '') {
            return array();
        }

        $delimiter = $this->detect_import_delimiter($first_line);
        $table_rows = array();

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $table_rows[] = str_getcsv($line, $delimiter);
        }

        return $this->student_import_rows_from_table($table_rows, $target_class_id);
    }

    private function read_xlsx_student_import_rows(string $file_path, int $target_class_id = 0): array
    {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
            return array();
        }

        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) {
            return array();
        }

        $worksheet_path = $this->first_xlsx_worksheet_path($zip);
        if ($worksheet_path === '') {
            $zip->close();
            return array();
        }

        $shared_strings = $this->xlsx_shared_strings($zip);
        $sheet_xml = $zip->getFromName($worksheet_path);
        $zip->close();

        if ($sheet_xml === false) {
            return array();
        }

        $sheet = simplexml_load_string($sheet_xml);
        if (!$sheet) {
            return array();
        }

        $sheet_body = $this->xlsx_default_children($sheet);
        if (!isset($sheet_body->sheetData->row)) {
            return array();
        }

        $table_rows = array();
        foreach ($sheet_body->sheetData->row as $row) {
            $row_body = $this->xlsx_default_children($row);
            $cells = array();
            $fallback_index = 0;

            foreach ($row_body->c as $cell) {
                $attributes = $cell->attributes();
                $reference = isset($attributes['r']) ? (string) $attributes['r'] : '';
                $index = $reference !== '' ? $this->xlsx_column_index($reference) : $fallback_index;
                $cells[$index] = $this->xlsx_cell_value($cell, $shared_strings);
                $fallback_index = $index + 1;
            }

            if (!$cells) {
                continue;
            }

            ksort($cells);
            $max_index = (int) max(array_keys($cells));
            $ordered = array();
            for ($i = 0; $i <= $max_index; $i++) {
                $ordered[] = $cells[$i] ?? '';
            }

            $table_rows[] = $ordered;
        }

        return $this->student_import_rows_from_table($table_rows, $target_class_id);
    }

    private function xlsx_default_children($xml)
    {
        $namespaces = $xml->getNamespaces(true);

        return isset($namespaces['']) ? $xml->children($namespaces['']) : $xml;
    }

    private function first_xlsx_worksheet_path($zip): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';

            if (preg_match('/^xl\/worksheets\/sheet\d+\.xml$/', $name)) {
                return $name;
            }
        }

        return '';
    }

    private function xlsx_shared_strings($zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || !function_exists('simplexml_load_string')) {
            return array();
        }

        $shared_xml = simplexml_load_string($xml);
        if (!$shared_xml) {
            return array();
        }

        $shared_body = $this->xlsx_default_children($shared_xml);
        if (!isset($shared_body->si)) {
            return array();
        }

        $strings = array();
        foreach ($shared_body->si as $item) {
            $item_body = $this->xlsx_default_children($item);
            if (isset($item_body->t)) {
                $strings[] = (string) $item_body->t;
                continue;
            }

            $text = '';
            foreach ($item_body->r as $run) {
                $run_body = $this->xlsx_default_children($run);
                $text .= isset($run_body->t) ? (string) $run_body->t : '';
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function xlsx_cell_value($cell, array $shared_strings): string
    {
        $attributes = $cell->attributes();
        $type = isset($attributes['t']) ? (string) $attributes['t'] : '';
        $cell_body = $this->xlsx_default_children($cell);

        if ($type === 's') {
            $index = isset($cell_body->v) ? (int) $cell_body->v : -1;
            return $shared_strings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            if (isset($cell_body->is->t)) {
                return (string) $cell_body->is->t;
            }

            $text = '';
            foreach ($cell_body->is->r as $run) {
                $run_body = $this->xlsx_default_children($run);
                $text .= isset($run_body->t) ? (string) $run_body->t : '';
            }
            return $text;
        }

        return isset($cell_body->v) ? (string) $cell_body->v : '';
    }

    private function xlsx_column_index(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private function student_import_rows_from_table(array $table_rows, int $target_class_id = 0): array
    {
        $rows = array();
        $indexes = null;

        foreach ($table_rows as $data) {
            if (!is_array($data)) {
                continue;
            }

            $data = array_map(array($this, 'normalize_import_cell'), $data);
            if (implode('', $data) === '') {
                continue;
            }

            if ($indexes === null) {
                $header_indexes = $this->import_header_indexes($data, $target_class_id);
                if ($header_indexes !== null) {
                    $indexes = $header_indexes;
                    continue;
                }

                $indexes = $this->default_import_indexes($data, $target_class_id);
                if ($indexes === null) {
                    continue;
                }
            }

            $class_number = isset($indexes['class_number']) ? ($data[$indexes['class_number']] ?? '') : '';
            $last_name = $data[$indexes['last_name']] ?? '';
            $first_name = $data[$indexes['first_name']] ?? '';

            if ($last_name === '' || $first_name === '') {
                continue;
            }

            if ($target_class_id <= 0 && $class_number === '') {
                continue;
            }

            $row = array(
                'class_number' => $class_number,
                'last_name' => $last_name,
                'first_name' => $first_name,
            );

            if ($target_class_id > 0) {
                $row['class_id'] = $target_class_id;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function detect_import_delimiter(string $line): string
    {
        $delimiters = array(';', "\t", ',');
        $best_delimiter = ';';
        $best_count = 0;

        foreach ($delimiters as $delimiter) {
            $count = count(str_getcsv($line, $delimiter));
            if ($count > $best_count) {
                $best_count = $count;
                $best_delimiter = $delimiter;
            }
        }

        return $best_delimiter;
    }

    private function import_header_indexes(array $cells, int $target_class_id = 0): ?array
    {
        $normalized = array();
        foreach ($cells as $index => $cell) {
            $key = $this->normalize_import_header((string) $cell);
            if ($key !== '') {
                $normalized[$key] = (int) $index;
            }
        }

        $aliases = array(
            'class_number' => array('classe', 'numero_classe', 'numero_de_classe', 'no_classe', 'n_classe', 'groupe', 'groupe_classe', 'class', 'class_number'),
            'last_name' => array('nom', 'nom_de_famille', 'last_name', 'lastname'),
            'first_name' => array('prenom', 'first_name', 'firstname'),
        );
        $indexes = array();

        foreach ($aliases as $field => $field_aliases) {
            foreach ($field_aliases as $alias) {
                if (isset($normalized[$alias])) {
                    $indexes[$field] = $normalized[$alias];
                    break;
                }
            }
        }

        if ($target_class_id > 0 && isset($indexes['last_name'], $indexes['first_name'])) {
            return $indexes;
        }

        return isset($indexes['class_number'], $indexes['last_name'], $indexes['first_name']) ? $indexes : null;
    }

    private function default_import_indexes(array $cells, int $target_class_id = 0): ?array
    {
        $count = count($cells);

        if ($target_class_id > 0) {
            $third_cell = $cells[2] ?? '';

            if ($count >= 3 && trim((string) $third_cell) !== '') {
                return array(
                    'class_number' => 0,
                    'last_name' => 1,
                    'first_name' => 2,
                );
            }

            if ($count >= 2) {
                return array(
                    'last_name' => 0,
                    'first_name' => 1,
                );
            }

            return null;
        }

        if ($count >= 3) {
            return array(
                'class_number' => 0,
                'last_name' => 1,
                'first_name' => 2,
            );
        }

        return null;
    }

    private function normalize_import_header(string $value): string
    {
        $value = strtolower(remove_accents($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function normalize_import_cell($value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value) ?: '';

        return sanitize_text_field(trim($value));
    }

    private function save_student_import_rows(array $rows): array
    {
        global $wpdb;

        $classes = GNE_Database::table('classes');
        $students = GNE_Database::table('students');
        $school_year = GNE_Database::default_school_year();
        $now = GNE_Database::now();
        $class_cache = array();
        $created_classes = 0;
        $created_students = 0;
        $updated_students = 0;

        foreach ($rows as $row) {
            $class_id = isset($row['class_id']) ? absint($row['class_id']) : 0;
            $class_number = (string) ($row['class_number'] ?? '');
            $last_name = (string) $row['last_name'];
            $first_name = (string) $row['first_name'];

            if ($class_id <= 0 && $class_number === '') {
                continue;
            }

            if ($class_id <= 0 && !isset($class_cache[$class_number])) {
                $class_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$classes} WHERE name = %s ORDER BY id ASC LIMIT 1",
                        $class_number
                    )
                );

                if ($class_id > 0) {
                    $wpdb->update(
                        $classes,
                        array('status' => 'active', 'updated_at' => $now),
                        array('id' => $class_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                } else {
                    $wpdb->insert(
                        $classes,
                        array(
                            'name' => $class_number,
                            'school_year' => $school_year,
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ),
                        array('%s', '%s', '%s', '%s', '%s')
                    );
                    $class_id = (int) $wpdb->insert_id;
                    $created_classes++;
                }

                $class_cache[$class_number] = $class_id;
            }

            if ($class_id <= 0) {
                $class_id = (int) ($class_cache[$class_number] ?? 0);
            }

            if ($class_id <= 0) {
                continue;
            }

            $student_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$students} WHERE class_id = %d AND last_name = %s AND first_name = %s LIMIT 1",
                    $class_id,
                    $last_name,
                    $first_name
                )
            );
            $student_data = array(
                'class_id' => $class_id,
                'last_name' => $last_name,
                'first_name' => $first_name,
                'identifier' => '',
                'email' => '',
                'status' => 'active',
                'updated_at' => $now,
            );
            $student_formats = array('%d', '%s', '%s', '%s', '%s', '%s', '%s');

            if ($student_id > 0) {
                $wpdb->update($students, $student_data, array('id' => $student_id), $student_formats, array('%d'));
                $updated_students++;
                continue;
            }

            $student_data['created_at'] = $now;
            $wpdb->insert($students, $student_data, array_merge($student_formats, array('%s')));
            $created_students++;
        }

        return array(
            'classes' => $created_classes,
            'students' => $created_students + $updated_students,
            'created_students' => $created_students,
            'updated_students' => $updated_students,
        );
    }

    private function classes_for_form(int $current_id = 0): array
    {
        global $wpdb;

        $table = GNE_Database::table('classes');
        if ($current_id > 0) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE (status = 'active' OR id = %d)" . LogiPilot_Permissions::notes_class_sql('id') . " ORDER BY name ASC",
                    $current_id
                )
            );
        }

        return $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'active'" . LogiPilot_Permissions::notes_class_sql('id') . " ORDER BY name ASC");
    }

    private function semesters_for_form(int $current_id = 0): array
    {
        global $wpdb;

        $table = GNE_Database::table('semesters');
        if ($current_id > 0) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = 'active' OR id = %d ORDER BY school_year DESC, sort_order ASC, start_date ASC, name ASC",
                    $current_id
                )
            );
        }

        return $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'active' ORDER BY school_year DESC, sort_order ASC, start_date ASC, name ASC");
    }

    private function branches_for_form(int $current_id = 0): array
    {
        global $wpdb;

        $table = GNE_Database::table('branches');
        if ($current_id > 0) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = 'active' OR id = %d ORDER BY name ASC",
                    $current_id
                )
            );
        }

        return $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'active' ORDER BY name ASC");
    }

    private function students_grouped_by_class(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM " . GNE_Database::table('students') . " WHERE status = 'active'" . LogiPilot_Permissions::notes_class_sql('class_id') . " ORDER BY last_name ASC, first_name ASC"
        );
        $grouped = array();

        foreach ($rows as $row) {
            $grouped[(int) $row->class_id][] = $row;
        }

        return $grouped;
    }

    private function recent_evaluations(int $limit): array
    {
        global $wpdb;

        $evaluations = GNE_Database::table('evaluations');
        $classes = GNE_Database::table('classes');
        $semesters = GNE_Database::table('semesters');
        $branches = GNE_Database::table('branches');
        $grades = GNE_Database::table('grades');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, c.name AS class_name, sem.name AS semester_name, b.name AS branch_name, COALESCE(stats.grade_count, 0) AS grade_count, COALESCE(stats.missing_count, 0) AS missing_count, stats.avg_note
                 FROM {$evaluations} e
                 LEFT JOIN {$classes} c ON c.id = e.class_id
                 LEFT JOIN {$semesters} sem ON sem.id = e.semester_id
                 LEFT JOIN {$branches} b ON b.id = e.branch_id
                 LEFT JOIN (
                    SELECT
                        evaluation_id,
                        SUM(CASE WHEN status = 'graded' AND note IS NOT NULL THEN 1 ELSE 0 END) AS grade_count,
                        SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END) AS missing_count,
                        AVG(CASE WHEN status = 'graded' AND note IS NOT NULL AND COALESCE(is_cheating, 0) = 0 THEN note ELSE NULL END) AS avg_note
                    FROM {$grades}
                    GROUP BY evaluation_id
                 ) stats ON stats.evaluation_id = e.id
                 WHERE 1 = 1" . LogiPilot_Permissions::notes_class_sql('e.class_id') . "
                 ORDER BY e.evaluation_date DESC, e.id DESC
                 LIMIT %d",
                $limit
            )
        );
    }

    private function agapepe_evaluations(): array
    {
        global $wpdb;

        $evaluations = GNE_Database::table('evaluations');
        $classes = GNE_Database::table('classes');
        $semesters = GNE_Database::table('semesters');
        $branches = GNE_Database::table('branches');
        $grades = GNE_Database::table('grades');

        return $wpdb->get_results(
            "SELECT
                e.*,
                c.name AS class_name,
                sem.name AS semester_name,
                sem.school_year AS semester_school_year,
                b.name AS branch_name,
                COALESCE(stats.grade_count, 0) AS grade_count,
                COALESCE(stats.missing_count, 0) AS missing_count,
                COALESCE(stats.not_applicable_count, 0) AS not_applicable_count,
                COALESCE(stats.cheating_count, 0) AS cheating_count,
                stats.avg_note
             FROM {$evaluations} e
             LEFT JOIN {$classes} c ON c.id = e.class_id
             LEFT JOIN {$semesters} sem ON sem.id = e.semester_id
             LEFT JOIN {$branches} b ON b.id = e.branch_id
             LEFT JOIN (
                SELECT
                    evaluation_id,
                    SUM(CASE WHEN status = 'graded' AND note IS NOT NULL THEN 1 ELSE 0 END) AS grade_count,
                    SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END) AS missing_count,
                    SUM(CASE WHEN status = 'not_applicable' THEN 1 ELSE 0 END) AS not_applicable_count,
                    SUM(CASE WHEN is_cheating = 1 THEN 1 ELSE 0 END) AS cheating_count,
                    AVG(CASE WHEN status = 'graded' AND note IS NOT NULL AND COALESCE(is_cheating, 0) = 0 THEN note ELSE NULL END) AS avg_note
                FROM {$grades}
                GROUP BY evaluation_id
             ) stats ON stats.evaluation_id = e.id
             WHERE 1 = 1" . LogiPilot_Permissions::notes_class_sql('e.class_id') . "
             ORDER BY e.agapepe_reported ASC, e.evaluation_date DESC, e.id DESC"
        );
    }

    private function evaluation_comparison_options(): array
    {
        global $wpdb;

        $evaluations = GNE_Database::table('evaluations');
        $semesters = GNE_Database::table('semesters');
        $branches = GNE_Database::table('branches');

        return $wpdb->get_results(
            "SELECT
                MIN(e.id) AS evaluation_id,
                e.module_name,
                e.semester_id,
                e.branch_id,
                e.total_points,
                sem.name AS semester_name,
                sem.school_year AS semester_school_year,
                b.name AS branch_name,
                COUNT(*) AS evaluation_count,
                COUNT(DISTINCT e.class_id) AS class_count,
                MIN(e.evaluation_date) AS first_date,
                MAX(e.evaluation_date) AS last_date
             FROM {$evaluations} e
             LEFT JOIN {$semesters} sem ON sem.id = e.semester_id
             LEFT JOIN {$branches} b ON b.id = e.branch_id
             WHERE e.module_name <> ''" . LogiPilot_Permissions::notes_class_sql('e.class_id') . "
             GROUP BY e.module_name, e.semester_id, e.branch_id, e.total_points, sem.name, sem.school_year, b.name
             ORDER BY MAX(e.evaluation_date) DESC, e.module_name ASC"
        );
    }

    private function evaluation_comparison(int $reference_evaluation_id): ?array
    {
        global $wpdb;

        $reference = $this->evaluation($reference_evaluation_id);
        if (!$reference) {
            return null;
        }

        $evaluations = GNE_Database::table('evaluations');
        $classes = GNE_Database::table('classes');
        $grades = GNE_Database::table('grades');
        $total_points = number_format((float) $reference->total_points, 2, '.', '');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    e.id,
                    e.evaluation_date,
                    e.total_points,
                    c.name AS class_name,
                    COALESCE(stats.grade_count, 0) AS grade_count,
                    COALESCE(stats.missing_count, 0) AS missing_count,
                    COALESCE(stats.not_applicable_count, 0) AS not_applicable_count,
                    stats.avg_note
                 FROM {$evaluations} e
                 LEFT JOIN {$classes} c ON c.id = e.class_id
                 LEFT JOIN (
                    SELECT
                        evaluation_id,
                        SUM(CASE WHEN status = 'graded' AND note IS NOT NULL THEN 1 ELSE 0 END) AS grade_count,
                        SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END) AS missing_count,
                        SUM(CASE WHEN status = 'not_applicable' THEN 1 ELSE 0 END) AS not_applicable_count,
                        AVG(CASE WHEN status = 'graded' AND note IS NOT NULL AND COALESCE(is_cheating, 0) = 0 THEN note ELSE NULL END) AS avg_note
                    FROM {$grades}
                    GROUP BY evaluation_id
                 ) stats ON stats.evaluation_id = e.id
                 WHERE e.module_name = %s
                   AND e.semester_id = %d
                   AND e.branch_id = %d
                   AND e.total_points = CAST(%s AS DECIMAL(10,2))
                 ORDER BY CASE WHEN stats.avg_note IS NULL THEN 1 ELSE 0 END ASC, stats.avg_note DESC, c.name ASC",
                (string) $reference->module_name,
                (int) $reference->semester_id,
                (int) $reference->branch_id,
                $total_points
            )
        );

        return array(
            'reference' => $reference,
            'rows' => $rows,
        );
    }

    private function evaluation(int $evaluation_id): ?object
    {
        global $wpdb;

        $evaluations = GNE_Database::table('evaluations');
        $classes = GNE_Database::table('classes');
        $semesters = GNE_Database::table('semesters');
        $branches = GNE_Database::table('branches');
        $evaluation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e.*, c.name AS class_name, c.school_year AS class_school_year, sem.name AS semester_name, sem.school_year AS semester_school_year, sem.start_date AS semester_start_date, sem.end_date AS semester_end_date, b.name AS branch_name
                 FROM {$evaluations} e
                 LEFT JOIN {$classes} c ON c.id = e.class_id
                 LEFT JOIN {$semesters} sem ON sem.id = e.semester_id
                 LEFT JOIN {$branches} b ON b.id = e.branch_id
                 WHERE e.id = %d",
                $evaluation_id
            )
        );

        if ($evaluation && !LogiPilot_Permissions::notes_class_allowed((int) $evaluation->class_id)) {
            return null;
        }

        return $evaluation ?: null;
    }

    private function evaluation_stats(int $evaluation_id): array
    {
        global $wpdb;

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN status = 'graded' AND note IS NOT NULL THEN 1 ELSE 0 END) AS grade_count,
                    SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END) AS missing_count,
                    SUM(CASE WHEN status = 'not_applicable' THEN 1 ELSE 0 END) AS not_applicable_count,
                    SUM(CASE WHEN is_cheating = 1 THEN 1 ELSE 0 END) AS cheating_count,
                    SUM(CASE WHEN is_makeup = 1 THEN 1 ELSE 0 END) AS makeup_count,
                    AVG(CASE WHEN status = 'graded' AND note IS NOT NULL AND COALESCE(is_cheating, 0) = 0 THEN note ELSE NULL END) AS avg_note,
                    MIN(CASE WHEN status = 'graded' THEN note ELSE NULL END) AS min_note,
                    MAX(CASE WHEN status = 'graded' THEN note ELSE NULL END) AS max_note
                 FROM " . GNE_Database::table('grades') . " WHERE evaluation_id = %d",
                $evaluation_id
            )
        );

        return array(
            'grade_count' => $stats ? (int) $stats->grade_count : 0,
            'missing_count' => $stats ? (int) $stats->missing_count : 0,
            'not_applicable_count' => $stats ? (int) $stats->not_applicable_count : 0,
            'cheating_count' => $stats ? (int) $stats->cheating_count : 0,
            'makeup_count' => $stats ? (int) $stats->makeup_count : 0,
            'avg_note' => $stats ? $stats->avg_note : null,
            'min_note' => $stats ? $stats->min_note : null,
            'max_note' => $stats ? $stats->max_note : null,
        );
    }

    private function evaluation_export_payload(int $evaluation_id): ?array
    {
        global $wpdb;

        $evaluation = $this->evaluation($evaluation_id);
        if (!$evaluation) {
            return null;
        }

        $students = GNE_Database::table('students');
        $grades = GNE_Database::table('grades');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    s.id,
                    s.last_name,
                    s.first_name,
                    g.points_obtained,
                    g.points_adjustment,
                    g.note,
                    g.is_cheating,
                    g.is_makeup,
                    g.makeup_date,
                    CASE
                        WHEN g.id IS NULL THEN 'missing'
                        WHEN g.status = 'not_applicable' THEN 'not_applicable'
                        WHEN g.note IS NULL THEN 'missing'
                        ELSE 'graded'
                    END AS grade_status,
                    g.reminded_at
                 FROM {$students} s
                 LEFT JOIN {$grades} g ON g.student_id = s.id AND g.evaluation_id = %d
                 WHERE (s.class_id = %d AND s.status = 'active') OR g.id IS NOT NULL
                 ORDER BY s.last_name ASC, s.first_name ASC",
                $evaluation_id,
                (int) $evaluation->class_id
            )
        );

        return array(
            'evaluation' => $evaluation,
            'rows' => $rows,
            'stats' => $this->evaluation_stats($evaluation_id),
        );
    }

    private function semester_export_payload(int $class_id, int $semester_id): ?array
    {
        global $wpdb;

        $classes = GNE_Database::table('classes');
        $semesters = GNE_Database::table('semesters');
        $evaluations_table = GNE_Database::table('evaluations');
        $branches_table = GNE_Database::table('branches');
        $students_table = GNE_Database::table('students');
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$classes} WHERE id = %d", $class_id));
        $semester = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$semesters} WHERE id = %d", $semester_id));

        if (!$class || !$semester) {
            return null;
        }

        $evaluations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, b.name AS branch_name
                 FROM {$evaluations_table} e
                 LEFT JOIN {$branches_table} b ON b.id = e.branch_id
                 WHERE e.class_id = %d AND e.semester_id = %d
                 ORDER BY e.evaluation_date ASC, e.id ASC",
                $class_id,
                $semester_id
            )
        );
        $evaluation_ids = array_map(static fn($evaluation): int => (int) $evaluation->id, $evaluations);
        $students = $this->participant_students($class_id, $evaluation_ids);
        $grades_by_student = $evaluation_ids ? $this->grades_by_student_for_evaluations($evaluation_ids) : array();
        $rows = array();
        $all_notes = array();

        foreach ($students as $student) {
            $student_grades = $grades_by_student[(int) $student->id] ?? array();
            $notes = array();

            foreach ($evaluations as $evaluation) {
                $grade = $student_grades[(int) $evaluation->id] ?? null;
                $grade_status = $this->grade_status_value($grade ? (string) ($grade->status ?? 'graded') : 'missing');

                if ($grade_status === 'graded' && $grade && $grade->note !== null) {
                    $note = (float) $grade->note;
                    $notes[] = $note;
                    if ((int) ($grade->is_cheating ?? 0) !== 1) {
                        $all_notes[] = $note;
                    }
                }
            }

            $rows[] = array(
                'student' => $student,
                'grades' => $student_grades,
                'average' => $notes ? array_sum($notes) / count($notes) : null,
                'note_count' => count($notes),
            );
        }

        return array(
            'class' => $class,
            'semester' => $semester,
            'evaluations' => $evaluations,
            'rows' => $rows,
            'stats' => array(
                'class_average' => $all_notes ? array_sum($all_notes) / count($all_notes) : null,
                'note_count' => count($all_notes),
            ),
        );
    }

    private function grade_map(int $evaluation_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . GNE_Database::table('grades') . " WHERE evaluation_id = %d",
                $evaluation_id
            )
        );
        $map = array();

        foreach ($rows as $row) {
            $map[(int) $row->student_id] = $row;
        }

        return $map;
    }

    private function grades_by_student_for_evaluations(array $evaluation_ids): array
    {
        global $wpdb;

        $evaluation_ids = array_values(array_filter(array_map('absint', $evaluation_ids)));
        if (!$evaluation_ids) {
            return array();
        }

        $rows = $wpdb->get_results(
            "SELECT * FROM " . GNE_Database::table('grades') . " WHERE evaluation_id IN (" . implode(',', $evaluation_ids) . ")"
        );
        $map = array();

        foreach ($rows as $row) {
            $map[(int) $row->student_id][(int) $row->evaluation_id] = $row;
        }

        return $map;
    }

    private function student_ids_for_class(int $class_id): array
    {
        global $wpdb;

        return array_map(
            'absint',
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM " . GNE_Database::table('students') . " WHERE class_id = %d AND status = 'active'",
                    $class_id
                )
            )
        );
    }

    private function class_exists(int $class_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . GNE_Database::table('classes') . " WHERE id = %d",
                $class_id
            )
        );
    }

    private function stored_class_school_year(int $class_id): string
    {
        global $wpdb;

        if ($class_id > 0) {
            $school_year = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT school_year FROM " . GNE_Database::table('classes') . " WHERE id = %d",
                    $class_id
                )
            );

            if (is_string($school_year) && $school_year !== '') {
                return sanitize_text_field($school_year);
            }
        }

        return GNE_Database::default_school_year();
    }

    private function semester_exists(int $semester_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . GNE_Database::table('semesters') . " WHERE id = %d",
                $semester_id
            )
        );
    }

    private function branch_exists(int $branch_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . GNE_Database::table('branches') . " WHERE id = %d",
                $branch_id
            )
        );
    }

    private function calculate_note(float $points, float $total_points, float $adjustment = 0.0): ?float
    {
        if ($total_points <= 0) {
            return null;
        }

        $adjusted_points = min(max($points + $adjustment, 0.0), $total_points);
        $raw_note = (($adjusted_points / $total_points) * 5) + 1;

        return round($raw_note * 2) / 2;
    }

    private function sanitize_points_adjustment($value): float
    {
        $adjustment = $this->sanitize_decimal($value);
        if ($adjustment === null) {
            return 0.0;
        }

        foreach (array(-2.5, -2.0, -1.5, -1.0, -0.5, 0.0, 0.5, 1.0, 1.5) as $allowed) {
            if (abs($adjustment - $allowed) < 0.00001) {
                return $allowed;
            }
        }

        return 0.0;
    }

    private function sanitize_decimal($value): ?float
    {
        $value = trim(str_replace(',', '.', (string) $value));

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function normalize_date_for_storage($value): ?string
    {
        $value = sanitize_text_field((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (array('Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y') as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function date_input_value($value): string
    {
        $value = (string) $value;

        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private function display_date(string $value): string
    {
        if ($value === '' || $value === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : '-';
    }

    /** Date d'une carte du tableau des évaluations : « 9 octobre 2026 ». */
    private function board_date(string $value): string
    {
        $timestamp = ($value === '' || $value === '0000-00-00') ? false : strtotime($value);

        return $timestamp ? date_i18n('j F Y', $timestamp) : '-';
    }

    private function display_datetime(string $value): string
    {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date_i18n(get_option('date_format') . ' H:i', $timestamp) : '-';
    }

    private function format_decimal($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $formatted = number_format((float) $value, 2, ',', '');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    private function format_note($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $float = (float) $value;
        if (abs($float - round($float)) < 0.00001) {
            return number_format($float, 0, ',', '');
        }

        return number_format($float, 1, ',', '');
    }

    private function format_average($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 1, ',', '');
    }

    private function number_input_value($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function grade_detail_summary(object $grade): string
    {
        $details = array();
        $adjustment = (float) ($grade->points_adjustment ?? 0);

        if ((int) ($grade->is_cheating ?? 0) === 1) {
            $details[] = 'Triche : note 1';
        }

        if (abs($adjustment) > 0.00001) {
            $details[] = $adjustment > 0
                ? 'Bonus +' . $this->format_decimal($adjustment)
                : 'Malus ' . $this->format_decimal($adjustment);
        }

        if ((int) ($grade->is_makeup ?? 0) === 1) {
            $date = $this->display_date((string) ($grade->makeup_date ?? ''));
            $details[] = 'Rattrapage le ' . $date;
        }

        return implode(' · ', $details);
    }

    private function semester_name(object $semester): string
    {
        $parts = array(trim((string) $semester->name), trim((string) $semester->school_year));

        return implode(' · ', array_filter($parts, static fn($part): bool => $part !== ''));
    }

    private function student_name(object $student): string
    {
        $name = trim((string) $student->last_name . ' ' . (string) $student->first_name);
        if (!empty($student->gne_other_class)) {
            $name .= ' (désormais en ' . (string) $student->gne_other_class . ')';
        }

        return $name;
    }

    /**
     * Participants d'une ou plusieurs évaluations d'une classe : les élèves actifs de la classe,
     * plus tout élève qui possède déjà une ligne de note dans ces évaluations (élève ayant changé de classe).
     * Un changement de classe ne retire donc jamais un élève de ses évaluations passées.
     */
    private function participant_students(int $class_id, array $evaluation_ids): array
    {
        global $wpdb;

        $students = GNE_Database::table('students');
        $grades = GNE_Database::table('grades');
        $classes = GNE_Database::table('classes');
        $evaluation_ids = array_values(array_filter(array_map('absint', $evaluation_ids)));
        $grade_clause = $evaluation_ids
            ? ' OR s.id IN (SELECT g.student_id FROM ' . $grades . ' g WHERE g.evaluation_id IN (' . implode(',', $evaluation_ids) . '))'
            : '';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, c.name AS current_class_name
                 FROM {$students} s
                 LEFT JOIN {$classes} c ON c.id = s.class_id
                 WHERE (s.class_id = %d AND s.status = 'active'){$grade_clause}
                 ORDER BY s.last_name ASC, s.first_name ASC",
                $class_id
            )
        );

        foreach ($rows as $row) {
            if ((int) $row->class_id !== $class_id) {
                $row->gne_other_class = (string) ($row->current_class_name ?: 'une autre classe');
            }
        }

        return $rows;
    }

    private function evaluation_context_label(object $evaluation): string
    {
        $parts = array(
            trim((string) ($evaluation->class_name ?? '')),
            trim((string) ($evaluation->branch_name ?? '')),
            trim((string) ($evaluation->semester_name ?? '')),
            $this->display_date((string) ($evaluation->evaluation_date ?? '')),
        );

        return implode(' · ', array_filter($parts, static fn($part): bool => $part !== '' && $part !== '-'));
    }

    private function evaluation_progress_status(object $evaluation): array
    {
        if ((int) ($evaluation->missing_count ?? 0) > 0) {
            return array(
                'label' => 'Notes à saisir',
                'class' => 'gne-progress-missing',
            );
        }

        if ((int) ($evaluation->agapepe_reported ?? 0) !== 1) {
            return array(
                'label' => 'Notes à reporter sur AGAPEPE',
                'class' => 'gne-progress-agapepe',
            );
        }

        return array(
            'label' => 'Tout est fait',
            'class' => 'gne-progress-done',
        );
    }

    private function comparison_option_label(object $option): string
    {
        $semester = trim((string) ($option->semester_name ?? ''));
        $school_year = trim((string) ($option->semester_school_year ?? ''));
        if ($semester !== '' && $school_year !== '') {
            $semester .= ' ' . $school_year;
        }

        $dates = $this->display_date((string) ($option->first_date ?? ''));
        if ((string) ($option->first_date ?? '') !== (string) ($option->last_date ?? '')) {
            $dates .= ' - ' . $this->display_date((string) ($option->last_date ?? ''));
        }

        $class_count = (int) ($option->class_count ?? 0);
        $parts = array(
            trim((string) ($option->module_name ?? '')),
            trim((string) ($option->branch_name ?? '')),
            $semester,
            $dates,
            $this->format_decimal($option->total_points ?? null) . ' pts',
            $class_count . ' classe' . ($class_count > 1 ? 's' : ''),
        );

        return implode(' · ', array_filter($parts, static fn($part): bool => $part !== '' && $part !== '-'));
    }

    private function grade_status_value(string $status): string
    {
        return in_array($status, array('graded', 'missing', 'not_applicable'), true) ? $status : 'missing';
    }

    private function parse_email_list(string $value): array
    {
        $parts = preg_split('/[\s,;]+/', $value) ?: array();
        $emails = array();

        foreach ($parts as $part) {
            $email = sanitize_email($part);

            if ($email !== '' && is_email($email)) {
                $emails[$email] = $email;
            }
        }

        return array_values($emails);
    }

    private function status_label(string $status): string
    {
        return $status === 'archived' ? 'Archivée' : 'Active';
    }

    private function page_url(array $args = array()): string
    {
        if ($this->is_admin_app_request()) {
            $page = isset($args['gne_tab'])
                ? $this->admin_page_for_tab((string) $args['gne_tab'])
                : sanitize_key(wp_unslash($_GET['page'] ?? 'gne_app'));
            unset($args['gne_tab']);
            $base = admin_url('admin.php?page=' . $page);
        } elseif (is_admin()) {
            $base = wp_get_referer();
        } else {
            $base = get_permalink(get_queried_object_id());
        }

        if (!$base || str_contains($base, 'admin-post.php')) {
            $base = $this->admin_referer_url();
        }

        if (!$base) {
            $base = home_url('/');
        }

        $clean = remove_query_arg(array('gne_notice', 'evaluation_id', 'class_id', 'semester_id', 'compare_evaluation_id'), $base);

        return add_query_arg($args, $clean);
    }

    private function export_evaluation_pdf_url(int $evaluation_id): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=gne_export_evaluation_pdf&evaluation_id=' . $evaluation_id),
            'gne_export_evaluation_pdf_' . $evaluation_id
        );
    }

    private function export_semester_pdf_url(int $class_id, int $semester_id): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=gne_export_semester_pdf&class_id=' . $class_id . '&semester_id=' . $semester_id),
            'gne_export_semester_pdf_' . $class_id . '_' . $semester_id
        );
    }

    private function progress_pdf_download_url(int $evaluation_id): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=gne_download_progress_pdf&evaluation_id=' . $evaluation_id),
            'gne_download_progress_pdf_' . $evaluation_id
        );
    }

    private function progress_pdf_protected_download_url(int $evaluation_id): string
    {
        return admin_url('admin-post.php?action=gne_download_progress_pdf&evaluation_id=' . $evaluation_id);
    }

    private function save_progress_pdf_upload(int $evaluation_id): bool
    {
        if (empty($_FILES['progress_pdf']) || !is_array($_FILES['progress_pdf'])) {
            return true;
        }

        $file = $_FILES['progress_pdf'];
        if (is_array($file['error'] ?? null) || is_array($file['tmp_name'] ?? null) || is_array($file['name'] ?? null) || is_array($file['size'] ?? null)) {
            return false;
        }

        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return true;
        }

        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $original_name = isset($file['name']) ? (string) $file['name'] : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        $max_size = defined('MB_IN_BYTES') ? 10 * MB_IN_BYTES : 10 * 1024 * 1024;

        if ($error !== UPLOAD_ERR_OK || $tmp_name === '' || !is_uploaded_file($tmp_name) || $size <= 0 || $size > $max_size) {
            return false;
        }

        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            return false;
        }

        $handle = @fopen($tmp_name, 'rb');
        if (!$handle) {
            return false;
        }

        $signature = fread($handle, 4);
        fclose($handle);

        if ($signature !== '%PDF') {
            return false;
        }

        $directory = $this->progress_pdf_upload_dir();
        if (!$directory) {
            return false;
        }

        global $wpdb;

        $safe_original = sanitize_file_name($original_name);
        if ($safe_original === '') {
            $safe_original = 'justificatif-bonus-malus.pdf';
        }

        // Nom impossible à deviner (le téléchargement garde le nom d'origine) : protège aussi si le serveur ignore .htaccess.
        $filename = wp_unique_filename($directory['path'], $evaluation_id . '-' . strtolower(wp_generate_password(16, false, false)) . '-' . $safe_original);
        $target_path = trailingslashit($directory['path']) . $filename;

        if (!move_uploaded_file($tmp_name, $target_path)) {
            return false;
        }

        $evaluation = $this->evaluation($evaluation_id);
        $old_path = $evaluation ? $this->progress_pdf_absolute_path((string) ($evaluation->progress_pdf_path ?? '')) : '';
        if ($old_path !== '' && is_readable($old_path)) {
            @unlink($old_path);
        }

        $wpdb->update(
            GNE_Database::table('evaluations'),
            array(
                'progress_pdf_path' => $directory['relative'] . '/' . $filename,
                'progress_pdf_name' => $safe_original,
                'progress_pdf_uploaded_at' => GNE_Database::now(),
                'updated_at' => GNE_Database::now(),
            ),
            array('id' => $evaluation_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        return true;
    }

    private function progress_pdf_upload_dir(): ?array
    {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return null;
        }

        $relative = 'loginotes-documents' . (class_exists('LogiPilot_Spaces') ? LogiPilot_Spaces::dir_suffix() : '');
        $path = trailingslashit((string) $uploads['basedir']) . $relative;

        if (!wp_mkdir_p($path)) {
            return null;
        }

        $index_path = trailingslashit($path) . 'index.html';
        if (!file_exists($index_path) && file_put_contents($index_path, '') === false) {
            return null;
        }

        $htaccess_path = trailingslashit($path) . '.htaccess';
        if (!file_exists($htaccess_path) && file_put_contents($htaccess_path, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n") === false) {
            return null;
        }

        return array(
            'path' => $path,
            'relative' => $relative,
        );
    }

    private function progress_pdf_absolute_path(string $relative_path): string
    {
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        if ($relative_path === '' || str_contains($relative_path, '..')) {
            return '';
        }

        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        return trailingslashit((string) $uploads['basedir']) . $relative_path;
    }

    private function hidden_page_fields(array $fields): void
    {
        if ($this->is_admin_app_request() && !isset($fields['page'])) {
            $fields = array('page' => sanitize_key(wp_unslash($_GET['page'] ?? 'gne_app'))) + $fields;
        } elseif (!is_admin()) {
            // LogiOne 1.53.20 : un formulaire GET (relevé, comparaison) remplace toute la requête ;
            // on garde l'outil ouvert dans le portail LogiOne (lp_module) et la page sans jolis liens (page_id).
            foreach (array('page_id', 'lp_module') as $keep) {
                if (!isset($fields[$keep]) && isset($_GET[$keep]) && '' !== $_GET[$keep]) {
                    $fields = array($keep => sanitize_key(wp_unslash($_GET[$keep]))) + $fields;
                }
            }
        }

        foreach ($fields as $key => $value) {
            printf('<input type="hidden" name="%s" value="%s">', esc_attr($key), esc_attr((string) $value));
        }
    }

    private function tab_from_admin_page(): string
    {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? 'gne_app'));
        $map = array(
            'gne_classes' => 'classes',
            'gne_branches' => 'branches',
            'gne_semesters' => 'semesters',
            'gne_report' => 'report',
            'gne_agapepe' => 'agapepe',
            'gne_settings' => 'settings',
        );

        return $map[$page] ?? 'evaluations';
    }

    private function admin_page_for_tab(string $tab): string
    {
        $map = array(
            'classes' => 'gne_classes',
            'branches' => 'gne_branches',
            'semesters' => 'gne_semesters',
            'report' => 'gne_report',
            'agapepe' => 'gne_agapepe',
            'settings' => 'gne_settings',
            'evaluations' => 'gne_app',
        );

        return $map[$tab] ?? 'gne_app';
    }

    private function is_admin_app_request(): bool
    {
        if (!is_admin() || !isset($_GET['page'])) {
            return false;
        }

        return in_array(
            sanitize_key(wp_unslash($_GET['page'])),
            array('gne_app', 'gne_classes', 'gne_branches', 'gne_semesters', 'gne_report', 'gne_agapepe', 'gne_settings'),
            true
        );
    }

    private function admin_referer_url(): string
    {
        $referer = wp_get_referer();

        if ($referer && preg_match('/page=gne_(app|classes|branches|semesters|report|settings)/', $referer)) {
            return $referer;
        }

        if (is_admin()) {
            return admin_url('admin.php?page=gne_app');
        }

        $permalink = get_permalink(get_queried_object_id());

        return $permalink ?: home_url('/');
    }

    private function redirect_notice(string $tab, string $notice): void
    {
        wp_safe_redirect($this->page_url(array('gne_tab' => $tab, 'gne_notice' => $notice)));
        exit;
    }

    private function redirect_import_notice(int $class_id, string $notice): void
    {
        $args = array('gne_tab' => 'classes', 'gne_notice' => $notice);
        if ($class_id > 0) {
            $args['class_id'] = $class_id;
        }

        wp_safe_redirect($this->page_url($args));
        exit;
    }

    /** LogiOne : seule la permission centrale compte (gca_manage ne donne plus accès, ACL-08). */
    private function can_manage(): bool
    {
        return current_user_can('lp_notes_view');
    }

    /** Permission exigée par chaque action, puis contrôle du périmètre de classe (ACL-03, SEC-01). */
    private function require_manage(): void
    {
        $action = sanitize_key((string) wp_unslash($_REQUEST['action'] ?? ''));
        $map = array(
            'gne_save_class' => 'lp_notes_edit', 'gne_import_students' => 'lp_notes_edit', 'gne_save_student' => 'lp_notes_edit',
            'gne_save_branch' => 'lp_notes_edit', 'gne_save_semester' => 'lp_notes_edit', 'gne_save_settings' => 'lp_notes_edit',
            'gne_save_agapepe_status' => 'lp_notes_edit', 'gne_save_evaluation' => 'lp_notes_grade',
            'gne_delete_class' => 'lp_notes_delete', 'gne_delete_student' => 'lp_notes_delete', 'gne_delete_branch' => 'lp_notes_delete',
            'gne_delete_semester' => 'lp_notes_delete', 'gne_delete_evaluation' => 'lp_notes_delete',
            'gne_export_evaluation_pdf' => 'lp_notes_export', 'gne_export_semester_pdf' => 'lp_notes_export', 'gne_download_progress_pdf' => 'lp_notes_export',
        );
        $perm = $map[$action] ?? 'lp_notes_edit';
        $ok = current_user_can('lp_notes_view') && current_user_can($perm);
        if ($ok && $action === 'gne_save_evaluation' && (int) ($_POST['evaluation_id'] ?? 0) === 0) {
            $ok = current_user_can('lp_notes_edit');
        }
        if ($ok && in_array($action, array('gne_save_class', 'gne_save_branch', 'gne_save_semester', 'gne_save_settings', 'gne_delete_branch', 'gne_delete_semester'), true)) {
            // Données partagées par toutes les classes : réservé aux utilisateurs sans restriction de périmètre.
            $ok = LogiPilot_Permissions::notes_allowed_class_ids() === null || ($action === 'gne_save_class' && (int) ($_POST['class_id'] ?? 0) > 0);
        }
        if ($ok) {
            $ok = $this->request_in_scope();
        }
        if (!$ok) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
    }

    private function request_in_scope(): bool
    {
        global $wpdb;

        if (LogiPilot_Permissions::notes_allowed_class_ids() === null) {
            return true;
        }
        $class_ids = array();
        foreach (array('class_id', 'target_class_id') as $key) {
            if (!empty($_REQUEST[$key])) {
                $class_ids[] = (int) $_REQUEST[$key];
            }
        }
        if (!empty($_REQUEST['evaluation_id'])) {
            $class_ids[] = (int) $wpdb->get_var($wpdb->prepare('SELECT class_id FROM ' . GNE_Database::table('evaluations') . ' WHERE id = %d', (int) $_REQUEST['evaluation_id']));
        }
        if (!empty($_REQUEST['student_id'])) {
            $class_ids[] = (int) $wpdb->get_var($wpdb->prepare('SELECT class_id FROM ' . GNE_Database::table('students') . ' WHERE id = %d', (int) $_REQUEST['student_id']));
        }
        foreach ($class_ids as $class_id) {
            if (!LogiPilot_Permissions::notes_class_allowed($class_id)) {
                return false;
            }
        }

        return true;
    }
}
