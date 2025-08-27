<?php
/**
 * Template Lista Partecipanti Admin - AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Variabili disponibili: $participants, $stats, $bib_stats, $active_event
?>

<div class="wrap amacarun-admin-container">
    <div class="amacarun-header">
        <h1>
            <?php echo esc_html(get_admin_page_title()); ?>
            <span class="amacarun-status-badge amacarun-status-active">
                📅 <?php echo esc_html($active_event->name); ?>
            </span>
        </h1>
        <div class="amacarun-header-actions">
            <button type="button" id="sync-woocommerce" class="amacarun-btn amacarun-btn-secondary">
                🔄 <?php _e('Sincronizza WooCommerce', 'amacarun-race-manager'); ?>
            </button>
            
            <button type="button" id="bulk-assign-bibs" class="amacarun-btn amacarun-btn-primary">
                🏷️ <?php _e('Assegna Pettorali', 'amacarun-race-manager'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=amacarun-export'); ?>" class="amacarun-btn amacarun-btn-outline">
                📤 <?php _e('Esporta', 'amacarun-race-manager'); ?>
            </a>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="amacarun-stats-grid">
        <div class="amacarun-stat-card participants">
            <div class="amacarun-stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="amacarun-stat-label"><?php _e('Partecipanti Totali', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card adults">
            <div class="amacarun-stat-number"><?php echo $stats['adults'] ?? 0; ?></div>
            <div class="amacarun-stat-label"><?php _e('Adulti', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card children">
            <div class="amacarun-stat-number"><?php echo $stats['children'] ?? 0; ?></div>
            <div class="amacarun-stat-label"><?php _e('Bambini', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card checked-in">
            <div class="amacarun-stat-number"><?php echo $stats['checked_in'] ?? 0; ?></div>
            <div class="amacarun-stat-label"><?php _e('Check-in Effettuati', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card bibs">
            <div class="amacarun-stat-number"><?php echo $bib_stats['total_assigned'] ?? 0; ?></div>
            <div class="amacarun-stat-label"><?php _e('Pettorali Assegnati', 'amacarun-race-manager'); ?></div>
        </div>

        <div class="amacarun-stat-card mailpoet">
            <div class="amacarun-stat-number"><?php echo $stats['mailpoet_subscribed'] ?? 0; ?></div>
            <div class="amacarun-stat-label"><?php _e('Iscritti MailPoet', 'amacarun-race-manager'); ?></div>
        </div>
    </div>

    <!-- Filtri e Ricerca -->
    <div class="amacarun-card">
        <div class="amacarun-card-header">
            <h3 class="amacarun-card-title"><?php _e('Filtra Partecipanti', 'amacarun-race-manager'); ?></h3>
        </div>

        <div class="amacarun-form-row">
            <div class="amacarun-form-group">
                <label for="filter-status"><?php _e('Stato', 'amacarun-race-manager'); ?></label>
                <select id="filter-status" class="amacarun-form-control">
                    <option value=""><?php _e('Tutti gli stati', 'amacarun-race-manager'); ?></option>
                    <option value="registered"><?php _e('Registrati', 'amacarun-race-manager'); ?></option>
                    <option value="checked_in"><?php _e('Check-in', 'amacarun-race-manager'); ?></option>
                    <option value="retired"><?php _e('Ritirati', 'amacarun-race-manager'); ?></option>
                </select>
            </div>

            <div class="amacarun-form-group">
                <label for="filter-type"><?php _e('Tipo', 'amacarun-race-manager'); ?></label>
                <select id="filter-type" class="amacarun-form-control">
                    <option value=""><?php _e('Tutti i tipi', 'amacarun-race-manager'); ?></option>
                    <option value="adult"><?php _e('Adulti', 'amacarun-race-manager'); ?></option>
                    <option value="child"><?php _e('Bambini', 'amacarun-race-manager'); ?></option>
                </select>
            </div>

            <div class="amacarun-form-group">
                <label for="filter-bib"><?php _e('Pettorale', 'amacarun-race-manager'); ?></label>
                <select id="filter-bib" class="amacarun-form-control">
                    <option value=""><?php _e('Tutti', 'amacarun-race-manager'); ?></option>
                    <option value="yes"><?php _e('Con pettorale', 'amacarun-race-manager'); ?></option>
                    <option value="no"><?php _e('Senza pettorale', 'amacarun-race-manager'); ?></option>
                </select>
            </div>

            <div class="amacarun-form-group">
                <label for="search-participant"><?php _e('Cerca', 'amacarun-race-manager'); ?></label>
                <input type="text" id="search-participant" class="amacarun-form-control amacarun-search-input" 
                       placeholder="<?php esc_attr_e('Nome, cognome o pettorale...', 'amacarun-race-manager'); ?>">
            </div>
        </div>
    </div>

    <!-- Azioni Bulk -->
    <div class="amacarun-bulk-actions">
        <label><?php _e('Azioni selezionati:', 'amacarun-race-manager'); ?></label>
        
        <select id="bulk-action-select">
            <option value=""><?php _e('Seleziona azione', 'amacarun-race-manager'); ?></option>
            <option value="assign_bibs"><?php _e('Assegna pettorali', 'amacarun-race-manager'); ?></option>
            <option value="remove_bibs"><?php _e('Rimuovi pettorali', 'amacarun-race-manager'); ?></option>
            <option value="mailpoet_subscribe"><?php _e('Iscrivi a MailPoet', 'amacarun-race-manager'); ?></option>
            <option value="export_selected"><?php _e('Esporta selezionati', 'amacarun-race-manager'); ?></option>
        </select>
        
        <button type="button" id="bulk-action-apply" class="amacarun-btn amacarun-btn-secondary">
            <?php _e('Applica', 'amacarun-race-manager'); ?>
        </button>
    </div>

    <!-- Tabella Partecipanti -->
    <div class="amacarun-table-container">
        <div class="amacarun-card-header">
            <h3 class="amacarun-card-title">
                <?php printf(__('Lista Partecipanti (%d)', 'amacarun-race-manager'), count($participants)); ?>
            </h3>
        </div>

        <table class="amacarun-table amacarun-data-table" id="participants-table">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="select-all-participants">
                    </th>
                    <th><?php _e('Pettorale', 'amacarun-race-manager'); ?></th>
                    <th><?php _e('Nome', 'amacarun-race-manager'); ?></th>
                    <th><?php _e('Cognome', 'amacarun-race-manager'); ?></th>
                    <th><?php _e('Email', 'amacarun-race-manager'); ?></th>
                    <th><?php _e('Tipo', 'amacarun-race-manager'); ?></th>
                    <th><?php _e('Stato', 'amacarun-race-manager'); ?></th>
                    <th><?php _e('Distanza', 'amacarun-race-manager'); ?></th>
                    <th><?php _e('MailPoet', 'amacarun-race-manager'); ?></th>
                    <th class="no-sort"><?php _e('Azioni', 'amacarun-race-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($participants)): ?>
                    <?php foreach ($participants as $participant): ?>
                        <tr data-participant-id="<?php echo $participant->id; ?>" 
                            class="participant-row status-<?php echo $participant->status; ?>">
                            
                            <!-- Checkbox selezione -->
                            <td>
                                <input type="checkbox" name="participants[]" value="<?php echo $participant->id; ?>">
                            </td>

                            <!-- Pettorale -->
                            <td class="bib-column">
                                <?php if ($participant->bib_number): ?>
                                    <div class="bib-number-display">
                                        <input type="number" 
                                               class="amacarun-bib-input bib-number-input" 
                                               data-participant-id="<?php echo $participant->id; ?>" 
                                               value="<?php echo $participant->bib_number; ?>" 
                                               min="1001" 
                                               max="9999">
                                    </div>
                                <?php else: ?>
                                    <div class="bib-actions">
                                        <input type="number" 
                                               class="amacarun-bib-input bib-number-input" 
                                               data-participant-id="<?php echo $participant->id; ?>" 
                                               placeholder="N/A" 
                                               min="1001" 
                                               max="9999">
                                        <button type="button" 
                                                class="amacarun-btn amacarun-btn-sm amacarun-btn-primary assign-next-bib" 
                                                data-participant-id="<?php echo $participant->id; ?>">
                                            <?php _e('Auto', 'amacarun-race-manager'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($participant->bib_number): ?>
                                    <button type="button" 
                                            class="amacarun-btn amacarun-btn-sm amacarun-btn-danger remove-bib" 
                                            data-participant-id="<?php echo $participant->id; ?>"
                                            title="<?php esc_attr_e('Rimuovi pettorale', 'amacarun-race-manager'); ?>">
                                        ✕
                                    </button>
                                <?php endif; ?>
                            </td>

                            <!-- Nome -->
                            <td class="participant-name">
                                <?php echo esc_html($participant->first_name); ?>
                            </td>

                            <!-- Cognome -->
                            <td class="participant-name">
                                <?php echo esc_html($participant->last_name); ?>
                            </td>

                            <!-- Email -->
                            <td class="participant-email">
                                <a href="mailto:<?php echo esc_attr($participant->email); ?>">
                                    <?php echo esc_html($participant->email); ?>
                                </a>
                            </td>

                            <!-- Tipo -->
                            <td>
                                <span class="participant-type type-<?php echo $participant->participant_type; ?>">
                                    <?php echo $participant->participant_type === 'adult' ? 
                                        __('Adulto', 'amacarun-race-manager') : 
                                        __('Bambino', 'amacarun-race-manager'); ?>
                                </span>
                            </td>

                            <!-- Stato -->
                            <td>
                                <span class="amacarun-status-badge amacarun-status-<?php echo $participant->status; ?>">
                                    <?php 
                                    $status_labels = array(
                                        'registered' => __('Registrato', 'amacarun-race-manager'),
                                        'checked_in' => __('Check-in', 'amacarun-race-manager'),
                                        'retired' => __('Ritirato', 'amacarun-race-manager')
                                    );
                                    echo $status_labels[$participant->status] ?? ucfirst($participant->status);
                                    ?>
                                </span>
                            </td>

                            <!-- Distanza -->
                            <td>
                                <?php if ($participant->distance): ?>
                                    <span class="distance-badge distance-<?php echo str_replace('km', '', $participant->distance); ?>km">
                                        <?php echo $participant->distance; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- MailPoet -->
                            <td class="mailpoet-column">
                                <?php if ($participant->mailpoet_subscribed): ?>
                                    <span class="mailpoet-status subscribed" title="<?php esc_attr_e('Iscritto a MailPoet', 'amacarun-race-manager'); ?>">
                                        ✅ <?php _e('Iscritto', 'amacarun-race-manager'); ?>
                                    </span>
                                    <button type="button" 
                                            class="amacarun-btn amacarun-btn-sm amacarun-btn-secondary mailpoet-unsubscribe" 
                                            data-participant-id="<?php echo $participant->id; ?>">
                                        <?php _e('Disiscrivi', 'amacarun-race-manager'); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="mailpoet-status not-subscribed">
                                        ❌ <?php _e('Non iscritto', 'amacarun-race-manager'); ?>
                                    </span>
                                    <button type="button" 
                                            class="amacarun-btn amacarun-btn-sm amacarun-btn-primary mailpoet-subscribe" 
                                            data-participant-id="<?php echo $participant->id; ?>">
                                        <?php _e('Iscrivi', 'amacarun-race-manager'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>

                            <!-- Azioni -->
                            <td class="actions-column">
                                <div class="amacarun-btn-group">
                                    <button type="button" 
                                            class="amacarun-btn amacarun-btn-sm amacarun-btn-outline view-participant" 
                                            data-participant-id="<?php echo $participant->id; ?>"
                                            title="<?php esc_attr_e('Visualizza dettagli', 'amacarun-race-manager'); ?>">
                                        👁️
                                    </button>
                                    
                                    <button type="button" 
                                            class="amacarun-btn amacarun-btn-sm amacarun-btn-secondary edit-participant" 
                                            data-participant-id="<?php echo $participant->id; ?>"
                                            title="<?php esc_attr_e('Modifica partecipante', 'amacarun-race-manager'); ?>">
                                        ✏️
                                    </button>
                                    
                                    <?php if ($participant->status === 'registered'): ?>
                                        <a href="<?php echo admin_url('admin.php?page=amacarun-checkin&search=' . urlencode($participant->first_name . ' ' . $participant->last_name)); ?>" 
                                           class="amacarun-btn amacarun-btn-sm amacarun-btn-success"
                                           title="<?php esc_attr_e('Vai al check-in', 'amacarun-race-manager'); ?>">
                                            ✅
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button type="button" 
                                            class="amacarun-btn amacarun-btn-sm amacarun-btn-danger delete-participant" 
                                            data-participant-id="<?php echo $participant->id; ?>"
                                            data-confirm="<?php esc_attr_e('Sei sicuro di voler eliminare questo partecipante?', 'amacarun-race-manager'); ?>"
                                            title="<?php esc_attr_e('Elimina partecipante', 'amacarun-race-manager'); ?>">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="no-results">
                            <div class="amacarun-alert amacarun-alert-warning">
                                <?php _e('Nessun partecipante trovato per questo evento.', 'amacarun-race-manager'); ?>
                                <br>
                                <button type="button" id="sync-woocommerce-inline" class="amacarun-btn amacarun-btn-primary amacarun-mt-1">
                                    🔄 <?php _e('Sincronizza da WooCommerce', 'amacarun-race-manager'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Statistiche Pettorali -->
    <?php if (!empty($bib_stats)): ?>
    <div class="amacarun-card">
        <div class="amacarun-card-header">
            <h3 class="amacarun-card-title"><?php _e('Statistiche Pettorali', 'amacarun-race-manager'); ?></h3>
        </div>

        <div class="amacarun-form-row">
            <div class="amacarun-form-group">
                <label><?php _e('Range Pettorali', 'amacarun-race-manager'); ?></label>
                <div class="amacarun-form-control" style="background: #f8f9fa; border: none;">
                    <?php printf(
                        __('Inizio: %d - Corrente: %d', 'amacarun-race-manager'),
                        $bib_stats['range_start'] ?? 1001,
                        $bib_stats['range_current'] ?? 1001
                    ); ?>
                </div>
            </div>

            <div class="amacarun-form-group">
                <label><?php _e('Assegnati', 'amacarun-race-manager'); ?></label>
                <div class="amacarun-form-control" style="background: #f8f9fa; border: none;">
                    <?php echo $bib_stats['total_assigned'] ?? 0; ?> / <?php echo $stats['total'] ?? 0; ?>
                </div>
            </div>

            <div class="amacarun-form-group">
                <label><?php _e('Mancanti', 'amacarun-race-manager'); ?></label>
                <div class="amacarun-form-control" style="background: #f8f9fa; border: none;">
                    <?php echo $bib_stats['missing_count'] ?? 0; ?>
                    <?php if (!empty($bib_stats['missing_bibs'])): ?>
                        <small>(<?php echo implode(', ', array_slice($bib_stats['missing_bibs'], 0, 5)); ?>...)</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="amacarun-btn-group">
            <button type="button" id="compact-bibs" class="amacarun-btn amacarun-btn-secondary">
                🗜️ <?php _e('Compatta Numerazione', 'amacarun-race-manager'); ?>
            </button>
            
            <button type="button" id="validate-bibs" class="amacarun-btn amacarun-btn-outline">
                ✅ <?php _e('Valida Integrità', 'amacarun-race-manager'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Input nascosti per JavaScript -->
    <input type="hidden" id="current-event-id" value="<?php echo $active_event->id; ?>">
    
    <!-- Nonces per sicurezza -->
    <?php wp_nonce_field('amacarun_admin_action', '_wpnonce'); ?>
</div>

<!-- Modal per dettagli partecipante -->
<div class="amacarun-modal-overlay" id="participant-details-modal">
    <div class="amacarun-modal">
        <div class="amacarun-modal-header">
            <h3 class="amacarun-modal-title"><?php _e('Dettagli Partecipante', 'amacarun-race-manager'); ?></h3>
            <button type="button" class="amacarun-modal-close">&times;</button>
        </div>
        <div class="amacarun-modal-body">
            <div id="participant-details-content">
                <!-- Contenuto caricato via AJAX -->
            </div>
        </div>
        <div class="amacarun-modal-footer">
            <button type="button" class="amacarun-btn amacarun-btn-secondary amacarun-modal-close">
                <?php _e('Chiudi', 'amacarun-race-manager'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Stili specifici per la tabella partecipanti */
.participant-row.status-checked_in {
    background-color: rgba(76, 175, 80, 0.05);
}

.participant-row.status-retired {
    background-color: rgba(255, 152, 0, 0.05);
    opacity: 0.7;
}

.bib-column {
    text-align: center;
    min-width: 120px;
}

.bib-actions {
    display: flex;
    gap: 5px;
    align-items: center;
}

.actions-column {
    width: 200px;
    text-align: center;
}

.mailpoet-column {
    min-width: 120px;
    text-align: center;
}

.mailpoet-status.subscribed {
    color: var(--amacarun-success);
    font-weight: 600;
}

.mailpoet-status.not-subscribed {
    color: var(--amacarun-warning);
}

.participant-type.type-adult {
    color: var(--amacarun-info);
    font-weight: 600;
}

.participant-type.type-child {
    color: var(--amacarun-warning);
    font-weight: 600;
}

.no-results {
    text-align: center;
    padding: 40px 20px;
}

/* Responsive per mobile */
@media (max-width: 768px) {
    .amacarun-table {
        font-size: 0.85rem;
    }
    
    .actions-column .amacarun-btn {
        padding: 6px 8px;
        font-size: 0.75rem;
    }
    
    .bib-actions {
        flex-direction: column;
        gap: 3px;
    }
    
    .amacarun-bib-input {
        width: 80px;
    }
}
</style>

<script>
// JavaScript specifico per questa pagina
jQuery(document).ready(function($) {
    
    // Gestione visualizzazione dettagli partecipante
    $('.view-participant').on('click', function() {
        const participantId = $(this).data('participant-id');
        
        // Carica dettagli via AJAX
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_get_participant_details',
                participant_id: participantId,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#participant-details-content').html(response.data.html);
                    $('#participant-details-modal').addClass('active');
                }
            }
        });
    });
    
    // Validazione integrità pettorali
    $('#validate-bibs').on('click', function() {
        const eventId = $('#current-event-id').val();
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_validate_bib_integrity',
                event_id: eventId,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.valid) {
                        AmacarUN.UI.showMessage('✅ Integrità pettorali OK!', 'success');
                    } else {
                        let message = '⚠️ Problemi rilevati:\n';
                        response.data.issues.forEach(function(issue) {
                            message += '• ' + issue + '\n';
                        });
                        alert(message);
                    }
                }
            }
        });
    });
    
    // Compatta numerazione pettorali
    $('#compact-bibs').on('click', function() {
        if (!confirm('Ricompattare la numerazione dei pettorali? Questa operazione riassegnerà i numeri in sequenza.')) {
            return;
        }
        
        const eventId = $('#current-event-id').val();
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_compact_bibs',
                event_id: eventId,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    AmacarUN.UI.showMessage(`Numerazione compattata: ${response.data.reassigned} pettorali riassegnati`, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            }
        });
    });
    
    // Filtri in tempo reale
    let filterTimeout;
    $('#filter-status, #filter-type, #filter-bib, #search-participant').on('change keyup', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(function() {
            filterParticipants();
        }, 300);
    });
    
    function filterParticipants() {
        const status = $('#filter-status').val();
        const type = $('#filter-type').val();
        const bib = $('#filter-bib').val();
        const search = $('#search-participant').val().toLowerCase();
        
        $('#participants-table tbody tr').each(function() {
            const $row = $(this);
            let show = true;
            
            // Filtro stato
            if (status && !$row.hasClass('status-' + status)) {
                show = false;
            }
            
            // Filtro tipo
            if (type) {
                const rowType = $row.find('.participant-type').hasClass('type-' + type);
                if (!rowType) show = false;
            }
            
            // Filtro pettorale
            if (bib === 'yes') {
                const hasBib = $row.find('.bib-number-input').val();
                if (!hasBib) show = false;
            } else if (bib === 'no') {
                const hasBib = $row.find('.bib-number-input').val();
                if (hasBib) show = false;
            }
            
            // Ricerca testo
            if (search) {
                const name = $row.find('.participant-name').text().toLowerCase();
                const email = $row.find('.participant-email').text().toLowerCase();
                const bibNum = $row.find('.bib-number-input').val() || '';
                
                if (name.indexOf(search) === -1 && 
                    email.indexOf(search) === -1 && 
                    bibNum.indexOf(search) === -1) {
                    show = false;
                }
            }
            
            // Mostra/nascondi riga
            if (show) {
                $row.show();
            } else {
                $row.hide();
            }
        });
        
        // Aggiorna contatore risultati
        const visibleRows = $('#participants-table tbody tr:visible').length;
        const totalRows = $('#participants-table tbody tr').length;
        
        if (visibleRows < totalRows) {
            $('.amacarun-card-title').text(`Lista Partecipanti (${visibleRows}/${totalRows})`);
        } else {
            $('.amacarun-card-title').text(`Lista Partecipanti (${totalRows})`);
        }
    }
});
</script>