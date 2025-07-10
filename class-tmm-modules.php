<?php
/**
 * Classe pour la gestion des modules TeachMeMore
 * Catalogue complet avec compétences, RNCP et tarification
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMM_Modules {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // Hooks pour les métaboxes des modules
        add_action('add_meta_boxes', array($this, 'add_module_meta_boxes'));
        add_action('save_post', array($this, 'save_module_meta'), 10, 2);
        
        // Actions AJAX
        add_action('wp_ajax_tmm_search_modules', array($this, 'search_modules'));
        add_action('wp_ajax_tmm_get_module_info', array($this, 'get_module_info'));
        add_action('wp_ajax_tmm_duplicate_module', array($this, 'duplicate_module'));
        add_action('wp_ajax_tmm_get_module_stats', array($this, 'get_module_stats'));
        add_action('wp_ajax_tmm_update_module_pricing', array($this, 'update_module_pricing'));
        add_action('wp_ajax_tmm_get_compatible_trainers', array($this, 'get_compatible_trainers'));
        
        // Shortcodes publics
        add_shortcode('tmm_module_catalog', array($this, 'module_catalog_shortcode'));
        add_shortcode('tmm_module_detail', array($this, 'module_detail_shortcode'));
        
        // Colonnes personnalisées
        add_filter('manage_tmm_module_posts_columns', array($this, 'module_columns'));
        add_action('manage_tmm_module_posts_custom_column', array($this, 'module_column_content'), 10, 2);
        
        // Filtres admin
        add_action('restrict_manage_posts', array($this, 'module_admin_filters'));
        add_filter('parse_query', array($this, 'filter_module_queries'));
        
        // Taxonomies personnalisées
        add_action('init', array($this, 'register_module_taxonomies'));
        
        // Templates frontend
        add_filter('single_template', array($this, 'module_single_template'));
        add_filter('archive_template', array($this, 'module_archive_template'));
    }
    
    public function init() {
        // Enregistrer les statuts personnalisés
        $this->register_module_statuses();
        
        // Ajouter les capacités
        $this->add_module_capabilities();
    }
    
    /**
     * Enregistrer les taxonomies pour les modules
     */
    public function register_module_taxonomies() {
        // Catégories de modules
        register_taxonomy('module_category', 'tmm_module', array(
            'labels' => array(
                'name' => 'Catégories',
                'singular_name' => 'Catégorie',
                'add_new_item' => 'Ajouter une catégorie',
                'edit_item' => 'Modifier la catégorie',
                'new_item' => 'Nouvelle catégorie',
                'view_item' => 'Voir la catégorie',
                'search_items' => 'Rechercher des catégories',
                'not_found' => 'Aucune catégorie trouvée'
            ),
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'module-category')
        ));
        
        // Compétences techniques
        register_taxonomy('module_skill', 'tmm_module', array(
            'labels' => array(
                'name' => 'Compétences',
                'singular_name' => 'Compétence',
                'add_new_item' => 'Ajouter une compétence',
                'edit_item' => 'Modifier la compétence'
            ),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'competence')
        ));
        
        // Niveau de difficulté
        register_taxonomy('module_level', 'tmm_module', array(
            'labels' => array(
                'name' => 'Niveaux',
                'singular_name' => 'Niveau'
            ),
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true
        ));
        
        // Insérer les termes par défaut
        $this->insert_default_terms();
    }
    
    /**
     * Insérer les termes par défaut
     */
    private function insert_default_terms() {
        // Catégories par défaut
        $categories = array(
            'Développement Web' => array(
                'Frontend', 'Backend', 'Full-Stack', 'Mobile'
            ),
            'Data & IA' => array(
                'Data Science', 'Machine Learning', 'Big Data', 'Business Intelligence'
            ),
            'DevOps & Cloud' => array(
                'Infrastructure', 'Containerisation', 'CI/CD', 'Monitoring'
            ),
            'Cybersécurité' => array(
                'Sécurité Réseau', 'Ethical Hacking', 'Forensic', 'Governance'
            ),
            'Gestion de Projet' => array(
                'Agile', 'Scrum', 'Kanban', 'Leadership'
            )
        );
        
        foreach ($categories as $parent => $children) {
            $parent_term = wp_insert_term($parent, 'module_category');
            if (!is_wp_error($parent_term)) {
                foreach ($children as $child) {
                    wp_insert_term($child, 'module_category', array(
                        'parent' => $parent_term['term_id']
                    ));
                }
            }
        }
        
        // Niveaux de difficulté
        $levels = array('Débutant', 'Intermédiaire', 'Avancé', 'Expert');
        foreach ($levels as $level) {
            wp_insert_term($level, 'module_level');
        }
        
        // Compétences techniques populaires
        $skills = array(
            'JavaScript', 'Python', 'React', 'Vue.js', 'Angular', 'Node.js',
            'PHP', 'Laravel', 'Symfony', 'Docker', 'Kubernetes', 'AWS',
            'Azure', 'GCP', 'Linux', 'Git', 'SQL', 'NoSQL', 'MongoDB',
            'PostgreSQL', 'Redis', 'Elasticsearch', 'TensorFlow', 'PyTorch'
        );
        foreach ($skills as $skill) {
            wp_insert_term($skill, 'module_skill');
        }
    }
    
    /**
     * Enregistrer les statuts personnalisés
     */
    private function register_module_statuses() {
        register_post_status('active_module', array(
            'label' => 'Module Actif',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Module Actif (%s)', 'Modules Actifs (%s)')
        ));
        
        register_post_status('deprecated', array(
            'label' => 'Obsolète',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Obsolète (%s)', 'Obsolètes (%s)')
        ));
        
        register_post_status('development', array(
            'label' => 'En Développement',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('En Développement (%s)', 'En Développement (%s)')
        ));
    }
    
    /**
     * Ajouter les capacités pour les modules
     */
    private function add_module_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_modules');
            $role->add_cap('edit_module_pricing');
            $role->add_cap('view_module_analytics');
        }
        
        $manager_role = get_role('tmm_pedagog_manager');
        if ($manager_role) {
            $manager_role->add_cap('manage_modules');
            $manager_role->add_cap('edit_module_pricing');
            $manager_role->add_cap('view_module_analytics');
        }
    }
    
    /**
     * Ajouter les métaboxes pour les modules
     */
    public function add_module_meta_boxes() {
        add_meta_box(
            'tmm_module_details',
            'Détails du Module',
            array($this, 'module_details_meta_box'),
            'tmm_module',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tmm_module_pedagogical',
            'Informations Pédagogiques',
            array($this, 'module_pedagogical_meta_box'),
            'tmm_module',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tmm_module_pricing',
            'Tarification',
            array($this, 'module_pricing_meta_box'),
            'tmm_module',
            'side',
            'high'
        );
        
        add_meta_box(
            'tmm_module_requirements',
            'Prérequis & Objectifs',
            array($this, 'module_requirements_meta_box'),
            'tmm_module',
            'normal',
            'default'
        );
        
        add_meta_box(
            'tmm_module_resources',
            'Ressources & Supports',
            array($this, 'module_resources_meta_box'),
            'tmm_module',
            'normal',
            'default'
        );
        
        add_meta_box(
            'tmm_module_stats',
            'Statistiques d\'Usage',
            array($this, 'module_stats_meta_box'),
            'tmm_module',
            'side',
            'default'
        );
    }
    
    /**
     * Métabox détails du module
     */
    public function module_details_meta_box($post) {
        wp_nonce_field('tmm_module_meta', 'tmm_module_meta_nonce');
        
        $duration_hours = get_post_meta($post->ID, 'duration_hours', true);
        $duration_days = get_post_meta($post->ID, 'duration_days', true);
        $format = get_post_meta($post->ID, 'format', true);
        $max_participants = get_post_meta($post->ID, 'max_participants', true);
        $language = get_post_meta($post->ID, 'language', true);
        $version = get_post_meta($post->ID, 'version', true);
        $last_update = get_post_meta($post->ID, 'last_update', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="duration_hours">Durée (heures)</label></th>
                <td>
                    <input type="number" id="duration_hours" name="duration_hours" 
                           value="<?php echo esc_attr($duration_hours); ?>" 
                           min="0.5" max="200" step="0.5" class="small-text" />
                    <span class="description">heures de formation</span>
                </td>
            </tr>
            
            <tr>
                <th><label for="duration_days">Durée (jours)</label></th>
                <td>
                    <input type="number" id="duration_days" name="duration_days" 
                           value="<?php echo esc_attr($duration_days); ?>" 
                           min="0.5" max="30" step="0.5" class="small-text" />
                    <span class="description">jours de formation (calculé automatiquement)</span>
                </td>
            </tr>
            
            <tr>
                <th><label for="format">Format de Formation</label></th>
                <td>
                    <select id="format" name="format" class="widefat">
                        <option value="">Sélectionner un format</option>
                        <option value="presential" <?php selected($format, 'presential'); ?>>Présentiel</option>
                        <option value="remote" <?php selected($format, 'remote'); ?>>Distanciel</option>
                        <option value="hybrid" <?php selected($format, 'hybrid'); ?>>Hybride</option>
                        <option value="elearning" <?php selected($format, 'elearning'); ?>>E-learning</option>
                        <option value="bootcamp" <?php selected($format, 'bootcamp'); ?>>Bootcamp Intensif</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="max_participants">Participants Maximum</label></th>
                <td>
                    <input type="number" id="max_participants" name="max_participants" 
                           value="<?php echo esc_attr($max_participants); ?>" 
                           min="1" max="100" class="small-text" />
                    <span class="description">nombre maximum de participants par session</span>
                </td>
            </tr>
            
            <tr>
                <th><label for="language">Langue</label></th>
                <td>
                    <select id="language" name="language" class="widefat">
                        <option value="fr" <?php selected($language, 'fr'); ?>>Français</option>
                        <option value="en" <?php selected($language, 'en'); ?>>Anglais</option>
                        <option value="es" <?php selected($language, 'es'); ?>>Espagnol</option>
                        <option value="de" <?php selected($language, 'de'); ?>>Allemand</option>
                        <option value="bilingual" <?php selected($language, 'bilingual'); ?>>Bilingue FR/EN</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="version">Version du Module</label></th>
                <td>
                    <input type="text" id="version" name="version" 
                           value="<?php echo esc_attr($version ?: '1.0'); ?>" 
                           class="regular-text" placeholder="1.0" />
                    <span class="description">ex: 1.0, 2.1, 3.0-beta</span>
                </td>
            </tr>
            
            <tr>
                <th><label for="last_update">Dernière Mise à Jour</label></th>
                <td>
                    <input type="date" id="last_update" name="last_update" 
                           value="<?php echo esc_attr($last_update ?: date('Y-m-d')); ?>" 
                           class="regular-text" />
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-calcul durée jours basé sur heures
            $('#duration_hours').on('input', function() {
                var hours = parseFloat($(this).val()) || 0;
                var days = Math.round((hours / 7) * 10) / 10; // 7h = 1 jour
                $('#duration_days').val(days);
            });
            
            // Auto-calcul heures basé sur jours
            $('#duration_days').on('input', function() {
                var days = parseFloat($(this).val()) || 0;
                var hours = days * 7;
                $('#duration_hours').val(hours);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Métabox informations pédagogiques
     */
    public function module_pedagogical_meta_box($post) {
        $pedagogy_method = get_post_meta($post->ID, 'pedagogy_method', true);
        $evaluation_method = get_post_meta($post->ID, 'evaluation_method', true);
        $certification = get_post_meta($post->ID, 'certification', true);
        $rncp_block = get_post_meta($post->ID, 'rncp_block', true);
        $rncp_level = get_post_meta($post->ID, 'rncp_level', true);
        $target_audience = get_post_meta($post->ID, 'target_audience', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="pedagogy_method">Méthode Pédagogique</label></th>
                <td>
                    <select id="pedagogy_method" name="pedagogy_method" class="widefat">
                        <option value="">Sélectionner une méthode</option>
                        <option value="theoretical" <?php selected($pedagogy_method, 'theoretical'); ?>>Théorique</option>
                        <option value="practical" <?php selected($pedagogy_method, 'practical'); ?>>Pratique</option>
                        <option value="mixed" <?php selected($pedagogy_method, 'mixed'); ?>>Mixte (Théorie + Pratique)</option>
                        <option value="project_based" <?php selected($pedagogy_method, 'project_based'); ?>>Par Projets</option>
                        <option value="case_study" <?php selected($pedagogy_method, 'case_study'); ?>>Études de Cas</option>
                        <option value="workshop" <?php selected($pedagogy_method, 'workshop'); ?>>Atelier Participatif</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="evaluation_method">Méthode d'Évaluation</label></th>
                <td>
                    <select id="evaluation_method" name="evaluation_method" class="widefat">
                        <option value="">Sélectionner une méthode</option>
                        <option value="exam" <?php selected($evaluation_method, 'exam'); ?>>Examen Final</option>
                        <option value="continuous" <?php selected($evaluation_method, 'continuous'); ?>>Contrôle Continu</option>
                        <option value="project" <?php selected($evaluation_method, 'project'); ?>>Projet Final</option>
                        <option value="presentation" <?php selected($evaluation_method, 'presentation'); ?>>Présentation Orale</option>
                        <option value="portfolio" <?php selected($evaluation_method, 'portfolio'); ?>>Portfolio</option>
                        <option value="peer_review" <?php selected($evaluation_method, 'peer_review'); ?>>Évaluation par les Pairs</option>
                        <option value="none" <?php selected($evaluation_method, 'none'); ?>>Pas d'Évaluation</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="certification">Certification</label></th>
                <td>
                    <input type="text" id="certification" name="certification" 
                           value="<?php echo esc_attr($certification); ?>" 
                           class="widefat" placeholder="ex: Certificat TeachMeMore DevOps Expert" />
                    <p class="description">Nom de la certification délivrée (si applicable)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="rncp_block">Bloc de Compétences RNCP</label></th>
                <td>
                    <input type="text" id="rncp_block" name="rncp_block" 
                           value="<?php echo esc_attr($rncp_block); ?>" 
                           class="widefat" placeholder="ex: RNCP34126-BC02" />
                    <p class="description">Référence du bloc de compétences RNCP correspondant</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="rncp_level">Niveau RNCP</label></th>
                <td>
                    <select id="rncp_level" name="rncp_level" class="widefat">
                        <option value="">Sélectionner un niveau</option>
                        <option value="3" <?php selected($rncp_level, '3'); ?>>Niveau 3 (CAP, BEP)</option>
                        <option value="4" <?php selected($rncp_level, '4'); ?>>Niveau 4 (Bac)</option>
                        <option value="5" <?php selected($rncp_level, '5'); ?>>Niveau 5 (Bac+2)</option>
                        <option value="6" <?php selected($rncp_level, '6'); ?>>Niveau 6 (Bac+3/4)</option>
                        <option value="7" <?php selected($rncp_level, '7'); ?>>Niveau 7 (Bac+5)</option>
                        <option value="8" <?php selected($rncp_level, '8'); ?>>Niveau 8 (Bac+8)</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="target_audience">Public Cible</label></th>
                <td>
                    <textarea id="target_audience" name="target_audience" rows="3" class="widefat"><?php echo esc_textarea($target_audience); ?></textarea>
                    <p class="description">Description du profil des participants idéaux</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Métabox tarification
     */
    public function module_pricing_meta_box($post) {
        $hourly_rate = get_post_meta($post->ID, 'hourly_rate', true);
        $fixed_price = get_post_meta($post->ID, 'fixed_price', true);
        $pricing_type = get_post_meta($post->ID, 'pricing_type', true) ?: 'hourly';
        $discount_volume = get_post_meta($post->ID, 'discount_volume', true);
        $pricing_notes = get_post_meta($post->ID, 'pricing_notes', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th>Type de Tarification</th>
                <td>
                    <label>
                        <input type="radio" name="pricing_type" value="hourly" <?php checked($pricing_type, 'hourly'); ?> />
                        Tarif Horaire
                    </label><br>
                    <label>
                        <input type="radio" name="pricing_type" value="fixed" <?php checked($pricing_type, 'fixed'); ?> />
                        Prix Forfaitaire
                    </label><br>
                    <label>
                        <input type="radio" name="pricing_type" value="negotiable" <?php checked($pricing_type, 'negotiable'); ?> />
                        Sur Devis
                    </label>
                </td>
            </tr>
            
            <tr class="hourly-pricing" <?php echo $pricing_type !== 'hourly' ? 'style="display:none"' : ''; ?>>
                <th><label for="hourly_rate">Tarif Horaire (€)</label></th>
                <td>
                    <input type="number" id="hourly_rate" name="hourly_rate" 
                           value="<?php echo esc_attr($hourly_rate); ?>" 
                           min="50" max="1000" step="5" class="small-text" />
                    <span class="description">€/heure</span>
                </td>
            </tr>
            
            <tr class="fixed-pricing" <?php echo $pricing_type !== 'fixed' ? 'style="display:none"' : ''; ?>>
                <th><label for="fixed_price">Prix Forfaitaire (€)</label></th>
                <td>
                    <input type="number" id="fixed_price" name="fixed_price" 
                           value="<?php echo esc_attr($fixed_price); ?>" 
                           min="100" max="50000" step="50" class="regular-text" />
                    <span class="description">€ pour tout le module</span>
                </td>
            </tr>
            
            <tr>
                <th><label for="discount_volume">Remise Volume (%)</label></th>
                <td>
                    <input type="number" id="discount_volume" name="discount_volume" 
                           value="<?php echo esc_attr($discount_volume); ?>" 
                           min="0" max="50" step="1" class="small-text" />
                    <span class="description">% de remise pour sessions multiples</span>
                </td>
            </tr>
            
            <tr>
                <th><label for="pricing_notes">Notes Tarification</label></th>
                <td>
                    <textarea id="pricing_notes" name="pricing_notes" rows="3" class="widefat"><?php echo esc_textarea($pricing_notes); ?></textarea>
                    <p class="description">Conditions particulières, inclusions/exclusions</p>
                </td>
            </tr>
        </table>
        
        <div class="pricing-calculator">
            <h4>Calculateur de Prix</h4>
            <div id="price-preview">
                <!-- Calculé dynamiquement via JavaScript -->
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Afficher/masquer champs selon type de tarification
            $('input[name="pricing_type"]').change(function() {
                $('.hourly-pricing, .fixed-pricing').hide();
                if ($(this).val() === 'hourly') {
                    $('.hourly-pricing').show();
                } else if ($(this).val() === 'fixed') {
                    $('.fixed-pricing').show();
                }
                updatePricePreview();
            });
            
            // Calculateur de prix en temps réel
            $('#hourly_rate, #fixed_price, #duration_hours, #discount_volume').on('input', updatePricePreview);
            
            function updatePricePreview() {
                var pricingType = $('input[name="pricing_type"]:checked').val();
                var hours = parseFloat($('#duration_hours').val()) || 0;
                var hourlyRate = parseFloat($('#hourly_rate').val()) || 0;
                var fixedPrice = parseFloat($('#fixed_price').val()) || 0;
                var discount = parseFloat($('#discount_volume').val()) || 0;
                
                var price = 0;
                var details = '';
                
                if (pricingType === 'hourly' && hours > 0 && hourlyRate > 0) {
                    price = hours * hourlyRate;
                    details = hours + 'h × ' + hourlyRate + '€/h = ' + price + '€';
                    
                    if (discount > 0) {
                        var discountAmount = price * (discount / 100);
                        var finalPrice = price - discountAmount;
                        details += '<br>Avec remise ' + discount + '% : ' + finalPrice + '€';
                    }
                } else if (pricingType === 'fixed' && fixedPrice > 0) {
                    price = fixedPrice;
                    details = 'Prix forfaitaire : ' + price + '€';
                    
                    if (discount > 0) {
                        var discountAmount = price * (discount / 100);
                        var finalPrice = price - discountAmount;
                        details += '<br>Avec remise ' + discount + '% : ' + finalPrice + '€';
                    }
                } else if (pricingType === 'negotiable') {
                    details = 'Prix sur devis selon besoins client';
                }
                
                $('#price-preview').html(details);
            }
            
            // Calcul initial
            updatePricePreview();
        });
        </script>
        
        <style>
        .pricing-calculator {
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .pricing-calculator h4 {
            margin: 0 0 10px 0;
            font-size: 13px;
        }
        
        #price-preview {
            font-weight: bold;
            color: #2271b1;
        }
        </style>
        <?php
    }
    
    /**
     * Métabox prérequis et objectifs
     */
    public function module_requirements_meta_box($post) {
        $prerequisites = get_post_meta($post->ID, 'prerequisites', true);
        $learning_objectives = get_post_meta($post->ID, 'learning_objectives', true);
        $required_skills = get_post_meta($post->ID, 'required_skills', true);
        $acquired_skills = get_post_meta($post->ID, 'acquired_skills', true);
        $technical_requirements = get_post_meta($post->ID, 'technical_requirements', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="prerequisites">Prérequis</label></th>
                <td>
                    <textarea id="prerequisites" name="prerequisites" rows="4" class="widefat"><?php echo esc_textarea($prerequisites); ?></textarea>
                    <p class="description">Connaissances ou expérience requises avant la formation</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="required_skills">Compétences Requises</label></th>
                <td>
                    <textarea id="required_skills" name="required_skills" rows="3" class="widefat"><?php echo esc_textarea($required_skills); ?></textarea>
                    <p class="description">Compétences techniques nécessaires (séparées par des virgules)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="learning_objectives">Objectifs Pédagogiques</label></th>
                <td>
                    <textarea id="learning_objectives" name="learning_objectives" rows="5" class="widefat"><?php echo esc_textarea($learning_objectives); ?></textarea>
                    <p class="description">Objectifs d'apprentissage et résultats attendus</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="acquired_skills">Compétences Acquises</label></th>
                <td>
                    <textarea id="acquired_skills" name="acquired_skills" rows="4" class="widefat"><?php echo esc_textarea($acquired_skills); ?></textarea>
                    <p class="description">Compétences que les participants vont acquérir</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="technical_requirements">Exigences Techniques</label></th>
                <td>
                    <textarea id="technical_requirements" name="technical_requirements" rows="3" class="widefat"><?php echo esc_textarea($technical_requirements); ?></textarea>
                    <p class="description">Matériel, logiciels ou infrastructure nécessaires</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Métabox ressources et supports
     */
    public function module_resources_meta_box($post) {
        $course_materials = get_post_meta($post->ID, 'course_materials', true) ?: array();
        $recommended_books = get_post_meta($post->ID, 'recommended_books', true);
        $online_resources = get_post_meta($post->ID, 'online_resources', true);
        $lab_exercises = get_post_meta($post->ID, 'lab_exercises', true);
        
        ?>
        <h4>Supports de Cours</h4>
        <div id="course-materials">
            <?php foreach ($course_materials as $index => $material): ?>
                <div class="material-row">
                    <input type="text" name="course_materials[<?php echo $index; ?>][title]" 
                           value="<?php echo esc_attr($material['title']); ?>" 
                           placeholder="Titre du support" style="width: 40%;" />
                    <select name="course_materials[<?php echo $index; ?>][type]" style="width: 25%;">
                        <option value="pdf" <?php selected($material['type'], 'pdf'); ?>>PDF</option>
                        <option value="slide" <?php selected($material['type'], 'slide'); ?>>Présentation</option>
                        <option value="video" <?php selected($material['type'], 'video'); ?>>Vidéo</option>
                        <option value="exercise" <?php selected($material['type'], 'exercise'); ?>>Exercice</option>
                        <option value="code" <?php selected($material['type'], 'code'); ?>>Code Source</option>
                    </select>
                    <input type="url" name="course_materials[<?php echo $index; ?>][url]" 
                           value="<?php echo esc_url($material['url']); ?>" 
                           placeholder="URL du fichier" style="width: 25%;" />
                    <button type="button" class="button remove-material">Supprimer</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-material" class="button">Ajouter un Support</button>
        
        <table class="form-table" style="margin-top: 20px;">
            <tr>
                <th><label for="recommended_books">Livres Recommandés</label></th>
                <td>
                    <textarea id="recommended_books" name="recommended_books" rows="3" class="widefat"><?php echo esc_textarea($recommended_books); ?></textarea>
                    <p class="description">Bibliographie recommandée (un livre par ligne)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="online_resources">Ressources en Ligne</label></th>
                <td>
                    <textarea id="online_resources" name="online_resources" rows="3" class="widefat"><?php echo esc_textarea($online_resources); ?></textarea>
                    <p class="description">Liens vers des ressources utiles (un lien par ligne)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="lab_exercises">Exercices Pratiques</label></th>
                <td>
                    <textarea id="lab_exercises" name="lab_exercises" rows="4" class="widefat"><?php echo esc_textarea($lab_exercises); ?></textarea>
                    <p class="description">Description des travaux pratiques et projets</p>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            var materialIndex = <?php echo count($course_materials); ?>;
            
            $('#add-material').click(function() {
                $('#course-materials').append(`
                    <div class="material-row">
                        <input type="text" name="course_materials[${materialIndex}][title]" 
                               placeholder="Titre du support" style="width: 40%;" />
                        <select name="course_materials[${materialIndex}][type]" style="width: 25%;">
                            <option value="pdf">PDF</option>
                            <option value="slide">Présentation</option>
                            <option value="video">Vidéo</option>
                            <option value="exercise">Exercice</option>
                            <option value="code">Code Source</option>
                        </select>
                        <input type="url" name="course_materials[${materialIndex}][url]" 
                               placeholder="URL du fichier" style="width: 25%;" />
                        <button type="button" class="button remove-material">Supprimer</button>
                    </div>
                `);
                materialIndex++;
            });
            
            $(document).on('click', '.remove-material', function() {
                $(this).closest('.material-row').remove();
            });
        });
        </script>
        
        <style>
        .material-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        </style>
        <?php
    }
    
    /**
     * Métabox statistiques module
     */
    public function module_stats_meta_box($post) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
        
        // Statistiques d'usage
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table WHERE module_id = %d", $post->ID
        ));
        
        $completed_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.module_id = %d AND p.post_status = 'completed'",
            $post->ID
        ));
        
        $total_hours_delivered = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(hours_realized) FROM $sessions_table s
             JOIN {$wpdb->posts} p ON s.session_id = p.ID
             WHERE s.module_id = %d AND p.post_status = 'completed'",
            $post->ID
        ));
        
        $avg_satisfaction = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(pm.meta_value AS DECIMAL(3,2))) FROM {$wpdb->postmeta} pm
             JOIN $sessions_table s ON pm.post_id = s.session_id
             WHERE s.module_id = %d AND pm.meta_key = 'module_satisfaction_rating'",
            $post->ID
        ));
        
        $schools_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT school_id) FROM $sessions_table WHERE module_id = %d", $post->ID
        ));
        
        $last_session = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(start_datetime) FROM $sessions_table WHERE module_id = %d", $post->ID
        ));
        
        ?>
        <div class="module-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_sessions ?: 0; ?></span>
                <span class="stat-label">Sessions Totales</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number"><?php echo $completed_sessions ?: 0; ?></span>
                <span class="stat-label">Sessions Terminées</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number"><?php echo round($total_hours_delivered ?: 0); ?>h</span>
                <span class="stat-label">Heures Dispensées</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number">
                    <?php if ($avg_satisfaction): ?>
                        <?php echo round($avg_satisfaction, 1); ?>/5 ⭐
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
                <span class="stat-label">Satisfaction Moyenne</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number"><?php echo $schools_count ?: 0; ?></span>
                <span class="stat-label">Écoles Clientes</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-label">Dernière Session</span>
                <span class="stat-number">
                    <?php if ($last_session): ?>
                        <?php echo human_time_diff(strtotime($last_session), current_time('timestamp')); ?> ago
                    <?php else: ?>
                        Jamais
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="module-actions">
            <button type="button" class="button button-primary" onclick="viewModuleAnalytics(<?php echo $post->ID; ?>)">
                📊 Analytics Détaillés
            </button>
            
            <button type="button" class="button" onclick="duplicateModule(<?php echo $post->ID; ?>)">
                📋 Dupliquer Module
            </button>
            
            <button type="button" class="button" onclick="createSessionFromModule(<?php echo $post->ID; ?>)">
                ➕ Créer Session
            </button>
        </div>
        
        <style>
        .module-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .stat-number {
            display: block;
            font-size: 16px;
            font-weight: bold;
            color: #2271b1;
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        
        .module-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        </style>
        
        <script>
        function viewModuleAnalytics(moduleId) {
            window.open(adminURL + 'admin.php?page=tmm-module-analytics&module_id=' + moduleId, '_blank');
        }
        
        function duplicateModule(moduleId) {
            if (confirm('Dupliquer ce module?')) {
                jQuery.post(ajaxurl, {
                    action: 'tmm_duplicate_module',
                    module_id: moduleId,
                    nonce: tmmNonce
                }, function(response) {
                    if (response.success) {
                        window.location.href = 'post.php?post=' + response.data.new_module_id + '&action=edit';
                    } else {
                        alert('Erreur: ' + response.data);
                    }
                });
            }
        }
        
        function createSessionFromModule(moduleId) {
            jQuery('#module-select').val(moduleId);
            jQuery('#quick-session-modal').show();
        }
        </script>
        <?php
    }
    
    /**
     * Sauvegarder les métadonnées des modules
     */
    public function save_module_meta($post_id, $post) {
        if (!isset($_POST['tmm_module_meta_nonce']) || 
            !wp_verify_nonce($_POST['tmm_module_meta_nonce'], 'tmm_module_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if ($post->post_type !== 'tmm_module') {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Champs simples
        $simple_fields = array(
            'duration_hours', 'duration_days', 'format', 'max_participants', 'language',
            'version', 'last_update', 'pedagogy_method', 'evaluation_method', 
            'certification', 'rncp_block', 'rncp_level', 'target_audience',
            'pricing_type', 'hourly_rate', 'fixed_price', 'discount_volume', 'pricing_notes',
            'prerequisites', 'learning_objectives', 'required_skills', 'acquired_skills',
            'technical_requirements', 'recommended_books', 'online_resources', 'lab_exercises'
        );
        
        foreach ($simple_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Supports de cours
        if (isset($_POST['course_materials'])) {
            $materials = array();
            foreach ($_POST['course_materials'] as $material) {
                if (!empty($material['title'])) {
                    $materials[] = array(
                        'title' => sanitize_text_field($material['title']),
                        'type' => sanitize_text_field($material['type']),
                        'url' => esc_url_raw($material['url'])
                    );
                }
            }
            update_post_meta($post_id, 'course_materials', $materials);
        }
        
        // Calculer automatiquement le prix total si changement
        $this->update_calculated_fields($post_id);
        
        // Déclencher hook
        do_action('tmm_module_meta_saved', $post_id, $_POST);
    }
    
    /**
     * Mettre à jour les champs calculés
     */
    private function update_calculated_fields($post_id) {
        $pricing_type = get_post_meta($post_id, 'pricing_type', true);
        $duration_hours = floatval(get_post_meta($post_id, 'duration_hours', true));
        
        if ($pricing_type === 'hourly' && $duration_hours > 0) {
            $hourly_rate = floatval(get_post_meta($post_id, 'hourly_rate', true));
            $total_price = $duration_hours * $hourly_rate;
            update_post_meta($post_id, 'calculated_total_price', $total_price);
        } elseif ($pricing_type === 'fixed') {
            $fixed_price = floatval(get_post_meta($post_id, 'fixed_price', true));
            update_post_meta($post_id, 'calculated_total_price', $fixed_price);
        }
        
        // Calculer automatiquement les jours si pas défini
        if ($duration_hours > 0) {
            $duration_days = get_post_meta($post_id, 'duration_days', true);
            if (empty($duration_days)) {
                $calculated_days = round($duration_hours / 7, 1);
                update_post_meta($post_id, 'duration_days', $calculated_days);
            }
        }
    }
    
    /**
     * Colonnes personnalisées pour les modules
     */
    public function module_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['category'] = 'Catégorie';
        $new_columns['duration'] = 'Durée';
        $new_columns['level'] = 'Niveau';
        $new_columns['pricing'] = 'Tarif';
        $new_columns['sessions'] = 'Sessions';
        $new_columns['satisfaction'] = 'Satisfaction';
        $new_columns['status'] = 'Statut';
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    public function module_column_content($column, $post_id) {
        switch ($column) {
            case 'category':
                $categories = get_the_terms($post_id, 'module_category');
                if ($categories) {
                    $cat_names = array_map(function($cat) { return $cat->name; }, $categories);
                    echo esc_html(implode(', ', $cat_names));
                } else {
                    echo '-';
                }
                break;
                
            case 'duration':
                $hours = get_post_meta($post_id, 'duration_hours', true);
                $days = get_post_meta($post_id, 'duration_days', true);
                if ($hours) {
                    echo $hours . 'h';
                    if ($days) echo ' (' . $days . 'j)';
                } else {
                    echo '-';
                }
                break;
                
            case 'level':
                $levels = get_the_terms($post_id, 'module_level');
                if ($levels) {
                    echo esc_html($levels[0]->name);
                } else {
                    echo '-';
                }
                break;
                
            case 'pricing':
                $pricing_type = get_post_meta($post_id, 'pricing_type', true);
                $hourly_rate = get_post_meta($post_id, 'hourly_rate', true);
                $fixed_price = get_post_meta($post_id, 'fixed_price', true);
                
                if ($pricing_type === 'hourly' && $hourly_rate) {
                    echo $hourly_rate . '€/h';
                } elseif ($pricing_type === 'fixed' && $fixed_price) {
                    echo number_format($fixed_price, 0, ',', ' ') . '€';
                } elseif ($pricing_type === 'negotiable') {
                    echo 'Sur devis';
                } else {
                    echo '-';
                }
                break;
                
            case 'sessions':
                global $wpdb;
                $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $sessions_table WHERE module_id = %d", $post_id
                ));
                echo $count ?: '0';
                break;
                
            case 'satisfaction':
                global $wpdb;
                $sessions_table = $wpdb->prefix . 'tmm_sessions_meta';
                $avg = $wpdb->get_var($wpdb->prepare(
                    "SELECT AVG(CAST(pm.meta_value AS DECIMAL(3,2))) FROM {$wpdb->postmeta} pm
                     JOIN $sessions_table s ON pm.post_id = s.session_id
                     WHERE s.module_id = %d AND pm.meta_key = 'module_satisfaction_rating'",
                    $post_id
                ));
                
                if ($avg) {
                    echo round($avg, 1) . '/5 ⭐';
                } else {
                    echo '-';
                }
                break;
                
            case 'status':
                $status = get_post_status($post_id);
                $status_labels = array(
                    'publish' => '<span style="color: #46b450;">Publié</span>',
                    'active_module' => '<span style="color: #00a32a;">Actif</span>',
                    'deprecated' => '<span style="color: #d63638;">Obsolète</span>',
                    'development' => '<span style="color: #dba617;">Développement</span>'
                );
                echo $status_labels[$status] ?? $status;
                break;
        }
    }
    
    /**
     * AJAX: Dupliquer un module
     */
    public function duplicate_module() {
        check_ajax_referer('tmm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_modules')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $module_id = intval($_POST['module_id']);
        $original_module = get_post($module_id);
        
        if (!$original_module) {
            wp_send_json_error('Module non trouvé');
        }
        
        // Créer la copie
        $new_module_data = array(
            'post_title' => $original_module->post_title . ' (Copie)',
            'post_content' => $original_module->post_content,
            'post_type' => 'tmm_module',
            'post_status' => 'draft',
            'post_author' => get_current_user_id()
        );
        
        $new_module_id = wp_insert_post($new_module_data);
        
        if ($new_module_id) {
            // Copier toutes les métadonnées
            $meta_data = get_post_meta($module_id);
            foreach ($meta_data as $key => $values) {
                foreach ($values as $value) {
                    add_post_meta($new_module_id, $key, maybe_unserialize($value));
                }
            }
            
            // Copier les taxonomies
            $taxonomies = get_object_taxonomies('tmm_module');
            foreach ($taxonomies as $taxonomy) {
                $terms = get_the_terms($module_id, $taxonomy);
                if ($terms) {
                    $term_ids = array_map(function($term) { return $term->term_id; }, $terms);
                    wp_set_object_terms($new_module_id, $term_ids, $taxonomy);
                }
            }
            
            wp_send_json_success(array(
                'new_module_id' => $new_module_id,
                'message' => 'Module dupliqué avec succès'
            ));
        } else {
            wp_send_json_error('Erreur lors de la duplication');
        }
    }
    
    /**
     * Shortcode catalogue de modules
     */
    public function module_catalog_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'level' => '',
            'limit' => 12,
            'columns' => 3,
            'show_price' => 'true',
            'show_duration' => 'true'
        ), $atts);
        
        $args = array(
            'post_type' => 'tmm_module',
            'post_status' => array('publish', 'active_module'),
            'posts_per_page' => intval($atts['limit']),
            'meta_query' => array()
        );
        
        if (!empty($atts['category'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'module_category',
                'field' => 'slug',
                'terms' => $atts['category']
            );
        }
        
        if (!empty($atts['level'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'module_level', 
                'field' => 'slug',
                'terms' => $atts['level']
            );
        }
        
        $modules = get_posts($args);
        
        ob_start();
        ?>
        <div class="tmm-module-catalog" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($modules as $module): ?>
                <div class="module-card">
                    <?php if (has_post_thumbnail($module->ID)): ?>
                        <div class="module-thumbnail">
                            <?php echo get_the_post_thumbnail($module->ID, 'medium'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="module-content">
                        <h3><a href="<?php echo get_permalink($module->ID); ?>"><?php echo esc_html($module->post_title); ?></a></h3>
                        
                        <div class="module-meta">
                            <?php if ($atts['show_duration'] === 'true'): ?>
                                <?php $hours = get_post_meta($module->ID, 'duration_hours', true); ?>
                                <?php if ($hours): ?>
                                    <span class="module-duration">⏱️ <?php echo $hours; ?>h</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($atts['show_price'] === 'true'): ?>
                                <?php $pricing_type = get_post_meta($module->ID, 'pricing_type', true); ?>
                                <?php if ($pricing_type === 'hourly'): ?>
                                    <?php $rate = get_post_meta($module->ID, 'hourly_rate', true); ?>
                                    <?php if ($rate): ?>
                                        <span class="module-price">💰 <?php echo $rate; ?>€/h</span>
                                    <?php endif; ?>
                                <?php elseif ($pricing_type === 'fixed'): ?>
                                    <?php $price = get_post_meta($module->ID, 'fixed_price', true); ?>
                                    <?php if ($price): ?>
                                        <span class="module-price">💰 <?php echo number_format($price, 0, ',', ' '); ?>€</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="module-excerpt">
                            <?php echo wp_trim_words($module->post_content, 20); ?>
                        </div>
                        
                        <div class="module-skills">
                            <?php
                            $skills = get_the_terms($module->ID, 'module_skill');
                            if ($skills) {
                                foreach (array_slice($skills, 0, 3) as $skill) {
                                    echo '<span class="skill-tag">' . esc_html($skill->name) . '</span>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <style>
        .tmm-module-catalog {
            display: grid;
            gap: 20px;
            margin: 20px 0;
        }
        
        .tmm-module-catalog[data-columns="2"] { grid-template-columns: repeat(2, 1fr); }
        .tmm-module-catalog[data-columns="3"] { grid-template-columns: repeat(3, 1fr); }
        .tmm-module-catalog[data-columns="4"] { grid-template-columns: repeat(4, 1fr); }
        
        .module-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
        }
        
        .module-thumbnail img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .module-content {
            padding: 20px;
        }
        
        .module-content h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .module-content h3 a {
            color: #333;
            text-decoration: none;
        }
        
        .module-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .module-excerpt {
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .skill-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .tmm-module-catalog {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Rechercher des modules
     */
    public function search_modules() {
        check_ajax_referer('tmm_admin_nonce', 'nonce');
        
        $term = sanitize_text_field($_POST['term']);
        
        $modules = get_posts(array(
            'post_type' => 'tmm_module',
            'post_status' => array('publish', 'active_module'),
            's' => $term,
            'numberposts' => 10
        ));
        
        $results = array();
        foreach ($modules as $module) {
            $duration = get_post_meta($module->ID, 'duration_hours', true);
            $pricing_type = get_post_meta($module->ID, 'pricing_type', true);
            $rate = get_post_meta($module->ID, 'hourly_rate', true);
            
            $results[] = array(
                'id' => $module->ID,
                'label' => $module->post_title,
                'value' => $module->post_title,
                'duration' => $duration,
                'pricing_type' => $pricing_type,
                'hourly_rate' => $rate
            );
        }
        
        wp_send_json($results);
    }
}

// Initialiser la classe
new TMM_Modules();