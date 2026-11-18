const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/js/agenda-patient-form.js','utf8');
function harness({fail=false}={}){
 const nodes={},callbacks={};let calls=0,changes=0;
 function node(id){const classes=new Set(id==='patient-modal'?['hidden']:[]);return nodes[id]={id,value:'',dataset:{},options:[],selectedIndex:0,
 classList:{add:c=>classes.add(c),remove:c=>classes.delete(c),contains:c=>classes.has(c)},addEventListener:(name,fn)=>callbacks[id+':'+name]=fn,
 dispatchEvent(){},focus(){},querySelectorAll:()=>[],add(option){this.options.push(option);}};}
 for(const id of ['agenda-create-patient','patient-modal','appointmentModal','patient-name','patient-birth-date','patient-gender','patient-phone','patient-email','patient-address','patient-insurance','patient-modal-save','patient-modal-cancel','appointmentPatient','appointmentProfessional','appointmentSpecialty','appointmentClinicBranch','appointmentDate','appointmentTime','appointmentReason'])node(id);
 for(const id of ['appointmentProfessional','appointmentSpecialty','appointmentClinicBranch','appointmentDate','appointmentTime','appointmentReason'])nodes[id].value='original-'+id;
 const state={form:{}};const context={document:{readyState:'complete',getElementById:id=>nodes[id],querySelector:()=>({content:'csrf'})},Event:class{},Option:class{constructor(text,value){this.text=text;this.value=value;}},
 window:{Alpine:{$data:()=>state},VitaIdentification:{defaults:()=>({identification_country:'CL'})},jQuery:()=>({trigger:()=>changes++}),VitaPatientForm:{operation:()=> 'test-operation',save:async options=>{calls++;if(!fail)options.onSaved({id:123,name:'Created patient',identification_label:'RUT test'});}}}};
 vm.runInNewContext(source,context);
 return {nodes,state,calls:()=>calls,changes:()=>changes,click:id=>callbacks[id+':click']()};
}
test('patient creation preserves the entire appointment draft and selects the result without booking',async()=>{
 const h=harness();const draft=Object.fromEntries(Object.entries(h.nodes).filter(([id])=>id.startsWith('appointment')&&id!=='appointmentPatient').map(([id,n])=>[id,n.value]));
 h.click('agenda-create-patient');assert.equal(h.nodes.appointmentModal.classList.contains('hidden'),true);
 await h.click('patient-modal-save');assert.equal(h.nodes.appointmentPatient.value,'123');assert.equal(h.nodes.appointmentPatient.options.length,1);assert.equal(h.calls(),1);assert.equal(h.changes(),1);
 assert.equal(h.nodes.appointmentModal.classList.contains('hidden'),false);for(const[id,value]of Object.entries(draft))assert.equal(h.nodes[id].value,value);
});
test('cancel returns to the untouched appointment without selecting or creating a patient',()=>{
 const h=harness();h.click('agenda-create-patient');h.click('patient-modal-cancel');assert.equal(h.calls(),0);assert.equal(h.nodes.appointmentPatient.value,'');assert.equal(h.nodes.appointmentModal.classList.contains('hidden'),false);
});
test('validation failure keeps the patient form open and preserves the appointment',async()=>{
 const h=harness({fail:true});h.click('agenda-create-patient');await h.click('patient-modal-save');assert.equal(h.nodes['patient-modal'].classList.contains('hidden'),false);assert.equal(h.nodes.appointmentPatient.value,'');assert.equal(h.nodes.appointmentTime.value,'original-appointmentTime');
});
test('existing option is selected instead of being duplicated',async()=>{
 const h=harness();h.nodes.appointmentPatient.options.push({value:'123',text:'Already present'});h.click('agenda-create-patient');await h.click('patient-modal-save');assert.equal(h.nodes.appointmentPatient.options.length,1);
});
