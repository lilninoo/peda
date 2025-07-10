<?php
/**
 * Classe pour la gestion des notifications temps réel
 * Système intelligent de notifications pour TeachMeMore PédagoConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMM_Notifications {
    
    private $notification_types = array(
        'session_proposed' => array(
            'icon' => '📋',
            'color' => '#ffa726',
            'priority' => 'medium'
        ),
        'session_confirmed' => array(
            'icon' => '✅',
            'color' => '#66bb6a',
            'priority' => 'low'
        ),
        'session_cancelled' => array(
            'icon' => '❌',
            'color' => '#ef5350',
            'priority' => 'high'
        ),
        'session_completed' => array(
            'icon' => '🎉',
            'color' => '#42a5f5',
            'priority' => 'low'
        ),
        'trainer_assigned' => array(
            'icon' => '👨‍🏫',
            'color' => '#ab47bc',
            'priority' => 'medium'
        ),
        'availability_updated' => array(
            'icon' => '📅',
            'color' => '#26a69a',
            'priority' => 'low'
        ),
        'reminder_sent' => array(
            'icon' => '⏰',
            'color' => '#ff7043',
            'priority' => 'medium'
        ),
        'system_alert' => array(
            'icon' => '🚨',
            'color' => '#f44336',
            'priority' => 'critical'
        ),
        'report_ready' => array(
            'icon' => '📊',
            'color' => '#5c6bc0',
            'priority' => 'low'
        ),
        'feedback_received' => array(
            'icon' => '⭐',
            'color' => '#ffca28',
            'priority' => 'low'
        )
    );
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // Actions AJAX pour les notifications
        add_action('wp_ajax_tmm_get_notifications', array($this, 'get_notifications'));
        add_action('wp_ajax_tmm_mark_notification_read', array($this, 'mark_notification_read'));
        add_action('wp_ajax_tmm_mark_all_read', array($this, 'mark_all_read'));
        add_action('wp_ajax_tmm_delete_notification', array($this, 'delete_notification'));
        add_action('wp_ajax_tmm_get_notification_count', array($this, 'get_notification_count'));
        add_action('wp_ajax_tmm_send_test_notification', array($this, 'send_test_notification'));
        
        // Actions pour utilisateurs non-connectés (écoles)
        add_action('wp_ajax_nopriv_tmm_get_notifications', array($this, 'get_notifications'));
        add_action('wp_ajax_nopriv_tmm_mark_notification_read', array($this, 'mark_notification_read'));
        
        // Hooks pour notifications automatiques
        add_action('tmm_session_created', array($this, 'on_session_created'), 10, 2);
        add_action('tmm_session_confirmed', array($this, 'on_session_confirmed'), 10, 2);
        add_action('tmm_session_cancelled', array($this, 'on_session_cancelled'), 10, 2);
        add_action('tmm_session_completed', array($this, 'on_session_completed'), 10, 2);
        add_action('tmm_trainer_assigned', array($this, 'on_trainer_assigned'), 10, 3);
        add_action('tmm_availability_updated', array($this, 'on_availability_updated'), 10, 2);
        add_action('tmm_feedback_received', array($this, 'on_feedback_received'), 10, 3);
        
        // Notifications périodiques
        add_action('tmm_daily_reminders', array($this, 'send_daily_reminders'));
        add_action('tmm_weekly_summary', array($this, 'send_weekly_summary'));
        
        // Programmer les tâches CRON
        if (!wp_next_scheduled('tmm_daily_reminders')) {
            wp_schedule_event(time(), 'daily', 'tmm_daily_reminders');
        }
        if (!wp_next_scheduled('tmm_weekly_summary')) {
            wp_schedule_event(time(), 'weekly', 'tmm_weekly_summary');
        }
        
        // Nettoyage automatique des anciennes notifications
        add_action('tmm_cleanup_notifications', array($this, 'cleanup_old_notifications'));
        if (!wp_next_scheduled('tmm_cleanup_notifications')) {
            wp_schedule_event(time(), 'weekly', 'tmm_cleanup_notifications');
        }
    }
    
    public function init() {
        // Enqueue scripts pour notifications temps réel
        add_action('wp_enqueue_scripts', array($this, 'enqueue_notification_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_notification_scripts'));
        
        // Ajouter le widget notifications au header
        add_action('wp_head', array($this, 'add_notification_widget'));
        add_action('admin_head', array($this, 'add_notification_widget'));
    }
    
    /**
     * Enqueue scripts pour notifications
     */
    public function enqueue_notification_scripts() {
        wp_enqueue_script('tmm-notifications', TMM_PLUGIN_URL . 'assets/js/notifications.js', array('jquery'), TMM_PLUGIN_VERSION, true);
        wp_enqueue_style('tmm-notifications', TMM_PLUGIN_URL . 'assets/css/notifications.css', array(), TMM_PLUGIN_VERSION);
        
        wp_localize_script('tmm-notifications', 'tmm_notifications', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tmm_notifications_nonce'),
            'user_id' => get_current_user_id(),
            'poll_interval' => apply_filters('tmm_notifications_poll_interval', 30000), // 30 secondes
            'sound_enabled' => get_user_meta(get_current_user_id(), 'tmm_notification_sound', true) !== 'disabled'
        ));
    }
    
    /**
     * Ajouter le widget notifications
     */
    public function add_notification_widget() {
        if (!is_user_logged_in()) return;
        
        ?>
        <div id="tmm-notification-widget" class="tmm-notification-widget">
            <div class="notification-bell" id="notification-bell">
                <span class="bell-icon">🔔</span>
                <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
            </div>
            
            <div class="notification-dropdown" id="notification-dropdown">
                <div class="dropdown-header">
                    <h4>Notifications</h4>
                    <div class="header-actions">
                        <button id="mark-all-read" class="btn-link">Tout marquer lu</button>
                        <button id="notification-settings" class="btn-link">⚙️</button>
                    </div>
                </div>
                
                <div class="notification-list" id="notification-list">
                    <!-- Chargé dynamiquement -->
                </div>
                
                <div class="dropdown-footer">
                    <a href="#" id="view-all-notifications">Voir toutes les notifications</a>
                </div>
            </div>
        </div>
        
        <style>
        .tmm-notification-widget {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
        }
        
        .notification-bell {
            cursor: pointer;
            position: relative;
            padding: 10px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .notification-bell:hover {
            transform: scale(1.1);
        }
        
        .bell-icon {
            font-size: 24px;
            display: block;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            width: 350px;
            max-height: 400px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: none;
            z-index: 10001;
        }
        
        .dropdown-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .dropdown-header h4 {
            margin: 0;
            color: #2c3e50;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-link {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        
        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .dropdown-footer {
            padding: 15px 20px;
            border-top: 1px solid #f1f3f4;
            text-align: center;
        }
        
        .dropdown-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        </style>
        <?php
    }
    
    /**
     * Créer une notification
     */
    public function create_notification($user_id, $type, $title, $message, $related_post_id = null, $data = array()) {
        global $wpdb;
        
        if (!isset($this->notification_types[$type])) {
            return false;
        }
        
        $table = $wpdb->prefix . 'tmm_notifications';
        
        $notification_data = array(
            'user_id' => $user_id,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'related_post_id' => $related_post_id,
            'data' => json_encode($data),
            'is_read' => 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $notification_data);
        
        if ($result) {
            $notification_id = $wpdb->insert_id;
            
            // Envoyer notification temps réel si l'utilisateur est en ligne
            $this->send_realtime_notification($user_id, $notification_id, $type, $title, $message);
            
            // Envoyer par email si configuré
            if ($this->should_send_email($user_id, $type)) {
                $this->send_email_notification($user_id, $type, $title, $message, $data);
            }
            
            // Déclencher webhook si configuré
            do_action('tmm_notification_created', $notification_id, $user_id, $type, $data);
            
            return $notification_id;
        }
        
        return false;
    }
    
    /**
     * Notification broadcast (plusieurs utilisateurs)
     */
    public function broadcast_notification($user_ids, $type, $title, $message, $related_post_id = null, $data = array()) {
        $notifications_created = 0;
        
        foreach ($user_ids as $user_id) {
            if ($this->create_notification($user_id, $type, $title, $message, $related_post_id, $data)) {
                $notifications_created++;
            }
        }
        
        return $notifications_created;
    }
    
    /**
     * Récupérer les notifications via AJAX
     */
    public function get_notifications() {
        check_ajax_referer('tmm_notifications_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $unread_only = isset($_POST['unread_only']) ? boolval($_POST['unread_only']) : false;
        
        $notifications = $this->get_user_notifications($user_id, $limit, $offset, $unread_only);
        
        wp_send_json_success($notifications);
    }
    
    /**
     * Récupérer les notifications d'un utilisateur
     */
    private function get_user_notifications($user_id, $limit = 10, $offset = 0, $unread_only = false) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_notifications';
        
        $where_clause = $wpdb->prepare("WHERE user_id = %d", $user_id);
        if ($unread_only) {
            $where_clause .= " AND is_read = 0";
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table 
             $where_clause 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $limit, $offset
        );
        
        $notifications = $wpdb->get_results($query);
        
        // Enrichir les données
        foreach ($notifications as &$notification) {
            $notification->type_config = $this->notification_types[$notification->notification_type] ?? array();
            $notification->data = json_decode($notification->data, true) ?: array();
            $notification->time_ago = $this->time_ago($notification->created_at);
            $notification->formatted_message = $this->format_notification_message($notification);
        }
        
        return $notifications;
    }
    
    /**
     * Marquer notification comme lue
     */
    public function mark_notification_read() {
        check_ajax_referer('tmm_notifications_nonce', 'nonce');
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'tmm_notifications';
        
        $result = $wpdb->update(
            $table,
            array('is_read' => 1, 'read_at' => current_time('mysql')),
            array('id' => $notification_id, 'user_id' => $user_id),
            array('%d', '%s'),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Erreur lors de la mise à jour');
        }
    }
    
    /**
     * Marquer toutes les notifications comme lues
     */
    public function mark_all_read() {
        check_ajax_referer('tmm_notifications_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'tmm_notifications';
        
        $result = $wpdb->update(
            $table,
            array('is_read' => 1, 'read_at' => current_time('mysql')),
            array('user_id' => $user_id, 'is_read' => 0),
            array('%d', '%s'),
            array('%d', '%d')
        );
        
        wp_send_json_success(array('updated_count' => $result));
    }
    
    /**
     * Obtenir le nombre de notifications non lues
     */
    public function get_notification_count() {
        check_ajax_referer('tmm_notifications_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'tmm_notifications';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
        
        wp_send_json_success(array('count' => intval($count)));
    }
    
    /**
     * Supprimer une notification
     */
    public function delete_notification() {
        check_ajax_referer('tmm_notifications_nonce', 'nonce');
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'tmm_notifications';
        
        $result = $wpdb->delete(
            $table,
            array('id' => $notification_id, 'user_id' => $user_id),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Erreur lors de la suppression');
        }
    }
    
    /**
     * Envoi de notification temps réel
     */
    private function send_realtime_notification($user_id, $notification_id, $type, $title, $message) {
        // Ici on pourrait intégrer WebSockets, Server-Sent Events, ou push notifications
        // Pour simplifier, on utilise un système de polling AJAX
        
        $notification_data = array(
            'id' => $notification_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'config' => $this->notification_types[$type] ?? array(),
            'timestamp' => time()
        );
        
        // Stocker en cache temporaire pour le polling
        $cache_key = 'tmm_realtime_notifications_' . $user_id;
        $existing_notifications = get_transient($cache_key) ?: array();
        $existing_notifications[] = $notification_data;
        
        // Garder seulement les 10 dernières
        $existing_notifications = array_slice($existing_notifications, -10);
        
        set_transient($cache_key, $existing_notifications, 300); // 5 minutes
        
        do_action('tmm_realtime_notification_sent', $user_id, $notification_data);
    }
    
    /**
     * Vérifier si notification email doit être envoyée
     */
    private function should_send_email($user_id, $type) {
        // Récupérer préférences utilisateur
        $email_preferences = get_user_meta($user_id, 'tmm_email_notifications', true);
        
        if (!$email_preferences) {
            // Préférences par défaut
            $email_preferences = array(
                'session_proposed' => true,
                'session_confirmed' => true,
                'session_cancelled' => true,
                'system_alert' => true,
                'feedback_received' => false,
                'availability_updated' => false
            );
        }
        
        return isset($email_preferences[$type]) ? $email_preferences[$type] : false;
    }
    
    /**
     * Envoyer notification par email
     */
    private function send_email_notification($user_id, $type, $title, $message, $data = array()) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return false;
        
        $config = $this->notification_types[$type] ?? array();
        
        $subject = sprintf('[TeachMeMore] %s %s', $config['icon'] ?? '📧', $title);
        
        $email_template = $this->build_email_template($type, $title, $message, $data, $config);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: TeachMeMore PédagoConnect <noreply@teachmemore.fr>'
        );
        
        return wp_mail($user->user_email, $subject, $email_template, $headers);
    }
    
    /**
     * Template email pour notifications
     */
    private function build_email_template($type, $title, $message, $data, $config) {
        $icon = $config['icon'] ?? '📧';
        $color = $config['color'] ?? '#667eea';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; }
                .header { background: <?php echo $color; ?>; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .icon { font-size: 32px; margin-bottom: 10px; }
                .button { 
                    display: inline-block; 
                    background: <?php echo $color; ?>; 
                    color: white; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 6px; 
                    margin: 10px 0; 
                }
                .footer { 
                    background: #f8f9fa; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #666; 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="icon"><?php echo $icon; ?></div>
                    <h1><?php echo esc_html($title); ?></h1>
                </div>
                
                <div class="content">
                    <p><?php echo nl2br(esc_html($message)); ?></p>
                    
                    <?php if (isset($data['action_url'])): ?>
                        <a href="<?php echo esc_url($data['action_url']); ?>" class="button">
                            <?php echo $data['action_text'] ?? 'Voir les détails'; ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($data)): ?>
                        <div style="background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 6px;">
                            <strong>Informations complémentaires:</strong>
                            <ul>
                                <?php foreach ($data as $key => $value): ?>
                                    <?php if (!in_array($key, array('action_url', 'action_text'))): ?>
                                        <li><?php echo esc_html(ucfirst($key)); ?>: <?php echo esc_html($value); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="footer">
                    <p>Cette notification a été envoyée par TeachMeMore PédagoConnect</p>
                    <p>
                        <a href="<?php echo home_url('/espace-partenaire/'); ?>">Accéder à votre espace</a> |
                        <a href="<?php echo home_url('/preferences-notifications/'); ?>">Gérer vos préférences</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Hooks pour événements automatiques
     */
    public function on_session_created($session_id, $school_id) {
        $session = get_post($session_id);
        $school = get_post($school_id);
        
        // Notifier l'école
        $school_users = $this->get_school_users($school_id);
        foreach ($school_users as $user_id) {
            $this->create_notification(
                $user_id,
                'session_proposed',
                'Nouvelle proposition de session',
                sprintf('TeachMeMore vous propose une nouvelle session: %s', $session->post_title),
                $session_id,
                array(
                    'school_name' => $school->post_title,
                    'action_url' => home_url('/espace-partenaire/'),
                    'action_text' => 'Répondre à la proposition'
                )
            );
        }
        
        // Notifier les responsables TeachMeMore
        $tmm_managers = get_users(array('role' => 'tmm_pedagog_manager'));
        foreach ($tmm_managers as $manager) {
            $this->create_notification(
                $manager->ID,
                'session_proposed',
                'Proposition envoyée',
                sprintf('Proposition de session envoyée à %s', $school->post_title),
                $session_id
            );
        }
    }
    
    public function on_session_confirmed($session_id, $school_id) {
        $session = get_post($session_id);
        $school = get_post($school_id);
        
        // Notifier TeachMeMore
        $tmm_managers = get_users(array('role' => 'tmm_pedagog_manager'));
        foreach ($tmm_managers as $manager) {
            $this->create_notification(
                $manager->ID,
                'session_confirmed',
                'Session confirmée !',
                sprintf('%s a confirmé la session: %s', $school->post_title, $session->post_title),
                $session_id,
                array(
                    'school_name' => $school->post_title,
                    'action_url' => admin_url('post.php?post=' . $session_id . '&action=edit'),
                    'action_text' => 'Voir la session'
                )
            );
        }
        
        // Notifier le formateur si assigné
        $trainer_id = get_post_meta($session_id, 'trainer_id', true);
        if ($trainer_id) {
            $this->create_notification(
                $trainer_id,
                'session_confirmed',
                'Session confirmée',
                sprintf('Votre session chez %s est confirmée: %s', $school->post_title, $session->post_title),
                $session_id
            );
        }
    }
    
    public function on_trainer_assigned($session_id, $trainer_id, $school_id) {
        $session = get_post($session_id);
        $trainer = get_user_by('ID', $trainer_id);
        $school = get_post($school_id);
        
        // Notifier le formateur
        $this->create_notification(
            $trainer_id,
            'trainer_assigned',
            'Nouvelle mission assignée',
            sprintf('Vous avez été assigné à la session: %s chez %s', $session->post_title, $school->post_title),
            $session_id,
            array(
                'school_name' => $school->post_title,
                'session_date' => get_post_meta($session_id, 'start_datetime', true),
                'action_url' => home_url('/formateur-dashboard/'),
                'action_text' => 'Voir mes missions'
            )
        );
        
        // Notifier l'école
        $school_users = $this->get_school_users($school_id);
        foreach ($school_users as $user_id) {
            $this->create_notification(
                $user_id,
                'trainer_assigned',
                'Formateur assigné',
                sprintf('Le formateur %s a été assigné à votre session: %s', $trainer->display_name, $session->post_title),
                $session_id
            );
        }
    }
    
    /**
     * Rappels quotidiens automatiques
     */
    public function send_daily_reminders() {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        // Sessions qui commencent demain
        $tomorrow_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.post_title, sch.post_title as school_name, u.display_name as trainer_name
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->posts} sch ON s.school_id = sch.ID
             LEFT JOIN {$wpdb->users} u ON s.trainer_id = u.ID
             WHERE DATE(s.start_datetime) = %s
             AND p.post_status = 'confirmed'",
            date('Y-m-d', strtotime('+1 day'))
        ));
        
        foreach ($tomorrow_sessions as $session) {
            // Rappel à l'école
            $school_users = $this->get_school_users($session->school_id);
            foreach ($school_users as $user_id) {
                $this->create_notification(
                    $user_id,
                    'reminder_sent',
                    'Session demain',
                    sprintf('Rappel: Session "%s" prévue demain avec %s', $session->post_title, $session->trainer_name),
                    $session->session_id
                );
            }
            
            // Rappel au formateur
            if ($session->trainer_id) {
                $this->create_notification(
                    $session->trainer_id,
                    'reminder_sent',
                    'Mission demain',
                    sprintf('Rappel: Vous avez une session demain chez %s', $session->school_name),
                    $session->session_id
                );
            }
        }
        
        // Sessions en attente depuis plus de 48h
        $pending_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.post_title, sch.post_title as school_name
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->posts} sch ON s.school_id = sch.ID
             WHERE p.post_status = 'proposed'
             AND s.created_at <= %s",
            date('Y-m-d H:i:s', strtotime('-48 hours'))
        ));
        
        if (!empty($pending_sessions)) {
            $tmm_managers = get_users(array('role' => 'tmm_pedagog_manager'));
            foreach ($tmm_managers as $manager) {
                $this->create_notification(
                    $manager->ID,
                    'system_alert',
                    'Sessions en attente',
                    sprintf('%d sessions attendent une réponse depuis plus de 48h', count($pending_sessions)),
                    null,
                    array(
                        'count' => count($pending_sessions),
                        'action_url' => admin_url('admin.php?page=tmm-pedagoconnect'),
                        'action_text' => 'Voir le dashboard'
                    )
                );
            }
        }
    }
    
    /**
     * Résumé hebdomadaire
     */
    public function send_weekly_summary() {
        $stats = $this->get_weekly_stats();
        
        $tmm_managers = get_users(array('role' => 'tmm_pedagog_manager'));
        foreach ($tmm_managers as $manager) {
            $this->create_notification(
                $manager->ID,
                'report_ready',
                'Résumé hebdomadaire disponible',
                sprintf('Cette semaine: %d sessions, %d heures réalisées, %d nouvelles écoles actives', 
                    $stats['sessions'], $stats['hours'], $stats['schools']),
                null,
                array_merge($stats, array(
                    'action_url' => admin_url('admin.php?page=tmm-reporting'),
                    'action_text' => 'Voir le rapport complet'
                ))
            );
        }
    }
    
    /**
     * Nettoyage des anciennes notifications
     */
    public function cleanup_old_notifications() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tmm_notifications';
        
        // Supprimer notifications lues de plus de 30 jours
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table 
             WHERE is_read = 1 
             AND read_at < %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Supprimer notifications non lues de plus de 90 jours
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table 
             WHERE is_read = 0 
             AND created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
    }
    
    /**
     * Utilitaires
     */
    private function get_school_users($school_id) {
        $users = get_users(array(
            'role' => 'partner_school',
            'meta_key' => 'school_id',
            'meta_value' => $school_id
        ));
        
        return array_map(function($user) { return $user->ID; }, $users);
    }
    
    private function time_ago($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'À l\'instant';
        if ($time < 3600) return floor($time/60) . ' min';
        if ($time < 86400) return floor($time/3600) . ' h';
        if ($time < 2592000) return floor($time/86400) . ' j';
        if ($time < 31536000) return floor($time/2592000) . ' mois';
        return floor($time/31536000) . ' ans';
    }
    
    private function format_notification_message($notification) {
        // Remplacer variables dans le message
        $message = $notification->message;
        $data = $notification->data;
        
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }
        }
        
        return $message;
    }
    
    private function get_weekly_stats() {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        $start_date = date('Y-m-d', strtotime('-7 days'));
        
        return array(
            'sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table s
                 JOIN {$wpdb->posts} p ON s.session_id = p.ID
                 WHERE s.created_at >= %s",
                $start_date
            )),
            'hours' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(hours_realized) FROM $sessions_table s
                 JOIN {$wpdb->posts} p ON s.session_id = p.ID
                 WHERE s.updated_at >= %s AND p.post_status = 'completed'",
                $start_date
            )),
            'schools' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT school_id) FROM $sessions_table s
                 WHERE s.created_at >= %s",
                $start_date
            ))
        );
    }
}

// Initialiser la classe
new TMM_Notifications();