<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas del tablero personalizables. Cada columna pertenece a una "categoría" (los estados de
 * fábrica: pendiente, en_progreso, completada); tareas.estado sigue existiendo y refleja la categoría
 * de la columna, así Hoy, el calendario y el flag "completada" siguen funcionando sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('columnas_tablero', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->string('categoria', 20);
            $table->unsignedInteger('posicion')->default(0);
            $table->timestamps();

            $table->index('posicion');
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->foreignId('columna_id')->nullable()->after('estado')
                ->constrained('columnas_tablero')->nullOnDelete();
        });

        $ahora = now();
        $fabrica = [
            'pendiente' => 'Pendiente',
            'en_progreso' => 'En progreso',
            'completada' => 'Completada',
        ];

        $posicion = 0;
        foreach ($fabrica as $categoria => $nombre) {
            $id = DB::table('columnas_tablero')->insertGetId([
                'nombre' => $nombre,
                'categoria' => $categoria,
                'posicion' => $posicion++,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('tareas')->where('estado', $categoria)->update(['columna_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('columna_id');
        });

        Schema::dropIfExists('columnas_tablero');
    }
};
