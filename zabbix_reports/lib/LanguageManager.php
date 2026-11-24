<?php

namespace Modules\ZabbixReports\Lib;

class LanguageManager {
    /**
     * Identifiers and defaults consistent with the Zabbix frontend
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            'Zabbix Reports' => 'Zabbix Reports',
            'Daily Report' => 'Daily Report',
            'Weekly Report' => 'Weekly Report', 
            'Monthly Report' => 'Monthly Report',
            'Zabbix Daily Report' => 'Zabbix Daily Report',
            'Zabbix Weekly Report' => 'Zabbix Weekly Report',
            'Zabbix Monthly Report' => 'Zabbix Monthly Report',
            'Export PDF' => 'Export PDF',
            'Send Email' => 'Send Email (In Development)',
            'Preview Data' => 'Preview Data',
            'Report Date' => 'Report Date',
            'Problem Count' => 'Problem Count',
            'Resolved Count' => 'Resolved Count',
            'Top Problem Hosts' => 'Top Problem Hosts',
            'Top CPU Usage Hosts' => 'Top CPU Usage Hosts',
            'Top Memory Usage Hosts' => 'Top Memory Usage Hosts',
            'Host Name' => 'Host Name',
            'Alert Name' => 'Alert Name',
            'Alert Time' => 'Alert Time',
            'Recovery Time' => 'Recovery Time',
            'Host Group' => 'Host Group',
            'CPU Usage' => 'CPU Usage',
            'CPU Total' => 'CPU Total',
            'Memory Usage' => 'Memory Usage',
            'Memory Total' => 'Memory Total',
            'Memory Total (GB)' => 'Memory Total (GB)',
            'GB' => 'GB',
            'No alerts found' => 'No alerts found',
            'No host data available' => 'No host data available',
            'No data available' => 'No data available',
            'Statistics Period' => 'Statistics Period',
            'Alert Information' => 'Alert Information',
            'Host Group Information' => 'Host Group Information',
            'CPU Information (TOP 5)' => 'CPU Information (TOP 5)',
            'Memory Information (TOP 5)' => 'Memory Information (TOP 5)',
            'Problem Hosts' => 'Problem Hosts',
            'Preview data (consistent with export format)' => 'Preview data (consistent with export format)',
            'Part 1: Alert Information' => 'Part 1: Alert Information',
            'Part 2: Host Group Information' => 'Part 2: Host Group Information',
            'Showing first 50 alerts, total %d alerts' => '(Showing first 50 alerts, total %d alerts)',
            'No alerts in this period' => 'No alerts in this period',
            'Today' => 'Today',
            'This Week' => 'This Week',
            'This Month' => 'This Month',
            'In Development' => 'In Development',
            'problems' => 'problems',
            // Custom Report translations
            'Custom Report' => 'Custom Report',
            'Zabbix Custom Report' => 'Zabbix Custom Report',
            'Start Date' => 'Start Date',
            'End Date' => 'End Date',
            'Generate Report' => 'Generate Report',
            'Report Period' => 'Report Period',
            'Please select start and end dates to generate a custom report. Maximum date range is 90 days.' => 'Please select start and end dates to generate a custom report. Maximum date range is 90 days.',
            'Invalid date format' => 'Invalid date format',
            'Start date must be before end date' => 'Start date must be before end date',
            'The selected date range cannot exceed 90 days.' => 'The selected date range cannot exceed 90 days.',
            'Export HTML' => 'Export HTML',
            'Summary' => 'Summary',
            'Problem Events' => 'Problem Events',
            'Resolved Events' => 'Resolved Events',
            'Total Alerts' => 'Total Alerts',
            'Total Hosts' => 'Total Hosts',
            'Alert Details' => 'Alert Details',
            'Latest 50 alerts' => 'Latest 50 alerts',
            'Time' => 'Time',
            'Host' => 'Host',
            'Alert Description' => 'Alert Description',
            'Top 10 Hosts with Most Alerts' => 'Top 10 Hosts with Most Alerts',
            'Alert Count' => 'Alert Count',
            'Top 10 Hosts by CPU Usage' => 'Top 10 Hosts by CPU Usage',
            'Top 10 Hosts by Memory Usage' => 'Top 10 Hosts by Memory Usage',
            'CPU Information (TOP 10)' => 'CPU Information (TOP 10)',
            'Memory Information (TOP 10)' => 'Memory Information (TOP 10)',
            'No Group' => 'No Group',
            'Host Groups' => 'Host Groups',
            'Search...' => 'Search...',
            'Severity' => 'Severity',
            'Not classified' => 'Not classified',
            'Information' => 'Information',
            'Warning' => 'Warning',
            'Average' => 'Average',
            'High' => 'High',
            'Disaster' => 'Disaster',
            'Duration' => 'Duration',
            'Generated by Zabbix Reports Module' => 'Generated by Zabbix Reports Module',
            'Unknown Host' => 'Unknown Host',
            'Disabled or Deleted' => 'Disabled or Deleted',
            'Severity Radar' => 'Severity Radar',
            'Host Group Problem Distribution' => 'Host Group Problem Distribution',
            'Other Groups' => 'Other Groups',
            'No Alert Data' => 'No Alert Data',
            'All Groups' => 'All Groups',
            'All Interface Types' => 'All Interface Types',
            'Interface Type' => 'Interface Type',
            'Apply Filter' => 'Apply Filter',
            'rows' => 'rows',
            'of' => 'of',
            'The start date cannot be later than the end date.' => 'The start date cannot be later than the end date.',
        ],
        'pt_BR' => [
            'Zabbix Reports' => 'Relatórios Zabbix',
            'Daily Report' => 'Relatório diário',
            'Weekly Report' => 'Relatório semanal',
            'Monthly Report' => 'Relatório mensal',
            'Zabbix Daily Report' => 'Relatório diário do Zabbix',
            'Zabbix Weekly Report' => 'Relatório semanal do Zabbix',
            'Zabbix Monthly Report' => 'Relatório mensal do Zabbix',
            'Export PDF' => 'Exportar PDF',
            'Send Email' => 'Enviar e-mail (em desenvolvimento)',
            'Preview Data' => 'Pré-visualizar dados',
            'Report Date' => 'Data do relatório',
            'Problem Count' => 'Quantidade de incidentes',
            'Resolved Count' => 'Quantidade resolvida',
            'Top Problem Hosts' => 'Hosts com mais incidentes',
            'Top CPU Usage Hosts' => 'Hosts com maior uso de CPU',
            'Top Memory Usage Hosts' => 'Hosts com maior uso de memória',
            'Host Name' => 'Nome do host',
            'Alert Name' => 'Nome do alerta',
            'Alert Time' => 'Hora do alerta',
            'Recovery Time' => 'Hora da recuperação',
            'Host Group' => 'Grupo de hosts',
            'CPU Usage' => 'Uso de CPU',
            'CPU Total' => 'Total de CPU',
            'Memory Usage' => 'Uso de memória',
            'Memory Total' => 'Memória total',
            'Memory Total (GB)' => 'Memória total (GB)',
            'GB' => 'GB',
            'No alerts found' => 'Nenhum alerta encontrado',
            'No host data available' => 'Nenhum dado de host disponível',
            'No data available' => 'Nenhum dado disponível',
            'Statistics Period' => 'Período estatístico',
            'Alert Information' => 'Informações de alertas',
            'Host Group Information' => 'Informações de grupos de hosts',
            'CPU Information (TOP 5)' => 'Informações de CPU (TOP 5)',
            'Memory Information (TOP 5)' => 'Informações de memória (TOP 5)',
            'Problem Hosts' => 'Hosts com incidentes',
            'Preview data (consistent with export format)' => 'Pré-visualização dos dados (igual ao formato de exportação)',
            'Part 1: Alert Information' => 'Parte 1: Informações de alertas',
            'Part 2: Host Group Information' => 'Parte 2: Informações de grupos de hosts',
            'Showing first 50 alerts, total %d alerts' => '(Exibindo os primeiros 50 alertas, total de %d alertas)',
            'No alerts in this period' => 'Nenhum alerta neste período',
            'Today' => 'Hoje',
            'This Week' => 'Esta semana',
            'This Month' => 'Este mês',
            'In Development' => 'Em desenvolvimento',
            'problems' => 'incidentes',
            'Custom Report' => 'Relatório personalizado',
            'Zabbix Custom Report' => 'Relatório personalizado do Zabbix',
            'Start Date' => 'Data inicial',
            'End Date' => 'Data final',
            'Generate Report' => 'Gerar relatório',
            'Report Period' => 'Período do relatório',
            'Please select start and end dates to generate a custom report. Maximum date range is 90 days.' => 'Selecione as datas inicial e final para gerar um relatório personalizado. O intervalo máximo é de 90 dias.',
            'Invalid date format' => 'Formato de data inválido',
            'Start date must be before end date' => 'A data inicial deve ser anterior à data final',
            'The selected date range cannot exceed 90 days.' => 'O intervalo selecionado não pode exceder 90 dias.',
            'Export HTML' => 'Exportar HTML',
            'Summary' => 'Resumo',
            'Problem Events' => 'Eventos de incidente',
            'Resolved Events' => 'Eventos resolvidos',
            'Total Alerts' => 'Total de alertas',
            'Total Hosts' => 'Total de hosts',
            'Alert Details' => 'Detalhes dos alertas',
            'Latest 50 alerts' => 'Últimos 50 alertas',
            'Time' => 'Hora',
            'Host' => 'Host',
            'Alert Description' => 'Descrição do alerta',
            'Top 10 Hosts with Most Alerts' => 'Top 10 hosts com mais alertas',
            'Alert Count' => 'Quantidade de alertas',
            'Top 10 Hosts by CPU Usage' => 'Top 10 hosts por uso de CPU',
            'Top 10 Hosts by Memory Usage' => 'Top 10 hosts por uso de memória',
            'CPU Information (TOP 10)' => 'Informações de CPU (TOP 10)',
            'Memory Information (TOP 10)' => 'Informações de memória (TOP 10)',
            'No Group' => 'Sem grupo',
            'Host Groups' => 'Grupos de hosts',
            'Search...' => 'Pesquisar...',
            'Severity' => 'Severidade',
            'Not classified' => 'Não classificada',
            'Information' => 'Informação',
            'Warning' => 'Atenção',
            'Average' => 'Média',
            'High' => 'Alta',
            'Disaster' => 'Desastre',
            'Duration' => 'Duração',
            'Generated by Zabbix Reports Module' => 'Gerado pelo módulo Zabbix Reports',
            'Unknown Host' => 'Host desconhecido',
            'Disabled or Deleted' => 'Desativado ou excluído',
            'Severity Radar' => 'Radar de severidade',
            'Host Group Problem Distribution' => 'Distribuição de incidentes por grupo de hosts',
            'Other Groups' => 'Outros grupos',
            'No Alert Data' => 'Sem dados de alerta',
            'All Groups' => 'Todos os grupos',
            'All Interface Types' => 'Todos os tipos de interface',
            'Interface Type' => 'Tipo de interface',
            'Apply Filter' => 'Aplicar filtro',
            'rows' => 'linhas',
            'of' => 'de',
            'The start date cannot be later than the end date.' => 'A data inicial não pode ser posterior à data final.',
            '=== Zabbix item diagnostics ===' => '=== Diagnóstico de itens do Zabbix ===',
            'Time range: %s to %s' => 'Período: %s a %s',
            'Host: %s (ID: %s)' => 'Host: %s (ID: %s)',
            'Total items: %d' => 'Total de itens: %d',
            'CPU items:' => 'Itens de CPU:',
            'No CPU items found' => 'Nenhum item de CPU encontrado',
            '- Name: %s' => '- Nome: %s',
            'Key: %s' => 'Chave: %s',
            'Memory items:' => 'Itens de memória:',
            'No memory items found' => 'Nenhum item de memória encontrado',
            'ItemFinder test results:' => 'Resultados do teste do ItemFinder:',
            'Found - name: %s, key: %s, value: ' => 'Encontrado - nome: %s, chave: %s, valor: ',
            'Not found' => 'Não encontrado',
            '=== Item search suggestions ===' => '=== Sugestões de pesquisa de itens ===',
            'The output above shows the actual item names and keys.' => 'A saída acima mostra os nomes e as chaves reais dos itens.',
            'Look for items containing the following keywords:' => 'Procure itens que contenham as seguintes palavras-chave:',
            '- CPU utilization: \'utilization\', \'usage\', \'util\'' => '- Uso de CPU: \'utilization\', \'usage\', \'util\'',
            '- CPU count: \'number\', \'cores\', \'count\'' => '- Quantidade de CPUs: \'number\', \'cores\', \'count\'',
            '- Memory utilization: \'utilization\', \'usage\', \'util\'' => '- Uso de memória: \'utilization\', \'usage\', \'util\'',
            '- Total memory: \'total\', \'size\'' => '- Memória total: \'total\', \'size\'',
            'No monitored hosts found.' => 'Nenhum host monitorado encontrado.',
            '=== Zabbix item scanner ===' => '=== Varredura de itens do Zabbix ===',
            'Scanning all items of the first 5 hosts...' => 'Varrendo todos os itens dos 5 primeiros hosts...',
            '%s (%d items):' => '%s (%d itens):',
            'Units: %s' => 'Unidades: %s',
            'Type: ' => 'Tipo: ',
            '=== Suggested search patterns ===' => '=== Padrões de pesquisa sugeridos ===',
            'Based on the scan results, try the following search patterns:' => 'Com base na varredura, experimente os seguintes padrões de pesquisa:',
            'Possible CPU utilization item names:' => 'Possíveis nomes de itens de uso de CPU:',
            'Possible CPU count item names:' => 'Possíveis nomes de itens de quantidade de CPUs:',
            'Possible memory utilization item names:' => 'Possíveis nomes de itens de uso de memória:',
            'Possible total memory item names:' => 'Possíveis nomes de itens de memória total:',
            'CPU' => 'CPU',
            'Memory' => 'Memória',
            'Network' => 'Rede',
            'Disk' => 'Disco',
            'System' => 'Sistema',
            'Other' => 'Outros',
            '=== Item key test tool ===' => '=== Ferramenta de teste de chaves de itens ===',
            'Testing the following item keys:' => 'Testando as seguintes chaves de itens:',
            '- system.cpu.num (total CPU cores)' => '- system.cpu.num (total de núcleos de CPU)',
            '- system.cpu.util (CPU utilization)' => '- system.cpu.util (uso de CPU)',
            '- vm.memory.size[total] (total memory)' => '- vm.memory.size[total] (memória total)',
            '- vm.memory.utilization (memory utilization)' => '- vm.memory.utilization (uso de memória)',
            'Total CPU cores' => 'Total de núcleos de CPU',
            'CPU utilization' => 'Uso de CPU',
            'Total memory' => 'Memória total',
            'Memory utilization' => 'Uso de memória',
            '=== Testing with ItemFinder ===' => '=== Testando com o ItemFinder ===',
            'CPU utilization: ' => 'Uso de CPU: ',
            'CPU count: ' => 'Quantidade de CPUs: ',
            'Memory utilization: ' => 'Uso de memória: ',
            'Total memory: ' => 'Memória total: ',
            'Found item %s (key: %s) = ' => 'Item encontrado %s (chave: %s) = ',
            'Testing %s (key: %s):' => 'Testando %s (chave: %s):',
            'No exact item match found' => 'Nenhuma correspondência exata de item encontrada',
            'Partially matching items found:' => 'Itens com correspondência parcial encontrados:',
            '- %s (key: %s, last value: %s, units: %s)' => '- %s (chave: %s, último valor: %s, unidades: %s)',
            'No matching items found' => 'Nenhum item correspondente encontrado',
            'Found item: %s' => 'Item encontrado: %s',
            'Last value: %s' => 'Último valor: %s',
            'Last updated: ' => 'Última atualização: ',
            'Yesterday\'s history (last 5 records):' => 'Histórico de ontem (últimos 5 registros):',
            'No history found for yesterday' => 'Nenhum histórico encontrado para ontem',
            '=== Quick item test ===' => '=== Teste rápido de itens ===',
            'Test host: %s (ID: %s)' => 'Host de teste: %s (ID: %s)',
            '=== Searching CPU items ===' => '=== Pesquisando itens de CPU ===',
            '=== Searching memory items ===' => '=== Pesquisando itens de memória ===',
            '=== Wildcard search ===' => '=== Pesquisa com curinga ===',
            '=== Search by key ===' => '=== Pesquisa por chave ===',
            'Search keyword: %s' => 'Palavra-chave: %s',
            'No matching items found' => 'Nenhum item correspondente encontrado',
            '- %s (key: %s, units: %s)' => '- %s (chave: %s, unidades: %s)',
            'Wildcard search: *%s*' => 'Pesquisa com curinga: *%s*',
            'Key pattern search: %s' => 'Pesquisa por padrão de chave: %s',
            'Float' => 'Numérico (fracionário)',
            'Character' => 'Caractere',
            'Log' => 'Log',
            'Unsigned integer' => 'Numérico (inteiro sem sinal)',
            'Text' => 'Texto',
            'Report sent successfully' => 'Relatório enviado com sucesso',
            '%s to %s' => '%s a %s',
            'Unknown' => 'Desconhecido',
            'W%02d' => 'W%02d',
            '%s Week %s (%s ~ %s)' => '%s, semana %s (%s ~ %s)'
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
        } catch (Throwable $e) {
            // Continue with other methods
        }
        
        // Method 2: try global variables (installation flow / page initialization cache)
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (Throwable $e) {
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
     * Get the user language setting through the API
     */
    private static function getUserLanguageByAPI($userid) {
        try {
            $apiClass = null;
            if (class_exists('API')) {
                $apiClass = 'API';
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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
     * Get the current language code
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
     * Get detailed language detection information (for debugging)
     */
    public static function getLanguageDetectionInfo() {
        $info = [
            'detected_language' => self::detectLanguage(),
            'detection_sources' => [],
            'zabbix_user_info' => [],
            'session_info' => [],
            'cookie_info' => [],
            'system_default_lang' => null
        ];
        
    // Hints about the source of the user language
        
        // Check the Zabbix user data
        try {
            // Check the user information in global variables
            if (isset($GLOBALS['USER_DETAILS'])) {
                $info['zabbix_user_info']['user_details'] = $GLOBALS['USER_DETAILS'];
                if (isset($GLOBALS['USER_DETAILS']['lang'])) {
                    $info['detection_sources']['user_details_lang'] = [
                        'value' => $GLOBALS['USER_DETAILS']['lang'],
                        'mapped' => self::mapZabbixLangToOurs($GLOBALS['USER_DETAILS']['lang']),
            'priority' => 1,
                        'source' => 'USER_DETAILS global variable'
                    ];
                }
            }
        } catch (Throwable $e) {
            $info['zabbix_user_info']['user_details_error'] = $e->getMessage();
        }
        
        // Check the language information in the session
        $sessionKeys = ['zbx_lang', 'lang', 'language', 'user_lang', 'zabbix_lang', 'userid', 'user'];
        foreach ($sessionKeys as $key) {
            if (isset($_SESSION[$key])) {
                $info['session_info'][$key] = $_SESSION[$key];
                
                if (in_array($key, ['zbx_lang', 'lang', 'language', 'user_lang', 'zabbix_lang']) && !empty($_SESSION[$key])) {
                    $info['detection_sources']['session_' . $key] = [
                        'value' => $_SESSION[$key],
                        'mapped' => self::mapZabbixLangToOurs($_SESSION[$key]),
            'priority' => 1,
                        'source' => 'Session - ' . $key
                    ];
                }
            }
        }
        
    // Record the system default language
    $info['system_default_lang'] = self::getSystemDefaultLanguage();
        
        // Add a database connection status check
        $info['database_info'] = [
            'constants_defined' => [
                'DB_HOST' => defined('DB_HOST'),
                'DB_NAME' => defined('DB_NAME'),
                'DB_USER' => defined('DB_USER'),
                'DB_PASSWORD' => defined('DB_PASSWORD')
            ]
        ];
        
        if (defined('DB_HOST')) {
            $info['database_info']['db_host'] = DB_HOST;
        }
        if (defined('DB_NAME')) {
            $info['database_info']['db_name'] = DB_NAME;
        }
        
        return $info;
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
            return self::formatDate($timestamp) . date(' H:i:s', $timestamp);
        }

        return date($format, $timestamp);
    }

    /**
     * Format a date for the current language (dd/mm/yyyy in pt_BR, yyyy-mm-dd otherwise).
     */
    public static function formatDate($timestamp, $format = null) {
        if ($format === null) {
            $format = self::getCurrentLanguage() === 'pt_BR' ? 'd/m/Y' : 'Y-m-d';
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
                $monday = strtotime('last monday', strtotime('tomorrow', $timestamp));
                $wkStart = self::formatDate($monday);
                $wkEnd = self::formatDate(strtotime('next sunday', $monday));
                return self::tf('%s Week %s (%s ~ %s)', date('Y', $timestamp), date('W', $timestamp), $wkStart, $wkEnd);
            case 'month':
            case 'monthly':
                return date(self::getCurrentLanguage() === 'pt_BR' ? 'm/Y' : 'Y-m', $timestamp);
            default:
                return self::formatDate($timestamp);
        }
    }
}
