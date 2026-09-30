<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * La app se usa sin cuenta: quien llega sin sesión entra como invitado (un usuario anónimo) y queda
 * recordado en ese navegador con la cookie de "recordarme". Crear una cuenta lo convierte en cuenta real.
 */
class IdentificarInvitado
{
    /** Invitados nuevos por IP y por hora: frena a bots y scripts que generen usuarios sin fin. */
    public const MAXIMO_POR_HORA = 20;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $this->registrarUso(Auth::user());

            return $next($request);
        }

        $clave = 'invitados:'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAXIMO_POR_HORA)) {
            abort(429, 'Se abrieron demasiadas sesiones nuevas desde esta red. Probá de nuevo en un rato.');
        }

        RateLimiter::hit($clave, 3600);
        Auth::login(User::crearInvitado(), remember: true);

        return $next($request);
    }

    /** Última vez que se usó la cuenta (a lo sumo una escritura por hora): la limpieza de invitados se basa en esto. */
    private function registrarUso(User $usuario): void
    {
        if ($usuario->ultimo_uso_en === null || $usuario->ultimo_uso_en->lt(now()->subHour())) {
            $usuario->forceFill(['ultimo_uso_en' => now()])->saveQuietly();
        }
    }
}
