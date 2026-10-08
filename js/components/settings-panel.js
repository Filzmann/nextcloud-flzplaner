(function() {
    const { esc, dateShort } = window.FlzPlaner.ui;
    const { renderReadonly, renderEditor } = window.FlzPlaner.shiftSettingsList;

    function render(team) {
        if (!team) {
            return '<p>Kein Assistenznehmer gewählt.</p>';
        }

        const settings = team.settings || {};
        const shifts = settings.shifts || [];
        const personal = team.personalWorkload || {};
        const regular = team.personalRegularShifts || [];
        const regularKeys = new Set(regular.map(rule => `${rule.weekday}|${rule.segmentKey}`));
        const weekdays = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
        const regularForm = team.canSetRegularShifts ? `<section class="flz-planer-personal-settings" aria-labelledby="flz-planer-regular-shifts-heading">
            <h3 id="flz-planer-regular-shifts-heading">Meine regelmäßigen festen Schichten</h3>
            <p>Diese Schichten werden in offenen Monatsplänen automatisch fest eingetragen.</p>
            <form id="personal-regular-shifts-form" class="flz-planer-settings-form"><div class="flz-planer-regular-shifts-grid">
                ${weekdays.map((label,index)=>`<fieldset><legend>${label}</legend>${shifts.filter(shift=>shift.enabled !== false).map(shift=>`<label><input type="checkbox" name="regularShift" value="${esc(`${index+1}|${shift.key}`)}"${regularKeys.has(`${index+1}|${shift.key}`)?' checked':''}> ${esc(shift.label)}</label>`).join('')}</fieldset>`).join('')}
            </div><button type="submit">Regelmäßige Schichten speichern</button></form>
        </section>` : '';
        const personalForm = team.canSetPersonalWorkload ? `
            <section class="flz-planer-personal-settings" aria-labelledby="flz-planer-personal-settings-heading">
                <h3 id="flz-planer-personal-settings-heading">Meine gewünschten Schichten</h3>
                <p>Leere Felder bedeuten, dass keine Grenze gilt.</p>
                <form id="personal-workload-form" class="flz-planer-settings-form">
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
                <section class="flz-planer-section">
                    <div class="flz-planer-section-head">
                        <h2>${esc(team.displayName || team.code)}</h2>
                    </div>
                    <dl class="flz-planer-settings-readonly">
                        <dt>Assistentinnentreffen</dt>
                        <dd>${settings.meetingDay ? esc(dateShort(settings.meetingDay)) : '-'}</dd>
                        <dt>Schichten</dt>
                        <dd>${renderReadonly(shifts)}</dd>
                    </dl>
                    ${regularForm}${personalForm}
                </section>
            `;
        }

        return `
            <section class="flz-planer-section">
                <div class="flz-planer-section-head">
                    <h2>${esc(team.displayName || team.code)}</h2>
                </div>
                <form id="settings-form" class="flz-planer-settings-form">
                    <label>Anzeigename <input name="displayName" type="text" maxlength="255" required value="${esc(team.displayName || team.code)}"></label>
                    <label>Assistentinnentreffen <input name="meetingDay" type="date" value="${esc(settings.meetingDay || '')}"></label>
                    <fieldset>
                        <legend>Schichten</legend>
                        ${renderEditor(shifts)}
                    </fieldset>
                    <button type="submit">Speichern</button>
                </form>
                ${regularForm}${personalForm}
            </section>
        `;
    }

    function limitInput(name, label, value) {
        const normalized = value === null || value === undefined ? '' : String(value);
        return `<label>${label} <input name="${name}" type="number" min="0" max="100" step="1" inputmode="numeric" value="${esc(normalized)}"></label>`;
    }

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.settingsPanel = { render };
})();
