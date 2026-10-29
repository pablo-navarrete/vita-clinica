                    <section>
                        <div class="mb-5 px-4 py-3 " style="background-color: {{ themeColor(100) }}">
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