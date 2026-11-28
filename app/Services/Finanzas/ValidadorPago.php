<?php

namespace App\Services\Finanzas;

use App\Services\Caja\MovimientoCajaService;
use Illuminate\Validation\ValidationException;

final class ValidadorPago
{
    public const MONEDA = 'MXN';

    public const CAMPOS_DETALLE = [
        'metodo_pago',
        'monto',
        'referencia',
        'detalle_adicional',
        'saldo_favor_id',
    ];

    public static function validarMoneda(
        mixed $moneda
    ): string {
        if ($moneda !== self::MONEDA) {
            throw ValidationException::withMessages([
                'moneda' => 'La moneda del pago debe ser MXN.',
            ]);
        }

        return self::MONEDA;
    }

    public static function validarMetodo(
        mixed $metodo,
        string $campo = 'metodo_pago'
    ): string {
        if (
            !is_string($metodo)
            || !in_array(
                $metodo,
                MovimientoCajaService::METODOS_PAGO,
                true
            )
        ) {
            throw ValidationException::withMessages([
                $campo => 'Metodo de pago no reconocido.',
            ]);
        }

        return $metodo;
    }

    private static function textoOpcional(
        mixed $valor,
        int $maximo,
        string $campo
    ): ?string {
        if ($valor === null) {
            return null;
        }

        if (!is_string($valor)) {
            throw ValidationException::withMessages([
                $campo => 'El campo debe contener texto.',
            ]);
        }

        $valor = trim($valor);

        if (mb_strlen($valor) > $maximo) {
            throw ValidationException::withMessages([
                $campo => 'El texto excede la longitud permitida.',
            ]);
        }

        return $valor === '' ? null : $valor;
    }

    public static function validarDetalles(
        array $detalles,
        mixed $totalEsperado
    ): array {
        $totalEsperado = ImporteFinanciero::normalizar(
            $totalEsperado,
            false,
            'monto_total'
        );

        if (
            $detalles === []
            || !array_is_list($detalles)
        ) {
            throw ValidationException::withMessages([
                'detalles' => 'Debe existir al menos un detalle de pago.',
            ]);
        }

        $normalizados = [];

        foreach ($detalles as $indice => $detalle) {
            $campo = "detalles.$indice";

            if (!is_array($detalle)) {
                throw ValidationException::withMessages([
                    $campo => 'El detalle de pago no es valido.',
                ]);
            }

            $extras = array_diff(
                array_keys($detalle),
                self::CAMPOS_DETALLE
            );

            if ($extras !== []) {
                throw ValidationException::withMessages([
                    $campo => 'El detalle contiene campos no permitidos.',
                ]);
            }

            $metodo = self::validarMetodo(
                $detalle['metodo_pago'] ?? null,
                "$campo.metodo_pago"
            );

            $saldoFavorId = SeleccionSaldoFavor::normalizar(
                $metodo,
                $detalle['saldo_favor_id'] ?? null
            );

            $normalizados[] = [
                'metodo_pago' => $metodo,

                'monto' => ImporteFinanciero::normalizar(
                    $detalle['monto'] ?? null,
                    false,
                    "$campo.monto"
                ),

                'referencia' => self::textoOpcional(
                    $detalle['referencia'] ?? null,
                    150,
                    "$campo.referencia"
                ),

                'detalle_adicional' => self::textoOpcional(
                    $detalle['detalle_adicional'] ?? null,
                    255,
                    "$campo.detalle_adicional"
                ),
            ];

            if ($saldoFavorId !== null) {
                $normalizados[
                    array_key_last($normalizados)
                ]['saldo_favor_id'] = $saldoFavorId;
            }
        }

        $total = ImporteFinanciero::sumar(
            array_column($normalizados, 'monto')
        );

        if (!ImporteFinanciero::iguales($total, $totalEsperado)) {
            throw ValidationException::withMessages([
                'monto_total' => 'La suma de los detalles no coincide con el pago.',
            ]);
        }

        return $normalizados;
    }

    public static function resumir(
        array $detalles,
        mixed $totalEsperado
    ): array {
        $detalles = self::validarDetalles(
            $detalles,
            $totalEsperado
        );

        $porMetodo = [];

        foreach (
            MovimientoCajaService::METODOS_PAGO as $metodo
        ) {
            $porMetodo[$metodo] = ImporteFinanciero::CERO;
        }

        foreach ($detalles as $detalle) {
            $metodo = $detalle['metodo_pago'];

            $porMetodo[$metodo] = ImporteFinanciero::sumar([
                $porMetodo[$metodo],
                $detalle['monto'],
            ]);
        }

        $total = ImporteFinanciero::sumar($porMetodo);

        return [
            'monto_total' => $total,

            'efectivo' => $porMetodo['efectivo'],

            'no_efectivo' => ImporteFinanciero::restar(
                $total,
                $porMetodo['efectivo']
            ),

            'por_metodo' => $porMetodo,

            'detalles' => $detalles,
        ];
    }
}
