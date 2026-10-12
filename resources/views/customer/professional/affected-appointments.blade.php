@extends('layouts.menu-customer')

@section('header')
    <div class="flex justify-between items-start">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Citas afectadas por el horario') }}
            </h2>
        </div>
        <button type="button" onclick="history.back()" class="px-5 py-2 rounded-lg bg-gray-200 hover:bg-gray-300">
            Volver
        </button>
    </div>
@endsection

@section('content')
    @php
        $days = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];
    @endphp

    <div class="py-12">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div class="p-6 text-gray-900">

    {{-- MENSAJE --}}
                    <div class="mb-6">

                        <div class="p-4 rounded-lg border border-blue-200 bg-blue-50 text-sm text-blue-800">

                            <strong>Importante:</strong>

                            Las siguientes citas utilizan este horario.
                            Antes de eliminarlo, debes gestionar cada cita
                            reagendándola o cancelándola.

                        </div>

                    </div>


                    {{-- INFORMACIÓN DEL HORARIO --}}
                    <div class="mb-6 p-4 rounded-lg border border-orange-200 bg-orange-50">

                        <div class="flex items-start justify-between gap-4">

                            {{-- IZQUIERDA: INFORMACIÓN DEL HORARIO --}}
                            <div class="flex items-start gap-3">

                                {{-- ICONO --}}
                                <div class="flex-shrink-0">

                                    <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">

                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">

                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />

                                        </svg>

                                    </div>

                                </div>


                                {{-- INFORMACIÓN --}}
                                <div class="text-sm text-orange-800">

                                    <div class="font-semibold text-base mb-1">
                                        Horario del profesional
                                    </div>

                                    <div class="space-y-1">

                                        {{-- DÍA --}}
                                        <div>
                                            <strong>Día:</strong>

                                            {{ $days[$schedule->day_of_week] ?? 'Día desconocido' }}
                                        </div>


                                        {{-- HORARIO --}}
                                        <div>
                                            <strong>Horario:</strong>

                                            {{ $schedule->start_time?->format('H:i') ?? '' }}
                                            -
                                            {{ $schedule->end_time?->format('H:i') ?? '' }}
                                            hrs
                                        </div>


                                        {{-- PROFESIONAL --}}
                                        <div>
                                            <strong>Profesional:</strong>

                                            {{ $schedule->professional->name ?? '' }}
                                        </div>

                                    </div>

                                </div>

                            </div>


                            {{-- DERECHA: BOTÓN ELIMINAR --}}
                            <div class="flex-shrink-0">

                                @if ($affectedAppointmentsCount > 0)
                                    <button id="delete-schedule-button" type="button" disabled
                                        class="inline-flex items-center gap-2 px-4 py-2
                                        bg-gray-300 text-gray-500
                                        text-sm font-medium
                                        rounded-lg shadow-sm
                                        cursor-not-allowed
                                        transition duration-150">

                                        <i class="fa-solid fa-trash"></i>

                                        <span id="delete-schedule-text">
                                            Hay citas pendientes
                                        </span>

                                    </button>
                                @else
                                    <button id="delete-schedule-button" type="button"
                                        onclick="deleteSchedule({{ $schedule->id }})"
                                        class="inline-flex items-center gap-2 px-4 py-2
                                        bg-red-600 hover:bg-red-700
                                        text-white
                                        text-sm font-medium
                                        rounded-lg shadow-sm
                                        cursor-pointer
                                        transition duration-150">

                                        <i class="fa-solid fa-trash"></i>

                                        <span id="delete-schedule-text">
                                            Borrar Horario
                                        </span>

                                    </button>
                                @endif



                            </div>

                        </div>

                    </div>

                    


                

                    {{-- TÍTULO --}}
                    <div class="mb-6">

                        <h3 class="text-lg font-semibold text-gray-800">
                            Citas que deben ser gestionadas
                        </h3>

                     

                    </div>
                    {{-- TABLA --}}
                    <div class="overflow-x-auto">

                        <table id="affected-appointments-table"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">

                            <thead style="background-color: {{ themeColor(100) }}">

                                <tr>

                                    <th>#</th>

                                    <th>Paciente</th>
                                    <th>RUT</th>

                                    <th>Especialidad</th>

                                    <th>Fecha</th>

                                    <th>Hora</th>

                                    <th>Estado</th>

                                    <th>Acciones</th>

                                </tr>

                            </thead>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>





{{-- ========================================================= --}}
{{-- MODAL CONTACTAR PACIENTE --}}
{{-- ========================================================= --}}

<div
    id="contact-patient-modal"
    class="hidden fixed flex  inset-0 items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

    <div class="bg-white rounded-lg shadow-lg w-full max-w-md flex flex-col">

        {{-- HEADER --}}

        <div
            class="px-6 py-4 border-b rounded-t-lg"
            style="background-color: {{ themeColor(100) }}">

            <div class="flex items-center justify-between">

                <div>

                    <h2 class="text-lg font-bold text-gray-800">
                        Contactar paciente
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Selecciona un medio de contacto
                    </p>

                </div>

            

            </div>

        </div>


        {{-- BODY --}}

        <div class="p-6 space-y-5">


            {{-- PACIENTE --}}

            <div>

                <label class="block text-sm font-medium text-gray-500 mb-1">
                    Paciente
                </label>

                <p
                    id="contact-patient-name"
                    class="text-gray-900 font-semibold">

                    —

                </p>
               
              
                <p
                    id="contact-patient-rut"
                    class="text-sm text-gray-500 mt-1">

                    RUT: —

                </p>

            </div>


            {{-- TELÉFONO --}}

            <div>

                <label class="block text-sm font-medium text-gray-500 mb-1">
                    Teléfono
                </label>

                <div class="flex items-center gap-2">

                    <p
                        id="contact-patient-phone"
                        class="text-gray-800 flex-1">

                        —

                    </p>

                    {{-- COPIAR TELÉFONO --}}

                   <button type="button" 
                   id="copy-phone-button" onclick="copyPatientPhone()"
                    class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition"> 
                    <i class="fa-regular fa-copy mr-1"></i> 
                    <span>Copiar</span> 
                </button>





                </div>

            </div>


            {{-- CORREO --}}

            <div>

                <label class="block text-sm font-medium text-gray-500 mb-1">
                    Correo electrónico
                </label>

                <div class="flex items-center gap-2">

                    <p
                        id="contact-patient-email"
                        class="text-gray-800 flex-1 break-all">

                        —

                    </p>

                    {{-- COPIAR CORREO --}}

<button type="button" 
id="copy-email-button" 
onclick="copyPatientEmail()" 
class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition"> 
    <i class="fa-regular fa-copy mr-1"></i> 
    <span>Copiar</span> 
</button>

                </div>

            </div>


            {{-- ACCIONES --}}

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-3">
                    Contactar mediante
                </label>


                <div class="grid grid-cols-3 gap-3">


                    {{-- WHATSAPP --}}

                    <a
                        id="contact-whatsapp"
                        href="#"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex flex-col items-center
                               justify-center gap-2
                               px-3 py-4
                               rounded-lg
                               border border-gray-200
                               hover:bg-gray-50
                               transition">

                        <i class="fa-brands fa-whatsapp
                                  text-xl text-green-600"></i>

                        <span class="text-sm font-medium text-gray-700">
                            WhatsApp
                        </span>

                    </a>


                    {{-- CORREO --}}

                    <button
                        type="button"
                        id="contact-email-button"
                        onclick="emailPatient()"
                        class="flex flex-col items-center
                               justify-center gap-2
                               px-3 py-4
                               rounded-lg
                               border border-gray-200
                               hover:bg-gray-50
                               transition">

                        <i class="fa-solid fa-envelope
                                  text-xl text-blue-600"></i>

                        <span class="text-sm font-medium text-gray-700">
                            Correo
                        </span>

                    </button>


                    {{-- LLAMAR --}}

                    <a
                        id="contact-phone-button"
                        href="#"
                        class="flex flex-col items-center
                               justify-center gap-2
                               px-3 py-4
                               rounded-lg
                               border border-gray-200
                               hover:bg-gray-50
                               transition">

                        <i class="fa-solid fa-phone
                                  text-xl text-gray-600"></i>

                        <span class="text-sm font-medium text-gray-700">
                            Llamar
                        </span>

                    </a>

                </div>

            </div>

        </div>


        {{-- FOOTER --}}

        <div
            class="px-6 py-4
                   border-t
                   flex justify-end">

            <button
                type="button"
                onclick="closeContactModal()"
                class="bg-gray-600
                       hover:bg-gray-700
                       text-white
                       px-4 py-2
                       rounded">

                Cerrar

            </button>

        </div>

    </div>

</div>


@endsection


@push('scripts')
    <script>
        $(document).ready(function() {

            const table = $('#affected-appointments-table').DataTable({

                processing: true,
                serverSide: true,
                responsive: true,

                ajax: {
                    url: '/customer/schedules/{{ $schedule->id }}/affected-appointments/data',
                    type: 'GET'
                },

                columns: [{
                        data: 'id',
                        name: 'id'
                    },
                    {
                        data: 'patient',
                        name: 'patient'
                    },
                    {
                        data: 'rut',
                        name: 'patient.rut'
                    },
                    {
                        data: 'specialty',
                        name: 'specialty'
                    },
                    {
                        data: 'date',
                        name: 'date'
                    },
                    {
                        data: 'time',
                        name: 'time'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        render: function(data, type, row) {

                            const colors = {
                                0: '#3B82F6',
                                1: '#10B981',
                                2: '#F59E0B',
                                3: '#6366F1',
                                4: '#EF4444',
                                5: '#6B7280'
                            };

                            const labels = {
                                0: 'Agendado',
                                1: 'Confirmada',
                                2: 'En atención',
                                3: 'Atendida',
                                4: 'No asistió',
                                5: 'Cancelada'
                            };

                            const color = colors[data] || '#3B82F6';
                            const label = labels[data] || 'Agendado';

                            return `
                                <span style="
                                    background-color: ${color}20;
                                    color: ${color};
                                    padding: 4px 10px;
                                    border-radius: 9999px;
                                    font-size: 12px;
                                    font-weight: 600;
                                    display: inline-block;
                                ">
                                    ${label}
                                </span>
                            `;
                        }
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false
                    }
                ],

                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
                }

            });

        });


        // ==========================================
        // CANCELAR CITA
        // ==========================================

        function cancelAppointment(appointmentId) {

            Swal.fire({
                title: '¿Estás seguro?',
                text: 'Se le notificará al paciente por el medio de comunicación asignado.',
                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Sí, cancelar cita',
                cancelButtonText: 'No, volver',

                reverseButtons: true
            }).then((result) => {

                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: '/customer/appointments/' + appointmentId + '/cancel',
                    type: 'POST',

                    data: {
                        _token: '{{ csrf_token() }}'
                    },

                    success: function(response) {

                        Swal.fire({
                            title: 'Cita cancelada',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {

                            location.reload();

                        });

                    },

                    error: function(xhr) {

                        Swal.fire({
                            title: 'Error',
                            text: xhr.responseJSON?.message ||
                                'No fue posible cancelar la cita.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });

                    }
                });

            });
        }

        //ver ficha
        $('#action-view').on('click', function() {

            if (!selectedPatient) {

                return;

            }


            const patientId = selectedPatient.id;


            actionsMenu.addClass('hidden');


            window.location.href =
                `/patients/${patientId}/medical-record`;

        });

        // ==========================================
        // ELIMINAR HORARIO
        // ==========================================

        function deleteSchedule(scheduleId) {

            // Tu función existente...

        }


function contactAppointment(patient) {
    document.getElementById('contact-patient-name').textContent =
        patient.name ?? 'No registrado';
    
    document.getElementById('contact-patient-rut').textContent =
        patient.rut ?? 'No registrado';

    document.getElementById('contact-patient-phone').textContent =
        patient.phone ?? 'No registrado';

    document.getElementById('contact-patient-email').textContent =
        patient.email ?? 'No registrado';

    const whatsapp = document.getElementById('contact-whatsapp');

    if (patient.phone) {
        const phone = patient.phone.replace(/\D/g, '');

        whatsapp.href = `https://wa.me/${phone}`;
        whatsapp.classList.remove('hidden');
    } else {
        whatsapp.href = '#';
        whatsapp.classList.add('hidden');
    }

    document.getElementById('contact-patient-modal')
        .classList.remove('hidden');
}

function closeContactModal() {
    document.getElementById('contact-patient-modal')
        .classList.add('hidden');
}


function copyPatientPhone() {

    const element = document.getElementById('contact-patient-phone');
    const button = document.getElementById('copy-phone-button');

    if (!element || !button) {
        return;
    }

    const phone = element.textContent.trim();

    if (!phone || phone === '—' || phone === 'No registrado') {
        return;
    }

    copyText(phone, button);
}


function copyPatientEmail() {

    const element = document.getElementById('contact-patient-email');
    const button = document.getElementById('copy-email-button');

    if (!element || !button) {
        return;
    }

    const email = element.textContent.trim();

    if (!email || email === '—' || email === 'No registrado') {
        return;
    }

    copyText(email, button);
}


function copyText(text, button) {

    const originalHTML = button.innerHTML;

    // Clipboard moderno
    if (navigator.clipboard && window.isSecureContext) {

        navigator.clipboard.writeText(text)
            .then(() => {

                showCopied(button);

            })
            .catch(() => {

                fallbackCopy(text, button, originalHTML);

            });

    } else {

        fallbackCopy(text, button, originalHTML);

    }
}


function fallbackCopy(text, button, originalHTML) {

    const textarea = document.createElement('textarea');

    textarea.value = text;

    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';

    document.body.appendChild(textarea);

    textarea.focus();
    textarea.select();

    try {

        const successful = document.execCommand('copy');

        if (successful) {

            showCopied(button);

        }

    } catch (error) {

        console.error('Error al copiar:', error);

    }

    document.body.removeChild(textarea);
}


function showCopied(button) {

    const originalHTML = button.innerHTML;

    button.innerHTML = `
        <i class="fa-solid fa-check mr-1"></i>
        <span>¡Copiado!</span>
    `;

    button.classList.remove(
        'bg-gray-100',
        'hover:bg-gray-200'
    );

    button.classList.add(
        'bg-green-100',
        'text-green-700'
    );

    setTimeout(() => {

        button.innerHTML = originalHTML;

        button.classList.remove(
            'bg-green-100',
            'text-green-700'
        );

        button.classList.add(
            'bg-gray-100',
            'hover:bg-gray-200'
        );

    }, 1500);
}




    </script>
@endpush
