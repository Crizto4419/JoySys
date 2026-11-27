<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\CambiarEstadoMetalRequest;
use App\Http\Requests\Catalogos\ConsultaCatalogosRequest;
use App\Http\Requests\Catalogos\GuardarMetalRequest;
use App\Models\Metal;
use App\Services\Catalogos\MetalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MetalController extends Controller
{
    public function __construct(
        private readonly MetalService $metalService
    ) {
    }

    public function index(
        ConsultaCatalogosRequest $request
    ): View {
        $filtros = $request->validated();

        $metales = $this->metalService
            ->listar($filtros);

        return view('catalogos.metales.index', [
            'metales' => $metales,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.crear'
        );

        $metal = new Metal();

        return view('catalogos.metales.create', [
            'metal' => $metal,
        ]);
    }

    public function store(
        GuardarMetalRequest $request
    ): RedirectResponse {
        $this->metalService->crear(
            $request->validated()
        );

        return redirect()
            ->route('catalogos.metales.index')
            ->with(
                'status',
                'El metal se registró correctamente.'
            );
    }

    public function edit(
        Metal $metal
    ): View {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.editar'
        );

        $metal->loadCount([
            'purezas',
            'productos',
            'piezas',
        ]);

        return view('catalogos.metales.edit', [
            'metal' => $metal,
        ]);
    }

    public function update(
        GuardarMetalRequest $request,
        Metal $metal
    ): RedirectResponse {
        $this->metalService->actualizar(
            $metal,
            $request->validated()
        );

        return redirect()
            ->route('catalogos.metales.index')
            ->with(
                'status',
                'El metal se actualizó correctamente.'
            );
    }

    public function cambiarEstado(
        CambiarEstadoMetalRequest $request,
        Metal $metal
    ): RedirectResponse {
        $registro = $this->metalService
            ->cambiarEstado(
                $metal,
                $request->validated('estado')
            );

        $mensaje = $registro->estado === 'activo'
            ? 'El metal está activo.'
            : 'El metal está inactivo.';

        return redirect()
            ->route('catalogos.metales.index')
            ->with('status', $mensaje);
    }
}