const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const blade = fs.readFileSync('resources/views/customer/patient/medical-record.blade.php', 'utf8');
const listener = blade.match(/document\.addEventListener\('click', event => \{\s*const link = event\.target\.closest\('#encounter-history a'\);[\s\S]*?\n\s*\}\);/);
assert.ok(listener, 'History navigation handler exists');
let handler; const updated = [];
const origin = 'https://clinic.example.test';
vm.runInNewContext(listener[0], {
    document: { addEventListener: (_, callback) => { handler = callback; } },
    location: { origin, pathname: '/patients/7/medical-record' }, URL,
    updateEncounterHistory: url => updated.push(url),
});
function click(path, extra = {}) {
    let prevented = false;
    const link = { href: origin + path, origin, target: '', hasAttribute: () => false };
    handler({ target: { closest: () => link }, button: 0, preventDefault() { prevented = true; }, ...extra });
    return prevented;
}
assert.equal(click('/patients/7/encounters/12/addenda/create'), false);
assert.equal(updated.length, 0);
assert.equal(click('/patients/7/medical-record?page=2'), true);
assert.deepEqual(updated, [origin + '/patients/7/medical-record?page=2']);
assert.equal(click('/patients/7/medical-record?page=3', { ctrlKey: true }), false);
assert.equal(updated.length, 1);
console.log('PASS addenda use module navigation while history pagination keeps its own update');
