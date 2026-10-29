@extends('layouts.menu-customer')

@php($editing = $post->exists)

@section('header')
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $editing ? 'Editar artículo' : 'Nuevo artículo' }}</h2>
        <a href="{{ route('customers.website-blog.index', ['customer' => request()->route('customer')]) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900"><i class="fa-solid fa-arrow-left mr-1"></i> Volver al blog</a>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('customers.website-blog.update', ['customer' => request()->route('customer'), 'id' => $post->id]) : route('customers.website-blog.store', ['customer' => request()->route('customer')]) }}" class="space-y-5 rounded-xl bg-white p-5 shadow-sm sm:p-7">
            @csrf @if($editing) @method('PUT') @endif
            <div>
                <label for="title" class="mb-1 block text-sm font-medium text-gray-700">Título</label>
                <input id="title" name="title" required maxlength="180" value="{{ old('title', $post->title) }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="excerpt" class="mb-1 block text-sm font-medium text-gray-700">Resumen <span class="font-normal text-gray-500">(opcional, aparece en el listado)</span></label>
                <textarea id="excerpt" name="excerpt" rows="2" maxlength="500" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('excerpt', $post->excerpt) }}</textarea>
                @error('excerpt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="blog-content-editor">
                <label for="content" class="mb-1 block text-sm font-medium text-gray-700">Contenido</label>
                <textarea id="content" name="content" rows="14" required maxlength="100000" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('content', $post->content) }}</textarea>
                @error('content')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="image" class="mb-1 block text-sm font-medium text-gray-700">Imagen destacada <span class="font-normal text-gray-500">(opcional, máximo 5 MB)</span></label>
                    <input id="image" type="file" name="image" accept="image/*" class="sr-only">
                    <label id="blog-article-image-dropzone" for="image" class="mt-2 flex min-h-48 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-5 py-6 text-center transition hover:border-blue-400 hover:bg-blue-50">
                        <img id="blog-article-image-preview" src="{{ $post->image_path ? asset('storage/' . $post->image_path) : '' }}" alt="Vista previa de la imagen destacada" class="mb-3 max-h-48 max-w-full rounded-lg object-cover shadow-sm {{ $post->image_path ? '' : 'hidden' }}">
                        <span id="blog-article-image-placeholder" class="{{ $post->image_path ? 'hidden' : '' }}">
                            <i class="fa-solid fa-cloud-arrow-up mb-2 text-3xl text-gray-400"></i>
                            <span class="block text-sm font-semibold text-gray-700">Arrastra una imagen aquí o haz clic</span>
                        </span>
                        <span id="blog-article-image-filename" class="mt-1 text-xs text-gray-500">{{ $post->image_path ? 'Haz clic para cambiar la imagen' : 'JPG, PNG o WEBP' }}</span>
                    </label>
                    @error('image')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
                    <input type="checkbox" name="status" value="1" @checked(old('status', $post->status)) class="mt-1 rounded border-gray-300" style="color: {{ themeColor(600) }}">
                    <span><span class="block text-sm font-semibold text-gray-800">Publicar artículo</span><span class="mt-1 block text-xs text-gray-500">Si lo dejas desmarcado, se guarda como borrador y no aparece en la web pública.</span></span>
                </label>
            </div>
            @error('status')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex flex-wrap justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('customers.website-blog.index', ['customer' => request()->route('customer')]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                <button class="rounded-md px-5 py-2 text-sm font-semibold text-white shadow-sm" style="background: {{ themeColor(600) }}">{{ $editing ? 'Guardar cambios' : 'Guardar artículo' }}</button>
            </div>
        </form>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .blog-content-editor .note-editor { border-color: #d1d5db; border-radius: .5rem; overflow: hidden; }
        .blog-content-editor .note-toolbar { background: #f9fafb; }
        .blog-content-editor .note-editable { min-height: 360px; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/lang/summernote-es-ES.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            $('#content').summernote({
                lang: 'es-ES',
                height: 400,
                dialogsInBody: true,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                    ['fontname', ['fontname']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link', 'picture', 'table']],
                    ['view', ['fullscreen', 'codeview']]
                ]
            });

            const input = document.getElementById('image');
            const dropzone = document.getElementById('blog-article-image-dropzone');
            const preview = document.getElementById('blog-article-image-preview');
            const placeholder = document.getElementById('blog-article-image-placeholder');
            const filename = document.getElementById('blog-article-image-filename');
            if (!input || !dropzone || !preview || !placeholder || !filename) return;

            let previewUrl = null;
            const showFile = file => {
                if (!file || !file.type.startsWith('image/')) return;
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(file);
                preview.src = previewUrl;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
                filename.textContent = file.name;
            };

            input.addEventListener('change', () => showFile(input.files[0]));
            dropzone.addEventListener('dragover', event => {
                event.preventDefault();
                dropzone.classList.add('border-blue-500', 'bg-blue-50');
            });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-500', 'bg-blue-50'));
            dropzone.addEventListener('drop', event => {
                event.preventDefault();
                dropzone.classList.remove('border-blue-500', 'bg-blue-50');
                const file = event.dataTransfer.files[0];
                if (!file || !file.type.startsWith('image/')) return;

                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                showFile(file);
            });
        });
    </script>
@endpush
