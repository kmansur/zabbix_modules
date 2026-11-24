<?php

namespace Modules\ZabbixGraphTrees\Actions;

use CController,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;

class GraphTreesData extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'type' => 'string',
            'hostid' => 'int32',
            'category' => 'string',
            'from' => 'string',
            'to' => 'string'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $type = $this->getInput('type', '');
        $hostid = $this->getInput('hostid', 0);

        $result = ['success' => false, 'error' => 'Invalid request'];

        switch ($type) {
            case 'validate_time_range':
                $result = $this->validateCustomTimeRange();
                break;
            case 'categories':
                $result = $this->getCategories($hostid);
                break;
            case 'graphs':
                $category = $this->getInput('category', '');
                $result = $this->getGraphs($hostid, $category);
                break;
        }

        // Save the session before output, so older debuggers do not warn when writing the session during PHP shutdown.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Validate using the same parser, user time zone and limits as native charts. */
    private function validateCustomTimeRange(): array {
        try {
            $timestamps = [];
            foreach (['from', 'to'] as $field) {
                $parser = new \CRangeTimeParser();
                if ($parser->parse($this->getInput($field, '')) !== \CParser::PARSE_SUCCESS) {
                    return ['success' => false, 'error' => LanguageManager::t('Select a valid start and end time.')];
                }
                $timestamps[$field] = $parser->getDateTime($field === 'from')->getTimestamp();
            }

            if ($timestamps['from'] >= $timestamps['to']) {
                return ['success' => false, 'error' => LanguageManager::t('Start time must be earlier than end time.')];
            }
            $period = $timestamps['to'] - $timestamps['from'] + 1;
            if ($period < ZBX_MIN_PERIOD) {
                return ['success' => false, 'error' => LanguageManager::t('Time range must be at least one minute.')];
            }
            if (class_exists('CTimePeriodHelper')) {
                $maxPeriod = \CTimePeriodHelper::getMaxPeriod();
            } else {
                // Zabbix 6.0 has no CTimePeriodHelper yet; follow its native graph validation.
                $limitParser = new \CRangeTimeParser();
                $limitParser->parse('now-' . \CSettingsHelper::get(\CSettingsHelper::MAX_PERIOD));
                $maxPeriod = time() - $limitParser->getDateTime(true)->getTimestamp();
            }
            if ($period > $maxPeriod + 1) {
                return ['success' => false, 'error' => LanguageManager::t('Time range exceeds the maximum allowed by Zabbix.')];
            }

            return [
                'success' => true,
                'from' => date('Y-m-d H:i:s', $timestamps['from']),
                'to' => date('Y-m-d H:i:s', $timestamps['to'])
            ];
        } catch (\Throwable $error) {
            error_log('GraphTreesData: time range validation failed: ' . $error->getMessage());
            return ['success' => false, 'error' => LanguageManager::t('Failed to validate time range. Please try again.')];
        }
    }

    /**
     * Get the host's category list (based on item tags)
     * Similar to the Application concept of older Zabbix versions
     */
    private function getCategories(int $hostid): array {
        if ($hostid <= 0) {
            return ['success' => false, 'error' => 'Invalid hostid'];
        }

        try {
            // Get all active items of the host and their tags
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'value_type', 'units'],
                'hostids' => [$hostid],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'selectTags' => ['tag', 'value'],
                'sortfield' => 'name'
            ]);

            // Get the host's predefined graphs
            $graphs = API::Graph()->get([
                'output' => ['graphid', 'name'],
                'hostids' => [$hostid],
                'selectItems' => ['itemid'],
                'sortfield' => 'name'
            ]);

            // Build the item → tag value map & the item information index
            $itemTagValues = [];
            $itemInfo = [];
            foreach ($items as $item) {
                $itemTagValues[$item['itemid']] = [];
                $itemInfo[$item['itemid']] = $item;
                if (!empty($item['tags'])) {
                    foreach ($item['tags'] as $tag) {
                        if (!empty($tag['value'])) {
                            $itemTagValues[$item['itemid']][] = $tag['value'];
                        }
                    }
                }
            }

            // Build the set of items already covered by predefined graphs
            $graphItemIds = [];
            foreach ($graphs as $graph) {
                foreach ($graph['items'] as $gItem) {
                    $graphItemIds[$gItem['itemid']] = true;
                }
            }

            // Group and count by tag value
            $categories = [];
            $uncategorizedCount = 0;
            $uncategorizedNumericCount = 0;
            foreach ($items as $item) {
                $tagValues = $itemTagValues[$item['itemid']];
                $isNumeric = in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                if (empty($tagValues)) {
                    $uncategorizedCount++;
                    if ($isNumeric) $uncategorizedNumericCount++;
                } else {
                    foreach ($tagValues as $tagValue) {
                        if (!isset($categories[$tagValue])) {
                            $categories[$tagValue] = [
                                'key' => $tagValue,
                                'name' => $tagValue,
                                'itemCount' => 0,
                                'numericItemCount' => 0,
                                'graphCount' => 0
                            ];
                        }
                        $categories[$tagValue]['itemCount']++;
                        if ($isNumeric) $categories[$tagValue]['numericItemCount']++;
                    }
                }
            }

            // Count the predefined graphs of each category
            $otherGraphCount = 0;
            foreach ($graphs as $graph) {
                $graphCategories = [];
                $hasUncategorizedItem = false;
                foreach ($graph['items'] as $gItem) {
                    if (isset($itemTagValues[$gItem['itemid']])) {
                        if (empty($itemTagValues[$gItem['itemid']])) {
                            $hasUncategorizedItem = true;
                        }
                        foreach ($itemTagValues[$gItem['itemid']] as $tagValue) {
                            $graphCategories[$tagValue] = true;
                        }
                    }
                }
                foreach ($graphCategories as $catName => $_) {
                    if (isset($categories[$catName])) {
                        $categories[$catName]['graphCount']++;
                    }
                }
                if ($hasUncategorizedItem) {
                    $otherGraphCount++;
                }
            }

            // Count the ad hoc graph cards of each category (standalone numeric items merged by unit, 8 per card)
            foreach ($categories as $catName => &$cat) {
                $orphanUnits = [];
                foreach ($items as $item) {
                    $inCat = in_array($catName, $itemTagValues[$item['itemid']] ?? []);
                    if ($inCat
                        && !isset($graphItemIds[$item['itemid']])
                        && in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
                    ) {
                        $u = $item['units'] ?? '';
                        $orphanUnits[$u] = ($orphanUnits[$u] ?? 0) + 1;
                    }
                }
                foreach ($orphanUnits as $cnt) {
                    $cat['graphCount'] += (int)ceil($cnt / 8);
                }
            }
            unset($cat);

            // Count the ad hoc graph cards of the "Other" category
            $otherOrphanUnits = [];
            foreach ($items as $item) {
                if (empty($itemTagValues[$item['itemid']])
                    && !isset($graphItemIds[$item['itemid']])
                    && in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
                ) {
                    $u = $item['units'] ?? '';
                    $otherOrphanUnits[$u] = ($otherOrphanUnits[$u] ?? 0) + 1;
                }
            }
            foreach ($otherOrphanUnits as $cnt) {
                $otherGraphCount += (int)ceil($cnt / 8);
            }

            ksort($categories);

            // Items without a category go to "Other"
            if ($uncategorizedCount > 0) {
                $categories['__other__'] = [
                    'key' => '__other__',
                    'name' => LanguageManager::t('Other'),
                    'itemCount' => $uncategorizedCount,
                    'numericItemCount' => $uncategorizedNumericCount,
                    'graphCount' => $otherGraphCount
                ];
            }

            // Compute the total number of cards for "All graphs"
            $totalCards = count($graphs);
            $allOrphanUnits = [];
            foreach ($items as $item) {
                if (!isset($graphItemIds[$item['itemid']])
                    && in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
                ) {
                    $u = $item['units'] ?? '';
                    $allOrphanUnits[$u] = ($allOrphanUnits[$u] ?? 0) + 1;
                }
            }
            foreach ($allOrphanUnits as $cnt) {
                $totalCards += (int)ceil($cnt / 8);
            }

            return [
                'success' => true,
                'categories' => array_values($categories),
                'totalGraphs' => $totalCards,
                'totalItems' => count($items)
            ];
        } catch (\Exception $e) {
            error_log("GraphTreesData: getCategories failed - " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the graph list of the given host + category
     * Returns predefined graphs (graphid) and automatically aggregated item graphs (itemids)
     */
    private function getGraphs(int $hostid, string $category): array {
        if ($hostid <= 0) {
            return ['success' => false, 'error' => 'Invalid hostid'];
        }

        try {
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'value_type', 'units'],
                'hostids' => [$hostid],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'selectTags' => ['tag', 'value'],
                'sortfield' => 'name'
            ]);

            $graphs = API::Graph()->get([
                'output' => ['graphid', 'name', 'width', 'height'],
                'hostids' => [$hostid],
                'selectItems' => ['itemid'],
                'sortfield' => 'name'
            ]);

            // Filter the items that belong to the current category
            $categoryItemIds = [];
            if ($category === '__all__') {
                $categoryItemIds = array_column($items, 'itemid');
            } elseif ($category === '__other__') {
                foreach ($items as $item) {
                    $hasTag = false;
                    if (!empty($item['tags'])) {
                        foreach ($item['tags'] as $tag) {
                            if (!empty($tag['value'])) {
                                $hasTag = true;
                                break;
                            }
                        }
                    }
                    if (!$hasTag) {
                        $categoryItemIds[] = $item['itemid'];
                    }
                }
            } else {
                foreach ($items as $item) {
                    if (!empty($item['tags'])) {
                        foreach ($item['tags'] as $tag) {
                            if ($tag['value'] === $category) {
                                $categoryItemIds[] = $item['itemid'];
                                break;
                            }
                        }
                    }
                }
            }
            $categoryItemIdsMap = array_flip($categoryItemIds);

            // Find the predefined graphs that belong to the current category
            $categoryGraphs = [];
            $graphItemIds = [];

            if ($category === '__all__') {
                $categoryGraphs = $graphs;
                foreach ($graphs as $graph) {
                    foreach ($graph['items'] as $gItem) {
                        $graphItemIds[$gItem['itemid']] = true;
                    }
                }
            } else {
                foreach ($graphs as $graph) {
                    $hasMatch = false;
                    foreach ($graph['items'] as $gItem) {
                        if (isset($categoryItemIdsMap[$gItem['itemid']])) {
                            $hasMatch = true;
                        }
                    }
                    if ($hasMatch) {
                        $categoryGraphs[] = $graph;
                        foreach ($graph['items'] as $gItem) {
                            $graphItemIds[$gItem['itemid']] = true;
                        }
                    }
                }
            }

            // Find numeric items without a predefined graph (standalone items)
            $orphanItems = [];
            foreach ($items as $item) {
                if (isset($categoryItemIdsMap[$item['itemid']]) && !isset($graphItemIds[$item['itemid']])) {
                    if (in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
                        $orphanItems[] = $item;
                    }
                }
            }

            // Build the result list
            $graphList = [];
            foreach ($categoryGraphs as $graph) {
                $graphList[] = [
                    'graphid' => $graph['graphid'],
                    'name' => $graph['name'],
                    'type' => 'graph'
                ];
            }

            // Merge similar standalone items into the same graph
            $mergedOrphans = $this->mergeOrphanItems($orphanItems);
            foreach ($mergedOrphans as $merged) {
                $graphList[] = [
                    'itemids' => $merged['itemids'],
                    'name' => $merged['name'],
                    'type' => 'adhoc'
                ];
            }

            return [
                'success' => true,
                'graphs' => $graphList
            ];
        } catch (\Exception $e) {
            error_log("GraphTreesData: getGraphs failed - " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Group standalone items by unit
     * Items with the same unit are drawn in the same graph (at most 8 lines)
     */
    private function mergeOrphanItems(array $items): array {
        if (empty($items)) {
            return [];
        }

        // Group by unit
        $groups = [];
        foreach ($items as $item) {
            $units = $item['units'] ?? '';
            $groups[$units][] = $item;
        }

        $result = [];
        foreach ($groups as $units => $groupItems) {
            if (count($groupItems) <= 8) {
                $result[] = [
                    'itemids' => array_column($groupItems, 'itemid'),
                    'name' => count($groupItems) === 1
                        ? $groupItems[0]['name']
                        : $this->findCommonPrefix($groupItems)
                ];
            } else {
                // Split large groups into groups of 8
                $chunks = array_chunk($groupItems, 8);
                foreach ($chunks as $i => $chunk) {
                    $name = $this->findCommonPrefix($chunk);
                    if (count($chunks) > 1) {
                        $name .= ' #' . ($i + 1);
                    }
                    $result[] = [
                        'itemids' => array_column($chunk, 'itemid'),
                        'name' => $name
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Find the common prefix of a group of item names
     */
    private function findCommonPrefix(array $items): string {
        if (count($items) === 1) {
            return $items[0]['name'];
        }

        $names = array_column($items, 'name');
        $prefix = $names[0];

        for ($i = 1; $i < count($names); $i++) {
            while (strlen($prefix) > 0 && strpos($names[$i], $prefix) !== 0) {
                $prefix = substr($prefix, 0, -1);
            }
            if (strlen($prefix) === 0) {
                break;
            }
        }

        $prefix = rtrim($prefix, " :-_/\\");

        if (strlen($prefix) >= 3) {
            return $prefix;
        }

        // Without a common prefix, use the first few names
        $display = array_slice($names, 0, 3);
        return implode(', ', $display) . (count($names) > 3 ? '...' : '');
    }
}
