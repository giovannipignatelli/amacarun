<?php
/**
 * Template Interfaccia Export - AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Variabili disponibili: $active_event, $events
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
        </div>
    </div>

    <!-- Selezione Evento -->
    <div class="amacarun-card">
        <div class="amacarun-card-header">
            <h3 class="amacarun-card-title"><?php _e('Seleziona Evento', 'amacarun-race-manager'); ?></h3>
        </div>

        <div class="amacarun-form-group">
            <select id="export-event-select" class="amacarun-form-control">
                <option value=""><?php _e('-- Seleziona un evento --', 'amacarun-race-manager'); ?></option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo $event->id; ?>" 
                            <?php selected($event->id, $active_event ? $active_event->id : 0); ?>>
                        <?php echo esc_html($event->name); ?> 
                        (<?php echo date_i18n(get_option('date_format'), strtotime($event->date)); ?>)
                        <?php if ($event->status === 'active'): ?>
                            - <?php _e('ATTIVO', 'amacarun-race-manager'); ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="export-event-info" style="display: none;"></div>
    </div>

    <!-- Sezioni Export -->
    <div class="export-sections" style="display: none;">
        
        <!-- Export CSV Partecipanti -->
        <div class="amacarun-card export-section">
            <div class="amacarun-card-header">
                <h3 class="amacarun-card-title">📊 <?php _e('Export CSV Partecipanti', 'amacarun-race-manager'); ?></h3>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="csv-format-select"><?php _e('Formato', 'amacarun-race-manager'); ?></label>
                    <select id="csv-format-select" class="amacarun-form-control">
                        <option value="complete"><?php _e('Completo - Tutti i dati', 'amacarun-race-manager'); ?></option>
                        <option value="basic"><?php _e('Base - Nome, Email, Tipo', 'amacarun-race-manager'); ?></option>
                        <option value="checkin"><?php _e('Check-in - Per registrazione giornaliera', 'amacarun-race-manager'); ?></option>
                        <option value="labels"><?php _e('Etichette - Per stampa pettorali', 'amacarun-race-manager'); ?></option>
                    </select>
                </div>

                <div class="amacarun-form-group">
                    <label for="csv-status-filter"><?php _e('Stato', 'amacarun-race-manager'); ?></label>
                    <select id="csv-status-filter" class="amacarun-form-control">
                        <option value="all"><?php _e('Tutti', 'amacarun-race-manager'); ?></option>
                        <option value="registered"><?php _e('Registrati', 'amacarun-race-manager'); ?></option>
                        <option value="checked_in"><?php _e('Check-in effettuati', 'amacarun-race-manager'); ?></option>
                        <option value="retired"><?php _e('Ritirati', 'amacarun-race-manager'); ?></option>
                    </select>
                </div>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="csv-type-filter"><?php _e('Tipologia', 'amacarun-race-manager'); ?></label>
                    <select id="csv-type-filter" class="amacarun-form-control">
                        <option value="all"><?php _e('Tutti', 'amacarun-race-manager'); ?></option>
                        <option value="adult"><?php _e('Solo adulti', 'amacarun-race-manager'); ?></option>
                        <option value="child"><?php _e('Solo bambini', 'amacarun-race-manager'); ?></option>
                    </select>
                </div>

                <div class="amacarun-form-group">
                    <label for="csv-distance-filter"><?php _e('Distanza', 'amacarun-race-manager'); ?></label>
                    <select id="csv-distance-filter" class="amacarun-form-control">
                        <option value="all"><?php _e('Tutte', 'amacarun-race-manager'); ?></option>
                        <option value="4km">4km</option>
                        <option value="11km">11km</option>
                    </select>
                </div>

                <div class="amacarun-form-group">
                    <label for="csv-bib-filter"><?php _e('Pettorali', 'amacarun-race-manager'); ?></label>
                    <select id="csv-bib-filter" class="amacarun-form-control">
                        <option value="all"><?php _e('Tutti', 'amacarun-race-manager'); ?></option>
                        <option value="yes"><?php _e('Con pettorale', 'amacarun-race-manager'); ?></option>
                        <option value="no"><?php _e('Senza pettorale', 'amacarun-race-manager'); ?></option>
                    </select>
                </div>
            </div>

            <div class="amacarun-btn-group">
                <button type="button" id="export-csv" class="amacarun-btn amacarun-btn-primary">
                    📥 <?php _e('Esporta CSV', 'amacarun-race-manager'); ?>
                </button>
            </div>
        </div>

        <!-- Export Etichette -->
        <div class="amacarun-card export-section">
            <div class="amacarun-card-header">
                <h3 class="amacarun-card-title">🏷️ <?php _e('Export Etichette Pettorali', 'amacarun-race-manager'); ?></h3>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="labels-format-select"><?php _e('Formato Foglio', 'amacarun-race-manager'); ?></label>
                    <select id="labels-format-select" class="amacarun-form-control">
                        <option value="avery_l7163"><?php _e('Avery L7163 (14 etichette A4)', 'amacarun-race-manager'); ?></option>
                        <option value="custom"><?php _e('Personalizzato', 'amacarun-race-manager'); ?></option>
                    </select>
                </div>

                <div class="amacarun-form-group">
                    <label for="labels-content-select"><?php _e('Contenuto Etichetta', 'amacarun-race-manager'); ?></label>
                    <select id="labels-content-select" class="amacarun-form-control">
                        <option value="bib_name"><?php _e('Pettorale + Nome', 'amacarun-race-manager'); ?></option>
                        <option value="full_info"><?php _e('Info Complete', 'amacarun-race-manager'); ?></option>
                        <option value="name_only"><?php _e('Solo Nome', 'amacarun-race-manager'); ?></option>
                    </select>
                </div>
            </div>

            <div class="amacarun-form-group">
                <div class="amacarun-checkbox-wrapper">
                    <input type="checkbox" id="labels-include-qr" value="1">
                    <label for="labels-include-qr">
                        <?php _e('Includi QR Code per check-in rapido', 'amacarun-race-manager'); ?>
                    </label>
                </div>
            </div>

            <div class="amacarun-btn-group">
                <button type="button" id="export-labels" class="amacarun-btn amacarun-btn-primary">
                    🖨️ <?php _e('Genera Etichette', 'amacarun-race-manager'); ?>
                </button>
            </div>
        </div>

        <!-- Export Statistiche -->
        <div class="amacarun-card export-section">
            <div class="amacarun-card-header">
                <h3 class="amacarun-card-title">📈 <?php _e('Report Statistiche', 'amacarun-race-manager'); ?></h3>
            </div>

            <div class="amacarun-form-group">
                <label for="stats-format-select"><?php _e('Formato Report', 'amacarun-race-manager'); ?></label>
                <select id="stats-format-select" class="amacarun-form-control">
                    <option value="html"><?php _e('HTML (Visualizzazione Web)', 'amacarun-race-manager'); ?></option>
                    <option value="csv"><?php _e('CSV (Excel)', 'amacarun-race-manager'); ?></option>
                    <option value="json"><?php _e('JSON (Dati Raw)', 'amacarun-race-manager'); ?></option>
                </select>
            </div>

            <div class="amacarun-btn-group">
                <button type="button" id="export-stats" class="amacarun-btn amacarun-btn-primary">
                    📊 <?php _e('Genera Report', 'amacarun-race-manager'); ?>
                </button>
            </div>
        </div>

    </div>
</div>

<style>
.export-sections {
    display: grid;
    gap: 20px;
}

.export-section {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>