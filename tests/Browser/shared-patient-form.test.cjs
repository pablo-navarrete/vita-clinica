const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs'),{webcrypto}=require('node:crypto');
const source=fs.readFileSync('public/js/patient-form.js','utf8');
function harness(status=200){
 const values={'#patient-name':'Patient entered in shared form','#patient-birth-date':'1990-01-01','#patient-gender':'Otro','#patient-email':'basic@example.test','#patient-phone':'912345678','#patient-address':'Chile','#patient-insurance':''};
 const calls=[],state={form:{identification_enabled:true,identification_country:'CL',identification_type:'national_id',identification_number:'12.345.678-5'},identityLoading:false,identityFailed:false};let saved=0,errors;
 const jq=selector=>({val:()=>values[selector],prop(){return this;},text(){return this;}});
 const context={window:{},crypto:webcrypto,Uint8Array,document:{getElementById:()=>({})},Alpine:{$data:()=>state},$:jq,Swal:{fire(){}},console:{error(){}},fetch:async(url,request)=>{calls.push({url,...request});return {ok:status===200,status,json:async()=>status===200?{data:{id:12,name:values['#patient-name']}}:{errors:{identification_number:['Duplicate document']}}};}};
 vm.runInNewContext(source,context);
 const options={registrationOperation:'operation-uuid',clearErrors(){},showErrors(value){errors=value;},csrfToken:'csrf',onSaved(){saved++;}};
 return {calls,state,options,save:extra=>context.window.VitaPatientForm.save({...options,...extra}),saved:()=>saved,errors:()=>errors};
}
test('both hosts use the patient endpoint and never create appointments',async()=>{
 const h=harness();await h.save();assert.equal(h.calls[0].url,'/patient');assert.equal(h.calls[0].method,'POST');assert.equal(h.saved(),1);
 const data=JSON.parse(h.calls[0].body);assert.equal(data.registration_operation,'operation-uuid');assert.equal(data.name,'Patient entered in shared form');assert.equal(data.portal_account_id,undefined);assert.equal(data.professional_id,undefined);
});
test('existing edit flow still sends PUT to the same patient route',async()=>{
 const h=harness();await h.save({isEdit:true,editingPatientId:9});assert.equal(h.calls[0].url,'/patient/9');assert.equal(h.calls[0].method,'PUT');assert.equal(h.saved(),1);
});
test('duplicate response does not return to the appointment or change retry identity',async()=>{
 const h=harness(422);await h.save();await h.save();assert.equal(h.saved(),0);assert.equal(h.errors().identification_number[0],'Duplicate document');assert.equal(h.state.errors.identification_number[0],'Duplicate document');
 assert.equal(JSON.parse(h.calls[0].body).registration_operation,JSON.parse(h.calls[1].body).registration_operation);
});
test('identity loading prevents submission just as in patient module',async()=>{
 const h=harness();h.state.identityLoading=true;await h.save();assert.equal(h.calls.length,0);
});
