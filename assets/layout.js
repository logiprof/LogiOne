/*
 * LogiOne · Disposition du portail : glisser-déposer (ou flèches) pour l'ordre des tuiles,
 * taille de chaque tuile, nombre de colonnes ; aperçu mis à jour en direct.
 */
(function () {
  'use strict';
  var form = document.querySelector('[data-lpl-form]');
  if (!form) { return; }
  var list = form.querySelector('[data-lpl-list]');
  var orderField = form.querySelector('[data-lpl-order]');
  var preview = form.querySelector('[data-lpl-preview]');
  var columns = form.querySelector('[data-lpl-columns]');
  var dragged = null;

  function items() { return Array.prototype.slice.call(list.querySelectorAll('.lpl-item')); }

  function light(hex) {
    var m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
    if (!m) { return true; }
    var n = parseInt(m[1], 16);
    return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) > 150;
  }

  function refresh() {
    var all = items();
    orderField.value = all.map(function (li) { return li.getAttribute('data-key'); }).join(',');
    all.forEach(function (li, i) {
      li.querySelector('[data-lpl-up]').disabled = i === 0;
      li.querySelector('[data-lpl-down]').disabled = i === all.length - 1;
    });
    var cols = parseInt(columns.value, 10) || 3;
    preview.className = 'lpl-preview lpl-cols-' + cols;
    preview.innerHTML = '';
    all.forEach(function (li) {
      var size = li.querySelector('[data-lpl-size]').value;
      var cell = document.createElement('div');
      var color = li.getAttribute('data-color');
      cell.className = 'lpl-cell lpl-size-' + size + (light(color) ? ' is-light' : ' is-dark');
      cell.style.background = color;
      cell.textContent = li.getAttribute('data-label');
      cell.setAttribute('data-key', li.getAttribute('data-key'));
      preview.appendChild(cell);
    });
  }

  function move(li, delta) {
    var all = items();
    var i = all.indexOf(li);
    var j = i + delta;
    if (j < 0 || j >= all.length) { return; }
    if (delta < 0) { list.insertBefore(li, all[j]); } else { list.insertBefore(li, all[j].nextSibling); }
    refresh();
  }

  list.addEventListener('click', function (e) {
    var up = e.target.closest('[data-lpl-up]');
    var down = e.target.closest('[data-lpl-down]');
    if (!up && !down) { return; }
    e.preventDefault();
    var li = e.target.closest('.lpl-item');
    move(li, up ? -1 : 1);
    var btn = li.querySelector(up ? '[data-lpl-up]' : '[data-lpl-down]');
    (btn.disabled ? li.querySelector(up ? '[data-lpl-down]' : '[data-lpl-up]') : btn).focus();
  });

  list.addEventListener('dragstart', function (e) {
    dragged = e.target.closest('.lpl-item');
    if (!dragged) { return; }
    dragged.classList.add('is-dragging');
    try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', dragged.getAttribute('data-key')); } catch (err) { /* ancien navigateur */ }
  });
  list.addEventListener('dragover', function (e) {
    if (!dragged) { return; }
    e.preventDefault();
    var over = e.target.closest('.lpl-item');
    if (!over || over === dragged) { return; }
    var box = over.getBoundingClientRect();
    list.insertBefore(dragged, e.clientY > box.top + box.height / 2 ? over.nextSibling : over);
  });
  list.addEventListener('drop', function (e) { e.preventDefault(); });
  list.addEventListener('dragend', function () {
    if (dragged) { dragged.classList.remove('is-dragging'); }
    dragged = null;
    refresh();
  });

  form.addEventListener('change', function (e) {
    if (e.target.matches('[data-lpl-size], [data-lpl-columns], [data-lpl-width]')) { refresh(); }
  });

  refresh();
})();
