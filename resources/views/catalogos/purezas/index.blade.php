@extends('layouts.app')

@section('title', 'Purezas')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Purezas"
        description="Consulta de kilatajes y leyes de los metales."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('catalogos.index')">
                Volver
            </x-ui.button>

            @can('joysys.permiso', 'catalogos.crear')
                <x-ui.button :href="route('catalogos.purezas.create')">
                    Nueva pureza
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
            :action="route('catalogos.purezas.index')"
            :search-value="$filtros['buscar'] ?? ''"
        >
            <x-forms.select
                name="metal_id"
                label="Metal"
                placeholder="Todos"
                :options="$metales->pluck('nombre', 'id')->all()"
                :value="$filtros['metal_id'] ?? ''"
            />
        </x-ui.filters>
    </x-ui.card>

    <x-ui.card>
        @if ($purezas->isEmpty())
            <x-ui.empty-state
                title="Sin purezas"
                description="No se encontraron registros."
            />
        @else
            <x-ui.table :headers="['Metal', 'Código', 'Nombre', 'Productos', 'Piezas', 'Disponibilidad', 'Acciones']">
                @foreach ($purezas as $pureza)
                    <tr>
                        <td class="p-3">{{ $pureza->metal->nombre }}</td>
                        <td class="p-3">{{ $pureza->codigo }}</td>
                        <td class="p-3">{{ $pureza->nombre }}</td>
                        <td class="p-3">{{ $pureza->productos_count }}</td>
                        <td class="p-3">{{ $pureza->piezas_count }}</td>
                        <td class="p-3">
                            <x-ui.badge :variant="$pureza->metal->estado === 'activo' ? 'success' : 'neutral'">
                                {{ $pureza->metal->estado === 'activo' ? 'Disponible' : 'No disponible' }}
                            </x-ui.badge>
                        </td>
                        <td class="p-3">
                            @can('joysys.permiso', 'catalogos.editar')
                                <x-ui.button size="sm" variant="secondary"
                                    :href="route('catalogos.purezas.edit', $pureza)">
                                    {{ $pureza->productos_count + $pureza->piezas_count > 0 ? 'Consultar' : 'Editar' }}
                                </x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$purezas" />
    </x-ui.card>
</div>
@endsection
