<?php declare(strict_types = 1);

namespace Modules\Topomap;

use Modules\Topomap\Lib\LanguageManager;

if (class_exists('Zabbix\\Core\\CModule')) {
	class Module extends \Zabbix\Core\CModule {
		public function init(): void {
			$this->registerMenu();
		}

		/**
		 * Registers the "Auto Topology Map" entry under Monitoring.
		 * Wrapped in try/catch: the menu component signature differs slightly between versions,
		 * so a failure only loses the menu instead of blanking every frontend page.
		 */
		private function registerMenu(): void {
			try {
				// Note: it must be \APP, not APP — inside the namespace APP resolves to
				// Modules\Topomap\APP; the Zabbix autoloader cannot find it and throws,
				// PHP's global class fallback does not happen, and the menu silently disappears.
				$submenu = \APP::Component()->get('menu.main')
					->findOrAdd(_('Monitoring'))
					->getSubmenu();

				if (method_exists($submenu, 'find') && $submenu->find(LanguageManager::t('Auto topology map')) !== null) {
					return;
				}

				$item = (new \CMenuItem(LanguageManager::t('Auto topology map')))->setAction('topomap.view');

				if (method_exists($submenu, 'insertAfter')) {
					$submenu->insertAfter(_('Maps'), $item);
				}
				else {
					$submenu->add($item);
				}
			}
			catch (\Throwable $e) {
				// A menu registration failure does not affect the module page (still reachable at zabbix.php?action=topomap.view)
			}
		}
	}
}
else {
	class Module extends \Core\CModule {
		public function init(): void {
			$this->registerMenu();
		}

		private function registerMenu(): void {
			try {
				// Note: it must be \APP, not APP — inside the namespace APP resolves to
				// Modules\Topomap\APP; the Zabbix autoloader cannot find it and throws,
				// PHP's global class fallback does not happen, and the menu silently disappears.
				$submenu = \APP::Component()->get('menu.main')
					->findOrAdd(_('Monitoring'))
					->getSubmenu();

				if (method_exists($submenu, 'find') && $submenu->find(LanguageManager::t('Auto topology map')) !== null) {
					return;
				}

				$item = (new \CMenuItem(LanguageManager::t('Auto topology map')))->setAction('topomap.view');

				if (method_exists($submenu, 'insertAfter')) {
					$submenu->insertAfter(_('Maps'), $item);
				}
				else {
					$submenu->add($item);
				}
			}
			catch (\Throwable $e) {
			}
		}
	}
}
