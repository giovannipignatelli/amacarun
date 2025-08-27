<?php
/**
 * Gestione Database del plugin AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per la gestione del database
 */
class AmacarUN_Database {
    
    /**
     * Versione schema database
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Crea le tabelle del plugin
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella eventi
        $events_table = AMACARUN_EVENTS_TABLE;
        $events_sql = "CREATE TABLE $events_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            date date NOT NULL,
            status enum('draft','active','completed') DEFAULT 'draft',
            woocommerce_category_id int(11) DEFAULT NULL,
            adult_product_id int(11) DEFAULT NULL,
            child_product_id int(11) DEFAULT NULL,
            bib_number_start int(11) DEFAULT 1001,
            bib_number_current int(11) DEFAULT 1001,
            mailpoet_list_id int(11) DEFAULT NULL,
            mailpoet_auto_subscribe tinyint(1) DEFAULT 1,
            mailpoet_double_optin tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY date (date)
        ) $charset_collate;";
        
        // Tabella partecipanti
        $participants_table = AMACARUN_PARTICIPANTS_TABLE;
        $participants_sql = "CREATE TABLE $participants_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_id int(11) NOT NULL,
            bib_number int(11) DEFAULT NULL,
            woocommerce_order_id int(11) DEFAULT NULL,
            woocommerce_item_id int(11) DEFAULT NULL,
            participant_type enum('adult','child') NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            association_member tinyint(1) DEFAULT 0,
            distance enum('4km','11km') DEFAULT NULL,
            status enum('registered','checked_in','retired') DEFAULT 'registered',
            registration_type enum('online','on_site') DEFAULT 'online',
            payment_method varchar(50) DEFAULT NULL,
            payment_amount decimal(10,2) DEFAULT NULL,
            check_in_time datetime DEFAULT NULL,
            mailpoet_subscriber_id int(11) DEFAULT NULL,
            mailpoet_subscribed tinyint(1) DEFAULT 0,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY bib_number (bib_number),
            KEY email (email),
            KEY status (status),
            KEY participant_type (participant_type),
            KEY woocommerce_order_id (woocommerce_order_id),
            UNIQUE KEY unique_bib_per_event (event_id, bib_number),
            CONSTRAINT fk_participant_event FOREIGN KEY (event_id) REFERENCES $events_table (id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Esegui creazione tabelle
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $result_events = dbDelta($events_sql);
        $result_participants = dbDelta($participants_sql);
        
        // Aggiorna versione database
        update_option('amacarun_db_version', self::DB_VERSION);
        
        // Log risultati
        amacarun_log('Database tables created/updated: Events - ' . print_r($result_events, true) . ', Participants - ' . print_r($result_participants, true));
        
        // Crea indici aggiuntivi se necessario
        self::create_additional_indexes();
        
        // Inserisci dati di default
        self::insert_default_data();
    }
    
    /**
     * Crea indici aggiuntivi per performance
     */
    private static function create_additional_indexes() {
        global $wpdb;
        
        $participants_table = AMACARUN_PARTICIPANTS_TABLE;
        
        // Indice composito per ricerche frequenti
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_event_status ON $participants_table (event_id, status)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_event_type ON $participants_table (event_id, participant_type)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_name_search ON $participants_table (first_name, last_name)");
        
        amacarun_log('Additional database indexes created');
    }
    
    /**
     * Inserisce dati di default
     */
    private static function insert_default_data() {
        global $wpdb;
        
        $events_table = AMACARUN_EVENTS_TABLE;
        
        // Verifica se esistono già eventi
        $existing_events = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");
        
        if ($existing_events == 0) {
            // Crea evento di esempio
            $current_year = date('Y');
            $sample_event = array(
                'name' => 'AmacarUN ' . $current_year,
                'date' => $current_year . '-06-15',
                'status' => 'draft',
                'woocommerce_category_id' => 29, // Categoria "amacarun"
                'bib_number_start' => 1001,
                'bib_number_current' => 1001
            );
            
            $wpdb->insert($events_table, $sample_event);
            
            amacarun_log('Default event created: AmacarUN ' . $current_year);
        }
    }
    
    /**
     * Elimina le tabelle del plugin
     */
    public static function drop_tables() {
        global $wpdb;
        
        $participants_table = AMACARUN_PARTICIPANTS_TABLE;
        $events_table = AMACARUN_EVENTS_TABLE;
        
        // Elimina prima la tabella con foreign key
        $wpdb->query("DROP TABLE IF EXISTS $participants_table");
        $wpdb->query("DROP TABLE IF EXISTS $events_table");
        
        // Elimina opzioni
        delete_option('amacarun_db_version');
        
        amacarun_log('Database tables dropped');
    }
    
    /**
     * Verifica integrità database
     */
    public static function check_integrity() {
        global $wpdb;
        
        $events_table = AMACARUN_EVENTS_TABLE;
        $participants_table = AMACARUN_PARTICIPANTS_TABLE;
        
        $errors = array();
        
        // Verifica esistenza tabelle
        $events_exists = $wpdb->get_var("SHOW TABLES LIKE '$events_table'") === $events_table;
        $participants_exists = $wpdb->get_var("SHOW TABLES LIKE '$participants_table'") === $participants_table;
        
        if (!$events_exists) {
            $errors[] = 'Tabella eventi mancante';
        }
        
        if (!$participants_exists) {
            $errors[] = 'Tabella partecipanti mancante';
        }
        
        // Verifica foreign key
        if ($events_exists && $participants_exists) {
            $orphaned = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM $participants_table p 
                LEFT JOIN $events_table e ON p.event_id = e.id 
                WHERE e.id IS NULL
            ");
            
            if ($orphaned > 0) {
                $errors[] = "Trovati $orphaned partecipanti orfani";
            }
        }
        
        // Verifica pettorali duplicati
        if ($participants_exists) {
            $duplicate_bibs = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM (
                    SELECT event_id, bib_number, COUNT(*) as cnt
                    FROM $participants_table 
                    WHERE bib_number IS NOT NULL
                    GROUP BY event_id, bib_number
                    HAVING cnt > 1
                ) as duplicates
            ");
            
            if ($duplicate_bibs > 0) {
                $errors[] = "Trovati $duplicate_bibs pettorali duplicati";
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Ripara problemi nel database
     */
    public static function repair_database() {
        global $wpdb;
        
        $events_table = AMACARUN_EVENTS_TABLE;
        $participants_table = AMACARUN_PARTICIPANTS_TABLE;
        
        $repairs = array();
        
        // Rimuovi partecipanti orfani
        $orphaned = $wpdb->query("
            DELETE p FROM $participants_table p 
            LEFT JOIN $events_table e ON p.event_id = e.id 
            WHERE e.id IS NULL
        ");
        
        if ($orphaned > 0) {
            $repairs[] = "Rimossi $orphaned partecipanti orfani";
        }
        
        // Correggi pettorali duplicati
        $duplicate_bibs = $wpdb->get_results("
            SELECT event_id, bib_number, GROUP_CONCAT(id) as participant_ids
            FROM $participants_table 
            WHERE bib_number IS NOT NULL
            GROUP BY event_id, bib_number
            HAVING COUNT(*) > 1
        ");
        
        foreach ($duplicate_bibs as $duplicate) {
            $ids = explode(',', $duplicate->participant_ids);
            // Mantieni il primo, rimuovi pettorale dagli altri
            array_shift($ids);
            
            foreach ($ids as $id) {
                $wpdb->update(
                    $participants_table,
                    array('bib_number' => null),
                    array('id' => $id)
                );
            }
            
            $repairs[] = "Corretti pettorali duplicati per evento {$duplicate->event_id}, numero {$duplicate->bib_number}";
        }
        
        return $repairs;
    }
    
    /**
     * Esegue backup delle tabelle
     */
    public static function backup_tables() {
        global $wpdb;
        
        $events_table = AMACARUN_EVENTS_TABLE;
        $participants_table = AMACARUN_PARTICIPANTS_TABLE;
        
        $backup_dir = AMACARUN_PLUGIN_PATH . 'backups/';
        
        // Crea directory backup se non exists
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $backup_dir . "amacarun_backup_$timestamp.sql";
        
        $sql_content = "-- AmacarUN Race Manager Backup - $timestamp\n\n";
        
        // Backup eventi
        $events = $wpdb->get_results("SELECT * FROM $events_table", ARRAY_A);
        $sql_content .= "-- Tabella Eventi\n";
        $sql_content .= "TRUNCATE TABLE $events_table;\n";
        
        foreach ($events as $event) {
            $values = array();
            foreach ($event as $key => $value) {
                $values[] = $wpdb->prepare('%s', $value);
            }
            $columns = implode(',', array_keys($event));
            $values_str = implode(',', $values);
            $sql_content .= "INSERT INTO $events_table ($columns) VALUES ($values_str);\n";
        }
        
        // Backup partecipanti
        $participants = $wpdb->get_results("SELECT * FROM $participants_table", ARRAY_A);
        $sql_content .= "\n-- Tabella Partecipanti\n";
        $sql_content .= "TRUNCATE TABLE $participants_table;\n";
        
        foreach ($participants as $participant) {
            $values = array();
            foreach ($participant as $key => $value) {
                $values[] = $wpdb->prepare('%s', $value);
            }
            $columns = implode(',', array_keys($participant));
            $values_str = implode(',', $values);
            $sql_content .= "INSERT INTO $participants_table ($columns) VALUES ($values_str);\n";
        }
        
        // Salva file
        $result = file_put_contents($backup_file, $sql_content);
        
        if ($result !== false) {
            amacarun_log("Database backup created: $backup_file");
            return $backup_file;
        } else {
            amacarun_log("Failed to create database backup", 'error');
            return false;
        }
    }
    
    /**
     * Ottiene statistiche database
     */
    public static function get_stats() {
        global $wpdb;
        
        $events_table = AMACARUN_EVENTS_TABLE;
        $participants_table = AMACARUN_PARTICIPANTS_TABLE;
        
        $stats = array();
        
        // Eventi
        $stats['events'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $events_table"),
            'active' => $wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE status = 'active'"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE status = 'completed'"),
            'draft' => $wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE status = 'draft'")
        );
        
        // Partecipanti
        $stats['participants'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $participants_table"),
            'adults' => $wpdb->get_var("SELECT COUNT(*) FROM $participants_table WHERE participant_type = 'adult'"),
            'children' => $wpdb->get_var("SELECT COUNT(*) FROM $participants_table WHERE participant_type = 'child'"),
            'checked_in' => $wpdb->get_var("SELECT COUNT(*) FROM $participants_table WHERE status = 'checked_in'"),
            'with_bib' => $wpdb->get_var("SELECT COUNT(*) FROM $participants_table WHERE bib_number IS NOT NULL"),
            'mailpoet_subscribed' => $wpdb->get_var("SELECT COUNT(*) FROM $participants_table WHERE mailpoet_subscribed = 1")
        );
        
        // Dimensioni tabelle
        $stats['table_sizes'] = array(
            'events' => $wpdb->get_var("
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = '$events_table'
            "),
            'participants' => $wpdb->get_var("
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = '$participants_table'
            ")
        );
        
        return $stats;
    }
}