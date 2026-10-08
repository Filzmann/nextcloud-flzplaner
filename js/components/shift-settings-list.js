(function() {
    const { esc } = window.FlzPlaner.ui;

    function renderReadonly(shifts) {
        return `
            <div class="flz-planer-readonly-shifts">
                ${(shifts || []).map(readonlyShift).join('')}
            </div>
        `;
    }

    function renderEditor(shifts) {
        return `
            <div id="flz-planer-shift-list" class="flz-planer-shift-list">
                ${(shifts || []).map((shift, index) => shiftRow(shift, index)).join('')}
            </div>
            <button type="button" class="flz-planer-small" data-action="add-shift-row">+ Schicht</button>
        `;
    }

    function readonlyShift(shift) {
        return `
            <span class="flz-planer-readonly-shift ${shift.enabled ? '' : 'is-disabled'}">
                ${esc(shift.label || shift.key)} ${esc(shift.startsAt)}-${esc(shift.endsAt)}
            </span>
        `;
    }

    function shiftRow(shift, index) {
        return `
            <div class="flz-planer-shift-row" data-shift-row>
                <input name="shiftKey" type="hidden" value="${esc(shift.key || newShiftKey(index))}">
                <label>Name <input name="shiftLabel" type="text" maxlength="64" required value="${esc(shift.label || ('Schicht ' + (index + 1)))}"></label>
                <label>Von <input name="shiftStart" type="time" required value="${esc(shift.startsAt || '08:00')}"></label>
                <label>Bis <input name="shiftEnd" type="time" required value="${esc(shift.endsAt || '14:00')}"></label>
                <label class="flz-planer-check"><input name="shiftEnabled" type="checkbox" ${shift.enabled === false ? '' : 'checked'}> aktiv</label>
                <button type="button" class="flz-planer-small" aria-label="Schicht ${esc(shift.label || ('Schicht ' + (index + 1)))} entfernen" data-action="remove-shift-row">&times;</button>
            </div>
        `;
    }

    function addRow() {
        const list = document.getElementById('flz-planer-shift-list');
        if (!list) {
            return;
        }

        const index = list.querySelectorAll('[data-shift-row]').length;
        list.insertAdjacentHTML('beforeend', shiftRow({
            key: newShiftKey(index),
            label: 'Schicht ' + (index + 1),
            startsAt: '08:00',
            endsAt: '14:00',
            enabled: true
        }, index));
    }

    function removeRow(button) {
        const list = document.getElementById('flz-planer-shift-list');
        const row = button instanceof Element ? button.closest('[data-shift-row]') : null;
        if (!list || !row || list.querySelectorAll('[data-shift-row]').length <= 1) {
            return;
        }

        row.remove();
    }

    function collect(form) {
        return Array.from(form.querySelectorAll('[data-shift-row]')).map(row => {
            return {
                key: row.querySelector('[name="shiftKey"]').value,
                label: row.querySelector('[name="shiftLabel"]').value,
                startsAt: row.querySelector('[name="shiftStart"]').value,
                endsAt: row.querySelector('[name="shiftEnd"]').value,
                enabled: row.querySelector('[name="shiftEnabled"]').checked
            };
        });
    }

    function newShiftKey(index) {
        return 'shift_' + Date.now().toString(36).slice(-8) + '_' + String(index + 1);
    }

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.shiftSettingsList = { renderReadonly, renderEditor, addRow, removeRow, collect };
})();
