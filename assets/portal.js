/*
 * Portail LogiOne : tuiles de contenu dépliées sous la tuile, sans recharger la page.
 * Fichier séparé (et non script dans la page) : fonctionne même si le thème ou une extension
 * d'optimisation déplace ou retarde les scripts. Sans JavaScript, l'adresse ?lp_tuile=… suffit.
 */
(function () {
  'use strict';
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function setUrl(grid, key) {
    try {
      var param = grid.getAttribute('data-lpp-param') || 'lp_tuile';
      var u = new URL(window.location.href);
      if (key) { u.searchParams.set(param, key); } else { u.searchParams.delete(param); }
      window.history.replaceState(window.history.state, '', u.toString());
    } catch (e) { /* adresse inchangée */ }
  }

  /* Le contenu s'ouvre juste sous la tuile choisie. Sans cela, le placement automatique de la grille le met
     sous toute la rangée (sous la plus grande tuile) et il est hors de l'écran. */
  function tracks(value) {
    return String(value || '').split(' ').map(parseFloat).filter(function (n) { return !isNaN(n); });
  }

  function placePanel(tile, panel) {
    var grid = tile.parentNode;
    if (!grid || !panel || panel.parentNode !== grid) { return; }
    panel.style.gridRow = '';
    panel.style.gridColumn = '';
    var cs = window.getComputedStyle(grid);
    var rows = tracks(cs.gridTemplateRows);
    var cols = tracks(cs.gridTemplateColumns);
    if (!rows.length || !cols.length) { return; }
    var rowGap = parseFloat(cs.rowGap) || 0;
    var colGap = parseFloat(cs.columnGap) || 0;
    var g = grid.getBoundingClientRect();
    var t = tile.getBoundingClientRect();
    var bottom = t.bottom - g.top - (parseFloat(cs.paddingTop) || 0) - (parseFloat(cs.borderTopWidth) || 0);
    var left = t.left - g.left - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.borderLeftWidth) || 0);
    // Toute la largeur : le contenu se place à la première ligne sous la tuile qu'aucune autre tuile ne traverse
    // (une grande tuile voisine garde ainsi sa hauteur ; le contenu se place sous elle).
    var others = Array.prototype.filter.call(grid.children, function (el) { return el !== panel && !el.hidden && !el.matches('.lpp-panel, .lph-panel'); }).map(function (el) {
      var r = el.getBoundingClientRect();
      return [r.top - g.top, r.bottom - g.top];
    });
    var line = rows.length + 1;
    var y = 0;
    for (var i = 0; i < rows.length; i++) {
      y += rows[i];
      if (bottom <= y + 2) {
        var edge = y + (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.borderTopWidth) || 0);
        if (!others.some(function (r) { return r[0] < edge - 2 && r[1] > edge + 2; })) {
          line = i + 2;
          break;
        }
      }
      y += rowGap;
    }
    var col = 1;
    var x = 0;
    for (var j = 0; j < cols.length; j++) {
      if (left < x + cols[j] - 2) { col = j + 1; break; }
      x += cols[j] + colGap;
    }
    panel.style.gridRow = String(line);
    panel.style.gridColumn = '1 / -1';
    fullBleed(grid, panel);
  }

  /* Le contenu ouvert occupe toute la largeur de l'écran (LogiOne 1.24.8), pour que les outils « pleine page »
     (LogiPlanning…) s'affichent en entier. */
  function fullBleed(grid, panel) {
    panel.style.marginLeft = '';
    panel.style.marginRight = '';
    var cs = window.getComputedStyle(grid);
    var g = grid.getBoundingClientRect();
    var vw = document.documentElement.clientWidth;
    var left = g.left + (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.borderLeftWidth) || 0);
    var right = vw - (g.right - (parseFloat(cs.paddingRight) || 0) - (parseFloat(cs.borderRightWidth) || 0));
    if (left < 0 || right < 0 || vw < 200) { return; }
    panel.style.marginLeft = (-left) + 'px';
    panel.style.marginRight = (-right) + 'px';
    panel.classList.add('is-fullbleed');
  }

  /* Les grandes tuiles à côté du contenu ouvert gardent leur hauteur (sinon elles s'étirent sur tout le contenu). */
  function unpin(grid) {
    Array.prototype.forEach.call(grid.querySelectorAll('[data-lpp-pinned]'), function (el) {
      el.style.height = '';
      el.style.alignSelf = '';
      el.removeAttribute('data-lpp-pinned');
    });
  }

  function measure(grid) {
    var sizes = [];
    Array.prototype.forEach.call(grid.children, function (el) {
      if (el.matches('.lpp-tile')) { sizes.push([el, el.getBoundingClientRect().height]); }
    });
    return sizes;
  }

  function pin(grid, panel, sizes) {
    var p = panel.getBoundingClientRect();
    sizes.forEach(function (pair) {
      var r = pair[0].getBoundingClientRect();
      if (r.top < p.top - 1 ? r.bottom > p.bottom - 1 : false) {
        pair[0].style.alignSelf = 'start';
        pair[0].style.height = pair[1] + 'px';
        pair[0].setAttribute('data-lpp-pinned', '1');
      }
    });
  }

  function placeOpen(grid) {
    unpin(grid);
    Array.prototype.forEach.call(grid.querySelectorAll('[data-lpp-toggle][aria-expanded="true"]'), function (t) {
      var panel = grid.querySelector('[data-lpp-panel="' + t.getAttribute('data-lpp-toggle') + '"]');
      if (panel) {
        panel.hidden = true;
        var sizes = measure(grid);
        placePanel(t, panel);
        panel.hidden = false;
        pin(grid, panel, sizes);
      }
    });
  }

  function toggle(grid, key, open, focusTile) {
    Array.prototype.forEach.call(grid.querySelectorAll('[data-lpp-toggle]'), function (t) {
      var k = t.getAttribute('data-lpp-toggle');
      var on = open && k === key;
      var panel = grid.querySelector('[data-lpp-panel="' + k + '"]');
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-expanded', on ? 'true' : 'false');
      if (panel) { panel.hidden = true; }
      if (k === key && !on && focusTile) { t.focus(); }
    });
    setUrl(grid, open ? key : '');
    unpin(grid);
    if (open) {
      var tile = grid.querySelector('[data-lpp-toggle="' + key + '"]');
      var openPanel = grid.querySelector('[data-lpp-panel="' + key + '"]');
      if (openPanel) {
        var sizes = measure(grid);
        placePanel(tile, openPanel);
        openPanel.hidden = false;
        pin(grid, openPanel, sizes);
      }
      var top = tile.getBoundingClientRect().top;
      var panelTop = openPanel ? openPanel.getBoundingClientRect().top : top;
      // La tuile et le début de son contenu doivent être visibles ensemble.
      if (top < 0 || top > window.innerHeight * 0.45 || panelTop > window.innerHeight * 0.7) {
        // Contenu placé loin sous la tuile (sous une grande tuile voisine) : on montre le début du contenu.
        var tileBottom = tile.getBoundingClientRect().bottom; var target = panelTop - top + 16 <= window.innerHeight * 0.6 ? top - 16 : (panelTop - tileBottom < window.innerHeight * 0.55 ? panelTop - window.innerHeight * 0.65 : panelTop - window.innerHeight * 0.25);
        window.scrollTo({ top: Math.max(0, window.pageYOffset + target), behavior: reduced ? 'auto' : 'smooth' });
      }
      var heading = grid.querySelector('[data-lpp-panel="' + key + '"] .lpp-panel-title');
      if (heading) { heading.focus({ preventScroll: true }); }
    }
  }

  document.addEventListener('click', function (e) {
    var grid = e.target.closest && e.target.closest('[data-lpp-tiles]');
    if (!grid) { return; }
    var t = e.target.closest('[data-lpp-toggle]');
    if (t) {
      e.preventDefault();
      toggle(grid, t.getAttribute('data-lpp-toggle'), t.getAttribute('aria-expanded') !== 'true');
      return;
    }
    var c = e.target.closest('[data-lpp-close]');
    if (c) {
      e.preventDefault();
      toggle(grid, c.closest('[data-lpp-panel]').getAttribute('data-lpp-panel'), false, true);
    }
  });

  function placeAll() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-lpp-tiles]'), placeOpen);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', placeAll); } else { placeAll(); }
  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(placeAll, 150);
  });

  document.addEventListener('keydown', function (e) {
    var grid = e.target.closest && e.target.closest('[data-lpp-tiles]');
    if (!grid) { return; }
    var t = e.target.closest('[data-lpp-toggle]');
    if (t && e.key === ' ') { e.preventDefault(); t.click(); return; }
    var p = e.target.closest('[data-lpp-panel]');
    if (p && e.key === 'Escape') { toggle(grid, p.getAttribute('data-lpp-panel'), false, true); }
  });
}());
