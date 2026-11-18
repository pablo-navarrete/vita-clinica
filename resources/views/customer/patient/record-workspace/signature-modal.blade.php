@once
    <style>
        [data-vita-signing-modal][hidden] { display: none !important; }
    </style>
@endonce
@php($signatureModalId = $signingModalId ?? 'encounter-signature-modal')
<div id="{{ $signatureModalId }}" data-vita-signing-modal hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="{{ $signatureModalId }}-title">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}"><h3 id="{{ $signatureModalId }}-title" class="text-lg font-semibold">{{ $signingTitle ?? 'Firmar y finalizar atención' }}</h3></div>
        <div class="overflow-y-auto px-6 py-5">
            <div data-signing-preview></div>
            <form data-signing-confirm class="mt-5 space-y-4" autocomplete="off">
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="acceptance" value="1" required class="mt-1 rounded border-gray-300"><span>{{ $acceptanceText ?? \App\Services\Signatures\EncounterSigningService::ACCEPTANCE }}</span></label>
                <div><label for="signing-password" class="mb-1 block text-sm font-medium">Confirma tu identidad con tu contraseña</label><input id="signing-password" name="password" type="password" required maxlength="1024" autocomplete="current-password" class="w-full rounded-lg border-gray-300"></div>
                <p data-signing-error role="alert" hidden class="text-sm text-red-600"></p>
                <div class="flex justify-end gap-3"><button data-signing-close type="button" class="rounded-lg border px-4 py-2">Volver a editar</button><button data-signing-submit type="submit" class="rounded-lg px-4 py-2 font-semibold text-white disabled:opacity-50" style="background-color: {{ themeColor(600) }}">{{ $signingTitle ?? 'Firmar y finalizar atención' }}</button></div>
            </form>
        </div>
    </div>
</div>
@once('encounter-signing-script')
@push('scripts')
<script src="{{ asset('js/encounter-signing.js') }}?v={{ filemtime(public_path('js/encounter-signing.js')) }}"></script>
@endpush
@endonce
