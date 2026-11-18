const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const source=fs.readFileSync('public/js/patient-portal-autofill.js','utf8');
function harness({accepted=true,response={name:'Portal name',email:'portal@example.test',document:'12.345.678-5',phone:'912345678'},fetcher}={}){
 const listeners={},modalListeners={},fields={};let calls=0,popups=0,options;
 const element=()=>({children:[],textContent:'',className:'',setAttribute(){},append(...items){this.children.push(...items);}});
 const number={name:'identification_number',parentElement:element(),addEventListener:(event,fn)=>listeners[event]=fn};
 const modal={dataset:{},querySelector:()=>number,addEventListener:(event,fn)=>modalListeners[event]=fn};
 const state={isEdit:false,form:{identification_enabled:true,identification_country:'CL',identification_type:'national_id',identification_number:'123456785'}};
 for(const name of ['name','email','gender','birth-date','phone','address'])fields['patient-'+name]={value:'Manual value',dispatchEvent(){}};
 const document={readyState:'complete',getElementById:id=>id==='patient-modal'?modal:fields[id],querySelector:()=>({content:'test-csrf'}),createElement:element,createTextNode:text=>({textContent:text})};
 const window={Alpine:{$data:()=>state},VitaIdentification:{validateNumber:form=>form.identification_number==='bad'?'invalid':''},Swal:{close(){},fire:async value=>{popups++;options=value;return {isConfirmed:accepted};}}};
 vm.runInNewContext(source,{window,document,AbortController,Event:class{},fetch:async(...args)=>{calls++;return fetcher?fetcher(...args):{ok:true,json:async()=>({profile:response})};}});
 return {state,fields,blur:()=>listeners.blur(),change:()=>modalListeners.input({target:number}),reset:()=>modalListeners['portal-autofill-reset'](),calls:()=>calls,popups:()=>popups,options:()=>options};
}
test('accept fills only supplied basic fields and does not submit portal identifiers',async()=>{
 const h=harness();await h.blur();assert.equal(h.fields['patient-name'].value,'Portal name');assert.equal(h.fields['patient-address'].value,'Manual value');
 assert.equal(h.options().confirmButtonText,'Sí, autocompletar');assert.equal(h.options().cancelButtonText,'No, ingresar manualmente');
 await h.blur();assert.equal(h.calls(),1);
});
test('reject keeps all manual values and suppresses repeated modal',async()=>{
 const h=harness({accepted:false});await h.blur();await h.blur();assert.equal(h.fields['patient-name'].value,'Manual value');assert.equal(h.popups(),1);
});
test('missing profile and invalid or foreign documents do not open modal',async()=>{
 const h=harness({response:null});await h.blur();assert.equal(h.popups(),0);
 h.reset();h.state.form.identification_number='bad';await h.blur();assert.equal(h.calls(),1);
 h.state.form.identification_number='123456785';h.state.form.identification_country='AR';await h.blur();assert.equal(h.calls(),1);
});
test('edit mode cannot fetch or overwrite an existing patient',async()=>{
 const h=harness();h.state.isEdit=true;await h.blur();assert.equal(h.calls(),0);
});
test('changed document invalidates a response already in flight',async()=>{
 let resolve;const h=harness({fetcher:()=>new Promise(r=>resolve=r)});const pending=h.blur();h.state.form.identification_number='123456785';h.change();
 resolve({ok:true,json:async()=>({profile:{name:'Stale profile'}})});await pending;assert.equal(h.popups(),0);assert.equal(h.fields['patient-name'].value,'Manual value');
});
test('query error allows manual entry and retry',async()=>{
 const h=harness({fetcher:async()=>{throw new Error('offline');}});await h.blur();await h.blur();assert.equal(h.calls(),2);assert.equal(h.fields['patient-name'].value,'Manual value');
});
test('profile content is inserted as text, including potentially unsafe markup',async()=>{
 const h=harness({response:{name:'<img src=x onerror=alert(1)>'}});await h.blur();
 assert.equal(h.fields['patient-name'].value,'<img src=x onerror=alert(1)>');assert.equal(h.options().html.innerHTML,undefined);
});
