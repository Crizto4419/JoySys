<?php

namespace App\Services\Catalogos;

use App\Models\Existencia;
use App\Models\Metal;
use App\Models\Pieza;
use App\Models\Producto;
use App\Models\Pureza;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetalService
{
    private const REGISTROS_POR_PAGINA = 15;

    private const ESTADO_ACTIVO = 'activo';

    private const ESTADO_INACTIVO = 'inactivo';

    private const TIEMPO_ESPERA_BLOQUEO = 10;

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $consulta = Metal::query()
            ->withCount([
                'purezas',
                'productos',
                'piezas',
            ]);

        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        if ($buscar !== '') {
            $buscar = strtr($buscar, [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_',
            ]);
            $consulta->where(function ($subconsulta) use ($buscar) {
                $subconsulta
                    ->whereRaw("nombre LIKE ? ESCAPE '!'", ['%' . $buscar . '%'])
                    ->orWhereRaw("simbolo LIKE ? ESCAPE '!'", ['%' . $buscar . '%']);
            });
        }

        $estado = $filtros['estado'] ?? null;

        if (in_array($estado, [
            self::ESTADO_ACTIVO,
            self::ESTADO_INACTIVO,
        ], true)) {
            $consulta->where('estado', $estado);
        }

        return $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(self::REGISTROS_POR_PAGINA)
            ->withQueryString();
    }

    public function obtenerActivos(): Collection
    {
        return Metal::query()
            ->where('estado', self::ESTADO_ACTIVO)
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
    }

    public function crear(array $datos): Metal
    {
        return $this->ejecutarConBloqueo(function () use ($datos) {
            $nombre = trim($datos['nombre']);

            $this->validarNombreUnico($nombre);

            return Metal::query()->create([
                'nombre' => $nombre,
                'simbolo' => $this->normalizarTextoOpcional(
                    $datos['simbolo'] ?? null
                ),
                'descripcion' => $this->normalizarTextoOpcional(
                    $datos['descripcion'] ?? null
                ),
                'estado' => self::ESTADO_ACTIVO,
            ]);
        });
    }

    public function actualizar(
        Metal $metal,
        array $datos
    ): Metal {
        return $this->ejecutarConBloqueo(
            function () use ($metal, $datos) {
                $registro = Metal::query()
                    ->lockForUpdate()
                    ->findOrFail($metal->id);

                $nombre = trim($datos['nombre']);

                $simbolo = $this->normalizarTextoOpcional(
                    $datos['simbolo'] ?? null
                );

                if (
                    ($registro->nombre !== $nombre
                        || $registro->simbolo !== $simbolo)
                    && $this->tieneReferencias($registro)
                ) {
                    throw ValidationException::withMessages([
                        'nombre' => 'No puedes cambiar la identidad de un metal que tiene referencias.',
                    ]);
                }

                $this->validarNombreUnico(
                    $nombre,
                    (int) $registro->id
                );

                $registro->update([
                    'nombre' => $nombre,
                    'simbolo' => $this->normalizarTextoOpcional(
                        $datos['simbolo'] ?? null
                    ),
                    'descripcion' => $this->normalizarTextoOpcional(
                        $datos['descripcion'] ?? null
                    ),
                ]);

                return $registro->refresh();
            }
        );
    }

    public function cambiarEstado(
        Metal $metal,
        string $estado
    ): Metal {
        if (! in_array($estado, [
            self::ESTADO_ACTIVO,
            self::ESTADO_INACTIVO,
        ], true)) {
            throw ValidationException::withMessages([
                'estado' => 'El estado seleccionado no es válido.',
            ]);
        }

        return $this->ejecutarConBloqueo(
            function () use ($metal, $estado) {
                $registro = Metal::query()
                    ->lockForUpdate()
                    ->findOrFail($metal->id);

                if ($registro->estado === $estado) {
                    return $registro;
                }

                if ($estado === self::ESTADO_INACTIVO) {
                    $this->validarDesactivacion($registro);
                }

                $registro->update([
                    'estado' => $estado,
                ]);

                return $registro->refresh();
            }
        );
    }

    private function validarNombreUnico(
        string $nombre,
        ?int $excluirId = null
    ): void {
        $consulta = Metal::query()
            ->where('nombre', $nombre);

        if ($excluirId !== null) {
            $consulta->where('id', '!=', $excluirId);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un metal registrado con este nombre.',
            ]);
        }
    }

    public function tieneReferencias(Metal $metal): bool
    {
        $metalId = (int) $metal->id;

        return Pureza::query()
            ->where('metal_id', $metalId)
            ->exists()
            || Producto::withTrashed()
                ->where('metal_id', $metalId)
                ->exists()
            || Pieza::query()
                ->where('metal_id', $metalId)
                ->exists();
    }

    private function validarDesactivacion(
        Metal $metal
    ): void {
        $metalId = (int) $metal->id;

        $tieneProductosActivos = Producto::query()
            ->where('estado', self::ESTADO_ACTIVO)
            ->where(function ($consulta) use ($metalId) {
                $consulta
                    ->where('metal_id', $metalId)
                    ->orWhereIn(
                        'pureza_id',
                        Pureza::query()
                            ->select('id')
                            ->where('metal_id', $metalId)
                    );
            })
            ->exists();

        if ($tieneProductosActivos) {
            throw ValidationException::withMessages([
                'estado' => 'No puedes desactivar un metal que tiene productos activos asociados directamente o mediante sus purezas.',
            ]);
        }

        $tienePiezasOperativas = Pieza::query()
            ->where(function ($consulta) use ($metalId) {
                $consulta
                    ->where('metal_id', $metalId)
                    ->orWhereIn(
                        'pureza_id',
                        Pureza::query()
                            ->select('id')
                            ->where('metal_id', $metalId)
                    )
                    ->orWhereIn(
                        'producto_id',
                        Producto::withTrashed()
                            ->select('id')
                            ->where(function ($productos) use ($metalId) {
                                $productos
                                    ->where('metal_id', $metalId)
                                    ->orWhereIn(
                                        'pureza_id',
                                        Pureza::query()
                                            ->select('id')
                                            ->where('metal_id', $metalId)
                                    );
                            })
                    );
            })
            ->whereNotIn(
                'estado_inventario_id',
                DB::table('estados_inventario')
                    ->select('id')
                    ->whereIn('codigo', [
                        'vendido',
                        'perdido',
                    ])
            )
            ->exists();

        if ($tienePiezasOperativas) {
            throw ValidationException::withMessages([
                'estado' => 'No puedes desactivar un metal que tiene piezas asociadas en inventario operativo.',
            ]);
        }

        $tieneExistenciasOperativas = Existencia::query()
            ->where('cantidad', '>', 0)
            ->whereIn(
                'producto_id',
                Producto::withTrashed()
                    ->select('id')
                    ->where(function ($productos) use ($metalId) {
                        $productos
                            ->where('metal_id', $metalId)
                            ->orWhereIn(
                                'pureza_id',
                                Pureza::query()
                                    ->select('id')
                                    ->where('metal_id', $metalId)
                            );
                    })
            )
            ->whereNotIn(
                'estado_inventario_id',
                DB::table('estados_inventario')
                    ->select('id')
                    ->whereIn('codigo', ['vendido', 'perdido'])
            )
            ->exists();

        if ($tieneExistenciasOperativas) {
            throw ValidationException::withMessages([
                'estado' => 'No puedes desactivar un metal que tiene existencias operativas.',
            ]);
        }
    }

    private function normalizarTextoOpcional(
        ?string $valor
    ): ?string {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        return $valor === ''
            ? null
            : $valor;
    }

    private function ejecutarConBloqueo(
        Closure $operacion
    ): mixed {
        $conexion = DB::connection();

        $nombreBloqueo = 'joysys:catalogos:' . sha1(
            $conexion->getDatabaseName()
        );

        $resultado = $conexion->selectOne(
            'SELECT GET_LOCK(?, ?) AS adquirido',
            [
                $nombreBloqueo,
                self::TIEMPO_ESPERA_BLOQUEO,
            ],
            false
        );

        if ((int) ($resultado->adquirido ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'catalogo' => 'El catálogo está siendo actualizado. Intenta nuevamente en unos segundos.',
            ]);
        }

        try {
            return $conexion->transaction(
                $operacion,
                1
            );
        } finally {
            $conexion->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$nombreBloqueo],
                false
            );
        }
    }
}