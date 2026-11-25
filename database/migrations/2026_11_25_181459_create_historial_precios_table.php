
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_precios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id');

            $table->decimal('precio_anterior', 12, 4)->nullable();
            $table->decimal('precio_nuevo', 12, 4);

            $table->char('moneda', 3)->default('MXN');

            $table->timestamp('vigente_desde');

            $table->foreignId('usuario_id')->nullable();

            $table->timestamp('created_at')->nullable();
        });

        DB::statement("
            ALTER TABLE historial_precios
                ADD INDEX hist_precio_producto_vigencia_index
                    (producto_id ASC, vigente_desde DESC),
                ADD INDEX hist_precio_usuario_fecha_index
                    (usuario_id ASC, created_at DESC),
                ADD CONSTRAINT historial_precios_precio_nuevo_check
                    CHECK (precio_nuevo > 0)
        ");

        Schema::table('historial_precios', function (Blueprint $table) {
            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
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
        Schema::dropIfExists('historial_precios');
    }
};
