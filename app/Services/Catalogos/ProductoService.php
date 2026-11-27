<?php

namespace App\Services\Catalogos;

use App\Models\Categoria;
use App\Models\Metal;
use App\Models\Pureza;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\User;
use App\Services\Precios\PrecioService;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductoService
{
    public const TIPOS = [
        'individual',
        'por_peso',
        'por_cantidad',
    ];

    public function __construct(
        private readonly PrecioService $precioService,
        private readonly CategoriaService $categoriaService,
        private readonly MetalService $metalService,
        private readonly PurezaService $purezaService
    ) {
    }

    public function listar(
        array $filtros = []
    ): LengthAwarePaginator {
        $consulta = Producto::query()->with([
            'categoria',
            'metal',
            'pureza',
            'proveedor',
        ]);

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

        if (in_array(
            $filtros['tipo'] ?? null,
            self::TIPOS,
            true
        )) {
            $consulta->where('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['categoria_id'])) {
            $consulta->where(
                'categoria_id',
                $filtros['categoria_id']
            );
        }

        if (!empty($filtros['metal_id'])) {
            $consulta->where(
                'metal_id',
                $filtros['metal_id']
            );
        }

        return $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function opciones(
        ?Producto $producto = null
    ): array {
        $categorias = $this->categoriaService
            ->obtenerPadresDisponibles();

        $metales = $this->metalService->obtenerActivos();

        $purezas = $this->purezaService->obtenerDisponibles();

        $proveedores = Proveedor::query()
            ->where('estado', 'activo')
            ->orderBy('nombre_comercial')
            ->orderBy('id')
            ->get();

        if ($producto?->exists) {
            $categoria = Categoria::withTrashed()
                ->find($producto->categoria_id);

            if ($categoria && !$categorias->contains('id', $categoria->id)) {
                $categorias->push($categoria);
            }

            $metal = Metal::find($producto->metal_id);

            if ($metal && !$metales->contains('id', $metal->id)) {
                $metales->push($metal);
            }

            $pureza = Pureza::with('metal')
                ->find($producto->pureza_id);

            if ($pureza && !$purezas->contains('id', $pureza->id)) {
                $purezas->push($pureza);
            }

            $proveedor = Proveedor::withTrashed()
                ->find($producto->proveedor_id);

            if ($proveedor && !$proveedores->contains('id', $proveedor->id)) {
                $proveedores->push($proveedor);
            }
        }

        return [
            'categorias' => $categorias->sortBy('nombre')->values(),
            'metales' => $metales->sortBy('nombre')->values(),
            'purezas' => $purezas,
            'proveedores' => $proveedores
                ->sortBy('nombre_comercial')
                ->values(),
            'tipos' => [
                'individual' => 'Por pieza individual',
                'por_peso' => 'Por peso',
                'por_cantidad' => 'Por cantidad',
            ],
        ];
    }

    public function crear(
        array $datos,
        User $usuario
    ): Producto {
        Gate::forUser($usuario)->authorize(
            'joysys.permiso',
            'catalogos.crear'
        );

        return $this->ejecutarConBloqueo(
            function () use ($datos, $usuario) {
                $valores = $this->normalizar($datos);

                $this->validarCatalogos($valores);
                $this->validarCodigo($valores['codigo']);

                $precio = $this->precioService->normalizarImporte(
                    $datos['precio_base'] ?? '',
                    'precio_base'
                );

                $producto = Producto::create(array_merge(
                    $valores,
                    [
                        'estado' => 'activo',
                        'precio_base' => $precio,
                    ]
                ));

                $this->precioService->inicializar(
                    $producto,
                    $precio,
                    $usuario
                );

                return $producto->refresh();
            }
        );
    }

    public function actualizar(
        Producto $producto,
        array $datos,
        User $usuario
    ): Producto {
        Gate::forUser($usuario)->authorize(
            'joysys.permiso',
            'catalogos.editar'
        );

        return $this->ejecutarConBloqueo(
            function () use ($producto, $datos) {
                $actual = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                $valores = $this->normalizar($datos);

                if ($this->tieneReferencias($actual)) {
                    foreach ([
                        'codigo',
                        'tipo',
                        'metal_id',
                        'pureza_id',
                    ] as $campo) {
                        if ($actual->{$campo} !== $valores[$campo]) {
                            throw ValidationException::withMessages([
                                $campo => 'Este campo esta protegido por referencias existentes.',
                            ]);
                        }
                    }
                }

                $this->validarCatalogos($valores, $actual);
                $this->validarCodigo(
                    $valores['codigo'],
                    $actual->id
                );

                $actual->update($valores);

                return $actual->refresh();
            }
        );
    }

    public function cambiarEstado(
        Producto $producto,
        string $estado,
        User $usuario
    ): Producto {
        Gate::forUser($usuario)->authorize(
            'joysys.permiso',
            'catalogos.cambiar_estado'
        );

        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            throw ValidationException::withMessages([
                'estado' => 'El estado seleccionado no es valido.',
            ]);
        }

        return $this->ejecutarConBloqueo(
            function () use ($producto, $estado) {
                $actual = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                if ($actual->estado === $estado) {
                    return $actual;
                }

                if (
                    $estado === 'inactivo' &&
                    $this->tieneInventarioOperativo($actual)
                ) {
                    throw ValidationException::withMessages([
                        'estado' => 'No se puede desactivar un producto con inventario operativo.',
                    ]);
                }

                if ($estado === 'activo') {
                    $this->validarCatalogos([
                        'categoria_id' => $actual->categoria_id,
                        'metal_id' => $actual->metal_id,
                        'pureza_id' => $actual->pureza_id,
                        'proveedor_id' => $actual->proveedor_id,
                    ]);

                    if (!$actual->precioVigente()->exists()) {
                        throw ValidationException::withMessages([
                            'estado' => 'El producto necesita un precio vigente.',
                        ]);
                    }
                }

                $actual->update(['estado' => $estado]);

                return $actual->refresh();
            }
        );
    }

    public function tieneReferencias(
        Producto $producto
    ): bool {
        return $producto->piezas()->exists()
            || $producto->existencias()->exists()
            || $producto->movimientosInventario()->exists()
            || $producto->incidenciasInventario()->exists()
            || $producto->detallesVenta()->exists();
    }

    public function tieneInventarioOperativo(
        Producto $producto
    ): bool {
        $estadosFinales = DB::table('estados_inventario')
            ->whereIn('codigo', ['vendido', 'perdido'])
            ->pluck('id')
            ->all();

        return $producto->piezas()
            ->whereNotIn('estado_inventario_id', $estadosFinales)
            ->exists()
            || $producto->existencias()
                ->where('cantidad', '>', 0)
                ->whereNotIn('estado_inventario_id', $estadosFinales)
                ->exists();
    }

    private function normalizar(array $datos): array
    {
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $nombre = trim((string) ($datos['nombre'] ?? ''));

        if ($codigo === '' || mb_strlen($codigo) > 60) {
            throw ValidationException::withMessages([
                'codigo' => 'Introduce un codigo valido de hasta 60 caracteres.',
            ]);
        }

        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw ValidationException::withMessages([
                'nombre' => 'Introduce un nombre valido de hasta 150 caracteres.',
            ]);
        }

        $tipo = $datos['tipo'] ?? '';

        if (!in_array($tipo, self::TIPOS, true)) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de producto no es valido.',
            ]);
        }

        $descripcion = trim((string) ($datos['descripcion'] ?? ''));

        if (strlen($descripcion) > 65535) {
            throw ValidationException::withMessages([
                'descripcion' => 'La descripcion supera el limite permitido.',
            ]);
        }

        return [
            'categoria_id' => $this->normalizarId(
                $datos['categoria_id'] ?? null,
                'categoria_id',
                false
            ),
            'proveedor_id' => $this->normalizarId(
                $datos['proveedor_id'] ?? null,
                'proveedor_id'
            ),
            'metal_id' => $this->normalizarId(
                $datos['metal_id'] ?? null,
                'metal_id'
            ),
            'pureza_id' => $this->normalizarId(
                $datos['pureza_id'] ?? null,
                'pureza_id'
            ),
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $descripcion === '' ? null : $descripcion,
            'tipo' => $tipo,
            'stock_minimo' => $this->precioService->normalizarImporte(
                $datos['stock_minimo'] ?? '0',
                'stock_minimo',
                true
            ),
        ];
    }

    private function normalizarId(
        mixed $valor,
        string $campo,
        bool $opcional = true
    ): ?int {
        if ($opcional && ($valor === null || $valor === '')) {
            return null;
        }

        if (
            (!is_int($valor) && !is_string($valor)) ||
            !preg_match('/^[1-9][0-9]*$/D', (string) $valor) ||
            filter_var(
                $valor,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false
        ) {
            throw ValidationException::withMessages([
                $campo => 'El identificador seleccionado no es valido.',
            ]);
        }

        return (int) $valor;
    }

    private function conservaReferencia(
        ?Producto $producto,
        string $campo,
        ?int $valor
    ): bool {
        return $producto !== null
            && $producto->estado === 'inactivo'
            && $producto->{$campo} === $valor;
    }

    private function validarCodigo(
        string $codigo,
        ?int $excepto = null
    ): void {
        $consulta = Producto::withTrashed()
            ->where('codigo', $codigo);

        if ($excepto !== null) {
            $consulta->where('id', '<>', $excepto);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'codigo' => 'El codigo ya esta registrado.',
            ]);
        }
    }

    private function validarCatalogos(
        array $datos,
        ?Producto $producto = null
    ): void {
        $visitados = [];

        $categoriaId = $this->conservaReferencia(
            $producto,
            'categoria_id',
            $datos['categoria_id']
        ) ? null : $datos['categoria_id'];

        while ($categoriaId !== null) {
            if (isset($visitados[$categoriaId])) {
                throw ValidationException::withMessages([
                    'categoria_id' => 'La jerarquia de categorias contiene un ciclo.',
                ]);
            }

            $visitados[$categoriaId] = true;

            $categoria = Categoria::query()
                ->lockForUpdate()
                ->find($categoriaId);

            if (!$categoria || $categoria->estado !== 'activa') {
                throw ValidationException::withMessages([
                    'categoria_id' => 'Selecciona una categoria con jerarquia activa.',
                ]);
            }

            $categoriaId = $categoria->categoria_padre_id;
        }

        if (
            $datos['metal_id'] !== null &&
            !$this->conservaReferencia(
                $producto,
                'metal_id',
                $datos['metal_id']
            )
        ) {
            $metal = Metal::query()
                ->lockForUpdate()
                ->find($datos['metal_id']);

            if (!$metal || $metal->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'metal_id' => 'Selecciona un metal activo.',
                ]);
            }
        }

        if ($datos['pureza_id'] !== null) {
            $pureza = Pureza::query()
                ->with('metal')
                ->lockForUpdate()
                ->find($datos['pureza_id']);

            $conservaPureza = $this->conservaReferencia(
                $producto,
                'pureza_id',
                $datos['pureza_id']
            ) && $this->conservaReferencia(
                $producto,
                'metal_id',
                $datos['metal_id']
            );

            if (
                $pureza &&
                !$conservaPureza &&
                $pureza->metal?->estado !== 'activo'
            ) {
                throw ValidationException::withMessages([
                    'pureza_id' => 'Selecciona una pureza de un metal activo.',
                ]);
            }

            if (
                !$pureza ||
                $datos['metal_id'] === null ||
                (int) $pureza->metal_id !== (int) $datos['metal_id']
            ) {
                throw ValidationException::withMessages([
                    'pureza_id' => 'La pureza debe corresponder al metal seleccionado.',
                ]);
            }
        }

        if (
            $datos['proveedor_id'] !== null &&
            !$this->conservaReferencia(
                $producto,
                'proveedor_id',
                $datos['proveedor_id']
            )
        ) {
            $proveedor = Proveedor::query()
                ->lockForUpdate()
                ->find($datos['proveedor_id']);

            if (!$proveedor || $proveedor->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'proveedor_id' => 'Selecciona un proveedor activo.',
                ]);
            }
        }
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
                'producto' => 'El catalogo esta siendo actualizado. Intenta nuevamente.',
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
