const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { readFileSync } = require('node:fs');

function setup() {
    const element = () => ({hidden:false, attributes:{}, events:{}, children:[], value:'',
        addEventListener(name,fn){this.events[name]=fn;},
        setAttribute(name,value){this.attributes[name]=value;},
        removeAttribute(name){delete this.attributes[name];},
        replaceChildren(){this.children=[];},append(child){this.children.push(child);},
        scrollIntoView(){},dispatchEvent(){}});
    const select=element(),input=element(),list=element(),status=element(),label={};
    select.options=[{value:'',textContent:'Todas las especialidades'},
        {value:'1',textContent:'Pediatría'},{value:'2',textContent:'Psicología'},
        {value:'3',textContent:'Categoría retirada',hidden:true}];
    Object.defineProperty(select,'selectedOptions',{get:()=>select.options.filter(o=>o.value===select.value)});
    const picker={querySelector:selector=>({'select':select,'input':input,'[role="listbox"]':list,
        '[role="status"]':status,'[data-specialty-label]':label})[selector]};
    vm.runInNewContext(readFileSync('public/js/customer-directory-specialty.js','utf8'),{
        document:{querySelector:()=>picker,createElement:()=>element()},Event:class {}});
    const fire=(name,key)=>input.events[name]({key,preventDefault(){}});
    return {select,input,list,status,label,fire};
}
test('search ignores accents and case and selects a stable identifier by keyboard',()=>{
    const s=setup();s.input.value='PEDIATRIA';s.fire('input');
    assert.equal(s.list.children.length,1);assert.equal(s.list.children[0].textContent,'Pediatría');
    s.fire('keydown','ArrowDown');s.fire('keydown','Enter');
    assert.equal(s.select.value,'1');assert.equal(s.input.value,'Pediatría');assert.equal(s.list.hidden,true);
});
test('touch and mouse options preserve focus and choose without submitting',()=>{
    const s=setup();s.fire('focus');assert.equal(s.list.children.length,3);
    const item=s.list.children[2];let prevented=false;
    item.events.pointerdown({preventDefault(){prevented=true;}});item.events.click();
    assert.equal(prevented,true);assert.equal(s.select.value,'2');assert.equal(s.list.hidden,true);
});
test('ArrowUp from closed combobox starts at the final available option',()=>{
    const s=setup();s.fire('focus');s.fire('keydown','ArrowUp');s.fire('keydown','Enter');
    assert.equal(s.select.value,'2');
});
test('clearing, no matches, escape and exact-match blur keep submitted filters consistent',()=>{
    const s=setup();s.input.value='psicologia';s.fire('input');s.fire('blur');assert.equal(s.select.value,'2');
    s.input.value='unknown';s.fire('input');assert.equal(s.select.value,'');assert.equal(s.list.hidden,true);
    s.fire('blur');assert.equal(s.input.value,'');
    s.fire('focus');s.fire('keydown','Escape');assert.equal(s.input.attributes['aria-expanded'],'false');
    s.input.value='';s.fire('input');assert.equal(s.select.value,'');
});
