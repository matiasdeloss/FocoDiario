<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Texto corto de "cuándo": "Hoy, 18:00", "Mañana, 09:00", "Vie, 12:00" o "12 oct, 09:00". */
final class CuandoCorto
{
    public static function para(CarbonInterface $fecha, ?CarbonInterface $ahora = null): string
    {
        $ahora ??= now();
        $hora = $fecha->format('H:i');
        $dias = (int) $ahora->copy()->startOfDay()->diffInDays($fecha->copy()->startOfDay(), false);

        return match (true) {
            $dias === 0 => "Hoy, {$hora}",
            $dias === 1 => "Mañana, {$hora}",
            $dias === -1 => "Ayer, {$hora}",
            $dias > 1 && $dias < 7 => ucfirst(rtrim($fecha->translatedFormat('D'), '.')).", {$hora}",
            default => rtrim($fecha->translatedFormat('j M'), '.').", {$hora}",
        };
    }
}
