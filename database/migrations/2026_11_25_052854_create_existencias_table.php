
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('existencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id');
            $table->foreignId('sucursal_id');
            $table->foreignId('estado_inventario_id');

            $table->decimal('cantidad', 12, 4)
                ->unsigned()
                ->default(0);

            $table->timestamps();

            $table->index('producto_id');
            $table->index('sucursal_id');
            $table->index('estado_inventario_id');

            $table->unique([
                'producto_id',
                'sucursal_id',
                'estado_inventario_id',
            ], 'existencias_producto_sucursal_estado_unique');

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('existencias');
    }
};
