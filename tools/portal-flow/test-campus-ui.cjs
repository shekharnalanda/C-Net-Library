const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

class Option {
  constructor(text, value, dataset = {}) { this.text = text; this.value = value; this.dataset = dataset; }
  cloneNode() { return new Option(this.text, this.value, {...this.dataset}); }
}
class Element {
  constructor(options = []) { this.options = options; this.value = ''; this.listeners = {}; this.disabled = false; }
  replaceChildren(...children) { this.options = children; this.value = children[0]?.value || ''; }
  add(option) { this.options.push(option); }
  addEventListener(name, callback) { this.listeners[name] = callback; }
  trigger(name) { return this.listeners[name]?.(); }
}
const ids = ['campus','duration','plan','startDate','timing','seats','availabilityStatus','fees','submitApplication','startTime','endTime'];
const elements = Object.fromEntries(ids.map(id => [id,new Element()]));
elements.duration.options = [new Option('choose',''),new Option('4 hours','4',{campus:'1'}),new Option('4 hours','14',{campus:'2'})];
elements.plan.options = [new Option('choose',''),new Option('C-Net fee','40',{campus:'1',slot:'4'}),new Option('MCI fee','140',{campus:'2',slot:'14'})];
const requests = [];
const context = vm.createContext({Option, URL, URLSearchParams, AbortController,
  document:{querySelector: selector => elements[selector.slice(1)]},
  fetch: async url => {requests.push(url); return {ok:true,json:async()=>({times:[{label:'10 AM–2 PM',available:1,seats:[]}],monthly_fee:300,admission_fee:0,registration_fee:0,security_deposit:0,expiry:'2026-11-01'})};}
});
let script = fs.readFileSync(path.join(__dirname,'../../resources/views/public/admission.blade.php'),'utf8').split('<script>')[1].split('</script>')[0];
script = script.replace("@json(route('admission.availability'))",JSON.stringify('https://library.example/admission-availability'));
vm.runInContext(script,context);
const values = id => elements[id].options.map(option => option.value);
const settle = async () => { await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); };
(async()=>{
  assert.equal(elements.duration.disabled,true);
  elements.campus.value='1'; elements.campus.trigger('change');
  assert.deepEqual(values('duration'),['','4']);
  elements.duration.value='4'; elements.duration.trigger('change');
  assert.deepEqual(values('plan'),['','40']);
  elements.plan.value='40'; elements.startDate.value='2026-10-05'; elements.plan.trigger('change'); await settle();
  assert.equal(requests.at(-1).searchParams.get('branch_id'),'1');
  elements.startTime.value='06:00'; elements.endTime.value='10:00';
  elements.campus.value='2'; elements.campus.trigger('change');
  assert.deepEqual(values('duration'),['','14']);
  assert.equal(elements.duration.value,''); assert.equal(elements.plan.value,'');
  assert.equal(elements.startTime.value,''); assert.equal(elements.endTime.value,'');
  assert.equal(elements.submitApplication.disabled,true);
  elements.duration.value='14'; elements.duration.trigger('change');
  assert.deepEqual(values('plan'),['','140']);
  elements.plan.value='140'; elements.plan.trigger('change'); await settle();
  assert.equal(requests.at(-1).searchParams.get('branch_id'),'2');
  assert.equal(requests.at(-1).searchParams.get('study_slot_id'),'14');
  assert.equal(requests.at(-1).searchParams.get('fee_plan_id'),'140');
  elements.campus.value='1'; elements.campus.trigger('change');
  assert.deepEqual(values('duration'),['','4']);
  elements.campus.value='3'; elements.campus.trigger('change');
  assert.equal(elements.duration.disabled,true);
  assert.match(elements.duration.options[0].text,/कॉन्फ़िगर/);
  console.log('PASS | campus switching, independent fees, stale selection reset and missing setup notice');
})().catch(error=>{console.error(error);process.exitCode=1;});
