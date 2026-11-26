<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdministradorSeeder extends Seeder
{
    public const USERNAME = 'admin';

    public const CODIGO_EMPLEADO = 'EMP-001';

    public const EMAIL = 'admin@joysys.test';

    public function run(): void
    {
        $rol = Role::query()
            ->where('slug', 'administrador')
            ->first();

        if (!$rol || $rol->estado !== 'activo') {
            throw new RuntimeException(
                'No existe un rol administrador activo.'
            );
        }

        if ($this->administradorExistente($rol)) {
            $this->command?->info(
                'El administrador inicial ya existe. Se conservaran sus datos.'
            );

            return;
        }

        if (
            !$this->command
            || $this->command->option('no-interaction')
        ) {
            throw new RuntimeException(
                'La creacion del administrador requiere una terminal interactiva.'
            );
        }

        $this->command->info(
            'Inicializacion del administrador de JoySys'
        );

        $password = $this->command->secret(
            'Introduce la contraseña del administrador',
            false
        );

        if (
            !is_string($password)
            || mb_strlen($password) < 12
            || strlen($password) > 72
        ) {
            throw new RuntimeException(
                'La contraseña debe tener al menos 12 caracteres y no superar 72 bytes.'
            );
        }

        $confirmacion = $this->command->secret(
            'Confirma la contraseña',
            false
        );

        if ($password !== $confirmacion) {
            throw new RuntimeException(
                'Las contraseñas no coinciden.'
            );
        }

        $creado = DB::transaction(function () use ($password) {
            $rol = Role::query()
                ->where('slug', 'administrador')
                ->lockForUpdate()
                ->first();

            if (!$rol || $rol->estado !== 'activo') {
                throw new RuntimeException(
                    'El rol administrador no esta disponible.'
                );
            }

            if ($this->administradorExistente($rol)) {
                return false;
            }

            $administrador = new User();

            $administrador->forceFill([
                'username' => self::USERNAME,
                'name' => 'Administrador',
                'apellidos' => 'JoySys',
                'codigo_empleado' => self::CODIGO_EMPLEADO,
                'telefono' => null,
                'email' => self::EMAIL,
                'email_verified_at' => null,
                'password' => $password,
                'role_id' => $rol->id,
                'estado' => 'activo',
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
                'ultimo_acceso' => null,
                'remember_token' => null,
                'deleted_at' => null,
            ]);

            $administrador->save();

            return true;
        });

        unset($password, $confirmacion);

        $this->command->info(
            $creado
                ? 'Administrador inicial creado correctamente.'
                : 'El administrador inicial ya existe. Se conservaron sus datos.'
        );
    }

    private function administradorExistente(Role $rol): ?User
    {
        $usuarios = User::withTrashed()
            ->where(function ($consulta) {
                $consulta
                    ->where('username', self::USERNAME)
                    ->orWhere(
                        'codigo_empleado',
                        self::CODIGO_EMPLEADO
                    )
                    ->orWhere('email', self::EMAIL);
            })
            ->get();

        if ($usuarios->isEmpty()) {
            return null;
        }

        if ($usuarios->count() !== 1) {
            throw new RuntimeException(
                'Existe un conflicto con los identificadores del administrador inicial.'
            );
        }

        $usuario = $usuarios->first();

        if (
            $usuario->username !== self::USERNAME
            || $usuario->codigo_empleado !== self::CODIGO_EMPLEADO
            || (int) $usuario->role_id !== (int) $rol->id
            || $usuario->trashed()
            || $usuario->estado !== 'activo'
        ) {
            throw new RuntimeException(
                'El usuario encontrado no corresponde a un administrador inicial activo. No se modificaron sus datos.'
            );
        }

        return $usuario;
    }
}
