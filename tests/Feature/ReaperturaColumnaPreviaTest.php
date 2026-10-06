<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Nota;
use App\Models\Tablero;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Al reabrir una tarea o nota completada vuelve a la columna que tenía antes de completarse. */
class ReaperturaColumnaPreviaTest extends TestCase
{
    use RefreshDatabase;

    private function columna(string $categoria, ?Tablero $tablero = null): ColumnaTablero
    {
        $consulta = ColumnaTablero::where('categoria', $categoria)->orderBy('posicion');

        if ($tablero !== null) {
            $consulta->where('tablero_id', $tablero->id);
        }

        return $consulta->firstOrFail();
    }

    private function revision(?Tablero $tablero = null): ColumnaTablero
    {
        return ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9, 'tablero_id' => $tablero?->id]);
    }

    private function otroTablero(): Tablero
    {
        $this->post(route('tableros.store'), ['nombre' => 'Facultad'])->assertRedirect();

        return Tablero::where('nombre', 'Facultad')->firstOrFail();
    }

    public function test_una_tarea_completada_desde_hoy_vuelve_a_su_columna_previa_al_reabrirse(): void
    {
        $revision = $this->revision();
        $tarea = Tarea::factory()->create(['columna_id' => $revision->id]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        $this->assertSame($this->columna('completada')->id, $tarea->fresh()->columna_id);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'pendiente'])->assertOk();

        $tarea->refresh();
        $this->assertSame($revision->id, $tarea->columna_id);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->estado);
        $this->assertNull($tarea->columna_previa_id);
    }

    public function test_una_nota_recuerda_su_columna_previa_al_completarse(): void
    {
        $revision = $this->revision();
        $nota = Nota::factory()->create(['columna_id' => $revision->id]);

        $this->patchJson(route('notas.columna', $nota), ['columna_id' => $this->columna('completada')->id])->assertOk();
        $this->assertSame($revision->id, $nota->fresh()->columna_previa_id);

        $this->assertSame($this->columna('completada')->id, $nota->fresh()->columna_id);
    }

    public function test_si_la_columna_previa_se_borra_vuelve_a_sin_asignar(): void
    {
        $revision = $this->revision();
        $tarea = Tarea::factory()->create(['columna_id' => $revision->id]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();

        $revision->delete();

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'pendiente'])->assertOk();

        $this->assertSame(ColumnaTablero::sinAsignarDe()->id, $tarea->fresh()->columna_id);
    }

    public function test_un_movimiento_explicito_despues_de_completar_ignora_la_memoria(): void
    {
        $revision = $this->revision();
        $pendiente = $this->columna('pendiente');
        $tarea = Tarea::factory()->create(['columna_id' => $revision->id]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        $this->patchJson(route('tareas.columna', $tarea), ['columna_id' => $pendiente->id])->assertOk();

        $tarea->refresh();
        $this->assertSame($pendiente->id, $tarea->columna_id);
        $this->assertNull($tarea->columna_previa_id);

        // Y al completarla de nuevo recuerda la columna desde la que se completó esta vez.
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'pendiente'])->assertOk();
        $this->assertSame($pendiente->id, $tarea->fresh()->columna_id);
    }

    public function test_una_columna_elegida_al_reabrir_gana_sobre_la_memoria(): void
    {
        $revision = $this->revision();
        $tarea = Tarea::factory()->create(['columna_id' => $revision->id]);
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();

        $this->patchJson(route('tareas.update', $tarea), [
            'titulo' => $tarea->titulo,
            'prioridad' => $tarea->prioridad->value,
            'columna_id' => ColumnaTablero::sinAsignarDe()->id,
        ])->assertSuccessful();

        $this->assertSame(ColumnaTablero::sinAsignarDe()->id, $tarea->fresh()->columna_id);
    }

    public function test_si_se_mueve_a_otro_tablero_al_reabrir_va_a_sin_asignar_del_tablero_actual(): void
    {
        $otro = $this->otroTablero();
        $revision = $this->revision();
        $tarea = Tarea::factory()->create(['columna_id' => $revision->id]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();

        // Pasa a la completada del otro tablero (movimiento explícito entre completadas).
        $completadaOtro = $this->columna('completada', $otro);
        $this->patchJson(route('tareas.columna', $tarea), ['columna_id' => $completadaOtro->id])->assertOk();

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'pendiente'])->assertOk();

        $this->assertSame(ColumnaTablero::sinAsignarDe($otro->id)->id, $tarea->fresh()->columna_id);
    }

    public function test_destildar_devuelve_a_en_progreso_aunque_se_pida_pendiente(): void
    {
        $enProgreso = $this->columna('en_progreso');
        $tarea = Tarea::factory()->create(['columna_id' => $enProgreso->id]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'pendiente'])->assertOk();

        $tarea->refresh();
        $this->assertSame($enProgreso->id, $tarea->columna_id);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->estado);
    }

    public function test_pedir_en_progreso_desde_un_formulario_va_a_esa_columna_y_no_a_la_previa_pendiente(): void
    {
        $pendiente = $this->columna('pendiente');
        $enProgreso = $this->columna('en_progreso');
        $tarea = Tarea::factory()->create(['columna_id' => $pendiente->id]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertOk();
        $this->patchJson(route('tareas.update', $tarea), [
            'titulo' => $tarea->titulo,
            'prioridad' => $tarea->prioridad->value,
            'estado' => 'en_progreso',
        ])->assertSuccessful();

        $tarea->refresh();
        $this->assertSame($enProgreso->id, $tarea->columna_id);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->estado);
    }

    public function test_cambiar_el_tipo_de_una_columna_a_completada_no_inventa_una_previa(): void
    {
        $revision = $this->revision();
        $tarea = Tarea::factory()->create(['columna_id' => $revision->id]);

        $this->patchJson(route('tablero.columnas.update', $revision), ['nombre' => 'Revisión', 'categoria' => 'completada'])->assertOk();

        $tarea->refresh();
        $this->assertSame(EstadoTarea::Completada, $tarea->estado);
        $this->assertNull($tarea->columna_previa_id);

        // Al reabrirse cae en la columna del estado pedido del tablero, no en una previa inexistente.
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'pendiente'])->assertOk();
        $this->assertSame(ColumnaTablero::sinAsignarDe()->id, $tarea->fresh()->columna_id);
    }
}
