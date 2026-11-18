<div class="min-w-0 {{ $fieldClass ?? '' }}">
    <label for="dark_overlay" class="mb-1 block text-sm font-medium text-gray-700">Apariencia de la imagen</label>
    <select id="dark_overlay" name="dark_overlay" class="vita-banner-select w-full rounded-md border-gray-300 shadow-sm">
        <option value="1" @selected((bool) $darkOverlay)>Con filtro oscuro</option>
        <option value="0" @selected(!(bool) $darkOverlay)>Imagen normal (sin filtro)</option>
    </select>
    <p class="mt-1 text-xs text-gray-500">El filtro oscuro ayuda a leer el texto sobre la imagen.</p>
    @error('dark_overlay')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
