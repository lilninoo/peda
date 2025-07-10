<?php
/**
 * Classe pour la gestion collaborative des plannings
 * Interface entre TeachMeMore et les écoles partenaires
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMM_Planning {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // Actions AJAX pour la gestion des plannings
        add_action('wp_ajax_tmm_propose_session', array($this, 'propose_session'));
        add_action('wp_ajax_tmm_respond_to_proposal', array($this, 'respond_to_proposal'));
        add_action('wp_ajax_tmm_get_planning_data', array($this, 'get_planning_data'));
        add_action('wp_ajax_tmm_update_session_status', array($this, 'update_session_status'));
        add_action('wp_ajax_tmm_get_school_calendar', array($this, 'get_school_calendar'));
        add_action('wp_ajax_tmm_export_planning', array($this, 'export_planning'));
        add_action('wp_ajax_tmm_import_school_constraints', array($this, 'import_school_constraints'));
        add_action('wp_ajax_tmm_auto_suggest_planning', array($this, 'auto_suggest_planning'));
        
        // Actions pour les utilisateurs non-connectés (écoles partenaires)
        add_action('wp_ajax_nopriv_tmm_respond_to_proposal', array($this, 'respond_to_proposal'));
        add_action('wp_ajax_nopriv_tmm_get_school_calendar', array($this, 'get_school_calendar'));
    }
    
    public function init() {
        // Ajouter les métaboxes pour les sessions
        add_action('add_meta_boxes', array($this, 'add_planning_meta_boxes'));
        add_action('save_post', array($this, 'save_session_meta'));
        
        // Statuts personnalisés pour les sessions
        add_action('init', array($this, 'register_session_statuses'));
    }
    
    /**
     * Enregistrer les statuts personnalisés pour les sessions
     */
    public function register_session_statuses() {
        register_post_status('proposed', array(
            'label' => 'Proposée',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Proposée (%s)', 'Proposées (%s)')
        ));
        
        register_post_status('confirmed', array(
            'label' => 'Confirmée',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Confirmée (%s)', 'Confirmées (%s)')
        ));
        
        register_post_status('in_progress', array(
            'label' => 'En cours',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('En cours (%s)', 'En cours (%s)')
        ));
        
        register_post_status('completed', array(
            'label' => 'Terminée',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Terminée (%s)', 'Terminées (%s)')
        ));
        
        register_post_status('cancelled', array(
            'label' => 'Annulée',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Annulée (%s)', 'Annulées (%s)')
        ));
    }
    
    /**
     * Ajouter les métaboxes de planning
     */
    public function add_planning_meta_boxes() {
        add_meta_box(
            'tmm_session_details',
            'Détails de la Session',
            array($this, 'session_details_meta_box'),
            'tmm_session',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tmm_session_planning',
            'Planification Collaborative',
            array($this, 'session_planning_meta_box'),
            'tmm_session',
            'side',
            'high'
        );
        
        add_meta_box(
            'tmm_session_tracking',
            'Suivi & Réalisation',
            array($this, 'session_tracking_meta_box'),
            'tmm_session',
            'normal',
            'default'
        );
    }
    
    /**
     * Métabox des détails de session
     */
    public function session_details_meta_box($post) {
        wp_nonce_field('tmm_session_meta', 'tmm_session_meta_nonce');
        
        // Récupérer les données existantes
        $session_data = $this->get_session_data($post->ID);
        $schools = get_posts(array('post_type' => 'tmm_school', 'numberposts' => -1));
        $modules = get_posts(array('post_type' => 'tmm_module', 'numberposts' => -1));
        $trainers = get_users(array('role' => 'tmm_trainer'));
        
        ?>
        <div class="tmm-session-details">
            <table class="form-table">
                <tr>
                    <th><label for="tmm_school_id">École Partenaire</label></th>
                    <td>
                        <select id="tmm_school_id" name="tmm_school_id" class="widefat" required>
                            <option value="">Sélectionner une école</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school->ID; ?>" 
                                    <?php selected($session_data['school_id'], $school->ID); ?>>
                                    <?php echo esc_html($school->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_module_id">Module TeachMeMore</label></th>
                    <td>
                        <select id="tmm_module_id" name="tmm_module_id" class="widefat" required>
                            <option value="">Sélectionner un module</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?php echo $module->ID; ?>" 
                                    <?php selected($session_data['module_id'], $module->ID); ?>>
                                    <?php echo esc_html($module->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Les objectifs et compétences seront automatiquement récupérés</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_trainer_id">Formateur Assigné</label></th>
                    <td>
                        <select id="tmm_trainer_id" name="tmm_trainer_id" class="widefat">
                            <option value="">À assigner</option>
                            <?php foreach ($trainers as $trainer): ?>
                                <option value="<?php echo $trainer->ID; ?>" 
                                    <?php selected($session_data['trainer_id'], $trainer->ID); ?>>
                                    <?php echo esc_html($trainer->display_name); ?>
                                    <?php 
                                    $skills = get_user_meta($trainer->ID, 'trainer_skills', true);
                                    if ($skills) echo ' - ' . esc_html($skills);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="suggest-trainer" class="button">Suggérer automatiquement</button>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_start_datetime">Date & Heure de début</label></th>
                    <td>
                        <input type="datetime-local" id="tmm_start_datetime" name="tmm_start_datetime" 
                               value="<?php echo esc_attr($session_data['start_datetime']); ?>" class="widefat" required>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_end_datetime">Date & Heure de fin</label></th>
                    <td>
                        <input type="datetime-local" id="tmm_end_datetime" name="tmm_end_datetime" 
                               value="<?php echo esc_attr($session_data['end_datetime']); ?>" class="widefat" required>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_hours_planned">Heures Planifiées</label></th>
                    <td>
                        <input type="number" id="tmm_hours_planned" name="tmm_hours_planned" 
                               value="<?php echo esc_attr($session_data['hours_planned']); ?>" 
                               step="0.5" min="0.5" max="40" class="small-text">
                        <span class="description">heures</span>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_location">Lieu</label></th>
                    <td>
                        <input type="text" id="tmm_location" name="tmm_location" 
                               value="<?php echo esc_attr($session_data['location']); ?>" 
                               placeholder="Salle, campus, ou lien visio" class="widefat">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_group_name">Groupe/Promotion</label></th>
                    <td>
                        <input type="text" id="tmm_group_name" name="tmm_group_name" 
                               value="<?php echo esc_attr($session_data['group_name']); ?>" 
                               placeholder="Ex: M1 DevOps, Promo 2025..." class="widefat">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_session_notes">Notes & Instructions</label></th>
                    <td>
                        <textarea id="tmm_session_notes" name="tmm_session_notes" 
                                  rows="4" class="widefat"><?php echo esc_textarea($session_data['notes']); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-calcul des heures
            $('#tmm_start_datetime, #tmm_end_datetime').change(function() {
                var start = new Date($('#tmm_start_datetime').val());
                var end = new Date($('#tmm_end_datetime').val());
                if (start && end && end > start) {
                    var hours = (end - start) / (1000 * 60 * 60);
                    $('#tmm_hours_planned').val(hours);
                }
            });
            
            // Suggestion automatique de formateur
            $('#suggest-trainer').click(function() {
                var moduleId = $('#tmm_module_id').val();
                if (!moduleId) {
                    alert('Veuillez d\'abord sélectionner un module');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'tmm_suggest_trainer',
                    module_id: moduleId,
                    start_datetime: $('#tmm_start_datetime').val(),
                    nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#tmm_trainer_id').val(response.data.trainer_id);
                        alert('Formateur suggéré: ' + response.data.trainer_name);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Métabox de planification collaborative
     */
    public function session_planning_meta_box($post) {
        $session_data = $this->get_session_data($post->ID);
        $current_status = get_post_status($post->ID);
        ?>
        <div class="tmm-planning-status">
            <h4>Statut Actuel: <span class="status-<?php echo $current_status; ?>"><?php echo $this->get_status_label($current_status); ?></span></h4>
            
            <?php if ($current_status === 'proposed'): ?>
                <div class="pending-approval">
                    <p><strong>⏳ En attente de réponse de l'école</strong></p>
                    <p class="description">L'école doit confirmer ou proposer une alternative</p>
                    
                    <button type="button" id="send-reminder" class="button">Envoyer un rappel</button>
                    <button type="button" id="modify-proposal" class="button">Modifier la proposition</button>
                </div>
            <?php elseif ($current_status === 'confirmed'): ?>
                <div class="confirmed-session">
                    <p><strong>✅ Session confirmée</strong></p>
                    <p class="description">Prête pour réalisation</p>
                    
                    <button type="button" id="start-session" class="button button-primary">Marquer comme en cours</button>
                    <button type="button" id="upload-materials" class="button">Déposer supports</button>
                </div>
            <?php endif; ?>
            
            <div class="quick-actions">
                <h4>Actions Rapides</h4>
                <button type="button" id="duplicate-session" class="button">Dupliquer session</button>
                <button type="button" id="generate-ics" class="button">Générer ICS</button>
                <button type="button" id="contact-school" class="button">Contacter l'école</button>
            </div>
            
            <div class="collaboration-history">
                <h4>Historique des échanges</h4>
                <div id="session-timeline">
                    <?php echo $this->get_session_timeline($post->ID); ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#send-reminder').click(function() {
                $.post(ajaxurl, {
                    action: 'tmm_send_reminder',
                    session_id: <?php echo $post->ID; ?>,
                    nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Rappel envoyé à l\'école');
                    }
                });
            });
            
            $('#start-session').click(function() {
                if (confirm('Marquer cette session comme en cours?')) {
                    $.post(ajaxurl, {
                        action: 'tmm_update_session_status',
                        session_id: <?php echo $post->ID; ?>,
                        new_status: 'in_progress',
                        nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Métabox de suivi et réalisation
     */
    public function session_tracking_meta_box($post) {
        $session_data = $this->get_session_data($post->ID);
        ?>
        <div class="tmm-session-tracking">
            <table class="form-table">
                <tr>
                    <th><label for="tmm_hours_realized">Heures Réalisées</label></th>
                    <td>
                        <input type="number" id="tmm_hours_realized" name="tmm_hours_realized" 
                               value="<?php echo esc_attr($session_data['hours_realized']); ?>" 
                               step="0.5" min="0" class="small-text">
                        <span class="description">heures (<?php echo $session_data['hours_planned']; ?>h planifiées)</span>
                    </td>
                </tr>
                
                <tr>
                    <th>Documents & Ressources</th>
                    <td>
                        <div id="session-attachments">
                            <?php echo $this->get_session_attachments($post->ID); ?>
                        </div>
                        <button type="button" id="add-attachment" class="button">Ajouter un document</button>
                    </td>
                </tr>
                
                <tr>
                    <th>Feuille d'émargement</th>
                    <td>
                        <div id="attendance-sheet">
                            <?php echo $this->get_attendance_sheet($post->ID); ?>
                        </div>
                        <button type="button" id="upload-attendance" class="button">Télécharger émargement</button>
                        <button type="button" id="generate-attendance" class="button">Générer feuille</button>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_feedback_trainer">Feedback Formateur</label></th>
                    <td>
                        <textarea id="tmm_feedback_trainer" name="tmm_feedback_trainer" 
                                  rows="3" class="widefat" 
                                  placeholder="Retour d'expérience, difficultés rencontrées, suggestions..."><?php 
                            echo esc_textarea(get_post_meta($post->ID, 'feedback_trainer', true)); 
                        ?></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="tmm_feedback_school">Feedback École</label></th>
                    <td>
                        <textarea id="tmm_feedback_school" name="tmm_feedback_school" 
                                  rows="3" class="widefat" readonly><?php 
                            echo esc_textarea(get_post_meta($post->ID, 'feedback_school', true)); 
                        ?></textarea>
                        <p class="description">Retour de l'école (mis à jour automatiquement)</p>
                    </td>
                </tr>
                
                <tr>
                    <th>Indicateurs</th>
                    <td>
                        <div class="session-indicators">
                            <div class="indicator">
                                <span class="indicator-label">Taux de réalisation:</span>
                                <span class="indicator-value"><?php echo $this->calculate_completion_rate($session_data); ?>%</span>
                            </div>
                            <div class="indicator">
                                <span class="indicator-label">Satisfaction école:</span>
                                <span class="indicator-value"><?php echo $this->get_school_satisfaction($post->ID); ?></span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Sauvegarder les métadonnées de session
     */
    public function save_session_meta($post_id) {
        if (!isset($_POST['tmm_session_meta_nonce']) || 
            !wp_verify_nonce($_POST['tmm_session_meta_nonce'], 'tmm_session_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Sauvegarder dans la table personnalisée ET en métadonnées
        global $wpdb;
        
        $session_data = array(
            'school_id' => intval($_POST['tmm_school_id']),
            'module_id' => intval($_POST['tmm_module_id']),
            'trainer_id' => intval($_POST['tmm_trainer_id']),
            'start_datetime' => sanitize_text_field($_POST['tmm_start_datetime']),
            'end_datetime' => sanitize_text_field($_POST['tmm_end_datetime']),
            'hours_planned' => floatval($_POST['tmm_hours_planned']),
            'hours_realized' => floatval($_POST['tmm_hours_realized']),
            'location' => sanitize_text_field($_POST['tmm_location']),
            'group_name' => sanitize_text_field($_POST['tmm_group_name']),
            'notes' => sanitize_textarea_field($_POST['tmm_session_notes'])
        );
        
        // Mettre à jour ou insérer dans la table tmm_sessions_meta
        $table = $wpdb->prefix . 'tmm_sessions_meta';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE session_id = %d", $post_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $table,
                array_merge($session_data, array('updated_at' => current_time('mysql'))),
                array('session_id' => $post_id),
                array('%d', '%d', '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table,
                array_merge($session_data, array('session_id' => $post_id)),
                array('%d', '%d', '%d', '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s')
            );
        }
        
        // Sauvegarder aussi en métadonnées pour compatibilité
        foreach ($session_data as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        
        // Feedback spécifiques
        if (isset($_POST['tmm_feedback_trainer'])) {
            update_post_meta($post_id, 'feedback_trainer', sanitize_textarea_field($_POST['tmm_feedback_trainer']));
        }
        
        // Notifier l'école si statut change
        $this->notify_session_update($post_id);
    }
    
    /**
     * Proposer une session via AJAX
     */
    public function propose_session() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        if (!current_user_can('tmm_pedagog_manager')) {
            wp_send_json_error('Accès non autorisé');
        }
        
        $session_data = array(
            'post_title' => sanitize_text_field($_POST['session_title']),
            'post_type' => 'tmm_session',
            'post_status' => 'proposed',
            'post_author' => get_current_user_id()
        );
        
        $session_id = wp_insert_post($session_data);
        
        if ($session_id) {
            // Sauvegarder les détails
            $this->save_session_details($session_id, $_POST);
            
            // Notifier l'école
            $this->notify_school_new_proposal($session_id);
            
            wp_send_json_success(array(
                'session_id' => $session_id,
                'message' => 'Proposition envoyée à l\'école'
            ));
        } else {
            wp_send_json_error('Erreur lors de la création de la session');
        }
    }
    
    /**
     * Répondre à une proposition via AJAX
     */
    public function respond_to_proposal() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $response = sanitize_text_field($_POST['response']); // 'accept', 'counter', 'reject'
        $comment = sanitize_textarea_field($_POST['comment']);
        
        // Vérifier que l'utilisateur a le droit de répondre
        if (!$this->can_user_respond_to_session($session_id)) {
            wp_send_json_error('Accès non autorisé');
        }
        
        switch ($response) {
            case 'accept':
                wp_update_post(array('ID' => $session_id, 'post_status' => 'confirmed'));
                $this->log_session_event($session_id, 'École a accepté la proposition', $comment);
                break;
                
            case 'counter':
                // Demande de modification
                $counter_data = array(
                    'new_start' => sanitize_text_field($_POST['counter_start']),
                    'new_end' => sanitize_text_field($_POST['counter_end']),
                    'new_location' => sanitize_text_field($_POST['counter_location'])
                );
                $this->create_counter_proposal($session_id, $counter_data, $comment);
                break;
                
            case 'reject':
                wp_update_post(array('ID' => $session_id, 'post_status' => 'cancelled'));
                $this->log_session_event($session_id, 'École a rejeté la proposition', $comment);
                break;
        }
        
        // Notifier TeachMeMore
        $this->notify_tmm_response($session_id, $response, $comment);
        
        wp_send_json_success('Réponse enregistrée');
    }
    
    /**
     * Obtenir les données de planning via AJAX
     */
    public function get_planning_data() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $view_type = sanitize_text_field($_POST['view_type']); // 'global', 'school', 'trainer'
        $filter_id = intval($_POST['filter_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        
        $planning_data = $this->get_sessions_for_calendar($view_type, $filter_id, $start_date, $end_date);
        
        wp_send_json_success($planning_data);
    }
    
    /**
     * Mettre à jour le statut d'une session
     */
    public function update_session_status() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        $comment = sanitize_textarea_field($_POST['comment']);
        
        if (!current_user_can('edit_post', $session_id)) {
            wp_send_json_error('Accès non autorisé');
        }
        
        $result = wp_update_post(array(
            'ID' => $session_id,
            'post_status' => $new_status
        ));
        
        if ($result) {
            $this->log_session_event($session_id, "Statut changé vers: $new_status", $comment);
            $this->notify_status_change($session_id, $new_status);
            wp_send_json_success('Statut mis à jour');
        } else {
            wp_send_json_error('Erreur lors de la mise à jour');
        }
    }
    
    /**
     * Suggestion automatique de planning
     */
    public function auto_suggest_planning() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $school_id = intval($_POST['school_id']);
        $module_id = intval($_POST['module_id']);
        $desired_start_date = sanitize_text_field($_POST['desired_start_date']);
        $total_hours = floatval($_POST['total_hours']);
        
        // Algorithme de suggestion intelligent
        $suggestions = $this->generate_planning_suggestions($school_id, $module_id, $desired_start_date, $total_hours);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * Générer des suggestions de planning
     */
    private function generate_planning_suggestions($school_id, $module_id, $start_date, $total_hours) {
        // 1. Récupérer les contraintes de l'école
        $school_constraints = $this->get_school_constraints($school_id);
        
        // 2. Récupérer les formateurs disponibles
        $available_trainers = $this->get_available_trainers($module_id, $start_date);
        
        // 3. Générer plusieurs options de planning
        $suggestions = array();
        
        // Option 1: Sessions intensives (journées complètes)
        $intensive_option = $this->generate_intensive_schedule($school_constraints, $available_trainers, $start_date, $total_hours);
        if ($intensive_option) {
            $suggestions[] = $intensive_option;
        }
        
        // Option 2: Sessions étalées (demi-journées)
        $spread_option = $this->generate_spread_schedule($school_constraints, $available_trainers, $start_date, $total_hours);
        if ($spread_option) {
            $suggestions[] = $spread_option;
        }
        
        // Option 3: Sessions hebdomadaires
        $weekly_option = $this->generate_weekly_schedule($school_constraints, $available_trainers, $start_date, $total_hours);
        if ($weekly_option) {
            $suggestions[] = $weekly_option;
        }
        
        return $suggestions;
    }
    
    /**
     * Obtenir les données d'une session
     */
    private function get_session_data($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_sessions_meta';
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %d", $session_id
        ), ARRAY_A);
        
        if (!$data) {
            // Valeurs par défaut
            $data = array(
                'school_id' => '',
                'module_id' => '',
                'trainer_id' => '',
                'start_datetime' => '',
                'end_datetime' => '',
                'hours_planned' => 0,
                'hours_realized' => 0,
                'location' => '',
                'group_name' => '',
                'notes' => ''
            );
        }
        
        return $data;
    }
    
    /**
     * Obtenir le libellé d'un statut
     */
    private function get_status_label($status) {
        $labels = array(
            'proposed' => 'Proposée',
            'confirmed' => 'Confirmée',
            'in_progress' => 'En cours',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * Obtenir la timeline d'une session
     */
    private function get_session_timeline($session_id) {
        global $wpdb;
        
        // Récupérer l'historique des événements
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmm_session_events 
             WHERE session_id = %d 
             ORDER BY created_at DESC",
            $session_id
        ));
        
        if (empty($events)) {
            return '<p>Aucun événement enregistré</p>';
        }
        
        $html = '<ul class="session-timeline">';
        foreach ($events as $event) {
            $html .= sprintf(
                '<li class="timeline-event">
                    <div class="event-date">%s</div>
                    <div class="event-title">%s</div>
                    <div class="event-description">%s</div>
                </li>',
                date('d/m/Y H:i', strtotime($event->created_at)),
                esc_html($event->event_title),
                esc_html($event->event_description)
            );
        }
        $html .= '</ul>';
        
        return $html;
    }
    
    /**
     * Enregistrer un événement de session
     */
    private function log_session_event($session_id, $title, $description = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_session_events';
        $wpdb->insert(
            $table,
            array(
                'session_id' => $session_id,
                'event_title' => $title,
                'event_description' => $description,
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Notifier l'école d'une nouvelle proposition
     */
    private function notify_school_new_proposal($session_id) {
        $session_data = $this->get_session_data($session_id);
        $school = get_post($session_data['school_id']);
        $module = get_post($session_data['module_id']);
        
        // Récupérer les contacts de l'école
        $school_contacts = get_post_meta($session_data['school_id'], 'contact_emails', true);
        
        if ($school_contacts) {
            $subject = 'Nouvelle proposition de session TeachMeMore';
            $message = sprintf(
                'Bonjour,

TeachMeMore vous propose une nouvelle session:

Module: %s
Date: %s
Durée: %s heures
Lieu: %s

Connectez-vous à votre espace partenaire pour répondre à cette proposition:
%s

Cordialement,
L\'équipe TeachMeMore',
                $module->post_title,
                date('d/m/Y H:i', strtotime($session_data['start_datetime'])),
                $session_data['hours_planned'],
                $session_data['location'],
                home_url('/espace-partenaire/')
            );
            
            wp_mail($school_contacts, $subject, $message);
        }
        
        // Créer une notification dans le système
        $this->create_notification(
            0, // Notification système
            'Nouvelle proposition envoyée',
            "Proposition envoyée à {$school->post_title} pour le module {$module->post_title}",
            'info',
            $session_id
        );
    }
    
    /**
     * Calculer le taux de réalisation
     */
    private function calculate_completion_rate($session_data) {
        if ($session_data['hours_planned'] == 0) return 0;
        return round(($session_data['hours_realized'] / $session_data['hours_planned']) * 100);
    }
    
    /**
     * Obtenir la satisfaction de l'école
     */
    private function get_school_satisfaction($session_id) {
        $rating = get_post_meta($session_id, 'school_satisfaction_rating', true);
        if (!$rating) return 'Non évaluée';
        
        $stars = str_repeat('⭐', intval($rating));
        return $stars . ' (' . $rating . '/5)';
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
     * Vérifier si un utilisateur peut répondre à une session
     */
    private function can_user_respond_to_session($session_id) {
        // Vérifier si l'utilisateur est associé à l'école de la session
        $session_data = $this->get_session_data($session_id);
        $current_user = wp_get_current_user();
        
        if (in_array('partner_school', $current_user->roles)) {
            $user_school_id = get_user_meta($current_user->ID, 'school_id', true);
            return $user_school_id == $session_data['school_id'];
        }
        
        return current_user_can('tmm_pedagog_manager');
    }
    
    /**
     * Notifier de la mise à jour d'une session
     */
    private function notify_session_update($session_id) {
        // Logique de notification selon le contexte
        do_action('tmm_session_updated', $session_id);
    }
}

// Initialiser la classe
new TMM_Planning();