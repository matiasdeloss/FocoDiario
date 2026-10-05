<?php

namespace Tests\Feature;

use App\Enums\ColorActividad;
use App\Enums\TipoCaja;
use App\Models\Caja;
use App\Models\Contexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaCajaTest extends TestCase
{
    use RefreshDatabase;

    private const FECHA = '2026-09-28';

    public function test_la_hoja_del_dia_muestra_las_cajas_y_el_boton_para_volver_al_planner(): void
    {
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'Pediatría', 'contenido' => 'Escuchar clase']);
        Caja::factory()->delDia('2026-09-29')->create(['titulo' => 'De otro día']);

        $this->get(route('agenda.dia', ['fecha' => self::FECHA]))
            ->assertOk()
            ->assertSee('Lunes 28 de septiembre')
            ->assertSee('Pediatría')
            ->assertSee('Escuchar clase')
            ->assertDontSee('De otro día')
            ->assertSee('Nueva caja')
            ->assertSee('Mostrar hechas')
            ->assertSee(route('agenda.index', ['semana' => self::FECHA]), false);
    }

    public function test_una_fecha_que_no_existe_da_404(): void
    {
        $this->get('/agenda/dia/2026-02-31')->assertNotFound();
        $this->get('/agenda/dia/manana')->assertNotFound();
    }

    public function test_se_crea_una_caja_de_texto_con_posicion_inicial_debajo_de_las_existentes(): void
    {
        Caja::factory()->delDia(self::FECHA)->create(['y' => 2, 'alto' => 6]);

        $respuesta = $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'texto'])
            ->assertCreated()
            ->assertJsonPath('caja.tipo', 'texto')
            ->assertJsonPath('caja.y', 8)
            ->assertJsonPath('caja.x', 0)
            ->assertJsonPath('caja.ancho', 6)
            ->assertJsonStructure(['caja' => ['id', 'fecha', 'items', 'hecha'], 'html']);

        $this->assertStringContainsString('gs-y="8"', $respuesta->json('html'));
        $this->assertDatabaseCount('cajas', 2);
    }

    public function test_se_crea_una_caja_de_lista_con_contexto_y_horas(): void
    {
        $actividad = Contexto::factory()->create(['color' => ColorActividad::Salvia->value]);

        $this->postJson(route('agenda.cajas.store'), [
            'fecha' => self::FECHA,
            'tipo' => 'lista',
            'titulo' => 'Otorrino',
            'contexto_id' => $actividad->id,
            'hora_inicio' => '18:00',
            'hora_fin' => '19:30',
            'items' => [['texto' => 'Leer resumen', 'hecho' => false], ['texto' => 'Repasar', 'hecho' => true]],
        ])->assertCreated();

        $caja = Caja::first();
        $this->assertSame(TipoCaja::Lista, $caja->tipo);
        $this->assertSame('18:00', $caja->hora_inicio);
        $this->assertSame('19:30', $caja->hora_fin);
        $this->assertSame($actividad->id, $caja->contexto_id);
        $this->assertSame([['texto' => 'Leer resumen', 'hecho' => false], ['texto' => 'Repasar', 'hecho' => true]], $caja->items);
        $this->assertSame('18:00–19:30', $caja->horaTexto());
    }

    public function test_se_edita_una_caja_de_a_un_campo_sin_perder_el_resto(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->conHora('18:00')->create(['titulo' => 'Neuro', 'contenido' => 'Texto original']);

        $this->patchJson(route('agenda.cajas.update', $caja), ['titulo' => 'Neurología'])->assertOk()->assertJsonPath('caja.titulo', 'Neurología');
        $this->patchJson(route('agenda.cajas.update', $caja), ['contenido' => 'Texto nuevo'])->assertOk();
        $this->patchJson(route('agenda.cajas.update', $caja), ['hecha' => true])->assertOk();

        $caja->refresh();
        $this->assertSame('Neurología', $caja->titulo);
        $this->assertSame('Texto nuevo', $caja->contenido);
        $this->assertTrue($caja->hecha);
        $this->assertSame('18:00', $caja->hora_inicio);
    }

    public function test_se_edita_el_contenido_de_una_lista_y_se_pasa_de_texto_a_lista(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->create(['contenido' => "Uno\nDos"]);

        $this->patchJson(route('agenda.cajas.update', $caja), [
            'tipo' => 'lista',
            'items' => [['texto' => 'Uno', 'hecho' => false], ['texto' => 'Dos', 'hecho' => true], ['texto' => '', 'hecho' => false]],
        ])->assertOk()->assertJsonPath('caja.tipo', 'lista')->assertJsonCount(3, 'caja.items');

        $this->assertTrue($caja->fresh()->itemsLista()[1]['hecho']);
    }

    public function test_se_pueden_quitar_las_horas_y_el_contexto(): void
    {
        $actividad = Contexto::factory()->create(['color' => ColorActividad::Rosa->value]);
        $caja = Caja::factory()->delDia(self::FECHA)->conHora('10:00', '11:00')->create(['contexto_id' => $actividad->id]);

        $this->patchJson(route('agenda.cajas.update', $caja), ['hora_inicio' => null, 'hora_fin' => null, 'contexto_id' => null])->assertOk();

        $caja->refresh();
        $this->assertNull($caja->hora_inicio);
        $this->assertNull($caja->hora_fin);
        $this->assertNull($caja->contexto_id);
    }

    public function test_se_borra_una_caja(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->create();

        $this->deleteJson(route('agenda.cajas.destroy', $caja))->assertOk();

        $this->assertDatabaseMissing('cajas', ['id' => $caja->id]);
    }

    public function test_un_contexto_inexistente_se_rechaza(): void
    {
        $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'texto', 'contexto_id' => 999])
            ->assertJsonValidationErrors('contexto_id');
    }

    public function test_validacion_de_fecha_y_tipo_al_crear(): void
    {
        $this->postJson(route('agenda.cajas.store'), ['tipo' => 'texto'])->assertJsonValidationErrors('fecha');
        $this->postJson(route('agenda.cajas.store'), ['fecha' => '2026-02-31', 'tipo' => 'texto'])->assertJsonValidationErrors('fecha');
        $this->postJson(route('agenda.cajas.store'), ['fecha' => 'ayer', 'tipo' => 'texto'])->assertJsonValidationErrors('fecha');
        $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA])->assertJsonValidationErrors('tipo');
        $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'dibujo'])->assertJsonValidationErrors('tipo');
        $this->assertDatabaseCount('cajas', 0);
    }

    public function test_una_caja_no_se_puede_pasar_a_otro_dia_desde_la_edicion(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->create();

        $this->patchJson(route('agenda.cajas.update', $caja), ['fecha' => '2026-09-29'])->assertJsonValidationErrors('fecha');
    }

    public function test_validacion_de_horas(): void
    {
        $crear = fn (array $horas) => $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'texto', ...$horas]);

        $crear(['hora_inicio' => '25:00'])->assertJsonValidationErrors('hora_inicio');
        $crear(['hora_inicio' => '9'])->assertJsonValidationErrors('hora_inicio');
        $crear(['hora_inicio' => 'tarde'])->assertJsonValidationErrors('hora_inicio');
        $crear(['hora_inicio' => '18:00', 'hora_fin' => '17:59'])->assertJsonValidationErrors('hora_fin');
        $crear(['hora_inicio' => '18:00', 'hora_fin' => '18:00'])->assertJsonValidationErrors('hora_fin');
        $crear(['hora_fin' => '18:00'])->assertJsonValidationErrors('hora_inicio');
        $crear(['hora_inicio' => '18:00', 'hora_fin' => '18:01'])->assertCreated();
    }

    public function test_la_hora_de_fin_se_compara_con_la_de_inicio_ya_guardada(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->conHora('18:00', '19:00')->create();

        // Solo llega la hora de fin: se controla contra el inicio guardado.
        $this->patchJson(route('agenda.cajas.update', $caja), ['hora_fin' => '17:00'])->assertJsonValidationErrors('hora_fin');
        // Adelantar el inicio más allá del fin guardado tampoco vale.
        $this->patchJson(route('agenda.cajas.update', $caja), ['hora_inicio' => '20:00'])->assertJsonValidationErrors('hora_fin');
        $this->patchJson(route('agenda.cajas.update', $caja), ['hora_inicio' => '17:00'])->assertOk();
    }

    public function test_validacion_del_tamano_dentro_de_la_grilla(): void
    {
        $crear = fn (array $datos) => $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'texto', ...$datos]);

        $crear(['ancho' => 13])->assertJsonValidationErrors('ancho');
        $crear(['ancho' => 0])->assertJsonValidationErrors('ancho');
        $crear(['alto' => 0])->assertJsonValidationErrors('alto');
        $crear(['alto' => 101])->assertJsonValidationErrors('alto');
        $crear(['x' => 12])->assertJsonValidationErrors('x');
        $crear(['x' => -1])->assertJsonValidationErrors('x');
        $crear(['y' => -1])->assertJsonValidationErrors('y');
        $crear(['y' => 999999])->assertJsonValidationErrors('y');
        // Entra en el rango, pero se sale por la derecha.
        $crear(['x' => 8, 'ancho' => 6])->assertJsonValidationErrors('ancho');
        $crear(['x' => 6, 'ancho' => 6, 'y' => 3, 'alto' => 4])->assertCreated();
    }

    public function test_se_guarda_el_layout_de_varias_cajas(): void
    {
        $a = Caja::factory()->delDia(self::FECHA)->create(['x' => 0, 'y' => 0]);
        $b = Caja::factory()->delDia(self::FECHA)->create(['x' => 6, 'y' => 0]);

        $this->patchJson(route('agenda.dia.layout', ['fecha' => self::FECHA]), ['cajas' => [
            ['id' => $a->id, 'x' => 2, 'y' => 4, 'ancho' => 5, 'alto' => 9],
            ['id' => $b->id, 'x' => 7, 'y' => 1, 'ancho' => 5, 'alto' => 12],
        ]])->assertOk();

        $this->assertSame([2, 4, 5, 9], [$a->fresh()->x, $a->fresh()->y, $a->fresh()->ancho, $a->fresh()->alto]);
        $this->assertSame([7, 1, 5, 12], [$b->fresh()->x, $b->fresh()->y, $b->fresh()->ancho, $b->fresh()->alto]);
    }

    public function test_el_layout_rechaza_valores_fuera_de_la_grilla_y_cajas_de_otro_dia(): void
    {
        $propia = Caja::factory()->delDia(self::FECHA)->create();
        $ajena = Caja::factory()->delDia('2026-09-29')->create();
        $url = route('agenda.dia.layout', ['fecha' => self::FECHA]);

        $this->patchJson($url, ['cajas' => [['id' => $propia->id, 'x' => 10, 'y' => 0, 'ancho' => 6, 'alto' => 4]]])
            ->assertJsonValidationErrors('cajas.0.ancho');
        $this->patchJson($url, ['cajas' => [['id' => $propia->id, 'x' => 0, 'y' => 0, 'ancho' => 20, 'alto' => 4]]])
            ->assertJsonValidationErrors('cajas.0.ancho');
        $this->patchJson($url, ['cajas' => [['id' => $propia->id, 'x' => 0, 'y' => 0, 'ancho' => 4, 'alto' => 0]]])
            ->assertJsonValidationErrors('cajas.0.alto');
        $this->patchJson($url, ['cajas' => [['id' => $ajena->id, 'x' => 0, 'y' => 0, 'ancho' => 4, 'alto' => 4]]])
            ->assertJsonValidationErrors('cajas.0.id');
        $this->patchJson($url, ['cajas' => []])->assertJsonValidationErrors('cajas');
    }

    public function test_el_filtro_de_hechas_y_pendientes_del_modelo(): void
    {
        Caja::factory()->delDia(self::FECHA)->hecha()->create(['titulo' => 'Hecha']);
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'Pendiente']);

        $this->assertSame(['Hecha'], Caja::hechas()->pluck('titulo')->all());
        $this->assertSame(['Pendiente'], Caja::pendientes()->pluck('titulo')->all());
    }

    public function test_las_cajas_del_dia_se_leen_de_arriba_a_abajo_y_de_izquierda_a_derecha(): void
    {
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'C', 'x' => 0, 'y' => 8]);
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'B', 'x' => 6, 'y' => 0]);
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'A', 'x' => 0, 'y' => 0]);

        $this->assertSame(['A', 'B', 'C'], Caja::delDia(self::FECHA)->enOrdenDeLectura()->pluck('titulo')->all());
    }

    public function test_la_hoja_renderiza_las_cajas_con_su_posicion_y_el_color_del_contexto(): void
    {
        $actividad = Contexto::factory()->create(['nombre' => 'Urología', 'color' => ColorActividad::Ciruela->value]);
        Caja::factory()->delDia(self::FECHA)->create(['contexto_id' => $actividad->id, 'x' => 3, 'y' => 5, 'ancho' => 4, 'alto' => 7]);

        $this->get(route('agenda.dia', ['fecha' => self::FECHA]))
            ->assertSee('gs-x="3" gs-y="5" gs-w="4" gs-h="7"', false)
            ->assertSee('caja '.ColorActividad::Ciruela->clase(), false)
            ->assertSee('Urología');
    }

    public function test_una_caja_nueva_tiene_borde_fino_y_automatico(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->create()->fresh();

        $this->assertSame(1, $caja->borde_grosor);
        $this->assertNull($caja->borde_color);
    }

    public function test_se_guarda_el_grosor_y_el_color_del_borde(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->create();

        $this->patchJson(route('agenda.cajas.update', $caja), ['borde_grosor' => 3, 'borde_color' => ColorActividad::Ciruela->value])
            ->assertOk()
            ->assertJsonPath('caja.borde_grosor', 3)
            ->assertJsonPath('caja.borde_color', ColorActividad::Ciruela->value);

        $this->assertSame(3, $caja->fresh()->borde_grosor);
        $this->assertSame('#8e4f73', $caja->fresh()->borde_color);

        $this->get(route('agenda.dia', ['fecha' => self::FECHA]))
            ->assertSee('--caja-borde-grosor: 3px;', false)
            ->assertSee('--caja-borde-color: #8e4f73;', false);

        $this->patchJson(route('agenda.cajas.update', $caja), ['borde_color' => null])->assertOk();
        $this->assertNull($caja->fresh()->borde_color);
    }

    public function test_el_grosor_del_borde_debe_estar_entre_1_y_4(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->create();

        foreach ([0, 5, 'grueso'] as $valor) {
            $this->patchJson(route('agenda.cajas.update', $caja), ['borde_grosor' => $valor])->assertJsonValidationErrors('borde_grosor');
        }

        $this->assertSame(1, $caja->fresh()->borde_grosor);
    }

    public function test_el_color_del_borde_tiene_que_ser_de_la_paleta(): void
    {
        $caja = Caja::factory()->delDia(self::FECHA)->create();

        foreach (['#ffffff', 'rojo', '#c0663'] as $valor) {
            $this->patchJson(route('agenda.cajas.update', $caja), ['borde_color' => $valor])->assertJsonValidationErrors('borde_color');
        }

        $this->assertNull($caja->fresh()->borde_color);
    }

    public function test_las_cajas_no_llevan_tinte_de_fondo_por_contexto(): void
    {
        $css = file_get_contents(resource_path('css/agenda.css'));

        $this->assertDoesNotMatchRegularExpression('/\.caja\[class\*="actividad-"\]\s*\{[^}]*background/', $css);
    }
}
