<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recuerda la columna en la que estaba una tarea o nota antes de entrar en una columna "completada", para devolverla ahí
 * al reabrirla. Si esa columna se borra, la referencia queda en null y la tarjeta vuelve a "Sin asignar" de su tablero.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tareas', 'notas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('columna_previa_id')->nullable()->constrained('columnas_tablero')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['tareas', 'notas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('columna_previa_id');
            });
        }
    }
};
