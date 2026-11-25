
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 50)->nullable();
            $table->string('nombre', 180);
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 150)->nullable();

            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('codigo');
            $table->index('nombre');
            $table->index('telefono');
            $table->index('correo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
