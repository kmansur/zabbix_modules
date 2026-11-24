<?php

namespace Modules\ZabbixDatabaseExport\Lib;

final class LanguageManager {
    private const DEFAULT_LANGUAGE = 'en_US';

    private const TRANSLATIONS = [
        'en_US' => [
            'Install a pg_dump client compatible with the PostgreSQL server version.' => 'Install a pg_dump client compatible with the PostgreSQL server version.',
            'Database export' => 'Database export',
            'Database type' => 'Database type',
            'Database version' => 'Database version',
            'MySQL, MariaDB or PostgreSQL is required.' => 'MySQL, MariaDB or PostgreSQL is required.',
            'The PostgreSQL export failed.' => 'The PostgreSQL export failed.',
            'PostgreSQL export requires proc_open and a compatible pg_dump client.' => 'PostgreSQL export requires proc_open and a compatible pg_dump client.',
            'PostgreSQL password files cannot contain line breaks.' => 'PostgreSQL password files cannot contain line breaks.',
            'pg_dump failed. Check its version, database access and server logs.' => 'pg_dump failed. Check its version, database access and server logs.',
            'Database tables' => 'Database tables',
            'Table name' => 'Table name',
            'Engine' => 'Engine',
            'Estimated size' => 'Estimated size',
            'Search tables' => 'Search tables',
            'Exclude history records' => 'Exclude history records',
            'Select all filtered tables' => 'Select all filtered tables',
            'Selected tables: %d / %d' => 'Selected tables: %d / %d',
            'Table sizes include data and indexes.' => 'Table sizes include data and indexes.',
            'No matching tables.' => 'No matching tables.',
            'No tables are available to export.' => 'No tables are available to export.',
            'Export options' => 'Export options',
            'Table scope' => 'Table scope',
            'All tables' => 'All tables',
            'Selected tables' => 'Selected tables',
            'Unselected tables are exported as structure only.' => 'Unselected tables are exported as structure only.',
            'Contents' => 'Contents',
            'Structure and data' => 'Structure and data',
            'Structure only' => 'Structure only',
            'Data only' => 'Data only',
            'Destination' => 'Destination',
            'Download in browser' => 'Download in browser',
            'Save on server' => 'Save on server',
            'Server directory' => 'Server directory',
            'SQL files over 500 MB are saved to this directory automatically.' => 'SQL files over 500 MB are saved to this directory automatically.',
            'Export SQL' => 'Export SQL',
            'Backup saved on the server' => 'Backup saved on the server',
            'Backup exceeded 500 MB and was saved on the server' => 'Backup exceeded 500 MB and was saved on the server',
            'The database export failed.' => 'The database export failed.',
            'The database export failed (MySQL error %d).' => 'The database export failed (MySQL error %d).',
            'Choose an export mode.' => 'Choose an export mode.',
            'Select at least one table.' => 'Select at least one table.',
            'The table selection is invalid. Refresh the page and retry.' => 'The table selection is invalid. Refresh the page and retry.',
            'Could not write the backup. Check available disk space.' => 'Could not write the backup. Check available disk space.',
            'Could not create a private backup file.' => 'Could not create a private backup file.',
            'Could not save the backup. Check permissions or retry in a second.' => 'Could not save the backup. Check permissions or retry in a second.',
            'The server backup directory configuration is invalid.' => 'The server backup directory configuration is invalid.',
            'Choose a private backup directory outside the web root.' => 'Choose a private backup directory outside the web root.',
            'Choose a private, writable directory configured for backups.' => 'Choose a private, writable directory configured for backups.',
            'The backup options are invalid.' => 'The backup options are invalid.',
            'Could not create a temporary backup file.' => 'Could not create a temporary backup file.',
        ],
        'pt_BR' => [
            'Install a pg_dump client compatible with the PostgreSQL server version.' => 'Instale um cliente pg_dump compatível com a versão do servidor PostgreSQL.',
            'Database export' => 'Exportação do banco de dados',
            'Database type' => 'Tipo de banco de dados',
            'Database version' => 'Versão do banco de dados',
            'MySQL, MariaDB or PostgreSQL is required.' => 'É necessário MySQL, MariaDB ou PostgreSQL.',
            'The PostgreSQL export failed.' => 'A exportação do PostgreSQL falhou.',
            'PostgreSQL export requires proc_open and a compatible pg_dump client.' => 'A exportação do PostgreSQL requer proc_open e um cliente pg_dump compatível.',
            'PostgreSQL password files cannot contain line breaks.' => 'Arquivos de senha do PostgreSQL não podem conter quebras de linha.',
            'pg_dump failed. Check its version, database access and server logs.' => 'O pg_dump falhou. Verifique a versão, o acesso ao banco de dados e os logs do servidor.',
            'Database tables' => 'Tabelas do banco de dados',
            'Table name' => 'Nome da tabela',
            'Engine' => 'Engine',
            'Estimated size' => 'Tamanho estimado',
            'Search tables' => 'Pesquisar tabelas',
            'Exclude history records' => 'Excluir registros de histórico',
            'Select all filtered tables' => 'Selecionar todas as tabelas filtradas',
            'Selected tables: %d / %d' => 'Tabelas selecionadas: %d / %d',
            'Table sizes include data and indexes.' => 'Os tamanhos das tabelas incluem dados e índices.',
            'No matching tables.' => 'Nenhuma tabela correspondente.',
            'No tables are available to export.' => 'Não há tabelas disponíveis para exportação.',
            'Export options' => 'Opções de exportação',
            'Table scope' => 'Escopo das tabelas',
            'All tables' => 'Todas as tabelas',
            'Selected tables' => 'Tabelas selecionadas',
            'Unselected tables are exported as structure only.' => 'Tabelas não selecionadas são exportadas apenas com a estrutura.',
            'Contents' => 'Conteúdo',
            'Structure and data' => 'Estrutura e dados',
            'Structure only' => 'Somente estrutura',
            'Data only' => 'Somente dados',
            'Destination' => 'Destino',
            'Download in browser' => 'Baixar no navegador',
            'Save on server' => 'Salvar no servidor',
            'Server directory' => 'Diretório no servidor',
            'SQL files over 500 MB are saved to this directory automatically.' => 'Arquivos SQL acima de 500 MB são salvos automaticamente neste diretório.',
            'Export SQL' => 'Exportar SQL',
            'Backup saved on the server' => 'Backup salvo no servidor',
            'Backup exceeded 500 MB and was saved on the server' => 'O backup ultrapassou 500 MB e foi salvo no servidor',
            'The database export failed.' => 'A exportação do banco de dados falhou.',
            'The database export failed (MySQL error %d).' => 'A exportação do banco de dados falhou (erro MySQL %d).',
            'Choose an export mode.' => 'Escolha um modo de exportação.',
            'Select at least one table.' => 'Selecione pelo menos uma tabela.',
            'The table selection is invalid. Refresh the page and retry.' => 'A seleção de tabelas é inválida. Atualize a página e tente novamente.',
            'Could not write the backup. Check available disk space.' => 'Não foi possível gravar o backup. Verifique o espaço livre em disco.',
            'Could not create a private backup file.' => 'Não foi possível criar um arquivo de backup privado.',
            'Could not save the backup. Check permissions or retry in a second.' => 'Não foi possível salvar o backup. Verifique as permissões ou tente novamente em um segundo.',
            'The server backup directory configuration is invalid.' => 'A configuração do diretório de backup no servidor é inválida.',
            'Choose a private backup directory outside the web root.' => 'Escolha um diretório de backup privado fora da raiz web.',
            'Choose a private, writable directory configured for backups.' => 'Escolha um diretório privado e gravável configurado para backups.',
            'The backup options are invalid.' => 'As opções de backup são inválidas.',
            'Could not create a temporary backup file.' => 'Não foi possível criar um arquivo temporário de backup.'
        ],
    ];

    private static $currentLanguage;

    public static function detectLanguage(): string {
        if (self::$currentLanguage === null) {
            $locale = self::normalize(\CWebUser::$data['lang'] ?? '');
            if ($locale === '' || $locale === 'default') {
                $locale = self::normalize(\CSettingsHelper::getPublic(\CSettingsHelper::DEFAULT_LANG));
            }

            // Supported languages: Brazilian Portuguese (pt*) and English (everything else).
            self::$currentLanguage = preg_match('/^pt(?:_|$)/', $locale) ? 'pt_BR' : self::DEFAULT_LANGUAGE;
        }

        return self::$currentLanguage;
    }

    private static function normalize(string $locale): string {
        return str_replace('-', '_', strtolower(trim($locale)));
    }

    public static function t(string $key): string {
        return self::TRANSLATIONS[self::detectLanguage()][$key]
            ?? self::TRANSLATIONS[self::DEFAULT_LANGUAGE][$key]
            ?? $key;
    }

    public static function tf(string $key, ...$args): string {
        return sprintf(self::t($key), ...$args);
    }
}
