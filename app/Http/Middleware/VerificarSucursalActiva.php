<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\ContextoSucursalService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarSucursalActiva
{
    public function __construct(
        private readonly ContextoSucursalService $contextoSucursal
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $usuario = $request->user();

        if (!$usuario instanceof User) {
            abort(403);
        }

        $sucursal = $this->contextoSucursal
            ->obtenerSucursal($usuario);

        if ($sucursal === null) {
            abort(
                403,
                'No tienes una sucursal operativa habilitada.'
            );
        }

        $request->attributes->set(
            'sucursal_operativa',
            $sucursal
        );

        $request->attributes->set(
            'sucursal_operativa_id',
            (int) $sucursal->getKey()
        );

        return $next($request);
    }
}