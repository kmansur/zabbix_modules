<?php

namespace Modules\ZabbixCmdb\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

class CmdbGroups extends CController {

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
            'sort' => 'string',
            'sortorder' => 'in ASC,DESC',
            'search' => 'string'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => LanguageManager::t('Invalid input parameters.')]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $sort = $this->getInput('sort', 'host_count');
        $sortorder = $this->getInput('sortorder', 'DESC');
        $search = $this->getInput('search', '');

        // Get the host group list
        $hostGroups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'sortorder' => 'ASC'
        ]);

        // Filter groups if there are search criteria
        if (!empty($search)) {
            $hostGroups = array_filter($hostGroups, function($group) use ($search) {
                return stripos($group['name'], $search) !== false;
            });
        }

        // Get details for each group
        $groupData = [];
        foreach ($hostGroups as $group) {
            // Get the hosts in the group
            $hosts = API::Host()->get([
                'output' => ['hostid', 'host', 'name', 'status'],
                'groupids' => [$group['groupid']],
                'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error']
            ]);

            $hostCount = count($hosts);
            $totalCpu = 0;
            $totalMemory = 0;

            // Use the same ordered rules as the host list and its totals.
            $items = ItemFinder::batchGetHostItems(array_column($hosts, 'hostid'), ['cpu_count', 'memory_total']);
            foreach ($items as $hostItems) {
                $totalCpu += (float) ($hostItems['cpu_count']['value'] ?? 0);
                $totalMemory += (float) ($hostItems['memory_total']['value'] ?? 0);
            }

            $groupData[] = [
                'groupid' => $group['groupid'],
                'name' => $group['name'],
                'host_count' => $hostCount,
                'total_cpu' => $totalCpu,
                'total_memory' => $totalMemory
            ];
        }

        // Sort the data according to the sort parameters
        if (!empty($groupData)) {
            usort($groupData, function($a, $b) use ($sort, $sortorder) {
                $valueA = $a[$sort] ?? 0;
                $valueB = $b[$sort] ?? 0;

                // Make sure numeric fields are compared correctly
                if (in_array($sort, ['host_count', 'total_cpu', 'total_memory'])) {
                    $valueA = (int)$valueA;
                    $valueB = (int)$valueB;
                } else {
                    $valueA = (string)$valueA;
                    $valueB = (string)$valueB;
                }

                if ($sortorder === 'DESC') {
                    return $valueB <=> $valueA;
                } else {
                    return $valueA <=> $valueB;
                }
            });
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Host Groups'),
            'groups' => $groupData,
            'sort' => $sort,
            'sortorder' => $sortorder,
            'search' => $search
        ]);
        
        // Set the response title explicitly (required by Zabbix 6.0)
        $response->setTitle(LanguageManager::t('Host Groups'));

        $this->setResponse($response);
    }
}