<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloques_tiempo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('tarea_id')->nullable()->constrained('tareas')->nullOnDelete();
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->string('origen', 10)->default('manual');
            $table->unsignedTinyInteger('concentracion')->nullable();
            $table->timestamps();

            $table->index('inicio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloques_tiempo');
    }
};
