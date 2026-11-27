<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\CambiarEstadoCategoriaRequest;
use App\Http\Requests\Catalogos\ConsultaCatalogosRequest;
use App\Http\Requests\Catalogos\GuardarCategoriaRequest;
use App\Models\Categoria;
use App\Services\Catalogos\CategoriaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CategoriaController extends Controller
{
    public function __construct(
        private readonly CategoriaService $categoriaService
    ) {
    }

    public function index(
        ConsultaCatalogosRequest $request
    ): View {
        $filtros = $request->validated();

        $categorias = $this->categoriaService
            ->listar($filtros);

        $categoriasPadre = Categoria::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return view('catalogos.categorias.index', [
            'categorias' => $categorias,
            'categoriasPadre' => $categoriasPadre,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.crear'
        );

        $categoria = new Categoria();

        $padresDisponibles = $this->categoriaService
            ->obtenerPadresDisponibles();

        return view('catalogos.categorias.create', [
            'categoria' => $categoria,
            'padresDisponibles' => $padresDisponibles,
        ]);
    }

    public function store(
        GuardarCategoriaRequest $request
    ): RedirectResponse {
        $this->categoriaService->crear(
            $request->validated()
        );

        return redirect()
            ->route('catalogos.categorias.index')
            ->with(
                'status',
                'La categoría se registró correctamente.'
            );
    }

    public function edit(
        Categoria $categoria
    ): View {
        Gate::authorize(
            'joysys.permiso',
            'catalogos.editar'
        );

        $categoria->load('categoriaPadre');

        $padresDisponibles = $this->categoriaService
            ->obtenerPadresDisponibles($categoria);

        return view('catalogos.categorias.edit', [
            'categoria' => $categoria,
            'padresDisponibles' => $padresDisponibles,
        ]);
    }

    public function update(
        GuardarCategoriaRequest $request,
        Categoria $categoria
    ): RedirectResponse {
        $this->categoriaService->actualizar(
            $categoria,
            $request->validated()
        );

        return redirect()
            ->route('catalogos.categorias.index')
            ->with(
                'status',
                'La categoría se actualizó correctamente.'
            );
    }

    public function cambiarEstado(
        CambiarEstadoCategoriaRequest $request,
        Categoria $categoria
    ): RedirectResponse {
        $registro = $this->categoriaService
            ->cambiarEstado(
                $categoria,
                $request->validated('estado')
            );

        $mensaje = $registro->estado === 'activa'
            ? 'La categoría está activa.'
            : 'La categoría está inactiva.';

        return redirect()
            ->route('catalogos.categorias.index')
            ->with('status', $mensaje);
    }
}