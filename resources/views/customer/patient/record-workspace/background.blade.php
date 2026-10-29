                    <section>

                        <div class="mb-5 px-4 py-3 " style="background-color: {{ themeColor(100) }}">

                            <h2 class="text-lg font-bold text-gray-800 ">
                                Antecedentes Médicos
                            </h2>

                        </div>

                        <div class="grid md:grid-cols-2 gap-5">

                            {{-- Alergias --}}

                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                                        <i class="fa-solid fa-triangle-exclamation text-yellow-500"></i>
                                        Alergias
                                    </h3>

                                    <a href="javascript:void(0)" onclick="openAllergyModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>

                                </div>

                                <div class="mt-3 p-5 ">
                                    @if ($allergies->isEmpty())
                                        <p class="text-gray-800"> No existen alergias registradas.</p>
                                    @else
                                        @foreach ($allergies as $allergen)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm cursor-pointer">
                                                    {{ $allergen->allergen }}
                                                </span>

                                                <div
                                                    class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2
                                                        hidden group-hover:block
                                                        bg-red-600 text-white text-xs rounded-lg px-3 py-2
                                                        shadow-lg whitespace-nowrap z-50">
                                                    {{ $allergen->reaction }}

                                                    <div
                                                        class="absolute left-1/2 top-full -translate-x-1/2
                                                                border-4 border-transparent border-t-red-600">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                            </div>

                            {{-- Enfermedades --}}
                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                                        <i class="fa-solid fa-triangle-exclamation text-yellow-500"></i>
                                        Enfermedades cronicas
                                    </h3>

                                    <a href="javascript:void(0)" onclick="openChronicDiseasesModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>

                                </div>
                                <div class="mt-3 p-5">
                                    @if ($chronicDiseases->isEmpty())
                                        <p class="text-gray-800"> No existen Enfermedades Cronicas.</p>
                                    @else
                                        @foreach ($chronicDiseases as $chronicDisease)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-sm cursor-pointer">
                                                    {{ $chronicDisease->name }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                            </div>

                            {{-- Medicamentos --}}
                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800">

                                        Medicamentos Habituales

                                    </h3>
                                    <a href="javascript:void(0)" onclick="openRegularMedicationsModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>
                                </div>
                                <div class="mt-3 p-5">
                                    @if ($regularMedications->isEmpty())
                                        <p class="text-gray-800"> No existen Medicamentos Habituales.</p>
                                    @else
                                        @foreach ($regularMedications as $regular)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm cursor-pointer">
                                                    {{ $regular->name }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>


                            </div>



                            {{-- Cirugías --}}
                            <div class="border rounded-xl">

                                <div class="flex justify-between items-center px-5 py-3 border rounded-t-xl"
                                    style="background-color: {{ themeColor(50) }}">

                                    <h3 class="font-semibold text-gray-800">

                                        Cirugías Previas

                                    </h3>
                                    <a href="javascript:void(0)" onclick="openPreviousSurgeriesModal()"
                                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                                        style="color: {{ themeColor(600) }}">

                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Editar

                                    </a>
                                </div>

                                <div class="mt-3 p-5">
                                    @if ($previousSurgeries->isEmpty())
                                        <p class="text-gray-800"> No existen Cirugías Previas.</p>
                                    @else
                                        @foreach ($previousSurgeries as $regular)
                                            <div class="relative inline-block group p-1">
                                                <span
                                                    class="inline-flex px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm cursor-pointer">
                                                    {{ $regular->name }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                            </div>

                        </div>

                    </section>