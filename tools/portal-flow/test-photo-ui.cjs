const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

class Input {
    constructor() { this.files = []; this.events = {}; this.required = false; }
    set value(value) { if (value === '') this.files = []; }
    addEventListener(name, handler) { this.events[name] = handler; }
}
const form = new Input(), camera = new Input(), gallery = new Input(), preview = {hidden:true}, status = {};
gallery.required = true;
const nodes = {'#application':form, '#photoCamera':camera, '#photoGallery':gallery, '#photoPreview':preview, '#photoStatus':status};
let serial = 0;
const urls = new Map();
class FakeImage {
    set src(url) {
        const file = urls.get(url);
        this.naturalWidth = file.width || 300;
        this.naturalHeight = file.height || 400;
        queueMicrotask(() => this.onload());
    }
}
class FakeFile { constructor(blobs, name, options) { this.size = blobs[0].size; this.type = options.type; this.name = name; } }
class Transfer {
    constructor() { this.files = []; this.items = {add: file => this.files.push(file)}; }
}
const canvas = {getContext:() => ({drawImage:() => {}}), toBlob: callback => callback({size:100000})};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/js/admission-photo.js'), 'utf8'), {
    document:{querySelector: selector => nodes[selector], createElement:() => canvas}, Image:FakeImage, DataTransfer:Transfer, File:FakeFile,
    URL:{createObjectURL:file => {const url = 'blob:'+(++serial); urls.set(url,file); return url;}, revokeObjectURL:() => {}},
});
(async () => {
    gallery.files = [{type:'image/jpeg', size:100000}];
    await gallery.events.change();
    assert.equal(preview.hidden, false);
    assert.equal(gallery.required, true);
    camera.files = [{type:'image/jpeg', size:6000000, width:4032, height:3024}];
    const processing = camera.events.change();
    let blocked = false;
    form.events.submit({preventDefault:() => blocked = true});
    assert.equal(blocked, true);
    await processing;
    assert.equal(gallery.files.length, 0);
    assert.equal(gallery.required, false);
    assert.equal(camera.files[0].size, 100000);
    assert.equal(preview.hidden, false);
    blocked = false;
    form.events.submit({preventDefault:() => blocked = true});
    assert.equal(blocked, false);
    gallery.files = [{type:'application/x-php', size:1000}];
    await gallery.events.change();
    assert.equal(camera.files.length, 0);
    assert.equal(gallery.files.length, 0);
    assert.equal(preview.hidden, true);
    assert.equal(gallery.required, true);
    gallery.files = [{type:'image/png', size:1000, width:50, height:50}];
    await gallery.events.change();
    assert.equal(gallery.files.length, 0);
    console.log('PASS | gallery/camera replacement, photo compression, busy submit guard and invalid photo handling');
})().catch(error => {console.error(error); process.exitCode = 1;});
