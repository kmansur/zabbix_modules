<?php

namespace Modules\ZabbixLldp\Lib;

class LanguageManager {

    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            'LLDP Topology' => 'LLDP Topology',
            'Select host group' => 'Select host group',
            'Select host' => 'Select host',
            'All Groups' => 'All Groups',
            'All Hosts' => 'All Hosts',
            'Query' => 'Query',
            'Querying...' => 'Querying...',
            'Host Group' => 'Host Group',
            'Host' => 'Host',
            'Local device' => 'Local device',
            'Remote device' => 'Remote device',
            'Local port' => 'Local port',
            'Remote port' => 'Remote port',
            'Last update' => 'Last update',
            'Links' => 'Links',
            'Devices' => 'Devices',
            'Hosts' => 'Hosts',
            'Stats format' => '%d hosts / %d links / %d devices',
            'Click query to load topology' => 'Select filters and click Query to load topology',
            'No LLDP data found' => 'No LLDP data found',
            'No hosts with LLDP template' => 'No hosts linked to the LLDP template',
            'Template not found' => 'LLDP template not found. Check template_name in data/config.json',
            'Invalid input parameters.' => 'Invalid input parameters.',
            'Query failed' => 'Query failed',
            'No permission.' => 'No permission.',
            'Local' => 'Local',
            'Remote' => 'Remote',
            'Skipped items' => 'Skipped items',
            'Zoom in' => 'Zoom in',
            'Zoom out' => 'Zoom out',
            'Details' => 'Details',
            'Click a node or link to view details' => 'Click a node or link to view details',
            'Node type' => 'Node type',
            'Connected links' => 'Connected links',
            'Link details' => 'Link details',
            'Link filter' => 'Link filter',
            'Filter links by device or port' => 'Filter links by device or port',
            'Clear filter' => 'Clear filter',
            'Matched links' => 'Matched links: %d',
            'Host name' => 'Host name',
            'Problem count' => 'Problem count',
            'No active problem' => 'No active problem',
            'Problem details' => 'Problem details',
            'Severity: Not classified' => 'Severity: Not classified',
            'Severity: Information' => 'Severity: Information',
            'Severity: Warning' => 'Severity: Warning',
            'Severity: Average' => 'Severity: Average',
            'Severity: High' => 'Severity: High',
            'Severity: Disaster' => 'Severity: Disaster',
        ],
        'pt_BR' => [
            'LLDP Topology' => 'Topologia LLDP',
            'Select host group' => 'Selecione o grupo de hosts',
            'Select host' => 'Selecione o host',
            'All Groups' => 'Todos os grupos',
            'All Hosts' => 'Todos os hosts',
            'Query' => 'Consultar',
            'Querying...' => 'Consultando...',
            'Host Group' => 'Grupo de hosts',
            'Host' => 'Host',
            'Local device' => 'Dispositivo local',
            'Remote device' => 'Dispositivo remoto',
            'Local port' => 'Porta local',
            'Remote port' => 'Porta remota',
            'Last update' => 'Última atualização',
            'Links' => 'Enlaces',
            'Devices' => 'Dispositivos',
            'Hosts' => 'Hosts',
            'Stats format' => '%d hosts / %d enlaces / %d dispositivos',
            'Click query to load topology' => 'Selecione os filtros e clique em Consultar para carregar a topologia',
            'No LLDP data found' => 'Nenhum dado LLDP encontrado',
            'No hosts with LLDP template' => 'Nenhum host vinculado ao template LLDP',
            'Template not found' => 'Template LLDP não encontrado. Verifique template_name em data/config.json',
            'Invalid input parameters.' => 'Parâmetros de entrada inválidos.',
            'Query failed' => 'Falha na consulta',
            'No permission.' => 'Sem permissão.',
            'Local' => 'Local',
            'Remote' => 'Remoto',
            'Skipped items' => 'Itens ignorados',
            'Zoom in' => 'Ampliar',
            'Zoom out' => 'Reduzir',
            'Details' => 'Detalhes',
            'Click a node or link to view details' => 'Clique em um nó ou enlace para ver os detalhes',
            'Node type' => 'Tipo de nó',
            'Connected links' => 'Enlaces conectados',
            'Link details' => 'Detalhes do enlace',
            'Link filter' => 'Filtro de enlaces',
            'Filter links by device or port' => 'Filtrar enlaces por dispositivo ou porta',
            'Clear filter' => 'Limpar filtro',
            'Matched links' => 'Enlaces correspondentes: %d',
            'Host name' => 'Nome do host',
            'Problem count' => 'Quantidade de incidentes',
            'No active problem' => 'Nenhum incidente ativo',
            'Problem details' => 'Detalhes do incidente',
            'Severity: Not classified' => 'Severidade: Não classificada',
            'Severity: Information' => 'Severidade: Informação',
            'Severity: Warning' => 'Severidade: Atenção',
            'Severity: Average' => 'Severidade: Média',
            'Severity: High' => 'Severidade: Alta',
            'Severity: Disaster' => 'Severidade: Desastre',
            'Show %d more devices' => 'Mostrar mais %d dispositivos',
            'Collapse branch' => 'Recolher ramo',
            'Hidden device alert' => 'Alerta de dispositivo oculto',
            'Visible devices: %d' => 'Dispositivos visíveis: %d'
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
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
        }

        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        return null;
    }

    private static function getSystemDefaultLanguage() {
        try {
            if (class_exists('CSettingsHelper') && method_exists('CSettingsHelper', 'get')) {
                $val = \CSettingsHelper::get('default_lang');
                if (!empty($val)) {
                    return $val;
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
        return isset(self::$translations[$mapped]) ? $mapped : self::ZBX_DEFAULT_LANG;
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
}
