
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     */
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {

            // 1. Identificador principal.
            $table->id();

            // 2. Relaciones.
            $table->unsignedBigInteger('sesion_caja_id');

            $table->unsignedBigInteger('usuario_id');

            $table->unsignedBigInteger('autorizado_por')
                ->nullable();

            $table->unsignedBigInteger('detalle_pago_id')
                ->nullable();

            $table->unsignedBigInteger('movimiento_original_id')
                ->nullable();

            // 3. Información del movimiento.
            $table->string('tipo_movimiento', 30);

            $table->decimal('monto', 12, 4);

            $table->string('concepto', 150);

            $table->text('observaciones')
                ->nullable();

            // 4. Fecha de registro.
            // Estructura append-only, sin updated_at ni deleted_at.
            $table->timestamp('created_at')
                ->useCurrent();

            // 5. Índice UNIQUE para evitar duplicar
            // movimientos derivados del mismo detalle de pago.
            $table->unique(
                'detalle_pago_id',
                'movimientos_caja_detalle_pago_id_unique'
            );

            // 6. Índices secundarios simples.
            $table->index(
                'autorizado_por',
                'mov_caja_autorizado_por_index'
            );

            $table->index(
                'movimiento_original_id',
                'mov_caja_original_index'
            );
        });

        // 7. Índices compuestos con orden físico ASC/DESC.

        DB::statement("
            CREATE INDEX mov_caja_sesion_fecha_index
            ON movimientos_caja (
                sesion_caja_id ASC,
                created_at DESC
            )
        ");

        DB::statement("
            CREATE INDEX mov_caja_usuario_fecha_index
            ON movimientos_caja (
                usuario_id ASC,
                created_at DESC
            )
        ");

        DB::statement("
            CREATE INDEX mov_caja_tipo_fecha_index
            ON movimientos_caja (
                tipo_movimiento ASC,
                created_at DESC
            )
        ");

        // 8. Restricción CHECK: monto positivo.

        DB::statement("
            ALTER TABLE movimientos_caja
            ADD CONSTRAINT mov_caja_monto_check
            CHECK (monto > 0)
        ");

        // 9. Restricción CHECK: tipos de movimiento permitidos.

        DB::statement("
            ALTER TABLE movimientos_caja
            ADD CONSTRAINT mov_caja_tipo_check
            CHECK (
                tipo_movimiento IN (
                    'ingreso',
                    'gasto',
                    'retiro',
                    'reembolso',
                    'compensacion'
                )
            )
        ");

        // 10. Restricción CHECK: relación con movimiento original.

        DB::statement("
            ALTER TABLE movimientos_caja
            ADD CONSTRAINT mov_caja_compensacion_check
            CHECK (
                (
                    tipo_movimiento = 'compensacion'
                    AND movimiento_original_id IS NOT NULL
                )
                OR
                (
                    tipo_movimiento <> 'compensacion'
                    AND movimiento_original_id IS NULL
                )
            )
        ");

        // 11. Restricción CHECK: autorización de compensaciones
        // y prohibición de reutilizar detalles de pago.

        DB::statement("
            ALTER TABLE movimientos_caja
            ADD CONSTRAINT mov_caja_compensacion_datos_check
            CHECK (
                tipo_movimiento <> 'compensacion'
                OR
                (
                    autorizado_por IS NOT NULL
                    AND detalle_pago_id IS NULL
                )
            )
        ");

        // 12. Claves foráneas.
        // Se agregan después de crear los índices físicos
        // para evitar índices redundantes.

        Schema::table('movimientos_caja', function (Blueprint $table) {

            $table->foreign(
                'sesion_caja_id',
                'movimientos_caja_sesion_caja_id_foreign'
            )
                ->references('id')
                ->on('sesiones_caja')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreign(
                'usuario_id',
                'movimientos_caja_usuario_id_foreign'
            )
                ->references('id')
                ->on('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreign(
                'autorizado_por',
                'movimientos_caja_autorizado_por_foreign'
            )
                ->references('id')
                ->on('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreign(
                'detalle_pago_id',
                'movimientos_caja_detalle_pago_id_foreign'
            )
                ->references('id')
                ->on('detalle_pagos')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreign(
                'movimiento_original_id',
                'movimientos_caja_movimiento_original_id_foreign'
            )
                ->references('id')
                ->on('movimientos_caja')
                ->restrictOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
