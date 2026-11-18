const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { readFileSync } = require('node:fs');

function setup() {
    const fields = Object.fromEntries(['name','code','description','position','active'].map(key => [key,{ value:'', focus() {} }]));
    const appended = [];
    const form = { elements:{namedItem:key => fields[key]}, append:element => appended.push(element), querySelector:() => null };
    const title = {textContent:''}, message = {hidden:true}, footer = {style:{}}, panel = {style:{}};
    const content = { className:'', replaceChildren() {}, querySelector:selector => selector === '[data-plan-form]' ? form : null };
    const modal = { hidden:true, style:{}, addEventListener() {}, querySelector:selector => ({
        '[data-plan-modal-content]':content, h2:title, '[data-plan-errors]':message,
        '[data-plan-view-footer]':footer, '[data-plan-modal-panel]':panel, '[data-close-plan]':{focus() {}}
    })[selector] };
    const template = {content:{cloneNode:() => ({})}};
    let calls = 0;
    const context = { URL, AbortController, location:{href:'https://vita.test/billing/plans'},
        document:{body:{style:{}}, activeElement:null, addEventListener() {},
            getElementById:id => id === 'billing-plan-modal' ? modal : template,
            createElement:() => ({})},
        fetch:async () => { calls++; throw new Error('Opening should not request the server'); }
    };
    context.window = context;
    vm.runInNewContext(readFileSync('public/js/billing-plan-modal.js','utf8'),context);
    return {api:context.VitaPlanModal, fields, form, appended, modal, title, calls:() => calls};
}

test('creating a plan opens the preloaded form with no network wait', async () => {
    const env=setup();
    await env.api.open('https://vita.test/billing/plans/create');
    assert.equal(env.calls(),0);
    assert.equal(env.modal.hidden,false);
    assert.equal(env.title.textContent,'Crear plan');
    assert.equal(env.appended.length,0);
});

test('editing uses datatable values and keeps the update method without fetching HTML', async () => {
    const env=setup();
    env.api.setRows([{id:12,name:'Plan propio',code:'own',description:'Condiciones',position:3,active:false,
        show_url:'https://vita.test/billing/plans/12',edit_url:'https://vita.test/billing/plans/12/edit',delete_url:'https://vita.test/billing/plans/12'}]);
    await env.api.open('https://vita.test/billing/plans/12/edit');
    assert.equal(env.calls(),0);
    assert.equal(env.fields.name.value,'Plan propio');
    assert.equal(env.fields.active.value,'0');
    assert.equal(env.fields.code.readOnly,true);
    assert.equal(env.appended[0].value,'PUT');
    assert.equal(env.form.action,'https://vita.test/billing/plans/12');
});
