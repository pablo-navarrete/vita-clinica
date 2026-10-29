@extends('layouts.menu-customer')


@section('header')
    <div class="flex justify-between items-start">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Ficha Clínica del Paciente') }}
            </h2>

            <p class="text-gray-500 text-sm mt-1">
                Historial médico y antecedentes clínicos
            </p>
        </div>

        <button type="button" onclick="history.back()" class="px-5 py-2 rounded-lg bg-gray-200 hover:bg-gray-300">
            Volver
        </button>
    </div>
@endsection





@section('content')
    @include('customer.patient.record-workspace.shell')

    {{-- modal editar / crear alergias --}}

    <div id="modalAllergies" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">

        
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
            {{-- Header --}}
            <div class="px-6 py-4 border-b rounded-t-lg " style="background-color: {{ themeColor(100) }}">
                <h2 class="text-lg font-bold">
                    Administrar alergias
                </h2>
            </div>
            <form action="{{ route('allergies.store', app('customer')->domain) }}" method="POST">

                @csrf

                <input type="hidden" name="medical_record_id" value="{{ $medicalRecord->id }}">
                <input type="hidden" name="customer_id" value="{{ app('customer')->id }}">
                <div class="p-6">
                    <h3 class="font-semibold mb-4">
                        Alergias registradas
                    </h3>
                    <div class="space-y-3 mb-6">
                        <div id="allergyList" class="flex flex-wrap gap-2 mb-6">
                            @foreach ($allergies as $allergy)
                                <div
                                    class="allergy-item inline-flex items-center gap-2 bg-red-100 text-red-700 px-3 py-2 rounded-full">

                                    <div class="relative inline-block group">
                                        <span
                                            class="inline-flex px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm cursor-pointer">
                                            {{ $allergy->allergen }}
                                        </span>

                                        <div
                                            class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2
                                                hidden group-hover:block
                                                bg-red-600 text-white text-xs rounded-lg px-3 py-2
                                                shadow-lg whitespace-nowrap z-50">
                                            {{ $allergy->reaction }}

                                            <div
                                                class="absolute left-1/2 top-full -translate-x-1/2
                                                        border-4 border-transparent border-t-red-600">
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" name="existing_allergies[]" value="{{ $allergy->id }}">

                                    <button type="button" class="remove-allergy">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                </div>
                            @endforeach
                        </div>

                    </div>

                    <hr class="my-5">

                    <h3 class="font-semibold mb-4">
                        Agregar nueva alergia
                    </h3>

                    <div class="space-y-4">

                        <div>

                            <label class="block text-sm font-medium mb-1">

                                Alérgeno

                            </label>

                            <input type="text" name="allergen" class="w-full rounded-lg border-gray-300"
                                placeholder="Ej. Penicilina" id="allergen">

                        </div>

                        <div>

                            <label class="block text-sm font-medium mb-1">

                                Reacción

                            </label>

                            <textarea name="reaction" rows="3" class="w-full rounded-lg border-gray-300" id="reaction"
                                placeholder="Ej. Erupción cutánea, dificultad respiratoria..."></textarea>

                        </div>

                        <div class="flex justify-end">

                            <button type="button"
                                class="bg-red-600 text-white px-4 py-2 rounded
                                disabled:bg-red-300
                                disabled:text-white
                                disabled:opacity-70"
                                id="btnAddAllergy" disabled>

                                <i class="fa-solid fa-plus mr-2"></i>

                                Agregar

                            </button>

                        </div>

                    </div>

                </div>

                <div class="flex justify-end gap-3 border-t px-6 py-4">

                    <button type="button" onclick="closeAllergyModal()"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                        Cancelar

                    </button>

                    <button type="submit" style="background-color: {{ themeColor(600) }}"
                        class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>


    {{-- modal editar / crear enfermedades cronicas --}}

    <div id="modalChronicDiseases" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
            {{-- Header --}}
            <div class="px-6 py-4 border-b rounded-t-lg " style="background-color: {{ themeColor(100) }}">
                <h2 class="text-lg font-bold">
                    Administrar Enfermedades cronicas
                </h2>
            </div>
            <form action="{{ route('chronic_diseases.store', app('customer')->domain) }}" method="POST">

                @csrf

                <input type="hidden" name="medical_record_id" value="{{ $medicalRecord->id }}">
                <input type="hidden" name="customer_id" value="{{ app('customer')->id }}">
                <div class="p-6">
                    <h3 class="font-semibold mb-4">
                        Enfermedades cronicas registradas
                    </h3>
                    <div class="space-y-3 mb-6">
                        <div id="chronicDiseasesList" class="flex flex-wrap gap-2 mb-6">
                            @foreach ($chronicDiseases as $chronicDisease)
                                <div
                                    class="chronic-diseases-item inline-flex items-center gap-2 bg-yellow-100 text-yellow-700 px-3 py-2 rounded-full">

                                    <div class="relative inline-block group">
                                        <span
                                            class="inline-flex px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-sm cursor-pointer">
                                            {{ $chronicDisease->name }}
                                        </span>


                                    </div>

                                    <input type="hidden" name="existing_chronic_diseases[]"
                                        value="{{ $chronicDisease->id }}">

                                    <button type="button" class="remove-chronic-diseases">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                </div>
                            @endforeach
                        </div>

                    </div>

                    <hr class="my-5">

                    <h3 class="font-semibold mb-4">
                        Agregar nueva Enfermedad cronica
                    </h3>

                    <div class="space-y-4">

                        <div>

                            <label class="block text-sm font-medium mb-1">

                                Enfermedad cronica

                            </label>

                            <input type="text" name="name" class="w-full rounded-lg border-gray-300"
                                placeholder="Ej. Hipertensión arterial" id="chronicDiseasesName">

                        </div>



                        <div class="flex justify-end">

                            <button type="button"
                                class="bg-red-600 text-white px-4 py-2 rounded
                                disabled:bg-red-300
                                disabled:text-white
                                disabled:opacity-70"
                                id="btnAddChronicDiseases" disabled>


                                <i class="fa-solid fa-plus mr-2"></i>

                                Agregar

                            </button>

                        </div>

                    </div>

                </div>

                <div class="flex justify-end gap-3 border-t px-6 py-4">

                    <button type="button" onclick="closeChronicDiseasesModal()"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                        Cancelar

                    </button>

                    <button type="submit" style="background-color: {{ themeColor(600) }}"
                        class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>

    {{-- modal editar / crear medicamentos habituales --}}

    <div id="modalRegularMedications" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
            {{-- Header --}}
            <div class="px-6 py-4 border-b rounded-t-lg " style="background-color: {{ themeColor(100) }}">
                <h2 class="text-lg font-bold">
                    Administrar Medicamentos Habituales
                </h2>
            </div>
            <form action="{{ route('regular_medications.store', app('customer')->domain) }}" method="POST">

                @csrf

                <input type="hidden" name="medical_record_id" value="{{ $medicalRecord->id }}">
                <input type="hidden" name="customer_id" value="{{ app('customer')->id }}">
                <div class="p-6">
                    <h3 class="font-semibold mb-4">
                        Medicamentos Habituales registrados
                    </h3>
                    <div class="space-y-3 mb-6">
                        <div id="regularMedicationsList" class="flex flex-wrap gap-2 mb-6">
                            @foreach ($regularMedications as $regular)
                                <div
                                    class="regular-medications-item inline-flex items-center gap-2 bg-green-100 text-green-700 px-3 py-2 rounded-full">

                                    <div class="relative inline-block group">
                                        <span
                                            class="inline-flex px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm cursor-pointer">
                                            {{ $regular->name }}
                                        </span>


                                    </div>

                                    <input type="hidden" name="existing_regular_medications[]"
                                        value="{{ $regular->id }}">

                                    <button type="button" class="remove-regular-medications">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                </div>
                            @endforeach
                        </div>

                    </div>

                    <hr class="my-5">

                    <h3 class="font-semibold mb-4">
                        Agregar nuevo Medicamento Habitual
                    </h3>

                    <div class="space-y-4">

                        <div>

                            <label class="block text-sm font-medium mb-1">

                                Medicamento Habitual

                            </label>

                            <input type="text" name="name" class="w-full rounded-lg border-gray-300"
                                placeholder="Ej. metformina 50 mg" id="regularMedicationName">

                        </div>



                        <div class="flex justify-end">

                            <button type="button"
                                class="bg-red-600 text-white px-4 py-2 rounded
                                disabled:bg-red-300
                                disabled:text-white
                                disabled:opacity-70"
                                id="btnAddRegularMedications" disabled>

                                <i class="fa-solid fa-plus mr-2"></i>

                                Agregar

                            </button>

                        </div>

                    </div>

                </div>

                <div class="flex justify-end gap-3 border-t px-6 py-4">

                    <button type="button" onclick="closeRegularMedicationsModal()"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                        Cancelar

                    </button>

                    <button type="submit" style="background-color: {{ themeColor(600) }}"
                        class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>

    {{-- modal editar / crear cirugias previas --}}

    <div id="modalPreviousSurgeries" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
            {{-- Header --}}
            <div class="px-6 py-4 border-b rounded-t-lg " style="background-color: {{ themeColor(100) }}">
                <h2 class="text-lg font-bold">
                    Administrar Cirugías Previas
                </h2>
            </div>
            <form action="{{ route('previous_surgeries.store', app('customer')->domain) }}" method="POST">

                @csrf

                <input type="hidden" name="medical_record_id" value="{{ $medicalRecord->id }}">
                <input type="hidden" name="customer_id" value="{{ app('customer')->id }}">
                <div class="p-6">
                    <h3 class="font-semibold mb-4">
                        Cirugías Previas registradas
                    </h3>
                    <div class="space-y-3 mb-6">
                        <div id="previousSurgeriesList" class="flex flex-wrap gap-2 mb-6">
                            @foreach ($previousSurgeries as $regular)
                                <div
                                    class="previous-surgeries-item inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-3 py-2 rounded-full">

                                    <div class="relative inline-block group">
                                        <span
                                            class="inline-flex px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm cursor-pointer">
                                            {{ $regular->name }}
                                        </span>


                                    </div>

                                    <input type="hidden" name="existing_previous_surgeries[]"
                                        value="{{ $regular->id }}">

                                    <button type="button" class="remove-previous-surgeries">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                </div>
                            @endforeach
                        </div>

                    </div>

                    <hr class="my-5">

                    <h3 class="font-semibold mb-4">
                        Agregar nueva Cirugía Previa
                    </h3>

                    <div class="space-y-4">

                        <div>

                            <label class="block text-sm font-medium mb-1">

                                Cirugía Previa

                            </label>

                            <input type="text" name="name" class="w-full rounded-lg border-gray-300"
                                placeholder="Ej. Apendicectomía (2018)" id="previousSurgeriesName">

                        </div>



                        <div class="flex justify-end">

                            <button type="button"
                                class="bg-red-600 text-white px-4 py-2 rounded
                                disabled:bg-red-300
                                disabled:text-white
                                disabled:opacity-70"
                                id="btnAddPreviousSurgeries" disabled>

                                <i class="fa-solid fa-plus mr-2"></i>

                                Agregar

                            </button>

                        </div>

                    </div>

                </div>

                <div class="flex justify-end gap-3 border-t px-6 py-4">

                    <button type="button" onclick="closePreviousSurgeriesModal()"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                        Cancelar

                    </button>

                    <button type="submit" style="background-color: {{ themeColor(600) }}"
                        class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>


    {{-- Modal para iniciar una atención --}}
    <div id="modalEncounters" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-hidden bg-black/50 p-4">
        <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl">
            <div class="rounded-t-lg border-b px-6 py-4" style="background-color: {{ themeColor(100) }}">
                <h2 class="text-lg font-bold">Nueva atención médica</h2>
                <p class="mt-1 text-sm text-gray-600">Paciente: {{ $patient->name }} · RUT: {{ $patient->rut }}</p>
            </div>

            <form action="{{ route('patients.encounters.store', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}" method="POST" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <input type="hidden" id="encounter-direct-session" name="telemedicine_session_id" value="{{ old('telemedicine_session_id') }}">
                <div class="space-y-4 overflow-y-auto p-6">
                    @error('encounter')
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror

                    @if ($ownActiveEncounters->isNotEmpty())
                        <div id="encounter-open-warning" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            <p>Ya tienes {{ $ownActiveEncounters->count() }} atención(es) abierta(s) para este paciente. Si eliges una cita nueva, puedes iniciar otra atención.</p>
                            <label class="mt-3 flex items-start gap-2">
                                <input id="confirm-open-encounter" type="checkbox" name="confirm_open_encounter" value="1" @checked(old('confirm_open_encounter')) class="mt-1 rounded border-gray-300">
                                <span>He revisado las atenciones abiertas y deseo iniciar otra consulta sin cita.</span>
                            </label>
                            @error('confirm_open_encounter')<p class="mt-2 text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div>
                        <label for="encounter-appointment" class="mb-1 block text-sm font-medium text-gray-700">Cita asociada (opcional)</label>
                        <select id="encounter-appointment" name="appointment_id" class="w-full rounded-lg border-gray-300">
                            <option value="">Sin cita asociada</option>
                            @foreach ($patientAppointments as $appointment)
                                @php
                                    $telemedicineReady = $appointment->telemedicineSession
                                        && $appointment->telemedicineSession->status === 'in_progress'
                                        && $appointment->telemedicineSession->patient_consent_at
                                        && !$appointment->telemedicineSession->ended_at;
                                @endphp
                                <option value="{{ $appointment->id }}" data-specialty-id="{{ $appointment->specialty_id }}" data-modality="{{ $appointment->modality ?? 'in_person' }}" data-telemedicine-ready="{{ $telemedicineReady ? '1' : '0' }}" @selected(old('appointment_id', $preselectedAppointmentId) == $appointment->id)>
                                    {{ \Illuminate\Support\Carbon::parse($appointment->start_at)->format('d/m/Y H:i') }} · {{ $appointment->professional?->name ?? 'Sin profesional' }} · {{ $appointment->specialty?->name ?? 'Sin especialidad' }}
                                </option>
                            @endforeach
                        </select>
                        @error('appointment_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="encounter-modality" class="mb-1 block text-sm font-medium text-gray-700">Modalidad de atención</label>
                        <select id="encounter-modality" name="modality" required class="w-full rounded-lg border-gray-300">
                            <option value="in_person" @selected(old('modality', $preselectedEncounterModality) === 'in_person')>Presencial</option>
                            <option value="telemedicine" @selected(old('modality', $preselectedEncounterModality) === 'telemedicine')>Telemedicina</option>
                        </select>
                        <p id="encounter-telemedicine-note" class="mt-1 text-xs text-gray-500">Al iniciar una atenci?n por telemedicina, su sala de video se crea dentro de la atenci?n. El paciente acepta el consentimiento al ingresar.</p>
                        @error('modality')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="encounter-specialty" class="mb-1 block text-sm font-medium text-gray-700">Especialidad</label>
                        <select id="encounter-specialty" name="specialty_id" class="w-full rounded-lg border-gray-300">
                            <option value="">Selecciona una especialidad</option>
                            @foreach ($specialties as $specialty)
                                <option value="{{ $specialty->id }}" @selected(old('specialty_id', $preselectedSpecialtyId) == $specialty->id)>{{ $specialty->name }}</option>
                            @endforeach
                        </select>
                        @error('specialty_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="encounter-chief-complaint" class="mb-1 block text-sm font-medium text-gray-700">Motivo de atención</label>
                        <textarea id="encounter-chief-complaint" name="chief_complaint" rows="4" required maxlength="5000"
                            class="w-full rounded-lg border-gray-300" placeholder="Describe el motivo de la atención">{{ old('chief_complaint') }}</textarea>
                        @error('chief_complaint')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t px-6 py-4">
                    <button type="button" onclick="closeEncountersModal()" class="rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700">Cancelar</button>
                    <button type="submit" style="background-color: {{ themeColor(600) }}" class="rounded px-4 py-2 font-semibold text-white transition hover:brightness-90">
                        Iniciar atención
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    <style>
        #encounter-history .select2-container { width: 100% !important; }
        #encounter-history .select2-container--default .select2-selection--single {
            height: 42px; border: 1px solid #d1d5db; border-radius: 0.5rem;
            display: flex; align-items: center;
        }
        #encounter-history .select2-container--default .select2-selection__rendered {
            padding-left: 0.75rem; color: #111827;
        }
        #encounter-history .select2-container--default .select2-selection__arrow { height: 40px; }
        #modalEncounters .select2-container { width: 100% !important; }
        #modalEncounters .select2-container--default .select2-selection--single {
            height: 42px; border: 1px solid #d1d5db; border-radius: 0.5rem;
            display: flex; align-items: center;
        }
        #modalEncounters .select2-container--default .select2-selection__rendered {
            padding-left: 0.75rem; color: #111827;
        }
        #modalEncounters .select2-container--default .select2-selection__arrow { height: 40px; }
        #modalEncounters .select2-dropdown { z-index: 60; }
    </style>
@endpush
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script>
        //ALERGIAS
        let allergyListOriginal = '';

        function openAllergyModal() {

            allergyListOriginal = document.getElementById('allergyList').innerHTML;

            document.getElementById('modalAllergies').classList.remove('hidden');

        }

        function closeAllergyModal() {

            document.getElementById('modalAllergies').classList.add('hidden');

            // Limpiar campos
            allergysInput.value = '';
            btnAddAllergy.disabled = true;
            document.getElementById('reaction').value = '';

            // Restaurar listado original
            document.getElementById('allergyList').innerHTML = allergyListOriginal;

        }


        const allergyList = document.getElementById('allergyList');
        const allergysInput = document.getElementById('allergen');
        const btnAddAllergy = document.getElementById('btnAddAllergy');

        allergysInput.addEventListener('input', function() {
            btnAddAllergy.disabled = allergysInput.value.trim() === '';
        });

        document.getElementById('btnAddAllergy').addEventListener('click', function() {

            const allergen = document.getElementById('allergen').value.trim();
            const reaction = document.getElementById('reaction').value.trim();

            if (allergen === '') {
                window.showClinicalRecordToast('Ingrese un alérgeno');
                return;
            }

            const div = document.createElement('div');

            div.className =
                'allergy-item new-allergy inline-flex items-center gap-2 bg-red-100 text-red-700 px-3 py-2 rounded-full';

            div.innerHTML = `
            <div class="relative inline-block group">
                <span class="inline-flex px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm cursor-pointer">
                    ${allergen}
                </span>

                <div
                    class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2
                        hidden group-hover:block
                        bg-red-600 text-white text-xs rounded-lg px-3 py-2
                        shadow-lg whitespace-nowrap z-50">
                    ${reaction}

                    <div class="absolute left-1/2 top-full -translate-x-1/2
                                border-4 border-transparent border-t-red-600">
                    </div>
                </div>
            </div>

            <input type="hidden" name="new_allergens[]" value="${allergen}">
            <input type="hidden" name="new_reactions[]" value="${reaction}">

            <button type="button" class="remove-allergy">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

            allergyList.appendChild(div);

            allergysInput.value = '';
            btnAddAllergy.disabled = true;
            document.getElementById('reaction').value = '';

        });


        document.addEventListener('click', function(e) {

            const btn = e.target.closest('.remove-allergy');

            if (!btn) return;

            btn.closest('.allergy-item').remove();

        });

        //ENFERMEDADES CRONICAS
        let chronicDiseasesListOriginal = '';

        function openChronicDiseasesModal() {

            chronicDiseasesListOriginal = document.getElementById('chronicDiseasesList').innerHTML;

            document.getElementById('modalChronicDiseases').classList.remove('hidden');

        }

        function closeChronicDiseasesModal() {

            document.getElementById('modalChronicDiseases').classList.add('hidden');

            // Limpiar campos
            chronicDiseasesInput.value = '';
            btnAddChronicDiseases.disabled = true;

            // Restaurar listado original
            document.getElementById('chronicDiseasesList').innerHTML = chronicDiseasesListOriginal;

        }


        const chronicDiseasesList = document.getElementById('chronicDiseasesList');
        const chronicDiseasesInput = document.getElementById('chronicDiseasesName');
        const btnAddChronicDiseases = document.getElementById('btnAddChronicDiseases');

        chronicDiseasesInput.addEventListener('input', function() {
            btnAddChronicDiseases.disabled = chronicDiseasesInput.value.trim() === '';
        });

        document.getElementById('btnAddChronicDiseases').addEventListener('click', function() {

            const name = document.getElementById('chronicDiseasesName').value.trim();

            if (name === '') {
                window.showClinicalRecordToast('Ingrese una enfermedad cronica');
                return;
            }

            const div = document.createElement('div');

            div.className =
                'chronic-diseases-item new-chronic-diseases inline-flex items-center gap-2 bg-yellow-100 text-yellow-700 px-3 py-2 rounded-full';

            div.innerHTML = `
            <div class="relative inline-block group">
                <span class="inline-flex px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-sm cursor-pointer">
                    ${name}
                </span>
            </div>

            <input type="hidden" name="new_chronic_diseases[]" value="${name}">

            <button type="button" class="remove-chronic-diseases">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

            chronicDiseasesList.appendChild(div);

            chronicDiseasesInput.value = '';
            btnAddChronicDiseases.disabled = true;

        });


        document.addEventListener('click', function(e) {

            const btn = e.target.closest('.remove-chronic-diseases');

            if (!btn) return;

            btn.closest('.chronic-diseases-item').remove();

        });


        //medicamentos habituales
        let regularMedicationsListOriginal = '';


        function openRegularMedicationsModal() {

            regularMedicationsListOriginal = document.getElementById('regularMedicationsList').innerHTML;

            document.getElementById('modalRegularMedications').classList.remove('hidden');

        }

        function closeRegularMedicationsModal() {

            document.getElementById('modalRegularMedications').classList.add('hidden');

            // Limpiar campos
            regularMedicationInput.value = '';
            btnAddRegularMedications.disabled = true;

            // Restaurar listado original
            document.getElementById('regularMedicationsList').innerHTML = regularMedicationsListOriginal;

        }


        const regularMedicationsList = document.getElementById('regularMedicationsList');
        const regularMedicationInput = document.getElementById('regularMedicationName');
        const btnAddRegularMedications = document.getElementById('btnAddRegularMedications');


        regularMedicationInput.addEventListener('input', function() {
            btnAddRegularMedications.disabled = regularMedicationInput.value.trim() === '';
        });

        document.getElementById('btnAddRegularMedications').addEventListener('click', function() {

            const name = document.getElementById('regularMedicationName').value.trim();

            if (name === '') {
                window.showClinicalRecordToast('Ingrese un medicamento habitual');
                return;
            }

            const div = document.createElement('div');

            div.className =
                'regular-medications-item new-regular-medications inline-flex items-center gap-2 bg-green-100 text-green-700 px-3 py-2 rounded-full';

            div.innerHTML = `
            <div class="relative inline-block group">
                <span class="inline-flex px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm cursor-pointer">
                    ${name}
                </span>
            </div>

            <input type="hidden" name="new_regular_medications[]" value="${name}">

            <button type="button" class="remove-regular-medications">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

            regularMedicationsList.appendChild(div);

            regularMedicationInput.value = '';
            btnAddRegularMedications.disabled = true;

        });


        document.addEventListener('click', function(e) {

            const btn = e.target.closest('.remove-regular-medications');

            if (!btn) return;

            btn.closest('.regular-medications-item').remove();

        });



        //virugias previas
        let previousSurgeriesListOriginal = '';


        function openPreviousSurgeriesModal() {

            previousSurgeriesListOriginal = document.getElementById('previousSurgeriesList').innerHTML;

            document.getElementById('modalPreviousSurgeries').classList.remove('hidden');

        }

        function closePreviousSurgeriesModal() {

            document.getElementById('modalPreviousSurgeries').classList.add('hidden');

            // Limpiar campos
            previousSurgeriesInput.value = '';
            btnAddPreviousSurgeries.disabled = true;

            // Restaurar listado original
            document.getElementById('previousSurgeriesList').innerHTML = previousSurgeriesListOriginal;

        }


        const previousSurgeriesList = document.getElementById('previousSurgeriesList');
        const previousSurgeriesInput = document.getElementById('previousSurgeriesName');
        const btnAddPreviousSurgeries = document.getElementById('btnAddPreviousSurgeries');


        previousSurgeriesInput.addEventListener('input', function() {
            btnAddPreviousSurgeries.disabled = previousSurgeriesInput.value.trim() === '';
        });

        document.getElementById('btnAddPreviousSurgeries').addEventListener('click', function() {

            const name = document.getElementById('previousSurgeriesName').value.trim();

            if (name === '') {
                window.showClinicalRecordToast('Ingrese una cirugía previa');
                return;
            }

            const div = document.createElement('div');

            div.className =
                'previous-surgeries-item new-previous-surgeries inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-3 py-2 rounded-full';

            div.innerHTML = `
            <div class="relative inline-block group">
                <span class="inline-flex px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm cursor-pointer">
                    ${name}
                </span>
            </div>

            <input type="hidden" name="new_previous_surgeries[]" value="${name}">

            <button type="button" class="remove-previous-surgeries">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

            previousSurgeriesList.appendChild(div);

            previousSurgeriesInput.value = '';
            btnAddPreviousSurgeries.disabled = true;

        });


        document.addEventListener('click', function(e) {

            const btn = e.target.closest('.remove-previous-surgeries');

            if (!btn) return;

            btn.closest('.previous-surgeries-item').remove();

        });

        //atencion medica 
        function openEncountersModal() {

            document.getElementById('modalEncounters').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');

        }

        function closeEncountersModal() {

            document.getElementById('modalEncounters').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');

        }

        const encounterAppointmentSelect = document.getElementById('encounter-appointment');
        const encounterSpecialtySelect = document.getElementById('encounter-specialty');
        const encounterModalitySelect = document.getElementById('encounter-modality');
        const telemedicineModalityOption = encounterModalitySelect?.querySelector('option[value="telemedicine"]');
        const telemedicineModalityNote = document.getElementById('encounter-telemedicine-note');
        let encounterAppointmentInitialized = false;
        function syncEncounterSelect(element) {
            if (element && window.jQuery?.fn.select2) jQuery(element).trigger('change.select2');
        }
        function updateEncounterModality(autoSelect = false) {
            if (!encounterAppointmentSelect || !encounterModalitySelect || !telemedicineModalityOption) return;
            const selectedAppointment = encounterAppointmentSelect.options[encounterAppointmentSelect.selectedIndex];
            const isAppointmentSelected = Boolean(encounterAppointmentSelect.value);
            const confirmation = document.getElementById('confirm-open-encounter');
            const warning = document.getElementById('encounter-open-warning');
            if (confirmation) {
                confirmation.required = !isAppointmentSelected;
                confirmation.disabled = isAppointmentSelected;
                warning?.classList.toggle('hidden', isAppointmentSelected);
            }
            const appointmentModality = isAppointmentSelected ? (selectedAppointment?.dataset.modality || 'in_person') : 'in_person';
            telemedicineModalityOption.disabled = false;
            if (autoSelect) encounterModalitySelect.value = appointmentModality;
            telemedicineModalityNote.textContent = 'Al iniciar una atención por telemedicina, su sala de video se crea dentro de la atención, con o sin cita. El paciente acepta el consentimiento al ingresar.';
            syncEncounterSelect(encounterModalitySelect);
        }
        if (encounterAppointmentSelect && encounterSpecialtySelect) {
            const onEncounterAppointmentChange = function () {
                document.getElementById('encounter-direct-session').value = '';
                document.getElementById('encounter-direct-session').dataset.ready = '0';
                const selectedOption = this.options[this.selectedIndex];
                const specialtyId = selectedOption?.dataset.specialtyId;
                if (specialtyId) {
                    encounterSpecialtySelect.value = specialtyId;
                    syncEncounterSelect(encounterSpecialtySelect);
                }
                encounterSpecialtySelect.required = !this.value;
                updateEncounterModality(encounterAppointmentInitialized);
            };
            if (window.jQuery) jQuery(encounterAppointmentSelect).on('change.encounter', onEncounterAppointmentChange);
            else encounterAppointmentSelect.addEventListener('change', onEncounterAppointmentChange);
            encounterSpecialtySelect.required = !encounterAppointmentSelect.value;
            if (encounterAppointmentSelect.value) {
                encounterAppointmentSelect.dispatchEvent(new Event('change'));
            } else {
                updateEncounterModality();
            }
            encounterAppointmentInitialized = true;
        }

        @if ($openEncounterModal || $errors->has('encounter') || $errors->has('chief_complaint') || $errors->has('appointment_id') || $errors->has('specialty_id') || $errors->has('modality') || $errors->has('confirm_open_encounter'))
            document.addEventListener('DOMContentLoaded', openEncountersModal);
        @endif
    </script>
    <script>
        (() => {
            const exportMenu = document.getElementById('medical-record-export-menu');
            if (!exportMenu) return;

            document.addEventListener('click', event => {
                if (!exportMenu.contains(event.target)) exportMenu.open = false;
                if (event.target.closest('#medical-record-export-menu a')) exportMenu.open = false;
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') exportMenu.open = false;
            });
        })();
    </script>
    <script>
        (() => {
            let historyRequest;
            let searchTimer;

            function initializeHistorySelects() {
                if (!window.jQuery || !jQuery.fn.select2) return;
                jQuery('#encounter-history select').select2({
                    width: '100%',
                    minimumResultsForSearch: 0,
                    allowClear: false,
                    language: {
                        noResults: () => 'No se encontraron opciones',
                        searching: () => 'Buscando...',
                    },
                });
            }

            function initializeEncounterSelects() {
                if (!window.jQuery || !jQuery.fn.select2) return;
                jQuery('#encounter-appointment, #encounter-modality, #encounter-specialty').select2({
                    width: '100%',
                    minimumResultsForSearch: 0,
                    dropdownParent: jQuery('#modalEncounters'),
                    language: {
                        noResults: () => 'No se encontraron opciones',
                        searching: () => 'Buscando...',
                    },
                });
            }

            initializeHistorySelects();
            initializeEncounterSelects();
            window.addEventListener('clinical-record-refreshed', initializeHistorySelects);

            async function updateEncounterHistory(url, updateAddress = true) {
                const currentHistory = document.getElementById('encounter-history');
                if (!currentHistory) return;

                const activeElement = document.activeElement;
                const restoreSearchFocus = activeElement?.matches('#encounter-history input[name="search"]');
                const selectionStart = restoreSearchFocus ? activeElement.selectionStart : null;
                const selectionEnd = restoreSearchFocus ? activeElement.selectionEnd : null;

                historyRequest?.abort();
                historyRequest = new AbortController();
                currentHistory.classList.add('opacity-50');
                currentHistory.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: historyRequest.signal,
                    });
                    if (!response.ok) throw new Error('No se pudo cargar el historial.');

                    const html = await response.text();
                    const parsedPage = new DOMParser().parseFromString(html, 'text/html');
                    const updatedHistory = parsedPage.getElementById('encounter-history');
                    if (!updatedHistory) throw new Error('No se encontró el historial actualizado.');

                    currentHistory.replaceWith(updatedHistory);
                    initializeHistorySelects();
                    if (restoreSearchFocus) {
                        const searchInput = updatedHistory.querySelector('input[name="search"]');
                        searchInput?.focus({ preventScroll: true });
                        if (searchInput && selectionStart !== null && selectionEnd !== null) {
                            searchInput.setSelectionRange(selectionStart, selectionEnd);
                        }
                    }
                    if (updateAddress) history.pushState({}, '', url);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error(error);
                        window.showClinicalRecordToast('No se pudo actualizar el historial de atenciones.');
                    }
                } finally {
                    document.getElementById('encounter-history')?.classList.remove('opacity-50');
                    document.getElementById('encounter-history')?.removeAttribute('aria-busy');
                }
            }

            document.addEventListener('submit', event => {
                const form = event.target.closest('#encounter-history form');
                if (!form || form.method.toLowerCase() !== 'get') return;
                event.preventDefault();
                const url = new URL(form.action);
                new FormData(form).forEach((value, key) => {
                    if (value.toString().trim()) url.searchParams.set(key, value);
                    else url.searchParams.delete(key);
                });
                updateEncounterHistory(url.toString());
            });

            if (window.jQuery) {
                jQuery(document).on('change', '#encounter-history select', function () {
                    this.form?.requestSubmit();
                });
            } else {
                document.addEventListener('change', event => {
                    if (event.target.matches('#encounter-history select')) event.target.form?.requestSubmit();
                });
            }

            document.addEventListener('input', event => {
                if (!event.target.matches('#encounter-history input[name="search"]')) return;
                clearTimeout(searchTimer);
                const searchInput = event.target;
                searchTimer = setTimeout(() => searchInput.form?.requestSubmit(), 350);
            });

            document.addEventListener('click', event => {
                const link = event.target.closest('#encounter-history a');
                if (!link || !link.href || link.origin !== location.origin) return;
                event.preventDefault();
                updateEncounterHistory(link.href);
            });

            window.addEventListener('popstate', () => updateEncounterHistory(location.href, false));
        })();
    </script>
@endpush
