(function() {
    const { esc } = window.FlzPlaner.ui;

    function render(candidate, canCoordinate, slotId, mutable = true) {
        const removable = mutable && (candidate.isSelf || (canCoordinate && !candidate.fixed));
        const displayName = candidate.displayName || candidate.uid;

        const status = ['under', 'over'].includes(candidate.workloadStatus) ? candidate.workloadStatus : 'normal';
        const statusLabel = status === 'under' ? 'Unter persönlichem Minimum' : (status === 'over' ? 'Über persönlichem Maximum' : 'Innerhalb persönlicher Grenzen');
        const preference = ['favorite', 'emergency'].includes(candidate.preference) ? candidate.preference : 'neutral';
        const editable = mutable && candidate.isSelf;
        return `
            <div class="flz-planer-chip flz-planer-chip--${status}${editable ? ' flz-planer-chip--editable' : ''}"${status === 'normal' ? '' : ` title="${esc(statusLabel)}"`}${editable ? ` tabindex="0" aria-label="Schichtaktionen für ${esc(displayName)}"` : ''} data-candidate-chip data-slot-id="${esc(slotId)}" data-current-preference="${esc(preference)}" data-fixed="${candidate.fixed ? 'true' : 'false'}">
                <span class="flz-planer-chip-status"><span class="flz-planer-visually-hidden">${esc(statusLabel)}: </span></span>
                ${preferenceDisplay(preference)}
                ${candidate.fixed ? '<span class="flz-planer-fixed-marker" aria-label="Feste Schicht" title="Regelmäßige feste Schicht">🔒</span>' : ''}
                ${candidate.unavailable ? '<span class="flz-planer-vacation-conflict" aria-label="Urlaub – nicht für den Planvorschlag verfügbar" title="Urlaub – nicht für den Planvorschlag verfügbar">U</span>' : ''}
                <span class="flz-planer-candidate-name">${esc(displayName)}</span>
                ${removable ? `<button type="button" aria-label="${esc(displayName)} entfernen" data-action="remove-candidate" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">&times;</button>` : ''}
                ${editable ? preferencePanel(candidate, preference, slotId) : ''}
            </div>
        `;
    }

    function preferencePanel(candidate, preference, slotId) {
        const favoriteLabel = preference === 'favorite' ? 'Lieblingsschicht entfernen' : 'Als Lieblingsschicht markieren';
        const emergencyLabel = 'Nur wenn sonst niemand kann';
        const noteLabel = candidate.note ? 'Anmerkung bearbeiten' : 'Anmerkung hinzufügen';
        return `<div class="flz-planer-preference-panel" role="group" aria-label="Schichtaktionen">
            <button type="button" class="flz-planer-preference-option flz-planer-preference-option--favorite" aria-label="${esc(favoriteLabel)}" title="${esc(favoriteLabel)}" aria-pressed="${preference === 'favorite'}" data-action="set-candidate-preference" data-preference="${preference === 'favorite' ? 'neutral' : 'favorite'}" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}"><span aria-hidden="true">★</span></button>
            <button type="button" class="flz-planer-preference-option flz-planer-preference-option--emergency" aria-label="${esc(emergencyLabel)}" title="${esc(emergencyLabel)}" aria-pressed="${preference === 'emergency'}" data-action="set-candidate-preference" data-preference="${preference === 'emergency' ? 'neutral' : 'emergency'}" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}"><span aria-hidden="true">🛟</span></button>
            <button type="button" class="flz-planer-preference-option" aria-label="${esc(noteLabel)}" title="${esc(noteLabel)}" data-action="open-candidate-note-editor" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}"><span aria-hidden="true">ⓘ</span></button>
        </div>`;
    }

    function preferenceDisplay(preference) {
        if (preference === 'neutral') return '';
        return `<span class="flz-planer-chip-preference" aria-label="${esc(preferenceLabel(preference))}" title="${esc(preferenceLabel(preference))}">${preferenceMarker(preference)}</span>`;
    }

    function preferenceMarker(preference) {
        const marker = preference === 'favorite' ? '★' : (preference === 'emergency' ? '🛟' : '☆');
        return `<span class="flz-planer-preference-marker flz-planer-preference-marker--${preference}" aria-hidden="true">${marker}</span>`;
    }

    function preferenceLabel(preference) {
        if (preference === 'favorite') return 'Lieblingsschicht';
        if (preference === 'emergency') return 'Nur wenn sonst niemand kann';
        return 'Keine Markierung';
    }

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.candidateChip = { render };
})();
