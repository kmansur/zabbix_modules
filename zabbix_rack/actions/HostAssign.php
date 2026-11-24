<?php
/**
 * Controller that assigns hosts to racks
 */

namespace Modules\ZabbixRack\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';
require_once dirname(__DIR__) . '/lib/RackPermission.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\RackConfig;
use Modules\ZabbixRack\Lib\RackPermission;
use Modules\ZabbixRack\Lib\HostRackManager;

class HostAssign extends CController {
    
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
            'hostid' => 'string',
            'host_type' => 'string',
            'custom_name' => 'string',
            'custom_ip' => 'string',
            'room_id' => 'required|string',
            'rack_id' => 'required|string',
            'u_start' => 'required|int32',
            'u_end' => 'required|int32',
            'side' => 'string',
        ];
        
        $ret = $this->validateInput($fields);
        
        if (!$ret) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('invalid_input')
            ]);
            exit;
        }
        
        return $ret;
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $hostId = trim((string) $this->getInput('hostid', ''));
        $hostType = $this->getInput('host_type', 'zabbix') === 'custom' ? 'custom' : 'zabbix';
        $customName = trim((string) $this->getInput('custom_name', ''));
        $customIp = trim((string) $this->getInput('custom_ip', ''));
        $roomId = $this->getInput('room_id');
        $rackId = $this->getInput('rack_id');
        $uStart = $this->getInput('u_start');
        $uEnd = $this->getInput('u_end');
        $side = HostRackManager::normalizeSide($this->getInput('side', HostRackManager::SIDE_FRONT));
        
        // Get the room and rack information
        $room = RackConfig::getRoom($roomId);
        $rack = RackConfig::getRack($rackId);
        
        if (!$room || !$rack) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('rack_not_found')
            ]);
            exit;
        }

        if (!RackPermission::userCanAccessRoomId($roomId)) {
            RackPermission::denyAccessJson();
        }
        
        // Validate the U range
        if ($uStart < 1 || $uEnd < 1 || $uStart > $rack['height'] || $uEnd > $rack['height'] || $uStart > $uEnd) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('invalid_position')
            ]);
            exit;
        }
        
        if ($hostType === 'custom') {
            if ($customName === '' || $customIp === '') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => LanguageManager::t('custom_host_required')
                ]);
                exit;
            }
            if (filter_var($customIp, FILTER_VALIDATE_IP) === false) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => LanguageManager::t('invalid_custom_ip')
                ]);
                exit;
            }
        } elseif ($hostId === '') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('select_a_host')
            ]);
            exit;
        }

        // When editing a custom host, exclude itself so its original position is not seen as occupied.
        $excludeHostId = ($hostType === 'custom' && $hostId !== '')
            ? HostRackManager::CUSTOM_HOST_PREFIX . HostRackManager::getCustomHostId($hostId)
            : null;

        // Check whether the U positions are free (no overlap within the same rack side)
        if (!HostRackManager::isPositionAvailable($room['name'], $rack['name'], $uStart, $uEnd, $excludeHostId, $side)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => LanguageManager::t('position_occupied')
            ]);
            exit;
        }
        
        // Assign a Zabbix host or save a custom host.
        $success = $hostType === 'custom'
            ? HostRackManager::assignCustomHost($hostId, $customName, $customIp, $roomId, $rackId, $uStart, $uEnd, $side)
            : HostRackManager::assignHost($hostId, $room['name'], $rack['name'], $uStart, $uEnd, $side);
        
        header('Content-Type: application/json');
        $response = [
            'success' => $success,
            'message' => $success ? LanguageManager::t('assign_success') : LanguageManager::t('assign_failed'),
        ];
        if (!$success) {
            $detail = HostRackManager::getLastError();
            if ($detail !== '') {
                $response['error'] = $detail;
            }
        }
        echo json_encode($response);
        exit;
    }
}
