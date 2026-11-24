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

require_once dirname(__DIR__) . '/lib/PdfGenerator.php';
use Modules\ZabbixReports\Lib\PdfGenerator;

class DailyReportDebug extends CController {

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
        $yesterday = strtotime('yesterday');
        $from = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
        $till = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));

        // Get one host to test items
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED],
            'limit' => 3
        ]);

        $debugInfo = [];
        
        foreach ($hosts as $host) {
            $hostDebug = [
                'hostid' => $host['hostid'],
                'hostname' => $host['name'],
                'items' => []
            ];
            
            // Get all items of this host
            $allItems = API::Item()->get([
                'output' => ['itemid', 'name', 'key_'],
                'hostids' => $host['hostid'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'limit' => 50
            ]);
            
            $hostDebug['total_items'] = count($allItems);
            
            // Find CPU-related items
            $cpuItems = [];
            $memItems = [];
            
            foreach ($allItems as $item) {
                if (stripos($item['name'], 'cpu') !== false || stripos($item['key_'], 'cpu') !== false) {
                    $cpuItems[] = [
                        'name' => $item['name'],
                        'key' => $item['key_'],
                        'itemid' => $item['itemid']
                    ];
                }
                
                if (stripos($item['name'], 'mem') !== false || stripos($item['key_'], 'mem') !== false) {
                    $memItems[] = [
                        'name' => $item['name'],
                        'key' => $item['key_'],
                        'itemid' => $item['itemid']
                    ];
                }
            }
            
            $hostDebug['cpu_items'] = $cpuItems;
            $hostDebug['memory_items'] = $memItems;
            
            // Test ItemFinder
            $yesterday = strtotime('yesterday');
            $testFrom = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            $testTill = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            
            $hostDebug['itemfinder_results'] = [
                'cpu_utilization' => ItemFinder::findCpuUtilization($host['hostid'], $testFrom, $testTill),
                'cpu_count' => ItemFinder::findCpuCount($host['hostid'], $testFrom, $testTill),
                'memory_utilization' => ItemFinder::findMemoryUtilization($host['hostid'], $testFrom, $testTill),
                'memory_total' => ItemFinder::findMemoryTotal($host['hostid'], $testFrom, $testTill)
            ];
            
            $debugInfo[] = $hostDebug;
        }
        
        // Output debug information
        header('Content-Type: text/plain; charset=UTF-8');
        echo LanguageManager::t('=== Zabbix item diagnostics ===') . "\n\n";
        echo LanguageManager::tf('Time range: %s to %s', date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $till)) . "\n\n";
        
        foreach ($debugInfo as $hostInfo) {
            echo LanguageManager::tf('Host: %s (ID: %s)', $hostInfo['hostname'], $hostInfo['hostid']) . "\n";
            echo LanguageManager::tf('Total items: %d', $hostInfo['total_items']) . "\n\n";
            
            echo LanguageManager::t('CPU items:') . "\n";
            if (empty($hostInfo['cpu_items'])) {
                echo "  " . LanguageManager::t('No CPU items found') . "\n";
            } else {
                foreach ($hostInfo['cpu_items'] as $item) {
                    echo "  " . LanguageManager::tf('- Name: %s', $item['name']) . "\n";
                    echo "    " . LanguageManager::tf('Key: %s', $item['key']) . "\n";
                    echo "    ID: {$item['itemid']}\n\n";
                }
            }
            
            echo LanguageManager::t('Memory items:') . "\n";
            if (empty($hostInfo['memory_items'])) {
                echo "  " . LanguageManager::t('No memory items found') . "\n";
            } else {
                foreach ($hostInfo['memory_items'] as $item) {
                    echo "  " . LanguageManager::tf('- Name: %s', $item['name']) . "\n";
                    echo "    " . LanguageManager::tf('Key: %s', $item['key']) . "\n";
                    echo "    ID: {$item['itemid']}\n\n";
                }
            }
            
            echo str_repeat("-", 80) . "\n";
            
            echo LanguageManager::t('ItemFinder test results:') . "\n";
            foreach ($hostInfo['itemfinder_results'] as $type => $result) {
                echo "  {$type}: ";
                if ($result) {
                    echo LanguageManager::tf('Found - name: %s, key: %s, value: ', $result['item']['name'], $result['item']['key_']) .
                         ($result['value'] !== null ? $result['value'] : 'N/A') . "\n";
                } else {
                    echo LanguageManager::t('Not found') . "\n";
                }
            }
            
            echo str_repeat("=", 80) . "\n\n";
        }
        
        echo LanguageManager::t('=== Item search suggestions ===') . "\n";
        echo LanguageManager::t('The output above shows the actual item names and keys.') . "\n";
        echo LanguageManager::t('Look for items containing the following keywords:') . "\n";
        echo LanguageManager::t('- CPU utilization: \'utilization\', \'usage\', \'util\'') . "\n";
        echo LanguageManager::t('- CPU count: \'number\', \'cores\', \'count\'') . "\n";
        echo LanguageManager::t('- Memory utilization: \'utilization\', \'usage\', \'util\'') . "\n";
        echo LanguageManager::t('- Total memory: \'total\', \'size\'') . "\n";
        
        exit;
    }
}
