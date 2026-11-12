function gneRoundToHalf(value) {
    return Math.round(value * 2) / 2;
}

function gneFormatNote(value) {
    if (!Number.isFinite(value)) {
        return '-';
    }

    return Number.isInteger(value) ? String(value) : value.toFixed(1).replace('.', ',');
}

function gneParseNumber(value) {
    const normalized = String(value || '').replace(',', '.').trim();

    if (!normalized) {
        return null;
    }

    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
}

function gneNormalizeText(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function gneUpdateGradeRow(input, totalPoints) {
    const row = input.closest('tr');
    const pointsInput = row ? row.querySelector('[data-gne-points-input]') : input;
    const output = row ? row.querySelector('[data-gne-note-output]') : null;
    const baseOutput = row ? row.querySelector('[data-gne-base-note-output]') : null;
    const notApplicable = row ? row.querySelector('[data-gne-not-applicable]') : null;
    const cheatingToggle = row ? row.querySelector('[data-gne-cheating-toggle]') : null;
    const adjustmentInput = row ? row.querySelector('[data-gne-adjustment-input]') : null;
    const makeupToggle = row ? row.querySelector('[data-gne-makeup-toggle]') : null;
    const makeupDate = row ? row.querySelector('[data-gne-makeup-date]') : null;
    const makeupDateWrap = row ? row.querySelector('[data-gne-makeup-date-wrap]') : null;
    const points = gneParseNumber(pointsInput ? pointsInput.value : '');
    const adjustment = gneParseNumber(adjustmentInput ? adjustmentInput.value : '') || 0;

    if (!output) {
        return;
    }

    if (notApplicable && notApplicable.checked) {
        if (pointsInput) {
            pointsInput.value = '';
            pointsInput.disabled = true;
        }

        if (adjustmentInput) {
            adjustmentInput.value = '0';
            adjustmentInput.disabled = true;
        }

        if (cheatingToggle) {
            cheatingToggle.checked = false;
            cheatingToggle.disabled = true;
        }

        if (makeupToggle) {
            makeupToggle.checked = false;
            makeupToggle.disabled = true;
        }

        if (makeupDate) {
            makeupDate.value = '';
            makeupDate.disabled = true;
            makeupDate.required = false;
        }

        if (makeupDateWrap) {
            makeupDateWrap.hidden = true;
        }

        output.textContent = 'Non faite';
        if (baseOutput) {
            baseOutput.textContent = 'Non faite';
        }
        return;
    }

    if (cheatingToggle) {
        cheatingToggle.disabled = false;
    }

    if (cheatingToggle && cheatingToggle.checked) {
        if (pointsInput) {
            pointsInput.value = '';
            pointsInput.disabled = true;
        }

        if (adjustmentInput) {
            adjustmentInput.value = '0';
            adjustmentInput.disabled = true;
        }

        if (makeupToggle) {
            makeupToggle.checked = false;
            makeupToggle.disabled = true;
        }

        if (makeupDate) {
            makeupDate.value = '';
            makeupDate.disabled = true;
            makeupDate.required = false;
        }

        if (makeupDateWrap) {
            makeupDateWrap.hidden = true;
        }

        output.textContent = '1';
        if (baseOutput) {
            baseOutput.textContent = '1';
        }
        return;
    }

    if (pointsInput) {
        pointsInput.disabled = false;
    }

    if (adjustmentInput) {
        adjustmentInput.disabled = false;
    }

    if (makeupToggle) {
        makeupToggle.disabled = false;
    }

    if (makeupDate && makeupToggle) {
        makeupDate.disabled = !makeupToggle.checked;
        makeupDate.required = makeupToggle.checked;

        if (makeupDateWrap) {
            makeupDateWrap.hidden = !makeupToggle.checked;
        }

        if (!makeupToggle.checked) {
            makeupDate.value = '';
        }
    }

    if (totalPoints <= 0 || points === null) {
        output.textContent = '-';
        if (baseOutput) {
            baseOutput.textContent = '-';
        }
        return;
    }

    const adjustedPoints = Math.min(Math.max(points + adjustment, 0), totalPoints);
    const note = gneRoundToHalf(((adjustedPoints / totalPoints) * 5) + 1);
    const basePoints = Math.min(Math.max(points, 0), totalPoints);
    const baseNote = gneRoundToHalf(((basePoints / totalPoints) * 5) + 1);
    output.textContent = gneFormatNote(note);
    if (baseOutput) {
        baseOutput.textContent = gneFormatNote(baseNote);
    }
}

function gneUpdateEvaluationForm(form) {
    const classSelect = form.querySelector('[data-gne-class-select]');
    const selectedClassId = classSelect ? classSelect.value : '';
    const totalInput = form.querySelector('[data-gne-total-points]');
    const totalPoints = gneParseNumber(totalInput ? totalInput.value : '') || 0;
    const noClass = form.querySelector('[data-gne-no-class]');

    if (noClass) {
        noClass.hidden = selectedClassId !== '';
    }

    form.querySelectorAll('[data-gne-class-students]').forEach(function (section) {
        section.hidden = section.getAttribute('data-gne-class-students') !== selectedClassId;
    });

    form.querySelectorAll('[data-gne-points-input]').forEach(function (input) {
        if (totalPoints > 0) {
            input.setAttribute('max', String(totalPoints));
        } else {
            input.removeAttribute('max');
        }

        gneUpdateGradeRow(input, totalPoints);
    });
}

function gneUpdateRecentEvaluations(panel) {
    const classSelect = panel.querySelector('[data-gne-recent-class]');
    const evaluationSelect = panel.querySelector('[data-gne-recent-evaluation]');
    const empty = panel.querySelector('[data-gne-recent-empty]');
    const classId = classSelect ? classSelect.value : '';
    const evaluationName = gneNormalizeText(evaluationSelect ? evaluationSelect.value : '');
    let visibleCount = 0;

    panel.querySelectorAll('[data-gne-recent-item]').forEach(function (item) {
        const matchesClass = !classId || item.getAttribute('data-gne-class-id') === classId;
        const matchesEvaluation = !evaluationName || gneNormalizeText(item.getAttribute('data-gne-evaluation-name')) === evaluationName;
        const isVisible = matchesClass && matchesEvaluation;

        item.hidden = !isVisible;

        if (isVisible) {
            visibleCount += 1;
        }
    });

    if (empty) {
        empty.hidden = visibleCount > 0;
    }
}

document.addEventListener('input', function (event) {
    const form = event.target.closest('[data-gne-evaluation-form]');

    if (form && event.target.matches('[data-gne-total-points], [data-gne-points-input], [data-gne-adjustment-input], [data-gne-not-applicable], [data-gne-cheating-toggle]')) {
        gneUpdateEvaluationForm(form);
    }
});

document.addEventListener('change', function (event) {
    const form = event.target.closest('[data-gne-evaluation-form]');

    if (form && event.target.matches('[data-gne-class-select], [data-gne-adjustment-input], [data-gne-not-applicable], [data-gne-makeup-toggle], [data-gne-cheating-toggle]')) {
        gneUpdateEvaluationForm(form);
    }

    const recentPanel = event.target.closest('[data-gne-recent-panel]');

    if (recentPanel && event.target.matches('[data-gne-recent-class], [data-gne-recent-evaluation]')) {
        gneUpdateRecentEvaluations(recentPanel);
    }

    const board = event.target.closest('[data-gne-board]');

    if (board && event.target.matches('[data-gne-board-class], [data-gne-board-evaluation]')) {
        gneUpdateBoard(board);
    }
});

function gneUpdateBoard(board) {
    const classSelect = board.querySelector('[data-gne-board-class]');
    const nameSelect = board.querySelector('[data-gne-board-evaluation]');
    const empty = board.querySelector('[data-gne-board-empty]');
    const classId = classSelect ? classSelect.value : '';
    const name = gneNormalizeText(nameSelect ? nameSelect.value : '');
    let visible = 0;

    board.querySelectorAll('[data-gne-board-column]').forEach(function (column) {
        const columnMatches = !classId || column.getAttribute('data-gne-class-id') === classId;
        let columnHits = 0;
        column.querySelectorAll('[data-gne-board-item]').forEach(function (item) {
            const show = columnMatches && (!name || gneNormalizeText(item.getAttribute('data-gne-evaluation-name')) === name);
            item.hidden = !show;
            if (show) {
                columnHits += 1;
            }
        });
        // Avec un filtre d'évaluation, les plus anciennes sont dépliées et les colonnes sans résultat masquées.
        column.querySelectorAll('[data-gne-board-more]').forEach(function (more) {
            if (name) {
                more.open = true;
            }
        });
        const none = column.querySelector('.gne-board-none');
        if (none) {
            none.hidden = !!name;
        }
        column.hidden = !columnMatches || (name !== '' && columnHits === 0);
        visible += columnHits;
    });

    board.classList.toggle('is-single', classId !== '');
    if (empty) {
        empty.hidden = visible > 0 || (!name && !classId);
    }
}

document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-gne-new-toggle], [data-gne-new-close]');
    if (!toggle) {
        return;
    }
    const panel = document.querySelector('[data-gne-new-panel]');
    if (!panel) {
        return;
    }
    event.preventDefault();
    const preset = toggle.getAttribute('data-gne-new-class');
    const open = toggle.hasAttribute('data-gne-new-toggle') ? (preset ? true : panel.hidden) : false;
    if (open && preset) {
        // « + » d'une colonne : le formulaire s'ouvre avec ce groupe classe déjà choisi.
        const select = panel.querySelector('select[name="class_id"]');
        if (select) {
            select.value = preset;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    panel.hidden = !open;
    document.querySelectorAll('[data-gne-new-toggle]').forEach(function (button) {
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    if (open) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const first = panel.querySelector('input[name="module_name"]');
        if (first) {
            first.focus({ preventScroll: true });
        }
    } else {
        const button = document.querySelector('[data-gne-new-toggle]');
        if (button) {
            button.focus();
        }
    }
});

document.addEventListener('submit', function (event) {
    const message = event.target.getAttribute('data-gne-confirm');

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-gne-evaluation-form]').forEach(gneUpdateEvaluationForm);
    document.querySelectorAll('[data-gne-recent-panel]').forEach(gneUpdateRecentEvaluations);
    document.querySelectorAll('[data-gne-board]').forEach(gneUpdateBoard);
});
