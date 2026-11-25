
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();

            $table->string('nombre_comercial', 150);
            $table->string('razon_social', 180)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('sitio_web', 180)->nullable();
            $table->string('estado', 30)->default('activo');
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('nombre_comercial');
            $table->index('estado');
            $table->index('rfc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
