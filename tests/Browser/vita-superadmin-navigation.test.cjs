const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function setup() {
    const listeners = {};
    const writes = [];
    const navigations = [];
    const location = { href: 'https://vita.test/dashboard', origin: 'https://vita.test' };
    class Form {
        constructor(action) { this.action = action; }
        getAttribute() { return this.action; }
    }
    const window = {
        VitaNavigation: {},
        VitaModules: { navigate: (...args) => navigations.push(args) },
        addEventListener: (event, handler) => listeners[event] = handler,
    };
    const document = {
        getElementById: () => ({}),
        addEventListener: (event, handler) => listeners[event] = handler,
    };
    const history = Object.fromEntries(['pushState', 'replaceState'].map(method => [method, (state, title, href) => {
        writes.push({method, href}); location.href = href;
    }]));
    vm.runInNewContext(readFileSync('public/js/vita-superadmin-navigation.js', 'utf8'), { window, document, history, location, URL, HTMLFormElement: Form });
    return { window, document, location, listeners, writes, navigations, Form };
}

test('module navigation exposes clean addresses and retains filters', () => {
    const app = setup();
    app.window.VitaNavigation.send({type: 'vita:module-location', url: 'https://vita.test/superadmin/customers?_vita_content=1&search=centro&_vita_frame=1#tabla', title: 'Clientes', historyPush: true});
    assert.deepEqual(app.writes, [{method: 'pushState', href: 'https://vita.test/superadmin/customers?search=centro#tabla'}]);
    assert.equal(app.document.title, 'Clientes');
    app.window.VitaNavigation.send({type: 'vita:module-location', url: 'https://vita.test/superadmin/customers?search=centro#tabla', historyPush: false});
    assert.equal(app.writes.length, 1);
    app.window.VitaNavigation.send({type: 'vita:module-location', url: 'https://other.test/dashboard', historyPush: true});
    assert.equal(app.writes.length, 1);
});

test('back navigation restores the central module without adding another entry', () => {
    const app = setup();
    app.location.href = 'https://vita.test/profile';
    app.listeners.popstate();
    assert.deepEqual(app.navigations, [['https://vita.test/profile', false]]);
    app.window.VitaNavigation.send({type: 'vita:module-location', url: 'https://vita.test/profile?_vita_content=1'});
    assert.equal(app.writes.length, 0);
});

test('logout leaves the persistent layout while other forms stay inside', () => {
    const app = setup();
    const logout = new app.Form('/logout');
    app.listeners.submit({target: logout});
    assert.equal(logout.target, '_top');
    const form = new app.Form('/website/topbar');
    app.listeners.submit({target: form});
    assert.equal(form.target, undefined);
});
