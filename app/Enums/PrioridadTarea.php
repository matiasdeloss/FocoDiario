<?php

namespace App\Enums;

enum PrioridadTarea: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
        };
    }

    /** Clase del badge definido en app.css. */
    public function claseBadge(): string
    {
        return 'badge-prioridad-'.$this->value;
    }
}
