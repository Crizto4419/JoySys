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
            'aplicaciones_saldos_favor',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('saldo_favor_id')
                    ->constrained('saldos_favor')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreignId('detalle_pago_id')
                    ->nullable()
                    ->unique()
                    ->constrained('detalle_pagos')
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

                $table->foreignId('revertida_por')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->decimal('monto_aplicado', 12, 4);

                $table->string('estado', 30)
                    ->default('aplicada');

                $table->timestamps();

                $table->index(
                    ['saldo_favor_id', 'estado'],
                    'idx_asf_saldo_estado'
                );
            }
        );

        try {
            DB::statement("
                ALTER TABLE aplicaciones_saldos_favor
                ADD CONSTRAINT chk_asf_monto
                CHECK (monto_aplicado > 0),
                ADD CONSTRAINT chk_asf_estado
                CHECK (
                    estado IN ('aplicada', 'revertida')
                ),
                ADD CONSTRAINT chk_asf_reversion
                CHECK (
                    (estado = 'aplicada'
                        AND revertida_por IS NULL)
                    OR
                    (estado = 'revertida'
                        AND revertida_por IS NOT NULL)
                )
            ");
        } catch (\Throwable $e) {
            Schema::dropIfExists(
                'aplicaciones_saldos_favor'
            );

            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_saldos_favor');
    }
};