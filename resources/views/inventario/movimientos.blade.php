@extends('layouts.app')

@section('title', 'Movimientos de inventario')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Movimientos de inventario"
        description="Historial de operaciones realizadas en tu sucursal."
    />

    @include('inventario._mensajes')
    @include('inventario._navegacion')

        @include('inventario._filtros', ['pantalla' => 'movimientos'])

    <x-ui.alert variant="info">
        El historial es de solo lectura. Los errores deben corregirse mediante nuevas operaciones autorizadas.
    </x-ui.alert>

    <x-ui.card title="Kardex">
        @if ($movimientos->isEmpty())
            <x-ui.empty-state
                title="Sin movimientos"
                description="Las operaciones de inventario aparecerán aquí."
                icon="package"
            />
        @else
            <x-ui.table :headers="[
                'Fecha',
                'Producto',
                'Pieza',
                'Movimiento',
                'Cantidad',
                'Origen',
                'Destino',
                'Usuario',
                'Observaciones'
            ]">
                @foreach ($movimientos as $movimiento)
                    <tr>
                        <td>
                            {{ $movimiento->created_at?->copy()
                                ->timezone('America/Mexico_City')
                                ->format('d/m/Y H:i:s') ?? '—' }}
                        </td>
                        <td>{{ $movimiento->producto?->nombre ?? '—' }}</td>
                        <td>{{ $movimiento->pieza?->codigo_pieza ?? '—' }}</td>
                        <td>
                            <x-ui.badge :variant="match ($movimiento->tipo_movimiento) {
                                'entrada' => 'success',
                                'salida' => 'danger',
                                'ajuste' => 'warning',
                                default => 'neutral'
                            }">
                                {{ ucfirst($movimiento->tipo_movimiento) }}
                            </x-ui.badge>
                        </td>
                        <td>
                            {{ $movimiento->cantidad }}
                            {{ match ($movimiento->producto?->tipo) {
                                'individual' => 'pzas.',
                                'por_peso' => 'g',
                                'por_cantidad' => 'uds.',
                                default => ''
                            } }}
                        </td>
                        <td>{{ $movimiento->estadoOrigen?->nombre ?? '—' }}</td>
                        <td>{{ $movimiento->estadoDestino?->nombre ?? '—' }}</td>
                        <td>{{ $movimiento->usuario?->username ?? '—' }}</td>
                        <td class="whitespace-pre-wrap break-words">
                            {{ $movimiento->observaciones ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$movimientos" />
    </x-ui.card>
</div>
@endsection
