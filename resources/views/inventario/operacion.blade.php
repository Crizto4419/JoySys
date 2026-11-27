@extends('layouts.app')

@php
    $titulo = match ($operacion) {
        'entradas' => 'Registrar entrada',
        'salidas' => 'Registrar salida',
        'ajustes' => 'Ajustar inventario',
    };

    $estados = [
        'disponible' => 'Disponible',
        'reparacion' => 'Reparación',
        'danado' => 'Dañado',
        'perdido' => 'Perdido',
    ];

    $opcionesProductos = $productos->mapWithKeys(
        fn ($producto) => [
            $producto->id => $producto->codigo . ' — ' . $producto->nombre
        ]
    )->all();

    $opcionesPiezas = $piezas->mapWithKeys(
        fn ($pieza) => [
            $pieza->id => $pieza->codigo_pieza
                . ' — ' . ($pieza->estadoInventario?->nombre ?? '')
        ]
    )->all();

    $datosProductos = $productos->mapWithKeys(
        fn ($producto) => [
            $producto->id => ['tipo' => $producto->tipo]
        ]
    );

    $datosPiezas = $piezas->mapWithKeys(
        fn ($pieza) => [
            $pieza->id => [
                'producto_id' => (string) $pieza->producto_id,
                'estado' => $pieza->estadoInventario?->codigo,
            ]
        ]
    );
@endphp

@section('title', $titulo)

@section('content')
<div class="js-stack">
    <x-ui.page-header
        :title="$titulo"
        description="Registra una operación autorizada en tu sucursal."
    />

    @include('inventario._mensajes')
    @include('inventario._navegacion')

    @if ($operacion === 'salidas')
        <x-ui.alert variant="warning">
            Selecciona una pérdida o una salida administrativa.
            Ninguna de estas operaciones representa una venta.
            Las salidas administrativas solo admiten productos por cantidad o peso.
        </x-ui.alert>
    @elseif ($operacion === 'ajustes')
        <x-ui.alert variant="info">
            Los ajustes requieren justificación y generan un movimiento permanente.
        </x-ui.alert>
    @endif

    <x-ui.card :title="$titulo">
        @if ($productos->isEmpty())
            <x-ui.empty-state
                title="Sin productos activos"
                description="Necesitas un producto activo antes de registrar operaciones."
                icon="package"
            />
        @else
            <form
                id="form-inventario"
                method="POST"
                action="{{ route('inventario.' . $operacion . '.store') }}"
                class="js-stack"
            >
                @csrf

                <x-forms.select
                    name="producto_id"
                    label="Producto"
                    :options="$opcionesProductos"
                    placeholder="Selecciona un producto"
                    required
                />

                <p id="tipo-producto" class="js-text-small js-text-muted"></p>

                @if ($operacion === 'salidas')
                    <fieldset data-campo="tipo_salida" hidden disabled>
                        <x-forms.select
                            name="tipo_salida"
                            label="Tipo de salida"
                            :options="[
                                'perdida' => 'Registrar pérdida',
                                'administrativa' => 'Salida administrativa',
                            ]"
                            placeholder="Selecciona el tipo de salida"
                        />
                    </fieldset>
                @endif

                @if ($operacion === 'ajustes')
                    <fieldset data-campo="operacion" hidden disabled>
                        <x-forms.select
                            name="operacion"
                            label="Tipo de ajuste"
                            :options="[
                                'cantidad' => 'Ajustar cantidad disponible',
                                'estado' => 'Cambiar estado'
                            ]"
                            placeholder="Selecciona una operación"
                        />
                    </fieldset>
                @endif

                @if ($operacion === 'entradas')
                    <fieldset data-campo="codigo_pieza" hidden disabled>
                        <x-forms.input
                            name="codigo_pieza"
                            label="Código de pieza"
                            maxlength="80"
                            help="Identificador único de la pieza."
                        />
                    </fieldset>

                    <fieldset data-campo="peso_gramos" hidden disabled>
                        <x-forms.input
                            name="peso_gramos"
                            label="Peso en gramos (opcional)"
                            type="number"
                            step="0.001"
                            min="0.001"
                            max="99999.999"
                        />
                    </fieldset>
                @else
                    <fieldset data-campo="pieza_id" hidden disabled>
                        <x-forms.select
                            name="pieza_id"
                            label="Pieza individual"
                            :options="$opcionesPiezas"
                            placeholder="Selecciona una pieza"
                        />
                    </fieldset>

                    <p id="sin-piezas" class="js-text-small js-text-muted" hidden>
                        No hay piezas disponibles para esta operación.
                    </p>
                @endif

                <fieldset data-campo="cantidad" hidden disabled>
                    <x-forms.input
                        name="cantidad"
                        label="Cantidad"
                        type="number"
                        min="0.0001"
                        max="99999999.9999"
                        step="0.0001"
                    />

                    <p id="unidad-cantidad" class="js-text-small js-text-muted"></p>
                </fieldset>

                @if ($operacion === 'ajustes')
                    <fieldset data-campo="sentido" hidden disabled>
                        <x-forms.select
                            name="sentido"
                            label="Sentido del ajuste"
                            :options="[
                                'positivo' => 'Incrementar existencias',
                                'negativo' => 'Disminuir existencias'
                            ]"
                            placeholder="Selecciona el sentido"
                        />
                    </fieldset>
                @endif

                @if ($operacion !== 'entradas')
                    <fieldset data-campo="estado_origen" hidden disabled>
                        <x-forms.select
                            name="estado_origen"
                            label="Estado de origen"
                            :options="$estados"
                            placeholder="Selecciona el estado"
                        />
                    </fieldset>
                @endif

                @if ($operacion === 'ajustes')
                    <fieldset data-campo="estado_destino" hidden disabled>
                        <x-forms.select
                            name="estado_destino"
                            label="Estado de destino"
                            :options="$estados"
                            placeholder="Selecciona el destino"
                        />
                    </fieldset>
                @endif

                <x-forms.textarea
                    name="observaciones"
                    :label="$operacion === 'entradas'
                        ? 'Observaciones (opcional)'
                        : 'Justificación de la operación'"
                    :required="$operacion !== 'entradas'"
                    maxlength="16000"
                    rows="4"
                />

                <div class="js-actions">
                    <x-ui.button
                        type="submit"
                        id="guardar-operacion"
                        disabled
                    >
                        Confirmar operación
                    </x-ui.button>

                    <x-ui.button
                        variant="secondary"
                        :href="route('inventario.index')"
                    >
                        Cancelar
                    </x-ui.button>
                </div>

                <noscript>
                    <x-ui.alert variant="warning">
                        Activa JavaScript para utilizar este formulario.
                    </x-ui.alert>
                </noscript>
            </form>
        @endif
    </x-ui.card>
</div>

<script>
(function () {
    const form = document.getElementById('form-inventario');

    if (!form) {
        return;
    }

    const modo = {{ Illuminate\Support\Js::from($operacion) }};
    const productos = {{ Illuminate\Support\Js::from($datosProductos) }};
    const piezas = {{ Illuminate\Support\Js::from($datosPiezas) }};
    const transiciones = {{ Illuminate\Support\Js::from($transiciones) }};

    const campo = nombre => form.elements.namedItem(nombre);

    const producto = campo('producto_id');
    const pieza = campo('pieza_id');
    const operacion = campo('operacion');
    const tipoSalida = campo('tipo_salida');
    const cantidad = campo('cantidad');
    const origen = campo('estado_origen');
    const destino = campo('estado_destino');

    const guardar = document.getElementById('guardar-operacion');

    function mostrar(nombre, visible, obligatorio = false) {
        const grupo = form.querySelector(
            '[data-campo="' + nombre + '"]'
        );

        if (!grupo) {
            return;
        }

        grupo.hidden = !visible;
        grupo.disabled = !visible;

        const control = campo(nombre);

        if (control) {
            control.required = visible && obligatorio;
        }
    }

    function filtrar(select, permite) {
        if (!select) {
            return;
        }

        for (const opcion of select.options) {
            const valida = opcion.value === '' || permite(opcion.value);

            opcion.hidden = !valida;
            opcion.disabled = !valida;
        }

        const seleccion = select.selectedOptions[0];

        if (seleccion && seleccion.disabled) {
            select.value = '';
        }
    }

    function actualizar() {
        const actual = productos[producto.value];
        const tipo = actual?.tipo ?? '';
        const individual = tipo === 'individual';
        const seleccionado = tipo !== '';

        if (operacion) {
            filtrar(
                operacion,
                valor => !individual || valor === 'estado'
            );

            if (individual) {
                operacion.value = 'estado';
            }
        }

        if (tipoSalida) {
            filtrar(
                tipoSalida,
                valor => !individual || valor === 'perdida'
            );
        }

        const salidaPerdida =
            modo === 'salidas' &&
            tipoSalida?.value === 'perdida';

        const salidaAdministrativa =
            modo === 'salidas' &&
            tipoSalida?.value === 'administrativa';

        mostrar('tipo_salida', seleccionado, true);

        const ajusteCantidad =
            modo === 'ajustes' &&
            operacion?.value === 'cantidad';

        const ajusteEstado =
            modo === 'ajustes' &&
            operacion?.value === 'estado';

        const necesitaPieza =
            seleccionado &&
            individual &&
            (salidaPerdida || ajusteEstado);

        const necesitaCantidad =
            seleccionado &&
            !individual &&
            (
                modo === 'entradas' ||
                salidaPerdida ||
                salidaAdministrativa ||
                ajusteCantidad ||
                ajusteEstado
            );

        mostrar('operacion', seleccionado, true);

        mostrar(
            'codigo_pieza',
            seleccionado && individual && modo === 'entradas',
            true
        );

        mostrar(
            'peso_gramos',
            seleccionado && individual && modo === 'entradas'
        );

        mostrar('pieza_id', necesitaPieza, true);
        mostrar('cantidad', necesitaCantidad, true);
        mostrar('sentido', seleccionado && ajusteCantidad && !individual, true);

        mostrar(
            'estado_origen',
            seleccionado &&
                !individual &&
                (salidaPerdida || ajusteEstado),
            true
        );

        mostrar(
            'estado_destino',
            seleccionado && ajusteEstado,
            true
        );

        filtrar(pieza, valor =>
            piezas[valor]?.producto_id === producto.value
        );

        filtrar(origen, valor =>
            modo === 'salidas'
                ? ['disponible', 'reparacion', 'danado'].includes(valor)
                : Object.hasOwn(transiciones, valor)
        );

        const estadoOrigen = individual
            ? piezas[pieza?.value]?.estado
            : origen?.value;

        filtrar(destino, valor =>
            valor !== 'perdido' &&
            (transiciones[estadoOrigen] ?? []).includes(valor)
        );

        if (cantidad) {
            cantidad.step = tipo === 'por_peso' ? '0.0001' : '1';
            cantidad.min = tipo === 'por_peso' ? '0.0001' : '1';

            cantidad.max = tipo === 'por_peso'
                ? '99999999.9999'
                : '99999999';
        }

        document.getElementById('tipo-producto').textContent =
            ({
                individual: 'Control individual por pieza.',
                por_cantidad: 'Control por unidades enteras.',
                por_peso: 'Control por gramos con cuatro decimales.'
            })[tipo] ?? '';

        document.getElementById('unidad-cantidad').textContent =
            tipo === 'por_peso'
                ? 'Cantidad expresada en gramos.'
                : 'Cantidad expresada en unidades enteras.';

        const hayPiezas = pieza
            ? Array.from(pieza.options).some(
                opcion => opcion.value !== '' && !opcion.disabled
            )
            : false;

        const sinPiezas = document.getElementById('sin-piezas');

        if (sinPiezas) {
            sinPiezas.hidden = !(necesitaPieza && !hayPiezas);
        }

        guardar.disabled =
            !seleccionado ||
            (modo === 'ajustes' && !operacion?.value) ||
            (modo === 'salidas' && !tipoSalida?.value) ||
            (individual && salidaAdministrativa) ||
            (necesitaPieza && !hayPiezas);
    }

    form.addEventListener('change', actualizar);

    form.addEventListener('submit', function (evento) {
        actualizar();

        if (guardar.disabled || !form.checkValidity()) {
            evento.preventDefault();
            form.reportValidity();
            return;
        }

        const mensaje = modo === 'salidas'
            ? (
                tipoSalida?.value === 'perdida'
                    ? '¿Confirmas el registro de mercancía perdida?'
                    : '¿Confirmas la salida administrativa de existencias disponibles?'
            )
            : '¿Confirmas esta operación de inventario?';

        if (!window.confirm(mensaje)) {
            evento.preventDefault();
            return;
        }

        guardar.disabled = true;
    });

    actualizar();
})();
</script>
@endsection
