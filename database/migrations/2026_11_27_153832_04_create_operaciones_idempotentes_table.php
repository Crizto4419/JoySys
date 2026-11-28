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
            'operaciones_idempotentes',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('sucursal_id')
                    ->constrained('sucursales')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->foreignId('usuario_id')
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();

                $table->string('tipo_operacion', 50);

                $table->string('clave', 100)
                    ->collation('utf8mb4_bin');

                $table->char('hash_solicitud', 64)
                    ->collation('utf8mb4_bin');

                $table->string('resultado_tipo', 50)
                    ->nullable();

                $table->unsignedBigInteger('resultado_id')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'sucursal_id',
                        'usuario_id',
                        'tipo_operacion',
                        'clave',
                    ],
                    'uq_operacion_idempotente'
                );

                $table->index(
                    ['resultado_tipo', 'resultado_id'],
                    'idx_idempotencia_resultado'
                );
            }
        );

        try {
            DB::statement("
                ALTER TABLE operaciones_idempotentes
                ADD CONSTRAINT chk_oi_resultado
                CHECK (
                    (
                        resultado_tipo IS NULL
                        AND resultado_id IS NULL
                    )
                    OR
                    (
                        resultado_tipo IS NOT NULL
                        AND resultado_id IS NOT NULL
                    )
                )
            ");
        } catch (\Throwable $e) {
            Schema::dropIfExists(
                'operaciones_idempotentes'
            );

            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones_idempotentes');
    }
};