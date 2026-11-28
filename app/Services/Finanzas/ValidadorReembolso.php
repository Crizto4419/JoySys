<?php

namespace App\Services\Finanzas;

use Illuminate\Validation\ValidationException;

final class ValidadorReembolso
{
    public const CAMPOS = [
        'justificacion',
        'detalles',
    ];

    public const CAMPOS_DETALLE = [
        'detalle_pago_original_id',
        'monto',
    ];

    private static function error(
        string $campo,
        string $mensaje
    ): never {
        throw ValidationException::withMessages([
            $campo => $mensaje,
        ]);
    }

    public static function justificar(
        mixed $valor
    ): string {
        if (!is_string($valor)) {
            self::error(
                'justificacion',
                'La justificacion es obligatoria.'
            );
        }

        $valor = trim($valor);

        if ($valor === '' || mb_strlen($valor) > 255) {
            self::error(
                'justificacion',
                'La justificacion debe contener entre 1 y 255 caracteres.'
            );
        }

        return $valor;
    }

    public static function disponible(
        mixed $original,
        iterable $reembolsos
    ): string {
        $original = ImporteFinanciero::normalizar(
            $original,
            false,
            'monto'
        );

        $acumulado = ImporteFinanciero::sumar(
            $reembolsos,
            'monto'
        );

        return ImporteFinanciero::restar(
            $original,
            $acumulado,
            'monto'
        );
    }

    public static function validar(
        array $datos
    ): array {
        if (
            array_diff(
                array_keys($datos),
                self::CAMPOS
            ) !== []
        ) {
            self::error(
                'reembolso',
                'El reembolso contiene campos no permitidos.'
            );
        }

        $justificacion = self::justificar(
            $datos['justificacion'] ?? null
        );

        $detalles = $datos['detalles'] ?? null;

        if (
            !is_array($detalles)
            || $detalles === []
            || !array_is_list($detalles)
        ) {
            self::error(
                'detalles',
                'Debe existir al menos una partida de reembolso.'
            );
        }

        $normalizados = [];
        $identificadores = [];

        foreach ($detalles as $indice => $detalle) {
            $campo = "detalles.$indice";

            if (
                !is_array($detalle)
                || array_diff(
                    array_keys($detalle),
                    self::CAMPOS_DETALLE
                ) !== []
            ) {
                self::error(
                    $campo,
                    'La partida contiene campos incompatibles.'
                );
            }

            $id = $detalle['detalle_pago_original_id'] ?? null;

            if (
                (!is_int($id) && !is_string($id))
                || filter_var(
                    $id,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                ) === false
            ) {
                self::error(
                    "$campo.detalle_pago_original_id",
                    'Identificador de partida invalido.'
                );
            }

            $id = (int) $id;

            if (isset($identificadores[$id])) {
                self::error(
                    "$campo.detalle_pago_original_id",
                    'La partida original esta duplicada.'
                );
            }

            $identificadores[$id] = true;

            $normalizados[] = [
                'detalle_pago_original_id' => $id,
                'monto' => ImporteFinanciero::normalizar(
                    $detalle['monto'] ?? null,
                    false,
                    "$campo.monto"
                ),
            ];
        }

        return [
            'justificacion' => $justificacion,
            'monto_total' => ImporteFinanciero::sumar(
                array_column($normalizados, 'monto')
            ),
            'detalles' => $normalizados,
        ];
    }

    public static function verificarLimite(
        mixed $solicitado,
        mixed $original,
        iterable $reembolsos
    ): string {
        $solicitado = ImporteFinanciero::normalizar(
            $solicitado
        );

        $disponible = self::disponible(
            $original,
            $reembolsos
        );

        if (
            bccomp(
                $solicitado,
                $disponible,
                ImporteFinanciero::ESCALA
            ) > 0
        ) {
            self::error(
                'monto',
                'El reembolso excede el importe disponible.'
            );
        }

        return $solicitado;
    }
}
