<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            // Hoja del día: la caja pertenece a una fecha.
            $table->date('fecha')->nullable();
            // Planner semanal: lunes de la semana para las cajas "notas" y "pendiente", que no son de un día.
            $table->date('semana')->nullable();
            $table->string('zona', 20)->nullable();
            // Actividad (materia con color). Si se borra, la caja queda sin actividad.
            $table->foreignId('contexto_id')->nullable()->constrained('contextos')->nullOnDelete();
            $table->string('titulo')->nullable();
            $table->string('tipo', 20);
            $table->text('contenido')->nullable();
            $table->json('items')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            // Posición y tamaño en la grilla de 12 columnas de la hoja del día.
            $table->unsignedSmallInteger('x')->default(0);
            $table->unsignedSmallInteger('y')->default(0);
            $table->unsignedSmallInteger('ancho')->default(6);
            $table->unsignedSmallInteger('alto')->default(8);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('hecha')->default(false);
            $table->timestamps();

            // La hoja del día busca por fecha; el planner por semana (y zona).
            $table->index('fecha');
            $table->index(['semana', 'zona']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
