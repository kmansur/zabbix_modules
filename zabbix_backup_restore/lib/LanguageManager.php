<?php

namespace Modules\ZabbixBackupRestore\Lib;

final class LanguageManager {

	private const PORTUGUESE = [
		'Backup and restore' => 'Backup e restauração',
		'Export backup' => 'Exportar backup',
		'Restore backup' => 'Restaurar backup',
		'Configuration to export' => 'Configuração a exportar',
		'Select all' => 'Selecionar tudo',
		'Download YAML' => 'Baixar YAML',
		'Preparing backup…' => 'Preparando backup…',
		'PHP ran out of memory while processing the backup.' => 'O PHP ficou sem memória ao processar o backup.',
		'Monitoring' => 'Monitoramento',
		'Access and notifications' => 'Acesso e notificações',
		'Services and shared configuration' => 'Serviços e configuração compartilhada',
		'Hosts' => 'Hosts',
		'Templates' => 'Templates',
		'Triggers' => 'Triggers',
		'Graphs' => 'Gráficos',
		'Discovery checks' => 'Verificações de descoberta',
		'Frontend modules' => 'Módulos do frontend',
		'MFA methods' => 'Métodos de MFA',
		'User directories' => 'Diretórios de usuários',
		'Host groups' => 'Grupos de hosts',
		'Template groups' => 'Grupos de templates',
		'Network discovery rules' => 'Regras de descoberta de rede',
		'Maintenance periods' => 'Períodos de manutenção',
		'Proxies' => 'Proxies',
		'Proxy groups' => 'Grupos de proxies',
		'Maps' => 'Mapas',
		'Images' => 'Imagens',
		'Dashboards' => 'Dashboards',
		'Users' => 'Usuários',
		'User groups' => 'Grupos de usuários',
		'User roles' => 'Perfis de usuário',
		'Media types' => 'Tipos de mídia',
		'Actions' => 'Ações',
		'Scripts' => 'Scripts',
		'Services and dependencies' => 'Serviços e dependências',
		'Service-level agreements' => 'Acordos de nível de serviço',
		'Global macros' => 'Macros globais',
		'Global regular expressions' => 'Expressões regulares globais',
		'Event correlations' => 'Correlações de eventos',
		'Includes items, triggers, low-level discovery and user macros.' => 'Inclui itens, triggers, descoberta de baixo nível e macros de usuário.',
		'Include related configuration or make sure it already exists on the destination.' => 'Inclua a configuração relacionada ou garanta que ela já exista no destino.',
		'Backup files may contain credentials and commands. Store them securely and restore only trusted files.' => 'Arquivos de backup podem conter credenciais e comandos. Armazene-os com segurança e restaure apenas arquivos confiáveis.',
		'Choose a YAML file' => 'Escolha um arquivo YAML',
		'or drag it here' => 'ou arraste-o para cá',
		'Choose a .yaml or .yml backup file (up to %s MiB).' => 'Escolha um arquivo de backup .yaml ou .yml (até %s MiB).',
		'Update existing configuration' => 'Atualizar a configuração existente',
		'Preview restore' => 'Pré-visualizar restauração',
		'Restore configuration' => 'Restaurar configuração',
		'Preview ready' => 'Pré-visualização pronta',
		'Source version' => 'Versão de origem',
		'Destination version' => 'Versão de destino',
		'Configuration' => 'Configuração',
		'Records in file' => 'Registros no arquivo',
		'API validation runs during restore. A failed operation rolls back the entire batch.' => 'A validação pela API é feita durante a restauração. Uma operação com falha reverte o lote inteiro.',
		'Restoring configuration…' => 'Restaurando configuração…',
		'Checking backup…' => 'Verificando backup…',
		'Restore complete.' => 'Restauração concluída.',
		'The request failed. Check the file size and sign-in status, then try again.' => 'A requisição falhou. Verifique o tamanho do arquivo e o status do login e tente novamente.',
		'The server could not complete the request (HTTP %s).' => 'O servidor não conseguiu concluir a requisição (HTTP %s).',
		'Choose at least one configuration type.' => 'Escolha pelo menos um tipo de configuração.',
		'The selected configuration is invalid.' => 'A configuração selecionada é inválida.',
		'The selection contains no exportable records.' => 'A seleção não contém registros exportáveis.',
		'Upload a valid YAML backup file.' => 'Envie um arquivo de backup YAML válido.',
		'The backup exceeds the upload size limit.' => 'O backup excede o limite de tamanho de upload.',
		'The YAML backup exceeds the 500 MiB file limit.' => 'O backup YAML excede o limite de 500 MiB por arquivo.',
		'Could not open a temporary backup file.' => 'Não foi possível abrir um arquivo temporário de backup.',
		'Could not write the temporary backup file.' => 'Não foi possível gravar o arquivo temporário de backup.',
		'The YAML backup is malformed or uses unsupported syntax.' => 'O backup YAML está malformado ou usa sintaxe não suportada.',
		'This file is not a supported module backup.' => 'Este arquivo não é um backup do módulo suportado.',
		'This module supports Zabbix 6.0, 7.0 and 8.0.' => 'Este módulo suporta o Zabbix 6.0, 7.0 e 8.0.',
		'This backup requires a newer Zabbix version.' => 'Este backup requer uma versão mais recente do Zabbix.',
		'Preview this file and restore option again before restoring.' => 'Pré-visualize novamente este arquivo e a opção de restauração antes de restaurar.',
		'Missing or ambiguous reference to %s (%s).' => 'Referência ausente ou ambígua a %s (%s).',
		'A required reference in %s is invalid.' => 'Uma referência obrigatória em %s é inválida.',
		'Duplicate record in %s (%s).' => 'Registro duplicado em %s (%s).',
		'Invalid record in %s.' => 'Registro inválido em %s.',
		'Could not start the restore transaction.' => 'Não foi possível iniciar a transação de restauração.',
		'Could not commit the restore transaction.' => 'Não foi possível confirmar a transação de restauração.',
		'Zabbix API call %s failed.' => 'A chamada à API do Zabbix %s falhou.',
		'An unexpected error occurred.' => 'Ocorreu um erro inesperado.',
		'%d secret macro values must be configured on the destination.' => '%d valores de macros secretas precisam ser configurados no destino.',
		'%d externally provisioned users must be provisioned on the destination.' => '%d usuários provisionados externamente precisam ser provisionados no destino.',
		'New local accounts require a password reset on the destination.' => 'Novas contas locais exigem redefinição de senha no destino.',
	];

	public static function t(string $message): string {
		$lang = \CWebUser::$data['lang'] ?? 'default';
		if ($lang === 'default') {
			$lang = \CSettingsHelper::getPublic(\CSettingsHelper::DEFAULT_LANG);
		}

		// Supported languages: Brazilian Portuguese (pt*) and English (everything else).
		if (preg_match('/^pt(?:[-_]|$)/i', trim((string) $lang))) {
			return self::PORTUGUESE[$message] ?? $message;
		}

		return $message;
	}

	public static function format(string $message, ...$arguments): string {
		return sprintf(self::t($message), ...$arguments);
	}
}
