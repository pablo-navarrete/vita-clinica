                        <details id="medical-record-export-menu" class="relative">
                            <summary style="background-color: {{ themeColor(600) }}; list-style:none"
                                class="flex cursor-pointer items-center gap-2 rounded px-4 py-2 font-bold text-white transition hover:brightness-90">
                                <i class="fa-solid fa-file-export"></i>
                                Exportar
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </summary>
                            <div class="absolute top-full right-0 z-20 mt-2 min-w-56 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-xl">
                                <a href="{{ route('patients.medical-record.export.pdfa', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}"
                                   class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 transition hover:bg-gray-100">
                                    <i class="fa-solid fa-file-pdf text-red-600"></i>
                                    <span><strong class="block">PDF/A-1b</strong><small class="text-gray-500">Ficha lista para imprimir</small></span>
                                </a>
                                <a href="{{ route('patients.medical-record.export.fhir-r4', ['customer' => request()->route('customer'), 'id' => $patient->id]) }}"
                                   class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 transition hover:bg-gray-100">
                                    <i class="fa-solid fa-code text-blue-600"></i>
                                    <span><strong class="block">FHIR R4 JSON</strong><small class="text-gray-500">Datos para interoperabilidad</small></span>
                                </a>
                            </div>
                        </details>