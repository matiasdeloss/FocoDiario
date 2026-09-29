<?php

namespace App\Services\Recomendaciones;

use App\Enums\CategoriaRecomendacion;

/** Consejo del catálogo. Sin fuente = consejo general. */
final readonly class ConsejoGeneral
{
    public function __construct(
        public string $clave,
        public CategoriaRecomendacion $categoria,
        public string $titulo,
        public string $texto,
        public string $icono,
        public ?Fuente $fuente = null,
        public ?string $ruta = null,
        public ?string $accion = null,
    ) {}
}
