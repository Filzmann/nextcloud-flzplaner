import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';

const required = ['FLZP_CDP_PORT', 'FLZP_BASE_URL', 'FLZP_BROWSER_EB', 'FLZP_BROWSER_ASSISTANT', 'FLZP_BROWSER_MEMBER', 'FLZP_BROWSER_TEAM', 'FLZP_BROWSER_MONTH', 'FLZP_BROWSER_SCREENSHOT'];
for (const name of required) {
    if (!process.env[name]) throw new Error(`${name} fehlt.`);
}

const port = Number(process.env.FLZP_CDP_PORT);
const baseUrl = process.env.FLZP_BASE_URL.replace(/\/$/, '');
const ebUid = process.env.FLZP_BROWSER_EB;
const assistantUid = process.env.FLZP_BROWSER_ASSISTANT;
const memberUid = process.env.FLZP_BROWSER_MEMBER;
const teamCode = process.env.FLZP_BROWSER_TEAM;
const month = process.env.FLZP_BROWSER_MONTH;
const screenshotPath = process.env.FLZP_BROWSER_SCREENSHOT;

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
    const pageState = await evaluate(`({url: location.href, title: document.title, panel: document.querySelector('#flz-planer-panel')?.innerText?.slice(0, 500) || '', notice: document.querySelector('#flz-planer-notice')?.innerText || ''})`);
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
    await cdp.call('Page.navigate', { url: `${baseUrl}/index.php/apps/flzplaner/?browser-smoke=${Date.now()}` });
    await waitFor(`document.readyState === 'complete'`, 'Dokumentabschluss');
    await waitFor(`document.querySelector('#flz-planer-panel .flz-planer-month-table')`, `Monatsplan für ${uid}`);
    assert.match(await evaluate('location.pathname'), /\/apps\/flzplaner\/$/);
    assert.equal(await evaluate(`Boolean(document.querySelector('form[name="login"]'))`), false, 'Der Browser darf nicht auf der Login-Seite landen.');
}

async function selectMonth() {
    await evaluate(`(() => { const input = document.querySelector('#month-input'); input.value = ${JSON.stringify(month)}; input.dispatchEvent(new Event('change', {bubbles: true})); return true; })()`);
    await waitFor(`document.querySelector('#month-input')?.value === ${JSON.stringify(month)} && document.querySelector('.flz-planer-section-head h2')?.textContent?.includes(${JSON.stringify(month)})`, `Monat ${month}`);
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

    await evaluate(`document.querySelector('#flz-planer-tab-month').focus(); document.querySelector('#flz-planer-tab-month').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowRight', bubbles: true}));`);
    await waitFor(`document.querySelector('#flz-planer-tab-workload')?.getAttribute('aria-selected') === 'true' && document.activeElement?.id === 'flz-planer-tab-workload'`, 'Tastaturnavigation zum Auslastungstab');
    await evaluate(`document.querySelector('#flz-planer-tab-workload').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowRight', bubbles: true}));`);
    await waitFor(`document.querySelector('#flz-planer-tab-settings')?.getAttribute('aria-selected') === 'true' && document.activeElement?.id === 'flz-planer-tab-settings'`, 'Tastaturnavigation zum Einstellungstab');
    await waitFor(`document.querySelector('#settings-form')`, 'EB-Einstellungsformular');
    await click('#flz-planer-tab-month', 'Wunschplan-Tab');
    await waitFor(`document.querySelector('#flz-planer-panel .flz-planer-month-table')`, 'Rückkehr zum Monatsplan');

    await click('[data-action="open-assignment-picker"]', 'Zuteilungsschalter');
    await waitFor(`Array.from(document.querySelectorAll('[data-action="add-selected"]')).some(button => !button.closest('[hidden]') && button.dataset.targetUid === ${JSON.stringify(assistantUid)})`, 'Geöffnete Assistenzwahl');
    await evaluate(`Array.from(document.querySelectorAll('[data-action="add-selected"]')).find(button => button.dataset.targetUid === ${JSON.stringify(assistantUid)}).click()`);
    await waitFor(`Array.from(document.querySelectorAll('.flz-planer-chip')).some(chip => chip.textContent.includes(${JSON.stringify(assistantUid)}))`, 'Persistierte Fremdzuweisung');

    await evaluate(`(() => { const textarea = document.querySelector('textarea[data-note-date]'); window.__adpNoteTextareaBeforeSave = textarea; textarea.value = 'Synthetische Browserbemerkung'; textarea.dispatchEvent(new Event('input', {bubbles: true})); textarea.parentElement.querySelector('[data-action="save-note"]').click(); return true; })()`);
    await waitFor(`document.querySelector('textarea[data-note-date]') !== window.__adpNoteTextareaBeforeSave && Array.from(document.querySelectorAll('textarea[data-note-date]')).some(textarea => textarea.value === 'Synthetische Browserbemerkung')`, 'Persistierte und neu geladene Tagesbemerkung');

    await click('#flz-planer-tab-settings', 'Einstellungstab');
    await waitFor(`document.querySelector('#settings-form')`, 'Einstellungsformular');
    await evaluate(`(() => { const form = document.querySelector('#settings-form'); form.querySelector('[name="displayName"]').value = 'Browserteam ${teamCode}'; form.querySelector('[name="shiftLabel"]').value = 'Früh Browser'; form.requestSubmit(); return true; })()`);
    await waitFor(`document.querySelector('#flz-planer-tab-month')?.getAttribute('aria-selected') === 'true' && Array.from(document.querySelectorAll('.flz-planer-month-table thead th')).some(th => th.textContent.includes('Früh Browser'))`, 'Persistierte Schichtkonfiguration');

    await navigateAs(assistantUid);
    await selectMonth();
    await click('#flz-planer-tab-settings', 'Einstellungen der ersten Assistenz');
    await waitFor(`document.querySelector('#personal-regular-shifts-form')`, 'Regelmäßige Schichten der ersten Assistenz');
    await evaluate(`(() => { const form=document.querySelector('#personal-regular-shifts-form'); window.__adpAssistantRegularBeforeSave=form; form.querySelector('[name="regularShift"]').checked=true; form.requestSubmit(); return true; })()`);
    await waitFor(`document.querySelector('#personal-regular-shifts-form') !== window.__adpAssistantRegularBeforeSave && Boolean(document.querySelector('#personal-regular-shifts-form [name="regularShift"]:checked'))`, 'Persistierte regelmäßige Schicht der ersten Assistenz');

    await navigateAs(ebUid);
    await selectMonth();

    await click('[data-action="transition-status"][data-target-status="planned"]', 'Festschreiben als geplant');
    await waitFor(`document.querySelector('[data-plan-status="planned"]')`, 'Status geplant');
    await click('[data-action="transition-status"][data-target-status="approved"]', 'Genehmigung');
    await waitFor(`document.querySelector('[data-plan-status="approved"]')`, 'Status genehmigt');
    assert.equal(await evaluate(`Boolean(document.querySelector('textarea[data-note-date], [data-action="add-selected"], [data-action="remove-candidate"]'))`), false, 'Ein genehmigter Plan muss vollständig gesperrt sein.');

    const layout = await evaluate(`(() => { const root = document.querySelector('#flzplaner-app'); const desktop = document.querySelector('.flz-planer-desktop-plan'); const mobile = document.querySelector('.flz-planer-mobile-plan'); const firstDay = document.querySelector('.flz-planer-mobile-day'); const firstTab = document.querySelector('.flz-planer-tab'); const rootStyle = getComputedStyle(root); return { rootOverflowY: rootStyle.overflowY, rootBackground: rootStyle.backgroundColor, rootScrollable: root.scrollHeight > root.clientHeight, desktopDisplay: getComputedStyle(desktop).display, mobileDisplay: getComputedStyle(mobile).display, mobileDayVisible: Boolean(firstDay && firstDay.getClientRects().length), tabMinHeight: getComputedStyle(firstTab).minHeight, viewportOverflowsX: document.documentElement.scrollWidth > document.documentElement.clientWidth, panelLabel: document.querySelector('#flz-planer-panel')?.getAttribute('aria-labelledby') }; })()`);
    assert.equal(layout.rootOverflowY, 'auto');
    assert.notEqual(layout.rootBackground, 'rgba(0, 0, 0, 0)');
    assert.equal(layout.rootScrollable, true, 'Der App-Root muss bei langem Plan vertikal scrollen.');
    assert.equal(layout.desktopDisplay, 'none', 'Die breite Planmatrix muss im schmalen Viewport ausgeblendet sein.');
    assert.equal(layout.mobileDisplay, 'grid', 'Der schmale Viewport muss die vertikale Tagesliste zeigen.');
    assert.equal(layout.mobileDayVisible, true, 'Mindestens ein mobiler Planungstag muss sichtbar sein.');
    assert.equal(layout.tabMinHeight, '44px', 'Mobile Tab-Schalter müssen das Mindest-Touchziel einhalten.');
    assert.equal(layout.viewportOverflowsX, false, 'Die mobile Tagesliste darf keinen horizontalen Seiten-Scroll erzeugen.');
    assert.equal(layout.panelLabel, 'flz-planer-tab-month');

    const screenshot = await cdp.call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await writeFile(screenshotPath, Buffer.from(screenshot.data, 'base64'));

    await click('[data-action="transition-status"][data-target-status="planned"]', 'Aufheben der Genehmigung');
    await waitFor(`document.querySelector('[data-plan-status="planned"]') && document.querySelector('textarea[data-note-date]')`, 'Wieder entsperrter Plan');

    await navigateAs(memberUid);
    await selectMonth();
    assert.equal(await evaluate(`Boolean(document.querySelector('[data-action="transition-status"], textarea[data-note-date], [data-action="add-selected"]'))`), false, 'Ein normales Teammitglied darf keine EB-Steuerung erhalten.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('[data-action="add-self"]')).some(button => button.getClientRects().length)`), true, 'Ein normales Teammitglied muss einen eigenen Wunsch eintragen können.');
    await evaluate(`(() => { const button=Array.from(document.querySelectorAll('[data-action="add-self"]')).find(item => item.getClientRects().length); window.__adpMemberSlot=button.dataset.slotId; button.click(); return true; })()`);
    await waitFor(`Array.from(document.querySelectorAll('[data-candidate-chip]')).some(chip => chip.dataset.slotId === window.__adpMemberSlot && chip.textContent.includes(${JSON.stringify(memberUid)}))`, 'Persistierter eigener Wunsch');
    assert.equal(await evaluate(`Boolean(document.querySelector('[data-candidate-chip] .flz-planer-chip-status [aria-hidden="true"]'))`), false, 'Die Auslastungsmarkierung darf kein missverständliches schwarzes Dreieck enthalten.');
    assert.equal(await evaluate(`Boolean(document.querySelector('.flz-planer-preference-marker--neutral'))`), false, 'Ein unmarkierter Chip darf keinen Platzhalterstern zeigen.');
    await evaluate(`Array.from(document.querySelectorAll('[data-candidate-chip]')).find(item => item.getClientRects().length && item.dataset.slotId === window.__adpMemberSlot && item.textContent.includes(${JSON.stringify(memberUid)})).focus()`);
    assert.equal(await evaluate(`getComputedStyle(Array.from(document.querySelectorAll('[data-candidate-chip]')).find(item => item.getClientRects().length && item.dataset.slotId === window.__adpMemberSlot && item.textContent.includes(${JSON.stringify(memberUid)})).querySelector('.flz-planer-preference-panel')).display`), 'flex', 'Tastaturfokus im Chip muss das Aktionsoverlay öffnen.');
    assert.equal(await evaluate(`getComputedStyle(Array.from(document.querySelectorAll('.flz-planer-preference-option--favorite > span')).find(item => item.getClientRects().length)).color`), 'rgb(245, 179, 1)', 'Der Stern im Overlay muss gelb sein.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('.flz-planer-preference-option--favorite')).find(item => item.getClientRects().length)?.title`), 'Als Lieblingsschicht markieren', 'Der Stern braucht einen Hover-Tooltip.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('.flz-planer-preference-option--emergency')).find(item => item.getClientRects().length)?.title`), 'Nur wenn sonst niemand kann', 'Die Rettungsboje braucht einen Hover-Tooltip.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('[data-action="open-candidate-note-editor"]')).find(item => item.getClientRects().length)?.title`), 'Anmerkung hinzufügen', 'Das Infozeichen braucht einen Hover-Tooltip.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('.flz-planer-preference-panel [data-action="set-candidate-preference"]')).filter(item => item.getClientRects().length).length`), 2, 'Nur Lieblings- und Notfallschicht dürfen als Status angeboten werden.');
    assert.equal(await evaluate(`Boolean(Array.from(document.querySelectorAll('.flz-planer-preference-option--emergency')).find(button => button.getClientRects().length && button.textContent.includes('🛟')))`), true, 'Das Reserve-/Notfallsymbol muss als Rettungsboje dargestellt werden.');
    await evaluate(`(() => { const chip=Array.from(document.querySelectorAll('[data-candidate-chip]')).find(item => item.getClientRects().length && item.dataset.slotId === window.__adpMemberSlot && item.textContent.includes(${JSON.stringify(memberUid)})); chip.querySelector('[data-action="set-candidate-preference"][aria-label="Als Lieblingsschicht markieren"]').click(); return true; })()`);
    await waitFor(`Array.from(document.querySelectorAll('[data-candidate-chip]')).some(chip => chip.dataset.slotId === window.__adpMemberSlot && chip.textContent.includes(${JSON.stringify(memberUid)}) && chip.textContent.includes('★'))`, 'Persistierte Lieblingsschicht');
    await evaluate(`(() => { const chip = Array.from(document.querySelectorAll('[data-candidate-chip]')).find(item => item.getClientRects().length && item.dataset.slotId === window.__adpMemberSlot && item.textContent.includes(${JSON.stringify(memberUid)})); chip.focus(); chip.querySelector('[data-action="open-candidate-note-editor"]').click(); return true; })()`);
    await waitFor(`Array.from(document.querySelectorAll('[data-candidate-note-entry][data-slot-id="'+window.__adpMemberSlot+'"][data-target-uid=${JSON.stringify(memberUid)}]')).some(entry => entry.getClientRects().length && entry.querySelector('.flz-planer-shift-note-editor:not([hidden]) [data-candidate-note]'))`, 'Geöffneter exakter Schichtanmerkungseditor');
    await evaluate(`(() => { const entry=Array.from(document.querySelectorAll('[data-candidate-note-entry][data-slot-id="'+window.__adpMemberSlot+'"][data-target-uid=${JSON.stringify(memberUid)}]')).find(item => item.getClientRects().length); window.__adpCandidateEntryBeforeSave=entry; entry.querySelector('[data-candidate-note]').value='Synthetischer Schichthinweis'; entry.querySelector('[data-action="save-candidate-note"]').click(); return true; })()`);
    await waitFor(`(() => { const entry=Array.from(document.querySelectorAll('[data-candidate-note-entry][data-slot-id="'+window.__adpMemberSlot+'"][data-target-uid=${JSON.stringify(memberUid)}]')).find(item => item.getClientRects().length); return entry && entry !== window.__adpCandidateEntryBeforeSave && entry.querySelector('.flz-planer-shift-note-display')?.textContent.includes('Synthetischer Schichthinweis'); })()`, 'Persistierte Schichtanmerkung');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('.flz-planer-shift-note-display')).some(note => note.textContent.includes(${JSON.stringify(memberUid)}) && note.textContent.includes('Synthetischer Schichthinweis'))`), true, 'Die gespeicherte Schichtanmerkung muss in der Bemerkungsspalte erscheinen.');
    assert.equal(await evaluate(`Boolean(document.querySelector('[data-action="delete-candidate-note"]'))`), true, 'Der Ersteller muss seine Schichtanmerkung in der Bemerkungsspalte löschen können.');
    await click('#flz-planer-tab-settings', 'Einstellungstab des normalen Mitglieds');
    await waitFor(`document.querySelector('.flz-planer-settings-readonly')`, 'Schreibgeschützte Einstellungen');
    assert.equal(await evaluate(`Boolean(document.querySelector('#settings-form'))`), false, 'Ein normales Mitglied darf kein Einstellungsformular erhalten.');
    assert.equal(await evaluate(`Boolean(document.querySelector('#personal-workload-form'))`), true, 'Ein schichtfähiges Mitglied benötigt persönliche Schichtgrenzen.');
    assert.equal(await evaluate(`Boolean(document.querySelector('#personal-regular-shifts-form'))`), true, 'Ein schichtfähiges Mitglied benötigt persönliche regelmäßige Festschichten.');
    await evaluate(`(() => { const form=document.querySelector('#personal-regular-shifts-form'); window.__adpRegularFormBeforeSave=form; const first=form.querySelector('[name="regularShift"]'); first.checked=true; form.requestSubmit(); return true; })()`);
    await waitFor(`document.querySelector('#personal-regular-shifts-form') !== window.__adpRegularFormBeforeSave && Boolean(document.querySelector('#personal-regular-shifts-form [name="regularShift"]:checked'))`, 'Persistierte regelmäßige Festschicht');
    await evaluate(`(() => { const form = document.querySelector('#personal-workload-form'); window.__adpWorkloadFormBeforeSave = form; form.querySelector('[name="weeklyMin"]').value = '3'; form.querySelector('[name="weeklyMax"]').value = '5'; form.querySelector('[name="monthlyMin"]').value = '10'; form.querySelector('[name="monthlyMax"]').value = '15'; form.requestSubmit(); return true; })()`);
    await waitFor(`document.querySelector('#personal-workload-form') !== window.__adpWorkloadFormBeforeSave && document.querySelector('#personal-workload-form [name="monthlyMin"]')?.value === '10'`, 'Persistierte persönliche Schichtgrenzen');
    await click('#flz-planer-tab-month', 'Wunschplan mit Festschichtkonflikt');
    await waitFor(`Array.from(document.querySelectorAll('.flz-planer-fixed-conflict [data-action="report-fixed-conflict"]')).some(button => button.getClientRects().length)`, 'Erkannter Konflikt zweier fester Schichten');
    for (let reports = await evaluate(`Array.from(document.querySelectorAll('[data-action="report-fixed-conflict"]')).filter(button => button.getClientRects().length).length`); reports > 0; reports--) {
        await evaluate(`Array.from(document.querySelectorAll('[data-action="report-fixed-conflict"]')).find(button => button.getClientRects().length).click()`);
        await waitFor(`Array.from(document.querySelectorAll('[data-action="report-fixed-conflict"]')).filter(button => button.getClientRects().length).length < ${reports}`, 'Persistierte konkrete Konflikteskalation');
    }
    assert.equal(await evaluate(`document.querySelectorAll('.flz-planer-fixed-conflict .flz-planer-badge').length > 0`), true, 'Weitergegebene Konflikte müssen gekennzeichnet sein.');

    await navigateAs(ebUid);
    await selectMonth();
    await waitFor(`Array.from(document.querySelectorAll('.flz-planer-fixed-conflict [data-action="resolve-fixed-conflict"][data-kept-uid=${JSON.stringify(memberUid)}]')).some(button => button.getClientRects().length)`, 'Eskalierter Konflikt in der EB-Ansicht');
    for (let conflicts = await evaluate(`Array.from(document.querySelectorAll('.flz-planer-fixed-conflict')).filter(item => item.getClientRects().length).length`); conflicts > 0; conflicts--) {
        await evaluate(`Array.from(document.querySelectorAll('.flz-planer-fixed-conflict [data-action="resolve-fixed-conflict"][data-kept-uid=${JSON.stringify(memberUid)}]')).find(button => button.getClientRects().length).click()`);
        await waitFor(`Array.from(document.querySelectorAll('.flz-planer-fixed-conflict')).filter(item => item.getClientRects().length).length < ${conflicts}`, 'Gelöstes konkretes Festschichtvorkommen');
    }
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('.flz-planer-fixed-conflict')).filter(item => item.getClientRects().length).length`), 0, 'Alle konkreten Festschichtkonflikte des Monats müssen gelöst sein.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('[data-candidate-chip].flz-planer-chip--under')).some(chip => chip.textContent.includes(${JSON.stringify(memberUid)}))`), true, 'Schichten eines Teammitglieds unter Minimum müssen kräftig markiert sein.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('[data-candidate-chip].flz-planer-chip--under')).find(chip => chip.textContent.includes(${JSON.stringify(memberUid)}))?.title`), 'Unter persönlichem Minimum', 'Die kräftige Auslastungsmarkierung braucht einen Tooltip.');
    await click('#flz-planer-tab-workload', 'Auslastungstab der EB');
    await waitFor(`document.querySelector('#flz-planer-workload-overlay:not([hidden]) #flz-planer-workload-heading') && document.querySelector('#flz-planer-proposal-heading') && Array.from(document.querySelectorAll('#flz-planer-panel .flz-planer-mobile-plan')).some(plan => plan.getClientRects().length)`, 'Auslastungs-Overlay über dem sichtbaren Monatsplan');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('#flz-planer-panel .flz-planer-mobile-day-head .flz-planer-badge')).some(label => label.getClientRects().length && label.textContent.match(/^KW [0-9]{2}$/))`), true, 'Die Auslastungs-Kalenderwochen müssen im sichtbaren Plan erkennbar sein.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('.flz-planer-capacity--under')).some(value => value.textContent === '<3')`), true, 'Unter Minimum muss die kompakte Mindestanzeige erscheinen.');
    assert.equal(await evaluate(`Boolean(document.querySelector('.flz-planer-plan-proposal'))`), true, 'Die EB benötigt einen groben, nicht schreibenden Planvorschlag.');

    const browserErrors = await evaluate(`window.__adpBrowserErrors || []`);
    const appBrowserErrors = browserErrors.filter(message => message !== 'ResizeObserver loop completed with undelivered notifications.');
    assert.deepEqual(appBrowserErrors, [], `Die Oberfläche erzeugte Browserfehler: ${appBrowserErrors.join('; ')}`);
    console.log('FlzPlaner synthetischer DDEV-Browser-Smoke: OK');
} finally {
    cdp.close();
}
