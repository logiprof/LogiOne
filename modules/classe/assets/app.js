function gcaEscapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function gcaLooksLikeHtml(value) {
    return /<\/?[a-z][\s\S]*>/i.test(String(value || ''));
}

function gcaValueToRichHtml(value) {
    const normalized = String(value || '').replace(/\r\n?/g, '\n').trim();

    if (!normalized) {
        return '';
    }

    if (gcaLooksLikeHtml(normalized)) {
        return normalized;
    }

    return normalized
        .split(/\n{2,}/)
        .map(function (paragraph) {
            const lines = paragraph
                .split('\n')
                .map(function (line) {
                    return gcaEscapeHtml(line);
                })
                .join('<br>');

            return '<p>' + lines + '</p>';
        })
        .join('');
}

function gcaFindRichEditorForSource(source) {
    const wrap = source.closest('[data-gca-rich-wrap]');
    const editor = wrap ? wrap.querySelector('[data-gca-rich-editor]') : null;

    if (editor) {
        return editor;
    }

    const editorId = source.getAttribute('data-gca-rich-source-id');
    return editorId ? document.getElementById(editorId) : null;
}

function gcaSyncRichEditor(editor) {
    const wrap = editor.closest('[data-gca-rich-wrap]');
    const source = wrap ? wrap.querySelector('[data-gca-rich-source]') : null;

    if (source) {
        source.value = editor.innerHTML.trim();
    }
}

function gcaSyncRichEditors(scope) {
    scope.querySelectorAll('[data-gca-rich-editor]').forEach(function (editor) {
        gcaSyncRichEditor(editor);
    });
}

function gcaRichTextIsEmpty(html) {
    const tester = document.createElement('div');
    tester.innerHTML = String(html || '');

    return tester.textContent.replace(/\u00a0/g, ' ').trim() === '' && !tester.querySelector('img');
}

function gcaSetRichEditorValue(source, value) {
    const editor = gcaFindRichEditorForSource(source);
    source.value = String(value || '');

    if (editor) {
        editor.innerHTML = gcaValueToRichHtml(value);
        gcaSyncRichEditor(editor);
    }
}

document.addEventListener('submit', function (event) {
    const form = event.target;
    const message = form.getAttribute('data-gca-confirm');

    gcaSyncRichEditors(form);

    const missingRequiredRichSource = Array.from(form.querySelectorAll('[data-gca-rich-source][data-gca-required="1"]')).find(function (source) {
        return gcaRichTextIsEmpty(source.value);
    });
    if (missingRequiredRichSource && gcaRichTextIsEmpty(missingRequiredRichSource.value)) {
        const editor = gcaFindRichEditorForSource(missingRequiredRichSource);
        event.preventDefault();
        window.alert('Le message est obligatoire.');

        if (editor) {
            editor.focus();
        }

        return;
    }

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.addEventListener('input', function (event) {
    const editor = event.target.closest('[data-gca-rich-editor]');

    if (editor) {
        gcaSyncRichEditor(editor);
    }
});

document.addEventListener('blur', function (event) {
    const editor = event.target.closest('[data-gca-rich-editor]');

    if (editor) {
        gcaSyncRichEditor(editor);
    }
}, true);

document.addEventListener('mousedown', function (event) {
    const button = event.target.closest('[data-gca-rich-command], [data-gca-rich-link]');

    if (button) {
        event.preventDefault();
    }
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-gca-toggle-target]');

    if (!button) {
        return;
    }

    const targetId = button.getAttribute('data-gca-toggle-target');
    const target = targetId ? document.getElementById(targetId.replace(/^#/, '')) : null;

    if (!target) {
        return;
    }

    event.preventDefault();

    const closedLabel = button.getAttribute('data-gca-toggle-label-closed') || button.textContent.trim();
    const openLabel = button.getAttribute('data-gca-toggle-label-open') || closedLabel;
    const willOpen = target.hasAttribute('hidden');

    if (!button.hasAttribute('data-gca-toggle-label-closed')) {
        button.setAttribute('data-gca-toggle-label-closed', closedLabel);
    }

    target.toggleAttribute('hidden', !willOpen);
    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    button.textContent = willOpen ? openLabel : closedLabel;
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-gca-rich-command], [data-gca-rich-link]');

    if (!button) {
        return;
    }

    const wrap = button.closest('[data-gca-rich-wrap]');
    const editor = wrap ? wrap.querySelector('[data-gca-rich-editor]') : null;

    if (!editor) {
        return;
    }

    editor.focus();

    if (button.hasAttribute('data-gca-rich-link')) {
        let url = window.prompt('Lien à insérer');

        if (url === null) {
            return;
        }

        url = url.trim();

        if (url === '') {
            return;
        }

        if (!/^[a-z][a-z0-9+.-]*:/i.test(url) && !url.startsWith('#')) {
            url = 'https://' + url;
        }

        document.execCommand('createLink', false, url);
        gcaSyncRichEditor(editor);
        return;
    }

    const command = button.getAttribute('data-gca-rich-command');

    if (command === 'removeFormat') {
        document.execCommand('removeFormat', false, null);
        document.execCommand('unlink', false, null);
    } else if (command) {
        document.execCommand(command, false, null);
    }

    gcaSyncRichEditor(editor);
});

document.addEventListener('change', function (event) {
    const bulkSelectAll = event.target.closest('[data-gca-bulk-select-all]');

    if (bulkSelectAll) {
        const table = bulkSelectAll.closest('table');

        if (table) {
            table.querySelectorAll('[data-gca-bulk-row]').forEach(function (checkbox) {
                checkbox.checked = bulkSelectAll.checked;
            });
            gcaUpdateBulkSelection(table);
        }

        return;
    }

    const bulkRow = event.target.closest('[data-gca-bulk-row]');

    if (bulkRow) {
        const table = bulkRow.closest('table');

        if (table) {
            gcaUpdateBulkSelection(table);
        }
    }

    const trainerSelect = event.target.closest('[data-gca-trainer-select]');

    if (trainerSelect) {
        const option = trainerSelect.options[trainerSelect.selectedIndex];
        const scope = trainerSelect.closest('tr') || trainerSelect.closest('form') || document;

        if (!option) {
            return;
        }

        scope.querySelectorAll('[data-gca-trainer-field]').forEach(function (field) {
            const fieldName = field.getAttribute('data-gca-trainer-field');
            const attributeName = 'data-' + String(fieldName || '').replace(/_/g, '-');
            field.value = option.dataset[fieldName] || option.getAttribute(attributeName) || '';
        });

        scope.querySelectorAll('[data-gca-trainer-email-display]').forEach(function (display) {
            display.textContent = option.dataset.email || '-';
        });
    }

    const select = event.target.closest('[data-gca-trainer-template-select], [data-gca-message-template-select]');

    if (!select) {
        return;
    }

    const form = select.closest('form');
    const option = select.options[select.selectedIndex];

    if (!form || !option) {
        return;
    }

    const label = form.querySelector('[data-gca-message-template-label], [data-gca-trainer-template-label]');
    const subject = form.querySelector('[data-gca-message-template-subject], [data-gca-trainer-template-subject]');
    const body = form.querySelector('[data-gca-message-template-body], [data-gca-trainer-template-body]');

    if (label) {
        label.value = option.dataset.label || '';
    }

    if (subject) {
        subject.value = option.dataset.subject || '';
    }

    if (body) {
        gcaSetRichEditorValue(body, option.dataset.body || '');
    }
});

function gcaUpdateBulkSelection(table) {
    const rowCheckboxes = Array.from(table.querySelectorAll('[data-gca-bulk-row]'));
    const checkedCount = rowCheckboxes.filter(function (checkbox) {
        return checkbox.checked;
    }).length;
    const selectAll = table.querySelector('[data-gca-bulk-select-all]');
    const form = table.closest('form');

    if (selectAll) {
        selectAll.checked = rowCheckboxes.length > 0 && checkedCount === rowCheckboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
    }

    if (form) {
        form.querySelectorAll('[data-gca-selected-count]').forEach(function (target) {
            target.textContent = checkedCount > 0 ? checkedCount + ' sélectionné(s)' : 'Aucune sélection';
        });
    }
}
