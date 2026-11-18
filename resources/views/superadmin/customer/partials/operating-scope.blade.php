<section class="sm:col-span-2 rounded-lg border border-gray-200 bg-gray-50 p-4" aria-labelledby="operating-scope-title">
    <h3 id="operating-scope-title" class="mb-3 text-sm font-semibold text-gray-800">Ámbito de operación</h3>
    <label for="operating-country" class="mb-2 block text-sm text-gray-700">País de operación</label>
    <input id="operating-country" type="text" value="Chile (CL)" disabled class="w-full rounded-md border-gray-300 bg-gray-100 text-gray-500">
    <p class="mt-2 text-xs text-gray-600">VITA opera exclusivamente en Chile. El país emisor del documento tributario puede ser diferente; no determina la nacionalidad del titular.</p>
    <template x-if="errors.operating_country_code"><p class="mt-2 text-xs text-red-600" x-text="errors.operating_country_code[0]"></p></template>
    <div class="mt-4 rounded-md border border-gray-200 bg-white p-3" x-show="!isEdit || form.operating_declaration?.version !== @js(\App\Services\CustomerOperatingScope::VERSION)">
        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="checkbox" x-model="form.operating_declaration_accepted" class="mt-1 rounded border-gray-300 text-blue-600">
            <span>{{ \App\Services\CustomerOperatingScope::DECLARATION }}</span>
        </label>
        <p class="mt-2 text-xs text-gray-500">Se registrarán esta versión, la fecha y el usuario que acepta. Las clínicas existentes conservan su acceso aunque no tengan una aceptación histórica.</p>
    </div>
    <template x-if="form.operating_declaration">
        <p class="mt-3 text-xs text-gray-600" x-text="`Declaración ${form.operating_declaration.version} registrada por usuario #${form.operating_declaration.accepted_by} · ${new Date(form.operating_declaration.accepted_at).toLocaleString('es-CL')}`"></p>
    </template>
    <template x-if="errors.operating_declaration_accepted"><p class="mt-2 text-xs text-red-600" x-text="errors.operating_declaration_accepted[0]"></p></template>
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="operating-review-status" class="mb-2 block text-sm text-gray-700">Revisión administrativa</label>
            <select id="operating-review-status" x-model="form.operating_review_status" class="w-full rounded-md border-gray-300 bg-white">
                @foreach(\App\Services\CustomerOperatingScope::STATUSES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <template x-if="errors.operating_review_status"><p class="mt-2 text-xs text-red-600" x-text="errors.operating_review_status[0]"></p></template>
        </div>
        <div>
            <label for="operating-review-note" class="mb-2 block text-sm text-gray-700">Nota de revisión (opcional)</label>
            <textarea id="operating-review-note" rows="2" maxlength="2000" x-model="form.operating_review_note" class="w-full rounded-md border-gray-300" placeholder="No incluyas documentos personales ni datos clínicos."></textarea>
            <template x-if="errors.operating_review_note"><p class="mt-2 text-xs text-red-600" x-text="errors.operating_review_note[0]"></p></template>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-500">La revisión administrativa no verifica automáticamente la identidad, el cumplimiento normativo ni la habilitación profesional.</p>
</section>
