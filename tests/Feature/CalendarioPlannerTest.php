<?php

namespace Tests\Feature;

use App\Enums\ColorActividad;
use App\Models\Caja;
use App\Models\Contexto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El filtro "Planner semanal" del calendario: eventos de las cajas de día y su movimiento. */
class CalendarioPlannerTest extends TestCase
{
    use RefreshDatabase;

    private const RANGO = ['start' => '2026-10-01T00:00:00', 'end' => '2026-11-01T00:00:00'];

    private function eventos(array $consulta = []): array
    {
        return $this->getJson(route('calendario.eventos', [...self::RANGO, ...$consulta]))->assertOk()->json();
    }

    public function test_los_eventos_del_planner_solo_aparecen_si_se_piden(): void
    {
        Caja::factory()->delDia('2026-10-06')->create(['titulo' => 'Caja del martes']);

        $this->assertSame([], $this->eventos(['tipos' => 'tarea,nota']));
        $this->assertSame(['Caja del martes'], array_column($this->eventos(['tipos' => 'planner']), 'title'));
        // Sin el parámetro "tipos" se incluyen todas las capas, el planner también.
        $this->assertSame(['Caja del martes'], array_column($this->eventos(), 'title'));
    }

    public function test_cajas_con_hora_y_de_todo_el_dia(): void
    {
        Caja::factory()->delDia('2026-10-06')->conHora('09:00', '10:30')->create(['titulo' => 'Con fin']);
        Caja::factory()->delDia('2026-10-06')->conHora('23:30')->create(['titulo' => 'Sin fin']);
        Caja::factory()->delDia('2026-10-07')->create(['titulo' => '']);

        $porTitulo = collect($this->eventos(['tipos' => 'planner']))->keyBy('title');

        $this->assertFalse($porTitulo['Con fin']['allDay']);
        $this->assertSame('2026-10-06T09:00:00', $porTitulo['Con fin']['start']);
        $this->assertSame('2026-10-06T10:30:00', $porTitulo['Con fin']['end']);
        $this->assertSame('2026-10-07T00:30:00', $porTitulo['Sin fin']['end']);
        $this->assertTrue($porTitulo['Sin título']['allDay']);
        $this->assertSame('2026-10-07', $porTitulo['Sin título']['start']);
        $this->assertArrayNotHasKey('end', $porTitulo['Sin título']);
        $this->assertSame(route('agenda.dia', ['fecha' => '2026-10-07']), $porTitulo['Sin título']['extendedProps']['urlDia']);
    }

    public function test_el_evento_toma_el_color_del_contexto(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Gimnasio', 'color' => ColorActividad::Salvia->value]);
        Caja::factory()->delDia('2026-10-06')->create(['titulo' => 'Pesas', 'contexto_id' => $contexto->id]);

        $evento = $this->eventos(['tipos' => 'planner'])[0];

        $this->assertContains('ev-tipo-planner', $evento['classNames']);
        $this->assertContains('actividad-salvia', $evento['classNames']);
        $this->assertSame('Gimnasio', $evento['extendedProps']['contexto']);
    }

    public function test_excluye_cajas_de_la_semana_fuera_de_rango_y_de_otros_usuarios(): void
    {
        Caja::factory()->delDia('2026-10-06')->create(['titulo' => 'Mía']);
        Caja::factory()->deLaSemana('2026-10-05')->create(['titulo' => 'Notas de la semana']);
        Caja::factory()->delDia('2026-11-01')->create(['titulo' => 'Fuera de rango']);
        Caja::factory()->delDia('2026-10-06')->create(['titulo' => 'Ajena', 'user_id' => User::factory()->create()->id]);

        $this->assertSame(['Mía'], array_column($this->eventos(['tipos' => 'planner']), 'title'));
    }

    public function test_mover_a_otro_dia_con_hora_actualiza_la_caja_y_la_deja_debajo_de_las_del_destino(): void
    {
        $caja = Caja::factory()->delDia('2026-10-06')->create(['titulo' => 'A mover', 'x' => 2, 'y' => 0, 'ancho' => 5, 'alto' => 7]);
        Caja::factory()->delDia('2026-10-08')->create(['y' => 0, 'alto' => 8]);
        Caja::factory()->delDia('2026-10-08')->create(['y' => 8, 'alto' => 4]);

        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-08', 'hora_inicio' => '14:00', 'hora_fin' => '15:30'])
            ->assertOk()
            ->assertJsonPath('evento.start', '2026-10-08T14:00:00')
            ->assertJsonPath('evento.end', '2026-10-08T15:30:00');

        $caja->refresh();
        $this->assertSame('2026-10-08', $caja->fecha->toDateString());
        $this->assertSame('14:00', $caja->hora_inicio);
        $this->assertSame('15:30', $caja->hora_fin);
        $this->assertSame([2, 12, 5, 7], [$caja->x, $caja->y, $caja->ancho, $caja->alto]);
    }

    public function test_mover_dentro_del_mismo_dia_no_cambia_la_posicion_en_la_hoja(): void
    {
        $caja = Caja::factory()->delDia('2026-10-06')->conHora('09:00', '10:00')->create(['y' => 6, 'alto' => 4]);

        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-06', 'hora_inicio' => '11:00', 'hora_fin' => '12:00'])->assertOk();

        $this->assertSame(6, $caja->fresh()->y);
        $this->assertSame('11:00', $caja->fresh()->hora_inicio);
    }

    public function test_pasar_a_todo_el_dia_borra_las_horas_y_pasar_a_con_hora_las_pone(): void
    {
        $caja = Caja::factory()->delDia('2026-10-06')->conHora('09:00', '10:00')->create();

        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-06', 'hora_inicio' => null, 'hora_fin' => '10:00'])
            ->assertOk()
            ->assertJsonPath('evento.allDay', true);
        $this->assertNull($caja->fresh()->hora_inicio);
        $this->assertNull($caja->fresh()->hora_fin);

        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-06', 'hora_inicio' => '16:00', 'hora_fin' => '17:00'])
            ->assertOk()
            ->assertJsonPath('evento.allDay', false);
        $this->assertSame('16:00', $caja->fresh()->hora_inicio);
        $this->assertSame('17:00', $caja->fresh()->hora_fin);
    }

    public function test_validacion_del_movimiento(): void
    {
        $caja = Caja::factory()->delDia('2026-10-06')->create();

        $this->patchJson(route('calendario.planner.mover', $caja), [])->assertJsonValidationErrors(['fecha', 'hora_inicio']);
        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '06/10/2026', 'hora_inicio' => null])->assertJsonValidationErrors('fecha');
        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-06', 'hora_inicio' => '10:00', 'hora_fin' => '09:00'])->assertJsonValidationErrors('hora_fin');
        $this->assertSame('2026-10-06', $caja->fresh()->fecha->toDateString());
    }

    public function test_no_se_mueven_cajas_de_la_semana_ni_ajenas(): void
    {
        $semana = Caja::factory()->deLaSemana('2026-10-05')->create();
        $ajena = Caja::factory()->delDia('2026-10-06')->create(['user_id' => User::factory()->create()->id]);
        $cuerpo = ['fecha' => '2026-10-07', 'hora_inicio' => null];

        $this->patchJson(route('calendario.planner.mover', $semana), $cuerpo)->assertUnprocessable();
        $this->assertNull($semana->fresh()->fecha);

        $this->patchJson(route('calendario.planner.mover', $ajena), $cuerpo)->assertNotFound();
        $this->assertSame('2026-10-06', Caja::withoutGlobalScopes()->find($ajena->id)->fecha->toDateString());
    }

    public function test_la_pagina_ofrece_el_filtro_del_planner_y_la_url_de_movimiento(): void
    {
        $this->get(route('calendario.index'))
            ->assertOk()
            ->assertSee('Planner semanal')
            ->assertSee('value="planner"', false)
            ->assertSee('data-url-planner=', false);
    }

    public function test_el_evento_expone_si_la_caja_tiene_hora_de_fin(): void
    {
        Caja::factory()->delDia('2026-10-06')->conHora('09:00', '10:30')->create(['titulo' => 'Con fin']);
        Caja::factory()->delDia('2026-10-06')->conHora('23:30')->create(['titulo' => 'Sin fin']);
        Caja::factory()->delDia('2026-10-07')->create(['titulo' => 'Todo el día']);

        $porTitulo = collect($this->eventos(['tipos' => 'planner']))->keyBy('title');

        $this->assertTrue($porTitulo['Con fin']['extendedProps']['tieneFin']);
        $this->assertFalse($porTitulo['Sin fin']['extendedProps']['tieneFin']);
        $this->assertFalse($porTitulo['Todo el día']['extendedProps']['tieneFin']);
    }

    public function test_mover_una_caja_sin_fin_no_le_inventa_hora_de_fin_y_estirarla_si_se_la_pone(): void
    {
        $caja = Caja::factory()->delDia('2026-10-06')->conHora('23:30')->create();

        // Mover: el calendario envía solo el inicio.
        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-07', 'hora_inicio' => '23:30', 'hora_fin' => null])->assertOk();
        $this->assertNull($caja->fresh()->hora_fin);

        // Estirar: ahí sí se guarda el fin.
        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-07', 'hora_inicio' => '23:30', 'hora_fin' => '23:59'])->assertOk();
        $this->assertSame('23:59', $caja->fresh()->hora_fin);
    }

    public function test_las_cajas_hechas_llevan_la_clase_ev_hecho(): void
    {
        Caja::factory()->delDia('2026-10-06')->create(['titulo' => 'Hecha', 'hecha' => true]);
        Caja::factory()->delDia('2026-10-06')->create(['titulo' => 'Pendiente', 'hecha' => false]);

        $porTitulo = collect($this->eventos(['tipos' => 'planner']))->keyBy('title');

        $this->assertContains('ev-hecho', $porTitulo['Hecha']['classNames']);
        $this->assertNotContains('ev-hecho', $porTitulo['Pendiente']['classNames']);
    }

    public function test_mover_a_un_dia_con_la_hoja_llena_no_pasa_de_la_ultima_fila(): void
    {
        $caja = Caja::factory()->delDia('2026-10-06')->create();
        Caja::factory()->delDia('2026-10-08')->create(['y' => Caja::MAX_FILA, 'alto' => 10]);

        $this->patchJson(route('calendario.planner.mover', $caja), ['fecha' => '2026-10-08', 'hora_inicio' => null])->assertOk();

        $this->assertSame(Caja::MAX_FILA, $caja->fresh()->y);
    }
}
