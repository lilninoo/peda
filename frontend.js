/**
 * TeachMeMore PédagoConnect - Frontend JavaScript
 * Gestion des interactions utilisateur côté frontend
 */

(function($) {
    'use strict';
    
    // Variables globales
    let tmmCalendar = null;
    let tmmNotifications = [];
    let tmmRealTimeUpdates = null;
    
    // Initialisation au chargement du DOM
    $(document).ready(function() {
        initTMMPedagoConnect();
    });
    
    /**
     * Initialisation principale du plugin
     */
    function initTMMPedagoConnect() {
        console.log('🚀 Initialisation TMM PédagoConnect');
        
        // Initialiser les composants
        initTabSystem();
        initModals();
        initFormValidation();
        initRealTimeNotifications();
        initAvailabilityPicker();
        initSessionManagement();
        initFileUploader();
        initSmartSuggestions();
        
        // Charger les données initiales
        loadInitialData();
        
        // Démarrer les mises à jour temps réel
        startRealTimeUpdates();
    }
    
    /**
     * Système d'onglets avancé
     */
    function initTabSystem() {
        $('.tmm-tab-button').on('click', function(e) {
            e.preventDefault();
            
            const targetTab = $(this).data('tab');
            const $tabContent = $('#' + targetTab);
            
            if ($tabContent.length === 0) return;
            
            // Animation de transition
            $('.tmm-tab-content.active').fadeOut(200, function() {
                $(this).removeClass('active');
                
                $('.tmm-tab-button').removeClass('active');
                $(e.target).addClass('active');
                
                $tabContent.fadeIn(200).addClass('active');
                
                // Callbacks spécifiques par onglet
                handleTabActivation(targetTab);
            });
        });
    }
    
    /**
     * Gestion des activations d'onglets
     */
    function handleTabActivation(tabName) {
        switch(tabName) {
            case 'calendar':
                initCalendarIfNeeded();
                break;
            case 'availabilities':
                loadTrainerAvailabilities();
                break;
            case 'reporting':
                loadReportingData();
                break;
            case 'recent':
                loadRecentSessions();
                break;
        }
    }
    
    /**
     * Initialisation du calendrier intelligent
     */
    function initCalendarIfNeeded() {
        if (window.calendarInitialized) return;
        
        $('#tmm-calendar').fullCalendar({
            locale: 'fr',
            height: 600,
            timezone: 'Europe/Paris',
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay,listWeek'
            },
            views: {
                listWeek: { buttonText: 'Agenda' }
            },
            eventSources: [
                {
                    url: tmm_ajax.ajax_url,
                    type: 'POST',
                    data: function() {
                        return {
                            action: 'tmm_get_planning_data',
                            view_type: getCurrentViewType(),
                            filter_id: getCurrentFilterId(),
                            nonce: tmm_ajax.nonce
                        };
                    },
                    color: '#667eea',
                    textColor: 'white'
                }
            ],
            loading: function(bool) {
                if (bool) {
                    $('#tmm-calendar').append('<div class="tmm-loading">Chargement...</div>');
                } else {
                    $('.tmm-loading').remove();
                }
            },
            eventClick: function(calEvent, jsEvent, view) {
                showSessionDetails(calEvent.id, calEvent);
            },
            dayClick: function(date, jsEvent, view) {
                if (hasCreatePermission()) {
                    showQuickCreateModal(date);
                }
            },
            eventDrop: function(event, delta, revertFunc) {
                updateSessionTime(event.id, event.start, event.end, revertFunc);
            },
            eventResize: function(event, delta, revertFunc) {
                updateSessionTime(event.id, event.start, event.end, revertFunc);
            },
            eventRender: function(event, element) {
                // Personnaliser l'affichage des événements
                element.find('.fc-title').prepend(getEventIcon(event.type) + ' ');
                
                // Ajouter des actions rapides
                if (event.editable) {
                    element.append('<div class="fc-quick-actions">' +
                        '<button class="btn-edit" data-id="' + event.id + '">✏️</button>' +
                        '<button class="btn-delete" data-id="' + event.id + '">🗑️</button>' +
                        '</div>');
                }
                
                // Tooltip avec informations détaillées
                element.tooltip({
                    title: buildEventTooltip(event),
                    placement: 'top',
                    html: true
                });
            }
        });
        
        window.calendarInitialized = true;
        console.log('📅 Calendrier initialisé');
    }
    
    /**
     * Système de modales avancé
     */
    function initModals() {
        // Gestion générique des modales
        $(document).on('click', '[data-modal]', function(e) {
            e.preventDefault();
            const modalId = $(this).data('modal');
            showModal(modalId, $(this).data());
        });
        
        // Fermeture des modales
        $(document).on('click', '.tmm-modal-close, .tmm-modal-overlay', function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });
        
        // Échap pour fermer
        $(document).keyup(function(e) {
            if (e.keyCode === 27) {
                closeAllModals();
            }
        });
    }
    
    /**
     * Validation de formulaires avancée
     */
    function initFormValidation() {
        // Validation en temps réel
        $('form[data-tmm-validate]').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = new FormData(this);
            
            // Ajouter le nonce
            formData.append('nonce', tmm_ajax.nonce);
            
            // Validation côté client
            if (!validateForm($form)) {
                showNotification('Veuillez corriger les erreurs du formulaire', 'error');
                return false;
            }
            
            // Soumission AJAX
            submitFormAjax($form, formData);
        });
        
        // Validation de champs en temps réel
        $('input[data-validate], select[data-validate], textarea[data-validate]').on('blur change', function() {
            validateField($(this));
        });
    }
    
    /**
     * Notifications temps réel
     */
    function initRealTimeNotifications() {
        // Créer le conteneur de notifications
        if ($('#tmm-notifications-container').length === 0) {
            $('body').append('<div id="tmm-notifications-container"></div>');
        }
        
        // Charger les notifications existantes
        loadNotifications();
        
        // Badge de notification dans le header
        updateNotificationBadge();
    }
    
    /**
     * Sélecteur de disponibilités intelligent
     */
    function initAvailabilityPicker() {
        if ($('#availability-picker').length === 0) return;
        
        // Configuration du sélecteur de créneaux
        $('#availability-picker').availabilityPicker({
            recurring: true,
            multiSelect: true,
            timeSlots: {
                start: '08:00',
                end: '18:00',
                interval: 30
            },
            weekDays: [1, 2, 3, 4, 5], // Lun-Ven par défaut
            onSelectionChange: function(selections) {
                handleAvailabilityChange(selections);
            },
            onRecurringToggle: function(isRecurring) {
                toggleRecurringOptions(isRecurring);
            }
        });
        
        console.log('⏰ Sélecteur de disponibilités initialisé');
    }
    
    /**
     * Gestion des sessions
     */
    function initSessionManagement() {
        // Actions rapides sur les sessions
        $(document).on('click', '.session-action', function(e) {
            e.preventDefault();
            
            const action = $(this).data('action');
            const sessionId = $(this).data('session-id');
            
            switch(action) {
                case 'accept':
                    acceptSession(sessionId);
                    break;
                case 'reject':
                    rejectSession(sessionId);
                    break;
                case 'counter':
                    proposeAlternative(sessionId);
                    break;
                case 'complete':
                    completeSession(sessionId);
                    break;
                case 'cancel':
                    cancelSession(sessionId);
                    break;
                default:
                    console.warn('Action non reconnue:', action);
            }
        });
        
        // Drag & drop pour réorganiser les sessions
        if ($('.tmm-sessions-sortable').length > 0) {
            $('.tmm-sessions-sortable').sortable({
                placeholder: 'session-placeholder',
                update: function(event, ui) {
                    updateSessionOrder($(this).sortable('toArray'));
                }
            });
        }
    }
    
    /**
     * Upload de fichiers avancé
     */
    function initFileUploader() {
        if ($('.tmm-file-upload').length === 0) return;
        
        $('.tmm-file-upload').each(function() {
            const $dropzone = $(this);
            
            // Drag & Drop
            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            
            $dropzone.on('dragleave dragend', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });
            
            $dropzone.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                handleFileUpload(files, $dropzone);
            });
            
            // Click pour sélectionner
            $dropzone.on('click', function() {
                const $input = $('<input type="file" multiple>');
                $input.on('change', function() {
                    handleFileUpload(this.files, $dropzone);
                });
                $input.click();
            });
        });
    }
    
    /**
     * Suggestions intelligentes
     */
    function initSmartSuggestions() {
        // Auto-complétion pour les formateurs
        $('input[data-autocomplete="trainers"]').autocomplete({
            source: function(request, response) {
                $.post(tmm_ajax.ajax_url, {
                    action: 'tmm_search_trainers',
                    term: request.term,
                    nonce: tmm_ajax.nonce
                }, function(data) {
                    response(data.success ? data.data : []);
                });
            },
            minLength: 2,
            select: function(event, ui) {
                handleTrainerSelection(ui.item);
            }
        });
        
        // Suggestions de créneaux optimaux
        $('#suggest-optimal-slots').on('click', function() {
            const moduleId = $('#module-select').val();
            const schoolId = $('#school-select').val();
            
            if (!moduleId || !schoolId) {
                showNotification('Veuillez sélectionner un module et une école', 'warning');
                return;
            }
            
            suggestOptimalTimeSlots(moduleId, schoolId);
        });
    }
    
    /**
     * Charger les données initiales
     */
    function loadInitialData() {
        // Charger les statistiques du dashboard
        if ($('.tmm-dashboard-stats').length > 0) {
            loadDashboardStats();
        }
        
        // Charger les notifications non lues
        loadUnreadNotifications();
        
        // Charger les sessions en attente
        loadPendingSessions();
    }
    
    /**
     * Mises à jour temps réel
     */
    function startRealTimeUpdates() {
        // Polling pour les mises à jour (toutes les 30 secondes)
        tmmRealTimeUpdates = setInterval(function() {
            checkForUpdates();
        }, 30000);
        
        // Heartbeat pour maintenir la session active
        setInterval(function() {
            $.post(tmm_ajax.ajax_url, {
                action: 'tmm_heartbeat',
                nonce: tmm_ajax.nonce
            });
        }, 300000); // 5 minutes
        
        console.log('🔄 Mises à jour temps réel activées');
    }
    
    /**
     * Accepter une session
     */
    function acceptSession(sessionId) {
        showConfirmDialog({
            title: 'Confirmer l\'acceptation',
            message: 'Êtes-vous sûr de vouloir accepter cette session?',
            confirmText: 'Accepter',
            confirmClass: 'btn-success',
            onConfirm: function() {
                $.post(tmm_ajax.ajax_url, {
                    action: 'tmm_respond_to_proposal',
                    session_id: sessionId,
                    response: 'accept',
                    comment: '',
                    nonce: tmm_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        showNotification('Session acceptée avec succès!', 'success');
                        refreshSessionsList();
                        updateCalendar();
                    } else {
                        showNotification('Erreur: ' + response.data, 'error');
                    }
                });
            }
        });
    }
    
    /**
     * Proposer une alternative
     */
    function proposeAlternative(sessionId) {
        const modalHtml = `
            <div class="tmm-modal-content">
                <div class="tmm-modal-header">
                    <h3>Proposer une alternative</h3>
                    <span class="tmm-modal-close">&times;</span>
                </div>
                <div class="tmm-modal-body">
                    <form id="alternative-form">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="alt-date">Nouvelle date</label>
                                <input type="date" id="alt-date" class="form-control" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="alt-start">Début</label>
                                <input type="time" id="alt-start" class="form-control" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="alt-end">Fin</label>
                                <input type="time" id="alt-end" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="alt-location">Lieu (optionnel)</label>
                            <input type="text" id="alt-location" class="form-control" placeholder="Salle, campus, visio...">
                        </div>
                        <div class="form-group">
                            <label for="alt-comment">Justification</label>
                            <textarea id="alt-comment" class="form-control" rows="3" 
                                placeholder="Expliquez pourquoi vous proposez cette alternative..." required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                            <button type="submit" class="btn btn-primary">Envoyer la proposition</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        showModal('custom', {}, modalHtml);
        
        $('#alternative-form').on('submit', function(e) {
            e.preventDefault();
            
            const counterData = {
                date: $('#alt-date').val(),
                start: $('#alt-start').val(),
                end: $('#alt-end').val(),
                location: $('#alt-location').val(),
                comment: $('#alt-comment').val()
            };
            
            submitAlternativeProposal(sessionId, counterData);
        });
    }
    
    /**
     * Suggestions de créneaux optimaux
     */
    function suggestOptimalTimeSlots(moduleId, schoolId) {
        showLoadingIndicator('Recherche des créneaux optimaux...');
        
        $.post(tmm_ajax.ajax_url, {
            action: 'tmm_get_suggested_slots',
            module_id: moduleId,
            school_id: schoolId,
            nonce: tmm_ajax.nonce
        }, function(response) {
            hideLoadingIndicator();
            
            if (response.success && response.data.length > 0) {
                displayOptimalSlots(response.data);
            } else {
                showNotification('Aucun créneau optimal trouvé', 'info');
            }
        });
    }
    
    /**
     * Afficher les créneaux optimaux
     */
    function displayOptimalSlots(slots) {
        const slotsHtml = slots.map(slot => `
            <div class="optimal-slot" data-slot='${JSON.stringify(slot)}'>
                <div class="slot-header">
                    <span class="slot-score">Score: ${slot.compatibility_score}%</span>
                    <span class="slot-trainer">${slot.trainer_name}</span>
                </div>
                <div class="slot-details">
                    <span class="slot-date">${formatDate(slot.start_datetime)}</span>
                    <span class="slot-time">${formatTime(slot.start_datetime)} - ${formatTime(slot.end_datetime)}</span>
                </div>
                <div class="slot-reason">${slot.reason}</div>
                <button class="btn btn-sm btn-primary select-slot">Sélectionner</button>
            </div>
        `).join('');
        
        const modalHtml = `
            <div class="tmm-modal-content">
                <div class="tmm-modal-header">
                    <h3>Créneaux optimaux suggérés</h3>
                    <span class="tmm-modal-close">&times;</span>
                </div>
                <div class="tmm-modal-body">
                    <div class="optimal-slots-list">
                        ${slotsHtml}
                    </div>
                </div>
            </div>
        `;
        
        showModal('optimal-slots', {}, modalHtml);
        
        // Gestion de la sélection
        $('.select-slot').on('click', function() {
            const slot = JSON.parse($(this).closest('.optimal-slot').data('slot'));
            selectOptimalSlot(slot);
        });
    }
    
    /**
     * Gestion de l'upload de fichiers
     */
    function handleFileUpload(files, $dropzone) {
        const sessionId = $dropzone.data('session-id');
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        Array.from(files).forEach(file => {
            if (file.size > maxSize) {
                showNotification(`Fichier ${file.name} trop volumineux (max 10MB)`, 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('session_id', sessionId);
            formData.append('action', 'tmm_upload_session_file');
            formData.append('nonce', tmm_ajax.nonce);
            
            // Créer un indicateur de progression
            const $progress = createProgressIndicator(file.name);
            $dropzone.append($progress);
            
            $.ajax({
                url: tmm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            $progress.find('.progress-bar').css('width', percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    $progress.remove();
                    if (response.success) {
                        showNotification(`Fichier ${file.name} uploadé avec succès`, 'success');
                        refreshFilesList(sessionId);
                    } else {
                        showNotification(`Erreur upload ${file.name}: ` + response.data, 'error');
                    }
                },
                error: function() {
                    $progress.remove();
                    showNotification(`Erreur lors de l'upload de ${file.name}`, 'error');
                }
            });
        });
    }
    
    /**
     * Afficher une notification
     */
    function showNotification(message, type = 'info', duration = 5000) {
        const id = 'notif-' + Date.now();
        const icons = {
            success: '✅',
            error: '❌', 
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        const $notification = $(`
            <div id="${id}" class="tmm-notification tmm-notification-${type}">
                <div class="notification-content">
                    <span class="notification-icon">${icons[type] || icons.info}</span>
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">&times;</button>
                </div>
            </div>
        `);
        
        $('#tmm-notifications-container').append($notification);
        
        // Animation d'entrée
        $notification.slideDown(300);
        
        // Auto-suppression
        if (duration > 0) {
            setTimeout(() => {
                removeNotification(id);
            }, duration);
        }
        
        // Suppression manuelle
        $notification.find('.notification-close').on('click', () => {
            removeNotification(id);
        });
        
        tmmNotifications.push({id, type, message, timestamp: Date.now()});
    }
    
    /**
     * Supprimer une notification
     */
    function removeNotification(id) {
        const $notification = $('#' + id);
        $notification.slideUp(300, function() {
            $(this).remove();
        });
        
        tmmNotifications = tmmNotifications.filter(n => n.id !== id);
    }
    
    /**
     * Dialogue de confirmation
     */
    function showConfirmDialog(options) {
        const defaults = {
            title: 'Confirmation',
            message: 'Êtes-vous sûr?',
            confirmText: 'Confirmer',
            cancelText: 'Annuler',
            confirmClass: 'btn-primary',
            onConfirm: function() {},
            onCancel: function() {}
        };
        
        const settings = $.extend(defaults, options);
        
        const modalHtml = `
            <div class="tmm-modal-content">
                <div class="tmm-modal-header">
                    <h3>${settings.title}</h3>
                </div>
                <div class="tmm-modal-body">
                    <p>${settings.message}</p>
                    <div class="modal-actions">
                        <button class="btn btn-secondary cancel-btn">${settings.cancelText}</button>
                        <button class="btn ${settings.confirmClass} confirm-btn">${settings.confirmText}</button>
                    </div>
                </div>
            </div>
        `;
        
        showModal('confirm', {}, modalHtml);
        
        $('.confirm-btn').on('click', function() {
            closeModal();
            settings.onConfirm();
        });
        
        $('.cancel-btn').on('click', function() {
            closeModal();
            settings.onCancel();
        });
    }
    
    /**
     * Utilitaires
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR');
    }
    
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
    }
    
    function createProgressIndicator(filename) {
        return $(`
            <div class="upload-progress">
                <div class="filename">${filename}</div>
                <div class="progress">
                    <div class="progress-bar" style="width: 0%"></div>
                </div>
            </div>
        `);
    }
    
    function getCurrentViewType() {
        return $('input[name="view_type"]:checked').val() || 'global';
    }
    
    function getCurrentFilterId() {
        return $('#filter-select').val() || 0;
    }
    
    function getEventIcon(type) {
        const icons = {
            'proposed': '📋',
            'confirmed': '✅',
            'in_progress': '🔄',
            'completed': '✔️',
            'cancelled': '❌'
        };
        return icons[type] || '📅';
    }
    
    function buildEventTooltip(event) {
        return `
            <div class="event-tooltip">
                <strong>${event.title}</strong><br>
                <small>${event.trainer || 'Formateur à définir'}</small><br>
                <small>${formatTime(event.start)} - ${formatTime(event.end)}</small>
            </div>
        `;
    }
    
    // Exposer les fonctions globales nécessaires
    window.tmmAcceptSession = acceptSession;
    window.tmmRejectSession = rejectSession;
    window.tmmProposeAlternative = proposeAlternative;
    window.tmmShowNotification = showNotification;
    window.tmmRefreshCalendar = function() {
        if (tmmCalendar) {
            tmmCalendar.refetchEvents();
        }
    };
    
    // Nettoyage à la fermeture de la page
    $(window).on('beforeunload', function() {
        if (tmmRealTimeUpdates) {
            clearInterval(tmmRealTimeUpdates);
        }
    });
    
})(jQuery);