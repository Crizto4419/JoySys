<?php

namespace App\Http\Controllers\Precios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Precios\ActualizarPrecioRequest;
use App\Http\Requests\Precios\ConsultaPreciosRequest;
use App\Models\Producto;
use App\Services\Precios\PrecioService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PrecioController extends Controller
{
    public function __construct(
        private readonly PrecioService $precioService
    ) {
    }

    public function index(
        ConsultaPreciosRequest $request
    ): View {
        $filtros = $request->validated();

        return view('precios.index', [
            'productos' => $this->precioService->listar($filtros),
            'filtros' => $filtros,
        ]);
    }

    public function edit(Producto $producto): View
    {
        Gate::authorize(
            'joysys.permiso',
            'precios.modificar'
        );

        $producto->load('precioVigente');

        return view('precios.edit', [
            'producto' => $producto,
        ]);
    }

    public function update(
        ActualizarPrecioRequest $request,
        Producto $producto
    ): RedirectResponse {
        $this->precioService->actualizar(
            $producto,
            $request->validated('precio'),
            $request->user()
        );

        return redirect()
            ->route('precios.index')
            ->with('status', 'La solicitud de precio se proceso correctamente.');
    }

    public function historial(
        ConsultaPreciosRequest $request,
        Producto $producto
    ): View {
        return view('precios.historial', [
            'producto' => $producto,
            'historial' => $this->precioService->historial($producto),
        ]);
    }
}
