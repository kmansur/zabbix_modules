<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** Language helper for Zabbix Clonehosts module.
** Auto-detects the Zabbix user/system language and provides English and Brazilian Portuguese translations.
** No gettext dependency — pure PHP array-based translation.
*/

namespace Modules\ZabbixClonehosts;

require_once __DIR__.'/lib/ModuleNotice.php';

class LangHelper {

	/** @var string|null Cached current language */
	private static $lang = null;

	/** @var array Translation table: key => [locale => translation] */
	private static $translations = [
		// ===== Menu =====
		'menu.clonehosts' => [
			'en_GB' => 'Host Batch Import',
			'pt_BR' => 'Importação de hosts em lote',
		],
		'page.clonehosts' => [
			'en_GB' => 'Clone hosts',
			'pt_BR' => 'Clonar hosts',
		],

		// ===== Step 1: Source Host Selection =====
		'step1.title' => [
			'en_GB' => 'Step 1: Select Source Host',
			'pt_BR' => 'Passo 1: Selecione o host de origem',
		],
		'step1.description' => [
			'en_GB' => 'Select an existing monitored host. Its configuration (groups, templates, tags, macros, interfaces, TLS settings, etc.) will serve as the baseline template for all cloned hosts. Fields not specified in the import data will inherit values from this host.',
			'pt_BR' => 'Selecione um host monitorado existente. A configuração dele (grupos, templates, tags, macros, interfaces, configurações de TLS etc.) servirá de base para todos os hosts clonados. Os campos não informados nos dados de importação herdarão os valores deste host.',
		],
		'step1.select_placeholder' => [
			'en_GB' => '— Select Source Host —',
			'pt_BR' => '— Selecione o host de origem —',
		],
		'step1.load_btn' => [
			'en_GB' => 'Load Source Host Info',
			'pt_BR' => 'Carregar informações do host de origem',
		],
		'step1.loading' => [
			'en_GB' => 'Loading...',
			'pt_BR' => 'Carregando...',
		],
		'step1.load_failed' => [
			'en_GB' => 'Failed to load source host info',
			'pt_BR' => 'Falha ao carregar as informações do host de origem',
		],
		'step1.load_failed_network' => [
			'en_GB' => 'Failed to load source host info, please check network connection.',
			'pt_BR' => 'Falha ao carregar as informações do host de origem; verifique a conexão de rede.',
		],
		'step1.source_host_label' => [
			'en_GB' => 'Source Host: ',
			'pt_BR' => 'Host de origem: ',
		],
		'step1.inherited_note' => [
			'en_GB' => 'Fields not specified in the import data will inherit the above configuration.',
			'pt_BR' => 'Os campos não informados nos dados de importação herdarão a configuração acima.',
		],

		// ===== Host Filter =====
		'filter.host_group' => [
			'en_GB' => 'Host Group',
			'pt_BR' => 'Grupo de hosts',
		],
		'filter.all_groups' => [
			'en_GB' => '— All Groups —',
			'pt_BR' => '— Todos os grupos —',
		],
		'filter.host_name' => [
			'en_GB' => 'Host Name',
			'pt_BR' => 'Nome do host',
		],
		'filter.name_placeholder' => [
			'en_GB' => 'Search by host name...',
			'pt_BR' => 'Pesquisar por nome do host...',
		],
		'filter.count' => [
			'en_GB' => 'Showing {total} of {all} hosts',
			'pt_BR' => 'Exibindo {total} de {all} hosts',
		],
		'filter.no_match' => [
			'en_GB' => 'No matching hosts found',
			'pt_BR' => 'Nenhum host correspondente encontrado',
		],

		// ===== Unified Search Box =====
		'search.placeholder' => [
			'en_GB' => 'Search hosts or host groups...',
			'pt_BR' => 'Pesquisar hosts ou grupos de hosts...',
		],
		'search.hint' => [
			'en_GB' => 'Type to search. Click a host or host group in the dropdown to filter the list below.',
			'pt_BR' => 'Digite para pesquisar. Clique em um host ou grupo de hosts na lista suspensa para filtrar a lista abaixo.',
		],
		'search.category.host' => [
			'en_GB' => 'Hosts',
			'pt_BR' => 'Hosts',
		],
		'search.category.group' => [
			'en_GB' => 'Host Groups',
			'pt_BR' => 'Grupos de hosts',
		],
		'search.no_match' => [
			'en_GB' => 'No matching data',
			'pt_BR' => 'Nenhum dado correspondente',
		],
		'search.remove' => [
			'en_GB' => 'Remove',
			'pt_BR' => 'Remover',
		],

		// ===== Multiselect Control =====
		'multiselect.search_placeholder' => [
			'en_GB' => 'Type to search...',
			'pt_BR' => 'Digite para pesquisar...',
		],
		'multiselect.select_btn' => [
			'en_GB' => 'Select',
			'pt_BR' => 'Selecionar',
		],
		'multiselect.popup_title_group' => [
			'en_GB' => 'Select Host Groups',
			'pt_BR' => 'Selecionar grupos de hosts',
		],
		'multiselect.popup_title_host' => [
			'en_GB' => 'Select Hosts',
			'pt_BR' => 'Selecionar hosts',
		],
		'multiselect.popup_search_placeholder' => [
			'en_GB' => 'Search...',
			'pt_BR' => 'Pesquisar...',
		],
		'multiselect.popup_name_header' => [
			'en_GB' => 'Name',
			'pt_BR' => 'Nome',
		],
		'multiselect.popup_count' => [
			'en_GB' => '{count} selected',
			'pt_BR' => '{count} selecionado(s)',
		],
		'multiselect.popup_cancel' => [
			'en_GB' => 'Cancel',
			'pt_BR' => 'Cancelar',
		],
		'multiselect.popup_apply' => [
			'en_GB' => 'Apply',
			'pt_BR' => 'Aplicar',
		],

		// ===== Step 2: Data Input =====
		'step2.title' => [
			'en_GB' => 'Step 2: Enter Host Data',
			'pt_BR' => 'Passo 2: Informe os dados dos hosts',
		],
		'step2.description' => [
			'en_GB' => 'Upload a CSV file or enter host data directly in the table below. Host name and interface IP are required; other fields are optional and will inherit from the source host when left blank.',
			'pt_BR' => 'Envie um arquivo CSV ou informe os dados dos hosts diretamente na tabela abaixo. O nome do host e o IP da interface são obrigatórios; os demais campos são opcionais e, se ficarem em branco, serão herdados do host de origem.',
		],
		'step2.tab_file' => [
			'en_GB' => 'File Upload (CSV)',
			'pt_BR' => 'Upload de arquivo (CSV)',
		],
		'step2.tab_table' => [
			'en_GB' => 'Online Table Entry',
			'pt_BR' => 'Preenchimento na tabela',
		],

		// ===== CSV Upload =====
		'csv.parse_btn' => [
			'en_GB' => 'Parse CSV File',
			'pt_BR' => 'Interpretar arquivo CSV',
		],
		'csv.download_template' => [
			'en_GB' => 'Download CSV Template',
			'pt_BR' => 'Baixar modelo CSV',
		],
		'csv.encoding_hint' => [
			'en_GB' => 'Tip: If Chinese characters in the CSV appear garbled, try switching encoding to GBK and re-parse. The template file uses UTF-8 encoding and can be opened directly in Excel.',
			'pt_BR' => 'Dica: se caracteres especiais no CSV aparecerem corrompidos, tente mudar a codificação para GBK e interpretar novamente. O arquivo de modelo usa codificação UTF-8 e pode ser aberto diretamente no Excel.',
		],
		'csv.no_data' => [
			'en_GB' => 'No valid data rows found in CSV file.',
			'pt_BR' => 'Nenhuma linha de dados válida encontrada no arquivo CSV.',
		],
		'csv.parsed_rows' => [
			'en_GB' => 'Parsed: ',
			'pt_BR' => 'Interpretadas: ',
		],
		'csv.valid_rows' => [
			'en_GB' => 'Valid: ',
			'pt_BR' => 'Válidas: ',
		],
		'csv.invalid_rows' => [
			'en_GB' => 'Invalid (missing required fields): ',
			'pt_BR' => 'Inválidas (campos obrigatórios ausentes): ',
		],
		'csv.more_rows' => [
			'en_GB' => '... ',
			'pt_BR' => '... ',
		],
		'csv.more_rows_suffix' => [
			'en_GB' => ' more rows',
			'pt_BR' => ' linhas a mais',
		],

		// ===== CSV Headers =====
		'csv.header.host' => [
			'en_GB' => 'Host Name(*)',
			'pt_BR' => 'Nome do host(*)',
		],
		'csv.header.visible_name' => [
			'en_GB' => 'Visible Name',
			'pt_BR' => 'Nome visível',
		],
		'csv.header.ip' => [
			'en_GB' => 'Interface IP(*)',
			'pt_BR' => 'IP da interface(*)',
		],
		'csv.header.port' => [
			'en_GB' => 'Port',
			'pt_BR' => 'Porta',
		],
		'csv.header.groups' => [
			'en_GB' => 'Host Groups',
			'pt_BR' => 'Grupos de hosts',
		],
		'csv.header.templates' => [
			'en_GB' => 'Templates',
			'pt_BR' => 'Templates',
		],
		'csv.header.tags' => [
			'en_GB' => 'Tags',
			'pt_BR' => 'Tags',
		],
		'csv.header.macros' => [
			'en_GB' => 'Macros',
			'pt_BR' => 'Macros',
		],
		'csv.header.description' => [
			'en_GB' => 'Description',
			'pt_BR' => 'Descrição',
		],

		// ===== Table Toolbar =====
		'table.add_row' => [
			'en_GB' => 'Add Row',
			'pt_BR' => 'Adicionar linha',
		],
		'table.remove_row' => [
			'en_GB' => 'Remove Selected Rows',
			'pt_BR' => 'Remover linhas selecionadas',
		],
		'table.clear_all' => [
			'en_GB' => 'Clear All',
			'pt_BR' => 'Limpar tudo',
		],
		'table.row_count' => [
			'en_GB' => 'Total rows: ',
			'pt_BR' => 'Total de linhas: ',
		],
		'table.clear_confirm' => [
			'en_GB' => 'Are you sure you want to clear all rows?',
			'pt_BR' => 'Tem certeza de que deseja limpar todas as linhas?',
		],
		'table.placeholder.required' => [
			'en_GB' => 'Required',
			'pt_BR' => 'Obrigatório',
		],
		'table.placeholder.inherited' => [
			'en_GB' => '(inherited)',
			'pt_BR' => '(herdado)',
		],

		// ===== Column Headers =====
		'col.host_name' => [
			'en_GB' => 'Host Name',
			'pt_BR' => 'Nome do host',
		],
		'col.visible_name' => [
			'en_GB' => 'Visible Name',
			'pt_BR' => 'Nome visível',
		],
		'col.interface_ip' => [
			'en_GB' => 'Interface IP',
			'pt_BR' => 'IP da interface',
		],
		'col.port' => [
			'en_GB' => 'Port',
			'pt_BR' => 'Porta',
		],
		'col.host_groups' => [
			'en_GB' => 'Host Groups',
			'pt_BR' => 'Grupos de hosts',
		],
		'col.templates' => [
			'en_GB' => 'Templates',
			'pt_BR' => 'Templates',
		],
		'col.tags' => [
			'en_GB' => 'Tags',
			'pt_BR' => 'Tags',
		],
		'col.macros' => [
			'en_GB' => 'Macros',
			'pt_BR' => 'Macros',
		],
		'col.description' => [
			'en_GB' => 'Description',
			'pt_BR' => 'Descrição',
		],
		'col.status' => [
			'en_GB' => 'Status',
			'pt_BR' => 'Status',
		],
		'col.result' => [
			'en_GB' => 'Result',
			'pt_BR' => 'Resultado',
		],
		'col.host_id' => [
			'en_GB' => 'Host ID',
			'pt_BR' => 'ID do host',
		],
		'col.error' => [
			'en_GB' => 'Error',
			'pt_BR' => 'Erro',
		],

		// ===== Preview =====
		'preview.btn' => [
			'en_GB' => 'Preview Import',
			'pt_BR' => 'Pré-visualizar importação',
		],
		'preview.hint_no_data' => [
			'en_GB' => 'Please fill in at least one valid row of host data to continue.',
			'pt_BR' => 'Preencha pelo menos uma linha válida de dados de host para continuar.',
		],
		'preview.hint_no_source' => [
			'en_GB' => 'Please select a source host first.',
			'pt_BR' => 'Selecione um host de origem primeiro.',
		],
		'preview.hint_ready' => [
			'en_GB' => 'Ready to preview import.',
			'pt_BR' => 'Pronto para pré-visualizar a importação.',
		],
		'preview.alert_no_data' => [
			'en_GB' => 'Please enter at least one valid host (host name and IP are required).',
			'pt_BR' => 'Informe pelo menos um host válido (nome do host e IP são obrigatórios).',
		],

		// ===== Preview Page =====
		'preview.source_config' => [
			'en_GB' => 'Source Host Configuration (will be inherited)',
			'pt_BR' => 'Configuração do host de origem (será herdada)',
		],
		'preview.interfaces' => [
			'en_GB' => 'Interfaces',
			'pt_BR' => 'Interfaces',
		],
		'preview.none' => [
			'en_GB' => 'None',
			'pt_BR' => 'Nenhum',
		],
		'preview.total' => [
			'en_GB' => 'Total: ',
			'pt_BR' => 'Total: ',
		],
		'preview.conflicts' => [
			'en_GB' => 'Conflicts: ',
			'pt_BR' => 'Conflitos: ',
		],
		'preview.importable' => [
			'en_GB' => 'Importable: ',
			'pt_BR' => 'Importáveis: ',
		],
		'preview.pending_count' => [
			'en_GB' => 'Hosts to import: ',
			'pt_BR' => 'Hosts a importar: ',
		],
		'preview.no_conflicts' => [
			'en_GB' => 'No conflicts detected',
			'pt_BR' => 'Nenhum conflito detectado',
		],
		'preview.title' => [
			'en_GB' => 'Host Import Preview',
			'pt_BR' => 'Pré-visualização da importação de hosts',
		],
		'preview.inherited' => [
			'en_GB' => '(inherited)',
			'pt_BR' => '(herdado)',
		],
		'preview.ready' => [
			'en_GB' => 'Ready',
			'pt_BR' => 'Pronto',
		],
		'preview.back' => [
			'en_GB' => 'Back',
			'pt_BR' => 'Voltar',
		],
		'preview.start_import' => [
			'en_GB' => 'Start Import',
			'pt_BR' => 'Iniciar importação',
		],
		'preview.resolve_conflicts' => [
			'en_GB' => 'Conflicting hosts will be skipped. You can uncheck ready hosts to exclude them too.',
			'pt_BR' => 'Hosts em conflito serão ignorados. Você também pode desmarcar hosts prontos para excluí-los.',
		],
		'preview.back_edit' => [
			'en_GB' => 'Back to Edit',
			'pt_BR' => 'Voltar para edição',
		],
		'preview.import_selected' => [
			'en_GB' => 'Import Selected ({count})',
			'pt_BR' => 'Importar selecionados ({count})',
		],
		'preview.no_selected' => [
			'en_GB' => 'Please select at least one ready host to import.',
			'pt_BR' => 'Selecione pelo menos um host pronto para importar.',
		],
		'preview.skipped' => [
			'en_GB' => 'Skipped',
			'pt_BR' => 'Ignorado',
		],
		'preview.col_select' => [
			'en_GB' => 'Import',
			'pt_BR' => 'Importar',
		],

		// ===== Field Status Legend =====
		'legend.exists' => [
			'en_GB' => 'Exists → Link directly',
			'pt_BR' => 'Existe → Vincular diretamente',
		],
		'legend.new' => [
			'en_GB' => 'Will be created',
			'pt_BR' => 'Será criado',
		],
		'legend.not_found' => [
			'en_GB' => 'Not found',
			'pt_BR' => 'Não encontrado',
		],
		'legend.inherited' => [
			'en_GB' => 'Inherited from source host',
			'pt_BR' => 'Herdado do host de origem',
		],
		'legend.exists_tooltip' => [
			'en_GB' => 'Already exists, will be linked directly',
			'pt_BR' => 'Já existe; será vinculado diretamente',
		],
		'legend.new_tooltip_group' => [
			'en_GB' => 'This host group will be created',
			'pt_BR' => 'Este grupo de hosts será criado',
		],
		'legend.not_found_tooltip_template' => [
			'en_GB' => 'Template not found, please verify the template name',
			'pt_BR' => 'Template não encontrado; verifique o nome do template',
		],

		// ===== Import Progress =====
		'import.progress_title' => [
			'en_GB' => 'Import Progress',
			'pt_BR' => 'Progresso da importação',
		],
		'import.status_success' => [
			'en_GB' => 'Success:',
			'pt_BR' => 'Sucesso:',
		],
		'import.status_failed' => [
			'en_GB' => 'Failed:',
			'pt_BR' => 'Falha:',
		],
		'import.status_pending' => [
			'en_GB' => 'Pending:',
			'pt_BR' => 'Pendente:',
		],
		'import.importing' => [
			'en_GB' => 'Importing...',
			'pt_BR' => 'Importando...',
		],
		'import.complete' => [
			'en_GB' => 'Import Complete',
			'pt_BR' => 'Importação concluída',
		],
		'import.results_title' => [
			'en_GB' => 'Import Results',
			'pt_BR' => 'Resultados da importação',
		],
		'import.success' => [
			'en_GB' => 'Success: ',
			'pt_BR' => 'Sucesso: ',
		],
		'import.failed' => [
			'en_GB' => 'Failed: ',
			'pt_BR' => 'Falha: ',
		],
		'import.download_report' => [
			'en_GB' => 'Download Report',
			'pt_BR' => 'Baixar relatório',
		],
		'import.result_success' => [
			'en_GB' => 'Success',
			'pt_BR' => 'Sucesso',
		],
		'import.result_failed' => [
			'en_GB' => 'Failed',
			'pt_BR' => 'Falhou',
		],
		'import.ajax_error' => [
			'en_GB' => 'AJAX error: ',
			'pt_BR' => 'Erro AJAX: ',
		],
		'import.unknown_error' => [
			'en_GB' => 'Unknown',
			'pt_BR' => 'Desconhecido',
		],

		// ===== Report CSV Headers =====
		'report.col_row' => [
			'en_GB' => '#',
			'pt_BR' => '#',
		],
		'report.col_result' => [
			'en_GB' => 'Result',
			'pt_BR' => 'Resultado',
		],
		'report.col_host' => [
			'en_GB' => 'Host Name',
			'pt_BR' => 'Nome do host',
		],
		'report.col_ip' => [
			'en_GB' => 'Interface IP',
			'pt_BR' => 'IP da interface',
		],
		'report.col_hostid' => [
			'en_GB' => 'Host ID',
			'pt_BR' => 'ID do host',
		],
		'report.col_error' => [
			'en_GB' => 'Error',
			'pt_BR' => 'Erro',
		],

		// ===== Error Messages (from controllers) =====
		'err.host_required' => [
			'en_GB' => 'Host name is required',
			'pt_BR' => 'O nome do host é obrigatório',
		],
		'err.ip_required' => [
			'en_GB' => 'Interface IP is required',
			'pt_BR' => 'O IP da interface é obrigatório',
		],
		'err.host_exists' => [
			'en_GB' => 'This host already exists',
			'pt_BR' => 'Este host já existe',
		],
		'err.template_exists' => [
			'en_GB' => 'A template with the same name already exists',
			'pt_BR' => 'Já existe um template com o mesmo nome',
		],
		'err.source_not_found' => [
			'en_GB' => 'Source host not found',
			'pt_BR' => 'Host de origem não encontrado',
		],
		'err.no_groups' => [
			'en_GB' => 'No host groups available (not specified and cannot inherit)',
			'pt_BR' => 'Nenhum grupo de hosts disponível (não informado e não é possível herdar)',
		],
		'err.unknown_api_response' => [
			'en_GB' => 'Unknown API response',
			'pt_BR' => 'Resposta desconhecida da API',
		],
		'err.batch_duplicate' => [
			'en_GB' => 'Duplicate host name within batch',
			'pt_BR' => 'Nome de host duplicado no lote',
		],
		'err.host_exists_in_zabbix' => [
			'en_GB' => 'This host name already exists in Zabbix',
			'pt_BR' => 'Este nome de host já existe no Zabbix',
		],

		// ===== Interface Type Names =====
		'iface.Agent' => [
			'en_GB' => 'Agent',
			'pt_BR' => 'Agente',
		],
		'iface.SNMP' => [
			'en_GB' => 'SNMP',
			'pt_BR' => 'SNMP',
		],
		'iface.IPMI' => [
			'en_GB' => 'IPMI',
			'pt_BR' => 'IPMI',
		],
		'iface.JMX' => [
			'en_GB' => 'JMX',
			'pt_BR' => 'JMX',
		],
		'iface.Type' => [
			'en_GB' => 'Type ',
			'pt_BR' => 'Tipo ',
		],
	];

	/**
	 * Detect the current user's language from Zabbix.
	 * Priority: CWebUser::$data['lang'] → API user.get → system default
	 *
	 * @return string Language code (e.g., 'pt_BR', 'en_GB')
	 */
	public static function getLang(): string {
		if (self::$lang !== null) {
			return self::$lang;
		}

		// Method 1: Try CWebUser (available in Zabbix frontend context)
		if (class_exists('CWebUser', false) && isset(\CWebUser::$data['lang'])) {
			self::$lang = self::normalizeLanguage(\CWebUser::$data['lang']);
			return self::$lang;
		}

		// Method 2: Try to get from the session/API
		try {
			$user = \API::User()->get([
				'output' => ['lang'],
				'userids' => [\CWebUser::$data['userid'] ?? 0]
			]);
			if (!empty($user) && !empty($user[0]['lang'])) {
				self::$lang = self::normalizeLanguage($user[0]['lang']);
				return self::$lang;
			}
		} catch (\Throwable $e) {
			// Fall through to default
		}

		self::$lang = self::normalizeLanguage('default');
		return self::$lang;
	}

	private static function normalizeLanguage(string $locale): string {
		if (trim($locale) === '' || strcasecmp(trim($locale), 'default') === 0) {
			return \ZabbixModuleNotice::language();
		}

		if (preg_match('/^pt(?:[-_]|$)/i', trim($locale))) {
			return 'pt_BR';
		}

		return 'en_GB';
	}

	/**
	 * Chinese is no longer supported; kept for backward compatibility.
	 *
	 * @return bool
	 */
	public static function isChinese(): bool {
		return false;
	}

	public static function getCsvHostHeaders(): array {
		return array_values(self::$translations['csv.header.host']);
	}

	/**
	 * Translate a key to the current language, with optional parameter substitution.
	 * Supports {placeholder} style parameters in the translation string.
	 *
	 * @param string $key Translation key
	 * @param array $params Optional ['{placeholder}' => 'value'] pairs for substitution
	 * @return string Translated string (falls back to English, then to the key itself)
	 */
	public static function t(string $key, array $params = []): string {
		$lang = self::getLang();

		if (isset(self::$translations[$key][$lang])) {
			$str = self::$translations[$key][$lang];
		} elseif (isset(self::$translations[$key]['en_GB'])) {
			$str = self::$translations[$key]['en_GB'];
		} else {
			$str = $key;
		}

		if (!empty($params)) {
			$str = strtr($str, $params);
		}

		return $str;
	}

	/**
	 * Get all translations for the current language as a JSON-serializable array.
	 * Used to pass translations to JavaScript.
	 *
	 * @return array Key => translated string
	 */
	public static function getAllForJs(): array {
		$lang = self::getLang();
		$result = [];

		foreach (self::$translations as $key => $langs) {
			if (isset($langs[$lang])) {
				$result[$key] = $langs[$lang];
			} elseif (isset($langs['en_GB'])) {
				$result[$key] = $langs['en_GB'];
			}
		}

		return $result;
	}
}
