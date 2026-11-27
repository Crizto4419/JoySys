<?php

namespace App\Services\Caja;

use App\Models\Caja;
use App\Models\SesionCaja;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SesionCajaService
{
    public function __construct(
        private readonly CajaService $cajas
    ) {}

    public static function reglasApertura(): array
    {
        return [
            'caja_id' => ['required', 'integer', 'min:1'],
            'fondo_inicial' => [
                'required',
                'regex:/\A[0-9]{1,8}(?:\.[0-9]{1,4})?\z/',
            ],
            'id' => ['prohibited'],
            'usuario_id' => ['prohibited'],
            'sucursal_id' => ['prohibited'],
            'estado' => ['prohibited'],
            'moneda' => ['prohibited'],
            'cerrada_por' => ['prohibited'],
            'caja_abierta_id' => ['prohibited'],
            'empleado_abierto_id' => ['prohibited'],
            'saldo_esperado' => ['prohibited'],
            'efectivo_contado' => ['prohibited'],
            'diferencia' => ['prohibited'],
            'fecha_apertura' => ['prohibited'],
            'fecha_cierre' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public static function normalizarFondo(mixed $importe): string
    {
        if (! is_string($importe) && ! is_int($importe)) {
            throw ValidationException::withMessages([
                'fondo_inicial' => 'El fondo debe ser un importe decimal válido.',
            ]);
        }

        $importe = trim((string) $importe);

        if (! preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,4})?\z/', $importe)) {
            throw ValidationException::withMessages([
                'fondo_inicial' => 'El fondo debe estar entre 0 y 99999999.9999, con hasta cuatro decimales.',
            ]);
        }

        $partes = explode('.', $importe, 2);

        $entero = ltrim($partes[0], '0');

        if ($entero === '') {
            $entero = '0';
        }

        return $entero.'.'.str_pad(
            $partes[1] ?? '',
            4,
            '0',
            STR_PAD_RIGHT
        );
    }

    public function obtenerAbierta(User $usuario): ?SesionCaja
    {
        $sucursalId = $this->cajas->autorizar($usuario);

        return SesionCaja::query()
            ->where('usuario_id', $usuario->getKey())
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'abierta')
            ->with(['caja', 'sucursal'])
            ->first();
    }

    public function abrir(array $datos, User $usuario): SesionCaja
    {
        $sucursalId = $this->cajas->autorizar(
            $usuario,
            'caja.abrir'
        );

        $fondo = self::normalizarFondo(
            $datos['fondo_inicial'] ?? null
        );

        $datos['fondo_inicial'] = $fondo;

        $validados = Validator::make(
            $datos,
            self::reglasApertura()
        )->validate();

        try {
            return $this->cajas->ejecutar(
                $usuario,
                $sucursalId,
                function () use ($usuario, $sucursalId, $validados, $fondo) {
                    $caja = Caja::query()
                        ->where('sucursal_id', $sucursalId)
                        ->lockForUpdate()
                        ->findOrFail($validados['caja_id']);

                    if ($caja->estado !== 'activa' || $caja->moneda !== 'MXN') {
                        throw ValidationException::withMessages([
                            'caja_id' => 'La caja seleccionada no está disponible.',
                        ]);
                    }

                    if (
                        SesionCaja::query()
                            ->where('usuario_id', $usuario->getKey())
                            ->where('estado', 'abierta')
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'caja_id' => 'Ya tienes una sesión de caja abierta.',
                        ]);
                    }

                    if (
                        SesionCaja::query()
                            ->where('caja_id', $caja->getKey())
                            ->where('estado', 'abierta')
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'caja_id' => 'La caja ya tiene una sesión abierta.',
                        ]);
                    }

                    $sesion = SesionCaja::query()->create([
                        'caja_id' => $caja->getKey(),
                        'sucursal_id' => $sucursalId,
                        'usuario_id' => $usuario->getKey(),
                        'fondo_inicial' => $fondo,
                        'estado' => 'abierta',
                        'fecha_apertura' => now(),
                        'cerrada_por' => null,
                        'saldo_esperado' => null,
                        'efectivo_contado' => null,
                        'diferencia' => null,
                        'fecha_cierre' => null,
                    ]);

                    return $sesion->refresh()->load([
                        'caja',
                        'sucursal',
                    ]);
                },
                false,
                'caja.abrir'
            );
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw ValidationException::withMessages([
                    'caja_id' => 'La caja o el empleado ya tiene una sesión abierta.',
                ]);
            }

            throw $exception;
        }
    }
}
