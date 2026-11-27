@extends('layouts.app')

@section('title', 'Detalle de sesión')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header
            :title="'Sesión de caja #'.$sesion->id"
            description="Consulta de operaciones y resultado de cierre."
        />

        @include('caja._navegacion')
        @include('inventario._mensajes')

        <x-ui.card title="Información de la sesión">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt>Caja</dt>
                    <dd>{{ $sesion->caja->nombre }}</dd>
                </div>
                <div>
                    <dt>Empleado</dt>
                    <dd>{{ $sesion->usuario->name }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>{{ ucfirst($sesion->estado) }}</dd>
                </div>
                <div>
                    <dt>Fondo inicial</dt>
                    <dd>${{ $sesion->fondo_inicial }} MXN</dd>
                </div>

                @if ($sesion->estado === 'cerrada')
                    <div>
                        <dt>Saldo esperado al cierre</dt>
                        <dd>${{ $sesion->saldo_esperado }} MXN</dd>
                    </div>
                    <div>
                        <dt>Efectivo contado</dt>
                        <dd>${{ $sesion->efectivo_contado }} MXN</dd>
                    </div>
                    <div>
                        <dt>Diferencia</dt>
                        <dd>${{ $sesion->diferencia }} MXN</dd>
                    </div>
                    <div>
                        <dt>Resultado</dt>
                        <dd>
                            @if (bccomp($sesion->diferencia, '0', 4) < 0)
                                Faltante
                            @elseif (bccomp($sesion->diferencia, '0', 4) > 0)
                                Sobrante
                            @else
                                Sin diferencia
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Cerrada por</dt>
                        <dd>{{ $sesion->cerradaPor?->name }}</dd>
                    </div>
                    <div>
                        <dt>Fecha de cierre</dt>
                        <dd>
                            {{ $sesion->fecha_cierre?->copy()->timezone(config('dashboard.zona_horaria', 'America/Mexico_City'))->format('d/m/Y H:i:s') }}
                        </dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        @include('caja.sesiones._movimientos')
    </div>
@endsection