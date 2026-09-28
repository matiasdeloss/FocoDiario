<?php

namespace App\Enums;

enum TipoIntervalo: string
{
    case Foco = 'foco';
    case Descanso = 'descanso';
    case Libre = 'libre';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Foco => 'Foco',
            self::Descanso => 'Descanso',
            self::Libre => 'Tiempo libre',
        };
    }
}
