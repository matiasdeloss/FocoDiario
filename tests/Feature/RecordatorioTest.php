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

    public function test_vencidos_devuelve_los_que_ya_llegaron_y_no_se_avisaron(): void
    {
        $this->travelTo('2026-09-30 15:00:00');

        $llego = Recordatorio::factory()->create(['mensaje' => 'Llamar al tutor', 'recordar_en' => '2026-09-30 14:55:00']);
        $justo = Recordatorio::factory()->create(['mensaje' => 'Justo ahora', 'recordar_en' => '2026-09-30 15:00:00']);
        Recordatorio::factory()->create(['mensaje' => 'Más tarde', 'recordar_en' => '2026-09-30 15:01:00']);
        Recordatorio::factory()->create(['mensaje' => 'Ya avisado', 'recordar_en' => '2026-09-30 14:00:00', 'avisado_en' => '2026-09-30 14:00:00']);
        Recordatorio::factory()->create(['mensaje' => 'De hace dos días', 'recordar_en' => '2026-09-28 10:00:00']);
        Recordatorio::factory()->create(['mensaje' => 'Sin fecha', 'recordar_en' => null]);

        $this->getJson(route('recordatorios.vencidos'))
            ->assertOk()
            ->assertJsonCount(2, 'recordatorios')
            ->assertJsonPath('recordatorios.0.id', $llego->id)
            ->assertJsonPath('recordatorios.0.mensaje', 'Llamar al tutor')
            ->assertJsonPath('recordatorios.0.hora', '14:55')
            ->assertJsonPath('recordatorios.0.url_avisar', route('recordatorios.avisar', $llego))
            ->assertJsonPath('recordatorios.0.url_posponer', route('recordatorios.posponer', $llego))
            ->assertJsonPath('recordatorios.1.id', $justo->id);
    }

    public function test_vencidos_no_devuelve_mas_de_cinco(): void
    {
        $this->travelTo('2026-09-30 15:00:00');
        Recordatorio::factory()->count(8)->create(['recordar_en' => '2026-09-30 14:00:00']);

        $this->getJson(route('recordatorios.vencidos'))->assertJsonCount(5, 'recordatorios');
    }

    public function test_el_toast_puede_marcar_como_listo_o_posponer(): void
    {
        $this->travelTo('2026-09-30 15:00:00');
        $recordatorio = Recordatorio::factory()->create(['recordar_en' => '2026-09-30 14:55:00']);

        // "10 min más": se cuenta desde ahora (hora del servidor) y deja de estar vencido.
        $this->patchJson(route('recordatorios.posponer', $recordatorio))
            ->assertOk()
            ->assertJson(['recordar_en' => '2026-09-30T15:10']);
        $this->getJson(route('recordatorios.vencidos'))->assertJsonCount(0, 'recordatorios');

        // Cuando vuelve a llegar la hora, "Listo" lo marca como avisado.
        $this->travelTo('2026-09-30 15:10:00');
        $this->getJson(route('recordatorios.vencidos'))->assertJsonCount(1, 'recordatorios');
        $this->patchJson(route('recordatorios.avisar', $recordatorio))->assertOk()->assertJson(['avisado' => true]);
        $this->getJson(route('recordatorios.vencidos'))->assertJsonCount(0, 'recordatorios');
    }

    public function test_posponer_valida_los_minutos_y_no_toca_los_ya_avisados(): void
    {
        $this->travelTo('2026-09-30 15:00:30');
        $recordatorio = Recordatorio::factory()->create(['recordar_en' => '2026-09-30 14:55:00']);

        $this->patchJson(route('recordatorios.posponer', $recordatorio), ['minutos' => 0])
            ->assertJsonValidationErrors(['minutos' => 'Se puede posponer entre 1 minuto y 24 horas.']);
        $this->patchJson(route('recordatorios.posponer', $recordatorio), ['minutos' => 30])
            ->assertOk()->assertJson(['recordar_en' => '2026-09-30T15:30']);

        $avisado = Recordatorio::factory()->create(['recordar_en' => '2026-09-30 14:00:00', 'avisado_en' => '2026-09-30 14:00:00']);
        $this->patchJson(route('recordatorios.posponer', $avisado))->assertStatus(422);
        $this->assertSame('2026-09-30 14:00', $avisado->fresh()->recordar_en->format('Y-m-d H:i'));
    }

    public function test_el_layout_publica_la_url_de_los_vencidos(): void
    {
        $this->get(route('tareas.index'))->assertSee('<meta name="url-recordatorios-vencidos" content="'.route('recordatorios.vencidos').'">', false);
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
