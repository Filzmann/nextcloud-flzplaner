const assert = require('assert');

global.window = {};
require('../../../localbase/js/ui/ui.js');
require('../../js/modules/ui.js');
require('../../js/components/workload-panel.js');

const plan = {
    month: '2026-09',
    team: { code: 'A1', displayName: 'Team A1', canCoordinate: true },
    workload: [
        { uid:'low', displayName:'Noch frei', monthCount:2, monthStatus:'under', monthlyMin:3, monthlyMax:5, weeks:[] },
        { uid:'fit', displayName:'Passend', monthCount:4, monthStatus:'normal', monthlyMin:3, monthlyMax:5, weeks:[{label:'KW 36',count:4,status:'normal'}] },
        { uid:'high', displayName:'Voll', monthCount:8, monthStatus:'over', monthlyMin:3, monthlyMax:5, weeks:[] },
        { uid:'fixed', displayName:'Fest gesetzt', monthCount:5, monthStatus:'normal', monthlyMin:3, monthlyMax:5, weeks:[] },
        { uid:'unbounded', displayName:'Ohne Grenzen', monthCount:6, monthStatus:'normal', monthlyMin:0, monthlyMax:0, weeklyMin:0, weeklyMax:0, weeks:[{label:'KW 36',count:2,status:'normal'}] },
    ],
    segments: [{key:'early',label:'Früh'},{key:'late',label:'Spät'},{key:'night',label:'Nacht'}],
    days: [{date:'2026-09-01',slots:[{id:1,segmentKey:'early',candidates:[
        {uid:'fixed',displayName:'Fest gesetzt',preference:'neutral',fixed:true},
        {uid:'high',displayName:'Voll',preference:'favorite',unavailable:true},
        {uid:'fit',displayName:'Passend',preference:'emergency'},
        {uid:'low',displayName:'Noch frei',preference:'neutral'},
    ]},{id:2,segmentKey:'late',candidates:[]},{id:3,segmentKey:'night',candidates:[
        {uid:'high',displayName:'Voll',preference:'favorite',unavailable:true},
    ]}]}],
};

const ebHtml = window.ADPlaner.workloadPanel.render(plan);
assert(ebHtml.includes('id="adp-workload-heading"'));
assert(ebHtml.includes('class="adp-week-capacities"'), 'Kalenderwochen müssen kompakt in einer gemeinsamen Zeile gruppiert werden.');
assert(!ebHtml.includes('<br>'), 'Kalenderwochen dürfen das Overlay nicht durch erzwungene Zeilenumbrüche aufblähen.');
assert(ebHtml.includes('<details class="adp-proposal-help"><summary>Sortierung</summary>'), 'Die ausführliche Sortiererklärung muss platzsparend einklappbar sein.');
assert(ebHtml.includes('1 Schicht ohne Wünsche'), 'Leere Schichten müssen kompakt zusammengefasst werden.');
assert(!ebHtml.includes('>Keine Wünsche</span>'), 'Leere Schichten dürfen den Vorschlag nicht mit einzelnen Tabellenzeilen verlängern.');
assert(ebHtml.includes('class="adp-capacity adp-capacity--under" title="2 von mindestens 3">&lt;3</span>'));
assert(ebHtml.includes('class="adp-capacity adp-capacity--within" title="4 von maximal 5">4/5</span>'));
assert(ebHtml.includes('class="adp-capacity adp-capacity--over" title="8 von maximal 5">8/5</span>'));
assert(ebHtml.includes('<th scope="row">Ohne Grenzen</th><td><span class="adp-capacity adp-capacity--plain" title="6 Schichten">6</span></td>'), 'Ohne Monatsgrenzen darf nur die aktuelle Anzahl ohne Farbmarkierung erscheinen.');
assert(ebHtml.includes('<span>KW 36</span><span class="adp-capacity adp-capacity--plain" title="2 Schichten">2</span>'), 'Ohne Wochengrenzen darf nur die aktuelle Anzahl ohne Farbmarkierung erscheinen.');
assert(!ebHtml.includes('Unter persönlichem Minimum'));
assert(!ebHtml.includes('Über persönlichem Maximum'));
assert(ebHtml.includes('Grober Planvorschlag'));
const proposalHtml = ebHtml.slice(ebHtml.indexOf('Grober Planvorschlag'));
const fixed = proposalHtml.indexOf('Fest gesetzt');
const neutral = proposalHtml.indexOf('Noch frei', fixed + 1);
const emergency = proposalHtml.indexOf('Passend', neutral + 1);
assert(fixed >= 0 && neutral > fixed && emergency > neutral, 'Feste Schicht, neutraler Wunsch und Notfall müssen in dieser Reihenfolge vorgeschlagen werden.');
assert(!proposalHtml.includes('Voll'), 'Eine Assistenzkraft im Urlaub darf nicht vorgeschlagen werden.');
assert(proposalHtml.includes('2 Urlaubskonflikte nicht vorgeschlagen'), 'Ausgelassene Urlaubskonflikte müssen für die EB nachvollziehbar bleiben.');
assert(proposalHtml.includes('title="Feste Schicht"'));
assert(!ebHtml.includes('data-action='), 'Der Vorschlag darf den Plan nicht automatisch verändern.');

const personalHtml = window.ADPlaner.workloadPanel.render({...plan, team:{...plan.team,canCoordinate:false}, workload:[plan.workload[1]]});
assert.strictEqual(personalHtml, '', 'Normale Mitglieder erhalten weder Auslastungs-Overlay noch Team-Planvorschlag.');

console.log('AdPlaner workload panel smoke test passed.');
