<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Tablero;
use App\Models\Tarea;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Arreglos de la revisión de tableros: estados sin columna, filtros por contexto, enlaces viejos, búsqueda, rollback y orden. */
class RevisionTablerosTest extends TestCase
{
    use RefreshDatabase;

    /** Tablero sin columna "En progreso" (solo Sin asignar y Completada). */
    private function tableroSinProgreso(): Tablero
    {
        return Tablero::crearConColumnas(auth()->id(), 'Corto', false, [['Hecho', EstadoTarea::Completada]], 1);
    }

    /** Títulos de las tareas de la lista de Tareas (el resto de la página también los menciona en selectores). */
    private function titulosListados(TestResponse $respuesta): array
    {
        return collect($respuesta->viewData('grupos'))->flatMap(fn ($grupo) => $grupo)->map(fn ($item) => $item->titulo)->sort()->values()->all();
    }

    // ---------- 1. Estado sin columna ----------

    public function test_pedir_en_progreso_en_un_tablero_sin_esa_columna_se_rechaza(): void
    {
        $tablero = $this->tableroSinProgreso();
        $sinAsignar = ColumnaTablero::sinAsignarDe($tablero->id);
        $tarea = Tarea::factory()->create(['columna_id' => $sinAsignar->id]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'en_progreso'])
            ->assertStatus(422)->assertJsonValidationErrors(['estado' => 'Este tablero no tiene una columna En progreso.']);

        $this->patchJson(route('calendario.tarjetas.update', ['tarea', $tarea->id]), ['estado' => 'en_progreso'])
            ->assertStatus(422)->assertJsonValidationErrors('estado');

        $this->putJson(route('tareas.update', $tarea), ['titulo' => 'X', 'prioridad' => 'media', 'estado' => 'en_progreso', 'tablero_id' => $tablero->id])
            ->assertStatus(422)->assertJsonValidationErrors('estado');

        $this->assertSame($sinAsignar->id, $tarea->fresh()->columna_id);
        $this->assertSame(EstadoTarea::Pendiente, $tarea->fresh()->estado);
    }

    public function test_en_progreso_sigue_funcionando_si_el_tablero_tiene_la_columna(): void
    {
        $tarea = Tarea::factory()->create();

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'en_progreso'])->assertOk();

        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);
    }

    // ---------- 2. Filtro por contexto con descendientes ----------

    public function test_el_filtro_de_contexto_de_tareas_incluye_los_descendientes(): void
    {
        $padre = Contexto::factory()->create(['nombre' => 'Carrera']);
        $hijo = Contexto::factory()->create(['nombre' => 'Programación', 'contexto_padre_id' => $padre->id]);
        $otro = Contexto::factory()->create(['nombre' => 'Casa']);
        Tarea::factory()->create(['titulo' => 'Del hijo', 'contexto_id' => $hijo->id]);
        Tarea::factory()->create(['titulo' => 'Del padre', 'contexto_id' => $padre->id]);
        Tarea::factory()->create(['titulo' => 'De casa', 'contexto_id' => $otro->id]);

        $this->assertSame(['Del hijo', 'Del padre'], $this->titulosListados($this->get(route('tareas.index', ['contexto' => $padre->id]))->assertOk()));
        $this->assertSame(['Del hijo'], $this->titulosListados($this->get(route('tareas.index', ['contexto' => $hijo->id]))->assertOk()));
    }

    public function test_las_tarjetas_del_tablero_llevan_la_cadena_de_ancestros_del_contexto(): void
    {
        $padre = Contexto::factory()->create();
        $hijo = Contexto::factory()->create(['contexto_padre_id' => $padre->id]);
        Tarea::factory()->create(['contexto_id' => $hijo->id]);
        Nota::factory()->create(['contexto_id' => $hijo->id]);

        $html = $this->get(route('tablero.index'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-contextos="'.$hijo->id.' '.$padre->id.'"'));
        // El filtro ofrece también al padre, que no tiene tareas propias pero sí en sus descendientes.
        preg_match('/<select id="k-contexto".*?<\/select>/s', $html, $filtro);
        $this->assertMatchesRegularExpression('/<option value="'.$padre->id.'"/', $filtro[0]);
    }

    // ---------- 3. ?proyecto= ----------

    public function test_proyecto_en_la_url_redirige_al_contexto_con_ese_nombre(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Tesis']);

        $this->get('/tareas?proyecto=tesis')->assertRedirect(route('tareas.index', ['contexto' => $contexto->id]));
        $this->get('/tablero?proyecto=TESIS')->assertRedirect(route('tablero.index', ['contexto' => $contexto->id]));
        // Sin coincidencia, o con un contexto ya elegido, se ignora.
        $this->get('/tareas?proyecto=nada')->assertOk();
        $this->get('/tablero?proyecto=tesis&contexto='.$contexto->id)->assertOk();
    }

    // ---------- 4. y 5. Búsqueda sin mayúsculas y confirmación ----------

    public function test_la_busqueda_de_tareas_y_notas_no_distingue_mayusculas_y_escapa_comodines(): void
    {
        Tarea::factory()->create(['titulo' => 'Estudiar algebra']);
        Tarea::factory()->create(['titulo' => '100% listo']);
        Tarea::factory()->create(['titulo' => 'Otra cosa']);
        Nota::factory()->create(['titulo' => 'Apuntes de fisica']);

        $this->assertSame(['Estudiar algebra'], $this->titulosListados($this->get(route('tareas.index', ['q' => 'ALGEBRA']))->assertOk()));
        $this->assertSame(['100% listo'], $this->titulosListados($this->get(route('tareas.index', ['q' => '100%']))->assertOk()));
        $this->assertSame(['100% listo'], $this->titulosListados($this->get(route('tareas.index', ['q' => '%']))->assertOk()));
        $this->getJson(route('notas.buscar', ['q' => 'APUNTES']))->assertOk()->assertJsonCount(1, 'notas');
    }

    public function test_la_confirmacion_de_borrar_un_tablero_avisa_de_las_completadas(): void
    {
        $this->tableroSinProgreso();

        $this->get(route('tablero.index'))->assertOk()->assertSee('las tareas completadas vuelven a pendientes', false);
    }

    // ---------- 6. Rollback con varios tableros ----------

    public function test_revertir_y_volver_a_subir_con_varios_tableros_conserva_todas_las_tarjetas(): void
    {
        $segundo = $this->tableroSinProgreso();
        $enSegundo = Tarea::factory()->create(['columna_id' => ColumnaTablero::where('tablero_id', $segundo->id)->where('categoria', 'completada')->value('id')]);
        $enSinAsignar = Tarea::factory()->create(['columna_id' => ColumnaTablero::sinAsignarDe($segundo->id)->id]);
        $enPrincipal = Tarea::factory()->create(['columna_id' => ColumnaTablero::sinAsignarDe()->id]);

        foreach (['2026_10_14_000001_agregar_oculta_a_notas', '2026_10_13_000001_agregar_columna_previa_a_tareas_y_notas', '2026_10_12_000003_create_nota_tarea_table', '2026_10_12_000002_agregar_columna_y_orden_a_notas', '2026_10_12_000001_crear_tableros'] as $migracion) {
            $this->artisan('migrate:rollback', ['--path' => "database/migrations/{$migracion}.php"])->assertExitCode(0);
        }

        // Quedan solo las columnas del principal (sin la fija) y ninguna tarea se quedó sin columna.
        $this->assertSame(3, DB::table('columnas_tablero')->count());
        $this->assertSame([0, 1, 2], DB::table('columnas_tablero')->orderBy('posicion')->pluck('posicion')->all());

        foreach ([$enSegundo, $enSinAsignar, $enPrincipal] as $tarea) {
            $columna = DB::table('tareas')->where('id', $tarea->id)->value('columna_id');
            $this->assertNotNull($columna);
            $this->assertTrue(DB::table('columnas_tablero')->where('id', $columna)->exists());
        }

        // La de la completada del otro tablero cae en la completada del principal.
        $this->assertSame('completada', DB::table('columnas_tablero')->where('id', DB::table('tareas')->where('id', $enSegundo->id)->value('columna_id'))->value('categoria'));

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertSame(3, DB::table('tareas')->whereNotNull('columna_id')->count());
    }

    // ---------- 7. Sin usuario no se toma el tablero de otro ----------

    public function test_crear_sin_sesion_ni_user_id_no_toma_la_columna_de_otro_usuario(): void
    {
        $ajeno = Auth::id();
        Auth::logout();

        // Sin dueño el guardado falla (user_id es obligatorio), pero el hook ya corrió: no debe haber elegido una columna ajena.
        $nota = new Nota(['contenido' => 'x']);
        $tarea = new Tarea(['titulo' => 'x', 'prioridad' => 'media', 'estado' => EstadoTarea::Pendiente]);

        foreach ([$nota, $tarea] as $modelo) {
            try {
                $modelo->save();
            } catch (QueryException) {
                // esperado
            }

            $this->assertNull($modelo->columna_id);
        }

        // Con user_id indicado (consola, seeders) sí usa el tablero de ese usuario.
        $conDueno = new Nota(['contenido' => 'y']);
        $conDueno->user_id = $ajeno;
        $conDueno->save();
        $this->assertSame(ColumnaTablero::sinAsignarDe(null, $ajeno)->id, $conDueno->columna_id);
    }

    // ---------- 9. Orden: solo se tocan las que cambian ----------

    public function test_guardar_el_orden_solo_actualiza_las_tarjetas_que_cambian(): void
    {
        $columna = ColumnaTablero::sinAsignarDe();
        $a = Tarea::factory()->create(['columna_id' => $columna->id, 'orden' => 1]);
        $b = Tarea::factory()->create(['columna_id' => $columna->id, 'orden' => 2]);
        $nota = Nota::factory()->create(['columna_id' => $columna->id, 'orden' => 3]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->patchJson(route('tareas.columna', $a), ['columna_id' => $columna->id, 'tarjetas' => ['tarea:'.$a->id, 'tarea:'.$b->id, 'nota:'.$nota->id]])->assertOk();
        DB::disableQueryLog();

        $this->assertSame([], array_filter(DB::getQueryLog(), fn ($q) => str_starts_with($q['query'], 'update')));

        $this->patchJson(route('tareas.columna', $a), ['columna_id' => $columna->id, 'tarjetas' => ['nota:'.$nota->id, 'tarea:'.$a->id, 'tarea:'.$b->id]])->assertOk();

        $this->assertSame([2, 3, 1], [$a->fresh()->orden, $b->fresh()->orden, $nota->fresh()->orden]);
    }
}
