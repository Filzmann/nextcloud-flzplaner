const assert = require('assert');

function element() {
    return {
        checked: false,
        disabled: false,
        hidden: true,
        className: '',
        textContent: '',
        listeners: {},
        classList: { add() {} },
        addEventListener(type, listener) { this.listeners[type] = listener; }
    };
}

const confirmation = element();
const button = element();
button.disabled = true;
const notice = element();
const requests = [];
let resolveRequest;
const pendingResponse = new Promise(resolve => { resolveRequest = resolve; });

global.document = {
    getElementById(id) {
        return {
            'adp-demo-confirm': confirmation,
            'adp-demo-install': button,
            'adp-demo-notice': notice,
        }[id] || null;
    }
};
global.window = {
    LocalBase: {
        api: {
            ApiClient: class {
                async request(path, options) {
                    requests.push({ path, options });
                    return pendingResponse;
                }
            }
        }
    }
};

require('../../js/admin.js');

(async () => {
    confirmation.checked = true;
    confirmation.listeners.change();
    assert.strictEqual(button.disabled, false);
    const firstClick = button.listeners.click();
    const secondClick = button.listeners.click();
    assert.deepStrictEqual(requests, [{
        path: '/api/admin/demo-pack/install',
        options: { method: 'POST', body: '{"confirmed":true}' }
    }]);
    resolveRequest({ result: { teams: ['A', 'B', 'C'] } });
    await Promise.all([firstClick, secondClick]);
    assert.strictEqual(confirmation.checked, false);
    assert.strictEqual(button.disabled, true);
    console.log('AdPlaner admin smoke test passed.');
})().catch(error => {
    console.error(error);
    process.exit(1);
});
