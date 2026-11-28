<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (
            DB::table('movimientos_saldos_favor')
                ->where('tipo_movimiento', 'cancelacion')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Existen cancelaciones anteriores que requieren auditoria.'
            );
        }

        Schema::table(
            'movimientos_saldos_favor',
            function (Blueprint $table) {
                $table->string('justificacion', 255)
                    ->nullable()
                    ->after('movimiento_original_id');
            }
        );

        try {
            DB::statement("
                ALTER TABLE movimientos_saldos_favor
                ADD CONSTRAINT chk_msf_justificacion
                CHECK (
                    tipo_movimiento <> 'cancelacion'
                    OR (
                        justificacion IS NOT NULL
                        AND CHAR_LENGTH(TRIM(justificacion)) > 0
                    )
                )
            ");
        } catch (\Throwable $e) {
            Schema::table(
                'movimientos_saldos_favor',
                function (Blueprint $table) {
                    $table->dropColumn('justificacion');
                }
            );

            throw $e;
        }
    }

    public function down(): void
    {
        if (
            DB::table('movimientos_saldos_favor')
                ->whereNotNull('justificacion')
                ->exists()
        ) {
            throw new \RuntimeException(
                'No se permite eliminar justificaciones registradas.'
            );
        }

        DB::statement("
            ALTER TABLE movimientos_saldos_favor
            DROP CHECK chk_msf_justificacion
        ");

        Schema::table(
            'movimientos_saldos_favor',
            function (Blueprint $table) {
                $table->dropColumn('justificacion');
            }
        );
    }
};