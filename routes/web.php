<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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
            DashboardController::class
        )
            ->middleware("can:joysys.permiso,'dashboard.ver'")
            ->name('dashboard');

    });

});

Route::middleware(['auth', 'usuario.activo'])
    ->prefix('catalogos')
    ->name('catalogos.')
    ->group(function () {

        Route::get('/', [\App\Http\Controllers\Catalogos\CatalogoController::class, 'index'])
            ->middleware("can:joysys.permiso,'catalogos.ver'")
            ->name('index');

        $modulos = [
            'categorias' => [
                \App\Http\Controllers\Catalogos\CategoriaController::class,
                'categoria',
                true,
            ],
            'metales' => [
                \App\Http\Controllers\Catalogos\MetalController::class,
                'metal',
                true,
            ],
            'purezas' => [
                \App\Http\Controllers\Catalogos\PurezaController::class,
                'pureza',
                false,
            ],
        ];

        foreach ($modulos as $nombre => [$controlador, $parametro, $tieneEstado]) {
            Route::prefix($nombre)->name($nombre.'.')->group(
                function () use ($controlador, $parametro, $tieneEstado) {

                    Route::get('/', [$controlador, 'index'])
                        ->middleware("can:joysys.permiso,'catalogos.ver'")
                        ->name('index');

                    Route::get('/crear', [$controlador, 'create'])
                        ->middleware("can:joysys.permiso,'catalogos.crear'")
                        ->name('create');

                    Route::post('/', [$controlador, 'store'])
                        ->middleware("can:joysys.permiso,'catalogos.crear'")
                        ->name('store');

                    Route::get('/{'.$parametro.'}/editar', [$controlador, 'edit'])
                        ->middleware("can:joysys.permiso,'catalogos.editar'")
                        ->name('edit');

                    Route::put('/{'.$parametro.'}', [$controlador, 'update'])
                        ->middleware("can:joysys.permiso,'catalogos.editar'")
                        ->name('update');

                    if ($tieneEstado) {
                        Route::patch('/{'.$parametro.'}/estado', [$controlador, 'cambiarEstado'])
                            ->middleware("can:joysys.permiso,'catalogos.cambiar_estado'")
                            ->name('estado');
                    }
                }
            );
        }
    });
