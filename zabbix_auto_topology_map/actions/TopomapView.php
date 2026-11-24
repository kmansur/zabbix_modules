<?php declare(strict_types = 1);

namespace Modules\Topomap\Actions;

use API;
use CController;
use CControllerResponseData;
use CProfile;
use Modules\Topomap\Lib\LanguageManager;

/**
 * "Monitoring → Auto Topology Map" page.
 * The page itself only outputs the skeleton; all data and interaction live in assets/js/topomap.js,
 * and the backend endpoints are in TopomapData (zabbix.php?action=topomap.data).
 *
 * View memory: copied exactly from native map.view —
 *   native CControllerMapView reads CProfile::get('web.maps.sysmapid') when no sysmapid is given,
 *   and the list page sysmaps.php calls CProfile::delete('web.maps.sysmapid') when opened.
 *   So "leave the map canvas and click the menu again → same map; leave the list and click again → the list"
 *   is really "the user profile stores the last viewed map id and clears it on returning to the list".
 *   This module is a single-page app (list and canvas share one action), so the cleanup on "back to list" is done by the frontend
 *   calling topomap.data&mode=remember; reads and writes use the same profile key.
 */
class TopomapView extends CController {

	/** Profile key that stores the last viewed map, with the same structure as native web.maps.sysmapid. */
	public const PROFILE_KEY = 'web.topomap.sysmapid';

	public function init(): void {
		// In 7.0, CController::run() treats GET requests to controllers that keep CSRF validation enabled as
		// failed validation and denies access, so read-only pages must disable it. The method name differs by version, so each is tried.
		foreach (['disableCsrfValidation', 'disableSIDValidation', 'disableSIDvalidation'] as $method) {
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
	}

	protected function checkInput(): bool {
		$this->validateInput([
			'sysmapid' => 'string'
		]);

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$sysmapid = trim((string) $this->getInput('sysmapid', ''));

		// Entering from the menu carries no sysmapid (the menu item action is fixed to topomap.view);
		// in that case the map remembered in the profile is used, matching native behavior.
		if ($sysmapid === '') {
			$sysmapid = $this->rememberedMapId();
		}

		$response = new CControllerResponseData([
			'main_menu' => '',
			'sub_menu' => '',
			'sysmapid' => $sysmapid
		]);

		// 7.0 only honors setTitle(); a title passed in the constructor parameters is ignored,
		// and the browser tab would show only the site name. setTitle must be called explicitly.
		$response->setTitle(LanguageManager::t('Auto topology map'));

		$this->setResponse($response);
	}

	/**
	 * Get the id of the last viewed map; an empty string if it was deleted or nothing is stored (the frontend then shows the list).
	 */
	private function rememberedMapId(): string {
		try {
			$sysmapid = (int) CProfile::get(self::PROFILE_KEY, 0);

			if ($sysmapid <= 0) {
				return '';
			}

			$maps = API::Map()->get([
				'output' => ['sysmapid'],
				'sysmapids' => [$sysmapid]
			]);

			if ($maps) {
				return (string) $sysmapid;
			}

			// The map was deleted: clear the stale record so the menu does not query it in vain every time.
			CProfile::delete(self::PROFILE_KEY);
		}
		catch (\Throwable $e) {
			// If it cannot be read, treat it as no memory; the page must never fail with 500 because of it.
		}

		return '';
	}
}
