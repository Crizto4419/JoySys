<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_sucursal', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('sucursal_id')
                ->constrained('sucursales')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->boolean('es_principal')->default(false);

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();

            $table->string('estado', 30)->default('activo');

            $table->foreignId('asignado_por')
                ->constrained('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->unsignedBigInteger('usuario_principal_vigente_id')
                ->nullable()
               ->virtualAs("CASE WHEN es_principal = 1 AND fecha_fin IS NULL THEN usuario_id ELSE NULL END");

            $table->timestamps();

            $table->unique('usuario_principal_vigente_id', 'empleado_sucursal_principal_vigente_unique');

            $table->index(['usuario_id', 'estado']);
            $table->index(['sucursal_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_sucursal');
    }
};