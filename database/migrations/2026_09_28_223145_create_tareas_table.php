<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('proyecto')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('prioridad', 10)->default('media');
            $table->string('estado', 20)->default('pendiente');
            $table->timestamps();

            $table->index(['estado', 'fecha_limite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};
