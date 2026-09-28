<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->nullable()->constrained('tareas')->nullOnDelete();
            $table->string('mensaje');
            $table->dateTime('recordar_en');
            $table->dateTime('avisado_en')->nullable();
            $table->timestamps();

            $table->index('recordar_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
