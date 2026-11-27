<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Metal;
use App\Models\Pureza;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class CatalogoController extends Controller
{
    public function index(): View
    {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.ver'
        );

        $resumen = [
            'categorias' => [
                'total' => Categoria::query()->count(),

                'activas' => Categoria::query()
                    ->where('estado', 'activa')
                    ->count(),

                'inactivas' => Categoria::query()
                    ->where('estado', 'inactiva')
                    ->count(),
            ],

            'metales' => [
                'total' => Metal::query()->count(),

                'activos' => Metal::query()
                    ->where('estado', 'activo')
                    ->count(),

                'inactivos' => Metal::query()
                    ->where('estado', 'inactivo')
                    ->count(),
            ],

            'purezas' => [
                'total' => Pureza::query()->count(),

                'disponibles' => Pureza::query()
                    ->whereHas('metal', function ($consulta) {
                        $consulta->where('estado', 'activo');
                    })
                    ->count(),

                'no_disponibles' => Pureza::query()
                    ->whereHas('metal', function ($consulta) {
                        $consulta->where('estado', 'inactivo');
                    })
                    ->count(),
            ],
        ];

        return view('catalogos.index', [
            'resumen' => $resumen,
        ]);
    }
}