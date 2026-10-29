                    <section id="encounter-history">
                        @if(request()->filled('history_encounter_id'))
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm" style="border-color: {{ themeColor(200) }}; background: {{ themeColor(50) }}">
                                <span class="font-semibold">Atenci&#243;n seleccionada #{{ request()->integer('history_encounter_id') }}</span>
                                <a href="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $patient->id, 'record_tab' => 'history']) }}" class="font-semibold underline" style="color: {{ themeColor(700) }}">Ver todo el historial</a>
                            </div>
                        @endif

                        <div class="mb-5 px-4 py-3" style="background-color: {{ themeColor(100) }}">
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
                                <article id="history-encounter-{{ $encounter->id }}" class="overflow-hidden rounded-xl border">
                                    <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-start sm:justify-between"
                                        style="background-color: {{ themeColor(50) }}">
                                        <div>
                                            <h3 class="font-bold text-gray-800">Atención #{{ $encounter->id }}</h3>
                                            <p class="mt-1 text-sm text-gray-500">Sede: {{ $encounter->clinicBranch?->name ?? 'Sin sede registrada' }}</p>
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
                                            <p class="text-sm text-gray-500">Modalidad</p>
                                            <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $encounter->modality === 'telemedicine' ? 'bg-cyan-100 text-cyan-800' : 'bg-gray-100 text-gray-700' }}">
                                                {{ $encounter->modality === 'telemedicine' ? 'Telemedicina' : 'Presencial' }}
                                            </span>
                                            @if ($encounter->telemedicineSession?->patient_consent_at)
                                                <p class="mt-1 text-xs text-gray-500">Consentimiento: {{ $encounter->telemedicineSession->patient_consent_at->timezone('America/Santiago')->format('d/m/Y H:i') }}</p>
                                            @endif
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
                                    <div x-data="{ addingObservation: false }" class="border-t bg-white px-5 py-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <h4 class="font-semibold text-gray-800">Observaciones adicionales</h4>
                                                <p class="mt-1 text-xs text-gray-500">Se agregan al registro sin modificar la atención cerrada.</p>
                                            </div>
                                            @if ($canCreateEncounter && (int) ($encounter->professional_id ?? $encounter->appointment?->professional_id) === (int) auth()->id() && $encounterStatus === 2)
                                                <button type="button" @click="addingObservation = !addingObservation" style="background-color: {{ themeColor(600) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-white hover:brightness-90">
                                                    <i class="fa-solid" :class="addingObservation ? 'fa-xmark' : 'fa-plus'"></i>
                                                    <span x-text="addingObservation ? 'Cancelar' : 'Agregar observación'"></span>
                                                </button>
                                            @endif
                                        </div>
                                        <div class="mt-3 space-y-3">
                                            @forelse ($encounter->observations as $observation)
                                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                                    <p class="whitespace-pre-wrap break-words text-sm text-gray-800">{{ $observation->observation }}</p>
                                                    <p class="mt-2 text-xs text-gray-500">
                                                        {{ $observation->author?->name ?? 'Usuario eliminado' }}
                                                        · {{ $observation->created_at->timezone('America/Santiago')->format('d/m/Y H:i') }}
                                                        @if ($observation->clinicBranch) · {{ $observation->clinicBranch->name }} @endif
                                                    </p>
                                                </div>
                                            @empty
                                                <p class="text-sm text-gray-500">Todavía no hay observaciones adicionales.</p>
                                            @endforelse
                                        </div>
                                        @if ($canCreateEncounter && (int) ($encounter->professional_id ?? $encounter->appointment?->professional_id) === (int) auth()->id() && $encounterStatus === 2)
                                            <form x-show="addingObservation" x-cloak action="{{ route('patients.encounters.observations.store', ['customer' => request()->route('customer'), 'id' => $patient->id, 'encounter' => $encounter->id]) }}" method="POST" class="mt-4 border-t border-gray-100 pt-4">
                                                @csrf
                                                <label for="encounter-observation-{{ $encounter->id }}" class="mb-1 block text-sm font-medium text-gray-700">Agregar observación</label>
                                                <textarea id="encounter-observation-{{ $encounter->id }}" name="observation" rows="3" maxlength="10000" required class="w-full rounded-lg border-gray-300 text-sm" placeholder="Agrega una nota complementaria con fecha y autor registrados"></textarea>
                                                <div class="mt-2 flex justify-end">
                                                    <button type="submit" style="background-color: {{ themeColor(600) }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-white hover:brightness-90">Guardar observación</button>
                                                </div>
                                            </form>
                                        @endif
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