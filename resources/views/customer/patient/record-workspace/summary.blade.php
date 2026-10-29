                    <section>
                        <div class="mb-5 px-4 py-3 " style="background-color: {{ themeColor(100) }}">

                            <h2 class="text-lg font-bold text-gray-800 ">

                                Resumen Clínico

                            </h2>
                        </div>

                        <div class="grid md:grid-cols-3 gap-5">

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Total atenciones

                                </p>

                                <p class="text-3xl font-bold">

                                    {{ $totalAttentions }}

                                </p>

                            </div>

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Última atención

                                </p>

                                <p class="font-bold">

                                    {{ $lastAttentionDate ? \Illuminate\Support\Carbon::parse($lastAttentionDate)->format('d/m/Y') : 'Sin atenciones registradas' }}

                                </p>

                            </div>

                            <div class="bg-gray-50 rounded-xl p-5">

                                <p class="text-sm text-gray-500">

                                    Último médico

                                </p>

                                <p class="font-bold">

                                    {{ $lastAttentionProfessional ?? 'Sin profesional registrado' }}

                                </p>

                            </div>

                        </div>

                    </section>