<?php

declare(strict_types=1);

namespace App\Services\Finanzas;

use App\Models\OperacionIdempotente;
use App\Models\User;
use App\Services\Caja\CajaService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class IdempotenciaService
{
    public function __construct(
        private readonly CajaService $cajas
    ) {
    }

    private static function error(
        string $campo,
        string $mensaje
    ): never {
        throw ValidationException::withMessages([
            $campo => $mensaje,
        ]);
    }

    public static function validarClave(mixed $clave): string
    {
        if (
            !is_string($clave)
            || !preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,99}\z/',
                $clave
            )
        ) {
            self::error(
                'clave_idempotencia',
                'Clave de idempotencia invalida.'
            );
        }

        return $clave;
    }

    private static function canonizar(mixed $valor): mixed
    {
        if (is_array($valor)) {
            if (!array_is_list($valor)) {
                ksort($valor, SORT_STRING);
            }

            foreach ($valor as $clave => $elemento) {
                $valor[$clave] = self::canonizar($elemento);
            }

            return $valor;
        }

        if (
            $valor === null
            || is_bool($valor)
            || is_int($valor)
            || is_string($valor)
        ) {
            return $valor;
        }

        self::error(
            'solicitud',
            'La solicitud contiene valores no admitidos.'
        );
    }

    public static function hashSolicitud(array $datos): string
    {
        return hash(
            'sha256',
            json_encode(
                self::canonizar($datos),
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );
    }

    public function ejecutar(
        User $usuario,
        string $permiso,
        string $tipo,
        string $clave,
        array $solicitudNormalizada,
        string $resultadoTipo,
        Closure $operacion
    ): OperacionIdempotente {
        if (
            DB::transactionLevel() > 0
            && !$this->cajas->tieneBloqueoTransaccional()
            && !app()->environment('testing')
        ) {
            throw new LogicException(
                'La idempotencia debe controlar la transaccion exterior.'
            );
        }

        $clave = self::validarClave($clave);

        foreach ([$tipo, $resultadoTipo] as $identificador) {
            if (!preg_match(
                '/\A[a-z][a-z0-9_]{0,49}\z/',
                $identificador
            )) {
                self::error(
                    'tipo_operacion',
                    'Identificador de operacion invalido.'
                );
            }
        }

        $hash = self::hashSolicitud($solicitudNormalizada);

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $usuario,
                $sucursalId,
                $tipo,
                $clave,
                $hash,
                $resultadoTipo,
                $operacion
            ): OperacionIdempotente {
                $registro = OperacionIdempotente::query()
                    ->where('sucursal_id', $sucursalId)
                    ->where('usuario_id', $usuario->id)
                    ->where('tipo_operacion', $tipo)
                    ->where('clave', $clave)
                    ->lockForUpdate()
                    ->first();

                if ($registro !== null) {
                    if (!hash_equals(
                        $registro->hash_solicitud,
                        $hash
                    )) {
                        self::error(
                            'clave_idempotencia',
                            'La clave ya corresponde a otra solicitud.'
                        );
                    }

                    if (
                        $registro->resultado_tipo !== $resultadoTipo
                        || $registro->resultado_id === null
                    ) {
                        throw new LogicException(
                            'Resultado idempotente inconsistente.'
                        );
                    }

                    return $registro;
                }

                $registro = OperacionIdempotente::create([
                    'sucursal_id' => $sucursalId,
                    'usuario_id' => $usuario->id,
                    'tipo_operacion' => $tipo,
                    'clave' => $clave,
                    'hash_solicitud' => $hash,
                    'resultado_tipo' => null,
                    'resultado_id' => null,
                ]);

                $resultadoId = $operacion($sucursalId);

                if (!is_int($resultadoId) || $resultadoId < 1) {
                    throw new LogicException(
                        'La operacion no devolvio un identificador valido.'
                    );
                }

                $registro->forceFill([
                    'resultado_tipo' => $resultadoTipo,
                    'resultado_id' => $resultadoId,
                ])->save();

                return $registro->refresh();
            },
            false,
            $permiso
        );
    }
}