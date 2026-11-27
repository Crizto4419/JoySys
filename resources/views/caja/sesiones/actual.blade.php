@extends('layouts.app')

@section('title', 'Mi sesión de caja')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header
            title="Mi sesión de caja"
            description="Consulta la información de tu sesión abierta."
        />

        @include('caja._navegacion')
        @include('inventario._mensajes')

        @if ($sesion === null)
            <x-ui.card>
                <x-ui.empty-state
                    title="No tienes una sesión abierta en esta sucursal"
                    description="Puedes iniciar una sesión si existe una caja disponible."
                />

                @can('joysys.permiso', 'caja.abrir')
                    <div class="mt-4">
                        <x-ui.button :href="route('caja.sesion.create')">
                            Abrir caja
                        </x-ui.button>
                    </div>
                @endcan
            </x-ui.card>
        @else
            <x-ui.card title="Sesión abierta">
                <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-sm opacity-70">Identificador</dt>
                        <dd class="mt-1 font-medium">#{{ $sesion->id }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm opacity-70">Caja</dt>
                        <dd class="mt-1 font-medium">
                            {{ $sesion->caja->codigo }} -
                            {{ $sesion->caja->nombre }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm opacity-70">Sucursal</dt>
                        <dd class="mt-1">{{ $sesion->sucursal->nombre }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm opacity-70">Fondo inicial</dt>
                        <dd class="mt-1 text-xl font-semibold">
                            ${{ $sesion->fondo_inicial }} MXN
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm opacity-70">Fecha de apertura</dt>
                        <dd class="mt-1">
                            {{ $sesion->fecha_apertura?->copy()->timezone(config('dashboard.zona_horaria', 'America/Mexico_City'))->format('d/m/Y H:i:s') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm opacity-70">Estado</dt>
                        <dd class="mt-1">
                            <x-ui.badge variant="success">
                                Abierta
                            </x-ui.badge>
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.alert variant="info">
                Tu sesión de caja permanece abierta aunque cierres
                tu sesión de acceso a JoySys.
            </x-ui.alert>
        @endif
    </div>
@endsection
