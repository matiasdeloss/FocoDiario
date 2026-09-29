<?php

namespace App\Support;

/** Texto de la insignia de Tareas abiertas de Hoy (el mismo criterio vive en hoy-lista-logica.js). */
class TextoPendientes
{
    public static function para(int $pendientes): string
    {
        return match (true) {
            $pendientes <= 0 => 'Todo al día',
            $pendientes === 1 => '1 pendiente',
            default => $pendientes.' pendientes',
        };
    }
}
