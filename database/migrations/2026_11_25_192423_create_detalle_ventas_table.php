
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
        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('venta_id');
            $table->foreignId('producto_id');
            $table->foreignId('pieza_id')->nullable();

            $table->decimal('cantidad', 12, 4);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('descuento', 12, 4)->default(0);
            $table->decimal('subtotal', 12, 4);
            $table->decimal('total', 12, 4);

            $table->timestamps();

            $table->index(
                ['venta_id', 'id'],
                'detalle_ventas_venta_id_index'
            );

            $table->index(
                ['producto_id', 'id'],
                'detalle_ventas_producto_id_index'
            );

            $table->index(
                'pieza_id',
                'detalle_ventas_pieza_id_index'
            );
        });

        DB::statement("
            ALTER TABLE detalle_ventas
                ADD CONSTRAINT detalle_ventas_cantidad_check
                    CHECK (cantidad > 0),

                ADD CONSTRAINT detalle_ventas_precio_unitario_check
                    CHECK (precio_unitario >= 0),

                ADD CONSTRAINT detalle_ventas_descuento_check
                    CHECK (descuento >= 0),

                ADD CONSTRAINT detalle_ventas_subtotal_check
                    CHECK (subtotal >= 0),

                ADD CONSTRAINT detalle_ventas_total_check
                    CHECK (total >= 0),

                ADD CONSTRAINT detalle_ventas_descuento_limite_check
                    CHECK (descuento <= subtotal),

                ADD CONSTRAINT detalle_ventas_modalidad_check
                    CHECK (pieza_id IS NULL OR cantidad = 1)
        ");

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->foreign('venta_id')
                ->references('id')
                ->on('ventas')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('pieza_id')
                ->references('id')
                ->on('piezas')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ventas');
    }
};
