(function() {
    const { esc } = window.ADPlaner.ui;

    function render(candidate, canCoordinate, slotId, mutable = true) {
        const removable = mutable && (canCoordinate || candidate.isSelf);
        const displayName = candidate.displayName || candidate.uid;

        return `
            <span class="adp-chip">
                ${esc(displayName)}
                ${removable ? `<button type="button" aria-label="${esc(displayName)} entfernen" data-action="remove-candidate" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">&times;</button>` : ''}
            </span>
        `;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.candidateChip = { render };
})();
