@extends('layouts.app')

@section('title', 'Detalle de caja')

@section('content')
    @php
        $ocupada = $caja->sesiones_abiertas_count > 0;
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            :title="$caja->nombre"
            :description="'Caja ' . $caja->codigo"
        >
            <x-slot:actions>
                @can('joysys.caja.administrar')
                    @unless ($ocupada)
                        <x-ui.button :href="route('caja.edit', $caja)">
                            Editar caja
                        </x-ui.button>
                    @endunless
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @include('caja._navegacion')
        @include('inventario._mensajes')

        <x-ui.card title="Información general">
            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm opacity-70">Código</dt>
                    <dd class="mt-1 font-medium">{{ $caja->codigo }}</dd>
                </div>

                <div>
                    <dt class="text-sm opacity-70">Nombre</dt>
                    <dd class="mt-1 font-medium">{{ $caja->nombre }}</dd>
                </div>

                <div>
                    <dt class="text-sm opacity-70">Moneda</dt>
                    <dd class="mt-1">{{ $caja->moneda }}</dd>
                </div>

                <div>
                    <dt class="text-sm opacity-70">Estado</dt>
                    <dd class="mt-1">
                        <x-ui.badge
                            :variant="$caja->estado === 'activa' ? 'success' : 'neutral'"
                        >
                            {{ ucfirst($caja->estado) }}
                        </x-ui.badge>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm opacity-70">Disponibilidad</dt>
                    <dd class="mt-1">
                        {{ $ocupada ? 'Ocupada' : ($caja->estado === 'activa' && $caja->moneda === 'MXN' ? 'Disponible' : 'No disponible') }}
                    </dd>
                </div>
            </dl>
        </x-ui.card>

        @can('joysys.caja.administrar')
            <x-ui.card title="Administración del estado">
                @if ($ocupada)
                    <x-ui.alert variant="warning">
                        No se permite cambiar el estado mientras exista
                        una sesión abierta.
                    </x-ui.alert>
                @else
                    <form
                        method="POST"
                        action="{{ route('caja.estado', $caja) }}"
                        onsubmit="return confirm('¿Confirmas el cambio de estado de esta caja?');"
                    >
                        @csrf
                        @method('PATCH')

                        <x-forms.select
                            name="estado"
                            label="Estado de la caja"
                            :value="$caja->estado"
                            :options="[
                                'activa' => 'Activa',
                                'inactiva' => 'Inactiva',
                                'mantenimiento' => 'Mantenimiento',
                            ]"
                            required
                        />

                        <div class="mt-4">
                            <x-ui.button type="submit">
                                Cambiar estado
                            </x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card>
        @endcan
    </div>
@endsection
