<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert as PHPUnit;

abstract class TestCase extends BaseTestCase
{
    /** La app entera pide sesión: los tests entran con un usuario, salvo los que prueban la entrada. */
    protected bool $conSesion = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Los tests no dependen de haber compilado los assets con npm run build.
        $this->withoutVite();

        // $respuesta->assertAvisoHtmx('Tarea creada.', 'exito'): el toast viaja en el encabezado HX-Trigger (evento mostrar-aviso).
        TestResponse::macro('assertAvisoHtmx', function (string $texto, string $tipo = 'exito', ?string $detalle = null) {
            /** @var TestResponse $this */
            $encabezado = $this->headers->get('HX-Trigger');

            PHPUnit::assertNotNull($encabezado, 'La respuesta no trae el encabezado HX-Trigger.');

            $avisos = json_decode($encabezado, true)['mostrar-aviso'] ?? [];
            $avisos = array_is_list($avisos) ? $avisos : [$avisos];

            PHPUnit::assertContainsEquals(['tipo' => $tipo, 'texto' => $texto, 'detalle' => $detalle], $avisos, "No hay un aviso {$tipo} con el texto \"{$texto}\" en HX-Trigger: {$encabezado}");

            return $this;
        });

        if ($this->conSesion && $this->usaBaseDeDatos()) {
            $this->actingAs(User::factory()->create());
        }
    }

    private function usaBaseDeDatos(): bool
    {
        return in_array(RefreshDatabase::class, class_uses_recursive($this), true);
    }
}
