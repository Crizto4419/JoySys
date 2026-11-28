<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PermisoReembolsoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = Role::query()
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
                    'Los tres roles oficiales deben existir.'
                );
            }

            $permiso = Permiso::firstOrCreate(
                ['clave' => 'pagos.reembolsar'],
                [
                    'nombre' => 'Autorizar reembolsos',
                    'modulo' => 'pagos',
                    'descripcion' => null,
                ]
            );

            foreach (['administrador', 'encargado'] as $slug) {
                $criterio = [
                    'rol_id' => $roles->get($slug)->id,
                    'permiso_id' => $permiso->id,
                ];

                if (!DB::table('rol_permiso')
                    ->where($criterio)->exists()) {
                    DB::table('rol_permiso')->insert([
                        ...$criterio,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (DB::table('rol_permiso')
                ->where('rol_id', $roles->get('empleado')->id)
                ->where('permiso_id', $permiso->id)
                ->exists()) {
                throw new RuntimeException(
                    'El rol empleado contiene una asignacion incompatible.'
                );
            }
        });
    }
}