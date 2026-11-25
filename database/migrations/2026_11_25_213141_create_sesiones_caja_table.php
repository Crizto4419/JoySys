
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     */
    public function up(): void
    {
        Schema::create('sesiones_caja', function (Blueprint $table) {

            // Identificador principal.
            $table->id();

            // Relaciones principales.
            $table->unsignedBigInteger('caja_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('cerrada_por')->nullable();

            // Columnas generadas para controlar sesiones abiertas.
            $table->unsignedBigInteger('caja_abierta_id')
                ->nullable()
                ->virtualAs(
                    "CASE WHEN estado = 'abierta' THEN caja_id ELSE NULL END"
                );

            $table->unsignedBigInteger('empleado_abierto_id')
                ->nullable()
                ->virtualAs(
                    "CASE WHEN estado = 'abierta' THEN usuario_id ELSE NULL END"
                );

            // Importes financieros.
            $table->decimal('fondo_inicial', 12, 4)
                ->default(0);

            $table->decimal('saldo_esperado', 12, 4)
                ->nullable();

            $table->decimal('efectivo_contado', 12, 4)
                ->nullable();

            $table->decimal('diferencia', 12, 4)
                ->nullable();

            // Fechas operativas.
            $table->timestamp('fecha_apertura');

            $table->timestamp('fecha_cierre')
                ->nullable();

            // Estado de la sesión.
            $table->string('estado', 30)
                ->default('abierta');

            // Marcas de tiempo.
            $table->timestamps();

            // Una sesión abierta como máximo por caja.
            $table->unique(
                'caja_abierta_id',
                'sesiones_caja_caja_abierta_id_unique'
            );

            // Una sesión abierta como máximo por empleado.
            $table->unique(
                'empleado_abierto_id',
                'sesiones_caja_empleado_abierto_id_unique'
            );

            // Índice del empleado responsable del cierre.
            $table->index(
                'cerrada_por',
                'sesiones_caja_cerrada_por_index'
            );
        });

        // Índices compuestos con orden físico ASC y DESC.
        DB::statement("
            CREATE INDEX sesiones_caja_caja_fecha_index
            ON sesiones_caja (caja_id ASC, fecha_apertura DESC)
        ");

        DB::statement("
            CREATE INDEX sesiones_caja_sucursal_fecha_index
            ON sesiones_caja (sucursal_id ASC, fecha_apertura DESC)
        ");

        DB::statement("
            CREATE INDEX sesiones_caja_usuario_fecha_index
            ON sesiones_caja (usuario_id ASC, fecha_apertura DESC)
        ");

        // Restricción de estados permitidos.
        DB::statement("
            ALTER TABLE sesiones_caja
            ADD CONSTRAINT sesiones_caja_estado_check
            CHECK (estado IN ('abierta', 'cerrada'))
        ");

        // El fondo inicial no puede ser negativo.
        DB::statement("
            ALTER TABLE sesiones_caja
            ADD CONSTRAINT sesiones_caja_fondo_inicial_check
            CHECK (fondo_inicial >= 0)
        ");

        // El efectivo contado no puede ser negativo.
        DB::statement("
            ALTER TABLE sesiones_caja
            ADD CONSTRAINT sesiones_caja_efectivo_contado_check
            CHECK (efectivo_contado >= 0)
        ");

        // La fecha de cierre no puede preceder a la apertura.
        DB::statement("
            ALTER TABLE sesiones_caja
            ADD CONSTRAINT sesiones_caja_fechas_check
            CHECK (
                fecha_cierre IS NULL
                OR fecha_cierre >= fecha_apertura
            )
        ");

        // Coherencia entre el estado y los datos del cierre.
        DB::statement("
            ALTER TABLE sesiones_caja
            ADD CONSTRAINT sesiones_caja_coherencia_estado_check
            CHECK (
                (
                    estado = 'abierta'
                    AND fecha_cierre IS NULL
                    AND cerrada_por IS NULL
                    AND saldo_esperado IS NULL
                    AND efectivo_contado IS NULL
                    AND diferencia IS NULL
                )
                OR
                (
                    estado = 'cerrada'
                    AND fecha_cierre IS NOT NULL
                    AND cerrada_por IS NOT NULL
                    AND saldo_esperado IS NOT NULL
                    AND efectivo_contado IS NOT NULL
                    AND diferencia IS NOT NULL
                )
            )
        ");

        // Claves foráneas con restricciones explícitas.
        Schema::table('sesiones_caja', function (Blueprint $table) {

            $table->foreign(
                'caja_id',
                'sesiones_caja_caja_id_foreign'
            )
                ->references('id')
                ->on('cajas')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign(
                'sucursal_id',
                'sesiones_caja_sucursal_id_foreign'
            )
                ->references('id')
                ->on('sucursales')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign(
                'usuario_id',
                'sesiones_caja_usuario_id_foreign'
            )
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign(
                'cerrada_por',
                'sesiones_caja_cerrada_por_foreign'
            )
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesiones_caja');
    }
};
