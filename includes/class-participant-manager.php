<?php
/**
 * Gestione Partecipanti AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per la gestione dei partecipanti
 */
class AmacarUN_Participant_Manager {
    
    /**
     * Tabella partecipanti
     */
    private $table_name;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->table_name = AMACARUN_PARTICIPANTS_TABLE;
    }
    
    /**
     * Crea nuovo partecipante
     */
    public function create_participant($data) {
        global $wpdb;
        
        $defaults = array(
            'event_id' => 0,
            'participant_type' => 'adult',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'association_member' => 0,
            'status' => 'registered',
            'registration_type' => 'online'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validazione dati
        $validation = $this->validate_participant_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Sanitizza dati
        $data = $this->sanitize_participant_data($data);
        
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result !== false) {
            $participant_id = $wpdb->insert_id;
            
            // Hook per azioni post-creazione
            do_action('amacarun_participant_created', $participant_id, $data);
            
            amacarun_log("Participant created: ID $participant_id, {$data['first_name']} {$data['last_name']}");
            
            return $participant_id;
        }
        
        return new WP_Error('db_error', 'Errore nella creazione del partecipante');
    }
    
    /**
     * Aggiorna partecipante
     */
    public function update_participant($participant_id, $data) {
        global $wpdb;
        
        // Rimuovi ID dai dati se presente
        unset($data['id']);
        
        // Sanitizza dati
        $data = $this->sanitize_participant_data($data);
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $participant_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            do_action('amacarun_participant_updated', $participant_id, $data);
            amacarun_log("Participant updated: ID $participant_id");
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottiene partecipante per ID
     */
    public function get_participant($participant_id) {
        global $wpdb;
        
        $participant = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $participant_id
        ));
        
        return $participant;
    }
    
    /**
     * Ottiene partecipanti per evento
     */
    public function get_participants_by_event($event_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '', // '', 'registered', 'checked_in', 'retired'
            'participant_type' => '', // '', 'adult', 'child'
            'distance' => '', // '', '4km', '11km'
            'has_bib' => null, // null, true, false
            'limit' => 0,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM {$this->table_name} WHERE event_id = %d";
        $query_params = array($event_id);
        
        // Filtri
        if (!empty($args['status'])) {
            $query .= " AND status = %s";
            $query_params[] = $args['status'];
        }
        
        if (!empty($args['participant_type'])) {
            $query .= " AND participant_type = %s";
            $query_params[] = $args['participant_type'];
        }
        
        if (!empty($args['distance'])) {
            $query .= " AND distance = %s";
            $query_params[] = $args['distance'];
        }
        
        if ($args['has_bib'] === true) {
            $query .= " AND bib_number IS NOT NULL";
        } elseif ($args['has_bib'] === false) {
            $query .= " AND bib_number IS NULL";
        }
        
        // Ordinamento
        $allowed_orderby = array('id', 'first_name', 'last_name', 'bib_number', 'created_at', 'check_in_time');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $query .= " ORDER BY $orderby $order";
        
        // Paginazione
        if ($args['limit'] > 0) {
            $query .= " LIMIT %d";
            $query_params[] = $args['limit'];
            
            if ($args['offset'] > 0) {
                $query .= " OFFSET %d";
                $query_params[] = $args['offset'];
            }
        }
        
        return $wpdb->get_results($wpdb->prepare($query, $query_params));
    }
    
    /**
     * Cerca partecipanti
     */
    public function search_participants($query, $event_id = null, $limit = 20) {
        global $wpdb;
        
        $search_query = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $params = array();
        
        if ($event_id) {
            $search_query .= " AND event_id = %d";
            $params[] = $event_id;
        }
        
        // Verifica se la query è un numero (possibile pettorale)
        if (is_numeric($query)) {
            $search_query .= " AND bib_number = %d";
            $params[] = intval($query);
        } else {
            // Ricerca per nome, cognome o email
            $search_query .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)";
            $like_query = '%' . $wpdb->esc_like($query) . '%';
            $params[] = $like_query;
            $params[] = $like_query;
            $params[] = $like_query;
        }
        
        $search_query .= " ORDER BY first_name, last_name LIMIT %d";
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($search_query, $params));
    }
    
    /**
     * Esegue check-in partecipante
     */
    public function check_in_participant($participant_id, $distance = null) {
        global $wpdb;
        
        $update_data = array(
            'status' => 'checked_in',
            'check_in_time' => current_time('mysql')
        );
        
        if ($distance && in_array($distance, array('4km', '11km'))) {
            $update_data['distance'] = $distance;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $participant_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('amacarun_participant_checked_in', $participant_id, $distance);
            amacarun_log("Participant checked in: ID $participant_id, Distance: $distance");
            return true;
        }
        
        return false;
    }
    
    /**
     * Ritira partecipante
     */
    public function retire_participant($participant_id, $notes = '') {
        global $wpdb;
        
        $update_data = array(
            'status' => 'retired'
        );
        
        if (!empty($notes)) {
            $update_data['notes'] = sanitize_textarea_field($notes);
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $participant_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('amacarun_participant_retired', $participant_id, $notes);
            amacarun_log("Participant retired: ID $participant_id");
            return true;
        }
        
        return false;
    }
    
    /**
     * Elimina partecipante
     */
    public function delete_participant($participant_id) {
        global $wpdb;
        
        // Ottieni dati partecipante prima dell'eliminazione
        $participant = $this->get_participant($participant_id);
        
        if (!$participant) {
            return new WP_Error('not_found', 'Partecipante non trovato');
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $participant_id),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('amacarun_participant_deleted', $participant_id, $participant);
            amacarun_log("Participant deleted: ID $participant_id, {$participant->first_name} {$participant->last_name}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottiene statistiche partecipanti per evento
     */
    public function get_event_stats($event_id) {
        global $wpdb;
        
        $stats = array();
        
        // Totale partecipanti
        $stats['total'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d",
            $event_id
        ));
        
        // Per tipologia
        $stats['adults'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND participant_type = 'adult'",
            $event_id
        ));
        
        $stats['children'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND participant_type = 'child'",
            $event_id
        ));
        
        // Per stato
        $stats['registered'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND status = 'registered'",
            $event_id
        ));
        
        $stats['checked_in'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND status = 'checked_in'",
            $event_id
        ));
        
        $stats['retired'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND status = 'retired'",
            $event_id
        ));
        
        // Per distanza (solo check-in)
        $stats['distance_4km'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND distance = '4km'",
            $event_id
        ));
        
        $stats['distance_11km'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND distance = '11km'",
            $event_id
        ));
        
        // Con pettorali assegnati
        $stats['with_bib'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND bib_number IS NOT NULL",
            $event_id
        ));
        
        // Membri associazione
        $stats['association_members'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND association_member = 1",
            $event_id
        ));
        
        // Iscritti MailPoet
        $stats['mailpoet_subscribed'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND mailpoet_subscribed = 1",
            $event_id
        ));
        
        // Per tipo registrazione
        $stats['online_registrations'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND registration_type = 'online'",
            $event_id
        ));
        
        $stats['onsite_registrations'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND registration_type = 'on_site'",
            $event_id
        ));
        
        return $stats;
    }
    
    /**
     * Valida dati partecipante
     */
    private function validate_participant_data($data) {
        $errors = array();
        
        // Campi obbligatori
        if (empty($data['event_id']) || !is_numeric($data['event_id'])) {
            $errors[] = 'ID evento richiesto';
        }
        
        if (empty($data['first_name'])) {
            $errors[] = 'Nome richiesto';
        }
        
        if (empty($data['last_name'])) {
            $errors[] = 'Cognome richiesto';
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = 'Email valida richiesta';
        }
        
        // Verifica tipo partecipante
        if (!in_array($data['participant_type'], array('adult', 'child'))) {
            $errors[] = 'Tipo partecipante non valido';
        }
        
        // Verifica stato
        if (isset($data['status']) && !in_array($data['status'], array('registered', 'checked_in', 'retired'))) {
            $errors[] = 'Stato non valido';
        }
        
        // Verifica distanza se specificata
        if (isset($data['distance']) && !empty($data['distance']) && !in_array($data['distance'], array('4km', '11km'))) {
            $errors[] = 'Distanza non valida';
        }
        
        // Verifica duplicati email per evento
        if (!empty($data['email']) && !empty($data['event_id'])) {
            global $wpdb;
            
            $existing_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE event_id = %d AND email = %s";
            $existing_params = array($data['event_id'], $data['email']);
            
            // Esclude se stesso se è un aggiornamento
            if (isset($data['id'])) {
                $existing_query .= " AND id != %d";
                $existing_params[] = $data['id'];
            }
            
            $existing = $wpdb->get_var($wpdb->prepare($existing_query, $existing_params));
            
            if ($existing > 0) {
                $errors[] = 'Email già registrata per questo evento';
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_error', implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Sanitizza dati partecipante
     */
    private function sanitize_participant_data($data) {
        $sanitized = array();
        
        // Campi testo
        $text_fields = array('first_name', 'last_name', 'phone', 'payment_method', 'participant_type', 'distance', 'status', 'registration_type');
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Email
        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }
        
        // Numeri
        $numeric_fields = array('event_id', 'bib_number', 'woocommerce_order_id', 'woocommerce_item_id', 'mailpoet_subscriber_id');
        foreach ($numeric_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = intval($data[$field]);
            }
        }
        
        // Decimali
        if (isset($data['payment_amount'])) {
            $sanitized['payment_amount'] = floatval($data['payment_amount']);
        }
        
        // Boolean (come tinyint)
        $boolean_fields = array('association_member', 'mailpoet_subscribed');
        foreach ($boolean_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field] ? 1 : 0;
            }
        }
        
        // Date/time
        if (isset($data['check_in_time']) && !empty($data['check_in_time'])) {
            $sanitized['check_in_time'] = sanitize_text_field($data['check_in_time']);
        }
        
        // Note
        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        return $sanitized;
    }
    
    /**
     * Ottiene partecipanti duplicati (stessa email)
     */
    public function get_duplicate_participants($event_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT email, COUNT(*) as count, GROUP_CONCAT(id) as participant_ids
            FROM {$this->table_name} 
            WHERE event_id = %d 
            GROUP BY email 
            HAVING count > 1
            ORDER BY count DESC
        ", $event_id));
    }
    
    /**
     * Ottiene partecipanti senza pettorale
     */
    public function get_participants_without_bib($event_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->table_name} 
            WHERE event_id = %d AND bib_number IS NULL 
            ORDER BY created_at ASC
        ", $event_id));
    }
    
    /**
     * Esporta partecipanti in formato array
     */
    public function export_participants($event_id, $format = 'array') {
        $participants = $this->get_participants_by_event($event_id);
        
        if ($format === 'csv_array') {
            $csv_data = array();
            $csv_data[] = array(
                'ID',
                'Pettorale',
                'Nome',
                'Cognome',
                'Email',
                'Telefono',
                'Tipo',
                'Membro Associazione',
                'Distanza',
                'Stato',
                'Check-in',
                'Registrazione',
                'Pagamento',
                'Importo',
                'MailPoet',
                'Note',
                'Data Iscrizione'
            );
            
            foreach ($participants as $participant) {
                $csv_data[] = array(
                    $participant->id,
                    $participant->bib_number ?: 'N/A',
                    $participant->first_name,
                    $participant->last_name,
                    $participant->email,
                    $participant->phone ?: 'N/A',
                    $participant->participant_type === 'adult' ? 'Adulto' : 'Bambino',
                    $participant->association_member ? 'Sì' : 'No',
                    $participant->distance ?: 'Non assegnata',
                    ucfirst($participant->status),
                    $participant->check_in_time ?: 'Non effettuato',
                    ucfirst($participant->registration_type),
                    $participant->payment_method ?: 'N/A',
                    $participant->payment_amount ? '€' . number_format($participant->payment_amount, 2) : 'N/A',
                    $participant->mailpoet_subscribed ? 'Sì' : 'No',
                    $participant->notes ?: '',
                    $participant->created_at
                );
            }
            
            return $csv_data;
        }
        
        return $participants;
    }
    
    /**
     * Importa partecipanti da array
     */
    public function import_participants($event_id, $participants_data) {
        $imported = 0;
        $errors = array();
        
        foreach ($participants_data as $index => $participant_data) {
            $participant_data['event_id'] = $event_id;
            
            $participant_id = $this->create_participant($participant_data);
            
            if (is_wp_error($participant_id)) {
                $errors[] = sprintf(
                    'Riga %d: %s',
                    $index + 1,
                    $participant_id->get_error_message()
                );
            } else {
                $imported++;
            }
        }
        
        return array(
            'imported' => $imported,
            'errors' => $errors
        );
    }
    
    /**
     * Ottiene partecipanti per check-in rapido
     */
    public function get_checkin_list($event_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                id,
                bib_number,
                first_name,
                last_name,
                participant_type,
                status,
                distance,
                email
            FROM {$this->table_name} 
            WHERE event_id = %d 
            ORDER BY 
                CASE 
                    WHEN bib_number IS NOT NULL THEN bib_number 
                    ELSE 9999999 
                END ASC,
                first_name ASC,
                last_name ASC
        ", $event_id));
    }
    
    /**
     * Aggiorna batch di partecipanti
     */
    public function bulk_update_participants($participant_ids, $data) {
        global $wpdb;
        
        if (empty($participant_ids) || empty($data)) {
            return false;
        }
        
        // Sanitizza dati
        $data = $this->sanitize_participant_data($data);
        
        // Prepara query
        $placeholders = implode(',', array_fill(0, count($participant_ids), '%d'));
        
        $set_clauses = array();
        $set_values = array();
        
        foreach ($data as $field => $value) {
            $set_clauses[] = "$field = %s";
            $set_values[] = $value;
        }
        
        $set_clause = implode(', ', $set_clauses);
        
        $query = "UPDATE {$this->table_name} SET $set_clause WHERE id IN ($placeholders)";
        $query_params = array_merge($set_values, $participant_ids);
        
        $result = $wpdb->query($wpdb->prepare($query, $query_params));
        
        if ($result !== false) {
            do_action('amacarun_participants_bulk_updated', $participant_ids, $data);
            amacarun_log("Bulk update completed: " . count($participant_ids) . " participants");
            return $result;
        }
        
        return false;
    }
}