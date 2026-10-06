<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Nota;
use App\Models\Tablero;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Varios tableros por usuario: "Sin asignar" fija, tablero principal, creación fuera del tablero y estado por tablero. */
class TablerosTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACIONES = [
        'database/migrations/2026_10_12_000003_create_nota_tarea_table.php',
        'database/migrations/2026_10_12_000002_agregar_columna_y_orden_a_notas.php',
        'database/migrations/2026_10_12_000001_crear_tableros.php',
    ];

    private function principal(): Tablero
    {
        return Tablero::where('principal', true)->firstOrFail();
    }

    private function nuevoTablero(string $nombre = 'Facultad'): Tablero
    {
        $this->post(route('tableros.store'), ['nombre' => $nombre])->assertRedirect();

        return Tablero::where('nombre', $nombre)->firstOrFail();
    }

    // ---------- Migración de datos ----------

    public function test_la_migracion_crea_el_principal_con_sin_asignar_primera_y_conserva_tareas_y_ubica_notas(): void
    {
        $yo = auth()->id();
        $otro = User::factory()->create()->id;

        foreach (self::MIGRACIONES as $ruta) {
            $this->artisan('migrate:rollback', ['--path' => $ruta])->assertExitCode(0);
        }

        $this->assertFalse(Schema::hasTable('tableros'));
        $this->assertFalse(Schema::hasColumn('columnas_tablero', 'tablero_id'));

        // Estado de antes: columnas de fábrica sin tablero y tareas / notas de dos usuarios.
        $columnasDe = fn (int $usuario) => DB::table('columnas_tablero')->where('user_id', $usuario)->orderBy('posicion')->pluck('id', 'categoria');
        $mias = $columnasDe($yo);
        $tarea = DB::table('tareas')->insertGetId([
            'user_id' => $yo, 'titulo' => 'En progreso', 'prioridad' => 'media', 'estado' => 'en_progreso', 'columna_id' => $mias['en_progreso'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tareaAjena = DB::table('tareas')->insertGetId([
            'user_id' => $otro, 'titulo' => 'Ajena', 'prioridad' => 'media', 'estado' => 'pendiente', 'columna_id' => $columnasDe($otro)['pendiente'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $nota = DB::table('notas')->insertGetId(['user_id' => $yo, 'contenido' => 'Mía', 'fijada' => false, 'created_at' => now(), 'updated_at' => now()]);
        $notaAjena = DB::table('notas')->insertGetId(['user_id' => $otro, 'contenido' => 'Ajena', 'fijada' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('migrate')->assertExitCode(0);

        foreach ([$yo, $otro] as $usuario) {
            $tableros = DB::table('tableros')->where('user_id', $usuario)->get();
            $this->assertCount(1, $tableros);
            $this->assertSame('Principal', $tableros[0]->nombre);
            $this->assertTrue((bool) $tableros[0]->principal);

            $columnas = DB::table('columnas_tablero')->where('tablero_id', $tableros[0]->id)->orderBy('posicion')->get();
            $this->assertSame(['Sin asignar', 'Pendiente', 'En progreso', 'Completada'], $columnas->pluck('nombre')->all());
            $this->assertSame([true, false, false, false], $columnas->map(fn ($c) => (bool) $c->fija)->all());
            $this->assertSame([0, 1, 2, 3], $columnas->pluck('posicion')->map(fn ($p) => (int) $p)->all());
            $this->assertSame('pendiente', $columnas[0]->categoria);
        }

        // Las tareas conservan su columna; las notas quedan en "Sin asignar" del principal de su usuario.
        $this->assertSame($mias['en_progreso'], (int) DB::table('tareas')->where('id', $tarea)->value('columna_id'));
        $this->assertSame($columnasDe($otro)['pendiente'], (int) DB::table('tareas')->where('id', $tareaAjena)->value('columna_id'));
        $sinAsignar = fn (int $usuario) => (int) DB::table('columnas_tablero')->where('user_id', $usuario)->where('fija', true)->value('id');
        $this->assertSame($sinAsignar($yo), (int) DB::table('notas')->where('id', $nota)->value('columna_id'));
        $this->assertSame($sinAsignar($otro), (int) DB::table('notas')->where('id', $notaAjena)->value('columna_id'));
    }

    public function test_al_revertir_las_tarjetas_de_sin_asignar_pasan_a_pendiente(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Nueva']);
        $this->assertTrue(ColumnaTablero::find($tarea->columna_id)->fija);

        foreach (self::MIGRACIONES as $ruta) {
            $this->artisan('migrate:rollback', ['--path' => $ruta])->assertExitCode(0);
        }

        $pendiente = DB::table('columnas_tablero')->where('nombre', 'Pendiente')->value('id');
        $this->assertSame($pendiente, (int) DB::table('tareas')->where('id', $tarea->id)->value('columna_id'));
        $this->assertSame(3, DB::table('columnas_tablero')->count());
    }

    // ---------- Un solo principal ----------

    public function test_siempre_hay_exactamente_un_principal(): void
    {
        $this->assertSame(1, Tablero::where('principal', true)->count());

        $facultad = $this->nuevoTablero();
        $this->assertFalse($facultad->principal, 'Un tablero nuevo no es principal.');
        $this->assertSame(1, Tablero::where('principal', true)->count());

        $this->patch(route('tableros.principal', $facultad))->assertRedirect();
        $this->assertSame([$facultad->id], Tablero::where('principal', true)->pluck('id')->all());

        $this->patch(route('tableros.principal', Tablero::where('nombre', 'Principal')->firstOrFail()))->assertRedirect();
        $this->assertSame(1, Tablero::where('principal', true)->count());
        $this->assertSame('Principal', $this->principal()->nombre);
    }

    public function test_un_tablero_nuevo_trae_sin_asignar_primera_y_las_columnas_de_fabrica(): void
    {
        $facultad = $this->nuevoTablero();

        $columnas = ColumnaTablero::ordenadas()->where('tablero_id', $facultad->id)->get();
        $this->assertSame(['Sin asignar', 'Pendiente', 'En progreso', 'Completada'], $columnas->pluck('nombre')->all());
        $this->assertTrue($columnas[0]->fija);
    }

    public function test_crear_y_renombrar_validan_el_nombre_dentro_del_usuario(): void
    {
        $this->post(route('tableros.store'), ['nombre' => ''])->assertSessionHasErrors('nombre');
        $this->post(route('tableros.store'), ['nombre' => 'Principal'])->assertSessionHasErrors('nombre');

        $facultad = $this->nuevoTablero();
        $this->patch(route('tableros.update', $facultad), ['nombre' => 'Facultad'])->assertSessionHasNoErrors();
        $this->patch(route('tableros.update', $facultad), ['nombre' => 'Principal'])->assertSessionHasErrors('nombre');
        $this->patch(route('tableros.update', $facultad), ['nombre' => 'Carrera'])->assertRedirect();
        $this->assertSame('Carrera', $facultad->fresh()->nombre);
    }

    // ---------- Eliminar un tablero ----------

    public function test_eliminar_un_tablero_pasa_sus_tarjetas_a_sin_asignar_del_destino(): void
    {
        $facultad = $this->nuevoTablero();
        $enFacultad = ColumnaTablero::where('tablero_id', $facultad->id)->where('categoria', 'en_progreso')->firstOrFail();
        $tarea = Tarea::factory()->create(['columna_id' => $enFacultad->id]);
        $nota = Nota::factory()->create(['columna_id' => $enFacultad->id]);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);

        $this->delete(route('tableros.destroy', $facultad))->assertSessionHasErrors('destino_id');
        $this->assertDatabaseHas('tableros', ['id' => $facultad->id]);

        $this->delete(route('tableros.destroy', $facultad), ['destino_id' => $facultad->id])->assertSessionHasErrors('destino_id');

        $principal = $this->principal();
        $this->delete(route('tableros.destroy', $facultad), ['destino_id' => $principal->id])->assertRedirect(route('tablero.index', ['tablero' => $principal->id]));

        $this->assertDatabaseMissing('tableros', ['id' => $facultad->id]);
        $this->assertDatabaseMissing('columnas_tablero', ['id' => $enFacultad->id]);
        $sinAsignar = ColumnaTablero::sinAsignarDe($principal->id);
        $this->assertSame($sinAsignar->id, $tarea->fresh()->columna_id);
        $this->assertSame(EstadoTarea::Pendiente, $tarea->fresh()->estado);
        $this->assertSame($sinAsignar->id, $nota->fresh()->columna_id);
    }

    public function test_eliminar_el_principal_deja_como_principal_al_de_destino(): void
    {
        $facultad = $this->nuevoTablero();
        $principal = $this->principal();

        $this->delete(route('tableros.destroy', $principal), ['destino_id' => $facultad->id])->assertRedirect();

        $this->assertSame([$facultad->id], Tablero::where('principal', true)->pluck('id')->all());
        $this->assertSame(1, Tablero::count());
    }

    public function test_no_se_puede_eliminar_el_ultimo_tablero(): void
    {
        $unico = $this->principal();
        $otro = User::factory()->create();
        $destino = Tablero::withoutGlobalScopes()->where('user_id', $otro->id)->firstOrFail();

        // Ni sin destino ni con el de otra persona.
        $this->delete(route('tableros.destroy', $unico))->assertSessionHasErrors();
        $this->delete(route('tableros.destroy', $unico), ['destino_id' => $destino->id])->assertSessionHasErrors();

        $this->assertDatabaseHas('tableros', ['id' => $unico->id]);
        $this->get(route('tablero.index'))->assertOk()->assertSee('Es tu único tablero: no se puede eliminar.');
    }

    // ---------- La columna fija ----------

    public function test_sin_asignar_no_se_renombra_ni_se_cambia_de_tipo_ni_se_elimina_ni_se_mueve(): void
    {
        $fija = ColumnaTablero::where('fija', true)->firstOrFail();
        $otra = ColumnaTablero::where('nombre', 'En progreso')->firstOrFail();

        $this->patchJson(route('tablero.columnas.update', $fija), ['nombre' => 'Otra cosa'])->assertStatus(422)->assertJsonValidationErrors('columna');
        $this->patchJson(route('tablero.columnas.update', $fija), ['categoria' => 'completada'])->assertStatus(422);
        $this->deleteJson(route('tablero.columnas.destroy', $fija), ['reasignar_a' => $otra->id])->assertStatus(422)->assertJsonValidationErrors('columna');
        $this->patchJson(route('tablero.columnas.mover', $fija), ['direccion' => 'derecha'])->assertStatus(422);

        $this->assertSame('Sin asignar', $fija->fresh()->nombre);
        $this->assertSame('Sin asignar', ColumnaTablero::ordenadas()->first()->nombre);
    }

    public function test_el_menu_de_sin_asignar_explica_que_es_fija(): void
    {
        $html = $this->get(route('tablero.index'))->assertOk()->getContent();

        $this->assertStringContainsString('"Sin asignar" es fija', $html);
    }

    // ---------- Creación fuera del tablero y dentro de uno ----------

    public function test_lo_que_se_crea_fuera_del_tablero_cae_en_sin_asignar_del_principal(): void
    {
        $facultad = $this->nuevoTablero();
        $principal = $this->principal();

        // Hoy (nota rápida), calendario y la nota de la página Notas.
        $this->withHeaders(['HX-Request' => 'true'])->post(route('hoy.captura'), ['tipo' => 'tarea', 'titulo' => 'Desde Hoy'])->assertOk();
        $this->withHeaders(['HX-Request' => 'true'])->post(route('hoy.captura'), ['tipo' => 'nota', 'titulo' => 'Nota de Hoy'])->assertOk();
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'tarea'])->assertCreated();
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'nota'])->assertCreated();
        $this->post(route('notas.store'), ['contenido' => 'Desde Notas'])->assertRedirect();
        $this->post(route('tareas.store'), ['titulo' => 'Formulario', 'prioridad' => 'media', 'estado' => 'pendiente'])->assertRedirect();

        $sinAsignar = ColumnaTablero::sinAsignarDe($principal->id)->id;
        $this->assertSame([$sinAsignar], Tarea::pluck('columna_id')->unique()->values()->all());
        $this->assertSame([$sinAsignar], Nota::pluck('columna_id')->unique()->values()->all());
        $this->assertSame(3, Tarea::count());
        $this->assertSame(3, Nota::count());

        // Si el principal cambia, lo nuevo va al "Sin asignar" del nuevo principal.
        $this->patch(route('tableros.principal', $facultad))->assertRedirect();
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'nota'])->assertCreated();
        $this->assertSame(ColumnaTablero::sinAsignarDe($facultad->id)->id, Nota::latest('id')->first()->columna_id);
    }

    public function test_el_formulario_de_tareas_permite_elegir_otro_tablero(): void
    {
        $facultad = $this->nuevoTablero();

        $this->get(route('tareas.create'))->assertOk()->assertSee('name="tablero_id"', false)->assertSee('Facultad');

        $this->post(route('tareas.store'), ['titulo' => 'En otro tablero', 'prioridad' => 'media', 'estado' => 'pendiente', 'tablero_id' => $facultad->id])->assertRedirect();
        $tarea = Tarea::where('titulo', 'En otro tablero')->firstOrFail();
        $this->assertSame(ColumnaTablero::sinAsignarDe($facultad->id)->id, $tarea->columna_id);

        // Editarla con el mismo tablero no la mueve; con otro, sí.
        $this->patch(route('tareas.update', $tarea), ['titulo' => 'En otro tablero', 'prioridad' => 'alta', 'estado' => 'pendiente', 'tablero_id' => $facultad->id])->assertRedirect();
        $this->assertSame(ColumnaTablero::sinAsignarDe($facultad->id)->id, $tarea->fresh()->columna_id);
        $this->patch(route('tareas.update', $tarea), ['titulo' => 'En otro tablero', 'prioridad' => 'alta', 'estado' => 'pendiente', 'tablero_id' => $this->principal()->id])->assertRedirect();
        $this->assertSame(ColumnaTablero::sinAsignarDe($this->principal()->id)->id, $tarea->fresh()->columna_id);

        // Un tablero ajeno se rechaza.
        $ajeno = Tablero::withoutGlobalScopes()->where('user_id', '!=', auth()->id())->first()
            ?? Tablero::crearConColumnas(User::factory()->create()->id, 'Ajeno', true, [], 0);
        $this->post(route('tareas.store'), ['titulo' => 'X', 'prioridad' => 'media', 'estado' => 'pendiente', 'tablero_id' => $ajeno->id])->assertSessionHasErrors('tablero_id');
    }

    public function test_el_dialogo_y_la_nota_rapida_ofrecen_las_columnas_de_todos_los_tableros_agrupadas(): void
    {
        $this->nuevoTablero('Facultad');

        foreach ([route('tablero.index'), route('hoy')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('<optgroup label="Principal (principal)">', $html, $url);
            $this->assertStringContainsString('<optgroup label="Facultad">', $html, $url);
        }

        $this->get(route('notas.create'))->assertOk()->assertSee('<optgroup label="Facultad">', false)->assertSee('Tablero principal › Sin asignar');
    }

    public function test_un_tablero_ajeno_no_se_ve_ni_se_toca(): void
    {
        $otro = User::factory()->create();
        $ajeno = Tablero::withoutGlobalScopes()->where('user_id', $otro->id)->firstOrFail();

        $this->patch(route('tableros.update', $ajeno), ['nombre' => 'Mío'])->assertNotFound();
        $this->patch(route('tableros.principal', $ajeno))->assertNotFound();
        $this->delete(route('tableros.destroy', $ajeno), ['destino_id' => $this->principal()->id])->assertNotFound();
        $this->assertSame('Principal', $ajeno->fresh()->nombre);

        // ?tablero= con un id ajeno no lo muestra: cae en el principal.
        $this->get(route('tablero.index', ['tablero' => $ajeno->id]))->assertOk()->assertViewHas('tableroActual', fn ($t) => $t->is($this->principal()));
    }

    // ---------- El estado sigue al tablero de la tarjeta ----------

    public function test_el_estado_se_sincroniza_con_las_columnas_del_propio_tablero(): void
    {
        $facultad = $this->nuevoTablero();
        $enCurso = ColumnaTablero::where('tablero_id', $facultad->id)->where('categoria', 'en_progreso')->firstOrFail();
        $tarea = Tarea::factory()->create(['columna_id' => $enCurso->id]);

        // Completar por la ruta de Hoy / calendario: va a la completada de SU tablero, no a la del principal.
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        $hecha = ColumnaTablero::where('tablero_id', $facultad->id)->where('categoria', 'completada')->firstOrFail();
        $this->assertSame($hecha->id, $tarea->fresh()->columna_id);

        // Reabrirla la devuelve a la columna que tenía en SU tablero antes de completarse.
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'pendiente'])->assertOk();
        $this->assertSame($enCurso->id, $tarea->fresh()->columna_id);

        // Moverla a una columna fija o de otro tablero ajusta el estado a la categoría de esa columna.
        $this->patchJson(route('tareas.columna', $tarea), ['columna_id' => $enCurso->id])->assertOk()->assertJson(['estado' => 'en_progreso']);
    }

    public function test_el_tablero_muestra_solo_las_columnas_del_tablero_elegido_y_recuerda_el_ultimo(): void
    {
        $facultad = $this->nuevoTablero();
        Tarea::factory()->create(['titulo' => 'Del principal']);
        Tarea::factory()->create(['titulo' => 'De facultad', 'columna_id' => ColumnaTablero::sinAsignarDe($facultad->id)->id]);

        $this->get(route('tablero.index', ['tablero' => $facultad->id]))->assertOk()
            ->assertSee('De facultad')->assertDontSee('Del principal')
            ->assertSee('aria-current="page"', false);

        // Sin parámetro, vuelve al último que se miró.
        $this->get(route('tablero.index'))->assertOk()->assertSee('De facultad')->assertDontSee('Del principal');
        $this->get(route('tablero.index', ['tablero' => $this->principal()->id]))->assertOk()->assertSee('Del principal');
    }

    // ---------- Consultas ----------

    public function test_el_tablero_con_tarjetas_mezcladas_no_hace_consultas_por_tarjeta(): void
    {
        $contexto = \App\Models\Contexto::factory()->create(['color' => '#5f86a3']);
        $consultas = function (int $tarjetas) use ($contexto): int {
            $enProgreso = ColumnaTablero::where('categoria', 'en_progreso')->firstOrFail();
            foreach (range(1, $tarjetas) as $i) {
                $tarea = Tarea::factory()->create(['contexto_id' => $contexto->id, 'columna_id' => $enProgreso->id]);
                $nota = Nota::factory()->create(['contexto_id' => $contexto->id, 'columna_id' => $enProgreso->id]);
                $tarea->notas()->attach($nota);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('tablero.index'))->assertOk();
            DB::disableQueryLog();

            // Los contextos se cargan completos una sola vez (las demás lecturas son las de eager loading por ids).
            $completas = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'from "contextos"') && ! str_contains($q['query'], ' in ('));
            $this->assertCount(1, $completas, 'El tablero debe cargar todos los contextos en una sola consulta.');

            return count(DB::getQueryLog());
        };

        $pocas = $consultas(2);
        $muchas = $consultas(20);

        $this->assertSame($pocas, $muchas, 'Las consultas no deben crecer con la cantidad de tarjetas.');
    }
}
