<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notas vinculadas a tareas (muchos a muchos): una nota puede servir a varias tareas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_tarea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained('notas')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['nota_id', 'tarea_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_tarea');
    }
};
