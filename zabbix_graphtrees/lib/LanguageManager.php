<?php

namespace Modules\ZabbixGraphTrees\Lib;

class LanguageManager {
    /**
     * Identifiers and defaults consistent with the Zabbix frontend
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            'Graph Trees' => 'Graph Trees',
            'Resource Tree' => 'Resource Tree',
            'Host Groups' => 'Host Groups',
            'Hosts' => 'Hosts',
            'Search...' => 'Search...',
            'Expand All' => 'Expand All',
            'Collapse All' => 'Collapse All',
            'Time Range' => 'Time Range',
            'Last 10 Minutes' => 'Last 10 Minutes',
            'Last 30 Minutes' => 'Last 30 Minutes',
            'Last Hour' => 'Last Hour',
            'Last 3 Hours' => 'Last 3 Hours',
            'Last 6 Hours' => 'Last 6 Hours',
            'Last 12 Hours' => 'Last 12 Hours',
            'Last 24 Hours' => 'Last 24 Hours',
            'Last 2 Days' => 'Last 2 Days',
            'Last 7 Days' => 'Last 7 Days',
            'Last 30 Days' => 'Last 30 Days',
            'Columns' => 'Columns',
            'Auto Refresh' => 'Auto Refresh',
            'Off' => 'Off',
            'seconds' => 'seconds',
            'Refresh' => 'Refresh',
            'All Graphs' => 'All Graphs',
            'Other' => 'Other',
            'No graphs available' => 'No graphs available',
            'No categories found' => 'No categories found',
            'Select a host to view graphs' => 'Select a host to view graphs',
            'Loading...' => 'Loading...',
            'Failed to load data' => 'Failed to load data',
            'Close' => 'Close',
            'Pre-configured' => 'Pre-configured',
            'Auto-generated' => 'Auto-generated',
            'graphs' => 'graphs',
            'No data' => 'No data',
            'Custom' => 'Custom',
            'From' => 'From',
            'To' => 'To',
            'Apply' => 'Apply',
            'Cancel' => 'Cancel',
            'Quick Select' => 'Quick Select',
            'Custom Range' => 'Custom Range',
            'Start time' => 'Start time',
            'End time' => 'End time',
            'Time zone' => 'Time zone',
            'Select a valid start and end time.' => 'Select a valid start and end time.',
            'Start time must be earlier than end time.' => 'Start time must be earlier than end time.',
            'Export image' => 'Export image',
            'Exporting...' => 'Exporting...',
            'Failed to export image. Please try again.' => 'Failed to export image. Please try again.',
            'Time range must be at least one minute.' => 'Time range must be at least one minute.',
            'Time range exceeds the maximum allowed by Zabbix.' => 'Time range exceeds the maximum allowed by Zabbix.',
            'Failed to validate time range. Please try again.' => 'Failed to validate time range. Please try again.'
        ],
        'pt_BR' => [
            'Graph Trees' => 'Árvores de gráficos',
            'Resource Tree' => 'Árvore de recursos',
            'Host Groups' => 'Grupos de hosts',
            'Hosts' => 'Hosts',
            'Search...' => 'Pesquisar...',
            'Expand All' => 'Expandir tudo',
            'Collapse All' => 'Recolher tudo',
            'Time Range' => 'Período',
            'Last 10 Minutes' => 'Últimos 10 minutos',
            'Last 30 Minutes' => 'Últimos 30 minutos',
            'Last Hour' => 'Última hora',
            'Last 3 Hours' => 'Últimas 3 horas',
            'Last 6 Hours' => 'Últimas 6 horas',
            'Last 12 Hours' => 'Últimas 12 horas',
            'Last 24 Hours' => 'Últimas 24 horas',
            'Last 2 Days' => 'Últimos 2 dias',
            'Last 7 Days' => 'Últimos 7 dias',
            'Last 30 Days' => 'Últimos 30 dias',
            'Columns' => 'Colunas',
            'Auto Refresh' => 'Atualização automática',
            'Off' => 'Desligado',
            'seconds' => 'segundos',
            'Refresh' => 'Atualizar',
            'All Graphs' => 'Todos os gráficos',
            'Other' => 'Outros',
            'No graphs available' => 'Nenhum gráfico disponível',
            'No categories found' => 'Nenhuma categoria encontrada',
            'Select a host to view graphs' => 'Selecione um host para ver os gráficos',
            'Loading...' => 'Carregando...',
            'Failed to load data' => 'Falha ao carregar os dados',
            'Close' => 'Fechar',
            'Pre-configured' => 'Pré-configurado',
            'Auto-generated' => 'Gerado automaticamente',
            'graphs' => 'gráficos',
            'No data' => 'Sem dados',
            'Custom' => 'Personalizado',
            'From' => 'De',
            'To' => 'Até',
            'Apply' => 'Aplicar',
            'Cancel' => 'Cancelar',
            'Quick Select' => 'Seleção rápida',
            'Custom Range' => 'Intervalo personalizado',
            'Start time' => 'Hora inicial',
            'End time' => 'Hora final',
            'Time zone' => 'Fuso horário',
            'Select a valid start and end time.' => 'Selecione uma hora inicial e final válidas.',
            'Start time must be earlier than end time.' => 'A hora inicial deve ser anterior à hora final.',
            'Export image' => 'Exportar imagem',
            'Exporting...' => 'Exportando...',
            'Failed to export image. Please try again.' => 'Falha ao exportar a imagem. Tente novamente.',
            'Time range must be at least one minute.' => 'O período deve ter pelo menos um minuto.',
            'Time range exceeds the maximum allowed by Zabbix.' => 'O período excede o máximo permitido pelo Zabbix.',
            'Failed to validate time range. Please try again.' => 'Falha ao validar o período. Tente novamente.'
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
     * Whether the language is Chinese
     */
    public static function isChinese() {
        return false; // Chinese is no longer supported.
    }
}
