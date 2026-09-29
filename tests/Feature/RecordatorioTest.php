<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Recordatorio;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordatorioTest extends TestCase
{
    use RefreshDatabase;

    public function test_recordatorios_redirige_a_la_lista_unificada(): void
    {
        $this->get(route('recordatorios.index'))->assertRedirect(route('tareas.index', ['tipo' => 'recordatorio']));
    }

    public function test_el_listado_muestra_primero_los_pendientes_ordenados_por_fecha(): void
    {
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Avisado viejo', 'recordar_en' => now()->addDay(), 'avisado_en' => now()]);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Pendiente lejano', 'recordar_en' => now()->addDays(5)]);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Pendiente cercano', 'recordar_en' => now()->addHour()]);

        $this->get(route('tareas.index', ['tipo' => 'recordatorio']))
            ->assertOk()
            ->assertSeeInOrder(['Pendiente cercano', 'Pendiente lejano', 'Completadas', 'Avisado viejo']);
    }

    public function test_se_puede_crear_con_y_sin_tarea(): void
    {
        $tarea = Tarea::factory()->create();

        $this->post(route('recordatorios.store'), [
            'mensaje' => 'Llamar al profesor',
            'recordar_en' => '2026-10-01T09:30',
            'tarea_id' => $tarea->id,
        ])->assertRedirect(route('tareas.index'));

        $this->post(route('recordatorios.store'), [
            'mensaje' => 'Sin tarea',
            'recordar_en' => '2026-10-02T10:00',
        ])->assertRedirect(route('tareas.index'));

        $this->assertDatabaseHas('recordatorios', ['mensaje' => 'Llamar al profesor', 'tarea_id' => $tarea->id]);
        $this->assertDatabaseHas('recordatorios', ['mensaje' => 'Sin tarea', 'tarea_id' => null]);
    }

    public function test_valida_los_campos_obligatorios(): void
    {
        $this->post(route('recordatorios.store'), ['mensaje' => '', 'recordar_en' => 'mañana', 'tarea_id' => 999])
            ->assertSessionHasErrors(['mensaje', 'recordar_en', 'tarea_id']);
    }

    public function test_el_formulario_solo_ofrece_tareas_abiertas(): void
    {
        Tarea::factory()->create(['titulo' => 'Abierta', 'estado' => EstadoTarea::Pendiente]);
        Tarea::factory()->create(['titulo' => 'Cerrada', 'estado' => EstadoTarea::Completada]);

        $this->get(route('recordatorios.create'))->assertOk()->assertSee('Abierta')->assertDontSee('Cerrada');
    }

    public function test_se_puede_editar_avisar_y_borrar(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Original']);

        $this->put(route('recordatorios.update', $recordatorio), [
            'mensaje' => 'Editado',
            'recordar_en' => '2026-11-01T08:00',
        ])->assertRedirect(route('tareas.index'));
        $this->assertDatabaseHas('recordatorios', ['id' => $recordatorio->id, 'mensaje' => 'Editado']);

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('recordatorios.avisar', $recordatorio))
            ->assertOk()->assertSee('aria-checked="true"', false)->assertDontSee('<html', false);
        $this->assertNotNull($recordatorio->fresh()->avisado_en);

        $this->withoutHeader("HX-Request")->delete(route("recordatorios.destroy", $recordatorio))->assertRedirect(route('tareas.index'));
        $this->assertDatabaseMissing('recordatorios', ['id' => $recordatorio->id]);
    }
}
