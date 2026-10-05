<?php

namespace Tests\Feature;

use App\Enums\ColorActividad;
use App\Enums\ZonaSemana;
use App\Models\Caja;
use App\Models\Ajuste;
use App\Models\Contexto;
use App\Models\User;
use App\Services\Agenda\PlannerLayout;
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
            // El color del contexto llega como clase de la línea.
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

    public function test_la_leyenda_lista_los_contextos_de_las_cajas_con_su_color(): void
    {
        $pediatria = Contexto::factory()->create(['nombre' => 'Pediatría', 'color' => ColorActividad::Ocre->value]);
        Contexto::factory()->create(['nombre' => 'Contexto sin cajas', 'color' => ColorActividad::Rosa->value]);
        Caja::factory()->delDia(now()->toDateString())->create(['contexto_id' => $pediatria->id]);

        $this->get(route('agenda.index'))
            ->assertSee('Pediatría')
            ->assertSee('plan-chip '.ColorActividad::Ocre->clase(), false)
            ->assertDontSee('Contexto sin cajas');
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
            ->assertSee('class="nav-link active" href="'.route('agenda.index').'"', false)
            ->assertDontSee('menu-planificar', false);
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

    private function urlLayout(string $semana = '2026-09-28'): string
    {
        return route('agenda.layout', ['semana' => $semana]);
    }

    public function test_sin_disposicion_guardada_el_planner_usa_la_de_fabrica(): void
    {
        $this->get(route('agenda.index', ['semana' => '2026-09-28']))
            ->assertOk()
            ->assertViewHas('layout', PlannerLayout::defecto())
            ->assertSee('gs-id="lunes" gs-x="0" gs-y="0" gs-w="4" gs-h="14"', false)
            ->assertSee('gs-id="pendiente" gs-x="6" gs-y="28" gs-w="6" gs-h="14"', false)
            ->assertSee($this->urlLayout(), false);
    }

    public function test_la_disposicion_de_una_semana_no_cambia_las_demas(): void
    {
        $this->patchJson($this->urlLayout('2026-09-28'), ['tarjetas' => [
            ['clave' => 'lunes', 'x' => 2, 'y' => 3, 'ancho' => 5, 'alto' => 9],
            ['clave' => 'notas', 'x' => 0, 'y' => 40, 'ancho' => 12, 'alto' => 6],
        ]])->assertOk()->assertJson(['ok' => true]);

        $this->get(route('agenda.index', ['semana' => '2026-09-28']))
            ->assertSee('gs-id="lunes" gs-x="2" gs-y="3" gs-w="5" gs-h="9"', false)
            ->assertSee('gs-id="notas" gs-x="0" gs-y="40" gs-w="12" gs-h="6"', false)
            // Las tarjetas que no se enviaron conservan su lugar de fábrica.
            ->assertSee('gs-id="martes" gs-x="4" gs-y="0" gs-w="4" gs-h="14"', false);

        $this->get(route('agenda.index', ['semana' => '2026-10-05']))
            ->assertViewHas('layout', PlannerLayout::defecto())
            ->assertSee('gs-id="lunes" gs-x="0" gs-y="0" gs-w="4" gs-h="14"', false);

        // Un segundo guardado parcial de la misma semana no pisa lo anterior.
        $this->patchJson($this->urlLayout('2026-09-28'), ['tarjetas' => [
            ['clave' => 'martes', 'x' => 0, 'y' => 0, 'ancho' => 3, 'alto' => 5],
        ]])->assertOk();

        $this->assertSame(['x' => 2, 'y' => 3, 'ancho' => 5, 'alto' => 9], PlannerLayout::obtener('2026-09-28')['lunes']);
        $this->assertSame(['x' => 0, 'y' => 0, 'ancho' => 3, 'alto' => 5], PlannerLayout::obtener('2026-09-28')['martes']);
        $this->assertSame(PlannerLayout::defecto(), PlannerLayout::obtener('2026-10-05'));
    }

    public function test_dos_fechas_de_la_misma_semana_comparten_la_disposicion(): void
    {
        $this->patchJson($this->urlLayout('2026-09-30'), ['tarjetas' => [
            ['clave' => 'lunes', 'x' => 1, 'y' => 1, 'ancho' => 6, 'alto' => 10],
        ]])->assertOk();

        $this->assertDatabaseHas('ajustes', ['clave' => 'planner.layout.2026-09-28']);

        foreach (['2026-09-28', '2026-10-04'] as $fecha) {
            $this->get(route('agenda.index', ['semana' => $fecha]))
                ->assertSee('gs-id="lunes" gs-x="1" gs-y="1" gs-w="6" gs-h="10"', false);
        }
    }

    public function test_el_layout_del_planner_rechaza_claves_y_valores_invalidos(): void
    {
        $url = $this->urlLayout();
        $valida = ['clave' => 'lunes', 'x' => 0, 'y' => 0, 'ancho' => 4, 'alto' => 8];

        $this->patchJson($url, ['tarjetas' => [['clave' => 'feriado'] + $valida]])->assertJsonValidationErrors('tarjetas.0.clave');
        $this->patchJson($url, ['tarjetas' => [$valida, $valida]])->assertJsonValidationErrors('tarjetas.0.clave');
        $this->patchJson($url, ['tarjetas' => [['x' => 10, 'ancho' => 6] + $valida]])->assertJsonValidationErrors('tarjetas.0.ancho');
        $this->patchJson($url, ['tarjetas' => [['ancho' => 20] + $valida]])->assertJsonValidationErrors('tarjetas.0.ancho');
        $this->patchJson($url, ['tarjetas' => [['alto' => 0] + $valida]])->assertJsonValidationErrors('tarjetas.0.alto');
        $this->patchJson($url, ['tarjetas' => [['y' => -1] + $valida]])->assertJsonValidationErrors('tarjetas.0.y');
        $this->patchJson($url, ['tarjetas' => [['x' => 'a'] + $valida]])->assertJsonValidationErrors('tarjetas.0.x');
        $this->patchJson($url, ['tarjetas' => []])->assertJsonValidationErrors('tarjetas');
        $this->patchJson($url, [])->assertJsonValidationErrors('tarjetas');

        $this->assertSame(PlannerLayout::defecto(), PlannerLayout::obtener('2026-09-28'));
    }

    public function test_una_semana_con_formato_invalido_no_guarda_ni_restablece(): void
    {
        $tarjetas = ['tarjetas' => [['clave' => 'lunes', 'x' => 0, 'y' => 0, 'ancho' => 4, 'alto' => 8]]];

        $this->patchJson('/agenda/semana/no-es-fecha/layout', $tarjetas)->assertNotFound();
        $this->patchJson('/agenda/semana/2026-02-31/layout', $tarjetas)->assertNotFound();
        $this->delete('/agenda/semana/2026-02-31/layout')->assertNotFound();
        $this->assertDatabaseCount('ajustes', 0);
    }

    public function test_cada_usuario_tiene_su_propia_disposicion(): void
    {
        $propio = auth()->user();

        $this->patchJson($this->urlLayout(), ['tarjetas' => [
            ['clave' => 'lunes', 'x' => 1, 'y' => 1, 'ancho' => 6, 'alto' => 10],
        ]])->assertOk();

        $this->actingAs(User::factory()->create());

        // El otro usuario ve la de fábrica y su guardado no toca la del primero.
        $this->assertSame(PlannerLayout::defecto(), PlannerLayout::obtener('2026-09-28'));
        $this->patchJson($this->urlLayout(), ['tarjetas' => [
            ['clave' => 'lunes', 'x' => 6, 'y' => 0, 'ancho' => 6, 'alto' => 4],
        ]])->assertOk();
        $this->assertSame(['x' => 6, 'y' => 0, 'ancho' => 6, 'alto' => 4], PlannerLayout::obtener('2026-09-28')['lunes']);

        $this->actingAs($propio);
        $this->assertSame(['x' => 1, 'y' => 1, 'ancho' => 6, 'alto' => 10], PlannerLayout::obtener('2026-09-28')['lunes']);
    }

    public function test_una_disposicion_guardada_corrupta_vuelve_a_la_de_fabrica(): void
    {
        $clave = PlannerLayout::clave('2026-09-28');

        Ajuste::guardar($clave, json_encode([
            'lunes' => ['x' => 11, 'y' => 0, 'ancho' => 6, 'alto' => 4], // se sale de la hoja
            'martes' => ['x' => 'a'],
            'miercoles' => ['x' => 0, 'y' => 2, 'ancho' => 4, 'alto' => 6],
        ]));

        $layout = PlannerLayout::obtener('2026-09-28');

        $this->assertSame(PlannerLayout::defecto()['lunes'], $layout['lunes']);
        $this->assertSame(PlannerLayout::defecto()['martes'], $layout['martes']);
        $this->assertSame(['x' => 0, 'y' => 2, 'ancho' => 4, 'alto' => 6], $layout['miercoles']);

        Ajuste::guardar($clave, 'no es json');
        $this->assertSame(PlannerLayout::defecto(), PlannerLayout::obtener('2026-09-28'));
    }

    public function test_la_antigua_disposicion_global_no_afecta_a_ninguna_semana(): void
    {
        Ajuste::guardar(PlannerLayout::AJUSTE, json_encode([
            'lunes' => ['x' => 2, 'y' => 3, 'ancho' => 5, 'alto' => 9],
        ]));

        foreach (['2026-09-28', '2026-10-05'] as $semana) {
            $this->get(route('agenda.index', ['semana' => $semana]))
                ->assertViewHas('layout', PlannerLayout::defecto());
        }

        // La fila vieja queda intacta: solo se deja de leer.
        $this->assertDatabaseHas('ajustes', ['clave' => 'planner.layout']);
    }

    public function test_restablecer_borra_solo_la_disposicion_de_esa_semana(): void
    {
        $tarjetas = ['tarjetas' => [['clave' => 'lunes', 'x' => 2, 'y' => 3, 'ancho' => 5, 'alto' => 9]]];
        $this->patchJson($this->urlLayout('2026-09-28'), $tarjetas)->assertOk();
        $this->patchJson($this->urlLayout('2026-10-05'), $tarjetas)->assertOk();

        $this->delete(route('agenda.layout.restablecer', ['semana' => '2026-09-30']))
            ->assertRedirect(route('agenda.index', ['semana' => '2026-09-30']));

        $this->assertSame(PlannerLayout::defecto(), PlannerLayout::obtener('2026-09-28'));
        $this->assertSame(['x' => 2, 'y' => 3, 'ancho' => 5, 'alto' => 9], PlannerLayout::obtener('2026-10-05')['lunes']);
    }
}
