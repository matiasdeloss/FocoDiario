<?php

namespace Tests\Feature;

use App\Enums\ColorActividad;
use App\Enums\ZonaSemana;
use App\Models\Caja;
use App\Models\Contexto;
use App\Services\Agenda\Semana;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_planner_muestra_los_siete_dias_y_las_zonas_de_notas(): void
    {
        $this->get(route('agenda.index', ['semana' => '2026-09-28']))
            ->assertOk()
            ->assertSee('Planner semanal')
            ->assertSeeInOrder(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo', 'Notas', 'Pendiente'])
            ->assertSee('28 sep – 4 oct 2026')
            ->assertSee('Septiembre – Octubre 2026');
    }

    public function test_cualquier_fecha_se_normaliza_al_lunes_de_su_semana(): void
    {
        foreach (['2026-09-28', '2026-09-30', '2026-10-04'] as $fecha) {
            $this->get(route('agenda.index', ['semana' => $fecha]))
                ->assertOk()
                ->assertViewHas('lunes', fn ($lunes) => $lunes->toDateString() === '2026-09-28');
        }

        // El domingo pertenece a la semana que termina, no a la siguiente.
        $this->assertSame('2026-09-28', Semana::lunesDe('2026-10-04')->toDateString());
        $this->assertSame('2026-10-05', Semana::lunesDe('2026-10-05')->toDateString());
    }

    public function test_una_fecha_invalida_muestra_la_semana_actual(): void
    {
        $this->travelTo('2026-09-30 10:00:00');

        foreach (['no-es-fecha', '2026-02-31', '2026-13-01'] as $invalida) {
            $this->get(route('agenda.index', ['semana' => $invalida]))
                ->assertOk()
                ->assertViewHas('lunes', fn ($lunes) => $lunes->toDateString() === '2026-09-28');
        }
    }

    public function test_los_enlaces_de_navegacion_apuntan_a_la_semana_anterior_y_siguiente(): void
    {
        $this->get(route('agenda.index', ['semana' => '2026-09-30']))
            ->assertViewHas('urlAnterior', route('agenda.index', ['semana' => '2026-09-21']))
            ->assertViewHas('urlSiguiente', route('agenda.index', ['semana' => '2026-10-05']));
    }

    public function test_cada_dia_muestra_sus_cajas_ordenadas_por_hora_y_luego_las_que_no_tienen(): void
    {
        $otorrino = Contexto::factory()->create(['nombre' => 'Otorrino', 'color' => ColorActividad::AzulPolvo->value]);

        Caja::factory()->delDia('2026-09-28')->conHora('18:00')->create(['titulo' => 'Otorrino', 'contexto_id' => $otorrino->id]);
        Caja::factory()->delDia('2026-09-28')->conHora('08:30')->create(['titulo' => 'Repaso temprano', 'y' => 5]);
        Caja::factory()->delDia('2026-09-28')->create(['titulo' => 'Caja sin hora', 'y' => 0]);
        Caja::factory()->delDia('2026-09-29')->conHora('17:00')->create(['titulo' => 'Neuro']);
        // Fuera de la semana: no debe aparecer.
        Caja::factory()->delDia('2026-10-05')->create(['titulo' => 'De la semana siguiente']);

        $this->get(route('agenda.index', ['semana' => '2026-09-28']))
            ->assertOk()
            ->assertSeeInOrder(['Lunes', '08:30', 'Repaso temprano', '18:00', 'Otorrino', 'Caja sin hora', 'Martes', '17:00', 'Neuro'])
            ->assertDontSee('De la semana siguiente')
            // El color de la actividad llega como clase de la línea.
            ->assertSee('plan-linea '.ColorActividad::AzulPolvo->clase(), false);
    }

    public function test_el_planner_muestra_hora_de_inicio_y_fin_y_el_avance_de_las_listas(): void
    {
        Caja::factory()->delDia('2026-09-30')->conHora('16:30', '18:30')->lista()->create(['titulo' => 'Traumato']);

        $this->get(route('agenda.index', ['semana' => '2026-09-28']))
            ->assertSee('16:30–18:30')
            ->assertSee('1/2');
    }

    public function test_las_cajas_hechas_se_ven_marcadas_en_el_planner(): void
    {
        Caja::factory()->delDia('2026-09-28')->hecha()->create(['titulo' => 'Ya estudiado']);

        $this->get(route('agenda.index', ['semana' => '2026-09-28']))
            ->assertSee('Ya estudiado')
            ->assertSee('es-hecha', false);
    }

    public function test_el_planner_muestra_las_cajas_de_notas_y_pendiente_de_la_semana(): void
    {
        Caja::factory()->deLaSemana('2026-09-28', ZonaSemana::Notas)->create(['contenido' => 'Hool 700-800 ml']);
        Caja::factory()->deLaSemana('2026-09-28', ZonaSemana::Pendiente)->lista([['texto' => 'Resumen de ejes', 'hecho' => false]])->create();
        Caja::factory()->deLaSemana('2026-10-05', ZonaSemana::Notas)->create(['contenido' => 'Nota de otra semana']);

        $this->get(route('agenda.index', ['semana' => '2026-09-30']))
            ->assertSee('Hool 700-800 ml')
            ->assertSee('Resumen de ejes')
            ->assertDontSee('Nota de otra semana');
    }

    public function test_la_leyenda_lista_las_actividades_con_su_color(): void
    {
        Contexto::factory()->create(['nombre' => 'Pediatría', 'color' => ColorActividad::Ocre->value]);
        Contexto::factory()->create(['nombre' => 'Contexto sin color', 'color' => null]);

        $this->get(route('agenda.index'))
            ->assertSee('Pediatría')
            ->assertSee('plan-chip '.ColorActividad::Ocre->clase(), false)
            ->assertDontSee('Contexto sin color');
    }

    public function test_las_cajas_de_la_semana_se_guardan_y_se_actualizan_por_zona(): void
    {
        $url = route('agenda.semana.guardar', ['semana' => '2026-09-30', 'zona' => 'notas']);

        $this->putJson($url, ['tipo' => 'texto', 'contenido' => 'Primera nota'])->assertOk()->assertJsonPath('caja.zona', 'notas');
        $this->putJson($url, ['tipo' => 'texto', 'contenido' => 'Nota corregida'])->assertOk();

        // La semana se normaliza al lunes y hay una sola caja por zona.
        $this->assertDatabaseCount('cajas', 1);
        $caja = Caja::first();
        $this->assertSame('2026-09-28', $caja->semana->toDateString());
        $this->assertSame('Nota corregida', $caja->contenido);
        $this->assertSame(ZonaSemana::Notas, $caja->zona);

        $this->putJson(route('agenda.semana.guardar', ['semana' => '2026-09-28', 'zona' => 'pendiente']), [
            'tipo' => 'lista', 'items' => [['texto' => 'Resumen', 'hecho' => true]],
        ])->assertOk();

        $this->assertDatabaseCount('cajas', 2);
        $this->assertSame([['texto' => 'Resumen', 'hecho' => true]], Caja::where('zona', 'pendiente')->first()->items);
    }

    public function test_una_zona_de_semana_desconocida_da_404(): void
    {
        $this->putJson('/agenda/semana/2026-09-28/otra', ['tipo' => 'texto'])->assertNotFound();
    }

    public function test_el_menu_tiene_el_item_agenda_activo_en_la_agenda(): void
    {
        $this->get(route('agenda.index'))
            ->assertSee('class="nav-link dropdown-toggle active" id="menu-planificar"', false)
            ->assertSee('class="dropdown-item active" href="'.route('agenda.index').'"', false);
        $this->get(route('hoy'))->assertSee('ver agenda');
    }

    public function test_el_titulo_del_planner_por_defecto_es_planner_semanal(): void
    {
        $this->get(route('agenda.index'))
            ->assertOk()
            ->assertViewHas('tituloPlanner', 'Planner semanal')
            ->assertSee('value="Planner semanal"', false);
    }

    public function test_el_titulo_del_planner_se_guarda_y_se_muestra(): void
    {
        $this->putJson(route('agenda.titulo'), ['titulo' => '  Mi semana de estudio '])
            ->assertOk()
            ->assertJson(['titulo' => 'Mi semana de estudio']);

        $this->assertDatabaseHas('ajustes', ['clave' => 'planner.titulo', 'valor' => 'Mi semana de estudio']);
        $this->get(route('agenda.index'))->assertSee('value="Mi semana de estudio"', false);
    }

    public function test_un_titulo_vacio_vuelve_al_valor_por_defecto(): void
    {
        $this->putJson(route('agenda.titulo'), ['titulo' => 'Otro'])->assertOk();

        $this->putJson(route('agenda.titulo'), ['titulo' => '   '])
            ->assertOk()
            ->assertJson(['titulo' => 'Planner semanal']);
    }

    public function test_el_titulo_del_planner_tiene_un_maximo_de_60_caracteres(): void
    {
        $this->putJson(route('agenda.titulo'), ['titulo' => str_repeat('a', 61)])->assertJsonValidationErrors('titulo');
        $this->putJson(route('agenda.titulo'), ['titulo' => str_repeat('a', 60)])->assertOk();
        $this->putJson(route('agenda.titulo'), [])->assertJsonValidationErrors('titulo');
    }

    public function test_cada_dia_con_cajas_tiene_su_enlace_ver_mas_a_la_hoja_del_dia(): void
    {
        Caja::factory()->create(['fecha' => '2026-09-29']);

        $this->get(route('agenda.index', ['semana' => '2026-09-28']))
            ->assertOk()
            ->assertSee('data-plan-mas', false)
            ->assertSee(route('agenda.dia', ['fecha' => '2026-09-29']), false);
    }
}
