<?php
/**
 * Classe pour la gestion des disponibilités des formateurs
 * Fonctionnalité innovante du plugin TeachMeMore PédagoConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMM_Availabilities {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_tmm_save_availability', array($this, 'save_availability'));
        add_action('wp_ajax_tmm_get_availabilities', array($this, 'get_availabilities'));
        add_action('wp_ajax_tmm_delete_availability', array($this, 'delete_availability'));
        add_action('wp_ajax_tmm_sync_calendar', array($this, 'sync_external_calendar'));
        add_action('wp_ajax_tmm_get_suggested_slots', array($this, 'get_suggested_time_slots'));
        
        // Tâche CRON pour mise à jour automatique des années
        add_action('tmm_yearly_update', array($this, 'yearly_calendar_update'));
        if (!wp_next_scheduled('tmm_yearly_update')) {
            wp_schedule_event(time(), 'yearly', 'tmm_yearly_update');
        }
    }
    
    public function init() {
        // Ajouter les métaboxes pour les formateurs
        add_action('add_meta_boxes', array($this, 'add_availability_meta_boxes'));
    }
    
    /**
     * Ajouter les métaboxes de disponibilité
     */
    public function add_availability_meta_boxes() {
        add_meta_box(
            'tmm_trainer_availability',
            'Gestion des Disponibilités',
            array($this, 'trainer_availability_meta_box'),
            'user'
        );
    }
    
    /**
     * Métabox de disponibilité pour les formateurs
     */
    public function trainer_availability_meta_box($user) {
        if (!in_array('tmm_trainer', $user->roles)) {
            return;
        }
        
        ?>
        <div id="tmm-availability-manager">
            <div class="tmm-availability-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#recurring" class="nav-tab nav-tab-active">Disponibilités Récurrentes</a>
                    <a href="#specific" class="nav-tab">Dates Spécifiques</a>
                    <a href="#unavailable" class="nav-tab">Indisponibilités</a>
                    <a href="#sync" class="nav-tab">Synchronisation</a>
                </nav>
            </div>
            
            <!-- Onglet Récurrentes -->
            <div id="recurring" class="tmm-tab-content active">
                <h4>Définir vos créneaux récurrents</h4>
                <form id="recurring-availability-form">
                    <table class="form-table">
                        <tr>
                            <th>Jour de la semaine</th>
                            <th>Heure de début</th>
                            <th>Heure de fin</th>
                            <th>Période</th>
                            <th>Actions</th>
                        </tr>
                        <tbody id="recurring-slots">
                            <!-- Lignes générées dynamiquement -->
                        </tbody>
                    </table>
                    <button type="button" id="add-recurring-slot" class="button">Ajouter un créneau</button>
                </form>
            </div>
            
            <!-- Onglet Spécifique -->
            <div id="specific" class="tmm-tab-content">
                <h4>Disponibilités ponctuelles</h4>
                <div id="specific-calendar"></div>
                <div class="tmm-availability-form">
                    <input type="date" id="specific-date" />
                    <input type="time" id="specific-start" />
                    <input type="time" id="specific-end" />
                    <textarea id="specific-note" placeholder="Note (optionnel)"></textarea>
                    <button type="button" id="add-specific-availability" class="button button-primary">Ajouter</button>
                </div>
            </div>
            
            <!-- Onglet Indisponibilités -->
            <div id="unavailable" class="tmm-tab-content">
                <h4>Périodes d'indisponibilité (congés, formations...)</h4>
                <div id="unavailable-calendar"></div>
                <div class="tmm-unavailability-form">
                    <input type="date" id="unavailable-start-date" />
                    <input type="date" id="unavailable-end-date" />
                    <textarea id="unavailable-reason" placeholder="Motif de l'indisponibilité"></textarea>
                    <button type="button" id="add-unavailability" class="button button-secondary">Marquer comme indisponible</button>
                </div>
            </div>
            
            <!-- Onglet Synchronisation -->
            <div id="sync" class="tmm-tab-content">
                <h4>Synchronisation avec calendriers externes</h4>
                <div class="tmm-sync-options">
                    <div class="sync-option">
                        <h5>Google Calendar</h5>
                        <button type="button" id="sync-google-calendar" class="button">Synchroniser avec Google</button>
                        <p class="description">Importer vos événements Google Calendar comme indisponibilités</p>
                    </div>
                    <div class="sync-option">
                        <h5>Import iCal/ICS</h5>
                        <input type="file" id="ical-import" accept=".ics,.ical" />
                        <button type="button" id="import-ical" class="button">Importer fichier ICS</button>
                    </div>
                    <div class="sync-option">
                        <h5>Export de vos disponibilités</h5>
                        <button type="button" id="export-availabilities" class="button">Télécharger ICS</button>
                        <p class="description">Importer dans votre agenda personnel</p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestion des onglets
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tmm-tab-content').removeClass('active');
                $(this).addClass('nav-tab-active');
                $($(this).attr('href')).addClass('active');
            });
            
            // Charger les disponibilités existantes
            loadExistingAvailabilities(<?php echo $user->ID; ?>);
            
            // Initialiser les calendriers
            initSpecificCalendar();
            initUnavailableCalendar();
        });
        </script>
        <?php
    }
    
    /**
     * Sauvegarder une disponibilité via AJAX
     */
    public function save_availability() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        if (!current_user_can('tmm_trainer')) {
            wp_die('Accès non autorisé');
        }
        
        global $wpdb;
        
        $user_id = intval($_POST['user_id']);
        $start_datetime = sanitize_text_field($_POST['start_datetime']);
        $end_datetime = sanitize_text_field($_POST['end_datetime']);
        $is_recurring = intval($_POST['is_recurring']);
        $recurrence_rule = sanitize_text_field($_POST['recurrence_rule']);
        $availability_type = sanitize_text_field($_POST['availability_type']);
        $note = sanitize_textarea_field($_POST['note']);
        
        // Validation des données
        if (empty($start_datetime) || empty($end_datetime)) {
            wp_send_json_error('Dates requises');
        }
        
        // Vérifier les conflits
        $conflicts = $this->check_availability_conflicts($user_id, $start_datetime, $end_datetime);
        if ($conflicts) {
            wp_send_json_error('Conflit détecté avec une disponibilité existante');
        }
        
        // Insérer en base
        $table = $wpdb->prefix . 'tmm_availabilities';
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'start_datetime' => $start_datetime,
                'end_datetime' => $end_datetime,
                'is_recurring' => $is_recurring,
                'recurrence_rule' => $recurrence_rule,
                'availability_type' => $availability_type,
                'note' => $note
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Notification des responsables pédagogiques
            $this->notify_availability_update($user_id);
            wp_send_json_success('Disponibilité enregistrée');
        } else {
            wp_send_json_error('Erreur lors de l\'enregistrement');
        }
    }
    
    /**
     * Récupérer les disponibilités via AJAX
     */
    public function get_availabilities() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $user_id = intval($_POST['user_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_availabilities';
        $availabilities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             AND ((start_datetime BETWEEN %s AND %s) 
             OR (end_datetime BETWEEN %s AND %s)
             OR (is_recurring = 1))
             ORDER BY start_datetime ASC",
            $user_id, $start_date, $end_date, $start_date, $end_date
        ));
        
        // Traiter les récurrences
        $processed_availabilities = $this->process_recurring_availabilities($availabilities, $start_date, $end_date);
        
        wp_send_json_success($processed_availabilities);
    }
    
    /**
     * Supprimer une disponibilité
     */
    public function delete_availability() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        if (!current_user_can('tmm_trainer')) {
            wp_die('Accès non autorisé');
        }
        
        global $wpdb;
        
        $availability_id = intval($_POST['availability_id']);
        $user_id = get_current_user_id();
        
        $table = $wpdb->prefix . 'tmm_availabilities';
        $result = $wpdb->delete(
            $table,
            array('id' => $availability_id, 'user_id' => $user_id),
            array('%d', '%d')
        );
        
        if ($result) {
            wp_send_json_success('Disponibilité supprimée');
        } else {
            wp_send_json_error('Erreur lors de la suppression');
        }
    }
    
    /**
     * Synchronisation avec calendrier externe
     */
    public function sync_external_calendar() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $calendar_type = sanitize_text_field($_POST['calendar_type']);
        $user_id = get_current_user_id();
        
        switch ($calendar_type) {
            case 'google':
                $result = $this->sync_google_calendar($user_id);
                break;
            case 'ical':
                $result = $this->import_ical_file($user_id, $_FILES['ical_file']);
                break;
            default:
                wp_send_json_error('Type de calendrier non supporté');
        }
        
        if ($result) {
            wp_send_json_success('Synchronisation réussie');
        } else {
            wp_send_json_error('Erreur lors de la synchronisation');
        }
    }
    
    /**
     * Suggérer des créneaux optimaux
     */
    public function get_suggested_time_slots() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $module_id = intval($_POST['module_id']);
        $school_id = intval($_POST['school_id']);
        $duration_hours = floatval($_POST['duration_hours']);
        $preferred_start_date = sanitize_text_field($_POST['preferred_start_date']);
        
        // Algorithme de suggestion intelligent
        $suggestions = $this->calculate_optimal_time_slots($module_id, $school_id, $duration_hours, $preferred_start_date);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * Algorithme de calcul des créneaux optimaux
     */
    private function calculate_optimal_time_slots($module_id, $school_id, $duration_hours, $start_date) {
        global $wpdb;
        
        // 1. Récupérer les formateurs compétents pour ce module
        $qualified_trainers = $this->get_qualified_trainers($module_id);
        
        // 2. Récupérer les contraintes de l'école
        $school_constraints = $this->get_school_constraints($school_id);
        
        $suggestions = array();
        
        foreach ($qualified_trainers as $trainer) {
            // 3. Récupérer les disponibilités du formateur
            $trainer_availabilities = $this->get_trainer_availabilities($trainer->ID, $start_date);
            
            // 4. Croiser avec les contraintes école
            $compatible_slots = $this->cross_check_availability($trainer_availabilities, $school_constraints, $duration_hours);
            
            foreach ($compatible_slots as $slot) {
                $suggestions[] = array(
                    'trainer_id' => $trainer->ID,
                    'trainer_name' => $trainer->display_name,
                    'start_datetime' => $slot['start'],
                    'end_datetime' => $slot['end'],
                    'compatibility_score' => $slot['score'], // Score de compatibilité
                    'reason' => $slot['reason']
                );
            }
        }
        
        // 5. Trier par score de compatibilité
        usort($suggestions, function($a, $b) {
            return $b['compatibility_score'] <=> $a['compatibility_score'];
        });
        
        return array_slice($suggestions, 0, 10); // Top 10 des suggestions
    }
    
    /**
     * Vérifier les conflits de disponibilité
     */
    private function check_availability_conflicts($user_id, $start_datetime, $end_datetime) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_availabilities';
        $conflicts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE user_id = %d 
             AND availability_type = 'available'
             AND (
                (start_datetime BETWEEN %s AND %s) 
                OR (end_datetime BETWEEN %s AND %s)
                OR (start_datetime <= %s AND end_datetime >= %s)
             )",
            $user_id, $start_datetime, $end_datetime, $start_datetime, $end_datetime, $start_datetime, $end_datetime
        ));
        
        return $conflicts > 0;
    }
    
    /**
     * Traiter les disponibilités récurrentes
     */
    private function process_recurring_availabilities($availabilities, $start_date, $end_date) {
        $processed = array();
        
        foreach ($availabilities as $availability) {
            if ($availability->is_recurring) {
                // Générer les occurrences entre start_date et end_date
                $occurrences = $this->generate_recurring_occurrences($availability, $start_date, $end_date);
                $processed = array_merge($processed, $occurrences);
            } else {
                $processed[] = $availability;
            }
        }
        
        return $processed;
    }
    
    /**
     * Générer les occurrences récurrentes
     */
    private function generate_recurring_occurrences($availability, $start_date, $end_date) {
        $occurrences = array();
        $rule = json_decode($availability->recurrence_rule, true);
        
        if (!$rule) return array();
        
        // Parser la règle de récurrence (RRULE simplifiée)
        switch ($rule['freq']) {
            case 'WEEKLY':
                $occurrences = $this->generate_weekly_occurrences($availability, $rule, $start_date, $end_date);
                break;
            case 'DAILY':
                $occurrences = $this->generate_daily_occurrences($availability, $rule, $start_date, $end_date);
                break;
        }
        
        return $occurrences;
    }
    
    /**
     * Notification de mise à jour des disponibilités
     */
    private function notify_availability_update($trainer_id) {
        $trainer = get_user_by('ID', $trainer_id);
        
        // Notifier les responsables pédagogiques
        $pedagog_managers = get_users(array('role' => 'tmm_pedagog_manager'));
        
        foreach ($pedagog_managers as $manager) {
            $this->create_notification(
                $manager->ID,
                'Mise à jour disponibilités',
                "Le formateur {$trainer->display_name} a mis à jour ses disponibilités.",
                'info',
                null
            );
        }
    }
    
    /**
     * Créer une notification
     */
    private function create_notification($user_id, $title, $message, $type = 'info', $related_post_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_notifications';
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'title' => $title,
                'message' => $message,
                'notification_type' => $type,
                'related_post_id' => $related_post_id
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );
    }
    
    /**
     * Mise à jour automatique annuelle des calendriers
     */
    public function yearly_calendar_update() {
        // Mettre à jour les règles de récurrence pour la nouvelle année
        // Ajuster les jours fériés
        // Reconduire les disponibilités récurrentes
        
        global $wpdb;
        
        $current_year = date('Y');
        $next_year = $current_year + 1;
        
        // Log de l'opération
        error_log("TMM PédagoConnect: Mise à jour automatique calendrier pour l'année $next_year");
        
        // Ici on pourrait ajouter la logique de mise à jour automatique
        do_action('tmm_yearly_calendar_updated', $next_year);
    }
    
    /**
     * Récupérer les formateurs qualifiés pour un module
     */
    private function get_qualified_trainers($module_id) {
        // Récupérer les métadonnées du module pour connaître les compétences requises
        $required_skills = get_post_meta($module_id, 'required_skills', true);
        
        if (!$required_skills) {
            // Si pas de compétences spécifiées, retourner tous les formateurs
            return get_users(array('role' => 'tmm_trainer'));
        }
        
        // Sinon, filtrer par compétences
        $args = array(
            'role' => 'tmm_trainer',
            'meta_query' => array(
                array(
                    'key' => 'trainer_skills',
                    'value' => $required_skills,
                    'compare' => 'LIKE'
                )
            )
        );
        
        return get_users($args);
    }
    
    /**
     * Récupérer les contraintes de l'école
     */
    private function get_school_constraints($school_id) {
        return array(
            'working_hours_start' => get_post_meta($school_id, 'working_hours_start', true) ?: '08:00',
            'working_hours_end' => get_post_meta($school_id, 'working_hours_end', true) ?: '18:00',
            'working_days' => get_post_meta($school_id, 'working_days', true) ?: array(1,2,3,4,5), // Lun-Ven
            'vacation_periods' => get_post_meta($school_id, 'vacation_periods', true) ?: array(),
            'room_availability' => get_post_meta($school_id, 'room_availability', true) ?: array()
        );
    }
    
    /**
     * Récupérer les disponibilités d'un formateur
     */
    private function get_trainer_availabilities($trainer_id, $start_date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_availabilities';
        $end_date = date('Y-m-d', strtotime($start_date . ' +3 months')); // 3 mois de recherche
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             AND availability_type = 'available'
             AND start_datetime >= %s 
             AND start_datetime <= %s
             ORDER BY start_datetime ASC",
            $trainer_id, $start_date, $end_date
        ));
    }
    
    /**
     * Croiser les disponibilités
     */
    private function cross_check_availability($trainer_availabilities, $school_constraints, $duration_hours) {
        $compatible_slots = array();
        
        foreach ($trainer_availabilities as $availability) {
            $start = new DateTime($availability->start_datetime);
            $end = new DateTime($availability->end_datetime);
            
            // Vérifier si le créneau est dans les heures de travail de l'école
            $start_hour = $start->format('H:i');
            $end_hour = $end->format('H:i');
            
            if ($start_hour >= $school_constraints['working_hours_start'] && 
                $end_hour <= $school_constraints['working_hours_end']) {
                
                // Vérifier si la durée est suffisante
                $available_duration = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                
                if ($available_duration >= $duration_hours) {
                    $compatible_slots[] = array(
                        'start' => $availability->start_datetime,
                        'end' => date('Y-m-d H:i:s', $start->getTimestamp() + ($duration_hours * 3600)),
                        'score' => $this->calculate_compatibility_score($availability, $school_constraints),
                        'reason' => 'Compatible avec horaires école'
                    );
                }
            }
        }
        
        return $compatible_slots;
    }
    
    /**
     * Calculer le score de compatibilité
     */
    private function calculate_compatibility_score($availability, $school_constraints) {
        $score = 50; // Score de base
        
        // Bonus si c'est en semaine
        $dayOfWeek = date('N', strtotime($availability->start_datetime));
        if (in_array($dayOfWeek, $school_constraints['working_days'])) {
            $score += 20;
        }
        
        // Bonus si c'est dans les heures préférées (matin)
        $hour = date('H', strtotime($availability->start_datetime));
        if ($hour >= 9 && $hour <= 11) {
            $score += 15;
        }
        
        // Malus si c'est trop tôt ou trop tard
        if ($hour < 8 || $hour > 17) {
            $score -= 10;
        }
        
        return max(0, min(100, $score));
    }
}

// Initialiser la classe
new TMM_Availabilities();