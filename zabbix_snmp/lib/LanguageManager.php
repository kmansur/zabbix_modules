<?php

namespace Modules\ZabbixSnmp\Lib;

class LanguageManager {
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;

    private static $translations = [
        'en_US' => [
            'SNMP MIB Browser' => 'SNMP MIB Browser',
            'SNMP Assistant' => 'SNMP Assistant',
            'Zabbix Mibs' => 'Zabbix Mibs',
            'Zabbix Walk' => 'Zabbix Walk',
            'SNMP Walk Runner' => 'SNMP Walk Runner',
            'Run' => 'Run',
            'Walk OID' => 'Walk OID',
            'Enter OID to walk, e.g. 1.3.6.1.2.1' => 'Enter OID to walk, e.g. 1.3.6.1.2.1',
            'SNMP Walk Results' => 'SNMP Walk Results',
            'Total lines' => 'Total lines',
            'No walk results' => 'No walk results',
            'SNMP walk command failed to execute' => 'SNMP walk command failed to execute',
            'SNMP walk returned no data' => 'SNMP walk returned no data',
            'SNMP walk failed' => 'SNMP walk failed',
            'Browse operating system MIB files from common SNMP directories.' => 'Browse operating system MIB files from common SNMP directories.',
            'MIB Directory' => 'MIB Directory',
            'Select MIB directory' => 'Select MIB directory',
            'SNMP Connection Test' => 'SNMP Connection Test',
            'Connection Mode' => 'Connection Mode',
            'Host Based' => 'Host Based',
            'Manual Input' => 'Manual Input',
            'Host Group' => 'Host Group',
            'Host' => 'Host',
            'Address' => 'Address',
            'Port' => 'Port',
            'Version' => 'Version',
            'Community' => 'Community',
            'Security Name' => 'Security Name',
            'Security Level' => 'Security Level',
            'Auth Protocol' => 'Auth Protocol',
            'Auth Passphrase' => 'Auth Passphrase',
            'Priv Protocol' => 'Priv Protocol',
            'Priv Passphrase' => 'Priv Passphrase',
            'Context Name' => 'Context Name',
            'Current Host SNMP Profile' => 'Current Host SNMP Profile',
            'No SNMP interface found on this host.' => 'No SNMP interface found on this host.',
            'V3 Extra Info' => 'V3 Extra Info',
            'Files in Current Directory' => 'Files in Current Directory',
            'SNMP Objects' => 'SNMP Objects',
            'No SNMP objects parsed from this file.' => 'No SNMP objects parsed from this file.',
            'Name' => 'Name',
            'Kind' => 'Kind',
            'OID' => 'OID',
            'Resolved OID' => 'Resolved OID',
            'Syntax' => 'Syntax',
            'Access' => 'Access',
            'Status' => 'Status',
            'Attributes' => 'Attributes',
            'Description' => 'Description',
            'Line' => 'Line',
            'No.' => 'No.',
            'Previous' => 'Previous',
            'Next' => 'Next',
            'Per page' => 'Per page',
            'Showing %d-%d of %d' => 'Showing %d-%d of %d',
            'Action' => 'Action',
            'Fullscreen' => 'Fullscreen',
            'Value' => 'Value',
            'View Source' => 'View Source',
            'Copy Command' => 'Copy Command',
            'Copy OID' => 'Copy OID',
            'View Raw Data' => 'View Raw Data',
            'Raw Data' => 'Raw Data',
            'Create Item' => 'Create Item',
            'Item Name' => 'Item Name',
            'Confirm' => 'Confirm',
            'Cancel' => 'Cancel',
            'Creating...' => 'Creating...',
            'Created' => 'Created',
            'Please select a host first.' => 'Please select a host first.',
            'Failed to create monitoring item.' => 'Failed to create monitoring item.',
            'Monitoring item created.' => 'Monitoring item created.',
            'Create Template' => 'Create Template',
            'Template Name' => 'Template Name',
            'Template Group' => 'Template Group',
            'Selected items' => 'Selected items',
            'Selected %d items' => 'Selected %d items',
            'Select all' => 'Select all',
            'Clear selection' => 'Clear selection',
            'No items selected.' => 'No items selected.',
            'Please enter a template name.' => 'Please enter a template name.',
            'Please enter a template group.' => 'Please enter a template group.',
            'Enter template name' => 'Enter template name',
            'Enter template group' => 'Enter template group',
            'Template created.' => 'Template created.',
            'Failed to create template.' => 'Failed to create template.',
            'Creating template...' => 'Creating template...',
            'Template name hint' => 'Only English letters, numbers, dots (.), underscores (_) and hyphens (-) are allowed. Chinese is not supported.',
            'Invalid template name.' => 'Template name contains invalid characters. Use English letters, numbers, dots (.), underscores (_) or hyphens (-) only.',
            'Copied' => 'Copied',
            'Success' => 'Success',
            'Failed' => 'Failed',
            'Test' => 'Test',
            'Test Result' => 'Test Result',
            'Last Test' => 'Last Test',
            'Source Preview' => 'Source Preview',
            'Showing lines %d to %d.' => 'Showing lines %d to %d.',
            'Source snippet is truncated.' => 'Source snippet is truncated.',
            'Loading source...' => 'Loading source...',
            'No source available' => 'No source available',
            'Close' => 'Close',
            'No directories with MIB files found.' => 'No directories with MIB files found.',
            'Search MIB files...' => 'Search MIB files...',
            'Search' => 'Search',
            'Clear' => 'Clear',
            'Detected Directories' => 'Detected Directories',
            'Existing Directories' => 'Existing Directories',
            'Total MIB Files' => 'Total MIB Files',
            'Files in Selected Directory' => 'Files in Selected Directory',
            'Directory Status' => 'Directory Status',
            'Available' => 'Available',
            'Missing' => 'Missing',
            'files' => 'files',
            'No MIB directories found.' => 'No MIB directories found.',
            'No MIB files found in this directory.' => 'No MIB files found in this directory.',
            'No MIB files match the current filter.' => 'No MIB files match the current filter.',
            'MIB File Details' => 'MIB File Details',
            'Click a MIB file on the left to view its details.' => 'Click a MIB file on the left to view its details.',
            'The selected MIB file is unavailable.' => 'The selected MIB file is unavailable.',
            'SNMP Test Success' => 'SNMP Test Success',
            'SNMP Test Failed' => 'SNMP Test Failed',
            'OID is empty' => 'OID is empty',
            'OID format is invalid' => 'OID format is invalid',
            'SNMP target address is empty' => 'SNMP target address is empty',
            'No SNMP runtime is available (PHP SNMP extension and snmpget command unavailable).' => 'No SNMP runtime is available (PHP SNMP extension and snmpget command unavailable).',
            'SNMP v2c query failed' => 'SNMP v2c query failed',
            'SNMP v1 query failed' => 'SNMP v1 query failed',
            'SNMP v3 query failed' => 'SNMP v3 query failed',
            'snmpget command failed to execute' => 'snmpget command failed to execute',
            'SNMP query failed' => 'SNMP query failed',
            'SNMPv3 security name is required' => 'SNMPv3 security name is required',
            'File Name' => 'File Name',
            'Relative Path' => 'Relative Path',
            'Full Path' => 'Full Path',
            'Directory' => 'Directory',
            'Size' => 'Size',
            'Modified Time' => 'Modified Time',
            'Line Count' => 'Line Count',
            'Preview Lines' => 'Preview Lines',
            'Extension' => 'Extension',
            'File Preview' => 'File Preview',
            'Preview is limited to the first %d lines.' => 'Preview is limited to the first %d lines.',
            'Unable to read the selected file.' => 'Unable to read the selected file.',
            'Common MIB directories scanned from the operating system.' => 'Common MIB directories scanned from the operating system.',
            'Selected File' => 'Selected File',
            'MIB File' => 'MIB File',
            'Module' => 'Module',
            'Data Type' => 'Data Type',
            'Select a MIB file from the dropdown above.' => 'Select a MIB file from the dropdown above.',
            'Preview truncated' => 'Preview truncated'
        ],
        'pt_BR' => [
            'SNMP MIB Browser' => 'Navegador de MIBs SNMP',
            'SNMP Assistant' => 'Assistente SNMP',
            'Zabbix Mibs' => 'MIBs do Zabbix',
            'Zabbix Walk' => 'Walk do Zabbix',
            'SNMP Walk Runner' => 'Executor de SNMP Walk',
            'Run' => 'Executar',
            'Walk OID' => 'OID do walk',
            'Enter OID to walk, e.g. 1.3.6.1.2.1' => 'Digite o OID para o walk, ex.: 1.3.6.1.2.1',
            'SNMP Walk Results' => 'Resultados do SNMP Walk',
            'Total lines' => 'Total de linhas',
            'No walk results' => 'Nenhum resultado de walk',
            'SNMP walk command failed to execute' => 'Falha ao executar o comando snmpwalk',
            'SNMP walk returned no data' => 'O SNMP walk não retornou dados',
            'SNMP walk failed' => 'Falha no SNMP walk',
            'Browse operating system MIB files from common SNMP directories.' => 'Navegue pelos arquivos MIB do sistema operacional nos diretórios SNMP comuns.',
            'MIB Directory' => 'Diretório de MIBs',
            'Select MIB directory' => 'Selecione o diretório de MIBs',
            'SNMP Connection Test' => 'Teste de conexão SNMP',
            'Connection Mode' => 'Modo de conexão',
            'Host Based' => 'Baseado em host',
            'Manual Input' => 'Entrada manual',
            'Host Group' => 'Grupo de hosts',
            'Host' => 'Host',
            'Address' => 'Endereço',
            'Port' => 'Porta',
            'Version' => 'Versão',
            'Community' => 'Comunidade',
            'Security Name' => 'Nome de segurança',
            'Security Level' => 'Nível de segurança',
            'Auth Protocol' => 'Protocolo de autenticação',
            'Auth Passphrase' => 'Senha de autenticação',
            'Priv Protocol' => 'Protocolo de privacidade',
            'Priv Passphrase' => 'Senha de privacidade',
            'Context Name' => 'Nome do contexto',
            'Current Host SNMP Profile' => 'Perfil SNMP do host atual',
            'No SNMP interface found on this host.' => 'Nenhuma interface SNMP encontrada neste host.',
            'V3 Extra Info' => 'Informações adicionais V3',
            'Files in Current Directory' => 'Arquivos no diretório atual',
            'SNMP Objects' => 'Objetos SNMP',
            'No SNMP objects parsed from this file.' => 'Nenhum objeto SNMP interpretado neste arquivo.',
            'Name' => 'Nome',
            'Kind' => 'Tipo',
            'OID' => 'OID',
            'Resolved OID' => 'OID resolvido',
            'Syntax' => 'Sintaxe',
            'Access' => 'Acesso',
            'Status' => 'Status',
            'Attributes' => 'Atributos',
            'Description' => 'Descrição',
            'Line' => 'Linha',
            'No.' => 'Nº',
            'Previous' => 'Anterior',
            'Next' => 'Próxima',
            'Per page' => 'Por página',
            'Showing %d-%d of %d' => 'Exibindo %d-%d de %d',
            'Action' => 'Ação',
            'Fullscreen' => 'Tela cheia',
            'Value' => 'Valor',
            'View Source' => 'Ver código-fonte',
            'Copy Command' => 'Copiar comando',
            'Copy OID' => 'Copiar OID',
            'View Raw Data' => 'Ver dados brutos',
            'Raw Data' => 'Dados brutos',
            'Create Item' => 'Criar item',
            'Item Name' => 'Nome do item',
            'Confirm' => 'Confirmar',
            'Cancel' => 'Cancelar',
            'Creating...' => 'Criando...',
            'Created' => 'Criado',
            'Please select a host first.' => 'Selecione um host primeiro.',
            'Failed to create monitoring item.' => 'Falha ao criar o item de monitoramento.',
            'Monitoring item created.' => 'Item de monitoramento criado.',
            'Create Template' => 'Criar template',
            'Template Name' => 'Nome do template',
            'Template Group' => 'Grupo de templates',
            'Selected items' => 'Itens selecionados',
            'Selected %d items' => '%d itens selecionados',
            'Select all' => 'Selecionar tudo',
            'Clear selection' => 'Limpar seleção',
            'No items selected.' => 'Nenhum item selecionado.',
            'Please enter a template name.' => 'Digite um nome de template.',
            'Please enter a template group.' => 'Digite um grupo de templates.',
            'Enter template name' => 'Digite o nome do template',
            'Enter template group' => 'Digite o grupo de templates',
            'Template created.' => 'Template criado.',
            'Failed to create template.' => 'Falha ao criar o template.',
            'Creating template...' => 'Criando template...',
            'Template name hint' => 'Somente letras sem acento, números, pontos (.), sublinhados (_) e hifens (-) são permitidos. Caracteres chineses não são suportados.',
            'Invalid template name.' => 'O nome do template contém caracteres inválidos. Use somente letras sem acento, números, pontos (.), sublinhados (_) ou hifens (-).',
            'Copied' => 'Copiado',
            'Success' => 'Sucesso',
            'Failed' => 'Falhou',
            'Test' => 'Testar',
            'Test Result' => 'Resultado do teste',
            'Last Test' => 'Último teste',
            'Source Preview' => 'Pré-visualização do código-fonte',
            'Showing lines %d to %d.' => 'Exibindo as linhas %d a %d.',
            'Source snippet is truncated.' => 'O trecho do código-fonte está truncado.',
            'Loading source...' => 'Carregando código-fonte...',
            'No source available' => 'Código-fonte indisponível',
            'Close' => 'Fechar',
            'No directories with MIB files found.' => 'Nenhum diretório com arquivos MIB encontrado.',
            'Search MIB files...' => 'Pesquisar arquivos MIB...',
            'Search' => 'Pesquisar',
            'Clear' => 'Limpar',
            'Detected Directories' => 'Diretórios detectados',
            'Existing Directories' => 'Diretórios existentes',
            'Total MIB Files' => 'Total de arquivos MIB',
            'Files in Selected Directory' => 'Arquivos no diretório selecionado',
            'Directory Status' => 'Status do diretório',
            'Available' => 'Disponível',
            'Missing' => 'Ausente',
            'files' => 'arquivos',
            'No MIB directories found.' => 'Nenhum diretório de MIBs encontrado.',
            'No MIB files found in this directory.' => 'Nenhum arquivo MIB encontrado neste diretório.',
            'No MIB files match the current filter.' => 'Nenhum arquivo MIB corresponde ao filtro atual.',
            'MIB File Details' => 'Detalhes do arquivo MIB',
            'Click a MIB file on the left to view its details.' => 'Clique em um arquivo MIB à esquerda para ver os detalhes.',
            'The selected MIB file is unavailable.' => 'O arquivo MIB selecionado não está disponível.',
            'SNMP Test Success' => 'Teste SNMP bem-sucedido',
            'SNMP Test Failed' => 'Falha no teste SNMP',
            'OID is empty' => 'O OID está vazio',
            'OID format is invalid' => 'O formato do OID é inválido',
            'SNMP target address is empty' => 'O endereço de destino SNMP está vazio',
            'No SNMP runtime is available (PHP SNMP extension and snmpget command unavailable).' => 'Nenhum runtime SNMP disponível (extensão SNMP do PHP e comando snmpget indisponíveis).',
            'SNMP v2c query failed' => 'Falha na consulta SNMP v2c',
            'SNMP v1 query failed' => 'Falha na consulta SNMP v1',
            'SNMP v3 query failed' => 'Falha na consulta SNMP v3',
            'snmpget command failed to execute' => 'Falha ao executar o comando snmpget',
            'SNMP query failed' => 'Falha na consulta SNMP',
            'SNMPv3 security name is required' => 'O nome de segurança SNMPv3 é obrigatório',
            'File Name' => 'Nome do arquivo',
            'Relative Path' => 'Caminho relativo',
            'Full Path' => 'Caminho completo',
            'Directory' => 'Diretório',
            'Size' => 'Tamanho',
            'Modified Time' => 'Data de modificação',
            'Line Count' => 'Quantidade de linhas',
            'Preview Lines' => 'Linhas de pré-visualização',
            'Extension' => 'Extensão',
            'File Preview' => 'Pré-visualização do arquivo',
            'Preview is limited to the first %d lines.' => 'A pré-visualização é limitada às primeiras %d linhas.',
            'Unable to read the selected file.' => 'Não foi possível ler o arquivo selecionado.',
            'Common MIB directories scanned from the operating system.' => 'Diretórios de MIBs comuns encontrados no sistema operacional.',
            'Selected File' => 'Arquivo selecionado',
            'MIB File' => 'Arquivo MIB',
            'Module' => 'Módulo',
            'Data Type' => 'Tipo de dado',
            'Select a MIB file from the dropdown above.' => 'Selecione um arquivo MIB na lista acima.',
            'Preview truncated' => 'Pré-visualização truncada',
            'No SNMP hosts in selected group' => 'Nenhum host SNMP no grupo selecionado',
            'Result' => 'Resultado'
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
                self::$currentLanguage = self::ensureSupportedOrFallback(self::getSystemDefaultLanguage());
                return self::$currentLanguage;
            }

            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        self::$currentLanguage = self::ensureSupportedOrFallback(self::getSystemDefaultLanguage());
        return self::$currentLanguage;
    }

    public static function getCurrentLanguage(): string {
        return self::detectLanguage();
    }

    public static function isChinese(): bool {
        return false; // Chinese is no longer supported.
    }

    public static function t($key) {
        $language = self::getCurrentLanguage();

        if (isset(self::$translations[$language][$key])) {
            return self::$translations[$language][$key];
        }

        if (isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }

        return $key;
    }

    public static function tf($key, ...$args) {
        return vsprintf(self::t($key), $args);
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

        if (!empty($GLOBALS['USER_DETAILS']['lang'])) {
            return $GLOBALS['USER_DETAILS']['lang'];
        }

        if (!empty($GLOBALS['ZBX_LOCALES']['selected'])) {
            return $GLOBALS['ZBX_LOCALES']['selected'];
        }

        if (!empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }

        if (!empty($_SESSION['lang'])) {
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

            if (!$userid || !defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
                return null;
            }

            $pdo = new \PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                defined('DB_PASSWORD') ? DB_PASSWORD : ''
            );

            $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = :userid LIMIT 1');
            $stmt->execute(['userid' => $userid]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && !empty($row['lang'])) {
                return $row['lang'];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private static function getSystemDefaultLanguage() {
        try {
            if (class_exists('CSettingsHelper') || class_exists('\\CSettingsHelper')) {
                $val = \CSettingsHelper::get('default_lang');
                if (!empty($val)) {
                    return $val;
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['value_str'])) {
                    return $row['value_str'];
                }

                try {
                    $stmt2 = $pdo->query('SELECT default_lang FROM config LIMIT 1');
                    $row2 = $stmt2 ? $stmt2->fetch(\PDO::FETCH_ASSOC) : null;
                    if ($row2 && !empty($row2['default_lang'])) {
                        return $row2['default_lang'];
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function mapZabbixLangToOurs(string $lang): string {
        $lang = strtolower(str_replace('-', '_', trim((string) $lang)));
        if ($lang === self::LANG_DEFAULT) {
            return self::LANG_DEFAULT;
        }
        // Supported languages: Brazilian Portuguese (pt*) and English (everything else).
        if (preg_match('/^pt(?:_|$)/', $lang)) {
            return 'pt_BR';
        }

        return 'en_US';
    }

    private static function ensureSupportedOrFallback(?string $lang): string {
        if ($lang === null || $lang === '') {
            return self::ZBX_DEFAULT_LANG;
        }

        $mapped = self::mapZabbixLangToOurs($lang);
        if ($mapped === self::LANG_DEFAULT) {
            return self::ZBX_DEFAULT_LANG;
        }

        return isset(self::$translations[$mapped]) ? $mapped : self::ZBX_DEFAULT_LANG;
    }
}