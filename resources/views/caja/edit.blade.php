@extends('layouts.app')

@section('title', 'Editar caja')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header
            title="Editar caja"
            :description="$caja->codigo . ' - ' . $caja->nombre"
        />

        @include('caja._navegacion')
        @include('inventario._mensajes')

        @if ($caja->sesiones_abiertas_count > 0)
            <x-ui.alert variant="warning">
                Esta caja tiene una sesión abierta.
                No puede modificarse hasta finalizar la sesión.
            </x-ui.alert>
        @else
            <x-ui.card title="Datos de la caja">
                <form method="POST" action="{{ route('caja.update', $caja) }}">
                    @csrf
                    @method('PUT')

                    @include('caja._formulario', ['caja' => $caja])

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button type="submit">
                            Guardar cambios
                        </x-ui.button>

                        <x-ui.button
                            variant="secondary"
                            :href="route('caja.show', $caja)"
                        >
                            Cancelar
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
@endsection
