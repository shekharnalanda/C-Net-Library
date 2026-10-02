const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const script = fs.readFileSync(__dirname + '/../../public/js/library-app-install.js', 'utf8');
const worker = fs.readFileSync(__dirname + '/../../public/library-app-sw.js', 'utf8');
function page(standalone = false) {
    const handlers = {}, elements = Object.fromEntries(['cnet-app-install','cnet-app-status','cnet-app-help'].map(id => [id, {addEventListener(type, fn) { this[type] = fn; }}]));
    const window = {matchMedia: () => ({matches: standalone}), navigator: {}, isSecureContext: true, addEventListener: (type, fn) => handlers[type] = fn};
    let registration;
    const navigator = {serviceWorker: {register: (...args) => { registration = args; return Promise.resolve(); }}};
    vm.runInNewContext(script, {window, navigator, document: {getElementById: id => elements[id]}});
    return {handlers, button: elements['cnet-app-install'], status: elements['cnet-app-status'], help: elements['cnet-app-help'], registration};
}
(async () => {
    const manual = page();
    await manual.button.click();
    assert.equal(manual.help.open, true);
    assert.equal(manual.registration[0], '/library-app-sw.js');
    assert.equal(manual.registration[1].scope, '/');
    let prompted = 0, prevented = 0;
    const native = page();
    native.handlers.beforeinstallprompt({preventDefault: () => prevented++, prompt: async () => prompted++, userChoice: Promise.resolve({outcome: 'accepted'})});
    await native.button.click();
    await native.button.click();
    assert.equal(prompted, 1); assert.equal(prevented, 1);
    native.handlers.appinstalled();
    assert.equal(native.button.disabled, true);
    assert.equal(page(true).button.disabled, true);
    const dismissed = page();
    dismissed.handlers.beforeinstallprompt({preventDefault() {}, prompt: async () => {}, userChoice: Promise.resolve({outcome:'dismissed'})});
    await dismissed.button.click(); assert.equal(dismissed.help.open, true);
    const events = {}, stored = [], deleted = [];
    const caches = {open: async () => ({add: async url => stored.push(url)}), keys: async () => ['other-app','cnet-library-app-shell-old'], delete: async key => deleted.push(key), match: async () => 'offline'};
    let online = true;
    vm.runInNewContext(worker, {self:{addEventListener: (type, fn) => events[type] = fn, location:{origin:'https://library.test'}, skipWaiting: async () => {}, clients:{claim: async () => {}}}, caches, URL, Response, fetch: async () => { if (online) return 'fresh-private-response'; throw Error('offline'); }});
    await new Promise((resolve, reject) => events.install({waitUntil: p => p.then(resolve,reject)}));
    assert.deepEqual(stored, ['/library-app-offline.html']);
    await new Promise((resolve, reject) => events.activate({waitUntil: p => p.then(resolve,reject)}));
    assert.deepEqual(deleted, ['cnet-library-app-shell-old']);
    for (const url of ['/student/dashboard','/admin/student-report','/admission']) {
        let response;
        const request = {method:'GET', mode:'navigate', url:'https://library.test'+url};
        events.fetch({request, respondWith: p => response = p});
        assert.equal(await response, 'fresh-private-response');
        online = false;
        events.fetch({request, respondWith: p => response = p});
        assert.equal(await response, 'offline');
        online = true;
    }
    events.fetch({request:{method:'POST',mode:'navigate',url:'https://library.test/admission'}, respondWith: () => assert.fail('POST intercepted')});
    events.fetch({request:{method:'GET',mode:'cors',url:'https://library.test/seats'}, respondWith: () => assert.fail('Live API intercepted')});
    events.fetch({request:{method:'GET',mode:'navigate',url:'https://test.example/test'}, respondWith: () => assert.fail('Other portal intercepted')});
    assert.deepEqual(stored, ['/library-app-offline.html']);
    console.log('PASS | install prompt, manual fallback, installed state, offline privacy and update isolation');
})().catch(error => { console.error(error); process.exitCode = 1; });
