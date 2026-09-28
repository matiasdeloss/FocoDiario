<?php

namespace Tests\Feature;

use App\Models\Contexto;
use App\Models\Nota;
use Database\Seeders\ContextoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_muestra_el_arbol(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera']);
        Contexto::factory()->create(['nombre' => 'Programación 2', 'contexto_padre_id' => $carrera->id]);

        $this->get(route('contextos.index'))
            ->assertOk()->assertSeeInOrder(['Carrera', 'Programación 2']);
    }

    public function test_se_crea_un_contexto_con_padre_opcional(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera']);

        $this->post(route('contextos.store'), [
            'nombre' => 'Programación 2', 'tipo' => 'materia', 'contexto_padre_id' => $carrera->id,
        ])->assertRedirect(route('contextos.index'));

        $this->assertDatabaseHas('contextos', ['nombre' => 'Programación 2', 'contexto_padre_id' => $carrera->id]);
    }

    public function test_el_nombre_es_unico_dentro_del_mismo_padre(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera']);
        Contexto::factory()->create(['nombre' => 'Física', 'contexto_padre_id' => $carrera->id]);

        $this->post(route('contextos.store'), ['nombre' => 'Física', 'tipo' => 'materia', 'contexto_padre_id' => $carrera->id])
            ->assertSessionHasErrors('nombre');

        // En otro padre se permite.
        $this->post(route('contextos.store'), ['nombre' => 'Física', 'tipo' => 'materia'])
            ->assertSessionHasNoErrors();
    }

    public function test_un_contexto_no_puede_ser_su_propio_padre(): void
    {
        $contexto = Contexto::factory()->create();

        $this->put(route('contextos.update', $contexto), [
            'nombre' => $contexto->nombre, 'tipo' => 'materia', 'contexto_padre_id' => $contexto->id,
        ])->assertSessionHasErrors('contexto_padre_id');

        $this->assertNull($contexto->fresh()->contexto_padre_id);
    }

    public function test_no_se_permiten_ciclos_en_la_jerarquia(): void
    {
        $a = Contexto::factory()->entorno()->create(['nombre' => 'A']);
        $b = Contexto::factory()->create(['nombre' => 'B', 'contexto_padre_id' => $a->id]);
        $c = Contexto::factory()->tema()->create(['nombre' => 'C', 'contexto_padre_id' => $b->id]);

        $this->put(route('contextos.update', $a), [
            'nombre' => 'A', 'tipo' => 'entorno', 'contexto_padre_id' => $c->id,
        ])->assertSessionHasErrors('contexto_padre_id');

        $this->assertNull($a->fresh()->contexto_padre_id);
    }

    public function test_borrar_un_contexto_deja_sus_notas_sin_destino(): void
    {
        $contexto = Contexto::factory()->create();
        $hijo = Contexto::factory()->tema()->create(['contexto_padre_id' => $contexto->id]);
        $nota = Nota::factory()->create(['contexto_id' => $contexto->id]);

        $this->delete(route('contextos.destroy', $contexto))->assertRedirect(route('contextos.index'));

        $this->assertDatabaseMissing('contextos', ['id' => $contexto->id]);
        $this->assertNull($nota->fresh()->contexto_id);
        $this->assertNull($hijo->fresh()->contexto_padre_id);
    }

    public function test_el_seeder_es_idempotente(): void
    {
        $this->seed(ContextoSeeder::class);
        $this->seed(ContextoSeeder::class);

        $this->assertDatabaseCount('contextos', 3);
    }
}
