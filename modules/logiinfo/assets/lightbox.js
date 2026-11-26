/* LogiInfo – agrandir une image par-dessus la page (au lieu d'ouvrir le fichier). Zoom : molette, double-clic, boutons ; Échap ou clic hors de l'image pour fermer. */
(function () {
	'use strict';
	var IMG = /\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i;
	var box, img, cap, scale = 1, tx = 0, ty = 0, drag = null, list = [], idx = 0, last = null;

	function build() {
		box = document.createElement('div');
		box.className = 'li-zoom';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.setAttribute('aria-label', 'Image agrandie');
		box.innerHTML = '<div class="li-zoom-stage"><img alt=""></div><div class="li-zoom-cap"></div>' +
			'<div class="li-zoom-bar"><button type="button" data-a="prev" aria-label="Image précédente">‹</button><button type="button" data-a="out" aria-label="Dézoomer">−</button>' +
			'<button type="button" data-a="fit" aria-label="Taille normale">1:1</button><button type="button" data-a="in" aria-label="Zoomer">+</button><button type="button" data-a="next" aria-label="Image suivante">›</button>' +
			'<button type="button" data-a="close" class="li-zoom-x" aria-label="Fermer">✕</button></div>';
		document.body.appendChild(box);
		img = box.querySelector('img'); cap = box.querySelector('.li-zoom-cap');
		box.addEventListener('click', function (e) {
			var a = e.target.getAttribute && e.target.getAttribute('data-a');
			if (a === 'close') { close(); } else if (a === 'in') { zoom(1.4); } else if (a === 'out') { zoom(1 / 1.4); } else if (a === 'fit') { reset(); }
			else if (a === 'prev') { go(-1); } else if (a === 'next') { go(1); }
			else if (e.target === box || e.target.classList.contains('li-zoom-stage')) { close(); }
		});
		img.addEventListener('dblclick', function (e) { e.preventDefault(); scale > 1 ? reset() : zoom(2.2); });
		box.addEventListener('wheel', function (e) { e.preventDefault(); zoom(e.deltaY < 0 ? 1.15 : 1 / 1.15); }, { passive: false });
		img.addEventListener('pointerdown', function (e) { if (scale <= 1) { return; } drag = { x: e.clientX - tx, y: e.clientY - ty }; img.setPointerCapture(e.pointerId); });
		img.addEventListener('pointermove', function (e) { if (!drag) { return; } tx = e.clientX - drag.x; ty = e.clientY - drag.y; apply(); });
		img.addEventListener('pointerup', function () { drag = null; });
		document.addEventListener('keydown', function (e) {
			if (!box.classList.contains('on')) { return; }
			if (e.key === 'Escape') { close(); } else if (e.key === 'ArrowRight') { go(1); } else if (e.key === 'ArrowLeft') { go(-1); }
			else if (e.key === '+' || e.key === '=') { zoom(1.4); } else if (e.key === '-') { zoom(1 / 1.4); }
		});
	}
	function apply() { img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')'; img.style.cursor = scale > 1 ? 'grab' : 'zoom-in'; }
	function reset() { scale = 1; tx = ty = 0; apply(); }
	function zoom(f) { scale = Math.min(6, Math.max(1, scale * f)); if (scale === 1) { tx = ty = 0; } apply(); }
	function show(i) {
		idx = (i + list.length) % list.length;
		var it = list[idx];
		img.src = it.src; img.alt = it.alt; cap.textContent = it.cap; cap.style.display = it.cap ? '' : 'none';
		box.querySelector('[data-a=prev]').style.display = box.querySelector('[data-a=next]').style.display = list.length > 1 ? '' : 'none';
		reset();
	}
	function go(d) { if (list.length > 1) { show(idx + d); } }
	function open(i, from) {
		if (!box) { build(); }
		last = from; show(i);
		box.classList.add('on'); document.documentElement.classList.add('li-zoom-lock');
		box.querySelector('.li-zoom-x').focus();
	}
	function close() { box.classList.remove('on'); document.documentElement.classList.remove('li-zoom-lock'); img.removeAttribute('src'); if (last) { last.focus(); } }

	var figs = Array.prototype.slice.call(document.querySelectorAll('.wp-block-image img, .entry-content figure img'));
	figs.forEach(function (im) {
		if (im.closest('.li-zoom')) { return; }
		var a = im.closest('a');
		if (a && !IMG.test(a.getAttribute('href') || '')) { return; } // lien vers une autre page : on ne touche pas
		var fig = im.closest('figure');
		var c = fig && fig.querySelector('figcaption');
		var item = { src: a ? a.href : (im.currentSrc || im.src), alt: im.alt || '', cap: c ? c.textContent.trim() : '' };
		var n = list.push(item) - 1;
		var target = a || im;
		target.style.cursor = 'zoom-in';
		if (!a) { im.setAttribute('tabindex', '0'); im.setAttribute('role', 'button'); }
		target.setAttribute('aria-label', 'Agrandir l’image' + (item.cap ? ' : ' + item.cap : ''));
		target.addEventListener('click', function (e) { e.preventDefault(); open(n, target); });
		target.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(n, target); } });
	});
})();
