@extends('layouts.app')

@section('title', 'Registrar caja')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header
            title="Registrar caja"
            description="Crea una caja para la sucursal operativa."
        />

        @include('caja._navegacion')
        @include('inventario._mensajes')

        <x-ui.card title="Datos de la caja">
            <form method="POST" action="{{ route('caja.store') }}">
                @csrf

                @include('caja._formulario', ['caja' => null])

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.button type="submit">
                        Guardar caja
                    </x-ui.button>

                    <x-ui.button
                        variant="secondary"
                        :href="route('caja.index')"
                    >
                        Cancelar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
