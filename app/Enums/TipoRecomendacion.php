<?php

namespace App\Enums;

enum TipoRecomendacion: string
{
    case Alerta = 'alerta';
    case Sugerencia = 'sugerencia';
    case Logro = 'logro';
    case Info = 'info';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Alerta => 'Alerta',
            self::Sugerencia => 'Sugerencia',
            self::Logro => 'Logro',
            self::Info => 'Información',
        };
    }

    public function etiquetaPlural(): string
    {
        return match ($this) {
            self::Alerta => 'Alertas',
            self::Sugerencia => 'Sugerencias',
            self::Logro => 'Logros',
            self::Info => 'Información',
        };
    }

    /** Clase del badge definido en app.css. */
    public function claseBadge(): string
    {
        return match ($this) {
            self::Alerta => 'badge-estado-vencida',
            self::Sugerencia => 'badge-estado-pendiente',
            self::Logro => 'badge-estado-completada',
            self::Info => 'badge-estado-en-curso',
        };
    }
}
