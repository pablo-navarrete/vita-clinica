<div>
    <label for="{{ $kind }}-upload" class="mb-2 block text-sm font-medium text-gray-700">{{ $uploadLabel }}</label>
    <label data-signature-dropzone="{{ $kind }}" for="{{ $kind }}-upload"
        class="block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 p-6 text-center transition hover:bg-gray-50 focus-within:ring-2 focus-within:ring-gray-300">
        <input id="{{ $kind }}-upload" data-signature-file="{{ $kind }}" type="file" name="{{ $kind }}" accept="image/png,image/jpeg" class="sr-only">
        <span class="block text-gray-500">
            <i class="fa-solid fa-cloud-arrow-up text-2xl" aria-hidden="true"></i>
            <span class="mt-2 block text-sm">Arrastra una imagen aquí o haz clic</span>
        </span>
        <span data-preview-box="{{ $kind }}" class="mt-3 block">
            <img data-preview="{{ $kind }}" @if($asset) src="{{ route('customers.signature.image', ['customer' => request()->route('customer'), 'uuid' => $asset->uuid]) }}" @else hidden @endif
                alt="{{ $previewLabel }}" class="mx-auto max-h-32 max-w-full rounded object-contain">
            <span data-empty="{{ $kind }}" @if($asset) hidden @endif class="block text-xs text-gray-400">PNG o JPG · Máximo 2 MB</span>
        </span>
    </label>
</div>
