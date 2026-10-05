<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Contexto;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TareaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_se_muestra_y_filtra_por_estado_y_contexto(): void
    {
        $tesis = Contexto::factory()->proyecto()->create(['nombre' => 'Tesis']);
        $casa = Contexto::factory()->entorno()->create(['nombre' => 'Casa']);
        Tarea::factory()->create(['titulo' => 'Tarea A', 'contexto_id' => $tesis->id, 'estado' => EstadoTarea::Pendiente]);
        Tarea::factory()->create(['titulo' => 'Tarea B', 'contexto_id' => $casa->id, 'estado' => EstadoTarea::Completada]);

        $this->get(route('tareas.index'))->assertOk()->assertSee('aria-label="Tarea A"', false)->assertSee('aria-label="Tarea B"', false);

        $this->get(route('tareas.index', ['estado' => 'hechas']))
            ->assertOk()->assertSee('aria-label="Tarea B"', false)->assertDontSee('aria-label="Tarea A"', false);

        // Los enlaces de antes (?estado=completada) siguen llevando a las hechas.
        $this->get(route('tareas.index', ['estado' => 'completada']))
            ->assertOk()->assertSee('aria-label="Tarea B"', false)->assertDontSee('aria-label="Tarea A"', false);

        $this->get(route('tareas.index', ['contexto' => $tesis->id]))
            ->assertOk()->assertSee('aria-label="Tarea A"', false)->assertDontSee('aria-label="Tarea B"', false);
    }

    public function test_se_puede_crear_una_tarea(): void
    {
        $facultad = Contexto::factory()->create(['nombre' => 'Facultad']);

        $this->post(route('tareas.store'), [
            'titulo' => 'Estudiar Laravel',
            'contexto_id' => $facultad->id,
            'fecha_limite' => '2026-10-15',
            'prioridad' => 'alta',
            'estado' => 'pendiente',
        ])->assertRedirect(route('tareas.index'));

        $this->assertDatabaseHas('tareas', ['titulo' => 'Estudiar Laravel', 'prioridad' => 'alta', 'contexto_id' => $facultad->id]);
    }

    public function test_no_se_puede_asignar_el_contexto_de_otro_usuario(): void
    {
        $ajeno = Contexto::factory()->create(['nombre' => 'Ajeno']);
        Contexto::withoutGlobalScopes()->whereKey($ajeno->id)->update(['user_id' => User::factory()->create()->id]);

        $this->from(route('tareas.create'))
            ->post(route('tareas.store'), ['titulo' => 'X', 'contexto_id' => $ajeno->id, 'prioridad' => 'media', 'estado' => 'pendiente'])
            ->assertRedirect(route('tareas.create'))
            ->assertSessionHasErrors('contexto_id');
        $this->assertDatabaseCount('tareas', 0);

        $tarea = Tarea::factory()->create();
        $this->patch(route('tareas.update', $tarea), ['titulo' => 'X', 'contexto_id' => $ajeno->id, 'prioridad' => 'media', 'estado' => 'pendiente'])
            ->assertSessionHasErrors('contexto_id');
        $this->assertNull($tarea->fresh()->contexto_id);
    }

    public function test_el_formulario_ofrece_los_cuatro_tipos_de_contexto_y_la_tarea_guarda_el_elegido(): void
    {
        foreach (['entorno', 'materia', 'tema', 'proyecto'] as $tipo) {
            Contexto::factory()->state(['tipo' => $tipo, 'nombre' => "Uno de {$tipo}"])->create();
        }

        $this->get(route('tareas.create'))->assertOk()
            ->assertSee('<optgroup label="Entorno">', false)->assertSee('<optgroup label="Materia">', false)
            ->assertSee('<optgroup label="Tema">', false)->assertSee('<optgroup label="Proyecto">', false)
            ->assertSee('Uno de proyecto');

        $tarea = Tarea::factory()->create(['contexto_id' => Contexto::where('tipo', 'proyecto')->value('id')]);
        $html = $this->get(route('tareas.edit', $tarea))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$tarea->contexto_id.'"\s+selected\s*>Uno de proyecto<\/option>/', $html);
    }

    public function test_la_creacion_se_valida_con_mensajes_en_espanol(): void
    {
        $this->from(route('tareas.create'))
            ->post(route('tareas.store'), ['titulo' => '', 'prioridad' => 'urgente', 'estado' => 'pendiente'])
            ->assertRedirect(route('tareas.create'))
            ->assertSessionHasErrors(['titulo', 'prioridad']);

        $this->assertSame(
            'Escribí un título para la tarea.',
            session('errors')->first('titulo'),
        );
        $this->assertDatabaseCount('tareas', 0);
    }

    public function test_se_puede_editar_y_borrar_una_tarea(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Vieja']);

        $this->put(route('tareas.update', $tarea), [
            'titulo' => 'Nueva',
            'prioridad' => 'baja',
            'estado' => 'en_progreso',
        ])->assertRedirect(route('tareas.index'));

        $this->assertDatabaseHas('tareas', ['id' => $tarea->id, 'titulo' => 'Nueva', 'estado' => 'en_progreso']);

        $this->delete(route('tareas.destroy', $tarea))->assertRedirect(route('tareas.index'));
        $this->assertDatabaseMissing('tareas', ['id' => $tarea->id]);
    }

    public function test_el_estado_cambia_y_con_htmx_devuelve_solo_la_fila(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Cambiar', 'estado' => EstadoTarea::Pendiente]);

        $respuesta = $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('tareas.estado', $tarea), ['estado' => 'completada']);

        $respuesta->assertOk()
            ->assertSee('id="tarea-'.$tarea->id.'"', false)
            ->assertDontSee('<html', false);
        $this->assertSame(EstadoTarea::Completada, $tarea->fresh()->estado);
    }

    public function test_alternar_varias_veces_la_fila_refleja_el_estado_guardado_y_es_coherente(): void
    {
        $tarea = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);
        $id = $tarea->id;

        foreach (['completada', 'pendiente', 'completada', 'pendiente'] as $estado) {
            $html = $this->withHeaders(['HX-Request' => 'true'])
                ->patch(route('tareas.estado', $tarea), ['estado' => $estado])
                ->assertOk()->getContent();

            $this->assertSame($estado, $tarea->fresh()->estado->value);
            $hecha = $estado === 'completada';
            $this->assertStringContainsString('id="tarea-'.$id.'"', $html);
            $this->assertStringContainsString('aria-checked="'.($hecha ? 'true' : 'false').'"', $html);
            $this->assertSame($hecha, str_contains($html, 'es-hecha'));
            // Sin JS, el check envía el estado contrario al actual.
            $this->assertStringContainsString('name="estado" value="'.($hecha ? 'pendiente' : 'completada').'"', $html);
        }
    }

    public function test_el_cambio_de_estado_sin_htmx_redirige_y_valida(): void
    {
        $tarea = Tarea::factory()->create();

        $this->patch(route('tareas.estado', $tarea), ['estado' => 'en_progreso'])->assertRedirect();
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);

        $this->patch(route('tareas.estado', $tarea), ['estado' => 'inventado'])->assertSessionHasErrors('estado');
    }

    public function test_una_tarea_vencida_se_marca_y_una_completada_no(): void
    {
        $vencida = Tarea::factory()->create(['fecha_limite' => today()->subDays(2), 'estado' => EstadoTarea::Pendiente]);
        $hecha = Tarea::factory()->create(['fecha_limite' => today()->subDays(2), 'estado' => EstadoTarea::Completada]);
        $hoy = Tarea::factory()->create(['fecha_limite' => today(), 'estado' => EstadoTarea::Pendiente]);

        $this->assertTrue($vencida->estaVencida());
        $this->assertFalse($hecha->estaVencida());
        $this->assertFalse($hoy->estaVencida());

        $this->get(route('tareas.index'))->assertSee('Vencida');
    }
}
