/**
     * Renderizza paginazione
     */
    private function render_pagination($event_id, $query_args, $per_page) {
        global $wpdb;
        
        // Conta totale partecipanti
        $count_query = "SELECT COUNT(*) FROM " . AMACARUN_PARTICIPANTS_TABLE . " WHERE event_id = %d";
        $count_params = array($event_id);
        
        if (isset($query_args['status'])) {
            $count_query .= " AND status = %s";
            $count_params[] = $query_args['status'];
        }
        
        if (isset($query_args['participant_type'])) {
            $count_query .= " AND participant_type = %s";
            $count_params[] = $query_args['participant_type'];
        }
        
        if (isset($query_args['distance'])) {
            $count_query .= " AND distance = %s";
            $count_params[] = $query_args['distance'];
        }
        
        $total = $wpdb->get_var($wpdb->prepare($count_query, $count_params));
        $total_pages = ceil($total / $per_page);
        
        if ($total_pages <= 1) {
            return '';
        }
        
        $current_page = max(1, get_query_var('paged', 1));
        
        $output = '<div class="amacarun-pagination">';
        
        // Pagina precedente
        if ($current_page > 1) {
            $prev_url = add_query_arg('paged', $current_page - 1);
            $output .= '<a href="' . esc_url($prev_url) . '" class="amacarun-page-prev">« ' . __('Precedente', 'amacarun-race-manager') . '</a>';
        }
        
        // Numeri pagine
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        if ($start_page > 1) {
            $output .= '<a href="' . esc_url(add_query_arg('paged', 1)) . '" class="amacarun-page-number">1</a>';
            if ($start_page > 2) {
                $output .= '<span class="amacarun-page-dots">…</span>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                $output .= '<span class="amacarun-page-number amacarun-current-page">' . $i . '</span>';
            } else {
                $output .= '<a href="' . esc_url(add_query_arg('paged', $i)) . '" class="amacarun-page-number">' . $i . '</a>';
            }
        }
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $output .= '<span class="amacarun-page-dots">…</span>';
            }
            $output .= '<a href="' . esc_url(add_query_arg('paged', $total_pages)) . '" class="amacarun-page-number">' . $total_pages . '</a>';
        }
        
        // Pagina successiva
        if ($current_page < $total_pages) {
            $next_url = add_query_arg('paged', $current_page + 1);
            $output .= '<a href="' . esc_url($next_url) . '" class="amacarun-page-next">' . __('Successiva', 'amacarun-race-manager') . ' »</a>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Ottiene etichetta stato localizzata
     */
    private function get_status_label($status) {
        $labels = array(
            'registered' => __('Registrato', 'amacarun-race-manager'),
            'checked_in' => __('Check-in', 'amacarun-race-manager'),
            'retired' => __('Ritirato', 'amacarun-race-manager')
        );
        
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }
    
    /**
     * Shortcode: Informazioni evento
     * 
     * [amacarun_event_info event_id="1" show_date="true" show_participants="true"]
     */
    public function event_info_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_id' => 0,
            'show_date' => 'true',
            'show_participants' => 'true',
            'show_status' => 'true',
            'style' => 'default' // default, minimal, detailed
        ), $atts, 'amacarun_event_info');
        
        // Determina evento
        $event_id = intval($atts['event_id']);
        if (!$event_id) {
            $active_event = AmacarUN_Race_Manager::get_active_event();
            $event_id = $active_event ? $active_event->id : 0;
        }
        
        if (!$event_id) {
            return '<div class="amacarun-notice amacarun-notice-warning">' . 
                   __('Nessun evento disponibile.', 'amacarun-race-manager') . 
                   '</div>';
        }
        
        // Ottieni dati evento
        global $wpdb;
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . AMACARUN_EVENTS_TABLE . " WHERE id = %d",
            $event_id
        ));
        
        if (!$event) {
            return '<div class="amacarun-notice amacarun-notice-error">' . 
                   __('Evento non trovato.', 'amacarun-race-manager') . 
                   '</div>';
        }
        
        $stats = null;
        if ($atts['show_participants'] === 'true') {
            $stats = $this->participant_manager->get_event_stats($event_id);
        }
        
        return $this->render_event_info($event, $stats, $atts);
    }
    
    /**
     * Renderizza informazioni evento
     */
    private function render_event_info($event, $stats, $atts) {
        $style = $atts['style'];
        
        $output = '<div class="amacarun-event-info amacarun-style-' . esc_attr($style) . '">';
        
        // Nome evento
        $output .= '<h2 class="amacarun-event-name">' . esc_html($event->name) . '</h2>';
        
        // Data
        if ($atts['show_date'] === 'true') {
            $date_formatted = date_i18n(get_option('date_format'), strtotime($event->date));
            $output .= '<div class="amacarun-event-date">';
            $output .= '<span class="amacarun-date-label">' . __('Data:', 'amacarun-race-manager') . '</span> ';
            $output .= '<span class="amacarun-date-value">' . $date_formatted . '</span>';
            $output .= '</div>';
        }
        
        // Stato
        if ($atts['show_status'] === 'true') {
            $status_label = $this->get_event_status_label($event->status);
            $output .= '<div class="amacarun-event-status">';
            $output .= '<span class="amacarun-status-badge amacarun-event-status-' . $event->status . '">' . $status_label . '</span>';
            $output .= '</div>';
        }
        
        // Statistiche partecipanti
        if ($stats && $atts['show_participants'] === 'true') {
            if ($style === 'detailed') {
                $output .= $this->render_detailed_event_stats($stats);
            } else {
                $output .= '<div class="amacarun-event-participants">';
                $output .= '<span class="amacarun-participants-label">' . __('Partecipanti:', 'amacarun-race-manager') . '</span> ';
                $output .= '<span class="amacarun-participants-count">' . $stats['total'] . '</span>';
                $output .= '</div>';
            }
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderizza statistiche dettagliate evento
     */
    private function render_detailed_event_stats($stats) {
        $output = '<div class="amacarun-detailed-stats">';
        $output .= '<h3>' . __('Statistiche Partecipazione', 'amacarun-race-manager') . '</h3>';
        
        $output .= '<div class="amacarun-stats-grid">';
        
        $output .= '<div class="amacarun-stat-item">';
        $output .= '<span class="amacarun-stat-number">' . $stats['total'] . '</span>';
        $output .= '<span class="amacarun-stat-label">' . __('Totale Iscritti', 'amacarun-race-manager') . '</span>';
        $output .= '</div>';
        
        $output .= '<div class="amacarun-stat-item">';
        $output .= '<span class="amacarun-stat-number">' . $stats['checked_in'] . '</span>';
        $output .= '<span class="amacarun-stat-label">' . __('Check-in Effettuati', 'amacarun-race-manager') . '</span>';
        $output .= '</div>';
        
        if ($stats['distance_4km'] > 0 || $stats['distance_11km'] > 0) {
            $output .= '<div class="amacarun-stat-item">';
            $output .= '<span class="amacarun-stat-number">' . $stats['distance_4km'] . '</span>';
            $output .= '<span class="amacarun-stat-label">4km</span>';
            $output .= '</div>';
            
            $output .= '<div class="amacarun-stat-item">';
            $output .= '<span class="amacarun-stat-number">' . $stats['distance_11km'] . '</span>';
            $output .= '<span class="amacarun-stat-label">11km</span>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Ottiene etichetta stato evento
     */
    private function get_event_status_label($status) {
        $labels = array(
            'draft' => __('Bozza', 'amacarun-race-manager'),
            'active' => __('Attivo', 'amacarun-race-manager'),
            'completed' => __('Completato', 'amacarun-race-manager')
        );
        
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }
    
    /**
     * Shortcode: Statistiche registrazione
     * 
     * [amacarun_registration_stats event_id="1" show_chart="true"]
     */
    public function registration_stats_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_id' => 0,
            'show_chart' => 'false',
            'show_breakdown' => 'true',
            'style' => 'default' // default, compact
        ), $atts, 'amacarun_registration_stats');
        
        // Determina evento
        $event_id = intval($atts['event_id']);
        if (!$event_id) {
            $active_event = AmacarUN_Race_Manager::get_active_event();
            $event_id = $active_event ? $active_event->id : 0;
        }
        
        if (!$event_id) {
            return '<div class="amacarun-notice amacarun-notice-warning">' . 
                   __('Nessun evento disponibile.', 'amacarun-race-manager') . 
                   '</div>';
        }
        
        $stats = $this->participant_manager->get_event_stats($event_id);
        
        return $this->render_registration_stats($stats, $atts);
    }
    
    /**
     * Renderizza statistiche registrazione
     */
    private function render_registration_stats($stats, $atts) {
        $output = '<div class="amacarun-registration-stats amacarun-style-' . esc_attr($atts['style']) . '">';
        
        if ($atts['show_breakdown'] === 'true') {
            $output .= '<div class="amacarun-stats-breakdown">';
            
            // Tipologie partecipanti
            $output .= '<div class="amacarun-breakdown-section">';
            $output .= '<h4>' . __('Per Tipologia', 'amacarun-race-manager') . '</h4>';
            $output .= '<div class="amacarun-breakdown-items">';
            
            $adult_percentage = $stats['total'] > 0 ? round(($stats['adults'] / $stats['total']) * 100, 1) : 0;
            $child_percentage = $stats['total'] > 0 ? round(($stats['children'] / $stats['total']) * 100, 1) : 0;
            
            $output .= '<div class="amacarun-breakdown-item">';
            $output .= '<span class="amacarun-breakdown-label">' . __('Adulti', 'amacarun-race-manager') . ':</span> ';
            $output .= '<span class="amacarun-breakdown-value">' . $stats['adults'] . ' (' . $adult_percentage . '%)</span>';
            $output .= '</div>';
            
            $output .= '<div class="amacarun-breakdown-item">';
            $output .= '<span class="amacarun-breakdown-label">' . __('Bambini', 'amacarun-race-manager') . ':</span> ';
            $output .= '<span class="amacarun-breakdown-value">' . $stats['children'] . ' (' . $child_percentage . '%)</span>';
            $output .= '</div>';
            
            $output .= '</div>';
            $output .= '</div>';
            
            // Stati
            $output .= '<div class="amacarun-breakdown-section">';
            $output .= '<h4>' . __('Per Stato', 'amacarun-race-manager') . '</h4>';
            $output .= '<div class="amacarun-breakdown-items">';
            
            $registered_percentage = $stats['total'] > 0 ? round(($stats['registered'] / $stats['total']) * 100, 1) : 0;
            $checkedin_percentage = $stats['total'] > 0 ? round(($stats['checked_in'] / $stats['total']) * 100, 1) : 0;
            
            $output .= '<div class="amacarun-breakdown-item">';
            $output .= '<span class="amacarun-breakdown-label">' . __('Registrati', 'amacarun-race-manager') . ':</span> ';
            $output .= '<span class="amacarun-breakdown-value">' . $stats['registered'] . ' (' . $registered_percentage . '%)</span>';
            $output .= '</div>';
            
            $output .= '<div class="amacarun-breakdown-item">';
            $output .= '<span class="amacarun-breakdown-label">' . __('Check-in', 'amacarun-race-manager') . ':</span> ';
            $output .= '<span class="amacarun-breakdown-value">' . $stats['checked_in'] . ' (' . $checkedin_percentage . '%)</span>';
            $output .= '</div>';
            
            if ($stats['retired'] > 0) {
                $retired_percentage = round(($stats['retired'] / $stats['total']) * 100, 1);
                $output .= '<div class="amacarun-breakdown-item">';
                $output .= '<span class="amacarun-breakdown-label">' . __('Ritirati', 'amacarun-race-manager') . ':</span> ';
                $output .= '<span class="amacarun-breakdown-value">' . $stats['retired'] . ' (' . $retired_percentage . '%)</span>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
            $output .= '</div>';
            
            $output .= '</div>';
        }
        
        // Chart placeholder (da implementare con Chart.js)
        if ($atts['show_chart'] === 'true') {
            $output .= '<div class="amacarun-chart-container">';
            $output .= '<canvas id="amacarun-stats-chart" data-stats="' . esc_attr(json_encode($stats)) . '"></canvas>';
            $output .= '</div>';
            
            // Enqueue Chart.js se non già fatto
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Shortcode: Ricerca partecipanti AJAX
     * 
     * [amacarun_search_participants event_id="1" live_search="true"]
     */
    public function search_participants_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_id' => 0,
            'live_search' => 'true',
            'placeholder' => '',
            'show_results_count' => 'true',
            'min_chars' => 2
        ), $atts, 'amacarun_search_participants');
        
        // Determina evento
        $event_id = intval($atts['event_id']);
        if (!$event_id) {
            $active_event = AmacarUN_Race_Manager::get_active_event();
            $event_id = $active_event ? $active_event->id : 0;
        }
        
        if (!$event_id) {
            return '<div class="amacarun-notice amacarun-notice-warning">' . 
                   __('Nessun evento disponibile.', 'amacarun-race-manager') . 
                   '</div>';
        }
        
        $placeholder = !empty($atts['placeholder']) ? $atts['placeholder'] : __('Cerca partecipante...', 'amacarun-race-manager');
        
        $output = '<div class="amacarun-live-search" data-event-id="' . $event_id . '" data-min-chars="' . intval($atts['min_chars']) . '">';
        
        $output .= '<div class="amacarun-search-input-container">';
        $output .= '<input type="text" class="amacarun-live-search-input" placeholder="' . esc_attr($placeholder) . '">';
        $output .= '<div class="amacarun-search-loading" style="display: none;">🔍</div>';
        $output .= '</div>';
        
        if ($atts['show_results_count'] === 'true') {
            $output .= '<div class="amacarun-results-count" style="display: none;"></div>';
        }
        
        $output .= '<div class="amacarun-search-results"></div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * AJAX: Ricerca pubblica partecipanti
     */
    public function ajax_public_search() {
        check_ajax_referer('amacarun_public_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $event_id = intval($_POST['event_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 20);
        
        if (strlen($query) < 2) {
            wp_send_json_success(array(
                'results' => array(),
                'count' => 0,
                'message' => __('Inserisci almeno 2 caratteri per la ricerca', 'amacarun-race-manager')
            ));
        }
        
        if (!$event_id) {
            wp_send_json_error(array('message' => __('Evento non specificato', 'amacarun-race-manager')));
        }
        
        // Esegui ricerca
        $participants = $this->participant_manager->search_participants($query, $event_id, $limit);
        
        // Formatta risultati
        $results = array();
        foreach ($participants as $participant) {
            $results[] = array(
                'id' => $participant->id,
                'name' => $participant->first_name . ' ' . $participant->last_name,
                'bib_number' => $participant->bib_number,
                'type' => $participant->participant_type,
                'status' => $participant->status,
                'distance' => $participant->distance
            );
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'count' => count($results),
            'message' => sprintf(_n('%d risultato trovato', '%d risultati trovati', count($results), 'amacarun-race-manager'), count($results))
        ));
    }
    
    /**
     * Registra widget
     */
    public function register_widgets() {
        // Widget per statistiche evento
        register_widget('AmacarUN_Event_Stats_Widget');
        
        // Widget per lista partecipanti compatta
        register_widget('AmacarUN_Participants_Widget');
    }
    
    /**
     * Metodo di utilità per verificare se un evento è pubblico
     */
    public function is_event_public($event_id) {
        // Per ora tutti gli eventi attivi sono pubblici
        // Futura implementazione potrebbe aggiungere campo privacy
        $event = AmacarUN_Race_Manager::get_active_event();
        return $event && $event->id == $event_id && $event->status === 'active';
    }
    
    /**
     * Ottieni URL pubblico filtrato
     */
    public function get_filtered_url($filters = array()) {
        $current_url = remove_query_arg(array('amacarun_status', 'amacarun_type', 'amacarun_distance', 'amacarun_search', 'paged'));
        
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $current_url = add_query_arg('amacarun_' . $key, $value, $current_url);
            }
        }
        
        return $current_url;
    }
}

/**
 * Widget statistiche evento
 */
class AmacarUN_Event_Stats_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'amacarun_event_stats',
            __('AmacarUN - Statistiche Evento', 'amacarun-race-manager'),
            array('description' => __('Mostra le statistiche dell\'evento attivo', 'amacarun-race-manager'))
        );
    }
    
    public function widget($args, $instance) {
        $active_event = AmacarUN_Race_Manager::get_active_event();
        
        if (!$active_event) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : __('Statistiche Gara', 'amacarun-race-manager');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        // Usa shortcode per il contenuto
        echo do_shortcode('[amacarun_registration_stats style="compact"]');
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Titolo:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

/**
 * Widget lista partecipanti
 */
class AmacarUN_Participants_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'amacarun_participants',
            __('AmacarUN - Lista Partecipanti', 'amacarun-race-manager'),
            array('description' => __('Mostra una lista compatta dei partecipanti', 'amacarun-race-manager'))
        );
    }
    
    public function widget($args, $instance) {
        $active_event = AmacarUN_Race_Manager::get_active_event();
        
        if (!$active_event) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : __('Partecipanti', 'amacarun-race-manager');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        $limit = !empty($instance['limit']) ? intval($instance['limit']) : 10;
        
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        // Usa shortcode per il contenuto
        echo do_shortcode("[amacarun_participants_list style='compact' limit='$limit' show_search='false' show_filters='false' show_stats='false' pagination='false']");
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $limit = !empty($instance['limit']) ? $instance['limit'] : 10;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Titolo:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>"><?php _e('Numero partecipanti da mostrare:'); ?></label>
            <input class="small-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('limit')); ?>" type="number" 
                   value="<?php echo esc_attr($limit); ?>" min="1" max="50">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? intval($new_instance['limit']) : 10;
        return $instance;
    }
}<?php
/**
 * Frontend Pubblico AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per il frontend pubblico
 */
class AmacarUN_Public {
    
    /**
     * Manager instances
     */
    private $participant_manager;
    private $race_manager;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->participant_manager = new AmacarUN_Participant_Manager();
        $this->race_manager = AmacarUN_Race_Manager::get_instance();
        
        $this->init_hooks();
    }
    
    /**
     * Inizializza hook frontend
     */
    private function init_hooks() {
        // Enqueue scripts e stili
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        
        // AJAX per frontend
        add_action('wp_ajax_amacarun_public_search', array($this, 'ajax_public_search'));
        add_action('wp_ajax_nopriv_amacarun_public_search', array($this, 'ajax_public_search'));
        
        // Custom query vars per filtri
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Widget support
        add_action('widgets_init', array($this, 'register_widgets'));
    }
    
    /**
     * Carica script e stili frontend
     */
    public function enqueue_public_scripts() {
        // Solo se ci sono shortcode AmacarUN nella pagina
        if (!$this->has_amacarun_shortcode()) {
            return;
        }
        
        // CSS pubblico
        wp_enqueue_style(
            'amacarun-public-style',
            AMACARUN_PLUGIN_URL . 'public/css/public-style.css',
            array(),
            AMACARUN_VERSION
        );
        
        // JavaScript pubblico
        wp_enqueue_script(
            'amacarun-public-script',
            AMACARUN_PLUGIN_URL . 'public/js/public-script.js',
            array('jquery'),
            AMACARUN_VERSION,
            true
        );
        
        // Localizzazione per AJAX
        wp_localize_script('amacarun-public-script', 'amacarun_public', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amacarun_public_nonce'),
            'strings' => array(
                'loading' => __('Caricamento...', 'amacarun-race-manager'),
                'no_results' => __('Nessun risultato trovato', 'amacarun-race-manager'),
                'search_placeholder' => __('Cerca per nome, cognome o pettorale...', 'amacarun-race-manager'),
                'show_more' => __('Mostra altri', 'amacarun-race-manager'),
                'show_less' => __('Mostra meno', 'amacarun-race-manager')
            )
        ));
    }
    
    /**
     * Verifica se la pagina contiene shortcode AmacarUN
     */
    private function has_amacarun_shortcode() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return false;
        }
        
        $shortcodes = array(
            'amacarun_participants_list',
            'amacarun_event_info', 
            'amacarun_registration_stats',
            'amacarun_search_participants'
        );
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Aggiunge query vars personalizzate
     */
    public function add_query_vars($vars) {
        $vars[] = 'amacarun_status';
        $vars[] = 'amacarun_type';
        $vars[] = 'amacarun_distance';
        $vars[] = 'amacarun_search';
        return $vars;
    }
    
    /**
     * Shortcode: Lista partecipanti
     * 
     * [amacarun_participants_list event_id="1" show_bibs="true" show_search="true" limit="50" status="all" type="all"]
     */
    public function participants_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_id' => 0,
            'show_bibs' => 'true',
            'show_search' => 'true',
            'show_stats' => 'true',
            'limit' => 50,
            'status' => 'all', // all, registered, checked_in, retired
            'type' => 'all', // all, adult, child
            'distance' => 'all', // all, 4km, 11km
            'orderby' => 'bib_number', // bib_number, first_name, last_name, created_at
            'order' => 'asc',
            'pagination' => 'true',
            'style' => 'table', // table, cards, compact
            'show_filters' => 'true',
            'cache_duration' => 300 // 5 minuti
        ), $atts, 'amacarun_participants_list');
        
        // Determina evento
        $event_id = intval($atts['event_id']);
        if (!$event_id) {
            $active_event = AmacarUN_Race_Manager::get_active_event();
            $event_id = $active_event ? $active_event->id : 0;
        }
        
        if (!$event_id) {
            return '<div class="amacarun-notice amacarun-notice-warning">' . 
                   __('Nessun evento disponibile.', 'amacarun-race-manager') . 
                   '</div>';
        }
        
        // Cache per performance
        $cache_key = 'amacarun_participants_list_' . md5(serialize($atts) . $event_id);
        $cached_output = get_transient($cache_key);
        
        if ($cached_output !== false && !is_user_logged_in()) {
            return $cached_output;
        }
        
        // Genera output
        $output = $this->render_participants_list($event_id, $atts);
        
        // Salva in cache se non loggato
        if (!is_user_logged_in()) {
            set_transient($cache_key, $output, intval($atts['cache_duration']));
        }
        
        return $output;
    }
    
    /**
     * Renderizza lista partecipanti
     */
    private function render_participants_list($event_id, $atts) {
        // Ottieni filtri da URL
        $filters = array(
            'status' => $atts['status'] !== 'all' ? $atts['status'] : get_query_var('amacarun_status', ''),
            'type' => $atts['type'] !== 'all' ? $atts['type'] : get_query_var('amacarun_type', ''),
            'distance' => $atts['distance'] !== 'all' ? $atts['distance'] : get_query_var('amacarun_distance', ''),
            'search' => get_query_var('amacarun_search', ''),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'limit' => intval($atts['limit'])
        );
        
        // Costruisci argomenti query
        $query_args = array();
        if (!empty($filters['status'])) $query_args['status'] = $filters['status'];
        if (!empty($filters['type'])) $query_args['participant_type'] = $filters['type'];
        if (!empty($filters['distance'])) $query_args['distance'] = $filters['distance'];
        
        $query_args['orderby'] = $filters['orderby'];
        $query_args['order'] = strtoupper($filters['order']);
        
        if ($atts['pagination'] === 'true') {
            $paged = max(1, get_query_var('paged', 1));
            $query_args['limit'] = $filters['limit'];
            $query_args['offset'] = ($paged - 1) * $filters['limit'];
        }
        
        // Ottieni partecipanti
        if (!empty($filters['search'])) {
            $participants = $this->participant_manager->search_participants($filters['search'], $event_id, $filters['limit']);
        } else {
            $participants = $this->participant_manager->get_participants_by_event($event_id, $query_args);
        }
        
        // Ottieni statistiche se richieste
        $stats = null;
        if ($atts['show_stats'] === 'true') {
            $stats = $this->participant_manager->get_event_stats($event_id);
        }
        
        // Genera HTML
        $output = '<div class="amacarun-participants-container" data-event-id="' . $event_id . '">';
        
        // Statistiche
        if ($stats) {
            $output .= $this->render_participants_stats($stats);
        }
        
        // Filtri
        if ($atts['show_filters'] === 'true') {
            $output .= $this->render_participants_filters($filters, $event_id);
        }
        
        // Ricerca
        if ($atts['show_search'] === 'true') {
            $output .= $this->render_search_form($filters['search']);
        }
        
        // Lista partecipanti
        if (!empty($participants)) {
            $output .= $this->render_participants_content($participants, $atts);
            
            // Paginazione
            if ($atts['pagination'] === 'true' && empty($filters['search'])) {
                $output .= $this->render_pagination($event_id, $query_args, $filters['limit']);
            }
        } else {
            $output .= '<div class="amacarun-no-results">' . 
                      __('Nessun partecipante trovato.', 'amacarun-race-manager') . 
                      '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderizza statistiche partecipanti
     */
    private function render_participants_stats($stats) {
        $output = '<div class="amacarun-stats-summary">';
        $output .= '<div class="amacarun-stats-grid">';
        
        $output .= '<div class="amacarun-stat-item">';
        $output .= '<span class="amacarun-stat-number">' . $stats['total'] . '</span>';
        $output .= '<span class="amacarun-stat-label">' . __('Totale', 'amacarun-race-manager') . '</span>';
        $output .= '</div>';
        
        $output .= '<div class="amacarun-stat-item">';
        $output .= '<span class="amacarun-stat-number">' . $stats['adults'] . '</span>';
        $output .= '<span class="amacarun-stat-label">' . __('Adulti', 'amacarun-race-manager') . '</span>';
        $output .= '</div>';
        
        $output .= '<div class="amacarun-stat-item">';
        $output .= '<span class="amacarun-stat-number">' . $stats['children'] . '</span>';
        $output .= '<span class="amacarun-stat-label">' . __('Bambini', 'amacarun-race-manager') . '</span>';
        $output .= '</div>';
        
        $output .= '<div class="amacarun-stat-item">';
        $output .= '<span class="amacarun-stat-number">' . $stats['checked_in'] . '</span>';
        $output .= '<span class="amacarun-stat-label">' . __('Check-in', 'amacarun-race-manager') . '</span>';
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderizza filtri
     */
    private function render_participants_filters($filters, $event_id) {
        $base_url = remove_query_arg(array('amacarun_status', 'amacarun_type', 'amacarun_distance', 'paged'));
        
        $output = '<div class="amacarun-filters">';
        $output .= '<form method="get" class="amacarun-filters-form">';
        
        // Mantieni query vars esistenti
        foreach ($_GET as $key => $value) {
            if (!in_array($key, array('amacarun_status', 'amacarun_type', 'amacarun_distance', 'paged'))) {
                $output .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
            }
        }
        
        // Filtro stato
        $output .= '<select name="amacarun_status" onchange="this.form.submit()">';
        $output .= '<option value=""' . selected('', $filters['status'], false) . '>' . __('Tutti gli stati', 'amacarun-race-manager') . '</option>';
        $output .= '<option value="registered"' . selected('registered', $filters['status'], false) . '>' . __('Registrati', 'amacarun-race-manager') . '</option>';
        $output .= '<option value="checked_in"' . selected('checked_in', $filters['status'], false) . '>' . __('Check-in', 'amacarun-race-manager') . '</option>';
        $output .= '<option value="retired"' . selected('retired', $filters['status'], false) . '>' . __('Ritirati', 'amacarun-race-manager') . '</option>';
        $output .= '</select>';
        
        // Filtro tipo
        $output .= '<select name="amacarun_type" onchange="this.form.submit()">';
        $output .= '<option value=""' . selected('', $filters['type'], false) . '>' . __('Tutti i tipi', 'amacarun-race-manager') . '</option>';
        $output .= '<option value="adult"' . selected('adult', $filters['type'], false) . '>' . __('Adulti', 'amacarun-race-manager') . '</option>';
        $output .= '<option value="child"' . selected('child', $filters['type'], false) . '>' . __('Bambini', 'amacarun-race-manager') . '</option>';
        $output .= '</select>';
        
        // Filtro distanza
        $output .= '<select name="amacarun_distance" onchange="this.form.submit()">';
        $output .= '<option value=""' . selected('', $filters['distance'], false) . '>' . __('Tutte le distanze', 'amacarun-race-manager') . '</option>';
        $output .= '<option value="4km"' . selected('4km', $filters['distance'], false) . '>4km</option>';
        $output .= '<option value="11km"' . selected('11km', $filters['distance'], false) . '>11km</option>';
        $output .= '</select>';
        
        // Reset filtri
        if (!empty($filters['status']) || !empty($filters['type']) || !empty($filters['distance'])) {
            $output .= '<a href="' . esc_url($base_url) . '" class="amacarun-reset-filters">' . __('Reset', 'amacarun-race-manager') . '</a>';
        }
        
        $output .= '</form>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderizza form ricerca
     */
    private function render_search_form($current_search = '') {
        $output = '<div class="amacarun-search-form">';
        $output .= '<form method="get" class="amacarun-search">';
        
        // Mantieni altri parametri
        foreach ($_GET as $key => $value) {
            if ($key !== 'amacarun_search' && $key !== 'paged') {
                $output .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
            }
        }
        
        $output .= '<input type="text" name="amacarun_search" value="' . esc_attr($current_search) . '" ';
        $output .= 'placeholder="' . esc_attr__('Cerca per nome, cognome o pettorale...', 'amacarun-race-manager') . '" ';
        $output .= 'class="amacarun-search-input">';
        $output .= '<button type="submit" class="amacarun-search-button">' . __('Cerca', 'amacarun-race-manager') . '</button>';
        
        if (!empty($current_search)) {
            $reset_url = remove_query_arg(array('amacarun_search', 'paged'));
            $output .= '<a href="' . esc_url($reset_url) . '" class="amacarun-search-reset">×</a>';
        }
        
        $output .= '</form>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderizza contenuto partecipanti
     */
    private function render_participants_content($participants, $atts) {
        $style = $atts['style'];
        
        switch ($style) {
            case 'cards':
                return $this->render_participants_cards($participants, $atts);
                
            case 'compact':
                return $this->render_participants_compact($participants, $atts);
                
            default: // table
                return $this->render_participants_table($participants, $atts);
        }
    }
    
    /**
     * Renderizza tabella partecipanti
     */
    private function render_participants_table($participants, $atts) {
        $show_bibs = $atts['show_bibs'] === 'true';
        
        $output = '<div class="amacarun-table-container">';
        $output .= '<table class="amacarun-participants-table">';
        
        // Header
        $output .= '<thead><tr>';
        if ($show_bibs) $output .= '<th class="amacarun-col-bib">' . __('Pettorale', 'amacarun-race-manager') . '</th>';
        $output .= '<th class="amacarun-col-name">' . __('Nome', 'amacarun-race-manager') . '</th>';
        $output .= '<th class="amacarun-col-lastname">' . __('Cognome', 'amacarun-race-manager') . '</th>';
        $output .= '<th class="amacarun-col-type">' . __('Tipo', 'amacarun-race-manager') . '</th>';
        $output .= '<th class="amacarun-col-status">' . __('Stato', 'amacarun-race-manager') . '</th>';
        $output .= '<th class="amacarun-col-distance">' . __('Distanza', 'amacarun-race-manager') . '</th>';
        $output .= '</tr></thead>';
        
        // Body
        $output .= '<tbody>';
        foreach ($participants as $participant) {
            $output .= '<tr class="amacarun-participant-row amacarun-status-' . $participant->status . '">';
            
            if ($show_bibs) {
                $bib_display = $participant->bib_number ? '#' . $participant->bib_number : '-';
                $output .= '<td class="amacarun-bib-number">' . $bib_display . '</td>';
            }
            
            $output .= '<td class="amacarun-first-name">' . esc_html($participant->first_name) . '</td>';
            $output .= '<td class="amacarun-last-name">' . esc_html($participant->last_name) . '</td>';
            $output .= '<td class="amacarun-type">' . ($participant->participant_type === 'adult' ? __('Adulto', 'amacarun-race-manager') : __('Bambino', 'amacarun-race-manager')) . '</td>';
            
            $status_label = $this->get_status_label($participant->status);
            $output .= '<td class="amacarun-status"><span class="amacarun-status-badge amacarun-status-' . $participant->status . '">' . $status_label . '</span></td>';
            
            $distance_display = $participant->distance ?: '-';
            $output .= '<td class="amacarun-distance">' . $distance_display . '</td>';
            
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        
        $output .= '</table>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderizza cards partecipanti
     */
    private function render_participants_cards($participants, $atts) {
        $show_bibs = $atts['show_bibs'] === 'true';
        
        $output = '<div class="amacarun-participants-cards">';
        
        foreach ($participants as $participant) {
            $output .= '<div class="amacarun-participant-card amacarun-status-' . $participant->status . '">';
            
            if ($show_bibs && $participant->bib_number) {
                $output .= '<div class="amacarun-card-bib">#' . $participant->bib_number . '</div>';
            }
            
            $output .= '<div class="amacarun-card-content">';
            $output .= '<h3 class="amacarun-card-name">' . esc_html($participant->first_name . ' ' . $participant->last_name) . '</h3>';
            
            $output .= '<div class="amacarun-card-meta">';
            $output .= '<span class="amacarun-card-type">' . ($participant->participant_type === 'adult' ? __('Adulto', 'amacarun-race-manager') : __('Bambino', 'amacarun-race-manager')) . '</span>';
            
            if ($participant->distance) {
                $output .= ' • <span class="amacarun-card-distance">' . $participant->distance . '</span>';
            }
            $output .= '</div>';
            
            $status_label = $this->get_status_label($participant->status);
            $output .= '<div class="amacarun-card-status"><span class="amacarun-status-badge amacarun-status-' . $participant->status . '">' . $status_label . '</span></div>';
            
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Renderizza lista compatta
     */
    private function render_participants_compact($participants, $atts) {
        $show_bibs = $atts['show_bibs'] === 'true';
        
        $output = '<div class="amacarun-participants-compact">';
        
        foreach ($participants as $participant) {
            $output .= '<div class="amacarun-compact-item amacarun-status-' . $participant->status . '">';
            
            if ($show_bibs && $participant->bib_number) {
                $output .= '<span class="amacarun-compact-bib">#' . $participant->bib_number . '</span>';
            }
            
            $output .= '<span class="amacarun-compact-name">' . esc_html($participant->first_name . ' ' . $participant->last_name) . '</span>';
            
            $status_label = $this->get_status_label($participant->status);
            $output .= '<span class="amacarun-compact-status amacarun-status-' . $participant->status . '">' . $status_label . '</span>';
            
            if ($participant->distance) {
                $output .= '<span class="amacarun-compact-distance">' . $participant->distance . '</span>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }