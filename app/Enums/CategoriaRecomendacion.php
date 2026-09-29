<?php

namespace App\Enums;

enum CategoriaRecomendacion: string
{
    case Estudio = 'estudio';
    case Salud = 'salud';
    case Productividad = 'productividad';
    case Bienestar = 'bienestar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Estudio => 'Estudio',
            self::Salud => 'Salud y hábitos',
            self::Productividad => 'Productividad',
            self::Bienestar => 'Bienestar',
        };
    }
}
