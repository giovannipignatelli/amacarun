<?php
/**
 * Interface Amministrazione AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per l'interface di amministrazione
 */
class AmacarUN_Admin {
    
    /**
     * Manager instances
     */
    private $race_manager;
    private $participant_manager;
    private $bib_manager;
    private $woocommerce_sync;
    private $mailpoet_manager;
    private $export_manager;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->race_manager = AmacarUN_Race_Manager::get_instance();
        $this->participant_manager = new AmacarUN_Participant_Manager();
        $this->bib_manager = new AmacarUN_Bib_Manager();
        $this->woocommerce_sync = new AmacarUN_WooCommerce_Sync();
        $this->mailpoet_manager = new AmacarUN_MailPoet_Manager();
        $this->export_manager = new AmacarUN_Export_Manager();
        
        $this->init_hooks();
    }
    
    /**
     * Inizializza hook admin
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        
        // AJAX handlers
        $this->register_ajax_handlers();
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Handle file downloads
        add_action('admin_init', array($this->export_manager, 'handle_file_download'));
    }
    
    /**
     * Registra handler AJAX
     */
    private function register_ajax_handlers() {
        // Gestione partecipanti
        add_action('wp_ajax_amacarun_update_bib', array($this, 'ajax_update_bib'));
        add_action('wp_ajax_amacarun_assign_next_bib', array($this, 'ajax_assign_next_bib'));
        add_action('wp_ajax_amacarun_bulk_assign_bibs', array($this, 'ajax_bulk_assign_bibs'));
        add_action('wp_ajax_amacarun_remove_bib', array($this, 'ajax_remove_bib'));
        
        // Check-in
        add_action('wp_ajax_amacarun_checkin_participant', array($this, 'ajax_checkin_participant'));
        add_action('wp_ajax_amacarun_retire_participant', array($this, 'ajax_retire_participant'));
        add_action('wp_ajax_amacarun_add_onsite_participant', array($this, 'ajax_add_onsite_participant'));
        
        // Sincronizzazione
        add_action('wp_ajax_amacarun_sync_woocommerce', array($this, 'ajax_sync_woocommerce'));
        add_action('wp_ajax_amacarun_get_sync_progress', array($this, 'ajax_get_sync_progress'));
        
        // MailPoet
        add_action('wp_ajax_amacarun_mailpoet_subscribe', array($this, 'ajax_mailpoet_subscribe'));
        add_action('wp_ajax_amacarun_mailpoet_unsubscribe', array($this, 'ajax_mailpoet_unsubscribe'));
        add_action('wp_ajax_amacarun_mailpoet_bulk_subscribe', array($this, 'ajax_mailpoet_bulk_subscribe'));
        add_action('wp_ajax_amacarun_mailpoet_sync_status', array($this, 'ajax_mailpoet_sync_status'));
        add_action('wp_ajax_amacarun_mailpoet_create_segment', array($this, 'ajax_mailpoet_create_segment'));
        
        // Eventi
        add_action('wp_ajax_amacarun_create_event', array($this, 'ajax_create_event'));
        add_action('wp_ajax_amacarun_activate_event', array($this, 'ajax_activate_event'));
        add_action('wp_ajax_amacarun_update_event', array($this, 'ajax_update_event'));
        
        // Utility
        add_action('wp_ajax_amacarun_test_connections', array($this, 'ajax_test_connections'));
        add_action('wp_ajax_amacarun_get_stats', array($this, 'ajax_get_stats'));
    }
    
    /**
     * Aggiunge menu amministrazione
     */
    public function add_admin_menus() {
        // Menu principale
        $main_page = add_menu_page(
            'AmacarUN Race Manager',
            'AmacarUN',
            'manage_amacarun_events',
            'amacarun-race-manager',
            array($this, 'display_main_page'),
            'dashicons-awards',
            30
        );
        
        // Sottomenu
        add_submenu_page(
            'amacarun-race-manager',
            'Dashboard',
            'Dashboard',
            'manage_amacarun_events',
            'amacarun-race-manager',
            array($this, 'display_main_page')
        );
        
        add_submenu_page(
            'amacarun-race-manager',
            'Eventi',
            'Eventi',
            'manage_amacarun_events',
            'amacarun-events',
            array($this, 'display_events_page')
        );
        
        add_submenu_page(
            'amacarun-race-manager',
            'Partecipanti',
            'Partecipanti',
            'manage_amacarun_participants',
            'amacarun-participants',
            array($this, 'display_participants_page')
        );
        
        add_submenu_page(
            'amacarun-race-manager',
            'Check-in',
            'Check-in',
            'amacarun_checkin',
            'amacarun-checkin',
            array($this, 'display_checkin_page')
        );
        
        add_submenu_page(
            'amacarun-race-manager',
            'Export',
            'Export',
            'export_amacarun_data',
            'amacarun-export',
            array($this, 'display_export_page')
        );
        
        add_submenu_page(
            'amacarun-race-manager',
            'Impostazioni',
            'Impostazioni',
            'manage_amacarun_events',
            'amacarun-settings',
            array($this, 'display_settings_page')
        );
        
        // Hook per caricamento asset pagina-specifici
        add_action('load-' . $main_page, array($this, 'load_main_page_assets'));
    }
    
    /**
     * Carica script e stili admin
     */
    public function enqueue_admin_scripts($hook) {
        // Solo sulle nostre pagine
        if (strpos($hook, 'amacarun') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'amacarun-admin-style',
            AMACARUN_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            AMACARUN_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'amacarun-admin-script',
            AMACARUN_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery', 'wp-util'),
            AMACARUN_VERSION,
            true
        );
        
        // Localizzazione script
        wp_localize_script('amacarun-admin-script', 'amacarun_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amacarun_nonce'),
            'strings' => array(
                'confirm_delete' => 'Sei sicuro di voler eliminare questo elemento?',
                'confirm_bulk_action' => 'Sei sicuro di voler eseguire questa azione su tutti gli elementi selezionati?',
                'sync_in_progress' => 'Sincronizzazione in corso...',
                'export_in_progress' => 'Export in corso...',
                'success' => 'Operazione completata con successo',
                'error' => 'Si è verificato un errore',
                'loading' => 'Caricamento in corso...',
                'no_participants_selected' => 'Nessun partecipante selezionato'
            )
        ));
        
        // Chart.js per statistiche
        if (strpos($hook, 'amacarun-race-manager') !== false) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                '3.9.1',
                true
            );
        }
        
        // DataTables per tabelle
        if (strpos($hook, 'amacarun-participants') !== false || strpos($hook, 'amacarun-checkin') !== false) {
            wp_enqueue_script(
                'datatables',
                'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
                array('jquery'),
                '1.13.6',
                true
            );
            
            wp_enqueue_style(
                'datatables',
                'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
                array(),
                '1.13.6'
            );
        }
    }
    
    /**
     * Carica asset specifici pagina principale
     */
    public function load_main_page_assets() {
        // Hook per future personalizzazioni
        do_action('amacarun_load_main_page_assets');
    }
    
    /**
     * Gestisce azioni admin
     */
    public function handle_admin_actions() {
        if (!isset($_POST['amacarun_action']) || !wp_verify_nonce($_POST['_wpnonce'], 'amacarun_admin_action')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['amacarun_action']);
        
        switch ($action) {
            case 'create_event':
                $this->handle_create_event();
                break;
                
            case 'update_event':
                $this->handle_update_event();
                break;
                
            case 'bulk_participant_action':
                $this->handle_bulk_participant_action();
                break;
                
            case 'save_settings':
                $this->handle_save_settings();
                break;
        }
    }
    
    /**
     * Mostra notice admin
     */
    public function show_admin_notices() {
        // Welcome message dopo attivazione
        if (isset($_GET['welcome']) && $_GET['welcome'] == '1') {
            $this->show_welcome_notice();
        }
        
        // Controllo configurazione
        if (!$this->race_manager->is_configured()) {
            $this->show_configuration_notice();
        }
        
        // Notice generiche
        $this->show_general_notices();
    }
    
    /**
     * Notice di benvenuto
     */
    private function show_welcome_notice() {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<h3>' . __('Benvenuto in AmacarUN Race Manager!', 'amacarun-race-manager') . '</h3>';
        echo '<p>' . __('Il plugin è stato attivato con successo. Inizia configurando il tuo primo evento.', 'amacarun-race-manager') . '</p>';
        echo '<p><a href="' . admin_url('admin.php?page=amacarun-events') . '" class="button button-primary">' . __('Crea il tuo primo evento', 'amacarun-race-manager') . '</a></p>';
        echo '</div>';
    }
    
    /**
     * Notice configurazione mancante
     */
    private function show_configuration_notice() {
        echo '<div class="notice notice-warning">';
        echo '<h3>' . __('Configurazione Incompleta', 'amacarun-race-manager') . '</h3>';
        echo '<p>' . __('Per utilizzare il plugin è necessario configurare almeno un evento attivo.', 'amacarun-race-manager') . '</p>';
        echo '<p><a href="' . admin_url('admin.php?page=amacarun-settings') . '" class="button button-secondary">' . __('Vai alle Impostazioni', 'amacarun-race-manager') . '</a></p>';
        echo '</div>';
    }
    
    /**
     * Notice generiche
     */
    private function show_general_notices() {
        // Controllo integrità database
        if (isset($_GET['page']) && $_GET['page'] === 'amacarun-race-manager') {
            $integrity = AmacarUN_Database::check_integrity();
            if ($integrity !== true) {
                echo '<div class="notice notice-error">';
                echo '<h3>' . __('Problemi Database Rilevati', 'amacarun-race-manager') . '</h3>';
                echo '<ul>';
                foreach ($integrity as $issue) {
                    echo '<li>' . esc_html($issue) . '</li>';
                }
                echo '</ul>';
                echo '<p><a href="' . wp_nonce_url(admin_url('admin.php?page=amacarun-settings&action=repair_db'), 'repair_db') . '" class="button button-secondary">' . __('Ripara Database', 'amacarun-race-manager') . '</a></p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Pagina principale (Dashboard)
     */
    public function display_main_page() {
        $active_event = AmacarUN_Race_Manager::get_active_event();
        $stats = $this->race_manager->get_stats();
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/admin-display.php';
    }
    
    /**
     * Pagina eventi
     */
    public function display_events_page() {
        $events = AmacarUN_Race_Manager::get_all_events();
        $active_event = AmacarUN_Race_Manager::get_active_event();
        
        // Gestione azioni
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'edit':
                    $this->display_edit_event_form(intval($_GET['event_id']));
                    return;
                    
                case 'new':
                    $this->display_new_event_form();
                    return;
            }
        }
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/events-list.php';
    }
    
    /**
     * Pagina partecipanti
     */
    public function display_participants_page() {
        $active_event = AmacarUN_Race_Manager::get_active_event();
        
        if (!$active_event) {
            echo '<div class="wrap"><h1>Partecipanti</h1>';
            echo '<div class="notice notice-warning"><p>Nessun evento attivo. <a href="' . admin_url('admin.php?page=amacarun-events') . '">Attiva un evento</a> per visualizzare i partecipanti.</p></div>';
            echo '</div>';
            return;
        }
        
        $participants = $this->participant_manager->get_participants_by_event($active_event->id);
        $stats = $this->participant_manager->get_event_stats($active_event->id);
        $bib_stats = $this->bib_manager->get_bib_stats($active_event->id);
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/participants-list.php';
    }
    
    /**
     * Pagina check-in
     */
    public function display_checkin_page() {
        $active_event = AmacarUN_Race_Manager::get_active_event();
        
        if (!$active_event) {
            echo '<div class="wrap"><h1>Check-in</h1>';
            echo '<div class="notice notice-warning"><p>Nessun evento attivo. <a href="' . admin_url('admin.php?page=amacarun-events') . '">Attiva un evento</a> per procedere con il check-in.</p></div>';
            echo '</div>';
            return;
        }
        
        $checkin_stats = array(
            'total' => $this->participant_manager->get_event_stats($active_event->id)['total'],
            'checked_in' => $this->participant_manager->get_event_stats($active_event->id)['checked_in'],
            'remaining' => $this->participant_manager->get_event_stats($active_event->id)['registered']
        );
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/checkin-interface.php';
    }
    
    /**
     * Pagina export
     */
    public function display_export_page() {
        $active_event = AmacarUN_Race_Manager::get_active_event();
        $events = AmacarUN_Race_Manager::get_all_events();
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/export-interface.php';
    }
    
    /**
     * Pagina impostazioni
     */
    public function display_settings_page() {
        $settings = $this->get_plugin_settings();
        $wc_test = $this->woocommerce_sync->test_woocommerce_connection();
        $mp_test = $this->mailpoet_manager->test_mailpoet_connection();
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/settings-page.php';
    }
    
    /**
     * Form nuovo evento
     */
    private function display_new_event_form() {
        $event = (object) array(
            'id' => 0,
            'name' => '',
            'date' => date('Y-m-d', strtotime('+1 month')),
            'status' => 'draft',
            'woocommerce_category_id' => get_option('amacarun_woocommerce_category_id', 29),
            'adult_product_id' => '',
            'child_product_id' => '',
            'bib_number_start' => 1001,
            'mailpoet_list_id' => '',
            'mailpoet_auto_subscribe' => 1,
            'mailpoet_double_optin' => 0
        );
        
        $mailpoet_lists = $this->mailpoet_manager->get_mailpoet_lists();
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/event-form.php';
    }
    
    /**
     * Form modifica evento
     */
    private function display_edit_event_form($event_id) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . AMACARUN_EVENTS_TABLE . " WHERE id = %d",
            $event_id
        ));
        
        if (!$event) {
            wp_die('Evento non trovato');
        }
        
        $mailpoet_lists = $this->mailpoet_manager->get_mailpoet_lists();
        
        include AMACARUN_PLUGIN_PATH . 'admin/partials/event-form.php';
    }
    
    /**
     * Gestisce creazione evento
     */
    private function handle_create_event() {
        $event_data = array(
            'name' => sanitize_text_field($_POST['event_name']),
            'date' => sanitize_text_field($_POST['event_date']),
            'woocommerce_category_id' => intval($_POST['woocommerce_category_id']),
            'adult_product_id' => intval($_POST['adult_product_id']),
            'child_product_id' => intval($_POST['child_product_id']),
            'bib_number_start' => intval($_POST['bib_number_start']),
            'mailpoet_list_id' => intval($_POST['mailpoet_list_id']),
            'mailpoet_auto_subscribe' => !empty($_POST['mailpoet_auto_subscribe']) ? 1 : 0,
            'mailpoet_double_optin' => !empty($_POST['mailpoet_double_optin']) ? 1 : 0
        );
        
        $event_id = $this->race_manager->create_event($event_data);
        
        if ($event_id) {
            wp_redirect(admin_url('admin.php?page=amacarun-events&message=event_created'));
        } else {
            wp_redirect(admin_url('admin.php?page=amacarun-events&message=error'));
        }
        exit;
    }
    
    /**
     * Gestisce aggiornamento evento
     */
    private function handle_update_event() {
        $event_id = intval($_POST['event_id']);
        
        $event_data = array(
            'name' => sanitize_text_field($_POST['event_name']),
            'date' => sanitize_text_field($_POST['event_date']),
            'woocommerce_category_id' => intval($_POST['woocommerce_category_id']),
            'adult_product_id' => intval($_POST['adult_product_id']),
            'child_product_id' => intval($_POST['child_product_id']),
            'bib_number_start' => intval($_POST['bib_number_start']),
            'mailpoet_list_id' => intval($_POST['mailpoet_list_id']),
            'mailpoet_auto_subscribe' => !empty($_POST['mailpoet_auto_subscribe']) ? 1 : 0,
            'mailpoet_double_optin' => !empty($_POST['mailpoet_double_optin']) ? 1 : 0
        );
        
        global $wpdb;
        $result = $wpdb->update(
            AMACARUN_EVENTS_TABLE,
            $event_data,
            array('id' => $event_id)
        );
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=amacarun-events&message=event_updated'));
        } else {
            wp_redirect(admin_url('admin.php?page=amacarun-events&message=error'));
        }
        exit;
    }
    
    /**
     * Gestisce azioni bulk sui partecipanti
     */
    private function handle_bulk_participant_action() {
        $action = sanitize_text_field($_POST['bulk_action']);
        $participant_ids = array_map('intval', $_POST['participants'] ?? array());
        
        if (empty($participant_ids)) {
            wp_redirect(admin_url('admin.php?page=amacarun-participants&message=no_selection'));
            exit;
        }
        
        switch ($action) {
            case 'assign_bibs':
                $this->bulk_assign_bibs($participant_ids);
                break;
                
            case 'remove_bibs':
                $this->bulk_remove_bibs($participant_ids);
                break;
                
            case 'mailpoet_subscribe':
                $this->bulk_mailpoet_subscribe($participant_ids);
                break;
                
            case 'export_selected':
                $this->export_selected_participants($participant_ids);
                break;
        }
    }
    
    /**
     * Ottiene impostazioni plugin
     */
    private function get_plugin_settings() {
        return array(
            'auto_sync_woocommerce' => get_option('amacarun_auto_sync_woocommerce', 1),
            'auto_assign_bibs' => get_option('amacarun_auto_assign_bibs', 1),
            'bib_start_number' => get_option('amacarun_bib_start_number', 1001),
            'mailpoet_auto_subscribe' => get_option('amacarun_mailpoet_auto_subscribe', 1),
            'enable_public_list' => get_option('amacarun_enable_public_list', 1),
            'debug_logging' => get_option('amacarun_debug_logging', 0),
            'backup_retention_days' => get_option('amacarun_backup_retention_days', 30),
            'woocommerce_category_id' => get_option('amacarun_woocommerce_category_id', 29)
        );
    }
    
    /* ===== AJAX HANDLERS ===== */
    
    /**
     * AJAX: Aggiorna numero pettorale
     */
    public function ajax_update_bib() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        if (!current_user_can('manage_amacarun_participants')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $participant_id = intval($_POST['participant_id']);
        $bib_number = $_POST['bib_number'] ? intval($_POST['bib_number']) : null;
        
        // Ottieni event_id del partecipante
        global $wpdb;
        $event_id = $wpdb->get_var($wpdb->prepare(
            "SELECT event_id FROM " . AMACARUN_PARTICIPANTS_TABLE . " WHERE id = %d",
            $participant_id
        ));
        
        if (!$event_id) {
            wp_send_json_error(array('message' => 'Partecipante non trovato'));
        }
        
        if ($bib_number) {
            $result = $this->bib_manager->assign_manual_bib($participant_id, $bib_number, $event_id);
        } else {
            $result = $this->bib_manager->remove_bib($participant_id);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Assegna prossimo pettorale
     */
    public function ajax_assign_next_bib() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        if (!current_user_can('manage_amacarun_participants')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $participant_id = intval($_POST['participant_id']);
        
        global $wpdb;
        $event_id = $wpdb->get_var($wpdb->prepare(
            "SELECT event_id FROM " . AMACARUN_PARTICIPANTS_TABLE . " WHERE id = %d",
            $participant_id
        ));
        
        if (!$event_id) {
            wp_send_json_error(array('message' => 'Partecipante non trovato'));
        }
        
        $bib_number = $this->bib_manager->assign_next_sequential_bib($participant_id, $event_id);
        
        if (is_wp_error($bib_number)) {
            wp_send_json_error(array('message' => $bib_number->get_error_message()));
        }
        
        wp_send_json_success(array('bib_number' => $bib_number));
    }
    
    /**
     * AJAX: Assegna pettorali in massa
     */
    public function ajax_bulk_assign_bibs() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        if (!current_user_can('manage_amacarun_participants')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $event_id = intval($_POST['event_id']);
        
        $result = $this->bib_manager->bulk_assign_sequential_bibs($event_id);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Check-in partecipante
     */
    public function ajax_checkin_participant() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        if (!current_user_can('amacarun_checkin')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $participant_id = intval($_POST['participant_id']);
        $distance = sanitize_text_field($_POST['distance']);
        
        $result = $this->participant_manager->check_in_participant($participant_id, $distance);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Errore nel check-in'));
        }
    }
    
    /**
     * AJAX: Sincronizzazione WooCommerce
     */
    public function ajax_sync_woocommerce() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        if (!current_user_can('manage_amacarun_participants')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $event_id = intval($_POST['event_id']);
        
        // Avvia sincronizzazione con progress
        $result = $this->woocommerce_sync->manual_sync_with_progress($event_id);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Iscrizione MailPoet
     */
    public function ajax_mailpoet_subscribe() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        if (!current_user_can('manage_amacarun_participants')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $participant_id = intval($_POST['participant_id']);
        
        $result = $this->mailpoet_manager->subscribe_participant($participant_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Test connessioni
     */
    public function ajax_test_connections() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        if (!current_user_can('manage_amacarun_events')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $wc_test = $this->woocommerce_sync->test_woocommerce_connection();
        $mp_test = $this->mailpoet_manager->test_mailpoet_connection();
        
        wp_send_json_success(array(
            'woocommerce' => $wc_test,
            'mailpoet' => $mp_test
        ));
    }
    
    /**
     * AJAX: Ottieni statistiche
     */
    public function ajax_get_stats() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        $event_id = intval($_POST['event_id']);
        
        $stats = array(
            'participants' => $this->participant_manager->get_event_stats($event_id),
            'bib' => $this->bib_manager->get_bib_stats($event_id),
            'mailpoet' => $this->mailpoet_manager->get_mailpoet_stats($event_id)
        );
        
        wp_send_json_success($stats);
    }
}