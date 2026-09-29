<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    private const RANGO = ['start' => '2026-10-01T00:00:00', 'end' => '2026-11-01T00:00:00'];

    public function test_la_pagina_del_calendario_responde_y_lista_tareas_sin_fecha(): void
    {
        Tarea::factory()->create(['titulo' => 'Sin fecha abierta', 'fecha_limite' => null]);
        Tarea::factory()->create(['titulo' => 'Sin fecha hecha', 'fecha_limite' => null, 'estado' => EstadoTarea::Completada]);
        Tarea::factory()->create(['titulo' => 'Con fecha puesta', 'fecha_limite' => '2026-10-05']);

        $this->get(route('calendario.index'))
            ->assertOk()
            ->assertSee('Por ubicar')
            ->assertSee('Sin fecha abierta')
            ->assertDontSee('Sin fecha hecha')
            ->assertDontSee('Con fecha puesta');
    }

    public function test_la_pagina_respeta_el_parametro_fecha(): void
    {
        $this->get(route('calendario.index', ['fecha' => '2026-12-25']))
            ->assertOk()
            ->assertSee('data-fecha="2026-12-25"', false);

        $this->get(route('calendario.index', ['fecha' => 'no-es-fecha']))->assertSessionHasErrors('fecha');
    }

    public function test_los_eventos_incluyen_cada_tipo_dentro_del_rango_y_excluyen_lo_de_fuera(): void
    {
        Tarea::factory()->create(['titulo' => 'Tarea dentro', 'fecha_limite' => '2026-10-10', 'prioridad' => PrioridadTarea::Alta]);
        Tarea::factory()->create(['titulo' => 'Tarea fuera', 'fecha_limite' => '2026-11-01']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Aviso dentro', 'recordar_en' => '2026-10-12 08:30:00']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Aviso fuera', 'recordar_en' => '2026-09-30 23:59:00']);
        Nota::factory()->create(['contenido' => 'Nota dentro', 'fecha' => '2026-10-15']);
        Nota::factory()->create(['contenido' => 'Nota fuera', 'fecha' => '2026-12-01']);
        Nota::factory()->create(['contenido' => 'Nota sin fecha', 'fecha' => null]);
        SesionEstudio::factory()->create(['tema' => 'Sesión dentro', 'iniciada_en' => '2026-10-20 10:00:00', 'finalizada_en' => '2026-10-20 11:00:00']);
        SesionEstudio::factory()->create(['tema' => 'Sesión fuera', 'iniciada_en' => '2026-08-20 10:00:00', 'finalizada_en' => '2026-08-20 11:00:00']);

        $respuesta = $this->getJson(route('calendario.eventos', self::RANGO))->assertOk();
        $titulos = collect($respuesta->json())->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['Tarea dentro', 'Aviso dentro', 'Nota dentro', 'Sesión dentro'], $titulos);

        $porTipo = collect($respuesta->json())->keyBy('extendedProps.tipo');
        $this->assertTrue($porTipo['tarea']['allDay']);
        $this->assertSame('2026-10-10', $porTipo['tarea']['start']);
        $this->assertContains('ev-prio-alta', $porTipo['tarea']['classNames']);
        $this->assertFalse($porTipo['recordatorio']['allDay']);
        $this->assertSame('2026-10-12T08:30:00', $porTipo['recordatorio']['start']);
        $this->assertTrue($porTipo['nota']['allDay']);
        $this->assertSame('2026-10-20T11:00:00', $porTipo['sesion']['end']);
        $this->assertStringContainsString('/tareas/', $porTipo['tarea']['extendedProps']['tarjeta']['editar']);
    }

    public function test_las_tareas_completadas_y_vencidas_se_marcan(): void
    {
        Tarea::factory()->create(['fecha_limite' => '2020-01-10', 'estado' => EstadoTarea::Pendiente]);
        Tarea::factory()->create(['fecha_limite' => '2020-01-11', 'estado' => EstadoTarea::Completada]);

        $eventos = collect($this->getJson(route('calendario.eventos', ['start' => '2020-01-01', 'end' => '2020-02-01']))->json());

        $this->assertContains('ev-vencida', $eventos->firstWhere('start', '2020-01-10')['classNames']);
        $completada = $eventos->firstWhere('start', '2020-01-11');
        $this->assertContains('ev-hecho', $completada['classNames']);
        $this->assertNotContains('ev-vencida', $completada['classNames']);
        $this->assertFalse($completada['editable']);
    }

    public function test_los_filtros_por_tipo_limitan_las_capas(): void
    {
        Tarea::factory()->create(['fecha_limite' => '2026-10-10']);
        Nota::factory()->create(['fecha' => '2026-10-10']);

        $tipos = collect($this->getJson(route('calendario.eventos', self::RANGO + ['tipos' => 'nota']))->json())
            ->pluck('extendedProps.tipo')->all();

        $this->assertSame(['nota'], $tipos);
    }

    public function test_el_rango_es_obligatorio_y_valido(): void
    {
        $this->getJson(route('calendario.eventos'))->assertUnprocessable()->assertJsonValidationErrors(['start', 'end']);
        $this->getJson(route('calendario.eventos', ['start' => '2026-10-10', 'end' => '2026-10-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('end');
    }

    public function test_los_eventos_no_hacen_consultas_por_cada_registro(): void
    {
        Tarea::factory()->count(15)->create(['fecha_limite' => '2026-10-10']);
        Recordatorio::factory()->count(15)->create(['recordar_en' => '2026-10-11 09:00:00']);
        Nota::factory()->count(15)->create(['fecha' => '2026-10-12']);

        \DB::enableQueryLog();
        $this->getJson(route('calendario.eventos', self::RANGO))->assertOk();

        $this->assertLessThanOrEqual(4, count(\DB::getQueryLog()));
    }

    public function test_se_asigna_y_se_quita_la_fecha_de_una_tarea(): void
    {
        $tarea = Tarea::factory()->create(['fecha_limite' => null]);

        $this->patchJson(route('calendario.tareas.fecha', $tarea), ['fecha' => '2026-10-20'])
            ->assertOk()
            ->assertJsonPath('evento.start', '2026-10-20')
            ->assertJsonPath('panel', null);
        $this->assertSame('2026-10-20', $tarea->fresh()->fecha_limite->toDateString());

        $this->patchJson(route('calendario.tareas.fecha', $tarea), ['fecha' => '2026-10-25'])->assertOk();
        $this->assertSame('2026-10-25', $tarea->fresh()->fecha_limite->toDateString());

        $this->patchJson(route('calendario.tareas.fecha', $tarea), ['fecha' => null])
            ->assertOk()
            ->assertJsonPath('evento', null)
            ->assertJsonPath('panel', fn ($html) => str_contains($html, 'data-tipo="tarea" data-id="'.$tarea->id.'"'));
        $this->assertNull($tarea->fresh()->fecha_limite);
    }

    public function test_la_fecha_de_la_tarea_se_valida_en_espanol(): void
    {
        $tarea = Tarea::factory()->create(['fecha_limite' => null]);

        $this->patchJson(route('calendario.tareas.fecha', $tarea), ['fecha' => '20/10/2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha' => 'La fecha debe tener el formato AAAA-MM-DD.']);

        $this->patchJson(route('calendario.tareas.fecha', $tarea), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha');

        $this->assertNull($tarea->fresh()->fecha_limite);
    }

    public function test_una_tarea_completada_no_se_puede_reasignar(): void
    {
        $tarea = Tarea::factory()->create(['fecha_limite' => '2026-10-05', 'estado' => EstadoTarea::Completada]);

        $this->patchJson(route('calendario.tareas.fecha', $tarea), ['fecha' => '2026-10-20'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Las tareas completadas no se pueden reasignar.');

        $this->assertSame('2026-10-05', $tarea->fresh()->fecha_limite->toDateString());
    }

    public function test_se_mueve_un_recordatorio_de_dia_y_se_valida(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-10-12 08:30:00']);

        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), ['recordar_en' => '2026-10-14T08:30:00'])
            ->assertOk()->assertJsonPath('recordar_en', '2026-10-14T08:30:00');
        $this->assertSame('2026-10-14 08:30:00', $recordatorio->fresh()->recordar_en->format('Y-m-d H:i:s'));

        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), ['recordar_en' => 'mañana'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recordar_en' => 'La fecha y hora del recordatorio no son válidas.']);
    }

    public function test_hoy_muestra_la_tira_semanal_con_conteos(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30)->setTime(10, 0)); // miércoles

        Tarea::factory()->count(2)->create(['fecha_limite' => '2026-09-29']);
        Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-09-30 18:00:00']);
        Nota::factory()->create(['fecha' => '2026-10-04']);
        Tarea::factory()->create(['fecha_limite' => '2026-10-05']); // lunes siguiente: fuera de la semana

        $respuesta = $this->get(route('hoy'))->assertOk();

        $respuesta->assertSee('Esta semana')
            ->assertSee('data-hoy', false)
            ->assertSee('martes 29: 2 tareas', false)
            ->assertSee('miércoles 30, hoy: 1 recordatorio', false)
            ->assertSee('domingo 4: 1 nota', false)
            ->assertSee('lunes 28: sin eventos', false);

        // Los datos de la semana viajan embebidos: de lunes 28 a domingo 4, sin el lunes siguiente.
        $semana = $respuesta->viewData('semana');
        $this->assertSame(['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'], array_column($semana, 'fecha'));
        $this->assertStringNotContainsString('2026-10-05', $respuesta->getContent());
    }

    public function test_el_menu_tiene_el_item_calendario_activo(): void
    {
        $this->get(route('calendario.index'))
            ->assertOk()
            ->assertSee('nav-link active" href="'.route('calendario.index').'"', false);
    }

    public function test_los_formularios_de_creacion_precargan_la_fecha(): void
    {
        $this->get(route('tareas.create', ['fecha' => '2026-10-20']))->assertOk()->assertSee('value="2026-10-20"', false);
        $this->get(route('recordatorios.create', ['fecha' => '2026-10-20']))->assertOk()->assertSee('value="2026-10-20T09:00"', false);
        $this->get(route('notas.create', ['fecha' => '2026-10-20']))->assertOk()->assertSee('value="2026-10-20"', false);
        $this->get(route('tareas.create', ['fecha' => 'basura']))->assertOk();
    }
}
