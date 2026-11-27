<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\ConsultaCatalogosRequest;
use App\Http\Requests\Catalogos\GuardarPurezaRequest;
use App\Models\Metal;
use App\Models\Pureza;
use App\Services\Catalogos\MetalService;
use App\Services\Catalogos\PurezaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PurezaController extends Controller
{
    public function __construct(
        private readonly PurezaService $purezaService,
        private readonly MetalService $metalService
    ) {
    }

    public function index(
        ConsultaCatalogosRequest $request
    ): View {
        $filtros = $request->validated();

        $purezas = $this->purezaService
            ->listar($filtros);

        $metales = Metal::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return view('catalogos.purezas.index', [
            'purezas' => $purezas,
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

        $pureza = new Pureza();

        $metalesDisponibles = $this->metalService
            ->obtenerActivos();

        return view('catalogos.purezas.create', [
            'pureza' => $pureza,
            'metalesDisponibles' => $metalesDisponibles,
        ]);
    }

    public function store(
        GuardarPurezaRequest $request
    ): RedirectResponse {
        $this->purezaService->crear(
            $request->validated()
        );

        return redirect()
            ->route('catalogos.purezas.index')
            ->with(
                'status',
                'La pureza se registró correctamente.'
            );
    }

    public function edit(
        Pureza $pureza
    ): View {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.editar'
        );

        $pureza->load('metal');

        $pureza->loadCount([
            'productos' => function ($consulta) {
                $consulta->withTrashed();
            },
            'piezas',
        ]);

        $tieneReferencias = $this->purezaService
            ->tieneReferencias($pureza);

        $metalesDisponibles = $this->metalService
            ->obtenerActivos();

        if (
            $pureza->metal !== null
            && ! $metalesDisponibles->contains(
                'id',
                $pureza->metal_id
            )
        ) {
            $metalesDisponibles->push(
                $pureza->metal
            );

            $metalesDisponibles = $metalesDisponibles
                ->sortBy('nombre')
                ->values();
        }

        return view('catalogos.purezas.edit', [
            'pureza' => $pureza,
            'metalesDisponibles' => $metalesDisponibles,
            'tieneReferencias' => $tieneReferencias,
        ]);
    }

    public function update(
        GuardarPurezaRequest $request,
        Pureza $pureza
    ): RedirectResponse {
        $this->purezaService->actualizar(
            $pureza,
            $request->validated()
        );

        return redirect()
            ->route('catalogos.purezas.index')
            ->with(
                'status',
                'La solicitud de actualización de la pureza se procesó correctamente.'
            );
    }
}