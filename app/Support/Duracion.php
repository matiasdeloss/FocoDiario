<?php

namespace App\Support;

class Duracion
{
    /** Convierte minutos en un texto corto: "2 h 30 min", "45 min", "3 h". */
    public static function formatear(int|float $minutos): string
    {
        $minutos = (int) round($minutos);
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return match (true) {
            $horas === 0 => $resto.' min',
            $resto === 0 => $horas.' h',
            default => $horas.' h '.$resto.' min',
        };
    }
}
