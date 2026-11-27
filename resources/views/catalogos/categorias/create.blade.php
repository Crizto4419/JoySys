@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('content')
<div class="js-stack">
    <x-ui.page-header title="Nueva categoría" />

    @if ($errors->any())
        <x-ui.alert variant="danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('catalogos.categorias.store') }}">
            @csrf


            @include('catalogos.categorias._form')
        </form>
    </x-ui.card>
</div>
@endsection
