@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Firma electrónica</h2>
@endsection

@section('content')
    <div class="px-4 pb-8 sm:px-6 lg:px-8">
        @include('customer.user.partials.electronic-signature')
    </div>
@endsection
