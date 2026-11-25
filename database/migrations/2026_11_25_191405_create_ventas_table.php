
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sucursal_id');
            $table->foreignId('cliente_id')->nullable();
            $table->foreignId('usuario_id');

            $table->string('folio', 50);

            $table->decimal('subtotal', 12, 4);
            $table->decimal('descuento_total', 12, 4)->default(0);
            $table->decimal('impuestos', 12, 4)->default(0);
            $table->decimal('total', 12, 4);

            $table->string('estado', 30)->default('pendiente');

            $table->timestamps();

            $table->unique(
                ['sucursal_id', 'folio'],
                'ventas_sucursal_id_folio_unique'
            );

            $table->index('cliente_id');
            $table->index('usuario_id');
        });

        DB::statement("
            ALTER TABLE ventas
                ADD INDEX ventas_sucursal_fecha_index
                    (sucursal_id ASC, created_at DESC),
                ADD CONSTRAINT ventas_subtotal_check
                    CHECK (subtotal >= 0),
                ADD CONSTRAINT ventas_descuento_total_check
                    CHECK (descuento_total >= 0),
                ADD CONSTRAINT ventas_impuestos_check
                    CHECK (impuestos >= 0),
                ADD CONSTRAINT ventas_total_check
                    CHECK (total > 0),
                ADD CONSTRAINT ventas_descuento_limite_check
                    CHECK (descuento_total <= subtotal),
                ADD CONSTRAINT ventas_estado_check
                    CHECK (
                        estado IN (
                            'pendiente',
                            'completada',
                            'cancelada',
                            'devuelta'
                        )
                    )
        ");

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreign('sucursal_id')
                ->references('id')
                ->on('sucursales')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('cliente_id')
                ->references('id')
                ->on('clientes')
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
        Schema::dropIfExists('ventas');
    }
};
