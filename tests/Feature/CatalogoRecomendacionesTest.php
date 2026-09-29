<?php

namespace Tests\Feature;

use App\Enums\CategoriaRecomendacion;
use App\Enums\EstadoTarea;
use App\Models\Tarea;
use App\Services\Recomendaciones\CatalogoRecomendaciones;
use App\Services\Recomendaciones\MotorRecomendaciones;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoRecomendacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_catalogo_es_amplio_tiene_claves_unicas_y_las_cuatro_categorias(): void
    {
        $todos = CatalogoRecomendaciones::todos();

        $this->assertGreaterThanOrEqual(16, $todos->count());
        $this->assertSame($todos->count(), $todos->pluck('clave')->unique()->count());
        foreach (CategoriaRecomendacion::cases() as $categoria) {
            $this->assertGreaterThanOrEqual(3, $todos->where('categoria', $categoria)->count(), $categoria->value);
        }
        foreach ($todos as $consejo) {
            $this->assertNotSame('', $consejo->titulo);
            $this->assertNotSame('', $consejo->texto);
            $this->assertSame($consejo->ruta === null, $consejo->accion === null);
            if ($consejo->ruta) {
                $this->assertTrue(app('router')->has($consejo->ruta), $consejo->ruta);
            }
        }
    }

    public function test_la_rotacion_es_estable_en_el_dia_y_cambia_entre_dias(): void
    {
        $hoy = CatalogoRecomendaciones::delDia('2026-09-29')->pluck('clave')->all();

        $this->assertSame($hoy, CatalogoRecomendaciones::delDia('2026-09-29')->pluck('clave')->all());
        $this->assertNotSame($hoy, CatalogoRecomendaciones::delDia('2026-09-30')->pluck('clave')->all());
        $this->assertEqualsCanonicalizing(CatalogoRecomendaciones::todos()->pluck('clave')->all(), $hoy);
    }

    public function test_filtrar_por_categoria_devuelve_solo_esa_categoria(): void
    {
        $estudio = CatalogoRecomendaciones::delDia('2026-09-29', CategoriaRecomendacion::Estudio);

        $this->assertNotEmpty($estudio);
        $this->assertTrue($estudio->every(fn ($c) => $c->categoria === CategoriaRecomendacion::Estudio));
    }

    public function test_la_vista_muestra_dos_columnas_y_el_resumen(): void
    {
        $this->get(route('recomendaciones.index'))
            ->assertOk()
            ->assertSee('Para ti ahora')
            ->assertSee('Ideas para tu día')
            ->assertSee('Salud y hábitos')
            ->assertSee('Hora sugerida para acostarte')
            ->assertSee('rec-card', false);
    }

    public function test_la_vista_es_estable_al_recargar(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 10:00'));
        $primera = $this->get(route('recomendaciones.index'))->assertOk()->getContent();
        $segunda = $this->get(route('recomendaciones.index'))->getContent();

        $this->assertSame(
            CatalogoRecomendaciones::delDia('2026-09-29')->take(8)->pluck('titulo')->all(),
            CatalogoRecomendaciones::delDia('2026-09-29')->take(8)->pluck('titulo')->all(),
        );
        $this->assertStringContainsString(e(CatalogoRecomendaciones::delDia('2026-09-29')->first()->titulo), $primera);
        $this->assertStringContainsString(e(CatalogoRecomendaciones::delDia('2026-09-29')->first()->titulo), $segunda);
    }

    public function test_el_filtro_por_categoria_oculta_las_demas_y_una_categoria_invalida_se_ignora(): void
    {
        $this->get(route('recomendaciones.index', ['categoria' => 'estudio']))
            ->assertOk()
            ->assertSee('Estudiá con la técnica Pomodoro')
            ->assertDontSee('Regla de los 2 minutos')
            ->assertDontSee('Hora sugerida para acostarte');

        $this->get(route('recomendaciones.index', ['categoria' => 'salud']))
            ->assertOk()
            ->assertSee('Hora sugerida para acostarte');

        $this->get(route('recomendaciones.index', ['categoria' => 'xx']))->assertOk()->assertSee('Para ti ahora');
    }

    public function test_los_consejos_sin_fuente_se_marcan_como_generales(): void
    {
        $this->get(route('recomendaciones.index', ['categoria' => 'salud']))
            ->assertSee('Consejo general, sin fuente específica.')
            ->assertSee('who.int', false);
    }

    public function test_un_parcial_cercano_genera_una_recomendacion_de_estudio(): void
    {
        Tarea::factory()->create(['titulo' => 'Parcial de Redes', 'fecha_limite' => '2026-09-30', 'estado' => EstadoTarea::Pendiente]);

        $r = app(MotorRecomendaciones::class)->generar(CarbonImmutable::parse('2026-09-29 11:00'))
            ->first(fn ($x) => str_starts_with($x->titulo, 'Se acerca'));

        $this->assertNotNull($r);
        $this->assertStringContainsString('mañana', $r->mensaje);
        $this->assertSame(CategoriaRecomendacion::Estudio, $r->categoria);
    }

    public function test_sin_pomodoros_la_semana_sugiere_uno(): void
    {
        $r = app(MotorRecomendaciones::class)->generar(CarbonImmutable::parse('2026-09-29 11:00'))
            ->first(fn ($x) => $x->titulo === 'Probá un pomodoro esta semana');

        $this->assertNotNull($r);
    }
}
