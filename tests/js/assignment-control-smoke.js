const assert = require('assert');

global.window = {};

require('../../../localbase/js/ui/ui.js');
require('../../js/modules/ui.js');
require('../../js/components/assignment-control.js');

const { assignmentControl } = window.ADPlaner;

const html = assignmentControl.render(
    { id: 7 },
    {
        assistants: [
            { uid: 'alice', displayName: 'Alice Test', canReceiveShifts: true },
            { uid: 'bob', displayName: 'Bob Test', canReceiveShifts: true },
            { uid: 'eb', displayName: 'Einsatzbegleitung', canReceiveShifts: false },
            { uid: 'chris', displayName: '<Chris & Co>', canReceiveShifts: true }
        ]
    },
    [{ uid: 'bob' }]
);

assert(html.includes('data-assignment-control="7"'));
assert(html.includes('data-action="open-assignment-picker"'));
assert(html.includes('aria-label="Assistenz zuteilen"'));
assert(html.includes('data-assignment-trigger="7"'));
assert(html.includes('aria-expanded="false"'));
assert(html.includes('class="adp-assignment-picker"'));
assert(html.includes('role="group"'));
assert(html.includes('aria-label="Assistenz auswählen"'));
assert(html.includes('data-action="add-selected" data-slot-id="7" data-target-uid="alice"'));
assert(!html.includes('data-target-uid="bob"'));
assert(!html.includes('data-target-uid="eb"'));
assert(html.includes('data-target-uid="chris"'));
assert(html.includes('&lt;Chris &amp; Co&gt;'));
assert(!html.includes('<Chris & Co>'));
assert(!html.includes('<select'));

const emptyHtml = assignmentControl.render(
    { id: 8 },
    {
        assistants: [
            { uid: 'bob', displayName: 'Bob Test', canReceiveShifts: true },
            { uid: 'eb', displayName: 'Einsatzbegleitung', canReceiveShifts: false }
        ]
    },
    [{ uid: 'bob' }]
);

assert(emptyHtml.includes('data-action="open-assignment-picker"'));
assert(emptyHtml.includes('data-assignment-trigger="8" data-slot-id="8" disabled'));
assert(emptyHtml.includes('Keine Assistenz verfügbar'));

const focused = [];
const expanded = [];
const triggerOne = {
    dataset: { assignmentTrigger: '1' },
    setAttribute(name, value) {
        assert.strictEqual(name, 'aria-expanded');
        expanded.push(['1', value]);
    }
};
const triggerTwo = {
    dataset: { assignmentTrigger: '2', slotId: '2' },
    setAttribute(name, value) {
        assert.strictEqual(name, 'aria-expanded');
        expanded.push(['2', value]);
    },
    focus() {
        focused.push('trigger-2');
    }
};
const pickerOne = {
    id: 'adp-assignment-picker-1',
    dataset: { assignmentPicker: '1' },
    hidden: false,
    querySelector() {
        return { focus: () => focused.push('1') };
    }
};
const pickerTwo = {
    id: 'adp-assignment-picker-2',
    dataset: { assignmentPicker: '2' },
    hidden: true,
    querySelector(selector) {
        assert.strictEqual(selector, 'button[data-action="add-selected"]');
        return { focus: () => focused.push('2') };
    },
    addEventListener(type, listener) {
        assert.strictEqual(type, 'keydown');
        this.keydown = listener;
    }
};

global.CSS = { escape: String };
global.document = {
    querySelectorAll(selector) {
        if (selector === '[data-assignment-picker]') return [pickerOne, pickerTwo];
        if (selector === '[data-assignment-trigger]') return [triggerOne, triggerTwo];
        assert.fail(`Unexpected selector: ${selector}`);
    },
    querySelector(selector) {
        assert.strictEqual(selector, '[data-assignment-picker="2"]');

        return pickerTwo;
    }
};

assignmentControl.open(triggerTwo);
assert.strictEqual(pickerOne.hidden, true);
assert.strictEqual(pickerTwo.hidden, false);
assert.deepStrictEqual(focused, ['2']);
assert.deepStrictEqual(expanded.slice(-3), [['1', 'false'], ['2', 'false'], ['2', 'true']]);

let prevented = false;
pickerTwo.keydown({
    key: 'Escape',
    preventDefault() { prevented = true; }
});
assert.strictEqual(prevented, true);
assert.strictEqual(pickerTwo.hidden, true);
assert.deepStrictEqual(expanded.at(-1), ['2', 'false']);
assert.deepStrictEqual(focused, ['2', 'trigger-2']);

assignmentControl.open(triggerTwo);
assignmentControl.open(triggerTwo);
assert.strictEqual(pickerTwo.hidden, true);
assert.deepStrictEqual(expanded.at(-1), ['2', 'false']);

console.log('AdPlaner assignment control smoke test passed.');
