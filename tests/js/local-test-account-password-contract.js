const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const scripts = [
    'tests/access-matrix-ddev-smoke.sh',
    'tests/browser-ddev-smoke.sh',
    'tests/privacy-ddev-smoke.sh',
];

scripts.forEach(relativePath => {
    const source = fs.readFileSync(path.join(root, relativePath), 'utf8');
    assert(!source.includes('random_bytes'), `${relativePath} verwendet weiterhin ein zufälliges Testpasswort.`);
    assert(!source.includes('OC_PASS="$password"'), `${relativePath} verwendet weiterhin ein geteiltes Testpasswort.`);
    assert(source.includes('OC_PASS="$uid"'), `${relativePath} setzt das lokale Testpasswort nicht auf die UID.`);
});

const browser = fs.readFileSync(path.join(root, 'tests/js/browser-ddev-smoke.mjs'), 'utf8');
assert(browser.includes('JSON.stringify(uid)'), 'Der Browser-Smoke meldet lokale Testkonten nicht mit UID = Passwort an.');
assert(!browser.includes('ADP_BROWSER_PASSWORD'), 'Der Browser-Smoke erwartet weiterhin ein gemeinsames Testpasswort.');

console.log('AdPlaner local test account password contract passed.');
