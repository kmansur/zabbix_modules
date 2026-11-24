<?php declare(strict_types = 1);

namespace Modules\Topomap\Services;

use Modules\Topomap\Lib\LanguageManager;

/**
 * Reads the LLDP / CDP / EDP neighbor tables of switches over SNMP, for auto discovery.
 *
 * Why SNMP and not the Zabbix API: Zabbix itself does not store neighbor relations; LLDP neighbors only exist on the devices.
 * The server's PHP already has the snmp extension (`php -m | grep snmp`), with responses measured in the 20 ms range,
 * so the net-snmp command-line tools are not needed.
 *
 * No method throws: a single device failure is only recorded in error, so one unreachable switch cannot break the whole discovery round.
 */
class LldpDiscovery {

	/**
	 * lldpRemTable root.
	 *
	 * Note: **never walk its ancestor `1.0.8802.1.1.2.1` (the whole lldpMIB)**.
	 * lldpExtensions (…2.1.5, i.e. LLDP-MED) in that subtree makes net-snmp's GETBULK hang forever on H3C Comware
	 * — in tests `snmp2_real_walk('1.0.8802.1.1.2.1')` could not even be killed with `timeout -s KILL 30`,
	 * and the whole discovery request was held up by that one device (symptom: the log stops at "Scanning xxx …" and never moves).
	 * The narrow tables below, walked separately, all return reliably in tens of milliseconds (remTable 144 rows / 0.53 s).
	 */
	private const OID_LLDP_REM = '1.0.8802.1.1.2.1.4.1.1';

	/** lldpLocPortId of lldpLocPortTable (local port name, e.g. GigabitEthernet1/0/1) */
	private const OID_LLDP_LOC_PORT_ID = '1.0.8802.1.1.2.1.3.7.1.3';

	/** lldpLocSysName (scalar, local system name) */
	private const OID_LLDP_LOC_SYSNAME = '1.0.8802.1.1.2.1.3.3.0';

	/** lldpRemManAddrTable (the neighbor management address is hidden in the index; the value is only the interface subtype) */
	private const OID_LLDP_REM_MAN_ADDR = '1.0.8802.1.1.2.1.4.2.1.3';

	/** CISCO-CDP-MIB cdpCacheTable root */
	private const OID_CDP_CACHE = '1.3.6.1.4.1.9.9.23.1.2.1.1';

	/** EXTREME-EDP-MIB extremeEdpNeighborTable: neighbor name / neighbor port */
	private const OID_EDP_NEIGHBOR_NAME = '1.3.6.1.4.1.1916.1.13.2.1.3';
	private const OID_EDP_NEIGHBOR_PORT = '1.3.6.1.4.1.1916.1.13.2.1.6';

	/** IF-MIB ifName: EDP only gives the local port ifIndex, which must be mapped to a readable interface name */
	private const OID_IF_NAME = '1.3.6.1.2.1.31.1.1.1.1';

	/**
	 * lldpRemTable column number → field name (per the standard lldpRemEntry definition).
	 *
	 * The neighbor port prefers lldpRemPortId (column 7): switches / routers report interface names such as
	 * "GigabitEthernet1/0/1", which are more useful than lldpRemPortDesc;
	 * only when PortId is a MAC (endpoint NIC) does it fall back to PortDesc.
	 */
	private const LLDP_COLUMNS = [
		5 => 'chassis',
		7 => 'port_id',
		8 => 'port_desc',
		9 => 'sysname',
		10 => 'sysdesc'
	];

	/** cdpCacheTable column number → field name */
	private const CDP_COLUMNS = [
		3 => 'address',
		6 => 'device_id',
		7 => 'device_port',
		8 => 'platform'
	];

	/** Single SNMP timeout (microseconds). An unreachable device gives up after 1.2 s so the whole discovery round is not held up. */
	/** @var int */ private $timeout_us;

	/** Retries: 0; a switch that does not answer really does not answer, and retrying only wastes time */
	/** @var int */ private $retries = 0;

	/**
	 * Minimum interval between two SNMP requests to the same device (microseconds).
	 * Huawei / H3C agents rate-limit requests: 5 or 6 consecutive walks lead straight to
	 * "No response from x.x.x.x", showing up as "sometimes discovered, sometimes no neighbors at all".
	 */
	private const WALK_GAP_US = 70000;

	/** @var float */ private $last_request_at = 0.0;

	/**
	 * How many SNMP responses succeeded in this scanHost round.
	 * Used to tell "the device returned an empty table (LLDP not enabled)" from "the device did not respond at all (timeout/rate limit)" —
	 * the former is a device configuration issue, the latter should tell the user to retry later; they must not be confused.
	 */
	/** @var int */ private $walk_answered = 0;

	public function __construct(int $timeout_ms = 1200) {
		$this->timeout_us = max(200, $timeout_ms) * 1000;
	}

	/**
	 * Scan one SNMP device and return its neighbor list.
	 *
	 * @param array $host  Zabbix host (with interfaces and the resolved community string)
	 *
	 * @return array{ok: bool, error: string, sysname: string, ifnames: array, neighbors: array}
	 */
	public function scanHost(array $host): array {
		$empty = ['ok' => false, 'error' => '', 'sysname' => '', 'neighbors' => []];

		if (empty($host['snmp_ip'])) {
			return array_merge($empty, ['error' => LanguageManager::t('No SNMP interface')]);
		}

		if (!extension_loaded('snmp') && !function_exists('snmp2_real_walk')) {
			return array_merge($empty, ['error' => LanguageManager::t('PHP snmp extension is missing')]);
		}

		$protocols = isset($host['protocols']) && is_array($host['protocols']) ? $host['protocols'] : ['LLDP'];
		$sysname = '';
		$neighbors = [];

		$this->walk_answered = 0;
		$this->useNumericOids();

		// Each device tries one protocol at a time in priority order: LLDP → CDP → EDP, stopping at the first hit.
		// This is not laziness but necessity: agents rate-limit consecutive requests, and a few extra calls make the whole device stop responding,
		// and enabling all three protocols would also yield duplicate neighbors.
		if (in_array('LLDP', $protocols, true)) {
			$lldp = $this->scanLldp($host);
			$neighbors = $lldp['neighbors'];
			$sysname = $lldp['sysname'];
		}

		// CDP is checked only when LLDP has no result: old Cisco devices often only run CDP.
		if (!$neighbors && in_array('CDP', $protocols, true)) {
			$neighbors = $this->scanCdp($host);
		}

		// EDP comes last: only Extreme devices use it (private MIB 1916; other devices return an empty table immediately).
		if (!$neighbors && in_array('EDP', $protocols, true)) {
			$neighbors = $this->scanEdp($host);
		}

		// No response at all = device unreachable / rate-limited, not "LLDP not enabled"; the hint must point the right way
		if (!$neighbors && $this->walk_answered === 0) {
			return [
				'ok' => false,
				'error' => LanguageManager::t('SNMP did not respond (timeout or rate limiting); please retry later'),
				'sysname' => $sysname,
				'neighbors' => []
			];
		}

		return [
			'ok' => true,
			'error' => '',
			'sysname' => $sysname,
			'neighbors' => $neighbors
		];
	}

	// ------------------------------------------------------------------
	// LLDP
	// ------------------------------------------------------------------

	/**
	 * Walk separate tables to get neighbor relations: lldpRemTable (neighbors) + lldpLocPortTable (local port names)
	 * + lldpRemManAddrTable (neighbor management addresses) + lldpLocSysName (local system name).
	 *
	 * **Never walk the whole `1.0.8802.1.1.2.1` subtree**; see the comment on OID_LLDP_REM:
	 * LLDP-MED in that subtree hangs GETBULK. With separate tables each device takes 4 requests,
	 * about 1 s with neighbors (including request intervals), far better than hanging in one go.
	 *
	 * @return array{neighbors: array, sysname: string}
	 */
	private function scanLldp(array $host): array {
		// An empty table is checked again: devices return empty tables when rate-limited (see the comment in walk())
		$tree = $this->walkMap(self::OID_LLDP_REM, $host, true);

		if (!$tree) {
			return ['neighbors' => [], 'sysname' => $this->readSysname($host)];
		}

		// An empty table is checked again: when a device is rate-limited, lldpLocPortId "normally" returns an empty table (see the comment in walk()).
		// On the busiest core switch tested (29 neighbors, earlier walks pushed the agent into rate limiting), this step
		// found no local port names at all, so every local port degraded to raw Comware numbers such as 65542.
		$locports = $this->walkMap(self::OID_LLDP_LOC_PORT_ID, $host, true);
		$sysname = $this->readSysname($host);
		$manaddrs = [];
		$manaddrs_by_port = [];
		// IF-MIB ifName is walked on demand only when lldpLocPortId is missing (one more SNMP request)
		$ifnames = null;

		// The address is hidden in the index of lldpRemManAddrTable:
		// <timeMark>.<local port>.<remIndex>.<subtype>.<len>.<addr...>
		// The value is only the "interface subtype"; the address can only be parsed from the index.
		//
		// Prefer associating by the full index of the rem entry (the correspondence intended by the RFC);
		// on devices such as Huawei the lldpRemIndex of the two tables does not match, so fall back to "by local port".
		// Keys used to be built by port only; several rem entries under one port overwrote each other and addresses got mixed up.
		foreach ($this->walkMap(self::OID_LLDP_REM_MAN_ADDR, $host) as $suffix => $value) {
			$parsed = $this->parseManAddr((string) $suffix);

			if (!$parsed) {
				continue;
			}

			if (!isset($manaddrs[$parsed['key']])) {
				$manaddrs[$parsed['key']] = $parsed['ip'];
			}

			if (!isset($manaddrs_by_port[$parsed['port']])) {
				$manaddrs_by_port[$parsed['port']] = $parsed['ip'];
			}
		}

		$rows = [];

		foreach ($tree as $suffix => $value) {
			// lldpRemTable: <column>.<timeMark>.<local port>.<remIndex>
			$parts = explode('.', (string) $suffix);

			if (count($parts) < 4) {
				continue;
			}

			$field = self::LLDP_COLUMNS[(int) $parts[0]] ?? '';

			if ($field === '') {
				continue;
			}

			$rows[implode('.', array_slice($parts, 1))][$field] = $value;
		}

		if (!$rows) {
			return ['neighbors' => [], 'sysname' => $sysname];
		}

		$result = [];
		$seen = [];

		foreach ($rows as $key => $row) {
			$sysname_of_neighbor = isset($row['sysname']) ? trim((string) $row['sysname']) : '';

			if ($sysname_of_neighbor === '' && empty($row['chassis'])) {
				continue;
			}

			$parts = explode('.', $key);
			$port_num = count($parts) >= 2 ? (int) $parts[count($parts) - 2] : 0;

			// Local port name: lldpLocPortId is the most accurate; otherwise fall back to IF-MIB ifName / Comware decoding,
			// see resolveLocalPortName().
			$local_port = $this->resolveLocalPortName($port_num, $locports, $host, $ifnames);

			// One switch may report two sets of localPortNum (stacking / dual supervisors: 65542 and 131078 are both ge1_0_6);
			// identical port names are treated as the same neighbor, otherwise the topology shows two duplicate links.
			//
			// The dedup key must include the neighbor's own identifier: endpoints / APs often only report a chassisId (MAC)
			// with an empty sysName; with several endpoints on one port (three on 1/0/12, five on 1/0/22),
			// using only "empty name + port" would merge them into one, losing most of the "include unmanaged devices" results.
			$ident = $sysname_of_neighbor;

			if ($ident === '') {
				$ident = isset($row['chassis']) ? trim((string) $row['chassis']) : '';
			}

			if ($ident === '') {
				$ident = isset($row['port_id']) ? trim((string) $row['port_id']) : '';
			}

			$uniq = mb_strtolower($ident).'|'.$local_port;

			if (isset($seen[$uniq])) {
				continue;
			}

			$seen[$uniq] = true;

			// The neighbor port name prefers lldpRemPortId; only when it is a MAC (endpoint NIC) does it fall back to PortDesc,
			// otherwise a MAC address would be shown on the link.
			$raw_port_id = isset($row['port_id']) ? trim((string) $row['port_id']) : '';
			$port_id = $raw_port_id;
			$port_desc = isset($row['port_desc']) ? trim((string) $row['port_desc']) : '';

			// A MAC as the remote port ⇒ the other side is an endpoint NIC (PC / printer / AP), not a switch port.
			// This is exactly what aiops' is_distribution checks, and it is used later to choose the Workstation icon.
			$port_mac = self::normalizeMac($raw_port_id) !== '';

			if ($port_id === '' || $port_mac) {
				$port_id = $port_desc;
			}

			// Neighbor management address: by the full index of the rem entry first, falling back to the local port
			// (see the comment above: on some devices the lldpRemIndex of the two tables does not match).
			$neighbor_ip = isset($manaddrs[$key])
				? $manaddrs[$key]
				: (isset($manaddrs_by_port[(string) $port_num]) ? $manaddrs_by_port[(string) $port_num] : '');

			$result[] = [
				'protocol' => 'LLDP',
				'sysname' => $sysname_of_neighbor,
				'chassis' => isset($row['chassis']) ? (string) $row['chassis'] : '',
				'port_id' => $port_id,
				'port_desc' => $port_desc,
				'port_mac' => $port_mac,
				'sysdesc' => isset($row['sysdesc']) ? (string) $row['sysdesc'] : '',
				'local_port' => $local_port,
				'local_port_num' => $port_num,
				'ip' => $neighbor_ip
			];
		}

		return ['neighbors' => $result, 'sysname' => $sysname];
	}

	/**
	 * Resolve the local port name, in order of priority:
	 *   1) lldpLocPortId (most accurate; Huawei / H3C give names such as ge0_0_21)
	 *   2) IF-MIB ifName: use the Comware-encoded port number directly as ifIndex (walked on demand, result reused across neighbors)
	 *   3) Comware decoding: lldpRemLocalPortNum is "slot<<16 | port", restored as GigabitEthernet{slot}/0/{port}
	 *   4) only if all fail, fall back to the raw number (old behavior)
	 *
	 * Why a readable name is essential: the local port name is drawn on the link and is also the key for "bidirectional discovery dedup" and
	 * "trigger matching by port". A raw number (65547) does not match Gi1/0/11 reported by the other end,
	 * so bidirectional duplicates would not merge and source-end triggers would not match (symptom: triggers bound at both ends while only one link is visible).
	 *
	 * @param array|null $ifnames passed by reference; ifName is walked only once per device
	 */
	private function resolveLocalPortName(int $port_num, array $locports, array $host, ?array &$ifnames): string {
		if ($port_num <= 0) {
			return '';
		}

		$key = (string) $port_num;
		$name = isset($locports[$key]) ? trim((string) $locports[$key]) : '';

		// Some H3C ports report the **port MAC** (70:81:85:2C:05:E1) in lldpLocPortId instead of the interface name.
		// These must be treated as "not found" and fall back to IF-MIB ifName, otherwise a MAC is shown on the link
		// and trigger matching by port never succeeds (consistent with aiops' readableName check).
		if ($name !== '' && self::normalizeMac($name) === '') {
			return $name;
		}

		if ($ifnames === null) {
			$ifnames = $this->walkMap(self::OID_IF_NAME, $host);
		}

		$name = isset($ifnames[$key]) ? trim((string) $ifnames[$key]) : '';

		if ($name !== '') {
			return $name;
		}

		$decoded = self::decodeComwarePortName($port_num);

		return $decoded !== '' ? $decoded : $key;
	}

	/**
	 * Comware lldpRemLocalPortNum = slot<<16 | port (65547 = slot 1 port 11, 131083 = slot 2 port 11).
	 * Restored as GigabitEthernet{slot}/0/{port}; values not in this encoding (below 65536) return an empty string.
	 */
	private static function decodeComwarePortName(int $port_num): string {
		if ($port_num < 65536) {
			return '';
		}

		$slot = intdiv($port_num, 65536);
		$port = $port_num % 65536;

		if ($slot <= 0 || $port <= 0) {
			return '';
		}

		return 'GigabitEthernet'.$slot.'/0/'.$port;
	}

	/** lldpLocSysName is a scalar; a walk returns only one row, so take the first non-empty value. */
	private function readSysname(array $host): string {
		foreach ($this->walkMap(self::OID_LLDP_LOC_SYSNAME, $host) as $value) {
			$value = trim((string) $value);

			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	/**
	 * lldpRemManAddrTable index: timeMark.localPortNum.remIndex.addrSubtype.len.addr...
	 *
	 * The address is a variable-length octet string: devices such as Huawei put "192.168.1.1" in as ASCII (len=11),
	 * while others give 4 binary bytes (len=4). Both must be recognized.
	 */
	private function parseManAddr(string $suffix): array {
		$parts = explode('.', $suffix);

		if (count($parts) < 6) {
			return [];
		}

		$subtype = (int) $parts[3];
		$len = (int) $parts[4];
		$bytes = array_slice($parts, 5, $len);

		if (count($bytes) !== $len || $len === 0) {
			return [];
		}

		$key = implode('.', array_slice($parts, 0, 3));
		$port = (string) $parts[1];

		if ($subtype === 1) {
			// 4 binary bytes
			if ($len === 4) {
				return ['key' => $key, 'port' => $port, 'ip' => implode('.', array_map('intval', $bytes))];
			}

			// ASCII dotted decimal
			$text = '';

			foreach ($bytes as $byte) {
				$text .= chr((int) $byte);
			}

			if (filter_var($text, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				return ['key' => $key, 'port' => $port, 'ip' => $text];
			}
		}

		return [];
	}

	// ------------------------------------------------------------------
	// CDP
	// ------------------------------------------------------------------

	private function scanCdp(array $host): array {
		// Likewise walk the whole cdpCacheTable once instead of requesting column by column (see the comment in scanLldp)
		$cache = $this->walkMap(self::OID_CDP_CACHE, $host);

		if (!$cache) {
			return [];
		}

		$rows = [];

		foreach ($cache as $suffix => $value) {
			$parts = explode('.', $suffix);

			if (count($parts) < 3) {
				continue;
			}

			$column = (int) array_shift($parts);
			$field = self::CDP_COLUMNS[$column] ?? '';

			if ($field === '') {
				continue;
			}

			$rows[implode('.', $parts)][$field] = $value;
		}

		$result = [];

		foreach ($rows as $suffix => $row) {
			$device_id = isset($row['device_id']) ? trim((string) $row['device_id']) : '';

			if ($device_id === '') {
				continue;
			}

			$parts = explode('.', $suffix);
			$ifindex = (int) $parts[0];

			$result[] = [
				'protocol' => 'CDP',
				'sysname' => $device_id,
				'chassis' => '',
				'port_id' => isset($row['device_port']) ? (string) $row['device_port'] : '',
				'port_desc' => '',
				'sysdesc' => isset($row['platform']) ? (string) $row['platform'] : '',
				'local_port' => (string) $ifindex,
				'local_port_num' => $ifindex,
				'ip' => isset($row['address']) ? $this->ipFromCdpAddress((string) $row['address']) : ''
			];
		}

		return $result;
	}

	/**
	 * cdpCacheAddress is a 4-byte IPv4 octet string, which the SNMP extension usually returns as
	 * "Hex-STRING: 0A 00 00 01" or "0.0.0.0".
	 */
	private function ipFromCdpAddress(string $raw): string {
		$raw = trim($raw);

		if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $raw)) {
			return $raw;
		}

		if (preg_match('/^([0-9a-f]{2}[\s:]){3,15}[0-9a-f]{2}$/i', $raw)) {
			$bytes = preg_split('/[\s:]+/', $raw);

			if (count($bytes) === 4) {
				return implode('.', array_map(function ($b) { return (string) hexdec($b); }, $bytes));
			}
		}

		return '';
	}

	// ------------------------------------------------------------------
	// EDP (Extreme Discovery Protocol)
	// ------------------------------------------------------------------

	/**
	 * EXTREME-EDP-MIB `extremeEdpNeighborTable` (1.3.6.1.4.1.1916.1.13.2.1):
	 *   .1 extremeEdpPortIfIndex (local port ifIndex, also the first part of the index)
	 *   .3 extremeEdpNeighborName  ← neighbor name
	 *   .6 extremeEdpNeighborPort  ← neighbor port
	 *
	 * The index is `<local port ifIndex>.<neighbor id>`, shared by both tables; the table has no neighbor IP
	 * (EDP does not report addresses), and the local port can only be mapped to an interface name via IF-MIB ifName using the ifIndex.
	 */
	private function scanEdp(array $host): array {
		$names = $this->walkMap(self::OID_EDP_NEIGHBOR_NAME, $host);

		if (!$names) {
			return [];
		}

		$ports = $this->walkMap(self::OID_EDP_NEIGHBOR_PORT, $host);
		$ifnames = $this->walkMap(self::OID_IF_NAME, $host);
		$result = [];

		foreach ($names as $suffix => $name) {
			$name = trim((string) $name);
			$ifindex = explode('.', (string) $suffix)[0] ?? '';

			if ($name === '' || $ifindex === '') {
				continue;
			}

			$result[] = [
				'protocol' => 'EDP',
				'sysname' => $name,
				'chassis' => '',
				'port_id' => isset($ports[$suffix]) ? (string) $ports[$suffix] : '',
				'port_desc' => '',
				'sysdesc' => '',
				'local_port' => isset($ifnames[$ifindex]) ? (string) $ifnames[$ifindex] : (string) $ifindex,
				'local_port_num' => (int) $ifindex,
				'ip' => ''
			];
		}

		return $result;
	}

	// ------------------------------------------------------------------
	// SNMP basics
	// ------------------------------------------------------------------

	/**
	 * One real_walk, returning "index suffix => cleaned value".
	 * Failures (timeout, OID not supported by the device) always return an empty array.
	 *
	 * Note that the key format depends on the device: `1.0.8802...8.0.65542.1`, `iso.0.8802...`,
	 * `IF-MIB::ifName.4` have all been seen, so it cannot be assumed to start with a numeric OID.
	 */
	private function walkMap(string $oid, array $host, bool $retry_empty = false): array {
		$raw = $this->walk($oid, $host, $retry_empty);
		$result = [];

		foreach ($raw as $key => $value) {
			$suffix = $this->suffixOf((string) $key, $oid);

			if ($suffix === '') {
				continue;
			}

			$result[$suffix] = $this->clean($value);
		}

		return $result;
	}

	/**
	 * Extract the index part belonging to $oid from a real_walk key.
	 * Locate it by numeric OID first; the named form (IF-MIB::ifName.4) degrades to "take the last part",
	 * because tables of this kind (ifName / ifDescr) have a single numeric index anyway.
	 */
	private function suffixOf(string $key, string $oid): string {
		$candidates = [$key];

		if (strpos($key, 'iso.') === 0) {
			// iso.0.8802... and 1.0.8802... are the same OID
			$candidates[] = '1.'.substr($key, 4);
		}

		foreach ($candidates as $candidate) {
			$pos = strpos($candidate, $oid.'.');

			if ($pos !== false) {
				return substr($candidate, $pos + strlen($oid) + 1);
			}
		}

		if (preg_match('/([0-9]+)$/', $key, $match)) {
			return $match[1];
		}

		return '';
	}

	/**
	 * @param bool $retry_empty whether an empty table is also retried (rate-limited devices "normally" return an empty table; only the LLDP subtree needs this)
	 */
	private function walk(string $oid, array $host, bool $retry_empty = false): array {
		$this->throttle();

		$result = $this->walkOnce($oid, $host);

		// Both kinds of failure are retried with backoff:
		//   1) null — timeout/packet loss. In tests, right after php-fpm restarts, the first SNMP request in a new worker
		//      often gets no answer, while the next one works.
		//   2) empty table — when rate-limited, Huawei snmpd "normally" returns an empty table instead of an error,
		//      showing up as the same switch having "sometimes 24 neighbors, sometimes 0".
		// A single retry is not enough; a cold process combined with rate limiting still comes back empty-handed, so there are two backoff steps.
		foreach ([350000, 900000] as $gap) {
			if (is_array($result) && ($result || !$retry_empty)) {
				break;
			}

			usleep($gap);
			$this->last_request_at = microtime(true);
			$result = $this->walkOnce($oid, $host);
		}

		return is_array($result) ? $result : [];
	}

	/**
	 * Keep 70 ms between two requests to the same device.
	 * Huawei / H3C snmpd are sensitive to consecutive requests and stop responding after 5 or 6 walks in a row,
	 * with the result that "the same switch sometimes shows 24 neighbors and sometimes 0".
	 */
	private function throttle(): void {
		$gap = self::WALK_GAP_US - (int) ((microtime(true) - $this->last_request_at) * 1000000);

		if ($gap > 0) {
			usleep($gap);
		}

		$this->last_request_at = microtime(true);
	}

	/**
	 * Make the SNMP extension output keys as pure numeric OIDs (`.1.3.6.1.2.1.31.1.1.1.1.7`).
	 *
	 * The default output mixes "MIB name + index" such as `IF-MIB::ifName.7` / `iso.0.8802...`,
	 * and when the index has more than one part (EDP's `<ifIndex>.<neighbor id>`, lldpRem's
	 * `<column>.<timeMark>.<port>.<index>`) it could only be guessed, and parsing would be wrong.
	 * In numeric form suffixOf() can cut out the part after the OID precisely.
	 */
	private function useNumericOids(): void {
		if (function_exists('snmp_set_oid_output_format') && defined('SNMP_OID_OUTPUT_NUMERIC')) {
			@snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
		}
	}

	/**
	 * @return array|null an array on success, null on failure (walk() decides whether to retry)
	 */
	private function walkOnce(string $oid, array $host): ?array {
		$version = (int) ($host['snmp_version'] ?? 2);
		$ip = (string) ($host['snmp_ip'] ?? '');

		try {
			if ($version === 3) {
				$v3 = $host['snmp_v3'] ?? [];

				$result = @snmp3_real_walk(
					$ip,
					(string) ($v3['sec_name'] ?? ''),
					(string) ($v3['sec_level'] ?? 'authNoPriv'),
					(string) ($v3['auth_protocol'] ?? 'SHA'),
					(string) ($v3['auth_passphrase'] ?? ''),
					(string) ($v3['priv_protocol'] ?? 'AES'),
					(string) ($v3['priv_passphrase'] ?? ''),
					$oid,
					$this->timeout_us,
					$this->retries
				);
			}
			elseif ($version === 1) {
				$result = @snmprealwalk($ip, (string) ($host['community'] ?? 'public'), $oid,
					$this->timeout_us, $this->retries);
			}
			else {
				// @ suppresses PHP warnings: when a device is unreachable the extension spams "No response from x.x.x.x",
				// dozens of such warnings per second would flood nginx's error.log. Failures are detected by the false return value.
				$result = @snmp2_real_walk($ip, (string) ($host['community'] ?? 'public'), $oid,
					$this->timeout_us, $this->retries);
			}
		}
		catch (\Throwable $e) {
			// When a device is unreachable the extension throws; treat it as "no data"
			return null;
		}

		if (is_array($result)) {
			$this->walk_answered++;

			return $result;
		}

		return null;
	}

	/**
	 * Strip the type prefix the snmp extension adds to values and turn Hex-STRING back into readable text.
	 * Non-ASCII device names (common on Huawei / H3C) are returned as Hex-STRING and would be garbled without decoding.
	 */
	private function clean($raw): string {
		$raw = is_string($raw) ? $raw : (string) $raw;
		$raw = trim($raw);

		if (preg_match('/^Hex-STRING:\s*(.+)$/is', $raw, $match)) {
			return $this->hexToString(trim($match[1]));
		}

		if (preg_match('/^(STRING|INTEGER|OID|Gauge32|Counter32|Counter64|Timeticks|IpAddress|Opaque|BITS|Network Address):\s*(.*)$/is', $raw, $match)) {
			$raw = trim($match[2]);
		}

		return trim($raw, " \"\r\n\t");
	}

	/**
	 * Hex-STRING → text: decode as UTF-8 when possible (non-ASCII names), otherwise show it MAC-style.
	 */
	private function hexToString(string $hex): string {
		$hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);

		if ($hex === '' || strlen($hex) % 2 !== 0) {
			return '';
		}

		$bytes = hex2bin($hex);

		if ($bytes === false) {
			return '';
		}

		// Only printable ASCII / UTF-8 counts as text
		if (preg_match('//u', $bytes)) {
			$text = trim($bytes, "\x00");

			if ($text !== '' && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
				return $text;
			}
		}

		$pairs = str_split($hex, 2);

		return implode(':', $pairs);
	}

	/**
	 * Parse MACs appearing in LLDP/CDP, used as a fallback match (when there is neither device name nor IP).
	 */
	public static function normalizeMac(string $value): string {
		$hex = preg_replace('/[^0-9a-fA-F]/', '', $value);

		return strlen($hex) === 12 ? strtolower($hex) : '';
	}
}
