const assert = require('assert');

global.window = { ADPlaner: {} };
global.CSS = { escape: String };
global.Element = class {};
global.FormData = class {
    get(name) { return {weeklyMin:'1',weeklyMax:'3',monthlyMin:'5',monthlyMax:'12'}[name] || ''; }
    getAll(name) { return name === 'regularShift' ? ['1|early','1|late'] : []; }
};
require('../../js/components/plan-panel.js');

function element() {
    return { listeners:{}, addEventListener(type, listener){this.listeners[type] = listener;} };
}
const panelElement = element();
const textarea = { focused:false, focus(){ this.focused = true; } };
const editor = { hidden:true, querySelector(selector){ return selector === '[data-candidate-note]' ? textarea : null; } };
const noteEntry = { querySelector(selector){ return selector === '.adp-shift-note-editor' ? editor : null; } };
panelElement.querySelector = selector => selector.includes('data-candidate-note-entry') ? noteEntry : null;
const form = element();
const regularForm = element();
let submitted = null;
let submittedRules = null;
const panel = new window.ADPlaner.PlanPanel({
    byId(id) { return id === 'adp-panel' ? panelElement : (id === 'personal-workload-form' ? form : (id === 'personal-regular-shifts-form' ? regularForm : null)); },
    onSavePersonalWorkload(values) { submitted = values; },
    onSavePersonalRegularShifts(rules) { submittedRules = rules; },
});
panel.bindPersonalWorkloadForm();
panel.bindPersonalRegularShiftsForm();

(async () => {
    let prevented = false;
    await form.listeners.submit({preventDefault(){prevented = true;}});
    assert.strictEqual(prevented, true);
    assert.deepStrictEqual(submitted, {weeklyMin:'1',weeklyMax:'3',monthlyMin:'5',monthlyMax:'12'});
    await regularForm.listeners.submit({preventDefault(){}});
    assert.deepStrictEqual(submittedRules,[{weekday:1,segmentKey:'early'},{weekday:1,segmentKey:'late'}]);

    const openButton = Object.assign(new Element(), {
        dataset:{action:'open-candidate-note-editor',slotId:'7',targetUid:'self'},
        closest(){ return this; }
    });
    await panel.handleClick({target:openButton});
    assert.strictEqual(editor.hidden, false, 'Der Anmerkungsbutton muss den Editor in der Bemerkungsspalte öffnen.');
    assert.strictEqual(textarea.focused, true);

    const closeButton = Object.assign(new Element(), {
        dataset:{action:'close-candidate-note-editor',slotId:'7',targetUid:'self'},
        closest(){ return this; }
    });
    await panel.handleClick({target:closeButton});
    assert.strictEqual(editor.hidden, true, 'Abbrechen muss den Editor ohne Speichervorgang schließen.');

    const mobileEditor = { hidden:true, querySelector(){ return textarea; } };
    const mobileEntry = { querySelector(){ return mobileEditor; } };
    const mobileSurface = { querySelector(){ return mobileEntry; } };
    const mobileButton = Object.assign(new Element(), {
        dataset:{action:'open-candidate-note-editor',slotId:'7',targetUid:'self'},
        closest(selector){
            if (selector === 'button[data-action]') return this;
            if (selector === '.adp-mobile-plan, .adp-desktop-plan') return mobileSurface;
            return null;
        },
    });
    await panel.handleClick({target:mobileButton});
    assert.strictEqual(mobileEditor.hidden, false, 'Der mobile Schichtchip muss den Editor seiner eigenen Projektion öffnen.');
    assert.strictEqual(editor.hidden, true, 'Der ausgeblendete Desktop-Editor darf durch eine mobile Aktion nicht geöffnet werden.');
    console.log('AdPlaner personal settings form smoke test passed.');
})().catch(error => { console.error(error); process.exit(1); });
