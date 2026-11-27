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
            'productos' => [
                \App\Http\Controllers\Catalogos\ProductoController::class,
                'producto',
                true,
            ],
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


Route::middleware(['auth', 'usuario.activo'])
    ->prefix('precios')
    ->name('precios.')
    ->group(function () {

        Route::get('/', [
            \App\Http\Controllers\Precios\PrecioController::class,
            'index',
        ])
            ->middleware("can:joysys.permiso,'precios.ver'")
            ->name('index');

        Route::get('/{producto}/editar', [
            \App\Http\Controllers\Precios\PrecioController::class,
            'edit',
        ])
            ->middleware("can:joysys.permiso,'precios.modificar'")
            ->name('edit');

        Route::put('/{producto}', [
            \App\Http\Controllers\Precios\PrecioController::class,
            'update',
        ])
            ->middleware("can:joysys.permiso,'precios.modificar'")
            ->name('update');

        Route::get('/{producto}/historial', [
            \App\Http\Controllers\Precios\PrecioController::class,
            'historial',
        ])
            ->middleware("can:joysys.permiso,'precios.ver_historial'")
            ->name('historial');
    });


Route::middleware([
    'auth',
    'usuario.activo',
    'sucursal.activa',
])->prefix('inventario')->name('inventario.')->group(function () {
    $controlador = \App\Http\Controllers\Inventario\InventarioController::class;

    Route::get('/', [$controlador, 'index'])
        ->middleware("can:joysys.permiso,'inventario.ver'")
        ->name('index');

    Route::get('/piezas', [$controlador, 'piezas'])
        ->middleware("can:joysys.permiso,'inventario.ver'")
        ->name('piezas');

    Route::get('/existencias', [$controlador, 'existencias'])
        ->middleware("can:joysys.permiso,'inventario.ver'")
        ->name('existencias');

    Route::get('/movimientos', [$controlador, 'movimientos'])
        ->middleware("can:joysys.permiso,'inventario.ver'")
        ->name('movimientos');

    Route::get('/entradas', [$controlador, 'entrada'])
        ->middleware("can:joysys.permiso,'inventario.registrar_entrada'")
        ->name('entradas.create');

    Route::post('/entradas', [$controlador, 'registrarEntrada'])
        ->middleware("can:joysys.permiso,'inventario.registrar_entrada'")
        ->name('entradas.store');

    Route::get('/salidas', [$controlador, 'salida'])
        ->middleware("can:joysys.permiso,'inventario.registrar_salida'")
        ->name('salidas.create');

    Route::post('/salidas', [$controlador, 'registrarSalida'])
        ->middleware("can:joysys.permiso,'inventario.registrar_salida'")
        ->name('salidas.store');

    Route::get('/ajustes', [$controlador, 'ajuste'])
        ->middleware("can:joysys.permiso,'inventario.ajustar'")
        ->name('ajustes.create');

    Route::post('/ajustes', [$controlador, 'registrarAjuste'])
        ->middleware("can:joysys.permiso,'inventario.ajustar'")
        ->name('ajustes.store');
});

Route::middleware([
    'auth',
    'usuario.activo',
    'sucursal.activa',
])->prefix('incidencias')->name('incidencias.')->group(function () {
    $controlador = \App\Http\Controllers\Inventario\IncidenciaController::class;

    Route::get('/', [$controlador, 'index'])
        ->middleware("can:joysys.permiso,'incidencias.ver'")
        ->name('index');

    Route::get('/crear', [$controlador, 'create'])
        ->middleware("can:joysys.permiso,'incidencias.reportar'")
        ->name('create');

    Route::post('/', [$controlador, 'store'])
        ->middleware("can:joysys.permiso,'incidencias.reportar'")
        ->name('store');

    Route::patch('/{incidencia}/estado', [$controlador, 'cambiarEstado'])
        ->middleware("can:joysys.permiso,'incidencias.resolver'")
        ->name('estado');
});

Route::middleware(['auth', 'usuario.activo'])
    ->prefix('clientes')
    ->name('clientes.')
    ->group(function () {
        Route::get(
            '/',
            [\App\Http\Controllers\Clientes\ClienteController::class, 'index']
        )
            ->middleware("can:joysys.permiso,'clientes.ver'")
            ->name('index');

        Route::get(
            '/crear',
            [\App\Http\Controllers\Clientes\ClienteController::class, 'create']
        )
            ->middleware("can:joysys.permiso,'clientes.crear'")
            ->name('create');

        Route::post(
            '/',
            [\App\Http\Controllers\Clientes\ClienteController::class, 'store']
        )
            ->middleware("can:joysys.permiso,'clientes.crear'")
            ->name('store');

        Route::get(
            '/{cliente}',
            [\App\Http\Controllers\Clientes\ClienteController::class, 'show']
        )
            ->whereNumber('cliente')
            ->middleware("can:joysys.permiso,'clientes.ver'")
            ->name('show');

        Route::get(
            '/{cliente}/editar',
            [\App\Http\Controllers\Clientes\ClienteController::class, 'edit']
        )
            ->whereNumber('cliente')
            ->middleware("can:joysys.permiso,'clientes.editar'")
            ->name('edit');

        Route::put(
            '/{cliente}',
            [\App\Http\Controllers\Clientes\ClienteController::class, 'update']
        )
            ->whereNumber('cliente')
            ->middleware("can:joysys.permiso,'clientes.editar'")
            ->name('update');

        Route::patch(
            '/{cliente}/estado',
            [\App\Http\Controllers\Clientes\ClienteController::class, 'cambiarEstado']
        )
            ->whereNumber('cliente')
            ->middleware("can:joysys.permiso,'clientes.desactivar'")
            ->name('estado');
    });


Route::middleware([
    'auth',
    'usuario.activo',
    'sucursal.activa',
])->prefix('caja')->name('caja.')->group(function () {
    Route::get(
        '/',
        [\App\Http\Controllers\Caja\CajaController::class, 'index']
    )->middleware("can:joysys.permiso,'caja.ver'")->name('index');

    Route::get(
        '/crear',
        [\App\Http\Controllers\Caja\CajaController::class, 'create']
    )->middleware('can:joysys.caja.administrar')->name('create');

    Route::post(
        '/',
        [\App\Http\Controllers\Caja\CajaController::class, 'store']
    )->middleware('can:joysys.caja.administrar')->name('store');

    Route::get(
        '/sesiones/abrir',
        [\App\Http\Controllers\Caja\SesionCajaController::class, 'create']
    )->middleware("can:joysys.permiso,'caja.abrir'")->name('sesion.create');

    Route::post(
        '/sesiones',
        [\App\Http\Controllers\Caja\SesionCajaController::class, 'store']
    )->middleware("can:joysys.permiso,'caja.abrir'")->name('sesion.store');

    Route::get(
        '/sesiones/actual',
        [\App\Http\Controllers\Caja\SesionCajaController::class, 'actual']
    )->middleware("can:joysys.permiso,'caja.ver'")->name('sesion.actual');

    Route::get(
        '/{caja}',
        [\App\Http\Controllers\Caja\CajaController::class, 'show']
    )->whereNumber('caja')
        ->middleware("can:joysys.permiso,'caja.ver'")
        ->name('show');

    Route::get(
        '/{caja}/editar',
        [\App\Http\Controllers\Caja\CajaController::class, 'edit']
    )->whereNumber('caja')
        ->middleware('can:joysys.caja.administrar')
        ->name('edit');

    Route::put(
        '/{caja}',
        [\App\Http\Controllers\Caja\CajaController::class, 'update']
    )->whereNumber('caja')
        ->middleware('can:joysys.caja.administrar')
        ->name('update');

    Route::patch(
        '/{caja}/estado',
        [\App\Http\Controllers\Caja\CajaController::class, 'cambiarEstado']
    )->whereNumber('caja')
        ->middleware('can:joysys.caja.administrar')
        ->name('estado');
});
