<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EntradaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $conSesion = false;

    private function usuario(): User
    {
        return User::factory()->create(['email' => 'yo@foco.test', 'password' => Hash::make('una-clave-larga')]);
    }

    public function test_sin_sesion_toda_la_app_lleva_a_la_entrada(): void
    {
        foreach (['/', '/tareas', '/agenda', '/notas', '/calendario', '/estudio', '/csrf'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_sin_sesion_un_pedido_json_recibe_401(): void
    {
        $this->patchJson('/tablero/columnas/1/mover', ['direccion' => 'derecha'])->assertUnauthorized();
    }

    public function test_sin_sesion_un_pedido_htmx_redirige_la_pagina_entera(): void
    {
        $this->delete('/notas/1', [], ['HX-Request' => 'true'])
            ->assertUnauthorized()
            ->assertHeader('HX-Redirect', route('login'));
    }

    public function test_la_pantalla_de_entrada_se_muestra(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Entrar');
    }

    public function test_entra_con_credenciales_correctas(): void
    {
        $usuario = $this->usuario();

        $this->post(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'una-clave-larga'])
            ->assertRedirect(route('hoy'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_vuelve_a_la_pagina_que_se_pidio_antes_de_entrar(): void
    {
        $this->usuario();

        $this->get('/notas');
        $this->post(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'una-clave-larga'])
            ->assertRedirect('/notas');
    }

    public function test_con_credenciales_incorrectas_no_entra_y_no_dice_cual_fallo(): void
    {
        $this->usuario();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'otra'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'El email o la contraseña no son correctos.']);

        $this->assertGuest();
    }

    public function test_bloquea_tras_varios_intentos_fallidos(): void
    {
        $this->usuario();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'mal']);
        }

        $this->post(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'una-clave-larga'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
    }

    public function test_con_sesion_la_entrada_lleva_a_hoy(): void
    {
        $this->actingAs($this->usuario())->get(route('login'))->assertRedirect(route('hoy'));
    }

    public function test_salir_cierra_la_sesion(): void
    {
        $this->actingAs($this->usuario())->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_el_token_csrf_se_puede_renovar(): void
    {
        $this->actingAs($this->usuario())
            ->getJson(route('csrf'))
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_las_respuestas_llevan_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->get(route('login'));

        $respuesta->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $respuesta->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp);
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
    }

    public function test_el_seeder_crea_la_cuenta_admin_y_se_puede_entrar(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post(route('login.store'), ['email' => 'admin@focodiario.com', 'password' => 'password'])
            ->assertRedirect(route('hoy'));

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
