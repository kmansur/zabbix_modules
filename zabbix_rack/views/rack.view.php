<?php
require_once dirname(__DIR__).'/lib/ModuleNotice.php';
echo \ZabbixModuleNotice::reminder(dirname(__DIR__));

/**
 * Rack view page
 */

// Load the language manager and the compatibility layer
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\ViewRenderer;

$lang = $data['lang'];
$rooms = $data['rooms'];
$racks = $data['racks'];
$currentRoomId = $data['current_room_id'];
$currentRackId = $data['current_rack_id'];
$currentRack = $data['current_rack'];
$hosts = $data['hosts'];
$hostsFront = $data['hosts_front'] ?? [];
$hostsBack = $data['hosts_back'] ?? [];
$chassisFront = $data['chassis_front'] ?? [];
$chassisBack = $data['chassis_back'] ?? [];
$search = $data['search'];
$searchResults = $data['search_results'];
$hostGroups = $data['host_groups'];
$showOverview = $data['show_overview'] ?? false;
$allRacksData = $data['all_racks_data'] ?? [];

// Like IPAM, uses native selects to avoid depending on the internal structure of custom components.
$buildSelect = static function (string $name, string $id, array $options, $selected = '') {
    $select = (new CTag('select', true))
        ->setAttribute('name', $name)
        ->setAttribute('id', $id);
    foreach ($options as $value => $label) {
        $option = (new CTag('option', true, htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')))
            ->setAttribute('value', (string) $value);
        if ((string) $value === (string) $selected) {
            $option->setAttribute('selected', 'selected');
        }
        $select->addItem($option);
    }
    return $select;
};

/**
 * Build the single-side rack visualization
 *
 * @param array<string, mixed> $rack
 * @param array<int, array<string, mixed>> $sideHosts
 */
$buildRackVisualSide = static function (array $rack, array $sideHosts, string $side) {
    $rackHeight = $rack['height'] ?? 42;
    $sideClass = ($side === 'back') ? ' rack-side-back' : ' rack-side-front';
    $sideLabel = ($side === 'back')
        ? LanguageManager::t('rack_side_back')
        : LanguageManager::t('rack_side_front');

    $occupiedSlots = [];
    foreach ($sideHosts as $host) {
        if ($host['u_start'] && $host['u_end']) {
            for ($u = $host['u_start']; $u <= $host['u_end']; $u++) {
                $occupiedSlots[$u] = $host;
            }
        }
    }

    $rackVisual = (new CDiv())
        ->addClass('rack-visual' . $sideClass)
        ->setAttribute('data-side', $side);
    $rackHeader = (new CDiv())->addClass('rack-header');
    $rackHeader->addItem('🗄️ ' . htmlspecialchars($rack['name']) . ' (' . $rackHeight . 'U)');
    $rackHeader->addItem(
        (new CSpan($sideLabel))->addClass('rack-side-badge')
    );
    $rackVisual->addItem($rackHeader);

    $rackUnits = (new CDiv())->addClass('rack-units');

    for ($u = $rackHeight; $u >= 1; $u--) {
        if (isset($occupiedSlots[$u])) {
            $host = $occupiedSlots[$u];
            if ((int) $u !== (int) $host['u_end']) {
                continue;
            }

            $uStart = (int) $host['u_start'];
            $uEnd = (int) $host['u_end'];
            $span = $uEnd - $uStart + 1;

            $unitRow = (new CDiv())
                ->addClass('rack-unit')
                ->setAttribute('data-u-span', (string) $span)
                ->setAttribute('style', '--u-span: ' . $span);

            if ($span > 1) {
                $numberCol = (new CDiv())->addClass('rack-unit-number rack-unit-number-span');
                $numberCol->addItem(new CSpan('U' . $uEnd));
                $numberCol->addItem(
                    (new CSpan('U' . $uStart))->addClass('u-label-small')
                );
            } else {
                $numberCol = (new CDiv('U' . $u))->addClass('rack-unit-number');
            }
            $unitRow->addItem($numberCol);

            $problemCount = $host['problem_count'] ?? 0;
            $maxSeverity = $host['max_severity'] ?? -1;
            $hasProblem = $problemCount > 0;

            $slotClasses = ['rack-unit-slot', 'occupied'];

            if ($host['status'] == 1) {
                $slotClasses[] = 'status-disabled';
            } elseif ($hasProblem && $maxSeverity >= 0) {
                switch ($maxSeverity) {
                    case 5: $slotClasses[] = 'severity-disaster'; break;
                    case 4: $slotClasses[] = 'severity-high'; break;
                    case 3: $slotClasses[] = 'severity-average'; break;
                    case 2: $slotClasses[] = 'severity-warning'; break;
                    case 1: $slotClasses[] = 'severity-info'; break;
                    case 0: $slotClasses[] = 'severity-not-classified'; break;
                    default: $slotClasses[] = 'has-problem';
                }
            } else {
                $slotClasses[] = 'no-problem';
            }

            $hostGroupsText = is_array($host['groups'] ?? null)
                ? implode(', ', $host['groups'])
                : (string) ($host['groups'] ?? '');
            $hostIpText = (string) ($host['main_ip'] ?? ($host['ip'] ?? ''));
            if ($hostIpText === '') {
                $hostIpText = '-';
            }
            if ($hostGroupsText === '') {
                $hostGroupsText = '-';
            }

            $hostDataJson = json_encode([
                'hostid' => $host['hostid'],
                'name' => $host['name'],
                'host' => $host['host'],
                'ip' => $host['main_ip'] ?? ($host['ip'] ?? ''),
                'groups' => $hostGroupsText,
                'u_start' => $host['u_start'],
                'u_end' => $host['u_end'],
                'side' => $host['side'] ?? $side,
                'status' => $host['status'] == 0 ? LanguageManager::t('enabled') : LanguageManager::t('disabled'),
                'problem_count' => $problemCount,
                'custom' => !empty($host['custom'])
            ]);

            $slotDiv = (new CDiv())
                ->addClass(implode(' ', $slotClasses))
                ->setAttribute('data-host', $hostDataJson)
                ->setAttribute('data-u-span', (string) $span)
                ->setAttribute('onclick', 'openEditModal(this)');

            $slotContent = (new CDiv())->addClass('host-slot-content');
            $hostNameSpan = (new CSpan())->addClass('host-name');
            $hostNameSpan->addItem(htmlspecialchars($host['name']));
            $hostNameSpan->addItem(
                (new CSpan(' (' . htmlspecialchars($hostIpText) . ', ' . htmlspecialchars($hostGroupsText) . ')'))
                    ->addClass('host-slot-meta')
            );
            $slotContent->addItem($hostNameSpan);
            if ($hasProblem) {
                $slotContent->addItem(
                    (new CSpan((string)$problemCount))
                        ->addClass('problem-badge')
                        ->setAttribute('onclick', "event.stopPropagation();showProblems('" . $host['hostid'] . "','" . htmlspecialchars(addslashes($host['name'])) . "')")
                        ->setAttribute('title', $problemCount . ' ' . LanguageManager::t('problems'))
                );
            }
            $slotDiv->addItem($slotContent);
            $unitRow->addItem($slotDiv);
            $rackUnits->addItem($unitRow);
            continue;
        }

        $unitRow = (new CDiv())->addClass('rack-unit');
        $unitRow->addItem(
            (new CDiv('U' . $u))->addClass('rack-unit-number')
        );
        $unitRow->addItem(
            (new CDiv())
                ->addClass('rack-unit-slot')
                ->setAttribute('data-u', (string)$u)
        );
        $rackUnits->addItem($unitRow);
    }

    $rackVisual->addItem($rackUnits);

    return $rackVisual;
};

$pageTitle = LanguageManager::t('rack_view');

// Add CSS styles - modeled on the CMDB module style
$styleTag = new CTag('style', true,
    file_get_contents(dirname(__DIR__) . '/assets/css/rack.view.css')
    . file_get_contents(dirname(__DIR__) . '/assets/css/element.css')
);


// ==================== Build the page content (CTag mode, consistent with rack.manage.php) ====================

$content = (new CDiv())->addClass('rack-page-wrapper rack-ui');
$container = (new CDiv())->addClass('rack-page-container');

// ── Top filter bar ──
$filterBar = (new CDiv())->addClass('rack-top-filter');
$filterForm = (new CTag('form', true))
    ->setAttribute('id', 'filter-form')
    ->setAttribute('method', 'get')
    ->setAttribute('action', 'zabbix.php');
$filterForm->addItem(
    (new CInput('hidden', 'action', 'rack.view'))
);

// Search box
$searchItem = (new CDiv())->addClass('filter-item');
$searchItem->addItem(
    (new CTag('label', true, '🔍 ' . LanguageManager::t('search')))->setAttribute('for', 'search-input')
);
$searchItem->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'text')
        ->setAttribute('name', 'search')
        ->setAttribute('id', 'search-input')
        ->setAttribute('value', htmlspecialchars($search))
        ->setAttribute('placeholder', LanguageManager::t('search_placeholder'))
);
$filterForm->addItem($searchItem);

// Room selection - native select
$roomFilterItem = (new CDiv())->addClass('filter-item');
$roomFilterItem->addItem(
    (new CTag('label', true, '🏢 ' . LanguageManager::t('room')))->setAttribute('for', 'room-select')
);
$roomOptions = ['' => LanguageManager::t('select_room')];
foreach ($rooms as $room) {
    $roomOptions[$room['id']] = $room['name'];
}
$roomFilterSelect = $buildSelect('room_id', 'room-select', $roomOptions, $currentRoomId);
$roomFilterItem->addItem($roomFilterSelect);
$filterForm->addItem($roomFilterItem);

// Rack selection - native select
$rackFilterItem = (new CDiv())->addClass('filter-item');
$rackFilterItem->addItem(
    (new CTag('label', true, '🗄️ ' . LanguageManager::t('rack')))->setAttribute('for', 'rack-select')
);
$rackOptions = ['' => LanguageManager::t('select_rack')];
foreach ($racks as $rack) {
    $rackOptions[$rack['id']] = $rack['name'];
}
$rackFilterSelect = $buildSelect('rack_id', 'rack-select', $rackOptions, $currentRackId);
$rackFilterItem->addItem($rackFilterSelect);
$filterForm->addItem($rackFilterItem);

// Filter button
$filterBtnItem = (new CDiv())->addClass('filter-item');
$filterBtnItem->addItem(
    (new CTag('button', true, '🔎 ' . LanguageManager::t('filter')))
        ->setAttribute('type', 'submit')
        ->addClass('btn btn-primary')
);
$filterForm->addItem($filterBtnItem);

$filterBar->addItem($filterForm);
$container->addItem($filterBar);

if (empty($rooms)) {
    $container->addItem(
        (new CDiv())
            ->addClass('no-data')
            ->setAttribute('style', 'text-align:center;padding:60px 20px;margin-top:20px;background:#f8f9fa;border-radius:8px;border:1px dashed #dee2e6;')
            ->addItem((new CDiv('🔒'))->setAttribute('style', 'font-size:48px;margin-bottom:16px;'))
            ->addItem(new CDiv(LanguageManager::t('no_accessible_rooms')))
    );
} else {

// ── Statistics cards ──
$totalHosts = count($hosts);
$rackHeight = $currentRack ? ($currentRack['height'] ?? 42) : 42;
$usedFront = 0;
$usedBack = 0;
if (!$showOverview && $currentRack) {
    foreach ($hostsFront as $host) {
        if (!empty($host['u_start']) && !empty($host['u_end'])) {
            $usedFront += ((int) $host['u_end'] - (int) $host['u_start'] + 1);
        }
    }
    foreach ($hostsBack as $host) {
        if (!empty($host['u_start']) && !empty($host['u_end'])) {
            $usedBack += ((int) $host['u_end'] - (int) $host['u_start'] + 1);
        }
    }
    foreach ($chassisFront as $chassis) {
        $cStart = (int) ($chassis['u_start'] ?? 0);
        $cEnd = (int) ($chassis['u_end'] ?? 0);
        if ($cStart > 0 && $cEnd >= $cStart) {
            $usedFront += ($cEnd - $cStart + 1);
        }
    }
    foreach ($chassisBack as $chassis) {
        $cStart = (int) ($chassis['u_start'] ?? 0);
        $cEnd = (int) ($chassis['u_end'] ?? 0);
        if ($cStart > 0 && $cEnd >= $cStart) {
            $usedBack += ($cEnd - $cStart + 1);
        }
    }
}
$usedTotal = $usedFront + $usedBack;
$totalCapacity = $rackHeight * 2;
$usagePercentTotal = $totalCapacity > 0 ? round(($usedTotal / $totalCapacity) * 100, 1) : 0;
$usagePercentFront = $rackHeight > 0 ? round(($usedFront / $rackHeight) * 100, 1) : 0;
$usagePercentBack = $rackHeight > 0 ? round(($usedBack / $rackHeight) * 100, 1) : 0;

$formatSpaceStat = static function (int $used, int $capacity, float $percent): string {
    return $used . 'U/' . $capacity . ' (' . $percent . '%)';
};

$statsRow = (new CDiv())->addClass('stats-row');

if ($showOverview) {
    // In overview mode show the statistics of the whole room
    $totalRackCount = count($racks);
    $totalHostsInRoom = 0;
    $totalProblemsInRoom = 0;
    foreach ($allRacksData as $rackData) {
        $totalHostsInRoom += $rackData['host_count'];
        $totalProblemsInRoom += $rackData['problem_count'];
    }
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🗄️'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalRackCount))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_racks')))->addClass('stat-label'))
            )
    );
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalHostsInRoom))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_hosts')))->addClass('stat-label'))
            )
    );
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🚨'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalProblemsInRoom))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_problems')))->addClass('stat-label'))
            )
    );
} else {
    // In single-rack mode show the rack statistics
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalHosts))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_hosts')))->addClass('stat-label'))
            )
    );
    $spaceStatCards = [
        ['icon' => '📊', 'label' => 'total_space', 'used' => $usedTotal, 'capacity' => $totalCapacity, 'percent' => $usagePercentTotal],
        ['icon' => '⬆️', 'label' => 'front_space', 'used' => $usedFront, 'capacity' => $rackHeight, 'percent' => $usagePercentFront],
        ['icon' => '⬇️', 'label' => 'back_space', 'used' => $usedBack, 'capacity' => $rackHeight, 'percent' => $usagePercentBack],
    ];
    foreach ($spaceStatCards as $spaceStat) {
        $statsRow->addItem(
            (new CDiv())
                ->addClass('stat-card')
                ->addItem((new CSpan($spaceStat['icon']))->addClass('stat-icon'))
                ->addItem(
                    (new CDiv())
                        ->addClass('stat-content')
                        ->addItem((new CSpan($formatSpaceStat(
                            (int) $spaceStat['used'],
                            (int) $spaceStat['capacity'],
                            (float) $spaceStat['percent']
                        )))->addClass('stat-number'))
                        ->addItem((new CSpan(LanguageManager::t($spaceStat['label'])))->addClass('stat-label'))
                )
        );
    }
}
$container->addItem($statsRow);

// ── Main container ──
$rackContainer = (new CDiv())->addClass('rack-container');

if ($showOverview) {
    // ===== Overview mode: show several racks =====
    $rackMain = (new CDiv())
        ->addClass('rack-main')
        ->setAttribute('style', 'width:100%;margin:0;');

    if (empty($allRacksData)) {
        $rackMain->addItem(
            (new CDiv('📭 ' . LanguageManager::t('no_racks')))->addClass('no-data')
        );
    } else {
        $racksGrid = (new CDiv())->addClass('racks-grid');

        foreach ($allRacksData as $rackData) {
            $severityClass = '';
            $hasAlert = $rackData['problem_count'] > 0;
            if ($hasAlert) {
                $severityClass = 'severity-' . $rackData['max_severity'];
            }

            $cardClasses = 'rack-card';
            if ($hasAlert) {
                $cardClasses .= ' has-alert ' . $severityClass;
            }

            $rackCard = (new CDiv())
                ->addClass($cardClasses)
                ->setAttribute('data-rack-id', htmlspecialchars($rackData['id']))
                ->setAttribute('onclick', "openRackDetail('" . htmlspecialchars($rackData['id']) . "')");

            // Card header
            $cardHeader = (new CDiv())->addClass('rack-card-header');
            $cardHeader->addItem(
                (new CDiv('🗄️ ' . htmlspecialchars($rackData['name'])))->addClass('rack-card-title')
            );
            if ($hasAlert) {
                $cardHeader->addItem(
                    (new CDiv((string)$rackData['problem_count']))
                        ->addClass('rack-alert-badge ' . $severityClass)
                        ->setAttribute('title', $rackData['problem_count'] . ' ' . LanguageManager::t('problems'))
                );
            }
            $rackCard->addItem($cardHeader);

            // Card content
            $cardBody = (new CDiv())->addClass('rack-card-body');

            $hostStat = (new CDiv())->addClass('rack-card-stat');
            $hostStat->addItem(
                (new CSpan('🖥️ ' . LanguageManager::t('hosts') . ':'))->addClass('rack-card-stat-label')
            );
            $hostStat->addItem(
                (new CSpan((string)$rackData['host_count']))->addClass('rack-card-stat-value')
            );
            $cardBody->addItem($hostStat);

            $usageStat = (new CDiv())->addClass('rack-card-stat');
            $usageStat->addItem(
                (new CSpan('📏 ' . LanguageManager::t('usage') . ':'))->addClass('rack-card-stat-label')
            );
            $cardUsagePercent = $rackData['height'] > 0 ? round(($rackData['used_u'] / $rackData['height']) * 100, 1) : 0;
            $usageStat->addItem(
                (new CSpan($rackData['used_u'] . 'U / ' . $rackData['height'] . 'U (' . $cardUsagePercent . '%)'))->addClass('rack-card-stat-value')
            );
            $cardBody->addItem($usageStat);

            // Mini rack visualization
            $miniVisual = (new CDiv())->addClass('rack-mini-visual');
            for ($i = 0; $i < $rackData['height']; $i++) {
                $unitClass = 'rack-mini-unit';
                $isOccupied = false;
                $unitSeverity = -1;
                foreach ($rackData['hosts'] as $rhost) {
                    $uPos = $rackData['height'] - $i;
                    if ($uPos >= $rhost['u_start'] && $uPos <= $rhost['u_end']) {
                        $isOccupied = true;
                        if ((int) ($rhost['problem_count'] ?? 0) > 0
                                && isset($rhost['max_severity']) && (int) $rhost['max_severity'] >= 0) {
                            $unitSeverity = $rhost['max_severity'];
                        }
                        break;
                    }
                }
                if ($isOccupied) {
                    $unitClass .= ' occupied';
                    if ($unitSeverity >= 0) {
                        $unitClass .= ' severity-' . $unitSeverity;
                    }
                }
                $miniVisual->addItem(
                    (new CDiv())->addClass($unitClass)
                );
            }
            $cardBody->addItem($miniVisual);
            $rackCard->addItem($cardBody);

            // Card footer
            $cardFooter = (new CDiv())->addClass('rack-card-footer');
            $cardFooter->addItem(
                (new CTag('button', true, LanguageManager::t('view_details') . ' →'))
                    ->addClass('rack-card-btn')
                    ->setAttribute('onclick', "event.stopPropagation();viewRackDetail('" . htmlspecialchars($rackData['id']) . "')")
            );
            $rackCard->addItem($cardFooter);

            $racksGrid->addItem($rackCard);
        }

        $rackMain->addItem($racksGrid);
    }

    $rackContainer->addItem($rackMain);

    // Search results in overview mode
    if (!empty($searchResults)) {
        $searchSection = (new CDiv())->addClass('sidebar-card');
        $searchSection->setAttribute('style', 'margin-top: 20px;');
        $searchSection->addItem(
            (new CDiv('🔍 ' . LanguageManager::t('search_results') . ' (' . count($searchResults) . ')'))
                ->addClass('sidebar-card-header')
        );
        $searchBody = (new CDiv())->addClass('sidebar-card-body');
        $searchList = (new CDiv())->addClass('host-list');
        foreach ($searchResults as $result) {
            $searchItem = (new CDiv())->addClass('host-list-item');
            $searchItem->addItem(
                (new CDiv('🖥️ ' . htmlspecialchars($result['name'])))->addClass('host-name')
            );
            $searchItem->addItem(
                (new CDiv('📍 ' . htmlspecialchars($result['main_ip'])))->addClass('host-ip')
            );
            $searchItem->addItem(
                (new CDiv('📦 ' . htmlspecialchars($result['room_name']) . ' / ' . htmlspecialchars($result['rack_name']) . ' (U' . $result['u_start'] . '-U' . $result['u_end'] . ')'))
                    ->addClass('host-position')
            );
            $searchList->addItem($searchItem);
        }
        $searchBody->addItem($searchList);
        $searchSection->addItem($searchBody);
        $rackContainer->addItem($searchSection);
    }
} else {
    // ===== Single-rack details mode =====

    // Left sidebar
    $sidebar = (new CDiv())->addClass('rack-sidebar');

    // Search results
    if (!empty($searchResults)) {
        $searchCard = (new CDiv())->addClass('sidebar-card');
        $searchCard->addItem(
            (new CDiv('🔍 ' . LanguageManager::t('search_results') . ' (' . count($searchResults) . ')'))
                ->addClass('sidebar-card-header')
        );
        $searchCardBody = (new CDiv())->addClass('sidebar-card-body');
        $searchHostList = (new CDiv())->addClass('host-list');
        foreach ($searchResults as $result) {
            $searchItem = (new CDiv())->addClass('host-list-item');
            $searchItem->addItem(
                (new CDiv('🖥️ ' . htmlspecialchars($result['name'])))->addClass('host-name')
            );
            $searchItem->addItem(
                (new CDiv('📍 ' . htmlspecialchars($result['main_ip'])))->addClass('host-ip')
            );
            $searchItem->addItem(
                (new CDiv('📦 ' . htmlspecialchars($result['room_name']) . ' / ' . htmlspecialchars($result['rack_name']) . ' (U' . $result['u_start'] . '-U' . $result['u_end'] . ')'))
                    ->addClass('host-position')
            );
            $searchHostList->addItem($searchItem);
        }
        $searchCardBody->addItem($searchHostList);
        $searchCard->addItem($searchCardBody);
        $sidebar->addItem($searchCard);
    }

    // Host list of the current rack
    if (!empty($hosts)) {
        $hostCard = (new CDiv())->addClass('sidebar-card');
        $hostCard->addItem(
            (new CDiv('📋 ' . LanguageManager::t('hosts_in_rack') . ' (' . count($hosts) . ')'))
                ->addClass('sidebar-card-header')
        );
        $hostCardBody = (new CDiv())->addClass('sidebar-card-body');
        $hostList = (new CDiv())->addClass('host-list');
        foreach ($hosts as $host) {
            $hostItem = (new CDiv())->addClass('host-list-item');

            $hostNameDiv = (new CDiv())->addClass('host-name');
            $hostNameDiv->addItem('🖥️ ' . htmlspecialchars($host['name']));
            $hostNameDiv->addItem(
                (new CTag('button', true, '🗑️ ' . LanguageManager::t('remove')))
                    ->addClass('btn-remove')
                    ->setAttribute('onclick', "removeHost('" . $host['hostid'] . "')")
            );
            $hostItem->addItem($hostNameDiv);

            $hostItem->addItem(
                (new CDiv('📍 ' . htmlspecialchars($host['main_ip'])))->addClass('host-ip')
            );
            $sideLabel = (($host['side'] ?? 'front') === 'back')
                ? LanguageManager::t('rack_side_back')
                : LanguageManager::t('rack_side_front');
            $hostItem->addItem(
                (new CDiv('📦 U' . $host['u_start'] . '-U' . $host['u_end'] . ' (' . $host['u_height'] . 'U) · ' . $sideLabel))
                    ->addClass('host-position')
            );
            $hostList->addItem($hostItem);
        }
        $hostCardBody->addItem($hostList);
        $hostCard->addItem($hostCardBody);
        $sidebar->addItem($hostCard);
    }

    $rackContainer->addItem($sidebar);

    // Main area - rack visualization (front/rear side by side)
    $rackMainDiv = (new CDiv())->addClass('rack-main');

    if ($currentRack) {
        $dualView = (new CDiv())->addClass('rack-dual-view');

        $frontPanel = (new CDiv())->addClass('rack-dual-side-panel');
        $frontPanel->addItem(
            (new CDiv(LanguageManager::t('rack_side_front')))->addClass('rack-detail-side-label')
        );
        $frontPanel->addItem($buildRackVisualSide($currentRack, $hostsFront, 'front'));
        $dualView->addItem($frontPanel);

        $backPanel = (new CDiv())->addClass('rack-dual-side-panel');
        $backPanel->addItem(
            (new CDiv(LanguageManager::t('rack_side_back')))->addClass('rack-detail-side-label')
        );
        $backPanel->addItem($buildRackVisualSide($currentRack, $hostsBack, 'back'));
        $dualView->addItem($backPanel);

        $rackMainDiv->addItem($dualView);
    } else {
        $rackMainDiv->addItem(
            (new CDiv('📭 ' . LanguageManager::t('no_rack_selected')))->addClass('no-data')
        );
    }

    $rackContainer->addItem($rackMainDiv);
}

$container->addItem($rackContainer);
}

$content->addItem($container);

// ==================== Host information tooltip ====================
$content->addItem(
    (new CDiv())
        ->setAttribute('id', 'host-tooltip')
        ->addClass('host-tooltip')
);

// ==================== Large rack dialog (shown when clicking a card in overview mode) ====================
$rackDetailModal = (new CDiv())
    ->setAttribute('id', 'rack-detail-modal')
    ->addClass('rack-detail-modal');

$rackDetailContent = (new CDiv())->addClass('rack-detail-content');

$rackDetailHeader = (new CDiv())->addClass('rack-detail-header');
$rackDetailTitle = (new CDiv())->addClass('rack-detail-title');
$rackDetailTitle->addItem('🗄️ ');
$rackDetailTitle->addItem(
    (new CSpan())->setAttribute('id', 'detail-rack-name')
);
$rackDetailHeader->addItem($rackDetailTitle);
$rackDetailHeader->addItem(
    (new CTag('button', true, '×'))
        ->addClass('rack-detail-close')
        ->setAttribute('onclick', 'closeRackDetail()')
);
$rackDetailContent->addItem($rackDetailHeader);

$rackDetailBody = (new CDiv())->addClass('rack-detail-body');
$rackDetailBody->addItem(
    (new CDiv())
        ->setAttribute('id', 'detail-rack-visual')
        ->addClass('rack-detail-visual')
);
$rackDetailContent->addItem($rackDetailBody);

$rackDetailModal->addItem($rackDetailContent);
$content->addItem($rackDetailModal);

// ==================== Assign/edit host dialog ====================
$assignModal = (new CDiv())
    ->setAttribute('id', 'assign-modal')
    ->addClass('modal-overlay');

$assignModalContent = (new CDiv())->addClass('modal-content');

// Dialog header
$assignModalContent->addItem(
    (new CDiv())
        ->addClass('modal-header')
        ->addItem(
            (new CTag('h3', true))
                ->setAttribute('id', 'modal-title')
                ->addItem('📌 ' . LanguageManager::t('assign_host'))
        )
        ->addItem(
            (new CTag('button', true, '×'))
                ->addClass('modal-close')
                ->setAttribute('onclick', 'closeAssignModal()')
        )
);

// Dialog body
$assignModalBody = (new CDiv())->addClass('modal-body');

// In edit mode show the current host information
$editHostInfo = (new CDiv())
    ->setAttribute('id', 'edit-host-info')
    ->addClass('form-group')
    ->setAttribute('style', 'display:none;background:#ecf5ff;padding:12px 16px;border-radius:8px;margin-bottom:18px;border-left:3px solid #409eff;');
$editHostInfo->addItem(
    (new CDiv())
        ->addItem(
            (new CTag('strong', true, ''))
                ->setAttribute('id', 'edit-host-name')
                ->setAttribute('style', 'font-size:15px;')
        )
);
$editHostInfo->addItem(
    (new CDiv())
        ->setAttribute('style', 'font-size:12px;color:#6c757d;margin-top:4px;')
        ->setAttribute('id', 'edit-host-ip')
);
$assignModalBody->addItem($editHostInfo);

// When adding a host, switch between Zabbix hosts and custom hosts.
$hostTypeTabs = (new CDiv())
    ->setAttribute('id', 'host-type-tabs')
    ->addClass('modal-tabs')
    ->setAttribute('role', 'tablist');
$hostTypeTabs->addItem(
    (new CTag('button', true, LanguageManager::t('assigned_host')))
        ->setAttribute('type', 'button')
        ->setAttribute('id', 'assigned-host-tab')
        ->setAttribute('role', 'tab')
        ->setAttribute('aria-selected', 'true')
        ->addClass('modal-tab is-active')
        ->setAttribute('onclick', "switchAssignTab('zabbix')")
);
$hostTypeTabs->addItem(
    (new CTag('button', true, LanguageManager::t('custom_host')))
        ->setAttribute('type', 'button')
        ->setAttribute('id', 'custom-host-tab')
        ->setAttribute('role', 'tab')
        ->setAttribute('aria-selected', 'false')
        ->addClass('modal-tab')
        ->setAttribute('onclick', "switchAssignTab('custom')")
);
$assignModalBody->addItem($hostTypeTabs);

// Host selection area
$hostSelectSection = (new CDiv())
    ->setAttribute('id', 'host-select-section')
    ->addClass('modal-tab-panel is-active');

// Host group selection - native select
$groupSelectGroup = (new CDiv())->addClass('form-group');
$groupSelectGroup->addItem(
    (new CTag('label', true, '📂 ' . LanguageManager::t('host_group')))->setAttribute('for', 'modal-group-select')
);
$groupOptions = ['' => LanguageManager::t('all_groups')];
foreach ($hostGroups as $group) {
    $groupOptions[$group['groupid']] = $group['name'];
}
$groupSelect = $buildSelect('group', 'modal-group-select', $groupOptions);
$groupSelectGroup->addItem($groupSelect);
$hostSelectSection->addItem($groupSelectGroup);

// Search hosts
$hostSearchGroup = (new CDiv())->addClass('form-group');
$hostSearchGroup->addItem(
    (new CTag('label', true, '🔍 ' . LanguageManager::t('search_host')))->setAttribute('for', 'modal-host-search')
);
$hostSearchGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'text')
        ->setAttribute('id', 'modal-host-search')
        ->setAttribute('placeholder', LanguageManager::t('search_host_placeholder'))
        ->setAttribute('onkeyup', 'debounceLoadHosts()')
);
$hostSelectSection->addItem($hostSearchGroup);

// Host list
$hostListGroup = (new CDiv())->addClass('form-group');
$hostListGroup->addItem(
    (new CTag('label', true, LanguageManager::t('select_host')))
);
$hostListGroup->addItem(
    (new CDiv())
        ->setAttribute('id', 'host-select-list')
        ->addClass('host-select-list')
);
$hostSelectSection->addItem($hostListGroup);
$assignModalBody->addItem($hostSelectSection);

$customHostSection = (new CDiv())
    ->setAttribute('id', 'custom-host-section')
    ->addClass('modal-tab-panel');
$customNameGroup = (new CDiv())->addClass('form-group');
$customNameGroup->addItem(
    (new CTag('label', true, LanguageManager::t('custom_host_name')))
        ->setAttribute('for', 'modal-custom-host-name')
);
$customNameGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'text')
        ->setAttribute('id', 'modal-custom-host-name')
        ->setAttribute('placeholder', LanguageManager::t('custom_host_name_placeholder'))
);
$customHostSection->addItem($customNameGroup);

$customIpGroup = (new CDiv())->addClass('form-group');
$customIpGroup->addItem(
    (new CTag('label', true, LanguageManager::t('custom_host_ip')))
        ->setAttribute('for', 'modal-custom-host-ip')
);
$customIpGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'text')
        ->setAttribute('id', 'modal-custom-host-ip')
        ->setAttribute('inputmode', 'decimal')
        ->setAttribute('placeholder', LanguageManager::t('custom_host_ip_placeholder'))
);
$customHostSection->addItem($customIpGroup);
$assignModalBody->addItem($customHostSection);

// U range
$maxU = $currentRack['height'] ?? 42;
$formRow = (new CDiv())->addClass('form-row');

$uStartGroup = (new CDiv())->addClass('form-group');
$uStartGroup->addItem(
    (new CTag('label', true, '⬆️ ' . LanguageManager::t('u_start')))->setAttribute('for', 'modal-u-start')
);
$uStartGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'number')
        ->setAttribute('id', 'modal-u-start')
        ->setAttribute('min', '1')
        ->setAttribute('max', (string)$maxU)
);
$formRow->addItem($uStartGroup);

$uEndGroup = (new CDiv())->addClass('form-group');
$uEndGroup->addItem(
    (new CTag('label', true, '⬇️ ' . LanguageManager::t('u_end')))->setAttribute('for', 'modal-u-end')
);
$uEndGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'number')
        ->setAttribute('id', 'modal-u-end')
        ->setAttribute('min', '1')
        ->setAttribute('max', (string)$maxU)
);
$formRow->addItem($uEndGroup);
$assignModalBody->addItem($formRow);

$sideGroup = (new CDiv())->addClass('form-group');
$sideGroup->addItem(
    (new CTag('label', true, '↔️ ' . LanguageManager::t('rack_side')))->setAttribute('for', 'modal-rack-side')->addClass('form-label')
);
$sideSelect = $buildSelect('rack_side', 'modal-rack-side', [
    'front' => LanguageManager::t('rack_side_front'),
    'back' => LanguageManager::t('rack_side_back')
], 'front');
$sideGroup->addItem($sideSelect);
$assignModalBody->addItem($sideGroup);

$assignModalContent->addItem($assignModalBody);

// Dialog footer
$assignModalFooter = (new CDiv())->addClass('modal-footer');
$assignModalFooter->addItem(
    (new CTag('button', true, '🗑️ ' . LanguageManager::t('remove')))
        ->setAttribute('id', 'btn-remove-host')
        ->addClass('btn btn-danger')
        ->setAttribute('style', 'display:none;')
        ->setAttribute('onclick', 'removeHostFromModal()')
);
$assignModalFooter->addItem(
    (new CDiv())->setAttribute('style', 'flex:1;')
);
$assignModalFooter->addItem(
    (new CTag('button', true, '❌ ' . LanguageManager::t('cancel')))
        ->addClass('btn btn-secondary')
        ->setAttribute('onclick', 'closeAssignModal()')
);
$assignModalFooter->addItem(
    (new CTag('button', true, '✅ ' . LanguageManager::t('confirm')))
        ->addClass('btn btn-success')
        ->setAttribute('onclick', 'saveHost()')
);
$assignModalContent->addItem($assignModalFooter);

$assignModal->addItem($assignModalContent);
$content->addItem($assignModal);

// ==================== Alarm dialog ====================
$problemModal = (new CDiv())
    ->setAttribute('id', 'problem-modal')
    ->addClass('problem-modal');

$problemModalContent = (new CDiv())->addClass('problem-modal-content');

// Alarm dialog header
$problemHeader = (new CDiv())->addClass('problem-modal-header');
$problemH3 = (new CTag('h3', true));
$problemH3->addItem('🚨 ');
$problemH3->addItem((new CSpan())->setAttribute('id', 'problem-host-name'));
$problemH3->addItem(' - ' . LanguageManager::t('problems'));
$problemHeader->addItem($problemH3);
$problemHeader->addItem(
    (new CTag('button', true, '×'))
        ->addClass('problem-modal-close')
        ->setAttribute('onclick', 'closeProblemModal()')
);
$problemModalContent->addItem($problemHeader);

// Alarm dialog body
$problemBody = (new CDiv())->addClass('problem-modal-body');
$problemBody->addItem(
    (new CTag('ul', true))
        ->addClass('problem-list')
        ->setAttribute('id', 'problem-list')
);
$problemModalContent->addItem($problemBody);

$problemModal->addItem($problemModalContent);
$content->addItem($problemModal);

// ==================== JavaScript ====================
$roomId = htmlspecialchars($currentRoomId);
$rackId = htmlspecialchars($currentRackId);
$allRacksDataJson = json_encode($allRacksData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$i18n = [
    'host_name_label' => LanguageManager::t('host_name_label'),
    'ip_label' => LanguageManager::t('ip_label'),
    'host_group_label' => LanguageManager::t('host_group_label'),
    'position_label' => LanguageManager::t('position_label'),
    'status_label' => LanguageManager::t('status_label'),
    'unnamed_host' => LanguageManager::t('unnamed_host'),
    'enabled' => LanguageManager::t('enabled'),
    'disabled' => LanguageManager::t('disabled'),
    'edit_host_position' => LanguageManager::t('edit_host_position'),
    'assign_host' => LanguageManager::t('assign_host'),
    'no_matching_hosts' => LanguageManager::t('no_matching_hosts'),
    'select_a_host' => LanguageManager::t('select_a_host'),
    'custom_host_required' => LanguageManager::t('custom_host_required'),
    'invalid_custom_ip' => LanguageManager::t('invalid_custom_ip'),
    'invalid_u_range' => LanguageManager::t('invalid_u_range'),
    'operation_failed' => LanguageManager::t('operation_failed'),
    'confirm_remove_host' => LanguageManager::t('confirm_remove_host'),
    'loading' => LanguageManager::t('loading'),
    'no_active_problems' => LanguageManager::t('no_active_problems'),
    'fetch_problems_failed' => LanguageManager::t('fetch_problems_failed'),
    'rack_side_front' => LanguageManager::t('rack_side_front'),
    'rack_side_back' => LanguageManager::t('rack_side_back'),
    'rack_side' => LanguageManager::t('rack_side'),
    'rack_side_front_view' => LanguageManager::t('rack_side_front_view'),
    'rack_side_back_view' => LanguageManager::t('rack_side_back_view'),
];
$i18nJson = json_encode($i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$content->addItem(new CJsScript('<script>
(function() {
    "use strict";

    var allRacksData = ' . $allRacksDataJson . ';
    var i18n = ' . $i18nJson . ';

    var selectedHostId = null;
    var debounceTimer = null;
    var currentRoomId = "' . $roomId . '";
    var currentRackId = "' . $rackId . '";
    var isEditMode = false;
    var editingHostId = null;
    var activeHostTab = "zabbix";
    var editingHostType = "zabbix";
    var detailRackData = null;

    // HTML escaping
    function escapeHtml(text) {
        if (!text) return "";
        var div = document.createElement("div");
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function formatHostSlotLabel(name, ip, groups) {
        var ipText = ip || "-";
        var groupsText = groups || "-";
        if (Array.isArray(groups)) {
            groupsText = groups.length ? groups.join(", ") : "-";
        }
        return escapeHtml(name || i18n.unnamed_host)
            + " <span class=\\"host-slot-meta\\">("
            + escapeHtml(String(ipText)) + ", "
            + escapeHtml(String(groupsText)) + ")</span>";
    }

    function setModalRackSide(side) {
        var select = document.getElementById("modal-rack-side");
        if (select) {
            select.value = (side === "back") ? "back" : "front";
        }
    }

    function getModalRackSide() {
        var select = document.getElementById("modal-rack-side");
        if (!select) {
            return "front";
        }
        return select.value === "back" ? "back" : "front";
    }

    window.switchAssignTab = function(type) {
        if (isEditMode) return;
        activeHostTab = type === "custom" ? "custom" : "zabbix";

        var assignedTab = document.getElementById("assigned-host-tab");
        var customTab = document.getElementById("custom-host-tab");
        var assignedPanel = document.getElementById("host-select-section");
        var customPanel = document.getElementById("custom-host-section");
        var customActive = activeHostTab === "custom";

        assignedTab.classList.toggle("is-active", !customActive);
        customTab.classList.toggle("is-active", customActive);
        assignedTab.setAttribute("aria-selected", customActive ? "false" : "true");
        customTab.setAttribute("aria-selected", customActive ? "true" : "false");
        assignedPanel.classList.toggle("is-active", !customActive);
        customPanel.classList.toggle("is-active", customActive);
    };

    // ============ Filter bar linkage (select event listeners) ============
    var roomSelect = document.getElementById("room-select");
    if (roomSelect) {
        roomSelect.addEventListener("change", function() {
            var roomId = this.value;
            if (roomId) {
                window.location.href = "zabbix.php?action=rack.view&room_id="
                    + encodeURIComponent(roomId);
            }
        });
    }

    var rackSelect = document.getElementById("rack-select");
    if (rackSelect) {
        rackSelect.addEventListener("change", function() {
            var rackId = this.value;
            if (rackId) {
                window.location.href = "zabbix.php?action=rack.view&room_id="
                    + encodeURIComponent(currentRoomId)
                    + "&rack_id=" + encodeURIComponent(rackId);
            }
        });
    }

    // Host group dropdown (select) change event → replaces the former onchange="loadHosts()"
    var groupSelect = document.getElementById("modal-group-select");
    if (groupSelect) {
        groupSelect.addEventListener("change", function() {
            loadHosts();
        });
    }

    // ============ Host hover tooltip ============
    var tooltip = document.getElementById("host-tooltip");
    var occupiedSlots = document.querySelectorAll(".rack-unit-slot.occupied");

    occupiedSlots.forEach(function(slot) {
        slot.addEventListener("mouseenter", function(e) {
            var hostData = JSON.parse(this.getAttribute("data-host"));
            var sideLabel = (hostData.side === "back") ? i18n.rack_side_back : i18n.rack_side_front;
            tooltip.innerHTML = "<h4>" + escapeHtml(hostData.name) + "</h4>" +
                "<p><span class=\\"label\\">" + i18n.host_name_label + "</span> " + escapeHtml(hostData.host) + "</p>" +
                "<p><span class=\\"label\\">" + i18n.ip_label + "</span> " + escapeHtml(hostData.ip) + "</p>" +
                "<p><span class=\\"label\\">" + i18n.host_group_label + "</span> " + escapeHtml(hostData.groups) + "</p>" +
                "<p><span class=\\"label\\">" + i18n.position_label + "</span> U" + hostData.u_start + "-U" + hostData.u_end + "</p>" +
                "<p><span class=\\"label\\">" + i18n.rack_side + "</span> " + escapeHtml(sideLabel) + "</p>" +
                "<p><span class=\\"label\\">" + i18n.status_label + "</span> " + escapeHtml(hostData.status) + "</p>";
            tooltip.classList.add("visible");
        });

        slot.addEventListener("mousemove", function(e) {
            tooltip.style.left = (e.clientX + 15) + "px";
            tooltip.style.top = (e.clientY + 15) + "px";
        });

        slot.addEventListener("mouseleave", function() {
            tooltip.classList.remove("visible");
        });
    });

    // ============ Overview mode - multi-rack display ============
    window.openRackDetail = function(rackId) {
        viewRackDetail(rackId);
    };

    function buildRackVisualHtml(rackData, hosts, side) {
        if (!Array.isArray(hosts)) {
            hosts = [];
        }

        var occupiedMap = {};
        for (var i = 0; i < hosts.length; i++) {
            var host = hosts[i];
            var uStart = parseInt(host.u_start);
            var uEnd = parseInt(host.u_end);
            if (uStart > 0 && uEnd > 0) {
                for (var u = uStart; u <= uEnd; u++) {
                    occupiedMap[u] = host;
                }
            }
        }

        var rackHeight = parseInt(rackData.height) || 42;
        var sideClass = side === "back" ? " rack-side-back" : " rack-side-front";
        var sideLabel = side === "back" ? i18n.rack_side_back : i18n.rack_side_front;
        var html = "<div class=\\"rack-visual" + sideClass + "\\" data-side=\\"" + side + "\\">";
        html += "<div class=\\"rack-header\\">🗄️ " + escapeHtml(rackData.name) + " (" + rackHeight + "U) <span class=\\"rack-side-badge\\">" + sideLabel + "</span></div>";
        html += "<div class=\\"rack-units\\">";

        for (var u = rackHeight; u >= 1; u--) {
            if (occupiedMap[u]) {
                var host = occupiedMap[u];
                var uStart = parseInt(host.u_start);
                var uEnd = parseInt(host.u_end);
                if (u !== uEnd) {
                    continue;
                }

                var span = uEnd - uStart + 1;
                html += "<div class=\\"rack-unit\\" data-u-span=\\"" + span + "\\" style=\\"--u-span:" + span + "\\">";

                if (span > 1) {
                    html += "<div class=\\"rack-unit-number rack-unit-number-span\\">";
                    html += "<span>U" + uEnd + "</span>";
                    html += "<span class=\\"u-label-small\\">U" + uStart + "</span>";
                    html += "</div>";
                } else {
                    html += "<div class=\\"rack-unit-number\\">U" + u + "</div>";
                }

                var hostName = host.name || host.host || i18n.unnamed_host;
                var hostId = host.hostid || "";
                var problemCount = parseInt(host.problem_count) || 0;
                var maxSeverity = parseInt(host.max_severity);
                if (isNaN(maxSeverity)) maxSeverity = -1;

                var classes = ["rack-unit-slot", "occupied"];

                var hostStatus = parseInt(host.status);
                if (hostStatus === 1) {
                    classes.push("status-disabled");
                } else if (problemCount > 0 && maxSeverity >= 0) {
                    switch (maxSeverity) {
                        case 5: classes.push("severity-disaster"); break;
                        case 4: classes.push("severity-high"); break;
                        case 3: classes.push("severity-average"); break;
                        case 2: classes.push("severity-warning"); break;
                        case 1: classes.push("severity-info"); break;
                        case 0: classes.push("severity-not-classified"); break;
                        default: classes.push("has-problem");
                    }
                } else {
                    classes.push("no-problem");
                }

                var hostDataJson = JSON.stringify({
                    hostid: hostId,
                    name: hostName,
                    host: host.host || hostName,
                    ip: host.ip || host.main_ip || "",
                    groups: host.groups || "",
                    u_start: uStart,
                    u_end: uEnd,
                    side: host.side || side,
                    status: hostStatus === 0 ? i18n.enabled : i18n.disabled,
                    problem_count: problemCount,
                    custom: !!host.custom
                }).replace(/\x27/g, "\\\x27").replace(/"/g, "&quot;");

                html += "<div class=\\"" + classes.join(" ") + "\\" data-host=\\"" + hostDataJson + "\\" data-u-span=\\"" + span + "\\" onclick=\\"openEditModalFromDetail(this)\\" style=\\"cursor:pointer;\\">";
                html += "<div class=\\"host-slot-content\\">";
                html += "<span class=\\"host-name\\">" + formatHostSlotLabel(hostName, host.ip || host.main_ip || "", host.groups || "") + "</span>";
                if (problemCount > 0) {
                    html += "<span class=\\"problem-badge\\" onclick=\\"event.stopPropagation();showProblemsFromDetail(\x27" + hostId + "\x27,\x27" + escapeHtml(hostName).replace(/\x27/g, "\\\x27") + "\x27)\\">" + problemCount + "</span>";
                }
                html += "</div></div></div>";
                continue;
            }

            html += "<div class=\\"rack-unit\\">";
            html += "<div class=\\"rack-unit-number\\">U" + u + "</div>";
            html += "<div class=\\"rack-unit-slot\\" data-u=\\"" + u + "\\"></div>";
            html += "</div>";
        }

        html += "</div></div>";
        return html;
    }

    window.viewRackDetail = function(rackId) {
        var rackData = null;
        for (var i = 0; i < allRacksData.length; i++) {
            if (allRacksData[i].id === rackId) {
                rackData = allRacksData[i];
                break;
            }
        }
        if (!rackData) {
            console.error("viewRackDetail: rackData not found for id:", rackId);
            return;
        }

        var hostsFront = rackData.hosts_front || rackData.hosts || [];
        var hostsBack = rackData.hosts_back || [];
        if (!Array.isArray(hostsFront)) {
            hostsFront = [];
        }
        if (!Array.isArray(hostsBack)) {
            hostsBack = [];
        }

        detailRackData = rackData;
        currentRackId = rackData.id;
        currentRoomId = rackData.room_id;

        document.getElementById("detail-rack-name").textContent = rackData.name;

        var html = "<div class=\\"rack-detail-dual-view\\">";
        html += "<div class=\\"rack-detail-side-panel\\">";
        html += "<div class=\\"rack-detail-side-label\\">" + escapeHtml(i18n.rack_side_front) + "</div>";
        html += buildRackVisualHtml(rackData, hostsFront, "front");
        html += "</div>";
        html += "<div class=\\"rack-detail-side-panel\\">";
        html += "<div class=\\"rack-detail-side-label\\">" + escapeHtml(i18n.rack_side_back) + "</div>";
        html += buildRackVisualHtml(rackData, hostsBack, "back");
        html += "</div>";
        html += "</div>";

        document.getElementById("detail-rack-visual").innerHTML = html;
        document.getElementById("rack-detail-modal").classList.add("visible");
    };

    window.openAssignModalFromDetail = function(uStart, uEndOrSide, maybeSide) {
        closeRackDetail();
        if (typeof uEndOrSide === "string") {
            openAssignModal(uStart, uEndOrSide);
        } else {
            openAssignModal(uStart, uEndOrSide, maybeSide);
        }
    };

    window.openEditModalFromDetail = function(elem) {
        closeRackDetail();
        var hostData = JSON.parse(elem.getAttribute("data-host"));

        isEditMode = true;
        editingHostId = hostData.hostid;
        selectedHostId = hostData.hostid;
        editingHostType = hostData.custom || String(hostData.hostid).indexOf("custom:") === 0 ? "custom" : "zabbix";

        document.getElementById("modal-title").textContent = i18n.edit_host_position;
        document.getElementById("edit-host-name").textContent = hostData.name;
        document.getElementById("edit-host-ip").textContent = (hostData.ip || "-") + " | " + (hostData.groups || "-");
        document.getElementById("modal-u-start").value = hostData.u_start;
        document.getElementById("modal-u-end").value = hostData.u_end;
        setModalRackSide(hostData.side || "front");
        document.getElementById("host-type-tabs").style.display = "none";
        document.getElementById("host-select-section").classList.remove("is-active");
        document.getElementById("custom-host-section").classList.remove("is-active");
        document.getElementById("modal-custom-host-name").value = editingHostType === "custom" ? (hostData.name || "") : "";
        document.getElementById("modal-custom-host-ip").value = editingHostType === "custom" ? (hostData.ip || "") : "";
        document.getElementById("edit-host-info").style.display = "block";
        document.getElementById("btn-remove-host").style.display = "inline-block";
        document.getElementById("assign-modal").classList.add("visible");
    };

    window.showProblemsFromDetail = function(hostId, hostName) {
        if (typeof showProblems === "function") {
            showProblems(hostId, hostName);
        }
    };

    window.closeRackDetail = function() {
        document.getElementById("rack-detail-modal").classList.remove("visible");
    };

    document.getElementById("rack-detail-modal").addEventListener("click", function(e) {
        if (e.target === this) {
            closeRackDetail();
        }
    });

    function getSideFromRackVisual(slot) {
        var visual = slot.closest(".rack-visual");
        if (!visual) {
            return "front";
        }
        return visual.getAttribute("data-side") === "back" ? "back" : "front";
    }

    function getEmptySlotFromElement(el) {
        if (!el) {
            return null;
        }
        var slot = el.closest(".rack-unit-slot:not(.occupied)");
        if (!slot || !slot.hasAttribute("data-u")) {
            return null;
        }
        return slot;
    }

    function getEmptySlotAtPoint(x, y) {
        var el = document.elementFromPoint(x, y);
        return getEmptySlotFromElement(el);
    }

    function clearURangeSelectionHighlight() {
        document.querySelectorAll(".rack-unit-slot.u-range-selecting").forEach(function(slot) {
            slot.classList.remove("u-range-selecting");
        });
    }

    function isURangeAvailable(unitsContainer, minU, maxU) {
        if (!unitsContainer) {
            return false;
        }
        for (var u = minU; u <= maxU; u++) {
            var slot = unitsContainer.querySelector(".rack-unit-slot[data-u=\"" + u + "\"]");
            if (!slot || slot.classList.contains("occupied")) {
                return false;
            }
        }
        return true;
    }

    function updateURangeSelectionHighlight(unitsContainer, startU, endU) {
        clearURangeSelectionHighlight();
        if (!unitsContainer) {
            return false;
        }

        var minU = Math.min(startU, endU);
        var maxU = Math.max(startU, endU);
        if (!isURangeAvailable(unitsContainer, minU, maxU)) {
            return false;
        }

        for (var u = minU; u <= maxU; u++) {
            var slot = unitsContainer.querySelector(".rack-unit-slot[data-u=\"" + u + "\"]");
            if (slot) {
                slot.classList.add("u-range-selecting");
            }
        }
        return true;
    }

    function finishURangeSelection(unitsContainer, startU, endU, side) {
        if (!unitsContainer) {
            return;
        }

        var minU = Math.min(startU, endU);
        var maxU = Math.max(startU, endU);
        if (!isURangeAvailable(unitsContainer, minU, maxU)) {
            if (maxU > minU) {
                RackUI.message(i18n.invalid_u_range);
            }
            return;
        }

        var detailModal = document.getElementById("rack-detail-modal");
        if (detailModal && detailModal.classList.contains("visible") && unitsContainer.closest("#rack-detail-modal")) {
            openAssignModalFromDetail(minU, maxU, side);
        } else {
            openAssignModal(minU, maxU, side);
        }
    }

    var uRangeSelection = {
        active: false,
        rackUnits: null,
        side: "front",
        startU: 0,
        endU: 0
    };

    function beginURangeSelection(slot) {
        var rackUnits = slot.closest(".rack-units");
        if (!rackUnits) {
            return;
        }

        uRangeSelection.active = true;
        uRangeSelection.rackUnits = rackUnits;
        uRangeSelection.side = getSideFromRackVisual(slot);
        uRangeSelection.startU = parseInt(slot.getAttribute("data-u"), 10);
        uRangeSelection.endU = uRangeSelection.startU;
        rackUnits.classList.add("u-selecting-active");
        updateURangeSelectionHighlight(rackUnits, uRangeSelection.startU, uRangeSelection.endU);
    }

    function moveURangeSelection(clientX, clientY) {
        if (!uRangeSelection.active || !uRangeSelection.rackUnits) {
            return;
        }

        var slot = getEmptySlotAtPoint(clientX, clientY);
        if (!slot) {
            clearURangeSelectionHighlight();
            return;
        }

        var rackUnits = slot.closest(".rack-units");
        if (rackUnits !== uRangeSelection.rackUnits) {
            return;
        }

        uRangeSelection.endU = parseInt(slot.getAttribute("data-u"), 10);
        updateURangeSelectionHighlight(uRangeSelection.rackUnits, uRangeSelection.startU, uRangeSelection.endU);
    }

    function endURangeSelection() {
        if (!uRangeSelection.active) {
            return;
        }

        var rackUnits = uRangeSelection.rackUnits;
        var startU = uRangeSelection.startU;
        var endU = uRangeSelection.endU;
        var side = uRangeSelection.side;

        uRangeSelection.active = false;
        uRangeSelection.rackUnits = null;

        if (rackUnits) {
            rackUnits.classList.remove("u-selecting-active");
        }
        clearURangeSelectionHighlight();
        finishURangeSelection(rackUnits, startU, endU, side);
    }

    document.addEventListener("mousedown", function(e) {
        if (e.button !== 0) {
            return;
        }
        var slot = getEmptySlotFromElement(e.target);
        if (!slot) {
            return;
        }
        e.preventDefault();
        beginURangeSelection(slot);
    });

    document.addEventListener("mousemove", function(e) {
        if (!uRangeSelection.active) {
            return;
        }
        moveURangeSelection(e.clientX, e.clientY);
    });

    document.addEventListener("mouseup", function() {
        endURangeSelection();
    });

    document.addEventListener("touchstart", function(e) {
        if (e.touches.length !== 1) {
            return;
        }
        var touch = e.touches[0];
        var slot = getEmptySlotAtPoint(touch.clientX, touch.clientY);
        if (!slot) {
            return;
        }
        e.preventDefault();
        beginURangeSelection(slot);
    }, {passive: false});

    document.addEventListener("touchmove", function(e) {
        if (!uRangeSelection.active || e.touches.length !== 1) {
            return;
        }
        e.preventDefault();
        moveURangeSelection(e.touches[0].clientX, e.touches[0].clientY);
    }, {passive: false});

    document.addEventListener("touchend", function() {
        endURangeSelection();
    });

    document.addEventListener("touchcancel", function() {
        if (!uRangeSelection.active) {
            return;
        }
        uRangeSelection.active = false;
        if (uRangeSelection.rackUnits) {
            uRangeSelection.rackUnits.classList.remove("u-selecting-active");
        }
        uRangeSelection.rackUnits = null;
        clearURangeSelectionHighlight();
    });

    // ============ Single-rack management functions ============
    window.openAssignModal = function(uStart, uEndOrSide, maybeSide) {
        var uEnd = uStart;
        var side = "front";
        if (typeof uEndOrSide === "string") {
            side = uEndOrSide === "back" ? "back" : "front";
        } else if (typeof uEndOrSide === "number" && !isNaN(uEndOrSide)) {
            uEnd = uEndOrSide;
            side = maybeSide === "back" ? "back" : "front";
        }

        var minU = Math.min(uStart, uEnd);
        var maxU = Math.max(uStart, uEnd);

        isEditMode = false;
        editingHostId = null;
        editingHostType = "zabbix";
        selectedHostId = null;
        document.getElementById("modal-title").textContent = i18n.assign_host;
        document.getElementById("modal-u-start").value = minU;
        document.getElementById("modal-u-end").value = maxU;
        setModalRackSide(side);
        document.getElementById("modal-group-select").value = "";
        document.getElementById("modal-host-search").value = "";
        document.getElementById("host-select-list").innerHTML = "";
        document.getElementById("modal-custom-host-name").value = "";
        document.getElementById("modal-custom-host-ip").value = "";
        document.getElementById("host-type-tabs").style.display = "flex";
        switchAssignTab("zabbix");
        document.getElementById("edit-host-info").style.display = "none";
        document.getElementById("btn-remove-host").style.display = "none";
        document.getElementById("assign-modal").classList.add("visible");
        loadHosts();
    };

    window.openEditModal = function(elem) {
        var hostData = JSON.parse(elem.getAttribute("data-host"));
        isEditMode = true;
        editingHostId = hostData.hostid;
        selectedHostId = hostData.hostid;
        editingHostType = hostData.custom || String(hostData.hostid).indexOf("custom:") === 0 ? "custom" : "zabbix";

        document.getElementById("modal-title").textContent = i18n.edit_host_position;
        document.getElementById("edit-host-name").textContent = hostData.name;
        document.getElementById("edit-host-ip").textContent = hostData.ip + " | " + hostData.groups;
        document.getElementById("modal-u-start").value = hostData.u_start;
        document.getElementById("modal-u-end").value = hostData.u_end;
        setModalRackSide(hostData.side || "front");
        document.getElementById("host-type-tabs").style.display = "none";
        document.getElementById("host-select-section").classList.remove("is-active");
        document.getElementById("custom-host-section").classList.remove("is-active");
        document.getElementById("modal-custom-host-name").value = editingHostType === "custom" ? (hostData.name || "") : "";
        document.getElementById("modal-custom-host-ip").value = editingHostType === "custom" ? (hostData.ip || "") : "";
        document.getElementById("edit-host-info").style.display = "block";
        document.getElementById("btn-remove-host").style.display = "inline-block";
        document.getElementById("assign-modal").classList.add("visible");
    };

    window.closeAssignModal = function() {
        document.getElementById("assign-modal").classList.remove("visible");
    };

    window.loadHosts = function() {
        var groupId = document.getElementById("modal-group-select").value;
        var search = document.getElementById("modal-host-search").value;

        var url = "zabbix.php?action=hosts.get";
        if (groupId) url += "&groupid=" + encodeURIComponent(groupId);
        if (search) url += "&search=" + encodeURIComponent(search);

        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderHostList(data.hosts);
                }
            })
            .catch(function() {
                document.getElementById("host-select-list").innerHTML = "<div style=\\"padding:20px;text-align:center;color:#dc3545\\">" + i18n.operation_failed + "</div>";
            });
    };

    window.debounceLoadHosts = function() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadHosts, 300);
    };

    function renderHostList(hosts) {
        var html = "";
        hosts.forEach(function(host) {
            var statusClass = host.in_rack ? " in-rack" : "";
            var statusText = host.in_rack ? "(" + host.current_room + "/" + host.current_rack + ")" : "";
            html += "<div class=\\"host-select-item\\" data-hostid=\\"" + host.hostid + "\\" onclick=\\"selectHost(this, \x27" + host.hostid + "\x27)\\">";
            html += "<div class=\\"host-info\\">";
            html += "<div>" + escapeHtml(host.name) + "</div>";
            html += "<div style=\\"font-size:11px;color:#666\\">" + escapeHtml(host.main_ip) + "</div>";
            html += "</div>";
            if (host.in_rack) {
                html += "<span class=\\"host-status in-rack\\">" + statusText + "</span>";
            }
            html += "</div>";
        });
        document.getElementById("host-select-list").innerHTML = html || "<div style=\\"padding:20px;text-align:center;color:#666\\">" + i18n.no_matching_hosts + "</div>";
    }

    window.selectHost = function(elem, hostId) {
        document.querySelectorAll(".host-select-item").forEach(function(item) {
            item.classList.remove("selected");
        });
        elem.classList.add("selected");
        selectedHostId = hostId;
    };

    window.saveHost = function() {
        var hostType = isEditMode ? editingHostType : activeHostTab;
        var hostId = isEditMode ? editingHostId : selectedHostId;
        var customName = document.getElementById("modal-custom-host-name").value.trim();
        var customIp = document.getElementById("modal-custom-host-ip").value.trim();

        if (hostType === "custom" && (!customName || !customIp)) {
            RackUI.message(i18n.custom_host_required);
            return;
        }

        if (hostType !== "custom" && !hostId) {
            RackUI.message(i18n.select_a_host);
            return;
        }

        var uStart = parseInt(document.getElementById("modal-u-start").value);
        var uEnd = parseInt(document.getElementById("modal-u-end").value);

        if (!uStart || !uEnd || uStart > uEnd) {
            RackUI.message(i18n.invalid_u_range);
            return;
        }

        if (hostType === "custom") {
            doAssignCustomHost(isEditMode ? hostId : "", customName, customIp, uStart, uEnd);
        } else if (isEditMode) {
            var removeFormData = new FormData();
            removeFormData.append("action", "host.remove");
            removeFormData.append("hostid", hostId);

            fetch("zabbix.php", {
                method: "POST",
                body: removeFormData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    doAssignHost(hostId, uStart, uEnd);
                } else {
                    RackUI.message(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function() {
                RackUI.message(i18n.operation_failed);
            });
        } else {
            doAssignHost(hostId, uStart, uEnd);
        }
    };

    function doAssignHost(hostId, uStart, uEnd) {
        var formData = new FormData();
        formData.append("action", "host.assign");
        formData.append("hostid", hostId);
        formData.append("host_type", "zabbix");
        formData.append("room_id", currentRoomId);
        formData.append("rack_id", currentRackId);
        formData.append("u_start", uStart);
        formData.append("u_end", uEnd);
        formData.append("side", getModalRackSide());

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                RackUI.message(data.error || data.message || i18n.operation_failed);
            }
        })
        .catch(function() {
            RackUI.message(i18n.operation_failed);
        });
    }

    function doAssignCustomHost(hostId, name, ip, uStart, uEnd) {
        var formData = new FormData();
        formData.append("action", "host.assign");
        formData.append("hostid", hostId || "");
        formData.append("host_type", "custom");
        formData.append("custom_name", name);
        formData.append("custom_ip", ip);
        formData.append("room_id", currentRoomId);
        formData.append("rack_id", currentRackId);
        formData.append("u_start", uStart);
        formData.append("u_end", uEnd);
        formData.append("side", getModalRackSide());

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                RackUI.message(data.error || data.message || i18n.operation_failed);
            }
        })
        .catch(function() {
            RackUI.message(i18n.operation_failed);
        });
    }

    window.removeHostFromModal = async function() {
        if (!editingHostId) return;

        if (!await RackUI.confirm(i18n.confirm_remove_host)) {
            return;
        }

        var formData = new FormData();
        formData.append("action", "host.remove");
        formData.append("hostid", editingHostId);

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                RackUI.message(data.error || data.message || i18n.operation_failed);
            }
        })
        .catch(function() {
            RackUI.message(i18n.operation_failed);
        });
    };

    window.removeHost = async function(hostId) {
        if (!await RackUI.confirm(i18n.confirm_remove_host)) {
            return;
        }

        var formData = new FormData();
        formData.append("action", "host.remove");
        formData.append("hostid", hostId);

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                RackUI.message(data.error || data.message || i18n.operation_failed);
            }
        })
        .catch(function() {
            RackUI.message(i18n.operation_failed);
        });
    };

    // ============ Alarm functions ============
    window.showProblems = function(hostId, hostName) {
        document.getElementById("problem-host-name").textContent = hostName;
        document.getElementById("problem-list").innerHTML = "<li class=\\"problem-item\\" style=\\"justify-content:center;\\"><span>" + i18n.loading + "</span></li>";
        document.getElementById("problem-modal").classList.add("visible");

        var formData = new FormData();
        formData.append("action", "host.problems");
        formData.append("hostid", hostId);

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var listHtml = "";
            if (data.success && data.problems && data.problems.length > 0) {
                data.problems.forEach(function(problem) {
                    listHtml += "<li class=\\"problem-item\\">";
                    listHtml += "<span class=\\"problem-severity severity-" + problem.severity + "\\"></span>";
                    listHtml += "<div class=\\"problem-info\\">";
                    listHtml += "<div class=\\"problem-name\\">" + escapeHtml(problem.name) + "</div>";
                    listHtml += "<div class=\\"problem-time\\">🕐 " + escapeHtml(problem.time) + " | " + escapeHtml(problem.severity_name) + "</div>";
                    listHtml += "</div>";
                    listHtml += "</li>";
                });
            } else {
                listHtml = "<li class=\\"no-problems\\">" + i18n.no_active_problems + "</li>";
            }
            document.getElementById("problem-list").innerHTML = listHtml;
        })
        .catch(function(error) {
            document.getElementById("problem-list").innerHTML = "<li class=\\"problem-item\\" style=\\"color:#dc3545;\\">" + i18n.fetch_problems_failed + "</li>";
        });
    };

    window.closeProblemModal = function() {
        document.getElementById("problem-modal").classList.remove("visible");
    };

    // ============ Keyboard events and closing dialogs by clicking outside ============
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            document.querySelectorAll(".modal-overlay.visible, .problem-modal.visible, .rack-detail-modal.visible").forEach(function(modal) {
                modal.classList.remove("visible");
            });
        }
    });

    document.querySelectorAll(".modal-overlay").forEach(function(modal) {
        modal.addEventListener("click", function(e) {
            if (e.target === this) {
                this.classList.remove("visible");
            }
        });
    });

    document.getElementById("problem-modal").addEventListener("click", function(e) {
        if (e.target === this) {
            closeProblemModal();
        }
    });

    // ============ Automatic alarm status refresh ============
    var REFRESH_INTERVAL = 30000; // Refresh every 30 seconds

    /**
     * Collect the IDs of all hosts on the current page that need monitoring
     */
    function collectAllHostIds() {
        var hostIds = [];
        var hostKeys = ["hosts", "hosts_front", "hosts_back"];
        // Collect from allRacksData in overview mode
        if (allRacksData && allRacksData.length > 0) {
            for (var i = 0; i < allRacksData.length; i++) {
                for (var k = 0; k < hostKeys.length; k++) {
                    var hosts = allRacksData[i][hostKeys[k]] || [];
                    for (var j = 0; j < hosts.length; j++) {
                        if (hosts[j].hostid && String(hosts[j].hostid).indexOf("custom:") !== 0 && hostIds.indexOf(hosts[j].hostid) === -1) {
                            hostIds.push(hosts[j].hostid);
                        }
                    }
                }
            }
        }
        // Collect from the DOM in single-rack mode
        document.querySelectorAll(".rack-unit-slot.occupied[data-host]").forEach(function(slot) {
            try {
                var hostData = JSON.parse(slot.getAttribute("data-host"));
                if (hostData.hostid && String(hostData.hostid).indexOf("custom:") !== 0 && hostIds.indexOf(hostData.hostid) === -1) {
                    hostIds.push(hostData.hostid);
                }
            } catch(e) {}
        });
        return hostIds;
    }

    /**
     * Get the CSS class name for a severity
     */
    function getSeverityClass(severity) {
        switch (parseInt(severity)) {
            case 5: return "severity-disaster";
            case 4: return "severity-high";
            case 3: return "severity-average";
            case 2: return "severity-warning";
            case 1: return "severity-info";
            case 0: return "severity-not-classified";
            default: return "";
        }
    }

    /**
     * Remove all alarm-related CSS classes from an element
     */
    function removeSeverityClasses(elem) {
        // Use a regular expression to remove all classes starting with severity- and the alarm-related classes
        // This is more robust than a fixed list and covers every possible severity class variant
        var toRemove = [];
        for (var i = 0; i < elem.classList.length; i++) {
            var cls = elem.classList[i];
            if (cls.indexOf("severity-") === 0 || cls === "has-problem" || cls === "no-problem" || cls === "has-alert") {
                toRemove.push(cls);
            }
        }
        for (var j = 0; j < toRemove.length; j++) {
            elem.classList.remove(toRemove[j]);
        }
    }

    function applyProblemDataToHosts(hosts, problemData) {
        var totalProblems = 0;
        var maxSeverity = -1;

        for (var j = 0; j < hosts.length; j++) {
            var hostId = hosts[j].hostid;
            if (problemData[hostId]) {
                hosts[j].problem_count = problemData[hostId].problem_count;
                hosts[j].max_severity = problemData[hostId].max_severity;
                totalProblems += problemData[hostId].problem_count;
                if (problemData[hostId].max_severity > maxSeverity) {
                    maxSeverity = problemData[hostId].max_severity;
                }
            } else {
                hosts[j].problem_count = 0;
                hosts[j].max_severity = -1;
            }
        }

        return {
            totalProblems: totalProblems,
            maxSeverity: maxSeverity
        };
    }

    /**
     * Update the rack cards in overview mode and the alarm data in allRacksData
     */
    function updateOverviewMode(problemData) {
        if (!allRacksData || allRacksData.length === 0) return;

        for (var i = 0; i < allRacksData.length; i++) {
            var rackData = allRacksData[i];
            var hosts = rackData.hosts || [];
            var hostsFront = rackData.hosts_front || [];
            var hostsBack = rackData.hosts_back || [];
            var summary = applyProblemDataToHosts(hosts, problemData);
            applyProblemDataToHosts(hostsFront, problemData);
            applyProblemDataToHosts(hostsBack, problemData);
            var totalProblems = summary.totalProblems;
            var maxSeverity = summary.maxSeverity;

            // Update allRacksData
            rackData.problem_count = totalProblems;
            rackData.max_severity = maxSeverity;

            // Update the DOM - rack cards (matched exactly by data-rack-id)
            var rackCards = document.querySelectorAll(".rack-card[data-rack-id]");
            rackCards.forEach(function(card) {
                if (card.getAttribute("data-rack-id") !== rackData.id) return;

                removeSeverityClasses(card);
                // Update the alarm badge
                var badge = card.querySelector(".rack-alert-badge");
                if (totalProblems > 0) {
                    card.classList.add("has-alert");
                    card.classList.add("severity-" + maxSeverity);
                    if (badge) {
                        badge.textContent = totalProblems;
                        badge.setAttribute("title", totalProblems + " problems");
                    } else {
                        // Create a new alarm badge
                        var header = card.querySelector(".rack-card-header");
                        if (header) {
                            var newBadge = document.createElement("div");
                            newBadge.className = "rack-alert-badge";
                            newBadge.textContent = totalProblems;
                            newBadge.setAttribute("title", totalProblems + " problems");
                            header.appendChild(newBadge);
                        }
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }

                // Update the alarm colors in the mini visualization
                var miniUnits = card.querySelectorAll(".rack-mini-unit");
                miniUnits.forEach(function(unit) {
                    removeSeverityClasses(unit);
                });
                // Recompute the colors of the mini visualization
                var rackHeight = parseInt(rackData.height) || 42;
                for (var k = 0; k < rackHeight; k++) {
                    var uPos = rackHeight - k;
                    var unitElem = miniUnits[k];
                    if (!unitElem) continue;
                    for (var h = 0; h < hosts.length; h++) {
                        if (uPos >= hosts[h].u_start && uPos <= hosts[h].u_end) {
                            if (parseInt(hosts[h].problem_count) > 0 && hosts[h].max_severity >= 0) {
                                unitElem.classList.add("severity-" + hosts[h].max_severity);
                            }
                            break;
                        }
                    }
                }
            });
        }

        // Update the alarm count in the statistics cards
        var statNumbers = document.querySelectorAll(".stat-card .stat-number");
        if (statNumbers.length >= 3) {
            var totalProblemsInRoom = 0;
            for (var r = 0; r < allRacksData.length; r++) {
                totalProblemsInRoom += allRacksData[r].problem_count;
            }
            // The third statistics card is the alarm count (in overview mode)
            var statLabels = document.querySelectorAll(".stat-card .stat-label");
            for (var s = 0; s < statLabels.length; s++) {
                var labelParent = statLabels[s].closest(".stat-card");
                if (labelParent && labelParent.querySelector(".stat-icon") &&
                    labelParent.querySelector(".stat-icon").textContent.indexOf("🚨") !== -1) {
                    labelParent.querySelector(".stat-number").textContent = totalProblemsInRoom;
                    break;
                }
            }
        }
    }

    /**
     * Update the alarm status of rack U positions in single-rack mode
     */
    function updateSingleRackMode(problemData) {
        document.querySelectorAll(".rack-unit-slot.occupied[data-host]").forEach(function(slot) {
            try {
                var hostData = JSON.parse(slot.getAttribute("data-host"));
                var hostId = hostData.hostid;
                if (!hostId || !problemData[hostId]) return;

                var newCount = problemData[hostId].problem_count;
                var newSeverity = problemData[hostId].max_severity;

                // Update the data in the data-host attribute
                hostData.problem_count = newCount;
                slot.setAttribute("data-host", JSON.stringify(hostData));

                // Update the CSS classes
                removeSeverityClasses(slot);
                var hostStatus = parseInt(hostData.status === "disabled" ? 1 : 0) || 0;
                // Check whether it is determined by the status text
                if (slot.classList.contains("status-disabled")) {
                    // Keep the disabled state unchanged
                } else if (newCount > 0 && newSeverity >= 0) {
                    slot.classList.add(getSeverityClass(newSeverity));
                } else {
                    slot.classList.add("no-problem");
                }

                // Update the alarm count badge
                var badge = slot.querySelector(".problem-badge");
                if (newCount > 0) {
                    if (badge) {
                        badge.textContent = newCount;
                        badge.setAttribute("title", newCount + " problems");
                    } else {
                        // The badge must be added to host-slot-content
                        var content = slot.querySelector(".host-slot-content");
                        if (content) {
                            var hostName = hostData.name || hostData.host || "";
                            var newBadge = document.createElement("span");
                            newBadge.className = "problem-badge";
                            newBadge.textContent = newCount;
                            newBadge.setAttribute("title", newCount + " problems");
                            newBadge.setAttribute("onclick",
                                "event.stopPropagation();showProblems(\x27" + hostId + "\x27,\x27" + escapeHtml(hostName).replace(/\x27/g, "\\\x27") + "\x27)");
                            content.appendChild(newBadge);
                        }
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }
            } catch(e) {
                console.error("updateSingleRackMode error:", e);
            }
        });
    }

    /**
     * Main function that refreshes alarm data
     */
    function refreshProblems() {
        var hostIds = collectAllHostIds();
        if (hostIds.length === 0) return;

        fetch("zabbix.php?action=rack.problems.refresh&hostids=" + encodeURIComponent(hostIds.join(",")))
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (!result.success || !result.data) return;

                var problemData = result.data;

                // Update the overview mode
                updateOverviewMode(problemData);

                // Update the single-rack mode
                updateSingleRackMode(problemData);

                // If the details dialog is open, update it too
                var detailModal = document.getElementById("rack-detail-modal");
                if (detailModal && detailModal.classList.contains("visible") && detailRackData) {
                    var hostKeys = ["hosts", "hosts_front", "hosts_back"];
                    var needRerender = false;
                    for (var k = 0; k < hostKeys.length; k++) {
                        var hosts = detailRackData[hostKeys[k]] || [];
                        for (var i = 0; i < hosts.length; i++) {
                            var hid = hosts[i].hostid;
                            if (problemData[hid]) {
                                var oldCount = parseInt(hosts[i].problem_count) || 0;
                                var newCount = problemData[hid].problem_count;
                                if (oldCount !== newCount) needRerender = true;
                                hosts[i].problem_count = newCount;
                                hosts[i].max_severity = problemData[hid].max_severity;
                            }
                        }
                    }
                    if (needRerender) {
                        viewRackDetail(detailRackData.id);
                    }
                }
            })
            .catch(function(err) {
                console.warn("refreshProblems failed:", err);
            });
    }

    // Start the periodic refresh
    setInterval(refreshProblems, REFRESH_INTERVAL);

})();
</script>'));

// Display the page with the compatible renderer
$content->addItem((new CTag('script', true))->setAttribute('src', 'modules/zabbix_rack/assets/js/element.js?v=20260910'));
ViewRenderer::render($pageTitle, $styleTag, $content);
