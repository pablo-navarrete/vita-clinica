@extends('layouts.menu-customer')

@section('header')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Editar página: {{ $page->title }}</h2>
            <p class="mt-1 text-sm text-gray-500">Agrega secciones de texto e imágenes y ordénalas como quieras.</p>
        </div>
        <a href="{{ route('customers.menus.index', ['customer' => request()->route('customer')]) }}"
            class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Volver al menú</a>
    </div>
@endsection

@section('content')
    <div id="website-page-admin-editor" class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8"
        data-image-upload-url="{{ route('customers.website-pages.images.store', ['customer' => request()->route('customer'), 'id' => $page->id]) }}">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-1 text-lg font-semibold text-gray-800">@if ($page->slug === 'inicio') Nombre de la p&aacute;gina principal @else Banner y t&iacute;tulo de la p&aacute;gina @endif</h3>
            @unless ($page->slug === 'inicio')
                <p class="mb-5 text-sm text-gray-500">La imagen se mostrará como fondo y el título de la página aparecerá sobre ella.</p>
            @endunless
            @if ($page->slug === 'inicio')
                <p class="mb-5 text-sm text-gray-600">Esta es la p&aacute;gina principal del sitio. Puedes cambiar su nombre aqu&iacute;.</p>
            @endif
            <form method="POST" enctype="multipart/form-data"
                action="{{ route('customers.website-pages.update', ['customer' => request()->route('customer'), 'id' => $page->id]) }}"
                class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="page-title" class="mb-1 block text-sm font-medium text-gray-700">Título</label>
                    <input id="page-title" name="title" value="{{ old('title', $page->title) }}" required maxlength="120"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @if ($page->slug !== 'inicio' && $page->banner_path)
                    <img src="{{ asset('storage/' . $page->banner_path) }}" alt="{{ $page->banner_alt }}"
                        class="max-h-64 w-full rounded-lg object-cover">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remove_banner" value="1" class="rounded border-gray-300">
                        Quitar banner actual
                    </label>
                @endif
                @if ($page->slug !== 'inicio')
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="page-banner" class="mb-1 block text-sm font-medium text-gray-700">{{ $page->banner_path ? 'Cambiar banner' : 'Agregar banner' }}</label>
                        <input id="page-banner" name="banner" type="file" accept="image/*"
                            class="w-full rounded-md border border-gray-300 p-2 text-sm">
                        @error('banner') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="page-banner-alt" class="mb-1 block text-sm font-medium text-gray-700">Descripción de la imagen</label>
                        <input id="page-banner-alt" name="banner_alt" value="{{ old('banner_alt', $page->banner_alt) }}" maxlength="160"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('banner_alt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                @endif
                <button type="submit" class="rounded-md px-4 py-2 font-semibold text-white hover:brightness-90"
                    style="background-color: {{ themeColor(600) }}">{{ $page->slug === 'inicio' ? 'Guardar nombre' : 'Guardar banner' }}</button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-1 text-lg font-semibold text-gray-800">Nueva sección</h3>
            <p class="mb-5 text-sm text-gray-500">Puedes agregar título, texto, imagen o combinar los tres. Las imágenes aceptadas son de hasta 5 MB.</p>

            <form method="POST" enctype="multipart/form-data"
                action="{{ route('customers.website-pages.sections.store', ['customer' => request()->route('customer'), 'id' => $page->id]) }}"
                class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label for="new-title" class="mb-1 block text-sm font-medium text-gray-700">Título de sección</label>
                    <input id="new-title" name="title" value="{{ old('title') }}" maxlength="160"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-position" class="mb-1 block text-sm font-medium text-gray-700">Orden</label>
                    <input id="new-position" name="position" type="number" min="0" max="999" value="{{ old('position', $page->sections->count()) }}" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-image-position" class="mb-1 block text-sm font-medium text-gray-700">Ubicación de imagen</label>
                    <select id="new-image-position" name="image_position" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="inline">En el texto</option>
                        <option value="top">Al inicio, texto abajo</option>
                        <option value="left">Izquierda, texto a la derecha</option>
                        <option value="right">Derecha, texto a la izquierda</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="new-body" class="mb-1 block text-sm font-medium text-gray-700">Texto</label>
                    <textarea id="new-body" name="body" rows="5" maxlength="50000"
                        class="website-rich-editor w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                    <button type="button" data-add-visual="new-body" class="mt-2 rounded-md border px-3 py-2 text-sm">+ Tarjeta o círculo</button>
                    @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-image" class="mb-1 block text-sm font-medium text-gray-700">Fotografía (opcional)</label>
                    <input id="new-image" name="image" type="file" accept="image/*"
                        class="w-full rounded-md border border-gray-300 p-2 text-sm">
                    @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-image-alt" class="mb-1 block text-sm font-medium text-gray-700">Descripción de la fotografía</label>
                    <input id="new-image-alt" name="image_alt" value="{{ old('image_alt') }}" maxlength="160"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Describe brevemente la imagen">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-md px-4 py-2 font-semibold text-white hover:brightness-90"
                        style="background-color: {{ themeColor(600) }}">
                        <i class="fa-solid fa-plus mr-1"></i> Agregar sección
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">Secciones de la página ({{ $page->sections->count() }})</h3>
            @forelse ($page->sections as $section)
                <article class="rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" enctype="multipart/form-data"
                        action="{{ route('customers.website-pages.sections.update', ['customer' => request()->route('customer'), 'id' => $page->id, 'sectionId' => $section->id]) }}"
                        class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="title-{{ $section->id }}" class="mb-1 block text-sm font-medium text-gray-700">Título</label>
                            <input id="title-{{ $section->id }}" name="title" value="{{ $section->title }}" maxlength="160"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="position-{{ $section->id }}" class="mb-1 block text-sm font-medium text-gray-700">Orden</label>
                            <input id="position-{{ $section->id }}" name="position" type="number" min="0" max="999" value="{{ $section->position }}" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="image-position-{{ $section->id }}" class="mb-1 block text-sm font-medium text-gray-700">Ubicación de imagen</label>
                            <select id="image-position-{{ $section->id }}" name="image_position" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="inline" @selected(($section->image_position ?? 'inline') === 'inline')>En el texto</option>
                                <option value="top" @selected(($section->image_position ?? 'inline') === 'top')>Al inicio, texto abajo</option>
                                <option value="left" @selected(($section->image_position ?? 'inline') === 'left')>Izquierda, texto a la derecha</option>
                                <option value="right" @selected(($section->image_position ?? 'inline') === 'right')>Derecha, texto a la izquierda</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="body-{{ $section->id }}" class="mb-1 block text-sm font-medium text-gray-700">Texto</label>
                            <textarea id="body-{{ $section->id }}" name="body" rows="5" maxlength="50000"
                                class="website-rich-editor w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $section->body }}</textarea>
                            <button type="button" data-add-visual="body-{{ $section->id }}" class="mt-2 rounded-md border px-3 py-2 text-sm">+ Tarjeta o círculo</button>
                        </div>
                        @if ($section->image_path)
                            <div class="md:col-span-2">
                                <img src="{{ asset('storage/' . $section->image_path) }}" alt="{{ $section->image_alt }}"
                                    class="max-h-56 rounded-lg object-cover">
                                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300">
                                    Quitar fotografía actual
                                </label>
                            </div>
                        @endif
                        <div>
                            <label for="image-{{ $section->id }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $section->image_path ? 'Cambiar fotografía' : 'Agregar fotografía' }}</label>
                            <input id="image-{{ $section->id }}" name="image" type="file" accept="image/*"
                                class="w-full rounded-md border border-gray-300 p-2 text-sm">
                        </div>
                        <div>
                            <label for="alt-{{ $section->id }}" class="mb-1 block text-sm font-medium text-gray-700">Descripción de la fotografía</label>
                            <input id="alt-{{ $section->id }}" name="image_alt" value="{{ $section->image_alt }}" maxlength="160"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="flex flex-wrap justify-end gap-2 border-t pt-4 md:col-span-2">
                            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Guardar sección</button>
                    </form>
                            <form method="POST" action="{{ route('customers.website-pages.sections.destroy', ['customer' => request()->route('customer'), 'id' => $page->id, 'sectionId' => $section->id]) }}"
                                data-swal-confirm="¿Eliminar esta sección y su fotografía?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md bg-red-100 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-200">Eliminar sección</button>
                            </form>
                        </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    Esta página está en blanco. Agrega arriba tu primera sección.
                </div>
            @endforelse
        </section>

        <div class="pb-6 text-right">
            <a href="{{ $page->slug === 'inicio' ? request()->getSchemeAndHttpHost() . '/' : route('customer.website-pages.show', ['customer' => request()->route('customer'), 'slug' => $page->slug]) }}" target="_blank" rel="noopener"
                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Ver página pública</a>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .website-card-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:1rem; margin:1.25rem 0; }
        .website-card { min-height:150px; padding:1.25rem; border:1px solid #e5e7eb; border-radius:.75rem; background:#fff; text-align:center; box-shadow:0 4px 14px #0000000d; }
        .website-card-circle { display:flex; width:100%; max-width:260px; aspect-ratio:1; flex-direction:column; align-items:center; justify-content:center; justify-self:center; border-radius:50%; }
        .website-card-icon { display:inline-flex; width:3.25rem; height:3.25rem; align-items:center; justify-content:center; margin-bottom:.75rem; border-radius:9999px; background:{{ themeColor(50) }}; color:{{ themeColor(700) }}; font-size:1.6rem; }
        .website-card-image { display:block; width:4.5rem; height:4.5rem; margin:0 auto .75rem; border-radius:50%; object-fit:cover; }
        .website-card h3 { margin:0 0 .5rem; font-size:1.1rem; font-weight:600; }
        .website-card p { margin:0; color:#4b5563; line-height:1.6; }
        .website-card-button { display:inline-block; margin-top:1rem; border-radius:.375rem; background:{{ themeColor(600) }}; padding:.55rem 1rem; color:#fff; font-weight:600; text-decoration:none; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/lang/summernote-es-ES.min.js"></script>
    <script>
        (() => {
            const root = document.getElementById('website-page-admin-editor');
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const icons = [
                'fa-stethoscope','fa-heart','fa-user-doctor','fa-hand-holding-heart','fa-house-medical','fa-brain','fa-star','fa-shield-heart',
                'fa-calendar-check','fa-comments','fa-spa','fa-tooth','fa-hospital','fa-user-nurse','fa-kit-medical','fa-pills','fa-syringe','fa-dna',
                'fa-eye','fa-ear-listen','fa-baby','fa-person','fa-people-group','fa-wheelchair','fa-notes-medical','fa-file-medical','fa-prescription',
                'fa-handshake','fa-leaf','fa-lightbulb','fa-award','fa-clock','fa-location-dot','fa-phone','fa-envelope','fa-circle-check'
            ];
            const upload = async (file) => {
                const form = new FormData(); form.append('image', file);
                const response = await fetch(root.dataset.imageUploadUrl, {method:'POST', body:form, headers:{'X-CSRF-TOKEN':token, 'Accept':'application/json'}});
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'No se pudo subir la imagen.');
                return result.url;
            };
            $('.website-rich-editor').summernote({
                lang:'es-ES', height:220, dialogsInBody:true,
                toolbar:[['style',['style']],['font',['bold','italic','underline','strikethrough','clear']],['fontname',['fontname']],['fontsize',['fontsize']],['color',['color']],['para',['ul','ol','paragraph']],['insert',['link','picture','table']],['view',['fullscreen','codeview']]],
                callbacks:{onImageUpload:function(files) { const editor=$(this); [...files].forEach(async (file) => { try { editor.summernote('insertImage', await upload(file)); } catch (error) { Swal.fire({icon:'error',title:'No se pudo subir la imagen',text:error.message}); } }); }}
            });

            document.querySelectorAll('[data-add-visual]').forEach((button) => button.addEventListener('click', async () => {
                const textarea = document.getElementById(button.dataset.addVisual);
                const html = '<select id="visual-shape" class="swal2-select"><option value="card">Tarjeta</option><option value="circle">Círculo</option></select><select id="visual-media" class="swal2-select"><option value="icon">Icono</option><option value="image">Imagen</option></select><div id="visual-icons" class="icon-picker-grid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:.5rem;margin:.75rem 1.5rem"></div><input id="visual-icon" type="hidden" value="fa-stethoscope"><input id="visual-image" type="file" accept="image/*" class="swal2-file" style="display:none"><input id="visual-title" class="swal2-input" placeholder="Título o nombre"><textarea id="visual-copy" class="swal2-textarea" placeholder="Descripción"></textarea><label style="display:block;margin:0 1.5rem;text-align:left"><input id="visual-button-toggle" type="checkbox"> Agregar botón con enlace</label><input id="visual-button-text" class="swal2-input" placeholder="Texto del botón" style="display:none"><input id="visual-button-url" class="swal2-input" placeholder="Enlace (https://... o /ruta)" style="display:none">';
                const result = await Swal.fire({
                    title:'Agregar elemento visual', html, showCancelButton:true, confirmButtonText:'Agregar', cancelButtonText:'Cancelar', focusConfirm:false,
                    didOpen:() => {
                        const picker = document.getElementById('visual-icons'); const iconValue = document.getElementById('visual-icon');
                        icons.forEach((name) => { const option=document.createElement('button'); option.type='button'; option.title=name.replace('fa-','').replaceAll('-',' '); option.setAttribute('aria-label',option.title); option.style.cssText='height:42px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer'; const glyph=document.createElement('i'); glyph.className=`fa-solid ${name}`; option.append(glyph); option.onclick=() => {iconValue.value=name; picker.querySelectorAll('button').forEach((item)=>item.style.background='#fff'); option.style.background='{{ themeColor(100) }}';}; picker.append(option); if(name===iconValue.value) option.style.background='{{ themeColor(100) }}'; });
                        const media=document.getElementById('visual-media'), image=document.getElementById('visual-image');
                        const syncMedia=()=>{picker.style.display=media.value==='icon'?'grid':'none'; image.style.display=media.value==='image'?'':'none';}; media.onchange=syncMedia; syncMedia();
                        const toggle=document.getElementById('visual-button-toggle'); toggle.onchange=()=>{document.getElementById('visual-button-text').style.display=toggle.checked?'':'none'; document.getElementById('visual-button-url').style.display=toggle.checked?'':'none';};
                    },
                    preConfirm:() => {
                        const title=document.getElementById('visual-title').value.trim(), media=document.getElementById('visual-media').value, image=document.getElementById('visual-image').files[0];
                        const hasButton=document.getElementById('visual-button-toggle').checked, buttonText=document.getElementById('visual-button-text').value.trim(), buttonUrl=document.getElementById('visual-button-url').value.trim();
                        if(!title) {Swal.showValidationMessage('Escribe un título.');return false;}
                        if(media==='image'&&!image) {Swal.showValidationMessage('Selecciona una imagen.');return false;}
                        if(hasButton&&(!buttonText||!buttonUrl)) {Swal.showValidationMessage('Completa el texto y el enlace del botón.');return false;}
                        if(hasButton&&!/^(https?:\/\/|\/|#|mailto:)/i.test(buttonUrl)) {Swal.showValidationMessage('Usa una URL http(s), ruta local o mailto:.');return false;}
                        return {shape:document.getElementById('visual-shape').value,media,image,icon:document.getElementById('visual-icon').value,title,copy:document.getElementById('visual-copy').value.trim(),hasButton,buttonText,buttonUrl};
                    }
                });
                if(!result.isConfirmed) return;
                try {
                    let mediaUrl=null; if(result.value.media==='image') mediaUrl=await upload(result.value.image);
                    const card=document.createElement('div'); card.className=`website-card${result.value.shape==='circle'?' website-card-circle':''}`;
                    if(mediaUrl){const image=document.createElement('img');image.className='website-card-image';image.src=mediaUrl;image.alt=result.value.title;card.append(image);}
                    else {const holder=document.createElement('span');holder.className='website-card-icon';const glyph=document.createElement('i');glyph.className=`fa-solid ${result.value.icon}`;holder.append(glyph);card.append(holder);}
                    const title=document.createElement('h3');title.textContent=result.value.title;const copy=document.createElement('p');copy.textContent=result.value.copy;card.append(title,copy);
                    if(result.value.hasButton){const link=document.createElement('a');link.className='website-card-button';link.href=result.value.buttonUrl;link.textContent=result.value.buttonText;card.append(link);}
                    const editor=$(textarea);let grid=editor.next('.note-editor').find('.note-editable .website-card-grid').get(0);
                    if(!grid){editor.summernote('pasteHTML','<div class="website-card-grid"></div><p><br></p>');grid=editor.next('.note-editor').find('.note-editable .website-card-grid').get(0);}
                    grid.append(card);
                } catch(error) { Swal.fire({icon:'error',title:'No se pudo agregar',text:error.message}); }
            }));
        })();

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-swal-confirm]');
            if (!form) return;

            event.preventDefault();
            const result = await Swal.fire({
                title: '¿Eliminar sección?',
                text: form.dataset.swalConfirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626'
            });

            if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form);
        });
    </script>
@endpush
