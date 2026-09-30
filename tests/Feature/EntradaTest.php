<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Cuenta admin, comando foco:usuario y cabeceras de seguridad. La entrada en sí está en CuentaTest. */
class EntradaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $conSesion = false;

    public function test_las_respuestas_llevan_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->get(route('hoy'));

        $respuesta->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $respuesta->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp);
    }

    public function test_el_seeder_crea_la_cuenta_admin_y_se_puede_entrar(): void
    {
        $this->seed(AdminSeeder::class);

        $this->postJson(route('login.store'), ['email' => 'admin@focodiario.com', 'password' => 'password'])->assertOk();

        $this->assertAuthenticatedAs(User::where('email', 'admin@focodiario.com')->firstOrFail());
    }

    public function test_volver_a_sembrar_no_pisa_una_contrasena_cambiada(): void
    {
        $this->seed(AdminSeeder::class);
        User::where('email', 'admin@focodiario.com')->firstOrFail()->update(['password' => 'nueva-clave']);

        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@focodiario.com')->count());
        $this->assertTrue(Hash::check('nueva-clave', User::where('email', 'admin@focodiario.com')->value('password')));
    }

    public function test_el_comando_crea_la_cuenta_y_despues_cambia_la_contrasena(): void
    {
        $this->artisan('foco:usuario', ['email' => 'Yo@Foco.test'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-bien-larga')
            ->expectsQuestion('Repetila', 'clave-bien-larga')
            ->expectsOutput('Cuenta creada: yo@foco.test.')
            ->assertSuccessful();

        $this->artisan('foco:usuario', ['email' => 'yo@foco.test'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'otra-clave-larga')
            ->expectsQuestion('Repetila', 'otra-clave-larga')
            ->expectsOutput('Contraseña actualizada para yo@foco.test.')
            ->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('otra-clave-larga', User::first()->password));
        $this->assertFalse(User::first()->es_invitado);
    }

    public function test_el_comando_pide_al_menos_8_caracteres(): void
    {
        $this->artisan('foco:usuario', ['email' => 'yo@foco.test'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', '1234567')
            ->expectsQuestion('Repetila', '1234567')
            ->expectsOutput('La contraseña tiene que tener al menos 8 caracteres.')
            ->assertFailed();

        $this->artisan('foco:usuario', ['email' => 'yo@foco.test'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', '12345678')
            ->expectsQuestion('Repetila', '12345678')
            ->assertSuccessful();
    }

    public function test_el_comando_rechaza_contrasenas_cortas_o_distintas(): void
    {
        $this->artisan('foco:usuario', ['email' => 'yo@foco.test'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'corta')
            ->expectsQuestion('Repetila', 'distinta')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
