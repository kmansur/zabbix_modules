<?php declare(strict_types = 0);

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/PdfGenerator.php';
require_once dirname(__DIR__) . '/lib/ProblemFinder.php';

use Modules\ZabbixReports\Lib\ItemFinder;
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\PdfGenerator;
use Modules\ZabbixReports\Lib\ProblemFinder;

class CustomReportExport extends CController {

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
            'format' => 'string',
            'groupid' => 'string'
        ];
        
        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        try {
            $language = LanguageManager::getCurrentLanguage();

            $fromDate = $this->getInput('from_date');
            $toDate = $this->getInput('to_date');
            $format = $this->getInput('format', 'pdf');
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

            // Validate the dates
            $fromTimestamp = strtotime($fromDate . ' 00:00:00');
            $toTimestamp = strtotime($toDate . ' 23:59:59');
            
            if ($fromTimestamp === false || $toTimestamp === false) {
                throw new \Exception(LanguageManager::t('Invalid date format'));
            }

            // Regenerate the report data
            $reportData = $this->generateReportData($fromTimestamp, $toTimestamp, $groupId);

            // Only PDF export is supported (modeled on the daily report export)
            $this->exportToPdf($reportData, $fromDate, $toDate, $groupName);

        } catch (\Exception $e) {
            error_log("Custom Report Export Error: " . $e->getMessage());
            $this->setResponse(new CControllerResponseFatal($e->getMessage()));
        }
    }

    private function generateReportData(int $fromTimestamp, int $toTimestamp, string $groupId = ''): array {
        // Get hosts (with group filter support)
        $hostOptions = [
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED]
        ];
        if ($groupId !== '') {
            $hostOptions['groupids'] = [$groupId];
        }
        $filteredHosts = API::Host()->get($hostOptions);
        $filteredHostIds = array_column($filteredHosts, 'hostid');

        // Use ProblemFinder to get all alarms that overlap the report period
        $problemResult = ProblemFinder::getProblemsInPeriod($fromTimestamp, $toTimestamp, 500, $filteredHostIds);
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

        // Part 2: host information grouped by host group
        $hostsByGroup = [];
        $cpuUsage = [];
        $memUsage = [];
        $cpuTotal = [];
        $memTotal = [];
        
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
            
            // Use ItemFinder to find CPU utilization
            $cpuUtilResult = ItemFinder::findCpuUtilization($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($cpuUtilResult && $cpuUtilResult['value'] !== null) {
                $cpuUsage[$host['name']] = $cpuUtilResult['value'];
                $hostInfo['cpu_usage'] = number_format($cpuUtilResult['value'], 2) . '%';
            }
            
            // Use ItemFinder to find the CPU count
            $cpuCountResult = ItemFinder::findCpuCount($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($cpuCountResult && $cpuCountResult['value'] !== null) {
                $cpuTotal[$host['name']] = $cpuCountResult['value'];
                $hostInfo['cpu_total'] = $cpuCountResult['value'];
            }
            
            // Use ItemFinder to find memory utilization
            $memUtilResult = ItemFinder::findMemoryUtilization($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($memUtilResult && $memUtilResult['value'] !== null) {
                $memUsage[$host['name']] = $memUtilResult['value'];
                $hostInfo['mem_usage'] = number_format($memUtilResult['value'], 2) . '%';
            }
            
            // Use ItemFinder to find total memory
            $memTotalResult = ItemFinder::findMemorySize($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($memTotalResult && $memTotalResult['value'] !== null) {
                $memTotal[$host['name']] = $memTotalResult['value'];
                $hostInfo['mem_total'] = number_format($memTotalResult['value'] / (1024*1024*1024), 2);
            }
            
            // Add the host to its group
            $hostsByGroup[$hostGroupName][] = $hostInfo;
        }
        arsort($cpuUsage);
        arsort($memUsage);
        $topCpuHosts = array_slice($cpuUsage, 0, 10, true);
        $topMemHosts = array_slice($memUsage, 0, 10, true);

        // Prepare the data (modeled on the daily report format)
        return [
            'date' => date('Y-m-d', $fromTimestamp),
            'report_date' => date('Y-m-d', $fromTimestamp),
            'problemCount' => $problemCount,
            'resolvedCount' => $resolvedCount,
            'alertInfo' => $alertInfo,
            'hostsByGroup' => $hostsByGroup,
            // Keep compatibility with the old format
            'topHosts' => $topHosts,
            'topCpuHosts' => $topCpuHosts,
            'topMemHosts' => $topMemHosts,
            'cpuTotal' => $cpuTotal,
            'memTotal' => $memTotal
        ];
    }

    private function exportToPdf(array $data, string $fromDate, string $toDate, string $groupName = ''): void {
        // Generate the PDF (strictly following the daily report export)
        $pdfTitle = LanguageManager::t('Custom Report') . ' - ' . $fromDate . ' - ' . $toDate;
        if ($groupName !== '') {
            $pdfTitle .= ' [' . $groupName . ']';
        }
        $pdfGenerator = new PdfGenerator($pdfTitle);
        $pdfGenerator->setData($data);
        $pdfContent = $pdfGenerator->generate();

        // Check whether it is HTML (fallback when no PDF library is available)
        if (strpos($pdfContent, '<!DOCTYPE html>') === 0) {
            // Return an HTML file that the user can print to PDF
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="custom_report_' . str_replace('-', '', $fromDate) . '_' . str_replace('-', '', $toDate) . '.html"');
        } else {
            // Return the actual PDF file
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="custom_report_' . str_replace('-', '', $fromDate) . '_' . str_replace('-', '', $toDate) . '.pdf"');
        }
        
        echo $pdfContent;
        exit;
    }


}