<?php

namespace Tests\Feature;

use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Tarea;
use App\Support\ColoresDeContexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Una nota o tarea sin color propio se ve con el del contexto (o el del ancestro más cercano con color); solo al mostrar. */
class ColorHeredadoDelContextoTest extends TestCase
{
    use RefreshDatabase;

    private const TERRACOTA = '#c0663a';

    private const SALVIA = '#728a58';

    public function test_el_color_propio_gana_al_del_contexto(): void
    {
        $contexto = Contexto::factory()->create(['color' => self::TERRACOTA]);
        $tarea = Tarea::factory()->create(['contexto_id' => $contexto->id, 'color' => '#78802a']);

        $color = $tarea->colorVisible();
        $this->assertTrue($color->propio);
        $this->assertSame('var(--actividad-oliva-fondo)', $color->fondo());
        $this->get(route('tablero.index'))->assertOk()->assertSee('--tarjeta-fondo: var(--actividad-oliva-fondo)', false);
    }

    public function test_hereda_el_color_del_contexto(): void
    {
        $contexto = Contexto::factory()->create(['color' => self::TERRACOTA]);
        $tarea = Tarea::factory()->create(['contexto_id' => $contexto->id]);
        $nota = Nota::factory()->create(['contexto_id' => $contexto->id, 'color' => null]);

        foreach ([$tarea, $nota] as $item) {
            $color = $item->colorVisible();
            $this->assertFalse($color->propio);
            $this->assertSame('var(--actividad-terracota-fondo)', $color->fondo());
            $this->assertSame('var(--actividad-terracota-acento)', $color->marca());
        }

        $this->get(route('tablero.index'))->assertOk()->assertSee('--tarjeta-fondo: var(--actividad-terracota-fondo)', false)->assertSee('con-color', false);
        $this->get(route('notas.index'))->assertOk()->assertSee('--nota-fondo: var(--actividad-terracota-fondo)', false)->assertSee('nota-con-color', false);
    }

    public function test_hereda_del_ancestro_mas_cercano_con_color(): void
    {
        $entorno = Contexto::factory()->entorno()->create(['color' => self::SALVIA]);
        $materia = Contexto::factory()->create(['contexto_padre_id' => $entorno->id, 'color' => self::TERRACOTA]);
        $tema = Contexto::factory()->tema()->create(['contexto_padre_id' => $materia->id]);
        $subtema = Contexto::factory()->tema()->create(['contexto_padre_id' => $tema->id]);

        $this->assertSame('var(--actividad-terracota-fondo)', Tarea::factory()->create(['contexto_id' => $subtema->id])->colorVisible()->fondo());

        $materia->update(['color' => null]);
        $this->assertSame('var(--actividad-salvia-fondo)', Nota::factory()->create(['contexto_id' => $subtema->id])->colorVisible()->fondo());
    }

    public function test_sin_color_en_ningun_lado_queda_el_aspecto_por_defecto(): void
    {
        $contexto = Contexto::factory()->create(['color' => null]);
        $tarea = Tarea::factory()->create(['contexto_id' => $contexto->id]);
        Nota::factory()->create(['contexto_id' => $contexto->id, 'color' => null]);

        $this->assertNull($tarea->colorVisible());
        $this->assertNull(Tarea::factory()->create(['contexto_id' => null])->colorVisible());
        $this->get(route('tablero.index'))->assertOk()->assertDontSee('--tarjeta-fondo', false);
        $this->get(route('notas.index'))->assertOk()->assertDontSee('nota-con-color', false);
    }

    public function test_cambiar_el_color_del_contexto_cambia_lo_que_no_tiene_color_propio(): void
    {
        $contexto = Contexto::factory()->create(['color' => self::TERRACOTA]);
        $tarea = Tarea::factory()->create(['contexto_id' => $contexto->id]);

        $this->get(route('tablero.index'))->assertSee('--tarjeta-fondo: var(--actividad-terracota-fondo)', false);

        $contexto->update(['color' => self::SALVIA]);

        $this->get(route('tablero.index'))->assertSee('--tarjeta-fondo: var(--actividad-salvia-fondo)', false)
            ->assertDontSee('--tarjeta-fondo: var(--actividad-terracota-fondo)', false);
        // Es solo de presentación: el color propio de la tarea sigue vacío.
        $this->assertNull($tarea->fresh()->color);
    }

    public function test_el_selector_de_color_sigue_mostrando_solo_el_propio(): void
    {
        $contexto = Contexto::factory()->create(['color' => self::TERRACOTA]);
        $tarea = Tarea::factory()->create(['contexto_id' => $contexto->id]);
        $nota = Nota::factory()->create(['contexto_id' => $contexto->id, 'color' => null]);

        $this->assertNull($tarea->datosModal()['color']);
        $this->get(route('notas.edit', $nota))->assertOk()->assertSee('name="color" value="" checked', false)->assertDontSee('value="terracota" checked', false);
    }

    public function test_un_ciclo_en_los_padres_no_cuelga_la_resolucion(): void
    {
        $a = Contexto::factory()->create(['color' => null]);
        $b = Contexto::factory()->create(['color' => null, 'contexto_padre_id' => $a->id]);
        DB::table('contextos')->where('id', $a->id)->update(['contexto_padre_id' => $b->id]);

        $this->assertNull(ColoresDeContexto::delUsuario()->para($a->id));
    }

    public function test_el_listado_no_consulta_los_contextos_por_cada_fila(): void
    {
        $padre = Contexto::factory()->entorno()->create(['color' => self::SALVIA]);
        foreach (range(1, 8) as $i) {
            $hijo = Contexto::factory()->tema()->create(['contexto_padre_id' => $padre->id]);
            Tarea::factory()->create(['contexto_id' => $hijo->id]);
            Nota::factory()->create(['contexto_id' => $hijo->id, 'color' => null]);
        }

        foreach (['tablero.index', 'notas.index'] as $ruta) {
            DB::enableQueryLog();
            $this->get(route($ruta))->assertOk();
            $contextos = collect(DB::getQueryLog())->pluck('query')
                ->filter(fn ($q) => str_contains($q, 'from "contextos"') || str_contains($q, 'from `contextos`'));
            DB::flushQueryLog();

            // Un puñado de consultas fijas (colores, eager load, opciones), nunca una por tarea o nota.
            $this->assertLessThanOrEqual(6, $contextos->count(), $ruta.': '.$contextos->implode(' | '));
        }
    }
}
