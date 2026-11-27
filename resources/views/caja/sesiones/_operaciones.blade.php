<div class="grid gap-6 lg:grid-cols-2">
    @foreach ([
        'gasto' => 'Registrar gasto',
        'retiro' => 'Registrar retiro',
    ] as $tipo => $titulo)
        @can('joysys.permiso', 'caja.registrar_'.$tipo)
            <x-ui.card :title="$titulo">
                <form method="POST"
                      action="{{ route('caja.movimientos.'.$tipo) }}"
                      class="space-y-4">
                    @csrf

                    <input type="hidden"
                           name="sesion_id"
                           value="{{ $sesion->id }}">

                    <label class="block">
                        <span>Importe MXN</span>
                        <input name="monto"
                               type="number"
                               min="0.0001"
                               max="99999999.9999"
                               step="0.0001"
                               required
                               class="block w-full rounded border p-2 bg-transparent">
                    </label>

                    <label class="block">
                        <span>Concepto</span>
                        <input name="concepto"
                               maxlength="150"
                               required
                               class="block w-full rounded border p-2 bg-transparent">
                    </label>

                    <label class="block">
                        <span>Observaciones</span>
                        <textarea name="observaciones"
                                  maxlength="5000"
                                  class="block w-full rounded border p-2 bg-transparent"></textarea>
                    </label>

                    <x-ui.button type="submit">
                        {{ $titulo }}
                    </x-ui.button>
                </form>
            </x-ui.card>
        @endcan
    @endforeach
</div>

@can('joysys.permiso', 'caja.cerrar')
    <x-ui.card title="Cerrar sesión de caja">
        <p class="mb-4 text-sm opacity-70">
            Cuenta el efectivo físico disponible.
            El cierre es definitivo.
        </p>

        <form method="POST"
              action="{{ route('caja.sesion.cerrar', $sesion->id) }}"
              class="space-y-4">
            @csrf

            <label class="block">
                <span>Efectivo contado MXN</span>
                <input name="efectivo_contado"
                       type="number"
                       min="0"
                       max="99999999.9999"
                       step="0.0001"
                       required
                       class="block w-full rounded border p-2 bg-transparent">
            </label>

            <label class="flex items-center gap-2">
                <input type="checkbox" required>
                Confirmo que he contado el efectivo.
            </label>

            <x-ui.button type="submit">
                Cerrar caja
            </x-ui.button>
        </form>
    </x-ui.card>
@endcan