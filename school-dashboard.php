<?php
/**
 * Template pour le tableau de bord des écoles partenaires
 * Shortcode: [tmm_school_dashboard]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifications de sécurité
if (!is_user_logged_in() || !current_user_can('partner_school')) {
    echo '<div class="tmm-access-denied">
            <h3>Accès restreint</h3>
            <p>Cette page est réservée aux écoles partenaires de TeachMeMore.</p>
            <p><a href="' . wp_login_url() . '">Se connecter</a></p>
          </div>';
    return;
}

$current_user = wp_get_current_user();
$school_id = get_user_meta($current_user->ID, 'school_id', true);
$school = get_post($school_id);

if (!$school) {
    echo '<div class="tmm-error">
            <p>Erreur: École non trouvée. Contactez l\'administration.</p>
          </div>';
    return;
}

// Récupérer les données pour le dashboard
global $wpdb;
$sessions_table = $wpdb->prefix . 'tmm_sessions_meta';

// Sessions en attente de réponse
$pending_sessions = $wpdb->get_results($wpdb->prepare(
    "SELECT s.*, p.post_title as session_title, m.post_title as module_title, u.display_name as trainer_name
     FROM $sessions_table s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     LEFT JOIN {$wpdb->posts} m ON s.module_id = m.ID
     LEFT JOIN {$wpdb->users} u ON s.trainer_id = u.ID
     WHERE s.school_id = %d AND p.post_status = 'proposed'
     ORDER BY s.start_datetime ASC",
    $school_id
));

// Sessions confirmées à venir
$upcoming_sessions = $wpdb->get_results($wpdb->prepare(
    "SELECT s.*, p.post_title as session_title, m.post_title as module_title, u.display_name as trainer_name
     FROM $sessions_table s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     LEFT JOIN {$wpdb->posts} m ON s.module_id = m.ID
     LEFT JOIN {$wpdb->users} u ON s.trainer_id = u.ID
     WHERE s.school_id = %d AND p.post_status IN ('confirmed', 'in_progress') 
     AND s.start_datetime > NOW()
     ORDER BY s.start_datetime ASC",
    $school_id
));

// Sessions en cours ou récentes
$recent_sessions = $wpdb->get_results($wpdb->prepare(
    "SELECT s.*, p.post_title as session_title, m.post_title as module_title, u.display_name as trainer_name
     FROM $sessions_table s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     LEFT JOIN {$wpdb->posts} m ON s.module_id = m.ID
     LEFT JOIN {$wpdb->users} u ON s.trainer_id = u.ID
     WHERE s.school_id = %d AND p.post_status IN ('in_progress', 'completed')
     AND s.start_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY s.start_datetime DESC",
    $school_id
));

// Statistiques
$total_hours_planned = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(hours_planned) FROM $sessions_table s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     WHERE s.school_id = %d AND p.post_status != 'cancelled'",
    $school_id
));

$total_hours_realized = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(hours_realized) FROM $sessions_table s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     WHERE s.school_id = %d AND p.post_status = 'completed'",
    $school_id
));
?>

<div class="tmm-school-dashboard">
    <!-- En-tête -->
    <div class="tmm-dashboard-header">
        <div class="school-info">
            <h1>Tableau de bord - <?php echo esc_html($school->post_title); ?></h1>
            <p class="welcome-message">Bienvenue <?php echo esc_html($current_user->display_name); ?></p>
            <div class="last-connection">
                Dernière connexion: <?php echo date('d/m/Y à H:i'); ?>
            </div>
        </div>
        
        <div class="quick-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo count($pending_sessions); ?></span>
                <span class="stat-label">Propositions en attente</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count($upcoming_sessions); ?></span>
                <span class="stat-label">Sessions à venir</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo round($total_hours_planned ?: 0); ?>h</span>
                <span class="stat-label">Heures planifiées</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo round($total_hours_realized ?: 0); ?>h</span>
                <span class="stat-label">Heures réalisées</span>
            </div>
        </div>
    </div>

    <!-- Notifications urgentes -->
    <?php if (count($pending_sessions) > 0): ?>
    <div class="tmm-alert tmm-alert-warning">
        <h3>⏰ Actions requises</h3>
        <p>Vous avez <?php echo count($pending_sessions); ?> proposition(s) de session en attente de votre réponse.</p>
    </div>
    <?php endif; ?>

    <!-- Navigation par onglets -->
    <div class="tmm-dashboard-tabs">
        <nav class="tmm-tabs-nav">
            <button class="tmm-tab-button active" data-tab="pending">
                Propositions (<?php echo count($pending_sessions); ?>)
            </button>
            <button class="tmm-tab-button" data-tab="upcoming">
                Prochaines sessions (<?php echo count($upcoming_sessions); ?>)
            </button>
            <button class="tmm-tab-button" data-tab="calendar">
                Calendrier
            </button>
            <button class="tmm-tab-button" data-tab="recent">
                Historique
            </button>
            <button class="tmm-tab-button" data-tab="resources">
                Ressources
            </button>
        </nav>

        <!-- Onglet Propositions en attente -->
        <div id="pending" class="tmm-tab-content active">
            <h2>📋 Propositions en attente de votre réponse</h2>
            
            <?php if (empty($pending_sessions)): ?>
                <div class="tmm-empty-state">
                    <p>✅ Aucune proposition en attente. Tout est à jour !</p>
                </div>
            <?php else: ?>
                <div class="tmm-sessions-grid">
                    <?php foreach ($pending_sessions as $session): ?>
                    <div class="tmm-session-card pending">
                        <div class="session-header">
                            <h3><?php echo esc_html($session->module_title); ?></h3>
                            <span class="session-status status-proposed">Proposée</span>
                        </div>
                        
                        <div class="session-details">
                            <div class="detail-item">
                                <span class="detail-label">📅 Date:</span>
                                <span class="detail-value"><?php echo date('d/m/Y', strtotime($session->start_datetime)); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">🕐 Horaires:</span>
                                <span class="detail-value">
                                    <?php echo date('H:i', strtotime($session->start_datetime)); ?> - 
                                    <?php echo date('H:i', strtotime($session->end_datetime)); ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">⏱️ Durée:</span>
                                <span class="detail-value"><?php echo $session->hours_planned; ?>h</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">👨‍🏫 Formateur:</span>
                                <span class="detail-value"><?php echo esc_html($session->trainer_name ?: 'À assigner'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">📍 Lieu:</span>
                                <span class="detail-value"><?php echo esc_html($session->location ?: 'À définir'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">👥 Groupe:</span>
                                <span class="detail-value"><?php echo esc_html($session->group_name ?: 'À préciser'); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($session->notes): ?>
                        <div class="session-notes">
                            <strong>Notes:</strong> <?php echo esc_html($session->notes); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="session-actions">
                            <button class="tmm-btn tmm-btn-success" onclick="acceptSession(<?php echo $session->session_id; ?>)">
                                ✅ Accepter
                            </button>
                            <button class="tmm-btn tmm-btn-warning" onclick="proposeAlternative(<?php echo $session->session_id; ?>)">
                                📝 Proposer alternative
                            </button>
                            <button class="tmm-btn tmm-btn-danger" onclick="rejectSession(<?php echo $session->session_id; ?>)">
                                ❌ Refuser
                            </button>
                            <button class="tmm-btn tmm-btn-secondary" onclick="viewModuleDetails(<?php echo $session->module_id; ?>)">
                                📖 Voir module
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Sessions à venir -->
        <div id="upcoming" class="tmm-tab-content">
            <h2>🗓️ Prochaines sessions confirmées</h2>
            
            <?php if (empty($upcoming_sessions)): ?>
                <div class="tmm-empty-state">
                    <p>Aucune session confirmée pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="tmm-sessions-list">
                    <?php foreach ($upcoming_sessions as $session): ?>
                    <div class="tmm-session-row confirmed">
                        <div class="session-date">
                            <span class="date"><?php echo date('d', strtotime($session->start_datetime)); ?></span>
                            <span class="month"><?php echo date('M', strtotime($session->start_datetime)); ?></span>
                        </div>
                        
                        <div class="session-info">
                            <h4><?php echo esc_html($session->module_title); ?></h4>
                            <div class="session-meta">
                                <span>🕐 <?php echo date('H:i', strtotime($session->start_datetime)); ?> - <?php echo date('H:i', strtotime($session->end_datetime)); ?></span>
                                <span>📍 <?php echo esc_html($session->location); ?></span>
                                <span>👨‍🏫 <?php echo esc_html($session->trainer_name); ?></span>
                            </div>
                        </div>
                        
                        <div class="session-status">
                            <span class="status-confirmed">Confirmée</span>
                        </div>
                        
                        <div class="session-actions">
                            <button class="tmm-btn tmm-btn-small" onclick="downloadResources(<?php echo $session->session_id; ?>)">
                                📥 Supports
                            </button>
                            <button class="tmm-btn tmm-btn-small" onclick="contactTrainer(<?php echo $session->trainer_id; ?>)">
                                📞 Contact
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Calendrier -->
        <div id="calendar" class="tmm-tab-content">
            <h2>📅 Calendrier collaboratif</h2>
            <div class="calendar-controls">
                <div class="calendar-filters">
                    <label>
                        <input type="checkbox" checked> Sessions TeachMeMore
                    </label>
                    <label>
                        <input type="checkbox"> Contraintes école
                    </label>
                    <label>
                        <input type="checkbox"> Périodes d'examens
                    </label>
                </div>
                <div class="calendar-actions">
                    <button class="tmm-btn tmm-btn-secondary" onclick="importCalendar()">
                        📥 Importer contraintes
                    </button>
                    <button class="tmm-btn tmm-btn-secondary" onclick="exportCalendar()">
                        📤 Exporter planning
                    </button>
                </div>
            </div>
            <div id="tmm-calendar"></div>
        </div>

        <!-- Onglet Historique -->
        <div id="recent" class="tmm-tab-content">
            <h2>📊 Historique & Statistiques</h2>
            
            <div class="tmm-stats-summary">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h4>Taux de réalisation</h4>
                        <div class="stat-big"><?php echo $total_hours_planned > 0 ? round(($total_hours_realized / $total_hours_planned) * 100) : 0; ?>%</div>
                        <p><?php echo round($total_hours_realized ?: 0); ?>h réalisées / <?php echo round($total_hours_planned ?: 0); ?>h planifiées</p>
                    </div>
                    <div class="stat-card">
                        <h4>Sessions ce mois</h4>
                        <div class="stat-big"><?php echo count($recent_sessions); ?></div>
                        <p>Sessions terminées ou en cours</p>
                    </div>
                    <div class="stat-card">
                        <h4>Modules abordés</h4>
                        <div class="stat-big"><?php 
                            $unique_modules = array_unique(array_column($recent_sessions, 'module_id'));
                            echo count($unique_modules); 
                        ?></div>
                        <p>Différents modules TeachMeMore</p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($recent_sessions)): ?>
            <div class="recent-sessions">
                <h3>Sessions récentes</h3>
                <div class="sessions-table">
                    <table class="tmm-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Module</th>
                                <th>Formateur</th>
                                <th>Heures</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sessions as $session): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($session->start_datetime)); ?></td>
                                <td><?php echo esc_html($session->module_title); ?></td>
                                <td><?php echo esc_html($session->trainer_name); ?></td>
                                <td><?php echo $session->hours_realized ?: $session->hours_planned; ?>h</td>
                                <td>
                                    <span class="status-<?php echo get_post_status($session->session_id); ?>">
                                        <?php echo $this->get_status_label(get_post_status($session->session_id)); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="tmm-btn tmm-btn-small" onclick="viewSessionDetails(<?php echo $session->session_id; ?>)">
                                        👁️ Détails
                                    </button>
                                    <?php if (get_post_status($session->session_id) === 'completed'): ?>
                                    <button class="tmm-btn tmm-btn-small" onclick="provideFeedback(<?php echo $session->session_id; ?>)">
                                        ⭐ Évaluer
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Ressources -->
        <div id="resources" class="tmm-tab-content">
            <h2>📚 Ressources & Documents</h2>
            
            <div class="resources-grid">
                <div class="resource-section">
                    <h3>📖 Catalogues des modules</h3>
                    <p>Découvrez tous les modules TeachMeMore disponibles</p>
                    <button class="tmm-btn tmm-btn-primary" onclick="viewCatalog()">
                        Parcourir le catalogue
                    </button>
                </div>
                
                <div class="resource-section">
                    <h3>👥 Fiches formateurs</h3>
                    <p>Consultez les profils de nos formateurs experts</p>
                    <button class="tmm-btn tmm-btn-primary" onclick="viewTrainers()">
                        Voir les formateurs
                    </button>
                </div>
                
                <div class="resource-section">
                    <h3>📋 Référentiels RNCP</h3>
                    <p>Alignement avec les blocs de compétences</p>
                    <button class="tmm-btn tmm-btn-primary" onclick="viewReferentials()">
                        Consulter les référentiels
                    </button>
                </div>
                
                <div class="resource-section">
                    <h3>📊 Rapports & Bilans</h3>
                    <p>Téléchargez vos rapports d'activité</p>
                    <button class="tmm-btn tmm-btn-primary" onclick="generateReport()">
                        Générer un rapport
                    </button>
                </div>
                
                <div class="resource-section">
                    <h3>🎯 Demande personnalisée</h3>
                    <p>Besoin d'un module sur mesure?</p>
                    <button class="tmm-btn tmm-btn-primary" onclick="requestCustomModule()">
                        Faire une demande
                    </button>
                </div>
                
                <div class="resource-section">
                    <h3>💬 Support & Contact</h3>
                    <p>Notre équipe à votre service</p>
                    <button class="tmm-btn tmm-btn-primary" onclick="contactSupport()">
                        Nous contacter
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales -->
<div id="tmm-modal-alternative" class="tmm-modal">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h3>Proposer une alternative</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <form id="alternative-form">
                <input type="hidden" id="alternative-session-id" value="">
                
                <div class="form-group">
                    <label for="alternative-date">Nouvelle date souhaitée:</label>
                    <input type="date" id="alternative-date" required>
                </div>
                
                <div class="form-group">
                    <label for="alternative-start">Heure de début:</label>
                    <input type="time" id="alternative-start" required>
                </div>
                
                <div class="form-group">
                    <label for="alternative-end">Heure de fin:</label>
                    <input type="time" id="alternative-end" required>
                </div>
                
                <div class="form-group">
                    <label for="alternative-location">Lieu (si différent):</label>
                    <input type="text" id="alternative-location" placeholder="Optionnel">
                </div>
                
                <div class="form-group">
                    <label for="alternative-comment">Commentaire/Justification:</label>
                    <textarea id="alternative-comment" rows="3" placeholder="Expliquez pourquoi vous proposez cette alternative..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="tmm-btn tmm-btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="tmm-btn tmm-btn-primary">Envoyer la proposition</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="tmm-modal-reject" class="tmm-modal">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h3>Refuser la proposition</h3>
            <span class="tmm-modal-close">&times;</span>
        </div>
        <div class="tmm-modal-body">
            <form id="reject-form">
                <input type="hidden" id="reject-session-id" value="">
                
                <div class="form-group">
                    <label for="reject-reason">Motif du refus:</label>
                    <select id="reject-reason" required>
                        <option value="">Sélectionner un motif</option>
                        <option value="conflict">Conflit d'horaires</option>
                        <option value="exam_period">Période d'examens</option>
                        <option value="vacation">Vacances scolaires</option>
                        <option value="room_unavailable">Salle indisponible</option>
                        <option value="budget">Contraintes budgétaires</option>
                        <option value="other">Autre motif</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reject-comment">Commentaire détaillé:</label>
                    <textarea id="reject-comment" rows="4" placeholder="Détaillez les raisons du refus..." required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="tmm-btn tmm-btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="tmm-btn tmm-btn-danger">Confirmer le refus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Gestion des onglets
    $('.tmm-tab-button').click(function() {
        var targetTab = $(this).data('tab');
        
        $('.tmm-tab-button').removeClass('active');
        $('.tmm-tab-content').removeClass('active');
        
        $(this).addClass('active');
        $('#' + targetTab).addClass('active');
        
        // Initialiser le calendrier si nécessaire
        if (targetTab === 'calendar' && !window.calendarInitialized) {
            initCalendar();
            window.calendarInitialized = true;
        }
    });
    
    // Initialiser le calendrier
    function initCalendar() {
        $('#tmm-calendar').fullCalendar({
            locale: 'fr',
            height: 600,
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            events: {
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmm_get_school_calendar',
                    school_id: <?php echo $school_id; ?>,
                    nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
                }
            },
            eventClick: function(event) {
                // Afficher les détails de l'événement
                viewSessionDetails(event.id);
            },
            dayClick: function(date) {
                // Possibilité d'ajouter des contraintes
                console.log('Date cliquée:', date.format());
            }
        });
    }
    
    // Formulaires modaux
    $('#alternative-form').submit(function(e) {
        e.preventDefault();
        submitAlternative();
    });
    
    $('#reject-form').submit(function(e) {
        e.preventDefault();
        submitRejection();
    });
});

// Fonctions JavaScript pour les actions
function acceptSession(sessionId) {
    if (confirm('Confirmer l\'acceptation de cette session?')) {
        jQuery.post(ajaxurl, {
            action: 'tmm_respond_to_proposal',
            session_id: sessionId,
            response: 'accept',
            comment: '',
            nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Session acceptée! TeachMeMore a été notifié.');
                location.reload();
            } else {
                alert('Erreur: ' + response.data);
            }
        });
    }
}

function proposeAlternative(sessionId) {
    document.getElementById('alternative-session-id').value = sessionId;
    document.getElementById('tmm-modal-alternative').style.display = 'block';
}

function rejectSession(sessionId) {
    document.getElementById('reject-session-id').value = sessionId;
    document.getElementById('tmm-modal-reject').style.display = 'block';
}

function submitAlternative() {
    var sessionId = document.getElementById('alternative-session-id').value;
    var newDate = document.getElementById('alternative-date').value;
    var newStart = document.getElementById('alternative-start').value;
    var newEnd = document.getElementById('alternative-end').value;
    var newLocation = document.getElementById('alternative-location').value;
    var comment = document.getElementById('alternative-comment').value;
    
    var counterStart = newDate + ' ' + newStart + ':00';
    var counterEnd = newDate + ' ' + newEnd + ':00';
    
    jQuery.post(ajaxurl, {
        action: 'tmm_respond_to_proposal',
        session_id: sessionId,
        response: 'counter',
        counter_start: counterStart,
        counter_end: counterEnd,
        counter_location: newLocation,
        comment: comment,
        nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('Alternative proposée! TeachMeMore examinera votre demande.');
            closeModal();
            location.reload();
        } else {
            alert('Erreur: ' + response.data);
        }
    });
}

function submitRejection() {
    var sessionId = document.getElementById('reject-session-id').value;
    var reason = document.getElementById('reject-reason').value;
    var comment = document.getElementById('reject-comment').value;
    
    var fullComment = 'Motif: ' + jQuery('#reject-reason option:selected').text() + '\n\n' + comment;
    
    jQuery.post(ajaxurl, {
        action: 'tmm_respond_to_proposal',
        session_id: sessionId,
        response: 'reject',
        comment: fullComment,
        nonce: '<?php echo wp_create_nonce('tmm_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('Refus enregistré. TeachMeMore a été notifié.');
            closeModal();
            location.reload();
        } else {
            alert('Erreur: ' + response.data);
        }
    });
}

function closeModal() {
    document.querySelectorAll('.tmm-modal').forEach(modal => {
        modal.style.display = 'none';
    });
}

function viewModuleDetails(moduleId) {
    // Ouvrir une modal avec les détails du module
    window.open('<?php echo home_url(); ?>/?tmm_module=' + moduleId, '_blank');
}

function viewSessionDetails(sessionId) {
    // Afficher les détails complets de la session
    console.log('Voir détails session:', sessionId);
}

function downloadResources(sessionId) {
    // Télécharger les supports de cours
    window.location.href = ajaxurl + '?action=tmm_download_resources&session_id=' + sessionId + '&nonce=<?php echo wp_create_nonce('tmm_nonce'); ?>';
}

function contactTrainer(trainerId) {
    // Contacter le formateur
    window.open('mailto:contact@teachmemore.fr?subject=Contact formateur&body=Référence formateur: ' + trainerId);
}

// Gestionnaire pour fermer les modales en cliquant sur X ou en dehors
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('tmm-modal-close') || e.target.classList.contains('tmm-modal')) {
        closeModal();
    }
});
</script>

<style>
/* Styles spécifiques au dashboard école */
.tmm-school-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.tmm-dashboard-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 30px;
    margin-bottom: 30px;
    padding: 25px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
}

.school-info h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 600;
}

.welcome-message {
    font-size: 16px;
    opacity: 0.9;
    margin: 0;
}

.last-connection {
    font-size: 14px;
    opacity: 0.8;
    margin-top: 5px;
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.stat-item {
    text-align: center;
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
}

.tmm-alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
}

.tmm-alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.tmm-dashboard-tabs {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.tmm-tabs-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.tmm-tab-button {
    flex: 1;
    padding: 15px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 500;
    color: #495057;
    transition: all 0.3s ease;
}

.tmm-tab-button.active {
    background: white;
    color: #667eea;
    border-bottom: 3px solid #667eea;
}

.tmm-tab-content {
    display: none;
    padding: 30px;
}

.tmm-tab-content.active {
    display: block;
}

.tmm-sessions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.tmm-session-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.tmm-session-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.session-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.session-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 18px;
}

.session-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-proposed {
    background: #fff3cd;
    color: #856404;
}

.status-confirmed {
    background: #d4edda;
    color: #155724;
}

.session-details {
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    margin-bottom: 8px;
}

.detail-label {
    min-width: 120px;
    font-weight: 500;
    color: #6c757d;
}

.detail-value {
    color: #495057;
}

.session-notes {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 14px;
}

.session-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.tmm-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.tmm-btn-success {
    background: #28a745;
    color: white;
}

.tmm-btn-warning {
    background: #ffc107;
    color: #212529;
}

.tmm-btn-danger {
    background: #dc3545;
    color: white;
}

.tmm-btn-secondary {
    background: #6c757d;
    color: white;
}

.tmm-btn-primary {
    background: #667eea;
    color: white;
}

.tmm-btn-small {
    padding: 6px 12px;
    font-size: 12px;
}

.tmm-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.tmm-modal-content {
    background: white;
    margin: 10% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
}

.tmm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #dee2e6;
}

.tmm-modal-header h3 {
    margin: 0;
    color: #2c3e50;
}

.tmm-modal-close {
    cursor: pointer;
    font-size: 24px;
    color: #adb5bd;
}

.tmm-modal-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #495057;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
}

.tmm-empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.tmm-sessions-list {
    space-y: 15px;
}

.tmm-session-row {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
}

.session-date {
    text-align: center;
    min-width: 60px;
}

.session-date .date {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #667eea;
}

.session-date .month {
    display: block;
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
}

.session-info {
    flex: 1;
}

.session-info h4 {
    margin: 0 0 8px 0;
    color: #2c3e50;
}

.session-meta {
    display: flex;
    gap: 20px;
    font-size: 14px;
    color: #6c757d;
}

.resources-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.resource-section {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
}

.resource-section h3 {
    margin: 0 0 10px 0;
    color: #2c3e50;
}

.resource-section p {
    margin: 0 0 20px 0;
    color: #6c757d;
}

@media (max-width: 768px) {
    .tmm-dashboard-header {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .tmm-tabs-nav {
        flex-direction: column;
    }
    
    .tmm-sessions-grid {
        grid-template-columns: 1fr;
    }
    
    .session-actions {
        justify-content: center;
    }
}
</style>