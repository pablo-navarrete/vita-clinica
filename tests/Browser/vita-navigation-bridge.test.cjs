const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const {readFileSync} = require('node:fs');

function bridge() {
    const handlers = {}, clicks = [];
    const context = {location:{origin:'https://clinic.test',href:'https://clinic.test/users'},
        history:{pushState() {},replaceState() {}},URL,
        document:{title:'Users',querySelectorAll:() => [],addEventListener(type,fn,capture) { if(type === 'click' && capture) clicks.push(fn); }},
        addEventListener(type,fn) { handlers[type] = fn; }};
    context.window = context;
    const parent = {document:{getElementById:() => ({contentWindow:context})},postMessage() {}};
    context.parent = parent;
    vm.createContext(context);
    vm.runInContext("parent.document.getElementById = () => ({contentWindow:window});",context);
    vm.runInContext(readFileSync('public/js/vita-navigation-bridge.js','utf8'),context);
    handlers.message({source:parent,origin:'https://clinic.test',data:{type:'vita:call-state',joined:true}});
    return (branchSelector) => {
        let prevented = false;
        const event = {target:{closest(selector) {
            if (selector === '[data-branch-id]') return {};
            if (selector === '#clinic-branch-switcher [data-branch-id]' && branchSelector) return {};
            return null;
        }},preventDefault() { prevented = true; },stopImmediatePropagation() {}};
        clicks.forEach(handler => handler(event));
        return prevented;
    };
}
test('an active call does not block professional status actions carrying a branch id', () => {
    assert.equal(bridge()(false),false);
});
test('the actual branch selector remains blocked during an active call', () => {
    assert.equal(bridge()(true),true);
});
