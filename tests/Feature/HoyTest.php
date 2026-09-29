<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Enums\TipoCategoria;
use App\Enums\TipoIntervalo;
use App\Models\BloqueTiempo;
use App\Models\Categoria;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Support\CuandoCorto;
use App\Support\Saludo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HoyTest extends TestCase
{
    use RefreshDatabase;

    /** Miércoles 30/09/2026 a la hora indicada. */
    private function miercoles(string $hora = '10:00'): void
    {
        $this->travelTo(Carbon::parse("2026-09-30 {$hora}"));
    }

    public function test_hoy_responde_y_muestra_todas_las_secciones(): void
    {
        $this->miercoles();

        $this->get(route('hoy'))
            ->assertOk()
            ->assertSee('MIÉRCOLES')
            ->assertSee('30 de septiembre de 2026')
            ->assertSee('Nota rápida')
            ->assertSee('Esta semana')
            ->assertSee('Recomendaciones para ahora')
            ->assertSee('Recordatorios')
            ->assertSee('Tareas abiertas')
            ->assertSee('Pomodoro')
            ->assertSee('Nada agendado este día.')
            ->assertDontSee('Próximas tareas')
            ->assertDontSee('metrica-valor', false);
    }

    public function test_el_saludo_depende_de_la_hora_y_no_lleva_nombre(): void
    {
        $this->assertSame('Buenos días', Saludo::para(Carbon::parse('2026-09-30 09:00')));
        $this->assertSame('Buenas tardes', Saludo::para(Carbon::parse('2026-09-30 15:00')));
        $this->assertSame('Buenas noches', Saludo::para(Carbon::parse('2026-09-30 22:00')));
        $this->assertSame('Buenas noches', Saludo::para(Carbon::parse('2026-09-30 03:00')));

        $this->miercoles('15:00');
        $this->get(route('hoy'))->assertSee('Buenas tardes');
    }

    public function test_las_horas_aprovechadas_van_en_el_encabezado(): void
    {
        $this->miercoles('18:00');
        $productiva = Categoria::factory()->create(['tipo' => TipoCategoria::Productiva]);
        $ocio = Categoria::factory()->create(['tipo' => TipoCategoria::Ocio]);
        BloqueTiempo::factory()->create(['categoria_id' => $productiva->id, 'inicio' => '2026-09-30 09:00', 'fin' => '2026-09-30 10:30']);
        BloqueTiempo::factory()->create(['categoria_id' => $ocio->id, 'inicio' => '2026-09-30 11:00', 'fin' => '2026-09-30 12:00']);
        BloqueTiempo::factory()->create(['categoria_id' => $productiva->id, 'inicio' => '2026-09-29 09:00', 'fin' => '2026-09-29 12:00']);

        $this->get(route('hoy'))->assertSee('1,5 h aprovechadas hoy');
    }

    public function test_la_nota_rapida_guarda_color_destino_y_fecha_con_htmx(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Álgebra']);

        $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('notas.store'), [
                'contenido' => 'Repasar matrices',
                'contexto_id' => $contexto->id,
                'fecha' => '2026-10-10',
                'color' => 'salvia',
                'origen' => 'hoy',
            ])
            ->assertOk()
            ->assertSee('Nota guardada en Álgebra')
            ->assertDontSee('Repasar matrices');

        $this->assertDatabaseHas('notas', ['contenido' => 'Repasar matrices', 'contexto_id' => $contexto->id, 'color' => 'salvia']);
    }

    public function test_la_nota_rapida_ofrece_cinco_colores_y_el_contador(): void
    {
        $html = $this->get(route('hoy'))->assertOk()->assertSee('0 caracteres')->getContent();

        $this->assertSame(5, substr_count($html, 'data-color-nota='));
        $this->assertStringContainsString('aria-label="Color de la nota"', $html);
    }

    public function test_un_color_de_nota_invalido_se_rechaza_y_se_conserva_el_formulario(): void
    {
        $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('notas.store'), ['contenido' => 'Algo', 'color' => 'fucsia', 'origen' => 'hoy'])
            ->assertOk()
            ->assertSee('El color elegido no es válido.')
            ->assertSee('Algo');

        $this->assertDatabaseCount('notas', 0);
    }

    public function test_el_color_es_opcional_y_el_listado_de_notas_lo_muestra(): void
    {
        $this->post(route('notas.store'), ['contenido' => 'Sin color', 'origen' => 'hoy'])->assertRedirect(route('hoy'));
        $this->assertNull(Nota::firstOrFail()->color);

        Nota::factory()->create(['contenido' => 'Con color', 'color' => 'terracota']);

        $this->get(route('notas.index'))->assertOk()->assertSee('Terracota');
    }

    public function test_editar_una_nota_no_borra_su_color(): void
    {
        $nota = Nota::factory()->create(['contenido' => 'Vieja', 'color' => 'oliva']);

        $this->put(route('notas.update', $nota), ['contenido' => 'Nueva'])->assertRedirect(route('notas.index'));

        $this->assertSame('oliva', $nota->fresh()->color->value);
    }

    public function test_alta_rapida_de_tarea_con_json_usa_prioridad_media_y_pendiente(): void
    {
        $this->postJson(route('tareas.store'), ['titulo' => 'Llamar al banco'])
            ->assertCreated()
            ->assertJsonPath('titulo', 'Llamar al banco')
            ->assertJsonPath('prioridad', 'media')
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonPath('id', Tarea::firstOrFail()->id)
            ->assertSee('Llamar al banco');

        $tarea = Tarea::firstOrFail();
        $this->assertSame(PrioridadTarea::Media, $tarea->prioridad);
        $this->assertSame(EstadoTarea::Pendiente, $tarea->estado);
    }

    public function test_alta_rapida_sin_titulo_devuelve_el_error_en_espanol(): void
    {
        $this->postJson(route('tareas.store'), ['titulo' => '  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['titulo' => 'Escribí un título para la tarea.']);
    }

    public function test_el_formulario_completo_de_tareas_sigue_exigiendo_prioridad_y_estado(): void
    {
        $this->post(route('tareas.store'), ['titulo' => 'Sin prioridad'])
            ->assertSessionHasErrors(['prioridad', 'estado']);

        $this->assertDatabaseCount('tareas', 0);
    }

    public function test_completar_una_tarea_desde_hoy_con_json(): void
    {
        $tarea = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])
            ->assertOk()
            ->assertJson(['estado' => 'completada']);

        $this->assertSame(EstadoTarea::Completada, $tarea->fresh()->estado);
    }

    public function test_las_tareas_abiertas_van_por_prioridad_y_las_completadas_al_final_con_tope_de_tres(): void
    {
        Tarea::factory()->create(['titulo' => 'Tarea baja', 'prioridad' => PrioridadTarea::Baja, 'fecha_limite' => null]);
        Tarea::factory()->create(['titulo' => 'Tarea alta', 'prioridad' => PrioridadTarea::Alta, 'fecha_limite' => null]);
        Tarea::factory()->create(['titulo' => 'Tarea media', 'prioridad' => PrioridadTarea::Media, 'fecha_limite' => null]);

        foreach (range(1, 5) as $n) {
            Tarea::factory()->create(['titulo' => "Hecha {$n}", 'estado' => EstadoTarea::Completada, 'updated_at' => now()->subMinutes(10 - $n)]);
        }

        $this->get(route('hoy'))
            ->assertOk()
            ->assertSeeInOrder(['Tarea alta', 'Tarea media', 'Tarea baja', 'Hecha 5', 'Hecha 4', 'Hecha 3'])
            ->assertDontSee('Hecha 2')
            ->assertDontSee('Hecha 1')
            ->assertSee('3 pendientes')
            ->assertSee('Prioridad alta');
    }

    public function test_el_contador_de_pendientes_usa_el_singular(): void
    {
        Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->get(route('hoy'))->assertSee('1 pendiente')->assertDontSee('1 pendientes');
    }

    public function test_marcar_un_recordatorio_como_avisado_con_json(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => now()->addHour()]);

        $this->patchJson(route('recordatorios.avisar', $recordatorio))
            ->assertOk()
            ->assertJson(['avisado' => true]);

        $this->assertNotNull($recordatorio->fresh()->avisado_en);

        // Ya avisado: deja de estar en la lista de recordatorios de Hoy.
        $this->assertTrue($this->get(route('hoy'))->assertOk()->viewData('recordatorios')->isEmpty());
    }

    public function test_los_recordatorios_muestran_cuando_en_formato_corto_y_avisan_de_los_sin_fecha(): void
    {
        $this->miercoles('09:00');
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Enviar facturas', 'recordar_en' => '2026-09-30 18:00']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Llamar a soporte', 'recordar_en' => '2026-10-01 09:00']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Renovar CRM', 'recordar_en' => '2026-10-02 12:00']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Ya avisado', 'recordar_en' => '2026-10-01 10:00', 'avisado_en' => now()]);
        Recordatorio::factory()->sinFecha()->count(2)->create(['tarea_id' => null]);

        $respuesta = $this->get(route('hoy'));

        $this->assertSame(['Enviar facturas', 'Llamar a soporte', 'Renovar CRM'], $respuesta->viewData('recordatorios')->pluck('mensaje')->all());

        $respuesta
            ->assertOk()
            ->assertSeeInOrder(['Enviar facturas', 'Hoy, 18:00', 'Llamar a soporte', 'Mañana, 09:00', 'Renovar CRM', 'Vie, 12:00'])
            ->assertSee('2 sin fecha:')
            ->assertSee('ubicarlos en el calendario')
            ->assertSee(route('recordatorios.avisar', Recordatorio::where('mensaje', 'Renovar CRM')->first()), false);
    }

    public function test_el_texto_corto_de_cuando_cubre_hoy_manana_dia_y_fecha(): void
    {
        $ahora = Carbon::parse('2026-09-30 09:00');

        $this->assertSame('Hoy, 18:00', CuandoCorto::para(Carbon::parse('2026-09-30 18:00'), $ahora));
        $this->assertSame('Mañana, 09:00', CuandoCorto::para(Carbon::parse('2026-10-01 09:00'), $ahora));
        $this->assertSame('Vie, 12:00', CuandoCorto::para(Carbon::parse('2026-10-02 12:00'), $ahora));
        $this->assertSame('Ayer, 08:00', CuandoCorto::para(Carbon::parse('2026-09-29 08:00'), $ahora));
        $this->assertSame('12 oct, 09:00', CuandoCorto::para(Carbon::parse('2026-10-12 09:00'), $ahora));
    }

    public function test_los_datos_de_la_semana_traen_los_cuatro_tipos_de_evento_de_lunes_a_domingo(): void
    {
        $this->miercoles();
        Tarea::factory()->create(['titulo' => 'Entregar informe', 'fecha_limite' => '2026-09-30']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Pagar luz', 'recordar_en' => '2026-09-30 18:00']);
        Nota::factory()->create(['contenido' => 'Idea', 'titulo' => 'Idea suelta', 'fecha' => '2026-10-02']);
        SesionEstudio::factory()->create(['tema' => 'Álgebra', 'iniciada_en' => '2026-09-30 08:00', 'finalizada_en' => '2026-09-30 09:00']);
        Tarea::factory()->create(['fecha_limite' => '2026-10-05']); // lunes siguiente

        $respuesta = $this->get(route('hoy'))->assertOk();
        $semana = collect($respuesta->viewData('semana'))->keyBy('fecha');

        $this->assertCount(7, $semana);
        $this->assertSame(['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'], $semana->pluck('nombre')->values()->all());
        $this->assertTrue($semana['2026-09-30']['hoy']);
        $this->assertFalse($semana['2026-09-29']['hoy']);

        // Los eventos con hora van primero y en orden; los de todo el día, después.
        $this->assertSame(
            [['sesion', '08:00'], ['recordatorio', '18:00'], ['tarea', null]],
            collect($semana['2026-09-30']['eventos'])->map(fn ($e) => [$e['tipo'], $e['hora']])->all(),
        );
        $this->assertSame('Idea suelta', $semana['2026-10-02']['eventos'][0]['titulo']);
        $this->assertSame([], $semana['2026-10-04']['eventos']);
        $this->assertStringNotContainsString('2026-10-05', $respuesta->getContent());

        // El día de hoy se muestra ya dibujado y enlaza al calendario.
        $respuesta->assertSee('Entregar informe')
            ->assertSee('Pagar luz')
            ->assertSee('Álgebra')
            ->assertSee(route('calendario.index', ['fecha' => '2026-09-30']), false)
            ->assertSee('data-semana=', false);
    }

    public function test_la_pantalla_hoy_no_hace_consultas_por_cada_evento(): void
    {
        $this->miercoles();
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('hoy'))->assertOk();

            return count(DB::getQueryLog());
        };

        $pocas = $contar();

        Tarea::factory()->count(12)->create(['fecha_limite' => '2026-10-01']);
        Recordatorio::factory()->count(12)->create(['tarea_id' => null, 'recordar_en' => '2026-10-01 10:00']);
        Nota::factory()->count(12)->create(['fecha' => '2026-10-01']);
        SesionEstudio::factory()->count(6)->create(['iniciada_en' => '2026-10-01 08:00']);

        $this->assertSame($pocas, $contar());
    }

    public function test_las_recomendaciones_van_en_un_acordeon_accesible_con_la_segunda_abierta(): void
    {
        $this->miercoles('22:00');

        $html = $this->get(route('hoy'))->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'class="hoy-rec-boton"'));
        $this->assertSame(1, substr_count($html, 'aria-expanded="true"'));
        $this->assertStringContainsString('aria-controls="hoy-rec-1"', $html);
        $this->assertMatchesRegularExpression('/id="hoy-rec-1"[^>]*role="region"/', $html);
        $this->assertMatchesRegularExpression('/id="hoy-rec-0"[^>]*hidden\s*>/', $html);
        $this->assertStringContainsString(route('recomendaciones.index'), $html);
        $this->assertStringContainsString('Hora sugerida para acostarte', $html); // pares de datos
    }

    public function test_el_pomodoro_cuenta_solo_los_focos_completados_de_hoy(): void
    {
        $this->miercoles();
        $sesion = SesionEstudio::factory()->create(['iniciada_en' => '2026-09-30 08:00']);

        foreach ([1, 2, 3] as $n) {
            IntervaloEstudio::factory()->create(['sesion_id' => $sesion->id, 'inicio' => "2026-09-30 0{$n}:00", 'fin' => "2026-09-30 0{$n}:25"]);
        }
        IntervaloEstudio::factory()->create(['sesion_id' => $sesion->id, 'inicio' => '2026-09-30 05:00', 'fin' => '2026-09-30 05:10', 'completado' => false]);
        IntervaloEstudio::factory()->create(['sesion_id' => $sesion->id, 'tipo' => TipoIntervalo::Descanso, 'inicio' => '2026-09-30 06:00', 'fin' => '2026-09-30 06:05']);
        IntervaloEstudio::factory()->create(['sesion_id' => $sesion->id, 'inicio' => '2026-09-29 09:00', 'fin' => '2026-09-29 09:25']);

        $this->get(route('hoy'))
            ->assertOk()
            ->assertSee('data-completados-hoy="3"', false)
            ->assertSee('3 pomodoros completados hoy');
    }

    public function test_la_tarjeta_pomodoro_tiene_los_modos_controles_y_datos_para_el_motor(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create(['iniciada_en' => now()]);

        $this->get(route('hoy'))
            ->assertOk()
            ->assertSee('Enfoque')
            ->assertSee('Descanso')
            ->assertSee('Pausa larga')
            ->assertSee('data-url-sesiones="'.route('estudio.sesiones.store').'"', false)
            ->assertSee('data-sesion-activa="'.$sesion->id.'"', false)
            ->assertSee('aria-label="Reiniciar temporizador"', false)
            ->assertSee('role="timer"', false)
            ->assertSee('0 pomodoros completados hoy')
            ->assertDontSee('id="pomodoro-widget"', false);
    }
}
