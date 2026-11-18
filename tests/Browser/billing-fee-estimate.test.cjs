const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/billing-fee-estimate.js', 'utf8');
function simulate({ gross = '100000', additional = '0', extra = '0', rate = '28900', vat = '190000', fixed = '0', enabled = true } = {}) {
    const outputs = { month: {}, year: {} };
    const form = {
        querySelector(selector) {
            if (selector.startsWith('input[type="checkbox"]')) return { checked: enabled };
            return { value: selector.includes('[additional_branch_amount]') ? additional : gross };
        }, addEventListener() {},
    };
    const box = {
        dataset: { rate, vat, fixed }, closest: () => form,
        querySelector(selector) {
            if (selector === '[data-fee-extra]') return { value: extra };
            return outputs[selector.includes('month') ? 'month' : 'year'];
        },
    };
    vm.runInNewContext(source, { document: { querySelectorAll: selector => selector === '[data-fee-estimator]' ? [box] : [] } });
    return outputs.month.textContent;
}
test('CLP percentage and VAT match backend rounding', () => {
    assert.match(simulate(), /Comisión: \$2\.890 CLP/);
    assert.match(simulate(), /IVA de la comisión: \$549 CLP/);
    assert.match(simulate(), /Recibirías: \$96\.561 CLP/);
});

test('desired net automatically fills the selling price including fee VAT', () => {
    for (const [rate, expected] of [['28900', '51781'], ['31900', '51973']]) {
        const listeners = {};
        const target = { value: '50000', addEventListener: (name, fn) => { listeners[name] = fn; } };
        const result = {};
        const rateInput = { value: String(Number(rate) / 10000), addEventListener: (name, fn) => { listeners.rate = fn; } };
        const vatInput = { value: '19', addEventListener() {} };
        let dispatched = false;
        const price = { addEventListener() {}, dispatchEvent() { dispatched = true; } };
        const box = {
            dataset: { rate, vat: '190000', fixed: '0', period: 'month' },
            closest: () => ({ querySelector: () => price }),
            querySelector: selector => ({ '[data-net-target]': target, '[data-net-result]': result, '[data-net-rate]': rateInput, '[data-net-vat]': vatInput })[selector],
        };
        vm.runInNewContext(source, { Event: class {}, document: { querySelectorAll: selector => selector === '[data-net-price]' ? [box] : [] } });
        listeners.input();
        assert.equal(price.value, expected);
        assert.equal(dispatched, true);
        assert.match(result.textContent, /IVA sobre la comisión: 19%/);
        rateInput.value = '0';
        listeners.rate();
        assert.equal(price.value, '50000');
        rateInput.value = String(Number(rate) / 10000);
        listeners.rate();
        target.value = '-1';
        listeners.input();
        assert.equal(price.value, expected);
        assert.match(result.textContent, /válido/);
    }
});
test('fixed fee is charged once and extras contribute to total', () => {
    const output = simulate({ additional: '10000', extra: '2', fixed: '100' });
    assert.match(output, /Precio al cliente: \$120\.000 CLP/);
    assert.match(output, /Comisión: \$3\.568 CLP/);
    assert.match(output, /IVA de la comisión: \$678 CLP/);
    assert.match(output, /Recibirías: \$115\.754 CLP/);
});
test('disabled or invalid prices do not display a misleading estimate', () => {
    assert.match(simulate({ enabled: false }), /Activa el período/);
    assert.match(simulate({ extra: '-1' }), /Activa el período/);
    assert.match(simulate({ gross: '1000000000001' }), /Activa el período/);
});
