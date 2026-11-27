<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\ActualizarCajaRequest;
use App\Http\Requests\Caja\CambiarEstadoCajaRequest;
use App\Http\Requests\Caja\CrearCajaRequest;
use App\Models\Caja;
use App\Services\Caja\CajaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CajaController extends Controller
{
    public function __construct(
        private readonly CajaService $servicio
    ) {}

    public function index(Request $request): View
    {
        $cajas = $this->servicio->listar(
            $request->user(),
            $request->query()
        );

        return view('caja.index', compact('cajas'));
    }

    public function create(Request $request): View
    {
        $this->servicio->autorizarAdministracion($request->user());

        return view('caja.create');
    }

    public function store(CrearCajaRequest $request): RedirectResponse
    {
        $caja = $this->servicio->crear(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('caja.show', $caja)
            ->with('status', 'Caja registrada correctamente.');
    }

    public function show(Request $request, Caja $caja): View
    {
        $caja = $this->servicio->obtener($caja, $request->user());

        return view('caja.show', compact('caja'));
    }

    public function edit(Request $request, Caja $caja): View
    {
        $this->servicio->autorizarAdministracion($request->user());

        $caja = $this->servicio->obtener($caja, $request->user());

        return view('caja.edit', compact('caja'));
    }

    public function update(
        ActualizarCajaRequest $request,
        Caja $caja
    ): RedirectResponse {
        $caja = $this->servicio->actualizar(
            $caja,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('caja.show', $caja)
            ->with('status', 'Caja actualizada correctamente.');
    }

    public function cambiarEstado(
        CambiarEstadoCajaRequest $request,
        Caja $caja
    ): RedirectResponse {
        $caja = $this->servicio->cambiarEstado(
            $caja,
            $request->validated('estado'),
            $request->user()
        );

        return redirect()
            ->route('caja.show', $caja)
            ->with('status', 'Estado de caja actualizado correctamente.');
    }
}
