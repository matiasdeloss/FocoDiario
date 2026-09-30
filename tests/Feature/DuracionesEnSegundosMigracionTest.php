<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** La migración que pasa las duraciones de estudio de minutos a segundos conserva los datos y es reversible. */
class DuracionesEnSegundosMigracionTest extends TestCase
{
    use RefreshDatabase;

    /** Revierte solo esta migración, aunque después se agreguen otras más nuevas. */
    private function revertirMigracionDeSegundos(): void
    {
        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2026_10_02_000001_pasar_duraciones_de_estudio_a_segundos.php',
        ])->assertExitCode(0);
    }

    private function insertarSesionEnMinutos(): int
    {
        return DB::table('sesiones_estudio')->insertGetId([
            'user_id' => auth()->id(), 'estilo' => 'clasico', 'foco_min' => 25, 'descanso_min' => 5, 'descanso_largo_min' => 15,
            'pomodoros_antes_largo' => 4, 'estado' => 'finalizada', 'iniciada_en' => '2026-09-28 20:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_al_subir_multiplica_por_60_las_filas_existentes(): void
    {
        $this->revertirMigracionDeSegundos();

        $this->assertTrue(Schema::hasColumns('sesiones_estudio', ['foco_min', 'descanso_min', 'descanso_largo_min']));
        $this->assertTrue(Schema::hasColumn('intervalos_estudio', 'planificado_min'));

        $sesion = $this->insertarSesionEnMinutos();
        foreach ([['foco', 25, 1500], ['libre', null, 0], ['descanso', 1, 45]] as $i => [$tipo, $plan, $dur]) {
            DB::table('intervalos_estudio')->insert([
                'user_id' => auth()->id(), 'sesion_id' => $sesion, 'tipo' => $tipo, 'clave' => "c-{$i}", 'inicio' => '2026-09-28 20:00:00',
                'fin' => '2026-09-28 20:10:00', 'planificado_min' => $plan, 'pausado_seg' => 0, 'duracion_seg' => $dur,
                'completado' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertFalse(Schema::hasColumn('sesiones_estudio', 'foco_min'));
        $this->assertFalse(Schema::hasColumn('intervalos_estudio', 'planificado_min'));

        $fila = DB::table('sesiones_estudio')->where('id', $sesion)->first();
        $this->assertSame([1500, 300, 900, 4], [(int) $fila->foco_seg, (int) $fila->descanso_seg, (int) $fila->descanso_largo_seg, (int) $fila->pomodoros_antes_largo]);
        $this->assertSame(
            [1500, null, 60],
            DB::table('intervalos_estudio')->orderBy('id')->pluck('planificado_seg')->map(fn ($v) => $v === null ? null : (int) $v)->all(),
        );
        // Lo demás no se toca.
        $this->assertSame([1500, 0, 45], DB::table('intervalos_estudio')->orderBy('id')->pluck('duracion_seg')->map(fn ($v) => (int) $v)->all());
    }

    public function test_al_bajar_vuelve_a_minutos_sin_perder_los_tiempos_cortos(): void
    {
        DB::table('sesiones_estudio')->insert([
            'user_id' => auth()->id(), 'estilo' => 'personalizado', 'foco_seg' => 5, 'descanso_seg' => 90, 'descanso_largo_seg' => 10800,
            'pomodoros_antes_largo' => 4, 'estado' => 'finalizada', 'iniciada_en' => '2026-09-28 20:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->revertirMigracionDeSegundos();

        $fila = DB::table('sesiones_estudio')->first();
        // 5 s queda en 1 min (el mínimo que admite la columna anterior); 90 s redondea a 2 min; 180 min se conserva.
        $this->assertSame([1, 2, 180], [(int) $fila->foco_min, (int) $fila->descanso_min, (int) $fila->descanso_largo_min]);

        $this->artisan('migrate')->assertExitCode(0);
        $this->assertTrue(Schema::hasColumns('sesiones_estudio', ['foco_seg', 'descanso_seg', 'descanso_largo_seg']));
    }
}
