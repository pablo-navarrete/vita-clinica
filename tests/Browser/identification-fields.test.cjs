const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function widget(fetch) {
    const blade = fs.readFileSync('resources/views/components/identification-fields.blade.php', 'utf8');
    const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1]
        .replace('@json($identificationCountries)', JSON.stringify([['CL','Chile'],['PE','Perú'],['AR','Argentina']]));
    const window = {};
    vm.runInNewContext(script, { window, fetch });
    return window.VitaIdentification;
}

test('new holders default to Chile and corporate and personal document types stay separate', () => {
    const id = widget();
    assert.equal(id.defaults('customer').identification_type, 'tax_id');
    assert.equal(id.defaults().identification_country, 'CL');
    assert.equal(id.defaults().identification_holder, 'natural_person');
    assert.equal(id.defaults('user', 'admin_').admin_identification_type, 'national_id');
    assert.equal(id.fold('  Perú  ').trim(), 'peru');
});

test('later administrator selection cannot be overwritten by an earlier response', async () => {
    const pending = [];
    const id = widget(() => new Promise(resolve => pending.push(resolve)));
    const form = {};
    const earlier = id.load('/first', form, 'admin_');
    const later = id.load('/second', form, 'admin_');
    assert.equal(id.isLoading(form), true);
    pending[1]({ok:true,json:async()=>({identification_number:'SECOND',identification_expected_primary:'second',customer_id:999})});
    await later;
    pending[0]({ok:true,json:async()=>({identification_number:'FIRST'})});
    await earlier;
    assert.equal(form.admin_identification_number, 'SECOND');
    assert.equal(form.admin_identification_expected_primary, 'second');
    assert.equal(form.admin_customer_id, undefined);
    assert.equal(id.isLoading(form), false);
});

test('private endpoint failure does not enable submitting a partially loaded identity', async () => {
    const id = widget(async () => ({ok:false}));
    const form = { identification_number:'UNCHANGED' };
    await assert.rejects(id.load('/denied', form), /Recarga/);
    assert.equal(form.identification_number, 'UNCHANGED');
    assert.equal(id.isLoading(form), false);
});

test('Chilean RUTs validate their check digit and format without changing the identity', () => {
    const id = widget();
    for (const [number, expected] of [['123456785','12.345.678-5'], ['12345678-5','12.345.678-5'], ['12.345.678-5','12.345.678-5'], ['6-k','6-K'], ['14-0','14-0']]) {
        const form = {...id.defaults(), identification_number:number};
        assert.equal(id.validateNumber(form), '');
        assert.equal(form.identification_number, expected);
    }
    const company = {...id.defaults('customer','admin_'), admin_identification_number:'123456785'};
    assert.equal(id.validateNumber(company,'admin_'), '');
    assert.equal(company.admin_identification_number, '12.345.678-5');
});

test('invalid RUTs show errors without silently fixing digits or deleting input', () => {
    const id = widget();
    for (const number of ['', '12345678-4', '0-0', '012345678-5', '12..345.678-5', '12.345.678-A', '12.345.678-5\n']) {
        const form = {...id.defaults(), identification_number:number};
        assert.notEqual(id.validateNumber(form), '');
        assert.equal(form.identification_number, number);
    }
});

test('foreign documents, passports and disabled historical fields remain unchanged', () => {
    const id = widget();
    for (const extra of [{identification_country:'PE'}, {identification_type:'passport'}, {identification_enabled:false}]) {
        const form = {...id.defaults(), identification_number:'000Ab-12', ...extra};
        assert.equal(id.validateNumber(form), '');
        assert.equal(form.identification_number, '000Ab-12');
    }
});
