(function() {
    const { esc, dateShort, dayHeader } = window.ADPlaner.ui;
    const { render: renderAssignmentControl } = window.ADPlaner.assignmentControl;
    const { render: renderCandidateChip } = window.ADPlaner.candidateChip;
    const { render: renderDayNoteControl } = window.ADPlaner.dayNoteControl;

    function render(plan, currentUser) {
        if (!plan || !plan.team) {
            return '<p>Kein Assistenznehmer gewählt.</p>';
        }

        const team = plan.team;
        const segments = plan.segments || [];
        const canCoordinate = !!team.canCoordinate;
        const status = typeof plan.status === 'string' ? plan.status : '';
        const mutable = status === 'draft' || status === 'planned';

        return `
            <section class="adp-section">
                <div class="adp-section-head">
                    <h2>${esc(team.displayName || team.code)} - ${esc(plan.month)}</h2>
                    <div class="adp-plan-meta">
                        ${team.settings && team.settings.meetingDay ? `<span class="adp-badge">Treffen ${esc(dateShort(team.settings.meetingDay))}</span>` : ''}
                        ${renderStatus(status, canCoordinate)}
                    </div>
                </div>
                <div class="adp-table-wrap">
                    <table class="adp-table adp-month-table">
                        <thead>
                            <tr>
                                <th scope="col">Tag</th>
                                ${segments.map(segment => `<th scope="col">${esc(segment.label)}<small>${esc(segment.startsAt)}-${esc(segment.endsAt)}</small></th>`).join('')}
                                <th scope="col">Bemerkungen</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(plan.days || []).map(day => dayRow(day, segments, team, currentUser, canCoordinate, mutable)).join('')}
                        </tbody>
                    </table>
                </div>
            </section>
        `;
    }

    function renderStatus(status, canCoordinate) {
        const labels = { draft: 'Entwurf', planned: 'Geplant', approved: 'Genehmigt' };
        const actions = {
            draft: [['planned', 'Als geplant festschreiben']],
            planned: [['draft', 'Auf Entwurf zurücksetzen'], ['approved', 'Genehmigen']],
            approved: [['planned', 'Genehmigung aufheben']],
        };
        return `<div class="adp-plan-status" data-plan-status="${esc(status)}">
            <span class="adp-badge">Status: ${esc(labels[status] || status)}</span>
            ${canCoordinate ? (actions[status] || []).map(([target, label]) => `<button type="button" class="adp-small" data-action="transition-status" data-target-status="${esc(target)}">${esc(label)}</button>`).join('') : ''}
        </div>`;
    }

    function dayRow(day, segments, team, currentUser, canCoordinate, mutable) {
        const slotsByKey = {};
        (day.slots || []).forEach(slot => {
            slotsByKey[slot.segmentKey] = slot;
        });

        return `
            <tr>
                <th scope="row" class="adp-day">${dayHeader(day)}<small>${esc(dateShort(day.date))}</small><span class="adp-week-label">${esc(day.weekLabel || '')}</span>${renderHints(day.hints || [])}</th>
                ${segments.map(segment => slotCell(slotsByKey[segment.key], team, currentUser, canCoordinate, mutable)).join('')}
                <td class="adp-note-cell">
                    <div class="adp-eb-note">${renderDayNoteControl(day, canCoordinate && mutable)}</div>
                    ${renderCandidateNotes(day, segments, mutable)}
                </td>
            </tr>
        `;
    }

    function renderCandidateNotes(day, segments, mutable) {
        const labels = Object.fromEntries(segments.map(segment => [segment.key, segment.label]));
        const entries = [];
        for (const slot of day.slots || []) {
            const shiftLabel = labels[slot.segmentKey] || slot.segmentKey || 'Schicht';
            for (const candidate of slot.candidates || []) {
                const editable = mutable && candidate.isSelf;
                if (!candidate.note && !editable) continue;
                entries.push(renderCandidateNote(candidate, slot.id, shiftLabel, editable));
            }
        }
        return entries.length ? `<div class="adp-shift-notes">${entries.join('')}</div>` : '';
    }

    function renderCandidateNote(candidate, slotId, shiftLabel, editable) {
        const displayName = candidate.displayName || candidate.uid;
        const preference = ['favorite', 'emergency'].includes(candidate.preference) ? candidate.preference : 'neutral';
        const note = candidate.note || '';
        return `<div class="adp-shift-note${note ? '' : ' adp-shift-note--empty'}" data-candidate-note-entry data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}" data-current-preference="${esc(preference)}">
            ${note ? `<div class="adp-shift-note-display"><p><strong>${esc(displayName)}, ${esc(shiftLabel)}:</strong> ${esc(note)}</p>${editable ? `<div class="adp-shift-note-actions"><button type="button" class="adp-icon-button" aria-label="Anmerkung bearbeiten" title="Anmerkung bearbeiten" data-action="open-candidate-note-editor" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">✎</button><button type="button" class="adp-icon-button" aria-label="Anmerkung löschen" title="Anmerkung löschen" data-action="delete-candidate-note" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">&times;</button></div>` : ''}</div>` : ''}
            ${editable ? `<div class="adp-shift-note-editor" hidden><label>Anmerkung <textarea maxlength="500" rows="2" data-candidate-note>${esc(note)}</textarea></label><div><button type="button" class="adp-small" data-action="save-candidate-note" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">Speichern</button><button type="button" class="adp-small" data-action="close-candidate-note-editor" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">Abbrechen</button></div></div>` : ''}
        </div>`;
    }

    function renderHints(hints) {
        if (!hints.length) return '';
        return `<div class="adp-day-hints" aria-label="Planungshinweise">${hints.map(hint =>
            `<span class="adp-hint" title="${esc(hint.label || 'Hinweis')}">${esc(hint.marker || '•')} ${esc(hint.displayName || hint.employeeUid || '')}</span>`
        ).join('')}</div>`;
    }

    function slotCell(slot, team, currentUser, canCoordinate, mutable) {
        if (!slot) {
            return '<td class="adp-empty"></td>';
        }

        const candidates = slot.candidates || [];
        const selfUid = currentUser && currentUser.uid ? currentUser.uid : '';
        const hasSelf = candidates.some(candidate => candidate.uid === selfUid);
        const selfAction = mutable && !canCoordinate && !hasSelf && !slot.selfUnavailable
            ? `<button type="button" class="adp-small" data-action="add-self" data-slot-id="${esc(slot.id)}">+ ich</button>`
            : '';

        return `
            <td>
                <div class="adp-candidates">
                    ${candidates.map(candidate => renderCandidateChip(candidate, canCoordinate, slot.id, mutable)).join('')}
                </div>
                ${renderFixedConflict(slot, candidates, canCoordinate, mutable)}
                <div class="adp-cell-actions">
                    ${selfAction}
                    ${slot.selfUnavailable && !canCoordinate ? '<span class="adp-badge">Nicht verfügbar</span>' : ''}
                    ${canCoordinate && mutable ? renderAssignmentControl(slot, team, candidates) : ''}
                </div>
            </td>
        `;
    }

    function renderFixedConflict(slot,candidates,canCoordinate,mutable) {
        const conflict=slot.fixedConflict;
        if (!conflict) return '';
        const labels=(conflict.candidateUids||[]).map(uid=>candidates.find(candidate=>candidate.uid===uid)?.displayName||uid);
        return `<div class="adp-fixed-conflict" role="alert"><strong>Konflikt bei festen Schichten</strong><span>${labels.map(esc).join(', ')}</span>${conflict.status==='escalated'?'<span class="adp-badge">An EB weitergegeben</span>':''}${mutable&&conflict.canReport?`<button type="button" class="adp-small" data-action="report-fixed-conflict" data-slot-id="${esc(slot.id)}">An EB weitergeben</button>`:''}${mutable&&canCoordinate&&conflict.canResolve?(conflict.candidateUids||[]).map(uid=>`<button type="button" class="adp-small" data-action="resolve-fixed-conflict" data-slot-id="${esc(slot.id)}" data-kept-uid="${esc(uid)}">${esc(candidates.find(candidate=>candidate.uid===uid)?.displayName||uid)} fest behalten</button>`).join(''):''}</div>`;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.monthPlan = { render };
})();
