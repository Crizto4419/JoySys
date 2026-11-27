@extends('layouts.app')

@section('title', 'Reportar incidencia')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Reportar incidencia"
        description="Registra una irregularidad detectada en el inventario."
    />

    @include('inventario._mensajes')

    <x-ui.alert variant="info">
        La incidencia quedará asociada automáticamente a tu usuario y sucursal.
    </x-ui.alert>

    <x-ui.card title="Datos de la incidencia">
        @if ($productos->isEmpty())
            <x-ui.empty-state
                title="Sin productos registrados"
                description="Necesitas un producto registrado para reportar una incidencia."
                icon="package"
            >
                <x-slot:actions>
                    <x-ui.button
                        variant="secondary"
                        :href="route('incidencias.index')"
                    >
                        Volver
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <form
                method="POST"
                action="{{ route('incidencias.store') }}"
                class="js-stack"
            >
                @csrf

                <x-forms.select
                    name="producto_id"
                    label="Producto"
                    :options="$productos->mapWithKeys(fn ($producto) => [
                        $producto->id => $producto->codigo . ' — ' . $producto->nombre
                    ])->all()"
                    placeholder="Selecciona un producto"
                    required
                />

                <x-forms.select
                    name="tipo_incidencia"
                    label="Tipo de incidencia"
                    :options="$tipos"
                    placeholder="Selecciona el tipo"
                    required
                />

                <x-forms.textarea
                    name="descripcion"
                    label="Descripción"
                    help="Describe lo ocurrido y los datos necesarios para su revisión."
                    rows="5"
                    maxlength="16000"
                    required
                />

                <div class="js-actions">
                    <x-ui.button type="submit">
                        Reportar incidencia
                    </x-ui.button>

                    <x-ui.button
                        variant="secondary"
                        :href="route('incidencias.index')"
                    >
                        Cancelar
                    </x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>
</div>
@endsection
