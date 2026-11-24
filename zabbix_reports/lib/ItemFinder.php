<?php

namespace Modules\ZabbixReports\Lib;

use API;

class ItemFinder {

    private const METRIC_PATTERNS = [
        'cpu_usage' => [
            'keys' => ['system.cpu.util', 'system.cpu.util[]', 'system.cpu.util[,user]', 'system.cpu.util[,system]'],
            'names' => ['cpu utilization', 'cpu usage']
        ],
        'cpu_total' => [
            'keys' => ['system.cpu.num', 'system.hw.cpu.num'],
            'names' => ['number of cpus', 'cpu cores']
        ],
        'mem_usage' => [
            'keys' => ['vm.memory.utilization', 'vm.memory.util[pused]', 'vm.memory.pused'],
            'names' => ['memory utilization', 'memory usage']
        ],
        'mem_total' => [
            'keys' => ['vm.memory.size[total]', 'vm.memory.total'],
            'names' => ['total memory', 'memory total']
        ]
    ];

    /**
     * Resolve all report metrics for a list of hosts in a bounded number of API calls.
     *
     * The original per-host helpers are intentionally kept for the diagnostic actions,
     * while report pages use this method to avoid 4-30 API requests per host.
     */
    public static function findMetricsForHosts(array $hostids, int $timeFrom, int $timeTill): array {
        $hostids = array_values(array_unique(array_filter(array_map('strval', $hostids), 'strlen')));
        if (empty($hostids)) {
            return [];
        }

        $fields = ['itemid', 'hostid', 'name', 'key_', 'lastvalue', 'lastclock', 'value_type'];
        $baseOptions = [
            'output' => $fields,
            'hostids' => $hostids,
            'filter' => ['status' => ITEM_STATUS_ACTIVE]
        ];

        // Query only known report keys; broad system.cpu/vm.memory searches can
        // return thousands of unrelated per-core and discovery items.
        $byId = [];
        $metricKeys = [];
        foreach (self::METRIC_PATTERNS as $patterns) {
            $metricKeys = array_merge($metricKeys, $patterns['keys']);
        }
        $metricKeys = array_values(array_unique($metricKeys));
        foreach (array_chunk($hostids, 200) as $hostChunk) {
            $chunkOptions = $baseOptions;
            $chunkOptions['hostids'] = $hostChunk;
            $items = API::Item()->get(array_merge($chunkOptions, [
                'filter' => [
                    'status' => ITEM_STATUS_ACTIVE,
                    'key_' => $metricKeys
                ]
            ]));

            // Keep name-based compatibility for custom or localized item keys.
            $namedItems = API::Item()->get(array_merge($chunkOptions, [
                'search' => ['name' => [
                    'CPU utilization', 'CPU usage', 'Number of CPUs', 'CPU cores',
                    'Memory utilization', 'Memory usage', 'Total memory', 'Memory total'
                ]],
                'searchByAny' => true,
                'searchWildcardsEnabled' => true
            ]));
            foreach (array_merge(is_array($items) ? $items : [], is_array($namedItems) ? $namedItems : []) as $item) {
                $byId[(string)$item['itemid']] = $item;
            }
        }
        $items = array_values($byId);

        $selected = [];
        $scores = [];
        foreach ($items as $item) {
            $hostid = (string)$item['hostid'];
            foreach (self::METRIC_PATTERNS as $metric => $patterns) {
                $score = self::scoreItem($item, $patterns, $metric);
                if ($score > ($scores[$hostid][$metric] ?? 0)) {
                    $scores[$hostid][$metric] = $score;
                    $selected[$hostid][$metric] = $item;
                }
            }
        }

        $historyValues = self::getRecentValues($selected, $timeFrom, $timeTill);
        $result = [];
        foreach ($selected as $hostid => $metrics) {
            foreach ($metrics as $metric => $item) {
                $itemid = (string)$item['itemid'];
                $value = $historyValues[$itemid] ?? null;
                if ($value === null && isset($item['lastvalue']) && $item['lastvalue'] !== '') {
                    $value = $item['lastvalue'];
                }
                $result[$hostid][$metric] = ['item' => $item, 'value' => $value];
            }
        }

        return $result;
    }

    private static function scoreItem(array $item, array $patterns, string $metric): int {
        $key = strtolower((string)($item['key_'] ?? ''));
        $name = strtolower((string)($item['name'] ?? ''));

        foreach ($patterns['keys'] as $index => $candidate) {
            if ($key === strtolower($candidate)) {
                return 1000 - $index;
            }
        }
        foreach ($patterns['names'] as $index => $candidate) {
            if (strpos($name, $candidate) !== false) {
                return 700 - $index;
            }
        }

        if ($metric === 'cpu_usage'
                && strpos($key, 'system.cpu.util') === 0
                && strpos($key, 'idle') === false) {
            return 500;
        }
        if ($metric === 'cpu_total' && (strpos($key, 'system.cpu.num') === 0 || strpos($key, 'system.hw.cpu.num') === 0)) {
            return 500;
        }
        if ($metric === 'mem_usage'
                && (strpos($key, 'vm.memory.util') === 0 || strpos($key, 'vm.memory.pused') === 0)) {
            return 500;
        }
        if ($metric === 'mem_total'
                && (strpos($key, 'vm.memory.size[total]') === 0 || strpos($key, 'vm.memory.total') === 0)) {
            return 500;
        }

        return 0;
    }

    private static function getRecentValues(array $selected, int $timeFrom, int $timeTill): array {
        $itemidsByType = [];
        $metricByItemid = [];
        foreach ($selected as $metrics) {
            foreach ($metrics as $metric => $item) {
                $itemid = (string)$item['itemid'];
                $itemidsByType[(int)$item['value_type']][$itemid] = true;
                $metricByItemid[$itemid] = $metric;
            }
        }

        $samples = [];
        foreach ($itemidsByType as $historyType => $itemidMap) {
            foreach (array_chunk(array_keys($itemidMap), 200) as $itemids) {
                $rows = API::History()->get([
                    'output' => ['itemid', 'value', 'clock'],
                    'history' => $historyType,
                    'itemids' => $itemids,
                    'time_from' => $timeFrom,
                    'time_till' => $timeTill,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => max(10, count($itemids) * 10)
                ]);
                if (!is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    $itemid = (string)$row['itemid'];
                    if (count($samples[$itemid] ?? []) < 10) {
                        $samples[$itemid][] = (float)$row['value'];
                    }
                }
            }
        }

        $values = [];
        foreach ($samples as $itemid => $itemSamples) {
            $metric = $metricByItemid[$itemid] ?? '';
            $values[$itemid] = ($metric === 'cpu_usage' || $metric === 'mem_usage')
                ? array_sum($itemSamples) / count($itemSamples)
                : $itemSamples[0];
        }
        return $values;
    }
    
    /**
     * Find the CPU utilization item
     */
    public static function findCpuUtilization($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // Prefer exact keys
            ['filter' => ['key_' => 'system.cpu.util']],
            ['filter' => ['key_' => 'system.cpu.util[]']],
            ['filter' => ['key_' => 'system.cpu.util[,user]']],
            ['filter' => ['key_' => 'system.cpu.util[,system]']],
            // Search by name as a fallback
            ['search' => ['name' => 'CPU utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.cpu.util'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }
    
    /**
     * Find the CPU count item
     */
    public static function findCpuCount($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // Prefer exact keys
            ['filter' => ['key_' => 'system.cpu.num']],
            ['filter' => ['key_' => 'system.hw.cpu.num']],
            // Search by name as a fallback
            ['search' => ['name' => 'Number of CPUs'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU cores'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.num'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }
    
    /**
     * Find the memory utilization item
     */
    public static function findMemoryUtilization($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // Prefer exact keys
            ['filter' => ['key_' => 'vm.memory.utilization']],
            ['filter' => ['key_' => 'vm.memory.util[pused]']],
            ['filter' => ['key_' => 'vm.memory.pused']],
            // Search by name as a fallback
            ['search' => ['name' => 'Memory utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'vm.memory.util'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }
    
    /**
     * Find the total memory item
     */
    public static function findMemoryTotal($hostid, $timeFrom = null, $timeTill = null) {
        $patterns = [
            // Prefer exact keys
            ['filter' => ['key_' => 'vm.memory.size[total]']],
            ['filter' => ['key_' => 'vm.memory.total']],
            // Search by name as a fallback
            ['search' => ['name' => 'Total memory'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory total'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'vm.memory.size'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns, $timeFrom, $timeTill);
    }

    /**
     * Find the memory size item (alias, same as findMemoryTotal)
     */
    public static function findMemorySize($hostid, $timeFrom = null, $timeTill = null) {
        return self::findMemoryTotal($hostid, $timeFrom, $timeTill);
    }
    
    /**
     * Find an item from a list of patterns and get its value
     */
    private static function findItemByPatterns($hostid, $patterns, $timeFrom = null, $timeTill = null) {
        foreach ($patterns as $pattern) {
            $searchParams = array_merge([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'value_type'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'limit' => 1
            ], $pattern);
            
            $items = API::Item()->get($searchParams);
            
            if (!empty($items)) {
                $item = $items[0];
                $value = null;
                
                // First try to get history data
                if ($timeFrom !== null && $timeTill !== null) {
                    $history = API::History()->get([
                        'output' => ['value', 'clock'],
                        'itemids' => $item['itemid'],
                        'time_from' => $timeFrom,
                        'time_till' => $timeTill,
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 10  // Get several values to make sure there is data
                    ]);
                    
                    if (!empty($history)) {
                        // Compute the average (for CPU and memory utilization)
                        if (strpos($item['key_'], 'util') !== false || strpos($item['key_'], 'utilization') !== false) {
                            $sum = 0;
                            $count = count($history);
                            foreach ($history as $h) {
                                $sum += floatval($h['value']);
                            }
                            $value = $count > 0 ? $sum / $count : 0;
                        } else {
                            // For CPU count and total memory, use the latest value
                            $value = $history[0]['value'];
                        }
                    }
                }
                
                // Without history data, use the last value
                if ($value === null && isset($item['lastvalue']) && $item['lastvalue'] !== '') {
                    $value = $item['lastvalue'];
                }
                
                // If there is still no value, try to get the latest history data
                if ($value === null) {
                    $recentHistory = API::History()->get([
                        'output' => ['value'],
                        'itemids' => $item['itemid'],
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1
                    ]);
                    
                    if (!empty($recentHistory)) {
                        $value = $recentHistory[0]['value'];
                    }
                }
                
                return [
                    'item' => $item,
                    'value' => $value
                ];
            }
        }
        
        return null;
    }
}
