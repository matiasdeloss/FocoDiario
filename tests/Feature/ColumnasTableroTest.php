<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColumnasTableroTest extends TestCase
{
    use RefreshDatabase;

    private function columna(string $categoria): ColumnaTablero
    {
        return ColumnaTablero::where('categoria', $categoria)->orderBy('posicion')->firstOrFail();
    }

    public function test_la_migracion_deja_las_tres_columnas_de_fabrica_en_orden(): void
    {
        $this->assertSame(
            ['pendiente', 'en_progreso', 'completada'],
            ColumnaTablero::ordenadas()->get()->map(fn ($c) => $c->categoria->value)->all(),
        );
    }

    public function test_una_tarea_nueva_cae_en_la_columna_de_su_estado_y_al_cambiar_el_estado_la_sigue(): void
    {
        $tarea = Tarea::factory()->create(['estado' => EstadoTarea::EnProgreso]);
        $this->assertSame($this->columna('en_progreso')->id, $tarea->columna_id);

        // Cambio de estado por la ruta de siempre (Hoy): la columna se ajusta sola.
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        $tarea->refresh();
        $this->assertSame($this->columna('completada')->id, $tarea->columna_id);
        $this->assertSame(EstadoTarea::Completada, $tarea->estado);
    }

    public function test_crear_una_columna_la_agrega_al_final_con_categoria_por_defecto(): void
    {
        $this->postJson(route('tablero.columnas.store'), ['nombre' => 'Revisión'])
            ->assertCreated()->assertJsonPath('columna.categoria', 'en_progreso');

        $ultima = ColumnaTablero::ordenadas()->get()->last();
        $this->assertSame('Revisión', $ultima->nombre);

        $this->post(route('tablero.columnas.store'), ['nombre' => ''])->assertSessionHasErrors('nombre');
        $this->post(route('tablero.columnas.store'), ['nombre' => 'X', 'categoria' => 'nada'])->assertSessionHasErrors('categoria');
    }

    public function test_mover_una_tarea_a_una_columna_personalizada_conserva_el_estado_de_su_categoria(): void
    {
        $revision = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);
        $tarea = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->patchJson(route('tareas.columna', $tarea), ['columna_id' => $revision->id])
            ->assertOk()->assertJson(['columna_id' => $revision->id, 'estado' => 'en_progreso']);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);

        // A una columna de categoría completada: queda completada.
        $hecha = ColumnaTablero::create(['nombre' => 'Publicada', 'categoria' => EstadoTarea::Completada, 'posicion' => 10]);
        $this->patchJson(route('tareas.columna', $tarea), ['columna_id' => $hecha->id])->assertOk();
        $this->assertSame(EstadoTarea::Completada, $tarea->fresh()->estado);

        $this->patchJson(route('tareas.columna', $tarea), ['columna_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('columna_id');
    }

    public function test_el_tablero_muestra_las_columnas_nuevas_y_sus_tareas(): void
    {
        $revision = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);
        Tarea::factory()->create(['titulo' => 'Para revisar', 'columna_id' => $revision->id]);

        $respuesta = $this->get(route('tablero.index'))->assertOk();

        $columnas = $respuesta->viewData('columnas');
        $this->assertCount(4, $columnas);
        $this->assertSame(['Para revisar'], $columnas[3]['tareas']->pluck('titulo')->all());
        $respuesta->assertSee('Revisión')->assertSee('Añadir columna')->assertSee('Añadir tarjeta');
    }

    public function test_renombrar_y_cambiar_categoria_ajusta_el_estado_de_sus_tareas(): void
    {
        $columna = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);
        $tarea = Tarea::factory()->create(['columna_id' => $columna->id]);

        $this->patchJson(route('tablero.columnas.update', $columna), ['nombre' => 'Listo para publicar', 'categoria' => 'completada'])->assertOk();

        $this->assertSame('Listo para publicar', $columna->fresh()->nombre);
        $this->assertSame(EstadoTarea::Completada, $tarea->fresh()->estado);
        $this->assertSame($columna->id, $tarea->fresh()->columna_id);
    }

    public function test_no_se_puede_cambiar_la_categoria_de_la_unica_columna_completada(): void
    {
        $this->patchJson(route('tablero.columnas.update', $this->columna('completada')), ['categoria' => 'pendiente'])
            ->assertStatus(422)->assertJsonValidationErrors('categoria');
    }

    public function test_eliminar_una_columna_reasigna_sus_tareas_y_ajusta_el_estado(): void
    {
        $columna = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);
        $tarea = Tarea::factory()->create(['columna_id' => $columna->id]);
        $destino = $this->columna('completada');

        $this->deleteJson(route('tablero.columnas.destroy', $columna))->assertStatus(422)->assertJsonValidationErrors('reasignar_a');
        $this->deleteJson(route('tablero.columnas.destroy', $columna), ['reasignar_a' => $columna->id])->assertStatus(422);
        $this->assertDatabaseHas('columnas_tablero', ['id' => $columna->id]);

        $this->deleteJson(route('tablero.columnas.destroy', $columna), ['reasignar_a' => $destino->id])->assertOk();

        $this->assertDatabaseMissing('columnas_tablero', ['id' => $columna->id]);
        $this->assertSame($destino->id, $tarea->fresh()->columna_id);
        $this->assertSame(EstadoTarea::Completada, $tarea->fresh()->estado);
    }

    public function test_no_se_puede_eliminar_la_unica_columna_pendiente_ni_completada(): void
    {
        $otra = $this->columna('en_progreso');

        $this->deleteJson(route('tablero.columnas.destroy', $this->columna('pendiente')), ['reasignar_a' => $otra->id])
            ->assertStatus(422)->assertJsonValidationErrors('columna');
        $this->deleteJson(route('tablero.columnas.destroy', $this->columna('completada')), ['reasignar_a' => $otra->id])
            ->assertStatus(422);

        // En progreso sí se puede quitar (no es obligatoria).
        $this->deleteJson(route('tablero.columnas.destroy', $otra), ['reasignar_a' => $this->columna('pendiente')->id])->assertOk();
        $this->assertSame(2, ColumnaTablero::count());
    }

    public function test_reordenar_columnas_intercambia_posiciones(): void
    {
        $enProgreso = $this->columna('en_progreso');

        $this->patchJson(route('tablero.columnas.mover', $enProgreso), ['direccion' => 'izquierda'])->assertOk();
        $this->assertSame(
            ['en_progreso', 'pendiente', 'completada'],
            ColumnaTablero::ordenadas()->get()->map(fn ($c) => $c->categoria->value)->all(),
        );

        // En el borde no hace nada ni falla.
        $this->patchJson(route('tablero.columnas.mover', $enProgreso), ['direccion' => 'izquierda'])->assertOk();
        $this->patchJson(route('tablero.columnas.mover', $enProgreso), ['direccion' => 'arriba'])->assertStatus(422);
    }

    public function test_anadir_tarjeta_al_pie_de_una_columna(): void
    {
        $revision = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);

        $this->post(route('tablero.columnas.tarjetas.store', $revision), ['titulo' => 'Tarjeta rápida'])
            ->assertRedirect(route('tablero.index'));

        $tarea = Tarea::where('titulo', 'Tarjeta rápida')->firstOrFail();
        $this->assertSame($revision->id, $tarea->columna_id);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->estado);

        $this->postJson(route('tablero.columnas.tarjetas.store', $revision), ['titulo' => ''])->assertStatus(422)->assertJsonValidationErrors('titulo');
        $this->postJson(route('tablero.columnas.tarjetas.store', $revision), ['titulo' => 'Otra'])
            ->assertCreated()->assertJsonStructure(['id', 'html']);
    }

    public function test_hoy_sigue_contando_pendientes_con_tareas_en_columnas_personalizadas(): void
    {
        $revision = ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]);
        $tarea = Tarea::factory()->create(['titulo' => 'En revisión', 'columna_id' => $revision->id]);

        $this->assertTrue(Tarea::abiertas()->whereKey($tarea->id)->exists());
        $this->get(route('hoy'))->assertOk();
    }
}
