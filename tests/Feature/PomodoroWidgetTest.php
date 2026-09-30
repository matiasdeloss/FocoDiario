<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PomodoroWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_layout_incluye_el_widget_con_marcado_accesible_en_todas_las_secciones(): void
    {
        foreach (['tareas.index', 'tablero.index', 'registro.index', 'notas.index', 'recomendaciones.index', 'estudio.historial', 'estudio.metodos'] as $ruta) {
            $this->get(route($ruta))
                ->assertOk()
                ->assertSee('id="pomodoro-widget"', false)
                ->assertSee('aria-label="Temporizador de estudio"', false)
                ->assertSee('aria-label="Pausar temporizador"', false)
                ->assertSee('aria-label="Saltar a la siguiente fase"', false)
                ->assertSee('aria-label="Terminar sesión de estudio"', false)
                ->assertSee('aria-label="Ir a Estudio"', false)
                ->assertSee('href="'.route('estudio.index').'"', false);
        }
    }

    public function test_el_widget_tambien_se_ve_en_hoy_y_en_estudio(): void
    {
        // Con una sesión en curso, el mini-temporizador acompaña en todas las pantallas (también junto a las tarjetas propias).
        $this->get(route('hoy'))->assertOk()->assertSee('id="pomodoro-widget"', false)->assertSee('id="hoy-pomodoro"', false);
        $this->get(route('estudio.index'))->assertOk()->assertSee('id="pomodoro-widget"', false)->assertSee('id="pomodoro"', false);
    }

    public function test_en_escritorio_el_widget_queda_entre_las_secciones_y_la_cuenta(): void
    {
        $html = $this->get(route('tareas.index'))->getContent();

        // Orden en el HTML: marca, widget, menú (secciones y cuenta); el CSS lo acomoda con order en escritorio.
        $this->assertLessThan(strpos($html, 'class="collapse navbar-collapse"'), strpos($html, 'id="pomodoro-widget"'));
        $this->assertStringContainsString('nav-cuenta', $html);
    }

    public function test_el_widget_nace_oculto_y_publica_la_url_de_sesiones(): void
    {
        $html = $this->get(route('tareas.index'))->getContent();

        $this->assertMatchesRegularExpression('/id="pomodoro-widget"[^>]*data-url-sesiones="[^"]*estudio\/sesiones"[^>]*\shidden>/s', $html);
    }
}
