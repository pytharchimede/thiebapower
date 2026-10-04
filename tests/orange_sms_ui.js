const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const status = { textContent: '' }, output = { textContent: '' };
const copy = { hidden: true, addEventListener() {} };
const panel = { hidden: true, scrollIntoView() {}, focus() {}, querySelector(q) { return ({ '[data-sms-status]': status, '[data-sms-response]': output, '[data-sms-copy]': copy })[q]; } };
const nonce = { value: 'old' }, button = { disabled: false };
let submit, calls = 0, resolveResponse;
const form = { action: '/admin/sms/test', querySelectorAll() { return [button]; }, querySelector() { return nonce; }, addEventListener(_, cb) { submit = cb; } };
const sandbox = {
 document: { querySelector(q) { return q === '#sms-live-result' ? panel : null; }, querySelectorAll() { return [form]; } },
 FormData: class {}, navigator: { clipboard: { writeText: async () => {} } },
 fetch: () => { calls++; return new Promise(resolve => { resolveResponse = resolve; }); }
};
vm.runInNewContext(fs.readFileSync(__dirname + '/../public/orange-sms.js', 'utf8'), sandbox);
(async () => {
 const event = { preventDefault() {} };
 const pending = submit(event);
 assert.equal(panel.hidden, false); assert.equal(button.disabled, true);
 assert.match(status.textContent, /en cours/);
 await submit(event); assert.equal(calls, 1);
 resolveResponse({ headers: { get: () => 'application/json' }, json: async () => ({ state: 'checked', http_status: 200, data: [{ availableUnits: 42 }], next_nonce: 'new' }) });
 await pending;
 assert.match(output.textContent, /availableUnits/); assert.doesNotMatch(output.textContent, /next_nonce/);
 assert.equal(nonce.value, 'new'); assert.equal(button.disabled, false); assert.equal(copy.hidden, false);
 const rejected = submit(event);
 resolveResponse({ status: 403, headers: { get: () => 'text/html' } }); await rejected;
 assert.match(status.textContent, /Accès refusé/); assert.equal(button.disabled, false);
 console.log('Orange SMS UI: immediate response, one request, JSON data, renewed nonce and visible permission error OK');
})().catch(error => { console.error(error); process.exitCode = 1; });
