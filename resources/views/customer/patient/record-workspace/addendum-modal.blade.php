<div data-addendum-editor class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="addendum-editor-title">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b px-6 py-4" style="background-color: {{ themeColor(100) }}">
            <div><h3 id="addendum-editor-title" class="text-lg font-semibold">Agregar adenda · Atención #{{ $encounter->id }}</h3><p class="mt-1 text-sm text-gray-600">{{ $encounter->patient->name }} · {{ app(\App\Services\Identification\PatientIdentification::class)->clinicalNumber($encounter->patient) }}</p></div>
            <button data-addendum-close type="button" aria-label="Cerrar adenda" class="rounded-lg px-2 py-1 text-gray-600 hover:bg-white/50"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="overflow-y-auto">@include('customer.patient.record-workspace.addendum-form', ['inModal' => true])</div>
    </div>
</div>
@include('customer.patient.record-workspace.signature-modal', [
    'signingModalId' => 'encounter-addendum-signature-modal', 'signingTitle' => 'Firmar y guardar adenda',
    'acceptanceText' => \App\Services\Signatures\EncounterAddendumService::ACCEPTANCE,
])
