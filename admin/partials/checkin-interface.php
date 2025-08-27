<?php
/**
 * Template Check-in Interface - AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Variabili disponibili: $active_event, $checkin_stats
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
            <button type="button" id="add-onsite-participant" class="amacarun-btn amacarun-btn-primary">
                ➕ <?php _e('Iscrizione sul posto', 'amacarun-race-manager'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=amacarun-participants'); ?>" class="amacarun-btn amacarun-btn-outline">
                👥 <?php _e('Lista Partecipanti', 'amacarun-race-manager'); ?>
            </a>
        </div>
    </div>

    <!-- Statistiche Check-in -->
    <div class="amacarun-checkin-container">
        <!-- Area principale check-in -->
        <div class="amacarun-checkin-search">
            <div class="amacarun-card">
                <div class="amacarun-card-header">
                    <h3 class="amacarun-card-title"><?php _e('Ricerca Partecipante', 'amacarun-race-manager'); ?></h3>
                </div>

                <!-- Form ricerca -->
                <div class="amacarun-form-group">
                    <input type="text" 
                           id="participant-search" 
                           class="amacarun-form-control amacarun-search-input" 
                           placeholder="<?php esc_attr_e('Cerca per nome, cognome o numero pettorale...', 'amacarun-race-manager'); ?>" 
                           autocomplete="off">
                </div>

                <!-- Risultati ricerca -->
                <div id="search-results" class="search-results-container">
                    <!-- Risultati popolati via JavaScript -->
                </div>

                <!-- Partecipante selezionato -->
                <div id="selected-participant" class="selected-participant-container" style="display: none;">
                    <div class="amacarun-card" style="border: 2px solid var(--amacarun-primary);">
                        <div class="amacarun-card-header">
                            <h4><?php _e('Partecipante Selezionato', 'amacarun-race-manager'); ?></h4>
                        </div>
                        
                        <div id="participant-info">
                            <!-- Info partecipante popolate via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Azioni Check-in -->
                <div id="checkin-actions" class="checkin-actions-container" style="display: none;">
                    <div class="amacarun-form-row">
                        <div class="amacarun-form-group">
                            <label for="distance-select"><?php _e('Seleziona Distanza', 'amacarun-race-manager'); ?></label>
                            <select id="distance-select" class="amacarun-form-control">
                                <option value=""><?php _e('Seleziona distanza', 'amacarun-race-manager'); ?></option>
                                <option value="4km">4km</option>
                                <option value="11km">11km</option>
                            </select>
                        </div>
                    </div>

                    <div class="amacarun-btn-group">
                        <button type="button" id="checkin-participant" class="amacarun-btn amacarun-btn-success amacarun-btn-lg">
                            ✅ <?php _e('CONFERMA CHECK-IN', 'amacarun-race-manager'); ?>
                        </button>
                        
                        <button type="button" id="retire-participant" class="amacarun-btn amacarun-btn-warning">
                            ❌ <?php _e('Ritira Partecipante', 'amacarun-race-manager'); ?>
                        </button>
                        
                        <button type="button" id="cancel-selection" class="amacarun-btn amacarun-btn-secondary">
                            <?php _e('Annulla', 'amacarun-race-manager'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pannello statistiche -->
        <div class="amacarun-checkin-stats">
            <h3><?php _e('Statistiche Check-in', 'amacarun-race-manager'); ?></h3>
            
            <div class="stat-item">
                <div class="stat-number checkin-total"><?php echo $checkin_stats['total'] ?? 0; ?></div>
                <div class="stat-label"><?php _e('Totale Partecipanti', 'amacarun-race-manager'); ?></div>
            </div>

            <div class="stat-item">
                <div class="stat-number checkin-completed"><?php echo $checkin_stats['checked_in'] ?? 0; ?></div>
                <div class="stat-label"><?php _e('Check-in Completati', 'amacarun-race-manager'); ?></div>
            </div>

            <div class="stat-item">
                <div class="stat-number checkin-remaining">
                    <?php echo ($checkin_stats['total'] - $checkin_stats['checked_in']) ?? 0; ?>
                </div>
                <div class="stat-label"><?php _e('Rimanenti', 'amacarun-race-manager'); ?></div>
            </div>

            <!-- Progress bar -->
            <div class="amacarun-progress">
                <?php 
                $percentage = $checkin_stats['total'] > 0 ? 
                    round(($checkin_stats['checked_in'] / $checkin_stats['total']) * 100) : 0;
                ?>
                <div class="amacarun-progress-bar checkin-progress-bar" 
                     style="width: <?php echo $percentage; ?>%">
                    <?php echo $percentage; ?>%
                </div>
            </div>

            <!-- Azioni rapide -->
            <div class="quick-actions" style="margin-top: 20px;">
                <h4><?php _e('Azioni Rapide', 'amacarun-race-manager'); ?></h4>
                
                <button type="button" id="view-pending" class="amacarun-btn amacarun-btn-sm amacarun-btn-outline" style="width: 100%; margin-bottom: 10px;">
                    👀 <?php _e('Visualizza In Attesa', 'amacarun-race-manager'); ?>
                </button>
                
                <button type="button" id="refresh-stats" class="amacarun-btn amacarun-btn-sm amacarun-btn-secondary" style="width: 100%; margin-bottom: 10px;">
                    🔄 <?php _e('Aggiorna Statistiche', 'amacarun-race-manager'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=amacarun-export'); ?>" 
                   class="amacarun-btn amacarun-btn-sm amacarun-btn-outline" 
                   style="width: 100%; margin-bottom: 10px; text-decoration: none; display: inline-block; text-align: center;">
                    📤 <?php _e('Esporta Check-in', 'amacarun-race-manager'); ?>
                </a>
            </div>

            <!-- Info evento -->
            <div class="event-info" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                <h4><?php _e('Info Evento', 'amacarun-race-manager'); ?></h4>
                <p style="margin: 5px 0; opacity: 0.9;">
                    <strong><?php echo esc_html($active_event->name); ?></strong>
                </p>
                <p style="margin: 5px 0; opacity: 0.9;">
                    📅 <?php echo date_i18n(get_option('date_format'), strtotime($active_event->date)); ?>
                </p>
                <p style="margin: 5px 0; opacity: 0.9;">
                    🏷️ <?php printf(__('Pettorali: %d - %d', 'amacarun-race-manager'), 
                        $active_event->bib_number_start, 
                        $active_event->bib_number_current - 1); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Input nascosti -->
    <input type="hidden" id="current-event-id" value="<?php echo $active_event->id; ?>">
    <input type="hidden" id="selected-participant-id" value="">
</div>

<!-- Modal iscrizione sul posto -->
<div class="amacarun-modal-overlay" id="onsite-modal">
    <div class="amacarun-modal">
        <div class="amacarun-modal-header">
            <h3 class="amacarun-modal-title"><?php _e('Iscrizione sul Posto', 'amacarun-race-manager'); ?></h3>
            <button type="button" class="amacarun-modal-close">&times;</button>
        </div>
        
        <div class="amacarun-modal-body">
            <form id="add-onsite-form">
                <div class="amacarun-form-row">
                    <div class="amacarun-form-group">
                        <label for="onsite-first-name"><?php _e('Nome *', 'amacarun-race-manager'); ?></label>
                        <input type="text" id="onsite-first-name" name="first_name" class="amacarun-form-control" required>
                    </div>
                    
                    <div class="amacarun-form-group">
                        <label for="onsite-last-name"><?php _e('Cognome *', 'amacarun-race-manager'); ?></label>
                        <input type="text" id="onsite-last-name" name="last_name" class="amacarun-form-control" required>
                    </div>
                </div>

                <div class="amacarun-form-row">
                    <div class="amacarun-form-group">
                        <label for="onsite-email"><?php _e('Email *', 'amacarun-race-manager'); ?></label>
                        <input type="email" id="onsite-email" name="email" class="amacarun-form-control" required>
                    </div>
                    
                    <div class="amacarun-form-group">
                        <label for="onsite-phone"><?php _e('Telefono', 'amacarun-race-manager'); ?></label>
                        <input type="tel" id="onsite-phone" name="phone" class="amacarun-form-control">
                    </div>
                </div>

                <div class="amacarun-form-row">
                    <div class="amacarun-form-group">
                        <label for="onsite-type"><?php _e('Tipologia *', 'amacarun-race-manager'); ?></label>
                        <select id="onsite-type" name="participant_type" class="amacarun-form-control" required>
                            <option value=""><?php _e('Seleziona tipologia', 'amacarun-race-manager'); ?></option>
                            <option value="adult"><?php _e('Adulto', 'amacarun-race-manager'); ?></option>
                            <option value="child"><?php _e('Bambino', 'amacarun-race-manager'); ?></option>
                        </select>
                    </div>
                    
                    <div class="amacarun-form-group">
                        <label for="onsite-distance"><?php _e('Distanza *', 'amacarun-race-manager'); ?></label>
                        <select id="onsite-distance" name="distance" class="amacarun-form-control" required>
                            <option value=""><?php _e('Seleziona distanza', 'amacarun-race-manager'); ?></option>
                            <option value="4km">4km</option>
                            <option value="11km">11km</option>
                        </select>
                    </div>
                </div>

                <div class="amacarun-form-group">
                    <label for="onsite-payment-amount"><?php _e('Importo Pagamento (€)', 'amacarun-race-manager'); ?></label>
                    <input type="number" id="onsite-payment-amount" name="payment_amount" class="amacarun-form-control" 
                           min="0" step="0.01" placeholder="0.00">
                </div>

                <div class="amacarun-checkbox-wrapper">
                    <input type="checkbox" id="onsite-association-member" name="association_member" value="1">
                    <label for="onsite-association-member"><?php _e('Membro dell\'associazione', 'amacarun-race-manager'); ?></label>
                </div>

                <div class="amacarun-checkbox-wrapper">
                    <input type="checkbox" id="onsite-auto-checkin" name="auto_checkin" value="1" checked>
                    <label for="onsite-auto-checkin"><?php _e('Effettua check-in automatico', 'amacarun-race-manager'); ?></label>
                </div>
            </form>
        </div>
        
        <div class="amacarun-modal-footer">
            <button type="button" class="amacarun-btn amacarun-btn-secondary amacarun-modal-close">
                <?php _e('Annulla', 'amacarun-race-manager'); ?>
            </button>
            <button type="submit" form="add-onsite-form" class="amacarun-btn amacarun-btn-primary">
                💾 <?php _e('Iscivi e Check-in', 'amacarun-race-manager'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Stili specifici per check-in */
.search-results-container {
    max-height: 400px;
    overflow-y: auto;
    margin-top: 15px;
}

.selected-participant-container {
    margin-top: 20px;
}

.checkin-actions-container {
    margin-top: 20px;
    padding: 20px;
    background: var(--amacarun-light);
    border-radius: var(--amacarun-radius);
    border-left: 4px solid var(--amacarun-success);
}

.amacarun-checkin-stats .stat-item {
    margin-bottom: 20px;
    text-align: center;
}

.amacarun-checkin-stats .stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: #fff;
    display: block;
    margin-bottom: 5px;
}

.amacarun-checkin-stats .stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.quick-actions h4,
.event-info h4 {
    color: #fff;
    margin-bottom: 15px;
    font-size: 1.1rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .amacarun-checkin-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .amacarun-checkin-stats {
        order: -1;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Variabili globali
    let searchTimeout;
    let selectedParticipant = null;
    
    // Event handlers
    $('#participant-search').on('keyup', handleParticipantSearch);
    $(document).on('click', '.participant-result', selectParticipant);
    $('#checkin-participant').on('click', performCheckin);
    $('#retire-participant').on('click', retireParticipant);
    $('#cancel-selection').on('click', cancelSelection);
    $('#add-onsite-participant').on('click', showOnsiteModal);
    $('#add-onsite-form').on('submit', addOnsiteParticipant);
    $('#refresh-stats').on('click', refreshStats);
    $('#view-pending').on('click', viewPendingParticipants);
    
    // Ricerca partecipanti
    function handleParticipantSearch() {
        const query = $(this).val().trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            $('#search-results').empty();
            return;
        }
        
        $('#search-results').html('<div class="loading">🔍 Ricerca in corso...</div>');
        
        searchTimeout = setTimeout(function() {
            searchParticipants(query);
        }, 300);
    }
    
    // Esegui ricerca
    function searchParticipants(query) {
        const eventId = $('#current-event-id').val();
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_search_participant',
                query: query,
                event_id: eventId,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data.participants);
                } else {
                    $('#search-results').html('<div class="no-results">Nessun partecipante trovato</div>');
                }
            },
            error: function() {
                $('#search-results').html('<div class="error">Errore nella ricerca</div>');
            }
        });
    }
    
    // Mostra risultati ricerca
    function displaySearchResults(participants) {
        let html = '';
        
        if (participants.length === 0) {
            html = '<div class="no-results">Nessun partecipante trovato</div>';
        } else {
            participants.forEach(function(participant) {
                const statusClass = 'status-' + participant.status;
                const bibDisplay = participant.bib_number ? '#' + participant.bib_number : 'N/A';
                const typeLabel = participant.participant_type === 'adult' ? 'Adulto' : 'Bambino';
                const statusLabel = getStatusLabel(participant.status);
                
                html += `
                    <div class="amacarun-participant-card participant-result ${statusClass}" 
                         data-participant='${JSON.stringify(participant)}'>
                        <div class="amacarun-participant-header">
                            <div class="amacarun-participant-name">
                                ${participant.first_name} ${participant.last_name}
                            </div>
                            <div class="amacarun-participant-bib">${bibDisplay}</div>
                        </div>
                        <div class="amacarun-participant-meta">
                            <span class="participant-type">${typeLabel}</span>
                            <span class="participant-status">${statusLabel}</span>
                            <span class="participant-distance">${participant.distance || 'N/A'}</span>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#search-results').html(html);
    }
    
    // Seleziona partecipante
    function selectParticipant() {
        const participantData = $(this).data('participant');
        selectedParticipant = participantData;
        
        // Rimuovi selezione precedente
        $('.participant-result').removeClass('selected');
        $(this).addClass('selected');
        
        // Mostra informazioni partecipante
        displayParticipantInfo(participantData);
        
        // Mostra azioni
        $('#selected-participant').show();
        $('#checkin-actions').show();
        
        // Aggiorna ID nascosto
        $('#selected-participant-id').val(participantData.id);
        
        // Pre-seleziona distanza se già presente
        if (participantData.distance) {
            $('#distance-select').val(participantData.distance);
        }
        
        // Scroll verso le azioni
        $('html, body').animate({
            scrollTop: $('#selected-participant').offset().top - 20
        }, 500);
    }
    
    // Mostra info partecipante
    function displayParticipantInfo(participant) {
        const statusLabel = getStatusLabel(participant.status);
        const typeLabel = participant.participant_type === 'adult' ? 'Adulto' : 'Bambino';
        const bibDisplay = participant.bib_number ? '#' + participant.bib_number : 'Non assegnato';
        
        let html = `
            <div class="participant-details">
                <h4>${participant.first_name} ${participant.last_name}</h4>
                <div class="detail-row">
                    <span class="label">Email:</span>
                    <span class="value">${participant.email}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Telefono:</span>
                    <span class="value">${participant.phone || 'Non specificato'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Tipo:</span>
                    <span class="value">${typeLabel}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Pettorale:</span>
                    <span class="value">${bibDisplay}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Stato:</span>
                    <span class="value">
                        <span class="amacarun-status-badge amacarun-status-${participant.status}">
                            ${statusLabel}
                        </span>
                    </span>
                </div>
        `;
        
        if (participant.distance) {
            html += `
                <div class="detail-row">
                    <span class="label">Distanza:</span>
                    <span class="value">${participant.distance}</span>
                </div>
            `;
        }
        
        if (participant.check_in_time) {
            html += `
                <div class="detail-row">
                    <span class="label">Check-in:</span>
                    <span class="value">${formatDateTime(participant.check_in_time)}</span>
                </div>
            `;
        }
        
        html += '</div>';
        
        $('#participant-info').html(html);
    }
    
    // Esegui check-in
    function performCheckin() {
        if (!selectedParticipant) {
            alert('Nessun partecipante selezionato');
            return;
        }
        
        const distance = $('#distance-select').val();
        if (!distance) {
            alert('Seleziona una distanza');
            return;
        }
        
        if (selectedParticipant.status === 'checked_in') {
            if (!confirm('Questo partecipante ha già effettuato il check-in. Vuoi aggiornare la distanza?')) {
                return;
            }
        }
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_checkin_participant',
                participant_id: selectedParticipant.id,
                distance: distance,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    AmacarUN.UI.showMessage('✅ Check-in completato con successo!', 'success');
                    
                    // Reset form
                    resetCheckInForm();
                    
                    // Aggiorna statistiche
                    refreshStats();
                } else {
                    AmacarUN.UI.showMessage('❌ Errore nel check-in: ' + response.data.message, 'error');
                }
            }
        });
    }
    
    // Ritira partecipante
    function retireParticipant() {
        if (!selectedParticipant) {
            alert('Nessun partecipante selezionato');
            return;
        }
        
        const notes = prompt('Note ritiro (opzionale):');
        if (notes === null) return; // Cancelled
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_retire_participant',
                participant_id: selectedParticipant.id,
                notes: notes,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    AmacarUN.UI.showMessage('❌ Partecipante ritirato', 'warning');
                    
                    // Reset form
                    resetCheckInForm();
                    
                    // Aggiorna statistiche
                    refreshStats();
                }
            }
        });
    }
    
    // Annulla selezione
    function cancelSelection() {
        resetCheckInForm();
    }
    
    // Reset form check-in
    function resetCheckInForm() {
        selectedParticipant = null;
        $('#selected-participant-id').val('');
        $('#selected-participant').hide();
        $('#checkin-actions').hide();
        $('#distance-select').val('');
        $('#participant-search').val('').focus();
        $('#search-results').empty();
        $('.participant-result').removeClass('selected');
    }
    
    // Mostra modal iscrizione sul posto
    function showOnsiteModal() {
        $('#onsite-modal').addClass('active');
        $('#onsite-first-name').focus();
    }
    
    // Aggiungi partecipante sul posto
    function addOnsiteParticipant(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'amacarun_add_onsite_participant');
        formData.append('nonce', amacarun_admin.nonce);
        formData.append('event_id', $('#current-event-id').val());
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    AmacarUN.UI.showMessage('✅ Partecipante aggiunto e check-in effettuato!', 'success');
                    
                    // Reset form
                    $('#add-onsite-form')[0].reset();
                    $('#onsite-modal').removeClass('active');
                    
                    // Aggiorna statistiche
                    refreshStats();
                } else {
                    AmacarUN.UI.showMessage('❌ Errore: ' + response.data.message, 'error');
                }
            }
        });
    }
    
    // Aggiorna statistiche
    function refreshStats() {
        const eventId = $('#current-event-id').val();
        
        $.ajax({
            url: amacarun_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'amacarun_get_checkin_stats',
                event_id: eventId,
                nonce: amacarun_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const stats = response.data;
                    $('.checkin-total').text(stats.total);
                    $('.checkin-completed').text(stats.checked_in);
                    $('.checkin-remaining').text(stats.remaining);
                    
                    // Aggiorna progress bar
                    const percentage = stats.total > 0 ? Math.round((stats.checked_in / stats.total) * 100) : 0;
                    $('.checkin-progress-bar').css('width', percentage + '%').text(percentage + '%');
                }
            }
        });
    }
    
    // Visualizza partecipanti in attesa
    function viewPendingParticipants() {
        window.open(
            '<?php echo admin_url('admin.php?page=amacarun-participants&filter_status=registered'); ?>',
            '_blank'
        );
    }
    
    // Utility functions
    function getStatusLabel(status) {
        const labels = {
            'registered': 'Registrato',
            'checked_in': 'Check-in',
            'retired': 'Ritirato'
        };
        return labels[status] || status;
    }
    
    function formatDateTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleString('it-IT');
    }
    
    // Auto-refresh statistiche ogni 30 secondi
    setInterval(refreshStats, 30000);
    
    // Focus automatico sul campo ricerca
    $('#participant-search').focus();
});
</script>