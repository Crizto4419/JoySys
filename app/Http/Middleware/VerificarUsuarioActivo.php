<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarUsuarioActivo
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $usuario = $request->user();

        $accesoPermitido = $usuario !== null
            && $usuario->estado === 'activo'
            && !$usuario->trashed()
            && (
                $usuario->bloqueado_hasta === null
                || !$usuario->bloqueado_hasta->isFuture()
            )
            && $usuario->role !== null
            && $usuario->role->estado === 'activo';

        if (!$accesoPermitido) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'username' => 'Tu sesión no está habilitada. Contacta al administrador.',
                ]);
        }

        return $next($request);
    }
}