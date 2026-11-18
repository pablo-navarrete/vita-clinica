@include('customer.patient.record-workspace.styles')
<div id="clinical-workspace" class="clinical-workspace" x-data="{ tab: '{{ request()->filled('history_encounter_id') || request('record_tab') === 'history' ? 'history' : 'personal' }}', mobilePanel: '{{ request()->filled('history_encounter_id') || request('record_tab') === 'history' ? 'record' : ($currentEncounter ? 'attention' : 'record') }}', ratio: '50', videoMode: 'normal', videoLoaded: {{ $currentEncounter?->modality === 'telemedicine' && $canManageCurrentEncounter && $currentEncounter->telemedicine_session_id ? 'true' : 'false' }}, videoCorner: 0 }" :data-ratio="ratio" :data-mobile-panel="mobilePanel" :data-video-mode="videoMode" :data-video-corner="videoCorner" :data-video-loaded="String(videoLoaded)">
    <header class="clinical-patient-header">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color: {{ themeColor(700) }}">Ficha clínica</p>
            <h1 class="break-words text-xl font-bold text-gray-900">{{ $patient->name }}</h1>
            <p class="mt-1 text-xs text-gray-600">{{ app(\App\Services\Identification\PatientIdentification::class)->clinicalNumber($patient) }} · {{ $patient->birth_date && !$patient->birth_date->isFuture() ? $patient->birth_date->age . ' años' : 'Edad no registrada' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user() && app(\App\Policies\PortalClinicalAccessPolicy::class)->manage(auth()->user(),$patient))
                <a href="{{ route('patients.portal-access.index',['customer'=>request()->route('customer'),'id'=>$patient->id]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700">Acceso Mi Salud</a>
            @endif
            <label class="clinical-ratio text-xs text-gray-600">Distribución
                <select x-model="ratio" aria-label="Distribución de ficha y atención" class="ml-1 rounded-lg border-gray-300 py-1 text-sm">
                    <option value="50">50 / 50</option><option value="40">40 / 60</option><option value="60">60 / 40</option>
                </select>
            </label>
            <a href="{{ route('patients.medical-record.activity', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700">Registro de actividad</a>
            @include('customer.patient.record-workspace.export')
        </div>
    </header>
    <div id="clinical-notices" hidden>@include('customer.patient.record-workspace.notices')</div>
    <nav class="clinical-mobile-tabs" aria-label="Área de trabajo">
        <button type="button" @click="mobilePanel = 'record'" :aria-pressed="mobilePanel === 'record'">Ficha clínica</button>
        <button type="button" @click="mobilePanel = 'attention'" :aria-pressed="mobilePanel === 'attention'">Atención actual</button>
    </nav>
    <div class="clinical-panels">
        <section class="clinical-panel clinical-record-panel" aria-label="Ficha clínica del paciente">
            <nav class="clinical-record-tabs" aria-label="Secciones de la ficha" role="tablist">
                @foreach(['personal' => 'Datos personales', 'summary' => 'Resumen', 'background' => 'Antecedentes', 'history' => 'Historial', 'documents' => 'Documentos'] as $tabKey => $tabLabel)
                    <button type="button" role="tab" id="record-tab-{{ $tabKey }}" aria-controls="record-section-{{ $tabKey }}" :aria-selected="tab === '{{ $tabKey }}'" @click="tab = '{{ $tabKey }}'" :tabindex="tab === '{{ $tabKey }}' ? 0 : -1"
                        @keydown.arrow-right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()" @keydown.arrow-left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()">{{ $tabLabel }}</button>
                @endforeach
            </nav>
            <div id="clinical-record-content" class="clinical-panel-scroll">
                @foreach(['personal', 'summary', 'background', 'history', 'documents'] as $tabKey)
                    <div id="record-section-{{ $tabKey }}" role="tabpanel" aria-labelledby="record-tab-{{ $tabKey }}" x-show="tab === '{{ $tabKey }}'" x-cloak>
                        @include('customer.patient.record-workspace.' . $tabKey)
                    </div>
                @endforeach
            </div>
        </section>
        <section class="clinical-panel clinical-attention-panel" aria-label="Atención médica actual">
            <div class="clinical-panel-scroll" id="clinical-attention-content">
                @include('customer.patient.record-workspace.attention')
            </div>
        </section>
    </div>
</div>
@include('customer.patient.record-workspace.scripts')
