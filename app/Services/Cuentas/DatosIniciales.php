<?php

namespace App\Services\Cuentas;

use App\Enums\EstadoTarea;
use App\Models\Tablero;
use App\Models\User;

/** Lo que necesita cada usuario nuevo para arrancar: el tablero principal con "Sin asignar" y las tres columnas de fábrica. */
class DatosIniciales
{
    public const COLUMNAS = [
        ['nombre' => 'Pendiente', 'categoria' => EstadoTarea::Pendiente],
        ['nombre' => 'En progreso', 'categoria' => EstadoTarea::EnProgreso],
        ['nombre' => 'Completada', 'categoria' => EstadoTarea::Completada],
    ];

    public function crear(User $usuario): void
    {
        Tablero::crearConColumnas(
            $usuario->id,
            'Principal',
            true,
            array_map(fn ($c) => [$c['nombre'], $c['categoria']], self::COLUMNAS),
            0,
        );
    }
}
