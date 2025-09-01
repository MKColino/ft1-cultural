<?php
/**
 * Upload management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Upload {
    
    private static $instance = null;
    private $upload_dir;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->upload_dir = wp_upload_dir()['basedir'] . '/ft1-cultural';
        
        add_action('wp_ajax_ft1_upload_document', array($this, 'ajax_upload_document'));
        add_action('wp_ajax_ft1_delete_document', array($this, 'ajax_delete_document'));
        add_action('wp_ajax_ft1_get_documents', array($this, 'ajax_get_documents'));
        add_action('wp_ajax_ft1_validate_document', array($this, 'ajax_validate_document'));
        
        // Security hooks
        add_filter('upload_mimes', array($this, 'filter_upload_mimes'));
        add_filter('wp_handle_upload_prefilter', array($this, 'filter_upload_prefilter'));
    }
    
    /**
     * Create upload directories
     */
    public static function create_directories() {
        $upload_dir = wp_upload_dir()['basedir'] . '/ft1-cultural';
        
        $directories = array(
            $upload_dir,
            $upload_dir . '/editais',
            $upload_dir . '/proponentes',
            $upload_dir . '/projetos',
            $upload_dir . '/contratos',
            $upload_dir . '/temp'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Create .htaccess for security
                $htaccess_content = "Options -Indexes\n";
                $htaccess_content .= "deny from all\n";
                $htaccess_content .= "<Files ~ \"\\.(pdf|doc|docx|jpg|jpeg|png)$\">\n";
                $htaccess_content .= "    allow from all\n";
                $htaccess_content .= "</Files>\n";
                
                file_put_contents($dir . '/.htaccess', $htaccess_content);
                
                // Create index.php for additional security
                file_put_contents($dir . '/index.php', '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Upload document
     */
    public function upload_document($file, $relacionado_tipo, $relacionado_id, $metadata = array()) {
        global $wpdb;
        
        // Validate file
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Validate relacionado_tipo and relacionado_id
        if (!$this->validate_relation($relacionado_tipo, $relacionado_id)) {
            return new WP_Error('invalid_relation', __('Relação inválida para o documento.', 'ft1-cultural'));
        }
        
        // Check permissions
        if (!$this->check_upload_permissions($relacionado_tipo, $relacionado_id)) {
            return new WP_Error('permission_denied', __('Você não tem permissão para fazer upload neste contexto.', 'ft1-cultural'));
        }
        
        // Generate secure filename
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        $secure_filename = $this->generate_secure_filename($relacionado_tipo, $relacionado_id, $extension);
        
        // Determine upload directory
        $upload_subdir = $this->get_upload_directory($relacionado_tipo, $relacionado_id);
        $upload_path = $this->upload_dir . '/' . $upload_subdir;
        
        if (!file_exists($upload_path)) {
            wp_mkdir_p($upload_path);
        }
        
        $full_path = $upload_path . '/' . $secure_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $full_path)) {
            return new WP_Error('upload_failed', __('Falha ao fazer upload do arquivo.', 'ft1-cultural'));
        }
        
        // Set file permissions
        chmod($full_path, 0644);
        
        // Save to database
        $document_data = array(
            'relacionado_tipo' => $relacionado_tipo,
            'relacionado_id' => $relacionado_id,
            'nome_original' => sanitize_file_name($file['name']),
            'nome_arquivo' => $secure_filename,
            'tipo_arquivo' => $extension,
            'tamanho_arquivo' => filesize($full_path),
            'caminho_arquivo' => $upload_subdir . '/' . $secure_filename,
            'descricao' => sanitize_textarea_field($metadata['descricao'] ?? ''),
            'categoria' => sanitize_text_field($metadata['categoria'] ?? ''),
            'obrigatorio' => (bool) ($metadata['obrigatorio'] ?? false),
            'uploaded_by' => get_current_user_id()
        );
        
        $table = FT1_Cultural_Database::get_table_name('documentos');
        
        $result = $wpdb->insert(
            $table,
            $document_data,
            array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            // Remove uploaded file if database insert failed
            unlink($full_path);
            return new WP_Error('db_error', __('Erro ao salvar informações do documento.', 'ft1-cultural'));
        }
        
        $document_id = $wpdb->insert_id;
        
        // Log the action
        $this->log_action('upload', $document_id, null, $document_data);
        
        do_action('ft1_cultural_document_uploaded', $document_id, $document_data);
        
        return $document_id;
    }
    
    /**
     * Delete document
     */
    public function delete_document($id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('documentos');
        
        // Get document info
        $document = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        
        if (!$document) {
            return new WP_Error('not_found', __('Documento não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        if (!$this->check_delete_permissions($document)) {
            return new WP_Error('permission_denied', __('Você não tem permissão para excluir este documento.', 'ft1-cultural'));
        }
        
        // Delete file from filesystem
        $file_path = $this->upload_dir . '/' . $document->caminho_arquivo;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $result = $wpdb->delete(
            $table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao excluir documento.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('delete', $id, $document, null);
        
        do_action('ft1_cultural_document_deleted', $id, $document);
        
        return true;
    }
    
    /**
     * Get documents
     */
    public function get_documents($relacionado_tipo, $relacionado_id, $categoria = null) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('documentos');
        
        $where = array(
            'relacionado_tipo = %s',
            'relacionado_id = %d'
        );
        $where_values = array($relacionado_tipo, $relacionado_id);
        
        if ($categoria) {
            $where[] = 'categoria = %s';
            $where_values[] = $categoria;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC";
        $query = $wpdb->prepare($query, $where_values);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Validate document
     */
    public function validate_document($id, $is_valid, $observacoes = '') {
        global $wpdb;
        
        if (!current_user_can('validate_ft1_documents')) {
            return new WP_Error('permission_denied', __('Você não tem permissão para validar documentos.', 'ft1-cultural'));
        }
        
        $table = FT1_Cultural_Database::get_table_name('documentos');
        
        $document = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        
        if (!$document) {
            return new WP_Error('not_found', __('Documento não encontrado.', 'ft1-cultural'));
        }
        
        $validation_data = array(
            'validado' => (bool) $is_valid,
            'validado_por' => get_current_user_id(),
            'validado_em' => current_time('mysql'),
            'observacoes_validacao' => sanitize_textarea_field($observacoes)
        );
        
        $result = $wpdb->update(
            $table,
            $validation_data,
            array('id' => $id),
            array('%d', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao validar documento.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('validate', $id, $document, $validation_data);
        
        do_action('ft1_cultural_document_validated', $id, $validation_data, $document);
        
        return true;
    }
    
    /**
     * Get document URL
     */
    public function get_document_url($id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('documentos');
        
        $document = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        
        if (!$document) {
            return false;
        }
        
        // Check permissions to view document
        if (!$this->check_view_permissions($document)) {
            return false;
        }
        
        $upload_url = wp_upload_dir()['baseurl'] . '/ft1-cultural';
        return $upload_url . '/' . $document->caminho_arquivo;
    }
    
    /**
     * Validate file
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }
        
        // Get plugin settings
        $settings = get_option('ft1_cultural_settings', array());
        $max_size = ($settings['max_file_size'] ?? 10) * 1024 * 1024; // Convert MB to bytes
        $allowed_types = $settings['allowed_file_types'] ?? array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png');
        
        // Check file size
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', sprintf(__('Arquivo muito grande. Tamanho máximo: %s MB', 'ft1-cultural'), $settings['max_file_size'] ?? 10));
        }
        
        // Check file type
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension'] ?? '');
        
        if (!in_array($extension, $allowed_types)) {
            return new WP_Error('invalid_file_type', sprintf(__('Tipo de arquivo não permitido. Tipos aceitos: %s', 'ft1-cultural'), implode(', ', $allowed_types)));
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        );
        
        if (!isset($allowed_mimes[$extension]) || $mime_type !== $allowed_mimes[$extension]) {
            return new WP_Error('invalid_mime_type', __('Tipo MIME do arquivo não corresponde à extensão.', 'ft1-cultural'));
        }
        
        // Scan for malware (basic check)
        if ($this->contains_malicious_content($file['tmp_name'])) {
            return new WP_Error('malicious_file', __('Arquivo contém conteúdo suspeito.', 'ft1-cultural'));
        }
        
        return true;
    }
    
    /**
     * Validate relation
     */
    private function validate_relation($tipo, $id) {
        global $wpdb;
        
        switch ($tipo) {
            case 'edital':
                $table = FT1_Cultural_Database::get_table_name('editais');
                break;
            case 'proponente':
                $table = FT1_Cultural_Database::get_table_name('proponentes');
                break;
            case 'projeto':
                $table = FT1_Cultural_Database::get_table_name('projetos');
                break;
            case 'contrato':
                $table = FT1_Cultural_Database::get_table_name('contratos');
                break;
            default:
                return false;
        }
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d",
            $id
        ));
        
        return (bool) $exists;
    }
    
    /**
     * Check upload permissions
     */
    private function check_upload_permissions($relacionado_tipo, $relacionado_id) {
        $user_id = get_current_user_id();
        
        // Admins can upload anywhere
        if (current_user_can('manage_ft1_cultural')) {
            return true;
        }
        
        switch ($relacionado_tipo) {
            case 'edital':
                return current_user_can('edit_ft1_editais');
                
            case 'proponente':
                $proponente = FT1_Cultural_Proponente::instance()->get($relacionado_id);
                return $proponente && $proponente->user_id == $user_id;
                
            case 'projeto':
                $projeto = FT1_Cultural_Projeto::instance()->get($relacionado_id);
                if (!$projeto) return false;
                
                $proponente = FT1_Cultural_Proponente::instance()->get($projeto->proponente_id);
                return $proponente && $proponente->user_id == $user_id;
                
            case 'contrato':
                return current_user_can('manage_ft1_contratos');
                
            default:
                return false;
        }
    }
    
    /**
     * Check delete permissions
     */
    private function check_delete_permissions($document) {
        $user_id = get_current_user_id();
        
        // Admins can delete anything
        if (current_user_can('manage_ft1_cultural')) {
            return true;
        }
        
        // Users can delete their own uploads
        if ($document->uploaded_by == $user_id) {
            return true;
        }
        
        // Check specific permissions based on relation
        return $this->check_upload_permissions($document->relacionado_tipo, $document->relacionado_id);
    }
    
    /**
     * Check view permissions
     */
    private function check_view_permissions($document) {
        $user_id = get_current_user_id();
        
        // Admins can view anything
        if (current_user_can('manage_ft1_cultural')) {
            return true;
        }
        
        // Users can view their own uploads
        if ($document->uploaded_by == $user_id) {
            return true;
        }
        
        // Check specific permissions based on relation
        return $this->check_upload_permissions($document->relacionado_tipo, $document->relacionado_id);
    }
    
    /**
     * Generate secure filename
     */
    private function generate_secure_filename($relacionado_tipo, $relacionado_id, $extension) {
        $timestamp = time();
        $random = wp_generate_password(8, false);
        return "{$relacionado_tipo}_{$relacionado_id}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Get upload directory
     */
    private function get_upload_directory($relacionado_tipo, $relacionado_id) {
        $year = date('Y');
        $month = date('m');
        
        return "{$relacionado_tipo}s/{$year}/{$month}";
    }
    
    /**
     * Check for malicious content
     */
    private function contains_malicious_content($file_path) {
        // Basic malware signatures
        $malicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        );
        
        $content = file_get_contents($file_path, false, null, 0, 8192); // Read first 8KB
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get upload error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('Arquivo muito grande.', 'ft1-cultural');
            case UPLOAD_ERR_PARTIAL:
                return __('Upload incompleto.', 'ft1-cultural');
            case UPLOAD_ERR_NO_FILE:
                return __('Nenhum arquivo selecionado.', 'ft1-cultural');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Diretório temporário não encontrado.', 'ft1-cultural');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Falha ao escrever arquivo.', 'ft1-cultural');
            case UPLOAD_ERR_EXTENSION:
                return __('Upload bloqueado por extensão.', 'ft1-cultural');
            default:
                return __('Erro desconhecido no upload.', 'ft1-cultural');
        }
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
                'objeto_tipo' => 'documento',
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
     * Filter upload mimes
     */
    public function filter_upload_mimes($mimes) {
        // Only apply to FT1 Cultural uploads
        if (!isset($_POST['ft1_cultural_upload'])) {
            return $mimes;
        }
        
        $settings = get_option('ft1_cultural_settings', array());
        $allowed_types = $settings['allowed_file_types'] ?? array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png');
        
        $ft1_mimes = array();
        $all_mimes = array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        );
        
        foreach ($allowed_types as $type) {
            if (isset($all_mimes[$type])) {
                $ft1_mimes[$type] = $all_mimes[$type];
            }
        }
        
        return $ft1_mimes;
    }
    
    /**
     * Filter upload prefilter
     */
    public function filter_upload_prefilter($file) {
        // Only apply to FT1 Cultural uploads
        if (!isset($_POST['ft1_cultural_upload'])) {
            return $file;
        }
        
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            $file['error'] = $validation->get_error_message();
        }
        
        return $file;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_upload_document() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(__('Nenhum arquivo enviado.', 'ft1-cultural'));
        }
        
        $relacionado_tipo = sanitize_text_field($_POST['relacionado_tipo'] ?? '');
        $relacionado_id = intval($_POST['relacionado_id'] ?? 0);
        $metadata = $_POST['metadata'] ?? array();
        
        $result = $this->upload_document($_FILES['file'], $relacionado_tipo, $relacionado_id, $metadata);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array('id' => $result));
    }
    
    public function ajax_delete_document() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        
        $result = $this->delete_document($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_get_documents() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $relacionado_tipo = sanitize_text_field($_POST['relacionado_tipo'] ?? '');
        $relacionado_id = intval($_POST['relacionado_id'] ?? 0);
        $categoria = sanitize_text_field($_POST['categoria'] ?? '');
        
        $documents = $this->get_documents($relacionado_tipo, $relacionado_id, $categoria ?: null);
        
        wp_send_json_success($documents);
    }
    
    public function ajax_validate_document() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $is_valid = (bool) ($_POST['is_valid'] ?? false);
        $observacoes = sanitize_textarea_field($_POST['observacoes'] ?? '');
        
        $result = $this->validate_document($id, $is_valid, $observacoes);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
}

