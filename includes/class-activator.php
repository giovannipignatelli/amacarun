<?php
/**
 * Classe per l'attivazione del plugin AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestisce l'attivazione del plugin
 */
class AmacarUN_Activator {
    
    /**
     * Esegue l'attivazione del plugin
     */
    public static function activate() {
        // Verifica requisiti di sistema
        if (!self::check_system_requirements()) {
            return;
        }
        
        // Verifica plugin dipendenti
        if (!self::check_plugin_dependencies()) {
            return;
        }
        
        // Crea/aggiorna database
        self::setup_database();
        
        // Crea capabilities personalizzate
        self::create_capabilities();
        
        // Configura cron jobs
        self::setup_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Salva timestamp attivazione
        update_option('amacarun_activated_time', current_time('timestamp'));
        
        // Log attivazione
        amacarun_log('Plugin activated successfully');
        
        // Imposta flag per mostrare welcome message
        set_transient('amacarun_activation_redirect', true, 60);
    }
    
    /**
     * Verifica requisiti di sistema
     */
    private static function check_system_requirements() {
        $errors = array();
        
        // Verifica versione PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(
                __('AmacarUN Race Manager richiede PHP 7.4 o superiore. Versione corrente: %s', 'amacarun-race-manager'),
                PHP_VERSION
            );
        }
        
        // Verifica versione WordPress
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $errors[] = sprintf(
                __('AmacarUN Race Manager richiede WordPress 5.0 o superiore. Versione corrente: %s', 'amacarun-race-manager'),
                get_bloginfo('version')
            );
        }
        
        // Verifica estensioni PHP necessarie
        $required_extensions = array('mysqli', 'json', 'curl');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf(
                    __('Estensione PHP mancante: %s', 'amacarun-race-manager'),
                    $extension
                );
            }
        }
        
        // Verifica permessi directory
        $upload_dir = wp_upload_dir();
        if (!is_writable($upload_dir['basedir'])) {
            $errors[] = __('Directory uploads non scrivibile', 'amacarun-race-manager');
        }
        
        // Se ci sono errori, disattiva il plugin
        if (!empty($errors)) {
            deactivate_plugins(AMACARUN_PLUGIN_BASENAME);
            wp_die(
                '<h1>' . __('Errore Attivazione Plugin', 'amacarun-race-manager') . '</h1>' .
                '<p>' . implode('<br>', $errors) . '</p>' .
                '<a href="' . admin_url('plugins.php') . '">' . __('Torna ai Plugin', 'amacarun-race-manager') . '</a>'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica plugin dipendenti
     */
    private static function check_plugin_dependencies() {
        $errors = array();
        
        // Verifica WooCommerce
        if (!class_exists('WooCommerce')) {
            $errors[] = sprintf(
                __('WooCommerce è richiesto. <a href="%s">Installa WooCommerce</a>', 'amacarun-race-manager'),
                admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')
            );
        } else {
            // Verifica versione WooCommerce
            if (version_compare(WC_VERSION, '5.0', '<')) {
                $errors[] = sprintf(
                    __('WooCommerce versione 5.0 o superiore è richiesta. Versione corrente: %s', 'amacarun-race-manager'),
                    WC_VERSION
                );
            }
        }
        
        // Verifica Advanced Product Fields (non obbligatorio ma consigliato)
        if (!class_exists('THWCFD_Admin')) {
            $warnings[] = __('Plugin "Advanced Product Fields for WooCommerce" consigliato per funzionalità complete', 'amacarun-race-manager');
        }
        
        // Se ci sono errori critici, blocca attivazione
        if (!empty($errors)) {
            deactivate_plugins(AMACARUN_PLUGIN_BASENAME);
            wp_die(
                '<h1>' . __('Plugin Dipendenti Mancanti', 'amacarun-race-manager') . '</h1>' .
                '<p>' . implode('<br>', $errors) . '</p>' .
                '<a href="' . admin_url('plugins.php') . '">' . __('Torna ai Plugin', 'amacarun-race-manager') . '</a>'
            );
            return false;
        }
        
        // Salva warnings per mostrarli dopo
        if (!empty($warnings)) {
            set_transient('amacarun_activation_warnings', $warnings, 300);
        }
        
        return true;
    }
    
    /**
     * Configura il database
     */
    private static function setup_database() {
        // Crea tabelle
        AmacarUN_Database::create_tables();
        
        // Salva versione database
        update_option('amacarun_db_version', AmacarUN_Database::DB_VERSION);
        
        // Verifica integrità
        $integrity_check = AmacarUN_Database::check_integrity();
        if ($integrity_check !== true) {
            amacarun_log('Database integrity issues found: ' . implode(', ', $integrity_check), 'warning');
        }
    }
    
    /**
     * Crea capabilities personalizzate
     */
    private static function create_capabilities() {
        // Capabilities per il plugin
        $capabilities = array(
            'manage_amacarun_events' => array(
                'administrator',
                'editor'
            ),
            'manage_amacarun_participants' => array(
                'administrator',
                'editor',
                'author'
            ),
            'amacarun_checkin' => array(
                'administrator',
                'editor',
                'author',
                'contributor'
            ),
            'export_amacarun_data' => array(
                'administrator',
                'editor'
            )
        );
        
        // Aggiungi capabilities ai ruoli
        foreach ($capabilities as $cap => $roles) {
            foreach ($roles as $role_name) {
                $role = get_role($role_name);
                if ($role) {
                    $role->add_cap($cap);
                }
            }
        }
        
        // Crea ruolo personalizzato per operatori gara
        add_role(
            'amacarun_operator',
            __('Operatore AmacarUN', 'amacarun-race-manager'),
            array(
                'read' => true,
                'amacarun_checkin' => true,
                'manage_amacarun_participants' => true
            )
        );
        
        amacarun_log('Custom capabilities created');
    }
    
    /**
     * Configura cron jobs
     */
    private static function setup_cron_jobs() {
        // Sincronizzazione automatica WooCommerce (ogni ora)
        if (!wp_next_scheduled('amacarun_sync_woocommerce')) {
            wp_schedule_event(time(), 'hourly', 'amacarun_sync_woocommerce');
        }
        
        // Backup automatico database (giornaliero)
        if (!wp_next_scheduled('amacarun_daily_backup')) {
            wp_schedule_event(time(), 'daily', 'amacarun_daily_backup');
        }
        
        // Pulizia dati temporanei (settimanale)
        if (!wp_next_scheduled('amacarun_cleanup_temp_data')) {
            wp_schedule_event(time(), 'weekly', 'amacarun_cleanup_temp_data');
        }
        
        amacarun_log('Cron jobs scheduled');
    }
    
    /**
     * Configura opzioni di default
     */
    private static function setup_default_options() {
        $default_options = array(
            'amacarun_auto_sync_woocommerce' => 1,
            'amacarun_auto_assign_bibs' => 1,
            'amacarun_bib_start_number' => 1001,
            'amacarun_mailpoet_auto_subscribe' => 1,
            'amacarun_enable_public_list' => 1,
            'amacarun_debug_logging' => 0,
            'amacarun_backup_retention_days' => 30
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
        
        amacarun_log('Default options configured');
    }
    
    /**
     * Verifica e crea directory necessarie
     */
    private static function create_directories() {
        $directories = array(
            AMACARUN_PLUGIN_PATH . 'backups',
            AMACARUN_PLUGIN_PATH . 'logs',
            AMACARUN_PLUGIN_PATH . 'temp'
        );
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
                
                // Crea .htaccess per protezione
                $htaccess_content = "Order deny,allow\nDeny from all";
                file_put_contents($dir . '/.htaccess', $htaccess_content);
                
                // Crea index.php vuoto
                file_put_contents($dir . '/index.php', '<?php // Silence is golden');
            }
        }
        
        amacarun_log('Plugin directories created');
    }
    
    /**
     * Configura integrazione WooCommerce
     */
    private static function setup_woocommerce_integration() {
        // Verifica esistenza categoria "amacarun"
        $category_id = self::ensure_woocommerce_category();
        
        if ($category_id) {
            update_option('amacarun_woocommerce_category_id', $category_id);
            amacarun_log('WooCommerce integration configured with category ID: ' . $category_id);
        }
        
        // Registra webhook per nuovi ordini (se possibile)
        if (function_exists('wc_get_webhook_statuses')) {
            self::create_woocommerce_webhook();
        }
    }
    
    /**
     * Assicura esistenza categoria WooCommerce "amacarun"
     */
    private static function ensure_woocommerce_category() {
        // Cerca categoria esistente
        $existing_category = get_term_by('slug', 'amacarun', 'product_cat');
        
        if ($existing_category) {
            return $existing_category->term_id;
        }
        
        // Crea nuova categoria
        $category_data = wp_insert_term(
            'AmacarUN',
            'product_cat',
            array(
                'description' => __('Prodotti per la gara AmacarUN', 'amacarun-race-manager'),
                'slug' => 'amacarun'
            )
        );
        
        if (is_wp_error($category_data)) {
            amacarun_log('Failed to create WooCommerce category: ' . $category_data->get_error_message(), 'error');
            return false;
        }
        
        return $category_data['term_id'];
    }
    
    /**
     * Crea webhook WooCommerce per sincronizzazione automatica
     */
    private static function create_woocommerce_webhook() {
        if (!class_exists('WC_Webhook')) {
            return false;
        }
        
        $webhook = new WC_Webhook();
        $webhook->set_name('AmacarUN Auto Sync');
        $webhook->set_user_id(get_current_user_id());
        $webhook->set_topic('order.updated');
        $webhook->set_delivery_url(site_url('/?amacarun_wc_webhook=1'));
        $webhook->set_secret(wp_generate_password(50, false));
        $webhook->set_status('active');
        
        $webhook_id = $webhook->save();
        
        if ($webhook_id) {
            update_option('amacarun_woocommerce_webhook_id', $webhook_id);
            amacarun_log('WooCommerce webhook created: ' . $webhook_id);
            return $webhook_id;
        }
        
        return false;
    }
    
    /**
     * Invia notifica di attivazione agli admin
     */
    private static function send_activation_notification() {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('AmacarUN Race Manager attivato su %s', 'amacarun-race-manager'), $site_name);
        
        $message = sprintf(
            __('Il plugin AmacarUN Race Manager è stato attivato con successo su %s.

Funzionalità disponibili:
- Gestione eventi e partecipanti
- Integrazione WooCommerce
- Sistema check-in con pettorali
- Integrazione MailPoet
- Export dati CSV

Prossimi passi:
1. Configura il tuo primo evento
2. Associa i prodotti WooCommerce
3. Configura MailPoet se necessario

Accedi alla dashboard: %s

Buona gara!', 'amacarun-race-manager'),
            $site_name,
            admin_url('admin.php?page=amacarun-race-manager')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Verifica aggiornamenti da versioni precedenti
     */
    private static function handle_upgrades() {
        $current_version = get_option('amacarun_version', '0.0.0');
        
        // Se è una nuova installazione, non fare nulla
        if ($current_version === '0.0.0') {
            return;
        }
        
        // Aggiornamenti specifici per versione
        if (version_compare($current_version, '1.0.0', '<')) {
            // Aggiornamenti per versione 1.0.0
            self::upgrade_to_1_0_0();
        }
        
        // Aggiorna versione
        update_option('amacarun_version', AMACARUN_VERSION);
    }
    
    /**
     * Aggiornamenti specifici per versione 1.0.0
     */
    private static function upgrade_to_1_0_0() {
        // Esempio: migrazione dati, nuove tabelle, ecc.
        amacarun_log('Upgraded to version 1.0.0');
    }
}