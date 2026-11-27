@extends('layouts.app')

@section('title', 'Inventario')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Inventario"
        description="Consulta las existencias disponibles de tu sucursal."
    />

    @include('inventario._mensajes')
    @include('inventario._navegacion')

        @include('inventario._filtros', ['pantalla' => 'productos'])

    <x-ui.card
        title="Disponibilidad de productos"
        description="El stock se expresa en piezas, unidades o gramos según el tipo de producto."
    >
        @if ($productos->isEmpty())
            <x-ui.empty-state
                title="Sin productos activos"
                description="Registra un producto en el catálogo para comenzar a gestionar su inventario."
                icon="package"
            >
                @can('joysys.permiso', 'catalogos.crear')
                    <x-slot:actions>
                        <x-ui.button :href="route('catalogos.productos.create')">
                            Registrar producto
                        </x-ui.button>
                    </x-slot:actions>
                @endcan
            </x-ui.empty-state>
        @else
            <x-ui.table :headers="[
                'Código',
                'Producto',
                'Categoría',
                'Tipo',
                'Disponible',
                'Stock mínimo',
                'Indicador'
            ]">
                @foreach ($productos as $producto)
                    @php
                        $unidad = match ($producto->tipo) {
                            'individual' => 'pzas.',
                            'por_cantidad' => 'uds.',
                            'por_peso' => 'g',
                            default => '',
                        };

                        $tipo = match ($producto->tipo) {
                            'individual' => 'Individual',
                            'por_cantidad' => 'Por cantidad',
                            'por_peso' => 'Por peso',
                            default => $producto->tipo,
                        };
                    @endphp

                    <tr>
                        <td>{{ $producto->codigo }}</td>
                        <td>{{ $producto->nombre }}</td>
                        <td>{{ $producto->categoria?->nombre ?? '—' }}</td>
                        <td>{{ $tipo }}</td>
                        <td>
                            {{ $producto->tipo === 'por_peso'
                                ? $producto->stock_disponible
                                : bcadd($producto->stock_disponible, '0', 0) }}
                            {{ $unidad }}
                        </td>
                        <td>{{ $producto->stock_minimo }} {{ $unidad }}</td>
                        <td>
                            @switch($producto->indicador_stock)
                                @case('sin_existencias')
                                    <x-ui.badge variant="danger">
                                        Sin existencias
                                    </x-ui.badge>
                                    @break

                                @case('bajo')
                                    <x-ui.badge variant="warning">
                                        Stock bajo
                                    </x-ui.badge>
                                    @break

                                @default
                                    <x-ui.badge variant="success">
                                        Suficiente
                                    </x-ui.badge>
                            @endswitch
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$productos" />
    </x-ui.card>
</div>
@endsection
