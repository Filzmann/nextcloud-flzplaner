(function() {
    const { esc, dateShort } = window.ADPlaner.ui;
    const { renderReadonly, renderEditor } = window.ADPlaner.shiftSettingsList;

    function render(team) {
        if (!team) {
            return '<p>Kein Assistenznehmer gewählt.</p>';
        }

        const settings = team.settings || {};
        const shifts = settings.shifts || [];

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
            </section>
        `;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.settingsPanel = { render };
})();
