<?php

use App\Services\Tareas\ConvertirProyectosEnContextos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las tareas se asignan a un contexto (entorno, materia, tema o proyecto) en lugar de un texto libre:
 * cada nombre de `proyecto` pasa a ser un contexto del mismo usuario y la columna se elimina.
 * `contextos.tipo` es un string(20): el tipo nuevo ("proyecto") no necesita cambios de esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->foreignId('contexto_id')->nullable()->after('titulo')->constrained('contextos')->nullOnDelete();
        });

        (new ConvertirProyectosEnContextos)();

        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn('proyecto');
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('proyecto')->nullable()->after('titulo');
        });

        foreach (DB::table('contextos')->whereIn('id', DB::table('tareas')->whereNotNull('contexto_id')->select('contexto_id'))->get(['id', 'nombre']) as $contexto) {
            DB::table('tareas')->where('contexto_id', $contexto->id)->update(['proyecto' => mb_substr($contexto->nombre, 0, 255)]);
        }

        Schema::table('tareas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contexto_id');
        });
    }
};
