<?php

namespace Modules\ZabbixCmdb\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

class Cmdb extends CController {

    /** Pagination constants */
    const PAGE_SIZE_DEFAULT = 50;
    const PAGE_SIZE_MIN     = 10;
    const PAGE_SIZE_MAX     = 100;

    public function init(): void {
        // Compatible with Zabbix 6 and 7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'search'         => 'string',
            'groupid'        => 'int32',
            'sort'           => 'string',
            'sortorder'      => 'in ASC,DESC',
            'interface_type' => 'int32',
            'page'           => 'int32',
            'page_size'      => 'int32',
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => _('Invalid input parameters.')]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $search         = $this->getInput('search', '');
        $groupid        = $this->getInput('groupid', 0);
        $interface_type = $this->getInput('interface_type', 0);
        $page           = max(1, $this->getInput('page', 1));
        $pageSize       = min(self::PAGE_SIZE_MAX, max(self::PAGE_SIZE_MIN, $this->getInput('page_size', self::PAGE_SIZE_DEFAULT)));
        $sort           = $this->getInput('sort', 'name');
        $sortorder      = $this->getInput('sortorder', 'ASC');

        // ── 1) Get the host group list (dropdown) ──
        $hostGroups = $this->getHostGroups();

        // ── 2) Lightweight query: get all matching host IDs ──
        //    Returns only hostid + the needed interface type information, no item data
        $allHostIds = $this->getFilteredHostIds($search, $groupid, $interface_type);

        // ── 2.5) Compute global statistics (CPU/memory totals + active hosts, across all filtered hosts) ──
        $globalStats = $this->computeGlobalStats($allHostIds);

        // ── 3) Compute pagination parameters ──
        $totalCount = count($allHostIds);
        $totalPages = max(1, (int)ceil($totalCount / $pageSize));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $pageSize;

        // ── 4) Take the slice of host IDs for the current page ──
        $pageHostIds = array_slice($allHostIds, $offset, $pageSize);

        // ── 5) Get full information for the hosts on the current page (one API call) ──
        $hosts = [];
        if (!empty($pageHostIds)) {
            $hosts = $this->getHostDetails($pageHostIds);
        }

        // ── 6) Fetch all items of the hosts on the current page in bulk ──
        //    Replaces the former N×8 per-host queries
        $itemsData = [];
        if (!empty($pageHostIds)) {
            $itemsData = ItemFinder::batchGetHostItems($pageHostIds);
        }

        // ── 7) Assemble host data ──
        $hostData = [];

        foreach ($hosts as $host) {
            $hostid = $host['hostid'];

            $hostInfo = [
                'hostid'             => $hostid,
                'host'               => $host['host'],
                'name'               => $host['name'],
                'status'             => $host['status'],
                'maintenance_status' => $host['maintenance_status'] ?? 0,
                'maintenance_type'   => $host['maintenance_type'] ?? 0,
                'groups'             => $host['hostgroups'] ?? ($host['groups'] ?? []),
                'interfaces'         => $host['interfaces'] ?? [],
                'cpu_total'          => '-',
                'cpu_usage'          => '-',
                'memory_total'       => '-',
                'memory_usage'       => '-',
                'kernel_version'     => '-',
                'system_name'        => '-',
                'operating_system'   => '-',
                'os_architecture'    => '-',
            ];

            // Availability status (based on the interface information already fetched, no extra API call)
            $hostInfo['availability'] = ItemFinder::getHostAvailabilityStatus(
                $hostid,
                $host['interfaces'] ?? []
            );

            // Extract item values from the bulk data
            if (isset($itemsData[$hostid])) {
                $items = $itemsData[$hostid];

                // CPU count
                if ($items['cpu_count'] !== null && $items['cpu_count']['value'] !== '') {
                    $hostInfo['cpu_total'] = $items['cpu_count']['value'];
                }

                // CPU utilization
                if ($items['cpu_usage'] !== null && $items['cpu_usage']['value'] !== '') {
                    $val = floatval($items['cpu_usage']['value']);

                    $hostInfo['cpu_usage'] = round($val, 2) . '%';
                }

                // Total memory
                if ($items['memory_total'] !== null && $items['memory_total']['value'] !== '') {
                    $hostInfo['memory_total'] = ItemFinder::formatMemorySize($items['memory_total']['value']);
                }

                // Memory utilization
                if ($items['memory_usage'] !== null && $items['memory_usage']['value'] !== '') {
                    $val = floatval($items['memory_usage']['value']);

                    $hostInfo['memory_usage'] = round($val, 2) . '%';
                }

                // Kernel version
                if ($items['kernel_version'] !== null && $items['kernel_version']['value'] !== '') {
                    $hostInfo['kernel_version'] = ItemFinder::extractKernelInfo($items['kernel_version']['value']);
                }

                // System name
                if ($items['system_name'] !== null && $items['system_name']['value'] !== '') {
                    $hostInfo['system_name'] = $items['system_name']['value'];
                }

                // Operating system
                if ($items['operating_system'] !== null && $items['operating_system']['value'] !== '') {
                    $hostInfo['operating_system'] = $items['operating_system']['value'];
                }

                // Architecture
                if ($items['os_architecture'] !== null && $items['os_architecture']['value'] !== '') {
                    $hostInfo['os_architecture'] = $items['os_architecture']['value'];
                }
            }

            $hostData[] = $hostInfo;
        }

        // ── 8) In-page sorting ──
        //    Sorting by host name is kept globally consistent by the API sortfield,
        //    item fields are sorted only within the current page (the API cannot sort by item value)
        if (!empty($hostData)) {
            $itemSortFields = [
                'cpu_total', 'cpu_usage', 'memory_total', 'memory_usage',
                'kernel_version', 'system_name', 'operating_system',
                'os_architecture', 'ip',
            ];

            // Sort in PHP only when the sort field is an item field
            // Fields such as host name are already sorted by the API
            if (in_array($sort, $itemSortFields)) {
                usort($hostData, function ($a, $b) use ($sort, $sortorder, $itemsData) {
                    $valueA = $a[$sort] ?? '';
                    $valueB = $b[$sort] ?? '';

                    if (in_array($sort, ['cpu_total', 'cpu_usage', 'memory_total', 'memory_usage'])) {
                        if ($sort === 'cpu_usage' || $sort === 'memory_usage') {
                            $valueA = $valueA !== '-' ? floatval(str_replace('%', '', $valueA)) : -1;
                            $valueB = $valueB !== '-' ? floatval(str_replace('%', '', $valueB)) : -1;
                        } elseif ($sort === 'memory_total') {
                            // Sort by raw byte values, not by the formatted strings
                            $valueA = isset($itemsData[$a['hostid']]['memory_total'])
                                ? floatval($itemsData[$a['hostid']]['memory_total']['value'])
                                : -1;
                            $valueB = isset($itemsData[$b['hostid']]['memory_total'])
                                ? floatval($itemsData[$b['hostid']]['memory_total']['value'])
                                : -1;
                        } else {
                            $valueA = is_numeric($valueA) ? floatval($valueA) : -1;
                            $valueB = is_numeric($valueB) ? floatval($valueB) : -1;
                        }
                    } else {
                        $valueA = ($valueA === '-') ? '' : (string)$valueA;
                        $valueB = ($valueB === '-') ? '' : (string)$valueB;
                    }

                    return $sortorder === 'DESC'
                        ? ($valueB <=> $valueA)
                        : ($valueA <=> $valueB);
                });
            }
        }

        // ── 9) Build the response ──
        $response = new CControllerResponseData([
            'title'            => LanguageManager::t('Host List'),
            'host_groups'      => $hostGroups,
            'hosts'            => $hostData,
            'search'           => $search,
            'selected_groupid' => $groupid,
            'interface_type'   => $interface_type,
            'sort'             => $sort,
            'sortorder'        => $sortorder,
            'total_cpu'        => $globalStats['total_cpu'],
            'total_memory'     => $globalStats['total_memory'],
            'active_hosts'     => $globalStats['active_hosts'],
            // Pagination information
            'page'             => $page,
            'page_size'        => $pageSize,
            'total_count'      => $totalCount,
            'total_pages'      => $totalPages,
        ]);

        $response->setTitle(LanguageManager::t('Host List'));
        $this->setResponse($response);
    }

    // ─────────────────────────────────────────────
    //  Helper methods
    // ─────────────────────────────────────────────

    /**
     * Get the host group list (for the search form dropdown)
     */
    private function getHostGroups(): array {
        $hostGroups = [];

        $strategies = [
            // Strategy 1: only groups that contain hosts (recommended, best performance)
            function () {
                return API::HostGroup()->get([
                    'output'     => ['groupid', 'name'],
                    'with_hosts' => true,
                    'sortfield'  => 'name',
                    'sortorder'  => 'ASC',
                ]);
            },
            // Strategy 2: all groups
            function () {
                return API::HostGroup()->get([
                    'output'    => ['groupid', 'name'],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC',
                ]);
            },
            // Strategy 3: extend output (compatibility with some versions)
            function () {
                $groups = API::HostGroup()->get([
                    'output'    => 'extend',
                    'sortfield' => 'name',
                    'sortorder' => 'ASC',
                ]);
                return array_map(function ($g) {
                    return ['groupid' => $g['groupid'], 'name' => $g['name']];
                }, $groups);
            },
            // Strategy 4: get groups through hosts (last compatibility option)
            function () {
                $hosts = API::Host()->get([
                    'output'           => ['hostid'],
                    'selectHostGroups' => ['groupid', 'name'],
                    'limit'            => 1000,
                ]);
                $map = [];
                foreach ($hosts as $host) {
                    $groups = $host['hostgroups'] ?? ($host['groups'] ?? []);
                    foreach ($groups as $group) {
                        $map[$group['groupid']] = [
                            'groupid' => $group['groupid'],
                            'name'    => $group['name'],
                        ];
                    }
                }
                $groups = array_values($map);
                usort($groups, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                return $groups;
            },
        ];

        foreach ($strategies as $index => $strategy) {
            try {
                $hostGroups = $strategy();
                if (!empty($hostGroups)) {
                    break;
                }
            } catch (\Exception $e) {
                error_log('CMDB: Host group strategy ' . ($index + 1) . ' failed: ' . $e->getMessage());
                continue;
            }
        }

        if (empty($hostGroups)) {
            error_log('CMDB: All host group retrieval strategies failed');
        }

        return $hostGroups;
    }

    /**
     * Get the filtered list of host IDs (lightweight query)
     *
     * Returns only an array of hostids, sorted by host name.
     * Search covers: host name, visible name, IP address.
     * Filtering by group / interface type is supported.
     */
    private function getFilteredHostIds(string $search, int $groupid, int $interface_type): array {
        $allHostIds = []; // hostid => host_lite_data

        // Base query parameters: return only hostid, sorted by name
        $baseParams = [
            'output'    => ['hostid'],
            'sortfield' => 'name',
            'sortorder' => 'ASC',
        ];

        if ($groupid > 0) {
            $baseParams['groupids'] = [$groupid];
        }

        // When filtering by interface type, include the interface type field
        if ($interface_type > 0) {
            $baseParams['selectInterfaces'] = ['type'];
        }

        if (!empty($search)) {
            // ── Search host name / visible name ──
            try {
                $nameParams = array_merge($baseParams, [
                    'search'                 => [
                        'host' => '*' . $search . '*',
                        'name' => '*' . $search . '*',
                    ],
                    'searchWildcardsEnabled' => true,
                    'searchByAny'            => true,
                ]);
                $nameHosts = API::Host()->get($nameParams);
                foreach ($nameHosts as $host) {
                    $allHostIds[$host['hostid']] = $host;
                }
            } catch (\Exception $e) {
                error_log('CMDB: Name search failed: ' . $e->getMessage());
            }

            // ── If the search term contains digits, also search IP addresses ──
            if (preg_match('/\d/', $search)) {
                try {
                    $interfaces = API::HostInterface()->get([
                        'output'                 => ['hostid'],
                        'search'                 => [
                            'ip'  => '*' . $search . '*',
                            'dns' => '*' . $search . '*',
                        ],
                        'searchWildcardsEnabled' => true,
                        'searchByAny'            => true,
                    ]);

                    if (!empty($interfaces)) {
                        $ipHostIds = array_unique(array_column($interfaces, 'hostid'));
                        // Exclude hosts already found by the name search
                        $ipHostIds = array_diff($ipHostIds, array_keys($allHostIds));

                        if (!empty($ipHostIds)) {
                            $ipParams = array_merge($baseParams, [
                                'hostids' => array_values($ipHostIds),
                            ]);
                            $ipHosts = API::Host()->get($ipParams);
                            foreach ($ipHosts as $host) {
                                $allHostIds[$host['hostid']] = $host;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log('CMDB: IP search failed: ' . $e->getMessage());
                }
            }
        } else {
            // ── No search criteria: get all hosts ──
            try {
                $hosts = API::Host()->get($baseParams);
                foreach ($hosts as $host) {
                    $allHostIds[$host['hostid']] = $host;
                }
            } catch (\Exception $e) {
                error_log('CMDB: Host fetch failed: ' . $e->getMessage());
            }
        }

        // ── Filter by interface type ──
        if ($interface_type > 0) {
            $filtered = [];
            foreach ($allHostIds as $hostid => $host) {
                if (!empty($host['interfaces'])) {
                    foreach ($host['interfaces'] as $iface) {
                        if ($iface['type'] == $interface_type) {
                            $filtered[$hostid] = $host;
                            break;
                        }
                    }
                }
            }
            $allHostIds = $filtered;
        }

        return array_keys($allHostIds);
    }

    /**
     * Compute global statistics (across all filtered hosts)
     *
     * Fetch CPU count and total memory in bulk using the configured rules:
     * - 1 host.get call for status/maintenance/interfaces to determine active hosts
     *
     * @param array $allHostIds all filtered host IDs
     * @return array ['total_cpu' => int, 'total_memory' => int, 'active_hosts' => int]
     */
    private function computeGlobalStats(array $allHostIds): array {
        $stats = [
            'total_cpu'    => 0,
            'total_memory' => 0,
            'active_hosts' => 0,
        ];

        if (empty($allHostIds)) {
            return $stats;
        }

        // CPU and memory totals: same matching rules as the host list
        try {
            $items = ItemFinder::batchGetHostItems($allHostIds, ['cpu_count', 'memory_total']);
            foreach ($items as $hostItems) {
                $stats['total_cpu'] += (float) ($hostItems['cpu_count']['value'] ?? 0);
                $stats['total_memory'] += (float) ($hostItems['memory_total']['value'] ?? 0);
            }
        } catch (\Exception $e) {
            error_log('CMDB: Global CPU/Memory stats failed: ' . $e->getMessage());
        }

        // ── Active host statistics: a single host.get ──
        // Active = enabled (status=0) + not in maintenance + main interface available=1
        try {
            $hosts = API::Host()->get([
                'output'           => ['hostid', 'status', 'maintenance_status'],
                'selectInterfaces' => ['type', 'main', 'available'],
                'hostids'          => $allHostIds,
            ]);

            foreach ($hosts as $host) {
                // Exclude disabled hosts
                if ($host['status'] == 1) {
                    continue;
                }
                // Exclude hosts in maintenance
                if (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
                    continue;
                }
                // Check interface availability (same logic as getHostAvailabilityStatus)
                if (!empty($host['interfaces'])) {
                    // Look for the main interface first
                    $mainIface = null;
                    foreach ($host['interfaces'] as $iface) {
                        if ($iface['main'] == 1) {
                            $mainIface = $iface;
                            break;
                        }
                    }
                    if (!$mainIface) {
                        $mainIface = $host['interfaces'][0];
                    }
                    if (isset($mainIface['available']) && $mainIface['available'] == 1) {
                        $stats['active_hosts']++;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('CMDB: Global active hosts count failed: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get full details for the given host IDs
     *
     * Called only for the hosts on the current page, usually 10–100.
     */
    private function getHostDetails(array $hostIds): array {
        if (empty($hostIds)) {
            return [];
        }

        try {
            return API::Host()->get([
                'output'           => [
                    'hostid', 'host', 'name', 'status',
                    'maintenance_status', 'maintenance_type', 'maintenanceid',
                ],
                'selectHostGroups' => ['groupid', 'name'],
                'selectInterfaces' => [
                    'interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error',
                ],
                'hostids'          => $hostIds,
                'preservekeys'     => false,
            ]);
        } catch (\Exception $e) {
            error_log('CMDB: Host details fetch failed: ' . $e->getMessage());
            return [];
        }
    }
}
