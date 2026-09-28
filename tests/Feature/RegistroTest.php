<?php

namespace Tests\Feature;

use App\Enums\TipoCategoria;
use App\Models\BloqueTiempo;
use App\Models\Categoria;
use App\Services\ResumenRegistro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistroTest extends TestCase
{
    use RefreshDatabase;

    private function categoria(string $nombre, TipoCategoria $tipo): Categoria
    {
        return Categoria::create(['nombre' => $nombre, 'tipo' => $tipo]);
    }

    public function test_la_pantalla_carga_con_el_dia_de_hoy_por_defecto(): void
    {
        $this->get(route('registro.index'))->assertOk()->assertSee('Registro del día');
    }

    public function test_se_registra_un_bloque_manual(): void
    {
        $estudio = $this->categoria('Estudio', TipoCategoria::Productiva);

        $this->post(route('registro.store'), [
            'fecha' => '2026-09-20',
            'categoria_id' => $estudio->id,
            'inicio' => '09:00',
            'fin' => '10:30',
            'concentracion' => 4,
        ])->assertRedirect(route('registro.index', ['fecha' => '2026-09-20']));

        $bloque = BloqueTiempo::firstOrFail();
        $this->assertSame('2026-09-20 09:00', $bloque->inicio->format('Y-m-d H:i'));
        $this->assertSame(90, $bloque->duracionEnMinutos());
        $this->assertSame('manual', $bloque->origen->value);
    }

    public function test_el_fin_debe_ser_posterior_al_inicio(): void
    {
        $estudio = $this->categoria('Estudio', TipoCategoria::Productiva);

        $this->post(route('registro.store'), [
            'fecha' => '2026-09-20',
            'categoria_id' => $estudio->id,
            'inicio' => '10:00',
            'fin' => '09:00',
        ])->assertSessionHasErrors('fin');

        $this->assertSame(
            'La hora de fin tiene que ser posterior a la de inicio.',
            session('errors')->first('fin'),
        );
        $this->assertDatabaseCount('bloques_tiempo', 0);
    }

    public function test_valida_concentracion_y_categoria(): void
    {
        $this->post(route('registro.store'), [
            'fecha' => '2026-09-20',
            'categoria_id' => 999,
            'inicio' => '09:00',
            'fin' => '10:00',
            'concentracion' => 9,
        ])->assertSessionHasErrors(['categoria_id', 'concentracion']);
    }

    public function test_el_resumen_suma_horas_por_tipo_de_categoria(): void
    {
        $estudio = $this->categoria('Estudio', TipoCategoria::Productiva);
        $juegos = $this->categoria('Juegos', TipoCategoria::Ocio);
        $siesta = $this->categoria('Siesta', TipoCategoria::Descanso);

        $crear = fn (Categoria $c, string $inicio, string $fin) => BloqueTiempo::create([
            'categoria_id' => $c->id, 'inicio' => $inicio, 'fin' => $fin,
        ]);
        $crear($estudio, '2026-09-20 09:00', '2026-09-20 11:00');
        $crear($estudio, '2026-09-20 14:00', '2026-09-20 14:30');
        $crear($juegos, '2026-09-20 20:00', '2026-09-20 21:00');
        $crear($siesta, '2026-09-20 15:00', '2026-09-20 15:20');
        $crear($estudio, '2026-09-21 09:00', '2026-09-21 12:00'); // otro día, no cuenta

        $bloques = BloqueTiempo::with('categoria')->whereDate('inicio', '2026-09-20')->get();
        $resumen = ResumenRegistro::deBloques($bloques);

        $this->assertSame(['total' => 230, 'productiva' => 150, 'ocio' => 60, 'descanso' => 20], $resumen);

        $this->get(route('registro.index', ['fecha' => '2026-09-20']))
            ->assertOk()
            ->assertSee('3 h 50 min')
            ->assertSee('2 h 30 min');
    }

    public function test_se_puede_editar_y_borrar_un_bloque(): void
    {
        $estudio = $this->categoria('Estudio', TipoCategoria::Productiva);
        $bloque = BloqueTiempo::factory()->create([
            'categoria_id' => $estudio->id, 'inicio' => '2026-09-20 09:00', 'fin' => '2026-09-20 10:00',
        ]);

        $this->get(route('registro.edit', $bloque))->assertOk();

        $this->put(route('registro.update', $bloque), [
            'fecha' => '2026-09-20', 'categoria_id' => $estudio->id, 'inicio' => '09:00', 'fin' => '09:45',
        ])->assertRedirect();
        $this->assertSame(45, $bloque->fresh()->duracionEnMinutos());

        $this->delete(route('registro.destroy', $bloque))->assertRedirect(route('registro.index', ['fecha' => '2026-09-20']));
        $this->assertDatabaseCount('bloques_tiempo', 0);
    }

    public function test_pantalla_hoy_sigue_funcionando(): void
    {
        $this->get(route('hoy'))->assertOk();
    }
}
