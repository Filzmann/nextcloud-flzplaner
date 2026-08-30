import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';

const required = ['ADP_CDP_PORT', 'ADP_BASE_URL', 'ADP_BROWSER_EB', 'ADP_BROWSER_ASSISTANT', 'ADP_BROWSER_MEMBER', 'ADP_BROWSER_TEAM', 'ADP_BROWSER_MONTH', 'ADP_BROWSER_SCREENSHOT'];
for (const name of required) {
    if (!process.env[name]) throw new Error(`${name} fehlt.`);
}

const port = Number(process.env.ADP_CDP_PORT);
const baseUrl = process.env.ADP_BASE_URL.replace(/\/$/, '');
const ebUid = process.env.ADP_BROWSER_EB;
const assistantUid = process.env.ADP_BROWSER_ASSISTANT;
const memberUid = process.env.ADP_BROWSER_MEMBER;
const teamCode = process.env.ADP_BROWSER_TEAM;
const month = process.env.ADP_BROWSER_MONTH;
const screenshotPath = process.env.ADP_BROWSER_SCREENSHOT;

const targetResponse = await fetch(`http://127.0.0.1:${port}/json/new?about%3Ablank`, { method: 'PUT' });
if (!targetResponse.ok) throw new Error(`Chrome-Ziel konnte nicht angelegt werden: HTTP ${targetResponse.status}`);
const target = await targetResponse.json();

class CdpClient {
    constructor(url) {
        this.nextId = 1;
        this.pending = new Map();
        this.socket = new WebSocket(url);
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.socket.addEventListener('open', resolve, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });
        this.socket.addEventListener('message', event => {
            const message = JSON.parse(String(event.data));
            if (!message.id || !this.pending.has(message.id)) return;
            const { resolve, reject } = this.pending.get(message.id);
            this.pending.delete(message.id);
            if (message.error) reject(new Error(`${message.error.message} (${message.error.code})`));
            else resolve(message.result || {});
        });
    }

    call(method, params = {}) {
        const id = this.nextId++;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    close() {
        this.socket.close();
    }
}

const cdp = new CdpClient(target.webSocketDebuggerUrl);
await cdp.connect();

const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
async function evaluate(expression) {
    const result = await cdp.call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'JavaScript-Auswertung fehlgeschlagen.');
    }
    return result.result?.value;
}

async function waitFor(expression, label, timeout = 20000) {
    const deadline = Date.now() + timeout;
    let lastError = null;
    while (Date.now() < deadline) {
        try {
            if (await evaluate(`Boolean(${expression})`)) return;
        } catch (error) {
            lastError = error;
        }
        await sleep(100);
    }
    const pageState = await evaluate(`({url: location.href, title: document.title, panel: document.querySelector('#adp-panel')?.innerText?.slice(0, 500) || '', notice: document.querySelector('#adp-notice')?.innerText || ''})`);
    throw new Error(`${label} wurde nicht erreicht. Zustand: ${JSON.stringify(pageState)}${lastError ? `; letzter Fehler: ${lastError.message}` : ''}`);
}

async function navigateAs(uid) {
    await cdp.call('Network.clearBrowserCookies');
    await cdp.call('Network.clearBrowserCache');
    await cdp.call('Network.setExtraHTTPHeaders', { headers: {} });
    await cdp.call('Page.navigate', { url: `${baseUrl}/index.php/login?browser-smoke=${Date.now()}` });
    await waitFor(`document.readyState === 'complete' && document.querySelector('form[name="login"] #user') && document.querySelector('form[name="login"] #password')`, `Loginformular für ${uid}`);
    await evaluate(`(() => {
        const form = document.querySelector('form[name="login"]');
        const user = form.querySelector('#user');
        const password = form.querySelector('#password');
        user.value = ${JSON.stringify(uid)};
        password.value = ${JSON.stringify(uid)};
        user.dispatchEvent(new Event('input', {bubbles: true}));
        password.dispatchEvent(new Event('input', {bubbles: true}));
        form.requestSubmit();
        return true;
    })()`);
    await waitFor(`!location.pathname.endsWith('/login') && !document.querySelector('form[name="login"]')`, `Anmeldung für ${uid}`, 30000);
    await cdp.call('Page.navigate', { url: `${baseUrl}/index.php/apps/adplaner/?browser-smoke=${Date.now()}` });
    await waitFor(`document.readyState === 'complete'`, 'Dokumentabschluss');
    await waitFor(`document.querySelector('#adp-panel .adp-month-table')`, `Monatsplan für ${uid}`);
    assert.match(await evaluate('location.pathname'), /\/apps\/adplaner\/$/);
    assert.equal(await evaluate(`Boolean(document.querySelector('form[name="login"]'))`), false, 'Der Browser darf nicht auf der Login-Seite landen.');
}

async function selectMonth() {
    await evaluate(`(() => { const input = document.querySelector('#month-input'); input.value = ${JSON.stringify(month)}; input.dispatchEvent(new Event('change', {bubbles: true})); return true; })()`);
    await waitFor(`document.querySelector('#month-input')?.value === ${JSON.stringify(month)} && document.querySelector('.adp-section-head h2')?.textContent?.includes(${JSON.stringify(month)})`, `Monat ${month}`);
}

async function click(selector, label) {
    const clicked = await evaluate(`(() => { const element = document.querySelector(${JSON.stringify(selector)}); if (!element) return false; element.click(); return true; })()`);
    assert.equal(clicked, true, `${label} fehlt.`);
}

try {
    await cdp.call('Runtime.enable');
    await cdp.call('Page.enable');
    await cdp.call('Network.enable');
    await cdp.call('Emulation.setDeviceMetricsOverride', { width: 700, height: 700, deviceScaleFactor: 1, mobile: false });
    await cdp.call('Page.addScriptToEvaluateOnNewDocument', {
        source: `window.__adpBrowserErrors = []; window.addEventListener('error', event => window.__adpBrowserErrors.push(String(event.error || event.message))); window.addEventListener('unhandledrejection', event => window.__adpBrowserErrors.push(String(event.reason)));`,
    });

    await navigateAs(ebUid);
    await selectMonth();

    assert.equal(await evaluate(`document.querySelector('#team-select')?.value`), teamCode, 'Das synthetische Team muss ausgewählt sein.');
    assert.equal(await evaluate(`document.querySelector('[data-action="transition-status"]')?.dataset.targetStatus`), 'planned', 'Die EB muss den Entwurf festschreiben können.');
    assert.equal(await evaluate(`Boolean(document.querySelector('textarea[data-note-date]'))`), true, 'Die EB benötigt editierbare Tagesbemerkungen.');
    assert.equal(await evaluate(`Boolean(Array.from(document.querySelectorAll('[data-action="add-selected"]')).find(button => button.dataset.targetUid === ${JSON.stringify(assistantUid)}))`), true, 'Die aktive Assistenz muss auswählbar sein.');
    assert.equal(await evaluate(`Boolean(Array.from(document.querySelectorAll('[data-action="add-selected"]')).find(button => button.dataset.targetUid === ${JSON.stringify(ebUid)}))`), false, 'Das EB-Konto darf nicht schichtfähig sein.');

    await click('#month-prev', 'Schalter für den vorherigen Monat');
    await waitFor(`document.querySelector('#month-input')?.value === '2098-10'`, 'Vorheriger Monat');
    await click('#month-next', 'Schalter für den nächsten Monat');
    await waitFor(`document.querySelector('#month-input')?.value === ${JSON.stringify(month)}`, 'Rückkehr zum Prüfmonat');

    await evaluate(`document.querySelector('#adp-tab-month').focus(); document.querySelector('#adp-tab-month').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowRight', bubbles: true}));`);
    await waitFor(`document.querySelector('#adp-tab-settings')?.getAttribute('aria-selected') === 'true' && document.activeElement?.id === 'adp-tab-settings'`, 'Tastaturnavigation zum Einstellungstab');
    await waitFor(`document.querySelector('#settings-form')`, 'EB-Einstellungsformular');
    await click('#adp-tab-month', 'Wunschplan-Tab');
    await waitFor(`document.querySelector('#adp-panel .adp-month-table')`, 'Rückkehr zum Monatsplan');

    await click('[data-action="open-assignment-picker"]', 'Zuteilungsschalter');
    await waitFor(`Array.from(document.querySelectorAll('[data-action="add-selected"]')).some(button => !button.closest('[hidden]') && button.dataset.targetUid === ${JSON.stringify(assistantUid)})`, 'Geöffnete Assistenzwahl');
    await evaluate(`Array.from(document.querySelectorAll('[data-action="add-selected"]')).find(button => button.dataset.targetUid === ${JSON.stringify(assistantUid)}).click()`);
    await waitFor(`Array.from(document.querySelectorAll('.adp-chip')).some(chip => chip.textContent.includes(${JSON.stringify(assistantUid)}))`, 'Persistierte Fremdzuweisung');

    await evaluate(`(() => { const textarea = document.querySelector('textarea[data-note-date]'); window.__adpNoteTextareaBeforeSave = textarea; textarea.value = 'Synthetische Browserbemerkung'; textarea.dispatchEvent(new Event('input', {bubbles: true})); textarea.parentElement.querySelector('[data-action="save-note"]').click(); return true; })()`);
    await waitFor(`document.querySelector('textarea[data-note-date]') !== window.__adpNoteTextareaBeforeSave && Array.from(document.querySelectorAll('textarea[data-note-date]')).some(textarea => textarea.value === 'Synthetische Browserbemerkung')`, 'Persistierte und neu geladene Tagesbemerkung');

    await click('#adp-tab-settings', 'Einstellungstab');
    await waitFor(`document.querySelector('#settings-form')`, 'Einstellungsformular');
    await evaluate(`(() => { const form = document.querySelector('#settings-form'); form.querySelector('[name="displayName"]').value = 'Browserteam ${teamCode}'; form.querySelector('[name="shiftLabel"]').value = 'Früh Browser'; form.requestSubmit(); return true; })()`);
    await waitFor(`document.querySelector('#adp-tab-month')?.getAttribute('aria-selected') === 'true' && Array.from(document.querySelectorAll('.adp-month-table thead th')).some(th => th.textContent.includes('Früh Browser'))`, 'Persistierte Schichtkonfiguration');

    await click('[data-action="transition-status"][data-target-status="planned"]', 'Festschreiben als geplant');
    await waitFor(`document.querySelector('[data-plan-status="planned"]')`, 'Status geplant');
    await click('[data-action="transition-status"][data-target-status="approved"]', 'Genehmigung');
    await waitFor(`document.querySelector('[data-plan-status="approved"]')`, 'Status genehmigt');
    assert.equal(await evaluate(`Boolean(document.querySelector('textarea[data-note-date], [data-action="add-selected"], [data-action="remove-candidate"]'))`), false, 'Ein genehmigter Plan muss vollständig gesperrt sein.');

    const layout = await evaluate(`(() => { const root = document.querySelector('#adplaner-app'); const wrap = document.querySelector('.adp-table-wrap'); const last = document.querySelector('.adp-month-table th:last-child'); const rootStyle = getComputedStyle(root); return { rootOverflowY: rootStyle.overflowY, rootBackground: rootStyle.backgroundColor, rootScrollable: root.scrollHeight > root.clientHeight, wrapOverflowX: getComputedStyle(wrap).overflowX, wrapScrollable: wrap.scrollWidth > wrap.clientWidth, lastPosition: getComputedStyle(last).position, lastRight: getComputedStyle(last).right, panelLabel: document.querySelector('#adp-panel')?.getAttribute('aria-labelledby') }; })()`);
    assert.equal(layout.rootOverflowY, 'auto');
    assert.notEqual(layout.rootBackground, 'rgba(0, 0, 0, 0)');
    assert.equal(layout.rootScrollable, true, 'Der App-Root muss bei langem Plan vertikal scrollen.');
    assert.equal(layout.wrapOverflowX, 'auto');
    assert.equal(layout.wrapScrollable, true, 'Der schmale Plan-Viewport muss horizontal scrollen.');
    assert.equal(layout.lastPosition, 'sticky');
    assert.equal(layout.lastRight, '0px');
    assert.equal(layout.panelLabel, 'adp-tab-month');

    const screenshot = await cdp.call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await writeFile(screenshotPath, Buffer.from(screenshot.data, 'base64'));

    await click('[data-action="transition-status"][data-target-status="planned"]', 'Aufheben der Genehmigung');
    await waitFor(`document.querySelector('[data-plan-status="planned"]') && document.querySelector('textarea[data-note-date]')`, 'Wieder entsperrter Plan');

    await navigateAs(memberUid);
    await selectMonth();
    assert.equal(await evaluate(`Boolean(document.querySelector('[data-action="transition-status"], textarea[data-note-date], [data-action="add-selected"]'))`), false, 'Ein normales Teammitglied darf keine EB-Steuerung erhalten.');
    assert.equal(await evaluate(`Boolean(document.querySelector('[data-action="add-self"]'))`), true, 'Ein normales Teammitglied muss einen eigenen Wunsch eintragen können.');
    await click('[data-action="add-self"]', 'Eigener Wunsch');
    await waitFor(`Array.from(document.querySelectorAll('.adp-chip')).some(chip => chip.textContent.includes(${JSON.stringify(memberUid)}))`, 'Persistierter eigener Wunsch');
    await click('[data-action="set-candidate-preference"][aria-label="Lieblingsschicht"]', 'Lieblingsschicht-Markierung');
    await waitFor(`Array.from(document.querySelectorAll('[data-candidate-chip]')).some(chip => chip.textContent.includes(${JSON.stringify(memberUid)}) && chip.textContent.includes('⭐'))`, 'Persistierte Lieblingsschicht');
    await evaluate(`(() => { const chip = Array.from(document.querySelectorAll('[data-candidate-chip]')).find(item => item.textContent.includes(${JSON.stringify(memberUid)})); const details = chip.querySelector('.adp-candidate-note'); details.open = true; const note = details.querySelector('[data-candidate-note]'); window.__adpCandidateNoteBeforeSave = note; note.value = 'Synthetischer Schichthinweis'; details.querySelector('[data-action="save-candidate-note"]').click(); return true; })()`);
    await waitFor(`Array.from(document.querySelectorAll('[data-candidate-note]')).some(note => note !== window.__adpCandidateNoteBeforeSave && note.value === 'Synthetischer Schichthinweis')`, 'Persistierte Schichtanmerkung');
    await click('#adp-tab-settings', 'Einstellungstab des normalen Mitglieds');
    await waitFor(`document.querySelector('.adp-settings-readonly')`, 'Schreibgeschützte Einstellungen');
    assert.equal(await evaluate(`Boolean(document.querySelector('#settings-form'))`), false, 'Ein normales Mitglied darf kein Einstellungsformular erhalten.');
    assert.equal(await evaluate(`Boolean(document.querySelector('#personal-workload-form'))`), true, 'Ein schichtfähiges Mitglied benötigt persönliche Schichtgrenzen.');
    await evaluate(`(() => { const form = document.querySelector('#personal-workload-form'); window.__adpWorkloadFormBeforeSave = form; form.querySelector('[name="weeklyMin"]').value = '3'; form.querySelector('[name="weeklyMax"]').value = '5'; form.querySelector('[name="monthlyMin"]').value = '10'; form.querySelector('[name="monthlyMax"]').value = '15'; form.requestSubmit(); return true; })()`);
    await waitFor(`document.querySelector('#personal-workload-form') !== window.__adpWorkloadFormBeforeSave && document.querySelector('#personal-workload-form [name="monthlyMin"]')?.value === '10'`, 'Persistierte persönliche Schichtgrenzen');

    await navigateAs(ebUid);
    await selectMonth();
    assert.equal(await evaluate(`Boolean(document.querySelector('.adp-workload-overview[open]'))`), true, 'Die EB benötigt die geöffnete Team-Auslastungsübersicht.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('.adp-workload--under')).some(row => row.textContent.includes(${JSON.stringify(memberUid)}))`), true, 'Ein Teammitglied unter Minimum muss für die EB kräftig und textlich markiert sein.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('[data-candidate-chip].adp-chip--under')).some(chip => chip.textContent.includes(${JSON.stringify(memberUid)}))`), true, 'Schichten eines Teammitglieds unter Minimum müssen kräftig markiert sein.');

    const browserErrors = await evaluate(`window.__adpBrowserErrors || []`);
    assert.deepEqual(browserErrors, [], `Die Oberfläche erzeugte Browserfehler: ${browserErrors.join('; ')}`);
    console.log('AdPlaner synthetischer DDEV-Browser-Smoke: OK');
} finally {
    cdp.close();
}
