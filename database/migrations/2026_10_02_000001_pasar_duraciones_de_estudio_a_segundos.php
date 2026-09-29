<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las duraciones de estudio pasan de minutos a segundos (rango válido: 5 a 10800 s) para poder
 * usar tiempos de pocos segundos. Se renombran las columnas y se multiplican por 60 los datos
 * existentes (el tipo actual, entero sin signo de 16 bits, admite hasta 65535: alcanza para 10800). down() hace el camino inverso (cualquier valor menor a un minuto
 * queda en 1 minuto, porque la columna anterior no admite fracciones).
 */
return new class extends Migration
{
    private const SESIONES = ['foco' => 'foco', 'descanso' => 'descanso', 'descanso_largo' => 'descanso_largo'];

    public function up(): void
    {
        Schema::table('sesiones_estudio', function (Blueprint $table) {
            foreach (self::SESIONES as $base) {
                $table->renameColumn("{$base}_min", "{$base}_seg");
            }
        });

        Schema::table('intervalos_estudio', function (Blueprint $table) {
            $table->renameColumn('planificado_min', 'planificado_seg');
        });

        DB::table('sesiones_estudio')->update([
            'foco_seg' => DB::raw('foco_seg * 60'),
            'descanso_seg' => DB::raw('descanso_seg * 60'),
            'descanso_largo_seg' => DB::raw('descanso_largo_seg * 60'),
        ]);
        DB::table('intervalos_estudio')->whereNotNull('planificado_seg')->update([
            'planificado_seg' => DB::raw('planificado_seg * 60'),
        ]);
    }

    public function down(): void
    {
        $aMinutos = fn (string $columna) => DB::raw("CASE WHEN {$columna} > 0 AND {$columna} < 60 THEN 1 ELSE ROUND({$columna} / 60.0) END");

        DB::table('sesiones_estudio')->update([
            'foco_seg' => $aMinutos('foco_seg'),
            'descanso_seg' => $aMinutos('descanso_seg'),
            'descanso_largo_seg' => $aMinutos('descanso_largo_seg'),
        ]);
        DB::table('intervalos_estudio')->whereNotNull('planificado_seg')->update([
            'planificado_seg' => $aMinutos('planificado_seg'),
        ]);

        Schema::table('sesiones_estudio', function (Blueprint $table) {
            foreach (self::SESIONES as $base) {
                $table->renameColumn("{$base}_seg", "{$base}_min");
            }
        });

        Schema::table('intervalos_estudio', function (Blueprint $table) {
            $table->renameColumn('planificado_seg', 'planificado_min');
        });
    }
};
