<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\ActualizarClienteRequest;
use App\Http\Requests\Clientes\CambiarEstadoClienteRequest;
use App\Http\Requests\Clientes\ConsultaClientesRequest;
use App\Http\Requests\Clientes\CrearClienteRequest;
use App\Models\Cliente;
use App\Services\Clientes\ClienteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteService $clientes
    ) {
    }

    public function index(
        ConsultaClientesRequest $request
    ): View {
        return view('clientes.index', [
            'clientes' => $this->clientes->listar(
                $request->user(),
                $request->validated()
            ),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::forUser($request->user())->authorize(
            'joysys.permiso',
            'clientes.crear'
        );

        return view('clientes.create');
    }

    public function store(
        CrearClienteRequest $request
    ): RedirectResponse {
        $cliente = $this->clientes->crear(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('clientes.show', $cliente)
            ->with(
                'status',
                'Cliente registrado correctamente.'
            );
    }

    public function show(
        Request $request,
        Cliente $cliente
    ): View {
        return view('clientes.show', [
            'cliente' => $this->clientes->obtener(
                $cliente,
                $request->user()
            ),
        ]);
    }

    public function edit(
        Request $request,
        Cliente $cliente
    ): View {
        Gate::forUser($request->user())->authorize(
            'joysys.permiso',
            'clientes.editar'
        );

        return view('clientes.edit', [
            'cliente' => $cliente,
        ]);
    }

    public function update(
        ActualizarClienteRequest $request,
        Cliente $cliente
    ): RedirectResponse {
        $registro = $this->clientes->actualizar(
            $cliente,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('clientes.show', $registro)
            ->with(
                'status',
                'Cliente actualizado correctamente.'
            );
    }

    public function cambiarEstado(
        CambiarEstadoClienteRequest $request,
        Cliente $cliente
    ): RedirectResponse {
        $registro = $this->clientes->cambiarEstado(
            $cliente,
            $request->validated('estado'),
            $request->user()
        );

        $mensaje = $registro->activo
            ? 'Cliente activado correctamente.'
            : 'Cliente desactivado correctamente.';

        return redirect()
            ->route('clientes.show', $registro)
            ->with('status', $mensaje);
    }
}
