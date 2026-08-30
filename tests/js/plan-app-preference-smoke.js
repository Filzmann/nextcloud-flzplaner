const assert = require('assert');

global.window = { ADPlaner: {} };
global.CSS = { escape: String };
require('../../js/modules/plan-app.js');

class ChromeFake { render() {} }
class PanelFake {
    constructor() {
        this.noteEntry = { dataset:{currentPreference:'favorite'}, querySelector(selector){ assert.strictEqual(selector, '[data-candidate-note]'); return {value:'Synthetischer Hinweis'}; } };
        this.panel = { querySelector: selector => selector.includes('data-candidate-note-entry') ? this.noteEntry : null };
    }
    render() {}
}

const calls = [];
const repository = {
    async updateCandidateMetadata(team, month, slot, preference, note) { calls.push(['metadata', team, month, slot, preference, note]); },
    async monthPlan(team, month) { return { month, team: { code: team, personalWorkload: {}, canSetPersonalWorkload: false } }; },
    async savePersonalWorkload(team, limits) { calls.push(['limits', team, limits]); },
    async state() { return { currentUser:{uid:'self'}, teams:[{code:'A1',personalWorkload:{weeklyMin:2},canSetPersonalWorkload:true}], organization:{} }; },
};
const errors = [];
const app = new window.ADPlaner.PlanApp({
    repository, PlanChrome:ChromeFake, PlanPanel:PanelFake, byId(){return null;}, esc:String,
    showNotice(){}, showError(error, fallback){errors.push({error,fallback});}, renderMonth(){return '';}, renderSettings(){return '';},
    addShiftRow(){}, removeShiftRow(){}, collectShifts(){return [];}, openAssignmentPicker(){},
});
app.state = { currentUser:{uid:'self'}, teams:[{code:'A1',personalWorkload:{weeklyMin:1},canSetPersonalWorkload:true}], selectedTeamCode:'A1', month:'2026-09', activeView:'month', monthPlan:{}, organization:{}, loading:false };

function actionButton(action, preference = '') {
    const chip = { dataset:{currentPreference:'favorite'} };
    return { disabled:false, dataset:{action, slotId:'7', targetUid:'self', preference}, closest(selector){ return selector === '[data-candidate-chip]' ? chip : null; } };
}

(async () => {
    await app.handleAction(actionButton('set-candidate-preference', 'emergency'));
    assert.deepStrictEqual(calls[0], ['metadata','A1','2026-09','7','emergency','Synthetischer Hinweis']);

    await app.handleAction(actionButton('save-candidate-note'));
    assert.deepStrictEqual(calls[1], ['metadata','A1','2026-09','7','favorite','Synthetischer Hinweis']);

    await app.handleAction(actionButton('delete-candidate-note'));
    assert.deepStrictEqual(calls[2], ['metadata','A1','2026-09','7','favorite','']);

    await app.savePersonalWorkload({weeklyMin:'2',weeklyMax:'4',monthlyMin:'8',monthlyMax:'12'});
    assert.deepStrictEqual(calls[3], ['limits','A1',{weeklyMin:'2',weeklyMax:'4',monthlyMin:'8',monthlyMax:'12'}]);
    assert.strictEqual(app.selectedTeam().personalWorkload.weeklyMin, 2);
    assert.strictEqual(app.selectedTeam().canSetPersonalWorkload, true);
    assert.deepStrictEqual(errors, []);

    console.log('AdPlaner preference and workload workflow smoke test passed.');
})().catch(error => { console.error(error); process.exit(1); });
