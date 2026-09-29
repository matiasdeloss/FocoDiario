<?php

namespace App\Services\Agenda;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/** Cálculos de la semana de la agenda: las semanas empiezan el lunes. */
class Semana
{
    /** Lunes de la semana de cualquier fecha (o de hoy si no hay fecha o no es válida). */
    public static function lunesDe(CarbonInterface|string|null $fecha = null): CarbonImmutable
    {
        try {
            $base = $fecha === null || $fecha === '' ? CarbonImmutable::today() : CarbonImmutable::parse($fecha);
        } catch (Throwable) {
            $base = CarbonImmutable::today();
        }

        return $base->startOfDay()->startOfWeek(CarbonInterface::MONDAY);
    }

    /** True si el texto es una fecha AAAA-MM-DD real (por ejemplo, no 2026-02-31). */
    public static function esFechaValida(?string $fecha): bool
    {
        if ($fecha === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }

        [$anio, $mes, $dia] = array_map('intval', explode('-', $fecha));

        return checkdate($mes, $dia, $anio);
    }
}
