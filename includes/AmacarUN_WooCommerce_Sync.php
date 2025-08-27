<?php
/**
 * Sincronizzazione WooCommerce AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per la sincronizzazione con WooCommerce
 */
class AmacarUN_WooCommerce_Sync {
    
    /**
     * Manager instances
     */
    private $participant_manager;
    private $bib_manager;
    private $mailpoet_manager;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->participant_manager = new AmacarUN_Participant_Manager();
        $this->bib_manager = new AmacarUN_Bib_Manager();
        
        // MailPoet sarà inizializzato solo se disponibile
        if (class_exists('AmacarUN_MailPoet_Manager')) {
            $this->mailpoet_manager = new AmacarUN_MailPoet_Manager();
        }
        
        $this->init_hooks();
    }
    
    /**
     * Inizializza hook WooCommerce
     */
    private function init_hooks() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Hook per nuovi ordini
        add_action('woocommerce_order_status_completed', array($this, 'handle_completed_order'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'handle_processing_order'), 10, 1);
        
        // Hook per modifiche ordini
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Hook per cancellazioni
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_cancelled_order'), 10, 1);
        add_action('woocommerce_order_status_refunded', array($this, 'handle_refunded_order'), 10, 1);
    }
    
    /**
     * Sincronizza tutti i partecipanti di un evento
     */
    public function sync_participants($event_id) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_missing', 'WooCommerce non è attivo');
        }
        
        $event = $this->get_event($event_id);
        if (!$event) {
            return new WP_Error('event_not_found', 'Evento non trovato');
        }
        
        amacarun_log("Starting WooCommerce sync for event $event_id");
        
        // Ottieni ordini completati per la categoria
        $orders = $this->get_orders_for_sync($event);
        
        $synced = 0;
        $errors = array();
        
        foreach ($orders as $order_id) {
            $result = $this->sync_single_order($order_id, $event_id);
            
            if (is_wp_error($result)) {
                $errors[] = "Order $order_id: " . $result->get_error_message();
            } else {
                $synced += $result;
            }
        }
        
        // Aggiorna timestamp ultima sincronizzazione
        update_option('amacarun_last_sync_time', current_time('mysql'));
        
        amacarun_log("WooCommerce sync completed: $synced participants synced, " . count($errors) . " errors");
        
        return array(
            'synced' => $synced,
            'errors' => $errors
        );
    }
    
    /**
     * Sincronizza singolo ordine
     */
    public function sync_single_order($order_id, $event_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || !$this->is_order_eligible($order)) {
            return new WP_Error('ineligible_order', 'Ordine non idoneo per sincronizzazione');
        }
        
        $participants_data = $this->extract_participants_from_order($order);
        
        if (empty($participants_data)) {
            return new WP_Error('no_participants', 'Nessun partecipante trovato nell\'ordine');
        }
        
        $synced = 0;
        
        foreach ($participants_data as $participant_data) {
            $participant_data['event_id'] = $event_id;
            $participant_data['woocommerce_order_id'] = $order_id;
            
            // Verifica se partecipante già exists
            if (!$this->participant_exists($participant_data['email'], $event_id, $order_id)) {
                $participant_id = $this->participant_manager->create_participant($participant_data);
                
                if (!is_wp_error($participant_id)) {
                    // Assegna pettorale automaticamente
                    $this->bib_manager->assign_next_sequential_bib($participant_id, $event_id);
                    
                    // Iscrivi a MailPoet se configurato
                    if ($this->mailpoet_manager) {
                        $this->mailpoet_manager->subscribe_participant($participant_id);
                    }
                    
                    $synced++;
                    
                    do_action('amacarun_woocommerce_participant_synced', $participant_id, $order_id);
                }
            }
        }
        
        return $synced;
    }
    
    /**
     * Estrae dati partecipanti da ordine WooCommerce
     */
    private function extract_participants_from_order($order) {
        $participants = array();
        $order_items = $order->get_items();
        
        foreach ($order_items as $item_id => $item) {
            $product = $item->get_product();
            
            if (!$product || !$this->is_amacarun_product($product)) {
                continue;
            }
            
            // Determina tipo partecipante dal prodotto
            $participant_type = $this->determine_participant_type($product);
            
            // Estrai campi personalizzati Advanced Product Fields
            $custom_fields = $this->extract_custom_fields($item);
            
            if (!empty($custom_fields)) {
                // Se ci sono più partecipanti nello stesso item (es. famiglia)
                $participants_in_item = $this->parse_multiple_participants($custom_fields, $participant_type);
                
                foreach ($participants_in_item as $participant_data) {
                    $participant_data['woocommerce_item_id'] = $item_id;
                    $participant_data['registration_type'] = 'online';
                    $participant_data['payment_method'] = $order->get_payment_method();
                    $participant_data['payment_amount'] = $item->get_total() / count($participants_in_item);
                    
                    $participants[] = $participant_data;
                }
            }
        }
        
        return $participants;
    }
    
    /**
     * Estrae campi personalizzati da Advanced Product Fields
     */
    private function extract_custom_fields($item) {
        $custom_fields = array();
        $item_meta = $item->get_meta_data();
        
        foreach ($item_meta as $meta) {
            $key = $meta->key;
            $value = $meta->value;
            
            // Verifica se è un campo Advanced Product Fields
            if (strpos($key, '_thwcfd_') === 0 || strpos($key, 'thwcfd_') === 0) {
                // Rimuovi prefisso per ottenere nome campo pulito
                $clean_key = str_replace(array('_thwcfd_', 'thwcfd_'), '', $key);
                $custom_fields[$clean_key] = $value;
            }
        }
        
        return $custom_fields;
    }
    
    /**
     * Parsifica più partecipanti dai campi personalizzati
     */
    private function parse_multiple_participants($custom_fields, $participant_type) {
        $participants = array();
        
        // Mappi campi comuni
        $field_mapping = array(
            'nome' => 'first_name',
            'cognome' => 'last_name', 
            'email' => 'email',
            'telefono' => 'phone',
            'associazione' => 'association_member'
        );
        
        // Controlla se ci sono campi multipli (es. nome_1, nome_2)
        $max_participants = $this->detect_max_participants($custom_fields);
        
        for ($i = 1; $i <= $max_participants; $i++) {
            $participant_data = array(
                'participant_type' => $participant_type
            );
            
            $has_required_fields = false;
            
            foreach ($field_mapping as $original_field => $mapped_field) {
                $field_key = $max_participants > 1 ? $original_field . '_' . $i : $original_field;
                
                if (isset($custom_fields[$field_key]) && !empty($custom_fields[$field_key])) {
                    $value = $custom_fields[$field_key];
                    
                    // Gestione campo associazione (checkbox)
                    if ($mapped_field === 'association_member') {
                        $participant_data[$mapped_field] = ($value === 'yes' || $value === '1' || $value === 'on') ? 1 : 0;
                    } else {
                        $participant_data[$mapped_field] = sanitize_text_field($value);
                    }
                    
                    // Controlla campi obbligatori
                    if (in_array($mapped_field, array('first_name', 'last_name', 'email'))) {
                        $has_required_fields = true;
                    }
                }
            }
            
            // Aggiungi partecipante solo se ha i campi richiesti
            if ($has_required_fields && !empty($participant_data['first_name']) && !empty($participant_data['last_name'])) {
                $participants[] = $participant_data;
            }
        }
        
        // Se non trovati partecipanti multipli, prova con campi singoli
        if (empty($participants) && $max_participants === 1) {
            $participant_data = array('participant_type' => $participant_type);
            
            foreach ($field_mapping as $original_field => $mapped_field) {
                if (isset($custom_fields[$original_field])) {
                    if ($mapped_field === 'association_member') {
                        $participant_data[$mapped_field] = ($custom_fields[$original_field] === 'yes' || $custom_fields[$original_field] === '1') ? 1 : 0;
                    } else {
                        $participant_data[$mapped_field] = sanitize_text_field($custom_fields[$original_field]);
                    }
                }
            }
            
            if (!empty($participant_data['first_name']) && !empty($participant_data['last_name'])) {
                $participants[] = $participant_data;
            }
        }
        
        return $participants;
    }
    
    /**
     * Rileva numero massimo di partecipanti nei campi
     */
    private function detect_max_participants($custom_fields) {
        $max = 1;
        
        foreach (array_keys($custom_fields) as $field_name) {
            if (preg_match('/_(\d+)$/', $field_name, $matches)) {
                $num = intval($matches[1]);
                if ($num > $max) {
                    $max = $num;
                }
            }
        }
        
        return $max;
    }
    
    /**
     * Determina tipo partecipante dal prodotto
     */
    private function determine_participant_type($product) {
        $product_name = strtolower($product->get_name());
        $product_slug = $product->get_slug();
        
        // Cerca keywords per bambini
        $child_keywords = array('bambino', 'bambina', 'child', 'kid', 'junior');
        
        foreach ($child_keywords as $keyword) {
            if (strpos($product_name, $keyword) !== false || strpos($product_slug, $keyword) !== false) {
                return 'child';
            }
        }
        
        return 'adult';
    }
    
    /**
     * Verifica se prodotto appartiene alla categoria AmacarUN
     */
    private function is_amacarun_product($product) {
        $amacarun_category_id = get_option('amacarun_woocommerce_category_id', 29);
        
        if (!$amacarun_category_id) {
            return false;
        }
        
        $product_categories = $product->get_category_ids();
        
        return in_array($amacarun_category_id, $product_categories);
    }
    
    /**
     * Verifica se ordine è idoneo per sincronizzazione
     */
    private function is_order_eligible($order) {
        // Deve essere completato o in elaborazione
        $eligible_statuses = array('completed', 'processing');
        if (!in_array($order->get_status(), $eligible_statuses)) {
            return false;
        }
        
        // Deve contenere almeno un prodotto AmacarUN
        $items = $order->get_items();
        foreach ($items as $item) {
            $product = $item->get_product();
            if ($product && $this->is_amacarun_product($product)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se partecipante già exists
     */
    private function participant_exists($email, $event_id, $order_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . "
             WHERE email = %s AND event_id = %d AND woocommerce_order_id = %d",
            $email, $event_id, $order_id
        ));
        
        return $exists > 0;
    }
    
    /**
     * Ottiene ordini per sincronizzazione
     */
    private function get_orders_for_sync($event) {
        $args = array(
            'status' => array('completed', 'processing'),
            'limit' => -1,
            'type' => 'shop_order'
        );
        
        // Filtra per data se specificata
        if (!empty($event->date)) {
            $event_date = new DateTime($event->date);
            $start_date = $event_date->modify('-6 months')->format('Y-m-d');
            $end_date = $event_date->modify('+1 day')->format('Y-m-d');
            
            $args['date_created'] = $start_date . '...' . $end_date;
        }
        
        $orders = wc_get_orders($args);
        $order_ids = array();
        
        foreach ($orders as $order) {
            if ($this->is_order_eligible($order)) {
                $order_ids[] = $order->get_id();
            }
        }
        
        return $order_ids;
    }
    
    /**
     * Ottiene evento
     */
    private function get_event($event_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . AMACARUN_EVENTS_TABLE . " WHERE id = %d",
            $event_id
        ));
    }
    
    /**
     * Gestisce ordine completato
     */
    public function handle_completed_order($order_id) {
        $this->handle_order_change($order_id, 'completed');
    }
    
    /**
     * Gestisce ordine in elaborazione
     */
    public function handle_processing_order($order_id) {
        $this->handle_order_change($order_id, 'processing');
    }
    
    /**
     * Gestisce cambio stato ordine
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        if (in_array($new_status, array('completed', 'processing'))) {
            $this->handle_order_change($order_id, $new_status);
        } elseif (in_array($new_status, array('cancelled', 'refunded'))) {
            $this->handle_order_removal($order_id);
        }
    }
    
    /**
     * Gestisce ordine cancellato
     */
    public function handle_cancelled_order($order_id) {
        $this->handle_order_removal($order_id);
    }
    
    /**
     * Gestisce ordine rimborsato
     */
    public function handle_refunded_order($order_id) {
        $this->handle_order_removal($order_id);
    }
    
    /**
     * Gestisce cambio ordine (nuovo o aggiornato)
     */
    private function handle_order_change($order_id, $status) {
        $active_event = AmacarUN_Race_Manager::get_active_event();
        
        if (!$active_event) {
            amacarun_log("No active event for order sync: $order_id");
            return;
        }
        
        $result = $this->sync_single_order($order_id, $active_event->id);
        
        if (is_wp_error($result)) {
            amacarun_log("Auto sync failed for order $order_id: " . $result->get_error_message(), 'error');
        } else {
            amacarun_log("Auto sync completed for order $order_id: $result participants synced");
        }
    }
    
    /**
     * Gestisce rimozione ordine (cancellato/rimborsato)
     */
    private function handle_order_removal($order_id) {
        global $wpdb;
        
        // Trova partecipanti associati all'ordine
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name FROM " . AMACARUN_PARTICIPANTS_TABLE . "
             WHERE woocommerce_order_id = %d",
            $order_id
        ));
        
        $auto_remove = get_option('amacarun_auto_remove_cancelled_orders', false);
        
        if ($auto_remove) {
            // Rimuovi automaticamente
            foreach ($participants as $participant) {
                $this->participant_manager->delete_participant($participant->id);
                amacarun_log("Auto removed participant {$participant->id} for cancelled order $order_id");
            }
        } else {
            // Marca come ritirato o aggiungi nota
            foreach ($participants as $participant) {
                $this->participant_manager->update_participant($participant->id, array(
                    'notes' => 'Ordine WooCommerce cancellato/rimborsato'
                ));
            }
            
            amacarun_log("Marked participants for cancelled order $order_id");
        }
    }
    
    /**
     * Sincronizzazione manuale con progress
     */
    public function manual_sync_with_progress($event_id) {
        $orders = $this->get_orders_for_sync($this->get_event($event_id));
        $total_orders = count($orders);
        
        if ($total_orders === 0) {
            return array('total' => 0, 'synced' => 0, 'errors' => array());
        }
        
        $progress_key = 'amacarun_sync_progress_' . $event_id;
        $batch_size = 10; // Processa 10 ordini per volta
        
        set_transient($progress_key, array(
            'total' => $total_orders,
            'processed' => 0,
            'synced' => 0,
            'errors' => array(),
            'status' => 'running'
        ), 300);
        
        $synced_total = 0;
        $errors_total = array();
        
        for ($i = 0; $i < $total_orders; $i += $batch_size) {
            $batch = array_slice($orders, $i, $batch_size);
            
            foreach ($batch as $order_id) {
                $result = $this->sync_single_order($order_id, $event_id);
                
                if (is_wp_error($result)) {
                    $errors_total[] = "Order $order_id: " . $result->get_error_message();
                } else {
                    $synced_total += $result;
                }
            }
            
            // Aggiorna progress
            $progress = get_transient($progress_key);
            if ($progress) {
                $progress['processed'] = min($i + $batch_size, $total_orders);
                $progress['synced'] = $synced_total;
                $progress['errors'] = $errors_total;
                set_transient($progress_key, $progress, 300);
            }
            
            // Pausa per evitare timeout
            if ($i + $batch_size < $total_orders) {
                usleep(100000); // 0.1 secondi
            }
        }
        
        // Finalizza progress
        set_transient($progress_key, array(
            'total' => $total_orders,
            'processed' => $total_orders,
            'synced' => $synced_total,
            'errors' => $errors_total,
            'status' => 'completed'
        ), 60);
        
        return array(
            'total' => $total_orders,
            'synced' => $synced_total,
            'errors' => $errors_total
        );
    }
    
    /**
     * Ottieni progress sincronizzazione
     */
    public function get_sync_progress($event_id) {
        $progress_key = 'amacarun_sync_progress_' . $event_id;
        return get_transient($progress_key);
    }
    
    /**
     * Test connessione WooCommerce
     */
    public function test_woocommerce_connection() {
        if (!class_exists('WooCommerce')) {
            return array('status' => 'error', 'message' => 'WooCommerce non è attivo');
        }
        
        $category_id = get_option('amacarun_woocommerce_category_id', 29);
        $category = get_term($category_id, 'product_cat');
        
        if (!$category || is_wp_error($category)) {
            return array('status' => 'warning', 'message' => 'Categoria AmacarUN non configurata correttamente');
        }
        
        // Conta prodotti nella categoria
        $products_count = wc_get_products(array(
            'category' => array($category->slug),
            'status' => 'publish',
            'return' => 'ids',
            'limit' => -1
        ));
        
        return array(
            'status' => 'success',
            'message' => "Connessione OK. Categoria: {$category->name}, Prodotti: " . count($products_count)
        );
    }
}