<?php

namespace App\Support;

use App\Enums\ColorActividad;

/**
 * Color con el que se dibuja una nota, una tarea o un contexto: tonos de fondo y de marca (valores CSS),
 * si es propio o heredado y, cuando es heredado, el nombre del contexto del que viene.
 */
final readonly class ColorVisible
{
    public function __construct(
        public ColorActividad $color,
        public bool $propio,
        public ?string $origen = null,
    ) {}

    public function fondo(): string
    {
        return $this->color->fondo();
    }

    public function marca(): string
    {
        return $this->color->marca();
    }
}
