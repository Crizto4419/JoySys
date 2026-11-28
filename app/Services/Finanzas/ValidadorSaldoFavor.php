<?php

declare(strict_types=1);

namespace App\Services\Finanzas;

use Illuminate\Validation\ValidationException;

final class ValidadorSaldoFavor
{
    public const ESTADOS = [
        'activo',
        'agotado',
        'cancelado',
    ];

    public const TIPOS = [
        'generacion',
        'aplicacion',
        'restitucion',
        'cancelacion',
    ];

    private static function error(
        string $campo,
        string $mensaje
    ): never {
        throw ValidationException::withMessages([
            $campo => $mensaje,
        ]);
    }

    public static function validarEstado(
        string $estado,
        mixed $saldo
    ): string {
        $saldo = ImporteFinanciero::normalizar(
            $saldo,
            true,
            'saldo'
        );

        if (!in_array($estado, self::ESTADOS, true)) {
            self::error('estado', 'Estado de saldo invalido.');
        }

        if (
            $estado !== 'activo'
            && !ImporteFinanciero::iguales($saldo, '0')
        ) {
            self::error(
                'saldo',
                'Un saldo no activo debe ser cero.'
            );
        }

        return $saldo;
    }

    public static function calcular(
        string $tipo,
        string $estado,
        mixed $saldoAnterior,
        mixed $monto
    ): array {
        $anterior = self::validarEstado(
            $estado,
            $saldoAnterior
        );

        $monto = ImporteFinanciero::normalizar($monto);

        if (!in_array($tipo, self::TIPOS, true)) {
            self::error(
                'tipo_movimiento',
                'Tipo de movimiento invalido.'
            );
        }

        if ($estado === 'cancelado') {
            self::error(
                'estado',
                'Un saldo cancelado no admite movimientos.'
            );
        }

        if ($tipo === 'generacion') {
            if (
                $estado !== 'activo'
                || !ImporteFinanciero::iguales($anterior, '0')
            ) {
                self::error(
                    'saldo',
                    'La generacion requiere un saldo inicial cero.'
                );
            }

            $nuevo = $monto;
        } elseif ($tipo === 'restitucion') {
            $nuevo = ImporteFinanciero::sumar([
                $anterior,
                $monto,
            ]);
        } else {
            if ($estado !== 'activo') {
                self::error(
                    'estado',
                    'El saldo debe estar activo.'
                );
            }

            $nuevo = ImporteFinanciero::restar(
                $anterior,
                $monto
            );
        }

        if (
            $tipo === 'cancelacion'
            && !ImporteFinanciero::iguales($nuevo, '0')
        ) {
            self::error(
                'monto',
                'La cancelacion debe consumir el saldo completo.'
            );
        }

        $estadoNuevo = match (true) {
            $tipo === 'cancelacion' => 'cancelado',
            ImporteFinanciero::iguales($nuevo, '0') => 'agotado',
            default => 'activo',
        };

        return [
            'tipo_movimiento' => $tipo,
            'monto' => $monto,
            'saldo_anterior' => $anterior,
            'saldo_nuevo' => $nuevo,
            'estado_nuevo' => $estadoNuevo,
        ];
    }
}