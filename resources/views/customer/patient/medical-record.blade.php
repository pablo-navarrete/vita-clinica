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

        <a href="/patient" class="px-5 py-2 rounded-lg bg-gray-200 hover:bg-gray-300">
            Volver
        </a>
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

                        {{-- Estado --}}
                        <div>

                            @if ($medicalRecord->patient->status)
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm font-semibold">

                                    Activo
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-semibold">

                                    Inactivo
                                </span>
                            @endif

                        </div>

                    </div>

                </div>


                <div class="p-6 space-y-10">

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

                                    24

                                </p>

                            </div>

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Última atención

                                </p>

                                <p class="font-bold">

                                    08/07/2026

                                </p>

                            </div>

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Último profesional

                                </p>

                                <p class="font-bold">

                                    Dr. Carlos González

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

                    <section>

                        <div class="-mx-6 -mt-6 mb-6 px-6 py-4 flex justify-between items-center "
                            style="background-color: {{ themeColor(100) }}" >
                            <h2 class="text-lg font-bold text-gray-800">

                                Atención Actual

                            </h2>

                            
                            @if(!$currentEncounter)

                                <a href="javascript:void(0)" onclick="openEncountersModal()" style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                <i class="fa-solid fa-circle-plus"></i>
                                Nueva atención

                                </a>
                            @endif

                        </div>


                    @if($currentEncounter)

                        <div class="border rounded-xl  mb-5 hover:shadow-md transition">

                            <div class="flex justify-between items-start p-5 border rounded-t-xl" style="background-color: {{ themeColor(50) }}">

                                <!-- Izquierda -->
                                <div class="flex gap-4" >

                                    <div
                                        class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-user-doctor text-blue-600 text-lg"></i>
                                    </div>

                                    <div>

                                        <h3 class="font-bold text-gray-800">
                                            Atención #123456
                                        </h3>

                                        <p class="text-sm text-gray-500 mt-1">
                                            23/12/2026 • 09:30 hrs
                                        </p>

                                    </div>

                                </div>

                                <!-- Derecha -->
                                 <button style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                
                                Continuar

                            </button>

                            </div>

                            <div class="grid md:grid-cols-4 gap-5 mt-6 p-5">

                                <div>
                                    <p class="text-sm text-gray-500">Profesional</p>
                                    <p class="font-medium">
                                        Dr. Pablo Navarrete
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Especialidad</p>
                                    <p class="font-medium">
                                        Medicina General
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Motivo</p>
                                    <p class="font-medium">
                                        Dolor de cabeza persistente y vómitos explosivos
                                    </p>
                                </div>

                                 <div>
                                    <p class="text-sm text-gray-500">Estado</p>
                                   <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                                    En espera
                                </span>
                                </div>

                            </div>

                          

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

                    <section>

                        <div class="-mx-6 -mt-6 mb-6 px-6 py-4 flex justify-between items-center"
                            style="background-color: {{ themeColor(100) }}">
                            <h2 class="text-lg font-bold text-gray-800">

                                Historial de Atenciones

                            </h2>


                        </div>


                        {{-- BUSCADOR Y FILTROS --}}



                        <div class="bg-gray-50 rounded-xl p-5 mb-6">


                            <div class="grid md:grid-cols-4 gap-4">



                                <div class="md:col-span-2">


                                    <input type="text" placeholder="Buscar diagnóstico, tratamiento..."
                                        class="w-full rounded-lg border-gray-300">


                                </div>





                                <div>


                                    <select class="w-full rounded-lg border-gray-300">


                                        <option>
                                            Todos los profesionales
                                        </option>


                                        <option>
                                            Dr. Carlos González
                                        </option>


                                        <option>
                                            Dra. María Soto
                                        </option>


                                    </select>


                                </div>






                                <div>


                                    <select class="w-full rounded-lg border-gray-300">


                                        <option>
                                            Todas las fechas
                                        </option>


                                        <option>
                                            2026
                                        </option>


                                        <option>
                                            2025
                                        </option>


                                    </select>


                                </div>




                            </div>


                        </div>




                        <div class="border rounded-xl  mb-5 hover:shadow-md transition">

                            <div class="flex justify-between items-start p-5 border rounded-t-xl" style="background-color: {{ themeColor(50) }}">

                                <!-- Izquierda -->
                                <div class="flex gap-4" >

                                    <div
                                        class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-user-doctor text-blue-600 text-lg"></i>
                                    </div>

                                    <div>

                                        <h3 class="font-bold text-gray-800">
                                            Atención #123456
                                        </h3>

                                        <p class="text-sm text-gray-500 mt-1">
                                            23/12/2026 • 09:30 hrs
                                        </p>

                                    </div>

                                </div>

                                <!-- Derecha -->
                                 <button style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                
                                Ver detalle

                            </button>

                            </div>

                            <div class="grid md:grid-cols-4 gap-5 mt-6 p-5">

                                <div>
                                    <p class="text-sm text-gray-500">Profesional</p>
                                    <p class="font-medium">
                                        Dr. Pablo Navarrete
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Especialidad</p>
                                    <p class="font-medium">
                                        Medicina General
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Motivo</p>
                                    <p class="font-medium">
                                        Dolor de cabeza persistente y vómitos explosivos
                                    </p>
                                </div>

                                 <div>
                                    <p class="text-sm text-gray-500">Estado</p>
                                   <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                                    En espera
                                </span>
                                </div>

                            </div>

                          

                        </div>

                        <div class="border rounded-xl  mb-5 hover:shadow-md transition">

                            <div class="flex justify-between items-start p-5 border rounded-t-xl" style="background-color: {{ themeColor(50) }}">

                                <!-- Izquierda -->
                                <div class="flex gap-4" >

                                    <div
                                        class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-user-doctor text-blue-600 text-lg"></i>
                                    </div>

                                    <div>

                                        <h3 class="font-bold text-gray-800">
                                            Atención #123456
                                        </h3>

                                        <p class="text-sm text-gray-500 mt-1">
                                            23/12/2026 • 09:30 hrs
                                        </p>

                                    </div>

                                </div>

                                <!-- Derecha -->
                                 <button style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                
                                Ver detalle

                            </button>

                            </div>

                            <div class="grid md:grid-cols-4 gap-5 mt-6 p-5">

                                <div>
                                    <p class="text-sm text-gray-500">Profesional</p>
                                    <p class="font-medium">
                                        Dr. Pablo Navarrete
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Especialidad</p>
                                    <p class="font-medium">
                                        Medicina General
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Motivo</p>
                                    <p class="font-medium">
                                        Dolor de cabeza persistente y vómitos explosivos
                                    </p>
                                </div>

                                 <div>
                                    <p class="text-sm text-gray-500">Estado</p>
                                   <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                                    En espera
                                </span>
                                </div>

                            </div>

                          

                        </div>

                        {{-- PAGINACION VISUAL --}}

                        <div class="flex justify-center gap-2 mt-8">

                            <button class="px-3 py-2 bg-gray-100 rounded-lg">

                                Anterior

                            </button>

                            <button class="px-3 py-2 bg-blue-100  rounded-lg">

                                1

                            </button>

                            <button class="px-3 py-2 bg-gray-100 rounded-lg">

                                2

                            </button>

                            <button class="px-3 py-2 bg-gray-100 rounded-lg">

                                3

                            </button>

                            <button class="px-3 py-2 bg-gray-100 rounded-lg">

                                Siguiente

                            </button>

                        </div>

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


    {{-- modal editar / crear atencion medica --}}

    <div id="modalEncounters" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
            {{-- Header --}}
            <div class="px-6 py-4 border-b rounded-t-lg " style="background-color: {{ themeColor(100) }}">
                <h2 class="text-lg font-bold">
                    Crear Atención Médica
                </h2>
            </div>
            <form action="{{ route('previous_surgeries.store', app('customer')->domain) }}" method="POST">

                @csrf

                <input type="hidden" name="medical_record_id" value="{{ $medicalRecord->id }}">
                <input type="hidden" name="customer_id" value="{{ app('customer')->id }}">
                <div class="p-6">
                    

                    <div class="space-y-4">

                        <div>

                            <label class="block text-sm font-medium mb-1">

                                Motivo

                            </label>

                            <input type="text" name="name" class="w-full rounded-lg border-gray-300"
                                placeholder="Ej. Apendicectomía (2018)" id="previousSurgeriesName">

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
@endsection
@push('scripts')
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

        }
    </script>
@endpush
