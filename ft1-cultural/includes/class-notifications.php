<?php
/**
 * Notifications management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Notifications {
    
    private static $instance = null;
    private $templates = array();
    private $settings = array();
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_notifications'));
        add_action('wp_ajax_ft1_send_notification', array($this, 'send_manual_notification'));
        add_action('wp_ajax_ft1_mark_notification_read', array($this, 'mark_notification_read'));
        add_action('wp_ajax_ft1_get_notifications', array($this, 'get_user_notifications'));
        
        // Hooks for automatic notifications
        add_action('ft1_edital_published', array($this, 'notify_edital_published'), 10, 2);
        add_action('ft1_projeto_submitted', array($this, 'notify_projeto_submitted'), 10, 2);
        add_action('ft1_projeto_evaluated', array($this, 'notify_projeto_evaluated'), 10, 3);
        add_action('ft1_contrato_sent', array($this, 'notify_contrato_sent'), 10, 2);
        add_action('ft1_contrato_signed', array($this, 'notify_contrato_signed'), 10, 2);
        add_action('ft1_document_uploaded', array($this, 'notify_document_uploaded'), 10, 3);
        
        // Email hooks
        add_filter('wp_mail_from', array($this, 'set_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'set_mail_from_name'));
        
        // WhatsApp integration
        add_action('ft1_send_whatsapp', array($this, 'send_whatsapp_message'), 10, 3);
        
        // Cleanup old notifications
        add_action('ft1_cleanup_notifications', array($this, 'cleanup_old_notifications'));
        add_action('init', array($this, 'schedule_cleanup'));
    }
    
    /**
     * Initialize notifications system
     */
    public function init_notifications() {
        $this->load_settings();
        $this->load_templates();
        $this->create_notification_tables();
    }
    
    /**
     * Load notification settings
     */
    private function load_settings() {
        $this->settings = get_option('ft1_cultural_notification_settings', array(
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'whatsapp_api_url' => '',
            'whatsapp_api_token' => '',
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'email_template' => 'default',
            'auto_notifications' => true,
            'notification_retention_days' => 30
        ));
    }
    
    /**
     * Load notification templates
     */
    private function load_templates() {
        $this->templates = array(
            // Edital notifications
            'edital_published' => array(
                'subject' => __('[FT1 Cultural] Novo edital publicado: {edital_title}', 'ft1-cultural'),
                'email_body' => __('Um novo edital foi publicado no sistema FT1 Cultural.

Título: {edital_title}
Descrição: {edital_description}
Data de Início: {edital_start_date}
Data de Fim: {edital_end_date}
Valor Total: {edital_value}

Para mais informações e submissão de projetos, acesse:
{edital_url}

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('🎯 *Novo Edital Publicado*

*{edital_title}*

📅 Prazo: {edital_start_date} até {edital_end_date}
💰 Valor: {edital_value}

Acesse: {edital_url}', 'ft1-cultural')
            ),
            
            'edital_deadline_warning' => array(
                'subject' => __('[FT1 Cultural] Prazo final se aproxima: {edital_title}', 'ft1-cultural'),
                'email_body' => __('O prazo para submissão de projetos está se aproximando.

Edital: {edital_title}
Prazo Final: {edital_end_date}
Dias Restantes: {days_remaining}

Não perca a oportunidade! Submeta seu projeto antes do prazo final.

Acesse: {edital_url}

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('⏰ *Prazo Final se Aproxima*

*{edital_title}*

🗓️ Prazo: {edital_end_date}
⚠️ Restam apenas {days_remaining} dias!

Submeta seu projeto: {edital_url}', 'ft1-cultural')
            ),
            
            // Projeto notifications
            'projeto_submitted' => array(
                'subject' => __('[FT1 Cultural] Projeto submetido: {projeto_title}', 'ft1-cultural'),
                'email_body' => __('Um novo projeto foi submetido para avaliação.

Projeto: {projeto_title}
Proponente: {proponente_name}
Edital: {edital_title}
Valor Solicitado: {projeto_value}
Data de Submissão: {submission_date}

Para avaliar o projeto, acesse:
{projeto_url}

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('📋 *Novo Projeto Submetido*

*{projeto_title}*

👤 Proponente: {proponente_name}
💰 Valor: {projeto_value}

Avaliar: {projeto_url}', 'ft1-cultural')
            ),
            
            'projeto_approved' => array(
                'subject' => __('[FT1 Cultural] Projeto aprovado: {projeto_title}', 'ft1-cultural'),
                'email_body' => __('Parabéns! Seu projeto foi aprovado.

Projeto: {projeto_title}
Edital: {edital_title}
Valor Aprovado: {approved_value}
Data de Aprovação: {approval_date}

Próximos passos:
1. Aguarde o contrato ser enviado
2. Assine o contrato digitalmente
3. Inicie a execução do projeto

Acompanhe o status em:
{projeto_url}

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('🎉 *Projeto Aprovado!*

*{projeto_title}*

✅ Valor aprovado: {approved_value}
📋 Aguarde o contrato

Acompanhe: {projeto_url}', 'ft1-cultural')
            ),
            
            'projeto_rejected' => array(
                'subject' => __('[FT1 Cultural] Projeto não aprovado: {projeto_title}', 'ft1-cultural'),
                'email_body' => __('Informamos que seu projeto não foi aprovado nesta edição.

Projeto: {projeto_title}
Edital: {edital_title}
Data de Avaliação: {evaluation_date}

Motivo: {rejection_reason}

Recomendações:
{recommendations}

Não desista! Você pode participar de futuros editais.

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('📋 *Resultado da Avaliação*

*{projeto_title}*

❌ Não aprovado nesta edição
💡 Motivo: {rejection_reason}

Continue tentando nos próximos editais!', 'ft1-cultural')
            ),
            
            // Contrato notifications
            'contrato_sent' => array(
                'subject' => __('[FT1 Cultural] Contrato disponível para assinatura: {projeto_title}', 'ft1-cultural'),
                'email_body' => __('Seu contrato está disponível para assinatura digital.

Projeto: {projeto_title}
Valor do Contrato: {contract_value}
Prazo para Assinatura: {signature_deadline}

IMPORTANTE:
- O link de assinatura é válido por {validity_days} dias
- Você pode assinar digitalmente ou desenhar sua rubrica
- Após a assinatura, o contrato terá validade legal

Para assinar o contrato, clique no link abaixo:
{signature_url}

Em caso de dúvidas, entre em contato conosco.

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('📄 *Contrato Disponível*

*{projeto_title}*

💰 Valor: {contract_value}
⏰ Prazo: {signature_deadline}

Assinar: {signature_url}

⚠️ Link válido por {validity_days} dias', 'ft1-cultural')
            ),
            
            'contrato_signed' => array(
                'subject' => __('[FT1 Cultural] Contrato assinado: {projeto_title}', 'ft1-cultural'),
                'email_body' => __('O contrato foi assinado com sucesso.

Projeto: {projeto_title}
Proponente: {proponente_name}
Data de Assinatura: {signature_date}
IP de Assinatura: {signature_ip}

O projeto pode agora iniciar sua execução conforme cronograma estabelecido.

Uma cópia do contrato assinado está disponível em:
{contract_url}

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('✅ *Contrato Assinado*

*{projeto_title}*

📅 Assinado em: {signature_date}
🚀 Projeto pode iniciar!

Contrato: {contract_url}', 'ft1-cultural')
            ),
            
            // Document notifications
            'document_uploaded' => array(
                'subject' => __('[FT1 Cultural] Novo documento enviado: {document_name}', 'ft1-cultural'),
                'email_body' => __('Um novo documento foi enviado no sistema.

Documento: {document_name}
Tipo: {document_type}
Enviado por: {uploader_name}
Relacionado a: {related_object}
Data de Upload: {upload_date}

Para revisar o documento, acesse:
{document_url}

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('📎 *Novo Documento*

*{document_name}*

👤 Enviado por: {uploader_name}
🔗 Relacionado: {related_object}

Revisar: {document_url}', 'ft1-cultural')
            ),
            
            // System notifications
            'user_registered' => array(
                'subject' => __('[FT1 Cultural] Bem-vindo ao sistema!', 'ft1-cultural'),
                'email_body' => __('Bem-vindo ao sistema FT1 Cultural!

Seu cadastro foi realizado com sucesso.

Dados de acesso:
Email: {user_email}
Perfil: {user_role}

Para acessar o sistema, clique no link abaixo:
{login_url}

Em caso de dúvidas, consulte nosso guia do usuário ou entre em contato.

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('👋 *Bem-vindo ao FT1 Cultural!*

Seu cadastro foi realizado com sucesso.

🔐 Acesse: {login_url}
📧 Email: {user_email}

Qualquer dúvida, estamos aqui para ajudar!', 'ft1-cultural')
            ),
            
            // Calendar notifications
            'calendar_reminder' => array(
                'subject' => __('[FT1 Cultural] Lembrete: {event_title}', 'ft1-cultural'),
                'email_body' => __('Lembrete de evento no calendário FT1 Cultural.

Evento: {event_title}
Data: {event_date}
Horário: {event_time}
Local: {event_location}

Descrição:
{event_description}

---
Sistema FT1 Cultural
{site_url}', 'ft1-cultural'),
                'whatsapp_body' => __('🗓️ *Lembrete de Evento*

*{event_title}*

📅 {event_date} às {event_time}
📍 {event_location}', 'ft1-cultural')
            )
        );
        
        $this->templates = apply_filters('ft1_cultural_notification_templates', $this->templates);
    }
    
    /**
     * Create notification tables
     */
    private function create_notification_tables() {
        global $wpdb;
        
        $table_name = FT1_Cultural_Database::get_table_name('notifications');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext,
            status enum('unread','read','archived') DEFAULT 'unread',
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            channels varchar(100) DEFAULT 'email',
            sent_at datetime DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Send notification
     */
    public function send_notification($user_id, $template_key, $data = array(), $channels = array('email'), $priority = 'normal') {
        if (!isset($this->templates[$template_key])) {
            return false;
        }
        
        $template = $this->templates[$template_key];
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Check user notification preferences
        if (!$this->user_wants_notification($user_id, $template_key)) {
            return false;
        }
        
        // Prepare notification data
        $notification_data = $this->prepare_notification_data($user, $data);
        
        // Replace placeholders in template
        $subject = $this->replace_placeholders($template['subject'], $notification_data);
        $email_body = $this->replace_placeholders($template['email_body'], $notification_data);
        $whatsapp_body = isset($template['whatsapp_body']) ? $this->replace_placeholders($template['whatsapp_body'], $notification_data) : '';
        
        // Store notification in database
        $notification_id = $this->store_notification($user_id, $template_key, $subject, $email_body, $data, $channels, $priority);
        
        $sent_channels = array();
        
        // Send via email
        if (in_array('email', $channels) && $this->settings['email_enabled']) {
            if ($this->send_email($user->user_email, $subject, $email_body, $notification_data)) {
                $sent_channels[] = 'email';
            }
        }
        
        // Send via WhatsApp
        if (in_array('whatsapp', $channels) && $this->settings['whatsapp_enabled'] && !empty($whatsapp_body)) {
            $phone = get_user_meta($user_id, 'ft1_whatsapp_number', true);
            if ($phone && $this->send_whatsapp($phone, $whatsapp_body, $notification_data)) {
                $sent_channels[] = 'whatsapp';
            }
        }
        
        // Send via SMS (if configured)
        if (in_array('sms', $channels) && $this->is_sms_enabled()) {
            $phone = get_user_meta($user_id, 'ft1_phone_number', true);
            if ($phone && $this->send_sms($phone, $whatsapp_body, $notification_data)) {
                $sent_channels[] = 'sms';
            }
        }
        
        // Update notification status
        if (!empty($sent_channels)) {
            $this->update_notification_status($notification_id, 'sent', $sent_channels);
            
            do_action('ft1_notification_sent', $notification_id, $user_id, $template_key, $sent_channels);
            
            return $notification_id;
        }
        
        return false;
    }
    
    /**
     * Store notification in database
     */
    private function store_notification($user_id, $type, $title, $message, $data, $channels, $priority) {
        global $wpdb;
        
        $result = $wpdb->insert(
            FT1_Cultural_Database::get_table_name('notifications'),
            array(
                'user_id' => $user_id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => wp_json_encode($data),
                'channels' => implode(',', $channels),
                'priority' => $priority,
                'status' => 'unread'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update notification status
     */
    private function update_notification_status($notification_id, $status, $sent_channels = array()) {
        global $wpdb;
        
        $update_data = array('status' => $status);
        $update_format = array('%s');
        
        if ($status === 'sent') {
            $update_data['sent_at'] = current_time('mysql');
            $update_format[] = '%s';
        } elseif ($status === 'read') {
            $update_data['read_at'] = current_time('mysql');
            $update_format[] = '%s';
        }
        
        return $wpdb->update(
            FT1_Cultural_Database::get_table_name('notifications'),
            $update_data,
            array('id' => $notification_id),
            $update_format,
            array('%d')
        );
    }
    
    /**
     * Send email notification
     */
    private function send_email($to, $subject, $body, $data = array()) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->settings['from_name'] . ' <' . $this->settings['from_email'] . '>'
        );
        
        // Apply email template
        $html_body = $this->apply_email_template($body, $subject, $data);
        
        $sent = wp_mail($to, $subject, $html_body, $headers);
        
        if (!$sent) {
            error_log('FT1 Cultural: Failed to send email to ' . $to);
        }
        
        return $sent;
    }
    
    /**
     * Send WhatsApp notification
     */
    private function send_whatsapp($phone, $message, $data = array()) {
        if (empty($this->settings['whatsapp_api_url']) || empty($this->settings['whatsapp_api_token'])) {
            return false;
        }
        
        // Clean phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present
        if (strlen($phone) === 11 && substr($phone, 0, 1) !== '55') {
            $phone = '55' . $phone;
        }
        
        $api_data = array(
            'phone' => $phone,
            'message' => $message,
            'token' => $this->settings['whatsapp_api_token']
        );
        
        $response = wp_remote_post($this->settings['whatsapp_api_url'], array(
            'body' => wp_json_encode($api_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->settings['whatsapp_api_token']
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('FT1 Cultural: WhatsApp API error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $result = json_decode($response_body, true);
            return isset($result['success']) && $result['success'];
        }
        
        error_log('FT1 Cultural: WhatsApp API failed with code ' . $response_code . ': ' . $response_body);
        return false;
    }
    
    /**
     * Send SMS notification
     */
    private function send_sms($phone, $message, $data = array()) {
        // SMS integration would go here
        // This is a placeholder for future SMS provider integration
        return apply_filters('ft1_cultural_send_sms', false, $phone, $message, $data);
    }
    
    /**
     * Check if SMS is enabled
     */
    private function is_sms_enabled() {
        return apply_filters('ft1_cultural_sms_enabled', false);
    }
    
    /**
     * Prepare notification data
     */
    private function prepare_notification_data($user, $custom_data = array()) {
        $base_data = array(
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'admin_url' => admin_url('admin.php?page=ft1-cultural'),
            'login_url' => wp_login_url(),
            'current_date' => current_time('d/m/Y'),
            'current_time' => current_time('H:i'),
            'current_datetime' => current_time('d/m/Y H:i')
        );
        
        return array_merge($base_data, $custom_data);
    }
    
    /**
     * Replace placeholders in template
     */
    private function replace_placeholders($template, $data) {
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', $value, $template);
            }
        }
        
        return $template;
    }
    
    /**
     * Apply email template
     */
    private function apply_email_template($body, $subject, $data = array()) {
        $template_file = FT1_CULTURAL_PLUGIN_DIR . 'templates/email-template.php';
        
        if (file_exists($template_file)) {
            ob_start();
            include $template_file;
            return ob_get_clean();
        }
        
        // Fallback to simple HTML template
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . esc_html($subject) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>FT1 Cultural</h1>
                </div>
                <div class="content">
                    ' . nl2br(esc_html($body)) . '
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' FT1 Cultural - Fabricat1 Soluções de Mercado</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Check if user wants notification
     */
    private function user_wants_notification($user_id, $template_key) {
        // Check global auto notifications setting
        if (!$this->settings['auto_notifications']) {
            return false;
        }
        
        // Check user-specific preferences
        $user_preferences = get_user_meta($user_id, 'ft1_notification_preferences', true);
        
        if (!is_array($user_preferences)) {
            return true; // Default to enabled
        }
        
        // Check if this specific notification type is disabled
        if (isset($user_preferences[$template_key]) && !$user_preferences[$template_key]) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get user notifications via AJAX
     */
    public function get_user_notifications() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);
        $status = sanitize_text_field($_POST['status'] ?? 'all');
        
        $notifications = $this->get_notifications($user_id, $page, $per_page, $status);
        
        wp_send_json_success($notifications);
    }
    
    /**
     * Get notifications for user
     */
    public function get_notifications($user_id, $page = 1, $per_page = 20, $status = 'all') {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('notifications');
        $offset = ($page - 1) * $per_page;
        
        $where_clause = "WHERE user_id = %d";
        $where_values = array($user_id);
        
        if ($status !== 'all') {
            $where_clause .= " AND status = %s";
            $where_values[] = $status;
        }
        
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($where_values, array($per_page, $offset))
        ));
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} {$where_clause}",
            $where_values
        ));
        
        return array(
            'notifications' => $notifications,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Mark notification as read
     */
    public function mark_notification_read() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        $result = $wpdb->update(
            FT1_Cultural_Database::get_table_name('notifications'),
            array(
                'status' => 'read',
                'read_at' => current_time('mysql')
            ),
            array(
                'id' => $notification_id,
                'user_id' => $user_id
            ),
            array('%s', '%s'),
            array('%d', '%d')
        );
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao marcar notificação como lida.', 'ft1-cultural'));
        }
    }
    
    /**
     * Send manual notification
     */
    public function send_manual_notification() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('manage_ft1_notifications')) {
            wp_die(__('Permissões insuficientes.', 'ft1-cultural'));
        }
        
        $recipients = array_map('intval', $_POST['recipients']);
        $subject = sanitize_text_field($_POST['subject']);
        $message = sanitize_textarea_field($_POST['message']);
        $channels = array_map('sanitize_text_field', $_POST['channels']);
        $priority = sanitize_text_field($_POST['priority']);
        
        $sent_count = 0;
        
        foreach ($recipients as $user_id) {
            if ($this->send_notification($user_id, 'manual', array(
                'subject' => $subject,
                'message' => $message
            ), $channels, $priority)) {
                $sent_count++;
            }
        }
        
        wp_send_json_success(array(
            'sent_count' => $sent_count,
            'total_recipients' => count($recipients)
        ));
    }
    
    /**
     * Automatic notification handlers
     */
    public function notify_edital_published($edital_id, $edital_data) {
        // Notify all proponentes about new edital
        $proponentes = get_users(array('role' => 'ft1_proponente'));
        
        foreach ($proponentes as $proponente) {
            $this->send_notification($proponente->ID, 'edital_published', array(
                'edital_id' => $edital_id,
                'edital_title' => $edital_data['titulo'],
                'edital_description' => $edital_data['descricao'],
                'edital_start_date' => date('d/m/Y', strtotime($edital_data['data_inicio'])),
                'edital_end_date' => date('d/m/Y', strtotime($edital_data['data_fim'])),
                'edital_value' => 'R$ ' . number_format($edital_data['valor_total'], 2, ',', '.'),
                'edital_url' => admin_url('admin.php?page=ft1-cultural-editais&action=view&id=' . $edital_id)
            ), array('email', 'whatsapp'));
        }
    }
    
    public function notify_projeto_submitted($projeto_id, $projeto_data) {
        // Notify evaluators and managers
        $evaluators = get_users(array('role__in' => array('ft1_administrator', 'ft1_manager', 'ft1_evaluator')));
        
        foreach ($evaluators as $evaluator) {
            $this->send_notification($evaluator->ID, 'projeto_submitted', array(
                'projeto_id' => $projeto_id,
                'projeto_title' => $projeto_data['titulo'],
                'proponente_name' => $projeto_data['proponente_name'],
                'edital_title' => $projeto_data['edital_title'],
                'projeto_value' => 'R$ ' . number_format($projeto_data['valor_solicitado'], 2, ',', '.'),
                'submission_date' => date('d/m/Y H:i', strtotime($projeto_data['data_submissao'])),
                'projeto_url' => admin_url('admin.php?page=ft1-cultural-projetos&action=view&id=' . $projeto_id)
            ), array('email'));
        }
    }
    
    public function notify_projeto_evaluated($projeto_id, $projeto_data, $evaluation_data) {
        $template_key = $evaluation_data['status'] === 'aprovado' ? 'projeto_approved' : 'projeto_rejected';
        
        // Get proponente user
        $proponente = get_user_by('id', $projeto_data['user_id']);
        
        if ($proponente) {
            $notification_data = array(
                'projeto_id' => $projeto_id,
                'projeto_title' => $projeto_data['titulo'],
                'edital_title' => $projeto_data['edital_title'],
                'evaluation_date' => date('d/m/Y H:i'),
                'projeto_url' => admin_url('admin.php?page=ft1-cultural-projetos&action=view&id=' . $projeto_id)
            );
            
            if ($evaluation_data['status'] === 'aprovado') {
                $notification_data['approved_value'] = 'R$ ' . number_format($evaluation_data['valor_aprovado'], 2, ',', '.');
                $notification_data['approval_date'] = date('d/m/Y H:i');
            } else {
                $notification_data['rejection_reason'] = $evaluation_data['motivo_rejeicao'];
                $notification_data['recommendations'] = $evaluation_data['recomendacoes'] ?? '';
            }
            
            $this->send_notification($proponente->ID, $template_key, $notification_data, array('email', 'whatsapp'), 'high');
        }
    }
    
    public function notify_contrato_sent($contrato_id, $contrato_data) {
        // Get proponente user
        $proponente = get_user_by('id', $contrato_data['user_id']);
        
        if ($proponente) {
            $this->send_notification($proponente->ID, 'contrato_sent', array(
                'contrato_id' => $contrato_id,
                'projeto_title' => $contrato_data['projeto_title'],
                'contract_value' => 'R$ ' . number_format($contrato_data['valor'], 2, ',', '.'),
                'signature_deadline' => date('d/m/Y', strtotime('+15 days')),
                'validity_days' => '15',
                'signature_url' => home_url('/ft1-contrato-assinatura/?token=' . $contrato_data['signature_token'])
            ), array('email', 'whatsapp'), 'high');
        }
    }
    
    public function notify_contrato_signed($contrato_id, $contrato_data) {
        // Notify managers about signed contract
        $managers = get_users(array('role__in' => array('ft1_administrator', 'ft1_manager')));
        
        foreach ($managers as $manager) {
            $this->send_notification($manager->ID, 'contrato_signed', array(
                'contrato_id' => $contrato_id,
                'projeto_title' => $contrato_data['projeto_title'],
                'proponente_name' => $contrato_data['proponente_name'],
                'signature_date' => date('d/m/Y H:i', strtotime($contrato_data['data_assinatura'])),
                'signature_ip' => $contrato_data['ip_assinatura'],
                'contract_url' => admin_url('admin.php?page=ft1-cultural-contratos&action=view&id=' . $contrato_id)
            ), array('email'));
        }
    }
    
    public function notify_document_uploaded($document_id, $document_data, $uploader_data) {
        // Notify relevant users about document upload
        $recipients = array();
        
        // Add managers and evaluators
        $staff = get_users(array('role__in' => array('ft1_administrator', 'ft1_manager', 'ft1_evaluator')));
        foreach ($staff as $user) {
            $recipients[] = $user->ID;
        }
        
        foreach ($recipients as $user_id) {
            $this->send_notification($user_id, 'document_uploaded', array(
                'document_id' => $document_id,
                'document_name' => $document_data['nome_original'],
                'document_type' => $document_data['tipo'],
                'uploader_name' => $uploader_data['display_name'],
                'related_object' => $document_data['relacionado_tipo'] . ' #' . $document_data['relacionado_id'],
                'upload_date' => date('d/m/Y H:i'),
                'document_url' => admin_url('admin.php?page=ft1-cultural-documents&action=view&id=' . $document_id)
            ), array('email'));
        }
    }
    
    /**
     * Set mail from address
     */
    public function set_mail_from($email) {
        return $this->settings['from_email'];
    }
    
    /**
     * Set mail from name
     */
    public function set_mail_from_name($name) {
        return $this->settings['from_name'];
    }
    
    /**
     * Schedule cleanup of old notifications
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('ft1_cleanup_notifications')) {
            wp_schedule_event(time(), 'daily', 'ft1_cleanup_notifications');
        }
    }
    
    /**
     * Cleanup old notifications
     */
    public function cleanup_old_notifications() {
        global $wpdb;
        
        $retention_days = $this->settings['notification_retention_days'];
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . FT1_Cultural_Database::get_table_name('notifications') . " 
            WHERE created_at < %s AND status = 'read'",
            $cutoff_date
        ));
    }
    
    /**
     * Get notification statistics
     */
    public function get_notification_stats($days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $table = FT1_Cultural_Database::get_table_name('notifications');
        
        $stats = array(
            'total_sent' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE sent_at >= %s",
                $start_date
            )),
            'total_read' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE read_at >= %s",
                $start_date
            )),
            'by_type' => $wpdb->get_results($wpdb->prepare(
                "SELECT type, COUNT(*) as count FROM {$table} 
                WHERE created_at >= %s GROUP BY type ORDER BY count DESC",
                $start_date
            ), ARRAY_A),
            'by_priority' => $wpdb->get_results($wpdb->prepare(
                "SELECT priority, COUNT(*) as count FROM {$table} 
                WHERE created_at >= %s GROUP BY priority",
                $start_date
            ), ARRAY_A)
        );
        
        $stats['read_rate'] = $stats['total_sent'] > 0 ? 
            round(($stats['total_read'] / $stats['total_sent']) * 100, 2) : 0;
        
        return $stats;
    }
}

