(function() {
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    const { esc, dateShort } = window.FlzPlaner.ui;
    const { renderReadonly, renderEditor } = window.FlzPlaner.shiftSettingsList;

    function render(team) {
        if (!team) {
            return `<p>${translate('flzplaner', 'Kein Assistenznehmer gewählt.')}</p>`;
        }

        const settings = team.settings || {};
        const shifts = settings.shifts || [];
        const personal = team.personalWorkload || {};
        const regular = team.personalRegularShifts || [];
        const regularKeys = new Set(regular.map(rule => `${rule.weekday}|${rule.segmentKey}`));
        const weekdays = [translate('flzplaner', 'Montag'),translate('flzplaner', 'Dienstag'),translate('flzplaner', 'Mittwoch'),translate('flzplaner', 'Donnerstag'),translate('flzplaner', 'Freitag'),translate('flzplaner', 'Samstag'),translate('flzplaner', 'Sonntag')];
        const regularForm = team.canSetRegularShifts ? `<section class="flz-planer-personal-settings" aria-labelledby="flz-planer-regular-shifts-heading">
            <h3 id="flz-planer-regular-shifts-heading">${translate('flzplaner', 'Meine regelmäßigen festen Schichten')}</h3>
            <p>${translate('flzplaner', 'Diese Schichten werden in offenen Monatsplänen automatisch fest eingetragen.')}</p>
            <form id="personal-regular-shifts-form" class="flz-planer-settings-form"><div class="flz-planer-regular-shifts-grid">
                ${weekdays.map((label,index)=>`<fieldset><legend>${label}</legend>${shifts.filter(shift=>shift.enabled !== false).map(shift=>`<label><input type="checkbox" name="regularShift" value="${esc(`${index+1}|${shift.key}`)}"${regularKeys.has(`${index+1}|${shift.key}`)?' checked':''}> ${esc(shift.label)}</label>`).join('')}</fieldset>`).join('')}
            </div><button type="submit">${translate('flzplaner', 'Regelmäßige Schichten speichern')}</button></form>
        </section>` : '';
        const personalForm = team.canSetPersonalWorkload ? `
            <section class="flz-planer-personal-settings" aria-labelledby="flz-planer-personal-settings-heading">
                <h3 id="flz-planer-personal-settings-heading">${translate('flzplaner', 'Meine gewünschten Schichten')}</h3>
                <p>${translate('flzplaner', 'Leere Felder bedeuten, dass keine Grenze gilt.')}</p>
                <form id="personal-workload-form" class="flz-planer-settings-form">
                    <fieldset><legend>${translate('flzplaner', 'Pro Kalenderwoche')}</legend>
                        ${limitInput('weeklyMin', translate('flzplaner', 'Minimum'), personal.weeklyMin)}
                        ${limitInput('weeklyMax', translate('flzplaner', 'Maximum'), personal.weeklyMax)}
                    </fieldset>
                    <fieldset><legend>${translate('flzplaner', 'Pro Monat')}</legend>
                        ${limitInput('monthlyMin', translate('flzplaner', 'Minimum'), personal.monthlyMin)}
                        ${limitInput('monthlyMax', translate('flzplaner', 'Maximum'), personal.monthlyMax)}
                    </fieldset>
                    <button type="submit">${translate('flzplaner', 'Persönliche Grenzen speichern')}</button>
                </form>
            </section>` : '';

        if (!team.canCoordinate) {
            return `
                <section class="flz-planer-section">
                    <div class="flz-planer-section-head">
                        <h2>${esc(team.displayName || team.code)}</h2>
                    </div>
                    <dl class="flz-planer-settings-readonly">
                        <dt>${translate('flzplaner', 'Assistentinnentreffen')}</dt>
                        <dd>${settings.meetingDay ? esc(dateShort(settings.meetingDay)) : '-'}</dd>
                        <dt>${translate('flzplaner', 'Schichten')}</dt>
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
                    <label>${translate('flzplaner', 'Anzeigename')} <input name="displayName" type="text" maxlength="255" required value="${esc(team.displayName || team.code)}"></label>
                    <label>${translate('flzplaner', 'Assistentinnentreffen')} <input name="meetingDay" type="date" value="${esc(settings.meetingDay || '')}"></label>
                    <fieldset>
                        <legend>${translate('flzplaner', 'Schichten')}</legend>
                        ${renderEditor(shifts)}
                    </fieldset>
                    <button type="submit">${translate('flzplaner', 'Speichern')}</button>
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
