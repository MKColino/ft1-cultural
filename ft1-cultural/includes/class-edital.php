<?php
/**
 * Edital management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Edital {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_ft1_create_edital', array($this, 'ajax_create_edital'));
        add_action('wp_ajax_ft1_update_edital', array($this, 'ajax_update_edital'));
        add_action('wp_ajax_ft1_delete_edital', array($this, 'ajax_delete_edital'));
        add_action('wp_ajax_ft1_get_editais', array($this, 'ajax_get_editais'));
    }
    
    /**
     * Create a new edital
     */
    public function create($data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        $defaults = array(
            'titulo' => '',
            'descricao' => '',
            'data_inicio' => '',
            'data_fim' => '',
            'data_resultado' => null,
            'valor_total' => 0,
            'status' => 'rascunho',
            'regulamento' => '',
            'criterios_avaliacao' => '',
            'documentos_necessarios' => '',
            'created_by' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        $required_fields = array('titulo', 'data_inicio', 'data_fim');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('O campo %s é obrigatório.', 'ft1-cultural'), $field));
            }
        }
        
        // Validate dates
        if (strtotime($data['data_inicio']) >= strtotime($data['data_fim'])) {
            return new WP_Error('invalid_dates', __('A data de início deve ser anterior à data de fim.', 'ft1-cultural'));
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        $result = $wpdb->insert(
            $table,
            $data,
            array(
                '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d'
            )
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao criar edital.', 'ft1-cultural'));
        }
        
        $edital_id = $wpdb->insert_id;
        
        // Log the action
        $this->log_action('create', $edital_id, null, $data);
        
        do_action('ft1_cultural_edital_created', $edital_id, $data);
        
        return $edital_id;
    }
    
    /**
     * Update an existing edital
     */
    public function update($id, $data) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        // Get current data for logging
        $current_data = $this->get($id);
        if (!$current_data) {
            return new WP_Error('not_found', __('Edital não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        if (!current_user_can('edit_ft1_editais') && $current_data->created_by != get_current_user_id()) {
            return new WP_Error('permission_denied', __('Você não tem permissão para editar este edital.', 'ft1-cultural'));
        }
        
        // Validate dates if provided
        if (isset($data['data_inicio']) && isset($data['data_fim'])) {
            if (strtotime($data['data_inicio']) >= strtotime($data['data_fim'])) {
                return new WP_Error('invalid_dates', __('A data de início deve ser anterior à data de fim.', 'ft1-cultural'));
            }
        }
        
        // Sanitize data
        $data = $this->sanitize_data($data);
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao atualizar edital.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('update', $id, $current_data, $data);
        
        do_action('ft1_cultural_edital_updated', $id, $data, $current_data);
        
        return true;
    }
    
    /**
     * Delete an edital
     */
    public function delete($id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        // Get current data for logging
        $current_data = $this->get($id);
        if (!$current_data) {
            return new WP_Error('not_found', __('Edital não encontrado.', 'ft1-cultural'));
        }
        
        // Check permissions
        if (!current_user_can('delete_ft1_editais') && $current_data->created_by != get_current_user_id()) {
            return new WP_Error('permission_denied', __('Você não tem permissão para excluir este edital.', 'ft1-cultural'));
        }
        
        // Check if there are projects associated
        $projects_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . FT1_Cultural_Database::get_table_name('projetos') . " WHERE edital_id = %d",
            $id
        ));
        
        if ($projects_count > 0) {
            return new WP_Error('has_projects', __('Não é possível excluir um edital que possui projetos associados.', 'ft1-cultural'));
        }
        
        $result = $wpdb->delete(
            $table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao excluir edital.', 'ft1-cultural'));
        }
        
        // Log the action
        $this->log_action('delete', $id, $current_data, null);
        
        do_action('ft1_cultural_edital_deleted', $id, $current_data);
        
        return true;
    }
    
    /**
     * Get an edital by ID
     */
    public function get($id) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get editais with filters
     */
    public function get_editais($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
            'created_by' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where[] = '(titulo LIKE %s OR descricao LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($args['created_by'])) {
            $where[] = 'created_by = %d';
            $where_values[] = $args['created_by'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }
        
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $limit_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get editais count
     */
    public function get_count($args = array()) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where[] = '(titulo LIKE %s OR descricao LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($args['created_by'])) {
            $where[] = 'created_by = %d';
            $where_values[] = $args['created_by'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Get editais for calendar
     */
    public function get_calendar_events($start_date = null, $end_date = null) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        $where = array("status != 'rascunho'");
        $where_values = array();
        
        if ($start_date) {
            $where[] = 'data_fim >= %s';
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'data_inicio <= %s';
            $where_values[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT id, titulo, data_inicio, data_fim, data_resultado, status FROM $table WHERE $where_clause ORDER BY data_inicio ASC";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Sanitize edital data
     */
    private function sanitize_data($data) {
        $sanitized = array();
        
        if (isset($data['titulo'])) {
            $sanitized['titulo'] = sanitize_text_field($data['titulo']);
        }
        
        if (isset($data['descricao'])) {
            $sanitized['descricao'] = wp_kses_post($data['descricao']);
        }
        
        if (isset($data['data_inicio'])) {
            $sanitized['data_inicio'] = sanitize_text_field($data['data_inicio']);
        }
        
        if (isset($data['data_fim'])) {
            $sanitized['data_fim'] = sanitize_text_field($data['data_fim']);
        }
        
        if (isset($data['data_resultado'])) {
            $sanitized['data_resultado'] = sanitize_text_field($data['data_resultado']);
        }
        
        if (isset($data['valor_total'])) {
            $sanitized['valor_total'] = floatval($data['valor_total']);
        }
        
        if (isset($data['status'])) {
            $allowed_status = array('rascunho', 'publicado', 'em_andamento', 'finalizado', 'cancelado');
            $sanitized['status'] = in_array($data['status'], $allowed_status) ? $data['status'] : 'rascunho';
        }
        
        if (isset($data['regulamento'])) {
            $sanitized['regulamento'] = wp_kses_post($data['regulamento']);
        }
        
        if (isset($data['criterios_avaliacao'])) {
            $sanitized['criterios_avaliacao'] = wp_kses_post($data['criterios_avaliacao']);
        }
        
        if (isset($data['documentos_necessarios'])) {
            $sanitized['documentos_necessarios'] = wp_kses_post($data['documentos_necessarios']);
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
                'objeto_tipo' => 'edital',
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
    public function ajax_create_edital() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('create_ft1_editais')) {
            wp_die(__('Você não tem permissão para criar editais.', 'ft1-cultural'));
        }
        
        $data = $_POST['data'] ?? array();
        $result = $this->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array('id' => $result));
    }
    
    public function ajax_update_edital() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $data = $_POST['data'] ?? array();
        
        $result = $this->update($id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_delete_edital() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        
        $result = $this->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_get_editais() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        $args = $_POST['args'] ?? array();
        $editais = $this->get_editais($args);
        $total = $this->get_count($args);
        
        wp_send_json_success(array(
            'editais' => $editais,
            'total' => $total
        ));
    }
}

