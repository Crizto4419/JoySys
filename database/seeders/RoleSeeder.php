<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'Administrador',
                'slug' => 'administrador',
                'descripcion' => 'Responsable de la administracion general y configuracion del sistema.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Encargado',
                'slug' => 'encargado',
                'descripcion' => 'Responsable de la supervision y operacion de la sucursal.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Empleado',
                'slug' => 'empleado',
                'descripcion' => 'Responsable de las operaciones comerciales autorizadas.',
                'estado' => 'activo',
            ],
        ];

        foreach ($roles as $rol) {
            Role::firstOrCreate(
                [
                    'slug' => $rol['slug'],
                ],
                [
                    'nombre' => $rol['nombre'],
                    'descripcion' => $rol['descripcion'],
                    'estado' => $rol['estado'],
                ]
            );
        }
    }
}