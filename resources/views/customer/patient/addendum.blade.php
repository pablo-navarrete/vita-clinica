@extends('layouts.menu-customer')
@section('header')
    <h2 class="text-xl font-semibold text-gray-800">Agregar adenda · Atención #{{ $encounter->id }}</h2>
@endsection
@section('content')
<div class="mx-auto max-w-5xl">
    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}">
            <h3 class="font-semibold">Nueva adenda médica firmada</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $encounter->patient->name }} · {{ app(\App\Services\Identification\PatientIdentification::class)->clinicalNumber($encounter->patient) }}</p>
        </div>
        @include('customer.patient.record-workspace.addendum-form')
    </div>
</div>
@include('customer.patient.record-workspace.signature-modal', ['signingTitle' => 'Firmar y guardar adenda', 'acceptanceText' => \App\Services\Signatures\EncounterAddendumService::ACCEPTANCE])
@endsection
@push('scripts')
<script src="{{ asset('js/encounter-addendum.js') }}?v={{ filemtime(public_path('js/encounter-addendum.js')) }}"></script>
@endpush
