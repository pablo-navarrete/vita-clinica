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
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800&display=swap" rel="stylesheet">

        <!-- FontAwesome -->
        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

        <!-- DataTables CSS -->
        <link rel="stylesheet"
            href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

        <!-- DataTables Responsive CSS -->
        <link rel="stylesheet"
            href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            .dataTables_length select {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                background-image: none !important;
            }

            /* ===== DATATABLE ===== */

            .dataTables_wrapper {
                padding-top: 10px;
            }

            /* BUSCADOR */
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
                box-shadow: 0 0 0 3px rgba(59, 130, 246, .2) !important;
            }

            /* SELECT */
            .dataTables_length {
                margin-bottom: 20px !important;
            }

            .dataTables_length select {
                border: 1px solid #d1d5db !important;
                border-radius: 8px !important;
                padding: 5px 10px !important;
            }

            /* TABLA */
            table.dataTable {
                border-collapse: collapse !important;
            }

            table.dataTable thead th {
                background: #dbeafe !important;
                color: #1e3a8a;
                font-weight: 700;
                padding: 14px !important;
                border-bottom: 2px solid #bfdbfe !important;
            }

            table.dataTable tbody td {
                padding: 14px !important;
                border-bottom: 1px solid #f3f4f6;
            }

            table.dataTable tbody tr:hover {
                background-color: #f9fafb !important;
            }

            /* PAGINACIÓN */
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

            /* INFO */
            .dataTables_info {
                margin-top: 20px;
                color: #6b7280;
            }
              [x-cloak] {
        display: none !important;
    }
        </style>
    </head>

    <body class="font-sans antialiased">

        <div class="min-h-screen bg-gray-100">

            @include('layouts.navigation')

            <!-- HEADER -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- CONTENT -->
            <main>
                {{ $slot }}
            </main>

        </div>

        <!-- JQUERY -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

        <!-- DATATABLES -->
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

        <!-- RESPONSIVE -->
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

        <!-- SWEET ALERT -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <!-- STACK -->
        @stack('scripts')

    </body>
</html>