<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CajaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $sucursal = Sucursal::query()
                ->where('codigo', 'SUC-001')
                ->lockForUpdate()
                ->first();

            if (!$sucursal) {
                throw new RuntimeException(
                    'No existe la sucursal principal SUC-001.'
                );
            }

            $caja = Caja::query()
                ->where('sucursal_id', $sucursal->id)
                ->where('codigo', 'CAJ-001')
                ->first();

            if ($caja) {
                $this->command?->info(
                    'La caja principal ya existe. Se conservara su configuracion.'
                );

                return;
            }

            if ($sucursal->estado !== 'activa') {
                throw new RuntimeException(
                    'No se puede inicializar una caja en una sucursal inactiva.'
                );
            }

            Caja::create([
                'sucursal_id' => $sucursal->id,
                'codigo' => 'CAJ-001',
                'nombre' => 'Caja Principal',
                'moneda' => 'MXN',
                'estado' => 'activa',
            ]);

            $this->command?->info(
                'Caja principal CAJ-001 creada correctamente.'
            );
        });
    }
}
