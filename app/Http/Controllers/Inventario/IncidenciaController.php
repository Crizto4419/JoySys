<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\ConsultaInventarioRequest;
use App\Http\Requests\Inventario\ReportarIncidenciaRequest;
use App\Http\Requests\Inventario\CambiarEstadoIncidenciaRequest;
use App\Models\Producto;
use App\Models\IncidenciaInventario;
use App\Services\Inventario\IncidenciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncidenciaController extends Controller
{
    public function __construct(
        private readonly IncidenciaService $incidencias
    ) {
    }

    public function index(ConsultaInventarioRequest $request)
    {
        return view('incidencias.index', [
            'incidencias' => $this->incidencias
                ->listar($request->user(), $request->validated()),
            'tipos' => IncidenciaService::TIPOS,
        ]);
    }

    public function create(Request $request)
    {
        Gate::forUser($request->user())->authorize(
            'joysys.permiso',
            'incidencias.reportar'
        );

        return view('incidencias.create', [
            'productos' => Producto::query()
                ->orderBy('nombre')
                ->orderBy('id')
                ->get(),
            'tipos' => IncidenciaService::TIPOS,
        ]);
    }

    public function store(
        ReportarIncidenciaRequest $request
    ) {
        $datos = $request->validated();

        $producto = Producto::findOrFail(
            $datos['producto_id']
        );

        $this->incidencias->reportar(
            $producto,
            $datos,
            $request->user()
        );

        return redirect()
            ->route('incidencias.index')
            ->with(
                'status',
                'Incidencia reportada correctamente.'
            );
    }

    public function cambiarEstado(
        CambiarEstadoIncidenciaRequest $request,
        IncidenciaInventario $incidencia
    ) {
        $this->incidencias->cambiarEstado(
            $incidencia,
            $request->validated('estado'),
            $request->user()
        );

        return redirect()
            ->route('incidencias.index')
            ->with(
                'status',
                'Estado de incidencia actualizado.'
            );
    }
}
