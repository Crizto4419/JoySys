<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;

class AutenticacionService
{
    public const MAX_INTENTOS = 5;

    public const MINUTOS_BLOQUEO = 15;

    public function autenticar(
        string $username,
        string $password
    ): ?User {
        return (new Timebox())->call(
            function () use ($username, $password): ?User {
                return DB::transaction(
                    function () use ($username, $password): ?User {
                        $usuario = User::query()
                            ->where('username', $username)
                            ->lockForUpdate()
                            ->first();

                        if (!$usuario) {
                            return null;
                        }

                        if ($usuario->estado !== 'activo') {
                            return null;
                        }

                        $rol = $usuario->role;

                        if (!$rol || $rol->estado !== 'activo') {
                            return null;
                        }

                        if ($usuario->bloqueado_hasta !== null) {
                            if ($usuario->bloqueado_hasta->isFuture()) {
                                return null;
                            }

                            $usuario->forceFill([
                                'intentos_fallidos' => 0,
                                'bloqueado_hasta' => null,
                            ])->save();
                        }

                        if (!Hash::check(
                            $password,
                            $usuario->password
                        )) {
                            $this->registrarIntentoFallido($usuario);

                            return null;
                        }

                        if (Hash::needsRehash($usuario->password)) {
                            $usuario->password = $password;
                        }

                        $usuario->forceFill([
                            'intentos_fallidos' => 0,
                            'bloqueado_hasta' => null,
                            'ultimo_acceso' => now(),
                        ])->save();

                        return $usuario;
                    },
                    3
                );
            },
            500000
        );
    }

    private function registrarIntentoFallido(
        User $usuario
    ): void {
        $intentos = min(
            $usuario->intentos_fallidos + 1,
            self::MAX_INTENTOS
        );

        $usuario->intentos_fallidos = $intentos;

        if ($intentos >= self::MAX_INTENTOS) {
            $usuario->bloqueado_hasta = now()->addMinutes(
                self::MINUTOS_BLOQUEO
            );
        }

        $usuario->save();
    }
}