@extends('layouts.app')

@section('title', 'Editar categoría')

@section('content')
<div class="js-stack">
    <x-ui.page-header title="Editar categoría" />

    @if ($errors->any())
        <x-ui.alert variant="danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('catalogos.categorias.update', $categoria) }}">
            @csrf
            @method('PUT')

            @include('catalogos.categorias._form')
        </form>
    </x-ui.card>
</div>
@endsection
