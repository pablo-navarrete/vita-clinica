/* Temporarily switches modals. Appointment fields and selection stay in the same DOM. */
(() => {
    function mount(){
        const trigger=document.getElementById('agenda-create-patient'),modal=document.getElementById('patient-modal'),appointment=document.getElementById('appointmentModal');
        if(!trigger||!modal||!appointment||trigger.dataset.mounted)return;
        trigger.dataset.mounted='true';let operation,busy=false;
        const fields={name:'patient-name',birth_date:'patient-birth-date',gender:'patient-gender',phone:'patient-phone',email:'patient-email',address:'patient-address'};
        const clearErrors=()=>{modal.querySelectorAll('[id^="error-"]').forEach(el=>{el.textContent='';el.classList.add('hidden');});};
        const close=()=>{modal.dispatchEvent(new Event('portal-autofill-reset'));modal.classList.add('hidden');modal.classList.remove('flex');appointment.classList.remove('hidden');trigger.focus();};
        trigger.addEventListener('click',()=>{
            if(busy)return;
            operation=window.VitaPatientForm.operation();
            const state=window.Alpine.$data(modal);state.form=window.VitaIdentification.defaults('patient');state.isEdit=false;state.errors={};state.identityLoading=false;state.identityFailed=false;
            for(const [field,id]of Object.entries(fields)){
                const input=document.getElementById(id);if(input._flatpickr)input._flatpickr.clear();else input.value=field==='gender'?'Masculino':'';
            }
            document.getElementById('patient-insurance').selectedIndex=0;clearErrors();
            modal.dispatchEvent(new Event('portal-autofill-reset'));appointment.classList.add('hidden');modal.classList.remove('hidden');modal.classList.add('flex');document.getElementById('patient-name').focus();
        });
        document.getElementById('patient-modal-cancel').addEventListener('click',()=>{if(!busy)close();});
        document.getElementById('patient-modal-save').addEventListener('click',async()=>{
            if(busy)return;busy=true;
            try{await window.VitaPatientForm.save({registrationOperation:operation,clearErrors,csrfToken:document.querySelector('meta[name="csrf-token"]').content,
                showErrors(errors){for(const [key,messages]of Object.entries(errors)){const el=document.getElementById('error-'+key.replaceAll('_','-'));if(el){el.textContent=messages[0];el.classList.remove('hidden');}}},
                onSaved(patient){
                    const select=document.getElementById('appointmentPatient');
                    let option=Array.from(select.options).find(option=>String(option.value)===String(patient.id));
                    if(!option){option=new Option([patient.identification_label,patient.name].filter(Boolean).join(' - '),patient.id,true,true);select.add(option);}
                    select.value=String(patient.id);window.jQuery(select).trigger('change');close();
                }});
            }finally{busy=false;}
        });
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
})();
