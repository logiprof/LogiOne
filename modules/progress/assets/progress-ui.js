/* LogiProgress : choix du groupe (favoris) et des cours avec recherche en direct, onglets, filtres, détail par élève. */
(function () {
	'use strict';

	function norm(text) {
		return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
	}
	function compact(text) {
		return norm(text).replace(/[\s.\-_]/g, '');
	}
	function matches(item, q) {
		if (!q) { return true; }
		var hay = item.getAttribute('data-search') || '';
		return hay.indexOf(q) !== -1 || compact(hay).indexOf(compact(q)) !== -1;
	}
	function each(list, fn) { Array.prototype.forEach.call(list || [], fn); }

	/* ------------------------------------------------------------------ */
	/* Liste de choix (groupes ou cours)                                   */
	/* ------------------------------------------------------------------ */
	function syncChecked(step) {
		each(step.querySelectorAll('[data-psgpt-item]'), function (item) {
			var input = item.querySelector('input');
			item.classList.toggle('is-checked', !!(input && input.checked));
		});
	}

	function setupGroups(root, step) {
		var list = step.querySelector('[data-psgpt-list]');
		if (!list) { return; }
		var modes = step.querySelector('[data-psgpt-modes]');
		var filter = step.querySelector('[data-psgpt-filter]');
		var nomatch = step.querySelector('[data-psgpt-nomatch]');
		var nofav = step.querySelector('[data-psgpt-nofav]');
		var showAll = step.querySelector('[data-psgpt-showall]');
		var allOnly = step.querySelector('[data-psgpt-allonly]');
		var favCount = step.querySelector('[data-psgpt-favcount]');
		var items = list.querySelectorAll('[data-psgpt-item]');

		function countFav() {
			var n = 0;
			each(items, function (it) { if (it.getAttribute('data-fav') === '1') { n++; } });
			return n;
		}
		function render() {
			var mode = list.getAttribute('data-mode');
			var q = mode === 'all' && filter ? norm(filter.value) : '';
			var shown = 0;
			each(items, function (it) {
				var checked = it.classList.contains('is-checked');
				var visible = mode === 'all' ? (matches(it, q) || checked) : (it.getAttribute('data-fav') === '1' || checked);
				it.hidden = !visible;
				if (visible) { shown++; }
			});
			if (modes) {
				each(modes.querySelectorAll('button'), function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-mode') === mode ? 'true' : 'false'); });
			}
			if (allOnly) { allOnly.hidden = mode !== 'all'; }
			if (showAll) { showAll.hidden = mode !== 'fav'; }
			if (nomatch) { nomatch.hidden = !(mode === 'all' && q && shown === 0); }
			if (nofav) { nofav.hidden = !(mode === 'fav' && shown === 0); }
			if (favCount) { favCount.textContent = String(countFav()); }
		}
		function setMode(mode) {
			list.setAttribute('data-mode', mode);
			render();
		}

		if (modes) {
			modes.hidden = false;
			each(modes.querySelectorAll('button'), function (b) {
				b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); });
			});
		}
		if (showAll) {
			showAll.addEventListener('click', function () {
				setMode('all');
				if (filter) { filter.focus(); }
			});
		}
		if (filter) {
			filter.addEventListener('input', render);
			filter.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
		}
		list.addEventListener('change', function () {
			syncChecked(step);
			render();
			if (step.getAttribute('data-psgpt-autosubmit') === '1') {
				var form = step.closest('form');
				if (form) { form.submit(); }
			}
		});

		each(list.querySelectorAll('.psgpt-star'), function (star) {
			star.addEventListener('click', function (e) {
				e.preventDefault();
				var item = star.closest('[data-psgpt-item]');
				var body = new window.FormData();
				body.append('action', root.getAttribute('data-fav-action'));
				body.append('nonce', root.getAttribute('data-fav-nonce'));
				body.append('group_id', star.getAttribute('data-group'));
				star.classList.add('is-busy');
				window.fetch(root.getAttribute('data-fav-url'), { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						star.classList.remove('is-busy');
						if (!res || !res.success) { return; }
						var fav = !!res.data.fav;
						var name = star.getAttribute('data-name') || '';
						var label = fav ? 'Retirer ' + name + ' des favoris' : 'Ajouter ' + name + ' aux favoris';
						item.setAttribute('data-fav', fav ? '1' : '0');
						star.classList.toggle('is-fav', fav);
						star.setAttribute('aria-pressed', fav ? 'true' : 'false');
						star.setAttribute('aria-label', label);
						star.setAttribute('title', label);
						render();
					})
					.catch(function () {
						star.classList.remove('is-busy');
						window.location.href = star.href;
					});
			});
		});

		syncChecked(step);
		setMode(countFav() > 0 ? 'fav' : 'all');
	}

	function setupCourses(step, update) {
		var list = step.querySelector('[data-psgpt-list]');
		if (!list) { return; }
		var filter = step.querySelector('[data-psgpt-filter]');
		var nomatch = step.querySelector('[data-psgpt-nomatch]');
		var hiddenBox = step.querySelector('[data-psgpt-hiddensel]');
		var hiddenText = step.querySelector('[data-psgpt-hiddentext]');
		var clear = step.querySelector('[data-psgpt-clear]');
		var btnAll = step.querySelector('[data-psgpt-all]');
		var btnNone = step.querySelector('[data-psgpt-none]');
		var items = list.querySelectorAll('[data-psgpt-item]');

		function render() {
			var q = filter ? norm(filter.value) : '';
			var shown = 0;
			var hiddenSel = 0;
			each(items, function (it) {
				var ok = matches(it, q);
				it.hidden = !ok;
				if (ok) { shown++; } else if (it.classList.contains('is-checked')) { hiddenSel++; }
			});
			if (nomatch) { nomatch.hidden = !(q && shown === 0); }
			if (hiddenBox) {
				hiddenBox.hidden = hiddenSel === 0;
				if (hiddenText) {
					hiddenText.textContent = hiddenSel === 1
						? '1 autre cours sélectionné reste sélectionné, même s’il est masqué par la recherche.'
						: hiddenSel + ' autres cours sélectionnés restent sélectionnés, même s’ils sont masqués par la recherche.';
				}
			}
			update();
		}
		function setVisible(on) {
			each(items, function (it) {
				if (it.hidden && on) { return; }
				var input = it.querySelector('input');
				if (input) { input.checked = on; }
			});
			syncChecked(step);
			render();
		}

		if (filter) {
			filter.addEventListener('input', render);
			filter.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					var visible = Array.prototype.filter.call(items, function (it) { return !it.hidden; });
					if (visible.length === 1) {
						var input = visible[0].querySelector('input');
						input.checked = !input.checked;
						syncChecked(step);
						render();
					}
				}
			});
		}
		if (clear) { clear.addEventListener('click', function () { filter.value = ''; render(); filter.focus(); }); }
		if (btnAll) { btnAll.hidden = false; btnAll.addEventListener('click', function () { setVisible(true); }); }
		if (btnNone) { btnNone.hidden = false; btnNone.addEventListener('click', function () { setVisible(false); }); }
		list.addEventListener('change', function () { syncChecked(step); render(); });
		syncChecked(step);
		render();
	}

	function setupSelect(root, form) {
		var groups = form.querySelector('[data-psgpt-groups]');
		var courses = form.querySelector('[data-psgpt-courses]');
		var title = form.querySelector('[data-psgpt-sumtitle]');
		var text = form.querySelector('[data-psgpt-sumtext]');
		var submit = form.querySelector('[data-psgpt-submit]');

		function update() {
			if (!title || !courses) { return; }
			var g = groups ? groups.querySelector('input:checked') : null;
			var gName = g ? g.closest('[data-psgpt-item]').querySelector('strong').textContent : '';
			var picked = courses.querySelectorAll('input:checked');
			var lessons = 0;
			var names = [];
			each(picked, function (c) {
				var it = c.closest('[data-psgpt-item]');
				lessons += parseInt(it.getAttribute('data-lessons') || '0', 10);
				names.push(it.querySelector('strong').textContent.split(' ')[0]);
			});
			var n = picked.length;
			title.textContent = (gName || 'Aucun groupe choisi') + (n ? ' · ' + n + (n > 1 ? ' cours sélectionnés' : ' cours sélectionné') : '');
			text.textContent = !g ? 'Choisissez un groupe classe.' : (!n ? 'Choisissez au moins un cours.' : lessons + (lessons > 1 ? ' leçons au total · ' : ' leçon au total · ') + names.join(', '));
			if (submit) {
				submit.disabled = !(g && n);
				submit.setAttribute('aria-disabled', submit.disabled ? 'true' : 'false');
			}
		}

		if (groups) {
			setupGroups(root, groups);
			groups.addEventListener('change', update);
		}
		if (courses) { setupCourses(courses, update); }
		update();
	}

	/* ------------------------------------------------------------------ */
	/* Résultats : onglets de cours, filtres d'élèves, détail par élève    */
	/* ------------------------------------------------------------------ */
	function setupBoard(board) {
		var tabs = board.querySelector('[data-psgpt-tabs]');
		var panels = board.querySelectorAll('[data-psgpt-panel]');
		if (tabs) {
			var buttons = tabs.querySelectorAll('[role="tab"]');
			var select = function (btn, focus) {
				each(buttons, function (b) {
					var on = b === btn;
					b.setAttribute('aria-selected', on ? 'true' : 'false');
					b.tabIndex = on ? 0 : -1;
					var panel = document.getElementById(b.getAttribute('aria-controls'));
					if (panel) { panel.hidden = !on; }
				});
				if (focus) { btn.focus(); }
			};
			tabs.hidden = false;
			board.classList.add('has-tabs');
			each(buttons, function (b, i) {
				b.addEventListener('click', function () { select(b, false); });
				b.addEventListener('keydown', function (e) {
					var k = e.key;
					if (k !== 'ArrowRight' && k !== 'ArrowLeft' && k !== 'Home' && k !== 'End') { return; }
					e.preventDefault();
					var n = buttons.length;
					var j = k === 'Home' ? 0 : (k === 'End' ? n - 1 : (i + (k === 'ArrowRight' ? 1 : -1) + n) % n);
					select(buttons[j], true);
				});
			});
			select(buttons[0], false);
		}
		each(panels, function (panel) {
			var chips = panel.querySelector('[data-psgpt-rowfilter]');
			var none = panel.querySelector('[data-psgpt-norows]');
			if (!chips) { return; }
			chips.hidden = false;
			each(chips.querySelectorAll('button'), function (b) {
				b.addEventListener('click', function () {
					var f = b.getAttribute('data-filter');
					each(chips.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
					var shown = 0;
					each(panel.querySelectorAll('tbody tr'), function (tr) {
						var ok = f === 'all' || tr.getAttribute('data-band') === f;
						tr.hidden = !ok;
						if (ok) { shown++; }
					});
					if (none) { none.hidden = shown > 0; }
				});
			});
		});
	}

	function setupDetail(root, detail) {
		var links = root.querySelectorAll('[data-psgpt-student]');
		var cards = detail.querySelectorAll('[data-psgpt-panel-student]');
		if (!cards.length) { return; }
		detail.classList.add('is-enhanced');
		function show(id, scroll) {
			var found = false;
			each(cards, function (c) {
				var on = c.getAttribute('data-psgpt-panel-student') === String(id);
				c.classList.toggle('is-active', on);
				if (on) { found = true; }
			});
			if (!found) { return false; }
			each(detail.querySelectorAll('.psgpt-st'), function (a) {
				var on = a.getAttribute('data-psgpt-student') === String(id);
				a.classList.toggle('is-active', on);
				if (on) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); }
			});
			if (scroll) {
				detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
			return true;
		}
		each(links, function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var id = a.getAttribute('data-psgpt-student');
				var inList = !!a.closest('.psgpt-student-list');
				show(id, !inList);
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', '#psgpt-student-' + id);
				}
			});
		});
		var m = (window.location.hash || '').match(/^#psgpt-student-(\d+)$/);
		if (!(m && show(m[1], true))) {
			show(cards[0].getAttribute('data-psgpt-panel-student'), false);
		}
	}

	function init() {
		each(document.querySelectorAll('[data-psgpt-ui]'), function (root) {
			if (root.getAttribute('data-psgpt-ready')) { return; }
			root.setAttribute('data-psgpt-ready', '1');
			root.classList.add('psgpt-js');
			each(root.querySelectorAll('[data-psgpt-select]'), function (form) { setupSelect(root, form); });
			each(root.querySelectorAll('[data-psgpt-board]'), setupBoard);
			each(root.querySelectorAll('[data-psgpt-detail]'), function (d) { setupDetail(root, d); });
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());

/* Réinitialiser l'avancement d'un élève : confirmation claire avant d'effacer dans Sensei (LogiOne 1.26.0). */
(function () {
	'use strict';
	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.matches || !form.matches('form[data-psgpt-confirm]')) { return; }
		var text = String(form.getAttribute('data-psgpt-confirm') || '').replace(/\\n/g, '\n');
		if (!window.confirm(text)) { event.preventDefault(); return; }
		var btn = form.querySelector('button');
		if (btn) { btn.disabled = true; btn.textContent = '…'; }
	});
}());

/* Barre « Classe / Modules » au-dessus des résultats : changer de sélection sans quitter LogiProgress (LogiOne 1.33.1). */
(function () {
	'use strict';
	function norm(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim(); }
	function setup(form) {
		var dd = form.querySelector('[data-psgpt-qb-dd]');
		var summary = form.querySelector('[data-psgpt-qb-summary]');
		var filter = form.querySelector('[data-psgpt-qb-filter]');
		var msg = form.querySelector('[data-psgpt-qb-msg]');
		var boxes = form.querySelectorAll('.psgpt-qb-item input[type="checkbox"]');
		function count() { return Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length; }
		function update() {
			var n = count();
			if (summary) { summary.textContent = n + (n > 1 ? ' modules choisis' : ' module choisi'); }
			if (msg && n) { msg.hidden = true; }
		}
		Array.prototype.forEach.call(boxes, function (b) { b.addEventListener('change', update); });
		if (filter) {
			filter.addEventListener('input', function () {
				var q = norm(filter.value);
				Array.prototype.forEach.call(form.querySelectorAll('.psgpt-qb-item'), function (it) {
					it.hidden = !!q && norm(it.getAttribute('data-search')).indexOf(q) === -1;
				});
			});
			filter.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
		}
		var none = form.querySelector('[data-psgpt-qb-none]');
		if (none) { none.addEventListener('click', function () { Array.prototype.forEach.call(boxes, function (b) { b.checked = false; }); update(); }); }
		var close = form.querySelector('[data-psgpt-qb-close]');
		if (close && dd) { close.addEventListener('click', function () { dd.open = false; }); }
		if (dd) {
			dd.addEventListener('toggle', function () { if (dd.open && filter) { filter.focus(); } });
			document.addEventListener('click', function (e) { if (dd.open && !dd.contains(e.target)) { dd.open = false; } });
			document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dd.open) { dd.open = false; } });
		}
		form.addEventListener('submit', function (e) {
			if (!count()) {
				e.preventDefault();
				if (msg) { msg.textContent = 'Choisis au moins un module.'; msg.hidden = false; }
				if (dd) { dd.open = true; }
				return;
			}
			var btn = form.querySelector('[data-psgpt-qb-submit]');
			if (btn) { btn.disabled = true; btn.textContent = 'Chargement…'; }
		});
	}
	function init() { Array.prototype.forEach.call(document.querySelectorAll('form[data-psgpt-quick]'), setup); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());

/* 1.37.2 : les liens vers un cours ou une leçon s'ouvrent TOUJOURS dans un nouvel onglet, pour garder le suivi de la
   classe ouvert. Certains thèmes ou extensions (transitions de page, chargement « instantané ») interceptent les clics
   sur les liens et ignorent target="_blank" : on ouvre l'onglet nous-mêmes, avant eux (phase de capture). */
(function () {
	'use strict';
	var SEL = 'a.psgpt-course-link, a.psgpt-lesson-link, a[data-psgpt-newtab]';
	function open(e) {
		var a = e.target && e.target.closest ? e.target.closest(SEL) : null;
		if (!a || !a.href) { return; }
		if (e.type === 'click' && e.button !== 0) { return; }
		e.preventDefault();
		e.stopImmediatePropagation();
		var w = window.open(a.href, '_blank', 'noopener');
		if (w) { try { w.opener = null; } catch (err) {} }
	}
	window.addEventListener('click', open, true);
	function mark() {
		Array.prototype.forEach.call(document.querySelectorAll(SEL), function (a) { a.target = '_blank'; a.rel = 'noopener'; });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mark); } else { mark(); }
}());
