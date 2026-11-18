const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const nodes = new Map();
    const element = () => ({
        listeners: {}, dataset: {}, hidden: false, value: '', checked: false,
        addEventListener(type, callback) { this.listeners[type] = callback; },
        getAttribute(name) { return this[name] || null; },
        removeAttribute(name) { delete this[name]; },
    });
    const context = { clearRect() {}, beginPath() {}, arc() {}, fill() {}, moveTo() {}, lineTo() {}, stroke() {} };
    const pending = [];
    const canvas = element();
    Object.assign(canvas, { width: 1000, height: 320, getContext: () => context,
        getBoundingClientRect: () => ({ left: 10, top: 20, width: 500, height: 160 }),
        setPointerCapture() {}, toBlob: callback => pending.push(callback) });
    nodes.set('[data-signature-canvas]', canvas);
    for (const selector of ['[data-signature-save]', '[data-signature-error]', '[data-clear-canvas]']) nodes.set(selector, element());
    for (const kind of ['signature', 'stamp']) {
        for (const name of ['signature-file', 'remove', 'preview', 'empty']) nodes.set(`[data-${name}="${kind}"]`, element());
        nodes.get(`[data-preview="${kind}"]`).src = `/private/${kind}`;
    }
    const form = { querySelector: selector => nodes.get(selector) };
    class Transfer { constructor() { this.files = []; this.items = { add: file => this.files.push(file) }; } }
    class File { constructor(parts, name, options) { this.name = name; this.type = options.type; this.size = 100; } }
    vm.runInNewContext(fs.readFileSync('public/js/professional-signature.js', 'utf8'), {
        document: { querySelector: () => form }, window: { addEventListener() {} },
        URL: { createObjectURL: () => 'blob:preview', revokeObjectURL() {} }, DataTransfer: Transfer, File,
    });
    const trigger = (node, name, extra = {}) => node.listeners[name]({ target: node, preventDefault() {}, ...extra });
    return { nodes, canvas, pending, trigger };
}

{
    const { nodes, canvas, pending, trigger } = setup();
    trigger(canvas, 'pointerdown', { pointerId: 1, pointerType: 'touch', clientX: 20, clientY: 30 });
    assert.equal(nodes.get('[data-signature-save]').disabled, true);
    trigger(canvas, 'pointermove', { pointerId: 1, clientX: 40, clientY: 40 });
    trigger(canvas, 'pointerup', { pointerId: 1 });
    pending.shift()({});
    assert.equal(nodes.get('[data-signature-file="signature"]').files[0].name, 'firma.png');
    assert.equal(nodes.get('[data-preview="signature"]').src, 'blob:preview');
    assert.equal(nodes.get('[data-signature-save]').disabled, false);
    trigger(nodes.get('[data-clear-canvas]'), 'click');
    assert.equal(nodes.get('[data-preview="signature"]').src, '/private/signature');
    console.log('PASS touch drawing prepares a PNG and clearing restores the saved preview');
}
{
    const { nodes, canvas, pending, trigger } = setup();
    trigger(canvas, 'pointerdown', { pointerId: 1, pointerType: 'mouse', button: 0, clientX: 20, clientY: 30 });
    trigger(canvas, 'pointerup', { pointerId: 1 });
    const file = nodes.get('[data-signature-file="signature"]');
    file.files = [{ name: 'replacement.jpg', type: 'image/jpeg', size: 200 }];
    trigger(file, 'change');
    pending.shift()({});
    assert.equal(file.files[0].name, 'replacement.jpg');
    const remove = nodes.get('[data-remove="signature"]');
    remove.checked = true; trigger(remove, 'change');
    assert.equal(file.value, '');
    assert.equal(nodes.get('[data-preview="signature"]').hidden, true);
    assert.equal(nodes.get('[data-signature-save]').disabled, false);
    console.log('PASS a delayed canvas export cannot overwrite a newly selected upload');
}
