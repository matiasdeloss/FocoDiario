<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Caja;
use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nadie ve ni toca lo de otro usuario: ni por URL, ni en listados, ni referenciándolo en un formulario. */
class AislamientoUsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $conSesion = false;

    private User $ana;

    private User $beto;

    /** @var array<string, mixed> Datos de Ana. */
    private array $deAna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana = User::factory()->create();
        $this->beto = User::factory()->create();

        $this->actingAs($this->ana);
        $contexto = Contexto::factory()->create(['nombre' => 'Materia de Ana']);
        $tarea = Tarea::factory()->create(['titulo' => 'Tarea de Ana', 'fecha_limite' => today()]);
        $this->deAna = [
            'contexto' => $contexto,
            'tarea' => $tarea,
            'nota' => Nota::factory()->create(['titulo' => 'Nota de Ana', 'contexto_id' => $contexto->id, 'fecha' => today()]),
            'recordatorio' => Recordatorio::factory()->create(['mensaje' => 'Recordatorio de Ana', 'tarea_id' => $tarea->id, 'recordar_en' => now()->subMinute()]),
            'caja' => Caja::factory()->create(['titulo' => 'Caja de Ana', 'fecha' => today()]),
            'columna' => ColumnaTablero::create(['nombre' => 'Columna de Ana', 'categoria' => 'en_progreso', 'posicion' => 5]),
            'sesion' => SesionEstudio::factory()->create(['tema' => 'Sesión de Ana', 'iniciada_en' => now()->subHour()]),
        ];
        Ajuste::guardar(Ajuste::TITULO_PLANNER, 'Planner de Ana');

        $this->actingAs($this->beto);
    }

    public function test_no_se_puede_ver_editar_ni_borrar_lo_ajeno_por_url(): void
    {
        ['nota' => $nota, 'tarea' => $tarea, 'recordatorio' => $recordatorio, 'caja' => $caja,
            'columna' => $columna, 'contexto' => $contexto, 'sesion' => $sesion] = $this->deAna;

        $this->get(route('notas.edit', $nota))->assertNotFound();
        $this->putJson(route('notas.update', $nota), ['contenido' => 'hackeada'])->assertNotFound();
        $this->delete(route('notas.destroy', $nota))->assertNotFound();
        $this->patch(route('notas.fijar', $nota))->assertNotFound();

        $this->get(route('tareas.edit', $tarea))->assertNotFound();
        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'completada'])->assertNotFound();
        $this->delete(route('tareas.destroy', $tarea))->assertNotFound();

        $this->patchJson(route('recordatorios.avisar', $recordatorio))->assertNotFound();
        $this->patchJson(route('recordatorios.reactivar', $recordatorio))->assertNotFound();
        $this->patchJson(route('recordatorios.posponer', $recordatorio))->assertNotFound();

        $this->patchJson(route('agenda.cajas.update', $caja), ['titulo' => 'x'])->assertNotFound();
        $this->deleteJson(route('agenda.cajas.destroy', $caja))->assertNotFound();

        $this->patchJson(route('tablero.columnas.update', $columna), ['nombre' => 'x'])->assertNotFound();
        $this->get(route('contextos.edit', $contexto))->assertNotFound();
        $this->delete(route('estudio.sesiones.destroy', $sesion))->assertNotFound();

        $this->getJson(route('calendario.detalle', ['tipo' => 'nota', 'id' => $nota->id]))->assertNotFound();
        $this->getJson(route('calendario.detalle', ['tipo' => 'sesion', 'id' => $sesion->id]))->assertNotFound();
        $this->patchJson(route('calendario.tarjetas.update', ['tipo' => 'tarea', 'id' => $tarea->id]), ['titulo' => 'x'])->assertNotFound();

        // Nada cambió.
        $this->assertSame('Nota de Ana', Nota::withoutGlobalScopes()->find($nota->id)->titulo);
        $this->assertNotNull(Tarea::withoutGlobalScopes()->find($tarea->id));
        $this->assertNotNull(Caja::withoutGlobalScopes()->find($caja->id));
    }

    public function test_los_listados_solo_muestran_lo_propio(): void
    {
        foreach ([route('hoy'), route('notas.index'), route('tareas.index'), route('tablero.index'), route('contextos.index'),
            route('estudio.index'), route('estudio.historial'), route('agenda.dia', ['fecha' => today()->toDateString()])] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('de Ana');
        }

        $eventos = $this->getJson(route('calendario.eventos', ['start' => today()->subDay()->toDateString(), 'end' => today()->addDays(2)->toDateString()]))->json();
        $this->assertSame([], $eventos);

        $this->getJson(route('recordatorios.vencidos'))->assertJsonCount(0, 'recordatorios');
        $this->assertSame('Planner semanal', Ajuste::tituloPlanner());
    }

    public function test_no_se_puede_apuntar_a_datos_ajenos_desde_un_formulario(): void
    {
        ['contexto' => $contexto, 'tarea' => $tarea, 'columna' => $columna, 'caja' => $caja] = $this->deAna;

        $this->postJson(route('notas.store'), ['contenido' => 'x', 'contexto_id' => $contexto->id])->assertJsonValidationErrors('contexto_id');
        $this->postJson(route('tareas.store'), ['titulo' => 'x', 'prioridad' => 'media', 'columna_id' => $columna->id])->assertJsonValidationErrors('columna_id');
        $this->postJson(route('recordatorios.store'), ['mensaje' => 'x', 'tarea_id' => $tarea->id])->assertJsonValidationErrors('tarea_id');
        $this->postJson(route('estudio.sesiones.store'), [
            'tarea_id' => $tarea->id, 'contexto_id' => $contexto->id, 'estilo' => 'clasico',
            'foco_seg' => 1500, 'descanso_seg' => 300, 'descanso_largo_seg' => 900, 'pomodoros_antes_largo' => 4,
        ])->assertJsonValidationErrors(['tarea_id', 'contexto_id']);
        $this->postJson(route('agenda.cajas.store'), ['fecha' => today()->toDateString(), 'tipo' => 'texto', 'contexto_id' => $contexto->id])
            ->assertJsonValidationErrors('contexto_id');
        $this->patchJson(route('agenda.dia.layout', ['fecha' => today()->toDateString()]), [
            'cajas' => [['id' => $caja->id, 'x' => 6, 'y' => 0, 'ancho' => 6, 'alto' => 4]],
        ])->assertJsonValidationErrors('cajas.0.id');

        $propia = Tarea::factory()->create();
        $this->patchJson(route('tareas.columna', $propia), ['columna_id' => $columna->id])->assertJsonValidationErrors('columna_id');

        $this->assertSame(0, Caja::withoutGlobalScopes()->find($caja->id)->x);
    }

    public function test_los_nombres_unicos_son_por_usuario(): void
    {
        // Beto puede tener su propio contexto con el mismo nombre que Ana.
        $this->post(route('contextos.store'), ['nombre' => 'Materia de Ana', 'tipo' => 'materia'])->assertSessionHasNoErrors();

        $this->assertSame(2, Contexto::withoutGlobalScopes()->where('nombre', 'Materia de Ana')->count());
    }

    public function test_cada_usuario_tiene_sus_propias_columnas(): void
    {
        $this->assertSame(['Sin asignar', 'Pendiente', 'En progreso', 'Completada'], ColumnaTablero::ordenadas()->pluck('nombre')->all());

        // Una tarea nueva de Beto cae en SU columna "Sin asignar".
        $tarea = Tarea::factory()->create();
        $this->assertSame($this->beto->id, ColumnaTablero::withoutGlobalScopes()->find($tarea->columna_id)->user_id);
    }
}
