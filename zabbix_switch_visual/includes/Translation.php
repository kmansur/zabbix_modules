<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Includes;

/**
 * Module-local EN → pt_BR translator.
 *
 * Zabbix core locale files cannot translate strings shipped inside custom
 * modules, so widget strings are routed through Translation::t(). Brazilian
 * Portuguese is used for pt*; other languages retain the English source.
 */
class Translation {

	/** @var ?string */ private static $language = null;

	private const PORTUGUESE = [
		'N/A' => 'N/D',
		'd' => 'd',
		'h' => 'h',
		'm' => 'm',
		'H3C' => 'H3C',
		'S/N' => 'N/S',
		'Zabbix Switch Visual' => 'Zabbix Switch Visual',
		'Ports' => 'Portas',
		'PoE' => 'PoE',
		'System' => 'Sistema',
		'Appearance' => 'Aparência',
		'Display' => 'Exibição',
		'Host' => 'Host',
		'Host groups' => 'Grupos de hosts',
		'BW In item pattern' => 'Padrão do item de banda de entrada',
		'BW Out item pattern' => 'Padrão do item de banda de saída',
		'Bandwidth items deliver bits/sec (not bytes/sec)' => 'Os itens de banda fornecem bits/s (não bytes/s)',
		'Status item pattern' => 'Padrão do item de status',
		'Speed item pattern' => 'Padrão do item de velocidade',
		'Errors In item pattern' => 'Padrão do item de erros de entrada',
		'Errors Out item pattern' => 'Padrão do item de erros de saída',
		'Interface alias pattern (optional)' => 'Padrão do alias da interface (opcional)',
		'PoE status pattern (optional, e.g. pethPsePortDetectionStatus[*])' => 'Padrão de status PoE (opcional, ex.: pethPsePortDetectionStatus[*])',
		'PoE port power pattern (optional, e.g. pethPsePortPowerConsumption[*], milliwatts)' => 'Padrão de potência PoE da porta (opcional, ex.: pethPsePortPowerConsumption[*], miliwatts)',
		'Duplex mode pattern (optional, e.g. dot3StatsDuplexStatus[*])' => 'Padrão do modo duplex (opcional, ex.: dot3StatsDuplexStatus[*])',
		'SFP optical RX power pattern (optional)' => 'Padrão de potência óptica RX do SFP (opcional)',
		'SFP optical TX power pattern (optional)' => 'Padrão de potência óptica TX do SFP (opcional)',
		'Port shape' => 'Formato das portas',
		'Style 1 (stepped)' => 'Estilo 1 (escalonado)',
		'Style 2 (metal)' => 'Estilo 2 (metálico)',
		'Style 3 (classic)' => 'Estilo 3 (clássico)',
		'RJ45 ports (per stack member; the rest are SFP)' => 'Portas RJ45 (por membro da pilha; as demais são SFP)',
		'SFP ports (manual mode only — with auto-detect the ports beyond RJ45 count are SFP)' => 'Portas SFP (somente modo manual — com detecção automática, as portas além da quantidade de RJ45 são SFP)',
		'Port rows (1 or 2)' => 'Fileiras de portas (1 ou 2)',
		'Invert row order (even ports top — e.g. Huawei)' => 'Inverter a ordem das fileiras (portas pares em cima — ex.: Huawei)',
		'Sequential rows (first half top, second half bottom — e.g. 1–24 / 25–48)' => 'Fileiras sequenciais (primeira metade em cima, segunda metade embaixo — ex.: 1–24 / 25–48)',
		'Zoom (50–300%)' => 'Zoom (50–300%)',
		'Port aliases (e.g. 1=Uplink, 3=ESX01, 4-6=VLAN1)' => 'Aliases das portas (ex.: 1=Uplink, 3=ESX01, 4-6=VLAN1)',
		'Uptime item key (optional)' => 'Chave do item de uptime (opcional)',
		'Serial number item key (optional)' => 'Chave do item de número de série (opcional)',
		'Model item key (optional)' => 'Chave do item de modelo (opcional)',
		'CPU % item pattern (optional, e.g. system.cpu.util[*])' => 'Padrão do item de % de CPU (opcional, ex.: system.cpu.util[*])',
		'Memory % item pattern (optional, e.g. vm.memory.pused[*])' => 'Padrão do item de % de memória (opcional, ex.: vm.memory.pused[*])',
		'Temperature item pattern (optional, e.g. sensor.temp.value[*]; multiple sensors: panel shows max, hover shows all)' => 'Padrão do item de temperatura (opcional, ex.: sensor.temp.value[*]; vários sensores: o painel mostra o máximo, ao passar o mouse mostra todos)',
		'Temperature alarm threshold (°C, 0 = disabled)' => 'Limite de alarme de temperatura (°C, 0 = desativado)',
		'RJ45 position' => 'Posição das RJ45',
		'Front (ports 1..N are RJ45)' => 'Frente (portas 1..N são RJ45)',
		'Back (last N ports are RJ45 — SFP first)' => 'Fundo (as últimas N portas são RJ45 — SFP primeiro)',
		'Total PoE consumed item key (optional, e.g. pethMainPseConsumptionPower.1, watts)' => 'Chave do item de consumo total de PoE (opcional, ex.: pethMainPseConsumptionPower.1, watts)',
		'Max PoE capacity item key (optional, e.g. pethMainPsePower.1, watts)' => 'Chave do item de capacidade máxima de PoE (opcional, ex.: pethMainPsePower.1, watts)',
		'Fan status pattern (optional, e.g. hpicfFanState[*])' => 'Padrão de status dos ventiladores (opcional, ex.: hpicfFanState[*])',
		'Fan OK value (3=HP good, 1=some vendors — check MIB)' => 'Valor OK dos ventiladores (3=HP bom, 1=alguns fabricantes — verifique a MIB)',
		'Chassis color' => 'Cor do chassi',
		'Brand logo' => 'Logo do fabricante',
		'None' => 'Nenhum',
		'1 Gbps port color' => 'Cor da porta de 1 Gbps',
		'100 Mbps port color' => 'Cor da porta de 100 Mbps',
		'10 Gbps port color' => 'Cor da porta de 10 Gbps',
		'40 Gbps port color' => 'Cor da porta de 40 Gbps',
		'100 Gbps port color' => 'Cor da porta de 100 Gbps',
		'Warning port color' => 'Cor da porta em alerta',
		'Error port color' => 'Cor da porta com erro',
		'Port index start (SNMP offset)' => 'Índice inicial das portas (deslocamento SNMP)',
		'Auto-detect port count (may include VLANs/tunnels)' => 'Detectar quantidade de portas automaticamente (pode incluir VLANs/túneis)',
		'Excluded ports (auto-detect only; comma/space separated; name substring match, digits match index, * wildcard, e.g. vlan, Bridge-Aggregation, mgmt*)' => 'Portas excluídas (somente detecção automática; separadas por vírgula/espaço; trecho do nome, dígitos correspondem ao índice, curinga *, ex.: vlan, Bridge-Aggregation, mgmt*)',
		'Show summary bar' => 'Mostrar barra de resumo',
		'Show port numbers' => 'Mostrar números das portas',
		'Show port labels / aliases' => 'Mostrar rótulos / aliases das portas',
		'Show global traffic sparkline' => 'Mostrar minigráfico de tráfego global',
		'Sparkline window (minutes)' => 'Janela do minigráfico (minutos)',
		'Utilization warning threshold (%)' => 'Limite de alerta de utilização (%)',
		'Select a host or host group in widget settings.' => 'Selecione um host ou grupo de hosts nas configurações do widget.',
		'No ports found — verify item key patterns match your Zabbix items.' => 'Nenhuma porta encontrada — verifique se os padrões de chave correspondem aos seus itens do Zabbix.',
		'Error: ' => 'Erro: ',
		'Invalid host.' => 'Host inválido.',
		'API error.' => 'Erro de API.',
		'Active Problems' => 'Incidentes ativos',
		'Type' => 'Tipo',
		'Status' => 'Status',
		'Speed' => 'Velocidade',
		'Port' => 'Porta',
		'RX' => 'RX',
		'TX' => 'TX',
		'Util' => 'Util.',
		'Down for' => 'Inativa há',
		'Duplex' => 'Duplex',
		'Half (!)' => 'Half (!)',
		'Opt RX' => 'RX óptico',
		'Opt TX' => 'TX óptico',
		'Delivering' => 'Fornecendo',
		'Traffic' => 'Tráfego',
		'green' => 'verde',
		'amber' => 'âmbar',
		'red' => 'vermelho',
		'gray' => 'cinza',
		'Error value: %.2f' => 'Valor de erro: %.2f',
		'Utilization > %.0f%%' => 'Utilização > %.0f%%',
		'min' => 'min',
		'Huawei' => 'Huawei',
		'Ruijie' => 'Ruijie',
		'ZTE' => 'ZTE',
		'Inspur' => 'Inspur',
		'FiberHome' => 'FiberHome',
		'Maipu' => 'Maipu',
		'DPtech' => 'DPtech',
		'Hillstone' => 'Hillstone',
		'Topsec' => 'Topsec',
		'Venustech' => 'Venustech',
		'NSFOCUS' => 'NSFOCUS',
		'Sangfor' => 'Sangfor',
		'Qianxin' => 'Qianxin',
		'DBAPPSecurity' => 'DBAPPSecurity',
		'Leadsec' => 'Leadsec',
		'Knownsec' => 'Knownsec',
		'iKuai' => 'iKuai',
		'Sundray' => 'Sundray',
		'HP' => 'HP',
		'Siemens' => 'Siemens',
		'Warom' => 'Warom',
		'up' => 'up',
		'dn' => 'dn',
		'total' => 'total',
		'Member' => 'Membro',
		'Err' => 'Err',
		'ports' => 'portas',
		'Uptime' => 'Uptime',
		'Memory' => 'Memória',
		'Temperature sensors' => 'Sensores de temperatura',
		'Sensor' => 'Sensor',
		'Temp' => 'Temp.',
		'PoE cap' => 'Cap. PoE',
		'Fans' => 'Ventiladores',
		'FAIL' => 'FALHA',
		'At' => 'Em',
		'Select a host' => 'Selecione um host',
		'Switch visual' => 'Visualização de switch',
	];

	/**
	 * Translate a source string using the current Zabbix language.
	 */
	public static function t(string $en): string {
		if (self::$language === null) {
			$lang = '';
			if (class_exists('CWebUser') && \CWebUser::$data !== null && isset(\CWebUser::$data['lang'])) {
				$lang = (string) \CWebUser::$data['lang'];
			}
			if ($lang === '' || strcasecmp($lang, 'default') === 0) {
				try {
					if (class_exists('CSettingsHelper') && method_exists('CSettingsHelper', 'get')) {
						$lang = (string) \CSettingsHelper::get('default_lang');
					}
				}
				catch (\Throwable $e) {
					$lang = '';
				}
				if (($lang === '' || strcasecmp($lang, 'default') === 0)
						&& isset($GLOBALS['ZBX_LOCALES']['selected'])) {
					$lang = (string) $GLOBALS['ZBX_LOCALES']['selected'];
				}
			}
			$lang = strtolower(str_replace('-', '_', trim($lang)));
			// Supported languages: Brazilian Portuguese (pt*) and English (everything else).
			self::$language = preg_match('/^pt(?:_|$)/', $lang) ? 'pt_BR' : 'en_US';
		}
		if (self::$language === 'pt_BR') {
			return self::PORTUGUESE[$en] ?? $en;
		}

		return $en;
	}
}
