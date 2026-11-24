<?php
/**
 * Host-rack manager
 * Manages the association between hosts and rack positions (through Zabbix host tags)
 */

namespace Modules\ZabbixRack\Lib;

require_once __DIR__ . '/ZabbixVersion.php';
require_once __DIR__ . '/RackConfig.php';

class HostRackManager {
    const CUSTOM_HOST_PREFIX = 'custom:';
    
    // Tag name constants
    const TAG_ROOM = 'rack_room';
    const TAG_RACK = 'rack_name';
    const TAG_U_START = 'rack_u_start';
    const TAG_U_END = 'rack_u_end';
    const TAG_SIDE = 'rack_side';
    const SIDE_FRONT = 'front';
    const SIDE_BACK = 'back';

    /** @var string */
    private static $lastError = '';

    /** @var array<int, string> */
    private static $rackTagNames = [
        self::TAG_ROOM,
        self::TAG_RACK,
        self::TAG_U_START,
        self::TAG_U_END,
        self::TAG_SIDE,
    ];

    public static function getLastError(): string {
        return self::$lastError;
    }

    /**
     * Normalize the rack front/rear; no tag or anything other than back counts as front.
     *
     * @param mixed $value
     */
    public static function normalizeSide($value): string {
        $value = strtolower(trim((string) $value));
        if ($value === self::SIDE_BACK || $value === 'rear') {
            return self::SIDE_BACK;
        }

        return self::SIDE_FRONT;
    }

    /**
     * @param array<int, array<string, mixed>> $tags
     * @return array{room:string,rack:string,u_start:?int,u_end:?int,side:string}
     */
    public static function parseRackTagsFromList(array $tags): array {
        $result = [
            'room'    => '',
            'rack'    => '',
            'u_start' => null,
            'u_end'   => null,
            'side'    => self::SIDE_FRONT,
        ];

        foreach ($tags as $tag) {
            $name = (string) ($tag['tag'] ?? '');
            $value = $tag['value'] ?? '';
            switch ($name) {
                case self::TAG_ROOM:
                    $result['room'] = (string) $value;
                    break;
                case self::TAG_RACK:
                    $result['rack'] = (string) $value;
                    break;
                case self::TAG_U_START:
                    $result['u_start'] = ($value !== '' && $value !== null) ? (int) $value : null;
                    break;
                case self::TAG_U_END:
                    $result['u_end'] = ($value !== '' && $value !== null) ? (int) $value : null;
                    break;
                case self::TAG_SIDE:
                    $result['side'] = self::normalizeSide($value);
                    break;
            }
        }

        return $result;
    }

    public static function sideMatches(string $hostSide, string $viewSide): bool {
        return self::normalizeSide($hostSide) === self::normalizeSide($viewSide);
    }

    private static function setLastError(string $message): void {
        self::$lastError = $message;
    }

    /**
     * host.update only accepts tag/value; extended fields such as automatic must be removed (Zabbix 7+).
     *
     * @param array<int, array<string, mixed>> $tags
     * @return array<int, array{tag:string,value:string}>
     */
    private static function sanitizeTagsForUpdate(array $tags): array {
        $result = [];

        foreach ($tags as $tag) {
            $name = trim((string) ($tag['tag'] ?? ''));
            if ($name === '') {
                continue;
            }
            $result[] = [
                'tag'   => $name,
                'value' => (string) ($tag['value'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $tags
     */
    private static function updateHostTags(string $hostId, array $tags): bool {
        self::setLastError('');

        try {
            $result = \API::Host()->update([
                'hostid' => $hostId,
                'tags'   => self::sanitizeTagsForUpdate($tags),
            ]);

            if (empty($result)) {
                self::setLastError('Host update returned empty result');
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            self::setLastError($message);
            error_log('HostRackManager: update tags failed for host ' . $hostId . ': ' . $message);
            return false;
        }
    }
    
    /**
     * Get the host group query parameter name (compatible with Zabbix 6/7)
     * Zabbix 7.0+: selectHostGroups
     * Zabbix 6.x:  selectGroups
     */
    private static function getSelectGroupsParam(): string {
        return ZabbixVersion::isVersion7() ? 'selectHostGroups' : 'selectGroups';
    }
    
    /**
     * Get the tag operator constant value (compatible with different Zabbix versions)
     */
    private static function getTagsOperatorEqual(): int {
        if (defined('TAGS_OPERATOR_EQUAL')) {
            return TAGS_OPERATOR_EQUAL;
        }
        return 0; // Default value
    }
    
    /**
     * Get the tag evaluation type constant value (compatible with different Zabbix versions)
     */
    private static function getTagEvalTypeAndOr(): int {
        if (defined('TAG_EVAL_TYPE_AND_OR')) {
            return TAG_EVAL_TYPE_AND_OR;
        }
        return 0; // Default AND/OR
    }
    
    /**
     * Get all hosts in a rack
     * 
     * @param string $roomName room name
     * @param string $rackName rack name
     * @param string $side front / back
     * @return array host list, including position information
     */
    public static function getHostsInRack(
        string $roomName,
        string $rackName,
        string $side = self::SIDE_FRONT
    ): array {
        $side = self::normalizeSide($side);

        $hosts = \API::Host()->get([
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectTags' => 'extend',
            self::getSelectGroupsParam() => ['groupid', 'name'],
            'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
            'tags' => [
                ['tag' => self::TAG_ROOM, 'value' => $roomName, 'operator' => self::getTagsOperatorEqual()],
                ['tag' => self::TAG_RACK, 'value' => $rackName, 'operator' => self::getTagsOperatorEqual()]
            ],
            'evaltype' => self::getTagEvalTypeAndOr()
        ]);
        
        $result = [];
        foreach ($hosts as $host) {
            $parsed = self::parseRackTagsFromList($host['tags'] ?? []);
            if (!self::sideMatches($parsed['side'], $side)) {
                continue;
            }

            $uStart = $parsed['u_start'];
            $uEnd = $parsed['u_end'];
            
            // Get the main interface IP
            $mainIp = '';
            foreach ($host['interfaces'] as $interface) {
                if ($interface['main'] == 1) {
                    $mainIp = $interface['ip'] ?: $interface['dns'];
                    break;
                }
            }
            
            // Get host group names - compatible with Zabbix 6 (groups) and Zabbix 7 (hostgroups)
            $groups = [];
            $hostGroups = isset($host['groups']) ? $host['groups'] : (isset($host['hostgroups']) ? $host['hostgroups'] : []);
            foreach ($hostGroups as $group) {
                $groups[] = $group['name'];
            }
            
            $result[] = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'u_start' => $uStart,
                'u_end' => $uEnd,
                'u_height' => ($uStart && $uEnd) ? ($uEnd - $uStart + 1) : 1,
                'main_ip' => $mainIp,
                'groups' => $groups,
                'side' => $parsed['side'],
                'custom' => false,
            ];
        }

        $roomId = '';
        $rackId = '';
        foreach (RackConfig::getRooms() as $room) {
            if ((string) ($room['name'] ?? '') === $roomName) {
                $roomId = (string) $room['id'];
                break;
            }
        }
        foreach (RackConfig::getRacks($roomId !== '' ? $roomId : null) as $rack) {
            if ((string) ($rack['name'] ?? '') === $rackName) {
                $rackId = (string) $rack['id'];
                break;
            }
        }

        if ($roomId !== '' && $rackId !== '') {
            foreach (RackConfig::getCustomHosts($roomId, $rackId) as $host) {
                $hostSide = self::normalizeSide($host['side'] ?? self::SIDE_FRONT);
                if (!self::sideMatches($hostSide, $side)) {
                    continue;
                }
                $uStart = (int) ($host['u_start'] ?? 0);
                $uEnd = (int) ($host['u_end'] ?? 0);
                $result[] = [
                    'hostid' => self::CUSTOM_HOST_PREFIX . (string) $host['id'],
                    'host' => (string) ($host['name'] ?? ''),
                    'name' => (string) ($host['name'] ?? ''),
                    'status' => 0,
                    'u_start' => $uStart,
                    'u_end' => $uEnd,
                    'u_height' => ($uStart > 0 && $uEnd >= $uStart) ? ($uEnd - $uStart + 1) : 1,
                    'main_ip' => (string) ($host['ip'] ?? ''),
                    'groups' => [],
                    'side' => $hostSide,
                    'custom' => true,
                    'problem_count' => 0,
                    'max_severity' => -1,
                ];
            }
        }
        
        // Sort by U position
        usort($result, function($a, $b) {
            return ($b['u_start'] ?? 0) - ($a['u_start'] ?? 0);
        });
        
        return $result;
    }

    public static function isCustomHostId(string $hostId): bool {
        return strpos($hostId, self::CUSTOM_HOST_PREFIX) === 0;
    }

    public static function getCustomHostId(string $hostId): string {
        return self::isCustomHostId($hostId)
            ? substr($hostId, strlen(self::CUSTOM_HOST_PREFIX))
            : $hostId;
    }

    public static function assignCustomHost(
        string $id,
        string $name,
        string $ip,
        string $roomId,
        string $rackId,
        int $uStart,
        int $uEnd,
        string $side = self::SIDE_FRONT
    ): bool {
        self::setLastError('');
        $name = trim($name);
        $ip = trim($ip);
        if ($name === '' || $ip === '') {
            self::setLastError('Custom host name and IP are required');
            return false;
        }

        return RackConfig::saveCustomHost([
            'id' => self::getCustomHostId($id),
            'name' => $name,
            'ip' => $ip,
            'room_id' => $roomId,
            'rack_id' => $rackId,
            'u_start' => $uStart,
            'u_end' => $uEnd,
            'side' => self::normalizeSide($side),
        ]);
    }

    public static function removeCustomHost(string $hostId): bool {
        return RackConfig::deleteCustomHost(self::getCustomHostId($hostId));
    }
    
    /**
     * Assign a host to a rack position
     * 
     * @param string $hostId host ID
     * @param string $roomName room name
     * @param string $rackName rack name
     * @param int $uStart starting U position
     * @param int $uEnd ending U position
     * @param string $side front / back (no tag is written for the front)
     * @return bool whether it succeeded
     */
    public static function assignHost(
        string $hostId,
        string $roomName,
        string $rackName,
        int $uStart,
        int $uEnd,
        string $side = self::SIDE_FRONT
    ): bool {
        self::setLastError('');
        $side = self::normalizeSide($side);

        try {
            $hosts = \API::Host()->get([
                'output' => ['hostid'],
                'selectTags' => 'extend',
                'hostids' => [$hostId],
            ]);
        } catch (\Throwable $e) {
            self::setLastError($e->getMessage());
            error_log('HostRackManager: assignHost get failed for host ' . $hostId . ': ' . $e->getMessage());
            return false;
        }
        
        if (empty($hosts)) {
            self::setLastError('Host not found or no permission to read host');
            return false;
        }
        
        $host = $hosts[0];
        $tags = $host['tags'] ?? [];
        
        // Remove the old rack tags
        $tags = array_values(array_filter($tags, static function ($tag) {
            return !in_array($tag['tag'] ?? '', self::$rackTagNames, true);
        }));
        
        // Add the new rack tags
        $tags[] = ['tag' => self::TAG_ROOM, 'value' => $roomName];
        $tags[] = ['tag' => self::TAG_RACK, 'value' => $rackName];
        $tags[] = ['tag' => self::TAG_U_START, 'value' => (string) $uStart];
        $tags[] = ['tag' => self::TAG_U_END, 'value' => (string) $uEnd];
        if ($side === self::SIDE_BACK) {
            $tags[] = ['tag' => self::TAG_SIDE, 'value' => self::SIDE_BACK];
        }
        
        return self::updateHostTags($hostId, $tags);
    }
    
    /**
     * Remove a host from a rack
     * 
     * @param string $hostId host ID
     * @return bool whether it succeeded
     */
    public static function removeHost(string $hostId): bool {
        self::setLastError('');

        try {
            $hosts = \API::Host()->get([
                'output' => ['hostid'],
                'selectTags' => 'extend',
                'hostids' => [$hostId],
            ]);
        } catch (\Throwable $e) {
            self::setLastError($e->getMessage());
            error_log('HostRackManager: removeHost get failed for host ' . $hostId . ': ' . $e->getMessage());
            return false;
        }
        
        if (empty($hosts)) {
            self::setLastError('Host not found or no permission to read host');
            return false;
        }
        
        $host = $hosts[0];
        $tags = $host['tags'] ?? [];
        
        // Remove the rack-related tags
        $tags = array_values(array_filter($tags, static function ($tag) {
            return !in_array($tag['tag'] ?? '', self::$rackTagNames, true);
        }));
        
        return self::updateHostTags($hostId, $tags);
    }
    
    /**
     * Get the list of assignable hosts
     * 
     * @param string|null $groupId host group ID (optional)
     * @param string|null $search search keyword (optional)
     * @return array host list
     */
    public static function getAvailableHosts(?string $groupId = null, ?string $search = null): array {
        $options = [
            'output' => ['hostid', 'host', 'name', 'status'],
            self::getSelectGroupsParam() => ['groupid', 'name'],
            'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
            'selectTags' => 'extend',
            'sortfield' => 'name',
            'sortorder' => 'ASC',
            'limit' => 100
        ];
        
        if ($groupId) {
            $options['groupids'] = [$groupId];
        }
        
        if ($search) {
            $options['search'] = [
                'name' => $search,
                'host' => $search
            ];
            $options['searchByAny'] = true;
        }
        
        $hosts = \API::Host()->get($options);
        
        $result = [];
        foreach ($hosts as $host) {
            $inRack = false;
            $currentRoom = '';
            $currentRack = '';
            $currentSide = self::SIDE_FRONT;
            
            $parsed = self::parseRackTagsFromList($host['tags'] ?? []);
            if ($parsed['rack'] !== '') {
                $inRack = true;
                $currentRoom = $parsed['room'];
                $currentRack = $parsed['rack'];
                $currentSide = $parsed['side'];
            }
            
            // Get the main interface IP
            $mainIp = '';
            foreach ($host['interfaces'] as $interface) {
                if ($interface['main'] == 1) {
                    $mainIp = $interface['ip'] ?: $interface['dns'];
                    break;
                }
            }
            
            // Get host group names - compatible with Zabbix 6 (groups) and Zabbix 7 (hostgroups)
            $groups = [];
            $hostGroups = isset($host['groups']) ? $host['groups'] : (isset($host['hostgroups']) ? $host['hostgroups'] : []);
            foreach ($hostGroups as $group) {
                $groups[] = $group['name'];
            }
            
            $result[] = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'main_ip' => $mainIp,
                'groups' => $groups,
                'in_rack' => $inRack,
                'current_room' => $currentRoom,
                'current_rack' => $currentRack,
                'current_side' => $currentSide,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get all host groups
     * 
     * @return array host group list
     */
    public static function getHostGroups(): array {
        $groups = \API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'sortorder' => 'ASC'
        ]);
        
        return $groups;
    }
    
    /**
     * Check whether the U positions are available
     * 
     * @param string $roomName room name
     * @param string $rackName rack name
     * @param int $uStart starting U position
     * @param int $uEnd ending U position
     * @param string|null $excludeHostId host ID to exclude (when editing)
     * @param string $side front / back
     * @return bool whether available
     */
    public static function isPositionAvailable(
        string $roomName,
        string $rackName,
        int $uStart,
        int $uEnd,
        ?string $excludeHostId = null,
        string $side = self::SIDE_FRONT
    ): bool {
        $hosts = self::getHostsInRack($roomName, $rackName, $side);
        
        foreach ($hosts as $host) {
            if ($excludeHostId && $host['hostid'] === $excludeHostId) {
                continue;
            }
            
            $hostStart = $host['u_start'];
            $hostEnd = $host['u_end'];
            
            if ($hostStart === null || $hostEnd === null) {
                continue;
            }
            
            // Check for overlaps
            if (!($uEnd < $hostStart || $uStart > $hostEnd)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Merge alarm information and normalize host fields (for the view).
     *
     * @param array<int, array<string, mixed>> $hosts
     * @return array<int, array<string, mixed>>
     */
    public static function enrichHostsWithProblems(array $hosts): array {
        if ($hosts === []) {
            return $hosts;
        }

        $zabbixHostIds = array_values(array_filter(
            array_column($hosts, 'hostid'),
            static function ($hostId): bool {
                return !self::isCustomHostId((string) $hostId);
            }
        ));
        $hostProblemsData = self::getHostProblems($zabbixHostIds);

        foreach ($hosts as &$host) {
            $hostId = $host['hostid'];
            if (self::isCustomHostId((string) $hostId)) {
                $host['problem_count'] = 0;
                $host['problems'] = [];
                $host['max_severity'] = -1;
                $host['groups'] = '';
                $host['ip'] = $host['main_ip'] ?? '';
                continue;
            }
            if (isset($hostProblemsData[$hostId])) {
                $host['problem_count'] = $hostProblemsData[$hostId]['count'];
                $host['problems'] = $hostProblemsData[$hostId]['problems'];
                $host['max_severity'] = 0;
                foreach ($hostProblemsData[$hostId]['problems'] as $problem) {
                    if ((int) $problem['severity'] > $host['max_severity']) {
                        $host['max_severity'] = (int) $problem['severity'];
                    }
                }
            } else {
                $host['problem_count'] = 0;
                $host['problems'] = [];
                $host['max_severity'] = -1;
            }

            if (is_array($host['groups'] ?? null)) {
                $host['groups'] = implode(', ', $host['groups']);
            }
            $host['ip'] = $host['main_ip'] ?? '';
        }
        unset($host);

        return $hosts;
    }
    
    /**
     * Search hosts assigned to racks
     * 
     * @param string $keyword search keyword
     * @return array host list
     */
    public static function searchAssignedHosts(string $keyword): array {
        $options = [
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectTags' => 'extend',
            'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
            'search' => [
                'name' => $keyword,
                'host' => $keyword
            ],
            'searchByAny' => true,
            'limit' => 50
        ];
        
        $hosts = \API::Host()->get($options);
        
        $result = [];
        foreach ($hosts as $host) {
            $parsed = self::parseRackTagsFromList($host['tags'] ?? []);
            
            // Only return hosts assigned to racks
            if ($parsed['rack'] === '') {
                continue;
            }
            
            $roomName = $parsed['room'];
            $rackName = $parsed['rack'];
            $uStart = $parsed['u_start'];
            $uEnd = $parsed['u_end'];
            $side = $parsed['side'];
            
            // Get the main interface IP
            $mainIp = '';
            foreach ($host['interfaces'] as $interface) {
                if ($interface['main'] == 1) {
                    $mainIp = $interface['ip'] ?: $interface['dns'];
                    break;
                }
            }
            
            $result[] = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'main_ip' => $mainIp,
                'room_name' => $roomName,
                'rack_name' => $rackName,
                'u_start' => $uStart,
                'u_end' => $uEnd,
                'side' => $side,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get host alarm counts
     * 
     * @param array $hostIds array of host IDs
     * @return array host ID => alarm count
     */
    public static function getHostProblems(array $hostIds): array {
        if (empty($hostIds)) {
            return [];
        }
        
        $result = [];
        foreach ($hostIds as $hostId) {
            $result[$hostId] = [
                'count' => 0,
                'problems' => []
            ];
        }
        
        try {
            // Get active alarms - without recent=>true, only currently unresolved problems are returned
            // recent=>true keeps returning alarms for about 30 minutes after recovery, delaying the page refresh
            $problems = \API::Problem()->get([
                'output' => ['eventid', 'objectid', 'name', 'severity', 'clock', 'acknowledged'],
                'hostids' => $hostIds,
                'sortfield' => 'eventid',
                'sortorder' => 'DESC',
                'limit' => 1000
            ]);
            
            // Check the API return value
            if (!is_array($problems) || empty($problems)) {
                return $result;
            }
            
            // Sort by severity and time in PHP
            usort($problems, function($a, $b) {
                if ($a['severity'] != $b['severity']) {
                    return $b['severity'] - $a['severity']; // Severity descending
                }
                return $b['clock'] - $a['clock']; // Time descending
            });
            
            // Get the host ID of each alarm
            $triggerIds = array_unique(array_column($problems, 'objectid'));
            $triggerHostMap = [];
            
            if (!empty($triggerIds)) {
                // Only query enabled triggers, filtering out alarms of disabled triggers
                $triggers = \API::Trigger()->get([
                    'output' => ['triggerid', 'status'],
                    'selectHosts' => ['hostid'],
                    'triggerids' => $triggerIds,
                    'filter' => ['status' => TRIGGER_STATUS_ENABLED],
                    'preservekeys' => true
                ]);
                
                // Check the triggers return value
                if (!is_array($triggers)) {
                    $triggers = [];
                }
                
                foreach ($triggers as $triggerId => $trigger) {
                    if (!empty($trigger['hosts'])) {
                        $triggerHostMap[$triggerId] = $trigger['hosts'][0]['hostid'];
                    }
                }
            }
            
            // Count the alarms of each host
            foreach ($problems as $problem) {
                $triggerId = $problem['objectid'];
                if (isset($triggerHostMap[$triggerId])) {
                    $hostId = $triggerHostMap[$triggerId];
                    if (isset($result[$hostId])) {
                        $result[$hostId]['count']++;
                        $result[$hostId]['problems'][] = [
                            'eventid' => $problem['eventid'],
                            'name' => $problem['name'],
                            'severity' => $problem['severity'],
                            'clock' => $problem['clock'],
                            'acknowledged' => $problem['acknowledged']
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("HostRackManager::getHostProblems error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Get the alarm details of a single host
     * 
     * @param string $hostId host ID
     * @return array alarm list
     */
    public static function getHostProblemDetails(string $hostId): array {
        try {
            $problems = \API::Problem()->get([
                'output' => ['eventid', 'objectid', 'name', 'severity', 'clock', 'acknowledged', 'r_clock'],
                'hostids' => [$hostId],
                'sortfield' => 'eventid',
                'sortorder' => 'DESC',
                'limit' => 100
            ]);
            
            // Check the API return value
            if (!is_array($problems)) {
                return [];
            }
            
            // Sort by severity and time in PHP
            usort($problems, function($a, $b) {
                if ($a['severity'] != $b['severity']) {
                    return $b['severity'] - $a['severity'];
                }
                return $b['clock'] - $a['clock'];
            });
            
            // Get all related trigger IDs, filtering out disabled triggers
            $triggerIds = array_unique(array_column($problems, 'objectid'));
            $enabledTriggerIds = [];
            if (!empty($triggerIds)) {
                $triggers = \API::Trigger()->get([
                    'output' => ['triggerid'],
                    'triggerids' => $triggerIds,
                    'filter' => ['status' => TRIGGER_STATUS_ENABLED],
                    'preservekeys' => true
                ]);
                if (is_array($triggers)) {
                    $enabledTriggerIds = array_keys($triggers);
                }
            }
            
            $result = [];
            foreach ($problems as $problem) {
                // Skip alarms whose trigger is disabled
                if (!in_array($problem['objectid'], $enabledTriggerIds)) {
                    continue;
                }
                
                $severityNames = [
                    LanguageManager::t('severity_not_classified'),
                    LanguageManager::t('severity_information'),
                    LanguageManager::t('severity_warning'),
                    LanguageManager::t('severity_average'),
                    LanguageManager::t('severity_high'),
                    LanguageManager::t('severity_disaster'),
                ];
                $result[] = [
                    'eventid' => $problem['eventid'],
                    'name' => $problem['name'],
                    'severity' => $problem['severity'],
                    'severity_name' => $severityNames[$problem['severity']] ?? LanguageManager::t('severity_unknown'),
                    'clock' => $problem['clock'],
                    'time' => date('Y-m-d H:i:s', $problem['clock']),
                    'acknowledged' => $problem['acknowledged']
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("HostRackManager::getHostProblemDetails error: " . $e->getMessage());
            return [];
        }
    }
}
