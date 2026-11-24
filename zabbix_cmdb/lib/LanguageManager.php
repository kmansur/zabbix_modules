<?php

namespace Modules\ZabbixCmdb\Lib;

class LanguageManager {
    /**
     * Identifiers and defaults consistent with the Zabbix frontend
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            'CMDB' => 'CMDB',
            'Configuration Management Database' => 'Configuration Management Database',
            'Search hosts...' => 'Search hosts...',
            'Search by hostname or IP' => 'Search by hostname or IP',
            'All Groups' => 'All Groups',
            'Select host group' => 'Select host group',
            'Host Name' => 'Host Name',
            'IP Address' => 'IP Address',
            'Interface Type' => 'Interface Type',
            'All Interfaces' => 'All Interfaces',
            'CPU Total' => 'CPU Total',
            'CPU Usage' => 'CPU Usage',
            'Memory Total' => 'Memory Total',
            'Memory Usage' => 'Memory Usage',
            'Host Group' => 'Host Group',
            'System Name' => 'System Name',
            'Architecture' => 'Architecture',
            'Operating System' => 'Operating System',
            'Kernel Version' => 'Kernel Version',
            'Agent' => 'Agent',
            'SNMP' => 'SNMP',
            'IPMI' => 'IPMI',
            'JMX' => 'JMX',
            'No hosts found' => 'No hosts found',
            'Loading...' => 'Loading...',
            'Search' => 'Search',
            'Clear' => 'Clear',
            'Total Hosts' => 'Total Hosts',
            'Host Groups' => 'Host Groups',
            'Host List' => 'Host List',
            'Search host groups...' => 'Search host groups...',
            'Enter group name' => 'Enter group name',
            'Group Name' => 'Group Name',
            'Host Count' => 'Host Count',
            'Active Hosts' => 'Active Hosts',
            'Search by group name' => 'Search by group name',
            'Search groups...' => 'Search groups...',
            'Status' => 'Status',
            'No groups found' => 'No groups found',
            'Empty Group' => 'Empty Group',
            'Active Group' => 'Active Group',
            'Basic Group' => 'Basic Group',
            'hosts' => 'hosts',
            'cores' => 'cores',
            'Invalid input parameters.' => 'Invalid input parameters.',
            'Showing %d to %d of %d hosts' => 'Showing %d to %d of %d hosts',
            'First' => 'First',
            'Prev' => 'Prev',
            'Next' => 'Next',
            'Last' => 'Last',
            'Per page' => 'Per page',
            'Current Page' => 'Current Page',
            'Item Configuration' => 'Item Configuration',
            'Rules are evaluated from top to bottom; the first match wins. Item name and key rules can be mixed in any order.' => 'Rules are evaluated from top to bottom; the first match wins. Item name and key rules can be mixed in any order.',
            'Fuzzy matching is case-insensitive and supports substrings and the * wildcard. Exact matching is case-sensitive. If multiple enabled items match one rule, the item with the lowest itemid is selected.' => 'Fuzzy matching is case-insensitive and supports substrings and the * wildcard. Exact matching is case-sensitive. If multiple enabled items match one rule, the item with the lowest itemid is selected.',
            'CPU total is measured in cores, memory total in bytes, and usage in %. For idle or available percentages, select “100 − value”. An empty rule list disables the metric.' => 'CPU total is measured in cores, memory total in bytes, and usage in %. For idle or available percentages, select “100 − value”. An empty rule list disables the metric.',
            'Match Field' => 'Match Field',
            'Match Type' => 'Match Type',
            'Item Name / Key' => 'Item Name / Key',
            'Value Conversion' => 'Value Conversion',
            'Order / Actions' => 'Order / Actions',
            'Add Rule' => 'Add Rule',
            'Save Configuration' => 'Save Configuration',
            'Back to Host List' => 'Back to Host List',
            'Item Name' => 'Item Name',
            'Exact Match' => 'Exact Match',
            'Fuzzy Match' => 'Fuzzy Match',
            'Item name or key' => 'Item name or key',
            'Original Value' => 'Original Value',
            '100 − value' => '100 − value',
            'Move Up' => 'Move Up',
            'Move Down' => 'Move Down',
            'Delete' => 'Delete',
            'Rules' => 'rules',
            'No rules configured' => 'No rules configured',
            'Add the first rule to enable this metric.' => 'Add the first rule to enable this metric.',
            'Invalid security token. Refresh the page and try again.' => 'Invalid security token. Refresh the page and try again.',
            'The configuration is too large.' => 'The configuration is too large.',
            'The configuration must be a JSON object.' => 'The configuration must be a JSON object.',
            'Configuration saved. The host list and group statistics now use the new rules.' => 'Configuration saved. The host list and group statistics now use the new rules.',
            'Cannot read CMDB data/item_rules.json, or the JSON is invalid.' => 'Cannot read CMDB data/item_rules.json, or the JSON is invalid.',
            'Each metric must have a rule list with at most 100 rules.' => 'Each metric must have a rule list with at most 100 rules.',
            'Invalid rule: enter an item name or key of 1–255 bytes and select valid matching and conversion options.' => 'Invalid rule: enter an item name or key of 1–255 bytes and select valid matching and conversion options.',
            'Cannot save: grant the web service user write access to the CMDB data directory.' => 'Cannot save: grant the web service user write access to the CMDB data directory.',
            'Cannot save: check write permissions on the CMDB data directory.' => 'Cannot save: check write permissions on the CMDB data directory.',
            'Invalid JSON configuration.' => 'Invalid JSON configuration.',
            'Available' => 'Available',
            'Unavailable' => 'Unavailable',
            'Unknown' => 'Unknown',
            'Disabled' => 'Disabled',
            'Maintenance' => 'Maintenance',
            'host' => 'host',
            'core' => 'core',
        ],
        'pt_BR' => [
            '%s Week %s' => '%s, semana %s',
            'CMDB' => 'CMDB',
            'Configuration Management Database' => 'Banco de dados de gerenciamento de configuração',
            'Search hosts...' => 'Pesquisar hosts...',
            'Search by hostname or IP' => 'Pesquisar por nome do host ou IP',
            'All Groups' => 'Todos os grupos',
            'Select host group' => 'Selecione o grupo de hosts',
            'Host Name' => 'Nome do host',
            'IP Address' => 'Endereço IP',
            'Interface Type' => 'Tipo de interface',
            'All Interfaces' => 'Todas as interfaces',
            'CPU Total' => 'Total de CPU',
            'CPU Usage' => 'Uso de CPU',
            'Memory Total' => 'Memória total',
            'Memory Usage' => 'Uso de memória',
            'Host Group' => 'Grupo de hosts',
            'System Name' => 'Nome do sistema',
            'Architecture' => 'Arquitetura',
            'Operating System' => 'Sistema operacional',
            'Kernel Version' => 'Versão do kernel',
            'Agent' => 'Agente',
            'SNMP' => 'SNMP',
            'IPMI' => 'IPMI',
            'JMX' => 'JMX',
            'No hosts found' => 'Nenhum host encontrado',
            'Loading...' => 'Carregando...',
            'Search' => 'Pesquisar',
            'Clear' => 'Limpar',
            'Total Hosts' => 'Total de hosts',
            'Host Groups' => 'Grupos de hosts',
            'Host List' => 'Lista de hosts',
            'Search host groups...' => 'Pesquisar grupos de hosts...',
            'Enter group name' => 'Digite o nome do grupo',
            'Group Name' => 'Nome do grupo',
            'Host Count' => 'Quantidade de hosts',
            'Active Hosts' => 'Hosts ativos',
            'Search by group name' => 'Pesquisar por nome do grupo',
            'Search groups...' => 'Pesquisar grupos...',
            'Status' => 'Status',
            'No groups found' => 'Nenhum grupo encontrado',
            'Empty Group' => 'Grupo vazio',
            'Active Group' => 'Grupo ativo',
            'Basic Group' => 'Grupo básico',
            'hosts' => 'hosts',
            'cores' => 'núcleos',
            'Invalid input parameters.' => 'Parâmetros de entrada inválidos.',
            'Showing %d to %d of %d hosts' => 'Exibindo %d a %d de %d hosts',
            'First' => 'Primeira',
            'Prev' => 'Anterior',
            'Next' => 'Próxima',
            'Last' => 'Última',
            'Per page' => 'Por página',
            'Current Page' => 'Página atual',
            'Item Configuration' => 'Configuração de itens',
            'Rules are evaluated from top to bottom; the first match wins. Item name and key rules can be mixed in any order.' => 'As regras são avaliadas de cima para baixo; a primeira correspondência prevalece. Regras por nome e por chave do item podem ser combinadas em qualquer ordem.',
            'Fuzzy matching is case-insensitive and supports substrings and the * wildcard. Exact matching is case-sensitive. If multiple enabled items match one rule, the item with the lowest itemid is selected.' => 'A correspondência aproximada não diferencia maiúsculas de minúsculas e aceita trechos e o curinga *. A correspondência exata diferencia maiúsculas de minúsculas. Se vários itens ativos corresponderem a uma regra, será usado o item com o menor itemid.',
            'CPU total is measured in cores, memory total in bytes, and usage in %. For idle or available percentages, select “100 − value”. An empty rule list disables the metric.' => 'O total de CPU é medido em núcleos, o total de memória em bytes e o uso em %. Para percentuais de ocioso ou disponível, selecione “100 − valor”. Uma lista de regras vazia desativa a métrica.',
            'Match Field' => 'Campo de correspondência',
            'Match Type' => 'Tipo de correspondência',
            'Item Name / Key' => 'Nome / chave do item',
            'Value Conversion' => 'Conversão de valor',
            'Order / Actions' => 'Ordem / Ações',
            'Add Rule' => 'Adicionar regra',
            'Save Configuration' => 'Salvar configuração',
            'Back to Host List' => 'Voltar à lista de hosts',
            'Item Name' => 'Nome do item',
            'Exact Match' => 'Correspondência exata',
            'Fuzzy Match' => 'Correspondência aproximada',
            'Item name or key' => 'Nome ou chave do item',
            'Original Value' => 'Valor original',
            '100 − value' => '100 − valor',
            'Move Up' => 'Mover para cima',
            'Move Down' => 'Mover para baixo',
            'Delete' => 'Excluir',
            'Rules' => 'regras',
            'No rules configured' => 'Nenhuma regra configurada',
            'Add the first rule to enable this metric.' => 'Adicione a primeira regra para ativar esta métrica.',
            'Invalid security token. Refresh the page and try again.' => 'Token de segurança inválido. Atualize a página e tente novamente.',
            'The configuration is too large.' => 'A configuração é grande demais.',
            'The configuration must be a JSON object.' => 'A configuração deve ser um objeto JSON.',
            'Configuration saved. The host list and group statistics now use the new rules.' => 'Configuração salva. A lista de hosts e as estatísticas de grupos já usam as novas regras.',
            'Cannot read CMDB data/item_rules.json, or the JSON is invalid.' => 'Não foi possível ler data/item_rules.json do CMDB, ou o JSON é inválido.',
            'Each metric must have a rule list with at most 100 rules.' => 'Cada métrica deve ter uma lista com no máximo 100 regras.',
            'Invalid rule: enter an item name or key of 1–255 bytes and select valid matching and conversion options.' => 'Regra inválida: informe um nome ou chave de item com 1–255 bytes e selecione opções válidas de correspondência e conversão.',
            'Cannot save: grant the web service user write access to the CMDB data directory.' => 'Não foi possível salvar: conceda ao usuário do serviço web permissão de escrita no diretório de dados do CMDB.',
            'Cannot save: check write permissions on the CMDB data directory.' => 'Não foi possível salvar: verifique as permissões de escrita no diretório de dados do CMDB.',
            'Invalid JSON configuration.' => 'Configuração JSON inválida.',
            'Available' => 'Disponível',
            'Unavailable' => 'Indisponível',
            'Unknown' => 'Desconhecido',
            'Disabled' => 'Desativado',
            'Maintenance' => 'Manutenção',
            'host' => 'host',
            'core' => 'núcleo'
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
            // Check whether $GLOBALS contains CWebUser information
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (\Throwable $e) {
            // Continue with other methods
        }
        
        // Method 2: try global variables (installation flow / page initialization cache)
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (\Throwable $e) {
            // Continue with other methods
        }
        
        // Method 3: from the session (set by the Zabbix frontend after login)
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
     * Try to get the user language setting through the API
     */
    private static function getUserLanguageFromAPI() {
        try {
            // Get the current user ID
            $userid = null;

            // Get the user ID from the session
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // Try different API class names (compatibility with different versions)
            $apiClass = null;
            if (class_exists('API') || class_exists('\API')) {
                $apiClass = '\API';
            } elseif (class_exists('CApiService') || class_exists('\CApiService')) {
                $apiClass = '\CApiService';
            } elseif (class_exists('\Zabbix\Api\ApiService')) {
                $apiClass = '\Zabbix\Api\ApiService';
            } elseif (class_exists('\API')) {
                $apiClass = '\API';
            }

            if ($apiClass && method_exists($apiClass, 'User')) {
                $users = $apiClass::User()->get([
                    'output' => ['lang'],
                    'userids' => $userid,
                    'limit' => 1
                ]);

                if (!empty($users) && isset($users[0]['lang'])) {
                    return $users[0]['lang'];
                }
            }
        } catch (\Throwable $e) {
            // API unavailable or failed
        }

        return null;
    }

    /**
     * Try to read the current user's language setting directly from the database
     */
    private static function getUserLanguageFromDatabase() {
        try {
            // Get the current user ID
            $userid = null;

            // Get the user ID from the session
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // Try to connect to the database (requires the Zabbix database configuration)
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
     * Read the system default language (settings.default_lang or config.default_lang)
     */
    private static function getSystemDefaultLanguage() {
        try {
            // Method 0: prefer the official Zabbix wrapper CSettingsHelper
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

                // Query the settings table first (used by Zabbix 6/7)
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
        // Supports en_US and pt_BR
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Only languages for which we provide translations count as available
     */
    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
    }

    /**
     * Get the translated text
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

    /**
     * Get translated text with parameters
     */
    public static function tf($key, ...$args) {
        $translation = self::t($key);
        return sprintf($translation, ...$args);
    }

    /**
     * Get the current language
     */
    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    /**
     * Reset the language cache (mainly for tests)
     */
    public static function resetLanguage() {
        self::$currentLanguage = null;
    }

    /**
     * Get language detection information (for debugging)
     */
    public static function getLanguageDetectionInfo() {
        return [
            'detected' => self::detectLanguage(),
            'zabbix_user_lang' => self::getUserLanguageFromZabbix(),
            'db_lang' => self::getUserLanguageFromDatabase(),
            'system_lang' => self::getSystemDefaultLanguage(),
            'supported_locales' => array_keys(self::$translations)
        ];
    }

    /**
     * Chinese is no longer supported; kept for backward compatibility.
     */
    public static function isChinese() {
        return false;
    }

    /**
     * Format a date and time for the current language.
     */
    public static function formatDateTime($timestamp, $format = null) {
        if ($format === null) {
            $format = self::detectLanguage() === 'pt_BR' ? 'd/m/Y H:i:s' : 'Y-m-d H:i:s';
        }

        return date($format, $timestamp);
    }

    /**
     * Format a date for the current language.
     */
    public static function formatDate($timestamp, $format = null) {
        if ($format === null) {
            $format = self::detectLanguage() === 'pt_BR' ? 'd/m/Y' : 'Y-m-d';
        }

        return date($format, $timestamp);
    }

    /**
     * Format a report period (day, week or month) for the current language.
     */
    public static function formatPeriod($type, $dateString) {
        $timestamp = is_string($dateString) ? strtotime($dateString) : $dateString;

        if ($timestamp === false) {
            return $dateString; // Return the original string if it cannot be parsed.
        }

        switch ($type) {
            case 'week':
            case 'weekly':
                return self::tf('%s Week %s', date('Y', $timestamp), date('W', $timestamp));
            case 'month':
            case 'monthly':
                return date(self::detectLanguage() === 'pt_BR' ? 'm/Y' : 'Y-m', $timestamp);
            default:
                return self::formatDate($timestamp);
        }
    }
}
