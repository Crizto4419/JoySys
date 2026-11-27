<?php

namespace App\Services\Catalogos;

use App\Models\Categoria;
use App\Models\Producto;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoriaService
{
    private const REGISTROS_POR_PAGINA = 15;

    private const ESTADO_ACTIVA = 'activa';

    private const ESTADO_INACTIVA = 'inactiva';

    private const TIEMPO_ESPERA_BLOQUEO = 10;

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $consulta = Categoria::query()
            ->with('categoriaPadre')
            ->withCount([
                'subcategorias',
                'productos',
            ]);

        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        if ($buscar !== '') {
            $buscar = strtr($buscar, [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_',
            ]);
            $consulta->whereRaw(
                "nombre LIKE ? ESCAPE '!'",
                ['%' . $buscar . '%']
            );
        }

        $estado = $filtros['estado'] ?? null;

        if (in_array($estado, [
            self::ESTADO_ACTIVA,
            self::ESTADO_INACTIVA,
        ], true)) {
            $consulta->where('estado', $estado);
        }

        $padre = $filtros['categoria_padre_id'] ?? null;

        if ($padre === 'principal') {
            $consulta->whereNull('categoria_padre_id');
        } elseif ($padre !== null && $padre !== '') {
            $consulta->where(
                'categoria_padre_id',
                (int) $padre
            );
        }

        return $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(self::REGISTROS_POR_PAGINA)
            ->withQueryString();
    }

    public function obtenerPadresDisponibles(
        ?Categoria $categoria = null
    ): Collection {
        $consulta = Categoria::query()
            ->where('estado', self::ESTADO_ACTIVA);

        if ($categoria !== null) {
            $excluidos = $this->obtenerDescendientes(
                (int) $categoria->id
            );

            $excluidos[] = (int) $categoria->id;

            $consulta->whereNotIn('id', $excluidos);
        }

        $categorias = Categoria::query()
            ->where('estado', self::ESTADO_ACTIVA)
            ->get()
            ->keyBy('id');

        return $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->get()
            ->filter(function (Categoria $registro) use ($categorias) {
                $visitados = [(int) $registro->id => true];
                $padreId = $registro->categoria_padre_id;

                while ($padreId !== null) {
                    $padreId = (int) $padreId;

                    if (isset($visitados[$padreId])) {
                        return false;
                    }

                    $visitados[$padreId] = true;
                    $padre = $categorias->get($padreId);

                    if ($padre === null) {
                        return false;
                    }

                    $padreId = $padre->categoria_padre_id;
                }

                return true;
            })
            ->values();
    }

    public function crear(array $datos): Categoria
    {
        return $this->ejecutarConBloqueo(function () use ($datos) {
            $nombre = trim($datos['nombre']);

            $padreId = $this->normalizarPadre(
                $datos['categoria_padre_id'] ?? null
            );

            $this->validarJerarquia($padreId);

            $this->validarNombreUnico(
                $nombre,
                $padreId
            );

            return Categoria::query()->create([
                'categoria_padre_id' => $padreId,
                'nombre' => $nombre,
                'descripcion' => $this->normalizarDescripcion(
                    $datos['descripcion'] ?? null
                ),
                'estado' => self::ESTADO_ACTIVA,
            ]);
        });
    }

    public function actualizar(
        Categoria $categoria,
        array $datos
    ): Categoria {
        return $this->ejecutarConBloqueo(
            function () use ($categoria, $datos) {
                $registro = Categoria::query()
                    ->lockForUpdate()
                    ->findOrFail($categoria->id);

                $nombre = trim($datos['nombre']);

                $padreId = $this->normalizarPadre(
                    $datos['categoria_padre_id'] ?? null
                );

                $this->validarJerarquia(
                    $padreId,
                    (int) $registro->id
                );

                $this->validarNombreUnico(
                    $nombre,
                    $padreId,
                    (int) $registro->id
                );

                $registro->update([
                    'categoria_padre_id' => $padreId,
                    'nombre' => $nombre,
                    'descripcion' => $this->normalizarDescripcion(
                        $datos['descripcion'] ?? null
                    ),
                ]);

                return $registro->refresh();
            }
        );
    }

    public function cambiarEstado(
        Categoria $categoria,
        string $estado
    ): Categoria {
        if (! in_array($estado, [
            self::ESTADO_ACTIVA,
            self::ESTADO_INACTIVA,
        ], true)) {
            throw ValidationException::withMessages([
                'estado' => 'El estado seleccionado no es válido.',
            ]);
        }

        return $this->ejecutarConBloqueo(
            function () use ($categoria, $estado) {
                $registro = Categoria::query()
                    ->lockForUpdate()
                    ->findOrFail($categoria->id);

                if ($registro->estado === $estado) {
                    return $registro;
                }

                if ($estado === self::ESTADO_INACTIVA) {
                    $this->validarDesactivacion($registro);
                }

                if ($estado === self::ESTADO_ACTIVA) {
                    $this->validarJerarquia(
                        $this->normalizarPadre(
                            $registro->categoria_padre_id
                        ),
                        (int) $registro->id
                    );

                    $this->validarNombreUnico(
                        $registro->nombre,
                        $this->normalizarPadre(
                            $registro->categoria_padre_id
                        ),
                        (int) $registro->id
                    );
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
        ?int $padreId,
        ?int $excluirId = null
    ): void {
        $consulta = Categoria::query()
            ->where('nombre', $nombre);

        if ($padreId === null) {
            $consulta->whereNull('categoria_padre_id');
        } else {
            $consulta->where(
                'categoria_padre_id',
                $padreId
            );
        }

        if ($excluirId !== null) {
            $consulta->where('id', '!=', $excluirId);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe una categoría con este nombre dentro del mismo nivel jerárquico.',
            ]);
        }
    }

    private function validarJerarquia(
        ?int $padreId,
        ?int $categoriaId = null
    ): void {
        if ($padreId === null) {
            return;
        }

        $visitados = [];

        $actualId = $padreId;

        while ($actualId !== null) {
            if ($categoriaId !== null && $actualId === $categoriaId) {
                throw ValidationException::withMessages([
                    'categoria_padre_id' => 'La categoría seleccionada generaría una referencia circular.',
                ]);
            }

            if (isset($visitados[$actualId])) {
                throw ValidationException::withMessages([
                    'categoria_padre_id' => 'La jerarquía seleccionada contiene una referencia circular.',
                ]);
            }

            $visitados[$actualId] = true;

            $padre = Categoria::query()
                ->lockForUpdate()
                ->find($actualId);

            if ($padre === null) {
                throw ValidationException::withMessages([
                    'categoria_padre_id' => 'La categoría padre o uno de sus antecesores no existe o fue eliminado.',
                ]);
            }

            if ($padre->estado !== self::ESTADO_ACTIVA) {
                throw ValidationException::withMessages([
                    'categoria_padre_id' => 'La categoría padre y todos sus antecesores deben estar activos.',
                ]);
            }

            $actualId = $this->normalizarPadre(
                $padre->categoria_padre_id
            );
        }
    }

    private function obtenerDescendientes(
        int $categoriaId
    ): array {
        $descendientes = [];

        $visitados = [
            $categoriaId => true,
        ];

        $pendientes = [$categoriaId];

        while ($pendientes !== []) {
            $hijos = Categoria::withTrashed()
                ->whereIn(
                    'categoria_padre_id',
                    $pendientes
                )
                ->pluck('id');

            $pendientes = [];

            foreach ($hijos as $hijoId) {
                $hijoId = (int) $hijoId;

                if (isset($visitados[$hijoId])) {
                    continue;
                }

                $visitados[$hijoId] = true;

                $descendientes[] = $hijoId;

                $pendientes[] = $hijoId;
            }
        }

        return $descendientes;
    }

    private function validarDesactivacion(
        Categoria $categoria
    ): void {
        $descendientes = $this->obtenerDescendientes(
            (int) $categoria->id
        );

        if ($descendientes !== []) {
            $tieneDescendientesActivos = Categoria::query()
                ->whereIn('id', $descendientes)
                ->where('estado', self::ESTADO_ACTIVA)
                ->exists();

            if ($tieneDescendientesActivos) {
                throw ValidationException::withMessages([
                    'estado' => 'No puedes desactivar una categoría que tiene subcategorías activas.',
                ]);
            }
        }

        $categoriasRelacionadas = [
            (int) $categoria->id,
            ...$descendientes,
        ];

        $tieneProductosActivos = Producto::query()
            ->whereIn(
                'categoria_id',
                $categoriasRelacionadas
            )
            ->where('estado', 'activo')
            ->exists();

        if ($tieneProductosActivos) {
            throw ValidationException::withMessages([
                'estado' => 'No puedes desactivar una categoría que tiene productos activos asociados.',
            ]);
        }
    }

    private function normalizarPadre(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }

    private function normalizarDescripcion(
        ?string $descripcion
    ): ?string {
        if ($descripcion === null) {
            return null;
        }

        $descripcion = trim($descripcion);

        return $descripcion === ''
            ? null
            : $descripcion;
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