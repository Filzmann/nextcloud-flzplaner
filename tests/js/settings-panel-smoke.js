const assert = require('assert');

global.window = {};

require('../../../localbase/js/ui/ui.js');
require('../../js/modules/ui.js');
require('../../js/components/shift-settings-list.js');
require('../../js/components/settings-panel.js');

const { settingsPanel, shiftSettingsList } = window.ADPlaner;

const team = {
    code: 'A1',
    displayName: 'Team <Settings>',
    settings: {
        meetingDay: '2026-07-15',
        shifts: [
            { key: 'early', label: 'Früh <A>', startsAt: '08:00', endsAt: '14:00', enabled: true },
            { key: 'night', label: 'Nacht', startsAt: '20:00', endsAt: '08:00', enabled: false }
        ]
    }
};

assert(settingsPanel.render(null).includes('Kein Assistenznehmer gewählt.'));

const readonlyHtml = settingsPanel.render({
    ...team,
    canCoordinate: false
});

assert(readonlyHtml.includes('Team &lt;Settings&gt;'));
assert(!readonlyHtml.includes('Team <Settings>'));
assert(readonlyHtml.includes('<dd>15.07.</dd>'));
assert(readonlyHtml.includes('Früh &lt;A&gt; 08:00-14:00'));
assert(!readonlyHtml.includes('Früh <A>'));
assert(readonlyHtml.includes('adp-readonly-shift is-disabled'));
assert(!readonlyHtml.includes('id="settings-form"'));

const editorHtml = settingsPanel.render({
    ...team,
    canCoordinate: true
});

assert(editorHtml.includes('id="settings-form"'));
assert(editorHtml.includes('name="displayName" type="text" maxlength="255" required'));
assert(editorHtml.includes('value="Team &lt;Settings&gt;"'));
assert(editorHtml.includes('value="early"'));
assert(editorHtml.includes('value="Früh &lt;A&gt;"'));
assert(editorHtml.includes('data-action="add-shift-row"'));
assert(editorHtml.includes('data-action="remove-shift-row"'));
assert(editorHtml.includes('aria-label="Schicht Früh &lt;A&gt; entfernen"'));
assert(!editorHtml.includes('aria-label="Schicht Früh <A> entfernen"'));
assert(editorHtml.includes('<button type="submit">Speichern</button>'));

const collectForm = {
    querySelectorAll(selector) {
        assert.strictEqual(selector, '[data-shift-row]');

        return [
            row({
                shiftKey: 'early',
                shiftLabel: 'Früh',
                shiftStart: '08:00',
                shiftEnd: '14:00',
                shiftEnabled: true
            }),
            row({
                shiftKey: 'late',
                shiftLabel: 'Spät',
                shiftStart: '14:00',
                shiftEnd: '20:00',
                shiftEnabled: false
            })
        ];
    }
};

assert.deepStrictEqual(shiftSettingsList.collect(collectForm), [
    { key: 'early', label: 'Früh', startsAt: '08:00', endsAt: '14:00', enabled: true },
    { key: 'late', label: 'Spät', startsAt: '14:00', endsAt: '20:00', enabled: false }
]);

const insertedRows = [];
const shiftList = {
    querySelectorAll(selector) {
        assert.strictEqual(selector, '[data-shift-row]');

        return [{}, {}];
    },
    insertAdjacentHTML(position, html) {
        insertedRows.push({ position, html });
    }
};

const originalNow = Date.now;
Date.now = () => 1234567890;
global.document = {
    getElementById(id) {
        assert.strictEqual(id, 'adp-shift-list');

        return shiftList;
    }
};
shiftSettingsList.addRow();
Date.now = originalNow;

assert.strictEqual(insertedRows.length, 1);
assert.strictEqual(insertedRows[0].position, 'beforeend');
assert(insertedRows[0].html.includes('value="shift_kf12oi_3"'));
assert(insertedRows[0].html.includes('value="Schicht 3"'));
assert(insertedRows[0].html.includes('value="08:00"'));
assert(insertedRows[0].html.includes('value="14:00"'));

let removedRows = 0;
global.Element = class {};
const removableRow = {
    remove() {
        removedRows += 1;
    }
};
const removeButton = new Element();
removeButton.closest = selector => {
    assert.strictEqual(selector, '[data-shift-row]');

    return removableRow;
};
global.document = {
    getElementById(id) {
        assert.strictEqual(id, 'adp-shift-list');

        return {
            querySelectorAll(selector) {
                assert.strictEqual(selector, '[data-shift-row]');

                return [{}, {}];
            }
        };
    }
};
shiftSettingsList.removeRow(removeButton);
assert.strictEqual(removedRows, 1);

global.document = {
    getElementById() {
        return {
            querySelectorAll() {
                return [{}];
            }
        };
    }
};
shiftSettingsList.removeRow(removeButton);
assert.strictEqual(removedRows, 1);

function row(values) {
    return {
        querySelector(selector) {
            const controls = {
                '[name="shiftKey"]': { value: values.shiftKey },
                '[name="shiftLabel"]': { value: values.shiftLabel },
                '[name="shiftStart"]': { value: values.shiftStart },
                '[name="shiftEnd"]': { value: values.shiftEnd },
                '[name="shiftEnabled"]': { checked: values.shiftEnabled }
            };

            return controls[selector];
        }
    };
}

console.log('AdPlaner settings panel smoke test passed.');
