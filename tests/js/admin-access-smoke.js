const assert = require('assert');

function element() {
    return {
        checked: false,
        disabled: false,
        textContent: '',
        listeners: {},
        dataset: {},
        children: [],
        elements: { enabled: { checked: true } },
        addEventListener(type, listener) { this.listeners[type] = listener; },
        append(child) { this.children.push(child); },
        replaceChildren() { this.children = []; },
        setAttribute(name, value) { this[name] = value; },
    };
}

const form = element();
const history = element();
const status = element();
const requests = [];

global.FormData = class {
    get(name) { return { enabled: 'on', targetUid: ' admin-target ', durationMinutes: '60' }[name] ?? null; }
};
global.document = {
    getElementById(id) { return { 'flz-planer-full-access-form': form, 'flz-planer-full-access-history': history, 'flz-planer-full-access-status': status }[id] || null; },
    createElement() { return element(); },
};
global.window = {
    LocalBase: { api: { ApiClient: class { async request(path, options = {}) { requests.push({ path, options }); return path === '/api/admin/full-access' && !options.method ? { history: [] } : {}; } } } },
};

require('../../js/admin-access.js');

async function flush() { for (let i = 0; i < 8; i++) await Promise.resolve(); }

(async () => {
    await flush();
    assert.deepStrictEqual(requests.shift(), { path: '/api/admin/full-access', options: {} });
    await form.listeners.submit({ preventDefault() {} });
    assert.deepStrictEqual(requests.splice(0, 2), [
        { path: '/api/admin/full-access', options: { method: 'POST', body: JSON.stringify({ targetUid: 'admin-target', durationMinutes: 60 }) } },
        { path: '/api/admin/full-access', options: {} },
    ]);
    const revoke = element();
    revoke.dataset.revokeUid = 'admin-target';
    await history.listeners.click({ target: { closest() { return revoke; } } });
    assert.deepStrictEqual(requests, [
        { path: '/api/admin/full-access/admin-target', options: { method: 'DELETE' } },
        { path: '/api/admin/full-access', options: {} },
    ]);
    assert.strictEqual(status.textContent, 'Der Vollzugriff wurde widerrufen.');
    console.log('FlzPlaner admin access smoke test passed.');
})().catch(error => { console.error(error); process.exit(1); });
