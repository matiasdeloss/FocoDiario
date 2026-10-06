<?php

namespace Tests\Feature;

use App\Models\ColumnaTablero;
use App\Models\Nota;
use App\Models\Tablero;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Las notas también son tarjetas del tablero, y se vinculan a tareas (muchos a muchos). */
class NotasEnTableroTest extends TestCase
{
    use RefreshDatabase;

    private function columna(string $categoria): ColumnaTablero
    {
        return ColumnaTablero::where('categoria', $categoria)->where('fija', false)->firstOrFail();
    }

    private function modal(): array
    {
        return ['X-Modal' => '1'];
    }

    // ---------- Notas como tarjetas ----------

    public function test_el_tablero_muestra_notas_y_tareas_con_su_badge_en_la_columna_donde_estan(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Una tarea']);
        $nota = Nota::factory()->create(['titulo' => 'Una nota', 'contenido' => 'Texto de la nota']);
        $enCurso = Nota::factory()->create(['titulo' => 'Nota en curso', 'columna_id' => $this->columna('en_progreso')->id]);

        $respuesta = $this->get(route('tablero.index'))->assertOk();
        $html = $respuesta->getContent();

        // Una nota y una tarea en "Sin asignar" (ordenadas juntas) y la otra nota en su columna.
        $columnas = $respuesta->viewData('columnas');
        $this->assertEqualsCanonicalizing(['Una tarea', 'Una nota'], $columnas[0]['tarjetas']->map(fn ($t) => $t instanceof Nota ? $t->tituloVisible() : $t->titulo)->all());
        $this->assertSame(['Nota en curso'], $columnas[2]['tarjetas']->map->tituloVisible()->all());

        // El badge distingue Nota de Tarea (con texto accesible) y cada tarjeta lleva su tipo.
        $this->assertSame(1, substr_count($html, 'k-tipo k-tipo-tarea'));
        $this->assertSame(2, substr_count($html, 'k-tipo k-tipo-nota'));
        $this->assertStringContainsString('data-tipo="nota"', $html);
        $this->assertStringContainsString('data-tipo="tarea"', $html);
        $this->assertStringContainsString('>Nota</span>', $html);
        // La nota se abre en el diálogo de notas y trae su extracto.
        $this->assertStringContainsString('data-abrir-nota="editar"', $html);
        $this->assertStringContainsString('Texto de la nota', $html);
        $this->assertStringContainsString('data-url-columna-nota=', $html);
    }

    public function test_una_nota_se_mueve_por_las_columnas_incluida_completada(): void
    {
        $nota = Nota::factory()->create();
        $hecha = $this->columna('completada');
        $enCurso = $this->columna('en_progreso');

        $this->patchJson(route('notas.columna', $nota), ['columna_id' => $enCurso->id])->assertOk()->assertJson(['columna_id' => $enCurso->id, 'completada' => false]);
        $this->assertFalse($nota->fresh()->estaCompletada());

        $this->patchJson(route('notas.columna', $nota), ['columna_id' => $hecha->id])->assertOk()->assertJson(['completada' => true]);
        $this->assertTrue($nota->fresh()->load('columna')->estaCompletada());

        // Sin columna válida o con la de otro usuario, se rechaza.
        $this->patchJson(route('notas.columna', $nota), ['columna_id' => 99999])->assertStatus(422)->assertJsonValidationErrors('columna_id');
        $ajena = ColumnaTablero::withoutGlobalScopes()->where('user_id', '!=', auth()->id())->first()
            ?? ColumnaTablero::withoutGlobalScopes()->where('user_id', User::factory()->create()->id)->firstOrFail();
        $this->patchJson(route('notas.columna', $nota), ['columna_id' => $ajena->id])->assertStatus(422);
        $this->assertSame($hecha->id, $nota->fresh()->columna_id);
    }

    public function test_el_orden_de_una_columna_mezcla_notas_y_tareas(): void
    {
        $columna = $this->columna('en_progreso');
        $tarea = Tarea::factory()->create(['columna_id' => $columna->id, 'fecha_limite' => null]);
        $nota = Nota::factory()->create(['columna_id' => $columna->id]);
        $otra = Tarea::factory()->create(['columna_id' => $columna->id, 'fecha_limite' => null]);

        $this->patchJson(route('tareas.columna', $tarea), [
            'columna_id' => $columna->id,
            'tarjetas' => ['nota:'.$nota->id, 'tarea:'.$otra->id, 'tarea:'.$tarea->id],
        ])->assertOk();

        $this->assertSame([1, 2, 3], [$nota->fresh()->orden, $otra->fresh()->orden, $tarea->fresh()->orden]);

        $fichas = collect($this->get(route('tablero.index'))->viewData('columnas')[2]['tarjetas'])->map(fn ($t) => ($t instanceof Nota ? 'nota:' : 'tarea:').$t->id)->all();
        $this->assertSame(['nota:'.$nota->id, 'tarea:'.$otra->id, 'tarea:'.$tarea->id], $fichas);

        // Un formato inválido se rechaza.
        $this->patchJson(route('notas.columna', $nota), ['columna_id' => $columna->id, 'tarjetas' => ['algo']])->assertStatus(422)->assertJsonValidationErrors('tarjetas.0');
    }

    public function test_el_alta_rapida_de_una_columna_crea_una_nota_o_una_tarea(): void
    {
        $columna = $this->columna('en_progreso');

        $this->postJson(route('tablero.columnas.tarjetas.store', $columna), ['titulo' => 'Mi nota', 'tipo' => 'nota'])
            ->assertCreated()->assertJsonStructure(['id', 'html']);
        $this->postJson(route('tablero.columnas.tarjetas.store', $columna), ['titulo' => 'Mi tarea'])->assertCreated();
        $this->postJson(route('tablero.columnas.tarjetas.store', $columna), ['titulo' => 'X', 'tipo' => 'otra'])->assertStatus(422)->assertJsonValidationErrors('tipo');

        $nota = Nota::where('titulo', 'Mi nota')->firstOrFail();
        $this->assertSame($columna->id, $nota->columna_id);
        $this->assertSame($columna->id, Tarea::where('titulo', 'Mi tarea')->firstOrFail()->columna_id);
        $this->assertGreaterThan(0, $nota->orden);
    }

    public function test_la_nota_completada_se_ve_atenuada_en_notas_y_como_hecha_en_el_calendario(): void
    {
        $abierta = Nota::factory()->create(['titulo' => 'Abierta', 'fecha' => '2026-10-10']);
        $hecha = Nota::factory()->create(['titulo' => 'Cerrada', 'fecha' => '2026-10-11', 'columna_id' => $this->columna('completada')->id]);

        $html = $this->get(route('notas.index'))->assertOk()->getContent();
        // Sigue en la lista, con su marca; la abierta no la lleva.
        $this->assertStringContainsString('Cerrada', $html);
        $this->assertSame(1, substr_count($html, 'class="nota-marca-completada"'));
        $this->assertSame(1, substr_count($html, 'nota-completada'));

        $eventos = collect($this->getJson(route('calendario.eventos', ['start' => '2026-10-01T00:00:00', 'end' => '2026-11-01T00:00:00', 'tipos' => 'nota']))->assertOk()->json());
        $this->assertContains('ev-hecho', $eventos->firstWhere('title', 'Cerrada')['classNames']);
        $this->assertNotContains('ev-hecho', $eventos->firstWhere('title', 'Abierta')['classNames']);
        $this->assertTrue($eventos->firstWhere('title', 'Cerrada')['extendedProps']['completada']);
    }

    public function test_el_dialogo_de_la_nota_cambia_su_columna_y_sin_elegir_conserva_la_actual(): void
    {
        $enCurso = $this->columna('en_progreso');
        $nota = Nota::factory()->create(['columna_id' => $enCurso->id]);

        // Sin columna elegida (vacío) no la pierde.
        $this->putJson(route('notas.update', $nota), ['contenido' => 'Editada', 'columna_id' => ''])->assertOk();
        $this->assertSame($enCurso->id, $nota->fresh()->columna_id);

        $hecha = $this->columna('completada');
        $this->putJson(route('notas.update', $nota), ['contenido' => 'Editada', 'columna_id' => $hecha->id])->assertOk();
        $this->assertSame($hecha->id, $nota->fresh()->columna_id);

        $this->putJson(route('notas.update', $nota), ['contenido' => 'x', 'columna_id' => 99999])->assertStatus(422)->assertJsonValidationErrors('columna_id');
    }

    // ---------- Notas vinculadas ----------

    public function test_una_tarea_vincula_y_desvincula_notas_desde_su_dialogo(): void
    {
        $tarea = Tarea::factory()->create();
        $a = Nota::factory()->create(['titulo' => 'Apunte A']);
        $b = Nota::factory()->create(['titulo' => 'Apunte B']);

        $datos = ['titulo' => $tarea->titulo, 'prioridad' => 'media', 'columna_id' => $tarea->columna_id];

        $this->patchJson(route('tareas.update', $tarea), $datos + ['notas' => [$a->id, $b->id]], $this->modal())->assertOk();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $tarea->fresh()->notas->pluck('id')->all());

        // Reemplaza el conjunto: desvincula las que no vienen.
        $this->patchJson(route('tareas.update', $tarea), $datos + ['notas' => [$b->id]], $this->modal())->assertOk();
        $this->assertSame([$b->id], $tarea->fresh()->notas->pluck('id')->all());

        // Sin el campo, no toca los vínculos.
        $this->patchJson(route('tareas.update', $tarea), $datos, $this->modal())->assertOk();
        $this->assertSame([$b->id], $tarea->fresh()->notas->pluck('id')->all());

        $this->patchJson(route('tareas.update', $tarea), $datos + ['notas' => []], $this->modal())->assertOk();
        $this->assertCount(0, $tarea->fresh()->notas);

        // Una nota puede servir a varias tareas.
        $otra = Tarea::factory()->create();
        $this->patchJson(route('tareas.update', $tarea), $datos + ['notas' => [$a->id]], $this->modal())->assertOk();
        $this->patchJson(route('tareas.update', $otra), ['titulo' => 'Otra', 'prioridad' => 'media', 'columna_id' => $otra->columna_id, 'notas' => [$a->id]], $this->modal())->assertOk();
        $this->assertEqualsCanonicalizing([$tarea->id, $otra->id], $a->fresh()->tareas->pluck('id')->all());
    }

    public function test_no_se_pueden_vincular_notas_de_otro_usuario_ni_repetidas(): void
    {
        $tarea = Tarea::factory()->create();
        $ajena = Nota::factory()->create();
        Nota::withoutGlobalScopes()->whereKey($ajena->id)->update(['user_id' => User::factory()->create()->id]);
        $propia = Nota::factory()->create();

        $datos = ['titulo' => 'x', 'prioridad' => 'media', 'columna_id' => $tarea->columna_id];

        $this->patchJson(route('tareas.update', $tarea), $datos + ['notas' => [$ajena->id]], $this->modal())
            ->assertStatus(422)->assertJsonValidationErrors('notas.0');
        $this->patchJson(route('tareas.update', $tarea), $datos + ['notas' => [$propia->id, $propia->id]], $this->modal())
            ->assertStatus(422)->assertJsonValidationErrors('notas.0');
        $this->assertCount(0, $tarea->fresh()->notas);

        // Tampoco al crear.
        $this->postJson(route('tareas.store'), $datos + ['notas' => [$ajena->id]], $this->modal())->assertStatus(422);
        $this->assertSame(1, Tarea::count());
    }

    public function test_crear_una_tarea_puede_traer_sus_notas_vinculadas(): void
    {
        $nota = Nota::factory()->create();

        $this->postJson(route('tareas.store'), ['titulo' => 'Con apuntes', 'prioridad' => 'media', 'notas' => [$nota->id]], $this->modal())->assertCreated();

        $this->assertSame([$nota->id], Tarea::where('titulo', 'Con apuntes')->firstOrFail()->notas->pluck('id')->all());
    }

    public function test_la_busqueda_de_notas_para_vincular_solo_trae_las_propias(): void
    {
        Nota::factory()->create(['titulo' => 'Redes: modelo OSI']);
        Nota::factory()->create(['titulo' => 'Cocina', 'contenido' => 'recetas']);
        $ajena = Nota::factory()->create(['titulo' => 'Redes ajenas']);
        Nota::withoutGlobalScopes()->whereKey($ajena->id)->update(['user_id' => User::factory()->create()->id]);

        $titulos = collect($this->getJson(route('notas.buscar', ['q' => 'redes']))->assertOk()->json('notas'))->pluck('titulo')->all();
        $this->assertSame(['Redes: modelo OSI'], $titulos);

        // Sin texto, las más recientes (máximo diez).
        Nota::factory()->count(12)->create();
        $this->assertCount(10, $this->getJson(route('notas.buscar'))->assertOk()->json('notas'));
    }

    public function test_la_tarjeta_muestra_cuantas_notas_tiene_y_las_abre_desde_su_lista(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Con notas']);
        $sin = Tarea::factory()->create(['titulo' => 'Sin notas']);
        $nota = Nota::factory()->create(['titulo' => 'Apunte vinculado']);
        Nota::factory()->create(['titulo' => 'Otro apunte'])->tareas()->attach($tarea);
        $nota->tareas()->attach($tarea);

        $html = $this->get(route('tablero.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="k-notas-vinc"'));
        $this->assertStringContainsString('2<span class="visually-hidden"> notas vinculadas</span>', $html);
        $this->assertStringContainsString('Apunte vinculado', $html);
        // Cada nota de la lista abre su diálogo.
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data-abrir-nota="editar"'));

        // El diálogo de la tarea recibe sus notas.
        $this->assertSame(['Apunte vinculado', 'Otro apunte'], collect($tarea->fresh()->datosModal()['notas'])->pluck('titulo')->sort()->values()->all());
        $this->assertSame([], $sin->fresh()->datosModal()['notas']);
        $this->assertStringContainsString('data-notas-zona', $html);
    }

    public function test_al_borrar_una_nota_o_una_tarea_se_borra_el_vinculo(): void
    {
        $tarea = Tarea::factory()->create();
        $nota = Nota::factory()->create();
        $tarea->notas()->attach($nota);

        $nota->delete();
        $this->assertDatabaseCount('nota_tarea', 0);

        $otra = Nota::factory()->create();
        $tarea->notas()->attach($otra);
        $tarea->delete();
        $this->assertDatabaseCount('nota_tarea', 0);
        $this->assertDatabaseHas('notas', ['id' => $otra->id]);
    }

    // ---------- Fusión de un invitado con tableros ----------

    public function test_al_entrar_el_tablero_del_invitado_se_une_al_principal_y_sus_otros_tableros_pasan_a_la_cuenta(): void
    {
        $cuenta = User::factory()->create(['email' => 'yo@foco.test', 'password' => Hash::make('una-clave-larga')]);
        $this->actingAs($cuenta);
        Tablero::crearConColumnas($cuenta->id, 'Facultad', false, [['Pendiente', \App\Enums\EstadoTarea::Pendiente]], 1);

        $invitado = User::factory()->invitado()->create();
        $this->actingAs($invitado);
        $facultadInvitado = Tablero::crearConColumnas($invitado->id, 'Facultad', false, [['En curso', \App\Enums\EstadoTarea::EnProgreso]], 1);
        $propia = Tablero::crearConColumnas($invitado->id, 'Trabajo', false, [['Hecho', \App\Enums\EstadoTarea::Completada]], 2);
        $revision = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => \App\Enums\EstadoTarea::EnProgreso, 'posicion' => 9]);
        $tarea = Tarea::factory()->create(['columna_id' => $revision->id]);
        $nota = Nota::factory()->create(['columna_id' => ColumnaTablero::sinAsignarDe($propia->id)->id]);
        $suelta = Nota::factory()->create();
        $tarea->notas()->attach([$nota->id, $suelta->id]);

        $this->postJson(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'una-clave-larga'])->assertOk();
        $this->actingAs($cuenta);

        // Exactamente un principal; el principal del invitado se unió al de la cuenta y los otros tableros se sumaron.
        $this->assertSame(1, Tablero::where('principal', true)->count());
        $this->assertSame(['Principal', 'Facultad', 'Facultad (invitado)', 'Trabajo'], Tablero::ordenados()->pluck('nombre')->all());
        $principal = Tablero::where('principal', true)->firstOrFail();
        $this->assertSame(['Sin asignar', 'Pendiente', 'En progreso', 'Completada', 'Revisión'], ColumnaTablero::ordenadas()->where('tablero_id', $principal->id)->pluck('nombre')->all());
        $this->assertSame(1, ColumnaTablero::where('tablero_id', $principal->id)->where('fija', true)->count());

        // Las tarjetas y los vínculos se conservan; las notas siguen en la columna de su tablero.
        $this->assertSame($principal->id, Tarea::find($tarea->id)->columna->tablero_id);
        $this->assertSame($propia->id, Nota::find($nota->id)->columna->tablero_id);
        $this->assertSame(ColumnaTablero::sinAsignarDe($principal->id)->id, Nota::find($suelta->id)->columna_id);
        $this->assertEqualsCanonicalizing([$nota->id, $suelta->id], Tarea::find($tarea->id)->notas->pluck('id')->all());
        $this->assertNull(User::find($invitado->id));
        $this->assertSame(0, Tablero::withoutGlobalScopes()->where('user_id', $invitado->id)->count());
        $this->assertNotNull($facultadInvitado);
    }

    public function test_editar_una_nota_con_la_columna_donde_esta_la_tarjeta_no_la_devuelve_a_la_vieja(): void
    {
        $nota = Nota::factory()->create(['titulo' => 'Mover']);
        $enProgreso = $this->columna('en_progreso');

        $this->patchJson(route('notas.columna', $nota), ['columna_id' => $enProgreso->id])->assertOk();
        // El diálogo manda la columna que muestra el tablero (resources/js/notas.js), no la del data-nota viejo.
        $this->putJson(route('notas.update', $nota), ['contenido' => 'Editada', 'columna_id' => $enProgreso->id])->assertOk();

        $this->assertSame($enProgreso->id, $nota->fresh()->columna_id);
    }
}
