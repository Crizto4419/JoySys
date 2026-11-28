<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PermisosSaldoFavorSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = DB::table('roles')
                ->whereIn('slug', [
                    'administrador',
                    'encargado',
                    'empleado',
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('slug');

            if ($roles->count() !== 3) {
                throw new RuntimeException(
                    'No se encontraron los tres roles base.'
                );
            }

            $definiciones = [
                'saldos_favor.generar' => [
                    'nombre' => 'Generar saldos a favor autorizados',
                    'roles' => ['administrador', 'encargado'],
                ],
                'saldos_favor.cancelar' => [
                    'nombre' => 'Cancelar saldos a favor',
                    'roles' => ['administrador'],
                ],
            ];

            foreach ($definiciones as $clave => $definicion) {
                $permiso = DB::table('permisos')
                    ->where('clave', $clave)
                    ->lockForUpdate()
                    ->first();

                if ($permiso === null) {
                    $permisoId = DB::table('permisos')->insertGetId([
                        'clave' => $clave,
                        'nombre' => $definicion['nombre'],
                        'modulo' => 'saldos_favor',
                        'descripcion' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    if ($permiso->modulo !== 'saldos_favor') {
                        throw new RuntimeException(
                            'Modulo incompatible: '.$clave
                        );
                    }

                    $permisoId = $permiso->id;
                }

                foreach ($roles as $slug => $rol) {
                    $existe = DB::table('rol_permiso')
                        ->where('rol_id', $rol->id)
                        ->where('permiso_id', $permisoId)
                        ->exists();

                    $autorizado = in_array(
                        $slug,
                        $definicion['roles'],
                        true
                    );

                    if (!$autorizado && $existe) {
                        throw new RuntimeException(
                            'Asignacion incompatible: '.$slug.' / '.$clave
                        );
                    }

                    if ($autorizado && !$existe) {
                        DB::table('rol_permiso')->insert([
                            'rol_id' => $rol->id,
                            'permiso_id' => $permisoId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }
}
