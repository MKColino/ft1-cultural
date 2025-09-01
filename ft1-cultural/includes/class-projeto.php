<?php
/**
 * Projeto management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Projeto {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_ft1_create_projeto', array($this, 'ajax_create_projeto'));
        add_action('wp_ajax_ft1_update_projeto', array($this, 'ajax_update_projeto'));
        add_action('wp_ajax_ft1_get_projeto', array($this, 'ajax_get_projeto'));
        add_action('wp_ajax_ft1_get_projetos', array($this, 'ajax_get_projetos'));
        add_action('wp_ajax_ft1_avaliar_projeto', array($this, 'ajax_avaliar_projeto'));
        add_action('wp_ajax_ft1_submit_projeto', array($this, 'ajax_submit_projeto'));
    }
    
    /**
     * Create a new projeto
     */
    public function create($data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('projetos');
        
        $defaults = array(
            'edital_id' => 0,
            'proponente_id' => 0,
            'titulo' => '',
            'descricao' => '',
            'objetivos' => '',
            'justificativa' => '',
            'metodologia' => '',
            'cronograma' => '',
            'orcamento' => '',
            'valor_solicitado' => 0,
            'valor_aprovado' => 0,
            'contrapartida' => 0,
            'status' => 'rascunho',
            'parecer_tecnico' => '',
            'nota_avaliacao' => null,
            'observacoes' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Generate unique project code
        $data['codigo_projeto'] = $this->generate_project_code($data['edital_id']);
        
        // Validate required fields
        $required_fields = array('edital_id', 'proponente_id', 'titulo', 'valor_solicitado');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('O campo %s é obrigatório.', 'ft1-cultural'), $field));
            }
        }
        
        // Validate edital exists and is active
        $edital = FT1_Cultural_Edital::instance()->get($data['edital_id']);
        if (!$edital) {
            return new WP_Error('invalid_edital', __('Edital não encontrado.', 'ft1-cultural'));
        }
        
        if (!in_array($edital->status, array('publicado', 'em_andamento'))) {
            return new WP_Error('edital_not_active', __('Edital não está ativo para submissões.', 'ft1-cultural'));
        }
        
        // Check if submission period is valid
        $now = current_time('mysql');
        if ($now < $edital->data_inicio || $now > $edital->data_fim) {
            return new WP_Error('submission_period_invalid', __('Fora do período de submissão do edital.', 'ft1-cultural'));
        }
        
        // Validate proponente exists
        $proponente = FT1_Cultural_Proponente::instance()->get($data['proponente_id']);
        if (!$proponente) {
            return new WP_Error('invalid_proponente', __('Proponente não encontrado.', 'ft1-cultural'));
        }
        
        // Check if proponente already has a project for this edital
        $existing_project = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE edital_id = %d AND proponente_id = %d",
            $data['edital_id'],
            $data['proponente_id']
        ));
        
        if ($existing_project) {
            return new WP_Error('project_exists', __('Proponente já possui um projeto para este edital.', 'ft1-cultural'));
        }
        
        // Validate valor_solicitado
        if ($data['valor_solicitado'] <= 0) {
            return new WP_Error('invalid_value', __('Valor solicitado deve ser maior que zero.', 'ft1-cultural'));
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        // Serialize complex fields
        if (isset($data['cronograma']) && is_array($data['cronograma'])) {
            $data['cronograma'] = json_encode($data['cronograma']);
        }
        
        if (isset($data['orcamento']) && is_array($data['orcamento'])) {
            $data['orcamento'] = json_encode($data['orcamento']);
        }
        
        $result = $wpdb->insert(
            $table,
            $data,
            array(
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%f', '%f', '%f', '%s', '%s', '%f', '%s', '%s', '%d', '%s'
            )
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao criar projeto.', 'ft1-cultural'));
        }
        
        $projeto_id = $wpdb->insert_id;
        
        // Log the action
        $this->log_action('create', $projeto_id, null, $data);
        
        do_action('ft1_cultural_projeto_created', $projeto_id, $data);
        
        return $projeto_id;
    }
    
    /**
     * Update an existing projeto
     */
    public function update($id, $data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('projetos');
        
        // Get current data for logging
        $current_data = $this->get($id);
        if (!$current_data) {
            return new WP_Error('not_found', __('Projeto não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        $proponente = FT1_Cultural_Proponente::instance()->get_by_user_id(get_current_user_id());
        if (!current_user_can('edit_ft1_projetos') && 
            (!$proponente || $current_data->proponente_id != $proponente->id)) {
            return new WP_Error('permission_denied', __('Você não tem permissão para editar este projeto.', 'ft1-cultural'));
        }
        
        // Check if project can be edited
        if (in_array($current_data->status, array('enviado', 'em_analise', 'aprovado', 'em_execucao', 'finalizado'))) {
            if (!current_user_can('edit_ft1_projetos')) {
                return new WP_Error('cannot_edit', __('Projeto não pode ser editado no status atual.', 'ft1-cultural'));
            }
        }
        
        // Validate valor_solicitado if provided
        if (isset($data['valor_solicitado']) && $data['valor_solicitado'] <= 0) {
            return new WP_Error('invalid_value', __('Valor solicitado deve ser maior que zero.', 'ft1-cultural'));
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        // Serialize complex fields
        if (isset($data['cronograma']) && is_array($data['cronograma'])) {
            $data['cronograma'] = json_encode($data['cronograma']);
        }
        
        if (isset($data['orcamento']) && is_array($data['orcamento'])) {
            $data['orcamento'] = json_encode($data['orcamento']);
        }
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao atualizar projeto.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('update', $id, $current_data, $data);
        
        do_action('ft1_cultural_projeto_updated', $id, $data, $current_data);
        
        return true;
    }
    
    /**
     * Submit project for evaluation
     */
    public function submit($id) {
        global $wpdb;
        
        $projeto = $this->get($id);
        if (!$projeto) {
            return new WP_Error('not_found', __('Projeto não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        $proponente = FT1_Cultural_Proponente::instance()->get_by_user_id(get_current_user_id());
        if (!$proponente || $projeto->proponente_id != $proponente->id) {
            return new WP_Error('permission_denied', __('Você não tem permissão para submeter este projeto.', 'ft1-cultural'));
        }
        
        // Check if project can be submitted
        if ($projeto->status != 'rascunho') {
            return new WP_Error('cannot_submit', __('Projeto não pode ser submetido no status atual.', 'ft1-cultural'));
        }
        
        // Validate required fields for submission
        $required_fields = array('titulo', 'descricao', 'objetivos', 'justificativa', 'metodologia');
        foreach ($required_fields as $field) {
            if (empty($projeto->$field)) {
                return new WP_Error('incomplete_project', sprintf(__('Campo obrigatório não preenchido: %s', 'ft1-cultural'), $field));
            }
        }
        
        // Check if all required documents are uploaded
        $required_docs = $this->get_required_documents($projeto->edital_id);
        foreach ($required_docs as $doc_type) {
            $doc_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('documentos') . " 
                WHERE relacionado_tipo = 'projeto' AND relacionado_id = %d AND categoria = %s",
                $id,
                $doc_type
            ));
            
            if ($doc_count == 0) {
                return new WP_Error('missing_documents', sprintf(__('Documento obrigatório não enviado: %s', 'ft1-cultural'), $doc_type));
            }
        }
        
        // Update project status
        $result = $wpdb->update(
            FT1_Cultural_Database::get_table_name('projetos'),
            array(
                'status' => 'enviado',
                'data_submissao' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao submeter projeto.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('submit', $id, $projeto, array('status' => 'enviado'));
        
        // Send notification
        do_action('ft1_cultural_projeto_submitted', $id, $projeto);
        
        return true;
    }
    
    /**
     * Evaluate project
     */
    public function evaluate($id, $evaluation_data) {
        global $wpdb;
        
        if (!current_user_can('evaluate_ft1_projetos')) {
            return new WP_Error('permission_denied', __('Você não tem permissão para avaliar projetos.', 'ft1-cultural'));
        }
        
        $projeto = $this->get($id);
        if (!$projeto) {
            return new WP_Error('not_found', __('Projeto não encontrado.', 'ft1-cultural'));
        }
        
        // Check if project can be evaluated
        if (!in_array($projeto->status, array('enviado', 'em_analise'))) {
            return new WP_Error('cannot_evaluate', __('Projeto não pode ser avaliado no status atual.', 'ft1-cultural'));
        }
        
        $defaults = array(
            'status' => 'aprovado',
            'parecer_tecnico' => '',
            'nota_avaliacao' => null,
            'valor_aprovado' => 0,
            'observacoes' => ''
        );
        
        $evaluation_data = wp_parse_args($evaluation_data, $defaults);
        
        // Validate status
        $allowed_status = array('aprovado', 'reprovado');
        if (!in_array($evaluation_data['status'], $allowed_status)) {
            return new WP_Error('invalid_status', __('Status de avaliação inválido.', 'ft1-cultural'));
        }
        
        // Validate nota_avaliacao
        if ($evaluation_data['nota_avaliacao'] !== null) {
            $nota = floatval($evaluation_data['nota_avaliacao']);
            if ($nota < 0 || $nota > 10) {
                return new WP_Error('invalid_grade', __('Nota deve estar entre 0 e 10.', 'ft1-cultural'));
            }
            $evaluation_data['nota_avaliacao'] = $nota;
        }
        
        // Validate valor_aprovado
        if ($evaluation_data['status'] == 'aprovado') {
            $valor_aprovado = floatval($evaluation_data['valor_aprovado']);
            if ($valor_aprovado <= 0 || $valor_aprovado > $projeto->valor_solicitado) {
                return new WP_Error('invalid_approved_value', __('Valor aprovado deve ser maior que zero e não pode exceder o valor solicitado.', 'ft1-cultural'));
            }
            $evaluation_data['valor_aprovado'] = $valor_aprovado;
        }
        
        // Sanitize data
        $evaluation_data = $this->sanitize_data($evaluation_data);
        
        // Add evaluation metadata
        $evaluation_data['data_avaliacao'] = current_time('mysql');
        $evaluation_data['avaliado_por'] = get_current_user_id();
        
        $result = $wpdb->update(
            FT1_Cultural_Database::get_table_name('projetos'),
            $evaluation_data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao avaliar projeto.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('evaluate', $id, $projeto, $evaluation_data);
        
        // Send notification
        do_action('ft1_cultural_projeto_evaluated', $id, $evaluation_data, $projeto);
        
        return true;
    }
    
    /**
     * Get a projeto by ID
     */
    public function get($id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('projetos');
        
        $projeto = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, e.titulo as edital_titulo, pr.nome_completo as proponente_nome, pr.codigo_unico as proponente_codigo
            FROM $table p
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('editais') . " e ON p.edital_id = e.id
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('proponentes') . " pr ON p.proponente_id = pr.id
            WHERE p.id = %d",
            $id
        ));
        
        if ($projeto) {
            // Decode JSON fields
            if (!empty($projeto->cronograma)) {
                $projeto->cronograma = json_decode($projeto->cronograma, true);
            }
            
            if (!empty($projeto->orcamento)) {
                $projeto->orcamento = json_decode($projeto->orcamento, true);
            }
        }
        
        return $projeto;
    }
    
    /**
     * Get projetos with filters
     */
    public function get_projetos($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'edital_id' => '',
            'proponente_id' => '',
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = FT1_Cultural_Database::get_table_name('projetos');
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['edital_id'])) {
            $where[] = 'p.edital_id = %d';
            $where_values[] = $args['edital_id'];
        }
        
        if (!empty($args['proponente_id'])) {
            $where[] = 'p.proponente_id = %d';
            $where_values[] = $args['proponente_id'];
        }
        
        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "p.status IN ($placeholders)";
                $where_values = array_merge($where_values, $args['status']);
            } else {
                $where[] = 'p.status = %s';
                $where_values[] = $args['status'];
            }
        }
        
        if (!empty($args['search'])) {
            $where[] = '(p.titulo LIKE %s OR p.descricao LIKE %s OR p.codigo_projeto LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'p.created_at DESC';
        }
        
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $query = "SELECT p.*, e.titulo as edital_titulo, pr.nome_completo as proponente_nome, pr.codigo_unico as proponente_codigo
                  FROM $table p
                  LEFT JOIN " . FT1_Cultural_Database::get_table_name('editais') . " e ON p.edital_id = e.id
                  LEFT JOIN " . FT1_Cultural_Database::get_table_name('proponentes') . " pr ON p.proponente_id = pr.id
                  WHERE $where_clause ORDER BY $orderby $limit_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $results = $wpdb->get_results($query);
        
        // Decode JSON fields for each result
        foreach ($results as $projeto) {
            if (!empty($projeto->cronograma)) {
                $projeto->cronograma = json_decode($projeto->cronograma, true);
            }
            
            if (!empty($projeto->orcamento)) {
                $projeto->orcamento = json_decode($projeto->orcamento, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get projetos count
     */
    public function get_count($args = array()) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('projetos');
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['edital_id'])) {
            $where[] = 'edital_id = %d';
            $where_values[] = $args['edital_id'];
        }
        
        if (!empty($args['proponente_id'])) {
            $where[] = 'proponente_id = %d';
            $where_values[] = $args['proponente_id'];
        }
        
        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ($placeholders)";
                $where_values = array_merge($where_values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $where_values[] = $args['status'];
            }
        }
        
        if (!empty($args['search'])) {
            $where[] = '(titulo LIKE %s OR descricao LIKE %s OR codigo_projeto LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Generate project code
     */
    private function generate_project_code($edital_id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('projetos');
        
        // Get edital year
        $edital = FT1_Cultural_Edital::instance()->get($edital_id);
        $year = date('Y', strtotime($edital->data_inicio));
        
        // Get next sequential number for this edital
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE edital_id = %d",
            $edital_id
        ));
        
        $sequential = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        return "FT1-{$year}-{$edital_id}-{$sequential}";
    }
    
    /**
     * Get required documents for edital
     */
    private function get_required_documents($edital_id) {
        // This would typically come from edital configuration
        // For now, return default required documents
        return array(
            'projeto_detalhado',
            'orcamento_detalhado',
            'cronograma_execucao',
            'curriculo_proponente'
        );
    }
    
    /**
     * Sanitize projeto data
     */
    private function sanitize_data($data) {
        $sanitized = array();
        
        $text_fields = array('titulo', 'codigo_projeto');
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        $textarea_fields = array('descricao', 'objetivos', 'justificativa', 'metodologia', 'parecer_tecnico', 'observacoes');
        foreach ($textarea_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = wp_kses_post($data[$field]);
            }
        }
        
        $numeric_fields = array('edital_id', 'proponente_id', 'avaliado_por');
        foreach ($numeric_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = intval($data[$field]);
            }
        }
        
        $float_fields = array('valor_solicitado', 'valor_aprovado', 'contrapartida', 'nota_avaliacao');
        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = floatval($data[$field]);
            }
        }
        
        if (isset($data['status'])) {
            $allowed_status = array('rascunho', 'enviado', 'em_analise', 'aprovado', 'reprovado', 'em_execucao', 'finalizado', 'cancelado');
            $sanitized['status'] = in_array($data['status'], $allowed_status) ? $data['status'] : 'rascunho';
        }
        
        $date_fields = array('data_submissao', 'data_avaliacao');
        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
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
                'objeto_tipo' => 'projeto',
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
    public function ajax_create_projeto() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $data = $_POST['data'] ?? array();
        $result = $this->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array('id' => $result));
    }
    
    public function ajax_update_projeto() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $data = $_POST['data'] ?? array();
        
        $result = $this->update($id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_get_projeto() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $projeto = $this->get($id);
        
        if (!$projeto) {
            wp_send_json_error(__('Projeto não encontrado.', 'ft1-cultural'));
        }
        
        wp_send_json_success($projeto);
    }
    
    public function ajax_get_projetos() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $args = $_POST['args'] ?? array();
        $projetos = $this->get_projetos($args);
        $total = $this->get_count($args);
        
        wp_send_json_success(array(
            'projetos' => $projetos,
            'total' => $total
        ));
    }
    
    public function ajax_avaliar_projeto() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $evaluation_data = $_POST['evaluation'] ?? array();
        
        $result = $this->evaluate($id, $evaluation_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_submit_projeto() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        
        $result = $this->submit($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
}

