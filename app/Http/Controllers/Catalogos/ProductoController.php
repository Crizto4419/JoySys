<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\GuardarProductoRequest;
use App\Http\Requests\Catalogos\CambiarEstadoProductoRequest;
use App\Http\Requests\Catalogos\ConsultaProductosRequest;
use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Metal;
use App\Services\Catalogos\ProductoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProductoController extends Controller
{
    public function __construct(
        private readonly ProductoService $productoService
    ) {
    }

    public function index(
        ConsultaProductosRequest $request
    ): View {
        $filtros = $request->validated();

        return view('catalogos.productos.index', [
            'productos' => $this->productoService->listar($filtros),
            'categorias' => Categoria::query()
                ->orderBy('nombre')
                ->get(),
            'metales' => Metal::query()
                ->orderBy('nombre')
                ->get(),
            'filtros' => $filtros,
            'tipos' => [
                'individual' => 'Por pieza individual',
                'por_peso' => 'Por peso',
                'por_cantidad' => 'Por cantidad',
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.crear'
        );

        return view('catalogos.productos.create', array_merge(
            $this->productoService->opciones(),
            [
                'producto' => new Producto(),
                'identidadProtegida' => false,
            ]
        ));
    }

    public function store(
        GuardarProductoRequest $request
    ): RedirectResponse {
        $this->productoService->crear(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('catalogos.productos.index')
            ->with('status', 'El producto se registro correctamente.');
    }

    public function edit(Producto $producto): View
    {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.editar'
        );

        return view('catalogos.productos.edit', array_merge(
            $this->productoService->opciones($producto),
            [
                'producto' => $producto,
                'identidadProtegida' => $this->productoService
                    ->tieneReferencias($producto),
            ]
        ));
    }

    public function update(
        GuardarProductoRequest $request,
        Producto $producto
    ): RedirectResponse {
        $this->productoService->actualizar(
            $producto,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('catalogos.productos.index')
            ->with('status', 'El producto se actualizo correctamente.');
    }

    public function cambiarEstado(
        CambiarEstadoProductoRequest $request,
        Producto $producto
    ): RedirectResponse {
        $this->productoService->cambiarEstado(
            $producto,
            $request->validated('estado'),
            $request->user()
        );

        return redirect()
            ->route('catalogos.productos.index')
            ->with('status', 'El estado del producto se actualizo correctamente.');
    }
}
