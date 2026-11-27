@extends('layouts.app')

@section('title', 'Metales')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Metales"
        description="Consulta y administración de materiales."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('catalogos.index')">
                Volver
            </x-ui.button>

            @can('joysys.permiso', 'catalogos.crear')
                <x-ui.button :href="route('catalogos.metales.create')">
                    Nuevo metal
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <x-ui.filters
            :action="route('catalogos.metales.index')"
            :search-value="$filtros['buscar'] ?? ''"
        >
            <x-forms.select
                name="estado"
                label="Estado"
                placeholder="Todos"
                :options="['activo' => 'Activo', 'inactivo' => 'Inactivo']"
                :value="$filtros['estado'] ?? ''"
            />
        </x-ui.filters>
    </x-ui.card>

    <x-ui.card>
        @if ($metales->isEmpty())
            <x-ui.empty-state
                title="Sin metales"
                description="No se encontraron registros."
            />
        @else
            <x-ui.table :headers="['Nombre', 'Símbolo', 'Purezas', 'Productos', 'Piezas', 'Estado', 'Acciones']">
                @foreach ($metales as $metal)
                    <tr>
                        <td class="p-3">{{ $metal->nombre }}</td>
                        <td class="p-3">{{ $metal->simbolo ?? '—' }}</td>
                        <td class="p-3">{{ $metal->purezas_count }}</td>
                        <td class="p-3">{{ $metal->productos_count }}</td>
                        <td class="p-3">{{ $metal->piezas_count }}</td>
                        <td class="p-3">
                            <x-ui.badge :variant="$metal->estado === 'activo' ? 'success' : 'neutral'">
                                {{ ucfirst($metal->estado) }}
                            </x-ui.badge>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-2">
                                @can('joysys.permiso', 'catalogos.editar')
                                    <x-ui.button size="sm" variant="secondary"
                                        :href="route('catalogos.metales.edit', $metal)">
                                        Editar
                                    </x-ui.button>
                                @endcan

                                @can('joysys.permiso', 'catalogos.cambiar_estado')
                                    <form method="POST" action="{{ route('catalogos.metales.estado', $metal) }}">
                                        @csrf
                                        @method('PATCH')

                                        <input type="hidden" name="estado"
                                            value="{{ $metal->estado === 'activo' ? 'inactivo' : 'activo' }}">

                                        <x-ui.button type="submit" size="sm" variant="secondary">
                                            {{ $metal->estado === 'activo' ? 'Desactivar' : 'Activar' }}
                                        </x-ui.button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$metales" />
    </x-ui.card>
</div>
@endsection
