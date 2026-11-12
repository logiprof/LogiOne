/*
 * LogiOne · Permissions : un onglet par module, rôles affichés au choix, « Tout le module » par rôle.
 * Sans JavaScript, tous les modules restent affichés les uns sous les autres (même formulaire).
 */
(function () {
  'use strict';
  var form = document.querySelector('[data-lpperm]');
  if (!form) { return; }
  var tabs = Array.prototype.slice.call(form.querySelectorAll('[data-lpperm-tab]'));
  var panels = Array.prototype.slice.call(form.querySelectorAll('[data-lpperm-panel]'));
  var roleToggles = Array.prototype.slice.call(form.querySelectorAll('[data-lpperm-role-toggle]'));
  var dirty = form.querySelector('[data-lpperm-dirty]');
  var changed = false;
  var ACCESS = form.getAttribute('data-access-perm') || 'lp_access';

  function store(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* stockage indisponible */ } }
  function read(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } }

  function box(role, perm) { return form.querySelector('input[name="perm[' + role + '][' + perm + ']"]'); }

  /* ---- Onglets ---- */
  function select(group, focus) {
    tabs.forEach(function (tab) {
      var on = tab.getAttribute('data-lpperm-tab') === group;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
      tab.classList.toggle('is-active', on);
      if (on && focus) { tab.focus(); }
    });
    panels.forEach(function (panel) { panel.hidden = panel.getAttribute('data-lpperm-panel') !== group; });
    store('lpperm-tab', group);
  }
  form.querySelector('[data-lpperm-tabs]').hidden = false;
  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { select(tab.getAttribute('data-lpperm-tab')); });
    tab.addEventListener('keydown', function (e) {
      var j = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : -2;
      if (j === -2) { return; }
      e.preventDefault();
      select(tabs[(j + tabs.length) % tabs.length].getAttribute('data-lpperm-tab'), true);
    });
  });
  var first = read('lpperm-tab');
  select(tabs.some(function (t) { return t.getAttribute('data-lpperm-tab') === first; }) ? first : tabs[0].getAttribute('data-lpperm-tab'));

  /* ---- Rôles affichés ---- */
  function applyRoles() {
    var shown = roleToggles.filter(function (t) { return t.checked; }).map(function (t) { return t.value; });
    Array.prototype.forEach.call(form.querySelectorAll('[data-lpperm-role]'), function (cell) {
      cell.hidden = shown.indexOf(cell.getAttribute('data-lpperm-role')) === -1;
    });
    store('lpperm-roles', JSON.stringify(shown));
    counts();
  }
  var savedRoles = null;
  try { savedRoles = JSON.parse(read('lpperm-roles') || 'null'); } catch (e) { savedRoles = null; }
  if (Array.isArray(savedRoles) && savedRoles.length) {
    roleToggles.forEach(function (t) { t.checked = savedRoles.indexOf(t.value) !== -1; });
  }
  roleToggles.forEach(function (t) { t.addEventListener('change', applyRoles); });

  /* ---- « Tout le module », droits liés, compteurs ---- */
  function syncAll(panel) {
    Array.prototype.forEach.call(panel.querySelectorAll('[data-lpperm-all]'), function (all) {
      var role = all.getAttribute('data-lpperm-all');
      var boxes = panel.querySelectorAll('input[name^="perm[' + role + ']"]');
      var on = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
      all.checked = on === boxes.length && on > 0;
      all.indeterminate = on > 0 && on < boxes.length;
    });
  }

  function counts() {
    var shown = roleToggles.filter(function (t) { return t.checked; }).map(function (t) { return t.value; });
    panels.forEach(function (panel) {
      var group = panel.getAttribute('data-lpperm-panel');
      var key = panel.getAttribute('data-key-perm');
      var n = shown.filter(function (role) {
        return key ? (box(role, key) || {}).checked : Array.prototype.some.call(panel.querySelectorAll('input[name^="perm[' + role + ']"]'), function (b) { return b.checked; });
      }).length;
      var label = form.querySelector('[data-lpperm-count="' + group + '"]');
      if (label) { label.textContent = n ? n + (n > 1 ? ' rôles' : ' rôle') : 'aucun rôle'; label.classList.toggle('is-none', !n); }
    });
  }

  function markDirty() {
    changed = true;
    if (dirty) { dirty.hidden = false; }
  }

  panels.forEach(function (panel) {
    Array.prototype.forEach.call(panel.querySelectorAll('[data-lpperm-js-only]'), function (row) { row.hidden = false; });
    var key = panel.getAttribute('data-key-perm');
    panel.addEventListener('change', function (e) {
      var t = e.target;
      if (t.hasAttribute('data-lpperm-all')) {
        var role = t.getAttribute('data-lpperm-all');
        Array.prototype.forEach.call(panel.querySelectorAll('input[name^="perm[' + role + ']"]'), function (b) { b.checked = t.checked; });
        if (t.checked && box(role, ACCESS)) { box(role, ACCESS).checked = true; }
      } else if (t.name && t.name.indexOf('perm[') === 0 && t.checked) {
        // Un droit dans un module suppose de pouvoir ouvrir ce module, et LogiOne.
        var r = t.name.slice(5, t.name.indexOf(']'));
        if (key && box(r, key)) { box(r, key).checked = true; }
        if (box(r, ACCESS)) { box(r, ACCESS).checked = true; }
      }
      panels.forEach(syncAll);
      counts();
      markDirty();
    });
    syncAll(panel);
  });
  applyRoles();

  form.addEventListener('submit', function () { changed = false; });
  window.addEventListener('beforeunload', function (e) {
    if (changed) { e.preventDefault(); e.returnValue = ''; }
  });
})();
