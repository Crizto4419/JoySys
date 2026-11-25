<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('categoria_padre_id')->nullable();

            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('activa');

            $table->timestamps();
            $table->softDeletes();

            $table->index('categoria_padre_id');

            $table->foreign('categoria_padre_id')
                ->references('id')
                ->on('categorias')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};