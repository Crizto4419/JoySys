@extends('layouts.app')

@section('title', 'Historial de precios')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Historial de precios"
        :description="$producto->codigo . ' - ' . $producto->nombre"
    >
        <x-slot:actions>
            <x-ui.button
                variant="secondary"
                :href="route('precios.index')"
            >
                Volver
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert variant="info">
        El historial es de solo consulta.
        Los registros anteriores no pueden editarse ni eliminarse.
    </x-ui.alert>

    @if($errors->any())
        <x-ui.alert variant="danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card title="Cambios de precio">
        @if($historial->count())
            <x-ui.table :headers="[
                'Fecha de vigencia',
                'Precio anterior',
                'Precio nuevo',
                'Moneda',
                'Usuario',
                'Movimiento',
            ]">
                @foreach($historial as $registro)
                    <tr>
                        <td>
                            {{ $registro->vigente_desde->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i:s') }}
                        </td>
                        <td>
                            @if($registro->precio_anterior !== null)
                                ${{ $registro->precio_anterior }}
                            @else
                                -
                            @endif
                        </td>
                        <td>${{ $registro->precio_nuevo }}</td>
                        <td>{{ $registro->moneda }}</td>
                        <td>
                            {{ $registro->usuario?->username ?? 'No disponible' }}
                        </td>
                        <td>
                            <x-ui.badge
                                :variant="$registro->precio_anterior === null ? 'info' : 'neutral'"
                            >
                                {{ $registro->precio_anterior === null ? 'Registro inicial' : 'Actualizacion' }}
                            </x-ui.badge>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty-state
                title="Sin historial"
                description="No existen cambios de precio registrados."
                icon="clock"
            />
        @endif

        <x-ui.pagination :paginator="$historial"/>
    </x-ui.card>
</div>
@endsection
