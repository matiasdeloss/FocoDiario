<?php

namespace App\Enums;

enum EstadoSesion: string
{
    case EnCurso = 'en_curso';
    case Finalizada = 'finalizada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnCurso => 'En curso',
            self::Finalizada => 'Finalizada',
        };
    }

    /** Clase del badge definido en app.css. */
    public function claseBadge(): string
    {
        return match ($this) {
            self::EnCurso => 'badge-estado-en-curso',
            self::Finalizada => 'badge-estado-completada',
        };
    }
}
