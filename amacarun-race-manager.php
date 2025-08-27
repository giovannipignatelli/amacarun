<?php
/**
 * Plugin Name: AmacarUN Race Manager
 * Plugin URI: https://yourdomain.com/amacarun-race-manager
 * Description: Plugin per la gestione completa della gara podistica AmacarUN con integrazione WooCommerce, pettorali, check-in e MailPoet.
 * Version: 1.0.0
 * Author: Il Tuo Nome
 * Author URI: https://yourdomain.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: amacarun-race-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Costanti del plugin
define('AMACARUN_VERSION', '1.0.0');
define('AMACARUN_PLUGIN_FILE', __FILE__);
define('AMACARUN_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AMACARUN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMACARUN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale del plugin
 */
final class AmacarUN_Race_Manager_Plugin {
    
    /**
     * Istanza singleton
     */
    private static $instance = null;
    
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
     * Costruttore privato per singleton
     */
    private function __construct() {
        $this->define_constants();
        $this->check_requirements();
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Definisce costanti aggiuntive
     */
    private function define_constants() {
        // Tabelle database
        global $wpdb;
        if (!defined('AMACARUN_EVENTS_TABLE')) {
            define('AMACARUN_EVENTS_TABLE', $wpdb->prefix . 'amacarun_events');
        }
        if (!defined('AMACARUN_PARTICIPANTS_TABLE')) {
            define('AMACARUN_PARTICIPANTS_TABLE', $wpdb->prefix . 'amacarun_participants');
        }
    }
    
    /**
     * Verifica requisiti del plugin
     */
    private function check_requirements() {
        // Verifica versione PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        // Verifica WordPress
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }
        
        // Verifica WooCommerce all'attivazione
        add_action('admin_init', array($this, 'check_woocommerce'));
        
        return true;
    }
    
    /**
     * Include i file necessari
     */
    private function includes() {
        // Core classes
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-database.php';
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-activator.php';
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-deactivator.php';
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-amacarun-race-manager.php';
        
        // Manager classes
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-participant-manager.php';
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-bib-manager.php';
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-woocommerce-sync.php';
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-mailpoet-manager.php';
        require_once AMACARUN_PLUGIN_PATH . 'includes/class-export-manager.php';
        
        // Admin and Public
        if (is_admin()) {
            require_once AMACARUN_PLUGIN_PATH . 'admin/class-admin.php';
        }
        
        require_once AMACARUN_PLUGIN_PATH . 'public/class-public.php';
    }
    
    /**
     * Inizializza gli hook
     */
    private function init_hooks() {
        // Attivazione e disattivazione
        register_activation_hook(__FILE__, array('AmacarUN_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('AmacarUN_Deactivator', 'deactivate'));
        
        // Inizializzazione plugin
        add_action('plugins_loaded', array($this, 'init'));
        
        // Caricamento traduzioni
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Hook per aggiornamenti database
        add_action('plugins_loaded', array($this, 'check_version'));
    }
    
    /**
     * Inizializza il plugin
     */
    public function init() {
        // Inizializza la classe principale
        AmacarUN_Race_Manager::get_instance();
    }
    
    /**
     * Carica le traduzioni
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'amacarun-race-manager',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Verifica versione e aggiorna database se necessario
     */
    public function check_version() {
        $current_version = get_option('amacarun_version', '0.0.0');
        
        if (version_compare($current_version, AMACARUN_VERSION, '<')) {
            AmacarUN_Database::create_tables();
            update_option('amacarun_version', AMACARUN_VERSION);
        }
    }
    
    /**
     * Verifica presenza WooCommerce
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }
    
    /**
     * Notice versione PHP
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('AmacarUN Race Manager richiede PHP versione 7.4 o superiore. Stai usando la versione %s.', 'amacarun-race-manager'),
            PHP_VERSION
        );
        echo '</p></div>';
    }
    
    /**
     * Notice versione WordPress
     */
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('AmacarUN Race Manager richiede WordPress versione 5.0 o superiore. Stai usando la versione %s.', 'amacarun-race-manager'),
            get_bloginfo('version')
        );
        echo '</p></div>';
    }
    
    /**
     * Notice WooCommerce mancante
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('AmacarUN Race Manager richiede WooCommerce per funzionare. <a href="%s">Installa WooCommerce</a> oppure <a href="%s">disattiva AmacarUN Race Manager</a>.', 'amacarun-race-manager'),
            admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'),
            admin_url('plugins.php')
        );
        echo '</p></div>';
    }
}

/**
 * Funzione di accesso globale
 */
function amacarun_race_manager() {
    return AmacarUN_Race_Manager_Plugin::get_instance();
}

// Inizializza il plugin
amacarun_race_manager();

/**
 * Funzioni di utilità globali
 */

/**
 * Ottiene evento attivo
 */
function amacarun_get_active_event() {
    return AmacarUN_Race_Manager::get_active_event();
}

/**
 * Ottiene partecipante per ID
 */
function amacarun_get_participant($participant_id) {
    $participant_manager = new AmacarUN_Participant_Manager();
    return $participant_manager->get_participant($participant_id);
}

/**
 * Verifica se MailPoet è disponibile
 */
function amacarun_is_mailpoet_active() {
    $mailpoet_manager = new AmacarUN_MailPoet_Manager();
    return $mailpoet_manager->is_mailpoet_active();
}

/**
 * Log di debug per il plugin
 */
function amacarun_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_entry = sprintf(
            '[%s] AmacarUN %s: %s',
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );
        error_log($log_entry);
    }
}