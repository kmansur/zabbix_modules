<?php
/**
 * Alarm event finder
 * Centralizes the alarm query logic of the reports
 * 
 * Key improvement: queries all alarm events that overlap the report period, not only alarms raised within the period.
 * 
 * Match condition (the alarm time range overlaps the report period):
 *   clock <= till AND (r_clock >= from OR r_clock = 0/unresolved)
 * 
 * Covered scenarios:
 *   A) raised and resolved within the period      → clock ∈ [from, till], r_clock ∈ [from, till]
 *   B) raised within the period, unresolved      → clock ∈ [from, till], r_clock = 0
 *   C) raised before the period, resolved within → clock < from, r_clock ∈ [from, till]
 *   D) raised before the period, still unresolved → clock < from, r_clock = 0
 *   E) raised before the period, resolved after  → clock < from, r_clock > till
 */

namespace Modules\ZabbixReports\Lib;

use API;

class ProblemFinder {

    /**
     * Get all problem events that overlap the given time period (detailed version)
     * 
     * @param int $from period start timestamp
     * @param int $till period end timestamp
     * @param int $limit maximum number of results
     * @return array result with problemEvents, problemCount, resolvedCount
     */
    public static function getProblemsInPeriod(int $from, int $till, int $limit = 500, ?array $hostids = null): array {
        // An explicitly empty host selection must not fall back to an unfiltered,
        // system-wide event query.
        if ($hostids !== null && empty($hostids)) {
            return [
                'problemEvents' => [],
                'problemCount' => 0,
                'resolvedCount' => 0,
                'recoveryMap' => [],
                'triggerHostMap' => [],
                'triggerStatusMap' => []
            ];
        }
        // ====================================================
        // Step 1: get problem events raised within the report period (scenarios A and B)
        // ====================================================
        $eventOptions = [
            'output' => ['eventid', 'objectid', 'name', 'clock', 'r_eventid', 'severity'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_from' => $from,
            'time_till' => $till,
            'sortfield' => ['clock'],
            'sortorder' => 'DESC',
            'limit' => $limit,
            'selectHosts' => ['hostid', 'name'],
        ];
        if ($hostids !== null) {
            $eventOptions['hostids'] = $hostids;
        }
        $eventsInPeriod = API::Event()->get($eventOptions);
        if (!is_array($eventsInPeriod)) {
            $eventsInPeriod = [];
        }

        // ====================================================
        // Step 2: get problem events raised before the report period but still active within it (scenarios C, D, E)
        // Strategy: query events raised before the period start, then filter by recovery time
        // ====================================================
        $eventBeforeOptions = [
            'output' => ['eventid', 'objectid', 'name', 'clock', 'r_eventid', 'severity'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_till' => $from - 1,  // Raised before the period start
            'sortfield' => ['clock'],
            'sortorder' => 'DESC',
            'limit' => 2000,  // Raise the limit to capture all events spanning periods
            'selectHosts' => ['hostid', 'name'],
        ];
        if ($hostids !== null) {
            $eventBeforeOptions['hostids'] = $hostids;
        }
        $eventsBeforePeriod = API::Event()->get($eventBeforeOptions);
        if (!is_array($eventsBeforePeriod)) {
            $eventsBeforePeriod = [];
        }

        // Get the recovery times of events raised before the period
        $earlyRecoveryMap = [];
        $earlyRecoveryIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (!empty($event['r_eventid']) && $event['r_eventid'] != 0) {
                $earlyRecoveryIds[] = $event['r_eventid'];
            }
        }
        if (!empty($earlyRecoveryIds)) {
            $earlyRecoveryEvents = API::Event()->get([
                'output' => ['eventid', 'clock'],
                'eventids' => array_values(array_unique($earlyRecoveryIds)),
            ]);
            if (is_array($earlyRecoveryEvents)) {
                foreach ($earlyRecoveryEvents as $re) {
                    $earlyRecoveryMap[$re['eventid']] = (int)$re['clock'];
                }
            }
        }

        // Validation: for events raised before the period and unresolved (r_eventid=0), confirm through the Problem API that they are really active
        // r_eventid=0 in the event table does not necessarily mean still active; it may have been closed manually, the trigger deleted, etc.
        $unresolvedEventIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                $unresolvedEventIds[] = $event['eventid'];
            }
        }
        $confirmedActiveProblems = [];
        if (!empty($unresolvedEventIds)) {
            $activeProblems = API::Problem()->get([
                'output' => ['eventid'],
                'eventids' => $unresolvedEventIds,
            ]);
            if (is_array($activeProblems)) {
                foreach ($activeProblems as $p) {
                    $confirmedActiveProblems[$p['eventid']] = true;
                }
            }
        }

        // Filter: keep only events still active within the report period
        $activeBeforePeriod = [];
        foreach ($eventsBeforePeriod as $event) {
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                // Unresolved → must be confirmed as still active through the Problem API
                if (isset($confirmedActiveProblems[$event['eventid']])) {
                    $activeBeforePeriod[] = $event;
                }
            } elseif (isset($earlyRecoveryMap[$event['r_eventid']])) {
                $rClock = $earlyRecoveryMap[$event['r_eventid']];
                if ($rClock >= $from) {
                    // Resolved only after the period start (recovery time >= period start) → overlaps the period
                    $activeBeforePeriod[] = $event;
                }
                // If rClock < from → resolved before the period start → excluded
            }
            // If there is an r_eventid but the recovery event cannot be found → excluded to be safe
        }

        // ====================================================
        // Step 3: merge and deduplicate (by eventid)
        // ====================================================
        $allEventIds = array_column($eventsInPeriod, 'eventid');
        foreach ($activeBeforePeriod as $event) {
            if (!in_array($event['eventid'], $allEventIds)) {
                $eventsInPeriod[] = $event;
                $allEventIds[] = $event['eventid'];
            }
        }

        // ====================================================
        // Step 3.5: check the status of the trigger of each event (exists/enabled/disabled/deleted)
        // The event table keeps all history, including events of deleted/disabled triggers
        // Query trigger status through the Trigger API and mark rather than filter
        // ====================================================
        $triggerStatusMap = []; // objectid => 'enabled' | 'disabled' | 'deleted'
        $allObjectIds = array_values(array_unique(array_column($eventsInPeriod, 'objectid')));
        if (!empty($allObjectIds)) {
            $existingTriggers = API::Trigger()->get([
                'output' => ['triggerid', 'status'],
                'triggerids' => $allObjectIds,
                'preservekeys' => true,
            ]);
            foreach ($allObjectIds as $oid) {
                if (!isset($existingTriggers[$oid])) {
                    $triggerStatusMap[$oid] = 'deleted';
                } elseif ((int)$existingTriggers[$oid]['status'] !== TRIGGER_STATUS_ENABLED) {
                    $triggerStatusMap[$oid] = 'disabled';
                } else {
                    $triggerStatusMap[$oid] = 'enabled';
                }
            }
        }

        // Sort by time descending
        usort($eventsInPeriod, function($a, $b) {
            return (int)$b['clock'] - (int)$a['clock'];
        });

        // Truncate to the limit
        if (count($eventsInPeriod) > $limit) {
            $eventsInPeriod = array_slice($eventsInPeriod, 0, $limit);
        }

        // ====================================================
        // Step 4: compute statistics
        // ====================================================
        // Total problems = number of problem events overlapping the period
        $problemCount = count($eventsInPeriod);

        // Recovery events are still counted as before: recovery events raised within the period (value=TRIGGER_VALUE_FALSE)
        // This represents "how many alarms actually recovered within this period"
        $resolvedOptions = [
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_FALSE],
            'time_from' => $from,
            'time_till' => $till
        ];
        if ($hostids !== null) {
            $resolvedOptions['hostids'] = $hostids;
        }
        $resolvedCount = API::Event()->get($resolvedOptions);

        // ====================================================
        // Step 5: get recovery event times in bulk
        // ====================================================
        $recoveryMap = [];
        $allRecoveryIds = [];
        foreach ($eventsInPeriod as $event) {
            if (!empty($event['r_eventid']) && $event['r_eventid'] != 0) {
                $allRecoveryIds[] = $event['r_eventid'];
            }
        }
        if (!empty($allRecoveryIds)) {
            $recoveryEvents = API::Event()->get([
                'output' => ['eventid', 'clock'],
                'eventids' => array_values(array_unique($allRecoveryIds)),
            ]);
            if (is_array($recoveryEvents)) {
                foreach ($recoveryEvents as $re) {
                    $recoveryMap[$re['eventid']] = $re['clock'];
                }
            }
        }

        // ====================================================
        // Step 6: resolve host names (for events where selectHosts returned nothing, fill in via the Trigger API)
        // ====================================================
        $unknownTriggerIds = [];
        foreach ($eventsInPeriod as $event) {
            if (empty($event['hosts'])) {
                $unknownTriggerIds[$event['objectid']] = true;
            }
        }
        $triggerHostMap = [];
        if (!empty($unknownTriggerIds)) {
            $triggers = API::Trigger()->get([
                'output' => ['triggerid'],
                'triggerids' => array_keys($unknownTriggerIds),
                'selectHosts' => ['name'],
            ]);
            if (is_array($triggers)) {
                foreach ($triggers as $trigger) {
                    if (!empty($trigger['hosts'])) {
                        $triggerHostMap[$trigger['triggerid']] = $trigger['hosts'][0]['name'];
                    }
                }
            }
        }

        return [
            'problemEvents' => $eventsInPeriod,
            'problemCount' => $problemCount,
            'resolvedCount' => (int)$resolvedCount,
            'recoveryMap' => $recoveryMap,
            'triggerHostMap' => $triggerHostMap,
            'triggerStatusMap' => $triggerStatusMap,
        ];
    }

    /**
     * Simplified version: get problem events overlapping the given time period (basic fields only)
     * For cases such as the Send class that only need eventid/objectid
     * 
     * @param int $from period start timestamp
     * @param int $till period end timestamp
     * @return array result with events, problemCount, resolvedCount
     */
    public static function getSimpleProblemsInPeriod(int $from, int $till): array {
        // Step 1: get events raised within the period
        $eventsInPeriod = API::Event()->get([
            'output' => ['eventid', 'objectid', 'clock', 'r_eventid'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_from' => $from,
            'time_till' => $till,
        ]);
        if (!is_array($eventsInPeriod)) {
            $eventsInPeriod = [];
        }

        // Step 2: get events raised before the period but still active within it
        $eventsBeforePeriod = API::Event()->get([
            'output' => ['eventid', 'objectid', 'clock', 'r_eventid'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_till' => $from - 1,
            'limit' => 2000,
        ]);
        if (!is_array($eventsBeforePeriod)) {
            $eventsBeforePeriod = [];
        }

        // Get recovery times
        $earlyRecoveryMap = [];
        $earlyRecoveryIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (!empty($event['r_eventid']) && $event['r_eventid'] != 0) {
                $earlyRecoveryIds[] = $event['r_eventid'];
            }
        }
        if (!empty($earlyRecoveryIds)) {
            $earlyRecoveryEvents = API::Event()->get([
                'output' => ['eventid', 'clock'],
                'eventids' => array_values(array_unique($earlyRecoveryIds)),
            ]);
            if (is_array($earlyRecoveryEvents)) {
                foreach ($earlyRecoveryEvents as $re) {
                    $earlyRecoveryMap[$re['eventid']] = (int)$re['clock'];
                }
            }
        }

        // Validation: for events raised before the period and unresolved, confirm through the Problem API that they are really active
        $unresolvedEventIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                $unresolvedEventIds[] = $event['eventid'];
            }
        }
        $confirmedActiveProblems = [];
        if (!empty($unresolvedEventIds)) {
            $activeProblems = API::Problem()->get([
                'output' => ['eventid'],
                'eventids' => $unresolvedEventIds,
            ]);
            if (is_array($activeProblems)) {
                foreach ($activeProblems as $p) {
                    $confirmedActiveProblems[$p['eventid']] = true;
                }
            }
        }

        // Filter active events
        $allEventIds = array_column($eventsInPeriod, 'eventid');
        foreach ($eventsBeforePeriod as $event) {
            $include = false;
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                // Must be confirmed as still active through the Problem API
                if (isset($confirmedActiveProblems[$event['eventid']])) {
                    $include = true;
                }
            } elseif (isset($earlyRecoveryMap[$event['r_eventid']])) {
                if ($earlyRecoveryMap[$event['r_eventid']] >= $from) {
                    $include = true;  // Resolved only within the period
                }
            }
            if ($include && !in_array($event['eventid'], $allEventIds)) {
                $eventsInPeriod[] = $event;
                $allEventIds[] = $event['eventid'];
            }
        }

        // Validation: check trigger status (exists/enabled/disabled/deleted), marking rather than filtering
        $triggerStatusMap = [];
        $allObjectIds = array_values(array_unique(array_column($eventsInPeriod, 'objectid')));
        if (!empty($allObjectIds)) {
            $existingTriggers = API::Trigger()->get([
                'output' => ['triggerid', 'status'],
                'triggerids' => $allObjectIds,
                'preservekeys' => true,
            ]);
            foreach ($allObjectIds as $oid) {
                if (!isset($existingTriggers[$oid])) {
                    $triggerStatusMap[$oid] = 'deleted';
                } elseif ((int)$existingTriggers[$oid]['status'] !== TRIGGER_STATUS_ENABLED) {
                    $triggerStatusMap[$oid] = 'disabled';
                } else {
                    $triggerStatusMap[$oid] = 'enabled';
                }
            }
        }

        // Statistics
        $problemCount = count($eventsInPeriod);

        $resolvedCount = API::Event()->get([
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_FALSE],
            'time_from' => $from,
            'time_till' => $till
        ]);

        return [
            'events' => $eventsInPeriod,
            'problemCount' => $problemCount,
            'resolvedCount' => (int)$resolvedCount,
            'triggerStatusMap' => $triggerStatusMap,
        ];
    }

    /**
     * Build the alarm information array from the problem event list
     * 
     * @param array $problemEvents problem event list
     * @param array $recoveryMap recovery event map [r_eventid => clock]
     * @param array $triggerHostMap trigger host map [triggerid => hostname]
     * @param string $unknownHostLabel label text for unknown hosts
     * @param array $triggerStatusMap trigger status map [triggerid => 'enabled'|'disabled'|'deleted']
     * @return array ['alertInfo' => [...], 'hostCounts' => [...], 'topHosts' => [...]]
     */
    public static function buildAlertInfo(array $problemEvents, array $recoveryMap, array $triggerHostMap, string $unknownHostLabel = 'Unknown Host', array $triggerStatusMap = []): array {
        $alertInfo = [];
        $hostCounts = [];

        foreach ($problemEvents as $event) {
            if (!empty($event['hosts'])) {
                $hostName = $event['hosts'][0]['name'];
            } elseif (isset($triggerHostMap[$event['objectid']])) {
                $hostName = $triggerHostMap[$event['objectid']];
            } else {
                $hostName = $unknownHostLabel;
            }

            $recoveryTime = null;
            if (!empty($event['r_eventid']) && isset($recoveryMap[$event['r_eventid']])) {
                $recoveryTime = date('Y-m-d H:i:s', $recoveryMap[$event['r_eventid']]);
            }

            // Whether the trigger is disabled or deleted
            $triggerDisabled = false;
            if (!empty($triggerStatusMap)) {
                $status = $triggerStatusMap[$event['objectid']] ?? 'enabled';
                $triggerDisabled = ($status === 'disabled' || $status === 'deleted');
            }

            $alertInfo[] = [
                'host' => $hostName,
                'alert' => $event['name'],
                'severity' => (int)($event['severity'] ?? 0),
                'time' => date('Y-m-d H:i:s', $event['clock']),
                'recovery_time' => $recoveryTime,
                'trigger_disabled' => $triggerDisabled,
            ];
            $hostCounts[$hostName] = ($hostCounts[$hostName] ?? 0) + 1;
        }

        arsort($hostCounts);
        $topHosts = array_slice($hostCounts, 0, 10, true);

        return [
            'alertInfo' => $alertInfo,
            'hostCounts' => $hostCounts,
            'topHosts' => $topHosts,
        ];
    }
}
