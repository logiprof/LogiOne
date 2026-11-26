/* Fiche de correction : copie de l'apprenti·e et résultat attendu côte à côte. */
(function () {
	'use strict';
	var cfg = window.LIR_COMPARE || window.FWR_COMPARE; // nom donné par class-fwr-admin.php (LogiOne)
	if (!cfg || !window.docx) { return; }
	var root = document.getElementById('fwr-compare');
	if (!root) { return; }
	var panes = { left: document.getElementById('fwr-left'), right: document.getElementById('fwr-right') };
	var state = { left: 0, right: cfg.startRight || 0, mode: 'docx', zoom: 1, fit: true, sync: true };
	var cache = {};
	var opts = { className: 'docx', inWrapper: true, ignoreWidth: false, ignoreHeight: false, breakPages: true, renderHeaders: true, renderFooters: true, renderFootnotes: true, experimental: true, useBase64URL: true };

	function body(side) { return panes[side].querySelector('.fwr-pane-body'); }

	function fillSelect(side, items) {
		var sel = panes[side].querySelector('.fwr-pane-sel');
		if (items.length < 2) { sel.style.display = 'none'; return; }
		items.forEach(function (it, i) { var o = document.createElement('option'); o.value = i; o.textContent = it.label; sel.appendChild(o); });
		sel.value = state[side];
		sel.addEventListener('change', function () {
			state[side] = parseInt(sel.value, 10);
			render(side);
			// leçon 14 : afficher en face le document attendu correspondant
			if (side === 'left' && cfg.right.length === cfg.left.length && cfg.right.length > 1) {
				state.right = state.left;
				var rs = panes.right.querySelector('.fwr-pane-sel'); rs.value = state.right; render('right');
			}
		});
	}

	function objets(side, item) {
		var box = panes[side].querySelector('.fwr-pane-objets');
		box.innerHTML = '';
		if (!item || !item.objets || !item.objets.length || (side === 'right' && state.mode === 'pdf')) { box.style.display = 'none'; return; }
		box.style.display = '';
		var t = document.createElement('div'); t.className = 'fwr-obj-title'; t.textContent = 'Non dessiné dans l’aperçu :'; box.appendChild(t);
		item.objets.forEach(function (o) { var d = document.createElement('div'); d.textContent = o; box.appendChild(d); });
	}

	function fetchBlob(url) {
		if (cache[url]) { return Promise.resolve(cache[url]); }
		return fetch(url, { credentials: 'same-origin' }).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.blob();
		}).then(function (b) { cache[url] = b; return b; });
	}

	function render(side) {
		var items = side === 'left' ? cfg.left : cfg.right;
		var item = items[state[side]];
		var el = body(side);
		objets(side, item);
		if (!item) { el.innerHTML = '<p class="fwr-wait">Aucun document.</p>'; return Promise.resolve(); }
		if (side === 'right' && state.mode === 'pdf') {
			if (!item.pdf) { el.innerHTML = '<p class="fwr-wait">PDF indisponible.</p>'; return Promise.resolve(); }
			el.innerHTML = '';
			var f = document.createElement('iframe'); f.src = item.pdf + '#view=FitH'; f.className = 'fwr-pdf'; f.title = 'Résultat attendu (PDF)';
			el.appendChild(f);
			return Promise.resolve();
		}
		el.innerHTML = '<p class="fwr-wait">Chargement du document…</p>';
		return fetchBlob(item.docx).then(function (blob) {
			el.innerHTML = '';
			return window.docx.renderAsync(blob, el, null, opts);
		}).then(function () { applyZoom(); }).catch(function (e) {
			el.innerHTML = '<p class="fwr-wait">Impossible d’afficher ce document (' + e.message + '). Téléchargez-le pour l’ouvrir dans Word.</p>';
		});
	}

	function applyZoom() {
		var z = state.zoom;
		if (state.fit) {
			var w = Math.min(body('left').clientWidth, body('right').clientWidth) - 24;
			var page = root.querySelector('.docx-wrapper section.docx');
			var pw = page ? page.offsetWidth + 40 : 834;
			z = Math.max(0.3, Math.min(1.5, w / pw));
			state.zoom = z;
		}
		root.querySelectorAll('.docx-wrapper').forEach(function (wr) { wr.style.zoom = z; });
		document.getElementById('fwr-zoom-val').textContent = Math.round(z * 100) + ' %';
	}

	// zoom
	root.querySelectorAll('[data-zoom]').forEach(function (b) {
		b.addEventListener('click', function () {
			var a = b.getAttribute('data-zoom');
			if (a === 'fit') { state.fit = true; } else { state.fit = false; state.zoom = Math.max(0.3, Math.min(2, state.zoom + (a === '+' ? 0.1 : -0.1))); }
			applyZoom();
		});
	});
	window.addEventListener('resize', function () { if (state.fit) { applyZoom(); } });

	// PDF officiel / aperçu Word
	panes.right.querySelectorAll('.fwr-seg button').forEach(function (b) {
		b.addEventListener('click', function () {
			panes.right.querySelectorAll('.fwr-seg button').forEach(function (x) { x.classList.remove('on'); });
			b.classList.add('on');
			state.mode = b.getAttribute('data-mode');
			render('right');
		});
	});

	// défilement synchronisé (proportionnel)
	var lock = false;
	function syncFrom(a, b) {
		a.addEventListener('scroll', function () {
			if (!state.sync || lock || state.mode === 'pdf') { return; }
			lock = true;
			var r = a.scrollTop / Math.max(1, a.scrollHeight - a.clientHeight);
			b.scrollTop = r * (b.scrollHeight - b.clientHeight);
			b.scrollLeft = a.scrollLeft;
			window.requestAnimationFrame(function () { lock = false; });
		});
	}
	syncFrom(body('left'), body('right'));
	syncFrom(body('right'), body('left'));
	document.getElementById('fwr-sync').addEventListener('change', function () { state.sync = this.checked; });

	// plein écran
	document.getElementById('fwr-full').addEventListener('click', function () {
		root.classList.toggle('fwr-full');
		this.textContent = root.classList.contains('fwr-full') ? '✕ Quitter le plein écran' : '⛶ Plein écran';
		if (state.fit) { setTimeout(applyZoom, 50); }
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.classList.contains('fwr-full')) { document.getElementById('fwr-full').click(); } });

	// reprendre les propositions de l'IA dans le formulaire
	document.querySelectorAll('[data-take]').forEach(function (b) {
		b.addEventListener('click', function () {
			if (b.getAttribute('data-take') === 'note') {
				document.getElementById('fwr-note').value = document.getElementById('fwr-ia-note').value;
			} else {
				var ta = document.getElementById('fwr-commentaire');
				var txt = document.getElementById('fwr-ia-texte').value;
				ta.value = ta.value.trim() ? ta.value.trim() + '\n\n' + txt : txt;
				ta.focus();
			}
			b.textContent = '✓ Repris';
		});
	});

	fillSelect('left', cfg.left);
	fillSelect('right', cfg.right);
	Promise.all([render('left'), render('right')]).then(applyZoom);
})();
