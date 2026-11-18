@once
    @push('scripts')<script src="{{ asset('js/billing-fee-estimate.js') }}?v={{ filemtime(public_path('js/billing-fee-estimate.js')) }}"></script>@endpush
@endonce
