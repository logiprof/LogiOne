/*
 * LogiPlanning · planning des élèves (aux couleurs LogiOne).
 * Prochain cours en évidence, liste de deux mois ou calendrier du mois, recherche d'un module,
 * « À reprendre » et modules en autonomie. Les journées passées sont repliées.
 */
(function () {
	'use strict';

	var DAY_MS = 86400000;

	function pad(n) { return String(n).padStart(2, '0'); }
	function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function parse(value) { return new Date(value + 'T12:00:00'); }
	function monthStart(d) { return new Date(d.getFullYear(), d.getMonth(), 1); }
	function addMonths(d, n) { return new Date(d.getFullYear(), d.getMonth() + n, 1); }
	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}
	function fmt(d, opts) { return d.toLocaleDateString('fr-CH', opts); }
	function monthName(d) { return fmt(d, { month: 'long', year: 'numeric' }); }
	function longDate(value) { return fmt(parse(value), { weekday: 'long', day: 'numeric', month: 'long' }); }
	function relative(value, today) {
		var diff = Math.round((parse(value) - parse(today)) / DAY_MS);
		if (diff === 0) { return 'Aujourd’hui'; }
		if (diff === 1) { return 'Demain'; }
		if (diff > 1 && diff < 7) { return 'Dans ' + diff + ' jours'; }
		return '';
	}
	function icon(name) {
		var p = {
			cal: '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3.5 10h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
			clock: '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7.5V12l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
			user: '<circle cx="12" cy="8.5" r="3.6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 20a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
			pin: '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="2.2" fill="currentColor"/>',
			home: '<path d="M4 11.5 12 5l8 6.5V20h-5.5v-5h-5v5H4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
			chev: '<path d="M8 10l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
		}[name] || '';
		return '<svg viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>';
	}

	function progressBar(value, show) {
		if (!show) { return ''; }
		var v = Math.max(0, Math.min(100, Number(value || 0)));
		return '<span class="lpl-progress' + (v >= 100 ? ' is-done' : '') + '" role="img" aria-label="Progression ' + v + ' %"><i style="width:' + v + '%"></i></span><b class="lpl-pct">' + v + '%</b>';
	}

	function courseRow(course, show) {
		var img = course.image ? '<img src="' + esc(course.image) + '" alt="" loading="lazy">' : '<span class="lpl-thumb-empty" aria-hidden="true"></span>';
		return '<a class="lpl-course" href="' + esc(course.url) + '">' +
			'<span class="lpl-thumb">' + img + '</span>' +
			'<span class="lpl-course-text"><strong>' + esc(course.title) + '</strong>' + (course.summary ? '<small>' + esc(course.summary) + '</small>' : '') + '</span>' +
			'<span class="lpl-course-progress">' + progressBar(course.progress, show) + '</span>' +
		'</a>';
	}

	function courseList(courses, show, limit, moreLabel) {
		if (!courses || !courses.length) { return ''; }
		limit = limit || 4;
		var html = '<div class="lpl-courses">' + courses.map(function (c, i) {
			return '<div class="lpl-course-wrap"' + (i >= limit ? ' hidden data-lpl-extra' : '') + '>' + courseRow(c, show) + '</div>';
		}).join('') + '</div>';
		if (courses.length > limit) {
			html += '<button type="button" class="lpl-more" data-lpl-more data-more="' + esc(moreLabel + ' (' + (courses.length - limit) + ')') + '" data-less="Afficher moins">' + esc(moreLabel + ' (' + (courses.length - limit) + ')') + '</button>';
		}
		return html;
	}

	function stateBadge(day) {
		if (!day.state || day.state.key === 'none' || !day.state.label) { return ''; }
		return '<span class="lpl-state is-' + esc(day.state.key) + '">' + esc(day.state.label) + '</span>';
	}

	function chips(day) {
		var out = [];
		if (day.schedule) { out.push('<span class="lpl-chip">' + icon('clock') + esc(day.schedule) + '</span>'); }
		if (day.modality) { out.push('<span class="lpl-chip' + (/distance/i.test(day.modality) ? ' is-distance' : '') + '">' + icon(/distance/i.test(day.modality) ? 'home' : 'pin') + esc(day.modality) + (day.classroom ? ' · ' + esc(day.classroom) : '') + '</span>'); }
		if (day.teacher) { out.push('<span class="lpl-chip">' + icon('user') + esc(day.teacher) + '</span>'); }
		return out.length ? '<div class="lpl-chips">' + out.join('') + '</div>' : '';
	}

	function dayCard(day, today, show) {
		var d = parse(day.date);
		var past = day.date < today;
		var isToday = day.date === today;
		var rel = relative(day.date, today);
		var groups = day.groupDetails && day.groupDetails.length > 1 ? '<ul class="lpl-groups">' + day.groupDetails.map(function (g) {
			return '<li><strong>' + esc(g.group) + '</strong> ' + esc([g.teacher, g.classroom].filter(Boolean).join(' · ')) + '</li>';
		}).join('') + '</ul>' : '';
		var message = day.message ? '<div class="lpl-message" data-lpl-message>' + day.message + '</div>' : '';
		var body = day.isVacation ?
			'<p class="lpl-vacation">' + esc(day.message ? '' : 'Vacances : aucun cours prévu.') + '</p>' + message :
			chips(day) + groups + message + courseList(day.courses, show, 3, 'Afficher les autres cours') +
			(day.icsUrl ? '<a class="lpl-link" href="' + esc(day.icsUrl) + '">' + icon('cal') + 'Ajouter cette journée à mon agenda</a>' : '');

		return '<article class="lpl-day' + (past ? ' is-past' : '') + (isToday ? ' is-today' : '') + (day.isVacation ? ' is-vacation' : '') + '" id="lpl-day-' + esc(day.id) + '" data-lpl-day="' + esc(day.id) + '" style="--branch:' + esc(day.color || '#067030') + '">' +
			'<div class="lpl-date" aria-hidden="true"><span>' + esc(fmt(d, { weekday: 'short' })) + '</span><strong>' + d.getDate() + '</strong><span>' + esc(fmt(d, { month: 'short' })) + '</span></div>' +
			'<div class="lpl-day-main">' +
				'<' + (past ? 'button type="button" aria-expanded="false" data-lpl-toggle' : 'div') + ' class="lpl-day-head">' +
					'<span class="lpl-day-title"><span class="lpl-sr">' + esc(longDate(day.date)) + ' : </span>' + esc(day.branch || 'Journée de formation') + '</span>' +
					(rel ? '<span class="lpl-rel">' + esc(rel) + '</span>' : '') +
					stateBadge(day) +
					(past ? '<span class="lpl-chev">' + icon('chev') + '<span class="lpl-sr">Afficher le détail</span></span>' : '') +
				'</' + (past ? 'button' : 'div') + '>' +
				'<div class="lpl-day-body"' + (past ? ' hidden' : '') + '>' + body + '</div>' +
			'</div>' +
		'</article>';
	}

	function monthGrid(month, days, today) {
		var first = monthStart(month);
		var start = new Date(first.getFullYear(), first.getMonth(), 1 - ((first.getDay() + 6) % 7));
		var byDate = {};
		days.forEach(function (d) { (byDate[d.date] = byDate[d.date] || []).push(d); });
		var html = '<div class="lpl-weekdays">' + ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].map(function (w) { return '<span>' + w + '</span>'; }).join('') + '</div><div class="lpl-grid">';
		for (var i = 0; i < 42; i++) {
			var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
			var key = iso(d);
			var inMonth = d.getMonth() === month.getMonth();
			if (i >= 35 && !inMonth && d.getDate() < 8 && i % 7 === 0) { break; }
			html += '<div class="lpl-cell' + (inMonth ? '' : ' is-outside') + (key === today ? ' is-today' : '') + '"><span class="lpl-cell-num">' + d.getDate() + '</span>' +
				(byDate[key] || []).map(function (day) {
					return '<button type="button" class="lpl-event' + (day.isVacation ? ' is-vacation' : '') + '" data-lpl-goto="' + esc(day.id) + '" style="--branch:' + esc(day.color) + '"><span>' + esc(day.branch) + '</span>' + (day.schedule ? '<small>' + esc(day.schedule) + '</small>' : '') + '</button>';
				}).join('') +
			'</div>';
		}
		return html + '</div>';
	}

	function init(root) {
		var data;
		try { data = JSON.parse(root.getAttribute('data-logiplanning') || '{}'); } catch (e) { return; }
		var days = (data.days || []).slice().sort(function (a, b) { return a.date.localeCompare(b.date); });
		var show = data.showProgress !== false;
		var today = iso(new Date());
		var view = 'list';
		var current = monthStart(parse(today));
		var $ = function (sel) { return root.querySelector(sel); };
		var list = $('[data-lpl-list]'), grid = $('[data-lpl-grid]'), title = $('[data-lpl-title]');

		// Pleine largeur : largeur visible sans la barre de défilement.
		function setWidth() { document.documentElement.style.setProperty('--lpl-vw', document.documentElement.clientWidth + 'px'); }
		setWidth(); window.addEventListener('resize', setWidth);

		var teaching = days.filter(function (d) { return !d.isVacation; });
		var upcoming = teaching.filter(function (d) { return d.date >= today; });

		// Prochain cours, en grand dans le bandeau.
		var next = upcoming[0];
		var nextBox = $('[data-lpl-next]');
		if (nextBox) {
			nextBox.innerHTML = next ?
				'<div class="lpl-next-card" style="--branch:' + esc(next.color) + '"><span class="lpl-next-label">' + esc(relative(next.date, today) || 'Prochain cours') + ' · ' + esc(longDate(next.date)) + '</span>' +
				'<strong>' + esc(next.branch) + '</strong>' + chips(next) +
				'<div class="lpl-next-actions"><button type="button" class="lpl-btn lpl-btn-light" data-lpl-goto="' + esc(next.id) + '">Voir la journée</button>' +
				(next.icsUrl ? '<a class="lpl-link lpl-link-light" href="' + esc(next.icsUrl) + '">' + icon('cal') + 'Ajouter à mon agenda</a>' : '') + '</div></div>' :
				'<p class="lpl-next-none">Aucun cours à venir dans ce plan.</p>';
		}

		// Si le mois en cours n'a plus de cours, on commence au mois du prochain cours.
		function startMonth() {
			var m = monthStart(parse(today));
			var end = addMonths(m, 1);
			var hasCourse = upcoming.some(function (d) { var x = parse(d.date); return x >= m && x < end; });
			return !hasCourse && next ? monthStart(parse(next.date)) : m;
		}
		current = startMonth();

		function draw() {
			var end = addMonths(current, view === 'list' ? 2 : 1);
			var visible = days.filter(function (d) { var x = parse(d.date); return x >= current && x < end; });
			title.textContent = view === 'list' ? monthName(current) + ' – ' + monthName(addMonths(current, 1)) : monthName(current);
			if (view === 'list') {
				var html = '';
				var lastMonth = '';
				visible.forEach(function (d) {
					var m = monthName(parse(d.date));
					if (m !== lastMonth) { html += '<h3 class="lpl-month-title">' + esc(m) + '</h3>'; lastMonth = m; }
					html += dayCard(d, today, show);
				});
				list.innerHTML = html || '<div class="lpl-empty">Aucun cours planifié sur ces deux mois.</div>';
			}
			grid.innerHTML = view === 'calendar' ? monthGrid(current, days, today) : '';
			list.hidden = view !== 'list';
			grid.hidden = view !== 'calendar';
			clampMessages(list);
		}

		function clampMessages(scope) {
			scope.querySelectorAll('[data-lpl-message]').forEach(function (m) {
				if (m.closest('[hidden]') || m.dataset.done) { return; }
				m.dataset.done = '1';
				if (m.scrollHeight > 140) {
					m.classList.add('is-clamped');
					m.insertAdjacentHTML('afterend', '<button type="button" class="lpl-more" data-lpl-readmore>Lire la suite</button>');
				}
			});
		}

		function setView(v) {
			view = v;
			root.querySelectorAll('[data-lpl-view]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-lpl-view') === v ? 'true' : 'false'); });
			draw();
		}

		function goTo(id) {
			var day = days.filter(function (d) { return String(d.id) === String(id); })[0];
			if (!day) { return; }
			current = monthStart(parse(day.date));
			setView('list');
			var card = root.querySelector('#lpl-day-' + CSS.escape(String(id)));
			if (!card) { return; }
			var toggle = card.querySelector('[data-lpl-toggle]');
			if (toggle && toggle.getAttribute('aria-expanded') !== 'true') { toggle.click(); }
			card.classList.add('is-flash');
			setTimeout(function () { card.classList.remove('is-flash'); }, 1600);
			card.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		// À reprendre : chaque cours non terminé d'une journée passée (une seule fois par cours).
		var overdueBox = $('[data-lpl-overdue]');
		if (overdueBox) {
			var seen = {}, overdue = [];
			days.forEach(function (d) {
				if (d.date >= today) { return; }
				(d.courses || []).forEach(function (c) {
					if (Number(c.progress || 0) < 100 && !seen[c.id]) { seen[c.id] = 1; overdue.push(c); }
				});
			});
			overdueBox.innerHTML = overdue.length ? courseList(overdue, show, 4, 'Afficher les autres') : '<p class="lpl-ok">Tout est à jour.</p>';
			var head = root.querySelector('.lpl-resume h3');
			if (head && overdue.length) { head.insertAdjacentHTML('beforeend', ' <span class="lpl-count">' + overdue.length + '</span>'); }
		}

		var autonomyBox = $('[data-lpl-autonomy]');
		if (autonomyBox) {
			var auto = data.autonomyCourses || [];
			autonomyBox.innerHTML = auto.length ? courseList(auto, show, 3, 'Afficher les autres modules') : '<p class="lpl-muted">Aucun module en autonomie pour le moment.</p>';
			if (!auto.length) { autonomyBox.closest('.lpl-autonomy').classList.add('is-empty'); }
		}

		// Recherche d'un module : toutes ses dates, d'un clic on y va.
		var search = $('[data-lpl-module-search]'), result = $('[data-lpl-module-result]');
		var modules = {};
		days.forEach(function (d) {
			(d.courses || []).forEach(function (c) {
				var k = String(c.id);
				(modules[k] = modules[k] || { title: c.title, days: [] }).days.push(d);
			});
		});
		var keys = Object.keys(modules).sort(function (a, b) { return modules[a].title.localeCompare(modules[b].title, 'fr-CH', { numeric: true, sensitivity: 'base' }); });
		if (!keys.length) {
			search.closest('.lpl-search').hidden = true;
		} else {
			search.insertAdjacentHTML('beforeend', keys.map(function (k) { return '<option value="' + esc(k) + '">' + esc(modules[k].title) + '</option>'; }).join(''));
		}
		search.addEventListener('change', function () {
			var m = modules[search.value];
			result.hidden = !m;
			result.innerHTML = m ? '<strong>' + esc(m.title) + '</strong><span class="lpl-muted"> · ' + m.days.length + (m.days.length > 1 ? ' journées' : ' journée') + '</span><div class="lpl-module-dates">' + m.days.map(function (d) {
				return '<button type="button" class="lpl-chip-btn' + (d.date < today ? ' is-past' : '') + '" data-lpl-goto="' + esc(d.id) + '">' + esc(longDate(d.date)) + '</button>';
			}).join('') + '</div>' : '';
		});

		root.addEventListener('click', function (e) {
			var t;
			if ((t = e.target.closest('[data-lpl-view]'))) { setView(t.getAttribute('data-lpl-view')); return; }
			if ((t = e.target.closest('[data-lpl-prev]'))) { current = addMonths(current, -1); draw(); return; }
			if ((t = e.target.closest('[data-lpl-next-month]'))) { current = addMonths(current, 1); draw(); return; }
			if ((t = e.target.closest('[data-lpl-today]'))) { current = startMonth(); draw(); return; }
			if ((t = e.target.closest('[data-lpl-goto]'))) { goTo(t.getAttribute('data-lpl-goto')); return; }
			if ((t = e.target.closest('[data-lpl-toggle]'))) {
				var open = t.getAttribute('aria-expanded') === 'true';
				t.setAttribute('aria-expanded', open ? 'false' : 'true');
				var body = t.closest('.lpl-day').querySelector('.lpl-day-body');
				body.hidden = open;
				t.closest('.lpl-day').classList.toggle('is-open', !open);
				if (!open) { clampMessages(body); }
				return;
			}
			if ((t = e.target.closest('[data-lpl-readmore]'))) {
				var msg = t.previousElementSibling;
				var clamped = msg.classList.toggle('is-clamped');
				t.textContent = clamped ? 'Lire la suite' : 'Réduire';
				return;
			}
			if ((t = e.target.closest('[data-lpl-more]'))) {
				var box = t.previousElementSibling;
				var openMore = t.getAttribute('aria-expanded') === 'true';
				box.querySelectorAll('[data-lpl-extra]').forEach(function (x) { x.hidden = openMore; });
				t.setAttribute('aria-expanded', openMore ? 'false' : 'true');
				t.textContent = openMore ? t.getAttribute('data-more') : t.getAttribute('data-less');
			}
		});

		draw();
	}

	function boot() { document.querySelectorAll('[data-lpl-app]').forEach(init); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
