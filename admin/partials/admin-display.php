<?php
/**
 * Template Dashboard Principale Admin
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap amacarun-admin-container">
    <div class="amacarun-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="amacarun-header-actions">
            <?php if ($active_event): ?>
                <span class="amacarun-status-badge amacarun-status-active">
                    📅 <?php echo esc_html($active_event->name); ?>
                </span>
            <?php endif; ?>
            
            <button type="button" id="refresh-stats" class="amacarun-btn amacarun-btn-secondary amacarun-btn-sm">
                🔄 <?php _e('Aggiorna', 'amacarun-race-manager'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=amacarun-events&action=new'); ?>" class="amacarun-btn amacarun-btn-primary">
                ➕ <?php _e('Nuovo Evento', 'amacarun-race-manager'); ?>
            </a>
        </div>
    </div>

    <?php if (!$active_event): ?>
        <div class="amacarun-welcome">
            <h2><?php _e('Benvenuto in AmacarUN Race Manager!', 'amacarun-race-manager'); ?></h2>
            <p><?php _e('Per iniziare, crea e attiva il tuo primo evento. Potrai poi sincronizzare i partecipanti da WooCommerce e gestire check-in, pettorali e molto altro.', 'amacarun-race-manager'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=amacarun-events&action=new'); ?>" class="amacarun-btn amacarun-btn-lg">
                🚀 <?php _e('Crea il Primo Evento', 'amacarun-race-manager'); ?>
            </a>
        </div>
    <?php else: ?>
        
        <!-- Statistiche Principali -->
        <div class="amacarun-stats-grid">
            <div class="amacarun-stat-card participants">
                <div class="amacarun-stat-number stat-total-participants">
                    <?php echo $stats['participants']['total'] ?? 0; ?>
                </div>
                <div class="amacarun-stat-label"><?php _e('Partecipanti Totali', 'amacarun-race-manager'); ?></div>
            </div>

            <div class="amacarun-stat-card adults">
                <div class="amacarun-stat-number stat-adults">
                    <?php echo $stats['participants']['adults'] ?? 0; ?>
                </div>
                <div class="amacarun-stat-label"><?php _e('Adulti', 'amacarun-race-manager'); ?></div>
            </div>

            <div class="amacarun-stat-card children">
                <div class="amacarun-stat-number stat-children">
                    <?php echo $stats['participants']['children'] ?? 0; ?>
                </div>
                <div class="amacarun-stat-label"><?php _e('Bambini', 'amacarun-race-manager'); ?></div>
            </div>

            <div class="amacarun-stat-card checked-in">
                <div class="amacarun-stat-number stat-checked-in">
                    <?php echo $stats['participants']['checked_in'] ?? 0; ?>
                </div>
                <div class="amacarun-stat-label"><?php _e('Check-in Effettuati', 'amacarun-race-manager'); ?></div>
            </div>

            <div class="amacarun-stat-card bibs">
                <div class="amacarun-stat-number stat-bibs-assigned">
                    <?php echo $stats['participants']['with_bib'] ?? 0; ?>
                </div>
                <div class="amacarun-stat-label"><?php _e('Pettorali Assegnati', 'amacarun-race-manager'); ?></div>
            </div>

            <div class="amacarun-stat-card mailpoet">
                <div class="amacarun-stat-number stat-mailpoet-subscribed">
                    <?php echo $stats['participants']['mailpoet_subscribed'] ?? 0; ?>
                </div>
                <div class="amacarun-stat-label"><?php _e('Iscritti MailPoet', 'amacarun-race-manager'); ?></div>
            </div>
        </div>

        <div class="amacarun-form-row">
            <!-- Pannello Sinistro -->
            <div style="flex: 2; margin-right: 30px;">
                
                <!-- Azioni Rapide -->
                <div class="amacarun-card">
                    <div class="amacarun-card-header">
                        <h3 class="amacarun-card-title"><?php _e('Azioni Rapide', 'amacarun-race-manager'); ?></h3>
                    </div>
                    
                    <div class="amacarun-btn-group">
                        <a href="<?php echo admin_url('admin.php?page=amacarun-participants'); ?>" class="amacarun-btn amacarun-btn-primary">
                            👥 <?php _e('Gestisci Partecipanti', 'amacarun-race-manager'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=amacarun-checkin'); ?>" class="amacarun-btn amacarun-btn-success">
                            ✅ <?php _e('Check-in', 'amacarun-race-manager'); ?>
                        </a>
                        
                        <button type="button" id="sync-woocommerce" class="amacarun-btn amacarun-btn-secondary">
                            🔄 <?php _e('Sincronizza WooCommerce', 'amacarun-race-manager'); ?>
                        </button>
                        
                        <a href="<?php echo admin_url('admin.php?page=amacarun-export'); ?>" class="amacarun-btn amacarun-btn-outline">
                            📤 <?php _e('Export Dati', 'amacarun-race-manager'); ?>
                        </a>
                    </div>
                </div>

                <!-- Informazioni Evento -->
                <div class="amacarun-card">
                    <div class="amacarun-card-header">
                        <h3 class="amacarun-card-title"><?php _e('Informazioni Evento', 'amacarun-race-manager'); ?></h3>
                        <div class="amacarun-card-actions">
                            <a href="<?php echo admin_url('admin.php?page=amacarun-events&action=edit&event_id=' . $active_event->id); ?>" class="amacarun-btn amacarun-btn-sm amacarun-btn-outline">
                                ✏️ <?php _e('Modifica', 'amacarun-race-manager'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <div class="amacarun-form-row">
                        <div class="amacarun-form-group">
                            <label><?php _e('Nome Evento', 'amacarun-race-manager'); ?></label>
                            <div class="amacarun-form-control" style="background: #f8f9fa; border: none;">
                                <?php echo esc_html($active_event->name); ?>
                            </div>
                        </div>
                        
                        <div class="amacarun-form-group">
                            <label><?php _e('Data Evento', 'amacarun-race-manager'); ?></label>
                            <div class="amacarun-form-control" style="background: #f8f9fa; border: none;">
                                <?php echo date_i18n(get_option('date_format'), strtotime($active_event->date)); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="amacarun-form-row">
                        <div class="amacarun-form-group">
                            <label><?php _e('Range Pettorali', 'amacarun-race-manager'); ?></label>
                            <div class="amacarun-form-control" style="background: #f8f9fa; border: none;">
                                <?php printf(
                                    __('Da %d - Prossimo: %d', 'amacarun-race-manager'),
                                    $active_event->bib_number_start,
                                    $active_event->bib_number_current
                                ); ?>
                            </div>
                        </div>
                        
                        <div class="amacarun-form-group">
                            <label><?php _e('Stato', 'amacarun-race-manager'); ?></label>
                            <div>
                                <span class="amacarun-status-badge amacarun-status-<?php echo $active_event->status; ?>">
                                    <?php echo ucfirst($active_event->status); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Check-in -->
                <?php 
                $total_participants = $stats['participants']['total'];
                $checked_in = $stats['participants']['checked_in'];
                $checkin_percentage = $total_participants > 0 ? round(($checked_in / $total_participants) * 100) : 0;
                ?>
                <div class="amacarun-card">
                    <div class="amacarun-card-header">
                        <h3 class="amacarun-card-title"><?php _e('Progresso Check-in', 'amacarun-race-manager'); ?></h3>
                    </div>
                    
                    <div class="amacarun-progress">
                        <div class="amacarun-progress-bar" style="width: <?php echo $checkin_percentage; ?>%" data-width="<?php echo $checkin_percentage; ?>">
                            <?php echo $checkin_percentage; ?>%
                        </div>
                    </div>
                    
                    <p style="text-align: center; margin: 15px 0 0 0; color: #666;">
                        <?php printf(
                            __('%d su %d partecipanti hanno effettuato il check-in', 'amacarun-race-manager'),
                            $checked_in,
                            $total_participants
                        ); ?>
                    </p>
                </div>

            </div>

            <!-- Pannello Destro -->
            <div style="flex: 1;">
                
                <!-- Stato Connessioni -->
                <div class="amacarun-card">
                    <div class="amacarun-card-header">
                        <h3 class="amacarun-card-title"><?php _e('Stato Integrazioni', 'amacarun-race-manager'); ?></h3>
                        <div class="amacarun-card-actions">
                            <button type="button" id="test-connections" class="amacarun-btn amacarun-btn-sm amacarun-btn-secondary">
                                🔍 <?php _e('Test', 'amacarun-race-manager'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="amacarun-connection-status woocommerce-status success">
                        <strong>WooCommerce</strong>
                        <div class="status-message"><?php _e('Connesso e funzionante', 'amacarun-race-manager'); ?></div>
                    </div>
                    
                    <div class="amacarun-connection-status mailpoet-status <?php echo amacarun_is_mailpoet_active() ? 'success' : 'warning'; ?>">
                        <strong>MailPoet</strong>
                        <div class="status-message">
                            <?php echo amacarun_is_mailpoet_active() ? 
                                __('Attivo e configurato', 'amacarun-race-manager') : 
                                __('Non attivo', 'amacarun-race-manager'); ?>
                        </div>
                    </div>
                </div>

                <!-- Attività Recenti -->
                <div class="amacarun-card">
                    <div class="amacarun-card-header">
                        <h3 class="amacarun-card-title"><?php _e('Attività Recenti', 'amacarun-race-manager'); ?></h3>
                    </div>
                    
                    <div class="activity-log">
                        <div class="activity-item">
                            <div class="activity-icon">👥</div>
                            <div class="activity-content">
                                <div class="activity-title"><?php _e('Ultima Sincronizzazione', 'amacarun-race-manager'); ?></div>
                                <div class="activity-time">
                                    <?php 
                                    $last_sync = get_option('amacarun_last_sync_time', '');
                                    echo $last_sync ? human_time_diff(strtotime($last_sync), current_time('timestamp')) . ' fa' : __('Mai', 'amacarun-race-manager');
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">💾</div>
                            <div class="activity-content">
                                <div class="activity-title"><?php _e('Ultimo Backup', 'amacarun-race-manager'); ?></div>
                                <div class="activity-time">
                                    <?php 
                                    $last_backup = get_option('amacarun_last_backup_time', '');
                                    echo $last_backup ? human_time_diff(strtotime($last_backup), current_time('timestamp')) . ' fa' : __('Mai', 'amacarun-race-manager');
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">📊</div>
                            <div class="activity-content">
                                <div class="activity-title"><?php _e('Versione Plugin', 'amacarun-race-manager'); ?></div>
                                <div class="activity-time">v<?php echo AMACARUN_VERSION; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Link Utili -->
                <div class="amacarun-card">
                    <div class="amacarun-card-header">
                        <h3 class="amacarun-card-title"><?php _e('Link Utili', 'amacarun-race-manager'); ?></h3>
                    </div>
                    
                    <div class="useful-links">
                        <a href="<?php echo admin_url('admin.php?page=amacarun-settings'); ?>" class="useful-link">
                            ⚙️ <?php _e('Impostazioni Plugin', 'amacarun-race-manager'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('edit.php?post_type=product&product_cat=amacarun'); ?>" class="useful-link" target="_blank">
                            🛒 <?php _e('Prodotti WooCommerce', 'amacarun-race-manager'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=mailpoet-segments'); ?>" class="useful-link" target="_blank">
                            📧 <?php _e('Segmenti MailPoet', 'amacarun-race-manager'); ?>
                        </a>
                        
                        <a href="#" class="useful-link" data-clipboard="[amacarun_participants_list]">
                            📋 <?php _e('Copia Shortcode Lista', 'amacarun-race-manager'); ?>
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Input nascosti per JavaScript -->
        <input type="hidden" id="active-event-id" value="<?php echo $active_event->id; ?>">
        <input type="hidden" id="current-event-id" value="<?php echo $active_event->id; ?>">

    <?php endif; ?>

    <!-- Footer -->
    <div class="amacarun-admin-footer">
        <p>
            <?php printf(
                __('AmacarUN Race Manager v%s - Gestione professionale gare podistiche. Fatto con ❤️ per la comunità running.', 'amacarun-race-manager'),
                AMACARUN_VERSION
            ); ?>
        </p>
    </div>
</div>

<style>
.activity-log {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    font-size: 1.2rem;
    width: 30px;
    text-align: center;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: var(--amacarun-dark);
    margin-bottom: 2px;
}

.activity-time {
    font-size: 0.85rem;
    color: #666;
}

.useful-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.useful-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: var(--amacarun-light);
    border-radius: var(--amacarun-radius);
    text-decoration: none;
    color: var(--amacarun-dark);
    font-size: 0.9rem;
    transition: var(--amacarun-transition);
}

.useful-link:hover {
    background: var(--amacarun-primary);
    color: #fff;
    text-decoration: none;
    transform: translateX(5px);
}
</style>