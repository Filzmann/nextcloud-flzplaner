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
                ${renderWorkload(plan.workload || [], canCoordinate)}
            </section>
        `;
    }

    function renderWorkload(rows, canCoordinate) {
        if (!rows.length) return '';
        const content = `<div class="adp-table-wrap"><table class="adp-table adp-workload-table"><thead><tr><th scope="col">Person</th><th scope="col">Monat</th><th scope="col">Kalenderwochen</th></tr></thead><tbody>${rows.map(row => `<tr class="adp-workload--${esc(row.status || row.monthStatus || 'normal')}"><th scope="row">${esc(row.displayName || row.uid)}</th><td>${esc(row.monthCount)} (${limitRange(row.monthlyMin, row.monthlyMax)}) <span>${esc(statusLabel(row.monthStatus))}</span></td><td>${(row.weeks || []).map(week => `${esc(week.label)}: ${esc(week.count)} <span>${esc(statusLabel(week.status))}</span>`).join('<br>')}</td></tr>`).join('')}</tbody></table></div>`;
        return `<details class="adp-workload-overview" ${canCoordinate ? 'open' : ''}><summary>${canCoordinate ? 'Auslastungsübersicht des Teams' : 'Meine Auslastung einblenden'}</summary>${content}</details>`;
    }

    function statusLabel(status) {
        return status === 'under' ? 'Unter persönlichem Minimum' : (status === 'over' ? 'Über persönlichem Maximum' : 'Innerhalb persönlicher Grenzen');
    }

    function limitRange(minimum, maximum) {
        return `${minimum ?? '–'} bis ${maximum ?? '–'}`;
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
                <th scope="row" class="adp-day">${dayHeader(day)}<small>${esc(dateShort(day.date))}</small>${renderHints(day.hints || [])}</th>
                ${segments.map(segment => slotCell(slotsByKey[segment.key], team, currentUser, canCoordinate, mutable)).join('')}
                <td class="adp-note-cell">${renderDayNoteControl(day, canCoordinate && mutable)}</td>
            </tr>
        `;
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
        const selfAction = mutable && !canCoordinate && !hasSelf
            ? `<button type="button" class="adp-small" data-action="add-self" data-slot-id="${esc(slot.id)}">+ ich</button>`
            : '';

        return `
            <td>
                <div class="adp-candidates">
                    ${candidates.map(candidate => renderCandidateChip(candidate, canCoordinate, slot.id, mutable)).join('')}
                </div>
                <div class="adp-cell-actions">
                    ${selfAction}
                    ${canCoordinate && mutable ? renderAssignmentControl(slot, team, candidates) : ''}
                </div>
            </td>
        `;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.monthPlan = { render };
})();
