<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetalleCalendarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_detalle_de_una_tarea(): void
    {
        $tarea = Tarea::factory()->create([
            'titulo' => 'Entregar informe', 'descripcion' => 'Con anexos', 'proyecto' => 'Tesis',
            'prioridad' => 'alta', 'fecha_limite' => '2026-10-10',
        ]);

        $this->getJson(route('calendario.detalle', ['tipo' => 'tarea', 'id' => $tarea->id]))
            ->assertOk()
            ->assertJsonPath('detalle.tipo', 'tarea')
            ->assertJsonPath('detalle.titulo', 'Entregar informe')
            ->assertJsonPath('detalle.comentario', 'Con anexos')
            ->assertJsonPath('detalle.prioridad', 'alta')
            ->assertJsonPath('detalle.fecha', '2026-10-10')
            ->assertJsonPath('detalle.completada', false)
            // Proyecto y estado se editan en el panel (no se repiten en el resumen de solo lectura).
            ->assertJsonPath('detalle.proyecto', 'Tesis')
            ->assertJsonPath('detalle.filas', [])
            ->assertJsonCount(3, 'detalle.prioridades');
    }

    public function test_el_detalle_de_un_recordatorio_avisado_ofrece_marcarlo_como_no_avisado(): void
    {
        $avisado = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-10-05 09:30:00', 'avisado_en' => '2026-10-05 09:31:00']);
        $this->getJson(route('calendario.detalle', ['tipo' => 'recordatorio', 'id' => $avisado->id]))
            ->assertOk()
            ->assertJsonPath('detalle.avisado', true)
            ->assertJsonPath('detalle.url_reactivar', route('recordatorios.reactivar', $avisado))
            ->assertJsonPath('detalle.ayuda_fecha', 'Los recordatorios ya avisados no se pueden reubicar.');

        $pendiente = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => now()->addDay()]);
        $this->getJson(route('calendario.detalle', ['tipo' => 'recordatorio', 'id' => $pendiente->id]))
            ->assertOk()
            ->assertJsonPath('detalle.avisado', false)
            ->assertJsonPath('detalle.url_reactivar', null)
            ->assertJsonPath('detalle.bloqueada', false)
            ->assertJsonPath('detalle.ayuda_fecha', null)
            ->assertJsonPath('detalle.filas.0.valor', 'Todavía no avisó');
    }

    public function test_al_reactivar_un_recordatorio_cuya_hora_ya_paso_el_detalle_dice_que_volvera_a_avisar(): void
    {
        $recordatorio = Recordatorio::factory()->create([
            'tarea_id' => null, 'recordar_en' => now()->subHour(), 'avisado_en' => now()->subMinutes(50),
        ]);

        $this->patchJson(route('recordatorios.reactivar', $recordatorio))
            ->assertOk()->assertExactJson(['id' => $recordatorio->id, 'avisado' => false]);

        $this->getJson(route('calendario.detalle', ['tipo' => 'recordatorio', 'id' => $recordatorio->id]))
            ->assertOk()
            ->assertJsonPath('detalle.avisado', false)
            ->assertJsonPath('detalle.bloqueada', false)
            ->assertJsonPath('detalle.url_reactivar', null)
            ->assertJsonPath('detalle.ayuda_fecha', null)
            ->assertJsonPath('detalle.filas.0.valor', 'Todavía no avisó. Volverá a avisar en el próximo chequeo.');

        // Más de 24 h atrás el aviso periódico ya no lo toma: no se promete nada.
        $viejo = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => now()->subDays(3), 'avisado_en' => null]);
        $this->getJson(route('calendario.detalle', ['tipo' => 'recordatorio', 'id' => $viejo->id]))
            ->assertJsonPath('detalle.filas.0.valor', 'Todavía no avisó');
    }

    public function test_detalle_de_recordatorio_nota_y_sesion(): void
    {
        $avisado = Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Llamar', 'recordar_en' => '2026-10-05 09:30:00', 'avisado_en' => '2026-10-05 09:31:00']);
        $this->getJson(route('calendario.detalle', ['tipo' => 'recordatorio', 'id' => $avisado->id]))
            ->assertOk()
            ->assertJsonPath('detalle.fecha', '2026-10-05T09:30')
            ->assertJsonPath('detalle.bloqueada', true)
            ->assertJsonPath('detalle.filas.0.valor', 'Ya avisó el 05/10/2026 a las 09:31');

        $materia = Contexto::factory()->create(['nombre' => 'Álgebra']);
        $nota = Nota::factory()->create(['titulo' => 'Apuntes', 'contenido' => 'Vectores', 'contexto_id' => $materia->id, 'fecha' => '2026-10-06', 'color' => 'salvia']);
        $this->getJson(route('calendario.detalle', ['tipo' => 'nota', 'id' => $nota->id]))
            ->assertOk()
            ->assertJsonPath('detalle.color', 'salvia')
            ->assertJsonPath('detalle.comentario', 'Vectores')
            ->assertJsonPath('detalle.contexto_id', $materia->id)
            ->assertJsonPath('detalle.filas', []);

        $sesion = SesionEstudio::factory()->create(['tema' => 'Cálculo', 'contexto_id' => $materia->id, 'iniciada_en' => '2026-10-07 10:00:00', 'finalizada_en' => '2026-10-07 11:00:00']);
        IntervaloEstudio::factory()->count(2)->create(['sesion_id' => $sesion->id]);
        $filas = collect($this->getJson(route('calendario.detalle', ['tipo' => 'sesion', 'id' => $sesion->id]))
            ->assertOk()
            ->assertJsonPath('detalle.solo_lectura', true)
            ->assertJsonPath('detalle.historial', route('estudio.historial'))
            ->json('detalle.filas'))->pluck('valor', 'etiqueta');

        $this->assertSame('Álgebra', $filas['Materia']);
        $this->assertSame('25 min', $filas['Foco']);
        $this->assertSame('2', $filas['Pomodoros completados']);
        $this->assertSame('07/10/2026 10:00', $filas['Inicio']);
    }

    public function test_detalle_inexistente_o_de_tipo_invalido(): void
    {
        $this->getJson(route('calendario.detalle', ['tipo' => 'tarea', 'id' => 999]))->assertNotFound();
        $this->getJson('/calendario/detalle/otro/1')->assertNotFound();
    }

    public function test_se_edita_prioridad_y_se_completa_y_reabre_una_tarea(): void
    {
        $tarea = Tarea::factory()->create(['prioridad' => 'baja', 'estado' => EstadoTarea::Pendiente, 'fecha_limite' => '2026-10-10']);
        $url = route('calendario.tarjetas.update', ['tipo' => 'tarea', 'id' => $tarea->id]);

        $this->patchJson($url, ['prioridad' => 'alta'])->assertOk()->assertJsonPath('detalle.prioridad', 'alta');
        $this->patchJson($url, ['completada' => true])
            ->assertOk()->assertJsonPath('detalle.completada', true)->assertJsonPath('detalle.bloqueada', true);
        $this->assertSame(EstadoTarea::Completada, $tarea->fresh()->estado);

        $this->patchJson($url, ['completada' => false])->assertOk()->assertJsonPath('detalle.completada', false);
        $this->assertSame(EstadoTarea::Pendiente, $tarea->fresh()->estado);
    }

    public function test_se_cambia_el_color_de_una_nota_y_se_valida(): void
    {
        $nota = Nota::factory()->create(['color' => 'durazno']);
        $url = route('calendario.tarjetas.update', ['tipo' => 'nota', 'id' => $nota->id]);

        $this->patchJson($url, ['color' => 'oliva'])->assertOk()->assertJsonPath('detalle.color', 'oliva');
        $this->assertSame('oliva', $nota->fresh()->color->value);

        $this->patchJson($url, ['color' => 'fucsia'])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->patchJson(route('calendario.tarjetas.update', ['tipo' => 'tarea', 'id' => Tarea::factory()->create()->id]), ['prioridad' => 'urgente'])
            ->assertUnprocessable()->assertJsonValidationErrors('prioridad');
    }

    public function test_el_recordatorio_se_edita_y_mueve_desde_el_panel(): void
    {
        $r = Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Antes', 'recordar_en' => '2026-10-05 09:00:00']);

        $this->patchJson(route('calendario.tarjetas.update', ['tipo' => 'recordatorio', 'id' => $r->id]), ['titulo' => 'Después', 'comentario' => 'Detalle'])
            ->assertOk()->assertJsonPath('detalle.titulo', 'Después')->assertJsonPath('detalle.comentario', 'Detalle');
        $this->patchJson(route('calendario.recordatorios.fecha', $r), ['recordar_en' => '2026-10-08T14:30'])->assertOk();

        $this->assertSame('2026-10-08 14:30:00', $r->fresh()->recordar_en->format('Y-m-d H:i:s'));
    }

    public function test_la_pagina_del_calendario_incluye_el_panel_de_detalle(): void
    {
        $this->get(route('calendario.index'))->assertOk()
            ->assertSee('id="detalle"', false)
            ->assertSee('role="dialog"', false)
            ->assertDontSee('popover-editor', false);
    }
}
