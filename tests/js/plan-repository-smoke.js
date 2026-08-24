const assert = require('assert');

const calls = [];
const responses = new Map([
    ['/api/state', {
        currentUser: { uid: 'anna' },
        teams: [{ code: 'TeamA', displayName: 'Team A' }],
        defaultMonth: '2026-07',
        defaultYear: 2026
    }],
    ['/api/teams/TeamA/months/2026-07', {
        team: { code: 'TeamA', displayName: 'Team A' },
        segments: [{ key: 'day', label: 'Tag', startsAt: '08:00', endsAt: '14:00' }],
        days: [{ workDate: '2026-07-01', slots: [{ id: 1, segmentKey: 'day' }] }]
    }]
]);

global.window = {};

require('../../../localbase/js/models/model.js');
require('../../js/models/assistant.js');
require('../../js/models/shift-candidate.js');
require('../../js/models/shift-definition.js');
require('../../js/models/shift-slot.js');
require('../../js/models/team-settings.js');
require('../../js/models/team.js');
require('../../js/models/day-note.js');
require('../../../localbase/js/repositories/repository.js');

window.ADPlaner.api = {
    request(path, options = {}) {
        calls.push({ path, options });

        return Promise.resolve(responses.get(path) || { path, options });
    },
    encode(value) {
        return encodeURIComponent(String(value));
    }
};
require('../../js/repositories/plan-repository.js');

(async () => {
    const { PlanRepository } = window.ADPlaner.repositories;
    const repository = new PlanRepository(window.ADPlaner.api);

    const state = await repository.state();
    const monthPlan = await repository.monthPlan('TeamA', '2026-07');
    await repository.addSelected('TeamA', '2026-07', 1, 'anna');
    await repository.saveDayNote('TeamA', '2026-07', '2026-07-01', 'Hinweis');
    await repository.transitionStatus('TeamA', '2026-07', 'planned');
    await repository.saveSettings('TeamA', 'Team A', '2', [{ key: 'day' }]);

    assert.strictEqual(state.teams[0] instanceof window.ADPlaner.models.Team, true);
    assert.strictEqual(monthPlan.team instanceof window.ADPlaner.models.Team, true);
    assert.strictEqual(monthPlan.segments[0] instanceof window.ADPlaner.models.ShiftDefinition, true);
    assert.strictEqual(monthPlan.days[0].slots[0] instanceof window.ADPlaner.models.ShiftSlot, true);
    assert.deepStrictEqual(calls.map(call => call.path), [
        '/api/state',
        '/api/teams/TeamA/months/2026-07',
        '/api/teams/TeamA/months/2026-07/slots/1/candidates',
        '/api/teams/TeamA/months/2026-07/days/2026-07-01/note',
        '/api/teams/TeamA/months/2026-07/status',
        '/api/teams/TeamA/settings'
    ]);
    assert.strictEqual(calls[2].options.body, '{"targetUid":"anna"}');
    assert.strictEqual(calls[4].options.body, '{"targetStatus":"planned"}');
    assert.strictEqual(calls[5].options.body, '{"displayName":"Team A","meetingDay":"2","shiftsJson":"[{\\"key\\":\\"day\\"}]"}');

    console.log('AdPlaner plan repository smoke test passed.');
})();
