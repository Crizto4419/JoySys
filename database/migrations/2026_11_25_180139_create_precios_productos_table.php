
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_productos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')->unique();

            $table->decimal('precio', 12, 4);
            $table->char('moneda', 3)->default('MXN');
            $table->timestamp('vigente_desde');

            $table->timestamps();

            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });

        DB::statement("
            ALTER TABLE precios_productos
                ADD CONSTRAINT precios_productos_precio_check
                    CHECK (precio > 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_productos');
    }
};
