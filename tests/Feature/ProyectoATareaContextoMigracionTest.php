<?php

namespace Tests\Feature;

use App\Models\Contexto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** La migración que reemplaza `tareas.proyecto` por `tareas.contexto_id` convierte los textos en contextos y es reversible. */
class ProyectoATareaContextoMigracionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_10_10_000001_reemplazar_proyecto_por_contexto_en_tareas.php';

    /** Vuelve al esquema de antes (con `proyecto`), aunque después se agreguen otras migraciones más nuevas. */
    private function revertir(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRACION])->assertExitCode(0);
    }

    private function tarea(int $usuario, ?string $proyecto, string $titulo = 'T'): int
    {
        return DB::table('tareas')->insertGetId([
            'user_id' => $usuario, 'titulo' => $titulo, 'proyecto' => $proyecto, 'prioridad' => 'media', 'estado' => 'pendiente',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function contexto(int $usuario, string $nombre, string $tipo, ?int $padre = null): int
    {
        return DB::table('contextos')->insertGetId([
            'user_id' => $usuario, 'nombre' => $nombre, 'tipo' => $tipo, 'contexto_padre_id' => $padre,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_cada_proyecto_pasa_a_ser_un_contexto_del_mismo_usuario(): void
    {
        $yo = auth()->id();
        $otro = User::factory()->create()->id;

        $this->revertir();
        $this->assertTrue(Schema::hasColumn('tareas', 'proyecto'));
        $this->assertFalse(Schema::hasColumn('tareas', 'contexto_id'));

        $existente = $this->contexto($yo, 'Programación II', 'materia', $this->contexto($yo, 'Carrera', 'entorno'));
        $enTesis = [$this->tarea($yo, 'Tesis'), $this->tarea($yo, '  tesis '), $this->tarea($yo, 'TESIS')];
        $enMateria = $this->tarea($yo, 'programación ii');
        $sinProyecto = [$this->tarea($yo, null), $this->tarea($yo, '   '), $this->tarea($yo, '')];
        $deOtro = $this->tarea($otro, 'Tesis');

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertFalse(Schema::hasColumn('tareas', 'proyecto'));
        $this->assertTrue(Schema::hasColumn('tareas', 'contexto_id'));

        // Reutiliza el contexto de la materia (sin distinguir mayúsculas) y no crea otro.
        $this->assertSame($existente, (int) DB::table('tareas')->where('id', $enMateria)->value('contexto_id'));
        $this->assertSame(1, DB::table('contextos')->where('user_id', $yo)->whereRaw('lower(nombre) = ?', ['programación ii'])->count());

        // Los demás nombres crean un contexto de tipo proyecto, sin padre, por usuario y una sola vez.
        $tesis = DB::table('contextos')->where('user_id', $yo)->where('nombre', 'Tesis')->first();
        $this->assertNotNull($tesis);
        $this->assertSame('proyecto', $tesis->tipo);
        $this->assertNull($tesis->contexto_padre_id);
        $this->assertSame([$tesis->id], DB::table('tareas')->whereIn('id', $enTesis)->pluck('contexto_id')->unique()->map(fn ($id) => (int) $id)->all());

        // La tarea del otro usuario apunta a un contexto suyo, no al mío.
        $suyo = DB::table('contextos')->where('user_id', $otro)->where('nombre', 'Tesis')->first();
        $this->assertNotNull($suyo);
        $this->assertNotSame((int) $tesis->id, (int) $suyo->id);
        $this->assertSame((int) $suyo->id, (int) DB::table('tareas')->where('id', $deOtro)->value('contexto_id'));

        // Sin proyecto (o en blanco) queda sin contexto.
        $this->assertSame([null, null, null], DB::table('tareas')->whereIn('id', $sinProyecto)->orderBy('id')->pluck('contexto_id')->all());
        // Solo se crearon los dos "Tesis" (uno por usuario): Carrera, Programación II y esos dos.
        $this->assertSame(4, DB::table('contextos')->count());
    }

    public function test_prefiere_el_contexto_de_primer_nivel_cuando_hay_varios_con_el_mismo_nombre(): void
    {
        $yo = auth()->id();

        $this->revertir();

        $hijo = $this->contexto($yo, 'Redes', 'tema', $this->contexto($yo, 'Carrera', 'entorno'));
        $raiz = $this->contexto($yo, 'Redes', 'materia');
        $tarea = $this->tarea($yo, 'Redes');

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertNotSame($hijo, $raiz);
        $this->assertSame($raiz, (int) DB::table('tareas')->where('id', $tarea)->value('contexto_id'));
    }

    public function test_al_revertir_el_proyecto_vuelve_como_texto_con_el_nombre_del_contexto(): void
    {
        $contexto = Contexto::factory()->proyecto()->create(['nombre' => 'Portfolio']);
        $fila = ['user_id' => auth()->id(), 'titulo' => 'T', 'prioridad' => 'media', 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()];
        $tarea = DB::table('tareas')->insertGetId($fila + ['contexto_id' => $contexto->id]);
        $sin = DB::table('tareas')->insertGetId($fila + ['contexto_id' => null]);

        $this->revertir();

        $this->assertFalse(Schema::hasColumn('tareas', 'contexto_id'));
        $this->assertSame('Portfolio', DB::table('tareas')->where('id', $tarea)->value('proyecto'));
        $this->assertNull(DB::table('tareas')->where('id', $sin)->value('proyecto'));
    }
}
