<?php

namespace App\Services\Precios;

use App\Models\Producto;
use App\Models\PrecioProducto;
use App\Models\HistorialPrecio;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PrecioService
{
    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $consulta = Producto::query()
            ->with(['categoria', 'precioVigente']);

        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        if ($buscar !== '') {
            $patron = '%' . strtr($buscar, [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_',
            ]) . '%';

            $consulta->where(function ($q) use ($patron) {
                $q->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron]);
            });
        }

        if (in_array(
            $filtros['estado'] ?? null,
            ['activo', 'inactivo'],
            true
        )) {
            $consulta->where('estado', $filtros['estado']);
        }

        return $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function historial(
        Producto $producto
    ): LengthAwarePaginator {
        return HistorialPrecio::query()
            ->where('producto_id', $producto->id)
            ->with('usuario')
            ->orderByDesc('vigente_desde')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function normalizarImporte(
        mixed $importe,
        string $campo = 'precio',
        bool $permitirCero = false
    ): string {
        if (!is_string($importe) && !is_int($importe)) {
            throw ValidationException::withMessages([
                $campo => 'El importe debe ser un decimal valido.',
            ]);
        }

        $valor = trim((string) $importe);

        if (!preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', $valor)) {
            throw ValidationException::withMessages([
                $campo => 'Utiliza hasta ocho enteros y cuatro decimales.',
            ]);
        }

        $partes = explode('.', $valor, 2);

        $entero = ltrim($partes[0], '0');
        $entero = $entero === '' ? '0' : $entero;

        $decimal = str_pad(
            $partes[1] ?? '',
            4,
            '0',
            STR_PAD_RIGHT
        );

        $normalizado = $entero . '.' . $decimal;

        if (!$permitirCero && $normalizado === '0.0000') {
            throw ValidationException::withMessages([
                $campo => 'El precio debe ser mayor que cero.',
            ]);
        }

        return $normalizado;
    }

    public function inicializar(
        Producto $producto,
        mixed $importe,
        User $usuario
    ): PrecioProducto {
        Gate::forUser($usuario)->authorize(
            'joysys.permiso',
            'catalogos.crear'
        );

        return $this->ejecutarConBloqueo(
            function () use ($producto, $importe, $usuario) {
                $actual = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                if (
                    $actual->precioVigente()->exists() ||
                    $actual->historialPrecios()->exists()
                ) {
                    throw ValidationException::withMessages([
                        'precio_base' => 'El producto ya tiene precios registrados.',
                    ]);
                }

                $precio = $this->normalizarImporte(
                    $importe,
                    'precio_base'
                );

                if ($precio !== $actual->precio_base) {
                    throw ValidationException::withMessages([
                        'precio_base' => 'El precio inicial no coincide con el precio base.',
                    ]);
                }

                $fecha = now();

                $vigente = PrecioProducto::create([
                    'producto_id' => $actual->id,
                    'precio' => $precio,
                    'moneda' => 'MXN',
                    'vigente_desde' => $fecha,
                ]);

                HistorialPrecio::create([
                    'producto_id' => $actual->id,
                    'precio_anterior' => null,
                    'precio_nuevo' => $precio,
                    'moneda' => 'MXN',
                    'vigente_desde' => $fecha,
                    'usuario_id' => $usuario->id,
                ]);

                return $vigente;
            }
        );
    }

    public function actualizar(
        Producto $producto,
        mixed $importe,
        User $usuario
    ): PrecioProducto {
        Gate::forUser($usuario)->authorize(
            'joysys.permiso',
            'precios.modificar'
        );

        $nuevo = $this->normalizarImporte($importe);

        return $this->ejecutarConBloqueo(
            function () use ($producto, $nuevo, $usuario) {
                $actual = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                $vigente = PrecioProducto::query()
                    ->where('producto_id', $actual->id)
                    ->lockForUpdate()
                    ->first();

                if (!$vigente) {
                    throw ValidationException::withMessages([
                        'precio' => 'El producto no tiene un precio inicial registrado.',
                    ]);
                }

                $anterior = $vigente->precio;

                if ($anterior === $nuevo) {
                    return $vigente;
                }

                $fecha = now();

                $vigente->update([
                    'precio' => $nuevo,
                    'moneda' => 'MXN',
                    'vigente_desde' => $fecha,
                ]);

                HistorialPrecio::create([
                    'producto_id' => $actual->id,
                    'precio_anterior' => $anterior,
                    'precio_nuevo' => $nuevo,
                    'moneda' => 'MXN',
                    'vigente_desde' => $fecha,
                    'usuario_id' => $usuario->id,
                ]);

                return $vigente->refresh();
            }
        );
    }

    private function ejecutarConBloqueo(
        Closure $operacion
    ): mixed {
        $conexion = DB::connection();

        $nombre = 'joysys:catalogos:' . sha1(
            $conexion->getDatabaseName()
        );

        $resultado = $conexion->selectOne(
            'SELECT GET_LOCK(?, ?) AS adquirido',
            [$nombre, 10],
            false
        );

        if ((int) ($resultado->adquirido ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'precio' => 'El catalogo esta siendo actualizado. Intenta nuevamente.',
            ]);
        }

        try {
            return $conexion->transaction($operacion, 1);
        } finally {
            $conexion->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$nombre],
                false
            );
        }
    }
}
