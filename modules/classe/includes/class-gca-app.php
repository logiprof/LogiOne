<?php

if (!defined('ABSPATH')) {
    exit;
}

class GCA_App
{
    private static ?GCA_App $instance = null;
    private GCA_Importer $importer;
    private string $last_mail_error = '';
    private const PRINT_COPY_EMAIL = 'carlos.fontes@eduvaud.ch';
    private const WEEKLY_REMINDER_HOOK = 'gca_weekly_followup_reminder';
    private const STALE_TASK_REMINDER_HOOK = 'gca_stale_task_reminder';
    private const IMPORT_REMINDER_HOOK = 'gca_import_freshness_reminder';

    public static function instance(): GCA_App
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->importer = new GCA_Importer();

        add_shortcode('gestion_apprentis', array($this, 'render_shortcode'));
        add_action('init', array($this, 'register_assets'));
        add_action('init', array($this, 'ensure_internal_reminder_schedules'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action(self::STALE_TASK_REMINDER_HOOK, array($this, 'send_stale_task_reminder'));
        add_action(self::IMPORT_REMINDER_HOOK, array($this, 'send_import_freshness_reminder'));

        $actions = array(
            'import_prepare',
            'import_commit',
            'save_settings',
            'save_class',
            'save_student',
            'save_student_quick_edit',
            'save_trainer',
            'save_trainer_assignments',
            'student_photo',
            'import_photos_pdf',
            'import_photos_commit',
            'photo_import_preview',
            'generate_student_report',
            'send_student_report',
            'generate_class_reports',
            'save_trainer_profiles',
            'save_free_message_template',
            'send_trainer_bulk_message',
            'send_email',
            'justify_student_absence',
            'justify_absence_task',
            'mark_task_done',
            'save_task',
            'delete_task',
            'save_action',
            'delete_action',
            'register_detention_no_show',
            'archive_student',
            'anonymize_student',
            'delete_student',
            'delete_class',
            'confirm_warning',
            'save_exclusion',
            'save_disciplinary_exclusion',
            'delete_disciplinary_exclusion',
            'download_pdf',
            'export_student',
            'export_students',
            'export_trainers',
        );

        foreach ($actions as $action) {
            add_action('admin_post_gca_' . $action, array($this, 'handle_' . $action));
        }
    }

    public function register_assets(): void
    {
        wp_register_style('gca-app', GCA_URL . 'assets/app.css', array(), GCA_VERSION);
        wp_register_script('gca-app', GCA_URL . 'assets/app.js', array(), GCA_VERSION, true);
    }

    public function ensure_internal_reminder_schedules(): void
    {
        wp_clear_scheduled_hook(self::WEEKLY_REMINDER_HOOK);

        // Un seul rappel planifié pour tous les enseignants : actif si au moins un espace l'a activé.
        $settings = class_exists('LogiPilot_Spaces') ? LogiPilot_Spaces::reminder_settings('classe', array('GCA_Database', 'get_settings'), array('stale_task_reminder_enabled', 'import_reminder_enabled')) : GCA_Database::get_settings();
        $time = (string) ($settings['stale_task_reminder_time'] ?? '08:15');

        if (!empty($settings['stale_task_reminder_enabled'])) {
            if (!wp_next_scheduled(self::STALE_TASK_REMINDER_HOOK)) {
                wp_schedule_event($this->next_daily_timestamp($time), 'daily', self::STALE_TASK_REMINDER_HOOK);
            }
        } else {
            wp_clear_scheduled_hook(self::STALE_TASK_REMINDER_HOOK);
        }

        if (!empty($settings['import_reminder_enabled'])) {
            if (!wp_next_scheduled(self::IMPORT_REMINDER_HOOK)) {
                wp_schedule_event($this->next_daily_timestamp($time), 'daily', self::IMPORT_REMINDER_HOOK);
            }
        } else {
            wp_clear_scheduled_hook(self::IMPORT_REMINDER_HOOK);
        }
    }

    private function reschedule_internal_reminders(): void
    {
        wp_clear_scheduled_hook(self::WEEKLY_REMINDER_HOOK);
        wp_clear_scheduled_hook(self::STALE_TASK_REMINDER_HOOK);
        wp_clear_scheduled_hook(self::IMPORT_REMINDER_HOOK);
        $this->ensure_internal_reminder_schedules();
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            'LogiClass',
            'LogiClass',
            'lp_classe_view',
            'gca_app',
            array($this, 'render_admin_page'),
            'dashicons-welcome-learn-more',
            26
        );

        add_submenu_page(
            'gca_app',
            'À traiter',
            'À traiter',
            'lp_classe_view',
            'gca_app',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Classes',
            'Classes',
            'lp_classe_view',
            'gca_classes',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Apprentis',
            'Apprentis',
            'lp_classe_view',
            'gca_students',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Formateurs',
            'Formateurs',
            'lp_classe_view',
            'gca_trainers',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Actions exécutées',
            'Actions exécutées',
            'lp_classe_view',
            'gca_actions',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Communication',
            'Communication',
            'lp_classe_view',
            'gca_reports',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Messages types',
            'Messages types',
            'lp_classe_view',
            'gca_templates',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Imports',
            'Imports',
            'lp_classe_view',
            'gca_imports',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'gca_app',
            'Paramètres',
            'Paramètres',
            'lp_classe_view',
            'gca_settings',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page(): void
    {
        echo '<div class="wrap gca-admin-wrap">';
        echo $this->render_app();
        echo '</div>';
    }

    public function render_shortcode(): string
    {
        return '<div class="gca-shortcode-fullwidth">' . $this->render_app() . '</div>';
    }

    private function render_rich_text_editor(string $name, string $value, array $args = array()): void
    {
        static $editor_count = 0;

        $editor_count++;
        $editor_id = 'gca-rich-editor-' . $editor_count;
        $rows = max(4, (int) ($args['rows'] ?? 8));
        $label = (string) ($args['label'] ?? 'Message');
        $required = !empty($args['required']);
        $source_attrs = is_array($args['source_attrs'] ?? null) ? $args['source_attrs'] : array();
        $source_attr_html = '';

        foreach ($source_attrs as $attr => $attr_value) {
            if (!preg_match('/^[a-zA-Z0-9_:-]+$/', (string) $attr)) {
                continue;
            }

            $source_attr_html .= ' ' . esc_attr((string) $attr);
            if ($attr_value !== '') {
                $source_attr_html .= '="' . esc_attr((string) $attr_value) . '"';
            }
        }

        ?>
        <div class="gca-editor-field">
            <span class="gca-field-label"><?php echo esc_html($label); ?></span>
            <div class="gca-rich-wrap" data-gca-rich-wrap>
                <div class="gca-rich-toolbar" role="toolbar" aria-label="Mise en forme du message">
                    <button type="button" data-gca-rich-command="bold" title="Gras" aria-label="Gras"><strong>B</strong></button>
                    <button type="button" data-gca-rich-command="italic" title="Italique" aria-label="Italique"><em>I</em></button>
                    <button type="button" data-gca-rich-command="underline" title="Souligné" aria-label="Souligné"><span class="gca-rich-underline">U</span></button>
                    <button type="button" data-gca-rich-command="insertUnorderedList" title="Liste à puces" aria-label="Liste à puces">•</button>
                    <button type="button" data-gca-rich-command="insertOrderedList" title="Liste numérotée" aria-label="Liste numérotée">1.</button>
                    <button type="button" data-gca-rich-link title="Lien" aria-label="Lien">Lien</button>
                    <button type="button" data-gca-rich-command="removeFormat" title="Retirer la mise en forme" aria-label="Retirer la mise en forme">Tx</button>
                </div>
                <div
                    id="<?php echo esc_attr($editor_id); ?>"
                    class="gca-rich-area"
                    contenteditable="true"
                    data-gca-rich-editor
                    data-gca-rich-target="<?php echo esc_attr($editor_id); ?>"
                    aria-label="<?php echo esc_attr($label); ?>"
                    style="min-height:<?php echo esc_attr((string) max(112, $rows * 24)); ?>px;"
                ><?php echo $this->rich_editor_display_html($value); ?></div>
                <textarea
                    class="gca-rich-source"
                    name="<?php echo esc_attr($name); ?>"
                    rows="<?php echo esc_attr((string) $rows); ?>"
                    data-gca-rich-source
                    data-gca-rich-source-id="<?php echo esc_attr($editor_id); ?>"
                    <?php echo $required ? 'data-gca-required="1"' : ''; ?>
                    tabindex="-1"
                    aria-hidden="true"<?php echo $source_attr_html; ?>
                ><?php echo esc_textarea($value); ?></textarea>
            </div>
        </div>
        <?php
    }

    private function rich_editor_display_html(string $content): string
    {
        $content = $this->normalize_email_text($content);
        if ($content === '') {
            return '';
        }

        if (trim(wp_strip_all_tags($content)) !== trim($content)) {
            return GCA_Database::sanitize_message_body($content);
        }

        $paragraphs = preg_split('/\n{2,}/', $content) ?: array();
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }

            $lines = array_map('esc_html', explode("\n", $paragraph));
            $html .= '<p>' . implode('<br>', $lines) . '</p>';
        }

        return $html;
    }

    private function maybe_recalculate_tasks_once(): void
    {
        if ((int) get_option(LogiPilot_Spaces::option('gca_recalculate_tasks_once'), 0) !== 1) {
            return;
        }

        $this->importer->recalculate_all_active_tasks();
        delete_option(LogiPilot_Spaces::option('gca_recalculate_tasks_once'));
    }

    private function render_app(): string
    {
        if (!is_user_logged_in()) {
            return '<div class="gca-access">Veuillez vous connecter pour accéder à la gestion des apprentis.</div>';
        }

        if (!$this->can_manage()) {
            return '<div class="gca-access">Accès refusé.</div>';
        }

        GCA_Database::ensure_private_dirs();
        $this->maybe_recalculate_tasks_once();
        wp_enqueue_style('gca-app');
        wp_enqueue_script('gca-app');

        $tab = isset($_GET['gca_tab']) ? sanitize_key(wp_unslash($_GET['gca_tab'])) : $this->tab_from_admin_page();
        $allowed_tabs = array('dashboard', 'classes', 'students', 'trainers', 'actions', 'reports', 'templates', 'imports', 'audit', 'settings');
        if (!in_array($tab, $allowed_tabs, true)) {
            $tab = 'dashboard';
        }

        ob_start();
        ?>
        <div class="gca-app">
            <header class="gca-header">
                <div>
                    <p class="gca-kicker">LogiClass</p>
                    <h1>Suivi des apprentis</h1>
                </div>
                <div class="gca-header-actions">
                    <span class="gca-chip"><?php echo esc_html(GCA_Database::get_settings()['school_year']); ?></span>
                </div>
            </header>

            <?php $this->render_notice(); ?>

            <nav class="gca-tabs" aria-label="Navigation gestion apprentis">
                <?php
                $tabs = array(
                    'dashboard' => 'À traiter',
                    'classes' => 'Classes',
                    'students' => 'Apprentis',
                    'trainers' => 'Formateurs',
                    'actions' => 'Actions exécutées',
                    'reports' => 'Communication',
                    'templates' => 'Messages types',
                    'imports' => 'Imports',
                    'settings' => 'Paramètres',
                );
                foreach ($tabs as $key => $label) :
                    ?>
                    <a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->page_url(array('gca_tab' => $key))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <main class="gca-main">
                <?php
                $gca_action = isset($_GET['gca_action']) ? sanitize_key(wp_unslash($_GET['gca_action'])) : '';
                if ($gca_action === 'prepare_email' && isset($_GET['task_id'])) {
                    $this->render_email_form((int) $_GET['task_id']);
                } elseif ($gca_action === 'edit_task' && isset($_GET['task_id'])) {
                    $this->render_task_edit((int) $_GET['task_id']);
                } elseif ($gca_action === 'edit_action' && isset($_GET['source'], $_GET['action_id'])) {
                    $this->render_action_edit(sanitize_key(wp_unslash($_GET['source'])), (int) $_GET['action_id']);
                } elseif ($gca_action === 'prepare_report_email' && isset($_GET['student_id'])) {
                    $this->render_report_email_form((int) $_GET['student_id']);
                } elseif ($tab === 'classes' && isset($_GET['student_id'])) {
                    $this->render_student_detail((int) $_GET['student_id']);
                } else {
                    switch ($tab) {
                        case 'classes':
                            $this->render_classes();
                            break;
                        case 'students':
                            $this->render_students_quick_edit();
                            break;
                        case 'trainers':
                            $this->render_trainers();
                            break;
                        case 'imports':
                            $this->render_imports();
                            break;
                        case 'actions':
                            $this->render_executed_actions();
                            break;
                        case 'reports':
                            $this->render_reports();
                            break;
                        case 'templates':
                            $this->render_message_templates();
                            break;
                        case 'audit':
                            $this->render_audit();
                            break;
                        case 'settings':
                            $this->render_settings();
                            break;
                        case 'dashboard':
                        default:
                            $this->render_dashboard();
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
        if (empty($_GET['gca_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['gca_notice']));
        $messages = array(
            'import_ready' => array('type' => 'success', 'text' => 'Fichier analysé. Vérifiez le mapping avant de lancer l’import.'),
            'import_ok' => array('type' => 'success', 'text' => 'Import terminé. Les situations à traiter ont été recalculées.'),
            'import_ok_mapping_saved' => array('type' => 'success', 'text' => 'Import terminé. Le mapping a été sauvegardé pour vos prochains imports de ce type.'),
            'photos_ready' => array('type' => 'success', 'text' => 'Photos extraites. Vérifiez chaque attribution avant de modifier les fiches apprentis.'),
            'photos_imported' => array('type' => 'success', 'text' => 'Photos importées depuis le PDF et rattachées aux fiches validées.'),
            'photos_imported_with_skips' => array('type' => 'success', 'text' => 'Photos importées. Certaines photos n’ont pas été rattachées ; le détail apparaît dans l’historique des imports.'),
            'photos_no_exact_match' => array('type' => 'error', 'text' => 'Aucune photo n’a été rattachée. Vérifiez les attributions et relancez l’import si nécessaire.'),
            'photos_no_assignment' => array('type' => 'error', 'text' => 'Aucune attribution de photo n’a été sélectionnée. Choisissez au moins un apprenti ou annulez l’import.'),
            'photos_duplicate_assignment' => array('type' => 'error', 'text' => 'Une même fiche apprenti ne peut pas recevoir deux photos dans le même import. Corrigez les attributions en double.'),
            'settings_saved' => array('type' => 'success', 'text' => 'Paramètres enregistrés.'),
            'class_saved' => array('type' => 'success', 'text' => 'Classe enregistrée.'),
            'class_deleted' => array('type' => 'success', 'text' => 'Classe supprimée avec ses élèves et actions associées.'),
            'class_exists' => array('type' => 'error', 'text' => 'Une autre classe utilise déjà ce nom pour cette année scolaire.'),
            'class_has_master' => array('type' => 'error', 'text' => 'Cette classe a déjà un maître de classe (un autre enseignant) : elle ne peut pas être créée chez vous. Si elle doit vous revenir, adressez-vous à l’administrateur.'),
            'student_saved' => array('type' => 'success', 'text' => 'Fiche apprenti enregistrée.'),
            'students_quick_saved' => array('type' => 'success', 'text' => 'Les fiches élèves ont été mises à jour.'),
            'students_bulk_saved' => array('type' => 'success', 'text' => 'L’action groupée a été appliquée aux apprentis sélectionnés.'),
            'students_bulk_missing' => array('type' => 'error', 'text' => 'Sélectionnez au moins un apprenti avant d’appliquer une action groupée.'),
            'students_quick_no_change' => array('type' => 'success', 'text' => 'Aucune modification à enregistrer.'),
            'trainer_saved' => array('type' => 'success', 'text' => 'Formateur responsable enregistré. Les fiches des apprentis rattachés ont été mises à jour.'),
            'trainer_assignments_saved' => array('type' => 'success', 'text' => 'Rattachements au responsable mis à jour. Les fiches élèves concernées ont été actualisées.'),
            'trainer_missing' => array('type' => 'error', 'text' => 'Indiquez au moins un nom ou un e-mail de responsable.'),
            'student_deleted' => array('type' => 'success', 'text' => 'Apprenti supprimé.'),
            'email_sent' => array('type' => 'success', 'text' => 'E-mail envoyé avec succès. L’action a été déplacée dans l’historique.'),
            'report_sent' => array('type' => 'success', 'text' => 'Rapport envoyé au responsable de formation et historisé.'),
            'trainer_bulk_sent' => array('type' => 'success', 'text' => 'E-mails individuels envoyés aux responsables de formation. Chaque fiche apprenti concernée a été historisée.'),
            'trainer_bulk_test_sent' => array('type' => 'success', 'text' => 'E-mail de test envoyé uniquement aux adresses indiquées. Aucun destinataire réel de la classe n’a reçu ce message.'),
            'trainer_bulk_contract_break_only' => array('type' => 'error', 'text' => 'Aucun e-mail formateur envoyé : les apprentis sélectionnés sont en rupture, candidats libres, ou aucun e-mail de responsable valide n’est disponible.'),
            'trainer_template_saved' => array('type' => 'success', 'text' => 'Message préformaté enregistré. Vous pourrez le réutiliser pour un prochain envoi.'),
            'free_template_saved' => array('type' => 'success', 'text' => 'Modèle de message libre enregistré. Vous pourrez le réutiliser depuis un e-mail libre.'),
            'trainer_profiles_saved' => array('type' => 'success', 'text' => 'Formules d’appel des formateurs enregistrées.'),
            'report_ready' => array('type' => 'success', 'text' => 'Rapport généré.'),
            'task_done' => array('type' => 'success', 'text' => 'Tâche marquée comme traitée.'),
            'absence_justified' => array('type' => 'success', 'text' => 'Absence validée sans envoi d’e-mail. Le jour et les périodes justifiées sont mémorisés dans la fiche de l’apprenti.'),
            'absence_justification_missing' => array('type' => 'error', 'text' => 'Indiquez l’apprenti, le jour et le nombre de périodes d’absence à justifier.'),
            'absence_justification_bad_date' => array('type' => 'error', 'text' => 'La date du justificatif d’absence n’est pas valide.'),
            'task_saved' => array('type' => 'success', 'text' => 'Action à traiter modifiée.'),
            'task_deleted' => array('type' => 'success', 'text' => 'Action à traiter supprimée.'),
            'action_saved' => array('type' => 'success', 'text' => 'Action exécutée modifiée.'),
            'action_deleted' => array('type' => 'success', 'text' => 'Action exécutée supprimée.'),
            'detention_no_show_saved' => array('type' => 'success', 'text' => 'Retenue manquée enregistrée. Cette information sera reprise dans les prochaines convocations.'),
            'student_archived' => array('type' => 'success', 'text' => 'Apprenti archivé.'),
            'student_anonymized' => array('type' => 'success', 'text' => 'Fiche anonymisée.'),
            'warning_confirmed' => array('type' => 'success', 'text' => 'Avertissement confirmé.'),
            'exclusion_saved' => array('type' => 'success', 'text' => 'Exclusion temporaire enregistrée.'),
            'disciplinary_exclusion_saved' => array('type' => 'success', 'text' => 'Exclusion enregistrée.'),
            'disciplinary_exclusion_updated' => array('type' => 'success', 'text' => 'Exclusion modifiée.'),
            'disciplinary_exclusion_deleted' => array('type' => 'success', 'text' => 'Exclusion supprimée.'),
            'missing_convocation_date' => array('type' => 'error', 'text' => 'La date de convocation est obligatoire.'),
            'missing_exclusion_date' => array('type' => 'error', 'text' => 'Indiquez au moins une date pour l’exclusion.'),
            'bad_exclusion_dates' => array('type' => 'error', 'text' => 'La date de fin doit être après la date de début.'),
            'test_recipients_missing' => array('type' => 'error', 'text' => 'Renseignez au moins une adresse de test valide.'),
            'student_contract_broken' => array('type' => 'error', 'text' => 'Cet apprenti est sans employeur actif : aucun e-mail ne peut être envoyé au formateur depuis cette action.'),
            'missing_file' => array('type' => 'error', 'text' => 'Aucun fichier valide n’a été transmis.'),
            'bad_import' => array('type' => 'error', 'text' => 'Le fichier n’a pas pu être lu.'),
            'not_lagap' => array('type' => 'error', 'text' => 'Ce fichier ne ressemble pas à un export Lagap-EP. Utilisez Exporter - Export CSV depuis la recherche d’élèves.'),
            'photos_import_failed' => array('type' => 'error', 'text' => 'Les photos n’ont pas pu être extraites de ce PDF. Vérifiez qu’il s’agit bien d’une liste de classe avec photos.'),
            'zip_missing' => array('type' => 'error', 'text' => 'La génération ZIP nécessite l’extension PHP ZipArchive sur l’hébergement.'),
            'missing_mapping' => array('type' => 'error', 'text' => 'Le mapping ne contient pas les champs obligatoires.'),
            'email_failed' => array('type' => 'error', 'text' => 'L’e-mail n’a pas pu être envoyé. Vérifiez les destinataires et la configuration SMTP.'),
            'not_found' => array('type' => 'error', 'text' => 'Élément introuvable.'),
        );

        if (!isset($messages[$notice])) {
            return;
        }

        $text = $messages[$notice]['text'];
        if ($notice === 'email_failed') {
            $mail_error = $this->consume_last_mail_error();
            if ($mail_error !== '') {
                $text .= ' Détail technique : ' . $mail_error;
            }
        }

        printf(
            '<div class="gca-notice gca-notice-%s">%s</div>',
            esc_attr($messages[$notice]['type']),
            esc_html($text)
        );
    }

    private function render_dashboard(): void
    {
        global $wpdb;

        $classes = GCA_Database::table('classes');
        $students = GCA_Database::table('students');
        $tasks = GCA_Database::table('tasks');
        $disciplinary_exclusions = GCA_Database::table('disciplinary_exclusions');
        $task_type_filter = isset($_GET['task_type']) ? sanitize_key(wp_unslash($_GET['task_type'])) : '';
        $task_type_options = $this->pending_task_type_options();
        if ($task_type_filter !== '' && !array_key_exists($task_type_filter, $task_type_options)) {
            $task_type_filter = '';
        }

        $class_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$classes} WHERE status = 'active'");
        $student_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$students} WHERE archived_at IS NULL");
        $task_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tasks} WHERE status = 'pending'");
        $executed_action_count = $this->executed_action_count();
        $missing_trainer_email_count = $this->trainer_message_missing_email_count();
        $missing_trainer_email_students = $this->students_missing_trainer_email();
        $today = wp_date('Y-m-d');
        $exclusions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT x.id AS exclusion_id, s.id, s.first_name, s.last_name, s.trainer_company, x.start_date, x.end_date, x.notes, c.id AS class_id, c.name AS class_name
                 FROM {$disciplinary_exclusions} x
                 INNER JOIN {$students} s ON s.id = x.student_id
                 INNER JOIN {$classes} c ON c.id = s.class_id
                 WHERE s.archived_at IS NULL
                   AND (x.start_date IS NULL OR x.start_date <= %s)
                   AND (x.end_date IS NULL OR x.end_date >= %s)
                 ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC",
                $today,
                $today
            )
        );
        $legacy_exclusions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 0 AS exclusion_id, s.id, s.first_name, s.last_name, s.trainer_company, s.exclusion_start AS start_date, s.exclusion_end AS end_date, '' AS notes, c.id AS class_id, c.name AS class_name
                 FROM {$students} s
                 INNER JOIN {$classes} c ON c.id = s.class_id
                 WHERE s.lateness_status = 'exclusion_confirmed'
                   AND s.archived_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM {$disciplinary_exclusions} x WHERE x.student_id = s.id
                   )
                   AND (s.exclusion_start IS NULL OR s.exclusion_start <= %s)
                   AND (s.exclusion_end IS NULL OR s.exclusion_end >= %s)
                 ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC",
                $today,
                $today
            )
        );
        $exclusions = array_merge($exclusions, $legacy_exclusions);
        usort(
            $exclusions,
            static function (object $left, object $right): int {
                $class_compare = strnatcasecmp((string) $left->class_name, (string) $right->class_name);
                if ($class_compare !== 0) {
                    return $class_compare;
                }

                $last_compare = strnatcasecmp((string) $left->last_name, (string) $right->last_name);
                if ($last_compare !== 0) {
                    return $last_compare;
                }

                return strnatcasecmp((string) $left->first_name, (string) $right->first_name);
            }
        );
        $exclusion_student_ids = array();
        foreach ($exclusions as $exclusion) {
            $exclusion_student_ids[(int) $exclusion->id] = true;
        }
        $exclusion_count = count($exclusion_student_ids);

        $where = "WHERE t.status = 'pending' AND s.archived_at IS NULL";
        if ($task_type_filter !== '') {
            $where .= $wpdb->prepare(' AND t.task_type = %s', $task_type_filter);
        }

        $pending = $wpdb->get_results(
            "SELECT t.*, s.first_name, s.last_name, s.trainer_company, s.contract_status, s.contract_break_date, s.lateness_count, s.absence_count, s.absence_rate, s.absence_justified_count, s.removal_count, s.comment_count, c.id AS class_id, c.name AS class_name
             FROM {$tasks} t
             INNER JOIN {$students} s ON s.id = t.student_id
             INNER JOIN {$classes} c ON c.id = s.class_id
             {$where}
             ORDER BY t.created_at ASC"
        );
        $pending_student_ids = array();
        foreach ($pending as $task) {
            $pending_student_ids[] = (int) $task->student_id;
        }
        $history_by_student = $this->recent_action_history_by_student($pending_student_ids, 4);
        $absence_justification_students = $this->absence_justification_student_options();
        ?>
        <section class="gca-stats">
            <a class="gca-stat-card" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes'))); ?>"><strong><?php echo esc_html((string) $class_count); ?></strong><span>Classes actives</span></a>
            <a class="gca-stat-card" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'students'))); ?>"><strong><?php echo esc_html((string) $student_count); ?></strong><span>Apprentis actifs</span></a>
            <a class="gca-stat-card" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard')) . '#gca-situations'); ?>"><strong><?php echo esc_html((string) $task_count); ?></strong><span>Actions à traiter</span></a>
            <a class="gca-stat-card" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'actions'))); ?>"><strong><?php echo esc_html((string) $executed_action_count); ?></strong><span>Actions déjà traitées</span></a>
            <a class="gca-stat-card" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard')) . '#gca-exclusions'); ?>"><strong><?php echo esc_html((string) $exclusion_count); ?></strong><span>Exclusions en cours</span></a>
            <a class="gca-stat-card gca-stat-card-warning" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard')) . '#gca-missing-trainer-emails'); ?>"><strong><?php echo esc_html((string) $missing_trainer_email_count); ?></strong><span>E-mails responsables manquants</span></a>
        </section>

        <section class="gca-panel" id="gca-missing-trainer-emails">
            <div class="gca-panel-title">
                <div>
                    <h2>Fiches sans e-mail de responsable</h2>
                    <p class="gca-muted"><?php echo esc_html((string) $missing_trainer_email_count); ?> fiche<?php echo $missing_trainer_email_count > 1 ? 's' : ''; ?> à compléter.</p>
                </div>
            </div>

            <?php if (!$missing_trainer_email_students) : ?>
                <p class="gca-empty">Toutes les fiches avec employeur actif ont un e-mail de responsable utilisable.</p>
            <?php else : ?>
                <div class="gca-table-wrap">
                    <table class="gca-table">
                        <thead>
                            <tr>
                                <th>Apprenti</th>
                                <th>Classe</th>
                                <th>Formateur responsable</th>
                                <th>Entreprise</th>
                                <th>E-mail apprenti</th>
                                <th>Fiche</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missing_trainer_email_students as $student) : ?>
                                <tr>
                                    <td>
                                        <?php echo $this->student_link_with_company($student, true); ?>
                                    </td>
                                    <td><?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?></td>
                                    <td><?php echo esc_html(trim((string) $student->trainer_name) !== '' ? (string) $student->trainer_name : '-'); ?></td>
                                    <td><?php echo esc_html(trim((string) $student->trainer_company) !== '' ? (string) $student->trainer_company : '-'); ?></td>
                                    <td><?php echo esc_html(trim((string) $student->email) !== '' ? (string) $student->email : '-'); ?></td>
                                    <td class="gca-table-actions">
                                        <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'student_id' => (int) $student->id))); ?>">Compléter</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="gca-panel gca-panel-spaced" id="gca-absence-justification">
            <div class="gca-panel-title">
                <h2>Ajouter un justificatif d’absence</h2>
            </div>

            <form class="gca-form gca-form-wide gca-absence-justification-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gca_justify_student_absence'); ?>
                <input type="hidden" name="action" value="gca_justify_student_absence">
                <div class="gca-form-grid">
                    <label>Apprenti
                        <select name="student_id" required>
                            <option value="">Choisir un apprenti</option>
                            <?php foreach ($absence_justification_students as $student) : ?>
                                <option value="<?php echo esc_attr((string) (int) $student->id); ?>">
                                    <?php echo esc_html($this->absence_justification_student_label($student)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Jour
                        <input type="date" name="absence_date" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required>
                    </label>
                    <label>Périodes d’absence à justifier
                        <input type="number" min="0.01" step="0.01" name="absence_justified_delta" placeholder="0" required>
                    </label>
                </div>
                <button class="gca-button gca-button-primary" type="submit">Enregistrer le justificatif</button>
            </form>
        </section>

        <section class="gca-panel gca-panel-spaced" id="gca-situations">
            <div class="gca-panel-title">
                <h2>Situations à traiter</h2>
            </div>

            <form class="gca-filter" method="get">
                <?php $this->hidden_page_fields(array('gca_tab' => 'dashboard')); ?>
                <select name="task_type">
                    <option value="">Tous les types de problèmes</option>
                    <?php foreach ($task_type_options as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($task_type_filter, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="gca-button" type="submit">Filtrer</button>
                <?php if ($task_type_filter !== '') : ?>
                    <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard')) . '#gca-situations'); ?>">Réinitialiser</a>
                <?php endif; ?>
            </form>

            <?php if (!$pending) : ?>
                <p class="gca-empty">Aucune situation en attente<?php echo $task_type_filter !== '' ? esc_html(' pour ce type de problème') : ''; ?>.</p>
            <?php else : ?>
                <div class="gca-table-wrap">
                    <table class="gca-table">
                        <thead>
                            <tr>
                                <th>Apprenti</th>
                                <th>Classe</th>
                                <th>Situation</th>
                                <th>Indicateurs</th>
                                <th>Déjà fait</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $task) : ?>
                                <?php
                                $task_without_employer = $this->student_without_employer($task);
                                $absence_periods_to_justify = $this->actionable_absence_count($task);
                                $can_justify_absence_from_task = $this->task_can_be_justified_without_email($task) && $absence_periods_to_justify > 0;
                                ?>
                                <tr>
                                    <td>
                                        <?php echo $this->student_link_with_company($task); ?>
                                    </td>
                                    <td><?php echo $this->class_link((int) $task->class_id, (string) $task->class_name); ?></td>
                                    <td>
                                        <div class="gca-task-summary">
                                            <strong><?php echo esc_html($this->task_type_label($task->task_type)); ?></strong>
                                            <small><?php echo esc_html($task->message); ?></small>
                                            <span class="gca-task-reason">
                                                <span>Cause</span>
                                                <?php echo esc_html($this->pending_task_reason($task)); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ((string) $task->task_type === 'contract_break_review') : ?>
                                            <span class="gca-metric gca-metric-alert">Rupture</span>
                                            <span class="gca-metric">Contrôle <?php echo esc_html($this->display_date_only((string) $task->threshold_value)); ?></span>
                                        <?php else : ?>
                                            <span class="gca-metric <?php echo esc_attr($this->task_metric_class($task, 'lateness')); ?>">Retards <?php echo esc_html((string) $this->lateness_total_for_message($task)); ?></span>
                                            <span class="gca-metric <?php echo esc_attr($this->task_metric_class($task, 'absence')); ?>">Périodes abs. <?php echo esc_html($this->format_decimal($task->absence_count)); ?></span>
                                            <?php if ($this->absence_justified_count($task) > 0) : ?>
                                                <span class="gca-metric gca-metric-muted">Justifiées <?php echo esc_html($this->format_decimal($this->absence_justified_count($task))); ?></span>
                                                <span class="gca-metric <?php echo esc_attr($this->task_metric_class($task, 'absence')); ?>">À traiter <?php echo esc_html($this->format_decimal($this->actionable_absence_count($task))); ?></span>
                                            <?php endif; ?>
                                            <span class="gca-metric <?php echo esc_attr($this->task_metric_class($task, 'absence')); ?>">Taux abs. <?php echo esc_html($this->format_decimal($this->actionable_absence_rate($task))); ?>%</span>
                                            <span class="gca-metric <?php echo esc_attr($this->task_metric_class($task, 'removal')); ?>">Mises à la porte <?php echo esc_html((string) (int) $task->removal_count); ?></span>
                                            <span class="gca-metric">Commentaires <?php echo esc_html((string) (int) ($task->comment_count ?? 0)); ?></span>
                                        <?php endif; ?>
                                        <?php if ($task_without_employer && (string) $task->task_type !== 'contract_break_review') : ?>
                                            <span class="gca-badge gca-badge-danger" title="<?php echo esc_attr($this->contract_break_alert_text($task)); ?>"><?php echo esc_html($this->contract_status_label((string) ($task->contract_status ?? 'active'))); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $this->render_action_history_summary($history_by_student[(int) $task->student_id] ?? array()); ?>
                                    </td>
                                    <td class="gca-actions">
                                        <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard', 'gca_action' => 'prepare_email', 'task_id' => (int) $task->id))); ?>">
                                            <?php echo esc_html($this->dashboard_task_action_label($task)); ?>
                                        </a>
                                        <?php if ($can_justify_absence_from_task) : ?>
                                            <form class="gca-justify-absence-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Justifier les périodes indiquées sans envoyer d’e-mail ? Ce justificatif daté sera conservé après les prochains imports.">
                                                <?php wp_nonce_field('gca_justify_absence_task_' . (int) $task->id); ?>
                                                <input type="hidden" name="action" value="gca_justify_absence_task">
                                                <input type="hidden" name="task_id" value="<?php echo esc_attr((string) (int) $task->id); ?>">
                                                <label class="gca-justify-absence-field">
                                                    <span>Jour de l’absence</span>
                                                    <input type="date" name="absence_date" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required>
                                                </label>
                                                <label class="gca-justify-absence-field">
                                                    <span><?php echo esc_html($this->absence_justification_period_label($task)); ?></span>
                                                    <input type="number" min="0.01" max="<?php echo esc_attr($this->format_decimal($absence_periods_to_justify)); ?>" step="0.01" name="absence_justified_delta" placeholder="<?php echo esc_attr('Max ' . $this->format_decimal($absence_periods_to_justify)); ?>" required>
                                                </label>
                                                <button class="gca-button" type="submit"><?php echo esc_html($this->absence_justification_submit_label($task)); ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <a class="gca-icon-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard', 'gca_action' => 'edit_task', 'task_id' => (int) $task->id))); ?>" title="Modifier l’action">✎</a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('gca_mark_task_done_' . (int) $task->id); ?>
                                            <input type="hidden" name="action" value="gca_mark_task_done">
                                            <input type="hidden" name="task_id" value="<?php echo esc_attr((string) (int) $task->id); ?>">
                                            <button class="gca-icon-button" type="submit" title="Marquer traité">&#10003;</button>
                                        </form>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Supprimer cette action à traiter ?">
                                            <?php wp_nonce_field('gca_delete_task_' . (int) $task->id); ?>
                                            <input type="hidden" name="action" value="gca_delete_task">
                                            <input type="hidden" name="task_id" value="<?php echo esc_attr((string) (int) $task->id); ?>">
                                            <button class="gca-icon-button gca-button-danger" type="submit" title="Supprimer l’action">×</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="gca-panel gca-panel-spaced" id="gca-exclusions">
            <div class="gca-panel-title">
                <h2>Exclusions en cours</h2>
            </div>

            <?php if (!$exclusions) : ?>
                <p class="gca-empty">Aucune exclusion en cours.</p>
            <?php else : ?>
                <div class="gca-table-wrap">
                    <table class="gca-table">
                        <thead>
                            <tr>
                                <th>Apprenti</th>
                                <th>Classe</th>
                                <th>Période</th>
                                <th>Durée</th>
                                <th>Fiche</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exclusions as $student) : ?>
                                <tr>
                                    <td><?php echo $this->student_link_with_company($student); ?></td>
                                    <td><?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?></td>
                                    <td><?php echo esc_html($this->disciplinary_exclusion_period_label($student)); ?></td>
                                    <td><?php echo esc_html($this->disciplinary_exclusion_duration_label($student)); ?></td>
                                    <td><a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'student_id' => (int) $student->id))); ?>">Ouvrir</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function recent_action_history_by_student(array $student_ids, int $limit = 4, bool $include_updates = false): array
    {
        global $wpdb;

        $student_ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));
        $history = array();
        foreach ($student_ids as $student_id) {
            $history[$student_id] = array();
        }

        if (!$student_ids) {
            return $history;
        }

        $id_list = implode(',', $student_ids);
        $row_limit = max($limit * max(1, count($student_ids)) * 2, $limit);
        $communications_table = GCA_Database::table('communications');
        $events_table = GCA_Database::table('events');

        $communications = $wpdb->get_results(
            "SELECT * FROM {$communications_table}
             WHERE student_id IN ({$id_list})
             ORDER BY sent_at DESC
             LIMIT " . (int) $row_limit
        );

        foreach ($communications as $communication) {
            $student_id = (int) $communication->student_id;
            if (!isset($history[$student_id])) {
                continue;
            }
            $history[$student_id][] = $this->action_entry_from_communication($communication);
        }

        $event_filter = $include_updates
            ? "event_type <> 'communication_sent'"
            : "event_type IN ('warning_confirmed', 'exclusion_confirmed', 'detention_no_show', 'absence_justified', 'task_resolved', 'disciplinary_warning_recorded', 'disciplinary_warning_removed', 'disciplinary_exclusion_recorded', 'disciplinary_exclusion_updated', 'disciplinary_exclusion_deleted', 'expulsion_recorded', 'expulsion_removed', 'wellbeing_council_recorded', 'wellbeing_council_removed', 'contract_break_recorded', 'contract_break_updated', 'contract_break_closed')";
        $events = $wpdb->get_results(
            "SELECT * FROM {$events_table}
             WHERE student_id IN ({$id_list}) AND {$event_filter}
             ORDER BY created_at DESC
             LIMIT " . (int) $row_limit
        );

        foreach ($events as $event) {
            $student_id = (int) $event->student_id;
            if (!isset($history[$student_id])) {
                continue;
            }
            $history[$student_id][] = $this->action_entry_from_event($event);
        }

        foreach ($history as $student_id => $entries) {
            usort(
                $entries,
                static function (array $left, array $right): int {
                    return (int) $right['timestamp'] <=> (int) $left['timestamp'];
                }
            );
            $history[$student_id] = array_slice($entries, 0, $limit);
        }

        return $history;
    }

    private function action_entry_from_communication(object $communication): array
    {
        $date = (string) $communication->sent_at;
        $recipients = $this->communication_recipients_summary($communication);
        $summary = trim((string) $communication->subject);

        return array(
            'source' => 'communication',
            'id' => (int) $communication->id,
            'label' => $this->communication_type_label((string) $communication->communication_type),
            'date' => $date,
            'timestamp' => strtotime($date) ?: 0,
            'summary' => $summary !== '' ? $summary : 'E-mail envoyé.',
            'meta' => $recipients,
            'download_url' => !empty($communication->attachment_path)
                ? wp_nonce_url(admin_url('admin-post.php?action=gca_download_pdf&communication_id=' . (int) $communication->id), 'gca_download_pdf_' . (int) $communication->id)
                : '',
            'communication_type' => (string) $communication->communication_type,
        );
    }

    private function action_entry_from_event(object $event): array
    {
        $date = (string) $event->created_at;

        return array(
            'source' => 'event',
            'id' => (int) $event->id,
            'label' => $this->event_type_label((string) $event->event_type),
            'date' => $date,
            'timestamp' => strtotime($date) ?: 0,
            'summary' => $this->event_detail($event),
            'meta' => '',
            'download_url' => '',
        );
    }

    private function render_action_history_summary(array $items): void
    {
        if (!$items) {
            echo '<span class="gca-muted">Aucune action enregistrée.</span>';
            return;
        }

        echo '<ul class="gca-action-history">';
        foreach ($items as $item) {
            echo '<li>';
            echo '<strong>' . esc_html((string) $item['label']) . '</strong>';
            echo '<span>' . esc_html($this->display_date((string) $item['date'])) . '</span>';
            if (!empty($item['summary'])) {
                echo '<small>' . esc_html((string) $item['summary']) . '</small>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    private function render_action_history_timeline(array $items): void
    {
        if (!$items) {
            echo '<p class="gca-empty">Aucune action enregistrée.</p>';
            return;
        }

        echo '<div class="gca-timeline">';
        foreach ($items as $item) {
            echo '<article>';
            echo '<strong>' . esc_html((string) $item['label']) . '</strong>';
            echo '<time>' . esc_html($this->display_date((string) $item['date'])) . '</time>';
            if (!empty($item['summary'])) {
                echo '<p>' . esc_html((string) $item['summary']) . '</p>';
            }
            if (!empty($item['meta'])) {
                echo '<p class="gca-muted">' . esc_html((string) $item['meta']) . '</p>';
            }
            if (!empty($item['download_url'])) {
                echo '<a href="' . esc_url((string) $item['download_url']) . '">Télécharger le PDF</a>';
            }
            echo '</article>';
        }
        echo '</div>';
    }

    private function student_link(int $student_id, string $label, string $class = ''): string
    {
        $label = trim($label);
        if ($student_id <= 0 || $label === '') {
            return esc_html($label !== '' ? $label : '-');
        }

        $class_attr = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';

        return sprintf(
            '<a%s href="%s" target="_blank" rel="noopener">%s</a>',
            $class_attr,
            esc_url($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id))),
            esc_html($label)
        );
    }

    private function student_link_with_company(object $student, bool $last_name_first = false): string
    {
        $student_id = (int) ($student->student_id ?? $student->id ?? 0);
        $label = $this->student_display_name($student, $last_name_first);
        $company = $this->company_label_for_student($student);
        $output = $this->student_link($student_id, $label);

        if ($company !== '') {
            $output .= ' <span class="gca-muted">- ' . esc_html($company) . '</span>';
        }

        return $output;
    }

    private function class_link(int $class_id, string $label, string $class = ''): string
    {
        $label = trim($label);
        if ($class_id <= 0 || $label === '') {
            return esc_html($label !== '' ? $label : '-');
        }

        $class_attr = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';

        return sprintf(
            '<a%s href="%s">%s</a>',
            $class_attr,
            esc_url($this->page_url(array('gca_tab' => 'classes', 'class_id' => $class_id))),
            esc_html($label)
        );
    }

    private function student_display_name(object $student, bool $last_name_first = false): string
    {
        $first_name = trim((string) ($student->first_name ?? ''));
        $last_name = trim((string) ($student->last_name ?? ''));

        return $last_name_first
            ? trim($last_name . ' ' . $first_name)
            : trim($first_name . ' ' . $last_name);
    }

    private function company_label_for_student(object $student): string
    {
        if ($this->student_without_employer($student)) {
            return '';
        }

        return trim((string) ($student->trainer_company ?? ''));
    }

    private function name_with_company_label(string $name, string $company): string
    {
        $name = trim($name);
        $company = trim($company);

        if ($name === '') {
            return $company;
        }

        return $company !== '' ? $name . ' - ' . $company : $name;
    }

    private function trainer_company_address_label(object $record): string
    {
        $address = trim((string) ($record->company_address ?? $record->trainer_company_address ?? ''));
        $zip = trim((string) ($record->company_zip ?? $record->trainer_company_zip ?? ''));
        $city = trim((string) ($record->company_city ?? $record->trainer_company_city ?? ''));
        $locality = trim($zip . ' ' . $city);

        return trim(implode(', ', array_filter(array($address, $locality))));
    }

    private function student_class_label(object $student): string
    {
        $class_code = $this->class_code_for_student($student);

        return $class_code !== '' ? $class_code : trim((string) ($student->class_name ?? ''));
    }

    private function absence_justification_student_options(): array
    {
        global $wpdb;

        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $rows = $wpdb->get_results(
            "SELECT s.id, s.first_name, s.last_name, s.absence_count, s.absence_rate, s.absence_justified_count, c.name AS class_name, c.school_year
             FROM {$students} s
             INNER JOIN {$classes} c ON c.id = s.class_id
             WHERE s.archived_at IS NULL
             ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC"
        );

        return is_array($rows) ? $rows : array();
    }

    private function absence_justification_student_label(object $student): string
    {
        return trim(implode(' - ', array_filter(array(
            $this->student_class_label($student),
            $this->student_display_name($student, true),
        ))));
    }

    private function verification_filter_value(string $value): string
    {
        $value = sanitize_key($value);

        return in_array($value, array('verified', 'unverified'), true) ? $value : '';
    }

    private function stored_verified_at($value): string
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '0000-00-00 00:00:00' ? $value : '';
    }

    private function is_verified_record(object $record): bool
    {
        return $this->stored_verified_at($record->verified_at ?? '') !== '';
    }

    private function verification_badge(object $record): string
    {
        $verified_at = $this->stored_verified_at($record->verified_at ?? '');
        if ($verified_at !== '') {
            return sprintf(
                '<span class="gca-badge" title="%s">%s</span>',
                esc_attr('Vérifié le ' . $this->display_date($verified_at)),
                esc_html('Vérifié')
            );
        }

        return sprintf(
            '<span class="gca-badge gca-badge-warning" title="%s">%s</span>',
            esc_attr('Données à vérifier'),
            esc_html('À vérifier')
        );
    }

    private function student_manual_editable_fields(): array
    {
        return array(
            'class_id',
            'last_name',
            'first_name',
            'date_of_birth',
            'gender',
            'phone',
            'email',
            'trainer_id',
            'trainer_name',
            'trainer_company',
            'trainer_company_address',
            'trainer_company_zip',
            'trainer_company_city',
            'trainer_email',
            'trainer_phone',
            'legal_rep1_name',
            'legal_rep1_email',
            'legal_rep1_phone',
            'legal_rep2_name',
            'legal_rep2_email',
            'legal_rep2_phone',
            'contract_status',
            'contract_break_date',
            'disciplinary_warning_received',
            'disciplinary_warning_date',
            'expulsion_received',
            'expulsion_date',
            'wellbeing_council_convoked',
            'wellbeing_council_date',
            'admin_notes',
        );
    }

    private function student_quick_manual_fields(): array
    {
        return array(
            'gender',
            'email',
            'phone',
            'trainer_id',
            'trainer_name',
            'trainer_company',
            'trainer_company_address',
            'trainer_company_zip',
            'trainer_company_city',
            'trainer_email',
            'trainer_phone',
        );
    }

    private function trainer_manual_editable_fields(): array
    {
        return array(
            'name',
            'company',
            'company_address',
            'company_zip',
            'company_city',
            'email',
            'phone',
            'salutation',
        );
    }

    private function changed_manual_fields(array $before, array $after, array $allowed_fields): array
    {
        if (!$before) {
            return $this->non_empty_manual_fields($after, $allowed_fields);
        }

        $allowed = array_fill_keys($allowed_fields, true);
        $changed = array();

        foreach ($after as $field => $value) {
            if (empty($allowed[$field])) {
                continue;
            }

            $current = $before[$field] ?? '';
            if ($this->manual_compare_value($current) !== $this->manual_compare_value($value)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private function non_empty_manual_fields(array $data, array $allowed_fields): array
    {
        $allowed = array_fill_keys($allowed_fields, true);
        $fields = array();

        foreach ($data as $field => $value) {
            if (empty($allowed[$field])) {
                continue;
            }

            if ($this->manual_compare_value($value) !== '') {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function manual_compare_value($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function add_manual_fields_lock(array &$data, array &$formats, array $before, array $allowed_fields, bool $verified): void
    {
        $manual_fields = $verified
            ? $allowed_fields
            : $this->changed_manual_fields($before, $data, $allowed_fields);

        if (!$manual_fields) {
            return;
        }

        $manual_fields_json = GCA_Database::merge_manual_fields($before['manual_fields_json'] ?? '', $manual_fields);
        if ($this->manual_compare_value($before['manual_fields_json'] ?? '') === $manual_fields_json) {
            return;
        }

        $data['manual_fields_json'] = $manual_fields_json;
        $formats[] = '%s';
    }

    private function render_students_quick_edit(): void
    {
        global $wpdb;

        $this->sync_trainers_from_students();

        $classes_table = GCA_Database::table('classes');
        $students_table = GCA_Database::table('students');
        $selected_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
        $search = isset($_GET['gca_search']) ? sanitize_text_field(wp_unslash($_GET['gca_search'])) : '';
        $gender_filter = isset($_GET['gender_filter']) ? sanitize_key(wp_unslash($_GET['gender_filter'])) : '';
        if (!in_array($gender_filter, array('male', 'female', 'unknown'), true)) {
            $gender_filter = '';
        }
        $verification_filter = isset($_GET['verification_filter'])
            ? $this->verification_filter_value((string) wp_unslash($_GET['verification_filter']))
            : '';

        $trainers = $this->trainer_select_options();
        $trainers_by_id = array();
        foreach ($trainers as $trainer) {
            $trainers_by_id[(int) $trainer->id] = $trainer;
        }

        $classes = $wpdb->get_results("SELECT * FROM {$classes_table} WHERE status = 'active' ORDER BY name ASC");
        $where = array("s.archived_at IS NULL", "c.status = 'active'");
        $params = array();

        if ($selected_class_id > 0) {
            $where[] = 's.class_id = %d';
            $params[] = $selected_class_id;
        }

        if ($gender_filter === 'unknown') {
            $where[] = "(s.gender = '' OR s.gender IS NULL)";
        } elseif ($gender_filter !== '') {
            $where[] = 's.gender = %s';
            $params[] = $gender_filter;
        }

        if ($verification_filter === 'verified') {
            $where[] = "(s.verified_at IS NOT NULL AND s.verified_at <> '0000-00-00 00:00:00')";
        } elseif ($verification_filter === 'unverified') {
            $where[] = "(s.verified_at IS NULL OR s.verified_at = '0000-00-00 00:00:00')";
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(s.last_name LIKE %s OR s.first_name LIKE %s OR s.email LIKE %s OR s.trainer_name LIKE %s OR s.trainer_company LIKE %s OR s.trainer_company_address LIKE %s OR s.trainer_company_city LIKE %s OR s.trainer_email LIKE %s)';
            for ($i = 0; $i < 8; $i++) {
                $params[] = $like;
            }
        }

        $query = "SELECT s.*, c.id AS class_id, c.name AS class_name, c.school_year
                  FROM {$students_table} s
                  INNER JOIN {$classes_table} c ON c.id = s.class_id
                  WHERE " . implode(' AND ', $where) . '
                  ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC';
        if ($params) {
            $query = $wpdb->prepare($query, $params);
        }
        $students = $wpdb->get_results($query);
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <h2>Apprentis</h2>
                    <p class="gca-muted"><?php echo esc_html(count($students) . ' fiche(s) affichée(s).'); ?> Les corrections rapides mettent directement à jour les fiches élèves, uniquement pour les champs visibles dans ce tableau.</p>
                </div>
                <a class="gca-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gca_export_students'), 'gca_export_students')); ?>">Exporter les apprentis</a>
            </div>

            <form class="gca-filter gca-filter-wide" method="get">
                <?php $this->hidden_page_fields(array('gca_tab' => 'students')); ?>
                <select name="class_id">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $class) : ?>
                        <option value="<?php echo esc_attr((string) (int) $class->id); ?>" <?php selected($selected_class_id, (int) $class->id); ?>>
                            <?php echo esc_html($class->name . ' - ' . $class->school_year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="gender_filter">
                    <option value="">Tous les genres</option>
                    <option value="unknown" <?php selected($gender_filter, 'unknown'); ?>>À vérifier</option>
                    <option value="male" <?php selected($gender_filter, 'male'); ?>>Homme</option>
                    <option value="female" <?php selected($gender_filter, 'female'); ?>>Femme</option>
                </select>
                <select name="verification_filter">
                    <option value="">Toutes les fiches</option>
                    <option value="verified" <?php selected($verification_filter, 'verified'); ?>>Vérifiées</option>
                    <option value="unverified" <?php selected($verification_filter, 'unverified'); ?>>Non vérifiées</option>
                </select>
                <input type="search" name="gca_search" value="<?php echo esc_attr($search); ?>" placeholder="Nom, prénom, e-mail ou formateur">
                <button class="gca-button" type="submit">Filtrer</button>
                <?php if ($selected_class_id > 0 || $gender_filter !== '' || $verification_filter !== '' || $search !== '') : ?>
                    <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'students'))); ?>">Réinitialiser</a>
                <?php endif; ?>
            </form>

            <?php if (!$students) : ?>
                <p class="gca-empty">Aucun apprenti ne correspond à ces filtres.</p>
            <?php else : ?>
                <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_save_student_quick_edit'); ?>
                    <input type="hidden" name="action" value="gca_save_student_quick_edit">
                    <input type="hidden" name="return_class_id" value="<?php echo esc_attr((string) $selected_class_id); ?>">
                    <input type="hidden" name="return_gender_filter" value="<?php echo esc_attr($gender_filter); ?>">
                    <input type="hidden" name="return_verification_filter" value="<?php echo esc_attr($verification_filter); ?>">
                    <input type="hidden" name="return_search" value="<?php echo esc_attr($search); ?>">
                    <div class="gca-bulk-actions">
                        <label>Action groupée
                            <select name="bulk_action">
                                <option value="">Choisir une action</option>
                                <option value="mark_verified">Marquer comme vérifiés</option>
                                <option value="mark_unverified">Marquer comme à vérifier</option>
                                <option value="gender_male">Genre : homme</option>
                                <option value="gender_female">Genre : femme</option>
                                <option value="gender_unknown">Genre : à vérifier</option>
                            </select>
                        </label>
                        <button class="gca-button" type="submit">Appliquer aux sélectionnés</button>
                        <span class="gca-muted" data-gca-selected-count>Aucune sélection</span>
                    </div>
                    <div class="gca-table-wrap">
                        <table class="gca-table gca-students-quick-table">
                            <thead>
                                <tr>
                                    <th class="gca-row-select">
                                        <input type="checkbox" data-gca-bulk-select-all aria-label="Sélectionner tous les apprentis affichés">
                                    </th>
                                    <th>Photo</th>
                                    <th>Apprenti</th>
                                    <th>Classe</th>
                                    <th>Vérification</th>
                                    <th>Genre</th>
                                    <th>E-mail élève</th>
                                    <th>Téléphone élève</th>
                                    <th>Responsable</th>
                                    <th>E-mail du responsable</th>
                                    <th>Fiche</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student) : ?>
                                    <?php $student_id = (int) $student->id; ?>
                                    <?php $selected_trainer_id = $this->student_selected_trainer_id($student, $trainers); ?>
                                    <?php $selected_trainer = $selected_trainer_id > 0 ? ($trainers_by_id[$selected_trainer_id] ?? null) : null; ?>
                                    <?php $trainer_email = $selected_trainer ? (string) $selected_trainer->email : (string) $student->trainer_email; ?>
                                    <?php $student_without_employer = $this->student_without_employer($student); ?>
                                    <tr>
                                        <td class="gca-row-select">
                                            <input type="checkbox" name="selected_student_ids[]" value="<?php echo esc_attr((string) $student_id); ?>" data-gca-bulk-row aria-label="<?php echo esc_attr('Sélectionner ' . $this->student_display_name($student)); ?>">
                                        </td>
                                        <td><?php echo $this->student_photo_markup($student, 'thumbnail'); ?></td>
                                        <td class="gca-student-quick-name">
                                            <input type="hidden" name="student_ids[]" value="<?php echo esc_attr((string) $student_id); ?>">
                                            <?php echo $this->student_link_with_company($student, true); ?>
                                        </td>
                                        <td><?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?></td>
                                        <td>
                                            <?php echo $this->verification_badge($student); ?>
                                            <label class="gca-check">
                                                <input type="checkbox" name="students[<?php echo esc_attr((string) $student_id); ?>][verified]" value="1" <?php checked($this->is_verified_record($student)); ?>>
                                                Vérifié
                                            </label>
                                        </td>
                                        <td>
                                            <select name="students[<?php echo esc_attr((string) $student_id); ?>][gender]" aria-label="<?php echo esc_attr('Genre de ' . $this->student_display_name($student)); ?>">
                                                <option value="" <?php selected((string) $student->gender, ''); ?>>À vérifier</option>
                                                <option value="male" <?php selected((string) $student->gender, 'male'); ?>>Homme</option>
                                                <option value="female" <?php selected((string) $student->gender, 'female'); ?>>Femme</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="email" name="students[<?php echo esc_attr((string) $student_id); ?>][email]" value="<?php echo esc_attr((string) $student->email); ?>" aria-label="<?php echo esc_attr('E-mail élève de ' . $this->student_display_name($student)); ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="students[<?php echo esc_attr((string) $student_id); ?>][phone]" value="<?php echo esc_attr((string) $student->phone); ?>" aria-label="<?php echo esc_attr('Téléphone élève de ' . $this->student_display_name($student)); ?>">
                                        </td>
                                        <td>
                                            <?php if ($student_without_employer) : ?>
                                                <span class="gca-badge gca-badge-warning">Sans employeur actif</span>
                                            <?php else : ?>
                                                <select name="students[<?php echo esc_attr((string) $student_id); ?>][trainer_id]" data-gca-trainer-select aria-label="<?php echo esc_attr('Formateur responsable de ' . $this->student_display_name($student)); ?>">
                                                    <option value="0" data-email="" data-name="" data-company="" data-company-address="" data-company-zip="" data-company-city="" data-phone="">Aucun responsable</option>
                                                    <?php foreach ($trainers as $trainer) : ?>
                                                        <option
                                                            value="<?php echo esc_attr((string) (int) $trainer->id); ?>"
                                                            data-name="<?php echo esc_attr((string) $trainer->name); ?>"
                                                            data-company="<?php echo esc_attr((string) $trainer->company); ?>"
                                                            data-company-address="<?php echo esc_attr((string) ($trainer->company_address ?? '')); ?>"
                                                            data-company-zip="<?php echo esc_attr((string) ($trainer->company_zip ?? '')); ?>"
                                                            data-company-city="<?php echo esc_attr((string) ($trainer->company_city ?? '')); ?>"
                                                            data-email="<?php echo esc_attr((string) $trainer->email); ?>"
                                                            data-phone="<?php echo esc_attr((string) $trainer->phone); ?>"
                                                            <?php selected($selected_trainer_id, (int) $trainer->id); ?>
                                                        >
                                                            <?php echo esc_html($this->trainer_option_label($trainer)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span data-gca-trainer-email-display><?php echo esc_html(!$student_without_employer && is_email($trainer_email) ? $trainer_email : '-'); ?></span>
                                        </td>
                                        <td class="gca-table-actions">
                                            <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id))); ?>" target="_blank" rel="noopener">Ouvrir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="gca-actions gca-savebar">
                        <button class="gca-button gca-button-primary" type="submit">Mettre à jour les fiches élèves</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_trainers(): void
    {
        global $wpdb;

        $this->sync_trainers_from_students();

        $trainer_id = isset($_GET['trainer_id']) ? (int) $_GET['trainer_id'] : 0;
        if ($trainer_id > 0) {
            $this->render_trainer_detail($trainer_id);
            return;
        }

        $trainers_table = GCA_Database::table('trainers');
        $students_table = GCA_Database::table('students');
        $search = isset($_GET['gca_search']) ? sanitize_text_field(wp_unslash($_GET['gca_search'])) : '';
        $verification_filter = isset($_GET['verification_filter'])
            ? $this->verification_filter_value((string) wp_unslash($_GET['verification_filter']))
            : '';
        $where = '1 = 1';
        $params = array();

        if ($verification_filter === 'verified') {
            $where .= " AND (t.verified_at IS NOT NULL AND t.verified_at <> '0000-00-00 00:00:00')";
        } elseif ($verification_filter === 'unverified') {
            $where .= " AND (t.verified_at IS NULL OR t.verified_at = '0000-00-00 00:00:00')";
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (t.name LIKE %s OR t.company LIKE %s OR t.company_address LIKE %s OR t.company_zip LIKE %s OR t.company_city LIKE %s OR t.email LIKE %s OR t.phone LIKE %s)';
            $params = array($like, $like, $like, $like, $like, $like, $like);
        }

        $query = "SELECT t.*, COUNT(s.id) AS student_count
                  FROM {$trainers_table} t
                  LEFT JOIN {$students_table} s ON s.trainer_id = t.id AND s.archived_at IS NULL AND COALESCE(s.contract_status, 'active') NOT IN ('broken', 'free_candidate')
                  WHERE {$where}
                  GROUP BY t.id
                  ORDER BY t.name ASC, t.company ASC, t.email ASC";
        if ($params) {
            $query = $wpdb->prepare($query, $params);
        }

        $trainers = $wpdb->get_results($query);
        ?>
        <section class="gca-panel gca-trainers-overview">
            <div class="gca-panel-title">
                <div>
                    <h2>Formateurs responsables</h2>
                    <p class="gca-muted">Une fiche par responsable. Les coordonnées enregistrées ici sont reprises dans les fiches des apprentis rattachés.</p>
                </div>
                <div class="gca-panel-actions">
                    <a class="gca-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gca_export_trainers'), 'gca_export_trainers')); ?>">Exporter les formateurs</a>
                    <button
                        class="gca-button gca-button-primary"
                        type="button"
                        data-gca-toggle-target="gca-add-trainer-panel"
                        data-gca-toggle-label-open="Masquer le formulaire"
                        aria-controls="gca-add-trainer-panel"
                        aria-expanded="false"
                    >
                        Ajouter un formateur responsable
                    </button>
                </div>
            </div>

            <div id="gca-add-trainer-panel" class="gca-collapsible-panel" hidden>
                <div class="gca-form-section gca-form-section-inset">
                    <h3>Ajouter un formateur responsable</h3>
                    <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('gca_save_trainer'); ?>
                        <input type="hidden" name="action" value="gca_save_trainer">
                        <input type="hidden" name="trainer_id" value="0">
                        <div class="gca-form-grid">
                            <label>Nom du formateur responsable
                                <input type="text" name="name" placeholder="Nom et prénom">
                            </label>
                            <label>Entreprise
                                <input type="text" name="company" placeholder="Entreprise formatrice">
                            </label>
                            <label>Adresse
                                <input type="text" name="company_address" placeholder="Rue et numéro">
                            </label>
                            <label>E-mail du responsable
                                <input type="email" name="email" placeholder="prenom.nom@example.ch">
                            </label>
                            <label>NPA
                                <input type="text" name="company_zip" placeholder="1000">
                            </label>
                            <label>Localité
                                <input type="text" name="company_city" placeholder="Lausanne">
                            </label>
                            <label>Téléphone
                                <input type="text" name="phone" placeholder="+41...">
                            </label>
                            <label>Formule d’appel
                                <input type="text" name="salutation" placeholder="Madame, Monsieur">
                            </label>
                            <label class="gca-check">
                                <input type="checkbox" name="trainer_verified" value="1">
                                Données vérifiées
                            </label>
                        </div>
                        <div class="gca-actions gca-savebar">
                            <button class="gca-button gca-button-primary" type="submit">Enregistrer le responsable</button>
                        </div>
                    </form>
                </div>
            </div>

            <form class="gca-filter gca-filter-wide" method="get">
                <?php $this->hidden_page_fields(array('gca_tab' => 'trainers')); ?>
                <select name="verification_filter">
                    <option value="">Tous les formateurs</option>
                    <option value="verified" <?php selected($verification_filter, 'verified'); ?>>Vérifiés</option>
                    <option value="unverified" <?php selected($verification_filter, 'unverified'); ?>>Non vérifiés</option>
                </select>
                <input type="search" name="gca_search" value="<?php echo esc_attr($search); ?>" placeholder="Nom, entreprise, adresse, e-mail ou téléphone">
                <button class="gca-button" type="submit">Filtrer</button>
                <?php if ($verification_filter !== '' || $search !== '') : ?>
                    <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'trainers'))); ?>">Réinitialiser</a>
                <?php endif; ?>
            </form>

            <?php if (!$trainers) : ?>
                <p class="gca-empty">Aucun formateur responsable enregistré pour le moment.</p>
            <?php else : ?>
                <div class="gca-table-wrap">
                    <table class="gca-table">
                        <thead>
                            <tr>
                                <th>Responsable</th>
                                <th>Vérification</th>
                                <th>Adresse entreprise</th>
                                <th>E-mail du responsable</th>
                                <th>Téléphone</th>
                                <th>Formule d’appel</th>
                                <th>Apprentis</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trainers as $trainer) : ?>
                                <tr>
                                    <td><?php echo $this->trainer_link((int) $trainer->id, $this->name_with_company_label($this->trainer_display_name($trainer), (string) $trainer->company)); ?></td>
                                    <td><?php echo $this->verification_badge($trainer); ?></td>
                                    <td><?php echo esc_html($this->trainer_company_address_label($trainer) ?: '-'); ?></td>
                                    <td><?php echo esc_html(is_email((string) $trainer->email) ? (string) $trainer->email : '-'); ?></td>
                                    <td><?php echo esc_html(trim((string) $trainer->phone) !== '' ? (string) $trainer->phone : '-'); ?></td>
                                    <td><?php echo esc_html(trim((string) $trainer->salutation) !== '' ? (string) $trainer->salutation : '-'); ?></td>
                                    <td><span class="gca-badge"><?php echo esc_html((string) (int) $trainer->student_count); ?></span></td>
                                    <td class="gca-table-actions">
                                        <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'trainers', 'trainer_id' => (int) $trainer->id))); ?>">Ouvrir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_trainer_detail(int $trainer_id): void
    {
        $trainer = $this->trainer_by_id($trainer_id);
        if (!$trainer) {
            echo '<p class="gca-empty">Formateur introuvable.</p>';
            return;
        }

        $attached_students = $this->trainer_students($trainer_id);
        $available_students = $this->students_available_for_trainer($trainer_id);
        ?>
        <section class="gca-grid gca-grid-detail">
            <div class="gca-panel">
                <div class="gca-panel-title">
                    <div>
                        <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'trainers'))); ?>">&larr; Formateurs</a>
                        <h2>
                            <?php echo esc_html($this->trainer_display_name($trainer)); ?>
                            <?php echo $this->verification_badge($trainer); ?>
                        </h2>
                        <p class="gca-muted">
                            <?php
                            $trainer_meta = array_filter(array(
                                trim((string) ($trainer->company ?? '')),
                                $this->trainer_company_address_label($trainer),
                            ));
                            echo esc_html(($trainer_meta ? implode(' · ', $trainer_meta) . ' · ' : '') . count($attached_students) . ' apprenti(s) rattaché(s).');
                            ?>
                        </p>
                    </div>
                </div>

                <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_save_trainer'); ?>
                    <input type="hidden" name="action" value="gca_save_trainer">
                    <input type="hidden" name="trainer_id" value="<?php echo esc_attr((string) $trainer_id); ?>">
                    <div class="gca-form-grid">
                        <label>Nom du formateur responsable
                            <input type="text" name="name" value="<?php echo esc_attr((string) $trainer->name); ?>">
                        </label>
                        <label>Entreprise
                            <input type="text" name="company" value="<?php echo esc_attr((string) $trainer->company); ?>">
                        </label>
                        <label>Adresse
                            <input type="text" name="company_address" value="<?php echo esc_attr((string) ($trainer->company_address ?? '')); ?>">
                        </label>
                        <label>NPA
                            <input type="text" name="company_zip" value="<?php echo esc_attr((string) ($trainer->company_zip ?? '')); ?>">
                        </label>
                        <label>Localité
                            <input type="text" name="company_city" value="<?php echo esc_attr((string) ($trainer->company_city ?? '')); ?>">
                        </label>
                        <label>E-mail du responsable
                            <input type="email" name="email" value="<?php echo esc_attr((string) $trainer->email); ?>">
                        </label>
                        <label>Téléphone
                            <input type="text" name="phone" value="<?php echo esc_attr((string) $trainer->phone); ?>">
                        </label>
                    </div>
                    <label>Formule d’appel
                        <input type="text" name="salutation" value="<?php echo esc_attr((string) $trainer->salutation); ?>" placeholder="Madame, Monsieur">
                    </label>
                    <label class="gca-check">
                        <input type="checkbox" name="trainer_verified" value="1" <?php checked($this->is_verified_record($trainer)); ?>>
                        Données vérifiées
                    </label>
                    <p class="gca-muted">En enregistrant cette fiche, le nom du responsable, l’entreprise, son adresse et l’e-mail du responsable sont recopiés sur toutes les fiches apprentis rattachées.</p>
                    <button class="gca-button gca-button-primary" type="submit">Mettre à jour le responsable et les fiches liées</button>
                </form>
            </div>

            <aside class="gca-side">
                <div class="gca-panel">
                    <div class="gca-panel-title"><h2>Apprentis rattachés</h2></div>
                    <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('gca_save_trainer_assignments_' . $trainer_id); ?>
                        <input type="hidden" name="action" value="gca_save_trainer_assignments">
                        <input type="hidden" name="trainer_id" value="<?php echo esc_attr((string) $trainer_id); ?>">

                        <?php if (!$attached_students) : ?>
                            <p class="gca-empty">Aucun apprenti n’est rattaché à ce formateur.</p>
                        <?php else : ?>
                            <div class="gca-table-wrap">
                                <table class="gca-table gca-table-compact">
                                    <thead>
                                        <tr>
                                            <th>Apprenti</th>
                                            <th>Classe</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attached_students as $student) : ?>
                                            <tr>
                                                <td><?php echo $this->student_link_with_company($student, true); ?></td>
                                                <td><?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?></td>
                                                <td>
                                                    <label class="gca-check">
                                                        <input type="checkbox" name="remove_student_ids[]" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                                                        Retirer
                                                    </label>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <label>Attribuer d’autres apprentis
                            <select name="add_student_ids[]" multiple size="8">
                                <?php foreach ($available_students as $student) : ?>
                                    <option value="<?php echo esc_attr((string) (int) $student->id); ?>">
                                        <?php echo esc_html($this->student_assignment_option_label($student)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php if (!$available_students) : ?>
                            <p class="gca-empty">Aucun autre apprenti actif disponible.</p>
                        <?php endif; ?>
                        <button class="gca-button" type="submit">Mettre à jour les rattachements</button>
                    </form>
                </div>
            </aside>
        </section>
        <?php
    }

    private function render_classes(): void
    {
        global $wpdb;

        if (isset($_GET['class_id'])) {
            $this->render_class_detail((int) $_GET['class_id']);
            return;
        }

        $classes_table = GCA_Database::table('classes');
        $students_table = GCA_Database::table('students');
        $classes = $wpdb->get_results(
            "SELECT c.*, COUNT(s.id) AS student_count
             FROM {$classes_table} c
             LEFT JOIN {$students_table} s ON s.class_id = c.id AND s.archived_at IS NULL
             GROUP BY c.id
             ORDER BY c.school_year DESC, c.name ASC"
        );
        ?>
        <section class="gca-grid gca-grid-2" id="gca-classes-list">
            <div class="gca-panel">
                <div class="gca-panel-title"><h2>Classes</h2></div>
                <?php if (!$classes) : ?>
                    <p class="gca-empty">Aucune classe pour le moment.</p>
                <?php else : ?>
                    <div class="gca-class-list">
                        <?php foreach ($classes as $class) : ?>
                            <a class="gca-class-row" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'class_id' => (int) $class->id))); ?>">
                                <span>
                                    <strong><?php echo esc_html($class->name); ?></strong>
                                    <small><?php echo esc_html($class->school_year); ?> &middot; <?php echo esc_html($this->status_label($class->status)); ?></small>
                                </span>
                                <span class="gca-badge"><?php echo esc_html((string) (int) $class->student_count); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="gca-panel">
                <div class="gca-panel-title"><h2>Ajouter une classe</h2></div>
                <form class="gca-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_save_class'); ?>
                    <input type="hidden" name="action" value="gca_save_class">
                    <label>Nom de la classe
                        <input type="text" name="name" placeholder="CLLOG-C2b" required>
                    </label>
                    <label>Année scolaire
                        <input type="text" name="school_year" value="<?php echo esc_attr(GCA_Database::get_settings()['school_year']); ?>" required>
                    </label>
                    <label>Statut
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="archived">Archivée</option>
                        </select>
                    </label>
                    <button class="gca-button gca-button-primary" type="submit">Enregistrer</button>
                </form>
            </div>
        </section>
        <?php
    }

    private function render_class_detail(int $class_id): void
    {
        global $wpdb;

        $classes = GCA_Database::table('classes');
        $students = GCA_Database::table('students');
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$classes} WHERE id = %d", $class_id));

        if (!$class) {
            echo '<p class="gca-empty">Classe introuvable.</p>';
            return;
        }

        $search = isset($_GET['gca_search']) ? sanitize_text_field(wp_unslash($_GET['gca_search'])) : '';
        $verification_filter = isset($_GET['verification_filter'])
            ? $this->verification_filter_value((string) wp_unslash($_GET['verification_filter']))
            : '';
        $where = 'WHERE class_id = %d AND archived_at IS NULL';
        $params = array($class_id);

        if ($verification_filter === 'verified') {
            $where .= " AND (verified_at IS NOT NULL AND verified_at <> '0000-00-00 00:00:00')";
        } elseif ($verification_filter === 'unverified') {
            $where .= " AND (verified_at IS NULL OR verified_at = '0000-00-00 00:00:00')";
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (last_name LIKE %s OR first_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$students} {$where} ORDER BY last_name ASC, first_name ASC",
            $params
        );
        $rows = $wpdb->get_results($query);
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes'))); ?>">&larr; Classes</a>
                    <h2><?php echo esc_html($class->name); ?></h2>
                </div>
                <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'student_id' => 0, 'class_id' => $class_id))); ?>">Ajouter un apprenti</a>
            </div>

            <form class="gca-form gca-form-wide" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gca_save_class'); ?>
                <input type="hidden" name="action" value="gca_save_class">
                <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
                <div class="gca-form-grid">
                    <label>Nom de la classe
                        <input type="text" name="name" value="<?php echo esc_attr($class->name); ?>" required>
                    </label>
                    <label>Année scolaire
                        <input type="text" name="school_year" value="<?php echo esc_attr($class->school_year); ?>" required>
                    </label>
                    <label>Statut
                        <select name="status">
                            <option value="active" <?php selected($class->status, 'active'); ?>>Active</option>
                            <option value="archived" <?php selected($class->status, 'archived'); ?>>Archivée</option>
                        </select>
                    </label>
                </div>
                <button class="gca-button gca-button-primary" type="submit">Mettre à jour la classe</button>
            </form>

            <form class="gca-inline-form gca-danger-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Supprimer cette classe, ses élèves et toutes les actions associées ?">
                <?php wp_nonce_field('gca_delete_class_' . (int) $class_id); ?>
                <input type="hidden" name="action" value="gca_delete_class">
                <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
                <button class="gca-button gca-button-danger" type="submit">Supprimer la classe</button>
            </form>

            <form class="gca-filter" method="get">
                <?php $this->hidden_page_fields(array('gca_tab' => 'classes', 'class_id' => $class_id)); ?>
                <select name="verification_filter">
                    <option value="">Toutes les fiches</option>
                    <option value="verified" <?php selected($verification_filter, 'verified'); ?>>Vérifiées</option>
                    <option value="unverified" <?php selected($verification_filter, 'unverified'); ?>>Non vérifiées</option>
                </select>
                <input type="search" name="gca_search" value="<?php echo esc_attr($search); ?>" placeholder="Rechercher un apprenti">
                <button class="gca-button" type="submit">Filtrer</button>
                <?php if ($verification_filter !== '' || $search !== '') : ?>
                    <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'class_id' => $class_id))); ?>">Réinitialiser</a>
                <?php endif; ?>
            </form>

            <div class="gca-table-wrap">
                <table class="gca-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Nom</th>
                            <th>Statut</th>
                            <th>Retards</th>
                            <th>Absences</th>
                            <th>Mises à la porte</th>
                            <th>Commentaires</th>
                            <th>Formateurs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows) : ?>
                            <tr><td colspan="9">Aucun apprenti dans cette vue.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $student) : ?>
                            <tr>
                                <td><?php echo $this->student_photo_markup($student, 'thumbnail'); ?></td>
                                <td>
                                    <?php echo $this->student_link_with_company($student, true); ?>
                                </td>
                                <td>
                                    <?php echo $this->student_status_badge($student); ?>
                                    <?php echo $this->verification_badge($student); ?>
                                </td>
                                <td><?php echo esc_html((string) (int) $student->lateness_count); ?></td>
                                <td>
                                    <?php echo esc_html($this->absence_periods_label($student->absence_count)); ?>
                                    <?php if ($this->absence_justified_count($student) > 0) : ?>
                                        <br><span class="gca-muted">Justifiées : <?php echo esc_html($this->format_decimal($this->absence_justified_count($student))); ?></span>
                                        <br><span class="gca-muted">À traiter : <?php echo esc_html($this->format_decimal($this->actionable_absence_count($student))); ?></span>
                                    <?php endif; ?>
                                    <br><span class="gca-muted"><?php echo esc_html($this->format_decimal($student->absence_rate)); ?>%</span>
                                </td>
                                <td><?php echo esc_html((string) (int) $student->removal_count); ?></td>
                                <td><?php echo esc_html((string) (int) ($student->comment_count ?? 0)); ?></td>
                                <td>
                                    <?php
                                    $trainer_id = (int) ($student->trainer_id ?? 0);
                                    if ($this->student_without_employer($student)) {
                                        echo '<span class="gca-muted">Aucun employeur actif</span>';
                                    } else {
                                        echo '<strong>Responsable</strong> ';
                                        echo $trainer_id > 0
                                            ? $this->trainer_link($trainer_id, $this->name_with_company_label((string) $student->trainer_name, (string) $student->trainer_company))
                                            : esc_html(trim((string) $student->trainer_name) !== '' ? (string) $student->trainer_name : '-');
                                    }
                                    ?>
                                </td>
                                <td class="gca-table-actions">
                                    <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'student_id' => (int) $student->id))); ?>">Modifier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
    }

    private function render_student_detail(int $student_id): void
    {
        global $wpdb;

        $this->sync_trainers_from_students();

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');

        $is_new = $student_id === 0;
        $selected_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;

        if ($is_new) {
            $student = (object) array(
                'id' => 0,
                'class_id' => $selected_class_id,
                'last_name' => '',
                'first_name' => '',
                'date_of_birth' => '',
                'gender' => '',
                'photo_path' => '',
                'phone' => '',
                'email' => '',
                'trainer_id' => 0,
                'trainer_name' => '',
                'trainer_company' => '',
                'trainer_company_address' => '',
                'trainer_company_zip' => '',
                'trainer_company_city' => '',
                'trainer_email' => '',
                'trainer_phone' => '',
                'field_trainer_name' => '',
                'field_trainer_email' => '',
                'field_trainer_phone' => '',
                'contract_status' => 'active',
                'contract_break_date' => null,
                'legal_rep1_name' => '',
                'legal_rep1_email' => '',
                'legal_rep1_phone' => '',
                'legal_rep2_name' => '',
                'legal_rep2_email' => '',
                'legal_rep2_phone' => '',
                'absence_count' => 0,
                'absence_rate' => 0,
                'absence_justified_count' => 0,
                'lateness_count' => 0,
                'removal_count' => 0,
                'comment_count' => 0,
                'lateness_status' => 'none',
                'warning_confirmed_at' => '',
                'exclusion_start' => '',
                'exclusion_end' => '',
                'disciplinary_warning_received' => 0,
                'disciplinary_warning_date' => '',
                'expulsion_received' => 0,
                'expulsion_date' => '',
                'wellbeing_council_convoked' => 0,
                'wellbeing_council_date' => '',
                'verified_at' => '',
                'verified_by' => 0,
                'manual_fields_json' => '',
                'admin_notes' => '',
            );
            $class = null;
        } else {
            $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$students_table} WHERE id = %d", $student_id));
            if (!$student) {
                echo '<p class="gca-empty">Apprenti introuvable.</p>';
                return;
            }
            $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$classes_table} WHERE id = %d", (int) $student->class_id));
        }

        $classes = $wpdb->get_results("SELECT * FROM {$classes_table} WHERE status = 'active' ORDER BY name ASC");
        $trainers = $this->trainer_select_options();
        $selected_trainer_id = $this->student_selected_trainer_id($student, $trainers);
        $trainers_by_id = array();
        foreach ($trainers as $trainer_row) {
            $trainers_by_id[(int) $trainer_row->id] = $trainer_row;
        }
        $selected_trainer = $selected_trainer_id > 0 ? ($trainers_by_id[$selected_trainer_id] ?? null) : null;
        $trainer_name_value = $selected_trainer ? (string) $selected_trainer->name : (string) $student->trainer_name;
        $trainer_company_value = $selected_trainer ? (string) $selected_trainer->company : (string) $student->trainer_company;
        $trainer_company_address_value = $selected_trainer ? (string) ($selected_trainer->company_address ?? '') : (string) ($student->trainer_company_address ?? '');
        $trainer_company_zip_value = $selected_trainer ? (string) ($selected_trainer->company_zip ?? '') : (string) ($student->trainer_company_zip ?? '');
        $trainer_company_city_value = $selected_trainer ? (string) ($selected_trainer->company_city ?? '') : (string) ($student->trainer_company_city ?? '');
        $trainer_email_value = $selected_trainer ? (string) $selected_trainer->email : (string) $student->trainer_email;
        $trainer_phone_value = $selected_trainer ? (string) $selected_trainer->phone : (string) $student->trainer_phone;
        $contract_status = $this->contract_status_value((string) ($student->contract_status ?? 'active'));
        $contract_break_date = $this->display_date_only((string) ($student->contract_break_date ?? ''));
        $contract_follow_until = $this->contract_follow_until_label($student);
        $contract_review_date = $this->contract_review_date_label($student);
        $contract_responsible_email = $this->internal_followup_email();
        $action_history = array();
        $absence_justifications = array();
        $disciplinary_exclusions = array();
        $student_grades = array();
        if (!$is_new) {
            $action_history_by_student = $this->recent_action_history_by_student(array($student_id), 30, true);
            $action_history = $action_history_by_student[$student_id] ?? array();
            $absence_justifications = $this->absence_justifications_for_student($student_id);
            $disciplinary_exclusions = $this->disciplinary_exclusions_for_student($student_id);
            $student_grades = $this->student_grades_for_student($student_id);
        }
        ?>
        <section class="gca-grid gca-grid-detail">
            <div class="gca-panel">
                <div class="gca-panel-title">
                    <div>
                        <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'class_id' => (int) $student->class_id))); ?>">&larr; Retour</a>
                        <h2>
                            <?php echo $is_new ? 'Nouvel apprenti' : esc_html(trim($student->first_name . ' ' . $student->last_name)); ?>
                            <?php if (!$is_new) : ?>
                                <?php echo $this->verification_badge($student); ?>
                            <?php endif; ?>
                        </h2>
                        <?php if (!$is_new && $this->company_label_for_student($student) !== '') : ?>
                            <p class="gca-muted"><?php echo esc_html($this->company_label_for_student($student)); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_new) : ?>
                        <a class="gca-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gca_export_student&student_id=' . (int) $student->id), 'gca_export_student_' . (int) $student->id)); ?>">Exporter</a>
                    <?php endif; ?>
                </div>

                <form class="gca-form gca-form-wide" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_save_student'); ?>
                    <input type="hidden" name="action" value="gca_save_student">
                    <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">

                    <div class="gca-form-section">
                        <h3>Identité</h3>
                        <div class="gca-form-grid">
                            <label>Nom
                                <input type="text" name="last_name" value="<?php echo esc_attr($student->last_name); ?>" required>
                            </label>
                            <label>Prénom
                                <input type="text" name="first_name" value="<?php echo esc_attr($student->first_name); ?>" required>
                            </label>
                            <label>Genre
                                <select name="gender">
                                    <option value="">À vérifier</option>
                                    <option value="male" <?php selected((string) $student->gender, 'male'); ?>>Homme</option>
                                    <option value="female" <?php selected((string) $student->gender, 'female'); ?>>Femme</option>
                                </select>
                            </label>
                            <label>Classe
                                <select name="class_id" required>
                                    <option value="">Choisir</option>
                                    <?php foreach ($classes as $row) : ?>
                                        <option value="<?php echo esc_attr((string) (int) $row->id); ?>" <?php selected((int) $student->class_id, (int) $row->id); ?>>
                                            <?php echo esc_html($row->name . ' - ' . $row->school_year); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Date de naissance
                                <input type="text" name="date_of_birth" value="<?php echo esc_attr($this->display_date_only((string) $student->date_of_birth)); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                            </label>
                            <label>Photo
                                <input type="file" name="student_photo" accept="image/jpeg,image/png,image/webp">
                            </label>
                            <label class="gca-check">
                                <input type="checkbox" name="student_verified" value="1" <?php checked($this->is_verified_record($student)); ?>>
                                Données vérifiées
                            </label>
                        </div>
                        <?php if (!$is_new && !empty($student->photo_path)) : ?>
                            <div class="gca-student-photo-block">
                                <img src="<?php echo esc_url($this->student_photo_url((int) $student->id)); ?>" alt="<?php echo esc_attr(trim($student->first_name . ' ' . $student->last_name)); ?>">
                                <label class="gca-check">
                                    <input type="checkbox" name="remove_student_photo" value="1">
                                    Supprimer la photo
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="gca-form-section">
                        <h3>Coordonnées</h3>
                        <div class="gca-form-grid">
                            <label>Téléphone apprenti
                                <input type="text" name="phone" value="<?php echo esc_attr($student->phone); ?>">
                            </label>
                            <label>E-mail principal de l’élève
                                <input type="email" name="email" value="<?php echo esc_attr($student->email); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="gca-form-section">
                        <h3>Contrat de formation</h3>
                        <div class="gca-form-grid">
                            <label>Statut du contrat
                                <select name="contract_status">
                                    <option value="active" <?php selected($contract_status, 'active'); ?>>Actif</option>
                                    <option value="broken" <?php selected($contract_status, 'broken'); ?>>Rupture de contrat</option>
                                    <option value="free_candidate" <?php selected($contract_status, 'free_candidate'); ?>>Candidat libre</option>
                                </select>
                            </label>
                            <label>Date de rupture
                                <input type="text" name="contract_break_date" value="<?php echo esc_attr($contract_break_date); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                            </label>
                        </div>
                        <?php if ($contract_status === 'broken') : ?>
                            <p class="gca-muted">
                                Suivi école jusqu’au <?php echo esc_html($contract_follow_until ?: 'à calculer'); ?>.
                                Alerte de contrôle le <?php echo esc_html($contract_review_date ?: 'à calculer'); ?>.
                                Responsable durant cette période : <?php echo esc_html($contract_responsible_email ?: 'vous'); ?>.
                            </p>
                        <?php elseif ($contract_status === 'free_candidate') : ?>
                            <p class="gca-muted">Cet apprenti est candidat libre : aucune donnée employeur ou formateur n’est utilisée pour les communications, mais ses situations restent visibles dans À traiter.</p>
                        <?php else : ?>
                            <p class="gca-muted">Par défaut, l’apprenti est actif car il a un contrat de formation.</p>
                        <?php endif; ?>
                    </div>

                    <div class="gca-form-section">
                        <h3>Formateur responsable</h3>
                        <?php if ($this->student_without_employer($student)) : ?>
                            <p class="gca-muted">Ces coordonnées restent conservées pour l’historique, mais elles ne sont pas utilisées dans les communications tant que l’apprenti est en rupture ou candidat libre.</p>
                        <?php endif; ?>
                        <label>Formateur responsable enregistré
                            <select name="trainer_id" data-gca-trainer-select>
                                <option value="0" data-name="" data-company="" data-company-address="" data-company-zip="" data-company-city="" data-email="" data-phone="">Ne pas lier à un formateur enregistré</option>
                                <?php foreach ($trainers as $trainer_row) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) (int) $trainer_row->id); ?>"
                                        data-name="<?php echo esc_attr((string) $trainer_row->name); ?>"
                                        data-company="<?php echo esc_attr((string) $trainer_row->company); ?>"
                                        data-company-address="<?php echo esc_attr((string) ($trainer_row->company_address ?? '')); ?>"
                                        data-company-zip="<?php echo esc_attr((string) ($trainer_row->company_zip ?? '')); ?>"
                                        data-company-city="<?php echo esc_attr((string) ($trainer_row->company_city ?? '')); ?>"
                                        data-email="<?php echo esc_attr((string) $trainer_row->email); ?>"
                                        data-phone="<?php echo esc_attr((string) $trainer_row->phone); ?>"
                                        <?php selected($selected_trainer_id, (int) $trainer_row->id); ?>
                                    >
                                        <?php echo esc_html($this->trainer_option_label($trainer_row)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="gca-form-grid">
                            <label>Nom du responsable
                                <input type="text" name="trainer_name" value="<?php echo esc_attr($trainer_name_value); ?>" data-gca-trainer-field="name">
                            </label>
                            <label>Entreprise
                                <input type="text" name="trainer_company" value="<?php echo esc_attr($trainer_company_value); ?>" data-gca-trainer-field="company">
                            </label>
                            <label>Adresse entreprise
                                <input type="text" name="trainer_company_address" value="<?php echo esc_attr($trainer_company_address_value); ?>" data-gca-trainer-field="company_address">
                            </label>
                            <label>NPA entreprise
                                <input type="text" name="trainer_company_zip" value="<?php echo esc_attr($trainer_company_zip_value); ?>" data-gca-trainer-field="company_zip">
                            </label>
                            <label>Localité entreprise
                                <input type="text" name="trainer_company_city" value="<?php echo esc_attr($trainer_company_city_value); ?>" data-gca-trainer-field="company_city">
                            </label>
                            <label>E-mail du responsable
                                <input type="email" name="trainer_email" value="<?php echo esc_attr($trainer_email_value); ?>" data-gca-trainer-field="email">
                            </label>
                            <label>Téléphone du responsable
                                <input type="text" name="trainer_phone" value="<?php echo esc_attr($trainer_phone_value); ?>" data-gca-trainer-field="phone">
                            </label>
                        </div>
                        <p class="gca-muted">Pour modifier durablement les coordonnées d’un responsable enregistré, utilisez l’onglet Formateurs.</p>
                    </div>

                    <div class="gca-form-section">
                        <h3>Représentants légaux</h3>
                        <div class="gca-form-grid">
                            <label>Nom représentant 1
                                <input type="text" name="legal_rep1_name" value="<?php echo esc_attr($student->legal_rep1_name); ?>">
                            </label>
                            <label>E-mail représentant 1
                                <input type="email" name="legal_rep1_email" value="<?php echo esc_attr($student->legal_rep1_email); ?>">
                            </label>
                            <label>Téléphone représentant 1
                                <input type="text" name="legal_rep1_phone" value="<?php echo esc_attr($student->legal_rep1_phone); ?>">
                            </label>
                            <label>Nom représentant 2
                                <input type="text" name="legal_rep2_name" value="<?php echo esc_attr($student->legal_rep2_name); ?>">
                            </label>
                            <label>E-mail représentant 2
                                <input type="email" name="legal_rep2_email" value="<?php echo esc_attr($student->legal_rep2_email); ?>">
                            </label>
                            <label>Téléphone représentant 2
                                <input type="text" name="legal_rep2_phone" value="<?php echo esc_attr($student->legal_rep2_phone); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="gca-form-section">
                        <h3>Compteurs</h3>
                        <div class="gca-form-grid">
                            <label>Retards
                                <input type="number" min="0" name="lateness_count" value="<?php echo esc_attr((string) (int) $student->lateness_count); ?>">
                            </label>
                            <label>Nombre de périodes d’absence
                                <input type="number" min="0" step="0.01" name="absence_count" value="<?php echo esc_attr($this->format_decimal($student->absence_count)); ?>">
                            </label>
                            <label>Périodes justifiées dans le plugin
                                <input type="number" min="0" step="0.01" name="absence_justified_count" value="<?php echo esc_attr($this->format_decimal($student->absence_justified_count ?? 0)); ?>">
                            </label>
                            <label>Périodes non justifiées à traiter
                                <input type="text" value="<?php echo esc_attr($this->format_decimal($this->actionable_absence_count($student))); ?>" readonly>
                            </label>
                            <label>Taux d’absence (%)
                                <input type="number" min="0" step="0.01" name="absence_rate" value="<?php echo esc_attr($this->format_decimal($student->absence_rate)); ?>">
                            </label>
                            <label>Taux non justifié calculé (%)
                                <input type="text" value="<?php echo esc_attr($this->format_decimal($this->actionable_absence_rate($student))); ?>" readonly>
                            </label>
                            <label>Mises à la porte
                                <input type="number" min="0" name="removal_count" value="<?php echo esc_attr((string) (int) $student->removal_count); ?>">
                            </label>
                            <label>Commentaires
                                <input type="number" min="0" name="comment_count" value="<?php echo esc_attr((string) (int) ($student->comment_count ?? 0)); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="gca-form-section">
                        <h3>Suivi disciplinaire</h3>
                        <div class="gca-status-line">
                            <?php echo $this->student_status_badge($student); ?>
                            <span><?php echo esc_html($this->minor_status_label($student)); ?></span>
                        </div>
                        <div class="gca-form-grid">
                            <label class="gca-check">
                                <input type="checkbox" name="disciplinary_warning_received" value="1" <?php checked((int) ($student->disciplinary_warning_received ?? 0), 1); ?>>
                                Avertissement reçu
                            </label>
                            <label>Date avertissement
                                <input type="text" name="disciplinary_warning_date" value="<?php echo esc_attr($this->display_date_only((string) ($student->disciplinary_warning_date ?? ''))); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                            </label>
                            <label class="gca-check">
                                <input type="checkbox" name="wellbeing_council_convoked" value="1" <?php checked((int) ($student->wellbeing_council_convoked ?? 0), 1); ?>>
                                Convocation à un conseil de savoir-être
                            </label>
                            <label>Date conseil de savoir-être
                                <input type="text" name="wellbeing_council_date" value="<?php echo esc_attr($this->display_date_only((string) ($student->wellbeing_council_date ?? ''))); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                            </label>
                        </div>
                        <label>Notes internes
                            <textarea name="admin_notes" rows="4"><?php echo esc_textarea((string) $student->admin_notes); ?></textarea>
                        </label>
                    </div>

                    <button class="gca-button gca-button-primary" type="submit">Enregistrer la fiche</button>
                </form>

                <?php if (!$is_new) : ?>
                    <div class="gca-form-section">
                        <h3>Justificatifs d’absence</h3>
                        <?php if (!$absence_justifications) : ?>
                            <p class="gca-empty">Aucun justificatif d’absence enregistré.</p>
                        <?php else : ?>
                            <div class="gca-table-wrap">
                                <table class="gca-table gca-table-compact">
                                    <thead>
                                        <tr>
                                            <th>Jour</th>
                                            <th>Périodes</th>
                                            <th>Source</th>
                                            <th>Saisie</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($absence_justifications as $justification) : ?>
                                            <tr>
                                                <td><?php echo esc_html($this->display_date_only((string) $justification->absence_date)); ?></td>
                                                <td><?php echo esc_html($this->format_decimal($justification->period_count)); ?></td>
                                                <td><?php echo esc_html($this->absence_justification_source_label((string) $justification->source)); ?></td>
                                                <td><?php echo esc_html($this->display_date((string) $justification->created_at)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="gca-form-section">
                        <h3>Notes récupérées</h3>
                        <?php if (!$student_grades) : ?>
                            <p class="gca-empty">Aucune note récupérée pour cet apprenti.</p>
                        <?php else : ?>
                            <div class="gca-table-wrap">
                                <table class="gca-table gca-table-compact">
                                    <thead>
                                        <tr>
                                            <th>Évaluation</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_grades as $grade) : ?>
                                            <tr>
                                                <td><?php echo esc_html((string) $grade->assessment !== '' ? (string) $grade->assessment : '-'); ?></td>
                                                <td><strong><?php echo esc_html((string) $grade->grade_value); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="gca-form-section">
                    <h3>Exclusions</h3>
                    <?php if ($is_new) : ?>
                        <p class="gca-empty">Enregistrez la fiche avant d’ajouter une exclusion.</p>
                    <?php else : ?>
                        <?php if (!$disciplinary_exclusions) : ?>
                            <p class="gca-empty">Aucune exclusion enregistrée.</p>
                        <?php else : ?>
                            <div class="gca-table-wrap">
                                <table class="gca-table">
                                    <thead>
                                        <tr>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Durée</th>
                                            <th>Note</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($disciplinary_exclusions as $exclusion) : ?>
                                            <?php $edit_form_id = 'gca-exclusion-edit-' . (int) $exclusion->id; ?>
                                            <tr>
                                                <td>
                                                    <input form="<?php echo esc_attr($edit_form_id); ?>" type="text" name="exclusion_start" value="<?php echo esc_attr($this->display_date_only((string) $exclusion->start_date)); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                                                </td>
                                                <td>
                                                    <input form="<?php echo esc_attr($edit_form_id); ?>" type="text" name="exclusion_end" value="<?php echo esc_attr($this->display_date_only((string) $exclusion->end_date)); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                                                </td>
                                                <td><?php echo esc_html($this->disciplinary_exclusion_duration_label($exclusion)); ?></td>
                                                <td>
                                                    <input form="<?php echo esc_attr($edit_form_id); ?>" type="text" name="exclusion_notes" value="<?php echo esc_attr((string) $exclusion->notes); ?>">
                                                </td>
                                                <td class="gca-table-actions">
                                                    <form id="<?php echo esc_attr($edit_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                        <?php wp_nonce_field('gca_save_disciplinary_exclusion_' . (int) $student->id); ?>
                                                        <input type="hidden" name="action" value="gca_save_disciplinary_exclusion">
                                                        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                                                        <input type="hidden" name="exclusion_id" value="<?php echo esc_attr((string) (int) $exclusion->id); ?>">
                                                    </form>
                                                    <button form="<?php echo esc_attr($edit_form_id); ?>" class="gca-button" type="submit">Modifier</button>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Supprimer cette exclusion ?">
                                                        <?php wp_nonce_field('gca_delete_disciplinary_exclusion_' . (int) $exclusion->id); ?>
                                                        <input type="hidden" name="action" value="gca_delete_disciplinary_exclusion">
                                                        <input type="hidden" name="exclusion_id" value="<?php echo esc_attr((string) (int) $exclusion->id); ?>">
                                                        <button class="gca-button gca-button-danger" type="submit">Supprimer</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('gca_save_disciplinary_exclusion_' . (int) $student->id); ?>
                            <input type="hidden" name="action" value="gca_save_disciplinary_exclusion">
                            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                            <div class="gca-form-grid">
                                <label>Début exclusion
                                    <input type="text" name="exclusion_start" placeholder="JJ.MM.AAAA" inputmode="numeric">
                                </label>
                                <label>Fin exclusion
                                    <input type="text" name="exclusion_end" placeholder="JJ.MM.AAAA" inputmode="numeric">
                                </label>
                            </div>
                            <label>Note
                                <textarea name="exclusion_notes" rows="2"></textarea>
                            </label>
                            <button class="gca-button" type="submit">Ajouter l’exclusion</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="gca-side">
                <?php if (!$is_new) : ?>
                    <div class="gca-panel">
                        <div class="gca-panel-title"><h2>Procédure retards</h2></div>
                        <div class="gca-stack">
                            <p class="gca-muted">Statut actuel : <?php echo esc_html($this->lateness_status_label($student->lateness_status)); ?></p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gca-inline-form">
                                <?php wp_nonce_field('gca_confirm_warning_' . (int) $student->id); ?>
                                <input type="hidden" name="action" value="gca_confirm_warning">
                                <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                                <label>Date avertissement
                                    <input type="text" name="warning_date" value="<?php echo esc_attr($student->warning_confirmed_at ? $this->display_date_only(substr($student->warning_confirmed_at, 0, 10)) : wp_date('d.m.Y')); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                                </label>
                                <button class="gca-button" type="submit">Confirmer avertissement</button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gca-inline-form">
                                <?php wp_nonce_field('gca_save_exclusion_' . (int) $student->id); ?>
                                <input type="hidden" name="action" value="gca_save_exclusion">
                                <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                                <label>Début exclusion
                                    <input type="text" name="exclusion_start" value="<?php echo esc_attr($this->display_date_only((string) $student->exclusion_start)); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                                </label>
                                <label>Fin exclusion
                                    <input type="text" name="exclusion_end" value="<?php echo esc_attr($this->display_date_only((string) $student->exclusion_end)); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                                </label>
                                <button class="gca-button" type="submit">Enregistrer exclusion</button>
                            </form>
                        </div>
                    </div>

                    <div class="gca-panel">
                        <div class="gca-panel-title"><h2>Actions déjà entreprises</h2></div>
                        <?php $this->render_action_history_timeline($action_history); ?>
                    </div>

                    <div class="gca-panel gca-danger-panel">
                        <div class="gca-panel-title"><h2>Archivage</h2></div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Archiver cette fiche ?">
                            <?php wp_nonce_field('gca_archive_student_' . (int) $student->id); ?>
                            <input type="hidden" name="action" value="gca_archive_student">
                            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                            <button class="gca-button gca-button-danger" type="submit">Archiver l’apprenti</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Anonymiser définitivement cette fiche et supprimer ses PDF ?">
                            <?php wp_nonce_field('gca_anonymize_student_' . (int) $student->id); ?>
                            <input type="hidden" name="action" value="gca_anonymize_student">
                            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                            <button class="gca-button gca-button-danger" type="submit">Anonymiser la fiche</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Supprimer définitivement cet apprenti et toutes ses actions ?">
                            <?php wp_nonce_field('gca_delete_student_' . (int) $student->id); ?>
                            <input type="hidden" name="action" value="gca_delete_student">
                            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                            <button class="gca-button gca-button-danger" type="submit">Supprimer l’apprenti</button>
                        </form>
                    </div>
                <?php endif; ?>
            </aside>
        </section>
        <?php
    }

    private function render_imports(): void
    {
        global $wpdb;

        if (!empty($_GET['gca_import_token'])) {
            $this->render_import_mapping(sanitize_text_field(wp_unslash($_GET['gca_import_token'])));
            return;
        }

        if (!empty($_GET['gca_photo_import_token'])) {
            $this->render_photo_import_review(sanitize_text_field(wp_unslash($_GET['gca_photo_import_token'])));
            return;
        }

        $imports_table = GCA_Database::table('imports');
        $teachers_table = GCA_Database::table('teachers');
        $imports = $wpdb->get_results(
            "SELECT i.*, t.display_name AS teacher_name, t.user_email AS teacher_email
             FROM {$imports_table} i
             LEFT JOIN {$teachers_table} t ON t.id = i.teacher_id
             ORDER BY i.created_at DESC
             LIMIT 20"
        );
        ?>
        <section class="gca-grid gca-grid-2">
            <div class="gca-panel">
                <div class="gca-panel-title"><h2>Import Lagap-EP</h2></div>
                <p class="gca-muted">Depuis Lagap-EP : sélectionner les classes, cliquer sur Tout charger si nécessaire, puis Exporter - Export CSV.</p>
                <p class="gca-muted">Les champs corrigés ou validés manuellement dans Apprentis et Formateurs restent prioritaires et ne sont pas écrasés par les imports suivants.</p>
                <form class="gca-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_import_prepare'); ?>
                    <input type="hidden" name="action" value="gca_import_prepare">
                    <input type="hidden" name="import_type" value="lagap">
                    <input type="hidden" name="import_mode" value="replace">
                    <label>Export CSV ou XLSX Lagap-EP
                        <input type="file" name="gca_file" accept=".csv,.txt,.xlsx" required>
                    </label>
                    <button class="gca-button gca-button-primary" type="submit">Importer un export Lagap</button>
                </form>
            </div>

            <div class="gca-panel">
                <div class="gca-panel-title"><h2>Photos de classe</h2></div>
                <p class="gca-muted">Le PDF est d’abord analysé. Vous vérifiez ensuite chaque photo, le nom détecté dans le PDF et l’élève cible avant d’enregistrer les attributions.</p>
                <form class="gca-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_import_photos_pdf'); ?>
                    <input type="hidden" name="action" value="gca_import_photos_pdf">
                    <label>Liste de classe PDF avec photos
                        <input type="file" name="gca_photos_pdf" accept=".pdf,application/pdf" required>
                    </label>
                    <button class="gca-button gca-button-primary" type="submit">Analyser les photos</button>
                </form>
            </div>
        </section>

        <section class="gca-grid gca-grid-2 gca-panel-spaced">
            <div class="gca-panel">
                <div class="gca-panel-title"><h2>Autre import</h2></div>
                <form class="gca-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_import_prepare'); ?>
                    <input type="hidden" name="action" value="gca_import_prepare">
                    <label>Type de fichier
                        <select name="import_type" required>
                            <option value="students">Liste des apprentis</option>
                            <option value="tracking">Suivi périodique</option>
                        </select>
                    </label>
                    <label>Mode de compteur
                        <select name="import_mode" required>
                            <option value="replace">Remplacer les compteurs existants</option>
                            <option value="add">Additionner aux compteurs existants</option>
                        </select>
                    </label>
                    <label>Fichier CSV ou XLSX
                        <input type="file" name="gca_file" accept=".csv,.txt,.xlsx" required>
                    </label>
                    <button class="gca-button gca-button-primary" type="submit">Analyser le fichier</button>
                </form>
            </div>

            <div class="gca-panel">
                <div class="gca-panel-title"><h2>Import de notes</h2></div>
                <p class="gca-muted">Pour un export de l’interface dédiée aux notes. La classe, le nom et le prénom servent uniquement à retrouver l’apprenti ; seules l’évaluation et la note obtenue sont récupérées.</p>
                <form class="gca-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_import_prepare'); ?>
                    <input type="hidden" name="action" value="gca_import_prepare">
                    <input type="hidden" name="import_type" value="grades">
                    <input type="hidden" name="import_mode" value="replace">
                    <label>Export CSV ou XLSX des notes
                        <input type="file" name="gca_file" accept=".csv,.txt,.xlsx" required>
                    </label>
                    <button class="gca-button gca-button-primary" type="submit">Importer des notes</button>
                </form>
            </div>
        </section>

        <section class="gca-panel gca-panel-spaced">
            <div class="gca-panel-title"><h2>Historique des imports</h2></div>
            <?php if (!$imports) : ?>
                <p class="gca-empty">Aucun import réalisé.</p>
            <?php else : ?>
                <div class="gca-table-wrap">
                    <table class="gca-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Prof</th>
                                <th>Fichier</th>
                                <th>Type</th>
                                <th>Résultat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($imports as $import) : ?>
                                <?php
                                $import_errors = json_decode((string) $import->errors_json, true);
                                if (!is_array($import_errors)) {
                                    $import_errors = array();
                                }
                                $import_errors = array_filter(array_map('strval', $import_errors));
                                ?>
                                <tr>
                                    <td><?php echo esc_html($this->display_date($import->created_at)); ?></td>
                                    <td>
                                        <?php echo esc_html($import->teacher_name ?: ('Utilisateur #' . (int) $import->created_by)); ?>
                                        <?php if (!empty($import->teacher_email)) : ?><br><span class="gca-muted"><?php echo esc_html($import->teacher_email); ?></span><?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($import->source_filename); ?></td>
                                    <td><?php echo esc_html($this->import_type_label($import->import_type)); ?></td>
                                    <td>
                                        <?php echo esc_html((int) $import->processed_count . ' traitées'); ?>
                                        <?php if ((int) $import->skipped_count > 0) : ?> - <?php echo esc_html((int) $import->skipped_count . ' ignorées'); ?><?php endif; ?>
                                        <?php if ((int) $import->error_count > 0) : ?> - <span class="gca-error"><?php echo esc_html((int) $import->error_count . ' erreurs'); ?></span><?php endif; ?>
                                        <?php if (!empty($import->source_sha256)) : ?><br><span class="gca-muted">SHA-256 <?php echo esc_html(substr($import->source_sha256, 0, 12)); ?>...</span><?php endif; ?>
                                        <?php if ($import_errors) : ?>
                                            <details class="gca-import-details">
                                                <summary>Détails des ignorées</summary>
                                                <ul>
                                                    <?php foreach (array_slice($import_errors, 0, 8) as $import_error) : ?>
                                                        <li><?php echo esc_html($import_error); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_photo_import_review(string $token): void
    {
        $payload = get_transient('gca_photo_import_' . $token);
        if (
            !is_array($payload)
            || empty($payload['photos'])
            || !is_array($payload['photos'])
            || (int) ($payload['prepared_by'] ?? 0) !== get_current_user_id()
        ) {
            ?>
            <section class="gca-panel">
                <div class="gca-panel-title">
                    <div>
                        <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'imports'))); ?>">&larr; Imports</a>
                        <h2>Attribution des photos expirée</h2>
                    </div>
                </div>
                <p class="gca-empty">L’analyse temporaire du PDF n’est plus disponible. Relancez l’analyse du PDF pour attribuer les photos.</p>
            </section>
            <?php
            return;
        }

        $students = $this->photo_assignment_students();
        $students_by_class = array();
        $students_by_exact_name = array();
        foreach ($students as $student) {
            $class_label = trim((string) $student->class_name);
            if ($class_label === '') {
                $class_label = 'Sans classe';
            }

            $students_by_class[$class_label][] = $student;
            $key = $this->exact_photo_name_key($this->student_photo_import_display_name($student));
            if ($key !== '') {
                $students_by_exact_name[$key][] = $student;
            }
        }

        $photos = array_values($payload['photos']);
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'imports'))); ?>">&larr; Imports</a>
                    <h2>Vérifier l’attribution des photos</h2>
                    <p class="gca-muted"><?php echo esc_html(count($photos) . ' photo(s) extraite(s) de ' . (string) ($payload['filename'] ?? 'ce PDF') . '.'); ?></p>
                </div>
            </div>
            <p class="gca-muted">Contrôlez chaque ligne avant validation. Le plugin propose une fiche uniquement lorsque le prénom et le nom détectés dans le PDF correspondent exactement à une seule fiche apprenti. Vous pouvez choisir un autre élève si le système s’est trompé.</p>

            <?php if (!$students) : ?>
                <p class="gca-empty">Aucune fiche apprenti active n’est disponible. Importez d’abord les élèves depuis Lagap ou un CSV.</p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Enregistrer ces attributions ? Les photos existantes des fiches sélectionnées seront remplacées.">
                    <?php wp_nonce_field('gca_import_photos_commit_' . $token); ?>
                    <input type="hidden" name="action" value="gca_import_photos_commit">
                    <input type="hidden" name="photo_import_token" value="<?php echo esc_attr($token); ?>">
                    <div class="gca-table-wrap">
                        <table class="gca-table gca-photo-import-table">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Identité détectée dans le PDF</th>
                                    <th>Proposition exacte</th>
                                    <th>Attribution à enregistrer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($photos as $index => $photo) : ?>
                                    <?php
                                    $photo_name = $this->normalize_exact_photo_name((string) ($photo['name'] ?? ''));
                                    $photo_key = $this->exact_photo_name_key($photo_name);
                                    $candidates = $photo_key !== '' ? ($students_by_exact_name[$photo_key] ?? array()) : array();
                                    $suggested_student_id = count($candidates) === 1 ? (int) $candidates[0]->id : 0;
                                    $preview_url = wp_nonce_url(
                                        admin_url('admin-post.php?action=gca_photo_import_preview&token=' . rawurlencode($token) . '&index=' . (int) $index),
                                        'gca_photo_import_preview_' . $token . '_' . (int) $index
                                    );
                                    ?>
                                    <tr>
                                        <td>
                                            <img class="gca-photo-import-preview" src="<?php echo esc_url($preview_url); ?>" alt="<?php echo esc_attr($photo_name); ?>">
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($photo_name !== '' ? $photo_name : 'Nom illisible'); ?></strong>
                                            <br><span class="gca-muted">Texte lié à la photo extraite.</span>
                                        </td>
                                        <td>
                                            <?php if ($suggested_student_id > 0) : ?>
                                                <strong><?php echo $this->student_link((int) $candidates[0]->id, $this->student_display_name($candidates[0], true)); ?></strong>
                                                <?php if (!empty($candidates[0]->class_name)) : ?>
                                                    <br><span class="gca-muted"><?php echo $this->class_link((int) $candidates[0]->class_id, (string) $candidates[0]->class_name); ?></span>
                                                <?php endif; ?>
                                                <br><span class="gca-success-text">Prénom et nom identiques, une seule fiche trouvée.</span>
                                            <?php elseif (count($candidates) > 1) : ?>
                                                <span class="gca-error">Plusieurs fiches portent exactement ce prénom et ce nom.</span>
                                                <ul class="gca-photo-import-candidates">
                                                    <?php foreach (array_slice($candidates, 0, 4) as $candidate) : ?>
                                                        <li>
                                                            <?php echo $this->student_link((int) $candidate->id, $this->student_display_name($candidate, true)); ?>
                                                            <?php if (!empty($candidate->class_name)) : ?>
                                                                <span class="gca-muted">· <?php echo $this->class_link((int) $candidate->class_id, (string) $candidate->class_name); ?></span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <span class="gca-muted">Aucune proposition automatique. Choisissez manuellement si la photo doit être importée.</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="assignments[<?php echo esc_attr((string) $index); ?>]">
                                                <option value="0">Ne pas importer cette photo</option>
                                                <?php foreach ($students_by_class as $class_label => $class_students) : ?>
                                                    <optgroup label="<?php echo esc_attr((string) $class_label); ?>">
                                                        <?php foreach ($class_students as $student) : ?>
                                                            <option value="<?php echo esc_attr((string) (int) $student->id); ?>" <?php selected($suggested_student_id, (int) $student->id); ?>>
                                                                <?php echo esc_html((string) $student->class_name . ' - ' . (string) $student->last_name . ' ' . (string) $student->first_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="gca-actions">
                        <button class="gca-button gca-button-primary" type="submit">Enregistrer les attributions</button>
                        <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'imports'))); ?>">Annuler</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_executed_actions(): void
    {
        global $wpdb;

        $classes_table = GCA_Database::table('classes');
        $selected_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
        $classes = $wpdb->get_results("SELECT * FROM {$classes_table} ORDER BY status ASC, name ASC");
        $actions_by_class = $this->executed_actions_by_class($selected_class_id);
        $total = 0;
        foreach ($actions_by_class as $class_group) {
            $total += count($class_group['actions']);
        }
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <h2>Actions exécutées</h2>
                    <p class="gca-muted"><?php echo esc_html($total . ' action(s) enregistrée(s), regroupée(s) par classe.'); ?></p>
                </div>
            </div>

            <form class="gca-filter" method="get">
                <?php $this->hidden_page_fields(array('gca_tab' => 'actions')); ?>
                <select name="class_id">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $class) : ?>
                        <option value="<?php echo esc_attr((string) (int) $class->id); ?>" <?php selected($selected_class_id, (int) $class->id); ?>>
                            <?php echo esc_html($class->name . ' - ' . $class->school_year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="gca-button" type="submit">Filtrer</button>
            </form>

            <?php if (!$actions_by_class) : ?>
                <p class="gca-empty">Aucune action exécutée pour cette vue.</p>
            <?php else : ?>
                <div class="gca-class-action-list">
                    <?php foreach ($actions_by_class as $class_group) : ?>
                        <section class="gca-class-action-group">
                            <div class="gca-class-action-title">
                                <div>
                                    <h3><?php echo $this->class_link((int) $class_group['class_id'], (string) $class_group['class_name']); ?></h3>
                                    <p class="gca-muted"><?php echo esc_html($class_group['school_year'] . ' · ' . count($class_group['actions']) . ' action(s)'); ?></p>
                                </div>
                                <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'class_id' => (int) $class_group['class_id']))); ?>">Voir la classe</a>
                            </div>

                            <div class="gca-table-wrap">
                                <table class="gca-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Apprenti</th>
                                            <th>Action</th>
                                            <th>Détail</th>
                                            <th>Gestion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($class_group['actions'] as $action) : ?>
                                            <tr>
                                                <td><?php echo esc_html($this->display_date((string) $action['date'])); ?></td>
                                                <td>
                                                    <?php echo $this->student_link((int) $action['student_id'], (string) $action['student_name']); ?>
                                                    <?php if (!empty($action['student_company'])) : ?>
                                                        <span class="gca-muted">- <?php echo esc_html((string) $action['student_company']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo esc_html((string) $action['label']); ?></strong></td>
                                                <td>
                                                    <?php if (!empty($action['summary'])) : ?>
                                                        <p><?php echo esc_html((string) $action['summary']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($action['meta'])) : ?>
                                                        <p class="gca-muted"><?php echo esc_html((string) $action['meta']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($action['download_url'])) : ?>
                                                        <a href="<?php echo esc_url((string) $action['download_url']); ?>">Télécharger le PDF</a>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="gca-actions">
                                                    <?php if ($this->action_allows_detention_no_show($action)) : ?>
                                                        <form class="gca-inline-form gca-no-show-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Enregistrer que l’apprenti ne s’est pas présenté à la retenue ?">
                                                            <?php wp_nonce_field('gca_register_detention_no_show_' . (int) $action['id']); ?>
                                                            <input type="hidden" name="action" value="gca_register_detention_no_show">
                                                            <input type="hidden" name="communication_id" value="<?php echo esc_attr((string) (int) $action['id']); ?>">
                                                            <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $selected_class_id); ?>">
                                                            <input type="date" name="detention_no_show_date" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" title="Date de la retenue manquée" required>
                                                            <button class="gca-button" type="submit">Non présenté</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <a class="gca-icon-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'actions', 'gca_action' => 'edit_action', 'source' => (string) $action['source'], 'action_id' => (int) $action['id']))); ?>" title="Modifier">✎</a>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-gca-confirm="Supprimer cette action exécutée ?">
                                                        <?php wp_nonce_field('gca_delete_action_' . (string) $action['source'] . '_' . (int) $action['id']); ?>
                                                        <input type="hidden" name="action" value="gca_delete_action">
                                                        <input type="hidden" name="source" value="<?php echo esc_attr((string) $action['source']); ?>">
                                                        <input type="hidden" name="action_id" value="<?php echo esc_attr((string) (int) $action['id']); ?>">
                                                        <button class="gca-icon-button gca-button-danger" type="submit" title="Supprimer">×</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_task_edit(int $task_id): void
    {
        global $wpdb;

        $tasks = GCA_Database::table('tasks');
        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $task = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*, s.first_name, s.last_name, s.trainer_company, s.class_id, c.name AS class_name
                 FROM {$tasks} t
                 INNER JOIN {$students} s ON s.id = t.student_id
                 INNER JOIN {$classes} c ON c.id = s.class_id
                 WHERE t.id = %d",
                $task_id
            )
        );

        if (!$task) {
            echo '<p class="gca-empty">Action introuvable.</p>';
            return;
        }
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard'))); ?>">&larr; À traiter</a>
                    <h2>Modifier l’action à traiter</h2>
                    <p class="gca-muted">
                        <?php echo $this->student_link_with_company($task); ?>
                        ·
                        <?php echo $this->class_link((int) $task->class_id, (string) $task->class_name); ?>
                    </p>
                </div>
            </div>

            <form class="gca-form gca-form-wide" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gca_save_task_' . (int) $task->id); ?>
                <input type="hidden" name="action" value="gca_save_task">
                <input type="hidden" name="task_id" value="<?php echo esc_attr((string) (int) $task->id); ?>">
                <div class="gca-form-grid">
                    <label>Type
                        <select name="task_type">
                            <?php foreach (array('lateness_3', 'lateness_6', 'absence_unjustified_21', 'absence_rate_30', 'absence', 'removal', 'contract_break_review', 'dean_warning', 'dean_exclusion') as $type) : ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($task->task_type, $type); ?>><?php echo esc_html($this->task_type_label($type)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Seuil / numéro
                        <input type="text" name="threshold_value" value="<?php echo esc_attr($task->threshold_value); ?>">
                    </label>
                    <label>Libellé du bouton
                        <input type="text" name="action_label" value="<?php echo esc_attr($task->action_label); ?>" required>
                    </label>
                    <label>Statut
                        <select name="status">
                            <option value="pending" <?php selected($task->status, 'pending'); ?>>À traiter</option>
                            <option value="resolved" <?php selected($task->status, 'resolved'); ?>>Traité</option>
                        </select>
                    </label>
                </div>
                <label>Message
                    <textarea name="message" rows="5"><?php echo esc_textarea((string) $task->message); ?></textarea>
                </label>
                <button class="gca-button gca-button-primary" type="submit">Enregistrer l’action</button>
            </form>
        </section>
        <?php
    }

    private function render_action_edit(string $source, int $action_id): void
    {
        global $wpdb;

        if ($source === 'communication') {
            $table = GCA_Database::table('communications');
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $action_id));
            if (!$row) {
                echo '<p class="gca-empty">Action introuvable.</p>';
                return;
            }
            $student = $this->student_with_class((int) $row->student_id);
            ?>
            <section class="gca-panel">
                <div class="gca-panel-title">
                    <div>
                        <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'actions'))); ?>">&larr; Actions exécutées</a>
                        <h2>Modifier l’e-mail envoyé</h2>
                        <?php if ($student) : ?>
                            <p class="gca-muted">
                                <?php echo $this->student_link_with_company($student); ?>
                                ·
                                <?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_save_action_communication_' . (int) $row->id); ?>
                    <input type="hidden" name="action" value="gca_save_action">
                    <input type="hidden" name="source" value="communication">
                    <input type="hidden" name="action_id" value="<?php echo esc_attr((string) (int) $row->id); ?>">
                    <div class="gca-form-grid">
                        <label>Type
                            <input type="text" name="communication_type" value="<?php echo esc_attr($row->communication_type); ?>">
                        </label>
                        <label>Statut
                            <select name="status">
                                <option value="sent" <?php selected($row->status, 'sent'); ?>>Envoyé</option>
                                <option value="cancelled" <?php selected($row->status, 'cancelled'); ?>>Annulé</option>
                            </select>
                        </label>
                        <label>À
                            <input type="text" name="recipients_to" value="<?php echo esc_attr($row->recipients_to); ?>">
                        </label>
                        <label>Copie
                            <input type="text" name="recipients_cc" value="<?php echo esc_attr($row->recipients_cc); ?>">
                        </label>
                    </div>
                    <label>Objet
                        <input type="text" name="subject" value="<?php echo esc_attr($row->subject); ?>">
                    </label>
                    <?php $this->render_rich_text_editor('body', (string) $row->body, array('rows' => 10)); ?>
                    <button class="gca-button gca-button-primary" type="submit">Enregistrer l’action exécutée</button>
                </form>
            </section>
            <?php
            return;
        }

        if ($source === 'event') {
            $table = GCA_Database::table('events');
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $action_id));
            if (!$row) {
                echo '<p class="gca-empty">Action introuvable.</p>';
                return;
            }
            $student = $this->student_with_class((int) $row->student_id);
            ?>
            <section class="gca-panel">
                <div class="gca-panel-title">
                    <div>
                        <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'actions'))); ?>">&larr; Actions exécutées</a>
                        <h2>Modifier l’événement</h2>
                        <?php if ($student) : ?>
                            <p class="gca-muted">
                                <?php echo $this->student_link_with_company($student); ?>
                                ·
                                <?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_save_action_event_' . (int) $row->id); ?>
                    <input type="hidden" name="action" value="gca_save_action">
                    <input type="hidden" name="source" value="event">
                    <input type="hidden" name="action_id" value="<?php echo esc_attr((string) (int) $row->id); ?>">
                    <div class="gca-form-grid">
                        <label>Type
                            <input type="text" name="event_type" value="<?php echo esc_attr($row->event_type); ?>">
                        </label>
                        <label>Libellé
                            <input type="text" name="status" value="<?php echo esc_attr($row->status); ?>">
                        </label>
                    </div>
                    <label>Données techniques JSON
                        <textarea name="payload_json" rows="8"><?php echo esc_textarea((string) $row->payload_json); ?></textarea>
                    </label>
                    <button class="gca-button gca-button-primary" type="submit">Enregistrer l’événement</button>
                </form>
            </section>
            <?php
        }
    }

    private function render_reports(): void
    {
        global $wpdb;

        $classes_table = GCA_Database::table('classes');
        $students_table = GCA_Database::table('students');
        $selected_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
        $this->sync_trainers_from_students();
        $classes = $wpdb->get_results("SELECT * FROM {$classes_table} ORDER BY status ASC, name ASC");
        $active_classes = array_values(array_filter($classes, static function (object $class): bool {
            return (string) $class->status === 'active';
        }));
        $students = array();
        $trainer_templates = GCA_Database::get_trainer_message_templates();
        $selected_trainer_template = reset($trainer_templates);
        if (!is_array($selected_trainer_template)) {
            $selected_trainer_template = array('id' => '', 'label' => '', 'subject' => '', 'body' => '');
        }
        $trainer_groups = $this->trainer_message_groups($selected_class_id);
        $trainer_recipient_count = count($trainer_groups);
        $trainer_student_count = $this->trainer_group_student_count($trainer_groups);
        $trainer_missing_email_count = $this->trainer_message_missing_email_count($selected_class_id);
        $student_recipient_count = $this->trainer_group_student_copy_count($trainer_groups);
        $student_missing_email_count = $this->trainer_group_student_missing_copy_count($trainer_groups);
        $contract_break_students = $this->contract_break_students($selected_class_id);
        $contract_break_student_count = count($contract_break_students);
        $bulk_recipient_count = $trainer_recipient_count;

        if ($selected_class_id > 0) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT s.*, c.name AS class_name, c.school_year
                     FROM {$students_table} s
                     INNER JOIN {$classes_table} c ON c.id = s.class_id
                     WHERE s.class_id = %d AND s.archived_at IS NULL
                     ORDER BY s.last_name ASC, s.first_name ASC",
                    $selected_class_id
                )
            );
        }
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <h2>Communication</h2>
                    <p class="gca-muted">Rapports individuels et envois ciblés aux responsables de formation.</p>
                </div>
            </div>

            <form class="gca-filter" method="get">
                <?php $this->hidden_page_fields(array('gca_tab' => 'reports')); ?>
                <select name="class_id" required>
                    <option value="0">Choisir une classe</option>
                    <?php foreach ($classes as $class) : ?>
                        <option value="<?php echo esc_attr((string) (int) $class->id); ?>" <?php selected($selected_class_id, (int) $class->id); ?>>
                            <?php echo esc_html($class->name . ' - ' . $class->school_year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="gca-button" type="submit">Afficher</button>
            </form>

            <?php $this->render_contract_break_alert($contract_break_students); ?>

            <div class="gca-form-section">
                <h3>Responsables de formation</h3>
                <p class="gca-muted">Une seule ligne par e-mail de responsable. Les apprentis en rupture de contrat ou candidats libres sont exclus des envois formateur.</p>
                <?php if (!$trainer_groups) : ?>
                    <p class="gca-empty">Aucun e-mail de responsable valide pour la sélection actuelle.</p>
                <?php else : ?>
                    <div class="gca-table-wrap">
                        <table class="gca-table">
                            <thead>
                                <tr>
                                    <th>E-mail du responsable</th>
                                    <th>Formateur responsable</th>
                                    <th>Apprentis rattachés</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trainer_groups as $group) : ?>
                                    <tr>
                                        <td><?php echo esc_html((string) $group['email']); ?></td>
                                        <td>
                                            <?php foreach ($this->trainer_group_responsible_labels($group) as $responsible_label) : ?>
                                                <span><?php echo esc_html($responsible_label); ?></span><br>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <?php $this->render_trainer_group_student_links($group); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="gca-form-section">
                <h3>Envois de masse</h3>
                <p class="gca-muted">Choisissez un message préformaté et les classes concernées. Un e-mail individuel part uniquement au responsable de formation. Les apprentis en rupture de contrat ou candidats libres sont signalés et retirés de l’envoi formateur.</p>
                <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_send_trainer_bulk_message'); ?>
                    <input type="hidden" name="action" value="gca_send_trainer_bulk_message">
                    <div class="gca-form-grid">
                        <label>Destinataires
                            <select name="recipient_mode">
                                <option value="trainers" selected>Formateurs uniquement</option>
                                <option value="trainers_students">Formateurs + élèves en copie</option>
                            </select>
                        </label>
                        <label>Message à envoyer
                            <select name="trainer_template_id" data-gca-trainer-template-select>
                                <?php foreach ($trainer_templates as $template) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $template['id']); ?>"
                                        data-label="<?php echo esc_attr((string) $template['label']); ?>"
                                        data-subject="<?php echo esc_attr((string) $template['subject']); ?>"
                                        data-body="<?php echo esc_attr((string) $template['body']); ?>"
                                        <?php selected((string) $selected_trainer_template['id'], (string) $template['id']); ?>
                                    >
                                        <?php echo esc_html((string) $template['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Objet
                            <input type="text" name="subject" data-gca-trainer-template-subject value="<?php echo esc_attr((string) $selected_trainer_template['subject']); ?>" required>
                        </label>
                    </div>
                    <?php $this->render_rich_text_editor('body', (string) $selected_trainer_template['body'], array('rows' => 8, 'required' => true, 'source_attrs' => array('data-gca-trainer-template-body' => ''))); ?>
                    <div class="gca-form-grid">
                        <label>Adresses de test
                            <input type="text" name="test_recipients" placeholder="adresse1@exemple.ch, adresse2@exemple.ch">
                        </label>
                    </div>
                    <div>
                        <strong>Classes destinataires</strong>
                        <div class="gca-checkbox-grid">
                            <?php foreach ($active_classes as $class) : ?>
                                <label>
                                    <input type="checkbox" name="class_ids[]" value="<?php echo esc_attr((string) (int) $class->id); ?>" <?php checked($selected_class_id <= 0 || $selected_class_id === (int) $class->id); ?>>
                                    <?php echo esc_html($class->name . ' - ' . $class->school_year); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="gca-muted">Variables disponibles : {trainer_email}, {responsible_trainer_name}, {responsible_trainer_company}, {trainer_company_full_address}, {apprentices_list}, {apprentices_count}, {classes_list}, {today}. Les repères {{Prénom Nom}} et {{Numéro de classe}} sont aussi compris dans les listes à puces. La liste des apprentis reprend le prénom, le nom et le numéro court de classe.</p>
                    <p class="gca-muted">
                        <?php echo esc_html($trainer_recipient_count . ' destinataire(s) principal(aux) avec e-mail · ' . $student_recipient_count . ' élève(s) pouvant être mis en copie · ' . $trainer_student_count . ' apprenti(s) relié(s) aux formateurs'); ?>
                        <?php if ($trainer_missing_email_count > 0) : ?>
                            · <?php echo esc_html($trainer_missing_email_count . ' apprenti(s) ignoré(s), car aucun e-mail de responsable n’est renseigné.'); ?>
                        <?php endif; ?>
                        <?php if ($student_missing_email_count > 0) : ?>
                            · <?php echo esc_html($student_missing_email_count . ' apprenti(s) ne pourront pas être mis en copie, car aucun e-mail élève valide n’est renseigné.'); ?>
                        <?php endif; ?>
                        <?php if ($contract_break_student_count > 0) : ?>
                            · <?php echo esc_html($contract_break_student_count . ' apprenti(s) sans employeur actif exclu(s) des envois formateur.'); ?>
                        <?php endif; ?>
                    </p>
                    <div class="gca-actions">
                        <button class="gca-button" type="submit" name="bulk_action" value="send_test">Envoyer un test</button>
                        <button class="gca-button gca-button-primary" type="submit" name="bulk_action" value="send" <?php disabled($bulk_recipient_count, 0); ?>>Envoyer le message</button>
                    </div>
                </form>
            </div>

            <?php if ($selected_class_id > 0 && $students) : ?>
                <form class="gca-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_generate_class_reports_' . (int) $selected_class_id); ?>
                    <input type="hidden" name="action" value="gca_generate_class_reports">
                    <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $selected_class_id); ?>">
                    <button class="gca-button gca-button-primary" type="submit">Télécharger les rapports de la classe</button>
                </form>
            <?php endif; ?>

            <?php if ($selected_class_id <= 0) : ?>
                <p class="gca-empty">Sélectionnez une classe pour préparer les rapports.</p>
            <?php elseif (!$students) : ?>
                <p class="gca-empty">Aucun apprenti actif dans cette classe.</p>
            <?php else : ?>
                <div class="gca-table-wrap">
                    <table class="gca-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Apprenti</th>
                                <th>Genre</th>
                                <th>Formateur</th>
                                <th>Indicateurs</th>
                                <th>Rapport</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student) : ?>
                                <?php $student_without_employer = $this->student_without_employer($student); ?>
                                <tr>
                                    <td><?php echo $this->student_photo_markup($student, 'thumbnail'); ?></td>
                                    <td>
                                        <?php echo $this->student_link_with_company($student, true); ?>
                                        <br><span class="gca-muted"><?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?></span>
                                    </td>
                                    <td><?php echo esc_html($this->gender_label((string) $student->gender)); ?></td>
                                    <td>
                                        <?php
                                        $responsible_trainer = trim((string) ($student->trainer_name ?? ''));
                                        ?>
                                        <?php if ($student_without_employer) : ?>
                                            <span class="gca-muted">Aucun employeur actif</span>
                                        <?php else : ?>
                                            <strong>Responsable</strong> <?php echo esc_html($responsible_trainer !== '' ? $this->name_with_company_label($responsible_trainer, (string) ($student->trainer_company ?? '')) : '-'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="gca-metric">Retards <?php echo esc_html((string) (int) $student->lateness_count); ?></span>
                                        <span class="gca-metric">Périodes abs. <?php echo esc_html($this->format_decimal($student->absence_count)); ?></span>
                                        <?php if ($this->absence_justified_count($student) > 0) : ?>
                                            <span class="gca-metric gca-metric-muted">Justifiées <?php echo esc_html($this->format_decimal($this->absence_justified_count($student))); ?></span>
                                            <span class="gca-metric">À traiter <?php echo esc_html($this->format_decimal($this->actionable_absence_count($student))); ?></span>
                                        <?php endif; ?>
                                        <span class="gca-metric">Taux abs. <?php echo esc_html($this->format_decimal($this->actionable_absence_rate($student))); ?>%</span>
                                        <span class="gca-metric">Mises à la porte <?php echo esc_html((string) (int) $student->removal_count); ?></span>
                                        <span class="gca-metric">Commentaires <?php echo esc_html((string) (int) ($student->comment_count ?? 0)); ?></span>
                                        <?php if ($student_without_employer) : ?>
                                            <span class="gca-badge gca-badge-danger"><?php echo esc_html($this->contract_status_label((string) ($student->contract_status ?? 'active'))); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="gca-actions">
                                        <a class="gca-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gca_generate_student_report&student_id=' . (int) $student->id), 'gca_generate_student_report_' . (int) $student->id)); ?>">PDF</a>
                                        <?php if ($student_without_employer) : ?>
                                            <span class="gca-badge gca-badge-danger" title="<?php echo esc_attr($this->contract_break_alert_text($student)); ?>">E-mail formateur bloqué</span>
                                        <?php else : ?>
                                            <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'reports', 'gca_action' => 'prepare_report_email', 'student_id' => (int) $student->id))); ?>">E-mail</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_message_templates(): void
    {
        $trainer_templates = GCA_Database::get_trainer_message_templates();
        $selected_trainer_template = reset($trainer_templates);
        if (!is_array($selected_trainer_template)) {
            $selected_trainer_template = array('id' => '', 'label' => '', 'subject' => '', 'body' => '');
        }

        $free_templates = GCA_Database::get_free_message_templates();
        $selected_free_template = reset($free_templates);
        if (!is_array($selected_free_template)) {
            $selected_free_template = array('id' => '', 'label' => '', 'subject' => '', 'body' => '');
        }
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <h2>Messages types</h2>
                    <p class="gca-muted">Création et modification des textes réutilisables. Ce module n’envoie aucun e-mail.</p>
                </div>
            </div>

            <div class="gca-form-section">
                <h3>Modèles pour e-mails libres</h3>
                <p class="gca-muted">Ces modèles sont proposés lorsque vous utilisez le bouton <strong>Rédiger un e-mail libre</strong> depuis une situation à traiter.</p>
                <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_save_free_message_template'); ?>
                    <input type="hidden" name="action" value="gca_save_free_message_template">
                    <div class="gca-form-grid">
                        <label>Modèle libre à modifier
                            <select name="free_template_id" data-gca-message-template-select>
                                <option value="" data-label="" data-subject="" data-body="">Créer un nouveau modèle</option>
                                <?php foreach ($free_templates as $template) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $template['id']); ?>"
                                        data-label="<?php echo esc_attr((string) $template['label']); ?>"
                                        data-subject="<?php echo esc_attr((string) $template['subject']); ?>"
                                        data-body="<?php echo esc_attr((string) $template['body']); ?>"
                                        <?php selected((string) $selected_free_template['id'], (string) $template['id']); ?>
                                    >
                                        <?php echo esc_html((string) $template['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Nom du modèle
                            <input type="text" name="template_label" data-gca-message-template-label value="<?php echo esc_attr((string) $selected_free_template['label']); ?>" placeholder="Mise à la porte">
                        </label>
                        <label>Objet
                            <input type="text" name="subject" data-gca-message-template-subject value="<?php echo esc_attr((string) $selected_free_template['subject']); ?>" required>
                        </label>
                    </div>
                    <?php $this->render_rich_text_editor('body', (string) $selected_free_template['body'], array('rows' => 8, 'required' => true, 'source_attrs' => array('data-gca-message-template-body' => ''))); ?>
                    <p class="gca-muted">Variables disponibles : {full_name}, {first_name}, {last_name}, {class_name}, {class_code}, {lateness_count}, {lateness_total}, {absence_count}, {absence_rate}, {absence_imported_count}, {absence_justified_count}, {removal_count}, {comment_count}, {threshold}, {today}, {trainer_company}, {responsible_trainer_name}, {contract_status}.</p>
                    <button class="gca-button gca-button-primary" type="submit">Enregistrer le modèle libre</button>
                </form>
            </div>

            <div class="gca-form-section">
                <h3>Modèles pour envois de masse</h3>
                <p class="gca-muted">Ces modèles sont utilisés dans l’onglet <strong>Communication</strong>, section <strong>Envois de masse</strong>.</p>
                <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('gca_send_trainer_bulk_message'); ?>
                    <input type="hidden" name="action" value="gca_send_trainer_bulk_message">
                    <input type="hidden" name="bulk_action" value="save_template">
                    <div class="gca-form-grid">
                        <label>Message préformaté à modifier
                            <select name="trainer_template_id" data-gca-trainer-template-select>
                                <option value="" data-label="" data-subject="" data-body="">Créer un nouveau message</option>
                                <?php foreach ($trainer_templates as $template) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $template['id']); ?>"
                                        data-label="<?php echo esc_attr((string) $template['label']); ?>"
                                        data-subject="<?php echo esc_attr((string) $template['subject']); ?>"
                                        data-body="<?php echo esc_attr((string) $template['body']); ?>"
                                        <?php selected((string) $selected_trainer_template['id'], (string) $template['id']); ?>
                                    >
                                        <?php echo esc_html((string) $template['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Nom du message préformaté
                            <input type="text" name="template_label" data-gca-trainer-template-label value="<?php echo esc_attr((string) $selected_trainer_template['label']); ?>" placeholder="Information aux formateurs">
                        </label>
                        <label>Objet
                            <input type="text" name="subject" data-gca-trainer-template-subject value="<?php echo esc_attr((string) $selected_trainer_template['subject']); ?>" required>
                        </label>
                    </div>
                    <?php $this->render_rich_text_editor('body', (string) $selected_trainer_template['body'], array('rows' => 8, 'required' => true, 'source_attrs' => array('data-gca-trainer-template-body' => ''))); ?>
                    <p class="gca-muted">Variables disponibles : {trainer_email}, {responsible_trainer_name}, {responsible_trainer_company}, {trainer_company_full_address}, {apprentices_list}, {apprentices_count}, {classes_list}, {today}. Les repères {{Prénom Nom}} et {{Numéro de classe}} sont aussi compris dans les listes à puces.</p>
                    <button class="gca-button gca-button-primary" type="submit">Enregistrer le message préformaté</button>
                </form>
            </div>
        </section>
        <?php
    }

    private function render_report_email_form(int $student_id): void
    {
        global $wpdb;

        $student = $this->student_with_class($student_id);
        if (!$student) {
            echo '<p class="gca-empty">Apprenti introuvable.</p>';
            return;
        }

        if ($this->student_without_employer($student)) {
            ?>
            <section class="gca-panel">
                <div class="gca-panel-title">
                    <div>
                        <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'reports', 'class_id' => (int) $student->class_id))); ?>">&larr; Communication</a>
                        <h2>Envoyer le rapport au formateur</h2>
                        <p class="gca-muted">
                            <?php echo $this->student_link_with_company($student); ?>
                            ·
                            <?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?>
                        </p>
                    </div>
                </div>
                <div class="gca-notice gca-notice-error">
                    <strong>Envoi formateur bloqué.</strong>
                    <p><?php echo esc_html($this->contract_break_alert_text($student)); ?></p>
                </div>
                <div class="gca-actions">
                    <a class="gca-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gca_generate_student_report&student_id=' . (int) $student->id), 'gca_generate_student_report_' . (int) $student->id)); ?>">Télécharger le PDF</a>
                    <a class="gca-button" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'classes', 'student_id' => (int) $student->id))); ?>" target="_blank" rel="noopener">Ouvrir la fiche</a>
                </div>
            </section>
            <?php
            return;
        }

        $templates = GCA_Database::get_email_templates();
        $recipients_to = array();
        $recipients_cc = array();
        $trainer_email = sanitize_email((string) ($student->trainer_email ?? ''));
        if (is_email($trainer_email)) {
            $recipients_to[] = $trainer_email;
        }
        $subject = $this->replace_variables((string) ($templates['student_report_subject'] ?? ''), $student);
        $subject = $this->ensure_subject_class_code($subject, $student);
        $body = $this->replace_variables((string) ($templates['student_report_body'] ?? ''), $student);
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                    <div>
                        <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'reports', 'class_id' => (int) $student->class_id))); ?>">&larr; Communication</a>
                        <h2>Envoyer le rapport au formateur</h2>
                    <p class="gca-muted">
                        <?php echo $this->student_link_with_company($student); ?>
                        ·
                        <?php echo $this->class_link((int) $student->class_id, (string) $student->class_name); ?>
                    </p>
                </div>
            </div>

            <form class="gca-form gca-form-wide" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gca_send_student_report_' . (int) $student->id); ?>
                <input type="hidden" name="action" value="gca_send_student_report">
                <input type="hidden" name="student_id" value="<?php echo esc_attr((string) (int) $student->id); ?>">
                <div class="gca-form-grid">
                    <label>À
                        <input type="text" name="recipients_to" value="<?php echo esc_attr(implode(', ', $recipients_to)); ?>" required>
                    </label>
                    <label>Copie
                        <input type="text" name="recipients_cc" value="<?php echo esc_attr(implode(', ', $recipients_cc)); ?>">
                    </label>
                    <label>Copie cachée
                        <input type="text" name="recipients_bcc" value="<?php echo esc_attr(implode(', ', $this->always_bcc_emails())); ?>">
                    </label>
                </div>
                <label>Objet
                    <input type="text" name="subject" value="<?php echo esc_attr($subject); ?>" required>
                </label>
                <?php $this->render_rich_text_editor('body', $body, array('rows' => 10, 'required' => true)); ?>
                <p class="gca-muted">Le rapport complet sera généré en PDF et joint à cet e-mail. Par défaut, le destinataire est le responsable de formation enregistré sur la fiche apprenti.</p>
                <button class="gca-button gca-button-primary" type="submit">Envoyer le rapport</button>
            </form>
        </section>
        <?php
    }

    private function trainer_by_id(int $trainer_id): ?object
    {
        global $wpdb;

        if ($trainer_id <= 0) {
            return null;
        }

        $trainer = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . GCA_Database::table('trainers') . " WHERE id = %d", $trainer_id)
        );

        return $trainer ?: null;
    }

    private function trainer_select_options(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM " . GCA_Database::table('trainers') . " ORDER BY name ASC, company ASC, email ASC"
        );
    }

    private function trainer_profiles_by_id(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT * FROM " . GCA_Database::table('trainers'));
        $profiles = array();
        foreach ($rows as $row) {
            $profiles[(int) $row->id] = $row;
        }

        return $profiles;
    }

    private function trainer_display_name(object $trainer): string
    {
        $name = trim((string) ($trainer->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return 'Responsable non renseigné';
    }

    private function trainer_option_label(object $trainer): string
    {
        $label = $this->trainer_display_name($trainer);
        $company = trim((string) ($trainer->company ?? ''));
        $email = sanitize_email((string) ($trainer->email ?? ''));

        if ($company !== '') {
            $label .= ' - ' . $company;
        }

        if (is_email($email)) {
            $label .= ' (' . $email . ')';
        }

        return $label;
    }

    private function trainer_link(int $trainer_id, string $label): string
    {
        $label = trim($label);
        if ($trainer_id <= 0 || $label === '') {
            return esc_html($label !== '' ? $label : '-');
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($this->page_url(array('gca_tab' => 'trainers', 'trainer_id' => $trainer_id))),
            esc_html($label)
        );
    }

    private function student_selected_trainer_id(object $student, array $trainers): int
    {
        $trainer_id = (int) ($student->trainer_id ?? 0);
        if ($trainer_id > 0) {
            return $trainer_id;
        }

        $email = sanitize_email((string) ($student->trainer_email ?? ''));
        if (!is_email($email)) {
            return 0;
        }

        foreach ($trainers as $trainer) {
            if (strtolower(sanitize_email((string) $trainer->email)) === strtolower($email)) {
                return (int) $trainer->id;
            }
        }

        return 0;
    }

    private function trainer_students(int $trainer_id): array
    {
        global $wpdb;

        if ($trainer_id <= 0) {
            return array();
        }

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, c.name AS class_name, c.school_year
                 FROM {$students_table} s
                 INNER JOIN {$classes_table} c ON c.id = s.class_id
                 WHERE s.archived_at IS NULL
                   AND s.trainer_id = %d
                   AND COALESCE(s.contract_status, 'active') NOT IN ('broken', 'free_candidate')
                 ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC",
                $trainer_id
            )
        );
    }

    private function students_available_for_trainer(int $trainer_id): array
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, c.name AS class_name, c.school_year
                 FROM {$students_table} s
                 INNER JOIN {$classes_table} c ON c.id = s.class_id
                 WHERE s.archived_at IS NULL
                   AND c.status = 'active'
                   AND s.trainer_id <> %d
                   AND COALESCE(s.contract_status, 'active') NOT IN ('broken', 'free_candidate')
                 ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC",
                $trainer_id
            )
        );
    }

    private function student_assignment_option_label(object $student): string
    {
        $label = $this->student_class_label($student);
        $name = $this->student_display_name($student, true);
        $trainer = trim((string) ($student->trainer_name ?? ''));

        $parts = array_filter(array($label, $name));
        $text = implode(' - ', $parts);

        if ($trainer !== '') {
            $text .= ' (actuellement : ' . $trainer . ')';
        }

        return $text !== '' ? $text : 'Apprenti #' . (int) ($student->id ?? 0);
    }

    private function sync_trainers_from_students(): int
    {
        global $wpdb;

        $table = GCA_Database::table('trainers');
        $students_table = GCA_Database::table('students');
        $groups = array();

        foreach ($this->trainer_rows_from_students() as $student) {
            $student_id = (int) ($student->id ?? 0);
            $trainer_id = (int) ($student->trainer_id ?? 0);
            $email = sanitize_email((string) $student->trainer_email);
            $name = trim((string) $student->trainer_name);
            $company = trim((string) $student->trainer_company);
            $company_address = trim((string) ($student->trainer_company_address ?? ''));
            $company_zip = trim((string) ($student->trainer_company_zip ?? ''));
            $company_city = trim((string) ($student->trainer_company_city ?? ''));
            $phone = trim((string) $student->trainer_phone);

            if ($student_id <= 0 || ($trainer_id <= 0 && !is_email($email) && $name === '' && $company === '' && $company_address === '' && $company_zip === '' && $company_city === '' && $phone === '')) {
                continue;
            }

            $key = $this->trainer_key($email, $name, $company, $phone);
            $group_key = $trainer_id > 0 ? 'id:' . $trainer_id : 'key:' . $key;

            if (!isset($groups[$group_key])) {
                $groups[$group_key] = array(
                    'trainer_id' => $trainer_id,
                    'trainer_key' => $key,
                    'name' => $name,
                    'company' => $company,
                    'company_address' => $company_address,
                    'company_zip' => $company_zip,
                    'company_city' => $company_city,
                    'email' => $email,
                    'phone' => $phone,
                    'student_ids' => array(),
                    'unlinked_student_ids' => array(),
                );
            }

            foreach (
                array(
                    'name' => 'trainer_name',
                    'company' => 'trainer_company',
                    'company_address' => 'trainer_company_address',
                    'company_zip' => 'trainer_company_zip',
                    'company_city' => 'trainer_company_city',
                    'phone' => 'trainer_phone',
                ) as $target => $source
            ) {
                if ($groups[$group_key][$target] === '' && trim((string) $student->{$source}) !== '') {
                    $groups[$group_key][$target] = trim((string) $student->{$source});
                }
            }
            if (!is_email((string) $groups[$group_key]['email']) && is_email($email)) {
                $groups[$group_key]['email'] = $email;
            }

            $groups[$group_key]['student_ids'][] = $student_id;
            if ($trainer_id <= 0) {
                $groups[$group_key]['unlinked_student_ids'][] = $student_id;
            }
        }

        $updated = 0;
        foreach ($groups as $group) {
            $existing = null;
            if ((int) $group['trainer_id'] > 0) {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $group['trainer_id']));
            }
            if (!$existing) {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE trainer_key = %s", $group['trainer_key']));
            }
            if (!$existing && is_email((string) $group['email'])) {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE LOWER(email) = LOWER(%s) LIMIT 1", (string) $group['email']));
            }

            if ($existing) {
                $data = array();
                $allow_overwrite = (int) $group['trainer_id'] <= 0;
                $locked_fields = array_fill_keys(GCA_Database::manual_fields_from_json($existing->manual_fields_json ?? ''), true);
                foreach (array('name', 'company', 'company_address', 'company_zip', 'company_city', 'email', 'phone') as $field) {
                    $value = trim((string) ($group[$field] ?? ''));
                    $current = trim((string) ($existing->{$field} ?? ''));
                    if ($value !== '' && empty($locked_fields[$field]) && ($allow_overwrite || $current === '') && $value !== $current) {
                        $data[$field] = $value;
                    }
                }
                if (trim((string) $existing->salutation) === '' && empty($locked_fields['salutation'])) {
                    $data['salutation'] = $this->default_trainer_salutation((string) ($data['name'] ?? $existing->name ?? $group['name']));
                }
                if ($data) {
                    $data['updated_at'] = GCA_Database::now();
                    $wpdb->update($table, $data, array('id' => (int) $existing->id), array_fill(0, count($data), '%s'), array('%d'));
                    $updated++;
                }

                $trainer_id = (int) $existing->id;
            } else {
                $data = array(
                    'trainer_key' => $group['trainer_key'],
                    'name' => (string) $group['name'],
                    'company' => (string) $group['company'],
                    'company_address' => (string) $group['company_address'],
                    'company_zip' => (string) $group['company_zip'],
                    'company_city' => (string) $group['company_city'],
                    'email' => (string) $group['email'],
                    'phone' => (string) $group['phone'],
                    'salutation' => $this->default_trainer_salutation((string) $group['name']),
                    'created_at' => GCA_Database::now(),
                    'updated_at' => GCA_Database::now(),
                );
                $wpdb->insert(
                    $table,
                    $data,
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                $trainer_id = (int) $wpdb->insert_id;
                $updated++;
            }

            $link_ids = array_values(array_unique(array_filter(array_map('intval', (array) $group['unlinked_student_ids']))));
            if (!$existing && (int) $group['trainer_id'] > 0) {
                $link_ids = array_values(array_unique(array_filter(array_map('intval', (array) $group['student_ids']))));
            }

            if ($trainer_id > 0 && $link_ids) {
                $linked_trainer = $wpdb->get_row($wpdb->prepare("SELECT name, company, company_address, company_zip, company_city, email, phone FROM {$table} WHERE id = %d", $trainer_id));
                $placeholders = implode(', ', array_fill(0, count($link_ids), '%d'));
                if ($linked_trainer) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$students_table}
                             SET trainer_id = %d,
                                 trainer_name = %s,
                                 trainer_company = %s,
                                 trainer_company_address = %s,
                                 trainer_company_zip = %s,
                                 trainer_company_city = %s,
                                 trainer_email = %s,
                                 trainer_phone = %s,
                                 updated_at = %s
                             WHERE id IN ({$placeholders})",
                            array_merge(
                                array(
                                    $trainer_id,
                                    (string) $linked_trainer->name,
                                    (string) $linked_trainer->company,
                                    (string) ($linked_trainer->company_address ?? ''),
                                    (string) ($linked_trainer->company_zip ?? ''),
                                    (string) ($linked_trainer->company_city ?? ''),
                                    (string) $linked_trainer->email,
                                    (string) $linked_trainer->phone,
                                    GCA_Database::now(),
                                ),
                                $link_ids
                            )
                        )
                    );
                }
            }
        }

        return $updated;
    }

    private function trainer_rows_from_students(array $class_ids = array()): array
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $class_sql = $this->class_ids_sql_filter($class_ids);

        return $wpdb->get_results(
            "SELECT s.*, c.name AS class_name, c.school_year
             FROM {$students_table} s
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE s.archived_at IS NULL
               AND c.status = 'active'
               AND COALESCE(s.contract_status, 'active') NOT IN ('broken', 'free_candidate')
               AND (
                   s.trainer_id > 0
                   OR TRIM(s.trainer_email) <> ''
                   OR TRIM(s.trainer_name) <> ''
                   OR TRIM(s.trainer_company) <> ''
                   OR TRIM(s.trainer_company_address) <> ''
                   OR TRIM(s.trainer_company_zip) <> ''
                   OR TRIM(s.trainer_company_city) <> ''
                   OR TRIM(s.trainer_phone) <> ''
               )
               {$class_sql}
             ORDER BY s.trainer_email ASC, c.name ASC, s.last_name ASC, s.first_name ASC"
        );
    }

    private function trainer_profiles_by_key(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT * FROM " . GCA_Database::table('trainers'));
        $profiles = array();
        foreach ($rows as $row) {
            $key = trim((string) $row->trainer_key);
            if ($key !== '') {
                $profiles[$key] = $row;
            }
        }

        return $profiles;
    }

    private function trainer_key(string $email, string $name = '', string $company = '', string $phone = ''): string
    {
        $email = sanitize_email($email);
        if (is_email($email)) {
            return sha1('email:' . strtolower($email));
        }

        $fallback = strtolower(remove_accents(trim($name . '|' . $company . '|' . $phone)));
        return sha1('trainer:' . $fallback);
    }

    private function unique_trainer_key(string $base_key): string
    {
        global $wpdb;

        $base_key = preg_match('/^[a-f0-9]{40}$/', $base_key) ? $base_key : sha1('trainer:' . $base_key);
        $key = $base_key;
        $suffix = 2;
        $table = GCA_Database::table('trainers');

        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE trainer_key = %s LIMIT 1", $key))) {
            $key = sha1($base_key . ':' . $suffix);
            $suffix++;
        }

        return $key;
    }

    private function propagate_trainer_to_students(int $trainer_id, array $student_ids = array(), string $event_type = ''): array
    {
        global $wpdb;

        $trainer = $this->trainer_by_id($trainer_id);
        if (!$trainer) {
            return array();
        }

        $students_table = GCA_Database::table('students');
        $student_ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));

        if ($student_ids) {
            $placeholders = implode(', ', array_fill(0, count($student_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT id, trainer_id, trainer_name, trainer_company, trainer_company_address, trainer_company_zip, trainer_company_city, trainer_email, trainer_phone, manual_fields_json
                 FROM {$students_table}
                 WHERE archived_at IS NULL AND COALESCE(contract_status, 'active') NOT IN ('broken', 'free_candidate') AND id IN ({$placeholders})",
                $student_ids
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT id, trainer_id, trainer_name, trainer_company, trainer_company_address, trainer_company_zip, trainer_company_city, trainer_email, trainer_phone, manual_fields_json
                 FROM {$students_table}
                 WHERE archived_at IS NULL AND COALESCE(contract_status, 'active') NOT IN ('broken', 'free_candidate') AND trainer_id = %d",
                $trainer_id
            );
        }

        $rows = $wpdb->get_results($query, ARRAY_A);
        $target = array(
            'trainer_id' => $trainer_id,
            'trainer_name' => (string) $trainer->name,
            'trainer_company' => (string) $trainer->company,
            'trainer_company_address' => (string) ($trainer->company_address ?? ''),
            'trainer_company_zip' => (string) ($trainer->company_zip ?? ''),
            'trainer_company_city' => (string) ($trainer->company_city ?? ''),
            'trainer_email' => (string) $trainer->email,
            'trainer_phone' => (string) $trainer->phone,
        );
        $affected_ids = array();
        foreach ($rows as $row) {
            foreach ($target as $field => $value) {
                if (trim((string) ($row[$field] ?? '')) !== trim((string) $value)) {
                    $affected_ids[] = (int) $row['id'];
                    break;
                }
            }
        }
        $affected_ids = array_values(array_unique(array_filter(array_map('intval', $affected_ids))));
        if (!$affected_ids) {
            return array();
        }

        $placeholders = implode(', ', array_fill(0, count($affected_ids), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$students_table}
                 SET trainer_id = %d,
                     trainer_name = %s,
                     trainer_company = %s,
                     trainer_company_address = %s,
                     trainer_company_zip = %s,
                     trainer_company_city = %s,
                     trainer_email = %s,
                     trainer_phone = %s,
                     updated_at = %s
                 WHERE id IN ({$placeholders})",
                array_merge(
                    array(
                        $trainer_id,
                        (string) $trainer->name,
                        (string) $trainer->company,
                        (string) ($trainer->company_address ?? ''),
                        (string) ($trainer->company_zip ?? ''),
                        (string) ($trainer->company_city ?? ''),
                        (string) $trainer->email,
                        (string) $trainer->phone,
                        GCA_Database::now(),
                    ),
                    $affected_ids
                )
            )
        );

        $manual_student_fields = array(
            'trainer_id',
            'trainer_name',
            'trainer_company',
            'trainer_company_address',
            'trainer_company_zip',
            'trainer_company_city',
            'trainer_email',
            'trainer_phone',
        );
        $affected_lookup = array_fill_keys($affected_ids, true);
        foreach ($rows as $row) {
            $student_id = (int) $row['id'];
            if (empty($affected_lookup[$student_id])) {
                continue;
            }

            $manual_fields_json = GCA_Database::merge_manual_fields($row['manual_fields_json'] ?? '', $manual_student_fields);
            if ($this->manual_compare_value($row['manual_fields_json'] ?? '') === $manual_fields_json) {
                continue;
            }

            $wpdb->update(
                $students_table,
                array('manual_fields_json' => $manual_fields_json),
                array('id' => $student_id),
                array('%s'),
                array('%d')
            );
        }

        if ($event_type !== '') {
            $status = $event_type === 'student_trainer_assigned'
                ? 'Formateur rattaché'
                : 'Coordonnées formateur mises à jour';
            foreach ($affected_ids as $student_id) {
                GCA_Database::add_event(
                    $student_id,
                    $event_type,
                    $status,
                    array(
                        'trainer_id' => $trainer_id,
                        'trainer_name' => (string) $trainer->name,
                        'trainer_email' => (string) $trainer->email,
                    )
                );
            }
        }

        return $affected_ids;
    }

    private function clear_trainer_from_students(int $trainer_id, array $student_ids): array
    {
        global $wpdb;

        $student_ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));
        if ($trainer_id <= 0 || !$student_ids) {
            return array();
        }

        $students_table = GCA_Database::table('students');
        $placeholders = implode(', ', array_fill(0, count($student_ids), '%d'));
        $affected_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$students_table}
                 WHERE archived_at IS NULL
                   AND trainer_id = %d
                   AND id IN ({$placeholders})",
                array_merge(array($trainer_id), $student_ids)
            )
        );
        $affected_ids = array_values(array_unique(array_filter(array_map('intval', $affected_ids))));

        if (!$affected_ids) {
            return array();
        }

        $placeholders = implode(', ', array_fill(0, count($affected_ids), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$students_table}
                 SET trainer_id = 0,
                     trainer_name = '',
                     trainer_company = '',
                     trainer_company_address = '',
                     trainer_company_zip = '',
                     trainer_company_city = '',
                     trainer_email = '',
                     trainer_phone = '',
                     updated_at = %s
                 WHERE id IN ({$placeholders})",
                array_merge(array(GCA_Database::now()), $affected_ids)
            )
        );

        foreach ($affected_ids as $student_id) {
            GCA_Database::add_event(
                $student_id,
                'student_trainer_removed',
                'Formateur retiré',
                array('trainer_id' => $trainer_id)
            );
        }

        return $affected_ids;
    }

    private function default_trainer_salutation(string $trainer_name): string
    {
        $trainer_name = trim($trainer_name);
        return $trainer_name !== '' ? $trainer_name : 'Madame, Monsieur';
    }

    private function saved_trainer_salutations(): array
    {
        $saved = get_option(LogiPilot_Spaces::option('gca_trainer_salutations'), array());
        if (!is_array($saved)) {
            return array();
        }

        $clean = array();
        foreach ($saved as $key => $salutation) {
            $key = sanitize_text_field((string) $key);
            if (!preg_match('/^[a-f0-9]{40}$/', $key)) {
                continue;
            }

            $clean[$key] = sanitize_text_field((string) $salutation);
        }

        return $clean;
    }

    private function trainer_message_groups(int $class_id = 0): array
    {
        return $this->trainer_message_groups_for_class_ids($class_id > 0 ? array($class_id) : array());
    }

    private function trainer_message_groups_for_class_ids(array $class_ids): array
    {
        $profiles_by_key = $this->trainer_profiles_by_key();
        $profiles_by_id = $this->trainer_profiles_by_id();
        $groups = array();

        foreach ($this->trainer_rows_from_students($class_ids) as $student) {
            if ($this->student_without_employer($student)) {
                continue;
            }

            $student_trainer_id = (int) ($student->trainer_id ?? 0);
            $fallback_key = $this->trainer_key(
                (string) $student->trainer_email,
                (string) $student->trainer_name,
                (string) $student->trainer_company,
                (string) $student->trainer_phone
            );
            $profile = $student_trainer_id > 0 ? ($profiles_by_id[$student_trainer_id] ?? null) : null;
            if (!$profile) {
                $profile = $profiles_by_key[$fallback_key] ?? null;
            }

            $responsible_email = sanitize_email((string) (($profile->email ?? '') ?: $student->trainer_email));
            if (!is_email($responsible_email)) {
                continue;
            }

            $responsible_name = trim((string) (($profile->name ?? '') ?: $student->trainer_name));
            $responsible_company = trim((string) (($profile->company ?? '') ?: $student->trainer_company));
            $responsible_company_address = trim((string) (($profile->company_address ?? '') ?: ($student->trainer_company_address ?? '')));
            $responsible_company_zip = trim((string) (($profile->company_zip ?? '') ?: ($student->trainer_company_zip ?? '')));
            $responsible_company_city = trim((string) (($profile->company_city ?? '') ?: ($student->trainer_company_city ?? '')));
            $responsible_phone = trim((string) (($profile->phone ?? '') ?: $student->trainer_phone));
            $responsible_salutation = trim((string) ($profile->salutation ?? ''));

            $email = $responsible_email;
            $key = $this->trainer_key($email, $responsible_name, $responsible_company, $responsible_phone);
            $responsible_label = trim($responsible_name);
            if ($responsible_label !== '' && $responsible_company !== '') {
                $responsible_label .= ' - ' . $responsible_company;
            } elseif ($responsible_label === '' && $responsible_company !== '') {
                $responsible_label = $responsible_company;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'trainer_id' => $profile ? (int) $profile->id : $student_trainer_id,
                    'trainer_key' => $key,
                    'email' => $email,
                    'trainer_name' => $responsible_name,
                    'trainer_company' => $responsible_company,
                    'trainer_company_address' => $responsible_company_address,
                    'trainer_company_zip' => $responsible_company_zip,
                    'trainer_company_city' => $responsible_company_city,
                    'trainer_phone' => $responsible_phone,
                    'trainer_salutation' => $responsible_salutation,
                    'field_trainer_name' => '',
                    'field_trainer_email' => '',
                    'field_trainer_phone' => '',
                    'responsible_trainer_name' => $responsible_name,
                    'responsible_trainer_email' => $responsible_email,
                    'responsible_trainer_company' => $responsible_company,
                    'responsible_trainer_company_address' => $responsible_company_address,
                    'responsible_trainer_company_zip' => $responsible_company_zip,
                    'responsible_trainer_company_city' => $responsible_company_city,
                    'responsible_trainer_phone' => $responsible_phone,
                    'responsible_labels' => array(),
                    'cc_emails' => array(),
                    'students' => array(),
                );
            }

            if ($groups[$key]['trainer_company'] === '' && $responsible_company !== '') {
                $groups[$key]['trainer_company'] = $responsible_company;
            }
            if ($groups[$key]['trainer_company_address'] === '' && $responsible_company_address !== '') {
                $groups[$key]['trainer_company_address'] = $responsible_company_address;
            }
            if ($groups[$key]['trainer_company_zip'] === '' && $responsible_company_zip !== '') {
                $groups[$key]['trainer_company_zip'] = $responsible_company_zip;
            }
            if ($groups[$key]['trainer_company_city'] === '' && $responsible_company_city !== '') {
                $groups[$key]['trainer_company_city'] = $responsible_company_city;
            }
            foreach (array(
                'trainer_name' => $responsible_name,
                'trainer_phone' => $responsible_phone,
                'trainer_salutation' => $responsible_salutation,
                'responsible_trainer_name' => $responsible_name,
                'responsible_trainer_email' => $responsible_email,
                'responsible_trainer_company' => $responsible_company,
                'responsible_trainer_company_address' => $responsible_company_address,
                'responsible_trainer_company_zip' => $responsible_company_zip,
                'responsible_trainer_company_city' => $responsible_company_city,
                'responsible_trainer_phone' => $responsible_phone,
            ) as $field => $value) {
                if ($groups[$key][$field] === '' && trim((string) $value) !== '') {
                    $groups[$key][$field] = trim((string) $value);
                }
            }
            if ($responsible_label !== '') {
                $groups[$key]['responsible_labels'][$responsible_label] = $responsible_label;
            }

            $student->trainer_email = $email;
            $student->effective_trainer_email = $email;
            $student->effective_trainer_name = $responsible_name;
            $student->responsible_trainer_name = $responsible_name;
            $student->responsible_trainer_email = $responsible_email;
            $student->trainer_company_address = $responsible_company_address;
            $student->trainer_company_zip = $responsible_company_zip;
            $student->trainer_company_city = $responsible_company_city;
            if ($profile) {
                $student->trainer_id = (int) $profile->id;
                $student->trainer_name = (string) (($profile->name ?? '') ?: $student->trainer_name);
                $student->trainer_company = (string) (($profile->company ?? '') ?: $student->trainer_company);
                $student->trainer_company_address = (string) (($profile->company_address ?? '') ?: $student->trainer_company_address);
                $student->trainer_company_zip = (string) (($profile->company_zip ?? '') ?: $student->trainer_company_zip);
                $student->trainer_company_city = (string) (($profile->company_city ?? '') ?: $student->trainer_company_city);
                $student->trainer_phone = (string) (($profile->phone ?? '') ?: $student->trainer_phone);
            }
            $groups[$key]['students'][] = $student;
        }

        uasort(
            $groups,
            static function (array $left, array $right): int {
                return strnatcasecmp((string) ($left['trainer_name'] ?: $left['email']), (string) ($right['trainer_name'] ?: $right['email']));
            }
        );

        foreach ($groups as &$group) {
            $group['cc_emails'] = array_values($group['cc_emails'] ?? array());
            $group['responsible_labels'] = array_values($group['responsible_labels'] ?? array());
        }
        unset($group);

        return array_values($groups);
    }

    private function trainer_salutation_for_group(array $group): string
    {
        $salutation = trim((string) ($group['trainer_salutation'] ?? ''));
        if ($salutation !== '') {
            return $salutation;
        }

        return $this->default_trainer_salutation((string) ($group['trainer_name'] ?? ''));
    }

    private function trainer_salutation_for_student(object $student): string
    {
        global $wpdb;

        $trainer_id = (int) ($student->trainer_id ?? 0);
        if ($trainer_id > 0) {
            $profile = $wpdb->get_row($wpdb->prepare("SELECT name, salutation FROM " . GCA_Database::table('trainers') . " WHERE id = %d", $trainer_id));
            if ($profile) {
                if (trim((string) $profile->salutation) !== '') {
                    return trim((string) $profile->salutation);
                }

                if (trim((string) $profile->name) !== '') {
                    return $this->default_trainer_salutation((string) $profile->name);
                }
            }
        }

        $email = sanitize_email((string) ($student->trainer_email ?? ''));
        $name = trim((string) ($student->trainer_name ?? ''));
        if (is_email($email)) {
            $key = $this->trainer_key($email, $name, (string) ($student->trainer_company ?? ''), (string) ($student->trainer_phone ?? ''));
            $profile = $wpdb->get_row($wpdb->prepare("SELECT salutation FROM " . GCA_Database::table('trainers') . " WHERE trainer_key = %s", $key));
            if ($profile && trim((string) $profile->salutation) !== '') {
                return trim((string) $profile->salutation);
            }
        }

        return $this->default_trainer_salutation($name);
    }

    private function trainer_group_student_labels(array $group): array
    {
        $labels = array();
        foreach (($group['students'] ?? array()) as $student) {
            $name = trim((string) $student->first_name . ' ' . (string) $student->last_name);
            $class_code = $this->class_code_for_student($student);
            $labels[] = $name . ($class_code !== '' ? ' - ' . $class_code : '');
        }

        return array_values(array_filter($labels));
    }

    private function render_trainer_group_student_links(array $group): void
    {
        $students = $group['students'] ?? array();
        if (!$students) {
            echo '<span class="gca-muted">-</span>';
            return;
        }

        echo '<ul class="gca-linked-list">';
        foreach ($students as $student) {
            $name = $this->student_display_name($student);
            $class_label = $this->student_class_label($student);
            echo '<li>';
            echo $this->student_link_with_company($student);
            if ($class_label !== '') {
                echo ' <span class="gca-muted">· ';
                echo $this->class_link((int) ($student->class_id ?? 0), $class_label);
                echo '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    private function trainer_group_student_copy_emails(array $group): array
    {
        $emails = array();
        foreach (($group['students'] ?? array()) as $student) {
            $email = sanitize_email((string) ($student->email ?? ''));
            if (is_email($email)) {
                $emails[strtolower($email)] = $email;
            }
        }

        return array_values($emails);
    }

    private function trainer_group_responsible_labels(array $group): array
    {
        $labels = isset($group['responsible_labels']) && is_array($group['responsible_labels'])
            ? array_values(array_filter(array_map('sanitize_text_field', $group['responsible_labels'])))
            : array();

        if ($labels) {
            return array_values(array_unique($labels));
        }

        $name = trim((string) ($group['responsible_trainer_name'] ?? ''));
        $company = trim((string) ($group['responsible_trainer_company'] ?? ''));
        $label = $name;
        if ($label !== '' && $company !== '') {
            $label .= ' - ' . $company;
        } elseif ($label === '' && $company !== '') {
            $label = $company;
        }

        return $label !== '' ? array($label) : array('-');
    }

    private function trainer_group_responsible_copy_emails(array $group): array
    {
        return array();
    }

    private function trainer_group_student_count(array $groups): int
    {
        $count = 0;
        foreach ($groups as $group) {
            $count += count($group['students'] ?? array());
        }

        return $count;
    }

    private function trainer_group_student_copy_count(array $groups): int
    {
        $count = 0;
        foreach ($groups as $group) {
            $count += count($this->trainer_group_student_copy_emails($group));
        }

        return $count;
    }

    private function trainer_group_student_missing_copy_count(array $groups): int
    {
        $count = 0;
        foreach ($groups as $group) {
            foreach (($group['students'] ?? array()) as $student) {
                if (!is_email(sanitize_email((string) ($student->email ?? '')))) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function contract_break_students(int $class_id = 0): array
    {
        return $this->contract_break_students_for_class_ids($class_id > 0 ? array($class_id) : array());
    }

    private function contract_break_students_for_class_ids(array $class_ids): array
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $class_sql = $this->class_ids_sql_filter($class_ids);

        return $wpdb->get_results(
            "SELECT s.*, c.name AS class_name, c.school_year
             FROM {$students_table} s
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE s.archived_at IS NULL
               AND c.status = 'active'
               AND s.contract_status IN ('broken', 'free_candidate')
               {$class_sql}
             ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC"
        );
    }

    private function render_contract_break_alert(array $students): void
    {
        if (!$students) {
            return;
        }

        ?>
        <div class="gca-notice gca-notice-error">
            <strong>Apprenti sans employeur actif : aucun envoi formateur.</strong>
            <ul class="gca-notice-list">
                <?php foreach (array_slice($students, 0, 8) as $student) : ?>
                    <li><?php echo esc_html($this->contract_break_alert_text($student)); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($students) > 8) : ?>
                <p><?php echo esc_html('+' . (count($students) - 8) . ' autre(s) apprenti(s) sans employeur actif.'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function trainer_message_missing_email_count(int $class_id = 0): int
    {
        return $this->trainer_message_missing_email_count_for_class_ids($class_id > 0 ? array($class_id) : array());
    }

    private function trainer_message_missing_email_count_for_class_ids(array $class_ids): int
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $class_sql = $this->class_ids_sql_filter($class_ids);

        $rows = $wpdb->get_results(
            "SELECT s.trainer_email
             FROM {$students_table} s
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE s.archived_at IS NULL
               AND c.status = 'active'
               AND COALESCE(s.contract_status, 'active') NOT IN ('broken', 'free_candidate')
               {$class_sql}"
        );

        $missing = 0;
        foreach ($rows as $student) {
            if (!is_email(sanitize_email((string) ($student->trainer_email ?? '')))) {
                $missing++;
            }
        }

        return $missing;
    }

    private function student_message_recipients(int $class_id = 0): array
    {
        return $this->student_message_recipients_for_class_ids($class_id > 0 ? array($class_id) : array());
    }

    private function student_message_recipients_for_class_ids(array $class_ids): array
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $class_sql = $this->class_ids_sql_filter($class_ids);
        $rows = $wpdb->get_results(
            "SELECT s.*, c.name AS class_name, c.school_year
             FROM {$students_table} s
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE s.archived_at IS NULL
               AND c.status = 'active'
               AND TRIM(s.email) <> ''
               {$class_sql}
             ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC"
        );

        $students = array();
        foreach ($rows as $student) {
            $email = sanitize_email((string) $student->email);
            if (!is_email($email)) {
                continue;
            }

            $student->email = $email;
            $students[] = $student;
        }

        return $students;
    }

    private function student_message_missing_email_count(int $class_id = 0): int
    {
        return $this->student_message_missing_email_count_for_class_ids($class_id > 0 ? array($class_id) : array());
    }

    private function student_message_missing_email_count_for_class_ids(array $class_ids): int
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $class_sql = $this->class_ids_sql_filter($class_ids);
        $rows = $wpdb->get_results(
            "SELECT s.email
             FROM {$students_table} s
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE s.archived_at IS NULL
               AND c.status = 'active'
               {$class_sql}"
        );

        $missing = 0;
        foreach ($rows as $student) {
            $email = sanitize_email((string) $student->email);
            if (!is_email($email)) {
                $missing++;
            }
        }

        return $missing;
    }

    private function students_missing_trainer_email(int $class_id = 0): array
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $trainers_table = GCA_Database::table('trainers');
        $class_sql = $this->class_ids_sql_filter($class_id > 0 ? array($class_id) : array());

        $rows = $wpdb->get_results(
            "SELECT s.id, s.first_name, s.last_name, s.email, s.trainer_email,
                    COALESCE(NULLIF(t.name, ''), s.trainer_name) AS trainer_name,
                    COALESCE(NULLIF(t.company, ''), s.trainer_company) AS trainer_company,
                    c.id AS class_id, c.name AS class_name
             FROM {$students_table} s
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             LEFT JOIN {$trainers_table} t ON t.id = s.trainer_id
             WHERE s.archived_at IS NULL
               AND c.status = 'active'
               AND COALESCE(s.contract_status, 'active') NOT IN ('broken', 'free_candidate')
               {$class_sql}
             ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC"
        );

        return array_values(array_filter($rows, static function (object $student): bool {
            return !is_email(sanitize_email((string) ($student->trainer_email ?? '')));
        }));
    }

    private function class_ids_sql_filter(array $class_ids): string
    {
        global $wpdb;

        $class_ids = array_values(array_unique(array_filter(array_map('intval', $class_ids))));
        if (!$class_ids) {
            return '';
        }

        $placeholders = implode(', ', array_fill(0, count($class_ids), '%d'));
        return call_user_func_array(array($wpdb, 'prepare'), array_merge(array(" AND c.id IN ({$placeholders})"), $class_ids));
    }

    private function class_ids_from_post(): array
    {
        $raw = isset($_POST['class_ids']) && is_array($_POST['class_ids'])
            ? wp_unslash($_POST['class_ids'])
            : array();

        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    private function save_trainer_message_template(string $template_id, string $label, string $subject, string $body): string
    {
        $templates = GCA_Database::get_trainer_message_templates();
        $template_id = sanitize_key($template_id);
        $label = trim(sanitize_text_field($label));

        if ($label === '') {
            $label = 'Message préformaté';
        }

        if ($template_id === '' || !isset($templates[$template_id])) {
            $base = sanitize_key(remove_accents($label));
            $base = $base !== '' ? $base : 'message';
            $template_id = $base;
            $suffix = 2;
            while (isset($templates[$template_id])) {
                $template_id = $base . '_' . $suffix;
                $suffix++;
            }
        }

        $templates[$template_id] = array(
            'id' => $template_id,
            'label' => $label,
            'subject' => sanitize_text_field($subject),
            'body' => GCA_Database::sanitize_message_body($body),
        );

        GCA_Database::update_trainer_message_templates($templates);
        GCA_Database::log('trainer_template_saved', 'template', 0, array('template_id' => $template_id, 'label' => $label));

        return $template_id;
    }

    private function save_free_message_template(string $template_id, string $label, string $subject, string $body): string
    {
        $templates = GCA_Database::get_free_message_templates();
        $template_id = sanitize_key($template_id);
        $label = trim(sanitize_text_field($label));

        if ($label === '') {
            $label = 'Message libre';
        }

        if ($template_id === '' || !isset($templates[$template_id])) {
            $base = sanitize_key(remove_accents($label));
            $base = $base !== '' ? $base : 'message_libre';
            $template_id = $base;
            $suffix = 2;
            while (isset($templates[$template_id])) {
                $template_id = $base . '_' . $suffix;
                $suffix++;
            }
        }

        $templates[$template_id] = array(
            'id' => $template_id,
            'label' => $label,
            'subject' => sanitize_text_field($subject),
            'body' => GCA_Database::sanitize_message_body($body),
        );

        GCA_Database::update_free_message_templates($templates);
        GCA_Database::log('free_template_saved', 'template', 0, array('template_id' => $template_id, 'label' => $label));

        return $template_id;
    }

    private function replace_message_variables(string $text, array $values, array $multiline_keys = array()): string
    {
        $values = $this->message_variable_aliases($values) + $values;
        $has_html = $this->content_contains_html($text);
        $multiline_keys = array_flip($multiline_keys);

        foreach ($values as $key => $value) {
            $replacement = (string) $value;
            if ($has_html) {
                $replacement = esc_html($replacement);
                if (isset($multiline_keys[$key])) {
                    $replacement = str_replace("\n", '<br>', $replacement);
                }
            }

            $text = str_replace('{' . $key . '}', $replacement, $text);
            $text = preg_replace_callback(
                '/\{\{\s*' . preg_quote((string) $key, '/') . '\s*\}\}/u',
                static function () use ($replacement): string {
                    return $replacement;
                },
                $text
            ) ?? $text;
        }

        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }

    private function message_variable_aliases(array $values): array
    {
        $name_value = (string) ($values['full_name'] ?? $values['student_name'] ?? $values['apprentices_names'] ?? '');
        $class_value = (string) ($values['class_code'] ?? $values['class_name'] ?? $values['classes_list'] ?? '');
        $apprentices_list = (string) ($values['apprentices_list'] ?? '');

        $aliases = array(
            'Prénom Nom' => $name_value,
            'Prenom Nom' => $name_value,
            'Nom Prénom' => $name_value,
            'Nom Prenom' => $name_value,
            'Nom de l’apprenti' => $name_value,
            "Nom de l'apprenti" => $name_value,
            'Numéro de classe' => $class_value,
            'Numero de classe' => $class_value,
            'Numéro court de classe' => $class_value,
            'Numero court de classe' => $class_value,
            'Classe' => $class_value,
            'Liste des apprentis' => $apprentices_list,
            'Apprentis concernés' => $apprentices_list,
            'Apprentis concernes' => $apprentices_list,
        );

        return array_filter($aliases, static function (string $value): bool {
            return $value !== '';
        });
    }

    private function content_contains_html(string $content): bool
    {
        return trim(wp_strip_all_tags($content)) !== trim($content);
    }

    private function cleanup_empty_salutation(string $text): string
    {
        $text = preg_replace('/Bonjour(?:&nbsp;|\\s)+,/u', 'Bonjour,', $text) ?? $text;
        $text = preg_replace('/Bonjour(?:&nbsp;|\\s)+<\\/p>/u', 'Bonjour</p>', $text) ?? $text;

        return $text;
    }

    private function expand_apprentice_placeholder_rows(string $text, array $students): string
    {
        if (!$students || !$this->text_contains_apprentice_placeholder($text)) {
            return $text;
        }

        $aliases = $this->apprentice_placeholder_alias_pattern();
        if ($this->content_contains_html($text)) {
            $html_list = $this->apprentice_placeholder_html_list($students);
            $human_placeholder_pattern = '\{\{\s*(?:' . $aliases . ')\s*\}\}';
            $text = $this->unwrap_placeholder_inline_formatting($text, $human_placeholder_pattern);

            foreach (array('ul', 'ol') as $tag) {
                $text = preg_replace_callback(
                    '/<' . $tag . '\b[^>]*>.*?\{\{\s*(?:' . $aliases . ')\s*\}\}.*?<\/' . $tag . '>/isu',
                    static function () use ($html_list): string {
                        return $html_list;
                    },
                    $text
                ) ?? $text;
            }
            $text = preg_replace_callback(
                '/<pre\b[^>]*>.*?\{\{\s*(?:' . $aliases . ')\s*\}\}.*?<\/pre>/isu',
                static function () use ($html_list): string {
                    return $html_list;
                },
                $text
            ) ?? $text;
            $text = preg_replace_callback(
                '/<code\b[^>]*>(.*?)\{\{\s*(?:' . $aliases . ')\s*\}\}(.*?)<\/code>/isu',
                static function (array $matches): string {
                    return wp_strip_all_tags($matches[0]);
                },
                $text
            ) ?? $text;
            foreach (array('p', 'div') as $tag) {
                $text = preg_replace_callback(
                    '/(?:<' . $tag . '\b[^>]*>(?:(?!<\/' . $tag . '>).)*?\{\{\s*(?:' . $aliases . ')\s*\}\}(?:(?!<\/' . $tag . '>).)*?<\/' . $tag . '>\s*)+/isu',
                    static function () use ($html_list): string {
                        return $html_list;
                    },
                    $text
                ) ?? $text;
            }

            return $text;
        }

        $plain_list = $this->apprentice_placeholder_plain_list($students);
        return preg_replace_callback(
            '/(?:^[^\r\n]*\{\{\s*(?:' . $aliases . ')\s*\}\}[^\r\n]*(?:\R|$))+/mu',
            static function () use ($plain_list): string {
                return $plain_list . "\n";
            },
            $text
        ) ?? $text;
    }

    private function text_contains_apprentice_placeholder(string $text): bool
    {
        return preg_match('/\{\{\s*(?:' . $this->apprentice_placeholder_alias_pattern() . ')\s*\}\}/u', $text) === 1;
    }

    private function apprentice_placeholder_alias_pattern(): string
    {
        $aliases = array(
            'Prénom Nom',
            'Prenom Nom',
            'Nom Prénom',
            'Nom Prenom',
            'Nom de l’apprenti',
            "Nom de l'apprenti",
            'Numéro de classe',
            'Numero de classe',
            'Numéro court de classe',
            'Numero court de classe',
        );

        return implode('|', array_map(static function (string $alias): string {
            return preg_quote($alias, '/');
        }, $aliases));
    }

    private function apprentice_placeholder_plain_list(array $students): string
    {
        $lines = array();
        foreach ($students as $student) {
            $line = $this->apprentice_placeholder_line($student);
            if ($line !== '') {
                $lines[] = '- ' . $line;
            }
        }

        return implode("\n", $lines);
    }

    private function apprentice_placeholder_html_list(array $students): string
    {
        $items = array();
        foreach ($students as $student) {
            $line = $this->apprentice_placeholder_line($student);
            if ($line !== '') {
                $items[] = '<li style="font-family:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;">' . esc_html($line) . '</li>';
            }
        }

        return '<ul style="font-family:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;margin:0 0 12px 22px;padding:0;">' . implode('', $items) . '</ul>';
    }

    private function apprentice_placeholder_line(object $student): string
    {
        $class_code = $this->class_code_for_student($student);
        $class_label = $class_code !== '' ? $class_code : trim((string) ($student->class_name ?? ''));
        $name = trim((string) $student->first_name . ' ' . (string) $student->last_name);

        return trim($name . ($class_label !== '' ? ' - classe ' . $class_label : ''));
    }

    private function normalize_multiline_variable_markup(string $text, array $keys, array $lines): string
    {
        if (!$lines || !$this->content_contains_html($text)) {
            return $text;
        }

        $placeholder_pattern = $this->message_placeholder_pattern($keys);
        $normal_block = $this->normal_text_lines_html($lines);
        $text = $this->unwrap_placeholder_inline_formatting($text, $placeholder_pattern);

        $text = preg_replace_callback(
            '/<pre\b[^>]*>.*?' . $placeholder_pattern . '.*?<\/pre>/isu',
            static function () use ($normal_block): string {
                return $normal_block;
            },
            $text
        ) ?? $text;

        foreach (array('p', 'div') as $tag) {
            $text = preg_replace_callback(
                '/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/isu',
                static function (array $matches) use ($placeholder_pattern, $normal_block): string {
                    $plain = html_entity_decode(wp_strip_all_tags((string) $matches[1]), ENT_QUOTES, 'UTF-8');
                    if (preg_match('/^\s*(?:[-*•]\s*)?' . $placeholder_pattern . '\s*$/u', $plain) === 1) {
                        return $normal_block;
                    }

                    return $matches[0];
                },
                $text
            ) ?? $text;
        }

        return preg_replace_callback(
            '/<code\b[^>]*>(.*?)(' . $placeholder_pattern . ')(.*?)<\/code>/isu',
            static function (array $matches): string {
                return $matches[1] . $matches[2] . $matches[3];
            },
            $text
        ) ?? $text;
    }

    private function unwrap_placeholder_inline_formatting(string $text, string $placeholder_pattern): string
    {
        foreach (array('code', 'span', 'font') as $tag) {
            $text = preg_replace_callback(
                '/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/isu',
                static function (array $matches) use ($placeholder_pattern): string {
                    $plain = html_entity_decode(wp_strip_all_tags((string) $matches[1]), ENT_QUOTES, 'UTF-8');
                    if (preg_match('/' . $placeholder_pattern . '/u', $plain) !== 1) {
                        return $matches[0];
                    }

                    return wp_strip_all_tags($matches[0]);
                },
                $text
            ) ?? $text;
        }

        return $text;
    }

    private function message_placeholder_pattern(array $keys): string
    {
        $patterns = array();
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $quoted = preg_quote($key, '/');
            $patterns[] = '\{' . $quoted . '\}';
            $patterns[] = '\{\{\s*' . $quoted . '\s*\}\}';
        }

        return '(?:' . implode('|', $patterns) . ')';
    }

    private function normal_text_lines_html(array $lines): string
    {
        $clean = array();
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $clean[] = esc_html($line);
            }
        }

        return '<p style="font-family:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;margin:0 0 12px 0;">' . implode('<br>', $clean) . '</p>';
    }

    private function replace_trainer_bulk_variables(string $text, array $group): string
    {
        $students = $group['students'] ?? array();
        $lines = array();
        $classes = array();

        foreach ($students as $student) {
            $class_code = $this->class_code_for_student($student);
            $classes[] = $class_code !== '' ? $class_code : (string) ($student->class_name ?? '');
            $name = trim((string) $student->first_name . ' ' . (string) $student->last_name);
            $lines[] = '- ' . $name . ($class_code !== '' ? ' - ' . $class_code : '');
        }

        $text = $this->expand_apprentice_placeholder_rows($text, $students);
        $text = $this->normalize_multiline_variable_markup($text, array('apprentices_list', 'Liste des apprentis', 'Apprentis concernés', 'Apprentis concernes'), $lines);

        $classes = array_values(array_unique(array_filter(array_map('trim', $classes))));
        $trainer_name = trim((string) ($group['trainer_name'] ?? ''));
        $trainer_salutation = $this->trainer_salutation_for_group($group);
        $group_address_record = (object) array(
            'trainer_company_address' => (string) ($group['trainer_company_address'] ?? ''),
            'trainer_company_zip' => (string) ($group['trainer_company_zip'] ?? ''),
            'trainer_company_city' => (string) ($group['trainer_company_city'] ?? ''),
        );
        $company_full_address = $this->trainer_company_address_label($group_address_record);
        $values = array(
            'recipient_name' => $trainer_salutation,
            'trainer_salutation' => $trainer_salutation,
            'trainer_name' => $trainer_name,
            'trainer_company' => (string) ($group['trainer_company'] ?? ''),
            'trainer_company_address' => (string) ($group['trainer_company_address'] ?? ''),
            'trainer_company_zip' => (string) ($group['trainer_company_zip'] ?? ''),
            'trainer_company_city' => (string) ($group['trainer_company_city'] ?? ''),
            'trainer_company_full_address' => $company_full_address,
            'trainer_email' => (string) ($group['email'] ?? ''),
            'trainer_phone' => (string) ($group['trainer_phone'] ?? ''),
            'field_trainer_name' => (string) ($group['field_trainer_name'] ?? ''),
            'field_trainer_email' => (string) ($group['field_trainer_email'] ?? ''),
            'field_trainer_phone' => (string) ($group['field_trainer_phone'] ?? ''),
            'responsible_trainer_name' => (string) ($group['responsible_trainer_name'] ?? ''),
            'responsible_trainer_company' => (string) ($group['responsible_trainer_company'] ?? ''),
            'responsible_trainer_company_address' => (string) ($group['responsible_trainer_company_address'] ?? ''),
            'responsible_trainer_company_zip' => (string) ($group['responsible_trainer_company_zip'] ?? ''),
            'responsible_trainer_company_city' => (string) ($group['responsible_trainer_company_city'] ?? ''),
            'responsible_trainer_company_full_address' => $company_full_address,
            'responsible_trainer_email' => (string) ($group['responsible_trainer_email'] ?? ''),
            'responsible_trainer_phone' => (string) ($group['responsible_trainer_phone'] ?? ''),
            'apprentices_count' => (string) count($students),
            'apprentices_list' => implode("\n", $lines),
            'apprentices_names' => implode(', ', array_map(static function (object $student): string {
                return trim((string) $student->first_name . ' ' . (string) $student->last_name);
            }, $students)),
            'classes_list' => implode(', ', $classes),
            'today' => wp_date('d.m.Y'),
        );

        return $this->cleanup_empty_salutation($this->replace_message_variables($text, $values, array('apprentices_list')));
    }

    private function replace_student_bulk_variables(string $text, object $student): string
    {
        $text = $this->expand_apprentice_placeholder_rows($text, array($student));
        $class_code = $this->class_code_for_student($student);
        $class_label = $class_code !== '' ? $class_code : trim((string) ($student->class_name ?? ''));
        $full_name = trim((string) $student->first_name . ' ' . (string) $student->last_name);
        $first_name = trim((string) $student->first_name);
        $recipient_name = $first_name !== '' ? $first_name : ($full_name !== '' ? $full_name : 'Madame, Monsieur');
        $apprentice_line = '- ' . $full_name . ($class_label !== '' ? ' - ' . $class_label : '');
        $text = $this->normalize_multiline_variable_markup($text, array('apprentices_list', 'Liste des apprentis', 'Apprentis concernés', 'Apprentis concernes'), array($apprentice_line));
        $values = array(
            'recipient_name' => $recipient_name,
            'first_name' => $first_name,
            'last_name' => trim((string) $student->last_name),
            'full_name' => $full_name,
            'student_name' => $full_name,
            'student_email' => (string) $student->email,
            'class_name' => trim((string) ($student->class_name ?? '')),
            'class_code' => $class_code,
            'lateness_count' => (string) (int) ($student->lateness_count ?? 0),
            'absence_count' => $this->format_decimal($this->actionable_absence_count($student)),
            'absence_rate' => $this->format_decimal($this->actionable_absence_rate($student)),
            'absence_imported_count' => $this->format_decimal($student->absence_count ?? 0),
            'absence_justified_count' => $this->format_decimal($this->absence_justified_count($student)),
            'removal_count' => (string) (int) ($student->removal_count ?? 0),
            'comment_count' => (string) (int) ($student->comment_count ?? 0),
            'classes_list' => $class_label,
            'apprentices_count' => '1',
            'apprentices_list' => $apprentice_line,
            'apprentices_names' => $full_name,
            'trainer_name' => $recipient_name,
            'trainer_email' => (string) $student->email,
            'today' => wp_date('d.m.Y'),
        );

        return $this->replace_message_variables($text, $values, array('apprentices_list'));
    }

    private function bulk_test_preview_payload(string $subject_template, string $body_template, array $class_ids, string $recipient_mode): array
    {
        $group = $this->bulk_test_trainer_group($class_ids);
        $trainer_subject = $this->replace_trainer_bulk_variables($subject_template, $group);
        $trainer_body = $this->replace_trainer_bulk_variables($body_template, $group);
        $body = '<p style="margin:0 0 12px 0;"><strong>E-mail de test.</strong> Aucun destinataire réel de la classe n’a reçu ce message.</p>'
            . '<p style="margin:0 0 12px 0;"><strong>Aperçu du message formateur</strong></p>';

        if ($recipient_mode === 'trainers_students') {
            $copy_emails = $this->trainer_group_student_copy_emails($group);
            $body .= '<p style="margin:0 0 12px 0;">En envoi réel, les élèves rattachés au formateur seraient placés en copie : '
                . esc_html($copy_emails ? implode(', ', $copy_emails) : 'aucun e-mail élève valide') . '.</p>';
        }

        $body .= $this->email_content_html($trainer_body);

        return array(
            'subject' => $this->test_subject($trainer_subject),
            'body' => $body,
        );
    }

    private function test_subject(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            $subject = 'E-mail individuel formateur';
        }

        return str_starts_with($subject, '[TEST]') ? $subject : '[TEST] ' . $subject;
    }

    private function bulk_test_trainer_group(array $class_ids): array
    {
        $groups = $this->trainer_message_groups_for_class_ids($class_ids);
        if ($groups) {
            return $groups[0];
        }

        $students = $this->sample_students_for_class_ids($class_ids, 3);
        if (!$students) {
            $students = array($this->fallback_bulk_test_student());
        }

        return array(
            'trainer_key' => $this->trainer_key('responsable.test@example.ch'),
            'email' => 'responsable.test@example.ch',
            'trainer_name' => 'Responsable test',
            'trainer_company' => 'Entreprise test',
            'trainer_company_address' => 'Rue de Test 1',
            'trainer_company_zip' => '1000',
            'trainer_company_city' => 'Lausanne',
            'trainer_phone' => '',
            'trainer_salutation' => 'Madame, Monsieur',
            'field_trainer_name' => '',
            'field_trainer_email' => '',
            'field_trainer_phone' => '',
            'responsible_trainer_name' => 'Responsable test',
            'responsible_trainer_email' => 'responsable.test@example.ch',
            'responsible_trainer_company' => 'Entreprise test',
            'responsible_trainer_company_address' => 'Rue de Test 1',
            'responsible_trainer_company_zip' => '1000',
            'responsible_trainer_company_city' => 'Lausanne',
            'responsible_trainer_phone' => '',
            'responsible_labels' => array('Responsable test - Entreprise test'),
            'cc_emails' => array(),
            'students' => $students,
        );
    }

    private function bulk_test_student(array $class_ids, array $group): object
    {
        $group_students = $group['students'] ?? array();
        if ($group_students) {
            return $group_students[0];
        }

        $students = $this->sample_students_for_class_ids($class_ids, 1);
        return $students ? $students[0] : $this->fallback_bulk_test_student();
    }

    private function sample_students_for_class_ids(array $class_ids, int $limit): array
    {
        global $wpdb;

        $limit = max(1, min(10, $limit));
        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $class_sql = $this->class_ids_sql_filter($class_ids);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, c.name AS class_name, c.school_year
                 FROM {$students_table} s
                 INNER JOIN {$classes_table} c ON c.id = s.class_id
                 WHERE s.archived_at IS NULL
                   AND c.status = 'active'
                   {$class_sql}
                 ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC
                 LIMIT %d",
                $limit
            )
        );
    }

    private function fallback_bulk_test_student(): object
    {
        return (object) array(
            'id' => 0,
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'email' => 'eleve.test@example.ch',
            'class_name' => 'CLLOG-C1a',
            'school_year' => GCA_Database::current_school_year(),
            'trainer_name' => 'Responsable test',
            'trainer_company' => 'Entreprise test',
            'trainer_company_address' => 'Rue de Test 1',
            'trainer_company_zip' => '1000',
            'trainer_company_city' => 'Lausanne',
            'trainer_email' => 'responsable.test@example.ch',
            'trainer_phone' => '',
            'field_trainer_name' => '',
            'field_trainer_email' => '',
            'field_trainer_phone' => '',
        );
    }

    private function executed_actions_by_class(int $class_id = 0): array
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $communications_table = GCA_Database::table('communications');
        $events_table = GCA_Database::table('events');
        $groups = array();

        $class_sql = $class_id > 0 ? $wpdb->prepare(' AND c.id = %d', $class_id) : '';
        $communications = $wpdb->get_results(
            "SELECT m.*, s.first_name, s.last_name, s.trainer_company, c.id AS class_id, c.name AS class_name, c.school_year
             FROM {$communications_table} m
             INNER JOIN {$students_table} s ON s.id = m.student_id
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE 1 = 1{$class_sql}
             ORDER BY c.name ASC, m.sent_at DESC
             LIMIT 300"
        );

        foreach ($communications as $communication) {
            $entry = $this->action_entry_from_communication($communication);
            $entry['student_id'] = (int) $communication->student_id;
            $entry['student_name'] = trim((string) $communication->first_name . ' ' . (string) $communication->last_name);
            $entry['student_company'] = trim((string) ($communication->trainer_company ?? ''));
            $this->add_class_action_group_entry($groups, $communication, $entry);
        }

        $event_types = "'warning_confirmed','exclusion_confirmed','detention_no_show','absence_justified','task_resolved','student_archived','disciplinary_warning_recorded','disciplinary_warning_removed','disciplinary_exclusion_recorded','disciplinary_exclusion_updated','disciplinary_exclusion_deleted','expulsion_recorded','expulsion_removed','wellbeing_council_recorded','wellbeing_council_removed'";
        $events = $wpdb->get_results(
            "SELECT e.*, s.first_name, s.last_name, s.trainer_company, c.id AS class_id, c.name AS class_name, c.school_year
             FROM {$events_table} e
             INNER JOIN {$students_table} s ON s.id = e.student_id
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE e.event_type IN ({$event_types}){$class_sql}
             ORDER BY c.name ASC, e.created_at DESC
             LIMIT 300"
        );

        foreach ($events as $event) {
            $entry = $this->action_entry_from_event($event);
            $entry['student_id'] = (int) $event->student_id;
            $entry['student_name'] = trim((string) $event->first_name . ' ' . (string) $event->last_name);
            $entry['student_company'] = trim((string) ($event->trainer_company ?? ''));
            $this->add_class_action_group_entry($groups, $event, $entry);
        }

        foreach ($groups as $key => $group) {
            usort(
                $group['actions'],
                static function (array $left, array $right): int {
                    return (int) $right['timestamp'] <=> (int) $left['timestamp'];
                }
            );
            $groups[$key]['actions'] = $group['actions'];
        }

        uasort(
            $groups,
            static function (array $left, array $right): int {
                return strnatcasecmp((string) $left['class_name'], (string) $right['class_name']);
            }
        );

        return $groups;
    }

    private function executed_action_count(int $class_id = 0): int
    {
        global $wpdb;

        $students_table = GCA_Database::table('students');
        $classes_table = GCA_Database::table('classes');
        $communications_table = GCA_Database::table('communications');
        $events_table = GCA_Database::table('events');
        $class_sql = $class_id > 0 ? $wpdb->prepare(' AND c.id = %d', $class_id) : '';
        $event_types = "'warning_confirmed','exclusion_confirmed','detention_no_show','absence_justified','task_resolved','student_archived','disciplinary_warning_recorded','disciplinary_warning_removed','disciplinary_exclusion_recorded','disciplinary_exclusion_updated','disciplinary_exclusion_deleted','expulsion_recorded','expulsion_removed','wellbeing_council_recorded','wellbeing_council_removed'";

        $communications_count = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$communications_table} m
             INNER JOIN {$students_table} s ON s.id = m.student_id
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE 1 = 1{$class_sql}"
        );

        $events_count = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$events_table} e
             INNER JOIN {$students_table} s ON s.id = e.student_id
             INNER JOIN {$classes_table} c ON c.id = s.class_id
             WHERE e.event_type IN ({$event_types}){$class_sql}"
        );

        return $communications_count + $events_count;
    }

    private function action_allows_detention_no_show(array $action): bool
    {
        if (($action['source'] ?? '') !== 'communication') {
            return false;
        }

        return in_array((string) ($action['communication_type'] ?? ''), array('lateness_3', 'lateness_6'), true);
    }

    private function add_class_action_group_entry(array &$groups, object $row, array $entry): void
    {
        $class_id = (int) $row->class_id;
        if (!isset($groups[$class_id])) {
            $groups[$class_id] = array(
                'class_id' => $class_id,
                'class_name' => (string) $row->class_name,
                'school_year' => (string) $row->school_year,
                'actions' => array(),
            );
        }

        $groups[$class_id]['actions'][] = $entry;
    }

    private function compatible_import_mapping_profile(string $type, array $headers, array $fallback_mapping, int $teacher_id): array
    {
        $profile = GCA_Database::get_import_mapping_profile($type, $teacher_id);

        if (!$profile || empty($profile['mapping']) || !is_array($profile['mapping'])) {
            return array();
        }

        $header_lookup = array();
        foreach ($headers as $header) {
            $header_lookup[(string) $header] = true;
        }

        $fields = $this->importer->field_definitions($type);
        $saved_mapping = (array) $profile['mapping'];
        $mapping = $fallback_mapping;

        foreach ($fields as $field => $definition) {
            if (!array_key_exists($field, $saved_mapping)) {
                continue;
            }

            $header = (string) $saved_mapping[$field];
            if ($header === '' || $header === '__ignore') {
                $mapping[$field] = '__ignore';
                continue;
            }

            if (!isset($header_lookup[$header])) {
                return array();
            }

            $mapping[$field] = $header;
        }

        foreach ($fields as $field => $definition) {
            if (!empty($definition['required']) && (empty($mapping[$field]) || $mapping[$field] === '__ignore')) {
                return array();
            }
        }

        $profile['mapping'] = $mapping;

        return $profile;
    }

    private function default_import_mapping_label(string $type): string
    {
        return 'Mapping ' . $this->import_type_label($type);
    }

    private function render_import_mapping(string $token): void
    {
        $payload = get_transient('gca_import_' . $token);

        if (!is_array($payload) || empty($payload['path']) || empty($payload['headers'])) {
            echo '<p class="gca-empty">Import introuvable ou expire.</p>';
            return;
        }

        $type = (string) $payload['type'];
        $fields = $this->importer->field_definitions($type);
        $headers = array_map('strval', $payload['headers']);
        $mapping = is_array($payload['mapping']) ? $payload['mapping'] : array();
        $preview = is_array($payload['preview']) ? $payload['preview'] : array();
        $mapping_source = sanitize_key((string) ($payload['mapping_source'] ?? 'detected'));
        $saved_mapping_label = sanitize_text_field((string) ($payload['saved_mapping_label'] ?? ''));
        $mapping_label = $saved_mapping_label !== '' ? $saved_mapping_label : $this->default_import_mapping_label($type);
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'imports'))); ?>">&larr; Imports</a>
                    <h2>Mapping des colonnes</h2>
                </div>
                <span class="gca-chip"><?php echo esc_html($payload['filename']); ?></span>
            </div>

            <?php if ($type === 'lagap') : ?>
                <div class="gca-notice gca-notice-success">Préréglage Lagap-EP détecté : les compteurs seront remplacés par les valeurs officielles de l’export.</div>
            <?php endif; ?>

            <?php if ($type === 'grades') : ?>
                <div class="gca-notice gca-notice-success">Import de notes : la classe, le nom et le prénom servent uniquement au rattachement. Seules l’évaluation et la note obtenue seront récupérées.</div>
            <?php endif; ?>

            <?php if ($mapping_source === 'saved') : ?>
                <div class="gca-notice gca-notice-success">Mapping sauvegardé appliqué : <?php echo esc_html($mapping_label); ?>.</div>
            <?php endif; ?>

            <form class="gca-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gca_import_commit_' . $token); ?>
                <input type="hidden" name="action" value="gca_import_commit">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="import_type" value="<?php echo esc_attr($type); ?>">
                <input type="hidden" name="import_mode" value="<?php echo esc_attr($payload['mode']); ?>">

                <div class="gca-mapping-grid">
                    <?php foreach ($fields as $field => $definition) : ?>
                        <label>
                            <?php echo esc_html($definition['label']); ?>
                            <?php if (!empty($definition['required'])) : ?><span class="gca-required">*</span><?php endif; ?>
                            <select name="mapping[<?php echo esc_attr($field); ?>]">
                                <option value="__ignore">Ne pas importer</option>
                                <?php foreach ($headers as $header) : ?>
                                    <option value="<?php echo esc_attr($header); ?>" <?php selected($mapping[$field] ?? '', $header); ?>>
                                        <?php echo esc_html($header); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label>Gestion des doublons
                    <select name="duplicate_mode">
                        <option value="update">Mettre à jour la fiche existante</option>
                        <option value="ignore">Ignorer la ligne existante</option>
                    </select>
                </label>

                <div class="gca-form-section">
                    <h3>Mapping sauvegardé</h3>
                    <label class="gca-check">
                        <input type="checkbox" name="save_mapping" value="1" <?php checked($mapping_source !== 'saved'); ?>>
                        Sauvegarder ce mapping pour mes prochains imports de ce type
                    </label>
                    <label>Nom du mapping
                        <input type="text" name="mapping_label" value="<?php echo esc_attr($mapping_label); ?>">
                    </label>
                    <p class="gca-muted">Ce mapping est lié à votre profil prof. Il sera repris automatiquement uniquement si les colonnes du prochain fichier correspondent.</p>
                </div>

                <button class="gca-button gca-button-primary" type="submit">Valider l’import</button>
            </form>

            <div class="gca-preview">
                <h3>Aperçu</h3>
                <?php $this->render_preview_table($preview, $headers); ?>
            </div>
        </section>
        <?php
    }

    private function render_preview_table(array $rows, array $headers): void
    {
        if (!$rows) {
            echo '<p class="gca-empty">Aucun aperçu disponible.</p>';
            return;
        }
        ?>
        <div class="gca-table-wrap">
            <table class="gca-table gca-table-compact">
                <thead>
                    <tr>
                        <?php foreach (array_slice($headers, 0, 8) as $header) : ?>
                            <th><?php echo esc_html($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($rows, 0, 8) as $row) : ?>
                        <tr>
                            <?php foreach (array_slice($headers, 0, 8) as $header) : ?>
                                <td><?php echo esc_html(wp_trim_words((string) ($row[$header] ?? ''), 12)); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_settings(): void
    {
        $settings = GCA_Database::get_settings();
        $templates = GCA_Database::get_email_templates();
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title"><h2>Paramètres</h2></div>
            <form class="gca-form gca-form-wide" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gca_save_settings'); ?>
                <input type="hidden" name="action" value="gca_save_settings">

                <div class="gca-form-section">
                    <h3>Seuils et destinataires</h3>
                    <div class="gca-form-grid">
                        <label>Année scolaire
                            <input type="text" name="settings[school_year]" value="<?php echo esc_attr($settings['school_year']); ?>">
                        </label>
                        <label>Seuil 1 retards
                            <input type="number" min="1" name="settings[lateness_threshold_1]" value="<?php echo esc_attr((string) (int) $settings['lateness_threshold_1']); ?>">
                        </label>
                        <label>Seuil 2 retards
                            <input type="number" min="1" name="settings[lateness_threshold_2]" value="<?php echo esc_attr((string) (int) $settings['lateness_threshold_2']); ?>">
                        </label>
                        <label>Seuil Doyen
                            <input type="number" min="1" name="settings[lateness_threshold_dean]" value="<?php echo esc_attr((string) (int) $settings['lateness_threshold_dean']); ?>">
                        </label>
                        <label>Seuil absences non justifiées
                            <input type="number" min="1" step="1" name="settings[absence_unjustified_days_threshold]" value="<?php echo esc_attr((string) (int) ($settings['absence_unjustified_days_threshold'] ?? 21)); ?>">
                        </label>
                        <label>Seuil critique absences (%)
                            <input type="number" min="1" max="100" step="0.01" name="settings[absence_critical_rate_threshold]" value="<?php echo esc_attr($this->format_decimal($settings['absence_critical_rate_threshold'] ?? 30)); ?>">
                        </label>
                        <label>Début des cours
                            <input type="text" name="settings[school_start_date]" value="<?php echo esc_attr($this->display_date_only((string) $settings['school_start_date'])); ?>" placeholder="JJ.MM.AAAA" inputmode="numeric">
                        </label>
                        <label>Délai de démarrage absences (jours)
                            <input type="number" min="0" max="120" name="settings[absence_grace_days]" value="<?php echo esc_attr((string) (int) $settings['absence_grace_days']); ?>">
                        </label>
                        <label>Périodes d’absence minimales avant alerte
                            <input type="number" min="0" step="0.5" name="settings[absence_min_count_before_alert]" value="<?php echo esc_attr($this->format_decimal($settings['absence_min_count_before_alert'])); ?>">
                        </label>
                        <label>Emails Doyen
                            <input type="text" name="settings[dean_emails]" value="<?php echo esc_attr($settings['dean_emails']); ?>" placeholder="doyen@ecole.ch">
                        </label>
                        <label>Nom d’expéditeur
                            <input type="text" name="settings[email_sender_name]" value="<?php echo esc_attr($settings['email_sender_name']); ?>" placeholder="CPNV">
                        </label>
                        <label>E-mail d’expédition
                            <input type="email" name="settings[email_sender_email]" value="<?php echo esc_attr($settings['email_sender_email']); ?>" placeholder="prenom.nom@ecole.ch">
                        </label>
                        <label>E-mail de réponse
                            <input type="email" name="settings[email_reply_to_email]" value="<?php echo esc_attr($settings['email_reply_to_email'] ?? ''); ?>" placeholder="prenom.nom@eduvaud.ch">
                        </label>
                    </div>
                    <p class="gca-muted">Une alerte d’absence est créée si le délai de démarrage est passé, si le nombre minimal de périodes est atteint, puis si l’apprenti dépasse le seuil d’absences non justifiées ou atteint le seuil critique en pourcentage. Pour ces alertes, vous pouvez justifier sans e-mail, écrire au responsable avec copie à l’apprenti, ou écrire au Doyen. Le plugin force le nom et l’e-mail d’expédition définis ici ; la configuration SMTP doit donc autoriser cette adresse.</p>
                    <p class="gca-muted">Les convocations, rapports et e-mails individuels aux formateurs partent uniquement après une action d’envoi manuelle depuis le formulaire correspondant.</p>
                </div>

                <div class="gca-form-section">
                    <h3>Rappels internes</h3>
                    <div class="gca-form-grid">
                        <label>E-mail(s) de rappel interne
                            <input type="text" name="settings[reminder_emails]" value="<?php echo esc_attr((string) $settings['reminder_emails']); ?>" placeholder="prenom.nom@eduvaud.ch">
                        </label>
                        <label>Délai actions en attente (jours)
                            <input type="number" min="1" max="30" name="settings[stale_task_days]" value="<?php echo esc_attr((string) max(1, (int) $settings['stale_task_days'])); ?>">
                        </label>
                        <label>Délai import Lagap (jours)
                            <input type="number" min="1" max="365" name="settings[import_reminder_days]" value="<?php echo esc_attr((string) max(1, (int) ($settings['import_reminder_days'] ?? 8))); ?>">
                        </label>
                        <label>Heure du contrôle quotidien
                            <input type="time" name="settings[stale_task_reminder_time]" value="<?php echo esc_attr($this->sanitize_time_setting((string) $settings['stale_task_reminder_time'], '08:15')); ?>">
                        </label>
                    </div>
                    <label class="gca-check">
                        <input type="checkbox" name="settings[stale_task_reminder_enabled]" value="1" <?php checked((int) $settings['stale_task_reminder_enabled'], 1); ?>>
                        Rappel si des actions restent en attente
                    </label>
                    <label class="gca-check">
                        <input type="checkbox" name="settings[import_reminder_enabled]" value="1" <?php checked((int) ($settings['import_reminder_enabled'] ?? 1), 1); ?>>
                        Rappel si le dernier import Lagap date de plus que le délai indiqué
                    </label>
                    <p class="gca-muted">Ces rappels sont destinés au suivi interne. Si le champ e-mail est vide, le plugin utilise l’e-mail de réponse, puis l’e-mail d’expédition, puis l’e-mail administrateur du site.</p>
                </div>

                <div class="gca-form-section">
                    <h3>Variables disponibles</h3>
                    <p class="gca-muted">{recipient_name}, {full_name}, {first_name}, {last_name}, {class_name}, {class_code}, {lateness_count}, {lateness_total}, {lateness_threshold}, {lateness_previous_threshold}, {lateness_additional_count}, {absence_count}, {absence_rate}, {absence_imported_count}, {absence_justified_count}, {removal_count}, {comment_count}, {today}, {convocation_date}, {first_convocation_date}, {detention_no_show_note}, {trainer_salutation}, {trainer_name}, {trainer_company}, {trainer_company_address}, {trainer_company_zip}, {trainer_company_city}, {trainer_company_full_address}, {trainer_email}, {trainer_phone}, {responsible_trainer_name}, {responsible_trainer_company}, {responsible_trainer_company_address}, {responsible_trainer_company_zip}, {responsible_trainer_company_city}, {responsible_trainer_company_full_address}, {responsible_trainer_email}, {responsible_trainer_phone}, {contract_status}, {contract_break_date}, {contract_follow_until}, {contract_review_date}, {contract_responsible_email}, {student_name}, {student_email}, {apprentices_list}, {apprentices_count}, {classes_list}, {threshold}, {gender_article}, {gender_article_cap}, {gender_pronoun}, {gender_participle}</p>
                </div>

                <div class="gca-form-section">
                    <h3>Signature des messages</h3>
                    <?php $this->render_rich_text_editor('settings[message_signature_text]', (string) $settings['message_signature_text'], array('rows' => 6, 'label' => 'Texte de signature formaté')); ?>
                    <label>Image de signature
                        <input type="file" name="message_signature_image" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <?php if (!empty($settings['message_signature_image_url'])) : ?>
                        <div class="gca-signature-preview">
                            <img src="<?php echo esc_url((string) $settings['message_signature_image_url']); ?>" alt="Signature">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="gca-form-section">
                    <h3>Modèles d'e-mails</h3>
                    <?php foreach ($templates as $key => $value) : ?>
                        <?php if (str_ends_with($key, '_body')) : ?>
                            <?php $this->render_rich_text_editor('templates[' . $key . ']', (string) $value, array('rows' => 6, 'label' => $this->template_label($key))); ?>
                        <?php else : ?>
                            <label><?php echo esc_html($this->template_label($key)); ?>
                                <input type="text" name="templates[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <button class="gca-button gca-button-primary" type="submit">Enregistrer les paramètres</button>
            </form>
        </section>
        <?php
    }

    private function render_email_form(int $task_id): void
    {
        global $wpdb;

        $tasks = GCA_Database::table('tasks');
        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $task = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*, s.*, t.id AS task_id, t.status AS task_status, c.name AS class_name
                 FROM {$tasks} t
                 INNER JOIN {$students} s ON s.id = t.student_id
                 INNER JOIN {$classes} c ON c.id = s.class_id
                 WHERE t.id = %d",
                $task_id
            )
        );

        if (!$task || $task->task_status !== 'pending') {
            echo '<p class="gca-empty">Tâche introuvable ou déjà traitée.</p>';
            return;
        }

        $templates = GCA_Database::get_email_templates();
        $template_key = $this->template_key_for_task($task);
        $free_templates = GCA_Database::get_free_message_templates();
        $selected_free_template_id = $template_key ? '' : $this->free_template_key_for_task($task);
        $selected_free_template = $selected_free_template_id !== '' && isset($free_templates[$selected_free_template_id])
            ? $free_templates[$selected_free_template_id]
            : reset($free_templates);
        if (!is_array($selected_free_template)) {
            $selected_free_template = array('id' => '', 'label' => '', 'subject' => '', 'body' => '');
        }
        $communication_type = $this->communication_type_for_task($task);
        $is_absence_followup_task = $this->task_is_absence_followup($task);
        $recipient_mode = $this->recipient_mode_for_task($task, sanitize_key(wp_unslash($_GET['recipient_mode'] ?? '')));
        $recipients = $this->default_recipients_for_task($task, $recipient_mode);
        $subject = $template_key ? ($templates[$template_key . '_subject'] ?? '') : (string) ($selected_free_template['subject'] ?? 'Mise à la porte {threshold} {class_code} - {full_name}');
        $body = $template_key ? ($templates[$template_key . '_body'] ?? '') : (string) ($selected_free_template['body'] ?? '');
        $detention_no_show_note = $this->detention_no_show_note_for_student($task);
        $requires_convocation_date = $this->task_requires_convocation_date($task);
        $preview_convocation_date = $requires_convocation_date ? '{convocation_date}' : '';
        $subject = $this->replace_variables($subject, $task, array('convocation_date' => $preview_convocation_date));
        $subject = $this->ensure_subject_class_code($subject, $task);
        $body = $this->replace_variables($body, $task, array('convocation_date' => $preview_convocation_date, 'detention_no_show_note' => $detention_no_show_note));
        if ($this->task_is_lateness_convocation($task)) {
            $body = $this->append_note_if_missing($body, $detention_no_show_note);
        }
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <a class="gca-back" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard'))); ?>">&larr; À traiter</a>
                    <h2><?php echo esc_html($this->task_type_label($task->task_type)); ?></h2>
                </div>
                <span class="gca-chip gca-chip-links">
                    <?php echo $this->student_link_with_company($task); ?>
                    ·
                    <?php echo $this->class_link((int) $task->class_id, (string) $task->class_name); ?>
                </span>
            </div>

            <?php if ($this->student_without_employer($task)) : ?>
                <div class="gca-notice gca-notice-error">
                    <strong>Apprenti sans employeur actif : aucun envoi formateur.</strong>
                    <p><?php echo esc_html($this->contract_break_alert_text($task)); ?></p>
                </div>
            <?php endif; ?>

            <form class="gca-form gca-form-wide" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gca_send_email_' . $task_id); ?>
                <input type="hidden" name="action" value="gca_send_email">
                <input type="hidden" name="task_id" value="<?php echo esc_attr((string) $task_id); ?>">
                <input type="hidden" name="communication_type" value="<?php echo esc_attr($communication_type); ?>">
                <input type="hidden" name="recipient_mode" value="<?php echo esc_attr($recipient_mode); ?>">

                <?php if ($is_absence_followup_task) : ?>
                    <div class="gca-form-section gca-form-section-inset">
                        <h3>Destinataire de l’information</h3>
                        <div class="gca-actions">
                            <a class="gca-button <?php echo $recipient_mode === 'trainer' ? 'gca-button-primary' : ''; ?>" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard', 'gca_action' => 'prepare_email', 'task_id' => (int) $task_id, 'recipient_mode' => 'trainer'))); ?>">Responsable + copie élève</a>
                            <a class="gca-button <?php echo $recipient_mode === 'dean' ? 'gca-button-primary' : ''; ?>" href="<?php echo esc_url($this->page_url(array('gca_tab' => 'dashboard', 'gca_action' => 'prepare_email', 'task_id' => (int) $task_id, 'recipient_mode' => 'dean'))); ?>">Doyen</a>
                        </div>
                        <p class="gca-muted">
                            <?php if ($recipient_mode === 'dean') : ?>
                                Le message est préparé pour le Doyen, sans copie automatique à l’apprenti.
                            <?php else : ?>
                                Le message est préparé pour le responsable de formation, avec l’apprenti en copie.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!$template_key && $free_templates) : ?>
                    <div class="gca-form-section gca-form-section-inset">
                        <label>Modèle de message libre
                            <select data-gca-message-template-select>
                                <?php foreach ($free_templates as $template) : ?>
                                    <?php
                                    $template_subject = $this->replace_variables((string) ($template['subject'] ?? ''), $task, array('convocation_date' => $preview_convocation_date));
                                    $template_subject = $this->ensure_subject_class_code($template_subject, $task);
                                    $template_body = $this->replace_variables((string) ($template['body'] ?? ''), $task, array('convocation_date' => $preview_convocation_date, 'detention_no_show_note' => $detention_no_show_note));
                                    ?>
                                    <option
                                        value="<?php echo esc_attr((string) ($template['id'] ?? '')); ?>"
                                        data-label="<?php echo esc_attr((string) ($template['label'] ?? '')); ?>"
                                        data-subject="<?php echo esc_attr($template_subject); ?>"
                                        data-body="<?php echo esc_attr($template_body); ?>"
                                        <?php selected((string) ($selected_free_template['id'] ?? ''), (string) ($template['id'] ?? '')); ?>
                                    >
                                        <?php echo esc_html((string) ($template['label'] ?? 'Message libre')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <p class="gca-muted">Choisir un modèle remplace l’objet et le message ci-dessous. Les variables de l’apprenti et de la classe sont appliquées automatiquement.</p>
                    </div>
                <?php endif; ?>

                <div class="gca-form-grid">
                    <label>A
                        <input type="text" name="recipients_to" value="<?php echo esc_attr(implode(', ', $recipients['to'])); ?>">
                    </label>
                    <label>Copie
                        <input type="text" name="recipients_cc" value="<?php echo esc_attr(implode(', ', $recipients['cc'])); ?>">
                    </label>
                    <label>Copie cachée
                        <input type="text" name="recipients_bcc" value="<?php echo esc_attr(implode(', ', $recipients['bcc'])); ?>">
                    </label>
                    <?php if ($requires_convocation_date) : ?>
                        <label>Date de convocation
                            <input type="date" name="convocation_date" required>
                        </label>
                    <?php endif; ?>
                </div>

                <label>Objet
                    <input type="text" name="subject" value="<?php echo esc_attr($subject); ?>" data-gca-message-template-subject required>
                </label>
                <?php $this->render_rich_text_editor('body', $body, array('rows' => 12, 'required' => true, 'source_attrs' => array('data-gca-message-template-body' => ''))); ?>

                <?php if ($requires_convocation_date) : ?>
                    <p class="gca-muted">Un PDF de convocation sera généré et joint à l’e-mail.</p>
                <?php endif; ?>

                <button class="gca-button gca-button-primary" type="submit">Envoyer et archiver</button>
            </form>
        </section>
        <?php
    }

    private function render_audit(): void
    {
        global $wpdb;

        $logs_table = GCA_Database::table('logs');
        $teachers_table = GCA_Database::table('teachers');
        $snapshots_table = GCA_Database::table('snapshots');
        $logs = $wpdb->get_results(
            "SELECT l.*, t.display_name AS teacher_name, t.user_email AS teacher_email
             FROM {$logs_table} l
             LEFT JOIN {$teachers_table} t ON t.id = l.teacher_id
             ORDER BY l.created_at DESC
             LIMIT 100"
        );
        $snapshot_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$snapshots_table}");
        ?>
        <section class="gca-panel">
            <div class="gca-panel-title">
                <div>
                    <h2>Journal d’audit</h2>
                    <p class="gca-muted"><?php echo esc_html($snapshot_count . ' snapshots conserves dans les tables du plugin.'); ?></p>
                </div>
            </div>

            <?php if (!$logs) : ?>
                <p class="gca-empty">Aucune entrée d’audit pour le moment.</p>
            <?php else : ?>
                <div class="gca-table-wrap">
                    <table class="gca-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Prof</th>
                                <th>Action</th>
                                <th>Objet</th>
                                <th>Empreinte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html($this->display_date($log->created_at)); ?></td>
                                    <td>
                                        <?php echo esc_html($log->teacher_name ?: ($log->actor_name ?: ('Utilisateur #' . (int) $log->user_id))); ?>
                                        <?php if (!empty($log->teacher_email ?: $log->actor_email)) : ?><br><span class="gca-muted"><?php echo esc_html($log->teacher_email ?: $log->actor_email); ?></span><?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($this->audit_action_label((string) $log->action)); ?></td>
                                    <td><?php echo esc_html(trim((string) $log->object_type . ' #' . (int) $log->object_id)); ?></td>
                                    <td><span class="gca-muted"><?php echo esc_html(substr((string) $log->data_hash, 0, 12)); ?>...</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    public function handle_import_prepare(): void
    {
        $this->require_manage();
        check_admin_referer('gca_import_prepare');

        if (empty($_FILES['gca_file']['tmp_name']) || !is_uploaded_file($_FILES['gca_file']['tmp_name'])) {
            $this->redirect_notice('imports', 'missing_file');
        }

        $filename = sanitize_file_name((string) $_FILES['gca_file']['name']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, array('csv', 'txt', 'xlsx'), true)) {
            $this->redirect_notice('imports', 'missing_file');
        }

        $private = GCA_Database::ensure_private_dirs();
        $destination = $private . '/imports/' . gmdate('Ymd-His') . '-' . wp_generate_password(8, false, false) . '-' . $filename;

        if (!move_uploaded_file($_FILES['gca_file']['tmp_name'], $destination)) {
            $this->redirect_notice('imports', 'missing_file');
        }

        $type = $this->importer->normalize_import_type(sanitize_key(wp_unslash($_POST['import_type'] ?? 'students')));
        $posted_mode = sanitize_key(wp_unslash($_POST['import_mode'] ?? 'replace'));
        $mode = $posted_mode === 'add' && !in_array($type, array('lagap', 'grades'), true) ? 'add' : 'replace';
        $preview = $this->importer->read_file($destination, 12);

        if (!$preview) {
            $this->redirect_notice('imports', 'bad_import');
        }

        $headers = array_keys($preview[0]);
        if ($type === 'lagap' && !$this->importer->is_lagap_export($headers)) {
            $this->redirect_notice('imports', 'not_lagap');
        }

        $mapping = $type === 'lagap'
            ? $this->importer->lagap_mapping($headers)
            : $this->importer->infer_mapping($headers, $type);
        $source_sha256 = is_readable($destination) ? (string) hash_file('sha256', $destination) : '';
        $source_size = is_readable($destination) ? (int) filesize($destination) : 0;
        $teacher_id = GCA_Database::current_teacher_id();
        $mapping_source = 'detected';
        $saved_mapping_label = '';
        $saved_mapping_updated_at = '';
        $saved_profile = $this->compatible_import_mapping_profile($type, $headers, $mapping, $teacher_id);

        if ($saved_profile) {
            $mapping = (array) $saved_profile['mapping'];
            $mapping_source = 'saved';
            $saved_mapping_label = (string) ($saved_profile['label'] ?? '');
            $saved_mapping_updated_at = (string) ($saved_profile['updated_at'] ?? '');
        }

        $token = wp_generate_uuid4();

        set_transient(
            'gca_import_' . $token,
            array(
                'path' => $destination,
                'filename' => $filename,
                'type' => $type,
                'mode' => $mode,
                'prepared_by' => get_current_user_id(),
                'teacher_id' => $teacher_id,
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'headers' => $headers,
                'mapping' => $mapping,
                'mapping_source' => $mapping_source,
                'saved_mapping_label' => $saved_mapping_label,
                'saved_mapping_updated_at' => $saved_mapping_updated_at,
                'preview' => $preview,
            ),
            6 * HOUR_IN_SECONDS
        );
        GCA_Database::log(
            'import_prepared',
            'import_file',
            0,
            array(
                'filename' => $filename,
                'type' => $type,
                'mode' => $mode,
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => GCA_Database::private_relative_path($destination),
                'mapping_source' => $mapping_source,
                'saved_mapping_label' => $saved_mapping_label,
            )
        );

        wp_safe_redirect($this->page_url(array('gca_tab' => 'imports', 'gca_import_token' => $token, 'gca_notice' => 'import_ready')));
        exit;
    }

    public function handle_import_commit(): void
    {
        $this->require_manage();

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        check_admin_referer('gca_import_commit_' . $token);

        $payload = get_transient('gca_import_' . $token);
        if (!is_array($payload)) {
            $this->redirect_notice('imports', 'bad_import');
        }

        if ((int) ($payload['prepared_by'] ?? 0) !== get_current_user_id()) {
            GCA_Database::log('import_commit_denied', 'import_file', 0, array('token' => $token));
            $this->redirect_notice('imports', 'bad_import');
        }

        $type = $this->importer->normalize_import_type(sanitize_key(wp_unslash($_POST['import_type'] ?? 'students')));
        $mode = isset($_POST['import_mode']) && $_POST['import_mode'] === 'add' && !in_array($type, array('lagap', 'grades'), true) ? 'add' : 'replace';
        $mapping = isset($_POST['mapping']) && is_array($_POST['mapping']) ? array_map('sanitize_text_field', wp_unslash($_POST['mapping'])) : array();
        $duplicate_mode = isset($_POST['duplicate_mode']) && $_POST['duplicate_mode'] === 'ignore' ? 'ignore' : 'update';
        $fields = $this->importer->field_definitions($type);
        $save_mapping = !empty($_POST['save_mapping']);
        $mapping_label = isset($_POST['mapping_label']) ? sanitize_text_field(wp_unslash($_POST['mapping_label'])) : '';

        foreach ($fields as $field => $definition) {
            if (!empty($definition['required']) && (empty($mapping[$field]) || $mapping[$field] === '__ignore')) {
                $this->redirect_notice('imports', 'missing_mapping');
            }
        }

        $this->importer->process(
            (string) $payload['path'],
            $type,
            $mode,
            $mapping,
            $duplicate_mode,
            (string) $payload['filename']
        );
        $this->sync_trainers_from_students();

        if ($save_mapping) {
            if ($mapping_label === '') {
                $mapping_label = $this->default_import_mapping_label($type);
            }

            GCA_Database::save_import_mapping_profile($type, (int) ($payload['teacher_id'] ?? 0), $mapping_label, $mapping);
            GCA_Database::log(
                'import_mapping_saved',
                'import_mapping',
                0,
                array(
                    'label' => $mapping_label,
                    'type' => $type,
                    'filename' => (string) $payload['filename'],
                )
            );
        }

        delete_transient('gca_import_' . $token);
        $this->redirect_notice('imports', $save_mapping ? 'import_ok_mapping_saved' : 'import_ok');
    }

    public function handle_save_trainer_profiles(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_save_trainer_profiles');

        $this->sync_trainers_from_students();

        $selected_class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $raw_keys = isset($_POST['trainer_keys']) && is_array($_POST['trainer_keys'])
            ? wp_unslash($_POST['trainer_keys'])
            : array();
        $raw_salutations = isset($_POST['trainer_salutations']) && is_array($_POST['trainer_salutations'])
            ? wp_unslash($_POST['trainer_salutations'])
            : array();

        $table = GCA_Database::table('trainers');
        $updated = 0;
        $saved_salutations = $this->saved_trainer_salutations();

        foreach ($raw_keys as $raw_key) {
            $key = sanitize_text_field((string) $raw_key);
            if (!preg_match('/^[a-f0-9]{40}$/', $key)) {
                continue;
            }

            $salutation = sanitize_text_field((string) ($raw_salutations[$key] ?? ''));
            if ($salutation !== '') {
                $saved_salutations[$key] = $salutation;
            } else {
                unset($saved_salutations[$key]);
            }

            $current_manual_fields = $wpdb->get_var($wpdb->prepare("SELECT manual_fields_json FROM {$table} WHERE trainer_key = %s LIMIT 1", $key));
            $manual_fields_json = GCA_Database::merge_manual_fields($current_manual_fields, array('salutation'));
            $wpdb->update(
                $table,
                array(
                    'salutation' => $salutation,
                    'manual_fields_json' => $manual_fields_json,
                    'updated_at' => GCA_Database::now(),
                ),
                array('trainer_key' => $key),
                array('%s', '%s', '%s'),
                array('%s')
            );
            $updated++;
        }

        update_option(LogiPilot_Spaces::option('gca_trainer_salutations'), $saved_salutations, false);

        GCA_Database::log(
            'trainer_profiles_saved',
            'trainer',
            0,
            array(
                'class_id' => $selected_class_id,
                'updated_count' => $updated,
            )
        );

        wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $selected_class_id, 'gca_notice' => 'trainer_profiles_saved')));
        exit;
    }

    public function handle_import_photos_pdf(): void
    {
        $this->require_manage();
        check_admin_referer('gca_import_photos_pdf');

        if (empty($_FILES['gca_photos_pdf']['tmp_name']) || !is_uploaded_file($_FILES['gca_photos_pdf']['tmp_name'])) {
            $this->redirect_notice('imports', 'missing_file');
        }

        $filename = sanitize_file_name((string) $_FILES['gca_photos_pdf']['name']);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->redirect_notice('imports', 'missing_file');
        }

        $private = GCA_Database::ensure_private_dirs();
        $destination = $private . '/imports/' . gmdate('Ymd-His') . '-' . wp_generate_password(8, false, false) . '-' . $filename;

        if (!move_uploaded_file($_FILES['gca_photos_pdf']['tmp_name'], $destination)) {
            $this->redirect_notice('imports', 'missing_file');
        }

        $photos = $this->extract_student_photos_from_pdf($destination);
        if (!$photos) {
            GCA_Database::log('photos_import_failed', 'import_file', 0, array('filename' => $filename));
            $this->redirect_notice('imports', 'photos_import_failed');
        }

        $token = wp_generate_password(24, false, false);
        $preview_dir = GCA_Database::private_dir('imports/photo-import-' . $token);
        wp_mkdir_p($preview_dir);
        file_put_contents($preview_dir . '/index.php', "<?php\n// Silence is golden.\n");
        file_put_contents($preview_dir . '/.htaccess', "Deny from all\n");

        $prepared_photos = array();
        foreach ($photos as $index => $photo) {
            $photo_path = $preview_dir . '/photo-' . ((int) $index + 1) . '.jpg';
            if (file_put_contents($photo_path, (string) ($photo['bytes'] ?? '')) === false) {
                continue;
            }

            $prepared_photos[] = array(
                'name' => $this->normalize_exact_photo_name((string) ($photo['name'] ?? '')),
                'path' => GCA_Database::private_relative_path($photo_path),
            );
        }

        if (!$prepared_photos) {
            $this->delete_private_dir($preview_dir);
            GCA_Database::log('photos_import_failed', 'import_file', 0, array('filename' => $filename));
            $this->redirect_notice('imports', 'photos_import_failed');
        }

        $source_sha256 = is_readable($destination) ? (string) hash_file('sha256', $destination) : '';
        $source_size = is_readable($destination) ? (int) filesize($destination) : 0;
        set_transient(
            'gca_photo_import_' . $token,
            array(
                'filename' => $filename,
                'prepared_by' => get_current_user_id(),
                'teacher_id' => GCA_Database::current_teacher_id(),
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => GCA_Database::private_relative_path($destination),
                'preview_dir' => GCA_Database::private_relative_path($preview_dir),
                'photos' => $prepared_photos,
            ),
            6 * HOUR_IN_SECONDS
        );

        GCA_Database::log(
            'photos_import_prepared',
            'import_file',
            0,
            array(
                'filename' => $filename,
                'photo_count' => count($prepared_photos),
                'source_sha256' => $source_sha256,
                'source_size' => $source_size,
                'source_path' => GCA_Database::private_relative_path($destination),
            )
        );

        wp_safe_redirect($this->page_url(array('gca_tab' => 'imports', 'gca_photo_import_token' => $token, 'gca_notice' => 'photos_ready')));
        exit;
    }

    public function handle_import_photos_commit(): void
    {
        global $wpdb;

        $this->require_manage();
        $token = isset($_POST['photo_import_token']) ? sanitize_text_field(wp_unslash($_POST['photo_import_token'])) : '';
        check_admin_referer('gca_import_photos_commit_' . $token);

        $payload = get_transient('gca_photo_import_' . $token);
        if (!is_array($payload) || empty($payload['photos']) || !is_array($payload['photos'])) {
            $this->redirect_notice('imports', 'bad_import');
        }

        if ((int) ($payload['prepared_by'] ?? 0) !== get_current_user_id()) {
            GCA_Database::log('photos_import_commit_denied', 'import_file', 0, array('token' => $token));
            $this->redirect_notice('imports', 'bad_import');
        }

        $raw_assignments = isset($_POST['assignments']) && is_array($_POST['assignments']) ? wp_unslash($_POST['assignments']) : array();
        $assignments = array();
        foreach ($raw_assignments as $index => $student_id) {
            $assignments[(int) $index] = max(0, (int) $student_id);
        }

        $selected_student_ids = array_values(array_filter($assignments, static function (int $student_id): bool {
            return $student_id > 0;
        }));

        if (!$selected_student_ids) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'imports', 'gca_photo_import_token' => $token, 'gca_notice' => 'photos_no_assignment')));
            exit;
        }

        $duplicates = array_filter(array_count_values($selected_student_ids), static function (int $count): bool {
            return $count > 1;
        });
        if ($duplicates) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'imports', 'gca_photo_import_token' => $token, 'gca_notice' => 'photos_duplicate_assignment')));
            exit;
        }

        $students_by_id = array();
        foreach ($this->photo_assignment_students() as $student) {
            $students_by_id[(int) $student->id] = $student;
        }

        $matched = 0;
        $unmatched = array();
        $assigned = array();
        $photos = array_values($payload['photos']);
        foreach ($photos as $index => $photo) {
            $student_id = $assignments[$index] ?? 0;
            $photo_name = $this->normalize_exact_photo_name((string) ($photo['name'] ?? ''));

            if ($student_id <= 0) {
                $unmatched[] = ($photo_name !== '' ? $photo_name : 'Photo #' . ((int) $index + 1)) . ' - non importée par choix manuel.';
                continue;
            }

            if (!isset($students_by_id[$student_id])) {
                $unmatched[] = ($photo_name !== '' ? $photo_name : 'Photo #' . ((int) $index + 1)) . ' - fiche apprenti introuvable ou archivée.';
                continue;
            }

            $preview_path = $this->private_path_from_relative((string) ($photo['path'] ?? ''));
            $bytes = $preview_path !== '' && is_readable($preview_path) ? file_get_contents($preview_path) : false;
            if ($bytes === false || !$this->store_student_photo_bytes($student_id, (string) $bytes)) {
                $unmatched[] = ($photo_name !== '' ? $photo_name : 'Photo #' . ((int) $index + 1)) . ' - fichier photo illisible.';
                continue;
            }

            $matched++;
            $assigned[] = ($photo_name !== '' ? $photo_name : 'Photo #' . ((int) $index + 1)) . ' -> ' . $this->student_photo_import_label($students_by_id[$student_id]);
        }

        $source_path = $this->private_path_from_relative((string) ($payload['source_path'] ?? ''));
        $wpdb->insert(
            GCA_Database::table('imports'),
            array(
                'import_type' => 'photos_pdf',
                'import_mode' => 'review',
                'source_filename' => sanitize_file_name((string) ($payload['filename'] ?? 'photos.pdf')),
                'mapping_json' => wp_json_encode(array('match' => 'Attribution vérifiée manuellement après prévisualisation', 'assigned' => array_slice($assigned, 0, 80))),
                'row_count' => count($photos),
                'processed_count' => $matched,
                'skipped_count' => max(0, count($photos) - $matched),
                'error_count' => 0,
                'errors_json' => wp_json_encode(array_slice($unmatched, 0, 80)),
                'created_by' => get_current_user_id(),
                'teacher_id' => (int) ($payload['teacher_id'] ?? GCA_Database::current_teacher_id()),
                'source_sha256' => (string) ($payload['source_sha256'] ?? ($source_path && is_readable($source_path) ? hash_file('sha256', $source_path) : '')),
                'source_size' => (int) ($payload['source_size'] ?? ($source_path && is_readable($source_path) ? filesize($source_path) : 0)),
                'source_path' => (string) ($payload['source_path'] ?? ''),
                'created_at' => GCA_Database::now(),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s')
        );

        GCA_Database::log(
            'photos_imported',
            'import_file',
            (int) $wpdb->insert_id,
            array(
                'filename' => (string) ($payload['filename'] ?? ''),
                'matched' => $matched,
                'assigned' => array_slice($assigned, 0, 80),
                'unmatched' => array_slice($unmatched, 0, 80),
                'source_path' => (string) ($payload['source_path'] ?? ''),
            )
        );

        delete_transient('gca_photo_import_' . $token);
        $this->delete_private_dir($this->private_path_from_relative((string) ($payload['preview_dir'] ?? '')));

        if ($matched <= 0) {
            $this->redirect_notice('imports', 'photos_no_exact_match');
        }

        $this->redirect_notice('imports', $unmatched ? 'photos_imported_with_skips' : 'photos_imported');
    }

    public function handle_photo_import_preview(): void
    {
        $this->require_manage();

        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $index = isset($_GET['index']) ? (int) $_GET['index'] : -1;
        check_admin_referer('gca_photo_import_preview_' . $token . '_' . $index);

        $payload = get_transient('gca_photo_import_' . $token);
        if (!is_array($payload) || (int) ($payload['prepared_by'] ?? 0) !== get_current_user_id()) {
            status_header(404);
            exit;
        }

        $photo = is_array($payload) && isset($payload['photos'][$index]) && is_array($payload['photos'][$index])
            ? $payload['photos'][$index]
            : array();
        $path = $this->private_path_from_relative((string) ($photo['path'] ?? ''));

        if ($path === '' || !is_readable($path)) {
            status_header(404);
            exit;
        }

        nocache_headers();
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function handle_save_settings(): void
    {
        $this->require_manage();
        check_admin_referer('gca_save_settings');

        $raw_settings = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
        $settings = GCA_Database::get_settings();
        $settings['school_year'] = sanitize_text_field($raw_settings['school_year'] ?? $settings['school_year']);
        $settings['lateness_threshold_1'] = max(1, (int) ($raw_settings['lateness_threshold_1'] ?? 3));
        $settings['lateness_threshold_2'] = max(1, (int) ($raw_settings['lateness_threshold_2'] ?? 6));
        $settings['lateness_threshold_dean'] = max(1, (int) ($raw_settings['lateness_threshold_dean'] ?? 7));
        $settings['absence_thresholds'] = sanitize_text_field($raw_settings['absence_thresholds'] ?? '');
        $settings['school_start_date'] = $this->normalize_date_for_storage($raw_settings['school_start_date'] ?? '') ?: GCA_Database::default_school_start_date();
        $settings['absence_grace_days'] = min(120, max(0, (int) ($raw_settings['absence_grace_days'] ?? 21)));
        $settings['absence_min_count_before_alert'] = max(0, (float) str_replace(',', '.', (string) ($raw_settings['absence_min_count_before_alert'] ?? 2)));
        $settings['absence_unjustified_days_threshold'] = max(1, (int) ($raw_settings['absence_unjustified_days_threshold'] ?? 21));
        $settings['absence_critical_rate_threshold'] = min(100, max(1, (float) str_replace(',', '.', (string) ($raw_settings['absence_critical_rate_threshold'] ?? 30))));
        $settings['dean_emails'] = sanitize_text_field($raw_settings['dean_emails'] ?? '');
        $sender_name = sanitize_text_field($raw_settings['email_sender_name'] ?? 'CPNV');
        $settings['email_sender_name'] = trim($sender_name) !== '' ? $sender_name : 'CPNV';
        $sender_email = sanitize_email($raw_settings['email_sender_email'] ?? '');
        $settings['email_sender_email'] = is_email($sender_email) ? $sender_email : '';
        $reply_to_email = sanitize_email($raw_settings['email_reply_to_email'] ?? '');
        $settings['email_reply_to_email'] = is_email($reply_to_email) ? $reply_to_email : '';
        $settings['message_signature_text'] = GCA_Database::sanitize_message_body($raw_settings['message_signature_text'] ?? '');
        $signature_image = $this->store_signature_image_from_request();
        if ($signature_image) {
            $settings['message_signature_image_url'] = $signature_image['url'];
            $settings['message_signature_image_path'] = $signature_image['path'];
        }
        $settings['reminder_emails'] = sanitize_text_field($raw_settings['reminder_emails'] ?? '');
        $settings['weekly_reminder_enabled'] = 0;
        $settings['weekly_reminder_time'] = '08:00';
        $settings['stale_task_reminder_enabled'] = isset($raw_settings['stale_task_reminder_enabled']) ? 1 : 0;
        $settings['stale_task_days'] = min(30, max(1, (int) ($raw_settings['stale_task_days'] ?? 3)));
        $settings['stale_task_reminder_time'] = $this->sanitize_time_setting((string) ($raw_settings['stale_task_reminder_time'] ?? ''), '08:15');
        $settings['import_reminder_enabled'] = isset($raw_settings['import_reminder_enabled']) ? 1 : 0;
        $settings['import_reminder_days'] = min(365, max(1, (int) ($raw_settings['import_reminder_days'] ?? 8)));
        $settings['manual_approval'] = 1;
        $settings['automatic_send'] = 0;
        GCA_Database::update_settings($settings);
        $this->reschedule_internal_reminders();

        $raw_templates = isset($_POST['templates']) && is_array($_POST['templates']) ? wp_unslash($_POST['templates']) : array();
        $templates = GCA_Database::get_email_templates();
        foreach ($templates as $key => $value) {
            if (isset($raw_templates[$key])) {
                $templates[$key] = str_ends_with($key, '_body')
                    ? GCA_Database::sanitize_message_body($raw_templates[$key])
                    : sanitize_text_field($raw_templates[$key]);
            }
        }
        GCA_Database::update_email_templates($templates);
        GCA_Database::log('settings_saved', 'settings', 0, array('settings' => $settings, 'templates' => $templates));
        $this->importer->recalculate_all_active_tasks();
        delete_option(LogiPilot_Spaces::option('gca_recalculate_tasks_once'));

        $this->redirect_notice('settings', 'settings_saved');
    }

    public function send_stale_task_reminder(): void
    {
        $settings = GCA_Database::get_settings();
        if (empty($settings['stale_task_reminder_enabled'])) {
            return;
        }

        $today = wp_date('Y-m-d');
        if (get_option(LogiPilot_Spaces::option('gca_stale_task_reminder_last_sent')) === $today) {
            return;
        }

        $this->importer->recalculate_all_active_tasks();
        $days = min(30, max(1, (int) ($settings['stale_task_days'] ?? 3)));
        $pending = $this->pending_task_rows($this->mysql_datetime_days_ago($days), 50);
        $pending_by_id = array();
        foreach ($pending as $row) {
            $pending_by_id[(int) $row->id] = $row;
        }
        foreach ($this->pending_contract_review_rows(50) as $contract_task) {
            $pending_by_id[(int) $contract_task->id] = $contract_task;
        }
        $pending = array_values($pending_by_id);
        if (!$pending) {
            return;
        }

        $to = $this->reminder_recipient_emails($settings);
        if (!$to) {
            return;
        }

        $body = "Bonjour,\n\nVoici le résumé des actions en attente depuis au moins " . $days . " jour(s), ainsi que des ruptures de contrat arrivées à échéance :\n\n";
        $body .= implode("\n", $this->task_digest_lines($pending)) . "\n\n";
        $body .= "Accéder aux situations à traiter : " . admin_url('admin.php?page=gca_app') . "\n\n";
        $body .= "Avec nos salutations.";

        $sent = $this->send_plugin_mail(
            $to,
            'Rappel interne - actions en attente',
            $this->email_body_with_signature($body),
            array_merge(array('Content-Type: text/html; charset=UTF-8'), $this->mail_sender_headers())
        );

        if ($sent) {
            update_option(LogiPilot_Spaces::option('gca_stale_task_reminder_last_sent'), $today, false);
            GCA_Database::log('stale_task_reminder_sent', 'reminder', 0, array('to' => $to, 'days' => $days, 'pending_count' => count($pending)));
        }
    }

    public function send_import_freshness_reminder(): void
    {
        global $wpdb;

        $settings = GCA_Database::get_settings();
        if (empty($settings['import_reminder_enabled'])) {
            return;
        }

        $today = wp_date('Y-m-d');
        if (get_option(LogiPilot_Spaces::option('gca_import_freshness_reminder_last_sent')) === $today) {
            return;
        }

        $imports = GCA_Database::table('imports');
        $latest = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, source_filename, created_at
                 FROM {$imports}
                 WHERE import_type = %s
                 ORDER BY created_at DESC
                 LIMIT 1",
                'lagap'
            )
        );

        if (!$latest) {
            return;
        }

        $days = min(365, max(1, (int) ($settings['import_reminder_days'] ?? 8)));
        $age = $this->task_age_days((string) $latest->created_at);
        if ($age <= $days) {
            return;
        }

        $to = $this->reminder_recipient_emails($settings);
        if (!$to) {
            return;
        }

        $body = "Bonjour,\n\nLe dernier import Lagap enregistré date de " . $age . " jour(s).";
        $body .= "\nDernier fichier : " . (string) $latest->source_filename;
        $body .= "\nDate du dernier import : " . $this->display_date((string) $latest->created_at);
        $body .= "\n\nPensez à importer une nouvelle version si un export plus récent est disponible.";
        $body .= "\n\nAccéder aux imports : " . admin_url('admin.php?page=gca_imports') . "\n\n";
        $body .= "Avec nos salutations.";

        $sent = $this->send_plugin_mail(
            $to,
            'Rappel interne - import Lagap à actualiser',
            $this->email_body_with_signature($body),
            array_merge(array('Content-Type: text/html; charset=UTF-8'), $this->mail_sender_headers())
        );

        if ($sent) {
            update_option(LogiPilot_Spaces::option('gca_import_freshness_reminder_last_sent'), $today, false);
            GCA_Database::log('import_freshness_reminder_sent', 'reminder', 0, array('to' => $to, 'age_days' => $age, 'latest_import_id' => (int) $latest->id));
        }
    }

    public function handle_save_class(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_save_class');

        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $school_year = sanitize_text_field(wp_unslash($_POST['school_year'] ?? ''));
        $status = isset($_POST['status']) && $_POST['status'] === 'archived' ? 'archived' : 'active';

        if ($name === '' || $school_year === '') {
            $this->redirect_notice('classes', 'not_found');
        }

        $table = GCA_Database::table('classes');
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE name = %s AND school_year = %s LIMIT 1",
                $name,
                $school_year
            )
        );

        // Une classe n'a qu'un maître de classe : si un autre enseignant l'a déjà, on ne peut pas la créer (ni renommer une classe en celle-ci).
        if (class_exists('LogiPilot_Spaces') && LogiPilot_Spaces::classe_master_elsewhere($name, $school_year) > 0) {
            $redirect_args = array('gca_tab' => 'classes', 'gca_notice' => 'class_has_master');
            if ($class_id > 0) {
                $redirect_args['class_id'] = $class_id;
            }
            wp_safe_redirect($this->page_url($redirect_args));
            exit;
        }

        if ($existing_id && (int) $existing_id !== $class_id) {
            $redirect_args = array('gca_tab' => 'classes', 'gca_notice' => 'class_exists');
            if ($class_id > 0) {
                $redirect_args['class_id'] = $class_id;
            }
            wp_safe_redirect($this->page_url($redirect_args));
            exit;
        }

        if ($class_id > 0) {
            $wpdb->update(
                $table,
                array(
                    'name' => $name,
                    'school_year' => $school_year,
                    'status' => $status,
                    'updated_at' => GCA_Database::now(),
                ),
                array('id' => $class_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'name' => $name,
                    'school_year' => $school_year,
                    'status' => $status,
                    'created_at' => GCA_Database::now(),
                    'updated_at' => GCA_Database::now(),
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            $class_id = (int) $wpdb->insert_id;
        }

        GCA_Database::log('class_saved', 'class', $class_id, array('name' => $name, 'school_year' => $school_year, 'status' => $status));
        wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'class_id' => $class_id, 'gca_notice' => 'class_saved')));
        exit;
    }

    public function handle_save_student(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_save_student');

        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

        if ($class_id <= 0) {
            $this->redirect_notice('classes', 'not_found');
        }

        $disciplinary_warning_date = $this->normalize_date_for_storage(wp_unslash($_POST['disciplinary_warning_date'] ?? ''));
        $wellbeing_council_date = $this->normalize_date_for_storage(wp_unslash($_POST['wellbeing_council_date'] ?? ''));
        $contract_status = $this->contract_status_value(sanitize_key(wp_unslash($_POST['contract_status'] ?? 'active')));
        $contract_break_date = $this->normalize_date_for_storage(wp_unslash($_POST['contract_break_date'] ?? ''));
        if ($contract_status !== 'free_candidate' && $contract_break_date !== '') {
            $contract_status = 'broken';
        }
        if ($contract_status !== 'broken') {
            $contract_break_date = '';
        }
        $posted_trainer_id = isset($_POST['trainer_id']) ? (int) $_POST['trainer_id'] : 0;
        $linked_trainer = $posted_trainer_id > 0 ? $this->trainer_by_id($posted_trainer_id) : null;
        $table = GCA_Database::table('students');
        $before = $student_id > 0
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $student_id), ARRAY_A)
            : array();
        $student_verified = !empty($_POST['student_verified']);
        $verified_at = $student_verified ? ($this->stored_verified_at($before['verified_at'] ?? '') ?: GCA_Database::now()) : null;
        $verified_by = $student_verified ? (int) (($before['verified_by'] ?? 0) ?: get_current_user_id()) : 0;

        $data = array(
            'class_id' => $class_id,
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'date_of_birth' => $this->normalize_date_for_storage(wp_unslash($_POST['date_of_birth'] ?? '')) ?: null,
            'gender' => $this->normalize_gender(wp_unslash($_POST['gender'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'trainer_id' => $linked_trainer ? $posted_trainer_id : 0,
            'trainer_name' => sanitize_text_field(wp_unslash($_POST['trainer_name'] ?? '')),
            'trainer_company' => sanitize_text_field(wp_unslash($_POST['trainer_company'] ?? '')),
            'trainer_company_address' => sanitize_text_field(wp_unslash($_POST['trainer_company_address'] ?? '')),
            'trainer_company_zip' => sanitize_text_field(wp_unslash($_POST['trainer_company_zip'] ?? '')),
            'trainer_company_city' => sanitize_text_field(wp_unslash($_POST['trainer_company_city'] ?? '')),
            'trainer_email' => sanitize_email(wp_unslash($_POST['trainer_email'] ?? '')),
            'trainer_phone' => sanitize_text_field(wp_unslash($_POST['trainer_phone'] ?? '')),
            'legal_rep1_name' => sanitize_text_field(wp_unslash($_POST['legal_rep1_name'] ?? '')),
            'legal_rep1_email' => sanitize_email(wp_unslash($_POST['legal_rep1_email'] ?? '')),
            'legal_rep1_phone' => sanitize_text_field(wp_unslash($_POST['legal_rep1_phone'] ?? '')),
            'legal_rep2_name' => sanitize_text_field(wp_unslash($_POST['legal_rep2_name'] ?? '')),
            'legal_rep2_email' => sanitize_email(wp_unslash($_POST['legal_rep2_email'] ?? '')),
            'legal_rep2_phone' => sanitize_text_field(wp_unslash($_POST['legal_rep2_phone'] ?? '')),
            'absence_count' => max(0, (float) str_replace(',', '.', wp_unslash($_POST['absence_count'] ?? '0'))),
            'absence_rate' => max(0, (float) str_replace(',', '.', wp_unslash($_POST['absence_rate'] ?? '0'))),
            'absence_justified_count' => max(0, (float) str_replace(',', '.', wp_unslash($_POST['absence_justified_count'] ?? '0'))),
            'lateness_count' => max(0, (int) ($_POST['lateness_count'] ?? 0)),
            'removal_count' => max(0, (int) ($_POST['removal_count'] ?? 0)),
            'comment_count' => max(0, (int) ($_POST['comment_count'] ?? 0)),
            'contract_status' => $contract_status,
            'contract_break_date' => $contract_break_date ?: null,
            'disciplinary_warning_received' => (!empty($_POST['disciplinary_warning_received']) || $disciplinary_warning_date !== '') ? 1 : 0,
            'disciplinary_warning_date' => $disciplinary_warning_date ?: null,
            'wellbeing_council_convoked' => (!empty($_POST['wellbeing_council_convoked']) || $wellbeing_council_date !== '') ? 1 : 0,
            'wellbeing_council_date' => $wellbeing_council_date ?: null,
            'verified_at' => $verified_at,
            'verified_by' => $verified_by,
            'admin_notes' => sanitize_textarea_field(wp_unslash($_POST['admin_notes'] ?? '')),
            'updated_at' => GCA_Database::now(),
        );

        if ($linked_trainer) {
            $data['trainer_name'] = (string) $linked_trainer->name;
            $data['trainer_company'] = (string) $linked_trainer->company;
            $data['trainer_company_address'] = (string) ($linked_trainer->company_address ?? '');
            $data['trainer_company_zip'] = (string) ($linked_trainer->company_zip ?? '');
            $data['trainer_company_city'] = (string) ($linked_trainer->company_city ?? '');
            $data['trainer_email'] = (string) $linked_trainer->email;
            $data['trainer_phone'] = (string) $linked_trainer->phone;
        }

        if ($data['gender'] === '') {
            $data['gender'] = $this->infer_gender_from_first_name($data['first_name']);
        }

        $manual_fields = $student_verified
            ? $this->student_manual_editable_fields()
            : $this->changed_manual_fields($before ?: array(), $data, $this->student_manual_editable_fields());
        if ($manual_fields) {
            $data['manual_fields_json'] = GCA_Database::merge_manual_fields($before['manual_fields_json'] ?? '', $manual_fields);
        }

        $formats = array();
        foreach ($data as $field => $_value) {
            if (in_array($field, array('class_id', 'trainer_id', 'lateness_count', 'removal_count', 'comment_count', 'disciplinary_warning_received', 'wellbeing_council_convoked', 'verified_by'), true)) {
                $formats[] = '%d';
            } elseif (in_array($field, array('absence_count', 'absence_rate', 'absence_justified_count'), true)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        if ($student_id > 0) {
            $wpdb->update($table, $data, array('id' => $student_id), $formats, array('%d'));
        } else {
            $data['lateness_status'] = 'none';
            $data['created_at'] = GCA_Database::now();
            $formats[] = '%s';
            $formats[] = '%s';
            $wpdb->insert($table, $data, $formats);
            $student_id = (int) $wpdb->insert_id;
        }

        $after = $student_id > 0
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $student_id), ARRAY_A)
            : array();

        if ($student_id > 0 && !empty($_POST['remove_student_photo'])) {
            $this->delete_student_photo($student_id);
        }

        if ($student_id > 0 && !empty($_FILES['student_photo']['tmp_name']) && is_uploaded_file($_FILES['student_photo']['tmp_name'])) {
            $this->store_uploaded_student_photo($student_id, $_FILES['student_photo']);
        }

        GCA_Database::add_event($student_id, 'student_saved', 'Fiche enregistrée');
        $this->record_student_followup_decision_events($student_id, $before ?: array(), $after ?: array());
        $this->record_student_contract_events($student_id, $before ?: array(), $after ?: array());
        GCA_Database::log(
            'student_saved',
            'student',
            $student_id,
            array(
                'before' => $before ?: null,
                'after' => $after ?: null,
            )
        );
        $this->importer->recalculate_tasks($student_id);
        $this->sync_trainers_from_students();

        wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'student_saved')));
        exit;
    }

    public function handle_save_student_quick_edit(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_save_student_quick_edit');
        $this->sync_trainers_from_students();

        $redirect_args = array('gca_tab' => 'students', 'gca_notice' => 'students_quick_no_change');
        $return_class_id = isset($_POST['return_class_id']) ? (int) $_POST['return_class_id'] : 0;
        $return_gender_filter = isset($_POST['return_gender_filter']) ? sanitize_key(wp_unslash($_POST['return_gender_filter'])) : '';
        $return_verification_filter = isset($_POST['return_verification_filter'])
            ? $this->verification_filter_value((string) wp_unslash($_POST['return_verification_filter']))
            : '';
        $return_search = isset($_POST['return_search']) ? sanitize_text_field(wp_unslash($_POST['return_search'])) : '';
        if ($return_class_id > 0) {
            $redirect_args['class_id'] = $return_class_id;
        }
        if (in_array($return_gender_filter, array('male', 'female', 'unknown'), true)) {
            $redirect_args['gender_filter'] = $return_gender_filter;
        }
        if ($return_verification_filter !== '') {
            $redirect_args['verification_filter'] = $return_verification_filter;
        }
        if ($return_search !== '') {
            $redirect_args['gca_search'] = $return_search;
        }

        $raw_ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? wp_unslash($_POST['student_ids']) : array();
        $student_ids = array_values(array_unique(array_filter(array_map('intval', $raw_ids))));
        $raw_selected_ids = isset($_POST['selected_student_ids']) && is_array($_POST['selected_student_ids']) ? wp_unslash($_POST['selected_student_ids']) : array();
        $selected_student_ids = array_values(array_unique(array_filter(array_map('intval', $raw_selected_ids))));
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';
        if (!in_array($bulk_action, array('mark_verified', 'mark_unverified', 'gender_male', 'gender_female', 'gender_unknown'), true)) {
            $bulk_action = '';
        }
        $posted_students = isset($_POST['students']) && is_array($_POST['students']) ? wp_unslash($_POST['students']) : array();
        if (!$student_ids || !is_array($posted_students)) {
            wp_safe_redirect($this->page_url($redirect_args));
            exit;
        }
        $selected_student_ids = array_values(array_intersect($selected_student_ids, $student_ids));
        $selected_lookup = array_fill_keys($selected_student_ids, true);
        $bulk_applied = $bulk_action !== '' && !empty($selected_student_ids);

        $trainers_by_id = $this->trainer_profiles_by_id();
        $students_table = GCA_Database::table('students');
        $placeholders = implode(', ', array_fill(0, count($student_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, gender, email, phone, trainer_id, trainer_name, trainer_company, trainer_company_address, trainer_company_zip, trainer_company_city, trainer_email, trainer_phone, verified_at, verified_by, manual_fields_json FROM {$students_table} WHERE id IN ({$placeholders})",
                $student_ids
            ),
            ARRAY_A
        );
        $current_by_id = array();
        foreach ($rows as $row) {
            $current_by_id[(int) $row['id']] = $row;
        }

        $updated_ids = array();
        foreach ($student_ids as $student_id) {
            if (empty($current_by_id[$student_id])) {
                continue;
            }

            $posted = $posted_students[$student_id] ?? ($posted_students[(string) $student_id] ?? array());
            if (!is_array($posted)) {
                continue;
            }

            $data = array(
                'gender' => $this->normalize_gender($posted['gender'] ?? ''),
                'email' => sanitize_email((string) ($posted['email'] ?? '')),
                'phone' => sanitize_text_field((string) ($posted['phone'] ?? '')),
            );
            if (isset($selected_lookup[$student_id])) {
                if ($bulk_action === 'gender_male') {
                    $data['gender'] = 'male';
                } elseif ($bulk_action === 'gender_female') {
                    $data['gender'] = 'female';
                } elseif ($bulk_action === 'gender_unknown') {
                    $data['gender'] = '';
                }
            }
            $is_verified = !empty($posted['verified']);
            if (isset($selected_lookup[$student_id])) {
                if ($bulk_action === 'mark_verified') {
                    $is_verified = true;
                } elseif ($bulk_action === 'mark_unverified') {
                    $is_verified = false;
                }
            }
            $current_verified_at = $this->stored_verified_at($current_by_id[$student_id]['verified_at'] ?? '');
            $data['verified_at'] = $is_verified ? ($current_verified_at ?: GCA_Database::now()) : null;
            $data['verified_by'] = $is_verified ? (int) (($current_by_id[$student_id]['verified_by'] ?? 0) ?: get_current_user_id()) : 0;
            if (array_key_exists('trainer_id', $posted)) {
                $trainer_id = (int) $posted['trainer_id'];
                if ($trainer_id > 0 && isset($trainers_by_id[$trainer_id])) {
                    $trainer = $trainers_by_id[$trainer_id];
                    $data['trainer_id'] = $trainer_id;
                    $data['trainer_name'] = (string) $trainer->name;
                    $data['trainer_company'] = (string) $trainer->company;
                    $data['trainer_company_address'] = (string) ($trainer->company_address ?? '');
                    $data['trainer_company_zip'] = (string) ($trainer->company_zip ?? '');
                    $data['trainer_company_city'] = (string) ($trainer->company_city ?? '');
                    $data['trainer_email'] = (string) $trainer->email;
                    $data['trainer_phone'] = (string) $trainer->phone;
                } else {
                    $data['trainer_id'] = 0;
                    $data['trainer_name'] = '';
                    $data['trainer_company'] = '';
                    $data['trainer_company_address'] = '';
                    $data['trainer_company_zip'] = '';
                    $data['trainer_company_city'] = '';
                    $data['trainer_email'] = '';
                    $data['trainer_phone'] = '';
                }
            }

            $manual_fields = $is_verified
                ? $this->student_quick_manual_fields()
                : $this->changed_manual_fields($current_by_id[$student_id], $data, $this->student_quick_manual_fields());
            if ($manual_fields) {
                $manual_fields_json = GCA_Database::merge_manual_fields($current_by_id[$student_id]['manual_fields_json'] ?? '', $manual_fields);
                if ($this->manual_compare_value($current_by_id[$student_id]['manual_fields_json'] ?? '') !== $manual_fields_json) {
                    $data['manual_fields_json'] = $manual_fields_json;
                }
            }

            $changes = array();
            foreach ($data as $field => $value) {
                $current_value = $current_by_id[$student_id][$field] ?? '';
                $normalized_current = $current_value === null ? '' : trim((string) $current_value);
                $normalized_value = $value === null ? '' : trim((string) $value);
                if ($normalized_current !== $normalized_value) {
                    $changes[$field] = $value;
                }
            }

            if (!$changes) {
                continue;
            }

            $changes['updated_at'] = GCA_Database::now();
            $formats = array();
            foreach ($changes as $field => $_value) {
                $formats[] = in_array($field, array('trainer_id', 'verified_by'), true) ? '%d' : '%s';
            }
            $updated = $wpdb->update(
                $students_table,
                $changes,
                array('id' => $student_id),
                $formats,
                array('%d')
            );

            if ($updated !== false) {
                $updated_ids[] = $student_id;
                $event_fields = array_values(array_diff(array_keys($changes), array('updated_at', 'verified_by', 'manual_fields_json')));
                if ($bulk_applied && isset($selected_lookup[$student_id])) {
                    $event_fields[] = 'bulk_action_' . $bulk_action;
                }
                GCA_Database::add_event(
                    $student_id,
                    'student_quick_updated',
                    'Modification rapide',
                    array('fields' => $event_fields)
                );
            }
        }

        if ($updated_ids) {
            $redirect_args['gca_notice'] = $bulk_applied ? 'students_bulk_saved' : 'students_quick_saved';
            GCA_Database::log(
                $bulk_applied ? 'students_bulk_updated' : 'students_quick_updated',
                'student',
                0,
                array('count' => count($updated_ids), 'student_ids' => $updated_ids, 'bulk_action' => $bulk_action, 'selected_student_ids' => $selected_student_ids)
            );
            $this->sync_trainers_from_students();
        } elseif ($bulk_action !== '' && !$selected_student_ids) {
            $redirect_args['gca_notice'] = 'students_bulk_missing';
        }

        wp_safe_redirect($this->page_url($redirect_args));
        exit;
    }

    public function handle_save_trainer(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_save_trainer');

        $trainer_id = isset($_POST['trainer_id']) ? (int) $_POST['trainer_id'] : 0;
        $trainers_table = GCA_Database::table('trainers');
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $company = sanitize_text_field(wp_unslash($_POST['company'] ?? ''));
        $company_address = sanitize_text_field(wp_unslash($_POST['company_address'] ?? ''));
        $company_zip = sanitize_text_field(wp_unslash($_POST['company_zip'] ?? ''));
        $company_city = sanitize_text_field(wp_unslash($_POST['company_city'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $salutation = sanitize_text_field(wp_unslash($_POST['salutation'] ?? ''));

        if (trim($name) === '' && !is_email($email)) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'trainers', 'gca_notice' => 'trainer_missing')));
            exit;
        }

        $before = $trainer_id > 0
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$trainers_table} WHERE id = %d", $trainer_id), ARRAY_A)
            : array();
        $trainer_verified = !empty($_POST['trainer_verified']);
        $verified_at = $trainer_verified ? ($this->stored_verified_at($before['verified_at'] ?? '') ?: GCA_Database::now()) : null;
        $verified_by = $trainer_verified ? (int) (($before['verified_by'] ?? 0) ?: get_current_user_id()) : 0;

        $data = array(
            'name' => $name,
            'company' => $company,
            'company_address' => $company_address,
            'company_zip' => $company_zip,
            'company_city' => $company_city,
            'email' => $email,
            'phone' => $phone,
            'salutation' => $salutation !== '' ? $salutation : $this->default_trainer_salutation($name),
            'verified_at' => $verified_at,
            'verified_by' => $verified_by,
            'updated_at' => GCA_Database::now(),
        );
        $data_formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s');

        if ($trainer_id > 0 && $before) {
            $update_data = $data;
            $update_formats = $data_formats;
            $this->add_manual_fields_lock($update_data, $update_formats, $before, $this->trainer_manual_editable_fields(), $trainer_verified);
            $wpdb->update(
                $trainers_table,
                $update_data,
                array('id' => $trainer_id),
                $update_formats,
                array('%d')
            );
        } else {
            $base_key = $this->trainer_key($email, $name, $company, $phone);
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$trainers_table} WHERE trainer_key = %s", $base_key), ARRAY_A);
            if (!$existing && is_email($email)) {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$trainers_table} WHERE LOWER(email) = LOWER(%s) LIMIT 1", $email), ARRAY_A);
            }
            $key = $base_key;
            if ($existing) {
                $trainer_id = (int) $existing['id'];
                $before = $existing;
                $update_data = $data;
                $update_formats = $data_formats;
                $this->add_manual_fields_lock($update_data, $update_formats, $before, $this->trainer_manual_editable_fields(), $trainer_verified);
                $wpdb->update(
                    $trainers_table,
                    $update_data,
                    array('id' => $trainer_id),
                    $update_formats,
                    array('%d')
                );
            } else {
                $key = $this->unique_trainer_key($base_key);
                $insert_data = $data;
                $insert_formats = $data_formats;
                $this->add_manual_fields_lock($insert_data, $insert_formats, array(), $this->trainer_manual_editable_fields(), $trainer_verified);
                $insert_data['trainer_key'] = $key;
                $insert_data['created_at'] = GCA_Database::now();
                $wpdb->insert(
                    $trainers_table,
                    $insert_data,
                    array_merge($insert_formats, array('%s', '%s'))
                );
                $trainer_id = (int) $wpdb->insert_id;
            }
        }

        $updated_student_ids = $this->propagate_trainer_to_students($trainer_id, array(), 'student_trainer_updated');
        $after = $trainer_id > 0
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$trainers_table} WHERE id = %d", $trainer_id), ARRAY_A)
            : array();

        GCA_Database::log(
            'trainer_saved',
            'trainer',
            $trainer_id,
            array(
                'before' => $before ?: null,
                'after' => $after ?: null,
                'updated_student_ids' => $updated_student_ids,
            )
        );

        wp_safe_redirect($this->page_url(array('gca_tab' => 'trainers', 'trainer_id' => $trainer_id, 'gca_notice' => 'trainer_saved')));
        exit;
    }

    public function handle_save_trainer_assignments(): void
    {
        $this->require_manage();

        $trainer_id = isset($_POST['trainer_id']) ? (int) $_POST['trainer_id'] : 0;
        check_admin_referer('gca_save_trainer_assignments_' . $trainer_id);

        $trainer = $this->trainer_by_id($trainer_id);
        if (!$trainer) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'trainers', 'gca_notice' => 'not_found')));
            exit;
        }

        $add_ids = isset($_POST['add_student_ids']) && is_array($_POST['add_student_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', wp_unslash($_POST['add_student_ids'])))))
            : array();
        $remove_ids = isset($_POST['remove_student_ids']) && is_array($_POST['remove_student_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', wp_unslash($_POST['remove_student_ids'])))))
            : array();

        $removed_ids = $this->clear_trainer_from_students($trainer_id, $remove_ids);
        $added_ids = $this->propagate_trainer_to_students($trainer_id, $add_ids, 'student_trainer_assigned');

        GCA_Database::log(
            'trainer_assignments_saved',
            'trainer',
            $trainer_id,
            array(
                'trainer_name' => $this->trainer_display_name($trainer),
                'added_student_ids' => $added_ids,
                'removed_student_ids' => $removed_ids,
            )
        );

        wp_safe_redirect($this->page_url(array('gca_tab' => 'trainers', 'trainer_id' => $trainer_id, 'gca_notice' => 'trainer_assignments_saved')));
        exit;
    }

    public function handle_student_photo(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
        check_admin_referer('gca_student_photo_' . $student_id);

        $student = $wpdb->get_row($wpdb->prepare("SELECT photo_path FROM " . GCA_Database::table('students') . " WHERE id = %d", $student_id));
        $path = $student ? $this->private_path_from_relative((string) $student->photo_path) : '';

        if ($path === '' || !is_readable($path)) {
            status_header(404);
            exit;
        }

        $type = wp_check_filetype($path);
        nocache_headers();
        header('Content-Type: ' . ($type['type'] ?: 'image/jpeg'));
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function handle_send_email(): void
    {
        global $wpdb;

        $this->require_manage();
        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        check_admin_referer('gca_send_email_' . $task_id);

        $tasks = GCA_Database::table('tasks');
        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $communications = GCA_Database::table('communications');

        $task = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*, s.*, t.id AS task_id, c.id AS class_id, c.name AS class_name
                 FROM {$tasks} t
                 INNER JOIN {$students} s ON s.id = t.student_id
                 INNER JOIN {$classes} c ON c.id = s.class_id
                 WHERE t.id = %d AND t.status = 'pending'",
                $task_id
            )
        );

        if (!$task) {
            $this->redirect_notice('dashboard', 'not_found');
        }

        $to = $this->email_list(wp_unslash($_POST['recipients_to'] ?? ''));
        $cc = $this->email_list(wp_unslash($_POST['recipients_cc'] ?? ''));
        $bcc = $this->email_list(wp_unslash($_POST['recipients_bcc'] ?? ''));
        $without_employer = $this->student_without_employer($task);
        if ($without_employer) {
            [$to, $cc, $bcc] = $this->remove_student_trainer_recipients($to, $cc, $bcc, $task);
        }
        $bcc = array_values(array_unique(array_merge($bcc, $this->always_bcc_emails())));
        [$to, $cc, $bcc] = $this->dedupe_recipient_groups($to, $cc, $bcc);

        if (!$to) {
            $notice = $without_employer ? 'student_contract_broken' : 'email_failed';
            wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_action' => 'prepare_email', 'task_id' => $task_id, 'gca_notice' => $notice)));
            exit;
        }

        $requires_convocation_date = $this->task_requires_convocation_date($task);
        $convocation_date = $requires_convocation_date ? $this->display_date_only(wp_unslash($_POST['convocation_date'] ?? '')) : '';
        if ($requires_convocation_date && $convocation_date === '') {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_action' => 'prepare_email', 'task_id' => $task_id, 'gca_notice' => 'missing_convocation_date')));
            exit;
        }

        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $body_template = GCA_Database::sanitize_message_body(wp_unslash($_POST['body'] ?? ''));
        $detention_no_show_note = $this->detention_no_show_note_for_student($task);
        $body = $this->replace_variables($body_template, $task, array('convocation_date' => $convocation_date, 'detention_no_show_note' => $detention_no_show_note));
        if ($this->task_is_lateness_convocation($task)) {
            $body = $this->append_note_if_missing($body, $detention_no_show_note);
        }
        $subject = $this->replace_variables($subject, $task, array('convocation_date' => $convocation_date));
        $subject = $this->ensure_subject_class_code($subject, $task);
        $attachments = array();
        $attachment_path = '';

        if ($requires_convocation_date) {
            $class = (object) array('id' => $task->class_id, 'name' => $task->class_name);
            $pdf_type = $task->task_type === 'absence' ? 'absence' : 'lateness';
            $pdf_meta = array(
                'convocation_date' => $convocation_date,
                'first_convocation_date' => $this->first_convocation_date_for_student($task),
            );
            $attachment_path = GCA_PDF::create_convocation_pdf($task, $class, $pdf_type, $pdf_meta);
            $attachments[] = $attachment_path;
        }

        $headers = array_merge(array('Content-Type: text/html; charset=UTF-8'), $this->mail_sender_headers());
        foreach ($cc as $email) {
            $headers[] = 'Cc: ' . $email;
        }
        foreach ($bcc as $email) {
            $headers[] = 'Bcc: ' . $email;
        }

        $sent = $this->send_plugin_mail($to, $subject, $this->email_body_with_signature($body), $headers, $attachments);

        if (!$sent) {
            $this->remember_last_mail_error();
            wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_action' => 'prepare_email', 'task_id' => $task_id, 'gca_notice' => 'email_failed')));
            exit;
        }

        $communication_type = sanitize_key(wp_unslash($_POST['communication_type'] ?? $this->communication_type_for_task($task)));
        $wpdb->insert(
            $communications,
            array(
                'student_id' => (int) $task->student_id,
                'communication_type' => $communication_type,
                'subject' => $subject,
                'body' => $body,
                'recipients_to' => implode(', ', $to),
                'recipients_cc' => implode(', ', $cc),
                'recipients_bcc' => implode(', ', $bcc),
                'attachment_path' => $attachment_path,
                'sent_at' => GCA_Database::now(),
                'created_by' => get_current_user_id(),
                'teacher_id' => GCA_Database::current_teacher_id(),
                'status' => 'sent',
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
        $communication_id = (int) $wpdb->insert_id;

        $this->update_student_after_communication((int) $task->student_id, $communication_type);
        $this->importer->resolve_task($task_id);
        GCA_Database::add_event((int) $task->student_id, 'communication_sent', $this->communication_type_label($communication_type), array('type' => $communication_type));
        GCA_Database::log(
            'email_sent',
            'student',
            (int) $task->student_id,
            array(
                'communication_id' => $communication_id,
                'type' => $communication_type,
                'to' => $to,
                'cc' => $cc,
                'bcc' => $bcc,
                'subject' => $subject,
                'convocation_date' => $convocation_date,
                'attachment_path' => $attachment_path ? GCA_Database::private_relative_path($attachment_path) : '',
            )
        );
        $this->importer->recalculate_tasks((int) $task->student_id);

        wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_notice' => 'email_sent')));
        exit;
    }

    public function handle_justify_student_absence(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_justify_student_absence');

        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $raw_absence_date = trim(sanitize_text_field(wp_unslash($_POST['absence_date'] ?? '')));
        $absence_date = $this->normalize_date_for_storage($raw_absence_date);
        $delta = max(0.0, (float) str_replace(',', '.', wp_unslash($_POST['absence_justified_delta'] ?? '0')));

        if ($student_id <= 0 || $raw_absence_date === '' || $delta <= 0) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_notice' => 'absence_justification_missing')) . '#gca-absence-justification');
            exit;
        }

        if ($absence_date === '') {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_notice' => 'absence_justification_bad_date')) . '#gca-absence-justification');
            exit;
        }

        $students = GCA_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT id, absence_count, absence_rate, absence_justified_count FROM {$students} WHERE id = %d AND archived_at IS NULL", $student_id));
        if (!$student) {
            $this->redirect_notice('dashboard', 'not_found');
        }

        $this->save_absence_justification_for_student(
            $student,
            $absence_date,
            $delta,
            array('source' => 'manual')
        );
        $this->importer->recalculate_tasks($student_id);

        wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_notice' => 'absence_justified')) . '#gca-absence-justification');
        exit;
    }

    public function handle_justify_absence_task(): void
    {
        global $wpdb;

        $this->require_manage();
        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        check_admin_referer('gca_justify_absence_task_' . $task_id);

        $tasks = GCA_Database::table('tasks');
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks} WHERE id = %d AND status = 'pending'", $task_id));
        if (!$task || !$this->task_can_be_justified_without_email($task)) {
            $this->redirect_notice('dashboard', 'not_found');
        }

        $raw_absence_date = trim(sanitize_text_field(wp_unslash($_POST['absence_date'] ?? '')));
        $absence_date = $this->normalize_date_for_storage($raw_absence_date);
        $delta = max(0.0, (float) str_replace(',', '.', wp_unslash($_POST['absence_justified_delta'] ?? '0')));
        if ($raw_absence_date === '' || $delta <= 0) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_notice' => 'absence_justification_missing')) . '#gca-situations');
            exit;
        }

        if ($absence_date === '') {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_notice' => 'absence_justification_bad_date')) . '#gca-situations');
            exit;
        }

        $students = GCA_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT id, absence_count, absence_rate, absence_justified_count FROM {$students} WHERE id = %d", (int) $task->student_id));
        if (!$student) {
            $this->redirect_notice('dashboard', 'not_found');
        }

        $result = $this->save_absence_justification_for_student(
            $student,
            $absence_date,
            $delta,
            array(
                'source' => 'task',
                'task_id' => $task_id,
                'task_type' => (string) $task->task_type,
                'threshold_value' => (string) $task->threshold_value,
                'message' => (string) $task->message,
            )
        );
        $this->importer->resolve_task($task_id);
        GCA_Database::log(
            'absence_justified_without_email',
            'task',
            $task_id,
            array(
                'student_id' => (int) $task->student_id,
                'task_type' => (string) $task->task_type,
                'threshold_value' => (string) $task->threshold_value,
                'absence_date' => $absence_date,
                'justification_id' => $result['justification_id'],
                'justified_delta' => $result['period_count'],
                'justified_total' => $result['justified_total'],
            )
        );
        $this->importer->recalculate_tasks((int) $task->student_id);

        wp_safe_redirect($this->page_url(array('gca_tab' => 'dashboard', 'gca_notice' => 'absence_justified')) . '#gca-situations');
        exit;
    }

    private function save_absence_justification_for_student(object $student, string $absence_date, float $period_count, array $context = array()): array
    {
        global $wpdb;

        $student_id = (int) $student->id;
        $period_count = max(0.0, $period_count);
        $source = sanitize_key((string) ($context['source'] ?? 'manual')) ?: 'manual';
        $task_id = max(0, (int) ($context['task_id'] ?? 0));
        $notes = sanitize_textarea_field((string) ($context['notes'] ?? ''));
        $now = GCA_Database::now();
        $teacher_id = GCA_Database::current_teacher_id();
        $absence_justifications = GCA_Database::table('absence_justifications');
        $students = GCA_Database::table('students');

        $wpdb->insert(
            $absence_justifications,
            array(
                'student_id' => $student_id,
                'absence_date' => $absence_date,
                'period_count' => $period_count,
                'source' => $source,
                'task_id' => $task_id,
                'notes' => $notes,
                'created_by' => get_current_user_id(),
                'teacher_id' => $teacher_id,
                'created_at' => $now,
            ),
            array('%d', '%s', '%f', '%s', '%d', '%s', '%d', '%d', '%s')
        );
        $justification_id = (int) $wpdb->insert_id;
        $previous_justified_total = max(0.0, (float) ($student->absence_justified_count ?? 0));
        $new_justified_total = $previous_justified_total + $period_count;

        $wpdb->update(
            $students,
            array(
                'absence_justified_count' => $new_justified_total,
                'updated_at' => $now,
            ),
            array('id' => $student_id),
            array('%f', '%s'),
            array('%d')
        );

        $after = (object) array(
            'absence_count' => max(0.0, (float) ($student->absence_count ?? 0)),
            'absence_rate' => max(0.0, (float) ($student->absence_rate ?? 0)),
            'absence_justified_count' => $new_justified_total,
        );
        $payload = array(
            'absence_justification_id' => $justification_id,
            'absence_date' => $absence_date,
            'source' => $source,
            'task_id' => $task_id,
            'task_type' => (string) ($context['task_type'] ?? ''),
            'threshold_value' => (string) ($context['threshold_value'] ?? ''),
            'message' => (string) ($context['message'] ?? ''),
            'justified_delta' => $period_count,
            'period_count' => $period_count,
            'previous_justified_total' => $previous_justified_total,
            'justified_total' => $new_justified_total,
            'imported_absence_count' => max(0.0, (float) ($student->absence_count ?? 0)),
            'remaining_absence_count' => $this->actionable_absence_count($after),
            'remaining_absence_rate' => $this->actionable_absence_rate($after),
            'resolved_by' => get_current_user_id(),
        );

        GCA_Database::add_event(
            $student_id,
            'absence_justified',
            $source === 'task' ? 'Absence validée sans envoi d’e-mail' : 'Justificatif d’absence enregistré',
            $payload
        );
        if ($source !== 'task') {
            GCA_Database::log('absence_justification_saved', 'student', $student_id, $payload);
        }

        return array(
            'justification_id' => $justification_id,
            'period_count' => $period_count,
            'justified_total' => $new_justified_total,
        );
    }

    public function handle_mark_task_done(): void
    {
        global $wpdb;

        $this->require_manage();
        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        check_admin_referer('gca_mark_task_done_' . $task_id);

        $tasks = GCA_Database::table('tasks');
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks} WHERE id = %d", $task_id));

        $this->importer->resolve_task($task_id);
        if ($task) {
            GCA_Database::add_event(
                (int) $task->student_id,
                'task_resolved',
                $this->task_type_label((string) $task->task_type),
                array(
                    'task_type' => (string) $task->task_type,
                    'threshold_value' => (string) $task->threshold_value,
                    'message' => (string) $task->message,
                )
            );
        }
        GCA_Database::log('task_resolved', 'task', $task_id);
        $this->redirect_notice('dashboard', 'task_done');
    }

    public function handle_save_task(): void
    {
        global $wpdb;

        $this->require_manage();
        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        check_admin_referer('gca_save_task_' . $task_id);

        $tasks = GCA_Database::table('tasks');
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks} WHERE id = %d", $task_id), ARRAY_A);
        if (!$before) {
            $this->redirect_notice('dashboard', 'not_found');
        }

        $status = isset($_POST['status']) && $_POST['status'] === 'resolved' ? 'resolved' : 'pending';
        $wpdb->update(
            $tasks,
            array(
                'task_type' => sanitize_key(wp_unslash($_POST['task_type'] ?? $before['task_type'])),
                'threshold_value' => sanitize_text_field(wp_unslash($_POST['threshold_value'] ?? '')),
                'status' => $status,
                'action_label' => sanitize_text_field(wp_unslash($_POST['action_label'] ?? '')),
                'message' => sanitize_textarea_field(wp_unslash($_POST['message'] ?? '')),
                'resolved_at' => $status === 'resolved' ? GCA_Database::now() : null,
            ),
            array('id' => $task_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        GCA_Database::log('task_saved', 'task', $task_id, array('before' => $before));
        $this->redirect_notice('dashboard', 'task_saved');
    }

    public function handle_delete_task(): void
    {
        global $wpdb;

        $this->require_manage();
        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        check_admin_referer('gca_delete_task_' . $task_id);

        $tasks = GCA_Database::table('tasks');
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks} WHERE id = %d", $task_id), ARRAY_A);
        if ($before) {
            if ($this->task_is_absence_followup((object) $before)) {
                $wpdb->update(
                    $tasks,
                    array(
                        'status' => 'deleted',
                        'resolved_at' => GCA_Database::now(),
                    ),
                    array('id' => $task_id),
                    array('%s', '%s'),
                    array('%d')
                );
            } else {
                $wpdb->delete($tasks, array('id' => $task_id), array('%d'));
            }
            GCA_Database::log('task_deleted', 'task', $task_id, array('before' => $before));
        }

        $this->redirect_notice('dashboard', 'task_deleted');
    }

    public function handle_save_action(): void
    {
        global $wpdb;

        $this->require_manage();
        $source = sanitize_key(wp_unslash($_POST['source'] ?? ''));
        $action_id = isset($_POST['action_id']) ? (int) $_POST['action_id'] : 0;
        check_admin_referer('gca_save_action_' . $source . '_' . $action_id);

        if ($source === 'communication') {
            $table = GCA_Database::table('communications');
            $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $action_id), ARRAY_A);
            if (!$before) {
                $this->redirect_notice('actions', 'not_found');
            }
            $wpdb->update(
                $table,
                array(
                    'communication_type' => sanitize_key(wp_unslash($_POST['communication_type'] ?? $before['communication_type'])),
                    'subject' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
                    'body' => GCA_Database::sanitize_message_body(wp_unslash($_POST['body'] ?? '')),
                    'recipients_to' => sanitize_text_field(wp_unslash($_POST['recipients_to'] ?? '')),
                    'recipients_cc' => sanitize_text_field(wp_unslash($_POST['recipients_cc'] ?? '')),
                    'status' => isset($_POST['status']) && $_POST['status'] === 'cancelled' ? 'cancelled' : 'sent',
                ),
                array('id' => $action_id),
                array('%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            GCA_Database::log('action_saved', 'communication', $action_id, array('before' => $before));
            $this->importer->recalculate_tasks((int) $before['student_id']);
            $this->redirect_notice('actions', 'action_saved');
        }

        if ($source === 'event') {
            $table = GCA_Database::table('events');
            $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $action_id), ARRAY_A);
            if (!$before) {
                $this->redirect_notice('actions', 'not_found');
            }
            $payload_json = trim((string) wp_unslash($_POST['payload_json'] ?? ''));
            json_decode($payload_json, true);
            if ($payload_json !== '' && json_last_error() !== JSON_ERROR_NONE) {
                $payload_json = (string) $before['payload_json'];
            }
            $wpdb->update(
                $table,
                array(
                    'event_type' => sanitize_key(wp_unslash($_POST['event_type'] ?? $before['event_type'])),
                    'status' => sanitize_text_field(wp_unslash($_POST['status'] ?? '')),
                    'payload_json' => $payload_json,
                ),
                array('id' => $action_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            GCA_Database::log('action_saved', 'event', $action_id, array('before' => $before));
            $this->redirect_notice('actions', 'action_saved');
        }

        $this->redirect_notice('actions', 'not_found');
    }

    public function handle_delete_action(): void
    {
        global $wpdb;

        $this->require_manage();
        $source = sanitize_key(wp_unslash($_POST['source'] ?? ''));
        $action_id = isset($_POST['action_id']) ? (int) $_POST['action_id'] : 0;
        check_admin_referer('gca_delete_action_' . $source . '_' . $action_id);

        if ($source === 'communication') {
            $table = GCA_Database::table('communications');
            $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $action_id), ARRAY_A);
            if ($before) {
                if (!empty($before['attachment_path'])) {
                    $this->delete_private_file((string) $before['attachment_path']);
                }
                $wpdb->delete($table, array('id' => $action_id), array('%d'));
                GCA_Database::log('action_deleted', 'communication', $action_id, array('before' => $before));
                $this->importer->recalculate_tasks((int) $before['student_id']);
            }
            $this->redirect_notice('actions', 'action_deleted');
        }

        if ($source === 'event') {
            $table = GCA_Database::table('events');
            $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $action_id), ARRAY_A);
            if ($before) {
                $wpdb->delete($table, array('id' => $action_id), array('%d'));
                GCA_Database::log('action_deleted', 'event', $action_id, array('before' => $before));
            }
            $this->redirect_notice('actions', 'action_deleted');
        }

        $this->redirect_notice('actions', 'not_found');
    }

    public function handle_register_detention_no_show(): void
    {
        global $wpdb;

        $this->require_manage();
        $communication_id = isset($_POST['communication_id']) ? (int) $_POST['communication_id'] : 0;
        check_admin_referer('gca_register_detention_no_show_' . $communication_id);

        $communications = GCA_Database::table('communications');
        $communication = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$communications} WHERE id = %d", $communication_id)
        );

        if (!$communication || !in_array((string) $communication->communication_type, array('lateness_3', 'lateness_6'), true)) {
            $this->redirect_notice('actions', 'not_found');
        }

        $student_id = (int) $communication->student_id;
        $detention_date = $this->display_date_only(wp_unslash($_POST['detention_no_show_date'] ?? ''));
        if ($detention_date === '') {
            $detention_date = wp_date('d.m.Y');
        }

        GCA_Database::add_event(
            $student_id,
            'detention_no_show',
            'Retenue manquée',
            array(
                'date' => $detention_date,
                'communication_id' => $communication_id,
                'communication_type' => (string) $communication->communication_type,
            )
        );
        GCA_Database::log(
            'detention_no_show_saved',
            'communication',
            $communication_id,
            array(
                'student_id' => $student_id,
                'date' => $detention_date,
                'communication_type' => (string) $communication->communication_type,
            )
        );
        $this->importer->recalculate_tasks($student_id);

        $args = array('gca_tab' => 'actions', 'gca_notice' => 'detention_no_show_saved');
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        if ($class_id > 0) {
            $args['class_id'] = $class_id;
        }

        wp_safe_redirect($this->page_url($args));
        exit;
    }

    public function handle_archive_student(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gca_archive_student_' . $student_id);

        $wpdb->update(
            GCA_Database::table('students'),
            array('archived_at' => GCA_Database::now(), 'updated_at' => GCA_Database::now()),
            array('id' => $student_id),
            array('%s', '%s'),
            array('%d')
        );

        GCA_Database::add_event($student_id, 'student_archived', 'Fiche archivée');
        GCA_Database::log('student_archived', 'student', $student_id);
        $this->redirect_notice('classes', 'student_archived');
    }

    public function handle_anonymize_student(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gca_anonymize_student_' . $student_id);

        $students = GCA_Database::table('students');
        $communications = GCA_Database::table('communications');
        $absence_justifications = GCA_Database::table('absence_justifications');
        $disciplinary_exclusions = GCA_Database::table('disciplinary_exclusions');
        $grades = GCA_Database::table('grades');
        $anonymous_name = 'Anonyme-' . $student_id;

        $this->delete_student_photo($student_id);

        $wpdb->update(
            $students,
            array(
                'last_name' => $anonymous_name,
                'first_name' => '',
                'date_of_birth' => null,
                'gender' => '',
                'photo_path' => '',
                'phone' => '',
                'email' => '',
                'trainer_id' => 0,
                'trainer_name' => '',
                'trainer_company' => '',
                'trainer_company_address' => '',
                'trainer_company_zip' => '',
                'trainer_company_city' => '',
                'trainer_email' => '',
                'trainer_phone' => '',
                'field_trainer_name' => '',
                'field_trainer_email' => '',
                'field_trainer_phone' => '',
                'legal_rep1_name' => '',
                'legal_rep1_email' => '',
                'legal_rep1_phone' => '',
                'legal_rep2_name' => '',
                'legal_rep2_email' => '',
                'legal_rep2_phone' => '',
                'disciplinary_warning_received' => 0,
                'disciplinary_warning_date' => null,
                'expulsion_received' => 0,
                'expulsion_date' => null,
                'wellbeing_council_convoked' => 0,
                'wellbeing_council_date' => null,
                'admin_notes' => '',
                'archived_at' => GCA_Database::now(),
                'updated_at' => GCA_Database::now(),
            ),
            array('id' => $student_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        $wpdb->update(
            $communications,
            array(
                'subject' => '[anonymise]',
                'body' => '[anonymise]',
                'recipients_to' => '',
                'recipients_cc' => '',
                'recipients_bcc' => '',
            ),
            array('student_id' => $student_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        $this->delete_student_pdfs($student_id);
        $this->delete_student_reports($student_id);
        $wpdb->delete($absence_justifications, array('student_id' => $student_id), array('%d'));
        $wpdb->delete($disciplinary_exclusions, array('student_id' => $student_id), array('%d'));
        $wpdb->delete($grades, array('student_id' => $student_id), array('%d'));
        GCA_Database::add_event($student_id, 'student_anonymized', 'Fiche anonymisée');
        GCA_Database::log('student_anonymized', 'student', $student_id);

        $this->redirect_notice('classes', 'student_anonymized');
    }

    public function handle_delete_student(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gca_delete_student_' . $student_id);

        $students = GCA_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$students} WHERE id = %d", $student_id));
        $class_id = $student ? (int) $student->class_id : 0;

        if ($student) {
            $this->delete_student_records($student_id);
            GCA_Database::log('student_deleted', 'student', $student_id, array('before' => (array) $student));
        }

        wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'class_id' => $class_id, 'gca_notice' => 'student_deleted')));
        exit;
    }

    public function handle_delete_class(): void
    {
        global $wpdb;

        $this->require_manage();
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        check_admin_referer('gca_delete_class_' . $class_id);

        $classes = GCA_Database::table('classes');
        $students = GCA_Database::table('students');
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$classes} WHERE id = %d", $class_id));

        if (!$class) {
            $this->redirect_notice('classes', 'not_found');
        }

        $student_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$students} WHERE class_id = %d", $class_id));
        foreach ($student_ids as $student_id) {
            $this->delete_student_records((int) $student_id);
        }

        $wpdb->delete($classes, array('id' => $class_id), array('%d'));
        GCA_Database::log('class_deleted', 'class', $class_id, array('before' => (array) $class, 'student_ids' => array_map('intval', $student_ids)));
        $this->redirect_notice('classes', 'class_deleted');
    }

    public function handle_confirm_warning(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gca_confirm_warning_' . $student_id);

        $students = GCA_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$students} WHERE id = %d", $student_id));

        if (!$student) {
            $this->redirect_notice('classes', 'not_found');
        }

        $date = $this->normalize_date_for_storage(wp_unslash($_POST['warning_date'] ?? wp_date('d.m.Y')));
        if ($date === '') {
            $date = wp_date('Y-m-d');
        }
        $confirmed_at = $date . ' 00:00:00';
        $wpdb->update(
            $students,
            array(
                'lateness_status' => 'warning_confirmed',
                'warning_confirmed_at' => $confirmed_at,
                'warning_lateness_count' => (int) $student->lateness_count,
                'updated_at' => GCA_Database::now(),
            ),
            array('id' => $student_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        GCA_Database::add_event($student_id, 'warning_confirmed', 'Courrier d’avertissement confirmé', array('lateness_count' => (int) $student->lateness_count));
        $this->importer->recalculate_tasks($student_id);

        wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'warning_confirmed')));
        exit;
    }

    public function handle_save_exclusion(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gca_save_exclusion_' . $student_id);

        $students = GCA_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$students} WHERE id = %d", $student_id));

        if (!$student) {
            $this->redirect_notice('classes', 'not_found');
        }

        $start = $this->normalize_date_for_storage(wp_unslash($_POST['exclusion_start'] ?? ''));
        $end = $this->normalize_date_for_storage(wp_unslash($_POST['exclusion_end'] ?? ''));

        if ($start === '' && $end === '') {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'missing_exclusion_date')));
            exit;
        }

        if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'bad_exclusion_dates')));
            exit;
        }

        $wpdb->update(
            $students,
            array(
                'lateness_status' => 'exclusion_confirmed',
                'exclusion_start' => $start ?: null,
                'exclusion_end' => $end ?: null,
                'exclusion_lateness_count' => (int) $student->lateness_count,
                'updated_at' => GCA_Database::now(),
            ),
            array('id' => $student_id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );

        GCA_Database::add_event($student_id, 'exclusion_confirmed', 'Exclusion temporaire confirmée', array('start' => $start, 'end' => $end));
        $this->record_disciplinary_exclusion($student_id, $start, $end, 'Exclusion liée à la procédure retards.', false);
        $this->importer->recalculate_tasks($student_id);

        wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'exclusion_saved')));
        exit;
    }

    public function handle_save_disciplinary_exclusion(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gca_save_disciplinary_exclusion_' . $student_id);

        $students = GCA_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$students} WHERE id = %d", $student_id));

        if (!$student) {
            $this->redirect_notice('classes', 'not_found');
        }

        $start = $this->normalize_date_for_storage(wp_unslash($_POST['exclusion_start'] ?? ''));
        $end = $this->normalize_date_for_storage(wp_unslash($_POST['exclusion_end'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['exclusion_notes'] ?? ''));
        $exclusion_id = isset($_POST['exclusion_id']) ? (int) $_POST['exclusion_id'] : 0;

        if ($start === '' && $end === '') {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'missing_exclusion_date')));
            exit;
        }

        if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'bad_exclusion_dates')));
            exit;
        }

        if ($exclusion_id > 0) {
            $table = GCA_Database::table('disciplinary_exclusions');
            $before = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d AND student_id = %d",
                    $exclusion_id,
                    $student_id
                ),
                ARRAY_A
            );

            if (!$before) {
                $this->redirect_notice('classes', 'not_found');
            }

            $wpdb->update(
                $table,
                array(
                    'start_date' => $start ?: null,
                    'end_date' => $end ?: null,
                    'notes' => $notes,
                    'updated_at' => GCA_Database::now(),
                ),
                array('id' => $exclusion_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            $this->sync_student_expulsion_from_exclusions($student_id);
            $exclusion = (object) array('start_date' => $start, 'end_date' => $end, 'notes' => $notes);
            GCA_Database::add_event(
                $student_id,
                'disciplinary_exclusion_updated',
                'Exclusion modifiée',
                array(
                    'exclusion_id' => $exclusion_id,
                    'start' => $start,
                    'end' => $end,
                    'duration' => $this->disciplinary_exclusion_duration_label($exclusion),
                    'notes' => $notes,
                    'before' => $before,
                )
            );
            GCA_Database::log('disciplinary_exclusion_updated', 'student', $student_id, array('before' => $before, 'after' => (array) $exclusion));
            $this->importer->recalculate_tasks($student_id);

            wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'disciplinary_exclusion_updated')));
            exit;
        }

        $this->record_disciplinary_exclusion($student_id, $start, $end, $notes);
        $this->importer->recalculate_tasks($student_id);

        wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'disciplinary_exclusion_saved')));
        exit;
    }

    public function handle_delete_disciplinary_exclusion(): void
    {
        global $wpdb;

        $this->require_manage();
        $exclusion_id = isset($_POST['exclusion_id']) ? (int) $_POST['exclusion_id'] : 0;
        check_admin_referer('gca_delete_disciplinary_exclusion_' . $exclusion_id);

        $table = GCA_Database::table('disciplinary_exclusions');
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $exclusion_id));

        if (!$before) {
            $this->redirect_notice('classes', 'not_found');
        }

        $student_id = (int) $before->student_id;
        $wpdb->delete($table, array('id' => $exclusion_id), array('%d'));
        $this->sync_student_expulsion_from_exclusions($student_id);

        GCA_Database::add_event(
            $student_id,
            'disciplinary_exclusion_deleted',
            'Exclusion supprimée',
            array(
                'exclusion_id' => $exclusion_id,
                'start' => (string) $before->start_date,
                'end' => (string) $before->end_date,
                'notes' => (string) $before->notes,
            )
        );
        GCA_Database::log('disciplinary_exclusion_deleted', 'student', $student_id, array('before' => (array) $before));
        $this->importer->recalculate_tasks($student_id);

        wp_safe_redirect($this->page_url(array('gca_tab' => 'classes', 'student_id' => $student_id, 'gca_notice' => 'disciplinary_exclusion_deleted')));
        exit;
    }

    public function handle_download_pdf(): void
    {
        global $wpdb;

        $this->require_manage();
        $communication_id = isset($_GET['communication_id']) ? (int) $_GET['communication_id'] : 0;
        check_admin_referer('gca_download_pdf_' . $communication_id);

        $communication = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . GCA_Database::table('communications') . " WHERE id = %d", $communication_id));

        if (!$communication || empty($communication->attachment_path) || !file_exists($communication->attachment_path)) {
            status_header(404);
            exit;
        }

        $private = realpath(GCA_Database::private_dir());
        $path = realpath($communication->attachment_path);

        if (!$private || !$path || !str_starts_with($path, trailingslashit($private))) {
            status_header(403);
            exit;
        }

        GCA_Database::log('pdf_downloaded', 'communication', $communication_id);

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function handle_export_student(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
        check_admin_referer('gca_export_student_' . $student_id);

        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $communications = GCA_Database::table('communications');
        $events = GCA_Database::table('events');
        $grades = GCA_Database::table('grades');
        $absence_justifications = GCA_Database::table('absence_justifications');
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, c.name AS class_name, c.school_year
                 FROM {$students} s
                 INNER JOIN {$classes} c ON c.id = s.class_id
                 WHERE s.id = %d",
                $student_id
            ),
            ARRAY_A
        );

        if (!$student) {
            status_header(404);
            exit;
        }

        GCA_Database::log('student_exported', 'student', $student_id);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="apprenti-' . $student_id . '.csv"');

        $out = fopen('php://output', 'wb');
        fputcsv($out, array('Section', 'Champ', 'Valeur'));
        foreach ($student as $key => $value) {
            fputcsv($out, array('Fiche', $key, $value));
        }
        $student_object = (object) $student;
        fputcsv($out, array('Fiche', 'absence_non_justified_count', $this->format_decimal($this->actionable_absence_count($student_object))));
        fputcsv($out, array('Fiche', 'absence_non_justified_rate', $this->format_decimal($this->actionable_absence_rate($student_object))));

        $comms = $wpdb->get_results($wpdb->prepare("SELECT communication_type, subject, recipients_to, recipients_cc, sent_at FROM {$communications} WHERE student_id = %d ORDER BY sent_at ASC", $student_id), ARRAY_A);
        foreach ($comms as $row) {
            fputcsv($out, array('Communication', $row['sent_at'] . ' - ' . $row['communication_type'], $row['subject'] . ' - A: ' . $row['recipients_to'] . ' - CC: ' . $row['recipients_cc']));
        }

        $student_grades = $wpdb->get_results($wpdb->prepare("SELECT assessment, grade_value FROM {$grades} WHERE student_id = %d ORDER BY assessment ASC, id ASC", $student_id), ARRAY_A);
        foreach ($student_grades as $row) {
            fputcsv($out, array('Notes', trim((string) ($row['assessment'] ?? '')), (string) $row['grade_value']));
        }

        $justifications = $wpdb->get_results($wpdb->prepare("SELECT absence_date, period_count, source, created_at FROM {$absence_justifications} WHERE student_id = %d ORDER BY absence_date ASC, id ASC", $student_id), ARRAY_A);
        foreach ($justifications as $row) {
            fputcsv(
                $out,
                array(
                    'Justificatifs absence',
                    $this->display_date_only((string) $row['absence_date']),
                    $this->format_decimal($row['period_count']) . ' période(s) - ' . (string) $row['source'] . ' - saisi le ' . $this->display_date((string) $row['created_at']),
                )
            );
        }

        $history = $wpdb->get_results($wpdb->prepare("SELECT event_type, status, created_at FROM {$events} WHERE student_id = %d ORDER BY created_at ASC", $student_id), ARRAY_A);
        foreach ($history as $row) {
            fputcsv($out, array('Historique', $row['created_at'] . ' - ' . $row['event_type'], $row['status']));
        }

        fclose($out);
        exit;
    }

    public function handle_export_students(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_export_students');

        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $users = $wpdb->users;

        $rows = $wpdb->get_results(
            "SELECT s.*, c.name AS class_name, c.school_year, c.status AS class_status, u.display_name AS verified_by_name
             FROM {$students} s
             LEFT JOIN {$classes} c ON c.id = s.class_id
             LEFT JOIN {$users} u ON u.ID = s.verified_by
             ORDER BY c.school_year DESC, c.name ASC, s.last_name ASC, s.first_name ASC"
        );

        $headers = array(
            'ID',
            'Statut fiche',
            'ID classe',
            'Année scolaire',
            'Classe',
            'Numéro court de classe',
            'Statut classe',
            'Nom',
            'Prénom',
            'Genre',
            'Date de naissance',
            'Téléphone élève',
            'E-mail élève',
            'Photo',
            'Statut contrat',
            'Date de rupture',
            'Suivi école jusqu’au',
            'Alerte rupture',
            'ID formateur responsable',
            'Formateur responsable',
            'Entreprise formatrice',
            'Adresse entreprise',
            'NPA entreprise',
            'Localité entreprise',
            'Adresse complète entreprise',
            'E-mail responsable',
            'Téléphone formateur',
            'Représentant légal 1 - nom',
            'Représentant légal 1 - e-mail',
            'Représentant légal 1 - téléphone',
            'Représentant légal 2 - nom',
            'Représentant légal 2 - e-mail',
            'Représentant légal 2 - téléphone',
            'Périodes d’absence',
            'Taux d’absence (%)',
            'Périodes justifiées',
            'Périodes non justifiées à traiter',
            'Taux non justifié calculé (%)',
            'Retards',
            'Mises à la porte',
            'Commentaires',
            'Statut retards',
            'Avertissement reçu',
            'Date avertissement',
            'Expulsion reçue',
            'Date expulsion',
            'Conseil de savoir-être',
            'Date conseil de savoir-être',
            'Exclusions enregistrées',
            'Actions à traiter',
            'Actions exécutées',
            'Communications',
            'Données vérifiées',
            'Vérifié le',
            'Vérifié par',
            'Notes récupérées',
            'Notes internes',
            'Champs protégés import',
            'Créé le',
            'Mis à jour le',
            'Archivé le',
        );

        $data = array();
        foreach ($rows as $student) {
            $data[] = array(
                (int) $student->id,
                trim((string) $student->archived_at) !== '' ? 'Archivée' : 'Active',
                (int) $student->class_id,
                (string) ($student->school_year ?? ''),
                (string) ($student->class_name ?? ''),
                $this->class_code_for_student($student),
                $this->status_label((string) ($student->class_status ?? 'active')),
                (string) $student->last_name,
                (string) $student->first_name,
                $this->gender_label((string) $student->gender),
                $this->display_date_only((string) $student->date_of_birth),
                (string) $student->phone,
                (string) $student->email,
                (string) $student->photo_path,
                $this->contract_status_label((string) $student->contract_status),
                $this->contract_break_date_label($student),
                $this->contract_follow_until_label($student),
                $this->contract_review_date_label($student),
                (int) $student->trainer_id,
                (string) $student->trainer_name,
                (string) $student->trainer_company,
                (string) $student->trainer_company_address,
                (string) $student->trainer_company_zip,
                (string) $student->trainer_company_city,
                $this->trainer_company_address_label($student),
                (string) $student->trainer_email,
                (string) $student->trainer_phone,
                (string) $student->legal_rep1_name,
                (string) $student->legal_rep1_email,
                (string) $student->legal_rep1_phone,
                (string) $student->legal_rep2_name,
                (string) $student->legal_rep2_email,
                (string) $student->legal_rep2_phone,
                $this->format_decimal($student->absence_count),
                $this->format_decimal($student->absence_rate),
                $this->format_decimal($this->absence_justified_count($student)),
                $this->format_decimal($this->actionable_absence_count($student)),
                $this->format_decimal($this->actionable_absence_rate($student)),
                (int) $student->lateness_count,
                (int) $student->removal_count,
                (int) ($student->comment_count ?? 0),
                $this->lateness_status_label((string) $student->lateness_status),
                $this->yes_no_label((int) $student->disciplinary_warning_received),
                $this->display_date_only((string) $student->disciplinary_warning_date),
                $this->yes_no_label((int) $student->expulsion_received),
                $this->display_date_only((string) $student->expulsion_date),
                $this->yes_no_label((int) $student->wellbeing_council_convoked),
                $this->display_date_only((string) $student->wellbeing_council_date),
                $this->student_exclusions_export_label((int) $student->id),
                $this->student_pending_tasks_export_label((int) $student->id),
                $this->student_events_export_label((int) $student->id),
                $this->student_communications_export_label((int) $student->id),
                $this->yes_no_label($this->is_verified_record($student)),
                $this->csv_display_datetime((string) $student->verified_at),
                (string) ($student->verified_by_name ?? ''),
                $this->student_grades_export_label((int) $student->id),
                (string) $student->admin_notes,
                $this->manual_fields_export_label($student->manual_fields_json ?? ''),
                $this->csv_display_datetime((string) $student->created_at),
                $this->csv_display_datetime((string) $student->updated_at),
                $this->csv_display_datetime((string) $student->archived_at),
            );
        }

        GCA_Database::log('students_exported', 'students', 0, array('count' => count($data)));
        $this->send_csv_download('apprentis-complet-' . wp_date('Ymd-His') . '.csv', $headers, $data);
    }

    public function handle_export_trainers(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_export_trainers');

        $trainers = GCA_Database::table('trainers');
        $users = $wpdb->users;

        $rows = $wpdb->get_results(
            "SELECT t.*, u.display_name AS verified_by_name
             FROM {$trainers} t
             LEFT JOIN {$users} u ON u.ID = t.verified_by
             ORDER BY t.company ASC, t.name ASC, t.email ASC"
        );

        $headers = array(
            'ID',
            'Clé interne',
            'Formateur responsable',
            'Entreprise',
            'Adresse',
            'NPA',
            'Localité',
            'Adresse complète entreprise',
            'E-mail du responsable',
            'Téléphone',
            'Formule d’appel',
            'Données vérifiées',
            'Vérifié le',
            'Vérifié par',
            'Nombre d’apprentis rattachés',
            'Apprentis rattachés',
            'Champs protégés import',
            'Créé le',
            'Mis à jour le',
        );

        $data = array();
        foreach ($rows as $trainer) {
            $attached_students = $this->trainer_students_export_label((int) $trainer->id);
            $data[] = array(
                (int) $trainer->id,
                (string) $trainer->trainer_key,
                $this->trainer_display_name($trainer),
                (string) $trainer->company,
                (string) ($trainer->company_address ?? ''),
                (string) ($trainer->company_zip ?? ''),
                (string) ($trainer->company_city ?? ''),
                $this->trainer_company_address_label($trainer),
                (string) $trainer->email,
                (string) $trainer->phone,
                (string) $trainer->salutation,
                $this->yes_no_label($this->is_verified_record($trainer)),
                $this->csv_display_datetime((string) $trainer->verified_at),
                (string) ($trainer->verified_by_name ?? ''),
                $attached_students['count'],
                $attached_students['label'],
                $this->manual_fields_export_label($trainer->manual_fields_json ?? ''),
                $this->csv_display_datetime((string) $trainer->created_at),
                $this->csv_display_datetime((string) $trainer->updated_at),
            );
        }

        GCA_Database::log('trainers_exported', 'trainers', 0, array('count' => count($data)));
        $this->send_csv_download('formateurs-complet-' . wp_date('Ymd-His') . '.csv', $headers, $data);
    }

    public function handle_generate_student_report(): void
    {
        $this->require_manage();
        $student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
        check_admin_referer('gca_generate_student_report_' . $student_id);

        $path = $this->create_student_report($student_id);
        if ($path === '' || !file_exists($path)) {
            status_header(404);
            exit;
        }

        GCA_Database::log('student_report_generated', 'student', $student_id, array('path' => GCA_Database::private_relative_path($path)));
        $this->download_private_pdf($path);
    }

    public function handle_generate_class_reports(): void
    {
        global $wpdb;

        $this->require_manage();
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        check_admin_referer('gca_generate_class_reports_' . $class_id);

        if (!class_exists('ZipArchive')) {
            $this->redirect_notice('reports', 'zip_missing');
        }

        $classes = GCA_Database::table('classes');
        $students = GCA_Database::table('students');
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$classes} WHERE id = %d", $class_id));
        $student_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$students} WHERE class_id = %d AND archived_at IS NULL ORDER BY last_name ASC, first_name ASC", $class_id));

        if (!$class || !$student_ids) {
            $this->redirect_notice('reports', 'not_found');
        }

        $zip_dir = GCA_Database::private_dir('reports');
        $zip_path = $zip_dir . '/' . sanitize_file_name($class->name . '-rapports-' . gmdate('Ymd-His') . '.zip');
        $zip = new ZipArchive();

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->redirect_notice('reports', 'not_found');
        }

        foreach ($student_ids as $student_id) {
            $report_path = $this->create_student_report((int) $student_id);
            if ($report_path !== '' && file_exists($report_path)) {
                $zip->addFile($report_path, basename($report_path));
            }
        }
        $zip->close();

        GCA_Database::log('class_reports_generated', 'class', $class_id, array('student_count' => count($student_ids), 'path' => GCA_Database::private_relative_path($zip_path)));

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zip_path) . '"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);
        exit;
    }

    public function handle_save_free_message_template(): void
    {
        $this->require_manage();
        check_admin_referer('gca_save_free_message_template');

        $template_id = sanitize_key(wp_unslash($_POST['free_template_id'] ?? ''));
        $template_label = sanitize_text_field(wp_unslash($_POST['template_label'] ?? ''));
        $subject_template = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $body_template = GCA_Database::sanitize_message_body(wp_unslash($_POST['body'] ?? ''));
        $redirect_args = array('gca_tab' => 'templates');

        if ($subject_template === '' || trim(wp_strip_all_tags($body_template)) === '') {
            $redirect_args['gca_notice'] = 'email_failed';
            wp_safe_redirect($this->page_url($redirect_args));
            exit;
        }

        $this->save_free_message_template($template_id, $template_label, $subject_template, $body_template);
        $redirect_args['gca_notice'] = 'free_template_saved';
        wp_safe_redirect($this->page_url($redirect_args));
        exit;
    }

    public function handle_send_trainer_bulk_message(): void
    {
        global $wpdb;

        $this->require_manage();
        check_admin_referer('gca_send_trainer_bulk_message');

        $class_ids = $this->class_ids_from_post();
        $redirect_class_id = count($class_ids) === 1 ? (int) $class_ids[0] : 0;
        $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action'] ?? 'send'));
        $template_id = sanitize_key(wp_unslash($_POST['trainer_template_id'] ?? ''));
        $template_label = sanitize_text_field(wp_unslash($_POST['template_label'] ?? ''));
        $subject_template = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $body_template = GCA_Database::sanitize_message_body(wp_unslash($_POST['body'] ?? ''));
        $test_recipients = $this->email_list(wp_unslash($_POST['test_recipients'] ?? ''));
        $recipient_mode = sanitize_key(wp_unslash($_POST['recipient_mode'] ?? 'trainers'));
        if (!in_array($recipient_mode, array('trainers', 'trainers_students'), true)) {
            $recipient_mode = 'trainers';
        }
        $include_student_copies = $recipient_mode === 'trainers_students';
        $this->sync_trainers_from_students();

        if ($bulk_action === 'save_template') {
            if ($subject_template === '' || trim(wp_strip_all_tags($body_template)) === '') {
                wp_safe_redirect($this->page_url(array('gca_tab' => 'templates', 'gca_notice' => 'email_failed')));
                exit;
            }

            $this->save_trainer_message_template($template_id, $template_label, $subject_template, $body_template);
            wp_safe_redirect($this->page_url(array('gca_tab' => 'templates', 'gca_notice' => 'trainer_template_saved')));
            exit;
        }

        if ($bulk_action === 'send_test') {
            if ($subject_template === '' || trim(wp_strip_all_tags($body_template)) === '') {
                wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $redirect_class_id, 'gca_notice' => 'email_failed')));
                exit;
            }

            if (!$test_recipients) {
                wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $redirect_class_id, 'gca_notice' => 'test_recipients_missing')));
                exit;
            }

            $preview = $this->bulk_test_preview_payload($subject_template, $body_template, $class_ids, $recipient_mode);
            $bcc = $this->always_bcc_emails();
            $sent_count = 0;
            $failed_count = 0;

            foreach ($test_recipients as $test_email) {
                $to = array($test_email);
                [$to, $unused_cc, $test_bcc] = $this->dedupe_recipient_groups($to, array(), $bcc);
                if (!$to) {
                    $failed_count++;
                    continue;
                }

                $headers = array_merge(array('Content-Type: text/html; charset=UTF-8'), $this->mail_sender_headers());
                foreach ($test_bcc as $email) {
                    $headers[] = 'Bcc: ' . $email;
                }

                $sent = $this->send_plugin_mail($to, (string) $preview['subject'], $this->email_body_with_signature((string) $preview['body']), $headers);
                if (!$sent) {
                    $this->remember_last_mail_error();
                    $failed_count++;
                    continue;
                }

                $sent_count++;
            }

            GCA_Database::log(
                'trainer_bulk_test_sent',
                'communication',
                0,
                array(
                    'class_ids' => $class_ids,
                    'recipient_mode' => $recipient_mode,
                    'test_recipients' => $test_recipients,
                    'sent_count' => $sent_count,
                    'failed_count' => $failed_count,
                )
            );

            if ($sent_count <= 0) {
                $this->remember_last_mail_error();
                wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $redirect_class_id, 'gca_notice' => 'email_failed')));
                exit;
            }

            wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $redirect_class_id, 'gca_notice' => 'trainer_bulk_test_sent')));
            exit;
        }

        $groups = $this->trainer_message_groups_for_class_ids($class_ids);
        $contract_break_students = $this->contract_break_students_for_class_ids($class_ids);

        if ($groups === array() || $subject_template === '' || trim(wp_strip_all_tags($body_template)) === '') {
            $notice = $groups === array() && $contract_break_students ? 'trainer_bulk_contract_break_only' : 'email_failed';
            wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $redirect_class_id, 'gca_notice' => $notice)));
            exit;
        }

        $communications = GCA_Database::table('communications');
        $bcc = $this->always_bcc_emails();
        $sent_count = 0;
        $failed_count = 0;
        $student_count = 0;
        $trainer_sent_count = 0;
        $student_copy_count = 0;

        foreach ($groups as $group) {
            $to = array((string) $group['email']);
            $responsible_copy_emails = $this->trainer_group_responsible_copy_emails($group);
            $student_copy_emails = $include_student_copies ? $this->trainer_group_student_copy_emails($group) : array();
            $cc = array_merge($responsible_copy_emails, $student_copy_emails);
            $student_labels = $this->trainer_group_student_labels($group);
            [$to, $group_cc, $group_bcc] = $this->dedupe_recipient_groups($to, $cc, $bcc);
            if (!$to) {
                $failed_count++;
                continue;
            }
            $subject = $this->replace_trainer_bulk_variables($subject_template, $group);
            $body = $this->replace_trainer_bulk_variables($body_template, $group);
            $headers = array_merge(array('Content-Type: text/html; charset=UTF-8'), $this->mail_sender_headers());
            foreach ($group_cc as $email) {
                $headers[] = 'Cc: ' . $email;
            }
            foreach ($group_bcc as $email) {
                $headers[] = 'Bcc: ' . $email;
            }

            $sent = $this->send_plugin_mail($to, $subject, $this->email_body_with_signature($body), $headers);
            if (!$sent) {
                $this->remember_last_mail_error();
                $failed_count++;
                continue;
            }

            $sent_count++;
            $trainer_sent_count++;
            $student_copy_count += count($student_copy_emails);
            foreach ($group['students'] as $student) {
                $student_count++;
                $wpdb->insert(
                    $communications,
                    array(
                        'student_id' => (int) $student->id,
                        'communication_type' => 'trainer_bulk_message',
                        'subject' => $subject,
                        'body' => $body,
                        'recipients_to' => implode(', ', $to),
                        'recipients_cc' => implode(', ', $group_cc),
                        'recipients_bcc' => implode(', ', $group_bcc),
                        'attachment_path' => '',
                        'sent_at' => GCA_Database::now(),
                        'created_by' => get_current_user_id(),
                        'teacher_id' => GCA_Database::current_teacher_id(),
                        'status' => 'sent',
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
                );
                GCA_Database::add_event(
                    (int) $student->id,
                    'trainer_bulk_message_sent',
                    'E-mail individuel envoyé au formateur',
                    array(
                        'trainer_email' => (string) $group['email'],
                        'responsible_copy_emails' => $responsible_copy_emails,
                        'student_copy_emails' => $student_copy_emails,
                        'apprentices' => $student_labels,
                        'communication_id' => (int) $wpdb->insert_id,
                    )
                );
            }
        }

        GCA_Database::log(
            'trainer_bulk_sent',
            'communication',
            0,
            array(
                'class_ids' => $class_ids,
                'recipient_mode' => $recipient_mode,
                'sent_count' => $sent_count,
                'failed_count' => $failed_count,
                'trainer_sent_count' => $trainer_sent_count,
                'student_copy_count' => $student_copy_count,
                'student_count' => $student_count,
            )
        );

        if ($sent_count <= 0) {
            $this->remember_last_mail_error();
            wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $redirect_class_id, 'gca_notice' => 'email_failed')));
            exit;
        }

        wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => $redirect_class_id, 'gca_notice' => 'trainer_bulk_sent')));
        exit;
    }

    public function handle_send_student_report(): void
    {
        global $wpdb;

        $this->require_manage();
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        check_admin_referer('gca_send_student_report_' . $student_id);

        $student = $this->student_with_class($student_id);
        if (!$student) {
            $this->redirect_notice('reports', 'not_found');
        }
        if ($this->student_without_employer($student)) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'gca_action' => 'prepare_report_email', 'student_id' => $student_id, 'gca_notice' => 'student_contract_broken')));
            exit;
        }

        $to = $this->email_list(wp_unslash($_POST['recipients_to'] ?? ''));
        $cc = $this->email_list(wp_unslash($_POST['recipients_cc'] ?? ''));
        $bcc = array_values(array_unique(array_merge($this->email_list(wp_unslash($_POST['recipients_bcc'] ?? '')), $this->always_bcc_emails())));
        [$to, $cc, $bcc] = $this->dedupe_recipient_groups($to, $cc, $bcc);

        if (!$to) {
            wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'gca_action' => 'prepare_report_email', 'student_id' => $student_id, 'gca_notice' => 'email_failed')));
            exit;
        }

        $subject = $this->ensure_subject_class_code($this->replace_variables(sanitize_text_field(wp_unslash($_POST['subject'] ?? '')), $student), $student);
        $body = $this->replace_variables(GCA_Database::sanitize_message_body(wp_unslash($_POST['body'] ?? '')), $student);
        $attachment_path = $this->create_student_report($student_id);
        $headers = array_merge(array('Content-Type: text/html; charset=UTF-8'), $this->mail_sender_headers());
        foreach ($cc as $email) {
            $headers[] = 'Cc: ' . $email;
        }
        foreach ($bcc as $email) {
            $headers[] = 'Bcc: ' . $email;
        }

        $sent = $this->send_plugin_mail($to, $subject, $this->email_body_with_signature($body), $headers, $attachment_path ? array($attachment_path) : array());
        if (!$sent) {
            $this->remember_last_mail_error();
            wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'gca_action' => 'prepare_report_email', 'student_id' => $student_id, 'gca_notice' => 'email_failed')));
            exit;
        }

        $communications = GCA_Database::table('communications');
        $wpdb->insert(
            $communications,
            array(
                'student_id' => $student_id,
                'communication_type' => 'student_report',
                'subject' => $subject,
                'body' => $body,
                'recipients_to' => implode(', ', $to),
                'recipients_cc' => implode(', ', $cc),
                'recipients_bcc' => implode(', ', $bcc),
                'attachment_path' => $attachment_path,
                'sent_at' => GCA_Database::now(),
                'created_by' => get_current_user_id(),
                'teacher_id' => GCA_Database::current_teacher_id(),
                'status' => 'sent',
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        GCA_Database::add_event($student_id, 'student_report_sent', 'Rapport envoyé au formateur', array('communication_id' => (int) $wpdb->insert_id));
        GCA_Database::log('student_report_sent', 'student', $student_id, array('communication_id' => (int) $wpdb->insert_id, 'to' => $to, 'cc' => $cc, 'bcc' => $bcc));
        wp_safe_redirect($this->page_url(array('gca_tab' => 'reports', 'class_id' => (int) $student->class_id, 'gca_notice' => 'report_sent')));
        exit;
    }

    private function update_student_after_communication(int $student_id, string $communication_type): void
    {
        global $wpdb;

        $status = '';

        if ($communication_type === 'lateness_3') {
            $status = 'convocation_1_sent';
        } elseif ($communication_type === 'lateness_6') {
            $status = 'convocation_2_sent';
        } elseif ($communication_type === 'dean_warning') {
            $status = 'dean_warning_pending';
        } elseif ($communication_type === 'dean_exclusion') {
            $status = 'dean_exclusion_pending';
        }

        if ($status === '') {
            return;
        }

        $wpdb->update(
            GCA_Database::table('students'),
            array(
                'lateness_status' => $status,
                'updated_at' => GCA_Database::now(),
            ),
            array('id' => $student_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    private function default_recipients_for_task(object $task, string $recipient_mode = ''): array
    {
        if ((string) $task->task_type === 'contract_break_review') {
            $to = array_filter(array($this->internal_followup_email()));
            return array(
                'to' => array_values(array_unique($to)),
                'cc' => array(),
                'bcc' => $this->always_bcc_emails(),
            );
        }

        if (in_array($task->task_type, array('dean_warning', 'dean_exclusion'), true)) {
            return array(
                'to' => $this->email_list(GCA_Database::get_settings()['dean_emails']),
                'cc' => array(),
                'bcc' => $this->always_bcc_emails(),
            );
        }

        if ($this->task_is_absence_followup($task)) {
            if ($recipient_mode === 'dean') {
                return array(
                    'to' => $this->email_list(GCA_Database::get_settings()['dean_emails']),
                    'cc' => array(),
                    'bcc' => $this->always_bcc_emails(),
                );
            }

            $trainer_email = sanitize_email((string) ($task->trainer_email ?? ''));
            $student_email = sanitize_email((string) ($task->email ?? ''));

            return array(
                'to' => !$this->student_without_employer($task) && is_email($trainer_email) ? array($trainer_email) : array(),
                'cc' => is_email($student_email) ? array($student_email) : array(),
                'bcc' => $this->always_bcc_emails(),
            );
        }

        $to = array();
        $cc = array();

        if (!empty($task->email) && is_email($task->email)) {
            $to[] = $task->email;
        }

        $internal_followup_email = $this->student_without_employer($task)
            ? $this->internal_followup_email()
            : '';
        if (is_email($internal_followup_email)) {
            $cc[] = $internal_followup_email;
        } else {
            $trainer_email = sanitize_email((string) ($task->trainer_email ?? ''));
            if (is_email($trainer_email)) {
                $cc[] = $trainer_email;
            }
        }

        if ($this->is_minor($task)) {
            foreach (array('legal_rep1_email', 'legal_rep2_email') as $field) {
                if (!empty($task->{$field}) && is_email($task->{$field})) {
                    $cc[] = $task->{$field};
                }
            }
        }

        return array(
            'to' => array_values(array_unique($to)),
            'cc' => array_values(array_unique($cc)),
            'bcc' => $this->always_bcc_emails(),
        );
    }

    private function recipient_mode_for_task(object $task, string $requested_mode): string
    {
        if (!$this->task_is_absence_followup($task)) {
            return '';
        }

        if ($this->student_without_employer($task)) {
            return 'dean';
        }

        return $requested_mode === 'dean' ? 'dean' : 'trainer';
    }

    /**
     * Copie cachée de chaque e-mail envoyé : dans l'espace principal (données d'origine), l'adresse habituelle ;
     * dans l'espace d'un autre enseignant, l'enseignant lui-même (jamais un collègue ni l'administrateur).
     */
    private function always_bcc_emails(): array
    {
        if (!$this->in_principal_space()) {
            $owner = $this->space_owner_email();
            return $owner !== '' ? array($owner) : array();
        }
        return is_email(self::PRINT_COPY_EMAIL) ? array(self::PRINT_COPY_EMAIL) : array();
    }

    /** Vrai dans l'espace principal (ou sans espaces) ; faux dans l'espace personnel d'un autre enseignant. */
    private function in_principal_space(): bool
    {
        return !class_exists('LogiPilot_Spaces') || LogiPilot_Spaces::infix() === '';
    }

    /** Adresse du compte propriétaire de l'espace utilisé (l'enseignant). */
    private function space_owner_email(): string
    {
        if (!class_exists('LogiPilot_Spaces')) {
            return '';
        }
        $user = get_userdata(LogiPilot_Spaces::current());
        $email = $user ? sanitize_email((string) $user->user_email) : '';
        return is_email($email) ? $email : '';
    }

    private function dedupe_recipient_groups(array $to, array $cc, array $bcc): array
    {
        $seen = array();
        $groups = array($to, $cc, $bcc);

        foreach ($groups as $group_index => $emails) {
            $filtered = array();
            foreach ($emails as $email) {
                $email = sanitize_email((string) $email);
                $key = strtolower($email);
                if ($email === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $filtered[] = $email;
            }
            $groups[$group_index] = $filtered;
        }

        return $groups;
    }

    private function send_plugin_mail($to, string $subject, string $message, array $headers = array(), array $attachments = array()): bool
    {
        $this->last_mail_error = '';
        $settings = GCA_Database::get_settings();
        $from_email = sanitize_email((string) ($settings['email_sender_email'] ?? ''));
        $from_name = $this->mail_from_name($settings);
        $mail_failed_action = function ($error): void {
            if ($error instanceof WP_Error) {
                $this->last_mail_error = $this->mail_error_summary($error);
                return;
            }

            $this->last_mail_error = sanitize_text_field((string) $error);
        };

        add_action('wp_mail_failed', $mail_failed_action, PHP_INT_MAX);

        $from_name_filter = static function ($current) use ($from_name): string {
            return $from_name !== '' ? $from_name : (string) $current;
        };
        $from_email_filter = static function ($current) use ($from_email): string {
            return is_email($from_email) ? $from_email : (string) $current;
        };
        $phpmailer_filter = static function ($phpmailer) use ($from_email, $from_name): void {
            if (is_email($from_email)) {
                try {
                    $phpmailer->setFrom($from_email, $from_name, false);
                } catch (Throwable $error) {
                    $phpmailer->From = $from_email;
                    $phpmailer->FromName = $from_name;
                }

                $phpmailer->Sender = $from_email;
                return;
            }

            if ($from_name !== '') {
                $phpmailer->FromName = $from_name;
            }
        };

        add_filter('wp_mail_from_name', $from_name_filter, PHP_INT_MAX);
        add_action('phpmailer_init', $phpmailer_filter, PHP_INT_MAX);
        if (is_email($from_email)) {
            add_filter('wp_mail_from', $from_email_filter, PHP_INT_MAX);
        }

        try {
            return wp_mail($to, $subject, $message, $headers, $attachments);
        } finally {
            remove_filter('wp_mail_from_name', $from_name_filter, PHP_INT_MAX);
            remove_action('phpmailer_init', $phpmailer_filter, PHP_INT_MAX);
            if (is_email($from_email)) {
                remove_filter('wp_mail_from', $from_email_filter, PHP_INT_MAX);
            }
            remove_action('wp_mail_failed', $mail_failed_action, PHP_INT_MAX);
        }
    }

    private function mail_error_summary(WP_Error $error): string
    {
        $parts = $error->get_error_messages();
        $data = $error->get_error_data();

        if (is_array($data)) {
            foreach (array('phpmailer_exception_code', 'smtp_code', 'smtp_code_ex') as $key) {
                if (!empty($data[$key])) {
                    $parts[] = $key . ': ' . (string) $data[$key];
                }
            }
        } elseif (is_scalar($data) && (string) $data !== '') {
            $parts[] = (string) $data;
        }

        return wp_trim_words(sanitize_text_field(implode(' ', array_unique(array_filter($parts)))), 60, '...');
    }

    private function remember_last_mail_error(): void
    {
        if ($this->last_mail_error === '') {
            return;
        }

        set_transient($this->mail_error_transient_key(), $this->last_mail_error, 10 * MINUTE_IN_SECONDS);
    }

    private function consume_last_mail_error(): string
    {
        $key = $this->mail_error_transient_key();
        $error = get_transient($key);
        delete_transient($key);

        return is_string($error) ? $error : '';
    }

    private function mail_error_transient_key(): string
    {
        return 'gca_last_mail_error_' . get_current_user_id();
    }

    private function mail_sender_headers(): array
    {
        $settings = GCA_Database::get_settings();
        $email = sanitize_email((string) ($settings['email_sender_email'] ?? ''));
        $reply_to_email = sanitize_email((string) ($settings['email_reply_to_email'] ?? ''));

        $headers = array();
        if (!is_email($email)) {
            if (is_email($reply_to_email)) {
                $headers[] = 'Reply-To: ' . $reply_to_email;
            }

            return $headers;
        }

        $name = $this->mail_from_name($settings);
        $from = $name !== '' ? $name . ' <' . $email . '>' : $email;
        $headers[] = 'From: ' . $from;
        if (is_email($reply_to_email)) {
            $headers[] = 'Reply-To: ' . $reply_to_email;
        }

        return $headers;
    }

    private function mail_from_name(array $settings): string
    {
        $name = trim((string) ($settings['email_sender_name'] ?? ''));
        $normalized = strtolower(remove_accents($name));
        if ($name === '' || $normalized === 'logiprof') {
            $name = 'CPNV';
        }

        return $this->mail_header_text($name);
    }

    private function mail_header_text(string $value): string
    {
        return trim(str_replace(array("\r", "\n"), '', sanitize_text_field($value)));
    }

    private function reminder_recipient_emails(array $settings): array
    {
        $emails = $this->email_list($settings['reminder_emails'] ?? '');

        if (!$emails && !empty($settings['email_reply_to_email']) && is_email($settings['email_reply_to_email'])) {
            $emails[] = sanitize_email((string) $settings['email_reply_to_email']);
        }

        if (!$emails && !empty($settings['email_sender_email']) && is_email($settings['email_sender_email'])) {
            $emails[] = sanitize_email((string) $settings['email_sender_email']);
        }

        // Espace d'un autre enseignant : ses rappels ne partent que chez lui.
        if (!$this->in_principal_space()) {
            $owner = $this->space_owner_email();
            if (!$emails && $owner !== '') {
                $emails[] = $owner;
            }
            return array_values(array_unique($emails));
        }

        $admin_email = sanitize_email((string) get_option('admin_email'));
        if (!$emails && is_email($admin_email)) {
            $emails[] = $admin_email;
        }

        if (!$emails && is_email(self::PRINT_COPY_EMAIL)) {
            $emails[] = self::PRINT_COPY_EMAIL;
        }

        return array_values(array_unique($emails));
    }

    private function pending_task_rows(string $created_before = '', int $limit = 20): array
    {
        global $wpdb;

        $tasks = GCA_Database::table('tasks');
        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $where = "WHERE t.status = 'pending' AND s.archived_at IS NULL";

        if ($created_before !== '') {
            $where .= $wpdb->prepare(' AND t.created_at <= %s', $created_before);
        }

        return $wpdb->get_results(
            "SELECT t.*, s.first_name, s.last_name, s.trainer_company, s.lateness_count, s.absence_count, s.absence_rate, s.absence_justified_count, s.removal_count, s.comment_count, c.name AS class_name
             FROM {$tasks} t
             INNER JOIN {$students} s ON s.id = t.student_id
             INNER JOIN {$classes} c ON c.id = s.class_id
             {$where}
             ORDER BY t.created_at ASC
             LIMIT " . max(1, (int) $limit)
        );
    }

    private function pending_contract_review_rows(int $limit = 20): array
    {
        global $wpdb;

        $tasks = GCA_Database::table('tasks');
        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');

        return $wpdb->get_results(
            "SELECT t.*, s.first_name, s.last_name, s.trainer_company, s.lateness_count, s.absence_count, s.absence_rate, s.absence_justified_count, s.removal_count, s.comment_count, c.name AS class_name
             FROM {$tasks} t
             INNER JOIN {$students} s ON s.id = t.student_id
             INNER JOIN {$classes} c ON c.id = s.class_id
             WHERE t.status = 'pending'
               AND t.task_type = 'contract_break_review'
               AND s.archived_at IS NULL
             ORDER BY t.created_at ASC
             LIMIT " . max(1, (int) $limit)
        );
    }

    private function task_digest_lines(array $tasks): array
    {
        $lines = array();

        foreach ($tasks as $task) {
            $age = $this->task_age_days((string) $task->created_at);
            $name = trim((string) $task->first_name . ' ' . (string) $task->last_name);
            $company = trim((string) ($task->trainer_company ?? ''));
            $message = trim((string) $task->message);
            $line = '- ' . $name . ($company !== '' ? ' - ' . $company : '') . ' (' . (string) $task->class_name . ') : ' . $this->task_type_label((string) $task->task_type);

            if ($message !== '') {
                $line .= ' - ' . $message;
            }
            if ($this->task_can_be_justified_without_email($task)) {
                $line .= ' - ' . $this->pending_task_reason($task);
            }

            $line .= ' - en attente depuis ' . $age . ' jour(s)';
            $lines[] = $line;
        }

        return $lines;
    }

    private function task_age_days(string $created_at): int
    {
        $created = $this->local_datetime_timestamp($created_at);
        if ($created <= 0) {
            return 0;
        }

        return max(0, (int) floor((current_datetime()->getTimestamp() - $created) / DAY_IN_SECONDS));
    }

    private function mysql_datetime_days_ago(int $days): string
    {
        return current_datetime()->modify('-' . max(1, $days) . ' days')->format('Y-m-d H:i:s');
    }

    private function sanitize_time_setting(string $time, string $fallback): string
    {
        $time = trim($time);

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return $time;
        }

        return $fallback;
    }

    private function next_daily_timestamp(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->sanitize_time_setting($time, '08:00')));
        $now = new DateTimeImmutable('now', wp_timezone());
        $run = $now->setTime($hour, $minute, 0);

        if ($run <= $now) {
            $run = $run->modify('+1 day');
        }

        return $run->getTimestamp();
    }

    private function task_is_lateness_convocation(object $task): bool
    {
        return in_array((string) $task->task_type, array('lateness_3', 'lateness_6'), true);
    }

    private function append_note_if_missing(string $body, string $note): string
    {
        $note = trim($note);

        if ($note === '' || str_contains($body, $note)) {
            return $body;
        }

        if ($this->content_contains_html($body)) {
            return rtrim($body) . '<p>' . str_replace("\n", '<br>', esc_html($note)) . '</p>';
        }

        return rtrim($body) . "\n\n" . $note;
    }

    private function detention_no_show_note_for_student(object $student): string
    {
        global $wpdb;

        $student_id = $this->student_id_from_object($student);
        if ($student_id <= 0) {
            return '';
        }

        $events = GCA_Database::table('events');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT payload_json, created_at
                 FROM {$events}
                 WHERE student_id = %d AND event_type = %s
                 ORDER BY created_at DESC
                 LIMIT 3",
                $student_id,
                'detention_no_show'
            )
        );

        if (!$rows) {
            return '';
        }

        $dates = array();
        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload_json, true);
            $date = is_array($payload) ? $this->display_date_only((string) ($payload['date'] ?? '')) : '';
            if ($date === '') {
                $date = $this->display_date_only((string) $row->created_at);
            }
            if ($date !== '') {
                $dates[] = $date;
            }
        }

        return $this->detention_no_show_note_from_dates($dates);
    }

    private function detention_no_show_note_from_dates(array $dates): string
    {
        $dates = array_values(array_unique(array_filter(array_map('trim', $dates))));

        if (!$dates) {
            return 'Nous relevons également que vous ne vous êtes pas présenté(e) à une retenue précédente.';
        }

        if (count($dates) === 1) {
            return 'Nous relevons également que vous ne vous êtes pas présenté(e) à la retenue prévue le ' . $dates[0] . '.';
        }

        return 'Nous relevons également que vous ne vous êtes pas présenté(e) aux retenues précédentes des ' . implode(', ', $dates) . '.';
    }

    private function delete_student_pdfs(int $student_id): void
    {
        $this->delete_private_dir(GCA_Database::private_dir('pdfs/student-' . $student_id));
    }

    private function delete_student_reports(int $student_id): void
    {
        $this->delete_private_dir(GCA_Database::private_dir('reports/student-' . $student_id));
    }

    private function delete_private_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        @rmdir($dir);
    }

    private function replace_variables(string $text, object $student, array $extra = array()): string
    {
        $without_employer = $this->student_without_employer($student);
        $trainer_salutation = $without_employer ? '' : $this->trainer_salutation_for_student($student);
        $responsible_trainer_email = $without_employer ? '' : sanitize_email((string) ($student->trainer_email ?? ''));
        $responsible_trainer_name = $without_employer ? '' : trim((string) ($student->trainer_name ?? ''));
        $responsible_trainer_company = $without_employer ? '' : trim((string) ($student->trainer_company ?? ''));
        $responsible_trainer_company_address = $without_employer ? '' : trim((string) ($student->trainer_company_address ?? ''));
        $responsible_trainer_company_zip = $without_employer ? '' : trim((string) ($student->trainer_company_zip ?? ''));
        $responsible_trainer_company_city = $without_employer ? '' : trim((string) ($student->trainer_company_city ?? ''));
        $responsible_trainer_phone = $without_employer ? '' : trim((string) ($student->trainer_phone ?? ''));
        $responsible_trainer_company_full_address = $without_employer ? '' : $this->trainer_company_address_label($student);
        $effective_trainer_email = $responsible_trainer_email;
        $effective_trainer_name = $responsible_trainer_name;
        $effective_trainer_phone = $responsible_trainer_phone;
        $lateness_total = $this->lateness_total_for_message($student);
        $lateness_threshold = $this->lateness_threshold_for_message($student);
        $lateness_previous_threshold = $this->lateness_previous_threshold_for_message($student, $lateness_threshold);
        $lateness_additional_count = $lateness_previous_threshold > 0
            ? max(0, $lateness_total - $lateness_previous_threshold)
            : 0;
        $values = array(
            'recipient_name' => $trainer_salutation,
            'full_name' => trim($student->first_name . ' ' . $student->last_name),
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'class_name' => $student->class_name ?? '',
            'class_code' => $this->class_code_for_student($student),
            'lateness_count' => (string) $lateness_total,
            'lateness_total' => (string) $lateness_total,
            'lateness_threshold' => $lateness_threshold > 0 ? (string) $lateness_threshold : '',
            'lateness_previous_threshold' => $lateness_previous_threshold > 0 ? (string) $lateness_previous_threshold : '',
            'lateness_additional_count' => (string) $lateness_additional_count,
            'absence_count' => $this->format_decimal($this->actionable_absence_count($student)),
            'absence_rate' => $this->format_decimal($this->actionable_absence_rate($student)),
            'absence_imported_count' => $this->format_decimal($student->absence_count ?? 0),
            'absence_justified_count' => $this->format_decimal($this->absence_justified_count($student)),
            'removal_count' => (string) (int) $student->removal_count,
            'comment_count' => (string) (int) ($student->comment_count ?? 0),
            'today' => wp_date('d.m.Y'),
            'convocation_date' => $extra['convocation_date'] ?? '',
            'first_convocation_date' => $this->first_convocation_date_for_student($student),
            'detention_no_show_note' => $extra['detention_no_show_note'] ?? $this->detention_no_show_note_for_student($student),
            'trainer_salutation' => $trainer_salutation,
            'trainer_name' => $effective_trainer_name,
            'trainer_company' => $responsible_trainer_company,
            'trainer_company_address' => $responsible_trainer_company_address,
            'trainer_company_zip' => $responsible_trainer_company_zip,
            'trainer_company_city' => $responsible_trainer_company_city,
            'trainer_company_full_address' => $responsible_trainer_company_full_address,
            'trainer_email' => $effective_trainer_email,
            'trainer_phone' => $effective_trainer_phone,
            'field_trainer_name' => '',
            'field_trainer_email' => '',
            'field_trainer_phone' => '',
            'responsible_trainer_name' => $responsible_trainer_name,
            'responsible_trainer_company' => $responsible_trainer_company,
            'responsible_trainer_company_address' => $responsible_trainer_company_address,
            'responsible_trainer_company_zip' => $responsible_trainer_company_zip,
            'responsible_trainer_company_city' => $responsible_trainer_company_city,
            'responsible_trainer_company_full_address' => $responsible_trainer_company_full_address,
            'responsible_trainer_email' => $responsible_trainer_email,
            'responsible_trainer_phone' => $responsible_trainer_phone,
            'contract_status' => $this->contract_status_label((string) ($student->contract_status ?? 'active')),
            'contract_break_date' => $this->contract_break_date_label($student),
            'contract_follow_until' => $this->contract_follow_until_label($student),
            'contract_review_date' => $this->contract_review_date_label($student),
            'contract_responsible_email' => $this->internal_followup_email(),
            'threshold' => $student->threshold_value ?? '',
            'gender_article' => $this->gender_article($student),
            'gender_article_cap' => $this->gender_article_cap($student),
            'gender_pronoun' => $this->gender_pronoun($student),
            'gender_participle' => $this->gender_participle($student, 'convoqué'),
        );

        return $this->cleanup_empty_salutation($this->replace_message_variables($text, $values, array('detention_no_show_note')));
    }

    private function lateness_total_for_message(object $student): int
    {
        return max(max(0, (int) ($student->lateness_count ?? 0)), $this->lateness_threshold_for_message($student));
    }

    private function lateness_threshold_for_message(object $student): int
    {
        $task_type = (string) ($student->task_type ?? '');
        if (!in_array($task_type, array('lateness_3', 'lateness_6', 'dean_warning', 'dean_exclusion'), true)) {
            return 0;
        }

        $threshold = trim((string) ($student->threshold_value ?? ''));
        if ($threshold === '' || !is_numeric(str_replace(',', '.', $threshold))) {
            return 0;
        }

        return max(0, (int) floor((float) str_replace(',', '.', $threshold)));
    }

    private function lateness_previous_threshold_for_message(object $student, int $lateness_threshold): int
    {
        $task_type = (string) ($student->task_type ?? '');
        if ($task_type !== 'lateness_6') {
            return 0;
        }

        $settings = GCA_Database::get_settings();
        $previous = max(1, (int) ($settings['lateness_threshold_1'] ?? 3));

        if ($lateness_threshold > 0) {
            return min($previous, max(0, $lateness_threshold - 1));
        }

        return $previous;
    }

    private function first_convocation_date_for_student(object $student): string
    {
        global $wpdb;

        $student_id = $this->student_id_from_object($student);
        if ($student_id <= 0) {
            return 'non renseignée';
        }

        $communications = GCA_Database::table('communications');
        $communication = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT body, sent_at
                 FROM {$communications}
                 WHERE student_id = %d
                   AND communication_type = %s
                   AND status = 'sent'
                 ORDER BY sent_at ASC
                 LIMIT 1",
                $student_id,
                'lateness_3'
            )
        );

        if (!$communication) {
            return 'non renseignée';
        }

        if (preg_match('/\b(\d{1,2}[.\/-]\d{1,2}[.\/-]\d{4})\b/', (string) $communication->body, $matches)) {
            return $this->display_date_only($matches[1]) ?: $matches[1];
        }

        return $this->display_date_only((string) $communication->sent_at) ?: 'non renseignée';
    }

    private function student_id_from_object(object $student): int
    {
        if (!empty($student->student_id)) {
            return (int) $student->student_id;
        }

        if (!empty($student->id)) {
            return (int) $student->id;
        }

        return 0;
    }

    private function ensure_subject_class_code(string $subject, object $student): string
    {
        $class_code = $this->class_code_for_student($student);

        if ($class_code === '' || stripos($subject, $class_code) !== false) {
            return $subject;
        }

        return trim($subject . ' - ' . $class_code);
    }

    private function class_code_for_student(object $student): string
    {
        $class_name = trim((string) ($student->class_name ?? ''));

        if ($class_name === '') {
            return '';
        }

        if (preg_match('/\bC(?:R)?\s*([0-9]+[A-Za-z])\b/u', $class_name, $matches)) {
            return 'C' . strtoupper(substr($matches[1], 0, -1)) . strtolower(substr($matches[1], -1));
        }

        if (preg_match('/(?:^|[-_\\s])([A-Za-z]+[0-9]+[A-Za-z])$/u', $class_name, $matches)) {
            return strtoupper(substr($matches[1], 0, -1)) . strtolower(substr($matches[1], -1));
        }

        return $class_name;
    }

    private function communication_type_for_task(object $task): string
    {
        if ($task->task_type === 'absence') {
            return 'absence_' . $this->importer->threshold_key((float) $task->threshold_value);
        }

        if ($this->task_is_absence_followup($task)) {
            return (string) $task->task_type;
        }

        if ($task->task_type === 'removal') {
            return 'removal_' . (int) $task->threshold_value;
        }

        return $task->task_type;
    }

    private function template_key_for_task(object $task): string
    {
        if ($task->task_type === 'absence') {
            return 'absence';
        }

        if ($this->task_is_absence_followup($task)) {
            return (string) $task->task_type;
        }

        if ($task->task_type === 'removal') {
            return '';
        }

        return $task->task_type;
    }

    private function free_template_key_for_task(object $task): string
    {
        if ($task->task_type === 'removal') {
            return 'removal_followup';
        }

        return 'general';
    }

    private function task_generates_pdf(object $task): bool
    {
        return in_array($task->task_type, array('lateness_3', 'lateness_6', 'absence'), true);
    }

    private function task_requires_convocation_date(object $task): bool
    {
        return $this->task_generates_pdf($task);
    }

    private function dashboard_task_action_label(object $task): string
    {
        $type = (string) ($task->task_type ?? '');
        $threshold = $this->format_decimal($task->threshold_value ?? 0);
        $labels = array(
            'lateness_3' => 'Préparer la convocation 3 retards',
            'lateness_6' => 'Préparer la convocation 6 retards',
            'absence' => 'Préparer la convocation absences',
            'absence_unjustified_21' => 'Message absences > ' . ($threshold !== '0' ? $threshold : '21'),
            'absence_rate_30' => 'Message taux ≥ ' . ($threshold !== '0' ? $threshold : '30') . ' %',
            'removal' => 'Préparer un message mise à la porte',
            'contract_break_review' => 'Faire le point rupture',
            'dean_warning' => 'Informer le doyen',
            'dean_exclusion' => 'Informer le doyen',
        );

        if (isset($labels[$type])) {
            return $labels[$type];
        }

        $stored = trim((string) ($task->action_label ?? ''));
        return $stored !== '' ? $stored : 'Traiter';
    }

    private function task_is_absence_followup(object $task): bool
    {
        return in_array((string) ($task->task_type ?? ''), array('absence_unjustified_21', 'absence_rate_30'), true);
    }

    private function task_can_be_justified_without_email(object $task): bool
    {
        return in_array((string) ($task->task_type ?? ''), array('absence', 'absence_unjustified_21', 'absence_rate_30'), true);
    }

    private function absence_justification_period_label(object $task): string
    {
        $type = (string) ($task->task_type ?? '');
        if ($type === 'absence_rate_30') {
            return 'Périodes à justifier pour recalculer le taux';
        }

        if ($type === 'absence_unjustified_21') {
            return 'Périodes non justifiées à justifier';
        }

        return 'Périodes d’absence à justifier';
    }

    private function absence_justification_submit_label(object $task): string
    {
        $type = (string) ($task->task_type ?? '');
        if ($type === 'absence_rate_30') {
            return 'Justifier et recalculer';
        }

        return 'Justifier sans e-mail';
    }

    private function student_followup_decision_definitions(): array
    {
        return array(
            'disciplinary_warning' => array(
                'flag' => 'disciplinary_warning_received',
                'date' => 'disciplinary_warning_date',
                'recorded_event' => 'disciplinary_warning_recorded',
                'removed_event' => 'disciplinary_warning_removed',
                'recorded_status' => 'Avertissement reçu',
                'removed_status' => 'Avertissement retiré',
            ),
            'wellbeing_council' => array(
                'flag' => 'wellbeing_council_convoked',
                'date' => 'wellbeing_council_date',
                'recorded_event' => 'wellbeing_council_recorded',
                'removed_event' => 'wellbeing_council_removed',
                'recorded_status' => 'Convocation à un conseil de savoir-être',
                'removed_status' => 'Convocation au conseil de savoir-être retirée',
            ),
        );
    }

    private function record_student_followup_decision_events(int $student_id, array $before, array $after): void
    {
        if ($student_id <= 0 || !$after) {
            return;
        }

        foreach ($this->student_followup_decision_definitions() as $definition) {
            $flag_field = (string) $definition['flag'];
            $date_field = (string) $definition['date'];
            $before_flag = !empty($before) ? (int) ($before[$flag_field] ?? 0) : 0;
            $after_flag = (int) ($after[$flag_field] ?? 0);
            $before_date = (string) ($before[$date_field] ?? '');
            $after_date = (string) ($after[$date_field] ?? '');

            if ($before_flag === $after_flag && $before_date === $after_date) {
                continue;
            }

            $event_type = $after_flag === 1 ? (string) $definition['recorded_event'] : (string) $definition['removed_event'];
            $status = $after_flag === 1 ? (string) $definition['recorded_status'] : (string) $definition['removed_status'];

            GCA_Database::add_event(
                $student_id,
                $event_type,
                $status,
                array(
                    'date' => $after_date,
                )
            );
        }
    }

    private function record_student_contract_events(int $student_id, array $before, array $after): void
    {
        if ($student_id <= 0 || !$after) {
            return;
        }

        $before_status = $this->contract_status_value((string) ($before['contract_status'] ?? 'active'));
        $after_status = $this->contract_status_value((string) ($after['contract_status'] ?? 'active'));
        $before_date = (string) ($before['contract_break_date'] ?? '');
        $after_date = (string) ($after['contract_break_date'] ?? '');

        if ($before_status === $after_status && $before_date === $after_date) {
            return;
        }

        if ($after_status === 'active') {
            GCA_Database::add_event($student_id, 'contract_break_closed', 'Contrat de formation actif');
            return;
        }

        if ($after_status === 'free_candidate') {
            $event_type = $before_status === 'free_candidate' ? 'free_candidate_updated' : 'free_candidate_recorded';
            $status = $before_status === 'free_candidate' ? 'Candidat libre modifié' : 'Candidat libre enregistré';
            GCA_Database::add_event(
                $student_id,
                $event_type,
                $status,
                array(
                    'contract_status' => $after_status,
                )
            );
            return;
        }

        $student = (object) $after;
        $event_type = $before_status === 'broken' ? 'contract_break_updated' : 'contract_break_recorded';
        $status = $before_status === 'broken' ? 'Rupture de contrat modifiée' : 'Rupture de contrat enregistrée';
        GCA_Database::add_event(
            $student_id,
            $event_type,
            $status,
            array(
                'break_date' => $after_date,
                'follow_until' => $this->contract_follow_until_label($student),
                'review_date' => $this->contract_review_date_label($student),
            )
        );
    }

    private function disciplinary_exclusions_for_student(int $student_id): array
    {
        global $wpdb;

        if ($student_id <= 0) {
            return array();
        }

        $table = GCA_Database::table('disciplinary_exclusions');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE student_id = %d
                 ORDER BY start_date DESC, end_date DESC, id DESC",
                $student_id
            )
        );
    }

    private function absence_justifications_for_student(int $student_id): array
    {
        global $wpdb;

        if ($student_id <= 0) {
            return array();
        }

        $table = GCA_Database::table('absence_justifications');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE student_id = %d
                 ORDER BY absence_date DESC,
                          id DESC",
                $student_id
            )
        );
    }

    private function absence_justification_source_label(string $source): string
    {
        if ($source === 'task') {
            return 'Action à traiter';
        }

        if ($source === 'manual') {
            return 'Saisie manuelle';
        }

        return $source !== '' ? $source : '-';
    }

    private function student_grades_for_student(int $student_id): array
    {
        global $wpdb;

        if ($student_id <= 0) {
            return array();
        }

        $table = GCA_Database::table('grades');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE student_id = %d
                 ORDER BY assessment ASC,
                          id DESC",
                $student_id
            )
        );
    }

    private function record_disciplinary_exclusion(int $student_id, string $start, string $end, string $notes = '', bool $record_event = true): int
    {
        global $wpdb;

        if ($student_id <= 0 || ($start === '' && $end === '')) {
            return 0;
        }

        $now = GCA_Database::now();
        $table = GCA_Database::table('disciplinary_exclusions');
        $wpdb->insert(
            $table,
            array(
                'student_id' => $student_id,
                'start_date' => $start ?: null,
                'end_date' => $end ?: null,
                'notes' => $notes,
                'created_by' => get_current_user_id(),
                'teacher_id' => GCA_Database::current_teacher_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );

        $exclusion_id = (int) $wpdb->insert_id;
        $exclusion = (object) array(
            'start_date' => $start,
            'end_date' => $end,
            'notes' => $notes,
        );

        $this->sync_student_expulsion_from_exclusions($student_id);
        if ($record_event) {
            GCA_Database::add_event(
                $student_id,
                'disciplinary_exclusion_recorded',
                'Exclusion enregistrée',
                array(
                    'exclusion_id' => $exclusion_id,
                    'start' => $start,
                    'end' => $end,
                    'duration' => $this->disciplinary_exclusion_duration_label($exclusion),
                    'notes' => $notes,
                )
            );
        }
        GCA_Database::log(
            'disciplinary_exclusion_saved',
            'student',
            $student_id,
            array(
                'exclusion_id' => $exclusion_id,
                'start' => $start,
                'end' => $end,
                'notes' => $notes,
            )
        );

        return $exclusion_id;
    }

    private function sync_student_expulsion_from_exclusions(int $student_id): void
    {
        global $wpdb;

        if ($student_id <= 0) {
            return;
        }

        $exclusions = GCA_Database::table('disciplinary_exclusions');
        $students = GCA_Database::table('students');
        $student = $wpdb->get_row($wpdb->prepare("SELECT lateness_status, warning_confirmed_at, lateness_count FROM {$students} WHERE id = %d", $student_id));
        $latest = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$exclusions}
                 WHERE student_id = %d
                 ORDER BY COALESCE(start_date, end_date, '0000-00-00') DESC, id DESC
                 LIMIT 1",
                $student_id
            )
        );

        $data = array(
            'expulsion_received' => $latest ? 1 : 0,
            'expulsion_date' => $latest ? ((string) ($latest->start_date ?: $latest->end_date) ?: null) : null,
            'exclusion_start' => $latest ? ((string) $latest->start_date ?: null) : null,
            'exclusion_end' => $latest ? ((string) $latest->end_date ?: null) : null,
            'exclusion_lateness_count' => $latest && $student ? (int) $student->lateness_count : 0,
            'updated_at' => GCA_Database::now(),
        );
        $formats = array('%d', '%s', '%s', '%s', '%d', '%s');

        if ($latest) {
            $data['lateness_status'] = 'exclusion_confirmed';
            $formats[] = '%s';
        } elseif ($student && (string) $student->lateness_status === 'exclusion_confirmed') {
            $data['lateness_status'] = !empty($student->warning_confirmed_at) ? 'warning_confirmed' : 'none';
            $formats[] = '%s';
        }

        $wpdb->update(
            $students,
            $data,
            array('id' => $student_id),
            $formats,
            array('%d')
        );
    }

    private function disciplinary_exclusion_duration_label(object $exclusion): string
    {
        $start = $this->normalize_date_for_storage((string) ($exclusion->start_date ?? ''));
        $end = $this->normalize_date_for_storage((string) ($exclusion->end_date ?? ''));

        if ($start !== '' && $end !== '') {
            try {
                $start_date = new DateTimeImmutable($start, wp_timezone());
                $end_date = new DateTimeImmutable($end, wp_timezone());
                $days = $start_date->diff($end_date)->days;
                if ($days === false) {
                    return '-';
                }
                $days++;

                return $days === 1 ? '1 jour' : $days . ' jours';
            } catch (Exception $exception) {
                return '-';
            }
        }

        return $start !== '' ? 'En cours' : '-';
    }

    private function disciplinary_exclusion_period_label(object $exclusion): string
    {
        $start = $this->display_date_only((string) ($exclusion->start_date ?? ''));
        $end = $this->display_date_only((string) ($exclusion->end_date ?? ''));

        if ($start !== '' && $end !== '') {
            return 'du ' . $start . ' au ' . $end;
        }

        if ($start !== '') {
            return 'depuis le ' . $start;
        }

        if ($end !== '') {
            return 'jusqu’au ' . $end;
        }

        return 'période non renseignée';
    }

    private function email_list($value): array
    {
        $raw = is_array($value) ? implode(',', $value) : (string) $value;
        $parts = preg_split('/[,;\s]+/', $raw);
        $emails = array();

        foreach ($parts as $part) {
            $email = sanitize_email(trim($part));
            if ($email && is_email($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function is_minor(object $student): bool
    {
        if (empty($student->date_of_birth)) {
            return false;
        }

        try {
            $birth = new DateTime((string) $student->date_of_birth);
            $today = new DateTime(wp_date('Y-m-d'));
            return $birth->diff($today)->y < 18;
        } catch (Exception $exception) {
            return false;
        }
    }

    private function minor_status_label(object $student): string
    {
        if (empty($student->date_of_birth)) {
            return 'Majorité inconnue : date de naissance manquante';
        }

        return $this->is_minor($student) ? 'Mineur' : 'Majeur';
    }

    private function contract_status_value(string $status): string
    {
        return in_array($status, array('broken', 'free_candidate'), true) ? $status : 'active';
    }

    private function contract_status_label(string $status): string
    {
        $status = $this->contract_status_value($status);
        if ($status === 'broken') {
            return 'Contrat rompu';
        }
        if ($status === 'free_candidate') {
            return 'Candidat libre';
        }

        return 'Contrat actif';
    }

    private function student_has_contract_break(object $student): bool
    {
        return $this->contract_status_value((string) ($student->contract_status ?? 'active')) === 'broken';
    }

    private function student_without_employer(object $student): bool
    {
        return in_array($this->contract_status_value((string) ($student->contract_status ?? 'active')), array('broken', 'free_candidate'), true);
    }

    private function contract_break_alert_text(object $student): string
    {
        $name = $this->student_display_name($student);
        if ($this->contract_status_value((string) ($student->contract_status ?? 'active')) === 'free_candidate') {
            return $name . ' est candidat libre. Aucune donnée employeur ou formateur ne sera reprise dans les communications.';
        }

        $parts = array($name . ' est en rupture de contrat');
        $break_date = $this->contract_break_date_label($student);
        $follow_until = $this->contract_follow_until_label($student);
        $review_date = $this->contract_review_date_label($student);

        if ($break_date !== '') {
            $parts[] = 'depuis le ' . $break_date;
        }
        if ($follow_until !== '') {
            $parts[] = 'suivi école jusqu’au ' . $follow_until;
        }
        if ($review_date !== '') {
            $parts[] = 'alerte prévue le ' . $review_date;
        }

        return implode(' · ', $parts) . '. Aucune donnée employeur ou formateur ne sera reprise dans les communications.';
    }

    private function student_trainer_recipient_emails(object $student): array
    {
        $emails = array();
        foreach (array('trainer_email', 'responsible_trainer_email', 'field_trainer_email') as $field) {
            $email = sanitize_email((string) ($student->{$field} ?? ''));
            if (is_email($email)) {
                $emails[strtolower($email)] = $email;
            }
        }

        return array_values($emails);
    }

    private function remove_student_trainer_recipients(array $to, array $cc, array $bcc, object $student): array
    {
        $blocked = array_fill_keys(array_map('strtolower', $this->student_trainer_recipient_emails($student)), true);
        if (!$blocked) {
            return array($to, $cc, $bcc);
        }

        $filter = static function (array $emails) use ($blocked): array {
            return array_values(array_filter($emails, static function (string $email) use ($blocked): bool {
                $email = sanitize_email($email);
                return $email !== '' && !isset($blocked[strtolower($email)]);
            }));
        };

        return array($filter($to), $filter($cc), $filter($bcc));
    }

    private function contract_break_date_label(object $student): string
    {
        return $this->display_date_only((string) ($student->contract_break_date ?? ''));
    }

    private function contract_follow_until_label(object $student): string
    {
        $date = $this->contract_date_from_student($student, '+3 months');
        return $date ? $date->format('d.m.Y') : '';
    }

    private function contract_review_date_label(object $student): string
    {
        $date = $this->contract_date_from_student($student, '+3 months +7 days');
        return $date ? $date->format('d.m.Y') : '';
    }

    private function contract_date_from_student(object $student, string $modifier): ?DateTimeImmutable
    {
        $break_date = $this->local_datetime((string) ($student->contract_break_date ?? ''));

        return $break_date ? $break_date->modify($modifier) : null;
    }

    private function internal_followup_email(): string
    {
        $settings = GCA_Database::get_settings();
        foreach (array('email_reply_to_email', 'email_sender_email') as $field) {
            $email = sanitize_email((string) ($settings[$field] ?? ''));
            if (is_email($email)) {
                return $email;
            }
        }

        $reminder_emails = $this->email_list((string) ($settings['reminder_emails'] ?? ''));
        if ($reminder_emails) {
            return $reminder_emails[0];
        }

        if (!$this->in_principal_space()) {
            return $this->space_owner_email();
        }

        $admin_email = sanitize_email((string) get_option('admin_email'));
        return is_email($admin_email) ? $admin_email : '';
    }

    private function internal_followup_name(): string
    {
        $user = wp_get_current_user();
        $name = trim((string) ($user->display_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $settings = GCA_Database::get_settings();
        $sender_name = trim((string) ($settings['email_sender_name'] ?? ''));

        return $sender_name !== '' ? $sender_name : 'Suivi école';
    }

    private function student_status_badge(object $student): string
    {
        $status = (string) ($student->lateness_status ?? 'none');
        $label = $this->lateness_status_label($status);
        $class = 'gca-badge';

        if ($status === 'exclusion_confirmed') {
            $class .= ' gca-badge-danger';
        } elseif ($status !== 'none') {
            $class .= ' gca-badge-warning';
        }

        $badges = array('<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>');

        $contract_status = $this->contract_status_value((string) ($student->contract_status ?? 'active'));
        if ($contract_status === 'broken') {
            $contract_label = 'Contrat rompu';
            $review_date = $this->contract_review_date_label($student);
            if ($review_date !== '') {
                $contract_label .= ' · alerte ' . $review_date;
            }
            $badges[] = '<span class="gca-badge gca-badge-danger">' . esc_html($contract_label) . '</span>';
        } elseif ($contract_status === 'free_candidate') {
            $badges[] = '<span class="gca-badge gca-badge-warning">Candidat libre</span>';
        }

        return implode(' ', $badges);
    }

    private function lateness_status_label(string $status): string
    {
        $labels = array(
            'none' => 'RAS',
            'convocation_1_sent' => '1ère convocation envoyée',
            'convocation_2_sent' => '2e convocation envoyée',
            'dean_warning_pending' => 'Doyen notifié - avertissement',
            'warning_confirmed' => 'Avertissement confirmé',
            'dean_exclusion_pending' => 'Doyen notifié - exclusion',
            'exclusion_confirmed' => 'Exclusion temporaire',
        );

        return $labels[$status] ?? $status;
    }

    private function task_type_label(string $type): string
    {
        $labels = array(
            'lateness_3' => 'Convocation 3 retards',
            'lateness_6' => 'Convocation 6 retards',
            'dean_warning' => 'Notification Doyen',
            'dean_exclusion' => 'Notification Doyen',
            'absence' => 'Absences non justifiées',
            'absence_unjustified_21' => 'Absences non justifiées > 21 jours',
            'absence_rate_30' => 'Taux d’absences ≥ 30%',
            'removal' => 'Mise à la porte',
            'contract_break_review' => 'Rupture de contrat',
        );

        return $labels[$type] ?? $type;
    }

    private function pending_task_type_options(): array
    {
        return array(
            'lateness_3' => $this->task_type_label('lateness_3'),
            'lateness_6' => $this->task_type_label('lateness_6'),
            'absence_unjustified_21' => $this->task_type_label('absence_unjustified_21'),
            'absence_rate_30' => $this->task_type_label('absence_rate_30'),
            'removal' => $this->task_type_label('removal'),
            'contract_break_review' => $this->task_type_label('contract_break_review'),
            'dean_warning' => 'Notification Doyen - avertissement',
            'dean_exclusion' => 'Notification Doyen - exclusion',
        );
    }

    private function pending_task_reason(object $task): string
    {
        $type = (string) ($task->task_type ?? '');
        $threshold = trim((string) ($task->threshold_value ?? ''));
        $threshold_number = $threshold !== '' ? (float) str_replace(',', '.', $threshold) : 0.0;
        $lateness = $this->lateness_total_for_message($task);
        $absence_count = $this->format_decimal($this->actionable_absence_count($task));
        $absence_rate = $this->format_decimal($this->actionable_absence_rate($task));
        $absence_imported_count = $this->format_decimal($task->absence_count ?? 0);
        $absence_justified_count = $this->format_decimal($this->absence_justified_count($task));
        $absence_adjustment = $this->absence_justified_count($task) > 0
            ? ' / importé : ' . $absence_imported_count . ' / justifiées : ' . $absence_justified_count
            : '';
        $removals = (int) ($task->removal_count ?? 0);

        if (in_array($type, array('lateness_3', 'lateness_6'), true)) {
            $threshold_label = $threshold_number > 0 ? (string) (int) $threshold_number : ($type === 'lateness_3' ? '3' : '6');
            return $lateness . ' retards enregistrés / seuil ' . $threshold_label;
        }

        if ($type === 'absence') {
            $threshold_label = $threshold_number > 0 ? ' / seuil ' . $this->format_decimal($threshold_number) . '%' : '';
            return 'Périodes d’absence non justifiées : ' . $absence_count . ' / taux non justifié : ' . $absence_rate . '%' . $threshold_label . $absence_adjustment;
        }

        if ($type === 'absence_unjustified_21') {
            $threshold_label = $threshold_number > 0 ? ' / seuil ' . $this->format_decimal($threshold_number) : '';
            return 'Absences non justifiées : ' . $absence_count . ' jours/périodes' . $threshold_label . ' / taux : ' . $absence_rate . '%' . $absence_adjustment;
        }

        if ($type === 'absence_rate_30') {
            $threshold_label = $threshold_number > 0 ? ' / seuil ' . $this->format_decimal($threshold_number) . '%' : '';
            return 'Taux d’absences non justifiées : ' . $absence_rate . '%' . $threshold_label . ' / périodes : ' . $absence_count . $absence_adjustment;
        }

        if ($type === 'removal') {
            $removal_index = $threshold_number > 0 ? (int) $threshold_number : $removals;
            $removal_label = $removals > 1 ? 'mises à la porte enregistrées' : 'mise à la porte enregistrée';
            return $removals . ' ' . $removal_label . ' / n°' . $removal_index . ' à traiter';
        }

        if ($type === 'dean_warning') {
            $threshold_label = $threshold_number > 0 ? ' / seuil Doyen ' . (int) $threshold_number : '';
            return $lateness . ' retards enregistrés' . $threshold_label . ', après les convocations précédentes';
        }

        if ($type === 'dean_exclusion') {
            $message = trim((string) ($task->message ?? ''));
            return ($message !== '' ? $message . ' ' : '') . 'Compteur actuel : ' . $lateness . ' retards';
        }

        if ($type === 'contract_break_review') {
            $review_date = $this->display_date_only($threshold);
            return $review_date !== ''
                ? 'Échéance de rupture atteinte le ' . $review_date
                : 'Échéance de rupture atteinte';
        }

        $message = trim((string) ($task->message ?? ''));
        return $message !== '' ? $message : 'Action en attente de traitement';
    }

    private function task_metric_class(object $task, string $metric): string
    {
        $type = (string) ($task->task_type ?? '');
        $matches = array(
            'lateness' => array('lateness_3', 'lateness_6', 'dean_warning', 'dean_exclusion'),
            'absence' => array('absence', 'absence_unjustified_21', 'absence_rate_30'),
            'removal' => array('removal'),
        );

        return in_array($type, $matches[$metric] ?? array(), true) ? 'gca-metric-alert' : '';
    }

    private function communication_type_label(string $type): string
    {
        if ($type === 'absence_unjustified_21') {
            return 'Information absences non justifiées';
        }

        if ($type === 'absence_rate_30') {
            return 'Information taux d’absences';
        }

        if (str_starts_with($type, 'absence_')) {
            return 'Convocation absences';
        }

        if (str_starts_with($type, 'removal_')) {
            $number = (int) substr($type, strlen('removal_'));
            return $number > 0 ? 'Mise à la porte ' . $number : 'Mise à la porte';
        }

        if ($type === 'student_report') {
            return 'Rapport formateur';
        }

        if ($type === 'trainer_bulk_message') {
            return 'E-mail individuel formateur';
        }

        if ($type === 'student_bulk_message') {
            return 'Message groupé élève';
        }

        return $this->task_type_label($type);
    }

    private function communication_recipients_summary(object $communication): string
    {
        $parts = array();
        $fields = array(
            'recipients_to' => 'À',
            'recipients_cc' => 'Copie',
            'recipients_bcc' => 'Copie cachée',
        );

        foreach ($fields as $field => $label) {
            $value = trim((string) ($communication->{$field} ?? ''));
            if ($value !== '') {
                $parts[] = $label . ' : ' . $value;
            }
        }

        return implode(' · ', $parts);
    }

    private function event_type_label(string $type): string
    {
        $labels = array(
            'student_saved' => 'Fiche modifiée',
            'student_quick_updated' => 'Modification rapide',
            'student_trainer_assigned' => 'Formateur rattaché',
            'student_trainer_removed' => 'Formateur retiré',
            'student_trainer_updated' => 'Coordonnées formateur mises à jour',
            'communication_sent' => 'Communication envoyée',
            'student_archived' => 'Archivage',
            'student_anonymized' => 'Anonymisation',
            'warning_confirmed' => 'Avertissement confirmé',
            'exclusion_confirmed' => 'Exclusion',
            'detention_no_show' => 'Retenue manquée',
            'absence_justified' => 'Absence validée',
            'task_resolved' => 'Action marquée comme traitée',
            'student_report_sent' => 'Rapport envoyé',
            'trainer_bulk_message_sent' => 'E-mail individuel formateur',
            'student_bulk_message_sent' => 'Message groupé élève',
            'disciplinary_warning_recorded' => 'Avertissement reçu',
            'disciplinary_warning_removed' => 'Avertissement retiré',
            'disciplinary_exclusion_recorded' => 'Exclusion enregistrée',
            'disciplinary_exclusion_updated' => 'Exclusion modifiée',
            'disciplinary_exclusion_deleted' => 'Exclusion supprimée',
            'expulsion_recorded' => 'Expulsion reçue',
            'expulsion_removed' => 'Expulsion retirée',
            'wellbeing_council_recorded' => 'Conseil de savoir-être',
            'wellbeing_council_removed' => 'Conseil de savoir-être retiré',
            'contract_break_recorded' => 'Rupture de contrat',
            'contract_break_updated' => 'Rupture de contrat modifiée',
            'contract_break_closed' => 'Contrat actif',
            'free_candidate_recorded' => 'Candidat libre',
            'free_candidate_updated' => 'Candidat libre modifié',
        );

        return $labels[$type] ?? $type;
    }

    private function event_detail(object $event): string
    {
        $payload = json_decode((string) $event->payload_json, true);
        if (!is_array($payload)) {
            $payload = array();
        }

        $status = trim((string) $event->status);

        if ($event->event_type === 'warning_confirmed') {
            $lateness_count = isset($payload['lateness_count']) ? (int) $payload['lateness_count'] : 0;
            return $lateness_count > 0
                ? 'Retards au moment de la confirmation : ' . $lateness_count . '.'
                : ($status !== '' ? $status : 'Courrier d’avertissement confirmé.');
        }

        if ($event->event_type === 'exclusion_confirmed') {
            $start = $this->display_date_only((string) ($payload['start'] ?? ''));
            $end = $this->display_date_only((string) ($payload['end'] ?? ''));

            if ($start !== '' && $end !== '') {
                return 'Période : du ' . $start . ' au ' . $end . '.';
            }

            if ($start !== '') {
                return 'Début : ' . $start . '.';
            }

            return $status !== '' ? $status : 'Exclusion temporaire confirmée.';
        }

        if (in_array($event->event_type, array('disciplinary_exclusion_recorded', 'disciplinary_exclusion_updated', 'disciplinary_exclusion_deleted'), true)) {
            $exclusion = (object) array(
                'start_date' => (string) ($payload['start'] ?? ''),
                'end_date' => (string) ($payload['end'] ?? ''),
            );
            $detail = $status !== '' ? $status : $this->event_type_label((string) $event->event_type);
            $detail .= ' : ' . $this->disciplinary_exclusion_period_label($exclusion);
            $duration = trim((string) ($payload['duration'] ?? ''));
            if ($duration === '') {
                $duration = $this->disciplinary_exclusion_duration_label($exclusion);
            }
            if ($duration !== '' && $duration !== '-') {
                $detail .= ' (' . $duration . ')';
            }
            $notes = trim((string) ($payload['notes'] ?? ''));
            if ($notes !== '') {
                $detail .= '. Note : ' . $notes;
            }

            return $detail . '.';
        }

        if ($event->event_type === 'detention_no_show') {
            $date = $this->display_date_only((string) ($payload['date'] ?? ''));
            return $date !== ''
                ? 'L’apprenti ne s’est pas présenté à la retenue prévue le ' . $date . '.'
                : 'L’apprenti ne s’est pas présenté à une retenue précédente.';
        }

        if ($event->event_type === 'absence_justified') {
            $task_type = (string) ($payload['task_type'] ?? '');
            $label = $task_type !== '' ? $this->task_type_label($task_type) : 'justificatif d’absence';
            $message = trim((string) ($payload['message'] ?? ''));
            $absence_date = $this->display_date_only((string) ($payload['absence_date'] ?? $payload['date'] ?? ''));
            $justified_delta = $this->format_decimal($payload['justified_delta'] ?? 0);
            $justified_total = $this->format_decimal($payload['justified_total'] ?? 0);
            $imported_count = $this->format_decimal($payload['imported_absence_count'] ?? 0);
            $remaining_count = $this->format_decimal($payload['remaining_absence_count'] ?? 0);
            $remaining_rate = $this->format_decimal($payload['remaining_absence_rate'] ?? 0);
            $detail = $task_type !== '' ? 'Absence validée sans envoi d’e-mail : ' . $label : 'Justificatif d’absence enregistré';
            if ($absence_date !== '') {
                $detail .= ' le ' . $absence_date;
            }
            $detail .= '. Périodes justifiées : ' . $justified_delta . ' / total justifié : ' . $justified_total . ' / importé : ' . $imported_count . ' / reste à traiter : ' . $remaining_count . ' (' . $remaining_rate . ' %).';
            return $detail . ($message !== '' ? ' ' . $message . '.' : '');
        }

        if ($event->event_type === 'task_resolved') {
            $task_type = (string) ($payload['task_type'] ?? '');
            $task_label = $task_type !== '' ? $this->task_type_label($task_type) : $status;
            if ($task_type === 'removal' && isset($payload['threshold_value'])) {
                $task_label = 'Mise à la porte ' . (int) $payload['threshold_value'];
            }

            $message = trim((string) ($payload['message'] ?? ''));
            $detail = 'Action marquée comme traitée';
            if ($task_label !== '') {
                $detail .= ' : ' . $task_label;
            }
            if ($message !== '') {
                $detail .= '. ' . $message;
            }

            return $detail . '.';
        }

        if ($event->event_type === 'student_saved') {
            return 'Fiche modifiée manuellement.';
        }

        if ($event->event_type === 'student_quick_updated') {
            $field_labels = array(
                'gender' => 'genre',
                'email' => 'e-mail élève',
                'phone' => 'téléphone élève',
                'trainer_id' => 'formateur',
                'trainer_name' => 'nom responsable',
                'trainer_company' => 'entreprise formatrice',
                'trainer_company_address' => 'adresse entreprise',
                'trainer_company_zip' => 'NPA entreprise',
                'trainer_company_city' => 'localité entreprise',
                'trainer_email' => 'e-mail du responsable',
                'trainer_phone' => 'téléphone responsable',
                'verified_at' => 'statut de vérification',
                'verified_by' => 'auteur de la vérification',
            );
            $fields = isset($payload['fields']) && is_array($payload['fields'])
                ? array_values(array_filter(array_map('sanitize_key', $payload['fields'])))
                : array();
            $labels = array();
            foreach ($fields as $field) {
                $labels[] = $field_labels[$field] ?? $field;
            }

            return $labels
                ? 'Modification rapide : ' . implode(', ', $labels) . '.'
                : 'Fiche modifiée rapidement.';
        }

        if (in_array($event->event_type, array('student_trainer_assigned', 'student_trainer_removed', 'student_trainer_updated'), true)) {
            $trainer_name = trim((string) ($payload['trainer_name'] ?? ''));
            $trainer_email = sanitize_email((string) ($payload['trainer_email'] ?? ''));

            if ($event->event_type === 'student_trainer_removed') {
                return 'Le formateur a été retiré de la fiche.';
            }

            $detail = $event->event_type === 'student_trainer_assigned'
                ? 'Formateur rattaché'
                : 'Coordonnées du formateur mises à jour';

            if ($trainer_name !== '') {
                $detail .= ' : ' . $trainer_name;
            }

            if (is_email($trainer_email)) {
                $detail .= ' (' . $trainer_email . ')';
            }

            return $detail . '.';
        }

        if (in_array($event->event_type, array('contract_break_recorded', 'contract_break_updated', 'contract_break_closed'), true)) {
            if ($event->event_type === 'contract_break_closed') {
                return 'Le contrat de formation est à nouveau indiqué comme actif.';
            }

            $break_date = $this->display_date_only((string) ($payload['break_date'] ?? ''));
            $follow_until = $this->display_date_only((string) ($payload['follow_until'] ?? ''));
            $review_date = $this->display_date_only((string) ($payload['review_date'] ?? ''));
            $detail = $status !== '' ? $status : $this->event_type_label((string) $event->event_type);
            if ($break_date !== '') {
                $detail .= ' le ' . $break_date;
            }
            if ($follow_until !== '') {
                $detail .= '. Suivi école jusqu’au ' . $follow_until;
            }
            if ($review_date !== '') {
                $detail .= '. Alerte prévue le ' . $review_date;
            }

            return $detail . '.';
        }

        if (in_array($event->event_type, array('free_candidate_recorded', 'free_candidate_updated'), true)) {
            return ($status !== '' ? $status : $this->event_type_label((string) $event->event_type)) . '.';
        }

        foreach ($this->student_followup_decision_definitions() as $definition) {
            if (!in_array($event->event_type, array($definition['recorded_event'], $definition['removed_event']), true)) {
                continue;
            }

            $date = $this->display_date_only((string) ($payload['date'] ?? ''));
            $detail = $status !== '' ? $status : $this->event_type_label((string) $event->event_type);
            if ($event->event_type === $definition['recorded_event'] && $date !== '') {
                $detail .= ' le ' . $date;
            }

            return $detail . '.';
        }

        if ($event->event_type === 'trainer_bulk_message_sent') {
            $email = sanitize_email((string) ($payload['trainer_email'] ?? ''));
            $copy_emails = isset($payload['student_copy_emails']) && is_array($payload['student_copy_emails'])
                ? array_values(array_filter(array_map('sanitize_email', $payload['student_copy_emails'])))
                : array();
            $apprentices = isset($payload['apprentices']) && is_array($payload['apprentices'])
                ? array_values(array_filter(array_map('sanitize_text_field', $payload['apprentices'])))
                : array();
            $detail = $email !== ''
                ? 'E-mail individuel envoyé au formateur : ' . $email . '.'
                : 'E-mail individuel envoyé au formateur.';

            if ($apprentices) {
                $shown_apprentices = array_slice($apprentices, 0, 8);
                $detail .= ' Apprentis mentionnés : ' . implode(', ', $shown_apprentices);
                if (count($apprentices) > count($shown_apprentices)) {
                    $detail .= ' +' . (count($apprentices) - count($shown_apprentices));
                }
                $detail .= '.';
            }

            if ($copy_emails) {
                $detail .= ' Élèves en copie : ' . count($copy_emails) . '.';
            }

            return $detail;
        }

        if ($event->event_type === 'student_bulk_message_sent') {
            $email = sanitize_email((string) ($payload['student_email'] ?? ''));
            return $email !== ''
                ? 'Message groupé envoyé à l’élève : ' . $email . '.'
                : 'Message groupé envoyé à l’élève.';
        }

        if ($event->event_type === 'student_report_sent') {
            return 'Rapport complet envoyé au formateur.';
        }

        return $status !== '' ? $status : '';
    }

    private function import_type_label(string $type): string
    {
        if ($type === 'lagap') {
            return 'Lagap-EP';
        }

        if ($type === 'photos_pdf') {
            return 'Photos PDF';
        }

        if ($type === 'grades') {
            return 'Notes';
        }

        return $type === 'tracking' ? 'Suivi périodique' : 'Liste apprentis';
    }

    private function audit_action_label(string $action): string
    {
        $labels = array(
            'import_prepared' => 'Import préparé',
            'import_processed' => 'Import traité',
            'import_commit_denied' => 'Import refusé',
            'import_mapping_saved' => 'Mapping import sauvegardé',
            'import_backup' => 'Sauvegarde import',
            'settings_saved' => 'Paramètres modifiés',
            'class_saved' => 'Classe sauvegardée',
            'student_saved' => 'Fiche apprenti sauvegardée',
            'students_quick_updated' => 'Fiches apprentis modifiées rapidement',
            'email_sent' => 'E-mail envoyé',
            'absence_justification_saved' => 'Justificatif d’absence enregistré',
            'absence_justified_without_email' => 'Absence validée sans e-mail',
            'task_resolved' => 'Tâche traitée',
            'student_archived' => 'Apprenti archivé',
            'student_anonymized' => 'Apprenti anonymisé',
            'pdf_downloaded' => 'PDF téléchargé',
            'student_exported' => 'Fiche exportée',
            'students_exported' => 'Liste apprentis exportée',
            'trainers_exported' => 'Liste formateurs exportée',
            'student_report_generated' => 'Rapport généré',
            'student_report_sent' => 'Rapport envoyé',
            'class_reports_generated' => 'Rapports de classe générés',
            'detention_no_show_saved' => 'Retenue manquée enregistrée',
            'trainer_bulk_sent' => 'E-mails individuels formateurs envoyés',
            'trainer_bulk_test_sent' => 'Test d’e-mail individuel formateur envoyé',
            'trainer_template_saved' => 'Message préformaté enregistré',
            'free_template_saved' => 'Modèle de message libre enregistré',
            'trainer_profiles_saved' => 'Formules d’appel formateurs enregistrées',
            'trainer_saved' => 'Formateur sauvegardé',
            'trainer_assignments_saved' => 'Rattachements formateur modifiés',
            'weekly_reminder_sent' => 'Ancien rappel hebdomadaire envoyé',
            'stale_task_reminder_sent' => 'Rappel interne actions en attente envoyé',
            'import_freshness_reminder_sent' => 'Rappel interne import Lagap envoyé',
            'photos_imported' => 'Photos importées',
            'photos_import_failed' => 'Import photos refusé',
            'photos_import_prepared' => 'Import photos préparé',
            'photos_import_commit_denied' => 'Validation import photos refusée',
            'task_saved' => 'Action à traiter modifiée',
            'task_deleted' => 'Action à traiter supprimée',
            'action_saved' => 'Action exécutée modifiée',
            'action_deleted' => 'Action exécutée supprimée',
            'disciplinary_exclusion_saved' => 'Exclusion enregistrée',
            'disciplinary_exclusion_updated' => 'Exclusion modifiée',
            'disciplinary_exclusion_deleted' => 'Exclusion supprimée',
            'student_deleted' => 'Apprenti supprimé',
            'class_deleted' => 'Classe supprimée',
        );

        return $labels[$action] ?? $action;
    }

    private function status_label(string $status): string
    {
        return $status === 'active' ? 'Active' : 'Archivée';
    }

    private function template_label(string $key): string
    {
        $labels = array(
            'lateness_3_subject' => 'Retards 3 - objet',
            'lateness_3_body' => 'Retards 3 - message',
            'lateness_6_subject' => 'Retards 6 - objet',
            'lateness_6_body' => 'Retards 6 - message',
            'absence_subject' => 'Absences - objet',
            'absence_body' => 'Absences - message',
            'absence_unjustified_21_subject' => 'Absences > 21 - objet',
            'absence_unjustified_21_body' => 'Absences > 21 - message',
            'absence_rate_30_subject' => 'Absences 30% - objet',
            'absence_rate_30_body' => 'Absences 30% - message',
            'dean_warning_subject' => 'Doyen avertissement - objet',
            'dean_warning_body' => 'Doyen avertissement - message',
            'dean_exclusion_subject' => 'Doyen exclusion - objet',
            'dean_exclusion_body' => 'Doyen exclusion - message',
            'contract_break_review_subject' => 'Rupture contrat - objet',
            'contract_break_review_body' => 'Rupture contrat - message',
            'student_report_subject' => 'Rapport formateur - objet',
            'student_report_body' => 'Rapport formateur - message',
            'trainer_bulk_subject' => 'E-mail individuel formateur - objet',
            'trainer_bulk_body' => 'E-mail individuel formateur - message',
        );

        return $labels[$key] ?? $key;
    }

    private function student_with_class(int $student_id): ?object
    {
        global $wpdb;

        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, c.name AS class_name, c.school_year
                 FROM {$students} s
                 INNER JOIN {$classes} c ON c.id = s.class_id
                 WHERE s.id = %d",
                $student_id
            )
        );
    }

    private function create_student_report(int $student_id): string
    {
        global $wpdb;

        $student = $this->student_with_class($student_id);
        if (!$student) {
            return '';
        }

        $class = (object) array(
            'id' => (int) $student->class_id,
            'name' => (string) $student->class_name,
            'school_year' => (string) $student->school_year,
        );
        $history_by_student = $this->recent_action_history_by_student(array($student_id), 200, true);
        $history = $history_by_student[$student_id] ?? array();
        foreach ($history as $index => $item) {
            $history[$index]['date'] = $this->display_date((string) ($item['date'] ?? ''));
        }

        $tasks_table = GCA_Database::table('tasks');
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tasks_table} WHERE student_id = %d AND status = 'pending' ORDER BY created_at ASC",
                $student_id
            )
        );
        $pending = array();
        foreach ($tasks as $task) {
            $pending[] = array(
                'label' => $this->task_type_label((string) $task->task_type),
                'message' => (string) $task->message,
            );
        }
        $disciplinary_exclusions = array();
        foreach ($this->disciplinary_exclusions_for_student($student_id) as $exclusion) {
            $disciplinary_exclusions[] = array(
                'start' => (string) $exclusion->start_date,
                'end' => (string) $exclusion->end_date,
                'period' => $this->disciplinary_exclusion_period_label($exclusion),
                'duration' => $this->disciplinary_exclusion_duration_label($exclusion),
                'notes' => (string) $exclusion->notes,
            );
        }
        $grades = array();
        foreach ($this->student_grades_for_student($student_id) as $grade) {
            $grades[] = array(
                'assessment' => (string) $grade->assessment,
                'grade_value' => (string) $grade->grade_value,
            );
        }

        return GCA_PDF::create_student_report_pdf(
            $student,
            $class,
            $history,
            $pending,
            array(
                'photo_path' => $this->private_path_from_relative((string) $student->photo_path),
                'gender_label' => $this->gender_label((string) $student->gender),
                'minor_label' => $this->minor_status_label($student),
                'status_label' => $this->lateness_status_label((string) $student->lateness_status),
                'contract_status_label' => $this->contract_status_label((string) ($student->contract_status ?? 'active')),
                'contract_break_date' => $this->contract_break_date_label($student),
                'contract_follow_until' => $this->contract_follow_until_label($student),
                'contract_review_date' => $this->contract_review_date_label($student),
                'disciplinary_exclusions' => $disciplinary_exclusions,
                'grades' => $grades,
            )
        );
    }

    private function download_private_pdf(string $path): void
    {
        $private = realpath(GCA_Database::private_dir());
        $real_path = realpath($path);

        if (!$private || !$real_path || !str_starts_with($real_path, trailingslashit($private))) {
            status_header(403);
            exit;
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($real_path) . '"');
        header('Content-Length: ' . filesize($real_path));
        readfile($real_path);
        exit;
    }

    private function student_photo_markup(object $student, string $size = 'thumbnail'): string
    {
        if (empty($student->photo_path)) {
            $initials = substr((string) $student->first_name, 0, 1) . substr((string) $student->last_name, 0, 1);
            return '<span class="gca-photo-placeholder">' . esc_html(strtoupper($initials)) . '</span>';
        }

        return '<img class="gca-photo gca-photo-' . esc_attr($size) . '" src="' . esc_url($this->student_photo_url((int) $student->id)) . '" alt="' . esc_attr(trim((string) $student->first_name . ' ' . (string) $student->last_name)) . '">';
    }

    private function student_photo_url(int $student_id): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=gca_student_photo&student_id=' . $student_id), 'gca_student_photo_' . $student_id);
    }

    private function photo_assignment_students(): array
    {
        global $wpdb;

        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $rows = $wpdb->get_results(
            "SELECT s.id, s.class_id, s.first_name, s.last_name, COALESCE(c.name, '') AS class_name, COALESCE(c.school_year, '') AS school_year
             FROM {$students} s
             LEFT JOIN {$classes} c ON c.id = s.class_id
             WHERE s.archived_at IS NULL
             ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC"
        );

        return is_array($rows) ? $rows : array();
    }

    private function store_uploaded_student_photo(int $student_id, array $file): bool
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        $image = $this->image_resource_from_file((string) $file['tmp_name']);
        if (!$image) {
            return false;
        }

        ob_start();
        imagejpeg($image, null, 88);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $this->store_student_photo_bytes($student_id, $bytes);
    }

    private function image_resource_from_file(string $path)
    {
        if (!function_exists('getimagesize')) {
            return null;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        $type = (int) $info[2];
        if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
            return @imagecreatefromjpeg($path);
        }
        if ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
            $source = @imagecreatefrompng($path);
        } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
            $source = @imagecreatefromwebp($path);
        } else {
            return null;
        }

        if (!$source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        return $canvas;
    }

    private function store_student_photo_bytes(int $student_id, string $bytes): bool
    {
        global $wpdb;

        if ($bytes === '') {
            return false;
        }

        GCA_Database::ensure_private_dirs();
        $path = GCA_Database::private_dir('photos/student-' . $student_id . '.jpg');
        if (file_put_contents($path, $bytes) === false) {
            return false;
        }

        $relative = GCA_Database::private_relative_path($path);
        $wpdb->update(
            GCA_Database::table('students'),
            array('photo_path' => $relative, 'updated_at' => GCA_Database::now()),
            array('id' => $student_id),
            array('%s', '%s'),
            array('%d')
        );

        return true;
    }

    private function delete_student_photo(int $student_id): void
    {
        global $wpdb;

        $table = GCA_Database::table('students');
        $path = (string) $wpdb->get_var($wpdb->prepare("SELECT photo_path FROM {$table} WHERE id = %d", $student_id));
        $this->delete_private_file($this->private_path_from_relative($path));
        $wpdb->update($table, array('photo_path' => '', 'updated_at' => GCA_Database::now()), array('id' => $student_id), array('%s', '%s'), array('%d'));
    }

    private function delete_student_records(int $student_id): void
    {
        global $wpdb;

        $communications = GCA_Database::table('communications');
        $attachments = $wpdb->get_col($wpdb->prepare("SELECT attachment_path FROM {$communications} WHERE student_id = %d", $student_id));
        foreach ($attachments as $attachment) {
            $this->delete_private_file((string) $attachment);
        }

        $this->delete_student_pdfs($student_id);
        $this->delete_student_reports($student_id);
        $this->delete_student_photo($student_id);
        $wpdb->delete(GCA_Database::table('tasks'), array('student_id' => $student_id), array('%d'));
        $wpdb->delete($communications, array('student_id' => $student_id), array('%d'));
        $wpdb->delete(GCA_Database::table('events'), array('student_id' => $student_id), array('%d'));
        $wpdb->delete(GCA_Database::table('absence_justifications'), array('student_id' => $student_id), array('%d'));
        $wpdb->delete(GCA_Database::table('disciplinary_exclusions'), array('student_id' => $student_id), array('%d'));
        $wpdb->delete(GCA_Database::table('grades'), array('student_id' => $student_id), array('%d'));
        $wpdb->delete(GCA_Database::table('students'), array('id' => $student_id), array('%d'));
    }

    private function delete_private_file(string $path): void
    {
        if ($path === '') {
            return;
        }

        $private = realpath(GCA_Database::private_dir());
        $real_path = realpath($path);

        if ($private && $real_path && str_starts_with($real_path, trailingslashit($private)) && is_file($real_path)) {
            @unlink($real_path);
        }
    }

    private function private_path_from_relative(string $relative): string
    {
        $relative = ltrim(str_replace(array('\\', "\0"), '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return '';
        }

        return GCA_Database::private_dir($relative);
    }

    private function extract_student_photos_from_pdf(string $path): array
    {
        if (!is_readable($path) || !function_exists('gzuncompress') || !function_exists('imagecreatetruecolor')) {
            return array();
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return array();
        }

        $figures = $this->extract_pdf_figure_alt_entries($bytes);
        if ($figures) {
            return $this->extract_structured_pdf_student_photos($bytes, $figures);
        }

        $names = $this->extract_pdf_alt_names($bytes);
        $images = $this->extract_pdf_jpeg_images($bytes);
        $count = min(count($names), count($images));
        $photos = array();

        for ($index = 0; $index < $count; $index++) {
            $name = trim((string) $names[$index]);
            if ($name === '') {
                continue;
            }

            $photos[] = array(
                'name' => $name,
                'bytes' => $images[$index],
            );
        }

        return $photos;
    }

    private function extract_structured_pdf_student_photos(string $bytes, array $figures): array
    {
        $image_refs = $this->extract_pdf_figure_image_refs($bytes);
        $photos = array();

        foreach ($figures as $figure) {
            $name = trim((string) ($figure['name'] ?? ''));
            $page_id = (int) ($figure['page_id'] ?? 0);
            $mcid = (int) ($figure['mcid'] ?? -1);

            if ($name === '' || $page_id <= 0 || $mcid < 0) {
                continue;
            }

            $image_object_id = (int) ($image_refs[$page_id . ':' . $mcid] ?? 0);
            if ($image_object_id <= 0) {
                continue;
            }

            $image_stream = $this->extract_pdf_stream_object($bytes, $image_object_id);
            if (!$image_stream) {
                continue;
            }

            $jpeg = $this->pdf_image_stream_to_jpeg($image_stream);
            if ($jpeg === '') {
                continue;
            }

            $photos[] = array(
                'name' => $name,
                'bytes' => $jpeg,
            );
        }

        return $photos;
    }

    private function extract_pdf_figure_alt_entries(string $bytes): array
    {
        $figures = array();
        preg_match_all('/(?:^|[\r\n])(\d+)\s+0\s+obj\s*(.*?)\s*endobj/s', $bytes, $objects, PREG_SET_ORDER);

        foreach ($objects as $object) {
            $body = (string) $object[2];
            if (!preg_match('/\/S\s*\/Figure\b/', $body)) {
                continue;
            }

            if (!preg_match('/\/Alt\s+(\((?:\\\\.|[^\\\\)])*\)|<FEFF[0-9A-Fa-f]+>)/s', $body, $alt_match)) {
                continue;
            }

            if (!preg_match('/\/Pg\s+(\d+)\s+0\s+R/', $body, $page_match) || !preg_match('/\/K\s+(\d+)/', $body, $mcid_match)) {
                continue;
            }

            $name = str_starts_with((string) $alt_match[1], '<')
                ? $this->decode_pdf_utf16_hex((string) $alt_match[1])
                : $this->decode_pdf_literal((string) $alt_match[1]);

            if ($name === '') {
                continue;
            }

            $figures[] = array(
                'name' => $name,
                'page_id' => (int) $page_match[1],
                'mcid' => (int) $mcid_match[1],
            );
        }

        return $figures;
    }

    private function extract_pdf_figure_image_refs(string $bytes): array
    {
        $refs = array();
        preg_match_all('/(?:^|[\r\n])(\d+)\s+0\s+obj\s*(.*?)\s*endobj/s', $bytes, $objects, PREG_SET_ORDER);

        foreach ($objects as $object) {
            $page_id = (int) $object[1];
            $body = (string) $object[2];

            if (!preg_match('/\/Type\s*\/Page\b/', $body) || !preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $body, $content_match)) {
                continue;
            }

            $xobjects = array();
            if (preg_match('/\/XObject\s*<<(.*?)>>/s', $body, $xobject_match)) {
                preg_match_all('/\/([A-Za-z0-9_.-]+)\s+(\d+)\s+0\s+R/', (string) $xobject_match[1], $xobject_refs, PREG_SET_ORDER);
                foreach ($xobject_refs as $xobject_ref) {
                    $xobjects[(string) $xobject_ref[1]] = (int) $xobject_ref[2];
                }
            }

            if (!$xobjects) {
                continue;
            }

            $content_object_id = (int) $content_match[1];
            $content_stream = $this->extract_pdf_stream_object($bytes, $content_object_id);
            $content = $content_stream ? $this->pdf_stream_data($content_stream) : '';
            if ($content === '') {
                continue;
            }

            preg_match_all('/\/Figure\s*<<\s*\/MCID\s+(\d+)\s*>>\s*BDC(.*?)(?=EMC)/s', $content, $figures, PREG_SET_ORDER);
            foreach ($figures as $figure) {
                $mcid = (int) $figure[1];
                $figure_body = (string) $figure[2];

                if (!preg_match('/\/([A-Za-z0-9_.-]+)\s+Do\b/', $figure_body, $do_match)) {
                    continue;
                }

                $xobject_name = (string) $do_match[1];
                if (isset($xobjects[$xobject_name])) {
                    $refs[$page_id . ':' . $mcid] = (int) $xobjects[$xobject_name];
                }
            }
        }

        return $refs;
    }

    private function extract_pdf_alt_names(string $bytes): array
    {
        $names = array();
        preg_match_all('/\/Alt\s+(\((?:\\\\.|[^\\\\)])*\)|<FEFF[0-9A-Fa-f]+>)/s', $bytes, $matches);

        foreach ($matches[1] as $raw) {
            $decoded = str_starts_with($raw, '<')
                ? $this->decode_pdf_utf16_hex($raw)
                : $this->decode_pdf_literal($raw);
            if ($decoded !== '') {
                $names[] = $decoded;
            }
        }

        return $names;
    }

    private function decode_pdf_utf16_hex(string $raw): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $raw);
        if (str_starts_with(strtoupper((string) $hex), 'FEFF')) {
            $hex = substr((string) $hex, 4);
        }

        $text = '';
        for ($i = 0; $i + 3 < strlen((string) $hex); $i += 4) {
            $code = hexdec(substr((string) $hex, $i, 4));
            $text .= html_entity_decode('&#x' . dechex($code) . ';', ENT_QUOTES, 'UTF-8');
        }

        return trim($text);
    }

    private function decode_pdf_literal(string $raw): string
    {
        $value = substr($raw, 1, -1);
        $value = (string) preg_replace_callback(
            '/\\\\([0-7]{1,3}|.)/s',
            static function (array $match): string {
                $escaped = $match[1];
                if (preg_match('/^[0-7]{1,3}$/', $escaped)) {
                    return chr(octdec($escaped));
                }

                return match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\b",
                    'f' => "\f",
                    default => $escaped,
                };
            },
            $value
        );

        if (function_exists('iconv')) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        return trim($value);
    }

    private function extract_pdf_stream_object(string $bytes, int $object_id): array
    {
        if ($object_id <= 0) {
            return array();
        }

        $pattern = '/(?:^|[\r\n])' . preg_quote((string) $object_id, '/') . '\s+0\s+obj\s*<<(?P<dict>.*?)>>\s*stream\r?\n(?P<stream>.*?)\r?\nendstream/s';
        if (!preg_match($pattern, $bytes, $match)) {
            return array();
        }

        return array(
            'dict' => (string) $match['dict'],
            'stream' => (string) $match['stream'],
        );
    }

    private function extract_pdf_stream_objects(string $bytes): array
    {
        $streams = array();
        preg_match_all('/(?:^|[\r\n])(\d+)\s+0\s+obj\s*<<(?P<dict>.*?)>>\s*stream\r?\n(?P<stream>.*?)\r?\nendstream/s', $bytes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $streams[(int) $match[1]] = array(
                'dict' => (string) $match['dict'],
                'stream' => (string) $match['stream'],
            );
        }

        return $streams;
    }

    private function pdf_stream_data(array $stream): string
    {
        $dict = (string) ($stream['dict'] ?? '');
        $bytes = (string) ($stream['stream'] ?? '');

        if (str_contains($dict, '/FlateDecode')) {
            $raw = @gzuncompress($bytes);
            return is_string($raw) ? $raw : '';
        }

        return $bytes;
    }

    private function extract_pdf_jpeg_images(string $bytes): array
    {
        $images = array();
        $streams = $this->extract_pdf_stream_objects($bytes);

        foreach ($streams as $stream) {
            $jpeg = $this->pdf_image_stream_to_jpeg($stream);
            if ($jpeg !== '') {
                $images[] = $jpeg;
            }
        }

        return $images;
    }

    private function pdf_image_stream_to_jpeg(array $stream): string
    {
        $dict = (string) ($stream['dict'] ?? '');
        if (!str_contains($dict, '/Subtype /Image')) {
            return '';
        }

        if (!preg_match('/\/Width\s+(\d+)/', $dict, $width_match) || !preg_match('/\/Height\s+(\d+)/', $dict, $height_match)) {
            return '';
        }

        $width = (int) $width_match[1];
        $height = (int) $height_match[1];
        $bytes = (string) ($stream['stream'] ?? '');

        if (str_contains($dict, '/DCTDecode')) {
            if ($this->is_missing_photo_placeholder($bytes, $width, $height, true)) {
                return '';
            }

            return $bytes;
        }

        if (!str_contains($dict, '/FlateDecode')) {
            return '';
        }

        $raw = @gzuncompress($bytes);
        if ($raw === false || strlen($raw) !== $width * $height * 3) {
            return '';
        }

        $jpeg = $this->rgb_bytes_to_jpeg($raw, $width, $height);
        if ($jpeg === '' || $this->is_missing_photo_placeholder($jpeg, $width, $height, false)) {
            return '';
        }

        return $jpeg;
    }

    private function is_missing_photo_placeholder(string $jpeg, int $width, int $height, bool $allow_size_heuristic): bool
    {
        if (function_exists('imagecreatefromstring') && strlen($jpeg) > 0) {
            $image = @imagecreatefromstring($jpeg);
            if ($image) {
                $image_width = imagesx($image);
                $image_height = imagesy($image);
                $step_x = max(1, (int) floor($image_width / 24));
                $step_y = max(1, (int) floor($image_height / 24));
                $samples = 0;
                $brightness = 0.0;
                $saturation = 0.0;

                for ($y = 0; $y < $image_height; $y += $step_y) {
                    for ($x = 0; $x < $image_width; $x += $step_x) {
                        $rgb = imagecolorat($image, $x, $y);
                        $red = ($rgb >> 16) & 255;
                        $green = ($rgb >> 8) & 255;
                        $blue = $rgb & 255;
                        $brightness += ($red + $green + $blue) / 3;
                        $saturation += max($red, $green, $blue) - min($red, $green, $blue);
                        $samples++;
                    }
                }

                imagedestroy($image);

                if ($samples > 0 && ($brightness / $samples) > 235 && ($saturation / $samples) < 8) {
                    return true;
                }
            }
        }

        return $allow_size_heuristic && strlen($jpeg) < 6000 && $width <= 260 && $height <= 260;
    }

    private function rgb_bytes_to_jpeg(string $raw, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $offset = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $red = ord($raw[$offset] ?? "\0");
                $green = ord($raw[$offset + 1] ?? "\0");
                $blue = ord($raw[$offset + 2] ?? "\0");
                imagesetpixel($image, $x, $y, ($red << 16) | ($green << 8) | $blue);
                $offset += 3;
            }
        }

        ob_start();
        imagejpeg($image, null, 86);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        return $jpeg;
    }

    private function name_match_key(string $name): string
    {
        $name = remove_accents($name);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name);
        return trim((string) preg_replace('/\s+/', ' ', (string) $name));
    }

    private function exact_photo_name_key(string $name): string
    {
        return $this->normalize_exact_photo_name($name);
    }

    private function normalize_exact_photo_name(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');

        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_C);
            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        $name = (string) preg_replace('/[\x{00A0}\x{202F}\t\r\n]+/u', ' ', $name);
        $name = (string) preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    private function student_photo_import_display_name(object $student): string
    {
        return $this->normalize_exact_photo_name((string) $student->first_name . ' ' . (string) $student->last_name);
    }

    private function student_photo_import_label(object $student): string
    {
        $label = $this->student_photo_import_display_name($student);
        $class_name = trim((string) ($student->class_name ?? ''));

        if ($class_name !== '') {
            $label .= ' - ' . $class_name;
        }

        return $label;
    }

    private function store_signature_image_from_request(): array
    {
        if (empty($_FILES['message_signature_image']['tmp_name']) || !is_uploaded_file($_FILES['message_signature_image']['tmp_name'])) {
            return array();
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload(
            $_FILES['message_signature_image'],
            array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ),
            )
        );

        if (!is_array($upload) || empty($upload['url']) || empty($upload['file'])) {
            return array();
        }

        return array(
            'url' => esc_url_raw((string) $upload['url']),
            'path' => (string) $upload['file'],
        );
    }

    private function email_body_with_signature(string $body): string
    {
        $settings = GCA_Database::get_settings();
        $body_html = $this->email_content_html($body);
        $signature = trim((string) ($settings['message_signature_text'] ?? ''));
        $signature_html = '';

        if ($signature !== '') {
            $signature_html .= '<div class="gca-mail-signature-text">' . $this->email_content_html($signature) . '</div>';
        }

        if (!empty($settings['message_signature_image_url'])) {
            $signature_html .= '<div class="gca-mail-signature-image" style="margin-top:10px;"><img src="' . esc_url((string) $settings['message_signature_image_url']) . '" alt="Signature" style="max-width:240px;height:auto;"></div>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.4;color:#1f2933;">'
            . $body_html
            . ($signature_html !== '' ? '<div style="margin-top:18px;">' . $signature_html . '</div>' : '')
            . '</div>';
    }

    private function email_content_html(string $content): string
    {
        $content = $this->normalize_email_text($content);
        if ($content === '') {
            return '';
        }

        if (trim(wp_strip_all_tags($content)) !== trim($content)) {
            return GCA_Database::sanitize_message_body($content);
        }

        $paragraphs = preg_split('/\n{2,}/', $content) ?: array();
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }

            $lines = array_map('esc_html', explode("\n", $paragraph));
            $html .= '<p style="margin:0 0 12px 0;">' . implode('<br>', $lines) . '</p>';
        }

        return $html;
    }

    private function normalize_email_text(string $content): string
    {
        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        $content = preg_replace("/[ \t]+\n/", "\n", $content) ?: $content;
        $content = preg_replace("/\n{3,}/", "\n\n", $content) ?: $content;

        return trim($content);
    }

    private function normalize_gender($value): string
    {
        $normalized = strtolower(trim(remove_accents((string) $value)));

        if (in_array($normalized, array('male', 'm', 'masculin', 'homme', 'garcon'), true)) {
            return 'male';
        }

        if (in_array($normalized, array('female', 'f', 'feminin', 'femme', 'fille'), true)) {
            return 'female';
        }

        return '';
    }

    private function infer_gender_from_first_name(string $first_name): string
    {
        $parts = preg_split('/\s+/', trim($first_name));
        $first = strtolower(trim(remove_accents((string) ($parts[0] ?? ''))));
        $female = array('adriana', 'amel', 'ebrar', 'ema', 'emilia', 'elodie', 'gilda', 'jasmin', 'mila', 'narges', 'svetlana');
        $male = array('adam', 'adrian', 'adriano', 'ahmad', 'albion', 'benny', 'brayan', 'burak', 'carlos', 'cristiano', 'damien', 'david', 'devrim', 'diogo', 'dorio', 'drilon', 'edi', 'eliott', 'elvis', 'evan', 'fabio', 'francisco', 'gabin', 'jonathan', 'kelvy', 'kingston', 'kris', 'lenny', 'louca', 'loukas', 'lucas', 'mateo', 'max', 'nathan', 'nicolas', 'noah', 'nolan', 'ossama', 'pedro', 'sacha', 'santiago', 'sofiane', 'sulayman', 'terry', 'theo', 'tharmisan', 'tiago');

        if (in_array($first, $female, true)) {
            return 'female';
        }

        if (in_array($first, $male, true)) {
            return 'male';
        }

        if ($first !== '' && str_ends_with($first, 'a')) {
            return 'female';
        }

        return '';
    }

    private function gender_value(object $student): string
    {
        $gender = $this->normalize_gender((string) ($student->gender ?? ''));
        return $gender !== '' ? $gender : $this->infer_gender_from_first_name((string) ($student->first_name ?? ''));
    }

    private function gender_label(string $gender): string
    {
        return match ($this->normalize_gender($gender)) {
            'male' => 'Homme',
            'female' => 'Femme',
            default => 'À vérifier',
        };
    }

    private function gender_article(object $student): string
    {
        return $this->gender_value($student) === 'female' ? "l’apprentie" : "l’apprenti";
    }

    private function gender_article_cap(object $student): string
    {
        return $this->gender_value($student) === 'female' ? "L’apprentie" : "L’apprenti";
    }

    private function gender_pronoun(object $student): string
    {
        return $this->gender_value($student) === 'female' ? 'elle' : 'il';
    }

    private function gender_participle(object $student, string $male_form): string
    {
        return $this->gender_value($student) === 'female' ? $male_form . 'e' : $male_form;
    }

    private function format_decimal($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function absence_justified_count(object $student): float
    {
        $raw_count = max(0.0, (float) ($student->absence_count ?? 0));
        return min($raw_count, max(0.0, (float) ($student->absence_justified_count ?? 0)));
    }

    private function actionable_absence_count(object $student): float
    {
        return max(0.0, (float) ($student->absence_count ?? 0) - $this->absence_justified_count($student));
    }

    private function actionable_absence_rate(object $student): float
    {
        $raw_count = max(0.0, (float) ($student->absence_count ?? 0));
        $raw_rate = max(0.0, (float) ($student->absence_rate ?? 0));
        if ($raw_count <= 0 || $raw_rate <= 0) {
            return 0.0;
        }

        return max(0.0, $raw_rate * ($this->actionable_absence_count($student) / $raw_count));
    }

    private function yes_no_label($value): string
    {
        return !empty($value) ? 'Oui' : 'Non';
    }

    private function csv_display_datetime(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        return $this->display_date($value);
    }

    private function csv_value($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $this->yes_no_label($value);
        } elseif (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }

        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = str_replace("\0", '', $value);
        $value = trim($value);

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }

    private function send_csv_download(string $filename, array $headers, array $rows): void
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'wb');
        fputcsv($out, array_map(array($this, 'csv_value'), $headers), ';');

        foreach ($rows as $row) {
            fputcsv($out, array_map(array($this, 'csv_value'), $row), ';');
        }

        fclose($out);
        exit;
    }

    private function manual_fields_export_label($manual_fields_json): string
    {
        $fields = GCA_Database::manual_fields_from_json($manual_fields_json);
        if (!$fields) {
            return '';
        }

        $labels = array(
            'class_id' => 'Classe',
            'last_name' => 'Nom',
            'first_name' => 'Prénom',
            'date_of_birth' => 'Date de naissance',
            'gender' => 'Genre',
            'photo_path' => 'Photo',
            'phone' => 'Téléphone élève',
            'email' => 'E-mail élève',
            'trainer_id' => 'Formateur responsable',
            'trainer_name' => 'Nom formateur',
            'trainer_company' => 'Entreprise formatrice',
            'trainer_company_address' => 'Adresse entreprise',
            'trainer_company_zip' => 'NPA entreprise',
            'trainer_company_city' => 'Localité entreprise',
            'trainer_email' => 'E-mail formateur',
            'trainer_phone' => 'Téléphone formateur',
            'legal_rep1_name' => 'Représentant légal 1 - nom',
            'legal_rep1_email' => 'Représentant légal 1 - e-mail',
            'legal_rep1_phone' => 'Représentant légal 1 - téléphone',
            'legal_rep2_name' => 'Représentant légal 2 - nom',
            'legal_rep2_email' => 'Représentant légal 2 - e-mail',
            'legal_rep2_phone' => 'Représentant légal 2 - téléphone',
            'absence_count' => 'Périodes d’absence',
            'absence_rate' => 'Taux d’absence',
            'absence_justified_count' => 'Périodes justifiées',
            'lateness_count' => 'Retards',
            'removal_count' => 'Mises à la porte',
            'comment_count' => 'Commentaires',
            'contract_status' => 'Statut contrat',
            'contract_break_date' => 'Date de rupture',
            'salutation' => 'Formule d’appel',
            'company' => 'Entreprise',
            'company_address' => 'Adresse',
            'company_zip' => 'NPA',
            'company_city' => 'Localité',
            'name' => 'Nom',
        );

        $export = array();
        foreach ($fields as $field) {
            $export[] = $labels[$field] ?? $field;
        }

        return implode(' | ', $export);
    }

    private function student_exclusions_export_label(int $student_id): string
    {
        global $wpdb;

        $table = GCA_Database::table('disciplinary_exclusions');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_date, end_date, notes
                 FROM {$table}
                 WHERE student_id = %d
                 ORDER BY start_date ASC, id ASC",
                $student_id
            )
        );

        $items = array();
        foreach ($rows as $row) {
            $start = $this->display_date_only((string) $row->start_date);
            $end = $this->display_date_only((string) $row->end_date);
            $period = trim(($start !== '' ? 'du ' . $start : '') . ($end !== '' ? ' au ' . $end : ''));
            $notes = trim((string) $row->notes);
            $items[] = trim($period . ($notes !== '' ? ' - ' . $notes : ''));
        }

        return implode(' | ', array_filter($items));
    }

    private function student_grades_export_label(int $student_id): string
    {
        $rows = $this->student_grades_for_student($student_id);

        $items = array();
        foreach ($rows as $row) {
            $assessment = trim((string) $row->assessment);
            $grade = trim((string) $row->grade_value);
            $items[] = trim(($assessment !== '' ? $assessment . ' : ' : '') . $grade);
        }

        return implode(' | ', $items);
    }

    private function student_pending_tasks_export_label(int $student_id): string
    {
        global $wpdb;

        $table = GCA_Database::table('tasks');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT task_type, threshold_value, action_label, message, created_at
                 FROM {$table}
                 WHERE student_id = %d AND status = 'pending'
                 ORDER BY created_at ASC, id ASC",
                $student_id
            )
        );

        $items = array();
        foreach ($rows as $row) {
            $parts = array($this->task_type_label((string) $row->task_type));
            if (trim((string) $row->threshold_value) !== '') {
                $parts[] = 'seuil ' . trim((string) $row->threshold_value);
            }
            if (trim((string) $row->action_label) !== '') {
                $parts[] = trim((string) $row->action_label);
            }
            if (trim((string) $row->message) !== '') {
                $parts[] = trim((string) $row->message);
            }
            $parts[] = 'créée le ' . $this->csv_display_datetime((string) $row->created_at);
            $items[] = implode(' - ', array_filter($parts));
        }

        return implode(' | ', $items);
    }

    private function student_events_export_label(int $student_id): string
    {
        global $wpdb;

        $table = GCA_Database::table('events');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, status, created_at
                 FROM {$table}
                 WHERE student_id = %d
                 ORDER BY created_at ASC, id ASC",
                $student_id
            )
        );

        $items = array();
        foreach ($rows as $row) {
            $status = trim((string) $row->status);
            $items[] = trim($this->csv_display_datetime((string) $row->created_at) . ' - ' . $this->event_type_label((string) $row->event_type) . ($status !== '' ? ' - ' . $status : ''));
        }

        return implode(' | ', $items);
    }

    private function student_communications_export_label(int $student_id): string
    {
        global $wpdb;

        $table = GCA_Database::table('communications');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT communication_type, subject, recipients_to, recipients_cc, recipients_bcc, sent_at, status
                 FROM {$table}
                 WHERE student_id = %d
                 ORDER BY sent_at ASC, id ASC",
                $student_id
            )
        );

        $items = array();
        foreach ($rows as $row) {
            $recipients = $this->communication_recipients_summary($row);
            $items[] = trim(implode(' - ', array_filter(array(
                $this->csv_display_datetime((string) $row->sent_at),
                $this->communication_type_label((string) $row->communication_type),
                (string) $row->subject,
                $recipients,
                (string) $row->status,
            ))));
        }

        return implode(' | ', $items);
    }

    private function trainer_students_export_label(int $trainer_id): array
    {
        global $wpdb;

        $students = GCA_Database::table('students');
        $classes = GCA_Database::table('classes');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.first_name, s.last_name, c.name AS class_name
                 FROM {$students} s
                 LEFT JOIN {$classes} c ON c.id = s.class_id
                 WHERE s.trainer_id = %d AND s.archived_at IS NULL
                 ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC",
                $trainer_id
            )
        );

        $items = array();
        foreach ($rows as $row) {
            $items[] = trim($this->student_display_name($row, false) . ' (' . (string) $row->class_name . ')');
        }

        return array(
            'count' => count($items),
            'label' => implode(' | ', $items),
        );
    }

    private function absence_periods_label($value): string
    {
        $formatted = $this->format_decimal($value);

        return $formatted . ((float) $value === 1.0 ? ' période' : ' périodes');
    }

    private function display_date_only($value): string
    {
        $stored = $this->normalize_date_for_storage($value);

        if ($stored === '') {
            return '';
        }

        $date = DateTime::createFromFormat('!Y-m-d', $stored, wp_timezone());

        return $date instanceof DateTime ? $date->format('d.m.Y') : '';
    }

    private function normalize_date_for_storage($value): string
    {
        $value = trim(sanitize_text_field((string) $value));

        if ($value === '') {
            return '';
        }

        $formats = array('d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d');

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTime && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp ? wp_date('Y-m-d', $timestamp) : '';
    }

    private function display_date(?string $mysql): string
    {
        if (!$mysql) {
            return '-';
        }

        $date = $this->local_datetime($mysql);
        return $date ? $date->format('d.m.Y H:i') : $mysql;
    }

    private function local_datetime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d') as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value, wp_timezone());
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date;
            }
        }

        $timestamp = strtotime($value);
        return $timestamp ? (new DateTimeImmutable('@' . $timestamp))->setTimezone(wp_timezone()) : null;
    }

    private function local_datetime_timestamp(string $value): int
    {
        $date = $this->local_datetime($value);
        return $date ? $date->getTimestamp() : 0;
    }

    private function page_url(array $args = array()): string
    {
        if ($this->is_admin_app_request()) {
            $page = isset($args['gca_tab'])
                ? $this->admin_page_for_tab((string) $args['gca_tab'])
                : sanitize_key(wp_unslash($_GET['page'] ?? 'gca_app'));
            unset($args['gca_tab']);
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

        $clean = remove_query_arg(array('gca_notice', 'gca_import_token', 'gca_action', 'task_id', 'student_id', 'class_id', 'trainer_id', 'source', 'action_id', 'gca_search', 'task_type', 'gender_filter'), $base);

        return add_query_arg($args, $clean);
    }

    private function tab_from_admin_page(): string
    {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? 'gca_app'));
        $map = array(
            'gca_classes' => 'classes',
            'gca_students' => 'students',
            'gca_trainers' => 'trainers',
            'gca_actions' => 'actions',
            'gca_reports' => 'reports',
            'gca_templates' => 'templates',
            'gca_imports' => 'imports',
            'gca_settings' => 'settings',
        );

        return $map[$page] ?? 'dashboard';
    }

    private function admin_page_for_tab(string $tab): string
    {
        $map = array(
            'classes' => 'gca_classes',
            'students' => 'gca_students',
            'trainers' => 'gca_trainers',
            'actions' => 'gca_actions',
            'reports' => 'gca_reports',
            'templates' => 'gca_templates',
            'imports' => 'gca_imports',
            'settings' => 'gca_settings',
        );

        return $map[$tab] ?? 'gca_app';
    }

    private function is_admin_app_request(): bool
    {
        if (!is_admin() || !isset($_GET['page'])) {
            return false;
        }

        return in_array(
            sanitize_key(wp_unslash($_GET['page'])),
            array('gca_app', 'gca_classes', 'gca_students', 'gca_trainers', 'gca_actions', 'gca_reports', 'gca_templates', 'gca_imports', 'gca_settings'),
            true
        );
    }

    private function admin_referer_url(): string
    {
        $referer = wp_get_referer();

        if ($referer && preg_match('/page=gca_(app|classes|students|trainers|actions|reports|templates|imports|settings)/', $referer)) {
            return $referer;
        }

        if (is_admin()) {
            return admin_url('admin.php?page=gca_app');
        }

        $permalink = get_permalink(get_queried_object_id());

        return $permalink ?: home_url('/');
    }

    private function redirect_notice(string $tab, string $notice): void
    {
        wp_safe_redirect($this->page_url(array('gca_tab' => $tab, 'gca_notice' => $notice)));
        exit;
    }

    private function hidden_page_fields(array $fields): void
    {
        if ($this->is_admin_app_request() && !isset($fields['page'])) {
            $fields = array('page' => sanitize_key(wp_unslash($_GET['page'] ?? 'gca_app'))) + $fields;
        }

        foreach ($fields as $key => $value) {
            printf('<input type="hidden" name="%s" value="%s">', esc_attr($key), esc_attr((string) $value));
        }
    }

    /** LogiOne : accès par la matrice centrale. Ce droit n'ouvre aucun autre module (CLA-01, ACL-08). */
    private function can_manage(): bool
    {
        return current_user_can('lp_classe_view');
    }

    /** Une permission d'import, d'envoi ou de suppression ne se déduit jamais du droit de lecture (ACL-08). */
    private function require_manage(): void
    {
        $action = sanitize_key((string) wp_unslash($_REQUEST['action'] ?? ''));
        $prefix = 'gca_';
        $action = strpos($action, $prefix) === 0 ? substr($action, strlen($prefix)) : $action;
        $map = array(
            'import_prepare' => 'lp_classe_import', 'import_commit' => 'lp_classe_import', 'import_photos_pdf' => 'lp_classe_import',
            'import_photos_commit' => 'lp_classe_import', 'photo_import_preview' => 'lp_classe_import',
            'save_settings' => 'lp_classe_settings',
            'save_free_message_template' => 'lp_classe_templates',
            'send_student_report' => 'lp_classe_send', 'send_trainer_bulk_message' => 'lp_classe_send', 'send_email' => 'lp_classe_send',
            'generate_student_report' => 'lp_classe_export', 'generate_class_reports' => 'lp_classe_export', 'download_pdf' => 'lp_classe_export',
            'export_student' => 'lp_classe_export', 'export_students' => 'lp_classe_export', 'export_trainers' => 'lp_classe_export',
            'anonymize_student' => 'lp_classe_delete', 'delete_student' => 'lp_classe_delete', 'delete_class' => 'lp_classe_delete',
        );
        $perm = $map[$action] ?? 'lp_classe_edit';
        if (!current_user_can('lp_classe_view') || !current_user_can($perm)) {
            wp_die('Accès refusé.', 403);
        }
    }
}
