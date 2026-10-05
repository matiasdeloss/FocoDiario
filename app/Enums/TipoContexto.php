<?php

namespace App\Enums;

enum TipoContexto: string
{
    case Entorno = 'entorno';
    case Materia = 'materia';
    case Tema = 'tema';
    case Proyecto = 'proyecto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entorno => 'Entorno',
            self::Materia => 'Materia',
            self::Tema => 'Tema',
            self::Proyecto => 'Proyecto',
        };
    }
}
