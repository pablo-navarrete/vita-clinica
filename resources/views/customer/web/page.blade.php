<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->title }} | {{ app('customer')->name }}</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    @include('customer.web.theme-colors')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        [hidden], .hidden { display: none !important; }
        body { background: #fff; font-family: Poppins, sans-serif; }
        .site-page-content { min-height: 55vh; padding: 0 1rem 3rem; }
        .site-page-banner { position: relative; display: flex; width: 100vw; min-height: 320px; align-items: center; justify-content: center; margin: 0 calc(50% - 50vw) 3rem; padding: 2rem 1rem; background: {{ themeColor(700) }} center/cover no-repeat; }
        .site-page-banner::before { position: absolute; inset: 0; background: rgba(0,0,0,.42); content: ''; }
        .site-page-title { position: relative; z-index: 1; max-width: 900px; margin: 0; color: #fff; text-align: center; font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 700; text-shadow: 0 2px 8px rgba(0,0,0,.35); }
        .site-page-section { position: relative; box-sizing: border-box; width: 100vw; min-height: 180px; margin: 0 calc(50% - 50vw) 2rem; border: 2px solid transparent; border-radius: 0; padding: 2rem max(1rem, calc((100vw - 1100px) / 2)); }
        .site-page-section:nth-child(even) { background-color: {{ themeColor(50) }}; }
        .inline-edit-icon { position: absolute; z-index: 3; display: inline-flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; border: 1px solid #d1d5db; border-radius: 9999px; background: #fff; color: #374151; box-shadow: 0 2px 8px #0002; cursor: pointer; }
        .banner-edit-icon { top: 1rem; right: 1rem; }
        .section-edit-icon { top: .5rem; right: .5rem; }
        .inline-edit-icon:hover { background: #f3f4f6; color: {{ themeColor(700) }}; }
        .editing-page .inline-edit-icon { display: none; }
        .site-page-section h2 { margin: 0 0 1rem; font-size: 1.75rem; font-weight: 600; }
        .site-page-section > .section-image { display: block; max-width: 100%; max-height: 620px; margin: 1.5rem auto; object-fit: contain; }
        .section-body img { max-width: 100%; height: auto; }
        .section-body .website-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin: 1.25rem 0; }
        .section-body .website-card { min-height: 150px; padding: 1.25rem; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; text-align: center; box-shadow: 0 4px 14px #0000000d; }
        .section-body .website-card-circle { display: flex; width: 100%; max-width: 260px; aspect-ratio: 1; flex-direction: column; align-items: center; justify-content: center; justify-self: center; border-radius: 50%; text-align: center; }
        .section-body .website-card-icon { display: inline-flex; width: 3.25rem; height: 3.25rem; align-items: center; justify-content: center; margin-bottom: .75rem; border-radius: 9999px; background: {{ themeColor(50) }}; color: {{ themeColor(700) }}; font-size: 1.6rem; }
        .section-body .website-card-image { display: block; width: 4.5rem; height: 4.5rem; margin: 0 auto .75rem; border-radius: 50%; object-fit: cover; }
        .section-body .website-card-category { display: none; }
        .section-body .website-card-button { display: inline-block; margin-top: 1rem; border-radius: .375rem; background: {{ themeColor(600) }}; padding: .55rem 1rem; color: #fff; font-weight: 600; text-decoration: none; }
        .section-body .website-card-button:hover { filter: brightness(.92); }
        .editing-page .section-body .website-card { position: relative; cursor: pointer; }
        .editing-page .section-body .website-card:hover { outline: 2px dashed {{ themeColor(600) }}; outline-offset: 3px; }
        .editing-page .section-body .website-card:hover::after { position: absolute; top: .35rem; right: .35rem; border-radius: .35rem; background: {{ themeColor(600) }}; padding: .2rem .5rem; color: #fff; content: 'Editar tarjeta'; font-size: .72rem; font-weight: 600; }
        .icon-picker-grid { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: .5rem; margin: .75rem 1.5rem; max-height: 230px; overflow-y: auto; }
        .icon-picker-option { display: flex; min-height: 2.75rem; align-items: center; justify-content: center; border: 1px solid #d1d5db; border-radius: .5rem; background: #fff; color: #374151; cursor: pointer; font-size: 1.15rem; }
        .icon-picker-option.selected { border-color: {{ themeColor(600) }}; background: {{ themeColor(50) }}; color: {{ themeColor(700) }}; box-shadow: 0 0 0 2px {{ themeColor(200) }}; }
        .section-body .website-card h3 { margin: 0 0 .5rem; font-size: 1.1rem; font-weight: 600; }
        .section-body .website-card p { margin: 0; color: #4b5563; line-height: 1.6; }
        .note-editable img { display: inline-block; max-width: 100%; resize: both; overflow: auto; cursor: move; }
        .section-layout { display: grid; align-items: start; gap: 2.5rem; }
        .section-layout-left, .section-layout-right { grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); }
        .section-layout-right .section-layout-image { order: 2; }
        .section-layout-top { grid-template-columns: minmax(0, 1fr); }
        .section-layout-image { display: block; width: 100%; max-height: 620px; object-fit: contain; }
        .editing-page .section-layout { display: block; }
        @media (max-width: 700px) { .section-layout { gap: 1rem; } .section-layout-left, .section-layout-right { grid-template-columns: 1fr; } .section-layout-right .section-layout-image { order: 0; } }
        .site-page-section p { color: #4b5563; white-space: pre-line; line-height: 1.8; }
        .site-page-editbar { position: sticky; z-index: 10; top: 0; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; background: #fff; padding: .75rem; box-shadow: 0 2px 10px #0002; }
        .site-page-editbar button, .site-page-editbar select { border: 1px solid #d1d5db; border-radius: .375rem; background: white; padding: .45rem .65rem; }
        .site-page-editbar button { cursor: pointer; }
        .site-page-section.editing { border-color: transparent; }
        [contenteditable="true"] { outline: 2px dashed #94a3b8; outline-offset: 3px; }
        .builder-controls { display: none; gap: .4rem; margin-bottom: .75rem; }
        .editing-page .builder-controls { display: flex; }
        .builder-controls button { border: 1px solid #d1d5db; border-radius: .375rem; background: #fff; padding: .35rem .6rem; }
        .dragging { opacity: .45; }
    </style>
</head>
<body>
    @include('customer.web.menu')
    @if ($page->slug === 'inicio')
        @include('customer.web.banners')
    @endif

    @php($canEditPage = auth()->check() && !request()->boolean('public_view') && !auth()->user()->roles()->where('roles.name', 'Doctor')->exists())
    @if ($canEditPage)
        <div id="page-editor-toolbar" class="site-page-editbar" hidden>
            <strong class="mr-2">Editando página</strong>
            <span class="mr-2 text-sm text-gray-600">Arrastra la imagen para ubicarla; usa la esquina inferior derecha para achicarla o ampliarla. Al seleccionarla aparecen opciones de alineación.</span>
            <button type="button" id="add-page-section">+ Sección</button>
            <button type="button" id="save-page" class="inline-flex items-center gap-2 font-semibold text-white" style="background: {{ themeColor(600) }}">
                <svg aria-hidden="true" viewBox="0 0 20 20" width="17" height="17" fill="currentColor"><path d="M3 2h12l3 3v13H2V2h1Zm2 1v5h9V3H5Zm0 8v6h10v-6H5Zm2 1h6v1H7v-1Z"/></svg>
                Guardar cambios
            </button>
            <button type="button" id="cancel-page-edit">Salir</button>
            <span id="editor-save-status" class="ml-auto text-sm text-gray-600"></span>
        </div>
    @endif

    <main id="site-page" class="site-page-content container"
        data-page-id="{{ $page->id }}"
        data-page-title="{{ $page->title }}"
        data-page-update-url="{{ route('customers.website-pages.update', ['customer' => request()->route('customer'), 'id' => $page->id]) }}"
        data-section-store-url="{{ route('customers.website-pages.sections.store', ['customer' => request()->route('customer'), 'id' => $page->id]) }}"
        data-section-update-url="{{ route('customers.website-pages.sections.update', ['customer' => request()->route('customer'), 'id' => $page->id, 'sectionId' => '__SECTION__']) }}"
        data-section-delete-url="{{ route('customers.website-pages.sections.destroy', ['customer' => request()->route('customer'), 'id' => $page->id, 'sectionId' => '__SECTION__']) }}"
        data-image-upload-url="{{ route('customers.website-pages.images.store', ['customer' => request()->route('customer'), 'id' => $page->id]) }}">
        @if ($page->slug !== 'inicio')
        <header id="page-banner" class="site-page-banner" @if($page->banner_path) style="background-image: url('{{ asset('storage/' . $page->banner_path) }}')" @endif>
            @if ($canEditPage)
                <button id="start-page-edit" type="button" class="inline-edit-icon banner-edit-icon" title="Editar título y banner" aria-label="Editar título y banner">
                    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18" fill="currentColor"><path d="M13.586 3.586a2 2 0 0 1 2.828 2.828l-8.9 8.9-3.82.99.99-3.82 8.902-8.898ZM3 17h14v1H3v-1Z"/></svg>
                </button>
            @endif
            <h1 id="page-title" class="site-page-title" data-value="{{ $page->title }}">{{ $page->title }}</h1>
        </header>
        @if ($canEditPage)
            <div id="banner-editor" class="mb-8 hidden rounded-lg border bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-sm">Cambiar imagen del banner<input id="banner-file" class="mt-1 block text-sm" type="file" accept="image/*"></label>
                    <label class="flex items-center gap-2 text-sm"><input id="remove-banner" type="checkbox"> Quitar banner</label>
                    <label class="text-sm">Descripción<input id="banner-alt" class="ml-2 rounded border px-2 py-1" value="{{ $page->banner_alt }}" maxlength="160"></label>
                </div>
            </div>
        @endif
        @endif

        <div id="page-sections">
            @foreach ($page->sections as $section)
                <section class="site-page-section" data-section-id="{{ $section->id }}" data-image-position="{{ $section->image_position ?? 'inline' }}">
                    @if ($canEditPage)
                        <button type="button" class="inline-edit-icon section-edit-icon" title="Editar esta sección" aria-label="Editar esta sección">
                            <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18" fill="currentColor"><path d="M13.586 3.586a2 2 0 0 1 2.828 2.828l-8.9 8.9-3.82.99.99-3.82 8.902-8.898ZM3 17h14v1H3v-1Z"/></svg>
                        </button>
                        <div class="builder-controls">
                            <button type="button" class="move-section-up" title="Subir sección" aria-label="Subir sección">↑ Subir</button>
                            <button type="button" class="move-section-down" title="Bajar sección" aria-label="Bajar sección">↓ Bajar</button>
                            <label class="text-sm">Ubicación de imagen
                                <select class="section-image-position rounded border px-2 py-1">
                                    <option value="inline" @selected(($section->image_position ?? 'inline') === 'inline')>En el texto</option>
                                    <option value="top" @selected(($section->image_position ?? 'inline') === 'top')>Al inicio, texto abajo</option>
                                    <option value="left" @selected(($section->image_position ?? 'inline') === 'left')>Izquierda, texto a la derecha</option>
                                    <option value="right" @selected(($section->image_position ?? 'inline') === 'right')>Derecha, texto a la izquierda</option>
                                </select>
                            </label>
                            <button type="button" class="add-section-card">+ Tarjeta o círculo</button>
                            <button type="button" class="delete-section text-red-600">Eliminar sección</button>
                        </div>
                    @endif
                    <h2 class="section-title" @if($canEditPage) contenteditable="false" @endif>{{ $section->title }}</h2>
                    @if ($section->image_path && in_array($section->image_position ?? 'inline', ['top', 'left', 'right'], true))
                        <div class="section-layout section-layout-{{ $section->image_position }}">
                            <img class="section-layout-image section-image" src="{{ asset('storage/' . $section->image_path) }}" alt="{{ $section->image_alt ?? '' }}">
                            <div class="section-layout-copy section-body">{!! $section->body !!}</div>
                        </div>
                    @else
                        <div class="section-body">{!! $section->body !!}</div>
                        @if ($section->image_path)
                            <img class="section-image" src="{{ asset('storage/' . $section->image_path) }}" alt="{{ $section->image_alt ?? '' }}">
                        @endif
                    @endif
                </section>
            @endforeach
        </div>
    </main>

    @include('customer.web.footer')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    @if ($canEditPage)
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/lang/summernote-es-ES.min.js"></script>
        <script>
            (() => {
                const main = document.getElementById('site-page');
                const toolbar = document.getElementById('page-editor-toolbar');
                const sections = document.getElementById('page-sections');
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const deletedSectionIds = [];
                let editing = false;
                let bannerEditing = false;
                const cardIconOptions = [
                    'fa-stethoscope', 'fa-heart', 'fa-user-doctor', 'fa-hand-holding-heart', 'fa-house-medical', 'fa-brain',
                    'fa-star', 'fa-shield-heart', 'fa-calendar-check', 'fa-comments', 'fa-spa', 'fa-tooth',
                    'fa-hospital', 'fa-user-nurse', 'fa-kit-medical', 'fa-pills', 'fa-syringe', 'fa-dna',
                    'fa-eye', 'fa-ear-listen', 'fa-baby', 'fa-person', 'fa-people-group', 'fa-wheelchair',
                    'fa-notes-medical', 'fa-file-medical', 'fa-prescription', 'fa-handshake', 'fa-leaf', 'fa-lightbulb',
                    'fa-award', 'fa-clock', 'fa-location-dot', 'fa-phone', 'fa-envelope', 'fa-circle-check',
                    'fa-heart-pulse', 'fa-briefcase-medical', 'fa-lungs', 'fa-bone', 'fa-hospital-user', 'fa-person-pregnant',
                    'fa-child', 'fa-user-group', 'fa-house-medical-circle-check', 'fa-file-prescription', 'fa-capsules', 'fa-vial',
                    'fa-microscope', 'fa-virus', 'fa-laptop-medical', 'fa-truck-medical', 'fa-bandage', 'fa-crutch',
                    'fa-wheelchair-move', 'fa-hand-holding-medical', 'fa-hand-holding-droplet', 'fa-seedling', 'fa-sun', 'fa-moon',
                    'fa-calendar-days', 'fa-map-location-dot', 'fa-phone-volume', 'fa-envelope-open-text', 'fa-comment-medical', 'fa-video',
                    'fa-user-shield', 'fa-shield-halved', 'fa-medal', 'fa-thumbs-up', 'fa-circle-plus', 'fa-circle-info',
                    'fa-circle-question', 'fa-check', 'fa-check-double', 'fa-clipboard-check', 'fa-list-check', 'fa-heart-circle-check',
                    'fa-person-circle-check', 'fa-people-roof', 'fa-hands-holding-circle', 'fa-handshake-angle', 'fa-book-medical', 'fa-book-open'
                ];

                const initializeEditor = (section) => {
                    const body = section.querySelector('.section-body');
                    if (!body || $(body).data('summernote')) return;
                    const oldStandaloneImage = section.querySelector('.section-image');
                    if (oldStandaloneImage) {
                        section.dataset.migratedImage = '1';
                        body.appendChild(oldStandaloneImage);
                    }
                    $(body).summernote({
                        lang: 'es-ES',
                        height: 220,
                        dialogsInBody: true,
                        disableDragAndDrop: false,
                        toolbar: [
                            ['style', ['style']],
                            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                            ['fontname', ['fontname']],
                            ['fontsize', ['fontsize']],
                            ['color', ['color']],
                            ['para', ['ul', 'ol', 'paragraph']],
                            ['insert', ['link', 'picture', 'table']],
                            ['view', ['fullscreen', 'codeview']]
                        ],
                        popover: {
                            image: [
                                ['image', ['resizeFull', 'resizeHalf', 'resizeQuarter', 'resizeNone']],
                                ['float', ['floatLeft', 'floatRight', 'floatNone']],
                                ['remove', ['removeMedia']]
                            ]
                        },
                        callbacks: {
                            onInit: () => {
                                $(body).next('.note-editor').find('.note-editable img').attr('draggable', 'true');
                            },
                            onChange: () => {
                                $(body).next('.note-editor').find('.note-editable img').attr('draggable', 'true');
                            },
                            onImageUpload: (files) => {
                                [...files].forEach(async (file) => {
                                    try {
                                        const data = new FormData();
                                        data.append('image', file);
                                        const response = await fetch(main.dataset.imageUploadUrl, {
                                            method: 'POST',
                                            body: data,
                                            headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}
                                        });
                                        const result = await response.json();
                                        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'No se pudo subir la imagen.');
                                        $(body).summernote('insertImage', result.url);
                                        $(body).next('.note-editor').find('.note-editable img').last().attr('draggable', 'true');
                                    } catch (error) {
                                        Swal.fire({icon: 'error', title: 'No se pudo subir la imagen', text: error.message});
                                    }
                                });
                            }
                        }
                    });
                };

                const openCardEditor = (section, existingCard = null) => {
                    const body = section.querySelector('.section-body');
                    const editor = $(body).next('.note-editor').find('.note-editable');
                    const oldImage = existingCard?.querySelector('.website-card-image');
                    const oldIcon = existingCard?.querySelector('.website-card-icon i');
                    const oldButton = existingCard?.querySelector('.website-card-button');
                    const currentImageUrl = oldImage?.getAttribute('src') || '';
                    const currentIcon = oldIcon ? [...oldIcon.classList].find((name) => name.startsWith('fa-') && name !== 'fa-solid' && name !== 'fa-brands') : 'fa-stethoscope';

                    Swal.fire({
                        title: existingCard ? 'Editar tarjeta' : 'Agregar elemento visual',
                        html: '<select id="card-shape" class="swal2-select"><option value="card">Tarjeta</option><option value="circle">Círculo</option></select><select id="card-media" class="swal2-select"><option value="icon">Icono</option><option value="image">Imagen</option></select><input id="card-icon-search" type="search" class="swal2-input" placeholder="Buscar icono"><div id="card-icon-picker" class="icon-picker-grid"></div><input id="card-icon" type="hidden"><div id="card-current-image" style="display:none;margin:.5rem 1.5rem;text-align:center"></div><input id="card-image" type="file" accept="image/*" class="swal2-file" style="display:none"><input id="card-title" class="swal2-input" placeholder="Título o nombre"><textarea id="card-copy" class="swal2-textarea" placeholder="Descripción"></textarea><label style="display:block;margin:0 1.5rem;text-align:left"><input id="card-button-toggle" type="checkbox"> Agregar botón con enlace</label><input id="card-button-text" class="swal2-input" placeholder="Texto del botón" style="display:none"><input id="card-button-url" class="swal2-input" placeholder="Enlace (https://... o /ruta)" style="display:none">',
                        showCancelButton: true,
                        confirmButtonText: existingCard ? 'Guardar cambios' : 'Agregar',
                        cancelButtonText: 'Cancelar',
                        focusConfirm: false,
                        didOpen: () => {
                            const media = document.getElementById('card-media');
                            const iconPicker = document.getElementById('card-icon-picker');
                            const iconSearch = document.getElementById('card-icon-search');
                            const iconInput = document.getElementById('card-icon');
                            const imageInput = document.getElementById('card-image');
                            const imagePreview = document.getElementById('card-current-image');
                            const buttonToggle = document.getElementById('card-button-toggle');
                            const buttonText = document.getElementById('card-button-text');
                            const buttonUrl = document.getElementById('card-button-url');

                            document.getElementById('card-shape').value = existingCard?.classList.contains('website-card-circle') ? 'circle' : 'card';
                            media.value = oldImage ? 'image' : 'icon';
                            iconInput.value = currentIcon || 'fa-stethoscope';
                            document.getElementById('card-title').value = existingCard?.querySelector('h3')?.textContent?.trim() || '';
                            document.getElementById('card-copy').value = existingCard?.querySelector('p')?.textContent?.trim() || '';
                            buttonToggle.checked = Boolean(oldButton);
                            buttonText.value = oldButton?.textContent?.trim() || '';
                            buttonUrl.value = oldButton?.getAttribute('href') || '';
                            if (currentImageUrl) {
                                imagePreview.innerHTML = `<img src="${currentImageUrl}" alt="Imagen actual" style="max-width:100%;max-height:120px;border-radius:.5rem">`;
                            }

                            cardIconOptions.forEach((iconClass) => {
                                const option = document.createElement('button');
                                option.type = 'button';
                                option.className = 'icon-picker-option';
                                option.title = iconClass.replace('fa-', '').replaceAll('-', ' ');
                                option.setAttribute('aria-label', option.title);
                                const glyph = document.createElement('i');
                                glyph.className = `fa-solid ${iconClass}`;
                                option.append(glyph);
                                option.addEventListener('click', () => {
                                    iconInput.value = iconClass;
                                    iconPicker.querySelectorAll('.icon-picker-option').forEach((item) => item.classList.remove('selected'));
                                    option.classList.add('selected');
                                });
                                iconPicker.append(option);
                                if (iconClass === iconInput.value) option.classList.add('selected');
                            });

                            iconSearch.addEventListener('input', () => {
                                const term = iconSearch.value.trim().toLowerCase();
                                iconPicker.querySelectorAll('.icon-picker-option').forEach((option) => {
                                    option.hidden = !option.title.includes(term);
                                });
                            });
                            const updateMedia = () => {
                                iconSearch.style.display = media.value === 'icon' ? '' : 'none';
                                iconPicker.style.display = media.value === 'icon' ? '' : 'none';
                                imagePreview.style.display = media.value === 'image' && currentImageUrl ? '' : 'none';
                                imageInput.style.display = media.value === 'image' ? '' : 'none';
                            };
                            media.addEventListener('change', updateMedia);
                            buttonToggle.addEventListener('change', () => {
                                buttonText.style.display = buttonToggle.checked ? '' : 'none';
                                buttonUrl.style.display = buttonToggle.checked ? '' : 'none';
                            });
                            updateMedia();
                            buttonText.style.display = buttonToggle.checked ? '' : 'none';
                            buttonUrl.style.display = buttonToggle.checked ? '' : 'none';
                        },
                        preConfirm: () => {
                            const title = document.getElementById('card-title').value.trim();
                            if (!title) { Swal.showValidationMessage('Escribe un título para la tarjeta.'); return false; }
                            const media = document.getElementById('card-media').value;
                            const image = document.getElementById('card-image').files[0];
                            if (media === 'image' && !image && !currentImageUrl) { Swal.showValidationMessage('Selecciona una imagen.'); return false; }
                            const hasButton = document.getElementById('card-button-toggle').checked;
                            const buttonText = document.getElementById('card-button-text').value.trim();
                            const buttonUrl = document.getElementById('card-button-url').value.trim();
                            if (hasButton && (!buttonText || !buttonUrl)) { Swal.showValidationMessage('Completa el texto y el enlace del botón.'); return false; }
                            if (hasButton && !/^(https?:\/\/|\/|#|mailto:)/i.test(buttonUrl)) { Swal.showValidationMessage('Usa un enlace http(s), una ruta que comience con / o un correo mailto:.'); return false; }
                            return {shape: document.getElementById('card-shape').value, media, icon: document.getElementById('card-icon').value || 'fa-stethoscope', image, title, copy: document.getElementById('card-copy').value.trim(), buttonText, buttonUrl, hasButton};
                        }
                    }).then(async (result) => {
                        if (!result.isConfirmed) return;
                        try {
                            let imageUrl = currentImageUrl;
                            if (result.value.media === 'image' && result.value.image) {
                                const data = new FormData();
                                data.append('image', result.value.image);
                                const response = await fetch(main.dataset.imageUploadUrl, {method: 'POST', body: data, headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}});
                                const uploaded = await response.json();
                                if (!response.ok) throw new Error(Object.values(uploaded.errors || {}).flat()[0] || uploaded.message || 'No se pudo subir la imagen.');
                                imageUrl = uploaded.url;
                            }

                            const card = existingCard || document.createElement('div');
                            card.className = `website-card${result.value.shape === 'circle' ? ' website-card-circle' : ''}`;
                            card.replaceChildren();
                            if (result.value.media === 'icon') {
                                const iconWrap = document.createElement('span');
                                iconWrap.className = 'website-card-icon';
                                const glyph = document.createElement('i');
                                glyph.className = `fa-solid ${result.value.icon}`;
                                iconWrap.append(glyph);
                                card.append(iconWrap);
                            } else {
                                const image = document.createElement('img');
                                image.className = 'website-card-image';
                                image.src = imageUrl;
                                image.alt = result.value.title;
                                card.append(image);
                            }
                            const title = document.createElement('h3');
                            title.textContent = result.value.title;
                            const copy = document.createElement('p');
                            copy.textContent = result.value.copy;
                            card.append(title, copy);
                            if (result.value.hasButton) {
                                const button = document.createElement('a');
                                button.className = 'website-card-button';
                                button.textContent = result.value.buttonText;
                                button.href = result.value.buttonUrl;
                                card.append(button);
                            }
                            if (!existingCard) {
                                let grid = editor.find('.website-card-grid').get(0);
                                if (!grid) {
                                    $(body).summernote('pasteHTML', '<div class="website-card-grid"></div><p><br></p>');
                                    grid = editor.find('.website-card-grid').get(0);
                                }
                                grid.append(card);
                            }
                        } catch (error) {
                            Swal.fire({icon: 'error', title: 'No se pudo guardar la tarjeta', text: error.message});
                        }
                    });
                };

                const makeEditable = (enabled, editBanner = false) => {
                    editing = enabled;
                    if (!enabled) bannerEditing = false;
                    else if (editBanner && document.getElementById('banner-editor')) bannerEditing = true;
                    document.body.classList.toggle('editing-page', enabled);
                    toolbar.hidden = !enabled;
                    const bannerEditor = document.getElementById('banner-editor');
                    const startPageEdit = document.getElementById('start-page-edit');
                    const pageTitle = document.getElementById('page-title');
                    if (bannerEditor) bannerEditor.classList.toggle('hidden', !enabled || !bannerEditing);
                    if (startPageEdit) startPageEdit.classList.toggle('hidden', enabled);
                    if (pageTitle) pageTitle.contentEditable = enabled && bannerEditing;
                    document.querySelectorAll('.site-page-section').forEach((section) => {
                        section.classList.toggle('editing', enabled);
                        section.querySelector('.section-title').contentEditable = enabled;
                        if (enabled) initializeEditor(section);
                    });
                };

                const startPageEdit = document.getElementById('start-page-edit');
                if (startPageEdit) startPageEdit.addEventListener('click', () => {
                    makeEditable(true, true);
                    document.getElementById('page-title')?.focus();
                });
                if (new URLSearchParams(window.location.search).get('edit') === '1') {
                    makeEditable(true, true);
                }
                sections.addEventListener('click', (event) => {
                    const editButton = event.target.closest('.section-edit-icon');
                    if (!editButton) return;
                    makeEditable(true);
                    const section = editButton.closest('.site-page-section');
                    initializeEditor(section);
                    $(section.querySelector('.section-body')).summernote('focus');
                });
                document.getElementById('cancel-page-edit').addEventListener('click', () => {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('edit');
                    window.location.href = url.toString();
                });

                document.getElementById('add-page-section').addEventListener('click', () => {
                    const section = document.createElement('section');
                    section.className = 'site-page-section editing';
                    section.dataset.newSection = '1';
                    section.innerHTML = `<button type="button" class="inline-edit-icon section-edit-icon" title="Editar esta sección" aria-label="Editar esta sección"><svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18" fill="currentColor"><path d="M13.586 3.586a2 2 0 0 1 2.828 2.828l-8.9 8.9-3.82.99.99-3.82 8.902-8.898ZM3 17h14v1H3v-1Z"/></svg></button><div class="builder-controls"><button type="button" class="move-section-up" title="Subir sección">↑ Subir</button><button type="button" class="move-section-down" title="Bajar sección">↓ Bajar</button><label class="text-sm">Ubicación de imagen<select class="section-image-position rounded border px-2 py-1"><option value="inline">En el texto</option><option value="top">Al inicio, texto abajo</option><option value="left">Izquierda, texto a la derecha</option><option value="right">Derecha, texto a la izquierda</option></select></label><button type="button" class="add-section-card">+ Tarjeta o círculo</button><button type="button" class="delete-section text-red-600">Eliminar sección</button></div><h2 class="section-title" contenteditable="true">Nueva sección</h2><div class="section-body"><p>Escribe aquí el contenido.</p></div>`;
                    sections.append(section);
                    initializeEditor(section);
                });

                document.getElementById('banner-file')?.addEventListener('change', (event) => {
                    const file = event.target.files[0];
                    if (file && document.getElementById('page-banner')) document.getElementById('page-banner').style.backgroundImage = `url("${URL.createObjectURL(file)}")`;
                });
                document.getElementById('remove-banner')?.addEventListener('change', (event) => {
                    if (event.target.checked && document.getElementById('page-banner')) document.getElementById('page-banner').style.backgroundImage = '';
                });

                sections.addEventListener('click', (event) => {
                    const section = event.target.closest('.site-page-section');
                    if (!section) return;
                    const selectedCard = event.target.closest('.website-card');
                    if (editing && selectedCard) {
                        event.preventDefault();
                        event.stopPropagation();
                        openCardEditor(section, selectedCard);
                        return;
                    }
                    if (event.target.closest('.move-section-up')) {
                        const previous = section.previousElementSibling;
                        if (previous) sections.insertBefore(section, previous);
                        return;
                    }
                    if (event.target.closest('.move-section-down')) {
                        const next = section.nextElementSibling;
                        if (next) sections.insertBefore(next, section);
                        return;
                    }
                    if (event.target.closest('.add-section-card')) {
                        const body = section.querySelector('.section-body');
                        const editor = $(body).next('.note-editor').find('.note-editable');
                        Swal.fire({
                            title: 'Agregar elemento visual',
                            html: '<select id="card-shape" class="swal2-select"><option value="card">Tarjeta</option><option value="circle">Círculo</option></select><select id="card-media" class="swal2-select"><option value="icon">Icono</option><option value="image">Imagen</option></select><div id="card-icon-picker" class="icon-picker-grid"></div><input id="card-icon" type="hidden" value="fa-stethoscope"><input id="card-image" type="file" accept="image/*" class="swal2-file" style="display:none"><input id="card-title" class="swal2-input" placeholder="Título o nombre"><textarea id="card-copy" class="swal2-textarea" placeholder="Descripción"></textarea><label style="display:block;margin:0 1.5rem;text-align:left"><input id="card-button-toggle" type="checkbox"> Agregar botón con enlace</label><input id="card-button-text" class="swal2-input" placeholder="Texto del botón" style="display:none"><input id="card-button-url" class="swal2-input" placeholder="Enlace (https://... o /ruta)" style="display:none">',
                            showCancelButton: true,
                            confirmButtonText: 'Agregar',
                            cancelButtonText: 'Cancelar',
                            focusConfirm: false,
                            didOpen: () => {
                                const media = document.getElementById('card-media');
                                const icon = document.getElementById('card-icon-picker');
                                const image = document.getElementById('card-image');
                                const buttonToggle = document.getElementById('card-button-toggle');
                                const buttonText = document.getElementById('card-button-text');
                                const buttonUrl = document.getElementById('card-button-url');
                                const iconOptions = [
                                    'fa-stethoscope', 'fa-heart', 'fa-user-doctor', 'fa-hand-holding-heart', 'fa-house-medical', 'fa-brain',
                                    'fa-star', 'fa-shield-heart', 'fa-calendar-check', 'fa-comments', 'fa-spa', 'fa-tooth',
                                    'fa-hospital', 'fa-user-nurse', 'fa-kit-medical', 'fa-pills', 'fa-syringe', 'fa-dna',
                                    'fa-eye', 'fa-ear-listen', 'fa-baby', 'fa-person', 'fa-people-group', 'fa-wheelchair',
                                    'fa-notes-medical', 'fa-file-medical', 'fa-prescription', 'fa-handshake', 'fa-leaf', 'fa-lightbulb',
                                    'fa-award', 'fa-clock', 'fa-location-dot', 'fa-phone', 'fa-envelope', 'fa-circle-check',
                                    'fa-heart-pulse', 'fa-briefcase-medical', 'fa-lungs', 'fa-bone', 'fa-hospital-user', 'fa-person-pregnant',
                                    'fa-child', 'fa-user-group', 'fa-house-medical-circle-check', 'fa-file-prescription', 'fa-capsules', 'fa-vial',
                                    'fa-microscope', 'fa-virus', 'fa-laptop-medical', 'fa-truck-medical', 'fa-bandage', 'fa-crutch',
                                    'fa-wheelchair-move', 'fa-hand-holding-medical', 'fa-hand-holding-droplet', 'fa-seedling', 'fa-sun', 'fa-moon',
                                    'fa-calendar-days', 'fa-map-location-dot', 'fa-phone-volume', 'fa-envelope-open-text', 'fa-comment-medical', 'fa-video',
                                    'fa-user-shield', 'fa-shield-halved', 'fa-medal', 'fa-thumbs-up', 'fa-circle-plus', 'fa-circle-info',
                                    'fa-circle-question', 'fa-check', 'fa-check-double', 'fa-clipboard-check', 'fa-list-check', 'fa-heart-circle-check',
                                    'fa-person-circle-check', 'fa-people-roof', 'fa-hands-holding-circle', 'fa-handshake-angle', 'fa-book-medical', 'fa-book-open'
                                ];
                                const picker = document.getElementById('card-icon-picker');
                                const iconInput = document.getElementById('card-icon');
                                const iconSearch = document.createElement('input');
                                iconSearch.type = 'search';
                                iconSearch.className = 'swal2-input';
                                iconSearch.placeholder = 'Buscar icono';
                                iconSearch.setAttribute('aria-label', 'Buscar icono');
                                picker.before(iconSearch);
                                iconOptions.forEach((iconClass) => {
                                    const option = document.createElement('button');
                                    option.type = 'button'; option.className = 'icon-picker-option'; option.title = iconClass.replace('fa-', '').replaceAll('-', ' '); option.setAttribute('aria-label', option.title);
                                    const glyph = document.createElement('i'); glyph.className = `fa-solid ${iconClass}`; option.append(glyph);
                                    option.addEventListener('click', () => {
                                        iconInput.value = iconClass;
                                        picker.querySelectorAll('.icon-picker-option').forEach((item) => item.classList.remove('selected'));
                                        option.classList.add('selected');
                                    });
                                    picker.append(option);
                                    if (iconClass === iconInput.value) option.classList.add('selected');
                                });
                                iconSearch.addEventListener('input', () => {
                                    const term = iconSearch.value.trim().toLowerCase();
                                    picker.querySelectorAll('.icon-picker-option').forEach((option) => {
                                        option.hidden = !option.title.includes(term);
                                    });
                                });
                                const updateMedia = () => {
                                    icon.style.display = media.value === 'icon' ? '' : 'none';
                                    image.style.display = media.value === 'image' ? '' : 'none';
                                };
                                media.addEventListener('change', updateMedia);
                                buttonToggle.addEventListener('change', () => {
                                    buttonText.style.display = buttonToggle.checked ? '' : 'none';
                                    buttonUrl.style.display = buttonToggle.checked ? '' : 'none';
                                });
                                updateMedia();
                            },
                            preConfirm: () => {
                                const title = document.getElementById('card-title').value.trim();
                                if (!title) { Swal.showValidationMessage('Escribe un título para la tarjeta.'); return false; }
                                const media = document.getElementById('card-media').value;
                                const image = document.getElementById('card-image').files[0];
                                if (media === 'image' && !image) { Swal.showValidationMessage('Selecciona una imagen.'); return false; }
                                const hasButton = document.getElementById('card-button-toggle').checked;
                                const buttonText = document.getElementById('card-button-text').value.trim();
                                const buttonUrl = document.getElementById('card-button-url').value.trim();
                                if (hasButton && (!buttonText || !buttonUrl)) { Swal.showValidationMessage('Completa el texto y el enlace del botón.'); return false; }
                                if (hasButton && !/^(https?:\/\/|\/|#|mailto:)/i.test(buttonUrl)) { Swal.showValidationMessage('Usa un enlace http(s), una ruta que comience con / o un correo mailto:.'); return false; }
                                return {shape: document.getElementById('card-shape').value, media, icon: document.getElementById('card-icon').value, image, title, copy: document.getElementById('card-copy').value.trim(), buttonText, buttonUrl, hasButton};
                            }
                        }).then((result) => {
                            if (!result.isConfirmed) return;
                            const addCard = async () => {
                                const card = document.createElement('div');
                                card.className = `website-card${result.value.shape === 'circle' ? ' website-card-circle' : ''}`;
                                if (result.value.media === 'icon') {
                                    const icon = document.createElement('span'); icon.className = 'website-card-icon';
                                    const fontAwesomeIcon = document.createElement('i'); fontAwesomeIcon.className = `fa-solid ${result.value.icon}`; icon.append(fontAwesomeIcon); card.append(icon);
                                } else {
                                    const data = new FormData(); data.append('image', result.value.image);
                                    const response = await fetch(main.dataset.imageUploadUrl, {method: 'POST', body: data, headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}});
                                    const uploaded = await response.json();
                                    if (!response.ok) throw new Error(Object.values(uploaded.errors || {}).flat()[0] || uploaded.message || 'No se pudo subir la imagen.');
                                    const image = document.createElement('img'); image.className = 'website-card-image'; image.src = uploaded.url; image.alt = result.value.title; card.append(image);
                                }
                                const title = document.createElement('h3'); title.textContent = result.value.title;
                                const copy = document.createElement('p'); copy.textContent = result.value.copy;
                                card.append(title, copy);
                                if (result.value.hasButton) {
                                    const button = document.createElement('a');
                                    button.className = 'website-card-button'; button.textContent = result.value.buttonText; button.href = result.value.buttonUrl;
                                    card.append(button);
                                }
                                let grid = editor.find('.website-card-grid').get(0);
                                if (!grid) {
                                    $(body).summernote('pasteHTML', '<div class="website-card-grid"></div><p><br></p>');
                                    grid = editor.find('.website-card-grid').get(0);
                                }
                                grid.append(card);
                            };
                            addCard().catch((error) => Swal.fire({icon: 'error', title: 'No se pudo agregar', text: error.message}));
                        });
                        return;
                    }
                    if (event.target.closest('.delete-section')) {
                        Swal.fire({title: '¿Eliminar sección?', text: 'También se eliminará su imagen.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'}).then((result) => {
                            if (result.isConfirmed) {
                                if (section.dataset.sectionId) deletedSectionIds.push(section.dataset.sectionId);
                                section.remove();
                            }
                        });
                    }
                });
                sections.addEventListener('change', (event) => {
                    if (!event.target.matches('.section-image-position')) return;
                    const section = event.target.closest('.site-page-section');
                    const body = section.querySelector('.section-body');
                    const editor = $(body).data('summernote')
                        ? $(body).next('.note-editor').find('.note-editable')
                        : $(body);
                    const image = editor.find('img').get(0);
                    if (!image) {
                        event.target.value = 'inline';
                        section.dataset.imagePosition = 'inline';
                        Swal.fire({icon: 'info', title: 'Agrega una imagen primero', text: 'Luego podrás elegir dónde ubicarla respecto del texto.'});
                        return;
                    }

                    const paragraph = image.parentElement;
                    if (paragraph && paragraph !== editor.get(0) && paragraph.tagName === 'P' && [...paragraph.childNodes].every((node) => node === image || (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()))) {
                        editor.prepend(paragraph);
                    } else {
                        editor.prepend(image);
                    }
                    image.style.float = event.target.value === 'left' ? 'left' : (event.target.value === 'right' ? 'right' : 'none');
                    image.style.width = ['left', 'right'].includes(event.target.value) ? '40%' : (event.target.value === 'top' ? '100%' : '');
                    image.style.maxWidth = '100%';
                    image.style.margin = event.target.value === 'left' ? '0 1.25rem 1rem 0' : (event.target.value === 'right' ? '0 0 1rem 1.25rem' : '1rem auto');
                    section.dataset.imagePosition = event.target.value;
                });

                const request = async (url, method, data) => {
                    const response = await fetch(url, {method, body: data, headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}});
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'No se pudo guardar.');
                    return payload;
                };

                document.getElementById('save-page').addEventListener('click', async () => {
                    const status = document.getElementById('editor-save-status');
                    status.textContent = 'Guardando…';
                    try {
                        const pageData = new FormData();
                        pageData.append('_method', 'PUT');
                        pageData.append('title', document.getElementById('page-title')?.innerText.trim() || main.dataset.pageTitle);
                        const bannerAlt = document.getElementById('banner-alt');
                        const bannerFile = document.getElementById('banner-file');
                        const removeBanner = document.getElementById('remove-banner');
                        if (bannerAlt) pageData.append('banner_alt', bannerAlt.value);
                        if (bannerFile?.files[0]) pageData.append('banner', bannerFile.files[0]);
                        if (removeBanner?.checked) pageData.append('remove_banner', '1');
                        await request(main.dataset.pageUpdateUrl, 'POST', pageData);

                        for (const sectionId of deletedSectionIds) {
                            const deleteData = new FormData();
                            deleteData.append('_method', 'DELETE');
                            await request(main.dataset.sectionDeleteUrl.replace('__SECTION__', sectionId), 'POST', deleteData);
                        }

                        for (const [position, section] of [...sections.children].entries()) {
                            const formData = new FormData();
                            formData.append('_method', section.dataset.sectionId ? 'PUT' : 'POST');
                            formData.append('title', section.querySelector('.section-title').innerText.trim());
                            const body = section.querySelector('.section-body');
                            const bodyHtml = $(body).data('summernote') ? $(body).summernote('code') : body.innerHTML;
                            formData.append('body', bodyHtml);
                            formData.append('position', position);
                            formData.append('image_alt', section.querySelector('.section-body img')?.alt || '');
                            formData.append('image_position', section.querySelector('.section-image-position')?.value || 'inline');
                            if (section.dataset.migratedImage === '1') formData.append('remove_image', '1');

                            if (section.dataset.sectionId) {
                                const url = main.dataset.sectionUpdateUrl.replace('__SECTION__', section.dataset.sectionId);
                                await request(url, 'POST', formData);
                            } else {
                                const created = await request(main.dataset.sectionStoreUrl, 'POST', formData);
                                section.dataset.sectionId = created.section.id;
                                delete section.dataset.newSection;
                            }
                        }
                        status.textContent = 'Cambios guardados';
                        await Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Página guardada', showConfirmButton: false, timer: 1800});
                        window.location.reload();
                    } catch (error) {
                        status.textContent = '';
                        Swal.fire({icon: 'error', title: 'No se pudo guardar', text: error.message});
                    }
                });
            })();
        </script>
    @endif
</body>
</html>
