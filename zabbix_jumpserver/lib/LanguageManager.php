<?php

namespace Modules\ZabbixJumpserver\Lib;

class LanguageManager {

    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            'JumpServer' => 'JumpServer',
            'JumpServer Sync' => 'JumpServer Sync',
            'Select host group' => 'Select host group',
            'Select host' => 'Select host',
            'All Groups' => 'All Groups',
            'All Hosts' => 'All Hosts',
            'Push all host groups' => 'Push all host groups',
            'Push all hosts' => 'Push all hosts',
            'Push' => 'Push',
            'Connect' => 'Connect',
            'Not pushed' => 'Not pushed',
            'Pushed' => 'Pushed',
            'Re-push' => 'Re-push',
            'Host Name' => 'Host Name',
            'IP Address' => 'IP Address',
            'Platform' => 'Platform',
            'Host Group' => 'Host Group',
            'JumpServer Status' => 'JumpServer Status',
            'Action' => 'Action',
            'No hosts found' => 'No hosts found',
            'Total Hosts' => 'Total Hosts',
            'Host Groups' => 'Host Groups',
            'Pushed Hosts' => 'Pushed Hosts',
            'Host List' => 'Host List',
            'Invalid input parameters.' => 'Invalid input parameters.',
            'JumpServer is not configured. Please edit data/config.json.' => 'JumpServer is not configured. Please edit data/config.json.',
            'Pushing...' => 'Pushing...',
            'Push completed' => 'Push completed',
            'Push failed' => 'Push failed',
            'Created' => 'Created',
            'Updated' => 'Updated',
            'Failed' => 'Failed',
            'Accounts linked' => 'Accounts linked',
            'No permission.' => 'No permission.',
            'Confirm push all host groups to JumpServer?' => 'Confirm push all host groups to JumpServer?',
            'Confirm push all hosts to JumpServer?' => 'Confirm push all hosts to JumpServer?',
            'Configuration error' => 'Configuration error',
            'hosts' => 'hosts',
            'groups' => 'groups',
            'Alarm Status' => 'Alarm Status',
            'All States' => 'All States',
            'Search' => 'Search',
            'Search by IP or host name' => 'Search by IP or host name',
            'OK' => 'OK',
            'Not classified' => 'Not classified',
            'Information' => 'Information',
            'Warning' => 'Warning',
            'Average' => 'Average',
            'High' => 'High',
            'Disaster' => 'Disaster',
            'Fetch JumpServer asset IDs' => 'Fetch JumpServer asset IDs',
            'Fetch all assets from JumpServer and match by IP to update host tags?' => 'Fetch all assets from JumpServer and match by IP to update host tags?',
            'Fetching...' => 'Fetching...',
            'Fetch completed' => 'Fetch completed',
            'Fetch failed' => 'Fetch failed',
            'No assets found in JumpServer.' => 'No assets found in JumpServer.',
            'Matched' => 'Matched',
            'Skipped' => 'Skipped',
            'Show alarms' => 'Show alarms',
            'No active alarms' => 'No active alarms',
            'Active alarms' => 'Active alarms',
            'Severity' => 'Severity',
            'Problem' => 'Problem',
            'Time' => 'Time',
        ],
        'pt_BR' => [
            'JumpServer' => 'JumpServer',
            'JumpServer Sync' => 'Sincronização com o JumpServer',
            'Select host group' => 'Selecione o grupo de hosts',
            'Select host' => 'Selecione o host',
            'All Groups' => 'Todos os grupos',
            'All Hosts' => 'Todos os hosts',
            'Push all host groups' => 'Enviar todos os grupos de hosts',
            'Push all hosts' => 'Enviar todos os hosts',
            'Push' => 'Enviar',
            'Connect' => 'Conectar',
            'Not pushed' => 'Não enviado',
            'Pushed' => 'Enviado',
            'Re-push' => 'Reenviar',
            'Host Name' => 'Nome do host',
            'IP Address' => 'Endereço IP',
            'Platform' => 'Plataforma',
            'Host Group' => 'Grupo de hosts',
            'JumpServer Status' => 'Status do JumpServer',
            'Action' => 'Ação',
            'No hosts found' => 'Nenhum host encontrado',
            'Total Hosts' => 'Total de hosts',
            'Host Groups' => 'Grupos de hosts',
            'Pushed Hosts' => 'Hosts enviados',
            'Host List' => 'Lista de hosts',
            'Invalid input parameters.' => 'Parâmetros de entrada inválidos.',
            'JumpServer is not configured. Please edit data/config.json.' => 'O JumpServer não está configurado. Edite data/config.json.',
            'Pushing...' => 'Enviando...',
            'Push completed' => 'Envio concluído',
            'Push failed' => 'Falha no envio',
            'Created' => 'Criado',
            'Updated' => 'Atualizado',
            'Failed' => 'Falhou',
            'Accounts linked' => 'Contas vinculadas',
            'No permission.' => 'Sem permissão.',
            'Confirm push all host groups to JumpServer?' => 'Confirma o envio de todos os grupos de hosts para o JumpServer?',
            'Confirm push all hosts to JumpServer?' => 'Confirma o envio de todos os hosts para o JumpServer?',
            'Configuration error' => 'Erro de configuração',
            'hosts' => 'hosts',
            'groups' => 'grupos',
            'Alarm Status' => 'Status de alarme',
            'All States' => 'Todos os estados',
            'Search' => 'Pesquisar',
            'Search by IP or host name' => 'Pesquisar por IP ou nome do host',
            'OK' => 'OK',
            'Not classified' => 'Não classificada',
            'Information' => 'Informação',
            'Warning' => 'Atenção',
            'Average' => 'Média',
            'High' => 'Alta',
            'Disaster' => 'Desastre',
            'Fetch JumpServer asset IDs' => 'Obter IDs de ativos do JumpServer',
            'Fetch all assets from JumpServer and match by IP to update host tags?' => 'Obter todos os ativos do JumpServer e associá-los por IP para atualizar as tags dos hosts?',
            'Fetching...' => 'Obtendo...',
            'Fetch completed' => 'Obtenção concluída',
            'Fetch failed' => 'Falha na obtenção',
            'No assets found in JumpServer.' => 'Nenhum ativo encontrado no JumpServer.',
            'Matched' => 'Correspondido',
            'Skipped' => 'Ignorado',
            'Show alarms' => 'Mostrar alarmes',
            'No active alarms' => 'Nenhum alarme ativo',
            'Active alarms' => 'Alarmes ativos',
            'Severity' => 'Severidade',
            'Problem' => 'Incidente',
            'Time' => 'Hora'
        ],
    ];

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

        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        return self::getUserLanguageFromDatabase();
    }

    private static function getUserLanguageFromDatabase() {
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
        $translation = self::t($key);
        return sprintf($translation, ...$args);
    }

    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }
}
