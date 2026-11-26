<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Bloque 1: Seguridad y sucursales.
            SucursalSeeder::class,
            RoleSeeder::class,
            PermisoSeeder::class,
            RolPermisoSeeder::class,

            // Bloque 2: Catalogos de inventario.
            EstadoInventarioSeeder::class,
            MetalSeeder::class,
            PurezaSeeder::class,

            // Bloque 3: Inicializacion operativa.
            AdministradorSeeder::class,
            EmpleadoSucursalSeeder::class,
            CajaSeeder::class,
        ]);
    }
}
