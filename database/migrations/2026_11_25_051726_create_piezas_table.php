
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piezas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id');
            $table->foreignId('sucursal_id');
            $table->foreignId('estado_inventario_id');
            $table->foreignId('metal_id')->nullable();
            $table->foreignId('pureza_id')->nullable();

            $table->string('codigo_pieza', 80)->unique();
            $table->decimal('peso_gramos', 8, 3)->nullable();

            $table->timestamps();

            $table->index('producto_id');
            $table->index('sucursal_id');
            $table->index('estado_inventario_id');
            $table->index('metal_id');
            $table->index('pureza_id');

            $table->index(['producto_id', 'estado_inventario_id']);

            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('sucursal_id')
                ->references('id')
                ->on('sucursales')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('estado_inventario_id')
                ->references('id')
                ->on('estados_inventario')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('metal_id')
                ->references('id')
                ->on('metales')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('pureza_id')
                ->references('id')
                ->on('purezas')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });

        DB::statement(
            'ALTER TABLE piezas
             ADD CONSTRAINT piezas_peso_gramos_check
             CHECK (peso_gramos > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('piezas');
    }
};
