@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Pacientes') }}
    </h2>
@endsection

@section('content')

<div class="py-12">

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-white shadow-sm sm:rounded-lg">

            <div class="p-6 text-gray-900">

                <!-- ========================================================= -->
                <!-- BOTÓN NUEVO -->
                <!-- ========================================================= -->

                <div class="flex justify-end mb-4">

                    <button
                        type="button"
                        id="new-patient-btn"
                        style="background-color: {{ themeColor(600) }}"
                        class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">

                        <i class="fa-solid fa-circle-plus mr-1"></i>

                        Nuevo

                    </button>

                </div>


                <!-- ========================================================= -->
                <!-- TABLA -->
                <!-- ========================================================= -->

                <div class="overflow-x-auto">

                    <table
                        id="patient-table"
                        class="min-w-full display responsive nowrap rounded-xl overflow-hidden">

                        <thead style="background-color: {{ themeColor(100) }}">

                            <tr>

                                <th>#</th>

                                <th>Nombre Completo</th>

                                <th>Rut</th>

                                <th>Teléfono</th>


                                <th>Previsión</th>

                                <th>Estado</th>

                                <th>Acciones</th>

                            </tr>

                        </thead>

                    </table>

                </div>


                <!-- ========================================================= -->
                <!-- MODAL CREAR / EDITAR PACIENTE -->
                <!-- ========================================================= -->

                <div
                    id="patient-modal"
                    class="hidden fixed inset-0 items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

                    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">

                        <!-- HEADER -->

                        <div
                            class="px-6 py-4 border-b rounded-t-lg"
                            style="background-color: {{ themeColor(100) }}">

                            <h2
                                id="patient-modal-title"
                                class="text-lg font-bold">

                                Nuevo Paciente

                            </h2>

                        </div>


                        <!-- BODY -->

                        <div class="p-6 space-y-4 overflow-y-auto flex-1">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                                <!-- NOMBRE -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre Completo *
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-name"
                                        name="name"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-name"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- RUT -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        RUT *
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-rut"
                                        name="rut"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-rut"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- FECHA NACIMIENTO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha Nacimiento
                                    </label>

                                    <input
                                        type="date"
                                        id="patient-birth-date"
                                        name="birth_date"
                                        max="{{ now()->format('Y-m-d') }}"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-birth-date"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- SEXO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Sexo
                                    </label>

                                    <select
                                        id="patient-gender"
                                        name="gender"
                                        class="w-full rounded-lg border-gray-300">

                                        <option value="Masculino">
                                            Masculino
                                        </option>

                                        <option value="Femenino">
                                            Femenino
                                        </option>

                                        <option value="Otro">
                                            Otro
                                        </option>

                                    </select>

                                </div>


                                <!-- TELÉFONO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Teléfono
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-phone"
                                        name="phone"
                                        maxlength="20"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-phone"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- EMAIL -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Correo Electrónico
                                    </label>

                                    <input
                                        type="email"
                                        id="patient-email"
                                        name="email"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-email"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- DIRECCIÓN -->

                                <div class="md:col-span-2">

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Dirección
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-address"
                                        name="address"
                                        maxlength="255"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-address"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- PREVISIÓN -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Previsión
                                    </label>

                                    <select
                                        id="patient-insurance"
                                        name="insurance_id"
                                        class="w-full rounded-lg border-gray-300">

                                        @foreach ($insurances as $insurance)

                                            <option value="{{ $insurance->id }}">
                                                {{ $insurance->name }}
                                            </option>

                                        @endforeach

                                    </select>

                                </div>


                                <!-- ESTADO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Estado
                                    </label>

                                    <select
                                        id="patient-status"
                                        name="status"
                                        class="w-full rounded-lg border-gray-300">

                                        <option value="1">
                                            Activo
                                        </option>

                                        <option value="0">
                                            Inactivo
                                        </option>

                                    </select>

                                </div>

                            </div>

                        </div>


                        <!-- FOOTER -->

                        <div class="px-6 py-4 border-t flex justify-end gap-2">

                            <button
                                type="button"
                                id="patient-modal-cancel"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                                Cancelar

                            </button>


                            <button
                                type="button"
                                id="patient-modal-save"
                                style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                                Guardar

                            </button>

                        </div>

                    </div>

                </div>


                <!-- ========================================================= -->
                <!-- MENÚ DE ACCIONES GLOBAL -->
                <!-- ========================================================= -->

                <div
                    id="patient-actions-menu"
                    class="hidden fixed w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-[9999]">

                    <!-- VER FICHA -->

                    <button
                        type="button"
                        id="action-view"
                        class="w-full text-left px-4 py-2 hover:bg-gray-100">

                        <i class="fa-regular fa-id-badge mr-2"></i>

                        Ver ficha

                    </button>


                    <!-- EDITAR -->

                    <button
                        type="button"
                        id="action-edit"
                        class="w-full text-left px-4 py-2 hover:bg-gray-100">

                        <i class="fa-solid fa-pen-to-square mr-2"></i>

                        Editar

                    </button>


                    <!-- AGENDAR -->

                    <button
                        type="button"
                        id="action-appointment"
                        class="w-full text-left px-4 py-2 hover:bg-gray-100">

                        <i class="fa-solid fa-calendar-days mr-2"></i>

                        Agendar cita

                    </button>


                    <hr>


                    <!-- ELIMINAR -->

                    <button
                        type="button"
                        id="action-delete"
                        class="w-full text-left px-4 py-2 text-red-600 hover:bg-red-50">

                        <i class="fa-solid fa-trash mr-2"></i>

                        Eliminar

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script>

$(function () {

    /*
    |--------------------------------------------------------------------------
    | VARIABLES
    |--------------------------------------------------------------------------
    */

    let tableActive;

    let editingPatientId = null;

    let selectedPatient = null;

    let isEdit = false;

    const modal = $('#patient-modal');

    const actionsMenu = $('#patient-actions-menu');

    const csrfToken = $('meta[name="csrf-token"]').attr('content');


    /*
    |--------------------------------------------------------------------------
    | DATATABLE
    |--------------------------------------------------------------------------
    */

    tableActive = $('#patient-table').DataTable({

        processing: true,

        serverSide: true,

        ajax: '/patient-data',

        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },

        responsive: false,

        autoWidth: false,

        columns: [

            /*
            |--------------------------------------------------------------------------
            | #
            |--------------------------------------------------------------------------
            */

            {
                data: null,

                render: function (data, type, row, meta) {

                    return meta.row +
                        meta.settings._iDisplayStart +
                        1;

                },

                orderable: false,

                searchable: false
            },


            /*
            |--------------------------------------------------------------------------
            | NOMBRE
            |--------------------------------------------------------------------------
            */

            {
                data: 'name'
            },


            /*
            |--------------------------------------------------------------------------
            | RUT
            |--------------------------------------------------------------------------
            */

            {
                data: 'rut'
            },


            /*
            |--------------------------------------------------------------------------
            | TELÉFONO
            |--------------------------------------------------------------------------
            */

            {
                data: 'phone'
            },


            /*
            |--------------------------------------------------------------------------
            | PREVISIÓN
            |--------------------------------------------------------------------------
            */

            {
                data: 'insurance'
            },


            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            */

            {
                data: 'status',

                render: function (data, type, row) {

                    const active = Number(data) === 1;

                    return `

                        <button
                            type="button"
                            class="toggle-status px-2 py-1 rounded text-xs text-white
                            ${active ? 'bg-green-500' : 'bg-red-500'}"
                            data-id="${row.id}"
                            data-status="${data}">

                            ${active ? 'Activo' : 'Inactivo'}

                        </button>

                    `;

                }

            },


            /*
            |--------------------------------------------------------------------------
            | ACCIONES
            |--------------------------------------------------------------------------
            */

            {

                data: null,

                orderable: false,

                searchable: false,

                render: function (data, type, row) {

                    return `

                        <button
                            type="button"
                            class="patient-actions-btn px-2 py-1 rounded hover:bg-gray-100"
                            data-id="${row.id}">

                            <i class="fa-solid fa-ellipsis-vertical"></i>

                        </button>

                    `;

                }

            }

        ]

    });


    /*
    |--------------------------------------------------------------------------
    | ABRIR MENÚ DE ACCIONES
    |--------------------------------------------------------------------------
    */

   $('#patient-table').on(
    'click',
    '.patient-actions-btn',
    function (e) {

        e.preventDefault();
        e.stopPropagation();

        // Si ya está abierto, cerrarlo y terminar
        if (!actionsMenu.hasClass('hidden')) {
            actionsMenu.addClass('hidden');
            return;
        }

        // Obtener paciente
        selectedPatient = tableActive
            .row($(this).closest('tr'))
            .data();

        if (!selectedPatient) {
            return;
        }

        // Posición del botón
        const button = this;
        const rect = button.getBoundingClientRect();

        // Mostrar menú
        actionsMenu
            .removeClass('hidden')
            .css({
                top: `${rect.bottom + 5}px`,
                left: `${rect.right - 208}px`
            });
    }
);


    /*
    |--------------------------------------------------------------------------
    | CERRAR MENÚ AL HACER CLICK AFUERA
    |--------------------------------------------------------------------------
    */

    $(document).on('click', function (e) {

        if (

            !$(e.target).closest('#patient-actions-menu').length &&

            !$(e.target).closest('.patient-actions-btn').length

        ) {

            actionsMenu.addClass('hidden');

        }

    });


    /*
    |--------------------------------------------------------------------------
    | ESC
    |--------------------------------------------------------------------------
    */

    $(document).on('keydown', function (e) {

        if (e.key === 'Escape') {

            actionsMenu.addClass('hidden');

            if (!modal.hasClass('hidden')) {

                closeModal();

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | VER FICHA
    |--------------------------------------------------------------------------
    */

    $('#action-view').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        const patientId = selectedPatient.id;


        actionsMenu.addClass('hidden');


        window.location.href =
            `/patients/${patientId}/medical-record`;

    });


    /*
    |--------------------------------------------------------------------------
    | EDITAR
    |--------------------------------------------------------------------------
    */

    $('#action-edit').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        const patient = selectedPatient;


        actionsMenu.addClass('hidden');


        openEditModal(patient);

    });


    /*
    |--------------------------------------------------------------------------
    | AGENDAR CITA
    |--------------------------------------------------------------------------
    */

    $('#action-appointment').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        const patientId = selectedPatient.id;


        actionsMenu.addClass('hidden');


        console.log(
            'Agendar cita para paciente:',
            patientId
        );


        /*
        |--------------------------------------------------------------------------
        | CAMBIA ESTA RUTA POR LA TUYA
        |--------------------------------------------------------------------------
        */

        // window.location.href =
        //     `/patients/${patientId}/appointments/create`;

    });


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR
    |--------------------------------------------------------------------------
    */

    $('#action-delete').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        const patient = selectedPatient;


        actionsMenu.addClass('hidden');


        deletePatient(patient);

    });


    /*
    |--------------------------------------------------------------------------
    | NUEVO PACIENTE
    |--------------------------------------------------------------------------
    */

    $('#new-patient-btn').on('click', function () {

        openCreateModal();

    });


    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL CREAR
    |--------------------------------------------------------------------------
    */

    function openCreateModal() {

        isEdit = false;

        editingPatientId = null;


        $('#patient-modal-title')
            .text('Nuevo Paciente');


        clearForm();

        clearErrors();

        showModal();

    }


    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL EDITAR
    |--------------------------------------------------------------------------
    */

    function openEditModal(data) {

        isEdit = true;

        editingPatientId = data.id;


        $('#patient-modal-title')
            .text('Editar Paciente');


        clearErrors();


        $('#patient-name')
            .val(data.name ?? '');


        $('#patient-rut')
            .val(data.rut ?? '');


        $('#patient-birth-date')
            .val(data.birth_date ?? '');


        $('#patient-gender')
            .val(data.gender ?? 'Masculino');


        $('#patient-phone')
            .val(data.phone ?? '');


        $('#patient-email')
            .val(data.email ?? '');


        $('#patient-address')
            .val(data.address ?? '');


        $('#patient-insurance')
            .val(data.insurance_id ?? '');


        $('#patient-status')
            .val(String(data.status ?? '1'));


        showModal();

    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR MODAL
    |--------------------------------------------------------------------------
    */

    function showModal() {

        modal
            .removeClass('hidden')
            .addClass('flex');


        $('body')
            .addClass('overflow-hidden');

    }


    /*
    |--------------------------------------------------------------------------
    | CERRAR MODAL
    |--------------------------------------------------------------------------
    */

    function closeModal() {

        modal
            .removeClass('flex')
            .addClass('hidden');


        $('body')
            .removeClass('overflow-hidden');


        clearErrors();

    }


    /*
    |--------------------------------------------------------------------------
    | CANCELAR
    |--------------------------------------------------------------------------
    */

    $('#patient-modal-cancel').on('click', function () {

        closeModal();

    });


    /*
    |--------------------------------------------------------------------------
    | CLICK FUERA DEL MODAL
    |--------------------------------------------------------------------------
    */

    $('#patient-modal').on('click', function (e) {

        if (e.target === this) {

            closeModal();

        }

    });


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR FORMULARIO
    |--------------------------------------------------------------------------
    */

    function clearForm() {

        $('#patient-name').val('');

        $('#patient-rut').val('');

        $('#patient-birth-date').val('');

        $('#patient-gender').val('Masculino');

        $('#patient-phone').val('');

        $('#patient-email').val('');

        $('#patient-address').val('');

        $('#patient-status').val('1');

        $('#patient-insurance')
            .val('{{ $insurances->first()->id ?? '' }}');

    }


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR ERRORES
    |--------------------------------------------------------------------------
    */

    function clearErrors() {

        $('[id^="error-"]')
            .text('')
            .addClass('hidden');

    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR ERRORES
    |--------------------------------------------------------------------------
    */

    function showErrors(errors) {

        clearErrors();


        $.each(errors, function (field, messages) {

            const errorId =
                '#error-' + field.replaceAll('_', '-');


            if ($(errorId).length) {

                $(errorId)
                    .text(messages[0])
                    .removeClass('hidden');

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | GUARDAR
    |--------------------------------------------------------------------------
    */

    $('#patient-modal-save').on('click', function () {

        savePatient();

    });


    /*
    |--------------------------------------------------------------------------
    | GUARDAR PACIENTE
    |--------------------------------------------------------------------------
    */

    function savePatient() {

        clearErrors();


        const patientData = {

            name: $('#patient-name').val(),

            rut: $('#patient-rut').val(),

            birth_date: $('#patient-birth-date').val(),

            gender: $('#patient-gender').val(),

            phone: $('#patient-phone').val(),

            email: $('#patient-email').val(),

            address: $('#patient-address').val(),

            insurance_id: $('#patient-insurance').val(),

            status: $('#patient-status').val()

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


        fetch(url, {

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

                    showErrors(
                        data.errors || {}
                    );


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

        .then(() => {

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


            closeModal();


            tableActive
                .ajax
                .reload(null, false);

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

    }


    /*
    |--------------------------------------------------------------------------
    | CAMBIAR ESTADO
    |--------------------------------------------------------------------------
    */

    $('#patient-table').on(
        'click',
        '.toggle-status',
        function () {

            const button = $(this);


            const id =
                button.data('id');


            const currentStatus =
                button.data('status');


            const newStatus =
                currentStatus == 1
                    ? 0
                    : 1;


            fetch(
                `/patient/${id}/toggle-status`,
                {

                    method: 'PATCH',

                    headers: {

                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            csrfToken

                    },

                    body: JSON.stringify({

                        status:
                            newStatus

                    })

                }
            )

            .then(async response => {

                const data =
                    await response.json();


                if (!response.ok) {

                    throw new Error(
                        data.message ||
                        'No se pudo actualizar el estado'
                    );

                }


                return data;

            })

            .then(() => {

                Swal.fire({

                    toast: true,

                    position: 'top-end',

                    icon: 'success',

                    title: 'Estado actualizado',

                    showConfirmButton: false,

                    timer: 1500

                });


                tableActive
                    .ajax
                    .reload(null, false);

            })

            .catch(error => {

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: error.message

                });

            });

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR PACIENTE
    |--------------------------------------------------------------------------
    */

    function deletePatient(patient) {

        Swal.fire({

            title: '¿Eliminar paciente?',

            text:
                'Esto lo eliminará del sistema permanentemente.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText:
                'Sí, eliminar',

            confirmButtonColor:
                '#dc2626',

            cancelButtonText:
                'Cancelar'

        })

        .then(result => {

            if (!result.isConfirmed) {

                return;

            }


            fetch(
                `/patient/${patient.id}`,
                {

                    method: 'DELETE',

                    headers: {

                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            csrfToken

                    }

                }
            )

            .then(async response => {

                const data =
                    await response.json();


                if (!response.ok) {

                    throw new Error(
                        data.message ||
                        'No se pudo eliminar el paciente'
                    );

                }


                return data;

            })

            .then(() => {

                Swal.fire({

                    toast: true,

                    position: 'top-end',

                    icon: 'success',

                    title:
                        'Paciente eliminado',

                    showConfirmButton:
                        false,

                    timer: 1500

                });


                tableActive
                    .ajax
                    .reload(null, false);

            })

            .catch(error => {

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: error.message

                });

            });

        });

    }


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR ERRORES AL ESCRIBIR
    |--------------------------------------------------------------------------
    */

    $('#patient-name').on('input', function () {

        $('#error-name')
            .text('')
            .addClass('hidden');

    });


    $('#patient-rut').on('input', function () {

        $('#error-rut')
            .text('')
            .addClass('hidden');

    });


    $('#patient-birth-date').on('input', function () {

        $('#error-birth-date')
            .text('')
            .addClass('hidden');

    });


    $('#patient-phone').on('input', function () {

        $('#error-phone')
            .text('')
            .addClass('hidden');

    });


    $('#patient-email').on('input', function () {

        $('#error-email')
            .text('')
            .addClass('hidden');

    });


    $('#patient-address').on('input', function () {

        $('#error-address')
            .text('')
            .addClass('hidden');

    });


    /*
    |--------------------------------------------------------------------------
    | REPOSICIONAR MENÚ AL HACER SCROLL
    |--------------------------------------------------------------------------
    */

    $(window).on('scroll', function () {

        actionsMenu.addClass('hidden');

    });


});

</script>

@endpush