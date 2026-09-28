<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PomodoroWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_layout_incluye_el_widget_con_marcado_accesible_en_todas_las_secciones(): void
    {
        foreach (['hoy', 'tareas.index', 'recordatorios.index', 'registro.index', 'notas.index', 'recomendaciones.index', 'estudio.historial', 'estudio.metodos'] as $ruta) {
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

    public function test_el_widget_nace_oculto_y_publica_la_url_de_sesiones(): void
    {
        $html = $this->get(route('hoy'))->getContent();

        $this->assertMatchesRegularExpression('/id="pomodoro-widget"[^>]*data-url-sesiones="[^"]*estudio\/sesiones"[^>]*\shidden>/s', $html);
    }

    public function test_en_estudio_no_se_duplica_la_tarjeta_con_el_widget(): void
    {
        $this->get(route('estudio.index'))
            ->assertOk()
            ->assertDontSee('id="pomodoro-widget"', false)
            ->assertSee('id="pomodoro"', false);
    }
}
