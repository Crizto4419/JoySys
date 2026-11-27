@extends('layouts.app')

@section('title', 'Existencias')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Existencias"
        description="Consulta las cantidades y pesos registrados por estado."
    />

    @include('inventario._mensajes')
    @include('inventario._navegacion')

        @include('inventario._filtros', ['pantalla' => 'existencias'])

    <x-ui.card title="Existencias por producto y estado">
        @if ($existencias->isEmpty())
            <x-ui.empty-state
                title="Sin existencias registradas"
                description="Registra entradas de productos por cantidad o peso."
                icon="boxes"
            />
        @else
            <x-ui.table :headers="[
                'Código',
                'Producto',
                'Estado',
                'Cantidad',
                'Unidad',
                'Última actualización'
            ]">
                @foreach ($existencias as $existencia)
                    <tr>
                        <td>{{ $existencia->producto?->codigo ?? '—' }}</td>
                        <td>{{ $existencia->producto?->nombre ?? '—' }}</td>
                        <td>
                            <x-ui.badge :variant="match ($existencia->estadoInventario?->codigo) {
                                'disponible' => 'success',
                                'perdido', 'danado' => 'danger',
                                'apartado', 'reparacion' => 'warning',
                                default => 'neutral'
                            }">
                                {{ $existencia->estadoInventario?->nombre ?? '—' }}
                            </x-ui.badge>
                        </td>
                        <td>{{ $existencia->cantidad }}</td>
                        <td>
                            {{ $existencia->producto?->tipo === 'por_peso'
                                ? 'Gramos'
                                : 'Unidades' }}
                        </td>
                        <td>
                            {{ $existencia->updated_at?->copy()
                                ->timezone('America/Mexico_City')
                                ->format('d/m/Y H:i') ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$existencias" />
    </x-ui.card>
</div>
@endsection
