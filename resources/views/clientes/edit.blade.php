@extends('layouts.app')

@section('title', 'Editar cliente')

@section('content')
    <x-ui.page-header
        title="Editar cliente"
        description="Actualiza los datos del cliente seleccionado."
    >
        <x-slot:actions>
            <x-ui.button
                :href="route('clientes.show', $cliente)"
                variant="secondary"
            >
                Volver al detalle
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('inventario._mensajes')

    @if (!$cliente->activo)
        <x-ui.alert variant="warning">
            Este cliente está inactivo. Editar sus datos no modificará su estado.
        </x-ui.alert>
    @endif

    <x-ui.card title="Datos del cliente">
        <form
            method="POST"
            action="{{ route('clientes.update', $cliente) }}"
            class="space-y-5"
        >
            @csrf
            @method('PUT')

            @include('clientes._formulario', ['cliente' => $cliente])

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary">
                    Guardar cambios
                </x-ui.button>

                <x-ui.button
                    :href="route('clientes.show', $cliente)"
                    variant="secondary"
                >
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
