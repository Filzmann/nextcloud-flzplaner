(function() {
    const { esc } = window.ADPlaner.ui;

    function render(slot, team, candidates) {
        const assigned = new Set((candidates || []).map(candidate => candidate.uid));
        const assistants = (team.assistants || []).filter(assistant => {
            return assistant.canReceiveShifts !== false && !assigned.has(assistant.uid);
        });
        const pickerId = `adp-assignment-picker-${esc(slot.id)}`;

        return `
            <span class="adp-assignment-control" data-assignment-control="${esc(slot.id)}">
                <button type="button" class="adp-small adp-icon-button" aria-label="Assistenz zuteilen" aria-controls="${pickerId}" aria-expanded="false" data-action="open-assignment-picker" data-assignment-trigger="${esc(slot.id)}" data-slot-id="${esc(slot.id)}" ${assistants.length === 0 ? 'disabled' : ''}>+</button>
                <span id="${pickerId}" class="adp-assignment-picker" role="group" aria-label="Assistenz auswählen" data-assignment-picker="${esc(slot.id)}" hidden>
                    ${assistants.length === 0 ? '<span>Keine Assistenz verfügbar</span>' : assistants.map(assistant => option(assistant, slot.id)).join('')}
                </span>
            </span>
        `;
    }

    function option(assistant, slotId) {
        return `<button type="button" class="adp-small" data-action="add-selected" data-slot-id="${esc(slotId)}" data-target-uid="${esc(assistant.uid)}">${esc(assistant.displayName || assistant.uid)}</button>`;
    }

    function open(button) {
        const slotId = button && button.dataset ? button.dataset.slotId : '';
        if (!slotId) {
            return;
        }

        const picker = document.querySelector(`[data-assignment-picker="${CSS.escape(slotId)}"]`);
        const shouldOpen = !!picker && picker.hidden;
        document.querySelectorAll('[data-assignment-picker]').forEach(candidate => {
            candidate.hidden = true;
        });
        document.querySelectorAll('[data-assignment-trigger]').forEach(trigger => {
            trigger.setAttribute('aria-expanded', 'false');
        });
        if (!shouldOpen) {
            return;
        }

        picker.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        if (!picker.dataset.escapeBound) {
            picker.addEventListener('keydown', event => {
                if (event.key !== 'Escape') return;
                event.preventDefault();
                picker.hidden = true;
                button.setAttribute('aria-expanded', 'false');
                button.focus();
            });
            picker.dataset.escapeBound = 'true';
        }
        const firstOption = picker ? picker.querySelector('button[data-action="add-selected"]') : null;
        if (firstOption) {
            firstOption.focus();
        }
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.assignmentControl = { render, open };
})();
