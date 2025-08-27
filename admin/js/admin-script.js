/**
     * Gestione Check-in
     */
    AmacarUN.Checkin = {
        
        init: function() {
            this.bindEvents();
            this.initSearch();
            this.loadStats();
        },
        
        bindEvents: function() {
            $(document).on('click', '.participant-card', this.selectParticipant);
            $(document).on('click', '#checkin-participant', this.performCheckin);
            $(document).on('click', '#retire-participant', this.retireParticipant);
            $(document).on('submit', '#add-onsite-form', this.addOnsiteParticipant);
        },
        
        initSearch: function() {
            let searchTimeout;
            
            $('#participant-search').on('keyup', function() {
                const query = $(this).val();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    $('#search-results').empty();
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    AmacarUN.Checkin.searchParticipants(query);
                }, 300);
            });
        },
        
        searchParticipants: function(query) {
            const eventId = $('#current-event-id').val();
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage('Partecipante aggiunto e check-in effettuato!', 'success');
                        
                        // Reset form
                        $('#add-onsite-form')[0].reset();
                        $('#onsite-modal').removeClass('active');
                        
                        // Aggiorna statistiche
                        AmacarUN.Checkin.loadStats();
                    }
                }
            });
        },
        
        loadStats: function() {
            const eventId = $('#current-event-id').val();
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_get_checkin_stats',
                    event_id: eventId,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
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
    };
    
    /**
     * Gestione Export
     */
    AmacarUN.Export = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('click', '#export-csv', this.exportCSV);
            $(document).on('click', '#export-labels', this.exportLabels);
            $(document).on('click', '#export-stats', this.exportStats);
            $(document).on('change', '#export-event-select', this.updateExportOptions);
        },
        
        exportCSV: function(e) {
            e.preventDefault();
            
            const eventId = $('#export-event-select').val();
            const options = {
                status: $('#csv-status-filter').val(),
                type: $('#csv-type-filter').val(),
                distance: $('#csv-distance-filter').val(),
                has_bib: $('#csv-bib-filter').val(),
                format: $('#csv-format-select').val()
            };
            
            if (!eventId) {
                alert('Seleziona un evento');
                return;
            }
            
            const updateProgress = AmacarUN.UI.showProgress('Export CSV', function(update) {
                $.ajax({
                    url: amacarun_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'amacarun_export_csv',
                        event_id: eventId,
                        ...options,
                        nonce: amacarun_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            update(100, `Export completato: ${response.data.count} partecipanti`);
                            
                            // Crea link download
                            const downloadLink = `<a href="${response.data.url}" class="amacarun-btn amacarun-btn-primary" download>📥 Scarica CSV (${response.data.filename})</a>`;
                            
                            setTimeout(function() {
                                AmacarUN.UI.showMessage(downloadLink, 'success', 10000);
                            }, 1000);
                        } else {
                            update(0, 'Errore nell\'export');
                        }
                    }
                });
            });
        },
        
        exportLabels: function(e) {
            e.preventDefault();
            
            const eventId = $('#export-event-select').val();
            const options = {
                format: $('#labels-format-select').val(),
                label_format: $('#labels-content-select').val(),
                include_qr: $('#labels-include-qr').is(':checked')
            };
            
            if (!eventId) {
                alert('Seleziona un evento');
                return;
            }
            
            const updateProgress = AmacarUN.UI.showProgress('Export Etichette', function(update) {
                $.ajax({
                    url: amacarun_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'amacarun_export_labels',
                        event_id: eventId,
                        ...options,
                        nonce: amacarun_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            update(100, `${response.data.count} etichette generate`);
                            
                            const downloadLink = `<a href="${response.data.url}" class="amacarun-btn amacarun-btn-primary" target="_blank">🏷️ Apri Etichette</a>`;
                            
                            setTimeout(function() {
                                AmacarUN.UI.showMessage(downloadLink, 'success', 10000);
                            }, 1000);
                        } else {
                            update(0, 'Errore nella generazione etichette');
                        }
                    }
                });
            });
        },
        
        exportStats: function(e) {
            e.preventDefault();
            
            const eventId = $('#export-event-select').val();
            const format = $('#stats-format-select').val();
            
            if (!eventId) {
                alert('Seleziona un evento');
                return;
            }
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_export_stats',
                    event_id: eventId,
                    format: format,
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const downloadLink = `<a href="${response.data.url}" class="amacarun-btn amacarun-btn-primary" target="_blank">📊 Apri Report</a>`;
                        AmacarUN.UI.showMessage('Report generato! ' + downloadLink, 'success', 8000);
                    }
                }
            });
        },
        
        updateExportOptions: function() {
            const eventId = $(this).val();
            
            if (!eventId) {
                $('.export-section').hide();
                return;
            }
            
            $('.export-section').show();
            
            // Carica statistiche evento per informazioni
            AmacarUN.Export.loadEventInfo(eventId);
        },
        
        loadEventInfo: function(eventId) {
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_get_event_info',
                    event_id: eventId,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        const event = response.data;
                        $('#export-event-info').html(`
                            <div class="amacarun-card">
                                <h3>${event.name}</h3>
                                <p><strong>Data:</strong> ${event.date}</p>
                                <p><strong>Partecipanti:</strong> ${event.participants_count}</p>
                                <p><strong>Check-in:</strong> ${event.checked_in_count}</p>
                            </div>
                        `).show();
                    }
                }
            });
        }
    };
    
    /**
     * Gestione Eventi
     */
    AmacarUN.Events = {
        
        init: function() {
            this.bindEvents();
            this.initFormValidation();
        },
        
        bindEvents: function() {
            $(document).on('click', '.activate-event', this.activateEvent);
            $(document).on('click', '.duplicate-event', this.duplicateEvent);
            $(document).on('submit', '#event-form', this.saveEvent);
            $(document).on('change', '#woocommerce-category-id', this.loadWooCommerceProducts);
        },
        
        activateEvent: function(e) {
            e.preventDefault();
            
            if (!confirm('Attivare questo evento? L\'evento attualmente attivo verrà disattivato.')) {
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
                        AmacarUN.UI.showMessage('Evento attivato con successo!', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                }
            });
        },
        
        duplicateEvent: function(e) {
            e.preventDefault();
            
            const eventId = $(this).data('event-id');
            const newName = prompt('Nome per il nuovo evento:');
            
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
                        AmacarUN.UI.showMessage('Evento duplicato!', 'success');
                        setTimeout(function() {
                            window.location.href = `admin.php?page=amacarun-events&action=edit&event_id=${response.data.new_event_id}`;
                        }, 1500);
                    }
                }
            });
        },
        
        saveEvent: function(e) {
            e.preventDefault();
            
            if (!AmacarUN.Events.validateEventForm()) {
                return;
            }
            
            const formData = new FormData(this);
            const isEdit = formData.get('event_id') > 0;
            
            formData.append('action', isEdit ? 'amacarun_update_event' : 'amacarun_create_event');
            formData.append('nonce', amacarun_admin.nonce);
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage(isEdit ? 'Evento aggiornato!' : 'Evento creato!', 'success');
                        
                        if (!isEdit) {
                            setTimeout(function() {
                                window.location.href = 'admin.php?page=amacarun-events';
                            }, 1500);
                        }
                    }
                }
            });
        },
        
        validateEventForm: function() {
            let isValid = true;
            
            // Nome evento
            const eventName = $('#event-name').val().trim();
            if (!eventName) {
                $('#event-name').addClass('error');
                isValid = false;
            } else {
                $('#event-name').removeClass('error');
            }
            
            // Data evento
            const eventDate = $('#event-date').val();
            if (!eventDate) {
                $('#event-date').addClass('error');
                isValid = false;
            } else {
                $('#event-date').removeClass('error');
            }
            
            if (!isValid) {
                AmacarUN.UI.showMessage('Compila tutti i campi obbligatori', 'error');
            }
            
            return isValid;
        },
        
        loadWooCommerceProducts: function() {
            const categoryId = $(this).val();
            
            if (!categoryId) {
                $('#adult-product-id, #child-product-id').empty().append('<option value="">Seleziona categoria prima</option>');
                return;
            }
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_get_wc_products',
                    category_id: categoryId,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        const products = response.data.products;
                        let options = '<option value="">Seleziona prodotto</option>';
                        
                        products.forEach(function(product) {
                            options += `<option value="${product.id}">${product.name}</option>`;
                        });
                        
                        $('#adult-product-id, #child-product-id').html(options);
                    }
                }
            });
        },
        
        initFormValidation: function() {
            // Validazione real-time
            $('#event-name, #event-date').on('blur', function() {
                if ($(this).val().trim()) {
                    $(this).removeClass('error');
                } else {
                    $(this).addClass('error');
                }
            });
            
            // Preview date
            $('#event-date').on('change', function() {
                const date = new Date($(this).val());
                const formatted = date.toLocaleDateString('it-IT', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                $('#date-preview').text(formatted);
            });
        }
    };
    
    /**
     * Gestione Impostazioni
     */
    AmacarUN.Settings = {
        
        init: function() {
            this.bindEvents();
            this.testConnections();
        },
        
        bindEvents: function() {
            $(document).on('click', '#test-connections', this.testConnections);
            $(document).on('click', '#repair-database', this.repairDatabase);
            $(document).on('click', '#backup-database', this.backupDatabase);
            $(document).on('submit', '#settings-form', this.saveSettings);
        },
        
        testConnections: function(e) {
            if (e) e.preventDefault();
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_test_connections',
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const wc = response.data.woocommerce;
                        const mp = response.data.mailpoet;
                        
                        $('#woocommerce-status').removeClass('success warning error')
                            .addClass(wc.status)
                            .find('.status-message').text(wc.message);
                            
                        $('#mailpoet-status').removeClass('success warning error')
                            .addClass(mp.status)
                            .find('.status-message').text(mp.message);
                    }
                }
            });
        },
        
        repairDatabase: function(e) {
            e.preventDefault();
            
            if (!confirm('Riparare il database? Questa operazione potrebbe richiedere alcuni minuti.')) {
                return;
            }
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_repair_database',
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage('Database riparato con successo!', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            });
        },
        
        backupDatabase: function(e) {
            e.preventDefault();
            
            const updateProgress = AmacarUN.UI.showProgress('Backup Database', function(update) {
                $.ajax({
                    url: amacarun_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'amacarun_backup_database',
                        nonce: amacarun_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            update(100, 'Backup completato');
                            setTimeout(function() {
                                AmacarUN.UI.showMessage('Backup creato con successo!', 'success');
                            }, 1000);
                        } else {
                            update(0, 'Errore nel backup');
                        }
                    }
                });
            });
        },
        
        saveSettings: function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'amacarun_save_settings');
            formData.append('nonce', amacarun_admin.nonce);
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage('Impostazioni salvate!', 'success');
                    }
                }
            });
        }
    };
    
    // Inizializzazione quando DOM è pronto
    $(document).ready(function() {
        AmacarUN.init();
    });
    
    // Esporta per uso globale
    window.AmacarUN = AmacarUN;
    
})(jQuery); amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_search_participant',
                    query: query,
                    event_id: eventId,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        AmacarUN.Checkin.displaySearchResults(response.data.participants);
                    }
                }
            });
        },
        
        displaySearchResults: function(participants) {
            let html = '';
            
            participants.forEach(function(participant) {
                const statusClass = 'status-' + participant.status;
                const bibDisplay = participant.bib_number ? '#' + participant.bib_number : 'N/A';
                const typeLabel = participant.participant_type === 'adult' ? 'Adulto' : 'Bambino';
                
                html += `
                    <div class="amacarun-participant-card ${statusClass}" data-participant-id="${participant.id}">
                        <div class="amacarun-participant-header">
                            <div class="amacarun-participant-name">${participant.first_name} ${participant.last_name}</div>
                            <div class="amacarun-participant-bib">${bibDisplay}</div>
                        </div>
                        <div class="amacarun-participant-meta">
                            <span class="participant-type">${typeLabel}</span>
                            <span class="participant-status">${participant.status}</span>
                            <span class="participant-distance">${participant.distance || 'N/A'}</span>
                        </div>
                    </div>
                `;
            });
            
            $('#search-results').html(html);
        },
        
        selectParticipant: function() {
            const participantId = $(this).data('participant-id');
            
            // Rimuovi selezione precedente
            $('.amacarun-participant-card').removeClass('selected');
            $(this).addClass('selected');
            
            // Abilita azioni
            $('#checkin-actions').show();
            $('#selected-participant-id').val(participantId);
            
            // Mostra info partecipante
            AmacarUN.Checkin.loadParticipantInfo(participantId);
        },
        
        loadParticipantInfo: function(participantId) {
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_get_participant_info',
                    participant_id: participantId,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        const participant = response.data;
                        $('#participant-info').html(`
                            <h4>${participant.first_name} ${participant.last_name}</h4>
                            <p><strong>Email:</strong> ${participant.email}</p>
                            <p><strong>Telefono:</strong> ${participant.phone || 'N/A'}</p>
                            <p><strong>Tipo:</strong> ${participant.participant_type === 'adult' ? 'Adulto' : 'Bambino'}</p>
                            <p><strong>Pettorale:</strong> ${participant.bib_number ? '#' + participant.bib_number : 'Non assegnato'}</p>
                            <p><strong>Stato:</strong> ${participant.status}</p>
                        `).show();
                    }
                }
            });
        },
        
        performCheckin: function(e) {
            e.preventDefault();
            
            const participantId = $('#selected-participant-id').val();
            const distance = $('#distance-select').val();
            
            if (!distance) {
                alert('Seleziona una distanza');
                return;
            }
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_checkin_participant',
                    participant_id: participantId,
                    distance: distance,
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage('Check-in completato!', 'success');
                        
                        // Aggiorna interfaccia
                        AmacarUN.Checkin.loadStats();
                        $('#participant-search').val('').trigger('keyup');
                        $('#checkin-actions').hide();
                        $('.amacarun-participant-card').removeClass('selected');
                    }
                }
            });
        },
        
        retireParticipant: function(e) {
            e.preventDefault();
            
            const participantId = $('#selected-participant-id').val();
            const notes = prompt('Note ritiro (opzionale):');
            
            if (notes === null) return; // Cancelled
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_retire_participant',
                    participant_id: participantId,
                    notes: notes,
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage('Partecipante ritirato', 'warning');
                        AmacarUN.Checkin.loadStats();
                        $('#participant-search').val('').trigger('keyup');
                        $('#checkin-actions').hide();
                        $('.amacarun-participant-card').removeClass('selected');
                    }
                }
            });
        },
        
        addOnsiteParticipant: function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'amacarun_add_onsite_participant');
            formData.append('nonce', amacarun_admin.nonce);
            formData.append('event_id', $('#current-event-id').val());
            
            $.ajax({
                url:/**
 * AmacarUN Race Manager - Admin JavaScript
 * Gestisce tutte le interazioni AJAX e UI dell'admin
 */

(function($) {
    'use strict';
    
    // Namespace globale
    window.AmacarUN = window.AmacarUN || {};
    
    const AmacarUN = {
        
        /**
         * Inizializzazione generale
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
            this.setupAjaxDefaults();
        },
        
        /**
         * Configurazione AJAX predefinita
         */
        setupAjaxDefaults: function() {
            $.ajaxSetup({
                beforeSend: function(xhr, settings) {
                    // Mostra loading per richieste lunghe
                    if (settings.showLoading !== false) {
                        AmacarUN.UI.showLoading();
                    }
                },
                complete: function() {
                    AmacarUN.UI.hideLoading();
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    AmacarUN.UI.showMessage(amacarun_admin.strings.error, 'error');
                }
            });
        },
        
        /**
         * Binding eventi generali
         */
        bindEvents: function() {
            // Conferma eliminazioni
            $(document).on('click', '[data-confirm]', this.handleConfirmAction);
            
            // Tabs
            $(document).on('click', '.amacarun-tab-link', this.handleTabClick);
            
            // Modals
            $(document).on('click', '[data-modal]', this.openModal);
            $(document).on('click', '.amacarun-modal-close, .amacarun-modal-overlay', this.closeModal);
            
            // Form submissions con AJAX
            $(document).on('submit', '.amacarun-ajax-form', this.handleAjaxForm);
            
            // Auto-save forms
            $(document).on('change', '.amacarun-auto-save', this.handleAutoSave);
            
            // Copy to clipboard
            $(document).on('click', '[data-clipboard]', this.copyToClipboard);
            
            // Tooltips
            this.initTooltips();
        },
        
        /**
         * Inizializza componenti specifici
         */
        initComponents: function() {
            // DataTables
            if ($.fn.DataTable && $('.amacarun-data-table').length) {
                this.initDataTables();
            }
            
            // Charts
            if (typeof Chart !== 'undefined' && $('.amacarun-chart').length) {
                this.initCharts();
            }
            
            // Progress bars animate
            this.animateProgressBars();
            
            // Inizializza componenti specifici per pagina
            const page = this.getCurrentPage();
            if (this[page]) {
                this[page].init();
            }
        },
        
        /**
         * Ottiene la pagina corrente
         */
        getCurrentPage: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            
            if (page === 'amacarun-participants') return 'Participants';
            if (page === 'amacarun-checkin') return 'Checkin';
            if (page === 'amacarun-export') return 'Export';
            if (page === 'amacarun-events') return 'Events';
            if (page === 'amacarun-settings') return 'Settings';
            
            return 'Dashboard';
        },
        
        /**
         * Gestione azioni con conferma
         */
        handleConfirmAction: function(e) {
            const message = $(this).data('confirm') || amacarun_admin.strings.confirm_delete;
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },
        
        /**
         * Gestione click sui tab
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $this = $(this);
            const target = $this.attr('href');
            
            // Attiva tab
            $('.amacarun-tab-link').removeClass('active');
            $this.addClass('active');
            
            // Mostra contenuto
            $('.amacarun-tab-content').hide();
            $(target).show().addClass('amacarun-fade-in');
        },
        
        /**
         * Apri modal
         */
        openModal: function(e) {
            e.preventDefault();
            
            const modalId = $(this).data('modal');
            const $modal = $('#' + modalId);
            
            if ($modal.length) {
                $modal.addClass('active');
                $('body').addClass('modal-open');
            }
        },
        
        /**
         * Chiudi modal
         */
        closeModal: function(e) {
            if (e.target === this || $(this).hasClass('amacarun-modal-close')) {
                $('.amacarun-modal-overlay').removeClass('active');
                $('body').removeClass('modal-open');
            }
        },
        
        /**
         * Form AJAX
         */
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = new FormData(this);
            formData.append('action', $form.data('action'));
            formData.append('nonce', amacarun_admin.nonce);
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage(response.data.message || amacarun_admin.strings.success, 'success');
                        $form.trigger('amacarun:success', response);
                    } else {
                        AmacarUN.UI.showMessage(response.data.message || amacarun_admin.strings.error, 'error');
                    }
                }
            });
        },
        
        /**
         * Auto-save
         */
        handleAutoSave: function() {
            const $field = $(this);
            const value = $field.val();
            const setting = $field.data('setting');
            
            if (!setting) return;
            
            clearTimeout($field.data('timeout'));
            
            $field.data('timeout', setTimeout(function() {
                AmacarUN.saveSetting(setting, value);
            }, 1000));
        },
        
        /**
         * Copia negli appunti
         */
        copyToClipboard: function() {
            const text = $(this).data('clipboard');
            navigator.clipboard.writeText(text).then(function() {
                AmacarUN.UI.showMessage('Copiato negli appunti!', 'success');
            });
        },
        
        /**
         * Salva impostazione
         */
        saveSetting: function(setting, value) {
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_save_setting',
                    setting: setting,
                    value: value,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false
            });
        },
        
        /**
         * Inizializza DataTables
         */
        initDataTables: function() {
            $('.amacarun-data-table').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json'
                },
                pageLength: 25,
                responsive: true,
                order: [[0, 'asc']],
                columnDefs: [{
                    targets: 'no-sort',
                    orderable: false
                }]
            });
        },
        
        /**
         * Inizializza Chart.js
         */
        initCharts: function() {
            $('.amacarun-chart').each(function() {
                const $chart = $(this);
                const data = $chart.data('chart-data');
                const type = $chart.data('chart-type') || 'doughnut';
                
                new Chart(this, {
                    type: type,
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            });
        },
        
        /**
         * Anima progress bars
         */
        animateProgressBars: function() {
            $('.amacarun-progress-bar').each(function() {
                const $bar = $(this);
                const width = $bar.data('width') || $bar.attr('style').match(/width:\s*(\d+)/)[1];
                
                $bar.css('width', '0%');
                setTimeout(function() {
                    $bar.animate({width: width + '%'}, 1000);
                }, 200);
            });
        },
        
        /**
         * Inizializza tooltips
         */
        initTooltips: function() {
            if ($.fn.tooltip) {
                $('[data-tooltip]').tooltip({
                    placement: 'top',
                    trigger: 'hover'
                });
            }
        }
    };
    
    /**
     * Utilities UI
     */
    AmacarUN.UI = {
        
        /**
         * Mostra loading overlay
         */
        showLoading: function(message) {
            const loadingHtml = `
                <div class="amacarun-loading-overlay">
                    <div class="amacarun-loading-spinner">
                        <div class="amacarun-loading"></div>
                        <span>${message || amacarun_admin.strings.loading}</span>
                    </div>
                </div>
            `;
            
            if (!$('.amacarun-loading-overlay').length) {
                $('body').append(loadingHtml);
            }
        },
        
        /**
         * Nascondi loading
         */
        hideLoading: function() {
            $('.amacarun-loading-overlay').fadeOut(200, function() {
                $(this).remove();
            });
        },
        
        /**
         * Mostra messaggio
         */
        showMessage: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;
            
            const $message = $(`
                <div class="amacarun-alert amacarun-alert-${type} amacarun-fade-in" style="position: fixed; top: 20px; right: 20px; z-index: 10001; max-width: 400px;">
                    <button type="button" class="notice-dismiss" style="float: right; padding: 0; margin: 0; border: none; background: none; font-size: 16px; cursor: pointer;">&times;</button>
                    <div>${message}</div>
                </div>
            `);
            
            $('body').append($message);
            
            // Auto-remove
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $message.remove();
                });
            }, duration);
            
            // Manual close
            $message.find('.notice-dismiss').on('click', function() {
                $message.fadeOut(300, function() {
                    $message.remove();
                });
            });
        },
        
        /**
         * Progress bar con callback
         */
        showProgress: function(title, callback) {
            const progressHtml = `
                <div class="amacarun-modal-overlay active">
                    <div class="amacarun-modal">
                        <div class="amacarun-modal-header">
                            <h3>${title}</h3>
                        </div>
                        <div class="amacarun-modal-body">
                            <div class="amacarun-progress">
                                <div class="amacarun-progress-bar" style="width: 0%">0%</div>
                            </div>
                            <div class="progress-message">Inizializzazione...</div>
                        </div>
                    </div>
                </div>
            `;
            
            const $modal = $(progressHtml);
            $('body').append($modal);
            
            const updateProgress = function(percentage, message) {
                const $bar = $modal.find('.amacarun-progress-bar');
                const $message = $modal.find('.progress-message');
                
                $bar.css('width', percentage + '%').text(percentage + '%');
                if (message) $message.text(message);
                
                if (percentage >= 100) {
                    setTimeout(function() {
                        $modal.fadeOut(300, function() {
                            $modal.remove();
                        });
                    }, 1000);
                }
            };
            
            if (typeof callback === 'function') {
                callback(updateProgress);
            }
            
            return updateProgress;
        }
    };
    
    /**
     * Gestione Dashboard
     */
    AmacarUN.Dashboard = {
        
        init: function() {
            this.loadStats();
            this.initRefresh();
            this.checkSystemHealth();
        },
        
        loadStats: function() {
            const activeEventId = $('#active-event-id').val();
            if (!activeEventId) return;
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_get_stats',
                    event_id: activeEventId,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        AmacarUN.Dashboard.updateStatsDisplay(response.data);
                    }
                }
            });
        },
        
        updateStatsDisplay: function(stats) {
            // Aggiorna numeri statistiche
            $('.stat-total-participants').text(stats.participants.total);
            $('.stat-adults').text(stats.participants.adults);
            $('.stat-children').text(stats.participants.children);
            $('.stat-checked-in').text(stats.participants.checked_in);
            $('.stat-bibs-assigned').text(stats.bib.total_assigned);
            $('.stat-mailpoet-subscribed').text(stats.mailpoet.subscribed || 0);
            
            // Anima le stats
            $('.amacarun-stat-number').each(function() {
                const $this = $(this);
                const target = parseInt($this.text());
                let current = 0;
                
                const increment = target / 50;
                const timer = setInterval(function() {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    $this.text(Math.floor(current));
                }, 20);
            });
        },
        
        initRefresh: function() {
            $('#refresh-stats').on('click', function(e) {
                e.preventDefault();
                AmacarUN.Dashboard.loadStats();
                AmacarUN.UI.showMessage('Statistiche aggiornate', 'success', 2000);
            });
            
            // Auto-refresh ogni 30 secondi
            setInterval(function() {
                AmacarUN.Dashboard.loadStats();
            }, 30000);
        },
        
        checkSystemHealth: function() {
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_test_connections',
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        AmacarUN.Dashboard.updateHealthStatus(response.data);
                    }
                }
            });
        },
        
        updateHealthStatus: function(health) {
            const $wc = $('.woocommerce-status');
            const $mp = $('.mailpoet-status');
            
            $wc.removeClass('success warning error')
               .addClass(health.woocommerce.status)
               .find('.status-message').text(health.woocommerce.message);
               
            $mp.removeClass('success warning error')
               .addClass(health.mailpoet.status)
               .find('.status-message').text(health.mailpoet.message);
        }
    };
    
    /**
     * Gestione Partecipanti
     */
    AmacarUN.Participants = {
        
        init: function() {
            this.bindEvents();
            this.initBulkActions();
        },
        
        bindEvents: function() {
            // Aggiornamento numero pettorale
            $(document).on('change', '.bib-number-input', this.updateBibNumber);
            
            // Assegnazione prossimo pettorale
            $(document).on('click', '.assign-next-bib', this.assignNextBib);
            
            // Rimozione pettorale
            $(document).on('click', '.remove-bib', this.removeBib);
            
            // Azioni MailPoet
            $(document).on('click', '.mailpoet-subscribe', this.mailpoetSubscribe);
            $(document).on('click', '.mailpoet-unsubscribe', this.mailpoetUnsubscribe);
            
            // Bulk assign bibs
            $(document).on('click', '#bulk-assign-bibs', this.bulkAssignBibs);
            
            // Sync WooCommerce
            $(document).on('click', '#sync-woocommerce', this.syncWooCommerce);
        },
        
        updateBibNumber: function() {
            const $input = $(this);
            const participantId = $input.data('participant-id');
            const bibNumber = $input.val() || null;
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_update_bib',
                    participant_id: participantId,
                    bib_number: bibNumber,
                    nonce: amacarun_admin.nonce
                },
                showLoading: false,
                success: function(response) {
                    if (response.success) {
                        $input.removeClass('error').addClass('success');
                        setTimeout(function() {
                            $input.removeClass('success');
                        }, 2000);
                    } else {
                        $input.addClass('error');
                        AmacarUN.UI.showMessage(response.data.message, 'error');
                    }
                }
            });
        },
        
        assignNextBib: function(e) {
            e.preventDefault();
            
            const participantId = $(this).data('participant-id');
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_assign_next_bib',
                    participant_id: participantId,
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const $row = $(`[data-participant-id="${participantId}"]`).closest('tr');
                        $row.find('.bib-number-input').val(response.data.bib_number);
                        $row.find('.assign-next-bib').hide();
                        AmacarUN.UI.showMessage(`Pettorale #${response.data.bib_number} assegnato`, 'success');
                    }
                }
            });
        },
        
        removeBib: function(e) {
            e.preventDefault();
            
            if (!confirm('Rimuovere il pettorale da questo partecipante?')) return;
            
            const participantId = $(this).data('participant-id');
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_remove_bib',
                    participant_id: participantId,
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const $row = $(`[data-participant-id="${participantId}"]`).closest('tr');
                        $row.find('.bib-number-input').val('');
                        $row.find('.assign-next-bib').show();
                        AmacarUN.UI.showMessage('Pettorale rimosso', 'success');
                    }
                }
            });
        },
        
        bulkAssignBibs: function(e) {
            e.preventDefault();
            
            if (!confirm(amacarun_admin.strings.confirm_bulk_action)) return;
            
            const eventId = $('#current-event-id').val();
            
            const updateProgress = AmacarUN.UI.showProgress('Assegnazione Pettorali', function(update) {
                $.ajax({
                    url: amacarun_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'amacarun_bulk_assign_bibs',
                        event_id: eventId,
                        nonce: amacarun_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            update(100, `${response.data.assigned} pettorali assegnati`);
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            update(0, 'Errore nell\'assegnazione');
                        }
                    }
                });
            });
        },
        
        syncWooCommerce: function(e) {
            e.preventDefault();
            
            const eventId = $('#current-event-id').val();
            
            const updateProgress = AmacarUN.UI.showProgress('Sincronizzazione WooCommerce', function(update) {
                
                // Avvia sincronizzazione
                $.ajax({
                    url: amacarun_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'amacarun_sync_woocommerce',
                        event_id: eventId,
                        nonce: amacarun_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Monitora progress
                            AmacarUN.Participants.monitorSyncProgress(eventId, update);
                        } else {
                            update(0, 'Errore nella sincronizzazione');
                        }
                    }
                });
            });
        },
        
        monitorSyncProgress: function(eventId, updateCallback) {
            const checkProgress = function() {
                $.ajax({
                    url: amacarun_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'amacarun_get_sync_progress',
                        event_id: eventId,
                        nonce: amacarun_admin.nonce
                    },
                    showLoading: false,
                    success: function(response) {
                        if (response.success && response.data) {
                            const progress = response.data;
                            const percentage = Math.round((progress.processed / progress.total) * 100);
                            
                            updateCallback(percentage, `${progress.processed}/${progress.total} ordini processati`);
                            
                            if (progress.status === 'completed') {
                                updateCallback(100, `Sincronizzazione completata: ${progress.synced} partecipanti`);
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else if (progress.status !== 'error') {
                                setTimeout(checkProgress, 1000);
                            }
                        }
                    }
                });
            };
            
            checkProgress();
        },
        
        mailpoetSubscribe: function(e) {
            e.preventDefault();
            
            const participantId = $(this).data('participant-id');
            const $button = $(this);
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_mailpoet_subscribe',
                    participant_id: participantId,
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.removeClass('mailpoet-subscribe')
                               .addClass('mailpoet-unsubscribe')
                               .text('Disiscrivi');
                        AmacarUN.UI.showMessage('Iscritto a MailPoet', 'success');
                    }
                }
            });
        },
        
        initBulkActions: function() {
            $('#bulk-action-apply').on('click', function(e) {
                e.preventDefault();
                
                const action = $('#bulk-action-select').val();
                const selected = $('input[name="participants[]"]:checked');
                
                if (!action) {
                    alert('Seleziona un\'azione');
                    return;
                }
                
                if (selected.length === 0) {
                    alert(amacarun_admin.strings.no_participants_selected);
                    return;
                }
                
                AmacarUN.Participants.executeBulkAction(action, selected);
            });
            
            // Select all checkbox
            $('#select-all-participants').on('change', function() {
                $('input[name="participants[]"]').prop('checked', $(this).prop('checked'));
            });
        },
        
        executeBulkAction: function(action, selected) {
            const participantIds = selected.map(function() {
                return $(this).val();
            }).get();
            
            let confirmMessage = '';
            switch (action) {
                case 'assign_bibs':
                    confirmMessage = 'Assegnare pettorali a tutti i partecipanti selezionati?';
                    break;
                case 'mailpoet_subscribe':
                    confirmMessage = 'Iscrivere tutti i partecipanti selezionati a MailPoet?';
                    break;
                case 'delete':
                    confirmMessage = 'ATTENZIONE: Eliminare definitivamente i partecipanti selezionati?';
                    break;
                default:
                    confirmMessage = amacarun_admin.strings.confirm_bulk_action;
            }
            
            if (!confirm(confirmMessage)) return;
            
            $.ajax({
                url: amacarun_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_bulk_' + action,
                    participant_ids: participantIds,
                    nonce: amacarun_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AmacarUN.UI.showMessage(response.data.message || 'Azione completata', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                }
            });
        }
    };