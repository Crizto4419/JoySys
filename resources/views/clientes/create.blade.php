@extends('layouts.app')

@section('title', 'Registrar cliente')

@section('content')
    <x-ui.page-header
        title="Registrar cliente"
        description="Agrega un nuevo cliente al catálogo general."
    >
        <x-slot:actions>
            <x-ui.button
                :href="route('clientes.index')"
                variant="secondary"
            >
                Volver al listado
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('inventario._mensajes')

    <x-ui.card
        title="Datos del cliente"
        description="El nombre es obligatorio. Los demás campos son opcionales."
    >
        <form
            method="POST"
            action="{{ route('clientes.store') }}"
            class="space-y-5"
        >
            @csrf

            @include('clientes._formulario', ['cliente' => null])

            <x-ui.alert variant="info">
                El cliente se registrará inicialmente como activo.
            </x-ui.alert>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary">
                    Guardar cliente
                </x-ui.button>

                <x-ui.button
                    :href="route('clientes.index')"
                    variant="secondary"
                >
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
