/**
 * TeachMeMore PédagoConnect - Reporting & Analytics JavaScript
 * Gestion complète des rapports et analyses
 * 
 * @version 1.0.0
 * @author TeachMeMore
 */

(function($) {
    'use strict';

    // Variables globales
    let monthlyEvolutionChart, statusDistributionChart;
    let sessionsMonthlyChart, hoursMonthlyChart;
    let topSchoolsChart, topModulesChart;
    let schoolsComparisonChart, trainersHoursChart;
    let modulesDistributionChart, revenueEvolutionChart;
    let revenueBySchoolChart, satisfactionDistributionChart;
    
    let currentFilters = {
        startDate: null,
        endDate: null,
        school: null,
        trainer: null,
        module: null
    };

    // Initialisation
    $(document).ready(function() {
        initReportingPage();
    });

    /**
     * Initialisation principale de la page
     */
    function initReportingPage() {
        initTabs();
        initControls();
        initDateFilters();
        loadDashboardData();
        initCharts();
        bindEvents();
    }

    /**
     * Initialisation des onglets
     */
    function initTabs() {
        const tabs = $('.nav-tab');
        const tabContents = $('.report-tab');
        
        tabs.on('click', function(e) {
            e.preventDefault();
            const targetTab = $(this).data('tab');
            
            // Désactiver tous les onglets
            tabs.removeClass('active');
            tabContents.removeClass('active');
            
            // Activer l'onglet sélectionné
            $(this).addClass('active');
            $(`#${targetTab}`).addClass('active');
            
            // Charger les données de l'onglet
            loadTabData(targetTab);
        });
    }

    /**
     * Initialisation des contrôles
     */
    function initControls() {
        // Dates rapides
        $('.btn-quick-date').on('click', function() {
            const days = parseInt($(this).data('days'));
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - days);
            
            $('#start-date').val(startDate.toISOString().split('T')[0]);
            $('#end-date').val(endDate.toISOString().split('T')[0]);
            
            updateCurrentFilters();
            refreshCurrentTab();
        });
        
        // Bouton actualiser
        $('#refresh-data').on('click', function() {
            updateCurrentFilters();
            refreshCurrentTab();
        });
        
        // Menu export
        $('#export-menu').on('click', function(e) {
            e.preventDefault();
            $('.export-options').toggleClass('show');
        });
        
        // Options d'export
        $('.export-btn').on('click', function() {
            const format = $(this).data('format');
            exportReport(format);
            $('.export-options').removeClass('show');
        });
        
        // Fermer dropdown si clic ailleurs
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.export-dropdown').length) {
                $('.export-options').removeClass('show');
            }
        });
    }

    /**
     * Initialisation des filtres de date
     */
    function initDateFilters() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        
        if (!$('#start-date').val()) {
            $('#start-date').val(thirtyDaysAgo.toISOString().split('T')[0]);
        }
        if (!$('#end-date').val()) {
            $('#end-date').val(today.toISOString().split('T')[0]);
        }
        
        updateCurrentFilters();
    }

    /**
     * Liaison des événements
     */
    function bindEvents() {
        // Changement de filtres
        $('#start-date, #end-date, #school-filter, #trainer-filter, #module-filter').on('change', function() {
            updateCurrentFilters();
            refreshCurrentTab();
        });
        
        // Fermeture de modal
        $('.tmm-modal-close').on('click', closeExportPreview);
        
        // Fermeture modal par clic extérieur
        $('.tmm-modal').on('click', function(e) {
            if (e.target === this) {
                closeExportPreview();
            }
        });
    }

    /**
     * Mise à jour des filtres actuels
     */
    function updateCurrentFilters() {
        currentFilters = {
            startDate: $('#start-date').val(),
            endDate: $('#end-date').val(),
            school: $('#school-filter').val(),
            trainer: $('#trainer-filter').val(),
            module: $('#module-filter').val()
        };
    }

    /**
     * Chargement des données du dashboard
     */
    function loadDashboardData() {
        showLoading();
        
        const requestData = {
            action: 'tmm_generate_report',
            report_type: 'dashboard',
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        $.post(tmmReporting.ajax_url, requestData)
            .done(function(response) {
                hideLoading();
                if (response.success) {
                    updateDashboardKPIs(response.data);
                    updateDashboardCharts(response.data);
                } else {
                    showError(response.data || tmmReporting.strings.error);
                }
            })
            .fail(function() {
                hideLoading();
                showError(tmmReporting.strings.error);
            });
    }

    /**
     * Mise à jour des KPIs du dashboard
     */
    function updateDashboardKPIs(data) {
        const summary = data.summary || {};
        
        $('#total-sessions').text(summary.total_sessions || 0);
        $('#completed-sessions').text(summary.completed_sessions || 0);
        $('#total-hours').text(Math.round(summary.total_hours_realized || 0) + 'h');
        $('#realization-rate').text((summary.realization_rate || 0) + '%');
        
        // Mettre à jour les tendances
        updateTrend('sessions-trend', summary.sessions_trend || 0);
        updateTrend('completed-trend', summary.completed_trend || 0);
        updateTrend('hours-trend', summary.hours_trend || 0);
        updateTrend('realization-trend', summary.realization_trend || 0);
    }

    /**
     * Mise à jour des tendances
     */
    function updateTrend(elementId, value) {
        const element = $(`#${elementId}`);
        const isPositive = value > 0;
        element.text((isPositive ? '+' : '') + value + '%');
        element.attr('class', 'kpi-trend ' + (isPositive ? 'positive' : 'negative'));
    }

    /**
     * Chargement des données par onglet
     */
    function loadTabData(tabName) {
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

    /**
     * Chargement des données d'activité
     */
    function loadActivityData() {
        showLoading();
        
        const requestData = {
            action: 'tmm_generate_report',
            report_type: 'activity',
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        $.post(tmmReporting.ajax_url, requestData)
            .done(function(response) {
                hideLoading();
                if (response.success) {
                    updateActivityContent(response.data);
                    updateActivityCharts(response.data);
                } else {
                    showError(response.data || tmmReporting.strings.error);
                }
            })
            .fail(function() {
                hideLoading();
                showError(tmmReporting.strings.error);
            });
    }

    /**
     * Chargement des données des écoles
     */
    function loadSchoolsData() {
        showLoading();
        
        const requestData = {
            action: 'tmm_generate_report',
            report_type: 'schools',
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        $.post(tmmReporting.ajax_url, requestData)
            .done(function(response) {
                hideLoading();
                if (response.success) {
                    updateSchoolsTable(response.data);
                    updateSchoolsCharts(response.data);
                } else {
                    showError(response.data || tmmReporting.strings.error);
                }
            })
            .fail(function() {
                hideLoading();
                showError(tmmReporting.strings.error);
            });
    }

    /**
     * Chargement des données des formateurs
     */
    function loadTrainersData() {
        showLoading();
        
        const requestData = {
            action: 'tmm_generate_report',
            report_type: 'trainers',
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        $.post(tmmReporting.ajax_url, requestData)
            .done(function(response) {
                hideLoading();
                if (response.success) {
                    updateTrainersTable(response.data);
                    updateTrainersCharts(response.data);
                } else {
                    showError(response.data || tmmReporting.strings.error);
                }
            })
            .fail(function() {
                hideLoading();
                showError(tmmReporting.strings.error);
            });
    }

    /**
     * Chargement des données des modules
     */
    function loadModulesData() {
        showLoading();
        
        const requestData = {
            action: 'tmm_generate_report',
            report_type: 'modules',
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        $.post(tmmReporting.ajax_url, requestData)
            .done(function(response) {
                hideLoading();
                if (response.success) {
                    updateModulesTable(response.data);
                    updateModulesCharts(response.data);
                } else {
                    showError(response.data || tmmReporting.strings.error);
                }
            })
            .fail(function() {
                hideLoading();
                showError(tmmReporting.strings.error);
            });
    }

    /**
     * Chargement des données financières
     */
    function loadFinancialData() {
        showLoading();
        
        const requestData = {
            action: 'tmm_generate_report',
            report_type: 'financial',
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        $.post(tmmReporting.ajax_url, requestData)
            .done(function(response) {
                hideLoading();
                if (response.success) {
                    updateFinancialKPIs(response.data);
                    updateFinancialCharts(response.data);
                } else {
                    showError(response.data || tmmReporting.strings.error);
                }
            })
            .fail(function() {
                hideLoading();
                showError(tmmReporting.strings.error);
            });
    }

    /**
     * Chargement des données de satisfaction
     */
    function loadSatisfactionData() {
        showLoading();
        
        const requestData = {
            action: 'tmm_generate_report',
            report_type: 'satisfaction',
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        $.post(tmmReporting.ajax_url, requestData)
            .done(function(response) {
                hideLoading();
                if (response.success) {
                    updateSatisfactionMetrics(response.data);
                    updateSatisfactionCharts(response.data);
                } else {
                    showError(response.data || tmmReporting.strings.error);
                }
            })
            .fail(function() {
                hideLoading();
                showError(tmmReporting.strings.error);
            });
    }

    /**
     * Initialisation des graphiques
     */
    function initCharts() {
        // Graphique d'évolution mensuelle
        const monthlyCtx = document.getElementById('monthly-evolution-chart');
        if (monthlyCtx) {
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
                        fill: true,
                        tension: 0.4
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
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                }
            });
        }
        
        // Graphique de répartition par statut
        const statusCtx = document.getElementById('status-distribution-chart');
        if (statusCtx) {
            statusDistributionChart = new Chart(statusCtx, {
                type: 'doughnut',
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
        
        // Initialiser les autres graphiques
        initActivityCharts();
        initSchoolsCharts();
        initTrainersCharts();
        initModulesCharts();
        initFinancialCharts();
        initSatisfactionCharts();
    }

    /**
     * Initialisation des graphiques d'activité
     */
    function initActivityCharts() {
        // Graphique sessions mensuelles
        const sessionsCtx = document.getElementById('sessions-monthly-chart');
        if (sessionsCtx) {
            sessionsMonthlyChart = new Chart(sessionsCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Sessions',
                        data: [],
                        backgroundColor: '#667eea',
                        borderColor: '#667eea',
                        borderWidth: 1
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
        }
        
        // Graphique heures mensuelles
        const hoursCtx = document.getElementById('hours-monthly-chart');
        if (hoursCtx) {
            hoursMonthlyChart = new Chart(hoursCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Heures',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
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
        }
        
        // Top 5 écoles
        const topSchoolsCtx = document.getElementById('top-schools-chart');
        if (topSchoolsCtx) {
            topSchoolsChart = new Chart(topSchoolsCtx, {
                type: 'horizontalBar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Sessions',
                        data: [],
                        backgroundColor: '#ffc107',
                        borderColor: '#ffc107',
                        borderWidth: 1
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
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Top 5 modules
        const topModulesCtx = document.getElementById('top-modules-chart');
        if (topModulesCtx) {
            topModulesChart = new Chart(topModulesCtx, {
                type: 'horizontalBar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Sessions',
                        data: [],
                        backgroundColor: '#17a2b8',
                        borderColor: '#17a2b8',
                        borderWidth: 1
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
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialisation des graphiques des écoles
     */
    function initSchoolsCharts() {
        const schoolsCtx = document.getElementById('schools-comparison-chart');
        if (schoolsCtx) {
            schoolsComparisonChart = new Chart(schoolsCtx, {
                type: 'radar',
                data: {
                    labels: ['Sessions', 'Heures', 'Complétion', 'Satisfaction', 'Performance'],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialisation des graphiques des formateurs
     */
    function initTrainersCharts() {
        const trainersCtx = document.getElementById('trainers-hours-chart');
        if (trainersCtx) {
            trainersHoursChart = new Chart(trainersCtx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#667eea', '#28a745', '#ffc107', '#dc3545',
                            '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14'
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
                                padding: 15
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialisation des graphiques des modules
     */
    function initModulesCharts() {
        const modulesCtx = document.getElementById('modules-distribution-chart');
        if (modulesCtx) {
            modulesDistributionChart = new Chart(modulesCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Sessions',
                        data: [],
                        backgroundColor: '#667eea',
                        borderColor: '#667eea',
                        borderWidth: 1
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
                        },
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialisation des graphiques financiers
     */
    function initFinancialCharts() {
        // Évolution du CA
        const revenueCtx = document.getElementById('revenue-evolution-chart');
        if (revenueCtx) {
            revenueEvolutionChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Chiffre d\'affaires',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
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
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' €';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // CA par école
        const revenueSchoolCtx = document.getElementById('revenue-by-school-chart');
        if (revenueSchoolCtx) {
            revenueBySchoolChart = new Chart(revenueSchoolCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#667eea', '#28a745', '#ffc107', '#dc3545',
                            '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14'
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
                                padding: 15
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialisation des graphiques de satisfaction
     */
    function initSatisfactionCharts() {
        const satisfactionCtx = document.getElementById('satisfaction-distribution-chart');
        if (satisfactionCtx) {
            satisfactionDistributionChart = new Chart(satisfactionCtx, {
                type: 'bar',
                data: {
                    labels: ['1 étoile', '2 étoiles', '3 étoiles', '4 étoiles', '5 étoiles'],
                    datasets: [{
                        label: 'Nombre d\'évaluations',
                        data: [],
                        backgroundColor: [
                            '#dc3545', '#fd7e14', '#ffc107', '#28a745', '#17a2b8'
                        ],
                        borderWidth: 1
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
        }
    }

    /**
     * Mise à jour des graphiques du dashboard
     */
    function updateDashboardCharts(data) {
        // Mettre à jour le graphique d'évolution mensuelle
        if (data.monthly_evolution && monthlyEvolutionChart) {
            const labels = data.monthly_evolution.map(item => item.month);
            const sessionData = data.monthly_evolution.map(item => item.sessions_count);
            
            monthlyEvolutionChart.data.labels = labels;
            monthlyEvolutionChart.data.datasets[0].data = sessionData;
            monthlyEvolutionChart.update();
        }
        
        // Mettre à jour le graphique de répartition par statut
        if (data.status_distribution && statusDistributionChart) {
            const statusData = [
                data.status_distribution.completed || 0,
                data.status_distribution.in_progress || 0,
                data.status_distribution.proposed || 0,
                data.status_distribution.cancelled || 0
            ];
            
            statusDistributionChart.data.datasets[0].data = statusData;
            statusDistributionChart.update();
        }
    }

    /**
     * Mise à jour du contenu d'activité
     */
    function updateActivityContent(data) {
        const summaryHtml = `
            <div class="activity-summary-grid">
                <div class="summary-item">
                    <div class="summary-value">${data.total_sessions || 0}</div>
                    <div class="summary-label">Sessions Total</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">${Math.round(data.total_hours || 0)}h</div>
                    <div class="summary-label">Heures Total</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">${data.active_schools || 0}</div>
                    <div class="summary-label">Écoles Actives</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">${data.active_trainers || 0}</div>
                    <div class="summary-label">Formateurs Actifs</div>
                </div>
            </div>
        `;
        
        $('#activity-summary-content').html(summaryHtml);
    }

    /**
     * Mise à jour des graphiques d'activité
     */
    function updateActivityCharts(data) {
        // Sessions mensuelles
        if (data.monthly_sessions && sessionsMonthlyChart) {
            const labels = data.monthly_sessions.map(item => item.month);
            const sessionData = data.monthly_sessions.map(item => item.count);
            
            sessionsMonthlyChart.data.labels = labels;
            sessionsMonthlyChart.data.datasets[0].data = sessionData;
            sessionsMonthlyChart.update();
        }
        
        // Heures mensuelles
        if (data.monthly_hours && hoursMonthlyChart) {
            const labels = data.monthly_hours.map(item => item.month);
            const hoursData = data.monthly_hours.map(item => item.hours);
            
            hoursMonthlyChart.data.labels = labels;
            hoursMonthlyChart.data.datasets[0].data = hoursData;
            hoursMonthlyChart.update();
        }
        
        // Top écoles
        if (data.top_schools && topSchoolsChart) {
            const labels = data.top_schools.map(item => item.name);
            const schoolData = data.top_schools.map(item => item.sessions);
            
            topSchoolsChart.data.labels = labels;
            topSchoolsChart.data.datasets[0].data = schoolData;
            topSchoolsChart.update();
        }
        
        // Top modules
        if (data.top_modules && topModulesChart) {
            const labels = data.top_modules.map(item => item.name);
            const moduleData = data.top_modules.map(item => item.sessions);
            
            topModulesChart.data.labels = labels;
            topModulesChart.data.datasets[0].data = moduleData;
            topModulesChart.update();
        }
    }

    /**
     * Mise à jour du tableau des écoles
     */
    function updateSchoolsTable(data) {
        const tbody = $('#schools-metrics-table tbody');
        tbody.empty();
        
        if (data.schools && data.schools.length > 0) {
            data.schools.forEach(school => {
                const row = `
                    <tr>
                        <td>${school.name}</td>
                        <td>${school.sessions}</td>
                        <td>${Math.round(school.hours)}h</td>
                        <td>${school.completion_rate}%</td>
                        <td>${school.satisfaction}/5</td>
                        <td>${school.performance_score}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        } else {
            tbody.append('<tr><td colspan="6">Aucune donnée disponible</td></tr>');
        }
    }

    /**
     * Mise à jour des graphiques des écoles
     */
    function updateSchoolsCharts(data) {
        if (data.schools && schoolsComparisonChart) {
            // Prendre les 5 meilleures écoles pour le graphique radar
            const topSchools = data.schools.slice(0, 5);
            
            schoolsComparisonChart.data.datasets = topSchools.map((school, index) => ({
                label: school.name,
                data: [
                    school.sessions_normalized,
                    school.hours_normalized,
                    school.completion_rate,
                    school.satisfaction * 20, // Convertir sur 100
                    school.performance_score
                ],
                backgroundColor: `rgba(${[102, 126, 234][index % 3]}, 0.2)`,
                borderColor: `rgba(${[102, 126, 234][index % 3]}, 1)`,
                borderWidth: 2
            }));
            
            schoolsComparisonChart.update();
        }
    }

    /**
     * Mise à jour du tableau des formateurs
     */
    function updateTrainersTable(data) {
        const tbody = $('#trainers-metrics-table tbody');
        tbody.empty();
        
        if (data.trainers && data.trainers.length > 0) {
            data.trainers.forEach(trainer => {
                const row = `
                    <tr>
                        <td>${trainer.name}</td>
                        <td>${trainer.sessions}</td>
                        <td>${Math.round(trainer.hours)}h</td>
                        <td>${trainer.schools_count}</td>
                        <td>${trainer.utilization_rate}%</td>
                        <td>${trainer.average_rating}/5</td>
                    </tr>
                `;
                tbody.append(row);
            });
        } else {
            tbody.append('<tr><td colspan="6">Aucune donnée disponible</td></tr>');
        }
    }

    /**
     * Mise à jour des graphiques des formateurs
     */
    function updateTrainersCharts(data) {
        if (data.trainers && trainersHoursChart) {
            const labels = data.trainers.map(trainer => trainer.name);
            const hoursData = data.trainers.map(trainer => trainer.hours);
            
            trainersHoursChart.data.labels = labels;
            trainersHoursChart.data.datasets[0].data = hoursData;
            trainersHoursChart.update();
        }
    }

    /**
     * Mise à jour du tableau des modules
     */
    function updateModulesTable(data) {
        const tbody = $('#modules-metrics-table tbody');
        tbody.empty();
        
        if (data.modules && data.modules.length > 0) {
            data.modules.forEach(module => {
                const row = `
                    <tr>
                        <td>${module.name}</td>
                        <td>${module.sessions}</td>
                        <td>${module.schools_count}</td>
                        <td>${Math.round(module.total_hours)}h</td>
                        <td>${module.completion_rate}%</td>
                        <td>${module.satisfaction}/5</td>
                    </tr>
                `;
                tbody.append(row);
            });
        } else {
            tbody.append('<tr><td colspan="6">Aucune donnée disponible</td></tr>');
        }
    }

    /**
     * Mise à jour des graphiques des modules
     */
    function updateModulesCharts(data) {
        if (data.modules && modulesDistributionChart) {
            const labels = data.modules.map(module => module.name);
            const sessionsData = data.modules.map(module => module.sessions);
            
            modulesDistributionChart.data.labels = labels;
            modulesDistributionChart.data.datasets[0].data = sessionsData;
            modulesDistributionChart.update();
        }
    }

    /**
     * Mise à jour des KPIs financiers
     */
    function updateFinancialKPIs(data) {
        const financial = data.financial || {};
        
        $('#total-revenue').text(formatCurrency(financial.total_revenue || 0));
        $('#avg-hourly-rate').text(formatCurrency(financial.avg_hourly_rate || 0));
        $('#billed-hours').text(Math.round(financial.billed_hours || 0) + 'h');
    }

    /**
     * Mise à jour des graphiques financiers
     */
    function updateFinancialCharts(data) {
        // Évolution du CA
        if (data.revenue_evolution && revenueEvolutionChart) {
            const labels = data.revenue_evolution.map(item => item.month);
            const revenueData = data.revenue_evolution.map(item => item.revenue);
            
            revenueEvolutionChart.data.labels = labels;
            revenueEvolutionChart.data.datasets[0].data = revenueData;
            revenueEvolutionChart.update();
        }
        
        // CA par école
        if (data.revenue_by_school && revenueBySchoolChart) {
            const labels = data.revenue_by_school.map(item => item.school_name);
            const revenueData = data.revenue_by_school.map(item => item.revenue);
            
            revenueBySchoolChart.data.labels = labels;
            revenueBySchoolChart.data.datasets[0].data = revenueData;
            revenueBySchoolChart.update();
        }
    }

    /**
     * Mise à jour des métriques de satisfaction
     */
    function updateSatisfactionMetrics(data) {
        const satisfaction = data.satisfaction || {};
        
        $('#overall-satisfaction').text((satisfaction.overall || 0) + '/5');
        $('#schools-satisfaction').text((satisfaction.schools || 0) + '/5');
        $('#trainers-satisfaction').text((satisfaction.trainers || 0) + '/5');
    }

    /**
     * Mise à jour des graphiques de satisfaction
     */
    function updateSatisfactionCharts(data) {
        if (data.satisfaction_distribution && satisfactionDistributionChart) {
            const distribution = data.satisfaction_distribution;
            const distributionData = [
                distribution.one_star || 0,
                distribution.two_stars || 0,
                distribution.three_stars || 0,
                distribution.four_stars || 0,
                distribution.five_stars || 0
            ];
            
            satisfactionDistributionChart.data.datasets[0].data = distributionData;
            satisfactionDistributionChart.update();
        }
    }

    /**
     * Actualisation de l'onglet actuel
     */
    function refreshCurrentTab() {
        const activeTab = $('.nav-tab.active');
        if (activeTab.length) {
            loadTabData(activeTab.data('tab'));
        }
    }

    /**
     * Export de rapport
     */
    function exportReport(format) {
        const activeTab = $('.nav-tab.active').data('tab');
        
        showLoading();
        
        const exportData = {
            action: 'tmm_export_report',
            format: format,
            report_type: activeTab,
            ...currentFilters,
            nonce: tmmReporting.nonce
        };
        
        // Créer un formulaire pour le téléchargement
        const form = $('<form>', {
            method: 'POST',
            action: tmmReporting.ajax_url,
            style: 'display: none;'
        });
        
        $.each(exportData, function(key, value) {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: value
            }));
        });
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        hideLoading();
    }

    /**
     * Affichage du loading
     */
    function showLoading() {
        $('#loading-overlay').show();
    }

    /**
     * Masquage du loading
     */
    function hideLoading() {
        $('#loading-overlay').hide();
    }

    /**
     * Affichage d'erreur
     */
    function showError(message) {
        // Créer une notification d'erreur
        const errorDiv = $('<div>', {
            class: 'tmm-error-notification',
            text: message
        });
        
        $('body').append(errorDiv);
        
        setTimeout(() => {
            errorDiv.fadeOut(() => {
                errorDiv.remove();
            });
        }, 5000);
    }

    /**
     * Formatage des devises
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }

    /**
     * Fermeture de l'aperçu d'export
     */
    function closeExportPreview() {
        $('#export-preview-modal').hide();
    }

    /**
     * Confirmation d'export
     */
    function confirmExport() {
        closeExportPreview();
        // Logique d'export confirmée
    }

    // Exposer les fonctions globales nécessaires
    window.closeExportPreview = closeExportPreview;
    window.confirmExport = confirmExport;

})(jQuery);