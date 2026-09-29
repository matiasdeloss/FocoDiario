<?php

namespace Tests\Feature;

use App\Enums\EstadoSesion;
use App\Enums\EstiloEstudio;
use App\Enums\OrigenBloque;
use App\Enums\TipoCategoria;
use App\Enums\TipoIntervalo;
use App\Models\BloqueTiempo;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Support\Duracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstudioTest extends TestCase
{
    use RefreshDatabase;

    private function datosSesion(array $cambios = []): array
    {
        return array_merge([
            'estilo' => 'clasico',
            'foco_seg' => 1500,
            'descanso_seg' => 300,
            'descanso_largo_seg' => 900,
            'pomodoros_antes_largo' => 4,
        ], $cambios);
    }

    private function datosIntervalo(array $cambios = []): array
    {
        return array_merge([
            'tipo' => 'foco',
            'clave' => '1-1',
            'inicio' => '2026-09-29T15:00:00Z',
            'fin' => '2026-09-29T15:25:00Z',
            'planificado_seg' => 1500,
            'pausado_seg' => 0,
            'completado' => true,
        ], $cambios);
    }

    public function test_las_tres_pantallas_responden(): void
    {
        $this->get(route('estudio.index'))->assertOk()->assertSee('Iniciar foco');
        $this->get(route('estudio.historial'))->assertOk()->assertSee('Todavía no hay sesiones');
        $this->get(route('estudio.metodos'))->assertOk()
            ->assertSee('Técnica Feynman')
            ->assertSee('estilos de aprendizaje')
            ->assertSee('Usar este método');
    }

    public function test_se_crea_una_sesion_con_tarea_y_contexto(): void
    {
        $tarea = Tarea::factory()->create();
        $contexto = Contexto::factory()->create();

        $this->postJson(route('estudio.sesiones.store'), $this->datosSesion([
            'tarea_id' => $tarea->id,
            'contexto_id' => $contexto->id,
            'tema' => 'Punteros',
        ]))->assertCreated()->assertJsonStructure(['id']);

        $sesion = SesionEstudio::firstOrFail();
        $this->assertSame($tarea->id, $sesion->tarea_id);
        $this->assertSame($contexto->id, $sesion->contexto_id);
        $this->assertSame(EstadoSesion::EnCurso, $sesion->estado);
        $this->assertSame(1500, $sesion->foco_seg);
    }

    public function test_al_crear_una_sesion_se_cierra_la_que_quedo_en_curso(): void
    {
        $vieja = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.store'), $this->datosSesion())->assertCreated();

        $this->assertSame(EstadoSesion::Finalizada, $vieja->fresh()->estado);
        $this->assertSame(1, SesionEstudio::enCurso()->count());
    }

    public function test_los_tiempos_editables_se_validan_con_minimos_y_maximos(): void
    {
        $rango = fn (string $nombre) => "{$nombre} debe durar entre 00:05 y 180:00 (min:seg).";
        $casos = [
            'foco_seg' => $rango('El foco'),
            'descanso_seg' => $rango('El descanso corto'),
            'descanso_largo_seg' => $rango('El descanso largo'),
        ];

        foreach ($casos as $campo => $mensaje) {
            // 4 s y 10801 s quedan fuera; 5 s y 10800 s (180 min) son los bordes válidos.
            foreach ([0, 4, 10801] as $invalido) {
                $this->postJson(route('estudio.sesiones.store'), $this->datosSesion([$campo => $invalido]))
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([$campo => $mensaje]);
            }

            foreach ([5, 10800] as $valido) {
                $this->postJson(route('estudio.sesiones.store'), $this->datosSesion([$campo => $valido]))->assertCreated();
            }
        }

        foreach ([1, 13] as $ciclos) {
            $this->postJson(route('estudio.sesiones.store'), $this->datosSesion(['pomodoros_antes_largo' => $ciclos]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['pomodoros_antes_largo' => 'Los pomodoros antes del descanso largo deben ser entre 2 y 12.']);
        }

        // Los extremos válidos juntos se aceptan y se guardan tal cual, en segundos.
        $this->postJson(route('estudio.sesiones.store'), $this->datosSesion([
            'foco_seg' => 5, 'descanso_seg' => 5, 'descanso_largo_seg' => 10800, 'pomodoros_antes_largo' => 12,
        ]))->assertCreated();
        $sesion = SesionEstudio::latest('id')->firstOrFail();
        $this->assertSame([5, 5, 10800, 12], [$sesion->foco_seg, $sesion->descanso_seg, $sesion->descanso_largo_seg, $sesion->pomodoros_antes_largo]);

        $this->postJson(route('estudio.sesiones.store'), $this->datosSesion(['foco_seg' => 'abc', 'estilo' => 'raro']))
            ->assertJsonValidationErrors(['foco_seg' => 'La duración del foco debe ser un número entero de segundos.', 'estilo' => 'El estilo de estudio elegido no es válido.']);

        $this->postJson(route('estudio.sesiones.store'), $this->datosSesion(['foco_seg' => 90.5]))
            ->assertJsonValidationErrors(['foco_seg' => 'La duración del foco debe ser un número entero de segundos.']);

        $this->postJson(route('estudio.sesiones.store'), array_diff_key($this->datosSesion(), ['descanso_seg' => 1]))
            ->assertJsonValidationErrors(['descanso_seg' => 'Indicá la duración del descanso corto.']);
    }

    public function test_los_presets_pasan_a_segundos_y_son_validos(): void
    {
        $this->assertSame(
            ['clasico' => ['foco' => 1500, 'descanso' => 300, 'largo' => 900, 'ciclos' => 4], 'bloques_largos' => ['foco' => 3000, 'descanso' => 600, 'largo' => 1200, 'ciclos' => 3]],
            EstiloEstudio::presets(),
        );

        foreach (EstiloEstudio::presets() as $clave => $t) {
            $this->postJson(route('estudio.sesiones.store'), [
                'estilo' => $clave, 'foco_seg' => $t['foco'], 'descanso_seg' => $t['descanso'],
                'descanso_largo_seg' => $t['largo'], 'pomodoros_antes_largo' => $t['ciclos'],
            ])->assertCreated();
        }
    }

    public function test_la_configuracion_de_estudio_ofrece_minutos_y_segundos_por_duracion(): void
    {
        $this->get(route('estudio.index'))->assertOk()
            ->assertSee('id="p-foco-min"', false)->assertSee('id="p-foco-seg"', false)
            ->assertSee('id="p-descanso-min"', false)->assertSee('id="p-descanso-seg"', false)
            ->assertSee('id="p-largo-min"', false)->assertSee('id="p-largo-seg"', false)
            ->assertSee('max="180"', false)
            ->assertSee('00:05 a 180:00')
            ->assertSee('Pomodoro clásico (25/5)');
    }

    public function test_el_foco_completado_genera_un_bloque_productivo_de_estudio(): void
    {
        $tarea = Tarea::factory()->create();
        $sesion = SesionEstudio::factory()->enCurso()->create(['tarea_id' => $tarea->id]);

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo())->assertCreated();

        $intervalo = IntervaloEstudio::firstOrFail();
        $this->assertSame(TipoIntervalo::Foco, $intervalo->tipo);
        $this->assertTrue($intervalo->completado);
        $this->assertSame(1500, $intervalo->duracion_seg);

        $bloque = BloqueTiempo::with('categoria')->findOrFail($intervalo->bloque_tiempo_id);
        $this->assertSame('Estudio', $bloque->categoria->nombre);
        $this->assertSame(TipoCategoria::Productiva, $bloque->categoria->tipo);
        $this->assertSame(OrigenBloque::Pomodoro, $bloque->origen);
        $this->assertSame($tarea->id, $bloque->tarea_id);
        $this->assertSame(25, $bloque->duracionEnMinutos());
    }

    public function test_el_descanso_y_el_tiempo_libre_generan_bloques_de_descanso_con_su_hora(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'tipo' => 'descanso', 'clave' => '1-2', 'inicio' => '2026-09-29T15:25:00Z', 'fin' => '2026-09-29T15:30:00Z', 'planificado_seg' => 300,
        ]))->assertCreated();
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'tipo' => 'libre', 'clave' => '1-3', 'inicio' => '2026-09-29T15:30:00Z', 'fin' => '2026-09-29T15:37:00Z', 'planificado_seg' => null,
        ]))->assertCreated();

        $this->assertSame(2, BloqueTiempo::whereHas('categoria', fn ($c) => $c->where('nombre', 'Descanso')->where('tipo', TipoCategoria::Descanso))->count());

        $libre = IntervaloEstudio::where('tipo', TipoIntervalo::Libre)->firstOrFail();
        $this->assertSame(420, $libre->duracion_seg);
        $this->assertSame('2026-09-29 15:30', $libre->inicio->setTimezone('UTC')->format('Y-m-d H:i'));
        $this->assertSame('2026-09-29 15:37', $libre->fin->setTimezone('UTC')->format('Y-m-d H:i'));
    }

    public function test_el_foco_interrumpido_no_cuenta_como_pomodoro_completo(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'fin' => '2026-09-29T15:10:00Z', 'completado' => false,
        ]))->assertCreated();
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'clave' => '1-2', 'inicio' => '2026-09-29T16:00:00Z', 'fin' => '2026-09-29T16:25:00Z',
        ]))->assertCreated();

        $sesion->load('intervalos');
        $this->assertSame(1, $sesion->pomodorosCompletados());
        $this->assertSame(1, $sesion->pomodorosInterrumpidos());
        // El interrumpido se registra con su duración real (10 min), no con la planificada.
        $this->assertSame(600, $sesion->intervalos->firstWhere('completado', false)->duracion_seg);
        $this->assertSame(2100, $sesion->segundosDe(TipoIntervalo::Foco));
    }

    public function test_las_pausas_no_cuentan_en_el_bloque(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'fin' => '2026-09-29T15:30:00Z', 'pausado_seg' => 300,
        ]))->assertCreated();

        $intervalo = IntervaloEstudio::firstOrFail();
        $this->assertSame(1500, $intervalo->duracion_seg);
        $this->assertSame(25, $intervalo->bloque->duracionEnMinutos());
    }

    public function test_un_intervalo_muy_corto_queda_en_el_historial_pero_sin_bloque(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'fin' => '2026-09-29T15:00:20Z', 'completado' => false,
        ]))->assertCreated();

        $this->assertNull(IntervaloEstudio::firstOrFail()->bloque_tiempo_id);
        $this->assertSame(0, BloqueTiempo::count());
    }

    public function test_un_intervalo_de_5_segundos_se_registra_sin_bloque_y_uno_de_60_con_bloque(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'fin' => '2026-09-29T15:00:05Z', 'planificado_seg' => 5, 'completado' => true,
        ]))->assertCreated();
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'clave' => '1-2', 'inicio' => '2026-09-29T15:01:00Z', 'fin' => '2026-09-29T15:01:59Z', 'completado' => false,
        ]))->assertCreated();
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'clave' => '1-3', 'inicio' => '2026-09-29T15:02:00Z', 'fin' => '2026-09-29T15:03:00Z', 'completado' => false,
        ]))->assertCreated();

        $corto = IntervaloEstudio::where('clave', '1-1')->firstOrFail();
        $this->assertSame(5, $corto->duracion_seg);
        $this->assertSame(5, $corto->planificado_seg);
        $this->assertTrue($corto->completado);
        $this->assertNull($corto->bloque_tiempo_id);
        $this->assertNull(IntervaloEstudio::where('clave', '1-2')->firstOrFail()->bloque_tiempo_id);

        $justo = IntervaloEstudio::where('clave', '1-3')->firstOrFail();
        $this->assertSame(60, $justo->duracion_seg);
        $this->assertNotNull($justo->bloque_tiempo_id);
        $this->assertSame(1, BloqueTiempo::count());
        $this->assertSame(1, BloqueTiempo::first()->duracionEnMinutos());
    }

    public function test_el_planificado_del_intervalo_se_valida_en_segundos(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo(['planificado_seg' => 10801]))
            ->assertJsonValidationErrors(['planificado_seg' => 'Los segundos planificados deben estar entre 0 y 10800 (3 horas).']);
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo(['planificado_seg' => 'x']))
            ->assertJsonValidationErrors(['planificado_seg' => 'Los segundos planificados deben ser un número entero.']);
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo(['planificado_seg' => 10800]))->assertCreated();
    }

    public function test_reenviar_el_mismo_intervalo_no_duplica_nada(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo())->assertCreated();
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo())->assertOk();

        $this->assertSame(1, IntervaloEstudio::count());
        $this->assertSame(1, BloqueTiempo::count());
    }

    public function test_el_intervalo_se_valida_en_espanol(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo([
            'tipo' => 'siesta', 'fin' => '2026-09-29T14:00:00Z',
        ]))->assertJsonValidationErrors([
            'tipo' => 'El tipo de intervalo no es válido.',
            'fin' => 'La hora de fin no puede ser anterior a la de inicio.',
        ]);
    }

    public function test_se_puede_finalizar_una_sesion(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();

        $this->patchJson(route('estudio.sesiones.finalizar', $sesion))->assertOk()->assertJson(['estado' => 'finalizada']);

        $sesion->refresh();
        $this->assertSame(EstadoSesion::Finalizada, $sesion->estado);
        $this->assertNotNull($sesion->finalizada_en);
    }

    public function test_el_historial_agrupa_por_dia_y_muestra_totales(): void
    {
        $sesion = SesionEstudio::factory()->create(['iniciada_en' => '2026-09-20 10:00:00', 'tema' => 'Sesion de repaso']);
        IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['clave' => 'a', 'duracion_seg' => 1500, 'completado' => true]);
        IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['clave' => 'b', 'duracion_seg' => 600, 'completado' => false]);
        IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['clave' => 'c', 'tipo' => TipoIntervalo::Descanso, 'planificado_seg' => 300, 'duracion_seg' => 360]);
        IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['clave' => 'd', 'tipo' => TipoIntervalo::Libre, 'planificado_seg' => null, 'duracion_seg' => 240]);

        $respuesta = $this->get(route('estudio.historial'))->assertOk();
        $respuesta->assertSee('Sesion de repaso')
            ->assertSee('domingo 20 de septiembre')
            ->assertSee('1 pomodoro ·', false)
            ->assertSee('35 min de foco')
            ->assertSee('6 min de descanso')
            ->assertSee('de 5 min previstos')
            ->assertSee('1 interrumpido');

        $this->assertSame(
            ['pomodoros' => 1, 'interrumpidos' => 1, 'foco_seg' => 2100, 'descanso_seg' => 360, 'libre_seg' => 240],
            $respuesta->viewData('totales'),
        );
    }

    public function test_el_historial_muestra_tiempos_cortos_en_segundos_y_horas(): void
    {
        $sesion = SesionEstudio::factory()->create(['iniciada_en' => '2026-09-21 10:00:00', 'foco_seg' => 5, 'descanso_seg' => 5, 'tema' => 'Prueba corta']);
        IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['clave' => 'a', 'duracion_seg' => 5, 'planificado_seg' => 5, 'completado' => true]);
        IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['clave' => 'b', 'duracion_seg' => 85, 'planificado_seg' => 300, 'completado' => false]);
        IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['clave' => 'c', 'tipo' => TipoIntervalo::Libre, 'planificado_seg' => null, 'duracion_seg' => 7500]);

        $this->get(route('estudio.historial'))->assertOk()
            ->assertSee('Prueba corta')
            ->assertSee('1 min 30 s de foco')
            ->assertSee('5 s / 5 s')
            ->assertSee('5 s / 1 min 30 s')
            ->assertSee('2 h 05 min');
    }

    public function test_duracion_formatea_segundos(): void
    {
        $this->assertSame('0 s', Duracion::formatearSegundos(0));
        $this->assertSame('5 s', Duracion::formatearSegundos(5));
        $this->assertSame('59 s', Duracion::formatearSegundos(59));
        $this->assertSame('1 min', Duracion::formatearSegundos(60));
        $this->assertSame('1 min 30 s', Duracion::formatearSegundos(90));
        $this->assertSame('25 min', Duracion::formatearSegundos(1500));
        $this->assertSame('59 min 59 s', Duracion::formatearSegundos(3599));
        $this->assertSame('1 h', Duracion::formatearSegundos(3600));
        $this->assertSame('2 h 05 min', Duracion::formatearSegundos(7500));
        $this->assertSame('3 h', Duracion::formatearSegundos(10800));
    }

    public function test_el_historial_filtra_por_rango_de_fechas_y_por_contexto(): void
    {
        $padre = Contexto::factory()->create(['nombre' => 'Programacion']);
        $hijo = Contexto::factory()->tema()->create(['nombre' => 'Punteros', 'contexto_padre_id' => $padre->id]);
        $otro = Contexto::factory()->create(['nombre' => 'Historia']);

        $a = SesionEstudio::factory()->create(['iniciada_en' => '2026-09-10 09:00:00', 'contexto_id' => $hijo->id, 'tema' => 'Tema A']);
        $b = SesionEstudio::factory()->create(['iniciada_en' => '2026-09-15 09:00:00', 'contexto_id' => $otro->id, 'tema' => 'Tema B']);
        $c = SesionEstudio::factory()->create(['iniciada_en' => '2026-09-25 23:30:00', 'contexto_id' => $hijo->id, 'tema' => 'Tema C']);
        foreach ([$a, $b, $c] as $sesion) {
            IntervaloEstudio::factory()->for($sesion, 'sesion')->create(['duracion_seg' => 1500]);
        }

        $this->get(route('estudio.historial', ['desde' => '2026-09-12', 'hasta' => '2026-09-25']))
            ->assertOk()->assertDontSee('Tema A')->assertSee('Tema B')->assertSee('Tema C')
            ->assertViewHas('totales', fn ($t) => $t['pomodoros'] === 2);

        // El contexto padre incluye a sus hijos.
        $this->get(route('estudio.historial', ['contexto_id' => $padre->id]))
            ->assertOk()->assertSee('Tema A')->assertSee('Tema C')->assertDontSee('Tema B')
            ->assertViewHas('totales', fn ($t) => $t['pomodoros'] === 2 && $t['foco_seg'] === 3000);

        $this->get(route('estudio.historial', ['desde' => '2026-09-30']))->assertSee('Ninguna sesión coincide con los filtros.');
    }

    public function test_los_filtros_del_historial_se_validan(): void
    {
        $this->from(route('estudio.historial'))
            ->get(route('estudio.historial', ['desde' => '2026-09-20', 'hasta' => '2026-09-10', 'contexto_id' => 999]))
            ->assertSessionHasErrors(['hasta', 'contexto_id']);

        $this->assertSame('La fecha "hasta" no puede ser anterior a "desde".', session('errors')->first('hasta'));
    }

    public function test_el_historial_se_pagina(): void
    {
        SesionEstudio::factory()->count(17)->create();

        $this->get(route('estudio.historial'))->assertOk()->assertViewHas('sesiones', fn ($s) => $s->count() === 15 && $s->total() === 17);
        $this->get(route('estudio.historial', ['page' => 2]))->assertOk()->assertViewHas('sesiones', fn ($s) => $s->count() === 2);
    }

    public function test_borrar_una_sesion_elimina_sus_intervalos_y_bloques(): void
    {
        $sesion = SesionEstudio::factory()->enCurso()->create();
        $this->postJson(route('estudio.sesiones.intervalos.store', $sesion), $this->datosIntervalo())->assertCreated();
        $otra = BloqueTiempo::factory()->create();

        $this->delete(route('estudio.sesiones.destroy', $sesion))->assertRedirect(route('estudio.historial'));

        $this->assertDatabaseCount('sesiones_estudio', 0);
        $this->assertDatabaseCount('intervalos_estudio', 0);
        $this->assertDatabaseHas('bloques_tiempo', ['id' => $otra->id]);
        $this->assertDatabaseCount('bloques_tiempo', 1);
    }

    public function test_el_borrado_con_htmx_pide_refrescar(): void
    {
        $sesion = SesionEstudio::factory()->create();

        $this->withHeaders(['HX-Request' => 'true'])->delete(route('estudio.sesiones.destroy', $sesion))
            ->assertOk()->assertHeader('HX-Refresh', 'true');
    }

    public function test_el_menu_marca_estudio_y_hoy_enlaza_al_temporizador(): void
    {
        $this->get(route('estudio.index'))->assertSee('href="'.route('estudio.index').'"', false);
        $this->get(route('hoy'))->assertSee('Ajustar tiempos y tema en Estudio')->assertSee(route('estudio.index'), false);
    }
}
