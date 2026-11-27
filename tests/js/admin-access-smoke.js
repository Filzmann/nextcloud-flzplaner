const assert = require('assert');

function element() {
    return {
        checked: false,
        disabled: false,
        hidden: false,
        textContent: '',
        attributes: {},
        focused: false,
        listeners: {},
        dataset: {},
        children: [],
        elements: { enabled: { checked: true } },
        addEventListener(type, listener) { (this.listeners[type] = this.listeners[type] || []).push(listener); },
        append(child) { this.children.push(child); },
        replaceChildren() { this.children = []; },
        setAttribute(name, value) { this.attributes[name] = String(value); },
        getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; },
        focus() { this.focused = true; },
    };
}

const form = element();
const history = element();
const status = element();
const overlay = element();
overlay.hidden = true;
const trigger = element();
const closeButton = element();
const doc = element();

const byId = {
    'flz-planer-full-access-form': form,
    'flz-planer-full-access-history': history,
    'flz-planer-full-access-status': status,
    'flz-planer-admin-access-overlay': overlay,
    'flz-planer-admin-access-warning': trigger,
    'flz-planer-admin-access-overlay-close': closeButton,
};

const requests = [];

global.FormData = class {
    get(name) { return { enabled: 'on', targetUid: ' admin-target ', durationMinutes: '60' }[name] ?? null; }
};
global.document = {
    getElementById(id) { return byId[id] || null; },
    createElement() { return element(); },
    addEventListener(type, listener) { doc.addEventListener(type, listener); },
    querySelectorAll() { return []; },
    head: { appendChild() {} },
};
global.window = {
    OCA: { FlzPlaner: { Shared: { api: { ApiClient: class { async request(path, options = {}) { requests.push({ path, options }); return path === '/api/admin/full-access' && !options.method ? { history: [] } : {}; } } } } } },
};

require('../../js/shared/overlay.js');
require('../../js/admin-access.js');

const fire = async (el, type, event) => { for (const listener of el.listeners[type] || []) await listener(event); };

(async () => {
    await Promise.resolve();
    // Ohne geöffnetes Overlay wird keine Historie geladen.
    assert.deepStrictEqual(requests, []);

    // Warn-Icon öffnet das Overlay und lädt die Historie.
    await fire(trigger, 'click', { target: trigger });
    assert.strictEqual(overlay.hidden, false);
    assert.strictEqual(trigger.getAttribute('aria-expanded'), 'true');
    assert.strictEqual(closeButton.focused, true);
    assert.deepStrictEqual(requests.shift(), { path: '/api/admin/full-access', options: {} });

    await fire(form, 'submit', { preventDefault() {} });
    assert.deepStrictEqual(requests.splice(0, 2), [
        { path: '/api/admin/full-access', options: { method: 'POST', body: JSON.stringify({ targetUid: 'admin-target', durationMinutes: 60 }) } },
        { path: '/api/admin/full-access', options: {} },
    ]);
    const revoke = element();
    revoke.dataset.revokeUid = 'admin-target';
    await fire(history, 'click', { target: { closest() { return revoke; } } });
    assert.deepStrictEqual(requests.splice(0, 2), [
        { path: '/api/admin/full-access/admin-target', options: { method: 'DELETE' } },
        { path: '/api/admin/full-access', options: {} },
    ]);
    assert.strictEqual(status.textContent, 'Der Vollzugriff wurde widerrufen.');

    // Escape schließt das Overlay und gibt den Fokus zurück.
    await fire(doc, 'keydown', { key: 'Escape' });
    assert.strictEqual(overlay.hidden, true);
    assert.strictEqual(trigger.getAttribute('aria-expanded'), 'false');
    assert.strictEqual(trigger.focused, true);

    // Schließen-Button und Backdrop-Klick schließen ebenfalls.
    await fire(trigger, 'click', { target: trigger });
    await fire(closeButton, 'click', {});
    assert.strictEqual(overlay.hidden, true);
    await fire(trigger, 'click', { target: trigger });
    await fire(overlay, 'click', { target: overlay });
    assert.strictEqual(overlay.hidden, true);

    assert.deepStrictEqual(requests.splice(0), [
        { path: '/api/admin/full-access', options: {} },
        { path: '/api/admin/full-access', options: {} },
    ]);
    console.log('FlzPlaner admin access overlay smoke test passed.');
})().catch(error => { console.error(error); process.exit(1); });
