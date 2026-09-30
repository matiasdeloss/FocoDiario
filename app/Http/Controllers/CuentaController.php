<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistroRequest;
use App\Services\Cuentas\FusionarInvitado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Invitado y cuentas. No hay pantalla de entrada: todo pasa por el modal de cuenta (layouts/_dialogo-cuenta).
 * El modal manda JSON; sin JS, los formularios redirigen como siempre.
 */
class CuentaController extends Controller
{
    /** Los enlaces viejos a /entrar abren el modal sobre Hoy. */
    public function create(): RedirectResponse
    {
        return redirect()->route('hoy', ['cuenta' => 'entrar']);
    }

    /** Iniciar sesión. Si quien entra era invitado, lo que hizo se suma a la cuenta. */
    public function entrar(LoginRequest $request, FusionarInvitado $fusion): RedirectResponse|JsonResponse
    {
        $anterior = $request->user();

        $request->autenticar();

        $cuenta = Auth::user();
        $sumado = $anterior !== null && $anterior->es_invitado && ! $anterior->is($cuenta);

        if ($sumado) {
            $fusion->ejecutar($anterior, $cuenta);
        }

        $request->session()->regenerate();

        return $this->listo($request, $sumado
            ? 'Iniciaste sesión. Lo que hiciste como invitado se sumó a tu cuenta.'
            : 'Iniciaste sesión.');
    }

    /** Crear cuenta: el invitado se convierte en cuenta y conserva todo (no se copia nada). */
    public function registrar(RegistroRequest $request): RedirectResponse|JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario->es_invitado) {
            return $this->listo($request, 'Ya tenés una cuenta iniciada.');
        }

        $usuario->forceFill([
            'name' => $request->validated('nombre') ?: strstr($request->validated('email'), '@', true),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'es_invitado' => false,
        ])->save();

        // Sesión y cookie de "recordarme" nuevas para la cuenta.
        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return $this->listo($request, 'Cuenta creada. Tus datos quedaron guardados en ella.');
    }

    /** Salir de la cuenta: se sigue como un invitado nuevo y vacío. Un invitado no puede "salir" (perdería todo). */
    public function salir(Request $request): RedirectResponse
    {
        if ($request->user()?->es_invitado) {
            return redirect()->route('hoy');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('hoy')->with('estado', 'Saliste de tu cuenta. Ahora estás como invitado.');
    }

    /** Token CSRF nuevo para las páginas que quedaron abiertas más que la sesión (lo pide resources/js/red.js ante un 419). */
    public function token(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()]);
    }

    private function listo(Request $request, string $mensaje): RedirectResponse|JsonResponse
    {
        $request->session()->flash('estado', $mensaje);

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje]);
        }

        return redirect()->intended(route('hoy'));
    }
}
