import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';

const appRoot = resolve(import.meta.dirname, '../..');
const bundleRoot = resolve(appRoot, 'js/shared');
const lock = JSON.parse(readFileSync(resolve(bundleRoot, 'flz-shared.lock.json'), 'utf8'));
if (lock.component !== 'browser'
    || lock.app_id !== 'flzplaner'
    || lock.app_namespace !== 'FlzPlaner'
    || lock.browser_global !== 'window.OCA.FlzPlaner.Shared'
    || Object.keys(lock.packages || {}).join(',') !== 'browser') {
    throw new Error('Der Browser-Lock beschreibt nicht ausschließlich das isolierte flzplaner-Bundle.');
}
readFileSync(resolve(bundleRoot, 'LICENSE'), 'utf8');

const requests = [];
const noticeElement = { className: '', hidden: true, textContent: '' };
const window = {
    fetch: async (url, options) => {
        requests.push({ url, options });
        return {
            ok: true,
            status: 200,
            text: async () => JSON.stringify({
                currentUser: { uid: 'person-a' },
                teams: [{ code: 'TeamA', displayName: 'Team A' }],
            }),
        };
    },
};
window.window = window;
window.OC = {
    generateUrl: path => `/index.php${path}`,
    requestToken: 'planer-token',
};

const context = vm.createContext({
    document: { getElementById: id => id === 'flz-planer-notice' ? noticeElement : null },
    encodeURIComponent,
    Error,
    JSON,
    Number,
    Object,
    OC: window.OC,
    String,
    window,
});

for (const relative of [
    'js/shared/api-client.js',
    'js/shared/model.js',
    'js/shared/overlay.js',
    'js/shared/repository.js',
    'js/shared/ui.js',
    'js/modules/api.js',
    'js/modules/ui.js',
    'js/models/assistant.js',
    'js/models/shift-candidate.js',
    'js/models/shift-definition.js',
    'js/models/shift-slot.js',
    'js/models/team-settings.js',
    'js/models/team.js',
    'js/models/day-note.js',
    'js/repositories/plan-repository.js',
]) {
    const filename = resolve(appRoot, relative);
    vm.runInContext(readFileSync(filename, 'utf8'), context, { filename });
}

const shared = window.OCA?.FlzPlaner?.Shared;
if (!shared?.api?.ApiClient || !shared?.models?.Model || !shared?.repositories?.Repository || !shared?.ui?.Notice || !shared?.ui?.Overlay || !shared?.ui?.AdminAccessOverlay) {
    throw new Error('Das app-lokale Browser-Bundle stellt seine Primitives nicht vollständig isoliert bereit.');
}
if ('LocalBase' in window) {
    throw new Error('Das app-lokale Browser-Bundle erzeugt ein window.LocalBase-Global.');
}

const repository = new window.FlzPlaner.repositories.PlanRepository(window.FlzPlaner.api);
const state = await repository.state();
if (!(state.teams[0] instanceof window.FlzPlaner.models.Team)
    || requests.length !== 1
    || requests[0].url !== '/index.php/apps/flzplaner/api/state'
    || requests[0].options.headers.requesttoken !== 'planer-token') {
    throw new Error('ApiClient, Modelle und PlanRepository bewahren URL-, CSRF- und Hydrationsvertrag nicht gemeinsam.');
}

window.FlzPlaner.ui.showNotice('Bereit');
if (noticeElement.textContent !== 'Bereit' || noticeElement.hidden) {
    throw new Error('Die gebündelte Notice bewahrt ihren sichtbaren Erfolgszustand nicht.');
}
if (window.FlzPlaner.ui.dayHeader({ weekday: 2, dayOfMonth: '<7>' }) !== 'Di<span>&lt;7&gt;</span>') {
    throw new Error('Das app-lokale UI-Primitive bewahrt den Escaping-Vertrag nicht.');
}

console.log('FlzPlaner isolated Category-A browser consumer smoke passed');
