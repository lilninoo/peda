<?php
/**
 * Template pour le planning global TeachMeMore
 * Vue d'ensemble de toutes les sessions planifiées
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifications de sécurité
if (!current_user_can('view_tmm_reports')) {
    wp_die('Accès non autorisé');
}

// Récupérer les données pour les filtres
$schools = get_posts(array('post_type' => 'tmm_school', 'numberposts' => -1));
$trainers = get_users(array('role' => 'tmm_trainer'));
$modules = get_posts(array('post_type' => 'tmm_module', 'numberposts' => -1));

// Statistiques rapides
global $wpdb;
$sessions_table = $wpdb->prefix . 'tmm_sessions_meta';

$quick_stats = array(
    'sessions_today' => $wpdb->get_var(
        "SELECT COUNT(*) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE DATE(s.start_datetime) = CURDATE()
         AND p.post_status IN ('confirmed', 'in_progress')"
    ),
    'sessions_this_week' => $wpdb->get_var(
        "SELECT COUNT(*) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE YEARWEEK(s.start_datetime) = YEARWEEK(NOW())
         AND p.post_status IN ('confirmed', 'in_progress')"
    ),
    'trainers_busy_today' => $wpdb->get_var(
        "SELECT COUNT(DISTINCT s.trainer_id) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE DATE(s.start_datetime) = CURDATE()
         AND p.post_status IN ('confirmed', 'in_progress')
         AND s.trainer_id IS NOT NULL"
    ),
    'pending_assignments' => $wpdb->get_var(
        "SELECT COUNT(*) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE p.post_status = 'confirmed'
         AND s.trainer_id IS NULL
         AND s.start_datetime > NOW()"
    )
);
?>

<div class="wrap tmm-planning-page">
    <div class="planning-header">
        <div class="header-content">
            <div class="header-title">
                <h1>📅 Planning Global TeachMeMore</h1>
                <p class="subtitle">Vue d'ensemble de toutes les sessions planifiées</p>
            </div>
            
            <div class="header-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $quick_stats['sessions_today']; ?></span>
                    <span class="stat-label">Aujourd'hui</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $quick_stats['sessions_this_week']; ?></span>
                    <span class="stat-label">Cette semaine</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $quick_stats['trainers_busy_today']; ?></span>
                    <span class="stat-label">Formateurs occupés</span>
                </div>
                <div class="stat-item urgent">
                    <span class="stat-number"><?php echo $quick_stats['pending_assignments']; ?></span>
                    <span class="stat-label">À assigner</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contrôles de filtrage -->
    <div class="planning-controls">
        <div class="controls-section">
            <h3>🔍 Filtres</h3>
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="view-type">Type de vue</label>
                    <select id="view-type" class="filter-select">
                        <option value="global">Vue globale</option>
                        <option value="by_trainer">Par formateur</option>
                        <option value="by_school">Par école</option>
                        <option value="by_module">Par module</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status-filter">Statut</label>
                    <select id="status-filter" class="filter-select">
                        <option value="">Tous les statuts</option>
                        <option value="proposed">Proposées</option>
                        <option value="confirmed">Confirmées</option>
                        <option value="in_progress">En cours</option>
                        <option value="completed">Terminées</option>
                        <option value="cancelled">Annulées</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="school-filter">École</label>
                    <select id="school-filter" class="filter-select">
                        <option value="">Toutes les écoles</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?php echo $school->ID; ?>"><?php echo esc_html($school->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="trainer-filter">Formateur</label>
                    <select id="trainer-filter" class="filter-select">
                        <option value="">Tous les formateurs</option>
                        <?php foreach ($trainers as $trainer): ?>
                            <option value="<?php echo $trainer->ID; ?>"><?php echo esc_html($trainer->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="module-filter">Module</label>
                    <select id="module-filter" class="filter-select">
                        <option value="">Tous les modules</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module->ID; ?>"><?php echo esc_html($module->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date-range">Période</label>
                    <select id="date-range" class="filter-select">
                        <option value="current_month">Ce mois</option>
                        <option value="next_month">Mois prochain</option>
                        <option value="current_quarter">Ce trimestre</option>
                        <option value="custom">Personnalisée</option>
                    </select>
                </div>
            </div>
            
            <div class="custom-date-range" style="display: none;">
                <div class="date-inputs">
                    <input type="date" id="start-date" />
                    <span>à</span>
                    <input type="date" id="end-date" />
                </div>
            </div>
        </div>
        
        <div class="controls-section">
            <h3>⚡ Actions Rapides</h3>
            <div class="action-buttons">
                <button id="refresh-planning" class="button button-primary">
                    🔄 Actualiser
                </button>
                <button id="export-planning" class="button">
                    📤 Exporter Planning
                </button>
                <button id="print-planning" class="button">
                    🖨️ Imprimer
                </button>
                <button id="detect-conflicts" class="button">
                    ⚠️ Détecter Conflits
                </button>
                <button id="optimize-planning" class="button">
                    🤖 Optimiser
                </button>
                <button id="new-session-quick" class="button button-secondary">
                    ➕ Nouvelle Session
                </button>
            </div>
        </div>
    </div>
    
    <!-- Barre d'outils du calendrier -->
    <div class="calendar-toolbar">
        <div class="view-switcher">
            <button class="view-btn active" data-view="month">📅 Mois</button>
            <button class="view-btn" data-view="agendaWeek">📊 Semaine</button>
            <button class="view-btn" data-view="agendaDay">📋 Jour</button>
            <button class="view-btn" data-view="listWeek">📃 Liste</button>
        </div>
        
        <div class="calendar-navigation">
            <button id="calendar-prev" class="nav-btn">‹ Précédent</button>
            <h3 id="calendar-title">Chargement...</h3>
            <button id="calendar-next" class="nav-btn">Suivant ›</button>
            <button id="calendar-today" class="nav-btn">Aujourd'hui</button>
        </div>
        
        <div class="calendar-options">
            <label class="checkbox-label">
                <input type="checkbox" id="show-weekends" checked>
                Week-ends
            </label>
            <label class="checkbox-label">
                <input type="checkbox" id="show-cancelled">
                Sessions annulées
            </label>
            <button id="calendar-fullscreen" class="nav-btn">⛶ Plein écran</button>
        </div>
    </div>
    
    <!-- Calendrier principal -->
    <div class="calendar-container">
        <div id="tmm-global-calendar" class="tmm-calendar"></div>
    </div>
    
    <!-- Légende et statistiques -->
    <div class="planning-footer">
        <div class="planning-legend">
            <h4>Légende</h4>
            <div class="legend-items">
                <span class="legend-item">
                    <span class="color-box status-proposed"></span>
                    Proposées
                </span>
                <span class="legend-item">
                    <span class="color-box status-confirmed"></span>
                    Confirmées
                </span>
                <span class="legend-item">
                    <span class="color-box status-in-progress"></span>
                    En cours
                </span>
                <span class="legend-item">
                    <span class="color-box status-completed"></span>
                    Terminées
                </span>
                <span class="legend-item">
                    <span class="color-box status-cancelled"></span>
                    Annulées
                </span>
                <span class="legend-item">
                    <span class="color-box status-conflict"></span>
                    Conflits
                </span>
            </div>
        </div>
        
        <div class="planning-insights">
            <h4>📊 Insights</h4>
            <div id="planning-insights-content">
                <!-- Chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<!-- Modal détails session -->
<div id="session-details-modal" class="tmm-modal">
    <div class="tmm-modal-content large">
        <div class="tmm-modal-header">
            <h3 id="session-modal-title">Détails de la Session</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <div id="session-details-content">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<!-- Modal conflits -->
<div id="conflicts-modal" class="tmm-modal">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h3>⚠️ Conflits Détectés</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <div id="conflicts-list">
                <!-- Liste des conflits -->
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let calendar;
    let currentFilters = {};
    
    // Initialisation
    initGlobalPlanning();
    
    function initGlobalPlanning() {
        // Initialiser le calendrier FullCalendar
        calendar = $('#tmm-global-calendar').fullCalendar({
            locale: 'fr',
            height: 'auto',
            header: false, // Désactiver le header par défaut
            defaultView: 'month',
            editable: true,
            droppable: true,
            selectable: true,
            selectHelper: true,
            businessHours: {
                dow: [1, 2, 3, 4, 5], // Lundi à vendredi
                start: '08:00',
                end: '18:00'
            },
            events: function(start, end, timezone, callback) {
                loadCalendarEvents(start, end, callback);
            },
            eventRender: function(event, element) {
                // Personnaliser l'affichage des événements
                element.addClass('session-event status-' + event.status);
                
                // Ajouter des informations supplémentaires
                var tooltip = `
                    <div class="event-tooltip">
                        <strong>${event.title}</strong><br>
                        École: ${event.school_name}<br>
                        Formateur: ${event.trainer_name || 'Non assigné'}<br>
                        Durée: ${event.duration}h<br>
                        Statut: ${getStatusLabel(event.status)}
                    </div>
                `;
                
                element.tooltip({
                    title: tooltip,
                    placement: 'top',
                    html: true,
                    container: 'body'
                });
                
                // Icône de statut
                element.find('.fc-title').prepend(getStatusIcon(event.status) + ' ');
                
                // Marquer les conflits
                if (event.hasConflict) {
                    element.addClass('has-conflict');
                    element.append('<span class="conflict-indicator">⚠️</span>');
                }
            },
            eventClick: function(event) {
                showSessionDetails(event.id);
            },
            select: function(start, end) {
                // Créer une nouvelle session
                showQuickCreateModal(start, end);
            },
            eventDrop: function(event, delta, revertFunc) {
                updateSessionTime(event.id, event.start, event.end, revertFunc);
            },
            eventResize: function(event, delta, revertFunc) {
                updateSessionTime(event.id, event.start, event.end, revertFunc);
            },
            dayClick: function(date, jsEvent, view) {
                // Navigation rapide
                if (view.name === 'month') {
                    calendar.fullCalendar('changeView', 'agendaDay', date);
                }
            }
        });
        
        // Synchroniser les contrôles personnalisés
        updateCalendarTitle();
        
        // Initialiser les filtres
        initFilters();
        
        // Charger les insights
        loadPlanningInsights();
    }
    
    function loadCalendarEvents(start, end, callback) {
        var data = {
            action: 'tmm_get_planning_events',
            start: start.format(),
            end: end.format(),
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        };
        
        // Ajouter les filtres actifs
        Object.assign(data, currentFilters);
        
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                callback(response.data);
            } else {
                callback([]);
            }
        });
    }
    
    function initFilters() {
        // Gestion des filtres
        $('.filter-select').change(function() {
            updateFilters();
            refreshCalendar();
        });
        
        // Période personnalisée
        $('#date-range').change(function() {
            if ($(this).val() === 'custom') {
                $('.custom-date-range').show();
            } else {
                $('.custom-date-range').hide();
                updateDateRange($(this).val());
            }
        });
        
        $('#start-date, #end-date').change(function() {
            if ($('#start-date').val() && $('#end-date').val()) {
                var start = moment($('#start-date').val());
                var end = moment($('#end-date').val());
                calendar.fullCalendar('gotoDate', start);
            }
        });
        
        // Cases à cocher
        $('#show-weekends').change(function() {
            calendar.fullCalendar('option', 'weekends', $(this).is(':checked'));
        });
        
        $('#show-cancelled').change(function() {
            updateFilters();
            refreshCalendar();
        });
    }
    
    function updateFilters() {
        currentFilters = {
            view_type: $('#view-type').val(),
            status: $('#status-filter').val(),
            school_id: $('#school-filter').val(),
            trainer_id: $('#trainer-filter').val(),
            module_id: $('#module-filter').val(),
            show_cancelled: $('#show-cancelled').is(':checked')
        };
    }
    
    function updateDateRange(range) {
        var start, end;
        
        switch(range) {
            case 'current_month':
                start = moment().startOf('month');
                end = moment().endOf('month');
                break;
            case 'next_month':
                start = moment().add(1, 'month').startOf('month');
                end = moment().add(1, 'month').endOf('month');
                break;
            case 'current_quarter':
                start = moment().startOf('quarter');
                end = moment().endOf('quarter');
                break;
        }
        
        if (start && end) {
            calendar.fullCalendar('gotoDate', start);
        }
    }
    
    function refreshCalendar() {
        calendar.fullCalendar('refetchEvents');
        loadPlanningInsights();
    }
    
    // Contrôles de vue
    $('.view-btn').click(function() {
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        
        var view = $(this).data('view');
        calendar.fullCalendar('changeView', view);
        updateCalendarTitle();
    });
    
    // Navigation
    $('#calendar-prev').click(function() {
        calendar.fullCalendar('prev');
        updateCalendarTitle();
    });
    
    $('#calendar-next').click(function() {
        calendar.fullCalendar('next');
        updateCalendarTitle();
    });
    
    $('#calendar-today').click(function() {
        calendar.fullCalendar('today');
        updateCalendarTitle();
    });
    
    function updateCalendarTitle() {
        var view = calendar.fullCalendar('getView');
        $('#calendar-title').text(view.title);
    }
    
    // Actions rapides
    $('#refresh-planning').click(function() {
        refreshCalendar();
    });
    
    $('#export-planning').click(function() {
        exportPlanning();
    });
    
    $('#print-planning').click(function() {
        window.print();
    });
    
    $('#detect-conflicts').click(function() {
        detectConflicts();
    });
    
    $('#optimize-planning').click(function() {
        optimizePlanning();
    });
    
    $('#new-session-quick').click(function() {
        showQuickCreateModal();
    });
    
    // Plein écran
    $('#calendar-fullscreen').click(function() {
        var elem = document.querySelector('.calendar-container');
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        }
    });
    
    function showSessionDetails(sessionId) {
        $.post(ajaxurl, {
            action: 'tmm_get_session_details',
            session_id: sessionId,
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#session-modal-title').text(response.data.title);
                $('#session-details-content').html(buildSessionDetailsHTML(response.data));
                $('#session-details-modal').show();
            }
        });
    }
    
    function buildSessionDetailsHTML(session) {
        return `
            <div class="session-detail-grid">
                <div class="detail-section">
                    <h4>📚 Module</h4>
                    <p><strong>${session.module_name}</strong></p>
                    <p>${session.module_description}</p>
                </div>
                
                <div class="detail-section">
                    <h4>🏫 École</h4>
                    <p><strong>${session.school_name}</strong></p>
                    <p>Contact: ${session.school_contact}</p>
                </div>
                
                <div class="detail-section">
                    <h4>👨‍🏫 Formateur</h4>
                    <p><strong>${session.trainer_name || 'Non assigné'}</strong></p>
                    ${session.trainer_email ? '<p>' + session.trainer_email + '</p>' : ''}
                </div>
                
                <div class="detail-section">
                    <h4>⏰ Planification</h4>
                    <p><strong>Début:</strong> ${session.start_datetime}</p>
                    <p><strong>Fin:</strong> ${session.end_datetime}</p>
                    <p><strong>Durée:</strong> ${session.duration}h</p>
                    <p><strong>Lieu:</strong> ${session.location || 'À définir'}</p>
                </div>
                
                <div class="detail-section">
                    <h4>📊 Statut</h4>
                    <p><span class="status-badge status-${session.status}">${getStatusLabel(session.status)}</span></p>
                    <p><strong>Créée:</strong> ${session.created_at}</p>
                    ${session.notes ? '<p><strong>Notes:</strong> ' + session.notes + '</p>' : ''}
                </div>
                
                <div class="detail-actions">
                    <button class="button button-primary" onclick="editSession(${session.id})">
                        ✏️ Modifier
                    </button>
                    <button class="button" onclick="duplicateSession(${session.id})">
                        📋 Dupliquer
                    </button>
                    <button class="button button-secondary" onclick="sendNotification(${session.id})">
                        📧 Notifier
                    </button>
                    ${session.status === 'proposed' ? 
                        '<button class="button button-link-delete" onclick="cancelSession(' + session.id + ')">❌ Annuler</button>' : ''}
                </div>
            </div>
        `;
    }
    
    function detectConflicts() {
        $.post(ajaxurl, {
            action: 'tmm_detect_planning_conflicts',
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                if (response.data.length > 0) {
                    displayConflicts(response.data);
                } else {
                    alert('Aucun conflit détecté dans le planning actuel.');
                }
            }
        });
    }
    
    function displayConflicts(conflicts) {
        var html = '<div class="conflicts-list">';
        conflicts.forEach(function(conflict) {
            html += `
                <div class="conflict-item">
                    <h5>${conflict.type}</h5>
                    <p>${conflict.description}</p>
                    <div class="conflict-sessions">
                        ${conflict.sessions.map(s => '<span class="session-tag">' + s + '</span>').join('')}
                    </div>
                    <div class="conflict-actions">
                        <button class="button button-small" onclick="resolveConflict(${conflict.id})">
                            🔧 Résoudre
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        $('#conflicts-list').html(html);
        $('#conflicts-modal').show();
    }
    
    function loadPlanningInsights() {
        $.post(ajaxurl, {
            action: 'tmm_get_planning_insights',
            filters: currentFilters,
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#planning-insights-content').html(buildInsightsHTML(response.data));
            }
        });
    }
    
    function buildInsightsHTML(insights) {
        return `
            <div class="insights-grid">
                <div class="insight-item">
                    <span class="insight-label">Taux d'occupation:</span>
                    <span class="insight-value">${insights.occupation_rate}%</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Formateurs les plus actifs:</span>
                    <span class="insight-value">${insights.top_trainer}</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Créneaux libres:</span>
                    <span class="insight-value">${insights.free_slots}</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Prochaine échéance:</span>
                    <span class="insight-value">${insights.next_deadline}</span>
                </div>
            </div>
        `;
    }
    
    function getStatusLabel(status) {
        const labels = {
            'proposed': 'Proposée',
            'confirmed': 'Confirmée', 
            'in_progress': 'En cours',
            'completed': 'Terminée',
            'cancelled': 'Annulée'
        };
        return labels[status] || status;
    }
    
    function getStatusIcon(status) {
        const icons = {
            'proposed': '📋',
            'confirmed': '✅',
            'in_progress': '🔄',
            'completed': '✔️',
            'cancelled': '❌'
        };
        return icons[status] || '📅';
    }
    
    function exportPlanning() {
        var filters = Object.assign({}, currentFilters);
        var view = calendar.fullCalendar('getView');
        
        var params = new URLSearchParams({
            action: 'tmm_export_planning',
            format: 'excel',
            start_date: view.start.format('YYYY-MM-DD'),
            end_date: view.end.format('YYYY-MM-DD'),
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        });
        
        Object.keys(filters).forEach(key => {
            if (filters[key]) params.append(key, filters[key]);
        });
        
        window.open(ajaxurl + '?' + params.toString());
    }
    
    // Fermeture des modales
    $(document).on('click', '.tmm-modal-close, .tmm-modal', function(e) {
        if (e.target === this) {
            $('.tmm-modal').hide();
        }
    });
});
</script>

<style>
.tmm-planning-page {
    padding: 20px;
    background: #f5f7fa;
    min-height: 100vh;
}

.planning-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 25px;
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

.header-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-item {
    text-align: center;
    background: rgba(255,255,255,0.15);
    padding: 15px;
    border-radius: 10px;
    backdrop-filter: blur(10px);
}

.stat-item.urgent {
    background: rgba(255,107,107,0.2);
    border: 1px solid rgba(255,255,255,0.3);
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.planning-controls {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

.controls-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.controls-section h3 {
    margin: 0 0 20px 0;
    color: #2c3e50;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 2px solid #f1f3f4;
    padding-bottom: 10px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 5px;
    font-weight: 500;
    color: #495057;
    font-size: 13px;
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    background: white;
}

.filter-select:focus {
    outline: none;
    border-color: #667eea;
}

.custom-date-range {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.date-inputs {
    display: flex;
    align-items: center;
    gap: 10px;
}

.date-inputs input {
    padding: 8px 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.calendar-toolbar {
    background: white;
    padding: 20px 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.view-switcher {
    display: flex;
    gap: 5px;
}

.view-btn {
    padding: 8px 15px;
    border: 2px solid #dee2e6;
    background: white;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-btn:hover {
    border-color: #667eea;
    color: #667eea;
}

.view-btn.active {
    background: #667eea;
    border-color: #667eea;
    color: white;
}

.calendar-navigation {
    display: flex;
    align-items: center;
    gap: 15px;
}

.nav-btn {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 13px;
}

.nav-btn:hover {
    background: #f8f9fa;
    border-color: #667eea;
}

#calendar-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    min-width: 200px;
    text-align: center;
}

.calendar-options {
    display: flex;
    align-items: center;
    gap: 15px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: #6c757d;
    cursor: pointer;
}

.calendar-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 25px;
}

.tmm-calendar {
    padding: 25px;
    min-height: 600px;
}

/* Styles pour FullCalendar */
.fc-event {
    border-radius: 6px !important;
    border: none !important;
    font-size: 12px !important;
    padding: 2px 6px !important;
    margin: 1px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    transition: all 0.3s ease !important;
}

.fc-event:hover {
    transform: scale(1.02) !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
}

.session-event.status-proposed {
    background: #ffa726 !important;
    color: white !important;
}

.session-event.status-confirmed {
    background: #66bb6a !important;
    color: white !important;
}

.session-event.status-in-progress {
    background: #42a5f5 !important;
    color: white !important;
}

.session-event.status-completed {
    background: #26c6da !important;
    color: white !important;
}

.session-event.status-cancelled {
    background: #ef5350 !important;
    color: white !important;
    opacity: 0.7;
}

.session-event.has-conflict {
    border: 2px solid #ff6b6b !important;
    animation: pulse 2s infinite;
}

.conflict-indicator {
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 10px;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(255, 107, 107, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0); }
}

.planning-footer {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.planning-legend,
.planning-insights {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.planning-legend h4,
.planning-insights h4 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    font-size: 16px;
    font-weight: 600;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #6c757d;
}

.color-box {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.color-box.status-proposed { background: #ffa726; }
.color-box.status-confirmed { background: #66bb6a; }
.color-box.status-in-progress { background: #42a5f5; }
.color-box.status-completed { background: #26c6da; }
.color-box.status-cancelled { background: #ef5350; }
.color-box.status-conflict { background: #ff6b6b; }

.insights-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.insight-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.insight-label {
    font-size: 13px;
    color: #6c757d;
}

.insight-value {
    font-weight: 600;
    color: #2c3e50;
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
    max-width: 800px;
    box-shadow: 0 10px 50px rgba(0,0,0,0.3);
    max-height: 80vh;
    overflow-y: auto;
}

.tmm-modal-content.large {
    max-width: 1000px;
}

.tmm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 15px 15px 0 0;
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

.session-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

.detail-section {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.detail-section h4 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 14px;
    font-weight: 600;
}

.detail-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.status-proposed {
    background: rgba(255, 167, 38, 0.15);
    color: #ffa726;
}

.status-badge.status-confirmed {
    background: rgba(102, 187, 106, 0.15);
    color: #66bb6a;
}

.conflicts-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.conflict-item {
    padding: 15px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
}

.conflict-item h5 {
    margin: 0 0 10px 0;
    color: #856404;
}

.conflict-sessions {
    margin: 10px 0;
}

.session-tag {
    display: inline-block;
    padding: 2px 8px;
    background: #667eea;
    color: white;
    border-radius: 12px;
    font-size: 11px;
    margin-right: 5px;
}

/* Responsive */
@media (max-width: 1200px) {
    .planning-controls {
        grid-template-columns: 1fr;
    }
    
    .filters-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .header-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .tmm-planning-page {
        padding: 10px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .calendar-toolbar {
        flex-direction: column;
        gap: 15px;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .planning-footer {
        grid-template-columns: 1fr;
    }
    
    .session-detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>