<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_msf_no_update
            BEFORE UPDATE ON movimientos_saldos_favor
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Los movimientos de saldo a favor son inmutables'
            SQL);

        try {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER trg_msf_no_delete
                BEFORE DELETE ON movimientos_saldos_favor
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Los movimientos de saldo a favor no pueden eliminarse'
                SQL);
        } catch (\Throwable $e) {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS trg_msf_no_update'
            );

            throw $e;
        }
    }

    public function down(): void
    {
        DB::unprepared(
            'DROP TRIGGER IF EXISTS trg_msf_no_delete'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS trg_msf_no_update'
        );
    }
};
