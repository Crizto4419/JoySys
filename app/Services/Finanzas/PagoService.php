<?php

namespace App\Services\Finanzas;

use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;
use App\Services\Caja\CajaService;
use App\Services\Caja\MovimientoCajaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PagoService
{
    public const ESTADOS = [
        'pendiente',
        'confirmado',
        'cancelado',
    ];

    public const REFERENCIAS = [
        'venta',
    ];

    public const CAMPOS_REGISTRO = [
        'referencia_tipo',
        'referencia_id',
        'monto_total',
        'moneda',
        'detalles',
    ];

    public function __construct(
        private readonly CajaService $cajas,
        private readonly MovimientoCajaService $movimientos
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
            throw new \LogicException(
                'El motor financiero requiere controlar la transaccion exterior.'
            );
        }
    }

    private function validarDatos(array $datos): array
    {
        if (
            array_diff(
                array_keys($datos),
                self::CAMPOS_REGISTRO
            ) !== []
        ) {
            $this->error(
                'pago',
                'El pago contiene campos no permitidos.'
            );
        }

        $tipo = $datos['referencia_tipo'] ?? null;

        if (
            !is_string($tipo)
            || !in_array($tipo, self::REFERENCIAS, true)
        ) {
            $this->error(
                'referencia_tipo',
                'Referencia comercial no admitida.'
            );
        }

        $id = $datos['referencia_id'] ?? null;

        if (
            (!is_int($id) && !is_string($id))
            || filter_var(
                $id,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false
        ) {
            $this->error(
                'referencia_id',
                'Identificador comercial invalido.'
            );
        }

        $moneda = ValidadorPago::validarMoneda(
            $datos['moneda'] ?? 'MXN'
        );

        $total = ImporteFinanciero::normalizar(
            $datos['monto_total'] ?? null,
            false,
            'monto_total'
        );

        if (!is_array($datos['detalles'] ?? null)) {
            $this->error(
                'detalles',
                'Los detalles del pago son obligatorios.'
            );
        }

        $detalles = ValidadorPago::validarDetalles(
            $datos['detalles'],
            $total
        );

        return [
            'referencia_tipo' => $tipo,
            'referencia_id' => (int) $id,
            'monto_total' => $total,
            'moneda' => $moneda,
            'detalles' => $detalles,
        ];
    }

    private function obtenerVenta(
        int $id,
        int $sucursalId,
        User $usuario
    ): Venta {
        $venta = Venta::query()
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->findOrFail($id);

        if (
            (string) $venta->usuario_id
            !== (string) $usuario->getKey()
        ) {
            throw new AuthorizationException(
                'La venta pertenece a otro usuario.'
            );
        }

        if ($venta->estado === 'cancelada') {
            $this->error(
                'referencia_id',
                'No se puede cobrar una venta cancelada.'
            );
        }

        return $venta;
    }

    private function verificarDisponible(
        Venta $venta,
        string $importe,
        ?int $excluirPago = null
    ): void {
        $consulta = Pago::query()
            ->where('sucursal_id', $venta->sucursal_id)
            ->where('referencia_tipo', 'venta')
            ->where('referencia_id', $venta->getKey())
            ->whereNull('pago_original_id')
            ->whereIn('estado', [
                'pendiente',
                'confirmado',
            ]);

        if ($excluirPago !== null) {
            $consulta->where('id', '!=', $excluirPago);
        }

        $pagos = $consulta
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $ocupado = ImporteFinanciero::sumar(
            $pagos->pluck('monto_total')
        );

        $totalVenta = ImporteFinanciero::normalizar(
            $venta->total
        );

        if (
            bccomp(
                bcadd($ocupado, $importe, 4),
                $totalVenta,
                4
            ) > 0
        ) {
            $this->error(
                'monto_total',
                'El pago excede el importe disponible de la venta.'
            );
        }
    }

    private function crearPendiente(
        array $datos,
        User $usuario,
        int $sucursalId
    ): Pago {
        $venta = $this->obtenerVenta(
            $datos['referencia_id'],
            $sucursalId,
            $usuario
        );

        $this->verificarDisponible(
            $venta,
            $datos['monto_total']
        );

        SeleccionSaldoFavor::verificar(
            $venta,
            $datos['detalles'],
            $sucursalId
        );

        $pago = Pago::query()->create([
            'sucursal_id' => $sucursalId,
            'referencia_tipo' => $datos['referencia_tipo'],
            'referencia_id' => $datos['referencia_id'],
            'pago_original_id' => null,
            'recibido_por' => $usuario->getKey(),
            'monto_total' => $datos['monto_total'],
            'moneda' => $datos['moneda'],
            'estado' => 'pendiente',
            'fecha_pago' => null,
        ]);

        foreach ($datos['detalles'] as $detalle) {
            $pago->detalles()->create([
                ...$detalle,
                'detalle_pago_original_id' => null,
            ]);
        }

        return $pago->refresh()->load('detalles');
    }

    private function obtenerPropio(
        int $pagoId,
        User $usuario,
        int $sucursalId
    ): Pago {
        return Pago::query()
            ->where('sucursal_id', $sucursalId)
            ->where('recibido_por', $usuario->getKey())
            ->lockForUpdate()
            ->findOrFail($pagoId);
    }

    private function verificarPagoOrdinario(
        Pago $pago
    ): void {
        if ($pago->pago_original_id !== null) {
            $this->error(
                'pago',
                'La operacion corresponde a un reembolso.'
            );
        }

        if ($pago->referencia_tipo !== 'venta') {
            $this->error(
                'referencia_tipo',
                'Referencia comercial no admitida.'
            );
        }

        ValidadorPago::validarMoneda($pago->moneda);
    }

    private function confirmarInterno(
        Pago $pago,
        User $usuario,
        int $sucursalId
    ): Pago {
        $this->verificarPagoOrdinario($pago);

        if ($pago->estado === 'cancelado') {
            $this->error(
                'pago',
                'Un pago cancelado no puede confirmarse.'
            );
        }

        if (!in_array(
            $pago->estado,
            ['pendiente', 'confirmado'],
            true
        )) {
            $this->error(
                'pago',
                'Estado financiero incompatible.'
            );
        }

        $detalles = $pago->detalles()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $datos = [];

        foreach ($detalles as $detalle) {
            if ($detalle->detalle_pago_original_id !== null) {
                $this->error(
                    'detalles',
                    'El pago contiene referencias originales incompatibles.'
                );
            }

            $datos[] = [
                'metodo_pago' => $detalle->metodo_pago,
                'monto' => $detalle->monto,
                'referencia' => $detalle->referencia,
                'detalle_adicional' => $detalle->detalle_adicional,
                'saldo_favor_id' => $detalle->saldo_favor_id,
            ];
        }

        $validados = ValidadorPago::validarDetalles(
            $datos,
            $pago->monto_total
        );

        if ($pago->estado === 'pendiente') {
            $venta = $this->obtenerVenta(
                (int) $pago->referencia_id,
                $sucursalId,
                $usuario
            );

            $this->verificarDisponible(
                $venta,
                $pago->monto_total,
                (int) $pago->getKey()
            );

            SeleccionSaldoFavor::verificar(
                $venta,
                $validados,
                $sucursalId
            );

            foreach ($detalles as $detalle) {
                if (
                    $detalle->movimientoCaja()->exists()
                    || \App\Models\AplicacionSaldoFavor::query()
                        ->where('detalle_pago_id', $detalle->id)
                        ->exists()
                ) {
                    $this->error(
                        'pago',
                        'El pago pendiente contiene movimientos financieros.'
                    );
                }
            }

            $pago->forceFill([
                'estado' => 'confirmado',
                'fecha_pago' => now(),
            ])->save();

            foreach ($detalles as $detalle) {
                if ($detalle->metodo_pago === 'saldo_favor') {
                    app(SaldoFavorService::class)->aplicar(
                        (int) $detalle->saldo_favor_id,
                        (int) $detalle->id,
                        $usuario
                    );

                    continue;
                }

                $this->movimientos->registrarMovimientoPago(
                    $detalle,
                    $usuario
                );
            }
        } else {
            if ($pago->fecha_pago === null) {
                $this->error(
                    'pago',
                    'El pago confirmado carece de fecha.'
                );
            }

            foreach ($detalles as $detalle) {
                $movimiento = $detalle->movimientoCaja()
                    ->lockForUpdate()
                    ->first();

                if ($detalle->metodo_pago === 'saldo_favor') {
                    if ($movimiento !== null) {
                        $this->error(
                            'pago',
                            'El saldo a favor no genera efectivo.'
                        );
                    }

                    $aplicacion = \App\Models\AplicacionSaldoFavor::query()
                        ->where('detalle_pago_id', $detalle->id)
                        ->lockForUpdate()
                        ->first();

                    if ($aplicacion === null) {
                        $this->error(
                            'pago',
                            'El pago confirmado carece de su aplicacion.'
                        );
                    }

                    app(SaldoFavorService::class)->aplicar(
                        (int) $detalle->saldo_favor_id,
                        (int) $detalle->id,
                        $usuario
                    );

                    continue;
                }

                if ($detalle->metodo_pago === 'efectivo') {
                    if ($movimiento === null) {
                        $this->error(
                            'pago',
                            'El pago confirmado carece de su movimiento de caja.'
                        );
                    }

                    $this->movimientos->registrarMovimientoPago(
                        $detalle,
                        $usuario
                    );
                } elseif ($movimiento !== null) {
                    $this->error(
                        'pago',
                        'Existe un movimiento de caja incompatible.'
                    );
                }
            }
        }

        return $pago->refresh()->load(
            'detalles.movimientoCaja'
        );
    }

    public function registrarPendiente(
        array $datos,
        User $usuario,
        ?string $claveIdempotencia = null
    ): Pago {
        if ($claveIdempotencia !== null) {
            return $this->registrarIdempotente(
                $datos,
                $usuario,
                $claveIdempotencia,
                false
            );
        }

        $this->verificarTransaccionExterior();

        $permiso = 'pagos.registrar';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        $datos = $this->validarDatos($datos);

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            fn () => $this->crearPendiente(
                $datos,
                $usuario,
                $sucursalId
            ),
            false,
            $permiso
        );
    }

    public function registrarConfirmado(
        array $datos,
        User $usuario,
        ?string $claveIdempotencia = null
    ): Pago {
        if ($claveIdempotencia !== null) {
            return $this->registrarIdempotente(
                $datos,
                $usuario,
                $claveIdempotencia,
                true
            );
        }

        $this->verificarTransaccionExterior();

        $permiso = 'pagos.registrar';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        $datos = $this->validarDatos($datos);

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $datos,
                $usuario,
                $sucursalId
            ) {
                $pago = $this->crearPendiente(
                    $datos,
                    $usuario,
                    $sucursalId
                );

                return $this->confirmarInterno(
                    $pago,
                    $usuario,
                    $sucursalId
                );
            },
            false,
            $permiso
        );
    }

    private function registrarIdempotente(
        array $datos,
        User $usuario,
        string $claveIdempotencia,
        bool $confirmado
    ): Pago {
        $this->verificarTransaccionExterior();

        $permiso = 'pagos.registrar';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        $datos = $this->validarDatos($datos);

        $tipo = $confirmado
            ? 'registro_pago_confirmado'
            : 'registro_pago_pendiente';

        $operacion = app(IdempotenciaService::class)->ejecutar(
            $usuario,
            $permiso,
            $tipo,
            $claveIdempotencia,
            $datos,
            'pago',
            function (int $sucursalActual) use (
                $datos,
                $usuario,
                $confirmado
            ): int {
                $pago = $this->crearPendiente(
                    $datos,
                    $usuario,
                    $sucursalActual
                );

                if ($confirmado) {
                    $pago = $this->confirmarInterno(
                        $pago,
                        $usuario,
                        $sucursalActual
                    );
                }

                return (int) $pago->getKey();
            }
        );

        $pago = Pago::query()
            ->where('sucursal_id', $sucursalId)
            ->where('recibido_por', $usuario->getKey())
            ->where('referencia_tipo', $datos['referencia_tipo'])
            ->where('referencia_id', $datos['referencia_id'])
            ->whereNull('pago_original_id')
            ->with('detalles.movimientoCaja')
            ->findOrFail($operacion->resultado_id);

        if (
            !ImporteFinanciero::iguales(
                $pago->monto_total,
                $datos['monto_total']
            )
            || $pago->moneda !== $datos['moneda']
            || ($confirmado && $pago->estado !== 'confirmado')
        ) {
            throw new \LogicException(
                'El resultado idempotente del pago es inconsistente.'
            );
        }

        return $pago;
    }

    public function confirmar(
        int $pagoId,
        User $usuario
    ): Pago {
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
                $pagoId,
                $usuario,
                $sucursalId
            ) {
                $pago = $this->obtenerPropio(
                    $pagoId,
                    $usuario,
                    $sucursalId
                );

                return $this->confirmarInterno(
                    $pago,
                    $usuario,
                    $sucursalId
                );
            },
            false,
            $permiso
        );
    }

    public function cancelar(
        int $pagoId,
        User $usuario
    ): Pago {
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
                $pagoId,
                $usuario,
                $sucursalId
            ) {
                $pago = $this->obtenerPropio(
                    $pagoId,
                    $usuario,
                    $sucursalId
                );

                $this->verificarPagoOrdinario($pago);

                if ($pago->estado === 'confirmado') {
                    $this->error(
                        'pago',
                        'Un pago confirmado requiere un reembolso.'
                    );
                }

                if (!in_array(
                    $pago->estado,
                    ['pendiente', 'cancelado'],
                    true
                )) {
                    $this->error(
                        'pago',
                        'Estado financiero incompatible.'
                    );
                }

                if ($pago->fecha_pago !== null) {
                    $this->error(
                        'pago',
                        'El pago contiene una fecha incompatible.'
                    );
                }

                $detalles = $pago->detalles()
                    ->lockForUpdate()
                    ->get();

                foreach ($detalles as $detalle) {
                    if (
                        $detalle->movimientoCaja()->exists()
                        || \App\Models\AplicacionSaldoFavor::query()
                            ->where('detalle_pago_id', $detalle->id)
                            ->exists()
                    ) {
                        $this->error(
                            'pago',
                            'El pago ya tiene movimientos financieros.'
                        );
                    }
                }

                if ($pago->estado === 'pendiente') {
                    $pago->forceFill([
                        'estado' => 'cancelado',
                    ])->save();
                }

                return $pago->refresh()->load('detalles');
            },
            false,
            $permiso
        );
    }

    public function consultar(
        int $pagoId,
        User $usuario
    ): Pago {
        $sucursalId = $this->cajas->autorizar(
            $usuario,
            'pagos.ver'
        );

        return Pago::query()
            ->where('sucursal_id', $sucursalId)
            ->where('recibido_por', $usuario->getKey())
            ->with('detalles.movimientoCaja')
            ->findOrFail($pagoId);
    }
}
