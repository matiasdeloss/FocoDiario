<?php

namespace Tests\Feature;

use App\Models\Contexto;
use App\Models\Nota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_tarjeta_abre_la_nota_completa_en_un_modal_de_lectura(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Bases de Datos', 'contexto_padre_id' => null]);
        $largo = str_repeat('Normalizar hasta 3FN. ', 40).'FINAL DEL TEXTO';
        $nota = Nota::factory()->create([
            'titulo' => 'Apuntes de normalización',
            'contenido' => $largo,
            'contexto_id' => $contexto->id,
            'fecha' => '2026-10-02',
            'fijada' => true,
        ]);

        $respuesta = $this->get(route('notas.index'))->assertOk();

        // El modal de lectura está en la página.
        $respuesta->assertSee('id="dialogo-ver-nota"', false)->assertSee('data-editar-desde-ver', false);

        // El enlace lleva a la nota (sin JS) y trae la nota completa, sin recortar, para el modal.
        $html = $respuesta->getContent();
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('notas.show', $nota), '#').'" class="nota-abrir" data-ver-nota="([^"]+)"#', $html);
        preg_match('#class="nota-abrir" data-ver-nota="([^"]+)"#', $html, $coincidencia);
        $datos = json_decode(html_entity_decode($coincidencia[1]), true);

        $this->assertSame('Apuntes de normalización', $datos['titulo']);
        $this->assertSame($largo, $datos['contenido']);
        $this->assertStringEndsWith('FINAL DEL TEXTO', $datos['contenido']);
        $this->assertSame('Bases de Datos', $datos['destino']);
        $this->assertSame('2 de octubre de 2026', $datos['fecha']);
        $this->assertTrue($datos['fijada']);
        $respuesta->assertSee('aria-label="Ver nota: Apuntes de normalización"', false);
    }

    public function test_la_nota_sin_destino_ni_titulo_tambien_se_puede_abrir(): void
    {
        Nota::factory()->create(['titulo' => null, 'contenido' => "Línea uno\nLínea dos", 'contexto_id' => null, 'fecha' => null]);

        preg_match('#data-ver-nota="([^"]+)"#', $this->get(route('notas.index'))->getContent(), $coincidencia);
        $datos = json_decode(html_entity_decode($coincidencia[1]), true);

        $this->assertNull($datos['titulo']);
        $this->assertNull($datos['destino']);
        $this->assertNull($datos['fecha']);
        $this->assertSame("Línea uno\nLínea dos", $datos['contenido']);
    }

    public function test_la_pantalla_hoy_muestra_la_nota_rapida(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera']);
        $prog = Contexto::factory()->create(['nombre' => 'Programación 2', 'contexto_padre_id' => $carrera->id]);

        $this->get(route('hoy'))
            ->assertOk()
            ->assertSee('Nota rápida')
            ->assertSee('Bandeja de entrada')
            ->assertSee('Carrera › Programación 2');
    }

    public function test_se_crea_una_nota_rapida_con_htmx_y_devuelve_el_formulario_limpio(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Álgebra']);

        $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('notas.store'), [
                'contenido' => 'Repasar matrices',
                'contexto_id' => $contexto->id,
                'fecha' => '2026-10-10',
                'origen' => 'hoy',
            ])
            ->assertOk()
            ->assertAvisoHtmx('Nota guardada en Álgebra.')
            ->assertDontSee('Repasar matrices');

        $this->assertDatabaseHas('notas', ['contenido' => 'Repasar matrices', 'contexto_id' => $contexto->id]);
    }

    public function test_una_nota_rapida_sin_destino_va_a_la_bandeja_de_entrada(): void
    {
        $this->post(route('notas.store'), ['contenido' => 'Comprar pilas', 'origen' => 'hoy'])
            ->assertRedirect(route('hoy'));

        $nota = Nota::firstOrFail();
        $this->assertNull($nota->contexto_id);
        $this->assertTrue(Nota::sinContexto()->whereKey($nota->id)->exists());
    }

    public function test_la_nota_rapida_vacia_devuelve_el_error_en_espanol_con_htmx(): void
    {
        $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('notas.store'), ['contenido' => '', 'origen' => 'hoy'])
            ->assertOk()
            ->assertSee('Escribí la nota antes de guardar.');

        $this->assertDatabaseCount('notas', 0);
    }

    public function test_la_bandeja_de_entrada_solo_lista_notas_sin_destino(): void
    {
        $contexto = Contexto::factory()->create();
        Nota::factory()->create(['contenido' => 'Suelta']);
        Nota::factory()->create(['contenido' => 'Clasificada', 'contexto_id' => $contexto->id]);

        $this->get(route('notas.index', ['contexto' => 'bandeja']))
            ->assertOk()->assertSee('Suelta')->assertDontSee('Clasificada');
    }

    public function test_el_filtro_por_contexto_incluye_los_descendientes(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera']);
        $materia = Contexto::factory()->create(['nombre' => 'Programación 2', 'contexto_padre_id' => $carrera->id]);
        $tema = Contexto::factory()->tema()->create(['nombre' => 'Punteros', 'contexto_padre_id' => $materia->id]);
        $otro = Contexto::factory()->entorno()->create(['nombre' => 'Vida']);

        Nota::factory()->create(['contenido' => 'Nota de tema', 'contexto_id' => $tema->id]);
        Nota::factory()->create(['contenido' => 'Nota de materia', 'contexto_id' => $materia->id]);
        Nota::factory()->create(['contenido' => 'Nota de otro', 'contexto_id' => $otro->id]);
        Nota::factory()->create(['contenido' => 'Nota suelta']);

        $this->get(route('notas.index', ['contexto' => $carrera->id]))
            ->assertOk()
            ->assertSee('Nota de tema')->assertSee('Nota de materia')
            ->assertDontSee('Nota de otro')->assertDontSee('Nota suelta');

        $this->get(route('notas.index', ['contexto' => $materia->id]))
            ->assertSee('Nota de tema')->assertDontSee('Nota de otro');

        $this->assertSame('Carrera › Programación 2 › Punteros', $tema->rutaCompleta());
    }

    public function test_se_puede_editar_fijar_mover_y_borrar_una_nota(): void
    {
        $contexto = Contexto::factory()->create();
        $nota = Nota::factory()->create(['contenido' => 'Original']);

        $this->put(route('notas.update', $nota), ['contenido' => 'Editada', 'fijada' => '0'])
            ->assertRedirect(route('notas.index'));
        $this->assertDatabaseHas('notas', ['id' => $nota->id, 'contenido' => 'Editada']);

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('notas.fijar', $nota))->assertOk()->assertSee('Fijada');
        $this->assertTrue($nota->fresh()->fijada);

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('notas.mover', $nota), ['contexto_id' => $contexto->id])->assertOk();
        $this->assertSame($contexto->id, $nota->fresh()->contexto_id);

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('notas.mover', $nota), ['contexto_id' => ''])->assertOk();
        $this->assertNull($nota->fresh()->contexto_id);

        $this->withHeaders(['HX-Request' => 'true'])->delete(route('notas.destroy', $nota))->assertOk();
        $this->assertDatabaseMissing('notas', ['id' => $nota->id]);
    }

    public function test_se_filtran_las_notas_por_busqueda_color_y_fijadas(): void
    {
        Nota::factory()->create(['titulo' => 'Vectores', 'contenido' => 'Producto escalar', 'color' => '#728a58', 'fijada' => true]);
        Nota::factory()->create(['titulo' => 'Compras', 'contenido' => 'Leche', 'color' => '#c0677a', 'fijada' => false]);

        $this->get(route('notas.index', ['q' => 'escalar']))->assertOk()->assertSee('Vectores')->assertDontSee('Compras');
        $this->get(route('notas.index', ['color' => '#c0677a']))->assertOk()->assertSee('Compras')->assertDontSee('Vectores');
        $this->get(route('notas.index', ['fijadas' => 1]))->assertOk()->assertSee('Vectores')->assertDontSee('Compras');
        $this->get(route('notas.index', ['q' => 'nada']))->assertOk()->assertSee('Ninguna nota coincide');
        $this->get(route('notas.index', ['color' => 'violeta']))->assertSessionHasErrors('color');
    }

    public function test_el_modal_guarda_y_edita_notas_con_json_y_devuelve_errores_422(): void
    {
        $this->postJson(route('notas.store'), ['titulo' => '', 'contenido' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('contenido');

        $this->postJson(route('notas.store'), ['titulo' => 'Idea', 'contenido' => 'Texto', 'color' => '#78802a', 'fijada' => '1'])
            ->assertOk()->assertJson(['ok' => true]);
        $nota = Nota::firstOrFail();
        $this->assertTrue($nota->fijada);

        $this->putJson(route('notas.update', $nota), ['titulo' => 'Idea 2', 'contenido' => 'Texto', 'fijada' => '0'])
            ->assertOk();
        $this->assertSame('Idea 2', $nota->fresh()->titulo);
    }
}
