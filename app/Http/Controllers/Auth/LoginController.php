<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\AutenticacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        LoginRequest $request,
        AutenticacionService $autenticacion
    ): RedirectResponse {
        $credenciales = $request->validated();

        $clave = 'joysys:login:ip:' . hash(
            'sha256',
            (string) $request->ip()
        );

        $usuario = RateLimiter::attempt(
            $clave,
            30,
            fn () => $autenticacion->autenticar(
                $credenciales['username'],
                $credenciales['password']
            ) ?? 'credenciales_invalidas',
            60
        );

        if ($usuario === false) {
            return back()
                ->withErrors([
                    'username' => 'Demasiadas solicitudes. Intenta nuevamente en unos momentos.',
                ])
                ->withInput(
                    $request->only('username')
                );
        }

        if (!$usuario instanceof User) {
            return back()
                ->withErrors([
                    'username' => 'No fue posible iniciar sesión. Verifica tus credenciales o contacta al administrador.',
                ])
                ->withInput(
                    $request->only('username')
                );
        }

        Auth::guard('web')->login($usuario, false);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(
        Request $request
    ): RedirectResponse {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Sesión cerrada correctamente.'
            );
    }
}