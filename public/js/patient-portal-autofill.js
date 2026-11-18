/* Uses the existing patient form and SweetAlert modal. No portal identifiers are submitted. */
(() => {
    function mount() {
        const modal=document.getElementById('patient-modal');
        if(!modal || modal.dataset.portalAutofillMounted)return;
        modal.dataset.portalAutofillMounted='true';
        const number=modal.querySelector('[name="identification_number"]');
        if(!number)return;
        let seen=new Set(), request=null, sequence=0, popup=false;
        const status=document.createElement('p');status.className='text-xs text-gray-500 mt-1';status.setAttribute('role','status');number.parentElement.append(status);
        const state=()=>window.Alpine.$data(modal);
        const key=()=>{
            const data=state();if(data.isEdit || !data.form.identification_enabled || data.form.identification_country!=='CL' || data.form.identification_type!=='national_id')return '';
            const form={...data.form};
            if(window.VitaIdentification.validateNumber(form))return '';
            return String(form.identification_number).replace(/[.\-\s]/g,'').toUpperCase();
        };
        const invalidate=()=>{sequence++;request?.abort();request=null;status.textContent='';if(popup){window.Swal.close();popup=false;}};
        modal.addEventListener('portal-autofill-reset',()=>{invalidate();seen=new Set();});
        modal.addEventListener('click',event=>{if(event.target.closest('#patient-modal-save,#patient-modal-cancel'))invalidate();},true);
        modal.addEventListener('input',event=>{if(event.target.name?.startsWith('identification_'))invalidate();});
        modal.addEventListener('change',event=>{if(event.target.name?.startsWith('identification_'))invalidate();});
        number.addEventListener('blur',async()=>{
            const lookup=key();if(!lookup || seen.has(lookup))return;
            seen.add(lookup);const token=++sequence;request=new AbortController();
            try {
                const response=await fetch('/patient/mi-salud-profile',{method:'POST',cache:'no-store',signal:request.signal,
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body:JSON.stringify({identification_country:'CL',identification_type:'national_id',identification_number:lookup})});
                if(!response.ok)throw new Error('lookup');
                const {profile}=await response.json();
                if(token!==sequence || lookup!==key() || !profile)return;
                const content=document.createElement('div');content.className='text-left text-sm';
                const question=document.createElement('p');question.textContent='Este paciente tiene una cuenta Mi Salud. ¿Deseas autocompletar el formulario con sus datos?';content.append(question);
                const labels={name:'Nombre',document:'RUT',email:'Correo',gender:'Sexo',birth_date:'Fecha de nacimiento',phone:'Teléfono',address:'Dirección'};
                for(const [field,label] of Object.entries(labels))if(profile[field]){
                    const row=document.createElement('p'),heading=document.createElement('strong');heading.textContent=label+': ';
                    row.append(heading,document.createTextNode(String(profile[field])));content.append(row);
                }
                const note=document.createElement('p');note.className='mt-3 text-xs text-gray-500';note.textContent='Al aceptar se reemplazarán los campos correspondientes que ya hayas completado.';content.append(note);
                popup=true;
                const result=await window.Swal.fire({title:'Mi Salud',text:'Este paciente tiene una cuenta Mi Salud. ¿Deseas autocompletar el formulario con sus datos?',
                    html:content,showCancelButton:true,confirmButtonText:'Sí, autocompletar',cancelButtonText:'No, ingresar manualmente',focusCancel:true});
                popup=false;
                if(!result.isConfirmed || token!==sequence || lookup!==key())return;
                const fields={name:'patient-name',email:'patient-email',gender:'patient-gender',birth_date:'patient-birth-date',phone:'patient-phone',address:'patient-address'};
                for(const [field,id] of Object.entries(fields))if(profile[field]){
                    const input=document.getElementById(id);if(!input)continue;
                    if(input._flatpickr)input._flatpickr.setDate(profile[field],false,'Y-m-d');else input.value=profile[field];
                    input.dispatchEvent(new Event('change',{bubbles:true}));
                }
            } catch(error) {
                if(error.name!=='AbortError' && token===sequence){seen.delete(lookup);status.textContent='No se pudo consultar Mi Salud. Puedes continuar ingresando los datos manualmente.';}
            }
        });
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
})();
