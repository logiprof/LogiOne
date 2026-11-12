(function () {
	'use strict';

	function pad(value) {
		return String(value).padStart(2, '0');
	}

	function isoDate(date) {
		return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
	}

	function monthLabel(date) {
		return date.toLocaleDateString('fr-CH', { month: 'long', year: 'numeric' });
	}

	function fullDateLabel(value) {
		return new Date(value + 'T12:00:00').toLocaleDateString('fr-CH', {
			weekday: 'long',
			day: '2-digit',
			month: 'long',
			year: 'numeric'
		});
	}

	function todayIso() {
		return isoDate(new Date());
	}

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"']/g, function (char) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
		});
	}

	function monthStart(date) {
		return new Date(date.getFullYear(), date.getMonth(), 1);
	}

	function nextMonth(date) {
		return new Date(date.getFullYear(), date.getMonth() + 1, 1);
	}

	function thirdMonth(date) {
		return new Date(date.getFullYear(), date.getMonth() + 2, 1);
	}

	function sameMonth(date, month) {
		return date.getFullYear() === month.getFullYear() && date.getMonth() === month.getMonth();
	}

	function renderCourse(course) {
		var image = course.image ?
			'<a class="lplms-course-image" href="' + escapeHtml(course.url) + '" target="_blank" rel="noopener noreferrer"><img src="' + escapeHtml(course.image) + '" alt=""></a>' :
			'<a class="lplms-course-image is-empty" href="' + escapeHtml(course.url) + '" target="_blank" rel="noopener noreferrer"></a>';

		return '<article class="lplms-course-card">' +
			image +
			'<div class="lplms-course-info">' +
				'<h4><a href="' + escapeHtml(course.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(course.title) + '</a></h4>' +
				(course.summary ? '<p>' + escapeHtml(course.summary) + '</p>' : '') +
				'<div class="lplms-course-progress"><span><i style="width:' + Number(course.progress || 0) + '%"></i></span><strong>' + Number(course.progress || 0) + '%</strong></div>' +
			'</div>' +
		'</article>';
	}

	function renderExpandableCourses(courses, className, emptyText, moreLabel, visibleLimit) {
		if (!courses || !courses.length) {
			if (emptyText === '') {
				return '';
			}
			return emptyText ? '<p class="lplms-no-course">' + escapeHtml(emptyText) + '</p>' : '';
		}

		var limit = visibleLimit || 3;
		var extra = Math.max(0, courses.length - limit);
		var html = '<div class="' + escapeHtml(className || 'lplms-course-stack') + '">' + courses.map(function (course, index) {
			return '<div class="' + (index >= limit ? 'lplms-extra-course' : '') + '"' + (index >= limit ? ' hidden' : '') + '>' + renderCourse(course) + '</div>';
		}).join('') + '</div>';

		if (extra > 0) {
			html += '<button type="button" class="lplms-expand-courses" data-lplms-expand-courses data-more-label="' + escapeHtml(moreLabel || 'Afficher les autres cours') + '" data-less-label="Masquer les cours" aria-expanded="false">' + escapeHtml((moreLabel || 'Afficher les autres cours') + ' (' + extra + ')') + '</button>';
		}

		return html;
	}

	function renderDay(day) {
		var isPast = day.date < todayIso();
		var message = day.message ?
			'<div><div class="lplms-message" data-lplms-message>' + day.message + '</div><button type="button" class="lplms-expand-message" data-lplms-expand-message hidden aria-expanded="false">Lire la suite</button></div>' :
			'';
		var dateMeta = day.isVacation ? '' : '<span>' + escapeHtml(day.schedule || day.modality || '') + '</span>';
		var state = day.state && day.state.key !== 'none' && (day.state.icon || day.state.label) ?
			'<em class="lplms-state-' + escapeHtml(day.state.key) + '">' + escapeHtml(day.state.icon) + ' ' + escapeHtml(day.state.label) + '</em>' :
			'';
		var groupDetails = day.groupDetails && day.groupDetails.length ?
			'<div class="lplms-group-details">' + day.groupDetails.map(function (item) {
				return '<div><strong>' + escapeHtml(item.group) + '</strong><span>' +
					(item.teacher ? escapeHtml(item.teacher) : '') +
					(item.teacher && item.classroom ? ' - ' : '') +
					(item.classroom ? escapeHtml(item.classroom) : '') +
				'</span></div>';
			}).join('') + '</div>' :
			'';
		var details = day.isVacation ?
			'<header><span></span><div><h3>' + escapeHtml(day.branch) + '</h3></div></header>' + message :
			'<header><span></span><div><h3>' + escapeHtml(day.branch) + '</h3><p>' + escapeHtml(day.modality) + '</p></div>' + state + '</header>' +
				groupDetails +
				message +
				renderExpandableCourses(day.courses || [], 'lplms-course-stack', '', 'Afficher les autres cours') +
				(day.icsUrl ? '<a class="lplms-day-export" href="' + escapeHtml(day.icsUrl) + '">Importer cette journée dans mon agenda</a>' : '');
		var pastSummary = isPast ?
			'<button type="button" class="lplms-past-summary" data-lplms-past-toggle aria-expanded="false">' +
				'<span>' + escapeHtml(day.branch) + '</span>' +
				'<small>Afficher le contenu</small>' +
			'</button>' :
			'';

		return '<article class="lplms-session' + (isPast ? ' is-past' : '') + '" data-lplms-session="' + escapeHtml(day.id) + '"' + (isPast ? ' data-lplms-past-session' : '') + ' style="--lplms-branch:' + escapeHtml(day.color) + '">' +
			'<div class="lplms-session-date">' +
				'<strong>' + escapeHtml(fullDateLabel(day.date)) + '</strong>' +
				dateMeta +
			'</div>' +
			pastSummary +
			'<div class="lplms-session-body"' + (isPast ? ' data-lplms-past-content hidden' : '') + '>' +
				details +
			'</div>' +
		'</article>';
	}

	function renderMonthGrid(month, days) {
		var first = monthStart(month);
		var firstDay = (first.getDay() + 6) % 7;
		var start = new Date(first.getFullYear(), first.getMonth(), 1 - firstDay);
		var monthDays = days.filter(function (day) {
			return sameMonth(new Date(day.date + 'T12:00:00'), month);
		});
		var byDate = {};
		monthDays.forEach(function (day) {
			if (!byDate[day.date]) {
				byDate[day.date] = [];
			}
			byDate[day.date].push(day);
		});

		var html = '<div class="lplms-month-weekdays">' +
			['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].map(function (label) {
				return '<span>' + label + '</span>';
			}).join('') +
			'</div><div class="lplms-month-grid">';

		for (var i = 0; i < 42; i++) {
			var date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
			var iso = isoDate(date);
			var items = byDate[iso] || [];
			var outside = sameMonth(date, month) ? '' : ' is-outside';
			var today = iso === todayIso() ? ' is-today' : '';
			html += '<div class="lplms-month-cell' + outside + today + '">' +
				'<strong>' + date.getDate() + '</strong>' +
				items.map(function (day) {
					return '<button type="button" class="lplms-month-event" data-lplms-day="' + escapeHtml(day.id) + '" style="--lplms-branch:' + escapeHtml(day.color) + '">' +
						'<span>' + escapeHtml(day.branch) + '</span>' +
						(day.schedule ? '<small>' + escapeHtml(day.schedule) + '</small>' : '') +
					'</button>';
				}).join('') +
			'</div>';
		}

		return html + '</div>';
	}

	function setupExpandableMessages(root) {
		root.querySelectorAll('[data-lplms-message]').forEach(function (message) {
			if (message.closest('[hidden]')) {
				return;
			}
			var button = message.nextElementSibling;
			if (!button || !button.matches('[data-lplms-expand-message]')) {
				return;
			}

			message.classList.add('is-collapsed');
			window.requestAnimationFrame(function () {
				if (message.scrollHeight > message.clientHeight + 4) {
					button.hidden = false;
				} else {
					message.classList.remove('is-collapsed');
				}
			});
		});
	}

	function setupPastSessions(root) {
		root.querySelectorAll('[data-lplms-past-session]').forEach(function (session) {
			var button = session.querySelector('[data-lplms-past-toggle]');
			var content = session.querySelector('[data-lplms-past-content]');
			if (!button || !content) {
				return;
			}

			function toggle() {
				var isOpen = button.getAttribute('aria-expanded') === 'true';
				button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
				button.querySelector('small').textContent = isOpen ? 'Afficher le contenu' : 'Masquer le contenu';
				content.hidden = isOpen;
				session.classList.toggle('is-open', !isOpen);
				if (isOpen) {
					setupExpandableMessages(content);
				}
			}

			session.addEventListener('click', function (event) {
				var otherButton = event.target.closest('button');
				if (event.target.closest('a') || (otherButton && otherButton !== button)) {
					return;
				}
				toggle();
			});
		});
	}

	function renderOverdue(container, days, today) {
		var courses = [];
		days.forEach(function (day) {
			if (day.date >= today || !day.courses || !day.courses.length) {
				return;
			}
			day.courses.forEach(function (course) {
				if (Number(course.progress || 0) < 100) {
					courses.push({
						day: day,
						course: course
					});
				}
			});
		});

		if (!courses.length) {
			container.innerHTML = '<div class="lplms-empty">Aucun cours en retard pour le moment.</div>';
			return;
		}

		container.innerHTML = renderExpandableCourses(courses.map(function (item) {
			return item.course;
		}), 'lplms-overdue-stack', 'Aucun cours en retard pour le moment.', 'Afficher les autres cours à reprendre', 2);
	}

	function renderAutonomy(root, courses) {
		var button = root.querySelector('[data-lplms-autonomy-toggle]');
		var content = root.querySelector('[data-lplms-autonomy-content]');
		if (!button || !content) {
			return;
		}

		if (!courses || !courses.length) {
			content.innerHTML = '<div class="lplms-empty">Aucun module d’autonomie n’est disponible pour le moment.</div>';
		} else {
			content.innerHTML = renderExpandableCourses(courses, 'lplms-autonomy-grid', 'Aucun module d’autonomie n’est disponible pour le moment.', 'Afficher les autres modules');
		}

		button.addEventListener('click', function () {
			var isOpen = button.getAttribute('aria-expanded') === 'true';
			button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
			button.textContent = isOpen ? 'Afficher les modules' : 'Masquer les modules';
			content.hidden = isOpen;
		});
	}

	function initModuleSearch(root, days) {
		var select = root.querySelector('[data-lplms-module-search]');
		var result = root.querySelector('[data-lplms-module-result]');
		if (!select || !result) {
			return;
		}

		var modules = {};
		days.forEach(function (day) {
			(day.courses || []).forEach(function (course) {
				var id = String(course.id || '');
				if (!id) {
					return;
				}
				if (!modules[id]) {
					modules[id] = {
						id: id,
						title: course.title || '',
						dates: []
					};
				}
				modules[id].dates.push(day);
			});
		});

		var sorted = Object.keys(modules).map(function (id) {
			return modules[id];
		}).sort(function (a, b) {
			return String(a.title).localeCompare(String(b.title), 'fr-CH', { numeric: true, sensitivity: 'base' });
		});

		if (!sorted.length) {
			select.disabled = true;
			result.hidden = true;
			return;
		}

		select.insertAdjacentHTML('beforeend', sorted.map(function (module) {
			return '<option value="' + escapeHtml(module.id) + '">' + escapeHtml(module.title) + '</option>';
		}).join(''));

		select.addEventListener('change', function () {
			var module = modules[select.value];
			if (!module) {
				result.hidden = true;
				result.innerHTML = '';
				return;
			}
			var dates = module.dates.slice().sort(function (a, b) {
				return a.date.localeCompare(b.date);
			});
			result.innerHTML = '<strong>' + escapeHtml(module.title) + '</strong><ul>' + dates.map(function (day) {
				return '<li><button type="button" data-lplms-module-day="' + escapeHtml(day.id) + '">' + escapeHtml(fullDateLabel(day.date)) + '</button></li>';
			}).join('') + '</ul>';
			result.hidden = false;
		});

		result.addEventListener('click', function (event) {
			var button = event.target.closest('[data-lplms-module-day]');
			if (!button) {
				return;
			}
			var id = String(button.getAttribute('data-lplms-module-day'));
			root.querySelectorAll('[data-lplms-view]').forEach(function (item) {
				item.setAttribute('aria-pressed', item.getAttribute('data-lplms-view') === 'list' ? 'true' : 'false');
			});
			var list = root.querySelector('[data-lplms-list]');
			var monthGrid = root.querySelector('[data-lplms-month-grid]');
			if (list) {
				list.hidden = false;
			}
			if (monthGrid) {
				monthGrid.hidden = true;
			}
			var session = null;
			root.querySelectorAll('[data-lplms-session]').forEach(function (item) {
				if (String(item.getAttribute('data-lplms-session')) === id) {
					session = item;
				}
			});
			if (session) {
				session.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	function initCalendar(root) {
		var data;
		try {
			data = JSON.parse(root.getAttribute('data-logiplanning') || '{}');
		} catch (error) {
			return;
		}

		data.today = todayIso();
		var current = monthStart(new Date(data.today + 'T12:00:00'));
		var title = root.querySelector('[data-lplms-title]');
		var list = root.querySelector('[data-lplms-list]');
		var monthGrid = root.querySelector('[data-lplms-month-grid]');
		var overdue = root.querySelector('[data-lplms-overdue]');
		var view = 'list';
		var days = data.days || [];

		renderAutonomy(root, data.autonomyCourses || []);
		initModuleSearch(root, days);

		function isTeachingDay(day) {
			return day && !day.isVacation;
		}

		function hasTeachingDayInMonth(month, today) {
			var end = nextMonth(month);
			return days.some(function (day) {
				var date = new Date(day.date + 'T12:00:00');
				return isTeachingDay(day) && day.date >= today && date >= month && date < end;
			});
		}

		function firstFutureTeachingDay(today) {
			return days
				.filter(function (day) {
					return isTeachingDay(day) && day.date >= today;
				})
				.sort(function (a, b) {
					return a.date.localeCompare(b.date);
				})[0];
		}

		function moveToNextFutureMonthIfCurrentIsEmpty() {
			var today = data.today;
			var todayMonth = monthStart(new Date(today + 'T12:00:00'));
			if (current.getFullYear() !== todayMonth.getFullYear() || current.getMonth() !== todayMonth.getMonth()) {
				return;
			}
			if (hasTeachingDayInMonth(current, today)) {
				return;
			}
			var nextDay = firstFutureTeachingDay(today);
			if (nextDay) {
				current = monthStart(new Date(nextDay.date + 'T12:00:00'));
			}
		}

		function draw() {
			var end = thirdMonth(current);
			var visibleDays = days.filter(function (day) {
				var date = new Date(day.date + 'T12:00:00');
				return date >= current && date < end;
			});

			title.textContent = view === 'calendar' ? monthLabel(current) : monthLabel(current) + ' - ' + monthLabel(nextMonth(current));
			list.innerHTML = visibleDays.length ?
				visibleDays.map(renderDay).join('') :
				'<div class="lplms-empty">Aucun cours planifié sur ces deux mois.</div>';
			if (monthGrid) {
				monthGrid.innerHTML = renderMonthGrid(current, days);
			}
			list.hidden = view !== 'list';
			if (monthGrid) {
				monthGrid.hidden = view !== 'calendar';
			}
			setupPastSessions(list);
			setupExpandableMessages(list);
			renderOverdue(overdue, days, data.today);
		}

		root.querySelectorAll('[data-lplms-view]').forEach(function (button) {
			button.addEventListener('click', function () {
				view = button.getAttribute('data-lplms-view') || 'list';
				root.querySelectorAll('[data-lplms-view]').forEach(function (item) {
					item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
				});
				draw();
			});
		});

		if (monthGrid) {
			monthGrid.addEventListener('click', function (event) {
				var button = event.target.closest('[data-lplms-day]');
				if (!button) {
					return;
				}
				var day = days.filter(function (item) {
					return String(item.id) === String(button.getAttribute('data-lplms-day'));
				})[0];
				if (day) {
					view = 'list';
					root.querySelectorAll('[data-lplms-view]').forEach(function (item) {
						item.setAttribute('aria-pressed', item.getAttribute('data-lplms-view') === 'list' ? 'true' : 'false');
					});
					draw();
					var target = null;
					list.querySelectorAll('[data-lplms-session]').forEach(function (session) {
						if (String(session.getAttribute('data-lplms-session')) === String(day.id)) {
							target = session;
						}
					});
					if (target) {
						target.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
				}
			});
		}

		root.querySelectorAll('[data-lplms-prev]').forEach(function (button) {
			button.addEventListener('click', function () {
				current.setMonth(current.getMonth() - 1);
				draw();
			});
		});
		root.querySelectorAll('[data-lplms-next]').forEach(function (button) {
			button.addEventListener('click', function () {
				current.setMonth(current.getMonth() + 1);
				draw();
			});
		});
		root.querySelectorAll('[data-lplms-today]').forEach(function (button) {
			button.addEventListener('click', function () {
				data.today = todayIso();
				current = monthStart(new Date(data.today + 'T12:00:00'));
				moveToNextFutureMonthIfCurrentIsEmpty();
				draw();
			});
		});

		moveToNextFutureMonthIfCurrentIsEmpty();
		draw();
	}

	document.addEventListener('DOMContentLoaded', function () {
		function updateCoursePreview(scope) {
			var preview = scope ? scope.querySelector('[data-lplms-course-preview]') : null;
			var picker = scope ? scope.querySelector('[data-lplms-category-picker]') : null;
			if (!preview || !picker) {
				return;
			}
			var codes = [];
			picker.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
				(input.getAttribute('data-lplms-course-codes') || '').split(',').forEach(function (code) {
					code = code.trim();
					if (code && codes.indexOf(code) === -1) {
						codes.push(code);
					}
				});
			});
			preview.innerHTML = codes.length ? escapeHtml(codes.join(', ')) : '<span>Aucun module lié</span>';
		}

		document.querySelectorAll('[data-lplms-category-picker]').forEach(function (picker) {
			picker.addEventListener('change', function (event) {
				var max = Number(picker.getAttribute('data-lplms-max') || 3);
				var checked = picker.querySelectorAll('input[type="checkbox"]:checked');
				if (checked.length > max) {
					event.target.checked = false;
					window.alert('Maximum 3 catégories Sensei par journée.');
				}
				updateCoursePreview(picker.closest('[data-lplms-day-row], tr') || picker.closest('form'));
			});
			updateCoursePreview(picker.closest('[data-lplms-day-row], tr') || picker.closest('form'));
		});

		document.querySelectorAll('[data-lplms-clear-categories]').forEach(function (button) {
			button.addEventListener('click', function () {
				var picker = button.previousElementSibling;
				if (!picker) {
					return;
				}
				picker.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
					input.checked = false;
				});
				updateCoursePreview(picker.closest('[data-lplms-day-row], tr') || picker.closest('form'));
			});
		});

		function syncVacationFields(toggle) {
			var scope = toggle.closest('[data-lplms-day-row], tr') || toggle.closest('form');
			if (!scope) {
				return;
			}
			scope.querySelectorAll('[data-lplms-vacation-field]').forEach(function (field) {
				field.querySelectorAll('input, select, textarea, button').forEach(function (input) {
					input.disabled = toggle.checked;
					if (toggle.checked && input.type === 'checkbox') {
						input.checked = false;
					}
					if (toggle.checked && (input.tagName === 'INPUT' || input.tagName === 'TEXTAREA') && input.type !== 'hidden' && input.type !== 'checkbox') {
						input.value = '';
					}
				});
				field.classList.toggle('is-disabled', toggle.checked);
			});
			syncDistanceClassroom(scope);
		}

		function syncDistanceClassroom(scope) {
			if (!scope) {
				return;
			}
			var select = scope.querySelector('[data-lplms-modality-select]');
			var field = scope.querySelector('[data-lplms-classroom-field]');
			if (!select || !field) {
				return;
			}
			var vacationToggle = scope.querySelector('[data-lplms-vacation-toggle]');
			var disabled = select.value === 'distance' || !!(vacationToggle && vacationToggle.checked);
			field.querySelectorAll('input, select, textarea, button').forEach(function (input) {
				input.disabled = disabled;
				if (disabled && (input.tagName === 'INPUT' || input.tagName === 'TEXTAREA') && input.type !== 'hidden' && input.type !== 'checkbox') {
					input.value = '';
				}
			});
			field.classList.toggle('is-disabled', disabled);
		}

		document.querySelectorAll('[data-lplms-vacation-toggle]').forEach(function (toggle) {
			syncVacationFields(toggle);
			toggle.addEventListener('change', function () {
				syncVacationFields(toggle);
			});
		});

		document.querySelectorAll('[data-lplms-modality-select]').forEach(function (select) {
			var scope = select.closest('[data-lplms-day-row], tr') || select.closest('form');
			syncDistanceClassroom(scope);
			select.addEventListener('change', function () {
				syncDistanceClassroom(scope);
			});
		});

		document.querySelectorAll('[data-lplms-branch-select]').forEach(function (select) {
			select.addEventListener('change', function () {
				var option = select.options[select.selectedIndex];
				var schedule = option ? option.getAttribute('data-schedule') : '';
				var scope = select.closest('[data-lplms-day-row], tr') || select.closest('form');
				var input = scope ? scope.querySelector('[data-lplms-schedule-input]') : null;
				if (input && schedule) {
					input.value = schedule;
				}
			});
		});

		document.querySelectorAll('.lplms-calendar').forEach(initCalendar);

		document.addEventListener('click', function (event) {
			var button = event.target.closest('[data-lplms-expand-courses]');
			if (!button) {
				return;
			}

			var isOpen = button.getAttribute('aria-expanded') === 'true';
			var container = button.previousElementSibling;
			if (container) {
				container.querySelectorAll('.lplms-extra-course').forEach(function (item) {
					item.hidden = isOpen;
				});
			}
			button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
			button.textContent = isOpen ? button.getAttribute('data-more-label') : button.getAttribute('data-less-label');
		});

		document.addEventListener('click', function (event) {
			var button = event.target.closest('[data-lplms-expand-message]');
			if (!button) {
				return;
			}
			var message = button.previousElementSibling;
			var isOpen = button.getAttribute('aria-expanded') === 'true';
			if (message) {
				message.classList.toggle('is-collapsed', isOpen);
			}
			button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
			button.textContent = isOpen ? 'Lire la suite' : 'Réduire le message';
		});
	});
}());

/* LogiOne : plan choisi = affiché tout de suite ; bouton « Copier » ; avertissement si des journées ne sont pas enregistrées. */
(function () {
	'use strict';
	document.querySelectorAll('.lplms-days-bar select[name="plan_id"]').forEach(function (select) {
		select.addEventListener('change', function () { select.form.submit(); });
	});
	document.addEventListener('click', function (event) {
		var btn = event.target.closest('[data-lplms-copy]');
		if (!btn) { return; }
		var input = btn.parentNode.querySelector('input');
		input.select();
		try { navigator.clipboard.writeText(input.value); } catch (e) { document.execCommand('copy'); }
		var label = btn.textContent;
		btn.textContent = 'Copié ✓';
		window.setTimeout(function () { btn.textContent = label; }, 1500);
	});
	var form = document.querySelector('.lplms-days-form');
	if (!form) { return; }
	var dirty = false;
	var flag = form.querySelector('[data-lplms-dirty]');
	form.addEventListener('change', function () { dirty = true; if (flag) { flag.hidden = false; } });
	form.addEventListener('submit', function () { dirty = false; });
	window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
