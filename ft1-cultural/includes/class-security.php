<?php
/**
 * Security management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Security {
    
    private static $instance = null;
    private $failed_attempts = array();
    private $blocked_ips = array();
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_security'));
        add_action('wp_login_failed', array($this, 'handle_login_failure'));
        add_action('wp_login', array($this, 'handle_successful_login'), 10, 2);
        
        // AJAX security
        add_action('wp_ajax_nopriv_ft1_*', array($this, 'check_ajax_security'), 1);
        add_action('wp_ajax_ft1_*', array($this, 'check_ajax_security'), 1);
        
        // File upload security
        add_filter('wp_handle_upload_prefilter', array($this, 'secure_file_upload'));
        add_filter('upload_mimes', array($this, 'restrict_upload_mimes'));
        
        // Content security
        add_filter('wp_kses_allowed_html', array($this, 'customize_allowed_html'), 10, 2);
        
        // Rate limiting
        add_action('wp_ajax_ft1_rate_limit', array($this, 'check_rate_limit'), 1);
        
        // Security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Audit logging
        add_action('ft1_cultural_security_event', array($this, 'log_security_event'), 10, 3);
    }
    
    /**
     * Initialize security measures
     */
    public function init_security() {
        // Load blocked IPs and failed attempts from database
        $this->load_security_data();
        
        // Check if current IP is blocked
        $this->check_ip_block();
        
        // Clean old security data
        $this->cleanup_old_data();
    }
    
    /**
     * Load security data from database
     */
    private function load_security_data() {
        $this->failed_attempts = get_option('ft1_cultural_failed_attempts', array());
        $this->blocked_ips = get_option('ft1_cultural_blocked_ips', array());
    }
    
    /**
     * Save security data to database
     */
    private function save_security_data() {
        update_option('ft1_cultural_failed_attempts', $this->failed_attempts);
        update_option('ft1_cultural_blocked_ips', $this->blocked_ips);
    }
    
    /**
     * Check if current IP is blocked
     */
    private function check_ip_block() {
        $ip = $this->get_client_ip();
        
        if (isset($this->blocked_ips[$ip])) {
            $block_data = $this->blocked_ips[$ip];
            
            // Check if block has expired
            if ($block_data['expires'] > time()) {
                $this->handle_blocked_access($block_data);
            } else {
                // Remove expired block
                unset($this->blocked_ips[$ip]);
                $this->save_security_data();
            }
        }
    }
    
    /**
     * Handle blocked access
     */
    private function handle_blocked_access($block_data) {
        $remaining_time = $block_data['expires'] - time();
        $minutes = ceil($remaining_time / 60);
        
        wp_die(
            sprintf(
                __('Acesso bloqueado por %d minutos devido a múltiplas tentativas de acesso inválidas. Tente novamente mais tarde.', 'ft1-cultural'),
                $minutes
            ),
            __('Acesso Bloqueado', 'ft1-cultural'),
            array('response' => 429)
        );
    }
    
    /**
     * Handle login failure
     */
    public function handle_login_failure($username) {
        $ip = $this->get_client_ip();
        $time = time();
        
        // Initialize IP data if not exists
        if (!isset($this->failed_attempts[$ip])) {
            $this->failed_attempts[$ip] = array(
                'count' => 0,
                'first_attempt' => $time,
                'last_attempt' => $time
            );
        }
        
        // Update attempt data
        $this->failed_attempts[$ip]['count']++;
        $this->failed_attempts[$ip]['last_attempt'] = $time;
        
        // Check if should block IP
        $max_attempts = apply_filters('ft1_cultural_max_login_attempts', 5);
        $time_window = apply_filters('ft1_cultural_login_time_window', 900); // 15 minutes
        
        if ($this->failed_attempts[$ip]['count'] >= $max_attempts) {
            $time_since_first = $time - $this->failed_attempts[$ip]['first_attempt'];
            
            if ($time_since_first <= $time_window) {
                $this->block_ip($ip, 'multiple_login_failures');
            }
        }
        
        $this->save_security_data();
        
        // Log security event
        $this->log_security_event('login_failure', array(
            'ip' => $ip,
            'username' => $username,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'attempts' => $this->failed_attempts[$ip]['count']
        ));
    }
    
    /**
     * Handle successful login
     */
    public function handle_successful_login($user_login, $user) {
        $ip = $this->get_client_ip();
        
        // Clear failed attempts for this IP
        if (isset($this->failed_attempts[$ip])) {
            unset($this->failed_attempts[$ip]);
            $this->save_security_data();
        }
        
        // Log successful login
        $this->log_security_event('login_success', array(
            'ip' => $ip,
            'user_id' => $user->ID,
            'username' => $user_login,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
        
        // Check for suspicious login patterns
        $this->check_suspicious_login($user);
    }
    
    /**
     * Block IP address
     */
    private function block_ip($ip, $reason) {
        $block_duration = apply_filters('ft1_cultural_block_duration', 3600); // 1 hour
        
        $this->blocked_ips[$ip] = array(
            'reason' => $reason,
            'blocked_at' => time(),
            'expires' => time() + $block_duration,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $this->save_security_data();
        
        // Log security event
        $this->log_security_event('ip_blocked', array(
            'ip' => $ip,
            'reason' => $reason,
            'duration' => $block_duration
        ));
        
        // Send notification to administrators
        $this->notify_administrators('ip_blocked', array(
            'ip' => $ip,
            'reason' => $reason
        ));
    }
    
    /**
     * Check for suspicious login patterns
     */
    private function check_suspicious_login($user) {
        $ip = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check for login from new location/device
        $last_login_data = get_user_meta($user->ID, 'ft1_last_login_data', true);
        
        if ($last_login_data) {
            $ip_changed = $last_login_data['ip'] !== $ip;
            $ua_changed = $last_login_data['user_agent'] !== $user_agent;
            
            if ($ip_changed || $ua_changed) {
                $this->log_security_event('suspicious_login', array(
                    'user_id' => $user->ID,
                    'new_ip' => $ip,
                    'old_ip' => $last_login_data['ip'],
                    'new_user_agent' => $user_agent,
                    'old_user_agent' => $last_login_data['user_agent']
                ));
                
                // Optionally notify user about new login
                $this->notify_user_new_login($user, $ip);
            }
        }
        
        // Update last login data
        update_user_meta($user->ID, 'ft1_last_login_data', array(
            'ip' => $ip,
            'user_agent' => $user_agent,
            'timestamp' => time()
        ));
    }
    
    /**
     * Check AJAX security
     */
    public function check_ajax_security() {
        // Only check FT1 Cultural AJAX requests
        $action = $_REQUEST['action'] ?? '';
        
        if (strpos($action, 'ft1_') !== 0) {
            return;
        }
        
        // Rate limiting
        if (!$this->check_rate_limit()) {
            wp_die(__('Muitas solicitações. Tente novamente mais tarde.', 'ft1-cultural'), '', array('response' => 429));
        }
        
        // Nonce verification
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'ft1_cultural_nonce')) {
            wp_die(__('Verificação de segurança falhou.', 'ft1-cultural'), '', array('response' => 403));
        }
        
        // Capability check for admin actions
        if (is_admin() && !current_user_can('view_ft1_dashboard')) {
            wp_die(__('Permissões insuficientes.', 'ft1-cultural'), '', array('response' => 403));
        }
        
        // Log AJAX request
        $this->log_security_event('ajax_request', array(
            'action' => $action,
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
    }
    
    /**
     * Check rate limiting
     */
    public function check_rate_limit() {
        $ip = $this->get_client_ip();
        $current_time = time();
        $rate_limit_window = 60; // 1 minute
        $max_requests = apply_filters('ft1_cultural_rate_limit', 60); // 60 requests per minute
        
        $rate_limit_data = get_transient('ft1_rate_limit_' . md5($ip));
        
        if (!$rate_limit_data) {
            $rate_limit_data = array(
                'count' => 1,
                'start_time' => $current_time
            );
        } else {
            $rate_limit_data['count']++;
        }
        
        // Check if rate limit exceeded
        if ($rate_limit_data['count'] > $max_requests) {
            $this->log_security_event('rate_limit_exceeded', array(
                'ip' => $ip,
                'requests' => $rate_limit_data['count'],
                'time_window' => $rate_limit_window
            ));
            
            return false;
        }
        
        // Update rate limit data
        set_transient('ft1_rate_limit_' . md5($ip), $rate_limit_data, $rate_limit_window);
        
        return true;
    }
    
    /**
     * Secure file upload
     */
    public function secure_file_upload($file) {
        // Only process FT1 Cultural uploads
        if (!isset($_POST['ft1_cultural_upload'])) {
            return $file;
        }
        
        // Check file size
        $max_size = apply_filters('ft1_cultural_max_file_size', 10 * 1024 * 1024); // 10MB
        if ($file['size'] > $max_size) {
            $file['error'] = __('Arquivo muito grande.', 'ft1-cultural');
            return $file;
        }
        
        // Check file type
        $allowed_types = apply_filters('ft1_cultural_allowed_file_types', array(
            'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'
        ));
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            $file['error'] = __('Tipo de arquivo não permitido.', 'ft1-cultural');
            return $file;
        }
        
        // Scan file content for malicious code
        if ($this->scan_file_content($file['tmp_name'])) {
            $file['error'] = __('Arquivo contém conteúdo suspeito.', 'ft1-cultural');
            
            $this->log_security_event('malicious_file_upload', array(
                'filename' => $file['name'],
                'ip' => $this->get_client_ip(),
                'user_id' => get_current_user_id()
            ));
            
            return $file;
        }
        
        // Rename file to prevent conflicts and hide original name
        $file['name'] = $this->generate_secure_filename($file['name']);
        
        return $file;
    }
    
    /**
     * Scan file content for malicious code
     */
    private function scan_file_content($file_path) {
        $content = file_get_contents($file_path, false, null, 0, 8192); // Read first 8KB
        
        // Malicious patterns to check
        $malicious_patterns = array(
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/shell_exec/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru/i',
            '/file_get_contents/i',
            '/file_put_contents/i',
            '/fopen/i',
            '/fwrite/i',
            '/curl_exec/i'
        );
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate secure filename
     */
    private function generate_secure_filename($original_name) {
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $timestamp = time();
        $random = wp_generate_password(8, false);
        
        return "ft1_file_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Restrict upload MIME types
     */
    public function restrict_upload_mimes($mimes) {
        // Only apply to FT1 Cultural uploads
        if (!isset($_POST['ft1_cultural_upload'])) {
            return $mimes;
        }
        
        $allowed_mimes = array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png'
        );
        
        return $allowed_mimes;
    }
    
    /**
     * Customize allowed HTML tags
     */
    public function customize_allowed_html($allowed, $context) {
        if ($context === 'ft1_cultural') {
            // Allow specific HTML tags for FT1 Cultural content
            $allowed = array(
                'p' => array(),
                'br' => array(),
                'strong' => array(),
                'em' => array(),
                'u' => array(),
                'h1' => array(),
                'h2' => array(),
                'h3' => array(),
                'h4' => array(),
                'h5' => array(),
                'h6' => array(),
                'ul' => array(),
                'ol' => array(),
                'li' => array(),
                'a' => array(
                    'href' => array(),
                    'title' => array(),
                    'target' => array()
                ),
                'img' => array(
                    'src' => array(),
                    'alt' => array(),
                    'width' => array(),
                    'height' => array()
                ),
                'table' => array(),
                'tr' => array(),
                'td' => array(),
                'th' => array(),
                'thead' => array(),
                'tbody' => array(),
                'div' => array(
                    'class' => array()
                ),
                'span' => array(
                    'class' => array()
                )
            );
        }
        
        return $allowed;
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add headers for FT1 Cultural pages
        if (!$this->is_ft1_cultural_page()) {
            return;
        }
        
        // Content Security Policy
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com; img-src \'self\' data: https:; connect-src \'self\';');
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Check if current page is FT1 Cultural page
     */
    private function is_ft1_cultural_page() {
        global $pagenow;
        
        if (is_admin() && $pagenow === 'admin.php') {
            $page = $_GET['page'] ?? '';
            return strpos($page, 'ft1-cultural') === 0;
        }
        
        return false;
    }
    
    /**
     * Log security event
     */
    public function log_security_event($event_type, $data = array(), $severity = 'info') {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('logs');
        
        $log_data = array(
            'user_id' => get_current_user_id(),
            'acao' => 'security_event',
            'objeto_tipo' => 'security',
            'objeto_id' => 0,
            'dados_anteriores' => null,
            'dados_novos' => wp_json_encode(array(
                'event_type' => $event_type,
                'severity' => $severity,
                'data' => $data,
                'timestamp' => time()
            )),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $wpdb->insert($table, $log_data, array(
            '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
        ));
        
        // Send critical events to administrators
        if (in_array($severity, array('critical', 'high'))) {
            $this->notify_administrators('security_alert', array(
                'event_type' => $event_type,
                'data' => $data
            ));
        }
    }
    
    /**
     * Notify administrators about security events
     */
    private function notify_administrators($event_type, $data) {
        $administrators = get_users(array(
            'role' => 'administrator',
            'meta_key' => 'ft1_security_notifications',
            'meta_value' => '1'
        ));
        
        if (empty($administrators)) {
            // Fallback to all administrators
            $administrators = get_users(array('role' => 'administrator'));
        }
        
        $subject = sprintf(__('[FT1 Cultural] Alerta de Segurança: %s', 'ft1-cultural'), $event_type);
        
        $message = sprintf(
            __('Um evento de segurança foi detectado no sistema FT1 Cultural:

Tipo: %s
Data/Hora: %s
IP: %s
Detalhes: %s

Por favor, verifique o sistema e tome as medidas necessárias.

---
Sistema FT1 Cultural
%s', 'ft1-cultural'),
            $event_type,
            current_time('mysql'),
            $this->get_client_ip(),
            wp_json_encode($data, JSON_PRETTY_PRINT),
            home_url()
        );
        
        foreach ($administrators as $admin) {
            wp_mail($admin->user_email, $subject, $message);
        }
    }
    
    /**
     * Notify user about new login
     */
    private function notify_user_new_login($user, $ip) {
        $user_preference = get_user_meta($user->ID, 'ft1_login_notifications', true);
        
        if ($user_preference !== '1') {
            return; // User disabled notifications
        }
        
        $subject = __('[FT1 Cultural] Novo acesso detectado', 'ft1-cultural');
        
        $message = sprintf(
            __('Olá %s,

Detectamos um novo acesso à sua conta no sistema FT1 Cultural:

Data/Hora: %s
IP: %s
Navegador: %s

Se este acesso foi realizado por você, pode ignorar este email.
Caso contrário, recomendamos que altere sua senha imediatamente.

---
Sistema FT1 Cultural
%s', 'ft1-cultural'),
            $user->display_name,
            current_time('mysql'),
            $ip,
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido',
            home_url()
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean old security data
     */
    private function cleanup_old_data() {
        $current_time = time();
        $cleanup_age = 30 * DAY_IN_SECONDS; // 30 days
        
        // Clean old failed attempts
        foreach ($this->failed_attempts as $ip => $data) {
            if (($current_time - $data['last_attempt']) > $cleanup_age) {
                unset($this->failed_attempts[$ip]);
            }
        }
        
        // Clean expired IP blocks
        foreach ($this->blocked_ips as $ip => $data) {
            if ($data['expires'] < $current_time) {
                unset($this->blocked_ips[$ip]);
            }
        }
        
        $this->save_security_data();
        
        // Clean old log entries
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . FT1_Cultural_Database::get_table_name('logs') . " 
            WHERE created_at < %s AND objeto_tipo = 'security'",
            date('Y-m-d H:i:s', $current_time - $cleanup_age)
        ));
    }
    
    /**
     * Validate and sanitize input data
     */
    public static function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
                
            case 'url':
                return esc_url_raw($data);
                
            case 'int':
                return intval($data);
                
            case 'float':
                return floatval($data);
                
            case 'html':
                return wp_kses($data, wp_kses_allowed_html('ft1_cultural'));
                
            case 'textarea':
                return sanitize_textarea_field($data);
                
            case 'filename':
                return sanitize_file_name($data);
                
            case 'key':
                return sanitize_key($data);
                
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate_csrf_token($token, $action = 'ft1_cultural_nonce') {
        return wp_verify_nonce($token, $action);
    }
    
    /**
     * Generate secure random token
     */
    public static function generate_secure_token($length = 32) {
        return wp_generate_password($length, false);
    }
    
    /**
     * Hash sensitive data
     */
    public static function hash_data($data, $salt = '') {
        if (empty($salt)) {
            $salt = wp_salt('auth');
        }
        
        return hash_hmac('sha256', $data, $salt);
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt_data($data, $key = '') {
        if (empty($key)) {
            $key = wp_salt('secure_auth');
        }
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt_data($encrypted_data, $key = '') {
        if (empty($key)) {
            $key = wp_salt('secure_auth');
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Check if user has permission for specific action
     */
    public static function check_permission($action, $object_id = 0, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check basic capability
        if (!user_can($user_id, $action)) {
            return false;
        }
        
        // Check object-specific permissions
        if ($object_id > 0) {
            return apply_filters('ft1_cultural_check_object_permission', true, $action, $object_id, $user_id);
        }
        
        return true;
    }
    
    /**
     * Get security report
     */
    public function get_security_report($days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT dados_novos, created_at FROM " . FT1_Cultural_Database::get_table_name('logs') . "
            WHERE acao = 'security_event' AND created_at >= %s
            ORDER BY created_at DESC",
            $start_date
        ));
        
        $report = array(
            'total_events' => count($events),
            'events_by_type' => array(),
            'events_by_severity' => array(),
            'blocked_ips' => count($this->blocked_ips),
            'failed_attempts' => array_sum(array_column($this->failed_attempts, 'count')),
            'recent_events' => array()
        );
        
        foreach ($events as $event) {
            $data = json_decode($event->dados_novos, true);
            
            if (isset($data['event_type'])) {
                $report['events_by_type'][$data['event_type']] = 
                    ($report['events_by_type'][$data['event_type']] ?? 0) + 1;
            }
            
            if (isset($data['severity'])) {
                $report['events_by_severity'][$data['severity']] = 
                    ($report['events_by_severity'][$data['severity']] ?? 0) + 1;
            }
            
            if (count($report['recent_events']) < 10) {
                $report['recent_events'][] = array(
                    'type' => $data['event_type'] ?? 'unknown',
                    'severity' => $data['severity'] ?? 'info',
                    'timestamp' => $event->created_at,
                    'data' => $data['data'] ?? array()
                );
            }
        }
        
        return $report;
    }
}

