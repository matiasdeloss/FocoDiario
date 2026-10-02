<?php

namespace App\Support;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/**
 * Avisos (toasts) del servidor. Todo mensaje al usuario sale por acá, con un tipo:
 * exito (por defecto), info, aviso, error y recordatorio.
 *
 * - Redirecciones y respuestas JSON que recargan la página: se guarda en la sesión flash con la clave
 *   `estado` (el texto), `estado_tipo` y, si hay, `estado_detalle`. El layout los deja en un nodo oculto
 *   que resources/js/avisos.js convierte en aviso flotante.
 * - Respuestas HTMX: el encabezado `HX-Trigger` dispara el evento `mostrar-aviso` con el mismo contenido.
 *
 * Un texto que empieza con "No se pudo" es un error aunque no se diga.
 */
final class Aviso
{
    public const TIPOS = ['exito', 'info', 'aviso', 'error', 'recordatorio'];

    public const TIPO_POR_DEFECTO = 'exito';

    /** El tipo pedido si es válido; si no, `error` para "No se pudo…" y `exito` para el resto. */
    public static function tipoDe(string $texto, ?string $tipo = null): string
    {
        if ($tipo !== null && in_array($tipo, self::TIPOS, true)) {
            return $tipo;
        }

        return str_starts_with($texto, 'No se pudo') ? 'error' : self::TIPO_POR_DEFECTO;
    }

    /**
     * Datos de sesión flash para `->with(...)` de una redirección.
     *
     * @return array{estado: string, estado_tipo: string, estado_detalle?: string}
     */
    public static function flash(string $texto, ?string $tipo = null, ?string $detalle = null): array
    {
        return array_filter([
            'estado' => $texto,
            'estado_tipo' => self::tipoDe($texto, $tipo),
            'estado_detalle' => $detalle,
        ], fn ($valor) => $valor !== null);
    }

    /** Deja el aviso en la sesión flash sin armar la respuesta (respuestas JSON tras las que la página se recarga). */
    public static function guardar(string $texto, ?string $tipo = null, ?string $detalle = null): void
    {
        foreach (self::flash($texto, $tipo, $detalle) as $clave => $valor) {
            session()->flash($clave, $valor);
        }
    }

    /**
     * Lo que recibe el navegador: { tipo, texto, detalle }.
     *
     * @return array{tipo: string, texto: string, detalle: ?string}
     */
    public static function datos(string $texto, ?string $tipo = null, ?string $detalle = null): array
    {
        return ['tipo' => self::tipoDe($texto, $tipo), 'texto' => $texto, 'detalle' => $detalle];
    }

    /**
     * Agrega el aviso al encabezado HX-Trigger de una respuesta (respeta otros eventos ya puestos).
     * Acepta una vista y la convierte en respuesta. El JSON va con \uXXXX: los encabezados HTTP no llevan UTF-8.
     */
    public static function enHtmx(View|RespuestaHttp $respuesta, string $texto, ?string $tipo = null, ?string $detalle = null): RespuestaHttp
    {
        if ($respuesta instanceof View) {
            $respuesta = new Response($respuesta->render());
        }

        $eventos = json_decode((string) $respuesta->headers->get('HX-Trigger'), true);
        $eventos = is_array($eventos) ? $eventos : [];
        $nuevo = self::datos($texto, $tipo, $detalle);
        $previo = $eventos['mostrar-aviso'] ?? null;

        // Un segundo aviso en la misma respuesta pasa a ser un arreglo.
        $eventos['mostrar-aviso'] = $previo === null
            ? $nuevo
            : [...(array_is_list($previo) ? $previo : [$previo]), $nuevo];

        $respuesta->headers->set('HX-Trigger', json_encode($eventos, JSON_THROW_ON_ERROR));

        return $respuesta;
    }
}
