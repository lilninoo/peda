<?php
/**
 * Classe pour le reporting et les analytics du plugin TeachMeMore PédagoConnect
 * Génération de rapports, statistiques et tableaux de bord business
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMM_Reporting {
    
    private $cache_duration = 3600; // 1 heure de cache
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // Actions AJAX pour les rapports
        add_action('wp_ajax_tmm_generate_report', array($this, 'generate_report'));
        add_action('wp_ajax_tmm_export_report', array($this, 'export_report'));
        add_action('wp_ajax_tmm_get_dashboard_stats', array($this, 'get_dashboard_stats'));
        add_action('wp_ajax_tmm_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_tmm_get_performance_metrics', array($this, 'get_performance_metrics'));
        add_action('wp_ajax_tmm_get_satisfaction_report', array($this, 'get_satisfaction_report'));
        add_action('wp_ajax_tmm_get_financial_report', array($this, 'get_financial_report'));
        
        // Tâches CRON pour les rapports automatiques
        add_action('tmm_weekly_report', array($this, 'send_weekly_report'));
        add_action('tmm_monthly_report', array($this, 'send_monthly_report'));
        
        // Programmer les rapports automatiques
        if (!wp_next_scheduled('tmm_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'tmm_weekly_report');
        }
        if (!wp_next_scheduled('tmm_monthly_report')) {
            wp_schedule_event(time(), 'monthly', 'tmm_monthly_report');
        }
    }
    
    public function init() {
        // Ajouter les capacités pour les rapports
        $this->add_reporting_capabilities();
    }
    
    /**
     * Ajouter les capacités de reporting aux rôles
     */
    private function add_reporting_capabilities() {
        $manager_role = get_role('tmm_pedagog_manager');
        if ($manager_role) {
            $manager_role->add_cap('view_tmm_reports');
            $manager_role->add_cap('export_tmm_reports');
            $manager_role->add_cap('manage_tmm_analytics');
        }
        
        $school_role = get_role('partner_school');
        if ($school_role) {
            $school_role->add_cap('view_school_reports');
        }
    }
    
    /**
     * Générer un rapport via AJAX
     */
    public function generate_report() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        if (!current_user_can('view_tmm_reports')) {
            wp_send_json_error('Accès non autorisé');
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        $report_data = $this->generate_report_data($report_type, $start_date, $end_date, $filters);
        
        if ($report_data) {
            wp_send_json_success($report_data);
        } else {
            wp_send_json_error('Erreur lors de la génération du rapport');
        }
    }
    
    /**
     * Générer les données de rapport selon le type
     */
    private function generate_report_data($type, $start_date, $end_date, $filters = array()) {
        switch ($type) {
            case 'global_activity':
                return $this->get_global_activity_report($start_date, $end_date, $filters);
            case 'school_performance':
                return $this->get_school_performance_report($start_date, $end_date, $filters);
            case 'trainer_activity':
                return $this->get_trainer_activity_report($start_date, $end_date, $filters);
            case 'module_popularity':
                return $this->get_module_popularity_report($start_date, $end_date, $filters);
            case 'financial_summary':
                return $this->get_financial_summary_report($start_date, $end_date, $filters);
            case 'satisfaction_analysis':
                return $this->get_satisfaction_analysis_report($start_date, $end_date, $filters);
            case 'planning_efficiency':
                return $this->get_planning_efficiency_report($start_date, $end_date, $filters);
            default:
                return false;
        }
    }
    
    /**
     * Rapport d'activité globale
     */
    private function get_global_activity_report($start_date, $end_date, $filters) {
        global $wpdb;
        
        $cache_key = 'tmm_global_activity_' . md5($start_date . $end_date . serialize($filters));
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        // Statistiques générales
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status != 'cancelled'",
            $start_date, $end_date
        ));
        
        $completed_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'",
            $start_date, $end_date
        ));
        
        $total_hours_planned = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(s.hours_planned) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status != 'cancelled'",
            $start_date, $end_date
        ));
        
        $total_hours_realized = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(s.hours_realized) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'",
            $start_date, $end_date
        ));
        
        // Évolution mensuelle
        $monthly_evolution = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(s.start_datetime, '%%Y-%%m') as month,
                COUNT(*) as sessions_count,
                SUM(s.hours_planned) as hours_planned,
                SUM(s.hours_realized) as hours_realized,
                COUNT(CASE WHEN p.post_status = 'completed' THEN 1 END) as completed_sessions
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status != 'cancelled'
             GROUP BY DATE_FORMAT(s.start_datetime, '%%Y-%%m')
             ORDER BY month",
            $start_date, $end_date
        ));
        
        // Top écoles partenaires
        $top_schools = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                sch.post_title as school_name,
                COUNT(*) as sessions_count,
                SUM(s.hours_realized) as total_hours,
                AVG(CASE WHEN fb.meta_value IS NOT NULL THEN fb.meta_value ELSE 0 END) as avg_satisfaction
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->posts} sch ON s.school_id = sch.ID
             LEFT JOIN {$wpdb->postmeta} fb ON p.ID = fb.post_id AND fb.meta_key = 'school_satisfaction_rating'
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'
             GROUP BY s.school_id
             ORDER BY sessions_count DESC
             LIMIT 10",
            $start_date, $end_date
        ));
        
        // Top modules
        $top_modules = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                m.post_title as module_name,
                COUNT(*) as sessions_count,
                SUM(s.hours_realized) as total_hours,
                AVG(s.hours_realized / s.hours_planned * 100) as completion_rate
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->posts} m ON s.module_id = m.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'
             GROUP BY s.module_id
             ORDER BY sessions_count DESC
             LIMIT 10",
            $start_date, $end_date
        ));
        
        // Top formateurs
        $top_trainers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.display_name as trainer_name,
                COUNT(*) as sessions_count,
                SUM(s.hours_realized) as total_hours,
                AVG(CASE WHEN fb.meta_value IS NOT NULL THEN fb.meta_value ELSE 0 END) as avg_rating
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->users} u ON s.trainer_id = u.ID
             LEFT JOIN {$wpdb->postmeta} fb ON p.ID = fb.post_id AND fb.meta_key = 'trainer_satisfaction_rating'
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'
             GROUP BY s.trainer_id
             ORDER BY sessions_count DESC
             LIMIT 10",
            $start_date, $end_date
        ));
        
        $report_data = array(
            'summary' => array(
                'total_sessions' => intval($total_sessions),
                'completed_sessions' => intval($completed_sessions),
                'completion_rate' => $total_sessions > 0 ? round(($completed_sessions / $total_sessions) * 100, 1) : 0,
                'total_hours_planned' => floatval($total_hours_planned),
                'total_hours_realized' => floatval($total_hours_realized),
                'realization_rate' => $total_hours_planned > 0 ? round(($total_hours_realized / $total_hours_planned) * 100, 1) : 0
            ),
            'monthly_evolution' => $monthly_evolution,
            'top_schools' => $top_schools,
            'top_modules' => $top_modules,
            'top_trainers' => $top_trainers,
            'generated_at' => current_time('mysql'),
            'period' => array(
                'start' => $start_date,
                'end' => $end_date
            )
        );
        
        set_transient($cache_key, $report_data, $this->cache_duration);
        return $report_data;
    }
    
    /**
     * Rapport de performance des écoles
     */
    private function get_school_performance_report($start_date, $end_date, $filters) {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        // Données par école
        $schools_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.school_id,
                sch.post_title as school_name,
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN p.post_status = 'completed' THEN 1 END) as completed_sessions,
                COUNT(CASE WHEN p.post_status = 'cancelled' THEN 1 END) as cancelled_sessions,
                SUM(s.hours_planned) as hours_planned,
                SUM(s.hours_realized) as hours_realized,
                AVG(CASE WHEN p.post_status = 'completed' THEN 
                    DATEDIFF(s.start_datetime, p.post_date) 
                END) as avg_planning_lead_time,
                AVG(CASE WHEN fb.meta_value IS NOT NULL THEN fb.meta_value ELSE NULL END) as avg_satisfaction
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->posts} sch ON s.school_id = sch.ID
             LEFT JOIN {$wpdb->postmeta} fb ON p.ID = fb.post_id AND fb.meta_key = 'school_satisfaction_rating'
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             GROUP BY s.school_id
             ORDER BY completed_sessions DESC",
            $start_date, $end_date
        ));
        
        // Calculer les métriques de performance pour chaque école
        foreach ($schools_data as &$school) {
            $school->completion_rate = $school->total_sessions > 0 ? 
                round(($school->completed_sessions / $school->total_sessions) * 100, 1) : 0;
            $school->realization_rate = $school->hours_planned > 0 ? 
                round(($school->hours_realized / $school->hours_planned) * 100, 1) : 0;
            $school->cancellation_rate = $school->total_sessions > 0 ? 
                round(($school->cancelled_sessions / $school->total_sessions) * 100, 1) : 0;
            $school->performance_score = $this->calculate_school_performance_score($school);
        }
        
        return array(
            'schools_data' => $schools_data,
            'summary' => $this->calculate_schools_summary($schools_data),
            'benchmarks' => $this->get_performance_benchmarks($schools_data),
            'generated_at' => current_time('mysql'),
            'period' => array('start' => $start_date, 'end' => $end_date)
        );
    }
    
    /**
     * Rapport d'activité des formateurs
     */
    private function get_trainer_activity_report($start_date, $end_date, $filters) {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        $trainers_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.trainer_id,
                u.display_name as trainer_name,
                u.user_email as trainer_email,
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN p.post_status = 'completed' THEN 1 END) as completed_sessions,
                SUM(s.hours_realized) as total_hours,
                COUNT(DISTINCT s.school_id) as schools_count,
                COUNT(DISTINCT s.module_id) as modules_count,
                AVG(CASE WHEN fb.meta_value IS NOT NULL THEN fb.meta_value ELSE NULL END) as avg_rating,
                MIN(s.start_datetime) as first_session,
                MAX(s.start_datetime) as last_session
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->users} u ON s.trainer_id = u.ID
             LEFT JOIN {$wpdb->postmeta} fb ON p.ID = fb.post_id AND fb.meta_key = 'trainer_satisfaction_rating'
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND s.trainer_id IS NOT NULL
             GROUP BY s.trainer_id
             ORDER BY total_hours DESC",
            $start_date, $end_date
        ));
        
        // Données de disponibilité des formateurs
        $availability_stats = $this->get_trainer_availability_stats($start_date, $end_date);
        
        // Combiner les données
        foreach ($trainers_data as &$trainer) {
            $trainer->availability_rate = isset($availability_stats[$trainer->trainer_id]) 
                ? $availability_stats[$trainer->trainer_id] : 0;
            $trainer->utilization_rate = $this->calculate_trainer_utilization($trainer->trainer_id, $start_date, $end_date);
        }
        
        return array(
            'trainers_data' => $trainers_data,
            'summary' => $this->calculate_trainers_summary($trainers_data),
            'availability_overview' => $availability_stats,
            'generated_at' => current_time('mysql'),
            'period' => array('start' => $start_date, 'end' => $end_date)
        );
    }
    
    /**
     * Rapport de popularité des modules
     */
    private function get_module_popularity_report($start_date, $end_date, $filters) {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        $modules_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.module_id,
                m.post_title as module_name,
                m.post_content as module_description,
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN p.post_status = 'completed' THEN 1 END) as completed_sessions,
                COUNT(DISTINCT s.school_id) as schools_count,
                COUNT(DISTINCT s.trainer_id) as trainers_count,
                SUM(s.hours_planned) as total_hours_planned,
                SUM(s.hours_realized) as total_hours_realized,
                AVG(s.hours_realized / s.hours_planned * 100) as avg_completion_rate,
                AVG(CASE WHEN fb.meta_value IS NOT NULL THEN fb.meta_value ELSE NULL END) as avg_satisfaction
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->posts} m ON s.module_id = m.ID
             LEFT JOIN {$wpdb->postmeta} fb ON p.ID = fb.post_id AND fb.meta_key = 'module_satisfaction_rating'
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             GROUP BY s.module_id
             ORDER BY total_sessions DESC",
            $start_date, $end_date
        ));
        
        // Évolution par mois pour chaque module populaire (top 5)
        $top_modules_evolution = array();
        $top_5_modules = array_slice($modules_data, 0, 5);
        
        foreach ($top_5_modules as $module) {
            $evolution = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    DATE_FORMAT(s.start_datetime, '%%Y-%%m') as month,
                    COUNT(*) as sessions_count
                 FROM $sessions_table s
                 JOIN {$wpdb->posts} p ON s.session_id = p.ID
                 WHERE s.module_id = %d
                 AND s.start_datetime >= %s AND s.start_datetime <= %s
                 GROUP BY DATE_FORMAT(s.start_datetime, '%%Y-%%m')
                 ORDER BY month",
                $module->module_id, $start_date, $end_date
            ));
            
            $top_modules_evolution[$module->module_name] = $evolution;
        }
        
        return array(
            'modules_data' => $modules_data,
            'evolution_data' => $top_modules_evolution,
            'summary' => array(
                'total_modules_used' => count($modules_data),
                'most_popular' => $modules_data[0] ?? null,
                'avg_sessions_per_module' => round(array_sum(array_column($modules_data, 'total_sessions')) / count($modules_data), 1)
            ),
            'generated_at' => current_time('mysql'),
            'period' => array('start' => $start_date, 'end' => $end_date)
        );
    }
    
    /**
     * Rapport financier
     */
    private function get_financial_summary_report($start_date, $end_date, $filters) {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        // Calculer le CA basé sur les heures réalisées
        $hourly_rates = get_option('tmm_hourly_rates', array(
            'standard' => 150,
            'expert' => 200,
            'premium' => 250
        ));
        
        $financial_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.school_id,
                sch.post_title as school_name,
                s.module_id,
                m.post_title as module_name,
                COUNT(*) as sessions_count,
                SUM(s.hours_realized) as total_hours,
                SUM(s.hours_planned) as planned_hours
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             JOIN {$wpdb->posts} sch ON s.school_id = sch.ID
             JOIN {$wpdb->posts} m ON s.module_id = m.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'
             GROUP BY s.school_id, s.module_id",
            $start_date, $end_date
        ));
        
        $total_revenue = 0;
        $revenue_by_school = array();
        $revenue_by_module = array();
        
        foreach ($financial_data as $record) {
            // Récupérer le tarif du module (ou utiliser le tarif standard)
            $module_rate = get_post_meta($record->module_id, 'hourly_rate', true) ?: $hourly_rates['standard'];
            $revenue = $record->total_hours * $module_rate;
            
            $total_revenue += $revenue;
            
            if (!isset($revenue_by_school[$record->school_name])) {
                $revenue_by_school[$record->school_name] = 0;
            }
            $revenue_by_school[$record->school_name] += $revenue;
            
            if (!isset($revenue_by_module[$record->module_name])) {
                $revenue_by_module[$record->module_name] = 0;
            }
            $revenue_by_module[$record->module_name] += $revenue;
        }
        
        // Évolution mensuelle du CA
        $monthly_revenue = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(s.start_datetime, '%%Y-%%m') as month,
                SUM(s.hours_realized) as total_hours
             FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'
             GROUP BY DATE_FORMAT(s.start_datetime, '%%Y-%%m')
             ORDER BY month",
            $start_date, $end_date
        ));
        
        // Ajouter le CA calculé
        foreach ($monthly_revenue as &$month_data) {
            $month_data->revenue = $month_data->total_hours * $hourly_rates['standard'];
        }
        
        return array(
            'summary' => array(
                'total_revenue' => $total_revenue,
                'total_hours_billed' => array_sum(array_column($financial_data, 'total_hours')),
                'average_hourly_rate' => array_sum(array_column($financial_data, 'total_hours')) > 0 
                    ? round($total_revenue / array_sum(array_column($financial_data, 'total_hours')), 2) : 0,
                'sessions_count' => array_sum(array_column($financial_data, 'sessions_count'))
            ),
            'revenue_by_school' => $revenue_by_school,
            'revenue_by_module' => $revenue_by_module,
            'monthly_evolution' => $monthly_revenue,
            'detailed_data' => $financial_data,
            'generated_at' => current_time('mysql'),
            'period' => array('start' => $start_date, 'end' => $end_date)
        );
    }
    
    /**
     * Exporter un rapport
     */
    public function export_report() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        if (!current_user_can('export_tmm_reports')) {
            wp_send_json_error('Accès non autorisé');
        }
        
        $report_data = json_decode(stripslashes($_POST['report_data']), true);
        $format = sanitize_text_field($_POST['format']); // 'csv', 'excel', 'pdf'
        $report_type = sanitize_text_field($_POST['report_type']);
        
        switch ($format) {
            case 'csv':
                $file_url = $this->export_to_csv($report_data, $report_type);
                break;
            case 'excel':
                $file_url = $this->export_to_excel($report_data, $report_type);
                break;
            case 'pdf':
                $file_url = $this->export_to_pdf($report_data, $report_type);
                break;
            default:
                wp_send_json_error('Format non supporté');
        }
        
        if ($file_url) {
            wp_send_json_success(array('download_url' => $file_url));
        } else {
            wp_send_json_error('Erreur lors de l\'export');
        }
    }
    
    /**
     * Export CSV
     */
    private function export_to_csv($data, $report_type) {
        $upload_dir = wp_upload_dir();
        $filename = 'tmm_report_' . $report_type . '_' . date('Y-m-d_H-i-s') . '.csv';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        $file = fopen($file_path, 'w');
        
        // Headers CSV selon le type de rapport
        switch ($report_type) {
            case 'global_activity':
                fputcsv($file, array('Période', 'Sessions Totales', 'Sessions Terminées', 'Heures Planifiées', 'Heures Réalisées', 'Taux de Réalisation'));
                fputcsv($file, array(
                    $data['period']['start'] . ' - ' . $data['period']['end'],
                    $data['summary']['total_sessions'],
                    $data['summary']['completed_sessions'],
                    $data['summary']['total_hours_planned'],
                    $data['summary']['total_hours_realized'],
                    $data['summary']['realization_rate'] . '%'
                ));
                break;
                
            case 'school_performance':
                fputcsv($file, array('École', 'Sessions Totales', 'Sessions Terminées', 'Taux de Complétion', 'Heures Réalisées', 'Satisfaction Moyenne'));
                foreach ($data['schools_data'] as $school) {
                    fputcsv($file, array(
                        $school->school_name,
                        $school->total_sessions,
                        $school->completed_sessions,
                        $school->completion_rate . '%',
                        $school->hours_realized,
                        $school->avg_satisfaction ? round($school->avg_satisfaction, 1) : 'N/A'
                    ));
                }
                break;
        }
        
        fclose($file);
        
        return $upload_dir['url'] . '/' . $filename;
    }
    
    /**
     * Calculer le score de performance d'une école
     */
    private function calculate_school_performance_score($school) {
        $score = 0;
        
        // Taux de complétion (40% du score)
        $score += ($school->completion_rate / 100) * 40;
        
        // Taux de réalisation (30% du score)
        $score += ($school->realization_rate / 100) * 30;
        
        // Satisfaction (20% du score)
        if ($school->avg_satisfaction) {
            $score += ($school->avg_satisfaction / 5) * 20;
        }
        
        // Faible taux d'annulation (10% du score)
        $score += (1 - ($school->cancellation_rate / 100)) * 10;
        
        return round($score, 1);
    }
    
    /**
     * Obtenir les statistiques de disponibilité des formateurs
     */
    private function get_trainer_availability_stats($start_date, $end_date) {
        global $wpdb;
        
        $availability_table = $wpdb->prefix . 'tmm_availabilities';
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                user_id,
                COUNT(*) as total_slots,
                SUM(CASE WHEN availability_type = 'available' THEN 1 ELSE 0 END) as available_slots
             FROM $availability_table
             WHERE start_datetime >= %s AND start_datetime <= %s
             GROUP BY user_id",
            $start_date, $end_date
        ));
        
        $result = array();
        foreach ($stats as $stat) {
            $result[$stat->user_id] = $stat->available_slots > 0 
                ? round(($stat->available_slots / $stat->total_slots) * 100, 1) : 0;
        }
        
        return $result;
    }
    
    /**
     * Calculer le taux d'utilisation d'un formateur
     */
    private function calculate_trainer_utilization($trainer_id, $start_date, $end_date) {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        $availability_table = $wpdb->prefix . 'tmm_availabilities';
        
        // Heures travaillées
        $worked_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(hours_realized) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.trainer_id = %d 
             AND s.start_datetime >= %s AND s.start_datetime <= %s
             AND p.post_status = 'completed'",
            $trainer_id, $start_date, $end_date
        ));
        
        // Heures disponibles
        $available_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIMESTAMPDIFF(HOUR, start_datetime, end_datetime)) 
             FROM $availability_table
             WHERE user_id = %d 
             AND availability_type = 'available'
             AND start_datetime >= %s AND start_datetime <= %s",
            $trainer_id, $start_date, $end_date
        ));
        
        return $available_hours > 0 ? round(($worked_hours / $available_hours) * 100, 1) : 0;
    }
    
    /**
     * Rapport hebdomadaire automatique
     */
    public function send_weekly_report() {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-7 days'));
        
        $report_data = $this->get_global_activity_report($start_date, $end_date, array());
        
        // Récupérer les destinataires
        $recipients = get_users(array('role' => 'tmm_pedagog_manager'));
        
        foreach ($recipients as $user) {
            $this->send_report_email($user->user_email, 'weekly', $report_data);
        }
        
        do_action('tmm_weekly_report_sent', $report_data);
    }
    
    /**
     * Rapport mensuel automatique
     */
    public function send_monthly_report() {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        $report_data = $this->get_global_activity_report($start_date, $end_date, array());
        
        // Récupérer les destinataires
        $recipients = get_users(array('role' => 'tmm_pedagog_manager'));
        
        foreach ($recipients as $user) {
            $this->send_report_email($user->user_email, 'monthly', $report_data);
        }
        
        do_action('tmm_monthly_report_sent', $report_data);
    }
    
    /**
     * Envoyer un rapport par email
     */
    private function send_report_email($email, $type, $data) {
        $subject = sprintf(
            'Rapport %s TeachMeMore - %s',
            $type === 'weekly' ? 'hebdomadaire' : 'mensuel',
            date('d/m/Y')
        );
        
        $message = $this->build_report_email_template($type, $data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: TeachMeMore <noreply@teachmemore.fr>'
        );
        
        wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Template email pour les rapports
     */
    private function build_report_email_template($type, $data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .stat-box { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; }
                .stat-number { font-size: 24px; font-weight: bold; color: #667eea; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📊 Rapport <?php echo $type === 'weekly' ? 'Hebdomadaire' : 'Mensuel'; ?> TeachMeMore</h1>
                <p>Période: <?php echo $data['period']['start']; ?> - <?php echo $data['period']['end']; ?></p>
            </div>
            
            <div class="content">
                <h2>📈 Résumé des Activités</h2>
                
                <div class="stat-box">
                    <div class="stat-number"><?php echo $data['summary']['total_sessions']; ?></div>
                    <div>Sessions Total</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-number"><?php echo $data['summary']['completed_sessions']; ?></div>
                    <div>Sessions Terminées</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-number"><?php echo round($data['summary']['total_hours_realized']); ?>h</div>
                    <div>Heures Réalisées</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-number"><?php echo $data['summary']['realization_rate']; ?>%</div>
                    <div>Taux de Réalisation</div>
                </div>
                
                <?php if (!empty($data['top_schools'])): ?>
                <h3>🏆 Top Écoles Partenaires</h3>
                <ul>
                    <?php foreach (array_slice($data['top_schools'], 0, 5) as $school): ?>
                    <li><?php echo esc_html($school->school_name); ?> - <?php echo $school->sessions_count; ?> sessions</li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p>Ce rapport a été généré automatiquement par TeachMeMore PédagoConnect</p>
                <p>Pour plus de détails, connectez-vous à votre tableau de bord</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Obtenir les statistiques du dashboard
     */
    public function get_dashboard_stats() {
        check_ajax_referer('tmm_nonce', 'nonce');
        
        $period = sanitize_text_field($_POST['period']) ?: '30'; // 30 jours par défaut
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$period} days"));
        
        $stats = $this->get_global_activity_report($start_date, $end_date, array());
        
        wp_send_json_success($stats['summary']);
    }
}

// Initialiser la classe
new TMM_Reporting();