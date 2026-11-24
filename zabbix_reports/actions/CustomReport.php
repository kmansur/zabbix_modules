<?php
declare(strict_types = 0);

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

class CustomReport extends CController {

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
            'from_date' => 'string',
            'to_date' => 'string',
            'generate' => 'string',
            'groupid' => 'string'
        ];
        
        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // Set the default dates: start date 7 days ago, end date today
        $defaultFromDate = date('Y-m-d', strtotime('-7 days'));
        $defaultToDate = date('Y-m-d');
        
        // Get the dates entered by the user, using the defaults when empty
        $fromDate = $this->getInput('from_date', $defaultFromDate);
        $toDate = $this->getInput('to_date', $defaultToDate);
        $generate = !empty($this->getInput('generate', ''));
        $groupId = $this->getInput('groupid', '');

        // Get all host groups (for the filter dropdown)
        $allGroups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'with_monitored_hosts' => true
        ]);

        $data = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'generate' => $generate,
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese(),
            'all_groups' => $allGroups,
            'filter_groupid' => $groupId,
            'filter_group_name' => $this->getGroupName($groupId, $allGroups)
        ];

        // If the user clicked generate report
        if ($generate && !empty($fromDate) && !empty($toDate)) {
            // Validate the date format and convert it to timestamps
            $fromTimestamp = strtotime($fromDate . ' 00:00:00');
            $toTimestamp = strtotime($toDate . ' 23:59:59');
            
            if ($fromTimestamp === false || $toTimestamp === false) {
                $data['error'] = LanguageManager::t('Invalid date format');
            } elseif ($fromTimestamp > $toTimestamp) {
                $data['error'] = LanguageManager::t('The start date cannot be later than the end date.');
            } elseif (($toTimestamp - $fromTimestamp) > (90 * 24 * 60 * 60)) {
                $data['error'] = LanguageManager::t('The selected date range cannot exceed 90 days.');
            } else {
                // Generate the actual report data
                $reportData = $this->generateReportData($fromTimestamp, $toTimestamp, $groupId);
                $data = array_merge($data, $reportData);
                $data['report_generated'] = true;
                $data['period_text'] = $fromDate . ' - ' . $toDate;
            }
        }

        // Add the title to the data
        $data['title'] = LanguageManager::t('Custom Report');
        
        $response = new CControllerResponseData($data);
        
        // Set the response title explicitly (required by Zabbix 6.0)
        $response->setTitle(LanguageManager::t('Custom Report'));
        
        $this->setResponse($response);
    }

    private function generateReportData(int $fromTimestamp, int $toTimestamp, string $groupId = ''): array {
        try {
            // Get all hosts (with group filter)
            $hostParams = [
                'output' => ['hostid', 'name'],
                'filter' => ['status' => HOST_STATUS_MONITORED]
            ];
            if ($groupId !== '') {
                $hostParams['groupids'] = [$groupId];
            }
            $hosts = API::Host()->get($hostParams);

            $filteredHostIds = array_column($hosts, 'hostid');

            // Use ProblemFinder to get all alarms that overlap the report period
            $problemResult = ProblemFinder::getProblemsInPeriod($fromTimestamp, $toTimestamp, 500, $filteredHostIds);
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
            $topProblemHosts = $alertResult['topHosts'];

            $hostMetrics = HostMetrics::build($hosts, $fromTimestamp, $toTimestamp);

            return [
                'problem_count' => $problemCount,
                'resolved_count' => $resolvedCount,
                'alert_info' => $alertInfo,
                'hosts_by_group' => $hostMetrics['hosts_by_group'],
                'top_problem_hosts' => $topProblemHosts,
                'top_cpu_hosts' => $hostMetrics['top_cpu_hosts'],
                'top_mem_hosts' => $hostMetrics['top_mem_hosts'],
                'cpu_total' => $hostMetrics['cpu_total'],
                'mem_total' => $hostMetrics['mem_total']
            ];
            
        } catch (Exception $e) {
            return [
                'error' => 'Error generating report: ' . $e->getMessage(),
                'problem_count' => 0,
                'resolved_count' => 0,
                'alert_info' => [],
                'hosts_by_group' => [],
                'top_problem_hosts' => [],
                'top_cpu_hosts' => [],
                'top_mem_hosts' => [],
                'cpu_total' => [],
                'mem_total' => []
            ];
        }
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
