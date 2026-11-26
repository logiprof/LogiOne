/* LogiProf – cases « Étape terminée » dans les exercices Word et Excel */
(function () {
	'use strict';
	var C = window.LPEX || { logged: false, state: {} };
	var LS = 'lpex_steps';
	function local() { try { return JSON.parse(localStorage.getItem(LS) || '{}'); } catch (e) { return {}; } }
	function saveLocal(all) { try { localStorage.setItem(LS, JSON.stringify(all)); } catch (e) {} }

	document.querySelectorAll('.fw-progress[data-lpex]').forEach(function (box) {
		var key = box.getAttribute('data-lpex');
		var zone = box.closest('.fw-exo-zone') || document;
		var steps = Array.prototype.slice.call(zone.querySelectorAll('.fw-step[data-step]'));
		if (!steps.length) { return; }
		var total = steps.length;
		var src = C.logged ? (C.state || {}) : local();
		var done = {};
		(src[key] || []).forEach(function (n) { done[n] = true; });
		var timer = null;

		box.innerHTML =
			'<div class="lpex-bar"><div class="lpex-top"><strong>📍 Ma progression</strong><span class="lpex-count"></span></div>' +
			'<div class="lpex-track"><div class="lpex-fill"></div></div>' +
			'<div class="lpex-foot"><a href="#" class="lpex-resume"></a><span class="lpex-msg" aria-live="polite"></span></div>' +
			(C.logged ? '' : '<p class="lpex-note">Vous n’êtes pas connecté : la progression reste seulement sur cet ordinateur.</p>') + '</div>';
		var count = box.querySelector('.lpex-count'), fill = box.querySelector('.lpex-fill'),
			resume = box.querySelector('.lpex-resume'), msg = box.querySelector('.lpex-msg');

		steps.forEach(function (st) {
			var n = parseInt(st.getAttribute('data-step'), 10);
			var lab = document.createElement('label');
			lab.className = 'lpex-check';
			lab.innerHTML = '<input type="checkbox"> <span>Étape terminée</span>';
			var cb = lab.querySelector('input');
			cb.checked = !!done[n];
			cb.addEventListener('change', function () {
				// cocher une étape coche aussi les précédentes ; décocher décoche les suivantes
				steps.forEach(function (o) {
					var m = parseInt(o.getAttribute('data-step'), 10);
					if (cb.checked && m <= n) { done[m] = true; }
					if (!cb.checked && m >= n) { delete done[m]; }
				});
				render(); save();
			});
			st._cb = cb;
			st.appendChild(lab);
		});

		function list() { return Object.keys(done).map(Number).sort(function (a, b) { return a - b; }); }
		function next() { for (var i = 0; i < steps.length; i++) { if (!done[parseInt(steps[i].getAttribute('data-step'), 10)]) { return steps[i]; } } return null; }
		function render() {
			var k = list().length, nx = next();
			count.textContent = k + ' / ' + total + ' étapes';
			fill.style.width = Math.round(100 * k / total) + '%';
			steps.forEach(function (st) {
				var n = parseInt(st.getAttribute('data-step'), 10);
				st._cb.checked = !!done[n];
				st.classList.toggle('lpex-done', !!done[n]);
				st.classList.toggle('lpex-next', st === nx && k > 0);
			});
			box.classList.toggle('lpex-complete', k === total);
			if (k === total) { resume.textContent = '🎉 Exercice terminé ! Remettez votre fichier ci-dessous.'; resume.removeAttribute('href'); }
			else if (k === 0) { resume.textContent = '▶ Commencer à l’étape 1'; resume.href = '#' + nx.id; }
			else { resume.textContent = '▶ Reprendre à l’étape ' + nx.getAttribute('data-step'); resume.href = '#' + nx.id; }
		}
		resume.addEventListener('click', function (e) {
			var nx = next(); if (!nx) { e.preventDefault(); return; }
			e.preventDefault(); nx.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
		function save() {
			var all = local(); all[key] = list(); saveLocal(all);
			if (!C.logged) { return; }
			clearTimeout(timer);
			msg.textContent = 'Enregistrement…';
			timer = setTimeout(function () {
				fetch(C.rest, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify({ key: key, steps: list() }) })
					.then(function (r) { if (!r.ok) { throw new Error(r.status); } msg.textContent = '✓ Enregistré'; setTimeout(function () { msg.textContent = ''; }, 2000); })
					.catch(function () { msg.textContent = '⚠ Non enregistré (connexion ?)'; });
			}, 400);
		}
		render();
	});
})();
