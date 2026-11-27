<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE TRIGGER trg_movimientos_caja_no_update
            BEFORE UPDATE ON movimientos_caja
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Los movimientos de caja son inmutables'
        ");

        try {
            DB::unprepared("
                CREATE TRIGGER trg_movimientos_caja_no_delete
                BEFORE DELETE ON movimientos_caja
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Los movimientos de caja no pueden eliminarse'
            ");
        } catch (\Throwable $e) {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS trg_movimientos_caja_no_update'
            );

            throw $e;
        }
    }

    public function down(): void
    {
        DB::unprepared(
            'DROP TRIGGER IF EXISTS trg_movimientos_caja_no_delete'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS trg_movimientos_caja_no_update'
        );
    }
};