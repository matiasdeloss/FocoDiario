<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableroTareasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_tablero_responde_y_agrupa_por_estado(): void
    {
        Tarea::factory()->create(['titulo' => 'Tarea pendiente', 'estado' => EstadoTarea::Pendiente]);
        Tarea::factory()->create(['titulo' => 'Tarea en curso', 'estado' => EstadoTarea::EnProgreso]);
        Tarea::factory()->create(['titulo' => 'Tarea lista', 'estado' => EstadoTarea::Completada]);

        $respuesta = $this->get(route('tareas.index', ['vista' => 'tablero']))->assertOk();

        $respuesta->assertViewIs('tareas.index')->assertViewHas('vista', 'tablero');
        $columnas = $respuesta->viewData('columnas');
        $this->assertCount(3, $columnas);
        $this->assertSame(['Tarea pendiente'], $columnas[0]['tareas']->pluck('titulo')->all());
        $this->assertSame(['Tarea en curso'], $columnas[1]['tareas']->pluck('titulo')->all());
        $this->assertSame(['Tarea lista'], $columnas[2]['tareas']->pluck('titulo')->all());
        $respuesta->assertDontSee('id="filtro-estado"', false);
    }

    public function test_el_tablero_respeta_el_filtro_por_proyecto(): void
    {
        Tarea::factory()->create(['titulo' => 'De tesis', 'proyecto' => 'Tesis']);
        Tarea::factory()->create(['titulo' => 'De casa', 'proyecto' => 'Casa']);

        $this->get(route('tareas.index', ['vista' => 'tablero', 'proyecto' => 'Tesis']))
            ->assertOk()->assertSee('De tesis')->assertDontSee('De casa');
    }

    public function test_las_completadas_se_limitan_a_las_diez_mas_recientes(): void
    {
        foreach (range(1, 12) as $i) {
            Tarea::factory()->create([
                'titulo' => "Hecha {$i}",
                'estado' => EstadoTarea::Completada,
                'updated_at' => now()->subDays(20 - $i),
            ]);
        }

        $respuesta = $this->get(route('tareas.index', ['vista' => 'tablero']))->assertOk();
        $completadas = $respuesta->viewData('columnas')[2];

        $this->assertCount(10, $completadas['tareas']);
        $this->assertSame(2, $completadas['ocultas']);
        $respuesta->assertSee('Hecha 12')->assertDontSee('Hecha 1<', false)->assertDontSee('Hecha 2<', false)
            ->assertSee(route('tareas.index', ['estado' => 'completada']), false);
    }

    public function test_el_estado_se_cambia_desde_el_tablero_con_json_y_valida(): void
    {
        $tarea = Tarea::factory()->create(['estado' => EstadoTarea::Pendiente]);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'en_progreso'])
            ->assertOk()->assertJson(['estado' => 'en_progreso']);
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);

        $this->patchJson(route('tareas.estado', $tarea), ['estado' => 'inventado'])
            ->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->assertSame(EstadoTarea::EnProgreso, $tarea->fresh()->estado);
    }

    public function test_la_vista_lista_sigue_igual_y_rechaza_vistas_invalidas(): void
    {
        Tarea::factory()->create(['titulo' => 'En la lista']);

        $this->get(route('tareas.index'))->assertOk()->assertSee('En la lista')
            ->assertSee('id="filtro-estado"', false)->assertViewHas('vista', 'lista');
        $this->get(route('tareas.index', ['vista' => 'otra']))->assertSessionHasErrors('vista');
    }
}
