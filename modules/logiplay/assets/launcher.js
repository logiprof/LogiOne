(function () {
	'use strict';
	var overlay = null, lastButton = null, wentFull = false, scrollY0 = 0;
	/* Safari : on attend la fin de l'animation du plein écran (entrée ou sortie) avant de charger le jeu ou de retirer la fenêtre du jeu, sinon Safari quitte le plein écran ou laisse une page blanche. */
	function afterFs(cb, ms) { var done = false; function go() { if (done) return; done = true; document.removeEventListener('fullscreenchange', go); document.removeEventListener('webkitfullscreenchange', go); setTimeout(cb, 60); } document.addEventListener('fullscreenchange', go); document.addEventListener('webkitfullscreenchange', go); setTimeout(go, ms || 900); }
	function fsElement() { return document.fullscreenElement || document.webkitFullscreenElement || null; }
	function requestFs(el) { try { var fn = el.requestFullscreen || el.webkitRequestFullscreen; if (!fn) return false; var p = fn.call(el, { navigationUI: 'hide' }); if (p && p.catch) p.catch(function () {}); return true; } catch (e) { return false; } }
	function exitFs() { try { if (!fsElement()) return; var fn = document.exitFullscreen || document.webkitExitFullscreen; if (fn) { var p = fn.call(document); if (p && p.catch) p.catch(function () {}); } } catch (e) {} }
	function open(btn) {
		if (overlay) return; lastButton = btn;
		overlay = document.createElement('div'); overlay.className = 'jdr-overlay';
		overlay.setAttribute('role', 'dialog'); overlay.setAttribute('aria-modal', 'true'); overlay.setAttribute('aria-label', btn.getAttribute('data-jdr-title') || 'Jeu de révision');
		var loader = document.createElement('div'); loader.className = 'jdr-loader'; var lg = btn.getAttribute('data-jdr-lang'); loader.textContent = lg === 'de' ? 'Spiel wird geladen…' : (lg === 'it' ? 'Caricamento del gioco…' : 'Chargement du jeu…');
		var frame = document.createElement('iframe'); frame.className = 'jdr-frame'; frame.title = btn.getAttribute('data-jdr-title') || 'Jeu de révision';
		frame.setAttribute('allow', 'fullscreen; autoplay'); frame.setAttribute('allowfullscreen', ''); var src = btn.getAttribute('data-jdr-src');
		frame.addEventListener('load', function () { loader.remove(); try { frame.focus(); } catch (e) {} });
		overlay.appendChild(loader); overlay.appendChild(frame); document.body.appendChild(overlay);
		scrollY0 = window.pageYOffset || 0;
		document.documentElement.classList.add('jdr-open');
		wentFull = requestFs(overlay); /* inside the click: user gesture required */
		if (wentFull) { afterFs(function () { frame.src = src; }, 900); } else { frame.src = src; }
	}
	/* After the game closes, reload the progress block of its card (logged-in users). */
	function refresh(id, lang) {
		var cfg = (window.JDR && window.JDR.cfg) || {};
		if (!id || !cfg.loggedIn || !cfg.rest || !window.fetch) return;
		fetch(cfg.rest + 'carte/' + encodeURIComponent(id) + (lang === 'de' || lang === 'it' ? '?lang=' + lang : ''), { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (j) {
				if (!j || typeof j.html !== 'string') return;
				document.querySelectorAll('[data-jdr-prog="' + id + '"]').forEach(function (el) { el.innerHTML = j.html; });
				if (j.bouton) document.querySelectorAll('.jdr-play[data-jdr-id="' + id + '"]').forEach(function (b) { if ((b.getAttribute('data-jdr-lang') || 'fr') === (lang || 'fr')) b.textContent = j.bouton; });
			}).catch(function () {});
	}
	function close() {
		if (!overlay) return; var o = overlay; overlay = null;
		var finish = function () {
			o.remove(); document.documentElement.classList.remove('jdr-open');
			void document.body.offsetHeight; window.scrollTo(0, scrollY0); /* Safari : redessine la page (sinon écran blanc) */
			window.requestAnimationFrame(function () { document.body.style.transform = 'translateZ(0)'; window.requestAnimationFrame(function () { document.body.style.transform = ''; }); });
		};
		if (fsElement()) { var fr = o.querySelector('iframe'); if (fr) fr.style.visibility = 'hidden'; afterFs(finish, 900); exitFs(); } else { finish(); }
		if (lastButton) { try { lastButton.focus(); } catch (e) {} refresh(lastButton.getAttribute('data-jdr-id'), lastButton.getAttribute('data-jdr-lang')); } }
	document.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('.jdr-play[data-jdr-src]'); if (!b) return; e.preventDefault(); open(b); });
	/* Leaving fullscreen (Esc or the game's button) keeps the game open: it still fills the window. */
	function onFs() { if (overlay && !fsElement()) wentFull = false; }
	document.addEventListener('fullscreenchange', onFs); document.addEventListener('webkitfullscreenchange', onFs);
	document.addEventListener('keydown', function (e) { if (overlay && e.key === 'Escape') close(); });
	window.addEventListener('message', function (e) { if (e.origin !== window.location.origin) return; if (e.data && e.data.jdr === 'close') close(); });
})();
