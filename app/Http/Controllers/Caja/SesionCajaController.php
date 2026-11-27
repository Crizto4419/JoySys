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

        $movimientos = $sesion === null
            ? null
            : app(\App\Services\Caja\MovimientoCajaService::class)
                ->listarMovimientos(
                    $sesion,
                    $request->user(),
                    $request->only(['tipo_movimiento', 'page'])
                );

        return view(
            'caja.sesiones.actual',
            compact('sesion', 'movimientos')
        );
    }


    public function historial(Request $request): View
    {
        $sucursalId = $this->cajas->autorizar(
            $request->user(),
            'caja.ver'
        );

        $filtros = $request->validate([
            'estado' => [
                'nullable',
                \Illuminate\Validation\Rule::in([
                    'abierta', 'cerrada',
                ]),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $consulta = \App\Models\SesionCaja::query()
            ->where('sucursal_id', $sucursalId)
            ->where('usuario_id', $request->user()->getKey())
            ->with('caja');

        if (!empty($filtros['estado'])) {
            $consulta->where('estado', $filtros['estado']);
        }

        $sesiones = $consulta
            ->orderByDesc('fecha_apertura')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view(
            'caja.sesiones.historial',
            compact('sesiones')
        );
    }

    public function detalle(
        Request $request,
        int $sesion
    ): View {
        $sucursalId = $this->cajas->autorizar(
            $request->user(),
            'caja.ver'
        );

        $sesion = \App\Models\SesionCaja::query()
            ->where('sucursal_id', $sucursalId)
            ->where('usuario_id', $request->user()->getKey())
            ->with(['caja', 'usuario', 'cerradaPor'])
            ->findOrFail($sesion);

        $movimientos = app(
            \App\Services\Caja\MovimientoCajaService::class
        )->listarMovimientos(
            $sesion,
            $request->user(),
            $request->only(['tipo_movimiento', 'page'])
        );

        return view(
            'caja.sesiones.detalle',
            compact('sesion', 'movimientos')
        );
    }

}
