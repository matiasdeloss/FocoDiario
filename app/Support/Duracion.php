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

    /**
     * Convierte segundos en un texto corto: "5 s", "1 min 30 s", "45 min", "2 h 05 min", "3 h".
     * Desde una hora se redondea al minuto.
     */
    public static function formatearSegundos(int|float $segundos): string
    {
        $segundos = max(0, (int) round($segundos));

        if ($segundos < 60) {
            return $segundos.' s';
        }

        if ($segundos < 3600) {
            $resto = $segundos % 60;
            $texto = intdiv($segundos, 60).' min';

            return $resto === 0 ? $texto : $texto.' '.$resto.' s';
        }

        $minutos = (int) round($segundos / 60);
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto === 0 ? $horas.' h' : sprintf('%d h %02d min', $horas, $resto);
    }
}
