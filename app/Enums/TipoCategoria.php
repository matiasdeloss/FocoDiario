<?php

namespace App\Enums;

enum TipoCategoria: string
{
    case Productiva = 'productiva';
    case Ocio = 'ocio';
    case Descanso = 'descanso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Productiva => 'Productiva',
            self::Ocio => 'Ocio',
            self::Descanso => 'Descanso',
        };
    }
}
