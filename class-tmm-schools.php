<?php
/**
 * Classe pour la gestion des écoles partenaires
 * CRUD complet et fonctionnalités avancées
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMM_Schools {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // Hooks pour les métaboxes des écoles
        add_action('add_meta_boxes', array($this, 'add_school_meta_boxes'));
        add_action('save_post', array($this, 'save_school_meta'), 10, 2);
        
        // Actions AJAX
        add_action('wp_ajax_tmm_search_schools', array($this, 'search_schools'));
        add_action('wp_ajax_tmm_get_school_info', array($this, 'get_school_info'));
        add_action('wp_ajax_tmm_update_school_settings', array($this, 'update_school_settings'));
        add_action('wp_ajax_tmm_get_school_stats', array($this, 'get_school_stats'));
        add_action('wp_ajax_tmm_sync_school_calendar', array($this, 'sync_school_calendar'));
        add_action('wp_ajax_tmm_invite_school_user', array($this, 'invite_school_user'));
        
        // Colonnes personnalisées
        add_filter('manage_tmm_school_posts_columns', array($this, 'school_columns'));
        add_action('manage_tmm_school_posts_custom_column', array($this, 'school_column_content'), 10, 2);
        
        // Filtres de recherche
        add_action('restrict_manage_posts', array($this, 'school_admin_filters'));
        add_filter('parse_query', array($this, 'filter_school_queries'));
        
        // Validation des données
        add_filter('wp_insert_post_data', array($this, 'validate_school_data'), 10, 2);
        
        // Notifications automatiques
        add_action('save_post_tmm_school', array($this, 'on_school_updated'), 10, 3);
        add_action('transition_post_status', array($this, 'on_school_status_change'), 10, 3);
    }
    
    public function init() {
        // Enregistrer les statuts personnalisés pour les écoles
        $this->register_school_statuses();
        
        // Ajouter les capacités personnalisées
        $this->add_school_capabilities();
    }
    
    /**
     * Enregistrer les statuts personnalisés
     */
    private function register_school_statuses() {
        register_post_status('active_partner', array(
            'label' => 'Partenaire Actif',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Partenaire Actif (%s)', 'Partenaires Actifs (%s)')
        ));
        
        register_post_status('inactive_partner', array(
            'label' => 'Partenaire Inactif',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Partenaire Inactif (%s)', 'Partenaires Inactifs (%s)')
        ));
        
        register_post_status('prospect', array(
            'label' => 'Prospect',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Prospect (%s)', 'Prospects (%s)')
        ));
    }
    
    /**
     * Ajouter les capacités pour les écoles
     */
    private function add_school_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_schools');
            $role->add_cap('edit_school_settings');
            $role->add_cap('view_school_analytics');
        }
        
        $manager_role = get_role('tmm_pedagog_manager');
        if ($manager_role) {
            $manager_role->add_cap('manage_schools');
            $manager_role->add_cap('view_school_analytics');
        }
    }
    
    /**
     * Ajouter les métaboxes pour les écoles
     */
    public function add_school_meta_boxes() {
        add_meta_box(
            'tmm_school_info',
            'Informations de l\'École',
            array($this, 'school_info_meta_box'),
            'tmm_school',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tmm_school_contact',
            'Contacts & Responsables',
            array($this, 'school_contact_meta_box'),
            'tmm_school',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tmm_school_settings',
            'Paramètres de Collaboration',
            array($this, 'school_settings_meta_box'),
            'tmm_school',
            'side',
            'default'
        );
        
        add_meta_box(
            'tmm_school_stats',
            'Statistiques',
            array($this, 'school_stats_meta_box'),
            'tmm_school',
            'side',
            'default'
        );
        
        add_meta_box(
            'tmm_school_calendar',
            'Contraintes Calendaires',
            array($this, 'school_calendar_meta_box'),
            'tmm_school',
            'normal',
            'default'
        );
    }
    
    /**
     * Métabox informations école
     */
    public function school_info_meta_box($post) {
        wp_nonce_field('tmm_school_meta', 'tmm_school_meta_nonce');
        
        $school_type = get_post_meta($post->ID, 'school_type', true);
        $address = get_post_meta($post->ID, 'address', true);
        $website = get_post_meta($post->ID, 'website', true);
        $student_count = get_post_meta($post->ID, 'student_count', true);
        $foundation_year = get_post_meta($post->ID, 'foundation_year', true);
        $accreditations = get_post_meta($post->ID, 'accreditations', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="school_type">Type d'établissement</label></th>
                <td>
                    <select id="school_type" name="school_type" class="widefat">
                        <option value="">Sélectionner un type</option>
                        <option value="university" <?php selected($school_type, 'university'); ?>>Université</option>
                        <option value="engineering_school" <?php selected($school_type, 'engineering_school'); ?>>École d'Ingénieurs</option>
                        <option value="business_school" <?php selected($school_type, 'business_school'); ?>>École de Commerce</option>
                        <option value="technical_school" <?php selected($school_type, 'technical_school'); ?>>École Technique</option>
                        <option value="training_center" <?php selected($school_type, 'training_center'); ?>>Centre de Formation</option>
                        <option value="other" <?php selected($school_type, 'other'); ?>>Autre</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="address">Adresse complète</label></th>
                <td>
                    <textarea id="address" name="address" rows="3" class="widefat"><?php echo esc_textarea($address); ?></textarea>
                </td>
            </tr>
            
            <tr>
                <th><label for="website">Site web</label></th>
                <td>
                    <input type="url" id="website" name="website" value="<?php echo esc_url($website); ?>" class="widefat" />
                </td>
            </tr>
            
            <tr>
                <th><label for="student_count">Nombre d'étudiants</label></th>
                <td>
                    <input type="number" id="student_count" name="student_count" value="<?php echo esc_attr($student_count); ?>" min="0" class="small-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="foundation_year">Année de fondation</label></th>
                <td>
                    <input type="number" id="foundation_year" name="foundation_year" value="<?php echo esc_attr($foundation_year); ?>" min="1800" max="<?php echo date('Y'); ?>" class="small-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="accreditations">Accréditations</label></th>
                <td>
                    <textarea id="accreditations" name="accreditations" rows="2" class="widefat" placeholder="Ex: CTI, AACSB, EQUIS..."><?php echo esc_textarea($accreditations); ?></textarea>
                    <p class="description">Accréditations et certifications de l'établissement</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Métabox contacts
     */
    public function school_contact_meta_box($post) {
        $contacts = get_post_meta($post->ID, 'contacts', true) ?: array();
        $main_email = get_post_meta($post->ID, 'main_email', true);
        $main_phone = get_post_meta($post->ID, 'main_phone', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="main_email">Email principal</label></th>
                <td>
                    <input type="email" id="main_email" name="main_email" value="<?php echo esc_attr($main_email); ?>" class="widefat" />
                    <p class="description">Email principal pour les notifications automatiques</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="main_phone">Téléphone principal</label></th>
                <td>
                    <input type="tel" id="main_phone" name="main_phone" value="<?php echo esc_attr($main_phone); ?>" class="widefat" />
                </td>
            </tr>
        </table>
        
        <h4>Contacts Spécialisés</h4>
        <div id="school-contacts">
            <?php if (!empty($contacts)): ?>
                <?php foreach ($contacts as $index => $contact): ?>
                    <div class="contact-row" data-index="<?php echo $index; ?>">
                        <table class="form-table">
                            <tr>
                                <th style="width: 150px;">Nom</th>
                                <td><input type="text" name="contacts[<?php echo $index; ?>][name]" value="<?php echo esc_attr($contact['name']); ?>" class="widefat" /></td>
                            </tr>
                            <tr>
                                <th>Fonction</th>
                                <td>
                                    <select name="contacts[<?php echo $index; ?>][role]" class="widefat">
                                        <option value="director" <?php selected($contact['role'], 'director'); ?>>Directeur</option>
                                        <option value="pedagogical_manager" <?php selected($contact['role'], 'pedagogical_manager'); ?>>Responsable Pédagogique</option>
                                        <option value="admin_manager" <?php selected($contact['role'], 'admin_manager'); ?>>Responsable Administratif</option>
                                        <option value="coordinator" <?php selected($contact['role'], 'coordinator'); ?>>Coordinateur</option>
                                        <option value="assistant" <?php selected($contact['role'], 'assistant'); ?>>Assistant</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><input type="email" name="contacts[<?php echo $index; ?>][email]" value="<?php echo esc_attr($contact['email']); ?>" class="widefat" /></td>
                            </tr>
                            <tr>
                                <th>Téléphone</th>
                                <td>
                                    <input type="tel" name="contacts[<?php echo $index; ?>][phone]" value="<?php echo esc_attr($contact['phone']); ?>" style="width: 70%;" />
                                    <button type="button" class="button remove-contact" style="margin-left: 10px;">Supprimer</button>
                                </td>
                            </tr>
                        </table>
                        <hr>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <button type="button" id="add-contact" class="button">Ajouter un contact</button>
        
        <script>
        jQuery(document).ready(function($) {
            var contactIndex = <?php echo count($contacts); ?>;
            
            $('#add-contact').click(function() {
                var contactHtml = `
                    <div class="contact-row" data-index="${contactIndex}">
                        <table class="form-table">
                            <tr>
                                <th style="width: 150px;">Nom</th>
                                <td><input type="text" name="contacts[${contactIndex}][name]" class="widefat" /></td>
                            </tr>
                            <tr>
                                <th>Fonction</th>
                                <td>
                                    <select name="contacts[${contactIndex}][role]" class="widefat">
                                        <option value="director">Directeur</option>
                                        <option value="pedagogical_manager" selected>Responsable Pédagogique</option>
                                        <option value="admin_manager">Responsable Administratif</option>
                                        <option value="coordinator">Coordinateur</option>
                                        <option value="assistant">Assistant</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><input type="email" name="contacts[${contactIndex}][email]" class="widefat" /></td>
                            </tr>
                            <tr>
                                <th>Téléphone</th>
                                <td>
                                    <input type="tel" name="contacts[${contactIndex}][phone]" style="width: 70%;" />
                                    <button type="button" class="button remove-contact" style="margin-left: 10px;">Supprimer</button>
                                </td>
                            </tr>
                        </table>
                        <hr>
                    </div>
                `;
                $('#school-contacts').append(contactHtml);
                contactIndex++;
            });
            
            $(document).on('click', '.remove-contact', function() {
                $(this).closest('.contact-row').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Métabox paramètres de collaboration
     */
    public function school_settings_meta_box($post) {
        $working_hours_start = get_post_meta($post->ID, 'working_hours_start', true) ?: '08:00';
        $working_hours_end = get_post_meta($post->ID, 'working_hours_end', true) ?: '18:00';
        $working_days = get_post_meta($post->ID, 'working_days', true) ?: array(1,2,3,4,5);
        $advance_notice = get_post_meta($post->ID, 'advance_notice', true) ?: 7;
        $max_hours_per_day = get_post_meta($post->ID, 'max_hours_per_day', true) ?: 8;
        $preferred_session_duration = get_post_meta($post->ID, 'preferred_session_duration', true) ?: 7;
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="working_hours_start">Heure début</label></th>
                <td>
                    <input type="time" id="working_hours_start" name="working_hours_start" value="<?php echo esc_attr($working_hours_start); ?>" />
                </td>
            </tr>
            
            <tr>
                <th><label for="working_hours_end">Heure fin</label></th>
                <td>
                    <input type="time" id="working_hours_end" name="working_hours_end" value="<?php echo esc_attr($working_hours_end); ?>" />
                </td>
            </tr>
            
            <tr>
                <th>Jours de travail</th>
                <td>
                    <?php
                    $days = array(
                        1 => 'Lundi',
                        2 => 'Mardi', 
                        3 => 'Mercredi',
                        4 => 'Jeudi',
                        5 => 'Vendredi',
                        6 => 'Samedi',
                        0 => 'Dimanche'
                    );
                    
                    foreach ($days as $day_num => $day_name) {
                        $checked = in_array($day_num, $working_days) ? 'checked' : '';
                        echo '<label><input type="checkbox" name="working_days[]" value="' . $day_num . '" ' . $checked . '> ' . $day_name . '</label><br>';
                    }
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label for="advance_notice">Préavis (jours)</label></th>
                <td>
                    <input type="number" id="advance_notice" name="advance_notice" value="<?php echo esc_attr($advance_notice); ?>" min="1" max="90" class="small-text" />
                    <p class="description">Nombre de jours de préavis requis</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="max_hours_per_day">Max heures/jour</label></th>
                <td>
                    <input type="number" id="max_hours_per_day" name="max_hours_per_day" value="<?php echo esc_attr($max_hours_per_day); ?>" min="1" max="12" step="0.5" class="small-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="preferred_session_duration">Durée session préférée</label></th>
                <td>
                    <select id="preferred_session_duration" name="preferred_session_duration">
                        <option value="3.5" <?php selected($preferred_session_duration, '3.5'); ?>>Demi-journée (3.5h)</option>
                        <option value="7" <?php selected($preferred_session_duration, '7'); ?>>Journée (7h)</option>
                        <option value="14" <?php selected($preferred_session_duration, '14'); ?>>2 jours (14h)</option>
                        <option value="21" <?php selected($preferred_session_duration, '21'); ?>>3 jours (21h)</option>
                        <option value="35" <?php selected($preferred_session_duration, '35'); ?>>5 jours (35h)</option>
                    </select>
                </td>
            </tr>
        </table>
        
        <div class="school-preferences">
            <h4>Préférences</h4>
            <?php
            $email_notifications = get_post_meta($post->ID, 'email_notifications', true) !== 'disabled';
            $auto_confirm = get_post_meta($post->ID, 'auto_confirm', true) === 'enabled';
            $allow_weekend = get_post_meta($post->ID, 'allow_weekend', true) === 'enabled';
            ?>
            
            <label>
                <input type="checkbox" name="email_notifications" value="enabled" <?php checked($email_notifications); ?> />
                Recevoir les notifications par email
            </label><br>
            
            <label>
                <input type="checkbox" name="auto_confirm" value="enabled" <?php checked($auto_confirm); ?> />
                Confirmation automatique des sessions compatibles
            </label><br>
            
            <label>
                <input type="checkbox" name="allow_weekend" value="enabled" <?php checked($allow_weekend); ?> />
                Autoriser les sessions en week-end
            </label>
        </div>
        <?php
    }
    
    /**
     * Métabox statistiques école
     */
    public function school_stats_meta_box($post) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        // Statistiques de base
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.school_id = %d",
            $post->ID
        ));
        
        $completed_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.school_id = %d AND p.post_status = 'completed'",
            $post->ID
        ));
        
        $total_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(hours_realized) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.school_id = %d AND p.post_status = 'completed'",
            $post->ID
        ));
        
        $avg_satisfaction = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(pm.meta_value AS DECIMAL(3,2))) FROM {$wpdb->postmeta} pm
             JOIN $sessions_table s ON pm.post_id = s.session_id
             WHERE s.school_id = %d AND pm.meta_key = 'school_satisfaction_rating'",
            $post->ID
        ));
        
        $partnership_start = get_post_meta($post->ID, 'partnership_start_date', true);
        if ($partnership_start) {
            $partnership_duration = ceil((time() - strtotime($partnership_start)) / (365.25 * 24 * 3600));
        } else {
            $partnership_duration = 0;
        }
        
        ?>
        <div class="school-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_sessions ?: 0; ?></span>
                <span class="stat-label">Sessions Total</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number"><?php echo $completed_sessions ?: 0; ?></span>
                <span class="stat-label">Sessions Terminées</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number"><?php echo round($total_hours ?: 0); ?>h</span>
                <span class="stat-label">Heures Réalisées</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number">
                    <?php if ($avg_satisfaction): ?>
                        <?php echo round($avg_satisfaction, 1); ?>/5 ⭐
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
                <span class="stat-label">Satisfaction Moyenne</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number"><?php echo $partnership_duration; ?> an<?php echo $partnership_duration > 1 ? 's' : ''; ?></span>
                <span class="stat-label">Partenariat</span>
            </div>
        </div>
        
        <div class="school-actions">
            <button type="button" class="button button-primary" onclick="viewSchoolDetails(<?php echo $post->ID; ?>)">
                📊 Voir Analytics Détaillés
            </button>
            
            <button type="button" class="button" onclick="generateSchoolReport(<?php echo $post->ID; ?>)">
                📋 Générer Rapport
            </button>
            
            <button type="button" class="button" onclick="createQuickSession(<?php echo $post->ID; ?>)">
                ➕ Nouvelle Session
            </button>
        </div>
        
        <style>
        .school-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .stat-number {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #2271b1;
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .school-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .school-actions .button {
            justify-self: stretch;
        }
        </style>
        
        <script>
        function viewSchoolDetails(schoolId) {
            window.open(adminURL + 'admin.php?page=tmm-school-analytics&school_id=' + schoolId, '_blank');
        }
        
        function generateSchoolReport(schoolId) {
            if (confirm('Générer un rapport pour cette école?')) {
                window.location.href = adminURL + 'admin-ajax.php?action=tmm_generate_school_report&school_id=' + schoolId + '&nonce=' + tmmNonce;
            }
        }
        
        function createQuickSession(schoolId) {
            // Logique pour créer une session rapide
            jQuery('#school-select').val(schoolId);
            jQuery('#quick-session-modal').show();
        }
        </script>
        <?php
    }
    
    /**
     * Métabox contraintes calendaires
     */
    public function school_calendar_meta_box($post) {
        $vacation_periods = get_post_meta($post->ID, 'vacation_periods', true) ?: array();
        $exam_periods = get_post_meta($post->ID, 'exam_periods', true) ?: array();
        $blocked_dates = get_post_meta($post->ID, 'blocked_dates', true) ?: array();
        
        ?>
        <div class="calendar-constraints">
            <h4>Périodes de Vacances</h4>
            <div id="vacation-periods">
                <?php foreach ($vacation_periods as $index => $period): ?>
                    <div class="period-row">
                        <input type="date" name="vacation_periods[<?php echo $index; ?>][start]" value="<?php echo esc_attr($period['start']); ?>" />
                        <input type="date" name="vacation_periods[<?php echo $index; ?>][end]" value="<?php echo esc_attr($period['end']); ?>" />
                        <input type="text" name="vacation_periods[<?php echo $index; ?>][name]" value="<?php echo esc_attr($period['name']); ?>" placeholder="Nom de la période" />
                        <button type="button" class="button remove-period">Supprimer</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-vacation" class="button">Ajouter Période de Vacances</button>
            
            <h4>Périodes d'Examens</h4>
            <div id="exam-periods">
                <?php foreach ($exam_periods as $index => $period): ?>
                    <div class="period-row">
                        <input type="date" name="exam_periods[<?php echo $index; ?>][start]" value="<?php echo esc_attr($period['start']); ?>" />
                        <input type="date" name="exam_periods[<?php echo $index; ?>][end]" value="<?php echo esc_attr($period['end']); ?>" />
                        <input type="text" name="exam_periods[<?php echo $index; ?>][name]" value="<?php echo esc_attr($period['name']); ?>" placeholder="Type d'examen" />
                        <button type="button" class="button remove-period">Supprimer</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-exam" class="button">Ajouter Période d'Examens</button>
            
            <h4>Dates Bloquées Ponctuelles</h4>
            <div id="blocked-dates">
                <?php foreach ($blocked_dates as $index => $date): ?>
                    <div class="date-row">
                        <input type="date" name="blocked_dates[<?php echo $index; ?>][date]" value="<?php echo esc_attr($date['date']); ?>" />
                        <input type="text" name="blocked_dates[<?php echo $index; ?>][reason]" value="<?php echo esc_attr($date['reason']); ?>" placeholder="Raison" />
                        <button type="button" class="button remove-date">Supprimer</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-blocked-date" class="button">Ajouter Date Bloquée</button>
        </div>
        
        <style>
        .period-row, .date-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .period-row input, .date-row input {
            flex: 1;
        }
        
        .remove-period, .remove-date {
            flex-shrink: 0;
        }
        
        .calendar-constraints h4 {
            margin: 20px 0 10px 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .calendar-constraints h4:first-child {
            margin-top: 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var vacationIndex = <?php echo count($vacation_periods); ?>;
            var examIndex = <?php echo count($exam_periods); ?>;
            var blockedIndex = <?php echo count($blocked_dates); ?>;
            
            $('#add-vacation').click(function() {
                $('#vacation-periods').append(`
                    <div class="period-row">
                        <input type="date" name="vacation_periods[${vacationIndex}][start]" />
                        <input type="date" name="vacation_periods[${vacationIndex}][end]" />
                        <input type="text" name="vacation_periods[${vacationIndex}][name]" placeholder="Nom de la période" />
                        <button type="button" class="button remove-period">Supprimer</button>
                    </div>
                `);
                vacationIndex++;
            });
            
            $('#add-exam').click(function() {
                $('#exam-periods').append(`
                    <div class="period-row">
                        <input type="date" name="exam_periods[${examIndex}][start]" />
                        <input type="date" name="exam_periods[${examIndex}][end]" />
                        <input type="text" name="exam_periods[${examIndex}][name]" placeholder="Type d'examen" />
                        <button type="button" class="button remove-period">Supprimer</button>
                    </div>
                `);
                examIndex++;
            });
            
            $('#add-blocked-date').click(function() {
                $('#blocked-dates').append(`
                    <div class="date-row">
                        <input type="date" name="blocked_dates[${blockedIndex}][date]" />
                        <input type="text" name="blocked_dates[${blockedIndex}][reason]" placeholder="Raison" />
                        <button type="button" class="button remove-date">Supprimer</button>
                    </div>
                `);
                blockedIndex++;
            });
            
            $(document).on('click', '.remove-period, .remove-date', function() {
                $(this).closest('.period-row, .date-row').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Sauvegarder les métadonnées des écoles
     */
    public function save_school_meta($post_id, $post) {
        if (!isset($_POST['tmm_school_meta_nonce']) || 
            !wp_verify_nonce($_POST['tmm_school_meta_nonce'], 'tmm_school_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if ($post->post_type !== 'tmm_school') {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Sauvegarder les champs simples
        $simple_fields = array(
            'school_type', 'address', 'website', 'student_count', 'foundation_year', 
            'accreditations', 'main_email', 'main_phone', 'working_hours_start', 
            'working_hours_end', 'advance_notice', 'max_hours_per_day', 
            'preferred_session_duration'
        );
        
        foreach ($simple_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Sauvegarder les jours de travail
        if (isset($_POST['working_days'])) {
            $working_days = array_map('intval', $_POST['working_days']);
            update_post_meta($post_id, 'working_days', $working_days);
        } else {
            update_post_meta($post_id, 'working_days', array());
        }
        
        // Sauvegarder les préférences
        $preferences = array('email_notifications', 'auto_confirm', 'allow_weekend');
        foreach ($preferences as $pref) {
            $value = isset($_POST[$pref]) ? 'enabled' : 'disabled';
            update_post_meta($post_id, $pref, $value);
        }
        
        // Sauvegarder les contacts
        if (isset($_POST['contacts'])) {
            $contacts = array();
            foreach ($_POST['contacts'] as $contact) {
                if (!empty($contact['name']) && !empty($contact['email'])) {
                    $contacts[] = array(
                        'name' => sanitize_text_field($contact['name']),
                        'role' => sanitize_text_field($contact['role']),
                        'email' => sanitize_email($contact['email']),
                        'phone' => sanitize_text_field($contact['phone'])
                    );
                }
            }
            update_post_meta($post_id, 'contacts', $contacts);
        }
        
        // Sauvegarder les périodes de vacances
        if (isset($_POST['vacation_periods'])) {
            $vacation_periods = array();
            foreach ($_POST['vacation_periods'] as $period) {
                if (!empty($period['start']) && !empty($period['end'])) {
                    $vacation_periods[] = array(
                        'start' => sanitize_text_field($period['start']),
                        'end' => sanitize_text_field($period['end']),
                        'name' => sanitize_text_field($period['name'])
                    );
                }
            }
            update_post_meta($post_id, 'vacation_periods', $vacation_periods);
        }
        
        // Sauvegarder les périodes d'examens
        if (isset($_POST['exam_periods'])) {
            $exam_periods = array();
            foreach ($_POST['exam_periods'] as $period) {
                if (!empty($period['start']) && !empty($period['end'])) {
                    $exam_periods[] = array(
                        'start' => sanitize_text_field($period['start']),
                        'end' => sanitize_text_field($period['end']),
                        'name' => sanitize_text_field($period['name'])
                    );
                }
            }
            update_post_meta($post_id, 'exam_periods', $exam_periods);
        }
        
        // Sauvegarder les dates bloquées
        if (isset($_POST['blocked_dates'])) {
            $blocked_dates = array();
            foreach ($_POST['blocked_dates'] as $date) {
                if (!empty($date['date'])) {
                    $blocked_dates[] = array(
                        'date' => sanitize_text_field($date['date']),
                        'reason' => sanitize_text_field($date['reason'])
                    );
                }
            }
            update_post_meta($post_id, 'blocked_dates', $blocked_dates);
        }
        
        // Déclencher hook pour actions personnalisées
        do_action('tmm_school_meta_saved', $post_id, $_POST);
    }
    
    /**
     * Colonnes personnalisées pour les écoles
     */
    public function school_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['school_type'] = 'Type';
        $new_columns['students'] = 'Étudiants';
        $new_columns['sessions'] = 'Sessions';
        $new_columns['status'] = 'Statut';
        $new_columns['contact'] = 'Contact Principal';
        $new_columns['last_session'] = 'Dernière Session';
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    public function school_column_content($column, $post_id) {
        switch ($column) {
            case 'school_type':
                $type = get_post_meta($post_id, 'school_type', true);
                $types = array(
                    'university' => 'Université',
                    'engineering_school' => 'École d\'Ingénieurs',
                    'business_school' => 'École de Commerce',
                    'technical_school' => 'École Technique',
                    'training_center' => 'Centre de Formation'
                );
                echo $types[$type] ?? ucfirst($type);
                break;
                
            case 'students':
                $count = get_post_meta($post_id, 'student_count', true);
                echo $count ? number_format($count) : '-';
                break;
                
            case 'sessions':
                global $wpdb;
                $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $sessions_table WHERE school_id = %d", $post_id
                ));
                echo $count ?: '0';
                break;
                
            case 'status':
                $status = get_post_status($post_id);
                $status_labels = array(
                    'publish' => '<span style="color: #46b450;">Actif</span>',
                    'active_partner' => '<span style="color: #00a32a;">Partenaire Actif</span>',
                    'inactive_partner' => '<span style="color: #d63638;">Partenaire Inactif</span>',
                    'prospect' => '<span style="color: #dba617;">Prospect</span>'
                );
                echo $status_labels[$status] ?? $status;
                break;
                
            case 'contact':
                $email = get_post_meta($post_id, 'main_email', true);
                $phone = get_post_meta($post_id, 'main_phone', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                    if ($phone) echo '<br><small>' . esc_html($phone) . '</small>';
                } else {
                    echo '-';
                }
                break;
                
            case 'last_session':
                global $wpdb;
                $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
                $last_session = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(start_datetime) FROM $sessions_table WHERE school_id = %d", $post_id
                ));
                if ($last_session) {
                    echo human_time_diff(strtotime($last_session), current_time('timestamp')) . ' ago';
                } else {
                    echo 'Jamais';
                }
                break;
        }
    }
    
    /**
     * AJAX: Rechercher des écoles
     */
    public function search_schools() {
        check_ajax_referer('tmm_admin_nonce', 'nonce');
        
        $term = sanitize_text_field($_POST['term']);
        
        $schools = get_posts(array(
            'post_type' => 'tmm_school',
            'post_status' => array('publish', 'active_partner'),
            's' => $term,
            'numberposts' => 10
        ));
        
        $results = array();
        foreach ($schools as $school) {
            $results[] = array(
                'id' => $school->ID,
                'label' => $school->post_title,
                'value' => $school->post_title,
                'type' => get_post_meta($school->ID, 'school_type', true),
                'email' => get_post_meta($school->ID, 'main_email', true)
            );
        }
        
        wp_send_json($results);
    }
    
    /**
     * AJAX: Obtenir les infos d'une école
     */
    public function get_school_info() {
        check_ajax_referer('tmm_admin_nonce', 'nonce');
        
        $school_id = intval($_POST['school_id']);
        
        if (!$school_id) {
            wp_send_json_error('ID école manquant');
        }
        
        $school = get_post($school_id);
        if (!$school) {
            wp_send_json_error('École non trouvée');
        }
        
        $info = array(
            'id' => $school->ID,
            'title' => $school->post_title,
            'type' => get_post_meta($school_id, 'school_type', true),
            'email' => get_post_meta($school_id, 'main_email', true),
            'phone' => get_post_meta($school_id, 'main_phone', true),
            'address' => get_post_meta($school_id, 'address', true),
            'website' => get_post_meta($school_id, 'website', true),
            'working_hours' => array(
                'start' => get_post_meta($school_id, 'working_hours_start', true),
                'end' => get_post_meta($school_id, 'working_hours_end', true)
            ),
            'working_days' => get_post_meta($school_id, 'working_days', true),
            'contacts' => get_post_meta($school_id, 'contacts', true)
        );
        
        wp_send_json_success($info);
    }
    
    /**
     * Hook: École mise à jour
     */
    public function on_school_updated($post_id, $post, $update) {
        if (!$update) return; // Seulement pour les mises à jour
        
        // Notifier les utilisateurs liés à cette école
        $school_users = get_users(array(
            'role' => 'partner_school',
            'meta_key' => 'school_id',
            'meta_value' => $post_id
        ));
        
        foreach ($school_users as $user) {
            // Créer notification de mise à jour
            do_action('tmm_school_settings_updated', $post_id, $user->ID);
        }
    }
    
    /**
     * Hook: Changement de statut école
     */
    public function on_school_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'tmm_school') return;
        
        // Logique selon le changement de statut
        if ($new_status === 'active_partner' && $old_status !== 'active_partner') {
            // École devient partenaire actif
            do_action('tmm_school_activated', $post->ID);
        } elseif ($old_status === 'active_partner' && $new_status !== 'active_partner') {
            // École n'est plus partenaire actif
            do_action('tmm_school_deactivated', $post->ID);
        }
    }
}

// Initialiser la classe
new TMM_Schools();