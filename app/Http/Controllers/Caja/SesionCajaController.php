<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\AbrirSesionCajaRequest;
use App\Services\Caja\CajaService;
use App\Services\Caja\SesionCajaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SesionCajaController extends Controller
{
    public function __construct(
        private readonly CajaService $cajas,
        private readonly SesionCajaService $sesiones
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $usuario = $request->user();

        $this->cajas->autorizar($usuario, 'caja.abrir');

        if ($this->sesiones->obtenerAbierta($usuario) !== null) {
            return redirect()
                ->route('caja.sesion.actual')
                ->with('status', 'Ya tienes una sesión de caja abierta.');
        }

        $cajas = $this->cajas->disponibles($usuario);

        return view('caja.sesiones.create', compact('cajas'));
    }

    public function store(
        AbrirSesionCajaRequest $request
    ): RedirectResponse {
        $this->sesiones->abrir(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('caja.sesion.actual')
            ->with('status', 'Sesión de caja abierta correctamente.');
    }

    public function actual(Request $request): View
    {
        $sesion = $this->sesiones->obtenerAbierta(
            $request->user()
        );

        return view('caja.sesiones.actual', compact('sesion'));
    }
}
