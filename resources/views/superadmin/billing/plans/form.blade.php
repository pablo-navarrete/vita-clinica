<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold text-gray-800">{{ $plan->exists ? 'Editar plan' : 'Crear plan' }}</h2><a href="{{ route('superadmin.billing.plans.index') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-sm">Volver a planes</a></div></x-slot>
    <div class="p-6"><div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">@include('superadmin.billing.partials.errors')
        @include('superadmin.billing.partials.plan-form')
    </div>
    </div>
    @push('scripts')
        <script src="{{ asset('js/billing-plan-form.js') }}?v={{ filemtime(public_path('js/billing-plan-form.js')) }}"></script>
    @endpush
</x-app-layout>
