<?php
/**
 * Proponente management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Proponente {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_ft1_create_proponente', array($this, 'ajax_create_proponente'));
        add_action('wp_ajax_ft1_update_proponente', array($this, 'ajax_update_proponente'));
        add_action('wp_ajax_ft1_get_proponente', array($this, 'ajax_get_proponente'));
        add_action('wp_ajax_ft1_search_proponentes', array($this, 'ajax_search_proponentes'));
        
        // Hook for user registration
        add_action('user_register', array($this, 'create_proponente_on_user_register'));
    }
    
    /**
     * Create a new proponente
     */
    public function create($data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('proponentes');
        
        $defaults = array(
            'user_id' => get_current_user_id(),
            'tipo' => 'pessoa_fisica',
            'nome_completo' => '',
            'cpf_cnpj' => '',
            'rg_ie' => '',
            'data_nascimento' => null,
            'telefone' => '',
            'whatsapp' => '',
            'email' => '',
            'endereco_completo' => '',
            'cep' => '',
            'cidade' => '',
            'estado' => '',
            'pais' => 'Brasil',
            'area_atuacao' => '',
            'experiencia_profissional' => '',
            'portfolio_url' => '',
            'redes_sociais' => '',
            'banco' => '',
            'agencia' => '',
            'conta' => '',
            'tipo_conta' => 'corrente',
            'pix' => '',
            'status' => 'ativo'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Generate unique code
        $data['codigo_unico'] = $this->generate_unique_code();
        
        // Validate required fields
        $required_fields = array('nome_completo', 'email');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('O campo %s é obrigatório.', 'ft1-cultural'), $field));
            }
        }
        
        // Validate email
        if (!is_email($data['email'])) {
            return new WP_Error('invalid_email', __('Email inválido.', 'ft1-cultural'));
        }
        
        // Validate CPF/CNPJ
        if (!empty($data['cpf_cnpj'])) {
            if (!$this->validate_cpf_cnpj($data['cpf_cnpj'])) {
                return new WP_Error('invalid_cpf_cnpj', __('CPF/CNPJ inválido.', 'ft1-cultural'));
            }
        }
        
        // Check if CPF/CNPJ already exists
        if (!empty($data['cpf_cnpj'])) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE cpf_cnpj = %s AND user_id != %d",
                $data['cpf_cnpj'],
                $data['user_id']
            ));
            
            if ($existing) {
                return new WP_Error('cpf_cnpj_exists', __('CPF/CNPJ já cadastrado.', 'ft1-cultural'));
            }
        }
        
        // Check if email already exists
        $existing_email = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE email = %s AND user_id != %d",
            $data['email'],
            $data['user_id']
        ));
        
        if ($existing_email) {
            return new WP_Error('email_exists', __('Email já cadastrado.', 'ft1-cultural'));
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        // Serialize social networks
        if (isset($data['redes_sociais']) && is_array($data['redes_sociais'])) {
            $data['redes_sociais'] = json_encode($data['redes_sociais']);
        }
        
        $result = $wpdb->insert(
            $table,
            $data,
            array(
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao criar proponente.', 'ft1-cultural'));
        }
        
        $proponente_id = $wpdb->insert_id;
        
        // Log the action
        $this->log_action('create', $proponente_id, null, $data);
        
        do_action('ft1_cultural_proponente_created', $proponente_id, $data);
        
        return $proponente_id;
    }
    
    /**
     * Update an existing proponente
     */
    public function update($id, $data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('proponentes');
        
        // Get current data for logging
        $current_data = $this->get($id);
        if (!$current_data) {
            return new WP_Error('not_found', __('Proponente não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        if (!current_user_can('edit_ft1_proponentes') && $current_data->user_id != get_current_user_id()) {
            return new WP_Error('permission_denied', __('Você não tem permissão para editar este proponente.', 'ft1-cultural'));
        }
        
        // Validate email if provided
        if (isset($data['email']) && !is_email($data['email'])) {
            return new WP_Error('invalid_email', __('Email inválido.', 'ft1-cultural'));
        }
        
        // Validate CPF/CNPJ if provided
        if (isset($data['cpf_cnpj']) && !empty($data['cpf_cnpj'])) {
            if (!$this->validate_cpf_cnpj($data['cpf_cnpj'])) {
                return new WP_Error('invalid_cpf_cnpj', __('CPF/CNPJ inválido.', 'ft1-cultural'));
            }
            
            // Check if CPF/CNPJ already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE cpf_cnpj = %s AND id != %d",
                $data['cpf_cnpj'],
                $id
            ));
            
            if ($existing) {
                return new WP_Error('cpf_cnpj_exists', __('CPF/CNPJ já cadastrado.', 'ft1-cultural'));
            }
        }
        
        // Check if email already exists
        if (isset($data['email'])) {
            $existing_email = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE email = %s AND id != %d",
                $data['email'],
                $id
            ));
            
            if ($existing_email) {
                return new WP_Error('email_exists', __('Email já cadastrado.', 'ft1-cultural'));
            }
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        // Serialize social networks
        if (isset($data['redes_sociais']) && is_array($data['redes_sociais'])) {
            $data['redes_sociais'] = json_encode($data['redes_sociais']);
        }
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao atualizar proponente.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('update', $id, $current_data, $data);
        
        do_action('ft1_cultural_proponente_updated', $id, $data, $current_data);
        
        return true;
    }
    
    /**
     * Get a proponente by ID
     */
    public function get($id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('proponentes');
        
        $proponente = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        
        if ($proponente && !empty($proponente->redes_sociais)) {
            $proponente->redes_sociais = json_decode($proponente->redes_sociais, true);
        }
        
        return $proponente;
    }
    
    /**
     * Get proponente by user ID
     */
    public function get_by_user_id($user_id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('proponentes');
        
        $proponente = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if ($proponente && !empty($proponente->redes_sociais)) {
            $proponente->redes_sociais = json_decode($proponente->redes_sociais, true);
        }
        
        return $proponente;
    }
    
    /**
     * Get proponente by unique code
     */
    public function get_by_code($code) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('proponentes');
        
        $proponente = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE codigo_unico = %s",
            $code
        ));
        
        if ($proponente && !empty($proponente->redes_sociais)) {
            $proponente->redes_sociais = json_decode($proponente->redes_sociais, true);
        }
        
        return $proponente;
    }
    
    /**
     * Search proponentes
     */
    public function search($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'search' => '',
            'tipo' => '',
            'status' => 'ativo',
            'orderby' => 'nome_completo',
            'order' => 'ASC',
            'limit' => 20,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = FT1_Cultural_Database::get_table_name('proponentes');
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['search'])) {
            $where[] = '(nome_completo LIKE %s OR email LIKE %s OR cpf_cnpj LIKE %s OR codigo_unico LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($args['tipo'])) {
            $where[] = 'tipo = %s';
            $where_values[] = $args['tipo'];
        }
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'nome_completo ASC';
        }
        
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $limit_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $results = $wpdb->get_results($query);
        
        // Decode social networks for each result
        foreach ($results as $proponente) {
            if (!empty($proponente->redes_sociais)) {
                $proponente->redes_sociais = json_decode($proponente->redes_sociais, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Generate unique code
     */
    private function generate_unique_code() {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('proponentes');
        
        do {
            $code = 'FT1' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE codigo_unico = %s",
                $code
            ));
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Validate CPF/CNPJ
     */
    private function validate_cpf_cnpj($cpf_cnpj) {
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $cpf_cnpj);
        
        if (strlen($cpf_cnpj) == 11) {
            return $this->validate_cpf($cpf_cnpj);
        } elseif (strlen($cpf_cnpj) == 14) {
            return $this->validate_cnpj($cpf_cnpj);
        }
        
        return false;
    }
    
    /**
     * Validate CPF
     */
    private function validate_cpf($cpf) {
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate CNPJ
     */
    private function validate_cnpj($cnpj) {
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        $weights1 = array(5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2);
        $weights2 = array(6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2);
        
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weights1[$i];
        }
        
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;
        
        if ($cnpj[12] != $digit1) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weights2[$i];
        }
        
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;
        
        return $cnpj[13] == $digit2;
    }
    
    /**
     * Sanitize proponente data
     */
    private function sanitize_data($data) {
        $sanitized = array();
        
        $text_fields = array(
            'nome_completo', 'cpf_cnpj', 'rg_ie', 'telefone', 'whatsapp', 
            'email', 'cep', 'cidade', 'estado', 'pais', 'area_atuacao', 
            'portfolio_url', 'banco', 'agencia', 'conta', 'pix'
        );
        
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        $textarea_fields = array('endereco_completo', 'experiencia_profissional');
        foreach ($textarea_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_textarea_field($data[$field]);
            }
        }
        
        if (isset($data['user_id'])) {
            $sanitized['user_id'] = intval($data['user_id']);
        }
        
        if (isset($data['data_nascimento'])) {
            $sanitized['data_nascimento'] = sanitize_text_field($data['data_nascimento']);
        }
        
        if (isset($data['tipo'])) {
            $allowed_types = array('pessoa_fisica', 'pessoa_juridica');
            $sanitized['tipo'] = in_array($data['tipo'], $allowed_types) ? $data['tipo'] : 'pessoa_fisica';
        }
        
        if (isset($data['tipo_conta'])) {
            $allowed_account_types = array('corrente', 'poupanca');
            $sanitized['tipo_conta'] = in_array($data['tipo_conta'], $allowed_account_types) ? $data['tipo_conta'] : 'corrente';
        }
        
        if (isset($data['status'])) {
            $allowed_status = array('ativo', 'inativo', 'bloqueado');
            $sanitized['status'] = in_array($data['status'], $allowed_status) ? $data['status'] : 'ativo';
        }
        
        if (isset($data['codigo_unico'])) {
            $sanitized['codigo_unico'] = sanitize_text_field($data['codigo_unico']);
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
                'objeto_tipo' => 'proponente',
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
     * Create proponente on user registration
     */
    public function create_proponente_on_user_register($user_id) {
        $user = get_user_by('id', $user_id);
        
        if ($user && in_array('ft1_proponente', $user->roles)) {
            $this->create(array(
                'user_id' => $user_id,
                'nome_completo' => $user->display_name,
                'email' => $user->user_email
            ));
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_create_proponente() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('create_ft1_proponentes')) {
            wp_die(__('Você não tem permissão para criar proponentes.', 'ft1-cultural'));
        }
        
        $data = $_POST['data'] ?? array();
        $result = $this->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array('id' => $result));
    }
    
    public function ajax_update_proponente() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $data = $_POST['data'] ?? array();
        
        $result = $this->update($id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_get_proponente() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $proponente = $this->get($id);
        
        if (!$proponente) {
            wp_send_json_error(__('Proponente não encontrado.', 'ft1-cultural'));
        }
        
        wp_send_json_success($proponente);
    }
    
    public function ajax_search_proponentes() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $args = $_POST['args'] ?? array();
        $proponentes = $this->search($args);
        
        wp_send_json_success($proponentes);
    }
}

