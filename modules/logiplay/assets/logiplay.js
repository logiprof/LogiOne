/* LogiPlay : largeur visible de la fenêtre (sans la barre de défilement) pour l'affichage pleine largeur */
(function () {
	'use strict';
	function vw() { document.documentElement.style.setProperty('--lpl-vw', document.documentElement.clientWidth + 'px'); }
	vw(); window.addEventListener('resize', vw);
})();
