<?php

namespace App\Services\Inventario;

use App\Models\IncidenciaInventario;
use App\Models\Producto;
use App\Models\User;
use App\Services\Auth\ContextoSucursalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IncidenciaService
{
    public const TIPOS = [
        'faltante' => 'Mercancia faltante',
        'sobrante' => 'Mercancia excedente',
        'danado' => 'Mercancia danada',
        'perdido' => 'Mercancia perdida',
        'diferencia_peso' => 'Diferencia de peso',
        'error_registro' => 'Error de registro',
        'otro' => 'Otra incidencia',
    ];

    public const TRANSICIONES = [
        'abierta' => ['en_revision'],
        'en_revision' => ['resuelta'],
        'resuelta' => [],
    ];

    public function __construct(
        private readonly ContextoSucursalService $contextoSucursal
    ) {
    }

    private function autorizar(
        User $usuario,
        string $permiso
    ): int {
        Gate::forUser($usuario)->authorize(
            'joysys.permiso',
            $permiso
        );

        $sucursalId = $this->contextoSucursal
            ->obtenerSucursalId($usuario);

        abort_if($sucursalId === null, 403);

        return $sucursalId;
    }

    public function listar(User $usuario, array $filtros = [])
    {
        $sucursalId = $this->autorizar(
            $usuario,
            'incidencias.ver'
        );

        $consulta = IncidenciaInventario::query()
            ->where('sucursal_id', $sucursalId)
            ->with([
                'producto' => fn ($q) => $q->withTrashed(),
                'usuarioReporta' => fn ($q) => $q->withTrashed(),
            ]);

        FiltrosInventario::registros(
            $consulta,
            $filtros,
            'incidencias'
        );

        return $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function reportar(
        Producto $producto,
        array $datos,
        User $usuario
    ): IncidenciaInventario {
        $sucursalId = $this->autorizar(
            $usuario,
            'incidencias.reportar'
        );

        $tipo = $datos['tipo_incidencia'] ?? null;
        $descripcion = $datos['descripcion'] ?? null;

        if (
            !is_string($tipo) ||
            !array_key_exists($tipo, self::TIPOS)
        ) {
            throw ValidationException::withMessages([
                'tipo_incidencia' => 'Tipo de incidencia invalido.',
            ]);
        }

        if (
            !is_string($descripcion) ||
            trim($descripcion) === '' ||
            strlen($descripcion) > 65535
        ) {
            throw ValidationException::withMessages([
                'descripcion' => 'Captura una descripcion valida.',
            ]);
        }

        return DB::transaction(
            function () use (
                $producto,
                $usuario,
                $sucursalId,
                $tipo,
                $descripcion
            ) {
                abort_unless(
                    $this->autorizar(
                        $usuario,
                        'incidencias.reportar'
                    ) === $sucursalId,
                    403
                );

                $actual = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                return IncidenciaInventario::create([
                    'producto_id' => $actual->id,
                    'sucursal_id' => $sucursalId,
                    'usuario_reporta_id' => $usuario->id,
                    'tipo_incidencia' => $tipo,
                    'descripcion' => trim($descripcion),
                    'estado' => 'abierta',
                ]);
            },
            1
        );
    }

    public function cambiarEstado(
        IncidenciaInventario $incidencia,
        string $destino,
        User $usuario
    ): IncidenciaInventario {
        $sucursalId = $this->autorizar(
            $usuario,
            'incidencias.resolver'
        );

        return DB::transaction(
            function () use (
                $incidencia,
                $destino,
                $usuario,
                $sucursalId
            ) {
                abort_unless(
                    $this->autorizar(
                        $usuario,
                        'incidencias.resolver'
                    ) === $sucursalId,
                    403
                );

                $actual = IncidenciaInventario::query()
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->findOrFail($incidencia->id);

                if (!in_array(
                    $destino,
                    self::TRANSICIONES[$actual->estado] ?? [],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'estado' => 'La transicion no esta permitida.',
                    ]);
                }

                $actual->update([
                    'estado' => $destino,
                ]);

                return $actual->refresh();
            },
            1
        );
    }
}
