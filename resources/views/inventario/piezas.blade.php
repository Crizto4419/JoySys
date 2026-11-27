@extends('layouts.app')

@section('title', 'Piezas individuales')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Piezas individuales"
        description="Consulta las piezas registradas en tu sucursal."
    />

    @include('inventario._mensajes')
    @include('inventario._navegacion')

        @include('inventario._filtros', ['pantalla' => 'piezas'])

    <x-ui.card title="Listado de piezas">
        @if ($piezas->isEmpty())
            <x-ui.empty-state
                title="Sin piezas registradas"
                description="Las piezas aparecerán al registrar entradas individuales."
                icon="gem"
            />
        @else
            <x-ui.table :headers="[
                'Código de pieza',
                'Producto',
                'Metal',
                'Pureza',
                'Peso',
                'Estado',
                'Registro'
            ]">
                @foreach ($piezas as $pieza)
                    <tr>
                        <td>{{ $pieza->codigo_pieza }}</td>
                        <td>{{ $pieza->producto?->nombre ?? '—' }}</td>
                        <td>{{ $pieza->metal?->nombre ?? '—' }}</td>
                        <td>{{ $pieza->pureza?->codigo ?? '—' }}</td>
                        <td>
                            {{ $pieza->peso_gramos !== null
                                ? $pieza->peso_gramos . ' g'
                                : '—' }}
                        </td>
                        <td>
                            <x-ui.badge :variant="match ($pieza->estadoInventario?->codigo) {
                                'disponible' => 'success',
                                'perdido', 'danado' => 'danger',
                                'reparacion', 'apartado' => 'warning',
                                default => 'neutral'
                            }">
                                {{ $pieza->estadoInventario?->nombre ?? '—' }}
                            </x-ui.badge>
                        </td>
                        <td>
                            {{ $pieza->created_at?->copy()
                                ->timezone('America/Mexico_City')
                                ->format('d/m/Y H:i') ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <x-ui.pagination :paginator="$piezas" />
    </x-ui.card>
</div>
@endsection
