/**
     * Ottiene progress export
     */
    public function get_export_progress($event_id, $type) {
        $progress_key = 'amacarun_export_progress_' . $event_id . '_' . $type;
        return get_transient($progress_key);
    }
    
    /**
     * Costruisce argomenti query per filtri
     */
    private function build_query_args($options) {
        $args = array();
        
        if ($options['status'] !== 'all') {
            $args['status'] = $options['status'];
        }
        
        if ($options['type'] !== 'all') {
            $args['participant_type'] = $options['type'];
        }
        
        if ($options['distance'] !== 'all') {
            $args['distance'] = $options['distance'];
        }
        
        if ($options['has_bib'] === 'yes') {
            $args['has_bib'] = true;
        } elseif ($options['has_bib'] === 'no') {
            $args['has_bib'] = false;
        }
        
        return $args;
    }
    
    /**
     * Genera nome file
     */
    private function generate_filename($event_id, $type, $options = array()) {
        global $wpdb;
        
        $event_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM " . AMACARUN_EVENTS_TABLE . " WHERE id = %d",
            $event_id
        ));
        
        $safe_event_name = sanitize_file_name($event_name ?: "evento_$event_id");
        $timestamp = date('Y-m-d_H-i-s');
        
        $extension = 'csv';
        if (strpos($type, 'html') !== false || $type === 'labels') {
            $extension = 'html';
        } elseif (strpos($type, 'json') !== false) {
            $extension = 'json';
        }
        
        $filename = "amacarun_{$safe_event_name}_{$type}_{$timestamp}.{$extension}";
        
        return $filename;
    }
    
    /**
     * Crea file HTML
     */
    private function create_html_file($content, $filename) {
        $upload_dir = wp_upload_dir();
        $amacarun_dir = $upload_dir['basedir'] . '/amacarun-exports/';
        
        if (!is_dir($amacarun_dir)) {
            wp_mkdir_p($amacarun_dir);
            file_put_contents($amacarun_dir . '.htaccess', "deny from all\n");
            file_put_contents($amacarun_dir . 'index.php', '<?php // Silence is golden');
        }
        
        $file_path = $amacarun_dir . $filename;
        
        if (file_put_contents($file_path, $content) !== false) {
            return $file_path;
        }
        
        return false;
    }
    
    /**
     * Crea file di testo generico
     */
    private function create_text_file($content, $filename) {
        $upload_dir = wp_upload_dir();
        $amacarun_dir = $upload_dir['basedir'] . '/amacarun-exports/';
        
        if (!is_dir($amacarun_dir)) {
            wp_mkdir_p($amacarun_dir);
            file_put_contents($amacarun_dir . '.htaccess', "deny from all\n");
            file_put_contents($amacarun_dir . 'index.php', '<?php // Silence is golden');
        }
        
        $file_path = $amacarun_dir . $filename;
        
        if (file_put_contents($file_path, $content) !== false) {
            return $file_path;
        }
        
        return false;
    }
    
    /**
     * Ottiene URL download
     */
    private function get_download_url($filename) {
        return admin_url('admin.php?page=amacarun-race-manager&action=download&file=' . urlencode($filename) . '&nonce=' . wp_create_nonce('amacarun_download'));
    }
    
    /**
     * Gestisce download file
     */
    public function handle_file_download() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'download') {
            return;
        }
        
        if (!isset($_GET['file']) || !isset($_GET['nonce'])) {
            wp_die('Parametri mancanti');
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'amacarun_download')) {
            wp_die('Nonce non valido');
        }
        
        if (!current_user_can('export_amacarun_data')) {
            wp_die('Permessi insufficienti');
        }
        
        $filename = sanitize_file_name($_GET['file']);
        $upload_dir = wp_upload_dir();<?php
/**
 * Export Manager AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per l'export dei dati
 */
class AmacarUN_Export_Manager {
    
    /**
     * Manager instances
     */
    private $participant_manager;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->participant_manager = new AmacarUN_Participant_Manager();
        $this->init_hooks();
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        add_action('wp_ajax_amacarun_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_amacarun_export_labels', array($this, 'ajax_export_labels'));
        add_action('wp_ajax_amacarun_export_progress', array($this, 'ajax_get_export_progress'));
    }
    
    /**
     * Esporta partecipanti in formato CSV
     */
    public function export_participants_csv($event_id, $options = array()) {
        $defaults = array(
            'status' => 'all', // all, registered, checked_in, retired
            'type' => 'all', // all, adult, child
            'distance' => 'all', // all, 4km, 11km
            'has_bib' => 'all', // all, yes, no
            'format' => 'complete', // complete, basic, labels, checkin
            'delimiter' => ',',
            'encoding' => 'UTF-8'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Ottieni partecipanti con filtri
        $args = $this->build_query_args($options);
        $participants = $this->participant_manager->get_participants_by_event($event_id, $args);
        
        if (empty($participants)) {
            return new WP_Error('no_data', 'Nessun partecipante trovato con i filtri specificati');
        }
        
        // Genera dati CSV
        $csv_data = $this->generate_csv_data($participants, $options['format'], $event_id);
        
        // Crea file CSV
        $filename = $this->generate_filename($event_id, 'csv', $options);
        $file_path = $this->create_csv_file($csv_data, $filename, $options);
        
        if (!$file_path) {
            return new WP_Error('file_creation_failed', 'Errore nella creazione del file CSV');
        }
        
        amacarun_log("CSV export completed: $filename, " . count($participants) . " participants");
        
        return array(
            'file_path' => $file_path,
            'filename' => $filename,
            'url' => $this->get_download_url($filename),
            'count' => count($participants),
            'size' => filesize($file_path)
        );
    }
    
    /**
     * Genera dati CSV in base al formato
     */
    private function generate_csv_data($participants, $format, $event_id) {
        $csv_data = array();
        
        // Headers base in base al formato
        switch ($format) {
            case 'basic':
                $headers = array('Nome', 'Cognome', 'Email', 'Tipo', 'Stato');
                break;
                
            case 'labels':
                $headers = array('Pettorale', 'Nome Completo', 'Tipo', 'Distanza');
                break;
                
            case 'checkin':
                $headers = array('Pettorale', 'Cognome', 'Nome', 'Tipo', 'Check-in', 'Distanza', 'Note');
                break;
                
            default: // complete
                $headers = array(
                    'ID', 'Pettorale', 'Nome', 'Cognome', 'Email', 'Telefono', 
                    'Tipo', 'Membro Associazione', 'Distanza', 'Stato', 
                    'Check-in', 'Tipo Registrazione', 'Metodo Pagamento', 'Importo',
                    'MailPoet', 'Note', 'Data Iscrizione'
                );
        }
        
        $csv_data[] = $headers;
        
        // Ottieni nome evento per reference
        global $wpdb;
        $event_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM " . AMACARUN_EVENTS_TABLE . " WHERE id = %d",
            $event_id
        ));
        
        // Righe dati
        foreach ($participants as $participant) {
            switch ($format) {
                case 'basic':
                    $row = array(
                        $participant->first_name,
                        $participant->last_name,
                        $participant->email,
                        $participant->participant_type === 'adult' ? 'Adulto' : 'Bambino',
                        ucfirst($participant->status)
                    );
                    break;
                    
                case 'labels':
                    $row = array(
                        $participant->bib_number ?: 'N/A',
                        $participant->first_name . ' ' . $participant->last_name,
                        $participant->participant_type === 'adult' ? 'A' : 'B',
                        $participant->distance ?: 'N/A'
                    );
                    break;
                    
                case 'checkin':
                    $row = array(
                        $participant->bib_number ?: 'N/A',
                        $participant->last_name,
                        $participant->first_name,
                        $participant->participant_type === 'adult' ? 'Adulto' : 'Bambino',
                        $participant->status === 'checked_in' ? 'Sì' : 'No',
                        $participant->distance ?: 'N/A',
                        $participant->notes ?: ''
                    );
                    break;
                    
                default: // complete
                    $row = array(
                        $participant->id,
                        $participant->bib_number ?: 'N/A',
                        $participant->first_name,
                        $participant->last_name,
                        $participant->email,
                        $participant->phone ?: 'N/A',
                        $participant->participant_type === 'adult' ? 'Adulto' : 'Bambino',
                        $participant->association_member ? 'Sì' : 'No',
                        $participant->distance ?: 'N/A',
                        ucfirst($participant->status),
                        $participant->check_in_time ?: 'N/A',
                        ucfirst($participant->registration_type),
                        $participant->payment_method ?: 'N/A',
                        $participant->payment_amount ? '€' . number_format($participant->payment_amount, 2) : 'N/A',
                        $participant->mailpoet_subscribed ? 'Sì' : 'No',
                        $participant->notes ?: '',
                        $participant->created_at
                    );
            }
            
            $csv_data[] = $row;
        }
        
        return $csv_data;
    }
    
    /**
     * Crea file CSV fisico
     */
    private function create_csv_file($csv_data, $filename, $options) {
        $upload_dir = wp_upload_dir();
        $amacarun_dir = $upload_dir['basedir'] . '/amacarun-exports/';
        
        // Crea directory se non exists
        if (!is_dir($amacarun_dir)) {
            wp_mkdir_p($amacarun_dir);
            
            // Proteggi directory con .htaccess
            file_put_contents($amacarun_dir . '.htaccess', "deny from all\n");
            file_put_contents($amacarun_dir . 'index.php', '<?php // Silence is golden');
        }
        
        $file_path = $amacarun_dir . $filename;
        
        // Apri file per scrittura
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            return false;
        }
        
        // BOM per UTF-8 se necessario
        if ($options['encoding'] === 'UTF-8') {
            fwrite($handle, "\xEF\xBB\xBF");
        }
        
        // Scrivi righe CSV
        foreach ($csv_data as $row) {
            fputcsv($handle, $row, $options['delimiter']);
        }
        
        fclose($handle);
        
        return $file_path;
    }
    
    /**
     * Esporta etichette per stampa
     */
    public function export_labels($event_id, $options = array()) {
        $defaults = array(
            'format' => 'avery_l7163', // avery_l7163, custom
            'include_logo' => true,
            'include_qr' => false,
            'label_format' => 'bib_name' // bib_name, name_only, full_info
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Ottieni partecipanti con pettorali
        $participants = $this->participant_manager->get_participants_by_event($event_id, array(
            'has_bib' => true,
            'orderby' => 'bib_number'
        ));
        
        if (empty($participants)) {
            return new WP_Error('no_bibs', 'Nessun partecipante con pettorale trovato');
        }
        
        // Genera HTML per etichette
        $html = $this->generate_labels_html($participants, $options);
        
        // Salva file
        $filename = $this->generate_filename($event_id, 'labels', $options);
        $file_path = $this->create_html_file($html, $filename);
        
        if (!$file_path) {
            return new WP_Error('file_creation_failed', 'Errore nella creazione del file etichette');
        }
        
        amacarun_log("Labels export completed: $filename, " . count($participants) . " labels");
        
        return array(
            'file_path' => $file_path,
            'filename' => $filename,
            'url' => $this->get_download_url($filename),
            'count' => count($participants)
        );
    }
    
    /**
     * Genera HTML per etichette
     */
    private function generate_labels_html($participants, $options) {
        $css = $this->get_labels_css($options['format']);
        
        $html = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n<title>Etichette Pettorali</title>\n";
        $html .= "<style>$css</style>\n</head>\n<body>\n";
        
        $html .= "<div class='labels-container'>\n";
        
        foreach ($participants as $participant) {
            $html .= "<div class='label'>\n";
            
            switch ($options['label_format']) {
                case 'name_only':
                    $html .= "<div class='name'>{$participant->first_name} {$participant->last_name}</div>\n";
                    break;
                    
                case 'full_info':
                    $html .= "<div class='bib-number'>#{$participant->bib_number}</div>\n";
                    $html .= "<div class='name'>{$participant->first_name} {$participant->last_name}</div>\n";
                    $html .= "<div class='info'>";
                    $html .= ($participant->participant_type === 'adult' ? 'Adulto' : 'Bambino');
                    if ($participant->distance) {
                        $html .= " - {$participant->distance}";
                    }
                    $html .= "</div>\n";
                    break;
                    
                default: // bib_name
                    $html .= "<div class='bib-number'>#{$participant->bib_number}</div>\n";
                    $html .= "<div class='name'>{$participant->first_name} {$participant->last_name}</div>\n";
            }
            
            // QR Code se richiesto
            if ($options['include_qr']) {
                $qr_data = "amacarun:participant:{$participant->id}:bib:{$participant->bib_number}";
                $html .= "<div class='qr-code' data-qr='$qr_data'></div>\n";
            }
            
            $html .= "</div>\n";
        }
        
        $html .= "</div>\n";
        
        // Script per QR codes se necessario
        if ($options['include_qr']) {
            $html .= "<script src='https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js'></script>\n";
            $html .= "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.qr-code').forEach(function(el) {
                        QRCode.toCanvas(el, el.dataset.qr, {width: 100});
                    });
                });
            </script>\n";
        }
        
        $html .= "</body>\n</html>";
        
        return $html;
    }
    
    /**
     * Ottiene CSS per formati etichette
     */
    private function get_labels_css($format) {
        switch ($format) {
            case 'avery_l7163':
                return "
                    @media print { @page { margin: 0; } }
                    body { font-family: Arial, sans-serif; margin: 0; padding: 8.5mm; }
                    .labels-container { width: 210mm; }
                    .label { 
                        width: 99.1mm; height: 38.1mm; 
                        float: left; margin-right: 0; margin-bottom: 0;
                        border: 1px solid #ddd; box-sizing: border-box;
                        padding: 5mm; text-align: center;
                        page-break-inside: avoid;
                    }
                    .label:nth-child(2n) { margin-right: 0; }
                    .label:nth-child(14n) { page-break-after: always; }
                    .bib-number { font-size: 18pt; font-weight: bold; color: #c41e3a; }
                    .name { font-size: 14pt; margin: 2mm 0; }
                    .info { font-size: 10pt; color: #666; }
                    .qr-code { margin-top: 2mm; }
                ";
                
            default:
                return "
                    body { font-family: Arial, sans-serif; margin: 10mm; }
                    .label { 
                        width: 90mm; height: 50mm; 
                        border: 1px solid #000; margin-bottom: 5mm;
                        padding: 5mm; text-align: center; float: left; margin-right: 10mm;
                    }
                    .bib-number { font-size: 24pt; font-weight: bold; }
                    .name { font-size: 16pt; margin: 5mm 0; }
                    .info { font-size: 12pt; }
                ";
        }
    }
    
    /**
     * Genera report statistiche
     */
    public function export_statistics_report($event_id, $format = 'html') {
        $stats = $this->generate_detailed_stats($event_id);
        
        if (empty($stats)) {
            return new WP_Error('no_stats', 'Impossibile generare statistiche per l\'evento');
        }
        
        $filename = $this->generate_filename($event_id, 'stats_' . $format);
        
        switch ($format) {
            case 'csv':
                $content = $this->generate_stats_csv($stats);
                $file_path = $this->create_csv_file($content, $filename, array('delimiter' => ','));
                break;
                
            case 'json':
                $content = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $file_path = $this->create_text_file($content, $filename);
                break;
                
            default: // html
                $content = $this->generate_stats_html($stats);
                $file_path = $this->create_html_file($content, $filename);
        }
        
        if (!$file_path) {
            return new WP_Error('file_creation_failed', 'Errore nella creazione del report');
        }
        
        return array(
            'file_path' => $file_path,
            'filename' => $filename,
            'url' => $this->get_download_url($filename)
        );
    }
    
    /**
     * Genera statistiche dettagliate
     */
    private function generate_detailed_stats($event_id) {
        global $wpdb;
        
        // Informazioni evento
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . AMACARUN_EVENTS_TABLE . " WHERE id = %d",
            $event_id
        ));
        
        if (!$event) {
            return false;
        }
        
        $stats = array(
            'event' => array(
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->date,
                'status' => $event->status
            ),
            'participants' => $this->participant_manager->get_event_stats($event_id),
            'demographics' => $this->get_demographics_stats($event_id),
            'registration' => $this->get_registration_stats($event_id),
            'checkin' => $this->get_checkin_stats($event_id),
            'mailpoet' => $this->get_mailpoet_stats($event_id),
            'payments' => $this->get_payment_stats($event_id),
            'generated_at' => current_time('mysql')
        );
        
        return $stats;
    }
    
    /**
     * Statistiche demografiche
     */
    private function get_demographics_stats($event_id) {
        global $wpdb;
        
        return array(
            'adults' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND participant_type = 'adult'",
                $event_id
            )),
            'children' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND participant_type = 'child'",
                $event_id
            )),
            'association_members' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND association_member = 1",
                $event_id
            ))
        );
    }
    
    /**
     * Statistiche registrazioni
     */
    private function get_registration_stats($event_id) {
        global $wpdb;
        
        // Registrazioni per giorno
        $daily_registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d 
             GROUP BY DATE(created_at)
             ORDER BY date",
            $event_id
        ), ARRAY_A);
        
        return array(
            'online' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND registration_type = 'online'",
                $event_id
            )),
            'on_site' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND registration_type = 'on_site'",
                $event_id
            )),
            'daily_breakdown' => $daily_registrations
        );
    }
    
    /**
     * Statistiche check-in
     */
    private function get_checkin_stats($event_id) {
        global $wpdb;
        
        return array(
            'checked_in' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND status = 'checked_in'",
                $event_id
            )),
            'distance_4km' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND distance = '4km'",
                $event_id
            )),
            'distance_11km' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND distance = '11km'",
                $event_id
            )),
            'retired' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
                 WHERE event_id = %d AND status = 'retired'",
                $event_id
            ))
        );
    }
    
    /**
     * Statistiche MailPoet
     */
    private function get_mailpoet_stats($event_id) {
        if (!class_exists('AmacarUN_MailPoet_Manager')) {
            return array('available' => false);
        }
        
        $mailpoet_manager = new AmacarUN_MailPoet_Manager();
        return array_merge(
            array('available' => $mailpoet_manager->is_mailpoet_active()),
            $mailpoet_manager->get_mailpoet_stats($event_id)
        );
    }
    
    /**
     * Statistiche pagamenti
     */
    private function get_payment_stats($event_id) {
        global $wpdb;
        
        $payment_methods = $wpdb->get_results($wpdb->prepare(
            "SELECT payment_method, COUNT(*) as count, SUM(payment_amount) as total
             FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND payment_method IS NOT NULL
             GROUP BY payment_method",
            $event_id
        ), ARRAY_A);
        
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(payment_amount) FROM " . AMACARUN_PARTICIPANTS_TABLE . " 
             WHERE event_id = %d AND payment_amount IS NOT NULL",
            $event_id
        ));
        
        return array(
            'total_revenue' => $total_revenue ?: 0,
            'payment_methods' => $payment_methods ?: array()
        );
    }
    
    /**
     * Export con progress tracking
     */
    public function export_with_progress($event_id, $type, $options = array()) {
        $progress_key = 'amacarun_export_progress_' . $event_id . '_' . $type;
        
        // Inizializza progress
        set_transient($progress_key, array(
            'status' => 'starting',
            'progress' => 0,
            'message' => 'Inizializzazione export...'
        ), 300);
        
        // Esegui export
        switch ($type) {
            case 'csv':
                $result = $this->export_participants_csv($event_id, $options);
                break;
                
            case 'labels':
                $result = $this->export_labels($event_id, $options);
                break;
                
            case 'stats':
                $result = $this->export_statistics_report($event_id, $options['format'] ?? 'html');
                break;
                
            default:
                $result = new WP_Error('invalid_type', 'Tipo export non valido');
        }
        
        // Aggiorna progress finale
        if (is_wp_error($result)) {
            set_transient($progress_key, array(
                'status' => 'error',
                'progress' => 0,
                'message' => $result->get_error_message()
            ), 60);
        } else {
            set_transient($progress_key, array(
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Export completato con successo',
                'result' => $result
            ), 300);
        }
        
        return $result;
    }