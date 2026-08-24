(function() {
    const { esc, dateShort } = window.ADPlaner.ui;

    function render(day, canCoordinate) {
        if (!canCoordinate) {
            return `<span class="adp-note-text">${esc(day.note || '')}</span>`;
        }

        return `
            <textarea rows="2" maxlength="2000" aria-label="Bemerkung für ${esc(dateShort(day.date))}" data-note-date="${esc(day.date)}">${esc(day.note || '')}</textarea>
            <button type="button" class="adp-small adp-icon-button" aria-label="Bemerkung für ${esc(dateShort(day.date))} speichern" data-action="save-note" data-date="${esc(day.date)}">&#10003;</button>
        `;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.dayNoteControl = { render };
})();
