@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <x-ui.page-header
        title="Clientes"
        description="Consulta y administra el catálogo de clientes."
    >
        <x-slot:actions>
            @can('joysys.permiso', 'clientes.crear')
                <x-ui.button
                    :href="route('clientes.create')"
                    variant="primary"
                >
                    Registrar cliente
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('inventario._mensajes')

    <x-ui.card title="Filtros">
        <x-ui.filters
            :action="route('clientes.index')"
            :search-value="request()->query('buscar', '')"
            search-placeholder="Código, nombre, teléfono o correo"
        >
            <x-forms.select
                name="estado"
                label="Estado"
                :options="[
                    'activo' => 'Activo',
                    'inactivo' => 'Inactivo',
                ]"
                :value="request()->query('estado')"
                placeholder="Todos los estados"
            />
        </x-ui.filters>
    </x-ui.card>

    <x-ui.card title="Listado de clientes">
        @if ($clientes->isEmpty())
            <x-ui.empty-state
                title="Sin resultados"
                description="No se encontraron clientes con los criterios seleccionados."
            />
        @else
            <x-ui.table
                :headers="[
                    'Código',
                    'Nombre',
                    'Teléfono',
                    'Correo',
                    'Estado',
                    'Acciones',
                ]"
                caption="Catálogo de clientes"
            >
                @foreach ($clientes as $cliente)
                    <tr>
                        <td>{{ $cliente->codigo ?? '—' }}</td>
                        <td>{{ $cliente->nombre }}</td>
                        <td>{{ $cliente->telefono ?? '—' }}</td>
                        <td>{{ $cliente->correo ?? '—' }}</td>
                        <td>
                            <x-ui.badge
                                :variant="$cliente->activo ? 'success' : 'neutral'"
                            >
                                {{ $cliente->activo ? 'Activo' : 'Inactivo' }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <x-ui.button
                                    :href="route('clientes.show', $cliente)"
                                    variant="secondary"
                                    size="sm"
                                >
                                    Ver
                                </x-ui.button>

                                @can('joysys.permiso', 'clientes.editar')
                                    <x-ui.button
                                        :href="route('clientes.edit', $cliente)"
                                        variant="secondary"
                                        size="sm"
                                    >
                                        Editar
                                    </x-ui.button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <x-ui.pagination :paginator="$clientes" />
        @endif
    </x-ui.card>
@endsection
