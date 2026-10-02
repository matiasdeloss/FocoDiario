<?php

namespace Tests\Feature;

use App\Enums\CategoriaRecomendacion;
use App\Enums\EstadoTarea;
use App\Enums\TipoRecomendacion;
use App\Models\Tarea;
use App\Services\Recomendaciones\CatalogoRecomendaciones;
use App\Services\Recomendaciones\MotorRecomendaciones;
use App\Services\Recomendaciones\Recomendacion;
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

    public function test_la_vista_muestra_una_seccion_por_categoria_y_el_resumen(): void
    {
        $html = $this->get(route('recomendaciones.index'))
            ->assertOk()
            ->assertSee('Hora sugerida para acostarte')
            ->assertSee('rec-card', false)
            ->assertDontSee('rec-filtro', false)
            ->getContent();

        foreach (CategoriaRecomendacion::cases() as $categoria) {
            $this->assertStringContainsString('id="rec-cat-'.$categoria->value.'"', $html, $categoria->value);
            $this->assertStringContainsString($categoria->etiqueta(), $html);
        }
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('ideas de estudio y vida', $html);
    }

    public function test_las_secciones_siguen_el_orden_del_enum(): void
    {
        $html = $this->get(route('recomendaciones.index'))->getContent();

        $posiciones = array_map(fn ($c) => strpos($html, 'id="rec-cat-'.$c->value.'"'), CategoriaRecomendacion::cases());
        $ordenadas = $posiciones;
        sort($ordenadas);
        $this->assertSame($ordenadas, $posiciones);
    }

    public function test_la_banda_para_ti_solo_aparece_con_alertas_sin_categoria(): void
    {
        $this->get(route('recomendaciones.index'))->assertOk()->assertDontSee('id="rec-para-ti"', false);

        $motor = $this->mock(MotorRecomendaciones::class);
        $motor->shouldReceive('generar')->andReturn(collect([
            new Recomendacion('Algo urgente sin categoría', 'Mensaje urgente.', TipoRecomendacion::Alerta, 'bi-exclamation-triangle', 90),
        ]));

        $this->get(route('recomendaciones.index'))
            ->assertOk()
            ->assertSee('id="rec-para-ti"', false)
            ->assertSee('Algo urgente sin categoría')
            ->assertSee('1 alerta')
            ->assertSee('rec-destacada', false);
    }

    public function test_el_hero_muestra_las_tres_metricas(): void
    {
        $motor = $this->mock(MotorRecomendaciones::class);
        $motor->shouldReceive('generar')->andReturn(collect([
            new Recomendacion('Urgente', 'Mensaje.', TipoRecomendacion::Alerta, 'bi-exclamation-triangle', 90),
            new Recomendacion('Otra', 'Mensaje.', TipoRecomendacion::Info, 'bi-info-circle', 10),
        ]));

        $html = $this->get(route('recomendaciones.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-stat="alertas">1</', $html);
        $this->assertMatchesRegularExpression('/data-stat="datos">2</', $html);
        $this->assertMatchesRegularExpression('/data-stat="ideas">8</', $html);
    }

    public function test_una_categoria_sin_items_no_se_muestra(): void
    {
        $motor = $this->mock(MotorRecomendaciones::class);
        $motor->shouldReceive('generar')->andReturn(collect());

        $html = $this->get(route('recomendaciones.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="rec-para-ti"', $html);
        // Con el motor vacío quedan las ideas del catálogo: dos por categoría.
        $this->assertSame(2 * count(CategoriaRecomendacion::cases()), substr_count($html, 'class="rec-card '));
    }

    public function test_el_parametro_categoria_ya_no_tiene_efecto_y_no_rompe(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 10:00'));

        foreach (['estudio', 'xx', ''] as $valor) {
            $html = $this->get(route('recomendaciones.index', ['categoria' => $valor]))->assertOk()->getContent();
            foreach (CategoriaRecomendacion::cases() as $categoria) {
                $this->assertStringContainsString('id="rec-cat-'.$categoria->value.'"', $html, $valor);
            }
        }
    }

    public function test_la_vista_es_estable_al_recargar(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 10:00'));
        $titulos = fn (string $html) => collect(CatalogoRecomendaciones::todos())
            ->filter(fn ($c) => str_contains($html, e($c->titulo)))->pluck('clave')->sort()->values()->all();

        $primera = $this->get(route('recomendaciones.index'))->assertOk()->getContent();
        $segunda = $this->get(route('recomendaciones.index'))->getContent();

        $this->assertSame($titulos($primera), $titulos($segunda));
        $esperadas = collect(CategoriaRecomendacion::cases())
            ->flatMap(fn ($c) => CatalogoRecomendaciones::delDia('2026-09-29', $c)->take(2)->pluck('clave'))->sort()->values()->all();
        $this->assertSame($esperadas, $titulos($primera));
    }

    public function test_los_consejos_sin_fuente_se_marcan_como_generales(): void
    {
        // Se busca un día en que entre las ideas mostradas haya una sin fuente (la rotación cambia por día).
        $dia = collect(range(0, 30))
            ->map(fn ($i) => CarbonImmutable::parse('2026-09-01')->addDays($i))
            ->first(fn ($f) => collect(CategoriaRecomendacion::cases())
                ->flatMap(fn ($c) => CatalogoRecomendaciones::delDia($f->toDateString(), $c)->take(2))
                ->contains(fn ($x) => $x->fuente === null));
        $this->assertNotNull($dia);

        $this->travelTo($dia->setTime(10, 0));
        $this->get(route('recomendaciones.index'))->assertSee('Consejo general, sin fuente específica.');
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
