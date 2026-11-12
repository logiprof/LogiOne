(function () {
	'use strict';

	var motionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

	function prefersReducedMotion() {
		return motionQuery && motionQuery.matches;
	}

	function getPanel(button) {
		var panelId = button.getAttribute('aria-controls');
		return panelId ? document.getElementById(panelId) : null;
	}

	function directChildWithClass(node, className) {
		if (!node) {
			return null;
		}

		for (var i = 0; i < node.children.length; i++) {
			if (node.children[i].classList.contains(className)) {
				return node.children[i];
			}
		}

		return null;
	}

	function directToggle(node) {
		return directChildWithClass(node, 'lpee-toggle');
	}

	function directPanel(node) {
		return directChildWithClass(node, 'lpee-panel');
	}

	function isAnimating(panel) {
		return panel && panel.dataset.lpeeAnimating === '1';
	}

	function finishOpening(panel, button) {
		window.setTimeout(function () {
			if (button.getAttribute('aria-expanded') === 'true') {
				panel.dataset.lpeeAnimating = '0';
				panel.style.maxHeight = 'none';
				panel.style.overflow = 'visible';
				updateAncestorHeights(panel);
			}
		}, 270);
	}

	function updateAncestorHeights(panel) {
		var parentPanel = panel ? panel.parentElement : null;

		while (parentPanel) {
			if (parentPanel.classList && parentPanel.classList.contains('lpee-panel') && !parentPanel.hidden) {
				parentPanel.style.maxHeight = isAnimating(parentPanel) ? parentPanel.scrollHeight + 'px' : 'none';
			}

			parentPanel = parentPanel.parentElement;
		}
	}

	function setPanelState(button, open, animate) {
		var panel = getPanel(button);
		var node = button.closest('[data-lpee-node]');

		if (!panel || !node) {
			return;
		}

		button.setAttribute('aria-expanded', open ? 'true' : 'false');
		node.classList.toggle('is-open', open);

		if (open) {
			panel.hidden = false;
			panel.style.visibility = 'visible';
			panel.style.opacity = '1';

			if (!animate || prefersReducedMotion()) {
				panel.dataset.lpeeAnimating = '0';
				panel.style.maxHeight = 'none';
				panel.style.overflow = 'visible';
				updateAncestorHeights(panel);
				return;
			}

			panel.dataset.lpeeAnimating = '1';
			panel.style.overflow = 'hidden';
			panel.style.maxHeight = '0px';

			window.requestAnimationFrame(function () {
				panel.style.maxHeight = panel.scrollHeight + 'px';
				updateAncestorHeights(panel);
			});

			finishOpening(panel, button);
			return;
		}

		if (!animate || prefersReducedMotion()) {
			panel.dataset.lpeeAnimating = '0';
			panel.style.overflow = 'hidden';
			panel.style.maxHeight = '0px';
			panel.style.opacity = '0';
			panel.style.visibility = 'hidden';
			panel.hidden = true;
			updateAncestorHeights(panel);
			return;
		}

		panel.dataset.lpeeAnimating = '1';
		panel.style.overflow = 'hidden';
		panel.style.maxHeight = panel.scrollHeight + 'px';
		panel.offsetHeight;

		window.requestAnimationFrame(function () {
			panel.style.maxHeight = '0px';
			panel.style.opacity = '0';
			panel.style.visibility = 'hidden';
		});

		window.setTimeout(function () {
			if (button.getAttribute('aria-expanded') === 'false') {
				panel.dataset.lpeeAnimating = '0';
				panel.hidden = true;
			}

			updateAncestorHeights(panel);
		}, 260);
	}

	function closeSiblingFolders(root, button) {
		if (root.getAttribute('data-single-open') !== '1') {
			return;
		}

		var node = button.closest('[data-lpee-node]');
		var list = node ? node.parentElement : null;

		if (!list) {
			return;
		}

		Array.prototype.forEach.call(list.children, function (sibling) {
			if (sibling === node || !sibling.classList.contains('lpee-node-folder')) {
				return;
			}

			var siblingButton = directToggle(sibling);

			if (siblingButton && siblingButton.getAttribute('aria-expanded') === 'true') {
				setPanelState(siblingButton, false, true);
			}
		});
	}

	function bindButton(root, button) {
		if (button.dataset.lpeeBound === '1') {
			return;
		}

		button.dataset.lpeeBound = '1';

		button.addEventListener('click', function () {
			var open = button.getAttribute('aria-expanded') !== 'true';

			if (open) {
				closeSiblingFolders(root, button);
			}

			setPanelState(button, open, true);
		});
	}

	function preparePanel(node) {
		var button = directToggle(node);
		var panel = directPanel(node);
		var isOpen = button && button.getAttribute('aria-expanded') === 'true';

		if (!panel || !button) {
			return;
		}

		if (isOpen) {
			panel.hidden = false;
			panel.style.visibility = 'visible';
			panel.style.opacity = '1';
			panel.dataset.lpeeAnimating = '0';
			panel.style.maxHeight = 'none';
			panel.style.overflow = 'visible';
		} else {
			panel.dataset.lpeeAnimating = '0';
			panel.style.overflow = 'hidden';
			panel.style.visibility = 'hidden';
			panel.style.opacity = '0';
			panel.style.maxHeight = '0px';
			panel.hidden = true;
		}
	}

	function initRoot(root) {
		if (!root) {
			return;
		}

		Array.prototype.slice.call(root.querySelectorAll('.lpee-node-folder')).reverse().forEach(preparePanel);
		Array.prototype.forEach.call(root.querySelectorAll('[data-lpee-toggle]'), function (button) {
			bindButton(root, button);
		});

		window.requestAnimationFrame(function () {
			Array.prototype.forEach.call(root.querySelectorAll('.lpee-node-folder.is-open > .lpee-panel'), function (panel) {
				panel.style.maxHeight = 'none';
				panel.style.overflow = 'visible';
			});
		});
	}

	function initAll(context) {
		var scope = context || document;
		Array.prototype.forEach.call(scope.querySelectorAll('[data-lpee-root]'), initRoot);
	}

	window.LogiprofEspaceEnseignants = window.LogiprofEspaceEnseignants || {};
	window.LogiprofEspaceEnseignants.init = initRoot;
	window.LogiprofEspaceEnseignants.initAll = initAll;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initAll(document);
		});
	} else {
		initAll(document);
	}
})();

/*
 * Présentation en tuiles (0.4.0) : tout se passe dans la même page.
 * Tuile de rubrique → panneau déplié dessous ; sous-rubriques en onglets ; recherche ; dernier onglet mémorisé.
 * Sans JavaScript, les liens (?lhm=…&lh=…) restent valables : le serveur ouvre le bon panneau.
 */
(function () {
	'use strict';

	var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function esc(value) {
		return window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&');
	}

	function store(key, value) {
		try {
			if (value === undefined) {
				return window.localStorage.getItem('logione-tuiles:' + key);
			}
			window.localStorage.setItem('logione-tuiles:' + key, value);
		} catch (e) {
			return null;
		}
		return null;
	}

	function norm(text) {
		return String(text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim();
	}

	function setUrl(root, id) {
		try {
			var url = new URL(window.location.href);
			if (id) {
				url.searchParams.set('lhm', root.getAttribute('data-lph-menu'));
				url.searchParams.set('lh', id);
			} else {
				url.searchParams.delete('lh');
				url.searchParams.delete('lhm');
			}
			window.history.replaceState(window.history.state, '', url.toString());
		} catch (e) { /* adresse inchangée */ }
	}

	function toggleOf(root, id) {
		return root.querySelector('[data-lph-toggle="' + esc(id) + '"]');
	}

	function panelOf(root, id) {
		return root.querySelector('[data-lph-panel="' + esc(id) + '"]');
	}

	function bringIntoView(tile, panel) {
		var top = tile.getBoundingClientRect().top;
		var panelTop = panel ? panel.getBoundingClientRect().top : top;
		// La tuile et le début de son contenu doivent être visibles ensemble.
		if (top < 0 || top > window.innerHeight * 0.45 || panelTop > window.innerHeight * 0.7) {
			// Contenu placé loin sous la tuile (sous une grande tuile voisine) : on montre le début du contenu.
			var tileBottom = tile.getBoundingClientRect().bottom; var target = panelTop - top + 16 <= window.innerHeight * 0.6 ? top - 16 : (panelTop - tileBottom < window.innerHeight * 0.55 ? panelTop - window.innerHeight * 0.65 : panelTop - window.innerHeight * 0.25);
			window.scrollTo({ top: Math.max(0, window.pageYOffset + target), behavior: reduced ? 'auto' : 'smooth' });
		}
	}

	/* Le contenu d'une tuile s'ouvre juste sous la tuile choisie (le placement automatique de la grille
	   le mettrait sinon sous toute la rangée). Les grandes tuiles voisines gardent leur hauteur. */
	function tracks(value) {
		return String(value || '').split(' ').map(parseFloat).filter(function (n) { return !isNaN(n); });
	}

	function placePanel(tile, panel) {
		var grid = tile.parentNode;
		if (!grid || !panel || panel.parentNode !== grid) {
			return;
		}
		panel.style.gridRow = '';
		panel.style.gridColumn = '';
		var cs = window.getComputedStyle(grid);
		var rows = tracks(cs.gridTemplateRows);
		var cols = tracks(cs.gridTemplateColumns);
		if (!rows.length || !cols.length) {
			return;
		}
		var rowGap = parseFloat(cs.rowGap) || 0;
		var colGap = parseFloat(cs.columnGap) || 0;
		var g = grid.getBoundingClientRect();
		var t = tile.getBoundingClientRect();
		var bottom = t.bottom - g.top - (parseFloat(cs.paddingTop) || 0) - (parseFloat(cs.borderTopWidth) || 0);
		var left = t.left - g.left - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.borderLeftWidth) || 0);
		// Toute la largeur : le contenu se place à la première ligne sous la tuile qu'aucune autre tuile ne traverse
		// (une grande tuile voisine garde ainsi sa hauteur ; le contenu se place sous elle).
		var others = Array.prototype.filter.call(grid.children, function (el) { return el !== panel && !el.hidden && !el.matches('.lpp-panel, .lph-panel'); }).map(function (el) {
			var r = el.getBoundingClientRect();
			return [r.top - g.top, r.bottom - g.top];
		});
		var line = rows.length + 1;
		var y = 0;
		for (var i = 0; i < rows.length; i++) {
			y += rows[i];
			if (bottom <= y + 2) {
				var edge = y + (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.borderTopWidth) || 0);
				if (!others.some(function (r) { return r[0] < edge - 2 && r[1] > edge + 2; })) {
					line = i + 2;
					break;
				}
			}
			y += rowGap;
		}
		var col = 1;
		var x = 0;
		for (var j = 0; j < cols.length; j++) {
			if (left < x + cols[j] - 2) {
				col = j + 1;
				break;
			}
			x += cols[j] + colGap;
		}
		panel.style.gridRow = String(line);
		panel.style.gridColumn = '1 / -1';
		fullBleed(grid, panel);
	}

	/* Le contenu ouvert occupe toute la largeur de l'écran (LogiOne 1.24.8) : il déborde de la colonne du thème
	   par des marges négatives calculées (largeur visible sans la barre de défilement). Les outils « pleine page »
	   comme LogiPlanning s'affichent ainsi en entier, sans partie coupée à gauche ou à droite. */
	function fullBleed(grid, panel) {
		panel.style.marginLeft = '';
		panel.style.marginRight = '';
		var cs = window.getComputedStyle(grid);
		var g = grid.getBoundingClientRect();
		var vw = document.documentElement.clientWidth;
		var left = g.left + (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.borderLeftWidth) || 0);
		var right = vw - (g.right - (parseFloat(cs.paddingRight) || 0) - (parseFloat(cs.borderRightWidth) || 0));
		if (left < 0 || right < 0 || vw < 200) {
			return;
		}
		panel.style.marginLeft = (-left) + 'px';
		panel.style.marginRight = (-right) + 'px';
		panel.classList.add('is-fullbleed');
	}

	function unpinTiles(root) {
		Array.prototype.forEach.call(root.querySelectorAll('[data-lph-pinned]'), function (el) {
			el.style.height = '';
			el.style.alignSelf = '';
			el.removeAttribute('data-lph-pinned');
		});
	}

	function measureTiles(grid) {
		var sizes = [];
		Array.prototype.forEach.call(grid ? grid.children : [], function (el) {
			if (el.matches('.lph-tile')) {
				sizes.push([el, el.getBoundingClientRect().height]);
			}
		});
		return sizes;
	}

	function pinTiles(panel, sizes) {
		var p = panel.getBoundingClientRect();
		sizes.forEach(function (pair) {
			var r = pair[0].getBoundingClientRect();
			if (r.top < p.top - 1 ? r.bottom > p.bottom - 1 : false) {
				pair[0].style.alignSelf = 'start';
				pair[0].style.height = pair[1] + 'px';
				pair[0].setAttribute('data-lph-pinned', '1');
			}
		});
	}

	function showPanel(tile, panel) {
		var sizes = measureTiles(tile.parentNode);
		placePanel(tile, panel);
		panel.hidden = false;
		pinTiles(panel, sizes);
	}

	function placeOpenPanels(root) {
		unpinTiles(root);
		Array.prototype.forEach.call(root.querySelectorAll('[data-lph-toggle][aria-expanded="true"]'), function (tile) {
			var panel = panelOf(root, tile.getAttribute('data-lph-toggle'));
			if (panel) {
				panel.hidden = true;
				showPanel(tile, panel);
			}
		});
	}

	function closeAll(root, except) {
		unpinTiles(root);
		Array.prototype.forEach.call(root.querySelectorAll('[data-lph-toggle]'), function (tile) {
			var id = tile.getAttribute('data-lph-toggle');
			if (id === except) {
				return;
			}
			tile.classList.remove('is-active');
			tile.setAttribute('aria-expanded', 'false');
			var panel = panelOf(root, id);
			if (panel) {
				panel.hidden = true;
			}
		});
	}

	function openPanel(root, id, options) {
		var tile = toggleOf(root, id);
		var panel = panelOf(root, id);
		options = options || {};
		if (!tile || !panel) {
			return;
		}
		closeAll(root, id);
		tile.classList.add('is-active');
		tile.setAttribute('aria-expanded', 'true');
		panel.hidden = true;
		showPanel(tile, panel);
		if (!options.keepTabs) {
			restoreTabs(root, panel);
		}
		if (!options.quiet) {
			setUrl(root, id);
			bringIntoView(tile, panel);
			var heading = panel.querySelector('.lph-panel-title');
			if (heading) {
				heading.focus({ preventScroll: true });
			}
		}
	}

	function closePanel(root, id, focusTile) {
		var tile = toggleOf(root, id);
		var panel = panelOf(root, id);
		if (tile) {
			tile.classList.remove('is-active');
			tile.setAttribute('aria-expanded', 'false');
			if (focusTile) {
				tile.focus();
			}
		}
		if (panel) {
			panel.hidden = true;
		}
		unpinTiles(root);
		setUrl(root, '');
	}

	function tabsOf(tablist) {
		return Array.prototype.filter.call(tablist.children, function (el) {
			return el.hasAttribute('data-lph-tab');
		});
	}

	function selectTab(root, tablist, id, remember) {
		tabsOf(tablist).forEach(function (tab) {
			var on = tab.getAttribute('data-lph-tab') === id;
			tab.classList.toggle('is-selected', on);
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.setAttribute('tabindex', on ? '0' : '-1');
		});
		Array.prototype.forEach.call(tablist.parentNode.children, function (el) {
			if (el.hasAttribute('data-lph-tabpanel')) {
				el.hidden = el.getAttribute('data-lph-tabpanel') !== id;
			}
		});
		if (remember) {
			store(root.getAttribute('data-lph-menu') + ':' + tablist.getAttribute('data-lph-tabs'), id);
			setUrl(root, id);
		}
	}

	/** Dernier onglet choisi par l'élève (ex. AFP plutôt que CFC), retrouvé à la visite suivante. */
	function restoreTabs(root, container) {
		Array.prototype.forEach.call(container.querySelectorAll('[data-lph-tabs]'), function (tablist) {
			var saved = store(root.getAttribute('data-lph-menu') + ':' + tablist.getAttribute('data-lph-tabs'));
			if (saved && tabsOf(tablist).some(function (tab) { return tab.getAttribute('data-lph-tab') === saved; })) {
				selectTab(root, tablist, saved, false);
			}
		});
	}

	/** Ouvre le chemin jusqu'à un élément (résultat de recherche) et le met en évidence. */
	function revealNode(root, id) {
		var node = root.querySelector('.lph-grid [data-lph-node="' + esc(id) + '"]');
		if (!node) {
			return;
		}
		if (node.hasAttribute('data-lph-toggle')) {
			openPanel(root, id);
			return;
		}
		var panel = node.closest('[data-lph-panel]');
		if (!panel) {
			node.focus();
			return;
		}
		openPanel(root, panel.getAttribute('data-lph-panel'), { quiet: true, keepTabs: true });
		var chain = [];
		for (var el = node.parentNode; el && el !== panel; el = el.parentNode) {
			if (el.hasAttribute && el.hasAttribute('data-lph-tabpanel')) {
				chain.unshift(el);
			}
		}
		chain.forEach(function (tabpanel) {
			var tablist = tabpanel.parentNode.querySelector(':scope > [data-lph-tabs]');
			if (tablist) {
				selectTab(root, tablist, tabpanel.getAttribute('data-lph-tabpanel'), false);
			}
		});
		if (node.hasAttribute('data-lph-tab')) {
			selectTab(root, node.parentNode, id, false);
		}
		if (node.tagName === 'DETAILS') {
			node.open = true;
		}
		setUrl(root, id);
		var target = node.tagName === 'DETAILS' ? node.querySelector('summary') : node;
		var top = target.getBoundingClientRect().top;
		window.scrollTo({ top: window.pageYOffset + top - window.innerHeight * 0.3, behavior: reduced ? 'auto' : 'smooth' });
		target.focus({ preventScroll: true });
		node.classList.remove('is-flash');
		void node.offsetWidth;
		node.classList.add('is-flash');
	}

	function buildIndex(root) {
		var index = [];
		Array.prototype.forEach.call(root.querySelectorAll('.lph-grid [data-lph-node]'), function (node) {
			if (node.classList.contains('is-unavailable')) {
				return;
			}
			var title = node.querySelector('.lph-tile-title') || node.querySelector('.lph-link-copy strong') || node.querySelector(':scope > span:not(.dashicons)');
			if (node.hasAttribute('data-lph-title')) {
				title = { textContent: node.getAttribute('data-lph-title') };
			}
			var text = node.classList.contains('lph-text') ? { textContent: node.textContent.slice(0, 400) } : node.querySelector('.lph-tile-text, .lph-link-copy small');
			var path = node.getAttribute('data-lph-path') || '';
			var isTab = node.hasAttribute('data-lph-tab');
			if (isTab) {
				var panel = node.closest('[data-lph-panel]');
				var owner = panel ? panel.querySelector('.lph-panel-title') : null;
				path = owner ? owner.textContent : '';
			}
			var isLink = node.tagName === 'A' && !node.hasAttribute('data-lph-toggle') && !isTab;
			var href = isLink ? node.getAttribute('href') : '';
			var newTab = isLink && node.getAttribute('target') === '_blank';
			index.push({
				id: node.getAttribute('data-lph-node'),
				title: title ? title.textContent : '',
				text: text ? text.textContent : '',
				path: path,
				href: href,
				newTab: newTab,
				kind: href ? 'link' : (node.classList.contains('lph-text') ? 'text' : (isTab || node.classList.contains('is-folder') ? 'folder' : 'tool')),
				key: norm((title ? title.textContent : '') + ' ' + (text ? text.textContent : '') + ' ' + path)
			});
		});
		return index;
	}

	function runSearch(root, query, results, grid) {
		var q = norm(query);
		if (q.length < 2) {
			results.hidden = true;
			results.innerHTML = '';
			grid.hidden = false;
			return;
		}
		root._lphIndex = root._lphIndex || buildIndex(root);
		var words = q.split(' ');
		var seen = {};
		var hits = root._lphIndex.filter(function (entry) {
			if (seen[entry.id] || !words.every(function (w) { return entry.key.indexOf(w) !== -1; })) {
				return false;
			}
			seen[entry.id] = true;
			return true;
		}).slice(0, 30);
		var labels = { link: 'Lien', folder: 'Rubrique', tool: 'Outil', text: 'Texte' };
		var html = '<p class="lph-results-count">' + (hits.length ? hits.length + (hits.length > 1 ? ' résultats' : ' résultat') : 'Aucune ressource ne correspond à votre recherche.') + '</p><ul class="lph-results-list">';
		hits.forEach(function (hit) {
			var inner = '<span class="lph-result-kind">' + labels[hit.kind] + '</span><strong></strong>' + (hit.path ? '<small></small>' : '');
			html += '<li>' + (hit.href
				? '<a class="lph-result"' + (hit.newTab ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + inner + '</a>'
				: '<button type="button" class="lph-result" data-lph-reveal>' + inner + '</button>') + '</li>';
		});
		results.innerHTML = html + '</ul>';
		// Textes insérés sans HTML (aucune injection possible)
		Array.prototype.forEach.call(results.querySelectorAll('.lph-result'), function (el, i) {
			el.querySelector('strong').textContent = hits[i].title;
			if (hits[i].path) {
				el.querySelector('small').textContent = hits[i].path;
			}
			if (hits[i].href) {
				el.setAttribute('href', hits[i].href);
			} else {
				el.setAttribute('data-lph-reveal', hits[i].id);
			}
		});
		results.hidden = false;
		grid.hidden = true;
	}

	function init(root) {
		if (root._lphReady) {
			return;
		}
		root._lphReady = true;
		placeOpenPanels(root);
		var resizeTimer = null;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () { placeOpenPanels(root); }, 150);
		});
		var grid = root.querySelector('[data-lph-grid]');
		var results = root.querySelector('[data-lph-results]');
		var search = root.querySelector('[data-lph-search]');

		root.addEventListener('click', function (event) {
			var reveal = event.target.closest('[data-lph-reveal]');
			if (reveal && search) {
				search.value = '';
				runSearch(root, '', results, grid);
				revealNode(root, reveal.getAttribute('data-lph-reveal'));
				return;
			}
			var tile = event.target.closest('[data-lph-toggle]');
			if (tile) {
				event.preventDefault();
				var id = tile.getAttribute('data-lph-toggle');
				if (tile.getAttribute('aria-expanded') === 'true') {
					closePanel(root, id, false);
				} else {
					openPanel(root, id);
				}
				return;
			}
			var closer = event.target.closest('[data-lph-close]');
			if (closer) {
				event.preventDefault();
				var panel = closer.closest('[data-lph-panel]');
				closePanel(root, panel.getAttribute('data-lph-panel'), true);
				return;
			}
			var tab = event.target.closest('[data-lph-tab]');
			if (tab) {
				event.preventDefault();
				selectTab(root, tab.parentNode, tab.getAttribute('data-lph-tab'), true);
			}
		});

		root.addEventListener('keydown', function (event) {
			var tile = event.target.closest('[data-lph-toggle]');
			if (tile && event.key === ' ') {
				event.preventDefault();
				tile.click();
				return;
			}
			var tab = event.target.closest('[data-lph-tab]');
			if (tab && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) !== -1) {
				event.preventDefault();
				var tabs = tabsOf(tab.parentNode);
				var i = tabs.indexOf(tab);
				var next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (i + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
				selectTab(root, tab.parentNode, tabs[next].getAttribute('data-lph-tab'), true);
				tabs[next].focus();
				return;
			}
			if (event.key === 'Escape') {
				var panel = event.target.closest('[data-lph-panel]');
				if (panel) {
					closePanel(root, panel.getAttribute('data-lph-panel'), true);
				} else if (search && event.target === search) {
					search.value = '';
					runSearch(root, '', results, grid);
				}
			}
		});

		if (search) {
			search.hidden = false;
			search.addEventListener('input', function () {
				runSearch(root, search.value, results, grid);
			});
		}
	}

	function initAll() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-lph]'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
}());
