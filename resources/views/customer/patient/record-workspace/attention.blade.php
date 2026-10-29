                    <section id="current-encounter-section">

                        <div class="mb-5 px-4 py-3 flex justify-between items-center "
                            style="background-color: {{ themeColor(100) }}" >
                            <h2 class="text-lg font-bold text-gray-800">

                                Atención Actual

                            </h2>

                            
                            @if($canCreateEncounter && $specialties->isNotEmpty())

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


                    @if ($activeEncounters->count() > 1)
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <label for="active-encounter-picker" class="mb-2 block text-sm font-semibold text-amber-900">Atenciones abiertas de este paciente</label>
                            <select id="active-encounter-picker" class="w-full rounded-lg border-gray-300 text-sm" onchange="if(this.value) window.VitaNavigation.navigate(this.value)">
                                @foreach ($activeEncounters as $openEncounter)
                                    <option value="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $patient->id, 'encounter_id' => $openEncounter->id]) }}" @selected($currentEncounter?->id === $openEncounter->id)>
                                        #{{ $openEncounter->id }} · {{ $openEncounter->professional?->name ?? $openEncounter->appointment?->professional?->name ?? 'Sin profesional' }} · {{ $openEncounter->clinicBranch?->name ?? 'Sin sede registrada' }} · {{ $openEncounter->appointment_id ? 'Cita #' . $openEncounter->appointment_id : 'Sin cita' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @include('customer.patient.record-workspace.video')
                    @if($currentEncounter)

                        <div class="border rounded-xl mb-5 hover:shadow-md transition">
                            <div class="flex justify-between items-start p-5 border rounded-t-xl" style="background-color: {{ themeColor(50) }}">
                                <div class="flex gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-user-doctor text-blue-600 text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800">Atención #{{ $currentEncounter->id }}</h3>
                                        <p class="text-sm text-gray-500 mt-1">{{ \Illuminate\Support\Carbon::parse($currentEncounter->started_at)->format('d/m/Y · H:i') }} hrs</p>
                                    </div>
                                </div>
                                <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700">En curso</span>
                            </div>
                            <div class="grid md:grid-cols-5 gap-5 mt-6 p-5">
                                <div><p class="text-sm text-gray-500">Profesional</p><p class="font-medium">{{ $currentEncounter->professional?->name ?? $currentEncounter->appointment?->professional?->name ?? 'Sin profesional asociado' }}</p></div>
                                <div><p class="text-sm text-gray-500">Especialidad</p><p class="font-medium">{{ $currentEncounter->specialty?->name ?? $currentEncounter->appointment?->specialty?->name ?? 'Sin especialidad asociada' }}</p></div>
                                <div>
                                    <p class="text-sm text-gray-500">Modalidad</p>
                                    <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $currentEncounter->modality === 'telemedicine' ? 'bg-cyan-100 text-cyan-800' : 'bg-gray-100 text-gray-700' }}">{{ $currentEncounter->modality === 'telemedicine' ? 'Telemedicina' : 'Presencial' }}</span>
                                    @if ($currentEncounter->telemedicineSession?->patient_consent_at)
                                        <p class="mt-1 text-xs text-gray-500">Consentimiento aceptado: {{ $currentEncounter->telemedicineSession->patient_consent_at->timezone('America/Santiago')->format('d/m/Y H:i') }}</p>
                                    @endif
                                </div>
                                <div><p class="text-sm text-gray-500">Motivo</p><p class="font-medium">{{ $currentEncounter->chief_complaint ?? 'Sin motivo registrado' }}</p></div>
                                <div><p class="text-sm text-gray-500">Estado</p><span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">En curso</span></div>
                            </div>
                            <div class="grid gap-4 border-t px-5 py-4 text-sm sm:grid-cols-2">
                                <div><p class="text-gray-500">Iniciado por</p><p class="font-medium">{{ $currentEncounter->openedBy?->name ?? 'No registrado' }}</p></div>
                                <div><p class="text-gray-500">Fecha y hora de inicio</p><p class="font-medium">{{ $currentEncounter->started_at?->format('d/m/Y H:i') ?? 'No registrada' }}</p></div>
                            </div>

                            @if ($canManageCurrentEncounter)
                                <form action="{{ route('patients.encounters.documents.store', ['customer' => request()->route('customer'), 'id' => $patient->id, 'encounter' => $currentEncounter->id]) }}" method="POST" enctype="multipart/form-data" class="mx-5 mb-5 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                                    @csrf
                                    <label class="mb-2 block text-sm font-semibold text-gray-700">Adjuntar documento a esta atención</label>
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="min-w-56 flex-1">
                                            <div x-data="{
                                                    fileName: '',
                                                    fileSize: '',
                                                    selectFile(file) {
                                                        this.fileName = file?.name || '';
                                                        this.fileSize = file ? `${(file.size / 1048576).toFixed(2)} MB` : '';
                                                    },
                                                    handleDrop(event) {
                                                        const files = event.dataTransfer.files;
                                                        if (!files.length) return;
                                                        this.$refs.documentInput.files = files;
                                                        this.selectFile(files[0]);
                                                    }
                                                }"
                                                class="rounded-lg border-2 border-dashed border-gray-300 bg-white p-6 text-center transition hover:bg-gray-50"
                                                @dragover.prevent
                                                @drop.prevent="handleDrop($event)"
                                                @click="$refs.documentInput.click()">
                                                <input id="encounter-document-input" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required class="hidden" x-ref="documentInput" @change="selectFile($event.target.files[0])">
                                                <template x-if="!fileName">
                                                    <div class="cursor-pointer text-gray-500">
                                                        <i class="fa-solid fa-cloud-arrow-up text-2xl"></i>
                                                        <p>Arrastra un documento aquí o haz clic</p>
                                                        <p class="mt-1 text-xs">PDF, JPG, PNG, DOC o DOCX. Máximo 20 MB.</p>
                                                    </div>
                                                </template>
                                                <template x-if="fileName">
                                                    <div class="cursor-pointer text-gray-600">
                                                        <i class="fa-solid fa-file-circle-check mb-2 text-3xl text-green-600"></i>
                                                        <p class="font-semibold text-green-700">Documento listo</p>
                                                        <p class="break-all text-sm" x-text="fileName"></p>
                                                        <p class="mt-1 text-xs" x-text="fileSize"></p>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <input type="text" name="description" maxlength="255" placeholder="Descripción (opcional)" class="min-w-48 flex-1 rounded-lg border-gray-300 text-sm">
                                        <button type="submit" style="background-color: {{ themeColor(600) }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-white hover:brightness-90">Adjuntar</button>
                                    </div>
                                    @error('document')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                    @error('description')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </form>
                            @endif

                            @include('customer.patient.record-workspace.attention-documents')
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
