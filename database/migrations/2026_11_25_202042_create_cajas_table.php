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
        Schema::create('cajas', function (Blueprint $table) {
            // Columnas físicas
            $table->id();

            $table->unsignedBigInteger('sucursal_id');

            $table->string('codigo', 40);
            $table->string('nombre', 100);

            $table->char('moneda', 3)->default('MXN');

            $table->string('estado', 30)->default('activa');

            $table->timestamps();

            // Índices
            $table->unique(
                ['sucursal_id', 'codigo'],
                'cajas_sucursal_id_codigo_unique'
            );

            $table->index(
                'estado',
                'cajas_estado_index'
            );
        });

        // Restricción CHECK
        DB::statement("
            ALTER TABLE cajas
            ADD CONSTRAINT cajas_estado_check
            CHECK (
                estado IN (
                    'activa',
                    'inactiva',
                    'mantenimiento'
                )
            )
        ");

        // Clave foránea
        Schema::table('cajas', function (Blueprint $table) {
            $table->foreign(
                'sucursal_id',
                'cajas_sucursal_id_foreign'
            )
                ->references('id')
                ->on('sucursales')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};