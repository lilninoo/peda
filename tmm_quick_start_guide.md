# 🚀 Guide de Démarrage Rapide - TeachMeMore PédagoConnect

## 🎯 Mise en Route en 10 Minutes

Bienvenue dans **TeachMeMore PédagoConnect** ! Ce guide vous permet de configurer et utiliser le plugin en quelques minutes seulement.

---

## ⚡ Installation Express

### 1. Activation du Plugin (2 min)

```bash
# 1. Télécharger les fichiers du plugin
# 2. Les placer dans wp-content/plugins/teachmemore-pedagoconnect/
# 3. Activer le plugin depuis l'admin WordPress
```

✅ **Le plugin se configure automatiquement** : tables, rôles, pages et tâches CRON sont créés automatiquement.

### 2. Assistant d'Installation (3 min)

Après activation, vous êtes redirigé vers l'**Assistant d'Installation** :

- ✅ **Données de démonstration** - Écoles et modules d'exemple
- ✅ **Configuration email** - Notifications automatiques  
- ✅ **Utilisateurs de test** - Comptes formateur et école
- ✅ **Analytics** - Tableaux de bord business

**→ Cliquez sur "🚀 Démarrer l'Installation Complète"**

---

## 👥 Premiers Utilisateurs (2 min)

### Créer un Responsable Pédagogique TeachMeMore

```php
// Via Functions.php ou plugin
$manager_data = array(
    'user_login' => 'responsable.pedagogique',
    'user_email' => 'pedagogie@teachmemore.fr',
    'user_pass' => 'motdepasse_securise',
    'display_name' => 'Responsable Pédagogique',
    'role' => 'tmm_pedagog_manager'
);
wp_insert_user($manager_data);
```

### Créer une École Partenaire

```php
// Créer l'école
$school_id = wp_insert_post(array(
    'post_title' => 'École Supérieure de Commerce',
    'post_type' => 'tmm_school',
    'post_status' => 'publish'
));

// Métadonnées école
update_post_meta($school_id, 'contact_emails', 'contact@esc.fr');
update_post_meta($school_id, 'working_hours_start', '08:00');
update_post_meta($school_id, 'working_hours_end', '18:00');

// Créer le compte utilisateur école
$user_id = wp_insert_user(array(
    'user_login' => 'esc_pedagogie',
    'user_email' => 'contact@esc.fr',
    'user_pass' => 'motdepasse_ecole',
    'display_name' => 'Responsable Pédagogique ESC',
    'role' => 'partner_school'
));
update_user_meta($user_id, 'school_id', $school_id);
```

### Créer un Formateur

```php
$trainer_id = wp_insert_user(array(
    'user_login' => 'formateur.devops',
    'user_email' => 'pierre.martin@teachmemore.fr',
    'user_pass' => 'motdepasse_formateur',
    'display_name' => 'Pierre Martin',
    'role' => 'tmm_trainer'
));
update_user_meta($trainer_id, 'trainer_skills', 'DevOps, Docker, Kubernetes');
```

---

## 📚 Premier Module (1 min)

```php
$module_id = wp_insert_post(array(
    'post_title' => 'Bootcamp DevOps - Expert',
    'post_content' => 'Formation intensive Docker, Kubernetes, CI/CD',
    'post_type' => 'tmm_module',
    'post_status' => 'publish'
));

// Métadonnées module
update_post_meta($module_id, 'duration_hours', 35);
update_post_meta($module_id, 'required_skills', 'DevOps, Docker');
update_post_meta($module_id, 'hourly_rate', 200);
update_post_meta($module_id, 'rncp_block', 'RNCP34126-BC02');
```

---

## 🗓️ Première Session (2 min)

### Méthode 1: Interface Admin

1. **Dashboard TeachMeMore** → `PédagoConnect > Dashboard`
2. **Bouton "➕ Nouvelle Session"**
3. **Remplir** : École, Module, Date, Formateur
4. **Cliquer "Créer & Envoyer"**

### Méthode 2: Programmation

```php
// Créer la session
$session_id = wp_insert_post(array(
    'post_title' => 'Session DevOps - ESC Mars 2025',
    'post_type' => 'tmm_session',
    'post_status' => 'proposed' // Statut initial
));

// Métadonnées session
global $wpdb;
$sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
$wpdb->insert($sessions_table, array(
    'session_id' => $session_id,
    'school_id' => $school_id,
    'module_id' => $module_id,
    'trainer_id' => $trainer_id,
    'start_datetime' => '2025-03-15 09:00:00',
    'end_datetime' => '2025-03-19 17:00:00',
    'hours_planned' => 35,
    'location' => 'Campus Principal',
    'group_name' => 'Promo 2025'
));

// Déclencher les notifications
do_action('tmm_session_created', $session_id, $school_id);
```

---

## 🎯 Workflow Standard

### 1. **TeachMeMore** propose une session
```php
// Statut: 'proposed'
// → Notification automatique à l'école
// → Email envoyé au responsable pédagogique
```

### 2. **École** répond à la proposition
```php
// 3 options possibles:
// ✅ Accepter → Statut: 'confirmed'
// 📝 Proposer alternative → Nouveau créneau
// ❌ Refuser → Statut: 'cancelled'
```

### 3. **Session confirmée**
```php
// → Notification au formateur
// → Ajout au planning global
// → Génération des supports
```

### 4. **Réalisation & Suivi**
```php
// Statut: 'in_progress' → 'completed'
// → Saisie des heures réalisées
// → Upload feuilles d'émargement
// → Feedback école & formateur
```

---

## 📱 Interfaces Principales

### Dashboard TeachMeMore
```
URL: /wp-admin/admin.php?page=tmm-pedagoconnect
Accès: Administrateur, Responsable Pédagogique TMM
```

**Fonctionnalités:**
- 📊 Métriques temps réel
- 🚨 Sessions urgentes
- 📅 Planning global
- 📈 Analytics business
- ⚙️ Gestion écoles/formateurs

### Espace École Partenaire
```
URL: /espace-partenaire/
Accès: Rôle 'partner_school'
Shortcode: [tmm_school_dashboard]
```

**Fonctionnalités:**
- 📋 Propositions en attente
- ✅ Sessions confirmées
- 📅 Calendrier collaboratif
- 📊 Historique & stats
- 📚 Catalogue modules

### Interface Formateur
```
URL: /formateur-dashboard/
Accès: Rôle 'tmm_trainer'
Shortcode: [tmm_availability_form]
```

**Fonctionnalités:**
- 📅 Gestion disponibilités
- 🔄 Synchronisation calendriers
- 👨‍🏫 Missions assignées
- 📤 Upload supports
- ⭐ Feedback sessions

---

## 🔧 Configuration Rapide

### Tarifs Horaires
```php
update_option('tmm_hourly_rates', array(
    'standard' => 150,
    'expert' => 200,
    'premium' => 250
));
```

### Notifications Email
```php
update_option('tmm_email_settings', array(
    'sender_name' => 'TeachMeMore',
    'sender_email' => 'noreply@teachmemore.fr',
    'reply_to' => 'contact@teachmemore.fr'
));
```

### Paramètres Planning
```php
update_option('tmm_planning_settings', array(
    'default_session_duration' => 7, // heures
    'working_hours_start' => '08:00',
    'working_hours_end' => '18:00',
    'working_days' => array(1,2,3,4,5), // Lun-Ven
    'advance_booking_days' => 7
));
```

---

## 📊 Utilisation des Shortcodes

### Dashboard École
```php
// Page complète
[tmm_school_dashboard]

// Composants individuels
[tmm_planning_calendar]
[tmm_school_notifications]
[tmm_session_list status="pending"]
```

### Disponibilités Formateur
```php
// Formulaire complet
[tmm_availability_form]

// Calendrier seul
[tmm_availability_calendar trainer_id="123"]
```

### Widgets Planning
```php
// Planning public (pour site vitrine)
[tmm_public_planning school_id="456"]

// Statistiques publiques
[tmm_public_stats]
```

---

## 🚨 Actions d'Urgence

### Session en Retard de Réponse
```php
// Identifier les sessions > 48h sans réponse
$urgent_sessions = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}tmm_sessions_meta s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     WHERE p.post_status = 'proposed' 
     AND s.created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)"
);

// Envoyer rappel automatique
foreach ($urgent_sessions as $session) {
    do_action('tmm_send_reminder', $session->session_id);
}
```

### Formateur Non Assigné
```php
// Sessions confirmées sans formateur
$unassigned = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}tmm_sessions_meta s
     JOIN {$wpdb->posts} p ON s.session_id = p.ID
     WHERE p.post_status = 'confirmed' 
     AND (s.trainer_id IS NULL OR s.trainer_id = 0)
     AND s.start_datetime > NOW()"
);
```

### Suggestions Automatiques
```php
// Obtenir suggestions de formateurs
$suggestions = apply_filters('tmm_suggest_trainers', array(), $module_id, $start_date);

// Obtenir créneaux optimaux
$optimal_slots = apply_filters('tmm_suggest_time_slots', array(), $school_id, $module_id, $duration);
```

---

## 📈 Rapports Express

### Rapport Mensuel
```php
// Via interface
Admin → PédagoConnect → Reporting → "Générer Rapport"

// Via code
$report = new TMM_Reporting();
$data = $report->get_global_activity_report(
    date('Y-m-01'), // Début du mois
    date('Y-m-d'),  // Aujourd'hui
    array() // Filtres
);
```

### Export Données
```php
// CSV des sessions
$csv_url = admin_url('admin-ajax.php?action=tmm_export_report&format=csv&type=sessions&nonce=' . wp_create_nonce('tmm_nonce'));

// Excel complet
$excel_url = admin_url('admin-ajax.php?action=tmm_export_report&format=excel&type=complete&nonce=' . wp_create_nonce('tmm_nonce'));
```

---

## 🔔 Notifications Temps Réel

### Configuration Côté Client
```javascript
// Activer notifications push
window.TMM.notifications.toggleSound(); // On/Off son
window.TMM.notifications.setPosition('top-right'); // Position

// Créer notification custom
window.TMM.notifications.createNotification(
    'Test Notification',
    'Ceci est un test',
    'info',
    { icon: '🧪', priority: 'medium' }
);

// Statistiques
console.log(window.TMM.notifications.getStats());
```

### Configuration Serveur
```php
// Créer notification programmatique
$notifications = new TMM_Notifications();
$notifications->create_notification(
    $user_id,
    'session_confirmed',
    'Session confirmée!',
    'Votre session DevOps a été confirmée par l\'école.',
    $session_id,
    array('action_url' => admin_url('post.php?post=' . $session_id))
);

// Broadcast (plusieurs utilisateurs)
$user_ids = array(1, 2, 3);
$notifications->broadcast_notification(
    $user_ids,
    'system_alert',
    'Maintenance programmée',
    'Maintenance système prévue demain de 2h à 4h.'
);
```

---

## 🔧 Personnalisation Avancée

### Hooks Disponibles
```php
// Avant création session
add_action('tmm_before_session_create', function($session_data) {
    // Validation custom, intégrations tierces
});

// Après confirmation école
add_action('tmm_session_confirmed', function($session_id, $school_id) {
    // Webhook, mise à jour CRM, etc.
});

// Suggestion formateurs
add_filter('tmm_suggest_trainers', function($suggestions, $module_id) {
    // Algorithme custom de suggestion
    return $suggestions;
}, 10, 2);

// Calcul tarifs
add_filter('tmm_calculate_session_price', function($price, $session_data) {
    // Logique de pricing personnalisée
    return $price;
}, 10, 2);
```

### Intégrations Tierces
```php
// Moodle
add_action('tmm_session_completed', function($session_id) {
    // Synchroniser avec Moodle
    $moodle_course_id = sync_with_moodle($session_id);
});

// Google Calendar
add_action('tmm_session_confirmed', function($session_id) {
    // Créer événement Google Calendar
    create_google_calendar_event($session_id);
});

// Webhook générique
add_action('tmm_session_updated', function($session_id, $old_status, $new_status) {
    $webhook_url = get_option('tmm_webhook_url');
    if ($webhook_url) {
        wp_remote_post($webhook_url, array(
            'body' => json_encode(array(
                'event' => 'session_updated',
                'session_id' => $session_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'timestamp' => time()
            )),
            'headers' => array('Content-Type' => 'application/json')
        ));
    }
});
```

---

## 🐛 Dépannage Express

### Problèmes Courants

**❌ Notifications ne s'affichent pas**
```php
// Vérifier configuration
$settings = get_option('tmm_notification_settings');
var_dump($settings);

// Forcer rechargement
delete_transient('tmm_notifications_cache');
wp_cache_flush();
```

**❌ Formateur ne reçoit pas les emails**
```php
// Test email
wp_mail('test@test.com', 'Test TMM', 'Test notification');

// Vérifier rôle
$user = get_user_by('email', 'formateur@email.com');
var_dump($user->roles); // Doit contenir 'tmm_trainer'
```

**❌ Sessions ne s'affichent pas dans le planning**
```php
// Vérifier données sessions
global $wpdb;
$sessions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tmm_sessions_meta LIMIT 5");
var_dump($sessions);

// Nettoyer cache
delete_transient('tmm_planning_cache');
```

### Mode Debug
```php
// Dans wp-config.php
define('TMM_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Logs dans wp-content/debug.log
tail -f wp-content/debug.log | grep TMM
```

---

## 🎉 Félicitations !

Votre plugin **TeachMeMore PédagoConnect** est maintenant opérationnel ! 

### Prochaines Étapes

1. **📧 Inviter vos écoles partenaires** à créer leur compte
2. **👨‍🏫 Ajouter vos formateurs** et leurs disponibilités  
3. **📅 Planifier vos premières sessions** collaboratives
4. **📊 Analyser les performances** via les rapports

### Support & Ressources

- 📖 **Documentation complète** : `/wp-content/plugins/teachmemore-pedagoconnect/README.md`
- 🔧 **Configuration avancée** : `Admin → PédagoConnect → Paramètres`
- 📊 **Analytics** : `Admin → PédagoConnect → Reporting`
- 🛠️ **Maintenance** : `Admin → PédagoConnect → Maintenance`

---

**🚀 Votre collaboration pédagogique vient de passer au niveau supérieur !**

*Plugin développé avec ❤️ pour révolutionner la formation professionnelle*