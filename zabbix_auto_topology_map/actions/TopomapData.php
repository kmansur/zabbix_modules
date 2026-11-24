<?php declare(strict_types = 1);

namespace Modules\Topomap\Actions;

use API;
use CController;
use CControllerResponseData;
use CProfile;
use CRoleHelper;
use Modules\Topomap\Lib\LanguageManager;

/**
 * Data endpoint of the auto topology map. zabbix.php?action=topomap.data
 *
 * Data is stored directly in native Zabbix sysmaps (map.create / map.update / map.delete),
 * so maps created here are also visible in Monitoring → Maps, and vice versa.
 * All calls use the current user's session; host/map permissions are enforced by Zabbix itself.
 */
class TopomapData extends CController {

	private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

	/** Map element (sysmaps_elements) fields that may be written back; everything else is dropped. */
	private const SELEMENT_FIELDS = [
		'selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on', 'iconid_disabled',
		'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap', 'evaltype', 'tags',
		'urls', 'width', 'height', 'elementsubtype', 'areatype', 'viewtype'
	];

	private const LINK_FIELDS = ['linkid', 'selementid1', 'selementid2', 'color', 'drawtype', 'label', 'show_label'];

	/**
	 * Per-link "metric item" configuration table.
	 *
	 * sysmaps_links only has seven columns: linkid/sysmapid/selementid1/selementid2/drawtype/color/label,
	 * with no spare column for bandwidth/upstream/downstream items, so the module keeps its own table keyed by linkid.
	 * The table is created lazily on the get/save path (CREATE TABLE IF NOT EXISTS), only once on the first request.
	 */
	private const LINK_METRICS_TABLE = 'topomap_link_metrics';

	/**
	 * Per-link "routing / waypoint" configuration table.
	 *
	 * routing: 0 automatic curve (default) / 1 automatic orthogonal / 2 manual waypoints;
	 * waypoints: JSON array [{"x":120,"y":80}, ...], used only when routing=2.
	 * Keyed by linkid like the metrics table and created lazily on the read/write path.
	 */
	private const LINK_PATHS_TABLE = 'topomap_link_paths';

	/**
	 * Module-level settings table (key/value): currently only stores the keywords for automatic trigger linking, one per line.
	 * Created lazily on the read/write path, like the metrics table.
	 */
	private const SETTINGS_TABLE = 'topomap_settings';

	private static $metricsTableReady = false;
	private static $pathsTableReady = false;
	private static $settingsTableReady = false;

	/** Keywords cached for this request (null = not read yet) */
	private static $triggerKeywords = null;

	/** Base color of automatically discovered links (normal state). The color below is used when a trigger is in problem state. */
	private const DISCOVERY_LINK_COLOR = '00CC00';

	/** Link color when the port status trigger bound to an automatically discovered link is in problem state. */
	private const DISCOVERY_TRIGGER_COLOR = 'DD0000';

	/**
	 * Default label of host elements: host name on the first line, connection address on the second.
	 * Stores the raw macros and expands them at render time, so renamed hosts / changed interfaces are picked up automatically.
	 */
	private const DEFAULT_HOST_LABEL = "{HOST.NAME}\n{HOST.CONN}";

	/**
	 * Keywords that identify "port status" triggers (kept in sync with the aiops project's lldpDiscoveryService):
	 * a trigger belongs to a link only if its description contains one of them and also the port name.
	 */
	private const LINK_TRIGGER_KEYWORDS = [
		'link down', 'link down on', 'is down', 'has changed status',
		'处于关闭状态', '链路中断', '接口关闭', '接口状态变化', '端口关闭'
	];

	/**
	 * "Trigger source" scope (configurable per link, stored in the trigger_source column of the module table):
	 *   AUTO   automatic: source end first, falls back to the local end only if the source does not match (legacy behavior)
	 *   SOURCE source only: match the source port only; no match means no link
	 *   TARGET local only: match the local port only
	 *   BOTH   both ends: link triggers matched on either end
	 *   NONE   none: ignore this link
	 */
	public const TRIGGER_SOURCE_AUTO = 0;
	public const TRIGGER_SOURCE_SOURCE = 1;
	public const TRIGGER_SOURCE_TARGET = 2;
	public const TRIGGER_SOURCE_BOTH = 3;
	public const TRIGGER_SOURCE_NONE = 4;

	private const SHAPE_FIELDS = [
		'sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font', 'font_size', 'font_color',
		'text_halign', 'text_valign', 'border_type', 'border_width', 'border_color', 'background_color', 'zindex'
	];

	public function init(): void {
		foreach (['disableCsrfValidation', 'disableSIDValidation', 'disableSIDvalidation'] as $method) {
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
	}

	protected function checkInput(): bool {
		// getInput() only reads the whitelist filled by validateInput(), so every field must be declared here;
		// nothing is marked required; missing parameters fall back to getInput defaults.
		$this->validateInput([
			'mode' => 'string',
			'sysmapid' => 'string',
			'sysmapids' => 'string',
			'name' => 'string',
			'width' => 'string',
			'height' => 'string',
			'payload' => 'string',
			'search' => 'string',
			// Auto discovery: hosts to scan (comma-separated), protocol, per-host SNMP timeout (ms)
			'hostids' => 'string',
			'protocols' => 'string',
			'timeout' => 'string',
			// "Host group" filter of the item selector (comma-separated)
			'groupids' => 'string',
			// Re-matching of the link "trigger source" property: hosts at both ends + ports + scope
			'hostid1' => 'string',
			'hostid2' => 'string',
			'port1' => 'string',
			'port2' => 'string',
			'trigger_source' => 'string',
			// Trigger selector: look up the names of already bound triggers by triggerid
			'triggerids' => 'string',
			// Keywords for automatic trigger linking (one per line, stored in the module settings table)
			'trigger_keywords' => 'string'
		]);

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		try {
			switch ($this->getInput('mode', 'list')) {
				case 'list':
					$result = $this->modeList();
					break;

				case 'get':
					$result = $this->modeGet();
					break;

				case 'create':
					$result = $this->modeCreate();
					break;

				case 'rename':
					$result = $this->modeRename();
					break;

				case 'delete':
					$result = $this->modeDelete();
					break;

				case 'export':
					$result = $this->modeExport();
					break;

				case 'save':
					$result = $this->modeSave();
					break;

				case 'icons':
					$result = $this->modeIcons();
					break;

				case 'hosts':
					$result = $this->modeHosts();
					break;

				case 'hostgroups':
					$result = $this->modeHostgroups();
					break;

				case 'items':
					$result = $this->modeItems();
					break;

				case 'linkTriggers':
					$result = $this->modeLinkTriggers();
					break;

				case 'hostTriggers':
					$result = $this->modeHostTriggers();
					break;

				case 'settings':
					$result = $this->modeSettings();
					break;

				case 'saveSettings':
					$result = $this->modeSaveSettings();
					break;

				case 'discover':
					$result = $this->modeDiscover();
					break;

				case 'discoverSave':
					$result = $this->modeDiscoverSave();
					break;

				case 'rediscover':
					$result = $this->modeRediscover();
					break;

				case 'remember':
					$result = $this->modeRemember();
					break;

				default:
					throw new \Exception(LanguageManager::t('Unknown operation'));
			}
		}
		catch (\Throwable $e) {
			$result = ['success' => false, 'error' => $e->getMessage()];
		}

		// layout.json outputs the main_block string directly, without json_encode.
		$json = json_encode($result, self::JSON_FLAGS);

		if ($json === false) {
			$json = json_encode(['success' => false, 'error' => LanguageManager::t('Failed to encode the result as JSON: ').json_last_error_msg()]);
		}

		$this->setResponse(new CControllerResponseData([
			'main_block' => $json
		]));
	}

	// ------------------------------------------------------------------
	// Permissions
	// ------------------------------------------------------------------

	private function canEdit(): bool {
		try {
			if (class_exists('CRoleHelper') && defined('CRoleHelper::ACTIONS_EDIT_MAPS')) {
				return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS);
			}
		}
		catch (\Throwable $e) {
			// Fall back to the user type below
		}

		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	// ------------------------------------------------------------------
	// List / details
	// ------------------------------------------------------------------

	private function modeList(): array {
		$maps = API::Map()->get([
			'output' => ['sysmapid', 'name', 'width', 'height'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'selectSelements' => ['selementid']
		]);

		$rows = [];

		foreach ($maps as $map) {
			$rows[] = [
				'sysmapid' => (string) $map['sysmapid'],
				'name' => $map['name'],
				'elements' => count($map['selements']),
				'width' => (int) $map['width'],
				'height' => (int) $map['height']
			];
		}

		return ['success' => true, 'maps' => $rows, 'can_edit' => $this->canEdit()];
	}

	private function modeGet(): array {
		$sysmapid = $this->requireSysmapid();

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			// label_* decides what the element label shows (0=custom label 1=IP 2=name 3=status 4=none 5=custom macro template),
			// show_unack / expandproblem decide how many lines and what to write when there is a problem. Native maps render from these fields.
			'output' => ['sysmapid', 'name', 'width', 'height', 'label_type', 'label_location',
				'label_format', 'label_type_host', 'label_type_hostgroup', 'label_type_trigger',
				'label_type_map', 'label_type_image',
				'label_string_host', 'label_string_hostgroup', 'label_string_trigger',
				'label_string_map', 'label_string_image',
				'show_unack', 'expandproblem', 'iconmapid', 'backgroundid',
				// Element highlight switch: natively, the severity-colored circle behind the icon is drawn only when highlight is on
				'highlight'
			],
			// selementid / sysmap_shapeid must be listed explicitly: once output is specified,
			// Zabbix only returns whitelisted fields; without them the frontend cannot tell existing elements from new ones,
			// and every save would insert the old elements again as new ones.
			'selectSelements' => ['selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on',
				'iconid_disabled', 'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap',
				'evaltype', 'tags', 'urls', 'elementsubtype', 'areatype', 'width', 'height', 'viewtype'
			],
			// linktriggers is a nested field of selectLinks (CMap.php in 7.0.28 checks
			// outputIsRequested('linktriggers', selectLinks)); a hard-coded field list would query
			// linktriggers as a sysmaps_links column and fail with an unknown field, so extend is required here.
			// Auto discovery binds port status triggers here; on alarm the link is drawn in DD0000.
			'selectLinks' => 'extend',
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font',
				'font_size', 'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width',
				'border_color', 'background_color', 'zindex'
			]
		]);

		if (!$maps) {
			throw new \Exception(LanguageManager::t('Map does not exist or you do not have permission to view it'));
		}

		$map = $maps[0];
		$map['selements'] = isset($map['selements']) ? array_values($map['selements']) : [];
		$map['links'] = $this->markLinkTriggerProblems(
			isset($map['links']) ? array_values($map['links']) : []
		);
		$map['shapes'] = isset($map['shapes']) ? array_values($map['shapes']) : [];

		$hostids = [];

		foreach ($map['selements'] as $selement) {
			if ((int) $selement['elementtype'] === 0) {
				foreach ($selement['elements'] as $element) {
					if (isset($element['hostid']) && $element['hostid'] !== '') {
						$hostids[$element['hostid']] = $element['hostid'];
					}
				}
			}
		}

		$map['hosts'] = $this->collectHosts(array_values($hostids));

		// Per-link bandwidth / upstream / downstream item configuration (module table topomap_link_metrics).
		// Then fetch the latest values of the items in use in one go; the frontend renders the metric labels at the link midpoint, summed over member ports.
		$metric_map = $this->loadLinkMetrics($sysmapid);
		$path_map = $this->loadLinkPaths($sysmapid);
		$itemids = [];

		foreach ($map['links'] as &$link) {
			$metrics = $metric_map[(string) ($link['linkid'] ?? '')] ?? [
				'bw' => [], 'in' => [], 'out' => [], 'source_port' => '', 'target_port' => '',
				'trigger_source' => self::TRIGGER_SOURCE_AUTO
			];
			$path = $path_map[(string) ($link['linkid'] ?? '')] ?? [
				// Auto-discovered links (with port names at both ends) default to "automatic orthogonal", branching from bottom-center to top-center in the tree layout;
				// manually drawn links still default to "automatic curve".
				'routing' => ($metrics['source_port'] !== '' || $metrics['target_port'] !== '') ? 1 : 0,
				'waypoints' => []
			];

			// Metric labels, the port names at both ends (drawn at the link ends) and the "trigger source" scope are sent to the frontend together
			$link['metrics'] = ['bw' => $metrics['bw'], 'in' => $metrics['in'], 'out' => $metrics['out']];
			$link['source_port'] = $metrics['source_port'];
			$link['target_port'] = $metrics['target_port'];
			$link['trigger_source'] = $metrics['trigger_source'];
			// Routing mode and manual waypoints (module table topomap_link_paths)
			$link['routing'] = $path['routing'];
			$link['waypoints'] = $path['waypoints'];

			foreach (['bw', 'in', 'out'] as $key) {
				foreach ($metrics[$key] as $itemid) {
					$itemids[$itemid] = $itemid;
				}
			}
		}
		unset($link);

		$map['metric_items'] = $this->fetchMetricItems(array_values($itemids));

		return [
			'success' => true,
			'map' => $map,
			'colors' => $this->labelColors(),
			'can_edit' => $this->canEdit()
		];
	}

	/**
	 * Colors of element status lines. As in the native map: normal/problem/acknowledged each have a setting,
	 * while disabled and maintenance use fixed colors (see getSelementInfo() in 7.0.28 maps.inc.php).
	 */
	private function labelColors(): array {
		$colors = [
			'ok' => '009900',
			'ok_ack' => '009900',
			'problem' => 'E45959',
			'problem_ack' => 'E97659',
			'disabled' => '960000',
			'maintenance' => 'EE9600'
		];

		if (!class_exists('\CSettingsHelper')) {
			return $colors;
		}

		try {
			$colors['ok'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::OK_UNACK_COLOR), '#');
			$colors['ok_ack'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::OK_ACK_COLOR), '#');
			$colors['problem'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::PROBLEM_UNACK_COLOR), '#');
			$colors['problem_ack'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::PROBLEM_ACK_COLOR), '#');
		}
		catch (\Throwable $e) {
			// If the global settings cannot be read, use the Zabbix default colors; the map must still open
		}

		return $colors;
	}

	/**
	 * Marks whether each trigger bound to a link is currently in problem state.
	 *
	 * Native sysmap link indicators already change the link color when a trigger fires, but the native map
	 * queries that itself while rendering; this module draws its own canvas, so the problem state is returned too and the frontend can draw the link in DD0000.
	 *
	 * @param array $links links returned by selectLinks (each may carry linktriggers)
	 */
	private function markLinkTriggerProblems(array $links): array {
		$triggerids = [];

		foreach ($links as &$link) {
			$link['linktriggers'] = isset($link['linktriggers']) && is_array($link['linktriggers'])
				? array_values($link['linktriggers'])
				: [];

			foreach ($link['linktriggers'] as $lt) {
				if (!empty($lt['triggerid'])) {
					$triggerids[(string) $lt['triggerid']] = (string) $lt['triggerid'];
				}
			}
		}
		unset($link);

		$problems = [];
		$enabled = [];

		if ($triggerids) {
			try {
				// Native CMapHelper only considers enabled link triggers (status == TRIGGER_STATUS_ENABLED);
				// unresolved problems left on disabled triggers must not change the link color.
				$rows = API::Trigger()->get([
					'output' => ['triggerid'],
					'triggerids' => array_values($triggerids),
					'filter' => ['status' => 0],
					'preservekeys' => true
				]);

				foreach ($rows as $triggerid => $row) {
					$enabled[(string) $triggerid] = true;
				}

				$rows = API::Problem()->get([
					'output' => ['objectid'],
					'objectids' => array_values($triggerids)
				]);

				foreach ($rows as $row) {
					$problems[(string) $row['objectid']] = true;
				}
			}
			catch (\Throwable $e) {
				// If problems cannot be read, treat everything as normal and keep the base color; the map must still open
			}
		}

		foreach ($links as &$link) {
			foreach ($link['linktriggers'] as &$lt) {
				$lt['problem'] = isset($problems[(string) $lt['triggerid']])
					&& isset($enabled[(string) $lt['triggerid']]);
				$lt['color'] = strtoupper(ltrim((string) ($lt['color'] ?? self::DISCOVERY_TRIGGER_COLOR), '#'));
			}
			unset($lt);
		}
		unset($link);

		return $links;
	}

	/**
	 * Host name/status + number of unresolved problems, unacknowledged count, highest severity and title.
	 * In preview this lights the problem icon, adds the badge and writes "OK / Problem / Disabled / In maintenance" under the label —
	 * these lines are the second part of the element label on native maps.
	 */
	private function collectHosts(array $hostids): array {
		if (!$hostids) {
			return [];
		}

		$result = [];

		$hosts = API::Host()->get([
			'hostids' => $hostids,
			'output' => ['hostid', 'name', 'status', 'maintenance_status'],
			'selectInterfaces' => ['ip', 'dns', 'main', 'type', 'useip'],
			'preservekeys' => true
		]);

		foreach ($hosts as $hostid => $host) {
			$ip = $this->hostIp($host);

			$result[$hostid] = [
				'name' => $host['name'],
				'ip' => $ip,
				// Connection address for {HOST.CONN}: main interface first, then the SNMP interface, then IP/DNS according to useip
				'conn' => $this->hostConn($host),
				'status' => (int) $host['status'],
				'maintenance' => isset($host['maintenance_status']) ? (int) $host['maintenance_status'] : 0,
				'problems' => 0,
				'unack' => 0,
				'severity' => -1,
				'title' => ''
			];
		}

		try {
			// Same as native maps (getSelementsInfo): first take only triggers that are enabled, monitored and currently in problem state,
			// then fetch their problems. Fetching problems directly by hostids also counted unresolved problems left on disabled triggers,
			// so the native map showed 1 while the module showed 2.
			$triggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'selectHosts' => ['hostid'],
				'hostids' => $hostids,
				'filter' => ['state' => null],
				'monitored' => true,
				'only_true' => true,
				'skipDependent' => true
			]);
		}
		catch (\Throwable $e) {
			// Failing to read problem data must not prevent the map from opening
			return $result;
		}

		if (!$triggers) {
			return $result;
		}

		$trigger_hosts = [];

		foreach ($triggers as $trigger) {
			foreach ($trigger['hosts'] as $host) {
				$trigger_hosts[$trigger['triggerid']][] = $host['hostid'];
			}
		}

		try {
			// Without recent: true — only unresolved problems count (native countSelementProblems only counts r_clock = 0)
			$problems = API::Problem()->get([
				'output' => ['objectid', 'severity', 'name', 'acknowledged'],
				'objectids' => array_keys($trigger_hosts)
			]);
		}
		catch (\Throwable $e) {
			return $result;
		}

		if (!$problems) {
			return $result;
		}

		foreach ($problems as $problem) {
			if (!isset($trigger_hosts[$problem['objectid']])) {
				continue;
			}

			$severity = (int) $problem['severity'];
			$unack = empty($problem['acknowledged']) ? 1 : 0;
			$title = isset($problem['name']) ? (string) $problem['name'] : '';

			foreach ($trigger_hosts[$problem['objectid']] as $hostid) {
				if (!isset($result[$hostid])) {
					continue;
				}

				$result[$hostid]['problems']++;
				$result[$hostid]['unack'] += $unack;

				// Only keep the title of the most severe one, for the "show single problem" mode
				if ($severity > $result[$hostid]['severity']) {
					$result[$hostid]['severity'] = $severity;
					$result[$hostid]['title'] = $title;
				}
			}
		}

		return $result;
	}

	// ------------------------------------------------------------------
	// Create / update / delete
	// ------------------------------------------------------------------

	private function modeCreate(): array {
		$name = $this->requireName();
		$width = $this->dimension('width') ?? 1000;
		$height = $this->dimension('height') ?? 700;

		// Map names are unique in sysmaps. In 7.0.28, map.create does not throw on a duplicate name
		// but simply returns false (verified), so without checking first the frontend only gets an empty sysmapid.
		$exists = API::Map()->get([
			'output' => ['sysmapid'],
			'filter' => ['name' => $name]
		]);

		if ($exists) {
			throw new \Exception(LanguageManager::tf('A map named "%1$s" already exists; choose another name', $name));
		}

		// The frontend API wrapper only returns false when map.create validation fails, and logs the error separately;
		// remember the message count before the call and include the real Zabbix error when it fails.
		$message_offset = $this->apiMessageCount();

		$result = API::Map()->create([[
			'name' => $name,
			'width' => $width,
			'height' => $height,
			// With label_type=0 the native map also shows the custom element label directly,
			// so both entry points show the same content.
			'label_type' => 0,
			'label_location' => 0,
			'grid_show' => 0,
			'grid_align' => 1,
			'grid_size' => 50,
			'highlight' => 1,
			'private' => 0
		]]);

		$sysmapid = '';

		if (is_array($result)) {
			if (!empty($result['sysmapids'][0])) {
				$sysmapid = (string) $result['sysmapids'][0];
			}
			elseif (!empty($result['sysmapid'])) {
				$sysmapid = (string) $result['sysmapid'];
			}
		}

		if ($sysmapid === '') {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('Failed to create "%1$s": %2$s', $name, $detail));
			}

			throw new \Exception(LanguageManager::tf('Failed to create "%1$s": Zabbix did not return a map ID', $name));
		}

		return ['success' => true, 'sysmapid' => $sysmapid];
	}

	private function modeRename(): array {
		$sysmapid = $this->requireSysmapid();
		$name = $this->requireName();

		// Renaming is also subject to the unique-name rule; check first and give an understandable message
		$exists = API::Map()->get([
			'output' => ['sysmapid'],
			'filter' => ['name' => $name]
		]);

		foreach ($exists as $row) {
			if ((string) $row['sysmapid'] !== $sysmapid) {
				throw new \Exception(LanguageManager::tf('A map named "%1$s" already exists; choose another name', $name));
			}
		}

		$update = [
			'sysmapid' => $sysmapid,
			'name' => $name
		];

		// Width / height are optional: when omitted the current values are kept, so old clients cannot silently reset them to 1000×700
		$width = $this->dimension('width');
		$height = $this->dimension('height');

		if ($width !== null) {
			$update['width'] = $width;
		}

		if ($height !== null) {
			$update['height'] = $height;
		}

		API::Map()->update([$update]);

		return ['success' => true];
	}

	/**
	 * Delete one map (sysmapid) or several (sysmapids, comma-separated).
	 */
	private function modeDelete(): array {
		$raw = trim((string) $this->getInput('sysmapids', ''));
		$ids = [];

		if ($raw === '') {
			$ids[] = $this->requireSysmapid();
		}
		else {
			foreach (explode(',', $raw) as $id) {
				$id = trim($id);

				if ($id !== '' && ctype_digit($id) && $id !== '0') {
					$ids[] = $id;
				}
			}
		}

		if (!$ids) {
			throw new \Exception(LanguageManager::t('Missing valid map ID'));
		}

		API::Map()->delete($ids);

		// When a map is deleted, its metric settings in the module tables must be removed too, otherwise they become orphan rows
		$this->dropLinkMetricsForMaps($ids);
		$this->dropLinkPathsForMaps($ids);

		foreach ($ids as $id) {
			$this->dropMapLayout((string) $id);
		}

		return ['success' => true, 'deleted' => count($ids)];
	}

	/**
	 * Delete the routing/waypoint settings of several maps (cleanup on map deletion, avoids orphan rows).
	 *
	 * @param string[] $sysmapids
	 */
	private function dropLinkPathsForMaps(array $sysmapids): void {
		$this->ensurePathsTable();

		$ids = [];

		foreach ($sysmapids as $id) {
			if (ctype_digit((string) $id) && (int) $id > 0) {
				$ids[] = (int) $id;
			}
		}

		if (!$ids) {
			return;
		}

		\DBexecute(
			'DELETE FROM '.self::LINK_PATHS_TABLE.' WHERE sysmapid IN ('.implode(',', $ids).')'
		);
	}

	/**
	 * Delete the metric settings of several maps (cleanup on map deletion, avoids orphan rows).
	 *
	 * @param string[] $sysmapids
	 */
	private function dropLinkMetricsForMaps(array $sysmapids): void {
		$this->ensureMetricsTable();

		$ids = [];

		foreach ($sysmapids as $id) {
			if (ctype_digit((string) $id) && (int) $id > 0) {
				$ids[] = (int) $id;
			}
		}

		if (!$ids) {
			return;
		}

		\DBexecute(
			'DELETE FROM '.self::LINK_METRICS_TABLE.' WHERE sysmapid IN ('.implode(',', $ids).')'
		);
	}

	/**
	 * Export maps as JSON (module format that can be imported back into sysmaps.
	 * Without sysmapids, all visible maps are exported).
	 */
	private function modeExport(): array {
		$raw = trim((string) $this->getInput('sysmapids', ''));
		$ids = [];

		foreach (explode(',', $raw) as $id) {
			$id = trim($id);

			if ($id !== '' && ctype_digit($id) && $id !== '0') {
				$ids[] = $id;
			}
		}

		$params = [
			'output' => ['sysmapid', 'name', 'width', 'height', 'label_type', 'label_location',
				'grid_show', 'grid_align', 'grid_size', 'highlight', 'private'
			],
			'selectSelements' => ['selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on',
				'iconid_disabled', 'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap',
				'evaltype', 'tags', 'urls', 'elementsubtype', 'areatype', 'width', 'height', 'viewtype'
			],
			'selectLinks' => ['linkid', 'selementid1', 'selementid2', 'color', 'drawtype', 'label'],
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font',
				'font_size', 'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width',
				'border_color', 'background_color', 'zindex'
			],
			'sortfield' => 'name',
			'sortorder' => 'ASC'
		];

		if ($ids) {
			$params['sysmapids'] = $ids;
		}

		$maps = API::Map()->get($params);

		$json = json_encode([
			'zabbix_export' => [
				'version' => '1.0',
				'generator' => 'topomap',
				'date' => date('Y-m-d\TH:i:s\Z'),
				'maps' => $maps
			]
		], self::JSON_FLAGS);

		if ($json === false) {
			throw new \Exception(LanguageManager::t('Failed to encode the exported content'));
		}

		return [
			'success' => true,
			'count' => count($maps),
			'filename' => 'topomap-'.date('Ymd-His').'.json',
			'json' => $json
		];
	}

	private function modeSave(): array {
		$sysmapid = $this->requireSysmapid();

		$raw = (string) $this->getInput('payload', '');
		$payload = json_decode($raw, true);

		if (!is_array($payload)) {
			throw new \Exception(LanguageManager::t('Save failed: the submitted data is not valid JSON'));
		}

		$update = ['sysmapid' => $sysmapid];

		if (array_key_exists('name', $payload) && trim((string) $payload['name']) !== '') {
			$update['name'] = $this->normalizeName((string) $payload['name']);
		}

		// The frontend computes the canvas size from the content and submits it (grows while dragging, shrinks on save);
		// here we only clamp the range to avoid 0 or huge values.
		if (array_key_exists('width', $payload)) {
			$update['width'] = max(100, min(10000, (int) $payload['width']));
		}

		if (array_key_exists('height', $payload)) {
			$update['height'] = max(100, min(10000, (int) $payload['height']));
		}

		$update['selements'] = $this->sanitizeSelements(
			isset($payload['selements']) && is_array($payload['selements']) ? $payload['selements'] : []
		);

		$update['links'] = $this->sanitizeLinks(
			isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : [],
			$this->existingLinkIndicatorTypes($sysmapid)
		);

		// In 7.0.x, CMapElement::checkLinkInput() spells the method name as "updateLink",
		// while the actual call passes __FUNCTION__ = "updateLinks", so the "update links" path
		// always takes the "create link" validation branch, which requires sysmapid on every link.
		// 7.4/8.0 use the field whitelist of CMap::validateLinkFields(), which has no sysmapid;
		// sending it fails with 'Invalid parameter "/1/links/1": unexpected parameter "sysmapid"', so it is only added for old versions.
		if (self::linkSysmapidRequired()) {
			foreach ($update['links'] as &$link) {
				if (isset($link['linkid'])) {
					$link['sysmapid'] = $sysmapid;
				}
			}
			unset($link);
		}

		$update['shapes'] = $this->sanitizeShapes(
			isset($payload['shapes']) && is_array($payload['shapes']) ? $payload['shapes'] : []
		);

		$message_offset = $this->apiMessageCount();
		$update_result = API::Map()->update([$update]);

		if ($update_result === false) {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('Save failed: %1$s', $detail));
			}

			throw new \Exception(LanguageManager::t('Save failed: Zabbix did not confirm the result'));
		}

		// After saving, read the links back once to claim the real linkid of newly created links, then write each link's
		// bandwidth/upstream/downstream items into the module table. sysmaps_links has no spare columns,
		// so metric settings can only live in a separate table keyed by linkid.
		$this->persistLinkMetrics(
			$sysmapid,
			isset($payload['link_metrics']) && is_array($payload['link_metrics']) ? $payload['link_metrics'] : [],
			$update['links']
		);

		// Per-link routing mode and manual waypoints (topomap_link_paths)
		$this->persistLinkPaths(
			$sysmapid,
			isset($payload['link_paths']) && is_array($payload['link_paths']) ? $payload['link_paths'] : [],
			$update['links']
		);

		return ['success' => true];
	}

	/**
	 * Persist the per-link metric settings submitted by the frontend.
	 *
	 * Each payload.link_metrics entry looks like:
	 *   {linkid: '123'|'', new_seq: 1|0, bw_itemids: [...], in_itemids: [...], out_itemids: [...]}
	 *
	 * Existing links are matched directly by linkid; new links have no linkid and are claimed by new_seq:
	 * Zabbix inserts new links in the order of the payload.links array, with increasing auto-increment linkids,
	 * so after reading back, the n-th link (excluding existing linkids, sorted ascending) is the one with new_seq=n.
	 *
	 * @param array $raw_metrics payload.link_metrics
	 * @param array $saved_links  the submitted $update['links'] (used to tell existing links from new ones)
	 */
	private function persistLinkMetrics(string $sysmapid, array $raw_metrics, array $saved_links): void {
		$existing_ids = [];

		foreach ($saved_links as $link) {
			if (isset($link['linkid'])) {
				$existing_ids[(string) $link['linkid']] = true;
			}
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$new_ids = [];

		if ($maps && isset($maps[0]['links']) && is_array($maps[0]['links'])) {
			foreach (array_values($maps[0]['links']) as $db_link) {
				$id = (string) $db_link['linkid'];

				if (!isset($existing_ids[$id])) {
					$new_ids[] = $id;
				}
			}
		}

		sort($new_ids, SORT_NUMERIC);

		$by_link = [];

		foreach ($raw_metrics as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$linkid = '';

			if (isset($entry['linkid']) && ctype_digit((string) $entry['linkid']) && (int) $entry['linkid'] > 0) {
				$linkid = (string) $entry['linkid'];
			}
			else {
				$seq = isset($entry['new_seq']) ? (int) $entry['new_seq'] : 0;

				if ($seq > 0 && isset($new_ids[$seq - 1])) {
					$linkid = $new_ids[$seq - 1];
				}
			}

			if ($linkid === '') {
				continue;
			}

			$by_link[$linkid] = [
				'bw' => $this->parseItemIds($entry['bw_itemids'] ?? []),
				'in' => $this->parseItemIds($entry['in_itemids'] ?? []),
				'out' => $this->parseItemIds($entry['out_itemids'] ?? []),
				'source_port' => (string) ($entry['source_port'] ?? ''),
				'target_port' => (string) ($entry['target_port'] ?? ''),
				'trigger_source' => $entry['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO
			];
		}

		$this->saveLinkMetrics($sysmapid, $by_link);
	}

	/**
	 * Persist the per-link routing / waypoint settings submitted by the frontend.
	 *
	 * Each payload.link_paths entry looks like:
	 *   {linkid: '123'|'', new_seq: 1|0, routing: 0|1|2, waypoints: [{x,y}, ...]}
	 *
	 * linkid / new_seq are claimed exactly as in persistLinkMetrics():
	 * new links are numbered in payload.links order; after reading back, existing ids are excluded and the rest map in ascending order.
	 *
	 * @param array $raw_paths   payload.link_paths
	 * @param array $saved_links the submitted $update['links']
	 */
	private function persistLinkPaths(string $sysmapid, array $raw_paths, array $saved_links): void {
		$existing_ids = [];

		foreach ($saved_links as $link) {
			if (isset($link['linkid'])) {
				$existing_ids[(string) $link['linkid']] = true;
			}
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$new_ids = [];

		if ($maps && isset($maps[0]['links']) && is_array($maps[0]['links'])) {
			foreach (array_values($maps[0]['links']) as $db_link) {
				$id = (string) $db_link['linkid'];

				if (!isset($existing_ids[$id])) {
					$new_ids[] = $id;
				}
			}
		}

		sort($new_ids, SORT_NUMERIC);

		$by_link = [];

		foreach ($raw_paths as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$linkid = '';

			if (isset($entry['linkid']) && ctype_digit((string) $entry['linkid']) && (int) $entry['linkid'] > 0) {
				$linkid = (string) $entry['linkid'];
			}
			else {
				$seq = isset($entry['new_seq']) ? (int) $entry['new_seq'] : 0;

				if ($seq > 0 && isset($new_ids[$seq - 1])) {
					$linkid = $new_ids[$seq - 1];
				}
			}

			if ($linkid === '') {
				continue;
			}

			$by_link[$linkid] = [
				'routing' => max(0, min(2, (int) ($entry['routing'] ?? 0))),
				'waypoints' => $this->normalizeWaypoints($entry['waypoints'] ?? [])
			];
		}

		$this->saveLinkPaths($sysmapid, $by_link);
	}

	/**
	 * Normalize waypoints: keep only numeric {x,y} pairs, rounded and clamped to non-negative (sysmaps coordinates cannot be negative either).
	 *
	 * @param mixed $raw
	 * @return array [{x:int,y:int}, ...]
	 */
	private function normalizeWaypoints($raw): array {
		if (!is_array($raw)) {
			return [];
		}

		$points = [];

		foreach ($raw as $point) {
			if (!is_array($point) || !isset($point['x'], $point['y'])) {
				continue;
			}

			if (!is_numeric($point['x']) || !is_numeric($point['y'])) {
				continue;
			}

			$points[] = [
				'x' => max(0, (int) round((float) $point['x'])),
				'y' => max(0, (int) round((float) $point['y']))
			];
		}

		return $points;
	}

	// ------------------------------------------------------------------
	// Icon / host selection
	// ------------------------------------------------------------------

	private function modeIcons(): array {
		// imagetype 1 = IMAGE_TYPE_ICON (map icon library)
		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => 1],
			'sortfield' => 'name',
			'sortorder' => 'ASC'
		]);

		$icons = [];

		foreach ($images as $image) {
			$icons[] = ['imageid' => (string) $image['imageid'], 'name' => $image['name']];
		}

		$preferred = ['Server_(96)', 'Server_(64)', 'Server_(48)', 'Server_(32)', 'Cloud_(96)'];
		$default = $icons ? $icons[0]['imageid'] : '0';

		foreach ($preferred as $want) {
			foreach ($icons as $icon) {
				if (strcasecmp($icon['name'], $want) === 0) {
					$default = $icon['imageid'];
					break 2;
				}
			}
		}

		return ['success' => true, 'icons' => $icons, 'default' => $default];
	}

	/**
	 * View memory: remember / forget the last opened map.
	 *
	 * Natively, opening map.view writes web.maps.sysmapid and opening the list page sysmaps.php deletes it;
	 * the menu item always points to action=map.view, so clicking the menu returns to the last view.
	 * Here the list and the canvas share one action, so the deletion on "back to list" can only be triggered by the frontend.
	 *
	 * An empty (or 0) sysmapid means back to the list.
	 */
	private function modeRemember(): array {
		$sysmapid = (int) $this->getInput('sysmapid', 0);

		if ($sysmapid > 0) {
			CProfile::update(TopomapView::PROFILE_KEY, $sysmapid, PROFILE_TYPE_ID);
		}
		else {
			CProfile::delete(TopomapView::PROFILE_KEY);
		}

		return ['success' => true];
	}

	private function modeHosts(): array {
		$search = trim((string) $this->getInput('search', ''));
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));
		$groupids = $this->parseIdList((string) $this->getInput('groupids', ''));

		$options = [
			'output' => ['hostid', 'name', 'status'],
			'selectInterfaces' => ['ip', 'type', 'main'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'limit' => 200
		];

		// "Host" popup of the item selector: can echo selected hosts / filter by host group.
		// Auto discovery only passes search, not these two parameters.
		if ($hostids) {
			$options['hostids'] = $hostids;
			unset($options['limit']);
		}

		if ($groupids) {
			$options['groupids'] = $groupids;
		}

		if ($search !== '') {
			$options['search'] = ['name' => $search, 'host' => $search];
			$options['searchByAny'] = true;
		}

		$hosts = [];

		foreach (API::Host()->get($options) as $host) {
			$hosts[] = [
				'hostid' => (string) $host['hostid'],
				'name' => $host['name'],
				'ip' => $this->hostIp($host),
				'status' => (int) $host['status'],
				// Only hosts with an SNMP interface can be discovery seeds
				'snmp' => $this->snmpInterface($host) !== null
			];
		}

		return ['success' => true, 'hosts' => $hosts];
	}

	/**
	 * Data source of the "host group" popup in the item selector: lists host groups, searchable by name.
	 */
	private function modeHostgroups(): array {
		$search = trim((string) $this->getInput('search', ''));

		$options = [
			'output' => ['groupid', 'name'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'limit' => 400
		];

		if ($search !== '') {
			$options['search'] = ['name' => $search];
		}

		$groups = [];

		foreach (API::HostGroup()->get($options) as $group) {
			$groups[] = [
				'groupid' => (string) $group['groupid'],
				'name' => (string) $group['name']
			];
		}

		return ['success' => true, 'hostgroups' => $groups];
	}

	/**
	 * List for the item selector: given a set of hosts, fuzzy-match numeric items by keyword.
	 *
	 * Only value_type 0 (float) / 3 (unsigned integer) are listed: link bandwidth and up/down traffic are numeric,
	 * while switch templates often have hundreds of net.if.alias / descr text items (value_type 1) that would flood the list.
	 */
	private function modeItems(): array {
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));

		if (!$hostids) {
			throw new \Exception(LanguageManager::t('No host specified for the item query'));
		}

		$search = trim((string) $this->getInput('search', ''));

		$options = [
			'hostids' => $hostids,
			// Numeric types only (0 float / 3 unsigned): filter in the API rather than after fetching,
			// otherwise hundreds of text items on a switch fill up the limit and the real bandwidth items never show.
			'filter' => ['value_type' => [0, 3]],
			'output' => ['itemid', 'name', 'key_', 'units', 'value_type', 'lastvalue', 'lastclock', 'type', 'status'],
			'selectHosts' => ['hostid', 'name'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'limit' => 400
		];

		// A match on either name or key is enough (searchByAny=true means OR)
		if ($search !== '') {
			$options['search'] = ['name' => $search, 'key_' => $search];
			$options['searchByAny'] = true;
		}

		$items = [];

		foreach (API::Item()->get($options) as $item) {
			$items[] = [
				'itemid' => (string) $item['itemid'],
				'name' => (string) $item['name'],
				'key_' => (string) $item['key_'],
				'units' => (string) $item['units'],
				'value_type' => (int) $item['value_type'],
				'type' => (int) $item['type'],
				'status' => (int) $item['status'],
				'lastvalue' => $item['lastvalue'] === null ? null : (string) $item['lastvalue'],
				'lastclock' => (string) $item['lastclock'],
				'hostid' => (string) ($item['hosts'][0]['hostid'] ?? ''),
				'hostname' => (string) ($item['hosts'][0]['name'] ?? '')
			];
		}

		return ['success' => true, 'items' => $items];
	}

	/**
	 * List for the trigger selector: given a set of hosts (both link ends), list their triggers by keyword.
	 *
	 * Manually drawn links (e.g. a newly added PC connected to a switch) have no LLDP port information,
	 * so keyword matching cannot work and the user has to pick triggers here.
	 */
	private function modeHostTriggers(): array {
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));
		$triggerids = $this->parseIdList((string) $this->getInput('triggerids', ''));
		$groupids = $this->parseIdList((string) $this->getInput('groupids', ''));

		if (!$hostids && !$triggerids && !$groupids) {
			return ['success' => true, 'triggers' => []];
		}

		$search = trim((string) $this->getInput('search', ''));

		$options = [
			'output' => ['triggerid', 'description', 'expression', 'priority', 'status', 'value'],
			'selectHosts' => ['hostid', 'name'],
			'sortfield' => ['priority', 'description'],
			'sortorder' => 'DESC',
			'limit' => 500
		];

		// Look up by triggerid (bound chips on the canvas need names); otherwise list candidates by host / host group
		if ($triggerids) {
			$options['triggerids'] = $triggerids;
			unset($options['limit']);
		}
		else {
			// Host groups act as a filter meaning "selected hosts ∩ selected groups";
			// if only groups are selected, list the triggers of all hosts in those groups.
			if ($groupids) {
				$allowed = [];

				foreach (API::Host()->get(['output' => ['hostid'], 'groupids' => $groupids]) as $host) {
					$allowed[(string) $host['hostid']] = (string) $host['hostid'];
				}

				if ($hostids) {
					$hostids = array_values(array_intersect($hostids, $allowed));
				}
				else {
					$hostids = array_values($allowed);
				}

				if (!$hostids) {
					return ['success' => true, 'triggers' => []];
				}
			}

			$options['hostids'] = $hostids;
		}

		if ($search !== '') {
			$options['search'] = ['description' => $search];
		}

		$triggers = [];
		$raw = API::Trigger()->get($options);

		// The link indicator shows "host name: trigger name", and native maps use the expanded name;
		// expand macros once when looking up by triggerid, so {HOST.NAME}/{$CPU_UTIL_MAX} are not shown raw after a reload.
		$resolved_names = [];

		if ($triggerids && class_exists('CMacrosResolverHelper')) {
			try {
				$by_id = [];

				foreach ($raw as $trigger) {
					$by_id[(string) $trigger['triggerid']] = $trigger;
				}

				$resolved = \CMacrosResolverHelper::resolveTriggerNames($by_id);

				foreach ($resolved as $tid => $trigger) {
					$resolved_names[(string) $tid] = (string) $trigger['description'];
				}
			}
			catch (\Throwable $e) {
				$resolved_names = [];
			}
		}

		foreach ($raw as $trigger) {
			$host = $trigger['hosts'][0] ?? [];
			$triggerid = (string) $trigger['triggerid'];

			$triggers[] = [
				'triggerid' => $triggerid,
				'description' => $resolved_names[$triggerid] ?? (string) $trigger['description'],
				'priority' => (int) $trigger['priority'],
				'status' => (int) $trigger['status'],
				'value' => (int) $trigger['value'],
				'hostid' => (string) ($host['hostid'] ?? ''),
				'hostname' => (string) ($host['name'] ?? '')
			];
		}

		return ['success' => true, 'triggers' => $triggers];
	}

	/**
	 * Re-match the port status triggers of one link with the given "trigger source" scope.
	 *
	 * Lets a change of "trigger source" in the link properties dialog take effect immediately (also for manual links,
	 * as long as both ends are Zabbix hosts and the port names are available in the link settings).
	 */
	private function modeLinkTriggers(): array {
		$src_hostid = trim((string) $this->getInput('hostid1', ''));
		$tgt_hostid = trim((string) $this->getInput('hostid2', ''));
		$src_port = trim((string) $this->getInput('port1', ''));
		$tgt_port = trim((string) $this->getInput('port2', ''));
		$trigger_source = $this->normalizeTriggerSource($this->getInput('trigger_source', self::TRIGGER_SOURCE_AUTO));

		$cache = [];
		$triggerids = $this->matchLinkTriggers($src_hostid, $tgt_hostid, $src_port, $tgt_port, $cache, $trigger_source);

		$triggers = [];

		if ($triggerids) {
			foreach (API::Trigger()->get([
				'triggerids' => $triggerids,
				'output' => ['triggerid', 'description'],
				'selectHosts' => ['hostid']
			]) as $trigger) {
				$triggers[] = [
					'triggerid' => (string) $trigger['triggerid'],
					'description' => (string) $trigger['description'],
					'hostid' => (string) ($trigger['hosts'][0]['hostid'] ?? '')
				];
			}
		}

		return ['success' => true, 'triggers' => $triggers];
	}

	// ------------------------------------------------------------------
	// Per-link metric (bandwidth / upstream / downstream) settings: module table
	// ------------------------------------------------------------------

	/**
	 * Create the metrics table lazily. The module tables belong to the Zabbix frontend DB user; CREATE TABLE IF NOT EXISTS
	 * is idempotent, so it runs at most once per request, with a static flag guarding repeated calls within the same request.
	 */
	private function ensureMetricsTable(): void {
		if (self::$metricsTableReady) {
			return;
		}

		self::$metricsTableReady = true;

		// The three ITEMIDS columns store comma-separated item IDs (aggregated member ports means several);
		// source_port / target_port store the port names at both ends (drawn at the link ends and used as the trigger matching key),
		// trigger_source stores the "trigger source" scope.
		// linkid is the primary key: one settings row per link.
		\DBexecute(
			'CREATE TABLE IF NOT EXISTS '.self::LINK_METRICS_TABLE.' ('
			.'linkid bigint NOT NULL,'
			.'sysmapid bigint NOT NULL,'
			.'bw_itemids text NULL,'
			.'in_itemids text NULL,'
			.'out_itemids text NULL,'
			.'source_port varchar(255) NULL,'
			.'target_port varchar(255) NULL,'
			.'trigger_source integer NULL,'
			.'PRIMARY KEY (linkid)'
			.')'
		);

		// Tables created by 1.0.11 lack the last three columns; add them (skipped if they exist).
		foreach ([
			'source_port' => 'varchar(255) NULL',
			'target_port' => 'varchar(255) NULL',
			'trigger_source' => 'integer NULL'
		] as $column => $definition) {
			if (!$this->metricsColumnExists($column)) {
				\DBexecute('ALTER TABLE '.self::LINK_METRICS_TABLE.' ADD COLUMN '.$column.' '.$definition);
			}
		}
	}

	/** Whether a column exists in a module table (checked via information_schema, compatible with MySQL / PostgreSQL). */
	private function metricsColumnExists(string $column): bool {
		try {
			$cursor = \DBselect(
				'SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS'
				.' WHERE TABLE_NAME='.\zbx_dbstr(self::LINK_METRICS_TABLE)
				.' AND COLUMN_NAME='.\zbx_dbstr($column)
			);

			if (is_array($cursor)) {
				$row = \DBfetch($cursor);

				return $row && (int) $row['cnt'] > 0;
			}
		}
		catch (\Throwable $e) {
			// If it cannot be determined, treat it as missing and let ALTER handle it; a real problem only affects these columns
		}

		return false;
	}

	/** Create the routing / waypoint table lazily (same approach as the metrics table, at most once per request). */
	private function ensurePathsTable(): void {
		if (self::$pathsTableReady) {
			return;
		}

		self::$pathsTableReady = true;

		\DBexecute(
			'CREATE TABLE IF NOT EXISTS '.self::LINK_PATHS_TABLE.' ('
			.'linkid bigint NOT NULL,'
			.'sysmapid bigint NOT NULL,'
			.'routing integer NULL,'
			.'waypoints text NULL,'
			.'PRIMARY KEY (linkid)'
			.')'
		);
	}

	/**
	 * Read the routing / waypoint settings of all links of a map.
	 *
	 * @return array linkid => ['routing'=>int, 'waypoints'=>array]
	 */
	private function loadLinkPaths(string $sysmapid): array {
		$this->ensurePathsTable();

		$sysmapid = (int) $sysmapid;
		$cursor = \DBselect(
			'SELECT linkid, routing, waypoints'
			.' FROM '.self::LINK_PATHS_TABLE
			.' WHERE sysmapid='.$sysmapid
		);

		$result = [];

		if (!$cursor) {
			return $result;
		}

		while ($row = \DBfetch($cursor)) {
			$waypoints = json_decode((string) ($row['waypoints'] ?? ''), true);

			$result[(string) $row['linkid']] = [
				'routing' => max(0, min(2, (int) ($row['routing'] ?? 0))),
				'waypoints' => $this->normalizeWaypoints($waypoints)
			];
		}

		return $result;
	}

	/**
	 * Rebuild a map's routing / waypoint settings: delete all rows of the map, then write back the current links.
	 * "Automatic curve" is stored too: discovered links default to automatic orthogonal, and a user's change to automatic curve must be remembered,
	 * otherwise it would render as automatic orthogonal by default every time.
	 *
	 * @param array $by_link linkid => ['routing'=>int, 'waypoints'=>array]
	 */
	private function saveLinkPaths(string $sysmapid, array $by_link): void {
		$this->ensurePathsTable();

		$sysmapid = (int) $sysmapid;

		\DBexecute('DELETE FROM '.self::LINK_PATHS_TABLE.' WHERE sysmapid='.$sysmapid);

		foreach ($by_link as $linkid => $path) {
			$linkid = (int) $linkid;
			$routing = max(0, min(2, (int) ($path['routing'] ?? 0)));
			$waypoints = $this->normalizeWaypoints($path['waypoints'] ?? []);

			if ($linkid <= 0) {
				continue;
			}

			\DBexecute(
				'INSERT INTO '.self::LINK_PATHS_TABLE
				.' (linkid, sysmapid, routing, waypoints) VALUES ('
				.$linkid.', '.$sysmapid
				.', '.$routing
				.', '.\zbx_dbstr(json_encode($waypoints, self::JSON_FLAGS))
				.')'
			);
		}
	}

	/**
	 * Read the metric settings of all links of a map.
	 *
	 * @return array linkid => ['bw'=>string[], 'in'=>string[], 'out'=>string[], 'source_port'=>string, 'target_port'=>string, 'trigger_source'=>int]
	 */
	private function loadLinkMetrics(string $sysmapid): array {
		$this->ensureMetricsTable();

		$sysmapid = (int) $sysmapid;
		$cursor = \DBselect(
			'SELECT linkid, bw_itemids, in_itemids, out_itemids, source_port, target_port, trigger_source'
			.' FROM '.self::LINK_METRICS_TABLE
			.' WHERE sysmapid='.$sysmapid
		);

		$result = [];

		if (!$cursor) {
			return $result;
		}

		while ($row = \DBfetch($cursor)) {
			$result[(string) $row['linkid']] = [
				'bw' => $this->parseItemIds((string) ($row['bw_itemids'] ?? '')),
				'in' => $this->parseItemIds((string) ($row['in_itemids'] ?? '')),
				'out' => $this->parseItemIds((string) ($row['out_itemids'] ?? '')),
				'source_port' => (string) ($row['source_port'] ?? ''),
				'target_port' => (string) ($row['target_port'] ?? ''),
				'trigger_source' => (int) ($row['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO)
			];
		}

		return $result;
	}

	/**
	 * Rebuild a map's metric settings: delete all rows of the map, then write back each current link.
	 * Rows with empty metrics, empty port names and no trigger source are not stored (keeps the table clean).
	 *
	 * @param array $by_link linkid => ['bw'=>string[], 'in'=>string[], 'out'=>string[], 'source_port'=>string, 'target_port'=>string, 'trigger_source'=>int]
	 */
	private function saveLinkMetrics(string $sysmapid, array $by_link): void {
		$this->ensureMetricsTable();

		$sysmapid = (int) $sysmapid;

		\DBexecute('DELETE FROM '.self::LINK_METRICS_TABLE.' WHERE sysmapid='.$sysmapid);

		foreach ($by_link as $linkid => $metrics) {
			$linkid = (int) $linkid;
			$bw = $this->parseItemIds($metrics['bw'] ?? []);
			$in = $this->parseItemIds($metrics['in'] ?? []);
			$out = $this->parseItemIds($metrics['out'] ?? []);
			$source_port = trim((string) ($metrics['source_port'] ?? ''));
			$target_port = trim((string) ($metrics['target_port'] ?? ''));
			$trigger_source = $this->normalizeTriggerSource($metrics['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO);

			if ($linkid <= 0 || (!$bw && !$in && !$out && $source_port === '' && $target_port === ''
					&& $trigger_source === self::TRIGGER_SOURCE_AUTO)) {
				continue;
			}

			\DBexecute(
				'INSERT INTO '.self::LINK_METRICS_TABLE
				.' (linkid, sysmapid, bw_itemids, in_itemids, out_itemids, source_port, target_port, trigger_source) VALUES ('
				.$linkid.', '.$sysmapid
				.', '.\zbx_dbstr(implode(',', $bw))
				.', '.\zbx_dbstr(implode(',', $in))
				.', '.\zbx_dbstr(implode(',', $out))
				.', '.\zbx_dbstr($source_port)
				.', '.\zbx_dbstr($target_port)
				.', '.$trigger_source
				.')'
			);
		}
	}

	// ------------------------------------------------------------------
	// Module settings (key/value)
	// ------------------------------------------------------------------

	private function ensureSettingsTable(): void {
		if (self::$settingsTableReady) {
			return;
		}

		self::$settingsTableReady = true;

		\DBexecute(
			'CREATE TABLE IF NOT EXISTS '.self::SETTINGS_TABLE.' ('
			.'name varchar(64) NOT NULL,'
			.'value text NULL,'
			.'PRIMARY KEY (name)'
			.')'
		);
	}

	/**
	 * Read a keyword setting. Falls back to the default constant when empty / missing.
	 *
	 * @return string[]
	 */
	private function loadTriggerKeywords(): array {
		// Keywords are requested for every link × trigger, so the database is queried only once per request
		if (self::$triggerKeywords !== null) {
			return self::$triggerKeywords;
		}

		$this->ensureSettingsTable();

		$cursor = \DBselect(
			'SELECT value FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr('trigger_keywords')
		);
		$row = \DBfetch($cursor);

		if (!$row || trim((string) $row['value']) === '') {
			return self::$triggerKeywords = self::LINK_TRIGGER_KEYWORDS;
		}

		$list = [];

		foreach (preg_split('/\r\n|\r|\n/', (string) $row['value']) as $line) {
			$line = trim($line);

			if ($line !== '') {
				$list[] = mb_strtolower($line);
			}
		}

		return self::$triggerKeywords = ($list ?: self::LINK_TRIGGER_KEYWORDS);
	}

	/**
	 * @param string[] $keywords
	 */
	private function saveTriggerKeywords(array $keywords): void {
		$this->ensureSettingsTable();

		$clean = [];

		foreach ($keywords as $keyword) {
			$keyword = trim((string) $keyword);

			if ($keyword !== '') {
				$clean[$keyword] = $keyword;
			}
		}

		\DBexecute(
			'DELETE FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr('trigger_keywords')
		);

		if ($clean) {
			\DBexecute(
				'INSERT INTO '.self::SETTINGS_TABLE.' (name, value) VALUES ('
				.\zbx_dbstr('trigger_keywords').', '.\zbx_dbstr(implode("\n", array_values($clean))).')'
			);
		}

		self::$triggerKeywords = null;
	}

	/**
	 * Read the stored tree layout of a map (root node + direction). Returns an empty array when nothing is stored.
	 *
	 * Rediscovery must always use the same root and direction for the same map; otherwise every time the scan start
	 * changes, the root drifts and the drawn "tree" is no longer the original one. So on first generation
	 * the root and direction are written to the settings table and used from then on (heuristics only for old maps without a record).
	 *
	 * @return array ['root_pkey' => string, 'direction' => string]
	 */
	private function loadMapLayout(string $sysmapid): array {
		$this->ensureSettingsTable();

		$cursor = \DBselect(
			'SELECT value FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr(self::mapLayoutKey($sysmapid))
		);
		$row = \DBfetch($cursor);

		if (!$row) {
			return [];
		}

		$data = json_decode((string) $row['value'], true);

		return is_array($data) ? $data : [];
	}

	/**
	 * Remember the root and direction of this map (a new sysmapid means a new map; old records need no migration).
	 */
	private function saveMapLayout(string $sysmapid, string $root_pkey, string $direction): void {
		$direction = $direction === 'horizontal' ? 'horizontal' : 'vertical';

		if ($root_pkey === '') {
			return;
		}

		$this->ensureSettingsTable();

		$value = json_encode(['root_pkey' => $root_pkey, 'direction' => $direction]);

		\DBexecute(
			'DELETE FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr(self::mapLayoutKey($sysmapid))
		);
		\DBexecute(
			'INSERT INTO '.self::SETTINGS_TABLE.' (name, value) VALUES ('
			.\zbx_dbstr(self::mapLayoutKey($sysmapid)).', '.\zbx_dbstr($value).')'
		);
	}

	/**
	 * When a map is deleted, its layout record is removed too, so the settings table does not collect useless rows.
	 */
	private function dropMapLayout(string $sysmapid): void {
		$this->ensureSettingsTable();

		\DBexecute(
			'DELETE FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr(self::mapLayoutKey($sysmapid))
		);
	}

	private static function mapLayoutKey(string $sysmapid): string {
		return 'map_layout:'.$sysmapid;
	}

	private function modeSettings(): array {
		return ['success' => true, 'trigger_keywords' => $this->loadTriggerKeywords()];
	}

	/**
	 * Save settings. Keywords are sent as plain text, one per line (textarea submitted directly),
	 * or as an array (API call).
	 */
	private function modeSaveSettings(): array {
		$raw = $this->getInput('trigger_keywords', '');
		$list = is_array($raw) ? $raw : preg_split('/\r\n|\r|\n/', (string) $raw);

		$this->saveTriggerKeywords($list);

		return ['success' => true, 'trigger_keywords' => $this->loadTriggerKeywords()];
	}

	/**
	 * Right after auto discovery creates a map, store each link's end ports and "trigger source" in creation order.
	 *
	 * map.create does not return linkids, so selectLinks is read back once: Zabbix inserts in the order of the payload.links array,
	 * with increasing auto-increment linkids, so the n-th result (by linkid ascending) is the n-th metadata entry.
	 *
	 * @param array $link_meta ['source_port'=>, 'target_port'=>, 'trigger_source'=>] in the same order as the links array at creation
	 */
	private function persistDiscoveredLinks(string $sysmapid, array $link_meta): void {
		if (!$link_meta) {
			return;
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$db_links = ($maps && isset($maps[0]['links']) && is_array($maps[0]['links']))
			? array_values($maps[0]['links'])
			: [];

		$by_link = [];

		foreach ($link_meta as $index => $meta) {
			if (!isset($db_links[$index])) {
				break;
			}

			$linkid = (string) ($db_links[$index]['linkid'] ?? '');

			if ($linkid === '' || !ctype_digit($linkid)) {
				continue;
			}

			$by_link[$linkid] = [
				'bw' => [],
				'in' => [],
				'out' => [],
				'source_port' => $meta['source_port'],
				'target_port' => $meta['target_port'],
				'trigger_source' => $meta['trigger_source']
			];
		}

		$this->saveLinkMetrics($sysmapid, $by_link);
	}

	/** Coerce any input into a valid "trigger source" value (0–4); invalid values become AUTO. */
	private function normalizeTriggerSource($raw): int {
		$value = (int) $raw;

		return ($value >= self::TRIGGER_SOURCE_AUTO && $value <= self::TRIGGER_SOURCE_NONE)
			? $value
			: self::TRIGGER_SOURCE_AUTO;
	}

	/**
	 * Normalize an item ID list: keep digits only, deduplicate, preserve order.
	 *
	 * @param string[]|string $raw
	 *
	 * @return string[]
	 */
	private function parseItemIds($raw): array {
		if (is_string($raw)) {
			$raw = explode(',', $raw);
		}

		if (!is_array($raw)) {
			return [];
		}

		$out = [];

		foreach ($raw as $itemid) {
			$itemid = trim((string) $itemid);

			if ($itemid !== '' && ctype_digit($itemid) && !isset($out[$itemid])) {
				$out[$itemid] = $itemid;
			}
		}

		return array_values($out);
	}

	/**
	 * Fetch metadata + latest values of a set of items (for link metric display).
	 *
	 * @param string[] $itemids
	 *
	 * @return array itemid => metadata (including lastvalue)
	 */
	private function fetchMetricItems(array $itemids): array {
		if (!$itemids) {
			return [];
		}

		$rows = API::Item()->get([
			'itemids' => $itemids,
			'output' => ['itemid', 'name', 'key_', 'units', 'value_type', 'lastvalue', 'lastclock'],
			'selectHosts' => ['hostid', 'name'],
			'preservekeys' => true
		]);

		$result = [];

		foreach ($rows as $itemid => $item) {
			$result[(string) $itemid] = [
				'itemid' => (string) $item['itemid'],
				'name' => (string) $item['name'],
				'key_' => (string) $item['key_'],
				'units' => (string) $item['units'],
				'value_type' => (int) $item['value_type'],
				'lastvalue' => $item['lastvalue'] === null ? null : (string) $item['lastvalue'],
				'lastclock' => (string) $item['lastclock'],
				'hostname' => (string) ($item['hosts'][0]['name'] ?? '')
			];
		}

		return $result;
	}

	/**
	 * Auto discovery step 1: scan the SNMP neighbor tables (LLDP / CDP) of a set of hosts.
	 *
	 * The frontend calls this in BFS batches: at most 8 hosts per call (the frontend actually uses batches of 3), default per-host timeout 1.2 s.
	 * An unreachable device is retried twice (about 10 s), so there is also a total time budget per batch;
	 * hosts over budget are skipped, so nginx's fastcgi_read_timeout is not exceeded and turned into a 502.
	 * Returned neighbors are already matched against Zabbix hosts.
	 */
	private function modeDiscover(): array {
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));

		if (!$hostids) {
			throw new \Exception(LanguageManager::t('Discovery: no hosts specified to scan'));
		}

		$hostids = array_slice($hostids, 0, 8);

		$protocols = $this->parseProtocols((string) $this->getInput('protocols', ''));
		$timeout = max(300, min(5000, (int) $this->getInput('timeout', '800')));

		$hosts = API::Host()->get([
			'hostids' => $hostids,
			'output' => ['hostid', 'name', 'host'],
			'selectInterfaces' => ['ip', 'type', 'main', 'port', 'details'],
			'preservekeys' => true
		]);

		if (!$hosts) {
			throw new \Exception(LanguageManager::t('Discovery: the specified hosts do not exist or you have no permission'));
		}

		$macros = $this->hostMacros($hostids);
		$index = $this->discoveryIndex();

		// The class lives in the module's own namespace and needs its fully qualified name: a bare class name resolves to the current namespace.
		$service = new \Modules\Topomap\Services\LldpDiscovery($timeout);

		$result = [];

		// Per-batch time budget: an unreachable device costs about 10 s (2 retries × two protocols), so 8 hosts would exceed the 60 s
		// fastcgi_read_timeout (the whole discovery request fails with 502). Hosts over budget are skipped,
		// the frontend records them as "not scanned" and the user can retry later.
		$deadline = microtime(true) + 20.0;

		foreach ($hostids as $hostid) {
			if (!isset($hosts[$hostid])) {
				$result[] = [
					'hostid' => (string) $hostid,
					'ok' => false,
					'error' => LanguageManager::t('Host does not exist or you have no permission'),
					'neighbors' => []
				];

				continue;
			}

			$host = $hosts[$hostid];

			if (microtime(true) > $deadline) {
				$result[] = [
					'hostid' => (string) $hostid,
					'name' => $host['name'],
					'ok' => false,
					'error' => LanguageManager::t('This batch exceeded the time budget and was skipped (please retry later)'),
					'neighbors' => []
				];

				continue;
			}

			$iface = $this->snmpInterface($host);

			if (!$iface) {
				$result[] = [
					'hostid' => (string) $hostid,
					'name' => $host['name'],
					'ok' => false,
					'error' => LanguageManager::t('No SNMP interface'),
					'neighbors' => []
				];

				continue;
			}

			$details = isset($iface['details']) && is_array($iface['details']) ? $iface['details'] : [];
			$version = isset($details['version']) ? (int) $details['version'] : 2;

			$community = $this->resolveMacros((string) ($details['community'] ?? 'public'), $macros[$hostid] ?? []);

			if ($version !== 3 && (trim($community) === '' || strpos($community, '{$') !== false)) {
				$result[] = [
					'hostid' => (string) $hostid,
					'name' => $host['name'],
					'ip' => (string) $iface['ip'],
					'ok' => false,
					'error' => LanguageManager::t('SNMP community is not defined (check the {$SNMP_COMMUNITY} macro)'),
					'neighbors' => []
				];

				continue;
			}

			$scan = $service->scanHost([
				'snmp_ip' => (string) $iface['ip'],
				'snmp_version' => $version,
				'community' => $community,
				'snmp_v3' => [
					'sec_name' => $this->resolveMacros((string) ($details['securityname'] ?? ''), $macros[$hostid] ?? []),
					'sec_level' => (string) ($details['securitylevel'] ?? 'authNoPriv'),
					'auth_protocol' => strtoupper((string) ($details['authprotocol'] ?? 'SHA')),
					'auth_passphrase' => $this->resolveMacros((string) ($details['authpassphrase'] ?? ''), $macros[$hostid] ?? []),
					'priv_protocol' => strtoupper((string) ($details['privprotocol'] ?? 'AES')),
					'priv_passphrase' => $this->resolveMacros((string) ($details['privpassphrase'] ?? ''), $macros[$hostid] ?? [])
				],
				'protocols' => $protocols
			]);

			$neighbors = [];
			$self_ips = [];

			foreach ($host['interfaces'] ?? [] as $host_iface) {
				if (!empty($host_iface['ip'])) {
					$self_ips[] = (string) $host_iface['ip'];
				}
			}

			// If, among the neighbors reported by one device, an address appears on neighbors with different device names, it is not a neighbor address
			// but the local management address echoed into lldpRemManAddr of every port (common on H3C / Huawei).
			// An IP identifies a device; one address cannot belong to two differently named devices at once, so such addresses are invalid.
			$addr_names = [];

			foreach ($scan['neighbors'] as $neighbor) {
				$ip = trim((string) $neighbor['ip']);

				if ($ip === '' || in_array($ip, $self_ips, true)) {
					continue;
				}

				$name = mb_strtolower(trim((string) $neighbor['sysname']));

				if ($name !== '') {
					$addr_names[$ip][$name] = true;
				}
			}

			$bad_ips = [];

			foreach ($addr_names as $ip => $names) {
				if (count($names) > 1) {
					$bad_ips[$ip] = true;
				}
			}

			foreach ($scan['neighbors'] as $neighbor) {
				$sysname = trim((string) $neighbor['sysname']);
				$chassis = trim((string) ($neighbor['chassis'] ?? ''));

				// Echoed local addresses and addresses shared by several device names cannot be used as neighbor addresses
				$neighbor_ip = trim((string) $neighbor['ip']);

				if (in_array($neighbor_ip, $self_ips, true) || isset($bad_ips[$neighbor_ip])) {
					$neighbor_ip = '';
				}

				$neighbor['ip'] = $neighbor_ip;
				$match = $this->matchNeighbor($neighbor, $index, $self_ips);

				$matched = $match['hostid'] !== '';

				// Unmanaged neighbors need a name: sysName first, then chassisId (endpoints / APs / printers
				// often only report a MAC and an empty sysName), and only then the reported management IP.
				// Dropped only when all three are missing — same as aiops' name || chassisId logic.
				$display = $sysname !== '' ? $sysname : $chassis;

				if ($display === '') {
					$display = $neighbor_ip;
				}

				if (!$matched && $display === '') {
					continue;
				}

				// If the remote port is a MAC, the other side is an endpoint NIC (PC / printer / AP), not a switch port,
				// matching aiops' is_distribution check, so the Workstation icon is chosen.
				// Note: the Windows sysDescr makes guessKind classify it as server; without server / printer / camera
				// keywords in the name it is treated as a PC (aiops also only looks at the name).
				$kind = $this->guessKind((string) $neighbor['sysdesc'], $display);

				if (!empty($neighbor['port_mac'])
						&& in_array($kind, ['switch', 'server', 'ap'], true)
						&& !preg_match('/server|服务|printer|打印|camera|摄像/i', $display)) {
					$kind = 'pc';
				}

				$neighbors[] = [
					'protocol' => $neighbor['protocol'],
					'sysname' => $sysname,
					'chassis' => $chassis,
					// When a host matches, use the host's management address in Zabbix; addresses reported by LLDP are often inaccurate
					'ip' => $matched ? (string) $match['ip'] : $neighbor_ip,
					'kind' => $kind,
					'local_port' => $neighbor['local_port'],
					'port_id' => $neighbor['port_id'],
					// When a Zabbix host matches, include its hostid so the frontend creates a host element instead of an image element
					'hostid' => $match['hostid'],
					'name' => $matched ? $match['name'] : $display,
					'matched' => $match['how']
				];
			}

			$result[] = [
				'hostid' => (string) $hostid,
				'name' => $host['name'],
				'ip' => (string) $iface['ip'],
				'sysname' => $scan['sysname'],
				'ok' => $scan['ok'],
				'error' => $scan['error'],
				'neighbors' => $neighbors
			];
		}

		return ['success' => true, 'hosts' => $result];
	}

	/**
	 * Arrange devices / links into a spanning tree (links treated as undirected, BFS defines parent and child).
	 *
	 * On rediscovery the edge direction in the frontend payload changes with the scan start (the same pair may be A→B this time
	 * and B→A next time); taking the root from edge direction would make it drift, so an undirected graph + a given root
	 * is used to build the tree, falling back to the node with in-degree 0 / most connections when the root is not found.
	 *
	 * @param array  $order node keys in payload order (determines a stable order for adjacency and the fallback root)
	 * @param array  $links payload.links
	 * @param string $root_key the given root (chosen automatically when empty)
	 *
	 * @return array ['children' => [key => [key, ...]], 'parent' => [key => key], 'roots' => [key, ...]]
	 */
	private function treeStructure(array $order, array $links, string $root_key = ''): array {
		$rank = [];
		$adj = [];
		$indegree = [];

		foreach ($order as $index => $key) {
			$rank[$key] = $index;
			$adj[$key] = [];
		}

		foreach ($links as $link) {
			if (!is_array($link)) {
				continue;
			}

			$source = (string) ($link['source'] ?? '');
			$target = (string) ($link['target'] ?? '');

			if ($source === '' || $target === '' || $source === $target
					|| !isset($rank[$source]) || !isset($rank[$target])) {
				continue;
			}

			$adj[$source][$target] = true;
			$adj[$target][$source] = true;
			$indegree[$target] = ($indegree[$target] ?? 0) + 1;
		}

		// The adjacency list follows payload order, so the same data always yields the same tree
		foreach ($adj as $key => $set) {
			$neighbors = array_keys($set);
			usort($neighbors, function (string $a, string $b) use ($rank): int {
				return $rank[$a] <=> $rank[$b];
			});
			$adj[$key] = $neighbors;
		}

		if ($root_key === '' || !isset($rank[$root_key])) {
			$root_key = $this->pickTreeRoot($order, $adj, $indegree);
		}

		$children = [];
		$parent = [];
		$roots = [];
		$visited = [];

		$walk = function (string $start) use (&$children, &$parent, &$roots, &$visited, $adj): void {
			$visited[$start] = true;
			$roots[] = $start;
			$queue = [$start];

			while ($queue) {
				$key = array_shift($queue);

				foreach ($adj[$key] ?? [] as $neighbor) {
					if (isset($visited[$neighbor])) {
						continue;
					}

					$visited[$neighbor] = true;
					$parent[$neighbor] = $key;
					$children[$key][] = $neighbor;
					$queue[] = $neighbor;
				}
			}
		};

		if ($root_key !== '') {
			$walk($root_key);
		}

		// Independent components not connected to the main tree become their own trees, placed beside the main tree (no node is dropped)
		foreach ($order as $key) {
			if (!isset($visited[$key])) {
				$walk($key);
			}
		}

		return ['children' => $children, 'parent' => $parent, 'roots' => $roots];
	}

	/**
	 * Automatic root choice: the most connected node among sources without incoming edges, otherwise the most connected node overall.
	 * Falling back to the connection count avoids making an isolated node without neighbors the root of the whole map.
	 */
	private function pickTreeRoot(array $order, array $adj, array $indegree): string {
		$best_source = '';
		$best_source_degree = -1;
		$best = '';
		$best_degree = -1;

		foreach ($order as $key) {
			$degree = count($adj[$key] ?? []);

			if ($degree > $best_degree) {
				$best = $key;
				$best_degree = $degree;
			}

			if (($indegree[$key] ?? 0) === 0 && $degree > $best_source_degree) {
				$best_source = $key;
				$best_source_degree = $degree;
			}
		}

		return $best_source !== '' ? $best_source : $best;
	}

	/**
	 * Root key of the spanning tree for a given payload (used when generating a new map, so the stored root
	 * matches the root actually used by treeLayout()).
	 */
	private function treeRootKey(array $nodes, array $links): string {
		$order = [];
		$seen = [];

		foreach ($nodes as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];

			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$order[] = $key;
			}
		}

		if (!$order) {
			return '';
		}

		$structure = $this->treeStructure($order, $links, '');

		return (string) ($structure['roots'][0] ?? '');
	}

	/**
	 * Tree layout for auto discovery (aligned with aiops' tidy tree):
	 *   - the given root is at the top (leftmost when direction=horizontal);
	 *   - children are spread along the cross axis, parents centered over their children;
	 *   - the main axis increases level by level with tree depth (vertical = y downwards, horizontal = x to the right).
	 *
	 * @param array  $nodes payload.nodes
	 * @param array  $links payload.links
	 * @param string $root_key the given root node (rediscovery passes the old map's root so it does not drift)
	 * @param string $direction vertical / horizontal, determined by the old map's direction
	 *
	 * @return array key => ['x' => int, 'y' => int] (x/y is the top-left of the icon, centered for the default width of 96)
	 */
	private function treeLayout(array $nodes, array $links, string $root_key = '', string $direction = 'vertical'): array {
		$order = [];
		$seen = [];

		foreach ($nodes as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];

			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$order[] = $key;
			}
		}

		if (!$order) {
			return [];
		}

		$structure = $this->treeStructure($order, $links, $root_key);
		$horizontal = $direction === 'horizontal';
		// Leaf slot width: a vertical tree spreads along x (96 icon + label + gap); a horizontal tree spreads along y,
		// icon 96 + name/status below about 60; 170 keeps it from overlapping the text of the previous device.
		$cross_gap = $horizontal ? 170 : 190;
		// Level spacing: y downwards for vertical, x to the right for horizontal; both leave room for trunk/bandwidth boxes.
		$level_gap = 300;
		$start = 80;
		$cursor = $start;
		$pos = [];
		$placed = [];

		$place = function (string $key, int $depth) use (&$place, &$cursor, &$pos, &$placed, $structure,
				$cross_gap, $level_gap, $start, $horizontal) {
			if (isset($placed[$key])) {
				return isset($pos[$key]) ? ($horizontal ? $pos[$key]['y'] : $pos[$key]['x']) + 48 : $cursor;
			}

			$placed[$key] = true;
			$kids = $structure['children'][$key] ?? [];

			if (!$kids) {
				$center = $cursor;
				$cursor += $cross_gap;
			}
			else {
				$centers = [];

				foreach ($kids as $kid) {
					$centers[] = $place($kid, $depth + 1);
				}

				$center = (min($centers) + max($centers)) / 2;
			}

			if ($horizontal) {
				$pos[$key] = ['x' => $start + $depth * $level_gap, 'y' => (int) round($center - 48)];
			}
			else {
				$pos[$key] = ['x' => (int) round($center - 48), 'y' => $start + $depth * $level_gap];
			}

			return $center;
		};

		foreach ($structure['roots'] as $index => $root) {
			if ($index > 0) {
				$cursor += 100;
			}

			$place($root, 0);
		}

		// Fallback: isolated nodes not covered by any link are placed at the right end of the same row
		foreach ($order as $key) {
			if (!isset($pos[$key])) {
				$pos[$key] = $horizontal
					? ['x' => $start, 'y' => $cursor]
					: ['x' => $cursor, 'y' => $start];
				$cursor += $cross_gap;
			}
		}

		return $pos;
	}

	/**
	 * Read the "root node" and the "tree direction" from the old map.
	 *
	 * Prefer the layout metadata stored at generation / last relayout (root_pkey + direction), so the same
	 * map returns to the same root and direction on rediscovery whichever device the scan starts from.
	 * Old maps without metadata are inferred from coordinates (see inferTreeLayout).
	 *
	 * @param string $sysmapid     current map
	 * @param array  $old_selements old elements returned by map.get
	 * @param array  $old_links     old links
	 * @param array  $payload_pkeys element match keys present in this payload (the root is chosen only among them)
	 *
	 * @return array ['direction' => vertical|horizontal, 'root_pkey' => string]
	 */
	private function detectTreeLayout(string $sysmapid, array $old_selements, array $old_links,
			array $payload_pkeys): array {
		$stored = $this->loadMapLayout($sysmapid);
		$direction = (string) ($stored['direction'] ?? '');

		if (!in_array($direction, ['vertical', 'horizontal'], true)) {
			$direction = '';
		}

		$root_pkey = (string) ($stored['root_pkey'] ?? '');

		// The stored root was not scanned this time (device offline / renamed) ⇒ only the direction is usable; the root is chosen again from coordinates
		if ($root_pkey !== '' && $payload_pkeys && !isset($payload_pkeys[$root_pkey])) {
			$root_pkey = '';
		}

		if ($direction !== '' && $root_pkey !== '') {
			return ['direction' => $direction, 'root_pkey' => $root_pkey];
		}

		$inferred = $this->inferTreeLayout($old_selements, $old_links, $payload_pkeys, $direction);

		return [
			'direction' => $direction !== '' ? $direction : $inferred['direction'],
			'root_pkey' => $root_pkey !== '' ? $root_pkey : $inferred['root_pkey']
		];
	}

	/**
	 * When an old map has no layout metadata, infer root and direction from coordinates.
	 *
	 * Direction: the tree is a tidy tree, so elements of one level are aligned on the main axis; coordinate clusters on the main axis
	 * are far fewer than on the cross axis, and the axis with fewer clusters is the level direction (fewer y = top-down; fewer x = left
	 * to right). Old link directions alone are not enough: once an old map was polluted by a wrong layout, diagonal links dominate
	 * and would skew the direction.
	 *
	 * Root: in the frontmost level on the main axis, the element closest to the map center on the cross axis (a tree root should be centered over all
	 * descendants), ties broken by connection count; elements with links are preferred, and if all are isolated the frontmost one is used.
	 *
	 * @return array ['direction' => string, 'root_pkey' => string]
	 */
	private function inferTreeLayout(array $old_selements, array $old_links, array $payload_pkeys,
			string $direction = ''): array {
		$points = [];
		$degree = [];
		$sum_dx = 0;
		$sum_dy = 0;

		foreach ($old_selements as $se) {
			$selementid = (string) ($se['selementid'] ?? '');

			if ($selementid !== '') {
				$points[$selementid] = [
					'x' => (int) ($se['x'] ?? 0),
					'y' => (int) ($se['y'] ?? 0)
				];
			}
		}

		foreach ($old_links as $link) {
			$id1 = (string) ($link['selementid1'] ?? '');
			$id2 = (string) ($link['selementid2'] ?? '');

			if ($id1 === '' || $id2 === '' || !isset($points[$id1]) || !isset($points[$id2])) {
				continue;
			}

			$sum_dx += abs($points[$id1]['x'] - $points[$id2]['x']);
			$sum_dy += abs($points[$id1]['y'] - $points[$id2]['y']);
			$degree[$id1] = ($degree[$id1] ?? 0) + 1;
			$degree[$id2] = ($degree[$id2] ?? 0) + 1;
		}

		$candidates = [];

		foreach ($old_selements as $se) {
			$selementid = (string) ($se['selementid'] ?? '');
			$pkey = $this->elementPKey($se);

			if ($selementid === '' || $pkey === '' || !isset($points[$selementid])) {
				continue;
			}

			if ($payload_pkeys && !isset($payload_pkeys[$pkey])) {
				continue;
			}

			$candidates[] = [
				'pkey' => $pkey,
				'x' => $points[$selementid]['x'],
				'y' => $points[$selementid]['y'],
				'degree' => $degree[$selementid] ?? 0
			];
		}

		if (!$candidates) {
			return ['direction' => $direction !== '' ? $direction : 'vertical', 'root_pkey' => ''];
		}

		if ($direction === '') {
			$direction = $this->inferTreeDirection($candidates, $sum_dx, $sum_dy);
		}

		$vertical = $direction !== 'horizontal';
		// Only elements with links can be a tree root; isolated old elements (not referenced by any link) are excluded
		$linked = array_values(array_filter($candidates, function (array $c): bool {
			return $c['degree'] > 0;
		}));

		if (!$linked) {
			$linked = $candidates;
		}

		$min_main = null;
		$cross_values = [];

		foreach ($linked as $c) {
			$main = $vertical ? $c['y'] : $c['x'];

			if ($min_main === null || $main < $min_main) {
				$min_main = $main;
			}
		}

		foreach ($candidates as $c) {
			// Compare icon centers (top-left +48); a root centered over the tree is closest to the map center
			$cross_values[] = ($vertical ? $c['x'] : $c['y']) + 48;
		}

		sort($cross_values, SORT_NUMERIC);
		$center = $cross_values[(int) floor(count($cross_values) / 2)];

		$best_pkey = '';
		$best_dist = PHP_INT_MAX;
		$best_degree = -1;

		foreach ($linked as $c) {
			$main = $vertical ? $c['y'] : $c['x'];

			// Frontmost level: within one level spacing of the minimum
			if ($main - $min_main > 150) {
				continue;
			}

			$dist = abs((($vertical ? $c['x'] : $c['y']) + 48) - $center);

			if ($dist < $best_dist || ($dist === $best_dist && $c['degree'] > $best_degree)) {
				$best_pkey = $c['pkey'];
				$best_dist = $dist;
				$best_degree = $c['degree'];
			}
		}

		return ['direction' => $direction, 'root_pkey' => $best_pkey];
	}

	/**
	 * Infer the level direction of the tree from coordinate cluster counts.
	 *
	 * In a tidy tree, elements of one level are aligned on the main axis (clusters ≈ levels), while on the cross axis each element has its own position
	 * (clusters ≈ nodes). If the difference is not clear, fall back to the overall direction of old links, and otherwise assume a vertical tree
	 * (maps generated by the module are top-down by default).
	 */
	private function inferTreeDirection(array $candidates, int $sum_dx, int $sum_dy): string {
		$xs = [];
		$ys = [];

		foreach ($candidates as $c) {
			$xs[] = $c['x'];
			$ys[] = $c['y'];
		}

		$yclusters = $this->countCoordinateClusters($ys);
		$xclusters = $this->countCoordinateClusters($xs);

		if ($yclusters * 3 <= $xclusters * 2) {
			return 'vertical';
		}

		if ($xclusters * 3 <= $yclusters * 2) {
			return 'horizontal';
		}

		return $sum_dy >= $sum_dx ? 'vertical' : 'horizontal';
	}

	/**
	 * Count how many clusters the coordinates form (a gap larger than one icon width starts a new level).
	 */
	private function countCoordinateClusters(array $values): int {
		if (!$values) {
			return 0;
		}

		sort($values, SORT_NUMERIC);

		$clusters = 1;
		$anchor = $values[0];

		foreach ($values as $value) {
			if ($value - $anchor > 100) {
				$clusters++;
				$anchor = $value;
			}
		}

		return $clusters;
	}

	/**
	 * Incremental placement of new elements when "keep current positions" is chosen:
	 *   - old elements never move;
	 *   - new elements hang below (vertical tree) / to the right of (horizontal tree) their parent in the spanning tree,
	 *     several new devices under one parent are spread along the cross axis, avoiding occupied positions;
	 *   - independent new components not connected to the existing tree are placed together one level beyond the existing map, not merged into the main tree.
	 *
	 * @param array  $nodes         payload.nodes
	 * @param array  $structure     result of treeStructure()
	 * @param array  $old_selements old elements (their coordinates are occupied)
	 * @param string $direction     vertical / horizontal
	 *
	 * @return array key => ['x' => int, 'y' => int]
	 */
	private function keepLayoutPositions(array $nodes, array $structure, array $old_selements, string $direction): array {
		$vertical = $direction !== 'horizontal';
		$level_gap = 300;
		$cross_gap = $vertical ? 190 : 170;

		$old_pos = [];
		$occupied = [];

		foreach ($old_selements as $se) {
			$pkey = $this->elementPKey($se);

			if ($pkey === '' || isset($old_pos[$pkey])) {
				continue;
			}

			$point = ['x' => (int) ($se['x'] ?? 0), 'y' => (int) ($se['y'] ?? 0)];
			$old_pos[$pkey] = $point;
			$occupied[] = $point;
		}

		$pkey_of = [];

		foreach ($nodes as $node) {
			if (is_array($node) && isset($node['key'])) {
				$pkey_of[(string) $node['key']] = $this->nodePKey($node);
			}
		}

		// Position of old elements in the tree: those matching a pkey keep their coordinates
		$placed = [];

		foreach ($pkey_of as $key => $pkey) {
			if ($pkey !== '' && isset($old_pos[$pkey])) {
				$placed[$key] = $old_pos[$pkey];
			}
		}

		$max_main = 80;
		$max_cross = 80;

		foreach ($occupied as $point) {
			$max_main = max($max_main, $vertical ? $point['y'] : $point['x']);
			$max_cross = max($max_cross, $vertical ? $point['x'] : $point['y']);
		}

		// Independent new components (not connected to the existing map) start a new row below / to the right of the existing map,
		// instead of sinking level by level along the main direction, which would lay several trees out diagonally
		$annex_main = $max_main + $level_gap;
		$annex_cross = 40;

		$free = function (int $x, int $y) use (&$occupied): bool {
			foreach ($occupied as $point) {
				if (abs($x - $point['x']) < 150 && abs($y - $point['y']) < 150) {
					return false;
				}
			}

			return true;
		};

		$queue = [];
		$results = [];

		foreach ($structure['roots'] as $root) {
			$queue[] = $root;
		}

		while ($queue) {
			$key = array_shift($queue);

			foreach ($structure['children'][$key] ?? [] as $kid) {
				$queue[] = $kid;
			}

			if (isset($placed[$key])) {
				continue;
			}

			$parent_key = (string) ($structure['parent'][$key] ?? '');
			$point = null;

			if ($parent_key !== '' && isset($placed[$parent_key])) {
				$parent = $placed[$parent_key];
				$parent_cross = ($vertical ? $parent['x'] : $parent['y']) + 48;
				$parent_main = $vertical ? $parent['y'] : $parent['x'];

				// Start right below / right of the parent and step aside alternately: 0, -1, +1, -2, +2 …
				// Large fan-outs (dozens of new devices under one switch) need enough steps, otherwise no free slot is found and they land outside the map
				for ($step = 0; $step < 40 && $point === null; $step++) {
					$offset = (int) (ceil($step / 2) * $cross_gap) * ($step % 2 === 0 ? 1 : -1);
					$cross = $parent_cross + $offset - 48;
					$main = $parent_main + $level_gap;
					$x = $vertical ? $cross : $main;
					$y = $vertical ? $main : $cross;

					if ($cross >= 20 && $free($x, $y)) {
						$point = ['x' => $x, 'y' => $y];
					}
				}
			}

			if ($point === null) {
				// New components (or parents not in this discovery): placed beyond the existing map along the main direction
				for ($step = 0; $step < 40; $step++) {
					$x = $vertical ? $annex_cross : $annex_main;
					$y = $vertical ? $annex_main : $annex_cross;

					if ($free($x, $y)) {
						$point = ['x' => $x, 'y' => $y];
						break;
					}

					$annex_cross += $cross_gap;
				}
			}

			if ($point === null) {
				$point = ['x' => $vertical ? 80 : $annex_main, 'y' => $vertical ? $annex_main : 80];
			}

			$placed[$key] = $point;
			$results[$key] = $point;
			$occupied[] = $point;
			$max_main = max($max_main, $vertical ? $point['y'] : $point['x']);
			$max_cross = max($max_cross, $vertical ? $point['x'] : $point['y']);
		}

		return $results;
	}

	/**
	 * Auto discovery step 2: write the nodes/links computed by the frontend into a new map.
	 *
	 * The layout is computed here with aiops' tidy tree: root at the top,
	 * children side by side with parents centered above them, level by level downwards.
	 */
	private function modeDiscoverSave(): array {
		$raw = (string) $this->getInput('payload', '');
		$payload = json_decode($raw, true);

		if (!is_array($payload) || empty($payload['nodes']) || !is_array($payload['nodes'])) {
			throw new \Exception(LanguageManager::t('Discovery: nothing to generate'));
		}

		// Keywords for automatic trigger linking: the ones entered on the frontend "auto discovery" page (one per line) take precedence,
		// and are saved along the way, so re-matching after changing "trigger source" in link properties uses the same set.
		if (!empty($payload['keywords']) && is_array($payload['keywords'])) {
			$this->saveTriggerKeywords($payload['keywords']);
		}

		$name = trim((string) $this->getInput('name', ''));

		if ($name === '') {
			$name = LanguageManager::t('Auto discovery').' '.date('Y-m-d H:i');
		}

		$name = $this->uniqueMapName($this->normalizeName($name));

		$selements = [];
		$host_of_node = [];
		$payload_links = isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : [];
		// The tree root is fixed at generation time and stored in the settings table; later rediscoveries always reuse it
		$root_key = $this->treeRootKey($payload['nodes'], $payload_links);
		$positions = $this->treeLayout($payload['nodes'], $payload_links, $root_key);
		$root_pkey = '';

		if ($root_key !== '') {
			foreach ($payload['nodes'] as $node) {
				if (is_array($node) && isset($node['key']) && (string) $node['key'] === $root_key) {
					$root_pkey = $this->nodePKey($node);
					break;
				}
			}
		}

		foreach ($payload['nodes'] as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];
			$p = $positions[$key] ?? ['x' => 80, 'y' => 80];
			$x = (int) $p['x'];
			$y = (int) $p['y'];

			$label = (string) ($node['name'] ?? '');

			if (!empty($node['ip'])) {
				$label = $label."\n".(string) $node['ip'];
			}

			$hostid = isset($node['hostid']) ? trim((string) $node['hostid']) : '';

			// Default label of host elements: name on one line, connection address on another. Raw macros are stored
			// and expanded by the renderer from the host's current values, so renamed hosts are reflected without rediscovery.
			if ($hostid !== '' && ctype_digit($hostid) && $hostid !== '0') {
				$label = self::DEFAULT_HOST_LABEL;
			}

			$selementid = (string) (count($selements) + 1);
			// Choose the icon by device type: switch Switch_(128), endpoint Workstation_(128) (aligned with aiops)
			$iconid = $this->iconForKind((string) ($node['kind'] ?? ''));

			// Trigger matching needs the Zabbix host of the node (unmanaged neighbors have no hostid and can only match via the other end)
			$host_of_node[$key] = ctype_digit($hostid) && $hostid !== '0' ? $hostid : '';

			if ($hostid !== '' && ctype_digit($hostid) && $hostid !== '0') {
				// Managed: host element; the preview shows status lines and clicking opens the host
				$selement = [
					'selementid' => $selementid,
					'elementtype' => 0,
					'elements' => [['hostid' => $hostid]],
					'label' => $label,
					'label_location' => 0,
					'x' => $x,
					'y' => $y,
					'iconid_off' => $iconid,
					'use_iconmap' => 0,
					'evaltype' => 0
				];
			}
			else {
				// Unmanaged (no host created for the remote device): image element showing only name and IP
				$selement = [
					'selementid' => $selementid,
					'elementtype' => 4,
					'label' => $label,
					'label_location' => 0,
					'x' => $x,
					'y' => $y,
					'iconid_off' => $iconid
				];
			}

			$selements[$key] = $selement;
		}

		if (!$selements) {
			throw new \Exception(LanguageManager::t('Discovery: no devices to generate'));
		}

		$links = [];
		$pairs = [];
		// Directed device pairs already created: the same physical link scanned in reverse is dropped entirely (as aiops does).
		// Deduplicating by port pair alone is not enough: when a device is rate-limited, the local port names at both ends may resolve differently,
		// so the port pairs do not merge, an extra duplicate link appears and down triggers of unrelated remote ports get bound.
		$directed = [];
		// Used to claim linkids after reading back: record each link's end ports and "trigger source" in creation order
		$link_meta = [];
		// Each host's trigger list is queried only once (the same switch appears on many links)
		$trigger_cache = [];
		$trigger_total = 0;

		foreach ((isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : []) as $link) {
			if (!is_array($link) || !isset($link['source'], $link['target'])) {
				continue;
			}

			$source = (string) $link['source'];
			$target = (string) $link['target'];

			if (!isset($selements[$source], $selements[$target]) || $source === $target) {
				continue;
			}

			// Reverse duplicates (B→A when A→B already exists) are always skipped; see the comment on $directed
			if (isset($directed[$target.'|'.$source])) {
				continue;
			}

			// There may be several links between the same pair of devices (stacking / multiple members of a link aggregation),
			// so the dedup key includes the ports at both ends: aggregated member links are kept,
			// while links reported twice in the same direction are merged into one.
			// Without ports it falls back to the device pair, as before.
			// Port names are normalized before comparison: the core switch reports raw Comware numbers (already restored by LldpDiscovery
			// to GigabitEthernet1/0/11) while the other end reports Gi1/0/11; both normalize to ge1011,
			// so duplicates merge while the two LAG member ports each keep their own link.
			$end_a = $source.'#'.$this->ifPortKey((string) ($link['source_port'] ?? ''));
			$end_b = $target.'#'.$this->ifPortKey((string) ($link['target_port'] ?? ''));
			$pair = $end_a < $end_b ? $end_a.'|'.$end_b : $end_b.'|'.$end_a;

			if (isset($pairs[$pair])) {
				continue;
			}

			$pairs[$pair] = true;
			$directed[$source.'|'.$target] = true;

			$row = [
				'selementid1' => $selements[$source]['selementid'],
				'selementid2' => $selements[$target]['selementid'],
				// Green in normal state; on alarm the bound link indicator turns it red (native sysmap mechanism)
				'color' => self::DISCOVERY_LINK_COLOR,
				'drawtype' => 0
			];

			// Each link can set its "trigger source" (source / local / both / none); by default the source end comes first
			$trigger_source = $this->normalizeTriggerSource($link['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO);

			// Automatically link port status triggers: once matched, both the native map and the module canvas draw the link red on alarm
			$triggerids = $this->matchLinkTriggers(
				$host_of_node[$source] ?? '',
				$host_of_node[$target] ?? '',
				(string) ($link['source_port'] ?? ''),
				(string) ($link['target_port'] ?? ''),
				$trigger_cache,
				$trigger_source
			);

			if ($triggerids) {
				$row['linktriggers'] = [];

				foreach ($triggerids as $triggerid) {
					$row['linktriggers'][] = [
						'triggerid' => $triggerid,
						'drawtype' => 0,
						'color' => self::DISCOVERY_TRIGGER_COLOR
					];
				}

				// Zabbix 8.0 only allows links with indicator_type=1 to carry linktriggers;
				// without it map.create rejects the whole map as invalid (versions before 8.0 do not write this field).
				$indicator_type = self::linkIndicatorType(true);

				if ($indicator_type !== null) {
					$row['indicator_type'] = $indicator_type;
				}

				$trigger_total += count($triggerids);
			}

			$links[] = $row;
			$link_meta[] = [
				'source_port' => (string) ($link['source_port'] ?? ''),
				'target_port' => (string) ($link['target_port'] ?? ''),
				'trigger_source' => $trigger_source
			];
		}

		$width = 80;
		$height = 80;

		foreach ($positions as $point) {
			$width = max($width, $point['x'] + 220);
			$height = max($height, $point['y'] + 180);
		}

		$message_offset = $this->apiMessageCount();

		$result = API::Map()->create([[
			'name' => $name,
			'width' => $width,
			'height' => $height,
			'label_type' => 0,
			'label_location' => 0,
			'grid_show' => 0,
			'grid_align' => 1,
			'grid_size' => 50,
			'highlight' => 1,
			'private' => 0,
			'selements' => array_values($selements),
			'links' => $links
		]]);

		$sysmapid = '';

		if (is_array($result)) {
			if (!empty($result['sysmapids'][0])) {
				$sysmapid = (string) $result['sysmapids'][0];
			}
			elseif (!empty($result['sysmapid'])) {
				$sysmapid = (string) $result['sysmapid'];
			}
		}

		if ($sysmapid === '') {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('Discovery: failed to generate the map: %1$s', $detail));
			}

			throw new \Exception(LanguageManager::t('Discovery: Zabbix did not return a map ID'));
		}

		// map.create does not return linkids: read back once and match in creation order, then write each link's end ports and
		// "trigger source" into the module table, so the frontend can label ports on links and re-match triggers per end.
		$this->persistDiscoveredLinks($sysmapid, $link_meta);
		$this->saveMapLayout($sysmapid, $root_pkey, 'vertical');

		return [
			'success' => true,
			'sysmapid' => $sysmapid,
			'name' => $name,
			'devices' => count($selements),
			'links' => count($links),
			'triggers' => $trigger_total
		];
	}

	/**
	 * In-canvas "rediscover": merge the nodes/links found in this scan into the existing map in place.
	 *
	 * Differences from modeDiscoverSave():
	 *   - reuses existing selementids and linkids, which preserves the bandwidth / upstream / downstream items
	 *     stored by linkid in topomap_link_metrics;
	 *   - submits the merged full selements / links / shapes with map.update (existing items not submitted are deleted);
	 *   - devices not scanned and manual links are kept by default; unused automatic links are removed only when prune_links=1.
	 */
	private function modeRediscover(): array {
		$sysmapid = $this->requireSysmapid();
		$payload = json_decode((string) $this->getInput('payload', ''), true);

		if (!is_array($payload) || empty($payload['nodes']) || !is_array($payload['nodes'])) {
			throw new \Exception(LanguageManager::t('Rediscovery: nothing to update'));
		}

		// Keywords share the same setting as auto discovery
		if (!empty($payload['keywords']) && is_array($payload['keywords'])) {
			$this->saveTriggerKeywords($payload['keywords']);
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid', 'name', 'width', 'height'],
			'selectSelements' => ['selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on',
				'iconid_disabled', 'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap',
				'evaltype', 'tags', 'urls', 'elementsubtype', 'areatype', 'width', 'height', 'viewtype'
			],
			'selectLinks' => 'extend',
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font',
				'font_size', 'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width',
				'border_color', 'background_color', 'zindex'
			]
		]);

		if (!$maps) {
			throw new \Exception(LanguageManager::t('Map does not exist or you do not have permission to view it'));
		}

		$map = $maps[0];
		$old_selements = isset($map['selements']) && is_array($map['selements'])
			? array_values($map['selements'])
			: [];
		$old_links = isset($map['links']) && is_array($map['links'])
			? array_values($map['links'])
			: [];
		$old_shapes = isset($map['shapes']) && is_array($map['shapes'])
			? array_values($map['shapes'])
			: [];
		$old_metrics = $this->loadLinkMetrics($sysmapid);
		$old_paths = $this->loadLinkPaths($sysmapid);

		// Old links sorted by linkid ascending, so the lowest number is reused first in the device-pair fallback match
		usort($old_links, function (array $a, array $b): int {
			return (int) ($a['linkid'] ?? 0) <=> (int) ($b['linkid'] ?? 0);
		});

		$payload_links = isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : [];
		$keep_layout = (string) ($payload['layout'] ?? 'tree') === 'keep';
		$prune_links = !empty($payload['prune_links']);

		// Direction and root of the old map: the relayout must keep the original root and level direction, instead of
		// switching the root or turning a top-down tree into a left-right layout just because this scan started elsewhere.
		$payload_pkeys = [];
		$node_keys = [];
		$key_pkey = [];

		foreach ($payload['nodes'] as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];
			$node_keys[] = $key;
			$pkey = $this->nodePKey($node);
			$key_pkey[$key] = $pkey;

			if ($pkey !== '') {
				$payload_pkeys[$pkey] = true;
			}
		}

		$layout_hint = $this->detectTreeLayout((string) $sysmapid, $old_selements, $old_links, $payload_pkeys);
		$direction = (string) ($layout_hint['direction'] ?? 'vertical');
		$root_key = '';

		if ((string) ($layout_hint['root_pkey'] ?? '') !== '') {
			foreach ($payload['nodes'] as $node) {
				if (is_array($node) && isset($node['key'])
						&& $this->nodePKey($node) === $layout_hint['root_pkey']) {
					$root_key = (string) $node['key'];
					break;
				}
			}
		}

		$structure = $this->treeStructure($node_keys, $payload_links, $root_key);
		// Record the root actually used (including one picked by the BFS fallback when the metadata has none)
		$effective_root_key = $root_key !== '' ? $root_key : (string) ($structure['roots'][0] ?? '');
		$root_pkey = (string) ($key_pkey[$effective_root_key] ?? '');
		$positions = $this->treeLayout($payload['nodes'], $payload_links, $effective_root_key, $direction);
		$keep_positions = [];

		if ($keep_layout) {
			$keep_positions = $this->keepLayoutPositions($payload['nodes'], $structure, $old_selements, $direction);
		}
		// Old map upgrade: old elements still using the default auto-discovery icon are switched to Switch_/Workstation by type;
		// icons chosen manually by the user are left alone. The default icon is fetched first as the comparison baseline.
		$default_iconid = $this->defaultIconId();

		// ------------------------------------------------------------
		// Elements: match old elements by h:<hostid> / i:<first label line> and update them in place on a match
		// ------------------------------------------------------------

		$old_by_pkey = [];
		$selement_pkey = [];

		foreach ($old_selements as $se) {
			$pkey = $this->elementPKey($se);
			$selement_pkey[(string) ($se['selementid'] ?? '')] = $pkey;

			if ($pkey !== '' && !isset($old_by_pkey[$pkey])) {
				$old_by_pkey[$pkey] = $se;
			}
		}

		$selements = [];        // selementid => row
		$node_selementid = [];  // payload node key => selementid
		$node_pkey = [];        // payload node key => pkey
		$host_of_node = [];     // payload node key => hostid (empty if unmanaged)
		$matched = [];          // Matched old selementid
		$new_seq = 0;
		$added_devices = 0;

		foreach ($payload['nodes'] as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];
			$hostid = trim((string) ($node['hostid'] ?? ''));
			$valid_host = $hostid !== '' && ctype_digit($hostid) && $hostid !== '0';
			$pkey = $this->nodePKey($node);
			$label = (string) ($node['name'] ?? '');

			if (!empty($node['ip'])) {
				$label = $label."\n".(string) $node['ip'];
			}

			// Newly added host elements use two-line macro labels (name / connection address); unmanaged images still hard-code name and IP
			if ($valid_host) {
				$label = self::DEFAULT_HOST_LABEL;
			}

			$node_pkey[$key] = $pkey;
			$host_of_node[$key] = $valid_host ? $hostid : '';
			$node_icon = $this->iconForKind((string) ($node['kind'] ?? ''));

			$old = ($pkey !== '' && isset($old_by_pkey[$pkey])) ? $old_by_pkey[$pkey] : null;

			if ($old !== null) {
				unset($old_by_pkey[$pkey]);

				$selementid = (string) ($old['selementid'] ?? '');
				$row = $old;
				$row['elementtype'] = (int) ($old['elementtype'] ?? 0);

				// When the old map still has the default auto-discovery icon (Server_(96)), switch it by device type to
				// Switch_(128) / Workstation_(128), so maps generated earlier are not stuck with Server everywhere;
				// manually changed icons are not overwritten.
				if ($node_icon !== '' && (string) ($row['iconid_off'] ?? '0') === $default_iconid) {
					$row['iconid_off'] = $node_icon;
				}

				// Was an unmanaged image last time and matches a host now: upgrade in place to a host element, keeping icon and label
				if ($valid_host && $row['elementtype'] === 4) {
					$row['elementtype'] = 0;
					$row['elements'] = [['hostid' => $hostid]];
					$row['label_location'] = 0;
				}

				if (trim((string) ($row['label'] ?? '')) === '') {
					$row['label'] = $label;
				}

				if (!$keep_layout && isset($positions[$key])) {
					$row['x'] = (int) $positions[$key]['x'];
					$row['y'] = (int) $positions[$key]['y'];
				}

				$matched[$selementid] = true;
			}
			else {
				$selementid = 'new'.(++$new_seq);

				if ($valid_host) {
					$row = [
						'selementid' => $selementid,
						'elementtype' => 0,
						'elements' => [['hostid' => $hostid]],
						'label' => $label,
						'label_location' => 0,
						'iconid_off' => $node_icon,
						'use_iconmap' => 0,
						'evaltype' => 0
					];
				}
				else {
					$row = [
						'selementid' => $selementid,
						'elementtype' => 4,
						'label' => $label,
						'label_location' => 0,
						'iconid_off' => $node_icon
					];
				}

				if ($keep_layout) {
					// Old elements stay put; new devices hang below / to the right of their parent in the spanning tree
					$point = $keep_positions[$key] ?? ['x' => 80, 'y' => 80];
					$row['x'] = (int) $point['x'];
					$row['y'] = (int) $point['y'];
				}
				else {
					$point = $positions[$key] ?? ['x' => 80, 'y' => 80];
					$row['x'] = (int) $point['x'];
					$row['y'] = (int) $point['y'];
				}

				$added_devices++;
			}

			$selements[$selementid] = $row;
			$node_selementid[$key] = $selementid;
		}

		// Add only, never remove: existing elements not scanned this time are submitted as is so map.update does not delete them
		foreach ($old_selements as $se) {
			$selementid = (string) ($se['selementid'] ?? '');

			if ($selementid === '' || isset($matched[$selementid]) || isset($selements[$selementid])) {
				continue;
			}

			$selements[$selementid] = $se;
		}

		// ------------------------------------------------------------
		// Links: exact match by port key first, then fall back to the device pair (when a host changed ports)
		// ------------------------------------------------------------

		$old_link_info = [];
		$old_by_port = [];
		$old_by_pair = [];

		foreach ($old_links as $ol) {
			$linkid = (string) ($ol['linkid'] ?? '');

			if ($linkid === '') {
				continue;
			}

			$p1 = $selement_pkey[(string) ($ol['selementid1'] ?? '')] ?? '';
			$p2 = $selement_pkey[(string) ($ol['selementid2'] ?? '')] ?? '';
			$metrics = $old_metrics[$linkid] ?? [
				'bw' => [], 'in' => [], 'out' => [], 'source_port' => '', 'target_port' => '',
				'trigger_source' => self::TRIGGER_SOURCE_AUTO
			];
			$port1 = (string) $metrics['source_port'];
			$port2 = (string) $metrics['target_port'];
			$auto = ($port1 !== '' || $port2 !== '');

			$old_link_info[$linkid] = [
				'auto' => $auto,
				'row' => $ol,
				'p1' => $p1,
				'p2' => $p2
			];

			if ($p1 === '' || $p2 === '') {
				continue;
			}

			$old_by_port[$this->linkMatchKey($p1, $p2, $port1, $port2)][] = $linkid;

			if ($auto) {
				$old_by_pair[$this->linkMatchKey($p1, $p2, '', '')][] = $linkid;
			}
		}

		$links = [];
		$used = [];
		$reuse_meta = [];
		$new_meta = [];
		$added_links = 0;
		$kept_links = 0;
		$removed_links = 0;
		$trigger_cache = [];
		$trigger_total = 0;
		$directed = [];
		$pairs = [];

		foreach ($payload_links as $link) {
			if (!is_array($link) || !isset($link['source'], $link['target'])) {
				continue;
			}

			$source = (string) $link['source'];
			$target = (string) $link['target'];

			if (!isset($node_selementid[$source], $node_selementid[$target]) || $source === $target) {
				continue;
			}

			// Same as auto discovery: reverse duplicates (B→A when A→B exists) are dropped entirely
			if (isset($directed[$target.'|'.$source])) {
				continue;
			}

			$source_port = (string) ($link['source_port'] ?? '');
			$target_port = (string) ($link['target_port'] ?? '');
			$p1 = $node_pkey[$source] ?? '';
			$p2 = $node_pkey[$target] ?? '';
			$port_key = $this->linkMatchKey($p1, $p2, $source_port, $target_port);

			if (isset($pairs[$port_key])) {
				continue;
			}

			$pairs[$port_key] = true;
			$directed[$source.'|'.$target] = true;

			$trigger_source = $this->normalizeTriggerSource(
				$link['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO
			);
			$triggerids = $this->matchLinkTriggers(
				$host_of_node[$source] ?? '',
				$host_of_node[$target] ?? '',
				$source_port,
				$target_port,
				$trigger_cache,
				$trigger_source
			);

			$row = [
				'selementid1' => $node_selementid[$source],
				'selementid2' => $node_selementid[$target],
				'color' => self::DISCOVERY_LINK_COLOR,
				'drawtype' => 0,
				// Matched links must rebind triggers for the new ports (when the ports change, the old binding is wrong)
				'linktriggers' => []
			];

			if ($triggerids) {
				foreach ($triggerids as $triggerid) {
					$row['linktriggers'][] = [
						'triggerid' => $triggerid,
						'drawtype' => 0,
						'color' => self::DISCOVERY_TRIGGER_COLOR
					];
				}

				$trigger_total += count($triggerids);
			}

			$meta = [
				'source_port' => $source_port,
				'target_port' => $target_port,
				'trigger_source' => $trigger_source
			];

			$reuse = '';

			if (isset($old_by_port[$port_key])) {
				foreach ($old_by_port[$port_key] as $candidate) {
					if (!isset($used[$candidate])) {
						$reuse = $candidate;
						break;
					}
				}
			}

			if ($reuse === '') {
				$pair_key = $this->linkMatchKey($p1, $p2, '', '');

				if (isset($old_by_pair[$pair_key])) {
					foreach ($old_by_pair[$pair_key] as $candidate) {
						if (!isset($used[$candidate])) {
							$reuse = $candidate;
							break;
						}
					}
				}
			}

			if ($reuse !== '') {
				// Reuse the old linkid: bandwidth/traffic settings in the metrics table are kept; only ports and trigger source are updated
				$used[$reuse] = true;
				$old_row = $old_link_info[$reuse]['row'];
				$row['linkid'] = $reuse;

				if (!empty($old_row['color'])) {
					$row['color'] = (string) $old_row['color'];
				}

				if (isset($old_row['drawtype']) && $old_row['drawtype'] !== '') {
					$row['drawtype'] = (int) $old_row['drawtype'];
				}

				if (trim((string) ($old_row['label'] ?? '')) !== '') {
					$row['label'] = (string) $old_row['label'];
				}

				$reuse_meta[$reuse] = $meta;
				$kept_links++;
			}
			else {
				$new_meta[] = $meta;
				$added_links++;
			}

			$links[] = $row;
		}

		$kept_ids = [];

		foreach ($links as $row) {
			if (isset($row['linkid'])) {
				$kept_ids[(string) $row['linkid']] = true;
			}
		}

		// Old links not found in this discovery: manual links are always kept; automatic links are kept or removed per user option
		foreach ($old_links as $ol) {
			$linkid = (string) ($ol['linkid'] ?? '');

			if ($linkid === '' || isset($used[$linkid])) {
				continue;
			}

			if ($old_link_info[$linkid]['auto'] && $prune_links) {
				$removed_links++;
				continue;
			}

			$links[] = $ol;
			$kept_ids[$linkid] = true;
		}

		// ------------------------------------------------------------
		// Submit the merged result in one go
		// ------------------------------------------------------------

		$width = 80;
		$height = 80;

		foreach ($selements as $se) {
			$width = max($width, (int) ($se['x'] ?? 0) + 220);
			$height = max($height, (int) ($se['y'] ?? 0) + 180);
		}

		$update = [
			'sysmapid' => $sysmapid,
			// Large maps (trees with hundreds of devices) get very wide; the limit is raised to 60000 so nodes on the right of the canvas
			// are not cut off by the old 10000 limit (the 8.0 API was verified to accept a width of 24000)
			'width' => max(100, min(60000, max($width, (int) ($map['width'] ?? 0)))),
			'height' => max(100, min(60000, max($height, (int) ($map['height'] ?? 0)))),
			'selements' => $this->sanitizeSelements(array_values($selements)),
			'links' => $this->sanitizeLinks($links, $this->existingLinkIndicatorTypes((string) $sysmapid)),
			// Shapes are sent back unchanged so map.update does not delete boxes drawn by the user
			'shapes' => $this->sanitizeShapes($old_shapes)
		];

		// 7.0.x link update validation requires sysmapid; the 7.4/8.0 field whitelist does not accept it (see the note in modeSave)
		if (self::linkSysmapidRequired()) {
			foreach ($update['links'] as &$link_row) {
				if (isset($link_row['linkid'])) {
					$link_row['sysmapid'] = $sysmapid;
				}
			}
			unset($link_row);
		}

		$message_offset = $this->apiMessageCount();
		$update_result = API::Map()->update([$update]);

		if ($update_result === false) {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('Rediscovery: failed to save the map: %1$s', $detail));
			}

			throw new \Exception(LanguageManager::t('Rediscovery: failed to save the map'));
		}

		// ------------------------------------------------------------
		// Metric write-back: new links claim their linkid from the read-back result; others keep their old settings
		// ------------------------------------------------------------

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$db_links = ($maps && isset($maps[0]['links']) && is_array($maps[0]['links']))
			? array_values($maps[0]['links'])
			: [];
		$new_ids = [];

		foreach ($db_links as $db_link) {
			$id = (string) ($db_link['linkid'] ?? '');

			if ($id !== '' && !isset($kept_ids[$id])) {
				$new_ids[] = $id;
			}
		}

		sort($new_ids, SORT_NUMERIC);

		$final = [];

		foreach (array_keys($kept_ids) as $linkid) {
			$linkid = (string) $linkid;
			$metrics = $old_metrics[$linkid] ?? [
				'bw' => [], 'in' => [], 'out' => [], 'source_port' => '', 'target_port' => '',
				'trigger_source' => self::TRIGGER_SOURCE_AUTO
			];

			if (isset($reuse_meta[$linkid])) {
				$metrics['source_port'] = $reuse_meta[$linkid]['source_port'];
				$metrics['target_port'] = $reuse_meta[$linkid]['target_port'];
				$metrics['trigger_source'] = $reuse_meta[$linkid]['trigger_source'];
			}

			$final[$linkid] = $metrics;
		}

		foreach ($new_meta as $index => $meta) {
			if (!isset($new_ids[$index])) {
				break;
			}

			$final[$new_ids[$index]] = [
				'bw' => [],
				'in' => [],
				'out' => [],
				'source_port' => $meta['source_port'],
				'target_port' => $meta['target_port'],
				'trigger_source' => $meta['trigger_source']
			];
		}

		$this->saveLinkMetrics($sysmapid, $final);

		// Routing / waypoints: links reusing a linkid are kept as is; rows of removed links disappear when the table is rebuilt,
		// new links default to automatic curve (no row).
		$final_paths = [];

		foreach (array_keys($kept_ids) as $linkid) {
			if (isset($old_paths[(string) $linkid])) {
				$final_paths[$linkid] = $old_paths[(string) $linkid];
			}
		}

		$this->saveLinkPaths($sysmapid, $final_paths);
		// Layout metadata write-back: the next rediscovery uses the same root / direction directly
		$this->saveMapLayout((string) $sysmapid, $root_pkey, $direction);

		return [
			'success' => true,
			'sysmapid' => $sysmapid,
			'added_devices' => $added_devices,
			'kept_devices' => count($matched),
			'added_links' => $added_links,
			'kept_links' => $kept_links,
			'removed_links' => $removed_links,
			'triggers' => $trigger_total
		];
	}

	/**
	 * Main interface IP (the first interface when none is marked main). Used to write the label automatically after selecting a host.
	 */
	private function hostIp(array $host): string {
		if (empty($host['interfaces'])) {
			return '';
		}

		foreach ($host['interfaces'] as $iface) {
			if (isset($iface['main']) && (int) $iface['main'] === 1) {
				return (string) $iface['ip'];
			}
		}

		return (string) $host['interfaces'][0]['ip'];
	}

	/**
	 * Connection address for {HOST.CONN}: main interface first, then the SNMP interface, then the first visible interface.
	 * Interfaces with useip=1 use the IP, otherwise DNS first; if either is empty, fall back to the other.
	 * Every modeGet calls host.get again, so renamed hosts / rediscoveries always get the latest values.
	 */
	private function hostConn(array $host): string {
		if (empty($host['interfaces'])) {
			return '';
		}

		$chosen = null;

		foreach ($host['interfaces'] as $iface) {
			if (isset($iface['main']) && (int) $iface['main'] === 1) {
				$chosen = $iface;
				break;
			}
		}

		if ($chosen === null) {
			$snmp_type = defined('INTERFACE_TYPE_SNMP') ? (int) INTERFACE_TYPE_SNMP : 2;

			foreach ($host['interfaces'] as $iface) {
				if (isset($iface['type']) && (int) $iface['type'] === $snmp_type) {
					$chosen = $iface;
					break;
				}
			}
		}

		if ($chosen === null) {
			$chosen = $host['interfaces'][0];
		}

		$ip = trim((string) ($chosen['ip'] ?? ''));
		$dns = trim((string) ($chosen['dns'] ?? ''));
		$useip = isset($chosen['useip']) ? (int) $chosen['useip'] : 1;

		if ($useip === 1) {
			return $ip !== '' ? $ip : $dns;
		}

		return $dns !== '' ? $dns : $ip;
	}

	// ------------------------------------------------------------------
	// Auto discovery helpers
	// ------------------------------------------------------------------

	/**
	 * Comma-separated ID list → deduplicated array of digits.
	 */
	private function parseIdList(string $raw): array {
		$ids = [];

		foreach (explode(',', $raw) as $id) {
			$id = trim($id);

			if ($id !== '' && ctype_digit($id) && $id !== '0') {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

	/**
	 * protocols=LLDP,CDP → ['LLDP', 'CDP']; invalid values are ignored, default LLDP.
	 */
	private function parseProtocols(string $raw): array {
		$allow = ['LLDP', 'CDP', 'EDP'];
		$result = [];

		foreach (explode(',', strtoupper($raw)) as $item) {
			$item = trim($item);

			if (in_array($item, $allow, true)) {
				$result[$item] = $item;
			}
		}

		return $result ? array_values($result) : ['LLDP'];
	}

	// ------------------------------------------------------------------
	// Auto discovery: linking links ↔ port status triggers
	// ------------------------------------------------------------------

	/**
	 * Match "port status" triggers for an auto-discovered link (source end first; the target end only if the source has no match).
	 *
	 * Source-first for the same reason as aiops: a source match is used alone, so binding both ends does not double the count;
	 * when the trigger is configured only on the other device, fall back to the target end.
	 *
	 * @param array $cache trigger lists cached by hostid (passed by reference, reused across links)
	 *
	 * @param int   $trigger_source the link's "trigger source" scope (see the TRIGGER_SOURCE_* constants)
	 *
	 * @return string[] matched triggerids
	 */
	private function matchLinkTriggers(string $src_hostid, string $tgt_hostid, string $src_port, string $tgt_port,
			array &$cache, int $trigger_source = self::TRIGGER_SOURCE_AUTO): array {
		if ($trigger_source === self::TRIGGER_SOURCE_NONE) {
			return [];
		}

		$src_port_key = $this->ifPortKey($src_port);
		$tgt_port_key = $this->ifPortKey($tgt_port);

		if ($trigger_source === self::TRIGGER_SOURCE_SOURCE) {
			return $this->matchPortTriggers($src_hostid, $src_port_key, $cache);
		}

		if ($trigger_source === self::TRIGGER_SOURCE_TARGET) {
			return $this->matchPortTriggers($tgt_hostid, $tgt_port_key, $cache);
		}

		$src = $this->matchPortTriggers($src_hostid, $src_port_key, $cache);

		if ($trigger_source === self::TRIGGER_SOURCE_BOTH) {
			$both = $src;

			foreach ($this->matchPortTriggers($tgt_hostid, $tgt_port_key, $cache) as $triggerid) {
				$both[$triggerid] = $triggerid;
			}

			return array_values($both);
		}

		// AUTO: source end first, falls back to the local end only when the source has none (legacy behavior)
		if ($src) {
			return $src;
		}

		return $this->matchPortTriggers($tgt_hostid, $tgt_port_key, $cache);
	}

	/**
	 * Get the host's triggers whose description matches a keyword and contains the port name.
	 *
	 * @param string $port normalized port name (e.g. ge006)
	 *
	 * @return string[]
	 */
	private function matchPortTriggers(string $hostid, string $port, array &$cache): array {
		if ($hostid === '' || $port === '') {
			return [];
		}

		if (!isset($cache[$hostid])) {
			$list = [];

			try {
				$rows = API::Trigger()->get([
					'hostids' => [$hostid],
					'output' => ['triggerid', 'description'],
					'monitored' => true,
					'skipDependent' => true
				]);

				foreach ($rows as $row) {
					$list[(string) $row['triggerid']] = (string) $row['description'];
				}
			}
			catch (\Throwable $e) {
				// If they cannot be read, treat the host as having no usable triggers
			}

			$cache[$hostid] = $list;
		}

		// Alphanumeric boundary match: ge006 must not match ge0060 / ge0061…, otherwise port 6 would get the triggers of ports 60+
		$pattern = '/(?<![a-z0-9])'.preg_quote($port, '/').'(?![a-z0-9])/';
		$out = [];

		foreach ($cache[$hostid] as $triggerid => $description) {
			$lower = mb_strtolower($description);

			if (!$this->hasLinkTriggerKeyword($lower)) {
				continue;
			}

			if (preg_match($pattern, $this->ifPortKey($description))) {
				$out[] = $triggerid;
			}
		}

		return $out;
	}

	private function hasLinkTriggerKeyword(string $lower_description): bool {
		foreach ($this->loadTriggerKeywords() as $keyword) {
			if (mb_strpos($lower_description, $keyword) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Port name normalization key: strips the " (description)" suffix added during discovery, abbreviates full names and removes separators,
	 * for cross-format matching (Gi0/0/6 ↔ ge0_0_6 ↔ GigabitEthernet0/0/6).
	 * Kept consistent with aiops' ifPortKey.
	 */
	private function ifPortKey(string $s): string {
		$s = preg_split('/\s*\(/', $s)[0] ?? '';
		$s = mb_strtolower($s);
		$s = (string) preg_replace('/ten[-_ ]?gigabitethernet/', 'te', $s);
		$s = (string) preg_replace('/gigabitethernet/', 'ge', $s);
		$s = (string) preg_replace('/fastethernet/', 'fa', $s);
		$s = (string) preg_replace('/gi(?=[0-9\/])/', 'ge', $s);

		return (string) preg_replace('/[\s_\-:.\/]/', '', $s);
	}

	/**
	 * Topology element match key: h:<hostid> for managed hosts, i:<first label line in lowercase> for unmanaged image elements.
	 */
	private function elementPKey(array $selement): string {
		$type = (int) ($selement['elementtype'] ?? -1);

		if ($type === 0 && !empty($selement['elements'][0]['hostid'])) {
			return 'h:'.(string) $selement['elements'][0]['hostid'];
		}

		if ($type === 4) {
			$lines = preg_split('/\r?\n/', (string) ($selement['label'] ?? ''));
			$name = mb_strtolower(trim((string) ($lines[0] ?? '')));

			return $name === '' ? '' : 'i:'.$name;
		}

		return '';
	}

	/**
	 * Match key of nodes in the rediscovery payload: same scheme as elementPKey().
	 */
	private function nodePKey(array $node): string {
		$hostid = trim((string) ($node['hostid'] ?? ''));

		if ($hostid !== '' && ctype_digit($hostid) && $hostid !== '0') {
			return 'h:'.$hostid;
		}

		$name = mb_strtolower(trim((string) ($node['name'] ?? '')));

		return $name === '' ? '' : 'i:'.$name;
	}

	/**
	 * Link match key: both ends (element key + normalized port) sorted and joined;
	 * falls back to the device-pair key when ports are empty.
	 */
	private function linkMatchKey(string $p1, string $p2, string $port1, string $port2): string {
		$a = $p1.'#'.$this->ifPortKey($port1);
		$b = $p2.'#'.$this->ifPortKey($port2);

		return $a <= $b ? $a.'|'.$b : $b.'|'.$a;
	}

	/**
	 * The host's main SNMP interface (type=2); null if there is none.
	 */
	private function snmpInterface(array $host): ?array {
		$fallback = null;

		foreach ($host['interfaces'] ?? [] as $iface) {
			if ((int) $iface['type'] !== 2) {
				continue;
			}

			if (isset($iface['main']) && (int) $iface['main'] === 1) {
				return $iface;
			}

			if ($fallback === null) {
				$fallback = $iface;
			}
		}

		return $fallback;
	}

	/**
	 * Host macros + global macros (host macros take precedence). Used to resolve {$SNMP_COMMUNITY} in SNMP interfaces.
	 */
	private function hostMacros(array $hostids): array {
		$global = [];
		$per_host = [];

		try {
			$globals = API::UserMacro()->get([
				'output' => ['macro', 'value'],
				'globalmacro' => true
			]);

			foreach ($globals as $macro) {
				$global[$macro['macro']] = (string) $macro['value'];
			}
		}
		catch (\Throwable $e) {
			// If global macros cannot be read, rely on host macros only
		}

		try {
			$hosts = API::UserMacro()->get([
				'output' => ['macro', 'value'],
				'hostids' => $hostids
			]);

			foreach ($hosts as $macro) {
				$hostid = isset($macro['hostid']) ? (string) $macro['hostid'] : '';

				if ($hostid === '') {
					continue;
				}

				$per_host[$hostid][$macro['macro']] = (string) $macro['value'];
			}
		}
		catch (\Throwable $e) {
			// Same as above
		}

		// Every host needs a complete macro table: global macros at the bottom, overridden by host macros.
		// Attaching global macros only to $result[0] is wrong — hosts without their own macros would get no value,
		// and macros defined only globally such as {$SNMP_COMMUNITY} would fail to resolve.
		$result = [];

		foreach ($hostids as $hostid) {
			$hostid = (string) $hostid;
			$result[$hostid] = array_merge($global, $per_host[$hostid] ?? []);
		}

		return $result;
	}

	private function resolveMacros(string $text, array $macros): string {
		if ($text === '' || strpos($text, '{$') === false) {
			return $text;
		}

		return (string) preg_replace_callback('/\{\$[A-Za-z0-9_.\-]+\}/', function (array $match) use ($macros) {
			return array_key_exists($match[0], $macros) ? $macros[$match[0]] : $match[0];
		}, $text);
	}

	/**
	 * Full host index: neighbor device names / management IPs must map back to Zabbix hosts.
	 * A host may have several interface IPs; all are indexed.
	 *
	 * The entry carries the host's own management IP: once a neighbor matches a host, the node shows the host's address in Zabbix
	 * instead of the address reported by the LLDP neighbor — the latter is often wrong (H3C echoes its own management address to all neighbor ports,
	 * so many devices show the same IP).
	 */
	private function discoveryIndex(): array {
		$index = ['by_name' => [], 'by_ip' => []];

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'host'],
			'selectInterfaces' => ['ip', 'main'],
			'limit' => 5000
		]);

		foreach ($hosts as $host) {
			$entry = ['hostid' => (string) $host['hostid'], 'name' => $host['name'], 'ip' => ''];

			// Display address: main interface first, then any usable interface
			$interfaces = $host['interfaces'] ?? [];
			$fallback = '';

			foreach ($interfaces as $iface) {
				$ip = trim((string) ($iface['ip'] ?? ''));

				if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
					continue;
				}

				if ($fallback === '') {
					$fallback = $ip;
				}

				if ((int) ($iface['main'] ?? 0) === 1) {
					$entry['ip'] = $ip;

					break;
				}
			}

			if ($entry['ip'] === '') {
				$entry['ip'] = $fallback;
			}

			foreach ([$host['name'], $host['host']] as $candidate) {
				$key = mb_strtolower(trim((string) $candidate));

				if ($key !== '' && !isset($index['by_name'][$key])) {
					$index['by_name'][$key] = $entry;
				}
			}

			foreach ($interfaces as $iface) {
				$ip = trim((string) ($iface['ip'] ?? ''));

				if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && !isset($index['by_ip'][$ip])) {
					$index['by_ip'][$ip] = $entry;
				}
			}
		}

		return $index;
	}

	/**
	 * Neighbor → Zabbix host. Match by device name first (sysName usually equals the host name or host),
	 * then by management IP. No match means an unmanaged device, shown as an image element.
	 *
	 * $self_ips are the interface IPs of the current switch: some devices (Huawei) put their own management address
	 * into lldpRemManAddr of every port; without excluding them the switch would match itself.
	 */
	private function matchNeighbor(array $neighbor, array $index, array $self_ips = []): array {
		$name = trim((string) ($neighbor['sysname'] ?? ''));

		if ($name !== '') {
			$key = mb_strtolower($name);

			if (isset($index['by_name'][$key])) {
				return $index['by_name'][$key] + ['how' => 'name'];
			}

			// LLDP sysName often has a domain suffix (sw01.example.com); strip it and try again
			$short = preg_replace('/\..*$/', '', $name);

			if ($short !== '' && $short !== $name && isset($index['by_name'][mb_strtolower($short)])) {
				return $index['by_name'][mb_strtolower($short)] + ['how' => 'name'];
			}
		}

		$ip = trim((string) ($neighbor['ip'] ?? ''));

		if ($ip !== '' && !in_array($ip, $self_ips, true) && isset($index['by_ip'][$ip])) {
			return $index['by_ip'][$ip] + ['how' => 'ip'];
		}

		return ['hostid' => '', 'name' => '', 'ip' => '', 'how' => ''];
	}

	/**
	 * Guess the device type from sysDescr / device name; the frontend picks the icon from it.
	 * When unsure, treat it as a switch — auto discovery starts from switches anyway.
	 */
	private function guessKind(string $sysdesc, string $sysname): string {
		$text = mb_strtolower($sysdesc.' '.$sysname);

		$rules = [
			'firewall' => ['firewall', 'usg', 'asa ', 'pix'],
			'router' => ['router', 'routeros', 'vrp', 'ios xe'],
			'camera' => ['camera', 'ipc', 'nvr', 'dvr', 'hikvision', '海康', '摄像'],
			'printer' => ['printer', 'laserjet', '打印'],
			'server' => ['server', 'vmware', 'esxi', 'linux', 'windows'],
			'ap' => ['access point', 'wireless', 'wlan', 'ap-', 'fit ap']
		];

		foreach ($rules as $kind => $words) {
			foreach ($words as $word) {
				if (strpos($text, $word) !== false) {
					return $kind;
				}
			}
		}

		return 'switch';
	}

	/**
	 * Map names are unique: on a duplicate name " (2)", " (3)" are appended automatically,
	 * instead of failing like the create button — clicking auto discovery several times is common.
	 */
	private function uniqueMapName(string $name): string {
		$candidate = $name;
		$seq = 2;

		while (true) {
			$exists = API::Map()->get([
				'output' => ['sysmapid'],
				'filter' => ['name' => $candidate]
			]);

			if (!$exists) {
				return $candidate;
			}

			$suffix = ' ('.$seq.')';
			$base = mb_substr($name, 0, max(1, 128 - mb_strlen($suffix)));
			$candidate = $base.$suffix;
			$seq++;

			if ($seq > 200) {
				return $base.' ('.date('His').')';
			}
		}
	}

	/**
	 * Icon of elements created by auto discovery: Server_(96) if available, otherwise the first icon of the map icon library.
	 */
	private function defaultIconId(): string {
		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => 1],
			'sortfield' => 'name',
			'sortorder' => 'ASC'
		]);

		if (!$images) {
			return '0';
		}

		foreach (['Server_(96)', 'Server_(64)', 'Switch_1', 'Cloud_(96)'] as $want) {
			foreach ($images as $image) {
				if (strcasecmp($image['name'], $want) === 0) {
					return (string) $image['imageid'];
				}
			}
		}

		return (string) $images[0]['imageid'];
	}

	/** Icon name (lowercase) => imageid, reused within one request */
	private $icon_ids = null;

	/**
	 * Choose the icon by device type, aligned with aiops:
	 * switch Switch_(128), endpoint PC Workstation_(128), server Server_(96).
	 */
	private function iconForKind(string $kind): string {
		if ($this->icon_ids === null) {
			$this->icon_ids = [];

			$images = API::Image()->get([
				'output' => ['imageid', 'name'],
				'filter' => ['imagetype' => 1]
			]);

			foreach ($images as $image) {
				$this->icon_ids[strtolower((string) $image['name'])] = (string) $image['imageid'];
			}
		}

		$by_kind = [
			'switch' => ['Switch_(128)', 'Switch_(96)', 'Switch_(64)'],
			'router' => ['Router_(128)', 'Router_(96)'],
			'firewall' => ['Firewall_(128)', 'Firewall_(96)'],
			'server' => ['Server_(96)', 'Server_(64)'],
			'pc' => ['Workstation_(128)', 'Workstation_(96)'],
			'ap' => ['Workstation_(128)', 'Workstation_(96)'],
			'printer' => ['Printer_(128)', 'Printer_(96)'],
			'camera' => ['Video_terminal_(128)', 'Video_terminal_(96)']
		];

		foreach ($by_kind[$kind] ?? [] as $want) {
			$key = strtolower($want);

			if (isset($this->icon_ids[$key])) {
				return $this->icon_ids[$key];
			}
		}

		return $this->defaultIconId();
	}

	// ------------------------------------------------------------------
	// Input validation / field whitelist
	// ------------------------------------------------------------------

	private function requireSysmapid(): string {
		$sysmapid = trim((string) $this->getInput('sysmapid', ''));

		if ($sysmapid === '' || !ctype_digit($sysmapid) || $sysmapid === '0') {
			throw new \Exception(LanguageManager::t('Missing valid map ID'));
		}

		return $sysmapid;
	}

	private function requireName(): string {
		return $this->normalizeName((string) $this->getInput('name', ''));
	}

	/**
	 * Canvas width / height: shared with native maps as sysmaps.width / height; it is the drag boundary for elements.
	 * Returns null when not passed (keep the current value) so existing sizes are not changed by accident; otherwise clamped to 100..10000.
	 */
	private function dimension(string $key): ?int {
		$raw = trim((string) $this->getInput($key, ''));

		if ($raw === '') {
			return null;
		}

		return max(100, min(10000, (int) $raw));
	}

	private function normalizeName(string $name): string {
		$name = trim($name);

		if ($name === '') {
			throw new \Exception(LanguageManager::t('The map name cannot be empty'));
		}

		// The sysmaps.name field length is 128
		if (mb_strlen($name) > 128) {
			throw new \Exception(LanguageManager::t('The map name cannot exceed 128 characters'));
		}

		return $name;
	}

	/**
	 * 7.0.x link update validation requires sysmapid on every link that has a linkid;
	 * CMap::validateLinkFields() in 7.4/8.0 is a strict field whitelist that rejects sysmapid.
	 */
	private static function linkSysmapidRequired(): bool {
		return !self::supportsLinkIndicatorType();
	}

	/**
	 * Since Zabbix 8.0 map links have indicator_type (0=static, 1=trigger, 2=item value),
	 * and linktriggers are only allowed when indicator_type=1. 7.x has no such field and writing it would
	 * be treated as an unknown parameter, so the feature is detected by checking whether the constant exists.
	 */
	private static function supportsLinkIndicatorType(): bool {
		return defined('MAP_INDICATOR_TYPE_TRIGGER') && defined('MAP_INDICATOR_TYPE_STATIC_LINK');
	}

	/**
	 * Return the indicator_type to write for the current version; on 7.x null means do not write it.
	 */
	private static function linkIndicatorType(bool $has_triggers): ?int {
		if (!self::supportsLinkIndicatorType()) {
			return null;
		}

		return $has_triggers
			? (int) constant('MAP_INDICATOR_TYPE_TRIGGER')
			: (int) constant('MAP_INDICATOR_TYPE_STATIC_LINK');
	}

	/**
	 * Number of messages currently registered. The Zabbix frontend API wrapper only returns false on error;
	 * the real error is recorded in CMessageHelper, from which it is retrieved and passed on here.
	 */
	private function apiMessageCount(): int {
		if (!class_exists('\CMessageHelper') || !method_exists('\CMessageHelper', 'getMessages')) {
			return 0;
		}

		return count(\CMessageHelper::getMessages());
	}

	/**
	 * API error texts registered after offset (several are joined with semicolons).
	 */
	private function apiErrorDetail(int $offset): string {
		if (!class_exists('\CMessageHelper') || !method_exists('\CMessageHelper', 'getMessages')) {
			return '';
		}

		$messages = array_slice(\CMessageHelper::getMessages(), $offset);
		$errors = [];

		foreach ($messages as $message) {
			if (($message['type'] ?? '') === 'error' && !empty($message['message'])) {
				$errors[] = trim((string) $message['message']);
			}
		}

		return implode('; ', array_unique($errors));
	}

	private function sanitizeSelements(array $items): array {
		$result = [];
		$seen_tags = [];

		foreach ($items as $item) {
			if (!is_array($item) || !isset($item['elementtype'])) {
				continue;
			}

			$row = [];

			foreach (self::SELEMENT_FIELDS as $field) {
				if (array_key_exists($field, $item)) {
					$row[$field] = $item[$field];
				}
			}

			$row['elementtype'] = (int) $row['elementtype'];

			if (!array_key_exists('iconid_off', $row)) {
				$row['iconid_off'] = 0;
			}

			foreach (['x', 'y', 'label_location', 'iconid_off', 'iconid_on', 'iconid_disabled',
					'iconid_maintenance', 'use_iconmap', 'evaltype'] as $field) {
				if (array_key_exists($field, $row) && $row[$field] !== '' && $row[$field] !== null) {
					$row[$field] = (int) $row[$field];
				}
			}

			if ($row['elementtype'] === 4) {
				// Image elements may not carry elements / labels / automatic icons
				unset($row['elements'], $row['tags'], $row['evaltype'], $row['use_iconmap']);
			}
			else {
				$elements = [];

				// Native maps can also have trigger / host group / nested map elements. This module does not edit them,
				// but they must be sent back unchanged on save (the API rejects missing elements, and dropped fields would delete those elements).
				if (isset($row['elements']) && is_array($row['elements'])) {
					foreach ($row['elements'] as $element) {
						if (!is_array($element)) {
							continue;
						}

						if (!empty($element['hostid'])) {
							$elements[] = ['hostid' => (string) $element['hostid']];
						}
						elseif (!empty($element['sysmapid'])) {
							$elements[] = ['sysmapid' => (string) $element['sysmapid']];
						}
						elseif (!empty($element['groupid'])) {
							$elements[] = ['groupid' => (string) $element['groupid']];
						}
						elseif (!empty($element['triggerid'])) {
							$trigger = ['triggerid' => (string) $element['triggerid']];

							if (!empty($element['selement_triggerid'])) {
								$trigger['selement_triggerid'] = (string) $element['selement_triggerid'];
							}

							$elements[] = $trigger;
						}
					}
				}

				// The API rejects elements without an attached object; skip them so the whole map save does not fail
				if (!$elements) {
					continue;
				}

				$row['elements'] = $elements;
			}

			if (isset($row['tags']) && is_array($row['tags'])) {
				$tags = [];
				$key = isset($row['selementid']) ? (string) $row['selementid'] : ('n'.count($result));

				foreach ($row['tags'] as $tag) {
					if (!is_array($tag) || !isset($tag['tag']) || trim((string) $tag['tag']) === '') {
						continue;
					}

					$tag_name = trim((string) $tag['tag']);
					$tag_value = isset($tag['value']) ? (string) $tag['value'] : '';
					$uniq = $key.'|'.$tag_name.'|'.$tag_value;

					if (isset($seen_tags[$uniq])) {
						continue;
					}

					$seen_tags[$uniq] = true;

					$tags[] = [
						'tag' => $tag_name,
						'value' => $tag_value,
						'operator' => isset($tag['operator']) ? (int) $tag['operator'] : 0
					];
				}

				$row['tags'] = $tags;
			}

			if (isset($row['urls']) && is_array($row['urls'])) {
				$urls = [];

				foreach ($row['urls'] as $url) {
					if (is_array($url) && isset($url['name'], $url['url']) && (string) $url['url'] !== '') {
						$urls[] = ['name' => (string) $url['name'], 'url' => (string) $url['url']];
					}
				}

				$row['urls'] = $urls;
			}

			if (isset($row['label']) && mb_strlen((string) $row['label']) > 2048) {
				$row['label'] = mb_substr((string) $row['label'], 0, 2048);
			}

			$result[] = $row;
		}

		return $result;
	}

	/**
	 * Read the indicator_type of the links currently on the map (linkid => int). Returns an empty array on 7.x where the field does not exist.
	 */
	private function existingLinkIndicatorTypes(string $sysmapid): array {
		if (!self::supportsLinkIndicatorType()) {
			return [];
		}

		$maps = API::Map()->get([
			'sysmapids' => $sysmapid,
			'output' => ['sysmapid'],
			'selectLinks' => ['linkid', 'indicator_type']
		]);

		if (!is_array($maps) || empty($maps[0]['links'])) {
			return [];
		}

		$result = [];

		foreach ($maps[0]['links'] as $link) {
			if (!empty($link['linkid'])) {
				$result[(string) $link['linkid']] = (int) ($link['indicator_type'] ?? 0);
			}
		}

		return $result;
	}

	/**
	 * @param array $items                   links submitted by the frontend
	 * @param array $existing_indicator_types indicator_type of existing links on 8.0 (linkid => int),
	 *                                        used so that clearing triggers only switches "trigger" links back to static
	 *                                        without affecting "item value" links set up by the user in the native editor.
	 */
	private function sanitizeLinks(array $items, array $existing_indicator_types = []): array {
		$result = [];

		foreach ($items as $item) {
			if (!is_array($item) || !isset($item['selementid1'], $item['selementid2'])) {
				continue;
			}

			$s1 = (string) $item['selementid1'];
			$s2 = (string) $item['selementid2'];

			if ($s1 === '' || $s2 === '' || $s1 === $s2) {
				continue;
			}

			$row = [];

			foreach (self::LINK_FIELDS as $field) {
				if (array_key_exists($field, $item) && $item[$field] !== '' && $item[$field] !== null) {
					$row[$field] = $item[$field];
				}
			}

			$row['selementid1'] = $s1;
			$row['selementid2'] = $s2;
			$row['label'] = isset($row['label']) ? (string) $row['label'] : '';

			// "Show label" in the native link properties: default -1 / always 1 / auto-hide 0
			$show_label = (int) ($row['show_label'] ?? -1);
			$row['show_label'] = in_array($show_label, [-1, 0, 1], true) ? $show_label : -1;

			if (isset($row['color'])) {
				$color = strtoupper(ltrim((string) $row['color'], '#'));

				if (!preg_match('/^[0-9A-F]{6}$/', $color)) {
					$color = '000000';
				}

				$row['color'] = $color;
			}

			if (isset($row['drawtype'])) {
				$drawtype = (int) $row['drawtype'];

				if (!in_array($drawtype, [0, 2, 3, 4], true)) {
					$drawtype = 0;
				}

				$row['drawtype'] = $drawtype;
			}

			// Triggers bound to the link (link indicators): links created by auto discovery carry them and they must be written back on save,
			// otherwise map.update drops them when rebuilding the link (a normal save would also turn green links back to default gray).
			if (isset($item['linktriggers']) && is_array($item['linktriggers'])) {
				$linktriggers = [];

				foreach ($item['linktriggers'] as $lt) {
					if (!is_array($lt) || empty($lt['triggerid']) || !ctype_digit((string) $lt['triggerid'])) {
						continue;
					}

					$lt_color = strtoupper(ltrim((string) ($lt['color'] ?? self::DISCOVERY_TRIGGER_COLOR), '#'));

					if (!preg_match('/^[0-9A-F]{6}$/', $lt_color)) {
						$lt_color = self::DISCOVERY_TRIGGER_COLOR;
					}

					// Each link indicator has its own line style: native supports 0/2/3/4; forcing 0 would discard the style chosen by the user
					$lt_drawtype = (int) ($lt['drawtype'] ?? 0);

					if (!in_array($lt_drawtype, [0, 2, 3, 4], true)) {
						$lt_drawtype = 0;
					}

					$linktriggers[] = [
						'triggerid' => (string) $lt['triggerid'],
						'drawtype' => $lt_drawtype,
						'color' => $lt_color
					];
				}

				// An empty array must be written too: when the user clicks "clear", only passing linktriggers explicitly as [] makes
				// CMap::update delete the bindings stored in the database.
				$row['linktriggers'] = $linktriggers;

				// 8.0 validates that linktriggers are only allowed with indicator_type=1 and, conversely,
				// that linktriggers cannot be empty when indicator_type=1. With triggers it must be 1;
				// when triggers are cleared, only former "trigger" links switch back to static, so item value links are not affected.
				if (self::linkIndicatorType(true) !== null) {
					if ($linktriggers !== []) {
						$row['indicator_type'] = (int) constant('MAP_INDICATOR_TYPE_TRIGGER');
					}
					else {
						$linkid = (string) ($item['linkid'] ?? '');
						$current_type = $existing_indicator_types[$linkid] ?? null;

						if ($current_type === null || (int) $current_type === (int) constant('MAP_INDICATOR_TYPE_TRIGGER')) {
							$row['indicator_type'] = (int) constant('MAP_INDICATOR_TYPE_STATIC_LINK');
						}
					}
				}
			}

			$result[] = $row;
		}

		return $result;
	}

	private function sanitizeShapes(array $items): array {
		$result = [];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$row = [];

			foreach (self::SHAPE_FIELDS as $field) {
				if (array_key_exists($field, $item) && $item[$field] !== '' && $item[$field] !== null) {
					$row[$field] = $item[$field];
				}
			}

			foreach (['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'font', 'font_size',
					'text_halign', 'text_valign', 'border_type', 'border_width', 'zindex'] as $field) {
				if (array_key_exists($field, $row)) {
					$row[$field] = (int) $row[$field];
				}
			}

			foreach (['font_color', 'border_color', 'background_color'] as $field) {
				if (array_key_exists($field, $row)) {
					$color = strtoupper(ltrim((string) $row[$field], '#'));

					if ($color === '') {
						unset($row[$field]);
					}
					elseif (preg_match('/^[0-9A-F]{6}$/', $color)) {
						$row[$field] = $color;
					}
					else {
						unset($row[$field]);
					}
				}
			}

			$row['type'] = isset($row['type']) ? (int) $row['type'] : 0;

			if (!in_array($row['type'], [0, 1, 2], true)) {
				$row['type'] = 0;
			}

			// For lines, x,y is the start and width,height the absolute end coordinates (same as native sysmap_shape),
			// 0 is allowed; rectangles/ellipses have a minimum width and height of 1.
			if ($row['type'] === 2) {
				$row['width'] = isset($row['width']) && $row['width'] >= 0 ? (int) $row['width'] : 0;
				$row['height'] = isset($row['height']) && $row['height'] >= 0 ? (int) $row['height'] : 0;
			}
			else {
				$row['width'] = isset($row['width']) && $row['width'] > 0 ? (int) $row['width'] : 200;
				$row['height'] = isset($row['height']) && $row['height'] > 0 ? (int) $row['height'] : 100;
			}

			// sysmap_shape.text is a short text column; overlong values are rejected by the DB layer
			$row['text'] = isset($row['text']) ? mb_substr((string) $row['text'], 0, 255) : '';

			$result[] = $row;
		}

		return $result;
	}
}
