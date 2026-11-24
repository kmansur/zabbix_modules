<?php
/**
 * Rack management controller
 * Manages the configuration of rooms and racks
 */

namespace Modules\ZabbixRack\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/RackPermission.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;
use Modules\ZabbixRack\Lib\RackPermission;
use Modules\ZabbixRack\Lib\HostRackManager;

class RackManage extends CController {
    
    protected function init(): void {
        // Compatible with Zabbix 6 and 7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }
    
    protected function checkInput(): bool {
        return true;
    }
    
    protected function checkPermissions(): bool {
        return RackPermission::canAccessManage();
    }
    
    protected function doAction(): void {
        $userGroupOptions = RackPermission::getUserGroupOptions();
        $userOptions = RackPermission::getUserOptions();
        $userGroupMap = [];
        foreach ($userGroupOptions as $group) {
            $userGroupMap[$group['usrgrpid']] = $group;
        }
        $userMap = [];
        foreach ($userOptions as $user) {
            $userMap[$user['userid']] = $user;
        }

        // Get all rooms
        $rooms = RackConfig::getRooms();
        foreach ($rooms as &$room) {
            $room['user_groups'] = RackPermission::normalizeIdList($room['user_groups'] ?? []);
            $room['users'] = RackPermission::normalizeIdList($room['users'] ?? []);
            $room['permission_summary'] = RackPermission::formatRoomPermissionSummary(
                $room,
                $userGroupMap,
                $userMap
            );
        }
        unset($room);
        
        // Get all racks
        $racks = RackConfig::getRacks();
        
        // Add the room name to each rack
        $roomNames = [];
        foreach ($rooms as $room) {
            $roomNames[$room['id']] = $room['name'];
        }
        
        // Compute the used U positions of each rack
        foreach ($racks as &$rack) {
            $rack['room_name'] = $roomNames[$rack['room_id']] ?? '';
            
            // Get the hosts in the rack to compute the used U positions
            $usedU = 0;
            if (!empty($rack['room_name']) && !empty($rack['name'])) {
                try {
                    $hosts = HostRackManager::getHostsInRack($rack['room_name'], $rack['name']);
                    foreach ($hosts as $host) {
                        if (isset($host['u_height']) && $host['u_height'] > 0) {
                            $usedU += $host['u_height'];
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore errors and use the default value
                }
            }
            $rack['used_u'] = $usedU;
        }
        
        $data = [
            'rooms' => $rooms,
            'racks' => $racks,
            'user_groups' => $userGroupOptions,
            'users' => $userOptions,
            'lang' => LanguageManager::class
        ];
        
        $response = new CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('rack_manage'));
        
        $this->setResponse($response);
    }
}
