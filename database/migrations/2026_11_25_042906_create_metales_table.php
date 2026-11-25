
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metales', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 100)->unique();
            $table->string('simbolo', 10)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('activo');

            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metales');
    }
};
