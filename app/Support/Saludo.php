<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Saludo de la pantalla Hoy según la hora (en la zona horaria de la aplicación). */
final class Saludo
{
    public static function para(CarbonInterface $ahora): string
    {
        return match (true) {
            $ahora->hour < 6 => 'Buenas noches',
            $ahora->hour < 12 => 'Buenos días',
            $ahora->hour < 20 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }
}
