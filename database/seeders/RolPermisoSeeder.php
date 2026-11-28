<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RolPermisoSeeder extends Seeder
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
                    'Los tres roles oficiales deben existir antes de asignar permisos.'
                );
            }

            $administrador = [];

            foreach (PermisoSeeder::PERMISOS as $permisos) {
                $administrador = array_merge(
                    $administrador,
                    array_keys($permisos)
                );
            }

            $empleado = [
                'dashboard.ver',

                'catalogos.ver',

                'inventario.ver',

                'incidencias.ver',
                'incidencias.reportar',

                'precios.ver',

                'clientes.ver',
                'clientes.crear',
                'clientes.editar',

                'ventas.ver',
                'ventas.crear',
                'ventas.descuento_basico',

                'pagos.ver',
                'pagos.registrar',

                'caja.ver',
                'caja.abrir',
                'caja.cerrar',
            ];

            $encargado = array_merge(
                $empleado,
                [
                    'sucursales.ver',
                    'ventas.descuento_superior',
                    'pagos.reembolsar',
            'saldos_favor.generar',
                    'caja.registrar_gasto',
                    'caja.registrar_retiro',
                    'reportes.ver',
                ]
            );

            $asignaciones = [
                'administrador' => $administrador,
                'encargado' => $encargado,
                'empleado' => $empleado,
            ];

            $permisosRegistrados = Permiso::query()
                ->whereIn('clave', $administrador)
                ->pluck('id', 'clave');

            if ($permisosRegistrados->count() !== count($administrador)) {
                throw new RuntimeException(
                    'El catalogo oficial de permisos esta incompleto.'
                );
            }

            if (DB::table('rol_permiso')->exists()) {
                $this->command?->warn(
                    'La matriz de permisos ya contiene registros. Se conservaran las asignaciones existentes.'
                );

                return;
            }

            $fecha = now();

            $registros = [];

            foreach ($asignaciones as $slug => $claves) {
                $rol = $roles->get($slug);

                foreach ($claves as $clave) {
                    if (! $permisosRegistrados->has($clave)) {
                        throw new RuntimeException(
                            "No existe el permiso requerido: {$clave}"
                        );
                    }

                    $registros[] = [
                        'rol_id' => $rol->id,
                        'permiso_id' => $permisosRegistrados->get($clave),
                        'created_at' => $fecha,
                        'updated_at' => $fecha,
                    ];
                }
            }

            DB::table('rol_permiso')->insert($registros);
        });
    }
}