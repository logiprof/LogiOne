/* LogiOne 1.36 : boutons flottants hors cadre dans une page avec le shortcode.
   Un conteneur du thème (section, colonne, animation…) qui a une transformation, un filtre ou « contain » devient la
   référence des éléments « position: fixed » : les boutons flottants se retrouvaient au bas de ce conteneur, hors de
   l'écran. Dans ce cas on les place dans un conteneur directement sous <body>. */
(function () {
	'use strict';
	function trapsFixed(el) {
		for (let n = el && el.parentElement; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
			const cs = window.getComputedStyle(n);
			if ((cs.transform && cs.transform !== 'none') || (cs.filter && cs.filter !== 'none') || (cs.perspective && cs.perspective !== 'none')
				|| (cs.backdropFilter && cs.backdropFilter !== 'none') || /paint|layout|strict|content/.test(cs.contain || '')
				|| /transform|filter|perspective/.test(cs.willChange || '') || (cs.containerType && cs.containerType !== 'normal')) {
				return true;
			}
		}
		return false;
	}
	function host() {
		let h = document.getElementById('les-fixed-portal');
		if (!h) {
			h = document.createElement('div');
			h.id = 'les-fixed-portal';
			h.className = 'logieval-secure les-portal';
			document.body.appendChild(h);
		}
		return h;
	}
	/** Place l'élément sous <body> si son conteneur l'empêche d'être vraiment « fixe ». Garde le lien avec son formulaire. */
	function escape(el, force) {
		if (!el || (!force && !trapsFixed(el)) || el.closest('#les-fixed-portal')) { return false; }
		const form = el.form || el.closest('form');
		if (form && (el.type === 'submit' || el.tagName === 'BUTTON')) {
			if (!form.id) { form.id = 'les-form-' + Math.random().toString(36).slice(2, 9); }
			el.setAttribute('form', form.id);
		}
		host().appendChild(el);
		return true;
	}
	window.LogiExamFixed = { traps: trapsFixed, escape: escape, host: host };
	function init() {
		document.querySelectorAll('.les-fab, .les-fab-save, .les-fab-draft').forEach((el) => { escape(el); });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	window.addEventListener('load', init);
}());

(function () {
	'use strict';

	if (!window.LogiEvalSecure) {
		return;
	}
	const teacherRoot = document.querySelector('[data-les-teacher]');
	if (teacherRoot) {
		initTeacherMonitoring(teacherRoot);
		initTeacherAiGrading(teacherRoot);
		initAiSettingsForm(teacherRoot);
		initBonusMalus(teacherRoot);
		initDirtyGuard(teacherRoot);
		initGradingCriteria(teacherRoot);
		initAiStrictness(teacherRoot);
		initQuestionTypeFields(teacherRoot);
	}

	// « Remettre dans l'ordre » (éditeur) : lettres ou éléments, ajout / suppression / déplacement des lignes.
	function initOrderEditor() {
		const renumber = (rows) => {
			Array.from(rows.querySelectorAll('[data-les-order-row]')).forEach((row, i, all) => {
				const num = row.querySelector('.les-order-num');
				if (num) num.textContent = String(i + 1);
				const up = row.querySelector('[data-les-order-up]');
				const down = row.querySelector('[data-les-order-down]');
				if (up) up.disabled = i === 0;
				if (down) down.disabled = i === all.length - 1;
			});
		};
		document.querySelectorAll('[data-les-order-rows]').forEach(renumber);
		document.addEventListener('change', (event) => {
			const mode = event.target.closest('[data-les-order-mode]');
			if (!mode) return;
			const editor = mode.closest('[data-les-order-editor]');
			editor.querySelectorAll('[data-les-order-for]').forEach((block) => { block.hidden = block.dataset.lesOrderFor !== mode.value; });
		});
		document.addEventListener('click', (event) => {
			const btn = event.target.closest('[data-les-order-add], [data-les-order-remove], [data-les-order-up], [data-les-order-down]');
			if (!btn) return;
			const editor = btn.closest('[data-les-order-editor]');
			const rows = editor && editor.querySelector('[data-les-order-rows]');
			if (!rows) return;
			event.preventDefault();
			const row = btn.closest('[data-les-order-row]');
			if (btn.hasAttribute('data-les-order-add')) {
				const last = rows.querySelector('[data-les-order-row]:last-child');
				if (!last) return;
				let max = -1;
				rows.querySelectorAll('[name*="[order_items]["]').forEach((input) => {
					const m = input.name.match(/\[order_items\]\[(\d+)\]/);
					if (m) max = Math.max(max, parseInt(m[1], 10));
				});
				const clone = last.cloneNode(true);
				clone.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/\[order_items\]\[\d+\]/, '[order_items][' + (max + 1) + ']'); });
				clone.querySelectorAll('input[type="text"], input[type="file"]').forEach((input) => { input.value = ''; });
				clone.querySelectorAll('[data-les-order-id], [data-les-order-media]').forEach((input) => { input.value = input.hasAttribute('data-les-order-media') ? '0' : ''; });
				clone.querySelectorAll('.les-order-image img, .les-order-image .les-check').forEach((el) => el.remove());
				clone.querySelectorAll('[data-les-media-picked]').forEach((el) => { el.innerHTML = ''; el.hidden = true; });
				rows.appendChild(clone);
				const text = clone.querySelector('.les-order-text');
				if (text) text.focus();
			} else if (btn.hasAttribute('data-les-order-remove') && row) {
				if (rows.querySelectorAll('[data-les-order-row]').length > 2) {
					row.remove();
				} else {
					row.querySelectorAll('input[type="text"]').forEach((input) => { input.value = ''; });
				}
			} else if (btn.hasAttribute('data-les-order-up') && row && row.previousElementSibling) {
				rows.insertBefore(row, row.previousElementSibling);
			} else if (btn.hasAttribute('data-les-order-down') && row && row.nextElementSibling) {
				rows.insertBefore(row.nextElementSibling, row);
			}
			renumber(rows);
		});
	}
	initOrderEditor();

	// 1.36 : « Relier » (éditeur) : ajouter / supprimer des paires ; « Texte à trous » : aperçu des trous.
	function initMatchBlanksEditors() {
		document.addEventListener('click', (event) => {
			const add = event.target.closest('[data-les-match-add]');
			const rm = event.target.closest('[data-les-match-remove]');
			if (!add && !rm) return;
			event.preventDefault();
			const editor = (add || rm).closest('[data-les-match-editor]');
			const rows = editor && editor.querySelector('[data-les-match-rows]');
			if (!rows) return;
			if (rm) {
				const row = rm.closest('[data-les-match-row]');
				if (rows.querySelectorAll('[data-les-match-row]').length > 2) row.remove();
				else row.querySelectorAll('input').forEach((i) => { i.value = ''; });
				return;
			}
			const last = rows.querySelector('[data-les-match-row]:last-child');
			if (!last) return;
			let max = -1;
			rows.querySelectorAll('[name*="[match_pairs]["]').forEach((input) => {
				const m = input.name.match(/\[match_pairs\]\[(\d+)\]/);
				if (m) max = Math.max(max, parseInt(m[1], 10));
			});
			const clone = last.cloneNode(true);
			clone.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/\[match_pairs\]\[\d+\]/, '[match_pairs][' + (max + 1) + ']'); input.value = ''; });
			rows.appendChild(clone);
			const first = clone.querySelector('input[type="text"]');
			if (first) first.focus();
		});
		const preview = (area) => {
			const box = area.closest('[data-les-blanks-editor]');
			const out = box && box.querySelector('[data-les-blanks-preview]');
			if (!out) return;
			const found = (area.value.match(/\[[^\[\]]+\]/g) || []).map((b) => b.slice(1, -1).split('|').map((v) => v.trim()).filter(Boolean)).filter((v) => v.length);
			out.textContent = found.length ? found.length + ' trou(s) : ' + found.map((v, i) => (i + 1) + '. ' + v.join(' / ')).join(' · ') : 'Aucun trou pour l’instant : mettez les mots à trouver entre crochets [ ].';
		};
		document.querySelectorAll('[data-les-blanks-text]').forEach(preview);
		document.addEventListener('input', (event) => { if (event.target.matches && event.target.matches('[data-les-blanks-text]')) preview(event.target); });
	}
	initMatchBlanksEditors();

	// Barème de l'éditeur : recalculé dès qu'on change les points d'une question (notes à la demi).
	function initBareme() {
		const panel = document.querySelector('[data-les-bareme]');
		if (!panel) return;
		const form = panel.closest('form');
		const grade = (p, t) => Math.round(Math.min(6, Math.max(1, p / t * 5 + 1)) * 2) / 2;
		const fmt = (n) => (Math.round(n * 100) / 100).toFixed(2);
		const label = (g) => String(g).replace('.', ',');
		const update = () => {
			let total = 0;
			form.querySelectorAll('input[name$="[points]"]').forEach((input) => {
				if (input.closest('template') || input.closest('[data-logiexam-question-template]')) return;
				const q = input.closest('.les-inline-question');
				const prompt = q ? q.querySelector('textarea[name$="[prompt]"]') : null;
				if (prompt && prompt.value.trim() === '') return;
				total += parseFloat(String(input.value).replace(',', '.')) || 0;
			});
			panel.querySelector('[data-les-bareme-total]').textContent = fmt(total);
			const grid = panel.querySelector('[data-les-bareme-grid]');
			const rows = {};
			if (total > 0) {
				for (let i = 0; i <= Math.round(total * 4); i++) {
					const p = i / 4; const g = grade(p, total);
					if (!rows[g]) rows[g] = { min: p, max: p }; rows[g].max = p;
				}
			}
			grid.innerHTML = Object.keys(rows).map(Number).sort((a, b) => b - a).map((g) => '<div class="les-bareme-cell"><strong>' + label(g) + '</strong><span>' + fmt(rows[g].min) + ' – ' + fmt(rows[g].max) + ' pts</span></div>').join('');
			const empty = panel.querySelector('[data-les-bareme-empty]');
			if (empty) empty.hidden = total > 0;
		};
		form.addEventListener('input', (e) => { if (e.target.matches('input[name$="[points]"], textarea[name$="[prompt]"]')) update(); });
		// 1.36 : document entier (le bouton flottant « Ajouter une question » peut être placé sous <body>).
		document.addEventListener('click', (e) => { if (e.target.closest('[data-logiexam-add-question], [data-logiexam-duplicate-question], [data-logiexam-remove-question], [data-logiexam-add-story-question], [data-logiexam-remove-story-section], [data-les-add-problem], [data-les-add-subquestion], [data-les-remove-problem]')) setTimeout(update, 50); });
		update();
	}
	initBareme();

	// Affiche les champs de réponse attendue correspondant au type de question (banque et éditeur).
	function initQuestionTypeFields(teacherRootNode) {
		const refresh = (question) => {
			const type = question.querySelector('[data-logiexam-question-type]');
			const value = type ? type.value : 'short_answer';
			question.querySelectorAll('[data-logiexam-field-types]').forEach((block) => {
				const types = (block.dataset.logiexamFieldTypes || '').split(/\s+/);
				// Anciens types : on garde le champ « Réponse attendue » texte.
				const legacy = ['multiple_choice', 'manual', 'mes'].indexOf(value) !== -1 && types.indexOf('long_answer') !== -1;
				block.hidden = types.indexOf(value) === -1 && !legacy;
			});
		};
		teacherRootNode.querySelectorAll('.les-inline-question').forEach(refresh);
		teacherRootNode.addEventListener('change', (event) => {
			if (!event.target.matches('[data-logiexam-question-type]')) return;
			const question = event.target.closest('.les-inline-question');
			if (question) refresh(question);
		});
	}
	initImageZoom();

	// Niveau de sévérité de la pré-correction IA : un seul niveau par évaluation, verrouillé dès la première pré-correction.
	function initAiStrictness(teacherRootNode) {
		teacherRootNode.querySelectorAll('[data-les-ai-strictness]').forEach((box) => {
			const radios = Array.from(box.querySelectorAll('input[type="radio"]'));
			const status = box.querySelector('[data-les-ai-strictness-status]');
			const lockNote = box.querySelector('[data-les-ai-lock-note]');
			const changeBtn = box.querySelector('[data-les-ai-strictness-change]');
			let current = (radios.find((r) => r.checked) || {}).value;
			let unlocking = false;
			const setDisabled = (disabled) => radios.forEach((r) => { r.disabled = disabled; });
			const lock = (text) => {
				box.dataset.locked = '1';
				box.classList.add('is-locked');
				setDisabled(true);
				if (lockNote) {
					lockNote.hidden = false;
					const span = lockNote.querySelector('[data-les-ai-lock-text]');
					if (span && text) span.textContent = text;
				}
			};
			if (changeBtn) {
				changeBtn.addEventListener('click', () => {
					const ok = window.confirm('Changer de niveau ?\n\nPour que tous les élèves soient évalués de la même façon, les propositions IA déjà faites seront effacées (copies dont la correction n’est pas terminée). Les points que vous avez saisis ne sont pas modifiés. Il faudra relancer la pré-correction.');
					if (!ok) return;
					unlocking = true;
					setDisabled(false);
					box.classList.remove('is-locked');
					if (status) status.textContent = 'Choisissez le nouveau niveau.';
				});
			}
			radios.forEach((radio) => radio.addEventListener('change', () => {
				if (!radio.checked) return;
				if (status) status.textContent = 'Enregistrement…';
				post('logieval_secure_set_ai_strictness', { evaluation_id: box.dataset.evaluationId, level: radio.value, reset: unlocking ? 1 : '' }).then((json) => {
					if (!json || !json.success) throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
					current = radio.value;
					document.querySelectorAll('[data-les-ai-panel-level]').forEach((node) => { node.textContent = json.data.label; });
					document.querySelectorAll('.les-ai-level-badge').forEach((badge) => {
						badge.textContent = 'Sévérité IA : ' + json.data.level + '/5 – ' + json.data.label + ' (identique pour tous les élèves)';
					});
					if (unlocking) {
						unlocking = false;
						if (status) status.textContent = 'Nouveau niveau « ' + json.data.label + ' » enregistré. ' + (json.data.cleared ? json.data.cleared + ' proposition(s) IA effacée(s) : relancez la pré-correction.' : '');
						if (json.data.graded > 0) lock(); else if (lockNote) lockNote.hidden = true;
						if (json.data.cleared) setTimeout(() => window.location.reload(), 1800);
					} else if (status) {
						status.textContent = 'Niveau « ' + json.data.label + ' » enregistré pour toute l’évaluation.';
					}
				}).catch((error) => {
					radios.forEach((r) => { r.checked = r.value === current; });
					if (status) status.textContent = error.message || LogiEvalSecure.i18n.error;
				});
			}));
			// Dès qu'une pré-correction réussit, le niveau est verrouillé pour toute l'évaluation.
			teacherRootNode.addEventListener('click', (event) => {
				if (!event.target.closest('[data-les-ai-grade], [data-les-ai-grade-bulk]')) return;
				setTimeout(() => {
					if (document.querySelector('.les-ai-grade-status.is-ok')) {
						const label = (radios.find((r) => r.checked) || {}).closest ? radios.find((r) => r.checked).closest('label').querySelector('strong').textContent : '';
						lock('Niveau verrouillé : des copies ont été pré-corrigées au niveau « ' + label + ' ».');
					}
				}, 2500);
			});
		});
	}

	// Agrandissement d'une image de question dans la page (le plein écran est conservé).
	function initImageZoom() {
		let overlay = null;
		let scale = 1;
		let keyboardLocked = false;
		function apply() {
			const img = overlay && overlay.querySelector('img');
			if (!img) return;
			if (scale === 1) {
				img.style.width = '';
				img.style.maxWidth = '100%';
				img.style.maxHeight = '100%';
			} else {
				// Zoom relatif à la taille « ajustée à l'écran ».
				if (!overlay.dataset.fitWidth) overlay.dataset.fitWidth = String(img.getBoundingClientRect().width || img.naturalWidth);
				img.style.maxWidth = 'none';
				img.style.maxHeight = 'none';
				img.style.width = (parseFloat(overlay.dataset.fitWidth) * scale) + 'px';
			}
			const level = overlay.querySelector('[data-zoom-level]');
			if (level) level.textContent = scale === 1 ? 'Ajusté' : Math.round(scale * 100) + ' %';
		}
		function close() {
			if (!overlay) return;
			overlay.remove();
			overlay = null;
			document.documentElement.classList.remove('les-zoom-open');
			if (keyboardLocked && navigator.keyboard && navigator.keyboard.unlock) {
				try { navigator.keyboard.unlock(); } catch (e) {}
			}
			keyboardLocked = false;
		}
		function open(src, alt) {
			close();
			scale = 1;
			overlay = document.createElement('div');
			overlay.className = 'les-zoom-overlay logieval-secure';
			overlay.setAttribute('role', 'dialog');
			overlay.setAttribute('aria-modal', 'true');
			overlay.innerHTML =
				'<div class="les-zoom-toolbar">' +
				'<button type="button" data-zoom="-">−</button><span data-zoom-level>Ajusté</span><button type="button" data-zoom="+">+</button>' +
				'<button type="button" data-zoom="fit">Ajuster à l’écran</button>' +
				'<button type="button" class="les-zoom-close" data-zoom="close" aria-label="Fermer">✕ Fermer</button>' +
				'</div>' +
				'<div class="les-zoom-stage"><img alt=""></div>' +
				'<p class="les-zoom-help" data-zoom-help>Fermez avec le bouton « ✕ Fermer » : la touche Échap ferait quitter le plein écran.</p>';
			const img = overlay.querySelector('img');
			img.src = src;
			img.alt = alt || '';
			// L'overlay est placé dans l'élément affiché en plein écran pour rester visible.
			document.body.appendChild(overlay);
			document.documentElement.classList.add('les-zoom-open');
			// Chrome / Edge : on capte la touche Échap pendant l'agrandissement pour qu'elle ferme l'image
			// au lieu de quitter le plein écran.
			if ((document.fullscreenElement || document.webkitFullscreenElement) && navigator.keyboard && navigator.keyboard.lock) {
				navigator.keyboard.lock(['Escape']).then(() => {
					keyboardLocked = true;
					const help = overlay && overlay.querySelector('[data-zoom-help]');
					if (help) help.textContent = 'Touche Échap ou bouton « ✕ Fermer » pour revenir à la question.';
				}).catch(() => {});
			}
			overlay.querySelector('[data-zoom="close"]').focus({ preventScroll: true });
		}
		document.addEventListener('click', function (event) {
			const trigger = event.target.closest('[data-les-zoom-image]');
			if (trigger) {
				event.preventDefault();
				const img = trigger.querySelector('img');
				open(trigger.dataset.lesZoomImage || (img && img.src), '');
				return;
			}
			if (!overlay) return;
			const control = event.target.closest('[data-zoom]');
			if (control) {
				event.preventDefault();
				const action = control.dataset.zoom;
				if (action === 'close') return close();
				if (action === 'fit') scale = 1;
				if (action === '+') scale = Math.min(4, (scale === 1 ? 1 : scale) + 0.5);
				if (action === '-') scale = Math.max(1, scale - 0.5);
				apply();
				return;
			}
			if (event.target.classList.contains('les-zoom-stage')) close();
		});
		document.addEventListener('keydown', function (event) {
			if (overlay && event.key === 'Escape' && keyboardLocked) {
				event.preventDefault();
				close();
			}
		}, true);
	}

	// Critères d'évaluation : édition (ajout / suppression de lignes) et cases à cocher dans la correction.
	function initGradingCriteria(teacherRootNode) {
		const num = (value) => {
			const n = parseFloat(String(value || '').replace(',', '.'));
			return isNaN(n) ? 0 : n;
		};
		const fmt = (n) => String(Math.round(n * 100) / 100);
		function questionPointsInput(editor) {
			const block = editor.closest('.les-inline-question') || editor.closest('form');
			return block ? block.querySelector('input[name$="[points]"]:not([name*="[criteria]"]), input[name="points"]') : null;
		}
		function refreshTotal(editor) {
			const totalNode = editor.querySelector('[data-les-criteria-total]');
			if (!totalNode) return;
			let total = 0;
			let count = 0;
			editor.querySelectorAll('.les-criteria-row').forEach((row) => {
				const inputs = row.querySelectorAll('input');
				if (inputs[0] && inputs[0].value.trim() !== '') {
					total += num(inputs[1] && inputs[1].value);
					count += 1;
				}
			});
			if (!count) {
				totalNode.textContent = '';
				totalNode.className = 'les-criteria-total';
				return;
			}
			const pointsInput = questionPointsInput(editor);
			const max = pointsInput ? num(pointsInput.value) : 0;
			const ok = !max || Math.abs(total - max) < 0.001;
			totalNode.textContent = 'Total des critères : ' + fmt(total) + (max ? ' / ' + fmt(max) + ' pt' : ' pt') + (ok ? '' : ' — ne correspond pas aux points de la question');
			totalNode.className = 'les-criteria-total ' + (ok ? 'is-ok' : 'is-warning');
		}
		teacherRootNode.querySelectorAll('[data-les-criteria-editor]').forEach(refreshTotal);

		teacherRootNode.addEventListener('click', function (event) {
			const add = event.target.closest('[data-les-add-criterion]');
			if (add) {
				event.preventDefault();
				const editor = add.closest('[data-les-criteria-editor]');
				const rows = editor && editor.querySelector('[data-les-criteria-rows]');
				if (!rows) return;
				const prefix = editor.dataset.lesCriteriaPrefix;
				const key = 'n' + Date.now() + Math.floor(Math.random() * 1000);
				const row = document.createElement('div');
				row.className = 'les-criteria-row';
				row.innerHTML = '<input type="text" placeholder="Critère">' +
					'<input type="number" step="0.25" min="0" value="1" aria-label="Points">' +
					'<button type="button" class="button les-button-secondary" data-les-remove-criterion aria-label="Supprimer ce critère">✕</button>';
				const inputs = row.querySelectorAll('input');
				inputs[0].name = prefix + '[' + key + '][label]';
				inputs[1].name = prefix + '[' + key + '][points]';
				rows.appendChild(row);
				inputs[0].focus();
				refreshTotal(editor);
				return;
			}
			const remove = event.target.closest('[data-les-remove-criterion]');
			if (remove) {
				event.preventDefault();
				const editor = remove.closest('[data-les-criteria-editor]');
				remove.closest('.les-criteria-row').remove();
				if (editor) refreshTotal(editor);
			}
		});

		teacherRootNode.addEventListener('input', function (event) {
			const editor = event.target.closest('[data-les-criteria-editor]');
			if (editor) {
				refreshTotal(editor);
				return;
			}
			if (event.target.matches('input[name$="[points]"], input[name="points"]')) {
				const block = event.target.closest('.les-inline-question') || event.target.closest('form');
				const nested = block && block.querySelector('[data-les-criteria-editor]');
				if (nested) refreshTotal(nested);
			}
		});

		// Correction : cocher des critères additionne leurs points dans le champ de la question.
		teacherRootNode.addEventListener('change', function (event) {
			const box = event.target.closest('[data-les-criterion]');
			if (!box) return;
			const group = box.closest('[data-les-criteria-check]');
			const scope = group.closest('[data-les-ai-grade-target]') || group.closest('.les-question-grade-form') || group.parentElement;
			const input = scope && scope.querySelector('input[name^="manual_points"], input[name^="question_manual_points"]');
			if (!input) return;
			let total = 0;
			group.querySelectorAll('[data-les-criterion]:checked').forEach((checked) => { total += num(checked.value); });
			input.value = fmt(total);
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});
	}

	// Bonus / Malus : boutons rapides et récapitulatif (points, total, note) recalculé en direct.
	function initBonusMalus(teacherRootNode) {
		const num = (value) => {
			const n = parseFloat(String(value || '').replace(',', '.'));
			return isNaN(n) ? 0 : n;
		};
		const fmt = (n) => (Math.round(n * 100) / 100).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
		function refresh(card) {
			const form = card.closest('form');
			const summary = card.querySelector('[data-les-bonus-summary]');
			if (!form || !summary) return;
			let points = 0;
			form.querySelectorAll('input[name^="manual_points"]').forEach((input) => {
				points += input.value.trim() !== '' ? num(input.value) : num(input.dataset.autoPoints);
			});
			const bonus = Math.max(0, num(card.querySelector('[data-les-bonus]').value));
			const malus = Math.max(0, num(card.querySelector('[data-les-malus]').value));
			const max = num(card.dataset.totalPoints);
			const total = Math.max(0, points + bonus - malus);
			let text = 'Points des questions : ' + fmt(points) + '  ·  Bonus +' + fmt(bonus) + '  ·  Malus −' + fmt(malus) + '  →  Total ' + fmt(total) + (max ? ' / ' + fmt(max) : '');
			if (max > 0) {
				const grade = Math.round(Math.min(6, Math.max(1, total / max * 5 + 1)) * 2) / 2;
				text += '  ·  Note ' + grade.toFixed(1);
			}
			summary.textContent = text;
			const sticky = form.querySelector('[data-les-sticky-summary]');
			if (sticky) {
				sticky.textContent = 'Total ' + fmt(total) + (max ? ' / ' + fmt(max) : '') + (max > 0 ? '  ·  Note ' + (Math.round(Math.min(6, Math.max(1, total / max * 5 + 1)) * 2) / 2).toFixed(1) : '') + (bonus ? '  ·  Bonus +' + fmt(bonus) : '') + (malus ? '  ·  Malus −' + fmt(malus) : '');
			}
		}
		const cards = () => teacherRootNode.querySelectorAll('[data-les-bonus-malus]');
		cards().forEach(refresh);
		teacherRootNode.addEventListener('click', function (event) {
			const button = event.target.closest('[data-les-adjust]');
			if (button) {
				const card = button.closest('[data-les-bonus-malus]');
				const input = card && card.querySelector(button.dataset.lesAdjust === 'bonus' ? '[data-les-bonus]' : '[data-les-malus]');
				if (input) {
					input.value = button.dataset.step === 'reset' ? '0' : fmt(Math.max(0, num(input.value) + num(button.dataset.step)));
					refresh(card);
				}
				return;
			}
			if (event.target.closest('[data-les-set-points], [data-les-ai-grade], [data-les-ai-grade-bulk]')) {
				// Les boutons de points et l'IA modifient les champs : on recalcule juste après.
				setTimeout(() => cards().forEach(refresh), 50);
				setTimeout(() => cards().forEach(refresh), 3000);
			}
		});
		teacherRootNode.addEventListener('input', function (event) {
			const form = event.target.closest('form');
			const card = form && form.querySelector('[data-les-bonus-malus]');
			if (card) refresh(card);
		});
		teacherRootNode.addEventListener('change', function (event) {
			const form = event.target.closest('form');
			const card = form && form.querySelector('[data-les-bonus-malus]');
			if (card) refresh(card);
		});
	}

	// Correction par élève : avertit avant de changer d'élève si des points n'ont pas été enregistrés.
	function initDirtyGuard(teacherRootNode) {
		const forms = teacherRootNode.querySelectorAll('form[data-les-dirty-guard]');
		if (!forms.length) return;
		let dirty = false;
		forms.forEach((form) => {
			form.addEventListener('input', () => { dirty = true; });
			form.addEventListener('change', () => { dirty = true; });
			form.addEventListener('submit', () => { dirty = false; });
		});
		teacherRootNode.addEventListener('click', function (event) {
			if (event.target.closest('[data-les-set-points], [data-les-adjust], [data-les-ai-grade], [data-les-ai-grade-bulk], [data-les-criterion]')) {
				dirty = true;
				return;
			}
			const link = event.target.closest('a[data-les-guarded-link], .les-roster-item, .les-segmented a, .les-steps a');
			if (link && dirty && !window.confirm('Des corrections ne sont pas enregistrées. Quitter cette copie sans enregistrer ?')) {
				event.preventDefault();
			}
		});
	}

	// Réglages IA : adapte le modèle proposé, les liens et le champ URL selon le fournisseur choisi.
	function initAiSettingsForm(teacherRootNode) {
		const panel = teacherRootNode.querySelector('[data-les-ai-settings]');
		if (!panel) return;
		const select = panel.querySelector('[data-les-ai-provider][data-les-ai-legacy]');
		const model = panel.querySelector('[data-les-ai-model]');
		const baseUrl = panel.querySelector('[data-les-ai-base-url]');
		const keyLink = panel.querySelector('[data-les-ai-key-link]');
		const modelsLink = panel.querySelector('[data-les-ai-models-link]');
		if (!select) return;
		const update = function () {
			const option = select.options[select.selectedIndex];
			if (!option) return;
			if (model) model.placeholder = option.dataset.defaultModel || 'nom-du-modele';
			if (baseUrl) baseUrl.hidden = select.value !== 'compatible';
			if (keyLink) {
				keyLink.hidden = !option.dataset.keyUrl;
				keyLink.href = option.dataset.keyUrl || '#';
			}
			if (modelsLink) {
				modelsLink.hidden = !option.dataset.modelsUrl;
				modelsLink.href = option.dataset.modelsUrl || '#';
			}
		};
		select.addEventListener('change', update);
		update();
	}
	const root = document.querySelector('[data-les-student]');
	if (!root) {
		return;
	}

	const state = {
		attemptId: null,
		token: null,
		evaluation: null,
		questions: [],
		answers: {},
		secureActive: false,
		listenersActive: false,
		lockActive: false,
		lockUntil: null,
		lockTimer: null,
		expiresAt: null,
		examTimer: null,
		lastBeaconAt: 0,
		localSaveTimer: null,
		storySectionIndex: 0,
		pendingSecurityWarning: '',
		warningCount: 0,
		securityStopped: false,
		securityActionApplied: false,
			awaitingTeacherUnlock: false,
				teacherUnlockReady: false,
				unlockRequested: false,
				unlockPollTimer: null,
				fullscreenRequired: false,
				fullscreenBlocked: false,
				submitConfirmPanel: null,
				timeExpired: false,
				translationInteractionUntil: 0
			};
	const message = root.querySelector('[data-les-message]');
	const exam = root.querySelector('[data-les-exam]');
	const answerForm = root.querySelector('[data-les-answer-form]');
	const examIntro = root.querySelector('[data-les-exam-intro]');
	const questionsArea = root.querySelector('[data-les-questions-area]');
	const beginMessage = root.querySelector('[data-les-begin-message]');
	const lockScreen = root.querySelector('[data-les-lock]');
	const lockTimer = root.querySelector('[data-les-lock-timer]');
	const lockMessage = root.querySelector('[data-les-lock-message]');
	const accessPanel = root.querySelector('.les-access');
	const examHeader = root.querySelector('.les-exam-header');
	const companyBox = root.querySelector('[data-les-company]');
	const fullscreenGuard = root.querySelector('[data-les-fullscreen-guard]');
	const fullscreenMessage = root.querySelector('[data-les-fullscreen-message]');
	const securityAck = root.querySelector('[data-les-security-ack]');
	const securityStart = root.querySelector('[data-les-security-start]');
	const evaluationStart = root.querySelector('[data-les-evaluation-start]');
	const securityAckButton = root.querySelector('[data-les-ack-security]');
	const securityMessage = root.querySelector('[data-les-security-message]');

	/* Safe Exam Browser : clé de configuration donnée par l'API JavaScript de SEB, jointe à chaque demande. */
	function sebInfo() {
		try {
			const s = window.SafeExamBrowser && window.SafeExamBrowser.security;
			if (s && s.configKey) return { ck: String(s.configKey), url: window.location.href.split('#')[0] };
		} catch (error) {}
		return null;
	}
	function inSeb() {
		return !!(window.SafeExamBrowser || /\bSEB\/\d/.test(navigator.userAgent || ''));
	}
	/* LogiOne 1.36.2 : iPad / iPhone (Safari et tous les navigateurs iOS, qui utilisent le moteur de Safari).
	   Le plein écran du navigateur y est peu fiable (il se ferme quand le clavier s'affiche ou au moindre glissement
	   vers le bas) : chaque fermeture comptait comme une sortie → blocage immédiat. En « mode tablette », pas de plein
	   écran : la page doit occuper tout l'écran (pas d'écran partagé ni de barre latérale), et changer d'app, d'onglet
	   ou ouvrir une app par-dessus reste une sortie, comme sur ordinateur. iPadOS se présente comme un Mac : on le
	   reconnaît à son écran tactile. */
	function tabletMode() {
		if (inSeb()) return false;
		const ua = navigator.userAgent || '';
		return /\b(iPad|iPhone|iPod)\b/.test(ua) || (/Macintosh/.test(ua) && (navigator.maxTouchPoints || 0) > 1);
	}
	function fullscreenNeeded() {
		return !inSeb() && !tabletMode();
	}
	/* Largeur de tout l'écran dans l'orientation actuelle (en points CSS). */
	function tabletFullWidth() {
		const a = Math.max(screen.width || 0, screen.height || 0);
		const b = Math.min(screen.width || 0, screen.height || 0);
		let landscape = window.innerWidth > window.innerHeight;
		try {
			if (screen.orientation && screen.orientation.type) landscape = /landscape/.test(screen.orientation.type);
			else if (typeof window.orientation === 'number') landscape = Math.abs(window.orientation) === 90;
		} catch (error) {}
		return landscape ? a : b;
	}
	/* Écran partagé (Split View), Stage Manager ou barre latérale de Safari : la page n'occupe plus tout l'écran. */
	function tabletSplit() {
		if (!tabletMode()) return false;
		const full = tabletFullWidth();
		const w = window.innerWidth;
		return full > 0 && w < full * 0.85 && full - w > 150;
	}
	if (tabletMode()) {
		root.classList.add('les-tablet');
		if (securityStart) {
			const note = document.createElement('div');
			note.className = 'les-tablet-note';
			note.setAttribute('role', 'note');
			note.innerHTML = '<strong>📱 Évaluation sur tablette</strong>' +
				'<p>Sur iPad, l’évaluation ne passe pas en plein écran : elle reste dans le navigateur, sur tout l’écran.</p>' +
				'<ul><li>Pas d’écran partagé, pas d’app à côté ou par-dessus, pas de barre latérale de Safari.</li>' +
				'<li>Revenir à l’écran d’accueil, changer d’app ou d’onglet compte comme une sortie, comme sur un ordinateur.</li>' +
				'<li>Vous pouvez tourner la tablette et utiliser le clavier de l’écran normalement.</li></ul>';
			securityStart.insertBefore(note, securityStart.firstChild);
		}
	}
	try {
		if (window.SafeExamBrowser && window.SafeExamBrowser.security && typeof window.SafeExamBrowser.security.updateKeys === 'function') {
			window.SafeExamBrowser.security.updateKeys(function () {});
		}
	} catch (error) {}
	function appendSeb(body) {
		const seb = sebInfo();
		if (seb) {
			body.append('seb_ck', seb.ck);
			body.append('seb_url', seb.url);
		}
	}

	function post(action, data) {
		const body = new FormData();
		body.append('action', action);
		body.append('nonce', LogiEvalSecure.nonce);
		appendSeb(body);
		Object.keys(data || {}).forEach((key) => {
			const value = data[key];
			body.append(key, value && typeof value === 'object' ? JSON.stringify(value) : value);
		});
		return fetch(LogiEvalSecure.ajaxUrl, { method: 'POST', credentials: 'same-origin', body }).then((response) => response.json());
	}

	function initTeacherMonitoring(teacherRootNode) {
		const panel = teacherRootNode.querySelector('[data-les-monitoring]');
		const body = panel ? panel.querySelector('[data-les-monitoring-body]') : null;
		const status = panel ? panel.querySelector('[data-les-monitoring-status]') : null;
		const evaluationId = panel ? (panel.dataset.evaluationId || '0') : '0';
		const interval = panel ? Math.max(parseInt(panel.dataset.refreshInterval || '10000', 10), 5000) : 10000;
		const baseTitle = document.title;
		let pending = false;

		function updateTitleBadge() {
			if (!body) return;
			let requests = 0;
			let blocked = 0;
			body.querySelectorAll('[data-les-blocked-count]').forEach((node) => {
				blocked += parseInt(node.dataset.lesBlockedCount || '0', 10);
				requests += parseInt(node.dataset.lesRequestCount || '0', 10);
			});
			if (requests > 0) {
				document.title = '(' + requests + ') Déblocage demandé - ' + baseTitle;
			} else if (blocked > 0) {
				document.title = '(' + blocked + ') Élève bloqué - ' + baseTitle;
			} else {
				document.title = baseTitle;
			}
		}

		function refreshMonitoring(force) {
			if (!panel || !body) return Promise.resolve();
			if (pending || (document.hidden && !force)) return Promise.resolve();
			// Un menu « Gérer » est ouvert : on n'efface pas ce que l'enseignant est en train de faire.
			if (!force && body.querySelector('details.les-ctl[open]')) return Promise.resolve();
			pending = true;
			if (status) status.textContent = 'Actualisation du monitoring...';
			return post('logieval_secure_monitoring_snapshot', { evaluation_id: evaluationId }).then((json) => {
				if (json && json.success && json.data && typeof json.data.html === 'string') {
					body.innerHTML = json.data.html;
					updateTitleBadge();
					if (status) {
						status.textContent = 'Actualisation automatique toutes les ' + Math.round(interval / 1000) + ' secondes. Dernière mise à jour : ' + (json.data.refreshed || '');
					}
				} else if (status) {
					status.textContent = (json && json.data && json.data.message) || LogiEvalSecure.i18n.error;
				}
			}).catch(() => {
				if (status) status.textContent = LogiEvalSecure.i18n.error;
			}).finally(() => {
				pending = false;
			});
		}

		// Déblocage d'un élève sans recharger la page (monitoring, registre sécurité, corrections).
		teacherRootNode.addEventListener('submit', function (event) {
			const form = event.target;
			if (!form || !form.querySelector) return;
			const actionField = form.querySelector('input[name="action"][value="logieval_secure_unlock_attempt"]');
			const attemptField = form.querySelector('input[name="attempt_id"]');
			if (!actionField || !attemptField) return;
			event.preventDefault();
			const button = form.querySelector('button[type="submit"], button:not([type])');
			if (button) {
				button.disabled = true;
				button.textContent = 'Déblocage...';
			}
			post('logieval_secure_teacher_unlock', { attempt_id: attemptField.value }).then((json) => {
				if (!json || !json.success) {
					throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
				}
				if (button) button.textContent = 'Élève débloqué';
				if (panel && body) {
					return refreshMonitoring(true);
				}
				window.location.reload();
			}).catch((error) => {
				if (button) {
					button.disabled = false;
					button.textContent = 'Débloquer l’élève';
				}
				window.alert(error && error.message ? error.message : LogiEvalSecure.i18n.error);
			});
		});

		if (!panel || !body) return;
		updateTitleBadge();
		window.setInterval(refreshMonitoring, interval);
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) refreshMonitoring();
		});
	}

	/* Réponse du serveur lue prudemment : si l'hébergement coupe une demande trop longue, il renvoie sa propre page
	   (HTML ou XML) au lieu de JSON ; on l'explique en français au lieu de « Unexpected token '<' ». */
	function postSafe(action, data) {
		// IA choisie dans le panneau de pré-correction (plusieurs fournisseurs connectés).
		if (action.indexOf('logieval_secure_ai_grade') === 0 && (!data || data.provider === undefined)) {
			const choice = document.querySelector('[data-les-ai-provider-choice="grading"]');
			if (choice) data = Object.assign({}, data || {}, { provider: choice.value });
		}
		const body = new FormData();
		body.append('action', action);
		body.append('nonce', LogiEvalSecure.nonce);
		Object.keys(data || {}).forEach((key) => {
			const value = data[key];
			body.append(key, value && typeof value === 'object' ? JSON.stringify(value) : value);
		});
		return fetch(LogiEvalSecure.ajaxUrl, { method: 'POST', credentials: 'same-origin', body }).then((response) => response.text().then((text) => {
			try {
				return JSON.parse(text);
			} catch (e) {
				const err = new Error('Le serveur n’a pas répondu correctement (code ' + response.status + ', délai dépassé ou protection de l’hébergement). Les propositions déjà faites sont enregistrées : relancez pour continuer.');
				err.serverCut = true;
				throw err;
			}
		}));
	}

	/* Pré-correction IA par petits morceaux : une copie à la fois, chaque demande limitée à ~20 s côté serveur.
	   Par défaut, seules les réponses sans proposition IA sont traitées (pas de double coût). */
	function runBulkAiGrading(teacherRootNode, bulkButton, status, evaluationId, questionId) {
		const setStatus = (text, cls) => {
			if (!status) return;
			status.textContent = text;
			status.className = 'les-ai-grade-status' + (cls ? ' ' + cls : '');
		};
		const redoBox = bulkButton.parentElement ? bulkButton.parentElement.querySelector('[data-les-ai-redo]') : null;
		const redo = !!(redoBox ? redoBox.checked : false);
		const single = bulkButton.dataset.attemptId || '';
		let processed = 0;
		let skipped = 0;
		const failed = [];
		setStatus('Préparation de la pré-correction…');
		return postSafe('logieval_secure_ai_grade_bulk', { evaluation_id: evaluationId, question_id: questionId, attempt_id: single, plan: 1 }).then((json) => {
			if (!json || !json.success || !json.data) {
				throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
			}
			const copies = (json.data.copies || []).filter((c) => redo || c.todo > 0);
			const since = json.data.started_at || '';
			const already = (json.data.copies || []).reduce((n, c) => n + (redo ? 0 : c.done), 0);
			if (!copies.length) {
				setStatus(already ? 'Toutes les réponses ont déjà une proposition IA (' + already + '). Cochez « Refaire aussi les réponses déjà pré-corrigées » pour tout refaire.' : 'Aucune copie à pré-corriger.', 'is-ok');
				return null;
			}
			let index = 0;
			const nextCopy = () => {
				if (index >= copies.length) return Promise.resolve();
				const copy = copies[index];
				const skipQuestions = [];
				let calls = 0;
				const step = () => {
					calls++;
					setStatus('Copie ' + (index + 1) + ' sur ' + copies.length + ' · ' + copy.name + ' · ' + processed + ' proposition(s) enregistrée(s)…');
					return postSafe('logieval_secure_ai_grade_bulk', {
						evaluation_id: evaluationId,
						question_id: questionId,
						attempt_id: copy.attempt_id,
						only_missing: redo ? '' : 1,
						since: redo ? since : '',
						skip_questions: skipQuestions.join(',')
					}).then((res) => {
						if (!res || !res.success || !res.data) {
							throw new Error((res && res.data && res.data.message) || LogiEvalSecure.i18n.error);
						}
						processed += res.data.processed || 0;
						(res.data.results || []).forEach((result) => {
							const selector = '[data-les-ai-grade-target][data-attempt-id="' + String(result.attempt_id) + '"][data-question-id="' + String(result.question_id) + '"]';
							teacherRootNode.querySelectorAll(selector).forEach((container) => applyAiGradeResult(container, result));
						});
						(res.data.failed || []).forEach((f) => {
							failed.push(f);
							skipQuestions.push(f.question_id);
						});
						if (res.data.remaining) {
							if (calls >= 40) {
								throw new Error('La copie de ' + copy.name + ' n’avance plus. Les propositions déjà faites sont enregistrées.');
							}
							return step();
						}
						skipped += res.data.skipped || 0;
						return null;
					});
				};
				return step().then(() => {
					index++;
					return nextCopy();
				});
			};
			return nextCopy().then(() => {
				const parts = [processed + ' proposition(s) IA enregistrée(s) sur ' + copies.length + ' copie(s)'];
				if (!redo ? already : 0) parts.push(already + ' déjà faite(s) conservée(s)');
				if (failed.length) parts.push(failed.length + ' erreur(s) : ' + failed.slice(0, 2).map((f) => f.message).join(' ; '));
				setStatus(parts.join(' · '), failed.length ? 'is-error' : 'is-ok');
			});
		}).catch((error) => {
			const done = processed ? ' (' + processed + ' proposition(s) déjà enregistrée(s) pendant cette série)' : '';
			setStatus((error && error.message ? error.message : LogiEvalSecure.i18n.error) + done, 'is-error');
		});
	}

	/* Case « Refaire aussi les réponses déjà pré-corrigées » à côté de chaque bouton de pré-correction groupée. */
	function addAiRedoOptions(teacherRootNode) {
		teacherRootNode.querySelectorAll('[data-les-ai-grade-bulk]').forEach((button) => {
			if (!button.parentElement || button.parentElement.querySelector('[data-les-ai-redo]')) return;
			const label = document.createElement('label');
			label.className = 'les-ai-redo';
			const box = document.createElement('input');
			box.type = 'checkbox';
			box.setAttribute('data-les-ai-redo', '');
			label.appendChild(box);
			label.appendChild(document.createTextNode(' Refaire aussi les réponses déjà pré-corrigées'));
			button.insertAdjacentElement('afterend', label);
		});
	}

	/* LogiOne 1.32 : « ⚖️ Comparer deux IA » sur une question — même réponse corrigée par deux IA, côte à côte. */
	function providerOptions(scope) {
		const sel = (scope && scope.querySelector('[data-les-ai-provider-choice]')) || document.querySelector('[data-les-ai-provider-choice="grading"]') || document.querySelector('[data-les-ai-provider-choice]');
		return sel ? Array.prototype.map.call(sel.options, function (o) { return { value: o.value, label: o.textContent }; }) : [];
	}
	function compareSelects(opts, current) {
		const mk = function (name, value) {
			return '<label>' + name + ' <select data-les-cmp-' + name.toLowerCase().slice(-1) + '>' + opts.map(function (o) {
				return '<option value="' + escapeAttr(o.value) + '"' + (o.value === value ? ' selected' : '') + '>' + escapeHtml(o.label) + '</option>';
			}).join('') + '</select></label>';
		};
		const a = current || (opts[0] && opts[0].value);
		const other = opts.filter(function (o) { return o.value !== a; })[0];
		return mk('IA A', a) + mk('IA B', other ? other.value : a);
	}
	function compareColumn(slot, r) {
		if (!r) return '';
		if (r.error) return '<div class="les-cmp-col"><h4>' + escapeHtml(slot + ' · ' + r.label) + '</h4><p class="les-ai-grade-status is-error">' + escapeHtml(r.error) + '</p></div>';
		return '<div class="les-cmp-col">' +
			'<h4>' + escapeHtml(slot + ' · ' + r.label) + '</h4>' +
			'<p class="les-cmp-points"><strong>' + escapeHtml(r.points) + ' pt</strong> · ' + escapeHtml(String(r.seconds)) + ' s' + (r.needs_review ? ' · à vérifier' : '') + '</p>' +
			aiAnalysisHtml(r.analysis || '') +
			'<p class="les-cmp-comment"><em>' + escapeHtml(r.comment || '') + '</em></p>' +
			'<button type="button" class="button button-primary" data-les-cmp-keep="' + slot.toLowerCase() + '">Garder la correction ' + slot + '</button>' +
			'</div>';
	}
	function initAiCompare() {
	if (document.__lesAiCompare) return;
	document.__lesAiCompare = true;
	document.addEventListener('click', function (event) {
		const open = event.target.closest('[data-les-ai-compare-open]');
		if (open) {
			const scope = open.closest('.les-answer-grade') || open.closest('tr, .les-qcard') || document;
			const panel = scope.querySelector('[data-les-ai-compare]');
			if (!panel) return;
			const opts = providerOptions(open.parentElement);
			const current = open.parentElement && open.parentElement.querySelector('[data-les-ai-provider-choice]') ? open.parentElement.querySelector('[data-les-ai-provider-choice]').value : '';
			panel.innerHTML = '<div class="les-cmp-head"><strong>⚖️ Comparer deux IA sur cette réponse</strong> ' + compareSelects(opts, current) +
				'<button type="button" class="button button-primary" data-les-cmp-run>Lancer la comparaison</button> <button type="button" class="button-link" data-les-cmp-close>Fermer</button>' +
				'<span class="les-ai-grade-status" data-les-cmp-status></span></div><div class="les-cmp-cols" data-les-cmp-cols></div>' +
				'<p class="description">Les deux corrections ne sont pas enregistrées tant que vous n’en gardez pas une. Chacune coûte une pré-correction.</p>';
			panel.hidden = false;
			panel.dataset.attemptId = open.dataset.attemptId;
			panel.dataset.questionId = open.dataset.questionId;
			return;
		}
		const panel = event.target.closest('[data-les-ai-compare]');
		if (!panel) return;
		if (event.target.closest('[data-les-cmp-close]')) { panel.hidden = true; return; }
		const status = panel.querySelector('[data-les-cmp-status]');
		if (event.target.closest('[data-les-cmp-run]')) {
			const btn = event.target.closest('[data-les-cmp-run]');
			const a = panel.querySelector('[data-les-cmp-a]').value, b = panel.querySelector('[data-les-cmp-b]').value;
			if (a === b) { status.textContent = 'Choisissez deux IA différentes.'; status.className = 'les-ai-grade-status is-error'; return; }
			btn.disabled = true;
			status.textContent = 'Les deux IA corrigent… (cela peut prendre une minute)';
			status.className = 'les-ai-grade-status';
			postSafe('logieval_secure_ai_grade_compare', { attempt_id: panel.dataset.attemptId, question_id: panel.dataset.questionId, provider_a: a, provider_b: b, provider: a }).then(function (json) {
				if (!json || !json.success) throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
				panel.querySelector('[data-les-cmp-cols]').innerHTML = compareColumn('A', json.data.a) + compareColumn('B', json.data.b);
				status.textContent = 'Comparez, puis gardez la correction qui vous convient.';
				status.className = 'les-ai-grade-status is-ok';
			}).catch(function (error) {
				status.textContent = error && error.message ? error.message : LogiEvalSecure.i18n.error;
				status.className = 'les-ai-grade-status is-error';
			}).finally(function () { btn.disabled = false; });
			return;
		}
		const keep = event.target.closest('[data-les-cmp-keep]');
		if (keep) {
			keep.disabled = true;
			postSafe('logieval_secure_ai_grade_keep', { attempt_id: panel.dataset.attemptId, question_id: panel.dataset.questionId, choice: keep.dataset.lesCmpKeep, provider: '' }).then(function (json) {
				if (!json || !json.success) throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
				const container = panel.closest('.les-answer-grade') || panel.closest('tr, .les-qcard');
				applyAiGradeResult(container, json.data, true);
				panel.hidden = true;
				const st = container ? container.querySelector('.les-ai-grade-status') : null;
				if (st) { st.textContent = 'Correction ' + keep.dataset.lesCmpKeep.toUpperCase() + ' gardée (' + (json.data.label || '') + ').'; st.className = 'les-ai-grade-status is-ok'; }
			}).catch(function (error) {
				keep.disabled = false;
				status.textContent = error && error.message ? error.message : LogiEvalSecure.i18n.error;
				status.className = 'les-ai-grade-status is-error';
			});
		}
	});
	}

	function initTeacherAiGrading(teacherRootNode) {
		initAiCompare();
		addAiRedoOptions(teacherRootNode);
		/* « Régénérer le commentaire avec l'IA » : nouveau texte d'après les points et critères de l'enseignant ; les points ne bougent pas. */
		teacherRootNode.addEventListener('click', function (event) {
			const regen = event.target.closest('[data-les-regen-comment]');
			if (!regen) return;
			event.preventDefault();
			const scope = regen.closest('.les-answer-grade') || regen.closest('tr, .les-qcard') || regen.closest('.les-question-grade-form');
			const box = regen.closest('details') || scope;
			const textarea = box ? box.querySelector('textarea') : null;
			const status = regen.parentElement ? regen.parentElement.querySelector('.les-regen-status') : null;
			const pointsInput = scope ? scope.querySelector('input[name^="manual_points"], input[name^="question_manual_points"]') : null;
			if (!textarea) return;
			const checked = [];
			Array.prototype.forEach.call(scope ? scope.querySelectorAll('[data-les-criterion]') : [], function (b, k) { if (b.checked) checked.push(k); });
			regen.disabled = true;
			if (status) { status.textContent = 'L’IA rédige le commentaire…'; status.className = 'les-regen-status'; }
			postSafe('logieval_secure_ai_grade_comment', {
				attempt_id: regen.dataset.attemptId,
				question_id: regen.dataset.questionId,
				points: pointsInput ? pointsInput.value : '',
				checked: JSON.stringify(checked),
				current: textarea.value
			}).then(function (json) {
				if (!json || !json.success || !json.data) throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
				textarea.value = json.data.comment || '';
				textarea.dataset.lastAi = textarea.value;
				const pts = parseFloat(String(json.data.points || (pointsInput ? pointsInput.value : '')).replace(',', '.'));
				if (!isNaN(pts)) textarea.dataset.regenFor = String(pts);
				textarea.dispatchEvent(new Event('input', { bubbles: true }));
				textarea.dispatchEvent(new Event('change', { bubbles: true }));
				if (status) { status.textContent = 'Nouveau commentaire (vos points n’ont pas changé). Pensez à enregistrer.'; status.className = 'les-regen-status is-ok'; }
			}).catch(function (error) {
				if (status) { status.textContent = error && error.message ? error.message : LogiEvalSecure.i18n.error; status.className = 'les-regen-status is-error'; }
			}).finally(function () { regen.disabled = false; });
		});
		teacherRootNode.addEventListener('click', function (event) {
			const bulkButton = event.target.closest('[data-les-ai-grade-bulk]');
			if (bulkButton) {
				const status = bulkButton.parentElement ? bulkButton.parentElement.querySelector('.les-ai-grade-status') : null;
				const evaluationId = bulkButton.dataset.evaluationId || '';
				const questionId = bulkButton.dataset.questionId || '';
				if (!evaluationId) return;

				bulkButton.disabled = true;
				runBulkAiGrading(teacherRootNode, bulkButton, status, evaluationId, questionId).finally(() => {
					bulkButton.disabled = false;
				});
				return;
			}

			const button = event.target.closest('[data-les-ai-grade]');
			if (!button) return;
			const container = button.closest('.les-answer-grade') || button.closest('.les-question-grade-form') || button.closest('tr, .les-qcard');
			const status = button.parentElement ? button.parentElement.querySelector('.les-ai-grade-status') : null;
			const attemptId = button.dataset.attemptId || '';
			const questionId = button.dataset.questionId || '';
			if (!attemptId || !questionId || !container) return;

			button.disabled = true;
			if (status) {
				status.textContent = 'Pré-correction en cours...';
				status.className = 'les-ai-grade-status';
			}
			const localChoice = button.parentElement ? button.parentElement.querySelector('[data-les-ai-provider-choice]') : null;
			const gradeData = { attempt_id: attemptId, question_id: questionId };
			if (localChoice) gradeData.provider = localChoice.value;
			postSafe('logieval_secure_ai_grade_answer', gradeData).then((json) => {
				if (!json || !json.success || !json.data) {
					throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
				}
				applyAiGradeResult(container, json.data, true);
				if (status) {
					const confidence = typeof json.data.confidence === 'number' ? Math.round(json.data.confidence * 100) : null;
					status.textContent = 'Proposition IA appliquée' + (confidence !== null ? ' (' + confidence + '%)' : '') + (json.data.needs_review ? ' - à vérifier' : '') + (json.data.comment ? ' · commentaire réécrit' : '');
					status.className = 'les-ai-grade-status is-ok';
				}
			}).catch((error) => {
				if (status) {
					status.textContent = error && error.message ? error.message : LogiEvalSecure.i18n.error;
					status.className = 'les-ai-grade-status is-error';
				}
			}).finally(() => {
				button.disabled = false;
			});
		});
	}

	/* Lien « ↶ Remettre l'ancien commentaire » sous le commentaire réécrit par la pré-correction. */
	function offerCommentUndo(textarea, previous) {
		const host = textarea.parentElement;
		if (!host) return;
		let link = host.querySelector('[data-les-comment-undo]');
		if (!link) {
			link = document.createElement('button');
			link.type = 'button';
			link.className = 'button-link les-comment-undo';
			link.setAttribute('data-les-comment-undo', '');
			link.textContent = '↶ Remettre l’ancien commentaire';
			textarea.insertAdjacentElement('afterend', link);
			link.addEventListener('click', function (event) {
				event.preventDefault();
				textarea.value = link._lesPrevious || '';
				textarea.dataset.lastAi = '';
				textarea.dispatchEvent(new Event('input', { bubbles: true }));
				textarea.dispatchEvent(new Event('change', { bubbles: true }));
				link.remove();
			});
		}
		if (typeof link._lesPrevious !== 'string') link._lesPrevious = previous; // le texte d'avant la 1re réécriture
	}

	/* Même découpage que côté serveur : un point par ligne, sinon un par phrase. */
	function aiAnalysisItems(text) {
		const items = [];
		String(text || '').split(/[\r\n]+/).forEach((line) => {
			line = line.replace(/^\s*(?:[-–•*]|\d+[.)])\s+/, '').trim();
			if (!line) return;
			if (/^(?:[✓✔◐✗✘→]|🧠)/u.test(line)) { items.push(line); return; }
			line.split(/(?<=[.!?…»])\s+(?=(?:Crit[èe]re\s*\d|[A-ZÀÂÉÈÊÎÔÛÇ«]))/u).forEach((s) => { s = s.trim(); if (s) items.push(s); });
		});
		return items;
	}

	/* Même classement que côté serveur : ok, partial, miss, note, end (conclusion). */
	function aiAnalysisStatus(item) {
		const t = String(item || '').trim();
		if (/^[✓✔]/u.test(t)) return 'ok';
		if (/^◐/u.test(t)) return 'partial';
		if (/^[✗✘]/u.test(t)) return 'miss';
		if (/^→/u.test(t)) return 'end';
		const lower = t.toLowerCase();
		if (/^conclusion\b/.test(lower)) return 'end';
		if (/(non rempli|pas rempli|n['’]est pas (rempli|présent|correct|juste|respecté)|non respecté|manquant|il manque|absent|incorrect|erron|ne (mentionne|cite|donne|dit|explique|précise|justifie|indique|identifie) (pas|rien)|(^|[^0-9])0 point)/.test(lower)) return 'miss';
		if (lower.indexOf('partiel') !== -1) return 'partial';
		if (/(rempli|respecté|présent|correct|juste|cohérent|validé|réussi)/.test(lower)) return 'ok';
		return 'note';
	}

	function aiAnalysisClass(item) {
		const st = aiAnalysisStatus(item);
		return st === 'ok' || st === 'partial' || st === 'miss' ? 'is-' + st : '';
	}

	/* Analyse séparée : réussi d'un côté, pas réussi / à revoir de l'autre, puis les remarques. */
	function aiAnalysisHtml(text) {
		const groups = { ok: [], miss: [], note: [] };
		let understood = '';
		aiAnalysisItems(text).forEach((item) => {
			if (/^🧠/u.test(item.trim())) { understood += '<p class="les-ai-understood">' + escapeHtml(item.trim()) + '</p>'; return; }
			const st = aiAnalysisStatus(item);
			const clean = item.replace(/^[✓✔◐✗✘→]\s*/u, '').trim();
			if (!clean) return;
			if (st === 'ok') groups.ok.push(['is-ok', clean]);
			else if (st === 'miss' || st === 'partial') groups.miss.push(['is-' + st, clean]);
			else groups.note.push([st === 'end' ? 'is-end' : '', clean]);
		});
		const list = (rows, cls) => '<ul class="les-ai-analysis-list' + (cls ? ' ' + cls : '') + '">' + rows.map((r) => '<li class="' + r[0] + '">' + escapeHtml(r[1]) + '</li>').join('') + '</ul>';
		let html = understood;
		if (groups.ok.length || groups.miss.length) {
			html += '<div class="les-ai-split"><div class="les-ai-col is-ok"><strong class="les-ai-col-title">✓ Réussi</strong>' + (groups.ok.length ? list(groups.ok) : '<p class="les-ai-col-empty">Rien de réussi selon l’IA.</p>') + '</div>' +
				'<div class="les-ai-col is-miss"><strong class="les-ai-col-title">✗ Pas réussi / à revoir</strong>' + (groups.miss.length ? list(groups.miss) : '<p class="les-ai-col-empty">Rien à revoir selon l’IA.</p>') + '</div></div>';
		}
		if (groups.note.length) html += list(groups.note, 'les-ai-notes');
		return html;
	}

	/* 1.36.3 : force = pré-correction relancée par l'enseignant (bouton ou « garder » une comparaison) : le commentaire
	   pour l'élève est TOUJOURS réécrit d'après la nouvelle correction ; l'ancien reste récupérable (« Remettre l'ancien »). */
	function applyAiGradeResult(container, data, force) {
		if (!container || !data) return;
		const scope = container.closest('tr, .les-qcard') || container;
		const pointsInput = container.querySelector('input[name^="manual_points"], input[name^="question_manual_points"]') || scope.querySelector('input[name^="manual_points"], input[name^="question_manual_points"]');
		const commentInput = scope.querySelector('textarea[name^="teacher_comment"], textarea[name^="question_teacher_comment"]');
		if (pointsInput) {
			pointsInput.value = data.points || '0';
			pointsInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		const crit = window.lesAiCriteria;
		let commentText = data.comment || '';
		if (crit) {
			crit.mark(scope, data.analysis || '');
			commentText = crit.comment(scope, crit.apply(scope), data.comment || '');
		}
		if (commentInput && commentText && (force || !commentInput.value.trim() || commentInput.value === commentInput.dataset.autoComment || commentInput.value === commentInput.dataset.lastAi)) {
			const previous = commentInput.value;
			if (force && previous.trim() && previous !== commentText) offerCommentUndo(commentInput, previous);
			commentInput.value = commentText;
			commentInput.dataset.lastAi = commentText;
			const details = commentInput.closest('details');
			if (details) details.open = true;
			commentInput.dataset.autoComment = commentText;
			commentInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		const analysis = scope.querySelector('[data-les-ai-analysis]');
		if (analysis) {
			const confidence = typeof data.confidence === 'number' ? Math.round(data.confidence * 100) : null;
			let html = '<strong>Analyse IA enregistrée</strong>';
			html += '<p>Proposition : ' + escapeHtml(data.points || '0') + ' point(s)';
			if (confidence !== null) html += ' - confiance ' + confidence + '%';
			if (data.needs_review) html += ' - à vérifier';
			html += '</p>';
			if (data.analysis) html += aiAnalysisHtml(data.analysis);
			if (data.comment) html += '<p><em>' + escapeHtml(data.comment) + '</em></p>';
			analysis.innerHTML = html;
			analysis.hidden = false;
		}
		const accept = scope.querySelector('[data-les-ai-accept]');
		if (accept) {
			accept.dataset.points = data.points || '0';
			accept.dataset.comment = data.comment || '';
			accept.textContent = (accept.dataset.label || 'Points de l’IA (%s)').replace('%s', data.points || '0');
			accept.hidden = false;
		}
		const form = container.closest('form');
		if (form) form.querySelectorAll('[data-les-ai-accept-all]').forEach((b) => { b.hidden = false; });
		const state = scope.querySelector('.les-save-state');
		if (state) {
			state.textContent = 'À enregistrer';
			state.className = 'les-save-state les-alert-badge is-warning';
		}
		if (window.lesMarkManualPoints) window.lesMarkManualPoints();
	}

	/* Évaluation réservée à Safe Exam Browser : bouton d'ouverture dans SEB + lien de téléchargement. */
	function showSebPanel(data) {
		let panel = root.querySelector('[data-les-seb-panel]');
		if (!panel) {
			panel = document.createElement('div');
			panel.className = 'les-seb-panel';
			panel.setAttribute('data-les-seb-panel', '');
			const form = root.querySelector('[data-les-start-form]');
			(form && form.parentNode ? form.parentNode : root).insertBefore(panel, form ? form.nextSibling : null);
		}
		panel.innerHTML = '<strong>🔒 Évaluation dans Safe Exam Browser</strong>' +
			'<ol><li>Cliquez sur « Ouvrir dans Safe Exam Browser » (acceptez l’ouverture du programme).</li>' +
			'<li>Connectez-vous avec votre compte, puis entrez à nouveau votre code.</li></ol>' +
			'<div class="les-actions"><a class="button les-seb-open" data-les-seb-open></a></div>' +
			'<p class="les-seb-help">Safe Exam Browser n’est pas installé sur cet ordinateur ? <a target="_blank" rel="noopener" data-les-seb-dl>Le télécharger</a> ou prévenez votre enseignant.</p>';
		const open = panel.querySelector('[data-les-seb-open]');
		open.textContent = 'Ouvrir dans Safe Exam Browser';
		open.href = data.seb_link || '#';
		const dl = panel.querySelector('[data-les-seb-dl]');
		dl.href = data.seb_download || LogiEvalSecure.sebDownload || 'https://safeexambrowser.org/download_en.html';
		if (data.reason === 'bad_key' || inSeb()) panel.querySelector('ol').hidden = true;
		panel.hidden = false;
	}
	function hideSebPanel() {
		const panel = root.querySelector('[data-les-seb-panel]');
		if (panel) panel.hidden = true;
	}

	function setMessage(text, isError) {
		if (!message) return;
		message.textContent = text || '';
		message.className = 'les-message' + (isError ? ' is-error' : '');
	}

	function hideAccessPanel() {
		if (accessPanel) {
			accessPanel.hidden = true;
		}
		setMessage('', false);
	}

	function showSecurityWarning(text) {
		const warning = text || exitWarningText();
		state.warningCount += 1;
		state.pendingSecurityWarning = warning;
		setMessage(warning, true);
		if (beginMessage) {
			beginMessage.textContent = warning;
			beginMessage.className = 'les-message is-error';
		}
		state.pendingSecurityWarning = '';
	}

	function currentFullscreenElement() {
		return document.fullscreenElement || document.webkitFullscreenElement || null;
	}

	// Doit être appelée directement dans le clic de l'élève (geste utilisateur), sans attendre une requête serveur,
	// sinon Safari / Firefox refusent le plein écran.
	function requestExamFullscreen() {
		if (tabletMode()) {
			return Promise.resolve(true);
		}
		if (currentFullscreenElement()) {
			return Promise.resolve(true);
		}
		const el = document.documentElement;
		const request = el.requestFullscreen || el.webkitRequestFullscreen || el.webkitRequestFullScreen;
		if (!request) {
			return Promise.resolve(false);
		}
		try {
			const result = request.call(el);
			if (result && typeof result.then === 'function') {
				return result.then(function () {
					return true;
				}).catch(function () {
					return !!currentFullscreenElement();
				});
			}
			return new Promise(function (resolve) {
				setTimeout(function () {
					resolve(!!currentFullscreenElement());
				}, 500);
			});
		} catch (error) {
			return Promise.resolve(false);
		}
	}

	function exitExamFullscreen() {
		if (!currentFullscreenElement()) return;
		const exit = document.exitFullscreen || document.webkitExitFullscreen;
		if (!exit) return;
		try {
			const result = exit.call(document);
			if (result && typeof result.catch === 'function') result.catch(function () {});
		} catch (error) {}
	}

	function showFullscreenGuard(text) {
		if (!state.fullscreenRequired || state.securityStopped) return;
		state.fullscreenBlocked = true;
		// Écran accroché directement au <body> : un bloc du thème (transformations, débordement caché…) ne peut plus le masquer.
		if (fullscreenGuard && fullscreenGuard.parentNode && !fullscreenGuard.parentNode.classList.contains('les-guard-host')) {
			const host = document.createElement('div');
			host.className = 'logieval-secure les-guard-host';
			document.body.appendChild(host);
			host.appendChild(fullscreenGuard);
		}
		if (fullscreenGuard) fullscreenGuard.hidden = false;
		if (questionsArea) questionsArea.classList.add('is-fullscreen-blocked');
		if (fullscreenMessage) {
			fullscreenMessage.textContent = text || 'Réactivez le plein écran pour continuer.';
			fullscreenMessage.className = 'les-message is-error';
		}
	}

		function hideFullscreenGuard() {
			state.fullscreenBlocked = false;
			if (fullscreenGuard) fullscreenGuard.hidden = true;
		if (questionsArea) questionsArea.classList.remove('is-fullscreen-blocked');
		if (fullscreenMessage) {
			fullscreenMessage.textContent = '';
			fullscreenMessage.className = 'les-message';
		}
	}

	function renderQuestionImage(question) {
		const image = question && question.image;
		if (!image || !image.url) return '';
		return '<figure class="les-question-image"><button type="button" class="les-zoomable" data-les-zoom-image="' + escapeAttr(image.full || image.url) + '" aria-label="Agrandir l’image">' +
			'<img src="' + escapeAttr(image.url) + '" alt="" draggable="false"><span class="les-zoom-hint">🔍 Agrandir</span></button></figure>';
	}

	/* Lecture audio des questions (LogiOne 1.24.7) : synthèse vocale du navigateur, en français, sur l'ordinateur de l'élève
	   (rien n'est envoyé sur internet). Lit ce qui est affiché : contexte, question, choix de réponse. Jamais la réponse attendue. */
	/* LogiOne 1.34 : aménagements de l'élève connecté (temps en plus, lecture audio, zoom). */
	function accommodations() {
		return (state.evaluation && state.evaluation.accommodations) || {};
	}
	function audioAllowed() {
		return !!accommodations().audio;
	}
	function applyAccommodations() {
		const acc = accommodations();
		const zoom = parseInt(acc.zoom, 10) || 100;
		root.classList.remove('les-zoom-125', 'les-zoom-150');
		if (zoom === 125 || zoom === 150) root.classList.add('les-zoom-' + zoom);
	}
	function accommodationsHtml() {
		const acc = accommodations();
		const items = [];
		const pct = parseInt(acc.extra_pct, 10) || 0;
		const base = parseInt(state.evaluation && state.evaluation.base_duration_minutes, 10) || 0;
		const mine = parseInt(state.evaluation && state.evaluation.duration_minutes, 10) || 0;
		if (pct) items.push('<li>⏱ <strong>Temps supplémentaire</strong> : ' + (base && mine ? mine + ' minutes au lieu de ' + base : (pct === 33 ? 'un tiers de plus' : '+' + pct + ' %')) + '.</li>');
		if (acc.audio) items.push('<li>🔊 <strong>Lecture audio</strong> : un bouton « Écouter » lit chaque question à voix haute.</li>');
		if ((parseInt(acc.zoom, 10) || 100) > 100) items.push('<li>🔍 <strong>Gros caractères</strong> : les questions sont affichées à ' + acc.zoom + ' %.</li>');
		return items.length ? '<div class="les-acc-student"><strong>Vos aménagements</strong><ul>' + items.join('') + '</ul></div>' : '';
	}

	const tts = {
		synth: ('speechSynthesis' in window && 'SpeechSynthesisUtterance' in window) ? window.speechSynthesis : null,
		voice: null,
		current: null,
		queue: [],
		rates: { lente: 0.8, normale: 1, rapide: 1.25 },
		rate() {
			let key = 'normale';
			try { key = window.localStorage.getItem('les_tts_rate') || 'normale'; } catch (e) { /* défaut */ }
			return this.rates[key] ? key : 'normale';
		},
		pickVoice() {
			if (!this.synth) return null;
			const voices = this.synth.getVoices() || [];
			const fr = voices.filter((v) => /^fr/i.test(v.lang || ''));
			this.voice = fr.find((v) => /fr[-_]CH/i.test(v.lang)) || fr.find((v) => /fr[-_]FR/i.test(v.lang)) || fr[0] || null;
			return this.voice;
		},
		sentences(text) {
			return String(text || '').replace(/\s+/g, ' ').trim().split(/(?<=[.!?…])\s+/).filter((x) => x.trim() !== '');
		},
		parts(container) {
			const out = [];
			const add = (el, text, prefix) => {
				const clean = String(text || '').replace(/\s+/g, ' ').trim();
				if (el && clean) out.push({ el, text: (prefix || '') + clean });
			};
			if (container.matches('[data-story-intro]')) {
				add(container.querySelector('.les-story-intro-text'), container.querySelector('.les-story-intro-text') ? container.querySelector('.les-story-intro-text').textContent : '');
				return out;
			}
			const context = container.querySelector(':scope > .les-question-context');
			if (context) add(context, context.textContent);
			const title = container.querySelector('.les-question-title');
			if (title) add(title, title.textContent.replace(/^\s*(\d+)\.\s*/, 'Question $1. '));
			const options = Array.prototype.map.call(container.querySelectorAll('.les-options label'), (l) => l.textContent.trim()).filter(Boolean);
			const optionsBox = container.querySelector('.les-options');
			if (options.length && optionsBox) add(optionsBox, options.join(' ; ') + '.', 'Choix possibles : ');
			const items = Array.prototype.map.call(container.querySelectorAll('.les-order .les-order-item .les-order-label'), (l) => l.textContent.trim()).filter(Boolean);
			const list = container.querySelector('.les-order');
			if (items.length && list) add(list, items.join(' ; ') + '.', 'Éléments à remettre dans l’ordre : ');
			if (container.querySelector('[data-tfj-justification]')) add(container.querySelector('.les-tfj-justify') || title, 'Justifie ta réponse.');
			return out;
		},
		stop() {
			if (this.synth) this.synth.cancel();
			this.queue = [];
			root.querySelectorAll('.les-tts-current').forEach((el) => el.classList.remove('les-tts-current'));
			if (this.current) this.setState(this.current, 'idle');
			this.current = null;
		},
		setState(bar, mode) {
			if (!bar) return;
			bar.dataset.mode = mode;
			const play = bar.querySelector('[data-les-tts-play]');
			const stop = bar.querySelector('[data-les-tts-stop]');
			if (play) play.textContent = mode === 'speaking' ? '⏸ Pause' : (mode === 'paused' ? '▶ Reprendre' : '🔊 Écouter');
			if (stop) stop.hidden = mode === 'idle';
		},
		next() {
			const item = this.queue.shift();
			root.querySelectorAll('.les-tts-current').forEach((el) => el.classList.remove('les-tts-current'));
			if (!item) { this.setState(this.current, 'idle'); this.current = null; return; }
			item.el.classList.add('les-tts-current');
			const u = new window.SpeechSynthesisUtterance(item.text);
			u.lang = this.voice ? this.voice.lang : 'fr-FR';
			if (this.voice) u.voice = this.voice;
			u.rate = this.rates[this.rate()];
			u.onend = () => { if (this.current) this.next(); };
			u.onerror = () => { if (this.current) this.next(); };
			this.synth.speak(u);
		},
		play(bar) {
			const container = bar.closest('.les-question, [data-story-intro]');
			if (!this.synth || !container) return;
			if (this.current === bar && bar.dataset.mode === 'speaking') { this.synth.pause(); this.setState(bar, 'paused'); return; }
			if (this.current === bar && bar.dataset.mode === 'paused') { this.synth.resume(); this.setState(bar, 'speaking'); return; }
			this.stop();
			this.pickVoice();
			const note = bar.querySelector('.les-tts-note');
			if (note) note.hidden = !!this.voice;
			this.current = bar;
			this.queue = [];
			this.parts(container).forEach((part) => this.sentences(part.text).forEach((sentence) => this.queue.push({ el: part.el, text: sentence })));
			this.setState(bar, 'speaking');
			this.next();
		},
		bar() {
			// 1.34 : la lecture audio est un aménagement : seuls les élèves qui l'ont reçoivent le bouton.
			if (!this.synth || !audioAllowed()) return null;
			const bar = document.createElement('div');
			bar.className = 'les-tts-bar';
			bar.dataset.mode = 'idle';
			const rate = this.rate();
			bar.innerHTML = '<button type="button" class="les-tts-play" data-les-tts-play>🔊 Écouter</button>' +
				'<button type="button" class="les-tts-stop" data-les-tts-stop hidden>⏹ Arrêter</button>' +
				'<label class="les-tts-rate">Vitesse <select data-les-tts-rate>' + Object.keys(this.rates).map((k) => '<option value="' + k + '"' + (k === rate ? ' selected' : '') + '>' + k + '</option>').join('') + '</select></label>' +
				'<span class="les-tts-note" hidden>Aucune voix française n’est installée sur cet ordinateur : la lecture peut avoir un accent.</span>';
			return bar;
		}
	};
	if (tts.synth) {
		tts.pickVoice();
		if ('onvoiceschanged' in tts.synth) tts.synth.addEventListener('voiceschanged', () => tts.pickVoice());
		root.addEventListener('click', (event) => {
			const play = event.target.closest('[data-les-tts-play]');
			if (play) { tts.play(play.closest('.les-tts-bar')); return; }
			if (event.target.closest('[data-les-tts-stop]')) tts.stop();
		});
		root.addEventListener('change', (event) => {
			const select = event.target.closest('[data-les-tts-rate]');
			if (!select) return;
			try { window.localStorage.setItem('les_tts_rate', select.value); } catch (e) { /* non mémorisé */ }
			root.querySelectorAll('[data-les-tts-rate]').forEach((other) => { other.value = select.value; });
		});
		document.addEventListener('visibilitychange', () => { if (document.hidden) tts.stop(); });
		window.addEventListener('pagehide', () => tts.stop());
	}

	function renderQuestion(question, index, previousQuestion, suppressContext) {
		const options = parseOptions(question.options);
		const savedAnswer = normalizedSavedAnswer(question.id);
		const field = document.createElement('fieldset');
		field.className = 'les-question';
		field.dataset.questionId = question.id;
		field.innerHTML = '<legend><span class="les-question-title">' + (index + 1) + '. ' + escapeHtml(questionText(question)) + '</span><span class="les-question-points">' + escapeHtml(question.points || question.evaluation_points || '') + ' pts</span></legend>' + (suppressContext ? '' : renderQuestionContext(question, previousQuestion)) + renderQuestionImage(question);

		let input;
		if (question.type === 'qcm' || question.type === 'multiple_choice' || question.type === 'true_false') {
			const values = question.type === 'true_false' ? ['Vrai', 'Faux'] : options;
			const wrap = document.createElement('div');
			wrap.className = 'les-options';
			values.forEach((value) => {
				const label = document.createElement('label');
				const isMulti = question.type === 'multiple_choice';
				const checked = isMulti ? savedAnswer.includes(String(value)) : String(savedAnswer) === String(value);
				label.innerHTML = '<input type="' + (isMulti ? 'checkbox' : 'radio') + '" name="q_' + question.id + '" value="' + escapeAttr(value) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(value);
				wrap.appendChild(label);
			});
			input = wrap;
		} else if (question.type === 'true_false_justify') {
			const saved = savedAnswer && typeof savedAnswer === 'object' && !Array.isArray(savedAnswer) ? savedAnswer : {};
			input = document.createElement('div');
			input.className = 'les-tfj-answer';
			input.dataset.tfjQuestion = question.id;
			input.innerHTML = '<div class="les-options les-tfj-choices">' + ['Vrai', 'Faux'].map((value) =>
				'<label><input type="radio" name="q_' + question.id + '_choice" value="' + value + '"' + (saved.choice === value ? ' checked' : '') + ' data-tfj-choice> ' + value + '</label>').join('') + '</div>' +
				'<label class="les-tfj-justify">Justifie ta réponse<textarea name="q_' + question.id + '_justification" rows="4" data-tfj-justification placeholder="Explique pourquoi c’est vrai ou faux"></textarea></label>';
			input.querySelector('[data-tfj-justification]').value = saved.justification || '';
		} else if (question.type === 'ordering') {
			input = renderOrderingQuestion(question, savedAnswer);
		} else if (question.type === 'matching') {
			input = renderMatchingQuestion(question, savedAnswer);
		} else if (question.type === 'fill_blanks') {
			input = renderBlanksQuestion(question, savedAnswer);
		} else if (question.type === 'short_answer') {
			input = document.createElement('input');
			input.name = 'q_' + question.id;
			input.value = savedAnswer;
		} else if (question.type === 'calculation') {
			const calcAnswer = savedAnswer && typeof savedAnswer === 'object' && !Array.isArray(savedAnswer)
				? savedAnswer
				: { development: String(savedAnswer || ''), final_result: '' };
			input = document.createElement('div');
			input.className = 'les-calculation-answer';
			input.dataset.calculationQuestion = question.id;
			input.innerHTML =
				'<div class="les-calculation-fields">' +
				(question.precision_label ? '<p class="les-precision-badge">' + escapeHtml(question.precision_label) + '</p>' : '') +
				'<label class="les-calculation-development">Développement / raisonnement<textarea name="q_' + question.id + '_development" rows="5" data-calculation-development></textarea></label>' +
				'<label class="les-calculation-result">Résultat final<input name="q_' + question.id + '_result" data-calculation-result></label>' +
				'</div>';
			input.querySelector('[data-calculation-development]').value = calcAnswer.development || '';
			input.querySelector('[data-calculation-result]').value = calcAnswer.final_result || '';
			input.appendChild(renderQuestionCalculator());
		} else {
			input = document.createElement('textarea');
			input.name = 'q_' + question.id;
			input.rows = 5;
			input.value = savedAnswer;
		}

		// 1.36 : calculatrice à droite pour toute question où l'enseignant l'a demandée.
		if (question.calculator && question.type !== 'calculation') {
			const withCalc = document.createElement('div');
			withCalc.className = 'les-with-calculator';
			const main = document.createElement('div');
			main.className = 'les-with-calculator-main';
			main.appendChild(input);
			withCalc.appendChild(main);
			withCalc.appendChild(renderQuestionCalculator());
			input = withCalc;
		}
		field.appendChild(input);
		const ttsBar = tts.bar();
		if (ttsBar) field.querySelector('legend').insertAdjacentElement('afterend', ttsBar);
		return field;
	}

	/* LogiOne 1.36 : « Relier » — cliquer un élément à gauche puis un élément à droite (ou choisir la lettre).
	   Les liaisons sont dessinées par des traits ; un élément de droite ne va qu'avec un seul élément de gauche. */
	function renderMatchingQuestion(question, savedAnswer) {
		const data = question.match || { left: [], right: [] };
		const saved = savedAnswer && typeof savedAnswer === 'object' && savedAnswer.pairs && typeof savedAnswer.pairs === 'object' ? savedAnswer.pairs : {};
		const letter = (i) => String.fromCharCode(65 + (i % 26));
		const wrap = document.createElement('div');
		wrap.className = 'les-match';
		wrap.dataset.matchQuestion = question.id;
		const rightIndex = {};
		data.right.forEach((r, i) => { rightIndex[r.id] = i; });
		wrap.innerHTML = '<p class="les-match-help">Relie chaque élément de gauche à un élément de droite : clique à gauche, puis à droite (ou choisis la lettre dans la liste). Clique encore sur une liaison pour l’enlever.</p>' +
			'<div class="les-match-board">' +
			'<ol class="les-match-col is-left">' + data.left.map((l, i) =>
				'<li><button type="button" class="les-match-item" data-match-left="' + escapeAttr(l.id) + '"><span class="les-match-num">' + (i + 1) + '</span><span class="les-match-text">' + escapeHtml(l.text) + '</span></button>' +
				'<select data-match-select="' + escapeAttr(l.id) + '" aria-label="Élément relié à « ' + escapeAttr(l.text) + ' »"><option value="">—</option>' +
				data.right.map((r, j) => '<option value="' + escapeAttr(r.id) + '"' + (saved[l.id] === r.id ? ' selected' : '') + '>' + letter(j) + '</option>').join('') + '</select></li>').join('') +
			'</ol>' +
			'<svg class="les-match-lines" aria-hidden="true"></svg>' +
			'<ol class="les-match-col is-right">' + data.right.map((r, j) =>
				'<li><button type="button" class="les-match-item" data-match-right="' + escapeAttr(r.id) + '"><span class="les-match-num">' + letter(j) + '</span><span class="les-match-text">' + escapeHtml(r.text) + '</span></button></li>').join('') +
			'</ol></div>';
		const board = wrap.querySelector('.les-match-board');
		const svg = wrap.querySelector('.les-match-lines');
		const colors = ['#047857', '#1d4ed8', '#b45309', '#7c3aed', '#be123c', '#0e7490', '#4d7c0f', '#c2410c'];
		let active = null;
		const selects = () => Array.from(wrap.querySelectorAll('[data-match-select]'));
		function draw() {
			const box = board.getBoundingClientRect();
			svg.setAttribute('width', String(Math.round(box.width)));
			svg.setAttribute('height', String(Math.round(box.height)));
			let lines = '';
			wrap.querySelectorAll('.les-match-item').forEach((b) => { b.classList.remove('is-linked'); b.style.removeProperty('--les-match-color'); });
			selects().forEach((sel, i) => {
				const lb = wrap.querySelector('[data-match-left="' + sel.dataset.matchSelect + '"]');
				const rb = sel.value ? wrap.querySelector('[data-match-right="' + sel.value + '"]') : null;
				if (!lb || !rb) return;
				const color = colors[i % colors.length];
				[lb, rb].forEach((b) => { b.classList.add('is-linked'); b.style.setProperty('--les-match-color', color); });
				const a = lb.getBoundingClientRect();
				const c = rb.getBoundingClientRect();
				if (!a.width || !c.width) return;
				const x1 = a.right - box.left, y1 = a.top + a.height / 2 - box.top;
				const x2 = c.left - box.left, y2 = c.top + c.height / 2 - box.top;
				lines += '<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke="' + color + '" stroke-width="3" stroke-linecap="round"/>' +
					'<circle cx="' + x1 + '" cy="' + y1 + '" r="5" fill="' + color + '"/><circle cx="' + x2 + '" cy="' + y2 + '" r="5" fill="' + color + '"/>';
			});
			svg.innerHTML = lines;
			wrap.querySelectorAll('[data-match-left]').forEach((b) => b.classList.toggle('is-active', b.dataset.matchLeft === active));
		}
		function link(leftId, rightId) {
			const sel = wrap.querySelector('[data-match-select="' + leftId + '"]');
			if (!sel) return;
			const unlink = sel.value === rightId;
			selects().forEach((s) => { if (s !== sel && s.value === rightId) s.value = ''; }); // une seule liaison par élément de droite
			sel.value = unlink ? '' : rightId;
			sel.dispatchEvent(new Event('change', { bubbles: true }));
		}
		wrap.addEventListener('click', (event) => {
			const left = event.target.closest('[data-match-left]');
			const right = event.target.closest('[data-match-right]');
			if (left) {
				event.preventDefault();
				active = left.dataset.matchLeft; // élément de gauche choisi : le clic suivant à droite le relie
				draw();
			} else if (right) {
				event.preventDefault();
				if (!active) {
					const owner = selects().find((s) => s.value === right.dataset.matchRight);
					if (owner) { owner.value = ''; owner.dispatchEvent(new Event('change', { bubbles: true })); }
					return;
				}
				link(active, right.dataset.matchRight);
				// élément de gauche suivant pas encore relié
				const next = selects().find((s) => !s.value);
				active = next ? next.dataset.matchSelect : null;
				draw();
			}
		});
		wrap.addEventListener('change', (event) => {
			const sel = event.target.closest('[data-match-select]');
			if (sel && sel.value) selects().forEach((s) => { if (s !== sel && s.value === sel.value) s.value = ''; });
			draw();
		});
		if (window.ResizeObserver) new ResizeObserver(() => draw()).observe(board);
		window.addEventListener('resize', draw);
		setTimeout(draw, 0);
		return wrap;
	}

	/* LogiOne 1.36 : « Texte à trous » — un champ par trou dans le texte ; liste de mots (facultative) : cliquer un mot
	   le place dans le trou choisi (ou le premier trou vide). */
	function renderBlanksQuestion(question, savedAnswer) {
		const data = question.blanks || { parts: [], count: 0, bank: [] };
		const saved = savedAnswer && typeof savedAnswer === 'object' && Array.isArray(savedAnswer.blanks) ? savedAnswer.blanks : [];
		const wrap = document.createElement('div');
		wrap.className = 'les-blanks-answer';
		wrap.dataset.blanksQuestion = question.id;
		let k = 0;
		const text = (data.parts || []).map((part) => {
			if (part === null) {
				const i = k++;
				return '<input type="text" class="les-blank" data-blank-index="' + i + '" aria-label="Trou ' + (i + 1) + '" autocomplete="off" autocapitalize="off" spellcheck="false" value="' + escapeAttr(saved[i] || '') + '">';
			}
			return escapeHtml(String(part)).replace(/\n/g, '<br>');
		}).join('');
		wrap.innerHTML = (data.bank && data.bank.length ? '<div class="les-blanks-bank"><span>Mots à placer :</span> ' + data.bank.map((w) => '<button type="button" data-bank-word="' + escapeAttr(w) + '">' + escapeHtml(w) + '</button>').join(' ') + '</div>' : '') +
			'<p class="les-blanks-help">Complète le texte : écris le mot qui manque dans chaque case.</p>' +
			'<p class="les-blanks-text">' + text + '</p>';
		let last = null;
		const inputs = () => Array.from(wrap.querySelectorAll('.les-blank'));
		const markBank = () => {
			const used = inputs().map((i) => i.value.trim().toLowerCase());
			wrap.querySelectorAll('[data-bank-word]').forEach((b) => b.classList.toggle('is-used', used.indexOf(String(b.dataset.bankWord).toLowerCase()) !== -1));
		};
		const fit = (input) => { input.style.width = Math.max(7, Math.min(28, (input.value || '').length + 2)) + 'ch'; };
		inputs().forEach(fit);
		wrap.addEventListener('focusin', (event) => { if (event.target.classList.contains('les-blank')) last = event.target; });
		wrap.addEventListener('input', (event) => { if (event.target.classList.contains('les-blank')) { fit(event.target); markBank(); } });
		wrap.addEventListener('click', (event) => {
			const b = event.target.closest('[data-bank-word]');
			if (!b) return;
			event.preventDefault();
			const target = last && !last.value.trim() ? last : (inputs().find((i) => !i.value.trim()) || last);
			if (!target) return;
			target.value = b.dataset.bankWord;
			target._lesLen = target.value.length; // mot choisi dans la liste : pas une insertion suspecte
			fit(target);
			markBank();
			target.dispatchEvent(new Event('change', { bubbles: true }));
			const next = inputs().find((i) => !i.value.trim());
			if (next) next.focus();
		});
		markBank();
		return wrap;
	}

	/* Remettre dans l'ordre : glisser (souris, doigt) ou flèches ; le clavier fonctionne aussi (Alt + flèches). */
	function renderOrderingQuestion(question, savedAnswer) {
		const items = Array.isArray(question.items) ? question.items.slice() : [];
		const savedOrder = savedAnswer && typeof savedAnswer === 'object' && Array.isArray(savedAnswer.order)
			? savedAnswer.order
			: (Array.isArray(savedAnswer) ? savedAnswer : []);
		if (savedOrder.length) {
			const pos = {};
			savedOrder.forEach((id, i) => { pos[id] = i; });
			items.sort((a, b) => (a.id in pos ? pos[a.id] : 999) - (b.id in pos ? pos[b.id] : 999));
		}
		const letters = question.order_mode === 'letters';
		const images = items.some((item) => item.image);
		const across = letters || images; // tuiles côte à côte : flèches gauche / droite
		const wrap = document.createElement('div');
		wrap.className = 'les-order-answer';
		wrap.dataset.orderQuestion = question.id;
		if (savedOrder.length) wrap.dataset.touched = '1';
		const help = document.createElement('p');
		help.className = 'les-order-help';
		help.textContent = letters ? 'Remets les lettres dans l’ordre : fais-les glisser ou utilise les flèches.' : (images ? 'Remets les images dans l’ordre (de gauche à droite, puis ligne suivante) : fais-les glisser ou utilise les flèches.' : 'Remets les éléments dans l’ordre (le premier en haut) : fais-les glisser ou utilise les flèches.');
		const list = document.createElement('ol');
		list.className = 'les-order' + (letters ? ' is-letters' : '') + (images ? ' has-images' : '');
		list.setAttribute('aria-label', 'Éléments à remettre dans l’ordre');
		items.forEach((item) => {
			const li = document.createElement('li');
			li.className = 'les-order-item';
			li.dataset.itemId = item.id;
			li.tabIndex = 0;
			li.innerHTML = '<span class="les-order-grip" aria-hidden="true">⋮⋮</span>' +
				(item.image ? '<img src="' + escapeAttr(item.image.url) + '" alt="' + escapeAttr(item.text || '') + '" draggable="false">' : '') +
				(item.text ? '<span class="les-order-label">' + escapeHtml(item.text) + '</span>' : '') +
				'<span class="les-order-move"><button type="button" data-order-move="-1" aria-label="' + (across ? 'Vers la gauche' : 'Monter') + '">' + (across ? '←' : '↑') + '</button>' +
				'<button type="button" data-order-move="1" aria-label="' + (across ? 'Vers la droite' : 'Descendre') + '">' + (across ? '→' : '↓') + '</button></span>';
			list.appendChild(li);
		});
		wrap.appendChild(help);
		wrap.appendChild(list);
		const status = document.createElement('p');
		status.className = 'les-order-status';
		status.setAttribute('aria-live', 'polite');
		status.textContent = savedOrder.length ? 'Ordre enregistré.' : 'Pas encore modifié.';
		wrap.appendChild(status);
		bindOrdering(wrap, list, letters);
		return wrap;
	}

	function bindOrdering(wrap, list, letters) {
		const changed = () => {
			wrap.dataset.touched = '1';
			const status = wrap.querySelector('.les-order-status');
			if (status) status.textContent = 'Ordre enregistré.';
			wrap.dispatchEvent(new Event('change', { bubbles: true }));
		};
		list.addEventListener('click', (event) => {
			const btn = event.target.closest('[data-order-move]');
			if (!btn) return;
			event.preventDefault();
			const li = btn.closest('.les-order-item');
			const dir = parseInt(btn.dataset.orderMove, 10);
			if (dir < 0 && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
			else if (dir > 0 && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
			else return;
			btn.focus();
			changed();
		});
		list.addEventListener('keydown', (event) => {
			const li = event.target.closest('.les-order-item');
			if (!li || !event.altKey) return;
			const back = event.key === 'ArrowUp' || event.key === 'ArrowLeft';
			const fwd = event.key === 'ArrowDown' || event.key === 'ArrowRight';
			if (!back && !fwd) return;
			event.preventDefault();
			if (back && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
			else if (fwd && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
			else return;
			li.focus();
			changed();
		});
		let drag = null;
		list.addEventListener('pointerdown', (event) => {
			const li = event.target.closest('.les-order-item');
			if (!li || event.target.closest('button') || (event.pointerType === 'mouse' && event.button !== 0)) return;
			event.preventDefault();
			drag = { li, moved: false, id: event.pointerId };
			li.setPointerCapture(event.pointerId);
			li.classList.add('is-dragging');
		});
		list.addEventListener('pointermove', (event) => {
			if (!drag || event.pointerId !== drag.id) return;
			const siblings = Array.from(list.children).filter((el) => el !== drag.li);
			let before = null;
			for (const el of siblings) {
				const r = el.getBoundingClientRect();
				const inRow = letters || list.classList.contains('has-images')
					? (event.clientY >= r.top && event.clientY <= r.bottom && event.clientX < r.left + r.width / 2) || event.clientY < r.top
					: event.clientY < r.top + r.height / 2;
				if (inRow) { before = el; break; }
			}
			if (before !== drag.li.nextElementSibling || (before === null && list.lastElementChild !== drag.li)) {
				list.insertBefore(drag.li, before);
				drag.moved = true;
			}
		});
		const end = (event) => {
			if (!drag || event.pointerId !== drag.id) return;
			drag.li.classList.remove('is-dragging');
			const moved = drag.moved;
			drag = null;
			if (moved) changed();
		};
		list.addEventListener('pointerup', end);
		list.addEventListener('pointercancel', end);
	}

	function renderQuestionCalculator() {
		const calculator = document.createElement('div');
		calculator.className = 'les-calculator les-question-calculator';
		calculator.tabIndex = 0;
		calculator.setAttribute('role', 'application');
		calculator.setAttribute('aria-label', 'Calculatrice');
		calculator.innerHTML =
			'<div class="les-calculator-title">Calculatrice</div>' +
			'<div class="les-calculator-screen">' +
			'<div class="les-calculator-expression" data-les-calculator-expression>0</div>' +
			'<div class="les-calculator-display" data-les-calculator-display>0</div>' +
			'</div>' +
			'<div class="les-calculator-keys">' +
			'<button type="button" data-calc="clear">C</button><button type="button" data-calc="(">(</button><button type="button" data-calc=")">)</button><button type="button" data-calc="/">÷</button>' +
			'<button type="button" data-calc="7">7</button><button type="button" data-calc="8">8</button><button type="button" data-calc="9">9</button><button type="button" data-calc="*">×</button>' +
			'<button type="button" data-calc="4">4</button><button type="button" data-calc="5">5</button><button type="button" data-calc="6">6</button><button type="button" data-calc="-">−</button>' +
			'<button type="button" data-calc="1">1</button><button type="button" data-calc="2">2</button><button type="button" data-calc="3">3</button><button type="button" data-calc="+">+</button>' +
			'<button type="button" data-calc="0">0</button><button type="button" data-calc=".">.</button><button type="button" data-calc="pi">π</button><button type="button" data-calc="percent">%</button>' +
			'<button type="button" data-calc="sqrt">√</button><button type="button" data-calc="equals" class="les-calculator-equals">=</button>' +
			'</div>';
		return calculator;
	}

	function questionText(question) {
		const div = document.createElement('div');
		div.innerHTML = question.prompt || question.title || '';
		return (div.textContent || '').trim();
	}

	function renderQuestionContext(question, previousQuestion) {
		if (!question.context) return '';
		if (previousQuestion && String(previousQuestion.context || '').trim() === String(question.context || '').trim()) return '';
		return '<div class="les-question-context">' + question.context + '</div>';
	}

	function parseOptions(value) {
		try {
			const parsed = JSON.parse(value || '[]');
			return Array.isArray(parsed) ? parsed : [];
		} catch (error) {
			return [];
		}
	}

	function normalizedSavedAnswer(questionId) {
		const raw = state.answers && state.answers[questionId] ? state.answers[questionId] : '';
		if (raw && typeof raw === 'object' && !Array.isArray(raw)) return raw;
		if (Array.isArray(raw)) return raw.map(String);
		if (typeof raw === 'string' && ['[', '{'].includes(raw.trim().charAt(0))) {
			try {
				const parsed = JSON.parse(raw);
				if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
				if (Array.isArray(parsed)) return parsed.map(String);
			} catch (error) {}
		}
		return raw;
	}

	function currentAnswer(questionId) {
		const tfj = answerForm.querySelector('[data-tfj-question="' + questionId + '"]');
		if (tfj) {
			const choice = tfj.querySelector('[data-tfj-choice]:checked');
			const just = tfj.querySelector('[data-tfj-justification]');
			return { choice: choice ? choice.value : '', justification: just ? just.value : '' };
		}
		const ordering = answerForm.querySelector('[data-order-question="' + questionId + '"]');
		if (ordering) {
			if (ordering.dataset.touched !== '1') return '';
			return Array.from(ordering.querySelectorAll('.les-order-item')).map((li) => li.dataset.itemId);
		}
		const matching = answerForm.querySelector('[data-match-question="' + questionId + '"]');
		if (matching) {
			const pairs = {};
			matching.querySelectorAll('[data-match-select]').forEach((sel) => { if (sel.value) pairs[sel.dataset.matchSelect] = sel.value; });
			return Object.keys(pairs).length ? { pairs } : '';
		}
		const blanks = answerForm.querySelector('[data-blanks-question="' + questionId + '"]');
		if (blanks) {
			const list = Array.from(blanks.querySelectorAll('.les-blank')).map((i) => i.value);
			return list.some((v) => v.trim() !== '') ? { blanks: list } : '';
		}
		const calculation = answerForm.querySelector('[data-calculation-question="' + questionId + '"]');
		if (calculation) {
			const development = calculation.querySelector('[data-calculation-development]');
			const result = calculation.querySelector('[data-calculation-result]');
			return {
				development: development ? development.value : '',
				final_result: result ? result.value : ''
			};
		}
		const checked = Array.from(answerForm.querySelectorAll('[name="q_' + questionId + '"]:checked'));
		if (checked.length && checked[0].type === 'checkbox') return checked.map((input) => input.value);
		if (checked.length === 1) return checked[0].value;
		const input = answerForm.querySelector('textarea[name="q_' + questionId + '"], input[name="q_' + questionId + '"]:not([type="radio"]):not([type="checkbox"])');
		return input ? input.value : '';
	}

	function answerHasValue(answer) {
		if (answer && typeof answer === 'object' && !Array.isArray(answer) && ('choice' in answer || 'justification' in answer)) {
			return String(answer.choice || '') !== '' || String(answer.justification || '').trim() !== '';
		}
		if (answer && typeof answer === 'object' && !Array.isArray(answer) && answer.pairs) {
			return Object.keys(answer.pairs).length > 0;
		}
		if (answer && typeof answer === 'object' && !Array.isArray(answer) && Array.isArray(answer.blanks)) {
			return answer.blanks.some((v) => String(v || '').trim() !== '');
		}
		if (answer && typeof answer === 'object' && !Array.isArray(answer)) {
			return String(answer.development || '').trim() !== '' || String(answer.final_result || '').trim() !== '';
		}
		return Array.isArray(answer) ? answer.length > 0 : String(answer || '').trim() !== '';
	}

	function answeredCount() {
		return state.questions.filter((question) => {
			return answerHasValue(currentAnswer(question.id));
		}).length;
	}

	function storySections() {
		const sections = [];
		let lastContext = null;
		state.questions.forEach((question, index) => {
			const context = String(question.context || '');
			if (lastContext === null || context !== lastContext) {
				sections.push({ context, questions: [] });
				lastContext = context;
			}
			sections[sections.length - 1].questions.push({ question, index });
		});
		return sections;
	}

	function renderStorySections() {
		const sections = storySections();
		const wrap = document.createElement('div');
		wrap.className = 'les-story-flow';
		sections.forEach((section, sectionIndex) => {
			const sectionNode = document.createElement('section');
			sectionNode.className = 'les-story-step';
			sectionNode.dataset.storySection = String(sectionIndex);
			sectionNode.hidden = true;
			const title = 'Partie ' + (sectionIndex + 1) + '/' + sections.length;
			sectionNode.innerHTML = '<div class="les-story-section-intro" data-story-intro><span class="les-kicker">' + title + '</span><div class="les-story-intro-text">' + (section.context || '<p>Section sans texte d’histoire.</p>') + '</div><div class="les-actions"><button type="button" data-story-start-section>Accéder aux questions</button></div></div><div class="les-story-section-questions" data-story-questions hidden></div>';
			const introBar = tts.bar();
			if (introBar) sectionNode.querySelector('.les-story-intro-text').insertAdjacentElement('beforebegin', introBar);
			const questionsBox = sectionNode.querySelector('[data-story-questions]');
			section.questions.forEach((item) => {
				questionsBox.appendChild(renderQuestion(item.question, item.index, null, true));
			});
			if (sectionIndex + 1 < sections.length) {
				const actions = document.createElement('div');
				actions.className = 'les-actions';
				const next = document.createElement('button');
				next.type = 'button';
				next.dataset.storyNextSection = '1';
				next.textContent = 'Passer à la section suivante';
				actions.appendChild(next);
				questionsBox.appendChild(actions);
			}
			wrap.appendChild(sectionNode);
		});
		return wrap;
	}

	function localStorageKey() {
		return state.attemptId ? 'logiexam_attempt_' + state.attemptId : '';
	}

	function saveLocalDraft() {
		const key = localStorageKey();
		if (!key || !state.questions.length) return;
		const answers = {};
		state.questions.forEach((question) => {
			answers[question.id] = currentAnswer(question.id);
		});
		try {
			localStorage.setItem(key, JSON.stringify({ savedAt: Date.now(), answers }));
		} catch (error) {}
	}

	function restoreLocalDraft() {
		const key = localStorageKey();
		if (!key) return;
		try {
			const draft = JSON.parse(localStorage.getItem(key) || '{}');
			if (!draft.answers || !Object.keys(draft.answers).length) return;
			const hasServerAnswers = state.answers && Object.keys(state.answers).some((id) => String(state.answers[id] || '').trim() !== '');
			if (!hasServerAnswers) {
				state.answers = draft.answers;
				setMessage('Une sauvegarde locale de votre progression a été restaurée.', false);
			}
		} catch (error) {}
	}

	function clearLocalDraft() {
		const key = localStorageKey();
		if (!key) return;
		try {
			localStorage.removeItem(key);
		} catch (error) {}
	}

	function updateProgress() {
		const progress = root.querySelector('[data-les-progress]');
		const progressText = root.querySelector('[data-les-progress-text]');
		const nav = root.querySelector('[data-les-question-nav]');
		const total = state.questions.length || 0;
		const answered = answeredCount();
		if (progress) progress.style.width = total ? Math.round((answered / total) * 100) + '%' : '0%';
		if (progressText) progressText.textContent = answered + '/' + total + ' répondues';
			// La barre de navigation n'est construite qu'une fois : la reconstruire pendant un clic (la réponse quittée
			// déclenche « change ») faisait perdre le clic, l'élève restait bloqué sur sa question.
			const navKey = state.questions.map((question) => question.id).join(',') + '|' + (state.evaluation ? state.evaluation.evaluation_type : '');
			if (nav && nav.dataset.lesNavKey === navKey) {
				nav.querySelectorAll('button[data-les-nav-question]').forEach((button) => {
					button.classList.toggle('is-answered', answerHasValue(currentAnswer(button.dataset.lesNavQuestion)));
				});
			} else if (nav) {
				nav.innerHTML = '';
				nav.dataset.lesNavKey = navKey;
			const addQuestionButton = (question, index, target) => {
				const answer = currentAnswer(question.id);
				const done = answerHasValue(answer);
				const button = document.createElement('button');
				button.type = 'button';
				button.className = done ? 'is-answered' : '';
				button.dataset.lesNavQuestion = String(question.id);
				button.textContent = String(index + 1);
				button.addEventListener('click', function () {
					const field = answerForm.querySelector('[data-question-id="' + question.id + '"]');
					if (!field) return;
					const storySection = field.closest('[data-story-section]');
					if (storySection) {
						showStorySection(Number(storySection.dataset.storySection || 0), true);
					}
					field.scrollIntoView({ behavior: 'smooth', block: 'start' });
				});
				target.appendChild(button);
			};
				if (state.evaluation && state.evaluation.evaluation_type === 'story') {
					storySections().forEach((section, sectionIndex) => {
						const group = document.createElement('div');
						group.className = 'les-question-nav-section';
						const label = document.createElement('button');
						label.type = 'button';
						label.className = 'les-question-nav-section-title';
						label.textContent = 'Partie ' + (sectionIndex + 1);
						label.addEventListener('click', function () {
							const sectionNode = showStorySection(sectionIndex, false);
							const intro = sectionNode ? sectionNode.querySelector('[data-story-intro]') : null;
							(intro || sectionNode || questionsArea).scrollIntoView({ behavior: 'smooth', block: 'start' });
						});
						group.appendChild(label);
						section.questions.forEach((item) => addQuestionButton(item.question, item.index, group));
						nav.appendChild(group);
					});
				} else {
					state.questions.forEach((question, index) => addQuestionButton(question, index, nav));
				}
			}
		}

		function isTranslationElement(element) {
			if (!element || !element.closest) return false;
			return !!element.closest('.gtranslate_wrapper, .gt_switcher, .gt_float_switcher, .gt_selected, .gt_options, .gt_option, .gt_container, .gt_selector, .goog-te-gadget, #google_translate_element, .skiptranslate, iframe.skiptranslate, iframe.goog-te-menu-frame, [class*="gtranslate"], [class*="goog-te"], [id*="gtranslate"], [id*="goog-gt"], [href*="gtranslate"], [href*="googtrans"], [href*="translate.google"]');
		}

		function isTranslationUrl(url) {
			const value = String(url || '').toLowerCase();
			return value.includes('gtranslate') || value.includes('googtrans') || value.includes('translate.google') || value.includes('#googtrans');
		}

		function noteTranslationInteraction() {
			state.translationInteractionUntil = Date.now() + 15000;
		}

		function translationInteractionActive() {
			return Date.now() < state.translationInteractionUntil;
		}

		function isTranslationEvent(event) {
			if (!event) return false;
			if (isTranslationElement(event.target)) return true;
			const link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
			if (link && isTranslationUrl(link.href || link.getAttribute('href'))) return true;
			const form = event.target && event.target.closest ? event.target.closest('form') : null;
			return !!(form && isTranslationUrl(form.action || ''));
		}

		function installTranslationAllowance() {
			document.addEventListener('pointerdown', function (event) {
				if (isTranslationEvent(event)) noteTranslationInteraction();
			}, true);
			document.addEventListener('mousedown', function (event) {
				if (isTranslationEvent(event)) noteTranslationInteraction();
			}, true);
			document.addEventListener('touchstart', function (event) {
				if (isTranslationEvent(event)) noteTranslationInteraction();
			}, true);
			document.addEventListener('click', function (event) {
				if (isTranslationEvent(event)) noteTranslationInteraction();
			}, true);
			document.addEventListener('change', function (event) {
				if (isTranslationEvent(event)) noteTranslationInteraction();
			}, true);
			document.addEventListener('focusin', function (event) {
				if (isTranslationEvent(event)) noteTranslationInteraction();
			}, true);
		}

	function logEvent(type, severity, data) {
		if (!state.attemptId || !state.token) return;
		return post('logieval_secure_security_event', {
			attempt_id: state.attemptId,
			session_token: state.token,
			event_type: type,
			severity: severity || 'warning',
			event_data: data || ''
		}).then((json) => {
			if (json && json.success && json.data) {
				applySecurityAction(json.data);
			}
		}).catch(function () {});
	}

		function applySecurityAction(data) {
			if (!data || !data.action || data.action === 'warning') return;
			if (state.securityActionApplied) return;
			state.securityActionApplied = true;
		const text = data.message || exitWarningText();
		state.securityStopped = true;
		state.secureActive = false;
		state.lockActive = false;
		clearInterval(state.examTimer);
		clearInterval(state.localSaveTimer);
		exam.hidden = true;
		hideAccessPanel();
			if (lockScreen) lockScreen.hidden = false;
			if (lockTimer) lockTimer.textContent = data.action === 'submitted' || data.action === 'terminated' ? 'Terminé' : 'Bloqué';
			if (lockMessage) lockMessage.textContent = text;
			if (fullscreenGuard) fullscreenGuard.hidden = true;
			const reopen = root.querySelector('[data-les-reopen]');
			if (reopen) {
				if (data.action === 'blocked') {
					setLockScreenContent(
						'security',
						'Évaluation bloquée',
						'Votre évaluation est bloquée après une sortie du mode sécurisé. Envoyez une demande de déblocage à votre enseignant pour reprendre.'
					);
						reopen.hidden = false;
						reopen.disabled = false;
						reopen.textContent = 'Demander un déblocage';
						state.unlockRequested = false;
						state.awaitingTeacherUnlock = true;
					state.teacherUnlockReady = false;
					startTeacherUnlockPolling();
					// Blocage décidé par le serveur : même compte à rebours rouge pour demander le déblocage.
					if (!state.unlockTimer) startUnlockCountdown(unlockRequestSeconds());
				} else {
					hideUnlockCountdown();
					setLockScreenContent(
						'finalized',
						'Évaluation clôturée',
						'Votre évaluation est clôturée. Vous ne pouvez plus la reprendre.'
					);
					reopen.hidden = true;
					state.awaitingTeacherUnlock = false;
					state.teacherUnlockReady = false;
					stopTeacherUnlockPolling();
					}
			}
		}

		function showBlockedAwaitingTeacher(text) {
			state.securityStopped = true;
			state.secureActive = false;
			state.lockActive = false;
			state.fullscreenRequired = false;
			state.fullscreenBlocked = true;
			state.awaitingTeacherUnlock = true;
			state.teacherUnlockReady = false;
			clearInterval(state.examTimer);
			clearInterval(state.localSaveTimer);
			if (exam) exam.hidden = true;
			hideAccessPanel();
			if (lockScreen) lockScreen.hidden = false;
			setLockScreenContent(
				'security',
				'Évaluation bloquée',
				'Votre évaluation est bloquée après une sortie du mode sécurisé. Envoyez une demande de déblocage à votre enseignant pour reprendre.'
			);
			if (lockTimer) lockTimer.textContent = 'Bloqué';
			if (lockMessage) lockMessage.textContent = text || 'Votre évaluation est bloquée. Votre enseignant doit autoriser la reprise depuis son interface.';
			if (fullscreenGuard) fullscreenGuard.hidden = true;
			const reopen = root.querySelector('[data-les-reopen]');
				if (reopen) {
					reopen.hidden = false;
					reopen.disabled = false;
					reopen.textContent = 'Demander un déblocage';
				}
				state.unlockRequested = false;
				startTeacherUnlockPolling();
				const override = state.unlockDeadlineOverride;
				state.unlockDeadlineOverride = undefined;
				startUnlockCountdown(override !== undefined ? override : unlockRequestSeconds());
		}

		/* LogiOne 1.30 : après un blocage, second compte à rebours rouge. L'élève doit cliquer sur
		   « Demander un déblocage » à temps ; sinon le serveur vérifie le délai et clôture l'évaluation (note 1). */
		function unlockRequestSeconds() {
			const v = parseInt((window.LogiEvalSecure && LogiEvalSecure.unlockRequestSeconds) || '0', 10);
			return isNaN(v) ? 0 : Math.max(0, Math.min(300, v));
		}
		function unlockCountdownNode() {
			let node = document.getElementById('les-unlock-countdown');
			if (node) return node;
			node = document.createElement('div');
			node.id = 'les-unlock-countdown';
			node.className = 'les-exit-countdown les-unlock-countdown';
			node.setAttribute('role', 'alertdialog');
			node.setAttribute('aria-live', 'assertive');
			node.hidden = true;
			node.innerHTML = '<div class="les-exit-box">' +
				'<div class="les-exit-title">⛔ Évaluation bloquée</div>' +
				'<div class="les-exit-number" data-les-unlock-number></div>' +
				'<div class="les-exit-text" data-les-unlock-text></div>' +
				'<button type="button" class="les-exit-button" data-les-unlock-request>Demander un déblocage maintenant</button>' +
				'</div>';
			node.querySelector('[data-les-unlock-request]').addEventListener('click', function () {
				const reopen = root.querySelector('[data-les-reopen]');
				if (reopen && !reopen.disabled) reopen.click();
			});
			document.body.appendChild(node);
			return node;
		}
		/* 1.33.2 : si le navigateur est encore en plein écran (fenêtre quittée avec la touche Windows, une notification…),
		   seul le contenu du plein écran est visible : le compte à rebours doit y être placé, sinon l'élève ne le voit
		   pas, ne peut pas demander le déblocage et l'évaluation est clôturée (note 1). */
		function placeUnlockCountdown(node) {
			const fs = currentFullscreenElement();
			const parent = fs && fs !== document.documentElement ? fs : document.body;
			if (node.parentNode !== parent) parent.appendChild(node);
		}
		['fullscreenchange', 'webkitfullscreenchange'].forEach(function (evt) {
			document.addEventListener(evt, function () {
				const node = document.getElementById('les-unlock-countdown');
				if (node && !node.hidden) placeUnlockCountdown(node);
				const ex = document.getElementById('les-exit-countdown');
				if (ex && !ex.hidden) {
					const fs = currentFullscreenElement();
					const parent = fs && fs !== document.documentElement ? fs : document.body;
					if (ex.parentNode !== parent) parent.appendChild(ex);
				}
			});
		});
		function hideUnlockCountdown() {
			clearInterval(state.unlockTimer);
			state.unlockTimer = null;
			state.unlockDeadlineAt = 0;
			const node = document.getElementById('les-unlock-countdown');
			if (node) node.hidden = true;
		}
		function unlockDeadlineReached() {
			if (state.unlockChecking) return;
			state.unlockChecking = true;
			post('logieval_secure_unlock_deadline', { attempt_id: state.attemptId, session_token: state.token }).then(function (json) {
				state.unlockChecking = false;
				const data = json && json.success ? json.data : null;
				if (!data) return;
				if (data.action === 'terminated') {
					hideUnlockCountdown();
					state.securityActionApplied = false;
					applySecurityAction({ action: 'terminated', message: data.message });
				} else if (data.action === 'wait') {
					state.unlockDeadlineAt = Date.now() + data.left * 1000;
				} else {
					hideUnlockCountdown();
				}
			}).catch(function () {
				state.unlockChecking = false;
				state.unlockDeadlineAt = Date.now() + 3000; // réseau : nouvel essai dans 3 s
			});
		}
		function startUnlockCountdown(seconds) {
			hideUnlockCountdown();
			if (seconds === null || seconds === undefined || seconds === '' || !unlockRequestSeconds()) return;
			seconds = parseInt(seconds, 10);
			if (isNaN(seconds)) return;
			const node = unlockCountdownNode();
			placeUnlockCountdown(node);
			state.unlockDeadlineAt = Date.now() + Math.max(0, seconds) * 1000;
			const render = function () {
				if (state.unlockRequested || state.teacherUnlockReady || !state.awaitingTeacherUnlock) {
					hideUnlockCountdown();
					return;
				}
				const left = Math.max(0, Math.ceil((state.unlockDeadlineAt - Date.now()) / 1000));
				node.hidden = false;
				node.dataset.lesCountdown = String(left);
				node.querySelector('[data-les-unlock-number]').textContent = String(left);
				node.querySelector('[data-les-unlock-text]').textContent = left > 0
					? 'Cliquez sur « Demander un déblocage » dans les ' + left + ' secondes. Sinon, votre évaluation sera clôturée DÉFINITIVEMENT avec la note de 1.'
					: 'Vérification…';
				if (left <= 0) unlockDeadlineReached();
			};
			render();
			state.unlockTimer = setInterval(render, 250);
		}

		function stopTeacherUnlockPolling() {
			if (state.unlockPollTimer) {
				clearInterval(state.unlockPollTimer);
				state.unlockPollTimer = null;
			}
		}

		function markTeacherUnlockReady(message) {
			state.teacherUnlockReady = true;
			state.awaitingTeacherUnlock = true;
			stopTeacherUnlockPolling();
			const reopen = root.querySelector('[data-les-reopen]');
			if (reopen) {
				reopen.hidden = false;
				reopen.disabled = false;
				reopen.textContent = 'Reprendre l’évaluation';
				try { reopen.focus({ preventScroll: true }); } catch (error) {}
			}
			setLockScreenContent(
				'security',
				'Vous êtes débloqué',
				'Votre enseignant a autorisé la reprise. Cliquez sur « Reprendre l’évaluation » : le plein écran se réactive et vous retrouvez vos réponses.'
			);
			if (lockTimer) lockTimer.textContent = 'Autorisé';
			if (lockMessage) {
				lockMessage.textContent = 'Votre enseignant a autorisé la reprise. Cliquez sur « Reprendre l’évaluation » pour continuer en plein écran.';
				lockMessage.className = 'les-message';
			}
		}

		function checkTeacherUnlockStatus() {
			if (!state.awaitingTeacherUnlock || state.teacherUnlockReady || !state.attemptId || !state.token) return;
			post('logieval_secure_unlock_status', {
				attempt_id: state.attemptId,
				session_token: state.token
			}).then((json) => {
				if (json && json.success && json.data && json.data.unlocked) {
					if (json.data.expires_at) {
						state.expiresAt = new Date(json.data.expires_at.replace(' ', 'T') + 'Z');
					}
					markTeacherUnlockReady(json.data.message);
				}
			}).catch(function () {});
		}

		function startTeacherUnlockPolling() {
			if (state.unlockPollTimer || state.teacherUnlockReady || !state.attemptId || !state.token) return;
			state.unlockPollTimer = setInterval(checkTeacherUnlockStatus, 3000);
			setTimeout(checkTeacherUnlockStatus, 1200);
		}

		function setLockScreenContent(mode, title, body) {
			if (!lockScreen) return;
			lockScreen.dataset.lesLockMode = mode || '';
			const heading = lockScreen.querySelector('h2');
			const intro = lockScreen.querySelector('.les-empty-state > p');
			if (heading && title) heading.textContent = title;
			if (intro && body) intro.textContent = body;
		}

		// Mode explicite de l'écran de verrouillage : 'security' (blocage enseignant),
		// 'post_submit' (attente 5 min après envoi) ou 'finalized' (clôturé). On ne le déduit jamais du texte affiché.
		function lockMode() {
			return lockScreen && lockScreen.dataset ? (lockScreen.dataset.lesLockMode || '') : '';
		}

		function isPostSubmitLockScreen() {
			if (!lockScreen || lockScreen.hidden) return false;
			return lockMode() === 'post_submit';
		}

			function markPostSubmitFinalized(text) {
				state.securityStopped = true;
				state.secureActive = false;
				state.lockActive = false;
				state.awaitingTeacherUnlock = false;
				state.teacherUnlockReady = false;
				stopTeacherUnlockPolling();
				state.fullscreenRequired = false;
				state.fullscreenBlocked = false;
				clearInterval(state.lockTimer);
				clearInterval(state.examTimer);
				if (fullscreenGuard) fullscreenGuard.hidden = true;
				setLockScreenContent(
					'finalized',
					'Évaluation clôturée',
					'Votre évaluation est clôturée. Vous ne pouvez plus la reprendre.'
				);
				if (lockTimer) lockTimer.textContent = 'Terminé';
				if (lockMessage) lockMessage.textContent = text || 'Votre évaluation est terminée définitivement. Vous ne pourrez pas la reprendre.';
				const reopen = root.querySelector('[data-les-reopen]');
				if (reopen) reopen.hidden = true;
				if (typeof hideUnlockCountdown === 'function') hideUnlockCountdown();
				showSebQuit();
			}
			function showSebQuit() {
				if (!inSeb() || !state.sebQuitUrl || root.querySelector('[data-les-seb-quit]')) return;
				const a = document.createElement('a');
				a.className = 'button les-seb-quit';
				a.setAttribute('data-les-seb-quit', '');
				a.href = state.sebQuitUrl;
				a.textContent = 'Quitter Safe Exam Browser';
				(lockMessage && lockMessage.parentNode ? lockMessage.parentNode : root).appendChild(a);
			}

			function answersSnapshot() {
				const answers = {};
				state.questions.forEach((question) => {
					answers[question.id] = currentAnswer(question.id);
				});
				return answers;
			}

			function expireEvaluation() {
				if (state.timeExpired || state.expireChecking) return;
				// Avant de clôturer : l'enseignant a peut-être ajouté du temps.
				state.expireChecking = true;
				post('logieval_secure_time_status', { attempt_id: state.attemptId, session_token: state.token }).then((json) => {
					state.expireChecking = false;
					if (applyServerDeadline(json)) return;
					doExpireEvaluation();
				}).catch(() => {
					state.expireChecking = false;
					doExpireEvaluation();
				});
			}

			function applyServerDeadline(json) {
				const raw = json ? (json.data ? json.data.expires_at : null) : null;
				if (!raw) return false;
				const next = new Date(String(raw).replace(' ', 'T') + 'Z');
				if (isNaN(next.getTime())) return false;
				const changed = !state.expiresAt || Math.abs(next.getTime() - state.expiresAt.getTime()) > 1000;
				state.expiresAt = next;
				if (changed && !state.timeExpired) updateExamTimer();
				return next.getTime() > Date.now() + 2000;
			}

			function pollServerDeadline() {
				if (!state.attemptId || !state.token || state.timeExpired || state.lockActive) return;
				if (exam && exam.hidden) return;
				post('logieval_secure_time_status', { attempt_id: state.attemptId, session_token: state.token }).then(applyServerDeadline).catch(() => {});
			}

			function doExpireEvaluation() {
				if (state.timeExpired) return;
				state.timeExpired = true;
				hideSubmitConfirm();
				clearInterval(state.examTimer);
				clearInterval(state.localSaveTimer);
				state.securityStopped = true;
				state.secureActive = false;
				state.lockActive = false;
				state.fullscreenRequired = false;
				state.fullscreenBlocked = false;
				if (fullscreenGuard) fullscreenGuard.hidden = true;
				if (exam) exam.hidden = true;
				hideAccessPanel();
				if (lockScreen) lockScreen.hidden = false;
				setLockScreenContent('finalized', 'Temps écoulé', 'Le temps de l’évaluation est terminé.');
				if (lockTimer) lockTimer.textContent = 'Temps écoulé';
				if (lockMessage) lockMessage.textContent = 'Le temps est écoulé. Votre évaluation est en cours de clôture automatique...';
				const reopen = root.querySelector('[data-les-reopen]');
				if (reopen) reopen.hidden = true;
				post('logieval_secure_expire', {
					attempt_id: state.attemptId,
					session_token: state.token,
					answers: answersSnapshot()
				}).then((json) => {
					const text = (json && json.data && json.data.message) || 'Le temps est écoulé. Votre évaluation a été clôturée automatiquement et transmise à votre enseignant.';
					markPostSubmitFinalized(text);
					clearLocalDraft();
				}).catch(() => {
					markPostSubmitFinalized('Le temps est écoulé. Votre évaluation est clôturée. Si le message persiste, prévenez votre enseignant.');
				});
			}

			function submitEvaluation() {
			if (state.timeExpired) return;
			hideSubmitConfirm();
			state.questions.forEach((question) => {
				post('logieval_secure_save_answer', {
				attempt_id: state.attemptId,
				session_token: state.token,
				question_id: question.id,
				answer: currentAnswer(question.id)
			});
		});
		post('logieval_secure_submit', { attempt_id: state.attemptId, session_token: state.token }).then((json) => {
			if (!json.success) {
				setMessage((json.data && json.data.message) || LogiEvalSecure.i18n.error, true);
				return;
			}
			setMessage((json.data && json.data.message) || LogiEvalSecure.i18n.submitted, false);
			clearLocalDraft();
			beginPostSubmitLock(json.data && json.data.lock_until);
		}).catch(() => setMessage(LogiEvalSecure.i18n.error, true));
	}

	function ensureSubmitConfirm() {
		if (state.submitConfirmPanel) return state.submitConfirmPanel;
		const panel = document.createElement('div');
		panel.className = 'les-submit-confirm';
		panel.hidden = true;
		panel.innerHTML =
			'<div class="les-submit-confirm-card">' +
			'<span class="les-kicker">Vérification avant envoi</span>' +
			'<h3 data-submit-confirm-title></h3>' +
			'<p data-submit-confirm-text></p>' +
			'<div class="les-actions">' +
			'<button type="button" data-submit-review>Reprendre l’évaluation</button>' +
			'<button type="button" data-submit-anyway>Envoyer quand même</button>' +
			'</div>' +
			'</div>';
		root.appendChild(panel);
		panel.querySelector('[data-submit-review]').addEventListener('click', function () {
			const firstId = panel.dataset.firstMissingId;
			hideSubmitConfirm();
			const firstMissing = firstId ? answerForm.querySelector('[data-question-id="' + firstId + '"]') : null;
			if (firstMissing) {
				const storySection = firstMissing.closest('[data-story-section]');
				if (storySection) {
					showStorySection(Number(storySection.dataset.storySection || 0), true);
				}
				firstMissing.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
		panel.querySelector('[data-submit-anyway]').addEventListener('click', function () {
			submitEvaluation();
		});
		state.submitConfirmPanel = panel;
		return panel;
	}

	function showSubmitConfirm(missing) {
		const panel = ensureSubmitConfirm();
		const count = missing.length;
		panel.dataset.firstMissingId = missing[0] ? missing[0].id : '';
		panel.querySelector('[data-submit-confirm-title]').textContent = count === 1 ? '1 question non complétée' : count + ' questions non complétées';
		panel.querySelector('[data-submit-confirm-text]').textContent = 'Vous pouvez envoyer votre évaluation maintenant, ou revenir compléter les réponses manquantes. Cette confirmation reste dans l’évaluation et ne désactive pas le plein écran.';
		panel.hidden = false;
		panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}

	function hideSubmitConfirm() {
		if (state.submitConfirmPanel) {
			state.submitConfirmPanel.hidden = true;
		}
	}

	function sendSecurityBeacon(type, severity, data) {
		if (!state.attemptId || !state.token || !navigator.sendBeacon) return false;
		const body = new FormData();
		body.append('action', 'logieval_secure_security_event');
		body.append('nonce', LogiEvalSecure.nonce);
		body.append('attempt_id', state.attemptId);
		body.append('session_token', state.token);
		body.append('event_type', type);
		body.append('severity', severity || 'warning');
		body.append('event_data', data || '');
		appendSeb(body);
		return navigator.sendBeacon(LogiEvalSecure.ajaxUrl, body);
	}

	/* Conséquences des sorties (LogiOne 1.33.5) : délai de retour, blocage avec compte à rebours pour demander
	   le déblocage, puis clôture. maxExits = numéro de la sortie qui clôture (« Blocages autorisés » + 1). */
	function securitySteps() {
		const max = Math.max(1, parseInt(LogiEvalSecure.maxExits || '3', 10) || 3);
		const nth = (n) => (n === 1 ? '1re' : n + 'e');
		const graceValue = parseInt(LogiEvalSecure.fullscreenGrace || '0', 10) || 0;
		const unlockValue = parseInt(LogiEvalSecure.unlockRequestSeconds || '0', 10) || 0;
		const item = (label, text) => '<li><span>' + label + '</span><p>' + text + '</p></li>';
		let html = '';
		if (graceValue > 0) {
			html += item('Plein écran ou fenêtre quittés', 'Un grand compte à rebours rouge s’affiche : vous avez ' + graceValue + ' secondes pour cliquer sur « Revenir à l’évaluation en plein écran ». Si vous revenez à temps, ce n’est pas compté (3 fois au maximum). Changer d’onglet bloque toujours.');
		}
		html += item('Outils d’IA interdits', 'Assistants IA, extensions et correcteurs « intelligents » du navigateur sont détectés : l’évaluation est bloquée.');
		if (max === 1) {
			return html + item('1re sortie', 'L’évaluation est clôturée définitivement avec la note de 1.');
		}
		const blocked = max === 2 ? '1re sortie' : (max === 3 ? '1re et 2e sortie' : '1re à ' + nth(max - 1) + ' sortie');
		html += item(blocked, 'L’évaluation est bloquée.' + (unlockValue > 0 ? ' Un compte à rebours rouge s’affiche : vous avez ' + unlockValue + ' secondes pour cliquer sur « Demander un déblocage », sinon note de 1.' : ' Cliquez sur « Demander un déblocage ».'));
		html += item('Reprise', 'Votre enseignant décide s’il vous laisse reprendre.');
		html += item(nth(max) + ' sortie', 'L’évaluation est clôturée définitivement avec la note de 1.');
		return html;
	}

	function guardIsActive() {
		return !state.securityStopped && (state.secureActive || state.lockActive);
	}

		function exitWarningText() {
			return state.lockActive
				? 'Si vous quittez maintenant, votre évaluation sera clôturée définitivement et vous ne pourrez plus la reprendre.'
				: "Vous êtes en mode évaluation sécurisée. Toute tentative de sortie est consignée et peut être signalée à l'enseignant.";
		}

	function securityDetails(reason) {
		return JSON.stringify({
			reason: reason || '',
			url: window.location.href,
			width: window.innerWidth,
			height: window.innerHeight,
			user_agent: navigator.userAgent
		});
	}

		/* Mise en veille de l'ordinateur : pendant la veille, plus aucun minuteur ne tourne. Un battement toutes les
		   secondes repère ce « trou » ; une sortie liée à la veille n'est pas comptée comme une tentative de sortie. */
		const SLEEP_GAP_MS = 20000;
		const EXIT_CONFIRM_MS = 4000;
		function startSleepWatch() {
			if (state.sleepWatch) return;
			state.lastTick = Date.now();
			state.sleepWatch = setInterval(function () {
				const now = Date.now();
				const gap = now - state.lastTick;
				if (gap > SLEEP_GAP_MS) {
					state.wokeAt = now;
					state.sleepGap = gap;
				}
				state.lastTick = now;
			}, 1000);
		}

		function sleepSuspected(since) {
			const now = Date.now();
			if (now - (state.lastTick || now) > SLEEP_GAP_MS) {
				state.sleepGap = now - state.lastTick;
				return true;
			}
			if (!state.wokeAt) return false;
			if (since) return state.wokeAt >= since;
			return now - state.wokeAt < 15000;
		}

		function fullscreenGraceSeconds() {
			const value = parseInt((window.LogiEvalSecure && LogiEvalSecure.fullscreenGrace) || '0', 10);
			return isNaN(value) ? 0 : Math.max(0, Math.min(60, value));
		}

		/* Compte à rebours de sortie : grand écran rouge par-dessus toute la page (accroché directement au <body>,
		   pour qu'aucun bloc du thème ne puisse le cacher). L'élève a quelques secondes pour revenir. */
		function exitRecovered() {
			if (state.fullscreenRequired && !currentFullscreenElement()) return false;
			if (document.hidden) return false;
			if (sidePanelOpen()) return false;
			return typeof document.hasFocus !== 'function' || document.hasFocus();
		}

		/* LogiOne 1.28.1 : panneau latéral du navigateur (Gemini dans Chrome, Copilot dans Edge…). Le plein écran
		   reste actif, mais la zone de l'évaluation rétrécit. On compare la largeur réelle (en pixels de l'écran,
		   donc insensible au zoom Ctrl +/-) à celle mesurée à l'entrée en plein écran. */
		function physicalWidth() {
			return Math.round(window.innerWidth * (window.devicePixelRatio || 1));
		}
		function physicalHeight() {
			return Math.round(window.innerHeight * (window.devicePixelRatio || 1));
		}
		function rememberFullscreenSize() {
			if (!currentFullscreenElement()) return;
			state.fsWidth = physicalWidth();
			state.fsHeight = physicalHeight();
			state.fsSizeAt = Date.now();
		}
		function sidePanelOpen() {
			if (tabletMode()) return tabletSplit();
			if (!state.fsWidth || !currentFullscreenElement()) return false;
			const w = physicalWidth();
			const h = physicalHeight();
			// Rotation d'une tablette : la hauteur change aussi, ce n'est pas un panneau.
			if (Math.abs(h - state.fsHeight) > state.fsHeight * 0.12) return false;
			return w < state.fsWidth * 0.88 && state.fsWidth - w > 150;
		}

		function exitCountdownNode() {
			let node = document.getElementById('les-exit-countdown');
			if (node) return node;
			node = document.createElement('div');
			node.id = 'les-exit-countdown';
			node.className = 'les-exit-countdown';
			node.setAttribute('role', 'alertdialog');
			node.setAttribute('aria-live', 'assertive');
			node.hidden = true;
			node.innerHTML = '<div class="les-exit-box">' +
				'<div class="les-exit-title">⚠ Revenez dans l’évaluation !</div>' +
				'<div class="les-exit-number" data-les-exit-number>5</div>' +
				'<div class="les-exit-text" data-les-exit-text></div>' +
				'<button type="button" class="les-exit-button" data-les-exit-return>Revenir à l’évaluation en plein écran</button>' +
				'</div>';
			node.querySelector('[data-les-exit-return]').addEventListener('click', function () {
				try { window.focus(); } catch (error) {}
				const done = function () {
					if (state.pendingExit) checkPendingExitRecovered(state.pendingExit);
				};
				if (state.fullscreenRequired) {
					requestExamFullscreen().then(done);
				} else {
					done();
				}
			});
			(document.fullscreenElement && document.fullscreenElement !== document.documentElement ? document.fullscreenElement : document.body).appendChild(node);
			return node;
		}

		function showExitCountdown(pending) {
			const node = exitCountdownNode();
			const parent = currentFullscreenElement();
			if (parent && parent !== document.documentElement && node.parentNode !== parent) parent.appendChild(node);
			const render = function () {
				if (state.pendingExit !== pending) return;
				if (exitRecovered()) {
					checkPendingExitRecovered(pending);
					return;
				}
				const left = Math.max(0, Math.ceil((pending.since + pending.grace * 1000 - Date.now()) / 1000));
				node.hidden = false;
				node.dataset.lesCountdown = String(left);
				node.querySelector('[data-les-exit-number]').textContent = String(left);
				node.querySelector('[data-les-exit-text]').textContent = left > 0
					? (sidePanelOpen()
						? 'Un panneau s’est ouvert à côté de l’évaluation (assistant IA ou autre outil du navigateur). Fermez-le dans les ' + left + ' secondes, sinon l’évaluation sera bloquée.'
						: state.fullscreenRequired && !currentFullscreenElement()
						? 'Vous avez quitté le plein écran. Cliquez sur le bouton ci-dessous dans les ' + left + ' secondes, sinon l’évaluation sera bloquée.'
						: 'Vous avez quitté la fenêtre de l’évaluation. Cliquez sur le bouton ci-dessous dans les ' + left + ' secondes, sinon l’évaluation sera bloquée.')
					: 'Temps écoulé : l’évaluation est bloquée.';
			};
			render();
			clearInterval(state.exitCountdownTimer);
			state.exitCountdownTimer = setInterval(render, 200);
		}

		function hideExitCountdown() {
			clearInterval(state.exitCountdownTimer);
			state.exitCountdownTimer = null;
			const node = document.getElementById('les-exit-countdown');
			if (node) {
				node.hidden = true;
				delete node.dataset.lesCountdown;
			}
		}

		function checkPendingExitRecovered(pending) {
			if (state.pendingExit !== pending || !pending.forgivable) return;
			if (!exitRecovered()) return;
			finishPendingExit(pending);
		}

		function finishPendingExit(pending) {
			if (state.pendingExit !== pending) return;
			clearTimeout(pending.timer);
			hideExitCountdown();
			if (sleepSuspected(pending.since) || Date.now() - pending.since > pending.wait + SLEEP_GAP_MS) {
				handleDeviceSleep(pending.type, pending.data);
				return;
			}
			state.pendingExit = null;
			if (!guardIsActive()) return;
			if (pending.forgivable && exitRecovered()) {
				state.graceUsed = (state.graceUsed || 0) + 1;
				const seconds = Math.max(1, Math.round((Date.now() - pending.since) / 1000));
				const what = pending.type === 'fullscreen_exit' ? 'Plein écran quitté' : (pending.type === 'side_panel' ? 'Panneau latéral ouvert' : 'Fenêtre quittée');
				logEvent('fullscreen_exit_recovered', 'info', securityDetails(what + ' puis rétabli en ' + seconds + ' s (délai : ' + pending.grace + ' s)'));
				hideFullscreenGuard();
				setMessage('Retour à temps : pas de blocage.', false);
				return;
			}
			state.pendingExitConfirmed = true;
			try {
				blockExitAttempt(pending.type, pending.data);
			} finally {
				state.pendingExitConfirmed = false;
			}
		}

		function handleDeviceSleep(type, data) {
			state.pendingExit = null;
			const minutes = Math.max(1, Math.round((state.sleepGap || 0) / 60000));
			const text = 'Votre ordinateur s’est mis en veille (' + minutes + ' min environ). Ce n’est pas compté comme une sortie.';
			logEvent('device_sleep', 'warning', securityDetails(text + ' Événement d’origine : ' + type + (data ? ' (' + data + ')' : '')));
			state.wokeAt = 0;
			state.sleepText = text;
			state.sleepNoticeUntil = Date.now() + 30000;
			if (state.fullscreenRequired && !currentFullscreenElement()) {
				showFullscreenGuard(text + ' Réactivez le plein écran pour continuer.');
			} else {
				setMessage(text, false);
			}
		}

		function blockExitAttempt(type, data) {
			if (translationInteractionActive() && type !== 'fullscreen_exit') return;
			if (inSeb() && !state.lockActive && ['focus_lost', 'fullscreen_exit', 'side_panel'].indexOf(type) !== -1) {
				// Safe Exam Browser empêche déjà d'ouvrir d'autres programmes : sa propre barre (Wi-Fi, batterie…)
				// peut prendre la main un instant. On note sans bloquer.
				logEvent('seb_focus_info', 'info', securityDetails('Dans Safe Exam Browser : ' + (data || type) + ' (non sanctionné)'));
				return;
			}
			if (!state.lockActive && sleepSuspected()) {
				handleDeviceSleep(type, data);
				return;
			}
			// Onglet masqué / fenêtre quittée / plein écran perdu : on vérifie d'abord que ce n'est pas la mise en veille.
			// Plein écran quitté ou fenêtre quittée (touche Windows, notification…) : grand compte à rebours rouge,
			// l'élève a quelques secondes pour revenir. Changer d'onglet (page masquée) n'est pas pardonné.
			if (!state.lockActive && !state.pendingExitConfirmed && ['tab_hidden', 'focus_lost', 'fullscreen_exit', 'side_panel'].indexOf(type) !== -1) {
				if (state.pendingExit) {
					if (type === 'tab_hidden' && state.pendingExit.type !== 'tab_hidden') {
						state.pendingExit.type = type;
						state.pendingExit.data = data;
						state.pendingExit.forgivable = false;
					} else if (type === 'fullscreen_exit' && state.pendingExit.type === 'focus_lost') {
						state.pendingExit.type = type;
						state.pendingExit.data = data;
					}
					return;
				}
				// Délai de grâce : 3 fois au maximum par évaluation, ensuite chaque sortie bloque (évite les allers-retours répétés).
				const grace = type === 'tab_hidden' || (state.graceUsed || 0) >= 3 ? 0 : fullscreenGraceSeconds();
				const pending = { type: type, data: data, since: Date.now(), grace: grace, forgivable: grace > 0 };
				pending.wait = Math.max(EXIT_CONFIRM_MS, grace * 1000);
				state.pendingExit = pending;
				if (pending.forgivable) {
					showExitCountdown(pending);
				} else if (type === 'fullscreen_exit') {
					showFullscreenGuard('Vous avez quitté le plein écran.');
				}
				pending.timer = setTimeout(function () { finishPendingExit(pending); }, pending.wait);
				return;
			}
			const eventType = state.lockActive ? 'post_submit_exit_attempt' : type;
			const severity = state.lockActive ? 'info' : 'warning';
			const detail = securityDetails(data);
			showSecurityWarning(exitWarningText());
			if (lockMessage && state.lockActive) lockMessage.textContent = exitWarningText();
			if (state.lockActive) {
				post('logieval_secure_force_security_block', {
					attempt_id: state.attemptId,
					session_token: state.token,
					event_type: eventType,
					severity: severity,
					reason: data || '',
					event_data: detail
				}).then((json) => {
					if (json && json.success && json.data) {
						applySecurityAction(json.data);
					} else {
						markPostSubmitFinalized();
					}
				}).catch(function () {
					markPostSubmitFinalized();
				});
				return;
			}
			showBlockedAwaitingTeacher('Vous avez quitté le mode sécurisé. L’évaluation est bloquée. Cliquez sur “Demander un déblocage” pour signaler votre demande à l’enseignant.');
			post('logieval_secure_force_security_block', {
				attempt_id: state.attemptId,
				session_token: state.token,
				event_type: eventType,
				severity: severity,
				reason: data || '',
				event_data: detail
			}).then((json) => {
				if (json && json.success && json.data) {
					applySecurityAction(json.data);
				}
			}).catch(function () {
				if (lockMessage) {
					lockMessage.textContent = 'Votre évaluation est bloquée sur cet écran. Si le blocage n’apparaît pas encore chez votre enseignant, demandez-lui de rafraîchir le monitoring.';
				}
			});
	}

		function ensureTeacherVisibleBlock(reason) {
			return post('logieval_secure_force_security_block', {
				attempt_id: state.attemptId,
				session_token: state.token,
				event_type: 'unlock_requested',
				severity: 'warning',
				reason: reason || 'Demande de déblocage envoyée par l’élève.',
				event_data: securityDetails(reason || 'Demande de déblocage envoyée par l’élève.')
			});
		}

		/* LogiOne 1.27.1 : outils d'IA dans le navigateur. Une extension (assistant IA, correcteur « intelligent »,
		   aide aux devoirs…) peut lire les questions et afficher une réponse DANS la page, sans quitter le plein écran.
		   On repère les traces qu'elle laisse (éléments, attributs, adresses chrome-extension://…) : avant l'évaluation,
		   l'élève doit la désactiver ; pendant l'évaluation, c'est une sortie (blocage) notée dans le registre. */
		const AI_EXT_URL = /^(chrome|moz|edge|ms-browser|safari-web)-extension:\/\//i;
		const AI_MARKERS = /(grammarly|monica-|(^|[^a-z])sider|maxai|harpa|chatgpt|openai|merlin-|quillbot|wiseone|copilot|gemini|perplexity|claude-|compose-?ai|quizard|quizgpt|answersai|studyx|brainly|photomath|gauth|chatsonic|writesonic|wordtune|deepl-|textcortex|languagetool|lt-toolbar|aiprm|mathgpt|solvely|(^|[^a-z])ai-(assist|helper|answer|tutor|sidebar))/i;
		const AI_SAFE = /(lastpass|bitwarden|1password|dashlane|keeper|nordpass|ublock|adblock|adguard|ghostery|privacy-?badger|gtranslate|goog-te|skiptranslate|les-|logieval|logipilot|wpadminbar)/i;
		const AI_ATTR = /^data-(gr-|new-gr-|gramm|lt-|languagetool|quillbot|wordtune|monica|sider|maxai|merlin|harpa|copilot|deepl)/i;

		function aiNodeLabel(el) {
			if (!el || el.nodeType !== 1) return '';
			const tag = el.tagName.toLowerCase();
			const src = el.getAttribute('src') || el.getAttribute('href') || '';
			if (AI_EXT_URL.test(src)) return tag + ' ' + src.slice(0, 80);
			const cls = typeof el.className === 'string' ? el.className : '';
			const label = tag + ' #' + (el.id || '') + ' .' + cls;
			if (AI_SAFE.test(label)) return '';
			if (AI_MARKERS.test(label)) return label.trim().slice(0, 120);
			for (let i = 0; i < el.attributes.length; i++) {
				// Valeur « false » : ce sont nos propres réglages qui désactivent ces correcteurs.
				if (AI_ATTR.test(el.attributes[i].name) && el.attributes[i].value !== 'false') return tag + ' [' + el.attributes[i].name + ']';
			}
			// Élément personnalisé avec contenu caché (« shadow DOM ») ajouté hors de l'évaluation : typique des extensions.
			if (tag.indexOf('-') !== -1 && el.shadowRoot && !root.contains(el)) return tag + ' (élément ajouté par une extension)';
			return '';
		}

		function scanForAiTools(nodes) {
			const found = [];
			const visit = function (el) {
				const label = aiNodeLabel(el);
				if (label && found.indexOf(label) === -1) found.push(label);
			};
			(nodes || [document.documentElement]).forEach(function (node) {
				if (!node || node.nodeType !== 1) return;
				visit(node);
				if (node.querySelectorAll) {
					const all = node.querySelectorAll('*');
					for (let i = 0; i < all.length && found.length < 6; i++) visit(all[i]);
				}
			});
			[document.documentElement, document.body].forEach(function (el) {
				if (!el) return;
				for (let i = 0; i < el.attributes.length; i++) {
					const name = el.attributes[i].name;
					if (AI_ATTR.test(name) && el.attributes[i].value !== 'false' && found.indexOf('[' + name + ']') === -1) found.push('[' + name + ']');
				}
			});
			return found;
		}

		/* Champs de réponse : les correcteurs « intelligents » (Grammarly, LanguageTool…) et le remplissage automatique sont désactivés. */
		function hardenAnswerFields() {
			if (!answerForm) return;
			answerForm.querySelectorAll('textarea, input[type="text"], input:not([type])').forEach(function (field) {
				field.setAttribute('autocomplete', 'off');
				// IA intégrée au navigateur (« Aide-moi à écrire », suggestions d'écriture) et correction automatique : coupées.
				field.setAttribute('writingsuggestions', 'false');
				field.setAttribute('spellcheck', 'false');
				field.setAttribute('autocorrect', 'off');
				field.setAttribute('autocapitalize', 'off');
				field.setAttribute('data-ms-editor', 'false');
				field.setAttribute('data-gramm', 'false');
				field.setAttribute('data-gramm_editor', 'false');
				field.setAttribute('data-enable-grammarly', 'false');
				field.setAttribute('data-lt-active', 'false');
			});
		}

		function aiToolsMessage(found) {
			return 'Un outil d’aide installé dans votre navigateur a été détecté (assistant IA, correcteur ou extension : ' + found.slice(0, 3).join(' ; ') + '). Il n’est pas autorisé pendant l’évaluation. Désactivez-le, ou ouvrez une fenêtre de navigation privée, puis entrez à nouveau votre code.';
		}

		/* Avant de commencer : s'il y a un outil d'IA, l'élève ne peut pas accéder à l'évaluation. */
		function aiToolsBlockStart() {
			const found = scanForAiTools();
			if (!found.length) return false;
			if (!state.aiStartReported) {
				state.aiStartReported = true;
				logEvent('ai_tool_detected_start', 'warning', securityDetails('Outil d’aide détecté avant le début (accès refusé) : ' + found.join(' ; ')));
			}
			if (securityMessage) {
				securityMessage.textContent = aiToolsMessage(found);
				securityMessage.className = 'les-message is-error';
			}
			return true;
		}

		/* Pendant l'évaluation : surveillance continue de la page. */
		function startAiWatch() {
			if (state.aiWatch || !window.MutationObserver) return;
			hardenAnswerFields();
			const report = function (found) {
				if (!found.length || !guardIsActive() || state.aiReported) return;
				// Avant les questions (écran des règles) : pas de sanction, l'accès est simplement refusé tant que l'outil est là.
				if (!questionsArea || questionsArea.hidden) {
					aiToolsBlockStart();
					return;
				}
				state.aiReported = true;
				blockExitAttempt('ai_tool_detected', 'Outil d’IA ou extension détecté dans la page pendant l’évaluation : ' + found.join(' ; '));
			};
			state.aiWatch = new MutationObserver(function (mutations) {
				const added = [];
				let attr = false;
				mutations.forEach(function (m) {
					if (m.type === 'attributes' && (m.target === document.documentElement || m.target === document.body)) attr = true;
					m.addedNodes.forEach(function (n) { if (n.nodeType === 1) added.push(n); });
				});
				if (added.length) hardenAnswerFields();
				const found = added.length ? scanForAiTools(added) : [];
				if (attr) scanForAiTools([]).forEach(function (f) { if (found.indexOf(f) === -1) found.push(f); });
				report(found);
			});
			// Une seule observation (deux appels sur le même élément se remplaceraient) : ajouts dans toute la page,
			// et attributs de <html> / <body> (là où beaucoup d'extensions signalent leur présence).
			state.aiWatch.observe(document.documentElement, { childList: true, subtree: true, attributes: true });
			report(scanForAiTools());
		}

		/* LogiOne 1.28.1 : texte qui arrive dans une réponse sans être tapé (glissé depuis une autre fenêtre, inséré
		   par une extension, une dictée ou un outil d'IA). Le collage et le glisser-déposer sont bloqués ; un long
		   passage qui apparaît d'un coup sans frappe au clavier est signalé à l'enseignant. Les frappes elles-mêmes
		   ne sont jamais enregistrées : seulement le nombre de caractères arrivés et la question concernée. */
		const INSERT_MIN_CHARS = 40;
		function answerField(el) {
			return !!(el && answerForm && answerForm.contains(el) && el.matches && el.matches('textarea, input[type="text"], input:not([type]), [contenteditable="true"]'));
		}
		function fieldText(el) {
			return el.isContentEditable ? (el.textContent || '') : String(el.value || '');
		}
		function fieldQuestionLabel(el) {
			const card = el.closest('[data-question-id], .les-question');
			if (!card) return '';
			const n = card.querySelector('.les-question-title');
			const m = n && n.textContent.match(/^\s*(\d+)\./);
			return m ? 'question ' + m[1] : '';
		}
		function reportInsertedText(el, chars, how) {
			if (!guardIsActive() || state.lockActive) return;
			const now = Date.now();
			if (el._lesInsertReportedAt && now - el._lesInsertReportedAt < 10000) return;
			el._lesInsertReportedAt = now;
			const q = fieldQuestionLabel(el);
			logEvent('text_inserted', 'warning', securityDetails('Texte inséré sans frappe au clavier' + (q ? ' (' + q + ')' : '') + ' : ' + chars + ' caractères d’un coup — ' + how));
			showSecurityWarning('Du texte est apparu dans votre réponse sans être tapé au clavier. Cet événement a été enregistré et sera signalé à l’enseignant.');
		}
		function startTypingWatch() {
			if (state.typingWatch) return;
			document.addEventListener('keydown', function (event) {
				if (answerField(event.target)) state.lastKeyAt = Date.now();
			}, true);
			document.addEventListener('beforeinput', function (event) {
				if (!guardIsActive() || !answerField(event.target)) return;
				const type = String(event.inputType || '');
				if (type === 'insertFromPaste' || type === 'insertFromDrop' || type === 'insertFromYank' || type === 'insertFromPasteAsQuotation') {
					event.preventDefault();
					logEvent(type === 'insertFromDrop' ? 'drop_attempt' : 'paste_attempt', 'warning', securityDetails('Insertion de texte extérieur bloquée (' + type + ')'));
					showSecurityWarning('Insérer du texte venant d’ailleurs est désactivé pendant cette évaluation. Cet événement a été enregistré.');
				}
			}, true);
			['dragover', 'drop'].forEach(function (name) {
				document.addEventListener(name, function (event) {
					if (!guardIsActive()) return;
					if (!answerField(event.target) && !(event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types || [], 'text/plain') !== -1)) return;
					event.preventDefault();
					if (name === 'drop') {
						logEvent('drop_attempt', 'warning', securityDetails('Glisser-déposer de texte dans l’évaluation bloqué'));
						showSecurityWarning('Glisser du texte dans l’évaluation est désactivé. Cet événement a été enregistré.');
					}
				}, true);
			});
			document.addEventListener('input', function (event) {
				const el = event.target;
				if (!answerField(el)) return;
				const len = fieldText(el).length;
				const before = typeof el._lesLen === 'number' ? el._lesLen : len;
				el._lesLen = len;
				if (len - before >= INSERT_MIN_CHARS && Date.now() - (state.lastKeyAt || 0) > 1000) {
					reportInsertedText(el, len - before, 'saisie ' + (event.inputType || 'sans type') + ' (dictée, extension ou outil d’IA possible)');
				}
			}, true);
			// Une extension peut aussi écrire dans le champ sans prévenir la page : contrôle toutes les 2 secondes.
			state.typingWatch = setInterval(function () {
				if (!guardIsActive() || !answerForm) return;
				answerForm.querySelectorAll('textarea, input[type="text"], input:not([type]), [contenteditable="true"]').forEach(function (el) {
					const len = fieldText(el).length;
					if (typeof el._lesLen !== 'number') { el._lesLen = len; return; }
					if (len - el._lesLen >= INSERT_MIN_CHARS) reportInsertedText(el, len - el._lesLen, 'texte écrit dans le champ par un programme');
					el._lesLen = len;
				});
			}, 2000);
		}

		/* LogiOne 1.31.2 : captures d'écran. Une page ne peut pas bloquer la capture du système ; elle peut :
		   - masquer les questions dès que la fenêtre perd la main ou qu'un raccourci de capture commence
		     (outil Capture de Windows, Cmd+Maj+4/5 sur Mac) : la capture ne montre alors qu'un écran masqué ;
		   - repérer la touche Impr. écran et les raccourcis de capture, et les noter dans le journal ;
		   - afficher un filigrane au nom de l'élève sur les questions (une photo qui circule reste identifiable). */
		function setVeil(on) {
			if (!questionsArea) return;
			questionsArea.classList.toggle('les-veiled', !!on);
		}
		function applyWatermark(text) {
			if (!questionsArea || !text) return;
			let wm = questionsArea.querySelector('.les-watermark');
			if (!wm) {
				wm = document.createElement('div');
				wm.className = 'les-watermark';
				wm.setAttribute('aria-hidden', 'true');
				questionsArea.appendChild(wm);
			}
			const label = String(text).replace(/[<>&"]/g, '');
			const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="220"><text x="10" y="130" transform="rotate(-20 210 110)" font-family="sans-serif" font-size="18" fill="rgba(30,41,59,0.10)">' + label + '</text></svg>';
			wm.style.backgroundImage = 'url("data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg) + '")';
		}
		function reportScreenshot(how) {
			if (!guardIsActive() || state.lockActive) return;
			const now = Date.now();
			if (state.lastShotAt && now - state.lastShotAt < 3000) return;
			state.lastShotAt = now;
			logEvent('screenshot_attempt', 'warning', securityDetails('Tentative de capture d’écran : ' + how));
			const text = 'Les captures d’écran sont interdites pendant l’évaluation. Cette tentative a été enregistrée et sera signalée à l’enseignant.';
			showSecurityWarning(text);
			// Message bien visible pendant l'évaluation (le message habituel est sur l'écran d'accès).
			let toast = document.getElementById('les-shot-toast');
			if (!toast) {
				toast = document.createElement('div');
				toast.id = 'les-shot-toast';
				toast.className = 'les-shot-toast';
				toast.setAttribute('role', 'alert');
			}
			const host = currentFullscreenElement() && currentFullscreenElement() !== document.documentElement ? currentFullscreenElement() : document.body;
			if (toast.parentNode !== host) host.appendChild(toast);
			toast.textContent = '📷 ' + text;
			toast.hidden = false;
			clearTimeout(state.shotToastTimer);
			state.shotToastTimer = setTimeout(function () { toast.hidden = true; }, 7000);
			try {
				if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText('Capture interdite pendant l’évaluation LogiExam').catch(function () {});
			} catch (error) {}
		}
		function startScreenshotGuard() {
			if (state.shotGuard) return;
			state.shotGuard = true;
			applyWatermark(state.watermark);
			const isShotCombo = function (event) {
				const key = String(event.key || '').toLowerCase();
				const code = String(event.code || '');
				if (key === 'printscreen' || code === 'PrintScreen' || key === 'snapshot') return 'touche Impr. écran';
				if (event.metaKey && event.shiftKey && (['3', '4', '5', '6', 's'].indexOf(key) !== -1 || /^(Digit[3-6]|KeyS)$/.test(code))) return 'raccourci ' + (navigator.platform && /mac/i.test(navigator.platform) ? 'Cmd+Maj+' : 'Win+Maj+') + (/^Digit\d$/.test(code) ? code.slice(5) : /^Key[A-Z]$/.test(code) ? code.slice(3) : key.toUpperCase());
				if (event.ctrlKey && event.shiftKey && (key === 'printscreen' || key === 's' && event.altKey)) return 'raccourci de capture';
				return '';
			};
			document.addEventListener('keydown', function (event) {
				if (!guardIsActive()) return;
				// Touche Windows / Cmd enfoncée : on masque tout de suite (début possible d'un raccourci de capture).
				if (event.key === 'Meta' || event.key === 'OS') setVeil(true);
				const how = isShotCombo(event);
				if (how) {
					setVeil(true);
					event.preventDefault();
					reportScreenshot(how);
				} else if (event.key !== 'Meta' && event.key !== 'OS' && !event.metaKey && document.hasFocus()) {
					setVeil(false); // filet de sécurité : le masque ne reste jamais collé pendant que l'élève écrit
				}
			}, true);
			document.addEventListener('pointerdown', function () { if (document.hasFocus() && !document.hidden) setVeil(false); }, true);
			document.addEventListener('keyup', function (event) {
				const how = isShotCombo(event);
				if (how && guardIsActive()) reportScreenshot(how);
				if (event.key === 'Meta' || event.key === 'OS' || how) {
					setTimeout(function () { if (document.hasFocus() && !document.hidden) setVeil(false); }, how ? 1500 : 150);
				}
			}, true);
			window.addEventListener('blur', function () { if (guardIsActive()) setVeil(true); });
			window.addEventListener('focus', function () { setTimeout(function () { if (document.hasFocus()) setVeil(false); }, 150); });
			document.addEventListener('visibilitychange', function () { if (document.hidden) setVeil(true); });
		}

		function activateSecureMode() {
			if (state.listenersActive) return;
			state.listenersActive = true;
			startScreenshotGuard();
			state.secureActive = true;
			startSleepWatch();
			startAiWatch();
			rememberFullscreenSize();
			installTranslationAllowance();
			document.addEventListener('visibilitychange', function () {
				if (translationInteractionActive()) return;
				if (document.hidden) {
					const eventType = state.lockActive ? 'post_submit_exit_confirmed' : 'tab_hidden';
					const severity = state.lockActive ? 'info' : 'warning';
					const detail = state.lockActive ? 'Sortie pendant le délai après soumission : évaluation clôturée définitivement' : "Changement d'onglet ou de fenêtre pendant l'évaluation";
					state.pendingSecurityWarning = exitWarningText();
					if (state.lockActive) {
						sendSecurityBeacon(eventType, severity, securityDetails(detail));
						logEvent(eventType, severity, securityDetails(detail));
						markPostSubmitFinalized();
					} else {
						blockExitAttempt(eventType, detail);
					}
				} else if (guardIsActive() && state.sleepText && Date.now() < state.sleepNoticeUntil) {
					setMessage(state.sleepText, false);
				} else if (guardIsActive()) {
					logEvent('tab_visible', 'info', securityDetails('Retour dans la fenêtre de l’évaluation'));
					showSecurityWarning(state.pendingSecurityWarning || exitWarningText());
			}
			});
			window.addEventListener('blur', function () {
				if (translationInteractionActive()) return;
				if (!guardIsActive()) return;
				if (tabletMode() && !state.lockActive && !state.tabletBlurCheck) {
					// Tablette : une liste déroulante, la dictée ou un menu du système prennent parfois la main un instant.
					// Si la page reprend la main tout de suite sans avoir été masquée, ce n'est pas une sortie.
					state.tabletBlurCheck = setTimeout(function () {
						state.tabletBlurCheck = null;
						if (!guardIsActive() || state.pendingExit) return;
						if (!document.hidden && (typeof document.hasFocus !== 'function' || document.hasFocus())) {
							logEvent('focus_flicker', 'info', securityDetails('Tablette : perte de focus de moins d’une seconde (menu du système), non comptée'));
							return;
						}
						blockExitAttempt('focus_lost', 'Tablette : une autre app ou un menu du système est passé devant l’évaluation');
					}, 900);
					return;
				}
				const detail = state.lockActive ? 'Perte de focus pendant le verrouillage après soumission'
					: (currentFullscreenElement()
						? 'Fenêtre quittée sans sortir du plein écran : une autre application s’est ouverte par-dessus (assistant IA, Copilot, notification…)'
						: 'Perte de focus pendant l’évaluation');
				state.pendingSecurityWarning = exitWarningText();
				if (state.lockActive) {
					sendSecurityBeacon('focus_lost', 'info', securityDetails(detail));
					logEvent('focus_lost', 'info', securityDetails(detail));
					markPostSubmitFinalized();
				} else {
					blockExitAttempt('focus_lost', detail);
				}
			});
			window.addEventListener('focus', function () {
				if (translationInteractionActive()) return;
				if (!guardIsActive()) return;
			logEvent('window_focus', 'info', securityDetails('Retour du focus sur l’évaluation'));
			if (state.pendingSecurityWarning) {
				showSecurityWarning(state.pendingSecurityWarning);
			}
		});
			const onFullscreenChange = function () {
				if (guardIsActive() && state.fullscreenRequired && !currentFullscreenElement()) {
					blockExitAttempt('fullscreen_exit', 'Sortie du plein écran');
				} else if (currentFullscreenElement()) {
					state.fsWidth = 0;
					setTimeout(rememberFullscreenSize, 1200);
					hideFullscreenGuard();
					if (guardIsActive()) {
						logEvent('fullscreen_enter', 'info', securityDetails('Retour en plein écran'));
					}
				}
			};
			document.addEventListener('fullscreenchange', onFullscreenChange);
			document.addEventListener('webkitfullscreenchange', onFullscreenChange);
		document.addEventListener('contextmenu', function (event) {
			if (!guardIsActive()) return;
			if (isTranslationEvent(event) || translationInteractionActive()) return;
			event.preventDefault();
			logEvent('right_click', 'warning', securityDetails('Clic droit bloqué'));
			showSecurityWarning('Le clic droit est désactivé pendant cette évaluation. Cet événement a été enregistré.');
		});
		document.addEventListener('copy', function (event) {
			if (!guardIsActive()) return;
			if (isTranslationEvent(event) || translationInteractionActive()) return;
			event.preventDefault();
			logEvent('copy_attempt', 'warning', securityDetails('Tentative de copie'));
			showSecurityWarning('Le copier-coller est désactivé pendant cette évaluation. Cet événement a été enregistré.');
		});
		document.addEventListener('cut', function (event) {
			if (!guardIsActive()) return;
			if (isTranslationEvent(event) || translationInteractionActive()) return;
			event.preventDefault();
			logEvent('cut_attempt', 'warning', securityDetails('Tentative de couper'));
			showSecurityWarning('Le couper-coller est désactivé pendant cette évaluation. Cet événement a été enregistré.');
		});
		document.addEventListener('paste', function (event) {
			if (!guardIsActive()) return;
			if (isTranslationEvent(event) || translationInteractionActive()) return;
			event.preventDefault();
			logEvent('paste_attempt', 'warning', securityDetails('Tentative de collage'));
			showSecurityWarning('Le copier-coller est désactivé pendant cette évaluation. Cet événement a été enregistré.');
		});
		document.addEventListener('keydown', function (event) {
			if (!guardIsActive()) return;
			if (isTranslationEvent(event) || translationInteractionActive()) return;
			// Code source de la page (Ctrl+U) et outils de développement (F12, Ctrl+Maj+I/J/C) : désactivés.
			const key = String(event.key || '').toLowerCase();
			if (key === 'f12' || ((event.ctrlKey || event.metaKey) && (key === 'u' || (event.shiftKey && ['i', 'j', 'c'].includes(key)))) || (event.metaKey && event.altKey && ['i', 'j', 'c', 'u'].includes(key))) {
				event.preventDefault();
				event.stopPropagation();
				logEvent('devtools_shortcut', 'warning', securityDetails('Raccourci code source / outils de développement bloqué : ' + event.key));
				showSecurityWarning('L’affichage du code de la page est désactivé pendant cette évaluation. Cet événement a été enregistré.');
				return;
			}
			if ((event.ctrlKey || event.metaKey) && ['c', 'v', 'x', 'a', 'p', 's'].includes(event.key.toLowerCase())) {
				event.preventDefault();
				logEvent('keyboard_shortcut', 'warning', securityDetails('Raccourci clavier bloqué: ' + event.key));
				showSecurityWarning('Ce raccourci clavier est désactivé pendant cette évaluation. Cet événement a été enregistré.');
			}
		});
		window.addEventListener('resize', function () {
			if (!guardIsActive()) return;
			if (translationInteractionActive()) return;
			if (currentFullscreenElement() && (!state.fsWidth || physicalWidth() > state.fsWidth)) rememberFullscreenSize();
			if (!state.lockActive && sidePanelOpen()) {
				if (!state.pendingExit) {
					blockExitAttempt('side_panel', tabletMode()
						? 'Tablette : écran partagé, Stage Manager ou barre latérale de Safari ouverts (largeur ' + window.innerWidth + ' au lieu de ' + tabletFullWidth() + ')'
						: 'Panneau ouvert à côté de l’évaluation (assistant IA du navigateur ou autre outil) : largeur ' + physicalWidth() + ' px au lieu de ' + state.fsWidth + ' px, plein écran toujours actif');
				}
				return;
			}
			clearTimeout(state.resizeLogTimer);
			state.resizeLogTimer = setTimeout(function () {
				logEvent('window_resize', 'info', securityDetails('Redimensionnement de la fenêtre pendant l’évaluation'));
			}, 600);
		});
		startTypingWatch();
		document.addEventListener('click', function (event) {
			if (!guardIsActive()) return;
			if (isTranslationEvent(event) || translationInteractionActive()) {
				noteTranslationInteraction();
				return;
			}
			const link = event.target.closest('a[href]');
			if (!link || link.closest('[data-les-student]')) return;
			if (isTranslationUrl(link.href || link.getAttribute('href'))) {
				noteTranslationInteraction();
				return;
			}
			// Le lien ne s'ouvre pas : l'élève reste dans l'évaluation, ce n'est donc pas une sortie (simple avertissement).
			event.preventDefault();
			event.stopPropagation();
			logEvent('navigation_prevented', 'warning', securityDetails('Clic sur un lien hors de l’évaluation (lien désactivé, pas de sortie) : ' + (link.textContent || '').trim().slice(0, 60)));
			showSecurityWarning('Les liens hors de l’évaluation sont désactivés. Continuez votre évaluation.');
		}, true);
		document.addEventListener('submit', function (event) {
			if (!guardIsActive()) return;
			if (isTranslationEvent(event) || translationInteractionActive()) {
				noteTranslationInteraction();
				return;
			}
			if (event.target.closest('[data-les-student]')) return;
			event.preventDefault();
			event.stopPropagation();
			logEvent('navigation_prevented', 'warning', securityDetails('Envoi d’un formulaire hors de l’évaluation (désactivé, pas de sortie)'));
			showSecurityWarning('Ce formulaire est désactivé pendant l’évaluation. Continuez votre évaluation.');
		}, true);
	}

	function renderExam(showIntro) {
		applyAccommodations();
		root.querySelector('[data-les-title]').textContent = state.evaluation.title;
		root.querySelector('[data-les-instructions]').innerHTML = state.evaluation.instructions || '';
		renderExamIntro();
		const questionCount = root.querySelector('[data-les-question-count]');
		if (questionCount) questionCount.textContent = state.questions.length + ' question' + (state.questions.length > 1 ? 's' : '');
		restoreLocalDraft();
		answerForm.innerHTML = '';
		if (state.evaluation.evaluation_type === 'story') {
			answerForm.appendChild(renderStorySections());
		} else {
			// 1.38.5 : questions qui se suivent avec le même énoncé = un « problème » : l'énoncé est affiché une fois, en
			// tête, et reste visible pendant qu'on répond aux sous-questions a), b), c)…
			let problemNo = 0;
			let k = 0;
			while (k < state.questions.length) {
				const ctx = String(state.questions[k].context || '').trim();
				let end = k + 1;
				while (ctx && end < state.questions.length && String(state.questions[end].context || '').trim() === ctx) end++;
				if (end - k > 1) {
					problemNo++;
					const box = document.createElement('section');
					box.className = 'les-problem';
					box.innerHTML = '<div class="les-problem-statement"><span class="les-kicker">Problème ' + problemNo + ' · questions ' + (k + 1) + ' à ' + end + '</span><div class="les-question-context">' + state.questions[k].context + '</div></div>';
					const subs = document.createElement('div');
					subs.className = 'les-problem-subs';
					for (let j = k; j < end; j++) {
						const node = renderQuestion(state.questions[j], j, null, true);
						const title = node.querySelector('.les-question-title');
						if (title) title.textContent = (j + 1) + '. ' + String.fromCharCode(97 + ((j - k) % 26)) + ') ' + title.textContent.replace(/^\d+\.\s*/, '');
						subs.appendChild(node);
					}
					box.appendChild(subs);
					answerForm.appendChild(box);
				} else {
					answerForm.appendChild(renderQuestion(state.questions[k], k, state.questions[k - 1], false));
				}
				k = end;
			}
		}
		exam.hidden = false;
		if (examIntro) examIntro.hidden = showIntro === false ? true : false;
		if (questionsArea) questionsArea.hidden = showIntro !== false;
		if (lockScreen) lockScreen.hidden = true;
		if (companyBox) companyBox.hidden = true;
		state.lockActive = false;
		state.secureActive = true;
		updateProgress();
		renderCalculator();
		if (showIntro === false) {
			startExamTimer();
		}
		clearInterval(state.localSaveTimer);
		if (showIntro === false) {
			state.localSaveTimer = setInterval(saveLocalDraft, 30000);
		}
	}

	function showQuestionArea() {
		if (examIntro) examIntro.hidden = true;
		if (companyBox) companyBox.hidden = true;
		if (examHeader) examHeader.classList.add('is-compact');
		if (questionsArea) questionsArea.hidden = false;
		if (state.evaluation && state.evaluation.evaluation_type === 'story') {
			showStorySection(0, false);
		}
		renderCalculator();
		updateProgress();
		startExamTimer();
		clearInterval(state.localSaveTimer);
		state.localSaveTimer = setInterval(saveLocalDraft, 30000);
	}

	function showStorySection(index, showQuestions) {
		let activeSection = null;
		state.storySectionIndex = index;
		root.querySelectorAll('[data-story-section]').forEach((section) => {
			const active = Number(section.dataset.storySection) === index;
			section.hidden = !active;
			if (!active) return;
			activeSection = section;
			const intro = section.querySelector('[data-story-intro]');
			const questions = section.querySelector('[data-story-questions]');
			if (intro) intro.hidden = !!showQuestions;
			if (questions) questions.hidden = !showQuestions;
		});
		return activeSection;
	}

	function unansweredQuestions() {
		return state.questions.filter((question) => {
			return !answerHasValue(currentAnswer(question.id));
		});
	}

	function scrollToFirstUnansweredQuestion() {
		const first = unansweredQuestions()[0];
		if (!first) {
			if (questionsArea) questionsArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
			return;
		}
		const field = answerForm.querySelector('[data-question-id="' + first.id + '"]');
		if (!field) return;
		const storySection = field.closest('[data-story-section]');
		if (storySection) {
			showStorySection(Number(storySection.dataset.storySection || 0), true);
		}
		field.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function renderCompanyContext() {
		const box = companyBox;
		if (!box || !state.evaluation) return;
		const hasCompany = state.evaluation.company_name || state.evaluation.company_context || state.evaluation.apprentice_role || state.evaluation.company_logo_url;
		if (state.evaluation.evaluation_type !== 'mes_company' || !hasCompany) {
			box.hidden = true;
			box.innerHTML = '';
			return;
		}
		let html = '';
		if (state.evaluation.company_logo_url) {
			html += '<div class="les-company-logo"><img src="' + escapeAttr(state.evaluation.company_logo_url) + '" alt=""></div>';
		}
		html += '<div class="les-company-body">';
		if (state.evaluation.company_name) {
			html += '<span class="les-kicker">Entreprise fictive</span><h3>' + escapeHtml(state.evaluation.company_name) + '</h3>';
		}
		if (state.evaluation.company_context) {
			html += '<div>' + state.evaluation.company_context + '</div>';
		}
		if (state.evaluation.apprentice_role) {
			html += '<div class="les-apprentice-role"><strong>Votre rôle / mission :</strong><div>' + state.evaluation.apprentice_role + '</div></div>';
		}
		html += '</div>';
		box.innerHTML = html;
		box.hidden = false;
	}

	/* Grand avertissement « touches du clavier » (touche Windows, Échap, Alt + Tab…) : une mauvaise touche fait sortir de l'écran. */
	function keysWarningHtml(compact) {
		return '<div class="les-keys-warning' + (compact ? ' is-compact' : '') + '" role="note">' +
			'<p class="les-keys-title"><span class="les-keys-icon" aria-hidden="true">⚠</span>Attention aux touches du clavier !</p>' +
			'<p class="les-keys-lead">Une seule mauvaise touche peut vous faire sortir de l’évaluation et la bloquer. <strong>Regardez bien sur quelle touche vous appuyez avant de taper.</strong></p>' +
			'<div class="les-keys-main"><kbd class="les-key-win"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5.5 10 4.5v7H3zM11 4.3 21 3v8.5H11zM3 12.5h7v7l-7-1zM11 12.5h10V21l-10-1.4z"/></svg> Windows</kbd>' +
			'<span><strong>Ne touchez JAMAIS la touche Windows</strong> (en bas à gauche du clavier, entre <kbd>Ctrl</kbd> et <kbd>Alt</kbd>) : elle ouvre le menu Démarrer et vous fait sortir de l’écran.</span></div>' +
			'<ul class="les-keys-list">' +
			'<li><span class="les-keys-combo"><kbd>Échap</kbd></span><span>quitte le plein écran</span></li>' +
			'<li><span class="les-keys-combo"><kbd>Alt</kbd> + <kbd>Tab</kbd></span><span>change de fenêtre</span></li>' +
			'<li><span class="les-keys-combo"><kbd>F11</kbd></span><span>quitte le plein écran</span></li>' +
			'<li><span class="les-keys-combo"><kbd>Ctrl</kbd> + <kbd>W</kbd> / <kbd>T</kbd></span><span>ferme ou ouvre un onglet</span></li>' +
			'<li><span class="les-keys-combo"><kbd>⌘ Cmd</kbd> + <kbd>Tab</kbd></span><span>change d’application (Mac)</span></li>' +
			'</ul>' +
			'<p class="les-keys-help">En cas de doute, ne touchez à rien et levez la main pour appeler votre enseignant.</p>' +
			'</div>';
	}

	function renderExamIntro() {
		if (!state.evaluation) return;
		const securityBox = root.querySelector('[data-les-security-notice]');
		const storyBox = root.querySelector('[data-les-story-intro]');
		const beginButton = root.querySelector('[data-les-begin-questions]');
		const notice = state.evaluation.security_notice || "Pendant l'évaluation, vous ne devez pas quitter l'écran, changer d'onglet ou effectuer des recherches. Toute tentative de sortie est enregistrée et pourra être considérée comme une tricherie.";

		if (securityBox) {
			securityBox.innerHTML =
				'<div class="les-security-hero">' +
				'<span class="les-kicker">Surveillance active</span>' +
				'<h3>Ce qui sera contrôlé pendant l’évaluation</h3>' +
				'<p>' + notice + '</p>' +
				'</div>' +
				keysWarningHtml(false) +
				'<div class="les-security-rule-grid">' +
				'<div><strong>Actions interdites</strong><ul><li>Quitter le plein écran</li><li>Changer d’onglet ou de fenêtre</li><li>Réduire le navigateur</li><li>Copier, couper, coller ou utiliser le clic droit</li><li>Utiliser des raccourcis clavier sensibles</li><li><strong>Appuyer sur la touche Windows</strong></li></ul></div>' +
				'<div><strong>Conséquences automatiques</strong><ul class="les-security-steps">' + securitySteps() + '</ul></div>' +
				'</div>' +
				accommodationsHtml() +
				(tts.synth && audioAllowed() ? '<div class="les-tts-intro">🎧 <strong>Lecture audio :</strong> chaque question a un bouton « 🔊 Écouter » qui la lit à voix haute. Utilisez des écouteurs pour ne pas déranger les autres.</div>' : '') +
				'<div class="les-security-alert"><strong>Important :</strong> chaque alerte est enregistrée avec l’heure, le type d’événement et les détails techniques disponibles. Ces informations seront visibles par l’enseignant et dans l’export de correction.</div>';
			securityBox.hidden = false;
		}

		if (storyBox) {
			if (state.evaluation.evaluation_type === 'story' && state.evaluation.story_intro) {
				storyBox.innerHTML = '<span class="les-kicker">Introduction de l’histoire</span><div>' + state.evaluation.story_intro + '</div>';
				storyBox.hidden = false;
			} else {
				storyBox.innerHTML = '';
				storyBox.hidden = true;
			}
		}

		if (securityStart) securityStart.hidden = false;
		if (evaluationStart) evaluationStart.hidden = true;
		if (companyBox) companyBox.hidden = true;
		if (securityAck) securityAck.checked = false;
		// Écran « sortir de l'évaluation » d'abord, puis les règles et la case d'acceptation.
		const exitRules = root.querySelector('[data-les-exit-rules]');
		const ackStep = root.querySelector('[data-les-security-ack-step]');
		if (exitRules && ackStep) {
			exitRules.hidden = false;
			ackStep.hidden = true;
			const rulesAck = exitRules.querySelector('[data-les-exit-rules-ack]');
			const rulesOk = exitRules.querySelector('[data-les-exit-rules-ok]');
			if (rulesAck) rulesAck.checked = false;
			if (rulesOk) rulesOk.disabled = true;
		} else if (ackStep) {
			ackStep.hidden = false;
		}
		if (securityAckButton) {
			securityAckButton.disabled = true;
			securityAckButton.textContent = hasStartContext() ? 'Continuer' : 'Commencer l’évaluation en plein écran';
		}
		if (beginButton) {
			beginButton.textContent = 'Commencer l’évaluation en plein écran';
			beginButton.disabled = false;
		}
	}

	function beginExamOnServer(feedbackEl) {
		if (state.expiresAt) {
			startExamTimer();
			return Promise.resolve(true);
		}
		return post('logieval_secure_begin_questions', { attempt_id: state.attemptId, session_token: state.token }).then((json) => {
			if (!json.success) {
				if (feedbackEl) {
					feedbackEl.textContent = (json.data && json.data.message) || LogiEvalSecure.i18n.error;
					feedbackEl.className = 'les-message is-error';
				}
				return false;
			}
			state.expiresAt = json.data && json.data.expires_at ? new Date(json.data.expires_at.replace(' ', 'T') + 'Z') : state.expiresAt;
			startExamTimer();
			return true;
		}).catch(() => {
			if (feedbackEl) {
				feedbackEl.textContent = LogiEvalSecure.i18n.error;
				feedbackEl.className = 'les-message is-error';
			}
			return false;
		});
	}

	function renderCalculator() {}

	function calculatorParts(calculator) {
		return {
			expression: calculator ? calculator.querySelector('[data-les-calculator-expression]') : null,
			display: calculator ? calculator.querySelector('[data-les-calculator-display]') : null
		};
	}

	function setActiveCalculator(calculator) {
		root.querySelectorAll('.les-calculator.is-active').forEach((item) => {
			if (item !== calculator) item.classList.remove('is-active');
		});
		if (calculator) {
			calculator.classList.add('is-active');
			calculator.focus({ preventScroll: true });
		}
	}

	function activeCalculator() {
		const focused = document.activeElement && document.activeElement.closest ? document.activeElement.closest('.les-calculator') : null;
		if (focused && root.contains(focused) && !focused.closest('[hidden]')) {
			return focused;
		}
		const selected = root.querySelector('.les-calculator.is-active');
		return selected && !selected.closest('[hidden]') ? selected : null;
	}

	function appendCalculatorValue(calculator, value) {
		const parts = calculatorParts(calculator);
		if (!parts.expression || !parts.display) return;
		const current = parts.expression.textContent || '0';
		parts.expression.textContent = current === '0' || current === 'Erreur' ? value : current + value;
		if (calculator.dataset.resultShown === '1') {
			parts.display.textContent = '0';
			calculator.dataset.resultShown = '0';
		}
	}

	function backspaceCalculator(calculator) {
		const parts = calculatorParts(calculator);
		if (!parts.expression || !parts.display) return;
		const current = parts.expression.textContent || '0';
		parts.expression.textContent = current.length <= 1 || current === 'Erreur' ? '0' : current.slice(0, -1);
		if (calculator.dataset.resultShown === '1') {
			parts.display.textContent = '0';
			calculator.dataset.resultShown = '0';
		}
	}

	function evaluateCalculator(calculator) {
		const parts = calculatorParts(calculator);
		if (!parts.expression || !parts.display) return;
		const expression = normalizeCalculatorExpression(parts.expression.textContent || '0');
		if (!/^[0-9+\-*/.() MathPIsqrt]+$/.test(expression)) {
			parts.display.textContent = 'Erreur';
			return;
		}
		try {
			const result = Function('"use strict"; return (' + expression + ')')();
			parts.display.textContent = Number.isFinite(result) ? String(Math.round(result * 1000000) / 1000000) : 'Erreur';
			calculator.dataset.resultShown = '1';
		} catch (error) {
			parts.display.textContent = 'Erreur';
		}
	}

	function runCalculatorAction(calculator, value) {
		if (!calculator) return;
		setActiveCalculator(calculator);
		const parts = calculatorParts(calculator);
		if (!parts.expression || !parts.display) return;
		if (value === 'clear') {
			parts.expression.textContent = '0';
			parts.display.textContent = '0';
			calculator.dataset.resultShown = '0';
			return;
		}
		if (value === 'backspace') {
			backspaceCalculator(calculator);
			return;
		}
		if (value === 'sqrt') {
			appendCalculatorValue(calculator, '√(');
			return;
		}
		if (value === 'pi') {
			appendCalculatorValue(calculator, 'π');
			return;
		}
		if (value === 'percent') {
			appendCalculatorValue(calculator, '%');
			return;
		}
		if (value === 'equals') {
			evaluateCalculator(calculator);
			return;
		}
		appendCalculatorValue(calculator, value);
	}

	function calculatorKeyboardValue(event) {
		const key = event.key;
		if (/^[0-9]$/.test(key)) return key;
		if (['+', '-', '*', '/', '(', ')', '.'].includes(key)) return key;
		if (key === ',' || event.code === 'NumpadDecimal') return '.';
		if (key === 'Enter' || key === '=') return 'equals';
		if (key === 'Backspace') return 'backspace';
		if (key === 'Escape' || key.toLowerCase() === 'c') return 'clear';
		if (key === '%') return 'percent';
		if (key.toLowerCase() === 'p') return 'pi';
		if (key.toLowerCase() === 'r') return 'sqrt';
		return '';
	}

	function normalizeCalculatorExpression(expression) {
		let normalized = String(expression || '0').replace(/π/g, 'Math.PI').replace(/√\(/g, 'Math.sqrt(');
		normalized = normalized.replace(/(\d+(?:\.\d+)?)%/g, '($1/100)');
		normalized = normalized.replace(/(\d|\))Math\.PI/g, '$1*Math.PI');
		normalized = normalized.replace(/Math\.PI(\d|\()/g, 'Math.PI*$1');
		return normalized;
	}

	/* Minuteur masquable (élèves stressés) : alertes à mi-parcours et quand il reste un tiers du temps ;
	   pendant les 5 dernières minutes, le minuteur est toujours affiché. */
	function timerKey(name) {
		return 'les-timer-' + name + '-' + (state.attemptId || (state.evaluation && state.evaluation.id) || '0');
	}
	function timerStore(name, value) {
		try {
			if (value === undefined) return window.localStorage.getItem(timerKey(name));
			window.localStorage.setItem(timerKey(name), String(value));
		} catch (e) {
			return null;
		}
		return null;
	}
	function timerTotalSeconds(remainingSeconds) {
		const minutes = state.evaluation && parseInt(state.evaluation.duration_minutes, 10);
		if (minutes > 0) return minutes * 60;
		if (!state.timerTotal || remainingSeconds > state.timerTotal) state.timerTotal = remainingSeconds;
		return state.timerTotal;
	}
	function showTimerToast(text) {
		const toast = root.querySelector('[data-les-timer-toast]');
		if (!toast) return;
		toast.querySelector('[data-les-timer-toast-text]').textContent = text;
		toast.hidden = false;
		clearTimeout(state.timerToastTimeout);
		state.timerToastTimeout = setTimeout(function () { toast.hidden = true; }, 12000);
	}
	function updateTimerComfort(remaining) {
		const timer = root.querySelector('[data-les-timer]');
		const toggle = root.querySelector('[data-les-timer-toggle]');
		if (!timer || !toggle) return;
		const total = timerTotalSeconds(remaining);
		const ending = remaining <= 300;
		if (state.timerHidden === undefined) state.timerHidden = timerStore('hidden') === '1';
		if (state.timerStage === undefined) state.timerStage = parseInt(timerStore('stage') || '0', 10) || 0;
		const stage = ending ? 3 : (remaining <= total / 3 ? 2 : (remaining <= total / 2 ? 1 : 0));
		if (stage > state.timerStage) {
			const mins = Math.max(1, Math.round(remaining / 60));
			if (stage === 3 && state.timerHidden) {
				showTimerToast('Plus que 5 minutes ! Le minuteur est de nouveau affiché.');
			} else if (state.timerHidden && stage === 2) {
				showTimerToast('Il reste un tiers du temps : environ ' + mins + ' minute' + (mins > 1 ? 's' : '') + '.');
			} else if (state.timerHidden && stage === 1) {
				showTimerToast('Mi-parcours : il reste environ ' + mins + ' minute' + (mins > 1 ? 's' : '') + '.');
			}
			state.timerStage = stage;
			timerStore('stage', stage);
		}
		const masked = state.timerHidden && !ending;
		timer.classList.toggle('is-masked', masked);
		timer.classList.toggle('is-ending', ending && remaining > 0);
		if (masked) timer.textContent = 'Minuteur masqué';
		toggle.hidden = total <= 300;
		toggle.disabled = ending;
		toggle.setAttribute('aria-pressed', masked ? 'true' : 'false');
		toggle.textContent = ending ? '5 dernières minutes' : (masked ? 'Afficher le minuteur' : 'Masquer le minuteur');
	}
	root.addEventListener('click', function (event) {
		if (event.target.closest('[data-les-timer-toast-close]')) {
			const toast = root.querySelector('[data-les-timer-toast]');
			if (toast) toast.hidden = true;
			return;
		}
		const toggle = event.target.closest('[data-les-timer-toggle]');
		if (!toggle || toggle.disabled) return;
		state.timerHidden = !state.timerHidden;
		timerStore('hidden', state.timerHidden ? '1' : '0');
		if (state.expiresAt) updateExamTimer();
	});

	function startExamTimer() {
		clearInterval(state.examTimer);
		if (!state.expiresAt) {
			const timer = root.querySelector('[data-les-timer]');
			if (timer) timer.textContent = '--:--';
			return;
		}
		updateExamTimer();
		state.examTimer = setInterval(updateExamTimer, 1000);
		clearInterval(state.deadlinePollTimer);
		state.deadlinePollTimer = setInterval(pollServerDeadline, 30000);
	}

	function updateExamTimer() {
		const timer = root.querySelector('[data-les-timer]');
		const remaining = Math.max(0, state.expiresAt.getTime() - Date.now());
		const totalSeconds = Math.ceil(remaining / 1000);
		const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
		const seconds = String(totalSeconds % 60).padStart(2, '0');
		if (timer) timer.textContent = minutes + ':' + seconds;
		updateTimerComfort(totalSeconds);
			if (totalSeconds <= 0) {
				expireEvaluation();
			}
		}

	function beginPostSubmitLock(lockUntil) {
		state.lockActive = true;
		state.awaitingTeacherUnlock = false;
		state.teacherUnlockReady = false;
		state.fullscreenBlocked = false;
		stopTeacherUnlockPolling();
		state.lockUntil = lockUntil ? new Date(lockUntil.replace(' ', 'T') + 'Z') : new Date(Date.now() + 5 * 60 * 1000);
		exam.hidden = true;
		hideAccessPanel();
		if (lockScreen) lockScreen.hidden = false;
		setLockScreenContent(
			'post_submit',
			'Évaluation envoyée',
			'Votre copie est envoyée. Restez sur cette interface pendant 5 minutes. Si vous quittez l’écran maintenant, l’évaluation sera clôturée définitivement sans blocage, mais vous ne pourrez plus la reprendre.'
		);
		if (lockMessage) lockMessage.textContent = '';
		const reopen = root.querySelector('[data-les-reopen]');
		if (reopen) {
			reopen.hidden = false;
			reopen.disabled = false;
			reopen.textContent = 'Rouvrir mon évaluation';
		}
		updateLockTimer();
		clearInterval(state.lockTimer);
		state.lockTimer = setInterval(updateLockTimer, 1000);
	}

	function updateLockTimer() {
		const remaining = Math.max(0, state.lockUntil.getTime() - Date.now());
		const totalSeconds = Math.ceil(remaining / 1000);
		const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
		const seconds = String(totalSeconds % 60).padStart(2, '0');
		if (lockTimer) lockTimer.textContent = minutes + ':' + seconds;
			if (totalSeconds <= 0) {
				clearInterval(state.lockTimer);
				state.lockActive = false;
				state.secureActive = false;
				setLockScreenContent(
					'finalized',
					'Évaluation clôturée',
					'Votre évaluation est clôturée. Vous ne pouvez plus la reprendre.'
				);
				if (lockMessage) lockMessage.textContent = 'Le délai de 5 minutes est terminé. Votre évaluation est clôturée définitivement. Vous pouvez maintenant quitter cette interface.';
				post('logieval_secure_finalize_lock', { attempt_id: state.attemptId, session_token: state.token }).catch(function () {});
			}
	}

	window.addEventListener('beforeunload', function (event) {
			if (!guardIsActive()) return;
			const eventType = state.lockActive ? 'post_submit_exit_attempt' : 'evaluation_exit_attempt';
			const severity = state.lockActive ? 'info' : 'warning';
			sendSecurityBeacon(eventType, severity, 'Tentative de fermeture, actualisation ou navigation hors de l’évaluation');
			if (state.lockActive) {
				markPostSubmitFinalized();
				return;
			}
			event.preventDefault();
			event.returnValue = exitWarningText();
			return event.returnValue;
	});

	window.addEventListener('pagehide', function () {
		if (!guardIsActive()) return;
		sendSecurityBeacon(
				state.lockActive ? 'post_submit_exit_confirmed' : 'evaluation_exit_confirmed',
				state.lockActive ? 'info' : 'warning',
				securityDetails(state.lockActive ? "L'élève est sorti pendant le délai après soumission : évaluation clôturée définitivement" : "L'élève est sorti de l'interface pendant l'évaluation")
			);
	});

	root.querySelector('[data-les-start-form]').addEventListener('submit', function (event) {
		event.preventDefault();
		setMessage('', false);
		const code = event.currentTarget.querySelector('[name="code"]').value;
		let accessGranted = false;
		post('logieval_secure_start', { code }).then((json) => {
			if (!json.success) {
				setMessage((json.data && json.data.message) || LogiEvalSecure.i18n.error, true);
				if (json.data && json.data.seb_required) showSebPanel(json.data);
				return;
			}
			hideSebPanel();
			accessGranted = true;
			state.sebRequired = !!json.data.seb;
			state.watermark = json.data.watermark || '';
			state.sebQuitUrl = json.data.seb_quit_url || '';
			state.attemptId = json.data.attempt_id;
			state.token = json.data.session_token;
			state.evaluation = json.data.evaluation;
			state.questions = json.data.questions || [];
			state.answers = json.data.answers || {};
			state.expiresAt = json.data.expires_at ? new Date(json.data.expires_at.replace(' ', 'T') + 'Z') : null;
			state.secureActive = true;
			setMessage('', false);
			const resumeState = json.data.resume_state || 'new';
			if (resumeState === 'blocked' || resumeState === 'unlocked') {
				// Retour après rechargement / fermeture de la page : on réaffiche directement l'écran de blocage.
				renderExam(false);
				activateSecureMode();
				state.securityActionApplied = true;
				state.unlockDeadlineOverride = json.data.unlock_requested ? null : (json.data.unlock_deadline_left === undefined ? null : json.data.unlock_deadline_left);
				showBlockedAwaitingTeacher(json.data.resume_message || 'Votre évaluation est bloquée. Demandez le déblocage à votre enseignant.');
				const reopen = root.querySelector('[data-les-reopen]');
				if (resumeState === 'unlocked') {
					markTeacherUnlockReady(json.data.resume_message);
				} else if (json.data.unlock_requested && reopen) {
					state.unlockRequested = true;
					reopen.disabled = true;
					reopen.textContent = 'Demande envoyée - en attente...';
					if (lockTimer) lockTimer.textContent = 'En attente';
					if (lockMessage) lockMessage.textContent = 'Votre demande de déblocage est déjà enregistrée. Restez sur cette page : un bouton « Reprendre l’évaluation » apparaîtra dès que votre enseignant vous aura débloqué.';
					setTimeout(function () {
						if (state.awaitingTeacherUnlock && !state.teacherUnlockReady) {
							reopen.disabled = false;
							reopen.textContent = 'Renvoyer la demande de déblocage';
						}
					}, 30000);
				}
				return;
			}
			renderExam(true);
			hideAccessPanel();
			activateSecureMode();
		}).catch((error) => {
			if (accessGranted) {
				hideAccessPanel();
				if (window.console && window.console.error) window.console.error(error);
				return;
			}
			setMessage(LogiEvalSecure.i18n.error, true);
		});
	});

	// Lance l'évaluation : plein écran demandé directement dans le clic (obligatoire pour Safari),
	// démarrage du chrono côté serveur, puis affichage des questions.
	function startExamFromClick(button, feedbackEl) {
		if (tabletSplit()) {
			const text = 'Sur tablette, l’évaluation doit occuper tout l’écran : fermez l’écran partagé (ou l’autre app à côté) et la barre latérale de Safari, puis touchez à nouveau ce bouton.';
			if (feedbackEl) { feedbackEl.textContent = text; feedbackEl.className = 'les-message is-error'; }
			else if (securityMessage) { securityMessage.textContent = text; securityMessage.className = 'les-message is-error'; }
			return;
		}
		button.disabled = true;
		if (feedbackEl) feedbackEl.textContent = '';
		if (securityStart) securityStart.hidden = true;
		if (evaluationStart) evaluationStart.hidden = true;
		const fullscreenPromise = requestExamFullscreen();
		Promise.all([fullscreenPromise, beginExamOnServer(feedbackEl)]).then((results) => {
			const fullscreenOk = results[0];
			const ok = results[1];
			button.disabled = false;
			if (!ok) {
				exitExamFullscreen();
				if (securityStart) securityStart.hidden = false;
				return;
			}
			// Dans Safe Exam Browser, l'écran est déjà verrouillé : pas d'exigence de plein écran du navigateur.
			state.fullscreenRequired = fullscreenNeeded();
			showQuestionArea();
			if (state.fullscreenRequired && (!fullscreenOk || !currentFullscreenElement())) {
				showFullscreenGuard('Le plein écran est obligatoire pour continuer. Cliquez sur « Réactiver le plein écran ».');
			}
		}).catch(() => {
			button.disabled = false;
		});
	}

	// Contexte à lire avant de commencer : entreprise fictive (MES) ou introduction d'histoire.
	function hasStartContext() {
		const ev = state.evaluation || {};
		if (ev.evaluation_type === 'story') return !!ev.story_intro;
		return ev.evaluation_type === 'mes_company' && !!(ev.company_name || ev.company_context || ev.apprentice_role || ev.company_logo_url);
	}

	const beginButton = root.querySelector('[data-les-begin-questions]');
	if (beginButton) {
		beginButton.addEventListener('click', function () {
			startExamFromClick(beginButton, beginMessage);
		});
	}
	(function () {
		const exitRules = root.querySelector('[data-les-exit-rules]');
		const ackStep = root.querySelector('[data-les-security-ack-step]');
		if (!exitRules || !ackStep) return;
		const rulesAck = exitRules.querySelector('[data-les-exit-rules-ack]');
		const rulesOk = exitRules.querySelector('[data-les-exit-rules-ok]');
		rulesAck.addEventListener('change', function () { rulesOk.disabled = !rulesAck.checked; });
		rulesOk.addEventListener('click', function () {
			if (!rulesAck.checked) return;
			exitRules.hidden = true;
			ackStep.hidden = false;
			if (securityStart && securityStart.scrollIntoView) securityStart.scrollIntoView({ block: 'start' });
		});
	})();
	if (securityAck && securityAckButton) {
		securityAck.addEventListener('change', function () {
			securityAckButton.disabled = !securityAck.checked;
			if (securityAck.checked && securityMessage) {
				securityMessage.textContent = '';
				securityMessage.className = 'les-message';
			}
		});
	}

	if (securityAckButton) {
		securityAckButton.addEventListener('click', function () {
			if (aiToolsBlockStart()) return;
			if (securityAck && !securityAck.checked) {
				if (securityMessage) {
					securityMessage.textContent = 'Vous devez confirmer avoir pris connaissance des règles avant d’accéder à l’évaluation.';
					securityMessage.className = 'les-message is-error';
				}
				return;
			}
			// Règles acceptées une seule fois. S'il y a un contexte à lire, il s'affiche sur un écran séparé ;
			// sinon l'évaluation démarre directement en plein écran.
			if (!hasStartContext()) {
				startExamFromClick(securityAckButton, securityMessage);
				return;
			}
			if (securityStart) securityStart.hidden = true;
			if (evaluationStart) evaluationStart.hidden = false;
			renderCompanyContext();
			const top = (companyBox && !companyBox.hidden) ? companyBox : evaluationStart;
			if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	}

	root.addEventListener('click', function (event) {
		const pointsButton = event.target.closest('[data-les-set-points]');
		if (pointsButton) {
			const wrap = pointsButton.closest('.les-points-tools');
			const input = wrap ? wrap.querySelector('input[name^="manual_points"]') : null;
			if (input) {
				input.value = pointsButton.dataset.lesSetPoints || '0';
				input.dispatchEvent(new Event('change', { bubbles: true }));
			}
			return;
		}
		const key = event.target.closest('[data-calc]');
		if (!key) return;
		const calculator = key.closest('.les-calculator');
		runCalculatorAction(calculator, key.dataset.calc);
	});

	root.addEventListener('keydown', function (event) {
		const target = event.target;
		if (target && target.closest && target.closest('input, textarea, select')) return;
		const calculator = activeCalculator();
		if (!calculator) return;
		const value = calculatorKeyboardValue(event);
		if (!value) return;
		event.preventDefault();
		runCalculatorAction(calculator, value);
	});

	answerForm.addEventListener('change', function (event) {
		const field = event.target.closest('[data-question-id]');
		if (!field) return;
		updateProgress();
		saveLocalDraft();
		post('logieval_secure_save_answer', {
			attempt_id: state.attemptId,
			session_token: state.token,
			question_id: field.dataset.questionId,
			answer: currentAnswer(field.dataset.questionId)
		}).then(applyServerDeadline).catch(() => {});
	});

	answerForm.addEventListener('click', function (event) {
		const start = event.target.closest('[data-story-start-section]');
		if (start) {
			event.preventDefault();
			showStorySection(state.storySectionIndex, true);
			return;
		}
		const next = event.target.closest('[data-story-next-section]');
		if (!next) return;
		event.preventDefault();
		saveLocalDraft();
		const sections = storySections();
		const nextIndex = state.storySectionIndex + 1;
		if (nextIndex >= sections.length) {
			setMessage('Toutes les sections sont terminées. Vous pouvez relire vos réponses ou soumettre votre évaluation.', false);
			return;
		}
		const activeSection = showStorySection(nextIndex, false);
		if (activeSection) {
			activeSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	});

	const fullscreenButton = root.querySelector('[data-les-fullscreen]');
	if (fullscreenButton) {
		fullscreenButton.addEventListener('click', function () {
			requestExamFullscreen().then(function (ok) {
				if (!ok) showFullscreenGuard('Le navigateur a refusé le plein écran. Cliquez à nouveau ou vérifiez les permissions du navigateur.');
			});
		});
	}

		const returnFullscreenButton = root.querySelector('[data-les-return-fullscreen]');
		if (returnFullscreenButton) {
			returnFullscreenButton.addEventListener('click', function () {
				returnFullscreenButton.disabled = true;
				requestExamFullscreen().then(function (ok) {
					returnFullscreenButton.disabled = false;
					if (ok) {
						hideFullscreenGuard();
					} else if (fullscreenMessage) {
						fullscreenMessage.textContent = 'Le navigateur a refusé le plein écran. Réessayez avec ce bouton ou appelez votre enseignant.';
						fullscreenMessage.className = 'les-message is-error';
					}
				});
			});
		}

		root.querySelector('[data-les-submit]').addEventListener('click', function () {
			if (state.timeExpired) {
				expireEvaluation();
				return;
			}
			if (state.fullscreenRequired && !currentFullscreenElement()) {
				showFullscreenGuard('Le plein écran est obligatoire pour soumettre ou continuer l’évaluation.');
				return;
			}
			if (state.expiresAt && state.expiresAt.getTime() <= Date.now()) {
				expireEvaluation();
				return;
			}
		const missing = unansweredQuestions();
		if (missing.length) {
			showSubmitConfirm(missing);
			return;
		}
		submitEvaluation();
	});

		function resumeAfterTeacherUnlock(reopenButton) {
			reopenButton.disabled = true;
			// Plein écran demandé IMMÉDIATEMENT dans le clic (avant tout appel serveur).
			const fullscreenPromise = requestExamFullscreen();
			if (lockMessage) {
				lockMessage.textContent = 'Reprise de l’évaluation...';
				lockMessage.className = 'les-message';
			}
			const serverPromise = post('logieval_secure_resume_after_unlock', { attempt_id: state.attemptId, session_token: state.token, requires_teacher_unlock: 1 });
			return Promise.all([fullscreenPromise, serverPromise]).then(function (results) {
				const fullscreenOk = results[0];
				const json = results[1];
				if (!json || !json.success) {
					// Le serveur n'a pas (encore) validé le déblocage : on reste bloqué.
					exitExamFullscreen();
					state.teacherUnlockReady = false;
					reopenButton.textContent = state.unlockRequested ? 'Demande envoyée - en attente...' : 'Demander un déblocage';
					reopenButton.disabled = !!state.unlockRequested;
					if (lockMessage) {
						lockMessage.textContent = (json && json.data && json.data.message) || 'Votre enseignant n’a pas encore autorisé la reprise.';
						lockMessage.className = 'les-message is-error';
					}
					startTeacherUnlockPolling();
					return json;
				}
				state.securityStopped = false;
				state.securityActionApplied = false;
				state.awaitingTeacherUnlock = false;
				state.teacherUnlockReady = false;
				state.unlockRequested = false;
				stopTeacherUnlockPolling();
				state.secureActive = true;
				state.lockActive = false;
				state.fullscreenRequired = fullscreenNeeded();
				state.pendingSecurityWarning = '';
				state.evaluation = json.data.evaluation;
				state.questions = json.data.questions || [];
				state.answers = json.data.answers || {};
				state.expiresAt = json.data.expires_at ? new Date(json.data.expires_at.replace(' ', 'T') + 'Z') : state.expiresAt;
				renderExam(false);
				if (examHeader) examHeader.classList.add('is-compact');
				if (lockScreen) {
					lockScreen.hidden = true;
					lockScreen.dataset.lesLockMode = '';
				}
				if (lockMessage) lockMessage.textContent = '';
				setMessage('', false);
				if (beginMessage) beginMessage.textContent = '';
				if (fullscreenOk && currentFullscreenElement()) {
					hideFullscreenGuard();
				} else {
					// Le navigateur a refusé : les questions restent masquées tant que le plein écran n'est pas réactivé.
					showFullscreenGuard('Votre enseignant vous a débloqué. Cliquez sur « Réactiver le plein écran » pour continuer votre évaluation.');
				}
				setTimeout(scrollToFirstUnansweredQuestion, 150);
				return json;
			}).catch(function () {
				exitExamFullscreen();
				if (lockMessage) {
					lockMessage.textContent = 'Connexion impossible. Vérifiez le réseau puis cliquez à nouveau sur « Reprendre l’évaluation ».';
					lockMessage.className = 'les-message is-error';
				}
			}).finally(function () {
				if (!state.unlockRequested || state.teacherUnlockReady) reopenButton.disabled = false;
			});
		}

		function requestTeacherUnlock(reopenButton) {
			reopenButton.disabled = true;
			if (lockMessage) {
				lockMessage.textContent = 'Envoi de la demande de déblocage...';
				lockMessage.className = 'les-message';
			}
			return ensureTeacherVisibleBlock('Demande de déblocage envoyée par l’élève après blocage de sécurité.').then(function (json) {
				const data = json && json.success ? json.data : null;
				if (!data) {
					throw new Error((json && json.data && json.data.message) || LogiEvalSecure.i18n.error);
				}
				if (data.action === 'unlocked') {
					markTeacherUnlockReady(data.message);
					return;
				}
				if (data.action === 'terminated' || data.action === 'submitted') {
					state.securityActionApplied = false;
					applySecurityAction(data);
					return;
				}
				state.unlockRequested = true;
				reopenButton.textContent = 'Demande envoyée - en attente...';
				if (lockTimer) lockTimer.textContent = 'En attente';
				if (lockMessage) {
					lockMessage.textContent = 'Votre demande de déblocage a été envoyée à votre enseignant. Restez sur cette page : dès qu’il vous aura débloqué, un bouton « Reprendre l’évaluation » apparaîtra ici.';
					lockMessage.className = 'les-message';
				}
				startTeacherUnlockPolling();
				// Permet de renvoyer la demande si l'enseignant ne réagit pas.
				setTimeout(function () {
					if (state.awaitingTeacherUnlock && !state.teacherUnlockReady) {
						reopenButton.disabled = false;
						reopenButton.textContent = 'Renvoyer la demande de déblocage';
					}
				}, 30000);
			}).catch(function (error) {
				reopenButton.disabled = false;
				if (lockMessage) {
					lockMessage.textContent = 'La demande n’a pas pu être transmise. Réessayez ou prévenez votre enseignant.';
					lockMessage.className = 'les-message is-error';
				}
			});
		}

		const reopenButton = root.querySelector('[data-les-reopen]');
		if (reopenButton) {
			reopenButton.addEventListener('click', function () {
				const mode = lockMode();
				// 1) Blocage sécurité : uniquement demande de déblocage ou reprise autorisée (jamais logieval_secure_reopen).
				if (mode === 'security') {
					if (state.teacherUnlockReady) {
						resumeAfterTeacherUnlock(reopenButton);
					} else {
						requestTeacherUnlock(reopenButton);
					}
					return;
				}
				// 2) Seul l'écran « Évaluation envoyée » (5 minutes) permet de rouvrir sa copie.
				if (mode !== 'post_submit' || !state.lockActive) {
					reopenButton.hidden = true;
					return;
				}
				state.awaitingTeacherUnlock = false;
				state.teacherUnlockReady = false;
				stopTeacherUnlockPolling();
			reopenButton.disabled = true;
			if (isPostSubmitLockScreen()) {
				reopenButton.textContent = 'Rouvrir mon évaluation';
				if (lockMessage) lockMessage.textContent = 'Réouverture de votre évaluation...';
			}
			post('logieval_secure_reopen', { attempt_id: state.attemptId, session_token: state.token }).then((json) => {
				if (!json.success) {
					reopenButton.disabled = false;
					const message = (json.data && json.data.message) || 'Cette évaluation ne peut plus être rouverte. Elle est clôturée définitivement.';
					if (isPostSubmitLockScreen()) {
						markPostSubmitFinalized(message);
					} else if (lockMessage) {
						lockMessage.textContent = message;
					}
					return;
				}
				reopenButton.disabled = false;
				if (lockScreen) lockScreen.dataset.lesLockMode = '';
				state.evaluation = json.data.evaluation;
				state.questions = json.data.questions || [];
				state.answers = json.data.answers || {};
				state.expiresAt = json.data.expires_at ? new Date(json.data.expires_at.replace(' ', 'T') + 'Z') : state.expiresAt;
				renderExam(false);
			}).catch(function () {
				reopenButton.disabled = false;
				if (lockMessage) lockMessage.textContent = LogiEvalSecure.i18n.error;
			});
		});
	}

	function escapeHtml(value) {
		const div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);
		return div.innerHTML;
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}
})();

/* Barre de progression toujours visible : hauteur réservée pour les ancres, sous la barre d'administration hors plein écran. */
(function () {
	'use strict';
	function setup(wrap) {
		var root = wrap.closest('.logieval-secure') || document.documentElement;
		/* 1.33.4 : le minuteur (et « Masquer le minuteur ») est déplacé dans la barre de progression, toujours visible ;
		   il n'est plus affiché deux fois. */
		var mainTimer = root.querySelector('[data-les-timer]');
		var meta = wrap.querySelector('.les-progress-meta');
		if (mainTimer && meta && !meta.querySelector('[data-les-timer]')) {
			var box = document.createElement('span');
			box.className = 'les-progress-timer';
			var toggleBtn = root.querySelector('[data-les-timer-toggle]');
			var clockBox = root.querySelector('[data-les-clock]');
			if (clockBox) { box.appendChild(clockBox); } /* 1.42.0 : l'heure, juste avant le compte à rebours */
			box.appendChild(mainTimer);
			if (toggleBtn) { box.appendChild(toggleBtn); }
			meta.appendChild(box);
		}
		/* Hauteur occupée en haut de l'écran par les bandeaux fixes du site (barre d'administration, en-tête du thème
		   collé en haut…). La barre des questions se place juste dessous : sinon l'en-tête du site recouvre les
		   pastilles et le clic tombe sur son logo (un lien), ce qui était compté comme une sortie. */
		function stickyTop() {
			// 1.33.4 : bandeaux empilés (barre d'administration, puis en-tête du thème juste dessous) : on cherche
			// d'abord en haut, puis juste sous le bandeau trouvé, jusqu'à ce qu'il n'y en ait plus.
			var total = 0;
			for (var pass = 0; pass < 4; pass++) {
				var next = stickyBottomAt(total + 2);
				if (next <= total) { break; }
				total = next;
			}
			return total;
		}
		function stickyBottomAt(y) {
			var bottom = 0;
			var w = window.innerWidth || document.documentElement.clientWidth;
			[8, Math.round(w / 4), Math.round(w / 2), Math.round(w * 3 / 4), w - 8].forEach(function (x) {
				var stack = document.elementsFromPoint ? document.elementsFromPoint(x, y) : [];
				stack.forEach(function (el) {
					if (el === document.documentElement || el === document.body || root.contains(el) || wrap.contains(el)) { return; }
					for (var n = el; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
						if (n.contains(wrap)) { break; }
						var cs = getComputedStyle(n);
						if (cs.position === 'fixed' || cs.position === 'sticky') {
							if (cs.visibility !== 'hidden' && cs.display !== 'none') {
								var r = n.getBoundingClientRect();
								if (r.top <= y && r.bottom > y && r.height < window.innerHeight / 2) { bottom = Math.max(bottom, Math.round(r.bottom)); }
							}
							break;
						}
					}
				});
			});
			return bottom;
		}
		function update() {
			var top = stickyTop();
			root.style.setProperty('--les-sticky-top', top + 'px');
			root.style.setProperty('--les-progress-h', wrap.offsetHeight + 'px');
			var r = wrap.getBoundingClientRect();
			wrap.classList.toggle('is-stuck', wrap.offsetParent !== null && r.top <= top + 1 && window.scrollY + r.top > 0 && (root.scrollTop > 0 || window.scrollY > 0));
		}
		var queued = false;
		function schedule() {
			if (queued) { return; }
			queued = true;
			window.requestAnimationFrame(function () { queued = false; update(); });
		}
		document.addEventListener('scroll', schedule, true);
		window.addEventListener('resize', update);
		document.addEventListener('fullscreenchange', update);
		document.addEventListener('webkitfullscreenchange', update);
		if (window.ResizeObserver) { new ResizeObserver(update).observe(wrap); }
		update();
	}
	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.logieval-secure .les-progress-wrap'), setup);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());

/* « Points de l'IA » : l'enseignant attribue la proposition de l'IA (une réponse, ou toutes celles d'une copie / d'une question).
   Le commentaire de l'IA n'est repris que si le champ commentaire est vide. Rien n'est enregistré avant « Enregistrer ». */
(function () {
	'use strict';
	function targets(button) {
		var scope = button.closest('tr, .les-qcard') || button.closest('.les-answer-grade') || button.closest('.les-points-tools');
		var tools = button.closest('.les-question-grade-form') || button.closest('.les-points-tools') || scope;
		return {
			scope: scope,
			points: tools ? tools.querySelector('input[name^="manual_points"], input[name^="question_manual_points"]') : null,
			comment: scope ? scope.querySelector('textarea[name^="teacher_comment"], textarea[name^="question_teacher_comment"]') : null
		};
	}
	function same(a, b) {
		var x = parseFloat(String(a).replace(',', '.'));
		var y = parseFloat(String(b).replace(',', '.'));
		return !isNaN(x) ? x === y : String(a) === String(b);
	}
	/* Statut de chaque critère d'après l'analyse IA (« Critère 2 partiellement rempli : … »). */
	function criteriaStatuses(analysis) {
		var out = {};
		String(analysis || '').split(/[\r\n]+|(?<=[.!?…»])\s+(?=Crit[èe]re\s*\d)/u).forEach(function (line) {
			var raw = line.replace(/^\s*(?:[-–•*]|\d+[.)])\s+/, '').trim();
			var sym = raw.match(/^([✓✔◐✗✘])\s*/u);
			var m = raw.replace(/^[✓✔◐✗✘→]\s*/u, '').match(/^crit[èe]re\s*(\d+)\s*([^:]*)/iu);
			if (!m) { return; }
			if (sym) { out[m[1]] = /[✓✔]/u.test(sym[1]) ? 'ok' : (sym[1] === '◐' ? 'partial' : 'miss'); return; }
			var head = m[2].toLowerCase();
			if (/(^|\s)(non|pas|absent|manquant)(\s|$)/.test(head)) { out[m[1]] = 'miss'; }
			else if (head.indexOf('partiel') !== -1) { out[m[1]] = 'partial'; }
			else if (/(rempli|respecté|présent|validé|atteint|correct)/.test(head)) { out[m[1]] = 'ok'; }
		});
		return out;
	}
	var BADGES = { ok: ['✓ IA', 'IA : rempli'], partial: ['◐ IA', 'IA : partiellement rempli'], miss: ['✗ IA', 'IA : non rempli'] };
	/* Nouvelle analyse IA reçue : met à jour les pastilles ✓ / ◐ / ✗ à côté des critères. */
	function markCriteria(scope, analysis) {
		var st = criteriaStatuses(analysis);
		Array.prototype.forEach.call(scope ? scope.querySelectorAll('[data-les-criterion]') : [], function (box, k) {
			var s = st[String(k + 1)] || '';
			box.dataset.aiStatus = s;
			var badge = box.closest('label') ? box.closest('label').querySelector('.les-ai-crit') : null;
			if (badge) { badge.hidden = !s; badge.className = 'les-ai-crit is-' + s; badge.textContent = s ? BADGES[s][0] : ''; badge.title = s ? BADGES[s][1] : ''; }
		});
	}
	/* Coche les critères que l'IA juge remplis (sans recalculer les points : ce sont ceux de l'IA qui comptent). */
	function applyCriteria(scope) {
		var list = [];
		Array.prototype.forEach.call(scope ? scope.querySelectorAll('[data-les-criterion]') : [], function (box) {
			var s = box.dataset.aiStatus || '';
			if (!s) { return; }
			box.checked = s === 'ok';
			list.push({ label: box.dataset.label || '', status: s });
		});
		return list;
	}
	/* Commentaire pour l'élève : les critères (remplis, partiels, non remplis), puis le commentaire de l'IA. */
	/* Commentaire pour l'élève : celui rédigé par l'IA (phrases simples qui expliquent les points donnés ou non). */
	function buildComment(scope, list, aiComment) {
		return String(aiComment || '').trim();
	}
	window.lesAiCriteria = { mark: markCriteria, apply: applyCriteria, comment: buildComment };
	function apply(button) {
		var t = targets(button);
		if (!t.points || button.dataset.points === '' || button.dataset.points === undefined) { return false; }
		t.points.value = button.dataset.points;
		t.points.dispatchEvent(new Event('input', { bubbles: true }));
		t.points.dispatchEvent(new Event('change', { bubbles: true }));
		var statuses = applyCriteria(t.scope);
		var text = buildComment(t.scope, statuses, button.dataset.comment || '');
		if (t.comment && text && (!t.comment.value.trim() || t.comment.value === t.comment.dataset.autoComment)) {
			t.comment.value = text;
			t.comment.dataset.autoComment = text;
			t.comment.dispatchEvent(new Event('change', { bubbles: true }));
		}
		var state = t.scope ? t.scope.querySelector('.les-save-state') : null;
		if (state) { state.textContent = 'À enregistrer'; state.className = 'les-save-state les-alert-badge is-warning'; }
		button.classList.add('is-applied');
		return true;
	}
	document.addEventListener('click', function (event) {
		var one = event.target.closest ? event.target.closest('[data-les-ai-accept]') : null;
		if (one) { apply(one); return; }
		var all = event.target.closest ? event.target.closest('[data-les-ai-accept-all]') : null;
		if (!all) { return; }
		var area = all.closest('.les-question-correction-section') || all.closest('form') || document;
		var buttons = Array.prototype.filter.call(area.querySelectorAll('[data-les-ai-accept]'), function (b) { return !b.hidden; });
		var differing = buttons.filter(function (b) { var t = targets(b); return t.points ? (t.points.value.trim() !== '' ? !same(t.points.value.trim(), b.dataset.points) : false) : false; });
		var replace = true;
		if (differing.length) {
			replace = window.confirm(differing.length + ' question(s) ont déjà des points différents de la proposition de l’IA. Les remplacer aussi ?\n\nOK : tout remplacer · Annuler : compléter seulement les questions sans points.');
		}
		var done = 0;
		buttons.forEach(function (b) {
			if (!replace && differing.indexOf(b) !== -1) { return; }
			if (apply(b)) { done++; }
		});
		var status = all.parentElement ? all.parentElement.querySelector('.les-ai-accept-status') : null;
		if (status) { status.textContent = done + ' proposition(s) de l’IA attribuée(s). Pensez à enregistrer.'; }
	});
}());

/* « Corbeille » : ouvre la liste des évaluations supprimées sans recharger la page. */
(function () {
	'use strict';
	document.addEventListener('click', function (event) {
		var link = event.target.closest ? event.target.closest('[data-les-open-trash]') : null;
		var box = link ? document.getElementById('les-trash') : null;
		if (!box) { return; }
		event.preventDefault();
		box.open = true;
		box.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});
}());

/* LogiOne 1.36.4 : « Points manuels » à côté des boutons ; encadré de couleur quand les points ont été saisis à la main
   (différents de la proposition de l'IA et de la proposition automatique ; 0 d'office d'une question vide non compris). */
(function () {
	'use strict';
	function num(v) { var x = parseFloat(String(v === undefined || v === null ? '' : v).replace(',', '.')); return isNaN(x) ? null : x; }
	function mark(input) {
		var scope = input.closest('.les-answer-grade') || input.closest('tr, .les-qcard');
		var label = input.closest('[data-les-manual-points]') || input.parentElement;
		var v = num(input.value);
		var accept = scope ? scope.querySelector('[data-les-ai-accept]') : null;
		var ai = accept && !accept.hidden ? num(accept.dataset.points) : null;
		var auto = num(input.dataset.autoPoints);
		var blankZero = scope && scope.hasAttribute('data-les-blank-answer') && v === 0;
		var manual = v !== null && !blankZero && !(ai !== null && Math.abs(ai - v) < 0.001) && !(auto !== null && Math.abs(auto - v) < 0.001);
		var fromAi = v !== null && ai !== null && Math.abs(ai - v) < 0.001;
		input.classList.toggle('is-manual', manual);
		input.classList.toggle('is-ai', fromAi);
		if (label) { label.classList.toggle('is-manual', manual); }
	}
	function all() { Array.prototype.forEach.call(document.querySelectorAll('input[name^="manual_points"], input[name^="question_manual_points"]'), mark); }
	document.addEventListener('input', function (e) { if (e.target.matches && e.target.matches('input[name^="manual_points"], input[name^="question_manual_points"]')) { mark(e.target); } });
	document.addEventListener('change', function (e) { if (e.target.matches && e.target.matches('input[name^="manual_points"], input[name^="question_manual_points"]')) { mark(e.target); } });
	// Boutons « Tous / Moitié / Aucun / Points de l'IA » : la valeur change sans événement → on revérifie après le clic.
	document.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('[data-les-set-points], [data-les-ai-accept], [data-les-ai-accept-all]')) { setTimeout(all, 0); }
	});
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', all); } else { all(); }
	window.lesMarkManualPoints = all;
}());

/* Correction par élève, une question à la fois (LogiOne 1.24.5) : barre « Question 2 / 8 » avec pastilles,
   un raccourci de points (Tous / Moitié / Aucun / Points de l'IA) affiche directement la question suivante,
   et un bouton « Question suivante → » sert après une saisie manuelle (ou Entrée dans le champ).
   « Afficher toutes les questions » revient à l'affichage complet (choix mémorisé). En correction par question,
   un raccourci passe à l'élève suivant. */
(function () {
	'use strict';
	var KEY_NEXT = 'les_auto_next';
	var KEY_PAGER = 'les_q_pager';
	function pref(key) {
		try { return window.localStorage.getItem(key) !== '0'; } catch (e) { return true; }
	}
	function setPref(key, on) {
		try { window.localStorage.setItem(key, on ? '1' : '0'); } catch (e) { /* non mémorisé */ }
	}
	function pointsInput(item) {
		return item ? item.querySelector('input[name^="manual_points"], input[name^="question_manual_points"]') : null;
	}
	function focusInput(el) {
		if (!el) { return; }
		try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
	}
	function make(tag, cls, text) {
		var el = document.createElement(tag);
		if (cls) { el.className = cls; }
		if (text) { el.textContent = text; }
		return el;
	}

	function Pager(form, items) {
		var self = this;
		this.form = form;
		this.items = items;
		this.index = 0;
		this.nav = make('div', 'les-qpager');
		this.prev = make('button', 'button les-button-secondary les-qpager-prev', '← Précédente');
		this.prev.type = 'button';
		this.label = make('strong', 'les-qpager-label');
		this.dots = make('div', 'les-qpager-dots');
		this.next = make('button', 'button les-button-secondary les-qpager-next', 'Suivante →');
		this.next.type = 'button';
		this.all = make('button', 'button-link les-qpager-all', 'Afficher toutes les questions');
		this.all.type = 'button';
		this.ptsLabels = [];
		this.dotButtons = items.map(function (item, k) {
			// 1.36.6 : sous chaque numéro, les points obtenus (vert = tous, orange = une partie, rouge = 0, gris = pas encore notée).
			var cell = make('span', 'les-qpager-cell');
			var b = make('button', 'les-qpager-dot', String(k + 1));
			b.type = 'button';
			b.title = 'Question ' + (k + 1);
			b.addEventListener('click', function () { self.show(k, true); });
			var pts = make('span', 'les-qpager-pts', '–');
			pts.setAttribute('aria-hidden', 'true');
			pts.addEventListener('click', function () { self.show(k, true); });
			cell.appendChild(b);
			cell.appendChild(pts);
			self.dots.appendChild(cell);
			self.ptsLabels.push(pts);
			return b;
		});
		this.nav.appendChild(this.prev);
		this.nav.appendChild(this.label);
		this.nav.appendChild(this.dots);
		this.nav.appendChild(this.next);
		this.nav.appendChild(this.all);
		items[0].parentNode.insertBefore(this.nav, items[0]);
		items.forEach(function (item, k) {
			var bar = make('div', 'les-qnext-bar');
			var last = k === items.length - 1;
			var b = make('button', 'button button-primary les-qnext', last ? 'Terminer : commentaire général et enregistrement ↓' : 'Question suivante →');
			b.type = 'button';
			b.setAttribute('data-les-qnext', '');
			bar.appendChild(b);
			item.appendChild(bar);
			var input = pointsInput(item);
			if (input) { input.addEventListener('input', function () { self.refresh(); }); input.addEventListener('change', function () { self.refresh(); }); }
		});
		this.prev.addEventListener('click', function () { var b = self.step(self.index, -1); if (b !== null) { self.show(b, true); } });
		this.next.addEventListener('click', function () { self.advance(); });
		// Boutons de points, « Points de l'IA », critères cochés, pré-correction : la note de la pastille suit.
		form.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('[data-les-set-points], [data-les-ai-accept], [data-les-ai-accept-all], [data-les-criterion], [data-les-adjust]')) { setTimeout(function () { self.refresh(); }, 30); }
		});
		form.addEventListener('change', function () { setTimeout(function () { self.refresh(); }, 30); });
		// Bouton flottant « Question suivante → » dans la barre du bas (toujours à l'écran) : après une saisie
		// manuelle des points, plus besoin de défiler pour passer à la suite.
		var bar = form.querySelector('.les-sticky-grade-bar');
		this.floatWrap = make('div', 'les-qfloat');
		this.floatPrev = make('button', 'button les-button-secondary les-qfloat-prev', '←');
		this.floatPrev.type = 'button';
		this.floatPrev.title = 'Question précédente';
		this.floatNext = make('button', 'button button-primary les-qfloat-next', 'Question suivante →');
		this.floatNext.type = 'button';
		this.floatNext.setAttribute('data-les-qfloat-next', '');
		this.floatWrap.appendChild(this.floatPrev);
		this.floatWrap.appendChild(this.floatNext);
		if (bar) {
			bar.insertBefore(this.floatWrap, bar.firstChild);
			// Barre du bas sortie de l'écran (fin de page) : le bouton reste flottant en bas à droite.
			if (window.IntersectionObserver) {
				var wrap = this.floatWrap;
				new IntersectionObserver(function (entries) {
					var fixed = !entries[0].isIntersecting;
					wrap.classList.toggle('is-fixed', fixed);
					// 1.36 : dans une page du thème qui « piège » les éléments fixes, le bouton flottant passe sous <body>.
					var F = window.LogiExamFixed;
					if (fixed && F && F.traps(bar)) { F.host().appendChild(wrap); }
					else if (!fixed && wrap.parentNode !== bar) { bar.insertBefore(wrap, bar.firstChild); }
				}).observe(bar);
			}
		} else {
			this.floatWrap.classList.add('is-fixed');
			form.appendChild(this.floatWrap);
			if (window.LogiExamFixed && window.LogiExamFixed.traps(form)) { window.LogiExamFixed.host().appendChild(this.floatWrap); }
		}
		this.floatPrev.addEventListener('click', function () { var b = self.step(self.index, -1); if (b !== null) { self.show(b, true); } });
		this.floatNext.addEventListener('click', function () { self.advance(); });
		this.all.addEventListener('click', function () { setPref(KEY_PAGER, !self.on); self.apply(!self.on); });
		// 1.36.4 : questions restées vides (0 pt et « Non répondu. » d'office) : pastille orange, sautées par « Suivante ».
		this.blank = items.map(function (item) { return item.hasAttribute('data-les-blank-answer'); });
		this.dotButtons.forEach(function (b, k) { if (self.blank[k]) { b.classList.add('is-blank'); b.title = 'Question ' + (k + 1) + ' : non répondue (0 pt)'; } });
		// 1.38.2 : questions déjà corrigées (enregistrées lors d'une correction partielle) : pastille verte ✓.
		// Une question devient « corrigée » quand l'enseignant y change quelque chose (points, boutons, IA, critères,
		// commentaire) ou passe à la suivante avec « Question suivante ». Les questions vides le sont d'office.
		this.reviewed = items.map(function (item, k) { return item.hasAttribute('data-les-reviewed') || self.blank[k]; });
		this.reviewedInput = form.querySelector('input[name="reviewed_questions"]');
		if (!this.reviewedInput) {
			this.reviewedInput = make('input');
			this.reviewedInput.type = 'hidden';
			this.reviewedInput.name = 'reviewed_questions';
			form.appendChild(this.reviewedInput);
		}
		var touch = function (e) {
			var k = self.items.indexOf(e.target.closest ? e.target.closest('.les-answer-grade') : null);
			if (k < 0 || !e.isTrusted) { return; } // seulement les gestes de l'enseignant (pas la pré-correction de toute la classe)
			if (e.type === 'click' && !(e.target.closest && e.target.closest('[data-les-set-points], [data-les-ai-accept], [data-les-ai-grade], [data-les-criterion], input[type="checkbox"]'))) { return; }
			self.markReviewed(k);
		};
		form.addEventListener('input', touch);
		form.addEventListener('change', touch);
		form.addEventListener('click', touch);
		this.syncReviewed();
		// En reprenant une correction : on s'ouvre sur la première question pas encore corrigée.
		var first = null;
		for (var q = 0; q < items.length; q++) { if (!this.reviewed[q] && !this.blank[q]) { first = q; break; } }
		if (first === null) { first = this.step(-1, 1); }
		this.index = first === null ? 0 : first;
		this.apply(pref(KEY_PAGER));
	}
	Pager.prototype.markReviewed = function (k) {
		if (k < 0 || this.reviewed[k]) { return; }
		this.reviewed[k] = true;
		this.syncReviewed();
		this.refresh();
	};
	Pager.prototype.syncReviewed = function () {
		var self = this;
		var ids = [];
		this.items.forEach(function (item, k) {
			var on = !!self.reviewed[k];
			var badge = item.querySelector('.les-reviewed-badge');
			if (badge) { badge.hidden = !on; }
			item.classList.toggle('is-reviewed', on);
			if (on && item.getAttribute('data-question-id')) { ids.push(item.getAttribute('data-question-id')); }
		});
		this.reviewedInput.value = ids.join(',');
	};
	/* Prochaine question répondue dans le sens dir (+1 / -1) à partir de k ; null s'il n'y en a plus. */
	Pager.prototype.step = function (k, dir) {
		for (var n = k + dir; n >= 0 && n < this.items.length; n += dir) {
			if (!this.blank || !this.blank[n]) { return n; }
		}
		return null;
	};
	Pager.prototype.apply = function (on) {
		this.on = on;
		this.form.classList.toggle('les-qpager-on', on);
		this.all.textContent = on ? 'Afficher toutes les questions' : 'Afficher une question à la fois';
		if (on) { this.show(this.index, false); } else { this.items.forEach(function (item) { item.classList.remove('les-q-hidden'); }); this.refresh(); }
	};
	Pager.prototype.show = function (k, scroll) {
		var self = this;
		this.index = Math.max(0, Math.min(this.items.length - 1, k));
		this.items.forEach(function (item, n) { item.classList.toggle('les-q-hidden', self.on && n !== self.index); });
		this.refresh();
		if (scroll) {
			var r = this.nav.getBoundingClientRect();
			if (r.top < 0 || r.top > window.innerHeight * 0.4) { this.nav.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			focusInput(pointsInput(this.items[this.index]));
		}
	};
	Pager.prototype.refresh = function () {
		var self = this;
		var nbDone = (this.reviewed || []).filter(Boolean).length;
		this.label.textContent = (this.on ? 'Question ' + (this.index + 1) + ' / ' + this.items.length : this.items.length + ' questions') + ' · ' + nbDone + ' corrigée' + (nbDone > 1 ? 's' : '');
		var after = this.step(this.index, 1), before = this.step(this.index, -1);
		this.prev.disabled = !this.on || before === null;
		this.next.disabled = !this.on || after === null;
		if (this.floatWrap) {
			this.floatWrap.hidden = !this.on;
			this.floatPrev.disabled = before === null;
			this.floatNext.textContent = after === null ? 'Commentaire général ↓' : 'Question suivante (' + (after + 1) + '/' + this.items.length + ') →';
		}
		this.items.forEach(function (item) {
			var last = item.querySelector('[data-les-qnext]');
			if (last) { last.textContent = self.step(self.items.indexOf(item), 1) === null ? 'Terminer : commentaire général et enregistrement ↓' : 'Question suivante →'; }
		});
		this.dotButtons.forEach(function (b, n) {
			var input = pointsInput(self.items[n]);
			b.classList.toggle('is-done', !!(self.reviewed && self.reviewed[n]));
			b.classList.toggle('is-todo-q', !(self.reviewed && self.reviewed[n]));
			b.classList.toggle('is-current', self.on && n === self.index);
			var lab = self.ptsLabels && self.ptsLabels[n];
			if (!lab) { return; }
			var max = parseFloat(self.items[n].getAttribute('data-les-max-points'));
			var raw = input ? input.value.trim().replace(',', '.') : '';
			var got = raw === '' ? NaN : parseFloat(raw);
			var fmt = function (v) { return (Math.round(v * 100) / 100).toString(); };
			lab.className = 'les-qpager-pts';
			if (isNaN(got)) {
				lab.textContent = '–' + (isNaN(max) ? '' : '/' + fmt(max));
				lab.classList.add('is-todo');
				b.title = 'Question ' + (n + 1) + ' : pas encore notée';
			} else {
				lab.textContent = fmt(got) + (isNaN(max) ? '' : '/' + fmt(max));
				var state = !isNaN(max) && max > 0 && got >= max - 0.001 ? 'is-full' : (got <= 0 ? 'is-zero' : 'is-part');
				lab.classList.add(state);
				b.title = 'Question ' + (n + 1) + ' : ' + lab.textContent + ' pt' + (self.blank && self.blank[n] ? ' (non répondue)' : '');
			}
			if (self.reviewed) {
				b.title += self.reviewed[n] ? ' · corrigée ✓' : ' · à corriger';
			}
		});
	};
	Pager.prototype.advance = function () {
		if (!this.on) { return false; }
		this.markReviewed(this.index); // « Question suivante » : la question quittée est considérée comme corrigée
		var after = this.step(this.index, 1);
		if (after !== null) { this.show(after, true); return true; }
		var end = this.form.querySelector('textarea[name="teacher_comment_global"]');
		if (end) { end.scrollIntoView({ behavior: 'smooth', block: 'center' }); focusInput(end); }
		return true;
	};
	Pager.prototype.indexOf = function (el) {
		var item = el.closest('.les-answer-grade');
		return this.items.indexOf(item);
	};

	var pagers = [];
	function setup() {
		Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
			if (form.__lesPager) { return; }
			var items = Array.prototype.slice.call(form.querySelectorAll('.les-answer-grade'));
			if (items.length < 2) { return; }
			form.__lesPager = new Pager(form, items);
			pagers.push(form.__lesPager);
		});
		var on = pref(KEY_NEXT);
		Array.prototype.forEach.call(document.querySelectorAll('[data-les-auto-next]'), function (box) { box.checked = on; });
	}
	function nextOf(item, selector) {
		var n = item.nextElementSibling;
		while (n && !n.matches(selector)) { n = n.nextElementSibling; }
		return n;
	}
	/* LogiOne 1.31.3 : points différents de la proposition de l'IA alors que le commentaire est encore celui de l'IA
	   (ou vide) → on ne passe pas à la question suivante : on invite à reformuler le commentaire pour l'élève. */
	function num(v) { var x = parseFloat(String(v || '').replace(',', '.')); return isNaN(x) ? null : x; }
	function commentReviewNeeded(scope) {
		if (!scope) { return null; }
		var accept = scope.querySelector('[data-les-ai-accept]');
		var input = pointsInput(scope);
		var area = scope.querySelector('textarea[name^="teacher_comment"], textarea[name^="question_teacher_comment"]');
		if (!accept || accept.hidden || !input || !area) { return null; }
		var ai = num(accept.dataset.points), mine = num(input.value);
		if (ai === null || mine === null || Math.abs(ai - mine) < 0.001) { return null; }
		if (area.dataset.userEdited === '1' || area.dataset.regenFor === String(mine) || area.dataset.keepFor === String(mine)) { return null; }
		var text = area.value.trim();
		var aiText = [accept.dataset.comment, area.dataset.autoComment, area.dataset.lastAi].map(function (t) { return String(t || '').trim(); });
		if (text !== '' && aiText.indexOf(text) === -1) { return null; } // commentaire déjà écrit par l'enseignant
		return { ai: ai, mine: mine, area: area, scope: scope };
	}
	function fmt(n) { return String(Math.round(n * 100) / 100).replace('.', ','); }
	function showCommentReview(info, from) {
		var scope = info.scope;
		var box = info.area.closest('details');
		if (box) { box.open = true; }
		var note = scope.querySelector('.les-comment-review');
		if (!note) {
			note = make('div', 'les-comment-review');
			note.setAttribute('role', 'alert');
			(box || info.area.parentNode).insertBefore(note, (box ? box.querySelector('textarea') : info.area));
		}
		note.innerHTML = '';
		note.appendChild(make('strong', '', '✏️ Vos points (' + fmt(info.mine) + ') ne sont pas ceux proposés par l’IA (' + fmt(info.ai) + ').'));
		note.appendChild(make('span', '', ' Le commentaire pour l’élève ne correspond plus : reformulez-le avant de passer à la suite.'));
		var actions = make('div', 'les-comment-review-actions');
		var regen = scope.querySelector('[data-les-regen-comment]');
		if (regen) {
			var r = make('button', 'button button-primary', '✨ Régénérer avec l’IA d’après mes points');
			r.type = 'button';
			r.addEventListener('click', function () { regen.click(); note.remove(); });
			actions.appendChild(r);
		}
		var e = make('button', 'button les-button-secondary', 'Je l’écris moi-même');
		e.type = 'button';
		e.addEventListener('click', function () { note.remove(); focusInput(info.area); });
		actions.appendChild(e);
		var k = make('button', 'button-link', 'Garder ce commentaire et continuer');
		k.type = 'button';
		k.addEventListener('click', function () { info.area.dataset.keepFor = String(info.mine); note.remove(); goNext(from); });
		actions.appendChild(k);
		note.appendChild(actions);
		note.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}
	/* Après un raccourci de points : commentaire ouvert + invitation à le revoir (pas de passage automatique). */
	function inviteComment(from) {
		var scope = from.closest('.les-answer-grade') || from.closest('tr, .les-qcard');
		if (!scope) { return; }
		var review = commentReviewNeeded(scope);
		if (review) { showCommentReview(review, from); return; }
		var input = pointsInput(scope);
		var area = scope.querySelector('textarea[name^="teacher_comment"], textarea[name^="question_teacher_comment"]');
		if (!area) { return; }
		var box = area.closest('details');
		if (box) { box.open = true; }
		var note = scope.querySelector('.les-comment-review');
		if (!note) {
			note = make('div', 'les-comment-review is-info');
			note.setAttribute('role', 'status');
			(box || area.parentNode).insertBefore(note, area);
		}
		note.innerHTML = '';
		note.appendChild(make('strong', '', '✏️ Points mis : ' + (input ? fmt(num(input.value) || 0) : '') + '.'));
		note.appendChild(make('span', '', area.value.trim() ? ' Relisez le commentaire pour l’élève, puis cliquez sur « Question suivante ».' : ' Écrivez le commentaire pour l’élève, puis cliquez sur « Question suivante ».'));
		var actions = make('div', 'les-comment-review-actions');
		var regen = scope.querySelector('[data-les-regen-comment]');
		if (regen) {
			var r = make('button', 'button button-primary', '✨ Rédiger avec l’IA d’après mes points');
			r.type = 'button';
			r.addEventListener('click', function () { regen.click(); note.remove(); });
			actions.appendChild(r);
		}
		var e = make('button', 'button les-button-secondary', 'Je l’écris moi-même');
		e.type = 'button';
		e.addEventListener('click', function () { note.remove(); focusInput(area); });
		actions.appendChild(e);
		note.appendChild(actions);
		note.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}

	document.addEventListener('input', function (event) {
		var t = event.target;
		if (t && t.matches && t.matches('textarea[name^="teacher_comment"], textarea[name^="question_teacher_comment"]') && event.isTrusted) {
			t.dataset.userEdited = '1';
			var note = (t.closest('.les-answer-grade') || t.closest('tr, .les-qcard') || document).querySelector('.les-comment-review');
			if (note) { note.remove(); }
		}
	}, true);
	document.addEventListener('change', function (event) {
		// Nouveaux points saisis : le commentaire devra peut-être de nouveau être revu.
		var t = event.target;
		if (t && t.matches && t.matches('input[name^="manual_points"], input[name^="question_manual_points"]')) {
			var scope = t.closest('.les-answer-grade') || t.closest('tr, .les-qcard');
			var area = scope ? scope.querySelector('textarea[name^="teacher_comment"], textarea[name^="question_teacher_comment"]') : null;
			if (area && area.dataset.regenFor && area.dataset.regenFor !== String(num(t.value))) { delete area.dataset.regenFor; }
		}
	}, true);

	function goNext(from) {
		var review = commentReviewNeeded(from.closest('.les-answer-grade') || from.closest('tr, .les-qcard'));
		if (review) { showCommentReview(review, from); return; }
		var form = from.closest('form');
		var pager = form ? form.__lesPager : null;
		var item = from.closest('.les-answer-grade');
		if (pager && pager.on && item) {
			pager.index = pager.indexOf(from);
			pager.advance();
			return;
		}
		var next = null;
		if (item) {
			next = nextOf(item, '.les-answer-grade');
			if (!next) { next = form ? form.querySelector('textarea[name="teacher_comment_global"]') : null; }
		} else {
			var row = from.closest('tr, .les-qcard');
			if (row) { next = nextOf(row, row.matches('tr') ? 'tr' : '.les-qcard'); }
			if (!next && row) { var section = row.closest('.les-question-correction-section'); next = section ? nextOf(section, '.les-question-correction-section') : null; }
		}
		if (!next) { return; }
		next.scrollIntoView({ behavior: 'smooth', block: next.matches('tr') || next.matches('textarea') ? 'center' : 'start' });
		focusInput(next.matches('textarea') ? next : pointsInput(next));
		next.classList.add('les-next-focus');
		window.setTimeout(function () { next.classList.remove('les-next-focus'); }, 1400);
	}
	document.addEventListener('change', function (event) {
		var box = event.target.closest ? event.target.closest('[data-les-auto-next]') : null;
		if (!box) { return; }
		setPref(KEY_NEXT, box.checked);
		Array.prototype.forEach.call(document.querySelectorAll('[data-les-auto-next]'), function (b) { b.checked = box.checked; });
	});
	document.addEventListener('click', function (event) {
		if (!event.target.closest) { return; }
		var manual = event.target.closest('[data-les-qnext]');
		if (manual) { goNext(manual); return; }
		if (!pref(KEY_NEXT)) { return; }
		var button = event.target.closest('[data-les-set-points], [data-les-ai-accept]');
		if (!button || !button.closest('.les-answer-grade, .les-question-grade-form')) { return; }
		// « Tous les points / Moitié / Aucun point » : on reste sur la question pour revoir le commentaire (1.31.5).
		if (button.matches('[data-les-set-points]')) { window.setTimeout(function () { inviteComment(button); }, 60); return; }
		window.setTimeout(function () { goNext(button); }, 60);
	});
	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter' || !event.target.matches) { return; }
		if (!event.target.matches('input[name^="manual_points"], input[name^="question_manual_points"]')) { return; }
		event.preventDefault();
		event.target.dispatchEvent(new Event('change', { bubbles: true }));
		goNext(event.target);
	});
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', setup); } else { setup(); }
}());

/* Commentaire par question : déplié dès qu'il contient un texte (LogiOne 1.25.0) ; vide, il reste replié (« + Ajouter un commentaire »). */
(function () {
	'use strict';
	function sync(area) {
		var box = area.closest('details.les-comment-box');
		if (!box) { return; }
		var text = area.value.trim();
		box.classList.toggle('is-filled', text !== '');
		if (text !== '' && !box.open) { box.open = true; } // Commentaire rempli (par l'IA ou l'enseignant) : déplié.
		var preview = box.querySelector('.les-comment-preview');
		if (preview) { preview.textContent = text; }
	}
	['input', 'change'].forEach(function (type) {
		document.addEventListener(type, function (event) {
			var area = event.target;
			if (area && area.matches && area.matches('details.les-comment-box textarea')) { sync(area); }
		});
	});
	document.addEventListener('toggle', function (event) {
		var box = event.target;
		if (!box || !box.matches || !box.matches('details.les-comment-box') || !box.open) { return; }
		var area = box.querySelector('textarea');
		if (area) { try { area.focus({ preventScroll: true }); } catch (e) { area.focus(); } }
	}, true);
}());

/* Suivi en direct : décompte du temps restant de chaque élève (recalé à chaque actualisation du tableau). */
(function () {
	'use strict';
	function fmt(sec) {
		var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
		var mm = String(m).padStart(2, '0'), ss = String(s).padStart(2, '0');
		return h ? h + ':' + mm + ':' + ss : mm + ':' + ss;
	}
	function tick() {
		var now = Date.now();
		Array.prototype.forEach.call(document.querySelectorAll('[data-les-time-left]'), function (el) {
			if (!el.dataset.lesT0) el.dataset.lesT0 = String(now);
			var left = Math.max(0, Math.round(parseInt(el.dataset.lesTimeLeft || '0', 10) - (now - parseInt(el.dataset.lesT0, 10)) / 1000));
			el.textContent = left > 0 ? fmt(left) : 'Temps écoulé';
			el.classList.toggle('is-low', left > 0 ? left <= 300 : false);
			el.classList.toggle('is-over', left <= 0);
		});
	}
	setInterval(tick, 1000);
}());

/* Correction : ajouter la réponse attendue d'une question qui n'en a pas, sans quitter la correction. */
(function () {
	'use strict';
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-les-expected-save]') : null;
		if (!button || !window.LogiEvalSecure) return;
		event.preventDefault();
		var box = button.closest('[data-les-expected-box]');
		var area = box.querySelector('[data-les-expected-text]');
		var status = box.querySelector('.les-expected-status');
		var text = (area.value || '').trim();
		if (!text) { status.textContent = 'Écrivez la réponse attendue.'; area.focus(); return; }
		button.disabled = true;
		var body = new FormData();
		body.append('action', 'logieval_secure_set_expected_answer');
		body.append('nonce', LogiEvalSecure.nonce);
		body.append('question_id', box.dataset.questionId);
		body.append('expected_answer', text);
		fetch(LogiEvalSecure.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); }).then(function (json) {
			if (!json || !json.success) throw new Error((json && json.data && json.data.message) || 'Erreur');
			// Toutes les copies de la page montrent la même question : on met à jour chaque encadré.
			document.querySelectorAll('[data-les-expected-box][data-question-id="' + box.dataset.questionId + '"]').forEach(function (other) {
				other.classList.remove('is-missing');
				other.removeAttribute('data-les-expected-box');
				other.innerHTML = '<strong>Réponse attendue</strong><div>' + json.data.html + '</div>';
			});
		}).catch(function (error) {
			button.disabled = false;
			status.textContent = error.message || 'Erreur';
		});
	});
}());

/* LogiOne 1.27.0 : « Créer avec l'IA » — théorie (texte, PDF, Word, leçons Sensei) → questions proposées par l'IA,
   relues et corrigées par l'enseignant, puis ajoutées à la banque (et à une évaluation brouillon). */
(function () {
	'use strict';
	var root = document.querySelector('[data-les-gen]');
	if (!root || !window.LogiEvalSecure) { return; }
	var TYPE_LABELS = { qcm: 'QCM', true_false: 'Vrai / Faux', true_false_justify: 'Vrai / Faux argumenté', short_answer: 'Réponse courte', long_answer: 'Réponse longue', calculation: 'Calcul', ordering: 'Remettre dans l’ordre', matching: 'Relier', fill_blanks: 'Texte à trous' };
	var CHUNK = 3;
	var state = { sources: [], questions: [], running: false };
	var $ = function (sel) { return root.querySelector(sel); };
	var status = $('[data-les-gen-status]');
	var list = $('[data-les-gen-list]');

	function post(action, data, file) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', LogiEvalSecure.nonce);
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); } else { body.append(k, v); }
		});
		if (file) { body.append('file', file); }
		return fetch(LogiEvalSecure.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) {
			return r.text().then(function (t) {
				var json = null;
				try { json = JSON.parse(t); } catch (e) { /* page d'erreur de l'hébergement */ }
				if (!json) { throw new Error('Le serveur a coupé la demande (délai dépassé). Réessayez : les questions déjà créées sont gardées.'); }
				if (!json.success) { throw new Error((json.data && json.data.message) || 'Erreur'); }
				return json.data;
			});
		});
	}
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function setStatus(text, error) { status.textContent = text || ''; status.classList.toggle('is-error', !!error); }

	/* ---------- Sources ---------- */
	function theory() {
		var parts = [];
		var pasted = ($('[data-les-gen-text]').value || '').trim();
		if (pasted) { parts.push(pasted); }
		state.sources.forEach(function (s) { parts.push('— ' + s.title + ' —\n' + s.text); });
		return parts.join('\n\n');
	}
	function renderSources() {
		var ul = $('[data-les-gen-sourcelist]');
		ul.innerHTML = state.sources.map(function (s, i) {
			return '<li><span>📄 ' + esc(s.title) + ' <small>(' + s.text.length.toLocaleString('fr-CH') + ' caractères)</small></span> <button type="button" class="button-link" data-les-gen-remove-source="' + i + '">Retirer</button></li>';
		}).join('');
		var n = theory().length;
		$('[data-les-gen-size]').textContent = n ? 'Théorie : ' + n.toLocaleString('fr-CH') + ' caractères (environ ' + Math.max(1, Math.round(n / 3000)) + ' page(s)).' + (n > 60000 ? ' Au-delà de 60 000 caractères, la fin n’est pas lue : choisissez les parties utiles.' : '') : '';
	}
	function addSource(title, text) {
		text = (text || '').trim();
		if (!text) { setStatus('Aucun texte trouvé dans « ' + title + ' ».', true); return; }
		state.sources.push({ title: title, text: text });
		renderSources();
		setStatus('« ' + title + ' » ajouté.', false);
	}
	function loadPdfJs() {
		if (window.pdfjsLib) { return Promise.resolve(window.pdfjsLib); }
		return new Promise(function (resolve, reject) {
			var s = document.createElement('script');
			s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
			s.onload = function () {
				window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
				resolve(window.pdfjsLib);
			};
			s.onerror = function () { reject(new Error('Le lecteur PDF n’a pas pu être chargé. Copiez le texte du PDF dans la case « Coller la théorie ».')); };
			document.head.appendChild(s);
		});
	}
	function readPdf(file) {
		return loadPdfJs().then(function (pdfjs) {
			return file.arrayBuffer().then(function (buf) { return pdfjs.getDocument({ data: buf }).promise; });
		}).then(function (pdf) {
			var pages = [];
			var chain = Promise.resolve();
			for (var i = 1; i <= pdf.numPages; i++) {
				(function (n) {
					chain = chain.then(function () { return pdf.getPage(n); }).then(function (page) { return page.getTextContent(); }).then(function (tc) {
						var line = '', out = [], lastY = null;
						tc.items.forEach(function (it) {
							var y = it.transform ? Math.round(it.transform[5]) : null;
							if (lastY !== null && y !== lastY) { out.push(line); line = ''; }
							line += it.str + (it.hasEOL ? '\n' : '');
							lastY = y;
						});
						out.push(line);
						pages.push(out.join('\n'));
					});
				}(i));
			}
			return chain.then(function () { return pages.join('\n\n'); });
		});
	}
	$('[data-les-gen-file]').addEventListener('change', function (event) {
		Array.prototype.forEach.call(event.target.files || [], function (file) {
			var ext = (file.name.split('.').pop() || '').toLowerCase();
			setStatus('Lecture de « ' + file.name + ' »…', false);
			var job;
			if (ext === 'pdf') { job = readPdf(file); }
			else if (ext === 'txt' || ext === 'md') { job = file.text(); }
			else { job = post('logieval_secure_gen_source', {}, file).then(function (d) { return d.text; }); }
			job.then(function (text) { addSource(file.name, text); }).catch(function (e) { setStatus(e.message, true); });
		});
		event.target.value = '';
	});
	$('[data-les-gen-course]').addEventListener('change', function (event) {
		var box = $('[data-les-gen-lessons]');
		var id = event.target.value;
		box.hidden = !id;
		if (!id) { return; }
		var target = $('[data-les-gen-target-course]');
		if (target && target.value === '0') { target.value = id; }
		box.innerHTML = '<em>Chargement des leçons…</em>';
		post('logieval_secure_gen_lessons', { course_id: id }).then(function (d) {
			if (!d.lessons.length) { box.innerHTML = '<em>Aucune leçon dans ce cours.</em>'; return; }
			box.innerHTML = d.lessons.map(function (l) {
				var isObj = /objectif/i.test(l.title);
				return '<div class="les-gen-lesson-row' + (isObj ? ' is-objectives' : '') + '"><label class="les-check"><input type="checkbox" value="' + l.id + '" data-les-gen-lesson ' + (l.chars > 0 ? '' : 'disabled') + '> ' + esc(l.title) + ' <small>' + (l.chars > 0 ? '(' + l.chars.toLocaleString('fr-CH') + ' car.)' : '(pas de texte)') + '</small></label>' +
					(l.chars > 0 ? '<button type="button" class="button-link les-gen-obj-btn" data-les-gen-obj-lesson="' + l.id + '" data-title="' + esc(l.title) + '" title="Utiliser cette leçon comme liste des objectifs">🎯 Objectifs</button>' : '') + '</div>';
			}).join('') + '<button type="button" class="button" data-les-gen-add-lessons>Ajouter les leçons cochées (théorie)</button>';
			// Une seule leçon « Objectifs » dans le cours et rien de saisi : on la reprend tout de suite.
			var objLessons = d.lessons.filter(function (l) { return l.chars > 0 && /objectif/i.test(l.title); });
			if (objLessons.length === 1 && !objectivesText()) { loadObjectivesLesson(objLessons[0].id, objLessons[0].title); }
		}).catch(function (e) { box.innerHTML = ''; setStatus(e.message, true); });
	});
	root.addEventListener('click', function (event) {
		var t = event.target;
		if (t.closest('[data-les-gen-add-lessons]')) {
			var ids = Array.prototype.map.call(root.querySelectorAll('[data-les-gen-lesson]:checked'), function (c) { return c.value; });
			if (!ids.length) { setStatus('Cochez au moins une leçon.', true); return; }
			post('logieval_secure_gen_source', { lesson_ids: ids }).then(function (d) { addSource(d.title, d.text); }).catch(function (e) { setStatus(e.message, true); });
		}
		var objBtn = t.closest('[data-les-gen-obj-lesson]');
		if (objBtn) { loadObjectivesLesson(objBtn.getAttribute('data-les-gen-obj-lesson'), objBtn.getAttribute('data-title') || ''); return; }
		var rm = t.closest('[data-les-gen-remove-source]');
		if (rm) { state.sources.splice(+rm.getAttribute('data-les-gen-remove-source'), 1); renderSources(); }
	});
	$('[data-les-gen-text]').addEventListener('input', renderSources);

	/* ---------- 1.33 : objectifs à atteindre ---------- */
	var objArea = $('[data-les-gen-objectives]');
	function objectivesText() { return objArea ? (objArea.value || '').trim() : ''; }
	function objectiveLines() {
		return objectivesText().split(/\n+/).map(function (s) { return s.replace(/^[\s•\-–*·]+/, '').trim(); }).filter(function (s) {
			return s.length >= 12 && !/^(objectifs?( évaluateurs?| opérationnels?| de la leçon| du module)?\s*:?)$/i.test(s);
		});
	}
	function objInfo(msg) {
		var info = $('[data-les-gen-obj-info]');
		var n = objectiveLines().length;
		if (info) { info.textContent = (msg ? msg + ' ' : '') + (n ? n + ' objectif(s) repéré(s) : les questions seront réparties sur ces objectifs.' : ''); }
	}
	function addObjectives(title, text) {
		text = (text || '').trim();
		if (!text) { setStatus('Aucun texte trouvé dans « ' + title + ' ».', true); return; }
		objArea.value = (objectivesText() ? objectivesText() + '\n\n' : '') + text;
		objInfo('Objectifs repris de « ' + title + ' ».');
		setStatus('🎯 Objectifs ajoutés depuis « ' + title + ' ».', false);
	}
	function loadObjectivesLesson(id, title) {
		post('logieval_secure_gen_source', { lesson_ids: [id] }).then(function (d) {
			// Le texte commence par le titre de la leçon : on l'enlève.
			var text = (d.text || '').replace(new RegExp('^' + title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*\\n'), '');
			addObjectives(title, text);
		}).catch(function (e) { setStatus(e.message, true); });
	}
	if (objArea) {
		objArea.addEventListener('input', function () { objInfo(''); });
		var objFile = $('[data-les-gen-obj-file]');
		if (objFile) {
			objFile.addEventListener('change', function (event) {
				var file = (event.target.files || [])[0];
				if (!file) { return; }
				var ext = (file.name.split('.').pop() || '').toLowerCase();
				setStatus('Lecture de « ' + file.name + ' »…', false);
				var job = ext === 'pdf' ? readPdf(file) : (ext === 'txt' || ext === 'md') ? file.text() : post('logieval_secure_gen_source', {}, file).then(function (d) { return d.text; });
				job.then(function (text) { addObjectives(file.name, text); }).catch(function (e) { setStatus(e.message, true); });
				event.target.value = '';
			});
		}
	}
	// Objectifs visés par chaque lot : répartis à tour de rôle sur l'ensemble des objectifs.
	var objCursor = 0;
	function focusFor(n) {
		var lines = objectiveLines();
		if (!lines.length) { return []; }
		var out = [];
		for (var i = 0; i < n; i++) { out.push(lines[(objCursor + i) % lines.length].slice(0, 220)); }
		objCursor = (objCursor + n) % lines.length;
		return out;
	}

	/* ---------- Génération ---------- */
	function plan(count, types) {
		var out = [];
		for (var i = 0; i < count; i++) { out.push(types[i % types.length]); }
		var chunks = [];
		for (var j = 0; j < out.length; j += CHUNK) { chunks.push(out.slice(j, j + CHUNK)); }
		return chunks;
	}
	$('[data-les-gen-run]').addEventListener('click', function () {
		if (state.running) { return; }
		var text = theory();
		if (text.length < 80) { setStatus('Ajoutez d’abord la théorie (au moins quelques phrases).', true); return; }
		var types = Array.prototype.map.call(root.querySelectorAll('[data-les-gen-type]:checked'), function (c) { return c.value; });
		if (!types.length) { setStatus('Choisissez au moins un type de question.', true); return; }
		var count = Math.max(1, Math.min(40, parseInt($('[data-les-gen-count]').value, 10) || 10));
		var chunks = plan(count, types);
		var button = this;
		var done = 0;
		var failed = 0;
		var lastError = '';
		state.running = true;
		button.disabled = true;
		// Un lot qui échoue est réessayé une fois, puis on passe au suivant (avant : tout s'arrêtait au premier échec).
		var step = function (k, retry) {
			if (k >= chunks.length) { finish(failed ? lastError : ''); return; }
			setStatus('L’IA rédige les questions… ' + done + ' / ' + count + ' (cela prend quelques secondes par question)', false);
			post('logieval_secure_gen_questions', {
				theory: text,
				types: chunks[k],
				level: $('[data-les-gen-level]').value,
				address: $('[data-les-gen-address]').value,
				notes: $('[data-les-gen-notes]').value,
				objectives: objectivesText(),
				focus: retry ? (chunks[k].focus || []) : (chunks[k].focus = focusFor(chunks[k].length)),
				provider: ($('[data-les-ai-provider-choice]') || {}).value || '',
				avoid: state.questions.map(function (q) { return q.prompt.slice(0, 160); })
			}).then(function (d) {
				d.questions.forEach(function (q) { q.keep = true; state.questions.push(q); });
				done += d.questions.length;
				render();
				step(k + 1);
			}).catch(function (e) {
				lastError = e.message;
				if (!retry) { setStatus('Petit souci avec l’IA, nouvel essai…', false); step(k, true); return; }
				failed += chunks[k].length;
				step(k + 1);
			});
		};
		var finish = function (error) {
			state.running = false;
			button.disabled = false;
			button.textContent = state.questions.length ? 'Générer d’autres questions' : 'Générer les questions';
			if (error) { setStatus(done + ' question(s) créée(s) sur ' + count + '. ' + failed + ' n’ont pas pu être créées (' + error + ') Cliquez sur « Générer d’autres questions » pour compléter.', true); }
			else { setStatus(done + ' question(s) créée(s). Relisez-les ci-dessous.', false); }
		};
		step(0);
	});

	/* ---------- Relecture ---------- */
	function pts(v) { return Math.round((parseFloat(String(v).replace(',', '.')) || 0) * 2) / 2; }
	function questionPoints(q) {
		var sum = (q.criteria || []).reduce(function (a, c) { return a + pts(c.points); }, 0);
		return sum > 0 ? sum : pts(q.points);
	}
	function field(label, html, cls) { return '<label class="les-gen-field ' + (cls || '') + '">' + label + html + '</label>'; }
	function scoringSelect(k, v, partialLabel) {
		return '<select data-k="' + k + '"><option value="partial"' + (v !== 'all' ? ' selected' : '') + '>' + partialLabel + '</option><option value="all"' + (v === 'all' ? ' selected' : '') + '>Tout juste ou rien</option></select>';
	}
	function area(k, v, rows) { return '<textarea rows="' + (rows || 2) + '" data-k="' + k + '">' + esc(v) + '</textarea>'; }
	function card(q, i) {
		var body = '';
		body += field('Mise en situation (facultatif)', area('context', q.context, 2));
		body += field('Question', area('prompt', q.prompt, 2));
		if (q.type === 'qcm') {
			body += '<div class="les-gen-field">Propositions (cochez la bonne)' + (q.options || []).map(function (o, n) {
				return '<div class="les-gen-option"><input type="radio" name="gen-correct-' + i + '" data-k="correct_index" value="' + n + '" ' + (n === q.correct_index ? 'checked' : '') + '><input type="text" data-k="option" data-n="' + n + '" value="' + esc(o) + '"></div>';
			}).join('') + '</div>';
		} else if (q.type === 'true_false' || q.type === 'true_false_justify') {
			body += field('Bonne réponse', '<select data-k="true_false"><option ' + (q.true_false === 'Vrai' ? 'selected' : '') + '>Vrai</option><option ' + (q.true_false === 'Faux' ? 'selected' : '') + '>Faux</option></select>', 'is-small');
			if (q.type === 'true_false_justify') {
				body += field('Justification attendue', area('justification', q.justification, 2));
				body += field('Points du choix Vrai/Faux', '<input type="number" step="0.5" min="0" data-k="choice_points" value="' + esc(q.choice_points) + '">', 'is-small');
			}
		} else if (q.type === 'calculation') {
			var c = q.calculation || {};
			body += field('Développement attendu', '<textarea rows="3" data-k="calc_development">' + esc(c.development) + '</textarea>');
			body += '<div class="les-gen-row">' + field('Résultat final', '<input type="text" data-k="calc_result" value="' + esc(c.result) + '">') +
				field('Précision', '<select data-k="calc_precision">' + ['exact', '0', '1', '2', '3', '4'].map(function (p) { return '<option value="' + p + '" ' + (c.precision === p ? 'selected' : '') + '>' + (p === 'exact' ? 'Valeur exacte' : p + ' décimale(s)') + '</option>'; }).join('') + '</select>', 'is-small') + '</div>';
		} else if (q.type === 'ordering') {
			body += field('Étapes dans le BON ordre (une par ligne ; les élèves les reçoivent mélangées)', '<textarea rows="5" data-k="items">' + esc((q.items || []).join('\n')) + '</textarea>');
			body += field('Points', scoringSelect('order_scoring', q.order_scoring, 'Au prorata des étapes bien placées'), 'is-small');
		} else if (q.type === 'matching') {
			// 1.38.4 : même présentation que l'éditeur de questions (une ligne par paire, intrus, mode de points).
			body += '<div class="les-gen-field">Paires justes : à gauche, et à droite l’élément qui va avec (la colonne de droite est mélangée pour les élèves)' +
				'<div class="les-gen-pairs">' + (q.pairs || []).map(function (p, n) {
					var decoy = !!p.decoy;
					return '<div class="les-gen-pair' + (decoy ? ' is-decoy' : '') + '">' +
						(decoy ? '<span class="les-gen-decoy">Intrus</span>' : '<input type="text" data-k="pair_left" data-n="' + n + '" value="' + esc(p.left) + '" aria-label="À gauche">') +
						'<span class="les-gen-arrow" aria-hidden="true">↔</span><input type="text" data-k="pair_right" data-n="' + n + '" value="' + esc(p.right) + '" aria-label="À droite">' +
						'<button type="button" class="button-link" data-gen-pair-remove="' + n + '" aria-label="Retirer cette ligne">✕</button></div>';
				}).join('') + '</div><p><button type="button" class="button-link" data-gen-pair-add>+ Ajouter une paire</button> · <button type="button" class="button-link" data-gen-decoy-add>+ Ajouter un intrus</button></p></div>';
			body += field('Points', scoringSelect('match_scoring', q.match_scoring, 'Au prorata des liaisons justes'), 'is-small');
		} else if (q.type === 'fill_blanks') {
			body += field('Texte à compléter (mots à trouver entre crochets, variantes séparées par |)', '<textarea rows="4" data-k="blanks_text">' + esc(q.blanks_text) + '</textarea>');
			var nb = ((q.blanks_text || '').match(/\[[^\]]+\]/g) || []).length;
			body += '<p class="les-gen-source" data-gen-blanks-count>' + nb + ' trou(s) à compléter.</p>';
			body += '<div class="les-gen-row">' +
				'<label class="les-check"><input type="checkbox" data-k="blanks_bank" ' + (q.blanks_bank !== false ? 'checked' : '') + '> Montrer la liste des mots à placer (mélangés)</label>' +
				'<label class="les-check"><input type="checkbox" data-k="blanks_tolerant" ' + (q.blanks_tolerant !== false ? 'checked' : '') + '> Accepter une petite faute de frappe</label></div>';
			body += field('Points', scoringSelect('blanks_scoring', q.blanks_scoring, 'Au prorata des trous justes'), 'is-small');
		} else {
			body += field('Réponse attendue', area('expected_answer', q.expected_answer, q.type === 'long_answer' ? 4 : 2));
		}
		body += '<div class="les-gen-field">Critères de correction (barème)' + (q.criteria || []).map(function (cr, n) {
			return '<div class="les-gen-crit"><input type="text" data-k="crit_label" data-n="' + n + '" value="' + esc(cr.label) + '"><input type="number" step="0.5" min="0" data-k="crit_points" data-n="' + n + '" value="' + esc(cr.points) + '"> pt <button type="button" class="button-link" data-gen-crit-remove="' + n + '" aria-label="Retirer ce critère">✕</button></div>';
		}).join('') + '<button type="button" class="button-link" data-gen-crit-add>+ Ajouter un critère</button></div>';
		if (!(q.criteria || []).length) {
			body += field('Points', '<input type="number" step="0.5" min="0.5" data-k="points" value="' + esc(q.points) + '">', 'is-small');
		}
		if (q.type !== 'calculation') {
			body += '<label class="les-check les-gen-calc"><input type="checkbox" data-k="calculator" ' + (q.calculator ? 'checked' : '') + '> 🧮 Afficher la calculatrice à l’élève pour cette question</label>';
		} else {
			body += '<p class="les-gen-source">🧮 Calculatrice affichée à l’élève (question de calcul).</p>';
		}
		if (q.objective) { body += '<p class="les-gen-source les-gen-objective">🎯 Objectif : ' + esc(q.objective) + '</p>'; }
		if (q.source) { body += '<p class="les-gen-source">📖 Source : ' + esc(q.source) + '</p>'; }
		return '<article class="les-gen-card' + (q.keep ? '' : ' is-off') + '" data-gen-index="' + i + '">' +
			'<header><label class="les-check"><input type="checkbox" data-k="keep" ' + (q.keep ? 'checked' : '') + '> <strong>Question ' + (i + 1) + '</strong></label>' +
			'<span class="les-gen-type">' + esc(TYPE_LABELS[q.type] || q.type) + '</span><span class="les-gen-pts" data-gen-pts>' + questionPoints(q) + ' pt</span>' +
			'<button type="button" class="button-link les-gen-delete" data-gen-delete>Supprimer</button></header>' +
			'<div class="les-gen-body">' + body + '</div></article>';
	}
	/* LogiOne 1.32 : « ⚖️ Comparer deux IA » — un lot de questions créé par deux IA, côte à côte ; on ajoute celles qu'on préfère. */
	var cmpBtn = $('[data-les-gen-compare]');
	var cmpPanel = $('[data-les-gen-compare-panel]');
	var cmpResult = {};
	function cmpQuestion(q) {
		var extra = '';
		if (q.type === 'qcm' && q.options) { extra = '<ol type="A">' + q.options.map(function (o, k) { return '<li' + (k === q.correct_index ? ' class="is-right"' : '') + '>' + esc(o) + '</li>'; }).join('') + '</ol>'; }
		return '<div class="les-cmp-q"><span class="les-cmp-type">' + esc(q.type) + ' · ' + esc(q.points) + ' pt</span><p><strong>' + esc(q.prompt) + '</strong></p>' + extra + '<p class="description">Attendu : ' + esc(q.expected_answer || '') + '</p></div>';
	}
	if (cmpBtn && cmpPanel) {
		cmpBtn.addEventListener('click', function () {
			var sel = $('[data-les-ai-provider-choice]');
			var opts = sel ? Array.prototype.map.call(sel.options, function (o) { return { value: o.value, label: o.textContent }; }) : [];
			var a = sel ? sel.value : (opts[0] || {}).value;
			var b = (opts.filter(function (o) { return o.value !== a; })[0] || {}).value;
			var mk = function (name, attr, v) { return '<label>' + name + ' <select ' + attr + '>' + opts.map(function (o) { return '<option value="' + esc(o.value) + '"' + (o.value === v ? ' selected' : '') + '>' + esc(o.label) + '</option>'; }).join('') + '</select></label>'; };
			cmpPanel.innerHTML = '<div class="les-cmp-head"><strong>⚖️ Comparer deux IA</strong> ' + mk('IA A', 'data-les-gcmp-a', a) + mk('IA B', 'data-les-gcmp-b', b) +
				'<button type="button" class="button button-primary" data-les-gcmp-run>Créer un lot avec chacune</button> <button type="button" class="button-link" data-les-gcmp-close>Fermer</button> <span class="les-gen-status" data-les-gcmp-status></span></div>' +
				'<div class="les-cmp-cols" data-les-gcmp-cols></div><p class="description">Chaque IA crée les mêmes types de questions (un lot, 3 au maximum) à partir de la même théorie et des mêmes consignes. Ajoutez ensuite les questions de l’une, de l’autre ou des deux.</p>';
			cmpPanel.hidden = false;
		});
		cmpPanel.addEventListener('click', function (e) {
			if (e.target.closest('[data-les-gcmp-close]')) { cmpPanel.hidden = true; return; }
			var st = cmpPanel.querySelector('[data-les-gcmp-status]');
			var run = e.target.closest('[data-les-gcmp-run]');
			if (run) {
				var text = theory();
				if (text.length < 80) { st.textContent = 'Ajoutez d’abord la théorie (au moins quelques phrases).'; st.classList.add('is-error'); return; }
				var types = Array.prototype.map.call(root.querySelectorAll('[data-les-gen-type]:checked'), function (c) { return c.value; }).slice(0, CHUNK);
				if (!types.length) { st.textContent = 'Choisissez au moins un type de question.'; st.classList.add('is-error'); return; }
				var a = cmpPanel.querySelector('[data-les-gcmp-a]').value, b = cmpPanel.querySelector('[data-les-gcmp-b]').value;
				if (a === b) { st.textContent = 'Choisissez deux IA différentes.'; st.classList.add('is-error'); return; }
				run.disabled = true; st.classList.remove('is-error'); st.textContent = 'Les deux IA rédigent les questions…';
				var base = { theory: text, types: types, level: $('[data-les-gen-level]').value, address: $('[data-les-gen-address]').value, notes: $('[data-les-gen-notes]').value, objectives: objectivesText(), focus: focusFor(Math.min(3, types.length)), avoid: state.questions.map(function (q) { return q.prompt.slice(0, 160); }) };
				var one = function (p) { var t0 = Date.now(); return post('logieval_secure_gen_questions', Object.assign({}, base, { provider: p })).then(function (d) { return { provider: p, questions: d.questions || [], model: d.model || p, seconds: Math.round((Date.now() - t0) / 100) / 10 }; }, function (err) { return { provider: p, error: err.message, questions: [] }; }); };
				Promise.all([one(a), one(b)]).then(function (res) {
					cmpResult = { a: res[0], b: res[1] };
					cmpPanel.querySelector('[data-les-gcmp-cols]').innerHTML = ['a', 'b'].map(function (k) {
						var r = cmpResult[k];
						return '<div class="les-cmp-col"><h4>' + k.toUpperCase() + ' · ' + esc(r.model || r.provider) + (r.seconds ? ' · ' + r.seconds + ' s' : '') + '</h4>' +
							(r.error ? '<p class="les-gen-status is-error">' + esc(r.error) + '</p>' : r.questions.map(cmpQuestion).join('') +
							'<button type="button" class="button button-primary" data-les-gcmp-keep="' + k + '">Ajouter les questions ' + k.toUpperCase() + '</button>') + '</div>';
					}).join('');
					st.textContent = 'Comparez, puis ajoutez les questions que vous préférez (vous pouvez ajouter les deux).';
				}).finally(function () { run.disabled = false; });
				return;
			}
			var keep = e.target.closest('[data-les-gcmp-keep]');
			if (keep) {
				var k = keep.dataset.lesGcmpKeep, r = cmpResult[k];
				if (!r || !r.questions.length) { return; }
				r.questions.forEach(function (q) { q.keep = true; state.questions.push(q); });
				render();
				keep.disabled = true; keep.textContent = '✓ Ajoutées';
				var other = cmpResult[k === 'a' ? 'b' : 'a'];
				post('logieval_secure_ai_compare_vote', { kept: r.provider, other: other ? other.provider : '' }).catch(function () {});
				setStatus(r.questions.length + ' question(s) de l’IA ' + k.toUpperCase() + ' ajoutée(s). Relisez-les ci-dessous.', false);
			}
		});
	}

	function render() {
		$('[data-les-gen-review]').hidden = !state.questions.length;
		list.innerHTML = state.questions.map(card).join('');
		total();
	}
	function total() {
		var kept = state.questions.filter(function (q) { return q.keep; });
		var sum = kept.reduce(function (a, q) { return a + questionPoints(q); }, 0);
		$('[data-les-gen-total]').textContent = kept.length + ' question(s) gardée(s) · total ' + sum + ' point(s)';
	}
	list.addEventListener('input', update);
	list.addEventListener('change', update);
	function update(event) {
		var el = event.target;
		var k = el.getAttribute('data-k');
		var cardEl = el.closest('[data-gen-index]');
		if (!k || !cardEl) { return; }
		var q = state.questions[+cardEl.getAttribute('data-gen-index')];
		var n = +el.getAttribute('data-n');
		switch (k) {
			case 'keep': q.keep = el.checked; cardEl.classList.toggle('is-off', !el.checked); break;
			case 'option': q.options[n] = el.value; break;
			case 'correct_index': q.correct_index = +el.value; break;
			case 'calc_development': q.calculation.development = el.value; break;
			case 'calc_result': q.calculation.result = el.value; break;
			case 'calc_precision': q.calculation.precision = el.value; break;
			case 'items': q.items = el.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean); break;
			case 'pair_left': q.pairs[n].left = el.value; break;
			case 'pair_right': q.pairs[n].right = el.value; break;
			case 'calculator': q.calculator = el.checked; break;
			case 'blanks_bank': q.blanks_bank = el.checked; break;
			case 'blanks_tolerant': q.blanks_tolerant = el.checked; break;
			case 'blanks_text':
				q.blanks_text = el.value;
				var bc = cardEl.querySelector('[data-gen-blanks-count]');
				if (bc) { bc.textContent = ((el.value || '').match(/\[[^\]]+\]/g) || []).length + ' trou(s) à compléter.'; }
				break;
			case 'crit_label': q.criteria[n].label = el.value; break;
			case 'crit_points': q.criteria[n].points = pts(el.value); break;
			default: q[k] = el.value;
		}
		var badge = cardEl.querySelector('[data-gen-pts]');
		if (badge) { badge.textContent = questionPoints(q) + ' pt'; }
		total();
	}
	list.addEventListener('click', function (event) {
		var cardEl = event.target.closest('[data-gen-index]');
		if (!cardEl) { return; }
		var i = +cardEl.getAttribute('data-gen-index');
		var q = state.questions[i];
		if (event.target.closest('[data-gen-delete]')) { state.questions.splice(i, 1); render(); }
		else if (event.target.closest('[data-gen-crit-add]')) { q.criteria = q.criteria || []; q.criteria.push({ label: '', points: 1 }); render(); }
		else if (event.target.closest('[data-gen-crit-remove]')) { q.criteria.splice(+event.target.closest('[data-gen-crit-remove]').getAttribute('data-gen-crit-remove'), 1); render(); }
		else if (event.target.closest('[data-gen-pair-add]')) { q.pairs = q.pairs || []; var firstDecoy = q.pairs.findIndex(function (p) { return p.decoy; }); q.pairs.splice(firstDecoy < 0 ? q.pairs.length : firstDecoy, 0, { left: '', right: '' }); render(); }
		else if (event.target.closest('[data-gen-decoy-add]')) { q.pairs = q.pairs || []; q.pairs.push({ left: '', right: '', decoy: true }); render(); }
		else if (event.target.closest('[data-gen-pair-remove]')) { q.pairs.splice(+event.target.closest('[data-gen-pair-remove]').getAttribute('data-gen-pair-remove'), 1); render(); }
	});
	$('[data-les-gen-form]').addEventListener('submit', function (event) {
		var kept = state.questions.filter(function (q) { return q.keep; }).map(function (q) {
			var copy = JSON.parse(JSON.stringify(q));
			copy.points = questionPoints(q);
			delete copy.keep;
			return copy;
		});
		if (!kept.length) { event.preventDefault(); setStatus('Gardez au moins une question.', true); return; }
		var submitter = event.submitter;
		if (submitter && submitter.value === 'evaluation' && !($('[data-les-gen-title]').value || '').trim()) {
			$('[data-les-gen-title]').value = 'Évaluation – ' + new Date().toLocaleDateString('fr-CH');
		}
		$('[data-les-gen-payload]').value = JSON.stringify(kept);
		$('[data-les-gen-payload-course]').value = $('[data-les-gen-target-course]').value || '0';
	});
}());

/* Choix de l'IA mémorisé par usage (corriger / créer des questions) dans ce navigateur. */
(function () {
	'use strict';
	Array.prototype.forEach.call(document.querySelectorAll('[data-les-ai-provider-choice]'), function (select) {
		var key = 'les_ai_provider_' + select.getAttribute('data-les-ai-provider-choice');
		try {
			var saved = window.localStorage.getItem(key);
			if (saved && select.querySelector('option[value="' + saved + '"]')) { select.value = saved; }
		} catch (e) { /* non mémorisé */ }
		select.addEventListener('change', function () {
			try { window.localStorage.setItem(key, select.value); } catch (e) { /* non mémorisé */ }
			// Tous les menus du même usage suivent (panneau de pré-correction et chaque question).
			Array.prototype.forEach.call(document.querySelectorAll('[data-les-ai-provider-choice="' + select.getAttribute('data-les-ai-provider-choice') + '"]'), function (other) {
				if (other !== select && other.querySelector('option[value="' + select.value + '"]')) { other.value = select.value; }
			});
		});
	});
}());

/* LogiOne 1.32.1 : « 📁 Choisir dans la médiathèque » pour toutes les images des questions (choisir une image déjà sur le site ou en téléverser une). */
(function () {
	'use strict';
	function ready() {
		const hasMedia = !!(window.wp && window.wp.media);
		document.querySelectorAll('[data-les-media-pick]').forEach((b) => { if (!hasMedia) { b.hidden = true; } });
		if (!hasMedia) { return; }
		let frame = null;
		let target = null;
		document.addEventListener('click', (event) => {
			const button = event.target.closest('[data-les-media-pick]');
			if (!button) { return; }
			event.preventDefault();
			target = button.closest('[data-les-media-field], .les-order-row');
			if (!target) { return; }
			if (!frame) {
				frame = window.wp.media({
					title: 'Choisir une image',
					library: { type: 'image' },
					multiple: false,
					button: { text: 'Utiliser cette image' }
				});
				frame.on('select', () => {
					const att = frame.state().get('selection').first();
					if (!att || !target) { return; }
					const data = att.toJSON();
					const input = target.querySelector('[data-les-media-input], [data-les-order-media]');
					if (input) { input.value = String(data.id); input.dispatchEvent(new Event('change', { bubbles: true })); }
					target.querySelectorAll('input[type="file"]').forEach((f) => { f.value = ''; });
					target.querySelectorAll('input[type="checkbox"][name$="remove_image]"], input[type="checkbox"][name="remove_image"]').forEach((c) => { c.checked = false; });
					const sizes = data.sizes || {};
					const url = (sizes.medium || sizes.thumbnail || sizes.full || {}).url || data.url;
					const picked = target.querySelector('[data-les-media-picked]');
					target.querySelectorAll('img').forEach((img) => { if (!picked || !picked.contains(img)) { img.closest('.les-question-image-current, .les-company-logo-preview') ? img.closest('.les-question-image-current, .les-company-logo-preview').hidden = true : (img.hidden = true); } });
					if (picked) {
						picked.innerHTML = '';
						const img = document.createElement('img');
						img.src = url; img.alt = data.alt || '';
						const name = document.createElement('span');
						name.textContent = '✓ ' + (data.filename || data.title || 'Image choisie');
						picked.appendChild(img); picked.appendChild(name);
						picked.hidden = false;
					}
				});
			}
			frame.open();
			try { frame.content.mode('browse'); } catch (e) { /* onglet par défaut */ }
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); } else { ready(); }
}());

/* 1.32.1 : pas de menu « Ouvrir / enregistrer l'image » ni de glisser-déposer sur les images des questions (nom de fichier caché). */
(function () {
	'use strict';
	const SEL = '.les-question-image img, .les-order-item img, .les-company-logo img, [data-zoom-overlay] img, .les-zoom-overlay img';
	document.addEventListener('contextmenu', (e) => { if (e.target.closest && e.target.closest(SEL)) { e.preventDefault(); } });
	document.addEventListener('dragstart', (e) => { if (e.target.closest && e.target.closest(SEL)) { e.preventDefault(); } });
}());

/* LogiOne 1.35.1 : réglages LogiExam en sous-menus (IA : connexion, correction, création ; sécurité ; site).
   Tous les champs restent dans la page (cachés) : enregistrer une partie ne perd rien des autres. */
(function () {
	'use strict';
	function init() {
		const nav = document.querySelector('[data-les-settings-tabs]');
		if (!nav) { return; }
		const tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-les-settings-tab]'));
		const panes = Array.prototype.slice.call(document.querySelectorAll('[data-les-settings-pane]'));
		const sections = Array.prototype.slice.call(document.querySelectorAll('[data-les-settings-section]'));
		if (!tabs.length || !panes.length) { return; }
		const keys = tabs.map((t) => t.getAttribute('data-les-settings-tab'));
		function show(key, push) {
			if (keys.indexOf(key) === -1) { key = keys[0]; }
			tabs.forEach((t) => {
				const on = t.getAttribute('data-les-settings-tab') === key;
				t.classList.toggle('is-active', on);
				if (on) { t.setAttribute('aria-current', 'page'); } else { t.removeAttribute('aria-current'); }
			});
			panes.forEach((p) => { p.hidden = p.getAttribute('data-les-settings-pane') !== key; });
			sections.forEach((s) => { s.hidden = !s.querySelector('[data-les-settings-pane="' + key + '"]'); });
			try { window.sessionStorage.setItem('les_settings_tab', key); } catch (e) { /* stockage indisponible */ }
			if (push) {
				try { const u = new URL(window.location.href); u.searchParams.set('settings_tab', key); window.history.replaceState(null, '', u.toString()); } catch (e) { /* ancien navigateur */ }
			}
		}
		nav.hidden = false;
		nav.addEventListener('click', (e) => {
			const a = e.target.closest('[data-les-settings-tab]');
			if (!a) { return; }
			e.preventDefault();
			show(a.getAttribute('data-les-settings-tab'), true);
		});
		let start = nav.getAttribute('data-current') || '';
		if (!start) { try { start = window.sessionStorage.getItem('les_settings_tab') || ''; } catch (e) { start = ''; } }
		show(start || keys[0], false);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());

/* LogiOne 1.36.4 : menu « Autres actions » des cartes d'évaluation — dans les colonnes par classe (qui défilent), le menu
   était coupé par le bord de la colonne. Il s'affiche maintenant par-dessus la page, toujours entier dans l'écran. */
(function () {
	'use strict';
	function place(details) {
		var menu = details.querySelector('.les-more-actions-menu');
		var summary = details.querySelector('summary');
		if (!menu || !summary) { return; }
		if (!details.open) { menu.classList.remove('is-floating'); menu.style.left = menu.style.top = ''; return; }
		menu.classList.add('is-floating');
		var s = summary.getBoundingClientRect();
		var w = menu.offsetWidth, h = menu.offsetHeight, vw = document.documentElement.clientWidth, vh = window.innerHeight;
		var left = Math.max(8, Math.min(s.left, vw - w - 8));
		var top = s.bottom + 4;
		if (top + h > vh - 8 && s.top - h - 4 > 8) { top = s.top - h - 4; } // pas la place dessous : au-dessus
		menu.style.left = left + 'px'; menu.style.top = top + 'px';
		// Un parent « transformé » (page web) décale les éléments fixes : on corrige d'après la position réelle.
		var r = menu.getBoundingClientRect();
		menu.style.left = (left + (left - r.left)) + 'px'; menu.style.top = (top + (top - r.top)) + 'px';
	}
	document.addEventListener('toggle', function (e) {
		var d = e.target;
		if (!d || !d.classList || !d.classList.contains('les-more-actions')) { return; }
		if (d.open) { document.querySelectorAll('details.les-more-actions[open]').forEach(function (o) { if (o !== d) { o.open = false; } }); }
		place(d);
	}, true);
	function closeAll() { document.querySelectorAll('details.les-more-actions[open]').forEach(function (d) { d.open = false; }); }
	window.addEventListener('resize', closeAll);
	document.addEventListener('scroll', function (e) {
		if (e.target && e.target.closest && e.target.closest('.les-more-actions-menu')) { return; }
		document.querySelectorAll('details.les-more-actions[open]').forEach(place);
	}, true);
	document.addEventListener('click', function (e) {
		if (!e.target.closest || !e.target.closest('details.les-more-actions')) { closeAll(); }
	});
}());

/* LogiOne 1.38.1 : actions « Gérer » d'une copie (ajouter du temps, débloquer, rouvrir) — bouton désactivé dès le
   premier clic, pour qu'un double clic n'envoie pas l'action deux fois (le serveur l'ignore aussi). */
document.addEventListener('submit', function (e) {
	var f = e.target;
	if (!f || !f.classList || !f.classList.contains('les-ctl-form') || e.defaultPrevented) { return; }
	if (f.dataset.lesSent === '1') { e.preventDefault(); return; }
	f.dataset.lesSent = '1';
	Array.prototype.forEach.call(f.querySelectorAll('button[type="submit"], button:not([type])'), function (b) {
		setTimeout(function () { b.disabled = true; b.setAttribute('aria-busy', 'true'); b.textContent = b.textContent + ' …'; }, 0);
	});
}, false);

/* LogiOne 1.40.6 — Correction par question : reprendre là où l'on s'était arrêté. */
(function () {
	function cardOf(el) { return el && el.closest ? el.closest('.les-qcard[id]') : null; }
	function show(card) {
		if (!card) { return; }
		var sec = card.closest('details');
		if (sec && !sec.open) { sec.open = true; }
		card.scrollIntoView({ behavior: 'smooth', block: 'start' });
		card.classList.add('les-next-focus');
		window.setTimeout(function () { card.classList.remove('les-next-focus'); }, 2200);
		var input = card.querySelector('input[name^="question_manual_points"]');
		if (input) { try { input.focus({ preventScroll: true }); } catch (e) {} }
	}
	// Carte en cours : la dernière touchée (clic, saisie), envoyée avec l'enregistrement.
	['focusin', 'click', 'input'].forEach(function (type) {
		document.addEventListener(type, function (e) {
			var card = cardOf(e.target);
			if (!card) { return; }
			var form = card.closest('form');
			var field = form && form.querySelector('[data-les-resume-field]');
			if (field) { field.value = card.id; }
		}, true);
	});
	document.addEventListener('click', function (e) {
		var go = e.target.closest ? e.target.closest('[data-les-resume-go]') : null;
		if (!go) { return; }
		e.preventDefault();
		show(document.getElementById((go.getAttribute('href') || '').slice(1)));
	});
	function onLoad() {
		if (!document.querySelector('.les-qcard[id]')) { return; }
		var hash = window.location.hash ? document.getElementById(window.location.hash.slice(1)) : null;
		var resume = /[?&]les_resume=1/.test(window.location.search);
		var target = hash && hash.classList.contains('les-qcard') ? hash : null;
		if (!target && resume) {
			// La carte enregistrée est faite : on va à la suivante qui reste à corriger.
			target = document.querySelector('.les-qcard[id]:not([data-les-saved])');
		} else if (target && resume && target.hasAttribute('data-les-saved')) {
			var all = Array.prototype.slice.call(document.querySelectorAll('.les-qcard[id]'));
			var next = all.slice(all.indexOf(target) + 1).filter(function (c) { return !c.hasAttribute('data-les-saved'); })[0];
			target = next || target;
		}
		if (target) { window.setTimeout(function () { show(target); }, 250); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', onLoad); } else { onLoad(); }
})();

/* 1.41.0 : onglet « Par élève » — filtre instantané par nom (sans accents) et par classe. */
(function () {
	function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
	function init() {
		var root = document.querySelector('[data-les-student-search]');
		if (!root) { return; }
		var input = root.querySelector('[data-les-ss-input]');
		var group = root.querySelector('[data-les-ss-group]');
		var items = Array.prototype.slice.call(root.querySelectorAll('[data-les-ss-list] li'));
		var empty = root.querySelector('[data-les-ss-empty]');
		function apply() {
			var words = norm(input ? input.value : '').split(/\s+/).filter(Boolean);
			var g = group ? group.value : '';
			var shown = 0;
			items.forEach(function (li) {
				var name = li.getAttribute('data-name') || '';
				var groups = [];
				try { groups = JSON.parse(li.getAttribute('data-groups') || '[]'); } catch (e) { groups = []; }
				var ok = words.every(function (w) { return name.indexOf(w) !== -1; }) && (!g || groups.indexOf(g) !== -1);
				li.hidden = !ok;
				if (ok) { shown++; }
			});
			if (empty) { empty.hidden = shown !== 0; }
		}
		if (input) {
			input.addEventListener('input', apply);
			input.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter') { return; }
				var first = items.filter(function (li) { return !li.hidden; })[0];
				if (first) { e.preventDefault(); first.querySelector('a').click(); }
			});
		}
		if (group) { group.addEventListener('change', apply); }
		var active = root.querySelector('[data-les-ss-list] a.is-active');
		// Élève choisi visible dans la liste (défilement de la liste seulement, pas de la page).
		var list = root.querySelector('[data-les-ss-list]');
		if (active && list) { list.scrollTop = Math.max(0, active.parentNode.offsetTop - list.offsetTop - 8); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();

/* 1.42.0 : heure actuelle affichée à côté du compte à rebours (mise à jour toutes les secondes). */
(function () {
	'use strict';
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function tick() {
		var now = new Date();
		var text = pad(now.getHours()) + ':' + pad(now.getMinutes());
		var nodes = document.querySelectorAll('[data-les-clock-time]');
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].textContent !== text) { nodes[i].textContent = text; }
		}
	}
	function start() { tick(); setInterval(tick, 1000); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
