<?php

namespace App\Services\Inventario;

use App\Models\Producto;
use App\Models\Pieza;
use App\Models\Existencia;
use App\Models\EstadoInventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use App\Services\Auth\ContextoSucursalService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    public const TRANSICIONES = [
        'disponible' => ['reparacion', 'danado', 'perdido'],
        'reparacion' => ['disponible', 'danado', 'perdido'],
        'danado' => ['disponible', 'reparacion', 'perdido'],
        'perdido' => ['disponible'],
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

    private function error(
        string $campo,
        string $mensaje
    ): never {
        throw ValidationException::withMessages([
            $campo => $mensaje,
        ]);
    }

    private function ejecutar(
        User $usuario,
        string $permiso,
        Closure $operacion
    ): mixed {
        $sucursalId = $this->autorizar(
            $usuario,
            $permiso
        );

        $conexion = DB::connection();

        $nombre = 'joysys:catalogos:' .
            sha1($conexion->getDatabaseName());

        $bloqueo = $conexion->selectOne(
            'SELECT GET_LOCK(?, ?) AS adquirido',
            [$nombre, 10],
            false
        );

        if ((int) ($bloqueo->adquirido ?? 0) !== 1) {
            $this->error(
                'inventario',
                'El inventario esta ocupado. Intenta nuevamente.'
            );
        }

        try {
            return $conexion->transaction(
                function () use (
                    $usuario,
                    $permiso,
                    $sucursalId,
                    $operacion
                ) {
                    abort_unless(
                        $this->autorizar(
                            $usuario,
                            $permiso
                        ) === $sucursalId,
                        403
                    );

                    return $operacion($sucursalId);
                },
                1
            );
        } finally {
            $conexion->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$nombre],
                false
            );
        }
    }

    public function normalizarCantidad(
        mixed $valor,
        string $tipo
    ): string {
        if (!in_array(
            $tipo,
            ['individual', 'por_cantidad', 'por_peso'],
            true
        )) {
            $this->error('tipo', 'Tipo de producto invalido.');
        }

        if (!is_string($valor) && !is_int($valor)) {
            $this->error('cantidad', 'Cantidad invalida.');
        }

        $valor = trim((string) $valor);

        if (!preg_match(
            '/^[0-9]{1,8}(?:\.[0-9]{1,4})?$/D',
            $valor
        )) {
            $this->error(
                'cantidad',
                'La cantidad admite hasta ocho enteros y cuatro decimales.'
            );
        }

        $cantidad = bcadd($valor, '0', 4);

        if (bccomp($cantidad, '0', 4) <= 0) {
            $this->error(
                'cantidad',
                'La cantidad debe ser mayor que cero.'
            );
        }

        if (
            $tipo !== 'por_peso' &&
            bccomp(
                $cantidad,
                bcadd($cantidad, '0', 0),
                4
            ) !== 0
        ) {
            $this->error(
                'cantidad',
                'Este producto requiere cantidades enteras.'
            );
        }

        if (
            $tipo === 'individual' &&
            bccomp($cantidad, '1', 4) !== 0
        ) {
            $this->error(
                'cantidad',
                'Cada movimiento individual corresponde a una pieza.'
            );
        }

        return $cantidad;
    }

    private function normalizarPeso(
        mixed $valor
    ): ?string {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_string($valor) && !is_int($valor)) {
            $this->error('peso_gramos', 'Peso invalido.');
        }

        $valor = trim((string) $valor);

        if (
            !preg_match(
                '/^[0-9]{1,5}(?:\.[0-9]{1,3})?$/D',
                $valor
            ) ||
            bccomp($valor, '0', 3) <= 0
        ) {
            $this->error(
                'peso_gramos',
                'Captura un peso positivo con hasta tres decimales.'
            );
        }

        return bcadd($valor, '0', 3);
    }

    private function observaciones(
        mixed $valor,
        bool $obligatorias = false
    ): ?string {
        if ($valor !== null && !is_string($valor)) {
            $this->error(
                'observaciones',
                'Las observaciones deben ser texto.'
            );
        }

        $texto = trim($valor ?? '');

        if (
            ($obligatorias && $texto === '') ||
            strlen($texto) > 65535
        ) {
            $this->error(
                'observaciones',
                'Captura una justificacion valida.'
            );
        }

        return $texto === '' ? null : $texto;
    }

    private function estadoId(string $codigo): int
    {
        $estado = EstadoInventario::query()
            ->where('codigo', $codigo)
            ->first();

        if ($estado === null) {
            $this->error(
                'estado',
                'El estado de inventario no existe.'
            );
        }

        return (int) $estado->id;
    }

    private function productoBloqueado(
        Producto $producto
    ): Producto {
        return Producto::query()
            ->lockForUpdate()
            ->findOrFail($producto->id);
    }

    private function exigirActivo(
        Producto $producto
    ): void {
        if ($producto->estado !== 'activo') {
            $this->error(
                'producto_id',
                'El producto debe estar activo.'
            );
        }
    }

    private function validarTransicion(
        string $origen,
        string $destino
    ): void {
        if (!in_array(
            $destino,
            self::TRANSICIONES[$origen] ?? [],
            true
        )) {
            $this->error(
                'estado_destino',
                'La transicion solicitada no esta permitida.'
            );
        }
    }

    private function registrarMovimiento(
        Producto $producto,
        int $sucursalId,
        User $usuario,
        string $tipo,
        string $cantidad,
        ?int $origenId,
        ?int $destinoId,
        ?int $piezaId,
        ?string $observaciones
    ): MovimientoInventario {
        return MovimientoInventario::create([
            'producto_id' => $producto->id,
            'sucursal_id' => $sucursalId,
            'pieza_id' => $piezaId,
            'estado_origen_id' => $origenId,
            'estado_destino_id' => $destinoId,
            'usuario_id' => $usuario->id,
            'tipo_movimiento' => $tipo,
            'cantidad' => $cantidad,
            'observaciones' => $observaciones,
        ]);
    }

    private function modificarExistencia(
        Producto $producto,
        int $sucursalId,
        int $estadoId,
        string $cantidad,
        bool $incrementar
    ): void {
        $existencia = Existencia::query()
            ->where('producto_id', $producto->id)
            ->where('sucursal_id', $sucursalId)
            ->where('estado_inventario_id', $estadoId)
            ->lockForUpdate()
            ->first();

        $actual = (string) (
            $existencia?->cantidad ?? '0.0000'
        );

        $nueva = $incrementar
            ? bcadd($actual, $cantidad, 4)
            : bcsub($actual, $cantidad, 4);

        if (bccomp($nueva, '0', 4) < 0) {
            $this->error(
                'cantidad',
                'No existen suficientes existencias.'
            );
        }

        if (
            bccomp(
                $nueva,
                '99999999.9999',
                4
            ) > 0
        ) {
            $this->error(
                'cantidad',
                'Se excede la capacidad maxima de existencias.'
            );
        }

        if ($existencia === null) {
            Existencia::create([
                'producto_id' => $producto->id,
                'sucursal_id' => $sucursalId,
                'estado_inventario_id' => $estadoId,
                'cantidad' => $nueva,
            ]);

            return;
        }

        $existencia->update([
            'cantidad' => $nueva,
        ]);
    }

    public function registrarPieza(
        Producto $producto,
        array $datos,
        User $usuario
    ): Pieza {
        return $this->ejecutar(
            $usuario,
            'inventario.registrar_entrada',
            function (int $sucursalId) use (
                $producto,
                $datos,
                $usuario
            ) {
                $actual = $this->productoBloqueado($producto);

                $this->exigirActivo($actual);

                if ($actual->tipo !== 'individual') {
                    $this->error(
                        'producto_id',
                        'El producto no admite piezas individuales.'
                    );
                }

                $codigo = $datos['codigo_pieza'] ?? null;

                if (!is_string($codigo)) {
                    $this->error(
                        'codigo_pieza',
                        'Captura el codigo de la pieza.'
                    );
                }

                $codigo = trim($codigo);

                if (
                    $codigo === '' ||
                    mb_strlen($codigo) > 80 ||
                    Pieza::query()
                        ->where('codigo_pieza', $codigo)
                        ->exists()
                ) {
                    $this->error(
                        'codigo_pieza',
                        'El codigo es invalido o ya existe.'
                    );
                }

                $peso = $this->normalizarPeso(
                    $datos['peso_gramos'] ?? null
                );

                $observaciones = $this->observaciones(
                    $datos['observaciones'] ?? null
                );

                $disponible = $this->estadoId('disponible');

                $pieza = Pieza::create([
                    'producto_id' => $actual->id,
                    'sucursal_id' => $sucursalId,
                    'estado_inventario_id' => $disponible,
                    'metal_id' => $actual->metal_id,
                    'pureza_id' => $actual->pureza_id,
                    'codigo_pieza' => $codigo,
                    'peso_gramos' => $peso,
                ]);

                $this->registrarMovimiento(
                    $actual,
                    $sucursalId,
                    $usuario,
                    'entrada',
                    '1.0000',
                    null,
                    $disponible,
                    (int) $pieza->id,
                    $observaciones
                );

                return $pieza;
            }
        );
    }

    public function registrarEntrada(
        Producto $producto,
        mixed $cantidad,
        User $usuario,
        mixed $observaciones = null
    ): MovimientoInventario {
        return $this->ejecutar(
            $usuario,
            'inventario.registrar_entrada',
            function (int $sucursalId) use (
                $producto,
                $cantidad,
                $usuario,
                $observaciones
            ) {
                $actual = $this->productoBloqueado($producto);

                $this->exigirActivo($actual);

                if ($actual->tipo === 'individual') {
                    $this->error(
                        'producto_id',
                        'Registra cada pieza individualmente.'
                    );
                }

                $cantidad = $this->normalizarCantidad(
                    $cantidad,
                    $actual->tipo
                );

                $disponible = $this->estadoId('disponible');

                $this->modificarExistencia(
                    $actual,
                    $sucursalId,
                    $disponible,
                    $cantidad,
                    true
                );

                return $this->registrarMovimiento(
                    $actual,
                    $sucursalId,
                    $usuario,
                    'entrada',
                    $cantidad,
                    null,
                    $disponible,
                    null,
                    $this->observaciones($observaciones)
                );
            }
        );
    }

    public function registrarSalida(
        Producto $producto,
        mixed $cantidad,
        User $usuario,
        mixed $observaciones
    ): MovimientoInventario {
        return $this->ejecutar(
            $usuario,
            'inventario.registrar_salida',
            function (int $sucursalId) use (
                $producto,
                $cantidad,
                $usuario,
                $observaciones
            ) {
                $actual = $this->productoBloqueado($producto);

                $this->exigirActivo($actual);

                if ($actual->tipo === 'individual') {
                    $this->error(
                        'producto_id',
                        'Las piezas individuales no admiten salidas administrativas ordinarias.'
                    );
                }

                $cantidad = $this->normalizarCantidad(
                    $cantidad,
                    $actual->tipo
                );

                $justificacion = $this->observaciones(
                    $observaciones,
                    true
                );

                $disponible = $this->estadoId('disponible');

                $this->modificarExistencia(
                    $actual,
                    $sucursalId,
                    $disponible,
                    $cantidad,
                    false
                );

                return $this->registrarMovimiento(
                    $actual,
                    $sucursalId,
                    $usuario,
                    'salida',
                    $cantidad,
                    $disponible,
                    null,
                    null,
                    $justificacion
                );
            }
        );
    }

    public function ajustarExistencia(
        Producto $producto,
        mixed $cantidad,
        string $sentido,
        User $usuario,
        mixed $observaciones
    ): MovimientoInventario {
        return $this->ejecutar(
            $usuario,
            'inventario.ajustar',
            function (int $sucursalId) use (
                $producto,
                $cantidad,
                $sentido,
                $usuario,
                $observaciones
            ) {
                $actual = $this->productoBloqueado($producto);

                $this->exigirActivo($actual);

                if ($actual->tipo === 'individual') {
                    $this->error(
                        'producto_id',
                        'Los ajustes individuales requieren una pieza.'
                    );
                }

                if (!in_array(
                    $sentido,
                    ['positivo', 'negativo'],
                    true
                )) {
                    $this->error(
                        'sentido',
                        'Sentido de ajuste invalido.'
                    );
                }

                $cantidad = $this->normalizarCantidad(
                    $cantidad,
                    $actual->tipo
                );

                $justificacion = $this->observaciones(
                    $observaciones,
                    true
                );

                $disponible = $this->estadoId('disponible');
                $positivo = $sentido === 'positivo';

                $this->modificarExistencia(
                    $actual,
                    $sucursalId,
                    $disponible,
                    $cantidad,
                    $positivo
                );

                return $this->registrarMovimiento(
                    $actual,
                    $sucursalId,
                    $usuario,
                    'ajuste',
                    $cantidad,
                    $positivo ? null : $disponible,
                    $positivo ? $disponible : null,
                    null,
                    $justificacion
                );
            }
        );
    }

    public function cambiarEstadoPieza(
        Pieza $pieza,
        string $destino,
        User $usuario,
        mixed $observaciones
    ): MovimientoInventario {
        $permiso = $destino === 'perdido'
            ? 'inventario.registrar_salida'
            : 'inventario.ajustar';

        return $this->ejecutar(
            $usuario,
            $permiso,
            function (int $sucursalId) use (
                $pieza,
                $destino,
                $usuario,
                $observaciones
            ) {
                $actual = Pieza::query()
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->findOrFail($pieza->id);

                $producto = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($actual->producto_id);

                $this->exigirActivo($producto);

                if ($producto->tipo !== 'individual') {
                    $this->error(
                        'producto_id',
                        'La pieza no corresponde a un producto individual.'
                    );
                }

                $origen = EstadoInventario::query()
                    ->findOrFail($actual->estado_inventario_id)
                    ->codigo;

                $this->validarTransicion($origen, $destino);

                $justificacion = $this->observaciones(
                    $observaciones,
                    true
                );

                $origenId = (int) $actual->estado_inventario_id;
                $destinoId = $this->estadoId($destino);

                $actual->update([
                    'estado_inventario_id' => $destinoId,
                ]);

                return $this->registrarMovimiento(
                    $producto,
                    $sucursalId,
                    $usuario,
                    $destino === 'perdido' ? 'salida' : 'ajuste',
                    '1.0000',
                    $origenId,
                    $destinoId,
                    (int) $actual->id,
                    $justificacion
                );
            }
        );
    }

    public function cambiarEstadoExistencia(
        Producto $producto,
        mixed $cantidad,
        string $origen,
        string $destino,
        User $usuario,
        mixed $observaciones
    ): MovimientoInventario {
        $permiso = $destino === 'perdido'
            ? 'inventario.registrar_salida'
            : 'inventario.ajustar';

        return $this->ejecutar(
            $usuario,
            $permiso,
            function (int $sucursalId) use (
                $producto,
                $cantidad,
                $origen,
                $destino,
                $usuario,
                $observaciones
            ) {
                $actual = $this->productoBloqueado($producto);

                $this->exigirActivo($actual);

                if ($actual->tipo === 'individual') {
                    $this->error(
                        'producto_id',
                        'Selecciona una pieza individual.'
                    );
                }

                $this->validarTransicion($origen, $destino);

                $cantidad = $this->normalizarCantidad(
                    $cantidad,
                    $actual->tipo
                );

                $justificacion = $this->observaciones(
                    $observaciones,
                    true
                );

                $origenId = $this->estadoId($origen);
                $destinoId = $this->estadoId($destino);

                $this->modificarExistencia(
                    $actual,
                    $sucursalId,
                    $origenId,
                    $cantidad,
                    false
                );

                $this->modificarExistencia(
                    $actual,
                    $sucursalId,
                    $destinoId,
                    $cantidad,
                    true
                );

                return $this->registrarMovimiento(
                    $actual,
                    $sucursalId,
                    $usuario,
                    $destino === 'perdido' ? 'salida' : 'ajuste',
                    $cantidad,
                    $origenId,
                    $destinoId,
                    null,
                    $justificacion
                );
            }
        );
    }

    public function listarProductos(User $usuario, array $filtros = [])
    {
        $sucursalId = $this->autorizar(
            $usuario,
            'inventario.ver'
        );

        $disponible = $this->estadoId('disponible');

        $consulta = Producto::query()
            ->where('estado', 'activo')
            ->with('categoria')
            ->withCount([
                'piezas as piezas_disponibles' => fn ($q) =>
                    $q->where('sucursal_id', $sucursalId)
                        ->where('estado_inventario_id', $disponible),
            ])
            ->withSum([
                'existencias as cantidad_disponible' => fn ($q) =>
                    $q->where('sucursal_id', $sucursalId)
                        ->where('estado_inventario_id', $disponible),
            ], 'cantidad');

        FiltrosInventario::productos(
            $consulta,
            $filtros,
            $sucursalId,
            $disponible
        );

        $productos = $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $productos->getCollection()->transform(
            function (Producto $producto) {
                $cantidad = $producto->tipo === 'individual'
                    ? (string) $producto->piezas_disponibles
                    : (string) (
                        $producto->cantidad_disponible ?? '0'
                    );

                $producto->setAttribute(
                    'stock_disponible',
                    bcadd($cantidad, '0', 4)
                );

                $producto->setAttribute(
                    'indicador_stock',
                    bccomp($cantidad, '0', 4) === 0
                        ? 'sin_existencias'
                        : (
                            bccomp(
                                $cantidad,
                                (string) $producto->stock_minimo,
                                4
                            ) <= 0
                                ? 'bajo'
                                : 'suficiente'
                        )
                );

                return $producto;
            }
        );

        return $productos;
    }

    public function listarPiezas(User $usuario, array $filtros = [])
    {
        $sucursalId = $this->autorizar(
            $usuario,
            'inventario.ver'
        );

        $consulta = Pieza::query()
            ->where('sucursal_id', $sucursalId)
            ->with([
                'producto' => fn ($q) => $q->withTrashed(),
                'estadoInventario',
                'metal',
                'pureza',
            ]);

        FiltrosInventario::registros(
            $consulta,
            $filtros,
            'piezas'
        );

        return $consulta
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function listarExistencias(User $usuario, array $filtros = [])
    {
        $sucursalId = $this->autorizar(
            $usuario,
            'inventario.ver'
        );

        $consulta = Existencia::query()
            ->where('sucursal_id', $sucursalId)
            ->with([
                'producto' => fn ($q) => $q->withTrashed(),
                'estadoInventario',
            ]);

        FiltrosInventario::registros(
            $consulta,
            $filtros,
            'existencias'
        );

        return $consulta
            ->orderBy('producto_id')
            ->orderBy('estado_inventario_id')
            ->paginate(15)
            ->withQueryString();
    }

    public function listarMovimientos(User $usuario, array $filtros = [])
    {
        $sucursalId = $this->autorizar(
            $usuario,
            'inventario.ver'
        );

        $consulta = MovimientoInventario::query()
            ->where('sucursal_id', $sucursalId)
            ->with([
                'producto' => fn ($q) => $q->withTrashed(),
                'pieza',
                'estadoOrigen',
                'estadoDestino',
                'usuario' => fn ($q) => $q->withTrashed(),
            ]);

        FiltrosInventario::registros(
            $consulta,
            $filtros,
            'movimientos'
        );

        return $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }
}
