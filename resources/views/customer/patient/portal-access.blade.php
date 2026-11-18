@extends('layouts.menu-customer')
@section('header')<h2 class="text-xl font-semibold text-gray-800">Acceso del paciente a Mi Salud</h2>@endsection
@section('content')
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <a class="text-sm font-semibold text-blue-700" href="{{ route('patients.medical-record.show',['customer'=>request()->route('customer'),'id'=>$patient->id]) }}">← Volver a la ficha</a>
    <section class="rounded-xl border border-gray-200 bg-white p-6"><h1 class="text-xl font-semibold">{{ $patient->name }}</h1><p class="mt-2 text-sm text-gray-600">Habilita a la cuenta personal verificada del paciente para consultar la ficha de este centro, sus atenciones firmadas y los documentos asociados. No podrá editar estos registros.</p></section>
    @if(session('success'))<p class="rounded-lg bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-4 text-sm text-red-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('patients.portal-access.store',['customer'=>request()->route('customer'),'id'=>$patient->id]) }}" class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">@csrf
        <h2 class="text-lg font-semibold">Habilitar acceso</h2>
        <p class="text-sm text-gray-600">Confirma la identidad con el paciente. La coincidencia del documento y la verificación del correo son controles adicionales y no reemplazan esa comprobación.</p>
        <div><label class="mb-1 block text-sm font-medium" for="portal-access-email">Correo de la cuenta Mi Salud del paciente</label><input id="portal-access-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off" class="w-full rounded-lg border-gray-300"></div>
        <div><label class="mb-1 block text-sm font-medium" for="portal-access-method">Cómo comprobaste la identidad</label><select id="portal-access-method" name="verification_method" required class="w-full rounded-lg border-gray-300"><option value="">Selecciona una opción</option><option value="in_person" @selected(old('verification_method')==='in_person')>Verificación presencial</option><option value="verified_contact" @selected(old('verification_method')==='verified_contact')>Verificación mediante un canal del paciente previamente acreditado por la clínica</option></select></div>
        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="identity_confirmed" value="1" required class="mt-1 rounded border-gray-300" @checked(old('identity_confirmed'))><span>He comprobado que esta cuenta pertenece al paciente y que solicita consultar su ficha en Mi Salud. Autorizo ese acceso en nombre de este centro.</span></label>
        <div><label class="mb-1 block text-sm font-medium" for="portal-access-password">Tu contraseña de administrador</label><input id="portal-access-password" type="password" name="password" required maxlength="1024" autocomplete="current-password" class="w-full rounded-lg border-gray-300"></div>
        <button type="submit" class="rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white">Habilitar ficha y atenciones firmadas</button>
    </form>
    <section class="rounded-xl border border-gray-200 bg-white p-6"><h2 class="text-lg font-semibold">Habilitaciones y revocaciones</h2><div class="mt-4 space-y-4">
        @forelse($grants as $grant)<article class="rounded-lg border border-gray-200 p-4"><p class="break-words font-semibold">{{ $grant->account?->email }}</p><p class="mt-1 text-sm text-gray-600">Habilitado por {{ $grant->approver?->name ?? 'Administración del centro' }} · {{ $grant->approved_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
            @if($grant->revoked_at)<p class="mt-2 text-sm text-gray-500">Revocado el {{ $grant->revoked_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.</p>
            @else<form method="POST" action="{{ route('patients.portal-access.revoke',['customer'=>request()->route('customer'),'id'=>$patient->id,'grant'=>$grant->id]) }}" class="mt-4 space-y-3">@csrf @method('DELETE')<label class="block text-sm">Confirma tu contraseña para revocar<input type="password" name="password" required maxlength="1024" autocomplete="current-password" class="mt-1 w-full rounded-lg border-gray-300"></label><button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700">Revocar acceso</button></form>@endif
        </article>@empty<p class="text-sm text-gray-500">Todavía no se ha habilitado ninguna cuenta para esta ficha.</p>@endforelse
    </div></section>
</div>
@endsection
