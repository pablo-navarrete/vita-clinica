<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Editar página: {{ $page['name'] }}</h2></x-slot>
    <div class="p-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            
            @if($errors->any())<div role="alert" class="rounded-md bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif
            <form method="POST" enctype="multipart/form-data" action="{{ route('superadmin.website-pages.update', $page['id']) }}"
                x-data="vitaPageEditor(@js(old('sections', $page['sections'])))" class="space-y-6">
                @csrf @method('PUT')
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-800">Contenido de la página</h3>
                        <div class="flex gap-3"><a href="{{ route('superadmin.website.section', ['section' => 'menus']) }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700">Volver al menú</a><a href="{{ url($page['url']) }}" target="_blank" rel="noopener" class="rounded-md border px-4 py-2 text-sm">Ver página</a></div>
                    </div>
                    <label for="page-title" class="mb-1 block text-sm font-medium text-gray-700">Título de la página y nombre en el menú</label>
                    <input id="page-title" name="title" required maxlength="120" value="{{ old('title', $page['name']) }}" class="w-full rounded-md border-gray-300 shadow-sm">
                    <p class="mt-2 text-xs text-gray-500">Dirección: {{ $page['url'] }} · {{ $page['status'] ? 'Activa' : 'Inactiva' }}</p>
                </section>
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-lg font-semibold text-gray-800">Secciones, textos e imágenes</h3><p class="mt-1 text-sm text-gray-500">Agrega contenido y cambia el orden de las secciones.</p></div><button type="button" @click="add()" :disabled="sections.length >= 30" class="rounded-md px-4 py-2 font-semibold text-white disabled:opacity-50" style="background-color: {{ themeColor(600) }}"><i class="fa-solid fa-plus mr-1"></i> Agregar sección</button></div>
                    <p x-show="!sections.length" class="rounded-md border border-dashed p-8 text-center text-gray-500">Esta página está en blanco. Agrega una sección para comenzar.</p>
                    <div class="space-y-5">
                        <template x-for="(section, index) in sections" :key="section.id">
                            <div class="rounded-lg border border-gray-200 p-5">
                                <input type="hidden" :name="`sections[${index}][id]`" :value="section.id">
                                <div class="mb-4 flex items-center justify-between gap-2"><h4 class="font-semibold text-gray-800" x-text="`Sección ${index + 1}`"></h4><div class="flex gap-3"><button type="button" @click="move(index, -1)" :disabled="index === 0" class="text-gray-500 disabled:opacity-30" title="Subir sección"><i class="fa-solid fa-arrow-up"></i></button><button type="button" @click="move(index, 1)" :disabled="index === sections.length - 1" class="text-gray-500 disabled:opacity-30" title="Bajar sección"><i class="fa-solid fa-arrow-down"></i></button><button type="button" @click="sections.splice(index, 1)" class="text-sm text-red-600">Eliminar</button></div></div>
                                <label class="mb-4 block text-sm font-medium text-gray-700">Título<input :name="`sections[${index}][title]`" x-model="section.title" maxlength="160" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></label>
                                <label class="mb-4 block text-sm font-medium text-gray-700">Texto<textarea :name="`sections[${index}][body]`" x-model="section.body" rows="5" maxlength="20000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea></label>
                                <template x-if="section.image_path"><div class="mb-4"><img :src="storageBase + section.image_path" :alt="section.image_alt || ''" class="mb-2 max-h-48 rounded-lg object-contain"><label class="text-sm text-gray-600"><input type="checkbox" :name="`sections[${index}][remove_image]`" value="1" x-model="section.remove_image" class="mr-2 rounded border-gray-300">Quitar imagen actual</label></div></template>
                                <label class="mb-4 block text-sm font-medium text-gray-700">Imagen<input type="file" :name="`sections[${index}][image]`" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm"><span class="mt-1 block text-xs font-normal text-gray-500">JPG, PNG o WEBP. Máximo 10 MB. Seleccionar una imagen reemplaza la actual.</span></label>
                                <label class="block text-sm font-medium text-gray-700">Descripción de la imagen<input :name="`sections[${index}][image_alt]`" x-model="section.image_alt" maxlength="160" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></label>
                            </div>
                        </template>
                    </div>
                    <div class="mt-6 flex justify-end border-t pt-5"><button type="submit" class="rounded-md px-5 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}"><i class="fa-solid fa-floppy-disk mr-1"></i> Guardar cambios</button></div>
                </section>
            </form>
        </div>
    </div>
    @push('scripts')
        <script>
            function vitaPageEditor(sections) {
                return {
                    sections: Object.values(sections || []), storageBase: @js(asset('storage') . '/'),
                    add() {
                        if (this.sections.length >= 30) return;
                        const id = '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (Number(c) ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> Number(c) / 4).toString(16));
                        this.sections.push({id, title: '', body: '', image_alt: '', image_path: null});
                    },
                    move(index, direction) {
                        const next = index + direction;
                        if (next >= 0 && next < this.sections.length) this.sections.splice(next, 0, this.sections.splice(index, 1)[0]);
                    }
                };
            }
        </script>
    @endpush
</x-app-layout>
