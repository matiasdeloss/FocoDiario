<?php

namespace Tests\Feature;

use App\Models\Contexto;
use App\Models\Nota;
use App\Support\Aviso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

/** Avisos (toasts) del servidor: el helper App\Support\Aviso y un caso por tipo de respuesta (redirección, HTMX y JSON). */
class AvisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_tipo_por_defecto_es_exito_y_no_se_pudo_es_error(): void
    {
        $this->assertSame('exito', Aviso::tipoDe('Tarea creada.'));
        $this->assertSame('error', Aviso::tipoDe('No se pudo guardar.'));
        $this->assertSame('info', Aviso::tipoDe('Saliste.', 'info'));
        $this->assertSame('aviso', Aviso::tipoDe('Ojo.', 'aviso'));
        // Un tipo inventado no pasa: se decide por el texto.
        $this->assertSame('exito', Aviso::tipoDe('Hola.', 'peligro'));
    }

    public function test_flash_mantiene_estado_y_agrega_el_tipo_y_el_detalle_solo_si_hay(): void
    {
        $this->assertSame(['estado' => 'Tarea creada.', 'estado_tipo' => 'exito'], Aviso::flash('Tarea creada.'));
        $this->assertSame(
            ['estado' => 'No se pudo mover.', 'estado_tipo' => 'error', 'estado_detalle' => 'Volvió a su columna.'],
            Aviso::flash('No se pudo mover.', null, 'Volvió a su columna.'),
        );
    }

    public function test_guardar_deja_el_aviso_en_la_sesion_flash(): void
    {
        Aviso::guardar('Nota guardada.', 'info');

        $this->assertSame('Nota guardada.', session('estado'));
        $this->assertSame('info', session('estado_tipo'));
        $this->assertNull(session('estado_detalle'));
    }

    public function test_en_htmx_agrega_el_aviso_a_hx_trigger_con_json_ascii(): void
    {
        $respuesta = Aviso::enHtmx(new Response('<p>fila</p>'), 'Nota guardada en Álgebra.');

        $encabezado = $respuesta->headers->get('HX-Trigger');

        // Los encabezados HTTP no llevan UTF-8: los acentos van como \uXXXX y al decodificar vuelven.
        $this->assertStringContainsString('\u00c1lgebra', $encabezado);
        $this->assertSame(
            ['mostrar-aviso' => ['tipo' => 'exito', 'texto' => 'Nota guardada en Álgebra.', 'detalle' => null]],
            json_decode($encabezado, true),
        );
        $this->assertSame('<p>fila</p>', $respuesta->getContent());
    }

    public function test_en_htmx_conserva_otros_eventos_y_junta_varios_avisos_en_un_arreglo(): void
    {
        $respuesta = new Response('');
        $respuesta->headers->set('HX-Trigger', json_encode(['otro-evento' => true]));

        Aviso::enHtmx($respuesta, 'Uno.');
        Aviso::enHtmx($respuesta, 'No se pudo dos.', null, 'Probá de nuevo.');

        $eventos = json_decode($respuesta->headers->get('HX-Trigger'), true);

        $this->assertTrue($eventos['otro-evento']);
        $this->assertSame([
            ['tipo' => 'exito', 'texto' => 'Uno.', 'detalle' => null],
            ['tipo' => 'error', 'texto' => 'No se pudo dos.', 'detalle' => 'Probá de nuevo.'],
        ], $eventos['mostrar-aviso']);
    }

    public function test_en_htmx_acepta_una_vista_y_la_convierte_en_respuesta(): void
    {
        $respuesta = Aviso::enHtmx(view('layouts._tema'), 'Listo.');

        $this->assertStringContainsString('data-tema-opcion', $respuesta->getContent());
        $this->assertSame('Listo.', json_decode($respuesta->headers->get('HX-Trigger'), true)['mostrar-aviso']['texto']);
    }

    public function test_redireccion_el_flash_se_ve_como_nodo_oculto_y_no_como_banner(): void
    {
        $this->post(route('tareas.store'), ['titulo' => 'Con redirección', 'prioridad' => 'media', 'estado' => 'pendiente'])
            ->assertRedirect(route('tareas.index'))
            ->assertSessionHas('estado', 'Tarea creada.')
            ->assertSessionHas('estado_tipo', 'exito');

        $this->get(route('tareas.index'))
            ->assertOk()
            ->assertSee('data-aviso-flash', false)
            ->assertSee('data-tipo="exito"', false)
            ->assertSee('data-texto="Tarea creada."', false)
            // Un solo contenedor de avisos en el layout y ningún banner en línea.
            ->assertSee('id="avisos-flotantes"', false)
            ->assertDontSee('class="aviso-foco"', false);
    }

    public function test_sin_flash_el_layout_no_deja_ningun_nodo_de_aviso(): void
    {
        $this->get(route('tareas.index'))
            ->assertOk()
            ->assertSee('id="avisos-flotantes"', false)
            ->assertDontSee('data-aviso-flash', false);
    }

    public function test_htmx_fijar_una_nota_avisa_por_hx_trigger(): void
    {
        $nota = Nota::factory()->create(['fijada' => false]);

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('notas.fijar', $nota))
            ->assertOk()
            ->assertAvisoHtmx('Nota fijada.');

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('notas.fijar', $nota))
            ->assertAvisoHtmx('Nota desfijada.');
    }

    public function test_htmx_mover_una_nota_avisa_por_hx_trigger(): void
    {
        $nota = Nota::factory()->create();

        $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('notas.mover', $nota), ['contexto_id' => ''])
            ->assertAvisoHtmx('Nota movida.');
    }

    public function test_sin_htmx_mover_una_nota_redirige_con_flash(): void
    {
        $nota = Nota::factory()->create();

        $this->from(route('notas.index'))
            ->patch(route('notas.mover', $nota), ['contexto_id' => ''])
            ->assertRedirect(route('notas.index'))
            ->assertSessionHas('estado', 'Nota movida.')
            ->assertSessionHas('estado_tipo', 'exito');
    }

    public function test_htmx_con_recarga_deja_el_aviso_en_la_sesion(): void
    {
        $contexto = Contexto::factory()->create();

        $this->withHeaders(['HX-Request' => 'true'])
            ->delete(route('contextos.destroy', $contexto))
            ->assertHeader('HX-Redirect', route('contextos.index'));

        $this->assertSame('Contexto eliminado. Sus notas pasaron a la bandeja de entrada.', session('estado'));
        $this->assertSame('exito', session('estado_tipo'));
    }

    public function test_json_el_modal_responde_con_mensaje_y_deja_el_aviso_en_la_sesion(): void
    {
        $this->postJson(route('tareas.store'), ['titulo' => 'Desde el modal', 'prioridad' => 'media', 'estado' => 'pendiente'], ['X-Modal' => '1'])
            ->assertCreated()
            ->assertJsonPath('mensaje', 'Tarea creada.');

        $this->assertSame('Tarea creada.', session('estado'));
        $this->assertSame('exito', session('estado_tipo'));
    }
}
