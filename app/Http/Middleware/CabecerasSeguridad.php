<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de todas las respuestas web. La CSP solo deja scripts propios (y los inline
 * que llevan el nonce de Vite); con el servidor de desarrollo de Vite corriendo se suma su origen.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $respuesta = $next($request);

        $respuesta->headers->set('Content-Security-Policy', $this->politica($nonce));
        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure() && app()->isProduction()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }

    private function politica(string $nonce): string
    {
        $vite = $this->origenViteDev();
        $viteWs = $vite ? preg_replace('#^http#', 'ws', $vite) : null;

        $directivas = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", $vite],
            // Bootstrap, GridStack, FullCalendar y HTMX ponen estilos inline.
            'style-src' => ["'self'", "'unsafe-inline'", $vite],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'", 'data:', $vite],
            'connect-src' => ["'self'", $vite, $viteWs],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directivas)
            ->map(fn (array $fuentes, string $nombre) => $nombre.' '.implode(' ', array_filter($fuentes)))
            ->implode('; ');
    }

    /** Origen del servidor de Vite en desarrollo (public/hot), o null si no está corriendo. */
    private function origenViteDev(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $url = rtrim(trim((string) @file_get_contents(public_path('hot'))), '/');

        if ($url === '') {
            return null;
        }

        // La CSP no acepta direcciones IPv6 como fuente (http://[::1]:5173 se ignora y el CSS no carga):
        // en ese caso, y solo en desarrollo, se permite el esquema entero.
        if (str_contains((string) parse_url($url, PHP_URL_HOST), ':') || str_contains($url, '[')) {
            return 'http:';
        }

        return $url;
    }
}
