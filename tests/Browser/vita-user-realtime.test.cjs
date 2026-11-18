const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function setup() {
    const timers = new Map(), handlers = {}, connection = {}, channel = {};
    let sequence = 0, calls = 0;
    const events = [];
    const nodes = {
        'vita-system-notifications': {dataset:{customerId:'4',userId:'8',summaryUrl:'/notifications/summary'},addEventListener(){}},
        'system-notification-badge': {style:{}}, 'system-notification-count': {}, 'system-notification-list': {},
    };
    const echo = {private(name) { assert.equal(name, 'clinic-user.4.8'); return {
        listen(name, callback) { channel.message = callback; return this; },
        subscribed(callback) { channel.subscribed = callback; return this; },
        error(callback) { channel.error = callback; return this; },
    }; }, connector:{pusher:{connection:{bind(name, callback){connection[name] = callback;}}}}};
    const window = {Echo:echo, dispatchEvent(event){events.push(event.type);}, addEventListener(name, callback){handlers[name]=callback;}};
    const document = {readyState:'complete',hidden:false,getElementById:id=>nodes[id],addEventListener(name, callback){handlers[name]=callback;}};
    vm.runInNewContext(fs.readFileSync('public/js/vita-user-realtime.js','utf8'), {
        window,document,AbortController,CustomEvent:class {constructor(type){this.type=type;}},
        setTimeout(callback){const id=++sequence;timers.set(id,callback);return id;},clearTimeout(id){timers.delete(id);},setInterval(){return 1;},clearInterval(){},
        async fetch(){calls++;return {ok:true,async json(){return {unread:3,html:'system notifications'};}};},
    });
    return {nodes,window,events,channel,connection,get calls(){return calls;},async flush(){const pending=[...timers.values()];timers.clear();for(const callback of pending) await callback();}};
}

test('system events update the bell without signaling chat', async () => {
    const app=setup(); await app.flush(); app.events.length=0;
    const before=app.calls;
    app.channel.message({area:'system'}); await app.flush();
    assert.equal(app.calls,before+1);
    assert.deepEqual(app.events,[]);
    assert.equal(app.nodes['system-notification-badge'].textContent,'3');
    assert.equal(app.nodes['system-notification-list'].innerHTML,'system notifications');
});
test('chat events signal chat without touching the bell', async () => {
    const app=setup();await app.flush();app.events.length=0;
    const before=app.calls;
    app.channel.message({area:'chat',conversation_id:12});await app.flush();
    assert.deepEqual(app.events,['vita:chat-sync']);
    assert.equal(app.calls,before);
});
test('subscription recovery resynchronizes both areas', async () => {
    const app=setup();await app.flush();app.events.length=0;
    app.channel.subscribed();assert.equal(app.window.VitaUserRealtime.ready,true);
    await app.flush();
    app.connection.state_change({current:'disconnected'});
    assert.equal(app.window.VitaUserRealtime.ready,false);
    app.channel.subscribed();await app.flush();
    assert.equal(app.window.VitaUserRealtime.ready,true);
    assert.deepEqual(app.events,['vita:chat-sync','vita:chat-sync']);
});
