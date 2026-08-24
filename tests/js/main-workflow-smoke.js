const assert = require('assert');
const {
    FakeButton,
    FakeElement,
    createElementMap,
} = require('../../../localbase/tests/js/helpers/fake-dom.js');

const elements = createElementMap([
    'team-select',
    'month-input',
    'month-prev',
    'month-next',
    'adp-panel',
]);
const tabs = new FakeElement('tabs');
const tabMonth = new FakeButton({ view: 'month' }, 'tab-month');
const tabSettings = new FakeButton({ view: 'settings' }, 'tab-settings');

const notices = [];
const errors = [];
const repositoryCalls = [];
let lastRepository = null;

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

global.Element = FakeElement;
global.CSS = { escape: String };
global.window = {
    ADPlaner: {
        api: {},
        ui: {
            byId(id) {
                return elements.get(id) || null;
            },
            esc,
            showNotice(message, type = 'info') {
                notices.push({ message, type });
            },
            showError(error, fallback) {
                errors.push({ message: error.message, fallback });
            }
        },
        monthPlan: {
            render(plan) {
                return `<section data-view="month">${esc(plan && plan.team ? plan.team.code : '')}:${esc(plan ? plan.month : '')}</section>`;
            }
        },
        settingsPanel: {
            render(team) {
                return `<form id="settings-form">${esc(team ? team.code : '')}</form>`;
            }
        },
        shiftSettingsList: {
            addRow() {
                repositoryCalls.push(['addShiftRow']);
            },
            removeRow() {
                repositoryCalls.push(['removeShiftRow']);
            },
            collect() {
                return [{ key: 'day' }];
            }
        },
        assignmentControl: {
            open(button) {
                repositoryCalls.push(['openAssignmentPicker', button.dataset.slotId]);
            }
        }
    }
};
global.document = {
    querySelector(selector) {
        return selector === '.adp-tabs' ? tabs : null;
    },
    querySelectorAll(selector) {
        return selector === '.adp-tab' ? [tabMonth, tabSettings] : [];
    }
};

class FakePlanRepository {
    constructor() {
        lastRepository = this;
        this.failMonth = '';
        this.deferredMonths = new Map();
        this.pendingAddSelf = null;
    }

    async state() {
        repositoryCalls.push(['state']);

        return {
            currentUser: { uid: 'anna' },
            teams: [
                { code: 'TeamA', displayName: 'Team <A>' },
                { code: 'TeamB', displayName: 'Team B' }
            ],
            defaultMonth: '2026-07'
        };
    }

    async monthPlan(teamCode, month) {
        repositoryCalls.push(['monthPlan', teamCode, month]);
        if (month === this.failMonth) {
            throw new Error('Monat nicht verfügbar');
        }
        if (this.deferredMonths.has(month)) {
            return this.deferredMonths.get(month).promise;
        }

        return { month, team: { code: teamCode, displayName: teamCode === 'TeamA' ? 'Team <A>' : teamCode } };
    }

    deferMonth(month) {
        let resolve;
        const promise = new Promise(done => { resolve = done; });
        this.deferredMonths.set(month, { promise, resolve });
        return resolve;
    }

    async addSelf(teamCode, month, slotId) {
        repositoryCalls.push(['addSelf', teamCode, month, slotId]);
        if (this.pendingAddSelf) await this.pendingAddSelf;
    }

    deferAddSelf() {
        let resolve;
        this.pendingAddSelf = new Promise(done => { resolve = done; });
        return resolve;
    }

    async addSelected(teamCode, month, slotId, targetUid) {
        repositoryCalls.push(['addSelected', teamCode, month, slotId, targetUid]);
    }

    async transitionStatus(teamCode, month, targetStatus) {
        repositoryCalls.push(['transitionStatus', teamCode, month, targetStatus]);
    }

}

window.ADPlaner.repositories = { PlanRepository: FakePlanRepository };

require('../../js/components/plan-chrome.js');
require('../../js/components/plan-panel.js');
require('../../js/modules/plan-app.js');
require('../../js/main.js');

async function flush() {
    for (let i = 0; i < 8; i++) {
        await Promise.resolve();
    }
}

(async () => {
    await flush();

    assert(lastRepository instanceof FakePlanRepository);
    assert.deepStrictEqual(repositoryCalls.slice(0, 2), [
        ['state'],
        ['monthPlan', 'TeamA', '2026-07']
    ]);
    assert(elements.get('team-select').innerHTML.includes('Team &lt;A&gt;'));
    assert(!elements.get('team-select').innerHTML.includes('Team <A>'));
    assert.strictEqual(elements.get('team-select').disabled, false);
    assert.strictEqual(elements.get('month-input').value, '2026-07');

    await elements.get('month-prev').listeners.click();
    assert.strictEqual(elements.get('month-input').value, '2026-06');
    assert.deepStrictEqual(repositoryCalls.at(-1), ['monthPlan', 'TeamA', '2026-06']);
    await elements.get('month-next').listeners.click();
    assert.strictEqual(elements.get('month-input').value, '2026-07');
    assert.deepStrictEqual(repositoryCalls.at(-1), ['monthPlan', 'TeamA', '2026-07']);
    const callsBeforeInvalidMonth = repositoryCalls.length;
    for (const invalidMonth of ['', '1999-12', '2101-01', '2026-13']) {
        await elements.get('month-input').listeners.change({ target: { value: invalidMonth } });
        assert.strictEqual(elements.get('month-input').value, '2026-07');
    }
    assert.strictEqual(repositoryCalls.length, callsBeforeInvalidMonth);
    await elements.get('month-input').listeners.change({ target: { value: '2000-01' } });
    assert.strictEqual(elements.get('month-prev').disabled, true);
    const callsAtLowerBoundary = repositoryCalls.length;
    await elements.get('month-prev').listeners.click();
    assert.strictEqual(repositoryCalls.length, callsAtLowerBoundary);
    assert.strictEqual(elements.get('month-input').value, '2000-01');
    await elements.get('month-input').listeners.change({ target: { value: '2100-12' } });
    assert.strictEqual(elements.get('month-next').disabled, true);
    const callsAtUpperBoundary = repositoryCalls.length;
    await elements.get('month-next').listeners.click();
    assert.strictEqual(repositoryCalls.length, callsAtUpperBoundary);
    assert.strictEqual(elements.get('month-input').value, '2100-12');
    await elements.get('month-input').listeners.change({ target: { value: '2026-07' } });
    await elements.get('month-input').listeners.change({ target: { value: '2026-12' } });
    await elements.get('month-next').listeners.click();
    assert.strictEqual(elements.get('month-input').value, '2027-01');
    assert.deepStrictEqual(repositoryCalls.at(-1), ['monthPlan', 'TeamA', '2027-01']);
    await elements.get('month-prev').listeners.click();
    assert.strictEqual(elements.get('month-input').value, '2026-12');
    assert.deepStrictEqual(repositoryCalls.at(-1), ['monthPlan', 'TeamA', '2026-12']);
    await elements.get('month-input').listeners.change({ target: { value: '2026-07' } });
    assert.strictEqual(tabMonth.classList.has('is-active'), true);
    assert.strictEqual(tabMonth.getAttribute('aria-selected'), 'true');
    assert.strictEqual(tabSettings.getAttribute('aria-selected'), 'false');
    assert.strictEqual(elements.get('adp-panel').getAttribute('aria-labelledby'), 'tab-month');
    assert(elements.get('adp-panel').innerHTML.includes('TeamA:2026-07'));

    let prevented = false;
    await tabs.listeners.keydown({
        target: tabMonth,
        key: 'ArrowRight',
        preventDefault() { prevented = true; }
    });
    assert.strictEqual(prevented, true);
    assert.strictEqual(tabSettings.focused, true);
    assert.strictEqual(tabSettings.getAttribute('aria-selected'), 'true');
    assert.strictEqual(elements.get('adp-panel').getAttribute('aria-labelledby'), 'tab-settings');
    assert(elements.get('adp-panel').innerHTML.includes('settings-form'));

    await tabs.listeners.click({ target: tabMonth });
    assert.strictEqual(tabMonth.getAttribute('aria-selected'), 'true');

    await elements.get('team-select').listeners.change({ target: { value: 'TeamB' } });
    assert.deepStrictEqual(repositoryCalls.at(-1), ['monthPlan', 'TeamB', '2026-07']);
    assert(elements.get('adp-panel').innerHTML.includes('TeamB:2026-07'));

    await elements.get('adp-panel').listeners.click({
        target: new FakeButton({
            action: 'add-self',
            slotId: '7'
        })
    });
    assert.deepStrictEqual(repositoryCalls.slice(-2), [
        ['addSelf', 'TeamB', '2026-07', '7'],
        ['monthPlan', 'TeamB', '2026-07']
    ]);
    assert.deepStrictEqual(errors, []);

    await elements.get('adp-panel').listeners.click({
        target: new FakeButton({
            action: 'add-selected',
            slotId: '8',
            targetUid: 'anna'
        })
    });
    assert.deepStrictEqual(repositoryCalls.slice(-2), [
        ['addSelected', 'TeamB', '2026-07', '8', 'anna'],
        ['monthPlan', 'TeamB', '2026-07']
    ]);

    await elements.get('adp-panel').listeners.click({
        target: new FakeButton({
            action: 'transition-status',
            targetStatus: 'planned'
        })
    });
    assert.deepStrictEqual(repositoryCalls.slice(-2), [
        ['transitionStatus', 'TeamB', '2026-07', 'planned'],
        ['monthPlan', 'TeamB', '2026-07']
    ]);

    const resolveAddSelf = lastRepository.deferAddSelf();
    const guardedButton = new FakeButton({
        action: 'add-self',
        slotId: '42'
    });
    const firstGuardedAction = elements.get('adp-panel').listeners.click({ target: guardedButton });
    await flush();
    assert.strictEqual(guardedButton.disabled, true);
    const secondGuardedAction = elements.get('adp-panel').listeners.click({ target: guardedButton });
    await flush();
    assert.strictEqual(
        repositoryCalls.filter(call => call[0] === 'addSelf' && call[3] === '42').length,
        1,
        'A disabled action button must not start a second concurrent mutation.'
    );
    resolveAddSelf();
    await Promise.all([firstGuardedAction, secondGuardedAction]);
    lastRepository.pendingAddSelf = null;
    assert.strictEqual(guardedButton.disabled, false);

    lastRepository.failMonth = '2026-08';
    await elements.get('month-input').listeners.change({ target: { value: '2026-08' } });
    assert.deepStrictEqual(repositoryCalls.at(-1), ['monthPlan', 'TeamB', '2026-08']);
    assert.strictEqual(errors.at(-1).message, 'Monat nicht verfügbar');
    assert(!elements.get('adp-panel').innerHTML.includes('TeamB:2026-07'));

    lastRepository.failMonth = '';
    await elements.get('month-input').listeners.change({ target: { value: '2026-07' } });
    assert(elements.get('adp-panel').innerHTML.includes('TeamB:2026-07'));
    lastRepository.failMonth = '2026-07';
    await elements.get('adp-panel').listeners.click({
        target: new FakeButton({
            action: 'add-self',
            slotId: '9'
        })
    });
    assert.deepStrictEqual(repositoryCalls.slice(-2), [
        ['addSelf', 'TeamB', '2026-07', '9'],
        ['monthPlan', 'TeamB', '2026-07']
    ]);
    assert.strictEqual(errors.at(-1).fallback, 'Die Änderung wurde gespeichert, aber der Monatsplan konnte nicht neu geladen werden.');
    assert(!elements.get('adp-panel').innerHTML.includes('TeamB:2026-07'));

    lastRepository.failMonth = '';
    const resolveAugust = lastRepository.deferMonth('2026-08');
    const resolveSeptember = lastRepository.deferMonth('2026-09');
    const augustLoad = elements.get('month-input').listeners.change({ target: { value: '2026-08' } });
    const septemberLoad = elements.get('month-input').listeners.change({ target: { value: '2026-09' } });
    resolveSeptember({ month: '2026-09', team: { code: 'TeamB', displayName: 'Team B' } });
    await septemberLoad;
    assert(elements.get('adp-panel').innerHTML.includes('TeamB:2026-09'));
    resolveAugust({ month: '2026-08', team: { code: 'TeamB', displayName: 'Team B' } });
    await augustLoad;
    assert(elements.get('adp-panel').innerHTML.includes('TeamB:2026-09'));
    assert(!elements.get('adp-panel').innerHTML.includes('TeamB:2026-08'));

    console.log('AdPlaner main workflow smoke test passed.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
