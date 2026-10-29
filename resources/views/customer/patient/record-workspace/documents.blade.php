                    <section id="patient-documents">
                        <div class="mb-5 flex items-center justify-between px-4 py-3" style="background-color: {{ themeColor(100) }}">
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">Documentos clínicos</h2>
                                <p class="mt-1 text-sm text-gray-600">Documentos del paciente, organizados por la atención que los originó.</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-gray-700">{{ $documents->count() }}</span>
                        </div>

                        <div class="space-y-4">
                            @forelse ($documents->groupBy('encounter_id') as $encounterDocuments)
                                @php
                                    $documentEncounter = $encounterDocuments->first()->encounter;
                                    $documentProfessional = $documentEncounter?->professional?->name ?? $documentEncounter?->appointment?->professional?->name;
                                    $documentDate = $documentEncounter?->started_at ?? $documentEncounter?->created_at;
                                @endphp
                                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                                        <p class="font-semibold text-gray-800">
                                            @if ($documentEncounter)
                                                Atención #{{ $documentEncounter->id }}
                                                @if ($documentEncounter->chief_complaint) · {{ $documentEncounter->chief_complaint }} @endif
                                            @else
                                                Atención no identificada
                                            @endif
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            @if ($documentDate) {{ $documentDate->format('d/m/Y H:i') }} @endif
                                            @if ($documentProfessional) · {{ $documentProfessional }} @endif
                                            @if ($encounterDocuments->first()->clinicBranch) · {{ $encounterDocuments->first()->clinicBranch->name }} @endif
                                        </p>
                                    </div>
                                    <div class="divide-y divide-gray-100">
                                        @foreach ($encounterDocuments as $document)
                                            <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-3">
                                                <div class="flex min-w-0 items-start gap-3">
                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700"><i class="fa-solid fa-file-lines"></i></span>
                                                    <div class="min-w-0">
                                                        <p class="break-words font-semibold text-gray-800">{{ $document->original_name }}</p>
                                                        @if ($document->description)<p class="mt-1 text-sm text-gray-600">{{ $document->description }}</p>@endif
                                                        <p class="mt-1 text-xs text-gray-500">{{ number_format($document->size / 1048576, 2, ',', '.') }} MB</p>
                                                    </div>
                                                </div>
                                                <div class="flex shrink-0 flex-wrap gap-2">
                                                    <a href="{{ route('patients.documents.preview', ['customer' => request()->route('customer'), 'id' => $patient->id, 'document' => $document->id]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                                                        <i class="fa-solid fa-eye"></i> Ver documento
                                                    </a>
                                                    <a href="{{ route('patients.documents.download', ['customer' => request()->route('customer'), 'id' => $patient->id, 'document' => $document->id]) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                                        <i class="fa-solid fa-download"></i> Descargar
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center text-sm text-gray-500">
                                    Aún no hay documentos en la ficha clínica.
                                </div>
                            @endforelse
                        </div>
                    </section>