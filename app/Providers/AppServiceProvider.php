<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Venta;
use App\Services\Auth\AutorizacionService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'venta' => Venta::class,
        ]);

        Gate::define(
            'joysys.permiso',
            function (User $usuario, string $clave): bool {
                return app(AutorizacionService::class)
                    ->tienePermiso($usuario, $clave);
            }
        );

        Gate::define(
            'joysys.caja.administrar',
            function (User $usuario): bool {
                if (! app(AutorizacionService::class)
                    ->tienePermiso($usuario, 'caja.ver')) {
                    return false;
                }

                $administrador = User::query()
                    ->whereKey($usuario->getKey())
                    ->whereHas('role', function ($consulta) {
                        $consulta
                            ->where('roles.slug', 'administrador')
                            ->where('roles.estado', 'activo');
                    })
                    ->exists();

                return $administrador
                    && app(\App\Services\Auth\ContextoSucursalService::class)
                        ->obtenerSucursalId($usuario) !== null;
            }
        );
    }
}
