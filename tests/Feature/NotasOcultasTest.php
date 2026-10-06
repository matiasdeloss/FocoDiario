<?php

namespace Tests\Feature;

use App\Models\Contexto;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Ocultar una nota la saca solo de la pantalla Notas: en el resto de la app sigue y no se borra nada. */
class NotasOcultasTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Endpoints ----------

    public function test_ocultar_y_mostrar_responden_json_y_cambian_solo_la_marca(): void
    {
        $nota = Nota::factory()->create(['titulo' => 'Apunte']);

        $this->patchJson(route('notas.ocultar', $nota))->assertOk()->assertExactJson(['ok' => true, 'oculta' => true]);
        $this->assertTrue($nota->fresh()->oculta);

        $this->patchJson(route('notas.mostrar', $nota))->assertOk()->assertExactJson(['ok' => true, 'oculta' => false]);
        $this->assertFalse($nota->fresh()->oculta);
        $this->assertDatabaseHas('notas', ['id' => $nota->id, 'titulo' => 'Apunte']);
    }

    public function test_sin_js_ocultar_vuelve_a_la_pantalla_con_el_aviso_en_la_sesion(): void
    {
        $nota = Nota::factory()->create();

        $this->from(route('notas.index'))->patch(route('notas.ocultar', $nota))
            ->assertRedirect(route('notas.index'))
            ->assertSessionHas('estado', 'Nota oculta.');

        $this->from(route('notas.index'))->patch(route('notas.mostrar', $nota))
            ->assertSessionHas('estado', 'Nota visible de nuevo.');
    }

    public function test_no_se_puede_ocultar_ni_mostrar_la_nota_de_otro_usuario(): void
    {
        $ajena = Nota::factory()->create(['oculta' => false]);
        $this->actingAs(User::factory()->create());

        $this->patchJson(route('notas.ocultar', $ajena))->assertNotFound();
        $this->patchJson(route('notas.mostrar', $ajena))->assertNotFound();
        $this->assertFalse($ajena->fresh()->oculta);
    }

    // ---------- Pantalla Notas ----------

    public function test_el_listado_por_defecto_no_incluye_las_ocultas(): void
    {
        Nota::factory()->create(['titulo' => 'Se ve']);
        Nota::factory()->create(['titulo' => 'Escondida', 'oculta' => true]);

        $respuesta = $this->get(route('notas.index'))->assertOk();

        $this->assertSame(['Se ve'], $respuesta->viewData('notas')->pluck('titulo')->all());
        $respuesta->assertSee('Se ve')->assertDontSee('Escondida');
    }

    public function test_ver_ocultas_lista_solo_las_ocultas_y_muestra_el_conteo(): void
    {
        Nota::factory()->create(['titulo' => 'Se ve']);
        $a = Nota::factory()->create(['titulo' => 'Escondida A', 'oculta' => true]);
        Nota::factory()->create(['titulo' => 'Escondida B', 'oculta' => true]);

        $this->get(route('notas.index'))->assertSee('Ver ocultas (<span data-conteo-ocultas>2</span>)', false);

        $respuesta = $this->get(route('notas.index', ['ocultas' => 1]))->assertOk();

        $this->assertEqualsCanonicalizing(['Escondida A', 'Escondida B'], $respuesta->viewData('notas')->pluck('titulo')->all());
        $respuesta->assertDontSee('Se ve')
            // Cada tarjeta ofrece "Mostrar nota" (ojo abierto) y apunta al endpoint de mostrar.
            ->assertSee('aria-label="Mostrar nota"', false)->assertSee('bi-eye"', false)
            ->assertSee('action="'.route('notas.mostrar', $a).'"', false);
    }

    public function test_la_pastilla_ver_ocultas_esta_escondida_si_no_hay_ocultas(): void
    {
        Nota::factory()->create();

        $html = $this->get(route('notas.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-pastilla-ocultas\s+hidden\s*>/', $html);

        Nota::factory()->create(['oculta' => true]);

        $html = $this->get(route('notas.index'))->getContent();
        $this->assertDoesNotMatchRegularExpression('/data-pastilla-ocultas[^>]*\shidden/', $html);
    }

    public function test_cada_tarjeta_visible_trae_el_boton_ocultar_accesible(): void
    {
        $nota = Nota::factory()->create();

        $this->get(route('notas.index'))
            ->assertSee('aria-label="Ocultar nota"', false)->assertSee('title="Ocultar"', false)->assertSee('bi-eye-slash', false)
            ->assertSee('action="'.route('notas.ocultar', $nota).'"', false)
            ->assertSee('data-url-inversa="'.route('notas.mostrar', $nota).'"', false);
    }

    public function test_los_filtros_siguen_funcionando_combinados_con_ocultas(): void
    {
        $contexto = Contexto::factory()->create();
        Nota::factory()->create(['titulo' => 'Redes visible', 'contexto_id' => $contexto->id]);
        Nota::factory()->create(['titulo' => 'Redes oculta', 'contexto_id' => $contexto->id, 'oculta' => true]);
        Nota::factory()->create(['titulo' => 'Otra oculta', 'oculta' => true]);

        $this->get(route('notas.index', ['q' => 'Redes']))->assertSee('Redes visible')->assertDontSee('Redes oculta');

        $titulos = $this->get(route('notas.index', ['ocultas' => 1, 'q' => 'Redes', 'contexto' => $contexto->id]))
            ->viewData('notas')->pluck('titulo')->all();
        $this->assertSame(['Redes oculta'], $titulos);
    }

    public function test_con_un_contexto_elegido_avisa_cuantas_ocultas_quedan_fuera_con_enlace_para_verlas(): void
    {
        $contexto = Contexto::factory()->create();
        Nota::factory()->create(['contexto_id' => $contexto->id, 'titulo' => 'Visible']);
        Nota::factory()->count(2)->create(['contexto_id' => $contexto->id, 'oculta' => true]);
        Nota::factory()->create(['oculta' => true]); // oculta de otro contexto: no cuenta

        $enlace = route('notas.index', ['contexto' => $contexto->id, 'ocultas' => 1]);

        $this->get(route('notas.index', ['contexto' => $contexto->id]))->assertOk()
            ->assertSee('data-ocultas-aviso', false)
            ->assertSee('<span data-ocultas-n>2</span> <span data-ocultas-etiqueta>ocultas</span>', false)
            ->assertSee('href="'.e($enlace).'"', false);

        // Singular, y el conteo respeta los demás filtros (texto).
        Nota::factory()->create(['contexto_id' => $contexto->id, 'oculta' => true, 'titulo' => 'Redes']);
        $this->get(route('notas.index', ['contexto' => $contexto->id, 'q' => 'Redes']))
            ->assertSee('<span data-ocultas-n>1</span> <span data-ocultas-etiqueta>oculta</span>', false);
    }

    public function test_el_aviso_de_ocultas_no_aparece_con_cero_sin_contexto_ni_viendo_ocultas(): void
    {
        $contexto = Contexto::factory()->create();
        Nota::factory()->create(['contexto_id' => $contexto->id]);

        // Contexto sin ocultas: la línea existe para el JS pero va oculta.
        $html = $this->get(route('notas.index', ['contexto' => $contexto->id]))->getContent();
        $this->assertMatchesRegularExpression('/data-ocultas-aviso\s+hidden/', $html);

        // Sin filtro de contexto, aunque haya ocultas.
        Nota::factory()->create(['contexto_id' => $contexto->id, 'oculta' => true]);
        $this->get(route('notas.index'))->assertDontSee('data-ocultas-aviso', false);

        // Viendo las ocultas.
        $this->get(route('notas.index', ['contexto' => $contexto->id, 'ocultas' => 1]))->assertDontSee('data-ocultas-aviso', false);
    }

    public function test_una_nota_fijada_tambien_se_puede_ocultar_y_la_oculta_gana(): void
    {
        $nota = Nota::factory()->create(['titulo' => 'Fijada y oculta', 'fijada' => true]);

        $this->patchJson(route('notas.ocultar', $nota))->assertOk();

        $this->get(route('notas.index'))->assertDontSee('Fijada y oculta');
        $this->get(route('notas.index', ['fijadas' => 1]))->assertDontSee('Fijada y oculta');
        $this->assertTrue($nota->fresh()->fijada);
    }

    // ---------- El resto de la app no cambia ----------

    public function test_la_vista_de_contextos_sigue_contando_las_notas_ocultas(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Bases de Datos']);
        Nota::factory()->create(['contexto_id' => $contexto->id]);
        Nota::factory()->create(['contexto_id' => $contexto->id, 'oculta' => true]);

        $this->get(route('contextos.index'))->assertOk()->assertSee('2 notas');
        $this->assertSame(2, $contexto->notas()->count());
    }

    public function test_el_tablero_sigue_mostrando_las_notas_ocultas(): void
    {
        Nota::factory()->create(['titulo' => 'Visible en tablero']);
        Nota::factory()->create(['titulo' => 'Oculta en tablero', 'oculta' => true]);

        $respuesta = $this->get(route('tablero.index'))->assertOk();
        $titulos = collect($respuesta->viewData('columnas'))->flatMap(fn ($c) => $c['tarjetas'])
            ->map(fn ($t) => $t->tituloVisible())->all();

        $this->assertEqualsCanonicalizing(['Visible en tablero', 'Oculta en tablero'], $titulos);
        $respuesta->assertSee('Oculta en tablero');
    }

    public function test_la_busqueda_para_vincular_notas_a_una_tarea_sigue_encontrando_las_ocultas(): void
    {
        Nota::factory()->create(['titulo' => 'Redes oculta', 'oculta' => true]);

        $titulos = collect($this->getJson(route('notas.buscar', ['q' => 'Redes']))->assertOk()->json('notas'))->pluck('titulo')->all();

        $this->assertSame(['Redes oculta'], $titulos);
    }

    // ---------- Migración ----------

    public function test_las_notas_existentes_quedan_visibles_por_defecto(): void
    {
        $nota = Nota::factory()->create();
        $this->assertFalse($nota->fresh()->oculta);

        // Sin indicar la columna, la base la deja en false.
        $id = DB::table('notas')->insertGetId(['user_id' => $nota->user_id, 'contenido' => 'Vieja', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertEquals(0, DB::table('notas')->where('id', $id)->value('oculta'));
        $this->assertTrue(Schema::hasColumn('notas', 'oculta'));
    }

    public function test_la_migracion_se_puede_revertir(): void
    {
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_10_14_000001_agregar_oculta_a_notas.php'])->assertExitCode(0);
        $this->assertFalse(Schema::hasColumn('notas', 'oculta'));

        $this->artisan('migrate')->assertExitCode(0);
        $this->assertTrue(Schema::hasColumn('notas', 'oculta'));
    }
}
