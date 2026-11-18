const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const source = fs.readFileSync('resources/views/customer/patient/index.blade.php', 'utf8');
const start = source.indexOf('function newPatientRegistrationOperation()');
const end = source.indexOf('let registrationOperation =', start);
const functionSource = source.slice(start, end);
test('local HTTP fallback generates distinct valid v4 operation UUIDs without randomUUID', () => {
    const context = vm.createContext({ crypto: { getRandomValues: values => webcrypto.getRandomValues(values) } });
    vm.runInContext(functionSource, context);
    const values = new Set(Array.from({ length: 100 }, () => context.newPatientRegistrationOperation()));
    assert.equal(values.size, 100);
    for (const value of values) assert.match(value, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
});
test('HTTPS uses the native cryptographic UUID implementation', () => {
    const expected = '12345678-1234-4321-8123-123456789abc';
    const context = vm.createContext({ crypto: { randomUUID: () => expected } });
    vm.runInContext(functionSource, context);
    assert.equal(context.newPatientRegistrationOperation(), expected);
});
