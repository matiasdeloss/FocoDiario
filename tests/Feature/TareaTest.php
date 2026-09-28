<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TareaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_se_muestra_y_filtra_por_estado_y_proyecto(): void
    {
        Tarea::factory()->create(['titulo' => 'Tarea A', 'proyecto' => 'Tesis', 'estado' => EstadoTarea::Pendiente]);
        Tarea::factory()->create(['titulo' => 'Tarea B', 'proyecto' => 'Casa', 'estado' => EstadoTarea::Completada]);

        $this->get(route('tareas.index'))->assertOk()->assertSee('Tarea A')->assertSee('Tarea B');

        $this->get(route('tareas.index', ['estado' => 'completada']))
            ->assertOk()->assertSee('Tarea B')->assertDontSee('Tarea A');

        $this->get(route('tareas.index', ['proyecto' => 'Tesis']))
            ->assertOk()->assertSee('Tarea A')->assertDontSee('Tarea B');
    }

    public function test_se_puede_crear_una_tarea(): void
    {
        $this->post(route('tareas.store'), [
            'titulo' => 'Estudiar Laravel',
            'proyecto' => 'Facultad',
            'fecha_limite' => '2026-10-15',
            'prioridad' => 'alta',
            'estado' => 'pendiente',
        ])->assertRedirect(route('tareas.index'));

        $this->assertDatabaseHas('tareas', ['titulo' => 'Estudiar Laravel', 'prioridad' => 'alta']);
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
