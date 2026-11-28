<?php

declare(strict_types=1);

namespace App\Services\Finanzas;

use App\Models\AplicacionSaldoFavor;
use App\Models\Cliente;
use App\Models\DetallePago;
use App\Models\MovimientoSaldoFavor;
use App\Models\Pago;
use App\Models\SaldoFavor;
use App\Models\User;
use App\Models\Venta;
use App\Services\Caja\CajaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SaldoFavorService
{
    public function __construct(
        private readonly CajaService $cajas
    ) {
    }

    private function error(
        string $campo,
        string $mensaje
    ): never {
        throw ValidationException::withMessages([
            $campo => $mensaje,
        ]);
    }

    private function verificarTransaccionExterior(): void
    {
        if (
            DB::transactionLevel() > 0
            && !$this->cajas->tieneBloqueoTransaccional()
            && !app()->environment('testing')
        ) {
            throw new LogicException(
                'El servicio debe controlar la transaccion exterior.'
            );
        }
    }

    private function obtenerSaldo(
        int $saldoId,
        int $sucursalId
    ): SaldoFavor {
        $saldo = SaldoFavor::query()
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->findOrFail($saldoId);

        ValidadorPago::validarMoneda($saldo->moneda);

        ValidadorSaldoFavor::validarEstado(
            $saldo->estado,
            $saldo->saldo_disponible
        );

        return $saldo;
    }

    private function obtenerPartida(
        int $detalleId,
        int $sucursalId,
        User $usuario,
        bool $reembolso
    ): array {
        $detalle = DetallePago::query()
            ->lockForUpdate()
            ->findOrFail($detalleId);

        $pago = Pago::query()
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->findOrFail($detalle->pago_id);

        if (
            $pago->estado !== 'confirmado'
            || $pago->fecha_pago === null
            || $pago->fecha_pago->isFuture()
            || $pago->referencia_tipo !== 'venta'
            || (int) $pago->recibido_por !== (int) $usuario->id
        ) {
            $this->error(
                'pago',
                'El pago no cumple las condiciones financieras.'
            );
        }

        ValidadorPago::validarMoneda($pago->moneda);

        if ($detalle->metodo_pago !== 'saldo_favor') {
            $this->error(
                'detalle_pago_id',
                'La partida no corresponde a saldo a favor.'
            );
        }

        if (
            ($pago->pago_original_id !== null) !== $reembolso
            || ($detalle->detalle_pago_original_id !== null)
                !== $reembolso
        ) {
            $this->error(
                'detalle_pago_id',
                'La naturaleza de la partida es incompatible.'
            );
        }

        if ($detalle->movimientoCaja()->exists()) {
            $this->error(
                'detalle_pago_id',
                'Un saldo a favor no debe afectar efectivo.'
            );
        }

        $venta = Venta::query()
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->findOrFail($pago->referencia_id);

        if ($venta->cliente_id === null) {
            $this->error(
                'cliente_id',
                'La venta requiere un cliente identificado.'
            );
        }

        if (!$reembolso) {
            if (
                $venta->estado === 'cancelada'
                || (int) $venta->usuario_id !== (int) $usuario->id
            ) {
                $this->error(
                    'venta',
                    'La venta no admite esta aplicacion.'
                );
            }

            Cliente::query()
                ->where('activo', true)
                ->lockForUpdate()
                ->findOrFail($venta->cliente_id);
        }

        return [$detalle, $pago, $venta];
    }

    private function verificarHistorial(
        SaldoFavor $saldo
    ): void {
        $movimientos = $saldo->movimientos()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $acumulado = '0.0000';
        $estado = 'activo';
        $generaciones = 0;

        foreach ($movimientos as $movimiento) {
            if (
                (int) $movimiento->sucursal_id
                !== (int) $saldo->sucursal_id
            ) {
                throw new LogicException(
                    'Movimiento perteneciente a otra sucursal.'
                );
            }

            if (!ImporteFinanciero::iguales(
                $movimiento->saldo_anterior,
                $acumulado
            )) {
                throw new LogicException(
                    'Discontinuidad en el historial del credito.'
                );
            }

            if ($movimiento->tipo_movimiento === 'generacion') {
                $generaciones++;

                if ($generaciones !== 1) {
                    throw new LogicException(
                        'El credito tiene generaciones duplicadas.'
                    );
                }
            } elseif ($generaciones !== 1) {
                throw new LogicException(
                    'El credito no tiene una generacion inicial.'
                );
            }

            $calculo = ValidadorSaldoFavor::calcular(
                $movimiento->tipo_movimiento,
                $estado,
                $acumulado,
                $movimiento->monto
            );

            if (!ImporteFinanciero::iguales(
                $calculo['saldo_nuevo'],
                $movimiento->saldo_nuevo
            )) {
                throw new LogicException(
                    'Importe inconsistente en el historial.'
                );
            }

            $acumulado = $calculo['saldo_nuevo'];
            $estado = $calculo['estado_nuevo'];
        }

        if (
            $generaciones !== 1
            || !ImporteFinanciero::iguales(
                $acumulado,
                $saldo->saldo_disponible
            )
            || $estado !== $saldo->estado
        ) {
            throw new LogicException(
                'El saldo disponible no coincide con su historial.'
            );
        }
    }

    private function registrarMovimiento(
        SaldoFavor $saldo,
        User $usuario,
        string $tipo,
        mixed $monto,
        AplicacionSaldoFavor $aplicacion,
        Pago $pago,
        ?MovimientoSaldoFavor $original = null
    ): MovimientoSaldoFavor {
        $calculo = ValidadorSaldoFavor::calcular(
            $tipo,
            $saldo->estado,
            $saldo->saldo_disponible,
            $monto
        );

        $movimiento = MovimientoSaldoFavor::create([
            'saldo_favor_id' => $saldo->id,
            'sucursal_id' => $saldo->sucursal_id,
            'usuario_id' => $usuario->id,
            'autorizado_por' => $tipo === 'restitucion'
                ? $usuario->id
                : null,
            'tipo_movimiento' => $tipo,
            'monto' => $calculo['monto'],
            'saldo_anterior' => $calculo['saldo_anterior'],
            'saldo_nuevo' => $calculo['saldo_nuevo'],
            'aplicacion_saldo_favor_id' => $aplicacion->id,
            'pago_id' => $pago->id,
            'devolucion_id' => null,
            'movimiento_original_id' => $original?->id,
        ]);

        $saldo->forceFill([
            'saldo_disponible' => $calculo['saldo_nuevo'],
            'estado' => $calculo['estado_nuevo'],
        ])->save();

        return $movimiento;
    }

    public function consultarDisponibles(
        int $clienteId,
        User $usuario
    ): array {
        $sucursalId = $this->cajas->autorizar(
            $usuario,
            'pagos.registrar'
        );

        Cliente::query()
            ->where('activo', true)
            ->findOrFail($clienteId);

        $saldos = SaldoFavor::query()
            ->where('cliente_id', $clienteId)
            ->where('sucursal_id', $sucursalId)
            ->where('moneda', 'MXN')
            ->where('estado', 'activo')
            ->where('saldo_disponible', '>', 0)
            ->orderBy('id')
            ->get();

        return [
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'moneda' => 'MXN',
            'saldo_total' => ImporteFinanciero::sumar(
                $saldos->pluck('saldo_disponible'),
                'saldo_total'
            ),
            'saldos' => $saldos,
        ];
    }

    public function aplicar(
        int $saldoId,
        int $detalleId,
        User $usuario
    ): AplicacionSaldoFavor {
        $this->verificarTransaccionExterior();

        $permiso = 'pagos.registrar';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $saldoId,
                $detalleId,
                $usuario,
                $sucursalId
            ): AplicacionSaldoFavor {
                [$detalle, $pago, $venta] =
                    $this->obtenerPartida(
                        $detalleId,
                        $sucursalId,
                        $usuario,
                        false
                    );

                if (
                    $detalle->saldo_favor_id === null
                    || (int) $detalle->saldo_favor_id !== $saldoId
                ) {
                    $this->error(
                        'saldo_favor_id',
                        'El credito no coincide con la partida del pago.'
                    );
                }

                $saldo = $this->obtenerSaldo(
                    $saldoId,
                    $sucursalId
                );

                if (
                    (int) $saldo->cliente_id
                    !== (int) $venta->cliente_id
                ) {
                    $this->error(
                        'saldo_favor_id',
                        'El credito pertenece a otro cliente.'
                    );
                }

                $this->verificarHistorial($saldo);

                $existente = AplicacionSaldoFavor::query()
                    ->where('detalle_pago_id', $detalle->id)
                    ->lockForUpdate()
                    ->first();

                if ($existente !== null) {
                    if (
                        (int) $existente->saldo_favor_id !== $saldoId
                        || (int) $existente->sucursal_id !== $sucursalId
                        || (int) $existente->usuario_id !== (int) $usuario->id
                        || !ImporteFinanciero::iguales(
                            $existente->monto_aplicado,
                            $detalle->monto
                        )
                    ) {
                        $this->error(
                            'detalle_pago_id',
                            'La partida ya tiene otra aplicacion.'
                        );
                    }

                    $movimientos = $existente->movimientos()
                        ->where('tipo_movimiento', 'aplicacion')
                        ->lockForUpdate()
                        ->get();

                    if (
                        $movimientos->count() !== 1
                        || (int) $movimientos->first()->pago_id !== (int) $pago->id
                        || (int) $movimientos->first()->saldo_favor_id !== $saldoId
                        || !ImporteFinanciero::iguales(
                            $movimientos->first()->monto,
                            $detalle->monto
                        )
                    ) {
                        throw new LogicException(
                            'Aplicacion financiera inconsistente.'
                        );
                    }

                    return $existente;
                }

                $monto = ImporteFinanciero::normalizar(
                    $detalle->monto
                );

                ValidadorSaldoFavor::calcular(
                    'aplicacion',
                    $saldo->estado,
                    $saldo->saldo_disponible,
                    $monto
                );

                $aplicacion = AplicacionSaldoFavor::create([
                    'saldo_favor_id' => $saldo->id,
                    'detalle_pago_id' => $detalle->id,
                    'sucursal_id' => $sucursalId,
                    'usuario_id' => $usuario->id,
                    'revertida_por' => null,
                    'monto_aplicado' => $monto,
                    'estado' => 'aplicada',
                ]);

                $this->registrarMovimiento(
                    $saldo,
                    $usuario,
                    'aplicacion',
                    $monto,
                    $aplicacion,
                    $pago
                );

                return $aplicacion->refresh();
            },
            false,
            $permiso
        );
    }

    public function restituir(
        int $detalleReembolsoId,
        User $usuario
    ): MovimientoSaldoFavor {
        $this->verificarTransaccionExterior();

        $permiso = 'pagos.reembolsar';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $detalleReembolsoId,
                $usuario,
                $sucursalId
            ): MovimientoSaldoFavor {
                [$detalle, $pago, $venta] =
                    $this->obtenerPartida(
                        $detalleReembolsoId,
                        $sucursalId,
                        $usuario,
                        true
                    );

                $partidaOriginal = DetallePago::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $detalle->detalle_pago_original_id
                    );

                $pagoOriginal = Pago::query()
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->findOrFail($pago->pago_original_id);

                if (
                    (int) $partidaOriginal->pago_id !== (int) $pagoOriginal->id
                    || $partidaOriginal->metodo_pago !== 'saldo_favor'
                    || $partidaOriginal->saldo_favor_id === null
                    || $detalle->saldo_favor_id === null
                    || (int) $detalle->saldo_favor_id
                        !== (int) $partidaOriginal->saldo_favor_id
                    || $partidaOriginal->detalle_pago_original_id !== null
                    || $pagoOriginal->pago_original_id !== null
                    || $pagoOriginal->estado !== 'confirmado'
                    || $pagoOriginal->fecha_pago === null
                    || $pagoOriginal->fecha_pago->isFuture()
                    || $pagoOriginal->referencia_tipo !== 'venta'
                    || (int) $pagoOriginal->referencia_id !== (int) $venta->id
                    || $pagoOriginal->moneda !== 'MXN'
                ) {
                    $this->error(
                        'pago_original_id',
                        'El origen del reembolso es incompatible.'
                    );
                }

                $aplicacion = AplicacionSaldoFavor::query()
                    ->where('detalle_pago_id', $partidaOriginal->id)
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $saldo = $this->obtenerSaldo(
                    (int) $aplicacion->saldo_favor_id,
                    $sucursalId
                );

                if (
                    (int) $saldo->cliente_id !== (int) $venta->cliente_id
                    || (int) $saldo->id !== (int) $partidaOriginal->saldo_favor_id
                    || (int) $saldo->id !== (int) $detalle->saldo_favor_id
                    || (int) $aplicacion->usuario_id !== (int) $pagoOriginal->recibido_por
                    || !ImporteFinanciero::iguales(
                        $aplicacion->monto_aplicado,
                        $partidaOriginal->monto
                    )
                ) {
                    throw new LogicException(
                        'El credito original es inconsistente.'
                    );
                }

                $this->verificarHistorial($saldo);

                $originales = $aplicacion->movimientos()
                    ->where('tipo_movimiento', 'aplicacion')
                    ->lockForUpdate()
                    ->get();

                if ($originales->count() !== 1) {
                    throw new LogicException(
                        'No existe una aplicacion original unica.'
                    );
                }

                $original = $originales->first();

                if (
                    (int) $original->saldo_favor_id !== (int) $saldo->id
                    || (int) $original->pago_id !== (int) $pagoOriginal->id
                    || !ImporteFinanciero::iguales(
                        $original->monto,
                        $aplicacion->monto_aplicado
                    )
                ) {
                    throw new LogicException(
                        'Movimiento original inconsistente.'
                    );
                }

                $historial = $original->restituciones()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $monto = ImporteFinanciero::normalizar(
                    $detalle->monto
                );

                foreach ($historial as $movimiento) {
                    if (
                        (int) $movimiento->saldo_favor_id !== (int) $saldo->id
                        || (int) $movimiento->aplicacion_saldo_favor_id
                            !== (int) $aplicacion->id
                    ) {
                        throw new LogicException(
                            'Restitucion historica inconsistente.'
                        );
                    }
                }

                $partidasEquivalentes = $pago->detalles()
                    ->where(
                        'detalle_pago_original_id',
                        $partidaOriginal->id
                    )
                    ->lockForUpdate()
                    ->get();

                if ($partidasEquivalentes->count() !== 1) {
                    $this->error(
                        'detalle_pago_id',
                        'El reembolso contiene partidas duplicadas.'
                    );
                }

                $existentes = $historial->filter(
                    fn ($movimiento) =>
                        (int) $movimiento->pago_id === (int) $pago->id
                );

                if ($existentes->isNotEmpty()) {
                    if (
                        $existentes->count() !== 1
                        || !ImporteFinanciero::iguales(
                            $existentes->first()->monto,
                            $monto
                        )
                    ) {
                        throw new LogicException(
                            'Restitucion duplicada o incompatible.'
                        );
                    }

                    return $existentes->first();
                }



                if ($aplicacion->estado !== 'aplicada') {
                    $this->error(
                        'aplicacion',
                        'La aplicacion ya fue revertida.'
                    );
                }

                ValidadorReembolso::verificarLimite(
                    $monto,
                    $aplicacion->monto_aplicado,
                    $historial->pluck('monto')
                );

                $movimiento = $this->registrarMovimiento(
                    $saldo,
                    $usuario,
                    'restitucion',
                    $monto,
                    $aplicacion,
                    $pago,
                    $original
                );

                $totalRestituido = ImporteFinanciero::sumar([
                    ...$historial->pluck('monto')->all(),
                    $monto,
                ]);

                if (ImporteFinanciero::iguales(
                    $totalRestituido,
                    $aplicacion->monto_aplicado
                )) {
                    $aplicacion->forceFill([
                        'estado' => 'revertida',
                        'revertida_por' => $usuario->id,
                    ])->save();
                }

                return $movimiento->refresh();
            },
            false,
            $permiso
        );
    }

    /**
     * Cancela completamente un saldo a favor sin alterar su historial.
     *
     * La clave idempotente pertenece al usuario y sucursal operativos.
     */
    public function cancelar(
        int $saldoFavorId,
        string $justificacion,
        string $claveIdempotencia,
        \App\Models\User $usuario
    ): \App\Models\MovimientoSaldoFavor {
        $this->verificarTransaccionExterior();

        if ($saldoFavorId <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'saldo_favor_id' => 'El identificador del saldo es invalido.',
            ]);
        }

        $justificacion = ValidadorReembolso::justificar(
            $justificacion
        );

        $permiso = 'saldos_favor.cancelar';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        $operacion = app(IdempotenciaService::class)->ejecutar(
            $usuario,
            $permiso,
            'cancelacion_saldo_favor',
            $claveIdempotencia,
            [
                'saldo_favor_id' => $saldoFavorId,
                'justificacion' => $justificacion,
            ],
            'movimiento_saldo_favor',
            function (int $sucursalOperativaId) use (
                $saldoFavorId,
                $justificacion,
                $usuario
            ): int {
                $saldo = $this->obtenerSaldo(
                    $saldoFavorId,
                    $sucursalOperativaId
                );

                $this->verificarHistorial($saldo);

                if ($saldo->estado !== 'activo') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'saldo_favor_id' => 'Solo se pueden cancelar saldos activos.',
                    ]);
                }

                $importe = ImporteFinanciero::normalizar(
                    $saldo->saldo_disponible,
                    false,
                    'saldo_disponible'
                );

                $aplicaciones = \App\Models\AplicacionSaldoFavor::query()
                    ->where('saldo_favor_id', $saldo->getKey())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($aplicaciones as $aplicacion) {
                    if (
                        (int) $aplicacion->sucursal_id
                        !== $sucursalOperativaId
                    ) {
                        throw new \LogicException(
                            'Aplicacion de saldo con sucursal inconsistente.'
                        );
                    }

                    if ($aplicacion->estado !== 'revertida') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'saldo_favor_id' =>
                                'El saldo conserva aplicaciones pendientes de restitucion.',
                        ]);
                    }

                    $movimientosAplicacion =
                        \App\Models\MovimientoSaldoFavor::query()
                            ->where(
                                'aplicacion_saldo_favor_id',
                                $aplicacion->getKey()
                            )
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                    $originales = $movimientosAplicacion
                        ->where('tipo_movimiento', 'aplicacion');

                    if ($originales->count() !== 1) {
                        throw new \LogicException(
                            'Historial de aplicacion inconsistente.'
                        );
                    }

                    $original = $originales->first();

                    if (
                        (int) $original->saldo_favor_id
                            !== (int) $saldo->getKey()
                        || (int) $original->sucursal_id
                            !== $sucursalOperativaId
                        || !ImporteFinanciero::iguales(
                            $original->monto,
                            $aplicacion->monto_aplicado
                        )
                    ) {
                        throw new \LogicException(
                            'Movimiento de aplicacion inconsistente.'
                        );
                    }

                    $importesRestituidos = [];

                    foreach (
                        $movimientosAplicacion->where(
                            'tipo_movimiento',
                            'restitucion'
                        ) as $restitucion
                    ) {
                        if (
                            (int) $restitucion->movimiento_original_id
                                !== (int) $original->getKey()
                            || (int) $restitucion->saldo_favor_id
                                !== (int) $saldo->getKey()
                            || (int) $restitucion->sucursal_id
                                !== $sucursalOperativaId
                        ) {
                            throw new \LogicException(
                                'Restitucion inconsistente.'
                            );
                        }

                        $importesRestituidos[] = $restitucion->monto;
                    }

                    if (!ImporteFinanciero::iguales(
                        ImporteFinanciero::sumar($importesRestituidos),
                        $aplicacion->monto_aplicado
                    )) {
                        throw new \LogicException(
                            'La aplicacion no fue restituida completamente.'
                        );
                    }
                }

                $calculo = ValidadorSaldoFavor::calcular(
                    'cancelacion',
                    $saldo->estado,
                    $saldo->saldo_disponible,
                    $importe
                );

                $movimiento = \App\Models\MovimientoSaldoFavor::query()
                    ->create([
                        'saldo_favor_id' => $saldo->getKey(),
                        'sucursal_id' => $sucursalOperativaId,
                        'usuario_id' => $usuario->getKey(),
                        'autorizado_por' => $usuario->getKey(),
                        'tipo_movimiento' => 'cancelacion',
                        'monto' => $calculo['monto'],
                        'saldo_anterior' => $calculo['saldo_anterior'],
                        'saldo_nuevo' => $calculo['saldo_nuevo'],
                        'aplicacion_saldo_favor_id' => null,
                        'pago_id' => null,
                        'devolucion_id' => null,
                        'movimiento_original_id' => null,
                        'justificacion' => $justificacion,
                    ]);

                $saldo->forceFill([
                    'saldo_disponible' => $calculo['saldo_nuevo'],
                    'estado' => $calculo['estado_nuevo'],
                ])->save();

                $this->verificarHistorial($saldo->refresh());

                return (int) $movimiento->getKey();
            }
        );

        $movimiento = \App\Models\MovimientoSaldoFavor::query()
            ->whereKey($operacion->resultado_id)
            ->where('saldo_favor_id', $saldoFavorId)
            ->where('sucursal_id', $sucursalId)
            ->where('usuario_id', $usuario->getKey())
            ->where('autorizado_por', $usuario->getKey())
            ->where('tipo_movimiento', 'cancelacion')
            ->firstOrFail();

        if (
            $movimiento->justificacion !== $justificacion
            || !ImporteFinanciero::iguales(
                $movimiento->saldo_nuevo,
                '0.0000'
            )
        ) {
            throw new \LogicException(
                'El resultado idempotente de cancelacion es inconsistente.'
            );
        }

        return $movimiento;
    }

}
