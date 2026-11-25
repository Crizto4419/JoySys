
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sucursal_id');

            $table->string('referencia_tipo', 50);
            $table->unsignedBigInteger('referencia_id');

            $table->unsignedBigInteger('pago_original_id')
                ->nullable();

            $table->unsignedBigInteger('recibido_por');

            $table->decimal('monto_total', 12, 4);

            $table->char('moneda', 3)
                ->default('MXN');

            $table->string('estado', 30)
                ->default('pendiente');

            $table->timestamp('fecha_pago')
                ->nullable();

            $table->timestamp('created_at')
                ->nullable();

            $table->index(
                ['referencia_tipo', 'referencia_id'],
                'pagos_referencia_index'
            );

            $table->index(
                'pago_original_id',
                'pagos_pago_original_id_index'
            );

            $table->index(
                'recibido_por',
                'pagos_recibido_por_index'
            );
        });

        DB::statement("
            ALTER TABLE pagos
                ADD INDEX pagos_sucursal_fecha_index
                    (sucursal_id ASC, fecha_pago DESC),

                ADD INDEX pagos_estado_fecha_index
                    (estado ASC, fecha_pago DESC),

                ADD CONSTRAINT pagos_monto_total_check
                    CHECK (monto_total > 0),

                ADD CONSTRAINT pagos_estado_check
                    CHECK (
                        estado IN (
                            'pendiente',
                            'confirmado',
                            'cancelado'
                        )
                    ),

                ADD CONSTRAINT pagos_fecha_confirmacion_check
                    CHECK (
                        estado <> 'confirmado'
                        OR fecha_pago IS NOT NULL
                    )
        ");

        Schema::table('pagos', function (Blueprint $table) {
            $table->foreign('sucursal_id')
                ->references('id')
                ->on('sucursales')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('recibido_por')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('pago_original_id')
                ->references('id')
                ->on('pagos')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
