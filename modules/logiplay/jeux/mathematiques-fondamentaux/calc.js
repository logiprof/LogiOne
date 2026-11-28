/* Calculatrice LogiPlay (comme dans LogiExam) pour les jeux de mathématiques.
   Écran large : à droite de la question. Écran étroit : bouton « Calculatrice » en bas à droite.
   Clavier : chiffres, + − × ÷ ( ) . , Entrée/=, Retour arrière, Échap/C, %, p (π), r (√).
   Ajoutée aux jeux par outils/calculatrice/ajouter.py (balise <script src="calc.js">). */
(function () {
	'use strict';
	var LG = (document.documentElement.lang || 'fr').slice(0, 2);
	var TX = {
		fr: { t: 'Calculatrice', err: 'Erreur', ins: 'Mettre dans la réponse', open: 'Calculatrice', close: 'Fermer', bk: 'Effacer le dernier caractère' },
		de: { t: 'Taschenrechner', err: 'Fehler', ins: 'In die Antwort übernehmen', open: 'Taschenrechner', close: 'Schliessen', bk: 'Letztes Zeichen löschen' },
		it: { t: 'Calcolatrice', err: 'Errore', ins: 'Inserisci nella risposta', open: 'Calcolatrice', close: 'Chiudi', bk: 'Cancella l’ultimo carattere' }
	}[LG] || null;
	if (!TX) TX = { t: 'Calculatrice', err: 'Erreur', ins: 'Mettre dans la réponse', open: 'Calculatrice', close: 'Fermer', bk: 'Effacer le dernier caractère' };

	var css = ''
		+ '.lpc{position:fixed;z-index:60;width:272px;background:#fff;border-radius:6px;border-top:6px solid #067030;box-shadow:0 14px 34px rgba(49,58,69,.18);padding:12px 12px 14px;font-family:Poppins,system-ui,sans-serif;color:#313a45}'
		+ '.lpc[hidden]{display:none!important}'
		+ '.lpc:focus{outline:none}.lpc.is-active{box-shadow:0 0 0 3px rgba(245,196,0,.55),0 14px 34px rgba(49,58,69,.18)}'
		+ '.lpc-head{display:flex;align-items:center;justify-content:space-between;margin:0 0 8px}'
		+ '.lpc-title{font-size:13px;font-weight:700;color:#5b6672}'
		+ '.lpc-x{display:none;border:0;background:#eef0eb;color:#313a45;border-radius:999px;width:32px;height:32px;font:700 18px/1 Poppins,system-ui,sans-serif;cursor:pointer}'
		+ '.lpc-screen{background:#313a45;border-radius:6px;padding:8px 12px;margin:0 0 10px}'
		+ '.lpc-expr{min-height:20px;color:#c9d1d9;text-align:right;font-size:13px;font-weight:600;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}'
		+ '.lpc-disp{min-height:40px;color:#fff;text-align:right;font-size:28px;line-height:1.3;font-weight:700;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}'
		+ '.lpc-keys{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}'
		+ '.lpc-keys button{min-height:42px;padding:6px;border-radius:6px;border:1.5px solid #d9ddd6;background:#fff;color:#313a45;font:600 17px/1 Poppins,system-ui,sans-serif;cursor:pointer;touch-action:manipulation}'
		+ '.lpc-keys button:hover{border-color:#313a45}.lpc-keys button:active{transform:translateY(1px)}'
		+ '.lpc-keys .op{background:#eef0eb}.lpc-keys .clr{color:#b42318}'
		+ '.lpc-keys .eq{grid-column:span 2;background:#f5c400;border-color:#f5c400;font-weight:700}.lpc-keys .eq:hover{background:#ffd83d;border-color:#ffd83d}'
		+ '.lpc-ins{display:block;width:100%;margin:10px 0 0;min-height:40px;border-radius:999px;border:0;background:#067030;color:#fff;font:700 14px/1.2 Poppins,system-ui,sans-serif;cursor:pointer}.lpc-ins[hidden]{display:none}.lpc-ins:hover{background:#0b4d22}'
		+ '.lpc-fab{position:fixed;z-index:59;right:14px;bottom:calc(14px + env(safe-area-inset-bottom,0px));display:inline-flex;align-items:center;gap:8px;min-height:48px;padding:10px 18px 10px 14px;border:0;border-radius:999px;background:#313a45;color:#fff;font:700 15px/1 Poppins,system-ui,sans-serif;box-shadow:0 8px 22px rgba(49,58,69,.28);cursor:pointer}'
		+ '.lpc-fab[hidden]{display:none!important}'
		+ '.lpc-fab svg{width:20px;height:20px}'
		+ 'body.lpc-dock .wrap{max-width:min(var(--lpc-w,1180px),calc(100% - 336px))!important;margin-left:max(16px,calc((100% - min(var(--lpc-w,1180px),calc(100% - 336px)) - 288px) / 2))!important;margin-right:auto!important}'
		+ '@media (max-width:999px){.lpc{left:auto!important;right:12px;top:auto!important;bottom:calc(72px + env(safe-area-inset-bottom,0px));width:min(320px,calc(100vw - 24px))}.lpc-x{display:block}}';
	var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

	var calc = document.createElement('div');
	calc.className = 'lpc'; calc.tabIndex = 0; calc.hidden = true;
	calc.setAttribute('role', 'application'); calc.setAttribute('aria-label', TX.t);
	var K = function (v, l, c) { return '<button type="button" data-k="' + v + '"' + (c ? ' class="' + c + '"' : '') + '>' + l + '</button>'; };
	calc.innerHTML = '<div class="lpc-head"><span class="lpc-title">' + TX.t + '</span><button type="button" class="lpc-x" aria-label="' + TX.close + '">×</button></div>'
		+ '<div class="lpc-screen"><div class="lpc-expr" aria-live="polite">0</div><div class="lpc-disp">0</div></div>'
		+ '<div class="lpc-keys">'
		+ K('clear', 'C', 'clr') + K('(', '(', 'op') + K(')', ')', 'op') + K('/', '÷', 'op')
		+ K('7', '7') + K('8', '8') + K('9', '9') + K('*', '×', 'op')
		+ K('4', '4') + K('5', '5') + K('6', '6') + K('-', '−', 'op')
		+ K('1', '1') + K('2', '2') + K('3', '3') + K('+', '+', 'op')
		+ K('0', '0') + K('.', '.') + K('percent', '%', 'op') + '<button type="button" data-k="back" class="op" aria-label="' + TX.bk + '">⌫</button>'
		+ K('sqrt', '√', 'op') + K('pi', 'π', 'op') + K('equals', '=', 'eq')
		+ '</div><button type="button" class="lpc-ins" hidden>' + TX.ins + '</button>';
	var fab = document.createElement('button');
	fab.type = 'button'; fab.className = 'lpc-fab'; fab.hidden = true; fab.setAttribute('aria-expanded', 'false');
	fab.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="2.5" width="14" height="19" rx="2"/><path d="M8 6.5h8M8.5 11h.01M12 11h.01M15.5 11h.01M8.5 14.5h.01M12 14.5h.01M15.5 14.5h.01M8.5 18h.01M12 18h3.5"/></svg>' + TX.open;
	document.body.appendChild(calc); document.body.appendChild(fab);
	var ex = calc.querySelector('.lpc-expr'), di = calc.querySelector('.lpc-disp'), ins = calc.querySelector('.lpc-ins');
	var shown = false, open = false, expr = '0';
	function show() { ex.textContent = expr.replace(/\*/g, '×').replace(/\//g, '÷').replace(/-/g, '−'); }

	function wide() { return window.matchMedia('(min-width:1000px)').matches; }
	function put(v) {
		var c = expr || '0';
		expr = (c === '0' || c === TX.err) && v !== '.' ? v : c + v; show();
		if (shown) { di.textContent = '0'; shown = false; }
	}
	function norm(s) {
		var n = String(s || '0').replace(/π/g, 'Math.PI').replace(/√\(/g, 'Math.sqrt(');
		n = n.replace(/(\d+(?:\.\d+)?)%/g, '($1/100)');
		n = n.replace(/(\d|\))Math\.PI/g, '$1*Math.PI').replace(/Math\.PI(\d|\()/g, 'Math.PI*$1');
		n = n.replace(/(\d|\))\(/g, '$1*(').replace(/\)(\d)/g, ')*$1').replace(/(\d|\))Math\.sqrt/g, '$1*Math.sqrt');
		return n;
	}
	function evalc() {
		var e = norm(expr);
		if (!/^[0-9+\-*/.() MathPIsqrt]+$/.test(e)) { di.textContent = TX.err; return; }
		try {
			var r = Function('"use strict";return (' + e + ')')();
			di.textContent = Number.isFinite(r) ? String(Math.round(r * 1e8) / 1e8) : TX.err;
			shown = Number.isFinite(r);
		} catch (x) { di.textContent = TX.err; }
		sync();
	}
	function act(v) {
		calc.classList.add('is-active');
		if (v === 'clear') { expr = '0'; show(); di.textContent = '0'; shown = false; }
		else if (v === 'back') { var c = expr || '0'; expr = c.length <= 1 ? '0' : c.slice(0, -1); show(); }
		else if (v === 'sqrt') put('√(');
		else if (v === 'pi') put('π');
		else if (v === 'percent') put('%');
		else if (v === 'equals') evalc();
		else if (shown && /^[+\-*/]$/.test(v)) { expr = di.textContent + v; show(); di.textContent = '0'; shown = false; }
		else if (shown) { expr = '0'; shown = false; act(v); return; }
		else put(v);
		sync();
	}
	function answerField() { var f = document.getElementById('numin'); return f && !f.disabled ? f : null; }
	function sync() { ins.hidden = !(answerField() && shown && di.textContent !== TX.err); }
	calc.addEventListener('click', function (e) {
		var k = e.target.closest('[data-k]'); if (k) { act(k.getAttribute('data-k')); return; }
		if (e.target.closest('.lpc-x')) { toggle(false); }
	});
	calc.addEventListener('pointerdown', function () { calc.classList.add('is-active'); });
	ins.addEventListener('click', function () {
		var f = answerField(); if (!f) return;
		f.value = di.textContent; f.dispatchEvent(new Event('input', { bubbles: true }));
		if (!wide()) toggle(false);
		try { f.focus({ preventScroll: true }); } catch (x) {}
	});
	document.addEventListener('pointerdown', function (e) { if (!calc.contains(e.target) && e.target !== fab) calc.classList.remove('is-active'); }, true);
	document.addEventListener('keydown', function (e) {
		if (calc.hidden || !calc.classList.contains('is-active')) return;
		if (e.target && e.target.closest && e.target.closest('input, textarea, select')) return;
		var k = e.key, v = '';
		if (/^[0-9]$/.test(k) || ['+', '-', '*', '/', '(', ')', '.'].indexOf(k) >= 0) v = k;
		else if (k === ',') v = '.';
		else if (k === 'Enter' || k === '=') v = 'equals';
		else if (k === 'Backspace') v = 'back';
		else if (k === 'Escape' || k === 'c' || k === 'C') v = 'clear';
		else if (k === '%') v = 'percent';
		else if (k === 'p' || k === 'P') v = 'pi';
		else if (k === 'r' || k === 'R') v = 'sqrt';
		if (!v) return;
		e.preventDefault(); e.stopImmediatePropagation(); act(v);
	}, true);

	function toggle(o) {
		open = o; fab.setAttribute('aria-expanded', o ? 'true' : 'false');
		place();
		if (o) { calc.classList.add('is-active'); try { calc.focus({ preventScroll: true }); } catch (x) {} }
	}
	fab.addEventListener('click', function () { toggle(!open || calc.hidden); });

	function place() {
		var card = document.getElementById('card');
		var onQ = !!card;
		if (!onQ) { calc.hidden = true; fab.hidden = true; document.body.classList.remove('lpc-dock'); return; }
		if (wide()) {
			var w = document.querySelector('.wrap') || card;
			if (!document.body.classList.contains('lpc-dock')) {
				var mw = parseFloat(window.getComputedStyle(w).maxWidth);
				if (mw > 0) document.documentElement.style.setProperty('--lpc-w', mw + 'px');
				document.body.classList.add('lpc-dock');
			}
			fab.hidden = true; calc.hidden = false;
			var r = w.getBoundingClientRect(), cr = card.getBoundingClientRect();
			calc.style.left = Math.round(r.right + 16) + 'px';
			calc.style.top = Math.round(Math.max(16, cr.top)) + 'px';
		} else {
			document.body.classList.remove('lpc-dock'); fab.hidden = false; calc.hidden = !open;
			calc.style.left = ''; calc.style.top = '';
		}
		sync();
	}
	var app = document.getElementById('app') || document.body;
	new MutationObserver(function () { window.requestAnimationFrame(place); }).observe(app, { childList: true, subtree: true });
	window.addEventListener('resize', place); window.addEventListener('scroll', place, { passive: true });
	place();
})();
