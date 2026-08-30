const assert = require('assert');

global.window = { ADPlaner: {} };
global.FormData = class {
    get(name) { return {weeklyMin:'1',weeklyMax:'3',monthlyMin:'5',monthlyMax:'12'}[name] || ''; }
};
require('../../js/components/plan-panel.js');

function element() {
    return { listeners:{}, addEventListener(type, listener){this.listeners[type] = listener;} };
}
const panelElement = element();
const form = element();
let submitted = null;
const panel = new window.ADPlaner.PlanPanel({
    byId(id) { return id === 'adp-panel' ? panelElement : (id === 'personal-workload-form' ? form : null); },
    onSavePersonalWorkload(values) { submitted = values; },
});
panel.bindPersonalWorkloadForm();

(async () => {
    let prevented = false;
    await form.listeners.submit({preventDefault(){prevented = true;}});
    assert.strictEqual(prevented, true);
    assert.deepStrictEqual(submitted, {weeklyMin:'1',weeklyMax:'3',monthlyMin:'5',monthlyMax:'12'});
    console.log('AdPlaner personal settings form smoke test passed.');
})().catch(error => { console.error(error); process.exit(1); });
