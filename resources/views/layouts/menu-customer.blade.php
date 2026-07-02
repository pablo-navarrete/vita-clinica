<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'VITA') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- FontAwesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet"
          href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <link rel="stylesheet"
          href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }

        .dataTables_length select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none !important;
        }

        .dataTables_wrapper {
            padding-top: 10px;
        }

        .dataTables_filter {
            margin-bottom: 20px !important;
        }

        .dataTables_filter label {
            font-weight: 600;
            color: #374151;
        }

        .dataTables_filter input {
            margin-left: 10px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 10px !important;
            padding: 8px 14px !important;
            outline: none !important;
            transition: all .2s ease;
        }

        .dataTables_filter input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59,130,246,.2) !important;
        }

        .dataTables_length {
            margin-bottom: 20px !important;
        }

        .dataTables_length select {
            border: 1px solid #d1d5db !important;
            border-radius: 8px !important;
            padding: 5px 10px !important;
        }

        table.dataTable {
            border-collapse: collapse !important;
        }

        table.dataTable thead th {
          
            font-weight: 700;
            padding: 14px !important;
        }

        table.dataTable tbody td {
            padding: 14px !important;
            border-bottom: 1px solid #f3f4f6;
        }

        table.dataTable tbody tr:hover {
            background-color: #f9fafb !important;
        }

        .dataTables_paginate {
            margin-top: 20px !important;
        }

        .paginate_button {
            border-radius: 8px !important;
            margin: 0 3px !important;
        }

        .paginate_button.current {
            background: #DBEAFE !important;
        }

        .dataTables_info {
            margin-top: 20px;
            color: #6b7280;
        }
    </style>
</head>

<body class="font-sans antialiased">
  

<div x-data="{ open: false }" class="min-h-screen flex flex-col " style="background-color: {{ themeColor(50) }}">

    {{-- NAVBAR SUPERIOR --}}
    @include('layouts.navigation-customer')

    <div class="flex flex-1">

        {{-- SIDEBAR --}}
      @include('layouts.sidebar-customer')

        {{-- CONTENIDO PRINCIPAL --}}
        <div class="flex-1 min-w-0">

            {{-- HEADER OPCIONAL --}}
            @if(View::hasSection('header'))
                <header class=" shadow" style="background-color: {{ themeColor(50) }}">
                    <div class="px-6 py-6">
                        @yield('header')
                    </div>
                </header>
            @endif

            {{-- CONTENIDO --}}
            <main class="p-6">
                @yield('content')
            </main>

        </div>

    </div>
@include('layouts.footer-customer')
</div>

<!-- JQUERY -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DATATABLES -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- DATATABLES RESPONSIVE -->
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<!-- SWEET ALERT -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

@stack('scripts')

</body>
</html>