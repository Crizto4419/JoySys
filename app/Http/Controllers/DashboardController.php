<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboardService
    ): View {
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        $resumen = $dashboardService
            ->obtenerResumen($usuario);

        return view('dashboard', [
            'sucursalOperativa' =>
                $resumen['sucursal_operativa'],
            'resumen' => $resumen,
        ]);
    }
}