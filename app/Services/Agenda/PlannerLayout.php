<?php

namespace App\Services\Agenda;

use App\Enums\ZonaSemana;
use App\Models\Ajuste;
use App\Models\Caja;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;

/**
 * Posición y tamaño de las tarjetas del planner semanal (los siete días y las cajas Notas y Pendiente).
 * Cada semana tiene su propia disposición por usuario: se guarda como JSON en `ajustes` bajo `planner.layout.AAAA-MM-DD`
 * (el lunes de la semana). Sin disposición guardada (o con claves faltantes) se usa la de fábrica, que reproduce el planner clásico.
 * La antigua clave global `planner.layout` ya no se lee: ninguna semana hereda esa disposición.
 */
class PlannerLayout
{
    /** Prefijo de la clave por semana; la clave sin sufijo es la antigua disposición global, que ya no se usa. */
    public const AJUSTE = 'planner.layout';

    /** Clave del ajuste de una semana: cualquier fecha de la semana se normaliza a su lunes. */
    public static function clave(CarbonInterface|string $semana): string
    {
        return self::AJUSTE.'.'.Semana::lunesDe($semana)->toDateString();
    }

    /** @return list<string> Los siete días y las zonas de la semana, con las claves de su fuente única. */
    public static function claves(): array
    {
        return [...PlannerSemanal::CLAVES_DIA, ...array_column(ZonaSemana::cases(), 'value')];
    }

    /**
     * Límites de la posición y el tamaño de una tarjeta (reglas de Laravel por campo): los únicos que existen,
     * tanto para validar lo que llega como para releer lo guardado.
     *
     * @return array<string, list<string>>
     */
    public static function reglasTarjeta(): array
    {
        return [
            'x' => ['required', 'integer', 'min:0', 'max:'.(Caja::COLUMNAS - 1)],
            'y' => ['required', 'integer', 'min:0', 'max:'.Caja::MAX_FILA],
            'ancho' => ['required', 'integer', 'min:1', 'max:'.Caja::COLUMNAS],
            'alto' => ['required', 'integer', 'min:1', 'max:'.Caja::MAX_ALTO],
        ];
    }

    /** Una tarjeta no puede salirse de las columnas de la grilla. */
    public static function seSale(int $x, int $ancho): bool
    {
        return $x + $ancho > Caja::COLUMNAS;
    }

    /**
     * Disposición de fábrica en la grilla de 12 columnas: tres columnas de cuatro; de lunes a viernes
     * ocupan dos "medias filas" y sábado y domingo una; Notas y Pendiente van abajo, a media hoja cada una.
     *
     * @return array<string, array{x: int, y: int, ancho: int, alto: int}>
     */
    public static function defecto(): array
    {
        return [
            'lunes' => ['x' => 0, 'y' => 0, 'ancho' => 4, 'alto' => 14],
            'martes' => ['x' => 4, 'y' => 0, 'ancho' => 4, 'alto' => 14],
            'miercoles' => ['x' => 8, 'y' => 0, 'ancho' => 4, 'alto' => 14],
            'jueves' => ['x' => 0, 'y' => 14, 'ancho' => 4, 'alto' => 14],
            'viernes' => ['x' => 4, 'y' => 14, 'ancho' => 4, 'alto' => 14],
            'sabado' => ['x' => 8, 'y' => 14, 'ancho' => 4, 'alto' => 7],
            'domingo' => ['x' => 8, 'y' => 21, 'ancho' => 4, 'alto' => 7],
            'notas' => ['x' => 0, 'y' => 28, 'ancho' => 6, 'alto' => 14],
            'pendiente' => ['x' => 6, 'y' => 28, 'ancho' => 6, 'alto' => 14],
        ];
    }

    /**
     * La disposición del usuario sobre la de fábrica. Todo lo guardado se vuelve a validar al leerlo:
     * un valor corrupto o fuera de la grilla vuelve a la posición de fábrica de esa tarjeta.
     *
     * @return array<string, array{x: int, y: int, ancho: int, alto: int}>
     */
    public static function obtener(CarbonInterface|string $semana): array
    {
        $guardado = json_decode((string) Ajuste::obtener(self::clave($semana), '{}'), true);
        $guardado = is_array($guardado) ? $guardado : [];

        $resultado = self::defecto();

        foreach ($resultado as $clave => $porDefecto) {
            if (isset($guardado[$clave]) && self::esValida($guardado[$clave])) {
                $resultado[$clave] = [
                    'x' => (int) $guardado[$clave]['x'],
                    'y' => (int) $guardado[$clave]['y'],
                    'ancho' => (int) $guardado[$clave]['ancho'],
                    'alto' => (int) $guardado[$clave]['alto'],
                ];
            }
        }

        return $resultado;
    }

    /**
     * Guarda las tarjetas indicadas de esa semana (las demás conservan su posición actual en ella).
     *
     * @param  array<string, array{x: int, y: int, ancho: int, alto: int}>  $tarjetas  por clave
     */
    public static function guardar(CarbonInterface|string $semana, array $tarjetas): void
    {
        $nuevo = array_merge(self::obtener($semana), array_intersect_key($tarjetas, array_flip(self::claves())));

        Ajuste::guardar(self::clave($semana), json_encode($nuevo, JSON_UNESCAPED_UNICODE));
    }

    /** Borra la disposición propia de esa semana: vuelve a la de fábrica. */
    public static function restablecer(CarbonInterface|string $semana): void
    {
        Ajuste::query()->where('clave', self::clave($semana))->delete();
    }

    private static function esValida(mixed $tarjeta): bool
    {
        if (! is_array($tarjeta)) {
            return false;
        }

        foreach (['x', 'y', 'ancho', 'alto'] as $campo) {
            if (! isset($tarjeta[$campo]) || ! is_int($tarjeta[$campo])) {
                return false;
            }
        }

        return ! self::seSale($tarjeta['x'], $tarjeta['ancho'])
            && ! Validator::make($tarjeta, self::reglasTarjeta())->fails();
    }
}
