@extends('layouts.app')

@section('title', 'Cajas')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header
            title="Cajas"
            description="Consulta las cajas y su disponibilidad en tu sucursal operativa."
        >
            <x-slot:actions>
                @can('joysys.caja.administrar')
                    <x-ui.button :href="route('caja.create')">
                        Registrar caja
                    </x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @include('caja._navegacion')
        @include('inventario._mensajes')

        <x-ui.card title="Buscar cajas">
            <x-ui.filters
                :action="route('caja.index')"
                :search-value="request('buscar')"
                search-placeholder="Código o nombre"
            >
                <x-forms.select
                    name="estado"
                    label="Estado"
                    :value="request('estado')"
                    :options="[
                        '' => 'Todos los estados',
                        'activa' => 'Activa',
                        'inactiva' => 'Inactiva',
                        'mantenimiento' => 'Mantenimiento',
                    ]"
                />
            </x-ui.filters>
        </x-ui.card>

        <x-ui.card title="Cajas de la sucursal">
            @if ($cajas->isEmpty())
                <x-ui.empty-state
                    title="No se encontraron cajas"
                    description="No existen cajas que coincidan con la consulta."
                />
            @else
                <x-ui.table
                    :headers="[
                        'Código',
                        'Nombre',
                        'Moneda',
                        'Estado',
                        'Disponibilidad',
                        'Acciones',
                    ]"
                    caption="Listado de cajas"
                >
                    @foreach ($cajas as $caja)
                        @php
                            $ocupada = $caja->sesiones_abiertas_count > 0;
                            $disponible = $caja->estado === 'activa'
                                && $caja->moneda === 'MXN'
                                && ! $ocupada;
                        @endphp

                        <tr>
                            <td class="px-4 py-3 font-medium">
                                {{ $caja->codigo }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $caja->nombre }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $caja->moneda }}
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge
                                    :variant="match ($caja->estado) {
                                        'activa' => 'success',
                                        'mantenimiento' => 'warning',
                                        default => 'neutral',
                                    }"
                                >
                                    {{ ucfirst($caja->estado) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge
                                    :variant="$disponible ? 'success' : ($ocupada ? 'warning' : 'neutral')"
                                >
                                    {{ $disponible ? 'Disponible' : ($ocupada ? 'Ocupada' : 'No disponible') }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.button
                                        variant="secondary"
                                        size="sm"
                                        :href="route('caja.show', $caja)"
                                    >
                                        Ver
                                    </x-ui.button>

                                    @can('joysys.caja.administrar')
                                        @unless ($ocupada)
                                            <x-ui.button
                                                variant="ghost"
                                                size="sm"
                                                :href="route('caja.edit', $caja)"
                                            >
                                                Editar
                                            </x-ui.button>
                                        @endunless
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif

            <x-ui.pagination :paginator="$cajas" />
        </x-ui.card>
    </div>
@endsection
