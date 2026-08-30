(function() {
    const { esc, dateShort } = window.ADPlaner.ui;

    function render(plan) {
        if (!plan || !plan.team) return '<p>Keine Auslastungsdaten verfügbar.</p>';
        const rows = plan.workload || [];
        const canCoordinate = !!plan.team.canCoordinate;
        return `<section class="adp-section adp-workload-view" aria-labelledby="adp-workload-heading">
            <div class="adp-section-head"><h2 id="adp-workload-heading">${canCoordinate ? 'Auslastung des Teams' : 'Meine Auslastung'} – ${esc(plan.month)}</h2></div>
            ${renderOverview(rows)}
            ${canCoordinate ? renderProposal(plan, rows) : ''}
        </section>`;
    }

    function renderOverview(rows) {
        if (!rows.length) return '<p>Noch keine Auslastungsdaten vorhanden.</p>';
        return `<div class="adp-table-wrap"><table class="adp-table adp-workload-table"><thead><tr><th scope="col">Person</th><th scope="col">Monat</th><th scope="col">Kalenderwochen</th></tr></thead><tbody>${rows.map(row => `<tr><th scope="row">${esc(row.displayName || row.uid)}</th><td>${capacity(row.monthCount, row.monthlyMin, row.monthlyMax)}</td><td>${(row.weeks || []).map(week => `${esc(week.label)}: ${capacity(week.count, row.weeklyMin, row.weeklyMax)}`).join('<br>') || '–'}</td></tr>`).join('')}</tbody></table></div>`;
    }

    function capacity(countValue, minimumValue, maximumValue) {
        const count = Number(countValue) || 0;
        const minimum = finiteOrNull(minimumValue);
        const maximum = finiteOrNull(maximumValue);
        if (minimum !== null && count < minimum) {
            return `<span class="adp-capacity adp-capacity--under" title="${esc(count)} von mindestens ${esc(minimum)}">&lt;${esc(minimum)}</span>`;
        }
        if (maximum !== null) {
            const status = count > maximum ? 'over' : 'within';
            return `<span class="adp-capacity adp-capacity--${status}" title="${esc(count)} von maximal ${esc(maximum)}">${esc(count)}/${esc(maximum)}</span>`;
        }
        return `<span class="adp-capacity adp-capacity--within" title="${esc(count)} Schichten">${esc(count)}</span>`;
    }

    function renderProposal(plan, workload) {
        const workloadByUid = new Map(workload.map(row => [row.uid, row]));
        const segmentLabels = Object.fromEntries((plan.segments || []).map(segment => [segment.key, segment.label]));
        const rows = [];
        for (const day of plan.days || []) {
            for (const slot of day.slots || []) {
                const candidates = [...(slot.candidates || [])].sort((left, right) => compareCandidates(left, right, workloadByUid));
                rows.push(`<tr><th scope="row">${esc(dateShort(day.date))}</th><td>${esc(segmentLabels[slot.segmentKey] || slot.segmentKey || 'Schicht')}</td><td>${candidates.length ? `<ol class="adp-proposal-candidates">${candidates.map(candidate => proposalCandidate(candidate, workloadByUid.get(candidate.uid))).join('')}</ol>` : '<span class="adp-muted">Keine Wünsche</span>'}</td></tr>`);
            }
        }
        return `<section class="adp-plan-proposal" aria-labelledby="adp-proposal-heading"><h3 id="adp-proposal-heading">Grober Planvorschlag</h3><p>Feste Schichten bleiben gesetzt. Danach folgen vorhandene Wünsche: Lieblingsschichten zuerst, Notfallschichten zuletzt; bei gleicher Präferenz wird die geringere Auslastung bevorzugt.</p><div class="adp-table-wrap"><table class="adp-table"><thead><tr><th scope="col">Tag</th><th scope="col">Schicht</th><th scope="col">Reihenfolge</th></tr></thead><tbody>${rows.join('')}</tbody></table></div></section>`;
    }

    function proposalCandidate(candidate, workload) {
        const preference = normalizedPreference(candidate.preference);
        const marker = candidate.fixed ? '<span title="Feste Schicht" aria-label="Feste Schicht">🔒</span> ' : (preference === 'favorite' ? '<span title="Lieblingsschicht" aria-label="Lieblingsschicht">★</span> ' : (preference === 'emergency' ? '<span title="Nur wenn sonst niemand kann" aria-label="Nur wenn sonst niemand kann">🛟</span> ' : ''));
        const load = workload ? ` ${capacity(workload.monthCount, workload.monthlyMin, workload.monthlyMax)}` : '';
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
