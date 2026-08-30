(function() {
    const { esc } = window.ADPlaner.ui;

    function render(candidate, canCoordinate, slotId, mutable = true) {
        const removable = mutable && (canCoordinate || candidate.isSelf);
        const displayName = candidate.displayName || candidate.uid;

        const status = ['under', 'over'].includes(candidate.workloadStatus) ? candidate.workloadStatus : 'normal';
        const statusLabel = status === 'under' ? 'Unter persönlichem Minimum' : (status === 'over' ? 'Über persönlichem Maximum' : 'Innerhalb persönlicher Grenzen');
        const marker = candidate.preference === 'favorite' ? '⭐' : (candidate.preference === 'emergency' ? '🆘' : '');
        const editable = mutable && candidate.isSelf;
        return `
            <div class="adp-chip adp-chip--${status}" data-candidate-chip data-slot-id="${esc(slotId)}" data-current-preference="${esc(candidate.preference || 'neutral')}">
                <span class="adp-chip-status" title="${esc(statusLabel)}"><span aria-hidden="true">${status === 'under' ? '▲' : (status === 'over' ? '▽' : '')}</span><span class="adp-visually-hidden">${esc(statusLabel)}: </span></span>
                ${marker ? `<span aria-hidden="true">${marker}</span>` : ''}${esc(displayName)}
                ${editable ? preferenceButtons(candidate.preference || 'neutral', slotId) : ''}
                ${(candidate.note || editable) ? noteDetails(candidate, slotId, editable) : ''}
                ${removable ? `<button type="button" aria-label="${esc(displayName)} entfernen" data-action="remove-candidate" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">&times;</button>` : ''}
            </div>
        `;
    }

    function preferenceButtons(preference, slotId) {
        return `<span class="adp-preference-actions" role="group" aria-label="Schichtpräferenz">
            <button type="button" class="adp-icon-button" aria-label="Lieblingsschicht" aria-pressed="${preference === 'favorite'}" data-action="set-candidate-preference" data-preference="${preference === 'favorite' ? 'neutral' : 'favorite'}" data-slot-id="${esc(slotId)}">⭐</button>
            <button type="button" class="adp-icon-button" aria-label="Nur im Notfall" aria-pressed="${preference === 'emergency'}" data-action="set-candidate-preference" data-preference="${preference === 'emergency' ? 'neutral' : 'emergency'}" data-slot-id="${esc(slotId)}">🆘</button>
        </span>`;
    }

    function noteDetails(candidate, slotId, editable) {
        return `<details class="adp-candidate-note"><summary class="adp-icon-button" aria-label="Anmerkung von ${esc(candidate.displayName || candidate.uid)} anzeigen">ℹ</summary>
            ${editable
                ? `<label>Anmerkung <textarea maxlength="500" rows="2" data-candidate-note>${esc(candidate.note || '')}</textarea></label><button type="button" class="adp-small" data-action="save-candidate-note" data-slot-id="${esc(slotId)}">Anmerkung speichern</button>`
                : `<p>${esc(candidate.note || '')}</p>`}
        </details>`;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.candidateChip = { render };
})();
