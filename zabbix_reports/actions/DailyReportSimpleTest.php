<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

use Modules\ZabbixReports\Lib\LanguageManager;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';

class DailyReportSimpleTest extends CController {

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
            'limit' => 1
        ]);
        
        if (empty($hosts)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo LanguageManager::t('No monitored hosts found.') . "\n";
            exit;
        }
        
        $host = $hosts[0];
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo LanguageManager::t('=== Quick item test ===') . "\n\n";
        echo LanguageManager::tf('Test host: %s (ID: %s)', $host['name'], $host['hostid']) . "\n\n";
        
        // Search all possible CPU and memory related items
        echo LanguageManager::t('=== Searching CPU items ===') . "\n";
        $this->searchItems($host['hostid'], ['cpu', 'processor', 'core']);
        
        echo "\n" . LanguageManager::t('=== Searching memory items ===') . "\n";
        $this->searchItems($host['hostid'], ['memory', 'mem', 'ram']);
        
        echo "\n" . LanguageManager::t('=== Wildcard search ===') . "\n";
        $this->wildcardSearch($host['hostid'], 'util');
        
        echo "\n" . LanguageManager::t('=== Search by key ===') . "\n";
        $this->keySearch($host['hostid'], ['system.cpu', 'vm.memory']);
        
        exit;
    }
    
    private function searchItems($hostid, $keywords) {
        foreach ($keywords as $keyword) {
            echo LanguageManager::tf('Search keyword: %s', $keyword) . "\n";
            
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['name' => $keyword],
                'searchWildcardsEnabled' => true,
                'limit' => 10
            ]);
            
            if (empty($items)) {
                echo "  " . LanguageManager::t('No matching items found') . "\n";
            } else {
                foreach ($items as $item) {
                    echo "  " . LanguageManager::tf('- %s (key: %s, units: %s)', $item['name'], $item['key_'], $item['units']) . "\n";
                }
            }
            echo "\n";
        }
    }
    
    private function wildcardSearch($hostid, $keyword) {
        echo LanguageManager::tf('Wildcard search: *%s*', $keyword) . "\n";
        
        $items = API::Item()->get([
            'output' => ['itemid', 'name', 'key_', 'units'],
            'hostids' => $hostid,
            'filter' => ['status' => ITEM_STATUS_ACTIVE],
            'search' => ['name' => $keyword],
            'searchWildcardsEnabled' => true,
            'limit' => 20
        ]);
        
        if (empty($items)) {
            echo "  " . LanguageManager::t('No matching items found') . "\n";
        } else {
            foreach ($items as $item) {
                echo "  " . LanguageManager::tf('- %s (key: %s, units: %s)', $item['name'], $item['key_'], $item['units']) . "\n";
            }
        }
        echo "\n";
    }
    
    private function keySearch($hostid, $keyPatterns) {
        foreach ($keyPatterns as $pattern) {
            echo LanguageManager::tf('Key pattern search: %s', $pattern) . "\n";
            
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'units'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'search' => ['key_' => $pattern],
                'searchWildcardsEnabled' => true,
                'limit' => 10
            ]);
            
            if (empty($items)) {
                echo "  " . LanguageManager::t('No matching items found') . "\n";
            } else {
                foreach ($items as $item) {
                    echo "  " . LanguageManager::tf('- %s (key: %s, units: %s)', $item['name'], $item['key_'], $item['units']) . "\n";
                }
            }
            echo "\n";
        }
    }
}
