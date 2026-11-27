@extends('layouts.app')

@section('title', 'Editar metal')

@section('content')
<div class="js-stack">
    <x-ui.page-header title="Editar metal" />

    @if ($errors->any())
        <x-ui.alert variant="danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('catalogos.metales.update', $metal) }}">
            @csrf
            @method('PUT')

            @include('catalogos.metales._form')
        </form>
    </x-ui.card>
</div>
@endsection
