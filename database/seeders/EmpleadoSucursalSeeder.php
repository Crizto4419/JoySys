<?php

namespace Database\Seeders;

use App\Models\EmpleadoSucursal;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmpleadoSucursalSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $rol = Role::query()
                ->where('slug', 'administrador')
                ->first();

            if (!$rol || $rol->estado !== 'activo') {
                throw new RuntimeException(
                    'No existe un rol administrador activo.'
                );
            }

            $administrador = User::query()
                ->where(
                    'username',
                    AdministradorSeeder::USERNAME
                )
                ->where(
                    'codigo_empleado',
                    AdministradorSeeder::CODIGO_EMPLEADO
                )
                ->where('role_id', $rol->id)
                ->lockForUpdate()
                ->first();

            if (
                !$administrador
                || $administrador->estado !== 'activo'
            ) {
                throw new RuntimeException(
                    'No existe un administrador inicial activo.'
                );
            }

            $sucursal = Sucursal::query()
                ->where('codigo', 'SUC-001')
                ->first();

            if (!$sucursal) {
                throw new RuntimeException(
                    'No existe la sucursal principal SUC-001.'
                );
            }

            $asignaciones = EmpleadoSucursal::query()
                ->where('usuario_id', $administrador->id)
                ->lockForUpdate()
                ->get();

            if ($asignaciones->isNotEmpty()) {
                $this->command?->warn(
                    'El administrador ya tiene historial de asignaciones. Se conservaran los registros existentes.'
                );

                return;
            }

            if ($sucursal->estado !== 'activa') {
                throw new RuntimeException(
                    'La sucursal principal no esta activa.'
                );
            }

            EmpleadoSucursal::create([
                'usuario_id' => $administrador->id,
                'sucursal_id' => $sucursal->id,
                'es_principal' => true,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => null,
                'estado' => 'activo',
                'asignado_por' => $administrador->id,
            ]);

            $this->command?->info(
                'Administrador asignado correctamente a SUC-001.'
            );
        });
    }
}
