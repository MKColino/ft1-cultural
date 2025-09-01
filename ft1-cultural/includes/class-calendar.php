<?php
/**
 * Calendar management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Calendar {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_ft1_get_calendar_events', array($this, 'get_calendar_events'));
        add_action('wp_ajax_ft1_create_calendar_event', array($this, 'create_calendar_event'));
        add_action('wp_ajax_ft1_update_calendar_event', array($this, 'update_calendar_event'));
        add_action('wp_ajax_ft1_delete_calendar_event', array($this, 'delete_calendar_event'));
        
        // Automatic event creation hooks
        add_action('ft1_edital_created', array($this, 'create_edital_events'), 10, 2);
        add_action('ft1_edital_updated', array($this, 'update_edital_events'), 10, 3);
        add_action('ft1_projeto_approved', array($this, 'create_projeto_events'), 10, 2);
        add_action('ft1_contrato_created', array($this, 'create_contrato_events'), 10, 2);
        
        // Cron hooks for notifications
        add_action('ft1_daily_calendar_check', array($this, 'check_upcoming_events'));
        add_action('init', array($this, 'schedule_daily_check'));
    }
    
    /**
     * Schedule daily calendar check
     */
    public function schedule_daily_check() {
        if (!wp_next_scheduled('ft1_daily_calendar_check')) {
            wp_schedule_event(time(), 'daily', 'ft1_daily_calendar_check');
        }
    }
    
    /**
     * Get calendar events for AJAX request
     */
    public function get_calendar_events() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('view_ft1_calendar')) {
            wp_die(__('Permissões insuficientes.', 'ft1-cultural'));
        }
        
        $start = sanitize_text_field($_POST['start']);
        $end = sanitize_text_field($_POST['end']);
        
        $events = $this->get_events($start, $end);
        
        wp_send_json_success($events);
    }
    
    /**
     * Get events for date range
     */
    public function get_events($start_date, $end_date, $user_id = null) {
        global $wpdb;
        
        $events = array();
        
        // Get edital events
        $edital_events = $this->get_edital_events($start_date, $end_date, $user_id);
        $events = array_merge($events, $edital_events);
        
        // Get projeto events
        $projeto_events = $this->get_projeto_events($start_date, $end_date, $user_id);
        $events = array_merge($events, $projeto_events);
        
        // Get contrato events
        $contrato_events = $this->get_contrato_events($start_date, $end_date, $user_id);
        $events = array_merge($events, $contrato_events);
        
        // Get custom events
        $custom_events = $this->get_custom_events($start_date, $end_date, $user_id);
        $events = array_merge($events, $custom_events);
        
        return apply_filters('ft1_cultural_calendar_events', $events, $start_date, $end_date, $user_id);
    }
    
    /**
     * Get edital events
     */
    private function get_edital_events($start_date, $end_date, $user_id = null) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('editais');
        
        $where_clause = "WHERE (data_inicio BETWEEN %s AND %s) OR (data_fim BETWEEN %s AND %s) OR (data_inicio <= %s AND data_fim >= %s)";
        $where_values = array($start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        
        // Filter by user permissions
        if ($user_id && !user_can($user_id, 'manage_ft1_editais')) {
            $where_clause .= " AND status IN ('publicado', 'em_andamento')";
        }
        
        $editais = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where_clause} ORDER BY data_inicio",
            $where_values
        ));
        
        $events = array();
        
        foreach ($editais as $edital) {
            // Edital start event
            if ($edital->data_inicio >= $start_date && $edital->data_inicio <= $end_date) {
                $events[] = array(
                    'id' => 'edital_start_' . $edital->id,
                    'title' => sprintf(__('Início: %s', 'ft1-cultural'), $edital->titulo),
                    'start' => $edital->data_inicio,
                    'className' => 'edital edital-start',
                    'backgroundColor' => '#3498db',
                    'borderColor' => '#2980b9',
                    'extendedProps' => array(
                        'type' => 'edital',
                        'subtype' => 'start',
                        'edital_id' => $edital->id,
                        'description' => $edital->descricao,
                        'status' => $edital->status
                    )
                );
            }
            
            // Edital end event
            if ($edital->data_fim >= $start_date && $edital->data_fim <= $end_date) {
                $events[] = array(
                    'id' => 'edital_end_' . $edital->id,
                    'title' => sprintf(__('Fim: %s', 'ft1-cultural'), $edital->titulo),
                    'start' => $edital->data_fim,
                    'className' => 'edital edital-end',
                    'backgroundColor' => '#e74c3c',
                    'borderColor' => '#c0392b',
                    'extendedProps' => array(
                        'type' => 'edital',
                        'subtype' => 'end',
                        'edital_id' => $edital->id,
                        'description' => $edital->descricao,
                        'status' => $edital->status
                    )
                );
            }
            
            // Submission deadline warning (7 days before end)
            $warning_date = date('Y-m-d', strtotime($edital->data_fim . ' -7 days'));
            if ($warning_date >= $start_date && $warning_date <= $end_date) {
                $events[] = array(
                    'id' => 'edital_warning_' . $edital->id,
                    'title' => sprintf(__('Prazo Final em 7 dias: %s', 'ft1-cultural'), $edital->titulo),
                    'start' => $warning_date,
                    'className' => 'edital edital-warning',
                    'backgroundColor' => '#f39c12',
                    'borderColor' => '#e67e22',
                    'extendedProps' => array(
                        'type' => 'edital',
                        'subtype' => 'warning',
                        'edital_id' => $edital->id,
                        'description' => __('Prazo final se aproximando', 'ft1-cultural'),
                        'status' => $edital->status
                    )
                );
            }
        }
        
        return $events;
    }
    
    /**
     * Get projeto events
     */
    private function get_projeto_events($start_date, $end_date, $user_id = null) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('projetos');
        
        $where_clause = "WHERE data_submissao BETWEEN %s AND %s";
        $where_values = array($start_date, $end_date);
        
        // Filter by user permissions
        if ($user_id && !user_can($user_id, 'view_ft1_projetos')) {
            // Show only user's own projects
            $proponente_id = $this->get_user_proponente_id($user_id);
            if ($proponente_id) {
                $where_clause .= " AND proponente_id = %d";
                $where_values[] = $proponente_id;
            } else {
                return array(); // User has no projects
            }
        }
        
        $projetos = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, pr.nome as proponente_nome, e.titulo as edital_titulo 
            FROM {$table} p
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('proponentes') . " pr ON p.proponente_id = pr.id
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('editais') . " e ON p.edital_id = e.id
            {$where_clause} ORDER BY p.data_submissao",
            $where_values
        ));
        
        $events = array();
        
        foreach ($projetos as $projeto) {
            $color = $this->get_projeto_color($projeto->status);
            
            $events[] = array(
                'id' => 'projeto_' . $projeto->id,
                'title' => sprintf(__('Projeto: %s', 'ft1-cultural'), $projeto->titulo),
                'start' => $projeto->data_submissao,
                'className' => 'projeto projeto-' . $projeto->status,
                'backgroundColor' => $color['bg'],
                'borderColor' => $color['border'],
                'extendedProps' => array(
                    'type' => 'projeto',
                    'projeto_id' => $projeto->id,
                    'proponente' => $projeto->proponente_nome,
                    'edital' => $projeto->edital_titulo,
                    'status' => $projeto->status,
                    'valor' => $projeto->valor_solicitado
                )
            );
        }
        
        return $events;
    }
    
    /**
     * Get contrato events
     */
    private function get_contrato_events($start_date, $end_date, $user_id = null) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('contratos');
        
        $where_clause = "WHERE (c.created_at BETWEEN %s AND %s) OR (c.data_assinatura BETWEEN %s AND %s)";
        $where_values = array($start_date, $end_date, $start_date, $end_date);
        
        // Filter by user permissions
        if ($user_id && !user_can($user_id, 'view_ft1_contratos')) {
            // Show only user's own contracts
            $proponente_id = $this->get_user_proponente_id($user_id);
            if ($proponente_id) {
                $where_clause .= " AND p.proponente_id = %d";
                $where_values[] = $proponente_id;
            } else {
                return array(); // User has no contracts
            }
        }
        
        $contratos = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.titulo as projeto_titulo, pr.nome as proponente_nome
            FROM {$table} c
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('projetos') . " p ON c.projeto_id = p.id
            LEFT JOIN " . FT1_Cultural_Database::get_table_name('proponentes') . " pr ON p.proponente_id = pr.id
            {$where_clause} ORDER BY c.created_at",
            $where_values
        ));
        
        $events = array();
        
        foreach ($contratos as $contrato) {
            $color = $this->get_contrato_color($contrato->status);
            
            // Contract creation event
            if ($contrato->created_at >= $start_date && $contrato->created_at <= $end_date) {
                $events[] = array(
                    'id' => 'contrato_created_' . $contrato->id,
                    'title' => sprintf(__('Contrato Criado: %s', 'ft1-cultural'), $contrato->projeto_titulo),
                    'start' => substr($contrato->created_at, 0, 10), // Date only
                    'className' => 'contrato contrato-created',
                    'backgroundColor' => $color['bg'],
                    'borderColor' => $color['border'],
                    'extendedProps' => array(
                        'type' => 'contrato',
                        'subtype' => 'created',
                        'contrato_id' => $contrato->id,
                        'projeto' => $contrato->projeto_titulo,
                        'proponente' => $contrato->proponente_nome,
                        'status' => $contrato->status
                    )
                );
            }
            
            // Contract signature event
            if ($contrato->data_assinatura && $contrato->data_assinatura >= $start_date && $contrato->data_assinatura <= $end_date) {
                $events[] = array(
                    'id' => 'contrato_signed_' . $contrato->id,
                    'title' => sprintf(__('Contrato Assinado: %s', 'ft1-cultural'), $contrato->projeto_titulo),
                    'start' => $contrato->data_assinatura,
                    'className' => 'contrato contrato-signed',
                    'backgroundColor' => '#27ae60',
                    'borderColor' => '#229954',
                    'extendedProps' => array(
                        'type' => 'contrato',
                        'subtype' => 'signed',
                        'contrato_id' => $contrato->id,
                        'projeto' => $contrato->projeto_titulo,
                        'proponente' => $contrato->proponente_nome,
                        'status' => $contrato->status
                    )
                );
            }
        }
        
        return $events;
    }
    
    /**
     * Get custom events
     */
    private function get_custom_events($start_date, $end_date, $user_id = null) {
        global $wpdb;
        
        $table = FT1_Cultural_Database::get_table_name('calendar_events');
        
        $where_clause = "WHERE (start_date BETWEEN %s AND %s) OR (end_date BETWEEN %s AND %s) OR (start_date <= %s AND end_date >= %s)";
        $where_values = array($start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        
        // Filter by user permissions
        if ($user_id && !user_can($user_id, 'manage_ft1_calendar')) {
            $where_clause .= " AND (created_by = %d OR visibility = 'public')";
            $where_values[] = $user_id;
        }
        
        $custom_events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where_clause} ORDER BY start_date",
            $where_values
        ));
        
        $events = array();
        
        foreach ($custom_events as $event) {
            $events[] = array(
                'id' => 'custom_' . $event->id,
                'title' => $event->title,
                'start' => $event->start_date,
                'end' => $event->end_date,
                'allDay' => $event->all_day == 1,
                'className' => 'custom-event',
                'backgroundColor' => $event->color ?: '#6c757d',
                'borderColor' => $event->color ?: '#5a6268',
                'extendedProps' => array(
                    'type' => 'custom',
                    'event_id' => $event->id,
                    'description' => $event->description,
                    'location' => $event->location,
                    'visibility' => $event->visibility,
                    'created_by' => $event->created_by
                )
            );
        }
        
        return $events;
    }
    
    /**
     * Create calendar event
     */
    public function create_calendar_event() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('manage_ft1_calendar')) {
            wp_die(__('Permissões insuficientes.', 'ft1-cultural'));
        }
        
        $title = sanitize_text_field($_POST['title']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $all_day = isset($_POST['all_day']) ? 1 : 0;
        $description = sanitize_textarea_field($_POST['description']);
        $location = sanitize_text_field($_POST['location']);
        $color = sanitize_hex_color($_POST['color']);
        $visibility = sanitize_text_field($_POST['visibility']);
        
        global $wpdb;
        
        $result = $wpdb->insert(
            FT1_Cultural_Database::get_table_name('calendar_events'),
            array(
                'title' => $title,
                'description' => $description,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'all_day' => $all_day,
                'location' => $location,
                'color' => $color,
                'visibility' => $visibility,
                'created_by' => get_current_user_id()
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            do_action('ft1_calendar_event_created', $wpdb->insert_id, $_POST);
            wp_send_json_success(array('id' => $wpdb->insert_id));
        } else {
            wp_send_json_error(__('Erro ao criar evento.', 'ft1-cultural'));
        }
    }
    
    /**
     * Update calendar event
     */
    public function update_calendar_event() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('manage_ft1_calendar')) {
            wp_die(__('Permissões insuficientes.', 'ft1-cultural'));
        }
        
        $event_id = intval($_POST['event_id']);
        $title = sanitize_text_field($_POST['title']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $all_day = isset($_POST['all_day']) ? 1 : 0;
        $description = sanitize_textarea_field($_POST['description']);
        $location = sanitize_text_field($_POST['location']);
        $color = sanitize_hex_color($_POST['color']);
        $visibility = sanitize_text_field($_POST['visibility']);
        
        global $wpdb;
        
        $result = $wpdb->update(
            FT1_Cultural_Database::get_table_name('calendar_events'),
            array(
                'title' => $title,
                'description' => $description,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'all_day' => $all_day,
                'location' => $location,
                'color' => $color,
                'visibility' => $visibility,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $event_id),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('ft1_calendar_event_updated', $event_id, $_POST);
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao atualizar evento.', 'ft1-cultural'));
        }
    }
    
    /**
     * Delete calendar event
     */
    public function delete_calendar_event() {
        check_ajax_referer('ft1_cultural_nonce', 'nonce');
        
        if (!current_user_can('manage_ft1_calendar')) {
            wp_die(__('Permissões insuficientes.', 'ft1-cultural'));
        }
        
        $event_id = intval($_POST['event_id']);
        
        global $wpdb;
        
        $result = $wpdb->delete(
            FT1_Cultural_Database::get_table_name('calendar_events'),
            array('id' => $event_id),
            array('%d')
        );
        
        if ($result) {
            do_action('ft1_calendar_event_deleted', $event_id);
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao excluir evento.', 'ft1-cultural'));
        }
    }
    
    /**
     * Create edital events automatically
     */
    public function create_edital_events($edital_id, $edital_data) {
        // Events are created dynamically in get_edital_events()
        // This hook can be used for additional processing
        do_action('ft1_edital_calendar_events_created', $edital_id, $edital_data);
    }
    
    /**
     * Update edital events automatically
     */
    public function update_edital_events($edital_id, $old_data, $new_data) {
        // Events are updated dynamically in get_edital_events()
        // This hook can be used for additional processing
        do_action('ft1_edital_calendar_events_updated', $edital_id, $old_data, $new_data);
    }
    
    /**
     * Create projeto events automatically
     */
    public function create_projeto_events($projeto_id, $projeto_data) {
        // Events are created dynamically in get_projeto_events()
        // This hook can be used for additional processing
        do_action('ft1_projeto_calendar_events_created', $projeto_id, $projeto_data);
    }
    
    /**
     * Create contrato events automatically
     */
    public function create_contrato_events($contrato_id, $contrato_data) {
        // Events are created dynamically in get_contrato_events()
        // This hook can be used for additional processing
        do_action('ft1_contrato_calendar_events_created', $contrato_id, $contrato_data);
    }
    
    /**
     * Check upcoming events and send notifications
     */
    public function check_upcoming_events() {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $next_week = date('Y-m-d', strtotime('+7 days'));
        
        // Check for events tomorrow
        $tomorrow_events = $this->get_events($tomorrow, $tomorrow);
        foreach ($tomorrow_events as $event) {
            $this->send_event_notification($event, 'tomorrow');
        }
        
        // Check for events next week
        $next_week_events = $this->get_events($next_week, $next_week);
        foreach ($next_week_events as $event) {
            $this->send_event_notification($event, 'next_week');
        }
        
        // Check for overdue items
        $this->check_overdue_items();
    }
    
    /**
     * Send event notification
     */
    private function send_event_notification($event, $timing) {
        $notification_class = FT1_Cultural_Notifications::instance();
        
        $message_data = array(
            'event' => $event,
            'timing' => $timing,
            'url' => admin_url('admin.php?page=ft1-cultural-calendar')
        );
        
        switch ($event['extendedProps']['type']) {
            case 'edital':
                $this->send_edital_notification($event, $timing, $notification_class);
                break;
                
            case 'projeto':
                $this->send_projeto_notification($event, $timing, $notification_class);
                break;
                
            case 'contrato':
                $this->send_contrato_notification($event, $timing, $notification_class);
                break;
                
            case 'custom':
                $this->send_custom_event_notification($event, $timing, $notification_class);
                break;
        }
    }
    
    /**
     * Send edital notification
     */
    private function send_edital_notification($event, $timing, $notification_class) {
        $edital_id = $event['extendedProps']['edital_id'];
        $subtype = $event['extendedProps']['subtype'];
        
        $recipients = array();
        
        // Get administrators and managers
        $admins = get_users(array('role__in' => array('administrator', 'ft1_administrator', 'ft1_manager')));
        foreach ($admins as $admin) {
            $recipients[] = $admin->ID;
        }
        
        // Get proponentes if it's a deadline warning
        if ($subtype === 'warning' || $subtype === 'end') {
            $proponentes = get_users(array('role' => 'ft1_proponente'));
            foreach ($proponentes as $proponente) {
                $recipients[] = $proponente->ID;
            }
        }
        
        $message_key = "edital_{$subtype}_{$timing}";
        
        foreach ($recipients as $user_id) {
            $notification_class->send_notification($user_id, $message_key, array(
                'edital_id' => $edital_id,
                'event_title' => $event['title'],
                'event_date' => $event['start']
            ));
        }
    }
    
    /**
     * Send projeto notification
     */
    private function send_projeto_notification($event, $timing, $notification_class) {
        $projeto_id = $event['extendedProps']['projeto_id'];
        
        // Get project evaluators and managers
        $recipients = get_users(array('role__in' => array('administrator', 'ft1_administrator', 'ft1_manager', 'ft1_evaluator')));
        
        $message_key = "projeto_submission_{$timing}";
        
        foreach ($recipients as $user) {
            $notification_class->send_notification($user->ID, $message_key, array(
                'projeto_id' => $projeto_id,
                'event_title' => $event['title'],
                'event_date' => $event['start']
            ));
        }
    }
    
    /**
     * Send contrato notification
     */
    private function send_contrato_notification($event, $timing, $notification_class) {
        $contrato_id = $event['extendedProps']['contrato_id'];
        $subtype = $event['extendedProps']['subtype'];
        
        // Get contract managers
        $recipients = get_users(array('role__in' => array('administrator', 'ft1_administrator', 'ft1_manager')));
        
        $message_key = "contrato_{$subtype}_{$timing}";
        
        foreach ($recipients as $user) {
            $notification_class->send_notification($user->ID, $message_key, array(
                'contrato_id' => $contrato_id,
                'event_title' => $event['title'],
                'event_date' => $event['start']
            ));
        }
    }
    
    /**
     * Send custom event notification
     */
    private function send_custom_event_notification($event, $timing, $notification_class) {
        $event_id = $event['extendedProps']['event_id'];
        $visibility = $event['extendedProps']['visibility'];
        $created_by = $event['extendedProps']['created_by'];
        
        $recipients = array();
        
        if ($visibility === 'public') {
            // Send to all users with calendar access
            $recipients = get_users(array('meta_key' => 'wp_capabilities', 'meta_value' => 'view_ft1_calendar', 'meta_compare' => 'LIKE'));
        } else {
            // Send only to creator
            $recipients = array(get_user_by('id', $created_by));
        }
        
        $message_key = "custom_event_{$timing}";
        
        foreach ($recipients as $user) {
            if ($user) {
                $notification_class->send_notification($user->ID, $message_key, array(
                    'event_id' => $event_id,
                    'event_title' => $event['title'],
                    'event_date' => $event['start']
                ));
            }
        }
    }
    
    /**
     * Check for overdue items
     */
    private function check_overdue_items() {
        $today = date('Y-m-d');
        
        // Check overdue editais
        global $wpdb;
        
        $overdue_editais = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . FT1_Cultural_Database::get_table_name('editais') . "
            WHERE data_fim < %s AND status IN ('publicado', 'em_andamento')",
            $today
        ));
        
        foreach ($overdue_editais as $edital) {
            // Update status to finalizado
            $wpdb->update(
                FT1_Cultural_Database::get_table_name('editais'),
                array('status' => 'finalizado'),
                array('id' => $edital->id),
                array('%s'),
                array('%d')
            );
            
            // Send notification
            $notification_class = FT1_Cultural_Notifications::instance();
            $admins = get_users(array('role__in' => array('administrator', 'ft1_administrator', 'ft1_manager')));
            
            foreach ($admins as $admin) {
                $notification_class->send_notification($admin->ID, 'edital_overdue', array(
                    'edital_id' => $edital->id,
                    'edital_title' => $edital->titulo
                ));
            }
        }
    }
    
    /**
     * Get projeto color based on status
     */
    private function get_projeto_color($status) {
        $colors = array(
            'rascunho' => array('bg' => '#6c757d', 'border' => '#5a6268'),
            'enviado' => array('bg' => '#17a2b8', 'border' => '#138496'),
            'em_analise' => array('bg' => '#ffc107', 'border' => '#e0a800'),
            'aprovado' => array('bg' => '#28a745', 'border' => '#1e7e34'),
            'rejeitado' => array('bg' => '#dc3545', 'border' => '#bd2130'),
            'em_execucao' => array('bg' => '#007bff', 'border' => '#0056b3'),
            'finalizado' => array('bg' => '#6f42c1', 'border' => '#59359a')
        );
        
        return isset($colors[$status]) ? $colors[$status] : $colors['rascunho'];
    }
    
    /**
     * Get contrato color based on status
     */
    private function get_contrato_color($status) {
        $colors = array(
            'rascunho' => array('bg' => '#6c757d', 'border' => '#5a6268'),
            'enviado' => array('bg' => '#ffc107', 'border' => '#e0a800'),
            'assinado' => array('bg' => '#28a745', 'border' => '#1e7e34'),
            'cancelado' => array('bg' => '#dc3545', 'border' => '#bd2130')
        );
        
        return isset($colors[$status]) ? $colors[$status] : $colors['rascunho'];
    }
    
    /**
     * Get user's proponente ID
     */
    private function get_user_proponente_id($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . FT1_Cultural_Database::get_table_name('proponentes') . " WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get calendar statistics
     */
    public function get_calendar_stats($start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-01'); // First day of current month
        }
        
        if (!$end_date) {
            $end_date = date('Y-m-t'); // Last day of current month
        }
        
        $events = $this->get_events($start_date, $end_date);
        
        $stats = array(
            'total_events' => count($events),
            'events_by_type' => array(),
            'upcoming_deadlines' => 0,
            'overdue_items' => 0
        );
        
        foreach ($events as $event) {
            $type = $event['extendedProps']['type'];
            $stats['events_by_type'][$type] = ($stats['events_by_type'][$type] ?? 0) + 1;
            
            // Count upcoming deadlines (next 7 days)
            $event_date = strtotime($event['start']);
            $next_week = strtotime('+7 days');
            
            if ($event_date <= $next_week && $event_date >= time()) {
                if (isset($event['extendedProps']['subtype']) && 
                    in_array($event['extendedProps']['subtype'], array('end', 'warning'))) {
                    $stats['upcoming_deadlines']++;
                }
            }
            
            // Count overdue items
            if ($event_date < time()) {
                if (isset($event['extendedProps']['subtype']) && $event['extendedProps']['subtype'] === 'end') {
                    $stats['overdue_items']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Export calendar events
     */
    public function export_calendar($format = 'ics', $start_date = null, $end_date = null, $user_id = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-1 month'));
        }
        
        if (!$end_date) {
            $end_date = date('Y-m-d', strtotime('+1 year'));
        }
        
        $events = $this->get_events($start_date, $end_date, $user_id);
        
        switch ($format) {
            case 'ics':
                return $this->export_to_ics($events);
                
            case 'json':
                return wp_json_encode($events);
                
            case 'csv':
                return $this->export_to_csv($events);
                
            default:
                return false;
        }
    }
    
    /**
     * Export events to ICS format
     */
    private function export_to_ics($events) {
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//FT1 Cultural//Calendar//PT\r\n";
        $ics_content .= "CALSCALE:GREGORIAN\r\n";
        
        foreach ($events as $event) {
            $ics_content .= "BEGIN:VEVENT\r\n";
            $ics_content .= "UID:" . $event['id'] . "@ft1cultural\r\n";
            $ics_content .= "DTSTART:" . date('Ymd\THis\Z', strtotime($event['start'])) . "\r\n";
            
            if (isset($event['end'])) {
                $ics_content .= "DTEND:" . date('Ymd\THis\Z', strtotime($event['end'])) . "\r\n";
            }
            
            $ics_content .= "SUMMARY:" . $this->escape_ics_text($event['title']) . "\r\n";
            
            if (isset($event['extendedProps']['description'])) {
                $ics_content .= "DESCRIPTION:" . $this->escape_ics_text($event['extendedProps']['description']) . "\r\n";
            }
            
            if (isset($event['extendedProps']['location'])) {
                $ics_content .= "LOCATION:" . $this->escape_ics_text($event['extendedProps']['location']) . "\r\n";
            }
            
            $ics_content .= "END:VEVENT\r\n";
        }
        
        $ics_content .= "END:VCALENDAR\r\n";
        
        return $ics_content;
    }
    
    /**
     * Export events to CSV format
     */
    private function export_to_csv($events) {
        $csv_content = "Title,Start Date,End Date,Type,Description,Location\n";
        
        foreach ($events as $event) {
            $row = array(
                $event['title'],
                $event['start'],
                $event['end'] ?? '',
                $event['extendedProps']['type'] ?? '',
                $event['extendedProps']['description'] ?? '',
                $event['extendedProps']['location'] ?? ''
            );
            
            $csv_content .= '"' . implode('","', array_map('str_replace', array_fill(0, count($row), '"'), array_fill(0, count($row), '""'), $row)) . '"' . "\n";
        }
        
        return $csv_content;
    }
    
    /**
     * Escape text for ICS format
     */
    private function escape_ics_text($text) {
        $text = str_replace(array("\r\n", "\n", "\r"), "\\n", $text);
        $text = str_replace(array("\\", ";", ","), array("\\\\", "\\;", "\\,"), $text);
        return $text;
    }
}

