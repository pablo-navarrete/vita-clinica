const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function shell() {
    const handlers = {}, elements = {};
    let roomLoads = 0;
    const urlChanges = [];
    let locationCheck;
    const element = id => elements[id] ||= {
        id, hidden:true, dataset:{}, style:{ values:{}, setProperty(key,value) { this.values[key] = value; } }, contentWindow:{ postMessage() {} },
        listeners:{}, addEventListener(type,fn) { this.listeners[type] = fn; },
        setAttribute() {}, getBoundingClientRect() { return {left:16,top:16,width:380,height:530}; },
        hasPointerCapture() { return false; }, setPointerCapture() {}, releasePointerCapture() {},
    };
    const roomFrame = element('vita-call-frame');
    Object.defineProperty(roomFrame,'src',{ set(value) { this.currentSrc = value; roomLoads++; }, get() { return this.currentSrc; } });
    const context = {
        document:{getElementById:element},location:{origin:'https://clinic.test',href:'https://clinic.test/patients/1',replace() {}},
        history:{replaceState(_state,_title,url) { urlChanges.push(String(url)); context.location.href = String(url); }},innerWidth:1200,innerHeight:900,URL,
        setTimeout() { return 1; },setInterval(fn) { locationCheck = fn; return 1; },clearTimeout() {},confirm() { return true; },
        addEventListener(type,fn) { handlers[type] = fn; },
    };
    context.window = context;
    vm.runInNewContext(readFileSync('public/js/vita-shell.js','utf8'),context);
    const send = (data, options = {}) => handlers.message({origin:options.origin || 'https://clinic.test',source:options.source || element('vita-module-frame').contentWindow,data});
    const open = id => send({type:'vita:open-call',url:`https://clinic.test/telemedicine/${id}?embedded=1`,recordUrl:'https://clinic.test/patients/1?encounter_id=5',sessionId:String(id),patient:'Test patient'});
    return {send,open,element,roomFrame,urlChanges,checkLocation:() => locationCheck(),get loads() { return roomLoads; }};
}
test('module navigation and returning to the same attention preserve the video document',() => {
    const app = shell(); app.open(10);
    assert.equal(app.loads,1);
    app.send({type:'vita:module-location',url:'https://clinic.test/appointments',title:'Agenda'});
    app.open(10);
    assert.equal(app.loads,1);
    assert.equal(app.roomFrame.src,'https://clinic.test/telemedicine/10?embedded=1');
    assert.equal(app.element('vita-persistent-call').hidden,false);
});
test('another attention cannot replace the existing call',() => {
    const app = shell(); app.open(10); app.open(11);
    assert.equal(app.loads,1);
    assert.equal(app.element('vita-shell-notice').hidden,false);
});
test('untrusted windows and other origins cannot open a room',() => {
    const app = shell();
    const payload = {type:'vita:open-call',url:'https://clinic.test/telemedicine/10?embedded=1',recordUrl:'https://clinic.test/patients/1',sessionId:'10'};
    app.send(payload,{origin:'https://other.test'});
    app.send(payload,{source:{}});
    app.send({...payload,url:'https://other.test/telemedicine/10?embedded=1'});
    assert.equal(app.loads,0);
});
test('minimizing, expanding and returning to the attention do not reload video',() => {
    const app = shell(); app.open(10);
    for (const id of ['vita-call-minimize','vita-call-expand','vita-call-return']) app.element(id).listeners.click();
    assert.equal(app.loads,1);
    assert.match(app.element('vita-module-frame').src,/encounter_id=5/);
});
test('a joined call closes only after its room acknowledges stopping',() => {
    const app = shell(); app.open(10);
    app.send({type:'vita:room-state',joined:true},{source:app.roomFrame.contentWindow});
    app.element('vita-call-close').listeners.click();
    assert.equal(app.loads,1);
    app.send({type:'vita:room-stopped'},{source:app.roomFrame.contentWindow});
    assert.equal(app.element('vita-persistent-call').hidden,true);
    assert.equal(app.roomFrame.src,'about:blank');
});

test('frame loads update the address bar without requiring a bridge message', () => {
    const app = shell(); app.open(10);
    app.element('vita-module-frame').contentWindow.location = {href:'https://clinic.test/my-encounters?_vita_frame=1&status=2'};
    app.element('vita-module-frame').listeners.load();
    assert.equal(app.urlChanges.at(-1),'https://clinic.test/my-encounters?status=2');
    assert.equal(app.loads,1);
});
test('module History API and back/forward changes are synchronized without reloading the call', () => {
    const app = shell(); app.open(10);
    const frame = app.element('vita-module-frame');
    frame.contentWindow.location = {href:'https://clinic.test/patients?page=2#history'};
    app.checkLocation();
    assert.equal(app.urlChanges.at(-1),'https://clinic.test/patients?page=2#history');
    frame.contentWindow.location.href = 'https://clinic.test/dashboard';
    app.checkLocation();
    assert.equal(app.urlChanges.at(-1),'https://clinic.test/dashboard');
    assert.equal(app.loads,1);
});

test('mouse resizing changes the floating dimensions without reloading the room', () => {
    const app = shell(); app.open(10);
    const grip = app.element('vita-call-resize-se');
    grip.listeners.pointerdown({button:0,pointerId:1,clientX:396,clientY:546,preventDefault() {},stopPropagation() {}});
    grip.listeners.pointermove({pointerId:1,clientX:496,clientY:596});
    assert.equal(app.element('vita-persistent-call').style.values['--call-width'],'480px');
    assert.equal(app.element('vita-persistent-call').style.values['--call-height'],'580px');
    grip.listeners.pointerup();
    assert.equal(app.loads,1);
});
test('mouse resizing keeps minimum readable dimensions', () => {
    const app = shell(); app.open(10);
    const grip = app.element('vita-call-resize-se');
    grip.listeners.pointerdown({button:0,pointerId:1,clientX:396,clientY:546,preventDefault() {},stopPropagation() {}});
    grip.listeners.pointermove({pointerId:1,clientX:0,clientY:0});
    assert.equal(app.element('vita-persistent-call').style.values['--call-width'],'240px');
    assert.equal(app.element('vita-persistent-call').style.values['--call-height'],'260px');
});

test('minimizing an expanded call exits expanded mode and can restore its normal size', () => {
    const app = shell(); app.open(10);
    const panel = app.element('vita-persistent-call');
    app.element('vita-call-expand').listeners.click();
    assert.equal(panel.dataset.expanded,'true');
    app.element('vita-call-minimize').listeners.click();
    assert.equal(panel.dataset.minimized,'true');
    assert.equal(panel.dataset.expanded,'false');
    app.element('vita-call-minimize').listeners.click();
    assert.equal(panel.dataset.minimized,'false');
    assert.equal(panel.dataset.expanded,'false');
    assert.equal(app.loads,1);
});
