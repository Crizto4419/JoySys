<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\ConsultaInventarioRequest;
use App\Http\Requests\Inventario\OperacionInventarioRequest;
use App\Models\Producto;
use App\Models\Pieza;
use App\Services\Inventario\InventarioService;
use App\Services\Auth\ContextoSucursalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventarioController extends Controller
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly ContextoSucursalService $contexto
    ) {
    }

    public function index(ConsultaInventarioRequest $request)
    {
        return view('inventario.index', [
            'productos' => $this->inventario
                ->listarProductos($request->user(), $request->validated()),
        ]);
    }

    public function piezas(ConsultaInventarioRequest $request)
    {
        return view('inventario.piezas', [
            'piezas' => $this->inventario
                ->listarPiezas($request->user(), $request->validated()),
        ]);
    }

    public function existencias(ConsultaInventarioRequest $request)
    {
        return view('inventario.existencias', [
            'existencias' => $this->inventario
                ->listarExistencias($request->user(), $request->validated()),
        ]);
    }

    public function movimientos(ConsultaInventarioRequest $request)
    {
        return view('inventario.movimientos', [
            'movimientos' => $this->inventario
                ->listarMovimientos($request->user(), $request->validated()),
        ]);
    }

    private function formulario(
        Request $request,
        string $operacion,
        string $permiso
    ) {
        Gate::forUser($request->user())->authorize(
            'joysys.permiso',
            $permiso
        );

        $sucursalId = $this->contexto
            ->obtenerSucursalId($request->user());

        abort_if($sucursalId === null, 403);

        $productos = Producto::query()
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        $piezas = $operacion === 'entradas'
            ? collect()
            : Pieza::query()
                ->where('sucursal_id', $sucursalId)
                ->whereHas('producto', fn ($q) =>
                    $q->where('estado', 'activo')
                )
                ->whereHas('estadoInventario', fn ($q) =>
                    $q->whereIn(
                        'codigo',
                        $operacion === 'salidas'
                            ? [
                                'disponible',
                                'reparacion',
                                'danado',
                            ]
                            : [
                                'disponible',
                                'reparacion',
                                'danado',
                                'perdido',
                            ]
                    )
                )
                ->with(['producto', 'estadoInventario'])
                ->orderBy('codigo_pieza')
                ->get();

        return view('inventario.operacion', [
            'operacion' => $operacion,
            'productos' => $productos,
            'piezas' => $piezas,
            'transiciones' => InventarioService::TRANSICIONES,
        ]);
    }

    public function entrada(Request $request)
    {
        return $this->formulario(
            $request,
            'entradas',
            'inventario.registrar_entrada'
        );
    }

    public function salida(Request $request)
    {
        return $this->formulario(
            $request,
            'salidas',
            'inventario.registrar_salida'
        );
    }

    public function ajuste(Request $request)
    {
        return $this->formulario(
            $request,
            'ajustes',
            'inventario.ajustar'
        );
    }

    private function error(
        string $campo,
        string $mensaje
    ): never {
        throw ValidationException::withMessages([
            $campo => $mensaje,
        ]);
    }

    private function obtenerPieza(
        OperacionInventarioRequest $request,
        Producto $producto,
        array $datos
    ): Pieza {
        if (empty($datos['pieza_id'])) {
            $this->error(
                'pieza_id',
                'Selecciona una pieza.'
            );
        }

        $sucursalId = $this->contexto
            ->obtenerSucursalId($request->user());

        abort_if($sucursalId === null, 403);

        return Pieza::query()
            ->where('sucursal_id', $sucursalId)
            ->where('producto_id', $producto->id)
            ->findOrFail($datos['pieza_id']);
    }

    private function comprobarCamposVacios(
        array $datos,
        array $campos
    ): void {
        foreach ($campos as $campo) {
            if (
                isset($datos[$campo]) &&
                $datos[$campo] !== ''
            ) {
                $this->error(
                    $campo,
                    'Este campo no corresponde a la operacion.'
                );
            }
        }
    }

    public function registrarEntrada(
        OperacionInventarioRequest $request
    ) {
        $datos = $request->validated();

        $producto = Producto::findOrFail(
            $datos['producto_id']
        );

        if ($producto->tipo === 'individual') {
            $this->comprobarCamposVacios(
                $datos,
                ['cantidad']
            );

            $this->inventario->registrarPieza(
                $producto,
                $datos,
                $request->user()
            );
        } else {
            $this->comprobarCamposVacios(
                $datos,
                ['codigo_pieza', 'peso_gramos']
            );

            $this->inventario->registrarEntrada(
                $producto,
                $datos['cantidad'] ?? null,
                $request->user(),
                $datos['observaciones'] ?? null
            );
        }

        return redirect()
            ->route('inventario.index')
            ->with(
                'status',
                'Entrada registrada correctamente.'
            );
    }

    public function registrarSalida(
        OperacionInventarioRequest $request
    ) {
        $datos = $request->validated();

        $producto = Producto::findOrFail(
            $datos['producto_id']
        );

        if ($datos['tipo_salida'] === 'administrativa') {
            $this->comprobarCamposVacios(
                $datos,
                ['pieza_id', 'estado_origen']
            );

            $this->inventario->registrarSalida(
                $producto,
                $datos['cantidad'] ?? null,
                $request->user(),
                $datos['observaciones']
            );

            return redirect()
                ->route('inventario.index')
                ->with(
                    'status',
                    'Salida administrativa registrada correctamente.'
                );
        }

        if ($producto->tipo === 'individual') {
            $this->comprobarCamposVacios(
                $datos,
                ['cantidad', 'estado_origen']
            );

            $pieza = $this->obtenerPieza(
                $request,
                $producto,
                $datos
            );

            $this->inventario->cambiarEstadoPieza(
                $pieza,
                'perdido',
                $request->user(),
                $datos['observaciones']
            );
        } else {
            $this->comprobarCamposVacios(
                $datos,
                ['pieza_id']
            );

            $this->inventario->cambiarEstadoExistencia(
                $producto,
                $datos['cantidad'] ?? null,
                $datos['estado_origen'] ?? '',
                'perdido',
                $request->user(),
                $datos['observaciones']
            );
        }

        return redirect()
            ->route('inventario.index')
            ->with(
                'status',
                'Perdida registrada correctamente.'
            );
    }

    public function registrarAjuste(
        OperacionInventarioRequest $request
    ) {
        $datos = $request->validated();

        $producto = Producto::findOrFail(
            $datos['producto_id']
        );

        if ($datos['operacion'] === 'cantidad') {
            $this->comprobarCamposVacios(
                $datos,
                [
                    'pieza_id',
                    'estado_origen',
                    'estado_destino',
                ]
            );

            $this->inventario->ajustarExistencia(
                $producto,
                $datos['cantidad'] ?? null,
                $datos['sentido'] ?? '',
                $request->user(),
                $datos['observaciones']
            );
        } elseif ($producto->tipo === 'individual') {
            $this->comprobarCamposVacios(
                $datos,
                [
                    'cantidad',
                    'sentido',
                    'estado_origen',
                ]
            );

            $pieza = $this->obtenerPieza(
                $request,
                $producto,
                $datos
            );

            $this->inventario->cambiarEstadoPieza(
                $pieza,
                $datos['estado_destino'] ?? '',
                $request->user(),
                $datos['observaciones']
            );
        } else {
            $this->comprobarCamposVacios(
                $datos,
                ['pieza_id', 'sentido']
            );

            $this->inventario->cambiarEstadoExistencia(
                $producto,
                $datos['cantidad'] ?? null,
                $datos['estado_origen'] ?? '',
                $datos['estado_destino'] ?? '',
                $request->user(),
                $datos['observaciones']
            );
        }

        return redirect()
            ->route('inventario.index')
            ->with(
                'status',
                'Ajuste registrado correctamente.'
            );
    }
}
