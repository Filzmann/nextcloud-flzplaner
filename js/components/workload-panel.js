(function() {
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    const { esc, dateShort, renderCapacity } = window.FlzPlaner.ui;

    function render(plan) {
        if (!plan || !plan.team) return `<p>${translate('flzplaner', 'Keine Auslastungsdaten verfügbar.')}</p>`;
        const canCoordinate = !!plan.team.canCoordinate;
        if (!canCoordinate) return '';
        const rows = plan.workload || [];
        return `<section class="flz-planer-section flz-planer-workload-view" aria-labelledby="flz-planer-workload-heading">
            <div class="flz-planer-section-head"><h2 id="flz-planer-workload-heading">${translate('flzplaner', 'Teamauslastung')} · ${esc(plan.month)}</h2></div>
            ${renderOverview(rows)}
            ${renderProposal(plan, rows)}
        </section>`;
    }

    function renderOverview(rows) {
        if (!rows.length) return `<p>${translate('flzplaner', 'Noch keine Auslastungsdaten vorhanden.')}</p>`;
        return `<div class="flz-planer-table-wrap"><table class="flz-planer-table flz-planer-workload-table"><thead><tr><th scope="col">${translate('flzplaner', 'Person')}</th><th scope="col">${translate('flzplaner', 'Monat')}</th><th scope="col">${translate('flzplaner', 'Kalenderwochen')}</th></tr></thead><tbody>${rows.map(row => `<tr><th scope="row">${esc(row.displayName || row.uid)}</th><td>${renderCapacity(row.monthCount, row.monthlyMin, row.monthlyMax)}</td><td>${renderWeeks(row)}</td></tr>`).join('')}</tbody></table></div>`;
    }

    function renderWeeks(row) {
        const weeks = row.weeks || [];
        if (!weeks.length) return '–';
        return `<span class="flz-planer-week-capacities">${weeks.map(week => `<span class="flz-planer-week-capacity"><span>${esc(week.label)}</span>${renderCapacity(week.count, row.weeklyMin, row.weeklyMax)}</span>`).join('')}</span>`;
    }

    function renderProposal(plan, workload) {
        const workloadByUid = new Map(workload.map(row => [row.uid, row]));
        const segmentLabels = Object.fromEntries((plan.segments || []).map(segment => [segment.key, segment.label]));
        const rows = [];
        let emptyCount = 0;
        let unavailableCount = 0;
        for (const day of plan.days || []) {
            for (const slot of day.slots || []) {
                const slotCandidates = slot.candidates || [];
                const candidates = slotCandidates.filter(candidate => {
                    if (!candidate.unavailable) return true;
                    unavailableCount += 1;
                    return false;
                }).sort((left, right) => compareCandidates(left, right, workloadByUid));
                if (!candidates.length) {
                    if (!slotCandidates.length) emptyCount += 1;
                    continue;
                }
                rows.push(`<tr><th scope="row">${esc(dateShort(day.date))}</th><td>${esc(segmentLabels[slot.segmentKey] || slot.segmentKey || translate('flzplaner', 'Schicht'))}</td><td><ol class="flz-planer-proposal-candidates">${candidates.map(candidate => proposalCandidate(candidate, workloadByUid.get(candidate.uid))).join('')}</ol></td></tr>`);
            }
        }
        const emptySummary = emptyCount ? `<span class="flz-planer-muted flz-planer-empty-summary">${esc(emptyCount)} ${emptyCount === 1 ? translate('flzplaner', 'Schicht') : translate('flzplaner', 'Schichten')} ${translate('flzplaner', 'ohne Wünsche')}</span>` : '';
        const unavailableSummary = unavailableCount ? `<span class="flz-planer-vacation-summary">${esc(unavailableCount)} ${unavailableCount === 1 ? translate('flzplaner', 'Urlaubskonflikt') : translate('flzplaner', 'Urlaubskonflikte')} ${translate('flzplaner', 'nicht vorgeschlagen')}</span>` : '';
        const table = rows.length ? `<div class="flz-planer-table-wrap"><table class="flz-planer-table"><thead><tr><th scope="col">${translate('flzplaner', 'Tag')}</th><th scope="col">${translate('flzplaner', 'Schicht')}</th><th scope="col">${translate('flzplaner', 'Reihenfolge')}</th></tr></thead><tbody>${rows.join('')}</tbody></table></div>` : `<p class="flz-planer-muted">${translate('flzplaner', 'Noch keine Wünsche für einen Vorschlag vorhanden.')}</p>`;
        return `<section class="flz-planer-plan-proposal" aria-labelledby="flz-planer-proposal-heading"><div class="flz-planer-proposal-head"><h3 id="flz-planer-proposal-heading">${translate('flzplaner', 'Grober Planvorschlag')}</h3><span>${unavailableSummary}${emptySummary}</span></div><details class="flz-planer-proposal-help"><summary>${translate('flzplaner', 'Sortierung')}</summary><p>${translate('flzplaner', 'Urlaub wird nicht vorgeschlagen. Feste Schichten bleiben gesetzt. Danach folgen Lieblingsschichten, normale Wünsche und zuletzt Notfallschichten; bei gleicher Präferenz wird die geringere Auslastung bevorzugt.')}</p></details>${table}</section>`;
    }

    function proposalCandidate(candidate, workload) {
        const preference = normalizedPreference(candidate.preference);
        const marker = candidate.fixed ? `<span title="${translate('flzplaner', 'Feste Schicht')}" aria-label="${translate('flzplaner', 'Feste Schicht')}">🔒</span> ` : (preference === 'favorite' ? `<span title="${translate('flzplaner', 'Lieblingsschicht')}" aria-label="${translate('flzplaner', 'Lieblingsschicht')}">★</span> ` : (preference === 'emergency' ? `<span title="${translate('flzplaner', 'Nur wenn sonst niemand kann')}" aria-label="${translate('flzplaner', 'Nur wenn sonst niemand kann')}">🛟</span> ` : ''));
        const load = workload ? ` ${renderCapacity(workload.monthCount, workload.monthlyMin, workload.monthlyMax)}` : '';
        return `<li data-preference="${preference}">${marker}${esc(candidate.displayName || candidate.uid)}${load}</li>`;
    }

    function compareCandidates(left, right, workloadByUid) {
        if (!!left.fixed !== !!right.fixed) return left.fixed ? -1 : 1;
        const preferenceOrder = { favorite: 0, neutral: 1, emergency: 2 };
        const preferenceDifference = preferenceOrder[normalizedPreference(left.preference)] - preferenceOrder[normalizedPreference(right.preference)];
        if (preferenceDifference !== 0) return preferenceDifference;
        const capacityDifference = capacityRank(workloadByUid.get(left.uid)) - capacityRank(workloadByUid.get(right.uid));
        if (capacityDifference !== 0) return capacityDifference;
        return String(left.displayName || left.uid).localeCompare(String(right.displayName || right.uid), 'de');
    }

    function capacityRank(row) {
        if (!row) return 1;
        const count = Number(row.monthCount) || 0;
        const minimum = finiteOrNull(row.monthlyMin);
        const maximum = finiteOrNull(row.monthlyMax);
        if (minimum !== null && count < minimum) return -1 + count / Math.max(minimum, 1);
        if (maximum !== null && count > maximum) return 2 + count / Math.max(maximum, 1);
        return maximum !== null && maximum > 0 ? count / maximum : 1;
    }

    function normalizedPreference(value) {
        return value === 'favorite' || value === 'emergency' ? value : 'neutral';
    }

    function finiteOrNull(value) {
        if (value === null || value === undefined || value === '') return null;
        const number = Number(value);
        return Number.isFinite(number) ? number : null;
    }

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.workloadPanel = { render };
})();
