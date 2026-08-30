(function() {
    const { esc, dateShort } = window.ADPlaner.ui;
    const { renderReadonly, renderEditor } = window.ADPlaner.shiftSettingsList;

    function render(team) {
        if (!team) {
            return '<p>Kein Assistenznehmer gewählt.</p>';
        }

        const settings = team.settings || {};
        const shifts = settings.shifts || [];
        const personal = team.personalWorkload || {};
        const personalForm = team.canSetPersonalWorkload ? `
            <section class="adp-personal-settings" aria-labelledby="adp-personal-settings-heading">
                <h3 id="adp-personal-settings-heading">Meine gewünschten Schichten</h3>
                <p>Leere Felder bedeuten, dass keine Grenze gilt.</p>
                <form id="personal-workload-form" class="adp-settings-form">
                    <fieldset><legend>Pro Kalenderwoche</legend>
                        ${limitInput('weeklyMin', 'Minimum', personal.weeklyMin)}
                        ${limitInput('weeklyMax', 'Maximum', personal.weeklyMax)}
                    </fieldset>
                    <fieldset><legend>Pro Monat</legend>
                        ${limitInput('monthlyMin', 'Minimum', personal.monthlyMin)}
                        ${limitInput('monthlyMax', 'Maximum', personal.monthlyMax)}
                    </fieldset>
                    <button type="submit">Persönliche Grenzen speichern</button>
                </form>
            </section>` : '';

        if (!team.canCoordinate) {
            return `
                <section class="adp-section">
                    <div class="adp-section-head">
                        <h2>${esc(team.displayName || team.code)}</h2>
                    </div>
                    <dl class="adp-settings-readonly">
                        <dt>Assistentinnentreffen</dt>
                        <dd>${settings.meetingDay ? esc(dateShort(settings.meetingDay)) : '-'}</dd>
                        <dt>Schichten</dt>
                        <dd>${renderReadonly(shifts)}</dd>
                    </dl>
                    ${personalForm}
                </section>
            `;
        }

        return `
            <section class="adp-section">
                <div class="adp-section-head">
                    <h2>${esc(team.displayName || team.code)}</h2>
                </div>
                <form id="settings-form" class="adp-settings-form">
                    <label>Anzeigename <input name="displayName" type="text" maxlength="255" required value="${esc(team.displayName || team.code)}"></label>
                    <label>Assistentinnentreffen <input name="meetingDay" type="date" value="${esc(settings.meetingDay || '')}"></label>
                    <fieldset>
                        <legend>Schichten</legend>
                        ${renderEditor(shifts)}
                    </fieldset>
                    <button type="submit">Speichern</button>
                </form>
                ${personalForm}
            </section>
        `;
    }

    function limitInput(name, label, value) {
        const normalized = value === null || value === undefined ? '' : String(value);
        return `<label>${label} <input name="${name}" type="number" min="0" max="100" step="1" inputmode="numeric" value="${esc(normalized)}"></label>`;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.settingsPanel = { render };
})();
