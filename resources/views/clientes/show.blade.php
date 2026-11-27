@extends('layouts.app')

@section('title', 'Detalle del cliente')

@section('content')
    <x-ui.page-header
        :title="$cliente->nombre"
        description="Información general del cliente."
    >
        <x-slot:actions>
            @can('joysys.permiso', 'clientes.editar')
                <x-ui.button
                    :href="route('clientes.edit', $cliente)"
                    variant="primary"
                >
                    Editar cliente
                </x-ui.button>
            @endcan

            <x-ui.button
                :href="route('clientes.index')"
                variant="secondary"
            >
                Volver al listado
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('inventario._mensajes')

    <x-ui.card title="Información del cliente">
        <dl class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <dt class="text-sm font-medium">Identificador interno</dt>
                <dd class="mt-1">{{ $cliente->id }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium">Código</dt>
                <dd class="mt-1">{{ $cliente->codigo ?? 'Sin código' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium">Nombre completo</dt>
                <dd class="mt-1">{{ $cliente->nombre }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium">Estado</dt>
                <dd class="mt-1">
                    <x-ui.badge
                        :variant="$cliente->activo ? 'success' : 'neutral'"
                    >
                        {{ $cliente->activo ? 'Activo' : 'Inactivo' }}
                    </x-ui.badge>
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium">Teléfono</dt>
                <dd class="mt-1">{{ $cliente->telefono ?? 'No registrado' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium">Correo electrónico</dt>
                <dd class="mt-1">{{ $cliente->correo ?? 'No registrado' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium">Fecha de registro</dt>
                <dd class="mt-1">
                    {{ $cliente->created_at?->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i') ?? '—' }}
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium">Última actualización</dt>
                <dd class="mt-1">
                    {{ $cliente->updated_at?->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i') ?? '—' }}
                </dd>
            </div>
        </dl>
    </x-ui.card>

    @can('joysys.permiso', 'clientes.desactivar')
        <x-ui.card
            title="Administración del estado"
            description="El cambio de estado conserva los datos y las relaciones históricas del cliente."
        >
            @if ($cliente->activo)
                <x-ui.alert variant="warning">
                    Desactivar al cliente no eliminará su información.
                </x-ui.alert>
            @else
                <x-ui.alert variant="info">
                    El cliente está inactivo. Puedes reactivarlo cuando sea necesario.
                </x-ui.alert>
            @endif

            <form
                method="POST"
                action="{{ route('clientes.estado', $cliente) }}"
                class="mt-4"
                onsubmit="return confirm('¿Confirmas el cambio de estado de este cliente?');"
            >
                @csrf
                @method('PATCH')

                <input
                    type="hidden"
                    name="estado"
                    value="{{ $cliente->activo ? 'inactivo' : 'activo' }}"
                >

                <x-ui.button
                    type="submit"
                    :variant="$cliente->activo ? 'danger' : 'primary'"
                >
                    {{ $cliente->activo ? 'Desactivar cliente' : 'Reactivar cliente' }}
                </x-ui.button>
            </form>
        </x-ui.card>
    @endcan
@endsection
