<?php

namespace App\Services\Inventario;

use App\Models\Categoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class FiltrosInventario
{
    public const TIPOS = [
        'individual' => 'Individual',
        'por_cantidad' => 'Por cantidad',
        'por_peso' => 'Por peso',
    ];

    public const ESTADOS = [
        'disponible' => 'Disponible',
        'apartado' => 'Apartado',
        'vendido' => 'Vendido',
        'reparacion' => 'En reparacion',
        'danado' => 'Danado',
        'perdido' => 'Perdido',
    ];

    public const NIVELES = [
        'sin_existencias' => 'Sin existencias',
        'bajo' => 'Stock bajo',
        'suficiente' => 'Stock suficiente',
    ];

    public const MOVIMIENTOS = [
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste',
        'transferencia' => 'Transferencia',
    ];

    public const ESTADOS_INCIDENCIA = [
        'abierta' => 'Abierta',
        'en_revision' => 'En revision',
        'resuelta' => 'Resuelta',
    ];

    public static function opciones(string $pantalla): array
    {
        return match ($pantalla) {
            'productos' => [
                'categoria_id' => Categoria::query()
                    ->orderBy('nombre')
                    ->orderBy('id')
                    ->pluck('nombre', 'id')
                    ->all(),
                'tipo' => self::TIPOS,
                'nivel_stock' => self::NIVELES,
            ],
            'piezas' => [
                'estado' => self::ESTADOS,
            ],
            'existencias' => [
                'tipo' => self::TIPOS,
                'estado' => self::ESTADOS,
            ],
            'movimientos' => [
                'tipo_movimiento' => self::MOVIMIENTOS,
            ],
            'incidencias' => [
                'tipo_incidencia' => IncidenciaService::TIPOS,
                'estado' => self::ESTADOS_INCIDENCIA,
            ],
            default => [],
        };
    }

    public static function valor(
        array $filtros,
        string $campo
    ): ?string {
        $valor = $filtros[$campo] ?? null;

        if (!is_string($valor) && !is_int($valor)) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private static function patron(string $texto): string
    {
        return '%' . str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $texto
        ) . '%';
    }

    private static function buscarProducto(
        Builder $consulta,
        string $patron
    ): void {
        $consulta->where(function ($q) use ($patron) {
            $q->whereRaw(
                "productos.codigo LIKE ? ESCAPE '!'",
                [$patron]
            )->orWhereRaw(
                "productos.nombre LIKE ? ESCAPE '!'",
                [$patron]
            );
        });
    }

    public static function productos(
        Builder $consulta,
        array $filtros,
        int $sucursalId,
        int $disponible
    ): void {
        if (($buscar = self::valor($filtros, 'buscar')) !== null) {
            self::buscarProducto(
                $consulta,
                self::patron($buscar)
            );
        }

        if (($categoria = self::valor(
            $filtros,
            'categoria_id'
        )) !== null) {
            $consulta->where('categoria_id', $categoria);
        }

        if (($tipo = self::valor($filtros, 'tipo')) !== null) {
            $consulta->where('tipo', $tipo);
        }

        $nivel = self::valor($filtros, 'nivel_stock');

        if (!array_key_exists($nivel ?? '', self::NIVELES)) {
            return;
        }

        $stock = <<<'SQL'
CASE WHEN productos.tipo = 'individual' THEN
    (SELECT COUNT(*) FROM piezas
        WHERE piezas.producto_id = productos.id
        AND piezas.sucursal_id = ?
        AND piezas.estado_inventario_id = ?)
ELSE
    COALESCE((SELECT SUM(existencias.cantidad)
        FROM existencias
        WHERE existencias.producto_id = productos.id
        AND existencias.sucursal_id = ?
        AND existencias.estado_inventario_id = ?), 0)
END
SQL;

        $parametros = [
            $sucursalId,
            $disponible,
            $sucursalId,
            $disponible,
        ];

        if ($nivel === 'sin_existencias') {
            $consulta->whereRaw(
                "($stock) = 0",
                $parametros
            );
        } elseif ($nivel === 'bajo') {
            $consulta->whereRaw(
                "($stock) > 0 AND ($stock) <= productos.stock_minimo",
                array_merge($parametros, $parametros)
            );
        } else {
            $consulta->whereRaw(
                "($stock) > productos.stock_minimo",
                $parametros
            );
        }
    }

    public static function registros(
        Builder $consulta,
        array $filtros,
        string $pantalla
    ): void {
        $buscar = self::valor($filtros, 'buscar');

        if ($buscar !== null) {
            $patron = self::patron($buscar);

            $consulta->where(function ($q) use (
                $patron,
                $pantalla
            ) {
                $q->whereHas('producto', function ($producto) use (
                    $patron
                ) {
                    $producto->withTrashed();
                    self::buscarProducto($producto, $patron);
                });

                if ($pantalla === 'piezas') {
                    $q->orWhereRaw(
                        "codigo_pieza LIKE ? ESCAPE '!'",
                        [$patron]
                    );
                }

                if ($pantalla === 'movimientos') {
                    $q->orWhereHas('pieza', fn ($pieza) =>
                        $pieza->whereRaw(
                            "codigo_pieza LIKE ? ESCAPE '!'",
                            [$patron]
                        )
                    );
                }
            });
        }

        $estado = self::valor($filtros, 'estado');
        $tipo = self::valor($filtros, 'tipo');

        if (in_array(
            $pantalla,
            ['piezas', 'existencias'],
            true
        ) && $estado !== null) {
            $consulta->whereHas(
                'estadoInventario',
                fn ($q) => $q->where('codigo', $estado)
            );
        }

        if ($pantalla === 'existencias' && $tipo !== null) {
            $consulta->whereHas(
                'producto',
                fn ($q) => $q->withTrashed()
                    ->where('tipo', $tipo)
            );
        }

        if ($pantalla === 'movimientos') {
            $movimiento = self::valor(
                $filtros,
                'tipo_movimiento'
            );

            if ($movimiento !== null) {
                $consulta->where(
                    'tipo_movimiento',
                    $movimiento
                );
            }
        }

        if ($pantalla === 'incidencias') {
            if ($estado !== null) {
                $consulta->where('estado', $estado);
            }

            $incidencia = self::valor(
                $filtros,
                'tipo_incidencia'
            );

            if ($incidencia !== null) {
                $consulta->where(
                    'tipo_incidencia',
                    $incidencia
                );
            }
        }

        if (in_array(
            $pantalla,
            ['movimientos', 'incidencias'],
            true
        )) {
            self::fechas($consulta, $filtros);
        }
    }

    private static function fechas(
        Builder $consulta,
        array $filtros
    ): void {
        $desde = self::valor($filtros, 'fecha_desde');
        $hasta = self::valor($filtros, 'fecha_hasta');
        $zona = 'America/Mexico_City';

        if ($desde !== null) {
            $inicio = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $desde,
                $zona
            )->utc();

            $consulta->where('created_at', '>=', $inicio);
        }

        if ($hasta !== null) {
            $fin = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $hasta,
                $zona
            )->addDay()->utc();

            $consulta->where('created_at', '<', $fin);
        }
    }
}
