const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(signingMethod = 'PATCH') {
    const element = (extra = {}) => ({ listeners: {}, value: '', hidden: false, disabled: false,
        addEventListener(type, fn) { this.listeners[type] = fn; }, focus() {}, reportValidity: () => true, ...extra });
    const acceptance = element(); const password = element({ value: 'secret-password' });
    const close = element(); const submit = element(); const error = element(); const open = element(); const preview = element();
    const confirm = element({ data: { acceptance: '1', password: 'secret-password' }, reset() { password.value = ''; },
        querySelector: selector => ({ '[name="acceptance"]': acceptance, '[name="password"]': password,
            '[data-signing-close]': close, '[data-signing-submit]': submit, '[data-signing-error]': error })[selector] });
    const modal = element({ hidden: true, querySelector: selector => ({ '[data-signing-confirm]': confirm, '[data-signing-preview]': preview })[selector] });
    const draft = element({ data: { _method: 'PATCH', clinical_assessment: 'Draft' }, dataset: { previewUrl: '/preview', signingMethod },
        getAttribute: () => '/finish', querySelector: selector => selector === '[name="_token"]' ? { value: 'csrf' } : open });
    class FormData {
        constructor(form) { this.data = { ...form.data }; }
        set(k, v) { this.data[k] = v; } delete(k) { delete this.data[k]; }
    }
    const calls = []; const navigation = [];
    const responses = [{ ok: true, json: async () => ({ operation_id: 'operation', html: '<p>Reviewed content</p>' }) }];
    const window = { showClinicalRecordToast: message => calls.push(message) };
    vm.runInNewContext(fs.readFileSync('public/js/encounter-signing.js', 'utf8'), {
        document: { querySelector: () => draft, getElementById: () => modal, body: { append() {} },
            createElement: () => ({ click() { navigation.push(this.href); }, remove() {} }) },
        window, FormData, fetch: async (url, request) => { calls.push({ url, request }); return responses.shift(); },
    });
    const event = { preventDefault() {}, stopImmediatePropagation() {} };
    return { open, close, confirm, modal, error, calls, responses, navigation, window, event };
}

(async () => {
    const state = setup();
    assert.equal(state.modal.hidden, true);
    assert.equal(state.calls.length, 0);
    await state.open.listeners.click();
    assert.equal(state.calls[0].url, '/preview');
    assert.equal(state.calls[0].request.body.data._method, undefined);
    assert.equal(state.modal.hidden, false);
    state.close.listeners.click();
    assert.equal(state.modal.hidden, true);
    assert.equal(state.calls.length, 1);
    console.log('PASS preparing and cancelling never submits a finalization');

    const signed = setup();
    await signed.open.listeners.click();
    signed.responses.push({ ok: false, json: async () => ({ errors: { password: ['Incorrect password'] } }) });
    await signed.confirm.listeners.submit(signed.event);
    assert.equal(signed.error.hidden, false);
    assert.equal(signed.navigation.length, 0);
    assert.equal(signed.calls[1].request.body.data.password, undefined);
    signed.responses.push({ ok: true, json: async () => ({ redirect: '/history' }) });
    await signed.confirm.listeners.submit(signed.event);
    assert.deepEqual(signed.navigation, ['/history']);
    assert.equal(signed.window.vitaHasUnsavedChanges(), false);
    console.log('PASS failures keep the modal open and success uses internal navigation');
    const addendum = setup('POST');
    await addendum.open.listeners.click();
    addendum.responses.push({ ok: true, json: async () => ({ redirect: '/history' }) });
    await addendum.confirm.listeners.submit(addendum.event);
    assert.equal(addendum.calls[1].request.body.data._method, undefined);
    assert.equal(addendum.calls[1].request.body.data.operation_id, 'operation');
    assert.deepEqual(addendum.navigation, ['/history']);
    console.log('PASS addenda reuse the signing modal with POST and their own operation');
})().catch(error => { console.error(error); process.exitCode = 1; });
