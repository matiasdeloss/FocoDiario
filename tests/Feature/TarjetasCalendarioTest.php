<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Services\Calendario\EventosCalendario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarjetasCalendarioTest extends TestCase
{
    use RefreshDatabase;

    private const RANGO = ['start' => '2026-10-01T00:00:00', 'end' => '2026-11-01T00:00:00'];

    public function test_crear_una_tarjeta_sin_fecha_trae_el_mensaje_para_el_aviso_de_por_ubicar(): void
    {
        foreach (['tarea', 'recordatorio', 'nota'] as $tipo) {
            $this->postJson(route('calendario.tarjetas.store'), ['tipo' => $tipo])
                ->assertCreated()->assertJsonPath('mensaje', 'Tarjeta agregada a Por ubicar.');
        }

        // Con fecha va directo al calendario: no es "Por ubicar", no hay aviso.
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'tarea', 'fecha' => '2026-10-10'])
            ->assertCreated()->assertJsonPath('mensaje', null);
    }

    public function test_las_tarjetas_de_por_ubicar_muestran_su_contexto_con_el_color_efectivo(): void
    {
        $entorno = Contexto::factory()->entorno()->create(['nombre' => 'Carrera', 'color' => '#3b8e9b']);
        $materia = Contexto::factory()->create(['nombre' => 'Programación II', 'contexto_padre_id' => $entorno->id, 'color' => null]);
        $suelto = Contexto::factory()->proyecto()->create(['nombre' => 'Sin color propio', 'color' => null]);

        Tarea::factory()->create(['titulo' => 'Con contexto', 'fecha_limite' => null, 'contexto_id' => $materia->id]);
        Nota::factory()->create(['titulo' => 'Nota con contexto', 'fecha' => null, 'contexto_id' => $suelto->id]);
        Tarea::factory()->create(['titulo' => 'Sin contexto', 'fecha_limite' => null, 'contexto_id' => null]);
        Nota::factory()->create(['titulo' => 'Nota sin contexto', 'fecha' => null, 'contexto_id' => null]);
        Recordatorio::factory()->create(['mensaje' => 'Aviso', 'recordar_en' => null, 'tarea_id' => null]);

        $html = $this->get(route('calendario.index'))->assertOk()->getContent();

        // Nombre en la tarjeta, ruta completa en el tooltip y el color heredado del entorno.
        $this->assertStringContainsString('title="Carrera › Programación II"', $html);
        $this->assertMatchesRegularExpression('/<span class="tarj-contexto actividad-celeste"[^>]*>.*?Programación II/s', $html);
        // El contexto sin color en toda su cadena se ve en neutro.
        $this->assertMatchesRegularExpression('/<span class="tarj-contexto tarj-contexto-neutro" title="Sin color propio"/', $html);
        // Solo las dos tarjetas con contexto lo muestran: las demás (y los recordatorios) no llevan nada extra.
        $this->assertSame(2, substr_count($html, 'class="tarj-contexto '));
    }

    public function test_el_panel_no_consulta_el_contexto_por_cada_tarjeta(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Redes', 'color' => '#5f86a3']);
        foreach (range(1, 8) as $i) {
            Tarea::factory()->create(['fecha_limite' => null, 'contexto_id' => Contexto::factory()->create(['contexto_padre_id' => $contexto->id])->id]);
            Nota::factory()->create(['fecha' => null, 'contexto_id' => $contexto->id]);
        }

        \DB::enableQueryLog();
        $this->get(route('calendario.index'))->assertOk();
        $consultas = collect(\DB::getQueryLog())->pluck('query')
            ->filter(fn ($q) => str_contains($q, 'from "contextos"') || str_contains($q, 'from `contextos`'));
        \DB::flushQueryLog();

        // Rutas, colores y las relaciones en bloque: un puñado fijo, no una por tarjeta.
        $this->assertLessThanOrEqual(6, $consultas->count(), $consultas->implode(' | '));
    }

    public function test_se_crea_cada_tipo_de_tarjeta_sin_fecha(): void
    {
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'tarea'])
            ->assertCreated()
            ->assertJsonPath('tarjeta.tipo', 'tarea')
            ->assertJsonPath('evento', null)
            ->assertJsonPath('panel', fn ($html) => str_contains($html, 'data-nueva="1"') && str_contains($html, 'tipo-tarea'));
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'recordatorio'])->assertCreated()->assertJsonPath('tarjeta.tipo', 'recordatorio');
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'nota'])->assertCreated()->assertJsonPath('tarjeta.tipo', 'nota');

        $tarea = Tarea::first();
        $this->assertSame('', $tarea->titulo);
        $this->assertNull($tarea->fecha_limite);
        $this->assertSame('media', $tarea->prioridad->value);
        $this->assertNull(Recordatorio::first()->recordar_en);
        $this->assertNull(Nota::first()->fecha);
    }

    public function test_se_crea_una_tarjeta_ya_con_fecha_y_el_recordatorio_toma_las_09(): void
    {
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'tarea', 'fecha' => '2026-10-10'])
            ->assertCreated()->assertJsonPath('evento.start', '2026-10-10')->assertJsonPath('panel', null);
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'nota', 'fecha' => '2026-10-11'])
            ->assertCreated()->assertJsonPath('evento.start', '2026-10-11');
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'recordatorio', 'fecha' => '2026-10-12'])
            ->assertCreated()->assertJsonPath('evento.start', '2026-10-12T09:00:00');
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'recordatorio', 'fecha' => '2026-10-13T15:30:00'])
            ->assertCreated()->assertJsonPath('evento.start', '2026-10-13T15:30:00');

        $this->assertCount(4, $this->getJson(route('calendario.eventos', self::RANGO))->json());
    }

    public function test_crear_valida_en_espanol(): void
    {
        $this->postJson(route('calendario.tarjetas.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['tipo' => 'Elegí qué querés crear: tarea, recordatorio o nota.']);
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'evento'])
            ->assertUnprocessable()->assertJsonValidationErrors(['tipo' => 'El tipo elegido no es válido.']);
        $this->postJson(route('calendario.tarjetas.store'), ['tipo' => 'tarea', 'fecha' => 'ayer'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha' => 'La fecha no es válida.']);
    }

    public function test_se_actualizan_titulo_y_comentario_de_cada_tipo(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => '', 'fecha_limite' => null]);
        $recordatorio = Recordatorio::factory()->sinFecha()->create(['tarea_id' => null, 'mensaje' => '']);
        $nota = Nota::factory()->create(['contenido' => '']);

        $this->patchJson(route('calendario.tarjetas.update', ['tarea', $tarea->id]), ['titulo' => ' Comprar pan ', 'comentario' => 'Integral'])
            ->assertOk()->assertJsonPath('tarjeta.titulo', 'Comprar pan');
        $this->patchJson(route('calendario.tarjetas.update', ['recordatorio', $recordatorio->id]), ['titulo' => 'Llamar', 'comentario' => 'Al banco'])->assertOk();
        $this->patchJson(route('calendario.tarjetas.update', ['nota', $nota->id]), ['titulo' => 'Idea', 'comentario' => 'Texto largo'])->assertOk();

        $this->assertSame(['Comprar pan', 'Integral'], [$tarea->fresh()->titulo, $tarea->fresh()->descripcion]);
        $this->assertSame(['Llamar', 'Al banco'], [$recordatorio->fresh()->mensaje, $recordatorio->fresh()->descripcion]);
        $this->assertSame(['Idea', 'Texto largo'], [$nota->fresh()->titulo, $nota->fresh()->contenido]);

        // Solo se guardan los campos enviados
        $this->patchJson(route('calendario.tarjetas.update', ['tarea', $tarea->id]), ['comentario' => null])->assertOk();
        $this->assertSame('Comprar pan', $tarea->fresh()->titulo);
        $this->assertNull($tarea->fresh()->descripcion);

        $this->patchJson(route('calendario.tarjetas.update', ['nota', $nota->id]), ['titulo' => str_repeat('a', 300)])
            ->assertUnprocessable()->assertJsonValidationErrors(['titulo' => 'El título no puede superar los 255 caracteres.']);
    }

    public function test_se_descarta_una_tarjeta_vacia_y_se_elimina_cualquiera(): void
    {
        foreach (['tarea', 'recordatorio', 'nota'] as $tipo) {
            $id = $this->postJson(route('calendario.tarjetas.store'), ['tipo' => $tipo])->json('tarjeta.id');
            $this->deleteJson(route('calendario.tarjetas.destroy', [$tipo, $id]))->assertNoContent();
        }

        $this->assertSame(0, Tarea::count() + Recordatorio::count() + Nota::count());
        $this->deleteJson(route('calendario.tarjetas.destroy', ['tarea', 999]))->assertNotFound();
        $this->deleteJson('/calendario/tarjetas/evento/1')->assertNotFound();
    }

    public function test_se_elimina_una_sesion_de_estudio_desde_el_calendario(): void
    {
        $sesion = SesionEstudio::factory()->create();

        $this->deleteJson(route('calendario.tarjetas.destroy', ['sesion', $sesion->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('sesiones_estudio', ['id' => $sesion->id]);
    }

    public function test_se_asigna_y_se_quita_la_fecha_de_una_nota(): void
    {
        $nota = Nota::factory()->create(['fecha' => null]);

        $this->patchJson(route('calendario.notas.fecha', $nota), ['fecha' => '2026-10-20'])
            ->assertOk()->assertJsonPath('evento.start', '2026-10-20')->assertJsonPath('panel', null);
        $this->assertSame('2026-10-20', $nota->fresh()->fecha->toDateString());

        $this->patchJson(route('calendario.notas.fecha', $nota), ['fecha' => null])
            ->assertOk()->assertJsonPath('evento', null)
            ->assertJsonPath('panel', fn ($html) => str_contains($html, 'data-tipo="nota"'));
        $this->assertNull($nota->fresh()->fecha);

        $this->patchJson(route('calendario.notas.fecha', $nota), ['fecha' => '20/10/2026'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha' => 'La fecha debe tener el formato AAAA-MM-DD.']);
    }

    public function test_el_recordatorio_toma_las_09_si_solo_llega_el_dia_y_se_quita_con_null(): void
    {
        $recordatorio = Recordatorio::factory()->sinFecha()->create(['tarea_id' => null]);

        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), ['fecha' => '2026-10-20'])
            ->assertOk()->assertJsonPath('recordar_en', '2026-10-20T09:00:00')->assertJsonPath('panel', null);
        $this->assertSame('2026-10-20 09:00:00', $recordatorio->fresh()->recordar_en->format('Y-m-d H:i:s'));

        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), ['recordar_en' => '2026-10-21T17:45:00'])->assertOk();
        $this->assertSame('2026-10-21 17:45:00', $recordatorio->fresh()->recordar_en->format('Y-m-d H:i:s'));

        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), ['recordar_en' => null])
            ->assertOk()->assertJsonPath('evento', null)
            ->assertJsonPath('panel', fn ($html) => str_contains($html, 'data-tipo="recordatorio"'));
        $this->assertNull($recordatorio->fresh()->recordar_en);

        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), [])
            ->assertUnprocessable()->assertJsonValidationErrors('recordar_en');
    }

    public function test_un_recordatorio_ya_avisado_no_se_puede_reubicar_ni_quitar(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => '2026-10-05 10:00:00', 'avisado_en' => now()]);

        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), ['recordar_en' => '2026-10-20T10:00:00'])
            ->assertUnprocessable()->assertJsonPath('message', 'Los recordatorios ya avisados no se pueden reubicar.');
        $this->patchJson(route('calendario.recordatorios.fecha', $recordatorio), ['recordar_en' => null])->assertUnprocessable();

        $this->assertSame('2026-10-05 10:00:00', $recordatorio->fresh()->recordar_en->format('Y-m-d H:i:s'));
    }

    public function test_una_tarea_completada_no_se_puede_quitar_del_calendario(): void
    {
        $tarea = Tarea::factory()->create(['fecha_limite' => '2026-10-05', 'estado' => EstadoTarea::Completada]);

        $this->patchJson(route('calendario.tareas.fecha', $tarea), ['fecha' => null])->assertUnprocessable();
        $this->assertNotNull($tarea->fresh()->fecha_limite);
    }

    public function test_el_panel_muestra_lo_sin_fecha_de_cada_tipo(): void
    {
        Tarea::factory()->create(['titulo' => 'Tarea suelta', 'fecha_limite' => null]);
        Tarea::factory()->create(['titulo' => 'Tarea con dia', 'fecha_limite' => '2026-10-05']);
        Tarea::factory()->create(['titulo' => 'Tarea hecha', 'fecha_limite' => null, 'estado' => EstadoTarea::Completada]);
        Recordatorio::factory()->sinFecha()->create(['tarea_id' => null, 'mensaje' => 'Aviso suelto']);
        Recordatorio::factory()->sinFecha()->create(['tarea_id' => null, 'mensaje' => 'Aviso ya avisado', 'avisado_en' => now()]);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Aviso con dia', 'recordar_en' => '2026-10-05 09:00:00']);
        Nota::factory()->create(['titulo' => 'Nota suelta', 'fecha' => null]);
        Nota::factory()->create(['titulo' => 'Nota con dia', 'fecha' => '2026-10-05']);
        Nota::factory()->create(['titulo' => null, 'contenido' => 'Nota vieja sin titulo', 'fecha' => null]);

        $this->get(route('calendario.index'))->assertOk()
            ->assertSee('Por ubicar')
            ->assertSee('Nueva tarea')->assertSee('Nuevo recordatorio')->assertSee('Nueva nota')
            ->assertSee('Tarea suelta')->assertSee('Aviso suelto')->assertSee('Nota suelta')
            ->assertSee('Nota vieja sin titulo') // el contenido recortado hace de título
            ->assertDontSee('Tarea con dia')->assertDontSee('Tarea hecha')
            ->assertDontSee('Aviso con dia')->assertDontSee('Aviso ya avisado')->assertDontSee('Nota con dia');
    }

    public function test_el_panel_limita_por_tipo_y_ver_mas_entrega_el_resto_sin_repetir(): void
    {
        Nota::factory()->count(25)->create(['fecha' => null]);
        $respuesta = $this->get(route('calendario.index'))->assertOk();
        $this->assertSame(20, substr_count($respuesta->getContent(), 'data-tipo="nota" data-id='));
        $respuesta->assertSee('data-ver-mas="nota"', false);

        $mostradas = Nota::whereNull('fecha')->orderByDesc('created_at')->orderByDesc('id')->limit(20)->pluck('id')->all();
        $pagina = $this->getJson(route('calendario.tarjetas.index', ['nota', 'excluir' => $mostradas]))->assertOk();
        $this->assertSame(5, substr_count($pagina->json('html'), 'data-tipo="nota"'));
        $this->assertFalse($pagina->json('hayMas'));
        foreach ($mostradas as $id) {
            $this->assertStringNotContainsString('data-tipo="nota" data-id="'.$id.'"', $pagina->json('html'));
        }
    }

    public function test_el_panel_no_hace_consultas_por_cada_tarjeta(): void
    {
        Tarea::factory()->count(10)->create(['fecha_limite' => null]);
        Recordatorio::factory()->sinFecha()->count(10)->create(['tarea_id' => null]);
        Nota::factory()->count(10)->create();

        \DB::enableQueryLog();
        $this->get(route('calendario.index'))->assertOk();

        $this->assertLessThanOrEqual(6, count(\DB::getQueryLog()));
    }

    public function test_los_recordatorios_sin_fecha_no_son_eventos_y_los_con_fecha_si(): void
    {
        Recordatorio::factory()->sinFecha()->create(['tarea_id' => null, 'mensaje' => 'Sin ubicar']);
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Ubicado', 'recordar_en' => '2026-10-05 09:00:00']);

        $titulos = collect($this->getJson(route('calendario.eventos', self::RANGO))->assertOk()->json())->pluck('title')->all();

        $this->assertSame(['Ubicado'], $titulos);
    }

    public function test_los_eventos_llevan_los_datos_del_editor_y_las_notas_viejas_usan_su_contenido(): void
    {
        Nota::factory()->create(['titulo' => null, 'contenido' => 'Contenido de una nota vieja', 'fecha' => '2026-10-05']);
        Tarea::factory()->create(['titulo' => 'Con detalle', 'descripcion' => 'Detalle', 'fecha_limite' => '2026-10-06']);

        $eventos = collect($this->getJson(route('calendario.eventos', self::RANGO))->json())->keyBy('extendedProps.tipo');

        $this->assertSame('Contenido de una nota vieja', $eventos['nota']['title']);
        $this->assertSame('Detalle', $eventos['tarea']['extendedProps']['tarjeta']['comentario']);
        $this->assertSame('2026-10-06', $eventos['tarea']['extendedProps']['tarjeta']['fecha']);
        $this->assertTrue($eventos['nota']['editable']);
    }

    public function test_recordatorio_sin_fecha_en_el_listado_aparte_hoy_y_marcar_avisado(): void
    {
        Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Con fecha', 'recordar_en' => now()->addDay()]);
        $suelto = Recordatorio::factory()->sinFecha()->create(['tarea_id' => null, 'mensaje' => 'Recordatorio suelto']);

        $this->get(route('tareas.index', ['tipo' => 'recordatorio']))->assertOk()
            ->assertSeeInOrder(['Con fecha', 'Sin fecha', 'Recordatorio suelto']);

        // Hoy: solo lista los que tienen fecha, y avisa de los otros
        $this->get(route('hoy'))->assertOk()
            ->assertSee('Con fecha')->assertDontSee('Recordatorio suelto')
            ->assertSee('1 sin fecha:')->assertSee('ubicarlos en el calendario');

        // Marcar como avisado no falla sin fecha
        $this->patch(route('recordatorios.avisar', $suelto), [], ['HX-Request' => 'true'])->assertOk()->assertSee('aria-checked="true"', false);
        $this->assertNotNull($suelto->fresh()->avisado_en);
    }

    public function test_el_formulario_completo_acepta_recordatorio_sin_fecha_y_comentarios(): void
    {
        $this->post(route('recordatorios.store'), ['mensaje' => 'Sin fecha aun', 'recordar_en' => '', 'descripcion' => 'Detalle'])
            ->assertRedirect(route('tareas.index'));
        $this->assertNull(Recordatorio::first()->recordar_en);
        $this->assertSame('Detalle', Recordatorio::first()->descripcion);

        $this->get(route('recordatorios.edit', Recordatorio::first()))->assertOk()->assertSee('Detalle');

        $this->post(route('notas.store'), ['titulo' => 'Solo titulo', 'contenido' => ''])->assertRedirect();
        $this->assertSame('', Nota::first()->contenido);
        $this->post(route('notas.store'), ['titulo' => '', 'contenido' => ''])->assertSessionHasErrors('contenido');

        $this->post(route('tareas.store'), ['titulo' => 'Con comentario', 'descripcion' => 'Más info', 'prioridad' => 'media', 'estado' => 'pendiente'])
            ->assertRedirect();
        $this->assertSame('Más info', Tarea::first()->descripcion);
    }

    public function test_la_tira_semanal_ignora_recordatorios_sin_fecha(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30)->setTime(10, 0));
        Recordatorio::factory()->sinFecha()->create(['tarea_id' => null]);

        $this->get(route('hoy'))->assertOk();
        $conteos = collect(app(EventosCalendario::class)->semanaActual())->pluck('conteos')->flatten()->sum();
        $this->assertSame(0, $conteos);
    }

    public function test_editar_completo_en_el_lugar_guarda_los_campos_de_cada_tipo(): void
    {
        $tarea = Tarea::factory()->create(['contexto_id' => null, 'estado' => EstadoTarea::Pendiente, 'fecha_limite' => null]);
        $vinculada = Tarea::factory()->create(['titulo' => 'Tarea vinculada']);
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => null]);
        $contexto = Contexto::factory()->create(['nombre' => 'Redes']);
        $nota = Nota::factory()->create(['contexto_id' => null, 'fijada' => false, 'color' => '#728a58']);

        // Tarea: contexto y estado (sin contexto se guarda null).
        $this->patchJson(route('calendario.tarjetas.update', ['tarea', $tarea->id]), ['contexto_id' => $contexto->id, 'estado' => 'en_progreso'])
            ->assertOk()->assertJsonPath('detalle.contexto_id', $contexto->id)->assertJsonPath('detalle.estado', 'en_progreso');
        $this->patchJson(route('calendario.tarjetas.update', ['tarea', $tarea->id]), ['contexto_id' => null])->assertOk();
        $this->assertNull($tarea->fresh()->contexto_id);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);

        // Nota: materia, fijada y sin color.
        $this->patchJson(route('calendario.tarjetas.update', ['nota', $nota->id]), ['contexto_id' => $contexto->id, 'fijada' => true, 'color' => null])
            ->assertOk()->assertJsonPath('detalle.contexto_id', $contexto->id)->assertJsonPath('detalle.fijada', true);
        $this->assertNull($nota->fresh()->color);

        // Recordatorio: tarea vinculada.
        $this->patchJson(route('calendario.tarjetas.update', ['recordatorio', $recordatorio->id]), ['tarea_id' => $vinculada->id])
            ->assertOk()->assertJsonPath('detalle.tarea_id', $vinculada->id);

        // Los campos de otro tipo se ignoran.
        $this->patchJson(route('calendario.tarjetas.update', ['nota', $nota->id]), ['estado' => 'completada', 'prioridad' => 'alta'])->assertOk();
    }

    public function test_el_detalle_trae_las_opciones_para_editar_en_el_lugar(): void
    {
        $portfolio = Contexto::factory()->proyecto()->create(['nombre' => 'Portfolio']);
        $tarea = Tarea::factory()->create(['contexto_id' => $portfolio->id]);
        Contexto::factory()->create(['nombre' => 'Redes']);
        $nota = Nota::factory()->create();
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => $tarea->id]);

        $this->getJson(route('calendario.detalle', ['tipo' => 'tarea', 'id' => $tarea->id]))
            ->assertJsonPath('detalle.contexto_id', $portfolio->id)
            ->assertJsonPath('detalle.destinos.0.etiqueta', 'Portfolio')
            ->assertJsonPath('detalle.destinos.1.etiqueta', 'Redes')
            ->assertJsonPath('detalle.estados.0', ['valor' => 'pendiente', 'etiqueta' => 'Pendiente']);
        $this->getJson(route('calendario.detalle', ['tipo' => 'nota', 'id' => $nota->id]))
            ->assertJsonPath('detalle.destinos.1.etiqueta', 'Redes')
            ->assertJsonStructure(['detalle' => ['contexto_id', 'fijada', 'colores']]);
        $this->getJson(route('calendario.detalle', ['tipo' => 'recordatorio', 'id' => $recordatorio->id]))
            ->assertJsonStructure(['detalle' => ['tarea_id', 'tareas' => [['valor', 'etiqueta']]]]);
    }

    public function test_editar_en_el_lugar_valida_los_valores(): void
    {
        $tarea = Tarea::factory()->create();
        $nota = Nota::factory()->create();

        $this->patchJson(route('calendario.tarjetas.update', ['tarea', $tarea->id]), ['estado' => 'archivada'])
            ->assertJsonValidationErrors(['estado' => 'El estado elegido no es válido.']);
        $this->patchJson(route('calendario.tarjetas.update', ['nota', $nota->id]), ['contexto_id' => 999])
            ->assertJsonValidationErrors(['contexto_id' => 'El contexto elegido ya no existe.']);
    }
}
