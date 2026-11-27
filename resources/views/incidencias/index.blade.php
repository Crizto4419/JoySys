@extends('layouts.app')

@section('title', 'Incidencias de inventario')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Incidencias de inventario"
        description="Consulta y da seguimiento a los reportes de tu sucursal."
    >
        <x-slot:actions>
            @can('joysys.permiso', 'incidencias.reportar')
                <x-ui.button :href="route('incidencias.create')">
                    Reportar incidencia
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('inventario._mensajes')
    @include('inventario._navegacion')

        @include('inventario._filtros', ['pantalla' => 'incidencias'])

    <x-ui.alert variant="info">
        Reportar o resolver una incidencia no modifica automáticamente las existencias.
    </x-ui.alert>

    <x-ui.card title="Incidencias registradas">
        @if ($incidencias->isEmpty())
            <x-ui.empty-state
                title="Sin incidencias"
                description="No hay incidencias registradas en esta sucursal."
                icon="alert"
            />
        @else
            <x-ui.table :headers="[
                'Folio interno',
                'Fecha',
                'Producto',
                'Tipo',
                'Descripción',
                'Reportado por',
                'Estado',
                'Acciones'
            ]">
                @foreach ($incidencias as $incidencia)
                    <tr>
                        <td>#{{ $incidencia->id }}</td>
                        <td>
                            {{ $incidencia->created_at?->copy()
                                ->timezone('America/Mexico_City')
                                ->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td>{{ $incidencia->producto?->nombre ?? '—' }}</td>
                        <td>
                            {{ $tipos[$incidencia->tipo_incidencia]
                                ?? $incidencia->tipo_incidencia }}
                        </td>
                        <td class="whitespace-pre-wrap break-words">
                            {{ $incidencia->descripcion }}
                        </td>
                        <td>{{ $incidencia->usuarioReporta?->username ?? '—' }}</td>
                        <td>
                            <x-ui.badge :variant="match ($incidencia->estado) {
                                'abierta' => 'danger',
                                'en_revision' => 'warning',
                                'resuelta' => 'success',
                                default => 'neutral'
                            }">
                                {{ match ($incidencia->estado) {
                                    'abierta' => 'Abierta',
                                    'en_revision' => 'En revisión',
                                    'resuelta' => 'Resuelta',
                                    default => $incidencia->estado
                                } }}
                            </x-ui.badge>
                        </td>
                        <td>
                            @can('joysys.permiso', 'incidencias.resolver')
                                @if (in_array($incidencia->estado, ['abierta', 'en_revision'], true))
                                    <form
                                        method="POST"
                                        action="{{ route('incidencias.estado', $incidencia) }}"
                                        onsubmit="return confirm('¿Confirmas el cambio de estado?');"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="{{ $incidencia->estado === 'abierta'
                                                ? 'en_revision'
                                                : 'resuelta' }}"
                                        >

                                        <x-ui.button
                                            type="submit"
                                            variant="secondary"
                                            size="sm"
                                        >
                                            {{ $incidencia->estado === 'abierta'
                                                ? 'Iniciar revisión'
                                                : 'Resolver' }}
                                        </x-ui.button>
                                    </form>
                                @else
                                    <span class="js-text-muted">Finalizada</span>
                                @endif
                            @else
                                <span class="js-text-muted">Solo consulta</span>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$incidencias" />
    </x-ui.card>
</div>
@endsection
