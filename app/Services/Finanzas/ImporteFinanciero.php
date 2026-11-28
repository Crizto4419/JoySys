<?php

namespace App\Services\Finanzas;

use App\Services\Caja\MovimientoCajaService;
use Illuminate\Validation\ValidationException;

final class ImporteFinanciero
{
    public const ESCALA = 4;

    public const MAXIMO = '99999999.9999';

    public const CERO = '0.0000';

    public static function normalizar(
        mixed $importe,
        bool $permitirCero = false,
        string $campo = 'monto'
    ): string {
        return MovimientoCajaService::normalizarMonto(
            $importe,
            $permitirCero,
            $campo
        );
    }

    public static function sumar(
        iterable $importes,
        string $campo = 'monto_total'
    ): string {
        $total = self::CERO;

        foreach ($importes as $importe) {
            $normalizado = self::normalizar(
                $importe,
                true,
                $campo
            );

            $total = bcadd(
                $total,
                $normalizado,
                self::ESCALA
            );

            if (
                bccomp(
                    $total,
                    self::MAXIMO,
                    self::ESCALA
                ) > 0
            ) {
                throw ValidationException::withMessages([
                    $campo => 'El importe excede el limite monetario.',
                ]);
            }
        }

        return $total;
    }

    public static function restar(
        mixed $importe,
        mixed $descuento,
        string $campo = 'monto'
    ): string {
        $importe = self::normalizar(
            $importe,
            true,
            $campo
        );

        $descuento = self::normalizar(
            $descuento,
            true,
            $campo
        );

        if (
            bccomp(
                $descuento,
                $importe,
                self::ESCALA
            ) > 0
        ) {
            throw ValidationException::withMessages([
                $campo => 'El resultado monetario no puede ser negativo.',
            ]);
        }

        return bcsub(
            $importe,
            $descuento,
            self::ESCALA
        );
    }

    public static function iguales(
        mixed $primero,
        mixed $segundo
    ): bool {
        return bccomp(
            self::normalizar($primero, true),
            self::normalizar($segundo, true),
            self::ESCALA
        ) === 0;
    }
}
