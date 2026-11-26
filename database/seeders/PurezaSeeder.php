<?php

namespace Database\Seeders;

use App\Models\Metal;
use App\Models\Pureza;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurezaSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = [
            'Oro' => [
                [
                    'codigo' => '8K',
                    'nombre' => 'Oro 8 quilates',
                ],
                [
                    'codigo' => '9K',
                    'nombre' => 'Oro 9 quilates',
                ],
                [
                    'codigo' => '10K',
                    'nombre' => 'Oro 10 quilates',
                ],
                [
                    'codigo' => '12K',
                    'nombre' => 'Oro 12 quilates',
                ],
                [
                    'codigo' => '14K',
                    'nombre' => 'Oro 14 quilates',
                ],
                [
                    'codigo' => '18K',
                    'nombre' => 'Oro 18 quilates',
                ],
                [
                    'codigo' => '21K',
                    'nombre' => 'Oro 21 quilates',
                ],
                [
                    'codigo' => '22K',
                    'nombre' => 'Oro 22 quilates',
                ],
                [
                    'codigo' => '24K',
                    'nombre' => 'Oro 24 quilates',
                ],
            ],

            'Plata' => [
                [
                    'codigo' => '800',
                    'nombre' => 'Plata .800',
                ],
                [
                    'codigo' => '900',
                    'nombre' => 'Plata .900',
                ],
                [
                    'codigo' => '925',
                    'nombre' => 'Plata .925',
                ],
                [
                    'codigo' => '950',
                    'nombre' => 'Plata .950',
                ],
                [
                    'codigo' => '999',
                    'nombre' => 'Plata .999',
                ],
            ],
        ];

        DB::transaction(function () use ($catalogo): void {
            $metales = Metal::query()
                ->whereIn('nombre', array_keys($catalogo))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('nombre');

            foreach ($catalogo as $nombreMetal => $purezas) {
                $metal = $metales->get($nombreMetal);

                if ($metal === null) {
                    throw new RuntimeException(
                        "No se encontró el metal requerido: {$nombreMetal}. "
                        . 'Ejecuta MetalSeeder antes de PurezaSeeder.'
                    );
                }

                foreach ($purezas as $pureza) {
                    Pureza::firstOrCreate(
                        [
                            'metal_id' => $metal->id,
                            'codigo' => $pureza['codigo'],
                        ],
                        [
                            'nombre' => $pureza['nombre'],
                        ]
                    );
                }
            }
        });
    }
}
