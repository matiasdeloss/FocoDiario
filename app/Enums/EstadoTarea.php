<?php

namespace App\Enums;

enum EstadoTarea: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Completada = 'completada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En progreso',
            self::Completada => 'Completada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Pendiente => 'bi-circle',
            self::EnProgreso => 'bi-play-circle',
            self::Completada => 'bi-check-circle',
        };
    }

    /** Clase del badge definido en app.css. */
    public function claseBadge(): string
    {
        return match ($this) {
            self::Pendiente => 'badge-estado-pendiente',
            self::EnProgreso => 'badge-estado-en-curso',
            self::Completada => 'badge-estado-completada',
        };
    }
}
