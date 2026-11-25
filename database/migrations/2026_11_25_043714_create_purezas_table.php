
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purezas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('metal_id');
            $table->string('codigo', 40);
            $table->string('nombre', 100);

            $table->timestamps();

            $table->unique(['metal_id', 'codigo']);
            $table->unique(['metal_id', 'nombre']);
            $table->index('metal_id');

            $table->foreign('metal_id')
                ->references('id')
                ->on('metales')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purezas');
    }
};
