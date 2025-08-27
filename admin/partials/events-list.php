<?php
/**
 * Template Lista Eventi Admin - AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Variabili disponibili: $events, $active_event
?>

<div class="wrap amacarun-admin-container">
    <div class="amacarun-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="amacarun-header-actions">
            <a href="<?php echo admin_url('admin.php?page=amacarun-events&action=new'); ?>" class="amacarun-btn amacarun-btn-primary">
                ➕ <?php _e('Nuovo Evento', 'amacarun-race-manager'); ?>
            </a>
        </div>
    </div>

    <?php if (isset($_GET['message'])): ?>
        <div class="amacarun-alert amacarun-alert-<?php echo $_GET['message'] === 'error' ? 'danger' : 'success'; ?>">
            <?php
            switch ($_GET['message']) {
                case 'event_created':
                    _e('Evento creato con successo!', 'amacarun-race-manager');
                    break;
                case 'event_updated':
                    _e('Evento aggiornato con successo!', 'amacarun-race-manager');
                    break;
                case 'event_activated':
                    _e('Evento attivato con successo!', 'amacarun-race-manager');
                    break;
                case 'error':
                    _e('Si è verificato un errore durante l\'operazione.', 'amacarun-race-manager');
                    break;
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Statistiche eventi -->
    <div class="amacarun-stats-grid">
        <?php 
        $events_by_status = array(
            'active' => 0,
            'draft' => 0,
            'completed' => 0
        );
        
        foreach ($events as $event) {
            if (isset($events_by_status[$event->status])) {
                $events_by_status[$event->status]++;
            }
        }
        ?>
        
        <div class="amacarun-stat-card">
            <div class="amacarun-stat-number"><?php echo count($events); ?></div>
            <div class="amacarun-stat-label"><?php _e('Eventi Totali', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card">
            <div class="amacarun-stat-number"><?php echo $events_by_status['active']; ?></div>
            <div class="amacarun-stat-label"><?php _e('Eventi Attivi', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card">
            <div class="amacarun-stat-number"><?php echo $events_by_status['draft']; ?></div>
            <div class="amacarun-stat-label"><?php _e('Bozze', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card">
            <div class="amacarun-stat-number"><?php echo $events_by_status['completed']; ?></div>
            <div class="amacarun-stat-label"><?php _e('Completati', 'amacarun-race-manager'); ?></div>
        </div>
    </div>

    <!-- Lista eventi -->
    <div class="amacarun-card">
        <div class="amacarun-card-header">
            <h3 class="amacarun-card-title"><?php _e('I Tuoi Eventi', 'amacarun-race-manager'); ?></h3>
        </div>

        <?php if (!empty($events)): ?>
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                    <div class="event-card <?php echo $event->status === 'active' ? 'active-event' : ''; ?>">
                        <!-- Header evento -->
                        <div class="event-header">
                            <h4 class="event-name"><?php echo esc_html($event->name); ?></h4>
                            <div class="event-status">
                                <span class="amacarun-status-badge amacarun-status-<?php echo $event->status; ?>">
                                    <?php 
                                    switch ($event->status) {
                                        case 'active':
                                            echo '🟢 ' . __('Attivo', 'amacarun-race-manager');
                                            break;
                                        case 'completed':
                                            echo '🏁 ' . __('Completato', 'amacarun-race-manager');
                                            break;
                                        default:
                                            echo '📝 ' . __('Bozza', 'amacarun-race-manager');
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Dettagli evento -->
                        <div class="event-details">
                            <div class="event-date">
                                <span class="icon">📅</span>
                                <span><?php echo date_i18n(get_option('date_format'), strtotime($event->date)); ?></span>
                            </div>

                            <!-- Statistiche partecipanti se evento attivo -->
                            <?php if ($event->status === 'active'): ?>
                                <?php
                                global $wpdb;
                                $participant_stats = $wpdb->get_row($wpdb->prepare(
                                    "SELECT 
                                        COUNT(*) as total,
                                        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
                                        SUM(CASE WHEN participant_type = 'adult' THEN 1 ELSE 0 END) as adults,
                                        SUM(CASE WHEN participant_type = 'child' THEN 1 ELSE 0 END) as children
                                     FROM " . AMACARUN_PARTICIPANTS_TABLE . " WHERE event_id = %d",
                                    $event->id
                                ));
                                ?>
                                
                                <div class="event-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $participant_stats->total ?? 0; ?></span>
                                        <span class="stat-label"><?php _e('Partecipanti', 'amacarun-race-manager'); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $participant_stats->checked_in ?? 0; ?></span>
                                        <span class="stat-label"><?php _e('Check-in', 'amacarun-race-manager'); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Configurazioni evento -->
                            <div class="event-config">
                                <?php if ($event->woocommerce_category_id): ?>
                                    <div class="config-item">
                                        <span class="icon">🛒</span>
                                        <span><?php _e('WooCommerce configurato', 'amacarun-race-manager'); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($event->mailpoet_list_id): ?>
                                    <div class="config-item">
                                        <span class="icon">📧</span>
                                        <span><?php _e('MailPoet configurato', 'amacarun-race-manager'); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="config-item">
                                    <span class="icon">🏷️</span>
                                    <span><?php printf(__('Pettorali: %d-%d', 'amacarun-race-manager'), 
                                        $event->bib_number_start, 
                                        $event->bib_number_current - 1); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Azioni evento -->
                        <div class="event-actions">
                            <?php if ($event->status !== 'active'): ?>
                                <button type="button" 
                                        class="amacarun-btn amacarun-btn-sm amacarun-btn-success activate-event" 
                                        data-event-id="<?php echo $event->id; ?>"
                                        title="<?php esc_attr_e('Attiva questo evento', 'amacarun-race-manager'); ?>">
                                    ▶️ <?php _e('Attiva', 'amacarun-race-manager'); ?>
                                </button>
                            <?php endif; ?>

                            <a href="<?php echo admin_url('admin.php?page=amacarun-events&action=edit&event_id=' . $event->id); ?>" 
                               class="amacarun-btn amacarun-btn-sm amacarun-btn-outline"
                               title="<?php esc_attr_e('Modifica evento', 'amacarun-race-manager'); ?>">
                                ✏️ <?php _e('Modifica', 'amacarun-race-manager'); ?>
                            </a>

                            <button type="button" 
                                    class="amacarun-btn amacarun-btn-sm amacarun-btn-secondary duplicate-event" 
                                    data-event-id="<?php echo $event->id; ?>"
                                    title="<?php esc_attr_e('Duplica evento', 'amacarun-race-manager'); ?>">
                                📋 <?php _e('Duplica', 'amacarun-race-manager'); ?>
                            </button>

                            <?php if ($event->status === 'active'): ?>
                                <a href="<?php echo admin_url('admin.php?page=amacarun-participants'); ?>" 
                                   class="amacarun-btn amacarun-btn-sm amacarun-btn-primary"
                                   title="<?php esc_attr_e('Gestisci partecipanti', 'amacarun-race-manager'); ?>">
                                    👥 <?php _e('Partecipanti', 'amacarun-race-manager'); ?>
                                </a>

                                <a href="<?php echo admin_url('admin.php?page=amacarun-checkin'); ?>" 
                                   class="amacarun-btn amacarun-btn-sm amacarun-btn-primary"
                                   title="<?php esc_attr_e('Interfaccia check-in', 'amacarun-race-manager'); ?>">
                                    ✅ <?php _e('Check-in', 'amacarun-race-manager'); ?>
                                </a>
                            <?php endif; ?>

                            <!-- Menu dropdown per azioni avanzate -->
                            <div class="dropdown-menu">
                                <button type="button" class="dropdown-toggle amacarun-btn amacarun-btn-sm amacarun-btn-outline">
                                    ⋮
                                </button>
                                <div class="dropdown-content">
                                    <?php if ($event->status === 'draft'): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=amacarun-events&action=delete&event_id=' . $event->id), 'delete_event_' . $event->id); ?>" 
                                           class="dropdown-item danger"
                                           onclick="return confirm('<?php esc_js_e('Sei sicuro di voler eliminare questo evento? Tutti i dati associati saranno persi.', 'amacarun-race-manager'); ?>')">
                                            🗑️ <?php _e('Elimina', 'amacarun-race-manager'); ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($event->status === 'active'): ?>
                                        <button type="button" 
                                                class="dropdown-item complete-event" 
                                                data-event-id="<?php echo $event->id; ?>">
                                            🏁 <?php _e('Completa Evento', 'amacarun-race-manager'); ?>
                                        </button>
                                    <?php endif; ?>

                                    <a href="<?php echo admin_url('admin.php?page=amacarun-export&event_id=' . $event->id); ?>" 
                                       class="dropdown-item">
                                        📤 <?php _e('Export Dati', 'amacarun-race-manager'); ?>
                                    </a>

                                    <button type="button" 
                                            class="dropdown-item view-settings" 
                                            data-event-id="<?php echo $event->id; ?>">
                                        ⚙️ <?php _e('Impostazioni', 'amacarun-race-manager'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Data creazione -->
                        <div class="event-footer">
                            <small class="creation-date">
                                <?php printf(
                                    __('Creato il %s', 'amacarun-race-manager'),
                                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->created_at))
                                ); ?>
                            </small>

                            <?php if ($event->updated_at && $event->updated_at !== $event->created_at): ?>
                                <small class="updated-date">
                                    <?php printf(
                                        __('Aggiornato il %s', 'amacarun-race-manager'),
                                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->updated_at))
                                    ); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Stato vuoto -->
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                <h3><?php _e('Nessun evento creato', 'amacarun-race-manager'); ?></h3>
                <p><?php _e('Crea il tuo primo evento per iniziare a gestire le iscrizioni e i partecipanti.', 'amacarun-race-manager'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=amacarun-events&action=new'); ?>" class="amacarun-btn amacarun-btn-primary amacarun-btn-lg">
                    🚀 <?php _e('Crea il Primo Evento', 'amacarun-race-manager'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Stili specifici per la lista eventi */
.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.event-card {
    background: #fff;
    border-radius: var(--amacarun-radius-lg);
    box-shadow: var(--amacarun-shadow);
    padding: 25px;
    border-left: 5px solid #ddd;
    transition: var(--amacarun-transition);
    position: relative;
}

.event-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--amacarun-shadow-lg);
}

.event-card.active-event {
    border-left-color: var(--amacarun-primary);
    background: linear-gradient(135deg, #fff 0%, rgba(196, 30, 58, 0.02) 100%);
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.event-name {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--amacarun-primary);
    flex: 1;
}

.event-details {
    margin-bottom: 25px;
}

.event-date {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--amacarun-dark);
    margin-bottom: 15px;
}

.event-date .icon {
    font-size: 1.2rem;
}

.event-stats {
    display: flex;
    gap: 20px;
    margin: 15px 0;
    padding: 15px;
    background: var(--amacarun-light);
    border-radius: var(--amacarun-radius);
}

.event-stats .stat-item {
    text-align: center;
}

.event-stats .stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--amacarun-primary);
}

.event-stats .stat-label {
    font-size: 0.8rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.event-config {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.config-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #666;
}

.config-item .icon {
    font-size: 1rem;
}

.event-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.event-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #999;
    font-size: 0.8rem;
    border-top: 1px solid #eee;
    padding-top: 10px;
}

/* Dropdown menu */
.dropdown-menu {
    position: relative;
    display: inline-block;
}

.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: #fff;
    border-radius: var(--amacarun-radius);
    box-shadow: var(--amacarun-shadow-lg);
    border: 1px solid #ddd;
    min-width: 160px;
    z-index: 1000;
}

.dropdown-menu.active .dropdown-content {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 10px 15px;
    text-decoration: none;
    color: var(--amacarun-dark);
    font-size: 0.9rem;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    transition: var(--amacarun-transition);
}

.dropdown-item:hover {
    background: var(--amacarun-light);
    text-decoration: none;
    color: var(--amacarun-primary);
}

.dropdown-item.danger:hover {
    background: #ffe6e6;
    color: var(--amacarun-danger);
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--amacarun-dark);
}

.empty-state p {
    margin-bottom: 30px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 768px) {
    .events-grid {
        grid-template-columns: 1fr;
    }
    
    .event-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .event-actions {
        justify-content: center;
    }
    
    .event-footer {
        flex-direction: column;
        gap: 5px;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Toggle dropdown menu
    $('.dropdown-toggle').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Chiudi altri dropdown
        $('.dropdown-menu').not($(this).closest('.dropdown-menu')).removeClass('active');
        
        // Toggle current dropdown
        $(this).closest('.dropdown-menu').toggleClass('active');
    });
    
    // Chiudi dropdown cliccando fuori
    $(document).on('click', function() {
        $('.dropdown-menu').removeClass('active');
    });
    
    // Attiva evento
    $('.activate-event').on('click', function() {
        if (!confirm('<?php esc_js_e('Attivare questo evento? L\'evento attualmente attivo verrà disattivato.', 'amacarun-race-manager'); ?>')) {
            return;
        }
        
        const eventId = $(this).data('event-id');
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_activate_event',
                event_id: eventId,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    AmacarUN.UI.showMessage('✅ Evento attivato con successo!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    AmacarUN.UI.showMessage('❌ Errore nell\'attivazione dell\'evento', 'error');
                }
            }
        });
    });
    
    // Duplica evento
    $('.duplicate-event').on('click', function() {
        const eventId = $(this).data('event-id');
        const newName = prompt('<?php esc_js_e('Nome per il nuovo evento:', 'amacarun-race-manager'); ?>');
        
        if (!newName) return;
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_duplicate_event',
                event_id: eventId,
                new_name: newName,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    AmacarUN.UI.showMessage('📋 Evento duplicato!', 'success');
                    setTimeout(function() {
                        window.location.href = `<?php echo admin_url('admin.php?page=amacarun-events&action=edit&event_id='); ?>${response.data.new_event_id}`;
                    }, 1500);
                } else {
                    AmacarUN.UI.showMessage('❌ Errore nella duplicazione', 'error');
                }
            }
        });
    });
    
    // Completa evento
    $('.complete-event').on('click', function() {
        if (!confirm('<?php esc_js_e('Completare questo evento? Non potrà più essere modificato.', 'amacarun-race-manager'); ?>')) {
            return;
        }
        
        const eventId = $(this).data('event-id');
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_complete_event',
                event_id: eventId,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    AmacarUN.UI.showMessage('🏁 Evento completato!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    AmacarUN.UI.showMessage('❌ Errore nel completamento dell\'evento', 'error');
                }
            }
        });
    });
    
    // Visualizza impostazioni evento
    $('.view-settings').on('click', function() {
        const eventId = $(this).data('event-id');
        // Redirect alla pagina di modifica
        window.location.href = `<?php echo admin_url('admin.php?page=amacarun-events&action=edit&event_id='); ?>${eventId}`;
    });
});
</script>