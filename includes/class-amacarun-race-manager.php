<?php
/**
 * Classe principale AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe core del plugin - orchestrazione generale
 */
class AmacarUN_Race_Manager {
    
    /**
     * Istanza singleton
     */
    private static $instance = null;
    
    /**
     * Manager instances
     */
    private $participant_manager;
    private $bib_manager;
    private $woocommerce_sync;
    private $mailpoet_manager;
    private $export_manager;
    private $admin;
    private $public;
    
    /**
     * Plugin version
     */
    public $version;
    
    /**
     * Ottiene l'istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Costruttore
     */
    private function __construct() {
        $this->version = AMACARUN_VERSION;
        $this->init_hooks();
        $this->init_managers();
    }
    
    /**
     * Inizializza gli hook
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('admin_init', array($this, 'admin_init'));
        
        // Hook per aggiornamenti
        add_action('admin_init', array($this, 'check_version'));
        
        // Hook personalizzati del plugin
        add_action('amacarun_sync_woocommerce', array($this, 'run_woocommerce_sync'));
        add_action('amacarun_daily_backup', array($this, 'run_daily_backup'));
        add_action('amacarun_cleanup_temp_data', array($this, 'cleanup_temp_data'));
        
        // Shortcodes
        add_action('init', array($this, 'register_shortcodes'));
        
        // AJAX handlers
        add_action('wp_ajax_amacarun_search_participant', array($this, 'ajax_search_participant'));
        add_action('wp_ajax_nopriv_amacarun_search_participant', array($this, 'ajax_search_participant'));
        
        // Webhook WooCommerce
        add_action('init', array($this, 'handle_woocommerce_webhook'));
    }
    
    /**
     * Inizializza i manager
     */
    private function init_managers() {
        $this->participant_manager = new AmacarUN_Participant_Manager();
        $this->bib_manager = new AmacarUN_Bib_Manager();
        $this->woocommerce_sync = new AmacarUN_WooCommerce_Sync();
        $this->mailpoet_manager = new AmacarUN_MailPoet_Manager();
        $this->export_manager = new AmacarUN_Export_Manager();
        
        if (is_admin()) {
            $this->admin = new AmacarUN_Admin();
        }
        
        $this->public = new AmacarUN_Public();
    }
    
    /**
     * Inizializzazione generale
     */
    public function init() {
        // Carica traduzioni
        $this->load_textdomain();
        
        // Inizializza sessioni se necessario
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        // Hook dopo inizializzazione
        do_action('amacarun_loaded');
    }
    
    /**
     * Inizializzazione admin
     */
    public function admin_init() {
        // Redirect dopo attivazione
        if (get_transient('amacarun_activation_redirect')) {
            delete_transient('amacarun_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_redirect(admin_url('admin.php?page=amacarun-race-manager&welcome=1'));
                exit;
            }
        }
        
        // Mostra warnings di attivazione
        $warnings = get_transient('amacarun_activation_warnings');
        if ($warnings) {
            delete_transient('amacarun_activation_warnings');
            foreach ($warnings as $warning) {
                add_action('admin_notices', function() use ($warning) {
                    echo '<div class="notice notice-warning"><p>' . esc_html($warning) . '</p></div>';
                });
            }
        }
    }
    
    /**
     * Verifica versione e aggiornamenti
     */
    public function check_version() {
        $current_version = get_option('amacarun_version', '0.0.0');
        
        if (version_compare($current_version, AMACARUN_VERSION, '<')) {
            $this->upgrade($current_version, AMACARUN_VERSION);
            update_option('amacarun_version', AMACARUN_VERSION);
        }
    }
    
    /**
     * Gestisce aggiornamenti del plugin
     */
    private function upgrade($from_version, $to_version) {
        amacarun_log("Upgrading from version $from_version to $to_version");
        
        // Backup database prima dell'aggiornamento
        AmacarUN_Database::backup_tables();
        
        // Aggiorna database se necessario
        AmacarUN_Database::create_tables();
        
        // Aggiornamenti specifici per versione
        if (version_compare($from_version, '1.0.0', '<')) {
            $this->upgrade_to_1_0_0();
        }
        
        // Hook per altre estensioni
        do_action('amacarun_upgraded', $from_version, $to_version);
        
        amacarun_log("Upgrade completed successfully");
    }
    
    /**
     * Aggiornamenti specifici per versione 1.0.0
     */
    private function upgrade_to_1_0_0() {
        // Esempio di aggiornamenti specifici
        // Potrebbe includere migrazioni dati, nuove opzioni, etc.
    }
    
    /**
     * Carica traduzioni
     */
    public function load_textdomain() {
        $locale = determine_locale();
        $mofile = AMACARUN_PLUGIN_PATH . "languages/amacarun-race-manager-{$locale}.mo";
        
        if (file_exists($mofile)) {
            load_textdomain('amacarun-race-manager', $mofile);
        }
    }
    
    /**
     * Registra shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('amacarun_participants_list', array($this->public, 'participants_list_shortcode'));
        add_shortcode('amacarun_event_info', array($this->public, 'event_info_shortcode'));
        add_shortcode('amacarun_registration_stats', array($this->public, 'registration_stats_shortcode'));
    }
    
    /**
     * AJAX: Ricerca partecipanti
     */
    public function ajax_search_participant() {
        check_ajax_referer('amacarun_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query']);
        $event_id = intval($_POST['event_id']);
        
        if (empty($query) || $query < 2) {
            wp_send_json_error(array('message' => 'Query troppo corta'));
        }
        
        $participants = $this->participant_manager->search_participants($query, $event_id);
        
        wp_send_json_success(array('participants' => $participants));
    }
    
    /**
     * Gestisce webhook WooCommerce
     */
    public function handle_woocommerce_webhook() {
        if (isset($_GET['amacarun_wc_webhook']) && $_GET['amacarun_wc_webhook'] == '1') {
            $webhook_id = get_option('amacarun_woocommerce_webhook_id');
            
            if ($webhook_id) {
                // Verifica header e signature se configurato
                $this->process_woocommerce_webhook();
            }
            
            exit;
        }
    }
    
    /**
     * Processa webhook WooCommerce
     */
    private function process_woocommerce_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        if (isset($data['id']) && isset($data['status'])) {
            $order_id = intval($data['id']);
            $status = sanitize_text_field($data['status']);
            
            // Se ordine completato, sincronizza
            if (in_array($status, array('completed', 'processing'))) {
                $active_event = self::get_active_event();
                if ($active_event) {
                    $this->woocommerce_sync->sync_single_order($order_id, $active_event->id);
                    amacarun_log("Webhook sync completed for order $order_id");
                }
            }
        }
    }
    
    /**
     * Esegue sincronizzazione WooCommerce (cron job)
     */
    public function run_woocommerce_sync() {
        $active_event = self::get_active_event();
        
        if ($active_event) {
            $synced = $this->woocommerce_sync->sync_participants($active_event->id);
            amacarun_log("Cron sync completed: $synced participants synced");
        }
    }
    
    /**
     * Esegue backup giornaliero (cron job)
     */
    public function run_daily_backup() {
        $backup_file = AmacarUN_Database::backup_tables();
        
        if ($backup_file) {
            amacarun_log("Daily backup created: $backup_file");
            
            // Pulisci backup vecchi
            $this->cleanup_old_backups();
        }
    }
    
    /**
     * Pulisce backup vecchi
     */
    private function cleanup_old_backups() {
        $backup_dir = AMACARUN_PLUGIN_PATH . 'backups/';
        $retention_days = get_option('amacarun_backup_retention_days', 30);
        
        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . 'amacarun_backup_*.sql');
            $cutoff_time = time() - ($retention_days * DAY_IN_SECONDS);
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff_time) {
                    unlink($file);
                    amacarun_log("Old backup deleted: " . basename($file));
                }
            }
        }
    }
    
    /**
     * Pulisce dati temporanei (cron job)
     */
    public function cleanup_temp_data() {
        // Pulisci transient scaduti
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_amacarun_%' AND option_value < UNIX_TIMESTAMP()");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_amacarun_%' AND option_name NOT IN (SELECT CONCAT('_transient_', SUBSTRING(option_name, 19)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_amacarun_%')");
        
        // Pulisci directory temp
        $temp_dir = AMACARUN_PLUGIN_PATH . 'temp/';
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            $cutoff_time = time() - DAY_IN_SECONDS;
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff_time) {
                    unlink($file);
                }
            }
        }
        
        amacarun_log("Temp data cleanup completed");
    }
    
    /**
     * Ottiene evento attivo
     */
    public static function get_active_event() {
        global $wpdb;
        
        $event = $wpdb->get_row("SELECT * FROM " . AMACARUN_EVENTS_TABLE . " WHERE status = 'active' LIMIT 1");
        
        return $event;
    }
    
    /**
     * Ottiene tutti gli eventi
     */
    public static function get_all_events($status = null) {
        global $wpdb;
        
        $query = "SELECT * FROM " . AMACARUN_EVENTS_TABLE;
        
        if ($status) {
            $query .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $query .= " ORDER BY date DESC, created_at DESC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Crea nuovo evento
     */
    public function create_event($data) {
        global $wpdb;
        
        $defaults = array(
            'name' => '',
            'date' => date('Y-m-d'),
            'status' => 'draft',
            'woocommerce_category_id' => get_option('amacarun_woocommerce_category_id', 29),
            'bib_number_start' => 1001,
            'bib_number_current' => 1001,
            'mailpoet_auto_subscribe' => 1
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitizza i dati
        $data['name'] = sanitize_text_field($data['name']);
        $data['date'] = sanitize_text_field($data['date']);
        $data['status'] = sanitize_text_field($data['status']);
        
        $result = $wpdb->insert(AMACARUN_EVENTS_TABLE, $data);
        
        if ($result !== false) {
            $event_id = $wpdb->insert_id;
            
            do_action('amacarun_event_created', $event_id, $data);
            
            amacarun_log("Event created: ID $event_id, Name: {$data['name']}");
            
            return $event_id;
        }
        
        return false;
    }
    
    /**
     * Attiva evento (disattiva altri eventi attivi)
     */
    public function activate_event($event_id) {
        global $wpdb;
        
        // Disattiva tutti gli eventi attivi
        $wpdb->update(
            AMACARUN_EVENTS_TABLE,
            array('status' => 'draft'),
            array('status' => 'active')
        );
        
        // Attiva l'evento richiesto
        $result = $wpdb->update(
            AMACARUN_EVENTS_TABLE,
            array('status' => 'active'),
            array('id' => $event_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('amacarun_event_activated', $event_id);
            amacarun_log("Event activated: ID $event_id");
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottiene statistiche generali
     */
    public function get_stats() {
        $db_stats = AmacarUN_Database::get_stats();
        $active_event = self::get_active_event();
        
        $stats = array(
            'database' => $db_stats,
            'active_event' => $active_event,
            'plugin_version' => $this->version,
            'last_sync' => get_option('amacarun_last_sync_time', 'Mai'),
            'last_backup' => get_option('amacarun_last_backup_time', 'Mai')
        );
        
        return apply_filters('amacarun_stats', $stats);
    }
    
    /**
     * Ottiene manager specifico
     */
    public function get_manager($type) {
        $managers = array(
            'participant' => $this->participant_manager,
            'bib' => $this->bib_manager,
            'woocommerce' => $this->woocommerce_sync,
            'mailpoet' => $this->mailpoet_manager,
            'export' => $this->export_manager
        );
        
        return isset($managers[$type]) ? $managers[$type] : null;
    }
    
    /**
     * Verifica se il plugin è configurato correttamente
     */
    public function is_configured() {
        $active_event = self::get_active_event();
        $woocommerce_active = class_exists('WooCommerce');
        $category_configured = get_option('amacarun_woocommerce_category_id');
        
        return $active_event && $woocommerce_active && $category_configured;
    }
    
    /**
     * Ottiene URL admin del plugin
     */
    public static function get_admin_url($page = '', $params = array()) {
        $base_url = admin_url('admin.php?page=amacarun-race-manager');
        
        if ($page) {
            $params['tab'] = $page;
        }
        
        if (!empty($params)) {
            $base_url .= '&' . http_build_query($params);
        }
        
        return $base_url;
    }
}