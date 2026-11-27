@extends('layouts.app')

@section('title', 'Abrir caja')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header
            title="Abrir caja"
            description="Inicia tu sesión de caja con el fondo inicial disponible."
        />

        @include('caja._navegacion')
        @include('inventario._mensajes')

        @if ($cajas->isEmpty())
            <x-ui.alert variant="warning">
                No hay cajas disponibles en tu sucursal operativa.
            </x-ui.alert>
        @else
            <x-ui.card title="Datos de apertura">
                <form
                    method="POST"
                    action="{{ route('caja.sesion.store') }}"
                    onsubmit="return confirm('¿Confirmas la apertura con el fondo indicado?');"
                >
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-forms.select
                            name="caja_id"
                            label="Caja disponible"
                            placeholder="Selecciona una caja"
                            :options="$cajas->mapWithKeys(fn ($caja) => [
                                $caja->id => $caja->codigo . ' - ' . $caja->nombre
                            ])->all()"
                            required
                        />

                        <x-forms.input
                            name="fondo_inicial"
                            label="Fondo inicial (MXN)"
                            type="text"
                            inputmode="decimal"
                            pattern="[0-9]{1,8}([.][0-9]{1,4})?"
                            maxlength="13"
                            value="0.00"
                            help="Importe no negativo, con hasta cuatro decimales."
                            required
                        />
                    </div>

                    <div class="mt-5">
                        <x-ui.alert variant="info">
                            La sesión quedará vinculada a tu usuario.
                            El fondo inicial no se registrará como un ingreso adicional.
                            Cerrar sesión en JoySys no cerrará la caja.
                        </x-ui.alert>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button type="submit">
                            Confirmar apertura
                        </x-ui.button>

                        <x-ui.button
                            variant="secondary"
                            :href="route('caja.index')"
                        >
                            Cancelar
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
@endsection
