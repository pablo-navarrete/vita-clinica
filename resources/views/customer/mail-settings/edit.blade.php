@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Notificaciones a pacientes</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="rounded-md bg-green-100 px-4 py-3 text-green-800" role="status">{{ session('success') }}</div>
        @endif

        <section class="rounded-xl border border-blue-100 bg-blue-50 p-5 text-sm text-blue-900">
            <p class="font-semibold">Configura cómo enviar las notificaciones a pacientes</p>
            <p class="mt-1">Esta preferencia se aplica a confirmaciones, cambios y cancelaciones de citas, avisos de atención y enlaces de telemedicina. Vita envía los correos desde su servicio central. WhatsApp requiere que Vita conecte su cuenta central de WhatsApp Business y que el paciente haya autorizado recibir mensajes.</p>
        </section>

        <form method="POST" action="{{ route('customers.mail-settings.update', ['customer' => request()->route('customer')]) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">Correo y WhatsApp de la clinica</h3>

                <label for="reply-to-email" class="mb-1 block text-sm font-medium text-gray-700">Correo de contacto de la clinica</label>
                <input id="reply-to-email" name="reply_to_email" type="email" maxlength="160" value="{{ old('reply_to_email', $replyEmail) }}" class="w-full rounded-md border-gray-300 shadow-sm" placeholder="clinica@gmail.com">
                @error('reply_to_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-gray-500">Es independiente del correo de la pagina publica. Vita envia las invitaciones desde la cuenta central y las respuestas del paciente llegan a esta direccion. Esta configuracion no abre ni lee la bandeja de entrada.</p>

                <label for="reply-whatsapp" class="mb-1 mt-5 block text-sm font-medium text-gray-700">WhatsApp de contacto de la clinica</label>
                <input id="reply-whatsapp" name="reply_whatsapp" type="tel" maxlength="40" value="{{ old('reply_whatsapp', $whatsappNumber) }}" class="w-full rounded-md border-gray-300 shadow-sm" placeholder="56912345678">
                @error('reply_whatsapp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-gray-500">Incluye el codigo de pais. Se agregara al mensaje para que el paciente pueda contactar a la clinica.</p>

                <label for="telemedicine-delivery" class="mb-1 mt-5 block text-sm font-medium text-gray-700">Canales para notificaciones de citas y telemedicina</label>
                <select id="telemedicine-delivery" name="telemedicine_delivery" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="email" @selected(old('telemedicine_delivery', $delivery) === 'email')>Solo correo</option>
                    <option value="whatsapp" @selected(old('telemedicine_delivery', $delivery) === 'whatsapp')>Solo WhatsApp</option>
                    <option value="both" @selected(old('telemedicine_delivery', $delivery) === 'both')>Correo y WhatsApp</option>
                </select>
                @error('telemedicine_delivery')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </section>

            <div class="flex justify-end pb-6">
                <button type="submit" class="rounded-md px-5 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">Guardar canales</button>
            </div>
        </form>
    </div>
@endsection
