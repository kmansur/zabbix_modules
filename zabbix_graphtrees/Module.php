<?php

namespace Modules\ZabbixGraphTrees;

// Version compatibility helper (dynamic import)
require_once __DIR__ . '/lib/ZabbixVersion.php';
require_once __DIR__ . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\ZabbixVersion;
use Modules\ZabbixGraphTrees\Lib\LanguageManager;
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
    // Fallback: create an empty base class
    class ModuleBase {
        public function init(): void {}
    }
}

class Module extends ModuleBase {

    public function init(): void {
        // Support the menu registration methods of different versions
        try {
            // Try the APP class (supported by both Zabbix 6 and 7)
            if (class_exists('APP')) {
                $app = class_exists('APP') ? new \ReflectionClass('APP') : null;
                
                if ($app && $app->hasMethod('Component')) {
                    // Zabbix 7.0+ way
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Monitoring'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(LanguageManager::t('Graph Trees')))
                                ->setAction('graphtrees')
                        );
                }
            }
        } catch (\Exception $e) {
            // Log the error without interrupting execution
            error_log('Graph Trees Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
