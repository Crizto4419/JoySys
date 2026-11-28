<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_favor', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
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

            $table->char('moneda', 3)->default('MXN');

            $table->decimal('saldo_disponible', 12, 4)
                ->default(0);

            $table->string('estado', 30)->default('activo');

            $table->timestamps();

            $table->index(
                ['cliente_id', 'sucursal_id', 'moneda', 'estado'],
                'idx_saldos_favor_consulta'
            );
        });

        try {
            DB::statement("
                ALTER TABLE saldos_favor
                ADD CONSTRAINT chk_sf_saldo
                CHECK (saldo_disponible >= 0),
                ADD CONSTRAINT chk_sf_moneda
                CHECK (moneda = 'MXN'),
                ADD CONSTRAINT chk_sf_estado
                CHECK (
                    estado IN ('activo', 'agotado', 'cancelado')
                ),
                ADD CONSTRAINT chk_sf_estado_saldo
                CHECK (
                    estado = 'activo'
                    OR saldo_disponible = 0
                )
            ");
        } catch (\Throwable $e) {
            Schema::dropIfExists('saldos_favor');
            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_favor');
    }
};