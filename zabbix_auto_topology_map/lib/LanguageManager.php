<?php declare(strict_types = 1);

namespace Modules\Topomap\Lib;

/**
 * Translations for the module's own strings.
 *
 * Keys are the English source texts; in Brazilian Portuguese the PT dictionary
 * is used, and any missing entry falls back to the English source.
 *
 * Language resolution follows the Zabbix frontend:
 *   1) the current user's language (CWebUser::get('lang'));
 *   2) the system default language (settings.default_lang) when the user uses "default";
 *   3) en_US when neither is available.
 * Supported languages: en_US and pt_BR; other languages fall back to en_US.
 */
class LanguageManager {

	private const ZBX_DEFAULT_LANG = 'en_US';

	/** Language cached for the current request; null means not determined yet. */
	/** @var ?string */ private static $currentLanguage = null;

	/** Brazilian Portuguese dictionary: key = English source text. */
	private const PT = [
		'Default' => 'Padrão',
		'Bottom' => 'Embaixo',
		'Left' => 'Esquerda',
		'Right' => 'Direita',
		'Top' => 'Em cima',
		'Contains' => 'Contém',
		'Equals' => 'Igual a',
		'Does not contain' => 'Não contém',
		'Does not equal' => 'Diferente de',
		'Exists' => 'Existe',
		'Does not exist' => 'Não existe',
		'Solid line' => 'Linha contínua',
		'Thick line' => 'Linha grossa',
		'Dotted line' => 'Linha pontilhada',
		'Dashed line' => 'Linha tracejada',
		'Auto curve' => 'Curva automática',
		'Auto orthogonal' => 'Ortogonal automática',
		'Manual waypoints' => 'Pontos de inflexão manuais',
		'Auto (source first)' => 'Automático (origem primeiro)',
		'Source only' => 'Somente origem',
		'Target only' => 'Somente destino',
		'Both ends' => 'Ambas as pontas',
		'None' => 'Nenhum',
		'Rectangle' => 'Retângulo',
		'Ellipse' => 'Elipse',
		'Line' => 'Linha',
		'Scan timed out (' => 'Tempo de varredura esgotado (',
		's without response); skipped this batch' => 's sem resposta); este lote foi ignorado',
		'API returned HTTP ' => 'A API retornou HTTP ',
		': ' => ': ',
		'API returned an empty response (HTTP ' => 'A API retornou uma resposta vazia (HTTP ',
		')' => ')',
		'API returned non-JSON data: ' => 'A API retornou dados que não são JSON: ',
		'API request failed' => 'Falha na requisição à API',
		'The menu API returned non-JSON data' => 'A API de menu retornou dados que não são JSON',
		'Menu data is empty' => 'Os dados do menu estão vazios',
		'Cannot open the host menu' => 'Não foi possível abrir o menu do host',
		'Request failed' => 'Falha na requisição',
		'Host' => 'Host',
		'Loading…' => 'Carregando…',
		'Total ' => 'Total de ',
		' maps' => ' mapas',
		'Your role cannot create or edit maps; read-only view' => 'Seu perfil não pode criar nem editar mapas; somente visualização',
		'Loading failed: ' => 'Falha ao carregar: ',
		' selected' => ' selecionado(s)',
		'Showing ' => 'Exibindo ',
		', found ' => ', encontrados ',
		'No matching maps.' => 'Nenhum mapa correspondente.',
		'No maps yet. Click "Create map" in the upper right corner to add one.' => 'Ainda não há mapas. Clique em "Criar mapa" no canto superior direito para adicionar um.',
		'Elements ' => 'Elementos ',
		'' => '',
		'Properties' => 'Propriedades',
		'Edit' => 'Editar',
		'Delete ' => 'Excluir ',
		' maps?' => ' mapas?',
		'This cannot be undone. These maps share data with Monitoring → Maps and will also disappear from the native list.' => 'Esta ação não pode ser desfeita. Estes mapas compartilham dados com Monitoramento → Mapas e também desaparecerão da lista nativa.',
		'Delete' => 'Excluir',
		'Deleted ' => 'Excluídos ',
		'Deletion failed: ' => 'Falha na exclusão: ',
		'Exported ' => 'Exportados ',
		'Export failed: ' => 'Falha na exportação: ',
		'No permission' => 'Sem permissão',
		'Your role cannot create or edit maps (the role needs "Create and edit maps").' => 'Seu perfil não pode criar nem editar mapas (o perfil precisa de "Criar e editar mapas").',
		'Create map' => 'Criar mapa',
		'Name' => 'Nome',
		'Enter a map name' => 'Digite o nome do mapa',
		'Canvas size' => 'Tamanho da tela',
		'Auto' => 'Automático',
		'The canvas expands with its elements and shrinks to fit the content when saving' => 'A tela se expande com os elementos e encolhe para caber no conteúdo ao salvar',
		'The name cannot be empty' => 'O nome não pode ficar vazio',
		'Creating…' => 'Criando…',
		'Created "' => 'Criado "',
		'"' => '"',
		'Creation failed: ' => 'Falha na criação: ',
		'Apply' => 'Aplicar',
		'Reset' => 'Redefinir',
		'Discover topology' => 'Descobrir topologia',
		'Cancel' => 'Cancelar',
		'Start discovery' => 'Iniciar descoberta',
		'The current map has no host elements; cannot rediscover' => 'O mapa atual não tem elementos de host; não é possível redescobrir',
		'Rediscover and update map' => 'Redescobrir e atualizar o mapa',
		'Using the ' => 'Usando os ',
		' hosts in the current map as seeds; new devices/links are merged in and ' => ' hosts do mapa atual como sementes; novos dispositivos/enlaces são mesclados e ',
		' existing bandwidth/traffic item settings are kept.' => ' configurações existentes de itens de banda/tráfego são mantidas.',
		'Reads the LLDP / CDP neighbor tables from switches and builds the map. Seed devices need an SNMP interface (v2c recommended).' => 'Lê as tabelas de vizinhos LLDP / CDP dos switches e monta o mapa. Os dispositivos-semente precisam de uma interface SNMP (v2c recomendado).',
		'Search host name…' => 'Pesquisar nome do host…',
		'Select all with SNMP' => 'Selecionar todos com SNMP',
		'0 selected' => '0 selecionado(s)',
		'Discovery depth (1-10 levels)' => 'Profundidade da descoberta (1-10 níveis)',
		'Include unmanaged devices' => 'Incluir dispositivos não gerenciados',
		'Layout' => 'Layout',
		'Rebuild as tree' => 'Reconstruir como árvore',
		'Keep current positions' => 'Manter posições atuais',
		'Auto links no longer discovered' => 'Enlaces automáticos não mais descobertos',
		'Keep' => 'Manter',
		'Remove' => 'Remover',
		'Trigger keywords for auto-linking (one per line)' => 'Palavras-chave de triggers para vínculo automático (uma por linha)',
		'link down' => 'link down',
		'One per line, e.g.:
link down' => 'Uma por linha, ex.:
link down',
		'Map name' => 'Nome do mapa',
		'Failed to load hosts: ' => 'Falha ao carregar hosts: ',
		'No matching hosts (try clearing the search)' => 'Nenhum host correspondente (tente limpar a pesquisa)',
		'No SNMP' => 'Sem SNMP',
		'Selected ' => 'Selecionado(s) ',
		'Auto discovery ' => 'Descoberta automática ',
		'Select at least one seed device first.' => 'Selecione pelo menos um dispositivo-semente primeiro.',
		'Discovery started: ' => 'Descoberta iniciada: ',
		' seeds, depth ' => ' sementes, profundidade ',
		', protocol ' => ', protocolo ',
		'Discovering…' => 'Descobrindo…',
		'Scanning ' => 'Varrendo ',
		', ' => ', ',
		' …' => ' …',
		'Scan failed: ' => 'Falha na varredura: ',
		'read failed' => 'falha na leitura',
		': no LLDP / CDP / EDP neighbors (neighbor discovery may be disabled on the device)' => ': nenhum vizinho LLDP / CDP / EDP (a descoberta de vizinhos pode estar desativada no dispositivo)',
		'Unnamed device' => 'Dispositivo sem nome',
		'Discovery complete: ' => 'Descoberta concluída: ',
		' devices, ' => ' dispositivos, ',
		' links' => ' enlaces',
		'Of which ' => 'Destes, ',
		' are in Zabbix and ' => ' estão no Zabbix e ',
		' are unmanaged devices.' => ' são dispositivos não gerenciados.',
		'Update map' => 'Atualizar mapa',
		'Generate map' => 'Gerar mapa',
		'Generating…' => 'Gerando…',
		', linked ' => ', vinculados ',
		' triggers' => ' triggers',
		'Generated "' => 'Gerado "',
		'": ' => '": ',
		'Generation failed: ' => 'Falha na geração: ',
		'Updating…' => 'Atualizando…',
		', removed ' => ', removidos ',
		' old links' => ' enlaces antigos',
		'Rediscovery complete: added ' => 'Redescoberta concluída: adicionados ',
		' links, kept ' => ' enlaces, mantidas ',
		' link settings' => ' configurações de enlaces',
		'Map updated: added ' => 'Mapa atualizado: adicionados ',
		'Update failed: ' => 'Falha na atualização: ',
		'Open failed: ' => 'Falha ao abrir: ',
		'This map has no elements yet' => 'Este mapa ainda não tem elementos',
		'Discard unsaved changes?' => 'Descartar alterações não salvas?',
		'The current map has unsaved changes; returning to the list will discard them.' => 'O mapa atual tem alterações não salvas; voltar para a lista irá descartá-las.',
		'Discard changes' => 'Descartar alterações',
		'Preview mode' => 'Modo de pré-visualização',
		'Unsaved changes' => 'Alterações não salvas',
		'Click "Map elements: Add" to start building the map' => 'Clique em "Elementos do mapa: Adicionar" para começar a montar o mapa',
		'Bandwidth' => 'Largura de banda',
		'Inbound traffic' => 'Tráfego de entrada',
		'Outbound traffic' => 'Tráfego de saída',
		'Waypoint mode: click empty space to add a point, drag dots to adjust, double-click a dot to delete, Esc to finish' => 'Modo de pontos de inflexão: clique em uma área vazia para adicionar um ponto, arraste os pontos para ajustar, clique duas vezes em um ponto para excluí-lo, Esc para concluir',
		'Cannot edit this element' => 'Não é possível editar este elemento',
		'This element type (host group / trigger / nested map) is maintained by the native Zabbix map editor; here it can only be previewed and moved.' => 'Este tipo de elemento (grupo de hosts / trigger / mapa aninhado) é mantido pelo editor de mapas nativo do Zabbix; aqui ele só pode ser pré-visualizado e movido.',
		'Unknown host' => 'Host desconhecido',
		'Image' => 'Imagem',
		'Disabled' => 'Desativado',
		'In maintenance' => 'Em manutenção',
		'OK' => 'OK',
		' problems' => ' incidentes',
		'%s problems' => '%s incidentes',
		'%s unacknowledged problems' => '%s incidentes não reconhecidos',
		'problem' => 'incidente',
		'Unacknowledged' => 'Não reconhecido',
		'Current problems: ' => 'Incidentes atuais: ',
		'Problem tags: ' => 'Tags de incidente: ',
		'Auto · ' => 'Automático · ',
		'The canvas size is not fixed: at least the viewport, it expands when elements are dragged right/down, ' => 'O tamanho da tela não é fixo: tem no mínimo o tamanho da área visível, expande quando elementos são arrastados para a direita/para baixo, ',
		'and the view pans when you drag to the edge; it shrinks to the content size on save. This shows the current actual size.' => 'e a visualização se desloca quando você arrasta até a borda; ao salvar, encolhe para o tamanho do conteúdo. Aqui é exibido o tamanho real atual.',
		'New element' => 'Novo elemento',
		'Remove mode: click the ' => 'Modo de remoção: clique em ',
		'map element' => 'elemento do mapa',
		'shape' => 'forma',
		'link' => 'enlace',
		' to remove (Esc to cancel)' => ' para remover (Esc para cancelar)',
		'Link mode: click two elements in order to create a link (Esc to cancel)' => 'Modo de enlace: clique em dois elementos, em ordem, para criar um enlace (Esc para cancelar)',
		'Link mode: selected "' => 'Modo de enlace: selecionado "',
		'element' => 'elemento',
		'", now click another element' => '", agora clique em outro elemento',
		'Map properties' => 'Propriedades do mapa',
		'Auto (current ' => 'Automático (atual ',
		' px)' => ' px)',
		'The canvas grows when you drag elements right/down and shrinks to fit on save, leaving no large empty area' => 'A tela cresce quando você arrasta elementos para a direita/para baixo e encolhe para caber ao salvar, sem deixar grandes áreas vazias',
		'Saved "' => 'Propriedades de "',
		'" properties' => '" salvas',
		'Save failed: ' => 'Falha ao salvar: ',
		'Delete map?' => 'Excluir o mapa?',
		'This will permanently delete "' => 'Isto excluirá permanentemente "',
		'" and all its elements, links and shapes. This action cannot be undone.' => '" e todos os seus elementos, enlaces e formas. Esta ação não pode ser desfeita.',
		'Deleted "' => 'Excluído "',
		'Close' => 'Fechar',
		'Type' => 'Tipo',
		'Label' => 'Rótulo',
		'Line breaks are allowed; selecting a host fills in "host name + IP"' => 'Quebras de linha são permitidas; ao selecionar um host, é preenchido "nome do host + IP"',
		'Label position' => 'Posição do rótulo',
		'Type to search' => 'Digite para pesquisar',
		'Select' => 'Selecionar',
		'(' => '(',
		'Problem tags' => 'Tags de incidente',
		'And/Or (default)' => 'E/Ou (padrão)',
		'Or' => 'Ou',
		'Add' => 'Adicionar',
		'Auto-select icon' => 'Selecionar ícone automaticamente',
		'Icon' => 'Ícone',
		'Maintenance' => 'Manutenção',
		'Select a host' => 'Selecione um host',
		'Tag' => 'Tag',
		'Value' => 'Valor',
		'(no icons available)' => '(nenhum ícone disponível)',
		'Map shape' => 'Forma do mapa',
		'Text' => 'Texto',
		'Font' => 'Fonte',
		'Font size' => 'Tamanho da fonte',
		'Color' => 'Cor',
		'Center' => 'Centro',
		'Horizontal align' => 'Alinhamento horizontal',
		'Vertical align' => 'Alinhamento vertical',
		'Background' => 'Fundo',
		'Border' => 'Borda',
		'Width (px)' => 'Largura (px)',
		'Coordinates' => 'Coordenadas',
		'Size' => 'Tamanho',
		'Height (px)' => 'Altura (px)',
		'Point' => 'Ponto',
		'This link has ' => 'Este enlace tem ',
		' member connections (link aggregation); changes apply to all members.' => ' conexões membro (agregação de enlaces); as alterações se aplicam a todos os membros.',
		'Line type' => 'Tipo de linha',
		'Routing' => 'Roteamento',
		'Pick waypoints on canvas' => 'Escolher pontos de inflexão na tela',
		'Auto orthogonal routes along horizontal/vertical segments; when "manual waypoints" is selected, click the button, ' => 'Ortogonal automático traça segmentos horizontais/verticais; ao selecionar "pontos de inflexão manuais", clique no botão, ',
		'then click empty canvas to add points, drag dots to adjust, double-click a dot to delete, Esc to finish.' => 'depois clique em uma área vazia da tela para adicionar pontos, arraste os pontos para ajustar, clique duas vezes em um ponto para excluí-lo, Esc para concluir.',
		'Trigger source' => 'Origem da trigger',
		'Source port: ' => 'Porta de origem: ',
		'unknown' => 'desconhecida',
		'  Target port: ' => '  Porta de destino: ',
		' (source = link start, target = link end)' => ' (origem = início do enlace, destino = fim do enlace)',
		'Select…' => 'Selecionar…',
		'Clear' => 'Limpar',
		'Items are chosen only from the hosts at both ends of this link; for aggregated links you can select multiple member ports and the values are summed.' => 'Os itens são escolhidos apenas entre os hosts das duas pontas deste enlace; em enlaces agregados você pode selecionar várias portas membro e os valores são somados.',
		'Trigger' => 'Trigger',
		'No triggers linked' => 'Nenhuma trigger vinculada',
		'Auto-linking matches only port-status keywords (link down / disabled / …) ' => 'O vínculo automático considera apenas palavras-chave de status de porta (link down / disabled / …) ',
		'and locates them by the port name on the link; manually created links can be selected here and will not be overwritten by auto-matching.' => 'e as localiza pelo nome da porta no enlace; enlaces criados manualmente podem ser selecionados aqui e não serão sobrescritos pela correspondência automática.',
		'No matching hosts' => 'Nenhum host correspondente',
		' (deleted or no access)' => ' (excluído ou sem acesso)',
		'Not selected' => 'Não selecionado',
		' items' => ' itens',
		'Zabbix agent' => 'Agente Zabbix',
		'Zabbix agent (active)' => 'Agente Zabbix (ativo)',
		'Simple check' => 'Verificação simples',
		'SNMP agent' => 'Agente SNMP',
		'Zabbix trapper' => 'Zabbix trapper',
		'Zabbix internal' => 'Interno do Zabbix',
		'External check' => 'Verificação externa',
		'Database monitor' => 'Monitoramento de banco de dados',
		'Aggregate' => 'Agregado',
		'HTTP agent' => 'Agente HTTP',
		'IPMI agent' => 'Agente IPMI',
		'SSH agent' => 'Agente SSH',
		'TELNET agent' => 'Agente TELNET',
		'Calculated' => 'Calculado',
		'JMX agent' => 'Agente JMX',
		'Dependent item' => 'Item dependente',
		'SNMP trap' => 'Trap SNMP',
		'Script' => 'Script',
		'Numeric (float)' => 'Numérico (fracionário)',
		'Character' => 'Caractere',
		'Log' => 'Log',
		'Numeric (unsigned)' => 'Numérico (inteiro sem sinal)',
		'No filter set' => 'Nenhum filtro definido',
		'Item' => 'Item',
		'Use the filter to show results' => 'Use o filtro para exibir resultados',
		'Loading failed' => 'Falha ao carregar',
		'Type ' => 'Tipo ',
		'Type of information ' => 'Tipo de informação ',
		'Enabled' => 'Ativado',
		'Not classified' => 'Não classificada',
		'Information' => 'Informação',
		'Warning' => 'Atenção',
		'Average' => 'Média',
		'High' => 'Alta',
		'Disaster' => 'Desastre',
		'Select a host or host group first, then pick triggers' => 'Selecione um host ou grupo de hosts primeiro e depois escolha as triggers',
		'No matching triggers' => 'Nenhuma trigger correspondente',
		'Try another keyword' => 'Tente outra palavra-chave',
		'Severity ' => 'Severidade ',
		'Saving…' => 'Salvando…',
		'Saved ' => 'Salvo ',
		'Save failed' => 'Falha ao salvar',
		'Exiting edit mode will discard unsaved changes.' => 'Sair do modo de edição descartará as alterações não salvas.',
		'Unknown operation' => 'Operação desconhecida',
		'Failed to encode the result as JSON: ' => 'Falha ao codificar o resultado como JSON: ',
		'Map does not exist or you do not have permission to view it' => 'O mapa não existe ou você não tem permissão para visualizá-lo',
		'A map named "%1$s" already exists; choose another name' => 'Já existe um mapa chamado "%1$s"; escolha outro nome',
		'Failed to create "%1$s": Zabbix did not return a map ID' => 'Falha ao criar "%1$s": o Zabbix não retornou o ID do mapa',
		'Failed to create "%1$s": %2$s' => 'Falha ao criar "%1$s": %2$s',
		'Save failed: Zabbix did not confirm the result' => 'Falha ao salvar: o Zabbix não confirmou o resultado',
		'Save failed: %1$s' => 'Falha ao salvar: %1$s',
		'Missing valid map ID' => 'ID de mapa válido ausente',
		'Failed to encode the exported content' => 'Falha ao codificar o conteúdo exportado',
		'Save failed: the submitted data is not valid JSON' => 'Falha ao salvar: os dados enviados não são um JSON válido',
		'No host specified for the item query' => 'Nenhum host especificado para a consulta de itens',
		'Discovery: no hosts specified to scan' => 'Descoberta: nenhum host especificado para varredura',
		'Discovery: the specified hosts do not exist or you have no permission' => 'Descoberta: os hosts especificados não existem ou você não tem permissão',
		'Host does not exist or you have no permission' => 'O host não existe ou você não tem permissão',
		'This batch exceeded the time budget and was skipped (please retry later)' => 'Este lote excedeu o tempo disponível e foi ignorado (tente novamente mais tarde)',
		'No SNMP interface' => 'Sem interface SNMP',
		'SNMP community is not defined (check the {$SNMP_COMMUNITY} macro)' => 'A comunidade SNMP não está definida (verifique a macro {$SNMP_COMMUNITY})',
		'Discovery: nothing to generate' => 'Descoberta: nada para gerar',
		'Auto discovery' => 'Descoberta automática',
		'Discovery: no devices to generate' => 'Descoberta: nenhum dispositivo para gerar',
		'Discovery: Zabbix did not return a map ID' => 'Descoberta: o Zabbix não retornou o ID do mapa',
		'Discovery: failed to generate the map: %1$s' => 'Descoberta: falha ao gerar o mapa: %1$s',
		'Rediscovery: failed to save the map' => 'Redescoberta: falha ao salvar o mapa',
		'Rediscovery: failed to save the map: %1$s' => 'Redescoberta: falha ao salvar o mapa: %1$s',
		'Rediscovery: nothing to update' => 'Redescoberta: nada para atualizar',
		'The map name cannot be empty' => 'O nome do mapa não pode ficar vazio',
		'The map name cannot exceed 128 characters' => 'O nome do mapa não pode exceder 128 caracteres',
		'PHP snmp extension is missing' => 'A extensão snmp do PHP está ausente',
		'SNMP did not respond (timeout or rate limiting); please retry later' => 'O SNMP não respondeu (tempo esgotado ou limitação de taxa); tente novamente mais tarde',
		'Auto topology map' => 'Mapa de topologia automático',
		'Content controls' => 'Controles de conteúdo',
		'Filter' => 'Filtro',
		'Actions' => 'Ações',
		'Pagination' => 'Paginação',
		'Export' => 'Exportar',
		'Back to list' => 'Voltar à lista',
		'Zoom out' => 'Reduzir',
		'Reset to 100%' => 'Redefinir para 100%',
		'Zoom in' => 'Ampliar',
		'Fit to window' => 'Ajustar à janela',
		'Fit' => 'Ajustar',
		'Rescan and update the current map in place' => 'Varrer novamente e atualizar o mapa atual no lugar',
		'Rediscover' => 'Redescobrir',
		'Edit map' => 'Editar mapa',
		'Save' => 'Salvar',
		'Exit editing' => 'Sair da edição',
		'Map elements:' => 'Elementos do mapa:',
		'Element properties' => 'Propriedades do elemento',
		'Link properties' => 'Propriedades do enlace',
		'Shapes:' => 'Formas:',
		'Links:' => 'Enlaces:',
		'Drag elements to move them; click an element, shape or link to edit its properties' => 'Arraste os elementos para movê-los; clique em um elemento, forma ou enlace para editar suas propriedades',
		'Key' => 'Chave',
		'Type of information' => 'Tipo de informação',
		'Status' => 'Status',
		'Host group' => 'Grupo de hosts',
		'Severity' => 'Severidade',
		'Select host' => 'Selecione o host',
		'%s topology maps in total' => '%s mapas de topologia no total',
		'Show labels' => 'Mostrar rótulos',
		'Always' => 'Sempre',
		'Auto-hide' => 'Ocultar automaticamente',
		'Color (OK)' => 'Cor (OK)',
		'Type (OK)' => 'Tipo (OK)',
		'Metrics' => 'Métricas',
	];

	/**
	 * Current language: en_US or pt_BR.
	 */
	public static function detectLanguage(): string {
		if (self::$currentLanguage !== null) {
			return self::$currentLanguage;
		}

		$lang = self::userLanguage();

		if (trim($lang) === '' || strtolower(trim($lang)) === 'default') {
			$lang = self::systemLanguage();
		}

		$lang = strtolower(trim($lang));

		// Supported languages: Brazilian Portuguese (pt*) and English (everything else).
		self::$currentLanguage = preg_match('/^pt(?:[-_]|$)/', $lang) ? 'pt_BR' : self::ZBX_DEFAULT_LANG;

		return self::$currentLanguage;
	}

	/**
	 * The current user's configured language; an empty string if unavailable.
	 */
	private static function userLanguage(): string {
		try {
			if (class_exists('CWebUser') && method_exists('CWebUser', 'get')) {
				$lang = \CWebUser::get('lang');

				if (is_string($lang) && $lang !== '') {
					return $lang;
				}
			}
		}
		catch (\Throwable $e) {
			// If unavailable, hand over to the next level
		}

		try {
			if (isset(\CWebUser::$data['lang']) && is_string(\CWebUser::$data['lang'])
					&& trim(\CWebUser::$data['lang']) !== '') {
				return \CWebUser::$data['lang'];
			}
		}
		catch (\Throwable $e) {
			// When get() is unavailable, still try the legacy static data, then the other sources.
		}

		// Same fallback order as the CMDB module, compatible with older frontend initialization.
		foreach ([$GLOBALS['USER_DETAILS']['lang'] ?? null,
			$GLOBALS['ZBX_LOCALES']['selected'] ?? null,
			$_SESSION['zbx_lang'] ?? null, $_SESSION['lang'] ?? null] as $lang) {
			if (is_string($lang) && trim($lang) !== '') {
				return $lang;
			}
		}

		// Uses the existing Zabbix database connection, compatible with MySQL / PostgreSQL.
		$userid = $_SESSION['userid'] ?? ($_SESSION['user']['userid'] ?? null);
		if (is_scalar($userid) && ctype_digit((string) $userid)
				&& function_exists('DBselect') && function_exists('DBfetch')) {
			try {
				$row = \DBfetch(\DBselect('SELECT lang FROM users WHERE userid='.(string) $userid));
				if (is_array($row) && isset($row['lang']) && is_string($row['lang'])) {
					return $row['lang'];
				}
			}
			catch (\Throwable $e) {
				// If the database is unavailable, keep inheriting the system language.
			}
		}

		return '';
	}

	/**
	 * System default language; an empty string if unavailable.
	 */
	private static function systemLanguage(): string {
		try {
			if (class_exists('CSettingsHelper') && method_exists('CSettingsHelper', 'get')) {
				$lang = \CSettingsHelper::get('default_lang');

				if (is_string($lang) && $lang !== '') {
					return $lang;
				}
			}
		}
		catch (\Throwable $e) {
			// Ignore
		}

		// Older frontends can provide the system default language through select_config().
		if (function_exists('select_config')) {
			try {
				$config = \select_config();
				if (is_array($config) && isset($config['default_lang'])
						&& is_string($config['default_lang'])) {
					return $config['default_lang'];
				}
			}
			catch (\Throwable $e) {
				// Fall back to English when no setting is found.
			}
		}

		return '';
	}

	/**
	 * Translate: returns the source text in English.
	 */
	public static function t(string $key): string {
		return self::detectLanguage() === 'pt_BR' ? (self::PT[$key] ?? $key) : $key;
	}

	/**
	 * Translation with parameters; placeholders use sprintf syntax (%s / %d).
	 */
	public static function tf(string $key, ...$args): string {
		return sprintf(self::t($key), ...$args);
	}

	/**
	 * Dictionary for the frontend: the Portuguese dictionary in pt_BR, an empty array in English.
	 * The frontend T() also falls back to the source text when nothing is found.
	 */
	public static function jsMap(): array {
		return self::detectLanguage() === 'pt_BR' ? self::PT : [];
	}

	public static function isChinese(): bool {
		return false; // Chinese is no longer supported.
	}

	/**
	 * Reset the cache (for tests).
	 */
	public static function resetLanguage(): void {
		self::$currentLanguage = null;
	}
}
