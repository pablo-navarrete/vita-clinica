const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const source=fs.readFileSync('public/js/portal-booking-form.js','utf8');
function harness(personal=[]){
 const listeners={},fields={},groups={};
 for(const [name,value]of Object.entries({identification_country:'AR',identification_type:'passport',name:'Owner',phone:'',specialty_id:'1',professional_id:'2',date:'2026-12-01',time:'10:00'})){
  groups[name]={hidden:false};fields[name]={value,options:[],addEventListener:(event,fn)=>listeners[name+event]=fn,closest:()=>groups[name],replaceChildren(...items){this.options=items;}};
 }
 const children=[],form={elements:{namedItem:name=>fields[name]},addEventListener:(event,fn)=>listeners[event]=fn,append:node=>children.push(node)};
 vm.runInNewContext(source,{window:{vitaPortalBookingFields:personal,vitaBookingDocumentTypes:{AR:[{value:'passport',label:'Pasaporte'}],CL:[{value:'national_id',label:'RUT'}]}},document:{getElementById:()=>form,createElement:()=>({setAttribute(){}})},Option:class {constructor(text,value){this.value=value;this.text=text;}}});
 return {fields,groups,children,listeners};
}
test('own available fields are hidden and missing fields stay editable',()=>{
 const h=harness(['name','phone','identification_country','identification_type']);assert.equal(h.groups.name.hidden,true);assert.equal(h.fields.name.readOnly,true);assert.equal(h.groups.phone.hidden,false);assert.equal(h.children.length,1);
 assert.match(h.children[0].textContent,/2026-12-01/);
});
test('traditional booking is unchanged and country selects registered document types',()=>{
 const h=harness();assert.equal(h.children.length,0);assert.equal(h.groups.name.hidden,false);
 h.fields.identification_country.value='CL';h.listeners.identification_countrychange();assert.equal(h.fields.identification_type.options[0].value,'national_id');
});
test('summary updates as the patient changes appointment selection',()=>{
 const h=harness(['name']);h.fields.time.value='11:30';h.listeners.change();assert.match(h.children[0].textContent,/11:30/);
});
