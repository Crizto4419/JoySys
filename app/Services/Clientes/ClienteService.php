<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClienteService
{
    private const REGISTROS_POR_PAGINA = 15;

    private function autorizar(
        User $usuario,
        string $permiso
    ): void {
        Gate::forUser($usuario)->authorize(
            'joysys.permiso',
            $permiso
        );
    }

    public static function reglasDatos(): array
    {
        return [
            'codigo' => [
                'nullable',
                'string',
                'max:50',
            ],
            'nombre' => [
                'required',
                'string',
                'max:180',
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[0-9() .-]+$/D',
                'regex:/[0-9]/',
            ],
            'correo' => [
                'nullable',
                'string',
                'email:rfc',
                'max:150',
            ],
            'activo' => ['prohibited'],
            'id' => ['prohibited'],
            'sucursal_id' => ['prohibited'],
            'usuario_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public static function reglasConsulta(): array
    {
        return [
            'buscar' => [
                'nullable',
                'string',
                'max:180',
            ],
            'estado' => [
                'nullable',
                'string',
                'in:activo,inactivo',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    private function normalizarDatos(array $datos): array
    {
        foreach ([
            'codigo',
            'nombre',
            'telefono',
            'correo',
        ] as $campo) {
            if (
                array_key_exists($campo, $datos)
                && is_string($datos[$campo])
            ) {
                $datos[$campo] = trim($datos[$campo]);

                if ($datos[$campo] === '') {
                    $datos[$campo] = null;
                }
            }
        }

        $validados = Validator::make(
            $datos,
            self::reglasDatos()
        )->validate();

        return [
            'codigo' => $validados['codigo'] ?? null,
            'nombre' => $validados['nombre'],
            'telefono' => $validados['telefono'] ?? null,
            'correo' => $validados['correo'] ?? null,
        ];
    }

    public function listar(
        User $usuario,
        array $filtros = []
    ): LengthAwarePaginator {
        $this->autorizar($usuario, 'clientes.ver');

        $filtros = Validator::make(
            $filtros,
            self::reglasConsulta()
        )->validate();

        $consulta = Cliente::query();

        $buscar = trim(
            (string) ($filtros['buscar'] ?? '')
        );

        if ($buscar !== '') {
            $patron = '%' . strtr($buscar, [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_',
            ]) . '%';

            $consulta->where(function ($query) use ($patron) {
                $query->whereRaw(
                    "codigo LIKE ? ESCAPE '!'",
                    [$patron]
                )->orWhereRaw(
                    "nombre LIKE ? ESCAPE '!'",
                    [$patron]
                )->orWhereRaw(
                    "telefono LIKE ? ESCAPE '!'",
                    [$patron]
                )->orWhereRaw(
                    "correo LIKE ? ESCAPE '!'",
                    [$patron]
                );
            });
        }

        $estado = $filtros['estado'] ?? null;

        if ($estado === 'activo') {
            $consulta->where('activo', true);
        } elseif ($estado === 'inactivo') {
            $consulta->where('activo', false);
        }

        return $consulta
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(self::REGISTROS_POR_PAGINA)
            ->withQueryString();
    }

    public function obtener(
        Cliente $cliente,
        User $usuario
    ): Cliente {
        $this->autorizar($usuario, 'clientes.ver');

        return Cliente::query()
            ->findOrFail($cliente->id);
    }

    public function crear(
        array $datos,
        User $usuario
    ): Cliente {
        $this->autorizar($usuario, 'clientes.crear');

        $datos = $this->normalizarDatos($datos);

        return DB::transaction(
            function () use ($datos, $usuario) {
                $this->autorizar(
                    $usuario,
                    'clientes.crear'
                );

                return Cliente::query()->create([
                    ...$datos,
                    'activo' => true,
                ]);
            },
            1
        );
    }

    public function actualizar(
        Cliente $cliente,
        array $datos,
        User $usuario
    ): Cliente {
        $this->autorizar($usuario, 'clientes.editar');

        $datos = $this->normalizarDatos($datos);

        return DB::transaction(
            function () use ($cliente, $datos, $usuario) {
                $this->autorizar(
                    $usuario,
                    'clientes.editar'
                );

                $registro = Cliente::query()
                    ->lockForUpdate()
                    ->findOrFail($cliente->id);

                $registro->update($datos);

                return $registro->refresh();
            },
            1
        );
    }

    public function cambiarEstado(
        Cliente $cliente,
        string $estado,
        User $usuario
    ): Cliente {
        $this->autorizar(
            $usuario,
            'clientes.desactivar'
        );

        if (!in_array($estado, [
            'activo',
            'inactivo',
        ], true)) {
            throw ValidationException::withMessages([
                'estado' => 'El estado seleccionado no es valido.',
            ]);
        }

        return DB::transaction(
            function () use ($cliente, $estado, $usuario) {
                $this->autorizar(
                    $usuario,
                    'clientes.desactivar'
                );

                $registro = Cliente::query()
                    ->lockForUpdate()
                    ->findOrFail($cliente->id);

                $activo = $estado === 'activo';

                if ($registro->activo !== $activo) {
                    $registro->update([
                        'activo' => $activo,
                    ]);
                }

                return $registro->refresh();
            },
            1
        );
    }
}
