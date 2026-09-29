<?php

namespace Tests\Feature;

use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Hoy\CapturaRapida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CapturaRapidaTest extends TestCase
{
    use RefreshDatabase;

    private function capturar(array $datos)
    {
        return $this->withHeaders(['HX-Request' => 'true'])->post(route('hoy.captura'), $datos);
    }

    private function totales(): array
    {
        return [Tarea::count(), Recordatorio::count(), Nota::count()];
    }

    public function test_la_tarjeta_pide_titulo_descripcion_y_tipo_con_nota_por_defecto(): void
    {
        $html = $this->get(route('hoy'))->assertOk()->getContent();

        $this->assertStringContainsString('name="titulo"', $html);
        $this->assertStringContainsString('name="descripcion"', $html);
        $this->assertSame(3, substr_count($html, 'data-tipo'));
        $this->assertMatchesRegularExpression('/value="nota"\s+checked/', $html);
        $this->assertSame(5, substr_count($html, 'data-color-nota='));
        // Ya no hay contador de caracteres ni botón Limpiar.
        $this->assertStringNotContainsString('data-nota-contador', $html);
        $this->assertStringNotContainsString('data-nota-limpiar', $html);
    }

    public function test_el_formulario_renderiza_los_chips_con_sus_paneles_cerrados_por_defecto(): void
    {
        $html = $this->get(route('hoy'))->assertOk()->getContent();

        foreach (['fecha', 'destino'] as $chip) {
            $this->assertMatchesRegularExpression('/data-chip="'.$chip.'"\s+aria-expanded="false"\s+aria-controls="nota-rapida-panel-'.$chip.'"/', $html);
            $this->assertStringContainsString('id="nota-rapida-panel-'.$chip.'"', $html);
        }
        $this->assertStringNotContainsString('hoy-panel es-abierto', $html);
        $this->assertStringContainsString('Bandeja de entrada', $html);
        $this->assertStringContainsString('name="fecha"', $html);
        $this->assertStringContainsString('name="hora"', $html);
        $this->assertStringContainsString('name="contexto_id"', $html);
        $this->assertStringContainsString('class="hoy-cuaderno-titulo', $html);
        // El color ya no es un chip ni un panel: sus círculos están siempre a la vista en la cabecera.
        $this->assertStringNotContainsString('data-chip="color"', $html);
        $this->assertStringNotContainsString('nota-rapida-panel-color', $html);
    }

    public function test_los_colores_van_en_la_cabecera_de_la_tarjeta_y_no_en_la_fila_inferior(): void
    {
        $html = $this->get(route('hoy'))->assertOk()->getContent();

        $inicioCab = strpos($html, 'class="hoy-nota-cab"');
        $finCab = strpos($html, 'class="hoy-cuaderno"');
        $inicioPie = strpos($html, 'class="hoy-nota-pie"');
        $this->assertNotFalse($inicioCab);
        $this->assertLessThan($finCab, $inicioCab);

        $cabecera = substr($html, $inicioCab, $finCab - $inicioCab);
        $pie = substr($html, $inicioPie);

        $this->assertStringContainsString('Nota rápida', $cabecera);
        $this->assertSame(5, substr_count($cabecera, 'data-color-nota='));
        $this->assertSame(5, substr_count($cabecera, 'aria-pressed="false"'));
        $this->assertStringContainsString('El color se guarda solo en las notas', $cabecera);
        $this->assertSame(0, substr_count($pie, 'data-color-nota='));
        $this->assertStringNotContainsString('data-chip-punto', $html);
    }

    public function test_el_selector_de_color_esta_para_los_tres_tipos_y_conserva_el_elegido(): void
    {
        foreach (['nota', 'tarea', 'recordatorio'] as $tipo) {
            $html = $this->capturar(['tipo' => $tipo, 'titulo' => '', 'color' => 'oliva'])->getContent();

            $this->assertSame(5, substr_count($html, 'data-color-nota='), $tipo);
            $this->assertSame(1, substr_count($html, 'aria-pressed="true"'), $tipo);
            $this->assertMatchesRegularExpression('/data-color-nota="oliva"\s+aria-label="Color oliva"\s+aria-pressed="true"/', $html);
        }
    }

    public function test_los_tipos_van_como_nota_tarea_recordatorio(): void
    {
        $html = $this->get(route('hoy'))->getContent();

        $this->assertSame(1, preg_match('/value="nota".*value="tarea".*value="recordatorio"/s', $html));
    }

    public function test_un_error_en_un_campo_de_chip_abre_ese_panel_y_conserva_lo_escrito(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Álgebra']);

        $html = $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Llamar', 'hora' => '10:00'])->assertOk()
            ->assertSee('Elegí también el día para esa hora.')->getContent();

        $this->assertMatchesRegularExpression('/data-chip="fecha"\s+aria-expanded="true"/', $html);
        $this->assertMatchesRegularExpression('/class="hoy-panel es-abierto"\s+id="nota-rapida-panel-fecha"/', $html);
        $this->assertMatchesRegularExpression('/data-chip="destino"\s+aria-expanded="false"/', $html);
        $this->assertStringContainsString('value="10:00"', $html);
        $this->assertStringContainsString('value="Llamar"', $html);
        $this->assertMatchesRegularExpression('/value="recordatorio"\s+checked/', $html);

        $html = $this->capturar(['tipo' => 'nota', 'titulo' => 'X', 'color' => 'fucsia', 'contexto_id' => $contexto->id])->getContent();
        $this->assertStringContainsString('El color elegido no es válido.', $html);

        $html = $this->capturar(['tipo' => 'nota', 'titulo' => 'X', 'contexto_id' => 99999])->getContent();
        $this->assertMatchesRegularExpression('/data-chip="destino"\s+aria-expanded="true"/', $html);
        $this->assertStringContainsString('El destino elegido no existe.', $html);

        $html = $this->capturar(['tipo' => 'tarea', 'titulo' => 'X', 'fecha' => '31/12/2026'])->getContent();
        $this->assertMatchesRegularExpression('/data-chip="fecha"\s+aria-expanded="true"/', $html);
        $this->assertStringContainsString('La fecha no es una fecha válida.', $html);
    }

    public function test_tras_un_error_los_chips_muestran_los_valores_recordados(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Álgebra']);
        Carbon::setTestNow('2026-09-30 10:00');

        $html = $this->capturar(['tipo' => 'nota', 'titulo' => '', 'fecha' => '2026-10-01', 'contexto_id' => $contexto->id, 'color' => 'salvia'])->getContent();

        $this->assertStringContainsString('aria-label="Fecha: Mañana"', $html);
        $this->assertStringContainsString('aria-label="Destino: Álgebra"', $html);
        $this->assertMatchesRegularExpression('/data-color-nota="salvia"\s+aria-label="Color salvia"\s+aria-pressed="true"/', $html);
        $this->assertStringContainsString('data-color="salvia"', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$contexto->id.'"\s+selected/', $html);

        $html = $this->capturar(['tipo' => 'recordatorio', 'titulo' => '', 'fecha' => '2026-10-12', 'hora' => '10:00'])->getContent();
        $this->assertStringContainsString('aria-label="Fecha: 12 oct 10:00"', $html);
        // El destino y el color no se ofrecen a quien no los usa.
        $this->assertMatchesRegularExpression('/data-chip-grupo="destino"[^>]*data-oculto/', $html);
    }

    public function test_etiqueta_corta_de_la_fecha(): void
    {
        $hoy = Carbon::parse('2026-09-30');

        $this->assertSame('Hoy', CapturaRapida::etiquetaFecha('2026-09-30', null, $hoy));
        $this->assertSame('Mañana', CapturaRapida::etiquetaFecha('2026-10-01', null, $hoy));
        $this->assertSame('12 oct', CapturaRapida::etiquetaFecha('2026-10-12', null, $hoy));
        $this->assertSame('12 oct 10:00', CapturaRapida::etiquetaFecha('2026-10-12', '10:00', $hoy));
        $this->assertSame('5 ene 2027', CapturaRapida::etiquetaFecha('2027-01-05', null, $hoy));
        $this->assertSame('', CapturaRapida::etiquetaFecha('', '10:00', $hoy));
        $this->assertSame('', CapturaRapida::etiquetaFecha('2026-13-45', null, $hoy));
    }

    public function test_crea_una_tarea_con_titulo_descripcion_y_fecha_limite(): void
    {
        $this->capturar(['tipo' => 'tarea', 'titulo' => 'Pagar la luz', 'descripcion' => 'Vence el viernes', 'fecha' => '2026-10-02'])
            ->assertOk()
            ->assertSee('Tarea creada.');

        $tarea = Tarea::firstOrFail();
        $this->assertSame('Pagar la luz', $tarea->titulo);
        $this->assertSame('Vence el viernes', $tarea->descripcion);
        $this->assertSame('2026-10-02', $tarea->fecha_limite->toDateString());
        $this->assertSame('media', $tarea->prioridad->value);
        $this->assertSame('pendiente', $tarea->estado->value);
        $this->assertSame([1, 0, 0], $this->totales());
    }

    public function test_crea_un_recordatorio_con_mensaje_y_descripcion_y_hora(): void
    {
        $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Llamar al médico', 'descripcion' => 'Pedir turno', 'fecha' => '2026-10-01', 'hora' => '15:30'])
            ->assertOk()
            ->assertSee('Recordatorio creado.');

        $r = Recordatorio::firstOrFail();
        $this->assertSame('Llamar al médico', $r->mensaje);
        $this->assertSame('Pedir turno', $r->descripcion);
        $this->assertSame('2026-10-01 15:30', $r->recordar_en->format('Y-m-d H:i'));
        $this->assertSame([0, 1, 0], $this->totales());
    }

    public function test_recordatorio_con_solo_el_dia_queda_a_las_nueve_y_sin_fecha_queda_por_ubicar(): void
    {
        $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Con día', 'fecha' => '2026-10-05'])->assertOk();
        $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Sin fecha'])->assertOk()->assertSee('por ubicar');

        $this->assertSame('2026-10-05 09:00', Recordatorio::where('mensaje', 'Con día')->firstOrFail()->recordar_en->format('Y-m-d H:i'));
        $this->assertNull(Recordatorio::where('mensaje', 'Sin fecha')->firstOrFail()->recordar_en);
    }

    public function test_crea_una_nota_con_destino_color_y_fecha(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Álgebra']);

        $this->capturar([
            'tipo' => 'nota', 'titulo' => 'Matrices', 'descripcion' => 'Repasar determinantes',
            'contexto_id' => $contexto->id, 'color' => 'salvia', 'fecha' => '2026-10-10',
        ])->assertOk()->assertSee('Nota guardada en Álgebra');

        $nota = Nota::firstOrFail();
        $this->assertSame('Matrices', $nota->titulo);
        $this->assertSame('Repasar determinantes', $nota->contenido);
        $this->assertSame($contexto->id, $nota->contexto_id);
        $this->assertSame('salvia', $nota->color->value);
        $this->assertSame('2026-10-10', $nota->fecha->toDateString());
        $this->assertSame([0, 0, 1], $this->totales());
    }

    public function test_una_nota_sin_destino_va_a_la_bandeja_y_sin_descripcion_guarda_contenido_vacio(): void
    {
        $this->capturar(['tipo' => 'nota', 'titulo' => 'Idea'])->assertOk()->assertSee('Nota guardada en la bandeja de entrada');

        $nota = Nota::firstOrFail();
        $this->assertNull($nota->contexto_id);
        $this->assertSame('', $nota->contenido);
    }

    public function test_el_color_y_el_destino_no_aplican_a_tareas_ni_recordatorios(): void
    {
        $this->capturar(['tipo' => 'tarea', 'titulo' => 'Solo tarea', 'color' => 'fucsia', 'contexto_id' => 999])->assertOk()->assertSee('Tarea creada.');
        $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Solo aviso', 'color' => 'fucsia'])->assertOk()->assertSee('Recordatorio creado');

        $this->assertSame([1, 1, 0], $this->totales());
    }

    public function test_titulo_vacio_devuelve_el_formulario_con_el_error_y_lo_escrito(): void
    {
        $this->capturar(['tipo' => 'tarea', 'titulo' => '', 'descripcion' => 'Sin título todavía'])
            ->assertOk()
            ->assertSee('Escribí un título.')
            ->assertSee('Sin título todavía')
            ->assertSee('Revisá los campos marcados.');

        $this->assertSame([0, 0, 0], $this->totales());
    }

    public function test_descripcion_demasiado_larga_tipo_invalido_color_y_fecha_invalidos(): void
    {
        $this->capturar(['tipo' => 'tarea', 'titulo' => 'X', 'descripcion' => str_repeat('a', 5001)])
            ->assertSee('La descripción no puede superar los 5000 caracteres.');
        $this->capturar(['tipo' => 'evento', 'titulo' => 'X'])->assertSee('El tipo elegido no es válido.');
        $this->capturar(['titulo' => 'X'])->assertSee('Elegí si es una tarea, un recordatorio o una nota.');
        $this->capturar(['tipo' => 'nota', 'titulo' => 'X', 'color' => 'fucsia'])->assertSee('El color elegido no es válido.');
        $this->capturar(['tipo' => 'tarea', 'titulo' => 'X', 'fecha' => '31/12/2026'])->assertSee('La fecha no es una fecha válida.');
        $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'X', 'hora' => '10:00'])->assertSee('Elegí también el día para esa hora.');
        $this->capturar(['tipo' => 'nota', 'titulo' => str_repeat('a', 256)])->assertSee('El título no puede superar los 255 caracteres.');

        $this->assertSame([0, 0, 0], $this->totales());
    }

    public function test_sin_htmx_redirige_a_hoy_con_la_confirmacion_y_los_errores_vuelven_atras(): void
    {
        $this->post(route('hoy.captura'), ['tipo' => 'tarea', 'titulo' => 'Sin JS'])
            ->assertRedirect(route('hoy'))
            ->assertSessionHas('estado', 'Tarea creada.');

        $this->post(route('hoy.captura'), ['tipo' => 'tarea', 'titulo' => ''])->assertSessionHasErrors('titulo');
    }

    public function test_la_respuesta_actualiza_las_listas_de_hoy(): void
    {
        Carbon::setTestNow('2026-09-30 10:00');

        $html = $this->capturar(['tipo' => 'tarea', 'titulo' => 'Tarea de la semana', 'fecha' => '2026-10-01'])->assertOk()->getContent();
        $this->assertStringContainsString('id="hoy-tareas"', $html);
        $this->assertStringContainsString('hx-swap-oob="true"', $html);
        $this->assertStringContainsString('1 pendiente', $html);
        $this->assertStringContainsString('Tarea de la semana', $html);
        $this->assertStringContainsString('id="hoy-semana"', $html);
        $this->assertStringNotContainsString('id="hoy-recordatorios"', $html);

        $html = $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Aviso sin día'])->assertOk()->getContent();
        $this->assertStringContainsString('id="hoy-recordatorios"', $html);
        $this->assertStringContainsString('1 sin fecha', $html);
        $this->assertStringNotContainsString('id="hoy-tareas"', $html);
        $this->assertStringNotContainsString('id="hoy-semana"', $html);

        $html = $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Aviso del jueves', 'fecha' => '2026-10-01'])->getContent();
        $this->assertStringContainsString('Aviso del jueves', $html);
        $this->assertStringContainsString('id="hoy-semana"', $html);

        $html = $this->capturar(['tipo' => 'nota', 'titulo' => 'Solo nota'])->getContent();
        $this->assertStringNotContainsString('id="hoy-tareas"', $html);
        $this->assertStringNotContainsString('id="hoy-recordatorios"', $html);
        $this->assertStringNotContainsString('id="hoy-semana"', $html);
    }

    public function test_tras_guardar_el_formulario_queda_limpio_y_conserva_el_tipo(): void
    {
        $html = $this->capturar(['tipo' => 'recordatorio', 'titulo' => 'Llamar', 'descripcion' => 'Texto largo', 'fecha' => '2026-10-01'])->getContent();

        $this->assertMatchesRegularExpression('/value="recordatorio"\s+checked/', $html);
        $this->assertStringNotContainsString('Texto largo', $html);
        $this->assertStringContainsString('id="nota-rapida-aviso"', $html);
        $this->assertStringContainsString('Recordatorio creado.', $html);
    }

    public function test_la_ruta_de_notas_con_origen_hoy_sigue_funcionando(): void
    {
        $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('notas.store'), ['contenido' => 'Desde notas', 'origen' => 'hoy'])
            ->assertOk()
            ->assertSee('Nota guardada en la bandeja de entrada');

        $this->assertDatabaseHas('notas', ['contenido' => 'Desde notas']);
    }
}
