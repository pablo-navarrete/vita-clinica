                <div
                    id="patient-modal" x-data="{form: VitaIdentification.defaults(), errors:{}, isEdit:false, identityLoading:false, identityFailed:false}"
                    class="hidden fixed inset-0 items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

                    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">

                        <!-- HEADER -->

                        <div
                            class="px-6 py-4 border-b rounded-t-lg"
                            style="background-color: {{ themeColor(100) }}">

                            <h2
                                id="patient-modal-title"
                                class="text-lg font-bold">

                                Nuevo Paciente

                            </h2>

                        </div>


                        <!-- BODY -->

                        <div class="p-6 space-y-4 overflow-y-auto flex-1">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                                <!-- NOMBRE -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre Completo *
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-name"
                                        name="name"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-name"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                @include('components.identification-fields', ['kind'=>'patient'])

                                <!-- FECHA NACIMIENTO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha Nacimiento
                                    </label>

                                    <input
                                        type="date"
                                        id="patient-birth-date"
                                        name="birth_date"
                                        max="{{ now()->format('Y-m-d') }}"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-birth-date"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- SEXO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Sexo
                                    </label>

                                    <select
                                        id="patient-gender"
                                        name="gender"
                                        class="w-full rounded-lg border-gray-300">

                                        <option value="Masculino">
                                            Masculino
                                        </option>

                                        <option value="Femenino">
                                            Femenino
                                        </option>

                                        <option value="Otro">
                                            Otro
                                        </option>

                                    </select>

                                </div>


                                <!-- TELÉFONO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Teléfono
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-phone"
                                        name="phone"
                                        maxlength="20"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-phone"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- EMAIL -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Correo Electrónico
                                    </label>

                                    <input
                                        type="email"
                                        id="patient-email"
                                        name="email"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-email"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- DIRECCIÓN -->

                                <div class="md:col-span-2">

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Dirección
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-address"
                                        name="address"
                                        maxlength="255"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-address"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- PREVISIÓN -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Previsión
                                    </label>

                                    <select
                                        id="patient-insurance"
                                        name="insurance_id"
                                        class="w-full rounded-lg border-gray-300">

                                        @foreach ($insurances as $insurance)

                                            <option value="{{ $insurance->id }}">
                                                {{ $insurance->name }}
                                            </option>

                                        @endforeach

                                    </select>

                                </div>


                            </div>

                        </div>


                        <!-- FOOTER -->

                        <div class="px-6 py-4 border-t flex justify-end gap-2">

                            <button
                                type="button"
                                id="patient-modal-cancel"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                                Cancelar

                            </button>


                            <button
                                type="button"
                                id="patient-modal-save"
                                style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                                Guardar

                            </button>

                        </div>

                    </div>

                </div>


