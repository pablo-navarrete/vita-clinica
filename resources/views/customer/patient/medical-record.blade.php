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
    <div class="py-12">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-xl rounded-xl overflow-hidden">


                {{-- HEADER PACIENTE --}}

                <div class="px-6 py-6" style="background-color: {{ themeColor(600) }}">

                    <div class="flex justify-between items-start">

                        {{-- Información del paciente --}}
                        <div>

                            <h1 class="text-2xl font-bold text-white">
                                {{ $medicalRecord->patient->name }}
                            </h1>

                            <p class="text-blue-100 mt-1">
                                RUT: {{ $medicalRecord->patient->rut }}
                            </p>

                        </div>

                    </div>

                </div>


                <div class="p-6 space-y-10">

                    @if (session('error'))
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('error') }}</div>
                    @endif

                    {{-- DATOS PACIENTE --}}

                    <section>
                        <div class="-mx-6 -mt-6 mb-6 px-6 py-4 " style="background-color: {{ themeColor(100) }}">
                            <h2 class="text-lg font-bold text-gray-800">
                                Datos del Paciente
                            </h2>
                        </div>


                        <div class="grid md:grid-cols-3 gap-6">

                            <div>

                                <p class="text-sm text-gray-500">
                                    Nombre completo
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->name }}

                                </p>

                            </div>

                            <div>

                                <p class="text-sm text-gray-500">
                                    RUT
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->rut }}

                                </p>

                            </div>

                            <div>

                                <p class="text-sm text-gray-500">
                                    Fecha nacimiento
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->birth_date?->format('d/m/Y') ?? '-' }}

                                </p>

                            </div>
                            <div>

                                <p class="text-sm text-gray-500">
                                    Sexo
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->gender ?? '-' }}

                                </p>

                            </div>

                            <div>

                                <p class="text-sm text-gray-500">
                                    Teléfono
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->phone ?? '-' }}

                                </p>

                            </div>

                            <div>

                                <p class="text-sm text-gray-500">
                                    Correo
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->email ?? '-' }}

                                </p>

                            </div>

                            <div>

                                <p class="text-sm text-gray-500">
                                    Dirección
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->address ?? '-' }}

                                </p>

                            </div>


                            <div>

                                <p class="text-sm text-gray-500">
                                    Previsión
                                </p>

                                <p class="font-semibold">

                                    {{ $patient->insurance?->name ?? 'Sin previsión' }}

                                </p>

                            </div>

                            <div>

                                <p class="text-sm text-gray-500">
                                    Fecha de ingreso
                                </p>

                                <p class="font-semibold">

                                    {{ $medicalRecord->patient->created_at?->format('d/m/Y - H:i') ?? '-' }}

                                </p>

                            </div>



                        </div>


                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">Registro de actividad</h2>
                                <p class="mt-1 text-sm text-gray-500">Quién creó o modificó los datos y la ficha clínica.</p>
                            </div>
                            <span class="text-xs text-gray-500">Últimos 100 cambios</span>
                        </div>

                        @if ($auditLogs->isEmpty())
                            <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500">No hay cambios registrados todavía.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($auditLogs as $auditLog)
                                    <article class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                                        <div class="flex flex-col justify-between gap-1 sm:flex-row sm:items-start">
                                            <div>
                                                @if ($auditLog->action === 'legacy')
                                                    <p class="font-semibold text-gray-800">Registro previo a la auditoría</p>
                                                    <p class="mt-1 text-sm text-gray-600">El usuario que lo creó y los cambios anteriores no quedaron registrados.</p>
                                                @else
                                                    <p class="font-semibold text-gray-800">
                                                        {{ $auditLog->user?->name ?? 'Usuario eliminado' }}
                                                        <span class="font-normal text-gray-600">
                                                            @switch($auditLog->action)
                                                                @case('created') creó @break
                                                                @case('updated') modificó @break
                                                                @case('started') inició @break
                                                                @case('finished') finalizó @break
                                                                @case('cancelled') canceló @break
                                                                @default actualizó
                                                            @endswitch
                                                            {{ strtolower($auditLog->section) }}
                                                        </span>
                                                    </p>
                                                @endif
                                            </div>
                                            <time class="text-xs text-gray-500" datetime="{{ $auditLog->created_at->toIso8601String() }}">
                                                {{ $auditLog->created_at->timezone('America/Santiago')->format('d/m/Y H:i') }}
                                            </time>
                                        </div>

                                        @if (!empty($auditLog->changes))
                                            <ul class="mt-3 space-y-1 text-sm text-gray-600">
                                                @foreach ($auditLog->changes as $field => $change)
                                                    @php
                                                        $beforeValue = $change['before'] ?? null;
                                                        $afterValue = $change['after'] ?? null;
                                                        $formatAuditValue = fn ($value) => is_array($value)
                                                            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                                            : ($value === null || $value === '' ? 'Sin dato' : (string) $value);
                                                    @endphp
                                                    <li>
                                                        <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $field)) }}:</span>
                                                        {{ $formatAuditValue($beforeValue) }}
                                                        <span class="px-1 text-gray-400">→</span>
                                                        {{ $formatAuditValue($afterValue) }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    {{-- RESUMEN CLINICO FICTICIO --}}

                    <section>
                        <div class="-mx-6 -mt-6 mb-6 px-6 py-4 " style="background-color: {{ themeColor(100) }}">

                            <h2 class="text-lg font-bold text-gray-800 ">

                                Resumen Clínico

                            </h2>
                        </div>

                        <div class="grid md:grid-cols-3 gap-5">

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Total atenciones

                                </p>

                                <p class="text-3xl font-bold">

                                    {{ $totalAttentions }}

                                </p>

                            </div>

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Última atención

                                </p>

                                <p class="font-bold">

                                    {{ $lastAttentionDate ? \Illuminate\Support\Carbon::parse($lastAttentionDate)->format('d/m/Y') : 'Sin atenciones registradas' }}

                                </p>

                            </div>

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Último médico

                                </p>

                                <p class="font-bold">

                                    {{ $lastAttentionProfessional ?? 'Sin profesional registrado' }}

                                </p>

                            </div>

                        </div>

                    </section>

                    {{-- antecedentesmedicos- --}}

                    <section>

                        <div class="-mx-6 -mt-6 mb-6 px-6 py-4 " style="background-color: {{ themeColor(100) }}">

                            <h2 class="text-lg font-bold text-gray-800 ">
                                Antecedentes Médicos
                            </h2>

                        </div>

                        <div class="grid md:grid-cols-2 gap-5">

                            {{-- Alergias --}}

                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                                        <i class="fa-solid fa-triangle-exclamation text-yellow-500"></i>
                                        Alergias
                                    </h3>

                                    <a href="javascript:void(0)" onclick="openAllergyModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>

                                </div>

                                <div class="mt-3 p-5 ">
                                    @if ($allergies->isEmpty())
                                        <p class="text-gray-800"> No existen alergias registradas.</p>
                                    @else
                                        @foreach ($allergies as $allergen)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm cursor-pointer">
                                                    {{ $allergen->allergen }}
                                                </span>

                                                <div
                                                    class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2
                                                        hidden group-hover:block
                                                        bg-red-600 text-white text-xs rounded-lg px-3 py-2
                                                        shadow-lg whitespace-nowrap z-50">
                                                    {{ $allergen->reaction }}

                                                    <div
                                                        class="absolute left-1/2 top-full -translate-x-1/2
                                                                border-4 border-transparent border-t-red-600">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                            </div>

                            {{-- Enfermedades --}}
                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                                        <i class="fa-solid fa-triangle-exclamation text-yellow-500"></i>
                                        Enfermedades cronicas
                                    </h3>

                                    <a href="javascript:void(0)" onclick="openChronicDiseasesModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>

                                </div>
                                <div class="mt-3 p-5">
                                    @if ($chronicDiseases->isEmpty())
                                        <p class="text-gray-800"> No existen Enfermedades Cronicas.</p>
                                    @else
                                        @foreach ($chronicDiseases as $chronicDisease)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-sm cursor-pointer">
                                                    {{ $chronicDisease->name }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                            </div>

                            {{-- Medicamentos --}}
                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800">

                                        Medicamentos Habituales

                                    </h3>
                                    <a href="javascript:void(0)" onclick="openRegularMedicationsModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>
                                </div>
                                <div class="mt-3 p-5">
                                    @if ($regularMedications->isEmpty())
                                        <p class="text-gray-800"> No existen Medicamentos Habituales.</p>
                                    @else
                                        @foreach ($regularMedications as $regular)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm cursor-pointer">
                                                    {{ $regular->name }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>


                            </div>



                            {{-- Cirugías --}}
                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800">

                                        Cirugías Previas

                                    </h3>
                                    <a href="javascript:void(0)" onclick="openPreviousSurgeriesModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>
                                </div>

                                <div class="mt-3 p-5">
                                    @if ($previousSurgeries->isEmpty())
                                        <p class="text-gray-800"> No existen Cirugías Previas.</p>
                                    @else
                                        @foreach ($previousSurgeries as $regular)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm cursor-pointer">
                                                    {{ $regular->name }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                            </div>

                        </div>

                    </section>

                    {{-- Atencion actual --}}

                    <section id="current-encounter-section">

                        <div class="-mx-6 -mt-6 mb-6 px-6 py-4 flex justify-between items-center "
                            style="background-color: {{ themeColor(100) }}" >
                            <h2 class="text-lg font-bold text-gray-800">

                                Atención Actual

                            </h2>

                            
                            @if($canCreateEncounter && $specialties->isNotEmpty() && !$currentEncounter)

                                <a href="javascript:void(0)" onclick="openEncountersModal()" style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                <i class="fa-solid fa-circle-plus"></i>
                                Nueva atención

                                </a>
                            @endif
                            @if ($canCreateEncounter && $specialties->isEmpty())
                                <span class="text-sm text-amber-700">No tienes especialidades activas asignadas; contacta al administrador.</span>
                            @endif

                        </div>


                    @if($currentEncounter)

                        <div class="border rounded-xl mb-5 hover:shadow-md transition">

                            <div class="flex justify-between items-start p-5 border rounded-t-xl" style="background-color: {{ themeColor(50) }}">

                                <!-- Izquierda -->
                                <div class="flex gap-4" >

                                    <div
                                        class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-user-doctor text-blue-600 text-lg"></i>
                                    </div>

                                    <div>

                                        <h3 class="font-bold text-gray-800">
                                            Atención #{{ $currentEncounter->id }}
                                        </h3>

                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ \Illuminate\Support\Carbon::parse($currentEncounter->started_at)->format('d/m/Y · H:i') }} hrs
                                        </p>

                                    </div>

                                </div>

                                <!-- Derecha -->
                                <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700">
                                    En curso
                                </span>

                            </div>

                            <div class="grid md:grid-cols-4 gap-5 mt-6 p-5">

                                <div>
                                    <p class="text-sm text-gray-500">Profesional</p>
                                    <p class="font-medium">
                                            {{ $currentEncounter->professional?->name ?? $currentEncounter->appointment?->professional?->name ?? 'Sin profesional asociado' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Especialidad</p>
                                    <p class="font-medium">
                                    {{ $currentEncounter->specialty?->name ?? $currentEncounter->appointment?->specialty?->name ?? 'Sin especialidad asociada' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Motivo</p>
                                    <p class="font-medium">
                                        {{ $currentEncounter->chief_complaint ?? 'Sin motivo registrado' }}
                                    </p>
                                </div>

                                 <div>
                                    <p class="text-sm text-gray-500">Estado</p>
                                   <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                                    En curso
                                </span>
                                </div>

                            </div>

                            <div class="grid gap-4 border-t px-5 py-4 text-sm sm:grid-cols-2">
                                <div>
                                    <p class="text-gray-500">Iniciado por</p>
                                    <p class="font-medium">{{ $currentEncounter->openedBy?->name ?? 'No registrado' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Fecha y hora de inicio</p>
                                    <p class="font-medium">{{ $currentEncounter->started_at?->format('d/m/Y H:i') ?? 'No registrada' }}</p>
                                </div>
                            </div>

                            @if ($canManageCurrentEncounter)
                            <div class="border-t px-5 py-5">
                                <form action="{{ route('patients.encounters.finish', ['customer' => request()->route('customer'), 'id' => $patient->id, 'encounter' => $currentEncounter->id]) }}" method="POST" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <h4 class="font-semibold text-gray-800">Cerrar atención</h4>
                                    <div>
                                        <label for="encounter-assessment" class="mb-1 block text-sm font-medium text-gray-700">Evaluación clínica / diagnóstico</label>
                                        <textarea id="encounter-assessment" name="clinical_assessment" rows="4" required maxlength="20000" class="w-full rounded-lg border-gray-300" placeholder="Registra la evaluación clínica y el diagnóstico">{{ old('clinical_assessment', $currentEncounter->clinical_assessment) }}</textarea>
                                        @error('clinical_assessment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="encounter-treatment-plan" class="mb-1 block text-sm font-medium text-gray-700">Plan / tratamiento (opcional)</label>
                                        <textarea id="encounter-treatment-plan" name="treatment_plan" rows="3" maxlength="20000" class="w-full rounded-lg border-gray-300" placeholder="Indicaciones, tratamiento o seguimiento">{{ old('treatment_plan', $currentEncounter->treatment_plan) }}</textarea>
                                    </div>
                                    <div class="flex flex-wrap items-end justify-between gap-4">
                                        <button type="submit" style="background-color: {{ themeColor(600) }}" class="rounded px-4 py-2 font-semibold text-white hover:brightness-90">Finalizar atención</button>
                                    </div>
                                </form>
                                <form action="{{ route('patients.encounters.cancel', ['customer' => request()->route('customer'), 'id' => $patient->id, 'encounter' => $currentEncounter->id]) }}" method="POST" class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">
                                    @csrf
                                    @method('PATCH')
                                    <div class="min-w-64 flex-1">
                                        <label for="encounter-cancellation-reason" class="mb-1 block text-sm font-medium text-gray-700">Motivo para cancelar</label>
                                        <input id="encounter-cancellation-reason" name="cancellation_reason" required maxlength="2000" class="w-full rounded-lg border-gray-300" value="{{ old('cancellation_reason') }}" placeholder="Indica por qué se cancela">
                                        @error('cancellation_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <button type="submit" class="rounded border border-red-300 px-4 py-2 font-semibold text-red-700 hover:bg-red-50">Cancelar atención</button>
                                </form>
                            </div>
                            @elseif (!$currentEncounter->professional_id && !$currentEncounter->appointment?->professional_id)
                                <p class="border-t px-5 py-4 text-sm text-amber-700">Esta atención no tiene un médico responsable asignado.</p>
                            @else
                                <p class="border-t px-5 py-4 text-sm text-gray-500">Solo el médico responsable puede finalizar o cancelar esta atención.</p>
                            @endif

                          

                        </div>
                    @else
                        <h3 class="font-semibold text-gray-800">
                            No hay una atención activa
                        </h3>

                        <p class="text-gray-500 mt-1">
                            El paciente no tiene ninguna atención en curso. Puede crear una nueva atención para comenzar la atención clínica.
                        </p>
                    @endif

                    </section>

                    {{-- HISTORIAL --}}
                    <section id="encounter-history">
                        <div class="-mx-6 -mt-6 mb-6 px-6 py-4" style="background-color: {{ themeColor(100) }}">
                            <h2 class="text-lg font-bold text-gray-800">Historial de atenciones</h2>
                        </div>

                        <form method="GET"
                            action="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}"
                            class="mb-6 grid gap-3 rounded-lg border bg-gray-50 p-4 md:grid-cols-2 xl:grid-cols-3">
                            <input type="search" name="search" value="{{ request('search') }}"
                                placeholder="Buscar en motivo, evaluación o plan"
                                class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                            <select name="specialty_id"
                                class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todas las especialidades</option>
                                @foreach ($specialtyOptions as $specialtyOption)
                                    <option value="{{ $specialtyOption->id }}" @selected((string) request('specialty_id') === (string) $specialtyOption->id)>
                                        {{ $specialtyOption->name }}
                                    </option>
                                @endforeach
                            </select>

                            <select name="professional_id"
                                class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todos los profesionales</option>
                                @foreach ($professionalOptions as $professionalOption)
                                    <option value="{{ $professionalOption->id }}" @selected((string) request('professional_id') === (string) $professionalOption->id)>
                                        {{ $professionalOption->name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}"
                                    class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                                    Limpiar
                                </a>
                            </div>
                        </form>

                        <div class="space-y-4">
                            @forelse ($encounters as $encounter)
                                @php
                                    $encounterDate = $encounter->started_at ?? $encounter->created_at;
                                    $encounterStatus = (int) $encounter->status;
                                @endphp
                                <article class="overflow-hidden rounded-xl border">
                                    <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-start sm:justify-between"
                                        style="background-color: {{ themeColor(50) }}">
                                        <div>
                                            <h3 class="font-bold text-gray-800">Atención #{{ $encounter->id }}</h3>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ \Illuminate\Support\Carbon::parse($encounterDate)->format('d/m/Y H:i') }} hrs
                                            </p>
                                        </div>
                                        <span class="inline-flex w-fit rounded-full px-3 py-1 text-sm font-semibold {{ $encounterStatus === 2 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $encounterStatus === 2 ? 'Terminada' : 'Cancelada' }}
                                        </span>
                                    </div>
                                    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <p class="text-sm text-gray-500">Profesional</p>
                                            <p class="font-medium">{{ $encounter->professional?->name ?? $encounter->appointment?->professional?->name ?? 'Sin profesional asociado' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Especialidad</p>
                                            <p class="font-medium">{{ $encounter->specialty?->name ?? $encounter->appointment?->specialty?->name ?? 'Sin especialidad asociada' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Motivo</p>
                                            <p class="whitespace-pre-wrap break-words font-medium">{{ $encounter->chief_complaint ?? 'Sin motivo registrado' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Evaluación clínica</p>
                                            <p class="whitespace-pre-wrap break-words font-medium">{{ $encounter->clinical_assessment ?? 'Sin evaluación registrada' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Plan / tratamiento</p>
                                            <p class="whitespace-pre-wrap break-words font-medium">{{ $encounter->treatment_plan ?? 'Sin plan registrado' }}</p>
                                        </div>
                                        @if ($encounterStatus === 3)
                                            <div>
                                                <p class="text-sm text-gray-500">Motivo de cancelación</p>
                                                <p class="whitespace-pre-wrap break-words font-medium">{{ $encounter->cancellation_reason ?? 'Sin motivo indicado' }}</p>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="grid gap-4 border-t px-5 py-4 text-sm sm:grid-cols-2">
                                        <div>
                                            <p class="text-gray-500">Iniciado por</p>
                                            <p class="font-medium">{{ $encounter->openedBy?->name ?? 'No registrado' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500">Atención cerrada el</p>
                                            <p class="font-medium">{{ $encounter->ended_at?->format('d/m/Y H:i') ?? 'No registrado' }}</p>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <p class="rounded-lg bg-gray-50 px-5 py-8 text-center text-sm text-gray-500">
                                    Todavía no hay atenciones finalizadas o canceladas.
                                </p>
                            @endforelse
                        </div>
                        @if ($encounters->hasPages())
                            <div class="mt-6">
                                {{ $encounters->links() }}
                            </div>
                        @endif
                    </section>

                    {{-- BOTONES --}}


                    <div class="border-t pt-6 flex justify-end gap-3">


                        <a href="/patient" class="px-5 py-2 rounded-lg bg-gray-200">

                            Volver

                        </a>

                        <button style="background-color: {{ themeColor(600) }}"
                            class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                            <i class="fa-solid fa-print"></i>
                            Imprimir ficha

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

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
    <div id="modalEncounters" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-black/50 p-4">
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl">
            <div class="rounded-t-lg border-b px-6 py-4" style="background-color: {{ themeColor(100) }}">
                <h2 class="text-lg font-bold">Nueva atención médica</h2>
                <p class="mt-1 text-sm text-gray-600">Paciente: {{ $patient->name }} · RUT: {{ $patient->rut }}</p>
            </div>

            <form action="{{ route('patients.encounters.store', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}" method="POST" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <div class="space-y-4 overflow-y-auto p-6">
                    @error('encounter')
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror

                    <div>
                        <label for="encounter-appointment" class="mb-1 block text-sm font-medium text-gray-700">Cita asociada (opcional)</label>
                        <select id="encounter-appointment" name="appointment_id" class="w-full rounded-lg border-gray-300">
                            <option value="">Sin cita asociada</option>
                            @foreach ($patientAppointments as $appointment)
                                <option value="{{ $appointment->id }}" data-specialty-id="{{ $appointment->specialty_id }}" @selected(old('appointment_id', $preselectedAppointmentId) == $appointment->id)>
                                    {{ \Illuminate\Support\Carbon::parse($appointment->start_at)->format('d/m/Y H:i') }} · {{ $appointment->professional?->name ?? 'Sin profesional' }} · {{ $appointment->specialty?->name ?? 'Sin especialidad' }}
                                </option>
                            @endforeach
                        </select>
                        @error('appointment_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
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
                alert('Ingrese un alérgeno');
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
                alert('Ingrese una enfermedad cronica');
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
                alert('Ingrese un medicamento habitual');
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
                alert('Ingrese una cirugía previa');
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
        if (encounterAppointmentSelect && encounterSpecialtySelect) {
            encounterAppointmentSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const specialtyId = selectedOption?.dataset.specialtyId;
                if (specialtyId) encounterSpecialtySelect.value = specialtyId;
                encounterSpecialtySelect.required = !this.value;
            });
            encounterSpecialtySelect.required = !encounterAppointmentSelect.value;
            if (encounterAppointmentSelect.value) {
                encounterAppointmentSelect.dispatchEvent(new Event('change'));
            }
        }

        @if ($openEncounterModal || $errors->has('encounter') || $errors->has('chief_complaint') || $errors->has('appointment_id') || $errors->has('specialty_id'))
            document.addEventListener('DOMContentLoaded', openEncountersModal);
        @endif
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

            initializeHistorySelects();

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
                        alert('No se pudo actualizar el historial de atenciones.');
                    }
                } finally {
                    document.getElementById('encounter-history')?.classList.remove('opacity-50');
                    document.getElementById('encounter-history')?.removeAttribute('aria-busy');
                }
            }

            document.addEventListener('submit', event => {
                const form = event.target.closest('#encounter-history form');
                if (!form) return;
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
