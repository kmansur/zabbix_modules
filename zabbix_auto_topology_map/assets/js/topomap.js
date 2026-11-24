/*
 * Auto Topology Map (Zabbix frontend module topomap)
 *
 * The page skeleton is in views/topomap.view.php and the data endpoint in actions/TopomapData.php.
 * The data itself lives in native Zabbix sysmaps (map.create / map.update),
 * so this reads and writes the same data as "Monitoring → Maps".
 *
 * Note: Zabbix injects module JS into <head> as a <script> without defer, before <body> is parsed,
 * so all initialization must wait for DOMContentLoaded, otherwise getElementById always returns null.
 */
(function () {
	'use strict';

	// ---------------------------------------------------------------
	// The translation dictionary is injected by LanguageManager::jsMap() on the PHP side
	// (window.TOPOMAP_I18N). In English the dictionary is empty and T() returns the source text;
	// in Portuguese, missing translations also fall back to the source text, so there is always some text.
	// ---------------------------------------------------------------

	// Note: the dictionary is injected by an inline script in the page skeleton, and this file runs first in <head>,
	// so window.TOPOMAP_I18N must be read on every call and cannot be cached at load time.
	function T(s) {
		var dict = window.TOPOMAP_I18N;

		if (!dict) {
			return s;
		}

		return Object.prototype.hasOwnProperty.call(dict, s) ? dict[s] : s;
	}

	// Translation with placeholders: TF('%s topology maps in total', n). %s / %d are replaced in order.
	function TF(s) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;

		return T(s).replace(/%s|%d/g, function () {
			return i < args.length ? args[i++] : '';
		});
	}

	function locale() {
		return (window.TOPOMAP_LANG || 'en_US').replace('_', '-');
	}


    function saveError(message) {
        var note = document.querySelector(".module-save-hint");
        return String(message) + (note && String(message).indexOf("getenforce") < 0 ? "\n" + note.getAttribute("data-save-hint") : "");
    }

	function start() {
		var root = document.getElementById('topomap-root');

		if (!root) {
			return;
		}

		// ---------------------------------------------------------------
		// Constants / basics
		// ---------------------------------------------------------------

		var ET_HOST = 0;
		var ET_MAP = 1;
		var ET_TRIGGER = 2;
		var ET_GROUP = 3;
		var ET_IMAGE = 4;

		// Values of sysmaps.label_type (same as CMap.LABEL_TYPE_* in 7.0.28 ui/js/class.cmap.js)
		var LT_LABEL = 0;    // The element's own label text
		var LT_IP = 1;       // Host IP
		var LT_NAME = 2;     // Element name (host name)
		var LT_STATUS = 3;   // Status only
		var LT_NOTHING = 4;  // No label
		var LT_CUSTOM = 5;   // Uses the sysmaps.label_string_* macro templates (the preview falls back to the label text)

		// Fallback colors for status lines (the backend sends the real colors configured in the sysmap)
		var STATUS_COLORS = {
			ok: '009900',
			ok_ack: '009900',
			problem: 'E45959',
			problem_ack: 'E97659',
			disabled: '960000',
			maintenance: 'EE9600'
		};

		var LABEL_LOCS = [[-1, T('Default')], [0, T('Bottom')], [1, T('Left')], [2, T('Right')], [3, T('Top')]];
		var ICON_LOCS = [[0, T('Default')], [1, T('Bottom')], [2, T('Left')], [3, T('Right')], [4, T('Top')]];
		var TAG_OPERATORS = [[0, T('Contains')], [1, T('Equals')], [2, T('Does not contain')], [3, T('Does not equal')], [4, T('Exists')], [5, T('Does not exist')]];
		var LINK_DRAWTYPES = [[0, T('Solid line')], [2, T('Thick line')], [3, T('Dotted line')], [4, T('Dashed line')]];
		// Per-link routing mode: 0 automatic curve (current) / 1 automatic orthogonal / 2 manual waypoints (picked and dragged on the canvas)
		var LINK_ROUTINGS = [[0, T('Auto curve')], [1, T('Auto orthogonal')], [2, T('Manual waypoints')]];
		// Per-link "trigger source" scope, matching the backend TopomapData::TRIGGER_SOURCE_* one to one.
		// Link direction = selementid1 (source) → selementid2 (local).
		var TRIGGER_SOURCES = [
			[0, T('Auto (source first)')],
			[1, T('Source only')],
			[2, T('Target only')],
			[3, T('Both ends')],
			[4, T('None')]
		];
		// Shape types as in native SVGMapShape: 0 rectangle / 1 ellipse / 2 line.
		// For lines x, y is the start and width, height are the absolute end coordinates (not width/height), as in native sysmap_shape.
		var SHAPE_TYPES = [[0, T('Rectangle')], [1, T('Ellipse')], [2, T('Line')]];
		var SHAPE_BORDERS = [[0, T('None')], [1, T('Solid line')], [2, T('Dotted line')], [3, T('Dashed line')]];
		// Fonts: the index is sysmap_shape.font, names match the dropdown of the native shapeForm;
		// the family actually drawn follows native SVGMap.FONTS (natively 9 shows Helvetica but the SVG draws Trebuchet MS);
		// the native rendering is copied as is, so it looks exactly like the built-in maps.
		var SHAPE_FONT_NAMES = ['Georgia', 'Palatino', 'Times New Roman', 'Arial', 'Arial Black', 'Comic Sans',
			'Impact', 'Lucida Sans', 'Tahoma', 'Helvetica', 'Verdana', 'Courier New', 'Lucida Console'];
		var SHAPE_FONT_FAMILIES = ['Georgia, serif', '"Palatino Linotype", "Book Antiqua", Palatino, serif',
			'"Times New Roman", Times, serif', 'Arial, Helvetica, sans-serif', '"Arial Black", Gadget, sans-serif',
			'"Comic Sans MS", cursive, sans-serif', 'Impact, Charcoal, sans-serif',
			'"Lucida Sans Unicode", "Lucida Grande", sans-serif', 'Tahoma, Geneva, sans-serif',
			'"Trebuchet MS", Helvetica, sans-serif', 'Verdana, Geneva, sans-serif',
			'"Courier New", Courier, monospace', '"Lucida Console", Monaco, monospace'];
		// Native SVGMap.BORDER_TYPES: dash arrays for dotted/dashed lines scale with the line width
		var SHAPE_BORDER_DASHES = ['', 'none', '1,2', '4,4'];
		var SHAPE_TEXT_PADDING = 5;
		// Selection handles: 7×7 circles, white fill, 1px dark blue stroke (aligned with native .ui-resize-dot)
		var SHAPE_HANDLE_RADIUS = 3.5;
		var SEVERITY_COLORS = ['#97AAB3', '#7499FF', '#FFC859', '#FFA059', '#E97659', '#E45959'];
		var DEFAULT_LINK_COLOR = '00CC00';
		// Link color when a bound trigger is in problem state (links created by auto discovery bind port status triggers)
		var DEFAULT_TRIGGER_COLOR = 'DD0000';
		// Vertical spacing (pixels) between adjacent links when several links join the same pair of elements (members of a link aggregation).
		// They are drawn as symmetrically offset quadratic Bézier curves so they do not overlap completely. Values aligned with aiops: links stay close in a bundle,
		// and each link's metric badge is shifted along the curve (so boxes of adjacent links do not overlap).
		var LINK_PAIR_SPACING = 12;
		// The auto discovery depth is configurable (1–10 levels, default 3); it used to be hard-coded as 1–4
		var DISCOVER_DEPTH_MIN = 1;
		var DISCOVER_DEPTH_MAX = 10;
		var DISCOVER_DEPTH_DEFAULT = 3;

		// The canvas size is not fixed: at least the visible area, growing in real time when dragging/adding beyond the right or bottom edge,
		// and shrinking on save to the larger of the content bounding box and the visible area.
		// These two minimums are only fallbacks before the canvas has been laid out.
		var MIN_CANVAS_W = 1000;
		var MIN_CANVAS_H = 700;
		var CANVAS_PAD = 60;   // Padding around the content
		var CANVAS_MAX = 60000; // Upper limit of sysmaps.width/height (60000 verified with the 8.0 API, same as the backend)
		var DRAG_EDGE = 56;    // Distance in pixels from the canvas edge at which dragging starts auto-panning
		var PAN_STEP = 16;     // Pixels per auto-pan step (screen coordinates)

		var theme = (document.documentElement.getAttribute('theme') || document.body.className || '').toLowerCase();
		var isDark = theme.indexOf('dark') !== -1;
		root.classList.add(isDark ? 'tm-dark' : 'tm-light');

		// Derive the directory of zabbix.php / imgstore.php from this script's URL, supporting subdirectory deployments
		var scriptSrc = document.currentScript && document.currentScript.src ? String(document.currentScript.src) : '';
		scriptSrc = scriptSrc.split('?')[0].split('#')[0];

		var baseDir = '';

		if (scriptSrc) {
			var idx = scriptSrc.indexOf('modules/');
			baseDir = idx > 0 ? scriptSrc.slice(0, idx) : '';
		}

		var API_URL = baseDir + 'zabbix.php?action=topomap.data';
		var IMG_URL = baseDir + 'imgstore.php?iconid=';

		var state = {
			maps: [],
			icons: [],
			defaultIcon: '0',
			canEdit: false,
			map: null,
			editing: false,
			dirty: false,
			removeMode: null,
			linkMode: false,
			linkFirst: null,
			// Link (representing its whole group) whose waypoints are being edited: when set, clicking empty canvas adds a waypoint and waypoint dots are shown
			bendLink: null,
			// Currently selected shape (as in native: one click shows 8 circular handles; dragging a handle resizes)
			shapeSel: null,
			selected: null,
			zoom: 1,
			panX: 0,
			panY: 0,
			tmpSeq: 0,
			iconSize: {},
			hostPick: null,
			itemPick: null,
			trigPick: null,
			// Name cache of bound triggers (triggerid => description), used by chips on the canvas/in dialogs
			triggerNames: {},
			// Whether a trigger is firing (triggerid => true): lets a just-bound trigger color the link immediately
			triggerProblems: {},
			// Trigger severity (triggerid => priority 0–5): used by the "indicator" swatch in link properties
			triggerSeverity: {},
			// List: filter / sort / select (aligned with the interactions of the native map list)
			filterName: '',
			sortField: 'name',
			sortOrder: 'ASC',
			checked: {}
		};

		// ---------------------------------------------------------------
		// DOM
		// ---------------------------------------------------------------

		var elListView = document.getElementById('tm-list-view');
		var elCanvasView = document.getElementById('tm-canvas-view');
		var elCreateBtn = document.getElementById('tm-create-btn');
		var elDiscoverBtn = document.getElementById('tm-discover-btn');
		// "Create map" belongs only to the list page; hide it in the canvas view
		var elHeaderControls = document.querySelector('#topomap-root .header-controls');
		var elListMsg = document.getElementById('tm-list-msg');
		var elListBody = document.getElementById('tm-list-body');
		var elListForm = document.getElementById('tm-list-form');

		var elFilterToggle = document.getElementById('tm-filter-toggle');
		var elFilterContainer = document.getElementById('tm-filter-container');
		var elFilterName = document.getElementById('tm-filter-name');
		var elCheckAll = document.getElementById('tm-check-all');
		var elSortName = document.getElementById('tm-sort-name');
		var elSortWidth = document.getElementById('tm-sort-width');
		var elSortHeight = document.getElementById('tm-sort-height');
		var elTableStats = document.getElementById('tm-table-stats');
		var elSelectedCount = document.getElementById('tm-selected-count');
		var elMassDelete = document.getElementById('tm-mass-delete');
		var elExportBtn = document.getElementById('tm-export-btn');

		var elCanvasTitle = document.getElementById('tm-canvas-title');
		var elCanvasSize = document.getElementById('tm-canvas-size');
		var elHint = document.getElementById('tm-hint');
		var elBackBtn = document.getElementById('tm-back-btn');
		var elZoomIn = document.getElementById('tm-zoom-in');
		var elZoomOut = document.getElementById('tm-zoom-out');
		var elZoomReset = document.getElementById('tm-zoom-reset');
		var elFitBtn = document.getElementById('tm-fit-btn');
		var elEditBtn = document.getElementById('tm-edit-btn');
		var elRediscoverBtn = document.getElementById('tm-rediscover-btn');
		var elSaveBtn = document.getElementById('tm-save-btn');
		var elExitBtn = document.getElementById('tm-exit-btn');
		var elEditbar = document.getElementById('tm-editbar');
		var elWrap = document.getElementById('tm-canvas-wrap');
		var elStage = document.getElementById('tm-stage');
		var elSvg = document.getElementById('tm-svg');

		var elOverlay = document.getElementById('tm-overlay');
		var elDialog = document.getElementById('tm-dialog');
		var elDialogTitle = document.getElementById('tm-dialog-title');
		var elDialogBody = document.getElementById('tm-dialog-body');
		var elDialogFoot = document.getElementById('tm-dialog-foot');
		var elDialogClose = document.getElementById('tm-dialog-close');

		var elHostOverlay = document.getElementById('tm-host-overlay');
		var elHostSearch = document.getElementById('tm-host-search');
		var elHostList = document.getElementById('tm-host-list');

		var elItemOverlay = document.getElementById('tm-item-overlay');
		var elItemTitle = document.getElementById('tm-item-title');
		var elItemSearch = document.getElementById('tm-item-search');
		var elItemList = document.getElementById('tm-item-list');
		var elItemHostBox = document.getElementById('tm-item-hostbox');
		var elItemHostPick = document.getElementById('tm-item-hostpick');
		var elItemAll = document.getElementById('tm-item-all');
		var elItemOk = document.getElementById('tm-item-ok');
		var elItemCancel = document.getElementById('tm-item-cancel');
		var elItemClose = document.getElementById('tm-item-close');

		// Host popup (opened via "Host → Select" in the item popup)
		var elHpOverlay = document.getElementById('tm-hp-overlay');
		var elHpGroupBox = document.getElementById('tm-hp-groupbox');
		var elHpGroupPick = document.getElementById('tm-hp-grouppick');
		var elHpSearch = document.getElementById('tm-hp-search');
		var elHpList = document.getElementById('tm-hp-list');
		var elHpAll = document.getElementById('tm-hp-all');
		var elHpOk = document.getElementById('tm-hp-ok');
		var elHpCancel = document.getElementById('tm-hp-cancel');
		var elHpClose = document.getElementById('tm-hp-close');

		// Host group popup (opened via "Host group → Select" in the host popup)
		var elGgOverlay = document.getElementById('tm-gg-overlay');
		var elGgSearch = document.getElementById('tm-gg-search');
		var elGgList = document.getElementById('tm-gg-list');
		var elGgAll = document.getElementById('tm-gg-all');
		var elGgOk = document.getElementById('tm-gg-ok');
		var elGgCancel = document.getElementById('tm-gg-cancel');
		var elGgClose = document.getElementById('tm-gg-close');

		// Trigger popup (opened via "Trigger → Select" in link properties)
		var elTrigOverlay = document.getElementById('tm-trig-overlay');
		var elTrigGroupBox = document.getElementById('tm-trig-groupbox');
		var elTrigGroupPick = document.getElementById('tm-trig-grouppick');
		var elTrigHostBox = document.getElementById('tm-trig-hostbox');
		var elTrigHostPick = document.getElementById('tm-trig-hostpick');
		var elTrigSearch = document.getElementById('tm-trig-search');
		var elTrigList = document.getElementById('tm-trig-list');
		var elTrigAll = document.getElementById('tm-trig-all');
		var elTrigOk = document.getElementById('tm-trig-ok');
		var elTrigCancel = document.getElementById('tm-trig-cancel');
		var elTrigClose = document.getElementById('tm-trig-close');

		// ---------------------------------------------------------------
		// Utility functions
		// ---------------------------------------------------------------

		function clear(node) {
			while (node && node.firstChild) {
				node.removeChild(node.firstChild);
			}
		}

		function num(value, fallback) {
			var n = parseInt(value, 10);

			return isNaN(n) ? fallback : n;
		}

		function clamp(value, min, max) {
			return value < min ? min : (value > max ? max : value);
		}

		function copy(obj) {
			return JSON.parse(JSON.stringify(obj));
		}

		function make(tag, className, text) {
			var node = document.createElement(tag);

			if (className) {
				node.className = className;
			}

			if (text !== undefined && text !== null) {
				node.textContent = String(text);
			}

			return node;
		}

		function makeSelect(options, value, extraClass) {
			var sel = make('select', extraClass || null);

			options.forEach(function (pair) {
				var opt = make('option', null, pair[1]);
				opt.value = String(pair[0]);
				sel.appendChild(opt);
			});

			sel.value = String(value);

			return sel;
		}

		function msg(node, text, kind) {
			if (!node) {
				return;
			}

			node.textContent = text || '';
			node.className = 'tm-msg' + (kind === 'error' ? ' tm-msg-error' : (kind === 'ok' ? ' tm-msg-ok' : ''));
		}

		function setHint(text) {
			elHint.textContent = text || '';
		}

		/*
		 * Show/hide consistently: set both the hidden attribute and inline display.
		 *
		 * The hidden attribute alone is unreliable in real browsers: the author style `.tm-overlay{display:flex}`
		 * overrides the user-agent style `[hidden]{display:none}` (author > UA), so
		 * `el.hidden = true` only adds an attribute with no visual change — the popup cannot be closed.
		 * Inline styles beat any author style selector and are not affected by a stale cached CSS file,
		 * so both are used. When showing again, the inline value is cleared and control returns to the stylesheet.
		 */
		function setHidden(el, hide) {
			if (!el) {
				return;
			}

			el.hidden = !!hide;
			el.style.display = hide ? 'none' : '';
		}

		/**
		 * Add a frontend timeout to API calls: fetch itself has no timeout parameter, so a stuck backend can only be raced,
		 * otherwise the discovery dialog would stay at "Scanning xxx …" forever.
		 */
		function withTimeout(promise, ms) {
			return new Promise(function (resolve, reject) {
				var timer = setTimeout(function () {
					reject(new Error(T('Scan timed out (') + Math.round(ms / 1000) + T('s without response); skipped this batch')));
				}, ms);

				promise.then(function (value) {
					clearTimeout(timer);
					resolve(value);
				}, function (err) {
					clearTimeout(timer);
					reject(err);
				});
			});
		}

		// Common API call: text() first, then JSON.parse, distinguishing HTTP errors / empty responses / non-JSON
		function api(mode, params) {
			var body = new URLSearchParams();

			body.set('mode', mode);

			Object.keys(params || {}).forEach(function (key) {
				body.set(key, params[key] === null || params[key] === undefined ? '' : String(params[key]));
			});

			return fetch(API_URL, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function (res) {
				return res.text().then(function (text) {
					if (!res.ok) {
						throw new Error(T('API returned HTTP ') + res.status + ': ' + text.slice(0, 150));
					}

					if (!text) {
						throw new Error(T('API returned an empty response (HTTP ') + res.status + ')');
					}

					var data;

					try {
						data = JSON.parse(text);
					}
					catch (e) {
						throw new Error(T('API returned non-JSON data: ') + text.slice(0, 150));
					}

					if (!data || data.success !== true) {
						throw new Error(data && data.error ? data.error : T('API request failed'));
					}

					return data;
				});
			});
		}

		// ---------------------------------------------------------------
		// Preview mode: clicking a host icon opens the native host menu
		// ---------------------------------------------------------------

		// The menu data is generated by Zabbix's own menu.popup controller (view/configuration/links/scripts sections,
		// including permission checks, URLs, scripts and "View Neighbors (LLDP/CDP)" injected by third-party modules),
		// and is rendered with native getMenuPopupHost + $.fn.menuPopup, so it looks like the built-in maps.
		function openHostMenu(se, node, event) {
			if (se.elementtype !== ET_HOST || !se._hostid) {
				return;
			}

			event.stopPropagation();
			event.preventDefault();

			var body = new URLSearchParams();

			body.set('type', 'host');
			body.set('data[hostid]', String(se._hostid));

			fetch('zabbix.php?action=menu.popup', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function (res) {
				return res.text();
			}).then(function (text) {
				var resp;

				try {
					resp = JSON.parse(text);
				}
				catch (e) {
					throw new Error(T('The menu API returned non-JSON data'));
				}

				if (!resp || !resp.data) {
					throw new Error(resp && resp.error ? resp.error : T('Menu data is empty'));
				}

				showHostMenu(se, node, event, resp.data);
			}).catch(function (err) {
				openNoticeDialog(T('Cannot open the host menu'), err && err.message ? err.message : T('Request failed'));
			});
		}

		function showHostMenu(se, node, event, menuData) {
			var $ = window.jQuery;

			if (!$ || !$.fn || typeof $.fn.menuPopup !== 'function' || typeof getMenuPopupHost !== 'function') {
				return;
			}

			var $el = $(node);
			var hostid = String(se._hostid);
			var sections = getMenuPopupHost(menuData, $el);

			// The native map page has view.editHost to open the host edit popup; the module page does not,
			// so Zabbix's PopUp is called directly (the same popup.host.edit page)
			sections.forEach(function (section) {
				(section.items || []).forEach(function (item) {
					if (item.label !== '主机' && item.label !== 'Host') {
						return;
					}

					item.disabled = !menuData.isWriteable;
					item.clickCallback = function () {
						if (typeof PopUp === 'function') {
							PopUp('popup.host.edit', {hostid: hostid}, {
								dialogueid: 'host_edit',
								dialogue_class: 'modal-popup-large',
								prevent_navigation: true,
								trigger_element: node
							});
						}
						else {
							window.location.href = 'zabbix.php?action=host.edit&hostid=' + encodeURIComponent(hostid);
						}
					};
				});
			});

			// menuPopup opens at the click position by default and needs a jQuery event (native event.originalEvent)
			var jqEvent = $.Event(event);

			jqEvent.pageX = event.pageX;
			jqEvent.pageY = event.pageY;

			$el.menuPopup(sections, jqEvent, {});
		}

		// ---------------------------------------------------------------
		// Icons
		// ---------------------------------------------------------------

		function iconUrl(iconid) {
			// The offline demo page attaches icons as data URIs to window.TOPOMAP_ICON_DATA;
			// in a real environment that variable does not exist and imgstore.php is used directly.
			if (window.TOPOMAP_ICON_DATA && window.TOPOMAP_ICON_DATA[iconid]) {
				return window.TOPOMAP_ICON_DATA[iconid];
			}

			return IMG_URL + encodeURIComponent(iconid);
		}

		function iconSize(iconid) {
			return state.iconSize[iconid] || {w: 48, h: 48};
		}

		function preloadIcons(ids) {
			var tasks = [];

			ids.forEach(function (iconid) {
				if (!iconid || iconid === '0' || state.iconSize[iconid]) {
					return;
				}

				tasks.push(new Promise(function (resolve) {
					var img = new Image();

					img.onload = function () {
						state.iconSize[iconid] = {
							w: img.naturalWidth || 48,
							h: img.naturalHeight || 48
						};
						resolve();
					};

					img.onerror = function () {
						state.iconSize[iconid] = {w: 48, h: 48};
						resolve();
					};

					img.src = iconUrl(iconid);
				}));
			});

			return Promise.all(tasks);
		}

		function iconForElement(se) {
			if (se.elementtype === ET_HOST) {
				if (se._status === 1 && se.iconid_disabled !== '0') {
					return se.iconid_disabled;
				}

				if (se._problems > 0 && se.iconid_on !== '0') {
					return se.iconid_on;
				}

				// In maintenance (and no problems): the native map uses the "maintenance" icon
				if (se._maintenance === 1 && se.iconid_maintenance !== '0') {
					return se.iconid_maintenance;
				}
			}

			return se.iconid_off;
		}

		/*
		 * Highlight shape behind the icon, aligned with native getMapHighligts() + SVGMapElement.updateHighlight():
		 *   problem → circle in the severity color (acknowledged problems get a 4px green stroke)
		 *   maintenance → orange square (50% transparent)
		 *   disabled → gray square (50% transparent)
		 * Drawn only when the map's highlight switch is odd (native SYSMAP_HIGHLIGHT_ON=1).
		 */
		function highlightForElement(se) {
			var map = state.map;

			if (!map || num(map.highlight, 0) % 2 !== 1) {
				return null;
			}

			if (se.elementtype !== ET_HOST) {
				return null;
			}

			if (se._status === 1) {
				return {st: '999999'};
			}

			if (se._problems > 0) {
				var sev = num(se._severity, 0);
				var color = SEVERITY_COLORS[sev >= 0 && sev < SEVERITY_COLORS.length ? sev : 0];

				return {hl: String(color).replace('#', ''), ack: num(se._unack, 0) === 0};
			}

			if (se._maintenance === 1) {
				return {st: 'FF9933'};
			}

			return null;
		}

		// ---------------------------------------------------------------
		// Data normalization
		// ---------------------------------------------------------------

		// Which label_type an element type uses: with "advanced labels" (label_format=1) it depends on the element type,
		// otherwise the global label_type is used — same as updateLabel() in 7.0.28 class.cmap.js.
		function labelTypeFor(map, elementtype) {
			var type = map.label_type;

			if (map.label_format == 1) {
				switch (elementtype) {
					case ET_HOST:
						type = map.label_type_host;
						break;

					case ET_MAP:
						type = map.label_type_map;
						break;

					case ET_TRIGGER:
						type = map.label_type_trigger;
						break;

					case ET_GROUP:
						type = map.label_type_hostgroup;
						break;

					case ET_IMAGE:
						type = map.label_type_image;
						break;
				}
			}

			if (type === undefined || type === null || type < 0 || isNaN(type)) {
				type = map.label_type;
			}

			return num(type, LT_LABEL);
		}

		function statusColor(name) {
			var map = state.map;
			var val = map && map.colors ? map.colors[name] : '';

			return String(val || STATUS_COLORS[name] || '000000').replace('#', '');
		}

		function normalizeMap(raw) {
			var map = raw;

			map.width = num(map.width, 1000);
			map.height = num(map.height, 700);
			map.label_location = num(map.label_location, 0);
			map.selements = map.selements || [];
			map.links = map.links || [];
			map.shapes = map.shapes || [];
			map.hosts = map.hosts || {};
			map.colors = map.colors || {};
			// Link metrics: per-link item IDs (module table) and the latest-value metadata of those items
			map.metric_items = map.metric_items || {};

			// Label settings: label_type_* per element type only when label_format=1, otherwise the global label_type
			map.label_format = num(map.label_format, 0);
			map.label_type = num(map.label_type, 0);
			map.label_type_host = num(map.label_type_host, -1);
			map.label_type_hostgroup = num(map.label_type_hostgroup, -1);
			map.label_type_trigger = num(map.label_type_trigger, -1);
			map.label_type_map = num(map.label_type_map, -1);
			map.label_type_image = num(map.label_type_image, -1);
			map.show_unack = num(map.show_unack, 0);
			map.expandproblem = num(map.expandproblem, 0);
			// Element highlight (native "highlight" switch): the severity circle / maintenance square behind the icon only appears when on
			map.highlight = num(map.highlight, 0);

			map.selements.forEach(function (se) {
				se.elementtype = num(se.elementtype, ET_IMAGE);
				se.x = num(se.x, 0);
				se.y = num(se.y, 0);
				se.label_location = num(se.label_location, -1);
				se.use_iconmap = num(se.use_iconmap, 0);
				se.evaltype = num(se.evaltype, 0);
				se.iconid_off = String(se.iconid_off || '0');
				se.iconid_on = String(se.iconid_on || '0');
				se.iconid_disabled = String(se.iconid_disabled || '0');
				se.iconid_maintenance = String(se.iconid_maintenance || '0');
				se.label = se.label || '';
				se.tags = se.tags || [];
				se.urls = se.urls || [];
				se.elements = se.elements || [];

				var hostid = '';

				if (se.elementtype === ET_HOST && se.elements.length && se.elements[0].hostid) {
					hostid = String(se.elements[0].hostid);
				}

				var host = hostid && map.hosts[hostid] ? map.hosts[hostid] : null;

				se._hostid = hostid;
				se._hostName = host ? host.name : '';
				se._ip = host ? String(host.ip || '') : '';
				// Connection address for {HOST.CONN}: computed by the backend from the IP or DNS of the main/SNMP interface;
				// old data without a conn field falls back to ip for compatibility.
				se._conn = host ? String(host.conn || host.ip || '') : '';
				se._status = host ? num(host.status, 0) : 0;
				se._maintenance = host ? num(host.maintenance, 0) : 0;
				se._problems = host ? num(host.problems, 0) : 0;
				se._unack = host ? num(host.unack, 0) : 0;
				se._severity = host ? num(host.severity, -1) : -1;
				se._title = host ? String(host.title || '') : '';
				se._labelType = labelTypeFor(map, se.elementtype);
			});

			map.links.forEach(function (link) {
				link.drawtype = num(link.drawtype, 0);
				link.color = link.color || DEFAULT_LINK_COLOR;
				link.label = link.label || '';
				// "Show label" in the native link properties: default -1 / always 1 / auto-hide 0
				link.show_label = num(link.show_label, -1);
				link.linktriggers = link.linktriggers || [];
				link.metrics = normalizeMetrics(link.metrics);
				// End port names and "trigger source" scope: stored by the backend in the module table and returned with the links
				link.source_port = String(link.source_port || '');
				link.target_port = String(link.target_port || '');
				link.trigger_source = num(link.trigger_source, 0);
				// Routing mode and manual waypoints (backend table topomap_link_paths).
				// Auto-discovered links (end port names written at discovery) default to "automatic orthogonal",
				// branching from below the parent in the tree layout; manual links still default to "automatic curve".
				var autoRouting = (link.source_port || link.target_port) ? 1 : 0;
				link.routing = Math.max(0, Math.min(2, num(link.routing, autoRouting)));
				link.waypoints = normalizeWaypointList(link.waypoints);
			});

			map.shapes.forEach(function (shape) {
				// Defaults match the native Shape constructor: starting at 10,10, 50×50, Helvetica 11, 2px black border
				shape.type = Math.max(0, Math.min(2, num(shape.type, 0)));
				shape.x = num(shape.x, 10);
				shape.y = num(shape.y, 10);
				shape.width = num(shape.width, 50);
				shape.height = num(shape.height, 50);
				shape.text = shape.text || '';
				shape.font = Math.max(0, Math.min(SHAPE_FONT_NAMES.length - 1, num(shape.font, 9)));
				shape.font_size = num(shape.font_size, 11);
				shape.font_color = shape.font_color || '000000';
				shape.text_halign = num(shape.text_halign, 0);
				shape.text_valign = num(shape.text_valign, 0);
				shape.border_type = Math.max(0, Math.min(3, num(shape.border_type, 1)));
				shape.border_width = num(shape.border_width, 2);
				shape.border_color = shape.border_color || '000000';
				shape.background_color = shape.background_color || '';
				shape.zindex = num(shape.zindex, 0);
			});

			return map;
		}

		// ---------------------------------------------------------------
		// List
		// ---------------------------------------------------------------

		function loadIcons() {
			return api('icons').then(function (data) {
				state.icons = data.icons || [];
				state.defaultIcon = String(data.default || '0');
			}).catch(function () {
				state.icons = [];
			});
		}

		function loadList() {
			msg(elListMsg, T('Loading…'));
			clear(elListBody);

			var tr = make('tr');
			var td = make('td', 'tm-empty', T('Loading…'));
			td.colSpan = 3;
			tr.appendChild(td);
			elListBody.appendChild(tr);

			return api('list').then(function (data) {
				state.maps = data.maps || [];
				state.canEdit = !!data.can_edit;
				syncCreateState();
				renderList();
				msg(elListMsg, T('Total ') + state.maps.length + T(' maps'));

				if (!state.canEdit) {
					msg(elListMsg, T('Your role cannot create or edit maps; read-only view'), 'error');
				}
			}).catch(function (e) {
				clear(elListBody);

				var row = make('tr');
				var cell = make('td', 'tm-empty', T('Loading failed: ') + e.message);
				cell.colSpan = 3;
				row.appendChild(cell);
				elListBody.appendChild(row);
				msg(elListMsg, T('Loading failed: ') + e.message, 'error');
			});
		}

		function syncCreateState() {
			elCreateBtn.disabled = !state.canEdit;
		}

		// ---------------------------------------------------------------
		// List: filter / sort / select / bulk actions
		// Structure and interactions aligned with native "Monitoring → Maps"
		// ---------------------------------------------------------------

		function visibleMaps() {
			var kw = state.filterName.trim().toLowerCase();
			var rows = state.maps.filter(function (map) {
				return !kw || String(map.name).toLowerCase().indexOf(kw) !== -1;
			});
			var field = state.sortField;
			var dir = state.sortOrder === 'DESC' ? -1 : 1;

			rows.sort(function (a, b) {
				var va = a[field];
				var vb = b[field];
				var cmp;

				if (typeof va === 'number' && typeof vb === 'number') {
					cmp = va - vb;
				}
				else {
					// localeCompare is required: plain string comparison uses code point order and sorts names incorrectly
					cmp = String(va).localeCompare(String(vb), locale(), {numeric: true, sensitivity: 'base'});
				}

				if (cmp === 0) {
					cmp = String(a.name).localeCompare(String(b.name), locale());
				}

				return cmp * dir;
			});

			return rows;
		}

		function checkedIds() {
			return state.maps
				.filter(function (m) { return state.checked[m.sysmapid]; })
				.map(function (m) { return m.sysmapid; });
		}

		function updateSelection() {
			var ids = checkedIds();
			var shown = visibleMaps().length;

			elSelectedCount.textContent = ids.length + T(' selected');

			if (elMassDelete) {
				elMassDelete.disabled = ids.length === 0;
			}

			if (elCheckAll) {
				elCheckAll.checked = shown > 0 && ids.length >= shown;
				elCheckAll.indeterminate = ids.length > 0 && ids.length < shown;
			}
		}

		function renderList() {
			clear(elListBody);

			var rows = visibleMaps();

			elTableStats.textContent = T('Showing ') + rows.length + T(', found ') + state.maps.length + ' ';

			if (!rows.length) {
				var emptyRow = make('tr');
				var emptyCell = make('td', 'tm-empty', state.maps.length
					? T('No matching maps.')
					: T('No maps yet. Click "Create map" in the upper right corner to add one.'));
				emptyCell.colSpan = 5;
				emptyRow.appendChild(emptyCell);
				elListBody.appendChild(emptyRow);
				updateSelection();

				return;
			}

			rows.forEach(function (map) {
				var tr = make('tr');

				// Checkbox (drawn as native .checkbox-radio + label>span)
				var tdCheck = make('td');
				var cb = make('input', 'checkbox-radio');
				cb.type = 'checkbox';
				cb.id = 'tm-maps_' + map.sysmapid;
				cb.checked = !!state.checked[map.sysmapid];
				cb.disabled = !state.canEdit;
				cb.addEventListener('change', function () {
					if (cb.checked) {
						state.checked[map.sysmapid] = true;
					}
					else {
						delete state.checked[map.sysmapid];
					}

					updateSelection();
				});

				var lb = make('label');
				lb.htmlFor = cb.id;
				lb.appendChild(make('span'));
				tdCheck.appendChild(cb);
				tdCheck.appendChild(lb);

				// Name (click to open the canvas preview)
				var tdName = make('td', 'wordbreak');
				var link = make('a', '', map.name);
				link.href = '#';
				link.title = T('Elements ') + map.elements + T('');
				link.addEventListener('click', function (e) {
					e.preventDefault();
					openMap(map.sysmapid, false);
				});
				tdName.appendChild(link);

				var tdW = make('td', '', String(map.width));
				var tdH = make('td', '', String(map.height));

				// Actions: properties / edit
				var tdAct = make('td');
				var ul = make('ul', 'hor-list');

				if (state.canEdit) {
					var prop = make('a', '', T('Properties'));
					prop.href = '#';
					prop.addEventListener('click', function (e) {
						e.preventDefault();
						openPropertiesDialog(map);
					});

					var edit = make('a', '', T('Edit'));
					edit.href = '#';
					edit.addEventListener('click', function (e) {
						e.preventDefault();
						openMap(map.sysmapid, true);
					});

					var liProp = make('li');
					var liEdit = make('li');
					liProp.appendChild(prop);
					liEdit.appendChild(edit);
					ul.appendChild(liProp);
					ul.appendChild(liEdit);
				}
				else {
					var liDash = make('li', 'tm-hint', '—');
					ul.appendChild(liDash);
				}

				tdAct.appendChild(ul);

				tr.appendChild(tdCheck);
				tr.appendChild(tdName);
				tr.appendChild(tdW);
				tr.appendChild(tdH);
				tr.appendChild(tdAct);
				elListBody.appendChild(tr);
			});

			updateSelection();
		}

		function syncSortArrows() {
			[[elSortName, 'name'], [elSortWidth, 'width'], [elSortHeight, 'height']].forEach(function (pair) {
				var el = pair[0];

				if (!el) {
					return;
				}

				var span = el.querySelector('span');

				if (!span) {
					span = make('span');
					el.appendChild(span);
				}

				span.className = (pair[1] === state.sortField)
					? (state.sortOrder === 'ASC' ? 'arrow-up' : 'arrow-down')
					: '';
			});
		}

		function sortBy(field) {
			if (state.sortField === field) {
				state.sortOrder = state.sortOrder === 'ASC' ? 'DESC' : 'ASC';
			}
			else {
				state.sortField = field;
				state.sortOrder = 'ASC';
			}

			syncSortArrows();
			renderList();
		}

		function applyFilter() {
			state.filterName = elFilterName.value;
			renderList();
		}

		function resetFilter() {
			elFilterName.value = '';
			state.filterName = '';
			renderList();
			elFilterName.focus();
		}

		function toggleCheckAll() {
			var check = !!elCheckAll.checked;

			visibleMaps().forEach(function (map) {
				if (check) {
					state.checked[map.sysmapid] = true;
				}
				else {
					delete state.checked[map.sysmapid];
				}
			});

			renderList();
		}

		function massDelete() {
			var ids = checkedIds();

			if (!ids.length) {
				return;
			}

			confirmDialog(T('Delete ') + ids.length + T(' maps?'),
				T('This cannot be undone. These maps share data with Monitoring → Maps and will also disappear from the native list.'),
				T('Delete')
			).then(function (ok) {
				if (!ok) {
					return;
				}

				api('delete', {sysmapids: ids.join(',')}).then(function () {
					ids.forEach(function (id) {
						delete state.checked[id];
					});
					msg(elListMsg, T('Deleted ') + ids.length + T(' maps'), 'ok');

					return loadList();
				}).catch(function (e) {
					msg(elListMsg, T('Deletion failed: ') + e.message, 'error');
				});
			});
		}

		function exportMaps() {
			var ids = checkedIds();

			api('export', {sysmapids: ids.join(',')}).then(function (data) {
				var a = document.createElement('a');

				if (typeof URL !== 'undefined' && URL.createObjectURL) {
					var url = URL.createObjectURL(new Blob([data.json], {type: 'application/json'}));

					a.href = url;
					setTimeout(function () {
						URL.revokeObjectURL(url);
					}, 2000);
				}
				else {
					a.href = 'data:application/json;charset=utf-8,' + encodeURIComponent(data.json);
				}

				a.download = data.filename || 'topomap-export.json';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				msg(elListMsg, T('Exported ') + data.count + T(' maps'), 'ok');
			}).catch(function (e) {
				msg(elListMsg, T('Export failed: ') + e.message, 'error');
			});
		}

		// Create map: create button → name / canvas size / apply / reset
		function openCreateDialog() {
			if (!state.canEdit) {
				openNoticeDialog(T('No permission'), T('Your role cannot create or edit maps (the role needs "Create and edit maps").'));

				return;
			}

			openDialog({
				title: T('Create map'),
				narrow: true,
				build: function (body) {
					var tbody = dialogForm(body);
					var nameRow = formRow(tbody, T('Name'), true);
					var input = make('input', 'tm-input tm-input-block');
					input.type = 'text';
					input.maxLength = 128;
					input.placeholder = T('Enter a map name');
					input.autocomplete = 'off';
					nameRow.td.appendChild(input);

					// The canvas size is no longer entered: the canvas grows wherever you drag and shrinks automatically on save
					var sizeRow = formRow(tbody, T('Canvas size'), false);
					sizeRow.td.appendChild(make('div', 'tm-size-auto', T('Auto')));
					sizeRow.td.appendChild(make('div', 'tm-hint',
						T('The canvas expands with its elements and shrinks to fit the content when saving')));

					var warn = make('div', 'tm-msg');
					warn.style.marginTop = '6px';
					nameRow.td.appendChild(warn);

					setTimeout(function () {
						input.focus();
					}, 0);

					input.addEventListener('keydown', function (e) {
						if (e.key === 'Enter') {
							openCreateDialog.submit();
						}
					});

					openCreateDialog.reset = function () {
						input.value = '';
						warn.textContent = '';
						input.focus();
					};

					openCreateDialog.submit = function () {
						var name = input.value.trim();

						if (!name) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('The name cannot be empty');
							input.focus();

							return;
						}

						warn.className = 'tm-msg';
						warn.textContent = T('Creating…');
						elCreateBtn.disabled = true;

						api('create', {name: name}).then(function (data) {
							closeDialog();

							return loadList().then(function () {
								msg(elListMsg, T('Created "') + name + '"', 'ok');
								openMap(data.sysmapid, false);
							});
						}).catch(function (e) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('Creation failed: ') + e.message;
						}).then(function () {
							syncCreateState();
						});
					};
				},
				buttons: [
					{label: T('Apply'), primary: true, onClick: function () { openCreateDialog.submit(); }},
					{label: T('Reset'), onClick: function () { openCreateDialog.reset(); }}
				]
			});
		}

		// ---------------------------------------------------------------
		// Auto discovery (SNMP LLDP / CDP)
		// ---------------------------------------------------------------

		// Temporary state of the dialog: which seeds are checked, which nodes/links were found
		var discover = null;

		function openDiscoverDialog() {
			discover = {
				mode: 'create',
				hosts: [],
				picked: {},
				nodes: {},
				links: {},
				scanned: {},
				running: false,
				finished: false
			};

			openDialog({
				title: T('Discover topology'),
				build: buildDiscoverBody,
				buttons: [
					{label: T('Cancel'), onClick: closeDialog},
					{
						label: T('Start discovery'),
						primary: true,
						// After discovery the same button becomes "Generate map"
						onClick: function () {
							if (discover && discover.finished) {
								saveDiscover();
							}
							else {
								startDiscover();
							}
						}
					}
				]
			});

			loadDiscoverHosts('');
		}

		function isRediscover() {
			return !!(discover && discover.mode === 'rediscover');
		}

		// In-canvas "rediscover": rescans using the host elements of the current map as seeds,
		// and merges the result into the current map in place (keeping configured link metrics).
		function openRediscoverDialog() {
			if (!state.map) {
				return;
			}

			if (!state.canEdit) {
				openNoticeDialog(T('No permission'), T('Your role cannot create or edit maps (the role needs "Create and edit maps").'));

				return;
			}

			var seeds = [];

			state.map.selements.forEach(function (se) {
				if (se.elementtype === ET_HOST && se._hostid) {
					seeds.push(String(se._hostid));
				}
			});

			if (!seeds.length) {
				msg(elListMsg, T('The current map has no host elements; cannot rediscover'), 'error');

				return;
			}

			discover = {
				mode: 'rediscover',
				hostids: seeds,
				hosts: [],
				picked: {},
				nodes: {},
				links: {},
				scanned: {},
				running: false,
				finished: false
			};

			seeds.forEach(function (hostid) {
				discover.picked[hostid] = true;
			});

			openDialog({
				title: T('Rediscover and update map'),
				build: buildDiscoverBody,
				buttons: [
					{label: T('Cancel'), onClick: closeDialog},
					{
						label: T('Start discovery'),
						primary: true,
						onClick: function () {
							if (discover && discover.finished) {
								applyRediscover();
							}
							else {
								startDiscover();
							}
						}
					}
				]
			});

			// Seed host names / IPs are used for new node labels; hostid is the fallback
			api('hosts', {hostids: seeds.join(',')}).then(function (data) {
				discover.hosts = data.hosts || [];
			}).catch(function () {});
		}

		function buildDiscoverBody(body) {
			if (isRediscover()) {
				body.appendChild(make('p', 'tm-discover-tip',
					T('Using the ') + discover.hostids.length + T(' hosts in the current map as seeds; new devices/links are merged in and ')
					+ T(' existing bandwidth/traffic item settings are kept.')));
			}
			else {
				body.appendChild(make('p', 'tm-discover-tip',
					T('Reads the LLDP / CDP neighbor tables from switches and builds the map. Seed devices need an SNMP interface (v2c recommended).')));

				var searchRow = make('div', 'tm-discover-search');
				var search = make('input', 'tm-input');
				search.type = 'text';
				search.placeholder = T('Search host name…');
				search.autocomplete = 'off';

				var timer = null;
				search.addEventListener('input', function () {
					clearTimeout(timer);
					timer = setTimeout(function () {
						loadDiscoverHosts(search.value.trim());
					}, 300);
				});

				searchRow.appendChild(search);

				var pick = make('button', 'tm-btn tm-btn-sm', T('Select all with SNMP'));
				pick.type = 'button';
				pick.addEventListener('click', function () {
					discover.hosts.forEach(function (host) {
						if (host.snmp) {
							discover.picked[host.hostid] = true;
						}
					});
					renderDiscoverHosts();
				});
				searchRow.appendChild(pick);

				var counter = make('span', 'tm-discover-count', T('0 selected'));
				counter.id = 'tm-discover-count';
				searchRow.appendChild(counter);
				body.appendChild(searchRow);

				var list = make('div', 'tm-discover-hosts');
				list.id = 'tm-discover-hosts';
				body.appendChild(list);
			}

			var opts = make('div', 'tm-discover-opts');

			var depthLabel = make('label', 'tm-discover-field');
			depthLabel.appendChild(make('span', 'tm-discover-field-name', T('Discovery depth (1-10 levels)')));
			// This used to be a fixed 1–4 level dropdown, so deep networks stopped at level 5; it is now a configurable number field
			var depth = make('input', 'tm-input');
			depth.type = 'number';
			depth.id = 'tm-discover-depth';
			depth.min = String(DISCOVER_DEPTH_MIN);
			depth.max = String(DISCOVER_DEPTH_MAX);
			depth.step = '1';
			depth.value = String(DISCOVER_DEPTH_DEFAULT);
			depthLabel.appendChild(depth);
			opts.appendChild(depthLabel);

			opts.appendChild(discoverCheck('tm-discover-lldp', 'LLDP', true));
			opts.appendChild(discoverCheck('tm-discover-cdp', 'CDP', false));
			opts.appendChild(discoverCheck('tm-discover-edp', 'EDP', false));
			opts.appendChild(discoverCheck('tm-discover-unmanaged', T('Include unmanaged devices'), true));

			// Two options specific to rediscovery: layout mode, and whether automatic links no longer discovered are removed
			if (isRediscover()) {
				var layoutField = make('label', 'tm-discover-field');
				layoutField.appendChild(make('span', 'tm-discover-field-name', T('Layout')));
				var layoutSel = make('select', 'tm-input');
				layoutSel.id = 'tm-rediscover-layout';
				[['tree', T('Rebuild as tree')], ['keep', T('Keep current positions')]].forEach(function (opt) {
					layoutSel.appendChild(make('option', '', opt[1])).value = opt[0];
				});
				layoutField.appendChild(layoutSel);
				opts.appendChild(layoutField);

				var pruneField = make('label', 'tm-discover-field');
				pruneField.appendChild(make('span', 'tm-discover-field-name', T('Auto links no longer discovered')));
				var pruneSel = make('select', 'tm-input');
				pruneSel.id = 'tm-rediscover-prune';
				[['0', T('Keep')], ['1', T('Remove')]].forEach(function (opt) {
					pruneSel.appendChild(make('option', '', opt[1])).value = opt[0];
				});
				pruneField.appendChild(pruneSel);
				opts.appendChild(pruneField);
			}

			body.appendChild(opts);

			// Keywords for automatic trigger linking: one per line; the generated map uses them to match port status triggers.
			// They used to be hard-coded, so users with different device descriptions could not change them.
			var kwField = make('label', 'tm-discover-field tm-discover-kw');
			kwField.appendChild(make('span', 'tm-discover-field-name', T('Trigger keywords for auto-linking (one per line)')));

			var keywords = make('textarea', 'tm-input tm-discover-keywords');
			keywords.id = 'tm-discover-keywords';
			keywords.rows = 4;
			keywords.value = T('link down');
			keywords.placeholder = T('One per line, e.g.:\nlink down');
			kwField.appendChild(keywords);
			body.appendChild(kwField);

			// Override the defaults with the last saved keywords (if nothing was saved, keep the defaults)
			api('settings').then(function (data) {
				var el = document.getElementById('tm-discover-keywords');

				if (el && data && data.trigger_keywords && data.trigger_keywords.length) {
					el.value = data.trigger_keywords.join('\n');
				}
			}).catch(function () {});

			var log = make('div', 'tm-discover-log');
			log.id = 'tm-discover-log';
			body.appendChild(log);

			var nameRow = make('div', 'tm-discover-namerow');
			nameRow.id = 'tm-discover-name-row';
			// .tm-discover-namerow uses display:flex, so setting only the hidden attribute is overridden by the author style;
			// always use setHidden (attribute + inline display together)
			setHidden(nameRow, true);
			nameRow.appendChild(make('span', 'tm-discover-field-name', T('Map name')));
			var nameInput = make('input', 'tm-input');
			nameInput.type = 'text';
			nameInput.id = 'tm-discover-name';
			nameInput.maxLength = 128;
			nameInput.value = defaultDiscoverName();
			nameRow.appendChild(nameInput);
			body.appendChild(nameRow);
		}

		function discoverCheck(id, label, isChecked) {
			var wrap = make('label', 'tm-discover-check');
			var input = make('input');
			input.type = 'checkbox';
			input.id = id;
			input.checked = !!isChecked;
			wrap.appendChild(input);
			wrap.appendChild(make('span', '', label));

			return wrap;
		}

		function loadDiscoverHosts(search) {
			var list = document.getElementById('tm-discover-hosts');

			if (!list) {
				return;
			}

			list.textContent = T('Loading…');

			api('hosts', {search: search || ''}).then(function (data) {
				// Keep only the checked state of hosts with an SNMP interface, so nothing is "checked but unscannable"
				discover.hosts = data.hosts || [];
				renderDiscoverHosts();
			}).catch(function (e) {
				clear(list);
				list.appendChild(make('div', 'tm-discover-empty', T('Failed to load hosts: ') + e.message));
			});
		}

		function renderDiscoverHosts() {
			var list = document.getElementById('tm-discover-hosts');

			if (!list) {
				return;
			}

			clear(list);

			if (!discover.hosts.length) {
				list.appendChild(make('div', 'tm-discover-empty', T('No matching hosts (try clearing the search)')));

				return;
			}

			discover.hosts.forEach(function (host) {
				var item = make('label', 'tm-discover-host' + (host.snmp ? '' : ' tm-discover-host-off'));

				var box = make('input');
				box.type = 'checkbox';
				box.value = host.hostid;
				box.checked = !!discover.picked[host.hostid];
				box.disabled = !host.snmp || discover.running;
				box.addEventListener('change', function () {
					if (box.checked) {
						discover.picked[host.hostid] = true;
					}
					else {
						delete discover.picked[host.hostid];
					}
					updateDiscoverCount();
				});

				item.appendChild(box);
				item.appendChild(make('span', 'tm-discover-host-name', host.name));

				if (host.ip) {
					item.appendChild(make('span', 'tm-discover-host-ip', host.ip));
				}

				item.appendChild(make('span', 'tm-discover-host-tag' + (host.snmp ? '' : ' tm-dim'),
					host.snmp ? 'SNMP' : T('No SNMP')));
				list.appendChild(item);
			});

			updateDiscoverCount();
		}

		function updateDiscoverCount() {
			var counter = document.getElementById('tm-discover-count');

			if (counter) {
				counter.textContent = T('Selected ') + Object.keys(discover.picked).length + T('');
			}
		}

		function appendDiscoverLog(text, kind) {
			var log = document.getElementById('tm-discover-log');

			if (!log) {
				return;
			}

			log.appendChild(make('div', 'tm-discover-line' + (kind ? ' tm-discover-' + kind : ''), text));
			log.scrollTop = log.scrollHeight;
		}

		function discoverOption(id) {
			var el = document.getElementById(id);

			return !!(el && el.checked);
		}

		function defaultDiscoverName() {
			var now = new Date();

			function pad(n) {
				return (n < 10 ? '0' : '') + n;
			}

			return T('Auto discovery ') + now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
				+ ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
		}

		function setDiscoverBusy(busy, label) {
			var buttons = elDialogFoot.querySelectorAll('button');

			if (buttons[0]) {
				buttons[0].disabled = busy;
			}

			if (buttons[1]) {
				buttons[1].disabled = busy;
				buttons[1].textContent = label;
			}

			document.querySelectorAll('#tm-dialog-body input, #tm-dialog-body select').forEach(function (el) {
				el.disabled = busy;
			});
		}

		function startDiscover() {
			if (!discover || discover.running) {
				return;
			}

			var seeds = Object.keys(discover.picked);

			if (!seeds.length) {
				appendDiscoverLog(T('Select at least one seed device first.'), 'error');

				return;
			}

			var protocols = [];

			if (discoverOption('tm-discover-lldp')) {
				protocols.push('LLDP');
			}

			if (discoverOption('tm-discover-cdp')) {
				protocols.push('CDP');
			}

			if (discoverOption('tm-discover-edp')) {
				protocols.push('EDP');
			}

			if (!protocols.length) {
				protocols = ['LLDP'];
			}

			var depthEl = document.getElementById('tm-discover-depth');
			var maxDepth = depthEl ? parseInt(depthEl.value, 10) : DISCOVER_DEPTH_DEFAULT;

			if (!isFinite(maxDepth)) {
				maxDepth = DISCOVER_DEPTH_DEFAULT;
			}

			// Clamp to 1–10: 0 levels means no discovery, and more than 10 levels is too expensive for SNMP scanning
			maxDepth = Math.min(DISCOVER_DEPTH_MAX, Math.max(DISCOVER_DEPTH_MIN, maxDepth));

			if (depthEl) {
				depthEl.value = String(maxDepth);
			}

			var includeUnmanaged = discoverOption('tm-discover-unmanaged');

			var hostMap = {};
			discover.hosts.forEach(function (host) {
				hostMap[host.hostid] = host;
			});

			discover.running = true;
			discover.finished = false;
			discover.nodes = {};
			discover.links = {};
			discover.scanned = {};

			clear(document.getElementById('tm-discover-log'));

			seeds.forEach(function (hostid) {
				var host = hostMap[hostid] || {hostid: hostid, name: hostid, ip: ''};

				addDiscoverNode('h:' + hostid, {
					hostid: hostid,
					name: host.name,
					ip: host.ip || '',
					depth: 0,
					kind: 'switch'
				});
			});

			var queue = seeds.map(function (hostid) {
				return {hostid: hostid, depth: 0};
			});
			var queued = {};

			seeds.forEach(function (hostid) {
				queued[hostid] = true;
			});

			appendDiscoverLog(T('Discovery started: ') + seeds.length + T(' seeds, depth ') + maxDepth + T(', protocol ')
				+ protocols.join(' / '));

			setDiscoverBusy(true, T('Discovering…'));

			// Sequential small batches: 3 hosts per batch, so SNMP scanning in one request does not exceed the fastcgi timeout
			(async function pump() {
				while (queue.length) {
					var batch = queue.splice(0, 3);

					appendDiscoverLog(T('Scanning ') + batch.map(function (item) {
						var host = hostMap[item.hostid];

						return host ? host.name : item.hostid;
					}).join(', ') + T(' …'));

					var data = null;

					try {
						// If backend SNMP gets stuck on a device, the request may not return for a long time; a frontend timeout
						// at least lets the discovery dialog report "scan failed" instead of staying at "Scanning xxx …" forever.
						// The backend's own per-batch budget is 20 s; 45 s only triggers when something is really stuck.
						data = await withTimeout(api('discover', {
							hostids: batch.map(function (item) { return item.hostid; }).join(','),
							protocols: protocols.join(','),
							timeout: '1200'
						}), 45000);
					}
					catch (e) {
						appendDiscoverLog(T('Scan failed: ') + e.message, 'error');
					}

					if (data) {
						(data.hosts || []).forEach(function (res) {
							collectDiscoverResult(res, hostMap, includeUnmanaged, maxDepth, queue, queued);
						});
					}

					batch.forEach(function (item) {
						discover.scanned[item.hostid] = true;
					});
				}

				discover.running = false;
				discover.finished = true;
				finishDiscover();
			})();
		}

		function collectDiscoverResult(res, hostMap, includeUnmanaged, maxDepth, queue, queued) {
			var sourceKey = 'h:' + res.hostid;
			var source = discover.nodes[sourceKey];

			if (source && res.sysname && !source.sysname) {
				source.sysname = res.sysname;
			}

			if (!res.ok) {
				appendDiscoverLog('· ' + (res.name || res.hostid) + ': ' + (res.error || T('read failed')), 'warn');

				return;
			}

			var sourceDepth = source ? source.depth : 0;

			if (!res.neighbors.length) {
				appendDiscoverLog('· ' + (res.name || res.hostid) + T(': no LLDP / CDP / EDP neighbors (neighbor discovery may be disabled on the device)'), 'warn');
			}

			res.neighbors.forEach(function (nb) {
				var targetKey;

				if (nb.hostid) {
					targetKey = 'h:' + nb.hostid;
				}
				else if (includeUnmanaged) {
					// Endpoints / APs / printers often have only a chassisId (MAC) and an empty sysName;
					// the chassis is used as a fallback node key, otherwise all nameless neighbors would collapse into one "n:" node.
					targetKey = 'n:' + String(nb.sysname || nb.chassis || nb.ip || '').toLowerCase();
				}
				else {
					return;
				}

				if (!targetKey || targetKey === 'n:') {
					return;
				}

				if (!discover.nodes[targetKey]) {
					var known = nb.hostid ? hostMap[nb.hostid] : null;

					addDiscoverNode(targetKey, {
						hostid: nb.hostid || '',
						name: nb.name || (known ? known.name : '') || nb.sysname || nb.chassis || nb.ip || T('Unnamed device'),
						ip: nb.ip || (known ? known.ip : ''),
						depth: sourceDepth + 1,
						kind: nb.kind || 'switch'
					});
				}

				addDiscoverLink(sourceKey, targetKey, nb);

				// Only managed neighbors are readable via SNMP; continue expanding to the next level
				if (nb.hostid && !discover.scanned[nb.hostid] && !queued[nb.hostid]
						&& sourceDepth + 1 < maxDepth) {
					queued[nb.hostid] = true;
					queue.push({hostid: nb.hostid, depth: sourceDepth + 1});
				}
			});
		}

		function addDiscoverNode(key, node) {
			if (!discover.nodes[key]) {
				discover.nodes[key] = node;
			}
		}

		// Port name normalization key: consistent with the backend TopomapData::ifPortKey
		// (Gi1/0/11 ↔ ge1_0_11 ↔ GigabitEthernet1/0/11 all normalize to ge1011).
		function portKey(s) {
			s = String(s || '').split(/\s*\(/)[0];
			s = s.toLowerCase();
			s = s.replace(/ten[-_ ]?gigabitethernet/, 'te');
			s = s.replace(/gigabitethernet/, 'ge');
			s = s.replace(/fastethernet/, 'fa');
			s = s.replace(/gi(?=[0-9/])/, 'ge');

			return s.replace(/[\s_\-:./]/g, '');
		}

		function addDiscoverLink(source, target, nb) {
			if (source === target) {
				return;
			}

			// Reverse duplicates are always skipped (the conclusion aiops reached after hitting the same problem):
			// for a pair of devices, once a link exists in the discovery direction, the record scanned in reverse is dropped entirely.
			//
			// Why the port-pair dedup below is not enough: the two ends may resolve local port names differently
			// (when a device is rate-limited, the lldpLocPortId table is incomplete and the port name resolves to another port), so
			// the aggregation switch reports ge0_0_23 ↔ access Gi0/0_48 while the reverse scan reports ge0_0_32 ↔ aggregation Gi0/0_23;
			// the port pairs differ → dedup fails → an extra duplicate link appears, and the "port down" trigger of an **unrelated port** on the access side
			// (GigabitEthernet0/0/32) gets bound to this real link.
			var reversed = false;

			Object.keys(discover.links).forEach(function (key) {
				var item = discover.links[key];

				if (item.source === target && item.target === source) {
					reversed = true;
				}
			});

			if (reversed) {
				return;
			}

			// The dedup key includes both end ports: the members of a link aggregation are several links between the same pair of devices
			// and cannot be deduplicated by device pair; the same port pair scanned again in the same direction is merged by the unordered normalized port-pair key.
			// Normalization is required: the core switch reports raw Comware numbers while the other end reports Gi1/0/11;
			// without it they do not merge and the map shows several visually overlapping duplicate links.
			var endA = source + '#' + portKey(nb.local_port || '');
			var endB = target + '#' + portKey(nb.port_id || '');
			var pair = endA < endB ? endA + '|' + endB : endB + '|' + endA;

			if (discover.links[pair]) {
				return;
			}

			discover.links[pair] = {
				source: source,
				target: target,
				source_port: nb.local_port || '',
				target_port: nb.port_id || '',
				protocol: nb.protocol || '',
				// New links match triggers with the default "source first"; users can change it in link properties
				trigger_source: 0
			};
		}

		function finishDiscover() {
			var devices = Object.keys(discover.nodes).length;
			var links = Object.keys(discover.links).length;

			appendDiscoverLog(T('Discovery complete: ') + devices + T(' devices, ') + links + T(' links'), devices ? 'ok' : 'warn');

			if (devices) {
				var matched = 0;

				Object.keys(discover.nodes).forEach(function (key) {
					if (discover.nodes[key].hostid) {
						matched++;
					}
				});

				appendDiscoverLog(T('Of which ') + matched + T(' are in Zabbix and ') + (devices - matched) + T(' are unmanaged devices.'));
			}

			var nameRow = document.getElementById('tm-discover-name-row');

			if (nameRow) {
				setHidden(nameRow, isRediscover() || devices === 0);
			}

			var buttons = elDialogFoot.querySelectorAll('button');

			if (buttons[0]) {
				buttons[0].disabled = false;
			}

			if (buttons[1]) {
				buttons[1].disabled = devices === 0;
				buttons[1].textContent = isRediscover() ? T('Update map') : T('Generate map');
			}

			document.querySelectorAll('#tm-dialog-body input, #tm-dialog-body select').forEach(function (el) {
				el.disabled = false;
			});
		}

		// Read the keyword text box of the discovery page: one per line, empty lines removed, trimmed, deduplicated.
		function discoverKeywords() {
			var el = document.getElementById('tm-discover-keywords');
			var seen = {};

			return (el ? el.value : '').split(/\r?\n/).map(function (line) {
				return line.trim();
			}).filter(function (line) {
				if (line === '' || seen[line.toLowerCase()]) {
					return false;
				}

				seen[line.toLowerCase()] = true;

				return true;
			});
		}

		// Serialize the current discovery result into the payload expected by the backend (shared by new maps and rediscovery)
		function discoverPayload() {
			var nodes = [];

			Object.keys(discover.nodes).forEach(function (key) {
				var node = discover.nodes[key];

				nodes.push({
					key: key,
					hostid: node.hostid || '',
					name: node.name,
					ip: node.ip || '',
					kind: node.kind || 'switch',
					depth: node.depth || 0
				});
			});

			// Sorted by level; the backend stacks them in order within the same level
			nodes.sort(function (a, b) {
				return a.depth - b.depth;
			});

			var links = Object.keys(discover.links).map(function (key) {
				return discover.links[key];
			});

			// Keywords for automatic trigger linking (entered on the discovery page, one per line) are submitted with the generate request,
			// and after the backend stores them, re-matching after changing "trigger source" in link properties uses the same set.
			var payload = {
				nodes: nodes,
				links: links,
				keywords: discoverKeywords()
			};

			if (isRediscover()) {
				var layoutEl = document.getElementById('tm-rediscover-layout');
				var pruneEl = document.getElementById('tm-rediscover-prune');

				payload.layout = layoutEl && layoutEl.value === 'keep' ? 'keep' : 'tree';
				payload.prune_links = pruneEl && pruneEl.value === '1' ? 1 : 0;
			}

			return payload;
		}

		function saveDiscover() {
			if (isRediscover()) {
				applyRediscover();

				return;
			}

			var nameInput = document.getElementById('tm-discover-name');
			var name = nameInput && nameInput.value.trim() !== '' ? nameInput.value.trim() : defaultDiscoverName();

			setDiscoverBusy(true, T('Generating…'));

			api('discoverSave', {
				name: name,
				payload: JSON.stringify(discoverPayload())
			}).then(function (data) {
				closeDialog();

				return loadList().then(function () {
					var extra = data.triggers ? T(', linked ') + data.triggers + T(' triggers') : '';

					msg(elListMsg, T('Generated "') + data.name + '": ' + data.devices + T(' devices, ') + data.links + T(' links') + extra, 'ok');

					return openMap(data.sysmapid, false);
				});
			}).catch(function (e) {
				appendDiscoverLog(T('Generation failed: ') + e.message, 'error');
				setDiscoverBusy(false, T('Generate map'));
			});
		}

		// Rediscovery step 2: send this result to the backend and merge it into the current map in place
		function applyRediscover() {
			if (!discover || !state.map) {
				return;
			}

			var sysmapid = String(state.map.sysmapid);

			setDiscoverBusy(true, T('Updating…'));

			api('rediscover', {
				sysmapid: sysmapid,
				payload: JSON.stringify(discoverPayload())
			}).then(function (data) {
				closeDialog();

				var removed = data.removed_links ? T(', removed ') + data.removed_links + T(' old links') : '';

				return openMap(sysmapid, false, true).then(function () {
					setHint(T('Rediscovery complete: added ') + data.added_devices + T(' devices, ') + data.added_links
						+ T(' links, kept ') + data.kept_links + T(' link settings') + removed);
					msg(elListMsg, T('Map updated: added ') + data.added_devices + T(' devices, ')
						+ data.added_links + T(' links') + removed, 'ok');
				});
			}).catch(function (e) {
				appendDiscoverLog(T('Update failed: ') + e.message, 'error');
				setDiscoverBusy(false, T('Update map'));
			});
		}

		// ---------------------------------------------------------------
		// View state memory (aligned with native map.view / sysmaps.php)
		// ---------------------------------------------------------------

		// Native: opening map.view writes the sysmapid to the user profile (web.maps.sysmapid),
		// and opening the list page sysmaps.php deletes it; the left menu always points to action=map.view,
		// so "coming back from the menu" always returns to the last view.
		// Here the list and the canvas are one action, so it is synced once on open and once on return.
		function rememberMap(sysmapid) {
			// Failures stay silent: the worst case is only that the menu does not return to this map next time; no error popup should interrupt the user.
			api('remember', {sysmapid: sysmapid || ''}).catch(function () {});
		}

		// The address bar follows the state too, so the browser back button returns from canvas to list as in native.
		// Only the sysmapid parameter is touched; other query parameters (action etc.) are kept.
		function viewUrl(sysmapid) {
			var params = new URLSearchParams(window.location.search);

			if (sysmapid) {
				params.set('sysmapid', sysmapid);
			}
			else {
				params.delete('sysmapid');
			}

			return window.location.pathname + '?' + params.toString();
		}

		// mode: 'push' (user action, default) / 'replace' (initial page state) / 'none' (responding to back, do not record again)
		function syncViewUrl(sysmapid, mode) {
			if (mode === 'none' || !window.history || !window.history.pushState) {
				return;
			}

			var url = viewUrl(sysmapid);

			if (url === window.location.pathname + window.location.search) {
				return;
			}

			try {
				history[mode === 'replace' ? 'replaceState' : 'pushState'](
					{tm_sysmapid: sysmapid || ''}, '', url
				);
			}
			catch (e) {
				// In special environments such as file:// pushState throws a SecurityError.
				// Syncing the address bar is only a nicety and must never interrupt opening the map itself.
			}
		}

		// ---------------------------------------------------------------
		// Open / close the canvas
		// ---------------------------------------------------------------

		function openMap(sysmapid, editing, keepView, urlMode) {
			setHint(T('Loading…'));

			return api('get', {sysmapid: sysmapid}).then(function (data) {
				state.map = normalizeMap(data.map);
				// Bound triggers only have a triggerid; their names are fetched for the chips of the "link" dialog
				var triggerids = [];
				state.map.links.forEach(function (link) {
					(link.linktriggers || []).forEach(function (lt) {
						if (lt && lt.triggerid) {
							triggerids.push(String(lt.triggerid));
						}
					});
				});
				resolveTriggerNames(triggerids);
				state.canEdit = !!data.can_edit;
				state.selected = null;
				state.dirty = false;
				state.removeMode = null;
				state.linkMode = false;
				state.linkFirst = null;
				state.bendLink = null;

				if (!keepView) {
					state.zoom = 1;
					state.panX = 0;
					state.panY = 0;
				}

				setHidden(elListView, true);
				setHidden(elCanvasView, false);
				// "Create map" must not appear at the top right of the canvas page (it is a list page action)
				setHidden(elHeaderControls, true);
				elCanvasTitle.textContent = state.map.name;

				// Remember which map is being viewed and sync the address bar
				rememberMap(state.map.sysmapid);
				syncViewUrl(String(state.map.sysmapid), urlMode || 'push');

				setEditing(!!editing && state.canEdit);
				renderCanvas();

				// Shrink the canvas to the content on entry: large canvases saved earlier (e.g. 3000×3000) no longer leave the screen blank.
				// Only computed in memory; saving waits for the user to click Save.
				autoSizeCanvas();

				if (!keepView) {
					// Show at 100% on entry (native map.view is also 100%); no automatic zoom
					resetView();
				}

				return loadIcons().then(function () {
					renderCanvas();
					applyView();
					startAutoRefresh();
				});
			}).catch(function (e) {
				setHint('');
				msg(elListMsg, T('Open failed: ') + e.message, 'error');
			});
		}

		// ---------------------------------------------------------------
		// Canvas auto-refresh: link trigger alarm state / host status / bandwidth and traffic values must follow the database,
		// otherwise while the page is open "links never turn red and metrics keep one value".
		// No refresh in edit mode (unsaved changes), and it stops when returning to the list.
		// ---------------------------------------------------------------

		var autoRefreshTimer = null;
		var AUTO_REFRESH_MS = 60000;

		function stopAutoRefresh() {
			if (autoRefreshTimer) {
				clearInterval(autoRefreshTimer);
				autoRefreshTimer = null;
			}
		}

		function startAutoRefresh() {
			stopAutoRefresh();
			autoRefreshTimer = setInterval(refreshMapState, AUTO_REFRESH_MS);
		}

		function refreshMapState() {
			if (!state.map || state.editing || document.hidden || elCanvasView.hidden) {
				return;
			}

			var sysmapid = String(state.map.sysmapid);
			var selected = state.selected;

			api('get', {sysmapid: sysmapid}).then(function (data) {
				if (!state.map || String(state.map.sysmapid) !== sysmapid || state.editing) {
					return;
				}

				// Only the data is replaced; zoom/pan/selection are untouched so the user's view is not reset every minute
				state.map = normalizeMap(data.map);
				state.canEdit = !!data.can_edit;
				state.selected = selected;
				state.dirty = false;
				// Incremental refresh: keep the element DOM (especially <img>) and only update status / labels / highlights / links;
				// rebuilding the whole layer makes every host icon flicker, while the built-in Zabbix maps update in place.
				refreshCanvas();
			}).catch(function () {
				// Auto-refresh failures do not bother the user; try again next round
			});
		}

		/**
		 * Incremental redraw for auto-refresh: reuses existing .tm-el nodes (icons are not rebuilt),
		 * only position / status class / label change, then the highlight layer and links are redrawn (pure SVG, no image loading).
		 */
		function refreshCanvas() {
			var map = state.map;

			if (!map) {
				return;
			}

			var oldNodes = elStage.querySelectorAll('.tm-el');
			var boxes = {};
			var seen = {};

			for (var i = 0; i < oldNodes.length; i++) {
				boxes[oldNodes[i].getAttribute('data-id')] = oldNodes[i];
			}

			map.selements.forEach(function (se) {
				var id = String(se.selementid);

				seen[id] = true;

				if (boxes[id]) {
					paintElementState(se, boxes[id]);
				}
				else {
					elStage.appendChild(renderElement(se));
				}
			});

			Object.keys(boxes).forEach(function (id) {
				if (!seen[id] && boxes[id].parentNode) {
					boxes[id].parentNode.removeChild(boxes[id]);
				}
			});

			var hint = elStage.querySelector('.tm-blank-hint');

			if (map.selements.length) {
				if (hint) {
					elStage.removeChild(hint);
				}
			}
			else if (!hint) {
				elStage.appendChild(make('div', 'tm-blank-hint', T('This map has no elements yet')));
			}

			drawShapes(map);
			drawHighlights(map);
			drawLinks(map);
			resizeStage();
		}

		function backToList() {
			if (state.editing && state.dirty) {
				confirmDialog(T('Discard unsaved changes?'), T('The current map has unsaved changes; returning to the list will discard them.'), T('Discard changes')).then(function (ok) {
					if (ok) {
						doBackToList();
					}
				});

				return;
			}

			doBackToList();
		}

		function doBackToList(urlMode) {
			stopAutoRefresh();
			state.map = null;
			state.editing = false;
			state.dirty = false;
			clearModes();

			// Back to list = forget the last map + remove sysmapid from the address bar (aligned with CProfile::delete in native sysmaps.php)
			rememberMap('');
			syncViewUrl('', urlMode || 'push');

			// Only direct children of the stage (element divs and the empty hint) are cleared; the SVG is kept for reuse.
			// Do not iterate querySelectorAll('*'): img/span are descendants of the div and removeChild would throw NotFoundError.
			var node = elStage.firstChild;

			while (node) {
				var next = node.nextSibling;

				if (node !== elSvg) {
					elStage.removeChild(node);
				}

				node = next;
			}

			clear(elSvg);
			setHidden(elCanvasView, true);
			setHidden(elListView, false);
			setHidden(elHeaderControls, false);
			loadList();
		}

		function setEditing(flag) {
			state.editing = !!flag;
			root.classList.toggle('tm-editing', state.editing);
			setHidden(elEditbar, !state.editing);
			setHidden(elEditBtn, state.editing);
			setHidden(elRediscoverBtn, state.editing);
			setHidden(elSaveBtn, !state.editing);
			setHidden(elExitBtn, !state.editing);
			elEditBtn.disabled = !state.canEdit;
			elRediscoverBtn.disabled = !state.canEdit;

			if (!state.editing) {
				clearModes();
			}

			setHint(state.editing ? '' : T('Preview mode'));

			// Showing/hiding the edit toolbar changes the canvas top position; recompute the available height
			layoutCanvasWrap();
			// The canvas height changed and may exceed the current canvas size (when leaving edit mode); fill it up
			ensureCanvasFitsViewport();
		}

		function clearModes() {
			state.removeMode = null;
			state.linkMode = false;
			state.linkFirst = null;
			state.bendLink = null;
			state.shapeSel = null;
			syncModeButtons();
			setHint(state.editing ? '' : T('Preview mode'));
		}

		// Remove / link modes are highlighted on the toolbar so the user always knows the current mode
		function syncModeButtons() {
			var buttons = elEditbar.querySelectorAll('button[data-group]');

			for (var i = 0; i < buttons.length; i++) {
				var group = buttons[i].getAttribute('data-group');
				var op = buttons[i].getAttribute('data-op');
				var active = (op === 'remove' && state.removeMode === group)
					|| (op === 'add' && group === 'link' && state.linkMode);

				buttons[i].classList.toggle('tm-btn-active', active);
			}
		}

		function markDirty() {
			state.dirty = true;
			// While editing waypoints the hint bar stays, so the user knows how to add / delete points
			setHint(state.bendLink ? BEND_HINT : T('Unsaved changes'));
		}

		// ---------------------------------------------------------------
		// Canvas rendering
		// ---------------------------------------------------------------

		function renderCanvas() {
			var map = state.map;

			if (!map) {
				return;
			}

			var wanted = {};

			map.selements.forEach(function (se) {
				wanted[iconForElement(se)] = true;
			});

			preloadIcons(Object.keys(wanted)).then(function () {
				if (state.map === map) {
					paintCanvas();
				}
			});

			paintCanvas();
			applyView();
		}

		function paintCanvas() {
			var map = state.map;

			if (!map) {
				return;
			}

			elStage.style.width = map.width + 'px';
			elStage.style.height = map.height + 'px';
			elSvg.setAttribute('width', String(map.width));
			elSvg.setAttribute('height', String(map.height));
			elSvg.setAttribute('viewBox', '0 0 ' + map.width + ' ' + map.height);

			clear(elSvg);

			var oldEls = elStage.querySelectorAll('.tm-el');

			for (var i = 0; i < oldEls.length; i++) {
				elStage.removeChild(oldEls[i]);
			}

			drawShapes(map);
			// The highlight layer must be below the links (native layering: shapes → highlights → links → elements),
			// so links run over the highlight circle straight to the host icon instead of stopping at the circle edge.
			drawHighlights(map);
			drawLinks(map);
			// Handles are drawn on top so links do not cover them
			drawShapeHandles();

			map.selements.forEach(function (se) {
				elStage.appendChild(renderElement(se));
			});

			if (!map.selements.length) {
				var hint = make('div', 'tm-blank-hint', state.editing ? T('Click "Map elements: Add" to start building the map') : T('This map has no elements yet'));
				elStage.appendChild(hint);
			}
		}

		// ---------------------------------------------------------------
		// Shapes: rendering (same semantics as native SVGMapShape)
		// ---------------------------------------------------------------

		function svgNode(tag) {
			return document.createElementNS('http://www.w3.org/2000/svg', tag);
		}

		// Native maps scale the dash arrays of dotted/dashed lines by line width (only when the width > 1)
		function shapeDashArray(shape) {
			var raw = SHAPE_BORDER_DASHES[shape.border_type] || '';
			var width = Math.max(0, num(shape.border_width, 0));

			if (raw === '' || raw === 'none' || width <= 1) {
				return raw;
			}

			return raw.split(',').map(function (part) {
				var value = parseInt(part, 10);

				return String(value === 1 && width > 2 ? 1 : value * width);
			}).join(',');
		}

		function applyShapePaint(node, shape) {
			node.setAttribute('fill', shape.type === 2 || !shape.background_color
				? 'none' : '#' + shape.background_color);

			if (shape.border_type === 0) {
				node.setAttribute('stroke', 'none');
				node.setAttribute('stroke-width', '0');

				return;
			}

			var width = Math.max(1, num(shape.border_width, 1));
			var dash = shapeDashArray(shape);

			node.setAttribute('stroke', '#' + shape.border_color);
			node.setAttribute('stroke-width', String(width));

			if (dash !== '' && dash !== 'none') {
				node.setAttribute('stroke-dasharray', dash);

				// Dotted lines get round caps on thick strokes, as in native
				if (shape.border_type === 2 && width > 2) {
					node.setAttribute('stroke-linecap', 'round');
				}
			}
		}

		// Geometry: rectangles/ellipses use x,y,width,height; lines treat width,height as the end coordinates (native semantics)
		function applyShapeGeometry(node, shape) {
			if (shape.type === 2) {
				node.setAttribute('x1', String(shape.x));
				node.setAttribute('y1', String(shape.y));
				node.setAttribute('x2', String(shape.width));
				node.setAttribute('y2', String(shape.height));
			}
			else if (shape.type === 1) {
				node.setAttribute('cx', String(shape.x + shape.width / 2));
				node.setAttribute('cy', String(shape.y + shape.height / 2));
				node.setAttribute('rx', String(shape.width / 2));
				node.setAttribute('ry', String(shape.height / 2));
			}
			else {
				node.setAttribute('x', String(shape.x));
				node.setAttribute('y', String(shape.y));
				node.setAttribute('width', String(shape.width));
				node.setAttribute('height', String(shape.height));
			}
		}

		function shapeTag(shape) {
			return shape.type === 2 ? 'line' : (shape.type === 1 ? 'ellipse' : 'rect');
		}

		// {MAP.NAME} macro in shape text: the raw macro is stored and expanded with the current map name at render time.
		// It is re-rendered on every open/rename, so nothing needs to be written back to the database.
		function shapeDisplayText(shape) {
			return String(shape.text || '').replace(/\{MAP\.NAME\}/gi, state.map ? String(state.map.name || '') : '');
		}

		// Text: alignment, font and padding follow native (5px padding) and are clipped inside the shape
		function drawShapeText(shape, clipId) {
			var display_text = shapeDisplayText(shape);

			// Native maps do not draw text on lines
			if (shape.type === 2 || !display_text) {
				return;
			}

			var lines = display_text.split(/\r?\n/);
			var fontSize = Math.max(1, num(shape.font_size, 11));
			var lineH = fontSize * 1.2;
			var total = lines.length * lineH;
			var x = shape.x + shape.width / 2;
			var anchor = 'middle';

			if (shape.text_halign === 1) {
				x = shape.x + SHAPE_TEXT_PADDING;
				anchor = 'start';
			}
			else if (shape.text_halign === 2) {
				x = shape.x + shape.width - SHAPE_TEXT_PADDING;
				anchor = 'end';
			}

			var top;

			if (shape.text_valign === 1) {
				top = shape.y + SHAPE_TEXT_PADDING;
			}
			else if (shape.text_valign === 2) {
				top = shape.y + shape.height - SHAPE_TEXT_PADDING - total;
			}
			else {
				top = shape.y + shape.height / 2 - total / 2;
			}

			var text = svgNode('text');

			text.setAttribute('class', 'tm-shape-text');
			text.setAttribute('fill', '#' + shape.font_color);
			text.setAttribute('font-family', SHAPE_FONT_FAMILIES[num(shape.font, 9)] || SHAPE_FONT_FAMILIES[9]);
			text.setAttribute('font-size', String(fontSize));
			text.setAttribute('text-anchor', anchor);
			text.setAttribute('y', String(top + fontSize));

			if (clipId) {
				text.setAttribute('clip-path', 'url(#' + clipId + ')');
			}

			lines.forEach(function (line, index) {
				var tspan = svgNode('tspan');

				tspan.setAttribute('x', String(x));
				tspan.setAttribute('dy', index === 0 ? '0' : String(lineH));
				tspan.textContent = line;
				text.appendChild(tspan);
			});

			elSvg.appendChild(text);
		}

		function drawShapes(map) {
			// Idempotent: clear the previous shapes first (including text and clip paths). This allows redrawing shapes alone while dragging
			// without paintCanvas() rebuilding the element DOM (which would invalidate the node being dragged).
			var olds = elSvg.querySelectorAll('.tm-shape, .tm-shape-text, .tm-shape-clip');

			for (var k = 0; k < olds.length; k++) {
				elSvg.removeChild(olds[k]);
			}

			map.shapes.forEach(function (shape, index) {
				var node = svgNode(shapeTag(shape));

				applyShapeGeometry(node, shape);
				applyShapePaint(node, shape);
				node.setAttribute('class', 'tm-shape');
				node.setAttribute('data-shapeid', String(shape.sysmap_shapeid || shape._tmpid || ''));
				node.style.cursor = state.editing ? 'move' : 'default';

				if (state.editing) {
					node.classList.toggle('tm-removable', state.removeMode === 'shape');
				}

				node.addEventListener('mousedown', function (e) {
					if (!state.editing || state.removeMode || state.linkMode) {
						return;
					}

					e.stopPropagation();
					selectShape(shape);
					startShapeDrag(shape, e);
				});

				// Native: single click selects and shows handles, double click opens the properties window
				node.addEventListener('click', function (e) {
					if (!state.editing) {
						return;
					}

					e.stopPropagation();

					if (state.removeMode === 'shape') {
						removeShape(shape);
						return;
					}

					if (!state.removeMode && !state.linkMode) {
						selectShape(shape);
					}
				});

				node.addEventListener('dblclick', function (e) {
					if (!state.editing || state.removeMode || state.linkMode) {
						return;
					}

					e.stopPropagation();
					selectShape(shape);
					openShapeDialog(shape, false);
				});

				elSvg.appendChild(node);

				var hasText = shape.type !== 2 && !!shapeDisplayText(shape);
				var clipId = '';

				if (hasText) {
					clipId = 'tm-shape-clip-' + String(shape.sysmap_shapeid || shape._tmpid || index);

					var clip = svgNode('clipPath');
					var clipShape = svgNode(shapeTag(shape));

					clip.setAttribute('id', clipId);
					clip.setAttribute('class', 'tm-shape-clip');
					applyShapeGeometry(clipShape, shape);
					clip.appendChild(clipShape);
					elSvg.appendChild(clip);
				}

				drawShapeText(shape, clipId);
			});
		}

		function selectShape(shape) {
			if (state.shapeSel === shape) {
				return;
			}

			state.shapeSel = shape;
			drawShapeHandles();
		}

		// Selection handles: 8 for rectangles/ellipses (four corners + four edge midpoints), 2 end points for lines — as in native
		function drawShapeHandles() {
			var olds = elSvg.querySelectorAll('.tm-shape-handle');

			for (var k = 0; k < olds.length; k++) {
				elSvg.removeChild(olds[k]);
			}

			var shape = state.shapeSel;

			if (!shape || !state.editing || state.removeMode || state.linkMode) {
				return;
			}

			var handles = [];

			if (shape.type === 2) {
				// For lines x,y and width,height are both absolute coordinates (start / end)
				handles = [
					{x: shape.x, y: shape.y, dir: 'p1', cursor: 'move'},
					{x: shape.width, y: shape.height, dir: 'p2', cursor: 'move'}
				];
			}
			else {
				var x1 = shape.x;
				var y1 = shape.y;
				var x2 = shape.x + shape.width;
				var y2 = shape.y + shape.height;
				var mx = (x1 + x2) / 2;
				var my = (y1 + y2) / 2;

				handles = [
					{x: x1, y: y1, dir: 'nw', cursor: 'nwse-resize'},
					{x: mx, y: y1, dir: 'n', cursor: 'ns-resize'},
					{x: x2, y: y1, dir: 'ne', cursor: 'nesw-resize'},
					{x: x2, y: my, dir: 'e', cursor: 'ew-resize'},
					{x: x2, y: y2, dir: 'se', cursor: 'nwse-resize'},
					{x: mx, y: y2, dir: 's', cursor: 'ns-resize'},
					{x: x1, y: y2, dir: 'sw', cursor: 'nesw-resize'},
					{x: x1, y: my, dir: 'w', cursor: 'ew-resize'}
				];
			}

			handles.forEach(function (handle) {
				var dot = svgNode('circle');

				dot.setAttribute('class', 'tm-shape-handle');
				dot.setAttribute('data-dir', handle.dir);
				dot.setAttribute('cx', String(handle.x));
				dot.setAttribute('cy', String(handle.y));
				dot.setAttribute('r', String(SHAPE_HANDLE_RADIUS));
				dot.style.cursor = handle.cursor;

				dot.addEventListener('mousedown', function (e) {
					e.stopPropagation();
					e.preventDefault();
					startShapeResize(shape, handle.dir, e);
				});

				elSvg.appendChild(dot);
			});
		}

		function startShapeResize(shape, dir, e) {
			var startX = e.clientX;
			var startY = e.clientY;
			var start = pointerToMap(e);
			var orig = {x: shape.x, y: shape.y, width: shape.width, height: shape.height};
			var moved = false;
			var isLine = shape.type === 2;

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 1 || Math.abs(ev.clientY - startY) > 1) {
					moved = true;
				}

				var pt = pointerToMap(ev);
				var dx = Math.round(pt.x - start.x);
				var dy = Math.round(pt.y - start.y);

				if (isLine) {
					if (dir === 'p1') {
						shape.x = Math.max(0, orig.x + dx);
						shape.y = Math.max(0, orig.y + dy);
					}
					else {
						shape.width = Math.max(0, orig.width + dx);
						shape.height = Math.max(0, orig.height + dy);
					}
				}
				else {
					var x1 = orig.x;
					var y1 = orig.y;
					var x2 = orig.x + orig.width;
					var y2 = orig.y + orig.height;

					if (dir.indexOf('w') !== -1) {
						x1 = Math.min(Math.max(0, orig.x + dx), x2 - 1);
					}
					else if (dir.indexOf('e') !== -1) {
						x2 = Math.max(x1 + 1, orig.x + orig.width + dx);
					}

					if (dir.indexOf('n') !== -1) {
						y1 = Math.min(Math.max(0, orig.y + dy), y2 - 1);
					}
					else if (dir.indexOf('s') !== -1) {
						y2 = Math.max(y1 + 1, orig.y + orig.height + dy);
					}

					shape.x = x1;
					shape.y = y1;
					shape.width = Math.max(1, x2 - x1);
					shape.height = Math.max(1, y2 - y1);
				}

				var right = isLine ? Math.max(shape.x, shape.width) : shape.x + shape.width;
				var bottom = isLine ? Math.max(shape.y, shape.height) : shape.y + shape.height;

				growCanvasTo(right + CANVAS_PAD, bottom + CANVAS_PAD);
				repaintSoon();
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		function elementCenter(se) {
			var size = iconSize(iconForElement(se));

			return {x: se.x + size.w / 2, y: se.y + size.h / 2, w: size.w, h: size.h};
		}

		// ---------------------------------------------------------------
		// Link anchors in the tree layout
		// ---------------------------------------------------------------

		// Gap between link ends and the icon / text block; length of the vertical "trunk" leaving the parent anchor in orthogonal routing
		var LINK_EDGE_GAP = 6;
		var LINK_TRUNK_GAP = 30;

		// Height of the label text block below the icon: name line + status line (OK / Problem / Disabled / In maintenance),
		// 16px per line, 3px below the icon, matching the .tm-el-label style.
		// When the label is not below (left / right / top) it takes no space below the icon and counts as 0.
		function elementBottomLabelHeight(se) {
			var loc = se.label_location === -1 && state.map ? state.map.label_location : se.label_location;

			if (loc !== 0 && loc !== -1) {
				return 0;
			}

			var name = elementLabel(se);
			var nameCount = name ? String(name).split('\n').length : 0;
			var count = nameCount + (elementStatusLines(se) || []).length;

			return count ? 3 + count * 16 : 0;
		}

		// "Same level" check: the element centers differ vertically by less than 3/4 of an icon height.
		// Neighboring hosts on the same level still connect by center and must not be pulled to top/bottom anchors.
		function sameRowElements(a, b) {
			var sa = iconSize(iconForElement(a));
			var sb = iconSize(iconForElement(b));

			return Math.abs((a.y + sa.h / 2) - (b.y + sb.h / 2)) < Math.max(sa.h, sb.h) * 0.75;
		}

		/*
		 * Link end anchors. Links between upper and lower levels use tree anchors:
		 *   parent (the upper one) = bottom of icon + name + "OK / Problem" text block, plus 6px;
		 *   child (the lower one) = 6px above the top of the icon.
		 * Links on the same level or diagonal neighbors still connect by element center.
		 * The returned pa is always the parent end and pb the child end (links have no arrows, so direction does not matter).
		 */
		function elementAnchors(a, b) {
			var ca = elementCenter(a);
			var cb = elementCenter(b);

			if (sameRowElements(a, b)) {
				return {pa: {x: ca.x, y: ca.y}, pb: {x: cb.x, y: cb.y}, vertical: false};
			}

			var sa = iconSize(iconForElement(a));
			var sb = iconSize(iconForElement(b));

			if (b.y > a.y) {
				return {
					pa: {x: ca.x, y: a.y + sa.h + elementBottomLabelHeight(a) + LINK_EDGE_GAP},
					pb: {x: cb.x, y: b.y - LINK_EDGE_GAP},
					vertical: true,
					parent: a
				};
			}

			return {
				pa: {x: cb.x, y: b.y + sb.h + elementBottomLabelHeight(b) + LINK_EDGE_GAP},
				pb: {x: ca.x, y: a.y - LINK_EDGE_GAP},
				vertical: true,
				parent: b
			};
		}

		/*
		 * Link aggregation member offset: the whole bundle now lies on the child side instead of the earlier symmetric offset.
		 * With a symmetric offset, the link on the other side crossed the vertical line of the host right below the same parent, which looked like a crossing.
		 */
		function treeMemberOffset(index, total, dx) {
			if (total <= 1) {
				return 0;
			}

			if (Math.abs(dx) < 1) {
				return (index - (total - 1) / 2) * LINK_PAIR_SPACING;
			}

			return (dx < 0 ? -1 : 1) * (index + 0.5) * LINK_PAIR_SPACING;
		}

		// Tree orthogonal: parent anchor → short vertical segment to the trunk → horizontal to above the child → vertical down to the child anchor.
		// pa / pb already include the aggregation member offset, so both ends are vertical and no slanted corners appear;
		// railOff only shifts the horizontal segments of group members a few pixels apart so they do not overlap into one line.
		// When levels are close, the trunk is pulled up so the horizontal segment stays above the child icon instead of crossing it.
		function treeRoute(pa, pb, railOff) {
			var rail = Math.min(pa.y + LINK_TRUNK_GAP + railOff, pb.y - 8);

			rail = Math.max(rail, pa.y + 4);

			return [
				{x: pa.x, y: pa.y},
				{x: pa.x, y: rail},
				{x: pb.x, y: rail},
				{x: pb.x, y: pb.y}
			];
		}

		// ---------------------------------------------------------------
		// Link metrics (bandwidth / upstream / downstream)
		// ---------------------------------------------------------------

		// Three groups of item IDs per link, chosen by the user from Zabbix items; aggregated member ports mean several, summed for display.
		var METRIC_KINDS = [
			{key: 'bw', label: T('Bandwidth'), prefix: '≡', color: '#FACC15'},
			{key: 'in', label: T('Inbound traffic'), prefix: '↑', color: '#00D4FF'},
			{key: 'out', label: T('Outbound traffic'), prefix: '↓', color: '#00FF88'}
		];

		function normalizeMetrics(raw) {
			var m = raw || {};
			var out = {};

			METRIC_KINDS.forEach(function (kind) {
				var list = m[kind.key];
				out[kind.key] = Array.isArray(list) ? list.map(String) : [];
			});

			return out;
		}

		// Normalize waypoints: keep only numeric {x,y} pairs, rounded and clamped to non-negative (same as sysmaps coordinates)
		function normalizeWaypointList(raw) {
			if (!Array.isArray(raw)) {
				return [];
			}

			var out = [];

			raw.forEach(function (point) {
				if (!point) {
					return;
				}

				var x = Number(point.x);
				var y = Number(point.y);

				if (!isFinite(x) || !isFinite(y)) {
					return;
				}

				out.push({x: Math.max(0, Math.round(x)), y: Math.max(0, Math.round(y))});
			});

			return out;
		}

		function metricKind(key) {
			for (var i = 0; i < METRIC_KINDS.length; i++) {
				if (METRIC_KINDS[i].key === key) {
					return METRIC_KINDS[i];
				}
			}

			return null;
		}

		function selementById(selementid) {
			var list = (state.map && state.map.selements) || [];

			for (var i = 0; i < list.length; i++) {
				if (String(list[i].selementid) === String(selementid)) {
					return list[i];
				}
			}

			return null;
		}

		// Zabbix hosts of the elements at both ends of a link (image elements have no hostid). Metric items are chosen only from these two.
		function linkHostids(link) {
			var out = [];

			[link.selementid1, link.selementid2].forEach(function (sid) {
				var se = selementById(sid);

				if (se && se._hostid && out.indexOf(String(se._hostid)) === -1) {
					out.push(String(se._hostid));
				}
			});

			return out;
		}

		// Hosts checked by default in the item selector: **source end first** (selementid1); falls back to the local end when the source is not a host.
		function defaultPickHostids(link) {
			var ends = linkEnds(link);

			if (ends.srcHostid) {
				return [ends.srcHostid];
			}

			if (ends.tgtHostid) {
				return [ends.tgtHostid];
			}

			return [];
		}

		// Hosts at both ends of the link (source = selementid1, local = selementid2).
		function linkEnds(link) {
			var a = selementById(link.selementid1);
			var b = selementById(link.selementid2);

			return {
				srcHostid: a && a._hostid ? String(a._hostid) : '',
				tgtHostid: b && b._hostid ? String(b._hostid) : ''
			};
		}

		// Hosts at both ends (deduplicated): manual trigger binding picks from the hosts at these two ends
		function linkEndHostids(link) {
			var ends = linkEnds(link);
			var ids = [];

			[ends.srcHostid, ends.tgtHostid].forEach(function (id) {
				if (id && ids.indexOf(id) === -1) {
					ids.push(id);
				}
			});

			return ids;
		}

		// Re-match the port status triggers of this link with the current "trigger source" scope.
		// Once matched, both the native map and the module canvas draw the link red on alarm.
		function rematchLinkTriggers(link) {
			var ends = linkEnds(link);

			// Both ends must be hosts and at least one port name must exist for anything to match.
			// Manual links have no port information; they are not re-matched so the user's manually bound triggers are not cleared.
			if ((!ends.srcHostid && !ends.tgtHostid) || (!link.source_port && !link.target_port)) {
				return;
			}

			// Keep the line style/color the user set on this link (reused by triggerid)
			var previous = {};

			(link.linktriggers || []).forEach(function (lt) {
				if (lt && lt.triggerid) {
					previous[String(lt.triggerid)] = lt;
				}
			});

			api('linkTriggers', {
				hostid1: ends.srcHostid,
				hostid2: ends.tgtHostid,
				port1: link.source_port || '',
				port2: link.target_port || '',
				trigger_source: num(link.trigger_source, 0)
			}).then(function (res) {
				var triggers = (res && res.triggers) ? res.triggers : [];

				link.linktriggers = triggers.map(function (trigger) {
					var tid = String(trigger.triggerid);
					var old = previous[tid] || {};

					return {
						triggerid: tid,
						drawtype: num(old.drawtype, 0),
						color: String(old.color || DEFAULT_TRIGGER_COLOR).replace('#', '')
					};
				});

				markDirty();
				renderCanvas();
			}).catch(function () {
				// Re-match failures do not interrupt editing; the user can click "Apply" again later
			});
		}

		// Sum of the latest values of a group of items (aggregated member ports are summed); null if no numeric value is available.
		// units comes from the first item that has units.
		function sumMetricItems(itemids) {
			var items = (state.map && state.map.metric_items) || {};
			var total = null;
			var units = '';

			(itemids || []).forEach(function (itemid) {
				var item = items[String(itemid)];

				if (!item) {
					return;
				}

				var n = parseFloat(item.lastvalue);

				if (isNaN(n)) {
					return;
				}

				total = (total === null ? 0 : total) + n;

				if (!units && item.units) {
					units = String(item.units);
				}
			});

			return total === null ? null : {value: total, units: units};
		}

		function formatBps(n) {
			if (n === null || n === undefined || isNaN(n)) {
				return '-';
			}

			if (n < 1000) {
				return Math.round(n) + ' bps';
			}

			if (n < 1000000) {
				return (n / 1000).toFixed(1) + ' Kbps';
			}

			if (n < 1000000000) {
				return (n / 1000000).toFixed(1) + ' Mbps';
			}

			return (n / 1000000000).toFixed(2) + ' Gbps';
		}

		function trimNum(n) {
			return Math.abs(n - Math.round(n)) < 1e-9 ? String(Math.round(n)) : String(Math.round(n * 100) / 100);
		}

		/*
		 * Metric value → display text.
		 * Units of bps (lowercase b, bits per second) are converted as rates; Bps / B / bytes / octets (bytes)
		 * are first multiplied by 8 into bits per second and then converted; other units (%, count, s…) show the value + unit as is.
		 */
		function metricText(res) {
			if (!res) {
				return '-';
			}

			var units = res.units || '';

			if (/bps/.test(units)) {
				return formatBps(res.value);
			}

			if (/^Bps$|^B\/s$|bytes|octets|^B$/.test(units)) {
				return formatBps(res.value * 8);
			}

			return trimNum(res.value) + (units ? ' ' + units : '');
		}

		// Small text drawn on links: label + configured metrics ("-" when data is unavailable).
		function linkMetricLines(link) {
			var m = normalizeMetrics(link.metrics);
			var lines = [];

			METRIC_KINDS.forEach(function (kind) {
				if (!m[kind.key].length) {
					return;
				}

				lines.push({
					text: kind.prefix + ' ' + metricText(sumMetricItems(m[kind.key])),
					color: kind.color
				});
			});

			return lines;
		}

		// If any trigger bound to a link is in problem state, the link is drawn in the trigger's own color (DD0000 for auto discovery);
		// otherwise the link keeps its own color (00CC00 for auto discovery, the user's choice for manual links).
		function linkProblemColor(link) {
			var list = link.linktriggers || [];

			for (var i = 0; i < list.length; i++) {
				if (list[i] && list[i].problem) {
					return String(list[i].color || DEFAULT_TRIGGER_COLOR);
				}
			}

			return '';
		}

		// Unordered key of an element pair (direction-independent): all links between the same pair belong to one group.
		function linkPairKey(link) {
			var a = String(link.selementid1);
			var b = String(link.selementid2);

			return a < b ? a + '|' + b : b + '|' + a;
		}

		/*
		 * All links between the same pair of elements (members of a link aggregation, duplicate bidirectional LLDP links) count as one link:
		 * they share one configuration (label / metrics / trigger source), clicking any of them opens the same "link" dialog,
		 * and editing and removal apply to the whole group — as aiops does, so users do not have to edit them one by one.
		 * The lines themselves are still drawn per member port as a close bundle of curves, preserving the visual aggregation information.
		 */
		function linkFamily(link) {
			var key = linkPairKey(link);
			var list = (state.map && state.map.links) || [];
			var family = [];

			list.forEach(function (item) {
				if (linkPairKey(item) === key) {
					family.push(item);
				}
			});

			return family.length ? family : [link];
		}

		// Metric items of the whole group: merge and deduplicate the settings of all member ports (old data may be spread across links).
		function familyMetrics(family) {
			var out = normalizeMetrics(null);
			var seen = {};

			family.forEach(function (link) {
				var m = normalizeMetrics(link.metrics);

				METRIC_KINDS.forEach(function (kind) {
					m[kind.key].forEach(function (id) {
						if (!seen[id]) {
							seen[id] = true;
							out[kind.key].push(id);
						}
					});
				});
			});

			return out;
		}

		// Group label: the first non-empty value (the group shares one label)
		function familyLabel(family) {
			for (var i = 0; i < family.length; i++) {
				if (family[i].label) {
					return String(family[i].label);
				}
			}

			return '';
		}

		// Bound triggers of the group: merged across all members (deduplicated by triggerid).
		// In native maps, parallel links between the same pair of elements are independent, each possibly with different link indicators;
		// taking only the first would swallow the triggers of the others (the cause of the "several linked but only 1 shown" report).
		function familyLinkTriggers(family) {
			var out = [];
			var seen = {};

			family.forEach(function (link) {
				(link.linktriggers || []).forEach(function (lt) {
					var tid = String(lt && lt.triggerid ? lt.triggerid : '');

					if (!tid || seen[tid]) {
						return;
					}

					seen[tid] = true;
					out.push(copy(lt));
				});
			});

			return out;
		}

		// Port names of the group (several member ports when aggregated): listed in the "link" dialog to confirm which ports the link covers.
		// Port names are no longer drawn on the links (removed at the user's request) and are only shown as information in the dialog.
		function familyPorts(family) {
			var src = [];
			var tgt = [];

			family.forEach(function (link) {
				var s = String(link.source_port || '');
				var t = String(link.target_port || '');

				if (s && src.indexOf(s) === -1) {
					src.push(s);
				}

				if (t && tgt.indexOf(t) === -1) {
					tgt.push(t);
				}
			});

			return {src: src, tgt: tgt};
		}

		// Remove the whole group of links (all member ports of a link aggregation disappear together)
		function removeLinkFamily(link) {
			var family = linkFamily(link);

			state.map.links = state.map.links.filter(function (item) {
				return family.indexOf(item) === -1;
			});
			markDirty();
			renderCanvas();
		}

		// ---------------------------------------------------------------
		// Routing / waypoints
		// ---------------------------------------------------------------

		var BEND_HINT = T('Waypoint mode: click empty space to add a point, drag dots to adjust, double-click a dot to delete, Esc to finish');

		// Automatic orthogonal routing: clearance from obstacles (other element icons + label text boxes),
		// the cost of each turn (higher means fewer turns), and how far around the link obstacles are considered
		var ORTHO_GAP = 18;
		var ORTHO_PAD = 8;
		var ORTHO_BEND_COST = 26;
		var ORTHO_MARGIN = 220;

		// Estimate the rectangle an element occupies: the icon itself + the label text box (including status lines),
		// so automatic orthogonal routing goes around icons and text instead of through them.
		function elementObstacleRect(se) {
			var size = iconSize(iconForElement(se));
			var pad = ORTHO_PAD;
			var cx = se.x + size.w / 2;
			var cy = se.y + size.h / 2;
			var x1 = se.x - pad;
			var x2 = se.x + size.w + pad;
			var y1 = se.y - pad;
			var y2 = se.y + size.h + pad;

			var nameLines = elementLabel(se) ? String(elementLabel(se)).split('\n') : [];
			var statusLines = elementStatusLines(se) || [];
			var count = nameLines.length + statusLines.length;

			if (count) {
				var lineW = 0;

				nameLines.forEach(function (line) {
					lineW = Math.max(lineW, textWidth(line, 12));
				});
				statusLines.forEach(function (line) {
					lineW = Math.max(lineW, textWidth(line.text || '', 12));
				});

				var textW = lineW + 10;
				var textH = count * 16;
				var loc = se.label_location === -1 && state.map ? state.map.label_location : se.label_location;

				if (loc === 3) {
					y1 = Math.min(y1, se.y - 4 - textH - pad);
					x1 = Math.min(x1, cx - textW / 2 - pad);
					x2 = Math.max(x2, cx + textW / 2 + pad);
				}
				else if (loc === 1) {
					x1 = Math.min(x1, se.x - 4 - textW - pad);
					y1 = Math.min(y1, cy - textH / 2 - pad);
					y2 = Math.max(y2, cy + textH / 2 + pad);
				}
				else if (loc === 2) {
					x2 = Math.max(x2, se.x + size.w + 4 + textW + pad);
					y1 = Math.min(y1, cy - textH / 2 - pad);
					y2 = Math.max(y2, cy + textH / 2 + pad);
				}
				else {
					y2 = Math.max(y2, se.y + size.h + 3 + textH + pad);
					x1 = Math.min(x1, cx - textW / 2 - pad);
					x2 = Math.max(x2, cx + textW / 2 + pad);
				}
			}

			return {x: x1, y: y1, w: x2 - x1, h: y2 - y1, owner: String(se.selementid)};
		}

		function collectLinkObstacles(map) {
			var out = [];

			(map.selements || []).forEach(function (se) {
				out.push(elementObstacleRect(se));
			});

			return out;
		}

		function uniqueSorted(values) {
			var sorted = values.slice().sort(function (a, b) {
				return a - b;
			});
			var out = [];

			sorted.forEach(function (value) {
				if (!out.length || Math.abs(value - out[out.length - 1]) > 0.01) {
					out.push(value);
				}
			});

			return out;
		}

		// Whether a segment crosses any obstacle rectangle (horizontal/vertical segments are checked by bounding box, which is enough and fast)
		function orthoSegmentFree(x1, y1, x2, y2, obstacles) {
			var minX = Math.min(x1, x2);
			var maxX = Math.max(x1, x2);
			var minY = Math.min(y1, y2);
			var maxY = Math.max(y1, y2);

			for (var i = 0; i < obstacles.length; i++) {
				var o = obstacles[i];

				if (o.x < maxX && o.x + o.w > minX && o.y < maxY && o.y + o.h > minY) {
					return false;
				}
			}

			return true;
		}

		function routeHeapPush(heap, item) {
			heap.push(item);

			var i = heap.length - 1;

			while (i > 0) {
				var p = (i - 1) >> 1;

				if (heap[p].f <= heap[i].f) {
					break;
				}

				var t = heap[p];
				heap[p] = heap[i];
				heap[i] = t;
				i = p;
			}
		}

		function routeHeapPop(heap) {
			var top = heap[0];
			var last = heap.pop();

			if (heap.length) {
				heap[0] = last;

				var i = 0;

				for (;;) {
					var l = i * 2 + 1;
					var r = l + 1;
					var m = i;

					if (l < heap.length && heap[l].f < heap[m].f) {
						m = l;
					}
					if (r < heap.length && heap[r].f < heap[m].f) {
						m = r;
					}
					if (m === i) {
						break;
					}

					var t = heap[m];
					heap[m] = heap[i];
					heap[i] = t;
					i = m;
				}
			}

			return top;
		}

		// Merge duplicate points and remove intermediate points on the same straight line
		function compressRoute(points) {
			var out = [];

			points.forEach(function (point) {
				var last = out[out.length - 1];

				if (last && Math.abs(last.x - point.x) < 0.01 && Math.abs(last.y - point.y) < 0.01) {
					return;
				}

				out.push({x: point.x, y: point.y});
			});

			var clean = [out[0]];

			for (var i = 1; i < out.length - 1; i++) {
				var a = clean[clean.length - 1];
				var b = out[i];
				var c = out[i + 1];
				var collinear = (Math.abs(a.x - b.x) < 0.01 && Math.abs(b.x - c.x) < 0.01)
					|| (Math.abs(a.y - b.y) < 0.01 && Math.abs(b.y - c.y) < 0.01);

				if (!collinear) {
					clean.push(b);
				}
			}

			if (out.length > 1) {
				clean.push(out[out.length - 1]);
			}

			return clean;
		}

		// Orthogonal A*: find a horizontal/vertical path around obstacles along their borders and the channels outside them.
		// If none is found, fall back to the original "one bend at the midpoint" path.
		function orthogonalRoute(pa, pb, obstacles) {
			var near = [];
			var left = Math.min(pa.x, pb.x) - ORTHO_MARGIN;
			var right = Math.max(pa.x, pb.x) + ORTHO_MARGIN;
			var top = Math.min(pa.y, pb.y) - ORTHO_MARGIN;
			var bottom = Math.max(pa.y, pb.y) + ORTHO_MARGIN;

			obstacles.forEach(function (o) {
				if (o.x >= right || o.x + o.w <= left || o.y >= bottom || o.y + o.h <= top) {
					return;
				}

				// When the start/end lies inside an obstacle (overlapping elements), ignore that obstacle, otherwise there is never a solution
				var hasPa = pa.x >= o.x && pa.x <= o.x + o.w && pa.y >= o.y && pa.y <= o.y + o.h;
				var hasPb = pb.x >= o.x && pb.x <= o.x + o.w && pb.y >= o.y && pb.y <= o.y + o.h;

				if (!hasPa && !hasPb) {
					near.push(o);
				}
			});

			function midRoute() {
				var dx = pb.x - pa.x;
				var dy = pb.y - pa.y;

				if (Math.abs(dx) >= Math.abs(dy)) {
					var mx = (pa.x + pb.x) / 2;

					return [{x: pa.x, y: pa.y}, {x: mx, y: pa.y}, {x: mx, y: pb.y}, {x: pb.x, y: pb.y}];
				}

				var my = (pa.y + pb.y) / 2;

				return [{x: pa.x, y: pa.y}, {x: pa.x, y: my}, {x: pb.x, y: my}, {x: pb.x, y: pb.y}];
			}

			if (!near.length) {
				return midRoute();
			}

			var xs = [pa.x, pb.x];
			var ys = [pa.y, pb.y];
			var minX = Infinity;
			var maxX = -Infinity;
			var minY = Infinity;
			var maxY = -Infinity;

			near.forEach(function (o) {
				xs.push(Math.max(0, o.x - ORTHO_GAP), o.x + o.w + ORTHO_GAP);
				ys.push(Math.max(0, o.y - ORTHO_GAP), o.y + o.h + ORTHO_GAP);
				minX = Math.min(minX, o.x);
				maxX = Math.max(maxX, o.x + o.w);
				minY = Math.min(minY, o.y);
				maxY = Math.max(maxY, o.y + o.h);
			});

			xs.push(Math.max(0, minX - ORTHO_GAP), maxX + ORTHO_GAP);
			ys.push(Math.max(0, minY - ORTHO_GAP), maxY + ORTHO_GAP);

			xs = uniqueSorted(xs);
			ys = uniqueSorted(ys);

			var nx = xs.length;
			var ny = ys.length;
			var starts = [];

			xs.forEach(function (x, xi) {
				if (Math.abs(x - pa.x) < 0.01) {
					starts.push({xi: xi, x: x});
				}
			});

			var startYi = -1;
			var endXi = -1;
			var endYi = -1;

			ys.forEach(function (y, yi) {
				if (Math.abs(y - pa.y) < 0.01) {
					startYi = yi;
				}
				if (Math.abs(y - pb.y) < 0.01) {
					endYi = yi;
				}
			});
			xs.forEach(function (x, xi) {
				if (Math.abs(x - pb.x) < 0.01) {
					endXi = xi;
				}
			});

			var goalXi = xs.length - 1;
			var goalYi = ys.length - 1;

			endXi = Math.max(0, endXi);
			endYi = Math.max(0, endYi);

			if (startYi < 0 || xs.length < 2 || ys.length < 2) {
				return midRoute();
			}

			var startXi = 0;

			xs.forEach(function (x, xi) {
				if (Math.abs(x - pa.x) < 0.01) {
					startXi = xi;
				}
			});

			// State = grid point × entry direction (0 start / 1 horizontal / 2 vertical)
			var total = nx * ny;
			var g = [];
			var prev = [];

			for (var s = 0; s < total * 3; s++) {
				g.push(Infinity);
				prev.push(-1);
			}

			var heap = [];
			var startState = (startYi * nx + startXi) * 3;

			g[startState] = 0;
			routeHeapPush(heap, {state: startState, f: Math.abs(pa.x - pb.x) + Math.abs(pa.y - pb.y)});

			var moves = [[-1, 0, 1], [1, 0, 1], [0, -1, 2], [0, 1, 2]];
			var bestState = -1;
			var guard = 0;

			while (heap.length && guard++ < 20000) {
				var item = routeHeapPop(heap);
				var state = item.state;
				var node = Math.floor(state / 3);
				var dir = state % 3;
				var xi = node % nx;
				var yi = Math.floor(node / nx);

				if (xi === endXi && yi === endYi) {
					bestState = state;
					break;
				}

				for (var m = 0; m < moves.length; m++) {
					var xi2 = xi + moves[m][0];
					var yi2 = yi + moves[m][1];
					var dir2 = moves[m][2];

					if (xi2 < 0 || xi2 >= nx || yi2 < 0 || yi2 >= ny) {
						continue;
					}

					var x2 = xs[xi2];
					var y2 = ys[yi2];

					if (!orthoSegmentFree(xs[xi], ys[yi], x2, y2, near)) {
						continue;
					}

					var cost = Math.abs(x2 - xs[xi]) + Math.abs(y2 - ys[yi]);

					if (dir !== 0 && dir !== dir2) {
						cost += ORTHO_BEND_COST;
					}

					var state2 = (yi2 * nx + xi2) * 3 + dir2;
					var g2 = g[state] + cost;

					if (g2 < g[state2]) {
						g[state2] = g2;
						prev[state2] = state;
						routeHeapPush(heap, {
							state: state2,
							f: g2 + Math.abs(xs[xi2] - pb.x) + Math.abs(ys[yi2] - pb.y)
						});
					}
				}
			}

			if (bestState < 0) {
				return midRoute();
			}

			var seq = [];
			var cur = bestState;

			while (cur !== -1) {
				var node2 = Math.floor(cur / 3);

				seq.push({
					x: xs[node2 % nx],
					y: ys[Math.floor(node2 / nx)]
				});
				cur = prev[cur];
			}

			seq.reverse();

			if (!seq.length || Math.abs(seq[0].x - pa.x) > 0.01 || Math.abs(seq[0].y - pa.y) > 0.01) {
				seq.unshift({x: pa.x, y: pa.y});
			}

			seq.push({x: pb.x, y: pb.y});

			return compressRoute(seq);
		}

		// Polyline points: routing 1 = automatic orthogonal (around icons / text boxes), routing 2 = manual waypoints.
		// Returns null to draw the original quadratic Bézier (routing 0).
		// off is the vertical offset of link aggregation members, applied only to intermediate points/waypoints; both ends stay at the element centers.
		function linkPolyline(link, pa, pb, nx, ny, off, obstacles) {
			var routing = num(link.routing, 0);
			var points;

			if (routing === 1) {
				points = orthogonalRoute(pa, pb, obstacles || []);

				if (off) {
					points = points.map(function (point, i) {
						if (i === 0 || i === points.length - 1) {
							return point;
						}

						return {x: point.x + nx * off, y: point.y + ny * off};
					});
				}

				return points;
			}

			if (routing === 2) {
				points = [{x: pa.x, y: pa.y}];

				normalizeWaypointList(link.waypoints).forEach(function (wp) {
					points.push({x: wp.x + nx * off, y: wp.y + ny * off});
				});

				points.push({x: pb.x, y: pb.y});

				return points;
			}

			return null;
		}

		function polylinePath(points) {
			var d = '';

			points.forEach(function (point, i) {
				d += (i ? ' L ' : 'M ') + point.x + ' ' + point.y;
			});

			return d;
		}

		// Midpoint of the total polyline length: the label / metric box goes here rather than at the midpoint of the ends
		function polylineMidpoint(points) {
			if (!points.length) {
				return {x: 0, y: 0};
			}

			var lengths = [];
			var total = 0;

			for (var i = 1; i < points.length; i++) {
				var dx = points[i].x - points[i - 1].x;
				var dy = points[i].y - points[i - 1].y;
				var len = Math.sqrt(dx * dx + dy * dy);

				lengths.push(len);
				total += len;
			}

			if (total <= 0) {
				return {x: points[0].x, y: points[0].y};
			}

			var remain = total / 2;

			for (var j = 0; j < lengths.length; j++) {
				if (remain <= lengths[j] || j === lengths.length - 1) {
					var t = lengths[j] > 0 ? Math.max(0, Math.min(1, remain / lengths[j])) : 0;

					return {
						x: points[j].x + (points[j + 1].x - points[j].x) * t,
						y: points[j].y + (points[j + 1].y - points[j].y) * t
					};
				}

				remain -= lengths[j];
			}

			return {x: points[points.length - 1].x, y: points[points.length - 1].y};
		}

		// The group of links whose waypoints are being edited (the whole aggregation group shares one set of waypoints)
		function bendFamily() {
			if (!state.bendLink) {
				return [];
			}

			var family = linkFamily(state.bendLink);

			return family.length ? family : [state.bendLink];
		}

		// Write the same waypoints to every member link of the group (each link stores its own copy, saved by linkid)
		function setFamilyWaypoints(family, points) {
			family.forEach(function (link) {
				link.routing = 2;
				link.waypoints = points.map(function (point) {
					return {x: point.x, y: point.y};
				});
			});
		}

		function startWaypointDrag(index, e) {
			var family = bendFamily();

			if (!family.length) {
				return;
			}

			var start = pointerToMap(e);
			var origin = family[0].waypoints[index];

			if (!origin) {
				return;
			}

			// On mousedown the waypoint coordinates are copied into a numeric snapshot: move rewrites waypoints,
			// and if the origin object were still referenced, each frame's delta would add up on already moved coordinates,
			// making the waypoint fly away with the slightest mouse movement.
			var originX = Number(origin.x) || 0;
			var originY = Number(origin.y) || 0;
			var moved = false;

			function move(ev) {
				var pt = pointerToMap(ev);
				var nx = Math.max(0, Math.round(originX + pt.x - start.x));
				var ny = Math.max(0, Math.round(originY + pt.y - start.y));

				family.forEach(function (link) {
					if (!link.waypoints || !link.waypoints[index]) {
						return;
					}

					link.waypoints[index].x = nx;
					link.waypoints[index].y = ny;
				});

				moved = true;
				drawLinks(state.map);
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		function removeWaypointAt(index) {
			var family = bendFamily();

			family.forEach(function (link) {
				if (link.waypoints && link.waypoints[index]) {
					link.waypoints.splice(index, 1);
				}
			});

			markDirty();
			drawLinks(state.map);
		}

		function exitBendMode() {
			if (!state.bendLink) {
				return false;
			}

			state.bendLink = null;
			setHint(state.dirty ? T('Unsaved changes') : (state.editing ? '' : T('Preview mode')));
			drawLinks(state.map);

			return true;
		}

		// Estimate SVG text width (good enough): full-width characters (CJK etc.) count as the font size, half-width as 0.6 of it.
		function textWidth(text, size) {
			var w = 0;

			for (var i = 0; i < text.length; i++) {
				w += text.charCodeAt(i) > 0x2E80 ? size : size * 0.6;
			}

			return w;
		}

		// Highlight layer: native SVG maps are layered shapes → highlights → links → elements.
		// It is a separate layer fixed after all shapes and before all links, so redrawing does not move it above the links.
		var hlLayer = null;

		function highlightLayer() {
			if (!hlLayer || hlLayer.parentNode !== elSvg) {
				hlLayer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				hlLayer.setAttribute('class', 'tm-hl-layer');
				hlLayer.setAttribute('pointer-events', 'none');
				elSvg.appendChild(hlLayer);
			}

			// Shapes are redrawn at the end, so the highlight layer is moved back after all shapes and before the first link
			var firstLink = elSvg.querySelector('.tm-link-group');

			elSvg.insertBefore(hlLayer, firstLink || null);

			return hlLayer;
		}

		/**
		 * Element highlight (native sysmaps.highlight).
		 *
		 * Problems are drawn as a true circle: native uses an ellipse with rx = ry = floor(icon width/2)+10 centered on the element,
		 * so it stays a circle even when the icon is not square. It used to be drawn with CSS percentages (+20px width/height, border-radius 50%),
		 * which turned into an ellipse for slightly taller icons.
		 */
		function drawHighlights(map) {
			var layer = highlightLayer();

			clear(layer);

			map.selements.forEach(function (se) {
				var hl = highlightForElement(se);

				if (!hl) {
					return;
				}

				var size = iconSize(iconForElement(se));
				var node;

				if (hl.st) {
					node = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
					node.setAttribute('x', String(se.x - 2));
					node.setAttribute('y', String(se.y - 2));
					node.setAttribute('width', String(size.w + 4));
					node.setAttribute('height', String(size.h + 4));
					node.setAttribute('fill', '#' + hl.st);
					node.setAttribute('fill-opacity', '0.5');
				}
				else {
					var radius = Math.floor(size.w / 2) + 10;

					node = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
					node.setAttribute('cx', String(se.x + size.w / 2));
					node.setAttribute('cy', String(se.y + size.h / 2));
					node.setAttribute('rx', String(radius));
					node.setAttribute('ry', String(radius));
					node.setAttribute('fill', '#' + hl.hl);

					if (hl.ack) {
						node.setAttribute('stroke', '#329632');
						node.setAttribute('stroke-width', '4');
					}
				}

				node.setAttribute('class', 'tm-hl');
				node.setAttribute('data-hl-id', String(se.selementid));
				node.setAttribute('pointer-events', 'none');
				layer.appendChild(node);
			});
		}

		// While dragging only this one highlight moves (the layer is not rebuilt, so the highlight does not flicker during the drag)
		function syncHighlight(se) {
			var node = hlLayer && hlLayer.parentNode === elSvg
				? hlLayer.querySelector('[data-hl-id="' + se.selementid + '"]')
				: null;

			if (!node) {
				return;
			}

			var size = iconSize(iconForElement(se));

			if (node.tagName.toLowerCase() === 'rect') {
				node.setAttribute('x', String(se.x - 2));
				node.setAttribute('y', String(se.y - 2));
				node.setAttribute('width', String(size.w + 4));
				node.setAttribute('height', String(size.h + 4));

				return;
			}

			node.setAttribute('cx', String(se.x + size.w / 2));
			node.setAttribute('cy', String(se.y + size.h / 2));
			node.setAttribute('rx', String(Math.floor(size.w / 2) + 10));
			node.setAttribute('ry', String(Math.floor(size.w / 2) + 10));
		}

		function drawLinks(map) {
			var nodes = elSvg.querySelectorAll('.tm-link-group, .tm-link-badge-group, .tm-waypoint-group');

			for (var i = 0; i < nodes.length; i++) {
				elSvg.removeChild(nodes[i]);
			}

			var byId = {};

			map.selements.forEach(function (se) {
				byId[String(se.selementid)] = se;
			});

			// There may be several links between the same pair of elements (members of a link aggregation, duplicate bidirectional links);
			// straight center-to-center lines would overlap completely. They are grouped by element pair,
			// each line in the group gets a symmetric perpendicular offset and is drawn as a quadratic Bézier curve to spread them,
			// and labels follow to the curve midpoint.
			var pairCount = {};
			var pairSeq = {};
			// Label boxes are collected and inserted at the end: SVG stacks in insertion order, so later inserts are on top.
			// Aggregated links are only 12px apart; if boxes were inserted while drawing each line, later member lines would cross the boxes.
			var pendingBadges = [];

			map.links.forEach(function (link) {
				if (!byId[String(link.selementid1)] || !byId[String(link.selementid2)]) {
					return;
				}

				var countKey = linkPairKey(link);
				pairCount[countKey] = (pairCount[countKey] || 0) + 1;
			});

			// Obstacles for automatic orthogonal routing: icons + label text boxes of other elements on the canvas.
			// Computed once per map; each link then removes its own two ends (the start/end elements must not block the link).
			var linkObstacles = collectLinkObstacles(map);

			// Tree layout: how many child elements hang under each parent (deduplicated by element pair; aggregation members count as one).
			// With only 1 child the link is straight; with several, automatic orthogonal routing branches from a shared trunk.
			var treeChildren = {};

			map.links.forEach(function (link) {
				var a = byId[String(link.selementid1)];
				var b = byId[String(link.selementid2)];

				if (!a || !b || sameRowElements(a, b)) {
					return;
				}

				var parent = b.y > a.y ? a : b;
				var pid = String(parent.selementid);

				treeChildren[pid] = treeChildren[pid] || {};
				treeChildren[pid][linkPairKey(link)] = true;
			});

			map.links.forEach(function (link) {
				var a = byId[String(link.selementid1)];
				var b = byId[String(link.selementid2)];

				if (!a || !b) {
					return;
				}

				// Links between levels go from below the parent's text block to above the child's icon; links on the same level still use centers
				var anchors = elementAnchors(a, b);
				var pa = anchors.pa;
				var pb = anchors.pb;
				var color = '#' + String(linkProblemColor(link) || link.color || DEFAULT_LINK_COLOR).replace('#', '');
				var width = link.drawtype === 2 ? 4 : 1.6;
				var dash = link.drawtype === 3 ? '2 4' : (link.drawtype === 4 ? '6 4' : '');

				// Index within the group → perpendicular offset; a single link has offset 0, equivalent to the original straight line.
				var pairKey = linkPairKey(link);
				var total = pairCount[pairKey] || 1;
				var index = pairSeq[pairKey] || 0;
				pairSeq[pairKey] = index + 1;
				// The badge is drawn only on the middle link of the group (a single link is its own middle)
				var midIndex = Math.floor((total - 1) / 2);

				var dx = pb.x - pa.x;
				var dy = pb.y - pa.y;
				var len = Math.sqrt(dx * dx + dy * dy) || 1;
				// Unit vector perpendicular to the link
				var nx = -dy / len;
				var ny = dx / len;
				var midX = (pa.x + pb.x) / 2;
				var midY = (pa.y + pb.y) / 2;
				var off = (index - (total - 1) / 2) * LINK_PAIR_SPACING;
				// Routing mode: 0 automatic curve (quadratic Bézier) / 1 automatic orthogonal / 2 manual waypoints.
				// Polylines only apply the aggregation offset to intermediate points / waypoints; both ends stay on the anchors.
				var ownA = String(link.selementid1);
				var ownB = String(link.selementid2);
				var obstacles = linkObstacles.filter(function (o) {
					return o.owner !== ownA && o.owner !== ownB;
				});
				var routing = num(link.routing, 0);
				var polyline;
				// With tree orthogonal routing the metric box is not placed at the routing midpoint (it would land on the horizontal trunk and not show which host it belongs to),
				// but right above the child icon
				var badgeAnchor = null;

				if (anchors.vertical && routing === 1) {
					// Tree orthogonal: from the parent anchor vertically to the trunk, horizontally to above each child, then vertically down to the child anchor.
					// With only 1 child, a straight line; the member ports of an aggregation are offset identically at both ends and run side by side
					// as two parallel vertical lines (previously only the midpoint was offset and both ends converged into an ugly sharp corner).
					var childCount = Object.keys(treeChildren[String(anchors.parent.selementid)] || {}).length;
					var treeOff = treeMemberOffset(index, total, pb.x - pa.x);
					var from = {x: pa.x + treeOff, y: pa.y};
					var to = {x: pb.x + treeOff, y: pb.y};
					// Horizontal segments of group members are shifted by one line spacing so they do not overlap into one
					var railOff = (index - (total - 1) / 2) * LINK_PAIR_SPACING;

					// Only parents with several children branch with orthogonal routing;
					// with only 1 child the link is straight (aggregation members are several parallel straight lines;
					// more than one member port must not force a right angle).
					polyline = childCount > 1 ? treeRoute(from, to, railOff) : [from, to];
					// The metric box sits at the midpoint of the last (vertical) segment, centered right above the child
					var tail = polyline[polyline.length - 1];
					var prev = polyline[polyline.length - 2];
					badgeAnchor = {x: pb.x, y: (prev.y + tail.y) / 2};
				}
				else {
					polyline = linkPolyline(link, pa, pb, nx, ny, off, obstacles);
				}
				var pathData;
				var labelX;
				var labelY;

				if (polyline) {
					pathData = polylinePath(polyline);

					var lineMid = polylineMidpoint(polyline);

					labelX = lineMid.x;
					labelY = lineMid.y;
				}
				else {
					// Quadratic Bézier control point offset off*2: the curve point at t=0.5 lands exactly on midpoint + perpendicular offset off
					var cx = midX + nx * off * 2;
					var cy = midY + ny * off * 2;

					pathData = 'M ' + pa.x + ' ' + pa.y + ' Q ' + cx + ' ' + cy + ' ' + pb.x + ' ' + pb.y;
					// The group draws only one label box, on the middle link (t=0.5, i.e. the curve midpoint):
					// aggregated links share one configuration; labels drawn per link would only overlap and could not be edited separately.
					var t = 0.5;
					var it = 1 - t;

					labelX = it * it * pa.x + 2 * it * t * cx + t * t * pb.x;
					labelY = it * it * pa.y + 2 * it * t * cy + t * t * pb.y;
				}

				var group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				group.setAttribute('class', 'tm-link-group');

				var line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
				line.setAttribute('class', 'tm-link-line');
				line.setAttribute('d', pathData);
				line.setAttribute('stroke', color);
				line.setAttribute('stroke-width', String(width));

				if (dash) {
					line.setAttribute('stroke-dasharray', dash);
				}

				group.appendChild(line);

				var hit = document.createElementNS('http://www.w3.org/2000/svg', 'path');
				hit.setAttribute('class', 'tm-link-hit');
				hit.setAttribute('d', pathData);

				if (state.editing && state.removeMode === 'link') {
					hit.classList.add('tm-removable');
				}

				hit.addEventListener('mousedown', function (e) {
					e.stopPropagation();
				});

				hit.addEventListener('click', function (e) {
					if (!state.editing) {
						return;
					}

					e.stopPropagation();

					if (state.removeMode === 'link') {
						removeLinkFamily(link);
						return;
					}

					// Clicking any link of the group opens the same "link" dialog: the whole group shares one configuration
					if (!state.removeMode && !state.linkMode) {
						openLinkDialog(link, false);
					}
				});

				group.appendChild(hit);

				// The group draws only one label box (on the middle link): label + bandwidth / upstream / downstream.
				// Metrics are the union of the settings of the whole group (aggregated member ports); the end ports are no longer labeled on the links.
				if (index !== midIndex) {
					elSvg.appendChild(group);

					return;
				}

				var lines = [];
				var badgeGroup = null;
				var groupLabel = familyLabel(linkFamily(link));

				if (groupLabel) {
					lines.push({text: groupLabel, metric: false});
				}

				linkMetricLines({metrics: familyMetrics(linkFamily(link))}).forEach(function (ml) {
					lines.push({text: ml.text, metric: true});
				});

				if (lines.length) {
					// Label + metrics are drawn in one rounded box (aligned with aiops): light background, black text.
					// The light background keeps black text readable in the dark theme as well.
					var step = 13;
					var padX = 6;
					var boxW = 0;

					lines.forEach(function (line) {
						boxW = Math.max(boxW, textWidth(line.text, 11));
					});

					boxW += padX * 2;

					var boxH = (lines.length - 1) * step + 20;

					if (badgeAnchor) {
						// Tree orthogonal: the box is centered right above the child, vertically at the midpoint of the segment "trunk bend → child anchor";
						// when the child moves down or level spacing grows the segment gets longer and the box re-centers automatically (aligned with built-in Zabbix maps)
						labelX = badgeAnchor.x;
						labelY = badgeAnchor.y;
					}

					var startY = labelY - boxH / 2 + 14;   // Baseline of the first text line

					// Boxes go into a separate "badge layer": added after all links of the group are in the SVG
					badgeGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
					badgeGroup.setAttribute('class', 'tm-link-badge-group');

					var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
					rect.setAttribute('class', 'tm-link-badge');
					rect.setAttribute('x', String(labelX - boxW / 2));
					rect.setAttribute('y', String(labelY - boxH / 2));
					rect.setAttribute('width', String(boxW));
					rect.setAttribute('height', String(boxH));
					rect.setAttribute('rx', '5');
					rect.setAttribute('ry', '5');
					rect.setAttribute('fill', isDark ? '#f4f7f8' : '#ffffff');
					// The border follows the link color: green when normal, red when down/alarming
					rect.setAttribute('stroke', color);
					rect.setAttribute('stroke-width', '1.5');
					// The box sits over the link's hit area and must not receive pointer events, otherwise clicking the link would not open the "link" dialog
					rect.setAttribute('pointer-events', 'none');
					badgeGroup.appendChild(rect);

					lines.forEach(function (line, i) {
						var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
						var cls = 'tm-link-label';

						if (line.metric) {
							cls += ' tm-link-metric';
						}

						text.setAttribute('class', cls);
						text.setAttribute('x', String(labelX));
						text.setAttribute('y', String(startY + i * step));
						text.setAttribute('fill', '#000000');
						text.setAttribute('pointer-events', 'none');
						text.textContent = line.text;
						badgeGroup.appendChild(text);
					});
				}

				elSvg.appendChild(group);

				// Deferred box insertion: added after all links of the group are in the SVG (SVG stacks in insertion order, there is no z-index)
				if (badgeGroup) {
					pendingBadges.push(badgeGroup);
				}
			});

			pendingBadges.forEach(function (badgeGroup) {
				elSvg.appendChild(badgeGroup);
			});

			// Waypoint handles: drawn only in waypoint editing mode, on the top layer (they must cover links and boxes for dragging/double-clicking)
			if (state.bendLink) {
				var family = bendFamily();
				var points = (family[0] && family[0].waypoints) || [];

				if (points.length) {
					var bendGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
					bendGroup.setAttribute('class', 'tm-waypoint-group');

					points.forEach(function (point, index) {
						var dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
						dot.setAttribute('class', 'tm-waypoint');
						dot.setAttribute('cx', String(point.x));
						dot.setAttribute('cy', String(point.y));
						dot.setAttribute('r', '5');
						dot.setAttribute('data-index', String(index));

						dot.addEventListener('mousedown', function (e) {
							e.preventDefault();
							e.stopPropagation();
							startWaypointDrag(index, e);
						});

						dot.addEventListener('dblclick', function (e) {
							e.preventDefault();
							e.stopPropagation();
							removeWaypointAt(index);
						});

						bendGroup.appendChild(dot);
					});

					elSvg.appendChild(bendGroup);
				}
			}
		}

		function renderElement(se) {
			var box = make('div', 'tm-el');
			var size = iconSize(iconForElement(se));

			box.setAttribute('data-id', String(se.selementid));

			if (state.linkMode) {
				box.classList.add('tm-linkable');
			}

			if (state.editing && state.removeMode === 'element') {
				box.classList.add('tm-removable');
			}

			var img = make('img', 'tm-el-icon');
			img.src = iconUrl(iconForElement(se));
			img.width = size.w;
			img.height = size.h;
			img.alt = '';
			img.draggable = false;
			img.addEventListener('error', function () {
				img.style.display = 'none';
				box.classList.add('tm-el-noicon');
			});
			box.appendChild(img);

			paintElementState(se, box);

			box.addEventListener('mousedown', function (e) {
				if (!state.editing || state.removeMode) {
					return;
				}

				if (state.linkMode) {
					return;
				}

				e.stopPropagation();
				startElementDrag(se, box, e);
			});

			box.addEventListener('click', function (e) {
				if (!state.editing) {
					// Preview mode works like the built-in maps: clicking a host icon opens the host menu
					openHostMenu(se, box, e);

					return;
				}

				e.stopPropagation();

				// The click right after a drag does not open the dialog
				if (se._suppressClick) {
					return;
				}

				if (state.removeMode === 'element') {
					removeElement(se);
					return;
				}

				if (state.linkMode) {
					pickLinkEndpoint(se);
					return;
				}

				state.selected = se.selementid;

				if (!isEditableElement(se)) {
					renderCanvas();
					openNoticeDialog(T('Cannot edit this element'), T('This element type (host group / trigger / nested map) is maintained by the native Zabbix map editor; here it can only be previewed and moved.'));

					return;
				}

				openElementDialog(se, false);
			});

			return box;
		}

		// Status-related parts of an element: position / size / status class / label / hover tooltip.
		// Auto-refresh calls only this and keeps the original <img> node — rebuilding the layer makes every host icon flicker,
		// while the built-in Zabbix maps update in place without flicker.
		function paintElementState(se, box) {
			var size = iconSize(iconForElement(se));

			box.style.left = se.x + 'px';
			box.style.top = se.y + 'px';
			box.style.width = size.w + 'px';
			box.style.height = size.h + 'px';

			box.classList.toggle('tm-selected', state.selected === se.selementid);
			box.classList.toggle('tm-disabled', se._status === 1);
			box.classList.toggle('tm-problem', se._problems > 0);

			paintElementLabel(se, box);

			if (!state.editing) {
				var tip = elementTitle(se);

				if (tip) {
					box.title = tip;
				}
				else {
					box.removeAttribute('title');
				}
			}
		}

		// Native maps do not show a problem badge at the top right of the icon; the count is only in the label lines, so there is no badge here.
		function paintElementLabel(se, box) {
			var old = box.querySelector('.tm-el-label');

			if (old) {
				box.removeChild(old);
			}

			var labelText = elementLabel(se);
			var statusLines = elementStatusLines(se);
			var nameLines = labelText ? String(labelText).split('\n') : [];

			if (!nameLines.length && !statusLines.length) {
				return;
			}

			var loc = se.label_location === -1 ? state.map.label_location : se.label_location;
			var label = make('span', 'tm-el-label tm-loc-' + loc);

			nameLines.forEach(function (text) {
				label.appendChild(make('span', 'tm-el-line tm-el-name', text));
			});

			statusLines.forEach(function (line) {
				var node = make('span', 'tm-el-line tm-el-status', line.text);

				if (line.color) {
					node.style.color = '#' + line.color;
				}

				label.appendChild(node);
			});

			box.appendChild(label);
		}

		function elementName(se) {
			if (se.elementtype === ET_HOST) {
				return se._hostName || T('Unknown host');
			}

			if (se.elementtype === ET_IMAGE) {
				return T('Image');
			}

			return se.label || '';
		}

		// Native macros {HOST.NAME} / {HOST.CONN} in host element labels.
		// Labels store the raw macros and are expanded only at render time, so after a host is renamed or rediscovered
		// every canvas open replaces them with the latest host.get values.
		function expandHostMacros(text, se) {
			text = String(text || '');

			if (text.indexOf('{') === -1) {
				return text;
			}

			return text
				.replace(/\{HOST\.NAME\}/gi, se._hostName || '')
				.replace(/\{HOST\.CONN\}/gi, se._conn || se._ip || '');
		}

		// First part of the label: what is shown depends on label_type, with semantics aligned with 7.0.28 maps.inc.php
		function elementLabel(se) {
			var type = num(se._labelType, LT_LABEL);

			if (type === LT_NAME) {
				return elementName(se);
			}

			if (type === LT_NOTHING || type === LT_STATUS) {
				return '';
			}

			if (type === LT_IP) {
				return se._ip || '';
			}

			// LT_LABEL / LT_CUSTOM: the element's own label text, with native macros expanded for host elements
			if (se.elementtype === ET_HOST) {
				return expandHostMacros(se.label, se);
			}

			return se.label || '';
		}

		// Second part of the label: status lines. Native maps append "OK / Problem / Disabled / In maintenance" below the host label,
		// colored by severity — this is the user's "the native preview shows OK, and problems too".
		function elementStatusLines(se) {
			var map = state.map;
			var lines = [];

			if (!map || se.elementtype !== ET_HOST || se._labelType === LT_NOTHING) {
				return lines;
			}

			if (se._status === 1) {
				lines.push({text: T('Disabled'), color: statusColor('disabled')});

				return lines;
			}

			if (se._maintenance === 1) {
				// In maintenance, as long as there are no unacknowledged problems, native maps use the maintenance color
				if (!se._problems) {
					lines.push({text: T('In maintenance'), color: statusColor('maintenance')});

					return lines;
				}
			}

			if (!se._problems) {
				// Native getSelementLabelColor(false, ack): with no problems ack = true and the "acknowledged OK" color is used
				lines.push({text: T('OK'), color: statusColor('ok_ack')});

				return lines;
			}

			lines = problemLines(se, map.show_unack, map.expandproblem);

			return lines.length ? lines : [{text: se._problems + T(' problems'), color: statusColor('problem')}];
		}

		// Aligned with the show_unack × expandproblem combinations in getSelementInfo() of 7.0.28 maps.inc.php.
		// expandproblem: 0 = expand a single problem, 1 = show only the problem count, 2 = count + expand the most severe one.
		// The only deviation: whenever there is **only 1 problem** the trigger name is written — the user's map has expandproblem=1,
		// with 1 problem the native map only writes "1 problem" without showing which trigger, and the user explicitly wants the trigger name.
		function problemLines(se, showUnack, expand) {
			var lines = [];
			var total = se._problems;
			var unack = se._unack;
			var title = se._title || '';
			// When all are acknowledged use the "acknowledged problem" color, otherwise the unacknowledged color (native getSelementLabelColor)
			var color = statusColor(unack > 0 ? 'problem' : 'problem_ack');

			function countLine(n, word) {
				return word ? TF('%s unacknowledged problems', n) : TF('%s problems', n);
			}

			// The total > 0 part (show_unack 0 shows all / 2 shows both)
			if (showUnack !== 1 && total > 0) {
				if (total === 1) {
					lines.push({text: title || countLine(1, ''), color: color});
				}
				else if (expand === 1) {
					lines.push({text: countLine(total, ''), color: color});
				}
				else if (expand === 2) {
					lines.push({text: title || countLine(total, ''), color: color});
					lines.push({text: countLine(total, ''), color: color});
				}
				else {
					lines.push({text: countLine(total, ''), color: color});
				}
			}

			// The unacknowledged part (show_unack 1 shows only unacknowledged / 2 shows both)
			if (showUnack !== 0 && unack > 0) {
				if (showUnack === 1) {
					var unackColor = statusColor('problem');

					if (unack === 1) {
						lines.push({text: title || countLine(1, T('Unacknowledged')), color: unackColor});
					}
					else if (expand === 1) {
						lines.push({text: countLine(unack, T('Unacknowledged')), color: unackColor});
					}
					else if (expand === 2) {
						lines.push({text: title || countLine(unack, T('Unacknowledged')), color: unackColor});
						lines.push({text: countLine(unack, T('Unacknowledged')), color: unackColor});
					}
					else {
						lines.push({text: countLine(unack, T('Unacknowledged')), color: unackColor});
					}
				}
				else if (showUnack === 2) {
					// When both are shown, the unacknowledged line always uses the "unacknowledged problem" color
					lines.push({text: countLine(unack, T('Unacknowledged')), color: statusColor('problem')});
				}
			}

			return lines;
		}

		// How many lines an element label takes (the canvas content bounding box must include it); label text supports line breaks
		function labelLineCount(se) {
			var text = elementLabel(se);

			return (text ? String(text).split('\n').length : 0) + elementStatusLines(se).length;
		}

		function elementTitle(se) {
			var parts = [];

			if (se.elementtype === ET_HOST) {
				parts.push(se._hostName || T('Unknown host'));

				if (se._status === 1) {
					parts.push(T('Disabled'));
				}

				if (se._problems > 0) {
					parts.push(T('Current problems: ') + se._problems + T(''));
				}
			}

			if (se.tags && se.tags.length) {
				parts.push(T('Problem tags: ') + se.tags.map(function (tag) {
					return tag.tag + (tag.value ? '=' + tag.value : '');
				}).join(', '));
			}

			return parts.join(' · ');
		}

		function isEditableElement(se) {
			return se.elementtype === ET_HOST || se.elementtype === ET_IMAGE;
		}

		// ---------------------------------------------------------------
		// Canvas size (automatic) and canvas container height (fills the viewport)
		// ---------------------------------------------------------------

		// Keep the canvas area reaching the bottom of the viewport: viewport height − distance from the top to the content area − footer height.
		// calc(100vh - 235px) is not hard-coded because the header, toolbar, edit toolbar and footer may all grow.
		function layoutCanvasWrap() {
			if (!elWrap || elCanvasView.hidden) {
				return;
			}

			// When the page is scrolled, top may be negative; clamp to 0 so the height does not exceed the viewport
			var top = Math.max(0, elWrap.getBoundingClientRect().top);
			var footer = document.getElementById('page-footer') || document.querySelector('footer');
			var footerH = footer ? footer.offsetHeight : 0;
			var viewportH = document.documentElement.clientHeight || window.innerHeight;
			var avail = viewportH - top - footerH - 12;

			elWrap.style.height = Math.max(300, Math.round(avail)) + 'px';
		}

		// The canvas must at least fill the visible area: this puts the canvas boundary out of sight,
		// so dragging an element anywhere on screen never hits the canvas width/height.
		function canvasMinSize() {
			// clientWidth/Height already exclude the border, which is exactly the content area the canvas must fill
			return {
				w: Math.max(MIN_CANVAS_W, elWrap.clientWidth || 0),
				h: Math.max(MIN_CANVAS_H, elWrap.clientHeight || 0)
			};
		}

		function ensureCanvasFitsViewport() {
			var map = state.map;

			if (!map) {
				return;
			}

			var min = canvasMinSize();
			var changed = false;

			if (map.width < min.w) {
				map.width = Math.min(CANVAS_MAX, Math.ceil(min.w));
				changed = true;
			}

			if (map.height < min.h) {
				map.height = Math.min(CANVAS_MAX, Math.ceil(min.h));
				changed = true;
			}

			if (changed) {
				resizeStage();
			}
		}

		function resizeStage() {
			var map = state.map;

			if (!map) {
				return;
			}

			elStage.style.width = map.width + 'px';
			elStage.style.height = map.height + 'px';
			elSvg.setAttribute('width', String(map.width));
			elSvg.setAttribute('height', String(map.height));
			elSvg.setAttribute('viewBox', '0 0 ' + map.width + ' ' + map.height);
			applyView();
		}

		// Content bounding box: bottom-right corner of element icons (including labels below) and shapes
		function contentExtent() {
			var map = state.map;
			var right = 0;
			var bottom = 0;

			map.selements.forEach(function (se) {
				var size = iconSize(iconForElement(se));
				var labelH = labelLineCount(se) * 18;

				right = Math.max(right, se.x + size.w);
				bottom = Math.max(bottom, se.y + size.h + labelH);
			});

			map.shapes.forEach(function (shape) {
				right = Math.max(right, shape.x + num(shape.width, 0));
				bottom = Math.max(bottom, shape.y + num(shape.height, 0));
			});

			// Manual waypoints may reach beyond elements/shapes, so the canvas range must include them too
			map.links.forEach(function (link) {
				normalizeWaypointList(link.waypoints).forEach(function (point) {
					right = Math.max(right, point.x);
					bottom = Math.max(bottom, point.y);
				});
			});

			return {w: right, h: bottom};
		}

		// Dragging/adding only expands to the bottom-right when needed and never shrinks — so dragging has no hard boundary
		function growCanvasTo(needW, needH) {
			var map = state.map;
			var changed = false;

			if (!map) {
				return;
			}

			if (needW > map.width) {
				map.width = Math.min(CANVAS_MAX, Math.ceil(needW));
				changed = true;
			}

			if (needH > map.height) {
				map.height = Math.min(CANVAS_MAX, Math.ceil(needH));
				changed = true;
			}

			if (changed) {
				resizeStage();
			}
		}

		// Top-left of the content bounding box. After dragging left/up the content shifts to the bottom-right as a whole,
		// so before saving it is used to move the content back to (0, 0), otherwise blank space remains on the left/top of the canvas.
		function contentMin() {
			var map = state.map;
			var minX = Infinity;
			var minY = Infinity;

			map.selements.forEach(function (se) {
				minX = Math.min(minX, se.x);
				minY = Math.min(minY, se.y);
			});

			map.shapes.forEach(function (shape) {
				minX = Math.min(minX, shape.x);
				minY = Math.min(minY, shape.y);
			});

			map.links.forEach(function (link) {
				normalizeWaypointList(link.waypoints).forEach(function (point) {
					minX = Math.min(minX, point.x);
					minY = Math.min(minY, point.y);
				});
			});

			return {
				x: isFinite(minX) ? Math.max(0, Math.round(minX)) : 0,
				y: isFinite(minY) ? Math.max(0, Math.round(minY)) : 0
			};
		}

		// Before saving, shrink the canvas to the content size (not below the visible area) to avoid large blank areas
		function autoSizeCanvas() {
			var map = state.map;
			var origin;
			var extent;
			var min;

			if (!map) {
				return;
			}

			origin = contentMin();

			if (origin.x > 0 || origin.y > 0) {
				map.selements.forEach(function (se) {
					se.x -= origin.x;
					se.y -= origin.y;
				});

				map.shapes.forEach(function (shape) {
					shape.x -= origin.x;
					shape.y -= origin.y;
				});

				// Waypoints move together with elements/shapes, otherwise links would be skewed after saving
				map.links.forEach(function (link) {
					(link.waypoints || []).forEach(function (point) {
						point.x = Math.max(0, point.x - origin.x);
						point.y = Math.max(0, point.y - origin.y);
					});
				});

				// Avoid a visual jump: pan compensates in the opposite direction (the view moves right by as much as the content moves left)
				state.panX += origin.x * state.zoom;
				state.panY += origin.y * state.zoom;
			}

			extent = contentExtent();
			min = canvasMinSize();

			map.width = clamp(Math.ceil(Math.max(extent.w + CANVAS_PAD, min.w)), MIN_CANVAS_W, CANVAS_MAX);
			map.height = clamp(Math.ceil(Math.max(extent.h + CANVAS_PAD, min.h)), MIN_CANVAS_H, CANVAS_MAX);
			resizeStage();

			// Coordinates changed; redraw once (openMap / saveMap never run during a drag, so a full redraw is fine)
			paintCanvas();
			applyView();
		}

		// ---------------------------------------------------------------
		// View zoom / pan
		// ---------------------------------------------------------------

		function applyView() {
			elStage.style.transform = 'translate(' + state.panX + 'px,' + state.panY + 'px) scale(' + state.zoom + ')';
			elZoomReset.textContent = Math.round(state.zoom * 100) + '%';

			if (elCanvasSize && state.map) {
				elCanvasSize.textContent = T('Auto · ') + state.map.width + ' × ' + state.map.height + ' px';
				elCanvasSize.title = T('The canvas size is not fixed: at least the viewport, it expands when elements are dragged right/down, ')
					+ T('and the view pans when you drag to the edge; it shrinks to the content size on save. This shows the current actual size.');
			}
		}

		// 100%: 1 unit = 1 pixel, starting at the top-left of the canvas (as in the native view)
		function resetView() {
			state.zoom = 1;
			state.panX = 0;
			state.panY = 0;
			applyView();
		}

		function fitView() {
			var map = state.map;

			if (!map) {
				return;
			}

			var w = elWrap.clientWidth - 40;
			var h = elWrap.clientHeight - 40;

			if (!elWrap.clientWidth || w <= 0 || h <= 0) {
				state.zoom = 1;
				state.panX = 0;
				state.panY = 0;
				applyView();

				return;
			}

			state.zoom = clamp(Math.min(1, w / map.width, h / map.height), 0.2, 3);
			state.panX = Math.max(0, (elWrap.clientWidth - map.width * state.zoom) / 2);
			state.panY = Math.max(0, (elWrap.clientHeight - map.height * state.zoom) / 2);
			applyView();
		}

		function zoomBy(factor) {
			var cw = elWrap.clientWidth / 2;
			var ch = elWrap.clientHeight / 2;
			var next = clamp(state.zoom * factor, 0.2, 3);
			var k = next / state.zoom;

			state.panX = cw - (cw - state.panX) * k;
			state.panY = ch - (ch - state.panY) * k;
			state.zoom = next;
			applyView();
		}

		function viewCenterInMap() {
			return {
				x: (elWrap.clientWidth / 2 - state.panX) / state.zoom,
				y: (elWrap.clientHeight / 2 - state.panY) / state.zoom
			};
		}

		// ---------------------------------------------------------------
		// Dragging
		// ---------------------------------------------------------------

		function startPan(e) {
			var startX = e.clientX;
			var startY = e.clientY;
			var ox = state.panX;
			var oy = state.panY;

			// Used by "click empty space to add a waypoint": dragging the canvas more than 3px is not a click, so no waypoint is added after panning
			panDragged = false;

			elWrap.classList.add('tm-panning');

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 3 || Math.abs(ev.clientY - startY) > 3) {
					panDragged = true;
				}

				state.panX = ox + (ev.clientX - startX);
				state.panY = oy + (ev.clientY - startY);
				applyView();
			}

			function up() {
				elWrap.classList.remove('tm-panning');
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		// When the pointer reaches the canvas edge while dragging, the view pans automatically — elements can always be dragged further down/right,
		// without the "stuck at the screen edge, release and pan first" feeling.
		// Left/up does not use panning; shiftContent() pushes the whole content outwards instead (see below),
		// since map coordinates cannot be negative there, infinite extension is achieved by growing the canvas to the top-left.
		function autoPan(ev) {
			var rect = elWrap.getBoundingClientRect();

			// No panning before the canvas is laid out (when width or height is so small that both sides count as edges), otherwise it keeps drifting one way
			if (rect.width < DRAG_EDGE * 2 || rect.height < DRAG_EDGE * 2) {
				return false;
			}

			var insideX = ev.clientX - rect.left;
			var insideY = ev.clientY - rect.top;
			var moved = false;

			if (insideX > rect.width - DRAG_EDGE) {
				state.panX -= PAN_STEP;
				moved = true;
			}

			if (insideY > rect.height - DRAG_EDGE) {
				state.panY -= PAN_STEP;
				moved = true;
			}

			return moved;
		}

		// Pointer position → map coordinates (taking the current zoom and pan into account)
		function pointerToMap(ev) {
			var rect = elWrap.getBoundingClientRect();

			return {
				x: (ev.clientX - rect.left - state.panX) / state.zoom,
				y: (ev.clientY - rect.top - state.panY) / state.zoom
			};
		}

		// Shift the whole content: element/shape coordinates get (dx, dy), the canvas grows by the same amount and the view shifts back —
		// elements already drawn on screen stay put, as if the canvas grew a piece to the top-left.
		// sysmaps_elements.x/y cannot be negative, as in the native editor, so this is the only way to drag infinitely left/up.
		function shiftContent(dx, dy) {
			var map = state.map;

			dx = Math.round(dx) || 0;
			dy = Math.round(dy) || 0;

			if (!map || (!dx && !dy)) {
				return false;
			}

			map.selements.forEach(function (se) {
				se.x += dx;
				se.y += dy;
			});

			map.shapes.forEach(function (shape) {
				shape.x += dx;
				shape.y += dy;
			});

			// Waypoints move with the content, so routes stay the same
			map.links.forEach(function (link) {
				(link.waypoints || []).forEach(function (point) {
					point.x += dx;
					point.y += dy;
				});
			});

			map.width = Math.min(CANVAS_MAX, map.width + dx);
			map.height = Math.min(CANVAS_MAX, map.height + dy);

			// The view pans in the opposite direction so the content on screen does not jump
			state.panX -= dx * state.zoom;
			state.panY -= dy * state.zoom;

			syncElementNodes();
			// Shapes first, then links: links must be above shapes (the highlight layer sits between shapes and links)
			drawShapes(map);
			drawHighlights(map);
			drawLinks(map);
			resizeStage();

			return true;
		}

		// Only sync left/top of element nodes without rebuilding the DOM (references to nodes being dragged must stay valid)
		function syncElementNodes() {
			var map = state.map;
			var byId = {};
			var nodes;

			if (!map) {
				return;
			}

			map.selements.forEach(function (se) {
				byId[String(se.selementid)] = se;
			});

			nodes = elStage.querySelectorAll('.tm-el');

			for (var i = 0; i < nodes.length; i++) {
				var se = byId[nodes[i].getAttribute('data-id')];

				if (se) {
					nodes[i].style.left = se.x + 'px';
					nodes[i].style.top = se.y + 'px';
				}
			}
		}

		function startElementDrag(se, box, e) {
			var startX = e.clientX;
			var startY = e.clientY;
			// Grab offset: the element follows the pointer while dragging, and the grabbed point stays under the pointer
			var startPt = pointerToMap(e);
			var grabX = startPt.x - se.x;
			var grabY = startPt.y - se.y;
			var size = iconSize(iconForElement(se));
			var moved = false;

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 2 || Math.abs(ev.clientY - startY) > 2) {
					moved = true;
				}

				var panned = autoPan(ev);
				var pt = pointerToMap(ev);
				var nx = Math.round(pt.x - grabX);
				var ny = Math.round(pt.y - grabY);

				// Dragging beyond the left/top boundary: push the whole map to the bottom-right and snap the dragged piece back to 0 (visually it stays put),
				// and the canvas grows by the same amount — so there is no boundary on the left and top either.
				if (nx < 0 || ny < 0) {
					shiftContent(nx < 0 ? -nx : 0, ny < 0 ? -ny : 0);
					nx = Math.max(0, nx);
					ny = Math.max(0, ny);
				}

				se.x = nx;
				se.y = ny;
				// Dragging beyond the current right/bottom canvas edge simply enlarges the canvas; there is no hard boundary
				growCanvasTo(se.x + size.w + CANVAS_PAD, se.y + size.h + CANVAS_PAD);
				box.style.left = se.x + 'px';
				box.style.top = se.y + 'px';
				syncHighlight(se);
				drawLinks(state.map);

				if (panned) {
					applyView();
				}
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
					// A click after dragging must not open the dialog
					se._suppressClick = true;
					setTimeout(function () {
						se._suppressClick = false;
					}, 0);
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		function startShapeDrag(shape, e) {
			var startX = e.clientX;
			var startY = e.clientY;
			var startPt = pointerToMap(e);
			var grabX = startPt.x - shape.x;
			var grabY = startPt.y - shape.y;
			var moved = false;

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 2 || Math.abs(ev.clientY - startY) > 2) {
					moved = true;
				}

				var panned = autoPan(ev);
				var pt = pointerToMap(ev);
				var nx = Math.round(pt.x - grabX);
				var ny = Math.round(pt.y - grabY);

				// Dragging an element across the left/top boundary pushes the whole map to the bottom-right and the canvas grows
				if (nx < 0 || ny < 0) {
					shiftContent(nx < 0 ? -nx : 0, ny < 0 ? -ny : 0);
					nx = Math.max(0, nx);
					ny = Math.max(0, ny);
				}

				shape.x = nx;
				shape.y = ny;
				growCanvasTo(shape.x + shape.width + CANVAS_PAD, shape.y + shape.height + CANVAS_PAD);
				repaintSoon();

				if (panned) {
					applyView();
				}
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		var repaintPending = false;
		// Whether the canvas was dragged after this press (distinguishes "click to add a point" from "drag to pan" in waypoint mode)
		var panDragged = false;

		function repaintSoon() {
			if (repaintPending) {
				return;
			}

			repaintPending = true;

			function run() {
				repaintPending = false;

				if (state.map) {
					paintCanvas();
					applyView();
				}
			}

			if (window.requestAnimationFrame) {
				window.requestAnimationFrame(run);
			}
			else {
				setTimeout(run, 16);
			}
		}

		elWrap.addEventListener('mousedown', function (e) {
			if (e.target && (e.target.closest('.tm-el') || e.target.classList.contains('tm-shape')
					|| e.target.classList.contains('tm-shape-handle')
					|| e.target.classList.contains('tm-link-hit') || e.target.classList.contains('tm-waypoint'))) {
				return;
			}

			if (e.button !== 0) {
				return;
			}

			// Clicking empty space deselects the shape (native maps also hide the handles when clicking elsewhere)
			if (state.shapeSel) {
				state.shapeSel = null;
				drawShapeHandles();
			}

			startPan(e);
		});

		// Waypoint mode: clicking empty space adds a waypoint. Panning does not trigger it (skipped when panDragged is true),
		// and clicks on elements / shapes / links / waypoint dots do not add one either.
		elWrap.addEventListener('click', function (e) {
			if (!state.bendLink || !state.editing || panDragged) {
				return;
			}

			var target = e.target;

			if (!target) {
				return;
			}

			if (target.closest && target.closest('.tm-el')) {
				return;
			}

			if (target.classList && (target.classList.contains('tm-shape')
					|| target.classList.contains('tm-link-hit') || target.classList.contains('tm-waypoint'))) {
				return;
			}

			var family = bendFamily();

			if (!family.length) {
				return;
			}

			var pt = pointerToMap(e);
			var points = normalizeWaypointList(family[0].waypoints);

			points.push({x: Math.max(0, Math.round(pt.x)), y: Math.max(0, Math.round(pt.y))});
			setFamilyWaypoints(family, points);
			markDirty();
			drawLinks(state.map);
		});

		// ---------------------------------------------------------------
		// Editing: add / remove
		// ---------------------------------------------------------------

		// New elements need a temporary selementid for links to reference; the real id replaces it when map.update saves.
		// It must be purely numeric and far larger than any real id: Zabbix's dbConditionId() filters selementid as bigint,
		// and a string like "n1" causes a type error on PostgreSQL (MySQL just casts it to 0).
		function nextTempId() {
			var id;

			do {
				state.tmpSeq += 1;
				id = String(900000000 + state.tmpSeq);
			} while (state.map.selements.some(function (se) {
				return String(se.selementid) === id;
			}));

			return id;
		}

		function addElement() {
			var map = state.map;
			var center = viewCenterInMap();
			var size = iconSize(state.defaultIcon);

			var se = {
				selementid: nextTempId(),
				elementtype: ET_IMAGE,
				elements: [],
				iconid_off: state.defaultIcon,
				iconid_on: '0',
				iconid_disabled: '0',
				iconid_maintenance: '0',
				label: T('New element'),
				label_location: -1,
				x: Math.max(0, Math.round(center.x - size.w / 2)),
				y: Math.max(0, Math.round(center.y - size.h / 2)),
				use_iconmap: 0,
				evaltype: 0,
				tags: [],
				urls: [],
				_hostid: '',
				_hostName: '',
				_conn: '',
				_status: 0,
				_problems: 0,
				_severity: -1
			};

			map.selements.push(se);
			growCanvasTo(se.x + size.w + CANVAS_PAD, se.y + size.h + CANVAS_PAD);
			markDirty();
			renderCanvas();
			openElementDialog(se, true);
		}

		function addShape() {
			var map = state.map;

			state.tmpSeq += 1;

			// Defaults match the native Shape constructor exactly: starting at 10,10, 50×50, 2px black border,
			// black Helvetica 11 text; clicking the shape button gives these values, which can then be changed.
			var shape = {
				_tmpid: 's' + state.tmpSeq,
				type: 0,
				x: 10,
				y: 10,
				width: 50,
				height: 50,
				text: '',
				font: 9,
				font_size: 11,
				font_color: '000000',
				text_halign: 0,
				text_valign: 0,
				border_type: 1,
				border_width: 2,
				border_color: '000000',
				background_color: '',
				zindex: map.shapes.length
			};

			map.shapes.push(shape);
			state.shapeSel = shape;
			growCanvasTo(shape.x + shape.width + CANVAS_PAD, shape.y + shape.height + CANVAS_PAD);
			markDirty();
			renderCanvas();
			openShapeDialog(shape, true);
		}

		function removeElement(se) {
			var map = state.map;

			map.selements = map.selements.filter(function (item) {
				return item !== se;
			});

			map.links = map.links.filter(function (link) {
				return String(link.selementid1) !== String(se.selementid) && String(link.selementid2) !== String(se.selementid);
			});

			markDirty();
			renderCanvas();
		}

		function removeShape(shape) {
			state.map.shapes = state.map.shapes.filter(function (item) {
				return item !== shape;
			});

			if (state.shapeSel === shape) {
				state.shapeSel = null;
			}

			markDirty();
			renderCanvas();
		}

		function setRemoveMode(kind) {
			if (state.removeMode === kind) {
				clearModes();
				renderCanvas();

				return;
			}

			state.removeMode = kind;
			state.linkMode = false;
			state.linkFirst = null;
			syncModeButtons();
			setHint(T('Remove mode: click the ') + (kind === 'element' ? T('map element') : (kind === 'shape' ? T('shape') : T('link'))) + T(' to remove (Esc to cancel)'));
			renderCanvas();
		}

		function startLinkMode() {
			state.linkMode = true;
			state.removeMode = null;
			state.linkFirst = null;
			syncModeButtons();
			setHint(T('Link mode: click two elements in order to create a link (Esc to cancel)'));
			renderCanvas();
		}

		function pickLinkEndpoint(se) {
			if (!state.linkFirst) {
				state.linkFirst = se;
				state.selected = se.selementid;
				setHint(T('Link mode: selected "') + (elementLabel(se) || T('element')) + T('", now click another element'));
				renderCanvas();

				return;
			}

			if (state.linkFirst === se) {
				return;
			}

			var link = {
				selementid1: state.linkFirst.selementid,
				selementid2: se.selementid,
				label: '',
				color: DEFAULT_LINK_COLOR,
				drawtype: 0
			};

			state.map.links.push(link);
			state.linkFirst = null;
			state.linkMode = false;
			state.selected = null;
			markDirty();
			renderCanvas();
			openLinkDialog(link, true);
		}

		// ---------------------------------------------------------------
		// Common popups
		// ---------------------------------------------------------------

		function openDialog(options) {
			elDialogTitle.textContent = options.title;
			clear(elDialogBody);
			clear(elDialogFoot);
			elDialog.className = 'tm-dialog'
				+ (options.narrow ? ' tm-dialog-narrow' : '')
				+ (options.linkDialog ? ' tm-dialog-link' : '');

			if (options.build) {
				options.build(elDialogBody);
			}

			(options.buttons || []).forEach(function (button) {
				var node = make('button', 'tm-btn' + (button.primary ? ' tm-btn-primary' : ''), button.label);
				node.type = 'button';
				node.addEventListener('click', function () {
					button.onClick();
				});
				elDialogFoot.appendChild(node);
			});

			setHidden(elOverlay, false);
		}

		function closeDialog() {
			setHidden(elOverlay, true);
			clear(elDialogBody);
			clear(elDialogFoot);

			// Esc / × at the top right / clicking the overlay closes the confirmation as "Cancel", so the Promise never hangs
			if (state.pendingConfirm) {
				var resolve = state.pendingConfirm;
				state.pendingConfirm = null;
				resolve(false);
			}
		}

		function dialogForm(body) {
			var table = make('table', 'tm-form');
			var tbody = make('tbody');
			table.appendChild(tbody);
			body.appendChild(table);

			return tbody;
		}

		function formRow(tbody, labelText, required) {
			var tr = make('tr');
			var th = make('th', required ? 'tm-req' : null, labelText);
			var td = make('td');
			tr.appendChild(th);
			tr.appendChild(td);
			tbody.appendChild(tr);

			return {tr: tr, td: td};
		}

		function fieldError(node, text) {
			var err = node.querySelector('.tm-field-error');

			if (!err) {
				err = make('div', 'tm-msg tm-msg-error');
				err.style.marginTop = '4px';
				node.appendChild(err);
			}

			err.textContent = text;
		}

		// ---------------------------------------------------------------
		// Properties (rename / delete)
		// ---------------------------------------------------------------

		function openPropertiesDialog(map) {
			openDialog({
				title: T('Map properties'),
				build: function (body) {
					var tbody = dialogForm(body);
					var nameRow = formRow(tbody, T('Name'), true);
					var input = make('input', 'tm-input tm-input-block');
					input.type = 'text';
					input.maxLength = 128;
					input.value = map.name;
					nameRow.td.appendChild(input);

					// The canvas size is now automatic: it expands while dragging and shrinks to the content on save; users no longer enter numbers
					var sizeRow = formRow(tbody, T('Canvas size'), false);
					sizeRow.td.appendChild(make('div', 'tm-size-auto',
						T('Auto (current ') + map.width + ' × ' + map.height + ' px)'));
					sizeRow.td.appendChild(make('div', 'tm-hint',
						T('The canvas grows when you drag elements right/down and shrinks to fit on save, leaving no large empty area')));

					var warn = make('div', 'tm-msg');
					warn.style.marginTop = '6px';
					nameRow.td.appendChild(warn);

					setTimeout(function () {
						input.focus();
						input.select();
					}, 0);

					openDialog.rename = function () {
						var name = input.value.trim();

						if (!name) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('The name cannot be empty');

							return;
						}

						api('rename', {sysmapid: map.sysmapid, name: name}).then(function () {
							closeDialog();
							loadList();
							msg(elListMsg, T('Saved "') + name + T('" properties'), 'ok');
						}).catch(function (e) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('Save failed: ') + saveError(e.message);
						});
					};

					openDialog.remove = function () {
						confirmDialog(T('Delete map?'), T('This will permanently delete "') + map.name + T('" and all its elements, links and shapes. This action cannot be undone.'), T('Delete')).then(function (ok) {
							if (!ok) {
								return;
							}

							api('delete', {sysmapid: map.sysmapid}).then(function () {
								closeDialog();
								delete state.checked[map.sysmapid];
								loadList();
								msg(elListMsg, T('Deleted "') + map.name + '"', 'ok');
							}).catch(function (e) {
								warn.className = 'tm-msg tm-msg-error';
								warn.textContent = T('Deletion failed: ') + e.message;
							});
						});
					};
				},
				buttons: [
					{label: T('Apply'), primary: true, onClick: function () { openDialog.rename(); }},
					{label: T('Delete'), onClick: function () { openDialog.remove(); }},
					{label: T('Close'), onClick: closeDialog}
				]
			});
		}

		// ---------------------------------------------------------------
		// Element properties dialog (mirrors the native "map element" form)
		// ---------------------------------------------------------------

		function openElementDialog(se, isNew) {
			var draft = copy(se);
			var hostRows = [];
			var iconExtraRows = [];

			openDialog({
				title: T('Element properties'),
				build: function (body) {
					var tbody = dialogForm(body);

					// Type
					var typeRow = formRow(tbody, T('Type'), false);
					var typeSel = makeSelect([[ET_IMAGE, T('Image')], [ET_HOST, T('Host')]], draft.elementtype, 'tm-w-200');
					typeSel.style.width = '180px';
					typeRow.td.appendChild(typeSel);

					// Label: "host name + IP" is written automatically after choosing a host, unless the user edited it
					var labelTouched = !!String(draft.label || '').trim() && draft.label !== T('New element');

					var labelRow = formRow(tbody, T('Label'), false);
					var labelInput = make('textarea');
					labelInput.rows = 2;
					labelInput.value = draft.label || '';
					labelInput.maxLength = 2048;
					labelInput.placeholder = T('Line breaks are allowed; selecting a host fills in "host name + IP"');
					labelInput.addEventListener('input', function () {
						labelTouched = true;
					});
					labelRow.td.appendChild(labelInput);

					// Label position
					var locRow = formRow(tbody, T('Label position'), false);
					var locSel = makeSelect(LABEL_LOCS, draft.label_location === undefined ? -1 : draft.label_location);
					locSel.style.width = '180px';
					locRow.td.appendChild(locSel);

					// Host
					var hostRow = formRow(tbody, T('Host'), true);
					var hostLine = make('div', 'tm-inline');
					var hostInput = make('input', 'tm-input tm-grow');
					hostInput.type = 'text';
					hostInput.placeholder = T('Type to search');
					hostInput.value = draft._hostName || '';
					hostInput.readOnly = true;
					var hostBtn = make('button', 'tm-btn', T('Select'));
					hostBtn.type = 'button';
					hostLine.appendChild(hostInput);
					hostLine.appendChild(hostBtn);
					hostRow.td.appendChild(hostLine);
					hostRows.push(hostRow.tr);
					openElementDialog.hostTd = hostRow.td;

					hostBtn.addEventListener('click', function () {
						openHostPicker(function (host) {
							draft._hostid = host.hostid;
							draft._hostName = host.name;
							draft._ip = host.ip || '';
							draft.elements = [{hostid: host.hostid}];
							hostInput.value = host.name + (host.ip ? ' (' + host.ip + ')' : '');

							// When the label is still empty or the "new element" placeholder, fill in two lines of macros by default:
							// host name on the first line, connection address on the second (expanded with the host's latest values at render time)
							if (!labelTouched) {
								labelInput.value = '{HOST.NAME}\n{HOST.CONN}';
							}
						});
					});

					// Problem tags
					var tagRow = formRow(tbody, T('Problem tags'), false);
					var tagsBox = make('div', 'tm-tags');
					var evalLine = make('div', 'tm-tags-eval');
					var evalName = 'tm-eval-' + Date.now();
					[[0, T('And/Or (default)')], [2, T('Or')]].forEach(function (pair) {
						var lab = make('label');
						var radio = make('input');
						radio.type = 'radio';
						radio.name = evalName;
						radio.value = String(pair[0]);
						radio.checked = num(draft.evaltype, 0) === pair[0];
						lab.appendChild(radio);
						lab.appendChild(document.createTextNode(pair[1]));
						evalLine.appendChild(lab);
					});
					tagsBox.appendChild(evalLine);

					var tagList = make('div');
					tagsBox.appendChild(tagList);

					var addTag = make('button', 'tm-linkbtn tm-tags-add', T('Add'));
					addTag.type = 'button';
					addTag.addEventListener('click', function () {
						tagList.appendChild(buildTagRow({tag: '', operator: 0, value: ''}));
					});
					tagsBox.appendChild(addTag);
					tagRow.td.appendChild(tagsBox);
					hostRows.push(tagRow.tr);

					(draft.tags || []).forEach(function (tag) {
						tagList.appendChild(buildTagRow(tag));
					});

					// Automatic icon selection
					var autoRow = formRow(tbody, T('Auto-select icon'), false);
					var autoLine = make('label', 'tm-checkline');
					var autoBox = make('input');
					autoBox.type = 'checkbox';
					autoBox.checked = num(draft.use_iconmap, 0) === 1;
					autoLine.appendChild(autoBox);
					autoRow.td.appendChild(autoLine);
					hostRows.push(autoRow.tr);

					// Icons
					var iconRow = formRow(tbody, T('Icon'), false);
					var iconBox = make('div', 'tm-icons-box');
					var iconTable = make('table', 'tm-icons-table');
					var iconBody = make('tbody');
					iconTable.appendChild(iconBody);
					iconBox.appendChild(iconTable);

					var iconOff = buildIconSelect(draft.iconid_off, false);
					var iconOn = buildIconSelect(draft.iconid_on, true);
					var iconMaint = buildIconSelect(draft.iconid_maintenance, true);
					var iconDisabled = buildIconSelect(draft.iconid_disabled, true);

					appendIconRow(iconBody, T('Default'), iconOff, null);
					appendIconRow(iconBody, T('problem'), iconOn, iconExtraRows);
					appendIconRow(iconBody, T('Maintenance'), iconMaint, iconExtraRows);
					appendIconRow(iconBody, T('Disabled'), iconDisabled, iconExtraRows);

					iconRow.td.appendChild(iconBox);

					// Type switch: images keep only the "default" icon; hosts show host/tags/automatic icon/four icons
					function syncType() {
						var type = num(typeSel.value, ET_IMAGE);
						var isHost = type === ET_HOST;

						hostRows.forEach(function (tr) {
							setHidden(tr, !isHost);
						});

						iconExtraRows.forEach(function (tr) {
							setHidden(tr, !isHost);
						});

						var autoMode = isHost && autoBox.checked;
						[iconOff, iconOn, iconMaint, iconDisabled].forEach(function (sel) {
							sel.disabled = autoMode;
						});
					}

					typeSel.addEventListener('change', syncType);
					autoBox.addEventListener('change', syncType);
					syncType();

					// The dialog's internal state is kept in the closure for the footer buttons
					openElementDialog.collect = function () {
						draft.elementtype = num(typeSel.value, ET_IMAGE);
						draft.label = labelInput.value;
						draft.label_location = num(locSel.value, -1);
						draft.use_iconmap = autoBox.checked ? 1 : 0;
						draft.iconid_off = String(iconOff.value);
						draft.iconid_on = String(iconOn.value);
						draft.iconid_maintenance = String(iconMaint.value);
						draft.iconid_disabled = String(iconDisabled.value);
						draft.evaltype = 0;
						draft.tags = [];

						var checked = evalLine.querySelector('input:checked');

						if (checked) {
							draft.evaltype = num(checked.value, 0);
						}

						var rows = tagList.querySelectorAll('.tm-tag-row');

						for (var i = 0; i < rows.length; i++) {
							var name = rows[i].querySelector('.tm-tag-name').value.trim();
							var value = rows[i].querySelector('.tm-tag-value').value;

							if (!name) {
								continue;
							}

							draft.tags.push({
								tag: name,
								operator: num(rows[i].querySelector('select').value, 0),
								value: value
							});
						}

						if (draft.elementtype === ET_IMAGE) {
							draft.elements = [];
							draft.tags = [];
							draft.use_iconmap = 0;
							draft.iconid_on = '0';
							draft.iconid_maintenance = '0';
							draft.iconid_disabled = '0';
						}
						else if (!draft._hostid) {
							return T('Select a host');
						}
						else {
							draft.elements = [{hostid: draft._hostid}];
						}

						return null;
					};
				},
				buttons: [
					{label: T('Apply'), primary: true, onClick: function () { applyElement(); }},
					{label: T('Remove'), onClick: function () { closeDialog(); removeElement(se); }},
					{label: T('Close'), onClick: closeDialog}
				]
			});

			function applyElement() {
				var err = openElementDialog.collect();

				if (err) {
					if (openElementDialog.hostTd) {
						fieldError(openElementDialog.hostTd, err);
					}

					return;
				}

				draft._status = se._status;
				draft._problems = se._problems;
				draft._severity = se._severity;

				if (draft._hostid) {
					var host = state.map.hosts[draft._hostid];

					if (host) {
						// When the host changes, status/problems switch to the new host too; do not keep the old host's problem count
						draft._hostName = host.name;
						draft._ip = String(host.ip || draft._ip || '');
						draft._status = num(host.status, 0);
						draft._maintenance = num(host.maintenance, 0);
						draft._problems = num(host.problems, 0);
						draft._unack = num(host.unack, 0);
						draft._severity = num(host.severity, -1);
						draft._title = host.title || '';
					}
				}
				else {
					draft._hostName = '';
				}

				Object.keys(draft).forEach(function (key) {
					se[key] = draft[key];
				});
				se._suppressClick = false;

				markDirty();
				closeDialog();
				renderCanvas();
			}

			function buildTagRow(tag) {
				var row = make('div', 'tm-tag-row');

				var name = make('input', 'tm-input tm-tag-name');
				name.type = 'text';
				name.placeholder = T('Tag');
				name.value = tag.tag || '';
				name.maxLength = 255;

				var op = makeSelect(TAG_OPERATORS, num(tag.operator, 0));

				var value = make('input', 'tm-input tm-tag-value');
				value.type = 'text';
				value.placeholder = T('Value');
				value.value = tag.value || '';
				value.maxLength = 255;

				var del = make('button', 'tm-linkbtn', T('Remove'));
				del.type = 'button';
				del.addEventListener('click', function () {
					row.parentNode.removeChild(row);
				});

				row.appendChild(name);
				row.appendChild(op);
				row.appendChild(value);
				row.appendChild(del);

				return row;
			}

			function buildIconSelect(value, withDefault) {
				var options = [];

				if (withDefault) {
					options.push(['0', T('Default')]);
				}

				state.icons.forEach(function (icon) {
					options.push([icon.imageid, icon.name]);
				});

				if (!options.length) {
					options.push(['0', T('(no icons available)')]);
				}

				return makeSelect(options, String(value || '0'));
			}

			function appendIconRow(tbody, title, select, bucket) {
				var tr = make('tr');
				var td1 = make('td', null, title);
				var td2 = make('td');
				td2.appendChild(select);
				tr.appendChild(td1);
				tr.appendChild(td2);
				tbody.appendChild(tr);

				if (bucket) {
					bucket.push(tr);
				}
			}
		}

		// ---------------------------------------------------------------
		// Shape properties dialog
		// ---------------------------------------------------------------

		function openShapeDialog(shape, isNew) {
			var draft = copy(shape);

			openDialog({
				title: T('Map shape'),
				build: function (body) {
					var tbody = dialogForm(body);

					// Shape: rectangle / ellipse / line — natively a three-way button
					var typeRow = formRow(tbody, T('shape'), false);
					var seg = make('div', 'tm-seg');
					var typeButtons = [];

					SHAPE_TYPES.forEach(function (pair) {
						var btn = make('button', 'tm-seg-item', pair[1]);

						btn.type = 'button';
						btn.setAttribute('data-shape-type', String(pair[0]));
						btn.addEventListener('click', function () {
							setShapeType(pair[0]);
						});
						seg.appendChild(btn);
						typeButtons.push(btn);
					});
					typeRow.td.appendChild(seg);

					var textRow = formRow(tbody, T('Text'), false);
					var textInput = make('textarea');
					textInput.rows = 2;
					textInput.maxLength = 255;
					textInput.value = draft.text || '';
					textRow.td.appendChild(textInput);

					// Font / font size / color (natively below the text, without a separate row label)
					var fontRow = formRow(tbody, '');
					var fontLine = make('div', 'tm-inline');
					var fontSel = makeSelect(SHAPE_FONT_NAMES.map(function (name, index) {
						return [index, name];
					}), draft.font);
					fontSel.style.width = '150px';
					var fontColor = buildColorInput(draft.font_color);
					var fontSize = make('input', 'tm-input');
					fontSize.type = 'text';
					fontSize.value = String(draft.font_size || 11);
					fontSize.style.width = '52px';
					fontLine.appendChild(make('span', 'tm-hint', T('Font')));
					fontLine.appendChild(fontSel);
					fontLine.appendChild(make('span', 'tm-hint', T('Font size')));
					fontLine.appendChild(fontSize);
					fontLine.appendChild(make('span', 'tm-hint', T('Color')));
					fontLine.appendChild(fontColor.wrap);
					fontRow.td.appendChild(fontLine);

					var alignRow = formRow(tbody, '');
					var alignLine = make('div', 'tm-inline');
					var halignSel = makeSelect([[0, T('Center')], [1, T('Left')], [2, T('Right')]], draft.text_halign);
					var valignSel = makeSelect([[0, T('Center')], [1, T('Top')], [2, T('Bottom')]], draft.text_valign);
					alignLine.appendChild(make('span', 'tm-hint', T('Horizontal align')));
					alignLine.appendChild(halignSel);
					alignLine.appendChild(make('span', 'tm-hint', T('Vertical align')));
					alignLine.appendChild(valignSel);
					alignRow.td.appendChild(alignLine);

					var fillRow = formRow(tbody, T('Background'));
					var fillColor = buildColorInput(draft.background_color);
					var fillLine = make('div', 'tm-inline');
					fillLine.appendChild(make('span', 'tm-hint', T('Color')));
					fillLine.appendChild(fillColor.wrap);
					fillRow.td.appendChild(fillLine);

					var borderRow = formRow(tbody, T('Border'));
					var borderLabel = borderRow.tr.firstChild;
					var borderLine = make('div', 'tm-inline');
					var borderSel = makeSelect(SHAPE_BORDERS, draft.border_type);
					var borderColor = buildColorInput(draft.border_color);
					var borderWidth = make('input', 'tm-input');
					borderWidth.type = 'text';
					borderWidth.value = String(num(draft.border_width, 2));
					borderWidth.style.width = '52px';
					borderLine.appendChild(make('span', 'tm-hint', T('Type')));
					borderLine.appendChild(borderSel);
					borderLine.appendChild(make('span', 'tm-hint', T('Width (px)')));
					borderLine.appendChild(borderWidth);
					borderLine.appendChild(make('span', 'tm-hint', T('Color')));
					borderLine.appendChild(borderColor.wrap);
					borderRow.td.appendChild(borderLine);

					var coordRow = formRow(tbody, T('Coordinates'));
					var coordLabel = coordRow.tr.firstChild;
					var coordLine = make('div', 'tm-inline');
					var xLabel = make('span', 'tm-hint', 'X');
					var yLabel = make('span', 'tm-hint', 'Y');
					var xInput = make('input', 'tm-input');
					xInput.type = 'text';
					xInput.value = String(draft.x);
					xInput.style.width = '70px';
					var yInput = make('input', 'tm-input');
					yInput.type = 'text';
					yInput.value = String(draft.y);
					yInput.style.width = '70px';
					coordLine.appendChild(xLabel);
					coordLine.appendChild(xInput);
					coordLine.appendChild(yLabel);
					coordLine.appendChild(yInput);
					coordRow.td.appendChild(coordLine);

					var sizeRow = formRow(tbody, T('Size'));
					var sizeLine = make('div', 'tm-inline');
					var wLabel = make('span', 'tm-hint', T('Width (px)'));
					var hLabel = make('span', 'tm-hint', T('Height (px)'));
					var wInput = make('input', 'tm-input');
					wInput.type = 'text';
					wInput.value = String(draft.width);
					wInput.style.width = '70px';
					var hInput = make('input', 'tm-input');
					hInput.type = 'text';
					hInput.value = String(draft.height);
					hInput.style.width = '70px';
					sizeLine.appendChild(wLabel);
					sizeLine.appendChild(wInput);
					sizeLine.appendChild(hLabel);
					sizeLine.appendChild(hInput);
					sizeRow.td.appendChild(sizeLine);

					// Lines have different semantics from shapes: x,y is the start and width,height the end;
					// native maps convert "width/height" to absolute end coordinates when switching to a line, and take the bounding box when switching back.
					function setShapeType(type) {
						var isLine = type === 2;
						var wasLine = draft.type === 2;

						if (!wasLine && isLine) {
							wInput.value = String(num(xInput.value, 0) + num(wInput.value, 0));
							hInput.value = String(num(yInput.value, 0) + num(hInput.value, 0));
						}
						else if (wasLine && !isLine) {
							var ax = num(xInput.value, 0);
							var ay = num(yInput.value, 0);
							var bx = num(wInput.value, 0);
							var by = num(hInput.value, 0);
							var left = Math.min(ax, bx);
							var top = Math.min(ay, by);

							xInput.value = String(left);
							yInput.value = String(top);
							wInput.value = String(Math.max(ax, bx) - left);
							hInput.value = String(Math.max(ay, by) - top);
						}

						draft.type = type;

						typeButtons.forEach(function (btn) {
							btn.classList.toggle('tm-seg-active', num(btn.getAttribute('data-shape-type'), 0) === type);
							btn.setAttribute('aria-pressed', String(num(btn.getAttribute('data-shape-type'), 0) === type));
						});

						// Lines have no text or background, and the labels become the native "line / dot / X1,Y1 / X2,Y2"
						setHidden(textRow.tr, isLine);
						setHidden(fillRow.tr, isLine);
						borderLabel.textContent = isLine ? T('Line') : T('Border');
						coordLabel.textContent = isLine ? T('Point') : T('Coordinates');
						xLabel.textContent = isLine ? 'X1' : 'X';
						yLabel.textContent = isLine ? 'Y1' : 'Y';
						wLabel.textContent = isLine ? 'X2' : T('Width (px)');
						hLabel.textContent = isLine ? 'Y2' : T('Height (px)');
					}

					var active = SHAPE_TYPES.some(function (pair) {
						return pair[0] === draft.type;
					}) ? draft.type : 0;

					setShapeType(active);

					openShapeDialog.collect = function () {
						draft.text = textInput.value;
						draft.font = Math.max(0, Math.min(SHAPE_FONT_NAMES.length - 1, num(fontSel.value, 9)));
						draft.font_size = Math.max(1, num(fontSize.value, 11));
						draft.font_color = fontColor.value() || '000000';
						draft.text_halign = num(halignSel.value, 0);
						draft.text_valign = num(valignSel.value, 0);
						draft.background_color = fillColor.value();
						draft.border_type = num(borderSel.value, 1);
						draft.border_width = Math.max(0, num(borderWidth.value, 2));
						draft.border_color = borderColor.value() || '000000';
						draft.x = Math.max(0, num(xInput.value, draft.x));
						draft.y = Math.max(0, num(yInput.value, draft.y));

						// Line end points may be 0; rectangles/ellipses have a minimum of 1 (as in native)
						var minSize = draft.type === 2 ? 0 : 1;
						draft.width = Math.max(minSize, num(wInput.value, draft.width));
						draft.height = Math.max(minSize, num(hInput.value, draft.height));

						return null;
					};
				},
				buttons: [
					{label: T('Apply'), primary: true, onClick: function () {
						openShapeDialog.collect();

						Object.keys(draft).forEach(function (key) {
							shape[key] = draft[key];
						});

						markDirty();
						closeDialog();
						renderCanvas();
					}},
					{label: T('Remove'), onClick: function () {
						closeDialog();
						removeShape(shape);
					}},
					{label: T('Close'), onClick: closeDialog}
				]
			});
		}

		// ---------------------------------------------------------------
		// Link properties dialog
		// ---------------------------------------------------------------

		function openLinkDialog(link, isNew) {
			// The whole group of links shares one dialog: several links between the same pair of elements (aggregation members)
			// are edited here at once, not one by one.
			var family = linkFamily(link);
			var draft = copy(link);
			// Triggers are shown merged across members (the link indicators of every member link must appear in the list)
			var triggersEdited = false;

			draft.metrics = familyMetrics(family);
			draft.label = familyLabel(family);
			draft.linktriggers = familyLinkTriggers(family);
			// The set of triggers of the whole group when opened: on apply it tells which ones were added now,
			// so only new items are synced to every member and triggers unique to member A are not copied to member B.
			var initialTriggerIds = {};

			draft.linktriggers.forEach(function (lt) {
				initialTriggerIds[String(lt.triggerid)] = true;
			});

			draft.routing = Math.max(0, Math.min(2, num(draft.routing, 0)));
			draft.waypoints = normalizeWaypointList(draft.waypoints);

			openDialog({
				title: T('Link properties'),
				linkDialog: true,
				build: function (body) {
					var tbody = dialogForm(body);

					if (family.length > 1) {
						var groupHint = make('div', 'tm-hint');
						groupHint.textContent = T('This link has ') + family.length
							+ T(' member connections (link aggregation); changes apply to all members.');
						body.appendChild(groupHint);
					}

					var labelRow = formRow(tbody, T('Label'), false);
					var labelInput = make('textarea', 'tm-input tm-link-labelbox');
					labelInput.rows = 2;
					labelInput.maxLength = 2048;
					labelInput.value = draft.label || '';
					labelRow.td.appendChild(labelInput);

					// Show label: exactly the same three options as native link properties (segmented buttons)
					var showRow = formRow(tbody, T('Show labels'), false);
					var showLine = make('div', 'tm-seg-radio');
					var showRadios = [];
					var showName = 'tm-link-show-' + Date.now();

					[[-1, T('Default')], [1, T('Always')], [0, T('Auto-hide')]].forEach(function (pair) {
						var lab = make('label');
						var radio = make('input');
						radio.type = 'radio';
						radio.name = showName;
						radio.value = String(pair[0]);
						radio.checked = num(draft.show_label, -1) === pair[0];
						radio.addEventListener('change', updateShowSeg);
						lab.appendChild(radio);
						lab.appendChild(document.createTextNode(pair[1]));
						showLine.appendChild(lab);
						showRadios.push(radio);
					});

					function updateShowSeg() {
						Array.prototype.forEach.call(showLine.querySelectorAll('label'), function (lab) {
							lab.classList.toggle('tm-seg-radio-on', lab.querySelector('input').checked);
						});
					}

					updateShowSeg();
					showRow.td.appendChild(showLine);

					var colorRow = formRow(tbody, T('Color (OK)'), false);
					var colorInput = make('input', 'tm-trig-color');
					colorInput.type = 'color';
					colorInput.value = '#' + String(draft.color || DEFAULT_LINK_COLOR).replace('#', '');
					colorRow.td.appendChild(colorInput);

					var typeRow = formRow(tbody, T('Type (OK)'), false);
					var typeSel = makeSelect(LINK_DRAWTYPES, draft.drawtype);
					typeSel.style.width = '68px';
					typeRow.td.appendChild(typeSel);

					// Routing: automatic curve / automatic orthogonal / manual waypoints. After choosing manual, click the button to add waypoints on the canvas.
					var routeRow = formRow(tbody, T('Routing'), false);
					var routeWrap = make('div', 'tm-metric-row');
					var routeSel = makeSelect(LINK_ROUTINGS, num(draft.routing, 0));
					routeSel.style.width = '180px';
					var bendBtn = make('button', 'tm-btn tm-btn-sm', T('Pick waypoints on canvas'));
					bendBtn.type = 'button';

					bendBtn.addEventListener('click', function () {
						openLinkDialog.collect();

						// The whole group shares one routing: after switching to manual waypoints, go to the canvas and click empty space to add points
						family.forEach(function (item) {
							item.routing = 2;
							item.waypoints = normalizeWaypointList(item.waypoints);
						});

						state.bendLink = family[0];
						markDirty();
						closeDialog();
						renderCanvas();
					});

					routeWrap.appendChild(routeSel);
					routeWrap.appendChild(bendBtn);
					routeRow.td.appendChild(routeWrap);

					var routeHint = make('div', 'tm-hint');
					routeHint.textContent = T('Auto orthogonal routes along horizontal/vertical segments; when "manual waypoints" is selected, click the button, ')
						+ T('then click empty canvas to add points, drag dots to adjust, double-click a dot to delete, Esc to finish.');
					routeRow.td.appendChild(routeHint);

					// Trigger source: the link direction is source (selementid1) → local (selementid2).
					// Auto discovery matches port status triggers "source first" by default; here it can be changed to one end only,
					// both ends or none at all (clicking "Apply" re-matches immediately).
					var srcRow = formRow(tbody, T('Trigger source'), false);
					var srcSel = makeSelect(TRIGGER_SOURCES, num(draft.trigger_source, 0));
					srcSel.style.width = '220px';
					srcRow.td.appendChild(srcSel);

					// Port information is only in the tooltip for troubleshooting and no longer added to the source text:
					// the long list of port names widened the "trigger source" column and misaligned the dropdown on the right.
					var ports = familyPorts(family);
					srcSel.title = T('Source port: ') + (ports.src.length ? ports.src.join(', ') : T('unknown'))
						+ T('  Target port: ') + (ports.tgt.length ? ports.tgt.join(', ') : T('unknown'))
						+ T(' (source = link start, target = link end)');

					// Bandwidth / upstream / downstream: multi-select items manually from the hosts at both ends (aggregation members);
					// for display the latest values of the selected items are summed and then converted by unit.
					var rows = {};
					var metricRows = [];

					METRIC_KINDS.forEach(function (kind) {
						var defRow = formRow(tbody, kind.label, false);
						metricRows.push(defRow);
						var wrap = make('div', 'tm-metric-row');
						var summary = make('span', 'tm-metric-summary');
						var pick = make('button', 'tm-btn tm-btn-sm', T('Select…'));
						pick.type = 'button';
						var clearBtn = make('button', 'tm-btn tm-btn-sm', T('Clear'));
						clearBtn.type = 'button';

						function refresh() {
							summary.textContent = metricSummaryText(draft.metrics[kind.key]);
							summary.title = metricSummaryTitle(draft.metrics[kind.key]);
						}

						pick.addEventListener('click', function () {
							openItemPicker(kind.key, defaultPickHostids(link), draft.metrics[kind.key], function (itemids) {
								draft.metrics[kind.key] = itemids;
								refresh();
							});
						});

						clearBtn.addEventListener('click', function () {
							draft.metrics[kind.key] = [];
							refresh();
						});

						wrap.appendChild(summary);
						wrap.appendChild(pick);
						wrap.appendChild(clearBtn);
						defRow.td.appendChild(wrap);
						rows[kind.key] = refresh;
					});

					var tip = make('div', 'tm-hint');
					tip.textContent = T('Items are chosen only from the hosts at both ends of this link; for aggregated links you can select multiple member ports and the values are summed.');
					body.appendChild(tip);

					Object.keys(rows).forEach(function (key) {
						rows[key]();
					});

					// Link indicators: consistent with the native 8.0 link form.
					// Header "empty / trigger / empty": each row starts with the trigger's color and ends with "remove",
					// and an "add" link below opens the native popup.generic trigger multi-select popup.
					var trigRow = formRow(tbody, T('Metrics'), true);
					var trigTable = make('table', 'tm-trig-table');
					var trigHead = make('thead');
					var trigHeadRow = make('tr');
					var trigBody = make('tbody');

					trigHeadRow.appendChild(make('th', 'tm-trig-sev', ''));
					trigHeadRow.appendChild(make('th', null, T('Trigger')));
					trigHeadRow.appendChild(make('th', 'tm-trig-act', ''));
					trigHead.appendChild(trigHeadRow);
					trigTable.appendChild(trigHead);
					trigTable.appendChild(trigBody);
					trigRow.td.appendChild(trigTable);

					function renderTriggerTable() {
						clear(trigBody);

						(draft.linktriggers || []).forEach(function (lt) {
							var tid = String(lt.triggerid);
							var tr = make('tr');

							// First column: each trigger's own color (native linktrigger color, default DD0000)
							var tdColor = make('td', 'tm-trig-sev');
							var colorInput = make('input', 'tm-trig-color');
							colorInput.type = 'color';
							colorInput.value = '#' + String(lt.color || DEFAULT_TRIGGER_COLOR).replace('#', '');
							colorInput.title = TRIGGER_SEVERITY_LABELS[num(state.triggerSeverity[tid], 0)]
								|| (T('Severity ') + num(state.triggerSeverity[tid], 0));
							colorInput.addEventListener('input', function () {
								lt.color = colorInput.value.replace('#', '').toUpperCase();
							});
							tdColor.appendChild(colorInput);

							var tdName = make('td', 'tm-trig-name', triggerName(tid));
							tdName.title = triggerName(tid);

							var tdAct = make('td', 'tm-trig-act');
							var rm = make('button', 'tm-linkbtn', T('Remove'));
							rm.type = 'button';
							rm.addEventListener('click', function () {
								draft.linktriggers = (draft.linktriggers || []).filter(function (item) {
									return String(item.triggerid) !== tid;
								});
								triggersEdited = true;
								renderTriggerTable();
							});
							tdAct.appendChild(rm);

							tr.appendChild(tdColor);
							tr.appendChild(tdName);
							tr.appendChild(tdAct);
							trigBody.appendChild(tr);
						});
					}

					var trigAdd = make('button', 'tm-linkbtn tm-trig-add', T('Add'));
					trigAdd.type = 'button';
					trigRow.td.appendChild(trigAdd);

					// Values returned by the native trigger popup (popup.generic): appended after deduplicating by triggerid,
					// without rebuilding the existing list — native addNewTriggers behaves the same way.
					trigAdd.addEventListener('click', function () {
						openNativeTriggerPopup(function (values) {
							var known = {};

							(draft.linktriggers || []).forEach(function (lt) {
								known[String(lt.triggerid)] = true;
							});

							(values || []).forEach(function (value) {
								var tid = String(value.triggerid || value.id || '');

								if (!tid || known[tid]) {
									return;
								}

								known[tid] = true;

								if (value.description) {
									state.triggerNames[tid] = String(value.description);
								}

								if (value.priority !== undefined) {
									state.triggerSeverity[tid] = num(value.priority, 0);
								}

								draft.linktriggers.push({
									triggerid: tid,
									drawtype: 0,
									color: DEFAULT_TRIGGER_COLOR,
									problem: !!state.triggerProblems[tid]
								});
							});

							triggersEdited = true;
							renderTriggerTable();
						});
					});

					// Only triggerids are stored; names/severities are fetched once in bulk by triggerid and the table is refreshed
					resolveTriggerNames((draft.linktriggers || []).map(function (lt) {
						return String(lt.triggerid);
					})).then(renderTriggerTable);
					renderTriggerTable();

					// Field order follows the native link form: label → show label → type (OK) → color (OK)
					// → indicators (link indicators); module-specific source/routing/items come after.
					[labelRow, showRow, typeRow, colorRow, trigRow, srcRow, routeRow]
						.concat(metricRows).forEach(function (row) {
						tbody.appendChild(row.tr);
					});

					openLinkDialog.collect = function () {
						draft.label = labelInput.value;
						draft.color = colorInput.value.replace('#', '').toUpperCase() || DEFAULT_LINK_COLOR;
						draft.drawtype = num(typeSel.value, 0);
						draft.routing = Math.max(0, Math.min(2, num(routeSel.value, 0)));
						draft.trigger_source = num(srcSel.value, 0);
						draft.show_label = -1;

						(showRadios || []).forEach(function (radio) {
							if (radio.checked) {
								draft.show_label = num(radio.value, -1);
							}
						});
					};
				},
				buttons: [
					{label: T('Apply'), primary: true, onClick: function () {
						var previousTriggerSource = num(link.trigger_source, 0);

						openLinkDialog.collect();

						// The whole group changes together: label / show label / color / line style / trigger source / routing;
						// each member keeps its own bound triggers, and only additions/removals/line style changes made now are synced,
						// so link indicators unique to member A are not copied to member B (natively each link is independent).
						var draftTriggers = (draft.linktriggers || []).map(function (lt) {
							return {
								triggerid: String(lt.triggerid),
								drawtype: num(lt.drawtype, 0),
								color: String(lt.color || DEFAULT_TRIGGER_COLOR).replace('#', ''),
								problem: !!lt.problem
							};
						});
						var draftById = {};

						draftTriggers.forEach(function (lt) {
							draftById[lt.triggerid] = lt;
						});

						// The whole group shares one set of waypoints: on apply the same coordinates are written to each member (each saved by its linkid)
						var waypoints = normalizeWaypointList(draft.waypoints);

						family.forEach(function (item) {
							var ownIds = {};

							(item.linktriggers || []).forEach(function (lt) {
								ownIds[String(lt.triggerid)] = true;
							});

							var nextTriggers = [];

							// Triggers the member already had and that were not removed now: kept, applying the line style/color from the table
							(item.linktriggers || []).forEach(function (lt) {
								var tid = String(lt.triggerid);

								if (draftById[tid]) {
									nextTriggers.push(copy(draftById[tid]));
								}
							});

							// Triggers added now: synced to every member of the group
							draftTriggers.forEach(function (lt) {
								if (!ownIds[lt.triggerid] && !initialTriggerIds[lt.triggerid]) {
									nextTriggers.push(copy(lt));
								}
							});

							item.label = draft.label;
							item.show_label = draft.show_label;
							item.color = draft.color;
							item.drawtype = draft.drawtype;
							item.routing = draft.routing;
							item.waypoints = waypoints.map(function (p) {
								return {x: p.x, y: p.y};
							});
							item.trigger_source = draft.trigger_source;
							item.linktriggers = nextTriggers;
						});

						// Metrics are attached only to the first link of the group (the group shares one set), so the same items are not summed twice
						var metrics = normalizeMetrics(draft.metrics);

						family.forEach(function (item, i) {
							item.metrics = i === 0 ? metrics : normalizeMetrics(null);
						});

						markDirty();
						closeDialog();
						renderCanvas();

						// Re-match only when the user actually changed "trigger source": so a quick label/color change
						// does not wipe out triggers bound manually in the native interface.
						// If triggers were picked manually this time (select/clear/× on a chip), never re-match.
						if (!triggersEdited && num(link.trigger_source, 0) !== previousTriggerSource) {
							family.forEach(rematchLinkTriggers);
						}
					}},
					{label: T('Remove'), onClick: function () {
						closeDialog();
						removeLinkFamily(link);
					}},
					{label: T('Close'), onClick: closeDialog}
				]
			});
		}

		function buildColorInput(hex) {
			var wrap = make('div', 'tm-colorline');
			var picker = make('input');
			picker.type = 'color';
			picker.value = '#' + String(hex || '7A8B94').replace('#', '');
			var text = make('input', 'tm-input');
			text.type = 'text';
			text.value = String(hex || '').replace('#', '');
			text.maxLength = 6;

			picker.addEventListener('input', function () {
				text.value = picker.value.replace('#', '').toUpperCase();
			});

			text.addEventListener('input', function () {
				var v = text.value.replace(/[^0-9a-fA-F]/g, '').slice(0, 6);

				if (v.length === 6) {
					picker.value = '#' + v;
				}
			});

			wrap.appendChild(picker);
			wrap.appendChild(text);

			return {
				wrap: wrap,
				value: function () {
					var v = text.value.replace(/[^0-9a-fA-F]/g, '').toUpperCase();

					return v.length === 6 ? v : '';
				}
			};
		}

		// ---------------------------------------------------------------
		// Alert / confirmation dialogs
		// ---------------------------------------------------------------

		function openNoticeDialog(title, text) {
			openDialog({
				title: title,
				narrow: true,
				build: function (body) {
					var p = make('div', null, text);
					p.style.lineHeight = '1.6';
					body.appendChild(p);
				},
				buttons: [{label: T('Close'), primary: true, onClick: closeDialog}]
			});
		}

		function confirmDialog(title, text, okLabel) {
			return new Promise(function (resolve) {
				function done(value) {
					state.pendingConfirm = null;
					closeDialog();
					resolve(value);
				}

				state.pendingConfirm = function () {
					resolve(false);
				};

				openDialog({
					title: title,
					narrow: true,
					build: function (body) {
						var p = make('div', null, text);
						p.style.lineHeight = '1.6';
						body.appendChild(p);
					},
					buttons: [
						{label: okLabel || T('OK'), primary: true, onClick: function () { done(true); }},
						{label: T('Cancel'), onClick: function () { done(false); }}
					]
				});
			});
		}

		// ---------------------------------------------------------------
		// Host selector
		// ---------------------------------------------------------------

		function openHostPicker(callback) {
			state.hostPick = callback;
			setHidden(elHostOverlay, false);
			elHostSearch.value = '';
			loadHosts('');
			setTimeout(function () { elHostSearch.focus(); }, 0);
		}

		var hostSearchTimer = null;

		function loadHosts(search) {
			clear(elHostList);
			elHostList.appendChild(make('div', 'tm-host-empty', T('Loading…')));

			api('hosts', {search: search}).then(function (data) {
				clear(elHostList);

				if (!data.hosts.length) {
					elHostList.appendChild(make('div', 'tm-host-empty', T('No matching hosts')));
					return;
				}

				data.hosts.forEach(function (host) {
					var item = make('div', 'tm-host-item', host.name);

					if (host.ip) {
						item.appendChild(make('span', 'tm-host-ip', host.ip));
					}

					if (host.status === 1) {
						item.appendChild(make('span', 'tm-host-dim', T('Disabled')));
					}

					item.addEventListener('click', function () {
						setHidden(elHostOverlay, true);

						if (state.hostPick) {
							state.hostPick(host);
						}
					});

					elHostList.appendChild(item);
				});
			}).catch(function (e) {
				clear(elHostList);
				elHostList.appendChild(make('div', 'tm-host-empty', T('Loading failed: ') + e.message));
			});
		}

		elHostSearch.addEventListener('input', function () {
			if (hostSearchTimer) {
				clearTimeout(hostSearchTimer);
			}

			hostSearchTimer = setTimeout(function () {
				loadHosts(elHostSearch.value.trim());
			}, 250);
		});

		// ---------------------------------------------------------------
		// Item selector (link metrics: bandwidth / upstream / downstream)
		// ---------------------------------------------------------------

		function metricItemInfo(itemid) {
			return ((state.map && state.map.metric_items) || {})[String(itemid)] || null;
		}

		// Readable names of selected items: prefer metadata returned by the backend (map.metric_items already has configured items at load time).
		function metricItemLabel(itemid) {
			var info = metricItemInfo(itemid);

			if (!info) {
				return '#' + itemid + T(' (deleted or no access)');
			}

			return info.name + ' (' + (info.hostname ? info.hostname + ' · ' : '') + (info.key_ || '') + ')';
		}

		function metricSummaryText(itemids) {
			if (!itemids || !itemids.length) {
				return T('Not selected');
			}

			return itemids.length + T(' items');
		}

		function metricSummaryTitle(itemids) {
			if (!itemids || !itemids.length) {
				return '';
			}

			return itemids.map(metricItemLabel).join('\n');
		}

		// Display names of the item "type" / "information type" columns (aligned with the names in the native dropdowns).
		var ITEM_TYPE_LABELS = {
			0: T('Zabbix agent'), 1: T('Zabbix agent (active)'), 2: T('Simple check'), 3: T('SNMP agent'), 4: T('Zabbix trapper'),
			5: T('Simple check'), 7: T('Zabbix internal'), 9: T('External check'), 10: T('Database monitor'), 11: T('Aggregate'), 12: T('HTTP agent'),
			13: T('IPMI agent'), 14: T('SSH agent'), 15: T('TELNET agent'), 16: T('Calculated'), 17: T('JMX agent'), 18: T('Dependent item'),
			19: T('HTTP agent'), 20: T('SNMP trap'), 21: T('Script')
		};
		var VALUE_TYPE_LABELS = {0: T('Numeric (float)'), 1: T('Character'), 2: T('Log'), 3: T('Numeric (unsigned)'), 4: T('Text')};

		// Temporary selection state of the "host" / "host group" popups (rebuilt every time they open).
		var hpState = null;
		var ggState = null;

		// Empty state of popup lists: funnel icon + title + subtitle (aligned with the native popup empty state).
		function makeFunnelIcon() {
			var ns = 'http://www.w3.org/2000/svg';
			var svg = document.createElementNS(ns, 'svg');
			svg.setAttribute('class', 'tm-pick-empty-icon');
			svg.setAttribute('viewBox', '0 0 24 24');
			svg.setAttribute('width', '44');
			svg.setAttribute('height', '44');
			var path = document.createElementNS(ns, 'path');
			path.setAttribute('d', 'M3 5h18l-7 9v6l-4-2v-4z');
			path.setAttribute('fill', 'none');
			path.setAttribute('stroke', 'currentColor');
			path.setAttribute('stroke-width', '1.5');
			path.setAttribute('stroke-linejoin', 'round');
			svg.appendChild(path);

			return svg;
		}

		function pickEmptyRow(title, subtitle, colspan, withIcon) {
			var tr = make('tr', 'tm-pick-emptyrow');
			var td = make('td');
			td.colSpan = colspan;
			var box = make('div', 'tm-pick-empty');

			if (withIcon) {
				box.appendChild(makeFunnelIcon());
			}

			box.appendChild(make('div', 'tm-pick-empty-title', title));

			if (subtitle) {
				box.appendChild(make('div', 'tm-pick-empty-sub', subtitle));
			}

			td.appendChild(box);
			tr.appendChild(td);

			return tr;
		}

		// Generic "chip list" rendering, used by the host box and the host group box; each chip has an × to remove it individually.
		function renderChips(box, ids, names, emptyText, onRemove) {
			clear(box);

			if (!ids.length) {
				box.appendChild(make('span', 'tm-pick-empty-inline', emptyText));

				return;
			}

			ids.forEach(function (id) {
				var chip = make('span', 'tm-chip');
				chip.appendChild(make('span', 'tm-chip-name', names[id] || ('#' + id)));
				var x = make('button', 'tm-chip-x', '×');
				x.type = 'button';
				x.addEventListener('click', function (e) {
					e.stopPropagation();
					onRemove(id);
				});
				chip.appendChild(x);
				box.appendChild(chip);
			});
		}

		// Host display names: when the backend only returned hostids, fetch the names once in bulk by hostid (for chips).
		function resolveHostNames(hostids) {
			if (!hostids || !hostids.length) {
				return Promise.resolve({});
			}

			return api('hosts', {hostids: hostids.join(',')}).then(function (data) {
				var names = {};
				(data.hosts || []).forEach(function (host) {
					names[String(host.hostid)] = host.name;
				});

				return names;
			}).catch(function () {
				return {};
			});
		}

		function renderHostChips() {
			if (!state.itemPick) {
				return;
			}

			renderChips(elItemHostBox, state.itemPick.hostids, state.itemPick.hostNames, T('No filter set'),
				function (hostid) {
					state.itemPick.hostids = state.itemPick.hostids.filter(function (id) {
						return id !== hostid;
					});
					renderHostChips();
					loadPickerItems(elItemSearch.value.trim());
				});
		}

		function openItemPicker(kind, hostids, current, onDone) {
			var def = metricKind(kind) || {label: T('Item')};

			state.itemPick = {
				hostids: (hostids || []).map(String),
				hostNames: {},
				checked: {},
				fetched: [],
				done: onDone
			};

			(current || []).forEach(function (itemid) {
				state.itemPick.checked[String(itemid)] = true;
			});

			elItemTitle.textContent = def.label + T(' items');
			elItemSearch.value = '';
			elItemAll.checked = false;
			elItemAll.indeterminate = false;
			setHidden(elItemOverlay, false);
			renderHostChips();
			loadPickerItems('');

			// Host names on chips are fetched separately (hostids may include the local host, whose name is not known locally)
			resolveHostNames(state.itemPick.hostids).then(function (names) {
				if (!state.itemPick) {
					return;
				}

				state.itemPick.hostNames = names || {};
				renderHostChips();
			});

			setTimeout(function () { elItemSearch.focus(); }, 0);
		}

		function closeItemPicker() {
			closePickHost();
			closeGroupPicker();
			setHidden(elItemOverlay, true);
			clear(elItemList);
			state.itemPick = null;
		}

		function loadPickerItems(search) {
			if (!state.itemPick) {
				return;
			}

			clear(elItemList);

			if (!state.itemPick.hostids.length) {
				elItemList.appendChild(pickEmptyRow(T('No filter set'), T('Use the filter to show results'), 6, true));
				elItemAll.checked = false;
				elItemAll.indeterminate = false;

				return;
			}

			elItemList.appendChild(pickEmptyRow(T('Loading…'), '', 6, false));

			api('items', {hostids: state.itemPick.hostids.join(','), search: search}).then(function (data) {
				if (!state.itemPick) {
					return;
				}

				state.itemPick.fetched = data.items || [];
				renderPickerList();
			}).catch(function (e) {
				clear(elItemList);
				elItemList.appendChild(pickEmptyRow(T('Loading failed'), e.message, 6, false));
			});
		}

		function syncItemMaster() {
			var list = (state.itemPick && state.itemPick.fetched) || [];
			var n = list.filter(function (item) {
				return state.itemPick.checked[String(item.itemid)];
			}).length;

			elItemAll.checked = list.length > 0 && n === list.length;
			elItemAll.indeterminate = n > 0 && n < list.length;
		}

		function renderPickerList() {
			var list = (state.itemPick && state.itemPick.fetched) || [];

			clear(elItemList);

			if (!list.length) {
				elItemList.appendChild(pickEmptyRow(T('No filter set'), T('Use the filter to show results'), 6, true));
				elItemAll.checked = false;
				elItemAll.indeterminate = false;

				return;
			}

			list.forEach(function (item) {
				var itemid = String(item.itemid);
				var tr = make('tr');

				if (state.itemPick.checked[itemid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-item-cb-' + itemid;
				box.checked = !!state.itemPick.checked[itemid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggleItem(on) {
					if (on) {
						state.itemPick.checked[itemid] = true;
					}
					else {
						delete state.itemPick.checked[itemid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncItemMaster();
				}

				box.addEventListener('change', function () {
					toggleItem(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				tr.appendChild(tdCheck);
				tr.appendChild(make('td', 'tm-pick-name', item.name));
				tr.appendChild(make('td', 'tm-pick-key', item.key_ || ''));
				tr.appendChild(make('td', null, ITEM_TYPE_LABELS[item.type] || (T('Type ') + item.type)));
				tr.appendChild(make('td', null, VALUE_TYPE_LABELS[item.value_type] || (T('Type of information ') + item.value_type)));
				tr.appendChild(make('td', 'tm-pick-status ' + (num(item.status, 0) === 0 ? 'tm-on' : 'tm-off'),
					num(item.status, 0) === 0 ? T('Enabled') : T('Disabled')));

				// Clicking anywhere on the row also toggles it (except the checkbox/label itself, to avoid double triggering)
				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggleItem(box.checked);
				});

				elItemList.appendChild(tr);
			});

			syncItemMaster();
		}

		elItemOk.addEventListener('click', function () {
			if (!state.itemPick) {
				closeItemPicker();

				return;
			}

			var picked = Object.keys(state.itemPick.checked);
			var done = state.itemPick.done;

			closeItemPicker();

			if (done) {
				done(picked);
			}
		});

		elItemCancel.addEventListener('click', closeItemPicker);
		elItemClose.addEventListener('click', closeItemPicker);

		elItemOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elItemOverlay) {
				closeItemPicker();
			}
		});

		elItemAll.addEventListener('change', function () {
			if (!state.itemPick) {
				return;
			}

			var on = elItemAll.checked;

			(state.itemPick.fetched || []).forEach(function (item) {
				var itemid = String(item.itemid);

				if (on) {
					state.itemPick.checked[itemid] = true;
				}
				else {
					delete state.itemPick.checked[itemid];
				}
			});

			renderPickerList();
		});

		elItemHostPick.addEventListener('click', openPickHost);
		elItemHostBox.addEventListener('click', openPickHost);

		var itemSearchTimer = null;

		elItemSearch.addEventListener('input', function () {
			if (itemSearchTimer) {
				clearTimeout(itemSearchTimer);
			}

			itemSearchTimer = setTimeout(function () {
				if (state.itemPick) {
					loadPickerItems(elItemSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// Trigger selector (manual trigger binding on links)
		// ---------------------------------------------------------------

		var TRIGGER_SEVERITY_LABELS = [T('Not classified'), T('Information'), T('Warning'), T('Average'), T('High'), T('Disaster')];

		// Names of bound triggers: only triggerids are stored, so descriptions are fetched once in bulk by triggerid after opening the canvas
		function resolveTriggerNames(triggerids) {
			var missing = (triggerids || []).map(String).filter(function (id) {
				return id !== '' && !state.triggerNames[id];
			});

			if (!missing.length) {
				return Promise.resolve();
			}

			return api('hostTriggers', {triggerids: missing.join(',')}).then(function (data) {
				(data.triggers || []).forEach(function (trigger) {
					var desc = String(trigger.description || '');
					var host = String(trigger.hostname || '');
					// Native link indicators use desc_exp "host name: trigger name"; kept consistent here
					state.triggerNames[String(trigger.triggerid)] = host && desc.indexOf(host + ': ') !== 0
						? host + ': ' + desc
						: desc;
					state.triggerProblems[String(trigger.triggerid)] = num(trigger.value, 0) === 1;
					state.triggerSeverity[String(trigger.triggerid)] = num(trigger.priority, 0);
				});
			}).catch(function () {
				// If the name cannot be fetched, fall back to #id without affecting editing
			});
		}

		function triggerName(triggerid) {
			return state.triggerNames[String(triggerid)] || ('#' + triggerid);
		}

		// Native trigger multi-select popup (popup.generic). In 8.0 PopUp is an in-page overlay
		// that needs a .wrapper container; the module page layout has none, so one holding only the popup is added on demand.
		function ensurePopupHost() {
			var host = document.getElementById('tm-popup-host');

			if (host) {
				return host;
			}

			// In 7.0/7.4 the module page already has a native .wrapper, which must be reused directly.
			// With two .wrapper elements on the page, `$('.wrapper').append(...)` in the native menu
			// clones the menu DOM into each wrapper, and one click on a host opens two menus.
			var nativeHost = document.querySelector('.wrapper');

			if (nativeHost) {
				return nativeHost;
			}

			host = make('div', 'wrapper');
			host.id = 'tm-popup-host';
			document.body.appendChild(host);

			return host;
		}

		// "Filter" input for the native trigger popup: the 8.0 popup.generic trigger popup only has a "host" multi-select
		// and no filter box. A row is added below the host row that filters the table instantly by trigger name (names in the popup are prefixed with the host name),
		// combined with the server-side host filter; changing the host makes the native popup redraw completely, so a MutationObserver re-injects it.
		var triggerFilterQuery = '';
		var triggerPopupObserver = null;

		function nativeTriggerDialog() {
			var host = document.getElementById('tm-popup-host') || document.querySelector('.wrapper');

			if (!host) {
				return null;
			}

			// Only applies to the "trigger" popup (the table form id is triggers);
			// switching hosts in the host multi-select stacks a host popup on top, which must not get a filter box.
			var dialogs = host.querySelectorAll('.overlay-dialogue');
			var i;

			for (i = dialogs.length - 1; i >= 0; i--) {
				if (dialogs[i].querySelector('#triggers')) {
					return dialogs[i];
				}
			}

			return null;
		}

		function applyNativeTriggerFilter(dialog) {
			if (!dialog) {
				return;
			}

			var tbody = dialog.querySelector('.list-table tbody');

			if (!tbody) {
				return;
			}

			var query = triggerFilterQuery.toLowerCase();

			// The popup's host is single-select; searching by host name matches all of that host's triggers;
			// the trigger name is in each row's text but the host name may not be, so the selected host name is read separately.
			var hostMatch = false;

			Array.prototype.forEach.call(dialog.querySelectorAll('.multiselect .selected li'), function (chip) {
				var label = String(chip.getAttribute('data-label') || chip.innerText || '').toLowerCase();

				if (query && label.indexOf(query) !== -1) {
					hostMatch = true;
				}
			});

			Array.prototype.forEach.call(tbody.rows, function (row) {
				row.style.display = (!query || hostMatch || row.innerText.toLowerCase().indexOf(query) !== -1)
					? ''
					: 'none';
			});
		}

		function installNativeTriggerFilter() {
			var dialog = nativeTriggerDialog();

			if (!dialog) {
				return;
			}

			var list = dialog.querySelector('#generic-popup-form ul.table-forms');

			if (!list) {
				return;
			}

			var input = dialog.querySelector('#tm-popup-trigger-filter');

			if (!input) {
				var li = make('li');
				var left = make('div', 'table-forms-td-left');
				var label = make('label', null, T('Filter'));
				label.setAttribute('for', 'tm-popup-trigger-filter');
				left.appendChild(label);

				var right = make('div', 'table-forms-td-right');
				// Native .table-forms-td-right uses text-align:right (to right-align buttons),
				// while the filter box must sit right next to the label like a native filter row, so it is left-aligned here.
				right.style.textAlign = 'left';
				input = make('input', 'input');
				input.type = 'text';
				input.id = 'tm-popup-trigger-filter';
				input.autocomplete = 'off';
				input.placeholder = T('Type to search');
				input.style.width = '300px';
				right.appendChild(input);

				li.appendChild(left);
				li.appendChild(right);

				var hostRow = list.querySelector('li');
				list.insertBefore(li, hostRow ? hostRow.nextSibling : null);

				input.addEventListener('input', function () {
					triggerFilterQuery = input.value;
					applyNativeTriggerFilter(nativeTriggerDialog());
				});

				// The native popup table is inside a form and Enter would submit it; here it only filters
				input.addEventListener('keydown', function (event) {
					if (event.key === 'Enter') {
						event.preventDefault();
					}
				});
			}

			if (input.value !== triggerFilterQuery) {
				input.value = triggerFilterQuery;
			}

			applyNativeTriggerFilter(dialog);
		}

		function watchNativeTriggerPopup() {
			var host = ensurePopupHost();

			if (triggerPopupObserver || typeof MutationObserver !== 'function') {
				return;
			}

			var pending = false;
			triggerPopupObserver = new MutationObserver(function () {
				if (pending) {
					return;
				}

				pending = true;
				setTimeout(function () {
					pending = false;
					installNativeTriggerFilter();
				}, 0);
			});
			triggerPopupObserver.observe(host, {childList: true, subtree: true});
		}

		function openNativeTriggerPopup(onAdd) {
			state.linkTriggerAdd = onAdd;
			triggerFilterQuery = '';
			watchNativeTriggerPopup();

			if (typeof PopUp !== 'function') {
				return false;
			}

			// Parameters exactly as in the native map link form: multi-select, real hosts, with triggers
			PopUp('popup.generic', {
				srctbl: 'triggers',
				srcfld1: 'triggerid',
				reference: 'linktrigger',
				multiselect: '1',
				real_hosts: '1',
				with_triggers: '1'
			}, {dialogue_class: 'modal-popup-generic'});

			return true;
		}

		// Called by Zabbix core after the popup is confirmed (same convention as native addPopupValues)
		window.addPopupValues = function (data) {
			if (!data || data.object !== 'linktrigger' || typeof state.linkTriggerAdd !== 'function') {
				return;
			}

			state.linkTriggerAdd(data.values || []);
		};

		function openTriggerPicker(hostids, current, onDone) {
			state.trigPick = {
				hostids: (hostids || []).map(String),
				hostNames: {},
				groups: [],
				groupNames: {},
				checked: {},
				fetched: [],
				done: onDone
			};

			(current || []).forEach(function (lt) {
				state.trigPick.checked[String(lt.triggerid || lt)] = true;
			});

			elTrigSearch.value = '';
			elTrigAll.checked = false;
			elTrigAll.indeterminate = false;
			setHidden(elTrigOverlay, false);
			renderTriggerGroupChips();
			renderTriggerHostChips();
			loadTriggerList('');

			// Host names on chips are fetched separately (one of the two end hosts may have no name locally)
			resolveHostNames(state.trigPick.hostids).then(function (names) {
				if (!state.trigPick) {
					return;
				}

				state.trigPick.hostNames = names || {};
				renderTriggerHostChips();
			});

			setTimeout(function () { elTrigSearch.focus(); }, 0);
		}

		// Three filter rows of the trigger selector: host group / host / filter (aligned with the item selector)
		function renderTriggerGroupChips() {
			if (!state.trigPick) {
				return;
			}

			renderChips(elTrigGroupBox, state.trigPick.groups, state.trigPick.groupNames, T('No filter set'),
				function (groupid) {
					state.trigPick.groups = state.trigPick.groups.filter(function (id) {
						return id !== groupid;
					});
					renderTriggerGroupChips();
					loadTriggerList(elTrigSearch.value.trim());
				});
		}

		function renderTriggerHostChips() {
			if (!state.trigPick) {
				return;
			}

			renderChips(elTrigHostBox, state.trigPick.hostids, state.trigPick.hostNames, T('No filter set'),
				function (hostid) {
					state.trigPick.hostids = state.trigPick.hostids.filter(function (id) {
						return id !== hostid;
					});
					renderTriggerHostChips();
					loadTriggerList(elTrigSearch.value.trim());
				});
		}

		function closeTriggerPicker() {
			closePickHost();
			closeGroupPicker();
			setHidden(elTrigOverlay, true);
			clear(elTrigList);
			state.trigPick = null;
		}

		function loadTriggerList(search) {
			if (!state.trigPick) {
				return;
			}

			clear(elTrigList);

			if (!state.trigPick.hostids.length && !state.trigPick.groups.length) {
				elTrigList.appendChild(pickEmptyRow(T('No filter set'),
					T('Select a host or host group first, then pick triggers'), 5, true));
				elTrigAll.checked = false;
				elTrigAll.indeterminate = false;

				return;
			}

			elTrigList.appendChild(pickEmptyRow(T('Loading…'), '', 5, false));

			api('hostTriggers', {
				hostids: state.trigPick.hostids.join(','),
				groupids: state.trigPick.groups.join(','),
				search: search
			}).then(function (data) {
				if (!state.trigPick) {
					return;
				}

				state.trigPick.fetched = data.triggers || [];
				renderTriggerList();
			}).catch(function (e) {
				clear(elTrigList);
				elTrigList.appendChild(pickEmptyRow(T('Loading failed'), e.message, 5, false));
			});
		}

		function syncTriggerMaster() {
			var list = (state.trigPick && state.trigPick.fetched) || [];
			var n = list.filter(function (trigger) {
				return state.trigPick.checked[String(trigger.triggerid)];
			}).length;

			elTrigAll.checked = list.length > 0 && n === list.length;
			elTrigAll.indeterminate = n > 0 && n < list.length;
		}

		function renderTriggerList() {
			var list = (state.trigPick && state.trigPick.fetched) || [];

			clear(elTrigList);

			if (!list.length) {
				elTrigList.appendChild(pickEmptyRow(T('No matching triggers'), T('Try another keyword'), 5, true));
				elTrigAll.checked = false;
				elTrigAll.indeterminate = false;

				return;
			}

			list.forEach(function (trigger) {
				var tid = String(trigger.triggerid);
				var tr = make('tr');

				if (state.trigPick.checked[tid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-trig-cb-' + tid;
				box.checked = !!state.trigPick.checked[tid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggle(on) {
					if (on) {
						state.trigPick.checked[tid] = true;
					}
					else {
						delete state.trigPick.checked[tid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncTriggerMaster();
				}

				box.addEventListener('change', function () {
					toggle(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				// Severity: swatch + text, as in the native trigger list
				var sev = num(trigger.priority, 0);
				var tdSev = make('td');
				var dot = make('span', 'tm-sev-dot');
				dot.style.background = SEVERITY_COLORS[sev] || SEVERITY_COLORS[0];
				tdSev.appendChild(dot);
				tdSev.appendChild(document.createTextNode(
					TRIGGER_SEVERITY_LABELS[sev] || (T('Severity ') + sev)));

				tr.appendChild(tdCheck);
				tr.appendChild(make('td', 'tm-pick-name', trigger.description));
				tr.appendChild(tdSev);
				tr.appendChild(make('td', 'tm-pick-status ' + (num(trigger.status, 0) === 0 ? 'tm-on' : 'tm-off'),
					num(trigger.status, 0) === 0 ? T('Enabled') : T('Disabled')));
				tr.appendChild(make('td', null, trigger.hostname || ''));

				// Clicking anywhere on the row also toggles it (except the checkbox/label itself)
				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggle(box.checked);
				});

				// Checked triggers have their name/severity cached right away, so the table shows names and swatches immediately
				state.triggerNames[tid] = trigger.description;
				state.triggerProblems[tid] = num(trigger.value, 0) === 1;
				state.triggerSeverity[tid] = num(trigger.priority, 0);

				elTrigList.appendChild(tr);
			});

			syncTriggerMaster();
		}

		elTrigOk.addEventListener('click', function () {
			if (!state.trigPick) {
				closeTriggerPicker();

				return;
			}

			var picked = Object.keys(state.trigPick.checked);
			var list = state.trigPick.fetched || [];
			var done = state.trigPick.done;

			list.forEach(function (trigger) {
				state.triggerNames[String(trigger.triggerid)] = trigger.description;
				state.triggerSeverity[String(trigger.triggerid)] = num(trigger.priority, 0);
			});

			closeTriggerPicker();

			if (done) {
				done(picked);
			}
		});

		elTrigCancel.addEventListener('click', closeTriggerPicker);
		elTrigClose.addEventListener('click', closeTriggerPicker);

		elTrigGroupPick.addEventListener('click', openGroupPicker);
		elTrigGroupBox.addEventListener('click', openGroupPicker);
		elTrigHostPick.addEventListener('click', openPickHost);
		elTrigHostBox.addEventListener('click', openPickHost);

		elTrigOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elTrigOverlay) {
				closeTriggerPicker();
			}
		});

		elTrigAll.addEventListener('change', function () {
			if (!state.trigPick) {
				return;
			}

			var on = elTrigAll.checked;

			(state.trigPick.fetched || []).forEach(function (trigger) {
				var tid = String(trigger.triggerid);

				if (on) {
					state.trigPick.checked[tid] = true;
				}
				else {
					delete state.trigPick.checked[tid];
				}
			});

			renderTriggerList();
		});

		var triggerSearchTimer = null;

		elTrigSearch.addEventListener('input', function () {
			if (triggerSearchTimer) {
				clearTimeout(triggerSearchTimer);
			}

			triggerSearchTimer = setTimeout(function () {
				if (state.trigPick) {
					loadTriggerList(elTrigSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// "Host" popup: host group + select → host group popup; list with checkboxes
		// ---------------------------------------------------------------

		function renderGroupChips() {
			if (!hpState) {
				return;
			}

			renderChips(elHpGroupBox, hpState.groups, hpState.groupNames, T('No filter set'),
				function (groupid) {
					hpState.groups = hpState.groups.filter(function (id) {
						return id !== groupid;
					});
					renderGroupChips();
					loadPickHost(elHpSearch.value.trim());
				});
		}

		// Which popup is currently picking hosts (item selector / trigger selector)
		function activePick() {
			return state.itemPick || state.trigPick;
		}

		function openPickHost() {
			var pick = activePick();

			if (!pick) {
				return;
			}

			hpState = {checked: {}, fetched: [], groups: [], groupNames: {}};

			pick.hostids.forEach(function (hostid) {
				hpState.checked[hostid] = true;
			});

			elHpSearch.value = '';
			elHpAll.checked = false;
			elHpAll.indeterminate = false;
			renderGroupChips();
			setHidden(elHpOverlay, false);
			loadPickHost('');
			setTimeout(function () { elHpSearch.focus(); }, 0);
		}

		function closePickHost() {
			setHidden(elHpOverlay, true);
			clear(elHpList);
			hpState = null;
		}

		function loadPickHost(search) {
			if (!hpState) {
				return;
			}

			clear(elHpList);
			elHpList.appendChild(pickEmptyRow(T('Loading…'), '', 2, false));

			api('hosts', {search: search, groupids: hpState.groups.join(',')}).then(function (data) {
				if (!hpState) {
					return;
				}

				hpState.fetched = data.hosts || [];
				renderPickHostList();
			}).catch(function (e) {
				clear(elHpList);
				elHpList.appendChild(pickEmptyRow(T('Loading failed'), e.message, 2, false));
			});
		}

		function syncHpMaster() {
			var list = (hpState && hpState.fetched) || [];
			var n = list.filter(function (host) {
				return hpState.checked[String(host.hostid)];
			}).length;

			elHpAll.checked = list.length > 0 && n === list.length;
			elHpAll.indeterminate = n > 0 && n < list.length;
		}

		function renderPickHostList() {
			var list = (hpState && hpState.fetched) || [];

			clear(elHpList);

			if (!list.length) {
				elHpList.appendChild(pickEmptyRow(T('No filter set'), T('Use the filter to show results'), 2, true));
				elHpAll.checked = false;
				elHpAll.indeterminate = false;

				return;
			}

			list.forEach(function (host) {
				var hostid = String(host.hostid);
				var tr = make('tr');

				if (hpState.checked[hostid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-hp-cb-' + hostid;
				box.checked = !!hpState.checked[hostid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggleHost(on) {
					if (on) {
						hpState.checked[hostid] = true;
					}
					else {
						delete hpState.checked[hostid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncHpMaster();
				}

				box.addEventListener('change', function () {
					toggleHost(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				var tdName = make('td', 'tm-pick-name');
				tdName.appendChild(make('span', null, host.name));

				if (host.ip) {
					tdName.appendChild(make('span', 'tm-pick-dim', host.ip));
				}

				tr.appendChild(tdCheck);
				tr.appendChild(tdName);

				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggleHost(box.checked);
				});

				elHpList.appendChild(tr);
			});

			syncHpMaster();
		}

		elHpOk.addEventListener('click', function () {
			var pick = activePick();

			if (!pick || !hpState) {
				closePickHost();

				return;
			}

			var ids = Object.keys(hpState.checked);

			(hpState.fetched || []).forEach(function (host) {
				if (hpState.checked[String(host.hostid)]) {
					pick.hostNames[String(host.hostid)] = host.name;
				}
			});

			pick.hostids = ids;
			closePickHost();

			if (state.itemPick) {
				renderHostChips();
				loadPickerItems(elItemSearch.value.trim());
			}
			else {
				renderTriggerHostChips();
				loadTriggerList(elTrigSearch.value.trim());
			}
		});

		elHpCancel.addEventListener('click', closePickHost);
		elHpClose.addEventListener('click', closePickHost);

		elHpGroupPick.addEventListener('click', openGroupPicker);
		elHpGroupBox.addEventListener('click', openGroupPicker);

		elHpOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elHpOverlay) {
				closePickHost();
			}
		});

		elHpAll.addEventListener('change', function () {
			if (!hpState) {
				return;
			}

			var on = elHpAll.checked;

			(hpState.fetched || []).forEach(function (host) {
				var hostid = String(host.hostid);

				if (on) {
					hpState.checked[hostid] = true;
				}
				else {
					delete hpState.checked[hostid];
				}
			});

			renderPickHostList();
		});

		var hpSearchTimer = null;

		elHpSearch.addEventListener('input', function () {
			if (hpSearchTimer) {
				clearTimeout(hpSearchTimer);
			}

			hpSearchTimer = setTimeout(function () {
				if (hpState) {
					loadPickHost(elHpSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// "Host group" popup: search box + list with checkboxes
		// ---------------------------------------------------------------

		function openGroupPicker() {
			var owner = hpState || state.trigPick;

			if (!owner) {
				return;
			}

			ggState = {checked: {}, fetched: []};

			owner.groups.forEach(function (groupid) {
				ggState.checked[groupid] = true;
			});

			elGgSearch.value = '';
			elGgAll.checked = false;
			elGgAll.indeterminate = false;
			setHidden(elGgOverlay, false);
			loadGroupPicker('');
			setTimeout(function () { elGgSearch.focus(); }, 0);
		}

		function closeGroupPicker() {
			setHidden(elGgOverlay, true);
			clear(elGgList);
			ggState = null;
		}

		function loadGroupPicker(search) {
			if (!ggState) {
				return;
			}

			clear(elGgList);
			elGgList.appendChild(pickEmptyRow(T('Loading…'), '', 2, false));

			api('hostgroups', {search: search}).then(function (data) {
				if (!ggState) {
					return;
				}

				ggState.fetched = data.hostgroups || [];
				renderGroupPickerList();
			}).catch(function (e) {
				clear(elGgList);
				elGgList.appendChild(pickEmptyRow(T('Loading failed'), e.message, 2, false));
			});
		}

		function syncGgMaster() {
			var list = (ggState && ggState.fetched) || [];
			var n = list.filter(function (group) {
				return ggState.checked[String(group.groupid)];
			}).length;

			elGgAll.checked = list.length > 0 && n === list.length;
			elGgAll.indeterminate = n > 0 && n < list.length;
		}

		function renderGroupPickerList() {
			var list = (ggState && ggState.fetched) || [];

			clear(elGgList);

			if (!list.length) {
				elGgList.appendChild(pickEmptyRow(T('No filter set'), T('Use the filter to show results'), 2, true));
				elGgAll.checked = false;
				elGgAll.indeterminate = false;

				return;
			}

			list.forEach(function (group) {
				var groupid = String(group.groupid);
				var tr = make('tr');

				if (ggState.checked[groupid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-gg-cb-' + groupid;
				box.checked = !!ggState.checked[groupid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggleGroup(on) {
					if (on) {
						ggState.checked[groupid] = true;
					}
					else {
						delete ggState.checked[groupid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncGgMaster();
				}

				box.addEventListener('change', function () {
					toggleGroup(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				tr.appendChild(tdCheck);
				tr.appendChild(make('td', 'tm-pick-name', group.name));

				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggleGroup(box.checked);
				});

				elGgList.appendChild(tr);
			});

			syncGgMaster();
		}

		elGgOk.addEventListener('click', function () {
			var owner = hpState || state.trigPick;

			if (!owner || !ggState) {
				closeGroupPicker();

				return;
			}

			var ids = Object.keys(ggState.checked);

			(ggState.fetched || []).forEach(function (group) {
				if (ggState.checked[String(group.groupid)]) {
					owner.groupNames[String(group.groupid)] = group.name;
				}
			});

			owner.groups = ids;
			closeGroupPicker();

			if (hpState) {
				renderGroupChips();
				loadPickHost(elHpSearch.value.trim());
			}
			else {
				renderTriggerGroupChips();
				loadTriggerList(elTrigSearch.value.trim());
			}
		});

		elGgCancel.addEventListener('click', closeGroupPicker);
		elGgClose.addEventListener('click', closeGroupPicker);

		elGgOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elGgOverlay) {
				closeGroupPicker();
			}
		});

		elGgAll.addEventListener('change', function () {
			if (!ggState) {
				return;
			}

			var on = elGgAll.checked;

			(ggState.fetched || []).forEach(function (group) {
				var groupid = String(group.groupid);

				if (on) {
					ggState.checked[groupid] = true;
				}
				else {
					delete ggState.checked[groupid];
				}
			});

			renderGroupPickerList();
		});

		var ggSearchTimer = null;

		elGgSearch.addEventListener('input', function () {
			if (ggSearchTimer) {
				clearTimeout(ggSearchTimer);
			}

			ggSearchTimer = setTimeout(function () {
				if (ggState) {
					loadGroupPicker(elGgSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// Save / leave edit mode
		// ---------------------------------------------------------------

		function saveMap() {
			if (!state.map) {
				return;
			}

			// Before saving, shrink the canvas to the content so a map with only a few elements does not store a 3000×3000 empty board
			autoSizeCanvas();

			// Per-link metric settings (bandwidth / upstream / downstream items): existing links match directly by linkid;
			// new links have no linkid and are numbered by their position in the links array (new_seq);
			// the backend sorts the new linkids ascending and claims them in new_seq order.
			var linkMetrics = [];
			var newSeq = 0;

			state.map.links.forEach(function (link) {
				if (!link.linkid) {
					newSeq++;
				}

				var m = normalizeMetrics(link.metrics);
				var sourcePort = String(link.source_port || '');
				var targetPort = String(link.target_port || '');
				var triggerSource = num(link.trigger_source, 0);

				// Rows with empty metrics, end ports and trigger source are not stored; with ports/source they must be written,
				// otherwise the dialog and trigger re-matching would not find this information next time.
				if (!m.bw.length && !m.in.length && !m.out.length
						&& sourcePort === '' && targetPort === '' && triggerSource === 0) {
					return;
				}

				linkMetrics.push({
					linkid: link.linkid ? String(link.linkid) : '',
					new_seq: link.linkid ? 0 : newSeq,
					bw_itemids: m.bw,
					in_itemids: m.in,
					out_itemids: m.out,
					source_port: sourcePort,
					target_port: targetPort,
					trigger_source: triggerSource
				});
			});

			// Per-link routing (automatic orthogonal / manual waypoints): linkid / new_seq claimed with the same rules as metrics
			var linkPaths = [];
			var pathSeq = 0;

			state.map.links.forEach(function (link) {
				if (!link.linkid) {
					pathSeq++;
				}

				var routing = Math.max(0, Math.min(2, num(link.routing, 0)));
				var waypoints = normalizeWaypointList(link.waypoints);

				// "Automatic curve" must be stored too: discovered links default to automatic orthogonal, and if the user's change to automatic curve
				// were not written, the next open would render it as automatic orthogonal again.
				linkPaths.push({
					linkid: link.linkid ? String(link.linkid) : '',
					new_seq: link.linkid ? 0 : pathSeq,
					routing: routing,
					waypoints: waypoints
				});
			});

			var payload = {
				sysmapid: state.map.sysmapid,
				name: state.map.name,
				width: state.map.width,
				height: state.map.height,
				selements: state.map.selements.map(function (se) {
					var out = {
						selementid: se.selementid,
						elementtype: se.elementtype,
						iconid_off: se.iconid_off,
						iconid_on: se.iconid_on,
						iconid_disabled: se.iconid_disabled,
						iconid_maintenance: se.iconid_maintenance,
						label: se.label || '',
						label_location: se.label_location,
						x: se.x,
						y: se.y,
						use_iconmap: se.use_iconmap,
						evaltype: se.evaltype,
						tags: se.tags || [],
						urls: se.urls || []
					};

					if (se.elementtype !== ET_IMAGE) {
						out.elements = se.elements || [];
					}

					if (se.elementtype === ET_HOST || se.elementtype === ET_GROUP) {
						out.elementsubtype = se.elementsubtype;
						out.areatype = se.areatype;
						out.width = se.width;
						out.height = se.height;
						out.viewtype = se.viewtype;
					}

					return out;
				}),
				links: state.map.links.map(function (link) {
					var out = {
						selementid1: link.selementid1,
						selementid2: link.selementid2,
						label: link.label || '',
						show_label: num(link.show_label, -1),
						color: link.color || DEFAULT_LINK_COLOR,
						drawtype: link.drawtype
					};

					if (link.linkid) {
						out.linkid = link.linkid;
					}

					// Triggers bound to links (link indicators) must be written back unchanged: without them map.update clears them.
					// An empty array must be sent too: when the user clicks "clear" in the dialog, this empty array deletes the bindings in the database.
					out.linktriggers = (link.linktriggers || []).map(function (lt) {
						return {
							triggerid: lt.triggerid,
							drawtype: num(lt.drawtype, 0),
							color: String(lt.color || DEFAULT_TRIGGER_COLOR).replace('#', '')
						};
					});

					return out;
				}),
				link_metrics: linkMetrics,
				link_paths: linkPaths,
				shapes: state.map.shapes.map(function (shape) {
					var out = {
						type: shape.type,
						x: shape.x,
						y: shape.y,
						width: shape.width,
						height: shape.height,
						text: shape.text || '',
						font: shape.font,
						font_size: shape.font_size,
						font_color: shape.font_color,
						text_halign: shape.text_halign,
						text_valign: shape.text_valign,
						border_type: shape.border_type,
						border_width: shape.border_width,
						border_color: shape.border_color,
						background_color: shape.background_color || '',
						zindex: shape.zindex
					};

					if (shape.sysmap_shapeid) {
						out.sysmap_shapeid = shape.sysmap_shapeid;
					}

					return out;
				})
			};

			elSaveBtn.disabled = true;
			setHint(T('Saving…'));

			api('save', {sysmapid: state.map.sysmapid, payload: JSON.stringify(payload)}).then(function () {
				return openMap(state.map.sysmapid, true, true, 'none');
			}).then(function () {
				setHint(T('Saved ') + new Date().toLocaleTimeString(locale()));
			}).catch(function (e) {
				setHint(T('Save failed: ') + saveError(e.message));
				openNoticeDialog(T('Save failed'), saveError(e.message));
			}).then(function () {
				elSaveBtn.disabled = false;
			});
		}

		function exitEditing() {
			if (state.dirty) {
				confirmDialog(T('Discard unsaved changes?'), T('Exiting edit mode will discard unsaved changes.'), T('Discard changes')).then(function (ok) {
					if (ok) {
						state.dirty = false;
						setEditing(false);
						renderCanvas();
					}
				});

				return;
			}

			setEditing(false);
			renderCanvas();
		}

		// ---------------------------------------------------------------
		// Event binding
		// ---------------------------------------------------------------

		elCreateBtn.addEventListener('click', openCreateDialog);
		elDiscoverBtn.addEventListener('click', openDiscoverDialog);

		// List: search / sort / select all / bulk delete / export
		elFilterToggle.addEventListener('click', function (e) {
			e.preventDefault();
			var hidden = elFilterContainer.style.display === 'none';

			elFilterContainer.style.display = hidden ? '' : 'none';
		});
		document.getElementById('tm-filter-form').addEventListener('submit', function (e) {
			e.preventDefault();
			applyFilter();
		});
		document.getElementById('tm-filter-apply').addEventListener('click', function (e) {
			e.preventDefault();
			applyFilter();
		});
		document.getElementById('tm-filter-reset').addEventListener('click', resetFilter);
		elSortName.addEventListener('click', function (e) { e.preventDefault(); sortBy('name'); });
		elSortWidth.addEventListener('click', function (e) { e.preventDefault(); sortBy('width'); });
		elSortHeight.addEventListener('click', function (e) { e.preventDefault(); sortBy('height'); });
		elCheckAll.addEventListener('change', toggleCheckAll);
		elMassDelete.addEventListener('click', massDelete);
		elExportBtn.addEventListener('click', exportMaps);

		elBackBtn.addEventListener('click', backToList);
		elZoomIn.addEventListener('click', function () { zoomBy(1.2); });
		elZoomOut.addEventListener('click', function () { zoomBy(1 / 1.2); });
		elZoomReset.addEventListener('click', resetView);

		if (elFitBtn) {
			elFitBtn.addEventListener('click', fitView);
		}

		elRediscoverBtn.addEventListener('click', openRediscoverDialog);

		elEditBtn.addEventListener('click', function () {
			if (!state.canEdit) {
				openNoticeDialog(T('No permission'), T('Your role cannot create or edit maps (the role needs "Create and edit maps").'));

				return;
			}

			setEditing(true);
			renderCanvas();
		});

		elSaveBtn.addEventListener('click', saveMap);
		elExitBtn.addEventListener('click', exitEditing);

		elEditbar.addEventListener('click', function (e) {
			var button = e.target.closest('button[data-group]');

			if (!button) {
				return;
			}

			var group = button.getAttribute('data-group');
			var op = button.getAttribute('data-op');

			if (op === 'add') {
				clearModes();

				if (group === 'element') {
					addElement();
				}
				else if (group === 'shape') {
					addShape();
				}
				else {
					startLinkMode();
				}

				return;
			}

			if (group === 'link') {
				setRemoveMode('link');
			}
			else {
				setRemoveMode(group);
			}
		});

		elDialogClose.addEventListener('click', closeDialog);

		elOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elOverlay) {
				closeDialog();
			}
		});

		document.getElementById('tm-host-close').addEventListener('click', function () {
			setHidden(elHostOverlay, true);
		});
		document.getElementById('tm-host-cancel').addEventListener('click', function () {
			setHidden(elHostOverlay, true);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') {
				return;
			}

			// Three popup levels stack over the link properties dialog, and Esc closes them from the top down:
			// host group → host → item
			if (!elGgOverlay.hidden) {
				closeGroupPicker();
				return;
			}

			if (!elHpOverlay.hidden) {
				closePickHost();
				return;
			}

			if (!elItemOverlay.hidden) {
				closeItemPicker();
				return;
			}

			if (!elOverlay.hidden) {
				closeDialog();
				return;
			}

			if (!elHostOverlay.hidden) {
				setHidden(elHostOverlay, true);
				return;
			}

			if (state.bendLink) {
				exitBendMode();
				return;
			}

			if (state.removeMode || state.linkMode) {
				clearModes();
				renderCanvas();
			}
		});

		// Browser back / forward: the sysmapid in the address bar decides between canvas and list
		window.addEventListener('popstate', function (event) {
			var want = (event.state && event.state.tm_sysmapid) || '';
			var current = state.map ? String(state.map.sysmapid) : '';

			if (want === current) {
				return;
			}

			if (want) {
				openMap(want, false, false, 'none');
			}
			else if (state.map) {
				doBackToList('none');
			}
		});

		window.addEventListener('resize', function () {
			if (state.map) {
				layoutCanvasWrap();
				// When the window grows the canvas fills it, so no area on the right/bottom is left without canvas
				ensureCanvasFitsViewport();
				applyView();
			}
		});

		// ---------------------------------------------------------------
		// Startup
		// ---------------------------------------------------------------

		syncSortArrows();

		// Native menus/popups (menuPopup, Overlay) rely on a .wrapper in the page for positioning.
		// The module page has no native .wrapper, so the container is created up front; otherwise clicking a host in preview mode
		// would make menu positioning call getBoundingClientRect on undefined.
		ensurePopupHost();

		loadIcons().then(loadList);

		var initial = root.getAttribute('data-sysmapid') || '';

		if (initial) {
			// The server decides from the user profile whether to open the canvas directly; this is the initial page state, so no history entry is added
			openMap(initial, false, false, 'replace');
		}
		else {
			// List state: if a sysmapid remains in the address bar (edited link etc.), remove it so back/refresh does not jump to the canvas again
			syncViewUrl('', 'replace');
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	}
	else {
		start();
	}
})();
