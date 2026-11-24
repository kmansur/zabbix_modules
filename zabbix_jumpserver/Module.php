<?php

namespace Modules\ZabbixJumpserver;

require_once __DIR__ . '/lib/ZabbixVersion.php';
require_once __DIR__ . '/lib/LanguageManager.php';

use Modules\ZabbixJumpserver\Lib\LanguageManager;
use Modules\ZabbixJumpserver\Lib\ZabbixVersion;
use CMenu;
use CMenuItem;

// Choose the base class according to the classes that actually exist
// Zabbix 7.0+ uses Zabbix\Core\CModule
// Zabbix 6.0 uses Core\CModule
if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    class ModuleBase {
        public function init(): void {}
    }
}

class Module extends ModuleBase {

    public function init(): void {
        try {
            if (class_exists('APP')) {
                $app = new \ReflectionClass('APP');

                if ($app->hasMethod('Component')) {
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Inventory'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(LanguageManager::t('JumpServer')))->setAction('jumpserver')
                        );
                }
            }
        } catch (\Exception $e) {
            error_log('Zabbix JumpServer Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
