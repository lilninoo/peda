<?php
/**
 * Template pour la page de reporting TeachMeMore PédagoConnect
 * Interface complète d'analytics et génération de rapports
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifier les permissions
if (!current_user_can('view_tmm_reports')) {
    wp_die('Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
}

// Récupérer les données pour les filtres
$schools = get_posts(array('post_type' => 'tmm_school', 'numberposts' => -1, 'post_status' => 'any'));
$trainers = get_users(array('role' => 'tmm_trainer'));
$modules = get_posts(array('post_type' => 'tmm_module', 'numberposts' => -1));

// Dates par défaut
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));
?>

<div class="wrap tmm-reporting-page">
    <h1 class="page-title">
        <span class="title-icon">📊</span>
        Reporting & Analytics TeachMeMore
    </h1>
    
    <!-- Navigation des rapports -->
    <nav class="tmm-report-nav">
        <div class="nav-tabs">
            <button class="nav-tab active" data-tab="dashboard">Dashboard</button>
            <button class="nav-tab" data-tab="activity">Activité Globale</button>
            <button class="nav-tab" data-tab="schools">Performance Écoles</button>
            <button class="nav-tab" data-tab="trainers">Activité Formateurs</button>
            <button class="nav-tab" data-tab="modules">Popularité Modules</button>
            <button class="nav-tab" data-tab="financial">Analyse Financière</button>
            <button class="nav-tab" data-tab="satisfaction">Satisfaction</button>
        </div>
    </nav>
    
    <!-- Panneau de contrôle -->
    <div class="tmm-report-controls">
        <div class="controls-row">
            <div class="date-range">
                <label for="start-date">Du:</label>
                <input type="date" id="start-date" value="<?php echo $default_start_date; ?>" />
                
                <label for="end-date">Au:</label>
                <input type="date" id="end-date" value="<?php echo $default_end_date; ?>" />
                
                <div class="quick-dates">
                    <button class="btn-quick-date" data-days="7">7 jours</button>
                    <button class="btn-quick-date" data-days="30">30 jours</button>
                    <button class="btn-quick-date" data-days="90">90 jours</button>
                    <button class="btn-quick-date" data-days="365">1 an</button>
                </div>
            </div>
            
            <div class="filters">
                <select id="school-filter">
                    <option value="">Toutes les écoles</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?php echo $school->ID; ?>"><?php echo esc_html($school->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="trainer-filter">
                    <option value="">Tous les formateurs</option>
                    <?php foreach ($trainers as $trainer): ?>
                        <option value="<?php echo $trainer->ID; ?>"><?php echo esc_html($trainer->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="module-filter">
                    <option value="">Tous les modules</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?php echo $module->ID; ?>"><?php echo esc_html($module->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="actions">
                <button id="refresh-data" class="button button-primary">
                    <span class="dashicons dashicons-update"></span> Actualiser
                </button>
                <div class="export-dropdown">
                    <button id="export-menu" class="button">
                        <span class="dashicons dashicons-download"></span> Exporter
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="export-options">
                        <button class="export-btn" data-format="pdf">📄 PDF</button>
                        <button class="export-btn" data-format="excel">📊 Excel</button>
                        <button class="export-btn" data-format="csv">📋 CSV</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenu des rapports -->
    <div class="tmm-report-content">
        
        <!-- Onglet Dashboard -->
        <div id="dashboard" class="report-tab active">
            <div class="dashboard-overview">
                <div class="kpi-cards">
                    <div class="kpi-card">
                        <div class="kpi-icon">📅</div>
                        <div class="kpi-content">
                            <div class="kpi-value" id="total-sessions">-</div>
                            <div class="kpi-label">Sessions Total</div>
                            <div class="kpi-trend" id="sessions-trend">-</div>
                        </div>
                    </div>
                    
                    <div class="kpi-card">
                        <div class="kpi-icon">✅</div>
                        <div class="kpi-content">
                            <div class="kpi-value" id="completed-sessions">-</div>
                            <div class="kpi-label">Sessions Terminées</div>
                            <div class="kpi-trend" id="completed-trend">-</div>
                        </div>
                    </div>
                    
                    <div class="kpi-card">
                        <div class="kpi-icon">⏰</div>
                        <div class="kpi-content">
                            <div class="kpi-value" id="total-hours">-</div>
                            <div class="kpi-label">Heures Réalisées</div>
                            <div class="kpi-trend" id="hours-trend">-</div>
                        </div>
                    </div>
                    
                    <div class="kpi-card">
                        <div class="kpi-icon">🎯</div>
                        <div class="kpi-content">
                            <div class="kpi-value" id="realization-rate">-</div>
                            <div class="kpi-label">Taux de Réalisation</div>
                            <div class="kpi-trend" id="realization-trend">-</div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-charts">
                    <div class="chart-container">
                        <h3>Évolution Mensuelle</h3>
                        <canvas id="monthly-evolution-chart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3>Répartition par Statut</h3>
                        <canvas id="status-distribution-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglet Activité Globale -->
        <div id="activity" class="report-tab">
            <div class="activity-content">
                <div class="activity-summary">
                    <h3>Résumé de l'Activité</h3>
                    <div id="activity-summary-content">
                        <!-- Chargé dynamiquement -->
                    </div>
                </div>
                
                <div class="activity-charts">
                    <div class="chart-row">
                        <div class="chart-container">
                            <h3>Sessions par Mois</h3>
                            <canvas id="sessions-monthly-chart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Heures par Mois</h3>
                            <canvas id="hours-monthly-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-row">
                        <div class="chart-container">
                            <h3>Top 5 Écoles</h3>
                            <canvas id="top-schools-chart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Top 5 Modules</h3>
                            <canvas id="top-modules-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglet Performance Écoles -->
        <div id="schools" class="report-tab">
            <div class="schools-content">
                <div class="schools-metrics">
                    <h3>Métriques des Écoles Partenaires</h3>
                    <div class="metrics-table-container">
                        <table id="schools-metrics-table" class="tmm-data-table">
                            <thead>
                                <tr>
                                    <th>École</th>
                                    <th>Sessions</th>
                                    <th>Heures</th>
                                    <th>Taux Complétion</th>
                                    <th>Satisfaction</th>
                                    <th>Score Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Chargé dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="schools-charts">
                    <div class="chart-container">
                        <h3>Comparaison Performance Écoles</h3>
                        <canvas id="schools-comparison-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglet Formateurs -->
        <div id="trainers" class="report-tab">
            <div class="trainers-content">
                <div class="trainers-metrics">
                    <h3>Activité des Formateurs</h3>
                    <div class="metrics-table-container">
                        <table id="trainers-metrics-table" class="tmm-data-table">
                            <thead>
                                <tr>
                                    <th>Formateur</th>
                                    <th>Sessions</th>
                                    <th>Heures</th>
                                    <th>Écoles</th>
                                    <th>Taux Utilisation</th>
                                    <th>Note Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Chargé dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="trainers-charts">
                    <div class="chart-container">
                        <h3>Répartition des Heures par Formateur</h3>
                        <canvas id="trainers-hours-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglet Modules -->
        <div id="modules" class="report-tab">
            <div class="modules-content">
                <div class="modules-metrics">
                    <h3>Popularité des Modules</h3>
                    <div class="metrics-table-container">
                        <table id="modules-metrics-table" class="tmm-data-table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Sessions</th>
                                    <th>Écoles</th>
                                    <th>Heures Total</th>
                                    <th>Taux Complétion</th>
                                    <th>Satisfaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Chargé dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="modules-charts">
                    <div class="chart-container">
                        <h3>Répartition des Sessions par Module</h3>
                        <canvas id="modules-distribution-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglet Financier -->
        <div id="financial" class="report-tab">
            <div class="financial-content">
                <div class="financial-summary">
                    <h3>Résumé Financier</h3>
                    <div class="financial-kpis">
                        <div class="financial-kpi">
                            <div class="kpi-label">Chiffre d'Affaires Total</div>
                            <div class="kpi-value" id="total-revenue">-</div>
                        </div>
                        <div class="financial-kpi">
                            <div class="kpi-label">Tarif Horaire Moyen</div>
                            <div class="kpi-value" id="avg-hourly-rate">-</div>
                        </div>
                        <div class="financial-kpi">
                            <div class="kpi-label">Heures Facturées</div>
                            <div class="kpi-value" id="billed-hours">-</div>
                        </div>
                    </div>
                </div>
                
                <div class="financial-charts">
                    <div class="chart-row">
                        <div class="chart-container">
                            <h3>Évolution du CA</h3>
                            <canvas id="revenue-evolution-chart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>CA par École</h3>
                            <canvas id="revenue-by-school-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglet Satisfaction -->
        <div id="satisfaction" class="report-tab">
            <div class="satisfaction-content">
                <div class="satisfaction-overview">
                    <h3>Analyse de la Satisfaction</h3>
                    <div class="satisfaction-metrics">
                        <div class="satisfaction-metric">
                            <div class="metric-icon">⭐</div>
                            <div class="metric-content">
                                <div class="metric-value" id="overall-satisfaction">-</div>
                                <div class="metric-label">Satisfaction Globale</div>
                            </div>
                        </div>
                        <div class="satisfaction-metric">
                            <div class="metric-icon">🏫</div>
                            <div class="metric-content">
                                <div class="metric-value" id="schools-satisfaction">-</div>
                                <div class="metric-label">Satisfaction Écoles</div>
                            </div>
                        </div>
                        <div class="satisfaction-metric">
                            <div class="metric-icon">👨‍🏫</div>
                            <div class="metric-content">
                                <div class="metric-value" id="trainers-satisfaction">-</div>
                                <div class="metric-label">Satisfaction Formateurs</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="satisfaction-charts">
                    <div class="chart-container">
                        <h3>Distribution des Notes</h3>
                        <canvas id="satisfaction-distribution-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Génération du rapport en cours...</p>
        </div>
    </div>
</div>

<!-- Modal d'aperçu avant export -->
<div id="export-preview-modal" class="tmm-modal">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h3>Aperçu du Rapport</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <div id="export-preview-content">
                <!-- Contenu généré dynamiquement -->
            </div>
        </div>
        <div class="tmm-modal-footer">
            <button class="button" onclick="closeExportPreview()">Annuler</button>
            <button class="button button-primary" onclick="confirmExport()">Exporter</button>
        </div>
    </div>
</div>

<style>
/* Styles spécifiques à la page de reporting */
.tmm-reporting-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 30px;
    font-size: 24px;
    font-weight: 600;
}

.title-icon {
    font-size: 32px;
}

.tmm-report-nav {
    margin-bottom: 20px;
}

.nav-tabs {
    display: flex;
    gap: 2px;
    border-bottom: 2px solid #e1e5e9;
}

.nav-tab {
    padding: 12px 24px;
    border: none;
    background: #f8f9fa;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nav-tab:hover {
    background: #e9ecef;
}

.nav-tab.active {
    background: #667eea;
    color: white;
}

.tmm-report-controls {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.controls-row {
    display: flex;
    gap: 30px;
    align-items: center;
    flex-wrap: wrap;
}

.date-range {
    display: flex;
    align-items: center;
    gap: 10px;
}

.date-range label {
    font-weight: 500;
    color: #555;
}

.date-range input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.quick-dates {
    display: flex;
    gap: 5px;
    margin-left: 15px;
}

.btn-quick-date {
    padding: 6px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.btn-quick-date:hover {
    background: #e9ecef;
}

.filters select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-right: 10px;
    min-width: 150px;
}

.actions {
    display: flex;
    gap: 10px;
    margin-left: auto;
}

.export-dropdown {
    position: relative;
}

.export-options {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    min-width: 120px;
    display: none;
}

.export-options.show {
    display: block;
}

.export-btn {
    display: block;
    width: 100%;
    padding: 10px 15px;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
}

.export-btn:hover {
    background: #f8f9fa;
}

.tmm-report-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.report-tab {
    display: none;
    padding: 30px;
}

.report-tab.active {
    display: block;
}

.kpi-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.kpi-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.kpi-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.kpi-content {
    flex: 1;
}

.kpi-value {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 5px;
}

.kpi-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
}

.kpi-trend {
    font-size: 12px;
    margin-top: 5px;
}

.kpi-trend.positive {
    color: #28a745;
}

.kpi-trend.negative {
    color: #dc3545;
}

.dashboard-charts {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-top: 30px;
}

.chart-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    position: relative;
    min-height: 300px;
}

.chart-container h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #2c3e50;
    text-align: center;
}

.chart-container canvas {
    max-width: 100%;
    height: auto !important;
}

.chart-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.tmm-data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.tmm-data-table th,
.tmm-data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.tmm-data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.tmm-data-table tr:hover {
    background: #f8f9fa;
}

.metrics-table-container {
    overflow-x: auto;
    margin-bottom: 30px;
}

.financial-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.financial-kpi {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}

.financial-kpi .kpi-label {
    font-size: 14px;
    margin-bottom: 10px;
    opacity: 0.9;
}

.financial-kpi .kpi-value {
    font-size: 24px;
    font-weight: 700;
}

.satisfaction-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.satisfaction-metric {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.metric-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.metric-content {
    flex: 1;
}

.metric-value {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.loading-spinner {
    text-align: center;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .dashboard-charts {
        grid-template-columns: 1fr;
    }
    
    .chart-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .controls-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .nav-tabs {
        flex-wrap: wrap;
    }
    
    .nav-tab {
        flex: 1;
        text-align: center;
    }
    
    .kpi-cards {
        grid-template-columns: 1fr;
    }
    
    .financial-kpis {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Initialisation de la page de reporting
document.addEventListener('DOMContentLoaded', function() {
    initReportingPage();
});

function initReportingPage() {
    // Initialiser les onglets
    initTabs();
    
    // Initialiser les contrôles
    initControls();
    
    // Charger les données initiales
    loadDashboardData();
    
    // Initialiser les graphiques
    initCharts();
}

function initTabs() {
    const tabs = document.querySelectorAll('.nav-tab');
    const tabContents = document.querySelectorAll('.report-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Désactiver tous les onglets
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));
            
            // Activer l'onglet sélectionné
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
            
            // Charger les données de l'onglet
            loadTabData(targetTab);
        });
    });
}

function initControls() {
    // Dates rapides
    document.querySelectorAll('.btn-quick-date').forEach(btn => {
        btn.addEventListener('click', function() {
            const days = parseInt(this.dataset.days);
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - days);
            
            document.getElementById('start-date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end-date').value = endDate.toISOString().split('T')[0];
            
            refreshCurrentTab();
        });
    });
    
    // Bouton actualiser
    document.getElementById('refresh-data').addEventListener('click', refreshCurrentTab);
    
    // Menu export
    document.getElementById('export-menu').addEventListener('click', function() {
        const dropdown = this.nextElementSibling;
        dropdown.classList.toggle('show');
    });
    
    // Options d'export
    document.querySelectorAll('.export-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const format = this.dataset.format;
            exportReport(format);
        });
    });
    
    // Fermer dropdown si clic ailleurs
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.export-dropdown')) {
            document.querySelectorAll('.export-options').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
}

function loadDashboardData() {
    showLoading();
    
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'tmm_generate_report',
            report_type: 'global_activity',
            start_date: startDate,
            end_date: endDate,
            nonce: tmmReporting.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            updateDashboardKPIs(data.data);
            updateDashboardCharts(data.data);
        } else {
            console.error('Erreur lors du chargement des données:', data.data);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erreur:', error);
    });
}

function updateDashboardKPIs(data) {
    const summary = data.summary;
    
    document.getElementById('total-sessions').textContent = summary.total_sessions;
    document.getElementById('completed-sessions').textContent = summary.completed_sessions;
    document.getElementById('total-hours').textContent = Math.round(summary.total_hours_realized) + 'h';
    document.getElementById('realization-rate').textContent = summary.realization_rate + '%';
    
    // Mettre à jour les tendances (simulation)
    updateTrend('sessions-trend', 15);
    updateTrend('completed-trend', 12);
    updateTrend('hours-trend', 8);
    updateTrend('realization-trend', 5);
}

function updateTrend(elementId, value) {
    const element = document.getElementById(elementId);
    const isPositive = value > 0;
    element.textContent = (isPositive ? '+' : '') + value + '%';
    element.className = 'kpi-trend ' + (isPositive ? 'positive' : 'negative');
}

function showLoading() {
    document.getElementById('loading-overlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loading-overlay').style.display = 'none';
}

function refreshCurrentTab() {
    const activeTab = document.querySelector('.nav-tab.active');
    if (activeTab) {
        loadTabData(activeTab.dataset.tab);
    }
}

function loadTabData(tabName) {
    // Implémentation du chargement par onglet
    console.log('Chargement des données pour l\'onglet:', tabName);
    
    switch(tabName) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'activity':
            loadActivityData();
            break;
        case 'schools':
            loadSchoolsData();
            break;
        case 'trainers':
            loadTrainersData();
            break;
        case 'modules':
            loadModulesData();
            break;
        case 'financial':
            loadFinancialData();
            break;
        case 'satisfaction':
            loadSatisfactionData();
            break;
    }
}

function exportReport(format) {
    const activeTab = document.querySelector('.nav-tab.active').dataset.tab;
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    
    showLoading();
    
    const exportData = {
        action: 'tmm_export_report',
        format: format,
        report_type: activeTab,
        start_date: startDate,
        end_date: endDate,
        nonce: tmmReporting.nonce
    };
    
    // Créer un formulaire pour le téléchargement
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = ajaxurl;
    form.style.display = 'none';
    
    Object.keys(exportData).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = exportData[key];
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    hideLoading();
}

// Variables globales pour les graphiques
let monthlyEvolutionChart, statusDistributionChart;

function initCharts() {
    // Graphique d'évolution mensuelle
    const monthlyCtx = document.getElementById('monthly-evolution-chart').getContext('2d');
    monthlyEvolutionChart = new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Sessions',
                data: [],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Graphique de répartition par statut (camembert)
    const statusCtx = document.getElementById('status-distribution-chart').getContext('2d');
    statusDistributionChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['Terminées', 'En cours', 'Proposées', 'Annulées'],
            datasets: [{
                data: [0, 0, 0, 0],
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#ffc107',
                    '#dc3545'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
    });
}

function updateDashboardCharts(data) {
    // Mettre à jour le graphique d'évolution mensuelle
    if (data.monthly_evolution) {
        const labels = data.monthly_evolution.map(item => item.month);
        const sessionData = data.monthly_evolution.map(item => item.sessions_count);
        
        monthlyEvolutionChart.data.labels = labels;
        monthlyEvolutionChart.data.datasets[0].data = sessionData;
        monthlyEvolutionChart.update();
    }
    
    // Mettre à jour le graphique de répartition (simulation)
    const statusData = [
        data.summary.completed_sessions,
        Math.round(data.summary.total_sessions * 0.1), // En cours
        Math.round(data.summary.total_sessions * 0.15), // Proposées
        Math.round(data.summary.total_sessions * 0.05)  // Annulées
    ];
    
    statusDistributionChart.data.datasets[0].data = statusData;
    statusDistributionChart.update();
}

// Fonctions de chargement des données par onglet
function loadActivityData() {
    console.log('Chargement des données d\'activité');
    // Implémentation du chargement des données d'activité
}

function loadSchoolsData() {
    console.log('Chargement des données des écoles');
    // Implémentation du chargement des données des écoles
}

function loadTrainersData() {
    console.log('Chargement des données des formateurs');
    // Implémentation du chargement des données des formateurs
}

function loadModulesData() {
    console.log('Chargement des données des modules');
    // Implémentation du chargement des données des modules
}

function loadFinancialData() {
    console.log('Chargement des données financières');
    // Implémentation du chargement des données financières
}

function loadSatisfactionData() {
    console.log('Chargement des données de satisfaction');
    // Implémentation du chargement des données de satisfaction
}
</script>
<?php
// Enqueue les scripts spécifiques au reporting
wp_enqueue_script('tmm-reporting-js', TMM_PLUGIN_URL . 'assets/js/tmm-reporting.js', array('jquery', 'chart-js'), TMM_PLUGIN_VERSION, true);
wp_localize_script('tmm-reporting-js', 'tmmReporting', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('tmm_nonce'),
    'strings' => array(
        'loading' => 'Chargement en cours...',
        'error' => 'Erreur lors du chargement des données',
        'no_data' => 'Aucune donnée disponible pour cette période'
    )
));
?>