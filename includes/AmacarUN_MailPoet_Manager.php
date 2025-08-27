<?php
/**
 * Integrazione MailPoet AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per l'integrazione con MailPoet
 */
class AmacarUN_MailPoet_Manager {
    
    /**
     * API MailPoet
     */
    private $mailpoet_api;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->init_mailpoet_api();
        $this->init_hooks();
    }
    
    /**
     * Inizializza API MailPoet
     */
    private function init_mailpoet_api() {
        if (class_exists('\MailPoet\API\API')) {
            try {
                $this->mailpoet_api = \MailPoet\API\API::MP('v1');
            } catch (Exception $e) {
                amacarun_log('MailPoet API initialization failed: ' . $e->getMessage(), 'error');
                $this->mailpoet_api = null;
            }
        }
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        // Hook per eventi del plugin
        add_action('amacarun_participant_created', array($this, 'auto_subscribe_participant'), 10, 2);
        add_action('amacarun_participant_checked_in', array($this, 'handle_checkin_event'), 10, 2);
        add_action('amacarun_event_completed', array($this, 'handle_event_completion'), 10, 1);
    }
    
    /**
     * Verifica se MailPoet è disponibile
     */
    public function is_mailpoet_active() {
        return !is_null($this->mailpoet_api) && class_exists('\MailPoet\API\API');
    }
    
    /**
     * Ottiene tutte le liste MailPoet disponibili
     */
    public function get_mailpoet_lists() {
        if (!$this->is_mailpoet_active()) {
            return array();
        }
        
        try {
            $lists = $this->mailpoet_api->getLists();
            return is_array($lists) ? $lists : array();
        } catch (Exception $e) {
            amacarun_log('Error fetching MailPoet lists: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Ottiene segmenti MailPoet
     */
    public function get_mailpoet_segments() {
        if (!$this->is_mailpoet_active()) {
            return array();
        }
        
        try {
            $segments = $this->mailpoet_api->getSegments();
            return is_array($segments) ? $segments : array();
        } catch (Exception $e) {
            amacarun_log('Error fetching MailPoet segments: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Iscrive partecipante alla mailing list
     */
    public function subscribe_participant($participant_id, $event_id = null) {
        if (!$this->is_mailpoet_active()) {
            return new WP_Error('mailpoet_inactive', 'MailPoet non è attivo');
        }
        
        global $wpdb;
        
        // Recupera dati partecipante con evento
        $participant = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, e.mailpoet_list_id, e.mailpoet_auto_subscribe, e.mailpoet_double_optin, e.name as event_name
             FROM " . AMACARUN_PARTICIPANTS_TABLE . " p
             JOIN " . AMACARUN_EVENTS_TABLE . " e ON p.event_id = e.id
             WHERE p.id = %d",
            $participant_id
        ));
        
        if (!$participant) {
            return new WP_Error('participant_not_found', 'Partecipante non trovato');
        }
        
        // Verifica se auto-iscrizione è abilitata
        if (!$participant->mailpoet_auto_subscribe) {
            return new WP_Error('auto_subscribe_disabled', 'Auto-iscrizione disabilitata per questo evento');
        }
        
        // Verifica se lista è configurata
        if (!$participant->mailpoet_list_id) {
            return new WP_Error('no_list_configured', 'Nessuna lista MailPoet configurata per questo evento');
        }
        
        // Verifica se già iscritto
        if ($participant->mailpoet_subscribed) {
            return new WP_Error('already_subscribed', 'Partecipante già iscritto');
        }
        
        try {
            // Prepara dati subscriber
            $subscriber_data = array(
                'email' => $participant->email,
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name
            );
            
            // Aggiungi campi personalizzati se esistono
            $custom_fields = $this->prepare_custom_fields($participant);
            $subscriber_data = array_merge($subscriber_data, $custom_fields);
            
            // Opzioni iscrizione
            $subscription_options = array(
                'send_confirmation_email' => (bool) $participant->mailpoet_double_optin,
                'schedule_welcome_email' => false, // Gestiremo manualmente
                'skip_subscriber_notification' => false
            );
            
            // Crea o aggiorna subscriber
            $subscriber = $this->mailpoet_api->addSubscriber(
                $subscriber_data, 
                array($participant->mailpoet_list_id), 
                $subscription_options
            );
            
            if ($subscriber && isset($subscriber['id'])) {
                // Aggiorna database
                $wpdb->update(
                    AMACARUN_PARTICIPANTS_TABLE,
                    array(
                        'mailpoet_subscriber_id' => $subscriber['id'],
                        'mailpoet_subscribed' => 1
                    ),
                    array('id' => $participant_id),
                    array('%d', '%d'),
                    array('%d')
                );
                
                do_action('amacarun_mailpoet_subscribed', $participant_id, $subscriber['id']);
                
                amacarun_log("MailPoet subscription successful: Participant $participant_id, Subscriber {$subscriber['id']}");
                
                return $subscriber;
            }
            
            return new WP_Error('subscription_failed', 'Errore nella creazione del subscriber');
            
        } catch (Exception $e) {
            amacarun_log('MailPoet subscription error: ' . $e->getMessage(), 'error');
            return new WP_Error('mailpoet_error', $e->getMessage());
        }
    }
    
    /**
     * Prepara campi personalizzati per MailPoet
     */
    private function prepare_custom_fields($participant) {
        $custom_fields = array();
        
        // Campi standard che vogliamo inviare a MailPoet
        $field_mapping = array(
            'participant_type' => $participant->participant_type === 'adult' ? 'Adulto' : 'Bambino',
            'event_name' => $participant->event_name,
            'bib_number' => $participant->bib_number,
            'registration_date' => $participant->created_at,
            'phone' => $participant->phone,
            'association_member' => $participant->association_member ? 'Sì' : 'No'
        );
        
        // Verifica quali campi personalizzati esistono in MailPoet
        $available_fields = $this->get_custom_fields();
        
        foreach ($field_mapping as $field_id => $value) {
            if (isset($available_fields[$field_id]) && !empty($value)) {
                $custom_fields[$field_id] = $value;
            }
        }
        
        return apply_filters('amacarun_mailpoet_custom_fields', $custom_fields, $participant);
    }
    
    /**
     * Ottiene campi personalizzati MailPoet
     */
    public function get_custom_fields() {
        if (!$this->is_mailpoet_active()) {
            return array();
        }
        
        static $fields = null;
        
        if ($fields === null) {
            try {
                $fields_list = $this->mailpoet_api->getSubscriberFields();
                $fields = array();
                
                foreach ($fields_list as $field) {
                    $fields[$field['id']] = $field;
                }
            } catch (Exception $e) {
                amacarun_log('Error fetching MailPoet custom fields: ' . $e->getMessage(), 'error');
                $fields = array();
            }
        }
        
        return $fields;
    }
    
    /**
     * Crea campi personalizzati necessari in MailPoet
     */
    public function create_custom_fields() {
        if (!$this->is_mailpoet_active()) {
            return false;
        }
        
        $fields_to_create = array(
            array(
                'id' => 'participant_type',
                'name' => 'Tipo Partecipante',
                'type' => 'text'
            ),
            array(
                'id' => 'event_name',
                'name' => 'Nome Evento',
                'type' => 'text'
            ),
            array(
                'id' => 'bib_number',
                'name' => 'Numero Pettorale',
                'type' => 'text'
            ),
            array(
                'id' => 'registration_date',
                'name' => 'Data Iscrizione',
                'type' => 'date'
            ),
            array(
                'id' => 'association_member',
                'name' => 'Membro Associazione',
                'type' => 'text'
            )
        );
        
        $created = 0;
        $existing_fields = $this->get_custom_fields();
        
        foreach ($fields_to_create as $field) {
            if (!isset($existing_fields[$field['id']])) {
                try {
                    $this->mailpoet_api->createCustomField($field);
                    $created++;
                    amacarun_log("Created MailPoet custom field: {$field['name']}");
                } catch (Exception $e) {
                    amacarun_log("Error creating MailPoet custom field {$field['name']}: " . $e->getMessage(), 'error');
                }
            }
        }
        
        return $created;
    }
    
    /**
     * Iscrizione bulk di tutti i partecipanti di un evento
     */
    public function bulk_subscribe_event($event_id) {
        global $wpdb;
        
        if (!$this->is_mailpoet_active()) {
            return new WP_Error('mailpoet_inactive', 'MailPoet non è attivo');
        }
        
        // Ottieni partecipanti non ancora iscritti
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND mailpoet_subscribed = 0 AND email != ''",
            $event_id
        ));
        
        $subscribed = 0;
        $errors = array();
        
        foreach ($participants as $participant) {
            $result = $this->subscribe_participant($participant->id);
            
            if (is_wp_error($result)) {
                $errors[] = "Participant {$participant->id}: " . $result->get_error_message();
            } else {
                $subscribed++;
            }
        }
        
        amacarun_log("Bulk MailPoet subscription completed: $subscribed subscribed, " . count($errors) . " errors");
        
        return array(
            'subscribed' => $subscribed,
            'errors' => $errors
        );
    }
    
    /**
     * Disiscrive partecipante
     */
    public function unsubscribe_participant($participant_id) {
        if (!$this->is_mailpoet_active()) {
            return new WP_Error('mailpoet_inactive', 'MailPoet non è attivo');
        }
        
        global $wpdb;
        
        $participant = $wpdb->get_row($wpdb->prepare(
            "SELECT mailpoet_subscriber_id, email FROM " . AMACARUN_PARTICIPANTS_TABLE . " WHERE id = %d",
            $participant_id
        ));
        
        if (!$participant || !$participant->mailpoet_subscriber_id) {
            return new WP_Error('not_subscribed', 'Partecipante non iscritto a MailPoet');
        }
        
        try {
            $this->mailpoet_api->unsubscribeSubscriber($participant->mailpoet_subscriber_id);
            
            // Aggiorna database
            $wpdb->update(
                AMACARUN_PARTICIPANTS_TABLE,
                array('mailpoet_subscribed' => 0),
                array('id' => $participant_id),
                array('%d'),
                array('%d')
            );
            
            do_action('amacarun_mailpoet_unsubscribed', $participant_id);
            
            amacarun_log("MailPoet unsubscription successful: Participant $participant_id");
            
            return true;
            
        } catch (Exception $e) {
            amacarun_log('MailPoet unsubscription error: ' . $e->getMessage(), 'error');
            return new WP_Error('mailpoet_error', $e->getMessage());
        }
    }
    
    /**
     * Crea segmento MailPoet per evento
     */
    public function create_event_segment($event_id, $segment_name) {
        if (!$this->is_mailpoet_active()) {
            return new WP_Error('mailpoet_inactive', 'MailPoet non è attivo');
        }
        
        try {
            $segment = $this->mailpoet_api->addSegment(array(
                'name' => $segment_name,
                'description' => 'Segmento automatico per partecipanti AmacarUN - ' . $segment_name,
                'type' => 'default'
            ));
            
            if ($segment && isset($segment['id'])) {
                // Aggiungi tutti i partecipanti iscritti dell'evento al segmento
                $this->add_event_participants_to_segment($event_id, $segment['id']);
                
                amacarun_log("MailPoet segment created: {$segment['name']} (ID: {$segment['id']})");
                
                return $segment;
            }
            
            return new WP_Error('segment_creation_failed', 'Errore nella creazione del segmento');
            
        } catch (Exception $e) {
            amacarun_log('MailPoet segment creation error: ' . $e->getMessage(), 'error');
            return new WP_Error('mailpoet_error', $e->getMessage());
        }
    }
    
    /**
     * Aggiunge partecipanti evento a segmento
     */
    private function add_event_participants_to_segment($event_id, $segment_id) {
        global $wpdb;
        
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT mailpoet_subscriber_id FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND mailpoet_subscribed = 1 AND mailpoet_subscriber_id IS NOT NULL",
            $event_id
        ));
        
        $added = 0;
        
        foreach ($participants as $participant) {
            try {
                $this->mailpoet_api->subscribeToSegment($segment_id, $participant->mailpoet_subscriber_id);
                $added++;
            } catch (Exception $e) {
                amacarun_log("Error adding subscriber {$participant->mailpoet_subscriber_id} to segment $segment_id: " . $e->getMessage(), 'error');
            }
        }
        
        amacarun_log("Added $added participants to MailPoet segment $segment_id");
        
        return $added;
    }
    
    /**
     * Sincronizza stato iscrizioni MailPoet
     */
    public function sync_subscription_status($event_id) {
        if (!$this->is_mailpoet_active()) {
            return new WP_Error('mailpoet_inactive', 'MailPoet non è attivo');
        }
        
        global $wpdb;
        
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT id, email, mailpoet_subscriber_id FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND email != ''",
            $event_id
        ));
        
        $updated = 0;
        
        foreach ($participants as $participant) {
            try {
                // Verifica stato in MailPoet
                $subscriber = $this->mailpoet_api->getSubscriber($participant->email);
                
                $is_subscribed = 0;
                $subscriber_id = $participant->mailpoet_subscriber_id;
                
                if ($subscriber && isset($subscriber['status']) && $subscriber['status'] === 'subscribed') {
                    $is_subscribed = 1;
                    $subscriber_id = $subscriber['id'];
                }
                
                // Aggiorna database se necessario
                if ($is_subscribed != $participant->mailpoet_subscribed || 
                    $subscriber_id != $participant->mailpoet_subscriber_id) {
                    
                    $wpdb->update(
                        AMACARUN_PARTICIPANTS_TABLE,
                        array(
                            'mailpoet_subscribed' => $is_subscribed,
                            'mailpoet_subscriber_id' => $subscriber_id
                        ),
                        array('id' => $participant->id),
                        array('%d', '%d'),
                        array('%d')
                    );
                    
                    $updated++;
                }
                
            } catch (Exception $e) {
                // Subscriber non trovato o altro errore - considera non iscritto
                if ($participant->mailpoet_subscribed) {
                    $wpdb->update(
                        AMACARUN_PARTICIPANTS_TABLE,
                        array('mailpoet_subscribed' => 0),
                        array('id' => $participant->id),
                        array('%d'),
                        array('%d')
                    );
                    $updated++;
                }
            }
        }
        
        amacarun_log("MailPoet sync completed: $updated participants updated");
        
        return $updated;
    }
    
    /**
     * Invia email personalizzata a partecipanti
     */
    public function send_custom_email($participant_ids, $subject, $content, $template_id = null) {
        if (!$this->is_mailpoet_active()) {
            return new WP_Error('mailpoet_inactive', 'MailPoet non è attivo');
        }
        
        global $wpdb;
        
        // Ottieni email dei partecipanti
        $placeholders = implode(',', array_fill(0, count($participant_ids), '%d'));
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT email, first_name, last_name, mailpoet_subscriber_id 
             FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE id IN ($placeholders) AND mailpoet_subscribed = 1",
            $participant_ids
        ));
        
        if (empty($participants)) {
            return new WP_Error('no_recipients', 'Nessun destinatario iscritto trovato');
        }
        
        try {
            // Crea newsletter
            $newsletter = $this->mailpoet_api->createNewsletter(array(
                'subject' => $subject,
                'type' => 'standard',
                'body' => array(
                    'html' => $content,
                    'text' => strip_tags($content)
                )
            ));
            
            if (!$newsletter || !isset($newsletter['id'])) {
                return new WP_Error('newsletter_creation_failed', 'Errore nella creazione della newsletter');
            }
            
            // Invia newsletter
            $sending_task = $this->mailpoet_api->sendNewsletter($newsletter['id']);
            
            amacarun_log("Custom email sent to " . count($participants) . " participants");
            
            return array(
                'newsletter_id' => $newsletter['id'],
                'recipients' => count($participants),
                'status' => 'sent'
            );
            
        } catch (Exception $e) {
            amacarun_log('Custom email sending error: ' . $e->getMessage(), 'error');
            return new WP_Error('mailpoet_error', $e->getMessage());
        }
    }
    
    /**
     * Auto-iscrizione partecipante (hook)
     */
    public function auto_subscribe_participant($participant_id, $data) {
        // Iscrivi solo se configurato per l'evento
        $this->subscribe_participant($participant_id);
    }
    
    /**
     * Gestisce evento check-in (hook)
     */
    public function handle_checkin_event($participant_id, $distance) {
        // Invia email di check-in se configurato
        $send_checkin_email = get_option('amacarun_mailpoet_send_checkin_email', false);
        
        if ($send_checkin_email) {
            $this->send_checkin_confirmation($participant_id);
        }
    }
    
    /**
     * Invia email conferma check-in
     */
    private function send_checkin_confirmation($participant_id) {
        global $wpdb;
        
        $participant = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, e.name as event_name 
             FROM " . AMACARUN_PARTICIPANTS_TABLE . " p
             JOIN " . AMACARUN_EVENTS_TABLE . " e ON p.event_id = e.id
             WHERE p.id = %d",
            $participant_id
        ));
        
        if (!$participant || !$participant->mailpoet_subscribed) {
            return;
        }
        
        $subject = sprintf('Check-in confermato - %s', $participant->event_name);
        $content = sprintf(
            'Ciao %s,<br><br>Il tuo check-in per %s è stato confermato!<br><br>Pettorale: #%s<br>Distanza: %s<br><br>Buona gara!',
            $participant->first_name,
            $participant->event_name,
            $participant->bib_number ?: 'Da assegnare',
            $participant->distance ?: 'Da scegliere'
        );
        
        $this->send_custom_email(array($participant_id), $subject, $content);
    }
    
    /**
     * Gestisce completamento evento (hook)
     */
    public function handle_event_completion($event_id) {
        // Invia email di ringraziamento se configurato
        $send_completion_email = get_option('amacarun_mailpoet_send_completion_email', false);
        
        if ($send_completion_email) {
            $this->send_event_completion_email($event_id);
        }
    }
    
    /**
     * Invia email completamento evento
     */
    private function send_event_completion_email($event_id) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . AMACARUN_EVENTS_TABLE . " WHERE id = %d",
            $event_id
        ));
        
        $participants = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND mailpoet_subscribed = 1",
            $event_id
        ));
        
        if (empty($participants)) {
            return;
        }
        
        $subject = sprintf('Grazie per aver partecipato a %s!', $event->name);
        $content = sprintf(
            'Ciao!<br><br>Grazie per aver partecipato a %s.<br><br>Ci vediamo alla prossima edizione!<br><br>Il team AmacarUN',
            $event->name
        );
        
        $this->send_custom_email($participants, $subject, $content);
    }
    
    /**
     * Ottiene statistiche MailPoet per evento
     */
    public function get_mailpoet_stats($event_id) {
        global $wpdb;
        
        $stats = array();
        
        // Totale partecipanti
        $stats['total_participants'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " WHERE event_id = %d",
            $event_id
        ));
        
        // Iscritti MailPoet
        $stats['subscribed'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND mailpoet_subscribed = 1",
            $event_id
        ));
        
        // Non iscritti
        $stats['not_subscribed'] = $stats['total_participants'] - $stats['subscribed'];
        
        // Percentuale iscrizione
        $stats['subscription_rate'] = $stats['total_participants'] > 0 
            ? round(($stats['subscribed'] / $stats['total_participants']) * 100, 2) 
            : 0;
        
        // Con email valida
        $stats['with_email'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND email != '' AND email IS NOT NULL",
            $event_id
        ));
        
        return $stats;
    }
    
    /**
     * Test connessione MailPoet
     */
    public function test_mailpoet_connection() {
        if (!$this->is_mailpoet_active()) {
            return array('status' => 'error', 'message' => 'MailPoet non è attivo');
        }
        
        try {
            $lists = $this->get_mailpoet_lists();
            $segments = $this->get_mailpoet_segments();
            
            return array(
                'status' => 'success', 
                'message' => sprintf('Connessione OK. Liste: %d, Segmenti: %d', count($lists), count($segments))
            );
        } catch (Exception $e) {
            return array('status' => 'error', 'message' => 'Errore connessione: ' . $e->getMessage());
        }
    }
}