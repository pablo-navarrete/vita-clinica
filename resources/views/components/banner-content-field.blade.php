<div class="min-w-0 {{ $fieldClass ?? '' }}">
    <label for="image_only" class="mb-1 block text-sm font-medium text-gray-700">Contenido del banner</label>
    <select id="image_only" name="image_only" class="vita-banner-select w-full rounded-md border-gray-300 shadow-sm">
        <option value="0" @selected(!(bool) $imageOnly)>Imagen con textos y botón</option>
        <option value="1" @selected((bool) $imageOnly)>Solo imagen (sin textos ni botón)</option>
    </select>
    <p class="mt-1 text-xs text-gray-500">En “Solo imagen”, el título es opcional y sirve para identificar el banner en administración.</p>
    @error('image_only')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
@once
    @push('styles')
        <style>
            select.vita-banner-select{appearance:none;-webkit-appearance:none;display:block;box-sizing:border-box;width:100%;min-width:0;min-height:46px;padding:11px 40px 11px 14px;border:1px solid #dbe3ed;border-radius:10px;background-color:#fff;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='1.8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m7 10 5 5 5-5'/%3E%3C/svg%3E");background-position:right 13px center;background-repeat:no-repeat;background-size:18px;color:#334155;font-family:inherit;font-size:14px;font-weight:500;line-height:1.5;box-shadow:0 1px 2px #0f172a05;cursor:pointer;transition:border-color .15s,box-shadow .15s}
            select.vita-banner-select:hover{border-color:{{ themeColor(300) }}}
            select.vita-banner-select:focus{outline:none;border-color:{{ themeColor(600) }};box-shadow:0 0 0 3px {{ themeColor(100) }};--tw-ring-shadow:0 0 #0000}
            select.vita-banner-select:disabled{background-color:#f8fafc;color:#94a3b8;cursor:not-allowed}
            @media(prefers-reduced-motion:reduce){select.vita-banner-select{transition:none}}
        </style>
    @endpush
@endonce
@push('scripts')
    <script>
        (() => {
            const mode = document.getElementById('image_only');
            const title = mode?.form?.querySelector('[name="title"]');
            if (!mode || !title) return;
            const update = () => { title.required = mode.value !== '1'; };
            mode.addEventListener('change', update);
            update();
        })();
    </script>
@endpush
