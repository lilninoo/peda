<?php
/**
 * Classe pour l'interface d'administration TeachMeMore PédagoConnect
 * Gestion complète des fonctionnalités admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMM_Admin {
    
    private $plugin_name = 'tmm-pedagoconnect';
    private $version = '1.0.0';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Actions AJAX pour l'admin
        add_action('wp_ajax_tmm_get_dashboard_data', array($this, 'get_dashboard_data'));
        add_action('wp_ajax_tmm_create_quick_session', array($this, 'create_quick_session'));
        add_action('wp_ajax_tmm_get_recent_activity', array($this, 'get_recent_activity'));
        add_action('wp_ajax_tmm_send_reminder', array($this, 'send_reminder'));
        add_action('wp_ajax_tmm_suggest_trainer', array($this, 'suggest_trainer'));
        add_action('wp_ajax_tmm_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_tmm_export_data', array($this, 'export_data'));
        add_action('wp_ajax_tmm_import_data', array($this, 'import_data'));
        add_action('wp_ajax_tmm_system_health', array($this, 'system_health_check'));
        
        // Hooks pour les métaboxes personnalisées
        add_action('add_meta_boxes', array($this, 'add_custom_meta_boxes'));
        add_action('save_post', array($this, 'save_custom_meta_boxes'));
        
        // Colonnes personnalisées dans les listes
        add_filter('manage_tmm_session_posts_columns', array($this, 'session_columns'));
        add_action('manage_tmm_session_posts_custom_column', array($this, 'session_column_content'), 10, 2);
        add_filter('manage_tmm_school_posts_columns', array($this, 'school_columns'));
        add_action('manage_tmm_school_posts_custom_column', array($this, 'school_column_content'), 10, 2);
        
        // Filtres de recherche
        add_action('restrict_manage_posts', array($this, 'add_admin_filters'));
        add_filter('parse_query', array($this, 'filter_admin_queries'));
        
        // Actions en masse
        add_filter('bulk_actions-edit-tmm_session', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-tmm_session', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Notifications admin
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Ajouter les menus d'administration
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            'TeachMeMore PédagoConnect',
            'PédagoConnect',
            'manage_options',
            $this->plugin_name,
            array($this, 'main_dashboard_page'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/></svg>'),
            30
        );
        
        // Sous-menus
        add_submenu_page(
            $this->plugin_name,
            'Dashboard',
            'Dashboard',
            'manage_options',
            $this->plugin_name,
            array($this, 'main_dashboard_page')
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Planning Global',
            'Planning Global',
            'view_tmm_reports',
            'tmm-global-planning',
            array($this, 'global_planning_page')
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Disponibilités',
            'Disponibilités',
            'manage_trainers',
            'tmm-availabilities',
            array($this, 'availabilities_page')
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Reporting',
            'Reporting',
            'view_tmm_reports',
            'tmm-reporting',
            array($this, 'reporting_page')
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Écoles Partenaires',
            'Écoles',
            'manage_schools',
            'edit.php?post_type=tmm_school'
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Modules',
            'Modules',
            'edit_posts',
            'edit.php?post_type=tmm_module'
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Sessions',
            'Sessions',
            'edit_posts',
            'edit.php?post_type=tmm_session'
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Paramètres',
            'Paramètres',
            'manage_options',
            'tmm-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            $this->plugin_name,
            'Outils',
            'Outils',
            'manage_options',
            'tmm-tools',
            array($this, 'tools_page')
        );
    }
    
    /**
     * Initialisation admin
     */
    public function admin_init() {
        register_setting('tmm_settings', 'tmm_general_settings');
        register_setting('tmm_settings', 'tmm_email_settings');
        register_setting('tmm_settings', 'tmm_notification_settings');
        register_setting('tmm_settings', 'tmm_planning_settings');
        register_setting('tmm_settings', 'tmm_integration_settings');
        
        // Sections de paramètres
        add_settings_section(
            'tmm_general_section',
            'Paramètres Généraux',
            array($this, 'general_section_callback'),
            'tmm_settings'
        );
        
        add_settings_section(
            'tmm_email_section',
            'Configuration Email',
            array($this, 'email_section_callback'),
            'tmm_settings'
        );
        
        add_settings_section(
            'tmm_notification_section',
            'Notifications',
            array($this, 'notification_section_callback'),
            'tmm_settings'
        );
        
        // Champs de paramètres
        $this->add_settings_fields();
    }
    
    /**
     * Enqueue scripts et styles admin
     */
    public function enqueue_admin_scripts($hook) {
        // Scripts globaux pour toutes les pages TMM
        if (strpos($hook, 'tmm-') !== false || strpos($hook, 'tmm_') !== false) {
            wp_enqueue_script('tmm-admin-js', TMM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-util'), $this->version, true);
            wp_enqueue_style('tmm-admin-css', TMM_PLUGIN_URL . 'assets/css/admin.css', array(), $this->version);
            
            // Chart.js pour les graphiques
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
            
            // FullCalendar pour le planning
            wp_enqueue_script('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.js', array('jquery'), '5.11.3', true);
            wp_enqueue_style('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.css', array(), '5.11.3');
            
            // Localisation
            wp_localize_script('tmm-admin-js', 'tmm_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tmm_admin_nonce'),
                'plugin_url' => TMM_PLUGIN_URL,
                'current_user_id' => get_current_user_id(),
                'strings' => array(
                    'confirm_delete' => 'Êtes-vous sûr de vouloir supprimer cet élément?',
                    'success' => 'Opération réussie',
                    'error' => 'Une erreur est survenue',
                    'loading' => 'Chargement...',
                    'no_data' => 'Aucune donnée disponible'
                )
            ));
        }
        
        // Scripts spécifiques par page
        switch ($hook) {
            case 'toplevel_page_' . $this->plugin_name:
                wp_enqueue_script('tmm-dashboard', TMM_PLUGIN_URL . 'assets/js/dashboard.js', array('tmm-admin-js'), $this->version, true);
                break;
            case 'pedagoconnect_page_tmm-global-planning':
                wp_enqueue_script('tmm-planning', TMM_PLUGIN_URL . 'assets/js/planning.js', array('tmm-admin-js', 'fullcalendar'), $this->version, true);
                break;
            case 'pedagoconnect_page_tmm-reporting':
                wp_enqueue_script('tmm-reporting', TMM_PLUGIN_URL . 'assets/js/reporting.js', array('tmm-admin-js', 'chart-js'), $this->version, true);
                break;
        }
    }
    
    /**
     * Page dashboard principal
     */
    public function main_dashboard_page() {
        include TMM_PLUGIN_PATH . 'templates/admin-dashboard.php';
    }
    
    /**
     * Page planning global
     */
    public function global_planning_page() {
        ?>
        <div class="wrap tmm-planning-page">
            <h1>📅 Planning Global TeachMeMore</h1>
            
            <div class="tmm-planning-controls">
                <div class="controls-row">
                    <div class="filter-group">
                        <label for="school-filter">École:</label>
                        <select id="school-filter">
                            <option value="">Toutes les écoles</option>
                            <?php
                            $schools = get_posts(array('post_type' => 'tmm_school', 'numberposts' => -1));
                            foreach ($schools as $school) {
                                echo '<option value="' . $school->ID . '">' . esc_html($school->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="trainer-filter">Formateur:</label>
                        <select id="trainer-filter">
                            <option value="">Tous les formateurs</option>
                            <?php
                            $trainers = get_users(array('role' => 'tmm_trainer'));
                            foreach ($trainers as $trainer) {
                                echo '<option value="' . $trainer->ID . '">' . esc_html($trainer->display_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status-filter">Statut:</label>
                        <select id="status-filter">
                            <option value="">Tous les statuts</option>
                            <option value="proposed">Proposées</option>
                            <option value="confirmed">Confirmées</option>
                            <option value="in_progress">En cours</option>
                            <option value="completed">Terminées</option>
                            <option value="cancelled">Annulées</option>
                        </select>
                    </div>
                    
                    <div class="action-group">
                        <button id="export-planning" class="button">📤 Exporter</button>
                        <button id="print-planning" class="button">🖨️ Imprimer</button>
                        <button id="refresh-planning" class="button button-primary">🔄 Actualiser</button>
                    </div>
                </div>
            </div>
            
            <div id="tmm-global-calendar" class="tmm-calendar-container">
                <!-- Calendrier chargé via JavaScript -->
            </div>
            
            <div class="tmm-planning-legend">
                <h3>Légende</h3>
                <div class="legend-items">
                    <span class="legend-item"><span class="color-box proposed"></span> Proposées</span>
                    <span class="legend-item"><span class="color-box confirmed"></span> Confirmées</span>
                    <span class="legend-item"><span class="color-box in_progress"></span> En cours</span>
                    <span class="legend-item"><span class="color-box completed"></span> Terminées</span>
                    <span class="legend-item"><span class="color-box cancelled"></span> Annulées</span>
                </div>
            </div>
        </div>
        
        <style>
        .tmm-planning-controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .controls-row {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #555;
        }
        
        .action-group {
            display: flex;
            gap: 10px;
        }
        
        .tmm-calendar-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-height: 600px;
        }
        
        .tmm-planning-legend {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .legend-items {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .color-box {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        
        .color-box.proposed { background: #ffa726; }
        .color-box.confirmed { background: #66bb6a; }
        .color-box.in_progress { background: #42a5f5; }
        .color-box.completed { background: #26c6da; }
        .color-box.cancelled { background: #ef5350; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            initGlobalPlanning();
            
            function initGlobalPlanning() {
                $('#tmm-global-calendar').fullCalendar({
                    locale: 'fr',
                    height: 'auto',
                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay,listWeek'
                    },
                    editable: true,
                    droppable: true,
                    events: function(start, end, timezone, callback) {
                        $.post(ajaxurl, {
                            action: 'tmm_get_planning_events',
                            start: start.format(),
                            end: end.format(),
                            school_id: $('#school-filter').val(),
                            trainer_id: $('#trainer-filter').val(),
                            status: $('#status-filter').val(),
                            nonce: tmm_admin.nonce
                        }, function(response) {
                            if (response.success) {
                                callback(response.data);
                            }
                        });
                    },
                    eventClick: function(event) {
                        openSessionModal(event.id);
                    },
                    eventDrop: function(event, delta, revertFunc) {
                        updateSessionTime(event.id, event.start, event.end, revertFunc);
                    },
                    eventResize: function(event, delta, revertFunc) {
                        updateSessionTime(event.id, event.start, event.end, revertFunc);
                    }
                });
            }
            
            // Filtres
            $('#school-filter, #trainer-filter, #status-filter').change(function() {
                $('#tmm-global-calendar').fullCalendar('refetchEvents');
            });
            
            // Actions
            $('#refresh-planning').click(function() {
                $('#tmm-global-calendar').fullCalendar('refetchEvents');
            });
            
            $('#export-planning').click(function() {
                exportPlanning();
            });
            
            $('#print-planning').click(function() {
                window.print();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Page disponibilités
     */
    public function availabilities_page() {
        ?>
        <div class="wrap tmm-availabilities-page">
            <h1>📅 Gestion des Disponibilités</h1>
            
            <div class="tmm-availability-overview">
                <div class="overview-cards">
                    <div class="overview-card">
                        <h3>👨‍🏫 Formateurs Actifs</h3>
                        <div class="card-number"><?php echo count(get_users(array('role' => 'tmm_trainer'))); ?></div>
                    </div>
                    
                    <div class="overview-card">
                        <h3>📅 Créneaux Cette Semaine</h3>
                        <div class="card-number" id="weekly-slots">-</div>
                    </div>
                    
                    <div class="overview-card">
                        <h3>⚠️ Conflits Détectés</h3>
                        <div class="card-number conflicts" id="conflicts-count">-</div>
                    </div>
                    
                    <div class="overview-card">
                        <h3>📊 Taux d'Occupation</h3>
                        <div class="card-number" id="occupation-rate">-</div>
                    </div>
                </div>
            </div>
            
            <div class="tmm-availability-controls">
                <div class="controls-left">
                    <select id="trainer-select">
                        <option value="">Tous les formateurs</option>
                        <?php
                        $trainers = get_users(array('role' => 'tmm_trainer'));
                        foreach ($trainers as $trainer) {
                            echo '<option value="' . $trainer->ID . '">' . esc_html($trainer->display_name) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <select id="time-range">
                        <option value="week">Cette semaine</option>
                        <option value="month">Ce mois</option>
                        <option value="quarter">Ce trimestre</option>
                    </select>
                </div>
                
                <div class="controls-right">
                    <button id="detect-conflicts" class="button">🔍 Détecter Conflits</button>
                    <button id="optimize-planning" class="button">⚡ Optimiser</button>
                    <button id="export-availabilities" class="button">📤 Exporter</button>
                </div>
            </div>
            
            <div class="tmm-availability-grid">
                <div class="trainers-list">
                    <h3>Formateurs</h3>
                    <div id="trainers-list-content">
                        <?php foreach ($trainers as $trainer): ?>
                        <div class="trainer-item" data-trainer-id="<?php echo $trainer->ID; ?>">
                            <div class="trainer-avatar">
                                <?php echo get_avatar($trainer->ID, 32); ?>
                            </div>
                            <div class="trainer-info">
                                <div class="trainer-name"><?php echo esc_html($trainer->display_name); ?></div>
                                <div class="trainer-skills"><?php echo esc_html(get_user_meta($trainer->ID, 'trainer_skills', true)); ?></div>
                            </div>
                            <div class="trainer-status">
                                <span class="status-indicator" id="status-<?php echo $trainer->ID; ?>">🟢</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="availability-calendar">
                    <div id="availability-calendar-view"></div>
                </div>
            </div>
        </div>
        
        <style>
        .tmm-availability-overview {
            margin-bottom: 20px;
        }
        
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .overview-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .overview-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .card-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .card-number.conflicts {
            color: #f44336;
        }
        
        .tmm-availability-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .controls-left, .controls-right {
            display: flex;
            gap: 10px;
        }
        
        .tmm-availability-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            min-height: 600px;
        }
        
        .trainers-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .trainers-list h3 {
            background: #f8f9fa;
            margin: 0;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .trainer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .trainer-item:hover {
            background: #f8f9fa;
        }
        
        .trainer-item.active {
            background: #e3f2fd;
        }
        
        .trainer-info {
            flex: 1;
        }
        
        .trainer-name {
            font-weight: 500;
            color: #333;
        }
        
        .trainer-skills {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        
        .availability-calendar {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            initAvailabilitiesPage();
            
            function initAvailabilitiesPage() {
                loadAvailabilityStats();
                
                // Initialiser le calendrier des disponibilités
                $('#availability-calendar-view').fullCalendar({
                    locale: 'fr',
                    defaultView: 'agendaWeek',
                    height: 'auto',
                    minTime: '07:00:00',
                    maxTime: '20:00:00',
                    events: function(start, end, timezone, callback) {
                        var trainerId = $('#trainer-select').val();
                        loadAvailabilityEvents(start, end, trainerId, callback);
                    },
                    eventRender: function(event, element) {
                        // Personnaliser l'affichage selon le type
                        if (event.type === 'unavailable') {
                            element.addClass('unavailable-slot');
                        } else if (event.type === 'session') {
                            element.addClass('session-slot');
                        }
                    }
                });
                
                // Événements
                $('#trainer-select, #time-range').change(function() {
                    $('#availability-calendar-view').fullCalendar('refetchEvents');
                    loadAvailabilityStats();
                });
                
                $('.trainer-item').click(function() {
                    var trainerId = $(this).data('trainer-id');
                    $('.trainer-item').removeClass('active');
                    $(this).addClass('active');
                    $('#trainer-select').val(trainerId);
                    $('#availability-calendar-view').fullCalendar('refetchEvents');
                });
                
                $('#detect-conflicts').click(detectConflicts);
                $('#optimize-planning').click(optimizePlanning);
                $('#export-availabilities').click(exportAvailabilities);
            }
            
            function loadAvailabilityStats() {
                $.post(ajaxurl, {
                    action: 'tmm_get_availability_stats',
                    trainer_id: $('#trainer-select').val(),
                    time_range: $('#time-range').val(),
                    nonce: tmm_admin.nonce
                }, function(response) {
                    if (response.success) {
                        $('#weekly-slots').text(response.data.weekly_slots);
                        $('#conflicts-count').text(response.data.conflicts);
                        $('#occupation-rate').text(response.data.occupation_rate + '%');
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Page de reporting
     */
    public function reporting_page() {
        include TMM_PLUGIN_PATH . 'templates/reporting.php';
    }
    
    /**
     * Page de paramètres
     */
    public function settings_page() {
        ?>
        <div class="wrap tmm-settings-page">
            <h1>⚙️ Paramètres TeachMeMore PédagoConnect</h1>
            
            <div class="tmm-settings-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active">Général</a>
                    <a href="#email" class="nav-tab">Email</a>
                    <a href="#notifications" class="nav-tab">Notifications</a>
                    <a href="#planning" class="nav-tab">Planning</a>
                    <a href="#integrations" class="nav-tab">Intégrations</a>
                    <a href="#advanced" class="nav-tab">Avancé</a>
                </nav>
                
                <form method="post" action="options.php">
                    <?php settings_fields('tmm_settings'); ?>
                    
                    <!-- Onglet Général -->
                    <div id="general" class="tmm-tab-content active">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Tarifs Horaires</th>
                                <td>
                                    <?php $rates = get_option('tmm_hourly_rates', array()); ?>
                                    <fieldset>
                                        <label>
                                            Standard: <input type="number" name="tmm_hourly_rates[standard]" value="<?php echo esc_attr($rates['standard'] ?? 150); ?>" /> €/heure
                                        </label><br>
                                        <label>
                                            Expert: <input type="number" name="tmm_hourly_rates[expert]" value="<?php echo esc_attr($rates['expert'] ?? 200); ?>" /> €/heure
                                        </label><br>
                                        <label>
                                            Premium: <input type="number" name="tmm_hourly_rates[premium]" value="<?php echo esc_attr($rates['premium'] ?? 250); ?>" /> €/heure
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Devise</th>
                                <td>
                                    <select name="tmm_general_settings[currency]">
                                        <option value="EUR">Euro (€)</option>
                                        <option value="USD">Dollar ($)</option>
                                        <option value="GBP">Livre (£)</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Fuseau Horaire</th>
                                <td>
                                    <select name="tmm_general_settings[timezone]">
                                        <option value="Europe/Paris">Europe/Paris</option>
                                        <option value="Europe/London">Europe/London</option>
                                        <option value="America/New_York">America/New_York</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Onglet Email -->
                    <div id="email" class="tmm-tab-content">
                        <table class="form-table">
                            <?php $email_settings = get_option('tmm_email_settings', array()); ?>
                            <tr>
                                <th scope="row">Nom de l'expéditeur</th>
                                <td>
                                    <input type="text" name="tmm_email_settings[sender_name]" value="<?php echo esc_attr($email_settings['sender_name'] ?? 'TeachMeMore'); ?>" class="regular-text" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Email de l'expéditeur</th>
                                <td>
                                    <input type="email" name="tmm_email_settings[sender_email]" value="<?php echo esc_attr($email_settings['sender_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Activer SMTP</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="tmm_email_settings[smtp_enabled]" value="1" <?php checked($email_settings['smtp_enabled'] ?? false); ?> />
                                        Utiliser SMTP pour l'envoi d'emails
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Onglet Notifications -->
                    <div id="notifications" class="tmm-tab-content">
                        <table class="form-table">
                            <?php $notif_settings = get_option('tmm_notification_settings', array()); ?>
                            <tr>
                                <th scope="row">Notifications Email</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="tmm_notification_settings[session_proposed]" value="1" <?php checked($notif_settings['session_proposed'] ?? true); ?> />
                                            Session proposée
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="tmm_notification_settings[session_confirmed]" value="1" <?php checked($notif_settings['session_confirmed'] ?? true); ?> />
                                            Session confirmée
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="tmm_notification_settings[daily_reminders]" value="1" <?php checked($notif_settings['daily_reminders'] ?? true); ?> />
                                            Rappels quotidiens
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Autres onglets... -->
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        
        <style>
        .tmm-settings-tabs .tmm-tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tmm-settings-tabs .tmm-tab-content.active {
            display: block;
        }
        
        .nav-tab {
            cursor: pointer;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tmm-tab-content').removeClass('active');
                
                $(this).addClass('nav-tab-active');
                $(target).addClass('active');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Page d'outils
     */
    public function tools_page() {
        ?>
        <div class="wrap tmm-tools-page">
            <h1>🛠️ Outils TeachMeMore PédagoConnect</h1>
            
            <div class="tmm-tools-grid">
                <div class="tool-section">
                    <h2>📊 Maintenance</h2>
                    <div class="tool-actions">
                        <button id="clear-cache" class="button">🗑️ Vider le Cache</button>
                        <button id="rebuild-index" class="button">🔄 Reconstruire Index</button>
                        <button id="check-integrity" class="button">✅ Vérifier Intégrité</button>
                    </div>
                </div>
                
                <div class="tool-section">
                    <h2>📤 Export/Import</h2>
                    <div class="tool-actions">
                        <button id="export-all" class="button">📤 Exporter Tout</button>
                        <button id="export-sessions" class="button">📅 Exporter Sessions</button>
                        <button id="import-data" class="button">📥 Importer Données</button>
                    </div>
                </div>
                
                <div class="tool-section">
                    <h2>🔧 Debug</h2>
                    <div class="tool-actions">
                        <button id="system-info" class="button">ℹ️ Infos Système</button>
                        <button id="test-email" class="button">📧 Test Email</button>
                        <button id="view-logs" class="button">📋 Voir Logs</button>
                    </div>
                </div>
            </div>
            
            <div id="tool-results" class="tmm-tool-results" style="display: none;">
                <h3>Résultats</h3>
                <div id="results-content"></div>
            </div>
        </div>
        
        <style>
        .tmm-tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .tool-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tool-section h2 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .tool-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .tmm-tool-results {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        #results-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Actions des outils
            $('#clear-cache').click(function() {
                runTool('clear_cache', 'Vidage du cache...');
            });
            
            $('#system-info').click(function() {
                runTool('system_info', 'Récupération des informations système...');
            });
            
            $('#test-email').click(function() {
                var email = prompt('Adresse email pour le test:');
                if (email) {
                    runTool('test_email', 'Envoi de l\'email de test...', {email: email});
                }
            });
            
            function runTool(tool, loadingText, data = {}) {
                $('#tool-results').show();
                $('#results-content').text(loadingText);
                
                $.post(ajaxurl, {
                    action: 'tmm_run_tool',
                    tool: tool,
                    data: data,
                    nonce: tmm_admin.nonce
                }, function(response) {
                    if (response.success) {
                        $('#results-content').text(response.data);
                    } else {
                        $('#results-content').text('Erreur: ' + response.data);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Ajouter les champs de paramètres
     */
    private function add_settings_fields() {
        // Champs généraux
        add_settings_field(
            'tmm_company_name',
            'Nom de l\'entreprise',
            array($this, 'company_name_callback'),
            'tmm_settings',
            'tmm_general_section'
        );
        
        // Champs email
        add_settings_field(
            'tmm_sender_email',
            'Email expéditeur',
            array($this, 'sender_email_callback'),
            'tmm_settings',
            'tmm_email_section'
        );
    }
    
    /**
     * Callbacks pour les sections
     */
    public function general_section_callback() {
        echo '<p>Configuration générale du plugin TeachMeMore PédagoConnect.</p>';
    }
    
    public function email_section_callback() {
        echo '<p>Paramètres pour l\'envoi d\'emails automatiques.</p>';
    }
    
    public function notification_section_callback() {
        echo '<p>Gestion des notifications utilisateurs.</p>';
    }
    
    /**
     * Callbacks pour les champs
     */
    public function company_name_callback() {
        $value = get_option('tmm_company_name', 'TeachMeMore');
        echo '<input type="text" name="tmm_company_name" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function sender_email_callback() {
        $value = get_option('tmm_sender_email', get_option('admin_email'));
        echo '<input type="email" name="tmm_sender_email" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    /**
     * AJAX: Obtenir données dashboard
     */
    public function get_dashboard_data() {
        check_ajax_referer('tmm_admin_nonce', 'nonce');
        
        if (!current_user_can('view_tmm_reports')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        $data = array(
            'total_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table"),
            'pending_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table s JOIN {$wpdb->posts} p ON s.session_id = p.ID WHERE p.post_status = 'proposed'"),
            'confirmed_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table s JOIN {$wpdb->posts} p ON s.session_id = p.ID WHERE p.post_status = 'confirmed'"),
            'total_hours' => $wpdb->get_var("SELECT SUM(hours_realized) FROM $sessions_table"),
            'active_schools' => $wpdb->get_var("SELECT COUNT(DISTINCT school_id) FROM $sessions_table"),
            'active_trainers' => $wpdb->get_var("SELECT COUNT(DISTINCT trainer_id) FROM $sessions_table WHERE trainer_id IS NOT NULL")
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Créer session rapide
     */
    public function create_quick_session() {
        check_ajax_referer('tmm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_sessions')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $school_id = intval($_POST['school_id']);
        $module_id = intval($_POST['module_id']);
        $preferred_date = sanitize_text_field($_POST['preferred_date']);
        $duration = floatval($_POST['duration']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Créer la session
        $session_id = wp_insert_post(array(
            'post_title' => get_the_title($module_id) . ' - ' . get_the_title($school_id),
            'post_type' => 'tmm_session',
            'post_status' => 'proposed',
            'post_author' => get_current_user_id()
        ));
        
        if ($session_id) {
            global $wpdb;
            $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
            
            $wpdb->insert($sessions_table, array(
                'session_id' => $session_id,
                'school_id' => $school_id,
                'module_id' => $module_id,
                'start_datetime' => $preferred_date . ' 09:00:00',
                'end_datetime' => date('Y-m-d H:i:s', strtotime($preferred_date . ' 09:00:00 +' . $duration . ' hours')),
                'hours_planned' => $duration,
                'notes' => $notes
            ));
            
            // Déclencher notification
            do_action('tmm_session_created', $session_id, $school_id);
            
            wp_send_json_success(array(
                'session_id' => $session_id,
                'message' => 'Session créée et proposition envoyée'
            ));
        } else {
            wp_send_json_error('Erreur lors de la création de la session');
        }
    }
    
    /**
     * AJAX: Obtenir activité récente
     */
    public function get_recent_activity() {
        check_ajax_referer('tmm_admin_nonce', 'nonce');
        
        global $wpdb;
        
        $activities = $wpdb->get_results("
            SELECT 
                p.post_title,
                p.post_status,
                p.post_modified,
                sch.post_title as school_name,
                m.post_title as module_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}tmm_sessions_meta sm ON p.ID = sm.session_id
            LEFT JOIN {$wpdb->posts} sch ON sm.school_id = sch.ID
            LEFT JOIN {$wpdb->posts} m ON sm.module_id = m.ID
            WHERE p.post_type = 'tmm_session'
            AND p.post_modified > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY p.post_modified DESC
            LIMIT 10
        ");
        
        $formatted_activities = array();
        foreach ($activities as $activity) {
            $icon = '📅';
            switch ($activity->post_status) {
                case 'proposed': $icon = '📋'; break;
                case 'confirmed': $icon = '✅'; break;
                case 'completed': $icon = '🎉'; break;
                case 'cancelled': $icon = '❌'; break;
            }
            
            $formatted_activities[] = array(
                'icon' => $icon,
                'description' => sprintf('%s - %s chez %s', 
                    $activity->module_name, 
                    ucfirst($activity->post_status), 
                    $activity->school_name
                ),
                'time_ago' => human_time_diff(strtotime($activity->post_modified), current_time('timestamp')) . ' ago'
            );
        }
        
        wp_send_json_success($formatted_activities);
    }
    
    /**
     * Colonnes personnalisées pour les sessions
     */
    public function session_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['school'] = 'École';
        $new_columns['module'] = 'Module';
        $new_columns['trainer'] = 'Formateur';
        $new_columns['date'] = 'Date';
        $new_columns['status'] = 'Statut';
        $new_columns['hours'] = 'Heures';
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    public function session_column_content($column, $post_id) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        $session_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE session_id = %d", $post_id
        ));
        
        if (!$session_data) return;
        
        switch ($column) {
            case 'school':
                $school = get_post($session_data->school_id);
                echo $school ? esc_html($school->post_title) : '-';
                break;
                
            case 'module':
                $module = get_post($session_data->module_id);
                echo $module ? esc_html($module->post_title) : '-';
                break;
                
            case 'trainer':
                if ($session_data->trainer_id) {
                    $trainer = get_user_by('ID', $session_data->trainer_id);
                    echo $trainer ? esc_html($trainer->display_name) : '-';
                } else {
                    echo '<span style="color: #ccc;">Non assigné</span>';
                }
                break;
                
            case 'date':
                echo $session_data->start_datetime ? date('d/m/Y H:i', strtotime($session_data->start_datetime)) : '-';
                break;
                
            case 'status':
                $status = get_post_status($post_id);
                $status_labels = array(
                    'proposed' => '<span style="color: #f57c00;">Proposée</span>',
                    'confirmed' => '<span style="color: #388e3c;">Confirmée</span>',
                    'in_progress' => '<span style="color: #1976d2;">En cours</span>',
                    'completed' => '<span style="color: #00796b;">Terminée</span>',
                    'cancelled' => '<span style="color: #d32f2f;">Annulée</span>'
                );
                echo $status_labels[$status] ?? $status;
                break;
                
            case 'hours':
                echo $session_data->hours_planned . 'h';
                if ($session_data->hours_realized > 0) {
                    echo ' (' . $session_data->hours_realized . 'h réalisées)';
                }
                break;
        }
    }
    
    /**
     * Notifications admin
     */
    public function admin_notices() {
        $screen = get_current_screen();
        
        // Vérifier si installation complète
        if (!get_option('tmm_installation_completed') && $screen->base !== 'toplevel_page_tmm-installer') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>TeachMeMore PédagoConnect:</strong> 
                    Configuration incomplète. 
                    <a href="<?php echo admin_url('admin.php?page=tmm-installer'); ?>">Terminer l'installation</a>
                </p>
            </div>
            <?php
        }
        
        // Vérifier les prérequis
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>TeachMeMore PédagoConnect:</strong> 
                    PHP 7.4+ requis. Version actuelle: <?php echo PHP_VERSION; ?>
                </p>
            </div>
            <?php
        }
    }
}

// Initialiser la classe admin
if (is_admin()) {
    new TMM_Admin();
}