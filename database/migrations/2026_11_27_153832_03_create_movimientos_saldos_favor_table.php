<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'movimientos_saldos_favor',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('saldo_favor_id')
                    ->constrained('saldos_favor')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreignId('sucursal_id')
                    ->constrained('sucursales')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreignId('usuario_id')
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreignId('autorizado_por')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->string('tipo_movimiento', 30);

                $table->decimal('monto', 12, 4);

                $table->decimal('saldo_anterior', 12, 4);

                $table->decimal('saldo_nuevo', 12, 4);

                $table->foreignId('aplicacion_saldo_favor_id')
                    ->nullable()
                    ->constrained('aplicaciones_saldos_favor')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreignId('pago_id')
                    ->nullable()
                    ->constrained('pagos')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                // FK diferida hasta implementar devoluciones.
                $table->unsignedBigInteger('devolucion_id')
                    ->nullable()
                    ->index();

                $table->foreignId('movimiento_original_id')
                    ->nullable()
                    ->constrained('movimientos_saldos_favor')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->timestamp('created_at')->useCurrent();

                $table->index(
                    ['saldo_favor_id', 'created_at', 'id'],
                    'idx_msf_historial'
                );
            }
        );

        try {
            DB::statement("
                ALTER TABLE movimientos_saldos_favor
                ADD CONSTRAINT chk_msf_monto
                CHECK (monto > 0),
                ADD CONSTRAINT chk_msf_saldos
                CHECK (
                    saldo_anterior >= 0
                    AND saldo_nuevo >= 0
                ),
                ADD CONSTRAINT chk_msf_tipo
                CHECK (
                    tipo_movimiento IN (
                        'generacion',
                        'aplicacion',
                        'restitucion',
                        'cancelacion'
                    )
                ),
                ADD CONSTRAINT chk_msf_direccion
                CHECK (
                    (
                        tipo_movimiento IN (
                            'generacion',
                            'restitucion'
                        )
                        AND saldo_nuevo = saldo_anterior + monto
                    )
                    OR
                    (
                        tipo_movimiento IN (
                            'aplicacion',
                            'cancelacion'
                        )
                        AND saldo_nuevo = saldo_anterior - monto
                    )
                ),
                ADD CONSTRAINT chk_msf_restitucion
                CHECK (
                    tipo_movimiento <> 'restitucion'
                    OR (
                        movimiento_original_id IS NOT NULL
                        AND aplicacion_saldo_favor_id IS NOT NULL
                    )
                ),
                ADD CONSTRAINT chk_msf_aplicacion
                CHECK (
                    tipo_movimiento <> 'aplicacion'
                    OR aplicacion_saldo_favor_id IS NOT NULL
                ),
                ADD CONSTRAINT chk_msf_cancelacion
                CHECK (
                    tipo_movimiento <> 'cancelacion'
                    OR (
                        autorizado_por IS NOT NULL
                        AND saldo_nuevo = 0
                    )
                )
            ");
        } catch (\Throwable $e) {
            Schema::dropIfExists(
                'movimientos_saldos_favor'
            );

            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_saldos_favor');
    }
};