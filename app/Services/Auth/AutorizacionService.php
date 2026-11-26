<?php

namespace App\Services\Auth;

use App\Models\User;

class AutorizacionService
{
    /**
     * Determina si un usuario tiene un permiso vigente.
     */
    public function tienePermiso(
        User $usuario,
        string $clave
    ): bool {
        if (trim($clave) === '') {
            return false;
        }

        if (! $usuario->exists || $usuario->getKey() === null) {
            return false;
        }

        return User::query()
            ->whereKey($usuario->getKey())
            ->where('estado', 'activo')
            ->where(function ($consulta) {
                $consulta
                    ->whereNull('bloqueado_hasta')
                    ->orWhere(
                        'bloqueado_hasta',
                        '<=',
                        now()
                    );
            })
            ->whereHas('role', function ($consulta) use ($clave) {
                $consulta
                    ->where('roles.estado', 'activo')
                    ->whereHas(
                        'permisos',
                        function ($permisos) use ($clave) {
                            $permisos->where(
                                'permisos.clave',
                                $clave
                            );
                        }
                    );
            })
            ->exists();
    }
}