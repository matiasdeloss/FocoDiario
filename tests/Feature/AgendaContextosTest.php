<?php

namespace Tests\Feature;

use App\Enums\ColorActividad;
use App\Enums\TipoContexto;
use App\Models\Caja;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** En la Agenda todo es "contexto": la caja usa cualquiera de los cuatro tipos y se ve con su color efectivo. */
class AgendaContextosTest extends TestCase
{
    use RefreshDatabase;

    private const FECHA = '2026-10-06';

    // ---------- Copy: "actividad" ya no aparece ----------

    public function test_el_planner_la_hoja_y_el_dialogo_de_caja_no_hablan_de_actividades(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Redes', 'color' => ColorActividad::Celeste->value]);
        Caja::factory()->delDia(self::FECHA)->create(['contexto_id' => $contexto->id]);

        $planner = $this->get(route('agenda.index', ['semana' => '2026-10-05']))->assertOk()->getContent();
        $hoja = $this->get(route('agenda.dia', ['fecha' => self::FECHA]))->assertOk()->getContent();

        foreach (['planner' => $planner, 'hoja' => $hoja] as $nombre => $html) {
            $visible = strip_tags(preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html));
            $this->assertStringNotContainsStringIgnoringCase('actividad', $visible, "Texto visible de {$nombre}");
            // Tampoco en atributos de accesibilidad ni tooltips.
            $this->assertDoesNotMatchRegularExpression('/(aria-label|title|placeholder)="[^"]*actividad/i', $html, "Atributos de {$nombre}");
        }

        $this->assertStringContainsString('aria-label="Contextos de la semana y sus colores"', $planner);
        $this->assertStringContainsString('Gestionar contextos', $hoja);
        $this->assertStringContainsString('Sin contexto', $hoja);
    }

    public function test_los_mensajes_de_validacion_de_la_caja_hablan_de_contextos(): void
    {
        $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'texto', 'contexto_id' => 999999])
            ->assertStatus(422)
            ->assertJsonPath('errors.contexto_id.0', 'El contexto elegido no existe.');
    }

    public function test_ya_no_existen_las_rutas_de_actividades(): void
    {
        $this->post('/agenda/actividades', ['nombre' => 'X', 'color' => ColorActividad::Rosa->value])->assertNotFound();
    }

    // ---------- La caja admite cualquier contexto ----------

    public function test_una_caja_acepta_un_contexto_de_cada_tipo(): void
    {
        foreach (TipoContexto::cases() as $tipo) {
            $contexto = Contexto::factory()->state(['tipo' => $tipo, 'nombre' => 'Uno de '.$tipo->value, 'color' => null])->create();

            $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'texto', 'contexto_id' => $contexto->id])
                ->assertCreated()->assertJsonPath('caja.contexto_id', $contexto->id);
        }

        $this->assertSame(4, Caja::whereNotNull('contexto_id')->count());
    }

    public function test_una_caja_rechaza_el_contexto_de_otro_usuario(): void
    {
        $ajeno = Contexto::factory()->create(['nombre' => 'Ajeno']);
        Contexto::withoutGlobalScopes()->whereKey($ajeno->id)->update(['user_id' => User::factory()->create()->id]);

        $this->postJson(route('agenda.cajas.store'), ['fecha' => self::FECHA, 'tipo' => 'texto', 'contexto_id' => $ajeno->id])
            ->assertStatus(422)->assertJsonValidationErrors('contexto_id');

        $caja = Caja::factory()->delDia(self::FECHA)->create();
        $this->patchJson(route('agenda.cajas.update', $caja), ['contexto_id' => $ajeno->id])->assertStatus(422);
        $this->assertNull($caja->fresh()->contexto_id);
    }

    public function test_el_dialogo_de_caja_ofrece_los_contextos_agrupados_por_tipo(): void
    {
        foreach (TipoContexto::cases() as $tipo) {
            Contexto::factory()->state(['tipo' => $tipo, 'nombre' => 'Uno de '.$tipo->value])->create();
        }

        $html = $this->get(route('agenda.dia', ['fecha' => self::FECHA]))->assertOk()->getContent();

        $this->assertStringContainsString('<select id="dc-contexto"', $html);
        foreach (['Entorno', 'Materia', 'Tema', 'Proyecto'] as $grupo) {
            $this->assertStringContainsString('<optgroup label="'.$grupo.'">', $html);
        }
        $this->assertStringContainsString('Uno de proyecto', $html);
    }

    // ---------- Color efectivo de la caja ----------

    public function test_la_caja_se_ve_con_el_color_de_su_contexto_o_el_de_su_ancestro_y_sin_color_queda_por_defecto(): void
    {
        $entorno = Contexto::factory()->entorno()->create(['nombre' => 'Carrera', 'color' => ColorActividad::Salvia->value]);
        $propio = Contexto::factory()->create(['nombre' => 'Redes', 'contexto_padre_id' => $entorno->id, 'color' => ColorActividad::Rosa->value]);
        $heredado = Contexto::factory()->tema()->create(['nombre' => 'Modelo OSI', 'contexto_padre_id' => $propio->id, 'color' => null]);
        $nada = Contexto::factory()->proyecto()->create(['nombre' => 'Suelto', 'color' => null]);

        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'Propia', 'contexto_id' => $propio->id]);
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'Heredada', 'contexto_id' => $heredado->id]);
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'Sin color', 'contexto_id' => $nada->id]);

        $html = $this->get(route('agenda.dia', ['fecha' => self::FECHA]))->assertOk()->getContent();

        // Dos cajas con color (la del contexto con color y la del hijo que hereda) y la de "Suelto" sin clase de color.
        $this->assertSame(2, substr_count($html, 'caja '.ColorActividad::Rosa->clase()));
        $this->assertSame(0, substr_count($html, 'caja '.ColorActividad::Salvia->clase()));
        $this->assertSame(3, preg_match_all('/<article[^>]*class="caja[^"]*"/', $html));
        $this->assertSame(1, preg_match_all('/<article[^>]*class="caja(?!\s+actividad-)[^"]*"/', $html));

        // El mapa para teñir una caja al cambiarle el contexto trae el color efectivo de cada contexto.
        preg_match('/data-contextos="([^"]*)"/', $html, $mapa);
        $clases = json_decode(html_entity_decode($mapa[1]), true);
        $this->assertSame(ColorActividad::Rosa->clase(), $clases[$heredado->id]);
        $this->assertSame(ColorActividad::Salvia->clase(), $clases[$entorno->id]);
        $this->assertArrayNotHasKey($nada->id, $clases);
    }

    public function test_el_planner_y_el_calendario_usan_el_color_efectivo(): void
    {
        $entorno = Contexto::factory()->entorno()->create(['nombre' => 'Carrera', 'color' => ColorActividad::Celeste->value]);
        $hijo = Contexto::factory()->create(['nombre' => 'Redes', 'contexto_padre_id' => $entorno->id, 'color' => null]);
        Caja::factory()->delDia(self::FECHA)->create(['titulo' => 'Estudiar', 'contexto_id' => $hijo->id]);

        $this->get(route('agenda.index', ['semana' => '2026-10-05']))->assertOk()
            ->assertSee('plan-linea '.ColorActividad::Celeste->clase(), false)
            // La leyenda lista los contextos usados esta semana, con su color efectivo.
            ->assertSee('plan-chip '.ColorActividad::Celeste->clase(), false)
            ->assertSee('Redes');

        $evento = collect($this->getJson(route('calendario.eventos', ['start' => '2026-10-05', 'end' => '2026-10-12', 'tipos' => 'planner']))->assertOk()->json())
            ->firstWhere('extendedProps.tipo', 'planner');
        $this->assertContains(ColorActividad::Celeste->clase(), $evento['classNames']);
        $this->assertSame('Redes', $evento['extendedProps']['contexto']);
    }

    public function test_la_leyenda_solo_lista_los_contextos_que_usan_las_cajas_de_la_semana(): void
    {
        $usado = Contexto::factory()->create(['nombre' => 'Pediatría', 'color' => ColorActividad::Ocre->value]);
        Contexto::factory()->create(['nombre' => 'No usado', 'color' => ColorActividad::Rosa->value]);
        Caja::factory()->delDia(self::FECHA)->create(['contexto_id' => $usado->id]);
        Caja::factory()->delDia('2026-11-20')->create(['contexto_id' => Contexto::factory()->create(['nombre' => 'Otra semana'])->id]);

        $this->get(route('agenda.index', ['semana' => '2026-10-05']))->assertOk()
            ->assertSee('Pediatría')->assertDontSee('No usado')->assertDontSee('Otra semana');

        $this->get(route('agenda.index', ['semana' => '2027-01-04']))->assertOk()
            ->assertSee('Asignale un contexto a las cajas para verlas con su color.');
    }

    // ---------- Lo que seguía valiendo de las "actividades" ----------

    public function test_los_contextos_con_color_aparecen_en_el_destino_de_la_nota_rapida(): void
    {
        Contexto::factory()->create(['nombre' => 'Oftalmología', 'color' => ColorActividad::Celeste->value]);

        $this->get(route('hoy'))->assertOk()
            ->assertSeeInOrder(['id="nota-rapida-destino"', 'Bandeja de entrada', 'Oftalmología'], false);
    }

    public function test_al_borrar_un_contexto_sus_cajas_y_notas_quedan_sin_el(): void
    {
        $contexto = Contexto::factory()->create(['nombre' => 'Urología', 'color' => ColorActividad::Ciruela->value]);
        $caja = Caja::factory()->create(['contexto_id' => $contexto->id]);
        $nota = Nota::factory()->create(['contexto_id' => $contexto->id]);
        $tarea = Tarea::factory()->create(['contexto_id' => $contexto->id]);

        $this->delete(route('contextos.destroy', $contexto))->assertRedirect();

        $this->assertNull($caja->fresh()->contexto_id);
        $this->assertNull($nota->fresh()->contexto_id);
        $this->assertNull($tarea->fresh()->contexto_id);
    }

    public function test_el_contexto_valida_el_color_contra_la_paleta(): void
    {
        $this->post(route('contextos.store'), ['nombre' => 'Física', 'tipo' => 'materia', 'color' => '#123456'])->assertSessionHasErrors('color');
        $this->post(route('contextos.store'), ['nombre' => 'Física', 'tipo' => 'materia', 'color' => ColorActividad::Oliva->value])->assertSessionHasNoErrors();
        // Sin color (hereda del padre) sigue siendo válido.
        $this->post(route('contextos.store'), ['nombre' => 'Química', 'tipo' => 'materia', 'color' => ''])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contextos', ['nombre' => 'Física', 'color' => ColorActividad::Oliva->value]);
        $this->assertDatabaseHas('contextos', ['nombre' => 'Química', 'color' => null]);
    }

    public function test_el_formulario_de_contextos_ofrece_la_paleta(): void
    {
        $this->get(route('contextos.create'))->assertOk()->assertSee('Terracota')->assertSee('Azul polvo')->assertSee('Sin color');
    }

    public function test_cada_color_de_la_paleta_tiene_sus_tokens_en_organic_css(): void
    {
        $css = file_get_contents(resource_path('css/organic.css'));

        $this->assertCount(10, ColorActividad::cases());

        foreach (ColorActividad::cases() as $color) {
            $clave = $color->clave();

            $this->assertStringContainsString("--actividad-{$clave}-fondo:", $css);
            $this->assertStringContainsString("--actividad-{$clave}-texto:", $css);
            $this->assertMatchesRegularExpression('/--actividad-'.preg_quote($clave, '/').'-acento:\s*'.preg_quote($color->value, '/').';/i', $css);
        }
    }
}
