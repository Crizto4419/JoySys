
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id');
            $table->foreignId('sucursal_id');
            $table->foreignId('usuario_reporta_id');

            $table->string('tipo_incidencia', 40);
            $table->text('descripcion');
            $table->string('estado', 30)->default('abierta');

            $table->timestamps();

            $table->index('estado');
            $table->index('usuario_reporta_id');
        });

        DB::statement("
            ALTER TABLE incidencias_inventario
                ADD INDEX inc_inv_producto_fecha_index
                    (producto_id, created_at DESC),
                ADD INDEX inc_inv_sucursal_fecha_index
                    (sucursal_id, created_at DESC),
                ADD CONSTRAINT incidencias_inventario_estado_check
                    CHECK (estado IN (
                        'abierta',
                        'en_revision',
                        'resuelta'
                    ))
        ");

        Schema::table('incidencias_inventario', function (Blueprint $table) {
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

            $table->foreign('usuario_reporta_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_inventario');
    }
};
