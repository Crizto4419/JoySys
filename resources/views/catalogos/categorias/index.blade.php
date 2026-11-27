@extends('layouts.app')

@section('title', 'Categorías')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Categorías"
        description="Consulta y administración de categorías de productos."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('catalogos.index')">
                Volver
            </x-ui.button>

            @can('joysys.permiso', 'catalogos.crear')
                <x-ui.button :href="route('catalogos.categorias.create')">
                    Nueva categoría
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
            :action="route('catalogos.categorias.index')"
            :search-value="$filtros['buscar'] ?? ''"
        >
            <x-forms.select
                name="estado"
                label="Estado"
                placeholder="Todos"
                :options="['activa' => 'Activa', 'inactiva' => 'Inactiva']"
                :value="$filtros['estado'] ?? ''"
            />

            <x-forms.select
                name="categoria_padre_id"
                label="Categoría padre"
                placeholder="Todas"
                :options="['principal' => 'Categorías principales'] + $categoriasPadre->pluck('nombre', 'id')->all()"
                :value="$filtros['categoria_padre_id'] ?? ''"
            />
        </x-ui.filters>
    </x-ui.card>

    <x-ui.card>
        @if ($categorias->isEmpty())
            <x-ui.empty-state
                title="Sin categorías"
                description="No se encontraron categorías con los filtros seleccionados."
            />
        @else
            <x-ui.table :headers="['Nombre', 'Categoría padre', 'Subcategorías', 'Productos', 'Estado', 'Acciones']">
                @foreach ($categorias as $categoria)
                    <tr>
                        <td class="p-3">{{ $categoria->nombre }}</td>
                        <td class="p-3">{{ $categoria->categoriaPadre?->nombre ?? 'Principal' }}</td>
                        <td class="p-3">{{ $categoria->subcategorias_count }}</td>
                        <td class="p-3">{{ $categoria->productos_count }}</td>
                        <td class="p-3">
                            <x-ui.badge :variant="$categoria->estado === 'activa' ? 'success' : 'neutral'">
                                {{ ucfirst($categoria->estado) }}
                            </x-ui.badge>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-2">
                                @can('joysys.permiso', 'catalogos.editar')
                                    <x-ui.button size="sm" variant="secondary"
                                        :href="route('catalogos.categorias.edit', $categoria)">
                                        Editar
                                    </x-ui.button>
                                @endcan

                                @can('joysys.permiso', 'catalogos.cambiar_estado')
                                    <form method="POST" action="{{ route('catalogos.categorias.estado', $categoria) }}">
                                        @csrf
                                        @method('PATCH')

                                        <input type="hidden" name="estado"
                                            value="{{ $categoria->estado === 'activa' ? 'inactiva' : 'activa' }}">

                                        <x-ui.button type="submit" size="sm" variant="secondary">
                                            {{ $categoria->estado === 'activa' ? 'Desactivar' : 'Activar' }}
                                        </x-ui.button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$categorias" />
    </x-ui.card>
</div>
@endsection
