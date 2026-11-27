/* LogiProjet by LogiProf — application (enseignant et apprentis). Sans dépendance. */
(function () {
  'use strict';
  var CFG = window.LOGIPROJET || {};
  var root = document.querySelector('[data-lpj-app]');
  if (!root || !CFG.root) { return; }

  var ORAL = [['clarte', 'Clarté de la présentation'], ['questions', 'Réponses aux questions'], ['temps', 'Temps respecté (10 min ± 1)']];
  var ST_T = { brouillon: 'Brouillon', ouvert: 'Ouvert aux dépôts', ferme: 'Fermé' };
  var ST_D = { groupe: 'En cours', depose: 'Remis', propose: 'À valider', valide: 'Validé', erreur: 'Erreur IA' };
  var S = { me: null, travail: null, depot: null, form: null, courses: null, groups: null, lessons: [], dirty: false, busy: false };

  /* ---------- Outils ---------- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function api(path, opt) {
    opt = opt || {};
    var init = { method: opt.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': CFG.nonce } };
    if (opt.form) { init.body = opt.form; }
    else if (opt.body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opt.body); }
    return fetch(CFG.root + path, init).then(function (r) {
      return r.text().then(function (t) {
        var data = null;
        try { data = t ? JSON.parse(t) : null; } catch (e) { data = null; }
        if (!r.ok) {
          var msg = data && data.message ? data.message : 'Erreur du serveur (' + r.status + ').';
          if (r.status === 403 && data && data.code === 'rest_cookie_invalid_nonce') { msg = 'Votre session a expiré. Rechargez la page.'; }
          throw new Error(msg);
        }
        return data;
      });
    });
  }
  var toastTimer;
  function toast(msg, err) {
    var el = document.querySelector('.lpj-toast');
    if (!el) { el = document.createElement('div'); el.className = 'lpj-toast'; el.setAttribute('role', 'status'); document.body.appendChild(el); }
    el.className = 'lpj-toast' + (err ? ' is-err' : '');
    el.textContent = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, err ? 7000 : 3500);
  }
  function ask(title, text, ok, danger) {
    return new Promise(function (resolve) {
      var bg = document.createElement('div');
      bg.className = 'lpj-modal-bg';
      bg.innerHTML = '<div class="lpj-modal" role="dialog" aria-modal="true"><h3>' + esc(title) + '</h3><p>' + esc(text) + '</p><div class="lpj-modal-actions"><button class="lpj-btn" data-r="0">Annuler</button><button class="lpj-btn ' + (danger ? 'lpj-btn-danger' : 'lpj-btn-yellow') + '" data-r="1">' + esc(ok) + '</button></div></div>';
      document.body.appendChild(bg);
      bg.querySelector('[data-r="1"]').focus();
      bg.addEventListener('click', function (e) {
        var b = e.target.closest('[data-r]');
        if (!b && e.target !== bg) { return; }
        document.body.removeChild(bg);
        resolve(!!(b && b.getAttribute('data-r') === '1'));
      });
    });
  }
  function fmtDate(v, withTime) {
    if (!v) { return ''; }
    var m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/);
    if (!m) { return v; }
    return m[3] + '.' + m[2] + '.' + m[1] + (withTime !== false && m[4] ? ' à ' + m[4] + ':' + m[5] : '');
  }
  function fmtN(n) { return n === null || n === undefined || n === '' ? '–' : String(Math.round(n * 10) / 10); }
  function size(b) { return b > 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.round(b / 1024)) + ' Ko'; }
  function roundHalf(x) { return Math.floor(x * 2 + 0.5) / 2; }
  function noteOf(points, total) { return total > 0 ? Math.max(1, Math.min(6, roundHalf(points / total * 5 + 1))) : null; }
  function go(hash) { if (location.hash === hash) { route(); } else { location.hash = hash; } }
  function blocs(grille) {
    var out = [];
    (grille || []).forEach(function (c, i) {
      var last = out[out.length - 1];
      if (!last || last.name !== c.bloc) { out.push({ name: c.bloc, items: [] }); last = out[out.length - 1]; }
      last.items.push({ c: c, i: i });
    });
    return out;
  }
  /* 1.53.18 : statut par classe. Badge du travail : statut commun, ou détail par classe s'il diffère. */
  function mixedStatus(t) {
    var cl = t.classes || [];
    if (cl.length < 2) { return false; }
    return cl.some(function (c) { return c.status !== cl[0].status; });
  }
  function travailBadge(t) {
    if (!mixedStatus(t)) { return statusBadge((t.classes && t.classes.length) ? t.classes[0].status : t.status, ST_T); }
    return t.classes.map(function (c) { return '<span class="lpj-badge st-' + esc(c.status) + '" style="max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(c.title + ' : ' + ST_T[c.status]) + '">' + esc(c.title) + ' · ' + esc(ST_T[c.status] === 'Ouvert aux dépôts' ? 'Ouvert' : ST_T[c.status]) + '</span>'; }).join('');
  }
  function anyOpen(t) {
    return (t.classes && t.classes.length) ? t.classes.some(function (c) { return c.status !== 'brouillon'; }) : t.status !== 'brouillon';
  }
  function classStatusPanel(t) {
    var cl = t.classes || [];
    if (cl.length < 2) { return ''; }
    return '<section class="lpj-card"><h3>Statut et dates par classe</h3><p class="lpj-muted" style="margin:0 0 10px">Ouvrez, verrouillez ou décalez ce travail pour une classe sans toucher aux autres : statut, date de début et date de fin (les pénalités de retard suivent la date de fin de la classe). Une classe en brouillon ne voit pas le travail ; avant sa date de début, une classe ouverte le voit mais ne peut pas l’ouvrir ; une classe fermée le voit mais ne peut plus déposer.</p>' +
      '<div class="lpj-cls-grid">' + cl.map(function (c) {
        var own = t.class_status && t.class_status[c.id] ? t.class_status[c.id] : '';
        var ownStart = t.class_start && t.class_start[c.id] ? t.class_start[c.id] : '';
        return '<div class="lpj-cls"><b>' + esc(c.title) + '</b>' +
          '<label class="lpj-field" style="margin:0"><span>Statut</span><select data-class-status="' + c.id + '">' +
          '<option value=""' + (own ? '' : ' selected') + '>Comme le travail (' + esc(ST_T[t.status]) + ')</option>' +
          Object.keys(ST_T).map(function (k) { return '<option value="' + k + '"' + (own === k ? ' selected' : '') + '>' + ST_T[k] + '</option>'; }).join('') +
          '</select></label>' +
          '<label class="lpj-field" style="margin:0"><span>Début</span><input type="datetime-local" data-class-start="' + c.id + '" value="' + esc(ownStart) + '"></label>' +
          '<label class="lpj-field" style="margin:0"><span>Fin · rendu du fichier</span><input type="datetime-local" data-class-due="' + c.id + '" value="' + esc(t.class_due && t.class_due[c.id] ? t.class_due[c.id] : '') + '"></label>' +
          (t.class_due && t.class_due[c.id] ? '' : '<small class="lpj-muted">Fin vide : ' + (t.due_at ? 'comme le travail (' + esc(fmtDate(t.due_at)) + ')' : 'sans date limite') + '</small>') +
          '<small class="lpj-muted">' + (ownStart ? '' : (t.date_debut ? 'Vide : comme le travail (' + esc(fmtDate(t.date_debut)) + ')' : 'Vide : accès dès l’ouverture')) +
          (c.locked && c.status === 'ouvert' ? (ownStart ? '' : ' · ') + '🔒 visible, s’ouvre le ' + esc(fmtDate(c.start)) : '') + '</small></div>';
      }).join('') + '</div></section>';
  }
  function lockBanner(t) {
    var cl = (t.classes && t.classes.length) ? t.classes : null;
    var locked = cl ? cl.filter(function (c) { return c.locked && c.status === 'ouvert'; }) : (t.locked_now && t.status === 'ouvert' ? [{ title: '', start: t.date_debut }] : []);
    if (!locked.length) { return ''; }
    var txt = cl && cl.length > 1 ? locked.map(function (c) { return '<b>' + esc(c.title) + '</b> le ' + esc(fmtDate(c.start)); }).join(', ') : 'le <b>' + esc(fmtDate(locked[0].start)) + '</b>';
    return '<div class="lpj-alert is-b">🔒 Les apprentis voient déjà ce travail, mais ne peuvent pas l’ouvrir avant sa date de début. Ouverture : ' + txt + '.</div>';
  }
  function statusBadge(st, map) { return '<span class="lpj-badge st-' + esc(st) + '">' + esc((map || ST_D)[st] || st) + '</span>'; }

  /* ---------- Gabarit ---------- */
  var CUBES = '<svg class="lpj-cubes" viewBox="0 0 118 86" aria-hidden="true"><g stroke="#252c35" stroke-width="1.5">' +
    '<rect x="42" y="2" width="26" height="26" rx="3" fill="#f5c400"/><rect x="28" y="30" width="26" height="26" rx="3" fill="#2fae5f"/>' +
    '<rect x="56" y="30" width="26" height="26" rx="3" fill="#067030"/><rect x="14" y="58" width="26" height="26" rx="3" fill="#dcefe3"/>' +
    '<rect x="42" y="58" width="26" height="26" rx="3" fill="#2fae5f"/><rect x="70" y="58" width="26" height="26" rx="3" fill="#067030"/></g></svg>';
  function page(kicker, title, sub, body, stamp) {
    return '<header class="lpj-band"><img class="lpj-band-logo" src="' + esc(CFG.logo) + '" alt="LogiProjet by LogiProf">' +
      '<div class="lpj-band-text"><div class="lpj-band-kicker">' + esc(kicker) + '</div><h1>' + esc(title) + '</h1>' + (sub ? '<div class="lpj-band-sub">' + esc(sub) + '</div>' : '') + helpHtml() + '</div>' + (stamp || CUBES) + '</header>' +
      '<div class="lpj-main">' + body + '</div>';
  }
  /* ---------- Aide : tutoriels vidéo et manuels (1.54.0) ---------- */
  function helpLinks(who) {
    var h = S.me && S.me.help && S.me.help[who];
    if (!h) { return ''; }
    return (h.video ? '<button type="button" class="lpj-help-btn" data-help-video="' + who + '">▶ Tutoriel vidéo</button>' : '') +
      (h.manuel ? '<a class="lpj-help-btn" href="' + esc(h.manuel) + '" target="_blank" rel="noopener">📘 Manuel</a>' : '');
  }
  function helpHtml() {
    if (!S.me || !S.me.help) { return ''; }
    if (!S.me.is_teacher) { var l = helpLinks('eleve'); return l ? '<div class="lpj-help">' + l + '</div>' : ''; }
    return '<div class="lpj-help"><details class="lpj-help-menu"><summary class="lpj-help-btn">❔ Aide</summary><div class="lpj-help-pop">' +
      '<b>Pour vous</b><div class="lpj-help-row">' + helpLinks('enseignant') + '</div>' +
      '<b>Pour vos apprentis</b><div class="lpj-help-row">' + helpLinks('eleve') + '</div>' +
      '<small>À projeter en classe ou à transmettre aux apprentis : ils ont aussi ces boutons dans leur LogiProjet.</small></div></details></div>';
  }
  function openVideo(who) {
    var v = S.me.help[who] && S.me.help[who].video;
    if (!v) { return; }
    var d = document.createElement('div');
    d.className = 'lpj-modal-bg lpj-video-bg';
    d.innerHTML = '<div class="lpj-video" role="dialog" aria-modal="true" aria-label="Tutoriel vidéo"><div class="lpj-video-frame">' +
      (v.type === 'youtube' ? '<iframe src="' + esc(v.src) + '" title="Tutoriel vidéo" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>' : '<video src="' + esc(v.src) + '" controls autoplay playsinline></video>') +
      '</div><div class="lpj-video-bar"><a class="lpj-btn lpj-btn-sm" href="' + esc(v.watch) + '" target="_blank" rel="noopener">' + (v.type === 'youtube' ? 'Ouvrir sur YouTube ↗' : 'Ouvrir la vidéo ↗') + '</a><button type="button" class="lpj-btn lpj-btn-sm lpj-btn-yellow" data-close>Fermer ✕</button></div></div>';
    document.body.appendChild(d);
    var close = function () { if (d.parentNode) { d.parentNode.removeChild(d); } document.removeEventListener('keydown', esc_); };
    var esc_ = function (e) { if (e.key === 'Escape') { close(); } };
    document.addEventListener('keydown', esc_);
    d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) { close(); } });
    d.querySelector('[data-close]').focus();
  }
  function draw(html) { root.innerHTML = html; }
  function loading() { draw(page('LogiProjet', 'Chargement…', '', '<div class="lpj-boot"><span class="lpj-spinner"></span></div>')); }
  function fail(e) { draw(page('LogiProjet', 'Oups', '', '<div class="lpj-alert is-r">' + esc(e.message || e) + '</div><button class="lpj-btn" data-go="#/">Retour</button>')); }

  /* =====================================================================
     ENSEIGNANT
     ===================================================================== */
  function teacherHome() {
    loading();
    if (S.allT === undefined) { try { S.allT = S.me.is_admin && localStorage.getItem('lpj-tous') === '1'; } catch (e) { S.allT = false; } }
    api('travaux' + (S.allT ? '?tous=1' : '')).then(function (list) {
      var warn = S.me.ai_ready ? '' : '<div class="lpj-alert is-y"><b>Aucune clé API d’IA n’est configurée.</b> Les apprentis peuvent déposer, mais la correction par IA ne fonctionnera pas. ' + (S.me.settings ? '<a href="' + esc(S.me.settings) + '">Ouvrir les réglages</a>' : 'Demandez à l’administrateur du site.') + '</div>';
      var tiles = list.map(function (t) {
        var dues = datesOf(t, 'due').filter(Boolean).sort();
        var allPast = dues.length && new Date(dues[dues.length - 1]) < new Date();
        var cls = (t.classes && t.classes.length ? t.classes.some(function (c) { return c.status === 'ouvert'; }) : t.status === 'ouvert') ? (allPast ? 'is-b' : (dues.some(soon) ? 'is-y' : '')) : 'is-g';
        var c = t.counts;
        return '<article class="lpj-tile lpj-tile-click ' + cls + '" data-go="#/travail/' + t.id + '" tabindex="0">' +
          '<div class="lpj-meta">' + (t.module ? '<b>' + esc(t.module) + '</b>' : '') + travailBadge(t) + (t.graded === false ? '<span class="lpj-badge">Non noté</span>' : '') + '</div>' +
          '<h3>' + esc(t.title) + '</h3>' +
          (t.mine === false ? '<div class="lpj-meta"><span class="lpj-badge lpj-badge-author">Créé par ' + esc(t.author) + '</span></div>' : '') +
          tileClasses(t) +
          '<div class="lpj-meta">' + (t.classes && t.classes.length ? '' : '<span>' + esc(rangeTxt(datesOf(t, 'due'), 'À rendre le', 'Sans date limite')) + '</span>') + '<span>' + t.grille.length + ' critères · ' + t.total + ' points</span></div>' +
          '<div class="lpj-stats"><span class="lpj-badge st-depose">' + c.total + ' dépôt' + (c.total > 1 ? 's' : '') + '</span>' +
          (c.groupe ? '<span class="lpj-badge st-groupe">' + c.groupe + ' en cours, pas remis</span>' : '') +
          (c.propose ? '<span class="lpj-badge st-propose">' + c.propose + ' à valider</span>' : '') +
          (c.valide ? '<span class="lpj-badge st-valide">' + c.valide + ' validé' + (c.valide > 1 ? 's' : '') + '</span>' : '') +
          (c.erreur ? '<span class="lpj-badge st-erreur">' + c.erreur + ' erreur' + (c.erreur > 1 ? 's' : '') + '</span>' : '') + '</div></article>';
      }).join('');
      var adminSwitch = S.me.is_admin ? '<label class="lpj-check lpj-admin-switch" title="Réservé aux administrateurs du site"><input type="checkbox" data-all-travaux' + (S.allT ? ' checked' : '') + '> Afficher aussi les travaux des autres enseignants</label>' : '';
      var body = warn + '<div class="lpj-bar"><h2>' + (S.allT ? 'Tous les travaux' : 'Vos activités') + '</h2>' + adminSwitch + (S.me.skill_url ? '<a class="lpj-btn" href="' + esc(S.me.skill_url) + '" title="Pour créer de nouvelles activités avec Claude, puis les importer ici">Compétence Claude (.zip)</a>' : '') + '<button class="lpj-btn" data-go="#/modeles" title="Vos modèles de travaux, à réutiliser pour d’autres classes">📚 Bibliothèque de modèles</button><label class="lpj-btn" title="Fichier .json exporté de LogiProjet ou préparé avec Claude">Importer un travail<input type="file" accept=".json,application/json" class="lpj-sr" data-import></label><button class="lpj-btn lpj-btn-yellow" data-go="#/nouveau">+ Nouveau travail</button></div>' +
        (list.length ? '<div class="lpj-grid">' + tiles + '</div>' : '<div class="lpj-empty"><b>Aucun travail pour l’instant</b>Vous ne voyez que les travaux que vous avez créés. Créez votre premier travail : choisissez le cours Sensei, et la grille se remplit avec ses leçons.<br><br><button class="lpj-btn lpj-btn-yellow" data-go="#/nouveau">+ Nouveau travail</button> <button class="lpj-btn" data-go="#/modeles">📚 Partir d’un modèle</button></div>');
      draw(page('LogiProjet · Espace enseignant', 'Bonjour ' + S.me.first_name, 'Activités notées ou formatives, seul ou en groupe, corrigées avec l’aide de l’IA', body));
    }).catch(fail);
  }
  /* 1.54.3 : dates par classe. Liste des dates (début ou fin) des classes visées, sinon celle du travail. */
  function datesOf(t, key) {
    var cl = t.classes || [];
    if (cl.length) { return cl.map(function (c) { return c[key === 'due' ? 'due' : 'start'] || ''; }); }
    return [key === 'due' ? (t.due_at || '') : (t.date_debut || '')];
  }
  function rangeTxt(list, one, none) {
    var v = list.filter(Boolean).sort();
    if (!v.length) { return none; }
    if (v[0] === v[v.length - 1] && v.length === list.length) { return one + ' ' + fmtDate(v[0]); }
    return one + ' ' + fmtDate(v[0]) + ' → ' + fmtDate(v[v.length - 1]) + ' (selon la classe)';
  }
  /* Classes affectées au travail, avec la date de remise de chacune (carte de l'accueil enseignant). */
  function tileClasses(t) {
    var cl = t.classes || [];
    if (!cl.length) { return '<div class="lpj-tile-cls"><div><b>Toutes les classes</b><span>' + esc(t.due_at ? 'à rendre le ' + fmtDate(t.due_at) : 'sans date limite') + '</span></div></div>'; }
    var now = new Date();
    return '<div class="lpj-tile-cls">' + cl.map(function (c) {
      var late = c.due && new Date(c.due) < now;
      var info = c.status === 'brouillon' ? 'brouillon' : (c.locked ? '🔒 s’ouvre le ' + fmtDate(c.start) + (c.due ? ' · à rendre le ' + fmtDate(c.due) : '') : (c.due ? 'à rendre le ' + fmtDate(c.due) : 'sans date limite'));
      return '<div class="' + (late && c.status !== 'brouillon' && !c.locked ? 'is-late' : '') + '"><b>' + esc(c.title) + '</b><span>' + esc(info) + '</span></div>';
    }).join('') + '</div>';
  }
  function soon(due) {
    if (!due) { return false; }
    var d = new Date(due.replace(' ', 'T'));
    var diff = d - new Date();
    return diff > 0 && diff < 3 * 864e5;
  }

  /* ---------- Créer / modifier un travail ---------- */
  function editTravail(id) {
    loading();
    var p = [api('courses'), api('groups')];
    if (id) { p.push(api('travaux/' + id)); }
    Promise.all(p).then(function (r) {
      S.courses = r[0]; S.groups = r[1];
      var t = r[2];
      S.form = t ? JSON.parse(JSON.stringify(t)) : { id: 0, title: '', module: '', course_id: 0, lessons: [], grille: [], consignes: '', consigne: null, date_debut: '', group_size: 2, graded: true, formats: ['pptx'], consigne_url: '', group_ids: [], due_at: '', oral_bonus: true, allow_solo: true, status: 'brouillon', class_start: {}, class_due: {} };
      ['class_start', 'class_due'].forEach(function (k) { S.form[k] = S.form[k] && !Array.isArray(S.form[k]) ? Object.assign({}, S.form[k]) : {}; });
      // Dates par classe : chaque classe reprend sa date effective (la date générale d'un ancien travail est reportée sur ses classes).
      (S.form.classes || []).forEach(function (c) {
        if (!S.form.class_start[c.id] && c.start) { S.form.class_start[c.id] = c.start; }
        if (!S.form.class_due[c.id] && c.due) { S.form.class_due[c.id] = c.due; }
      });
      S.lessons = [];
      S.dirty = false;
      if (S.form.course_id) {
        return api('courses/' + S.form.course_id + '/lessons').then(function (ls) { S.lessons = ls; });
      }
    }).then(drawForm).catch(fail);
  }
  function drawForm() {
    var f = S.form;
    var picked = {};
    (f.lessons || []).forEach(function (l) { picked[l.id] = true; });
    var lessonsHtml = !f.course_id ? '<p class="lpj-muted">Choisissez d’abord un cours.</p>' :
      (S.lessons.length ? S.lessons.map(function (l) {
        return '<label class="lpj-check"><input type="checkbox" data-lesson="' + l.id + '"' + (picked[l.id] ? ' checked' : '') + '> <span>' + esc(l.title) + '</span></label>';
      }).join('') : '<p class="lpj-muted">Ce cours n’a aucune leçon.</p>');

    var total = 2 * f.grille.length;
    var gHtml = blocs(f.grille).map(function (b, bi) {
      return '<div class="lpj-gbloc"><div class="lpj-gbloc-head"><input type="text" value="' + esc(b.name) + '" data-blocname="' + esc(b.name) + '" aria-label="Nom du bloc"><span class="lpj-gbloc-pts">' + (2 * b.items.length) + ' pts</span>' +
        '<button class="lpj-btn lpj-btn-sm lpj-btn-ghost" data-act="add-crit" data-bloc="' + esc(b.name) + '" title="Ajouter un critère">+ Critère</button></div>' +
        b.items.map(function (it) {
          return '<div class="lpj-gcrit"><input type="text" value="' + esc(it.c.label) + '" data-gi="' + it.i + '" aria-label="Critère"><button class="lpj-btn lpj-btn-sm lpj-btn-ghost" data-act="del-crit" data-gi="' + it.i + '" title="Supprimer ce critère" aria-label="Supprimer ce critère">✕</button></div>';
        }).join('') + '</div>';
    }).join('');

    var conv = total ? conversionText(total) : '';
    var groups = S.groups.length ? '<div class="lpj-pills">' + S.groups.map(function (g) {
      return '<label><input type="checkbox" data-group="' + g.id + '"' + (f.group_ids.indexOf(g.id) > -1 ? ' checked' : '') + '> ' + esc(g.title) + '</label>';
    }).join('') + '</div><small>Aucune classe cochée = tous les apprentis connectés voient le travail.</small>' : '<p class="lpj-muted">Aucun groupe Sensei trouvé : tous les apprentis connectés verront le travail.</p>';

    var body = '<div class="lpj-crumb"><a href="#/" data-go="#/">Vos travaux</a>' + (f.id ? ' / <a href="#/travail/' + f.id + '" data-go="#/travail/' + f.id + '">' + esc(f.title) + '</a>' : '') + '</div>' +
      '<div class="lpj-bar"><h2>' + (f.id ? 'Modifier le travail' : 'Nouveau travail') + '</h2></div>' +
      (S.importWarnings && S.importWarnings.length ? '<div class="lpj-alert is-y"><b>' + (S.fromModele ? 'Créé à partir du modèle : à vérifier' : 'Travail importé : à vérifier') + '</b><ul style="margin:6px 0 0 18px;padding:0">' + S.importWarnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul></div>' :
        (S.importWarnings ? '<div class="lpj-alert is-g"><b>' + (S.fromModele ? 'Travail créé à partir du modèle « ' + esc(S.fromModele) + ' ».' : 'Travail importé.') + '</b> Le cours, les leçons, la grille et la consigne ont été repris. Choisissez les classes, la date de début et la date limite' + (S.fromModele && f.consigne_url ? ', vérifiez le lien vers la consigne PDF' : '') + ', puis passez le statut à « Ouvert aux dépôts ».</div>' : '')) +
      '<div class="lpj-cols"><div>' +
      '<section class="lpj-card"><h3><span class="lpj-num">1</span>Le cours et ses leçons</h3>' +
      '<label class="lpj-field"><span>Cours Sensei</span><select data-f="course_id"><option value="0">Choisir un cours…</option>' + S.courses.map(function (c) {
        return '<option value="' + c.id + '"' + (c.id === f.course_id ? ' selected' : '') + '>' + esc(c.title) + '</option>';
      }).join('') + '</select></label>' +
      '<div class="lpj-field"><span>Leçons à présenter</span>' + lessonsHtml + '</div>' +
      (f.course_id ? '<button class="lpj-btn lpj-btn-dark" data-act="gen-grille">Créer la grille à partir de ces leçons</button>' : '') + '</section>' +
      '<section class="lpj-card"><h3><span class="lpj-num">2</span>Le travail</h3>' +
      '<label class="lpj-field"><span>Titre</span><input type="text" data-f="title" value="' + esc(f.title) + '" placeholder="Présenter le contenu du module M109"></label>' +
      '<div class="lpj-row"><label class="lpj-field"><span>Module</span><input type="text" data-f="module" value="' + esc(f.module) + '" placeholder="M109"></label>' +
      '</div>' + (f.group_ids.length ? classStartRows(f) :
      '<div class="lpj-row"><label class="lpj-field"><span>Date de début</span><input type="datetime-local" data-f="date_debut" value="' + esc(f.date_debut || '') + '"></label>' +
      '<label class="lpj-field"><span>Date de fin · rendu du fichier</span><input type="datetime-local" data-f="due_at" value="' + esc(f.due_at || '') + '"></label></div>' +
      '<small class="lpj-muted" style="display:block;margin:-6px 0 12px">Cochez les classes concernées (section 3) pour fixer les dates classe par classe.</small>') +
      '<label class="lpj-field"><span>Statut</span><select data-f="status">' + Object.keys(ST_T).map(function (k) { return '<option value="' + k + '"' + (k === f.status ? ' selected' : '') + '>' + ST_T[k] + '</option>'; }).join('') + '</select>' +
      '<small>Brouillon : invisible pour les apprentis. Ouvert : ils voient le travail et, à partir de la date de début, peuvent l’ouvrir et déposer (avant, il apparaît verrouillé). Fermé : ils voient le travail, mais ne peuvent plus déposer.</small></label>' +
      '<label class="lpj-field"><span>Lien vers la consigne PDF (facultatif)</span><input type="url" data-f="consigne_url" value="' + esc(f.consigne_url) + '" placeholder="https://… (médiathèque)"></label>' +
      '</section>' +
      '<section class="lpj-card"><h3><span class="lpj-num">3</span>Classes, groupes et formats</h3><div class="lpj-field"><span>Classes concernées</span>' + groups + '</div>' +
      '<label class="lpj-field"><span>Nombre de participants par groupe</span><select data-f="group_size">' + [1, 2, 3, 4, 5, 6].map(function (n) { return '<option value="' + n + '"' + (n === (f.group_size || 2) ? ' selected' : '') + '>' + n + ' · ' + groupWord(n) + '</option>'; }).join('') + '</select>' +
      '<small>Au dépôt, chaque apprenti choisit ses camarades dans sa classe. Après un changement, « Remettre le modèle » adapte les textes de la consigne (duo, trio…).</small></label>' +
      '<div class="lpj-field"><span>Formats acceptés pour la remise</span><div class="lpj-pills">' + FORMATS.map(function (x) {
        return '<label><input type="checkbox" data-format="' + x[0] + '"' + ((f.formats || ['pptx']).indexOf(x[0]) > -1 ? ' checked' : '') + '> ' + esc(x[1]) + '</label>';
      }).join('') + '</div><small>La correction par l’IA ne lit que les PowerPoint (.pptx) ; les autres formats se notent à la main.</small></div>' +
      '<label class="lpj-check"><input type="checkbox" data-f="graded"' + (f.graded !== false ? ' checked' : '') + '> <span><b>Activité notée</b> (note sur 6). Décochée : activité formative, sans note, avec un retour de l’enseignant.</span></label>' +
      '<label class="lpj-check"><input type="checkbox" data-f="oral_bonus"' + (f.oral_bonus ? ' checked' : '') + '> <span>Bonus oral de +0.5 pour les groupes tirés au sort (4 / 6 ou plus)</span></label>' +
      '<label class="lpj-check"><input type="checkbox" data-f="allow_solo"' + (f.allow_solo ? ' checked' : '') + '> <span>Les apprentis peuvent aussi travailler seuls ou en groupe incomplet</span></label></section>' +
      '</div><div>' +
      '<section class="lpj-card"><h3><span class="lpj-num">4</span>Grille d’évaluation</h3>' +
      '<p class="lpj-muted">Chaque critère est noté 0, 1 ou 2. Modifiez les textes directement, ajoutez ou supprimez des critères.</p>' +
      (f.grille.length ? gHtml : '<div class="lpj-empty"><b>Grille vide</b>Choisissez un cours, cochez les leçons, puis « Créer la grille ».</div>') +
      '<div style="display:flex;gap:8px;margin:4px 0 12px"><button class="lpj-btn lpj-btn-sm" data-act="add-bloc">+ Bloc</button></div>' +
      '<div class="lpj-total-line"><span>' + f.grille.length + ' critères</span><span>Total : ' + total + ' points</span></div>' +
      (conv ? '<p class="lpj-muted" style="margin-top:8px">' + conv + '</p>' : '') + '</section>' +
      '</div></div>' + consigneEditor(f) +
      '<div class="lpj-sum"><span>' + (S.dirty ? 'Modifications non enregistrées' : 'Tout est enregistré') + '</span>' +
      (f.id ? '<button class="lpj-btn lpj-btn-danger" data-act="del-travail" style="margin-left:auto">Supprimer</button>' : '') +
      '<button class="lpj-btn" data-go="' + (f.id ? '#/travail/' + f.id : '#/') + '"' + (f.id ? '' : ' style="margin-left:auto"') + '>Annuler</button>' +
      '<button class="lpj-btn lpj-btn-yellow" data-act="save-travail" style="margin-left:0">Enregistrer</button></div>';
    draw(page('LogiProjet · Espace enseignant', f.id ? f.title : 'Nouveau travail', f.module ? 'Module ' + f.module : 'Présentation PowerPoint en duo', body));
  }
  function classStartRows(f) {
    var sel = (S.groups || []).filter(function (g) { return f.group_ids.indexOf(g.id) > -1; });
    if (!sel.length) { return ''; }
    var cs = f.class_start || {}, cd = f.class_due || {};
    return '<div class="lpj-field"><span>Dates de chaque classe</span><div class="lpj-cls-grid">' + sel.map(function (g) {
      return '<div class="lpj-cls"><b>' + esc(g.title) + '</b>' +
        '<label class="lpj-cls-date"><small>Début</small><input type="datetime-local" data-class-start="' + g.id + '" value="' + esc(cs[g.id] || '') + '"></label>' +
        '<label class="lpj-cls-date"><small>Fin · rendu du fichier</small><input type="datetime-local" data-class-due="' + g.id + '" value="' + esc(cd[g.id] || '') + '"></label></div>';
    }).join('') + '</div><small>Début vide : accès dès l’ouverture. Fin vide : pas de date limite. Les pénalités de retard se calculent avec la date de fin de chaque classe.</small></div>';
  }
  var FORMATS = [['pptx', 'PowerPoint (.pptx)'], ['pdf', 'PDF (.pdf)'], ['docx', 'Word (.docx)'], ['xlsx', 'Excel (.xlsx)'], ['odp', 'Impress (.odp)'], ['key', 'Keynote (.key)'], ['img', 'Image (.jpg, .png)'], ['mp4', 'Vidéo (.mp4)'], ['zip', 'Archive (.zip)']];
  var CF = [
    ['note', 'Encadré jaune « note du groupe »', 'Un seul paragraphe.'],
    ['mission', '1 · Votre mission', 'Un paragraphe par ligne.'],
    ['actions', '2 · Les actions pour chaque leçon', 'Une action par ligne : « Titre | texte ».'],
    ['plan', '3 · Le plan de la présentation', 'Une ligne par slide : « Slide 1 | texte ».'],
    ['faire', '4 · Règles : à faire', 'Une règle par ligne.'],
    ['eviter', '4 · Règles : à éviter', 'Une règle par ligne.'],
    ['etapes', '5 · Les étapes de travail en classe', 'Une ligne par période : le nombre de lignes donne le nombre de périodes.'],
    ['rendu', '6 · Le rendu', 'Un point par ligne.'],
    ['jourj', '7 · Le jour de la présentation', 'Un point par ligne.'],
    ['checklist', '8 · Check-list avant de rendre', 'Une case par ligne.']
  ];
  function consigneEditor(f) {
    var c = f.consigne;
    var fields = c ? CF.map(function (x) {
      var val = x[0] === 'note' ? (c.note || '') : (c[x[0]] || []).join('\n');
      var rows = Math.min(9, Math.max(3, (x[0] === 'note' ? 3 : (c[x[0]] || []).length + 1)));
      return '<label class="lpj-field"><span>' + esc(x[1]) + '</span><textarea data-cf="' + x[0] + '" rows="' + rows + '">' + esc(val) + '</textarea><small>' + esc(x[2]) + '</small></label>';
    }).join('') : '';
    var T = titres(f);
    var tit = c ? '<details style="margin-bottom:12px"><summary class="lpj-btn lpj-btn-sm" style="display:inline-flex">Titres des sections et phrases d’introduction</summary><div class="lpj-cf-grid" style="margin-top:12px">' +
      [['mission', 'Section « mission »'], ['contenu', 'Section des leçons et actions'], ['plan', 'Section « plan »'], ['page2', 'Titre de la 2e page du Word'], ['regles', 'Section « règles »'], ['etapes', 'Section « étapes »'], ['rendu', 'Encadré « rendu »'], ['jourj', 'Encadré « jour J »'], ['checklist', 'Section « check-list »'], ['grille', 'Titre de la grille']].map(function (x) {
        return '<label class="lpj-field"><span>' + esc(x[1]) + '</span><input type="text" data-ct="' + x[0] + '" value="' + esc(T[x[0]]) + '"></label>';
      }).join('') +
      '<label class="lpj-field"><span>Phrase avant les leçons (vide = pas de tuiles de leçons)</span><input type="text" data-ci="intro_lecons" value="' + esc(c.intro_lecons !== undefined ? c.intro_lecons : 'Votre présentation couvre **les {n} leçons du module {module}** sur logiprof :') + '"></label>' +
      '<label class="lpj-field"><span>Phrase avant les actions ({k} = nombre d’actions)</span><input type="text" data-ci="intro_actions" value="' + esc(c.intro_actions !== undefined ? c.intro_actions : 'Pour **chaque leçon**, votre présentation doit faire ces {k} choses :') + '"></label>' +
      '</div></details>' : '';
    return '<section class="lpj-card" style="margin-top:16px"><h3><span class="lpj-num">5</span>La consigne, section par section (comme le document Word)</h3>' + tit +
      '<p class="lpj-muted">Les apprentis voient exactement ces textes, avec la mise en page du document Word (bouton « Aperçu apprenti » sur la page du travail). <b>**mot**</b> = gras. Remplacés automatiquement : {module}, {total}, {n} (nombre de leçons), {lecons} (ex. 9.1 à 9.5), {date_debut}, {date_fin}.</p>' +
      (c ? '<div class="lpj-cf-grid">' + fields + '</div>' : '<div class="lpj-empty"><b>Pas encore de consigne</b>Remplissez-la avec le modèle (identique au document Word), puis adaptez-la.</div>') +
      '<div style="display:flex;gap:8px;margin-top:12px"><button class="lpj-btn' + (c ? '' : ' lpj-btn-dark') + '" data-act="gen-consigne">' + (c ? 'Remettre le modèle' : 'Remplir avec le modèle') + '</button></div></section>';
  }
  function conversionText(total) {
    var p4 = 0;
    while (p4 <= total && noteOf(p4, total) < 4) { p4++; }
    return 'Note = points ÷ ' + total + ' × 5 + 1, arrondie au demi-point. Le 4 est atteint dès ' + p4 + ' points.';
  }
  function formInput(el) {
    var f = S.form;
    if (el.hasAttribute('data-f')) {
      var k = el.getAttribute('data-f');
      if (el.type === 'checkbox') { f[k] = el.checked; }
      else if (k === 'group_size') { f.group_size = parseInt(el.value, 10) || 2; }
      else if (k === 'course_id') { return changeCourse(parseInt(el.value, 10) || 0); }
      else { f[k] = el.value; }
    } else if (el.hasAttribute('data-cf')) {
      var key = el.getAttribute('data-cf');
      f.consigne = f.consigne || {};
      if (key === 'note') { f.consigne.note = el.value; } else { f.consigne[key] = el.value.split('\n').map(function (x) { return x.trim(); }).filter(Boolean); }
    } else if (el.hasAttribute('data-ct')) {
      f.consigne = f.consigne || {};
      f.consigne.titres = f.consigne.titres || {};
      f.consigne.titres[el.getAttribute('data-ct')] = el.value;
    } else if (el.hasAttribute('data-ci')) {
      f.consigne = f.consigne || {};
      f.consigne[el.getAttribute('data-ci')] = el.value;
    } else if (el.hasAttribute('data-format')) {
      f.formats = FORMATS.map(function (x) { return x[0]; }).filter(function (k) { var b = root.querySelector('[data-format="' + k + '"]'); return b && b.checked; });
    } else if (el.hasAttribute('data-gi')) {
      f.grille[parseInt(el.getAttribute('data-gi'), 10)].label = el.value;
    } else if (el.hasAttribute('data-blocname')) {
      var old = el.getAttribute('data-blocname');
      f.grille.forEach(function (c) { if (c.bloc === old) { c.bloc = el.value; } });
      el.setAttribute('data-blocname', el.value);
    } else if (el.hasAttribute('data-lesson')) {
      var lid = parseInt(el.getAttribute('data-lesson'), 10);
      var picked = S.lessons.filter(function (l) {
        var box = root.querySelector('[data-lesson="' + l.id + '"]');
        return box && box.checked;
      });
      f.lessons = picked.map(function (l) { return { id: l.id, title: l.title }; });
      void lid;
    } else if (el.hasAttribute('data-group')) {
      var g = parseInt(el.getAttribute('data-group'), 10);
      f.group_ids = f.group_ids.filter(function (x) { return x !== g; });
      if (el.checked) {
        var src = f.group_ids.filter(function (x) { return (f.class_start && f.class_start[x]) || (f.class_due && f.class_due[x]); })[0];
        f.class_start = f.class_start || {}; f.class_due = f.class_due || {};
        if (src) { f.class_start[g] = f.class_start[g] || f.class_start[src] || ''; f.class_due[g] = f.class_due[g] || f.class_due[src] || ''; }
        else if (!f.group_ids.length) { f.class_start[g] = f.class_start[g] || f.date_debut || ''; f.class_due[g] = f.class_due[g] || f.due_at || ''; }
        f.group_ids.push(g);
      }
      S.dirty = true; drawForm(); return;
    }
    S.dirty = true;
    var info = root.querySelector('.lpj-sum span');
    if (info) { info.textContent = 'Modifications non enregistrées'; }
  }
  function changeCourse(cid) {
    var f = S.form;
    f.course_id = cid;
    S.dirty = true;
    if (!cid) { S.lessons = []; f.lessons = []; drawForm(); return; }
    api('courses/' + cid + '/lessons').then(function (ls) {
      S.lessons = ls;
      f.lessons = ls.filter(function (l) { return l.suggested; }).map(function (l) { return { id: l.id, title: l.title }; });
      var c = S.courses.filter(function (x) { return x.id === cid; })[0];
      if (c && !f.module) { f.module = c.module; }
      if (c && !f.title) { f.title = 'Présenter le contenu du module ' + (c.module || c.title); }
      drawForm();
    }).catch(function (e) { toast(e.message, true); });
  }
  function genGrille() {
    var f = S.form;
    if (!f.lessons.length) { toast('Cochez au moins une leçon.', true); return; }
    var run = function () {
      api('grille-modele', { method: 'POST', body: { lessons: f.lessons, module: f.module } }).then(function (g) {
        f.grille = g; S.dirty = true;
        if (!f.consigne) { return api('consigne-modele', { method: 'POST', body: { lessons: f.lessons, group_size: f.group_size, formats: f.formats, graded: f.graded !== false } }).then(function (c) { f.consigne = c; drawForm(); toast('Grille et consigne créées : vérifiez-les.'); }); }
        drawForm(); toast('Grille créée : ' + g.length + ' critères.');
      }).catch(function (e) { toast(e.message, true); });
    };
    if (f.grille.length) {
      ask('Remplacer la grille ?', 'La grille actuelle sera remplacée par le modèle (une ligne par leçon, puis les blocs B à E).', 'Remplacer').then(function (ok) { if (ok) { run(); } });
    } else { run(); }
  }
  function genConsigne() {
    var f = S.form;
    var run = function () {
      api('consigne-modele', { method: 'POST', body: { lessons: f.lessons, group_size: f.group_size, formats: f.formats, graded: f.graded !== false } }).then(function (c) { f.consigne = c; S.dirty = true; drawForm(); toast('Consigne remplie avec le modèle.'); }).catch(function (e) { toast(e.message, true); });
    };
    if (f.consigne) { ask('Remettre le modèle ?', 'Vos modifications de la consigne seront remplacées par le texte du modèle (adapté au nombre de participants et aux formats choisis).', 'Remettre le modèle').then(function (ok) { if (ok) { run(); } }); } else { run(); }
  }
  function saveTravail() {
    S.importWarnings = null;
    var f = S.form;
    var body = { title: f.title, module: f.module, course_id: f.course_id, lessons: f.lessons, grille: f.grille, consignes: f.consignes, consigne: f.consigne, date_debut: f.date_debut || '', group_size: f.group_size || 2, graded: f.graded !== false, formats: f.formats && f.formats.length ? f.formats : ['pptx'], consigne_url: f.consigne_url, group_ids: f.group_ids, due_at: f.due_at || '', oral_bonus: !!f.oral_bonus, allow_solo: !!f.allow_solo, status: f.status };
    if (f.group_ids.length) { body.date_debut = ''; body.due_at = ''; }
    ['class_start', 'class_due'].forEach(function (k) {
      if (!f[k]) { return; }
      var m = {}; Object.keys(f[k]).forEach(function (g) { if (f.group_ids.indexOf(parseInt(g, 10)) > -1) { m[g] = f[k][g]; } }); body[k] = m;
    });
    var btn = root.querySelector('[data-act="save-travail"]');
    if (btn) { btn.disabled = true; }
    api(f.id ? 'travaux/' + f.id : 'travaux', { method: f.id ? 'PUT' : 'POST', body: body }).then(function (t) {
      S.dirty = false; toast('Travail enregistré.'); go('#/travail/' + t.id);
    }).catch(function (e) { toast(e.message, true); if (btn) { btn.disabled = false; } });
  }

  /* ---------- Bibliothèque de modèles (1.54.0) ---------- */
  function askName(title, text, value, withShare, shared, okLabel) {
    return new Promise(function (resolve) {
      var bg = document.createElement('div');
      bg.className = 'lpj-modal-bg';
      bg.innerHTML = '<div class="lpj-modal" role="dialog" aria-modal="true"><h3>' + esc(title) + '</h3><p>' + esc(text) + '</p>' +
        '<label class="lpj-field"><span>Nom du modèle</span><input type="text" data-n maxlength="120" value="' + esc(value) + '"></label>' +
        (withShare ? '<label class="lpj-check"><input type="checkbox" data-sh' + (shared ? ' checked' : '') + '> <span>Partager avec les autres enseignants du site</span></label>' : '') +
        '<div class="lpj-modal-actions"><button class="lpj-btn" data-r="0">Annuler</button><button class="lpj-btn lpj-btn-yellow" data-r="1">' + esc(okLabel || 'Enregistrer') + '</button></div></div>';
      document.body.appendChild(bg);
      var inp = bg.querySelector('[data-n]'); inp.focus(); inp.select();
      var done = function (ok) {
        var out = ok ? { name: inp.value.trim(), shared: !!(bg.querySelector('[data-sh]') && bg.querySelector('[data-sh]').checked) } : null;
        document.body.removeChild(bg); resolve(out);
      };
      inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); done(true); } if (e.key === 'Escape') { done(false); } });
      bg.addEventListener('click', function (e) {
        var b = e.target.closest('[data-r]');
        if (!b && e.target !== bg) { return; }
        done(!!(b && b.getAttribute('data-r') === '1'));
      });
    });
  }
  function saveModele() {
    var t = S.travail;
    askName('Enregistrer comme modèle', 'Le modèle garde le cours, les leçons, la grille, la consigne et les réglages du travail — sans les classes, les dates ni les dépôts. Vous le retrouverez dans la bibliothèque de modèles.', (t.module ? t.module + ' · ' : '') + t.title, true, false).then(function (r) {
      if (!r) { return; }
      api('travaux/' + t.id + '/modele', { method: 'POST', body: r }).then(function (m) {
        toast('Modèle « ' + m.name + ' » ajouté à la bibliothèque.');
      }).catch(function (e) { toast(e.message, true); });
    });
  }
  /* Série d'un modèle : M107 → M100, M2xx → M200… (d'après le module, sinon le titre ou le cours). */
  function serieOf(m) {
    var src = [m.module, m.name, m.title, m.course].join(' ');
    var x = src.match(/\bM\s?(\d)(\d\d)\b/i);
    return x ? 'M' + x[1] + '00' : 'Autres';
  }
  function normTxt(v) { return String(v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
  function modeleCard(m) {
    return '<article class="lpj-tile lpj-modele">' +
      '<div class="lpj-meta">' + (m.module ? '<b>' + esc(m.module) + '</b>' : '') + '<span>' + (m.graded ? 'Noté' : 'Formatif') + ' · ' + esc(groupWord(m.group_size)) + '</span>' + (m.shared ? '<span class="lpj-badge st-valide">Partagé</span>' : '') + '</div>' +
      '<h3>' + esc(m.name) + '</h3>' +
      (m.title && m.title !== m.name ? '<div class="lpj-muted" style="font-size:14px">Travail : ' + esc(m.title) + '</div>' : '') +
      '<div class="lpj-meta">' + (m.course ? '<span>' + esc(m.course) + '</span>' : '') + '<span>' + m.lessons + ' leçon' + (m.lessons > 1 ? 's' : '') + ' · ' + m.criteres + ' critères · ' + m.points + ' points</span><span>' + esc(m.formats) + '</span>' + (m.has_pdf ? '<span>Consigne PDF</span>' : '') + '</div>' +
      '<div class="lpj-meta"><span>' + (m.mine ? 'Votre modèle' : 'Par ' + esc(m.author)) + ' · ' + esc(fmtDate(m.created)) + (m.uses ? ' · utilisé ' + m.uses + ' fois' : '') + '</span></div>' +
      '<div class="lpj-modele-actions"><button class="lpj-btn lpj-btn-yellow lpj-btn-sm" data-act="use-modele" data-id="' + esc(m.id) + '">Utiliser ce modèle</button>' +
      (m.can_edit ? '<button class="lpj-btn lpj-btn-sm" data-act="ren-modele" data-id="' + esc(m.id) + '">Renommer</button>' +
        '<button class="lpj-btn lpj-btn-sm" data-act="share-modele" data-id="' + esc(m.id) + '" data-v="' + (m.shared ? '0' : '1') + '">' + (m.shared ? 'Ne plus partager' : 'Partager') + '</button>' +
        '<button class="lpj-btn lpj-btn-sm lpj-btn-danger" data-act="del-modele" data-id="' + esc(m.id) + '">Supprimer</button>' : '') + '</div></article>';
  }
  function showModeles() {
    loading();
    api('modeles').then(function (list) {
      S.modeles = list;
      if (S.mq === undefined) { S.mq = ''; S.mser = ''; S.mwho = ''; }
      var series = [];
      list.forEach(function (m) { m.serie = serieOf(m); m.hay = normTxt([m.name, m.title, m.module, m.course, m.author, m.formats].join(' ')); if (series.indexOf(m.serie) < 0) { series.push(m.serie); } });
      series.sort(function (a, b) { return a === 'Autres' ? 1 : (b === 'Autres' ? -1 : a.localeCompare(b, 'fr', { numeric: true })); });
      if (S.mser && series.indexOf(S.mser) < 0) { S.mser = ''; }
      var chip = function (v, label, n) { return '<button type="button" class="lpj-chip' + (S.mser === v ? ' is-on' : '') + '" data-mser="' + esc(v) + '">' + esc(label) + ' <span>' + n + '</span></button>'; };
      var tools = list.length ? '<div class="lpj-mtools">' +
        '<label class="lpj-msearch"><span aria-hidden="true">🔍</span><input type="search" data-mq value="' + esc(S.mq) + '" placeholder="Rechercher un modèle : module, titre, cours, collègue…" aria-label="Rechercher un modèle"></label>' +
        '<select data-mwho aria-label="Auteur"><option value=""' + (S.mwho ? '' : ' selected') + '>Tous les modèles</option><option value="mine"' + (S.mwho === 'mine' ? ' selected' : '') + '>Mes modèles</option><option value="others"' + (S.mwho === 'others' ? ' selected' : '') + '>Partagés par mes collègues</option></select>' +
        '</div><div class="lpj-chips">' + chip('', 'Toutes les séries', list.length) + series.map(function (sr) { return chip(sr, sr === 'Autres' ? 'Autres' : sr, list.filter(function (m) { return m.serie === sr; }).length); }).join('') + '</div>' : '';
      var body = '<div class="lpj-crumb"><a href="#/" data-go="#/">Vos travaux</a> / Bibliothèque de modèles</div>' +
        '<div class="lpj-bar"><h2>Bibliothèque de modèles</h2><button class="lpj-btn" data-go="#/nouveau">+ Travail vide</button></div>' +
        '<p class="lpj-muted" style="margin:-4px 0 16px">« Utiliser ce modèle » crée un nouveau travail en brouillon avec le cours, les leçons, la grille et la consigne du modèle. Il ne reste qu’à cocher les classes et indiquer les dates. Pour ajouter un modèle : ouvrez un travail, puis « Enregistrer comme modèle ».</p>' +
        tools + '<div data-mlist></div>';
      draw(page('LogiProjet · Espace enseignant', 'Bibliothèque de modèles', 'Vos travaux prêts à réutiliser, classés par série de modules', body));
      drawModeleList();
    }).catch(fail);
  }
  function drawModeleList() {
    var box = root.querySelector('[data-mlist]');
    if (!box) { return; }
    var list = S.modeles || [];
    if (!list.length) {
      box.innerHTML = '<div class="lpj-empty"><b>Aucun modèle pour l’instant</b>Ouvrez un de vos travaux et cliquez sur « 📚 Enregistrer comme modèle » : vous pourrez le réutiliser ici pour d’autres classes, l’année prochaine.</div>';
      return;
    }
    var words = normTxt(S.mq).split(/\s+/).filter(Boolean);
    var shown = list.filter(function (m) {
      if (S.mser && m.serie !== S.mser) { return false; }
      if (S.mwho === 'mine' && !m.mine) { return false; }
      if (S.mwho === 'others' && m.mine) { return false; }
      return words.every(function (w) { return m.hay.indexOf(w) > -1 || normTxt(m.serie).indexOf(w) > -1; });
    });
    if (!shown.length) {
      box.innerHTML = '<div class="lpj-empty"><b>Aucun modèle ne correspond</b>Essayez un autre mot, ou choisissez « Toutes les séries ».</div>';
      return;
    }
    var groups = {}, order = [];
    shown.forEach(function (m) { if (!groups[m.serie]) { groups[m.serie] = []; order.push(m.serie); } groups[m.serie].push(m); });
    order.sort(function (a, b) { return a === 'Autres' ? 1 : (b === 'Autres' ? -1 : a.localeCompare(b, 'fr', { numeric: true })); });
    box.innerHTML = order.map(function (sr) {
      var g = groups[sr].sort(function (a, b) { return (a.module || '').localeCompare(b.module || '', 'fr', { numeric: true }) || a.name.localeCompare(b.name, 'fr'); });
      return '<section class="lpj-serie"><h3 class="lpj-serie-h"><span class="lpj-serie-tag">' + esc(sr) + '</span>' + (sr === 'Autres' ? 'Sans module' : 'Modules ' + esc(sr.replace(/00$/, '')) + 'xx') + '<small>' + g.length + ' modèle' + (g.length > 1 ? 's' : '') + '</small></h3>' +
        '<div class="lpj-grid">' + g.map(modeleCard).join('') + '</div></section>';
    }).join('');
  }
  function useModele(id) {
    var m = (S.modeles || []).filter(function (x) { return x.id === id; })[0];
    api('modeles/' + id + '/utiliser', { method: 'POST', body: {} }).then(function (t) {
      S.importWarnings = t.warnings || [];
      S.fromModele = m ? m.name : (t.modele || '');
      toast('Nouveau travail créé à partir du modèle : choisissez les classes et les dates.');
      location.hash = '#/travail/' + t.id + '/modifier';
    }).catch(function (e) { toast(e.message, true); });
  }
  function updModele(id, body) {
    api('modeles/' + id, { method: 'PUT', body: body }).then(function (m) { toast(m.shared ? 'Modèle partagé avec vos collègues.' : 'Modèle enregistré.'); showModeles(); }).catch(function (e) { toast(e.message, true); });
  }
  function renModele(id) {
    var m = (S.modeles || []).filter(function (x) { return x.id === id; })[0];
    askName('Renommer le modèle', 'Le nouveau nom s’affiche dans la bibliothèque.', m ? m.name : '', false, false).then(function (r) { if (r && r.name) { updModele(id, { name: r.name }); } });
  }
  function delModele(id) {
    var m = (S.modeles || []).filter(function (x) { return x.id === id; })[0];
    ask('Supprimer ce modèle ?', 'Le modèle « ' + (m ? m.name : '') + ' » sera retiré de la bibliothèque. Les travaux déjà créés à partir de lui ne changent pas.', 'Supprimer', true).then(function (ok) {
      if (ok) { api('modeles/' + id, { method: 'DELETE' }).then(function () { toast('Modèle supprimé.'); showModeles(); }).catch(function (e) { toast(e.message, true); }); }
    });
  }

  /* ---------- Import / export d'un travail ---------- */
  function importTravail(file) {
    if (!file) { return; }
    if (file.size > 15 * 1048576) { toast('Ce fichier est trop lourd pour un travail LogiProjet.', true); return; }
    var reader = new FileReader();
    reader.onload = function () {
      var data;
      try { data = JSON.parse(reader.result); } catch (e) { toast('Ce fichier n’est pas un fichier .json valable.', true); return; }
      if (!data || data.format !== 'logiprojet-travail') { toast('Ce fichier n’est pas un travail LogiProjet.', true); return; }
      toast('Import en cours…');
      api('travaux/import', { method: 'POST', body: data }).then(function (t) {
        S.importWarnings = t.warnings || [];
        toast('Travail importé en brouillon : vérifiez-le, puis ouvrez-le aux dépôts.');
        go('#/travail/' + t.id + '/modifier');
      }).catch(function (e) { toast(e.message, true); });
    };
    reader.readAsText(file);
  }
  function exportTravail() {
    var t = S.travail;
    api('travaux/' + t.id + '/export').then(function (data) {
      var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (t.module ? t.module + '_' : '') + 'travail_LogiProjet.json';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
    }).catch(function (e) { toast(e.message, true); });
  }

  /* ---------- Détail d'un travail ---------- */
  function showTravail(id) {
    loading();
    api('travaux/' + id).then(function (t) { S.travail = t; drawTravail(); }).catch(fail);
  }
  function drawTravail(progress) {
    var t = S.travail;
    var todo = t.depots.filter(function (d) { return (d.status === 'depose' || d.status === 'erreur') && d.ext === 'pptx'; });
    var rowOf = function (d) {
      var low = d.note_finale !== null && d.note_finale < 4;
      var late = d.status !== 'groupe' && d.retard_jours > 0;
      var lateNow = d.status === 'groupe' && t.retard_actuel > 0;
      return '<tr class="is-click" data-go="#/depot/' + d.id + '" data-row="' + d.id + '"><td><b>' + esc(d.members.map(function (m) { return m.name; }).join(' & ')) + '</b>' + (t.group_size > 1 && d.members.length === 1 ? ' <span class="lpj-badge">seul·e</span>' : '') + '</td>' +
        '<td>' + (d.status === 'groupe' ? '<span class="lpj-muted">pas encore remis' + (d.draft ? ' · fichier de travail du ' + esc(fmtDate(d.draft.at)) : '') + '</span>' + (lateNow ? ' <span class="lpj-badge lpj-late">' + t.retard_actuel + ' j de retard</span>' : '') :
          esc(fmtDate(d.submitted_at)) + (late ? ' <span class="lpj-badge lpj-late' + (d.retard_excuse ? ' is-excused' : '') + '">' + d.retard_jours + ' j de retard' + (d.retard_excuse ? ' · excusé' : (t.graded === false ? '' : ' · ' + penaltyLabel(d.retard_jours))) + '</span>' : '')) + '</td>' +
        '<td>' + (d.slide_count || '–') + '</td><td>' + statusBadge(d.status) + '</td>' +
        '<td>' + (d.points !== null ? fmtN(d.points) + ' / ' + t.total : '–') + '</td><td><span class="lpj-note' + (low ? ' is-low' : '') + '">' + fmtN(d.note_finale) + '</span></td>' +
        '<td class="lpj-right"><span class="lpj-btn lpj-btn-sm">Ouvrir</span></td></tr>';
    };
    var byClass = {}, order = [];
    t.depots.forEach(function (d) { var c = d.classe || 'Sans classe'; if (!byClass[c]) { byClass[c] = []; order.push(c); } byClass[c].push(d); });
    order.sort(function (a, b) { return a === 'Sans classe' ? 1 : (b === 'Sans classe' ? -1 : a.localeCompare(b, 'fr')); });
    var thead = '<thead><tr><th>' + (t.group_size === 1 ? 'Apprenti·e' : 'Groupe') + '</th><th>Remis le</th><th>Slides</th><th>Statut</th><th>Points</th><th>Note</th><th></th></tr></thead>';
    var rows = order.map(function (c) {
      var list = byClass[c];
      var remis = list.filter(function (d) { return d.status !== 'groupe'; }).length;
      var lates = list.filter(function (d) { return d.status !== 'groupe' && d.retard_jours > 0; }).length;
      return '<section class="lpj-classblock"><div class="lpj-classblock-head"><h3>' + esc(c) + '</h3><span class="lpj-muted">' + list.length + ' ' + (t.group_size === 1 ? 'apprenti' + (list.length > 1 ? 's' : '') : 'groupe' + (list.length > 1 ? 's' : '')) + ' · ' + remis + ' remis' +
        (lates ? ' · <b class="lpj-late-txt">' + lates + ' en retard</b>' : '') + '</span></div>' +
        '<table class="lpj-table">' + thead + '<tbody>' + list.map(rowOf).join('') + '</tbody></table></section>';
    }).join('');
    var groupNames = (S.groups || []).filter(function (g) { return t.group_ids.indexOf(g.id) > -1; }).map(function (g) { return g.title; });
    var body = '<div class="lpj-crumb"><a href="#/" data-go="#/">Vos travaux</a> / ' + esc(t.title) + '</div>' +
      '<div class="lpj-bar"><h2>' + esc(t.title) + ' ' + travailBadge(t) + '</h2>' +
      '<button class="lpj-btn" data-go="#/travail/' + t.id + '/modifier">Modifier</button>' +
      '<button class="lpj-btn" data-go="#/travail/' + t.id + '/apercu">Aperçu apprenti</button>' +
      '<a class="lpj-btn" href="' + esc(t.print_url) + '" target="_blank" rel="noopener">' + (t.depots.length ? 'Imprimer les grilles' : 'Grille vierge') + '</a>' +
      (t.depots.length ? '<a class="lpj-btn" href="' + esc(t.print_url + '&vierge=1') + '" target="_blank" rel="noopener">Grille vierge</a>' : '') +
      '<a class="lpj-btn" href="' + esc(t.csv_url) + '">Exporter les notes (CSV)</a>' +
      '<button class="lpj-btn" data-act="save-modele" title="Garder ce travail dans votre bibliothèque pour le réutiliser avec d’autres classes">📚 Enregistrer comme modèle</button>' +
      '<button class="lpj-btn" data-act="export-travail" title="Fichier .json pour un collègue d’un autre site">Exporter le travail</button>' +
      '<button class="lpj-btn lpj-btn-yellow" data-act="ai-all"' + (todo.length && !S.busy ? '' : ' disabled') + '>Corriger avec l’IA (' + todo.length + ')</button></div>' +
      '<div class="lpj-meta" style="margin-bottom:14px">' + (t.module ? '<span>Module <b>' + esc(t.module) + '</b></span>' : '') +
      (datesOf(t, 'start').some(Boolean) ? '<span>' + esc(rangeTxt(datesOf(t, 'start'), 'Début le', '')) + '</span>' : '') +
      '<span>' + esc(rangeTxt(datesOf(t, 'due'), 'À rendre le', 'Sans date limite')) + '</span><span>' + t.grille.length + ' critères · ' + t.total + ' points</span>' +
      '<span>' + (groupNames.length ? 'Classes : ' + esc(groupNames.join(', ')) : (t.group_ids.length ? t.group_ids.length + ' classe(s)' : 'Toutes les classes')) + '</span></div>' +
      dueBanner(t) + lockBanner(t) +
      (!anyOpen(t) ? '<div class="lpj-alert is-y">Ce travail est en <b>brouillon</b> : les apprentis ne le voient pas encore. <button class="lpj-btn lpj-btn-sm lpj-btn-yellow" data-act="open-travail" style="margin-left:8px">Ouvrir aux dépôts</button></div>' : '') +
      (progress ? '<div class="lpj-alert is-b"><b>Correction par l’IA en cours : ' + progress.done + ' / ' + progress.total + '</b> · ne fermez pas cette page.<div class="lpj-progress"><span style="width:' + Math.round(progress.done / progress.total * 100) + '%"></span></div></div>' : '') +
      (S.me.ai_ready ? '' : '<div class="lpj-alert is-y">Aucune clé API configurée : la correction directe par IA est désactivée. Utilisez « Corriger dans Claude » ci-dessous.</div>') +
      classStatusPanel(t) +
      claudePanel(t) +
      classesPanel(t) +
      (t.depots.length ? rows :
        '<div class="lpj-empty"><b>Aucun groupe ni dépôt pour l’instant</b>Les apprentis forment leur groupe et déposent leur fichier depuis la page qui contient le code court [logiprojet].</div>');
    var nf = t.depots.filter(function (d) { return d.status !== 'groupe'; }).length, ng = t.depots.length - nf;
    draw(page('LogiProjet · Espace enseignant', t.title, (t.module ? 'Module ' + t.module + ' · ' : '') + nf + ' dépôt' + (nf > 1 ? 's' : '') + (ng ? ' · ' + ng + ' en cours, pas remis' : ''), body));
  }
  function lateCard(t, d, val) {
    if (!d.retard_jours) { return ''; }
    var graded = t.graded !== false;
    return '<section class="lpj-card lpj-latecard' + (d.retard_excuse ? ' is-excused' : '') + '"><h3>Remis avec ' + d.retard_jours + ' jour' + (d.retard_jours > 1 ? 's' : '') + ' de retard</h3>' +
      '<p style="margin:0 0 8px">Échéance : ' + esc(fmtDate(d.echeance || t.due_at)) + ' · remis le ' + esc(fmtDate(d.submitted_at)) + '.' +
      (graded ? ' Pénalité : <b>' + (d.retard_jours > 14 ? 'note 1 (plus de 14 jours)' : (d.retard_jours > 7 ? '−1 point (8 à 14 jours)' : '−0.5 point (1 à 7 jours)')) + '</b>, déjà comptée dans la note finale.' : ' Activité non notée : pas de pénalité.') + '</p>' +
      (graded ? '<label class="lpj-check"><input type="checkbox" data-late-excuse' + (d.retard_excuse ? ' checked' : '') + (val ? ' disabled' : '') + '> <span>Excuser ce retard (certificat médical, accord préalable…) : aucune pénalité</span></label>' : '') + '</section>';
  }
  function oralBonusOf(t, d) {
    if (!t.oral_bonus || !d || !d.oral || !d.oral.presented) { return 0; }
    var p = 0; ORAL.forEach(function (o) { p += parseInt(d.oral[o[0]], 10) || 0; });
    return p >= 4 ? 0.5 : 0;
  }
  function penaltyText(t, d) {
    if (!d || !d.retard_jours || d.status === 'groupe' || t.graded === false) { return ''; }
    return d.retard_excuse ? 'retard de ' + d.retard_jours + ' j excusé' : 'retard de ' + d.retard_jours + ' j : ' + (d.retard_jours > 14 ? 'note 1' : penaltyLabel(d.retard_jours));
  }
  function penaltyLabel(days) {
    return days > 14 ? 'note 1' : (days > 7 ? '−1' : '−0.5');
  }
  function dueBanner(t) {
    var cl = t.classes || [];
    if (cl.length > 1 && cl.some(function (c) { return c.due !== cl[0].due; })) {
      var now = Date.now();
      return '<div class="lpj-due lpj-due-multi"><span class="lpj-due-label">Remise au plus tard</span><div class="lpj-due-list">' + cl.map(function (c) {
        if (!c.due) { return '<span><b>' + esc(c.title) + '</b> · sans date limite</span>'; }
        var ms = new Date(c.due) - now, days = Math.ceil(Math.abs(ms) / 86400000);
        var when = ms < 0 ? 'dépassée depuis ' + days + ' j' : (days <= 1 ? 'dans moins de 24 h' : 'dans ' + days + ' jours');
        return '<span class="' + (ms < 0 ? 'is-late' : (days <= 3 ? 'is-soon' : '')) + '"><b>' + esc(c.title) + '</b> · ' + esc(fmtDate(c.due)) + ' <em>' + when + '</em></span>';
      }).join('') + '</div>' +
        (t.graded === false ? '<span class="lpj-muted">Activité non notée : les remises en retard sont signalées, sans pénalité.</span>' :
          '<span class="lpj-due-rule">Retard (selon la classe) : 1 à 7 j <b>−0.5</b> · 8 à 14 j <b>−1</b> · plus de 14 j <b>note 1</b></span>') + '</div>';
    }
    var oneDue = t.due_at, oneTxt = (t.vars && t.vars['{date_fin}']) || fmtDate(t.due_at), dueIn = t.due_in;
    if (cl.length) {
      oneDue = cl[0].due || '';
      oneTxt = fmtDate(oneDue);
      dueIn = oneDue ? Math.round((new Date(oneDue) - new Date()) / 1000) : null;
    }
    if (!oneDue) { return '<div class="lpj-due is-none"><span class="lpj-due-label">Date de remise</span><b>aucune date limite</b><span class="lpj-muted">Ajoutez-la dans « Modifier » (dates de chaque classe) pour appliquer les pénalités de retard.</span></div>'; }
    var ms = (dueIn || 0) * 1000;
    var days = Math.ceil(Math.abs(ms) / 86400000);
    var cls = ms < 0 ? 'is-late' : (days <= 3 ? 'is-soon' : '');
    var when = ms < 0 ? 'dépassée depuis ' + days + ' jour' + (days > 1 ? 's' : '') : (days <= 1 ? 'dans moins de 24 h' : 'dans ' + days + ' jours');
    return '<div class="lpj-due ' + cls + '"><span class="lpj-due-label">Remise au plus tard</span><b>' + esc(oneTxt) + '</b><span class="lpj-due-when">' + when + '</span>' +
      (t.graded === false ? '<span class="lpj-muted">Activité non notée : les remises en retard sont signalées, sans pénalité.</span>' :
        '<span class="lpj-due-rule">Retard : 1 à 7 j <b>−0.5</b> · 8 à 14 j <b>−1</b> · plus de 14 j <b>note 1</b></span>') + '</div>';
  }
  function classesPanel(t) {
    var cl = t.sans_groupe || [];
    if (!cl.length) { return ''; }
    var solo = t.group_size === 1;
    var missing = cl.reduce(function (n, c) { return n + c.sans.length; }, 0);
    var blocks = cl.map(function (c) {
      var ok = !c.sans.length;
      return '<div class="lpj-class' + (ok ? ' is-ok' : '') + '"><div class="lpj-class-head"><b>' + esc(c.classe) + '</b>' +
        (ok ? '<span class="lpj-badge st-valide">✓ ' + (solo ? 'Tous ont commencé' : 'Tous ont un groupe') + '</span>' :
          '<span class="lpj-badge st-propose">' + c.sans.length + ' ' + (solo ? 'sans dépôt' : 'sans groupe') + ' sur ' + c.total + '</span>') + '</div>' +
        (ok ? '' : '<ul>' + c.sans.map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') + '</ul>') +
        (c.seuls && !solo ? '<small class="lpj-muted">dont ' + c.seuls + ' apprenti' + (c.seuls > 1 ? 's' : '') + ' seul·e' + (c.seuls > 1 ? 's' : '') + ' par choix</small>' : '') + '</div>';
    }).join('');
    return '<details class="lpj-card lpj-classes"' + (missing ? ' open' : '') + '><summary><b>' + (solo ? 'Qui n’a encore rien déposé' : 'Qui n’a pas encore de groupe') + '</b> <span class="lpj-muted">· par classe · ' +
      (missing ? missing + ' apprenti' + (missing > 1 ? 's' : '') + ' en attente' : 'tout le monde est réparti') + '</span></summary><div class="lpj-classes-grid">' + blocks + '</div></details>';
  }
  function claudePanel(t) {
    if (!t.depots.some(function (d) { return d.status !== 'groupe'; }) || !t.claude_export_url) { return ''; }
    var open = t.depots.filter(function (d) { return d.status !== 'valide' && d.status !== 'groupe'; }).length;
    var res = S.corrResult;
    return '<details class="lpj-card lpj-claude"' + (res || !S.me.ai_ready ? ' open' : '') + '><summary><b>Corriger dans Claude</b> <span class="lpj-muted">· exporter, corriger, importer · sans limite de temps du serveur</span></summary>' +
      (res ? '<div class="lpj-alert ' + (res.warnings.length ? 'is-y' : 'is-g') + '"><b>' + res.importes + ' correction' + (res.importes > 1 ? 's' : '') + ' importée' + (res.importes > 1 ? 's' : '') + '.</b> Les notes de Claude sont des propositions marquées « IA » : ouvrez chaque dépôt, vérifiez, puis validez.' +
        (res.warnings.length ? '<ul style="margin:6px 0 0 18px;padding:0">' + res.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul>' : '') + '</div>' : '') +
      '<ol class="lpj-claude-steps">' +
      '<li><b>Exporter les dépôts</b><span>' + open + ' dépôt' + (open > 1 ? 's' : '') + ' non validé' + (open > 1 ? 's' : '') + '. Noms remplacés par [Apprenti 1], [Apprenti 2]… dans le texte des PowerPoint.</span>' +
        '<label class="lpj-check"><input type="checkbox" data-claude-pptx> Joindre aussi les fichiers PowerPoint (pour que Claude voie les images ; fichier plus lourd)</label>' +
        '<a class="lpj-btn lpj-btn-sm" data-claude-export href="' + esc(t.claude_export_url) + '"' + (open ? '' : ' aria-disabled="true"') + '>Exporter pour Claude (.zip)</a></li>' +
      '<li><b>Corriger dans Claude</b><span>Installez une fois la compétence de correction, puis envoyez le .zip à Claude : « Corrige cet export LogiProjet ». Claude rend un fichier .json.</span>' +
        (S.me.correction_skill_url ? '<a class="lpj-btn lpj-btn-sm" href="' + esc(S.me.correction_skill_url) + '">Compétence de correction (.zip)</a>' : '') + '</li>' +
      '<li><b>Importer la correction</b><span>Les notes arrivent comme propositions. Les dépôts déjà validés ne sont pas modifiés.</span>' +
        '<label class="lpj-btn lpj-btn-sm lpj-btn-yellow">Importer la correction (.json)<input type="file" accept=".json,application/json" class="lpj-sr" data-corr-import></label></li>' +
      '</ol></details>';
  }
  function importCorrection(file) {
    var t = S.travail;
    if (!file || !t) { return; }
    if (file.size > 10 * 1048576) { toast('Ce fichier est trop lourd pour une correction.', true); return; }
    var reader = new FileReader();
    reader.onload = function () {
      var data;
      try { data = JSON.parse(reader.result); } catch (e) { toast('Ce fichier n’est pas un fichier .json valable.', true); return; }
      if (!data || data.format !== 'logiprojet-correction') { toast(data && data.format === 'logiprojet-correction-export' ? 'C’est le fichier exporté pour Claude : importez le fichier .json que Claude a rendu.' : 'Ce fichier n’est pas une correction LogiProjet.', true); return; }
      toast('Import de la correction…');
      api('travaux/' + t.id + '/correction', { method: 'POST', body: data }).then(function (r) {
        S.corrResult = r;
        toast(r.importes ? r.importes + ' correction(s) importée(s) : vérifiez-les puis validez.' : 'Aucune correction importée.', !r.importes);
        showTravail(t.id);
      }).catch(function (e) { toast(e.message, true); });
    };
    reader.readAsText(file);
  }
  function aiAll() {
    var t = S.travail;
    var todo = t.depots.filter(function (d) { return (d.status === 'depose' || d.status === 'erreur') && d.ext === 'pptx'; });
    if (!todo.length || S.busy) { return; }
    S.busy = true;
    var done = 0, errors = 0;
    drawTravail({ done: 0, total: todo.length });
    var next = function () {
      if (!todo.length) {
        S.busy = false;
        toast(errors ? errors + ' correction(s) en erreur : ouvrez le dépôt pour voir le détail.' : 'Toutes les propositions de l’IA sont prêtes : vérifiez-les puis validez.', !!errors);
        return showTravail(t.id);
      }
      var d = todo.shift();
      api('depots/' + d.id + '/corriger', { method: 'POST' }).catch(function () { errors++; }).then(function () {
        done++;
        drawTravail({ done: done, total: done + todo.length });
        next();
      });
    };
    next();
  }

  /* ---------- Correction d'un dépôt ---------- */
  function showDepot(id) {
    loading();
    api('depots/' + id).then(function (d) {
      S.depot = d; S.dirty = false;
      if (S.travail && S.travail.id === d.travail_id) { return; }
      return api('travaux/' + d.travail_id).then(function (t) { S.travail = t; });
    }).then(drawDepot).catch(fail);
  }
  function compute() {
    var t = S.travail, d = S.depot, pts = 0, filled = 0;
    t.grille.forEach(function (c) {
      var s = d.scores[c.id];
      if (s && s.score !== null && s.score !== undefined && s.score !== '') { pts += parseInt(s.score, 10); filled++; }
    });
    var note = filled ? noteOf(pts, t.total) : null;
    var oralPts = 0;
    ORAL.forEach(function (o) { oralPts += parseInt(d.oral[o[0]], 10) || 0; });
    var bonus = (t.oral_bonus && d.oral.presented && oralPts >= 4) ? 0.5 : 0;
    var finale = note === null ? null : Math.min(6, note + bonus);
    var days = d.status !== 'groupe' ? (d.retard_jours || 0) : 0;
    var pen = (t.graded !== false && days > 0 && !d.retard_excuse) ? (days > 14 ? 'note1' : (days > 7 ? 1 : 0.5)) : null;
    if (finale !== null && pen === 'note1') { finale = 1; } else if (finale !== null && pen) { finale = Math.max(1, finale - pen); }
    return { points: filled ? pts : null, note: note, oral: oralPts, bonus: bonus, retard: days, penalite: pen, finale: finale, complete: filled === t.grille.length };
  }
  function seg(name, value, ai) {
    var h = '<span class="lpj-seg" role="radiogroup">';
    for (var v = 0; v <= 2; v++) {
      var on = value !== null && value !== undefined && value !== '' && parseInt(value, 10) === v;
      h += '<button type="button" role="radio" aria-checked="' + on + '" class="' + (on ? 'is-on' : '') + (ai !== undefined && ai !== null && parseInt(ai, 10) === v ? ' is-ai' : '') + '" data-seg="' + esc(name) + '" data-v="' + v + '">' + v + '</button>';
    }
    return h + '</span>';
  }
  function drawDepot() {
    var t = S.travail, d = S.depot, x = d.extract || {}, ai = d.ai, val = d.status === 'valide';
    var names = d.members.map(function (m) { return m.name; }).join(' & ');
    if (d.status === 'groupe') {
      return draw(page('LogiProjet · Correction', names, t.title,
        '<div class="lpj-crumb"><a href="#/" data-go="#/">Vos travaux</a> / <a href="#/travail/' + t.id + '" data-go="#/travail/' + t.id + '">' + esc(t.title) + '</a> / ' + esc(names) + '</div>' +
        '<section class="lpj-card"><h3>' + esc(names) + ' ' + statusBadge('groupe') + '</h3>' +
        '<p>' + (t.group_size > 1 ? 'Ce groupe est enregistré depuis le ' + esc(fmtDate(d.submitted_at)) + ' (par ' + esc(d.submitted_by) + '), mais il n’a pas encore remis son travail.' : 'Ce travail n’est pas encore remis.') + '</p>' +
        (d.draft ? '<div class="lpj-draft"><div><b>Fichier de travail</b> (pas remis)<br><a href="' + esc(d.draft.url) + '">' + esc(d.draft.name) + '</a> · ' + size(d.draft.size) + '<br><span class="lpj-muted">Enregistré le ' + esc(fmtDate(d.draft.at)) + ' par ' + esc(d.draft.by) + '</span></div><a class="lpj-btn lpj-btn-sm" href="' + esc(d.draft.url) + '">Télécharger</a></div>' : '<p class="lpj-muted">Aucun fichier de travail enregistré pour l’instant.</p>') +
        '<p class="lpj-muted">Pour changer la composition, un membre peut quitter le groupe depuis sa page, ou vous pouvez supprimer le groupe : chacun pourra alors en former un nouveau.</p>' +
        '<div class="lpj-bar" style="margin:12px 0 0"><a class="lpj-btn" href="' + esc(d.print_url) + '" target="_blank" rel="noopener">Imprimer la grille du groupe</a><button class="lpj-btn lpj-btn-danger" data-act="del-depot">' + (t.group_size > 1 ? 'Supprimer le groupe' : 'Supprimer') + '</button></div></section>'));
    }
    var revoir = ai && ai.a_revoir ? ai.a_revoir : [];
    var slides = (x.slides || []).map(function (s) {
      var warn = s.words > 60 || s.lines.length > 6;
      var tags = '<span class="' + (s.words > 60 ? 'w' : '') + '">' + s.words + ' mots</span>' +
        (s.images ? '<span>' + s.images + ' image' + (s.images > 1 ? 's' : '') + '</span>' : '') + (s.tables ? '<span>tableau</span>' : '') +
        (s.charts ? '<span>graphique</span>' : '') + (s.diagrams ? '<span>SmartArt</span>' : '') + (s.notes ? '<span>notes</span>' : '') + (s.hidden ? '<span class="w">masquée</span>' : '');
      return '<div class="lpj-slide' + (warn ? ' is-warn' : '') + (s.hidden ? ' is-hidden' : '') + '"><div class="lpj-slide-head"><span>' + s.n + '. ' + esc(s.title || '(sans titre)') + '</span></div>' +
        '<div class="lpj-slide-tags">' + tags + '</div>' + (s.lines.length ? '<div class="lpj-slide-text">' + esc(s.lines.join('\n')) + '</div>' : '') +
        (s.notes ? '<div class="lpj-slide-notes">Notes : ' + esc(s.notes) + '</div>' : '') + '</div>';
    }).join('');
    var gr = blocs(t.grille).map(function (b) {
      return '<div class="lpj-bloc-title"><span>' + esc(b.name) + '</span><span>' + (2 * b.items.length) + ' pts</span></div>' +
        b.items.map(function (it) {
          var c = it.c, s = d.scores[c.id] || {};
          return '<div class="lpj-crit"><div class="lpj-crit-label">' + esc(c.label) + (revoir.indexOf(c.id) > -1 ? '<span class="lpj-badge st-attention">à revoir</span>' : '') + '</div>' +
            seg('c:' + c.id, s.score, s.ai) + '<textarea data-comment="' + esc(c.id) + '" rows="2" placeholder="Commentaire (visible par les apprentis)"' + (val ? ' disabled' : '') + '>' + esc(s.comment || '') + '</textarea></div>';
        }).join('');
    }).join('');
    var k = compute();
    var oral = t.oral_bonus ? '<section class="lpj-card"><h3>Bonus oral</h3><label class="lpj-check"><input type="checkbox" data-oral="presented"' + (d.oral.presented ? ' checked' : '') + (val ? ' disabled' : '') + '> <span>Ce duo a été tiré au sort et a présenté devant la classe</span></label>' +
      (d.oral.presented ? ORAL.map(function (o) { return '<div class="lpj-crit"><div class="lpj-crit-label">' + o[1] + '</div>' + seg('o:' + o[0], d.oral[o[0]]) + '</div>'; }).join('') +
        '<p class="lpj-muted">' + k.oral + ' / 6 · ' + (k.bonus ? '<b>+0.5</b> sur la note' : 'il faut 4 / 6 pour le bonus') + '</p>' : '') + '</section>' : '';
    var fb = d.feedback || {};
    var head = '';
    if (d.ai_error) { head += '<div class="lpj-alert is-r"><b>La dernière correction par IA a échoué :</b> ' + esc(d.ai_error) + '</div>'; }
    if (ai) {
      head += '<div class="lpj-alert is-b"><b>Proposition de l’IA</b> (' + esc(d.ai_model) + ') · ' + esc(ai.resume || '') + '<br><span class="lpj-muted">Les notes proposées par l’IA sont marquées « IA ». Vérifiez chaque critère : c’est vous qui décidez.</span>' +
        (revoir.length ? '<br><b>' + revoir.length + ' critère' + (revoir.length > 1 ? 's' : '') + ' à revoir.</b>' : '') + '</div>';
      if (ai.copie_suspecte) { head += '<div class="lpj-alert is-r"><b>Copier-coller possible :</b> l’IA a trouvé des passages qui semblent recopiés mot pour mot du cours.</div>'; }
    } else if (!d.ai_error) {
      head += d.ext === 'pptx' ? '<div class="lpj-alert is-y">Ce dépôt n’a pas encore été corrigé par l’IA. Vous pouvez lancer la correction, ou noter vous-même directement.</div>' : '<div class="lpj-alert is-y">Fichier ' + esc((d.ext || '').toUpperCase()) + ' : la correction directe par l’IA ne lit que les PowerPoint (.pptx). Ouvrez le fichier et notez directement dans la grille, ou corrigez-le dans Claude depuis la page du travail.</div>';
    }
    if (val) { head += '<div class="lpj-alert is-g"><b>Validé et publié le ' + esc(fmtDate(d.validated_at, false)) + '.</b> ' + (t.graded === false ? 'Les apprentis voient les critères de réussite et votre retour.' : 'Les apprentis voient leur note, la grille et votre retour.') + ' Retirez la publication pour modifier.</div>'; }

    var body = '<div class="lpj-crumb"><a href="#/" data-go="#/">Vos travaux</a> / <a href="#/travail/' + t.id + '" data-go="#/travail/' + t.id + '">' + esc(t.title) + '</a> / ' + esc(names) + '</div>' +
      '<div class="lpj-bar"><h2>' + esc(names) + ' ' + statusBadge(d.status) + '</h2>' +
      '<a class="lpj-btn" href="' + esc(d.file_url) + '">Télécharger le fichier</a>' +
      '<a class="lpj-btn" href="' + esc(d.print_url) + '" target="_blank" rel="noopener">Imprimer la grille</a>' +
      (!val && d.ext === 'pptx' ? '<button class="lpj-btn lpj-btn-yellow" data-act="ai-one"' + (S.me.ai_ready && !S.busy ? '' : ' disabled') + '>' + (ai ? 'Relancer l’IA' : 'Corriger avec l’IA') + '</button>' : '') +
      (!val ? '<button class="lpj-btn" data-act="give-back" title="Le fichier remis redevient le fichier de travail : les apprentis peuvent le rouvrir, le modifier et le remettre à nouveau">Rendre ' + (t.group_size > 1 ? 'au groupe' : 'à l’apprenti') + '</button><button class="lpj-btn lpj-btn-danger" data-act="del-depot">Supprimer le dépôt</button>' : '') + '</div>' +
      '<div class="lpj-meta" style="margin-bottom:14px"><span>' + esc(d.file_name) + ' · ' + size(d.file_size) + '</span><span>Déposé le ' + esc(fmtDate(d.submitted_at)) + ' par ' + esc(d.submitted_by) + '</span></div>' + head +
      '<div class="lpj-review"><section class="lpj-card"><h3>Contenu du PowerPoint</h3>' +
      (x.slides ? '<p class="lpj-muted">' + x.slide_count + ' slides · ' + x.words + ' mots · ' + x.visuals + ' visuels · notes sur ' + x.notes_count + ' slide' + (x.notes_count > 1 ? 's' : '') + '. Une bordure jaune signale plus de 60 mots ou plus de 6 lignes.</p><div class="lpj-slides">' + slides + '</div>' :
        '<p class="lpj-muted">Le contenu n’a pas pu être lu. Téléchargez le fichier pour le consulter.</p>') + '</section>' +
      '<div>' + lateCard(t, d, val) + '<section class="lpj-card"><h3>Grille d’évaluation</h3>' + gr + '</section>' + oral +
      '<section class="lpj-card"><h3>Retour aux apprentis</h3><label class="lpj-field"><span>Points forts</span><textarea data-fb="points_forts" rows="3"' + (val ? ' disabled' : '') + '>' + esc(fb.points_forts || '') + '</textarea></label>' +
      '<label class="lpj-field"><span>Piste d’amélioration</span><textarea data-fb="piste" rows="3"' + (val ? ' disabled' : '') + '>' + esc(fb.piste || '') + '</textarea></label></section></div></div>' +
      '<div class="lpj-sum" data-sum>' + sumHtml(k, val) + '</div>';
    draw(page('LogiProjet · Correction', names, t.title, body));
  }
  function sumHtml(k, val) {
    var t = S.travail;
    if (t.graded === false) {
      return '<span>Activité <b>non notée</b> : les critères et votre retour sont publiés sans note.</span><span class="lpj-muted" style="color:#c9d1d9" data-dirty>' + (S.dirty ? 'Non enregistré' : '') + '</span>' +
        (val ? '<button class="lpj-btn lpj-btn-yellow" data-act="unvalidate">Retirer la publication</button>' : '<button class="lpj-btn" data-act="save-depot">Enregistrer</button><button class="lpj-btn lpj-btn-green" data-act="validate" style="margin-left:0">Publier le retour</button>');
    }
    return '<span>Points <b>' + fmtN(k.points) + '</b> / ' + t.total + '</span><span>Note du support <b>' + fmtN(k.note) + '</b></span>' +
      (t.oral_bonus ? '<span>Bonus <b>' + (k.bonus ? '+0.5' : '0') + '</b></span>' : '') +
      (k.retard ? '<span class="lpj-sum-late">Retard ' + k.retard + ' j <b>' + (S.depot.retard_excuse ? 'excusé' : (k.penalite === 'note1' ? 'note 1' : '−' + fmtN(k.penalite))) + '</b></span>' : '') +
      '<span>Note finale <b>' + fmtN(k.finale) + '</b></span>' +
      '<span class="lpj-muted" style="color:#c9d1d9" data-dirty>' + (S.dirty ? 'Non enregistré' : '') + '</span>' +
      (val ? '<button class="lpj-btn lpj-btn-yellow" data-act="unvalidate">Retirer la publication</button>' :
        '<button class="lpj-btn" data-act="save-depot">Enregistrer</button><button class="lpj-btn lpj-btn-green" data-act="validate" style="margin-left:0"' + (k.complete ? '' : ' disabled title="Notez tous les critères"') + '>Valider et publier</button>');
  }
  function refreshSum() {
    var el = root.querySelector('[data-sum]');
    if (el) { el.innerHTML = sumHtml(compute(), S.depot.status === 'valide'); }
  }
  function depotPayload(action) {
    var d = S.depot, scores = {};
    Object.keys(d.scores).forEach(function (k) { scores[k] = { score: d.scores[k].score, comment: d.scores[k].comment || '' }; });
    return { action: action, scores: scores, oral: d.oral, feedback: d.feedback, retard_excuse: !!d.retard_excuse };
  }
  function saveDepot(action) {
    var d = S.depot;
    if (action === 'validate' && !compute().complete && S.travail.graded !== false) { toast('Notez tous les critères avant de valider.', true); return; }
    S.busy = true;
    api('depots/' + d.id, { method: 'PUT', body: depotPayload(action) }).then(function (nd) {
      S.busy = false; S.depot = nd; S.dirty = false;
      toast(action === 'validate' ? (S.travail.graded === false ? 'Publié : les apprentis voient maintenant votre retour.' : 'Validé : les apprentis voient maintenant leur note.') : (action === 'unvalidate' ? 'Publication retirée : vous pouvez modifier.' : 'Enregistré.'));
      drawDepot();
    }).catch(function (e) { S.busy = false; toast(e.message, true); });
  }
  function aiOne() {
    var d = S.depot;
    var run = function () {
      S.busy = true;
      var btn = root.querySelector('[data-act="ai-one"]');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="lpj-spinner"></span> L’IA corrige… (jusqu’à 2 minutes)'; }
      api('depots/' + d.id + '/corriger', { method: 'POST' }).then(function (nd) {
        S.busy = false; S.depot = nd; S.dirty = false; toast('Proposition de l’IA prête : vérifiez chaque critère.'); drawDepot();
      }).catch(function (e) { S.busy = false; toast(e.message, true); showDepot(d.id); });
    };
    if (d.ai || S.dirty) {
      ask('Relancer la correction ?', 'Vos notes et commentaires actuels pour ce duo seront remplacés par une nouvelle proposition de l’IA.', 'Relancer').then(function (ok) { if (ok) { run(); } });
    } else { run(); }
  }

  /* =====================================================================
     APPRENTIS
     ===================================================================== */
  function studentHome() {
    loading();
    api('eleve/travaux').then(function (list) {
      S.elist = list;
      clearTimeout(S.lockTimer);
      var next = list.filter(function (t) { return t.locked; }).reduce(function (n, t) { return Math.min(n, t.starts_in); }, Infinity);
      if (next < 86400) { S.lockTimer = setTimeout(function () { if (!S.me.is_teacher && (location.hash || '#/') === '#/') { studentHome(); } }, (next + 2) * 1000); }
      var tiles = list.map(function (t) {
        if (t.locked) {
          return '<article class="lpj-tile is-g lpj-tile-locked" data-locked="' + t.id + '" tabindex="0" title="Ce travail n’a pas encore commencé"><div class="lpj-meta">' + (t.module ? '<b>' + esc(t.module) + '</b>' : '') + '<span>' + (t.graded === false ? 'Activité non notée' : 'Travail noté') + (t.group_size > 1 ? ' en ' + esc(groupWord(t.group_size)) : ' individuel') + '</span></div>' +
            '<h3>' + esc(t.title) + '</h3><div class="lpj-meta">' + (t.due_at ? '<span>À rendre avant le ' + esc(fmtDate(t.due_at)) + '</span>' : '') + '</div>' +
            '<div class="lpj-stats"><span class="lpj-badge st-lock">🔒 S’ouvre le ' + esc(fmtDate(t.date_debut)) + '</span></div></article>';
        }
        var d = t.depot, cls, state;
        if (d && d.status === 'valide') { cls = ''; state = '<span class="lpj-badge st-valide">' + (t.graded === false ? 'Corrigé · retour disponible' : 'Corrigé · note ' + fmtN(d.note_finale)) + '</span>'; }
        else if (t.can_submit && t.retard_actuel && (!d || d.status === 'groupe')) { cls = 'is-r'; state = '<span class="lpj-badge st-erreur">En retard de ' + t.retard_actuel + ' j · à remettre</span>'; }
        else if (d && d.status === 'groupe' && t.can_submit) { cls = 'is-y'; state = '<span class="lpj-badge st-groupe">' + (d.draft ? 'En cours · fichier de travail enregistré' : (t.group_size > 1 ? 'Groupe enregistré · fichier à déposer' : 'À rendre')) + '</span>'; }
        else if (d && d.status === 'groupe') { cls = 'is-r'; state = '<span class="lpj-badge st-erreur">Remises fermées · pas remis</span>'; }
        else if (d) { cls = 'is-b'; state = '<span class="lpj-badge st-depose">Remis · en attente de correction</span>'; }
        else if (t.can_submit) { cls = 'is-y'; state = '<span class="lpj-badge st-propose">À rendre</span>'; }
        else { cls = 'is-r'; state = '<span class="lpj-badge st-erreur">Remises fermées</span>'; }
        return '<article class="lpj-tile lpj-tile-click ' + cls + '" data-go="#/t/' + t.id + '" tabindex="0"><div class="lpj-meta">' + (t.module ? '<b>' + esc(t.module) + '</b>' : '') + '<span>' + (t.graded === false ? 'Activité non notée' : 'Travail noté') + (t.group_size > 1 ? ' en ' + esc(groupWord(t.group_size)) : ' individuel') + '</span></div>' +
          '<h3>' + esc(t.title) + '</h3><div class="lpj-meta">' + (t.due_at ? '<span>À rendre avant le ' + esc(fmtDate(t.due_at)) + '</span>' : '') + '</div><div class="lpj-stats">' + state + '</div></article>';
      }).join('');
      var body = '<div class="lpj-bar"><h2>Vos travaux</h2></div>' + (list.length ? '<div class="lpj-grid">' + tiles + '</div>' : '<div class="lpj-empty"><b>Aucun travail pour l’instant</b>Votre enseignant n’a pas encore ouvert de travail pour votre classe.</div>');
      draw(page('LogiProjet', 'Bonjour ' + S.me.first_name, 'Vos travaux et activités', body));
    }).catch(fail);
  }
  function studentTravail(id) {
    loading();
    api('eleve/travaux').then(function (list) {
      var t = list.filter(function (x) { return x.id === id; })[0];
      if (!t) { throw new Error('Ce travail n’est pas disponible.'); }
      if (t.locked) { throw new Error('Ce travail n’a pas encore commencé : il s’ouvre le ' + fmtDate(t.date_debut) + '.'); }
      S.et = t; S.file = null; S.mates = null;
      if (t.can_submit && (t.can_group || !t.depot)) {
        return api('eleve/travaux/' + id + '/camarades').then(function (m) { S.mates = m; });
      }
    }).then(drawStudentTravail).catch(fail);
  }
  /* ---------- Le travail vu comme le document Word (apprentis et aperçu enseignant) ---------- */
  var NUMW = { 2: 'deux', 3: 'trois', 4: 'quatre', 5: 'cinq', 6: 'six' };
  function groupWord(n) { return n === 1 ? 'travail individuel' : (n === 2 ? 'duo' : (n === 3 ? 'trio' : 'groupe de ' + n)); }
  function fillVars(s, t) {
    var v = t.vars || {};
    s = String(s == null ? '' : s).replace(/\{[a-z_]+\}/g, function (k) { return v[k] !== undefined ? v[k] : k; });
    // Le document Word renvoie aux pages : sur le site, tout est sur la même page.
    return s.replace(/de la page 3/g, 'ci-dessous').replace(/de la page 1/g, 'ci-dessus').replace(/\(voir page 3\)/g, '(voir la grille ci-dessous)');
  }
  function rich(s, t) {
    return esc(fillVars(s, t)).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  }
  function splitPipe(line) {
    var i = line.indexOf('|');
    return i < 0 ? [line, ''] : [line.slice(0, i).trim(), line.slice(i + 1).trim()];
  }
  function dh(n, title, t) { return '<div class="lpj-d-h"><span>' + n + '</span><h3>' + (t ? rich(title, t) : esc(title)) + '</h3></div>'; }
  function titres(t) {
    var g = t.graded !== false;
    return Object.assign({ mission: 'Votre mission', contenu: 'Ce que vous devez présenter', plan: 'Le plan de votre présentation (8 à 12 slides)', page2: 'Comment réaliser votre présentation',
      regles: 'Les règles de réalisation', etapes: 'Les étapes de travail en classe', rendu: 'Le rendu', jourj: 'Le jour de la présentation', checklist: 'Check-list avant de rendre',
      grille: g ? 'Grille d’évaluation de la présentation' : 'Critères de réussite' }, (t.consigne && t.consigne.titres) || {});
  }
  function bullets(list, t) { return '<ul class="lpj-d-ul">' + (list || []).map(function (x) { return '<li>' + rich(x, t) + '</li>'; }).join('') + '</ul>'; }
  function stampHtml(top, bottom) { return '<div class="lpj-stamp"><b>' + esc(top) + '</b><span>' + esc(bottom) + '</span></div>'; }

  function docHtml(t, d) {
    var c = t.consigne;
    var val = d && d.status === 'valide';
    var gsize = t.group_size || 2;
    var names = d ? d.members.map(function (m) { return m.name; }) : [];
    var periodes = c && c.etapes ? c.etapes.length : 0;
    var h = '', sec = 0, T = titres(t), graded = t.graded !== false;
    var has = function (k) { return c && c[k] && c[k].length; };
    if (!c) {
      var g0 = gridHtml(t, d);
      return '<section class="lpj-doc"><div class="lpj-d-box">' + (t.consignes ? '<p class="lpj-pre">' + esc(t.consignes) + '</p>' : '<p class="lpj-muted">Voir la consigne donnée en classe.</p>') + '</div></section>' + (g0 ? '<section class="lpj-doc">' + g0 + '</section>' : '');
    }
    // Encadré jaune « note du groupe »
    if (c.note) {
      h += graded ? '<div class="lpj-d-note"><div class="lpj-d-note-big">1 – 6<small>note du ' + (gsize === 1 ? 'travail' : esc(groupWord(gsize).replace(/^groupe de \d+$/, 'groupe'))) + '</small></div><p>' + rich(c.note, t) + '</p></div>'
        : '<div class="lpj-d-note is-formatif"><div class="lpj-d-note-big">Formatif<small>non noté</small></div><p>' + rich(c.note, t) + '</p></div>';
    }
    // Cartouche
    var cells = [];
    for (var i = 0; i < Math.max(gsize, names.length); i++) { cells.push(['Apprenti·e ' + (i + 1), names[i] || '']); }
    cells.push(['Classe', (t.group_titles || []).join(', ')]);
    var dates = [['Date de début', fillVars('{date_debut}', t)], ['Date de fin · rendu du fichier', fillVars('{date_fin}', t)], ['Durée', periodes ? periodes + ' période' + (periodes > 1 ? 's' : '') + ' en classe' : '']];
    var row = function (list) { return '<div class="lpj-d-ids" style="grid-template-columns:repeat(' + list.length + ',1fr)">' + list.map(function (x) { return '<div><label>' + esc(x[0]) + '</label><b>' + esc(x[1]) + '</b></div>'; }).join('') + '</div>'; };
    h += '<div class="lpj-d-cart">' + row(cells) + row(dates) + '</div>';
    // 1 · Mission
    if (has('mission')) {
      h += dh(++sec, T.mission, t) + '<div class="lpj-d-box">' + c.mission.map(function (p) { return '<p>' + rich(p, t) + '</p>'; }).join('') + '</div>';
    }
    // 2 · Ce que vous devez présenter
    var introL = c.intro_lecons !== undefined ? c.intro_lecons : 'Votre présentation couvre **les {n} leçons du module {module}** sur logiprof :';
    var introA = c.intro_actions !== undefined ? c.intro_actions : 'Pour **chaque leçon**, votre présentation doit faire ces {k} choses :';
    if (t.lessons.length || has('actions')) { h += dh(++sec, T.contenu, t); }
    if (t.lessons.length && introL) {
      h += '<p>' + rich(introL, t) + '</p>';
      h += '<div class="lpj-d-tiles lpj-d-lessons" style="grid-template-columns:repeat(' + Math.min(5, Math.max(1, t.lessons.length)) + ',1fr)">' + t.lessons.map(function (l) {
        var m = String(l.title).match(/^(\d+(?:\.\d+)*)\s+(.*)$/);
        return '<div><b>' + esc(m ? m[1] : '') + '</b>' + esc(m ? m[2] : l.title) + '</div>';
      }).join('') + '</div>';
    }
    if (has('actions')) {
      if (introA) { h += '<p>' + rich(introA.replace('{k}', String(c.actions.length)), t) + '</p>'; }
      h += '<div class="lpj-d-tiles lpj-d-actions" style="grid-template-columns:repeat(' + c.actions.length + ',1fr)">' + c.actions.map(function (a) {
        var p = splitPipe(a); return '<div><h4>' + rich(p[0], t) + '</h4><p>' + rich(p[1], t) + '</p></div>';
      }).join('') + '</div>';
    }
    // 3 · Plan
    if (has('plan')) {
      h += dh(++sec, T.plan, t) + '<div class="lpj-d-plan">' + c.plan.map(function (l) {
        var p = splitPipe(l); return '<div><span>' + rich(p[0], t) + '</span><p>' + rich(p[1], t) + '</p></div>';
      }).join('') + '</div>';
    }
    // 4 · Règles
    if (has('faire') || has('eviter')) {
      h += dh(++sec, T.regles, t) + '<div class="lpj-d-two">' + (has('faire') ? '<div class="lpj-d-tile is-g"><h4>✔ À faire</h4>' + bullets(c.faire, t) + '</div>' : '') + (has('eviter') ? '<div class="lpj-d-tile is-r"><h4>✘ À éviter</h4>' + bullets(c.eviter, t) + '</div>' : '') + '</div>';
    }
    // 5 · Étapes
    if (periodes) {
      h += dh(++sec, T.etapes + ' (' + periodes + ' période' + (periodes > 1 ? 's' : '') + ')') + '<div class="lpj-d-steps">' + c.etapes.map(function (e, i) {
        return '<div><span>Période ' + (i + 1) + '</span><p>' + rich(e, t) + '</p></div>';
      }).join('') + '</div>';
    }
    // 6 · Rendu et 7 · Jour J
    var two = '';
    two += '<div class="lpj-d-tile is-y"><h4>' + (++sec) + ' · ' + esc(T.rendu) + '</h4>' + bullets(c.rendu, t) + '<p class="lpj-d-small">Formats acceptés : <b>' + esc(t.formats_label || 'PowerPoint (.pptx)') + '</b></p></div>';
    if (has('jourj')) { two += '<div class="lpj-d-tile is-y"><h4>' + (++sec) + ' · ' + esc(T.jourj) + '</h4>' + bullets(c.jourj, t) + '</div>'; }
    h += '<div class="lpj-d-two">' + two + '</div>';
    // 8 · Check-list
    if (c.checklist && c.checklist.length) {
      var half = Math.ceil(c.checklist.length / 2);
      var col = function (list) { return '<div>' + list.map(function (x) { return '<label class="lpj-d-check"><input type="checkbox"> <span>' + rich(x, t) + '</span></label>'; }).join('') + '</div>'; };
      h += dh(++sec, T.checklist, t) + '<div class="lpj-d-tile is-k lpj-d-two">' + col(c.checklist.slice(0, half)) + col(c.checklist.slice(half)) + '</div>';
    }
    if (t.consigne_url) { h += '<p style="margin-top:14px"><a class="lpj-btn" href="' + esc(t.consigne_url) + '" target="_blank" rel="noopener">Ouvrir le document de consigne (PDF)</a></p>'; }
    var g = gridHtml(t, d);
    return '<section class="lpj-doc">' + h + '</section>' + (g ? '<section class="lpj-doc">' + g + '</section>' : '');
  }

  /* Grille d'évaluation, comme la page 3 du document Word ; cochée une fois le travail validé. */
  function gridHtml(t, d) {
    if (!t.grille || !t.grille.length) { return ''; }
    var graded = t.graded !== false, T = titres(t);
    var val = d && d.status === 'valide';
    var sc = val ? (d.scores || {}) : {};
    var box = function (on) { return '<span class="lpj-box' + (on ? ' on' : '') + '"></span>'; };
    var rows = blocs(t.grille).map(function (b) {
      return '<tr class="blk"><td colspan="4">' + esc(b.name) + (graded ? '<span>' + 2 * b.items.length + ' pts</span>' : '') + '</td></tr>' + b.items.map(function (it) {
        var s = sc[it.c.id] || {}, v = s.score === null || s.score === undefined ? -1 : parseInt(s.score, 10);
        var m = String(it.c.label).match(/^(\d+(?:\.\d+)* [^:]+)( : .*)$/);
        return '<tr><td>' + (m ? '<strong>' + esc(m[1]) + '</strong>' + esc(m[2]) : esc(it.c.label)) + (val && s.comment ? '<div class="cm">' + esc(s.comment) + '</div>' : '') + '</td>' +
          [0, 1, 2].map(function (x) { return '<td class="c">' + box(v === x) + '</td>'; }).join('') + '</tr>';
      }).join('');
    }).join('');
    var conv = t.conversion || [];
    var oral = val ? (d.oral || {}) : {};
    var bonus = val ? oralBonusOf(t, d) : null;
    var fb = val ? (d.feedback || {}) : {};
    var head = '<div class="lpj-d-gridhead"><div><small>' + (graded ? 'Travail noté' : 'Activité non notée') + '</small><h3>' + esc(T.grille) + '</h3></div>' + (graded ? stampHtml('/ ' + t.total, 'points') : stampHtml('FORMATIF', 'non noté')) + '</div>';
    if (!graded) {
      return head + '<p class="lpj-d-small">Chaque critère : <b>0</b> = pas encore · <b>1</b> = en partie · <b>2</b> = réussi.</p>' +
        '<table class="lpj-d-grid"><thead><tr><th>Critère</th><th class="c">0</th><th class="c">1</th><th class="c">2</th></tr></thead><tbody>' + rows + '</tbody></table>' +
        '<div class="lpj-d-comment"><label>Retour de l’enseignant</label>' + (fb.points_forts ? '<p><b>Points forts :</b> ' + esc(fb.points_forts) + '</p>' : '') + (fb.piste ? '<p><b>Piste d’amélioration :</b> ' + esc(fb.piste) + '</p>' : '') + '</div>';
    }
    return head +
      '<p class="lpj-d-small">Chaque critère : <b>0</b> = absent ou faux · <b>1</b> = partiel · <b>2</b> = atteint. Une seule case par ligne.</p>' +
      '<table class="lpj-d-grid"><thead><tr><th>Critère</th><th class="c">0</th><th class="c">1</th><th class="c">2</th></tr></thead><tbody>' + rows +
      '<tr class="tot"><td colspan="2">Total du support</td><td colspan="2" class="r">' + (val ? fmtN(d.points) + ' ' : '') + '/ ' + t.total + '</td></tr></tbody></table>' +
      '<div class="lpj-d-two lpj-d-bottom"><div><h4>Conversion points → note</h4><table class="lpj-d-conv"><tr><th>Points</th>' + conv.map(function (r) { return '<td class="' + (r.note >= 4 ? 'g4' : '') + '">' + (r.min === r.max ? r.min : r.min + '-' + r.max) + '</td>'; }).join('') + '</tr><tr><th>Note</th>' +
      conv.map(function (r) { return '<td class="' + (r.note >= 4 ? 'g4' : '') + '">' + fmtN(r.note) + '</td>'; }).join('') + '</tr></table><p class="lpj-d-small">Note = points ÷ ' + t.total + ' × 5 + 1, arrondie au demi-point.</p></div>' +
      (t.oral_bonus ? '<div class="lpj-d-tile is-y"><h4>Bonus oral · seulement si tiré au sort</h4>' + ORAL.map(function (o) {
        var v = oral.presented && oral[o[0]] !== null && oral[o[0]] !== undefined ? parseInt(oral[o[0]], 10) : -1;
        return '<div class="lpj-d-orow"><span>' + o[1] + '</span><span>' + [0, 1, 2].map(function (x) { return box(v === x) + ' ' + x; }).join('&nbsp; ') + '</span></div>';
      }).join('') + '<p class="lpj-d-small">4 / 6 ou plus : <b>+0.5</b> sur la note (maximum 6).</p></div>' : '<div></div>') + '</div>' +
      '<div class="lpj-d-result"><div><label>Points</label><b>' + (val ? fmtN(d.points) : '') + ' / ' + t.total + '</b></div><div><label>Note du support</label><b>' + (val ? fmtN(d.note) : '') + '</b></div>' +
      '<div><label>Bonus oral</label><b>+ ' + (bonus ? fmtN(bonus) : '') + '</b></div>' + (val && d.retard_jours && graded ? '<div class="is-late"><label>Retard ' + d.retard_jours + ' j</label><b>' + (d.retard_excuse ? 'excusé' : (d.retard_jours > 14 ? 'note 1' : penaltyLabel(d.retard_jours))) + '</b></div>' : '') + '<div class="is-final"><label>Note finale</label><b>' + (val ? fmtN(d.note_finale) : '') + '</b></div></div>' +
      '<div class="lpj-d-comment"><label>Points forts et piste d’amélioration</label>' + (fb.points_forts ? '<p><b>Points forts :</b> ' + esc(fb.points_forts) + '</p>' : '') + (fb.piste ? '<p><b>Piste d’amélioration :</b> ' + esc(fb.piste) + '</p>' : '') + '</div>';
  }

  function docBand(t) {
    var gsize = t.group_size || 2;
    return {
      kicker: (gsize > 1 ? 'Travail en ' + groupWord(gsize) + (t.allow_solo ? ' ou seul·e' : '') : 'Travail individuel') + (t.module ? ' · Module ' + t.module : ''),
      stamp: t.graded === false ? stampHtml('ACTIVITÉ', 'non notée') : stampHtml('TRAVAIL NOTÉ', 'note sur 6')
    };
  }

  function previewTravail(id) {
    loading();
    api('travaux/' + id).then(function (t) {
      S.travail = t;
      var b = docBand(t);
      var body = '<div class="lpj-crumb"><a href="#/" data-go="#/">Vos travaux</a> / <a href="#/travail/' + t.id + '" data-go="#/travail/' + t.id + '">' + esc(t.title) + '</a> / Aperçu apprenti</div>' +
        '<div class="lpj-alert is-b"><b>Aperçu :</b> voici exactement ce que voient les apprentis (sans leur zone de dépôt). <button class="lpj-btn lpj-btn-sm" data-go="#/travail/' + t.id + '/modifier" style="margin-left:8px">Modifier la consigne</button></div>' + docHtml(t, null);
      draw(page(b.kicker, t.title, t.course_title || '', body, b.stamp));
    }).catch(fail);
  }

  function drawStudentTravail() {
    var t = S.et, d = t.depot, val = d && d.status === 'valide';
    var gsize = t.group_size || 2;
    var need = gsize - 1;
    var top = '';
    if (val && t.graded === false) {
      top += '<div class="lpj-bignote is-formatif"><b>✓</b><div><div style="font-weight:700;font-size:17px">Votre enseignant a corrigé votre travail</div><div class="lpj-muted">Activité non notée. Ses commentaires sont dans les critères de réussite, en bas de la page.</div></div></div>';
    } else if (val) {
      var bonus = oralBonusOf(t, d), pt = penaltyText(t, d);
      top += '<div class="lpj-bignote"><b>' + fmtN(d.note_finale) + '</b><div><div style="font-weight:700;font-size:17px">Votre note</div><div class="lpj-muted">' + fmtN(d.points) + ' / ' + t.total + ' points · note du support ' + fmtN(d.note) + (bonus ? ' · bonus oral +' + bonus : '') + (pt ? ' · ' + pt : '') + '. La grille corrigée et le retour de votre enseignant sont en bas de la page.</div></div></div>';
    }
    var depotCard = '';
    var gw = esc(groupWord(gsize));
    var remis = d && d.status !== 'groupe';
    if (remis) {
      depotCard = '<section class="lpj-card lpj-d-deposit lpj-d-done"><h3><span class="lpj-num">✓</span>Travail remis</h3>' +
        (gsize > 1 ? '<p><b>' + (d.members.length > 1 ? 'Groupe :</b> ' + esc(d.members.map(function (m) { return m.name; }).join(' & ')) : 'Travail réalisé seul·e</b>') + '</p>' : '') +
        '<p><span class="lpj-file-locked" title="Le travail est remis : il ne peut plus être ouvert">🔒 ' + esc(d.file_name) + '</span> · ' + size(d.file_size) + (d.slide_count ? ' · ' + d.slide_count + ' slides' : '') +
        '<br><span class="lpj-muted">Remis le ' + esc(fmtDate(d.submitted_at)) + ' par ' + esc(d.submitted_by) + '</span>' + (d.retard_jours ? ' <span class="lpj-badge lpj-late">' + d.retard_jours + ' j de retard' + (t.graded === false ? '' : (d.retard_excuse ? ' · excusé' : ' · ' + penaltyLabel(d.retard_jours))) + '</span>' : '') + '</p>' +
        (val ? '' : '<div class="lpj-alert is-g" style="margin:0">Votre travail est bien remis. Il ne peut plus être ouvert ni remplacé. Si vous devez le corriger, demandez à votre enseignant de vous le rendre.</div>') + '</section>';
    } else if (!t.can_submit) {
      depotCard = '<section class="lpj-card lpj-d-deposit"><h3>Remise</h3><div class="lpj-alert is-r" style="margin:0">Les remises sont fermées pour ce travail' + (d ? ' et votre travail n’a pas été remis' : '') + '. Contactez votre enseignant.</div>' +
        (d && d.draft ? '<p style="margin-top:10px">Votre fichier de travail : <a href="' + esc(d.draft.url) + '">' + esc(d.draft.name) + '</a></p>' : '') + '</section>';
    } else {
      var n = 0;
      var alone = d && d.members.length === 1;
      if (need > 0 && alone) {
        n++;
        depotCard += '<section class="lpj-card lpj-d-deposit lpj-d-group"><h3><span class="lpj-num">✓</span>Vous travaillez seul·e</h3>' +
          '<p class="lpj-muted" style="margin:0 0 10px">Choix enregistré le ' + esc(fmtDate(d.submitted_at)) + '. Vous pouvez encore rejoindre des camarades tant que le travail n’est pas remis.</p>' +
          (t.can_group ? '<details class="lpj-d-edit"><summary class="lpj-btn lpj-btn-sm" style="display:inline-flex">Travailler en groupe</summary><div style="margin-top:12px">' + partnerSelects(need, []) +
            '<div class="lpj-bar" style="margin:8px 0 0"><button class="lpj-btn lpj-btn-sm lpj-btn-yellow" data-act="save-group">Former le groupe</button>' +
            (d.draft ? '' : '<button class="lpj-btn lpj-btn-sm" data-act="leave-group" data-solo>Annuler mon choix</button>') + '</div>' +
            (d.draft ? '<small class="lpj-muted">Votre fichier de travail sera partagé avec vos camarades.</small>' : '') + '</div></details>' : '') + '</section>';
      } else if (need > 0 && d) {
        var others = d.members.filter(function (m) { return m.id !== S.me.id; });
        n++;
        depotCard += '<section class="lpj-card lpj-d-deposit lpj-d-group"><h3><span class="lpj-num">✓</span>Votre ' + gw + ' est enregistré</h3>' +
          '<p><b>Groupe :</b> ' + esc(d.members.map(function (m) { return m.name; }).join(' & ')) + '<br><span class="lpj-muted">Enregistré le ' + esc(fmtDate(d.submitted_at)) + ' par ' + esc(d.submitted_by) + '</span></p>' +
          (t.can_group ? '<details class="lpj-d-edit"><summary class="lpj-btn lpj-btn-sm" style="display:inline-flex">Modifier le groupe</summary><div style="margin-top:12px">' + partnerSelects(need, others) +
            '<div class="lpj-bar" style="margin:8px 0 0"><button class="lpj-btn lpj-btn-sm lpj-btn-yellow" data-act="save-group">Enregistrer les changements</button><button class="lpj-btn lpj-btn-sm lpj-btn-danger" data-act="leave-group">Quitter le groupe</button></div></div></details>' : '') + '</section>';
      } else if (need > 0) {
        var mates = S.mates || [];
        n++;
        depotCard += '<section class="lpj-card lpj-d-deposit lpj-d-group"><h3><span class="lpj-num">1</span>Formez votre ' + gw + '</h3>' +
          '<div class="lpj-field"><span>' + (need === 1 ? 'Votre partenaire' : 'Vos ' + need + ' camarades') + '</span>' + partnerSelects(need, []) +
          '<small>Un seul groupe par apprenti pour ce travail. Vos camarades verront aussi le travail et ' + (t.graded === false ? 'le retour' : 'la note') + '.' + (t.allow_solo ? ' Vous pouvez laisser des cases vides si votre groupe est incomplet.' : '') + (mates.length ? '' : ' Aucun camarade disponible : ils sont peut-être déjà dans un autre groupe.') + '</small></div>' +
          '<div class="lpj-bar" style="margin:0"><button class="lpj-btn" data-act="save-group">Enregistrer le groupe</button>' +
          (t.allow_solo ? '<button class="lpj-btn" data-act="go-solo">Je travaille seul·e</button>' : '') + '</div>' +
          (t.allow_solo ? '' : '<small class="lpj-muted" style="display:block;margin-top:8px">Ce travail se fait obligatoirement en ' + gw + '.</small>') + '</section>';
      }
      var dr = d && d.draft;
      var lateNow = t.retard_actuel || 0;
      depotCard += '<section class="lpj-card lpj-d-deposit lpj-d-work"><h3><span class="lpj-num">' + (n ? '2' : '↑') + '</span>Votre travail</h3>' +
        (lateNow ? '<div class="lpj-alert is-r" style="margin:0 0 12px"><b>La date de remise est dépassée depuis ' + lateNow + ' jour' + (lateNow > 1 ? 's' : '') + '.</b> Vous pouvez encore remettre votre travail' +
          (t.graded === false ? ', il sera signalé en retard.' : ', avec une pénalité : <b>−0.5</b> de 1 à 7 jours, <b>−1</b> de 8 à 14 jours, <b>note 1</b> après 14 jours. Remettez-le au plus vite.') + '</div>' : '') +
        (dr ? '<div class="lpj-draft"><div><b>Fichier de travail</b> · pas encore remis<br><a href="' + esc(dr.url) + '" data-draft-link>' + esc(dr.name) + '</a> · ' + size(dr.size) +
            '<br><span class="lpj-muted">Enregistré le ' + esc(fmtDate(dr.at)) + ' par ' + esc(dr.by) + '</span></div>' +
            '<a class="lpj-btn lpj-btn-sm" href="' + esc(dr.url) + '">Télécharger pour continuer</a></div>' :
          '<p class="lpj-muted" style="margin:0 0 10px">Vous pouvez enregistrer votre fichier en cours de travail' + (need > 0 && !alone ? ' : chaque membre du ' + gw + ' pourra le récupérer pour continuer, par exemple si quelqu’un est absent' : ' et le reprendre plus tard') + '. Il n’est pas remis tant que vous ne cliquez pas sur « Remettre le travail ».</p>') +
        '<div class="lpj-field"><span>' + (dr ? 'Nouvelle version' : 'Votre fichier') + ' (' + esc(t.formats_label) + ')</span>' + dropZone() + '</div>' +
        '<div class="lpj-bar" style="margin:0"><button class="lpj-btn" data-act="save-draft">' + (dr ? 'Mettre à jour le fichier de travail' : 'Enregistrer comme fichier de travail') + '</button>' +
        '<button class="lpj-btn lpj-btn-yellow" data-act="hand-in">Remettre le travail</button></div>' +
        '<small class="lpj-muted" style="display:block;margin-top:8px">' + (dr ? 'Sans nouveau fichier, « Remettre le travail » remet le fichier de travail ci-dessus. ' : '') + (alone || need === 0 ? 'Après la remise, vous ne pouvez plus ouvrir ni remplacer le fichier.' : 'Après la remise, plus personne du groupe ne peut ouvrir ni remplacer le fichier.') + '</small></section>';
    }
    var b = docBand(t);
    var body = '<div class="lpj-crumb"><a href="#/" data-go="#/">Vos travaux</a> / ' + esc(t.title) + '</div>' + top + (remis ? '' : dueBanner(t)) + depotCard + docHtml(t, d);
    draw(page(b.kicker, t.title, t.course_title || '', body, b.stamp));
  }
  function partnerSelects(need, current) {
    var mates = S.mates || [];
    var html = '';
    for (var i = 0; i < need; i++) {
      var cur = current[i] ? current[i].id : 0;
      html += '<select data-partner style="margin-bottom:6px"><option value="">' + (need > 1 ? 'Camarade ' + (i + 1) + '…' : 'Choisir…') + '</option>' +
        mates.map(function (m) { return '<option value="' + m.id + '"' + (m.id === cur ? ' selected' : '') + '>' + esc(m.name) + '</option>'; }).join('') + '</select>';
    }
    return html;
  }
  function chosenPartners() {
    var ids = [];
    root.querySelectorAll('[data-partner]').forEach(function (sel) { if (sel.value) { ids.push(sel.value); } });
    return ids;
  }
  function checkPartners(ids) {
    var t = S.et, need = (t.group_size || 2) - 1;
    if (ids.length !== (new Set(ids)).size) { toast('Vous avez choisi deux fois le même camarade.', true); return false; }
    if (ids.length < need && !t.allow_solo) { toast(need === 1 ? 'Choisissez votre partenaire.' : 'Choisissez vos ' + need + ' camarades.', true); return false; }
    return true;
  }
  function reloadStudent(nt, msg) {
    S.et = nt;
    var after = nt.can_group ? api('eleve/travaux/' + nt.id + '/camarades').then(function (m) { S.mates = m; }) : Promise.resolve();
    return after.then(function () { toast(msg); drawStudentTravail(); });
  }
  function saveGroup() {
    var t = S.et, ids = chosenPartners();
    if (!ids.length) { toast('Choisissez au moins un camarade.', true); return; }
    if (!checkPartners(ids)) { return; }
    api('eleve/travaux/' + t.id + '/groupe', { method: 'POST', body: { partner_ids: ids.map(Number) } })
      .then(function (nt) { return reloadStudent(nt, 'Groupe enregistré. Vous pourrez déposer le fichier plus tard.'); })
      .catch(function (e) { toast(e.message, true); if (/Rechargez/.test(e.message)) { studentTravail(t.id); } });
  }
  function goSolo() {
    var t = S.et;
    ask('Travailler seul·e ?', 'Vous ferez ce travail sans camarade. Vous pourrez encore rejoindre un groupe tant que le travail n’est pas remis.', 'Je travaille seul·e').then(function (ok) {
      if (!ok) { return; }
      api('eleve/travaux/' + t.id + '/groupe', { method: 'POST', body: { seul: true } })
        .then(function (nt) { return reloadStudent(nt, 'C’est noté : vous travaillez seul·e.'); })
        .catch(function (e) { toast(e.message, true); });
    });
  }
  function leaveGroup() {
    var t = S.et;
    if (t.depot && t.depot.members.length === 1) {
      return api('eleve/travaux/' + t.id + '/groupe', { method: 'DELETE' })
        .then(function (nt) { return reloadStudent(nt, 'Choix annulé : vous pouvez former un groupe.'); })
        .catch(function (e) { toast(e.message, true); });
    }
    ask('Quitter ce groupe ?', 'Vous pourrez rejoindre ou former un autre groupe pour ce travail. ' + ((t.depot.members.length > 2) ? 'Vos camarades restent ensemble.' : 'Votre camarade sera de nouveau libre.'), 'Quitter le groupe', true).then(function (ok) {
      if (!ok) { return; }
      api('eleve/travaux/' + t.id + '/groupe', { method: 'DELETE' })
        .then(function (nt) { return reloadStudent(nt, 'Vous avez quitté le groupe.'); })
        .catch(function (e) { toast(e.message, true); });
    });
  }
  function dropZone() {
    var t = S.et || {};
    return '<label class="lpj-drop" data-drop><input type="file" accept="' + esc(t.accept || '.pptx') + '" class="lpj-sr" data-file>' +
      '<b data-file-label>' + (S.file ? esc(S.file.name) : 'Cliquez ou glissez votre fichier ici') + '</b><small>' + esc(t.formats_label || 'PowerPoint (.pptx)') + ' · ' + S.me.max_mb + ' Mo maximum</small></label>';
  }
  function setFile(file) {
    if (!file) { return; }
    var okExt = String((S.et && S.et.accept) || '.pptx').split(',');
    var ext = '.' + String(file.name.split('.').pop()).toLowerCase();
    if (okExt.indexOf(ext) < 0) { toast('Ce format n’est pas accepté. Formats acceptés : ' + ((S.et && S.et.formats_label) || 'PowerPoint (.pptx)') + '.', true); return; }
    if (file.size > S.me.max_mb * 1048576) { toast('Le fichier dépasse ' + S.me.max_mb + ' Mo. Compressez les images dans PowerPoint.', true); return; }
    S.file = file;
    root.querySelectorAll('[data-file-label]').forEach(function (el) { el.textContent = file.name + ' · ' + size(file.size); });
  }
  function fileForm() {
    var t = S.et, fd = new FormData();
    if (S.file) { fd.append('fichier', S.file); }
    if (!t.depot && (t.group_size || 2) > 1) {
      var ids = chosenPartners();
      if (!checkPartners(ids)) { return null; }
      ids.forEach(function (id) { fd.append('partner_ids[]', id); });
      if (!ids.length) { fd.append('partner_ids[]', '0'); }
    }
    return fd;
  }
  function busyBtn(act, label) {
    var btn = root.querySelector('[data-act="' + act + '"]');
    var text = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="lpj-spinner"></span> ' + label; }
    return function () { if (btn) { btn.disabled = false; btn.textContent = text; } };
  }
  function saveDraft() {
    var t = S.et;
    if (!S.file) { toast('Choisissez d’abord votre fichier.', true); return; }
    var fd = fileForm();
    if (!fd) { return; }
    var undo = busyBtn('save-draft', 'Enregistrement…');
    api('eleve/travaux/' + t.id + '/brouillon', { method: 'POST', form: fd }).then(function (nt) {
      S.file = null; return reloadStudent(nt, 'Fichier de travail enregistré. Il n’est pas encore remis.');
    }).catch(function (e) { toast(e.message, true); undo(); });
  }
  function handIn() {
    var t = S.et, dr = t.depot && t.depot.draft;
    if (!S.file && !dr) { toast('Choisissez le fichier à remettre.', true); return; }
    var fd = S.file ? fileForm() : null;
    if (S.file && !fd) { return; }
    var name = S.file ? S.file.name : dr.name;
    var lateTxt = t.retard_actuel && t.graded !== false ? ' Remise en retard (' + t.retard_actuel + ' j) : pénalité ' + (t.retard_actuel > 14 ? 'note 1' : penaltyLabel(t.retard_actuel)) + '.' : '';
    ask('Remettre le travail ?', 'Fichier remis : « ' + name + ' ». Après la remise, plus personne' + ((t.group_size || 2) > 1 ? ' du groupe' : '') + ' ne pourra ouvrir ni remplacer ce fichier.' + lateTxt, 'Remettre le travail').then(function (ok) {
      if (!ok) { return; }
      var undo = busyBtn('hand-in', 'Remise en cours…');
      var req = S.file ? api('eleve/travaux/' + t.id + '/depot', { method: 'POST', form: fd }) : api('eleve/travaux/' + t.id + '/remettre', { method: 'POST', body: {} });
      req.then(function (nt) { S.file = null; return reloadStudent(nt, 'Travail remis. Bravo !'); }).catch(function (e) { toast(e.message, true); undo(); });
    });
  }

  /* =====================================================================
     Événements et navigation
     ===================================================================== */
  root.addEventListener('click', function (e) {
    var seg = e.target.closest('[data-seg]');
    if (seg && S.depot) {
      if (S.depot.status === 'valide') { toast('Retirez la publication pour modifier.', true); return; }
      var name = seg.getAttribute('data-seg'), v = parseInt(seg.getAttribute('data-v'), 10);
      if (name.indexOf('c:') === 0) {
        var id = name.slice(2);
        S.depot.scores[id] = S.depot.scores[id] || { score: null, comment: '' };
        S.depot.scores[id].score = v;
      } else { S.depot.oral[name.slice(2)] = v; }
      S.dirty = true;
      seg.parentNode.querySelectorAll('button').forEach(function (b) { var on = b === seg; b.classList.toggle('is-on', on); b.setAttribute('aria-checked', on); });
      if (name.indexOf('o:') === 0) { drawDepot(); } else { refreshSum(); }
      return;
    }
    var lk = e.target.closest('[data-locked]');
    if (lk) { var lt = (S.elist || []).filter(function (x) { return String(x.id) === lk.getAttribute('data-locked'); })[0]; toast('Ce travail n’a pas encore commencé. Il s’ouvre le ' + (lt ? fmtDate(lt.date_debut) : '…') + '.'); return; }
    var ms = e.target.closest('[data-mser]');
    if (ms) { S.mser = ms.getAttribute('data-mser'); root.querySelectorAll('[data-mser]').forEach(function (x) { x.classList.toggle('is-on', x === ms); }); drawModeleList(); return; }
    var hv = e.target.closest('[data-help-video]');
    if (hv) { e.preventDefault(); var dm = hv.closest('details'); if (dm) { dm.open = false; } openVideo(hv.getAttribute('data-help-video')); return; }
    var nav = e.target.closest('[data-go]');
    var act = e.target.closest('[data-act]');
    if (act) {
      e.preventDefault();
      var a = act.getAttribute('data-act');
      if (act.disabled) { return; }
      if (a === 'add-crit') {
        var bloc = act.getAttribute('data-bloc'), idx = -1;
        S.form.grille.forEach(function (c, i) { if (c.bloc === bloc) { idx = i; } });
        S.form.grille.splice(idx + 1, 0, { id: 'n' + Date.now().toString(36), bloc: bloc, label: 'Nouveau critère' });
        S.dirty = true; drawForm();
      } else if (a === 'del-crit') {
        S.form.grille.splice(parseInt(act.getAttribute('data-gi'), 10), 1); S.dirty = true; drawForm();
      } else if (a === 'add-bloc') {
        var letter = String.fromCharCode(65 + blocs(S.form.grille).length);
        S.form.grille.push({ id: 'n' + Date.now().toString(36), bloc: letter + ' · Nouveau bloc', label: 'Nouveau critère' }); S.dirty = true; drawForm();
      } else if (a === 'gen-grille') { genGrille(); }
      else if (a === 'gen-consigne') { genConsigne(); }
      else if (a === 'save-travail') { saveTravail(); }
      else if (a === 'del-travail') {
        ask('Supprimer ce travail ?', 'Le travail, tous les dépôts et tous les fichiers PowerPoint des apprentis seront supprimés définitivement.', 'Supprimer', true).then(function (ok) {
          if (ok) { api('travaux/' + S.form.id, { method: 'DELETE' }).then(function () { S.dirty = false; toast('Travail supprimé.'); go('#/'); }).catch(function (er) { toast(er.message, true); }); }
        });
      } else if (a === 'open-travail') {
        api('travaux/' + S.travail.id, { method: 'PUT', body: { status: 'ouvert' } }).then(function (t) { S.travail = t; toast('Travail ouvert : les apprentis peuvent déposer.'); drawTravail(); }).catch(function (er) { toast(er.message, true); });
      } else if (a === 'export-travail') { exportTravail();
      } else if (a === 'save-modele') { saveModele();
      } else if (a === 'use-modele') { useModele(act.getAttribute('data-id'));
      } else if (a === 'share-modele') { updModele(act.getAttribute('data-id'), { shared: act.getAttribute('data-v') === '1' });
      } else if (a === 'ren-modele') { renModele(act.getAttribute('data-id'));
      } else if (a === 'del-modele') { delModele(act.getAttribute('data-id'));
      } else if (a === 'ai-all') { aiAll(); }
      else if (a === 'ai-one') { aiOne(); }
      else if (a === 'save-depot') { saveDepot('save'); }
      else if (a === 'validate') {
        ask('Valider et publier ?', 'Les deux apprentis verront leur note, la grille avec vos commentaires et votre retour.', 'Valider et publier').then(function (ok) { if (ok) { saveDepot('validate'); } });
      } else if (a === 'unvalidate') { saveDepot('unvalidate'); }
      else if (a === 'del-depot') {
        var grp = S.depot.status === 'groupe';
        ask(grp ? 'Supprimer ce groupe ?' : 'Supprimer ce dépôt ?', grp ? 'Les membres seront à nouveau libres de former un groupe pour ce travail.' : 'Le fichier déposé et la correction seront supprimés. Les apprentis pourront déposer à nouveau.', 'Supprimer', true).then(function (ok) {
          if (ok) { api('depots/' + S.depot.id, { method: 'DELETE' }).then(function () { S.dirty = false; toast(grp ? 'Groupe supprimé.' : 'Dépôt supprimé.'); go('#/travail/' + S.depot.travail_id); }).catch(function (er) { toast(er.message, true); }); }
        });
      } else if (a === 'give-back') {
        ask('Rendre le travail ?', 'Le fichier remis redevient un fichier de travail : les apprentis pourront le rouvrir, le modifier et le remettre. La proposition de correction et vos notes sur ce dépôt seront effacées.', 'Rendre le travail').then(function (ok) {
          if (ok) { api('depots/' + S.depot.id, { method: 'PUT', body: { action: 'rendre' } }).then(function () { S.dirty = false; toast('Travail rendu : les apprentis peuvent à nouveau le modifier.'); go('#/travail/' + S.depot.travail_id); }).catch(function (er) { toast(er.message, true); }); }
        });
      } else if (a === 'go-solo') { goSolo();
      } else if (a === 'save-draft') { saveDraft();
      } else if (a === 'hand-in') { handIn();
      } else if (a === 'save-group') { saveGroup();
      } else if (a === 'leave-group') { leaveGroup(); }
      return;
    }
    if (nav) {
      e.preventDefault();
      var target = nav.getAttribute('data-go');
      if (S.dirty) {
        ask('Quitter sans enregistrer ?', 'Vos dernières modifications seront perdues.', 'Quitter', true).then(function (ok) { if (ok) { S.dirty = false; go(target); } });
      } else { go(target); }
    }
  });
  root.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('lpj-tile-click')) { e.preventDefault(); e.target.click(); }
  });
  root.addEventListener('input', function (e) {
    var el = e.target;
    if (el.hasAttribute('data-mq')) { S.mq = el.value; drawModeleList(); return; }
    if (S.form && root.querySelector('[data-act="save-travail"]') && (el.matches('[data-f],[data-gi],[data-blocname],[data-cf],[data-ct],[data-ci]') && el.type !== 'checkbox' && el.tagName !== 'SELECT')) { formInput(el); return; }
    if (S.depot && el.hasAttribute('data-comment')) {
      var id = el.getAttribute('data-comment');
      S.depot.scores[id] = S.depot.scores[id] || { score: null, comment: '' };
      S.depot.scores[id].comment = el.value; S.dirty = true; markDirty();
    } else if (S.depot && el.hasAttribute('data-fb')) {
      S.depot.feedback = S.depot.feedback || {};
      S.depot.feedback[el.getAttribute('data-fb')] = el.value; S.dirty = true; markDirty();
    }
  });
  function markDirty() { var m = root.querySelector('[data-dirty]'); if (m) { m.textContent = 'Non enregistré'; } }
  root.addEventListener('change', function (e) {
    var el = e.target;
    if (el.hasAttribute('data-file')) { setFile(el.files[0]); return; }
    if (el.hasAttribute('data-class-status') && S.travail) {
      var map = {}; root.querySelectorAll('[data-class-status]').forEach(function (x) { if (x.value) { map[x.getAttribute('data-class-status')] = x.value; } });
      api('travaux/' + S.travail.id, { method: 'PUT', body: { class_status: map } }).then(function (t) { S.travail = t; toast('Statut de la classe enregistré.'); drawTravail(); }).catch(function (er) { toast(er.message, true); drawTravail(); });
      return;
    }
    if (el.hasAttribute('data-class-start') && S.travail && !S.form) {
      var smap = {}; root.querySelectorAll('[data-class-start]').forEach(function (x) { smap[x.getAttribute('data-class-start')] = x.value; });
      api('travaux/' + S.travail.id, { method: 'PUT', body: { class_start: smap } }).then(function (t) { S.travail = t; toast('Date de début de la classe enregistrée.'); drawTravail(); }).catch(function (er) { toast(er.message, true); drawTravail(); });
      return;
    }
    if (el.hasAttribute('data-class-start') && S.form) { S.form.class_start = S.form.class_start || {}; S.form.class_start[el.getAttribute('data-class-start')] = el.value; S.dirty = true; return; }
    if (el.hasAttribute('data-class-due') && S.travail && !S.form) {
      var dmap = {}; root.querySelectorAll('[data-class-due]').forEach(function (x) { dmap[x.getAttribute('data-class-due')] = x.value; });
      api('travaux/' + S.travail.id, { method: 'PUT', body: { class_due: dmap } }).then(function (t) { S.travail = t; toast('Date de fin de la classe enregistrée.'); drawTravail(); }).catch(function (er) { toast(er.message, true); drawTravail(); });
      return;
    }
    if (el.hasAttribute('data-class-due') && S.form) { S.form.class_due = S.form.class_due || {}; S.form.class_due[el.getAttribute('data-class-due')] = el.value; S.dirty = true; return; }
    if (el.hasAttribute('data-mwho')) { S.mwho = el.value; drawModeleList(); return; }
    if (el.hasAttribute('data-import')) { importTravail(el.files[0]); el.value = ''; return; }
    if (el.hasAttribute('data-all-travaux')) { S.allT = el.checked; try { localStorage.setItem('lpj-tous', el.checked ? '1' : '0'); } catch (er) {} teacherHome(); return; }
    if (el.hasAttribute('data-corr-import')) { importCorrection(el.files[0]); el.value = ''; return; }
    if (el.hasAttribute('data-claude-pptx')) { var ex = root.querySelector('[data-claude-export]'); ex.href = S.travail.claude_export_url + (el.checked ? '&pptx=1' : ''); return; }
    if (S.form && root.querySelector('[data-act="save-travail"]') && (el.type === 'checkbox' || el.tagName === 'SELECT')) { formInput(el); return; }
    if (S.depot && el.hasAttribute('data-late-excuse')) { S.depot.retard_excuse = el.checked; S.dirty = true; drawDepot(); return; }
    if (S.depot && el.getAttribute('data-oral') === 'presented') { S.depot.oral.presented = el.checked; S.dirty = true; drawDepot(); }
  });
  ['dragover', 'dragleave', 'drop'].forEach(function (ev) {
    root.addEventListener(ev, function (e) {
      var z = e.target.closest && e.target.closest('[data-drop]');
      if (!z) { return; }
      e.preventDefault();
      z.classList.toggle('is-over', ev === 'dragover');
      if (ev === 'drop' && e.dataTransfer.files.length) { setFile(e.dataTransfer.files[0]); }
    });
  });
  window.addEventListener('beforeunload', function (e) { if (S.dirty || S.busy) { e.preventDefault(); e.returnValue = ''; } });

  function route() {
    var h = location.hash.replace(/^#/, '') || '/';
    var m;
    window.scrollTo(0, Math.min(window.scrollY, root.getBoundingClientRect().top + window.scrollY));
    if (!S.me.is_teacher) {
      S.depot = null; S.form = null;
      if ((m = h.match(/^\/t\/(\d+)/))) { return studentTravail(parseInt(m[1], 10)); }
      return studentHome();
    }
    S.form = null; S.depot = null;
    if (!/^\/travail\/\d+\/modifier/.test(h)) { S.importWarnings = null; S.fromModele = null; }
    if (!/^\/travail\/\d+$/.test(h)) { S.corrResult = null; }
    if (h === '/nouveau') { return editTravail(0); }
    if (h === '/modeles') { return showModeles(); }
    if ((m = h.match(/^\/travail\/(\d+)\/modifier/))) { return editTravail(parseInt(m[1], 10)); }
    if ((m = h.match(/^\/travail\/(\d+)\/apercu/))) { return previewTravail(parseInt(m[1], 10)); }
    if ((m = h.match(/^\/travail\/(\d+)/))) { if (!S.groups) { api('groups').then(function (g) { S.groups = g; }).catch(function () {}); } return showTravail(parseInt(m[1], 10)); }
    if ((m = h.match(/^\/depot\/(\d+)/))) { return showDepot(parseInt(m[1], 10)); }
    return teacherHome();
  }

  /* Pleine largeur, sauf dans le portail LogiOne qui gère lui-même la largeur. */
  if (root.closest('[class*="logione"],[id*="logione"]')) { root.classList.add('lpj-embedded'); }
  function setVw() { root.style.setProperty('--lpj-vw', document.documentElement.clientWidth + 'px'); }
  setVw();
  window.addEventListener('resize', setVw);

  api('me').then(function (me) {
    S.me = me;
    window.addEventListener('hashchange', route);
    route();
  }).catch(fail);
})();
