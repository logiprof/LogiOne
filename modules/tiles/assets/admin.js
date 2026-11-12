(function ($) {
	'use strict';

	var config = window.logiprofEspaceEnseignantsAdmin || {};
	var strings = config.strings || {};
	var icons = config.icons || {};
	var roles = config.roles || {};
	var adminUrl = config.adminUrl || '/wp-admin/';
	var shortcode = config.shortcode || 'logipilot_tuile_contenu';
	var pages = config.pages || [];
	var defaultSettings = normalizeSettings(config.defaultSettings || {});
	var defaultAppearance = config.defaultAppearance || {};
	var menus = normalizeMenus(config.menus || []);
	var activeMenuId = String(config.activeMenuId || '');
	var active = null;
	var items = [];
	var collapsed = {};
	var dirty = false;
	var syncTimer = null;
	var applyingMenu = false;

	if (!menus.length) {
		menus.push({
			id: 'principal',
			name: 'Ressources',
			items: normalizeItems(config.items || []),
			settings: normalizeSettings(config.settings || {})
		});
	}

	active = menuById(activeMenuId) || menus[0];
	activeMenuId = active.id;
	items = cloneItems(active.items);

	function t(key, fallback) {
		return strings[key] || fallback || key;
	}

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"']/g, function (char) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[char];
		});
	}

	function toBool(value) {
		return value === true || value === 1 || value === '1';
	}

	function normalizeSettings(rawSettings) {
		var raw = rawSettings || {};

		return {
			theme: raw.theme === 'custom' ? 'custom' : 'logione',
			tile_text: String(raw.tile_text || ''),
			tile_color: String(raw.tile_color || 'menthe'),
			tile_icon: parseInt(raw.tile_icon || 0, 10) || 0,
			intro_text: String(raw.intro_text !== undefined ? raw.intro_text : ''),
			show_search: toBool(raw.show_search !== undefined ? raw.show_search : '1') ? '1' : '0',
			show_greeting: toBool(raw.show_greeting !== undefined ? raw.show_greeting : '1') ? '1' : '0',
			show_art: toBool(raw.show_art !== undefined ? raw.show_art : '1') ? '1' : '0',
			hero_color: /^#[0-9a-fA-F]{6}$/.test(String(raw.hero_color || '')) ? String(raw.hero_color) : '#313a45',
			tile_palette: (Array.isArray(raw.tile_palette) ? raw.tile_palette : []).filter(function (c) { return /^#[0-9a-fA-F]{6}$/.test(String(c)); }).slice(0, 8),
			block_title: String(raw.block_title !== undefined ? raw.block_title : 'Ressources'),
			show_title: toBool(raw.show_title !== undefined ? raw.show_title : '1') ? '1' : '0',
			primary_color: String(raw.primary_color || '#155e75'),
			container_bg: String(raw.container_bg || '#eef6f5'),
			item_bg: String(raw.item_bg || '#ffffff'),
			text_color: String(raw.text_color || '#183039'),
			max_width: parseInt(raw.max_width || 980, 10) || 980,
			radius: parseInt(raw.radius || 14, 10) || 14,
			spacing: raw.spacing === 'compact' ? 'compact' : 'comfortable',
			empty_message: String(raw.empty_message || 'Contenu bientôt disponible.'),
			single_open_siblings: toBool(raw.single_open_siblings) ? '1' : '0',
			restrict_menu_roles: toBool(raw.restrict_menu_roles) ? '1' : '0',
			allowed_roles: Array.isArray(raw.allowed_roles) ? raw.allowed_roles.map(String) : ['administrator']
		};
	}

	function normalizeMenus(rawMenus) {
		if (!Array.isArray(rawMenus)) {
			return [];
		}

		var seen = {};

		return rawMenus.map(function (menu, index) {
			var name = String(menu && menu.name ? menu.name : 'Menu ' + (index + 1));
			var id = String(menu && menu.id ? menu.id : uniqueMenuId(name, seen));

			id = slugifyMenuId(id) || uniqueMenuId(name, seen);

			if (seen[id]) {
				id = uniqueMenuId(name, seen);
			}

			seen[id] = true;

			return {
				id: id,
				name: name,
				items: normalizeItems(menu && menu.items ? menu.items : []),
				settings: normalizeSettings(menu && menu.settings ? menu.settings : {}),
				order: parseInt(menu && menu.order ? menu.order : index + 1, 10) || index + 1
			};
		}).sort(function (a, b) {
			return a.order - b.order;
		});
	}

	function normalizeItems(rawItems) {
		if (!Array.isArray(rawItems)) {
			return [];
		}

		return rawItems.map(function (item, index) {
			return {
				id: String(item.id || uniqueId()),
				type: ['link', 'text'].indexOf(item.type) !== -1 ? item.type : 'folder',
				title: String(item.title || ''),
				description: String(item.description || ''),
				icon: icons[item.icon] ? item.icon : 'book',
				parent_id: String(item.parent_id || ''),
				allowed_roles: Array.isArray(item.allowed_roles) ? item.allowed_roles.map(String) : [],
				hidden: toBool(item.hidden),
				open: toBool(item.open),
				include_menu: String(item.include_menu || ''),
				include_inline: toBool(item.include_inline),
				tile_color: ['auto', 'ink', 'green', 'white', 'yellow', 'mint', 'custom'].indexOf(item.tile_color) !== -1 ? item.tile_color : 'auto',
				tile_size: item.tile_size === 'wide' ? 'wide' : 'normal',
				tile_custom: /^#[0-9a-fA-F]{6}$/.test(String(item.tile_custom || '')) ? String(item.tile_custom) : '',
				icon_image: parseInt(item.icon_image || 0, 10) || 0,
				link_target: ['same', 'new'].indexOf(item.link_target) !== -1 ? item.link_target : 'auto',
				destination_type: ['url', 'admin', 'shortcode'].indexOf(item.destination_type) !== -1 ? item.destination_type : 'page',
				page_id: parseInt(item.page_id || 0, 10) || 0,
				url: String(item.url || ''),
				shortcode: String(item.shortcode || ''),
				content: String(item.content || ''),
				order: parseInt(item.order || index + 1, 10) || index + 1
			};
		});
	}

	function uniqueId() {
		return 'lpee_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
	}

	function slugifyMenuId(value) {
		var slug = String(value || '').toLowerCase();

		if (slug.normalize) {
			slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		}

		return slug.replace(/[^a-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 48);
	}

	function uniqueMenuId(name, seen) {
		var used = seen || menus.reduce(function (ids, menu) {
			ids[menu.id] = true;
			return ids;
		}, {});
		var base = slugifyMenuId(name) || 'menu';
		var id = base;
		var suffix = 2;

		while (used[id]) {
			id = base + '_' + suffix;
			suffix++;
		}

		return id;
	}

	function cloneItems(sourceItems) {
		var list = Array.isArray(sourceItems) ? sourceItems : [];

		return normalizeItems(list.map(function (item) {
			var copy = $.extend({}, item);
			copy.allowed_roles = Array.isArray(item.allowed_roles) ? item.allowed_roles.slice() : [];
			return copy;
		}));
	}

	function cloneSettings(settings) {
		return normalizeSettings($.extend(true, {}, settings || {}));
	}

	function menuById(id) {
		id = String(id || '');

		for (var i = 0; i < menus.length; i++) {
			if (menus[i].id === id) {
				return menus[i];
			}
		}

		return null;
	}

	function activeMenu() {
		active = menuById(activeMenuId) || menus[0] || null;
		activeMenuId = active ? active.id : '';
		return active;
	}

	function itemIndex(id) {
		for (var i = 0; i < items.length; i++) {
			if (items[i].id === id) {
				return i;
			}
		}

		return -1;
	}

	function findItem(id) {
		var index = itemIndex(id);
		return index >= 0 ? items[index] : null;
	}

	function sortByOrder(a, b) {
		if (a.order === b.order) {
			return a.title.localeCompare(b.title);
		}

		return a.order - b.order;
	}

	/** Menus que l'on peut reprendre dans une rubrique (tous sauf le menu en cours). */
	function populateIncludeSelect(selected) {
		var $select = $('#lpee-field-include-menu');
		$select.empty().append($('<option value=""></option>').text('Aucun (contenu propre seulement)'));
		menus.forEach(function (menu) {
			if (menu.id !== activeMenuId) {
				$select.append($('<option></option>').val(menu.id).text(menu.name));
			}
		});
		$select.val(selected && menus.some(function (m) { return m.id === selected && m.id !== activeMenuId; }) ? selected : '');
	}

	function includeName(id) {
		var found = menus.filter(function (m) { return m.id === id; })[0];
		return found ? found.name : id;
	}

	function includedTopCount(id) {
		var found = menus.filter(function (m) { return m.id === id; })[0];
		return found ? (found.items || []).filter(function (i) { return !i.parent_id && !toBool(i.hidden); }).length : 0;
	}

	/** Texte d'accueil : seulement la mise en forme permise (comme sur le site). */
	function safeIntro(text) {
		var allowed = { STRONG: 1, B: 1, EM: 1, I: 1, U: 1, MARK: 1, BR: 1, P: 1, UL: 1, OL: 1, LI: 1, A: 1, H1: 1, H2: 1, H3: 1, H4: 1 };
		var doc = new DOMParser().parseFromString('<div>' + String(text || '') + '</div>', 'text/html');
		(function clean(node) {
			Array.prototype.slice.call(node.childNodes).forEach(function (child) {
				if (child.nodeType === 1) {
					if (!allowed[child.tagName]) {
						clean(child);
						while (child.firstChild) { node.insertBefore(child.firstChild, child); }
						node.removeChild(child);
						return;
					}
					Array.prototype.slice.call(child.attributes).forEach(function (attr) {
						var ok = child.tagName === 'A' && (attr.name === 'href' && !/^\s*javascript:/i.test(attr.value) || attr.name === 'target');
						if (!ok) { child.removeAttribute(attr.name); }
					});
					clean(child);
				} else if (child.nodeType !== 3) {
					node.removeChild(child);
				}
			});
		}(doc.body.firstChild));
		var html = doc.body.firstChild.innerHTML;
		return /<(p|ul|ol|h[1-4])\b/i.test(html) ? html : html.split(/\n{2,}/).map(function (part) { return '<p>' + part.replace(/\n/g, '<br>') + '</p>'; }).join('');
	}

	/* ---- Éditeurs visuels (texte d'accueil, blocs de texte) : type de texte, gras, italique, souligné, surligné, lien, liste ---- */
	function richAreas() {
		return $('textarea[data-lpee-rich]');
	}

	function richOf(el) {
		return $(el).closest('.lpee-rich-field').find('.lpee-rich').get(0) || null;
	}

	function areaOf(rich) {
		return $(rich).closest('.lpee-rich-field').find('textarea[data-lpee-rich]');
	}

	/** Recharge chaque éditeur visuel depuis son champ (ou seulement celui de « area »). */
	function syncRichFromTextarea(area) {
		(area ? $(area) : richAreas()).each(function () {
			var rich = richOf(this);
			if (rich) {
				$(rich).html(safeIntro($(this).val()));
			}
		});
	}

	function syncTextareaFromRich(rich) {
		var $areas = rich ? areaOf(rich) : richAreas();
		$areas.each(function () {
			var r = richOf(this);
			if (r) {
				$(this).val(safeIntro($(r).html()).replace(/<p><\/p>/g, '')).trigger('input');
			}
		});
	}

	$(function () {
		richAreas().each(function () {
			var $area = $(this);
			var label = $area.closest('.lpee-rich-field').children('span').first().text() || 'Texte';
			var $rich = $('<div class="lpee-rich" contenteditable="true" role="textbox" aria-multiline="true"></div>').attr('aria-label', label);
			$area.hide().before($rich);
			$rich.on('input blur', function () { syncTextareaFromRich(this); });
			$rich.on('paste', function (event) {
				// Coller du texte simple (pas la mise en forme d'un autre site)
				var text = (event.originalEvent.clipboardData || window.clipboardData).getData('text/plain');
				event.preventDefault();
				document.execCommand('insertText', false, text);
			});
		});
		syncRichFromTextarea();
		try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* ancien navigateur */ }
	});

	/* Type de texte du paragraphe où se trouve le curseur : Paragraphe, Titre 1 à 4. */
	function currentBlock(rich) {
		var sel = window.getSelection();
		if (!rich || !sel.rangeCount) { return ''; }
		var node = sel.getRangeAt(0).startContainer;
		node = node.nodeType === 1 ? node : node.parentElement;
		if (!node || !rich.contains(node)) { return ''; }
		var block = node.closest('h1, h2, h3, h4, p, li');
		return block && rich.contains(block) ? block.tagName.toLowerCase() : 'p';
	}
	$(document).on('selectionchange', function () {
		var sel = window.getSelection();
		if (!sel.rangeCount) { return; }
		var node = sel.getRangeAt(0).startContainer;
		var rich = $(node.nodeType === 1 ? node : node.parentElement).closest('.lpee-rich').get(0);
		var tag = currentBlock(rich);
		if (tag && tag !== 'li') { $(rich).closest('.lpee-rich-field').find('[data-lpee-block]').val(tag); }
	});
	$(document).on('change', '[data-lpee-block]', function () {
		var rich = richOf(this);
		if (!rich) { return; }
		var tag = $(this).val();
		rich.focus();
		var saved = $(this).data('lpeeRange');
		if (saved && rich.contains(saved.startContainer)) {
			var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(saved);
		}
		document.execCommand('formatBlock', false, '<' + tag + '>');
		syncTextareaFromRich(rich);
	});
	// Mémoriser la sélection avant d'ouvrir la liste (le clic sur la liste fait perdre le curseur).
	$(document).on('mousedown focus', '[data-lpee-block]', function () {
		var rich = richOf(this), sel = window.getSelection();
		if (rich && sel.rangeCount && rich.contains(sel.getRangeAt(0).startContainer)) {
			$(this).data('lpeeRange', sel.getRangeAt(0).cloneRange());
		}
	});

	$(document).on('mousedown', '[data-lpee-format]', function (event) {
		event.preventDefault(); // garder la sélection dans l'éditeur
	});

	$(document).on('click', '[data-lpee-format]', function (event) {
		event.preventDefault();
		var tag = $(this).attr('data-lpee-format');
		var rich = richOf(this);
		if (!rich) {
			return;
		}
		rich.focus();
		if (tag === 'strong') {
			document.execCommand('bold');
		} else if (tag === 'em') {
			document.execCommand('italic');
		} else if (tag === 'u') {
			document.execCommand('underline');
		} else if (tag === 'ul') {
			document.execCommand('insertUnorderedList');
		} else if (tag === 'a') {
			var url = window.prompt('Adresse du lien (https://…)', 'https://');
			if (url && /^(https?:|mailto:|\/)/i.test(url.trim())) {
				document.execCommand('createLink', false, url.trim());
			}
		} else if (tag === 'mark') {
			var sel = window.getSelection();
			if (sel.rangeCount && !sel.isCollapsed) {
				var range = sel.getRangeAt(0);
				var parentMark = range.commonAncestorContainer.parentElement && range.commonAncestorContainer.parentElement.closest('mark');
				if (parentMark && rich.contains(parentMark)) {
					while (parentMark.firstChild) { parentMark.parentNode.insertBefore(parentMark.firstChild, parentMark); }
					parentMark.parentNode.removeChild(parentMark);
				} else {
					var mark = document.createElement('mark');
					mark.appendChild(range.extractContents());
					range.insertNode(mark);
				}
			}
		}
		syncTextareaFromRich(rich);
	});

	function childrenOf(parentId) {
		return items.filter(function (item) {
			return item.parent_id === String(parentId || '');
		}).sort(sortByOrder);
	}

	function descendantsOf(id) {
		var found = [];

		childrenOf(id).forEach(function (child) {
			found.push(child.id);
			found = found.concat(descendantsOf(child.id));
		});

		return found;
	}

	function hasChildren(id) {
		return childrenOf(id).length > 0;
	}

	function wouldCreateCycle(id, parentId) {
		if (!id || !parentId) {
			return false;
		}

		return descendantsOf(id).indexOf(parentId) !== -1 || id === parentId;
	}

	function normalizeAllOrders() {
		var parents = {};

		items.forEach(function (item) {
			parents[item.parent_id] = true;
		});

		Object.keys(parents).forEach(function (parentId) {
			childrenOf(parentId).forEach(function (child, index) {
				child.order = index + 1;
			});
		});
	}

	function saveActiveMenuState() {
		var menu = activeMenu();

		if (!menu) {
			return;
		}

		normalizeAllOrders();
		menu.items = cloneItems(items);
		menu.settings = currentSettings();
	}

	function serializePayload() {
		saveActiveMenuState();
		$('#lpee-items-payload').val(JSON.stringify(items));
		$('#lpee-menus-payload').val(JSON.stringify(menus.map(function (menu, index) {
			return {
				id: menu.id,
				name: menu.name,
				items: menu.items,
				settings: menu.settings,
				order: index + 1
			};
		})));
		$('#lpee-active-menu-id').val(activeMenuId);
	}

	function markDirty() {
		dirty = true;
		serializePayload();
		renderPreview();
	}

	function menuShortcode(id) {
		return '[' + shortcode + ' menu="' + id + '"]';
	}

	function renderMenuControls() {
		var menu = activeMenu();
		var $select = $('#lpee-menu-select');

		if (!$select.length || !menu) {
			return;
		}

		$select.empty();

		menus.forEach(function (candidate) {
			$select.append($('<option></option>').attr('value', candidate.id).text(candidate.name));
		});

		$select.val(menu.id);
		if (!$('#lpee-menu-name').is(':focus')) {
			$('#lpee-menu-name').val(menu.name);
		}
		$('#lpee-menu-id').val(menu.id);
		$('#lpee-menu-shortcode').val(menuShortcode(menu.id));
		$('#lpee-menu-delete').prop('disabled', menus.length <= 1);
	}

	function applySettings(settings) {
		var normalized = cloneSettings(settings);

		applyingMenu = true;
		$('[name="lpee_settings[theme]"]').val(normalized.theme);
		$('[name="lpee_settings[tile_text]"]').val(normalized.tile_text);
		$('[name="lpee_settings[tile_color]"]').val(normalized.tile_color);
		setMediaField('#lpee-menu-tile-icon', '#lpee-menu-tile-icon-preview', normalized.tile_icon);
		$('[name="lpee_settings[intro_text]"]').val(normalized.intro_text);
		syncRichFromTextarea();
		var palette = normalized.tile_palette.length ? normalized.tile_palette : defaultPalette();
		$('[name="lpee_settings[tile_palette][]"]').each(function (i) {
			$(this).val(palette[i] || '#ffffff');
		});
		['show_search', 'show_greeting', 'show_art'].forEach(function (key) {
			$('[name="lpee_settings[' + key + ']"]').prop('checked', normalized[key] === '1');
		});
		$('#lpee-hero-color').val(normalized.hero_color);
		$('[name="lpee_settings[block_title]"]').val(normalized.block_title);
		$('[name="lpee_settings[show_title]"]').prop('checked', normalized.show_title === '1');
		$('[name="lpee_settings[primary_color]"]').val(normalized.primary_color);
		$('[name="lpee_settings[container_bg]"]').val(normalized.container_bg);
		$('[name="lpee_settings[item_bg]"]').val(normalized.item_bg);
		$('[name="lpee_settings[text_color]"]').val(normalized.text_color);
		$('[name="lpee_settings[max_width]"]').val(normalized.max_width);
		$('[name="lpee_settings[radius]"]').val(normalized.radius);
		$('[name="lpee_settings[spacing]"]').val(normalized.spacing);
		$('[name="lpee_settings[empty_message]"]').val(normalized.empty_message);
		$('[name="lpee_settings[single_open_siblings]"]').prop('checked', normalized.single_open_siblings === '1');
		$('#lpee-field-restrict-menu-roles').prop('checked', normalized.restrict_menu_roles === '1');
		$('[name="lpee_settings[allowed_roles][]"]').prop('checked', false).each(function () {
			$(this).prop('checked', normalized.allowed_roles.indexOf(String($(this).val())) !== -1);
		});
		toggleMenuRoleRestrictionFields();
		applyingMenu = false;
	}

	function switchMenu(id) {
		var next = menuById(id);

		if (!next || next.id === activeMenuId) {
			renderMenuControls();
			return;
		}

		saveActiveMenuState();
		activeMenuId = next.id;
		items = cloneItems(next.items);
		collapsed = {};
		applySettings(next.settings);
		renderMenuControls();
		renderTree();
	}

	function createMenu() {
		var name = window.prompt(t('menuNamePrompt', 'Nom du menu'), t('newMenuName', 'Nouveau menu'));

		if (!name || !name.trim()) {
			return;
		}

		saveActiveMenuState();

		var id = uniqueMenuId(name.trim());
		menus.push({
			id: id,
			name: name.trim(),
			items: [],
			settings: cloneSettings(defaultSettings),
			order: menus.length + 1
		});

		activeMenuId = id;
		items = [];
		collapsed = {};
		applySettings(defaultSettings);
		renderMenuControls();
		renderTree();
		markDirty();
	}

	function duplicateMenu() {
		var source = activeMenu();

		if (!source) {
			return;
		}

		saveActiveMenuState();

		var defaultName = t('duplicateMenuName', 'Copie de %s').replace('%s', source.name);
		var name = window.prompt(t('menuNamePrompt', 'Nom du menu'), defaultName);

		if (!name || !name.trim()) {
			return;
		}

		var id = uniqueMenuId(name.trim());
		menus.push({
			id: id,
			name: name.trim(),
			items: cloneItems(source.items),
			settings: cloneSettings(source.settings),
			order: menus.length + 1
		});

		activeMenuId = id;
		items = cloneItems(source.items);
		collapsed = {};
		applySettings(source.settings);
		renderMenuControls();
		renderTree();
		markDirty();
	}

	function deleteMenu() {
		var menu = activeMenu();

		if (!menu) {
			return;
		}

		if (menus.length <= 1) {
			alert(t('lastMenu', 'Il faut conserver au moins un menu.'));
			return;
		}

		if (!window.confirm(t('deleteMenu', 'Supprimer ce menu ?'))) {
			return;
		}

		menus = menus.filter(function (candidate) {
			return candidate.id !== menu.id;
		});

		activeMenuId = menus[0].id;
		items = cloneItems(menus[0].items);
		collapsed = {};
		applySettings(menus[0].settings);
		renderMenuControls();
		renderTree();
		markDirty();
	}

	function renderTree() {
		var $tree = $('#lpee-tree');
		var $list = renderAdminList('', 0);

		$tree.empty();

		if (!$list.children().length) {
			$tree.append($('<p class="lpee-admin-empty"></p>').text(t('emptyMenu', 'Aucun élément pour le moment.')));
		} else {
			$tree.append($list);
		}

		initSortables();
		serializePayload();
		renderPreview();
	}

	function renderAdminList(parentId, level) {
		var $list = $('<ol class="lpee-admin-list"></ol>')
			.attr('data-parent-id', parentId || '')
			.attr('data-level', level);

		childrenOf(parentId).forEach(function (item) {
			$list.append(renderAdminNode(item, level));
		});

		return $list;
	}

	function renderAdminNode(item, level) {
		var children = childrenOf(item.id);
		var isFolder = item.type === 'folder';
		var isCollapsed = collapsed[item.id] === true;
		var $node = $('<li class="lpee-admin-node"></li>').attr('data-id', item.id);
		var $card = $('<div class="lpee-admin-card"></div>')
			.toggleClass('is-hidden', item.hidden)
			.toggleClass('is-link', !isFolder);
		var $left = $('<div class="lpee-admin-card-main"></div>');
		var $collapse = $('<button type="button" class="lpee-admin-icon-button" data-action="collapse"></button>')
			.attr('aria-expanded', isCollapsed ? 'false' : 'true')
			.attr('title', isCollapsed ? 'Déplier' : 'Replier');

		if (isFolder) {
			$collapse.append($('<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'));
		} else {
			$collapse.addClass('is-placeholder').attr('aria-hidden', 'true').attr('tabindex', '-1');
		}

		$left.append($collapse);
		$left.append($('<span class="lpee-drag-handle" title="Déplacer"></span>').append($('<span class="dashicons dashicons-menu" aria-hidden="true"></span>')));
		$left.append($('<span class="lpee-admin-item-icon dashicons"></span>').addClass(iconClass(item.icon)).attr('aria-hidden', 'true'));

		var $copy = $('<div class="lpee-admin-copy"></div>');
		$copy.append($('<strong></strong>').text(item.title));

		var meta = [
			isFolder ? t('folder', 'Rubrique') : (item.type === 'text' ? 'Texte' : t('link', 'Lien')),
			item.allowed_roles.length ? 'Restriction: ' + item.allowed_roles.map(roleLabel).join(', ') : '',
			children.length ? children.length + ' élément(s)' : '',
			item.include_menu ? (item.include_inline ? 'Insère directement le menu « ' : 'Reprend le menu « ') + includeName(item.include_menu) + ' »' : '',
			item.hidden ? t('hidden', 'Masqué') : ''
		].filter(Boolean).join(' · ');

		$copy.append($('<span></span>').text(meta));

			if (item.description) {
				$copy.append($('<em></em>').text(item.description));
			}

			$copy.append($('<code class="lpee-item-shortcode"></code>').text(directShortcode(item.id)));

		$left.append($copy);
		$card.append($left);

		var $actions = $('<div class="lpee-admin-actions"></div>');
		addActionButton($actions, 'move-up', t('moveUp', 'Monter'), 'dashicons-arrow-up-alt2');
		addActionButton($actions, 'move-down', t('moveDown', 'Descendre'), 'dashicons-arrow-down-alt2');
		addActionButton($actions, 'edit', t('edit', 'Modifier'), 'dashicons-edit');
		addActionButton($actions, 'toggle-visibility', item.hidden ? t('show', 'Réafficher') : t('hide', 'Masquer'), item.hidden ? 'dashicons-visibility' : 'dashicons-hidden');

		if (isFolder) {
			addActionButton($actions, 'add-folder', t('addFolder', 'Ajouter une rubrique'), 'dashicons-plus-alt2');
			addActionButton($actions, 'add-link', t('addLink', 'Ajouter un lien'), 'dashicons-admin-links');
			addActionButton($actions, 'add-text', 'Ajouter un texte', 'dashicons-editor-paragraph');
		}

		addActionButton($actions, 'delete', t('delete', 'Supprimer'), 'dashicons-trash');
		$card.append($actions);
		$node.append($card);

		if (isFolder) {
			var $children = renderAdminList(item.id, level + 1);
			$children.toggle(!isCollapsed);
			$node.append($children);
		}

		return $node;
	}

	function addActionButton($target, action, label, iconClassName) {
		var $button = $('<button type="button" class="button lpee-action-button"></button>')
			.attr('data-action', action)
			.attr('title', label);

		$button.append($('<span class="dashicons" aria-hidden="true"></span>').addClass(iconClassName));
		$button.append($('<span></span>').text(label));
		$target.append($button);
	}

	function iconClass(icon) {
		return icons[icon] ? icons[icon].class : icons.book.class;
	}

	function initSortables() {
		if (!$.fn.sortable) {
			return;
		}

		$('.lpee-admin-list').each(function () {
			var $list = $(this);

			if ($list.data('ui-sortable')) {
				$list.sortable('destroy');
			}
		});

		$('.lpee-admin-list').sortable({
			connectWith: '.lpee-admin-list',
			handle: '.lpee-drag-handle',
			placeholder: 'lpee-sort-placeholder',
			tolerance: 'pointer',
			start: function (event, ui) {
				ui.placeholder.height(ui.item.outerHeight());
			},
			receive: function (event, ui) {
				var newParentId = String($(this).attr('data-parent-id') || '');
				var movedId = String(ui.item.attr('data-id') || '');

				if (wouldCreateCycle(movedId, newParentId)) {
					alert(t('invalidParent', 'Ce parent n’est pas autorisé.'));
					$(ui.sender).sortable('cancel');
				}
			},
			update: scheduleSyncFromDom
		});
	}

	function scheduleSyncFromDom() {
		window.clearTimeout(syncTimer);
		syncTimer = window.setTimeout(function () {
			syncFromDom();
			renderTree();
			dirty = true;
		}, 30);
	}

	function syncFromDom() {
		function walk($list, parentId) {
			$list.children('.lpee-admin-node').each(function (index) {
				var id = String($(this).attr('data-id') || '');
				var item = findItem(id);

				if (!item) {
					return;
				}

				item.parent_id = String(parentId || '');
				item.order = index + 1;
				walk($(this).children('.lpee-admin-list').first(), item.id);
			});
		}

		walk($('#lpee-tree > .lpee-admin-list').first(), '');
		serializePayload();
	}

	function moveItem(id, direction) {
		var item = findItem(id);

		if (!item) {
			return;
		}

		var siblings = childrenOf(item.parent_id);
		var index = siblings.findIndex(function (sibling) {
			return sibling.id === id;
		});
		var swapIndex = index + direction;

		if (index < 0 || swapIndex < 0 || swapIndex >= siblings.length) {
			return;
		}

		var currentOrder = siblings[index].order;
		siblings[index].order = siblings[swapIndex].order;
		siblings[swapIndex].order = currentOrder;
		renderTree();
		markDirty();
	}

	function deleteItem(id) {
		var item = findItem(id);

		if (!item) {
			return;
		}

		var descendantIds = descendantsOf(id);
		var message = descendantIds.length ? t('deleteWithChildren', 'Supprimer aussi les descendants ?') : t('deleteSimple', 'Supprimer cet élément ?');

		if (!window.confirm(message)) {
			return;
		}

		var toDelete = descendantIds.concat([id]);
		items = items.filter(function (candidate) {
			return toDelete.indexOf(candidate.id) === -1;
		});

		renderTree();
		markDirty();
	}

	function toggleVisibility(id) {
		var item = findItem(id);

		if (!item) {
			return;
		}

		item.hidden = !item.hidden;
		renderTree();
		markDirty();
	}

	function openEditor(id, preferredType, preferredParent) {
		var item = id ? findItem(id) : null;
		var isNew = !item;
		var type = item ? item.type : (['link', 'text'].indexOf(preferredType) !== -1 ? preferredType : 'folder');
		var parentId = item ? item.parent_id : String(preferredParent || '');
		var siblingCount = childrenOf(parentId).filter(function (child) {
			return !item || child.id !== item.id;
		}).length;

		$('#lpee-field-id').val(item ? item.id : '');
		$('#lpee-editor-title').text(isNew ? (type === 'link' ? t('addLink', 'Ajouter un lien') : (type === 'text' ? 'Ajouter un texte' : t('addFolder', 'Ajouter une rubrique'))) : t('edit', 'Modifier'));
		$('#lpee-field-type').val(type);
		$('#lpee-field-title').val(item ? item.title : (type === 'link' ? t('newLinkTitle', 'Nouveau lien') : (type === 'text' ? 'Nouveau texte' : t('newFolderTitle', 'Nouvelle rubrique'))));
		$('#lpee-field-content').val(item ? item.content : '');
		syncRichFromTextarea('#lpee-field-content');
		$('#lpee-field-direct-shortcode').val(item ? directShortcode(item.id) : t('directShortcodePending', 'Disponible après avoir appliqué l’élément.'));
		$('#lpee-field-description').val(item ? item.description : '');
		$('#lpee-field-visible').prop('checked', item ? !item.hidden : true);
		$('#lpee-field-open').prop('checked', item ? item.open : false);
		populateIncludeSelect(item ? item.include_menu : '');
		$('#lpee-field-include-inline').prop('checked', item ? item.include_inline : false);
		$('#lpee-field-tile-color').val(item ? item.tile_color : 'auto');
		$('#lpee-field-tile-size').val(item ? item.tile_size : 'normal');
		$('#lpee-field-tile-custom').val(item && item.tile_custom ? item.tile_custom : '#067030');
		$('#lpee-field-link-target').val(item && item.link_target ? item.link_target : 'auto');
		setIconImage(item ? item.icon_image : 0);
		toggleTileCustom();
		$('#lpee-field-destination-type').val(item && ['url', 'admin', 'shortcode'].indexOf(item.destination_type) !== -1 ? item.destination_type : 'page');
		$('#lpee-field-url').val(item ? item.url : '');
		$('#lpee-field-shortcode').val(item ? item.shortcode : '');

		populateIconSelect(item ? item.icon : (type === 'link' ? 'link' : (type === 'text' ? 'document' : 'book')));
		populateParentSelect(item ? item.id : '', parentId);
		populateRoleChoices(item ? item.allowed_roles : []);
		$('#lpee-field-limit-roles').prop('checked', item ? item.allowed_roles.length > 0 : false);
		toggleRoleRestrictionFields();
		populatePageSelect('', item ? item.page_id : 0);
		$('#lpee-field-page-search').val('');
		$('#lpee-field-position').attr('max', siblingCount + 1).val(item ? item.order : siblingCount + 1);
		toggleTypeFields();
		toggleDestinationFields();
		openModal();
	}

	function populateIconSelect(selected) {
		var $select = $('#lpee-field-icon');
		$select.empty();

		Object.keys(icons).forEach(function (key) {
			$select.append($('<option></option>').attr('value', key).text(icons[key].label || key));
		});

		$select.val(icons[selected] ? selected : 'book');
	}

	function roleLabel(role) {
		return roles[role] || role;
	}

	function directShortcode(id) {
		return '[' + shortcode + ' menu="' + activeMenuId + '" item="' + id + '"]';
	}

	function populateRoleChoices(selectedRoles) {
		var $list = $('#lpee-field-roles');
		var selected = Array.isArray(selectedRoles) ? selectedRoles.map(String) : [];

		$list.empty();

		Object.keys(roles).forEach(function (role) {
			var id = 'lpee-role-' + role.replace(/[^a-zA-Z0-9_-]/g, '');
			var $label = $('<label class="lpee-check"></label>');
			var $input = $('<input type="checkbox">')
				.attr('id', id)
				.attr('value', role)
				.prop('checked', selected.indexOf(role) !== -1);

			$label.append($input);
			$label.append($('<span></span>').text(roleLabel(role)));
			$list.append($label);
		});
	}

	function selectedRolesFromEditor() {
		if (!$('#lpee-field-limit-roles').prop('checked')) {
			return [];
		}

		var selected = [];

		$('#lpee-field-roles input:checked').each(function () {
			selected.push(String($(this).val()));
		});

		return selected;
	}

	function toggleRoleRestrictionFields() {
		var enabled = $('#lpee-field-limit-roles').prop('checked');

		$('#lpee-field-roles')
			.toggleClass('is-disabled', !enabled)
			.find('input')
			.prop('disabled', !enabled);
	}

	function toggleMenuRoleRestrictionFields() {
		$('#lpee-menu-role-list').toggleClass('is-disabled', !$('#lpee-field-restrict-menu-roles').prop('checked'));
	}

	function populateParentSelect(currentId, selectedParent) {
		var $select = $('#lpee-field-parent');
		var blocked = currentId ? descendantsOf(currentId).concat([currentId]) : [];

		$select.empty().append($('<option></option>').attr('value', '').text(t('root', 'Aucun - premier niveau')));

		function appendFolderOptions(parentId, depth) {
			childrenOf(parentId).forEach(function (candidate) {
				if (candidate.type !== 'folder') {
					return;
				}

				if (blocked.indexOf(candidate.id) === -1) {
					$select.append(
						$('<option></option>')
							.attr('value', candidate.id)
							.text(new Array(depth + 1).join('— ') + candidate.title)
					);
				}

				appendFolderOptions(candidate.id, depth + 1);
			});
		}

		appendFolderOptions('', 0);
		$select.val(selectedParent || '');
	}

	function populatePageSelect(filter, selectedId) {
		var $select = $('#lpee-field-page-id');
		var query = String(filter || '').toLowerCase();
		var selected = parseInt(selectedId || $select.val() || 0, 10) || 0;
		var matched = pages.filter(function (page) {
			return !query || String(page.title || '').toLowerCase().indexOf(query) !== -1;
		});

		if (selected && !matched.some(function (page) { return parseInt(page.id, 10) === selected; })) {
			var selectedPage = pages.find(function (page) {
				return parseInt(page.id, 10) === selected;
			});

			if (selectedPage) {
				matched.unshift(selectedPage);
			}
		}

		$select.empty();
		$select.append($('<option></option>').attr('value', '0').text(t('noPage', 'Aucune page sélectionnée')));

		matched.forEach(function (page) {
			var label = page.title + (page.status && page.status !== 'publish' ? ' (' + page.status + ')' : '');
			$select.append($('<option></option>').attr('value', page.id).text(label));
		});

		$select.val(String(selected));
	}

	function toggleTypeFields() {
		var type = $('#lpee-field-type').val();
		$('.lpee-folder-fields').toggle(type === 'folder');
		$('.lpee-link-fields').toggle(type === 'link');
		$('.lpee-text-fields').toggle(type === 'text');
		updatePositionLimit();
	}

	function toggleDestinationFields() {
		var type = $('#lpee-field-destination-type').val();
		$('.lpee-page-destination').toggle(type === 'page');
		$('.lpee-url-destination').toggle(type === 'url');
		$('.lpee-shortcode-destination').toggle(type === 'shortcode');
	}

	function updatePositionLimit() {
		var currentId = String($('#lpee-field-id').val() || '');
		var parentId = String($('#lpee-field-parent').val() || '');
		var count = childrenOf(parentId).filter(function (child) {
			return child.id !== currentId;
		}).length + 1;
		var $position = $('#lpee-field-position');

		$position.attr('max', count);

		if ((parseInt($position.val(), 10) || 1) > count) {
			$position.val(count);
		}
	}

	function saveEditor() {
		var id = String($('#lpee-field-id').val() || '');
		var existing = id ? findItem(id) : null;
		var type = ['link', 'text'].indexOf($('#lpee-field-type').val()) !== -1 ? $('#lpee-field-type').val() : 'folder';
		var parentId = String($('#lpee-field-parent').val() || '');
		var title = String($('#lpee-field-title').val() || '').trim();

		if (!title) {
			alert(t('emptyTitle', 'Le titre est obligatoire.'));
			$('#lpee-field-title').trigger('focus');
			return;
		}

		if (id && wouldCreateCycle(id, parentId)) {
			alert(t('invalidParent', 'Ce parent n’est pas autorisé.'));
			return;
		}

		if (existing && existing.type === 'folder' && type !== 'folder' && hasChildren(id)) {
			alert(t('typeHasChildren', 'Cette rubrique contient des éléments.'));
			return;
		}

		var item = existing || {
			id: uniqueId(),
			order: 1
		};

		var selectedRoles = selectedRolesFromEditor();

		if ($('#lpee-field-limit-roles').prop('checked') && !selectedRoles.length) {
			alert(t('chooseRole', 'Choisissez au moins un rôle.'));
			$('#lpee-field-roles input').first().trigger('focus');
			return;
		}

		item.type = type;
		item.title = title;
		item.description = String($('#lpee-field-description').val() || '');
		item.icon = $('#lpee-field-icon').val() || 'book';
		item.parent_id = parentId;
		item.allowed_roles = selectedRoles;
		item.hidden = !$('#lpee-field-visible').prop('checked');
		item.open = type === 'folder' ? $('#lpee-field-open').prop('checked') : false;
		item.include_menu = type === 'folder' ? String($('#lpee-field-include-menu').val() || '') : '';
		item.include_inline = item.include_menu !== '' && $('#lpee-field-include-inline').prop('checked');
		item.tile_color = $('#lpee-field-tile-color').val() || 'auto';
		item.tile_size = $('#lpee-field-tile-size').val() === 'wide' ? 'wide' : 'normal';
		item.tile_custom = item.tile_color === 'custom' ? String($('#lpee-field-tile-custom').val() || '') : '';
		item.icon_image = parseInt($('#lpee-field-icon-image').val() || 0, 10) || 0;
		item.link_target = type === 'link' && ['same', 'new'].indexOf($('#lpee-field-link-target').val()) !== -1 ? $('#lpee-field-link-target').val() : 'auto';

		if (type === 'link') {
			item.destination_type = ['url', 'admin', 'shortcode'].indexOf($('#lpee-field-destination-type').val()) !== -1 ? $('#lpee-field-destination-type').val() : 'page';
			item.page_id = parseInt($('#lpee-field-page-id').val() || 0, 10) || 0;
			item.url = item.destination_type === 'url' ? String($('#lpee-field-url').val() || '').trim() : '';
			item.shortcode = item.destination_type === 'shortcode' ? String($('#lpee-field-shortcode').val() || '').trim() : '';
		} else {
			item.destination_type = '';
			item.page_id = 0;
			item.url = '';
			item.shortcode = '';
		}
		item.content = type === 'text' ? safeIntro(String($('#lpee-field-content').val() || '')) : '';

		if (!existing) {
			items.push(item);
		}

		placeInSiblingPosition(item, parseInt($('#lpee-field-position').val(), 10) || 1);
		closeModal();
		renderTree();
		markDirty();
	}

	function placeInSiblingPosition(item, position) {
		var siblings = childrenOf(item.parent_id).filter(function (candidate) {
			return candidate.id !== item.id;
		});
		var targetPosition = Math.max(1, Math.min(position, siblings.length + 1));

		siblings.splice(targetPosition - 1, 0, item);
		siblings.forEach(function (sibling, index) {
			sibling.order = index + 1;
		});
	}

	function openModal() {
		var $modal = $('#lpee-editor-modal');
		$modal.prop('hidden', false);
		window.setTimeout(function () {
			$('#lpee-field-title').trigger('focus').trigger('select');
		}, 50);
	}

	function closeModal() {
		$('#lpee-editor-modal').prop('hidden', true);
	}

	function selectedMenuRoles() {
		var selected = [];

		$('[name="lpee_settings[allowed_roles][]"]:checked').each(function () {
			selected.push(String($(this).val()));
		});

		return selected;
	}

	function currentSettings() {
		return {
			theme: $('[name="lpee_settings[theme]"]').val() === 'custom' ? 'custom' : 'logione',
			tile_text: $('[name="lpee_settings[tile_text]"]').val() || '',
			tile_icon: parseInt($('#lpee-menu-tile-icon').val() || 0, 10) || 0,
			tile_color: $('[name="lpee_settings[tile_color]"]').val() || 'menthe',
			intro_text: $('[name="lpee_settings[intro_text]"]').val() || '',
			show_search: $('[name="lpee_settings[show_search]"]').prop('checked') ? '1' : '0',
			show_greeting: $('[name="lpee_settings[show_greeting]"]').prop('checked') ? '1' : '0',
			show_art: $('[name="lpee_settings[show_art]"]').prop('checked') ? '1' : '0',
			hero_color: /^#[0-9a-fA-F]{6}$/.test(String($('#lpee-hero-color').val() || '')) ? String($('#lpee-hero-color').val()) : '#313a45',
			tile_palette: $('[name="lpee_settings[tile_palette][]"]').map(function () { return String($(this).val() || ''); }).get(),
			block_title: $('[name="lpee_settings[block_title]"]').val() || '',
			show_title: $('[name="lpee_settings[show_title]"]').prop('checked') ? '1' : '0',
			primary_color: $('[name="lpee_settings[primary_color]"]').val() || '#155e75',
			container_bg: $('[name="lpee_settings[container_bg]"]').val() || '#eef6f5',
			item_bg: $('[name="lpee_settings[item_bg]"]').val() || '#ffffff',
			text_color: $('[name="lpee_settings[text_color]"]').val() || '#183039',
			max_width: parseInt($('[name="lpee_settings[max_width]"]').val() || 980, 10) || 980,
			radius: parseInt($('[name="lpee_settings[radius]"]').val() || 14, 10) || 14,
			spacing: $('[name="lpee_settings[spacing]"]').val() === 'compact' ? 'compact' : 'comfortable',
			empty_message: $('[name="lpee_settings[empty_message]"]').val() || '',
			single_open_siblings: $('[name="lpee_settings[single_open_siblings]"]').prop('checked') ? '1' : '0',
			restrict_menu_roles: $('#lpee-field-restrict-menu-roles').prop('checked') ? '1' : '0',
			allowed_roles: selectedMenuRoles()
		};
	}

	function previewStyle(settings) {
		if (settings.theme !== 'custom') {
			return '--lpee-max-width:' + settings.max_width + 'px';
		}

		return [
			'--lpee-primary:' + settings.primary_color,
			'--lpee-bg:' + settings.container_bg,
			'--lpee-item-bg:' + settings.item_bg,
			'--lpee-text:' + settings.text_color,
			'--lpee-max-width:' + settings.max_width + 'px',
			'--lpee-radius:' + settings.radius + 'px'
		].join(';');
	}

	var TILE_COLORS = ['ink', 'green', 'white', 'yellow', 'mint'];

	function defaultPalette() {
		var presets = config.tilePresets || {};
		return TILE_COLORS.map(function (key) { return presets[key] || '#ffffff'; });
	}

	function luminance(hex) {
		var c = String(hex).replace('#', '');
		return [0, 2, 4].map(function (i) {
			var v = parseInt(c.substr(i, 2), 16) / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		}).reduce(function (sum, v, i) { return sum + v * [0.2126, 0.7152, 0.0722][i]; }, 0);
	}

	function contrast(a, b) {
		var la = luminance(a), lb = luminance(b);
		return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
	}

	/** Mêmes règles que le site : texte et accents lisibles sur n'importe quel fond. */
	function tileVars(hex) {
		var v;
		if (contrast(hex, '#ffffff') >= contrast(hex, '#313a45')) {
			v = { '--bg': hex, '--fg': '#ffffff', '--sub': 'rgba(255,255,255,.8)', '--icon-bg': 'rgba(255,255,255,.14)', '--icon-fg': '#ffffff', '--cta': contrast(hex, '#f5c400') >= 3 ? '#f5c400' : '#ffffff' };
		} else {
			var accent = contrast(hex, '#067030') >= 3 ? '#067030' : '#313a45';
			v = { '--bg': hex, '--fg': '#313a45', '--sub': 'rgba(49,58,69,.78)', '--icon-bg': contrast(hex, '#ffffff') < 1.1 ? '#dcefe3' : 'rgba(255,255,255,.6)', '--icon-fg': accent, '--cta': accent };
		}
		return Object.keys(v).map(function (k) { return k + ':' + v[k]; }).join(';');
	}

	/** Même calcul que l'en-tête publié (PHP hero_vars). */
	function heroVars(hex) {
		var v;
		if (contrast(hex, '#ffffff') >= contrast(hex, '#313a45')) {
			v = { '--hero-bg': hex, '--hero-fg': '#ffffff', '--hero-sub': 'rgba(255,255,255,.82)', '--hero-accent': contrast(hex, '#2fae5f') >= 3 ? '#2fae5f' : (contrast(hex, '#f5c400') >= 3 ? '#f5c400' : '#ffffff') };
		} else {
			v = { '--hero-bg': hex, '--hero-fg': '#313a45', '--hero-sub': 'rgba(49,58,69,.82)', '--hero-accent': contrast(hex, '#067030') >= 3 ? '#067030' : '#313a45' };
		}
		return Object.keys(v).map(function (k) { return k + ':' + v[k]; }).join(';');
	}

	function tileHex(item, palette, counter) {
		var presets = config.tilePresets || {};
		if (item.tile_color === 'custom' && item.tile_custom) {
			return item.tile_custom;
		}
		if (presets[item.tile_color]) {
			return presets[item.tile_color];
		}
		return palette[counter.n++ % palette.length];
	}

	function iconHtml(item, cls) {
		var url = item.icon_image && config.iconImages ? config.iconImages[String(item.icon_image)] : '';
		if (url) {
			return '<span class="' + cls + ' is-image" aria-hidden="true"><img src="' + escapeHtml(url) + '" alt=""></span>';
		}
		return '<span class="' + cls + ' dashicons ' + escapeHtml(iconClass(item.icon)) + '" aria-hidden="true"></span>';
	}

	function setIconImage(id) {
		id = parseInt(id || 0, 10) || 0;
		var url = id && config.iconImages ? config.iconImages[String(id)] : '';
		$('#lpee-field-icon-image').val(id);
		$('#lpee-icon-image-preview').html(url ? '<img src="' + escapeHtml(url) + '" alt="">' : '').toggleClass('is-empty', !url);
		$('#lpee-icon-image-remove').toggle(!!url);
	}

	/* ---- Pastilles de couleur : gamme complète + « Autre couleur » ---- */
	var LEGACY_KEYS = { menthe: '#dcefe3', vert: '#067030', anthracite: '#313a45', jaune: '#f5c400', blanc: '#ffffff' };

	function swatchList() {
		return Array.isArray(config.tileSwatches) ? config.tileSwatches : [];
	}

	function swatchButton(sw, current) {
		var on = current && String(current).toLowerCase() === String(sw.hex).toLowerCase();
		return '<button type="button" class="lpee-swatch' + (on ? ' is-selected' : '') + '" role="radio" aria-checked="' + (on ? 'true' : 'false') + '" data-hex="' + escapeHtml(sw.hex) + '" data-key="' + escapeHtml(sw.key || '') + '" title="' + escapeHtml(sw.label) + '" style="--sw:' + escapeHtml(sw.hex) + '"><span class="screen-reader-text">' + escapeHtml(sw.label) + '</span></button>';
	}

	/** Pastilles d'un élément (tuile LogiOne) : automatique, couleurs prédéfinies, ou couleur libre. */
	function renderItemSwatches() {
		var $box = $('[data-lpee-swatches="item"]');
		if (!$box.length) {
			return;
		}
		var choice = $('#lpee-field-tile-color').val() || 'auto';
		var presets = config.tilePresets || {};
		var current = choice === 'custom' ? $('#lpee-field-tile-custom').val() : (presets[choice] || '');
		var html = '<button type="button" class="lpee-swatch is-auto' + (choice === 'auto' ? ' is-selected' : '') + '" role="radio" aria-checked="' + (choice === 'auto' ? 'true' : 'false') + '" data-auto="1" title="Automatique"><span>Auto</span></button>';
		swatchList().forEach(function (sw) {
			html += swatchButton(sw, choice === 'auto' ? '' : current);
		});
		$box.html(html);
	}

	/** Pastilles d'un réglage (couleur d'une tuile LogiOne) : la valeur est une couleur #rrggbb. */
	function renderSettingSwatches() {
		$('[data-lpee-swatches="setting"]').each(function () {
			var $box = $(this);
			var $input = $($box.attr('data-input'));
			var value = String($input.val() || '');
			value = LEGACY_KEYS[value] || value;
			$box.html(swatchList().map(function (sw) { return swatchButton(sw, value); }).join(''));
			$($box.attr('data-picker')).val(/^#[0-9a-fA-F]{6}$/.test(value) ? value : '#067030');
		});
	}

	$(document).on('click', '[data-lpee-swatches] .lpee-swatch', function (event) {
		event.preventDefault();
		var $btn = $(this);
		var $box = $btn.closest('[data-lpee-swatches]');
		if ($box.attr('data-lpee-swatches') === 'item') {
			if ($btn.attr('data-auto')) {
				$('#lpee-field-tile-color').val('auto');
			} else if ($btn.attr('data-key')) {
				$('#lpee-field-tile-color').val($btn.attr('data-key'));
			} else {
				$('#lpee-field-tile-color').val('custom');
				$('#lpee-field-tile-custom').val($btn.attr('data-hex'));
			}
			renderItemSwatches();
		} else {
			$($box.attr('data-input')).val($btn.attr('data-hex')).trigger('change');
			renderSettingSwatches();
		}
	});
	$(document).on('input change', '#lpee-field-tile-custom', function () {
		$('#lpee-field-tile-color').val('custom');
		renderItemSwatches();
	});
	$(document).on('input change', '[data-lpee-color-picker]', function () {
		var $box = $('[data-lpee-swatches="setting"][data-picker="#' + this.id + '"]');
		$($box.attr('data-input')).val($(this).val()).trigger('change');
		renderSettingSwatches();
	});
	$(document).on('change', '#lpee-menu-select', function () {
		window.setTimeout(renderSettingSwatches, 0);
	});
	$(function () {
		renderSettingSwatches();
	});

	function toggleTileCustom() {
		renderItemSwatches();
		var isTool = $('#lpee-field-destination-type').val() === 'shortcode';
		$('.lpee-link-target-field').toggle($('#lpee-field-type').val() === 'link' && !isTool);
	}

	var mediaFrame = null;

	/** Champ d'image générique (icône de la tuile LogiOne) : médiathèque WordPress. */
	function setMediaField(input, preview, id) {
		id = parseInt(id || 0, 10) || 0;
		var url = id && config.iconImages ? config.iconImages[String(id)] : '';
		$(input).val(id);
		$(preview).html(url ? '<img src="' + escapeHtml(url) + '" alt="">' : '').toggleClass('is-empty', !url);
	}
	var fieldFrame = null, fieldTarget = null;
	$(document).on('click', '[data-lpee-media-pick]', function (event) {
		event.preventDefault();
		if (!window.wp || !wp.media) { return; }
		fieldTarget = $(this);
		if (!fieldFrame) {
			fieldFrame = wp.media({ title: 'Choisir une icône', library: { type: 'image' }, multiple: false, button: { text: 'Utiliser cette image' } });
			fieldFrame.on('select', function () {
				var a = fieldFrame.state().get('selection').first().toJSON();
				config.iconImages = config.iconImages || {};
				config.iconImages[String(a.id)] = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
				setMediaField(fieldTarget.attr('data-lpee-media-pick'), fieldTarget.attr('data-preview'), a.id);
				$(fieldTarget.attr('data-lpee-media-pick')).trigger('change');
			});
		}
		fieldFrame.open();
	});
	$(document).on('click', '[data-lpee-media-clear]', function (event) {
		event.preventDefault();
		setMediaField($(this).attr('data-lpee-media-clear'), $(this).attr('data-preview'), 0);
		$($(this).attr('data-lpee-media-clear')).trigger('change');
	});
	$(function () { setMediaField('#lpee-menu-tile-icon', '#lpee-menu-tile-icon-preview', $('#lpee-menu-tile-icon').val()); });

	function pickIconImage() {
		if (!window.wp || !wp.media) {
			return;
		}
		if (!mediaFrame) {
			mediaFrame = wp.media({ title: 'Choisir une icône', library: { type: 'image' }, multiple: false, button: { text: 'Utiliser cette image' } });
			mediaFrame.on('select', function () {
				var a = mediaFrame.state().get('selection').first().toJSON();
				var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
				config.iconImages = config.iconImages || {};
				config.iconImages[String(a.id)] = url;
				setIconImage(a.id);
			});
		}
		mediaFrame.open();
	}

	$(document).on('change', '#lpee-field-tile-color, #lpee-field-type, #lpee-field-destination-type', toggleTileCustom);
	$(document).on('click', '#lpee-icon-image-pick', function (event) {
		event.preventDefault();
		pickIconImage();
	});
	$(document).on('click', '#lpee-icon-image-remove', function (event) {
		event.preventDefault();
		setIconImage(0);
	});
	$(document).on('click', '#lpee-palette-reset', function (event) {
		event.preventDefault();
		var palette = defaultPalette();
		$('[name="lpee_settings[tile_palette][]"]').each(function (i) {
			$(this).val(palette[i] || '#ffffff');
		}).last().trigger('change');
	});
	$(function () {
		if (!window.wp || !wp.media) {
			$('#lpee-icon-image-pick').prop('disabled', true).attr('title', 'Médiathèque indisponible : droit « téléverser des fichiers » nécessaire.');
		}
	});

	function renderPreviewTiles(settings) {
		var children = childrenOf('').filter(function (item) {
			return !item.hidden;
		});
		var title = settings.block_title.trim() || 'Mes ressources';
		var html = '<div class="lpee-root lph lpee-theme-tiles is-preview">';
		var counter = { n: 0 };
		var palette = settings.tile_palette && settings.tile_palette.length ? settings.tile_palette : defaultPalette();

		var heroDark = contrast(settings.hero_color, '#ffffff') >= contrast(settings.hero_color, '#313a45');
		html += '<header class="lph-hero' + (heroDark ? '' : ' is-light') + '" style="' + escapeHtml(heroVars(settings.hero_color)) + '"><div class="lph-hero-top"><img class="lph-logo" src="' + escapeHtml((heroDark ? config.logoUrl : config.logoDarkUrl) || config.logoUrl || '') + '" alt="LogiOne by LogiProf"></div>';
		html += '<div class="lph-hero-main no-art"><div class="lph-hero-text">';
		html += settings.show_greeting === '1' ? '<p class="lph-hello">Bonjour Léa</p>' : '';
		html += '<h2 class="lph-title">' + escapeHtml(title) + '</h2>';
		html += settings.intro_text.trim() ? '<div class="lph-lead">' + safeIntro(settings.intro_text) + '</div>' : '';
		html += settings.show_search === '1' ? '<div class="lph-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input class="lph-search-input" type="search" placeholder="Rechercher une ressource…" disabled></div>' : '';
		html += '</div></div></header>';

		if (!children.length) {
			return html + '<div class="lph-body"><p class="lph-empty">' + escapeHtml(settings.empty_message || '') + '</p></div></div>';
		}

		html += '<div class="lph-body"><div class="lph-grid">';
		children.forEach(function (item) {
			var count = item.type === 'folder' ? childrenOf(item.id).filter(function (c) { return !c.hidden; }).length + (item.include_menu ? includedTopCount(item.include_menu) : 0) : 0;
			var hex = tileHex(item, palette, counter);
			html += '<span class="lph-tile' + (item.tile_size === 'wide' ? ' is-wide' : '') + '" style="' + escapeHtml(tileVars(hex)) + '">';
			html += iconHtml(item, 'lph-tile-icon');
			html += '<span class="lph-tile-body"><strong class="lph-tile-title">' + escapeHtml(item.title) + '</strong>';
			html += item.description ? '<span class="lph-tile-text">' + escapeHtml(item.description) + '</span>' : '';
			html += count ? '<span class="lph-tile-meta">' + count + (count > 1 ? ' éléments' : ' élément') + '</span>' : '';
			html += item.type === 'text' ? '<span class="lph-tile-meta">Texte</span>' : '';
			html += '<span class="lph-tile-cta">' + (item.type === 'folder' || item.type === 'text' || item.destination_type === 'shortcode' ? 'Afficher' : 'Ouvrir') + '</span></span></span>';
		});

		return html + '</div></div></div>';
	}

	function renderPreview() {
		var settings = currentSettings();

		if (settings.theme === 'tiles') {
			$('#lpee-preview').html(renderPreviewTiles(settings));
			return;
		}

		var html = '<div class="lpee-root lpee-theme-' + escapeHtml(settings.theme) + ' lpee-spacing-' + escapeHtml(settings.spacing) + '" data-lpee-root data-single-open="' + escapeHtml(settings.single_open_siblings) + '" style="' + escapeHtml(previewStyle(settings)) + '">';
		var title = settings.show_title === '1' && settings.block_title.trim() ? '<h2 class="lpee-block-title">' + escapeHtml(settings.block_title) + '</h2>' : '';

		if (settings.theme !== 'custom') {
			html += '<div class="lpee-head">' + title + '</div>';
		} else {
			html += title;
		}

		html += renderPreviewList('', 0, settings);
		html += '</div>';

		$('#lpee-preview').html(html);

		if (window.LogiprofEspaceEnseignants && window.LogiprofEspaceEnseignants.init) {
			window.LogiprofEspaceEnseignants.init($('#lpee-preview [data-lpee-root]').get(0));
		}
	}

	function renderPreviewList(parentId, level, settings) {
		var children = childrenOf(parentId).filter(function (item) {
			return !item.hidden;
		});

		if (!children.length) {
			return '<p class="lpee-empty">' + escapeHtml(settings.empty_message || '') + '</p>';
		}

		var html = '<ul class="lpee-list lpee-level-' + level + '" data-lpee-level="' + level + '">';

		children.forEach(function (item) {
			if (item.type === 'folder') {
				html += renderPreviewFolder(item, level, settings);
			} else if (item.type === 'text') {
				html += item.content.replace(/<[^>]*>/g, '').trim() ? '<li class="lpee-node lpee-node-text" data-lpee-node><div class="lpee-text">' + safeIntro(item.content) + '</div></li>' : '';
			} else if (item.destination_type === 'shortcode') {
				html += renderPreviewShortcodeTool(item);
			} else {
				html += renderPreviewLink(item);
			}
		});

		html += '</ul>';
		return html;
	}

	function renderPreviewFolder(item, level, settings) {
		var open = item.open || hasOpenDescendant(item.id);
		var safeId = item.id.replace(/[^a-zA-Z0-9_-]/g, '');
		var buttonId = 'lpee-preview-toggle-' + safeId;
		var panelId = 'lpee-preview-panel-' + safeId;
		var html = '<li class="lpee-node lpee-node-folder' + (open ? ' is-open' : '') + '" data-lpee-node>';

		html += '<button id="' + escapeHtml(buttonId) + '" type="button" class="lpee-row lpee-toggle" data-lpee-toggle aria-expanded="' + (open ? 'true' : 'false') + '" aria-controls="' + escapeHtml(panelId) + '">';
		html += '<span class="lpee-row-icon dashicons ' + escapeHtml(iconClass(item.icon)) + '" aria-hidden="true"></span>';
		html += renderPreviewCopy(item);
		html += '<span class="lpee-chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
		html += '</button>';
		html += '<div id="' + escapeHtml(panelId) + '" class="lpee-panel" role="region" aria-labelledby="' + escapeHtml(buttonId) + '"' + (open ? '' : ' hidden') + '>';
		html += renderPreviewList(item.id, level + 1, settings);
		html += '</div></li>';

		return html;
	}

	function renderPreviewShortcodeTool(item) {
		var hasShortcode = item.shortcode.trim() !== '';
		var safeId = item.id.replace(/[^a-zA-Z0-9_-]/g, '');
		var buttonId = 'lpee-preview-toggle-shortcode-' + safeId;
		var panelId = 'lpee-preview-panel-shortcode-' + safeId;
		var html = '<li class="lpee-node lpee-node-shortcode' + (hasShortcode ? '' : ' is-disabled') + '" data-lpee-node>';

		if (!hasShortcode) {
			html += '<div class="lpee-row lpee-link is-unavailable" role="button" aria-disabled="true">';
			html += '<span class="lpee-row-icon dashicons ' + escapeHtml(iconClass(item.icon)) + '" aria-hidden="true"></span>';
			html += renderPreviewCopy(item);
			html += '<span class="lpee-status">Shortcode à renseigner</span>';
			html += '</div></li>';
			return html;
		}

		html += '<button id="' + escapeHtml(buttonId) + '" type="button" class="lpee-row lpee-toggle lpee-shortcode-toggle" data-lpee-toggle aria-expanded="false" aria-controls="' + escapeHtml(panelId) + '">';
		html += '<span class="lpee-row-icon dashicons ' + escapeHtml(iconClass(item.icon)) + '" aria-hidden="true"></span>';
		html += renderPreviewCopy(item);
		html += '<span class="lpee-chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
		html += '</button>';
		html += '<div id="' + escapeHtml(panelId) + '" class="lpee-panel lpee-shortcode-panel" role="region" aria-labelledby="' + escapeHtml(buttonId) + '" hidden>';
		html += '<div class="lpee-shortcode-content"><p class="lpee-empty">Le shortcode sera exécuté sur le site.</p><code>' + escapeHtml(item.shortcode) + '</code></div>';
		html += '</div></li>';
		return html;
	}

	function renderPreviewLink(item) {
		var url = previewUrl(item);
		var html = '<li class="lpee-node lpee-node-link' + (url ? '' : ' is-disabled') + '" data-lpee-node>';

		if (url) {
			html += '<a class="lpee-row lpee-link" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" aria-label="' + escapeHtml(item.title + ', ' + t('opensNewTab', 'ouvre dans un nouvel onglet')) + '">';
		} else {
			html += '<div class="lpee-row lpee-link is-unavailable" role="link" aria-disabled="true">';
		}

		html += '<span class="lpee-row-icon dashicons ' + escapeHtml(iconClass(item.icon)) + '" aria-hidden="true"></span>';
		html += renderPreviewCopy(item);

		if (url) {
			html += '<span class="lpee-external dashicons dashicons-external" aria-hidden="true"></span>';
			html += '</a>';
		} else {
			html += '<span class="lpee-status">' + escapeHtml(t('unavailable', 'Indisponible')) + '</span>';
			html += '</div>';
		}

		html += '</li>';
		return html;
	}

	function renderPreviewCopy(item) {
		var html = '<span class="lpee-row-copy">';
		html += '<span class="lpee-row-title">' + escapeHtml(item.title) + '</span>';

		if (item.description.trim()) {
			html += '<span class="lpee-row-description">' + escapeHtml(item.description).replace(/\n/g, '<br>') + '</span>';
		}

		html += '</span>';
		return html;
	}

	function hasOpenDescendant(id) {
		return childrenOf(id).some(function (child) {
			return !child.hidden && child.type === 'folder' && (child.open || hasOpenDescendant(child.id));
		});
	}

	function previewUrl(item) {
		if (item.destination_type === 'url') {
			return item.url || '';
		}

		if (item.destination_type === 'admin') {
			return adminUrl;
		}

		var page = pages.find(function (candidate) {
			return parseInt(candidate.id, 10) === parseInt(item.page_id, 10);
		});

		return page && page.url ? page.url : '';
	}

	function resetAppearance() {
		Object.keys(defaultAppearance).forEach(function (key) {
			var selector = '[name="lpee_settings[' + key + ']"]';
			var $field = $(selector);

			if (!$field.length) {
				return;
			}

			if ($field.attr('type') === 'checkbox') {
				$field.prop('checked', defaultAppearance[key] === '1' || defaultAppearance[key] === true);
			} else {
				$field.val(defaultAppearance[key]);
			}
		});

		markDirty();
	}

	function bindEvents() {
		$('#lpee-menu-select').on('change', function () {
			switchMenu($(this).val());
		});

		$('#lpee-menu-name').on('input', function () {
			var menu = activeMenu();

			if (!menu) {
				return;
			}

			menu.name = String($(this).val() || '').trim() || t('newMenuName', 'Nouveau menu');
			renderMenuControls();
			markDirty();
		});

		$('#lpee-menu-create').on('click', createMenu);
		$('#lpee-menu-duplicate').on('click', duplicateMenu);
		$('#lpee-menu-delete').on('click', deleteMenu);

		$('[data-lpee-tab-button]').on('click', function () {
			var tab = $(this).attr('data-lpee-tab-button');

			$('[data-lpee-tab-button]').removeClass('is-active');
			$(this).addClass('is-active');
			$('[data-lpee-tab-panel]').removeClass('is-active').prop('hidden', true);
			$('[data-lpee-tab-panel="' + tab + '"]').addClass('is-active').prop('hidden', false);
		});

		$('#lpee-tree').on('click', '[data-action]', function () {
			var action = $(this).attr('data-action');
			var id = String($(this).closest('.lpee-admin-node').attr('data-id') || '');

			if (action === 'collapse') {
				var item = findItem(id);

				if (item && item.type === 'folder') {
					collapsed[id] = !collapsed[id];
					renderTree();
				}
			} else if (action === 'edit') {
				openEditor(id);
			} else if (action === 'toggle-visibility') {
				toggleVisibility(id);
			} else if (action === 'delete') {
				deleteItem(id);
			} else if (action === 'move-up') {
				moveItem(id, -1);
			} else if (action === 'move-down') {
				moveItem(id, 1);
			} else if (action === 'add-folder') {
				openEditor('', 'folder', id);
			} else if (action === 'add-link') {
				openEditor('', 'link', id);
			} else if (action === 'add-text') {
				openEditor('', 'text', id);
			}
		});

		$('[data-lpee-add]').on('click', function () {
			openEditor('', $(this).attr('data-lpee-add'), $(this).attr('data-parent-id') || '');
		});

		$('[data-lpee-close-editor]').on('click', closeModal);
		$('#lpee-save-editor').on('click', saveEditor);
		$('#lpee-field-type').on('change', toggleTypeFields);
		$('#lpee-field-destination-type').on('change', toggleDestinationFields);
		$('#lpee-field-limit-roles').on('change', toggleRoleRestrictionFields);
		$('#lpee-field-restrict-menu-roles').on('change', toggleMenuRoleRestrictionFields);
		$('#lpee-field-page-search').on('input', function () {
			populatePageSelect($(this).val(), $('#lpee-field-page-id').val());
		});
		$('#lpee-field-parent').on('change', updatePositionLimit);
		$('#lpee-reset-appearance').on('click', resetAppearance);

		$('.lpee-admin-form').on('input change', 'input, textarea, select', function () {
			if (applyingMenu || $(this).closest('.lpee-menu-manager').length) {
				return;
			}

			if ($(this).closest('#lpee-editor-modal').length) {
				return;
			}

			markDirty();
		});

		$('#lpee-admin-form').on('submit', function () {
			syncFromDom();
			serializePayload();
			dirty = false;
		});

		$(window).on('beforeunload', function () {
			if (dirty) {
				return t('unsaved', 'Des modifications ne sont pas enregistrées.');
			}
		});

		$(document).on('keydown', function (event) {
			if (event.key === 'Escape' && !$('#lpee-editor-modal').prop('hidden')) {
				closeModal();
			}
		});
	}

	$(function () {
		bindEvents();
		applySettings(activeMenu().settings);
		renderMenuControls();
		renderTree();
	});
})(jQuery);
