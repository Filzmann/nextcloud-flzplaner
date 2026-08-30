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
    ],
    segments: [{key:'early',label:'Früh'}],
    days: [{date:'2026-09-01',slots:[{id:1,segmentKey:'early',candidates:[
        {uid:'fixed',displayName:'Fest gesetzt',preference:'neutral',fixed:true},
        {uid:'high',displayName:'Voll',preference:'favorite'},
        {uid:'fit',displayName:'Passend',preference:'emergency'},
        {uid:'low',displayName:'Noch frei',preference:'neutral'},
    ]}]}],
};

const ebHtml = window.ADPlaner.workloadPanel.render(plan);
assert(ebHtml.includes('id="adp-workload-heading"'));
assert(ebHtml.includes('class="adp-capacity adp-capacity--under" title="2 von mindestens 3">&lt;3</span>'));
assert(ebHtml.includes('class="adp-capacity adp-capacity--within" title="4 von maximal 5">4/5</span>'));
assert(ebHtml.includes('class="adp-capacity adp-capacity--over" title="8 von maximal 5">8/5</span>'));
assert(!ebHtml.includes('Unter persönlichem Minimum'));
assert(!ebHtml.includes('Über persönlichem Maximum'));
assert(ebHtml.includes('Grober Planvorschlag'));
const proposalHtml = ebHtml.slice(ebHtml.indexOf('Grober Planvorschlag'));
const fixed = proposalHtml.indexOf('Fest gesetzt');
const favorite = proposalHtml.indexOf('Voll', fixed + 1);
const neutral = proposalHtml.indexOf('Noch frei', favorite + 1);
const emergency = proposalHtml.indexOf('Passend', neutral + 1);
assert(fixed >= 0 && favorite > fixed && neutral > favorite && emergency > neutral, 'Feste Schicht, Favorit, neutral und Notfall müssen in dieser Reihenfolge vorgeschlagen werden.');
assert(proposalHtml.includes('title="Feste Schicht"'));
assert(!ebHtml.includes('data-action='), 'Der Vorschlag darf den Plan nicht automatisch verändern.');

const personalHtml = window.ADPlaner.workloadPanel.render({...plan, team:{...plan.team,canCoordinate:false}, workload:[plan.workload[1]]});
assert(personalHtml.includes('Meine Auslastung'));
assert(!personalHtml.includes('Grober Planvorschlag'), 'Normale Mitglieder erhalten keinen Team-Planvorschlag.');

console.log('AdPlaner workload panel smoke test passed.');
