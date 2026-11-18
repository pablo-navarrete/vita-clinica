<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $definition['title'] }}</h2><p class="mt-1 text-sm text-gray-500">Administración web de VITA</p></x-slot>
    <div class="vita-web-section p-6 sm:p-10">
        <style>.vita-web-section .field{display:block;width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}.vita-web-section label{display:block;margin-bottom:7px;font-size:13px;font-weight:600;color:#475569}.vita-web-section .field:focus{border-color:#2563eb;--tw-ring-color:#2563eb}</style>
        
        @if($errors->any())<div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="mb-6 flex flex-wrap justify-end gap-3"><a href="{{ route('superadmin.website.preview') }}" target="_blank" rel="noopener" class="rounded-lg border bg-white px-4 py-2 text-sm">Vista previa guardada</a><a href="{{ route('vita.website') }}" target="_blank" rel="noopener" class="rounded-lg border bg-white px-4 py-2 text-sm"><i class="fa-solid fa-globe mr-1"></i>Ver web</a></div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('superadmin.website.section.update', ['section' => $section]) }}">
            @csrf @method('PUT')
            <section class="mb-6 rounded-xl bg-white p-6 shadow-sm"><div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach($definition['fields'] as $name => $label)
                    <div @class(['md:col-span-2' => str_ends_with($name, 'description')])><label for="{{ $name }}">{{ $label }}</label>
                        @if(in_array($name, ['published', 'topbar_enabled']))
                            <select class="field" id="{{ $name }}" name="{{ $name }}"><option value="1" @selected(old($name, $site[$name]) == 1)>{{ $name === 'published' ? 'Publicado' : 'Mostrar' }}</option><option value="0" @selected(old($name, $site[$name]) == 0)>{{ $name === 'published' ? 'En mantenimiento' : 'Ocultar' }}</option></select>
                        @elseif(str_ends_with($name, 'description'))<textarea class="field" id="{{ $name }}" name="{{ $name }}" rows="4" required>{{ old($name, $site[$name]) }}</textarea>
                        @else<input class="field" id="{{ $name }}" name="{{ $name }}" type="{{ $name === 'contact_email' ? 'email' : 'text' }}" value="{{ old($name, $site[$name]) }}">@endif
                    </div>
                @endforeach
                @if(in_array($section, ['logo', 'banners']))
                    @php($asset = $section === 'logo' ? 'logo' : 'banner')
                    <div class="md:col-span-2"><label for="{{ $asset }}">{{ $section === 'logo' ? 'Logo' : 'Imagen principal del banner' }} (PNG, JPG o WebP, hasta 4 MB)</label><input class="field" type="file" id="{{ $asset }}" name="{{ $asset }}" accept="image/png,image/jpeg,image/webp">@if($site[$asset . '_path'])<img class="mt-4 max-h-48 rounded-lg object-contain" src="{{ asset('storage/' . $site[$asset . '_path']) }}" alt="Imagen actual"><label class="mt-3"><input type="checkbox" name="remove_asset" value="1"> Quitar imagen actual</label>@endif</div>
                @endif
            </div></section>
            @foreach($definition['collections'] ?? [] as $key => $collection)
                @php($initialRows = array_values(old($key, $site[$key]) ?? []))
                @if($key === 'posts')
                    @php($initialRows = array_map(fn ($row) => array_replace($row, ['published' => !empty($row['published']) ? '1' : '0']), $initialRows))
                @endif
                <section class="mb-6 rounded-xl bg-white p-6 shadow-sm" x-data="{ rows: @js($initialRows), emptyRow: @js(array_fill_keys(array_keys($collection['fields']), '')) }">
                    <h3 class="mb-5 font-semibold text-gray-800">{{ $collection['title'] }}</h3>
                    @if($key === 'menus')<p class="mb-4 text-sm text-gray-500">Puedes usar anclas como #funcionalidades, #preguntas, #blog o #contacto, rutas locales o direcciones HTTP/HTTPS. El orden de la lista es el orden del menú.</p>@endif
                    <template x-for="(row, index) in rows" :key="index"><div class="mb-4 space-y-4 rounded-lg border border-gray-200 p-4">
                        @foreach($collection['fields'] as $field => $label)
                            <div><label :for="'{{ $key }}-{{ $field }}-' + index">{{ $label }}</label>
                            @if($field === 'published')<select class="field" :id="'{{ $key }}-{{ $field }}-' + index" :name="'{{ $key }}[' + index + '][{{ $field }}]'" x-model="row.{{ $field }}"><option value="1">Publicado</option><option value="0">Borrador</option></select>
                            @elseif(in_array($field, ['description', 'answer', 'excerpt', 'content']))<textarea class="field" :id="'{{ $key }}-{{ $field }}-' + index" :name="'{{ $key }}[' + index + '][{{ $field }}]'" x-model="row.{{ $field }}" rows="{{ $field === 'content' ? 8 : 3 }}" required></textarea>
                            @else<input class="field" :id="'{{ $key }}-{{ $field }}-' + index" :name="'{{ $key }}[' + index + '][{{ $field }}]'" x-model="row.{{ $field }}" required>@endif</div>
                        @endforeach
                        <div class="flex flex-wrap gap-4 text-sm"><button type="button" class="text-gray-600" :disabled="index === 0" @click="const previous = rows[index - 1]; rows[index - 1] = rows[index]; rows[index] = previous">Subir</button><button type="button" class="text-gray-600" :disabled="index === rows.length - 1" @click="const next = rows[index + 1]; rows[index + 1] = rows[index]; rows[index] = next">Bajar</button><button type="button" class="text-red-600" @click="rows.splice(index, 1)" @if($key === 'features') x-show="rows.length > 1" @endif>Eliminar</button></div>
                    </div></template>
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm text-blue-700" x-show="rows.length < {{ $key === 'posts' ? 50 : 12 }}" @click="rows.push({ ...emptyRow, @if($key === 'posts') published: '0' @endif })">Agregar</button>
                </section>
            @endforeach
            <div class="flex justify-end"><button type="submit" class="rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800"><i class="fa-solid fa-floppy-disk mr-2"></i>Guardar cambios</button></div>
        </form>
    </div>
</x-app-layout>
