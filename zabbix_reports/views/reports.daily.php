<?php
require_once dirname(__DIR__).'/lib/ModuleNotice.php';
echo \ZabbixModuleNotice::reminder(dirname(__DIR__));


require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
require_once dirname(__DIR__) . '/lib/ReportViewHelper.php';
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ViewRenderer;
use Modules\ZabbixReports\Lib\ReportViewHelper;

$pageTitle = $data['title'] ?? LanguageManager::t('Daily Report');
$styleTag = ReportViewHelper::getStyleTag();

// ===== Build the page content =====
$content = (new CDiv())->addClass('rpt-container');

// Title + action buttons
$exportParams = ['groupid' => $data['filter_groupid'] ?? ''];
$filterYear = $data['filter_year'] ?? (int)date('Y');
$filterMonth = $data['filter_month'] ?? (int)date('m');
$filterDay = $data['filter_day'] ?? ((int)date('d') - 1);
$exportParams['year'] = $filterYear;
$exportParams['month'] = $filterMonth;
$exportParams['day'] = $filterDay;

$titleText = LanguageManager::t('Zabbix Daily Report') . ' - ' . $data['report_date'];
if (!empty($data['filter_group_name'])) {
    $titleText .= ' [' . $data['filter_group_name'] . ']';
}

// Build the year/month/day dropdown options
$currentYear = (int)date('Y');
$yearOptions = [
    ['value' => (string)$currentYear, 'label' => (string)$currentYear],
    ['value' => (string)($currentYear - 1), 'label' => (string)($currentYear - 1)]
];
$monthOptions = [];
for ($m = 1; $m <= 12; $m++) {
    $monthOptions[] = ['value' => (string)$m, 'label' => sprintf('%02d', $m)];
}
$maxDay = (int)date('t', mktime(0, 0, 0, $filterMonth, 1, $filterYear));
$dayOptions = [];
for ($d = 1; $d <= $maxDay; $d++) {
    $dayOptions[] = ['value' => (string)$d, 'label' => sprintf('%02d', $d)];
}

$content->addItem(
    ReportViewHelper::buildHeader(
        $titleText,
        "\u{1F4C5}",
        'reports.daily.export',
        $exportParams,
        [
            'action_name' => 'reports.daily',
            'all_groups' => $data['all_groups'] ?? [],
            'filter_groupid' => $data['filter_groupid'] ?? '',
            'date_selects' => [
                ['name' => 'year', 'selected' => $filterYear, 'options' => $yearOptions],
                ['name' => 'month', 'selected' => $filterMonth, 'options' => $monthOptions],
                ['name' => 'day', 'selected' => $filterDay, 'options' => $dayOptions]
            ]
        ]
    )
);

// Summary statistics cards
$content->addItem(ReportViewHelper::buildStatsGrid($data));

// Top CPU / Memory in two columns
$content->addItem(ReportViewHelper::buildTopResourceSection($data));

// Severity radar + host group problem distribution, two-column charts
$content->addItem(ReportViewHelper::buildChartsRow($data['alert_info'] ?? [], $data['hosts_by_group'] ?? []));

// Problem host tags
$content->addItem(ReportViewHelper::buildTopProblemHosts($data['top_problem_hosts'] ?? []));

// Alarm information
$content->addItem(ReportViewHelper::buildAlertSection($data['alert_info'] ?? []));

// Host group information
$content->addItem(ReportViewHelper::buildHostGroupSection($data['hosts_by_group'] ?? []));

// Render the page
ViewRenderer::render($pageTitle, $styleTag, $content);

// JS enhancements
echo ReportViewHelper::getScriptTag();
