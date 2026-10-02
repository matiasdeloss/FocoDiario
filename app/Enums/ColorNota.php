<?php

namespace App\Enums;

/** Colores que se pueden elegir para una nota. Salen de la paleta Organic (rampas de terracota, salvia y arena). */
enum ColorNota: string
{
    case Durazno = 'durazno';
    case Salvia = 'salvia';
    case Arena = 'arena';
    case Terracota = 'terracota';
    case Oliva = 'oliva';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Durazno => 'Durazno',
            self::Salvia => 'Salvia',
            self::Arena => 'Arena',
            self::Terracota => 'Terracota',
            self::Oliva => 'Oliva',
        };
    }

    /** Tono suave con el que se tiñe el fondo de la nota. */
    public function fondo(): string
    {
        return match ($this) {
            self::Durazno => 'var(--color-accent-200)',
            self::Salvia => 'var(--color-accent-2-200)',
            self::Arena => 'var(--color-neutral-200)',
            self::Terracota => 'var(--color-accent-300)',
            self::Oliva => 'var(--color-accent-2-300)',
        };
    }

    /** Tono de la misma rampa, más fuerte y distinto en cada color, para la marca y el selector. */
    public function marca(): string
    {
        return match ($this) {
            self::Durazno => 'var(--color-nota-durazno)',
            self::Terracota => 'var(--color-nota-terracota)',
            self::Salvia => 'var(--color-nota-salvia)',
            self::Oliva => 'var(--color-nota-oliva)',
            self::Arena => 'var(--color-nota-arena)',
        };
    }
}
