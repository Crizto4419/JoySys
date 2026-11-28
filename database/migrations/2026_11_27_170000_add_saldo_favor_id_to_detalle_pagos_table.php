<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            DB::table('detalle_pagos')
                ->where('metodo_pago', 'saldo_favor')
                ->exists()
        ) {
            throw new RuntimeException(
                'Existen partidas de saldo a favor sin credito identificado.'
            );
        }

        Schema::table('detalle_pagos', function (Blueprint $table) {
            $table->foreignId('saldo_favor_id')
                ->nullable()
                ->after('detalle_pago_original_id')
                ->constrained('saldos_favor')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });

        try {
            DB::statement("
                ALTER TABLE detalle_pagos
                ADD CONSTRAINT chk_dp_saldo_favor
                CHECK (
                    (
                        metodo_pago = 'saldo_favor'
                        AND saldo_favor_id IS NOT NULL
                    )
                    OR
                    (
                        metodo_pago <> 'saldo_favor'
                        AND saldo_favor_id IS NULL
                    )
                )
            ");
        } catch (Throwable $e) {
            Schema::table('detalle_pagos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('saldo_favor_id');
            });

            throw $e;
        }
    }

    public function down(): void
    {
        if (
            DB::table('detalle_pagos')
                ->whereNotNull('saldo_favor_id')
                ->exists()
        ) {
            throw new RuntimeException(
                'No se puede eliminar la vinculacion de creditos utilizados.'
            );
        }

        DB::statement(
            'ALTER TABLE detalle_pagos DROP CHECK chk_dp_saldo_favor'
        );

        Schema::table('detalle_pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saldo_favor_id');
        });
    }
};
