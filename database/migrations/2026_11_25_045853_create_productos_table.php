
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('categoria_id');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('metal_id')->nullable();
            $table->unsignedBigInteger('pureza_id')->nullable();

            $table->string('codigo', 60)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('activo');
            $table->string('tipo', 30);
            $table->decimal('precio_base', 12, 4);
            $table->decimal('stock_minimo', 12, 4)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('categoria_id');
            $table->index('proveedor_id');
            $table->index('metal_id');
            $table->index('pureza_id');
            $table->index(['categoria_id', 'estado']);

            $table->foreign('categoria_id')
                ->references('id')
                ->on('categorias')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('proveedor_id')
                ->references('id')
                ->on('proveedores')
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

        DB::statement("
            ALTER TABLE productos
            ADD CONSTRAINT productos_tipo_check
                CHECK (tipo IN ('individual', 'por_peso', 'por_cantidad')),
            ADD CONSTRAINT productos_precio_base_check
                CHECK (precio_base >= 0),
            ADD CONSTRAINT productos_stock_minimo_check
                CHECK (stock_minimo >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
