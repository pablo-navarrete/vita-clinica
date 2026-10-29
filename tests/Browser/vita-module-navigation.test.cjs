const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { readFileSync } = require('node:fs');

function modules() {
    const created = [], messages = [], nodes = {};
    const element = () => ({ dataset:{},style:{},listeners:{},contentWindow:{ location:{href:'about:blank'},dispatchEvent() {},postMessage() {} },
        contentDocument:{body:{dataset:{vitaContent:'true'}},title:'Module'},
        getBoundingClientRect() { return {left:256,top:64,width:944,height:736}; },
        addEventListener(type,fn) { this.listeners[type] = fn; },setAttribute() {},removeAttribute(name) { delete this[name]; },remove() { this.removed = true; } });
    nodes['vita-content-current'] = element();
    nodes['vita-content-current'].dataset.initialUrl = 'https://clinic.test/patients?_vita_content=1';
    nodes['vita-module-loading'] = element();
    nodes['vita-module-slot'] = {...element(),append(frame) { created.push(frame); }};
    const context = {URL,Event,location:{origin:'https://clinic.test',href:'https://clinic.test/patients'},confirm:() => true,
        document:{getElementById:id => nodes[id],createElement:element,addEventListener() {},querySelectorAll:() => []},
        VitaNavigation:{send:data => messages.push(data),requestState() {}}};
    context.window = context;
    vm.runInNewContext(readFileSync('public/js/vita-module-navigation.js','utf8'),context);
    return {context,nodes,created,messages,api:context.VitaModules};
}
test('the previous content stays visible until the replacement document has loaded',() => {
    const app = modules(), previous = app.nodes['vita-content-current'];
    app.api.navigate('https://clinic.test/appointments');
    const pending = app.created[0];
    assert.equal(previous.removed,undefined);
    assert.match(pending.style.cssText,/visibility:hidden/);
    pending.contentWindow.location.href = 'https://clinic.test/appointments?_vita_content=1';
    pending.listeners.load();
    assert.equal(previous.removed,true);
    assert.equal(pending.id,'vita-content-current');
    assert.doesNotMatch(pending.style.cssText,/visibility:hidden/);
    assert.equal(app.messages.at(-1).historyPush,true);
});
test('a failed module load keeps the current screen',() => {
    const app = modules(), previous = app.nodes['vita-content-current'];
    app.api.navigate('/appointments');
    const pending = app.created[0];
    pending.contentWindow.location.href = 'https://clinic.test/appointments';
    pending.contentDocument.body.dataset.vitaContent = 'false';
    pending.listeners.load();
    assert.equal(previous.removed,undefined);
    assert.equal(pending.removed,true);
});
test('cancelling navigation preserves unsaved clinical work',() => {
    const app = modules();
    app.nodes['vita-content-current'].contentWindow.vitaHasUnsavedChanges = () => true;
    app.context.confirm = () => false;
    assert.equal(app.api.navigate('/appointments'),false);
    assert.equal(app.created.length,0);
});
test('modules from another origin cannot replace the central content',() => {
    const app = modules();
    assert.equal(app.api.navigate('https://other.test/patients'),false);
    assert.equal(app.created.length,0);
});

test('a legacy clinical-record navigation is corrected before displaying a nested menu', () => {
    const app = modules(), frame = app.nodes['vita-content-current'];
    let redirected;
    frame.contentDocument.body.dataset.vitaContent = 'false';
    frame.contentWindow.location = { href:'https://clinic.test/patients/5/medical-record', replace(url) { redirected = url; } };
    frame.listeners.load();
    assert.equal(redirected,'https://clinic.test/patients/5/medical-record?_vita_content=1');
    assert.equal(frame.style.visibility,'hidden');
    frame.contentDocument.body.dataset.vitaContent = 'true';
    frame.contentWindow.location.href = redirected;
    frame.listeners.load();
    assert.equal(frame.style.visibility,'');
});

test('refresh reloads only the current content and preserves its filters and anchor', () => {
    const app = modules(), previous = app.nodes['vita-content-current'];
    previous.contentWindow.location.href = 'https://clinic.test/chat?conversation=7&_vita_content=1#messages';
    assert.equal(app.api.refresh(),true);
    const pending = app.created[0];
    const url = new URL(pending.src);
    assert.equal(url.pathname,'/chat');
    assert.equal(url.searchParams.get('conversation'),'7');
    assert.equal(url.searchParams.get('_vita_content'),'1');
    assert.equal(url.hash,'#messages');
    assert.equal(previous.removed,undefined);
    pending.contentWindow.location.href = pending.src;
    pending.listeners.load();
    assert.equal(app.messages.at(-1).historyPush,false);
    assert.equal(previous.removed,true);
});
test('saving a form targets a new content document while the current module stays visible', () => {
    const app = modules(), previous = app.nodes['vita-content-current'];
    const fields = [];
    const form = {action:'https://clinic.test/patients/10', getAttribute(name) { return this[name] || null; },setAttribute(name,value) { this[name] = value; },querySelector:() => null,
        ownerDocument:{createElement:() => ({})},append:field => fields.push(field)};
    assert.equal(app.api.submit(form),true);
    assert.equal(form.target,app.created[0].name);
    assert.equal(new URL(form.action).searchParams.get('_vita_content'),'1');
    assert.equal(fields[0].name,'_vita_content');
    assert.equal(fields[0].value,'1');
    assert.equal(previous.removed,undefined);
});

test('a history filter named action cannot shadow the destination of its form', () => {
    const app = modules();
    const attrs = {action:'/activity-history',method:'GET'};
    const form = {action:{toString:() => '[object HTMLSelectElement]'},
        getAttribute:name => attrs[name] || null,setAttribute:(name,value) => attrs[name] = value,
        querySelector:() => null,ownerDocument:{baseURI:'https://clinic.test/activity-history?_vita_content=1',createElement:() => ({})},append() {}};
    assert.equal(app.api.submit(form),true);
    const destination = new URL(attrs.action);
    assert.equal(destination.pathname,'/activity-history');
    assert.equal(destination.searchParams.get('_vita_content'),'1');
    assert.equal(attrs.target,app.created[0].name);
    assert.equal(form.action.toString(),'[object HTMLSelectElement]');
});

test('opening a modal covers the whole Vita viewport and closing restores only the content frame', () => {
    const app = modules(), frame = app.nodes['vita-content-current'];
    frame.style.cssText = 'border:0;width:100%;height:100%;display:block;';
    app.api.setModal(true);
    assert.equal(frame.dataset.modalFullscreen,'true');
    assert.match(frame.style.cssText,/position:fixed;inset:0;width:100vw;height:100dvh/);
    app.api.setModal(true);
    app.api.setModal(false);
    assert.equal(frame.style.cssText,'border:0;width:100%;height:100%;display:block;');
    assert.equal(frame.dataset.modalFullscreen,undefined);
    assert.equal(frame.removed,undefined);
});

test('modals keep the module at its original size and leave the surrounding menus visible', () => {
    const app = modules(), frame = app.nodes['vita-content-current'];
    const attrs = {}, styles = [];
    frame.contentDocument.body.firstElementChild = {setAttribute:(name,value) => attrs[name] = value,removeAttribute:name => delete attrs[name]};
    frame.contentDocument.createElement = () => ({remove() {this.removed = true;}});
    frame.contentDocument.head = {append:style => styles.push(style)};
    app.api.setModal(true);
    assert.match(styles[0].textContent,/background:transparent!important/);
    assert.match(styles[0].textContent,/left:256px!important;top:64px!important;width:944px!important;height:736px!important/);
    app.api.setModal(false);
    assert.equal(styles[0].removed,true);
    assert.equal(attrs['data-vita-modal-content'],undefined);
});

test('ready content is displayed before images and other load-event resources finish', () => {
    const app = modules(), previous = app.nodes['vita-content-current'];
    app.api.navigate('/appointments');
    const frame = app.created[0];
    frame.contentWindow.location.href = frame.src;
    app.api.contentReady(frame.contentWindow);
    assert.equal(previous.removed,true);
    assert.equal(frame.id,'vita-content-current');
    assert.equal(app.nodes['vita-module-loading'].hidden,true);
    frame.listeners.load();
    assert.equal(frame.removed,undefined);
});
