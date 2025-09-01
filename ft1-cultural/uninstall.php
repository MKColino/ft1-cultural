<?php
/**
 * Uninstall script for FT1 Cultural plugin
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It removes all plugin data including database tables, options, and user roles.
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check
if (!current_user_can('activate_plugins')) {
    return;
}

// Check if we should preserve data
$preserve_data = get_option('ft1_cultural_preserve_data_on_uninstall', false);

if ($preserve_data) {
    // If preserve data is enabled, only remove plugin options but keep user data
    delete_option('ft1_cultural_preserve_data_on_uninstall');
    delete_option('ft1_cultural_version');
    delete_option('ft1_cultural_activated');
    return;
}

global $wpdb;

// Define table names
$tables = array(
    $wpdb->prefix . 'ft1_editais',
    $wpdb->prefix . 'ft1_proponentes',
    $wpdb->prefix . 'ft1_projetos',
    $wpdb->prefix . 'ft1_contratos',
    $wpdb->prefix . 'ft1_documentos',
    $wpdb->prefix . 'ft1_avaliacoes',
    $wpdb->prefix . 'ft1_calendar_events',
    $wpdb->prefix . 'ft1_notifications',
    $wpdb->prefix . 'ft1_logs'
);

// Drop all plugin tables
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Remove all plugin options
$options_to_delete = array(
    'ft1_cultural_version',
    'ft1_cultural_db_version',
    'ft1_cultural_activated',
    'ft1_cultural_settings',
    'ft1_cultural_notification_settings',
    'ft1_cultural_roles_created',
    'ft1_cultural_capabilities_version',
    'ft1_cultural_failed_attempts',
    'ft1_cultural_blocked_ips',
    'ft1_cultural_preserve_data_on_uninstall'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
    delete_site_option($option); // For multisite
}

// Remove user meta related to plugin
$user_meta_keys = array(
    'ft1_last_login_data',
    'ft1_notification_preferences',
    'ft1_security_notifications',
    'ft1_login_notifications',
    'ft1_whatsapp_number',
    'ft1_phone_number'
);

foreach ($user_meta_keys as $meta_key) {
    delete_metadata('user', 0, $meta_key, '', true);
}

// Remove custom roles and capabilities
$custom_roles = array(
    'ft1_administrator',
    'ft1_manager', 
    'ft1_evaluator',
    'ft1_operator',
    'ft1_proponente'
);

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

// Remove scheduled events
wp_clear_scheduled_hook('ft1_daily_calendar_check');
wp_clear_scheduled_hook('ft1_cleanup_notifications');

// Remove transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ft1_%' OR option_name LIKE '_transient_timeout_ft1_%'");

// Remove rate limiting transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ft1_rate_limit_%'");

// Remove uploaded files (be careful with this)
$upload_dir = wp_upload_dir();
$ft1_upload_dir = $upload_dir['basedir'] . '/ft1-cultural/';

if (is_dir($ft1_upload_dir)) {
    // Only remove if user explicitly confirms
    $remove_files = get_option('ft1_cultural_remove_files_on_uninstall', false);
    
    if ($remove_files) {
        // Recursively remove directory and all files
        function ft1_remove_directory($dir) {
            if (!is_dir($dir)) {
                return false;
            }
            
            $files = array_diff(scandir($dir), array('.', '..'));
            
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    ft1_remove_directory($path);
                } else {
                    unlink($path);
                }
            }
            
            return rmdir($dir);
        }
        
        ft1_remove_directory($ft1_upload_dir);
    }
}

// Log uninstallation (if logging is still available)
if (function_exists('error_log')) {
    error_log('FT1 Cultural plugin uninstalled completely at ' . current_time('mysql'));
}

// Clear any cached data
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Clear object cache
if (function_exists('wp_cache_delete_group')) {
    wp_cache_delete_group('ft1_cultural');
}

// Remove any remaining plugin files from uploads (backup files, logs, etc.)
$backup_files = glob($upload_dir['basedir'] . '/ft1-cultural-backup-*.sql');
if ($backup_files) {
    foreach ($backup_files as $backup_file) {
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
    }
}

// Final cleanup - remove any orphaned data
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'ft1_%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ft1_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ft1_%'");

// Optimize database tables after cleanup
$wpdb->query("OPTIMIZE TABLE {$wpdb->options}");
$wpdb->query("OPTIMIZE TABLE {$wpdb->usermeta}");
$wpdb->query("OPTIMIZE TABLE {$wpdb->postmeta}");

// Send notification to admin about successful uninstallation
$admin_email = get_option('admin_email');
if ($admin_email) {
    $subject = '[' . get_bloginfo('name') . '] FT1 Cultural Plugin Desinstalado';
    $message = 'O plugin FT1 Cultural foi completamente desinstalado do seu site WordPress.

Dados removidos:
- Todas as tabelas do banco de dados
- Configurações e opções
- Papéis e permissões de usuário
- Eventos agendados
- Cache e dados temporários

Se você precisar reinstalar o plugin no futuro, todos os dados precisarão ser configurados novamente.

Data da desinstalação: ' . current_time('d/m/Y H:i:s') . '
Site: ' . home_url() . '

---
Sistema FT1 Cultural
Fabricat1 Soluções de Mercado';

    wp_mail($admin_email, $subject, $message);
}

// Final message for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('FT1 Cultural: Uninstallation completed successfully. All plugin data has been removed.');
}

