<?php

namespace App\Services\Catalogos;

use App\Models\Metal;
use App\Models\Pieza;
use App\Models\Producto;
use App\Models\Pureza;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurezaService
{
    private const REGISTROS_POR_PAGINA = 15;

    private const ESTADO_METAL_ACTIVO = 'activo';

    private const TIEMPO_ESPERA_BLOQUEO = 10;

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $consulta = Pureza::query()
            ->with('metal')
            ->withCount([
                'productos' => function ($productos) {
                    $productos->withTrashed();
                },
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
                    ->orWhereRaw("codigo LIKE ? ESCAPE '!'", ['%' . $buscar . '%']);
            });
        }

        $metalId = $filtros['metal_id'] ?? null;

        if ($metalId !== null && $metalId !== '') {
            $consulta->where('metal_id', (int) $metalId);
        }

        return $consulta
            ->orderBy(
                Metal::query()
                    ->select('nombre')
                    ->whereColumn(
                        'metales.id',
                        'purezas.metal_id'
                    )
            )
            ->orderBy('id')
            ->paginate(self::REGISTROS_POR_PAGINA)
            ->withQueryString();
    }

    public function obtenerDisponibles(
        ?int $metalId = null
    ): Collection {
        $consulta = Pureza::query()
            ->with('metal')
            ->whereHas('metal', function ($metales) {
                $metales->where(
                    'estado',
                    self::ESTADO_METAL_ACTIVO
                );
            });

        if ($metalId !== null) {
            $consulta->where('metal_id', $metalId);
        }

        return $consulta
            ->orderBy(
                Metal::query()
                    ->select('nombre')
                    ->whereColumn(
                        'metales.id',
                        'purezas.metal_id'
                    )
            )
            ->orderBy('id')
            ->get();
    }

    public function crear(array $datos): Pureza
    {
        return $this->ejecutarConBloqueo(function () use ($datos) {
            $metalId = (int) $datos['metal_id'];

            $codigo = mb_strtoupper(
                trim($datos['codigo']),
                'UTF-8'
            );

            $nombre = trim($datos['nombre']);

            $this->validarMetalActivo($metalId);

            $this->validarUnicidad(
                $metalId,
                $codigo,
                $nombre
            );

            return Pureza::query()->create([
                'metal_id' => $metalId,
                'codigo' => $codigo,
                'nombre' => $nombre,
            ]);
        });
    }

    public function actualizar(
        Pureza $pureza,
        array $datos
    ): Pureza {
        return $this->ejecutarConBloqueo(
            function () use ($pureza, $datos) {
                $registro = Pureza::query()
                    ->lockForUpdate()
                    ->findOrFail($pureza->id);

                $metalId = (int) $datos['metal_id'];

                $codigo = mb_strtoupper(
                    trim($datos['codigo']),
                    'UTF-8'
                );

                $nombre = trim($datos['nombre']);

                $hayCambios =
                    (int) $registro->metal_id !== $metalId
                    || $registro->codigo !== $codigo
                    || $registro->nombre !== $nombre;

                if (! $hayCambios) {
                    return $registro;
                }

                if ($this->tieneReferencias($registro)) {
                    throw ValidationException::withMessages([
                        'pureza' => 'No puedes modificar una pureza que ya tiene productos o piezas asociados.',
                    ]);
                }

                $this->validarMetalActivo($metalId);

                $this->validarUnicidad(
                    $metalId,
                    $codigo,
                    $nombre,
                    (int) $registro->id
                );

                $registro->update([
                    'metal_id' => $metalId,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                ]);

                return $registro->refresh();
            }
        );
    }

    public function tieneReferencias(
        Pureza $pureza
    ): bool {
        $purezaId = (int) $pureza->id;

        $tieneProductos = Producto::withTrashed()
            ->where('pureza_id', $purezaId)
            ->exists();

        if ($tieneProductos) {
            return true;
        }

        return Pieza::query()
            ->where('pureza_id', $purezaId)
            ->exists();
    }

    private function validarMetalActivo(
        int $metalId
    ): void {
        $metal = Metal::query()
            ->lockForUpdate()
            ->find($metalId);

        if ($metal === null) {
            throw ValidationException::withMessages([
                'metal_id' => 'El metal seleccionado no existe.',
            ]);
        }

        if ($metal->estado !== self::ESTADO_METAL_ACTIVO) {
            throw ValidationException::withMessages([
                'metal_id' => 'El metal seleccionado debe estar activo.',
            ]);
        }
    }

    private function validarUnicidad(
        int $metalId,
        string $codigo,
        string $nombre,
        ?int $excluirId = null
    ): void {
        $consulta = Pureza::query()
            ->where('metal_id', $metalId);

        if ($excluirId !== null) {
            $consulta->where('id', '!=', $excluirId);
        }

        $errores = [];

        $codigoDuplicado = (clone $consulta)
            ->where('codigo', $codigo)
            ->exists();

        if ($codigoDuplicado) {
            $errores['codigo'] =
                'Ya existe una pureza con este código para el metal seleccionado.';
        }

        $nombreDuplicado = (clone $consulta)
            ->where('nombre', $nombre)
            ->exists();

        if ($nombreDuplicado) {
            $errores['nombre'] =
                'Ya existe una pureza con este nombre para el metal seleccionado.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(
                $errores
            );
        }
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