<?php

namespace App\Enums;

/** Cajas del planner semanal que pertenecen a la semana y no a un día. */
enum ZonaSemana: string
{
    case Notas = 'notas';
    case Pendiente = 'pendiente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Notas => 'Notas',
            self::Pendiente => 'Pendiente',
        };
    }
}
