const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const events = {}; const notices = []; const requests = [];
let currentHost; let disposed = 0; let signingOptions;
const form = { querySelector: () => ({ focus() {} }) };
const preview = { hidden: true };
const opener = { href: 'https://clinic.example.test/patients/7/encounters/12/addenda/create',
    setAttribute() {}, removeAttribute() {}, focus() {} };
const window = {
    initializeEncounterAddendum: node => { assert.equal(node, form); return { isDirty: () => false, dispose: () => disposed++ }; },
    initializeEncounterSigning: (node, modal, options) => { assert.equal(modal, preview); signingOptions = options; return { isBusy: () => false }; },
    dispatchEvent: event => notices.push(event), showClinicalRecordToast: message => { throw new Error(message); },
};
vm.runInNewContext(fs.readFileSync('public/js/encounter-addendum-modal.js', 'utf8'), {
    window, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    document: { addEventListener: (type, callback) => events[type] = callback, body: { append() {} },
        createElement: () => currentHost = { listeners: {}, querySelector: selector => selector === '[data-addendum-form]' ? form : preview,
            addEventListener(type, callback) { this.listeners[type] = callback; }, remove() { this.removed = true; } } },
    fetch: async (url, options) => { requests.push({ url, options }); return { ok: true, json: async () => ({ html: '<div>Authorized modal</div>' }) }; },
});
(async () => {
    let prevented = false; let stopped = false;
    const click = { button: 0, target: { closest: () => opener }, preventDefault() { prevented = true; }, stopImmediatePropagation() { stopped = true; } };
    await events.click(click);
    assert.equal(prevented, true); assert.equal(stopped, true);
    assert.equal(requests[0].options.headers.Accept, 'application/json');
    const first = currentHost;
    signingOptions.onSigned({ redirect: '/patients/7/medical-record?record_tab=history' });
    assert.equal(first.removed, true); assert.equal(disposed, 1);
    assert.equal(notices[0].type, 'clinical-addendum-signed');
    assert.equal(notices[0].detail.url, '/patients/7/medical-record?record_tab=history');
    await events.click(click);
    currentHost.listeners.click({ target: { closest: () => true }, preventDefault() {} });
    assert.equal(currentHost.removed, true); assert.equal(disposed, 2);
    console.log('PASS modal opens without navigation, closes after signing, and can be opened again');
})().catch(error => { console.error(error); process.exitCode = 1; });
