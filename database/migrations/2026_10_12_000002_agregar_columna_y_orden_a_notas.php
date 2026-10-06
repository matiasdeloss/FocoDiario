<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las notas también son tarjetas del tablero: tienen columna y orden. Las que ya existen quedan en la columna
 * "Sin asignar" del tablero principal de su usuario. Una nota no tiene estado propio: se considera completada
 * cuando su columna es de categoría "completada".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->foreignId('columna_id')->nullable()->constrained('columnas_tablero')->nullOnDelete();
            $table->integer('orden')->default(0);
        });

        $sinAsignar = DB::table('columnas_tablero')
            ->join('tableros', 'tableros.id', '=', 'columnas_tablero.tablero_id')
            ->where('tableros.principal', true)->where('columnas_tablero.fija', true)
            ->pluck('columnas_tablero.id', 'columnas_tablero.user_id');

        foreach ($sinAsignar as $usuario => $columna) {
            DB::table('notas')->where('user_id', $usuario)->update(['columna_id' => $columna]);
        }
    }

    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('columna_id');
            $table->dropColumn('orden');
        });
    }
};
