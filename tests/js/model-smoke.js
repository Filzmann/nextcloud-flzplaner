const assert = require('assert');

global.window = {};

require('../../../localbase/js/models/model.js');

require('../../js/models/assistant.js');
require('../../js/models/shift-candidate.js');
require('../../js/models/shift-definition.js');
require('../../js/models/shift-slot.js');
require('../../js/models/team-settings.js');
require('../../js/models/team.js');
require('../../js/models/day-note.js');

const {
    Assistant,
    DayNote,
    ShiftCandidate,
    ShiftDefinition,
    ShiftSlot,
    Team,
    TeamSettings
} = window.ADPlaner.models;

const assistant = Assistant.get({
    uid: 'anna',
    display_name: 'Anna Assistenz',
    can_receive_shifts: true
});

assert(assistant instanceof Assistant);
assert.strictEqual(assistant.displayName, 'Anna Assistenz');

const candidate = ShiftCandidate.get({
    slot_id: 7,
    assistant_uid: 'anna',
    display_name: 'Anna Assistenz',
    createdByUid: 'internal-coordinator',
    created_at: '2026-08-09T10:00:00Z'
});

assert(candidate instanceof ShiftCandidate);
assert.strictEqual(candidate.uid, 'anna');
assert.strictEqual(Object.hasOwn(candidate, 'createdByUid'), false);
assert.strictEqual(Object.hasOwn(candidate, 'createdAt'), false);
assert.strictEqual(Object.hasOwn(candidate.toArray(), 'createdByUid'), false);
assert.strictEqual(Object.hasOwn(candidate.toArray(), 'createdAt'), false);

const definition = ShiftDefinition.get({
    key: 'day',
    label: 'Tagdienst',
    starts_at: '08:00',
    ends_at: '14:00'
});

assert(definition instanceof ShiftDefinition);
assert.strictEqual(definition.toArray().startsAt, '08:00');

const slot = ShiftSlot.get({
    id: 7,
    segment_key: 'day',
    candidates: [candidate.toArray()]
});

assert(slot instanceof ShiftSlot);
assert.strictEqual(slot.candidates.length, 1);

const team = Team.get({
    code: 'TeamA',
    group_name: 'ad-ASN-TeamA',
    assistants: [assistant.toArray()]
});

assert(team instanceof Team);
assert.strictEqual(team.assistants.length, 1);

assert(TeamSettings.get({ team_code: 'TeamA' }) instanceof TeamSettings);
assert(DayNote.get({ team_code: 'TeamA', note: 'Hinweis' }) instanceof DayNote);

console.log('AdPlaner model smoke test passed.');
