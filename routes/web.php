<?php

use App\Http\Controllers\Auth\LoginController;
use App\Services\Auth\ContextoSucursalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get(
        '/login',
        [LoginController::class, 'create']
    )->name('login');

    Route::post(
        '/login',
        [LoginController::class, 'store']
    )->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post(
        '/logout',
        [LoginController::class, 'destroy']
    )->name('logout');

    Route::middleware('usuario.activo')->group(function () {
        Route::get(
            '/dashboard',
            function (
                Request $request,
                ContextoSucursalService $contextoSucursal
            ) {
                $sucursalOperativa = $contextoSucursal
                    ->obtenerSucursal($request->user());

                return view('dashboard', [
                    'sucursalOperativa' => $sucursalOperativa,
                ]);
            }
        )
            ->middleware("can:joysys.permiso,'dashboard.ver'")
            ->name('dashboard');
    });
});