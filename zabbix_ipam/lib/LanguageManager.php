<?php

namespace Modules\ZabbixIpam\Lib;

class LanguageManager {
    private static $language;

    private const PORTUGUESE = [
    	'IPAM' => 'IPAM',
    	'IP Management' => 'Gerenciamento de IPs',
    	'IP Details' => 'Detalhes do IP',
    	'Report' => 'Relatório',
    	'IP matrix' => 'Matriz de IPs',
    	'Task Management' => 'Gerenciamento de tarefas',
    	'Manage IPv4 ranges and run ICMP-only availability scans.' => 'Gerencie faixas IPv4 e execute varreduras de disponibilidade somente por ICMP.',
    	'Track ICMP scan progress and review completed background tasks.' => 'Acompanhe o progresso das varreduras ICMP e revise as tarefas em segundo plano concluídas.',
    	'Browse every address, its latest ICMP status, and its matched Zabbix host.' => 'Navegue por todos os endereços, seu último status ICMP e o host do Zabbix associado.',
    	'Quick guide' => 'Guia rápido',
    	'Add a range, start an ICMP scan, then click the IP count to view the availability matrix. Green means alive; gray means unreachable or not scanned.' => 'Adicione uma faixa, inicie uma varredura ICMP e clique na quantidade de IPs para ver a matriz de disponibilidade. Verde significa ativo; cinza significa inacessível ou não varrido.',
    	'Association rules' => 'Regras de associação',
    	'A host is associated automatically when one of its Zabbix interfaces uses the same IPv4 address. Click the host name to open its latest data page.' => 'Um host é associado automaticamente quando uma de suas interfaces no Zabbix usa o mesmo endereço IPv4. Clique no nome do host para abrir a página de dados recentes.',
    	'Add IP range' => 'Adicionar faixa de IP',
    	'Edit IP range' => 'Editar faixa de IP',
    	'All IP ranges' => 'Todas as faixas de IP',
    	'IP' => 'IP',
    	'IP range' => 'Faixa de IP',
    	'IP range name' => 'Nome da faixa de IP',
    	'No.' => 'Nº',
    	'Task ID' => 'ID da tarefa',
    	'IP status' => 'Status do IP',
    	'Status last updated' => 'Última atualização do status',
    	'Name' => 'Nome',
    	'Example: Office network' => 'Exemplo: Rede do escritório',
    	'Search' => 'Pesquisar',
    	'Search name or CIDR' => 'Pesquisar nome ou CIDR',
    	'Search task ID or range name' => 'Pesquisar ID da tarefa ou nome da faixa',
    	'Search IP, range, or host name' => 'Pesquisar IP, faixa ou nome do host',
    	'Status' => 'Status',
    	'Enabled status' => 'Status de ativação',
    	'Scan status' => 'Status da varredura',
    	'Last scan time' => 'Última varredura',
    	'Filter' => 'Filtro',
    	'Reset' => 'Redefinir',
    	'All statuses' => 'Todos os status',
    	'All enabled statuses' => 'Todos os status de ativação',
    	'All scan statuses' => 'Todos os status de varredura',
    	'All IP statuses' => 'Todos os status de IP',
    	'Enabled' => 'Ativado',
    	'Disabled' => 'Desativado',
    	'Pending' => 'Pendente',
    	'Running' => 'Em execução',
    	'Completed' => 'Concluída',
    	'Failed' => 'Falhou',
    	'Stopped' => 'Parada',
    	'Never' => 'Nunca',
    	'Alive' => 'Ativo',
    	'Unreachable' => 'Inacessível',
    	'Not scanned' => 'Não varrido',
    	'Alive IPs / Total' => 'IPs ativos / Total',
    	'Host association' => 'Associação de host',
    	'Associated host' => 'Host associado',
    	'All associations' => 'Todas as associações',
    	'Associated' => 'Associado',
    	'Unassociated' => 'Não associado',
    	'No associated host' => 'Nenhum host associado',
    	'Actions' => 'Ações',
    	'Start scan' => 'Iniciar varredura',
    	'View task' => 'Ver tarefa',
    	'Edit' => 'Editar',
    	'Delete' => 'Excluir',
    	'No IP ranges found.' => 'Nenhuma faixa de IP encontrada.',
    	'No IP addresses found.' => 'Nenhum endereço IP encontrado.',
    	'Scan schedule' => 'Agendamento de varredura',
    	'The scan interval is controlled by crontab. Each enabled IP range is scanned whenever the cron job runs.' => 'O intervalo de varredura é controlado pelo crontab. Cada faixa de IP ativa é varrida sempre que o job do cron é executado.',
    	'Enable scheduled scans' => 'Ativar varreduras agendadas',
    	'When enabled, this range is scanned each time the IPAM cron job runs.' => 'Quando ativado, esta faixa é varrida a cada execução do job de cron do IPAM.',
    	'Cancel' => 'Cancelar',
    	'Save' => 'Salvar',
    	'Close' => 'Fechar',
    	'IP availability matrix' => 'Matriz de disponibilidade de IPs',
    	'Unreachable / not scanned' => 'Inacessível / não varrido',
    	'Loading...' => 'Carregando...',
    	'Request failed.' => 'Falha na requisição.',
    	'Delete this IP range?' => 'Excluir esta faixa de IP?',
    	'Tasks' => 'Tarefas',
    	'Task / IP range' => 'Tarefa / faixa de IP',
    	'Progress' => 'Progresso',
    	'Shards' => 'Fragmentos',
    	'Created' => 'Criado',
    	'Stop' => 'Parar',
    	'View range' => 'Ver faixa',
    	'No scan tasks found.' => 'Nenhuma tarefa de varredura encontrada.',
    	'Previous' => 'Anterior',
    	'Next' => 'Próxima',
    	'Items per page' => 'Itens por página',
    	'Page %1$d of %2$d' => 'Página %1$d de %2$d',
    	'%1$d items in total' => '%1$d itens no total',
    	'%1$d IPs in total' => '%1$d IPs no total',
    	'Range saved.' => 'Faixa salva.',
    	'Range deleted.' => 'Faixa excluída.',
    	'Task started.' => 'Tarefa iniciada.',
    	'Task stopped.' => 'Tarefa parada.',
    	'Task not found.' => 'Tarefa não encontrada.',
    	'Range not found.' => 'Faixa não encontrada.',
    	'Failed to start task.' => 'Falha ao iniciar a tarefa.',
    	'Stop active tasks before deleting this range.' => 'Pare as tarefas ativas antes de excluir esta faixa.',
    	'Data directory is not writable.' => 'O diretório de dados não tem permissão de escrita.',
    ];

    public static function detectLanguage(): string {
        if (self::$language !== null) {
            return self::$language;
        }

        $language = $GLOBALS['USER_DETAILS']['lang']
            ?? $GLOBALS['ZBX_LOCALES']['selected']
            ?? $_SESSION['zbx_lang']
            ?? $_SESSION['lang']
            ?? 'en_US';

        try {
            if (class_exists('CWebUser') && method_exists('CWebUser', 'getLang')) {
                $language = \CWebUser::getLang() ?: $language;
            }
            elseif (class_exists('CWebUser') && method_exists('CWebUser', 'get')) {
                $language = \CWebUser::get('lang') ?: $language;
            }
            elseif (isset(\CWebUser::$data['lang'])) {
                $language = \CWebUser::$data['lang'] ?: $language;
            }
        }
        catch (\Throwable $exception) {
        }

        if ($language === 'default') {
            try {
                if (class_exists('CSettingsHelper')) {
                    $default = method_exists('CSettingsHelper', 'getPublic')
                        ? \CSettingsHelper::getPublic('default_lang')
                        : \CSettingsHelper::get('default_lang');
                    $language = $default ?: $language;
                }
            }
            catch (\Throwable $exception) {
            }
            if ($language === 'default') {
                $language = $GLOBALS['ZBX_LOCALES']['selected'] ?? $language;
            }
        }

        // Supported languages: Brazilian Portuguese (pt*) and English (everything else).
        if (preg_match('/^pt(?:[-_]|$)/i', trim((string) $language))) {
            return self::$language = 'pt_BR';
        }

        return self::$language = 'en_US';
    }

    public static function t(string $text): string {
        $language = self::detectLanguage();
        if ($language === 'pt_BR') {
            return self::PORTUGUESE[$text] ?? $text;
        }

        return $text;
    }
}
