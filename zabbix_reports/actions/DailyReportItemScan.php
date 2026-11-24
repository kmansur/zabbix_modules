<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

use Modules\ZabbixReports\Lib\LanguageManager;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixReports\Lib\ItemFinder;

class DailyReportItemScan extends CController {

    public function init(): void {
        // Compatible with Zabbix 6 and 7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        
        // Get all hosts
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED],
            'limit' => 5  // Only check the first 5 hosts
        ]);
        
        if (empty($hosts)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo LanguageManager::t('No monitored hosts found.') . "\n";
            exit;
        }
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo LanguageManager::t('=== Zabbix item scanner ===') . "\n\n";
        echo LanguageManager::t('Scanning all items of the first 5 hosts...') . "\n\n";
        
        foreach ($hosts as $host) {
            echo LanguageManager::tf('Host: %s (ID: %s)', $host['name'], $host['hostid']) . "\n";
            echo str_repeat("=", 60) . "\n";
            
            // Get all items
            $allItems = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units', 'value_type'],
                'hostids' => $host['hostid'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'sortfield' => 'name'
            ]);
            
            echo LanguageManager::tf('Total items: %d', count($allItems)) . "\n\n";
            
            // Categorize items
            $categories = [
                'CPU' => [],
                'Memory' => [],
                'Network' => [],
                'Disk' => [],
                'System' => [],
                'Other' => []
            ];
            
            foreach ($allItems as $item) {
                $name = strtolower($item['name']);
                $key = strtolower($item['key_']);
                
                if (strpos($name, 'cpu') !== false || strpos($key, 'cpu') !== false) {
                    $categories['CPU'][] = $item;
                } elseif (strpos($name, 'mem') !== false || strpos($key, 'mem') !== false) {
                    $categories['Memory'][] = $item;
                } elseif (strpos($name, 'net') !== false || strpos($key, 'net') !== false || 
                         strpos($name, 'interface') !== false) {
                    $categories['Network'][] = $item;
                } elseif (strpos($name, 'disk') !== false || strpos($key, 'disk') !== false ||
                         strpos($name, 'file') !== false || strpos($key, 'vfs') !== false) {
                    $categories['Disk'][] = $item;
                } elseif (strpos($name, 'system') !== false || strpos($key, 'system') !== false ||
                         strpos($name, 'uptime') !== false || strpos($name, 'load') !== false) {
                    $categories['System'][] = $item;
                } else {
                    $categories['Other'][] = $item;
                }
            }
            
            // Output each category
            foreach ($categories as $categoryName => $items) {
                if (!empty($items)) {
                    echo LanguageManager::tf('%s (%d items):', LanguageManager::t($categoryName), count($items)) . "\n";
                    foreach ($items as $item) {
                        echo "  - {$item['name']}\n";
                        echo "    " . LanguageManager::tf('Key: %s', $item['key_']) . "\n";
                        echo "    " . LanguageManager::tf('Units: %s', $item['units']) . "\n";
                        echo "    " . LanguageManager::t('Type: ') . $this->getValueTypeName($item['value_type']) . "\n\n";
                    }
                }
            }
            
            echo str_repeat("=", 80) . "\n\n";
        }
        
        echo LanguageManager::t('=== Suggested search patterns ===') . "\n";
        echo LanguageManager::t('Based on the scan results, try the following search patterns:') . "\n\n";
        echo LanguageManager::t('Possible CPU utilization item names:') . "\n";
        echo "- CPU utilization\n";
        echo "- CPU usage\n";
        echo "- Processor time\n";
        echo "- CPU idle time\n\n";
        
        echo LanguageManager::t('Possible CPU count item names:') . "\n";
        echo "- Number of CPUs\n";
        echo "- CPU cores\n";
        echo "- Processors\n\n";
        
        echo LanguageManager::t('Possible memory utilization item names:') . "\n";
        echo "- Memory utilization\n";
        echo "- Memory usage\n";
        echo "- Available memory\n\n";
        
        echo LanguageManager::t('Possible total memory item names:') . "\n";
        echo "- Total memory\n";
        echo "- Physical memory\n";
        echo "- Memory size\n";
        
        exit;
    }
    
    private function getValueTypeName($valueType) {
        $types = [
            ITEM_VALUE_TYPE_FLOAT => 'Float',
            ITEM_VALUE_TYPE_STR => 'Character',
            ITEM_VALUE_TYPE_LOG => 'Log',
            ITEM_VALUE_TYPE_UINT64 => 'Unsigned integer',
            ITEM_VALUE_TYPE_TEXT => 'Text'
        ];
        
        return LanguageManager::t($types[$valueType] ?? 'Unknown');
    }
}
