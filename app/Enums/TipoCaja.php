<?php

namespace App\Enums;

/** Cómo se escribe el contenido de una caja de la agenda. */
enum TipoCaja: string
{
    case Texto = 'texto';
    case Lista = 'lista';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Texto => 'Texto',
            self::Lista => 'Lista con casillas',
        };
    }
}
