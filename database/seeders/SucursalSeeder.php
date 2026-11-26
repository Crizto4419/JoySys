<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    public function run(): void
    {
        Sucursal::firstOrCreate(
            [
                'codigo' => 'SUC-001',
            ],
            [
                'nombre' => 'Sucursal Principal',
                'direccion' => null,
                'telefono' => null,
                'estado' => 'activa',
            ]
        );
    }
}