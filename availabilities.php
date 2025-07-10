<?php
/**
 * Template pour la gestion des disponibilités des formateurs
 * Interface avancée de planification et optimisation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifications de sécurité
if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé');
}

// Récupérer les formateurs
$trainers = get_users(array('role' => 'tmm_trainer'));

// Statistiques globales des disponibilités
global $wpdb;
$availability_table = $wpdb->prefix . 'tmm_availabilities';
$sessions_table = $wpdb->prefix . 'tmm_sessions_meta';

$availability_stats = array(
    'total_trainers' => count($trainers),
    'trainers_with_availability' => $wpdb->get_var(
        "SELECT COUNT(DISTINCT user_id) FROM $availability_table 
         WHERE start_datetime >= CURDATE()"
    ),
    'total_slots_this_week' => $wpdb->get_var(
        "SELECT COUNT(*) FROM $availability_table 
         WHERE YEARWEEK(start_datetime) = YEARWEEK(NOW())
         AND availability_type = 'available'"
    ),
    'conflicts_detected' => $wpdb->get_var(
        "SELECT COUNT(*) FROM (
            SELECT a1.user_id, a1.start_datetime, a1.end_datetime
            FROM $availability_table a1
            JOIN $availability_table a2 ON a1.user_id = a2.user_id
            WHERE a1.id != a2.id
            AND a1.start_datetime < a2.end_datetime
            AND a1.end_datetime > a2.start_datetime
            AND a1.start_datetime >= CURDATE()
        ) conflicts"
    )
);

// Calcul du taux d'occupation moyen
$occupation_rate = 0;
if ($availability_stats['total_trainers'] > 0) {
    $total_available_hours = $wpdb->get_var(
        "SELECT SUM(TIMESTAMPDIFF(HOUR, start_datetime, end_datetime)) 
         FROM $availability_table 
         WHERE availability_type = 'available' 
         AND YEARWEEK(start_datetime) = YEARWEEK(NOW())"
    );
    
    $total_booked_hours = $wpdb->get_var(
        "SELECT SUM(hours_planned) FROM $sessions_table s
         JOIN {$wpdb->posts} p ON s.session_id = p.ID
         WHERE YEARWEEK(s.start_datetime) = YEARWEEK(NOW())
         AND p.post_status IN ('confirmed', 'in_progress')"
    );
    
    $occupation_rate = $total_available_hours > 0 ? round(($total_booked_hours / $total_available_hours) * 100, 1) : 0;
}
?>

<div class="wrap tmm-availabilities-page">
    <!-- Header avec statistiques -->
    <div class="availabilities-header">
        <div class="header-content">
            <div class="header-title">
                <h1>📅 Gestion des Disponibilités</h1>
                <p class="subtitle">Optimisation intelligente du planning des formateurs</p>
            </div>
            
            <div class="header-stats">
                <div class="stat-card">
                    <div class="stat-icon">👨‍🏫</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $availability_stats['total_trainers']; ?></div>
                        <div class="stat-label">Formateurs Actifs</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $availability_stats['total_slots_this_week']; ?></div>
                        <div class="stat-label">Créneaux Cette Semaine</div>
                    </div>
                </div>
                
                <div class="stat-card <?php echo $availability_stats['conflicts_detected'] > 0 ? 'warning' : 'success'; ?>">
                    <div class="stat-icon"><?php echo $availability_stats['conflicts_detected'] > 0 ? '⚠️' : '✅'; ?></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $availability_stats['conflicts_detected']; ?></div>
                        <div class="stat-label">Conflits Détectés</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $occupation_rate; ?>%</div>
                        <div class="stat-label">Taux d'Occupation</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contrôles et filtres -->
    <div class="availabilities-controls">
        <div class="controls-left">
            <div class="filter-group">
                <label for="trainer-select">Formateur</label>
                <select id="trainer-select" class="filter-select">
                    <option value="">Tous les formateurs</option>
                    <?php foreach ($trainers as $trainer): ?>
                        <option value="<?php echo $trainer->ID; ?>">
                            <?php echo esc_html($trainer->display_name); ?>
                            <?php 
                            $skills = get_user_meta($trainer->ID, 'trainer_skills', true);
                            if ($skills) echo ' - ' . esc_html($skills);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="time-range">Période</label>
                <select id="time-range" class="filter-select">
                    <option value="current_week">Cette semaine</option>
                    <option value="next_week">Semaine prochaine</option>
                    <option value="current_month">Ce mois</option>
                    <option value="next_month">Mois prochain</option>
                    <option value="custom">Personnalisée</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="availability-type">Type</label>
                <select id="availability-type" class="filter-select">
                    <option value="">Tous les types</option>
                    <option value="available">Disponible</option>
                    <option value="unavailable">Indisponible</option>
                    <option value="booked">Réservé</option>
                </select>
            </div>
            
            <div class="custom-date-range" id="custom-date-range" style="display: none;">
                <input type="date" id="start-date" />
                <span>à</span>
                <input type="date" id="end-date" />
            </div>
        </div>
        
        <div class="controls-right">
            <button id="detect-conflicts" class="button">
                🔍 Détecter Conflits
            </button>
            <button id="optimize-planning" class="button">
                ⚡ Optimiser Planning
            </button>
            <button id="bulk-import" class="button">
                📥 Import en Masse
            </button>
            <button id="export-availabilities" class="button">
                📤 Exporter
            </button>
            <button id="add-availability" class="button button-primary">
                ➕ Ajouter Disponibilité
            </button>
        </div>
    </div>

    <!-- Interface principale -->
    <div class="availabilities-interface">
        <!-- Liste des formateurs -->
        <div class="trainers-panel">
            <div class="panel-header">
                <h3>👥 Formateurs</h3>
                <div class="panel-actions">
                    <button id="collapse-all" class="panel-btn">⬆️</button>
                    <button id="expand-all" class="panel-btn">⬇️</button>
                </div>
            </div>
            
            <div class="trainers-list" id="trainers-list">
                <?php foreach ($trainers as $trainer): ?>
                    <?php
                    // Calculer les statistiques du formateur
                    $trainer_stats = array(
                        'available_slots' => $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $availability_table 
                             WHERE user_id = %d AND availability_type = 'available' 
                             AND start_datetime >= CURDATE()",
                            $trainer->ID
                        )),
                        'booked_sessions' => $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $sessions_table s
                             JOIN {$wpdb->posts} p ON s.session_id = p.ID
                             WHERE s.trainer_id = %d 
                             AND p.post_status IN ('confirmed', 'in_progress')
                             AND s.start_datetime >= CURDATE()",
                            $trainer->ID
                        )),
                        'next_session' => $wpdb->get_var($wpdb->prepare(
                            "SELECT MIN(start_datetime) FROM $sessions_table s
                             JOIN {$wpdb->posts} p ON s.session_id = p.ID
                             WHERE s.trainer_id = %d 
                             AND p.post_status IN ('confirmed', 'in_progress')
                             AND s.start_datetime >= NOW()",
                            $trainer->ID
                        ))
                    );
                    ?>
                    
                    <div class="trainer-item" data-trainer-id="<?php echo $trainer->ID; ?>">
                        <div class="trainer-header">
                            <div class="trainer-avatar">
                                <?php echo get_avatar($trainer->ID, 40); ?>
                            </div>
                            <div class="trainer-info">
                                <div class="trainer-name"><?php echo esc_html($trainer->display_name); ?></div>
                                <div class="trainer-email"><?php echo esc_html($trainer->user_email); ?></div>
                                <div class="trainer-skills">
                                    <?php 
                                    $skills = get_user_meta($trainer->ID, 'trainer_skills', true);
                                    if ($skills) {
                                        $skills_array = explode(',', $skills);
                                        foreach (array_slice($skills_array, 0, 3) as $skill) {
                                            echo '<span class="skill-tag">' . esc_html(trim($skill)) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="trainer-status">
                                <div class="status-indicator" id="status-<?php echo $trainer->ID; ?>">
                                    <?php if ($trainer_stats['next_session']): ?>
                                        🟡 <!-- Occupé bientôt -->
                                    <?php elseif ($trainer_stats['available_slots'] > 0): ?>
                                        🟢 <!-- Disponible -->
                                    <?php else: ?>
                                        🔴 <!-- Non disponible -->
                                    <?php endif; ?>
                                </div>
                                <button class="toggle-trainer" data-trainer="<?php echo $trainer->ID; ?>">
                                    <span class="toggle-icon">▼</span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="trainer-details" id="trainer-details-<?php echo $trainer->ID; ?>" style="display: none;">
                            <div class="trainer-stats">
                                <div class="mini-stat">
                                    <span class="mini-stat-number"><?php echo $trainer_stats['available_slots']; ?></span>
                                    <span class="mini-stat-label">Créneaux dispos</span>
                                </div>
                                <div class="mini-stat">
                                    <span class="mini-stat-number"><?php echo $trainer_stats['booked_sessions']; ?></span>
                                    <span class="mini-stat-label">Sessions planifiées</span>
                                </div>
                                <div class="mini-stat">
                                    <span class="mini-stat-label">Prochaine session</span>
                                    <span class="mini-stat-number">
                                        <?php if ($trainer_stats['next_session']): ?>
                                            <?php echo human_time_diff(strtotime($trainer_stats['next_session']), current_time('timestamp')); ?>
                                        <?php else: ?>
                                            Aucune
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="trainer-actions">
                                <button class="action-btn" onclick="viewTrainerCalendar(<?php echo $trainer->ID; ?>)">
                                    📅 Voir Calendrier
                                </button>
                                <button class="action-btn" onclick="addTrainerAvailability(<?php echo $trainer->ID; ?>)">
                                    ➕ Ajouter Dispo
                                </button>
                                <button class="action-btn" onclick="exportTrainerSchedule(<?php echo $trainer->ID; ?>)">
                                    📤 Exporter
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Calendrier des disponibilités -->
        <div class="calendar-panel">
            <div class="panel-header">
                <h3 id="calendar-title">📅 Calendrier des Disponibilités</h3>
                <div class="calendar-controls">
                    <button id="calendar-prev" class="calendar-nav">‹</button>
                    <button id="calendar-today" class="calendar-nav">Aujourd'hui</button>
                    <button id="calendar-next" class="calendar-nav">›</button>
                    <select id="calendar-view" class="view-select">
                        <option value="agendaWeek">Semaine</option>
                        <option value="month">Mois</option>
                        <option value="agendaDay">Jour</option>
                    </select>
                </div>
            </div>
            
            <div id="availability-calendar" class="calendar-container">
                <!-- Calendrier FullCalendar chargé via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Panel d'insights et optimisations -->
    <div class="insights-panel">
        <div class="insights-section">
            <h3>🧠 Insights Intelligents</h3>
            <div id="smart-insights">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
        
        <div class="optimization-section">
            <h3>⚡ Optimisations Suggérées</h3>
            <div id="optimization-suggestions">
                <!-- Suggestions chargées dynamiquement -->
            </div>
        </div>
    </div>
</div>

<!-- Modal ajout de disponibilité -->
<div id="availability-modal" class="tmm-modal">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h3>➕ Ajouter une Disponibilité</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <form id="availability-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="av-trainer">Formateur</label>
                        <select id="av-trainer" required>
                            <option value="">Sélectionner un formateur</option>
                            <?php foreach ($trainers as $trainer): ?>
                                <option value="<?php echo $trainer->ID; ?>"><?php echo esc_html($trainer->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="av-type">Type</label>
                        <select id="av-type" required>
                            <option value="available">Disponible</option>
                            <option value="unavailable">Indisponible</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="av-start-date">Date de début</label>
                        <input type="date" id="av-start-date" required>
                    </div>
                    <div class="form-group">
                        <label for="av-end-date">Date de fin</label>
                        <input type="date" id="av-end-date" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="av-start-time">Heure de début</label>
                        <input type="time" id="av-start-time" value="09:00" required>
                    </div>
                    <div class="form-group">
                        <label for="av-end-time">Heure de fin</label>
                        <input type="time" id="av-end-time" value="17:00" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="av-recurring"> 
                        Récurrent
                    </label>
                </div>
                
                <div id="recurring-options" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="av-frequency">Fréquence</label>
                            <select id="av-frequency">
                                <option value="weekly">Hebdomadaire</option>
                                <option value="daily">Quotidien</option>
                                <option value="monthly">Mensuel</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="av-until">Jusqu'au</label>
                            <input type="date" id="av-until">
                        </div>
                    </div>
                    
                    <div class="form-group" id="weekdays-selection">
                        <label>Jours de la semaine</label>
                        <div class="weekdays-checkboxes">
                            <label><input type="checkbox" value="1"> Lun</label>
                            <label><input type="checkbox" value="2"> Mar</label>
                            <label><input type="checkbox" value="3"> Mer</label>
                            <label><input type="checkbox" value="4"> Jeu</label>
                            <label><input type="checkbox" value="5"> Ven</label>
                            <label><input type="checkbox" value="6"> Sam</label>
                            <label><input type="checkbox" value="0"> Dim</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="av-notes">Notes (optionnel)</label>
                    <textarea id="av-notes" rows="3" placeholder="Commentaires, contraintes spéciales..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="button" onclick="closeModal()">Annuler</button>
                    <button type="button" class="button" onclick="detectAvailabilityConflicts()">🔍 Vérifier Conflits</button>
                    <button type="submit" class="button button-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal conflits -->
<div id="conflicts-modal" class="tmm-modal">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h3>⚠️ Conflits de Disponibilités</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <div id="conflicts-content">
                <!-- Contenu des conflits -->
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let availabilityCalendar;
    let currentFilters = {};
    let selectedTrainer = null;
    
    // Initialisation
    initAvailabilitiesPage();
    
    function initAvailabilitiesPage() {
        // Initialiser le calendrier
        initAvailabilityCalendar();
        
        // Charger les données initiales
        loadAvailabilityStats();
        loadSmartInsights();
        loadOptimizationSuggestions();
        
        // Initialiser les événements
        initEventListeners();
    }
    
    function initAvailabilityCalendar() {
        availabilityCalendar = $('#availability-calendar').fullCalendar({
            locale: 'fr',
            defaultView: 'agendaWeek',
            height: 600,
            minTime: '07:00:00',
            maxTime: '20:00:00',
            slotDuration: '00:30:00',
            businessHours: {
                dow: [1, 2, 3, 4, 5],
                start: '08:00',
                end: '18:00'
            },
            selectable: true,
            selectHelper: true,
            editable: true,
            events: function(start, end, timezone, callback) {
                loadAvailabilityEvents(start, end, callback);
            },
            select: function(start, end) {
                if (selectedTrainer) {
                    showQuickAvailabilityModal(selectedTrainer, start, end);
                } else {
                    alert('Veuillez d\'abord sélectionner un formateur');
                }
                availabilityCalendar.fullCalendar('unselect');
            },
            eventClick: function(event) {
                showAvailabilityDetails(event.id);
            },
            eventDrop: function(event, delta, revertFunc) {
                updateAvailabilityTime(event.id, event.start, event.end, revertFunc);
            },
            eventResize: function(event, delta, revertFunc) {
                updateAvailabilityTime(event.id, event.start, event.end, revertFunc);
            },
            eventRender: function(event, element) {
                // Personnaliser l'affichage selon le type
                element.addClass('availability-event type-' + event.type);
                
                if (event.type === 'unavailable') {
                    element.addClass('unavailable-slot');
                    element.find('.fc-title').prepend('🚫 ');
                } else if (event.type === 'available') {
                    element.addClass('available-slot');
                    element.find('.fc-title').prepend('✅ ');
                } else if (event.type === 'booked') {
                    element.addClass('booked-slot');
                    element.find('.fc-title').prepend('📚 ');
                }
                
                // Tooltip avec détails
                element.tooltip({
                    title: buildAvailabilityTooltip(event),
                    placement: 'top',
                    html: true
                });
            }
        });
    }
    
    function initEventListeners() {
        // Filtres
        $('#trainer-select, #time-range, #availability-type').change(function() {
            updateFilters();
            refreshCalendar();
            if ($('#trainer-select').val()) {
                selectTrainer($('#trainer-select').val());
            }
        });
        
        // Période personnalisée
        $('#time-range').change(function() {
            if ($(this).val() === 'custom') {
                $('#custom-date-range').show();
            } else {
                $('#custom-date-range').hide();
            }
        });
        
        // Navigation calendrier
        $('#calendar-prev').click(function() {
            availabilityCalendar.fullCalendar('prev');
        });
        
        $('#calendar-next').click(function() {
            availabilityCalendar.fullCalendar('next');
        });
        
        $('#calendar-today').click(function() {
            availabilityCalendar.fullCalendar('today');
        });
        
        $('#calendar-view').change(function() {
            availabilityCalendar.fullCalendar('changeView', $(this).val());
        });
        
        // Actions
        $('#detect-conflicts').click(detectConflicts);
        $('#optimize-planning').click(optimizePlanning);
        $('#export-availabilities').click(exportAvailabilities);
        $('#add-availability').click(function() {
            $('#availability-modal').show();
        });
        
        // Gestion des formateurs
        $('.toggle-trainer').click(function() {
            var trainerId = $(this).data('trainer');
            var $details = $('#trainer-details-' + trainerId);
            var $icon = $(this).find('.toggle-icon');
            
            if ($details.is(':visible')) {
                $details.slideUp();
                $icon.text('▼');
            } else {
                $details.slideDown();
                $icon.text('▲');
            }
        });
        
        $('.trainer-item').click(function(e) {
            if (!$(e.target).hasClass('toggle-trainer') && !$(e.target).closest('.trainer-actions').length) {
                var trainerId = $(this).data('trainer-id');
                selectTrainer(trainerId);
            }
        });
        
        // Formulaire de disponibilité
        $('#availability-form').submit(function(e) {
            e.preventDefault();
            saveAvailability();
        });
        
        $('#av-recurring').change(function() {
            if ($(this).is(':checked')) {
                $('#recurring-options').show();
            } else {
                $('#recurring-options').hide();
            }
        });
        
        // Fermeture modales
        $('.tmm-modal-close, .tmm-modal').click(function(e) {
            if (e.target === this) {
                $('.tmm-modal').hide();
            }
        });
    }
    
    function selectTrainer(trainerId) {
        // Désélectionner tous les formateurs
        $('.trainer-item').removeClass('selected');
        
        // Sélectionner le formateur
        $('.trainer-item[data-trainer-id="' + trainerId + '"]').addClass('selected');
        selectedTrainer = trainerId;
        
        // Mettre à jour les filtres
        $('#trainer-select').val(trainerId);
        updateFilters();
        refreshCalendar();
        
        // Mettre à jour le titre
        var trainerName = $('.trainer-item[data-trainer-id="' + trainerId + '"] .trainer-name').text();
        $('#calendar-title').text('📅 Calendrier de ' + trainerName);
    }
    
    function updateFilters() {
        currentFilters = {
            trainer_id: $('#trainer-select').val(),
            time_range: $('#time-range').val(),
            availability_type: $('#availability-type').val(),
            start_date: $('#start-date').val(),
            end_date: $('#end-date').val()
        };
    }
    
    function refreshCalendar() {
        availabilityCalendar.fullCalendar('refetchEvents');
    }
    
    function loadAvailabilityEvents(start, end, callback) {
        var data = {
            action: 'tmm_get_availability_events',
            start: start.format(),
            end: end.format(),
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        };
        
        // Ajouter les filtres
        Object.assign(data, currentFilters);
        
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                callback(response.data);
            } else {
                callback([]);
            }
        });
    }
    
    function loadAvailabilityStats() {
        $.post(ajaxurl, {
            action: 'tmm_get_availability_stats',
            filters: currentFilters,
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                updateStatsDisplay(response.data);
            }
        });
    }
    
    function loadSmartInsights() {
        $.post(ajaxurl, {
            action: 'tmm_get_smart_insights',
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#smart-insights').html(buildInsightsHTML(response.data));
            }
        });
    }
    
    function loadOptimizationSuggestions() {
        $.post(ajaxurl, {
            action: 'tmm_get_optimization_suggestions',
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#optimization-suggestions').html(buildOptimizationsHTML(response.data));
            }
        });
    }
    
    function saveAvailability() {
        var formData = {
            action: 'tmm_save_availability',
            trainer_id: $('#av-trainer').val(),
            type: $('#av-type').val(),
            start_date: $('#av-start-date').val(),
            end_date: $('#av-end-date').val(),
            start_time: $('#av-start-time').val(),
            end_time: $('#av-end-time').val(),
            is_recurring: $('#av-recurring').is(':checked'),
            frequency: $('#av-frequency').val(),
            until_date: $('#av-until').val(),
            weekdays: getSelectedWeekdays(),
            notes: $('#av-notes').val(),
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        };
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                alert('Disponibilité enregistrée avec succès!');
                $('#availability-modal').hide();
                refreshCalendar();
                loadAvailabilityStats();
                resetAvailabilityForm();
            } else {
                alert('Erreur: ' + response.data);
            }
        });
    }
    
    function detectConflicts() {
        $.post(ajaxurl, {
            action: 'tmm_detect_availability_conflicts',
            filters: currentFilters,
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                if (response.data.length > 0) {
                    displayConflicts(response.data);
                } else {
                    alert('Aucun conflit détecté dans les disponibilités actuelles.');
                }
            }
        });
    }
    
    function optimizePlanning() {
        if (confirm('Lancer l\'optimisation automatique du planning? Cette action peut modifier certaines disponibilités.')) {
            $.post(ajaxurl, {
                action: 'tmm_optimize_planning',
                nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Optimisation terminée! ' + response.data.changes + ' modifications effectuées.');
                    refreshCalendar();
                    loadOptimizationSuggestions();
                } else {
                    alert('Erreur lors de l\'optimisation: ' + response.data);
                }
            });
        }
    }
    
    function exportAvailabilities() {
        var params = new URLSearchParams({
            action: 'tmm_export_availabilities',
            format: 'excel',
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        });
        
        // Ajouter les filtres
        Object.keys(currentFilters).forEach(key => {
            if (currentFilters[key]) {
                params.append(key, currentFilters[key]);
            }
        });
        
        window.open(ajaxurl + '?' + params.toString());
    }
    
    function buildAvailabilityTooltip(event) {
        return `
            <div class="availability-tooltip">
                <strong>${event.title}</strong><br>
                Formateur: ${event.trainer_name}<br>
                Type: ${event.type === 'available' ? 'Disponible' : event.type === 'unavailable' ? 'Indisponible' : 'Réservé'}<br>
                Durée: ${event.duration}h<br>
                ${event.notes ? 'Notes: ' + event.notes : ''}
            </div>
        `;
    }
    
    function buildInsightsHTML(insights) {
        var html = '<div class="insights-list">';
        insights.forEach(function(insight) {
            html += `
                <div class="insight-item ${insight.type}">
                    <div class="insight-icon">${insight.icon}</div>
                    <div class="insight-content">
                        <div class="insight-title">${insight.title}</div>
                        <div class="insight-description">${insight.description}</div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }
    
    function buildOptimizationsHTML(optimizations) {
        var html = '<div class="optimizations-list">';
        optimizations.forEach(function(opt) {
            html += `
                <div class="optimization-item">
                    <div class="optimization-header">
                        <span class="optimization-impact ${opt.impact}">${opt.impact_label}</span>
                        <span class="optimization-title">${opt.title}</span>
                    </div>
                    <div class="optimization-description">${opt.description}</div>
                    <div class="optimization-actions">
                        <button class="button button-small" onclick="applyOptimization(${opt.id})">
                            ✅ Appliquer
                        </button>
                        <button class="button button-small" onclick="dismissOptimization(${opt.id})">
                            ❌ Ignorer
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }
    
    function getSelectedWeekdays() {
        var weekdays = [];
        $('#weekdays-selection input:checked').each(function() {
            weekdays.push($(this).val());
        });
        return weekdays;
    }
    
    function resetAvailabilityForm() {
        $('#availability-form')[0].reset();
        $('#recurring-options').hide();
        $('#av-recurring').prop('checked', false);
    }
    
    // Fonctions globales pour les actions
    window.viewTrainerCalendar = function(trainerId) {
        selectTrainer(trainerId);
    };
    
    window.addTrainerAvailability = function(trainerId) {
        $('#av-trainer').val(trainerId);
        $('#availability-modal').show();
    };
    
    window.exportTrainerSchedule = function(trainerId) {
        window.open(ajaxurl + '?action=tmm_export_trainer_schedule&trainer_id=' + trainerId + '&nonce=' + '<?php echo wp_create_nonce('tmm_nonce'); ?>');
    };
    
    window.applyOptimization = function(optimizationId) {
        if (confirm('Appliquer cette optimisation?')) {
            $.post(ajaxurl, {
                action: 'tmm_apply_optimization',
                optimization_id: optimizationId,
                nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Optimisation appliquée!');
                    refreshCalendar();
                    loadOptimizationSuggestions();
                }
            });
        }
    };
    
    window.dismissOptimization = function(optimizationId) {
        $.post(ajaxurl, {
            action: 'tmm_dismiss_optimization',
            optimization_id: optimizationId,
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                loadOptimizationSuggestions();
            }
        });
    };
    
    window.closeModal = function() {
        $('.tmm-modal').hide();
    };
});
</script>

<style>
.tmm-availabilities-page {
    padding: 20px;
    background: #f5f7fa;
    min-height: 100vh;
}

.availabilities-header {
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

.stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255,255,255,0.15);
    padding: 20px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.stat-card:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.stat-card.warning {
    background: rgba(255,167,38,0.2);
    border: 1px solid rgba(255,255,255,0.3);
}

.stat-card.success {
    background: rgba(102,187,106,0.2);
    border: 1px solid rgba(255,255,255,0.3);
}

.stat-icon {
    font-size: 24px;
}

.stat-number {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 2px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.availabilities-controls {
    display: flex;
    justify-content: space-between;
    align-items: end;
    background: white;
    padding: 20px 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    flex-wrap: wrap;
    gap: 20px;
}

.controls-left {
    display: flex;
    gap: 20px;
    align-items: end;
    flex-wrap: wrap;
}

.controls-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 500;
    color: #495057;
    font-size: 13px;
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
    transition: border-color 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #667eea;
}

.custom-date-range {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.custom-date-range input {
    padding: 8px 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.availabilities-interface {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

.trainers-panel,
.calendar-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.panel-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 16px;
    font-weight: 600;
}

.panel-actions {
    display: flex;
    gap: 5px;
}

.panel-btn {
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.3s ease;
}

.panel-btn:hover {
    background: #f8f9fa;
    border-color: #667eea;
}

.trainers-list {
    max-height: 600px;
    overflow-y: auto;
    padding: 15px;
}

.trainer-item {
    margin-bottom: 15px;
    border: 2px solid #f1f3f4;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.trainer-item:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.trainer-item.selected {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.trainer-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
}

.trainer-avatar img {
    border-radius: 50%;
}

.trainer-info {
    flex: 1;
}

.trainer-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.trainer-email {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
}

.trainer-skills {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.skill-tag {
    background: rgba(102, 126, 234, 0.15);
    color: #667eea;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 500;
}

.trainer-status {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-indicator {
    font-size: 18px;
}

.toggle-trainer {
    background: none;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 4px 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.toggle-trainer:hover {
    background: #f8f9fa;
    border-color: #667eea;
}

.trainer-details {
    padding: 15px;
    border-top: 1px solid #f1f3f4;
    background: #fafbfc;
}

.trainer-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.mini-stat {
    text-align: center;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border: 1px solid #f1f3f4;
}

.mini-stat-number {
    display: block;
    font-weight: 700;
    color: #667eea;
    font-size: 16px;
    margin-bottom: 2px;
}

.mini-stat-label {
    font-size: 10px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.trainer-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.action-btn {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
    text-align: center;
    transition: all 0.3s ease;
}

.action-btn:hover {
    background: #f8f9fa;
    border-color: #667eea;
    color: #667eea;
}

.calendar-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.calendar-nav {
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.3s ease;
}

.calendar-nav:hover {
    background: #f8f9fa;
    border-color: #667eea;
}

.view-select {
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 13px;
}

.calendar-container {
    padding: 25px;
    min-height: 600px;
}

/* Styles pour les événements de disponibilité */
.availability-event {
    border-radius: 4px !important;
    font-size: 11px !important;
    padding: 2px 4px !important;
    margin: 1px !important;
}

.availability-event.type-available {
    background: #66bb6a !important;
    border-color: #4caf50 !important;
}

.availability-event.type-unavailable {
    background: #ef5350 !important;
    border-color: #f44336 !important;
}

.availability-event.type-booked {
    background: #42a5f5 !important;
    border-color: #2196f3 !important;
}

.insights-panel {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.insights-section,
.optimization-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.insights-section h3,
.optimization-section h3 {
    margin: 0 0 20px 0;
    color: #2c3e50;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.insights-list,
.optimizations-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.insight-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.insight-item.warning {
    border-left-color: #ffa726;
}

.insight-item.success {
    border-left-color: #66bb6a;
}

.insight-item.info {
    border-left-color: #42a5f5;
}

.insight-icon {
    font-size: 20px;
}

.insight-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.insight-description {
    font-size: 13px;
    color: #6c757d;
}

.optimization-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.optimization-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.optimization-impact {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
}

.optimization-impact.high {
    background: rgba(76, 175, 80, 0.15);
    color: #4caf50;
}

.optimization-impact.medium {
    background: rgba(255, 167, 38, 0.15);
    color: #ffa726;
}

.optimization-impact.low {
    background: rgba(158, 158, 158, 0.15);
    color: #9e9e9e;
}

.optimization-title {
    font-weight: 600;
    color: #2c3e50;
}

.optimization-description {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 10px;
}

.optimization-actions {
    display: flex;
    gap: 8px;
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
    max-height: 80vh;
    overflow-y: auto;
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: 500;
    color: #495057;
    font-size: 13px;
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

.weekdays-checkboxes {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.weekdays-checkboxes label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

/* Responsive */
@media (max-width: 1200px) {
    .availabilities-interface {
        grid-template-columns: 300px 1fr;
    }
    
    .header-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 968px) {
    .tmm-availabilities-page {
        padding: 10px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .availabilities-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .controls-left {
        justify-content: center;
    }
    
    .availabilities-interface {
        grid-template-columns: 1fr;
    }
    
    .insights-panel {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>