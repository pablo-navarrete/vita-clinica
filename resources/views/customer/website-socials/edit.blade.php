@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Agregar red social</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if (session('success'))<div class="rounded-md bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('customers.website-socials.update', ['customer' => request()->route('customer')]) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Enlaces de redes sociales</h3>
                        <p class="mt-1 text-sm text-gray-600">Agrega las redes y sus enlaces. Su aparici&oacute;n en el footer y en la barra superior se configura en sus respectivos m&oacute;dulos.</p>
                    </div>
                    <button id="add-social-link" type="button" class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color: {{ themeColor(600) }}">+ Agregar red</button>
                </div>

                @php($socialLinks = old('social_links', $settings->social_links ?? []))
                <div id="social-links" class="space-y-3">
                    @foreach ($socialLinks as $index => $social)
                        <div class="social-link-row grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_2fr_auto]">
                            <select name="social_links[{{ $index }}][network]" class="rounded-md border-gray-300 shadow-sm" required>
                                @foreach ($networks as $key => $network)
                                    <option value="{{ $key }}" @selected(($social['network'] ?? '') === $key)>{{ $network['label'] }}</option>
                                @endforeach
                            </select>
                            <input name="social_links[{{ $index }}][url]" type="url" value="{{ $social['url'] ?? '' }}" required maxlength="500" class="rounded-md border-gray-300 shadow-sm" placeholder="https://...">
                            <button type="button" class="remove-social-link rounded-md bg-red-100 px-3 py-2 text-sm text-red-700">Quitar</button>
                        </div>
                    @endforeach
                </div>
                @error('social_links')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @foreach ($errors->get('social_links.*') as $messages)
                    @foreach ($messages as $message)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@endforeach
                @endforeach
            </section>
            <div class="flex flex-wrap justify-between gap-3 pb-6">
                <a href="{{ route('customers.menus.index', ['customer' => request()->route('customer')]) }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700">Volver a Administración Web</a>
                <button type="submit" class="rounded-md px-5 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">Guardar redes sociales</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const list = document.getElementById('social-links');
            const networks = @json($networks);
            let nextIndex = {{ count($socialLinks) }};
            document.getElementById('add-social-link').addEventListener('click', () => {
                const row = document.createElement('div');
                row.className = 'social-link-row grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_2fr_auto]';
                const select = document.createElement('select');
                select.name = `social_links[${nextIndex}][network]`; select.required = true; select.className = 'rounded-md border-gray-300 shadow-sm';
                Object.entries(networks).forEach(([key, network]) => { const option = document.createElement('option'); option.value = key; option.textContent = network.label; select.append(option); });
                const input = document.createElement('input'); input.name = `social_links[${nextIndex}][url]`; input.type = 'url'; input.required = true; input.maxLength = 500; input.placeholder = 'https://...'; input.className = 'rounded-md border-gray-300 shadow-sm';
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'remove-social-link rounded-md bg-red-100 px-3 py-2 text-sm text-red-700'; remove.textContent = 'Quitar';
                row.append(select, input, remove); list.append(row); nextIndex++;
            });
            list.addEventListener('click', (event) => {
                const button = event.target.closest('.remove-social-link');
                if (button) button.closest('.social-link-row').remove();
            });
        })();
    </script>
@endpush
