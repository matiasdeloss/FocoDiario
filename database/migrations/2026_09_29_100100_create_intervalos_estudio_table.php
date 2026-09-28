<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervalos_estudio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones_estudio')->cascadeOnDelete();
            $table->string('tipo', 10);
            // Clave generada por el navegador: evita duplicados si se reintenta un envío.
            $table->string('clave', 60);
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->unsignedSmallInteger('planificado_min')->nullable();
            $table->unsignedInteger('pausado_seg')->default(0);
            // Duración real sin contar las pausas.
            $table->unsignedInteger('duracion_seg')->default(0);
            $table->boolean('completado')->default(false);
            $table->foreignId('bloque_tiempo_id')->nullable()->constrained('bloques_tiempo')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sesion_id', 'clave']);
            $table->index(['sesion_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervalos_estudio');
    }
};
