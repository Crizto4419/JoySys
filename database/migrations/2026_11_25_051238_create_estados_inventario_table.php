
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_inventario', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 40)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_inventario');
    }
};
