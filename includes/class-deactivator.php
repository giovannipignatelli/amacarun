<?php
/**
 * Classe per la disattivazione del plugin AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestisce la disattivazione del plugin
 */
class AmacarUN_Deactivator {
    
    /**
     * Esegue la disattivazione del plugin
     */
    public static function deactivate() {
        // Rimuovi cron jobs
        self::clear_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Salva timestamp disattivazione
        update_option('amacarun_deactivated_time', current_time('timestamp'));
        
        // Log disattivazione
        amacarun_log('Plugin deactivated');
        
        // Opzionale: backup dati prima della disattivazione
        if (get_option('amacarun_backup_on_deactivate', false)) {
            self::create_backup();
        }
        
        // Notifica agli admin se configurato
        if (get_option('amacarun_notify_on_deactivate', false)) {
            self::send_deactivation_notification();
        }
    }
    
    /**
     * Rimuove tutti i cron jobs del plugin
     */
    private static function clear_cron_jobs() {
        $cron_jobs = array(
            'amacarun_sync_woocommerce',
            'amacarun_daily_backup',
            'amacarun_cleanup_temp_data'
        );
        
        foreach ($cron_jobs as $job) {
            $timestamp = wp_next_scheduled($job);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $job);
            }
        }
        
        amacarun_log('Cron jobs cleared');
    }
    
    /**
     * Crea backup dei dati prima della disattivazione
     */
    private static function create_backup() {
        $backup_file = AmacarUN_Database::backup_tables();
        
        if ($backup_file) {
            amacarun_log('Backup created on deactivation: ' . $backup_file);
        } else {
            amacarun_log('Failed to create backup on deactivation', 'error');
        }
    }
    
    /**
     * Invia notifica di disattivazione
     */
    private static function send_deactivation_notification() {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('AmacarUN Race Manager disattivato su %s', 'amacarun-race-manager'), $site_name);
        
        $message = sprintf(
            __('Il plugin AmacarUN Race Manager è stato disattivato su %s.

I dati rimangono nel database e saranno disponibili alla riattivazione.

Se non intendi più utilizzare il plugin, puoi eliminarlo completamente dalle impostazioni plugin di WordPress.

Data disattivazione: %s', 'amacarun-race-manager'),
            $site_name,
            current_time('Y-m-d H:i:s')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Pulisce dati temporanei
     */
    private static function cleanup_temp_data() {
        // Rimuovi transient
        $temp_options = array(
            'amacarun_activation_redirect',
            'amacarun_activation_warnings',
            'amacarun_sync_progress',
            'amacarun_export_progress'
        );
        
        foreach ($temp_options as $option) {
            delete_transient($option);
        }
        
        // Pulisci directory temporanee
        $temp_dir = AMACARUN_PLUGIN_PATH . 'temp/';
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        amacarun_log('Temporary data cleaned');
    }
    
    /**
     * Disattiva webhook WooCommerce se presente
     */
    private static function disable_woocommerce_webhook() {
        $webhook_id = get_option('amacarun_woocommerce_webhook_id');
        
        if ($webhook_id && class_exists('WC_Webhook')) {
            $webhook = new WC_Webhook($webhook_id);
            if ($webhook->get_id()) {
                $webhook->set_status('disabled');
                $webhook->save();
                amacarun_log('WooCommerce webhook disabled: ' . $webhook_id);
            }
        }
    }
}