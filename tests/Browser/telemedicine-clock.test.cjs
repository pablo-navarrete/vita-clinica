const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const {readFileSync} = require('node:fs');

test('the consultation warns at five minutes and continues after thirty minutes', () => {
    const view = readFileSync('resources/views/customer/telemedicine/room.blade.php','utf8');
    const clockCode = view.slice(view.indexOf('const consultationDuration ='),view.indexOf('function refreshMediaButtons()'));
    let now = 0;
    const messages = [], elements = {};
    const context = {Date:{now:() => now},setInterval() {},location:{origin:'https://clinic.test'},
        document:{getElementById:id => elements[id] ||= {textContent:'',hidden:true}},
        ended:false,window:{parent:{postMessage:message => messages.push(message)}}};
    vm.createContext(context);
    vm.runInContext(clockCode + '\nconsultationStartedAt = 0; updateConsultationClock();',context);
    assert.match(elements.telemedicineTimer.textContent,/30:00/);
    assert.equal(messages.filter(message => message.type === 'vita:consultation-warning').length,0);
    now = 25 * 60 * 1000;
    vm.runInContext('updateConsultationClock()',context);
    assert.match(elements.telemedicineTimer.textContent,/05:00/);
    assert.equal(messages.filter(message => message.type === 'vita:consultation-warning').length,1);
    now = 26 * 60 * 1000;
    vm.runInContext('updateConsultationClock()',context);
    assert.equal(messages.filter(message => message.type === 'vita:consultation-warning').length,1);
    now = 31 * 60 * 1000;
    vm.runInContext('updateConsultationClock()',context);
    assert.match(elements.telemedicineTimer.textContent,/Puedes continuar/);
    assert.equal(context.ended,false);
});
