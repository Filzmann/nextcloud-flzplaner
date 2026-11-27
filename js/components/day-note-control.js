(function() {
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    const { esc, dateShort } = window.FlzPlaner.ui;

    function render(day, canCoordinate) {
        if (!canCoordinate) {
            return `<span class="flz-planer-note-text">${esc(day.note || '')}</span>`;
        }

        return `
            <textarea rows="2" maxlength="2000" aria-label="${translate('flzplaner', 'Bemerkung für')} ${esc(dateShort(day.date))}" data-note-date="${esc(day.date)}">${esc(day.note || '')}</textarea>
            <button type="button" class="flz-planer-small flz-planer-icon-button" aria-label="${translate('flzplaner', 'Bemerkung für')} ${esc(dateShort(day.date))} ${translate('flzplaner', 'speichern')}" data-action="save-note" data-date="${esc(day.date)}">&#10003;</button>
        `;
    }

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.dayNoteControl = { render };
})();
