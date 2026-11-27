<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\MovimientoCaja;
use App\Services\Caja\MovimientoCajaService;
use App\Services\Caja\SesionCajaService;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Caja\OperacionCajaRequest;

class OperacionCajaController extends Controller
{
    public function __construct(
        private readonly MovimientoCajaService $movimientos,
        private readonly SesionCajaService $sesiones
    ) {}

    private function datos(OperacionCajaRequest $request): array
    {
        return $request->safe()->except(['sesion_id']);
    }

    public function gasto(OperacionCajaRequest $request): RedirectResponse
    {
        $this->movimientos->registrarGasto(
            $this->datos($request),
            $request->user(),
            (int) $request->validated('sesion_id')
        );

        return redirect()
            ->route('caja.sesion.actual')
            ->with('status', 'Gasto registrado correctamente.');
    }

    public function retiro(OperacionCajaRequest $request): RedirectResponse
    {
        $this->movimientos->registrarRetiro(
            $this->datos($request),
            $request->user(),
            (int) $request->validated('sesion_id')
        );

        return redirect()
            ->route('caja.sesion.actual')
            ->with('status', 'Retiro registrado correctamente.');
    }

    public function compensacion(
        OperacionCajaRequest $request,
        MovimientoCaja $movimiento
    ): RedirectResponse {
        $this->movimientos->registrarCompensacion(
            $movimiento,
            $this->datos($request),
            $request->user()
        );

        return redirect()
            ->route('caja.sesion.actual')
            ->with('status', 'Compensacion registrada correctamente.');
    }

    public function cerrar(
        OperacionCajaRequest $request,
        int $sesion
    ): RedirectResponse {
        $cerrada = $this->sesiones->cerrarIdentificada(
            $sesion,
            $this->datos($request),
            $request->user()
        );

        return redirect()
            ->route('caja.sesion.detalle', $cerrada->id)
            ->with('status', 'Sesion de caja cerrada correctamente.');
    }
}