<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** La app entera pide sesión: los tests entran con un usuario, salvo los que prueban la entrada. */
    protected bool $conSesion = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Los tests no dependen de haber compilado los assets con npm run build.
        $this->withoutVite();

        if ($this->conSesion && $this->usaBaseDeDatos()) {
            $this->actingAs(User::factory()->create());
        }
    }

    private function usaBaseDeDatos(): bool
    {
        return in_array(RefreshDatabase::class, class_uses_recursive($this), true);
    }
}
