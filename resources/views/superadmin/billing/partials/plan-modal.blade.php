<div id="billing-plan-modal" hidden role="dialog" aria-modal="true" aria-labelledby="billing-plan-modal-title"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none">
    <div data-plan-modal-panel class="flex w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-lg" style="max-height:90vh">
        <div class="flex shrink-0 items-center justify-between border-b rounded-t-lg bg-blue-100 px-6 py-4">
            <h2 id="billing-plan-modal-title" class="text-lg font-bold text-gray-800"></h2>
            <button type="button" data-close-plan aria-label="Cerrar" class="px-2 py-1 text-gray-500 hover:text-gray-800"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="min-h-0 overflow-y-auto">
            <div data-plan-errors role="alert" hidden class="mx-6 mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"></div>
            <div data-plan-modal-content></div>
        </div>
        <div data-plan-view-footer class="flex shrink-0 justify-end border-t px-6 py-4">
            <button type="button" data-close-plan class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Cerrar</button>
        </div>
    </div>
</div>
<template id="billing-plan-form-template">
    @include('superadmin.billing.partials.plan-form', ['plan' => new \App\Models\BillingPlan])
</template>
<template id="billing-plan-summary-template">
    <section class="rounded-lg border border-gray-200 p-4">
        <h3 data-summary-name class="font-semibold text-gray-800"></h3>
        <p data-summary-code class="mt-2 text-sm text-gray-600"></p>
        <p data-summary-status class="mt-2 text-sm text-gray-600"></p>
        <p data-summary-description class="mt-3 whitespace-pre-wrap text-sm text-gray-600"></p>
        <p data-summary-versions class="mt-3 text-sm text-gray-500"></p>
    </section>
</template>
