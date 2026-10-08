const assert = require('assert');

global.window = {};

require('../../../localbase/js/ui/ui.js');
require('../../js/modules/ui.js');
require('../../js/components/candidate-chip.js');
require('../../js/components/day-note-control.js');
require('../../js/components/assignment-control.js');
require('../../js/components/month-plan.js');

const { monthPlan, candidateChip } = window.FlzPlaner;

const neutralPreferenceHtml = candidateChip.render({
    uid: 'assistant-neutral',
    displayName: 'Neutral',
    isSelf: true,
    preference: 'neutral',
    note: ''
}, false, 12, true);
assert(neutralPreferenceHtml.includes('<span class="flz-planer-candidate-name">Neutral</span>'));
assert(!neutralPreferenceHtml.includes('flz-planer-preference-marker--neutral'), 'Ein neutraler Chip darf keine Statusmarke anzeigen.');
assert(!neutralPreferenceHtml.includes('☆'), 'Ein neutraler Chip darf keinen Platzhalterstern anzeigen.');
assert(neutralPreferenceHtml.includes('flz-planer-chip--editable'), 'Das Overlay muss am gesamten eigenen Chip hängen.');
assert(neutralPreferenceHtml.includes('tabindex="0" aria-label="Schichtaktionen für Neutral"'), 'Der Chip muss das Hover-Overlay auch per Tastatur öffnen können.');
assert(neutralPreferenceHtml.includes('class="flz-planer-preference-panel"'), 'Hover und Tastaturfokus benötigen ein kompaktes Auswahlpanel.');
assert(neutralPreferenceHtml.includes('aria-label="Als Lieblingsschicht markieren"'));
assert(neutralPreferenceHtml.includes('title="Als Lieblingsschicht markieren"'), 'Der Stern braucht einen sichtbaren Hover-Tooltip.');
assert(neutralPreferenceHtml.includes('aria-hidden="true">★</span>'));
assert(neutralPreferenceHtml.includes('aria-label="Nur wenn sonst niemand kann"'));
assert(neutralPreferenceHtml.includes('title="Nur wenn sonst niemand kann"'), 'Die Rettungsboje braucht einen sichtbaren Hover-Tooltip.');
assert(neutralPreferenceHtml.includes('aria-hidden="true">🛟</span>'));
assert(neutralPreferenceHtml.includes('data-action="open-candidate-note-editor"'));
assert(neutralPreferenceHtml.includes('aria-label="Anmerkung hinzufügen"'));
assert(neutralPreferenceHtml.includes('title="Anmerkung hinzufügen"'), 'Der Anmerkungsbutton braucht einen sichtbaren Hover-Tooltip.');
assert(!neutralPreferenceHtml.includes('data-candidate-note'), 'Der Editor gehört nicht in den Schichtchip.');
assert.strictEqual((neutralPreferenceHtml.match(/data-action="set-candidate-preference"/g) || []).length, 2, 'Nur Favorit und Notfall dürfen als Status angeboten werden.');

const favoritePreferenceHtml = candidateChip.render({
    uid: 'assistant-favorite', displayName: 'Favorit', isSelf: true,
    preference: 'favorite', note: '<Hinweis>'
}, false, 13, true);
assert(favoritePreferenceHtml.includes('flz-planer-preference-marker--favorite'));
assert(favoritePreferenceHtml.includes('aria-hidden="true">★</span>'));
assert(favoritePreferenceHtml.includes('aria-label="Anmerkung bearbeiten"'));
assert(!favoritePreferenceHtml.includes('&lt;Hinweis&gt;'), 'Anmerkungstext darf nicht mehr im Chip oder Overlay erscheinen.');

const emergencyPreferenceHtml = candidateChip.render({
    uid: 'assistant-emergency', displayName: 'Reserve', isSelf: true,
    preference: 'emergency', note: ''
}, false, 14, true);
assert(emergencyPreferenceHtml.includes('flz-planer-preference-marker--emergency'));
assert(emergencyPreferenceHtml.includes('aria-hidden="true">🛟</span>'));
assert(!emergencyPreferenceHtml.includes('🆘'), 'Das missverständliche SOS-Symbol darf nicht mehr verwendet werden.');

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
            weekLabel: 'KW 27',
            note: '<Hinweis>',
            hints: [
                { employeeUid: 'assistant-a', displayName: 'Assistant <A>', type: 'absence', marker: 'U?', label: 'Urlaub', blocks: true },
                { employeeUid: 'assistant-b', displayName: 'Assistant B', type: 'calendar', marker: 'K', label: 'Termin', blocks: false }
            ],
            slots: [
                { id: 10, segmentKey: 'early', candidates: [] },
                {
                    id: 11,
                    segmentKey: 'late',
                    candidates: [
                        { uid: 'assistant-a', displayName: 'Assistant A', isSelf: true, preference: 'favorite', note: '<Nur vormittags>', workloadStatus: 'under', unavailable: true }
                    ]
                }
            ]
        }
    ]
};

const assistantHtml = monthPlan.render({
    ...basePlan,
    workload: [{ uid: 'assistant-a', displayName: 'Assistant A', monthCount: 1, monthStatus: 'under', status: 'under', weeklyMin: 2, weeklyMax: 4, monthlyMin: 8, monthlyMax: 12, weeks: [{ label: 'KW 27', count: 1, status: 'under' }] }],
    team: {
        code: 'A1',
        displayName: 'Team <A1>',
        canCoordinate: false,
        settings: { meetingDay: '2026-07-15' },
        assistants: []
    }
}, { uid: 'assistant-a' });

assert(assistantHtml.includes('Team &lt;A1&gt; - 2026-07'));
assert(assistantHtml.includes('class="flz-planer-personal-month-capacity"'), 'Assistenzkräfte müssen ihre Monatsauslastung direkt an der Monatsangabe sehen.');
assert(assistantHtml.includes('title="1 von mindestens 8">&lt;8</span>'), 'Die Monatsauslastung muss die persönlichen Monatsgrenzen verwenden.');
assert(!assistantHtml.includes('Team <A1>'));
assert(assistantHtml.includes('Treffen 15.07.'));
assert(assistantHtml.includes('Früh &lt;A&gt;'));
assert(assistantHtml.includes('<th scope="col">Tag</th>'), 'Month-plan column headings need an explicit scope.');
assert(assistantHtml.includes('<th scope="col" class="flz-planer-vacation-column">Urlaub</th>'), 'Urlaube benötigen eine eigene kompakte Tabellenspalte.');
assert(assistantHtml.includes('<th scope="row" class="flz-planer-day">'), 'Each planning day needs an explicit row heading.');
assert(assistantHtml.includes('Mi<span>1</span>'));
assert(!assistantHtml.includes('Mi<span>1</span><small>01.07.</small>'), 'Der Tageskopf darf die Tageszahl nicht zusätzlich als Kurzdatum wiederholen.');
assert(assistantHtml.includes('data-action="add-self" data-slot-id="10"'));
assert(!assistantHtml.includes('data-action="add-self" data-slot-id="11"'));
assert(!assistantHtml.includes('flz-planer-assignment-control'));
assert(!assistantHtml.includes('data-action="transition-status"'));
assert(assistantHtml.includes('<span class="flz-planer-note-text">&lt;Hinweis&gt;</span>'));
assert(!assistantHtml.includes('<Hinweis>'));
assert(assistantHtml.includes('U? Assistant &lt;A&gt;'));
assert(assistantHtml.includes('K Assistant B'));
assert(assistantHtml.includes('<td rowspan="1" class="flz-planer-vacation-cell" aria-label="Urlaub: U? Assistant &lt;A&gt;">'), 'Der Urlaub eines Teammitglieds muss als zusammenhängender vertikaler Block erscheinen.');
assert(assistantHtml.includes('<span class="flz-planer-vacation-entry">U? Assistant &lt;A&gt;</span>'), 'Der Urlaubsblock muss Status und Teammitglied benennen.');
assert(assistantHtml.includes('<div class="flz-planer-day-hints" aria-label="Planungshinweise"><span class="flz-planer-hint" title="Termin">K Assistant B</span></div>'), 'Andere Hinweise bleiben am Tag, Urlaub wird dort nicht doppelt ausgegeben.');
assert(!assistantHtml.includes('Assistant <A>'));
assert(!assistantHtml.includes('flz-planer-workload-overview'), 'Die Auslastung gehört nicht mehr unter den Wunschplan.');
assert(assistantHtml.includes('Unter persönlichem Minimum'));
assert(assistantHtml.includes('flz-planer-chip--under'));
assert(assistantHtml.includes('title="Unter persönlichem Minimum"'), 'Die verbleibende kräftige Markierung braucht eine textliche Erklärung als Tooltip.');
assert(assistantHtml.includes('aria-label="Urlaub – nicht für den Planvorschlag verfügbar"'), 'Ein bestehender Wunsch im Urlaub braucht eine sicht- und zugängliche Konfliktmarkierung.');
assert(!assistantHtml.includes('aria-hidden="true">▲</span>'), 'Das missverständliche schwarze Dreieck darf nicht mehr erscheinen.');
assert(!assistantHtml.includes('aria-hidden="true">▽</span>'), 'Auch die Über-Maximum-Markierung darf kein zusätzliches Dreieck verwenden.');
assert(assistantHtml.includes('data-action="set-candidate-preference"'));
assert(assistantHtml.includes('KW 27'), 'Die im Auslastungs-Overlay verwendete Kalenderwoche muss im Monatsplan erkennbar sein.');
assert(assistantHtml.includes('class="flz-planer-week-cell flz-planer-capacity--under"'), 'Die gesamte KW-Zelle muss die persönliche Auslastungsfarbe tragen.');
assert(assistantHtml.includes('<span>KW</span><strong>27</strong><span>&lt;2</span>'), 'KW, Nummer und Auslastung müssen platzsparend untereinander stehen.');
assert(assistantHtml.includes('class="flz-planer-mobile-plan" role="list"'), 'Kleine Viewports brauchen eine semantische Tagesliste statt der Desktopmatrix.');
assert(assistantHtml.includes('class="flz-planer-mobile-day" role="listitem"'), 'Jeder mobile Planungstag muss als eigener Listeneintrag erkennbar sein.');
assert(assistantHtml.includes('class="flz-planer-mobile-shift"'), 'Schichtzeiten und Zuständigkeit müssen mobil je Tag zusammenbleiben.');
assert(assistantHtml.includes('class="flz-planer-mobile-notes"'), 'Tages- und persönliche Anmerkungen müssen mobil erreichbar bleiben.');
assert.strictEqual((assistantHtml.match(/data-action="add-self" data-slot-id="10"/g) || []).length, 2, 'Die eigene Wunschaktion muss in Desktop- und Mobilprojektion verfügbar sein.');

const weekSpanPlan = JSON.parse(JSON.stringify(basePlan));
weekSpanPlan.days.push({
    ...JSON.parse(JSON.stringify(basePlan.days[0])),
    date: '2026-07-02',
    dayOfMonth: 2,
    weekday: 4,
    note: '',
    hints: [],
});
weekSpanPlan.team = {
    code: 'A1', displayName: 'Team A1', canCoordinate: false, settings: {}, assistants: [],
};
weekSpanPlan.workload = [{
    uid: 'assistant-a', displayName: 'Assistant A', monthCount: 3,
    weeklyMin: 2, weeklyMax: 4, monthlyMin: 2, monthlyMax: 4,
    weeks: [{ label: 'KW 27', count: 3, status: 'within' }],
}];
const weekSpanHtml = monthPlan.render(weekSpanPlan, { uid: 'assistant-a' });
assert(weekSpanHtml.includes('<th scope="rowgroup" rowspan="2" class="flz-planer-week-cell flz-planer-capacity--within"'), 'Eine KW-Zelle muss alle zugehörigen Tageszeilen überspannen.');
assert.strictEqual((weekSpanHtml.match(/class="flz-planer-week-cell/g) || []).length, 1, 'Eine zusammenhängende Kalenderwoche darf nur eine KW-Zelle erzeugen.');
assert(weekSpanHtml.includes('<span>KW</span><strong>27</strong><span>3/4</span>'), 'Eine Auslastung innerhalb der Grenzen muss kompakt als Ist/Maximum erscheinen.');
assert(!weekSpanHtml.includes('flz-planer-personal-week-capacity'), 'Die alte wiederholte KW-Auslastung im Tageskopf darf nicht bestehen bleiben.');

const vacationSpanPlan = JSON.parse(JSON.stringify(weekSpanPlan));
vacationSpanPlan.days[1].hints = [
    { employeeUid: 'assistant-a', displayName: 'Assistant <A>', type: 'absence', marker: 'U?', label: 'Urlaub', blocks: true },
];
const vacationSpanHtml = monthPlan.render(vacationSpanPlan, { uid: 'assistant-a' });
assert(vacationSpanHtml.includes('<td rowspan="2" class="flz-planer-vacation-cell"'), 'Ein unveränderter Urlaub muss alle zusammenhängenden Tageszeilen überspannen.');
assert.strictEqual((vacationSpanHtml.match(/class="flz-planer-vacation-cell"/g) || []).length, 1, 'Ein zusammenhängender Urlaub darf nur eine sichtbare Blockzelle erzeugen.');

const teamVacationPlan = JSON.parse(JSON.stringify(weekSpanPlan));
teamVacationPlan.days[0].hints.push({ employeeUid: 'assistant-c', displayName: 'Assistant C', type: 'absence', marker: 'U', label: 'Urlaub', blocks: true });
const teamVacationHtml = monthPlan.render(teamVacationPlan, { uid: 'assistant-a' });
assert(teamVacationHtml.includes('<span class="flz-planer-vacation-entry">U? Assistant &lt;A&gt;</span><span class="flz-planer-vacation-entry">U Assistant C</span>'), 'Die Urlaubsspalte darf die Urlaube des gesamten Teams gemeinsam zeigen.');

const changingVacationPlan = JSON.parse(JSON.stringify(weekSpanPlan));
changingVacationPlan.days[0].hints = [{ displayName: 'Assistant A', type: 'absence', marker: 'U', label: 'Urlaub', blocks: true }];
changingVacationPlan.days[1].hints = [{ displayName: 'Assistant B', type: 'absence', marker: 'U', label: 'Urlaub', blocks: true }];
const changingVacationHtml = monthPlan.render(changingVacationPlan, { uid: 'assistant-a' });
assert.strictEqual((changingVacationHtml.match(/class="flz-planer-vacation-cell"/g) || []).length, 2, 'Aufeinanderfolgende Urlaube verschiedener Teammitglieder müssen getrennte Blöcke bleiben.');
assert(changingVacationHtml.includes('aria-label="Urlaub: U Assistant A"') && changingVacationHtml.includes('aria-label="Urlaub: U Assistant B"'), 'Die datensparsam projizierten Anzeigenamen müssen den richtigen Urlaubstagen zugeordnet bleiben.');

const separatedWeeksPlan = JSON.parse(JSON.stringify(weekSpanPlan));
separatedWeeksPlan.days.push({
    ...JSON.parse(JSON.stringify(basePlan.days[0])),
    date: '2026-07-06',
    dayOfMonth: 6,
    weekday: 1,
    weekLabel: 'KW 28',
});
separatedWeeksPlan.workload[0].weeks.push({ label: 'KW 28', count: 1, status: 'under' });
const separatedWeeksHtml = monthPlan.render(separatedWeeksPlan, { uid: 'assistant-a' });
assert.strictEqual((separatedWeeksHtml.match(/<tr class="flz-planer-week-start">/g) || []).length, 1, 'Nur der Beginn einer nachfolgenden KW darf die kräftige Trennlinie erhalten.');
assert(separatedWeeksHtml.includes('<tr class="flz-planer-week-start">\n                <th scope="rowgroup" rowspan="1"'), 'Die KW-Trennung muss an der ersten Tageszeile der neuen Woche beginnen.');

const unboundedHtml = monthPlan.render({
    ...weekSpanPlan,
    workload: [{
        uid: 'assistant-a', displayName: 'Assistant A', monthCount: 6,
        weeklyMin: 0, weeklyMax: 0, monthlyMin: 0, monthlyMax: 0,
        weeks: [{ label: 'KW 27', count: 2, status: 'normal' }],
    }],
}, { uid: 'assistant-a' });
assert(unboundedHtml.includes('class="flz-planer-personal-month-capacity" aria-label="Monatsauslastung"><span class="flz-planer-capacity flz-planer-capacity--plain" title="6 Schichten">6</span>'), 'Ohne Monatsgrenzen darf nur die aktuelle Anzahl erscheinen.');
assert(unboundedHtml.includes('class="flz-planer-week-cell" title="2 Schichten"'), 'Die KW-Zelle darf ohne Grenzen keine Auslastungs-Farbklasse erhalten.');
assert(unboundedHtml.includes('<span>KW</span><strong>27</strong><span>2</span>'), 'Die KW-Zelle muss ohne Grenzen nur die aktuelle Anzahl zeigen.');
assert(!unboundedHtml.includes('class="flz-planer-week-cell flz-planer-capacity--'), 'Ohne Wochenlimits darf keine farbliche KW-Markierung entstehen.');

const conflictPlan = JSON.parse(JSON.stringify(basePlan));
conflictPlan.team = {code:'A1',displayName:'Team A1',canCoordinate:true,settings:{},assistants:[]};
conflictPlan.days[0].slots[0].candidates = [
    {uid:'a',displayName:'A',fixed:true},
    {uid:'b',displayName:'B',fixed:true},
];
conflictPlan.days[0].slots[0].fixedConflict = {status:'escalated',candidateUids:['a','b'],canReport:false,canResolve:true};
const conflictHtml = monthPlan.render(conflictPlan,{uid:'eb'});
assert(conflictHtml.includes('Konflikt bei festen Schichten'));
assert(conflictHtml.includes('An EB weitergegeben'));
assert(conflictHtml.includes('data-action="resolve-fixed-conflict"'));
assert(!conflictHtml.includes('aria-label="A entfernen"'), 'Die EB darf feste Schichten nicht über das normale Lösch-X verändern.');
assert(assistantHtml.includes('aria-label="Lieblingsschicht entfernen"'));
assert(assistantHtml.includes('maxlength="500"'));
assert(assistantHtml.includes('&lt;Nur vormittags&gt;'));
const ebNotePosition = assistantHtml.indexOf('<span class="flz-planer-note-text">&lt;Hinweis&gt;</span>');
const candidateNotePosition = assistantHtml.indexOf('<strong>Assistant A, Spät:</strong> &lt;Nur vormittags&gt;');
assert(ebNotePosition >= 0 && candidateNotePosition > ebNotePosition, 'Schichtanmerkungen müssen unter der EB-Bemerkung erscheinen.');
assert(assistantHtml.includes('data-action="open-candidate-note-editor" data-slot-id="11" data-target-uid="assistant-a"'));
assert(assistantHtml.includes('data-action="delete-candidate-note" data-slot-id="11" data-target-uid="assistant-a"'));

const ebHtml = monthPlan.render({
    ...basePlan,
    days: basePlan.days.map(day => ({ ...day, slots: day.slots.map(slot => ({ ...slot, candidates: (slot.candidates || []).map(candidate => ({ ...candidate, isSelf: false })) })) })),
    workload: [{ uid: 'assistant-a', displayName: 'Assistant A', monthCount: 1, monthStatus: 'over', status: 'over', weeklyMin: null, weeklyMax: 0, monthlyMin: null, monthlyMax: 0, weeks: [] }],
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
assert(ebHtml.includes('flz-planer-assignment-control'));
assert(ebHtml.includes('data-action="remove-candidate" data-slot-id="11" data-target-uid="assistant-a"'));
assert(ebHtml.includes('aria-label="Assistant A entfernen"'));
assert(ebHtml.includes('<textarea rows="2" maxlength="2000" aria-label="Bemerkung für 01.07." data-note-date="2026-07-01">&lt;Hinweis&gt;</textarea>'));
assert(ebHtml.includes('aria-label="Bemerkung für 01.07."'), 'The editable day note needs its own accessible name.');
assert(ebHtml.includes('aria-label="Bemerkung für 01.07. speichern"'));
assert(ebHtml.includes('<strong>Assistant A, Spät:</strong> &lt;Nur vormittags&gt;'), 'Die EB muss Schichtanmerkungen in der Bemerkungsspalte sehen.');
assert(!ebHtml.includes('data-action="delete-candidate-note"'), 'Die EB darf fremde Schichtanmerkungen nicht löschen.');
assert(!ebHtml.includes('data-action="open-candidate-note-editor"'), 'Die EB darf fremde Schichtanmerkungen nicht bearbeiten.');
assert(ebHtml.includes('data-action="add-selected" data-slot-id="10" data-target-uid="assistant-b"'));
assert(ebHtml.includes('Entwurf'));
assert(ebHtml.includes('data-action="transition-status" data-target-status="planned"'));
assert(!ebHtml.includes('flz-planer-workload-table'), 'Die Team-Auslastung darf nicht mehr im Wunschplan eingebettet sein.');
assert(!ebHtml.includes('flz-planer-personal-month-capacity'), 'Die EB erhält die Teamübersicht im Overlay und keine persönliche Monatsanzeige im Plan.');
assert(!ebHtml.includes('flz-planer-personal-week-capacity'), 'Die EB erhält keine persönliche KW-Anzeige im Plan.');
assert(!ebHtml.includes('data-action="set-candidate-preference"'), 'EB darf fremde Präferenzen nicht verändern.');
assert(ebHtml.includes('id="flz-planer-assignment-picker-10-desktop"'), 'Die Desktop-Zuteilung braucht eine eindeutige Steuerelement-ID.');
assert(ebHtml.includes('id="flz-planer-assignment-picker-10-mobile"'), 'Die mobile Zuteilung darf keine ID der Desktopprojektion duplizieren.');
assert.strictEqual((ebHtml.match(/data-action="add-selected" data-slot-id="10" data-target-uid="assistant-b"/g) || []).length, 2, 'EB-Zuteilungen müssen in Desktop- und Mobilprojektion denselben Aktionsvertrag verwenden.');

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
assert(!approvedHtml.includes('flz-planer-assignment-control'));
assert(!approvedHtml.includes('data-action="remove-candidate"'));
assert(!approvedHtml.includes('<textarea'));
assert(!approvedHtml.includes('data-action="add-self"'), 'Ein genehmigter Plan darf auch mobil keine eigenen Mutationen anbieten.');

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
assert(!unknownStatusHtml.includes('flz-planer-assignment-control'), 'Unknown plan statuses must fail closed in the assignment UI.');
assert(!unknownStatusHtml.includes('data-action="remove-candidate"'), 'Unknown plan statuses must not expose candidate mutations.');
assert(!unknownStatusHtml.includes('<textarea'), 'Unknown plan statuses must not expose day-note mutations.');

console.log('FlzPlaner month plan smoke test passed.');
