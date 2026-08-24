const assert = require('assert');

global.window = {};

require('../../../localbase/js/ui/ui.js');
require('../../js/modules/ui.js');
require('../../js/components/candidate-chip.js');
require('../../js/components/day-note-control.js');
require('../../js/components/assignment-control.js');
require('../../js/components/month-plan.js');

const { monthPlan } = window.ADPlaner;

const basePlan = {
    month: '2026-07',
    status: 'draft',
    segments: [
        { key: 'early', label: 'Früh <A>', startsAt: '08:00', endsAt: '14:00' },
        { key: 'late', label: 'Spät', startsAt: '14:00', endsAt: '20:00' }
    ],
    days: [
        {
            date: '2026-07-01',
            dayOfMonth: 1,
            weekday: 3,
            note: '<Hinweis>',
            hints: [
                { employeeUid: 'assistant-a', displayName: 'Assistant <A>', marker: 'U?', label: 'Urlaub', blocks: false },
                { employeeUid: 'assistant-b', displayName: 'Assistant B', marker: 'K', label: 'Termin', blocks: false }
            ],
            slots: [
                { id: 10, segmentKey: 'early', candidates: [] },
                {
                    id: 11,
                    segmentKey: 'late',
                    candidates: [
                        { uid: 'assistant-a', displayName: 'Assistant A', isSelf: true }
                    ]
                }
            ]
        }
    ]
};

const assistantHtml = monthPlan.render({
    ...basePlan,
    team: {
        code: 'A1',
        displayName: 'Team <A1>',
        canCoordinate: false,
        settings: { meetingDay: '2026-07-15' },
        assistants: []
    }
}, { uid: 'assistant-a' });

assert(assistantHtml.includes('Team &lt;A1&gt; - 2026-07'));
assert(!assistantHtml.includes('Team <A1>'));
assert(assistantHtml.includes('Treffen 15.07.'));
assert(assistantHtml.includes('Früh &lt;A&gt;'));
assert(assistantHtml.includes('<th scope="col">Tag</th>'), 'Month-plan column headings need an explicit scope.');
assert(assistantHtml.includes('<th scope="row" class="adp-day">'), 'Each planning day needs an explicit row heading.');
assert(assistantHtml.includes('Mi<span>1</span>'));
assert(assistantHtml.includes('data-action="add-self" data-slot-id="10"'));
assert(!assistantHtml.includes('data-action="add-self" data-slot-id="11"'));
assert(!assistantHtml.includes('adp-assignment-control'));
assert(!assistantHtml.includes('data-action="transition-status"'));
assert(assistantHtml.includes('<span class="adp-note-text">&lt;Hinweis&gt;</span>'));
assert(!assistantHtml.includes('<Hinweis>'));
assert(assistantHtml.includes('U? Assistant &lt;A&gt;'));
assert(assistantHtml.includes('K Assistant B'));
assert(!assistantHtml.includes('Assistant <A>'));

const ebHtml = monthPlan.render({
    ...basePlan,
    team: {
        code: 'A1',
        displayName: 'Team A1',
        canCoordinate: true,
        settings: {},
        assistants: [
            { uid: 'assistant-a', displayName: 'Assistant A', canReceiveShifts: true },
            { uid: 'assistant-b', displayName: 'Assistant B', canReceiveShifts: true }
        ]
    }
}, { uid: 'eb' });

assert(!ebHtml.includes('data-action="add-self"'));
assert(ebHtml.includes('adp-assignment-control'));
assert(ebHtml.includes('data-action="remove-candidate" data-slot-id="11" data-target-uid="assistant-a"'));
assert(ebHtml.includes('aria-label="Assistant A entfernen"'));
assert(ebHtml.includes('<textarea rows="2" maxlength="2000" aria-label="Bemerkung für 01.07." data-note-date="2026-07-01">&lt;Hinweis&gt;</textarea>'));
assert(ebHtml.includes('aria-label="Bemerkung für 01.07."'), 'The editable day note needs its own accessible name.');
assert(ebHtml.includes('aria-label="Bemerkung für 01.07. speichern"'));
assert(ebHtml.includes('data-action="add-selected" data-slot-id="10" data-target-uid="assistant-b"'));
assert(ebHtml.includes('Entwurf'));
assert(ebHtml.includes('data-action="transition-status" data-target-status="planned"'));

const approvedHtml = monthPlan.render({
    ...basePlan,
    status: 'approved',
    team: {
        code: 'A1',
        displayName: 'Team A1',
        canCoordinate: true,
        settings: {},
        assistants: [{ uid: 'assistant-a', displayName: 'Assistant A', canReceiveShifts: true }]
    }
}, { uid: 'eb' });
assert(approvedHtml.includes('Genehmigt'));
assert(approvedHtml.includes('data-action="transition-status" data-target-status="planned"'));
assert(!approvedHtml.includes('adp-assignment-control'));
assert(!approvedHtml.includes('data-action="remove-candidate"'));
assert(!approvedHtml.includes('<textarea'));

const unknownStatusHtml = monthPlan.render({
    ...basePlan,
    status: 'unexpected',
    team: {
        code: 'A1',
        displayName: 'Team A1',
        canCoordinate: true,
        settings: {},
        assistants: [{ uid: 'assistant-a', displayName: 'Assistant A', canReceiveShifts: true }]
    }
}, { uid: 'eb' });
assert(!unknownStatusHtml.includes('adp-assignment-control'), 'Unknown plan statuses must fail closed in the assignment UI.');
assert(!unknownStatusHtml.includes('data-action="remove-candidate"'), 'Unknown plan statuses must not expose candidate mutations.');
assert(!unknownStatusHtml.includes('<textarea'), 'Unknown plan statuses must not expose day-note mutations.');

console.log('AdPlaner month plan smoke test passed.');
