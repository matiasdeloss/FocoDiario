<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Support\TextoPendientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Alternar tareas y recordatorios desde Hoy: el servidor devuelve el estado real (fuente de verdad). */
class AlternarHoyTest extends TestCase
{
    use RefreshDatabase;

    private function conJson(): static
    {
        return $this->withHeaders(['Accept' => 'application/json']);
    }

    public function test_reactivar_deja_el_recordatorio_pendiente_con_json(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => now()->addDay(), 'avisado_en' => now()]);

        $this->conJson()->patch(route('recordatorios.reactivar', $recordatorio))
            ->assertOk()
            ->assertExactJson(['id' => $recordatorio->id, 'avisado' => false]);

        $this->assertNull($recordatorio->fresh()->avisado_en);
        $this->assertNotNull($recordatorio->fresh()->recordar_en);
    }

    public function test_avisar_y_reactivar_alternan_y_son_idempotentes(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'avisado_en' => null]);

        $this->conJson()->patch(route('recordatorios.avisar', $recordatorio))->assertOk()->assertJsonPath('avisado', true);
        $this->assertNotNull($recordatorio->fresh()->avisado_en);

        $this->conJson()->patch(route('recordatorios.reactivar', $recordatorio))->assertOk()->assertJsonPath('avisado', false);
        $this->conJson()->patch(route('recordatorios.reactivar', $recordatorio))->assertOk()->assertJsonPath('avisado', false);
        $this->assertNull($recordatorio->fresh()->avisado_en);

        $this->conJson()->patch(route('recordatorios.avisar', $recordatorio))->assertOk();
        $this->assertNotNull($recordatorio->fresh()->avisado_en);
    }

    public function test_reactivar_un_recordatorio_inexistente_da_404(): void
    {
        $this->conJson()->patch(route('recordatorios.reactivar', 999))->assertNotFound();
    }

    public function test_reactivar_con_htmx_devuelve_solo_la_fila_pendiente_y_sin_htmx_vuelve_atras(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Pagar luz', 'recordar_en' => now()->addDay(), 'avisado_en' => now()]);

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('recordatorios.reactivar', $recordatorio))
            ->assertOk()
            ->assertSee('Pagar luz')
            ->assertSee('Pendiente')
            ->assertSee('Marcar como avisado')
            ->assertDontSee('Volver a pendiente')
            ->assertDontSee('<html', false);

        $recordatorio->update(['avisado_en' => now()]);

        $this->withoutHeader('HX-Request')->from(route('recordatorios.index'))
            ->patch(route('recordatorios.reactivar', $recordatorio))
            ->assertRedirect(route('recordatorios.index'))
            ->assertSessionHas('estado', 'Recordatorio vuelto a pendiente.');
        $this->assertNull($recordatorio->fresh()->avisado_en);
    }

    public function test_el_listado_ofrece_volver_a_pendiente_solo_en_los_avisados(): void
    {
        $avisado = Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Ya avisado', 'recordar_en' => now()->addDay(), 'avisado_en' => now()]);
        $pendiente = Recordatorio::factory()->create(['tarea_id' => null, 'mensaje' => 'Todavía no', 'recordar_en' => now()->addDays(2)]);

        $html = $this->get(route('recordatorios.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('recordatorios.reactivar', $avisado), $html);
        $this->assertStringNotContainsString(route('recordatorios.reactivar', $pendiente), $html);
        $this->assertStringContainsString(route('recordatorios.avisar', $pendiente), $html);
        $this->assertStringNotContainsString(route('recordatorios.avisar', $avisado), $html);
    }

    public function test_hoy_da_a_cada_recordatorio_las_dos_rutas_para_alternar(): void
    {
        $recordatorio = Recordatorio::factory()->create(['tarea_id' => null, 'recordar_en' => now()->addDay()]);

        $html = $this->get(route('hoy'))->assertOk()->getContent();

        $this->assertStringContainsString('data-url-avisar="'.route('recordatorios.avisar', $recordatorio).'"', $html);
        $this->assertStringContainsString('data-url-reactivar="'.route('recordatorios.reactivar', $recordatorio).'"', $html);
        $this->assertStringContainsString('class="hoy-check-marca"', $html);
    }

    public function test_cambiar_estado_devuelve_el_estado_la_lista_ordenada_y_los_pendientes(): void
    {
        $alta = Tarea::factory()->create(['titulo' => 'Tarea alta', 'prioridad' => PrioridadTarea::Alta, 'fecha_limite' => null]);
        $media = Tarea::factory()->create(['titulo' => 'Tarea media', 'prioridad' => PrioridadTarea::Media, 'fecha_limite' => null]);
        $baja = Tarea::factory()->create(['titulo' => 'Tarea baja', 'prioridad' => PrioridadTarea::Baja, 'fecha_limite' => null]);

        // Completar la de prioridad media: sale de la lista (las completadas no se listan).
        $respuesta = $this->conJson()->patch(route('tareas.estado', $media), ['estado' => 'completada'])
            ->assertOk()
            ->assertJsonPath('estado', 'completada')
            ->assertJsonPath('id', $media->id)
            ->assertJsonPath('pendientes', 2);

        $this->assertSame(['Tarea alta', 'Tarea baja'], $this->titulos($respuesta->json('lista')));

        // Desmarcarla: vuelve a las abiertas en su lugar por prioridad (entre la alta y la baja).
        $respuesta = $this->conJson()->patch(route('tareas.estado', $media), ['estado' => 'pendiente'])
            ->assertOk()
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonPath('pendientes', 3);

        $this->assertSame(['Tarea alta', 'Tarea media', 'Tarea baja'], $this->titulos($respuesta->json('lista')));
        $this->assertStringNotContainsString('es-hecha', $respuesta->json('lista'));
        $this->assertNotNull($alta->id.$baja->id);
    }

    public function test_al_completar_todas_la_lista_queda_vacia_y_las_tareas_quedan_completadas_en_la_base(): void
    {
        $tareas = Tarea::factory()->count(4)->create(['fecha_limite' => null, 'prioridad' => PrioridadTarea::Media])->values();

        foreach ($tareas as $indice => $tarea) {
            $this->travel($indice + 1)->minutes();
            $respuesta = $this->conJson()->patch(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        }

        $lista = $respuesta->json('lista');

        $this->assertSame(0, substr_count($lista, 'es-hecha'));
        $this->assertSame(0, $respuesta->json('pendientes'));
        $this->assertSame([], $this->titulos($lista));
        $this->assertSame(4, Tarea::where('estado', EstadoTarea::Completada)->count());
    }

    public function test_cambiar_estado_rechaza_un_estado_invalido_sin_cambiar_nada(): void
    {
        $tarea = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->conJson()->patch(route('tareas.estado', $tarea), ['estado' => 'hecha'])->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->conJson()->patch(route('tareas.estado', $tarea), [])->assertUnprocessable();

        $this->assertSame(EstadoTarea::Pendiente, $tarea->fresh()->estado);
    }

    public function test_el_alta_rapida_devuelve_tambien_la_lista_y_el_total(): void
    {
        Tarea::factory()->create(['prioridad' => PrioridadTarea::Alta, 'titulo' => 'Existente', 'fecha_limite' => null]);

        $respuesta = $this->conJson()->post(route('tareas.store'), ['titulo' => 'Recién creada'])
            ->assertCreated()
            ->assertJsonPath('pendientes', 2);

        $this->assertSame(['Existente', 'Recién creada'], $this->titulos($respuesta->json('lista')));
        $this->assertStringContainsString('Recién creada', $respuesta->json('html'));
    }

    public function test_la_insignia_de_pendientes_usa_singular_plural_y_todo_al_dia(): void
    {
        $this->assertSame('Todo al día', TextoPendientes::para(0));
        $this->assertSame('1 pendiente', TextoPendientes::para(1));
        $this->assertSame('3 pendientes', TextoPendientes::para(3));

        $html = $this->get(route('hoy'))->assertOk()->getContent();
        $this->assertStringContainsString('hoy-insignia es-al-dia', $html);
        $this->assertStringContainsString('Todo al día', $html);
        $this->assertStringNotContainsString('0 pendientes', $html);

        Tarea::factory()->create();
        $html = $this->get(route('hoy'))->getContent();
        $this->assertStringNotContainsString('es-al-dia', $html);
        $this->assertStringContainsString('1 pendiente<', $html);
    }

    /** @return list<string> */
    private function titulos(string $html): array
    {
        preg_match_all('/class="hoy-fila-titulo">([^<]*)</', $html, $coincidencias);

        return array_map(fn (string $titulo) => html_entity_decode($titulo), $coincidencias[1]);
    }
}
