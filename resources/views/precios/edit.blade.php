@extends('layouts.app')

@section('title', 'Modificar precio')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Modificar precio"
        :description="$producto->codigo . ' - ' . $producto->nombre"
    />

    @if($errors->any())
        <x-ui.alert variant="danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    @if($producto->precioVigente)
        <x-ui.card>
            <form
                method="POST"
                action="{{ route('precios.update', $producto) }}"
                class="js-stack"
            >
                @csrf
                @method('PUT')

                <x-ui.alert variant="info">
                    El cambio de precio generara un registro historico.
                    El precio inicial del producto permanecera intacto.
                </x-ui.alert>

                @if($producto->estado === 'inactivo')
                    <x-ui.alert variant="warning">
                        El producto esta inactivo. Cambiar su precio
                        no lo reactivara.
                    </x-ui.alert>
                @endif

                <x-forms.input
                    name="precio_actual_visible"
                    label="Precio vigente (MXN)"
                    :value="$producto->precioVigente->precio"
                    readonly
                />

                <x-forms.input
                    name="precio"
                    label="Nuevo precio (MXN)"
                    type="number"
                    :value="$producto->precioVigente->precio"
                    min="0.0001"
                    max="99999999.9999"
                    step="0.0001"
                    required
                />

                <div class="js-actions">
                    <x-ui.button type="submit">
                        Guardar precio
                    </x-ui.button>

                    <x-ui.button
                        variant="secondary"
                        :href="route('precios.index')"
                    >
                        Cancelar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @else
        <x-ui.alert variant="warning">
            Este producto no tiene un precio inicial registrado.
        </x-ui.alert>

        <x-ui.button
            variant="secondary"
            :href="route('precios.index')"
        >
            Volver
        </x-ui.button>
    @endif
</div>
@endsection
