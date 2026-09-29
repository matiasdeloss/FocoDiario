<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Puntitos por tipo y nombre del día de la tarjeta "Esta semana" de Hoy. */
class SemanaHoyPuntosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-29 10:00')); // martes
    }

    /** @return array<string, mixed> */
    private function dia(string $fecha): array
    {
        return collect($this->get(route('hoy'))->assertOk()->viewData('semana'))->firstWhere('fecha', $fecha);
    }

    private function html(): string
    {
        return $this->get(route('hoy'))->assertOk()->getContent();
    }

    /** Fragmento del botón de un día (desde su data-fecha hasta el cierre del botón). */
    private function boton(string $html, string $fecha): string
    {
        preg_match('/<button[^>]*data-fecha="'.$fecha.'".*?<\/button>/s', $html, $m);

        return $m[0] ?? '';
    }

    public function test_los_puntos_van_por_tipo_y_luego_por_hora(): void
    {
        Nota::factory()->create(['fecha' => '2026-09-29']);
        SesionEstudio::factory()->create(['iniciada_en' => '2026-09-29 08:00', 'finalizada_en' => '2026-09-29 09:00']);
        Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-09-29 18:00']);
        Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-09-29 07:00', 'mensaje' => 'Temprano']);
        Tarea::factory()->create(['fecha_limite' => '2026-09-29']);

        $dia = $this->dia('2026-09-29');

        $this->assertSame(['tarea', 'recordatorio', 'recordatorio', 'nota', 'sesion'], array_column($dia['puntos'], 'tipo'));

        $html = $this->boton($this->html(), '2026-09-29');
        preg_match_all('/hoy-dia-punto tipo-(\w+)/', $html, $m);
        $this->assertSame(['tarea', 'recordatorio', 'recordatorio', 'nota', 'sesion'], $m[1]);
        $this->assertStringNotContainsString('hoy-dia-mas', $html);
    }

    public function test_el_tope_es_cinco_puntos_y_el_resto_se_resume_en_mas_n(): void
    {
        Tarea::factory()->count(4)->create(['fecha_limite' => '2026-09-30']);
        Recordatorio::factory()->count(2)->create(['tarea_id' => null, 'recordar_en' => '2026-09-30 12:00']);
        Nota::factory()->count(2)->create(['fecha' => '2026-09-30']);

        $html = $this->boton($this->html(), '2026-09-30');

        $this->assertSame(5, substr_count($html, 'hoy-dia-punto tipo-'));
        $this->assertSame(1, substr_count($html, 'class="hoy-dia-mas">+3<'));
        $this->assertStringContainsString('4 tareas, 2 recordatorios, 2 notas', $html);
    }

    public function test_con_cinco_o_menos_no_hay_mas_n_y_un_dia_vacio_no_tiene_puntos(): void
    {
        Tarea::factory()->count(5)->create(['fecha_limite' => '2026-10-01']);
        Nota::factory()->create(['fecha' => '2026-10-02']);
        $html = $this->html();

        $cinco = $this->boton($html, '2026-10-01');
        $this->assertSame(5, substr_count($cinco, 'hoy-dia-punto tipo-'));
        $this->assertStringNotContainsString('hoy-dia-mas', $cinco);

        $this->assertSame(1, substr_count($this->boton($html, '2026-10-02'), 'hoy-dia-punto tipo-'));

        $vacio = $this->boton($html, '2026-10-03');
        $this->assertSame(0, substr_count($vacio, 'hoy-dia-punto tipo-'));
        $this->assertStringContainsString('sábado 3: sin eventos', $vacio);
    }

    public function test_los_eventos_hechos_llevan_la_clase_atenuada_y_conservan_el_tipo(): void
    {
        Tarea::factory()->create(['fecha_limite' => '2026-09-29', 'estado' => EstadoTarea::Completada]);
        Tarea::factory()->create(['fecha_limite' => '2026-09-29']);
        Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-09-29 09:00', 'avisado_en' => '2026-09-29 09:00']);

        $html = $this->boton($this->html(), '2026-09-29');
        preg_match_all('/class="(hoy-dia-punto [^"]*)"/', $html, $m);

        $this->assertCount(3, $m[1]);
        $this->assertSame(2, count(array_filter($m[1], fn ($c) => str_contains($c, 'es-hecho'))));
        $this->assertSame(2, count(array_filter($m[1], fn ($c) => str_contains($c, 'tipo-tarea'))));
        $this->assertStringContainsString('tipo-recordatorio es-hecho', $html);
    }

    public function test_el_aria_label_resume_los_conteos_en_espanol(): void
    {
        Tarea::factory()->count(2)->create(['fecha_limite' => '2026-09-29']);
        Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-09-29 09:00']);
        Nota::factory()->create(['fecha' => '2026-09-30']);
        SesionEstudio::factory()->count(2)->create(['iniciada_en' => '2026-09-30 08:00', 'finalizada_en' => '2026-09-30 09:00']);

        $html = $this->html();

        $this->assertStringContainsString('aria-label="martes 29, hoy: 2 tareas, 1 recordatorio"', $html);
        $this->assertStringContainsString('aria-label="miércoles 30: 1 nota, 2 sesiones de estudio"', $html);
        $this->assertStringContainsString('aria-label="lunes 28: sin eventos"', $html);
        $this->assertStringContainsString('aria-pressed="true"', $this->boton($html, '2026-09-29'));
    }

    public function test_el_nombre_del_dia_se_renderiza_en_tres_variantes_ocultas_al_lector(): void
    {
        $boton = $this->boton($this->html(), '2026-09-30');

        $this->assertStringContainsString('<span class="hoy-dia-nombre" aria-hidden="true">', $boton);
        $this->assertStringContainsString('hoy-dia-nombre-completo">Miércoles<', $boton);
        $this->assertStringContainsString('hoy-dia-nombre-corto">Mié<', $boton);
        $this->assertStringContainsString('hoy-dia-nombre-min">Mi<', $boton);
        $this->assertStringContainsString('class="hoy-dia-puntos"', $boton);
    }

    public function test_los_datos_embebidos_traen_tipo_y_hecho_por_evento(): void
    {
        Tarea::factory()->create(['fecha_limite' => '2026-09-29', 'estado' => EstadoTarea::Completada]);

        $dia = $this->dia('2026-09-29');

        $this->assertSame('tarea', $dia['eventos'][0]['tipo']);
        $this->assertTrue($dia['eventos'][0]['hecho']);
        $this->assertStringContainsString('&quot;hecho&quot;:true', $this->html());
    }

    public function test_la_respuesta_de_la_captura_rapida_trae_la_semana_con_puntos(): void
    {
        $html = $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('hoy.captura'), ['tipo' => 'tarea', 'titulo' => 'Nueva', 'fecha' => '2026-09-29'])
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="hoy-semana"', $html);
        $this->assertStringContainsString('hx-swap-oob="true"', $html);
        $this->assertSame(1, substr_count($this->boton($html, '2026-09-29'), 'hoy-dia-punto tipo-tarea'));
        $this->assertStringContainsString('martes 29, hoy: 1 tarea', $html);
    }
}
