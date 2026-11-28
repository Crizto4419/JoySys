<?php

namespace App\Services\Finanzas;

use App\Models\Cliente;
use App\Models\SaldoFavor;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SeleccionSaldoFavor
{
    private static function error(string $mensaje): never
    {
        throw ValidationException::withMessages([
            'detalles' => $mensaje,
        ]);
    }

    public static function normalizar(
        string $metodo,
        mixed $saldoFavorId
    ): ?int {
        if ($metodo !== 'saldo_favor') {
            if ($saldoFavorId !== null) {
                self::error(
                    'El metodo de pago no admite un credito asociado.'
                );
            }

            return null;
        }

        if (
            (!is_int($saldoFavorId) && !is_string($saldoFavorId))
            || filter_var(
                $saldoFavorId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false
        ) {
            self::error(
                'Debe seleccionar un saldo a favor valido.'
            );
        }

        return (int) $saldoFavorId;
    }

    public static function verificar(
        Venta $venta,
        array $detalles,
        int $sucursalId
    ): void {
        if (
            DB::connection()->transactionLevel() < 1
            || !app(
                \App\Services\Caja\CajaService::class
            )->tieneBloqueoTransaccional()
        ) {
            throw new \LogicException(
                'La seleccion requiere una transaccion activa.'
            );
        }

        if ((int) $venta->sucursal_id !== $sucursalId) {
            self::error(
                'La venta no pertenece a la sucursal.'
            );
        }

        $creditos = [];

        foreach ($detalles as $detalle) {
            $id = self::normalizar(
                $detalle['metodo_pago'],
                $detalle['saldo_favor_id'] ?? null
            );

            if ($id === null) {
                continue;
            }

            if (isset($creditos[$id])) {
                self::error(
                    'Cada credito debe aparecer en una sola partida.'
                );
            }

            $creditos[$id] = ImporteFinanciero::normalizar(
                $detalle['monto']
            );
        }

        if ($creditos === []) {
            return;
        }

        if ($venta->cliente_id === null) {
            self::error(
                'La venta requiere un cliente identificado.'
            );
        }

        $cliente = Cliente::query()
            ->lockForUpdate()
            ->find($venta->cliente_id);

        if ($cliente === null || !$cliente->activo) {
            self::error(
                'El cliente no se encuentra disponible.'
            );
        }

        ksort($creditos, SORT_NUMERIC);

        foreach ($creditos as $id => $monto) {
            $saldo = SaldoFavor::query()
                ->where('sucursal_id', $sucursalId)
                ->where('cliente_id', $cliente->getKey())
                ->where('moneda', 'MXN')
                ->lockForUpdate()
                ->find($id);

            if ($saldo === null) {
                self::error(
                    'El credito no pertenece al cliente o sucursal.'
                );
            }

            if ($saldo->estado !== 'activo') {
                self::error(
                    'El credito no se encuentra activo.'
                );
            }

            if (
                bccomp(
                    $saldo->saldo_disponible,
                    $monto,
                    4
                ) < 0
            ) {
                self::error(
                    'El saldo a favor disponible es insuficiente.'
                );
            }
        }
    }
}
