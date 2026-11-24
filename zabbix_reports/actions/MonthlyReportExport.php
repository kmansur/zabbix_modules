<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/PdfGenerator.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ProblemFinder.php';
use Modules\ZabbixReports\Lib\PdfGenerator;
use Modules\ZabbixReports\Lib\ItemFinder;
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ProblemFinder;

class MonthlyReportExport extends CController {

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
            'format' => 'string',
            'groupid' => 'string',
            'year' => 'string',
            'month' => 'string'
        ];
        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $groupId = $this->getInput('groupid', '');
        $groupName = '';
        if ($groupId !== '') {
            $groups = API::HostGroup()->get([
                'output' => ['name'],
                'groupids' => [$groupId],
                'limit' => 1
            ]);
            if (!empty($groups)) {
                $groupName = $groups[0]['name'];
            }
        }

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

        // Use ProblemFinder to get all alarms that overlap the report period
        // First get the hosts (with group filter support), used to filter alarms
        $hostOptions = [
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED]
        ];
        if ($groupId !== '') {
            $hostOptions['groupids'] = [$groupId];
        }
        $filteredHosts = API::Host()->get($hostOptions);
        $filteredHostIds = array_column($filteredHosts, 'hostid');

        $problemResult = ProblemFinder::getProblemsInPeriod($from, $till, 500, $filteredHostIds);
        $problemCount = $problemResult['problemCount'];
        $resolvedCount = $problemResult['resolvedCount'];
        $events = $problemResult['problemEvents'];
        $recoveryMap = $problemResult['recoveryMap'];
        $triggerHostMap = $problemResult['triggerHostMap'];
        $triggerStatusMap = $problemResult['triggerStatusMap'] ?? [];

        // Build the alarm information
        $alertInfo = [];
        $hostCounts = [];
        foreach ($events as $event) {
            if (!empty($event['hosts'][0]['name'])) {
                $hostName = $event['hosts'][0]['name'];
            } elseif (isset($triggerHostMap[$event['objectid']])) {
                $hostName = $triggerHostMap[$event['objectid']];
            } else {
                $hostName = LanguageManager::t('Unknown Host');
            }
            $alertTime = date('Y-m-d H:i:s', $event['clock']);
            $recoveryTime = null;
            if (!empty($event['r_eventid']) && isset($recoveryMap[$event['r_eventid']])) {
                $recoveryTime = date('Y-m-d H:i:s', $recoveryMap[$event['r_eventid']]);
            }
            $alertInfo[] = [
                'host' => $hostName,
                'alert' => $event['name'],
                'time' => $alertTime,
                'recovery_time' => $recoveryTime,
                'severity' => (int)($event['severity'] ?? 0),
                'trigger_disabled' => isset($triggerStatusMap[$event['objectid']]) && ($triggerStatusMap[$event['objectid']] === 'disabled' || $triggerStatusMap[$event['objectid']] === 'deleted')
            ];
            $hostCounts[$hostName] = ($hostCounts[$hostName] ?? 0) + 1;
        }
        arsort($hostCounts);
        $topHosts = array_slice($hostCounts, 0, 10, true);

        // Get all hosts (using the filtered host list)
        $hosts = $filteredHosts;

        // Get the host group map
        $hostGroups = [];
        if (!empty($hosts)) {
            $hostids = array_column($hosts, 'hostid');
            $hostGroupMap = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'hostids' => $hostids
            ]);
            
            // Build the host-to-group map
            foreach ($hostGroupMap as $group) {
                $groupHosts = API::Host()->get([
                    'output' => ['hostid'],
                    'groupids' => $group['groupid'],
                    'filter' => ['status' => HOST_STATUS_MONITORED]
                ]);
                
                foreach ($groupHosts as $gh) {
                    if (!isset($hostGroups[$gh['hostid']])) {
                        $hostGroups[$gh['hostid']] = [];
                    }
                    $hostGroups[$gh['hostid']][] = $group;
                }
            }
        }

        $cpuUsage = [];
        $memUsage = [];
        foreach ($hosts as $host) {
            // Use ItemFinder to get CPU utilization
            $cpuUtilResult = ItemFinder::findCpuUtilization($host['hostid'], $from, $till);
            if ($cpuUtilResult && $cpuUtilResult['value'] !== null) {
                $cpuUsage[$host['name']] = $cpuUtilResult['value'];
            }

            // Use ItemFinder to get memory utilization
            $memUtilResult = ItemFinder::findMemoryUtilization($host['hostid'], $from, $till);
            if ($memUtilResult && $memUtilResult['value'] !== null) {
                $memUsage[$host['name']] = $memUtilResult['value'];
            }
        }
        arsort($cpuUsage);
        arsort($memUsage);
        $topCpuHosts = array_slice($cpuUsage, 0, 10, true);
        $topMemHosts = array_slice($memUsage, 0, 10, true);

        // Part 2: host information grouped by host group
        $hostsByGroup = [];
        $cpuTotal = [];
        $memTotal = [];
        
        // Iterate over the hosts again to get the full information
        foreach ($hosts as $host) {
            // Group hosts
            $groups = isset($hostGroups[$host['hostid']]) ? $hostGroups[$host['hostid']] : [];
            $hostGroupName = !empty($groups) ? $groups[0]['name'] : 'No Group';
            if (!isset($hostsByGroup[$hostGroupName])) {
                $hostsByGroup[$hostGroupName] = [];
            }
            
            // Initialize host information
            $hostInfo = [
                'name' => $host['name'],
                'cpu_usage' => 'N/A',
                'cpu_total' => 'N/A',
                'mem_usage' => 'N/A',
                'mem_total' => 'N/A'
            ];
            
            // If CPU utilization data already exists, use it
            if (isset($cpuUsage[$host['name']])) {
                $hostInfo['cpu_usage'] = number_format($cpuUsage[$host['name']], 2) . '%';
            }
            
            // If memory utilization data already exists, use it
            if (isset($memUsage[$host['name']])) {
                $hostInfo['mem_usage'] = number_format($memUsage[$host['name']], 2) . '%';
            }

            // Use ItemFinder to get the CPU count
            $cpuCountResult = ItemFinder::findCpuCount($host['hostid'], $from, $till);
            if ($cpuCountResult && $cpuCountResult['value'] !== null) {
                $cpuTotal[$host['name']] = $cpuCountResult['value'];
                $hostInfo['cpu_total'] = $cpuCountResult['value'];
            }
            
            // Use ItemFinder to get total memory
            $memTotalResult = ItemFinder::findMemorySize($host['hostid'], $from, $till);
            if ($memTotalResult && $memTotalResult['value'] !== null) {
                $memTotal[$host['name']] = $memTotalResult['value'];
                $hostInfo['mem_total'] = number_format($memTotalResult['value'] / (1024*1024*1024), 2);
            }
            
            // Add the host to its group
            $hostsByGroup[$hostGroupName][] = $hostInfo;
        }

        // Prepare the PDF data (new format)
        $reportData = [
            'date' => date('Y-m', $monthStart),
            'report_date' => date('Y-m', $monthStart),
            'problemCount' => $problemCount,
            'resolvedCount' => $resolvedCount,
            'alertInfo' => $alertInfo,
            'hostsByGroup' => $hostsByGroup,
            // Keep compatibility
            'topHosts' => $topHosts,
            'topCpuHosts' => $topCpuHosts,
            'topMemHosts' => $topMemHosts,
            'cpuTotal' => $cpuTotal,
            'memTotal' => $memTotal
        ];

        // Generate the PDF
        $pdfTitle = LanguageManager::t('Zabbix Monthly Report') . ' - ' . date('Y-m', $monthStart);
        if ($groupName !== '') {
            $pdfTitle .= ' [' . $groupName . ']';
        }
        $pdfGenerator = new PdfGenerator($pdfTitle);
        $pdfGenerator->setData($reportData);
        $pdfContent = $pdfGenerator->generate();

        // Check whether it is HTML (fallback when no PDF library is available)
        if (strpos($pdfContent, '<!DOCTYPE html>') === 0) {
            // Return an HTML file that the user can print to PDF
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="monthly_report_' . date('Y-m', $monthStart) . '.html"');
        } else {
            // Return the actual PDF file
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="monthly_report_' . date('Y-m', $monthStart) . '.pdf"');
        }
        
        echo $pdfContent;
        exit;
    }
}
