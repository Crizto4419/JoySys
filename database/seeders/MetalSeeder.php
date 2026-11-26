<?php

namespace Database\Seeders;

use App\Models\Metal;
use Illuminate\Database\Seeder;

class MetalSeeder extends Seeder
{
    public function run(): void
    {
        $metales = [
            [
                'nombre' => 'Oro',
                'simbolo' => 'Au',
                'descripcion' => 'Metal precioso utilizado en joyería.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Plata',
                'simbolo' => 'Ag',
                'descripcion' => 'Metal precioso utilizado en joyería.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Platino',
                'simbolo' => 'Pt',
                'descripcion' => 'Metal precioso de alta resistencia a la corrosión.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Paladio',
                'simbolo' => 'Pd',
                'descripcion' => 'Metal precioso del grupo del platino.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Acero inoxidable',
                'simbolo' => null,
                'descripcion' => 'Aleación metálica resistente a la corrosión.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Titanio',
                'simbolo' => 'Ti',
                'descripcion' => 'Metal ligero y resistente utilizado en joyería.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Cobre',
                'simbolo' => 'Cu',
                'descripcion' => 'Metal utilizado en joyería y aleaciones metálicas.',
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Latón',
                'simbolo' => null,
                'descripcion' => 'Aleación compuesta principalmente por cobre y zinc.',
                'estado' => 'activo',
            ],
        ];

        foreach ($metales as $metal) {
            Metal::firstOrCreate(
                [
                    'nombre' => $metal['nombre'],
                ],
                [
                    'simbolo' => $metal['simbolo'],
                    'descripcion' => $metal['descripcion'],
                    'estado' => $metal['estado'],
                ]
            );
        }
    }
}
