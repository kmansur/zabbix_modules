<?php
/**
 * Rack view controller
 * Displays the rack visualization
 */

namespace Modules\ZabbixRack\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/RackPermission.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;
use Modules\ZabbixRack\Lib\RackPermission;
use Modules\ZabbixRack\Lib\HostRackManager;
use Modules\ZabbixRack\Lib\ViewRenderer;

class RackView extends CController {
    
    protected function init(): void {
        // Compatible with Zabbix 6 and 7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }
    
    protected function checkInput(): bool {
        $fields = [
            'room_id' => 'string',
            'rack_id' => 'string',
            'search' => 'string',
            'side' => 'string',
        ];
        
        $ret = $this->validateInput($fields);
        
        return $ret;
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $roomId = $this->getInput('room_id', '');
        $rackId = $this->getInput('rack_id', '');
        $search = $this->getInput('search', '');
        
        // Filter rooms by the current user's permissions
        $rooms = RackPermission::filterRoomsForCurrentUser(RackConfig::getRooms());
        $allowedRoomNames = [];
        foreach ($rooms as $room) {
            $allowedRoomNames[(string) ($room['name'] ?? '')] = true;
        }

        if ($roomId !== '' && !RackPermission::userCanAccessRoomId($roomId, $rooms)) {
            $roomId = '';
            $rackId = '';
        }
        if ($rackId !== '' && !RackPermission::userCanAccessRackId($rackId)) {
            $rackId = '';
        }
        
        // Get the rack list
        $racks = [];
        if ($roomId) {
            $racks = RackPermission::filterRacksForCurrentUser(RackConfig::getRacks($roomId), $rooms);
        } elseif (!empty($rooms)) {
            // Select the first accessible room by default
            $roomId = $rooms[0]['id'];
            $racks = RackPermission::filterRacksForCurrentUser(RackConfig::getRacks($roomId), $rooms);
        }
        
        // Get the currently selected rack
        $currentRack = null;
        $hosts = [];
        $hostsFront = [];
        $hostsBack = [];
        
        // Added: data of all racks (for the multi-rack display)
        $allRacksData = [];
        $showOverview = false; // Whether to show the rack overview mode
        
        if ($rackId) {
            // The user selected a specific rack: show the single-rack details
            $currentRack = RackConfig::getRack($rackId);
            if ($currentRack !== null && !RackPermission::userCanAccessRackId($rackId)) {
                $currentRack = null;
                $rackId = '';
            }
            $showOverview = ($currentRack === null);
        } else {
            // No rack selected: show an overview of all racks in the current room
            $showOverview = true;
        }
        
        // Get the hosts in the rack - single-rack mode
        if ($currentRack) {
            $roomInfo = RackConfig::getRoom($currentRack['room_id']);
            $roomName = $roomInfo ? $roomInfo['name'] : '';
            $hostsFront = HostRackManager::getHostsInRack(
                $roomName,
                $currentRack['name'],
                HostRackManager::SIDE_FRONT
            );
            $hostsBack = HostRackManager::getHostsInRack(
                $roomName,
                $currentRack['name'],
                HostRackManager::SIDE_BACK
            );
            $hostsFront = HostRackManager::enrichHostsWithProblems($hostsFront);
            $hostsBack = HostRackManager::enrichHostsWithProblems($hostsBack);
            $hosts = array_merge($hostsFront, $hostsBack);
        }
        
        // Get the data of all racks - overview mode
        if ($showOverview && !empty($racks)) {
            $roomInfo = RackConfig::getRoom($roomId);
            $roomName = $roomInfo ? $roomInfo['name'] : '';
            
            foreach ($racks as $rack) {
                $rackHostsFront = HostRackManager::getHostsInRack(
                    $roomName,
                    $rack['name'],
                    HostRackManager::SIDE_FRONT
                );
                $rackHostsBack = HostRackManager::getHostsInRack(
                    $roomName,
                    $rack['name'],
                    HostRackManager::SIDE_BACK
                );
                $rackProblems = 0;
                $maxSeverity = -1;
                $hostCount = count($rackHostsFront) + count($rackHostsBack);
                $usedUFront = 0;
                $usedUBack = 0;

                foreach ($rackHostsFront as $host) {
                    $usedUFront += ($host['u_end'] - $host['u_start'] + 1);
                }
                foreach ($rackHostsBack as $host) {
                    $usedUBack += ($host['u_end'] - $host['u_start'] + 1);
                }
                $usedU = max($usedUFront, $usedUBack);

                $rackHostsFront = HostRackManager::enrichHostsWithProblems($rackHostsFront);
                $rackHostsBack = HostRackManager::enrichHostsWithProblems($rackHostsBack);
                $rackHosts = array_merge($rackHostsFront, $rackHostsBack);

                foreach ($rackHosts as $host) {
                    if (($host['problem_count'] ?? 0) > 0) {
                        $rackProblems += (int) $host['problem_count'];
                        $hostSeverity = (int) ($host['max_severity'] ?? -1);
                        if ($hostSeverity > $maxSeverity) {
                            $maxSeverity = $hostSeverity;
                        }
                    }
                }
                
                $allRacksData[] = [
                    'id' => $rack['id'],
                    'name' => $rack['name'],
                    'room_id' => $rack['room_id'] ?? $roomId,
                    'height' => $rack['height'] ?? 42,
                    'description' => $rack['description'] ?? '',
                    'host_count' => $hostCount,
                    'used_u' => $usedU,
                    'problem_count' => $rackProblems,
                    'max_severity' => $maxSeverity,
                    'hosts' => $rackHosts,
                    'hosts_front' => $rackHostsFront,
                    'hosts_back' => $rackHostsBack,
                ];
            }
        }
        
        // If there is a search keyword, search hosts
        $searchResults = [];
        if ($search) {
            $searchResults = HostRackManager::searchAssignedHosts($search);
            $searchResults = array_values(array_filter($searchResults, static function ($row) use ($allowedRoomNames) {
                $roomName = (string) ($row['room_name'] ?? '');
                return $roomName !== '' && isset($allowedRoomNames[$roomName]);
            }));

            // In overview mode, filter the displayed racks by the search keyword
            if ($showOverview && !empty($allRacksData)) {
                // Collect the names of the racks containing the hosts found
                $matchedRackNames = [];
                foreach ($searchResults as $sr) {
                    $matchedRackNames[$sr['rack_name']] = true;
                }

                // Also support searching by rack name
                $searchLower = mb_strtolower($search);
                $filteredRacks = [];
                foreach ($allRacksData as $rackData) {
                    $rackNameLower = mb_strtolower($rackData['name']);
                    // Rack name matches or contains a host found
                    if (mb_strpos($rackNameLower, $searchLower) !== false
                        || isset($matchedRackNames[$rackData['name']])) {
                        $filteredRacks[] = $rackData;
                    }
                }
                $allRacksData = $filteredRacks;
            }
        }
        
        // Get the host group list (for the add host dialog)
        $hostGroups = HostRackManager::getHostGroups();
        
        $data = [
            'rooms' => $rooms,
            'racks' => $racks,
            'current_room_id' => $roomId,
            'current_rack_id' => $rackId,
            'current_rack' => $currentRack,
            'hosts' => $hosts,
            'hosts_front' => $hostsFront,
            'hosts_back' => $hostsBack,
            'search' => $search,
            'search_results' => $searchResults,
            'host_groups' => $hostGroups,
            'lang' => LanguageManager::class,
            'show_overview' => $showOverview,
            'all_racks_data' => $allRacksData,
        ];
        
        $response = new CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('rack_view'));
        
        $this->setResponse($response);
    }
}
