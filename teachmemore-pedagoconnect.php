<?php
/**
 * Plugin Name: TeachMeMore PédagoConnect
 * Plugin URI: https://teachmemore.fr
 * Description: Plugin innovant pour la gestion collaborative des plannings pédagogiques entre TeachMeMore et les écoles partenaires
 * Version: 1.0.0
 * Author: TeachMeMore
 * License: GPL v2 or later
 * Text Domain: tmm-pedagoconnect
 */

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Constantes du plugin
define('TMM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TMM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TMM_PLUGIN_VERSION', '1.0.0');

/**
 * Classe principale du plugin TeachMeMore PédagoConnect
 */
class TeachMeMore_PedagoConnect {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Hooks d'activation/désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Charger les classes
        $this->load_dependencies();
    }
    
    /**
     * Initialisation du plugin
     */
    public function init() {
        // Créer les custom post types
        $this->create_custom_post_types();
        
        // Créer les rôles utilisateurs
        $this->create_user_roles();
        
        // Ajouter les pages admin
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Shortcodes
        add_shortcode('tmm_school_dashboard', array($this, 'school_dashboard_shortcode'));
        add_shortcode('tmm_planning_calendar', array($this, 'planning_calendar_shortcode'));
        add_shortcode('tmm_availability_form', array($this, 'availability_form_shortcode'));
    }
    
    /**
     * Charger les dépendances
     */
    private function load_dependencies() {
        require_once TMM_PLUGIN_PATH . 'includes/class-tmm-admin.php';
        require_once TMM_PLUGIN_PATH . 'includes/class-tmm-schools.php';
        require_once TMM_PLUGIN_PATH . 'includes/class-tmm-modules.php';
        require_once TMM_PLUGIN_PATH . 'includes/class-tmm-planning.php';
        require_once TMM_PLUGIN_PATH . 'includes/class-tmm-availabilities.php';
        require_once TMM_PLUGIN_PATH . 'includes/class-tmm-reporting.php';
        require_once TMM_PLUGIN_PATH . 'includes/class-tmm-notifications.php';
    }
    
    /**
     * Créer les Custom Post Types
     */
    public function create_custom_post_types() {
        
        // CPT Module TeachMeMore
        register_post_type('tmm_module', array(
            'labels' => array(
                'name' => 'Modules TeachMeMore',
                'singular_name' => 'Module',
                'add_new' => 'Ajouter un module',
                'add_new_item' => 'Ajouter un nouveau module',
                'edit_item' => 'Modifier le module',
                'new_item' => 'Nouveau module',
                'view_item' => 'Voir le module',
                'search_items' => 'Rechercher des modules',
                'not_found' => 'Aucun module trouvé',
                'not_found_in_trash' => 'Aucun module dans la corbeille'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon' => 'dashicons-book-alt',
            'show_in_rest' => true,
            'capability_type' => 'post',
            'map_meta_cap' => true
        ));
        
        // CPT École Partenaire
        register_post_type('tmm_school', array(
            'labels' => array(
                'name' => 'Écoles Partenaires',
                'singular_name' => 'École',
                'add_new' => 'Ajouter une école',
                'add_new_item' => 'Ajouter une nouvelle école',
                'edit_item' => 'Modifier l\'école',
                'new_item' => 'Nouvelle école',
                'view_item' => 'Voir l\'école',
                'search_items' => 'Rechercher des écoles',
                'not_found' => 'Aucune école trouvée',
                'not_found_in_trash' => 'Aucune école dans la corbeille'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon' => 'dashicons-building',
            'show_in_rest' => true,
            'capability_type' => 'post',
            'map_meta_cap' => true
        ));
        
        // CPT Session Planifiée
        register_post_type('tmm_session', array(
            'labels' => array(
                'name' => 'Sessions Planifiées',
                'singular_name' => 'Session',
                'add_new' => 'Planifier une session',
                'add_new_item' => 'Planifier une nouvelle session',
                'edit_item' => 'Modifier la session',
                'new_item' => 'Nouvelle session',
                'view_item' => 'Voir la session',
                'search_items' => 'Rechercher des sessions',
                'not_found' => 'Aucune session trouvée',
                'not_found_in_trash' => 'Aucune session dans la corbeille'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'custom-fields'),
            'menu_icon' => 'dashicons-calendar-alt',
            'show_in_rest' => true,
            'capability_type' => 'post',
            'map_meta_cap' => true
        ));
        
        // CPT Feedback/Suivi
        register_post_type('tmm_feedback', array(
            'labels' => array(
                'name' => 'Suivi & Feedback',
                'singular_name' => 'Feedback',
                'add_new' => 'Ajouter un suivi',
                'add_new_item' => 'Ajouter un nouveau suivi',
                'edit_item' => 'Modifier le suivi',
                'new_item' => 'Nouveau suivi',
                'view_item' => 'Voir le suivi',
                'search_items' => 'Rechercher des suivis',
                'not_found' => 'Aucun suivi trouvé',
                'not_found_in_trash' => 'Aucun suivi dans la corbeille'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'custom-fields'),
            'menu_icon' => 'dashicons-chart-line',
            'show_in_rest' => true,
            'capability_type' => 'post',
            'map_meta_cap' => true
        ));
    }
    
    /**
     * Créer les rôles utilisateurs
     */
    public function create_user_roles() {
        // Rôle École Partenaire
        add_role('partner_school', 'École Partenaire', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => true,
        ));
        
        // Rôle Formateur TeachMeMore
        add_role('tmm_trainer', 'Formateur TeachMeMore', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => true,
        ));
        
        // Rôle Responsable Pédagogique TeachMeMore
        add_role('tmm_pedagog_manager', 'Responsable Pédagogique TMM', array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
            'manage_categories' => true,
        ));
    }
    
    /**
     * Menu admin
     */
    public function admin_menu() {
        add_menu_page(
            'TMM PédagoConnect',
            'PédagoConnect', 
            'manage_options',
            'tmm-pedagoconnect',
            array($this, 'admin_dashboard'),
            'dashicons-networking',
            30
        );
        
        add_submenu_page(
            'tmm-pedagoconnect',
            'Planification Globale',
            'Planning Global',
            'manage_options',
            'tmm-global-planning',
            array($this, 'global_planning_page')
        );
        
        add_submenu_page(
            'tmm-pedagoconnect',
            'Disponibilités Formateurs',
            'Disponibilités',
            'manage_options',
            'tmm-availabilities',
            array($this, 'availabilities_page')
        );
        
        add_submenu_page(
            'tmm-pedagoconnect',
            'Reporting & Analytics',
            'Reporting',
            'manage_options',
            'tmm-reporting',
            array($this, 'reporting_page')
        );
        
        add_submenu_page(
            'tmm-pedagoconnect',
            'Paramètres',
            'Paramètres',
            'manage_options',
            'tmm-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Dashboard principal admin
     */
    public function admin_dashboard() {
        include TMM_PLUGIN_PATH . 'templates/admin-dashboard.php';
    }
    
    /**
     * Page planning global
     */
    public function global_planning_page() {
        include TMM_PLUGIN_PATH . 'templates/global-planning.php';
    }
    
    /**
     * Page disponibilités
     */
    public function availabilities_page() {
        include TMM_PLUGIN_PATH . 'templates/availabilities.php';
    }
    
    /**
     * Page reporting
     */
    public function reporting_page() {
        include TMM_PLUGIN_PATH . 'templates/reporting.php';
    }
    
    /**
     * Page paramètres
     */
    public function settings_page() {
        include TMM_PLUGIN_PATH . 'templates/settings.php';
    }
    
    /**
     * Scripts frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_script('tmm-frontend', TMM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), TMM_PLUGIN_VERSION, true);
        wp_enqueue_style('tmm-frontend', TMM_PLUGIN_URL . 'assets/css/frontend.css', array(), TMM_PLUGIN_VERSION);
        
        // Calendrier FullCalendar
        wp_enqueue_script('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.js', array('jquery'), null, true);
        wp_enqueue_style('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.css');
        
        // Localize script pour AJAX
        wp_localize_script('tmm-frontend', 'tmm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tmm_nonce')
        ));
    }
    
    /**
     * Scripts admin
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_script('tmm-admin', TMM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TMM_PLUGIN_VERSION, true);
        wp_enqueue_style('tmm-admin', TMM_PLUGIN_URL . 'assets/css/admin.css', array(), TMM_PLUGIN_VERSION);
        
        // Calendrier admin
        wp_enqueue_script('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.js', array('jquery'), null, true);
        wp_enqueue_style('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.css');
    }
    
    /**
     * Shortcode tableau de bord école
     */
    public function school_dashboard_shortcode($atts) {
        if (!is_user_logged_in() || !current_user_can('partner_school')) {
            return '<p>Accès réservé aux écoles partenaires.</p>';
        }
        
        ob_start();
        include TMM_PLUGIN_PATH . 'templates/school-dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode calendrier planning
     */
    public function planning_calendar_shortcode($atts) {
        ob_start();
        include TMM_PLUGIN_PATH . 'templates/planning-calendar.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode formulaire disponibilités
     */
    public function availability_form_shortcode($atts) {
        if (!is_user_logged_in() || !current_user_can('tmm_trainer')) {
            return '<p>Accès réservé aux formateurs TeachMeMore.</p>';
        }
        
        ob_start();
        include TMM_PLUGIN_PATH . 'templates/availability-form.php';
        return ob_get_clean();
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        // Créer les tables personnalisées
        $this->create_database_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Créer les tables de base de données
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table des disponibilités
        $table_availabilities = $wpdb->prefix . 'tmm_availabilities';
        $sql_availabilities = "CREATE TABLE $table_availabilities (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            is_recurring tinyint(1) DEFAULT 0,
            recurrence_rule varchar(255) DEFAULT '',
            availability_type enum('available','unavailable') DEFAULT 'available',
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime)
        ) $charset_collate;";
        
        // Table des notifications
        $table_notifications = $wpdb->prefix . 'tmm_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            notification_type enum('info','warning','success','error') DEFAULT 'info',
            is_read tinyint(1) DEFAULT 0,
            related_post_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table des sessions avec statuts
        $table_sessions_meta = $wpdb->prefix . 'tmm_sessions_meta';
        $sql_sessions_meta = "CREATE TABLE $table_sessions_meta (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            school_id bigint(20) NOT NULL,
            module_id bigint(20) NOT NULL,
            trainer_id bigint(20) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            status enum('proposed','confirmed','cancelled','completed') DEFAULT 'proposed',
            hours_planned decimal(5,2) DEFAULT 0,
            hours_realized decimal(5,2) DEFAULT 0,
            location varchar(255) DEFAULT '',
            group_name varchar(255) DEFAULT '',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY school_id (school_id),
            KEY module_id (module_id),
            KEY trainer_id (trainer_id),
            KEY start_datetime (start_datetime),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_availabilities);
        dbDelta($sql_notifications);
        dbDelta($sql_sessions_meta);
    }
}

// Initialiser le plugin
new TeachMeMore_PedagoConnect();