<?php declare(strict_types = 0);
require_once dirname(__DIR__).'/lib/ModuleNotice.php';
echo \ZabbixModuleNotice::reminder(dirname(__DIR__));


use Modules\Topomap\Lib\LanguageManager;

/**
 * Page skeleton of "Monitoring → Auto Topology Map".
 *
 * The list area deliberately mirrors native "Monitoring → Maps" (filter-space + list-table + action-buttons),
 * using class names that already exist in the Zabbix themes, so appearance, light/dark themes and table styles match native.
 * All interaction is in assets/js/topomap.js, with extra styles in assets/css/topomap.css.
 *
 * Translations: static texts are translated with LanguageManager::t(); for dynamic texts an inline script injects the dictionary
 * into window.TOPOMAP_I18N, and T()/TF() in topomap.js look it up (falling back to the English source).
 *
 * @var CView $this
 * @var array $data
 */

$sysmapid = isset($data['sysmapid']) ? (string) $data['sysmapid'] : '';
$t = static function (string $s): string  { return htmlspecialchars(LanguageManager::t($s), ENT_QUOTES, 'UTF-8'); };
?>
<div id="topomap-root" data-sysmapid="<?= htmlspecialchars($sysmapid, ENT_QUOTES, 'UTF-8') ?>">

	<!-- The dictionary must be in place before topomap.js's DOMContentLoaded callback:
	     module JS loads in <head>, and this inline script in <body> runs before DOMContentLoaded. -->
	<script>
		window.TOPOMAP_LANG = <?= json_encode(LanguageManager::detectLanguage()) ?>;
		window.TOPOMAP_I18N = <?= json_encode((object) LanguageManager::jsMap(),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
	</script>

	<!-- Header: layout.htmlpage only outputs wrapper + main_block and no header, so the title and
	     button group are written here. The structure follows the native 8.0 header (header > .header-title > h1 + .header-controls);
	     the 7.0 theme has no "header .header-title" rule, so the module CSS provides the same single-line flex layout. -->
	<header>
		<div class="header-title">
			<div class="overflow-ellipsis"><h1 id="page-title-general"><?= $t('Auto topology map') ?></h1></div>
			<div class="header-controls">
				<nav aria-label="<?= $t('Content controls') ?>">
					<ul>
						<li><button type="button" id="tm-discover-btn" class="btn-alt"><?= $t('Auto discovery') ?></button></li>
						<li><button type="button" id="tm-create-btn"><?= $t('Create map') ?></button></li>
					</ul>
				</nav>
			</div>
		</div>
	</header>

	<main>
	<!-- List view -->
	<div class="tm-list-view" id="tm-list-view">

		<div class="tm-msg-line"><span class="tm-msg" id="tm-list-msg"></span></div>

		<!-- Search: same structure as native sysmap.list -->
		<div class="filter-space" id="tm-filter-space" aria-label="<?= $t('Filter') ?>">
			<ul class="filter-btn-container">
				<li><a class="btn zi-filter filter-trigger" id="tm-filter-toggle" href="#" role="button"><?= $t('Filter') ?></a></li>
			</ul>
			<form id="tm-filter-form" action="#" onsubmit="return false;">
				<div class="filter-container" id="tm-filter-container">
					<div class="table filter-forms">
						<div class="row">
							<div class="cell">
								<ul class="table-forms">
									<li>
										<div class="table-forms-td-left"><label for="tm-filter-name"><?= $t('Name') ?></label></div>
										<div class="table-forms-td-right">
											<input type="text" id="tm-filter-name" maxlength="255" autocomplete="off" style="width: 300px;">
										</div>
									</li>
								</ul>
							</div>
						</div>
					</div>
					<div class="filter-forms">
						<button type="submit" id="tm-filter-apply"><?= $t('Apply') ?></button>
						<button type="button" class="btn-alt" id="tm-filter-reset"><?= $t('Reset') ?></button>
					</div>
				</div>
			</form>
		</div>

		<form id="tm-list-form" action="#" onsubmit="return false;">
			<table class="list-table" id="tm-list-table">
				<thead>
					<tr>
						<th class="cell-width">
							<input type="checkbox" id="tm-check-all" class="checkbox-radio" autocomplete="off">
							<label for="tm-check-all"><span></span></label>
						</th>
						<th><a href="#" id="tm-sort-name"><?= $t('Name') ?><span class="arrow-up"></span></a></th>
						<th><a href="#" id="tm-sort-width"><?= $t('Width (px)') ?><span class="arrow-up"></span></a></th>
						<th><a href="#" id="tm-sort-height"><?= $t('Height (px)') ?><span class="arrow-up"></span></a></th>
						<th><?= $t('Actions') ?></th>
					</tr>
				</thead>
				<tbody id="tm-list-body">
					<tr><td colspan="5" class="tm-loading"><?= $t('Loading…') ?></td></tr>
				</tbody>
			</table>

			<div class="table-paging">
				<nav class="paging-btn-container" role="navigation" aria-label="<?= $t('Pagination') ?>">
					<div class="table-stats" id="tm-table-stats"></div>
				</nav>
			</div>

			<div class="action-buttons" id="tm-action-buttons">
				<span class="selected-item-count" id="tm-selected-count"><?= $t('0 selected') ?></span>
				<button type="button" class="btn-alt" id="tm-export-btn"><?= $t('Export') ?></button>
				<button type="button" class="btn-alt" id="tm-mass-delete" disabled><?= $t('Delete') ?></button>
			</div>
		</form>
	</div>

	<!-- Canvas view (preview / edit) -->
	<div class="tm-canvas-view" id="tm-canvas-view" hidden>
		<div class="tm-toolbar">
			<button class="tm-btn" id="tm-back-btn" type="button">← <?= $t('Back to list') ?></button>
			<span class="tm-canvas-title" id="tm-canvas-title"></span>
			<span class="tm-spacer"></span>
			<span class="tm-hint" id="tm-hint"></span>
			<span class="tm-canvas-size" id="tm-canvas-size"></span>
			<button class="tm-btn tm-btn-icon" id="tm-zoom-out" type="button" title="<?= $t('Zoom out') ?>">−</button>
			<button class="tm-btn tm-btn-zoom" id="tm-zoom-reset" type="button" title="<?= $t('Reset to 100%') ?>">100%</button>
			<button class="tm-btn tm-btn-icon" id="tm-zoom-in" type="button" title="<?= $t('Zoom in') ?>">+</button>
			<button class="tm-btn" id="tm-fit-btn" type="button" title="<?= $t('Fit to window') ?>"><?= $t('Fit') ?></button>
			<button class="tm-btn" id="tm-rediscover-btn" type="button" title="<?= $t('Rescan and update the current map in place') ?>"><?= $t('Rediscover') ?></button>
			<button class="tm-btn tm-btn-primary" id="tm-edit-btn" type="button"><?= $t('Edit map') ?></button>
			<button class="tm-btn tm-btn-primary" id="tm-save-btn" type="button" hidden><?= $t('Save') ?></button>
			<button class="tm-btn" id="tm-exit-btn" type="button" hidden><?= $t('Exit editing') ?></button>
		</div>

		<div class="tm-editbar" id="tm-editbar" hidden>
			<span class="tm-editbar-group">
				<span class="tm-editbar-label"><?= $t('Map elements:') ?></span>
				<button class="tm-btn tm-btn-sm" data-group="element" data-op="add" type="button"><?= $t('Add') ?></button>
				<button class="tm-btn tm-btn-sm" data-group="element" data-op="remove" type="button"><?= $t('Remove') ?></button>
			</span>
			<span class="tm-editbar-sep"></span>
			<span class="tm-editbar-group">
				<span class="tm-editbar-label"><?= $t('Shapes:') ?></span>
				<button class="tm-btn tm-btn-sm" data-group="shape" data-op="add" type="button"><?= $t('Add') ?></button>
				<button class="tm-btn tm-btn-sm" data-group="shape" data-op="remove" type="button"><?= $t('Remove') ?></button>
			</span>
			<span class="tm-editbar-sep"></span>
			<span class="tm-editbar-group">
				<span class="tm-editbar-label"><?= $t('Links:') ?></span>
				<button class="tm-btn tm-btn-sm" data-group="link" data-op="add" type="button"><?= $t('Add') ?></button>
				<button class="tm-btn tm-btn-sm" data-group="link" data-op="remove" type="button"><?= $t('Remove') ?></button>
			</span>
			<span class="tm-spacer"></span>
			<span class="tm-hint"><?= $t('Drag elements to move them; click an element, shape or link to edit its properties') ?></span>
		</div>

		<div class="tm-canvas-wrap" id="tm-canvas-wrap">
			<div class="tm-stage" id="tm-stage">
				<svg class="tm-svg" id="tm-svg" xmlns="http://www.w3.org/2000/svg"></svg>
			</div>
		</div>
	</div>

	</main>

	<!-- Popup containers: element properties / shape properties / link properties / host selection -->
	<div class="tm-overlay" id="tm-overlay" hidden>
		<div class="tm-dialog" id="tm-dialog" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title" id="tm-dialog-title"></span>
				<button class="tm-dialog-close" id="tm-dialog-close" type="button" title="<?= $t('Close') ?>">×</button>
			</div>
			<div class="tm-dialog-body" id="tm-dialog-body"></div>
			<div class="tm-dialog-foot" id="tm-dialog-foot"></div>
		</div>
	</div>

	<!-- Item selector: link metrics (bandwidth / upstream / downstream) are chosen from items.
	     The structure follows the native Zabbix "item" popup: host field (source host preselected) + select button
	     → host popup (host group + select) → host group popup (search + list with checkboxes). -->
	<div class="tm-overlay" id="tm-item-overlay" hidden>
		<div class="tm-dialog tm-dialog-wide" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title" id="tm-item-title"><?= $t('Item') ?></span>
				<button class="tm-dialog-close" id="tm-item-close" type="button" title="<?= $t('Close') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Host') ?></span>
					<div class="tm-pick-box" id="tm-item-hostbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-item-hostpick" type="button"><?= $t('Select') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Filter') ?></span>
					<input class="tm-input" id="tm-item-search" type="text"
						placeholder="<?= $t('Type to search') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-item-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-item-all" autocomplete="off">
									<label for="tm-item-all"><span></span></label>
								</th>
								<th><?= $t('Name') ?></th>
								<th><?= $t('Key') ?></th>
								<th><?= $t('Type') ?></th>
								<th><?= $t('Type of information') ?></th>
								<th><?= $t('Status') ?></th>
							</tr>
						</thead>
						<tbody id="tm-item-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-item-ok" type="button"><?= $t('Select') ?></button>
				<button class="tm-btn" id="tm-item-cancel" type="button"><?= $t('Cancel') ?></button>
			</div>
		</div>
	</div>

	<!-- Host popup (opened from "Host → Select" in the item popup): host group + select → host group popup; list with checkboxes -->
	<div class="tm-overlay" id="tm-hp-overlay" hidden>
		<div class="tm-dialog" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title"><?= $t('Host') ?></span>
				<button class="tm-dialog-close" id="tm-hp-close" type="button" title="<?= $t('Close') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Host group') ?></span>
					<div class="tm-pick-box" id="tm-hp-groupbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-hp-grouppick" type="button"><?= $t('Select') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Filter') ?></span>
					<input class="tm-input" id="tm-hp-search" type="text"
						placeholder="<?= $t('Type to search') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-hp-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-hp-all" autocomplete="off">
									<label for="tm-hp-all"><span></span></label>
								</th>
								<th><?= $t('Name') ?></th>
							</tr>
						</thead>
						<tbody id="tm-hp-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-hp-ok" type="button"><?= $t('Select') ?></button>
				<button class="tm-btn" id="tm-hp-cancel" type="button"><?= $t('Cancel') ?></button>
			</div>
		</div>
	</div>

	<!-- Host group popup (opened from "Host group → Select" in the host popup): search box + list with checkboxes -->
	<div class="tm-overlay" id="tm-gg-overlay" hidden>
		<div class="tm-dialog tm-dialog-narrow" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title"><?= $t('Host group') ?></span>
				<button class="tm-dialog-close" id="tm-gg-close" type="button" title="<?= $t('Close') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Filter') ?></span>
					<input class="tm-input" id="tm-gg-search" type="text"
						placeholder="<?= $t('Type to search') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-gg-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-gg-all" autocomplete="off">
									<label for="tm-gg-all"><span></span></label>
								</th>
								<th><?= $t('Name') ?></th>
							</tr>
						</thead>
						<tbody id="tm-gg-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-gg-ok" type="button"><?= $t('Select') ?></button>
				<button class="tm-btn" id="tm-gg-cancel" type="button"><?= $t('Cancel') ?></button>
			</div>
		</div>
	</div>

	<!-- Trigger selector: manual trigger binding on links (links such as a new PC ↔ switch have no LLDP port information,
	     so automatic keyword matching cannot cover them and triggers must be picked manually). The list shows the triggers of the hosts at both ends. -->
	<div class="tm-overlay" id="tm-trig-overlay" hidden>
		<div class="tm-dialog tm-dialog-wide" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title" id="tm-trig-title"><?= $t('Trigger') ?></span>
				<button class="tm-dialog-close" id="tm-trig-close" type="button" title="<?= $t('Close') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Host group') ?></span>
					<div class="tm-pick-box" id="tm-trig-groupbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-trig-grouppick" type="button"><?= $t('Select') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Host') ?></span>
					<div class="tm-pick-box" id="tm-trig-hostbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-trig-hostpick" type="button"><?= $t('Select') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('Filter') ?></span>
					<input class="tm-input" id="tm-trig-search" type="text"
						placeholder="<?= $t('Type to search') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-trig-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-trig-all" autocomplete="off">
									<label for="tm-trig-all"><span></span></label>
								</th>
								<th><?= $t('Name') ?></th>
								<th><?= $t('Severity') ?></th>
								<th><?= $t('Status') ?></th>
								<th><?= $t('Host') ?></th>
							</tr>
						</thead>
						<tbody id="tm-trig-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-trig-ok" type="button"><?= $t('Select') ?></button>
				<button class="tm-btn" id="tm-trig-cancel" type="button"><?= $t('Cancel') ?></button>
			</div>
		</div>
	</div>

	<!-- Host selector -->
	<div class="tm-overlay" id="tm-host-overlay" hidden>
		<div class="tm-dialog tm-dialog-narrow" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title"><?= $t('Select host') ?></span>
				<button class="tm-dialog-close" id="tm-host-close" type="button" title="<?= $t('Close') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<input class="tm-input tm-input-block" id="tm-host-search" type="text"
					placeholder="<?= $t('Type to search') ?>" autocomplete="off">
				<div class="tm-host-list" id="tm-host-list"></div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn" id="tm-host-cancel" type="button"><?= $t('Close') ?></button>
			</div>
		</div>
	</div>
</div>
