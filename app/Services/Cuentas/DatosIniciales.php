<?php

namespace App\Services\Cuentas;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\User;

/** Lo que necesita cada usuario nuevo para arrancar: las tres columnas de fábrica del tablero. */
class DatosIniciales
{
    public const COLUMNAS = [
        ['nombre' => 'Pendiente', 'categoria' => EstadoTarea::Pendiente],
        ['nombre' => 'En progreso', 'categoria' => EstadoTarea::EnProgreso],
        ['nombre' => 'Completada', 'categoria' => EstadoTarea::Completada],
    ];

    public function crear(User $usuario): void
    {
        foreach (self::COLUMNAS as $posicion => $columna) {
            $nueva = new ColumnaTablero([...$columna, 'posicion' => $posicion]);
            $nueva->user_id = $usuario->id;
            $nueva->save();
        }
    }
}
