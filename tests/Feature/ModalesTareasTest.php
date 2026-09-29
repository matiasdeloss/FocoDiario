<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Modales <dialog> de crear/editar tarea y de crear/editar/eliminar columna (el JS envía JSON con X-Modal). */
class ModalesTareasTest extends TestCase
{
    use RefreshDatabase;

    private function columna(string $categoria): ColumnaTablero
    {
        return ColumnaTablero::where('categoria', $categoria)->orderBy('posicion')->firstOrFail();
    }

    private function modal(): array
    {
        return ['X-Modal' => '1', 'Accept' => 'application/json'];
    }

    public function test_el_tablero_incluye_los_modales_y_sus_disparadores_con_respaldo_sin_js(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Con modal']);

        $html = $this->get(route('tablero.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="dialogo-tarea"', $html);
        $this->assertStringContainsString('aria-labelledby="dialogo-tarea-titulo"', $html);
        $this->assertStringContainsString('id="dialogo-columna"', $html);
        $this->assertStringContainsString('id="dialogo-columna-eliminar"', $html);
        $this->assertStringContainsString('data-abrir-tarea="nueva"', $html);
        $this->assertStringContainsString('data-abrir-columna="editar"', $html);
        $this->assertStringContainsString('data-abrir-columna="eliminar"', $html);
        // La tarjeta conserva el enlace a la página de edición y suma los datos del modal.
        $this->assertStringContainsString('href="'.route('tareas.edit', $tarea).'"', $html);
        $this->assertStringContainsString('data-abrir-tarea="editar"', $html);
        // Sin JS siguen los formularios de siempre.
        $this->assertMatchesRegularExpression('#<noscript>.*tablero\.columnas\.tarjetas|<noscript>.*name="titulo"#s', $html);
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString(route('tablero.columnas.store'), $html);
    }

    public function test_la_lista_incluye_el_modal_de_tarea_y_el_enlace_de_nueva_tarea_sigue_llevando_a_la_pagina(): void
    {
        $tarea = Tarea::factory()->create();

        $html = $this->get(route('tareas.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="dialogo-tarea"', $html);
        $this->assertStringNotContainsString('id="dialogo-columna"', $html);
        // "Nueva…" ofrece tarea y recordatorio, cada uno con su modal y su página de respaldo sin JS.
        $this->assertStringContainsString('id="dialogo-recordatorio"', $html);
        $this->assertStringContainsString('href="'.route('tareas.create').'" class="t-menu-item tipo-tarea" role="menuitem" data-abrir-tarea="nueva"', $html);
        $this->assertStringContainsString('href="'.route('recordatorios.create').'" class="t-menu-item tipo-recordatorio" role="menuitem" data-abrir-recordatorio="nuevo"', $html);
        $this->assertStringContainsString('href="'.route('tareas.edit', $tarea).'"', $html);
        $this->assertStringContainsString('data-abrir-tarea="editar"', $html);
    }

    public function test_las_paginas_de_crear_y_editar_siguen_funcionando_sin_js(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Editable']);

        $this->get(route('tareas.create'))->assertOk()->assertSee('Nueva tarea');
        $this->get(route('tareas.edit', $tarea))->assertOk()->assertSee('Editable');
    }

    public function test_crear_desde_el_modal_usa_la_columna_elegida_y_su_tipo(): void
    {
        $revision = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);

        $this->postJson(route('tareas.store'), [
            'titulo' => 'Desde el modal',
            'descripcion' => 'Detalle',
            'proyecto' => 'Tesis',
            'fecha_limite' => '2026-11-01',
            'prioridad' => 'alta',
            'columna_id' => $revision->id,
        ], $this->modal())->assertCreated()->assertJsonPath('mensaje', 'Tarea creada.');

        $this->assertDatabaseHas('tareas', [
            'titulo' => 'Desde el modal',
            'columna_id' => $revision->id,
            'estado' => 'en_progreso',
            'prioridad' => 'alta',
        ]);
        $this->assertSame('Tarea creada.', session('estado'));
    }

    public function test_el_modal_devuelve_errores_por_campo_sin_crear_nada(): void
    {
        $this->postJson(route('tareas.store'), [
            'titulo' => '',
            'prioridad' => 'urgente',
            'columna_id' => 9999,
            'fecha_limite' => 'no-es-fecha',
        ], $this->modal())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['titulo', 'prioridad', 'columna_id', 'fecha_limite'])
            ->assertJsonPath('errors.titulo.0', 'Escribí un título para la tarea.');

        $this->assertDatabaseCount('tareas', 0);
    }

    public function test_sin_columna_ni_estado_se_exige_el_estado(): void
    {
        $this->post(route('tareas.store'), ['titulo' => 'X', 'prioridad' => 'media'])
            ->assertSessionHasErrors('estado');
    }

    public function test_editar_desde_el_modal_cambia_los_datos_y_mover_de_columna_ajusta_el_estado(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Vieja', 'estado' => EstadoTarea::Pendiente]);
        $hecha = $this->columna('completada');

        $this->patchJson(route('tareas.update', $tarea), [
            'titulo' => 'Nueva',
            'descripcion' => null,
            'proyecto' => null,
            'fecha_limite' => null,
            'prioridad' => 'baja',
            'columna_id' => $hecha->id,
        ], $this->modal())->assertOk()->assertJsonPath('mensaje', 'Tarea actualizada.');

        $tarea->refresh();
        $this->assertSame('Nueva', $tarea->titulo);
        $this->assertSame($hecha->id, $tarea->columna_id);
        $this->assertSame(EstadoTarea::Completada, $tarea->estado);
        $this->assertSame('Tarea actualizada.', session('estado'));
    }

    public function test_editar_desde_el_modal_valida_y_no_pierde_los_datos_guardados(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Intacta']);

        $this->patchJson(route('tareas.update', $tarea), ['titulo' => '', 'prioridad' => 'media', 'columna_id' => $tarea->columna_id], $this->modal())
            ->assertStatus(422)->assertJsonValidationErrors('titulo');

        $this->assertSame('Intacta', $tarea->fresh()->titulo);
    }

    public function test_los_datos_del_modal_de_editar_viajan_en_la_tarjeta_y_en_la_fila(): void
    {
        $tarea = Tarea::factory()->create(['titulo' => 'Con "comillas"', 'proyecto' => 'Casa', 'fecha_limite' => '2026-12-24']);

        $datos = $tarea->datosModal();
        $this->assertSame('2026-12-24', $datos['fecha_limite']);
        $this->assertSame(route('tareas.update', $tarea), $datos['url']);
        $this->assertSame($tarea->columna_id, $datos['columna_id']);

        $this->get(route('tablero.index'))->assertOk()->assertSee('Con &quot;comillas&quot;', false);
        $this->get(route('tareas.index'))->assertOk()->assertSee('Con &quot;comillas&quot;', false);
    }

    public function test_crear_y_editar_columna_desde_el_modal(): void
    {
        $this->postJson(route('tablero.columnas.store'), ['nombre' => 'Revisión', 'categoria' => 'en_progreso'], $this->modal())
            ->assertCreated();
        $this->assertSame('Columna creada.', session('estado'));

        $columna = ColumnaTablero::where('nombre', 'Revisión')->firstOrFail();

        $this->patchJson(route('tablero.columnas.update', $columna), ['nombre' => 'QA', 'categoria' => 'pendiente'], $this->modal())
            ->assertOk();
        $this->assertDatabaseHas('columnas_tablero', ['id' => $columna->id, 'nombre' => 'QA', 'categoria' => 'pendiente']);

        $this->postJson(route('tablero.columnas.store'), ['nombre' => ''], $this->modal())
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_no_se_puede_cambiar_el_tipo_de_la_unica_columna_de_completadas(): void
    {
        $hecha = $this->columna('completada');

        $this->patchJson(route('tablero.columnas.update', $hecha), ['nombre' => 'Hecho', 'categoria' => 'pendiente'], $this->modal())
            ->assertStatus(422)->assertJsonValidationErrors('categoria');
    }

    public function test_eliminar_una_columna_con_tareas_exige_destino_y_las_reasigna(): void
    {
        $extra = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);
        $tarea = Tarea::factory()->create(['columna_id' => $extra->id]);
        $destino = $this->columna('completada');

        $this->deleteJson(route('tablero.columnas.destroy', $extra), [], $this->modal())
            ->assertStatus(422)->assertJsonValidationErrors('reasignar_a');
        $this->assertDatabaseHas('columnas_tablero', ['id' => $extra->id]);

        $this->deleteJson(route('tablero.columnas.destroy', $extra), ['reasignar_a' => $destino->id], $this->modal())
            ->assertOk();
        $this->assertDatabaseMissing('columnas_tablero', ['id' => $extra->id]);
        $this->assertSame($destino->id, $tarea->fresh()->columna_id);
        $this->assertSame(EstadoTarea::Completada, $tarea->fresh()->estado);
    }

    public function test_el_boton_de_eliminar_lleva_la_cantidad_de_tareas_y_las_otras_columnas(): void
    {
        $extra = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);
        Tarea::factory()->count(2)->create(['columna_id' => $extra->id]);

        $html = $this->get(route('tablero.index'))->assertOk()->getContent();

        $this->assertStringContainsString('&quot;tareas&quot;:2', $html);
        $this->assertStringContainsString('&quot;urlEliminar&quot;:&quot;'.str_replace('/', '\\/', route('tablero.columnas.destroy', $extra)), $html);
    }

    public function test_un_json_sin_cabecera_de_modal_no_deja_avisos_en_la_sesion(): void
    {
        $this->postJson(route('tablero.columnas.store'), ['nombre' => 'Sola'])->assertCreated();

        $this->assertNull(session('estado'));
    }
}
