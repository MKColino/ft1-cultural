<?php
/**
 * Roles and capabilities management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Roles {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_roles'));
        add_action('user_register', array($this, 'assign_default_role'));
        add_filter('user_has_cap', array($this, 'filter_user_capabilities'), 10, 4);
    }
    
    /**
     * Initialize roles and capabilities
     */
    public function init_roles() {
        // Only run once during plugin activation
        if (!get_option('ft1_cultural_roles_created')) {
            $this->create_roles();
            update_option('ft1_cultural_roles_created', true);
        }
        
        // Always ensure capabilities are up to date
        $this->update_role_capabilities();
    }
    
    /**
     * Create custom roles
     */
    public static function create_roles() {
        // Remove existing roles first
        self::remove_roles();
        
        // FT1 Administrator - Full access to all FT1 Cultural features
        add_role('ft1_administrator', __('FT1 Administrador', 'ft1-cultural'), array(
            'read' => true,
            'manage_ft1_cultural' => true,
            'manage_ft1_settings' => true,
            'manage_ft1_editais' => true,
            'create_ft1_editais' => true,
            'edit_ft1_editais' => true,
            'delete_ft1_editais' => true,
            'publish_ft1_editais' => true,
            'manage_ft1_proponentes' => true,
            'create_ft1_proponentes' => true,
            'edit_ft1_proponentes' => true,
            'delete_ft1_proponentes' => true,
            'view_ft1_proponentes' => true,
            'manage_ft1_projetos' => true,
            'create_ft1_projetos' => true,
            'edit_ft1_projetos' => true,
            'delete_ft1_projetos' => true,
            'evaluate_ft1_projetos' => true,
            'approve_ft1_projetos' => true,
            'view_ft1_projetos' => true,
            'manage_ft1_contratos' => true,
            'create_ft1_contratos' => true,
            'edit_ft1_contratos' => true,
            'delete_ft1_contratos' => true,
            'send_ft1_contratos' => true,
            'view_ft1_contratos' => true,
            'manage_ft1_documents' => true,
            'upload_ft1_documents' => true,
            'validate_ft1_documents' => true,
            'delete_ft1_documents' => true,
            'view_ft1_dashboard' => true,
            'view_ft1_reports' => true,
            'view_ft1_calendar' => true,
            'manage_ft1_notifications' => true
        ));
        
        // FT1 Manager - Manage editais, evaluate projects, manage contracts
        add_role('ft1_manager', __('FT1 Gestor', 'ft1-cultural'), array(
            'read' => true,
            'manage_ft1_editais' => true,
            'create_ft1_editais' => true,
            'edit_ft1_editais' => true,
            'publish_ft1_editais' => true,
            'view_ft1_proponentes' => true,
            'edit_ft1_proponentes' => true,
            'manage_ft1_projetos' => true,
            'edit_ft1_projetos' => true,
            'evaluate_ft1_projetos' => true,
            'approve_ft1_projetos' => true,
            'view_ft1_projetos' => true,
            'manage_ft1_contratos' => true,
            'create_ft1_contratos' => true,
            'edit_ft1_contratos' => true,
            'send_ft1_contratos' => true,
            'view_ft1_contratos' => true,
            'upload_ft1_documents' => true,
            'validate_ft1_documents' => true,
            'view_ft1_dashboard' => true,
            'view_ft1_reports' => true,
            'view_ft1_calendar' => true
        ));
        
        // FT1 Evaluator - Evaluate projects and validate documents
        add_role('ft1_evaluator', __('FT1 Avaliador', 'ft1-cultural'), array(
            'read' => true,
            'view_ft1_proponentes' => true,
            'view_ft1_projetos' => true,
            'evaluate_ft1_projetos' => true,
            'view_ft1_contratos' => true,
            'validate_ft1_documents' => true,
            'view_ft1_dashboard' => true,
            'view_ft1_calendar' => true
        ));
        
        // FT1 Operator - Basic operations, view and edit assigned items
        add_role('ft1_operator', __('FT1 Operador', 'ft1-cultural'), array(
            'read' => true,
            'view_ft1_proponentes' => true,
            'edit_ft1_proponentes' => true,
            'view_ft1_projetos' => true,
            'edit_ft1_projetos' => true,
            'view_ft1_contratos' => true,
            'upload_ft1_documents' => true,
            'view_ft1_dashboard' => true,
            'view_ft1_calendar' => true
        ));
        
        // FT1 Proponente - Limited access to own data only
        add_role('ft1_proponente', __('FT1 Proponente', 'ft1-cultural'), array(
            'read' => true,
            'edit_own_ft1_profile' => true,
            'create_own_ft1_projetos' => true,
            'edit_own_ft1_projetos' => true,
            'view_own_ft1_projetos' => true,
            'submit_own_ft1_projetos' => true,
            'view_own_ft1_contratos' => true,
            'sign_own_ft1_contratos' => true,
            'upload_own_ft1_documents' => true,
            'view_own_ft1_dashboard' => true
        ));
        
        // Add capabilities to existing WordPress roles
        $administrator = get_role('administrator');
        if ($administrator) {
            $admin_caps = array(
                'manage_ft1_cultural', 'manage_ft1_settings', 'manage_ft1_editais',
                'create_ft1_editais', 'edit_ft1_editais', 'delete_ft1_editais', 'publish_ft1_editais',
                'manage_ft1_proponentes', 'create_ft1_proponentes', 'edit_ft1_proponentes', 'delete_ft1_proponentes', 'view_ft1_proponentes',
                'manage_ft1_projetos', 'create_ft1_projetos', 'edit_ft1_projetos', 'delete_ft1_projetos', 'evaluate_ft1_projetos', 'approve_ft1_projetos', 'view_ft1_projetos',
                'manage_ft1_contratos', 'create_ft1_contratos', 'edit_ft1_contratos', 'delete_ft1_contratos', 'send_ft1_contratos', 'view_ft1_contratos',
                'manage_ft1_documents', 'upload_ft1_documents', 'validate_ft1_documents', 'delete_ft1_documents',
                'view_ft1_dashboard', 'view_ft1_reports', 'view_ft1_calendar', 'manage_ft1_notifications'
            );
            
            foreach ($admin_caps as $cap) {
                $administrator->add_cap($cap);
            }
        }
        
        $editor = get_role('editor');
        if ($editor) {
            $editor_caps = array(
                'view_ft1_proponentes', 'edit_ft1_proponentes',
                'view_ft1_projetos', 'edit_ft1_projetos', 'evaluate_ft1_projetos',
                'view_ft1_contratos', 'upload_ft1_documents', 'validate_ft1_documents',
                'view_ft1_dashboard', 'view_ft1_calendar'
            );
            
            foreach ($editor_caps as $cap) {
                $editor->add_cap($cap);
            }
        }
    }
    
    /**
     * Remove custom roles
     */
    public static function remove_roles() {
        $custom_roles = array('ft1_administrator', 'ft1_manager', 'ft1_evaluator', 'ft1_operator', 'ft1_proponente');
        
        foreach ($custom_roles as $role) {
            remove_role($role);
        }
        
        // Remove capabilities from WordPress default roles
        $roles_to_clean = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
        
        foreach ($roles_to_clean as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $ft1_caps = array(
                    'manage_ft1_cultural', 'manage_ft1_settings', 'manage_ft1_editais',
                    'create_ft1_editais', 'edit_ft1_editais', 'delete_ft1_editais', 'publish_ft1_editais',
                    'manage_ft1_proponentes', 'create_ft1_proponentes', 'edit_ft1_proponentes', 'delete_ft1_proponentes', 'view_ft1_proponentes',
                    'manage_ft1_projetos', 'create_ft1_projetos', 'edit_ft1_projetos', 'delete_ft1_projetos', 'evaluate_ft1_projetos', 'approve_ft1_projetos', 'view_ft1_projetos',
                    'manage_ft1_contratos', 'create_ft1_contratos', 'edit_ft1_contratos', 'delete_ft1_contratos', 'send_ft1_contratos', 'view_ft1_contratos',
                    'manage_ft1_documents', 'upload_ft1_documents', 'validate_ft1_documents', 'delete_ft1_documents',
                    'view_ft1_dashboard', 'view_ft1_reports', 'view_ft1_calendar', 'manage_ft1_notifications',
                    'edit_own_ft1_profile', 'create_own_ft1_projetos', 'edit_own_ft1_projetos', 'view_own_ft1_projetos',
                    'submit_own_ft1_projetos', 'view_own_ft1_contratos', 'sign_own_ft1_contratos',
                    'upload_own_ft1_documents', 'view_own_ft1_dashboard'
                );
                
                foreach ($ft1_caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        
        delete_option('ft1_cultural_roles_created');
    }
    
    /**
     * Update role capabilities
     */
    private function update_role_capabilities() {
        // This method can be used to update capabilities without recreating roles
        // Useful for plugin updates that add new capabilities
        
        $version = get_option('ft1_cultural_capabilities_version', '1.0.0');
        
        if (version_compare($version, FT1_CULTURAL_VERSION, '<')) {
            // Add new capabilities introduced in newer versions
            $this->add_new_capabilities();
            update_option('ft1_cultural_capabilities_version', FT1_CULTURAL_VERSION);
        }
    }
    
    /**
     * Add new capabilities for plugin updates
     */
    private function add_new_capabilities() {
        // Example: Add new capabilities introduced in version updates
        $new_caps = array(
            // Add new capabilities here as the plugin evolves
        );
        
        if (!empty($new_caps)) {
            $roles_with_new_caps = array(
                'ft1_administrator' => $new_caps,
                'ft1_manager' => array_slice($new_caps, 0, -1), // All except the last one
                'administrator' => $new_caps
            );
            
            foreach ($roles_with_new_caps as $role_name => $caps) {
                $role = get_role($role_name);
                if ($role) {
                    foreach ($caps as $cap) {
                        $role->add_cap($cap);
                    }
                }
            }
        }
    }
    
    /**
     * Assign default role to new users
     */
    public function assign_default_role($user_id) {
        $user = get_user_by('id', $user_id);
        
        // If user doesn't have any FT1 Cultural role, assign proponente role
        if ($user && !$this->user_has_ft1_role($user)) {
            $user->add_role('ft1_proponente');
        }
    }
    
    /**
     * Check if user has any FT1 Cultural role
     */
    private function user_has_ft1_role($user) {
        $ft1_roles = array('ft1_administrator', 'ft1_manager', 'ft1_evaluator', 'ft1_operator', 'ft1_proponente');
        
        foreach ($ft1_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Filter user capabilities for context-specific permissions
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        // Handle "own" capabilities - users can only access their own data
        if (isset($args[0])) {
            $capability = $args[0];
            $object_id = isset($args[2]) ? $args[2] : 0;
            
            // Handle own profile editing
            if ($capability === 'edit_own_ft1_profile') {
                if ($object_id && $object_id == $user->ID) {
                    $allcaps['edit_own_ft1_profile'] = true;
                }
            }
            
            // Handle own project management
            if (in_array($capability, array('create_own_ft1_projetos', 'edit_own_ft1_projetos', 'view_own_ft1_projetos', 'submit_own_ft1_projetos'))) {
                if ($this->user_owns_project($user->ID, $object_id)) {
                    $allcaps[$capability] = true;
                }
            }
            
            // Handle own contract access
            if (in_array($capability, array('view_own_ft1_contratos', 'sign_own_ft1_contratos'))) {
                if ($this->user_owns_contract($user->ID, $object_id)) {
                    $allcaps[$capability] = true;
                }
            }
            
            // Handle own document uploads
            if ($capability === 'upload_own_ft1_documents') {
                if ($this->user_can_upload_to_object($user->ID, $object_id)) {
                    $allcaps[$capability] = true;
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Check if user owns a project
     */
    private function user_owns_project($user_id, $project_id) {
        if (!$project_id) {
            return true; // Allow creation
        }
        
        global $wpdb;
        
        $proponente = $wpdb->get_var($wpdb->prepare(
            "SELECT pr.user_id FROM " . FT1_Cultural_Database::get_table_name('projetos') . " p
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('proponentes') . " pr ON p.proponente_id = pr.id
            WHERE p.id = %d",
            $project_id
        ));
        
        return $proponente == $user_id;
    }
    
    /**
     * Check if user owns a contract
     */
    private function user_owns_contract($user_id, $contract_id) {
        if (!$contract_id) {
            return false;
        }
        
        global $wpdb;
        
        $proponente = $wpdb->get_var($wpdb->prepare(
            "SELECT pr.user_id FROM " . FT1_Cultural_Database::get_table_name('contratos') . " c
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('projetos') . " p ON c.projeto_id = p.id
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('proponentes') . " pr ON p.proponente_id = pr.id
            WHERE c.id = %d",
            $contract_id
        ));
        
        return $proponente == $user_id;
    }
    
    /**
     * Check if user can upload documents to an object
     */
    private function user_can_upload_to_object($user_id, $object_id) {
        // This would need more context about the object type
        // For now, allow if user has upload capability
        return true;
    }
    
    /**
     * Get user's FT1 Cultural role
     */
    public static function get_user_ft1_role($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        $ft1_roles = array('ft1_administrator', 'ft1_manager', 'ft1_evaluator', 'ft1_operator', 'ft1_proponente');
        
        foreach ($ft1_roles as $role) {
            if (in_array($role, $user->roles)) {
                return $role;
            }
        }
        
        return false;
    }
    
    /**
     * Get role display name
     */
    public static function get_role_display_name($role) {
        $role_names = array(
            'ft1_administrator' => __('FT1 Administrador', 'ft1-cultural'),
            'ft1_manager' => __('FT1 Gestor', 'ft1-cultural'),
            'ft1_evaluator' => __('FT1 Avaliador', 'ft1-cultural'),
            'ft1_operator' => __('FT1 Operador', 'ft1-cultural'),
            'ft1_proponente' => __('FT1 Proponente', 'ft1-cultural')
        );
        
        return isset($role_names[$role]) ? $role_names[$role] : $role;
    }
    
    /**
     * Get all FT1 Cultural capabilities
     */
    public static function get_all_capabilities() {
        return array(
            'manage_ft1_cultural' => __('Gerenciar FT1 Cultural', 'ft1-cultural'),
            'manage_ft1_settings' => __('Gerenciar Configurações', 'ft1-cultural'),
            
            // Editais
            'manage_ft1_editais' => __('Gerenciar Editais', 'ft1-cultural'),
            'create_ft1_editais' => __('Criar Editais', 'ft1-cultural'),
            'edit_ft1_editais' => __('Editar Editais', 'ft1-cultural'),
            'delete_ft1_editais' => __('Excluir Editais', 'ft1-cultural'),
            'publish_ft1_editais' => __('Publicar Editais', 'ft1-cultural'),
            
            // Proponentes
            'manage_ft1_proponentes' => __('Gerenciar Proponentes', 'ft1-cultural'),
            'create_ft1_proponentes' => __('Criar Proponentes', 'ft1-cultural'),
            'edit_ft1_proponentes' => __('Editar Proponentes', 'ft1-cultural'),
            'delete_ft1_proponentes' => __('Excluir Proponentes', 'ft1-cultural'),
            'view_ft1_proponentes' => __('Visualizar Proponentes', 'ft1-cultural'),
            
            // Projetos
            'manage_ft1_projetos' => __('Gerenciar Projetos', 'ft1-cultural'),
            'create_ft1_projetos' => __('Criar Projetos', 'ft1-cultural'),
            'edit_ft1_projetos' => __('Editar Projetos', 'ft1-cultural'),
            'delete_ft1_projetos' => __('Excluir Projetos', 'ft1-cultural'),
            'evaluate_ft1_projetos' => __('Avaliar Projetos', 'ft1-cultural'),
            'approve_ft1_projetos' => __('Aprovar Projetos', 'ft1-cultural'),
            'view_ft1_projetos' => __('Visualizar Projetos', 'ft1-cultural'),
            
            // Contratos
            'manage_ft1_contratos' => __('Gerenciar Contratos', 'ft1-cultural'),
            'create_ft1_contratos' => __('Criar Contratos', 'ft1-cultural'),
            'edit_ft1_contratos' => __('Editar Contratos', 'ft1-cultural'),
            'delete_ft1_contratos' => __('Excluir Contratos', 'ft1-cultural'),
            'send_ft1_contratos' => __('Enviar Contratos', 'ft1-cultural'),
            'view_ft1_contratos' => __('Visualizar Contratos', 'ft1-cultural'),
            
            // Documentos
            'manage_ft1_documents' => __('Gerenciar Documentos', 'ft1-cultural'),
            'upload_ft1_documents' => __('Fazer Upload de Documentos', 'ft1-cultural'),
            'validate_ft1_documents' => __('Validar Documentos', 'ft1-cultural'),
            'delete_ft1_documents' => __('Excluir Documentos', 'ft1-cultural'),
            
            // Dashboard e Relatórios
            'view_ft1_dashboard' => __('Visualizar Dashboard', 'ft1-cultural'),
            'view_ft1_reports' => __('Visualizar Relatórios', 'ft1-cultural'),
            'view_ft1_calendar' => __('Visualizar Calendário', 'ft1-cultural'),
            'manage_ft1_notifications' => __('Gerenciar Notificações', 'ft1-cultural'),
            
            // Capacidades "próprias"
            'edit_own_ft1_profile' => __('Editar Próprio Perfil', 'ft1-cultural'),
            'create_own_ft1_projetos' => __('Criar Próprios Projetos', 'ft1-cultural'),
            'edit_own_ft1_projetos' => __('Editar Próprios Projetos', 'ft1-cultural'),
            'view_own_ft1_projetos' => __('Visualizar Próprios Projetos', 'ft1-cultural'),
            'submit_own_ft1_projetos' => __('Submeter Próprios Projetos', 'ft1-cultural'),
            'view_own_ft1_contratos' => __('Visualizar Próprios Contratos', 'ft1-cultural'),
            'sign_own_ft1_contratos' => __('Assinar Próprios Contratos', 'ft1-cultural'),
            'upload_own_ft1_documents' => __('Upload de Próprios Documentos', 'ft1-cultural'),
            'view_own_ft1_dashboard' => __('Visualizar Próprio Dashboard', 'ft1-cultural')
        );
    }
    
    /**
     * Check if current user can access admin area
     */
    public static function current_user_can_access_admin() {
        return current_user_can('view_ft1_dashboard') || 
               current_user_can('manage_ft1_cultural') ||
               current_user_can('manage_ft1_editais') ||
               current_user_can('manage_ft1_proponentes') ||
               current_user_can('manage_ft1_projetos') ||
               current_user_can('manage_ft1_contratos');
    }
    
    /**
     * Get user's accessible menu items
     */
    public static function get_user_menu_items($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $menu_items = array();
        
        // Dashboard
        if (user_can($user_id, 'view_ft1_dashboard')) {
            $menu_items['dashboard'] = array(
                'title' => __('Dashboard', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural'),
                'icon' => 'dashicons-dashboard'
            );
        }
        
        // Editais
        if (user_can($user_id, 'manage_ft1_editais') || user_can($user_id, 'view_ft1_editais')) {
            $menu_items['editais'] = array(
                'title' => __('Editais', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural-editais'),
                'icon' => 'dashicons-megaphone'
            );
        }
        
        // Proponentes
        if (user_can($user_id, 'manage_ft1_proponentes') || user_can($user_id, 'view_ft1_proponentes')) {
            $menu_items['proponentes'] = array(
                'title' => __('Proponentes', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural-proponentes'),
                'icon' => 'dashicons-groups'
            );
        }
        
        // Projetos
        if (user_can($user_id, 'manage_ft1_projetos') || user_can($user_id, 'view_ft1_projetos') || user_can($user_id, 'view_own_ft1_projetos')) {
            $menu_items['projetos'] = array(
                'title' => __('Projetos', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural-projetos'),
                'icon' => 'dashicons-portfolio'
            );
        }
        
        // Contratos
        if (user_can($user_id, 'manage_ft1_contratos') || user_can($user_id, 'view_ft1_contratos') || user_can($user_id, 'view_own_ft1_contratos')) {
            $menu_items['contratos'] = array(
                'title' => __('Contratos', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural-contratos'),
                'icon' => 'dashicons-media-document'
            );
        }
        
        // Calendário
        if (user_can($user_id, 'view_ft1_calendar')) {
            $menu_items['calendar'] = array(
                'title' => __('Calendário', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural-calendar'),
                'icon' => 'dashicons-calendar-alt'
            );
        }
        
        // Relatórios
        if (user_can($user_id, 'view_ft1_reports')) {
            $menu_items['reports'] = array(
                'title' => __('Relatórios', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural-reports'),
                'icon' => 'dashicons-chart-bar'
            );
        }
        
        // Configurações
        if (user_can($user_id, 'manage_ft1_settings')) {
            $menu_items['settings'] = array(
                'title' => __('Configurações', 'ft1-cultural'),
                'url' => admin_url('admin.php?page=ft1-cultural-settings'),
                'icon' => 'dashicons-admin-settings'
            );
        }
        
        return apply_filters('ft1_cultural_user_menu_items', $menu_items, $user_id);
    }
    
    /**
     * Get role hierarchy for permission inheritance
     */
    public static function get_role_hierarchy() {
        return array(
            'ft1_administrator' => 5,
            'ft1_manager' => 4,
            'ft1_evaluator' => 3,
            'ft1_operator' => 2,
            'ft1_proponente' => 1
        );
    }
    
    /**
     * Check if role A has higher privileges than role B
     */
    public static function role_has_higher_privileges($role_a, $role_b) {
        $hierarchy = self::get_role_hierarchy();
        
        $level_a = isset($hierarchy[$role_a]) ? $hierarchy[$role_a] : 0;
        $level_b = isset($hierarchy[$role_b]) ? $hierarchy[$role_b] : 0;
        
        return $level_a > $level_b;
    }
}

