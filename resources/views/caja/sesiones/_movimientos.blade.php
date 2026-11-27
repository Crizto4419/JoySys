<x-ui.card title="Movimientos de caja">
    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <label>
            <span>Tipo de movimiento</span>
            <select name="tipo_movimiento"
                    class="rounded border p-2 bg-transparent">
                <option value="">Todos</option>

                @foreach (\App\Services\Caja\MovimientoCajaService::TIPOS as $tipo)
                    <option value="{{ $tipo }}"
                        @selected(request('tipo_movimiento') === $tipo)>
                        {{ ucfirst($tipo) }}
                    </option>
                @endforeach
            </select>
        </label>

        <x-ui.button type="submit">Filtrar</x-ui.button>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="p-3">ID</th>
                    <th class="p-3">Fecha</th>
                    <th class="p-3">Tipo</th>
                    <th class="p-3">Importe MXN</th>
                    <th class="p-3">Concepto</th>
                    <th class="p-3">Autorización</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($movimientos as $movimiento)
                    <tr class="border-b">
                        <td class="p-3">#{{ $movimiento->id }}</td>
                        <td class="p-3">
                            {{ $movimiento->created_at?->copy()->timezone(config('dashboard.zona_horaria', 'America/Mexico_City'))->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="p-3">
                            {{ ucfirst($movimiento->tipo_movimiento) }}
                        </td>
                        <td class="p-3">
                            ${{ $movimiento->monto }}
                        </td>
                        <td class="p-3">
                            {{ $movimiento->concepto }}

                            @if ($movimiento->observaciones)
                                <p class="text-xs opacity-70">
                                    {{ $movimiento->observaciones }}
                                </p>
                            @endif

                            @if ($movimiento->movimiento_original_id)
                                <p class="text-xs">
                                    Original #{{ $movimiento->movimiento_original_id }}
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            {{ $movimiento->autorizadoPor?->name ?? '—' }}
                        </td>
                    </tr>

                    @if (
                        $sesion->estado === 'abierta'
                        && in_array($movimiento->tipo_movimiento, ['gasto', 'retiro'], true)
                        && $movimiento->detalle_pago_id === null
                    )
                        @can('joysys.permiso', 'caja.autorizar_compensacion')
                            <tr class="border-b">
                                <td colspan="6" class="p-3">
                                    <details>
                                        <summary class="cursor-pointer">
                                            Compensar movimiento #{{ $movimiento->id }}
                                        </summary>

                                        <form method="POST"
                                              action="{{ route('caja.movimientos.compensacion', $movimiento->id) }}"
                                              class="mt-3 space-y-3">
                                            @csrf

                                            <label class="block">
                                                Importe MXN
                                                <input name="monto"
                                                       type="number"
                                                       min="0.0001"
                                                       max="{{ $movimiento->monto }}"
                                                       step="0.0001"
                                                       required
                                                       class="block w-full rounded border p-2 bg-transparent">
                                            </label>

                                            <label class="block">
                                                Concepto
                                                <input name="concepto"
                                                       maxlength="150"
                                                       required
                                                       class="block w-full rounded border p-2 bg-transparent">
                                            </label>

                                            <label class="block">
                                                Justificación
                                                <textarea name="observaciones"
                                                          maxlength="5000"
                                                          required
                                                          class="block w-full rounded border p-2 bg-transparent"></textarea>
                                            </label>

                                            <x-ui.button type="submit">
                                                Registrar compensación
                                            </x-ui.button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endcan
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="p-5 text-center">
                            No hay movimientos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>
</x-ui.card>