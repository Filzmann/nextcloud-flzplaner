(function() {
    const { esc, dateShort, dayHeader, renderCapacity, hasCapacityLimits } = window.ADPlaner.ui;
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
        const personalWorkload = canCoordinate ? null : (plan.workload || []).find(row => row.uid === currentUser?.uid) || null;
        const status = typeof plan.status === 'string' ? plan.status : '';
        const mutable = status === 'draft' || status === 'planned';
        const days = plan.days || [];
        const weekGroups = groupWeeks(days);
        const vacationGroups = groupVacations(days);

        return `
            <section class="adp-section">
                <div class="adp-section-head">
                    <h2>${esc(team.displayName || team.code)} - ${esc(plan.month)}${personalWorkload ? `<span class="adp-personal-month-capacity" aria-label="Monatsauslastung">${renderCapacity(personalWorkload.monthCount, personalWorkload.monthlyMin, personalWorkload.monthlyMax)}</span>` : ''}</h2>
                    <div class="adp-plan-meta">
                        ${team.settings && team.settings.meetingDay ? `<span class="adp-badge">Treffen ${esc(dateShort(team.settings.meetingDay))}</span>` : ''}
                        ${renderStatus(status, canCoordinate)}
                    </div>
                </div>
                <div class="adp-table-wrap">
                    <table class="adp-table adp-month-table">
                        <thead>
                            <tr>
                                <th scope="col" class="adp-week-column"><span class="adp-visually-hidden">Kalenderwoche</span></th>
                                <th scope="col" class="adp-vacation-column">Urlaub</th>
                                <th scope="col">Tag</th>
                                ${segments.map(segment => `<th scope="col">${esc(segment.label)}<small>${esc(segment.startsAt)}-${esc(segment.endsAt)}</small></th>`).join('')}
                                <th scope="col">Bemerkungen</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${days.map((day, index) => dayRow(day, segments, team, currentUser, canCoordinate, mutable, personalWorkload, weekGroups.get(index), vacationGroups.get(index))).join('')}
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

    function dayRow(day, segments, team, currentUser, canCoordinate, mutable, personalWorkload, weekGroup, vacationGroup) {
        const slotsByKey = {};
        (day.slots || []).forEach(slot => {
            slotsByKey[slot.segmentKey] = slot;
        });

        return `
            <tr${weekGroup?.separated ? ' class="adp-week-start"' : ''}>
                ${renderWeekCell(weekGroup, personalWorkload)}
                ${renderVacationCell(vacationGroup)}
                <th scope="row" class="adp-day">${dayHeader(day)}${renderHints(nonVacationHints(day.hints || []))}</th>
                ${segments.map(segment => slotCell(slotsByKey[segment.key], team, currentUser, canCoordinate, mutable)).join('')}
                <td class="adp-note-cell">
                    <div class="adp-eb-note">${renderDayNoteControl(day, canCoordinate && mutable)}</div>
                    ${renderCandidateNotes(day, segments, mutable)}
                </td>
            </tr>
        `;
    }

    function groupWeeks(days) {
        const groups = new Map();
        let start = 0;
        while (start < days.length) {
            const label = days[start].weekLabel || '';
            let end = start + 1;
            while (end < days.length && (days[end].weekLabel || '') === label) end++;
            groups.set(start, { label, span: end - start, separated: start > 0 });
            start = end;
        }
        return groups;
    }

    function groupVacations(days) {
        const groups = new Map();
        let start = 0;
        while (start < days.length) {
            const hints = vacationHints(days[start].hints || []);
            const key = vacationKey(days[start], hints);
            let end = start + 1;
            while (end < days.length) {
                const nextHints = vacationHints(days[end].hints || []);
                if (vacationKey(days[end], nextHints) !== key) break;
                end++;
            }
            groups.set(start, { hints, span: end - start });
            start = end;
        }
        return groups;
    }

    function vacationKey(day, hints) {
        return `${day.weekLabel || ''}|${hints.map(hint => `${hint.employeeUid || hint.displayName || ''}:${hint.marker || ''}`).join('|')}`;
    }

    function vacationHints(hints) {
        return hints
            .filter(hint => hint.type === 'absence')
            .slice()
            .sort((left, right) => [left.employeeUid || left.displayName || '', left.marker || ''].join('|').localeCompare([right.employeeUid || right.displayName || '', right.marker || ''].join('|')));
    }

    function nonVacationHints(hints) {
        return hints.filter(hint => hint.type !== 'absence');
    }

    function renderVacationCell(group) {
        if (!group) return '';
        if (!group.hints.length) {
            return `<td rowspan="${esc(group.span)}" class="adp-vacation-cell adp-vacation-cell--empty" aria-label="Kein Urlaub"></td>`;
        }
        const entries = group.hints.map(hint => `${hint.marker || 'U'} ${hint.displayName || hint.employeeUid || ''}`);
        return `<td rowspan="${esc(group.span)}" class="adp-vacation-cell" aria-label="Urlaub: ${entries.map(esc).join(', ')}"><div class="adp-vacation-label">${entries.map(entry => `<span class="adp-vacation-entry">${esc(entry)}</span>`).join('')}</div></td>`;
    }

    function renderWeekCell(group, workload) {
        if (!group) return '';
        const label = String(group.label || '');
        const number = label.replace(/^KW\s*/i, '') || '–';
        const week = workload ? (workload.weeks || []).find(item => item.label === label) : null;
        const capacity = weekCapacity(week, workload);
        const statusClass = capacity?.status ? ` adp-capacity--${capacity.status}` : '';
        const capacityLabel = capacity ? `<span>${esc(capacity.text)}</span>` : '';
        const title = capacity ? ` title="${esc(capacity.title)}"` : '';
        return `<th scope="rowgroup" rowspan="${esc(group.span)}" class="adp-week-cell${statusClass}"${title} aria-label="${esc(label || 'Kalenderwoche')}${capacity ? `, ${esc(capacity.title)}` : ''}"><span>KW</span><strong>${esc(number)}</strong>${capacityLabel}</th>`;
    }

    function weekCapacity(week, workload) {
        if (!week || !workload) return null;
        const count = Number(week.count) || 0;
        const minimum = finiteOrNull(workload.weeklyMin);
        const maximum = finiteOrNull(workload.weeklyMax);
        if (!hasCapacityLimits(minimum, maximum)) {
            return { status: null, text: String(count), title: `${count} Schichten` };
        }
        if (minimum !== null && count < minimum) {
            return { status: 'under', text: `<${minimum}`, title: `${count} von mindestens ${minimum}` };
        }
        if (maximum !== null) {
            return { status: count > maximum ? 'over' : 'within', text: `${count}/${maximum}`, title: `${count} von maximal ${maximum}` };
        }
        return { status: 'within', text: String(count), title: `${count} Schichten` };
    }

    function finiteOrNull(value) {
        if (value === null || value === undefined || value === '') return null;
        const number = Number(value);
        return Number.isFinite(number) ? number : null;
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
