@extends('layouts.app')

@section('title', 'Productos')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Productos"
        description="Consulta y administra el catalogo de productos."
    >
        <x-slot:actions>
            <x-ui.button
                variant="secondary"
                :href="route('catalogos.index')"
            >
                Volver
            </x-ui.button>

            @can('joysys.permiso', 'catalogos.crear')
                <x-ui.button
                    :href="route('catalogos.productos.create')"
                >
                    Nuevo producto
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))
        <x-ui.alert variant="success">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert variant="danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card>
        <x-ui.filters
            :action="route('catalogos.productos.index')"
            :search-value="$filtros['buscar'] ?? ''"
            search-placeholder="Buscar por codigo o nombre..."
        >
            <x-forms.select
                name="estado"
                label="Estado"
                placeholder="Todos"
                :options="[
                    'activo' => 'Activo',
                    'inactivo' => 'Inactivo',
                ]"
                :value="$filtros['estado'] ?? null"
            />

            <x-forms.select
                name="tipo"
                label="Tipo de producto"
                placeholder="Todos"
                :options="$tipos"
                :value="$filtros['tipo'] ?? null"
            />

            <x-forms.select
                name="categoria_id"
                label="Categoria"
                placeholder="Todas"
                :options="$categorias->pluck('nombre', 'id')->all()"
                :value="$filtros['categoria_id'] ?? null"
            />

            <x-forms.select
                name="metal_id"
                label="Metal"
                placeholder="Todos"
                :options="$metales->pluck('nombre', 'id')->all()"
                :value="$filtros['metal_id'] ?? null"
            />
        </x-ui.filters>
    </x-ui.card>

    <x-ui.card title="Productos registrados">
        @if($productos->count())
            <x-ui.table :headers="[
                'Codigo',
                'Producto',
                'Categoria',
                'Metal y pureza',
                'Tipo',
                'Estado',
                'Acciones',
            ]">
                @foreach($productos as $producto)
                    <tr>
                        <td>{{ $producto->codigo }}</td>
                        <td>{{ $producto->nombre }}</td>
                        <td>
                            {{ $producto->categoria?->nombre ?? 'No disponible' }}
                        </td>
                        <td>
                            {{ $producto->metal?->nombre ?? 'Sin metal' }}
                            @if($producto->pureza)
                                <span class="js-text-muted">
                                    {{ $producto->pureza->codigo }}
                                </span>
                            @endif
                        </td>
                        <td>
                            {{ $tipos[$producto->tipo] ?? $producto->tipo }}
                        </td>
                        <td>
                            <x-ui.badge
                                :variant="$producto->estado === 'activo' ? 'success' : 'neutral'"
                            >
                                {{ ucfirst($producto->estado) }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <div class="js-actions">
                                @can('joysys.permiso', 'catalogos.editar')
                                    <x-ui.button
                                        variant="secondary"
                                        size="sm"
                                        :href="route('catalogos.productos.edit', $producto)"
                                    >
                                        Editar
                                    </x-ui.button>
                                @endcan

                                @can('joysys.permiso', 'catalogos.cambiar_estado')
                                    <form
                                        method="POST"
                                        action="{{ route('catalogos.productos.estado', $producto) }}"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="{{ $producto->estado === 'activo' ? 'inactivo' : 'activo' }}"
                                        >

                                        <x-ui.button
                                            type="submit"
                                            size="sm"
                                            variant="secondary"
                                        >
                                            {{ $producto->estado === 'activo' ? 'Desactivar' : 'Activar' }}
                                        </x-ui.button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty-state
                title="No hay productos"
                description="No se encontraron productos con los criterios seleccionados."
                icon="package"
            />
        @endif

        <x-ui.pagination :paginator="$productos"/>
    </x-ui.card>
</div>
@endsection
