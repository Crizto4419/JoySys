@extends('layouts.app')

@section('title', 'Precios')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Precios"
        description="Consulta los precios vigentes de los productos."
    />

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
            :action="route('precios.index')"
            :search-value="$filtros['buscar'] ?? ''"
            search-placeholder="Buscar por codigo o nombre..."
        >
            <x-forms.select
                name="estado"
                label="Estado del producto"
                placeholder="Todos"
                :options="[
                    'activo' => 'Activo',
                    'inactivo' => 'Inactivo',
                ]"
                :value="$filtros['estado'] ?? null"
            />
        </x-ui.filters>
    </x-ui.card>

    <x-ui.card title="Precios vigentes">
        @if($productos->count())
            <x-ui.table :headers="[
                'Codigo',
                'Producto',
                'Categoria',
                'Precio vigente',
                'Vigente desde',
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
                            @if($producto->precioVigente)
                                ${{ $producto->precioVigente->precio }} MXN
                            @else
                                Sin precio
                            @endif
                        </td>
                        <td>
                            {{ $producto->precioVigente?->vigente_desde?->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i:s') ?? '-' }}
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
                                @can('joysys.permiso', 'precios.modificar')
                                    @if($producto->precioVigente)
                                        <x-ui.button
                                            size="sm"
                                            variant="secondary"
                                            :href="route('precios.edit', $producto)"
                                        >
                                            Modificar
                                        </x-ui.button>
                                    @endif
                                @endcan

                                @can('joysys.permiso', 'precios.ver_historial')
                                    <x-ui.button
                                        size="sm"
                                        variant="secondary"
                                        :href="route('precios.historial', $producto)"
                                    >
                                        Historial
                                    </x-ui.button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty-state
                title="No hay precios registrados"
                description="Los precios apareceran al registrar productos."
                icon="tag"
            />
        @endif

        <x-ui.pagination :paginator="$productos"/>
    </x-ui.card>
</div>
@endsection
