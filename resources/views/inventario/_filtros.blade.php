@php
    $opciones = \App\Services\Inventario\FiltrosInventario::opciones(
        $pantalla
    );

    $etiquetas = [
        'categoria_id' => 'Categoria',
        'tipo' => 'Tipo de producto',
        'nivel_stock' => 'Nivel de stock',
        'estado' => 'Estado',
        'tipo_movimiento' => 'Tipo de movimiento',
        'tipo_incidencia' => 'Tipo de incidencia',
    ];
@endphp

<x-ui.card title="Filtros">
    <x-ui.filters
        :action="url()->current()"
        search-placeholder="Nombre o codigo..."
    >
        @foreach ($opciones as $campo => $valores)
            <div class="js-field">
                <label
                    class="js-label"
                    for="filter-{{ $campo }}"
                >
                    {{ $etiquetas[$campo] ?? $campo }}
                </label>

                <select
                    class="js-input"
                    id="filter-{{ $campo }}"
                    name="{{ $campo }}"
                >
                    <option value="">Todos</option>

                    @foreach ($valores as $valor => $etiqueta)
                        <option
                            value="{{ $valor }}"
                            @selected(
                                (string) request()->query($campo, '')
                                === (string) $valor
                            )
                        >
                            {{ $etiqueta }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endforeach

        @if (in_array(
            $pantalla,
            ['movimientos', 'incidencias'],
            true
        ))
            @foreach ([
                'fecha_desde' => 'Desde',
                'fecha_hasta' => 'Hasta',
            ] as $campo => $etiqueta)
                <div class="js-field">
                    <label
                        class="js-label"
                        for="filter-{{ $campo }}"
                    >
                        {{ $etiqueta }}
                    </label>

                    <input
                        class="js-input"
                        type="date"
                        id="filter-{{ $campo }}"
                        name="{{ $campo }}"
                        value="{{ request()->query($campo, '') }}"
                    >
                </div>
            @endforeach
        @endif
    </x-ui.filters>
</x-ui.card>
