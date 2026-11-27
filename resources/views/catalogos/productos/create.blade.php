@extends('layouts.app')

@section('title', 'Nuevo producto')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Nuevo producto"
        description="Registra un producto y su precio inicial."
    />

    @if($errors->any())
        <x-ui.alert variant="danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('catalogos.productos.store') }}">
            @csrf
            @include('catalogos.productos._form')
        </form>
    </x-ui.card>
</div>
@endsection
