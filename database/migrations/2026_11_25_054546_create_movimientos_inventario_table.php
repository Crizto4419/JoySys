
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id');
            $table->foreignId('sucursal_id');
            $table->foreignId('pieza_id')->nullable();
            $table->foreignId('estado_origen_id')->nullable();
            $table->foreignId('estado_destino_id')->nullable();
            $table->foreignId('usuario_id')->nullable();

            $table->string('tipo_movimiento', 40);

            $table->decimal('cantidad', 12, 4)
                ->unsigned()
                ->default(1);

            $table->text('observaciones')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('usuario_id');
            $table->index('estado_origen_id');
            $table->index('estado_destino_id');
        });

        DB::statement("
            ALTER TABLE movimientos_inventario
                ADD INDEX mov_inv_producto_fecha_index
                    (producto_id, created_at DESC),
                ADD INDEX mov_inv_sucursal_fecha_index
                    (sucursal_id, created_at DESC),
                ADD INDEX mov_inv_tipo_fecha_index
                    (tipo_movimiento, created_at DESC),
                ADD INDEX mov_inv_pieza_fecha_index
                    (pieza_id, created_at DESC),
                ADD CONSTRAINT movimientos_inventario_cantidad_check
                    CHECK (cantidad > 0),
                ADD CONSTRAINT movimientos_inventario_tipo_check
                    CHECK (tipo_movimiento IN (
                        'entrada',
                        'salida',
                        'transferencia',
                        'ajuste'
                    ))
        ");

        Schema::table('movimientos_inventario', function (Blueprint $table) {
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

            $table->foreign('pieza_id')
                ->references('id')
                ->on('piezas')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('estado_origen_id')
                ->references('id')
                ->on('estados_inventario')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('estado_destino_id')
                ->references('id')
                ->on('estados_inventario')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
