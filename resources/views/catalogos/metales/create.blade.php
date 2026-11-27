@extends('layouts.app')

@section('title', 'Nuevo metal')

@section('content')
<div class="js-stack">
    <x-ui.page-header title="Nuevo metal" />

    @if ($errors->any())
        <x-ui.alert variant="danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('catalogos.metales.store') }}">
            @csrf


            @include('catalogos.metales._form')
        </form>
    </x-ui.card>
</div>
@endsection
