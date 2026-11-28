<?php

namespace App\Services\Finanzas;

use App\Models\Caja;
use App\Models\DetallePago;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\Caja\CajaService;
use App\Services\Caja\MovimientoCajaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReembolsoService
{
    public const PERMISO = 'pagos.reembolsar';

    public function __construct(
        private readonly CajaService $cajas
    ) {}

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
                'El reembolso requiere controlar la transaccion exterior.'
            );
        }
    }

    private function obtenerOriginal(
        int $pagoId,
        int $sucursalId
    ): Pago {
        $pago = Pago::query()
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->findOrFail($pagoId);

        if (
            $pago->estado !== 'confirmado'
            || $pago->fecha_pago === null
            || $pago->fecha_pago->isFuture()
            || $pago->pago_original_id !== null
        ) {
            $this->error(
                'pago',
                'El pago original no admite reembolsos.'
            );
        }

        if ($pago->referencia_tipo !== 'venta') {
            $this->error(
                'pago',
                'Referencia comercial no admitida.'
            );
        }

        ValidadorPago::validarMoneda($pago->moneda);

        return $pago;
    }

    private function obtenerPartidas(Pago $pago)
    {
        $partidas = $pago->detalles()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($partidas->isEmpty()) {
            $this->error(
                'pago',
                'El pago original carece de partidas.'
            );
        }

        foreach ($partidas as $partida) {
            ValidadorPago::validarMetodo(
                $partida->metodo_pago
            );

            if ($partida->detalle_pago_original_id !== null) {
                $this->error(
                    'pago',
                    'El pago original contiene partidas incompatibles.'
                );
            }

            $movimiento = $partida->movimientoCaja()
                ->lockForUpdate()
                ->first();

            if ($partida->metodo_pago === 'efectivo') {
                if (
                    $movimiento === null
                    || $movimiento->tipo_movimiento !== 'ingreso'
                    || $movimiento->movimiento_original_id !== null
                    || !ImporteFinanciero::iguales(
                        $movimiento->monto,
                        $partida->monto
                    )
                ) {
                    $this->error(
                        'pago',
                        'El ingreso original es inconsistente.'
                    );
                }

                $sesion = SesionCaja::query()
                    ->where('sucursal_id', $pago->sucursal_id)
                    ->where('usuario_id', $pago->recibido_por)
                    ->lockForUpdate()
                    ->findOrFail($movimiento->sesion_caja_id);

                if (
                    (string) $movimiento->usuario_id
                    !== (string) $sesion->usuario_id
                ) {
                    $this->error(
                        'pago',
                        'El usuario del ingreso original es inconsistente.'
                    );
                }
            } elseif ($movimiento !== null) {
                $this->error(
                    'pago',
                    'Existe un movimiento de efectivo incompatible.'
                );
            }
        }

        if (!ImporteFinanciero::iguales(
            ImporteFinanciero::sumar(
                $partidas->pluck('monto')
            ),
            $pago->monto_total
        )) {
            $this->error(
                'pago',
                'El total del pago original es inconsistente.'
            );
        }

        return $partidas->keyBy('id');
    }

    private function historial(
        Pago $original,
        DetallePago $partida
    ): array {
        $detalles = DetallePago::query()
            ->where(
                'detalle_pago_original_id',
                $partida->getKey()
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $importes = [];

        foreach ($detalles as $detalle) {
            $pago = Pago::query()
                ->lockForUpdate()
                ->findOrFail($detalle->pago_id);

            if (
                (string) $pago->pago_original_id
                    !== (string) $original->getKey()
                || (string) $pago->sucursal_id
                    !== (string) $original->sucursal_id
                || $pago->referencia_tipo
                    !== $original->referencia_tipo
                || (string) $pago->referencia_id
                    !== (string) $original->referencia_id
                || $pago->moneda !== $original->moneda
                || $detalle->metodo_pago !== $partida->metodo_pago
            ) {
                $this->error(
                    'pago',
                    'El historial de reembolsos es inconsistente.'
                );
            }

            if ($pago->estado === 'cancelado') {
                if ($detalle->movimientoCaja()->exists()) {
                    $this->error(
                        'pago',
                        'Un reembolso cancelado tiene movimientos.'
                    );
                }

                continue;
            }

            if (
                $pago->estado !== 'confirmado'
                || $pago->fecha_pago === null
            ) {
                $this->error(
                    'pago',
                    'Existe un reembolso en estado incompatible.'
                );
            }

            $movimiento = $detalle->movimientoCaja()
                ->lockForUpdate()
                ->first();

            if ($detalle->metodo_pago === 'efectivo') {
                if (
                    $movimiento === null
                    || $movimiento->tipo_movimiento !== 'reembolso'
                    || $movimiento->movimiento_original_id !== null
                    || !ImporteFinanciero::iguales(
                        $movimiento->monto,
                        $detalle->monto
                    )
                ) {
                    $this->error(
                        'pago',
                        'El historial de efectivo es inconsistente.'
                    );
                }
            } elseif ($movimiento !== null) {
                $this->error(
                    'pago',
                    'Existe una salida de efectivo incompatible.'
                );
            }

            $importes[] = $detalle->monto;
        }

        return $importes;
    }

    private function obtenerSesion(
        User $usuario,
        int $sucursalId,
        string $efectivo
    ): ?SesionCaja {
        if (ImporteFinanciero::iguales($efectivo, '0')) {
            return null;
        }

        $sesion = SesionCaja::query()
            ->where('usuario_id', $usuario->getKey())
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'abierta')
            ->lockForUpdate()
            ->first();

        if ($sesion === null) {
            $this->error(
                'sesion_caja',
                'El reembolso requiere una caja abierta.'
            );
        }

        $caja = Caja::query()
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->findOrFail($sesion->caja_id);

        if (
            $caja->estado !== 'activa'
            || $caja->moneda !== 'MXN'
            || $sesion->fecha_apertura->isFuture()
        ) {
            $this->error(
                'caja',
                'La caja no esta disponible para reembolsos.'
            );
        }

        $movimientos = $sesion->movimientos()
            ->with('movimientoOriginal')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $saldo = MovimientoCajaService::calcularSaldo(
            $sesion->fondo_inicial,
            $movimientos
        );

        if (bccomp($saldo, $efectivo, 4) < 0) {
            $this->error(
                'monto',
                'El efectivo disponible es insuficiente.'
            );
        }

        MovimientoCajaService::verificarRango(
            bcsub($saldo, $efectivo, 4),
            'saldo_esperado'
        );

        return $sesion;
    }

    public function registrar(
        int $pagoOriginalId,
        array $datos,
        User $usuario,
        ?string $claveIdempotencia = null
    ): Pago {
        if ($claveIdempotencia !== null) {
            return $this->registrarIdempotente(
                $pagoOriginalId,
                $datos,
                $usuario,
                $claveIdempotencia
            );
        }

        $this->verificarTransaccionExterior();

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            self::PERMISO
        );

        $datos = ValidadorReembolso::validar($datos);

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $pagoOriginalId,
                $datos,
                $usuario,
                $sucursalId
            ): Pago {
                $original = $this->obtenerOriginal(
                    $pagoOriginalId,
                    $sucursalId
                );

                $partidas = $this->obtenerPartidas($original);

                $validados = [];
                $efectivo = ImporteFinanciero::CERO;

                foreach ($datos['detalles'] as $solicitud) {
                    $partida = $partidas->get(
                        $solicitud['detalle_pago_original_id']
                    );

                    if ($partida === null) {
                        $this->error(
                            'detalles',
                            'La partida no pertenece al pago original.'
                        );
                    }

                    $saldoFavorId = SeleccionSaldoFavor::normalizar(
                        $partida->metodo_pago,
                        $partida->saldo_favor_id
                    );

                    $monto = ValidadorReembolso::verificarLimite(
                        $solicitud['monto'],
                        $partida->monto,
                        $this->historial($original, $partida)
                    );

                    $validados[] = [
                        'detalle_pago_original_id' => $partida->getKey(),
                        'saldo_favor_id' => $saldoFavorId,
                        'metodo_pago' => $partida->metodo_pago,
                        'monto' => $monto,
                        'referencia' => null,
                        'detalle_adicional' => $datos['justificacion'],
                    ];

                    if ($partida->metodo_pago === 'efectivo') {
                        $efectivo = ImporteFinanciero::sumar([
                            $efectivo,
                            $monto,
                        ]);
                    }
                }

                $sesion = $this->obtenerSesion(
                    $usuario,
                    $sucursalId,
                    $efectivo
                );

                $reembolso = Pago::query()->create([
                    'sucursal_id' => $sucursalId,
                    'referencia_tipo' => $original->referencia_tipo,
                    'referencia_id' => $original->referencia_id,
                    'pago_original_id' => $original->getKey(),
                    'recibido_por' => $usuario->getKey(),
                    'monto_total' => $datos['monto_total'],
                    'moneda' => $original->moneda,
                    'estado' => 'pendiente',
                    'fecha_pago' => null,
                ]);

                foreach ($validados as $validado) {
                    $reembolso->detalles()->create($validado);
                }

                $reembolso->forceFill([
                    'estado' => 'confirmado',
                    'fecha_pago' => now(),
                ])->save();

                foreach ($reembolso->detalles()->orderBy('id')->get() as $detalle) {
                    if ($detalle->metodo_pago === 'saldo_favor') {
                        app(SaldoFavorService::class)->restituir(
                            (int) $detalle->getKey(),
                            $usuario
                        );

                        continue;
                    }

                    if ($detalle->metodo_pago !== 'efectivo') {
                        continue;
                    }

                    MovimientoCaja::query()->create([
                        'sesion_caja_id' => $sesion->getKey(),
                        'usuario_id' => $usuario->getKey(),
                        'autorizado_por' => $usuario->getKey(),
                        'detalle_pago_id' => $detalle->getKey(),
                        'movimiento_original_id' => null,
                        'tipo_movimiento' => 'reembolso',
                        'monto' => $detalle->monto,
                        'concepto' => 'Reembolso #'.$reembolso->getKey(),
                        'observaciones' => $datos['justificacion'],
                    ]);
                }

                return $reembolso->refresh()->load(
                    'detalles.movimientoCaja'
                );
            },
            false,
            self::PERMISO
        );
    }

    private function registrarIdempotente(
        int $pagoOriginalId,
        array $datos,
        User $usuario,
        string $claveIdempotencia
    ): Pago {
        $this->verificarTransaccionExterior();

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            self::PERMISO
        );

        $normalizados = ValidadorReembolso::validar($datos);

        $solicitud = [
            'pago_original_id' => $pagoOriginalId,
            'justificacion' => $normalizados['justificacion'],
            'detalles' => $normalizados['detalles'],
        ];

        $operacion = app(IdempotenciaService::class)->ejecutar(
            $usuario,
            self::PERMISO,
            'registro_reembolso',
            $claveIdempotencia,
            $solicitud,
            'pago',
            function (int $sucursalActual) use (
                $pagoOriginalId,
                $solicitud,
                $usuario
            ): int {
                $reembolso = $this->registrar(
                    $pagoOriginalId,
                    [
                        'justificacion' => $solicitud['justificacion'],
                        'detalles' => $solicitud['detalles'],
                    ],
                    $usuario
                );

                if ((int) $reembolso->sucursal_id !== $sucursalActual) {
                    throw new \LogicException(
                        'Sucursal del reembolso inconsistente.'
                    );
                }

                return (int) $reembolso->getKey();
            }
        );

        $reembolso = Pago::query()
            ->where('sucursal_id', $sucursalId)
            ->where('recibido_por', $usuario->getKey())
            ->where('pago_original_id', $pagoOriginalId)
            ->where('estado', 'confirmado')
            ->with('detalles.movimientoCaja')
            ->findOrFail($operacion->resultado_id);

        if (
            !ImporteFinanciero::iguales(
                $reembolso->monto_total,
                $normalizados['monto_total']
            )
        ) {
            throw new \LogicException(
                'El resultado idempotente del reembolso es inconsistente.'
            );
        }

        return $reembolso;
    }

}
