<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('id');
            $table->string('apellidos', 150)->after('name');
            $table->string('codigo_empleado', 30)->unique()->after('apellidos');
            $table->string('telefono', 20)->nullable()->after('codigo_empleado');

            $table->foreignId('role_id')
                ->after('telefono')
                ->constrained('roles')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('estado', 30)->default('activo')->after('role_id');
            $table->unsignedInteger('intentos_fallidos')->default(0)->after('estado');
            $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
            $table->timestamp('ultimo_acceso')->nullable()->after('bloqueado_hasta');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropUnique(['username']);
            $table->dropUnique(['codigo_empleado']);

            $table->dropColumn([
                'username',
                'apellidos',
                'codigo_empleado',
                'telefono',
                'role_id',
                'estado',
                'intentos_fallidos',
                'bloqueado_hasta',
                'ultimo_acceso',
                'deleted_at',
            ]);
        });
    }
};