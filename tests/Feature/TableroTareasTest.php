<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableroTareasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_tablero_responde_y_agrupa_por_estado(): void
    {
        Tarea::factory()->create(['titulo' => 'Tarea pendiente', 'estado' => EstadoTarea::Pendiente]);
        Tarea::factory()->create(['titulo' => 'Tarea en curso', 'estado' => EstadoTarea::EnProgreso]);
        Tarea::factory()->create(['titulo' => 'Tarea lista', 'estado' => EstadoTarea::Completada]);

        $respuesta = $this->get(route('tablero.index'))->assertOk();

        $respuesta->assertViewIs('tablero.index');
        $columnas = $respuesta->viewData('columnas');
        $this->assertCount(3, $columnas);
        $this->assertSame(['Tarea pendiente'], $columnas[0]['tareas']->pluck('titulo')->all());
        $this->assertSame(['Tarea en curso'], $columnas[1]['tareas']->pluck('titulo')->all());
        $this->assertSame(['Tarea lista'], $columnas[2]['tareas']->pluck('titulo')->all());
    }

    public function test_el_tablero_ofrece_filtros_rapidos_y_preselecciona_el_proyecto_de_la_url(): void
    {
        Tarea::factory()->create(['titulo' => 'De tesis', 'proyecto' => 'Tesis', 'prioridad' => 'alta']);
        Tarea::factory()->create(['titulo' => 'De casa', 'proyecto' => 'Casa']);

        $html = $this->get(route('tablero.index', ['proyecto' => 'Tesis']))->assertOk()->getContent();

        $this->assertStringContainsString('data-filtro="q"', $html);
        $this->assertStringContainsString('data-filtro="prioridad"', $html);
        $this->assertMatchesRegularExpression('/<option value="Tesis"\s+selected>/', $html);
        // Cada tarjeta lleva lo que el filtro necesita y sus etiquetas de prioridad y proyecto.
        $this->assertStringContainsString('data-proyecto="Tesis"', $html);
        $this->assertStringContainsString('data-prioridad="alta"', $html);
        $this->assertStringContainsString('k-chip-proyecto', $html);
    }

    public function test_las_tarjetas_muestran_la_fecha_con_su_estado_vencida_u_hoy(): void
    {
        Tarea::factory()->create(['titulo' => 'Atrasada', 'fecha_limite' => today()->subDays(3)]);
        Tarea::factory()->create(['titulo' => 'Para hoy', 'fecha_limite' => today()]);
        Tarea::factory()->create(['titulo' => 'Ya lista', 'fecha_limite' => today()->subDays(3), 'estado' => EstadoTarea::Completada]);

        $html = $this->get(route('tablero.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'k-fecha-vencida'));
        $this->assertSame(1, substr_count($html, 'k-fecha-hoy'));
    }

    public function test_el_orden_manual_de_las_tarjetas_manda_dentro_de_la_columna(): void
    {
        $a = Tarea::factory()->create(['titulo' => 'Primera', 'fecha_limite' => '2026-10-01']);
        $b = Tarea::factory()->create(['titulo' => 'Segunda', 'fecha_limite' => '2026-11-01']);
        $c = Tarea::factory()->create(['titulo' => 'Tercera', 'fecha_limite' => null]);

        // Sin orden manual: por fecha límite, y las sin fecha al final.
        $this->assertSame(['Primera', 'Segunda', 'Tercera'], $this->get(route('tablero.index'))->viewData('columnas')[0]['tareas']->pluck('titulo')->all());

        $this->patchJson(route('tareas.columna', $c), ['columna_id' => $c->columna_id, 'orden' => [$c->id, $b->id, $a->id]])->assertOk();

        $this->assertSame(['Tercera', 'Segunda', 'Primera'], $this->get(route('tablero.index'))->viewData('columnas')[0]['tareas']->pluck('titulo')->all());
        $this->assertSame([1, 2, 3], [$c->fresh()->orden, $b->fresh()->orden, $a->fresh()->orden]);
    }

    public function test_arrastrar_a_otra_columna_guarda_columna_estado_y_posicion(): void
    {
        $enProgreso = ColumnaTablero::where('categoria', 'en_progreso')->firstOrFail();
        $x = Tarea::factory()->create(['titulo' => 'X', 'columna_id' => $enProgreso->id, 'orden' => 1]);
        $y = Tarea::factory()->create(['titulo' => 'Y', 'columna_id' => $enProgreso->id, 'orden' => 2]);
        $movida = Tarea::factory()->create(['titulo' => 'Movida', 'estado' => EstadoTarea::Pendiente]);

        $this->patchJson(route('tareas.columna', $movida), ['columna_id' => $enProgreso->id, 'orden' => [$x->id, $movida->id, $y->id]])
            ->assertOk()->assertJson(['columna_id' => $enProgreso->id, 'estado' => 'en_progreso']);

        $this->assertSame(['X', 'Movida', 'Y'], $this->get(route('tablero.index'))->viewData('columnas')[1]['tareas']->pluck('titulo')->all());
        $this->assertSame(EstadoTarea::EnProgreso, $movida->fresh()->estado);
    }

    public function test_el_orden_no_puede_tocar_tarjetas_de_otra_columna_ni_traer_ids_repetidos(): void
    {
        $ajena = Tarea::factory()->create(['estado' => EstadoTarea::EnProgreso, 'orden' => 7]);
        $propia = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->patchJson(route('tareas.columna', $propia), ['columna_id' => $propia->columna_id, 'orden' => [$ajena->id, $propia->id]])->assertOk();
        $this->assertSame(7, $ajena->fresh()->orden);

        $this->patchJson(route('tareas.columna', $propia), ['columna_id' => $propia->columna_id, 'orden' => [$propia->id, $propia->id]])
            ->assertStatus(422)->assertJsonValidationErrors('orden.0');
    }

    public function test_al_mover_sin_orden_la_tarjeta_queda_arriba_de_la_columna_de_destino(): void
    {
        $enProgreso = ColumnaTablero::where('categoria', 'en_progreso')->firstOrFail();
        $existente = Tarea::factory()->create(['columna_id' => $enProgreso->id, 'orden' => 3]);
        $nueva = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->patchJson(route('tareas.columna', $nueva), ['columna_id' => $enProgreso->id])->assertOk();

        $this->assertLessThan($existente->fresh()->orden, $nueva->fresh()->orden);
    }

    public function test_la_tarjeta_rapida_se_agrega_al_final_de_la_columna(): void
    {
        $columna = ColumnaTablero::where('categoria', 'pendiente')->firstOrFail();
        Tarea::factory()->create(['columna_id' => $columna->id, 'orden' => 4]);

        $id = $this->postJson(route('tablero.columnas.tarjetas.store', $columna), ['titulo' => 'Al pie'])->assertCreated()->json('id');

        $this->assertSame(5, Tarea::findOrFail($id)->orden);
    }

    public function test_el_tablero_tiene_su_entrada_en_el_menu_y_se_marca_activa(): void
    {
        $html = $this->get(route('tablero.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#class="nav-link active" href="'.preg_quote(route('tablero.index'), '#').'"\s+aria-current="page"\s*>Tablero#', $html);
        $this->assertStringNotContainsString('>Recordatorios</a>', $html);
    }

    public function test_la_vista_de_tablero_de_la_url_vieja_redirige_al_tablero(): void
    {
        $this->get(route('tareas.index', ['vista' => 'tablero']))->assertRedirect(route('tablero.index'));
        $this->get(route('tareas.index', ['vista' => 'tablero', 'proyecto' => 'Tesis']))->assertRedirect(route('tablero.index', ['proyecto' => 'Tesis']));
    }

    public function test_las_completadas_se_limitan_a_las_diez_mas_recientes(): void
    {
        foreach (range(1, 12) as $i) {
            Tarea::factory()->create([
                'titulo' => "Hecha {$i}",
                'estado' => EstadoTarea::Completada,
                'updated_at' => now()->subDays(20 - $i),
            ]);
        }

        $respuesta = $this->get(route('tablero.index'))->assertOk();
        $completadas = $respuesta->viewData('columnas')[2];

        $this->assertCount(10, $completadas['tareas']);
        $this->assertSame(2, $completadas['ocultas']);
        $respuesta->assertSee('Hecha 12')->assertDontSee('Hecha 1<', false)->assertDontSee('Hecha 2<', false)
            ->assertSee(route('tareas.index', ['estado' => 'hechas']), false);
    }

    public function test_el_estado_se_cambia_desde_el_tablero_con_json_y_valida(): void
    {
        $tarea = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'en_progreso'])
            ->assertOk()->assertJson(['estado' => 'en_progreso']);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'inventado'])
            ->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);
    }

    public function test_la_lista_no_tiene_selector_de_vista_y_rechaza_vistas_invalidas(): void
    {
        Tarea::factory()->create(['titulo' => 'En la lista']);

        $this->get(route('tareas.index'))->assertOk()->assertSee('En la lista')
            ->assertDontSee('selector-vista', false)->assertDontSee('foco.vistaTareas', false);
        $this->get(route('tareas.index', ['vista' => 'otra']))->assertSessionHasErrors('vista');
    }
}
