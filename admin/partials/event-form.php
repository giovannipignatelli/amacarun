<?php
/**
 * Template Form Evento - AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Variabili disponibili: $event, $mailpoet_lists
$is_edit = $event->id > 0;
?>

<div class="wrap amacarun-admin-container">
    <div class="amacarun-header">
        <h1><?php echo $is_edit ? __('Modifica Evento', 'amacarun-race-manager') : __('Nuovo Evento', 'amacarun-race-manager'); ?></h1>
        <div class="amacarun-header-actions">
            <a href="<?php echo admin_url('admin.php?page=amacarun-events'); ?>" class="amacarun-btn amacarun-btn-secondary">
                ← <?php _e('Torna alla Lista', 'amacarun-race-manager'); ?>
            </a>
        </div>
    </div>

    <form id="event-form" class="amacarun-event-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('amacarun_admin_action', '_wpnonce'); ?>
        <input type="hidden" name="action" value="amacarun_admin_action">
        <input type="hidden" name="amacarun_action" value="<?php echo $is_edit ? 'update_event' : 'create_event'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="event_id" value="<?php echo $event->id; ?>">
        <?php endif; ?>

        <!-- Informazioni Base -->
        <div class="amacarun-card form-section">
            <div class="amacarun-card-header">
                <h3 class="section-title"><?php _e('Informazioni Evento', 'amacarun-race-manager'); ?></h3>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="event-name"><?php _e('Nome Evento *', 'amacarun-race-manager'); ?></label>
                    <input type="text" id="event-name" name="event_name" class="amacarun-form-control" 
                           value="<?php echo esc_attr($event->name); ?>" required>
                    <div class="amacarun-form-help"><?php _e('Es: AmacarUN 2024', 'amacarun-race-manager'); ?></div>
                </div>

                <div class="amacarun-form-group">
                    <label for="event-date"><?php _e('Data Evento *', 'amacarun-race-manager'); ?></label>
                    <input type="date" id="event-date" name="event_date" class="amacarun-form-control" 
                           value="<?php echo esc_attr($event->date); ?>" required>
                    <div id="date-preview" class="amacarun-form-help"></div>
                </div>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="event-status"><?php _e('Stato', 'amacarun-race-manager'); ?></label>
                    <select id="event-status" name="event_status" class="amacarun-form-control">
                        <option value="draft" <?php selected($event->status, 'draft'); ?>><?php _e('Bozza', 'amacarun-race-manager'); ?></option>
                        <option value="active" <?php selected($event->status, 'active'); ?>><?php _e('Attivo', 'amacarun-race-manager'); ?></option>
                        <?php if ($is_edit): ?>
                            <option value="completed" <?php selected($event->status, 'completed'); ?>><?php _e('Completato', 'amacarun-race-manager'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Configurazione WooCommerce -->
        <div class="amacarun-card form-section woocommerce-section">
            <div class="amacarun-card-header">
                <h3 class="section-title"><?php _e('Integrazione WooCommerce', 'amacarun-race-manager'); ?></h3>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="woocommerce-category-id"><?php _e('Categoria Prodotti', 'amacarun-race-manager'); ?></label>
                    <input type="number" id="woocommerce-category-id" name="woocommerce_category_id" 
                           class="amacarun-form-control" 
                           value="<?php echo esc_attr($event->woocommerce_category_id); ?>" 
                           placeholder="29">
                    <div class="amacarun-form-help"><?php _e('ID categoria WooCommerce per i prodotti della gara', 'amacarun-race-manager'); ?></div>
                </div>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="adult-product-id"><?php _e('ID Prodotto Adulti', 'amacarun-race-manager'); ?></label>
                    <input type="number" id="adult-product-id" name="adult_product_id" 
                           class="amacarun-form-control" 
                           value="<?php echo esc_attr($event->adult_product_id); ?>">
                </div>

                <div class="amacarun-form-group">
                    <label for="child-product-id"><?php _e('ID Prodotto Bambini', 'amacarun-race-manager'); ?></label>
                    <input type="number" id="child-product-id" name="child_product_id" 
                           class="amacarun-form-control" 
                           value="<?php echo esc_attr($event->child_product_id); ?>">
                </div>
            </div>
        </div>

        <!-- Configurazione Pettorali -->
        <div class="amacarun-card form-section bibs-section">
            <div class="amacarun-card-header">
                <h3 class="section-title"><?php _e('Configurazione Pettorali', 'amacarun-race-manager'); ?></h3>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="bib-number-start"><?php _e('Numero Iniziale Pettorali', 'amacarun-race-manager'); ?></label>
                    <input type="number" id="bib-number-start" name="bib_number_start" 
                           class="amacarun-form-control" 
                           value="<?php echo esc_attr($event->bib_number_start); ?>" 
                           min="1" 
                           placeholder="1001">
                    <div class="amacarun-form-help"><?php _e('Numero da cui partire per l\'assegnazione dei pettorali', 'amacarun-race-manager'); ?></div>
                </div>

                <?php if ($is_edit): ?>
                <div class="amacarun-form-group">
                    <label><?php _e('Prossimo Numero', 'amacarun-race-manager'); ?></label>
                    <div class="amacarun-form-control" style="background: #f8f9fa; border: none;">
                        <?php echo $event->bib_number_current; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Configurazione MailPoet -->
        <?php if (!empty($mailpoet_lists)): ?>
        <div class="amacarun-card form-section mailpoet-section">
            <div class="amacarun-card-header">
                <h3 class="section-title"><?php _e('Integrazione MailPoet', 'amacarun-race-manager'); ?></h3>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <label for="mailpoet-list-id"><?php _e('Lista MailPoet', 'amacarun-race-manager'); ?></label>
                    <select id="mailpoet-list-id" name="mailpoet_list_id" class="amacarun-form-control">
                        <option value=""><?php _e('-- Nessuna lista --', 'amacarun-race-manager'); ?></option>
                        <?php foreach ($mailpoet_lists as $list): ?>
                            <option value="<?php echo $list['id']; ?>" <?php selected($event->mailpoet_list_id, $list['id']); ?>>
                                <?php echo esc_html($list['name']); ?> (<?php echo $list['subscribers']['subscribed'] ?? 0; ?> iscritti)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="amacarun-form-row">
                <div class="amacarun-form-group">
                    <div class="amacarun-checkbox-wrapper">
                        <input type="checkbox" id="mailpoet-auto-subscribe" name="mailpoet_auto_subscribe" 
                               value="1" <?php checked($event->mailpoet_auto_subscribe, 1); ?>>
                        <label for="mailpoet-auto-subscribe">
                            <?php _e('Iscrivi automaticamente i partecipanti alla newsletter', 'amacarun-race-manager'); ?>
                        </label>
                    </div>
                </div>

                <div class="amacarun-form-group">
                    <div class="amacarun-checkbox-wrapper">
                        <input type="checkbox" id="mailpoet-double-optin" name="mailpoet_double_optin" 
                               value="1" <?php checked($event->mailpoet_double_optin, 1); ?>>
                        <label for="mailpoet-double-optin">
                            <?php _e('Richiedi doppio opt-in per l\'iscrizione', 'amacarun-race-manager'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Azioni Form -->
        <div class="amacarun-card">
            <div class="amacarun-btn-group">
                <button type="submit" class="amacarun-btn amacarun-btn-primary amacarun-btn-lg">
                    💾 <?php echo $is_edit ? __('Salva Modifiche', 'amacarun-race-manager') : __('Crea Evento', 'amacarun-race-manager'); ?>
                </button>

                <?php if ($is_edit && $event->status !== 'active'): ?>
                <button type="button" class="amacarun-btn amacarun-btn-success activate-event" 
                        data-event-id="<?php echo $event->id; ?>">
                    ▶️ <?php _e('Attiva Evento', 'amacarun-race-manager'); ?>
                </button>
                <?php endif; ?>

                <a href="<?php echo admin_url('admin.php?page=amacarun-events'); ?>" 
                   class="amacarun-btn amacarun-btn-secondary">
                    <?php _e('Annulla', 'amacarun-race-manager'); ?>
                </a>
            </div>
        </div>
    </form>
</div>