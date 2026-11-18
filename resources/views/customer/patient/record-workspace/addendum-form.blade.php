        <form data-addendum-form data-signing-draft data-signing-method="POST"
            data-preview-url="{{ route('patients.encounters.addenda.preview', ['customer' => request()->route('customer'), 'id' => $encounter->patient_id, 'encounter' => $encounter->id]) }}"
            action="{{ route('patients.encounters.addenda.store', ['customer' => request()->route('customer'), 'id' => $encounter->patient_id, 'encounter' => $encounter->id]) }}" method="POST" class="space-y-5 p-6">
            @csrf
            <p class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">La atención original y los documentos anteriores se conservarán íntegros. Esta adenda tendrá su propia aceptación, verificación de identidad y firma FES.</p>
            <div><label for="addendum-type" class="mb-1 block text-sm font-medium">Tipo de adenda</label><select id="addendum-type" name="type" class="w-full rounded-lg border-gray-300" required>
                @foreach($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select></div>
            <div data-rectification-fields hidden class="space-y-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div><label for="addendum-target" class="mb-1 block text-sm font-medium">Documento que se corrige</label><select id="addendum-target" name="rectifies_version_id" disabled class="w-full rounded-lg border-gray-300">
                    @foreach($targets as $target)<option value="{{ $target['id'] }}" data-kind="{{ $target['kind'] }}">{{ $target['kind'] === 'original' ? 'Atención original' : 'Adenda' }} · Documento #{{ $target['id'] }} · Secuencia {{ $target['version'] }}</option>@endforeach
                </select></div>
                <div><label for="addendum-field" class="mb-1 block text-sm font-medium">Apartado que se corrige</label><select id="addendum-field" name="target_field" disabled class="w-full rounded-lg border-gray-300">
                    @foreach($fields as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select></div>
                <div><label for="addendum-reason" class="mb-1 block text-sm font-medium">Motivo de la rectificación</label><textarea id="addendum-reason" name="reason" rows="3" maxlength="2000" disabled class="w-full rounded-lg border-gray-300" placeholder="Explica por qué se corrige esta información"></textarea></div>
                <p class="text-xs text-amber-800">La vista previa mostrará el texto anterior, que no será reemplazado ni eliminado.</p>
            </div>
            <div><label for="addendum-content" class="mb-1 block text-sm font-medium">Contenido de la adenda / información corregida</label><textarea id="addendum-content" name="content" rows="7" required maxlength="20000" class="w-full rounded-lg border-gray-300" placeholder="Escribe la información que deseas incorporar"></textarea></div>
            <div class="flex flex-wrap justify-end gap-3">
                @if($inModal ?? false)
                    <button data-addendum-close type="button" class="rounded-lg border px-4 py-2">Cancelar</button>
                @else
                    <a href="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $encounter->patient_id, 'record_tab' => 'history', 'history_encounter_id' => $encounter->id]) }}" class="rounded-lg border px-4 py-2">Volver al historial</a>
                @endif
                <button data-signing-open type="button" class="rounded-lg px-4 py-2 font-semibold text-white disabled:opacity-50" style="background-color: {{ themeColor(600) }}">Firmar y guardar adenda</button>
            </div>
        </form>
