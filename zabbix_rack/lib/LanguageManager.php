<?php

namespace Modules\ZabbixRack\Lib;

class LanguageManager {
    /**
     * Identifiers and defaults consistent with the Zabbix frontend
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            // Module and menu (used by Module.php)
            'Rack Module' => 'Rack Management',
            'Rack View' => 'Rack View',
            'Rack Config' => 'Rack Config',
            
            // Page titles (used by view files, snake_case)
            'rack_view' => 'Rack View',
            'rack_manage' => 'Rack Config',
            'rack_management' => 'Rack Management',
            'room_management' => 'Room Management',
            
            // General
            'search' => 'Search',
            'search_placeholder' => 'Search rack or host...',
            'all_rooms' => 'All Rooms',
            'all_racks' => 'All Racks',
            'room' => 'Room',
            'rack' => 'Rack',
            'racks' => 'Racks',
            'rooms' => 'Rooms',
            'name' => 'Name',
            'location' => 'Location',
            'height' => 'Height',
            'units' => 'Units',
            'actions' => 'Actions',
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'close' => 'Close',
            'confirm' => 'Confirm',
            'yes' => 'Yes',
            'no' => 'No',
            'empty' => 'Empty',
            'occupied' => 'Occupied',
            'filter' => 'Filter',
            'remove' => 'Remove',
            'description' => 'Description',
            'created_at' => 'Created At',
            
            // Rooms
            'add_room' => 'Add Room',
            'edit_room' => 'Edit Room',
            'room_name' => 'Room Name',
            'enter_room_name' => 'Enter room name',
            'room_description' => 'Room Description',
            'enter_room_description' => 'Enter room description',
            'delete_room' => 'Delete Room',
            'confirm_delete_room' => 'Are you sure to delete this room? All racks in this room will also be deleted.',
            'no_rooms' => 'No rooms',
            'rack_count' => 'Rack Count',
            
            // Racks
            'add_rack' => 'Add Rack',
            'edit_rack' => 'Edit Rack',
            'rack_name' => 'Rack Name',
            'enter_rack_name' => 'Enter rack name',
            'rack_height' => 'Rack Height (U)',
            'select_room' => 'Select Room',
            'delete_rack' => 'Delete Rack',
            'confirm_delete_rack' => 'Are you sure to delete this rack? All host assignments will be removed.',
            'no_racks_found' => 'No racks found',
            'no_rooms_configured' => 'No rooms configured. Please add a room first.',
            'no_racks' => 'No racks',
            'no_rack_selected' => 'Please select a rack',
            
            // Host assignment
            'assign_host' => 'Assign Host',
            'assigned_host' => 'Assigned Host',
            'custom_host' => 'Custom Host',
            'custom_host_name' => 'Host Name',
            'custom_host_ip' => 'IP Address',
            'custom_host_name_placeholder' => 'Enter a custom host name',
            'custom_host_ip_placeholder' => 'For example: 192.168.1.10',
            'remove_host' => 'Remove Host',
            'select_host' => 'Select Host',
            'host_group' => 'Host Group',
            'all_groups' => 'All Groups',
            'position' => 'Position',
            'u_start' => 'Start U',
            'u_end' => 'End U',
            'host' => 'Host',
            'ip_address' => 'IP Address',
            'status' => 'Status',
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
            'search_host' => 'Search Host',
            'search_host_placeholder' => 'Search by host name...',
            'hosts_in_rack' => 'Hosts in Rack',
            'search_results' => 'Search Results',
            
            // Operation results
            'save_success' => 'Save successful',
            'save_failed' => 'Save failed',
            'delete_success' => 'Delete successful',
            'delete_failed' => 'Delete failed',
            'assign_success' => 'Assign successful',
            'assign_failed' => 'Assign failed',
            'remove_success' => 'Remove successful',
            'remove_failed' => 'Remove failed',
            'operation_failed' => 'Operation failed',
            'save_permission_hint' => "Save failed. Check directory/file permissions on {path} and the SELinux httpd_sys_rw_content_t label. Ownership or chmod alone cannot resolve SELinux denials. See the module README and PHP/Web logs.",
            
            // Error messages
            'room_not_found' => 'Room not found',
            'rack_not_found' => 'Rack not found',
            'invalid_position' => 'Invalid U position range',
            'position_occupied' => 'Position is occupied',
            'custom_host_required' => 'Please enter a custom host name and IP address',
            'invalid_custom_ip' => 'Please enter a valid IP address',
            'room_name_required' => 'Room name is required',
            'rack_name_required' => 'Rack name is required',
            'room_selection_required' => 'Please select a room',
            
            // Hints
            'click_to_assign_host' => 'Click to assign host',
            'drag_to_reposition' => 'Drag to reposition',
            'double_click_to_edit' => 'Double click to edit',
            'loading' => 'Loading...',
            'no_data' => 'No data',
            
            // Statistics
            'total_rooms' => 'Total Rooms',
            'total_racks' => 'Total Racks',
            'total_hosts' => 'Total Hosts',
            'total_problems' => 'Total Problems',
            'utilization' => 'Utilization',
            'space_usage' => 'Space Usage',
            'usage_rate' => 'Usage Rate',
            'total_space' => 'Total Space',
            'front_space' => 'Front Space',
            'back_space' => 'Rear Space',
            'total_usage_rate' => 'Total Usage',
            'front_usage_rate' => 'Front Usage',
            'back_usage_rate' => 'Rear Usage',
            'select_rack' => 'Select Rack',
            'total_capacity' => 'Total Capacity',
            'used_capacity' => 'Used',
            'used' => 'Used',
            'enter_rack_description' => 'Enter rack description',
            'hosts' => 'Hosts',
            'usage' => 'Usage',
            'view_details' => 'View Details',
            
            // Alarms
            'problems' => 'Problems',
            'no_problems' => 'No Problems',
            'host_problems' => 'Host Problems',
            'problem_count' => 'Problem Count',
            'no_active_problems' => '✅ No active problems',
            'fetch_problems_failed' => 'Failed to fetch problems',
            
            // Severity names
            'severity_not_classified' => 'Not classified',
            'severity_information' => 'Information',
            'severity_warning' => 'Warning',
            'severity_average' => 'Average',
            'severity_high' => 'High',
            'severity_disaster' => 'Disaster',
            'severity_unknown' => 'Unknown',
            
            // Rack view JS translations
            'host_name_label' => 'Host:',
            'ip_label' => 'IP:',
            'host_group_label' => 'Group:',
            'position_label' => 'Position:',
            'status_label' => 'Status:',
            'unnamed_host' => 'Unnamed Host',
            'edit_host_position' => 'Edit Host Position',
            'no_matching_hosts' => 'No matching hosts',
            'select_a_host' => 'Please select a host',
            'invalid_u_range' => 'Please enter a valid U range',
            'confirm_remove_host' => 'Are you sure to remove this host from the rack?',
            
            // Rack management JS translations
            'delete_room_title' => 'Delete Room',
            'delete_rack_title' => 'Delete Rack',
            'room_has_racks_warning' => 'This room has {count} rack(s), which will also be deleted!',
            'rack_height_hint' => 'Valid range: 1-60U, default 42U',
            
            // Backend validation
            'invalid_input' => 'Invalid input',
            'permission_denied' => 'You do not have access to this room',
            'room_permissions' => 'Access control',
            'room_user_groups' => 'Zabbix user groups',
            'room_users' => 'Zabbix users',
            'permission_public' => 'Visible to all users',
            'permission_restricted' => 'Restricted',
            'permission_user_groups_count' => '%d user group(s)',
            'permission_users_count' => '%d user(s)',
            'permission_hint' => 'All user groups and users selected by default means visible to everyone; deselect entries to restrict access.',
            'select_user_groups' => 'Hold Ctrl/Shift to select multiple user groups',
            'select_users' => 'Hold Ctrl/Shift to select multiple users',
            'no_accessible_rooms' => 'No accessible rooms for your account',
            'manage_super_admin_only' => 'Only Super Admin can access Rack Config',
            'rack_side' => 'Rack side',
            'rack_side_front' => 'Front',
            'rack_side_back' => 'Rear',
            'rack_side_front_view' => 'Rack front',
            'rack_side_back_view' => 'Rack rear',
            'switch_to_front' => 'Switch to front',
            'switch_to_back' => 'Switch to rear',
        ],
        'pt_BR' => [
            'Rack Module' => 'Gerenciamento de racks',
            'Rack View' => 'Visualização de racks',
            'Rack Config' => 'Configuração de racks',
            'rack_view' => 'Visualização de racks',
            'rack_manage' => 'Configuração de racks',
            'rack_management' => 'Gerenciamento de racks',
            'room_management' => 'Gerenciamento de salas',
            'search' => 'Pesquisar',
            'search_placeholder' => 'Pesquisar rack ou host...',
            'all_rooms' => 'Todas as salas',
            'all_racks' => 'Todos os racks',
            'room' => 'Sala',
            'rack' => 'Rack',
            'racks' => 'Racks',
            'rooms' => 'Salas',
            'name' => 'Nome',
            'location' => 'Localização',
            'height' => 'Altura',
            'units' => 'Unidades',
            'actions' => 'Ações',
            'add' => 'Adicionar',
            'edit' => 'Editar',
            'delete' => 'Excluir',
            'save' => 'Salvar',
            'cancel' => 'Cancelar',
            'close' => 'Fechar',
            'confirm' => 'Confirmar',
            'yes' => 'Sim',
            'no' => 'Não',
            'empty' => 'Vazio',
            'occupied' => 'Ocupado',
            'filter' => 'Filtro',
            'remove' => 'Remover',
            'description' => 'Descrição',
            'created_at' => 'Criado em',
            'add_room' => 'Adicionar sala',
            'edit_room' => 'Editar sala',
            'room_name' => 'Nome da sala',
            'enter_room_name' => 'Digite o nome da sala',
            'room_description' => 'Descrição da sala',
            'enter_room_description' => 'Digite a descrição da sala',
            'delete_room' => 'Excluir sala',
            'confirm_delete_room' => 'Tem certeza de que deseja excluir esta sala? Todos os racks desta sala também serão excluídos.',
            'no_rooms' => 'Nenhuma sala',
            'rack_count' => 'Quantidade de racks',
            'add_rack' => 'Adicionar rack',
            'edit_rack' => 'Editar rack',
            'rack_name' => 'Nome do rack',
            'enter_rack_name' => 'Digite o nome do rack',
            'rack_height' => 'Altura do rack (U)',
            'select_room' => 'Selecione a sala',
            'delete_rack' => 'Excluir rack',
            'confirm_delete_rack' => 'Tem certeza de que deseja excluir este rack? Todas as atribuições de hosts serão removidas.',
            'no_racks_found' => 'Nenhum rack encontrado',
            'no_rooms_configured' => 'Nenhuma sala configurada. Adicione uma sala primeiro.',
            'no_racks' => 'Nenhum rack',
            'no_rack_selected' => 'Selecione um rack',
            'assign_host' => 'Atribuir host',
            'assigned_host' => 'Host atribuído',
            'custom_host' => 'Host personalizado',
            'custom_host_name' => 'Nome do host',
            'custom_host_ip' => 'Endereço IP',
            'custom_host_name_placeholder' => 'Digite um nome de host personalizado',
            'custom_host_ip_placeholder' => 'Por exemplo: 192.168.1.10',
            'remove_host' => 'Remover host',
            'select_host' => 'Selecionar host',
            'host_group' => 'Grupo de hosts',
            'all_groups' => 'Todos os grupos',
            'position' => 'Posição',
            'u_start' => 'U inicial',
            'u_end' => 'U final',
            'host' => 'Host',
            'ip_address' => 'Endereço IP',
            'status' => 'Status',
            'enabled' => 'Ativado',
            'disabled' => 'Desativado',
            'search_host' => 'Pesquisar host',
            'search_host_placeholder' => 'Pesquisar por nome do host...',
            'hosts_in_rack' => 'Hosts no rack',
            'search_results' => 'Resultados da pesquisa',
            'save_success' => 'Salvo com sucesso',
            'save_failed' => 'Falha ao salvar',
            'delete_success' => 'Excluído com sucesso',
            'delete_failed' => 'Falha ao excluir',
            'assign_success' => 'Atribuído com sucesso',
            'assign_failed' => 'Falha na atribuição',
            'remove_success' => 'Removido com sucesso',
            'remove_failed' => 'Falha na remoção',
            'operation_failed' => 'Falha na operação',
            'save_permission_hint' => 'Falha ao salvar. Verifique as permissões do diretório/arquivo em {path} e o rótulo SELinux httpd_sys_rw_content_t. Alterar o proprietário ou usar chmod não resolve bloqueios do SELinux. Consulte o README do módulo e os logs do PHP/web.',
            'room_not_found' => 'Sala não encontrada',
            'rack_not_found' => 'Rack não encontrado',
            'invalid_position' => 'Intervalo de posição U inválido',
            'position_occupied' => 'A posição está ocupada',
            'custom_host_required' => 'Digite um nome de host personalizado e o endereço IP',
            'invalid_custom_ip' => 'Digite um endereço IP válido',
            'room_name_required' => 'O nome da sala é obrigatório',
            'rack_name_required' => 'O nome do rack é obrigatório',
            'room_selection_required' => 'Selecione uma sala',
            'click_to_assign_host' => 'Clique para atribuir um host',
            'drag_to_reposition' => 'Arraste para reposicionar',
            'double_click_to_edit' => 'Clique duas vezes para editar',
            'loading' => 'Carregando...',
            'no_data' => 'Sem dados',
            'total_rooms' => 'Total de salas',
            'total_racks' => 'Total de racks',
            'total_hosts' => 'Total de hosts',
            'total_problems' => 'Total de incidentes',
            'utilization' => 'Ocupação',
            'space_usage' => 'Uso de espaço',
            'usage_rate' => 'Taxa de uso',
            'total_space' => 'Espaço total',
            'front_space' => 'Espaço frontal',
            'back_space' => 'Espaço traseiro',
            'total_usage_rate' => 'Uso total',
            'front_usage_rate' => 'Uso frontal',
            'back_usage_rate' => 'Uso traseiro',
            'select_rack' => 'Selecione o rack',
            'total_capacity' => 'Capacidade total',
            'used_capacity' => 'Usado',
            'used' => 'Usado',
            'enter_rack_description' => 'Digite a descrição do rack',
            'hosts' => 'Hosts',
            'usage' => 'Uso',
            'view_details' => 'Ver detalhes',
            'problems' => 'Incidentes',
            'no_problems' => 'Sem incidentes',
            'host_problems' => 'Incidentes do host',
            'problem_count' => 'Quantidade de incidentes',
            'no_active_problems' => '✅ Nenhum incidente ativo',
            'fetch_problems_failed' => 'Falha ao obter os incidentes',
            'severity_not_classified' => 'Não classificada',
            'severity_information' => 'Informação',
            'severity_warning' => 'Atenção',
            'severity_average' => 'Média',
            'severity_high' => 'Alta',
            'severity_disaster' => 'Desastre',
            'severity_unknown' => 'Desconhecido',
            'host_name_label' => 'Host:',
            'ip_label' => 'IP:',
            'host_group_label' => 'Grupo:',
            'position_label' => 'Posição:',
            'status_label' => 'Status:',
            'unnamed_host' => 'Host sem nome',
            'edit_host_position' => 'Editar posição do host',
            'no_matching_hosts' => 'Nenhum host correspondente',
            'select_a_host' => 'Selecione um host',
            'invalid_u_range' => 'Digite um intervalo U válido',
            'confirm_remove_host' => 'Tem certeza de que deseja remover este host do rack?',
            'delete_room_title' => 'Excluir sala',
            'delete_rack_title' => 'Excluir rack',
            'room_has_racks_warning' => 'Esta sala tem {count} rack(s), que também serão excluídos!',
            'rack_height_hint' => 'Intervalo válido: 1-60U, padrão 42U',
            'invalid_input' => 'Entrada inválida',
            'permission_denied' => 'Você não tem acesso a esta sala',
            'room_permissions' => 'Controle de acesso',
            'room_user_groups' => 'Grupos de usuários do Zabbix',
            'room_users' => 'Usuários do Zabbix',
            'permission_public' => 'Visível para todos os usuários',
            'permission_restricted' => 'Restrito',
            'permission_user_groups_count' => '%d grupo(s) de usuários',
            'permission_users_count' => '%d usuário(s)',
            'permission_hint' => 'Todos os grupos de usuários e usuários selecionados por padrão significa visível para todos; desmarque itens para restringir o acesso.',
            'select_user_groups' => 'Mantenha Ctrl/Shift pressionado para selecionar vários grupos de usuários',
            'select_users' => 'Mantenha Ctrl/Shift pressionado para selecionar vários usuários',
            'no_accessible_rooms' => 'Nenhuma sala acessível para a sua conta',
            'manage_super_admin_only' => 'Apenas Super Admin pode acessar a Configuração de racks',
            'rack_side' => 'Lado do rack',
            'rack_side_front' => 'Frente',
            'rack_side_back' => 'Traseira',
            'rack_side_front_view' => 'Frente do rack',
            'rack_side_back_view' => 'Traseira do rack',
            'switch_to_front' => 'Mudar para a frente',
            'switch_to_back' => 'Mudar para a traseira'
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
        
        // 1) User language
        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            // 'default' means inherit the system default
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                self::$currentLanguage = self::ensureSupportedOrFallback($sys);
                return self::$currentLanguage;
            }
            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        // 2) System default language
        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            return self::$currentLanguage;
        }

        // 3) Zabbix default language
        self::$currentLanguage = self::ensureSupportedOrFallback(self::ZBX_DEFAULT_LANG);
        return self::$currentLanguage;
    }

    /**
     * Try to get the current user's language setting from Zabbix
     */
    private static function getUserLanguageFromZabbix() {
        // Method 0: prefer the official Zabbix wrapper CWebUser
        try {
            if (class_exists('CWebUser') || class_exists('\\CWebUser')) {
                // Static get method (newer versions)
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                // Static data container of older versions
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && isset(\CWebUser::$data['lang']) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
            // Ignore and continue with other methods
        }

        // Method 1: try to get CWebUser information through global variables
        try {
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (\Throwable $e) {
            // Continue with other methods
        }
        
        // Method 2: try global variables
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (\Throwable $e) {
            // Continue with other methods
        }
        
        // Method 3: from the session
        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        // Method 4: try to read the user language setting directly from the database
        return self::getUserLanguageFromDatabase();
    }

    /**
     * Try to read the current user's language setting directly from the database
     */
    private static function getUserLanguageFromDatabase() {
        try {
            // Get the current user ID
            $userid = null;

            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // Try to connect to the database
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
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
            }
        } catch (\Throwable $e) {
            // Database connection failed or other error
        }

        return null;
    }

    /**
     * Read the system default language
     */
    private static function getSystemDefaultLanguage() {
        try {
            // Prefer the official Zabbix wrapper CSettingsHelper
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

                // Query the settings table first
                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                if ($stmt->execute()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value_str'])) {
                        return $row['value_str'];
                    }
                }

                // Compatibility with older versions: config table
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
            // Ignore and fall back
        }

        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Map the Zabbix language code to our language code
     */
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

    /**
     * Check and fall back to a supported language
     */
    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        if (self::isSupportedLocale($mapped)) {
            return $mapped;
        }
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Only languages for which we provide translations count as available
     */
    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
    }

    /**
     * Get translated text (static method, can also be called on an instance)
     * Supports both LanguageManager::t('key') and $lm->t('key')
     */
    public static function t($key) {
        $lang = self::detectLanguage();

        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }

        // If the current language has no translation, try English
        if ($lang !== 'en_US' && isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }

        // If neither exists, return the key itself
        return $key;
    }

    public static function tf($key, ...$args) {
        return sprintf(self::t($key), ...$args);
    }

    /**
     * Get translated text statically (alias of translate)
     */
    public static function translate($key) {
        return self::t($key);
    }

    /**
     * Get the current language
     */
    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    /**
     * Whether the current language is Chinese
     */
    public static function isChinese() {
        return false; // Chinese is no longer supported.
    }
}
