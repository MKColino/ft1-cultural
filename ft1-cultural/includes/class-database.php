<?php
/**
 * Database management class
 * 
 * @package FT1_Cultural
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT1_Cultural_Database {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'check_version'));
    }
    
    public function check_version() {
        $installed_version = get_option('ft1_cultural_db_version', '0');
        
        if (version_compare($installed_version, FT1_CULTURAL_VERSION, '<')) {
            $this->update_database();
        }
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Editais table
        $table_editais = $wpdb->prefix . 'ft1_editais';
        $sql_editais = "CREATE TABLE $table_editais (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            titulo varchar(255) NOT NULL,
            descricao longtext,
            data_inicio datetime NOT NULL,
            data_fim datetime NOT NULL,
            data_resultado datetime,
            valor_total decimal(15,2) DEFAULT 0,
            status enum('rascunho','publicado','em_andamento','finalizado','cancelado') DEFAULT 'rascunho',
            regulamento longtext,
            criterios_avaliacao longtext,
            documentos_necessarios longtext,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY status (status),
            KEY data_inicio (data_inicio),
            KEY data_fim (data_fim)
        ) $charset_collate;";
        
        // Proponentes table
        $table_proponentes = $wpdb->prefix . 'ft1_proponentes';
        $sql_proponentes = "CREATE TABLE $table_proponentes (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            codigo_unico varchar(20) NOT NULL UNIQUE,
            tipo enum('pessoa_fisica','pessoa_juridica') NOT NULL,
            nome_completo varchar(255) NOT NULL,
            cpf_cnpj varchar(20),
            rg_ie varchar(20),
            data_nascimento date,
            telefone varchar(20),
            whatsapp varchar(20),
            email varchar(100) NOT NULL,
            endereco_completo text,
            cep varchar(10),
            cidade varchar(100),
            estado varchar(2),
            pais varchar(50) DEFAULT 'Brasil',
            area_atuacao varchar(255),
            experiencia_profissional longtext,
            portfolio_url varchar(255),
            redes_sociais longtext,
            banco varchar(100),
            agencia varchar(10),
            conta varchar(20),
            tipo_conta enum('corrente','poupanca'),
            pix varchar(255),
            status enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY codigo_unico (codigo_unico),
            KEY user_id (user_id),
            KEY cpf_cnpj (cpf_cnpj),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";
        
        // Projetos table
        $table_projetos = $wpdb->prefix . 'ft1_projetos';
        $sql_projetos = "CREATE TABLE $table_projetos (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            edital_id mediumint(9) NOT NULL,
            proponente_id mediumint(9) NOT NULL,
            codigo_projeto varchar(30) NOT NULL UNIQUE,
            titulo varchar(255) NOT NULL,
            descricao longtext,
            objetivos longtext,
            justificativa longtext,
            metodologia longtext,
            cronograma longtext,
            orcamento longtext,
            valor_solicitado decimal(15,2) NOT NULL,
            valor_aprovado decimal(15,2) DEFAULT 0,
            contrapartida decimal(15,2) DEFAULT 0,
            status enum('rascunho','enviado','em_analise','aprovado','reprovado','em_execucao','finalizado','cancelado') DEFAULT 'rascunho',
            parecer_tecnico longtext,
            nota_avaliacao decimal(3,2),
            data_submissao datetime,
            data_avaliacao datetime,
            avaliado_por bigint(20) UNSIGNED,
            observacoes longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY codigo_projeto (codigo_projeto),
            KEY edital_id (edital_id),
            KEY proponente_id (proponente_id),
            KEY status (status),
            KEY data_submissao (data_submissao),
            FOREIGN KEY (edital_id) REFERENCES $table_editais(id) ON DELETE CASCADE,
            FOREIGN KEY (proponente_id) REFERENCES $table_proponentes(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Contratos table
        $table_contratos = $wpdb->prefix . 'ft1_contratos';
        $sql_contratos = "CREATE TABLE $table_contratos (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            projeto_id mediumint(9) NOT NULL,
            numero_contrato varchar(50) NOT NULL UNIQUE,
            tipo enum('execucao','prestacao_contas','aditivo') DEFAULT 'execucao',
            conteudo longtext NOT NULL,
            valor decimal(15,2) NOT NULL,
            data_inicio date NOT NULL,
            data_fim date NOT NULL,
            status enum('rascunho','enviado','assinado','vigente','vencido','rescindido') DEFAULT 'rascunho',
            assinado_em datetime,
            assinatura_proponente longtext,
            ip_assinatura varchar(45),
            user_agent text,
            hash_documento varchar(64),
            pdf_path varchar(255),
            enviado_email boolean DEFAULT false,
            enviado_whatsapp boolean DEFAULT false,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY numero_contrato (numero_contrato),
            KEY projeto_id (projeto_id),
            KEY status (status),
            KEY data_inicio (data_inicio),
            KEY data_fim (data_fim),
            FOREIGN KEY (projeto_id) REFERENCES $table_projetos(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Documentos table
        $table_documentos = $wpdb->prefix . 'ft1_documentos';
        $sql_documentos = "CREATE TABLE $table_documentos (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            relacionado_tipo enum('edital','proponente','projeto','contrato') NOT NULL,
            relacionado_id mediumint(9) NOT NULL,
            nome_original varchar(255) NOT NULL,
            nome_arquivo varchar(255) NOT NULL,
            tipo_arquivo varchar(10) NOT NULL,
            tamanho_arquivo bigint(20) NOT NULL,
            caminho_arquivo varchar(500) NOT NULL,
            descricao text,
            categoria varchar(100),
            obrigatorio boolean DEFAULT false,
            validado boolean DEFAULT false,
            validado_por bigint(20) UNSIGNED,
            validado_em datetime,
            observacoes_validacao text,
            uploaded_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY relacionado (relacionado_tipo, relacionado_id),
            KEY uploaded_by (uploaded_by),
            KEY categoria (categoria),
            KEY obrigatorio (obrigatorio),
            KEY validado (validado)
        ) $charset_collate;";
        
        // Notificações table
        $table_notificacoes = $wpdb->prefix . 'ft1_notificacoes';
        $sql_notificacoes = "CREATE TABLE $table_notificacoes (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            tipo enum('email','whatsapp','sistema') NOT NULL,
            titulo varchar(255) NOT NULL,
            mensagem longtext NOT NULL,
            status enum('pendente','enviado','erro','lido') DEFAULT 'pendente',
            data_envio datetime,
            data_leitura datetime,
            relacionado_tipo varchar(50),
            relacionado_id mediumint(9),
            prioridade enum('baixa','media','alta','urgente') DEFAULT 'media',
            tentativas int(2) DEFAULT 0,
            erro_detalhes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY tipo (tipo),
            KEY status (status),
            KEY relacionado (relacionado_tipo, relacionado_id),
            KEY prioridade (prioridade),
            KEY data_envio (data_envio)
        ) $charset_collate;";
        
        // Logs table
        $table_logs = $wpdb->prefix . 'ft1_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED,
            acao varchar(100) NOT NULL,
            objeto_tipo varchar(50) NOT NULL,
            objeto_id mediumint(9) NOT NULL,
            dados_anteriores longtext,
            dados_novos longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY acao (acao),
            KEY objeto (objeto_tipo, objeto_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_editais);
        dbDelta($sql_proponentes);
        dbDelta($sql_projetos);
        dbDelta($sql_contratos);
        dbDelta($sql_documentos);
        dbDelta($sql_notificacoes);
        dbDelta($sql_logs);
        
        update_option('ft1_cultural_db_version', FT1_CULTURAL_VERSION);
    }
    
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ft1_logs',
            $wpdb->prefix . 'ft1_notificacoes',
            $wpdb->prefix . 'ft1_documentos',
            $wpdb->prefix . 'ft1_contratos',
            $wpdb->prefix . 'ft1_projetos',
            $wpdb->prefix . 'ft1_proponentes',
            $wpdb->prefix . 'ft1_editais'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('ft1_cultural_db_version');
    }
    
    private function update_database() {
        self::create_tables();
    }
    
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'ft1_' . $table;
    }
}

