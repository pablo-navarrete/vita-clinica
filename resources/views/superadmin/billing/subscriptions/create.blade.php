<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Crear suscripción</h2></x-slot>
    <div class="p-6"><div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
@include('superadmin.billing.subscriptions.create-form')
    </div></div>
@include('superadmin.billing.subscriptions.create-assets')
</x-app-layout>