<?php

namespace App\Services\Caja;

use App\Models\Caja;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\Auth\ContextoSucursalService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CajaService
{
    public const ESTADOS = [
        'activa',
        'inactiva',
        'mantenimiento',
    ];

    public function __construct(
        private readonly ContextoSucursalService $contexto
    ) {}

    public function autorizar(
        User $usuario,
        string $permiso = 'caja.ver'
    ): int {
        Gate::forUser($usuario)->authorize('joysys.permiso', $permiso);

        $sucursalId = $this->contexto->obtenerSucursalId($usuario);

        if ($sucursalId === null) {
            throw new AuthorizationException(
                'Se requiere una sucursal operativa.'
            );
        }

        return $sucursalId;
    }

    public function autorizarAdministracion(User $usuario): int
    {
        Gate::forUser($usuario)->authorize('joysys.caja.administrar');

        return $this->autorizar($usuario);
    }

    public static function reglasDatos(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:40'],
            'nombre' => ['required', 'string', 'max:100'],
            'sucursal_id' => ['prohibited'],
            'moneda' => ['prohibited'],
            'estado' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    private function normalizarDatos(array $datos): array
    {
        foreach (['codigo', 'nombre'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = trim($datos[$campo]);
            }
        }

        $validados = Validator::make(
            $datos,
            self::reglasDatos()
        )->validate();

        return [
            'codigo' => $validados['codigo'],
            'nombre' => $validados['nombre'],
        ];
    }

    private static ?array $contextoTransaccional = null;

    public function tieneBloqueoTransaccional(): bool
    {
        $conexion = DB::connection();

        if (
            $conexion->transactionLevel() < 1
            || self::$contextoTransaccional === null
            || self::$contextoTransaccional['conexion'] !== $conexion
        ) {
            return false;
        }

        $nombre = 'joysys:caja:'
            .sha1($conexion->getDatabaseName());

        $resultado = $conexion->selectOne(
            'SELECT IS_USED_LOCK(?) AS propietario, CONNECTION_ID() AS conexion',
            [$nombre],
            false
        );

        return $resultado !== null
            && (string) $resultado->conexion
                === self::$contextoTransaccional['conexion_id']
            && $resultado->propietario !== null
            && (string) $resultado->propietario
                === (string) $resultado->conexion;
    }

    public function ejecutar(
        User $usuario,
        int $sucursalId,
        Closure $operacion,
        bool $administracion = false,
        string $permiso = 'caja.ver'
    ): mixed {
        $conexion = DB::connection();

        if (
            self::$contextoTransaccional === null
            && $conexion->transactionLevel() > 0
            && !(
                app()->environment('testing')
                && $conexion->getDatabaseName() === 'joysys_testing'
                && $conexion->transactionLevel() >= 1
            )
        ) {
            throw new \LogicException(
                'CajaService debe controlar la transaccion exterior.'
            );
        }

        if (self::$contextoTransaccional !== null) {
            if (
                !$this->tieneBloqueoTransaccional()
                || self::$contextoTransaccional['usuario_id']
                    !== (int) $usuario->id
                || self::$contextoTransaccional['sucursal_id']
                    !== $sucursalId
            ) {
                throw new AuthorizationException(
                    'El contexto transaccional no corresponde al actor.'
                );
            }
        }

        $bloqueo = 'joysys:caja:'.sha1(
            $conexion->getDatabaseName()
        );

        $resultado = $conexion->selectOne(
            'SELECT GET_LOCK(?, 10) AS adquirido',
            [$bloqueo],
            false
        );

        if ((int) ($resultado->adquirido ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'caja' => 'El mÃƒÂ³dulo de caja estÃƒÂ¡ ocupado. Intenta nuevamente.',
            ]);
        }

        $contextoAnterior = self::$contextoTransaccional;

        try {
            if ($contextoAnterior === null) {
                $identidad = $conexion->selectOne(
                    'SELECT CONNECTION_ID() AS conexion',
                    [],
                    false
                );

                self::$contextoTransaccional = [
                    'conexion' => $conexion,
                    'conexion_id' => (string) $identidad->conexion,
                    'usuario_id' => (int) $usuario->id,
                    'sucursal_id' => $sucursalId,
                ];
            }

            return $conexion->transaction(
                function () use (
                    $usuario,
                    $sucursalId,
                    $operacion,
                    $administracion,
                    $permiso
                ) {
                    $actual = $administracion
                        ? $this->autorizarAdministracion($usuario)
                        : $this->autorizar($usuario, $permiso);

                    if ($actual !== $sucursalId) {
                        throw new AuthorizationException(
                            'El contexto de sucursal ha cambiado.'
                        );
                    }

                    return $operacion();
                },
                1
            );
        } finally {
            self::$contextoTransaccional = $contextoAnterior;

            $conexion->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$bloqueo],
                false
            );
        }
    }

    public function listar(
        User $usuario,
        array $filtros = []
    ): LengthAwarePaginator {
        $sucursalId = $this->autorizar($usuario);

        $filtros = Validator::make($filtros, [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $consulta = Caja::query()
            ->where('sucursal_id', $sucursalId)
            ->withCount([
                'sesiones as sesiones_abiertas_count' => function ($query) {
                    $query->where('estado', 'abierta');
                },
            ]);

        $buscar = trim($filtros['buscar'] ?? '');

        if ($buscar !== '') {
            $patron = '%'.str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $buscar
            ).'%';

            $consulta->where(function ($query) use ($patron) {
                $query
                    ->whereRaw("codigo LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereRaw("nombre LIKE ? ESCAPE '!'", [$patron]);
            });
        }

        if (! empty($filtros['estado'])) {
            $consulta->where('estado', $filtros['estado']);
        }

        return $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function obtener(Caja $caja, User $usuario): Caja
    {
        return Caja::query()
            ->where('sucursal_id', $this->autorizar($usuario))
            ->withCount([
                'sesiones as sesiones_abiertas_count' => function ($query) {
                    $query->where('estado', 'abierta');
                },
            ])
            ->findOrFail($caja->getKey());
    }

    public function disponibles(User $usuario)
    {
        $sucursalId = $this->autorizar($usuario, 'caja.abrir');

        return Caja::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activa')
            ->where('moneda', 'MXN')
            ->whereDoesntHave('sesiones', function ($query) {
                $query->where('estado', 'abierta');
            })
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
    }

    private function verificarCodigo(
        int $sucursalId,
        string $codigo,
        ?int $excluir = null
    ): void {
        $consulta = Caja::query()
            ->where('sucursal_id', $sucursalId)
            ->where('codigo', $codigo);

        if ($excluir !== null) {
            $consulta->where('id', '!=', $excluir);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'codigo' => 'El cÃƒÂ³digo ya existe en esta sucursal.',
            ]);
        }
    }

    private function verificarSinSesion(Caja $caja): void
    {
        if ($caja->sesiones()->where('estado', 'abierta')->exists()) {
            throw ValidationException::withMessages([
                'caja' => 'La caja tiene una sesiÃƒÂ³n abierta.',
            ]);
        }
    }

    public function crear(array $datos, User $usuario): Caja
    {
        $sucursalId = $this->autorizarAdministracion($usuario);
        $datos = $this->normalizarDatos($datos);

        try {
            return $this->ejecutar(
                $usuario,
                $sucursalId,
                function () use ($datos, $sucursalId) {
                    $this->verificarCodigo($sucursalId, $datos['codigo']);

                    return Caja::query()->create([
                        'sucursal_id' => $sucursalId,
                        'codigo' => $datos['codigo'],
                        'nombre' => $datos['nombre'],
                        'moneda' => 'MXN',
                        'estado' => 'activa',
                    ]);
                },
                true
            );
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw ValidationException::withMessages([
                    'codigo' => 'El cÃƒÂ³digo ya existe en esta sucursal.',
                ]);
            }

            throw $exception;
        }
    }

    public function actualizar(
        Caja $caja,
        array $datos,
        User $usuario
    ): Caja {
        $sucursalId = $this->autorizarAdministracion($usuario);
        $datos = $this->normalizarDatos($datos);

        try {
            return $this->ejecutar(
                $usuario,
                $sucursalId,
                function () use ($caja, $datos, $sucursalId) {
                    $actual = Caja::query()
                        ->where('sucursal_id', $sucursalId)
                        ->lockForUpdate()
                        ->findOrFail($caja->getKey());

                    $this->verificarSinSesion($actual);

                    if (
                        $actual->codigo !== $datos['codigo']
                        && $actual->sesiones()->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'codigo' => 'El cÃƒÂ³digo no puede cambiar despuÃƒÂ©s de registrar sesiones.',
                        ]);
                    }

                    $this->verificarCodigo(
                        $sucursalId,
                        $datos['codigo'],
                        (int) $actual->getKey()
                    );

                    $actual->update($datos);

                    return $actual->refresh();
                },
                true
            );
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw ValidationException::withMessages([
                    'codigo' => 'El cÃƒÂ³digo ya existe en esta sucursal.',
                ]);
            }

            throw $exception;
        }
    }

    public function cambiarEstado(
        Caja $caja,
        string $estado,
        User $usuario
    ): Caja {
        $sucursalId = $this->autorizarAdministracion($usuario);

        Validator::make(
            ['estado' => $estado],
            ['estado' => ['required', Rule::in(self::ESTADOS)]]
        )->validate();

        return $this->ejecutar(
            $usuario,
            $sucursalId,
            function () use ($caja, $estado, $sucursalId) {
                $actual = Caja::query()
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->findOrFail($caja->getKey());

                if ($actual->estado === $estado) {
                    return $actual;
                }

                $this->verificarSinSesion($actual);

                $actual->update(['estado' => $estado]);

                return $actual->refresh();
            },
            true
        );
    }
}
