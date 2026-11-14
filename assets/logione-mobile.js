/* LogiOne 1.39.0 — téléphone : l'onglet actif des menus qui défilent est amené à l'écran ; le menu « Outils » se ferme
   quand on touche ailleurs. */
(function () {
	'use strict';
	if (!window.matchMedia || !window.matchMedia('(max-width: 640px)').matches) { return; }
	function ready(fn) { if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); } else { fn(); } }
	ready(function () {
		var strips = '.gne-tabs, .gca-tabs, .psgpt-tabs, .lpee-admin-tabs, .logieval-secure .les-main-nav, .logieval-secure .les-library-tabs, .logieval-secure .les-settings-tabs, .logieval-secure .les-steps';
		Array.prototype.forEach.call(document.querySelectorAll(strips), function (nav) {
			var active = nav.querySelector('.is-active, [aria-current="page"], [aria-selected="true"], .is-current');
			if (active && active.offsetLeft + active.offsetWidth > nav.clientWidth) {
				nav.scrollLeft = Math.max(0, active.offsetLeft - 16);
			}
		});
		document.addEventListener('click', function (e) {
			Array.prototype.forEach.call(document.querySelectorAll('details.lpp-switch-menu[open]'), function (d) {
				if (!d.contains(e.target)) { d.open = false; }
			});
		});
	});
}());
