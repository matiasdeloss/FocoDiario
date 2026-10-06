<?php

namespace Tests\Feature;

use App\Enums\ColorActividad;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Notas, tareas y contextos usan la misma paleta de 10 colores, con "Sin color" (hereda) como primera opción. */
class PaletaUnificadaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_10_11_000001_unificar_colores_de_notas_y_tareas.php';

    private function fila(string $tabla, ?string $color): int
    {
        return DB::table($tabla)->insertGetId(array_filter([
            'user_id' => auth()->id(),
            'titulo' => 'T',
            'contenido' => $tabla === 'notas' ? 'N' : null,
            'prioridad' => $tabla === 'tareas' ? 'media' : null,
            'estado' => $tabla === 'tareas' ? 'pendiente' : null,
            'color' => $color,
            'created_at' => now(),
            'updated_at' => now(),
        ], fn ($v) => $v !== null));
    }

    // ---------- Migración ----------

    public function test_la_migracion_pasa_los_cinco_colores_viejos_a_la_paleta_nueva(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRACION])->assertExitCode(0);

        $esperado = [
            'durazno' => '#c0677a', 'terracota' => '#c0663a', 'salvia' => '#728a58', 'oliva' => '#78802a', 'arena' => '#9a8350',
        ];

        foreach (['notas', 'tareas'] as $tabla) {
            $ids = [];
            foreach (array_keys($esperado) as $viejo) {
                $ids[$viejo] = $this->fila($tabla, $viejo);
            }
            $sin = $this->fila($tabla, null);
            $desconocido = $this->fila($tabla, 'fucsia');
            $yaNuevo = $this->fila($tabla, '#8378b5');

            $this->artisan('migrate')->assertExitCode(0);

            foreach ($esperado as $viejo => $hex) {
                $this->assertSame($hex, DB::table($tabla)->where('id', $ids[$viejo])->value('color'), "{$tabla}: {$viejo}");
            }
            $this->assertNull(DB::table($tabla)->where('id', $sin)->value('color'));
            $this->assertNull(DB::table($tabla)->where('id', $desconocido)->value('color'), "{$tabla}: valor desconocido");
            $this->assertSame('#8378b5', DB::table($tabla)->where('id', $yaNuevo)->value('color'));

            // Y el modelo ya lo lee con la paleta nueva.
            $modelo = $tabla === 'notas' ? Nota::find($ids['durazno']) : Tarea::find($ids['durazno']);
            $this->assertSame(ColorActividad::Rosa, $modelo->color);

            $this->artisan('migrate:rollback', ['--path' => self::MIGRACION])->assertExitCode(0);
        }
    }

    public function test_al_revertir_vuelven_las_claves_viejas_con_el_color_mas_cercano(): void
    {
        $nota = $this->fila('notas', '#728a58');      // salvia
        $otra = $this->fila('notas', '#5f86a3');      // azul polvo: no existía, pasa a arena
        $tarea = $this->fila('tareas', '#c0677a');    // rosa -> durazno

        $this->artisan('migrate:rollback', ['--path' => self::MIGRACION])->assertExitCode(0);

        $this->assertSame('salvia', DB::table('notas')->where('id', $nota)->value('color'));
        $this->assertSame('arena', DB::table('notas')->where('id', $otra)->value('color'));
        $this->assertSame('durazno', DB::table('tareas')->where('id', $tarea)->value('color'));
    }

    // ---------- Validación ----------

    public function test_se_aceptan_los_diez_colores_y_se_rechazan_los_viejos_y_los_desconocidos(): void
    {
        foreach (ColorActividad::cases() as $color) {
            $this->postJson(route('notas.store'), ['contenido' => 'x', 'color' => $color->value])->assertSuccessful();
            $this->postJson(route('tareas.store'), ['titulo' => 'x', 'prioridad' => 'media', 'estado' => 'pendiente', 'color' => $color->value], ['X-Modal' => '1'])->assertSuccessful();
        }
        $this->assertSame(10, Nota::count());
        $this->assertSame(10, Tarea::count());

        foreach (['durazno', 'salvia', 'terracota', 'oliva', 'arena', 'fucsia', '#ffffff'] as $malo) {
            $this->postJson(route('notas.store'), ['contenido' => 'x', 'color' => $malo])->assertJsonValidationErrors('color');
            $this->postJson(route('tareas.store'), ['titulo' => 'x', 'prioridad' => 'media', 'estado' => 'pendiente', 'color' => $malo])->assertJsonValidationErrors('color');
        }
    }

    // ---------- Selectores ----------

    public function test_los_selectores_de_notas_y_tareas_ofrecen_sin_color_y_los_diez_colores(): void
    {
        $nota = $this->get(route('notas.create'))->assertOk()->getContent();
        $tablero = $this->get(route('tablero.index'))->assertOk()->getContent();

        // El tablero trae además el diálogo de notas (las notas también son tarjetas): dos selectores.
        foreach ([[$nota, 11], [$tablero, 22]] as [$html, $cuantos]) {
            $this->assertSame($cuantos, substr_count($html, 'name="color" value='));
            $this->assertStringContainsString('Sin color (usa el del contexto)', $html);
            // "Sin color" va primero.
            $this->assertLessThan(strpos($html, 'name="color" value="#'), strpos($html, 'name="color" value=""'));
            foreach (ColorActividad::cases() as $color) {
                $this->assertStringContainsString('name="color" value="'.$color->value.'"', $html);
            }
        }
    }

    public function test_el_selector_de_contextos_ofrece_sin_color_que_usa_el_del_padre(): void
    {
        $html = $this->get(route('contextos.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Sin color (usa el del contexto padre)', $html);
        $this->assertSame(11, substr_count($html, 'name="color" value='));
    }

    // ---------- Herencia en el árbol de contextos ----------

    public function test_un_contexto_sin_color_muestra_el_del_ancestro_mas_cercano_en_el_arbol(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera', 'color' => ColorActividad::Salvia->value]);
        $materia = Contexto::factory()->create(['nombre' => 'Redes', 'contexto_padre_id' => $carrera->id, 'color' => null]);
        Contexto::factory()->tema()->create(['nombre' => 'Modelo OSI', 'contexto_padre_id' => $materia->id, 'color' => null]);
        Contexto::factory()->entorno()->create(['nombre' => 'Sin nada', 'color' => null]);

        $html = $this->get(route('contextos.index'))->assertOk()->getContent();

        // El propio de Carrera y el heredado (con su pista) de Redes y Modelo OSI; "Sin nada" no lleva punto.
        $this->assertSame(3, substr_count($html, 'class="arbol-color'));
        $this->assertSame(2, preg_match_all('/arbol-color\s+es-heredado/', $html));
        $this->assertSame(2, substr_count($html, 'title="Usa el color de Carrera"'));
        $this->assertSame(3, substr_count($html, 'background: var(--actividad-salvia-acento)'));

        // Es solo de presentación: no se copia a la base.
        $this->assertNull(Contexto::where('nombre', 'Redes')->value('color'));
    }

    // ---------- Pista de color en los diálogos ----------

    public function test_la_pista_aparece_solo_cuando_la_nota_hereda_un_color(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera', 'color' => ColorActividad::Celeste->value]);
        $hija = Contexto::factory()->create(['nombre' => 'Redes', 'contexto_padre_id' => $carrera->id]);
        $sinColor = Contexto::factory()->create(['nombre' => 'Suelto']);

        $hereda = Nota::factory()->create(['contexto_id' => $hija->id, 'color' => null]);
        $propia = Nota::factory()->create(['contexto_id' => $hija->id, 'color' => ColorActividad::Rosa->value]);
        $nada = Nota::factory()->create(['contexto_id' => $sinColor->id, 'color' => null]);
        $bandeja = Nota::factory()->create(['contexto_id' => null, 'color' => null]);

        $this->get(route('notas.edit', $hereda))->assertOk()
            ->assertSee('Usa el color de Carrera')
            ->assertSee('--pista-fondo: var(--actividad-celeste-fondo)', false);

        foreach ([$propia, $nada, $bandeja] as $nota) {
            $html = $this->get(route('notas.edit', $nota))->assertOk()->getContent();
            $this->assertStringNotContainsString('Usa el color de', $html);
            $this->assertMatchesRegularExpression('/<p class="color-pista" data-color-pista\s+hidden/', $html);
        }
    }

    public function test_las_opciones_del_selector_de_contexto_llevan_su_color_efectivo_para_actualizar_la_pista(): void
    {
        $carrera = Contexto::factory()->entorno()->create(['nombre' => 'Carrera', 'color' => ColorActividad::Celeste->value]);
        Contexto::factory()->create(['nombre' => 'Redes', 'contexto_padre_id' => $carrera->id]);
        Contexto::factory()->create(['nombre' => 'Suelto']);

        foreach ([[route('notas.create'), 2], [route('tablero.index'), 4]] as [$url, $cuantos]) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame($cuantos, substr_count($html, 'data-color-heredado="celeste" data-color-origen="Carrera"'), $url);
            $this->assertStringContainsString('data-color-pista', $html);
        }
    }
}
