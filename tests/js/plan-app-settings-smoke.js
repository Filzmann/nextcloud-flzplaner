const assert = require('assert');

global.window = { ADPlaner: {} };
require('../../js/modules/plan-app.js');

class ChromeFake {
    constructor() {}
    render() {}
}

class PanelFake {
    constructor() {
        this.panel = { querySelector() { return null; } };
    }
    render() {}
}

class SettingsRepositoryFake {
    constructor() {
        this.saveCalls = 0;
        this.failState = false;
        this.failSave = false;
        this.pendingSave = null;
    }

    async saveSettings() {
        this.saveCalls++;
        if (this.failSave) throw new Error('Speichern fehlgeschlagen');
        if (this.pendingSave) await this.pendingSave;
    }

    deferSave() {
        let resolve;
        this.pendingSave = new Promise(done => { resolve = done; });
        return resolve;
    }

    async state() {
        if (this.failState) throw new Error('Zustand nicht verfügbar');
        return {
            currentUser: { uid: 'test-eb' },
            teams: [{ code: 'A1', displayName: 'Team A1' }],
            organization: {}
        };
    }

    async monthPlan(teamCode, month) {
        return { month, team: { code: teamCode, displayName: 'Team A1' } };
    }
}

function createApp(repository, errors) {
    const app = new window.ADPlaner.PlanApp({
        repository,
        PlanChrome: ChromeFake,
        PlanPanel: PanelFake,
        byId() { return null; },
        esc: String,
        showNotice() {},
        showError(error, fallback) { errors.push({ message: error.message, fallback }); },
        renderMonth() { return ''; },
        renderSettings() { return ''; },
        addShiftRow() {},
        removeShiftRow() {},
        collectShifts() { return []; },
        openAssignmentPicker() {},
    });
    app.state = {
        currentUser: { uid: 'test-eb' },
        teams: [{ code: 'A1', displayName: 'Team A1' }],
        selectedTeamCode: 'A1',
        month: '2026-08',
        activeView: 'settings',
        monthPlan: { month: '2026-08', team: { code: 'A1' } },
        organization: {},
        loading: false,
    };
    return app;
}

const values = {
    displayName: 'Team A1',
    meetingDay: '2026-08-10',
    shifts: [{ key: 'day', label: 'Tag', startsAt: '08:00', endsAt: '16:00', enabled: true }]
};

(async () => {
    const errors = [];
    const repository = new SettingsRepositoryFake();
    const app = createApp(repository, errors);

    repository.failState = true;
    await app.saveSettings(values);
    assert.strictEqual(repository.saveCalls, 1);
    assert.strictEqual(errors.at(-1).fallback, 'Die Einstellungen wurden gespeichert, aber die Ansicht konnte nicht neu geladen werden.');
    assert.strictEqual(app.state.monthPlan, null);

    repository.failState = false;
    repository.failSave = true;
    await app.saveSettings(values);
    assert.strictEqual(repository.saveCalls, 2);
    assert.strictEqual(errors.at(-1).fallback, 'Einstellungen konnten nicht gespeichert werden.');

    repository.failSave = false;
    const resolveSave = repository.deferSave();
    const firstSave = app.saveSettings(values);
    await Promise.resolve();
    const secondSave = app.saveSettings(values);
    await Promise.resolve();
    assert.strictEqual(repository.saveCalls, 3, 'A second submit must not start another settings write while one is pending.');
    resolveSave();
    await Promise.all([firstSave, secondSave]);
    repository.pendingSave = null;
    assert.strictEqual(app.settingsSaving, false);

    repository.failSave = true;
    await app.saveSettings(values);
    assert.strictEqual(repository.saveCalls, 4, 'Saving must be possible again after the pending request completed.');

    console.log('AdPlaner settings workflow smoke test passed.');
})().catch(error => {
    console.error(error);
    process.exit(1);
});
