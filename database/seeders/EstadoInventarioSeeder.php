<?php

namespace Database\Seeders;

use App\Models\EstadoInventario;
use Illuminate\Database\Seeder;

class EstadoInventarioSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            [
                'codigo' => 'disponible',
                'nombre' => 'Disponible',
                'descripcion' => 'Inventario disponible para venta.',
            ],
            [
                'codigo' => 'apartado',
                'nombre' => 'Apartado',
                'descripcion' => 'Inventario reservado para un cliente.',
            ],
            [
                'codigo' => 'vendido',
                'nombre' => 'Vendido',
                'descripcion' => 'Inventario comercializado.',
            ],
            [
                'codigo' => 'reparacion',
                'nombre' => 'Reparación',
                'descripcion' => 'Inventario en proceso de reparación.',
            ],
            [
                'codigo' => 'danado',
                'nombre' => 'Dañado',
                'descripcion' => 'Inventario con daños físicos.',
            ],
            [
                'codigo' => 'perdido',
                'nombre' => 'Perdido',
                'descripcion' => 'Inventario reportado como perdido.',
            ],
        ];

        foreach ($estados as $estado) {
            EstadoInventario::firstOrCreate(
                [
                    'codigo' => $estado['codigo'],
                ],
                [
                    'nombre' => $estado['nombre'],
                    'descripcion' => $estado['descripcion'],
                ]
            );
        }
    }
}
