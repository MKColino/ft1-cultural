# FT1 Cultural - Plugin CRM para WordPress

## Descrição

O FT1 Cultural é um plugin CRM completo para WordPress desenvolvido especialmente para gestão de editais culturais. O sistema oferece uma solução robusta e segura para controle de projetos, cadastro de proponentes, upload de documentos, contratos digitais com assinatura eletrônica, dashboard responsivo e sistema completo de níveis de acesso.

## Características Principais

### 🎯 Gestão de Editais
- Cadastro completo de editais com datas de início e fim
- Controle de status (rascunho, publicado, em andamento, finalizado)
- Definição de valores e critérios de avaliação
- Publicação automática e notificações

### 👥 Cadastro de Proponentes
- Sistema de cadastro com ID único para cada proponente
- Perfis completos com dados pessoais e profissionais
- Upload de documentos comprobatórios
- Histórico de participações em editais

### 📋 Controle de Projetos
- Submissão de projetos pelos proponentes
- Sistema de avaliação com múltiplos critérios
- Controle de status (enviado, em análise, aprovado, rejeitado)
- Área separada para cada edital e projeto
- Apresentação em tabelas dos projetos aprovados e não aprovados

### 📄 Sistema de Contratos Digitais
- Geração automática de contratos para projetos aprovados
- Assinatura digital com validade legal
- Envio por email e WhatsApp
- Registro de IP, data e hora da assinatura
- Possibilidade de assinatura por digitação ou desenho da rubrica

### 📅 Calendário Integrado
- Visualização das datas de início e encerramento dos editais
- Lembretes automáticos de prazos
- Eventos personalizados
- Exportação para formatos ICS, CSV e JSON

### 🔐 Sistema de Segurança
- Níveis de acesso diferenciados por perfil
- Proponentes têm acesso apenas aos próprios projetos
- Criptografia de dados sensíveis
- Auditoria completa de ações
- Proteção contra ataques e tentativas de invasão

### 📊 Dashboard Responsivo
- Interface moderna e intuitiva
- Estatísticas em tempo real
- Gráficos e relatórios
- Compatível com dispositivos móveis

### 🔔 Sistema de Notificações
- Notificações por email e WhatsApp
- Alertas automáticos de prazos
- Comunicação personalizada
- Histórico de notificações

## Requisitos do Sistema

- WordPress 5.0 ou superior
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Extensões PHP: openssl, curl, json, mbstring

## Instalação

### Método 1: Upload via Painel Administrativo

1. Faça login no painel administrativo do WordPress
2. Vá em **Plugins > Adicionar Novo**
3. Clique em **Enviar Plugin**
4. Selecione o arquivo `ft1-cultural.zip`
5. Clique em **Instalar Agora**
6. Após a instalação, clique em **Ativar Plugin**

### Método 2: Upload via FTP

1. Extraia o arquivo `ft1-cultural.zip`
2. Faça upload da pasta `ft1-cultural` para `/wp-content/plugins/`
3. No painel administrativo, vá em **Plugins**
4. Localize "FT1 Cultural" e clique em **Ativar**

## Configuração Inicial

### 1. Primeiro Acesso

Após a ativação, você verá uma notificação de boas-vindas. Clique em **Acessar Dashboard** para começar a configuração.

### 2. Configurações Básicas

Vá em **FT1 Cultural > Configurações** e configure:

- **Notificações por Email**: Ative/desative notificações automáticas
- **Notificações por WhatsApp**: Configure API do WhatsApp (opcional)
- **Upload de Arquivos**: Defina tamanho máximo e tipos permitidos
- **Contratos**: Configure validade dos links de assinatura

### 3. Criação de Usuários

O plugin cria automaticamente os seguintes perfis de usuário:

- **FT1 Administrador**: Acesso completo ao sistema
- **FT1 Gestor**: Gerencia editais, avalia projetos, gerencia contratos
- **FT1 Avaliador**: Avalia projetos e valida documentos
- **FT1 Operador**: Operações básicas, visualização e edição
- **FT1 Proponente**: Acesso limitado aos próprios projetos

## Guia de Uso

### Para Administradores

#### Criando um Edital

1. Vá em **FT1 Cultural > Editais**
2. Clique em **Novo Edital**
3. Preencha as informações:
   - Título e descrição
   - Datas de início e fim
   - Valor total disponível
   - Critérios de avaliação
4. Defina o status como **Publicado** para disponibilizar

#### Gerenciando Proponentes

1. Acesse **FT1 Cultural > Proponentes**
2. Visualize todos os proponentes cadastrados
3. Edite informações ou adicione novos proponentes
4. Acompanhe o histórico de participações

#### Avaliando Projetos

1. Vá em **FT1 Cultural > Projetos**
2. Filtre por status **Em Análise**
3. Clique em **Avaliar** no projeto desejado
4. Preencha os critérios de avaliação
5. Defina como **Aprovado** ou **Rejeitado**

#### Gerenciando Contratos

1. Acesse **FT1 Cultural > Contratos**
2. Contratos são gerados automaticamente para projetos aprovados
3. Clique em **Enviar** para enviar por email/WhatsApp
4. Acompanhe o status da assinatura

### Para Proponentes

#### Cadastro no Sistema

1. Acesse a página de registro do site
2. Preencha seus dados pessoais
3. Aguarde aprovação do cadastro
4. Receba as credenciais por email

#### Submetendo um Projeto

1. Faça login no sistema
2. Vá em **Meus Projetos**
3. Clique em **Novo Projeto**
4. Selecione o edital desejado
5. Preencha todas as informações obrigatórias
6. Faça upload dos documentos necessários
7. Clique em **Submeter Projeto**

#### Acompanhando Status

1. Acesse **Meus Projetos**
2. Visualize o status atual de cada projeto
3. Receba notificações sobre mudanças de status
4. Acesse contratos quando aprovado

#### Assinando Contratos

1. Receba o link de assinatura por email/WhatsApp
2. Clique no link (válido por 15 dias)
3. Revise o contrato
4. Assine digitalmente ou desenhe sua rubrica
5. Confirme a assinatura

## Funcionalidades Avançadas

### API REST

O plugin oferece endpoints REST para integração:

```
GET /wp-json/ft1-cultural/v1/editais
GET /wp-json/ft1-cultural/v1/projetos
POST /wp-json/ft1-cultural/v1/projetos
GET /wp-json/ft1-cultural/v1/contratos
```

### Webhooks

Configure webhooks para receber notificações em tempo real:

- Novo projeto submetido
- Projeto avaliado
- Contrato assinado
- Prazo de edital se aproximando

### Exportação de Dados

- Exporte listas de proponentes em CSV/Excel
- Gere relatórios de projetos por período
- Exporte calendário em formato ICS
- Backup completo dos dados

### Integração WhatsApp

Configure a API do WhatsApp para envio automático de notificações:

1. Obtenha uma API key de um provedor WhatsApp Business
2. Configure a URL e token em **Configurações**
3. Teste o envio de mensagens
4. Ative notificações automáticas

## Segurança

### Medidas Implementadas

- **Criptografia**: Dados sensíveis são criptografados
- **Auditoria**: Todas as ações são registradas
- **Rate Limiting**: Proteção contra ataques de força bruta
- **Validação**: Todos os dados são validados e sanitizados
- **CSRF Protection**: Proteção contra ataques CSRF
- **File Upload Security**: Verificação rigorosa de arquivos

### Backup e Recuperação

- Configure backups automáticos regulares
- Mantenha cópias dos contratos assinados
- Exporte dados periodicamente
- Teste a recuperação regularmente

## Suporte e Manutenção

### Logs do Sistema

Acesse **FT1 Cultural > Relatórios > Logs** para visualizar:

- Ações dos usuários
- Eventos de segurança
- Erros do sistema
- Estatísticas de uso

### Solução de Problemas

#### Plugin não ativa
- Verifique se os requisitos do sistema são atendidos
- Ative o modo debug do WordPress
- Consulte os logs de erro

#### Emails não são enviados
- Verifique as configurações SMTP
- Teste com um plugin de email
- Verifique se o servidor permite envio de emails

#### Upload de arquivos falha
- Verifique as permissões da pasta uploads
- Aumente o limite de upload no PHP
- Verifique os tipos de arquivo permitidos

### Atualizações

- Sempre faça backup antes de atualizar
- Teste em ambiente de desenvolvimento primeiro
- Leia as notas de versão
- Monitore o sistema após a atualização

## Desenvolvimento e Personalização

### Hooks Disponíveis

```php
// Ações
do_action('ft1_edital_created', $edital_id, $edital_data);
do_action('ft1_projeto_submitted', $projeto_id, $projeto_data);
do_action('ft1_contrato_signed', $contrato_id, $contrato_data);

// Filtros
apply_filters('ft1_cultural_notification_templates', $templates);
apply_filters('ft1_cultural_user_capabilities', $capabilities, $user_id);
apply_filters('ft1_cultural_contract_template', $template, $projeto_data);
```

### Personalização de Templates

Copie os templates para seu tema ativo:

```
wp-content/themes/seu-tema/ft1-cultural/
├── contract-template.php
├── email-template.php
└── dashboard-widget.php
```

### CSS Personalizado

Adicione CSS personalizado em **Aparência > Personalizar > CSS Adicional**:

```css
/* Personalizar cores do FT1 Cultural */
:root {
    --ft1-primary: #seu-cor-primaria;
    --ft1-secondary: #sua-cor-secundaria;
}
```

## Licença e Propriedade Intelectual

Este plugin foi desenvolvido por **Fabricat1 Soluções de Mercado** e está protegido por direitos autorais.

### Termos de Uso

- Uso permitido apenas para o cliente licenciado
- Proibida a redistribuição sem autorização
- Suporte técnico incluído por 12 meses
- Atualizações gratuitas por 12 meses

### Contato

- **Empresa**: Fabricat1 Soluções de Mercado
- **Website**: [www.fabricat1.com.br](http://www.fabricat1.com.br)
- **Email**: contato@fabricat1.com.br
- **Suporte**: suporte@fabricat1.com.br

## Changelog

### Versão 1.0.0 (Data de Lançamento)

#### Funcionalidades Implementadas
- ✅ Sistema completo de gestão de editais
- ✅ Cadastro e gerenciamento de proponentes
- ✅ Controle de projetos com avaliação
- ✅ Sistema de contratos com assinatura digital
- ✅ Dashboard responsivo e moderno
- ✅ Calendário integrado com eventos
- ✅ Sistema de notificações (email/WhatsApp)
- ✅ Níveis de acesso e segurança
- ✅ Upload seguro de documentos
- ✅ Auditoria e logs completos
- ✅ API REST para integrações
- ✅ Exportação de dados
- ✅ Sistema de backup

#### Segurança
- ✅ Criptografia de dados sensíveis
- ✅ Proteção contra ataques
- ✅ Validação rigorosa de entrada
- ✅ Auditoria completa de ações
- ✅ Rate limiting implementado

#### Interface
- ✅ Design responsivo e moderno
- ✅ Compatibilidade com dispositivos móveis
- ✅ Interface intuitiva e amigável
- ✅ Acessibilidade implementada

---

**© 2024 Fabricat1 Soluções de Mercado. Todos os direitos reservados.**

