const assert = require('assert');

const elements = new Map([
    ['flz-planer-notice', { textContent: '', hidden: true, className: '' }]
]);

global.window = {};
global.document = {
    getElementById(id) {
        return elements.get(id) || null;
    }
};

require('../../../localbase/js/ui/ui.js');
require('../../js/modules/ui.js');

const { dateShort, dayHeader, monthHeader, statusLabel, showError, showNotice } = window.FlzPlaner.ui;
const notice = elements.get('flz-planer-notice');

assert.strictEqual(dateShort('2026-07-04'), '04.07.');
assert.strictEqual(statusLabel('approved'), 'genehmigt');
assert.strictEqual(statusLabel('planned'), 'geplant');
assert.strictEqual(dayHeader({ weekday: 2, dayOfMonth: 7 }), 'Di<span>7</span>');
assert.strictEqual(monthHeader({ month: 7, dayOfMonth: 4 }), 'Jul<span>4</span>');

showNotice('Bereit');
assert.strictEqual(notice.textContent, 'Bereit');
assert.strictEqual(notice.hidden, false);

showError({ data: { message: 'API kaputt' }, message: 'HTTP 400' }, 'Fallback');
assert.strictEqual(notice.textContent, 'API kaputt');

showError(null, 'Fallback');
assert.strictEqual(notice.textContent, 'Fallback');

console.log('FlzPlaner UI smoke test passed.');
