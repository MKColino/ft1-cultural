<?php
/**
 * Plugin Name: FT1 Cultural
 * Plugin URI: https://fabricat1.com.br
 * Description: Sistema CRM completo para gestão de editais culturais, proponentes, projetos e contratos digitais com assinatura eletrônica.
 * Version: 1.0.0
 * Author: Fabricat1 Soluções de Mercado
 * Author URI: https://fabricat1.com.br
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ft1-cultural
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * Copyright (c) 2025 Fabricat1 Soluções de Mercado. Todos os direitos reservados.
 * 
 * Este plugin é propriedade intelectual da Fabricat1 Soluções de Mercado.
 * É proibida a reprodução, distribuição ou modificação sem autorização expressa.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FT1_CULTURAL_VERSION', '1.0.0');
define('FT1_CULTURAL_PLUGIN_FILE', __FILE__);
define('FT1_CULTURAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FT1_CULTURAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FT1_CULTURAL_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'FT1_Cultural\\';
    $base_dir = FT1_CULTURAL_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin class
final class FT1_Cultural {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('FT1_Cultural', 'uninstall'));
    }
    
    public function init() {
        // Initialize plugin components
        $this->includes();
        $this->init_components();
        
        do_action('ft1_cultural_loaded');
    }
    
    private function includes() {
        // Core includes
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-database.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-edital.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-proponente.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-projeto.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-contrato.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-upload.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-calendar.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-notifications.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-security.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'includes/class-roles.php';
        
        // Admin includes
        if (is_admin()) {
            require_once FT1_CULTURAL_PLUGIN_DIR . 'admin/class-admin.php';
            require_once FT1_CULTURAL_PLUGIN_DIR . 'admin/class-menu.php';
            require_once FT1_CULTURAL_PLUGIN_DIR . 'admin/class-ajax.php';
        }
        
        // Public includes
        require_once FT1_CULTURAL_PLUGIN_DIR . 'public/class-public.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'public/class-shortcodes.php';
        require_once FT1_CULTURAL_PLUGIN_DIR . 'public/class-dashboard.php';
    }
    
    private function init_components() {
        // Initialize database
        FT1_Cultural_Database::instance();
        
        // Initialize admin
        if (is_admin()) {
            FT1_Cultural_Admin::instance();
        }
        
        // Initialize public
        FT1_Cultural_Public::instance();
        
        // Initialize security
        FT1_Cultural_Security::instance();
        
        // Initialize roles
        FT1_Cultural_Roles::instance();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('ft1-cultural', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function activate() {
        // Create database tables
        FT1_Cultural_Database::create_tables();
        
        // Create roles and capabilities
        FT1_Cultural_Roles::create_roles();
        
        // Create upload directories
        FT1_Cultural_Upload::create_directories();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('ft1_cultural_activated', true);
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear scheduled events
        wp_clear_scheduled_hook('ft1_cultural_daily_tasks');
    }
    
    public static function uninstall() {
        // Remove database tables
        FT1_Cultural_Database::drop_tables();
        
        // Remove roles and capabilities
        FT1_Cultural_Roles::remove_roles();
        
        // Remove options
        delete_option('ft1_cultural_version');
        delete_option('ft1_cultural_settings');
        delete_option('ft1_cultural_activated');
        
        // Remove upload directories (optional)
        // FT1_Cultural_Upload::remove_directories();
    }
    
    private function set_default_options() {
        $default_settings = array(
            'email_notifications' => true,
            'whatsapp_notifications' => false,
            'contract_validity_days' => 30,
            'max_file_size' => 10, // MB
            'allowed_file_types' => array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'),
            'dashboard_theme' => 'default'
        );
        
        add_option('ft1_cultural_settings', $default_settings);
        add_option('ft1_cultural_version', FT1_CULTURAL_VERSION);
    }
    
    public function get_version() {
        return FT1_CULTURAL_VERSION;
    }
}

// Initialize the plugin
function ft1_cultural() {
    return FT1_Cultural::instance();
}

// Start the plugin
ft1_cultural();

