<?php
/**
 * Gestione Pettorali AmacarUN Race Manager
 *
 * @package AmacarUN_Race_Manager
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per la gestione dei numeri di pettorale
 */
class AmacarUN_Bib_Manager {
    
    /**
     * Tabelle utilizzate
     */
    private $participants_table;
    private $events_table;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->participants_table = AMACARUN_PARTICIPANTS_TABLE;
        $this->events_table = AMACARUN_EVENTS_TABLE;
    }
    
    /**
     * Assegna prossimo numero sequenziale
     */
    public function assign_next_sequential_bib($participant_id, $event_id) {
        global $wpdb;
        
        // Inizia transazione
        $wpdb->query('START TRANSACTION');
        
        try {
            // Blocca la riga dell'evento per evitare race conditions
            $event = $wpdb->get_row($wpdb->prepare(
                "SELECT bib_number_current FROM {$this->events_table} WHERE id = %d FOR UPDATE",
                $event_id
            ));
            
            if (!$event) {
                throw new Exception('Evento non trovato');
            }
            
            $next_bib = $event->bib_number_current;
            
            // Verifica che il numero non sia già usato (sicurezza aggiuntiva)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->participants_table} WHERE event_id = %d AND bib_number = %d",
                $event_id, $next_bib
            ));
            
            if ($existing > 0) {
                throw new Exception("Numero pettorale $next_bib già assegnato");
            }
            
            // Assegna numero al partecipante
            $update_participant = $wpdb->update(
                $this->participants_table,
                array('bib_number' => $next_bib),
                array('id' => $participant_id),
                array('%d'),
                array('%d')
            );
            
            if ($update_participant === false) {
                throw new Exception('Errore nell\'assegnazione del pettorale al partecipante');
            }
            
            // Incrementa contatore evento
            $update_event = $wpdb->update(
                $this->events_table,
                array('bib_number_current' => $next_bib + 1),
                array('id' => $event_id),
                array('%d'),
                array('%d')
            );
            
            if ($update_event === false) {
                throw new Exception('Errore nell\'aggiornamento del contatore pettorali');
            }
            
            // Commit transazione
            $wpdb->query('COMMIT');
            
            do_action('amacarun_bib_assigned', $participant_id, $next_bib, $event_id);
            amacarun_log("Bib assigned: Participant $participant_id, Bib $next_bib");
            
            return $next_bib;
            
        } catch (Exception $e) {
            // Rollback in caso di errore
            $wpdb->query('ROLLBACK');
            amacarun_log('Error assigning sequential bib: ' . $e->getMessage(), 'error');
            return new WP_Error('bib_assignment_failed', $e->getMessage());
        }
    }
    
    /**
     * Assegna manualmente numero specifico
     */
    public function assign_manual_bib($participant_id, $bib_number, $event_id) {
        global $wpdb;
        
        // Valida numero pettorale
        if (!is_numeric($bib_number) || $bib_number < 1001) {
            return new WP_Error('invalid_bib', 'Numero pettorale deve essere >= 1001');
        }
        
        // Verifica che il numero non sia già usato
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->participants_table} 
             WHERE event_id = %d AND bib_number = %d AND id != %d",
            $event_id, $bib_number, $participant_id
        ));
        
        if ($existing > 0) {
            return new WP_Error('bib_exists', 'Numero pettorale già assegnato');
        }
        
        // Assegna numero
        $result = $wpdb->update(
            $this->participants_table,
            array('bib_number' => $bib_number),
            array('id' => $participant_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Aggiorna contatore se necessario (se numero è >= current)
            $this->update_bib_counter_if_needed($event_id, $bib_number);
            
            do_action('amacarun_bib_manually_assigned', $participant_id, $bib_number, $event_id);
            amacarun_log("Manual bib assigned: Participant $participant_id, Bib $bib_number");
            
            return $bib_number;
        }
        
        return new WP_Error('db_error', 'Errore nell\'assegnazione del pettorale');
    }
    
    /**
     * Rimuove numero pettorale da partecipante
     */
    public function remove_bib($participant_id) {
        global $wpdb;
        
        // Ottieni numero pettorale corrente
        $current_bib = $wpdb->get_var($wpdb->prepare(
            "SELECT bib_number FROM {$this->participants_table} WHERE id = %d",
            $participant_id
        ));
        
        if (!$current_bib) {
            return new WP_Error('no_bib', 'Partecipante non ha un pettorale assegnato');
        }
        
        $result = $wpdb->update(
            $this->participants_table,
            array('bib_number' => null),
            array('id' => $participant_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('amacarun_bib_removed', $participant_id, $current_bib);
            amacarun_log("Bib removed: Participant $participant_id, Bib $current_bib");
            return true;
        }
        
        return false;
    }
    
    /**
     * Assegna pettorali a tutti i partecipanti senza numero
     */
    public function bulk_assign_sequential_bibs($event_id) {
        global $wpdb;
        
        // Recupera partecipanti senza pettorale in ordine cronologico
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$this->participants_table} 
             WHERE event_id = %d AND bib_number IS NULL 
             ORDER BY created_at ASC",
            $event_id
        ));
        
        $assigned = 0;
        $errors = array();
        
        foreach ($participants as $participant) {
            $bib = $this->assign_next_sequential_bib($participant->id, $event_id);
            
            if (is_wp_error($bib)) {
                $errors[] = "Participant {$participant->id}: " . $bib->get_error_message();
            } else {
                $assigned++;
            }
        }
        
        amacarun_log("Bulk bib assignment completed: $assigned assigned, " . count($errors) . " errors");
        
        return array(
            'assigned' => $assigned,
            'errors' => $errors
        );
    }
    
    /**
     * Scambia numeri pettorali tra due partecipanti
     */
    public function swap_bibs($participant_id_1, $participant_id_2) {
        global $wpdb;
        
        // Ottieni numeri pettorali correnti
        $bib_1 = $wpdb->get_var($wpdb->prepare(
            "SELECT bib_number FROM {$this->participants_table} WHERE id = %d",
            $participant_id_1
        ));
        
        $bib_2 = $wpdb->get_var($wpdb->prepare(
            "SELECT bib_number FROM {$this->participants_table} WHERE id = %d",
            $participant_id_2
        ));
        
        if (!$bib_1 || !$bib_2) {
            return new WP_Error('missing_bib', 'Entrambi i partecipanti devono avere un pettorale assegnato');
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Scambio temporaneo con numero negativo per evitare conflitti
            $temp_bib = -abs($bib_1);
            
            // Passo 1: assegna numero temporaneo al primo partecipante
            $wpdb->update(
                $this->participants_table,
                array('bib_number' => $temp_bib),
                array('id' => $participant_id_1),
                array('%d'),
                array('%d')
            );
            
            // Passo 2: assegna il numero del primo al secondo
            $wpdb->update(
                $this->participants_table,
                array('bib_number' => $bib_1),
                array('id' => $participant_id_2),
                array('%d'),
                array('%d')
            );
            
            // Passo 3: assegna il numero del secondo al primo
            $wpdb->update(
                $this->participants_table,
                array('bib_number' => $bib_2),
                array('id' => $participant_id_1),
                array('%d'),
                array('%d')
            );
            
            $wpdb->query('COMMIT');
            
            do_action('amacarun_bibs_swapped', $participant_id_1, $participant_id_2, $bib_1, $bib_2);
            amacarun_log("Bibs swapped: P1($participant_id_1)=$bib_2, P2($participant_id_2)=$bib_1");
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('swap_failed', 'Errore nello scambio dei pettorali');
        }
    }
    
    /**
     * Verifica disponibilità numero
     */
    public function is_bib_available($event_id, $bib_number, $exclude_participant_id = null) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->participants_table} 
                  WHERE event_id = %d AND bib_number = %d";
        $params = array($event_id, $bib_number);
        
        if ($exclude_participant_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_participant_id;
        }
        
        return $wpdb->get_var($wpdb->prepare($query, $params)) == 0;
    }
    
    /**
     * Trova partecipante per numero pettorale
     */
    public function find_by_bib_number($event_id, $bib_number) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->participants_table} 
             WHERE event_id = %d AND bib_number = %d",
            $event_id, $bib_number
        ));
    }
    
    /**
     * Ottiene prossimo numero disponibile
     */
    public function get_next_available_bib($event_id) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT bib_number_current FROM {$this->events_table} WHERE id = %d",
            $event_id
        ));
        
        return $event ? $event->bib_number_current : 1001;
    }
    
    /**
     * Ottiene numeri pettorali mancanti in sequenza
     */
    public function get_missing_bibs_in_sequence($event_id) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT bib_number_start, bib_number_current FROM {$this->events_table} WHERE id = %d",
            $event_id
        ));
        
        if (!$event) {
            return array();
        }
        
        // Ottieni tutti i numeri assegnati nell'intervallo
        $assigned_bibs = $wpdb->get_col($wpdb->prepare(
            "SELECT bib_number FROM {$this->participants_table} 
             WHERE event_id = %d AND bib_number >= %d AND bib_number < %d
             ORDER BY bib_number ASC",
            $event_id, $event->bib_number_start, $event->bib_number_current
        ));
        
        // Trova i numeri mancanti
        $missing = array();
        for ($i = $event->bib_number_start; $i < $event->bib_number_current; $i++) {
            if (!in_array($i, $assigned_bibs)) {
                $missing[] = $i;
            }
        }
        
        return $missing;
    }
    
    /**
     * Ricompatta numerazione (rimuove buchi)
     */
    public function compact_bib_numbers($event_id) {
        global $wpdb;
        
        // Ottieni tutti i partecipanti con pettorale in ordine cronologico
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT id, bib_number FROM {$this->participants_table} 
             WHERE event_id = %d AND bib_number IS NOT NULL 
             ORDER BY created_at ASC",
            $event_id
        ));
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT bib_number_start FROM {$this->events_table} WHERE id = %d",
            $event_id
        ));
        
        if (!$event || empty($participants)) {
            return false;
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $current_bib = $event->bib_number_start;
            $reassigned = 0;
            
            foreach ($participants as $participant) {
                if ($participant->bib_number != $current_bib) {
                    $wpdb->update(
                        $this->participants_table,
                        array('bib_number' => $current_bib),
                        array('id' => $participant->id),
                        array('%d'),
                        array('%d')
                    );
                    
                    $reassigned++;
                }
                
                $current_bib++;
            }
            
            // Aggiorna contatore evento
            $wpdb->update(
                $this->events_table,
                array('bib_number_current' => $current_bib),
                array('id' => $event_id),
                array('%d'),
                array('%d')
            );
            
            $wpdb->query('COMMIT');
            
            amacarun_log("Bib numbers compacted: $reassigned participants reassigned");
            
            return $reassigned;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            amacarun_log('Error compacting bib numbers: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Ottiene statistiche pettorali per evento
     */
    public function get_bib_stats($event_id) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT bib_number_start, bib_number_current FROM {$this->events_table} WHERE id = %d",
            $event_id
        ));
        
        if (!$event) {
            return false;
        }
        
        $stats = array();
        
        // Totale assegnati
        $stats['total_assigned'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->participants_table} WHERE event_id = %d AND bib_number IS NOT NULL",
            $event_id
        ));
        
        // Senza pettorale
        $stats['without_bib'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->participants_table} WHERE event_id = %d AND bib_number IS NULL",
            $event_id
        ));
        
        // Range utilizzato
        $stats['range_start'] = $event->bib_number_start;
        $stats['range_current'] = $event->bib_number_current;
        $stats['range_used'] = $stats['range_current'] - $stats['range_start'];
        
        // Numeri mancanti in sequenza
        $missing_bibs = $this->get_missing_bibs_in_sequence($event_id);
        $stats['missing_count'] = count($missing_bibs);
        $stats['missing_bibs'] = array_slice($missing_bibs, 0, 10); // Prime 10 per evitare output troppo lungo
        
        // Numero più alto assegnato
        $stats['highest_bib'] = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(bib_number) FROM {$this->participants_table} WHERE event_id = %d",
            $event_id
        ));
        
        // Numero più basso assegnato
        $stats['lowest_bib'] = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(bib_number) FROM {$this->participants_table} WHERE event_id = %d AND bib_number IS NOT NULL",
            $event_id
        ));
        
        // Duplicati (non dovrebbero esistere, ma verifichiamo)
        $stats['duplicates'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT bib_number, COUNT(*) as cnt 
                FROM {$this->participants_table} 
                WHERE event_id = %d AND bib_number IS NOT NULL 
                GROUP BY bib_number 
                HAVING cnt > 1
            ) as duplicates",
            $event_id
        ));
        
        return $stats;
    }
    
    /**
     * Valida integrità pettorali per evento
     */
    public function validate_bib_integrity($event_id) {
        global $wpdb;
        
        $issues = array();
        
        // Verifica duplicati
        $duplicates = $wpdb->get_results($wpdb->prepare(
            "SELECT bib_number, COUNT(*) as count, GROUP_CONCAT(id) as participant_ids
             FROM {$this->participants_table} 
             WHERE event_id = %d AND bib_number IS NOT NULL
             GROUP BY bib_number 
             HAVING count > 1",
            $event_id
        ));
        
        if (!empty($duplicates)) {
            foreach ($duplicates as $duplicate) {
                $issues[] = "Pettorale duplicato: #{$duplicate->bib_number} assegnato a partecipanti: {$duplicate->participant_ids}";
            }
        }
        
        // Verifica numeri fuori range
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT bib_number_start FROM {$this->events_table} WHERE id = %d",
            $event_id
        ));
        
        if ($event) {
            $out_of_range = $wpdb->get_results($wpdb->prepare(
                "SELECT id, bib_number FROM {$this->participants_table} 
                 WHERE event_id = %d AND bib_number IS NOT NULL AND bib_number < %d",
                $event_id, $event->bib_number_start
            ));
            
            if (!empty($out_of_range)) {
                foreach ($out_of_range as $participant) {
                    $issues[] = "Partecipante {$participant->id} ha pettorale #{$participant->bib_number} fuori range (< {$event->bib_number_start})";
                }
            }
        }
        
        // Verifica coerenza contatore
        $max_assigned = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(bib_number) FROM {$this->participants_table} WHERE event_id = %d",
            $event_id
        ));
        
        if ($event && $max_assigned && $max_assigned >= $event->bib_number_current) {
            $issues[] = "Il contatore pettorali ({$event->bib_number_current}) è indietro rispetto al numero più alto assegnato ($max_assigned)";
        }
        
        return empty($issues) ? true : $issues;
    }
    
    /**
     * Corregge problemi di integrità pettorali
     */
    public function fix_bib_integrity($event_id) {
        global $wpdb;
        
        $fixes = array();
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Rimuovi pettorali duplicati (mantieni il primo)
            $duplicates = $wpdb->get_results($wpdb->prepare(
                "SELECT bib_number, GROUP_CONCAT(id ORDER BY created_at ASC) as participant_ids
                 FROM {$this->participants_table} 
                 WHERE event_id = %d AND bib_number IS NOT NULL
                 GROUP BY bib_number 
                 HAVING COUNT(*) > 1",
                $event_id
            ));
            
            foreach ($duplicates as $duplicate) {
                $ids = explode(',', $duplicate->participant_ids);
                $keep_id = array_shift($ids); // Mantieni il primo (più vecchio)
                
                foreach ($ids as $remove_id) {
                    $wpdb->update(
                        $this->participants_table,
                        array('bib_number' => null),
                        array('id' => $remove_id),
                        array('%s'),
                        array('%d')
                    );
                }
                
                $fixes[] = "Rimosso pettorale duplicato #{$duplicate->bib_number} da " . count($ids) . " partecipanti, mantenuto per partecipante $keep_id";
            }
            
            // Correggi contatore evento
            $max_assigned = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(bib_number) FROM {$this->participants_table} WHERE event_id = %d",
                $event_id
            ));
            
            if ($max_assigned) {
                $wpdb->update(
                    $this->events_table,
                    array('bib_number_current' => $max_assigned + 1),
                    array('id' => $event_id),
                    array('%d'),
                    array('%d')
                );
                
                $fixes[] = "Aggiornato contatore pettorali a " . ($max_assigned + 1);
            }
            
            $wpdb->query('COMMIT');
            
            amacarun_log("Bib integrity fixed for event $event_id: " . implode(', ', $fixes));
            
            return $fixes;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            amacarun_log('Error fixing bib integrity: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Genera numeri pettorali in formato personalizzato
     */
    public function format_bib_number($bib_number, $format = 'default') {
        switch ($format) {
            case 'padded':
                return str_pad($bib_number, 4, '0', STR_PAD_LEFT);
                
            case 'with_prefix':
                return 'A' . $bib_number;
                
            case 'with_year':
                return date('y') . str_pad($bib_number, 3, '0', STR_PAD_LEFT);
                
            default:
                return (string) $bib_number;
        }
    }
    
    /**
     * Imposta range personalizzato per evento
     */
    public function set_bib_range($event_id, $start_number, $reset_current = false) {
        global $wpdb;
        
        if ($start_number < 1) {
            return new WP_Error('invalid_start', 'Il numero iniziale deve essere >= 1');
        }
        
        $update_data = array('bib_number_start' => $start_number);
        
        if ($reset_current) {
            $update_data['bib_number_current'] = $start_number;
        }
        
        $result = $wpdb->update(
            $this->events_table,
            $update_data,
            array('id' => $event_id),
            array('%d', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            amacarun_log("Bib range updated for event $event_id: start=$start_number, reset_current=$reset_current");
            return true;
        }
        
        return false;
    }
    
    /**
     * Esporta lista pettorali per stampa
     */
    public function export_bibs_for_print($event_id, $format = 'simple') {
        global $wpdb;
        
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                bib_number, 
                first_name, 
                last_name, 
                participant_type,
                distance 
             FROM {$this->participants_table} 
             WHERE event_id = %d AND bib_number IS NOT NULL 
             ORDER BY bib_number ASC",
            $event_id
        ));
        
        $export_data = array();
        
        switch ($format) {
            case 'labels':
                // Formato per etichette
                foreach ($participants as $participant) {
                    $export_data[] = array(
                        'bib' => $this->format_bib_number($participant->bib_number, 'padded'),
                        'name' => $participant->first_name . ' ' . $participant->last_name,
                        'type' => $participant->participant_type === 'adult' ? 'A' : 'B',
                        'distance' => $participant->distance ?: ''
                    );
                }
                break;
                
            case 'checklist':
                // Formato per lista check-in
                foreach ($participants as $participant) {
                    $export_data[] = array(
                        'bib' => $participant->bib_number,
                        'name' => $participant->last_name . ', ' . $participant->first_name,
                        'type' => $participant->participant_type,
                        'distance' => $participant->distance ?: 'N/A',
                        'checked' => '☐'
                    );
                }
                break;
                
            default:
                // Formato semplice
                foreach ($participants as $participant) {
                    $export_data[] = array(
                        'bib_number' => $participant->bib_number,
                        'first_name' => $participant->first_name,
                        'last_name' => $participant->last_name,
                        'participant_type' => $participant->participant_type,
                        'distance' => $participant->distance
                    );
                }
        }
        
        return $export_data;
    }
    
    /**
     * Importa assegnazioni pettorali da file
     */
    public function import_bib_assignments($event_id, $assignments) {
        global $wpdb;
        
        $imported = 0;
        $errors = array();
        
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($assignments as $assignment) {
                // Validazione base
                if (empty($assignment['participant_id']) || empty($assignment['bib_number'])) {
                    $errors[] = "Dati mancanti: participant_id o bib_number";
                    continue;
                }
                
                $participant_id = intval($assignment['participant_id']);
                $bib_number = intval($assignment['bib_number']);
                
                // Verifica esistenza partecipante
                $participant_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->participants_table} WHERE id = %d AND event_id = %d",
                    $participant_id, $event_id
                ));
                
                if (!$participant_exists) {
                    $errors[] = "Partecipante $participant_id non trovato";
                    continue;
                }
                
                // Verifica disponibilità pettorale
                if (!$this->is_bib_available($event_id, $bib_number, $participant_id)) {
                    $errors[] = "Pettorale $bib_number già assegnato";
                    continue;
                }
                
                // Assegna pettorale
                $result = $wpdb->update(
                    $this->participants_table,
                    array('bib_number' => $bib_number),
                    array('id' => $participant_id),
                    array('%d'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $imported++;
                    $this->update_bib_counter_if_needed($event_id, $bib_number);
                } else {
                    $errors[] = "Errore nell'assegnazione pettorale $bib_number al partecipante $participant_id";
                }
            }
            
            $wpdb->query('COMMIT');
            
            amacarun_log("Bib import completed: $imported imported, " . count($errors) . " errors");
            
            return array(
                'imported' => $imported,
                'errors' => $errors
            );
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array(
                'imported' => 0,
                'errors' => array('Errore durante import: ' . $e->getMessage())
            );
        }
    }
    
    /**
     * Aggiorna contatore pettorali se necessario
     */
    private function update_bib_counter_if_needed($event_id, $bib_number) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT bib_number_current FROM {$this->events_table} WHERE id = %d",
            $event_id
        ));
        
        if ($event && $bib_number >= $event->bib_number_current) {
            $wpdb->update(
                $this->events_table,
                array('bib_number_current' => $bib_number + 1),
                array('id' => $event_id),
                array('%d'),
                array('%d')
            );
        }
    }
    
    /**
     * Genera report pettorali per evento
     */
    public function generate_bib_report($event_id) {
        $stats = $this->get_bib_stats($event_id);
        $integrity = $this->validate_bib_integrity($event_id);
        
        $report = array(
            'event_id' => $event_id,
            'generated_at' => current_time('mysql'),
            'statistics' => $stats,
            'integrity_check' => array(
                'valid' => $integrity === true,
                'issues' => $integrity === true ? array() : $integrity
            )
        );
        
        // Aggiungi dettagli se ci sono problemi
        if ($integrity !== true) {
            $missing_bibs = $this->get_missing_bibs_in_sequence($event_id);
            $report['missing_bibs'] = $missing_bibs;
        }
        
        return $report;
    }
}