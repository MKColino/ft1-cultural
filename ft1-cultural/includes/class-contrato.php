<?php
/**
 * Contrato management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(ABSPATH . 'wp-admin/includes/file.php');

class FT1_Cultural_Contrato {
    
    private static $instance = null;
    private $contracts_dir;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->contracts_dir = wp_upload_dir()['basedir'] . '/ft1-cultural/contratos';
        
        add_action('wp_ajax_ft1_create_contrato', array($this, 'ajax_create_contrato'));
        add_action('wp_ajax_ft1_update_contrato', array($this, 'ajax_update_contrato'));
        add_action('wp_ajax_ft1_get_contrato', array($this, 'ajax_get_contrato'));
        add_action('wp_ajax_ft1_send_contrato', array($this, 'ajax_send_contrato'));
        add_action('wp_ajax_ft1_sign_contrato', array($this, 'ajax_sign_contrato'));
        add_action('wp_ajax_nopriv_ft1_sign_contrato', array($this, 'ajax_sign_contrato'));
        add_action('wp_ajax_ft1_get_contrato_pdf', array($this, 'ajax_get_contrato_pdf'));
        add_action('wp_ajax_nopriv_ft1_get_contrato_pdf', array($this, 'ajax_get_contrato_pdf'));
        
        // Add rewrite rules for contract signing
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_contract_signing'));
    }
    
    /**
     * Create a new contrato
     */
    public function create($data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('contratos');
        
        $defaults = array(
            'projeto_id' => 0,
            'tipo' => 'execucao',
            'conteudo' => '',
            'valor' => 0,
            'data_inicio' => '',
            'data_fim' => '',
            'status' => 'rascunho',
            'created_by' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Generate contract number
        $data['numero_contrato'] = $this->generate_contract_number($data['projeto_id']);
        
        // Validate required fields
        $required_fields = array('projeto_id', 'conteudo', 'valor', 'data_inicio', 'data_fim');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('O campo %s é obrigatório.', 'ft1-cultural'), $field));
            }
        }
        
        // Validate project exists
        $projeto = FT1_Cultural_Projeto::instance()->get($data['projeto_id']);
        if (!$projeto) {
            return new WP_Error('invalid_projeto', __('Projeto não encontrado.', 'ft1-cultural'));
        }
        
        // Check if project is approved
        if ($projeto->status != 'aprovado') {
            return new WP_Error('projeto_not_approved', __('Projeto deve estar aprovado para gerar contrato.', 'ft1-cultural'));
        }
        
        // Validate dates
        if (strtotime($data['data_inicio']) >= strtotime($data['data_fim'])) {
            return new WP_Error('invalid_dates', __('A data de início deve ser anterior à data de fim.', 'ft1-cultural'));
        }
        
        // Validate valor
        if ($data['valor'] <= 0) {
            return new WP_Error('invalid_value', __('Valor deve ser maior que zero.', 'ft1-cultural'));
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        // Replace template variables in content
        $data['conteudo'] = $this->replace_template_variables($data['conteudo'], $projeto);
        
        $result = $wpdb->insert(
            $table,
            $data,
            array('%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao criar contrato.', 'ft1-cultural'));
        }
        
        $contrato_id = $wpdb->insert_id;
        
        // Generate PDF
        $this->generate_pdf($contrato_id);
        
        // Log the action
        $this->log_action('create', $contrato_id, null, $data);
        
        do_action('ft1_cultural_contrato_created', $contrato_id, $data);
        
        return $contrato_id;
    }
    
    /**
     * Update an existing contrato
     */
    public function update($id, $data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('contratos');
        
        // Get current data for logging
        $current_data = $this->get($id);
        if (!$current_data) {
            return new WP_Error('not_found', __('Contrato não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        if (!current_user_can('edit_ft1_contratos') && $current_data->created_by != get_current_user_id()) {
            return new WP_Error('permission_denied', __('Você não tem permissão para editar este contrato.', 'ft1-cultural'));
        }
        
        // Check if contract can be edited
        if (in_array($current_data->status, array('assinado', 'vigente'))) {
            return new WP_Error('cannot_edit', __('Contrato não pode ser editado após assinatura.', 'ft1-cultural'));
        }
        
        // Validate dates if provided
        if (isset($data['data_inicio']) && isset($data['data_fim'])) {
            if (strtotime($data['data_inicio']) >= strtotime($data['data_fim'])) {
                return new WP_Error('invalid_dates', __('A data de início deve ser anterior à data de fim.', 'ft1-cultural'));
            }
        }
        
        // Validate valor if provided
        if (isset($data['valor']) && $data['valor'] <= 0) {
            return new WP_Error('invalid_value', __('Valor deve ser maior que zero.', 'ft1-cultural'));
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        // Replace template variables if content is updated
        if (isset($data['conteudo'])) {
            $projeto = FT1_Cultural_Projeto::instance()->get($current_data->projeto_id);
            $data['conteudo'] = $this->replace_template_variables($data['conteudo'], $projeto);
        }
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao atualizar contrato.', 'ft1-cultural'));
        }
        
        // Regenerate PDF if content changed
        if (isset($data['conteudo']) || isset($data['valor']) || isset($data['data_inicio']) || isset($data['data_fim'])) {
            $this->generate_pdf($id);
        }
        
        // Log the action
        $this->log_action('update', $id, $current_data, $data);
        
        do_action('ft1_cultural_contrato_updated', $id, $data, $current_data);
        
        return true;
    }
    
    /**
     * Send contract via email and/or WhatsApp
     */
    public function send_contract($id, $methods = array('email')) {
        global $wpdb;
        
        $contrato = $this->get($id);
        if (!$contrato) {
            return new WP_Error('not_found', __('Contrato não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        if (!current_user_can('send_ft1_contratos')) {
            return new WP_Error('permission_denied', __('Você não tem permissão para enviar contratos.', 'ft1-cultural'));
        }
        
        // Get project and proponent data
        $projeto = FT1_Cultural_Projeto::instance()->get($contrato->projeto_id);
        $proponente = FT1_Cultural_Proponente::instance()->get($projeto->proponente_id);
        
        $results = array();
        
        // Generate signing URL
        $signing_url = $this->generate_signing_url($id);
        
        // Send via email
        if (in_array('email', $methods)) {
            $email_result = $this->send_email($contrato, $proponente, $signing_url);
            $results['email'] = $email_result;
            
            if (!is_wp_error($email_result)) {
                $wpdb->update(
                    FT1_Cultural_Database::get_table_name('contratos'),
                    array('enviado_email' => true),
                    array('id' => $id),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        // Send via WhatsApp
        if (in_array('whatsapp', $methods)) {
            $whatsapp_result = $this->send_whatsapp($contrato, $proponente, $signing_url);
            $results['whatsapp'] = $whatsapp_result;
            
            if (!is_wp_error($whatsapp_result)) {
                $wpdb->update(
                    FT1_Cultural_Database::get_table_name('contratos'),
                    array('enviado_whatsapp' => true),
                    array('id' => $id),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        // Update contract status
        if ($contrato->status == 'rascunho') {
            $wpdb->update(
                FT1_Cultural_Database::get_table_name('contratos'),
                array('status' => 'enviado'),
                array('id' => $id),
                array('%s'),
                array('%d')
            );
        }
        
        // Log the action
        $this->log_action('send', $id, $contrato, array('methods' => $methods, 'results' => $results));
        
        do_action('ft1_cultural_contrato_sent', $id, $methods, $results);
        
        return $results;
    }
    
    /**
     * Sign contract
     */
    public function sign_contract($id, $signature_data) {
        global $wpdb;
        
        $contrato = $this->get($id);
        if (!$contrato) {
            return new WP_Error('not_found', __('Contrato não encontrado.', 'ft1-cultural'));
        }
        
        // Check if contract can be signed
        if (!in_array($contrato->status, array('enviado', 'rascunho'))) {
            return new WP_Error('cannot_sign', __('Contrato não pode ser assinado no status atual.', 'ft1-cultural'));
        }
        
        // Validate signature data
        if (empty($signature_data['signature'])) {
            return new WP_Error('missing_signature', __('Assinatura é obrigatória.', 'ft1-cultural'));
        }
        
        // Get client information for legal validation
        $ip_address = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $timestamp = current_time('mysql');
        
        // Generate document hash for integrity
        $document_hash = $this->generate_document_hash($contrato);
        
        // Prepare signature data
        $signature_update = array(
            'status' => 'assinado',
            'assinado_em' => $timestamp,
            'assinatura_proponente' => wp_json_encode($signature_data),
            'ip_assinatura' => $ip_address,
            'user_agent' => $user_agent,
            'hash_documento' => $document_hash
        );
        
        $result = $wpdb->update(
            FT1_Cultural_Database::get_table_name('contratos'),
            $signature_update,
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao processar assinatura.', 'ft1-cultural'));
        }
        
        // Generate signed PDF
        $this->generate_signed_pdf($id, $signature_data);
        
        // Update project status
        $wpdb->update(
            FT1_Cultural_Database::get_table_name('projetos'),
            array('status' => 'em_execucao'),
            array('id' => $contrato->projeto_id),
            array('%s'),
            array('%d')
        );
        
        // Log the action
        $this->log_action('sign', $id, $contrato, $signature_update);
        
        // Send confirmation notifications
        do_action('ft1_cultural_contrato_signed', $id, $signature_data);
        
        return true;
    }
    
    /**
     * Get a contrato by ID
     */
    public function get($id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('contratos');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, p.titulo as projeto_titulo, pr.nome_completo as proponente_nome, pr.email as proponente_email
            FROM $table c
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('projetos') . " p ON c.projeto_id = p.id
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('proponentes') . " pr ON p.proponente_id = pr.id
            WHERE c.id = %d",
            $id
        ));
    }
    
    /**
     * Generate PDF
     */
    private function generate_pdf($contrato_id) {
        $contrato = $this->get($contrato_id);
        if (!$contrato) {
            return false;
        }
        
        // Ensure contracts directory exists
        if (!file_exists($this->contracts_dir)) {
            wp_mkdir_p($this->contracts_dir);
        }
        
        $filename = "contrato_{$contrato_id}_{$contrato->numero_contrato}.pdf";
        $filepath = $this->contracts_dir . '/' . $filename;
        
        // Generate HTML content
        $html_content = $this->generate_pdf_html($contrato);
        
        // Use WeasyPrint or similar to generate PDF
        // For now, we'll use a simple HTML to PDF conversion
        $this->html_to_pdf($html_content, $filepath);
        
        // Update database with PDF path
        global $wpdb;
        $wpdb->update(
            FT1_Cultural_Database::get_table_name('contratos'),
            array('pdf_path' => $filename),
            array('id' => $contrato_id),
            array('%s'),
            array('%d')
        );
        
        return $filepath;
    }
    
    /**
     * Generate signed PDF
     */
    private function generate_signed_pdf($contrato_id, $signature_data) {
        $contrato = $this->get($contrato_id);
        if (!$contrato) {
            return false;
        }
        
        $filename = "contrato_assinado_{$contrato_id}_{$contrato->numero_contrato}.pdf";
        $filepath = $this->contracts_dir . '/' . $filename;
        
        // Generate HTML content with signature
        $html_content = $this->generate_signed_pdf_html($contrato, $signature_data);
        
        // Generate PDF
        $this->html_to_pdf($html_content, $filepath);
        
        // Update database with signed PDF path
        global $wpdb;
        $wpdb->update(
            FT1_Cultural_Database::get_table_name('contratos'),
            array('pdf_path' => $filename),
            array('id' => $contrato_id),
            array('%s'),
            array('%d')
        );
        
        return $filepath;
    }
    
    /**
     * Generate PDF HTML content
     */
    private function generate_pdf_html($contrato) {
        $projeto = FT1_Cultural_Projeto::instance()->get($contrato->projeto_id);
        $proponente = FT1_Cultural_Proponente::instance()->get($projeto->proponente_id);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Contrato <?php echo esc_html($contrato->numero_contrato); ?></title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .contract-number { font-weight: bold; font-size: 14px; }
                .content { text-align: justify; }
                .signature-area { margin-top: 50px; }
                .signature-box { border: 1px solid #ccc; height: 100px; margin: 20px 0; }
                .footer { margin-top: 30px; font-size: 10px; }
                .legal-info { background: #f5f5f5; padding: 10px; margin: 20px 0; font-size: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>CONTRATO DE <?php echo strtoupper($contrato->tipo); ?></h1>
                <div class="contract-number">Nº <?php echo esc_html($contrato->numero_contrato); ?></div>
            </div>
            
            <div class="content">
                <?php echo wp_kses_post($contrato->conteudo); ?>
            </div>
            
            <div class="legal-info">
                <strong>Informações Legais:</strong><br>
                Data de Geração: <?php echo date('d/m/Y H:i:s'); ?><br>
                Valor do Contrato: R$ <?php echo number_format($contrato->valor, 2, ',', '.'); ?><br>
                Período de Vigência: <?php echo date('d/m/Y', strtotime($contrato->data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($contrato->data_fim)); ?><br>
                Hash do Documento: <?php echo $this->generate_document_hash($contrato); ?>
            </div>
            
            <div class="signature-area">
                <p><strong>ASSINATURA DIGITAL</strong></p>
                <p>Este contrato deve ser assinado digitalmente através do sistema FT1 Cultural.</p>
                <div class="signature-box">
                    <p style="text-align: center; margin-top: 40px;">Área reservada para assinatura digital</p>
                </div>
                <p>Proponente: <?php echo esc_html($proponente->nome_completo); ?></p>
                <p>CPF/CNPJ: <?php echo esc_html($proponente->cpf_cnpj); ?></p>
            </div>
            
            <div class="footer">
                <p>Este documento foi gerado pelo sistema FT1 Cultural - Fabricat1 Soluções de Mercado</p>
                <p>© <?php echo date('Y'); ?> Fabricat1 Soluções de Mercado. Todos os direitos reservados.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate signed PDF HTML content
     */
    private function generate_signed_pdf_html($contrato, $signature_data) {
        $projeto = FT1_Cultural_Projeto::instance()->get($contrato->projeto_id);
        $proponente = FT1_Cultural_Proponente::instance()->get($projeto->proponente_id);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Contrato Assinado <?php echo esc_html($contrato->numero_contrato); ?></title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .contract-number { font-weight: bold; font-size: 14px; }
                .content { text-align: justify; }
                .signature-area { margin-top: 50px; border: 2px solid #28a745; padding: 20px; }
                .signature-info { background: #d4edda; padding: 15px; margin: 10px 0; }
                .signature-image { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
                .footer { margin-top: 30px; font-size: 10px; }
                .legal-info { background: #f5f5f5; padding: 10px; margin: 20px 0; font-size: 10px; }
                .signed-stamp { color: #28a745; font-weight: bold; font-size: 16px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>CONTRATO DE <?php echo strtoupper($contrato->tipo); ?></h1>
                <div class="contract-number">Nº <?php echo esc_html($contrato->numero_contrato); ?></div>
                <div class="signed-stamp">✓ DOCUMENTO ASSINADO DIGITALMENTE</div>
            </div>
            
            <div class="content">
                <?php echo wp_kses_post($contrato->conteudo); ?>
            </div>
            
            <div class="legal-info">
                <strong>Informações Legais:</strong><br>
                Data de Geração: <?php echo date('d/m/Y H:i:s', strtotime($contrato->created_at)); ?><br>
                Valor do Contrato: R$ <?php echo number_format($contrato->valor, 2, ',', '.'); ?><br>
                Período de Vigência: <?php echo date('d/m/Y', strtotime($contrato->data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($contrato->data_fim)); ?><br>
                Hash do Documento: <?php echo esc_html($contrato->hash_documento); ?>
            </div>
            
            <div class="signature-area">
                <h3>ASSINATURA DIGITAL VÁLIDA</h3>
                
                <div class="signature-info">
                    <strong>Informações da Assinatura:</strong><br>
                    Data/Hora: <?php echo date('d/m/Y H:i:s', strtotime($contrato->assinado_em)); ?><br>
                    IP do Signatário: <?php echo esc_html($contrato->ip_assinatura); ?><br>
                    Navegador: <?php echo esc_html(substr($contrato->user_agent, 0, 100)); ?>...
                </div>
                
                <?php if (isset($signature_data['signature']) && !empty($signature_data['signature'])): ?>
                <div class="signature-image">
                    <p><strong>Assinatura Digital:</strong></p>
                    <img src="<?php echo esc_attr($signature_data['signature']); ?>" alt="Assinatura Digital" style="max-width: 300px; max-height: 100px;">
                </div>
                <?php endif; ?>
                
                <p><strong>Signatário:</strong> <?php echo esc_html($proponente->nome_completo); ?></p>
                <p><strong>CPF/CNPJ:</strong> <?php echo esc_html($proponente->cpf_cnpj); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($proponente->email); ?></p>
            </div>
            
            <div class="footer">
                <p>Este documento foi assinado digitalmente através do sistema FT1 Cultural - Fabricat1 Soluções de Mercado</p>
                <p>A validade jurídica desta assinatura digital é garantida pela Lei 14.063/2020 e MP 2.200-2/2001</p>
                <p>© <?php echo date('Y'); ?> Fabricat1 Soluções de Mercado. Todos os direitos reservados.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Convert HTML to PDF
     */
    private function html_to_pdf($html_content, $output_path) {
        // Using WeasyPrint for better PDF generation
        $temp_html = tempnam(sys_get_temp_dir(), 'ft1_contract_') . '.html';
        file_put_contents($temp_html, $html_content);
        
        // Try WeasyPrint first
        $command = "weasyprint '$temp_html' '$output_path' 2>/dev/null";
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            // Fallback to wkhtmltopdf
            $command = "wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm '$temp_html' '$output_path' 2>/dev/null";
            exec($command, $output, $return_code);
            
            if ($return_code !== 0) {
                // Final fallback: use PHP library
                $this->generate_pdf_fallback($html_content, $output_path);
            }
        }
        
        unlink($temp_html);
        return file_exists($output_path);
    }
    
    /**
     * Fallback PDF generation
     */
    private function generate_pdf_fallback($html_content, $output_path) {
        // Simple HTML to PDF conversion using FPDF or similar
        // This is a basic implementation - in production, use a proper library
        
        require_once(FT1_CULTURAL_PLUGIN_DIR . 'includes/libraries/fpdf/fpdf.php');
        
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Convert HTML to plain text for basic PDF
        $text_content = strip_tags($html_content);
        $text_content = html_entity_decode($text_content);
        
        $pdf->MultiCell(0, 10, utf8_decode($text_content));
        $pdf->Output('F', $output_path);
    }
    
    /**
     * Generate contract number
     */
    private function generate_contract_number($projeto_id) {
        global $wpdb;
        
        $projeto = FT1_Cultural_Projeto::instance()->get($projeto_id);
        $year = date('Y');
        
        // Get next sequential number for this year
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('contratos') . " 
            WHERE YEAR(created_at) = %d",
            $year
        ));
        
        $sequential = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        return "FT1-CONT-{$year}-{$sequential}";
    }
    
    /**
     * Replace template variables
     */
    private function replace_template_variables($content, $projeto) {
        $proponente = FT1_Cultural_Proponente::instance()->get($projeto->proponente_id);
        $edital = FT1_Cultural_Edital::instance()->get($projeto->edital_id);
        
        $variables = array(
            '{{PROJETO_TITULO}}' => $projeto->titulo,
            '{{PROJETO_CODIGO}}' => $projeto->codigo_projeto,
            '{{PROJETO_VALOR}}' => 'R$ ' . number_format($projeto->valor_aprovado, 2, ',', '.'),
            '{{PROPONENTE_NOME}}' => $proponente->nome_completo,
            '{{PROPONENTE_CPF_CNPJ}}' => $proponente->cpf_cnpj,
            '{{PROPONENTE_EMAIL}}' => $proponente->email,
            '{{PROPONENTE_TELEFONE}}' => $proponente->telefone,
            '{{PROPONENTE_ENDERECO}}' => $proponente->endereco_completo,
            '{{EDITAL_TITULO}}' => $edital->titulo,
            '{{DATA_ATUAL}}' => date('d/m/Y'),
            '{{ANO_ATUAL}}' => date('Y')
        );
        
        return str_replace(array_keys($variables), array_values($variables), $content);
    }
    
    /**
     * Generate document hash
     */
    private function generate_document_hash($contrato) {
        $data = $contrato->numero_contrato . $contrato->conteudo . $contrato->valor . $contrato->data_inicio . $contrato->data_fim;
        return hash('sha256', $data);
    }
    
    /**
     * Generate signing URL
     */
    private function generate_signing_url($contrato_id) {
        $token = wp_generate_password(32, false);
        
        // Store token temporarily
        set_transient("ft1_contract_token_{$contrato_id}", $token, DAY_IN_SECONDS);
        
        return home_url("ft1-contract-sign/{$contrato_id}/?token={$token}");
    }
    
    /**
     * Get client IP
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
     * Send email
     */
    private function send_email($contrato, $proponente, $signing_url) {
        $subject = sprintf(__('Contrato %s - Assinatura Digital Necessária', 'ft1-cultural'), $contrato->numero_contrato);
        
        $message = sprintf(
            __('Olá %s,

Seu contrato %s está pronto para assinatura digital.

Para assinar o contrato, acesse o link abaixo:
%s

Este link é válido por 24 horas.

Dados do Contrato:
- Número: %s
- Valor: R$ %s
- Vigência: %s a %s

Em caso de dúvidas, entre em contato conosco.

Atenciosamente,
Equipe FT1 Cultural', 'ft1-cultural'),
            $proponente->nome_completo,
            $contrato->numero_contrato,
            $signing_url,
            $contrato->numero_contrato,
            number_format($contrato->valor, 2, ',', '.'),
            date('d/m/Y', strtotime($contrato->data_inicio)),
            date('d/m/Y', strtotime($contrato->data_fim))
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($proponente->email, $subject, nl2br($message), $headers);
    }
    
    /**
     * Send WhatsApp
     */
    private function send_whatsapp($contrato, $proponente, $signing_url) {
        // This would integrate with a WhatsApp API service
        // For now, return success as placeholder
        
        $message = sprintf(
            'Olá %s! Seu contrato %s está pronto para assinatura digital. Acesse: %s',
            $proponente->nome_completo,
            $contrato->numero_contrato,
            $signing_url
        );
        
        // Here you would integrate with WhatsApp Business API or similar service
        // Example: Twilio, ChatAPI, etc.
        
        return true; // Placeholder
    }
    
    /**
     * Add rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^ft1-contract-sign/([0-9]+)/?$',
            'index.php?ft1_contract_sign=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%ft1_contract_sign%', '([0-9]+)');
    }
    
    /**
     * Handle contract signing page
     */
    public function handle_contract_signing() {
        $contract_id = get_query_var('ft1_contract_sign');
        
        if ($contract_id) {
            $this->display_signing_page($contract_id);
            exit;
        }
    }
    
    /**
     * Display signing page
     */
    private function display_signing_page($contract_id) {
        $token = $_GET['token'] ?? '';
        $stored_token = get_transient("ft1_contract_token_{$contract_id}");
        
        if (!$token || $token !== $stored_token) {
            wp_die(__('Link inválido ou expirado.', 'ft1-cultural'));
        }
        
        $contrato = $this->get($contract_id);
        if (!$contrato) {
            wp_die(__('Contrato não encontrado.', 'ft1-cultural'));
        }
        
        // Load signing page template
        include FT1_CULTURAL_PLUGIN_DIR . 'templates/contract-signing.php';
    }
    
    /**
     * Sanitize contrato data
     */
    private function sanitize_data($data) {
        $sanitized = array();
        
        if (isset($data['projeto_id'])) {
            $sanitized['projeto_id'] = intval($data['projeto_id']);
        }
        
        if (isset($data['numero_contrato'])) {
            $sanitized['numero_contrato'] = sanitize_text_field($data['numero_contrato']);
        }
        
        if (isset($data['tipo'])) {
            $allowed_types = array('execucao', 'prestacao_contas', 'aditivo');
            $sanitized['tipo'] = in_array($data['tipo'], $allowed_types) ? $data['tipo'] : 'execucao';
        }
        
        if (isset($data['conteudo'])) {
            $sanitized['conteudo'] = wp_kses_post($data['conteudo']);
        }
        
        if (isset($data['valor'])) {
            $sanitized['valor'] = floatval($data['valor']);
        }
        
        if (isset($data['data_inicio'])) {
            $sanitized['data_inicio'] = sanitize_text_field($data['data_inicio']);
        }
        
        if (isset($data['data_fim'])) {
            $sanitized['data_fim'] = sanitize_text_field($data['data_fim']);
        }
        
        if (isset($data['status'])) {
            $allowed_status = array('rascunho', 'enviado', 'assinado', 'vigente', 'vencido', 'rescindido');
            $sanitized['status'] = in_array($data['status'], $allowed_status) ? $data['status'] : 'rascunho';
        }
        
        if (isset($data['created_by'])) {
            $sanitized['created_by'] = intval($data['created_by']);
        }
        
        return $sanitized;
    }
    
    /**
     * Log action
     */
    private function log_action($action, $object_id, $old_data, $new_data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('logs');
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => get_current_user_id(),
                'acao' => $action,
                'objeto_tipo' => 'contrato',
                'objeto_id' => $object_id,
                'dados_anteriores' => $old_data ? json_encode($old_data) : null,
                'dados_novos' => $new_data ? json_encode($new_data) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_create_contrato() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('create_ft1_contratos')) {
            wp_die(__('Você não tem permissão para criar contratos.', 'ft1-cultural'));
        }
        
        $data = $_POST['data'] ?? array();
        $result = $this->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array('id' => $result));
    }
    
    public function ajax_update_contrato() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $data = $_POST['data'] ?? array();
        
        $result = $this->update($id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_get_contrato() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $contrato = $this->get($id);
        
        if (!$contrato) {
            wp_send_json_error(__('Contrato não encontrado.', 'ft1-cultural'));
        }
        
        wp_send_json_success($contrato);
    }
    
    public function ajax_send_contrato() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $methods = $_POST['methods'] ?? array('email');
        
        $result = $this->send_contract($id, $methods);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_sign_contrato() {
        check_ajax_referer('ft1_cultural_sign_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $signature_data = $_POST['signature_data'] ?? array();
        
        $result = $this->sign_contract($id, $signature_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_get_contrato_pdf() {
        $id = intval($_GET['id'] ?? 0);
        $token = $_GET['token'] ?? '';
        
        // Verify token for public access
        $stored_token = get_transient("ft1_contract_token_{$id}");
        if (!$token || $token !== $stored_token) {
            wp_die(__('Acesso negado.', 'ft1-cultural'));
        }
        
        $contrato = $this->get($id);
        if (!$contrato || !$contrato->pdf_path) {
            wp_die(__('PDF não encontrado.', 'ft1-cultural'));
        }
        
        $pdf_path = $this->contracts_dir . '/' . $contrato->pdf_path;
        
        if (!file_exists($pdf_path)) {
            wp_die(__('Arquivo não encontrado.', 'ft1-cultural'));
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($contrato->pdf_path) . '"');
        header('Content-Length: ' . filesize($pdf_path));
        
        readfile($pdf_path);
        exit;
    }
}

