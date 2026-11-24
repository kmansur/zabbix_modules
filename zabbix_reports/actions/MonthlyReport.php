<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ProblemFinder.php';
require_once dirname(__DIR__) . '/lib/HostMetrics.php';
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ProblemFinder;
use Modules\ZabbixReports\Lib\HostMetrics;

class MonthlyReport extends CController {

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
            'groupid' => 'string',
            'year' => 'string',
            'month' => 'string',
            'apply' => 'string'
        ];
        $ret = $this->validateInput($fields);
        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $groupId = $this->getInput('groupid', '');

        // Get all host groups (for the filter dropdown)
        $allGroups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'with_monitored_hosts' => true
        ]);

        $currentYear = (int)date('Y');
        $year = (int)$this->getInput('year', $currentYear);
        $month = (int)$this->getInput('month', date('m'));
        if ($year < $currentYear - 1 || $year > $currentYear) {
            $year = $currentYear;
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }

        $monthStart = mktime(0, 0, 0, $month, 1, $year);
        $monthEnd = mktime(23, 59, 59, $month + 1, 0, $year);
        $from = $monthStart;
        $till = $monthEnd;

        // Get hosts (with group filter support)
        $hostOptions = [
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED]
        ];
        if ($groupId !== '') {
            $hostOptions['groupids'] = [$groupId];
        }
        $hosts = API::Host()->get($hostOptions);

        // Get the filtered host IDs
        $filteredHostIds = array_column($hosts, 'hostid');

        // Use ProblemFinder to get all alarms that overlap the report period
        $problemResult = ProblemFinder::getProblemsInPeriod($from, $till, 500, $filteredHostIds);
        $problemCount = $problemResult['problemCount'];
        $resolvedCount = $problemResult['resolvedCount'];
        $problemEvents = $problemResult['problemEvents'];
        $recoveryMap = $problemResult['recoveryMap'];
        $triggerHostMap = $problemResult['triggerHostMap'];
        $triggerStatusMap = $problemResult['triggerStatusMap'] ?? [];

        // Build the alarm information
        $alertResult = ProblemFinder::buildAlertInfo(
            $problemEvents, $recoveryMap, $triggerHostMap,
            LanguageManager::t('Unknown Host'), $triggerStatusMap
        );
        $alertInfo = $alertResult['alertInfo'];
        $hostCounts = $alertResult['hostCounts'];
        $topHosts = $alertResult['topHosts'];

        $hostMetrics = HostMetrics::build($hosts, $from, $till);

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Monthly Report'),
            'report_period' => LanguageManager::formatPeriod('monthly', $monthStart),
            'report_period_raw' => date('Y-m', $monthStart),
            'problem_count' => $problemCount,
            'resolved_count' => $resolvedCount,
            'alert_info' => $alertInfo,
            'hosts_by_group' => $hostMetrics['hosts_by_group'],
            'top_problem_hosts' => $topHosts,
            'top_cpu_hosts' => $hostMetrics['top_cpu_hosts'],
            'top_mem_hosts' => $hostMetrics['top_mem_hosts'],
            'cpu_total' => $hostMetrics['cpu_total'],
            'mem_total' => $hostMetrics['mem_total'],
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese(),
            'all_groups' => $allGroups,
            'filter_groupid' => $groupId,
            'filter_group_name' => $this->getGroupName($groupId, $allGroups),
            'filter_year' => $year,
            'filter_month' => $month
        ]);
        
        // Set the response title explicitly (required by Zabbix 6.0)
        $response->setTitle(LanguageManager::t('Monthly Report'));

        $this->setResponse($response);
    }

    private function getGroupName(string $groupId, array $allGroups): string {
        if ($groupId === '') return '';
        foreach ($allGroups as $group) {
            if ((string)$group['groupid'] === (string)$groupId) {
                return $group['name'];
            }
        }
        return '';
    }
}
