/*
 * LogiLive — signale que l'utilisateur connecté est sur la page, et sa dernière action.
 * - Présence : un signal toutes les 10 s (60 s si l'onglet est caché) ; « page fermée » envoyé à la fermeture.
 * - Actions (si l'école les active) : seul le TYPE est envoyé (défilement en %, clic, vidéo, copie, onglet quitté,
 *   inactivité). Jamais le texte copié, jamais ce qui est tapé : les touches servent seulement à savoir que l'élève
 *   n'est pas inactif.
 */
(function () {
	'use strict';

	var cfg = window.LogiLivePing;
	if (!cfg || !cfg.ajaxUrl || !cfg.nonce || !window.fetch) {
		return;
	}

	var HEART = parseInt(cfg.intervalMs || '10000', 10);
	if (!(HEART >= 5000)) {
		HEART = 10000;
	}
	var HIDDEN_HEART = 60000;
	var IDLE_AFTER = 60000;
	var MIN_GAP = 3000; // au plus une action envoyée toutes les 3 s (la dernière gagne)
	var track = String(cfg.actions) === '1';

	var lastSent = 0;
	var lastInput = Date.now();
	var idle = false;
	var pending = null;
	var timer = null;
	var lastScroll = -1;

	function body(event, value) {
		var b = new URLSearchParams();
		b.append('action', cfg.action);
		b.append('nonce', cfg.nonce);
		b.append('context', cfg.context || '');
		b.append('lesson_id', cfg.lessonId || '0');
		b.append('course_id', cfg.courseId || '0');
		b.append('title', cfg.title || document.title || '');
		b.append('url', cfg.url || window.location.href);
		b.append('token', cfg.token || '');
		b.append('event', event || 'heartbeat');
		b.append('value', String(value || 0));
		return b.toString();
	}

	function send(event, value, beacon) {
		var data = body(event, value);
		lastSent = Date.now();
		if (beacon && navigator.sendBeacon) {
			try {
				if (navigator.sendBeacon(cfg.ajaxUrl, new Blob([data], { type: 'application/x-www-form-urlencoded; charset=UTF-8' }))) {
					return;
				}
			} catch (e) { /* on passe par fetch */ }
		}
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: !!beacon,
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: data
		}).catch(function () {});
	}

	function flush() {
		timer = null;
		if (pending) {
			send(pending.event, pending.value);
			pending = null;
		}
	}

	function action(event, value) {
		if (!track) {
			return;
		}
		pending = { event: event, value: value };
		if (!timer) {
			timer = window.setTimeout(flush, Math.max(0, MIN_GAP - (Date.now() - lastSent)));
		}
	}

	function now(event, value) {
		if (!track) {
			return;
		}
		if (timer) {
			window.clearTimeout(timer);
			timer = null;
		}
		pending = null;
		send(event, value);
	}

	function input() {
		lastInput = Date.now();
		if (idle) {
			idle = false;
			action('active');
		}
	}

	/* Présence */
	send('heartbeat');
	window.setInterval(function () {
		if (track && !idle && !document.hidden && Date.now() - lastInput >= IDLE_AFTER) {
			idle = true;
			now('idle');
			return;
		}
		if (Date.now() - lastSent >= (document.hidden ? HIDDEN_HEART : HEART) - 500) {
			send('heartbeat');
		}
	}, 2500);

	// Onglet quitté : envoyé après un court instant, pour ne pas le confondre avec le passage à une autre page.
	var leaving = false;
	var hiddenTimer = null;
	var hiddenSent = false;
	document.addEventListener('visibilitychange', function () {
		if (leaving) {
			return;
		}
		if (document.hidden) {
			hiddenTimer = window.setTimeout(function () {
				hiddenTimer = null;
				if (leaving) { return; }
				hiddenSent = true;
				if (track) { now('hidden'); } else { send('heartbeat'); }
			}, 400);
		} else {
			if (hiddenTimer) {
				window.clearTimeout(hiddenTimer);
				hiddenTimer = null;
			}
			lastInput = Date.now();
			idle = false;
			if (hiddenSent) {
				hiddenSent = false;
				if (track) { now('visible'); } else { send('heartbeat'); }
			}
		}
	});

	// Fermeture de l'onglet ou départ vers une autre page : signalé tout de suite.
	window.addEventListener('pagehide', function () {
		leaving = true;
		if (hiddenTimer) {
			window.clearTimeout(hiddenTimer);
			hiddenTimer = null;
		}
		send('leave', 0, true);
	});

	// Retour sur la page par « Précédent » (page gardée en mémoire par le navigateur).
	window.addEventListener('pageshow', function (e) {
		if (e.persisted) {
			leaving = false;
			send('heartbeat');
		}
	});

	/* Actions (type seulement) */
	['mousemove', 'keydown', 'touchstart', 'wheel'].forEach(function (name) {
		window.addEventListener(name, input, { passive: true, capture: true });
	});

	window.addEventListener('scroll', function () {
		input();
		var doc = document.documentElement;
		var max = Math.max(1, doc.scrollHeight);
		var pct = Math.min(100, Math.round(((window.scrollY || doc.scrollTop) + window.innerHeight) / max * 100));
		if (Math.abs(pct - lastScroll) >= 5 || (pct === 100 && lastScroll !== 100)) {
			lastScroll = pct;
			action('scroll', pct);
		}
	}, { passive: true });

	document.addEventListener('click', function () {
		input();
		action('click');
	}, true);

	document.addEventListener('copy', function () {
		input();
		now('copy'); // aucun contenu : seulement « a copié »
	}, true);

	['play', 'pause', 'ended'].forEach(function (name) {
		document.addEventListener(name, function (e) {
			var t = e.target && e.target.tagName;
			if (t === 'VIDEO' || t === 'AUDIO') {
				input();
				if (name === 'pause' && e.target.ended) {
					return;
				}
				now(name === 'play' ? 'video_play' : (name === 'pause' ? 'video_pause' : 'video_end'));
			}
		}, true);
	});

	// Vidéo YouTube / Vimeo ou exercice intégré : on sait seulement que l'élève est entré dedans.
	window.addEventListener('blur', function () {
		window.setTimeout(function () {
			var el = document.activeElement;
			if (el && el.tagName === 'IFRAME') {
				lastInput = Date.now();
				now('embed');
			}
		}, 0);
	});

	/* Avis aux élèves (une seule fois) */
	function notice() {
		// Jamais par-dessus une évaluation LogiExam en cours.
		if (!cfg.notice || document.querySelector('[data-les-student]')) {
			return;
		}
		try {
			if (window.localStorage.getItem('logilive-notice') === '1') {
				return;
			}
		} catch (e) { /* stockage indisponible */ }
		var box = document.createElement('div');
		box.className = 'llv-notice';
		box.setAttribute('role', 'status');
		box.style.cssText = 'position:fixed;left:16px;right:16px;bottom:16px;z-index:99999;max-width:560px;margin:0 auto;background:#313a45;color:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 10px 30px rgba(0,0,0,.25);font:500 14px/1.45 Poppins,system-ui,sans-serif;display:flex;gap:12px;align-items:center;';
		var text = document.createElement('span');
		text.textContent = cfg.notice;
		var ok = document.createElement('button');
		ok.type = 'button';
		ok.textContent = cfg.noticeOk || 'OK';
		ok.style.cssText = 'flex:none;border:0;border-radius:999px;background:#f5c400;color:#1d232b;font:600 14px Poppins,system-ui,sans-serif;padding:8px 16px;cursor:pointer;';
		ok.addEventListener('click', function (e) {
			e.stopPropagation();
			try { window.localStorage.setItem('logilive-notice', '1'); } catch (err) { /* rien */ }
			box.remove();
			send('notice_ok');
		});
		box.appendChild(text);
		box.appendChild(ok);
		document.body.appendChild(box);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', notice);
	} else {
		notice();
	}
}());
