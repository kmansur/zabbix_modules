<?php

namespace Modules\ZabbixCmdb\Lib;

use API;

require_once __DIR__ . '/ItemConfig.php';

class ItemFinder {
    
    /**
     * Find the CPU count item
     */
    public static function findCpuCount($hostid) {
        $items = self::batchGetHostItems([$hostid], ['cpu_count']);
        return $items[$hostid]['cpu_count'];
    }
    
    /**
     * Find the total memory item
     */
    public static function findMemoryTotal($hostid) {
        $items = self::batchGetHostItems([$hostid], ['memory_total']);
        return $items[$hostid]['memory_total'];
    }

    /**
     * Find the CPU utilization item
     */
    public static function findCpuUsage($hostid) {
        $items = self::batchGetHostItems([$hostid], ['cpu_usage']);
        return $items[$hostid]['cpu_usage'];
    }

    /**
     * Find the memory utilization item
     */
    public static function findMemoryUsage($hostid) {
        $items = self::batchGetHostItems([$hostid], ['memory_usage']);
        return $items[$hostid]['memory_usage'];
    }

        /**
     * Find the kernel version item
     */
    public static function findKernelVersion($hostid) {
        $items = self::batchGetHostItems([$hostid], ['kernel_version']);
        return $items[$hostid]['kernel_version'];
    }
    
    /**
     * Find the system name item
     */
    public static function findSystemName($hostid) {
        $items = self::batchGetHostItems([$hostid], ['system_name']);
        return $items[$hostid]['system_name'];
    }
    
    /**
     * Find the operating system item
     */
    public static function findOperatingSystem($hostid) {
        $items = self::batchGetHostItems([$hostid], ['operating_system']);
        return $items[$hostid]['operating_system'];
    }
    
    /**
     * Find the OS architecture item
     */
    public static function findOsArchitecture($hostid) {
        $items = self::batchGetHostItems([$hostid], ['os_architecture']);
        return $items[$hostid]['os_architecture'];
    }
    
    /** Fetch candidates in batches, then apply JSON rules in their declared order. */
    public static function batchGetHostItems(array $hostIds, array $categories = []): array {
        if (!$hostIds) {
            return [];
        }
        $rules = ItemConfig::load();
        if ($categories) {
            $rules = array_intersect_key($rules, array_flip($categories));
        }
        $result = array_fill_keys($hostIds, array_fill_keys(array_keys($rules), null));
        $search = [];
        foreach ($rules as $categoryRules) {
            foreach ($categoryRules as $rule) {
                // API search narrows candidates; local matching enforces exact/fuzzy semantics.
                $search[$rule['field']][] = $rule['match'] === 'fuzzy'
                    ? '*'.$rule['pattern'].'*' : $rule['pattern'];
            }
        }
        if (!$search) {
            return $result;
        }
        foreach ($search as &$patterns) {
            $patterns = array_values(array_unique($patterns));
        }
        unset($patterns);
        foreach (array_chunk($hostIds, 200) as $chunk) {
            $items = API::Item()->get([
                'output' => ['itemid', 'hostid', 'name', 'key_', 'lastvalue', 'value_type'],
                'hostids' => $chunk,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => $search,
                'searchByAny' => true,
                'searchWildcardsEnabled' => true
            ]);
            // Stable tie-break for multiple items matching the same rule.
            usort($items, function ($a, $b) {
                return strnatcmp($a['itemid'], $b['itemid']);
            });
            $byHost = [];
            foreach ($items as $item) {
                $byHost[$item['hostid']][] = $item;
            }
            foreach ($chunk as $hostid) {
                foreach ($rules as $category => $categoryRules) {
                    $selected = self::selectItem($byHost[$hostid] ?? [], $categoryRules);
                    if ($selected === null) {
                        continue;
                    }
                    $item = $selected['item'];
                    $value = $item['lastvalue'] ?? '';
                    if ($value === '') {
                        $history = API::History()->get([
                            'output' => ['value'], 'itemids' => [$item['itemid']],
                            'history' => (int) $item['value_type'],
                            'sortfield' => ['clock', 'ns'], 'sortorder' => 'DESC', 'limit' => 1
                        ]);
                        $value = $history[0]['value'] ?? '';
                    }
                    if ($value !== '' && $selected['rule']['transform'] === 'subtract_from_100'
                            && is_numeric($value)) {
                        $value = 100 - (float) $value;
                    }
                    $result[$hostid][$category] = [
                        'item' => $item, 'value' => $value,
                        'key' => $item['key_'], 'value_type' => $item['value_type']
                    ];
                }
            }
        }
        return $result;
    }

    public static function selectItem(array $items, array $rules) {
        foreach ($rules as $rule) {
            foreach ($items as $item) {
                $value = $item[$rule['field']] ?? '';
                $matched = $rule['match'] === 'exact'
                    ? $value === $rule['pattern']
                    : preg_match('/'.str_replace('\\*', '.*', preg_quote($rule['pattern'], '/')).'/iu', $value) === 1;
                if ($matched) {
                    return ['item' => $item, 'rule' => $rule];
                }
            }
        }
        return null;
    }

    /**
     * Format the memory size
     */
    public static function formatMemorySize($bytes) {
        if (empty($bytes) || !is_numeric($bytes)) {
            return '-';
        }
        
        $bytes = floatval($bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Extract the kernel version information
     */
    public static function extractKernelInfo($fullString) {
        if (empty($fullString)) {
            return '-';
        }

        // If the string is too long, try to extract the key information
        if (strlen($fullString) > 50) {
            // Try to extract the Linux kernel version
            if (preg_match('/Linux\s+\S+\s+(\S+)/', $fullString, $matches)) {
                return $matches[1];
            }
            
            // Try to extract the Windows version information
            if (preg_match('/Windows\s+[^0-9]*([0-9]+[^,\s]*)/i', $fullString, $matches)) {
                return 'Windows ' . $matches[1];
            }
            
            // For other systems, take the first 50 characters
            return substr($fullString, 0, 47) . '...';
        }
        
        return $fullString;
    }

    /**
     * Get the availability status of host interfaces (based on native Zabbix interface availability)
     * Returns an array with status information
     */
    public static function getHostAvailabilityStatus($hostid, $interfaces = []) {
        try {
            // If no interface information was passed, get it from the API
            if (empty($interfaces)) {
                $interfaces = API::HostInterface()->get([
                    'hostids' => [$hostid],
                    'output' => ['interfaceid', 'type', 'main', 'available', 'error']
                ]);
            }

            if (empty($interfaces)) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // Find the availability status of the main interface
            $mainInterface = null;
            foreach ($interfaces as $interface) {
                if ($interface['main'] == 1) {
                    $mainInterface = $interface;
                    break;
                }
            }

            // If there is no main interface, use the first interface
            if (!$mainInterface && !empty($interfaces)) {
                $mainInterface = $interfaces[0];
            }

            if (!$mainInterface) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // Return the display matching the Zabbix interface availability status
            // available: 0=unknown, 1=available, 2=unavailable
            switch ($mainInterface['available']) {
                case '1':
                    return ['status' => 'available', 'text' => 'Available', 'class' => 'status-available'];
                case '2':
                    return ['status' => 'unavailable', 'text' => 'Unavailable', 'class' => 'status-unavailable'];
                case '0':
                default:
                    return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

        } catch (Exception $e) {
            error_log("Failed to check host availability for {$hostid}: " . $e->getMessage());
            return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
        }
    }
}
