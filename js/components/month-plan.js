(function() {
    const { esc, dateShort, dayHeader, renderCapacity, hasCapacityLimits } = window.FlzPlaner.ui;
    const { render: renderAssignmentControl } = window.FlzPlaner.assignmentControl;
    const { render: renderCandidateChip } = window.FlzPlaner.candidateChip;
    const { render: renderDayNoteControl } = window.FlzPlaner.dayNoteControl;

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
            <section class="flz-planer-section">
                <div class="flz-planer-section-head">
                    <h2>${esc(team.displayName || team.code)} - ${esc(plan.month)}${personalWorkload ? `<span class="flz-planer-personal-month-capacity" aria-label="Monatsauslastung">${renderCapacity(personalWorkload.monthCount, personalWorkload.monthlyMin, personalWorkload.monthlyMax)}</span>` : ''}</h2>
                    <div class="flz-planer-plan-meta">
                        ${team.settings && team.settings.meetingDay ? `<span class="flz-planer-badge">Treffen ${esc(dateShort(team.settings.meetingDay))}</span>` : ''}
                        ${renderStatus(status, canCoordinate)}
                    </div>
                </div>
                <div class="flz-planer-desktop-plan">
                    <div class="flz-planer-table-wrap">
                        <table class="flz-planer-table flz-planer-month-table">
                        <thead>
                            <tr>
                                <th scope="col" class="flz-planer-week-column"><span class="flz-planer-visually-hidden">Kalenderwoche</span></th>
                                <th scope="col" class="flz-planer-vacation-column">Urlaub</th>
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
                </div>
                <div class="flz-planer-mobile-plan" role="list" aria-label="Wunschplan nach Tagen">
                    ${days.map(day => mobileDay(day, segments, team, currentUser, canCoordinate, mutable)).join('')}
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
        return `<div class="flz-planer-plan-status" data-plan-status="${esc(status)}">
            <span class="flz-planer-badge">Status: ${esc(labels[status] || status)}</span>
            ${canCoordinate ? (actions[status] || []).map(([target, label]) => `<button type="button" class="flz-planer-small" data-action="transition-status" data-target-status="${esc(target)}">${esc(label)}</button>`).join('') : ''}
        </div>`;
    }

    function dayRow(day, segments, team, currentUser, canCoordinate, mutable, personalWorkload, weekGroup, vacationGroup) {
        const slotsByKey = {};
        (day.slots || []).forEach(slot => {
            slotsByKey[slot.segmentKey] = slot;
        });

        return `
            <tr${weekGroup?.separated ? ' class="flz-planer-week-start"' : ''}>
                ${renderWeekCell(weekGroup, personalWorkload)}
                ${renderVacationCell(vacationGroup)}
                <th scope="row" class="flz-planer-day">${dayHeader(day)}${renderHints(nonVacationHints(day.hints || []))}</th>
                ${segments.map(segment => slotCell(slotsByKey[segment.key], team, currentUser, canCoordinate, mutable)).join('')}
                <td class="flz-planer-note-cell">
                    <div class="flz-planer-eb-note">${renderDayNoteControl(day, canCoordinate && mutable)}</div>
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
            return `<td rowspan="${esc(group.span)}" class="flz-planer-vacation-cell flz-planer-vacation-cell--empty" aria-label="Kein Urlaub"></td>`;
        }
        const entries = group.hints.map(hint => `${hint.marker || 'U'} ${hint.displayName || hint.employeeUid || ''}`);
        return `<td rowspan="${esc(group.span)}" class="flz-planer-vacation-cell" aria-label="Urlaub: ${entries.map(esc).join(', ')}"><div class="flz-planer-vacation-label">${entries.map(entry => `<span class="flz-planer-vacation-entry">${esc(entry)}</span>`).join('')}</div></td>`;
    }

    function renderWeekCell(group, workload) {
        if (!group) return '';
        const label = String(group.label || '');
        const number = label.replace(/^KW\s*/i, '') || '–';
        const week = workload ? (workload.weeks || []).find(item => item.label === label) : null;
        const capacity = weekCapacity(week, workload);
        const statusClass = capacity?.status ? ` flz-planer-capacity--${capacity.status}` : '';
        const capacityLabel = capacity ? `<span>${esc(capacity.text)}</span>` : '';
        const title = capacity ? ` title="${esc(capacity.title)}"` : '';
        return `<th scope="rowgroup" rowspan="${esc(group.span)}" class="flz-planer-week-cell${statusClass}"${title} aria-label="${esc(label || 'Kalenderwoche')}${capacity ? `, ${esc(capacity.title)}` : ''}"><span>KW</span><strong>${esc(number)}</strong>${capacityLabel}</th>`;
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
        return entries.length ? `<div class="flz-planer-shift-notes">${entries.join('')}</div>` : '';
    }

    function renderCandidateNote(candidate, slotId, shiftLabel, editable) {
        const displayName = candidate.displayName || candidate.uid;
        const preference = ['favorite', 'emergency'].includes(candidate.preference) ? candidate.preference : 'neutral';
        const note = candidate.note || '';
        return `<div class="flz-planer-shift-note${note ? '' : ' flz-planer-shift-note--empty'}" data-candidate-note-entry data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}" data-current-preference="${esc(preference)}">
            ${note ? `<div class="flz-planer-shift-note-display"><p><strong>${esc(displayName)}, ${esc(shiftLabel)}:</strong> ${esc(note)}</p>${editable ? `<div class="flz-planer-shift-note-actions"><button type="button" class="flz-planer-icon-button" aria-label="Anmerkung bearbeiten" title="Anmerkung bearbeiten" data-action="open-candidate-note-editor" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">✎</button><button type="button" class="flz-planer-icon-button" aria-label="Anmerkung löschen" title="Anmerkung löschen" data-action="delete-candidate-note" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">&times;</button></div>` : ''}</div>` : ''}
            ${editable ? `<div class="flz-planer-shift-note-editor" hidden><label>Anmerkung <textarea maxlength="500" rows="2" data-candidate-note>${esc(note)}</textarea></label><div><button type="button" class="flz-planer-small" data-action="save-candidate-note" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">Speichern</button><button type="button" class="flz-planer-small" data-action="close-candidate-note-editor" data-slot-id="${esc(slotId)}" data-target-uid="${esc(candidate.uid)}">Abbrechen</button></div></div>` : ''}
        </div>`;
    }

    function renderHints(hints) {
        if (!hints.length) return '';
        return `<div class="flz-planer-day-hints" aria-label="Planungshinweise">${hints.map(hint =>
            `<span class="flz-planer-hint" title="${esc(hint.label || 'Hinweis')}">${esc(hint.marker || '•')} ${esc(hint.displayName || hint.employeeUid || '')}</span>`
        ).join('')}</div>`;
    }

    function slotCell(slot, team, currentUser, canCoordinate, mutable) {
        if (!slot) {
            return '<td class="flz-planer-empty"></td>';
        }

        return `<td>${slotContents(slot, team, currentUser, canCoordinate, mutable, 'desktop')}</td>`;
    }

    function slotContents(slot, team, currentUser, canCoordinate, mutable, surface) {

        const candidates = slot.candidates || [];
        const selfUid = currentUser && currentUser.uid ? currentUser.uid : '';
        const hasSelf = candidates.some(candidate => candidate.uid === selfUid);
        const selfAction = mutable && !canCoordinate && !hasSelf && !slot.selfUnavailable
            ? `<button type="button" class="flz-planer-small" data-action="add-self" data-slot-id="${esc(slot.id)}">+ ich</button>`
            : '';

        return `
                <div class="flz-planer-candidates">
                    ${candidates.map(candidate => renderCandidateChip(candidate, canCoordinate, slot.id, mutable)).join('')}
                </div>
                ${renderFixedConflict(slot, candidates, canCoordinate, mutable)}
                <div class="flz-planer-cell-actions">
                    ${selfAction}
                    ${slot.selfUnavailable && !canCoordinate ? '<span class="flz-planer-badge">Nicht verfügbar</span>' : ''}
                    ${canCoordinate && mutable ? renderAssignmentControl(slot, team, candidates, surface) : ''}
                </div>
        `;
    }

    function mobileDay(day, segments, team, currentUser, canCoordinate, mutable) {
        const slotsByKey = Object.fromEntries((day.slots || []).map(slot => [slot.segmentKey, slot]));
        return `<article class="flz-planer-mobile-day" role="listitem">
            <header class="flz-planer-mobile-day-head">
                <h3>${dayHeader(day)}<small>${esc(day.date)}</small></h3>
                <span class="flz-planer-badge">${esc(day.weekLabel || 'Kalenderwoche')}</span>
            </header>
            ${renderHints(day.hints || [])}
            <div class="flz-planer-mobile-shifts">
                ${segments.map(segment => mobileShift(day, segment, slotsByKey[segment.key], team, currentUser, canCoordinate, mutable)).join('')}
            </div>
            <section class="flz-planer-mobile-notes" aria-label="Bemerkungen">
                <div class="flz-planer-eb-note">${renderDayNoteControl(day, canCoordinate && mutable)}</div>
                ${renderCandidateNotes(day, segments, mutable)}
            </section>
        </article>`;
    }

    function mobileShift(day, segment, slot, team, currentUser, canCoordinate, mutable) {
        const headingId = `flz-planer-mobile-shift-${esc(day.date)}-${esc(segment.key)}`;
        return `<section class="flz-planer-mobile-shift" aria-labelledby="${headingId}">
            <h4 id="${headingId}">${esc(segment.label)} <small>${esc(segment.startsAt)}–${esc(segment.endsAt)}</small></h4>
            ${slot ? slotContents(slot, team, currentUser, canCoordinate, mutable, 'mobile') : '<p>Keine Schicht angelegt.</p>'}
        </section>`;
    }

    function renderFixedConflict(slot,candidates,canCoordinate,mutable) {
        const conflict=slot.fixedConflict;
        if (!conflict) return '';
        const labels=(conflict.candidateUids||[]).map(uid=>candidates.find(candidate=>candidate.uid===uid)?.displayName||uid);
        return `<div class="flz-planer-fixed-conflict" role="alert"><strong>Konflikt bei festen Schichten</strong><span>${labels.map(esc).join(', ')}</span>${conflict.status==='escalated'?'<span class="flz-planer-badge">An EB weitergegeben</span>':''}${mutable&&conflict.canReport?`<button type="button" class="flz-planer-small" data-action="report-fixed-conflict" data-slot-id="${esc(slot.id)}">An EB weitergeben</button>`:''}${mutable&&canCoordinate&&conflict.canResolve?(conflict.candidateUids||[]).map(uid=>`<button type="button" class="flz-planer-small" data-action="resolve-fixed-conflict" data-slot-id="${esc(slot.id)}" data-kept-uid="${esc(uid)}">${esc(candidates.find(candidate=>candidate.uid===uid)?.displayName||uid)} fest behalten</button>`).join(''):''}</div>`;
    }

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.monthPlan = { render };
})();
