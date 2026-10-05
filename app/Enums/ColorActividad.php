<?php

namespace App\Enums;

/**
 * Paleta cerrada de colores para las actividades de la agenda. El valor es el tono de acento en hex,
 * que es lo que se guarda en contextos.color. Los tonos de fondo y de texto de cada color viven como
 * variables en resources/css/organic.css (--actividad-{clave}-fondo, -acento y -texto).
 */
enum ColorActividad: string
{
    case Terracota = '#c0663a';
    case Salvia = '#728a58';
    case Ocre = '#a97f1c';
    case AzulPolvo = '#5f86a3';
    case Ciruela = '#8e4f73';
    case Rosa = '#c0677a';
    case Oliva = '#78802a';
    case Arena = '#9a8350';
    case Celeste = '#3b8e9b';
    case Lavanda = '#8378b5';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Terracota => 'Terracota',
            self::Salvia => 'Salvia',
            self::Ocre => 'Ocre',
            self::AzulPolvo => 'Azul polvo',
            self::Ciruela => 'Ciruela',
            self::Rosa => 'Rosa',
            self::Oliva => 'Oliva',
            self::Arena => 'Arena',
            self::Celeste => 'Celeste',
            self::Lavanda => 'Lavanda',
        };
    }

    /** Clave usada en los nombres de variable y de clase CSS. */
    public function clave(): string
    {
        return match ($this) {
            self::AzulPolvo => 'azul-polvo',
            default => strtolower($this->name),
        };
    }

    /** Clase CSS que define las variables --caja-fondo, --caja-acento y --caja-texto. */
    public function clase(): string
    {
        return 'actividad-'.$this->clave();
    }

    /** Tono suave de fondo (variable CSS de organic.css, con su versión oscura) para teñir una nota o una tarjeta. */
    public function fondo(): string
    {
        return 'var(--actividad-'.$this->clave().'-fondo)';
    }

    /** Tono de acento para la marca de una nota o una tarjeta. */
    public function marca(): string
    {
        return 'var(--actividad-'.$this->clave().'-acento)';
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
