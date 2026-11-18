@extends('layouts.menu-customer')
@section('header')<h2 class="text-xl font-semibold text-gray-800">Directorio de VITA</h2>@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
<form method="POST" enctype="multipart/form-data" action="{{ route('customers.directory.update',['customer'=>request()->route('customer')]) }}" class="rounded-lg bg-white p-6 shadow-sm">
@csrf @method('PUT')<input type="hidden" name="revision" value="{{ old('revision',$profile->revision ?? 0) }}">
<h3 class="text-lg font-semibold">Presentación pública de {{ app('customer')->name }}</h3>
<p class="mb-5 mt-2 text-sm text-gray-500">Una tarjeta por cliente. Participar es voluntario. La dirección y la portada son opcionales; puedes retirar tu autorización en cualquier momento.</p>
<p class="mb-5 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">Para aparecer, tu cliente debe estar activo, tener un subdominio válido y una página de inicio pública activa, estar fuera de mantenimiento y no tener una suspensión del directorio. No depende del estado de pago. Sin imagen válida se utiliza una portada de VITA; sin ubicación no se muestra una dirección.</p>
@if($errors->any())<div role="alert" class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($profile?->suspended_at)<p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm">Publicación suspendida por VITA: {{ $profile->suspension_reason }}. Guardar no levanta la suspensión.</p>@endif
<label class="mb-2 block text-sm font-medium" for="directory-description">Descripción pública</label>
<textarea id="directory-description" name="description" rows="4" required maxlength="1500" class="mb-5 w-full rounded-md border-gray-300 shadow-sm">{{ old('description',$profile->description ?? mb_substr($suggestion,0,1500)) }}</textarea>
<div class="grid gap-5 sm:grid-cols-2">
<label class="text-sm font-medium">Identidad visible<select name="brand_display" class="mt-1 w-full rounded-md border-gray-300">@foreach(['both'=>'Logo y nombre','name'=>'Solo nombre','logo'=>'Solo logo'] as $value=>$label)<option value="{{ $value }}" @selected(old('brand_display',$profile->brand_display ?? 'both')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="text-sm font-medium">Origen de la portada<select name="cover_source" class="mt-1 w-full rounded-md border-gray-300">@foreach(['default'=>'Portada de VITA','banner'=>'Imagen pública existente','uploaded'=>'Mi portada subida'] as $value=>$label)<option value="{{ $value }}" @selected(old('cover_source',$profile->cover_source ?? 'default')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="text-sm font-medium">Logo público<select name="logo_id" class="mt-1 w-full rounded-md border-gray-300"><option value="">Usar automáticamente el logo del sitio web</option>@foreach($logos as $logo)<option value="{{ $logo->id }}" @selected(old('logo_id',$profile->logo_id ?? null)==$logo->id)>{{ $logo->name }}</option>@endforeach</select></label>
<label class="text-sm font-medium">Portada pública (opcional)<select name="website_banner_id" class="mt-1 w-full rounded-md border-gray-300"><option value="">Portada predeterminada de VITA</option>@foreach($banners as $banner)<option value="{{ $banner->id }}" @selected(old('website_banner_id',$profile->website_banner_id ?? null)==$banner->id)>{{ $banner->title ?: 'Imagen pública #'.$banner->id }}</option>@endforeach</select></label>
@foreach(['commune'=>'Comuna (opcional)','city'=>'Ciudad (opcional)','region'=>'Región (opcional)','public_address'=>'Dirección pública (opcional)'] as $field=>$label)<label class="text-sm font-medium">{{ $label }}<input name="{{ $field }}" maxlength="{{ $field==='public_address'?255:100 }}" value="{{ old($field,$profile?->$field) }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></label>@endforeach
</div>
<div class="mt-5 grid gap-4 sm:grid-cols-2">
    <p class="text-xs text-gray-500 sm:col-span-2">Para aparecer en «Cerca de mí», agrega las coordenadas de tu ubicación pública. Son opcionales; déjalas vacías si atiendes exclusivamente por telemedicina. Se usan para calcular una distancia aproximada.</p>
    <label class="text-sm font-medium">Latitud (opcional)<input type="number" step="any" min="-90" max="90" name="latitude" value="{{ old('latitude',$profile->latitude ?? '') }}" placeholder="Ej. -32.88" class="mt-1 w-full rounded-md border-gray-300"></label>
    <label class="text-sm font-medium">Longitud (opcional)<input type="number" step="any" min="-180" max="180" name="longitude" value="{{ old('longitude',$profile->longitude ?? '') }}" placeholder="Ej. -71.25" class="mt-1 w-full rounded-md border-gray-300"></label>
</div>
<div class="mt-5" x-data="{ filename: '', previewUrl: '', selectFile() { const file = this.$refs.coverInput.files[0]; if (this.previewUrl) URL.revokeObjectURL(this.previewUrl); this.filename = file?.name || ''; this.previewUrl = file &amp;&amp; ['image/png','image/jpeg','image/webp'].includes(file.type) ? URL.createObjectURL(file) : ''; }, destroy() { if (this.previewUrl) URL.revokeObjectURL(this.previewUrl); } }">
    <label for="directory-cover" role="button" tabindex="0" aria-label="Seleccionar portada del directorio" @keydown.enter.prevent="$refs.coverInput.click()" @keydown.space.prevent="$refs.coverInput.click()" @dragover.prevent @drop.prevent="if ($event.dataTransfer.files.length) { $refs.coverInput.files = $event.dataTransfer.files; selectFile(); }" class="block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 p-6 text-center hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
        <template x-if="!previewUrl"><div class="text-gray-500">
            <i class="fa-solid fa-cloud-arrow-up text-2xl" aria-hidden="true"></i>
            <p>Arrastra una imagen aquí o haz click</p>
            <p class="mt-1 text-xs">PNG, JPG o WebP · Hasta 4 MB y 4096 × 4096 píxeles.</p>
        </div></template>
        <template x-if="previewUrl"><div><p class="mb-2 text-sm text-green-600">Imagen lista</p><img :src="previewUrl" alt="Vista previa de la portada seleccionada" class="mx-auto max-h-32 rounded"><p x-text="filename" class="mt-2 break-all text-xs text-gray-500"></p></div></template>
    </label>
    <input id="directory-cover" x-ref="coverInput" name="cover" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="selectFile()">
    @if($profile?->cover_path)<p class="mt-2 text-xs text-gray-500">Ya tienes una portada guardada. Selecciona «Mi portada subida» para usarla, o «Portada de VITA» para dejar de mostrarla.</p>@endif
    <p class="mt-2 text-xs text-gray-500">La búsqueda siempre usa el nombre de la clínica, incluso si eliges mostrar solo el logo. Sin un logo válido se mostrará el nombre.</p>
</div>
<section class="mt-6 rounded-lg border border-gray-200 p-4" aria-labelledby="directory-contact-heading">
    <h3 id="directory-contact-heading" class="font-semibold text-gray-800">Contacto público y redes sociales</h3>
    <p class="mt-2 text-sm text-gray-500">Autoriza qué datos de tu web podrán mostrarse en la ficha del directorio. No se utilizarán datos administrativos ni de facturación. Puedes cambiar estas opciones en cualquier momento.</p>
    <input type="hidden" name="show_public_contact" value="0">
    <label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" name="show_public_contact" value="1" @checked(old('show_public_contact',$profile->show_public_contact ?? false)) class="mt-1 rounded border-gray-300">Mostrar el teléfono y correo públicos de mi web.</label>
    <div class="mt-2 rounded-md bg-gray-50 p-3 text-sm text-gray-600">
        <p>Teléfono público: <span data-phone-display>{{ $publicContact?->phone ?: 'No configurado' }}</span></p>
        <p class="mt-1 break-words">Correo público: {{ $publicContact?->email ?: 'No configurado' }}</p>
        <p class="mt-2 text-xs">Se utilizan los datos de Barra superior cuando está habilitada. Esta autorización no publica campos vacíos ni habilita la barra de tu web.</p>
    </div>
    <input type="hidden" name="show_public_social_links" value="0">
    <label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" name="show_public_social_links" value="1" @checked(old('show_public_social_links',$profile->show_public_social_links ?? false)) class="mt-1 rounded border-gray-300">Mostrar las redes oficiales configuradas en mi web.</label>
    <p class="mt-2 text-xs text-gray-500">{{ count($publicSocials?->social_links ?? []) }} enlace(s) configurado(s). Puedes administrarlos desde Redes sociales.</p>
</section>
<section class="mt-6 rounded-lg border border-gray-200 p-4" aria-labelledby="directory-branches-heading">
    <h3 id="directory-branches-heading" class="font-semibold text-gray-800">Sedes públicas</h3>
    <p class="mt-2 text-sm text-gray-500">Selecciona las sedes cuyo nombre y dirección autorizas mostrar. No es obligatorio publicar ninguna sede, por ejemplo si atiendes exclusivamente por telemedicina. Solo se ofrecen sedes activas de tu clínica.</p>
    @foreach($branches as $branch)
        @php($entry = collect(old('branches', []))->first(fn($item) => (int)($item['id'] ?? 0) === (int)$branch->id))
        <div class="mt-4 rounded-lg border border-gray-200 p-4">
            <input type="hidden" name="branches[{{ $loop->index }}][id]" value="{{ $branch->id }}">
            <input type="hidden" name="branches[{{ $loop->index }}][publication_enabled]" value="0">
            <label class="flex items-start gap-2 text-sm font-medium"><input type="checkbox" name="branches[{{ $loop->index }}][publication_enabled]" value="1" @checked($entry ? ($entry['publication_enabled'] ?? false) : $branch->directory_publication_enabled) class="mt-1 rounded border-gray-300">Publicar {{ $branch->name }}</label>
            <p class="mt-2 break-words text-sm text-gray-500">Dirección que se mostrará: {{ $branch->address ?: 'Sin dirección configurada' }}</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <label class="text-sm">Comuna pública (opcional)<input name="branches[{{ $loop->index }}][commune]" maxlength="100" value="{{ $entry ? ($entry['commune'] ?? '') : $branch->directory_commune }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></label>
                <label class="text-sm">Región pública (opcional)<input name="branches[{{ $loop->index }}][region]" maxlength="100" value="{{ $entry ? ($entry['region'] ?? '') : $branch->directory_region }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></label>
            </div>
        </div>
    @endforeach
    @if($branches->isEmpty())<p class="mt-3 text-sm text-gray-500">No hay sedes activas para publicar. Puedes guardar tu presentación sin sedes.</p>@endif
</section>
<p class="mt-5 text-sm text-gray-500">El enlace a tu sitio se obtiene automáticamente del subdominio configurado en VITA. No se publican datos de pacientes, profesionales ni datos administrativos.</p>
<input type="hidden" name="publication_enabled" value="0"><label class="mt-5 flex items-start gap-2 text-sm"><input type="checkbox" name="publication_enabled" value="1" @checked(old('publication_enabled',$profile->publication_enabled ?? false)) class="mt-1 rounded border-gray-300">Quiero aparecer en el directorio público.</label>
<label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 rounded border-gray-300">Autorizo expresamente a VITA a publicar esta presentación, ubicación opcional, imágenes y sedes seleccionadas, y los datos de contacto y redes públicas que habilité, en el directorio y permitir su indexación por buscadores. Debo marcar esta autorización cada vez que guarde con publicación habilitada.</label>
<div class="mt-6 flex justify-end"><button class="rounded-md px-5 py-2 font-semibold text-white hover:brightness-90" style="background-color:{{ themeColor(600) }}">Guardar configuración</button></div>
</form></div>
@endsection
