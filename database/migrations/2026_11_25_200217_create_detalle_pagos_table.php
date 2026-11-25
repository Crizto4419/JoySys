
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detalle_pagos', function (Blueprint $table) {
            $table->id();

            // Relaciones financieras.
            $table->unsignedBigInteger('pago_id');

            // Información del componente financiero.
            $table->string('metodo_pago', 30);
            $table->decimal('monto', 12, 4);
            $table->string('referencia', 150)->nullable();
            $table->string('detalle_adicional', 255)->nullable();

            // Trazabilidad de reembolsos y compensaciones.
            $table->unsignedBigInteger('detalle_pago_original_id')
                ->nullable();

            // Registro temporal.
            $table->timestamp('created_at')->nullable();

            // Índices físicos.
            $table->index(
                'pago_id',
                'detalle_pagos_pago_id_index'
            );

            $table->index(
                'metodo_pago',
                'detalle_pagos_metodo_pago_index'
            );

            $table->index(
                'detalle_pago_original_id',
                'detalle_pagos_detalle_pago_original_id_index'
            );
        });

        // Restricciones CHECK.
        DB::statement("
            ALTER TABLE detalle_pagos
            ADD CONSTRAINT detalle_pagos_monto_check
                CHECK (monto > 0),
            ADD CONSTRAINT detalle_pagos_metodo_pago_check
                CHECK (
                    metodo_pago IN (
                        'efectivo',
                        'tarjeta_credito',
                        'tarjeta_debito',
                        'transferencia',
                        'saldo_favor',
                        'otro'
                    )
                )
        ");

        // Claves foráneas.
        Schema::table('detalle_pagos', function (Blueprint $table) {
            $table->foreign(
                'pago_id',
                'detalle_pagos_pago_id_foreign'
            )
                ->references('id')
                ->on('pagos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign(
                'detalle_pago_original_id',
                'detalle_pagos_detalle_pago_original_id_foreign'
            )
                ->references('id')
                ->on('detalle_pagos')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_pagos');
    }
};
