<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $alignmentFieldClass }}">
    @foreach(['text_alignment' => 'Alineación del texto', 'button_alignment' => 'Alineación del botón'] as $field => $label)
        <div class="min-w-0">
            <label for="{{ $field }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
            <select id="{{ $field }}" name="{{ $field }}" class="vita-banner-select">
                @foreach(['left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha'] as $value => $text)
                    <option value="{{ $value }}" @selected(old($field, isset($banner) ? ($banner->{$field} ?? 'center') : 'center') === $value)>{{ $text }}</option>
                @endforeach
            </select>
            @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endforeach
</div>
