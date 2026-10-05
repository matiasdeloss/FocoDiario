<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Contexto;
use App\Models\Recordatorio;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pantalla unificada de Tareas y Recordatorios: lista agrupada por tiempo, filtros y modales de recordatorio. */
class TareasUnificadasTest extends TestCase
{
    use RefreshDatabase;

    private function fila(string $titulo): string
    {
        return 'aria-label="'.$titulo.'"';
    }

    public function test_muestra_tareas_y_recordatorios_juntos_agrupados_por_tiempo(): void
    {
        Tarea::factory()->create(['titulo' => 'Atrasada', 'fecha_limite' => today()->subDays(2)]);
        Tarea::factory()->create(['titulo' => 'De hoy', 'fecha_limite' => today()]);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Llamar mañana', 'recordar_en' => today()->addDay()->setTime(10, 0)]);
        Tarea::factory()->create(['titulo' => 'Muy lejos', 'fecha_limite' => today()->addDays(40)]);
        Tarea::factory()->create(['titulo' => 'Sin fecha alguna', 'fecha_limite' => null]);
        Recordatorio::factory()->sinFecha()->create(['tarea_id' => null, 'mensaje' => 'Recordar sin fecha']);

        $respuesta = $this->get(route('tareas.index'))->assertOk();
        $respuesta->assertSeeInOrder(['Vencidas', 'Atrasada', 'Hoy', 'De hoy', 'Mañana', 'Llamar mañana', 'Más adelante', 'Muy lejos', 'Sin fecha', 'Sin fecha alguna', 'Recordar sin fecha']);

        $grupos = $respuesta->viewData('grupos');
        $this->assertSame(['Atrasada'], $grupos['vencidas']->pluck('titulo')->all());
        $this->assertSame(['De hoy'], $grupos['hoy']->pluck('titulo')->all());
        $this->assertSame(['Llamar mañana'], $grupos['manana']->pluck('titulo')->all());
        $this->assertSame(['Muy lejos'], $grupos['despues']->pluck('titulo')->all());
        $this->assertEqualsCanonicalizing(['Sin fecha alguna', 'Recordar sin fecha'], $grupos['sin_fecha']->pluck('titulo')->all());
    }

    public function test_el_resumen_cuenta_abiertas_y_vencidas_de_ambos_tipos(): void
    {
        Tarea::factory()->create(['fecha_limite' => today()->subDay()]);
        Tarea::factory()->create(['estado' => EstadoTarea::Completada, 'fecha_limite' => today()->subDay()]);
        Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => today()->subDays(3)]);
        Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => now()->addDay(), 'avisado_en' => now()]);

        $resumen = $this->get(route('tareas.index'))->viewData('resumen');

        $this->assertSame(['tareas' => 1, 'recordatorios' => 1, 'vencidas' => 2], $resumen);
    }

    public function test_los_filtros_de_tipo_estado_prioridad_y_contexto(): void
    {
        $tesis = Contexto::factory()->proyecto()->create(['nombre' => 'Tesis']);
        $casa = Contexto::factory()->entorno()->create(['nombre' => 'Casa']);
        Tarea::factory()->create(['titulo' => 'Alta tesis', 'prioridad' => 'alta', 'contexto_id' => $tesis->id]);
        Tarea::factory()->create(['titulo' => 'Baja casa', 'prioridad' => 'baja', 'contexto_id' => $casa->id]);
        Tarea::factory()->create(['titulo' => 'Vieja hecha', 'estado' => EstadoTarea::Completada]);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Un recordatorio']);

        $this->get(route('tareas.index', ['tipo' => 'tarea']))->assertOk()
            ->assertSee($this->fila('Alta tesis'), false)->assertDontSee($this->fila('Un recordatorio'), false);
        $this->get(route('tareas.index', ['tipo' => 'recordatorio']))->assertOk()
            ->assertSee($this->fila('Un recordatorio'), false)->assertDontSee($this->fila('Alta tesis'), false);
        $this->get(route('tareas.index', ['estado' => 'hechas']))->assertOk()
            ->assertSee($this->fila('Vieja hecha'), false)->assertDontSee($this->fila('Alta tesis'), false);

        // Prioridad y contexto son de las tareas: los recordatorios no entran.
        $this->get(route('tareas.index', ['prioridad' => 'alta']))->assertOk()
            ->assertSee($this->fila('Alta tesis'), false)->assertDontSee($this->fila('Baja casa'), false)->assertDontSee($this->fila('Un recordatorio'), false);
        $this->get(route('tareas.index', ['contexto' => $casa->id]))->assertOk()
            ->assertSee($this->fila('Baja casa'), false)->assertDontSee($this->fila('Alta tesis'), false);

        $this->get(route('tareas.index', ['tipo' => 'otro']))->assertSessionHasErrors('tipo');
        $this->get(route('tareas.index', ['estado' => 'nada']))->assertSessionHasErrors('estado');
        $this->get(route('tareas.index', ['prioridad' => 'urgente']))->assertSessionHasErrors('prioridad');
    }

    public function test_la_busqueda_recorre_titulos_comentarios_y_recordatorios(): void
    {
        Tarea::factory()->create(['titulo' => 'Comprar pan', 'descripcion' => null]);
        Tarea::factory()->create(['titulo' => 'Otra cosa', 'descripcion' => 'pasar por la panadería']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Hornear pan']);
        Tarea::factory()->create(['titulo' => 'Nada que ver']);

        $this->get(route('tareas.index', ['q' => 'pan']))->assertOk()
            ->assertSee($this->fila('Comprar pan'), false)->assertSee($this->fila('Otra cosa'), false)->assertSee($this->fila('Hornear pan'), false)
            ->assertDontSee($this->fila('Nada que ver'), false);
    }

    public function test_las_completadas_van_en_su_grupo_y_las_dos_clases_se_mezclan_por_reciente(): void
    {
        Tarea::factory()->create(['titulo' => 'Tarea hecha', 'estado' => EstadoTarea::Completada, 'updated_at' => now()->subDays(2)]);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Aviso hecho', 'avisado_en' => now()->subDay()]);

        $respuesta = $this->get(route('tareas.index'))->assertOk();

        $this->assertSame(['Aviso hecho', 'Tarea hecha'], $respuesta->viewData('completadas')->pluck('titulo')->all());
        $this->assertSame(2, $respuesta->viewData('hechasTotal'));
        $respuesta->assertSee('aria-checked="true"', false);
    }

    public function test_la_fila_lleva_las_urls_del_check_y_los_datos_de_los_modales(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Con datos']);
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Aviso con datos', 'recordar_en' => '2026-12-24 18:30:00']);

        $html = $this->get(route('tareas.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-url-hecho="'.route('tareas.estado', $tarea).'"', $html);
        $this->assertStringContainsString('data-url-hecho="'.route('recordatorios.avisar', $recordatorio).'"', $html);
        $this->assertStringContainsString('data-url-deshacer="'.route('recordatorios.reactivar', $recordatorio).'"', $html);
        $this->assertStringContainsString('data-abrir-recordatorio="editar"', $html);
        $this->assertStringContainsString('href="'.route('recordatorios.edit', $recordatorio).'"', $html);
        $this->assertSame('2026-12-24T18:30', $recordatorio->datosModal()['recordar_en']);
        $this->assertSame(route('recordatorios.update', $recordatorio), $recordatorio->datosModal()['url']);
    }

    public function test_el_modal_de_recordatorio_crea_y_edita_con_json_y_valida_por_campo(): void
    {
        $cabeceras = ['X-Modal' => '1', 'Accept' => 'application/json'];

        $this->postJson(route('recordatorios.store'), ['mensaje' => 'Desde el modal', 'recordar_en' => '2026-11-05T09:00'], $cabeceras)
            ->assertCreated()->assertJsonPath('mensaje', 'Recordatorio creado.');
        $this->assertSame('Recordatorio creado.', session('estado'));

        $recordatorio = Recordatorio::where('mensaje', 'Desde el modal')->firstOrFail();

        $this->patchJson(route('recordatorios.update', $recordatorio), ['mensaje' => 'Editado', 'recordar_en' => ''], $cabeceras)->assertOk();
        $this->assertSame('Editado', $recordatorio->fresh()->mensaje);
        $this->assertNull($recordatorio->fresh()->recordar_en);

        $this->postJson(route('recordatorios.store'), ['mensaje' => ''], $cabeceras)
            ->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->assertDatabaseCount('recordatorios', 1);
    }

    public function test_desde_la_lista_se_puede_marcar_y_desmarcar_un_recordatorio_con_json(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null]);

        $this->patchJson(route('recordatorios.avisar', $recordatorio))->assertOk()->assertJson(['avisado' => true]);
        $this->assertNotNull($recordatorio->fresh()->avisado_en);
        $this->patchJson(route('recordatorios.reactivar', $recordatorio))->assertOk()->assertJson(['avisado' => false]);
        $this->assertNull($recordatorio->fresh()->avisado_en);
    }

    public function test_el_enlace_a_recordatorios_ya_no_esta_en_el_menu_y_tareas_lleva_al_tablero(): void
    {
        $html = $this->get(route('tareas.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('>Recordatorios</a>', str_replace('t-pill', '', substr($html, 0, strpos($html, '<main'))));
        $this->assertStringContainsString('href="'.route('tablero.index').'" class="btn btn-foco-suave"', $html);
    }

    public function test_las_paginas_de_recordatorio_sin_js_vuelven_a_la_lista_unificada(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null]);

        $this->get(route('recordatorios.create'))->assertOk()->assertSee('href="'.route('tareas.index').'"', false);
        $this->get(route('recordatorios.edit', $recordatorio))->assertOk();
    }
}
