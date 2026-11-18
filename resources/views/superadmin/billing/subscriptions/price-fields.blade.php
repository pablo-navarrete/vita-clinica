<div class="subscription-search-picker min-w-0">
    <label class="mb-1 block text-sm font-medium" for="subscription-price">Plan y periodicidad</label>
    <select id="subscription-price" name="billing_plan_price_id" required class="w-full rounded-lg border-gray-300">
        <option value="">Selecciona un plan</option>
        @foreach($options as $option)
            <option value="{{ $option['id'] }}" data-interval="{{ $option['interval'] }}" data-base="{{ $option['amount'] }}" data-additional="{{ $option['additional'] }}" data-included="{{ $option['included'] }}" @selected(old('billing_plan_price_id') == $option['id'])>{{ $option['name'] }} · {{ $option['interval'] === 'month' ? 'Mensual' : 'Anual' }} · ${{ number_format($option['amount'], 0, ',', '.') }} CLP</option>
        @endforeach
    </select>
</div>
<div>
    <label class="mb-1 block text-sm font-medium" for="subscription-branches">Sedes contratadas</label>
    <input id="subscription-branches" type="number" name="branch_quantity" min="1" max="2147483647" value="{{ old('branch_quantity', $subscription->branch_quantity ?? 1) }}" required class="w-full rounded-lg border-gray-300">
    <p class="mt-1 text-xs text-gray-500">Incluye la sede principal. Esta cantidad no crea ni elimina sedes.</p>
</div>
@unless($deferEstimate ?? false)
    @include('superadmin.billing.subscriptions.estimate')
@endunless
@once
    @push('scripts')<script src="{{ asset('js/billing-subscriptions.js') }}?v={{ filemtime(public_path('js/billing-subscriptions.js')) }}"></script>@endpush
@endonce
