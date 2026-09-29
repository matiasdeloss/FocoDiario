<?php

namespace Tests\Feature;

use App\Enums\ColorActividad;
use App\Enums\TipoContexto;
use App\Models\Caja;
use App\Models\Contexto;
use App\Models\Nota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaActividadTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_crea_una_actividad_como_contexto_de_tipo_materia_con_color(): void
    {
        $this->from(route('agenda.index'))
            ->post(route('agenda.actividades.store'), ['nombre' => 'Otorrino', 'color' => ColorActividad::AzulPolvo->value])
            ->assertRedirect(route('agenda.index'));

        $this->assertDatabaseHas('contextos', [
            'nombre' => 'Otorrino',
            'tipo' => TipoContexto::Materia->value,
            'color' => ColorActividad::AzulPolvo->value,
            'contexto_padre_id' => null,
        ]);
    }

    public function test_el_color_debe_ser_de_la_paleta(): void
    {
        $this->post(route('agenda.actividades.store'), ['nombre' => 'Neuro', 'color' => '#123456'])->assertSessionHasErrors('color');
        $this->post(route('agenda.actividades.store'), ['nombre' => 'Neuro', 'color' => 'rojo'])->assertSessionHasErrors('color');
        $this->post(route('agenda.actividades.store'), ['nombre' => 'Neuro'])->assertSessionHasErrors('color');
        $this->assertDatabaseCount('contextos', 0);
    }

    public function test_el_nombre_es_obligatorio_y_no_se_repite(): void
    {
        $this->post(route('agenda.actividades.store'), ['nombre' => '', 'color' => ColorActividad::Rosa->value])->assertSessionHasErrors('nombre');

        Contexto::factory()->create(['nombre' => 'Pediatría', 'color' => ColorActividad::Rosa->value]);
        $this->post(route('agenda.actividades.store'), ['nombre' => 'Pediatría', 'color' => ColorActividad::Oliva->value])->assertSessionHasErrors('nombre');
    }

    public function test_se_edita_el_nombre_y_el_color(): void
    {
        $actividad = Contexto::factory()->create(['nombre' => 'Neuro', 'color' => ColorActividad::Rosa->value]);

        $this->patch(route('agenda.actividades.update', $actividad), ['nombre' => 'Neurología', 'color' => ColorActividad::Lavanda->value])
            ->assertRedirect();

        $this->assertSame('Neurología', $actividad->fresh()->nombre);
        $this->assertSame(ColorActividad::Lavanda, $actividad->fresh()->colorActividad());
        // Editar sin cambiar el nombre no choca con la regla de nombre único.
        $this->patch(route('agenda.actividades.update', $actividad), ['nombre' => 'Neurología', 'color' => ColorActividad::Celeste->value])
            ->assertSessionHasNoErrors();
    }

    public function test_al_borrar_una_actividad_sus_cajas_y_notas_quedan_sin_ella(): void
    {
        $actividad = Contexto::factory()->create(['nombre' => 'Urología', 'color' => ColorActividad::Ciruela->value]);
        $caja = Caja::factory()->create(['contexto_id' => $actividad->id]);
        $nota = Nota::factory()->create(['contexto_id' => $actividad->id]);

        $this->delete(route('agenda.actividades.destroy', $actividad))->assertRedirect();

        $this->assertDatabaseMissing('contextos', ['id' => $actividad->id]);
        $this->assertNull($caja->fresh()->contexto_id);
        $this->assertNull($nota->fresh()->contexto_id);
    }

    public function test_solo_se_gestionan_contextos_que_son_actividades(): void
    {
        $sinColor = Contexto::factory()->create(['nombre' => 'Carrera', 'color' => null]);

        $this->patch(route('agenda.actividades.update', $sinColor), ['nombre' => 'Otra', 'color' => ColorActividad::Rosa->value])->assertNotFound();
        $this->delete(route('agenda.actividades.destroy', $sinColor))->assertNotFound();
        $this->assertDatabaseHas('contextos', ['id' => $sinColor->id]);
    }

    public function test_las_actividades_de_la_agenda_aparecen_en_el_destino_de_la_nota_rapida(): void
    {
        Contexto::factory()->create(['nombre' => 'Oftalmología', 'color' => ColorActividad::Celeste->value]);

        $this->get(route('hoy'))
            ->assertOk()
            ->assertSeeInOrder(['id="nota-rapida-destino"', 'Bandeja de entrada', 'Oftalmología'], false);
    }

    public function test_el_contexto_de_notas_valida_el_color_contra_la_paleta(): void
    {
        $this->post(route('contextos.store'), ['nombre' => 'Física', 'tipo' => 'materia', 'color' => '#123456'])->assertSessionHasErrors('color');
        $this->post(route('contextos.store'), ['nombre' => 'Física', 'tipo' => 'materia', 'color' => ColorActividad::Oliva->value])->assertSessionHasNoErrors();
        // Sin color sigue siendo válido (radio "Sin color" con valor vacío).
        $this->post(route('contextos.store'), ['nombre' => 'Química', 'tipo' => 'materia', 'color' => ''])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contextos', ['nombre' => 'Física', 'color' => ColorActividad::Oliva->value]);
        $this->assertDatabaseHas('contextos', ['nombre' => 'Química', 'color' => null]);
    }

    public function test_el_formulario_de_contextos_ofrece_la_paleta(): void
    {
        $this->get(route('contextos.create'))
            ->assertOk()
            ->assertSee('Terracota')
            ->assertSee('Azul polvo')
            ->assertSee('Sin color');
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
