@extends('layouts.app')

@section('title', 'Nueva pureza')

@section('content')
<div class="js-stack">
    <x-ui.page-header title="Nueva pureza" />

    @if ($errors->any())
        <x-ui.alert variant="danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('catalogos.purezas.store') }}">
            @csrf


            @include('catalogos.purezas._form')
        </form>
    </x-ui.card>
</div>
@endsection
