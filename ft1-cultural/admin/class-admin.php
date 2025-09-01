<?php
/**
 * Admin interface class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Admin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Admin bar
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('FT1 Cultural', 'ft1-cultural'),
            __('FT1 Cultural', 'ft1-cultural'),
            'manage_ft1_cultural',
            'ft1-cultural',
            array($this, 'dashboard_page'),
            'dashicons-awards',
            30
        );
        
        // Dashboard
        add_submenu_page(
            'ft1-cultural',
            __('Dashboard', 'ft1-cultural'),
            __('Dashboard', 'ft1-cultural'),
            'manage_ft1_cultural',
            'ft1-cultural',
            array($this, 'dashboard_page')
        );
        
        // Editais
        add_submenu_page(
            'ft1-cultural',
            __('Editais', 'ft1-cultural'),
            __('Editais', 'ft1-cultural'),
            'manage_ft1_editais',
            'ft1-cultural-editais',
            array($this, 'editais_page')
        );
        
        // Proponentes
        add_submenu_page(
            'ft1-cultural',
            __('Proponentes', 'ft1-cultural'),
            __('Proponentes', 'ft1-cultural'),
            'manage_ft1_proponentes',
            'ft1-cultural-proponentes',
            array($this, 'proponentes_page')
        );
        
        // Projetos
        add_submenu_page(
            'ft1-cultural',
            __('Projetos', 'ft1-cultural'),
            __('Projetos', 'ft1-cultural'),
            'manage_ft1_projetos',
            'ft1-cultural-projetos',
            array($this, 'projetos_page')
        );
        
        // Contratos
        add_submenu_page(
            'ft1-cultural',
            __('Contratos', 'ft1-cultural'),
            __('Contratos', 'ft1-cultural'),
            'manage_ft1_contratos',
            'ft1-cultural-contratos',
            array($this, 'contratos_page')
        );
        
        // Calendário
        add_submenu_page(
            'ft1-cultural',
            __('Calendário', 'ft1-cultural'),
            __('Calendário', 'ft1-cultural'),
            'view_ft1_calendar',
            'ft1-cultural-calendar',
            array($this, 'calendar_page')
        );
        
        // Relatórios
        add_submenu_page(
            'ft1-cultural',
            __('Relatórios', 'ft1-cultural'),
            __('Relatórios', 'ft1-cultural'),
            'view_ft1_reports',
            'ft1-cultural-reports',
            array($this, 'reports_page')
        );
        
        // Configurações
        add_submenu_page(
            'ft1-cultural',
            __('Configurações', 'ft1-cultural'),
            __('Configurações', 'ft1-cultural'),
            'manage_ft1_settings',
            'ft1-cultural-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on FT1 Cultural pages
        if (strpos($hook, 'ft1-cultural') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'ft1-cultural-admin',
            FT1_CULTURAL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FT1_CULTURAL_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'ft1-cultural-admin',
            FT1_CULTURAL_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            FT1_CULTURAL_VERSION,
            true
        );
        
        // Chart.js for reports
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );
        
        // FullCalendar for calendar view
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
            array(),
            '6.1.8',
            true
        );
        
        // Signature pad for contracts
        wp_enqueue_script(
            'signature-pad',
            'https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js',
            array(),
            '4.1.7',
            true
        );
        
        // Localize script
        wp_localize_script('ft1-cultural-admin', 'ft1Cultural', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ft1_cultural_nonce'),
            'strings' => array(
                'confirm_delete' => __('Tem certeza que deseja excluir este item?', 'ft1-cultural'),
                'loading' => __('Carregando...', 'ft1-cultural'),
                'error' => __('Erro ao processar solicitação.', 'ft1-cultural'),
                'success' => __('Operação realizada com sucesso.', 'ft1-cultural'),
                'required_field' => __('Este campo é obrigatório.', 'ft1-cultural'),
                'invalid_email' => __('Email inválido.', 'ft1-cultural'),
                'invalid_date' => __('Data inválida.', 'ft1-cultural'),
                'file_too_large' => __('Arquivo muito grande.', 'ft1-cultural'),
                'invalid_file_type' => __('Tipo de arquivo não permitido.', 'ft1-cultural')
            )
        ));
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Register settings
        register_setting('ft1_cultural_settings', 'ft1_cultural_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Add settings sections and fields
        $this->add_settings_sections();
    }
    
    /**
     * Add settings sections
     */
    private function add_settings_sections() {
        // General settings
        add_settings_section(
            'ft1_cultural_general',
            __('Configurações Gerais', 'ft1-cultural'),
            array($this, 'general_settings_callback'),
            'ft1_cultural_settings'
        );
        
        add_settings_field(
            'email_notifications',
            __('Notificações por Email', 'ft1-cultural'),
            array($this, 'checkbox_field_callback'),
            'ft1_cultural_settings',
            'ft1_cultural_general',
            array('field' => 'email_notifications', 'description' => __('Enviar notificações por email', 'ft1-cultural'))
        );
        
        add_settings_field(
            'whatsapp_notifications',
            __('Notificações por WhatsApp', 'ft1-cultural'),
            array($this, 'checkbox_field_callback'),
            'ft1_cultural_settings',
            'ft1_cultural_general',
            array('field' => 'whatsapp_notifications', 'description' => __('Enviar notificações por WhatsApp', 'ft1-cultural'))
        );
        
        // File upload settings
        add_settings_section(
            'ft1_cultural_uploads',
            __('Configurações de Upload', 'ft1-cultural'),
            array($this, 'uploads_settings_callback'),
            'ft1_cultural_settings'
        );
        
        add_settings_field(
            'max_file_size',
            __('Tamanho Máximo de Arquivo (MB)', 'ft1-cultural'),
            array($this, 'number_field_callback'),
            'ft1_cultural_settings',
            'ft1_cultural_uploads',
            array('field' => 'max_file_size', 'min' => 1, 'max' => 100)
        );
        
        add_settings_field(
            'allowed_file_types',
            __('Tipos de Arquivo Permitidos', 'ft1-cultural'),
            array($this, 'multiselect_field_callback'),
            'ft1_cultural_settings',
            'ft1_cultural_uploads',
            array(
                'field' => 'allowed_file_types',
                'options' => array(
                    'pdf' => 'PDF',
                    'doc' => 'DOC',
                    'docx' => 'DOCX',
                    'jpg' => 'JPG',
                    'jpeg' => 'JPEG',
                    'png' => 'PNG'
                )
            )
        );
        
        // Contract settings
        add_settings_section(
            'ft1_cultural_contracts',
            __('Configurações de Contratos', 'ft1-cultural'),
            array($this, 'contracts_settings_callback'),
            'ft1_cultural_settings'
        );
        
        add_settings_field(
            'contract_validity_days',
            __('Validade do Link de Assinatura (dias)', 'ft1-cultural'),
            array($this, 'number_field_callback'),
            'ft1_cultural_settings',
            'ft1_cultural_contracts',
            array('field' => 'contract_validity_days', 'min' => 1, 'max' => 365)
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $stats = $this->get_dashboard_stats();
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Editais page
     */
    public function editais_page() {
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/editais.php';
    }
    
    /**
     * Proponentes page
     */
    public function proponentes_page() {
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/proponentes.php';
    }
    
    /**
     * Projetos page
     */
    public function projetos_page() {
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/projetos.php';
    }
    
    /**
     * Contratos page
     */
    public function contratos_page() {
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/contratos.php';
    }
    
    /**
     * Calendar page
     */
    public function calendar_page() {
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/calendar.php';
    }
    
    /**
     * Reports page
     */
    public function reports_page() {
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/reports.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include FT1_CULTURAL_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Editais
        $stats['editais'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('editais')),
            'ativos' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('editais') . " WHERE status IN ('publicado', 'em_andamento')"),
            'finalizados' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('editais') . " WHERE status = 'finalizado'")
        );
        
        // Proponentes
        $stats['proponentes'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('proponentes')),
            'ativos' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('proponentes') . " WHERE status = 'ativo'"),
            'novos_mes' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('proponentes') . " WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")
        );
        
        // Projetos
        $stats['projetos'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('projetos')),
            'aprovados' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('projetos') . " WHERE status = 'aprovado'"),
            'em_analise' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('projetos') . " WHERE status IN ('enviado', 'em_analise')"),
            'valor_total' => $wpdb->get_var("SELECT SUM(valor_aprovado) FROM " . FT1_Cultural_Database::get_table_name('projetos') . " WHERE status = 'aprovado'")
        );
        
        // Contratos
        $stats['contratos'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('contratos')),
            'assinados' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('contratos') . " WHERE status = 'assinado'"),
            'pendentes' => $wpdb->get_var("SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('contratos') . " WHERE status = 'enviado'")
        );
        
        // Recent activities
        $stats['recent_activities'] = $wpdb->get_results("
            SELECT l.*, u.display_name as user_name 
            FROM " . FT1_Cultural_Database::get_table_name('logs') . " l
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
            ORDER BY l.created_at DESC 
            LIMIT 10
        ");
        
        return $stats;
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        if (current_user_can('view_ft1_dashboard')) {
            wp_add_dashboard_widget(
                'ft1_cultural_overview',
                __('FT1 Cultural - Visão Geral', 'ft1-cultural'),
                array($this, 'dashboard_widget_overview')
            );
            
            wp_add_dashboard_widget(
                'ft1_cultural_recent',
                __('FT1 Cultural - Atividades Recentes', 'ft1-cultural'),
                array($this, 'dashboard_widget_recent')
            );
        }
    }
    
    /**
     * Dashboard widget overview
     */
    public function dashboard_widget_overview() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="ft1-dashboard-widget">
            <div class="ft1-stats-grid">
                <div class="ft1-stat-item">
                    <div class="ft1-stat-number"><?php echo $stats['editais']['ativos']; ?></div>
                    <div class="ft1-stat-label"><?php _e('Editais Ativos', 'ft1-cultural'); ?></div>
                </div>
                <div class="ft1-stat-item">
                    <div class="ft1-stat-number"><?php echo $stats['projetos']['em_analise']; ?></div>
                    <div class="ft1-stat-label"><?php _e('Projetos em Análise', 'ft1-cultural'); ?></div>
                </div>
                <div class="ft1-stat-item">
                    <div class="ft1-stat-number"><?php echo $stats['contratos']['pendentes']; ?></div>
                    <div class="ft1-stat-label"><?php _e('Contratos Pendentes', 'ft1-cultural'); ?></div>
                </div>
                <div class="ft1-stat-item">
                    <div class="ft1-stat-number">R$ <?php echo number_format($stats['projetos']['valor_total'], 0, ',', '.'); ?></div>
                    <div class="ft1-stat-label"><?php _e('Valor Total Aprovado', 'ft1-cultural'); ?></div>
                </div>
            </div>
            <div class="ft1-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=ft1-cultural'); ?>" class="button button-primary">
                    <?php _e('Ver Dashboard Completo', 'ft1-cultural'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Dashboard widget recent activities
     */
    public function dashboard_widget_recent() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="ft1-dashboard-widget">
            <ul class="ft1-activity-list">
                <?php foreach ($stats['recent_activities'] as $activity): ?>
                <li class="ft1-activity-item">
                    <div class="ft1-activity-content">
                        <strong><?php echo esc_html($activity->user_name); ?></strong>
                        <?php echo $this->format_activity_message($activity); ?>
                    </div>
                    <div class="ft1-activity-time">
                        <?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ' . __('atrás', 'ft1-cultural'); ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Format activity message
     */
    private function format_activity_message($activity) {
        $messages = array(
            'create' => array(
                'edital' => __('criou um novo edital', 'ft1-cultural'),
                'proponente' => __('cadastrou um novo proponente', 'ft1-cultural'),
                'projeto' => __('criou um novo projeto', 'ft1-cultural'),
                'contrato' => __('gerou um novo contrato', 'ft1-cultural')
            ),
            'update' => array(
                'edital' => __('atualizou um edital', 'ft1-cultural'),
                'proponente' => __('atualizou dados de proponente', 'ft1-cultural'),
                'projeto' => __('atualizou um projeto', 'ft1-cultural'),
                'contrato' => __('atualizou um contrato', 'ft1-cultural')
            ),
            'delete' => array(
                'edital' => __('excluiu um edital', 'ft1-cultural'),
                'proponente' => __('excluiu um proponente', 'ft1-cultural'),
                'projeto' => __('excluiu um projeto', 'ft1-cultural'),
                'contrato' => __('excluiu um contrato', 'ft1-cultural')
            ),
            'submit' => array(
                'projeto' => __('submeteu um projeto para análise', 'ft1-cultural')
            ),
            'evaluate' => array(
                'projeto' => __('avaliou um projeto', 'ft1-cultural')
            ),
            'sign' => array(
                'contrato' => __('assinou um contrato', 'ft1-cultural')
            )
        );
        
        $action = $activity->acao;
        $type = $activity->objeto_tipo;
        
        if (isset($messages[$action][$type])) {
            return $messages[$action][$type];
        }
        
        return sprintf(__('%s %s', 'ft1-cultural'), $action, $type);
    }
    
    /**
     * Add admin bar menu
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('view_ft1_dashboard')) {
            return;
        }
        
        $wp_admin_bar->add_menu(array(
            'id' => 'ft1-cultural',
            'title' => __('FT1 Cultural', 'ft1-cultural'),
            'href' => admin_url('admin.php?page=ft1-cultural'),
            'meta' => array(
                'title' => __('FT1 Cultural Dashboard', 'ft1-cultural')
            )
        ));
        
        // Quick stats
        $stats = $this->get_dashboard_stats();
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'ft1-cultural',
            'id' => 'ft1-cultural-stats',
            'title' => sprintf(__('Projetos em Análise: %d', 'ft1-cultural'), $stats['projetos']['em_analise']),
            'href' => admin_url('admin.php?page=ft1-cultural-projetos&status=em_analise')
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'ft1-cultural',
            'id' => 'ft1-cultural-contracts',
            'title' => sprintf(__('Contratos Pendentes: %d', 'ft1-cultural'), $stats['contratos']['pendentes']),
            'href' => admin_url('admin.php?page=ft1-cultural-contratos&status=enviado')
        ));
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if plugin was just activated
        if (get_option('ft1_cultural_activated')) {
            delete_option('ft1_cultural_activated');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('FT1 Cultural ativado com sucesso!', 'ft1-cultural'); ?></strong>
                    <a href="<?php echo admin_url('admin.php?page=ft1-cultural'); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php _e('Acessar Dashboard', 'ft1-cultural'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Check for pending actions
        $this->check_pending_actions();
    }
    
    /**
     * Check for pending actions
     */
    private function check_pending_actions() {
        global $wpdb;
        
        // Check for projects pending evaluation
        $pending_projects = $wpdb->get_var("
            SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('projetos') . " 
            WHERE status = 'enviado' AND data_submissao < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        if ($pending_projects > 0 && current_user_can('evaluate_ft1_projetos')) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Atenção:', 'ft1-cultural'); ?></strong>
                    <?php printf(__('Existem %d projetos aguardando avaliação há mais de 7 dias.', 'ft1-cultural'), $pending_projects); ?>
                    <a href="<?php echo admin_url('admin.php?page=ft1-cultural-projetos&status=enviado'); ?>">
                        <?php _e('Ver projetos', 'ft1-cultural'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Check for contracts pending signature
        $pending_contracts = $wpdb->get_var("
            SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('contratos') . " 
            WHERE status = 'enviado' AND created_at < DATE_SUB(NOW(), INTERVAL 15 DAY)
        ");
        
        if ($pending_contracts > 0 && current_user_can('manage_ft1_contratos')) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Informação:', 'ft1-cultural'); ?></strong>
                    <?php printf(__('Existem %d contratos aguardando assinatura há mais de 15 dias.', 'ft1-cultural'), $pending_contracts); ?>
                    <a href="<?php echo admin_url('admin.php?page=ft1-cultural-contratos&status=enviado'); ?>">
                        <?php _e('Ver contratos', 'ft1-cultural'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Settings callbacks
     */
    public function general_settings_callback() {
        echo '<p>' . __('Configurações gerais do sistema FT1 Cultural.', 'ft1-cultural') . '</p>';
    }
    
    public function uploads_settings_callback() {
        echo '<p>' . __('Configurações para upload de documentos.', 'ft1-cultural') . '</p>';
    }
    
    public function contracts_settings_callback() {
        echo '<p>' . __('Configurações para contratos e assinatura digital.', 'ft1-cultural') . '</p>';
    }
    
    public function checkbox_field_callback($args) {
        $settings = get_option('ft1_cultural_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : false;
        
        echo '<input type="checkbox" id="' . $args['field'] . '" name="ft1_cultural_settings[' . $args['field'] . ']" value="1" ' . checked(1, $value, false) . ' />';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    public function number_field_callback($args) {
        $settings = get_option('ft1_cultural_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : '';
        
        echo '<input type="number" id="' . $args['field'] . '" name="ft1_cultural_settings[' . $args['field'] . ']" value="' . esc_attr($value) . '"';
        if (isset($args['min'])) echo ' min="' . $args['min'] . '"';
        if (isset($args['max'])) echo ' max="' . $args['max'] . '"';
        echo ' />';
    }
    
    public function multiselect_field_callback($args) {
        $settings = get_option('ft1_cultural_settings', array());
        $values = isset($settings[$args['field']]) ? $settings[$args['field']] : array();
        
        echo '<select multiple id="' . $args['field'] . '" name="ft1_cultural_settings[' . $args['field'] . '][]" style="height: 120px;">';
        foreach ($args['options'] as $key => $label) {
            $selected = in_array($key, $values) ? 'selected' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Boolean fields
        $boolean_fields = array('email_notifications', 'whatsapp_notifications');
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? (bool) $input[$field] : false;
        }
        
        // Numeric fields
        if (isset($input['max_file_size'])) {
            $sanitized['max_file_size'] = max(1, min(100, intval($input['max_file_size'])));
        }
        
        if (isset($input['contract_validity_days'])) {
            $sanitized['contract_validity_days'] = max(1, min(365, intval($input['contract_validity_days'])));
        }
        
        // Array fields
        if (isset($input['allowed_file_types']) && is_array($input['allowed_file_types'])) {
            $allowed_types = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png');
            $sanitized['allowed_file_types'] = array_intersect($input['allowed_file_types'], $allowed_types);
        }
        
        return $sanitized;
    }
}

