<?php

namespace Modules\ZabbixIm\Lib;

class LanguageManager {

    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            'IM Sync Assistant' => 'IM Sync Assistant',
            'IM Sync' => 'IM Sync',
            'IM Sync Settings' => 'Sync Settings',
            'Add sync setting' => 'Add sync setting',
            'Edit sync setting' => 'Edit sync setting',
            'Setting name' => 'Setting name',
            'Credentials' => 'Credentials',
            'Actions' => 'Actions',
            'Enabled' => 'Enabled',
            'Disabled' => 'Disabled',
            'Enable' => 'Enable',
            'Disable' => 'Disable',
            'Edit' => 'Edit',
            'Delete' => 'Delete',
            'Save' => 'Save',
            'Cancel' => 'Cancel',
            'Active sync setting' => 'Active sync setting',
            'Sync settings' => 'Sync settings',
            'Go to IM Sync' => 'Back to IM Sync',
            'Go to IM Sync Assistant' => 'Back to IM Sync Assistant',
            'Go to IM Sync Settings' => 'Go to Sync Settings',
            'Root department ID' => 'Root department ID',
            'Corp ID' => 'Corp ID',
            'Corp Secret' => 'Corp Secret',
            'App ID' => 'App ID',
            'App Secret' => 'App Secret',
            'App Key' => 'App Key',
            'Leave blank to keep unchanged' => 'Leave blank to keep unchanged',
            'Enable this setting (disables others)' => 'Enable this setting (disables others)',
            'WeCom/DingTalk default 1, Feishu default 0' => 'WeCom/DingTalk default 1, Feishu default 0',
            'Only one sync setting can be enabled at a time. The sync page uses the enabled setting.' => 'Only one sync setting can be enabled at a time. The IM Sync page uses the enabled setting.',
            'No sync settings yet. Click "Add sync setting" to create one.' => 'No sync settings yet. Click "Add sync setting" to create one.',
            'Confirm delete this sync setting?' => 'Confirm delete this sync setting?',
            'Settings saved' => 'Settings saved',
            'Settings deleted' => 'Settings deleted',
            'Save failed' => 'Save failed',
            'Delete failed' => 'Delete failed',
            'Sync setting not found' => 'Sync setting not found',
            'Setting name is required.' => 'Setting name is required.',
            'This sync setting is missing required credentials.' => 'This sync setting is missing required credentials.',
            'The enabled sync setting is missing required credentials. Please edit it in IM Sync Settings.' => 'The enabled sync setting is missing required credentials. Please edit it in Sync Settings.',
            'No enabled sync setting. Please add and enable one in IM Sync Settings.' => 'No enabled sync setting. Please add and enable one in Sync Settings.',
            'IM provider is not configured. Please configure it in IM Sync Settings.' => 'IM provider is not configured. Please configure it in Sync Settings.',
            'Instant Messaging Sync' => 'IM Sync Assistant',
            'WeCom' => 'WeCom',
            'Feishu' => 'Feishu',
            'DingTalk' => 'DingTalk',
            'Provider' => 'Provider',
            'Group Prefix' => 'Group Prefix',
            'User Match Field' => 'User Match Field',
            'Configuration File' => 'Configuration File',
            'Not configured' => 'Not configured',
            'Configured' => 'Configured',
            'Yes' => 'Yes',
            'No' => 'No',
            'Sync all departments' => 'Sync all departments',
            'Preview departments' => 'Preview departments',
            'Syncing...' => 'Syncing...',
            'Previewing...' => 'Previewing...',
            'Sync completed' => 'Sync completed',
            'Sync failed' => 'Sync failed',
            'Preview completed' => 'Preview completed',
            'Preview failed' => 'Preview failed',
            'No permission.' => 'No permission.',
            'Invalid input parameters.' => 'Invalid input parameters.',
            'IM provider is not configured. Please edit data/config.json.' => 'IM provider is not configured. Please edit data/config.json.',
            'Confirm sync all departments to Zabbix user groups?' => 'Confirm sync all departments to Zabbix user groups?',
            'Departments' => 'Departments',
            'Groups Created' => 'Groups Created',
            'Groups Updated' => 'Groups Updated',
            'Groups Existing' => 'Groups Existing',
            'Groups Failed' => 'Groups Failed',
            'Users Matched' => 'Users Matched',
            'Users Synced' => 'Users Synced',
            'Unmatched user list' => 'Unmatched user list',
            'Users Unmatched' => 'Users Unmatched',
            'Department' => 'Department',
            'User Group' => 'User Group',
            'IM Users' => 'IM Users',
            'Matched' => 'Matched',
            'Unmatched' => 'Unmatched',
            'Status' => 'Status',
            'Action' => 'Action',
            'Created' => 'Created',
            'Updated' => 'Updated',
            'Existing' => 'Existing',
            'Failed' => 'Failed',
            'No departments found' => 'No departments found',
            'Sync Result' => 'Sync Result',
            'Preview Result' => 'Preview Result',
            'username' => 'username',
            'email' => 'email',
            'alias' => 'alias',
            'Use full department path as group name' => 'Use full department path as group name',
            'Remove orphan groups on sync' => 'Remove orphan groups on sync',
            'Close' => 'Close',
            'Details' => 'Details',
            'Managed User Groups' => 'Managed User Groups',
            'Managed Users' => 'Managed Users',
            'Members' => 'Members',
            'No managed groups yet' => 'No managed groups yet',
            'Auto create users' => 'Auto create users',
            'Users Created' => 'Users Created',
            'Users Deleted' => 'Users Deleted',
            'Groups Deleted' => 'Groups Deleted',
            'System and manual users/groups are never deleted.' => 'System and manual users/groups are never deleted.',
            'Sync all users' => 'Sync all users',
            'Syncing users...' => 'Syncing users...',
            'User sync completed' => 'User sync completed',
            'User sync failed' => 'User sync failed',
            'User Sync Result' => 'User Sync Result',
            'Confirm sync all users and assign them to user groups?' => 'Confirm sync all users and assign them to user groups?',
            'User group not synced yet' => 'User group not synced yet',
            'default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.' => 'default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.',
            'Some departments are not synced yet. Run sync all departments before syncing users.' => 'Some departments are not synced yet. Run sync all departments before syncing users.',
            'IM raw user data' => 'IM raw user data',
            'IM User ID' => 'IM User ID',
            'Name' => 'Name',
            'Zabbix Username' => 'Zabbix Username',
            'Zabbix create debug' => 'Zabbix create debug',
            'Error' => 'Error',
            'WeCom API raw response' => 'WeCom API raw response',
            'IM API raw response' => 'IM API raw response',
            'API action' => 'API action',
            'HTTP raw body' => 'HTTP raw body',
            'Parsed response' => 'Parsed response',
            'Extracted userids' => 'Extracted userids',
            'Message' => 'Message',
            'Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.' => 'Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.',
            'Zabbix API call failed' => 'Zabbix API call failed',
            'check Zabbix PHP log or Authentication password policy' => 'check Zabbix PHP log or Authentication password policy',
            'Unknown API error' => 'Unknown API error',
            'skipped' => 'skipped',
            'failed' => 'failed',
            'updated' => 'updated',
            'created' => 'created',
            'renamed' => 'renamed',
            'existing' => 'existing',
            'linked' => 'linked',
            'matched' => 'matched',
            'unmatched' => 'unmatched',
            'preview' => 'preview',
            'User sync list' => 'User sync list',
            'Username' => 'Username',
            'Mobile' => 'Mobile',
            'Password' => 'Password',
            'New user passwords are shown only once. Please save them securely.' => 'New user passwords are shown only once. Please save them securely.',
            'Show IM raw user data' => 'Show IM raw user data',
            'Hide IM raw user data' => 'Hide IM raw user data',
            'Show IM API raw response' => 'Show IM API raw response',
            'Hide IM API raw response' => 'Hide IM API raw response',
            'No synced users' => 'No synced users',
            'Email' => 'Email',
            'Username or password is empty.' => 'Username or password is empty.',
            'API returned unexpected result: %s' => 'API returned unexpected result: %s',
            'Group %s is not managed by module' => 'Group %s is not managed by module',
            'Failed to encode config' => 'Failed to encode config',
            'Failed to write config file: %s' => 'Failed to write config file: %s',
            'DingTalk root department' => 'DingTalk root department',
            'Feishu root department' => 'Feishu root department',
            'Department %s' => 'Department %s',
            'HTTP error' => 'HTTP error',
            'WeCom department list failed' => 'WeCom department list failed',
            'WeCom user list failed' => 'WeCom user list failed',
            'WeCom gettoken failed' => 'WeCom gettoken failed',
            'WeCom corp_id or corp_secret is empty' => 'WeCom corp_id or corp_secret is empty',
            'WeCom access_token is empty' => 'WeCom access_token is empty',
            'Feishu user list failed' => 'Feishu user list failed',
            'Feishu department list failed' => 'Feishu department list failed',
            'Feishu app_id or app_secret is empty' => 'Feishu app_id or app_secret is empty',
            'Feishu tenant_access_token failed' => 'Feishu tenant_access_token failed',
            'Feishu tenant_access_token is empty' => 'Feishu tenant_access_token is empty',
            'DingTalk user list failed' => 'DingTalk user list failed',
            'DingTalk department listsub failed' => 'DingTalk department listsub failed',
            'DingTalk gettoken failed' => 'DingTalk gettoken failed',
            'DingTalk app_key or app_secret is empty' => 'DingTalk app_key or app_secret is empty',
            'DingTalk access_token is empty' => 'DingTalk access_token is empty',
        ],
        'pt_BR' => [
            'IM Sync Assistant' => 'Assistente de sincronização de IM',
            'IM Sync' => 'Sincronização de IM',
            'IM Sync Settings' => 'Configurações de sincronização',
            'Add sync setting' => 'Adicionar configuração de sincronização',
            'Edit sync setting' => 'Editar configuração de sincronização',
            'Setting name' => 'Nome da configuração',
            'Credentials' => 'Credenciais',
            'Actions' => 'Ações',
            'Enabled' => 'Ativado',
            'Disabled' => 'Desativado',
            'Enable' => 'Ativar',
            'Disable' => 'Desativar',
            'Edit' => 'Editar',
            'Delete' => 'Excluir',
            'Save' => 'Salvar',
            'Cancel' => 'Cancelar',
            'Active sync setting' => 'Configuração de sincronização ativa',
            'Sync settings' => 'Configurações de sincronização',
            'Go to IM Sync' => 'Voltar à sincronização de IM',
            'Go to IM Sync Assistant' => 'Voltar ao assistente de sincronização de IM',
            'Go to IM Sync Settings' => 'Ir para as configurações de sincronização',
            'Root department ID' => 'ID do departamento raiz',
            'Corp ID' => 'Corp ID',
            'Corp Secret' => 'Corp Secret',
            'App ID' => 'App ID',
            'App Secret' => 'App Secret',
            'App Key' => 'App Key',
            'Leave blank to keep unchanged' => 'Deixe em branco para manter inalterado',
            'Enable this setting (disables others)' => 'Ativar esta configuração (desativa as demais)',
            'WeCom/DingTalk default 1, Feishu default 0' => 'WeCom/DingTalk padrão 1, Feishu padrão 0',
            'Only one sync setting can be enabled at a time. The sync page uses the enabled setting.' => 'Apenas uma configuração de sincronização pode estar ativa por vez. A página de sincronização de IM usa a configuração ativa.',
            'No sync settings yet. Click "Add sync setting" to create one.' => 'Ainda não há configurações de sincronização. Clique em "Adicionar configuração de sincronização" para criar uma.',
            'Confirm delete this sync setting?' => 'Confirma a exclusão desta configuração de sincronização?',
            'Settings saved' => 'Configurações salvas',
            'Settings deleted' => 'Configuração excluída',
            'Save failed' => 'Falha ao salvar',
            'Delete failed' => 'Falha ao excluir',
            'Sync setting not found' => 'Configuração de sincronização não encontrada',
            'Setting name is required.' => 'O nome da configuração é obrigatório.',
            'This sync setting is missing required credentials.' => 'Faltam credenciais obrigatórias nesta configuração de sincronização.',
            'The enabled sync setting is missing required credentials. Please edit it in IM Sync Settings.' => 'Faltam credenciais obrigatórias na configuração de sincronização ativa. Edite-a em Configurações de sincronização.',
            'No enabled sync setting. Please add and enable one in IM Sync Settings.' => 'Nenhuma configuração de sincronização ativa. Adicione e ative uma em Configurações de sincronização.',
            'IM provider is not configured. Please configure it in IM Sync Settings.' => 'O provedor de IM não está configurado. Configure-o em Configurações de sincronização.',
            'Instant Messaging Sync' => 'Assistente de sincronização de IM',
            'WeCom' => 'WeCom',
            'Feishu' => 'Feishu',
            'DingTalk' => 'DingTalk',
            'Provider' => 'Provedor',
            'Group Prefix' => 'Prefixo do grupo',
            'User Match Field' => 'Campo de correspondência de usuário',
            'Configuration File' => 'Arquivo de configuração',
            'Not configured' => 'Não configurado',
            'Configured' => 'Configurado',
            'Yes' => 'Sim',
            'No' => 'Não',
            'Sync all departments' => 'Sincronizar todos os departamentos',
            'Preview departments' => 'Pré-visualizar departamentos',
            'Syncing...' => 'Sincronizando...',
            'Previewing...' => 'Gerando pré-visualização...',
            'Sync completed' => 'Sincronização concluída',
            'Sync failed' => 'Falha na sincronização',
            'Preview completed' => 'Pré-visualização concluída',
            'Preview failed' => 'Falha na pré-visualização',
            'No permission.' => 'Sem permissão.',
            'Invalid input parameters.' => 'Parâmetros de entrada inválidos.',
            'IM provider is not configured. Please edit data/config.json.' => 'O provedor de IM não está configurado. Edite data/config.json.',
            'Confirm sync all departments to Zabbix user groups?' => 'Confirma a sincronização de todos os departamentos com os grupos de usuários do Zabbix?',
            'Departments' => 'Departamentos',
            'Groups Created' => 'Grupos criados',
            'Groups Updated' => 'Grupos atualizados',
            'Groups Existing' => 'Grupos existentes',
            'Groups Failed' => 'Grupos com falha',
            'Users Matched' => 'Usuários correspondidos',
            'Users Synced' => 'Usuários sincronizados',
            'Unmatched user list' => 'Lista de usuários sem correspondência',
            'Users Unmatched' => 'Usuários sem correspondência',
            'Department' => 'Departamento',
            'User Group' => 'Grupo de usuários',
            'IM Users' => 'Usuários do IM',
            'Matched' => 'Correspondido',
            'Unmatched' => 'Sem correspondência',
            'Status' => 'Status',
            'Action' => 'Ação',
            'Created' => 'Criado',
            'Updated' => 'Atualizado',
            'Existing' => 'Existente',
            'Failed' => 'Falhou',
            'No departments found' => 'Nenhum departamento encontrado',
            'Sync Result' => 'Resultado da sincronização',
            'Preview Result' => 'Resultado da pré-visualização',
            'username' => 'usuário',
            'email' => 'e-mail',
            'alias' => 'alias',
            'Use full department path as group name' => 'Usar o caminho completo do departamento como nome do grupo',
            'Remove orphan groups on sync' => 'Remover grupos órfãos na sincronização',
            'Close' => 'Fechar',
            'Details' => 'Detalhes',
            'Managed User Groups' => 'Grupos de usuários gerenciados',
            'Managed Users' => 'Usuários gerenciados',
            'Members' => 'Membros',
            'No managed groups yet' => 'Ainda não há grupos gerenciados',
            'Auto create users' => 'Criar usuários automaticamente',
            'Users Created' => 'Usuários criados',
            'Users Deleted' => 'Usuários excluídos',
            'Groups Deleted' => 'Grupos excluídos',
            'System and manual users/groups are never deleted.' => 'Usuários/grupos do sistema e criados manualmente nunca são excluídos.',
            'Sync all users' => 'Sincronizar todos os usuários',
            'Syncing users...' => 'Sincronizando usuários...',
            'User sync completed' => 'Sincronização de usuários concluída',
            'User sync failed' => 'Falha na sincronização de usuários',
            'User Sync Result' => 'Resultado da sincronização de usuários',
            'Confirm sync all users and assign them to user groups?' => 'Confirma a sincronização de todos os usuários e a atribuição aos grupos de usuários?',
            'User group not synced yet' => 'Grupo de usuários ainda não sincronizado',
            'default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.' => 'default_roleid é obrigatório no Zabbix 7+ para criar usuários automaticamente. Edite data/config.json.',
            'Some departments are not synced yet. Run sync all departments before syncing users.' => 'Alguns departamentos ainda não foram sincronizados. Execute a sincronização de todos os departamentos antes de sincronizar os usuários.',
            'IM raw user data' => 'Dados brutos de usuários do IM',
            'IM User ID' => 'ID do usuário no IM',
            'Name' => 'Nome',
            'Zabbix Username' => 'Usuário no Zabbix',
            'Zabbix create debug' => 'Depuração de criação no Zabbix',
            'Error' => 'Erro',
            'WeCom API raw response' => 'Resposta bruta da API do WeCom',
            'IM API raw response' => 'Resposta bruta da API do IM',
            'API action' => 'Ação da API',
            'HTTP raw body' => 'Corpo HTTP bruto',
            'Parsed response' => 'Resposta interpretada',
            'Extracted userids' => 'userids extraídos',
            'Message' => 'Mensagem',
            'Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.' => 'Criar usuários automaticamente requer permissão de Super Admin. No Zabbix 7, user.create é exclusivo de Super Admin.',
            'Zabbix API call failed' => 'Falha na chamada à API do Zabbix',
            'check Zabbix PHP log or Authentication password policy' => 'verifique o log PHP do Zabbix ou a política de senhas em Autenticação',
            'Unknown API error' => 'Erro desconhecido da API',
            'skipped' => 'ignorado',
            'failed' => 'falhou',
            'updated' => 'atualizado',
            'created' => 'criado',
            'renamed' => 'renomeado',
            'existing' => 'existente',
            'linked' => 'vinculado',
            'matched' => 'correspondido',
            'unmatched' => 'sem correspondência',
            'preview' => 'pré-visualização',
            'User sync list' => 'Lista de sincronização de usuários',
            'Username' => 'Nome de usuário',
            'Mobile' => 'Celular',
            'Password' => 'Senha',
            'New user passwords are shown only once. Please save them securely.' => 'As senhas de novos usuários são exibidas apenas uma vez. Guarde-as em local seguro.',
            'Show IM raw user data' => 'Mostrar dados brutos de usuários do IM',
            'Hide IM raw user data' => 'Ocultar dados brutos de usuários do IM',
            'Show IM API raw response' => 'Mostrar resposta bruta da API do IM',
            'Hide IM API raw response' => 'Ocultar resposta bruta da API do IM',
            'No synced users' => 'Nenhum usuário sincronizado',
            'Email' => 'E-mail',
            'Username or password is empty.' => 'Usuário ou senha vazio.',
            'API returned unexpected result: %s' => 'A API retornou um resultado inesperado: %s',
            'Group %s is not managed by module' => 'O grupo %s não é gerenciado pelo módulo',
            'Failed to encode config' => 'Falha ao codificar a configuração',
            'Failed to write config file: %s' => 'Falha ao gravar o arquivo de configuração: %s',
            'DingTalk root department' => 'Departamento raiz do DingTalk',
            'Feishu root department' => 'Departamento raiz do Feishu',
            'Department %s' => 'Departamento %s',
            'HTTP error' => 'Erro HTTP',
            'WeCom department list failed' => 'Falha ao listar departamentos do WeCom',
            'WeCom user list failed' => 'Falha ao listar usuários do WeCom',
            'WeCom gettoken failed' => 'Falha no gettoken do WeCom',
            'WeCom corp_id or corp_secret is empty' => 'corp_id ou corp_secret do WeCom está vazio',
            'WeCom access_token is empty' => 'access_token do WeCom está vazio',
            'Feishu user list failed' => 'Falha ao listar usuários do Feishu',
            'Feishu department list failed' => 'Falha ao listar departamentos do Feishu',
            'Feishu app_id or app_secret is empty' => 'app_id ou app_secret do Feishu está vazio',
            'Feishu tenant_access_token failed' => 'Falha ao obter tenant_access_token do Feishu',
            'Feishu tenant_access_token is empty' => 'tenant_access_token do Feishu está vazio',
            'DingTalk user list failed' => 'Falha ao listar usuários do DingTalk',
            'DingTalk department listsub failed' => 'Falha no listsub de departamentos do DingTalk',
            'DingTalk gettoken failed' => 'Falha no gettoken do DingTalk',
            'DingTalk app_key or app_secret is empty' => 'app_key ou app_secret do DingTalk está vazio',
            'DingTalk access_token is empty' => 'access_token do DingTalk está vazio'
        ],
    ];

    /**
     * Detect the current language (aligned with the Zabbix source logic)
     * Priority:
     * 1) User language (users.lang); 'default' inherits the system default
     * 2) System default language (settings.default_lang); falls back to the Zabbix default if it cannot be read
     * 3) Zabbix default language (en_US)
     */
    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }

        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                self::$currentLanguage = self::ensureSupportedOrFallback($sys);
                return self::$currentLanguage;
            }
            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            return self::$currentLanguage;
        }

        self::$currentLanguage = self::ensureSupportedOrFallback(self::ZBX_DEFAULT_LANG);
        return self::$currentLanguage;
    }

    private static function getUserLanguageFromZabbix() {
        try {
            if (class_exists('CWebUser') || class_exists('\\CWebUser')) {
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && isset(\CWebUser::$data['lang']) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (\Throwable $e) {
        }

        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (\Throwable $e) {
        }

        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        $apiLang = self::getUserLanguageFromAPI();
        if (!empty($apiLang)) {
            return $apiLang;
        }

        return self::getUserLanguageFromDatabase();
    }

    private static function getUserLanguageFromAPI() {
        try {
            $userid = null;
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            $apiClass = null;
            if (class_exists('API') || class_exists('\API')) {
                $apiClass = '\API';
            }

            if ($apiClass && method_exists($apiClass, 'User')) {
                $users = $apiClass::User()->get([
                    'output' => ['lang'],
                    'userids' => $userid,
                    'limit' => 1,
                ]);

                if (!empty($users) && isset($users[0]['lang'])) {
                    return $users[0]['lang'];
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private static function getUserLanguageFromDatabase() {
        try {
            $userid = null;
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid || !defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
                return null;
            }

            $pdo = new \PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                defined('DB_PASSWORD') ? DB_PASSWORD : ''
            );

            $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = ? LIMIT 1');
            $stmt->execute([$userid]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['lang'])) {
                return $result['lang'];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private static function getSystemDefaultLanguage() {
        try {
            if (class_exists('CSettingsHelper') || class_exists('\\CSettingsHelper')) {
                if (method_exists('CSettingsHelper', 'get')) {
                    $val = \CSettingsHelper::get('default_lang');
                    if (!empty($val)) {
                        return $val;
                    }
                }
            }

            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                if ($stmt->execute()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value_str'])) {
                        return $row['value_str'];
                    }
                }

                $stmt2 = $pdo->query("SHOW TABLES LIKE 'config'");
                $hasConfig = $stmt2 && $stmt2->fetch();
                if ($hasConfig) {
                    $stmt3 = $pdo->query("SELECT default_lang FROM config LIMIT 1");
                    $row2 = $stmt3 ? $stmt3->fetch(\PDO::FETCH_ASSOC) : false;
                    if ($row2 && !empty($row2['default_lang'])) {
                        return $row2['default_lang'];
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function mapZabbixLangToOurs($zabbixLang) {
        $lang = strtolower(str_replace('-', '_', trim((string) $zabbixLang)));
        if ($lang === self::LANG_DEFAULT) {
            return self::LANG_DEFAULT;
        }
        // Supported languages: Brazilian Portuguese (pt*) and English (everything else).
        if (preg_match('/^pt(?:_|$)/', $lang)) {
            return 'pt_BR';
        }

        return 'en_US';
    }

    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        if (self::isSupportedLocale($mapped)) {
            return $mapped;
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
    }

    public static function t($key) {
        $lang = self::detectLanguage();

        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }

        if ($lang !== 'en_US' && isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }

        return $key;
    }

    public static function tf($key, ...$args) {
        return sprintf(self::t($key), ...$args);
    }

    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    public static function resetLanguage() {
        self::$currentLanguage = null;
    }

    public static function getLanguageDetectionInfo() {
        return [
            'detected' => self::detectLanguage(),
            'zabbix_user_lang' => self::getUserLanguageFromZabbix(),
            'db_lang' => self::getUserLanguageFromDatabase(),
            'system_lang' => self::getSystemDefaultLanguage(),
            'supported_locales' => array_keys(self::$translations),
        ];
    }

    public static function isChinese() {
        return false; // Chinese is no longer supported.
    }
}
