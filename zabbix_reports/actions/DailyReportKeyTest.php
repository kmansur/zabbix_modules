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

class DailyReportKeyTest extends CController {

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
        
        // Get the first host for testing
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED],
            'limit' => 3  // Test the first 3 hosts
        ]);
        
        if (empty($hosts)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo LanguageManager::t('No monitored hosts found.') . "\n";
            exit;
        }
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo LanguageManager::t('=== Item key test tool ===') . "\n\n";
        echo LanguageManager::t('Testing the following item keys:') . "\n";
        echo LanguageManager::t('- system.cpu.num (total CPU cores)') . "\n";
        echo LanguageManager::t('- system.cpu.util (CPU utilization)') . "\n";
        echo LanguageManager::t('- vm.memory.size[total] (total memory)') . "\n";
        echo LanguageManager::t('- vm.memory.utilization (memory utilization)') . "\n\n";
        
        $yesterday = strtotime('yesterday');
        $from = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
        $till = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
        
        foreach ($hosts as $host) {
            echo LanguageManager::tf('Host: %s (ID: %s)', $host['name'], $host['hostid']) . "\n";
            echo str_repeat("=", 60) . "\n";
            
            // Test each specific key
            $this->testSpecificKey($host['hostid'], 'system.cpu.num', LanguageManager::t('Total CPU cores'));
            $this->testSpecificKey($host['hostid'], 'system.cpu.util', LanguageManager::t('CPU utilization'));
            $this->testSpecificKey($host['hostid'], 'vm.memory.size[total]', LanguageManager::t('Total memory'));
            $this->testSpecificKey($host['hostid'], 'vm.memory.utilization', LanguageManager::t('Memory utilization'));
            
            echo "\n" . LanguageManager::t('=== Testing with ItemFinder ===') . "\n";
            
            // Test with ItemFinder
            $cpuUtil = ItemFinder::findCpuUtilization($host['hostid'], $from, $till);
            $cpuCount = ItemFinder::findCpuCount($host['hostid'], $from, $till);
            $memUtil = ItemFinder::findMemoryUtilization($host['hostid'], $from, $till);
            $memTotal = ItemFinder::findMemoryTotal($host['hostid'], $from, $till);
            
            echo LanguageManager::t('CPU utilization: ') . ($cpuUtil ?
                LanguageManager::tf('Found item %s (key: %s) = ', $cpuUtil['item']['name'], $cpuUtil['item']['key_']) .
                ($cpuUtil['value'] !== null ? number_format($cpuUtil['value'], 2) . '%' : 'N/A') : 'N/A') . "\n";
                
            echo LanguageManager::t('CPU count: ') . ($cpuCount ?
                LanguageManager::tf('Found item %s (key: %s) = ', $cpuCount['item']['name'], $cpuCount['item']['key_']) .
                ($cpuCount['value'] !== null ? $cpuCount['value'] : 'N/A') : 'N/A') . "\n";
                
            echo LanguageManager::t('Memory utilization: ') . ($memUtil ?
                LanguageManager::tf('Found item %s (key: %s) = ', $memUtil['item']['name'], $memUtil['item']['key_']) .
                ($memUtil['value'] !== null ? number_format($memUtil['value'], 2) . '%' : 'N/A') : 'N/A') . "\n";
                
            echo LanguageManager::t('Total memory: ') . ($memTotal ?
                LanguageManager::tf('Found item %s (key: %s) = ', $memTotal['item']['name'], $memTotal['item']['key_']) .
                ($memTotal['value'] !== null ? number_format($memTotal['value'] / (1024*1024*1024), 2) . ' GB' : 'N/A') : 'N/A') . "\n";
            
            echo "\n" . str_repeat("-", 80) . "\n\n";
        }
        
        exit;
    }
    
    private function testSpecificKey($hostid, $key, $description) {
        echo LanguageManager::tf('Testing %s (key: %s):', $description, $key) . "\n";
        
        // Exact key match
        $items = API::Item()->get([
            'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'units'],
            'hostids' => $hostid,
            'filter' => [
                'status' => ITEM_STATUS_ACTIVE,
                'key_' => $key
            ]
        ]);
        
        if (empty($items)) {
            echo "  " . LanguageManager::t('No exact item match found') . "\n";
            
            // Try a fuzzy match
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['key_' => $key],
                'searchWildcardsEnabled' => true
            ]);
            
            if (!empty($items)) {
                echo "  " . LanguageManager::t('Partially matching items found:') . "\n";
                foreach ($items as $item) {
                    echo "    " . LanguageManager::tf('- %s (key: %s, last value: %s, units: %s)', $item['name'], $item['key_'], $item['lastvalue'], $item['units']) . "\n";
                }
            } else {
                echo "  " . LanguageManager::t('No matching items found') . "\n";
            }
        } else {
            $item = $items[0];
            echo "  " . LanguageManager::tf('Found item: %s', $item['name']) . "\n";
            echo "  " . LanguageManager::tf('Key: %s', $item['key_']) . "\n";
            echo "  " . LanguageManager::tf('Last value: %s', $item['lastvalue']) . "\n";
            echo "  " . LanguageManager::tf('Units: %s', $item['units']) . "\n";
            echo "  " . LanguageManager::t('Last updated: ') . date('Y-m-d H:i:s', $item['lastclock']) . "\n";
            
            // Try to get history data
            $yesterday = strtotime('yesterday');
            $from = mktime(0, 0, 0, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            $till = mktime(23, 59, 59, date('m', $yesterday), date('d', $yesterday), date('Y', $yesterday));
            
            $history = API::History()->get([
                'output' => ['value', 'clock'],
                'itemids' => $item['itemid'],
                'time_from' => $from,
                'time_till' => $till,
                'sortfield' => 'clock',
                'sortorder' => 'DESC',
                'limit' => 5
            ]);
            
            if (!empty($history)) {
                echo "  " . LanguageManager::t('Yesterday\'s history (last 5 records):') . "\n";
                foreach ($history as $h) {
                    echo "    " . date('Y-m-d H:i:s', $h['clock']) . " = {$h['value']}\n";
                }
            } else {
                echo "  " . LanguageManager::t('No history found for yesterday') . "\n";
            }
        }
        echo "\n";
    }
}
