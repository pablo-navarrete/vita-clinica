<section id="electronic-signature" class="mt-8 overflow-hidden rounded-xl bg-white shadow-sm">
    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
        <h3 class="font-semibold text-gray-800"><i class="fa-solid fa-signature mr-2" aria-hidden="true"></i>Firma electrónica</h3>
        <p class="mt-1 text-sm text-gray-600">Configura tu firma visual y timbre profesional para esta clínica.</p>
    </div>
    <form data-signature-configuration action="{{ route('customers.signature.update', ['customer' => request()->route('customer')]) }}" method="POST" enctype="multipart/form-data" class="space-y-6 p-6">
        @csrf
        <div class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm sm:grid-cols-2">
            <div><span class="block text-gray-500">Profesional</span><strong class="break-words text-gray-800">{{ $user->name }}</strong></div>
            <div><span class="block text-gray-500">Identificaci?n documental</span><strong class="text-gray-800">{{ app(\App\Services\Signatures\SignerIdentitySnapshot::class)->label(app(\App\Services\Signatures\SignerIdentitySnapshot::class)->capture($user, false)) }}</strong></div>
            <div class="sm:col-span-2"><span class="block text-gray-500">Especialidades</span><span class="text-gray-800">{{ $signatureSpecialties->pluck('name')->join(' · ') ?: 'Sin especialidades asignadas' }}</span></div>
        </div>
        <div>
            <span class="mb-1 block text-sm font-medium text-gray-700">Método disponible</span>
            <p class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color: {{ themeColor(200) }}; color: {{ themeColor(700) }}"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>{{ $signatureMethods['fes_vita']->label() }}</p>
            <input type="hidden" name="method" value="fes_vita">
            @error('method')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="min-w-0 space-y-3">
                <h4 class="font-semibold text-gray-800">Tu firma visual</h4>
                <p id="signature-canvas-help" class="text-sm text-gray-500">Dibuja con mouse, lápiz o el dedo, o selecciona una imagen.</p>
                <div class="overflow-hidden rounded-lg border border-dashed border-gray-300 bg-white">
                    <canvas data-signature-canvas width="1000" height="320" aria-label="Área para dibujar tu firma" aria-describedby="signature-canvas-help" class="block w-full" style="height: 180px; touch-action: none; cursor: crosshair;"></canvas>
                </div>
                <button data-clear-canvas type="button" class="text-sm font-medium text-gray-600 underline hover:text-gray-900">Limpiar dibujo</button>
                @include('customer.user.partials.signature-upload', ['kind' => 'signature', 'asset' => $signatureProfile?->signatureAsset, 'uploadLabel' => 'Subir o reemplazar firma', 'previewLabel' => 'Vista previa de tu firma visual'])
                @error('signature')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="inline-flex items-center gap-2 text-sm text-red-600"><input data-remove="signature" type="checkbox" name="remove_signature" value="1" class="rounded border-gray-300">Eliminar firma de mi configuración</label>
                @error('remove_signature')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="min-w-0 space-y-3">
                <h4 class="font-semibold text-gray-800">Timbre profesional <span class="text-sm font-normal text-gray-500">(opcional)</span></h4>
                <p class="text-sm text-gray-500">Puedes añadir una imagen de tu sello, independiente de tu firma.</p>
                @include('customer.user.partials.signature-upload', ['kind' => 'stamp', 'asset' => $signatureProfile?->stampAsset, 'uploadLabel' => 'Subir o reemplazar timbre', 'previewLabel' => 'Vista previa de tu timbre profesional'])
                @error('stamp')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="inline-flex items-center gap-2 text-sm text-red-600"><input data-remove="stamp" type="checkbox" name="remove_stamp" value="1" class="rounded border-gray-300">Eliminar timbre de mi configuración</label>
                @error('remove_stamp')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <p class="text-xs text-gray-500">Máximo 2 MB por imagen. Dimensiones: de 32 × 16 a 2400 × 1600 píxeles. La configuración se comparte entre tus sedes de esta clínica. Los reemplazos y retiros conservan las versiones anteriores protegidas.</p>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i>Esta imagen es una representación visual. Guardarla no firma atenciones ni acredita por sí sola una firma electrónica. La firma de documentos requerirá aceptación explícita y verificación de identidad en una etapa posterior.</div>
        <p data-signature-error role="alert" hidden class="text-sm text-red-600"></p>
        <div class="flex justify-end"><button data-signature-save type="submit" class="rounded-lg px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-90 disabled:opacity-50" style="background-color: {{ themeColor(600) }}"><i class="fa-solid fa-floppy-disk mr-2" aria-hidden="true"></i>Guardar firma electrónica</button></div>
    </form>
</section>
@push('scripts')
    <script src="{{ asset('js/professional-signature.js') }}?v={{ filemtime(public_path('js/professional-signature.js')) }}"></script>
@endpush
