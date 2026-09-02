(function() {
    const { esc, dateShort, renderCapacity } = window.ADPlaner.ui;

    function render(plan) {
        if (!plan || !plan.team) return '<p>Keine Auslastungsdaten verfügbar.</p>';
        const canCoordinate = !!plan.team.canCoordinate;
        if (!canCoordinate) return '';
        const rows = plan.workload || [];
        return `<section class="adp-section adp-workload-view" aria-labelledby="adp-workload-heading">
            <div class="adp-section-head"><h2 id="adp-workload-heading">Teamauslastung · ${esc(plan.month)}</h2></div>
            ${renderOverview(rows)}
            ${renderProposal(plan, rows)}
        </section>`;
    }

    function renderOverview(rows) {
        if (!rows.length) return '<p>Noch keine Auslastungsdaten vorhanden.</p>';
        return `<div class="adp-table-wrap"><table class="adp-table adp-workload-table"><thead><tr><th scope="col">Person</th><th scope="col">Monat</th><th scope="col">Kalenderwochen</th></tr></thead><tbody>${rows.map(row => `<tr><th scope="row">${esc(row.displayName || row.uid)}</th><td>${renderCapacity(row.monthCount, row.monthlyMin, row.monthlyMax)}</td><td>${renderWeeks(row)}</td></tr>`).join('')}</tbody></table></div>`;
    }

    function renderWeeks(row) {
        const weeks = row.weeks || [];
        if (!weeks.length) return '–';
        return `<span class="adp-week-capacities">${weeks.map(week => `<span class="adp-week-capacity"><span>${esc(week.label)}</span>${renderCapacity(week.count, row.weeklyMin, row.weeklyMax)}</span>`).join('')}</span>`;
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
                rows.push(`<tr><th scope="row">${esc(dateShort(day.date))}</th><td>${esc(segmentLabels[slot.segmentKey] || slot.segmentKey || 'Schicht')}</td><td><ol class="adp-proposal-candidates">${candidates.map(candidate => proposalCandidate(candidate, workloadByUid.get(candidate.uid))).join('')}</ol></td></tr>`);
            }
        }
        const emptySummary = emptyCount ? `<span class="adp-muted adp-empty-summary">${esc(emptyCount)} ${emptyCount === 1 ? 'Schicht' : 'Schichten'} ohne Wünsche</span>` : '';
        const unavailableSummary = unavailableCount ? `<span class="adp-vacation-summary">${esc(unavailableCount)} ${unavailableCount === 1 ? 'Urlaubskonflikt' : 'Urlaubskonflikte'} nicht vorgeschlagen</span>` : '';
        const table = rows.length ? `<div class="adp-table-wrap"><table class="adp-table"><thead><tr><th scope="col">Tag</th><th scope="col">Schicht</th><th scope="col">Reihenfolge</th></tr></thead><tbody>${rows.join('')}</tbody></table></div>` : '<p class="adp-muted">Noch keine Wünsche für einen Vorschlag vorhanden.</p>';
        return `<section class="adp-plan-proposal" aria-labelledby="adp-proposal-heading"><div class="adp-proposal-head"><h3 id="adp-proposal-heading">Grober Planvorschlag</h3><span>${unavailableSummary}${emptySummary}</span></div><details class="adp-proposal-help"><summary>Sortierung</summary><p>Urlaub wird nicht vorgeschlagen. Feste Schichten bleiben gesetzt. Danach folgen Lieblingsschichten, normale Wünsche und zuletzt Notfallschichten; bei gleicher Präferenz wird die geringere Auslastung bevorzugt.</p></details>${table}</section>`;
    }

    function proposalCandidate(candidate, workload) {
        const preference = normalizedPreference(candidate.preference);
        const marker = candidate.fixed ? '<span title="Feste Schicht" aria-label="Feste Schicht">🔒</span> ' : (preference === 'favorite' ? '<span title="Lieblingsschicht" aria-label="Lieblingsschicht">★</span> ' : (preference === 'emergency' ? '<span title="Nur wenn sonst niemand kann" aria-label="Nur wenn sonst niemand kann">🛟</span> ' : ''));
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

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.workloadPanel = { render };
})();
