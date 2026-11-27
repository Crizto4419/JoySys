@extends('layouts.app')

@section('title', 'Editar producto')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Editar producto"
        :description="$producto->codigo . ' - ' . $producto->nombre"
    />

    @if($errors->any())
        <x-ui.alert variant="danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form
            method="POST"
            action="{{ route('catalogos.productos.update', $producto) }}"
        >
            @csrf
            @method('PUT')

            @include('catalogos.productos._form')
        </form>
    </x-ui.card>
</div>
@endsection
