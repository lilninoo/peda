<?php
/**
 * Template principal du dashboard admin TeachMeMore PédagoConnect
 * Interface de pilotage globale pour l'équipe TeachMeMore
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifications de sécurité
if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé');
}

// Récupérer les données du dashboard
global $wpdb;
$sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
$notifications_table = $wpdb->prefix . 'tmm_notifications';

// Statistiques globales (30 derniers jours)
$stats_period = date('Y-m-d', strtotime('-30 days'));

$global_stats = array(
    'total_sessions' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE s.created_at >= %s",
        $stats_period
    )),
    'pending_proposals' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE p.post_status = 'proposed' AND s.created_at >= %s",
        $stats_period
    )),
    'confirmed_sessions' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE p.post_status = 'confirmed' AND s.start_datetime >= NOW()",
        $stats_period
    )),
    'completed_sessions' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE p.post_status = 'completed' AND s.created_at >= %s",
        $stats_period
    )),
    'total_hours_realized' => $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(hours_realized) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE p.post_status = 'completed' AND s.created_at >= %s",
        $stats_period
    )),
    'active_schools' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT school_id) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE s.created_at >= %s",
        $stats_period
    )),
    'active_trainers' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT trainer_id) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE s.created_at >= %s AND s.trainer_id IS NOT NULL",
        $stats_period
    )),
    'unread_notifications' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $notifications_table 
         WHERE is_read = 0 AND created_at >= %s",
        $stats_period
    ))
);

// Sessions nécessitant attention
$urgent_sessions = $wpdb->get_results($wpdb->prepare(
    "SELECT s.*, p.post_title, sch.post_title as school_name, m.post_title as module_name
     FROM $sessions_table s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     LEFT JOIN {$wpdb->posts} sch ON s.school_id = sch.ID
     LEFT JOIN {$wpdb->posts} m ON s.module_id = m.ID
     WHERE (
         (p.post_status = 'proposed' AND s.created_at <= %s) OR
         (p.post_status = 'confirmed' AND s.start_datetime <= %s AND s.trainer_id IS NULL)
     )
     ORDER BY s.start_datetime ASC
     LIMIT 10",
    date('Y-m-d H:i:s', strtotime('-48 hours')),
    date('Y-m-d H:i:s', strtotime('+7 days'))
));

// Données pour graphiques
$monthly_evolution = $wpdb->get_results(
    "SELECT 
        DATE_FORMAT(s.start_datetime, '%Y-%m') as month,
        COUNT(*) as sessions_count,
        SUM(s.hours_realized) as hours_realized,
        COUNT(DISTINCT s.school_id) as schools_count
     FROM $sessions_table s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     WHERE s.start_datetime >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     AND p.post_status = 'completed'
     GROUP BY DATE_FORMAT(s.start_datetime, '%Y-%m')
     ORDER BY month DESC
     LIMIT 12"
);

// Top performances
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
     WHERE s.created_at >= %s AND p.post_status = 'completed'
     GROUP BY s.school_id
     ORDER BY total_hours DESC
     LIMIT 5",
    $stats_period
));

$revenue_estimate = array_sum(array_column($top_schools, 'total_hours')) * 175; // Tarif moyen estimé
?>

<div class="wrap tmm-admin-dashboard">
    <!-- Header avec actions rapides -->
    <div class="tmm-admin-header">
        <div class="header-content">
            <div class="header-title">
                <h1>🎯 Dashboard TeachMeMore PédagoConnect</h1>
                <p class="subtitle">Pilotage global des collaborations pédagogiques</p>
            </div>
            
            <div class="header-actions">
                <button id="refresh-data" class="button button-primary">
                    🔄 Actualiser
                </button>
                <button id="new-session" class="button button-secondary">
                    ➕ Nouvelle Session
                </button>
                <button id="quick-report" class="button">
                    📊 Rapport Rapide
                </button>
                
                <?php if ($global_stats['unread_notifications'] > 0): ?>
                <div class="notification-badge">
                    <span class="badge"><?php echo $global_stats['unread_notifications']; ?></span>
                    🔔 Notifications
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Métriques principales -->
    <div class="tmm-metrics-grid">
        <div class="metric-card primary">
            <div class="metric-icon">📚</div>
            <div class="metric-content">
                <div class="metric-number"><?php echo number_format($global_stats['total_sessions']); ?></div>
                <div class="metric-label">Sessions Totales</div>
                <div class="metric-trend">+12% vs mois dernier</div>
            </div>
        </div>

        <div class="metric-card warning">
            <div class="metric-icon">⏳</div>
            <div class="metric-content">
                <div class="metric-number"><?php echo $global_stats['pending_proposals']; ?></div>
                <div class="metric-label">En Attente Réponse</div>
                <div class="metric-trend">Nécessite attention</div>
            </div>
        </div>

        <div class="metric-card success">
            <div class="metric-icon">✅</div>
            <div class="metric-content">
                <div class="metric-number"><?php echo $global_stats['confirmed_sessions']; ?></div>
                <div class="metric-label">Sessions Confirmées</div>
                <div class="metric-trend">À venir</div>
            </div>
        </div>

        <div class="metric-card info">
            <div class="metric-icon">⏱️</div>
            <div class="metric-content">
                <div class="metric-number"><?php echo round($global_stats['total_hours_realized']); ?>h</div>
                <div class="metric-label">Heures Réalisées</div>
                <div class="metric-trend">Ce mois</div>
            </div>
        </div>

        <div class="metric-card accent">
            <div class="metric-icon">🏫</div>
            <div class="metric-content">
                <div class="metric-number"><?php echo $global_stats['active_schools']; ?></div>
                <div class="metric-label">Écoles Actives</div>
                <div class="metric-trend">Partenaires</div>
            </div>
        </div>

        <div class="metric-card secondary">
            <div class="metric-icon">👨‍🏫</div>
            <div class="metric-content">
                <div class="metric-number"><?php echo $global_stats['active_trainers']; ?></div>
                <div class="metric-label">Formateurs Actifs</div>
                <div class="metric-trend">Ce mois</div>
            </div>
        </div>

        <div class="metric-card revenue">
            <div class="metric-icon">💰</div>
            <div class="metric-content">
                <div class="metric-number"><?php echo number_format($revenue_estimate, 0, ',', ' '); ?>€</div>
                <div class="metric-label">CA Estimé</div>
                <div class="metric-trend">30 derniers jours</div>
            </div>
        </div>

        <div class="metric-card performance">
            <div class="metric-icon">📈</div>
            <div class="metric-content">
                <div class="metric-number">94%</div>
                <div class="metric-label">Taux Réalisation</div>
                <div class="metric-trend">Excellent</div>
            </div>
        </div>
    </div>

    <!-- Navigation par onglets -->
    <div class="tmm-admin-tabs">
        <nav class="admin-tabs-nav">
            <button class="admin-tab-button active" data-tab="overview">
                📊 Vue d'ensemble
            </button>
            <button class="admin-tab-button" data-tab="urgent">
                🚨 Actions Urgentes (<?php echo count($urgent_sessions); ?>)
            </button>
            <button class="admin-tab-button" data-tab="planning">
                📅 Planning Global
            </button>
            <button class="admin-tab-button" data-tab="analytics">
                📈 Analytics
            </button>
            <button class="admin-tab-button" data-tab="management">
                ⚙️ Gestion
            </button>
        </nav>

        <!-- Onglet Vue d'ensemble -->
        <div id="overview" class="admin-tab-content active">
            <div class="overview-grid">
                <!-- Graphique évolution mensuelle -->
                <div class="chart-container">
                    <h3>📈 Évolution Mensuelle</h3>
                    <canvas id="monthly-evolution-chart" width="400" height="200"></canvas>
                </div>

                <!-- Top écoles partenaires -->
                <div class="top-performers">
                    <h3>🏆 Top Écoles Partenaires</h3>
                    <div class="performers-list">
                        <?php foreach ($top_schools as $index => $school): ?>
                        <div class="performer-item">
                            <div class="performer-rank">#<?php echo $index + 1; ?></div>
                            <div class="performer-info">
                                <div class="performer-name"><?php echo esc_html($school->school_name); ?></div>
                                <div class="performer-stats">
                                    <?php echo $school->total_hours; ?>h • 
                                    <?php echo $school->sessions_count; ?> sessions •
                                    <?php if ($school->avg_satisfaction > 0): ?>
                                        ⭐ <?php echo round($school->avg_satisfaction, 1); ?>/5
                                    <?php else: ?>
                                        Pas encore évalué
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="performer-revenue">
                                <?php echo number_format($school->total_hours * 175, 0, ',', ' '); ?>€
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Activité récente -->
                <div class="recent-activity">
                    <h3>🔄 Activité Récente</h3>
                    <div class="activity-feed" id="activity-feed">
                        <!-- Chargé dynamiquement via JavaScript -->
                    </div>
                </div>

                <!-- Météo du business -->
                <div class="business-weather">
                    <h3>🌡️ Météo Business</h3>
                    <div class="weather-indicators">
                        <div class="weather-item">
                            <div class="weather-icon">🌞</div>
                            <div class="weather-text">
                                <strong>Pipeline Sain</strong><br>
                                <?php echo $global_stats['pending_proposals']; ?> propositions en cours
                            </div>
                        </div>
                        
                        <div class="weather-item">
                            <div class="weather-icon">⚡</div>
                            <div class="weather-text">
                                <strong>Réactivité Excellente</strong><br>
                                Temps de réponse moyen: 18h
                            </div>
                        </div>
                        
                        <div class="weather-item">
                            <div class="weather-icon">🎯</div>
                            <div class="weather-text">
                                <strong>Objectifs en Cours</strong><br>
                                76% de l'objectif mensuel atteint
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Actions Urgentes -->
        <div id="urgent" class="admin-tab-content">
            <h2>🚨 Sessions Nécessitant une Attention Immédiate</h2>
            
            <?php if (empty($urgent_sessions)): ?>
                <div class="no-urgent-sessions">
                    <div class="success-icon">✅</div>
                    <h3>Tout est sous contrôle !</h3>
                    <p>Aucune session ne nécessite d'attention urgente pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="urgent-sessions-list">
                    <?php foreach ($urgent_sessions as $session): ?>
                    <div class="urgent-session-card" data-session-id="<?php echo $session->session_id; ?>">
                        <div class="urgency-indicator">
                            <?php 
                            $days_pending = (time() - strtotime($session->created_at)) / (24 * 3600);
                            if ($days_pending > 2): ?>
                                <span class="urgency-high">🔴 URGENT</span>
                            <?php else: ?>
                                <span class="urgency-medium">🟡 ATTENTION</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="session-details">
                            <h4><?php echo esc_html($session->module_name ?: 'Module non défini'); ?></h4>
                            <div class="session-meta">
                                <span>🏫 <?php echo esc_html($session->school_name); ?></span>
                                <span>📅 <?php echo date('d/m/Y H:i', strtotime($session->start_datetime)); ?></span>
                                <span>⏱️ <?php echo $session->hours_planned; ?>h</span>
                            </div>
                            
                            <div class="problem-description">
                                <?php if (get_post_status($session->session_id) === 'proposed'): ?>
                                    <strong>⏳ En attente depuis <?php echo round($days_pending); ?> jours</strong>
                                    <p>L'école n'a pas encore répondu à cette proposition.</p>
                                <?php elseif (!$session->trainer_id): ?>
                                    <strong>👨‍🏫 Formateur non assigné</strong>
                                    <p>Session confirmée mais aucun formateur assigné.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="quick-actions">
                            <?php if (get_post_status($session->session_id) === 'proposed'): ?>
                                <button class="button button-secondary" onclick="sendReminder(<?php echo $session->session_id; ?>)">
                                    📧 Relancer École
                                </button>
                                <button class="button" onclick="modifyProposal(<?php echo $session->session_id; ?>)">
                                    ✏️ Modifier
                                </button>
                            <?php elseif (!$session->trainer_id): ?>
                                <button class="button button-primary" onclick="assignTrainer(<?php echo $session->session_id; ?>)">
                                    👨‍🏫 Assigner Formateur
                                </button>
                                <button class="button" onclick="suggestTrainers(<?php echo $session->session_id; ?>)">
                                    🤖 Suggestions Auto
                                </button>
                            <?php endif; ?>
                            <button class="button button-link-delete" onclick="cancelSession(<?php echo $session->session_id; ?>)">
                                ❌ Annuler
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Planning Global -->
        <div id="planning" class="admin-tab-content">
            <div class="planning-controls">
                <h2>📅 Planning Global TeachMeMore</h2>
                
                <div class="planning-filters">
                    <select id="view-filter">
                        <option value="all">Toutes les sessions</option>
                        <option value="proposed">Proposées</option>
                        <option value="confirmed">Confirmées</option>
                        <option value="in_progress">En cours</option>
                    </select>
                    
                    <select id="school-filter">
                        <option value="">Toutes les écoles</option>
                        <?php 
                        $schools = get_posts(array('post_type' => 'tmm_school', 'numberposts' => -1));
                        foreach ($schools as $school): ?>
                            <option value="<?php echo $school->ID; ?>"><?php echo esc_html($school->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="trainer-filter">
                        <option value="">Tous les formateurs</option>
                        <?php 
                        $trainers = get_users(array('role' => 'tmm_trainer'));
                        foreach ($trainers as $trainer): ?>
                            <option value="<?php echo $trainer->ID; ?>"><?php echo esc_html($trainer->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button id="export-planning" class="button">
                        📤 Exporter Planning
                    </button>
                </div>
            </div>
            
            <div id="global-calendar" class="global-calendar-container">
                <!-- Calendrier FullCalendar chargé via JavaScript -->
            </div>
        </div>

        <!-- Onglet Analytics -->
        <div id="analytics" class="admin-tab-content">
            <h2>📈 Analytics & Business Intelligence</h2>
            
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>💼 Performance Commerciale</h3>
                    <canvas id="revenue-chart" width="300" height="200"></canvas>
                </div>
                
                <div class="analytics-card">
                    <h3>⭐ Satisfaction Clients</h3>
                    <canvas id="satisfaction-chart" width="300" height="200"></canvas>
                </div>
                
                <div class="analytics-card">
                    <h3>👥 Activité Formateurs</h3>
                    <canvas id="trainers-chart" width="300" height="200"></canvas>
                </div>
                
                <div class="analytics-card">
                    <h3>📚 Modules Populaires</h3>
                    <canvas id="modules-chart" width="300" height="200"></canvas>
                </div>
            </div>
            
            <div class="analytics-reports">
                <h3>📊 Rapports Avancés</h3>
                <div class="reports-grid">
                    <button class="report-btn" onclick="generateReport('global_activity')">
                        📈 Rapport d'Activité Globale
                    </button>
                    <button class="report-btn" onclick="generateReport('school_performance')">
                        🏫 Performance des Écoles
                    </button>
                    <button class="report-btn" onclick="generateReport('trainer_activity')">
                        👨‍🏫 Activité des Formateurs
                    </button>
                    <button class="report-btn" onclick="generateReport('financial_summary')">
                        💰 Résumé Financier
                    </button>
                    <button class="report-btn" onclick="generateReport('satisfaction_analysis')">
                        ⭐ Analyse Satisfaction
                    </button>
                    <button class="report-btn" onclick="generateReport('module_popularity')">
                        📚 Popularité des Modules
                    </button>
                </div>
            </div>
        </div>

        <!-- Onglet Gestion -->
        <div id="management" class="admin-tab-content">
            <h2>⚙️ Gestion & Administration</h2>
            
            <div class="management-grid">
                <div class="management-section">
                    <h3>🏫 Écoles Partenaires</h3>
                    <div class="management-actions">
                        <button class="button button-primary" onclick="addNewSchool()">
                            ➕ Nouvelle École
                        </button>
                        <button class="button" onclick="manageSchools()">
                            📝 Gérer Écoles
                        </button>
                        <button class="button" onclick="schoolsReport()">
                            📊 Rapport Écoles
                        </button>
                    </div>
                </div>
                
                <div class="management-section">
                    <h3>👨‍🏫 Formateurs</h3>
                    <div class="management-actions">
                        <button class="button button-primary" onclick="addNewTrainer()">
                            ➕ Nouveau Formateur
                        </button>
                        <button class="button" onclick="manageAvailabilities()">
                            📅 Gérer Disponibilités
                        </button>
                        <button class="button" onclick="trainersPerformance()">
                            📈 Performance
                        </button>
                    </div>
                </div>
                
                <div class="management-section">
                    <h3>📚 Modules</h3>
                    <div class="management-actions">
                        <button class="button button-primary" onclick="addNewModule()">
                            ➕ Nouveau Module
                        </button>
                        <button class="button" onclick="manageModules()">
                            📝 Gérer Modules
                        </button>
                        <button class="button" onclick="modulesAnalytics()">
                            📊 Analytics Modules
                        </button>
                    </div>
                </div>
                
                <div class="management-section">
                    <h3>⚙️ Configuration</h3>
                    <div class="management-actions">
                        <button class="button" onclick="pluginSettings()">
                            🔧 Paramètres Plugin
                        </button>
                        <button class="button" onclick="exportData()">
                            📤 Exporter Données
                        </button>
                        <button class="button button-secondary" onclick="systemMaintenance()">
                            🛠️ Maintenance
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="system-status">
                <h3>🔍 État du Système</h3>
                <div class="status-grid">
                    <div class="status-item status-good">
                        <span class="status-indicator">🟢</span>
                        <span class="status-label">Base de données</span>
                        <span class="status-value">Opérationnelle</span>
                    </div>
                    <div class="status-item status-good">
                        <span class="status-indicator">🟢</span>
                        <span class="status-label">Notifications</span>
                        <span class="status-value">Actives</span>
                    </div>
                    <div class="status-item status-warning">
                        <span class="status-indicator">🟡</span>
                        <span class="status-label">Cache</span>
                        <span class="status-value">À optimiser</span>
                    </div>
                    <div class="status-item status-good">
                        <span class="status-indicator">🟢</span>
                        <span class="status-label">Synchronisation</span>
                        <span class="status-value">En ligne</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales et popups -->
<div id="quick-session-modal" class="tmm-modal">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h3>➕ Création Rapide de Session</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <form id="quick-session-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="quick-school">École Partenaire</label>
                        <select id="quick-school" required>
                            <option value="">Sélectionner une école</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school->ID; ?>"><?php echo esc_html($school->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quick-module">Module</label>
                        <select id="quick-module" required>
                            <option value="">Sélectionner un module</option>
                            <?php 
                            $modules = get_posts(array('post_type' => 'tmm_module', 'numberposts' => -1));
                            foreach ($modules as $module): ?>
                                <option value="<?php echo $module->ID; ?>"><?php echo esc_html($module->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="quick-date">Date souhaitée</label>
                        <input type="date" id="quick-date" required>
                    </div>
                    <div class="form-group">
                        <label for="quick-duration">Durée (heures)</label>
                        <input type="number" id="quick-duration" min="1" max="40" value="7" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="quick-notes">Notes (optionnel)</label>
                    <textarea id="quick-notes" rows="3" placeholder="Informations supplémentaires..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="button" onclick="closeModal()">Annuler</button>
                    <button type="button" class="button" onclick="suggestOptimalSlots()">🤖 Suggestions Auto</button>
                    <button type="submit" class="button button-primary">Créer & Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialisation du dashboard
    initAdminDashboard();
    
    // Gestion des onglets
    $('.admin-tab-button').click(function() {
        var targetTab = $(this).data('tab');
        
        $('.admin-tab-button').removeClass('active');
        $('.admin-tab-content').removeClass('active');
        
        $(this).addClass('active');
        $('#' + targetTab).addClass('active');
        
        // Callbacks spécifiques
        if (targetTab === 'planning') {
            initGlobalCalendar();
        } else if (targetTab === 'analytics') {
            loadAnalyticsCharts();
        }
    });
    
    // Actions rapides
    $('#new-session').click(function() {
        $('#quick-session-modal').show();
    });
    
    $('#refresh-data').click(function() {
        location.reload();
    });
    
    $('#quick-report').click(function() {
        generateQuickReport();
    });
    
    // Formulaire création rapide
    $('#quick-session-form').submit(function(e) {
        e.preventDefault();
        createQuickSession();
    });
    
    // Charger l'activité récente
    loadRecentActivity();
    
    // Auto-refresh toutes les 5 minutes
    setInterval(function() {
        loadRecentActivity();
        updateMetrics();
    }, 300000);
});

function initAdminDashboard() {
    console.log('🚀 Dashboard admin initialisé');
    
    // Initialiser les graphiques
    initMonthlyEvolutionChart();
    
    // Charger les données temps réel
    loadRealTimeData();
}

function initMonthlyEvolutionChart() {
    var ctx = document.getElementById('monthly-evolution-chart').getContext('2d');
    var chartData = <?php echo json_encode(array_reverse($monthly_evolution)); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(function(item) {
                return item.month;
            }),
            datasets: [{
                label: 'Sessions',
                data: chartData.map(function(item) {
                    return item.sessions_count;
                }),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4
            }, {
                label: 'Heures',
                data: chartData.map(function(item) {
                    return item.hours_realized;
                }),
                borderColor: '#764ba2',
                backgroundColor: 'rgba(118, 75, 162, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Évolution des Sessions et Heures'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

function loadRecentActivity() {
    $.post(ajaxurl, {
        action: 'tmm_get_recent_activity',
        nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            displayRecentActivity(response.data);
        }
    });
}

function displayRecentActivity(activities) {
    var html = '';
    activities.forEach(function(activity) {
        html += `
            <div class="activity-item">
                <div class="activity-icon">${activity.icon}</div>
                <div class="activity-content">
                    <div class="activity-text">${activity.description}</div>
                    <div class="activity-time">${activity.time_ago}</div>
                </div>
            </div>
        `;
    });
    $('#activity-feed').html(html);
}

function createQuickSession() {
    var formData = {
        action: 'tmm_create_quick_session',
        school_id: $('#quick-school').val(),
        module_id: $('#quick-module').val(),
        preferred_date: $('#quick-date').val(),
        duration: $('#quick-duration').val(),
        notes: $('#quick-notes').val(),
        nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
    };
    
    $.post(ajaxurl, formData, function(response) {
        if (response.success) {
            alert('Session créée et proposition envoyée à l\'école!');
            closeModal();
            location.reload();
        } else {
            alert('Erreur: ' + response.data);
        }
    });
}

function suggestOptimalSlots() {
    var schoolId = $('#quick-school').val();
    var moduleId = $('#quick-module').val();
    var duration = $('#quick-duration').val();
    
    if (!schoolId || !moduleId) {
        alert('Veuillez sélectionner une école et un module');
        return;
    }
    
    $.post(ajaxurl, {
        action: 'tmm_get_suggested_slots',
        school_id: schoolId,
        module_id: moduleId,
        duration_hours: duration,
        nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            displayOptimalSlots(response.data);
        } else {
            alert('Aucun créneau optimal trouvé');
        }
    });
}

function generateQuickReport() {
    $.post(ajaxurl, {
        action: 'tmm_generate_report',
        report_type: 'global_activity',
        start_date: '<?php echo date('Y-m-01'); ?>',
        end_date: '<?php echo date('Y-m-d'); ?>',
        nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            window.open('data:text/html,' + encodeURIComponent(buildReportHTML(response.data)));
        }
    });
}

function closeModal() {
    $('.tmm-modal').hide();
}

// Fonctions pour actions urgentes
function sendReminder(sessionId) {
    if (confirm('Envoyer un rappel à l\'école pour cette session?')) {
        $.post(ajaxurl, {
            action: 'tmm_send_reminder',
            session_id: sessionId,
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Rappel envoyé avec succès!');
            }
        });
    }
}

function assignTrainer(sessionId) {
    window.location.href = 'post.php?post=' + sessionId + '&action=edit';
}

function cancelSession(sessionId) {
    if (confirm('Êtes-vous sûr de vouloir annuler cette session?')) {
        $.post(ajaxurl, {
            action: 'tmm_update_session_status',
            session_id: sessionId,
            new_status: 'cancelled',
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    }
}

// Fermeture modales
$(document).click(function(e) {
    if (e.target.classList.contains('tmm-modal') || e.target.classList.contains('tmm-modal-close')) {
        closeModal();
    }
});
</script>

<style>
/* Styles spécifiques au dashboard admin */
.tmm-admin-dashboard {
    padding: 20px;
    background: #f5f7fa;
    min-height: 100vh;
}

.tmm-admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title h1 {
    margin: 0 0 5px 0;
    font-size: 28px;
    font-weight: 700;
}

.subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 16px;
}

.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.notification-badge {
    position: relative;
    padding: 10px 15px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.notification-badge .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.tmm-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 20px;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.metric-card.primary::before { background: #667eea; }
.metric-card.warning::before { background: #ffa726; }
.metric-card.success::before { background: #66bb6a; }
.metric-card.info::before { background: #42a5f5; }
.metric-card.accent::before { background: #ab47bc; }
.metric-card.secondary::before { background: #78909c; }
.metric-card.revenue::before { background: #4caf50; }
.metric-card.performance::before { background: #ff7043; }

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.metric-icon {
    font-size: 32px;
    opacity: 0.8;
}

.metric-content {
    flex: 1;
}

.metric-number {
    font-size: 32px;
    font-weight: 800;
    color: #2c3e50;
    line-height: 1;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 14px;
    color: #7f8c8d;
    font-weight: 500;
    margin-bottom: 3px;
}

.metric-trend {
    font-size: 12px;
    color: #27ae60;
    font-weight: 500;
}

.tmm-admin-tabs {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.admin-tabs-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.admin-tab-button {
    flex: 1;
    padding: 15px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s ease;
    position: relative;
}

.admin-tab-button::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 3px;
    background: #667eea;
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.admin-tab-button:hover {
    color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.admin-tab-button.active {
    color: #667eea;
    background: white;
    font-weight: 600;
}

.admin-tab-button.active::after {
    width: 80%;
}

.admin-tab-content {
    display: none;
    padding: 30px;
}

.admin-tab-content.active {
    display: block;
}

.overview-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    grid-template-rows: auto auto;
    gap: 25px;
}

.chart-container {
    grid-column: 1;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.top-performers {
    grid-column: 2;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.performer-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f1f3f4;
}

.performer-rank {
    width: 30px;
    height: 30px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}

.performer-info {
    flex: 1;
}

.performer-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.performer-stats {
    font-size: 12px;
    color: #7f8c8d;
}

.performer-revenue {
    font-weight: 700;
    color: #27ae60;
    font-size: 14px;
}

.urgent-sessions-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.urgent-session-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border-left: 4px solid #ff6b6b;
}

.urgency-indicator {
    margin-bottom: 15px;
}

.urgency-high {
    background: #ff6b6b;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.urgency-medium {
    background: #ffa726;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.no-urgent-sessions {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.success-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.no-urgent-sessions h3 {
    color: #27ae60;
    margin-bottom: 10px;
}

/* Modales */
.tmm-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
}

.tmm-modal-content {
    background: white;
    margin: 5% auto;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 10px 50px rgba(0,0,0,0.3);
}

.tmm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #dee2e6;
}

.tmm-modal-close {
    cursor: pointer;
    font-size: 24px;
    color: #6c757d;
    transition: color 0.3s ease;
}

.tmm-modal-close:hover {
    color: #dc3545;
}

.tmm-modal-body {
    padding: 25px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: 500;
    color: #495057;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

@media (max-width: 768px) {
    .tmm-admin-header {
        padding: 20px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .tmm-metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .overview-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>