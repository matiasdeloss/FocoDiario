<?php

namespace Tests\Feature;

use App\Enums\EstadoSesion;
use App\Enums\EstadoTarea;
use App\Http\Middleware\IdentificarInvitado;
use App\Models\Ajuste;
use App\Models\Caja;
use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Invitados, crear cuenta, entrar (con fusión), salir y limpieza de invitados. */
class CuentaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $conSesion = false;

    private function cuenta(string $email = 'yo@foco.test'): User
    {
        return User::factory()->create(['email' => $email, 'password' => Hash::make('una-clave-larga')]);
    }

    /** Crea datos a nombre de $usuario (el trait completa user_id con el usuario de la sesión). */
    private function como(User $usuario, callable $crear): mixed
    {
        $this->actingAs($usuario);

        return $crear();
    }

    // ---------- Invitado ----------

    public function test_la_primera_visita_entra_como_invitado_con_sus_columnas(): void
    {
        $this->get(route('hoy'))->assertOk()->assertSee('Guardar mis datos')->assertSee('id="dialogo-cuenta"', false);

        $invitado = auth()->user();

        $this->assertTrue($invitado->es_invitado);
        $this->assertNull($invitado->email);
        $this->assertSame(['Pendiente', 'En progreso', 'Completada'], ColumnaTablero::ordenadas()->pluck('nombre')->all());

        // Siguiente pedido: el mismo invitado, no uno nuevo.
        $this->get(route('notas.index'))->assertOk();
        $this->assertSame(1, User::count());
    }

    public function test_lo_que_guarda_el_invitado_queda_a_su_nombre(): void
    {
        $this->get(route('hoy'));
        $invitado = auth()->user();

        $this->postJson(route('notas.store'), ['titulo' => 'Idea', 'contenido' => 'Algo'])->assertOk();

        $this->assertSame($invitado->id, Nota::withoutGlobalScopes()->sole()->user_id);
    }

    public function test_hay_un_limite_de_invitados_nuevos_por_ip(): void
    {
        for ($i = 0; $i < IdentificarInvitado::MAXIMO_POR_HORA; $i++) {
            RateLimiter::hit('invitados:127.0.0.1', 3600);
        }

        $this->get(route('hoy'))->assertStatus(429);
        $this->assertSame(0, User::count());
    }

    public function test_entrar_ya_no_es_una_pantalla_sino_el_modal(): void
    {
        $this->get(route('login'))->assertRedirect(route('hoy', ['cuenta' => 'entrar']));

        $this->get(route('hoy', ['cuenta' => 'crear']))->assertSee('data-abrir-al-cargar="crear"', false);
    }

    public function test_una_cuenta_no_ve_el_modal_y_puede_salir(): void
    {
        $this->actingAs($this->cuenta())->get(route('hoy'))
            ->assertDontSee('id="dialogo-cuenta"', false)
            ->assertDontSee('Guardar mis datos')
            ->assertSee(route('logout'), false);
    }

    // ---------- Crear cuenta ----------

    public function test_crear_cuenta_convierte_al_invitado_y_conserva_sus_datos(): void
    {
        $this->get(route('hoy'));
        $invitado = auth()->user();
        $this->postJson(route('notas.store'), ['titulo' => 'Mi nota', 'contenido' => 'x'])->assertOk();

        $this->postJson(route('cuenta.store'), [
            'nombre' => 'Mati', 'email' => 'Mati@Foco.test ', 'password' => 'clave-segura', 'password_confirmation' => 'clave-segura',
        ])->assertOk()->assertJson(['mensaje' => 'Cuenta creada. Tus datos quedaron guardados en ella.']);

        $cuenta = $invitado->fresh();

        $this->assertFalse($cuenta->es_invitado);
        $this->assertSame('mati@foco.test', $cuenta->email);
        $this->assertSame('Mati', $cuenta->name);
        $this->assertTrue(Hash::check('clave-segura', $cuenta->password));
        $this->assertAuthenticatedAs($cuenta);
        $this->assertSame(1, User::count());
        $this->assertSame($cuenta->id, Nota::withoutGlobalScopes()->sole()->user_id);
    }

    public function test_crear_cuenta_valida_en_espanol(): void
    {
        $this->cuenta('ocupado@foco.test');
        $this->get(route('hoy'));

        $this->postJson(route('cuenta.store'), ['email' => 'ocupado@foco.test', 'password' => 'corta', 'password_confirmation' => 'otra'])
            ->assertJsonValidationErrors([
                'email' => 'Ya existe una cuenta con ese email. Probá iniciar sesión.',
                'password' => 'Las contraseñas no coinciden.',
            ]);

        $this->assertTrue(auth()->user()->fresh()->es_invitado);
    }

    // ---------- Entrar ----------

    public function test_entrar_con_credenciales_incorrectas_sigue_como_invitado(): void
    {
        $this->cuenta();
        $this->get(route('hoy'));
        $invitado = auth()->user();

        $this->postJson(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'otra'])
            ->assertJsonValidationErrors(['email' => 'El email o la contraseña no son correctos.']);

        $this->assertAuthenticatedAs($invitado);
    }

    public function test_bloquea_tras_varios_intentos_fallidos(): void
    {
        $this->cuenta();
        $this->get(route('hoy'));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'mal']);
        }

        $this->postJson(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'una-clave-larga'])
            ->assertJsonValidationErrors('email');

        $this->assertTrue(auth()->user()->es_invitado);
    }

    public function test_entrar_sin_datos_de_invitado_no_deja_rastros(): void
    {
        $cuenta = $this->cuenta();
        $this->get(route('hoy'));

        $this->postJson(route('login.store'), ['email' => 'YO@foco.test', 'password' => 'una-clave-larga'])
            ->assertOk()->assertJson(['mensaje' => 'Iniciaste sesión. Lo que hiciste como invitado se sumó a tu cuenta.']);

        $this->assertAuthenticatedAs($cuenta);
        $this->assertSame(1, User::count());
        // Las columnas de fábrica del invitado se unieron con las de la cuenta: no quedan repetidas.
        $this->assertSame(3, ColumnaTablero::withoutGlobalScopes()->count());
    }

    public function test_al_entrar_lo_del_invitado_se_suma_a_la_cuenta(): void
    {
        $cuenta = $this->cuenta();
        $this->como($cuenta, function () {
            Contexto::factory()->create(['nombre' => 'Carrera']);
            Caja::factory()->create(['fecha' => '2026-10-01', 'x' => 0, 'y' => 0, 'alto' => 8]);
            Ajuste::guardar(Ajuste::TITULO_PLANNER, 'Mi planner');
        });

        $invitado = User::factory()->invitado()->create();
        [$nota, $tarea, $carrera, $hija, $revision, $caja, $sesion] = $this->como($invitado, function () {
            $carrera = Contexto::factory()->create(['nombre' => 'Carrera']);
            Ajuste::guardar(Ajuste::TITULO_PLANNER, 'El del invitado');
            Ajuste::guardar('otro.ajuste', 'se conserva');

            return [
                Nota::factory()->create(['titulo' => 'Nota del invitado', 'contexto_id' => $carrera->id]),
                Tarea::factory()->create(['estado' => EstadoTarea::Pendiente, 'contexto_id' => $carrera->id]),
                $carrera,
                Contexto::factory()->create(['nombre' => 'Programación', 'contexto_padre_id' => $carrera->id]),
                ColumnaTablero::create(['nombre' => 'Revisión', 'categoria' => EstadoTarea::EnProgreso, 'posicion' => 9]),
                Caja::factory()->create(['fecha' => '2026-10-01', 'x' => 0, 'y' => 2, 'alto' => 4]),
                SesionEstudio::factory()->enCurso()->create(),
            ];
        });

        $this->postJson(route('login.store'), ['email' => 'yo@foco.test', 'password' => 'una-clave-larga'])->assertOk();

        $this->assertAuthenticatedAs($cuenta);
        $this->assertNull($invitado->fresh());

        // Todo pasó a la cuenta.
        $this->assertSame($cuenta->id, Nota::find($nota->id)?->user_id);
        $this->assertSame(['Carrera', 'Carrera (invitado)'], Contexto::whereNull('contexto_padre_id')->orderBy('id')->pluck('nombre')->all());
        $this->assertSame($carrera->id, Contexto::find($hija->id)->contexto_padre_id);
        // La tarea del invitado sigue apuntando a su contexto (que se renombró, pero conserva el id).
        $this->assertSame($carrera->id, Tarea::find($tarea->id)->contexto_id);
        $this->assertSame('Carrera (invitado)', Tarea::find($tarea->id)->contexto->nombre);

        // Columnas: las de fábrica se unieron; "Revisión" se agregó al final.
        $this->assertSame(['Pendiente', 'En progreso', 'Completada', 'Revisión'], ColumnaTablero::ordenadas()->pluck('nombre')->all());
        $this->assertSame(ColumnaTablero::where('nombre', 'Pendiente')->value('id'), Tarea::find($tarea->id)->columna_id);
        $this->assertNotNull(ColumnaTablero::find($revision->id));

        // La caja del invitado quedó debajo de la de la cuenta ese día.
        $this->assertSame(10, Caja::find($caja->id)->y);

        // Ajustes: manda la cuenta; los que la cuenta no tenía se conservan.
        $this->assertSame('Mi planner', Ajuste::tituloPlanner());
        $this->assertSame('se conserva', Ajuste::obtener('otro.ajuste'));

        // La sesión de estudio que quedó abierta se dio por terminada.
        $this->assertSame(EstadoSesion::Finalizada, SesionEstudio::find($sesion->id)->estado);
    }

    // ---------- Salir ----------

    public function test_salir_deja_un_invitado_nuevo_y_vacio(): void
    {
        $cuenta = $this->cuenta();
        $this->como($cuenta, fn () => Nota::factory()->create(['titulo' => 'Privada']));

        $this->post(route('logout'))->assertRedirect(route('hoy'));
        $this->assertGuest();

        $this->get(route('notas.index'))->assertOk()->assertDontSee('Privada');
        $this->assertTrue(auth()->user()->es_invitado);
        $this->assertNotSame($cuenta->id, auth()->id());
    }

    public function test_un_invitado_no_puede_salir_y_perder_sus_datos(): void
    {
        $this->get(route('hoy'));
        $invitado = auth()->user();

        $this->post(route('logout'))->assertRedirect(route('hoy'));

        $this->assertAuthenticatedAs($invitado);
    }

    public function test_el_token_csrf_se_puede_renovar(): void
    {
        $this->actingAs($this->cuenta())->getJson(route('csrf'))->assertOk()->assertJsonStructure(['token']);
    }

    // ---------- Limpieza ----------

    public function test_la_limpieza_borra_invitados_abandonados_o_vacios_y_nada_mas(): void
    {
        $viejo = User::factory()->invitado()->create(['ultimo_uso_en' => now()->subDays(91)]);
        $this->como($viejo, fn () => Nota::factory()->create());

        $vacio = User::factory()->invitado()->create(['created_at' => now()->subDays(3)]);

        $conDatos = User::factory()->invitado()->create(['created_at' => now()->subDays(3)]);
        $this->como($conDatos, fn () => Tarea::factory()->create());

        $nuevo = User::factory()->invitado()->create();
        $cuentaVieja = User::factory()->create(['ultimo_uso_en' => now()->subYears(2)]);

        $this->artisan('foco:limpiar-invitados')->expectsOutput('Invitados borrados: 1 sin uso, 1 sin datos.')->assertSuccessful();

        $this->assertNull($viejo->fresh());
        $this->assertNull($vacio->fresh());
        $this->assertNotNull($conDatos->fresh());
        $this->assertNotNull($nuevo->fresh());
        $this->assertNotNull($cuentaVieja->fresh());
        // Sus datos se fueron con ellos (cascada).
        $this->assertSame(0, Nota::withoutGlobalScopes()->where('user_id', $viejo->id)->count());
        $this->assertSame(0, ColumnaTablero::withoutGlobalScopes()->where('user_id', $vacio->id)->count());
    }
}
