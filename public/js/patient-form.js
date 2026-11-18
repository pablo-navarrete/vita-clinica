// Shared patient creation/edit submission. No appointment creation.
function newPatientRegistrationOperation() {
        if (typeof globalThis.crypto.randomUUID === 'function') return globalThis.crypto.randomUUID();
        // Local Laragon HTTP domains do not expose randomUUID (secure-context API).
        const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    }
window.VitaPatientForm = {operation:newPatientRegistrationOperation,save:function savePatient({isEdit=false,editingPatientId=null,registrationOperation,clearErrors,showErrors,csrfToken,onSaved}) {

        clearErrors();


        const identityState = Alpine.$data(document.getElementById('patient-modal'));
        if(identityState.identityLoading || identityState.identityFailed){Swal.fire({icon:'error',text:'Espera la carga de identificación o vuelve a abrir el paciente.'});return;}
        identityState.errors = {};
        const patientData = {
            registration_operation: registrationOperation,
            ...Object.fromEntries(['identification_enabled','identification_country','identification_type','identification_number','identification_expected_primary'].map(key=>[key,identityState.form[key]])),

            name: $('#patient-name').val(),



            birth_date: $('#patient-birth-date').val(),

            gender: $('#patient-gender').val(),

            phone: $('#patient-phone').val(),

            email: $('#patient-email').val(),

            address: $('#patient-address').val(),

            insurance_id: $('#patient-insurance').val(),

        };


        let url;

        let method;


        if (isEdit) {

            url = `/patient/${editingPatientId}`;

            method = 'PUT';

        } else {

            url = '/patient';

            method = 'POST';

        }


        const saveButton =
            $('#patient-modal-save');


        saveButton
            .prop('disabled', true)
            .text('Guardando...');


        return fetch(url, {

            method: method,

            headers: {

                'Content-Type': 'application/json',

                'Accept': 'application/json',

                'X-CSRF-TOKEN': csrfToken

            },

            body: JSON.stringify(patientData)

        })

        .then(async response => {

            const data =
                await response.json();


            if (!response.ok) {

                if (response.status === 422) {

                    identityState.errors = data.errors || {};
                    showErrors(data.errors || {});


                    throw new Error(
                        'validation'
                    );

                }


                throw new Error(
                    data.message ||
                    'Error al guardar paciente'
                );

            }


            return data;

        })

        .then(data => {

            Swal.fire({

                toast: true,

                position: 'top-end',

                icon: 'success',

                title: isEdit
                    ? 'Paciente actualizado correctamente'
                    : 'Paciente creado correctamente',

                showConfirmButton: false,

                timer: 2000

            });


            onSaved(data.data);

        })

        .catch(error => {

            console.error(error);


            if (
                error.message !==
                'validation'
            ) {

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text:
                        error.message ||
                        'No se pudo guardar el paciente'

                });

            }

        })

        .finally(() => {

            saveButton
                .prop('disabled', false)
                .text('Guardar');

        });

    }};
