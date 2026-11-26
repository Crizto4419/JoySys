<?php

namespace App\Services\Auth;

use App\Models\Sucursal;
use App\Models\User;

class ContextoSucursalService
{
    public function obtenerSucursal(
        User $usuario
    ): ?Sucursal {
        if (
            !$usuario->exists
            || $usuario->getKey() === null
        ) {
            return null;
        }

        $usuarioVigente = User::query()
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
            ->whereHas('role', function ($consulta) {
                $consulta->where(
                    'roles.estado',
                    'activo'
                );
            })
            ->first();

        if ($usuarioVigente === null) {
            return null;
        }

        $sucursales = $usuarioVigente
            ->sucursalPrincipal()
            ->where('sucursales.estado', 'activa')
            ->limit(2)
            ->get();

        if ($sucursales->count() !== 1) {
            return null;
        }

        return $sucursales->first();
    }

    public function obtenerSucursalId(
        User $usuario
    ): ?int {
        $sucursal = $this->obtenerSucursal($usuario);

        return $sucursal !== null
            ? (int) $sucursal->getKey()
            : null;
    }

    public function tieneSucursalActiva(
        User $usuario
    ): bool {
        return $this->obtenerSucursal($usuario) !== null;
    }

    public function puedeOperarEnSucursal(
        User $usuario,
        int $sucursalId
    ): bool {
        if ($sucursalId <= 0) {
            return false;
        }

        return $this->obtenerSucursalId($usuario)
            === $sucursalId;
    }
}