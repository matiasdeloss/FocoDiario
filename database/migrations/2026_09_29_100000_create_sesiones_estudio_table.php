<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_estudio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contexto_id')->nullable()->constrained('contextos')->nullOnDelete();
            $table->foreignId('tarea_id')->nullable()->constrained('tareas')->nullOnDelete();
            $table->string('tema')->nullable();
            $table->string('estilo', 20)->default('clasico');
            $table->unsignedSmallInteger('foco_min');
            $table->unsignedSmallInteger('descanso_min');
            $table->unsignedSmallInteger('descanso_largo_min');
            $table->unsignedSmallInteger('pomodoros_antes_largo');
            $table->string('estado', 12)->default('en_curso');
            $table->dateTime('iniciada_en');
            $table->dateTime('finalizada_en')->nullable();
            $table->timestamps();

            $table->index('iniciada_en');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_estudio');
    }
};
