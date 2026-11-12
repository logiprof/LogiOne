/* LogiLive — tableau de suivi en direct (chargé seulement là où le tableau est affiché). */
(function () {
	'use strict';

	var config = window.LogiLiveConfig || null;
	var i18n = (config && config.i18n) || {};

	// Pleine largeur sans barre de défilement horizontale : largeur visible réelle (sans la barre verticale).
	function setViewportWidth() {
		document.documentElement.style.setProperty('--llv-vw', document.documentElement.clientWidth + 'px');
	}

	function fmt(str, value) {
		return String(str || '').replace('%s', value);
	}

	function setLive(app, text) {
		var el = app.querySelector('[data-llv-live-time]');
		if (el) {
			el.textContent = text;
		}
	}

	/* Filtres côté navigateur : état (tous / connectés / hors ligne) + recherche. Conservés entre deux actualisations. */
	function applyFilters(app) {
		var state = app._llvFilter || 'all';
		var query = (app._llvQuery || '').trim();
		var normalized = query.normalize ? query.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() : query.toLowerCase();
		var rows = app.querySelectorAll('[data-llv-row]');
		var visible = 0;

		rows.forEach(function (row) {
			var okState = state === 'all' || row.getAttribute('data-state') === state;
			var okQuery = !normalized || (row.getAttribute('data-name') || '').indexOf(normalized) !== -1;
			row.hidden = !(okState && okQuery);
			if (!row.hidden) {
				visible++;
			}
		});

		app.querySelectorAll('[data-llv-filter]').forEach(function (chip) {
			chip.setAttribute('aria-pressed', chip.getAttribute('data-llv-filter') === state ? 'true' : 'false');
		});

		var search = app.querySelector('[data-llv-search]');
		if (search && search.value !== (app._llvQuery || '')) {
			search.value = app._llvQuery || '';
		}

		var none = app.querySelector('[data-llv-nomatch]');
		if (none) {
			none.hidden = !(rows.length && visible === 0);
		}
	}

	function refresh(app) {
		var results = app.querySelector('[data-llv-results]');
		if (!results || !config || app._llvBusy) {
			return;
		}

		var body = new URLSearchParams();
		body.append('action', config.action);
		body.append('nonce', config.nonce);
		body.append('group_id', app.getAttribute('data-group-id') || '0');
		body.append('online_minutes', app.getAttribute('data-online-minutes') || '5');

		app._llvBusy = true;
		app.classList.add('is-loading');

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data) {
					throw new Error('payload');
				}
				// Les listes « Avant » ouvertes restent ouvertes après l'actualisation.
				var open = Array.prototype.map.call(results.querySelectorAll('details[data-llv-more][open]'), function (d) { return d.getAttribute('data-llv-more'); });
				results.innerHTML = payload.data.html;
				open.forEach(function (key) {
					var d = results.querySelector('details[data-llv-more="' + key + '"]');
					if (d) { d.open = true; }
				});
				app.classList.remove('is-error');
				app._llvBackoff = 0;
				setLive(app, fmt(i18n.updated, payload.data.time || ''));
				applyFilters(app);
			})
			.catch(function () {
				app.classList.add('is-error');
				setLive(app, i18n.offline || '');
				// 1.38.1 : serveur en difficulté → on espace les actualisations (jusqu'à 2 min).
				app._llvBackoff = Math.min(120000, app._llvBackoff ? app._llvBackoff * 2 : 15000);
			})
			.finally(function () {
				app._llvBusy = false;
				app.classList.remove('is-loading');
			});
	}

	function bind(app) {
		var refreshMs = Math.max(10000, parseInt(app.getAttribute('data-refresh-ms') || '15000', 10));
		var select = app.querySelector('[data-llv-group-select]');
		var form = app.querySelector('[data-llv-group-form]');
		var param = app.getAttribute('data-group-param');

		app.classList.add('has-js');
		app._llvFilter = 'all';
		app._llvQuery = '';

		if (select) {
			select.addEventListener('change', function () {
				app.setAttribute('data-group-id', select.value || '0');
				// L'adresse de la page garde le groupe choisi (utile pour recharger ou mettre en favori).
				if (param && window.history && window.history.replaceState) {
					var url = new URL(window.location.href);
					if (select.value && select.value !== '0') {
						url.searchParams.set(param, select.value);
					} else {
						url.searchParams.delete(param);
					}
					window.history.replaceState(null, '', url.toString());
				}
				app._llvFilter = 'all';
				app._llvQuery = '';
				refresh(app);
			});
		}
		if (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				refresh(app);
			});
		}

		// Délégation : les filtres sont recréés à chaque actualisation.
		app.addEventListener('click', function (event) {
			var chip = event.target.closest('[data-llv-filter]');
			if (chip && app.contains(chip)) {
				app._llvFilter = chip.getAttribute('data-llv-filter');
				applyFilters(app);
			}
		});
		app.addEventListener('input', function (event) {
			if (event.target.matches('[data-llv-search]')) {
				app._llvQuery = event.target.value;
				applyFilters(app);
			}
		});

		if (refreshMs >= 10000) {
			var last = Date.now();
			window.setInterval(function () {
				// Pas d'actualisation quand l'onglet est caché : moins de charge pour le serveur.
				if (!document.hidden && Date.now() - last >= refreshMs + (app._llvBackoff || 0)) {
					last = Date.now();
					refresh(app);
				}
			}, 2500);
		}

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				app.classList.add('is-paused');
				setLive(app, i18n.paused || '');
			} else {
				app.classList.remove('is-paused');
				refresh(app);
			}
		});

		applyFilters(app);
	}

	setViewportWidth();
	window.addEventListener('resize', setViewportWidth);

	document.addEventListener('DOMContentLoaded', function () {
		setViewportWidth();
		document.querySelectorAll('[data-llv-app]').forEach(bind);
	});
}());
